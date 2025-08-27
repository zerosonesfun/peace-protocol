<?php
/**
 * REST Endpoints and Authentication Handlers for Peace Protocol
 * 
 * IMPORTANT: This file contains inline <script> tags for authentication pages
 * that load outside the WordPress environment. These scripts cannot use
 * wp_enqueue_script() or wp_add_inline_script() because WordPress is not loaded
 * on these pages. This is a legitimate exception to WordPress coding standards
 * for OAuth flows and authentication callbacks.
 * 
 * The inline scripts are only used on:
 * - Authentication callback pages (peace_auth parameter)
 * - Redirect pages (peace_redirect parameter) 
 * - Pages that must work without WordPress loaded
 * 
 * All other scripts in this plugin use proper wp_enqueue_script() functions.
 */

defined('ABSPATH') || exit;

/**
 * Generate a secure URL-safe token for Peace Protocol
 * Uses only alphanumeric characters, hyphens, and underscores to avoid WordPress sanitization issues
 * 
 * @param int $length The length of the token (default: 32)
 * @return string The generated token
 */
function peaceprotocol_generate_secure_token($length = 32) {
    // Use only URL-safe characters: A-Z, a-z, 0-9, -, _
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_';
    $token = '';
    
    // Generate cryptographically secure random bytes
    $random_bytes = random_bytes($length);
    
    for ($i = 0; $i < $length; $i++) {
        $token .= $chars[ord($random_bytes[$i]) % strlen($chars)];
    }
    
    return $token;
}

function peaceprotocol_receive_peace($request) {
    // error_log('Peace Protocol REST: receive_peace called');
    // error_log('Peace Protocol REST: Request parameters: ' . print_r($request->get_params(), true));
    
    $target_site = sanitize_url($request->get_param('target_site'));
    $message = sanitize_textarea_field($request->get_param('message'));
    $token = sanitize_text_field($request->get_param('token'));
    $from_site = sanitize_url($request->get_param('from_site'));
    
    // error_log('Peace Protocol REST: Target site: ' . $target_site);
    // error_log('Peace Protocol REST: Token: ' . $token);
    // error_log('Peace Protocol REST: From site: ' . $from_site);
    
    // Validate token
    $identity = peaceprotocol_validate_token($token);
    // error_log('Peace Protocol REST: Token validation result: ' . print_r($identity, true));
    
    if (!$identity) {
        // error_log('Peace Protocol REST: Token validation failed');
        return new WP_Error('invalid_token', 'Invalid token', array('status' => 403));
    }
    
    // Check if the sending site's user is banned (for federated users)
    if (function_exists('peaceprotocol_is_user_banned') && peaceprotocol_is_user_banned()) {
        // error_log('Peace Protocol REST: Banned user attempted to send peace');
        return new WP_Error('user_banned', 'You are banned from sending peace', array('status' => 403));
    }
    
    // Use from_site if provided, otherwise fall back to token's site_url
    $sending_site = $from_site ?: $identity['site_url'];
    
    // error_log('Peace Protocol REST: Identity validated: ' . print_r($identity, true));
    // error_log('Peace Protocol REST: Using sending site: ' . $sending_site);
    
    // Save Peace Log directly (don't call send_peace_to_site which would create infinite loop)
    $log_id = wp_insert_post([
        'post_type' => 'peaceprotocol_log',
        'post_title' => 'Peace from ' . $sending_site,
        'post_content' => $message,
        'post_status' => 'publish',
        'meta_input' => ['from_site' => $sending_site, 'note' => $message]
    ]);

    // error_log('Peace Protocol REST: Peace log created with ID: ' . $log_id);
    
    if (is_wp_error($log_id)) {
        // error_log('Peace Protocol REST: Failed to create peace log: ' . $log_id->get_error_message());
        return new WP_Error('save_failed', 'Failed to save peace log', array('status' => 500));
    }
    
    // error_log('Peace Protocol REST: Peace received and saved successfully');
    return new WP_REST_Response(array('message' => 'Peace received successfully', 'log_id' => $log_id), 200);
}

add_action('init', function () {
    // error_log('Peace Protocol: init hook - registering REST routes');
});

add_action('init', function () {
    // error_log('Peace Protocol: Registering REST routes on init');
add_action('rest_api_init', function () {
        // error_log('Peace Protocol: rest_api_init hook fired');
        register_rest_route('peace-protocol/v1', '/receive', [
        'methods' => 'POST',
        'callback' => 'peaceprotocol_receive_peace',
                    'permission_callback' => function() {
            // This endpoint is intentionally public for cross-site peace protocol federation
            // Security is handled via token validation in the callback function
            return true;
        }
        ]);
        // Add a test endpoint for debugging
        register_rest_route('peace-protocol/v1', '/test', [
            'methods' => 'GET',
                    'callback' => function() { 
            return new WP_REST_Response(['success' => true], 200);
        },
        'permission_callback' => function() {
            // This endpoint is intentionally public for testing peace protocol connectivity
            return true;
        }
        ]);
        // error_log('Peace Protocol: REST routes registered');
    });
});

// Add CORS headers for REST API
add_action('rest_api_init', function() {
    add_filter('rest_pre_serve_request', function($served, $result, $request, $server) {
        if (strpos($request->get_route(), '/peace-protocol/') === 0) {
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization');
        }
        return $served;
    }, 10, 4);
});

// Handle preflight OPTIONS requests
add_action('rest_api_init', function() {
    add_filter('rest_pre_dispatch', function($result, $server, $request) {
        if ($request->get_method() === 'OPTIONS' && strpos($request->get_route(), '/peace-protocol/') === 0) {
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization');
            return new WP_REST_Response([], 200);
        }
        return $result;
    }, 10, 3);
});

// Discover IndieAuth metadata from a URL (server-side version) - moved here to avoid function order issues
function peaceprotocol_discover_indieauth_metadata($url) {
    error_log("Peace Protocol: Starting server-side IndieAuth discovery for: {$url}");
    
    try {
        // Fetch the URL to find indieauth-metadata link
        $response = wp_remote_get($url, array(
            'timeout' => 30,
            'headers' => array(
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8'
            )
        ));
    
    if (is_wp_error($response)) {
        error_log('Peace Protocol: Failed to fetch URL for IndieAuth discovery: ' . $response->get_error_message());
        return false;
    }
    
    $status_code = wp_remote_retrieve_response_code($response);
    error_log("Peace Protocol: HTTP response status for {$url}: {$status_code}");
    
    if ($status_code !== 200) {
        error_log("Peace Protocol: HTTP {$status_code} when fetching URL for IndieAuth discovery: {$url}");
        return false;
    }
    
    // Look for indieauth-metadata in Link headers first
    $link_header = wp_remote_retrieve_header($response, 'Link');
    error_log("Peace Protocol: Link header found: " . (is_array($link_header) ? json_encode($link_header) : $link_header));
    $metadata_url = null;
    
    if ($link_header) {
        // Handle case where Link header is an array (multiple Link headers)
        if (is_array($link_header)) {
            $link_header = implode(', ', $link_header);
        }
        
        $links = explode(',', $link_header);
        error_log("Peace Protocol: Parsed links: " . json_encode($links));
        
        foreach ($links as $link) {
            $parts = explode(';', $link);
            $link_url = trim($parts[0], ' <>');
            $rel = '';
            
            foreach ($parts as $part) {
                if (strpos($part, 'rel=') === 0) {
                    $rel = trim($part, ' "');
                    $rel = str_replace('rel=', '', $rel);
                    break;
                }
            }
            
            error_log("Peace Protocol: Link - URL: {$link_url}, rel: {$rel}");
            
            if ($rel === 'indieauth-metadata') {
                $metadata_url = $link_url;
                error_log("Peace Protocol: Found indieauth-metadata URL: {$metadata_url}");
                break;
            }
        }
    }
    
    // Get HTML body for parsing if needed
    $html = wp_remote_retrieve_body($response);
    
    // If no Link header, parse HTML for <link> tags
    if (!$metadata_url) {
        // Simple regex to find link tags (for simplicity, not full HTML parsing)
        if (preg_match('/<link[^>]*rel=["\']indieauth-metadata["\'][^>]*href=["\']([^"\']+)["\'][^>]*>/i', $html, $matches)) {
            $metadata_url = $matches[1];
            // Make sure it's an absolute URL
            if (!filter_var($metadata_url, FILTER_VALIDATE_URL)) {
                $metadata_url = rtrim($url, '/') . '/' . ltrim($metadata_url, '/');
            }
        }
    }
    
    // If still no metadata URL, try legacy discovery
    if (!$metadata_url) {
        // Try to find authorization_endpoint directly in Link headers
        if ($link_header) {
            // Handle case where Link header is an array (multiple Link headers)
            if (is_array($link_header)) {
                $link_header = implode(', ', $link_header);
            }
            
            $links = explode(',', $link_header);
            foreach ($links as $link) {
                $parts = explode(';', $link);
                $link_url = trim($parts[0], ' <>');
                $rel = '';
                
                foreach ($parts as $part) {
                    if (strpos($part, 'rel=') === 0) {
                        $rel = trim($part, ' "');
                        $rel = str_replace('rel=', '', $rel);
                        break;
                    }
                }
                
                if ($rel === 'authorization_endpoint') {
                    return array(
                        'authorization_endpoint' => $link_url,
                        'token_endpoint' => null
                    );
                }
            }
        }
        
        // Try HTML link elements for legacy discovery
        if (preg_match('/<link[^>]*rel=["\']authorization_endpoint["\'][^>]*href=["\']([^"\']+)["\'][^>]*>/i', $html, $matches)) {
            return array(
                'authorization_endpoint' => $matches[1],
                'token_endpoint' => null
            );
        }
        
        error_log("Peace Protocol: No IndieAuth metadata or authorization endpoint found for: {$url}");
        return false;
    }
    
    // Fetch the metadata document
    $metadata_response = wp_remote_get($metadata_url, array(
        'timeout' => 30,
        'headers' => array(
            'Accept' => 'application/json'
        )
    ));
    
    if (is_wp_error($metadata_response)) {
        error_log('Peace Protocol: Failed to fetch metadata document: ' . $metadata_response->get_error_message());
        return false;
    }
    
    $metadata_status = wp_remote_retrieve_response_code($metadata_response);
    if ($metadata_status !== 200) {
        error_log("Peace Protocol: HTTP {$metadata_status} when fetching metadata document: {$metadata_url}");
        return false;
    }
    
    $metadata_body = wp_remote_retrieve_body($metadata_response);
    $metadata = json_decode($metadata_body, true);
    
    if (!$metadata || !isset($metadata['authorization_endpoint'])) {
        error_log("Peace Protocol: Invalid metadata document or missing authorization_endpoint: {$metadata_url}");
        return false;
    }
    
    return $metadata;
    } catch (Exception $e) {
        error_log("Peace Protocol: Exception in IndieAuth discovery: " . $e->getMessage());
        error_log("Peace Protocol: Exception trace: " . $e->getTraceAsString());
        return false;
    } catch (Error $e) {
        error_log("Peace Protocol: Fatal error in IndieAuth discovery: " . $e->getMessage());
        error_log("Peace Protocol: Error trace: " . $e->getTraceAsString());
        return false;
    }
}

// AJAX fallback for receiving peace (when REST API is disabled)
add_action('wp_ajax_peaceprotocol_receive_peace', function() {
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Cross-site endpoint uses token-based authentication
    // error_log('Peace Protocol: AJAX receive_peace handler called (logged in)');
    
    // Check if current user is banned
    if (function_exists('peaceprotocol_is_user_banned') && peaceprotocol_is_user_banned()) {
        // error_log('Peace Protocol: Banned user attempted to receive peace via AJAX');
        wp_send_json_error('You are banned from receiving peace', 403);
    }
    
    if (!isset($_POST['from_site']) || !isset($_POST['token']) || !isset($_POST['note'])) {
        // error_log('Peace Protocol: AJAX missing required fields');
        wp_send_json_error('Missing required fields');
    }
    
    $from = sanitize_text_field(wp_unslash($_POST['from_site']));
    $token = sanitize_text_field(wp_unslash($_POST['token']));
    $note = sanitize_text_field(wp_unslash($_POST['note']));
    
    // error_log('Peace Protocol: AJAX from_site=' . $from . ', token=' . substr($token, 0, 8) . '..., note=' . $note);

    // Check if token is valid for the from_site
    // First check current site's tokens
    $tokens = get_option('peaceprotocol_tokens', []);
    // error_log('Peace Protocol: AJAX peace_tokens: ' . print_r($tokens, true));
    $token_valid = in_array($token, $tokens, true);
    
    // If not valid in current site, check if it's a federated token
    if (!$token_valid) {
        // Check if this token was issued via federated exchange
        $codes = get_option('peaceprotocol_federated_codes', []);
        // error_log('Peace Protocol: AJAX peace_federated_codes: ' . print_r($codes, true));
        foreach ($codes as $code_data) {
            // error_log('Peace Protocol: AJAX checking code_data: ' . print_r($code_data, true));
            if ($code_data['token'] === $token && $code_data['site'] === $from) {
                $token_valid = true;
                // error_log('Peace Protocol: AJAX token matched in federated codes');
                break;
            }
        }
    }
    
    if (!$token_valid) {
        // error_log('Peace Protocol: AJAX invalid token');
        wp_send_json_error('Invalid token', 403);
    }

    // Save Peace Log
    $log_id = wp_insert_post([
        'post_type' => 'peaceprotocol_log',
        'post_title' => 'Peace from ' . $from,
        'post_content' => $note,
        'post_status' => 'publish',
        'meta_input' => ['from_site' => $from, 'note' => $note]
    ]);

    // error_log('Peace Protocol: AJAX peace log created with ID: ' . $log_id);
    wp_send_json_success(['log_id' => $log_id]);
});

add_action('wp_ajax_nopriv_peaceprotocol_receive_peace', function() {
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Cross-site endpoint uses token-based authentication
    // error_log('Peace Protocol: AJAX receive_peace handler called (not logged in)');
    if (!isset($_POST['from_site']) || !isset($_POST['token']) || !isset($_POST['note'])) {
        // error_log('Peace Protocol: AJAX missing required fields (nopriv)');
        wp_send_json_error('Missing required fields');
    }
    
    $from = sanitize_text_field(wp_unslash($_POST['from_site']));
    $token = sanitize_text_field(wp_unslash($_POST['token']));
    $note = sanitize_text_field(wp_unslash($_POST['note']));
    
    // error_log('Peace Protocol: AJAX nopriv from_site=' . $from . ', token=' . substr($token, 0, 8) . '..., note=' . $note);

    // Check if token is valid for the from_site
    // First check current site's tokens
    $tokens = get_option('peaceprotocol_tokens', []);
    $token_valid = in_array($token, $tokens, true);
    
    // If not valid in current site, check if it's a federated token
    if (!$token_valid) {
        // Check if this token was issued via federated exchange
        $codes = get_option('peaceprotocol_federated_codes', []);
        foreach ($codes as $code_data) {
            if ($code_data['token'] === $token && $code_data['site'] === $from) {
                $token_valid = true;
                break;
            }
        }
    }
    
    if (!$token_valid) {
        // error_log('Peace Protocol: AJAX nopriv invalid token');
        wp_send_json_error('Invalid token', 403);
    }

    // Save Peace Log
    $log_id = wp_insert_post([
        'post_type' => 'peaceprotocol_log',
        'post_title' => 'Peace from ' . $from,
        'post_content' => $note,
        'post_status' => 'publish',
        'meta_input' => ['from_site' => $from, 'note' => $note]
    ]);

    // error_log('Peace Protocol: AJAX nopriv peace log created with ID: ' . $log_id);
    wp_send_json_success(['log_id' => $log_id]);
});

// AJAX fallback for federated auth (when REST API is disabled)
add_action('wp_ajax_peaceprotocol_federated_auth', function() {
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Cross-site endpoint uses token-based authentication
    if (!isset($_POST['token']) || !isset($_POST['remote_site']) || !isset($_POST['state'])) {
        wp_send_json_error('Missing required fields');
    }
    
    $token = sanitize_text_field(wp_unslash($_POST['token']));
    $remote_site = esc_url_raw(wp_unslash($_POST['remote_site']));
    $state = sanitize_text_field(wp_unslash($_POST['state']));
    
    $tokens = get_option('peaceprotocol_tokens', []);
    if (!in_array($token, $tokens, true)) {
        wp_send_json_error('Invalid token', 403);
    }
    
    $active_token = is_array($tokens) && count($tokens) ? $tokens[0] : '';
    $code = peaceprotocol_generate_secure_token(20);
    $expires = time() + 300; // 5 minutes
    $codes = get_option('peaceprotocol_codes', array());
    $codes[$code] = [
        'site_url' => get_site_url(),
        'expires' => $expires,
    ];
    update_option('peaceprotocol_codes', $codes);
    wp_send_json_success(['code' => $code]);
});

add_action('wp_ajax_nopriv_peaceprotocol_federated_auth', function() {
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Cross-site endpoint uses token-based authentication
    if (!isset($_POST['token']) || !isset($_POST['remote_site']) || !isset($_POST['state'])) {
        wp_send_json_error('Missing required fields');
    }
    
    $token = sanitize_text_field(wp_unslash($_POST['token']));
    $remote_site = esc_url_raw(wp_unslash($_POST['remote_site']));
    $state = sanitize_text_field(wp_unslash($_POST['state']));
    
    $tokens = get_option('peaceprotocol_tokens', []);
    if (!in_array($token, $tokens, true)) {
        wp_send_json_error('Invalid token', 403);
    }
    
    $active_token = is_array($tokens) && count($tokens) ? $tokens[0] : '';
    $code = peaceprotocol_generate_secure_token(20);
    $expires = time() + 300; // 5 minutes
    $codes = get_option('peaceprotocol_codes', array());
    $codes[$code] = [
        'site_url' => get_site_url(),
        'expires' => $expires,
    ];
    update_option('peaceprotocol_codes', $codes);
    wp_send_json_success(['code' => $code]);
});

// AJAX fallback for federated exchange (when REST API is disabled)
add_action('wp_ajax_peaceprotocol_federated_exchange', 'peaceprotocol_ajax_federated_exchange');
add_action('wp_ajax_nopriv_peaceprotocol_federated_exchange', 'peaceprotocol_ajax_federated_exchange');

function peaceprotocol_ajax_federated_exchange() {
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Cross-site endpoint uses token-based authentication
    // error_log('Peace Protocol AJAX: federated_exchange called');
    // error_log('Peace Protocol AJAX: POST data: ' . print_r($_POST, true));
    
    if (!isset($_POST['code']) || !isset($_POST['site'])) {
        wp_send_json_error('Missing required fields');
    }
    
    $code = sanitize_text_field(wp_unslash($_POST['code']));
    $site = esc_url_raw(wp_unslash($_POST['site']));
    
    // error_log('Peace Protocol AJAX: Code: ' . $code . ', Site: ' . $site);
    
    // Check if this is an authorization code (new system) or a regular code (old system)
    $authorizations = get_option('peaceprotocol_authorizations', array());
    
    if (isset($authorizations[$code])) {
        // This is an authorization code from the new system
        $auth_data = $authorizations[$code];
        
        if ($auth_data['expires'] < time() || $auth_data['used']) {
            // error_log('Peace Protocol AJAX: Authorization code expired or used');
            unset($authorizations[$code]);
            update_option('peaceprotocol_authorizations', $authorizations);
            wp_die('Authorization code expired or used', 403);
        }
        
        // Mark as used
        $authorizations[$code]['used'] = true;
        update_option('peaceprotocol_authorizations', $authorizations);
        
        // Generate new token for this site
        $token = peaceprotocol_generate_secure_token(64);
        $expires = time() + 86400; // 24 hours
        
        // Store in federated identities so this site knows about the token
        $federated_identities = get_option('peaceprotocol_federated_identities', array());
        $federated_identities[] = array(
            'site_url' => $auth_data['site_url'],
            'token' => $token,
            'expires' => $expires
        );
        update_option('peaceprotocol_federated_identities', $federated_identities);
        
        // error_log('Peace Protocol AJAX: Exchanged authorization code for token: ' . $token . ' for site: ' . $auth_data['site_url']);
        
        wp_die(json_encode(array('success' => true, 'token' => $token)), 200);
    } else {
        // This is a regular code from the old system
        $codes = get_option('peaceprotocol_codes', array());
        // error_log('Peace Protocol AJAX: Available codes: ' . print_r($codes, true));
        
        if (!isset($codes[$code])) {
            // error_log('Peace Protocol AJAX: Code not found');
            wp_die('Invalid code', 403);
        }
        
        $code_data = $codes[$code];
        // error_log('Peace Protocol AJAX: Code data: ' . print_r($code_data, true));
        
        if ($code_data['expires'] < time()) {
            // error_log('Peace Protocol AJAX: Code expired');
            unset($codes[$code]);
            update_option('peaceprotocol_codes', $codes);
            wp_die('Code expired', 403);
        }
        
        // Generate new token for this site
        $token = peaceprotocol_generate_secure_token(64);
        $expires = time() + 86400; // 24 hours
        
        // Store in federated identities so this site knows about the token
        $federated_identities = get_option('peaceprotocol_federated_identities', array());
        $federated_identities[] = array(
            'site_url' => $code_data['site_url'],
            'token' => $token,
            'expires' => $expires
        );
        update_option('peaceprotocol_federated_identities', $federated_identities);
        
        // Remove used code
        unset($codes[$code]);
        update_option('peaceprotocol_codes', $codes);
        
        // error_log('Peace Protocol AJAX: Exchanged code for token: ' . $token . ' for site: ' . $code_data['site_url']);
        // error_log('Peace Protocol AJAX: Federated identities after exchange: ' . print_r($federated_identities, true));
        
        wp_die(json_encode(array('success' => true, 'token' => $token)), 200);
    }
}

// Federated login endpoints
add_action('template_redirect', function () {
    // Handle ?peace_get_token=1&return_site=...&state=... (normal flow)
    if (isset($_GET['peace_get_token']) && $_GET['peace_get_token'] == '1' && isset($_GET['return_site']) && isset($_GET['state'])) {
        $return_site = esc_url_raw(wp_unslash($_GET['return_site']));
        $state = sanitize_text_field(wp_unslash($_GET['state']));
        
        // error_log('Peace Protocol: peace_get_token called with return_site: ' . $return_site . ', state: ' . $state);
        
        // Check if user is admin and has tokens
        if (current_user_can('manage_options') && get_option('peaceprotocol_tokens')) {
            $tokens = get_option('peaceprotocol_tokens', []);
            if (empty($tokens)) {
                // error_log('Peace Protocol: No tokens available');
                return;
            }
            
            // Generate authorization code
            $auth_code = peaceprotocol_generate_secure_token(32);
            $expires = time() + 300; // 5 minutes
            
            // Store authorization code
            $authorizations = get_option('peaceprotocol_authorizations', array());
            $authorizations[$auth_code] = array(
                'site_url' => get_site_url(),
                'return_site' => $return_site,
                'expires' => $expires,
                'used' => false
            );
            update_option('peaceprotocol_authorizations', $authorizations);
            
            // error_log('Peace Protocol: Generated authorization code: ' . $auth_code . ' for return site: ' . $return_site);
            
            // Subscribe this site (siteA) to the return site's (siteB) feed
            // error_log('Peace Protocol: Subscribing ' . get_site_url() . ' to ' . $return_site . ' feed');
            peaceprotocol_subscribe_to_feed($return_site);
            
            // Redirect back to return site with authorization code
            $redirect_url = $return_site;
            $separator = strpos($return_site, '?') !== false ? '&' : '?';
            $redirect_url .= $separator . 'peace_authorization_code=' . $auth_code . '&peace_federated_site=' . get_site_url() . '&peace_federated_state=' . $state;
            
            // error_log('Peace Protocol: Redirecting to: ' . $redirect_url);
            
            // Add JavaScript to set up localStorage identity before redirect
            ?>
            <script>
            // Set up localStorage identity for the federated user
            (function() {
                const identities = JSON.parse(localStorage.getItem('peace-protocol-identities') || '[]');
                const newIdentity = {
                    site: '<?php echo esc_js($return_site); ?>',
                    token: '<?php echo esc_js($auth_code); ?>'
                };
                
                // Remove any existing identity for this site
                const filteredIdentities = identities.filter(id => id.site !== '<?php echo esc_js($return_site); ?>');
                
                // Add the new identity
                filteredIdentities.push(newIdentity);
                
                localStorage.setItem('peace-protocol-identities', JSON.stringify(filteredIdentities));
                // console.log('[Peace Protocol] Set up localStorage identity for federated user:', newIdentity);
                
                // Also set up the authorization code in localStorage for the frontend to use
                const authCode = '<?php echo esc_js($auth_code); ?>';
                if (authCode) {
                    let authorizations = [];
                    const stored = localStorage.getItem('peace-protocol-authorizations');
                    if (stored) {
                        authorizations = JSON.parse(stored);
                        if (!Array.isArray(authorizations)) authorizations = [];
                    }
                    
                    // Add the authorization code
                    const newAuth = {
                        site: '<?php echo esc_js($return_site); ?>',
                        code: authCode,
                        timestamp: Date.now()
                    };
                    
                    // Remove any existing authorization for this site
                    authorizations = authorizations.filter(auth => auth.site !== '<?php echo esc_js($return_site); ?>');
                    
                    // Add the new authorization
                    authorizations.push(newAuth);
                    
                    localStorage.setItem('peace-protocol-authorizations', JSON.stringify(authorizations));
                    // console.log('[Peace Protocol] Set up authorization code for federated user:', newAuth);
                }
            })();
            </script>
            <?php
            
            wp_redirect($redirect_url);
            exit;
        } else {
            // error_log('Peace Protocol: User is not admin or no tokens, showing login page');
            // Not admin or no tokens: show a page with JS to check localStorage for a valid token
            ?><!DOCTYPE html><html><head><title>Peace Protocol Federated Auth</title><style>
            body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; line-height: 1.6; }
            .container { background: #f9f9f9; border-radius: 8px; padding: 30px; text-align: center; }
            .login-link { display: inline-block; background: #0073aa; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; margin: 20px 0; }
            .login-link:hover { background: #005a87; }
            .error { color: #d63638; background: #fef7f1; padding: 15px; border-radius: 4px; margin: 20px 0; }
            /* Dark mode support */
            @media (prefers-color-scheme: dark) {
                body { background: #1a1a1a; color: #eee; }
                .container { background: #222; color: #eee; }
                .error { background: #2d1b1b; color: #fca5a5; }
            }
            </style></head><body>
            <div class="container">
                <h2>Peace Protocol Federated Login</h2>
                <p>To send peace as this site, you need to authenticate.</p>
                
                <div id="auth-status">
                    <p>Checking for existing authentication...</p>
                </div>
                
                <div id="login-options" style="display: none;">
                    <p><strong>Choose an authentication method:</strong></p>
                    <?php 
                    $current_url = add_query_arg(['peace_get_token' => '1', 'return_site' => $return_site, 'state' => $state], get_site_url());
                    ?>
                    <a href="<?php echo esc_url(wp_login_url($current_url)); ?>" class="login-link" onclick="localStorage.setItem('peace-login-return-site', '<?php echo esc_js($return_site); ?>'); localStorage.setItem('peace-login-state', '<?php echo esc_js($state); ?>');">Log in as Admin</a>
                    <p><em>Or if you have a token, it will be detected automatically.</em></p>
                </div>
                
                <div id="error-message" class="error" style="display: none;"></div>
            </div>
            
            <!-- 
            LEGITIMATE EXCEPTION: This inline script is necessary because this page loads outside WordPress environment.
            wp_enqueue_script() and wp_add_inline_script() are not available when WordPress is not loaded.
            This is a security-critical authentication page that must function independently.
            See PLUGIN_REVIEW_NOTES.md for full documentation.
            -->
            <script>
            (function() {
                var statusEl = document.getElementById('auth-status');
                var optionsEl = document.getElementById('login-options');
                var errorEl = document.getElementById('error-message');
                
                // Try to get token from localStorage
                function getIdentities() {
                    try {
                        var val = localStorage.getItem('peace-protocol-identities');
                        if (!val) return [];
                        var arr = JSON.parse(val);
                        if (Array.isArray(arr)) return arr;
                    } catch(e) {}
                    return [];
                }
                
                var identities = getIdentities();
                var siteUrl = '<?php echo esc_js(get_site_url()); ?>';
                var token = '';
                
                for (var i = 0; i < identities.length; ++i) {
                    if (identities[i].site === siteUrl && identities[i].token) {
                        token = identities[i].token;
                        break;
                    }
                }
                
                if (!token) {
                    statusEl.innerHTML = '<p>No existing authentication found.</p>';
                    optionsEl.style.display = 'block';
                    return;
                }
                
                statusEl.innerHTML = '<p>Found existing token, validating...</p>';
                
                // Validate token by checking if it's in the current site's tokens
                var xhr = new XMLHttpRequest();
                var ajaxurl = (typeof window.peaceprotocolData !== 'undefined' && window.peaceprotocolData.ajaxurl) ? window.peaceprotocolData.ajaxurl : '<?php echo esc_js(peaceprotocol_get_admin_ajax_url()); ?>';
                xhr.open('POST', ajaxurl, true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onload = function() {
                    // console.log('Peace Protocol: Token validation response status:', xhr.status);
                    // console.log('Peace Protocol: Token validation response text:', xhr.responseText);
                    
                    if (xhr.status === 200) {
                        try {
                            var data = JSON.parse(xhr.responseText);
                            // console.log('Peace Protocol: Token validation parsed data:', data);
                            
                            if (data && data.success) {
                                // Token is valid, generate authorization code and redirect back
                                statusEl.innerHTML = '<p>Token validated! Generating authorization code...</p>';
                                
                                // Generate authorization code via AJAX
                                var authXhr = new XMLHttpRequest();
                                var authAjaxurl = (typeof window.peaceprotocolData !== 'undefined' && window.peaceprotocolData.ajaxurl) ? window.peaceprotocolData.ajaxurl : '<?php echo esc_js(peaceprotocol_get_admin_ajax_url()); ?>';
                                authXhr.open('POST', authAjaxurl, true);
                                authXhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                                authXhr.onload = function() {
                                    // console.log('Peace Protocol: Auth code generation response status:', authXhr.status);
                                    // console.log('Peace Protocol: Auth code generation response text:', authXhr.responseText);
                                    
                                    if (authXhr.status === 200) {
                                        try {
                                            var authData = JSON.parse(authXhr.responseText);
                                            // console.log('Peace Protocol: Auth code generation parsed data:', authData);
                                            
                                            if (authData && authData.success && authData.data && authData.data.redirect_url) {
                                                statusEl.innerHTML = '<p>Authorization code generated! Redirecting...</p>';
                                                // console.log('Peace Protocol: About to redirect to URL:', authData.data.redirect_url);
                                                
                                                // Subscribe to the return site's feed
                                                var subscribeXhr = new XMLHttpRequest();
                                                var subscribeAjaxurl = (typeof window.peaceprotocolData !== 'undefined' && window.peaceprotocolData.ajaxurl) ? window.peaceprotocolData.ajaxurl : '<?php echo esc_js(peaceprotocol_get_admin_ajax_url()); ?>';
                                                subscribeXhr.open('POST', subscribeAjaxurl, true);
                                                subscribeXhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                                                subscribeXhr.send('action=peaceprotocol_subscribe_feed&feed_url=' + encodeURIComponent(<?php echo wp_json_encode(esc_url($return_site)); ?>) + '&nonce=' + encodeURIComponent(<?php echo wp_json_encode(esc_attr(wp_create_nonce('peaceprotocol_subscribe_feed'))); ?>));
                                                
                                                // Use a small delay to ensure logs are written
                                                setTimeout(function() {
                                                    // console.log('Peace Protocol: Executing redirect now to:', authData.data.redirect_url);
                                                    window.location.href = authData.data.redirect_url;
                                                }, 100);
                                            } else {
                                                statusEl.innerHTML = '<p>Failed to generate authorization code.</p>';
                                                optionsEl.style.display = 'block';
                                            }
                                        } catch(e) {
                                            // console.log('Peace Protocol: Auth code generation JSON parse error:', e);
                                            statusEl.innerHTML = '<p>Failed to generate authorization code.</p>';
                                            optionsEl.style.display = 'block';
                                        }
                                    } else {
                                        // console.log('Peace Protocol: Auth code generation failed with status:', authXhr.status);
                                        statusEl.innerHTML = '<p>Failed to generate authorization code.</p>';
                                        optionsEl.style.display = 'block';
                                    }
                                };
                                authXhr.onerror = function() {
                                    // console.log('Peace Protocol: Auth code generation AJAX error');
                                    statusEl.innerHTML = '<p>Failed to generate authorization code.</p>';
                                    optionsEl.style.display = 'block';
                                };
                                authXhr.send('action=peaceprotocol_complete_auth&return_site=' + encodeURIComponent(<?php echo wp_json_encode(esc_url($return_site)); ?>) + '&state=' + encodeURIComponent(<?php echo wp_json_encode(esc_attr($state)); ?>) + '&token=' + encodeURIComponent(token));
                            } else {
                                statusEl.innerHTML = '<p>Failed to generate authorization code.</p>';
                                optionsEl.style.display = 'block';
                            }
                        } catch(e) {
                            // console.log('Peace Protocol: Auth code generation JSON parse error:', e);
                            statusEl.innerHTML = '<p>Failed to generate authorization code.</p>';
                            optionsEl.style.display = 'block';
                        }
                    } else {
                        // console.log('Peace Protocol: Token validation failed with status:', xhr.status);
                        statusEl.innerHTML = '<p>Token validation failed.</p>';
                        optionsEl.style.display = 'block';
                    }
                };
                xhr.onerror = function() {
                    // console.log('Peace Protocol: Token validation AJAX error');
                    statusEl.innerHTML = '<p>Token validation failed.</p>';
                    optionsEl.style.display = 'block';
                };
                // console.log('Peace Protocol: Sending token validation request with token:', token);
                xhr.send('action=peaceprotocol_validate_token&token=' + encodeURIComponent(token));
            })();
            </script>
            </body></html><?php
            exit;
        }
    }
    
    // Handle ?peace_get_token=1 (after login, missing return_site and state)
    if (isset($_GET['peace_get_token']) && $_GET['peace_get_token'] == '1' && !isset($_GET['return_site'])) {
        // User is logged in but missing return_site and state parameters
        // Show a modal with a link to complete the peace protocol
        if (is_user_logged_in() && current_user_can('manage_options')) {
            ?><!DOCTYPE html><html><head><title>Peace Protocol - Complete Authentication</title><style>
            body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; line-height: 1.6; }
            .container { background: #f9f9f9; border-radius: 8px; padding: 30px; text-align: center; }
            .complete-link { display: inline-block; background: #0073aa; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; margin: 20px 0; font-weight: bold; }
            .complete-link:hover { background: #005a87; }
            .info { background: #e7f3ff; padding: 15px; border-radius: 4px; margin: 20px 0; }
            /* Dark mode support */
            @media (prefers-color-scheme: dark) {
                body { background: #1a1a1a; color: #eee; }
                .container { background: #222; color: #eee; }
                .info { background: #1e3a5f; color: #dbeafe; }
            }
            </style></head><body>
            <div class="container">
                <h2>Peace Protocol Authentication Complete</h2>
                <div class="info">
                    <p><strong>You are now logged in as an admin.</strong></p>
                    <p>Click the button below to complete the peace protocol and return to the original site.</p>
                </div>
                
                <a href="#" id="complete-peace-protocol" class="complete-link">Complete Peace Protocol</a>
                
                <p><em>This will generate an authorization code and redirect you back to the site that requested authentication.</em></p>
            </div>
            
            <script>
            document.getElementById('complete-peace-protocol').addEventListener('click', function(e) {
                e.preventDefault();
                
                // console.log('Peace Protocol: Complete button clicked');
                
                // Get stored return_site and state from localStorage
                var returnSite = localStorage.getItem('peace-login-return-site');
                var state = localStorage.getItem('peace-login-state');
                
                // console.log('Peace Protocol: Return site from localStorage:', returnSite);
                // console.log('Peace Protocol: State from localStorage:', state);
                
                if (!returnSite || !state) {
                    alert('Missing return site or state information. Please try the peace protocol again from the original site.');
                    return;
                }
                
                // Generate authorization code and redirect
                var xhr = new XMLHttpRequest();
                var ajaxurl = (typeof window.peaceprotocolData !== 'undefined' && window.peaceprotocolData.ajaxurl) ? window.peaceprotocolData.ajaxurl : '<?php echo esc_js(peaceprotocol_get_admin_ajax_url()); ?>';
                xhr.open('POST', ajaxurl, true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onload = function() {
                    // console.log('Peace Protocol: AJAX response status:', xhr.status);
                    // console.log('Peace Protocol: AJAX response text:', xhr.responseText);
                    
                    if (xhr.status === 200) {
                        try {
                            var data = JSON.parse(xhr.responseText);
                            // console.log('Peace Protocol: Parsed data:', data);
                            // console.log('Peace Protocol: Data structure check - success:', data.success, 'data:', data.data, 'redirect_url:', data.data ? data.data.redirect_url : 'undefined');
                            
                            if (data && data.success && data.data && data.data.redirect_url) {
                                // console.log('Peace Protocol: Redirecting to:', data.data.redirect_url);
                                // Clean up localStorage
                                localStorage.removeItem('peace-login-return-site');
                                localStorage.removeItem('peace-login-state');
                                window.location.href = data.data.redirect_url;
                            } else {
                                // console.log('Peace Protocol: Missing redirect_url in response. Full response structure:', JSON.stringify(data, null, 2));
                                alert('Failed to complete peace protocol. Please try again.');
                            }
                        } catch(e) {
                            // console.log('Peace Protocol: JSON parse error:', e);
                            // console.log('Peace Protocol: Raw response that failed to parse:', xhr.responseText);
                            alert('Failed to complete peace protocol. Please try again.');
                        }
                    } else {
                        // console.log('Peace Protocol: AJAX failed with status:', xhr.status);
                        // console.log('Peace Protocol: AJAX response text:', xhr.responseText);
                        alert('Failed to complete peace protocol. Please try again.');
                    }
                };
                xhr.onerror = function() {
                    // console.log('Peace Protocol: AJAX error');
                    alert('Failed to complete peace protocol. Please try again.');
                };
                xhr.send('action=peaceprotocol_complete_auth&return_site=' + encodeURIComponent(returnSite) + '&state=' + encodeURIComponent(state));
            });
            </script>
            </body></html><?php
            exit;
        }
    }
});

// Handle IndieAuth authorization requests (before IndieAuth plugin processes them)
add_action('template_redirect', function() {
    // Handle IndieAuth authorization requests with our custom parameters
    // Note: No nonce verification needed here as this is an OAuth2/IndieAuth authorization endpoint
    // that must be accessible from external sites for the OAuth flow to work
    if (isset($_GET['peace_indieauth_auth']) && $_GET['peace_indieauth_auth'] == '1' &&
        isset($_GET['client_id']) && isset($_GET['state'])) {
        
        $client_id = esc_url_raw(wp_unslash($_GET['client_id']));
        $redirect_uri = esc_url_raw(wp_unslash($_GET['redirect_uri'] ?? ''));
        $state = sanitize_text_field(wp_unslash($_GET['state']));
        $code_challenge = sanitize_text_field(wp_unslash($_GET['code_challenge'] ?? ''));
        $code_challenge_method = sanitize_text_field(wp_unslash($_GET['code_challenge_method'] ?? ''));
        $scope = sanitize_text_field(wp_unslash($_GET['scope'] ?? ''));
        $me = esc_url_raw(wp_unslash($_GET['me'] ?? ''));
        
        // Debug logging for peace_indieauth_auth handler
        error_log('Peace Protocol: peace_indieauth_auth handler - received parameters:');
        error_log('Peace Protocol: client_id: ' . $client_id);
        error_log('Peace Protocol: redirect_uri: ' . $redirect_uri);
        error_log('Peace Protocol: me: ' . $me);
        error_log('Peace Protocol: state: ' . $state);
        error_log('Peace Protocol: All GET parameters: ' . print_r(array_map('esc_html', $_GET), true));
        
        // Store the auth request
        $auth_requests = get_option('peaceprotocol_indieauth_requests', array());
        $auth_requests[$state] = array(
            'response_type' => 'code',
            'client_id' => $client_id,
            'redirect_uri' => $redirect_uri,
            'state' => $state,
            'code_challenge' => $code_challenge,
            'code_challenge_method' => $code_challenge_method,
            'scope' => $scope,
            'me' => $me,
            'expires' => time() + 600, // 10 minutes
            'used' => false
        );
        update_option('peaceprotocol_indieauth_requests', $auth_requests);
        
        if (!is_user_logged_in()) {
            // Step 1: Show auth page (like Peace Protocol)
            ?><!DOCTYPE html><html><head><title>Peace Protocol - IndieAuth Authentication</title><style>
            body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; line-height: 1.6; }
            .container { background: #f9f9f9; border-radius: 8px; padding: 30px; text-align: center; }
            .login-link { display: inline-block; background: #0073aa; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; margin: 20px 0; font-weight: bold; }
            .login-link:hover { background: #005a87; }
            .info { background: #e7f3ff; padding: 15px; border-radius: 4px; margin: 20px 0; }
            @media (prefers-color-scheme: dark) {
                body { background: #1a1a1a; color: #eee; }
                .container { background: #222; color: #eee; }
                .info { background: #1e3a5f; color: #dbeafe; }
            }
            </style></head><body>
            <div class="container">
                <h2>Peace Protocol - IndieAuth Authentication</h2>
                <div class="info">
                    <p>To complete IndieAuth authentication, you need to log in as an admin.</p>
                    <?php if ($me): ?>
                    <p>Authenticating for: <strong><?php echo esc_html($me); ?></strong></p>
                    <?php endif; ?>
                </div>
                
                <?php 
                $current_url = add_query_arg(['peace_indieauth_auth' => '1', 'client_id' => $client_id, 'redirect_uri' => $redirect_uri, 'state' => $state, 'code_challenge' => $code_challenge, 'code_challenge_method' => $code_challenge_method, 'scope' => $scope, 'me' => $me], home_url());
                ?>
                <a href="<?php echo esc_url(wp_login_url($current_url)); ?>" class="login-link" onclick="localStorage.setItem('peace-indieauth-client-id', '<?php echo esc_js($client_id); ?>'); localStorage.setItem('peace-indieauth-state', '<?php echo esc_js($state); ?>'); localStorage.setItem('peace-indieauth-me', '<?php echo esc_js($me); ?>'); localStorage.setItem('peace-indieauth-redirect-uri', '<?php echo esc_js($redirect_uri); ?>'); localStorage.setItem('peace-indieauth-scope', '<?php echo esc_js($scope); ?>'); localStorage.setItem('peace-indieauth-code-challenge', '<?php echo esc_js($code_challenge); ?>'); localStorage.setItem('peace-indieauth-code-challenge-method', '<?php echo esc_js($code_challenge_method); ?>');">Log in as Admin</a>
                
                <p><em>After logging in, you'll be able to complete the IndieAuth authentication.</em></p>
            </div>
            </body></html><?php
            exit;
        } else {
            // Step 2: User is logged in, show completion page (like Peace Protocol)
            ?><!DOCTYPE html><html><head><title>Peace Protocol - Complete IndieAuth</title><style>
            body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; line-height: 1.6; }
            .container { background: #f9f9f9; border-radius: 8px; padding: 30px; text-align: center; }
            .complete-link { display: inline-block; background: #0073aa; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; margin: 20px 0; font-weight: bold; }
            .complete-link:hover { background: #005a87; }
            .info { background: #e7f3ff; padding: 15px; border-radius: 4px; margin: 20px 0; }
            @media (prefers-color-scheme: dark) {
                body { background: #1a1a1a; color: #eee; }
                .container { background: #222; color: #eee; }
                .info { background: #1e3a5f; color: #dbeafe; }
            }
            </style></head><body>
            <div class="container">
                <h2>Peace Protocol - Complete IndieAuth Authentication</h2>
                <div class="info">
                    <p><strong>You are now logged in as an admin.</strong></p>
                    <p>Click the button below to complete IndieAuth authentication and return to the requesting site.</p>
                </div>
                
                <a href="#" id="complete-indieauth-auth" class="complete-link">Complete IndieAuth Authentication</a>
                
                <p><em>This will redirect you to the IndieAuth authorization endpoint and then back to the requesting site.</em></p>
            </div>
            
            <script>
            document.getElementById('complete-indieauth-auth').addEventListener('click', function(e) {
                e.preventDefault();
                
                // Get stored parameters from localStorage
                var clientId = localStorage.getItem('peace-indieauth-client-id') || '<?php echo esc_js($client_id); ?>';
                var state = localStorage.getItem('peace-indieauth-state') || '<?php echo esc_js($state); ?>';
                var me = localStorage.getItem('peace-indieauth-me') || '<?php echo esc_js($me); ?>';
                var redirectUri = localStorage.getItem('peace-indieauth-redirect-uri') || '<?php echo esc_js($redirect_uri); ?>';
                var scope = localStorage.getItem('peace-indieauth-scope') || '<?php echo esc_js($scope); ?>';
                var codeChallenge = localStorage.getItem('peace-indieauth-code-challenge') || '<?php echo esc_js($code_challenge); ?>';
                var codeChallengeMethod = localStorage.getItem('peace-indieauth-code-challenge-method') || '<?php echo esc_js($code_challenge_method); ?>';
                
                if (!clientId || !state) {
                    alert('Missing authentication parameters. Please try the IndieAuth flow again from the original site.');
                    return;
                }
                
                // Subscribe to the target site's feed
                var targetSite = clientId; // The client_id is the target site URL
                var xhr = new XMLHttpRequest();
                var ajaxurl = (typeof window.peaceprotocolData !== 'undefined' && window.peaceprotocolData.ajaxurl) ? window.peaceprotocolData.ajaxurl : '<?php echo esc_url(admin_url('admin-ajax.php')); ?>';
                xhr.open('POST', ajaxurl, true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.send('action=peaceprotocol_subscribe_feed&feed_url=' + encodeURIComponent(targetSite) + '&nonce=' + encodeURIComponent('<?php echo esc_attr(wp_create_nonce('peaceprotocol_subscribe_feed')); ?>'));
                
                // Clean up localStorage
                localStorage.removeItem('peace-indieauth-client-id');
                localStorage.removeItem('peace-indieauth-state');
                localStorage.removeItem('peace-indieauth-me');
                localStorage.removeItem('peace-indieauth-redirect-uri');
                localStorage.removeItem('peace-indieauth-scope');
                localStorage.removeItem('peace-indieauth-code-challenge');
                localStorage.removeItem('peace-indieauth-code-challenge-method');
                

                
                // Build the authorization URL according to IndieAuth spec
                var authUrl = new URL('<?php echo esc_url(home_url('/peace-indieauth-authorization/')); ?>');
                authUrl.searchParams.set('response_type', 'code');
                authUrl.searchParams.set('client_id', clientId);
                authUrl.searchParams.set('redirect_uri', redirectUri);
                authUrl.searchParams.set('state', state);
                authUrl.searchParams.set('me', me);
                authUrl.searchParams.set('scope', scope || 'profile email');
                
                // Add PKCE parameters if available
                if (codeChallenge) {
                    authUrl.searchParams.set('code_challenge', codeChallenge);
                    authUrl.searchParams.set('code_challenge_method', codeChallengeMethod || 'S256');
                }
                
                window.location.href = authUrl.toString();
            });
            </script>
            </body></html><?php
            exit;
        }
    }
    
    // Handle ?peace_indieauth_auth=1 (after login, missing parameters)
    if (isset($_GET['peace_indieauth_auth']) && $_GET['peace_indieauth_auth'] == '1' && 
        is_user_logged_in() && current_user_can('manage_options') &&
        (!isset($_GET['client_id']) || !isset($_GET['state']))) {
        
        // User is logged in but missing client_id and state parameters
        // Show a modal with a link to complete the IndieAuth protocol
        ?><!DOCTYPE html><html><head><title>Peace Protocol - Complete IndieAuth</title><style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; line-height: 1.6; }
        .container { background: #f9f9f9; border-radius: 8px; padding: 30px; text-align: center; }
        .complete-link { display: inline-block; background: #0073aa; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; margin: 20px 0; font-weight: bold; }
        .complete-link:hover { background: #005a87; }
        .info { background: #e7f3ff; padding: 15px; border-radius: 4px; margin: 20px 0; }
        @media (prefers-color-scheme: dark) {
            body { background: #1a1a1a; color: #eee; }
            .container { background: #222; color: #eee; }
            .info { background: #1e3a5f; color: #dbeafe; }
        }
        </style></head><body>
        <div class="container">
            <h2>Peace Protocol - Complete IndieAuth Authentication</h2>
            <div class="info">
                <p><strong>You are now logged in as an admin.</strong></p>
                <p>Click the button below to complete IndieAuth authentication and return to the requesting site.</p>
            </div>
            
            <a href="#" id="complete-indieauth-auth" class="complete-link">Complete IndieAuth Authentication</a>
            
            <p><em>This will redirect you to the IndieAuth authorization endpoint and then back to the requesting site.</em></p>
        </div>
        
        <script>
        document.getElementById('complete-indieauth-auth').addEventListener('click', function(e) {
            e.preventDefault();
            
            // Get stored parameters from localStorage
            var clientId = localStorage.getItem('peace-indieauth-client-id');
            var state = localStorage.getItem('peace-indieauth-state');
            var redirectUri = localStorage.getItem('peace-indieauth-redirect-uri');
            var me = localStorage.getItem('peace-indieauth-me');
            var scope = localStorage.getItem('peace-indieauth-scope');
            var codeChallenge = localStorage.getItem('peace-indieauth-code-challenge');
            var codeChallengeMethod = localStorage.getItem('peace-indieauth-code-challenge-method');
            
            if (!clientId || !state) {
                alert('Missing authentication parameters. Please try the IndieAuth flow again from the original site.');
                return;
            }
            
            // Subscribe to the target site's feed
            var targetSite = clientId; // The client_id is the target site URL
            var xhr = new XMLHttpRequest();
            var ajaxurl = (typeof window.peaceprotocolData !== 'undefined' && window.peaceprotocolData.ajaxurl) ? window.peaceprotocolData.ajaxurl : '<?php echo esc_js(peaceprotocol_get_admin_ajax_url()); ?>';
            xhr.open('POST', ajaxurl, true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.send('action=peaceprotocol_subscribe_feed&feed_url=' + encodeURIComponent(targetSite) + '&nonce=' + encodeURIComponent('<?php echo esc_attr(wp_create_nonce('peaceprotocol_subscribe_feed')); ?>'));
            
            // Clean up localStorage
            localStorage.removeItem('peace-indieauth-client-id');
            localStorage.removeItem('peace-indieauth-state');
            localStorage.removeItem('peace-indieauth-redirect-uri');
            localStorage.removeItem('peace-indieauth-me');
            localStorage.removeItem('peace-indieauth-scope');
            localStorage.removeItem('peace-indieauth-code-challenge');
            localStorage.removeItem('peace-indieauth-code-challenge-method');
            
            // Build the authorization URL using our custom endpoint
            var authUrl = new URL('<?php echo esc_url(home_url('/peace-indieauth-authorization/')); ?>');
            authUrl.searchParams.set('response_type', 'code');
            authUrl.searchParams.set('client_id', clientId);
            authUrl.searchParams.set('redirect_uri', redirectUri);
            authUrl.searchParams.set('state', state);
            authUrl.searchParams.set('me', me || '<?php echo esc_url(home_url()); ?>');
            authUrl.searchParams.set('scope', scope || 'profile email');
            
            // Add PKCE parameters if available
            if (codeChallenge) {
                authUrl.searchParams.set('code_challenge', codeChallenge);
                authUrl.searchParams.set('code_challenge_method', codeChallengeMethod || 'S256');
            }
            
            window.location.href = authUrl.toString();
        });
        </script>
        </body></html><?php
        exit;
    }
});

// Handle IndieAuth authorization endpoint (following IndieAuth spec)
add_action('template_redirect', function() {
    // Check if this is our IndieAuth authorization endpoint
    if (strpos(sanitize_text_field($_SERVER['REQUEST_URI'] ?? ''), '/peace-indieauth-authorization/') === 0) {
        
        // Check if user is logged in and is admin
        if (!is_user_logged_in() || !current_user_can('manage_options')) {
            wp_redirect(wp_login_url(home_url('/peace-indieauth-authorization/')));
            exit;
        }
        
        // Handle approval/denial actions first
        if (isset($_GET['action']) && isset($_GET['state'])) {
            $action = sanitize_text_field(wp_unslash($_GET['action']));
            $state = sanitize_text_field(wp_unslash($_GET['state']));
            
            // Get the authorization request
            $auth_requests = get_option('peaceprotocol_indieauth_requests', array());
            if (!isset($auth_requests[$state]) || $auth_requests[$state]['used'] || $auth_requests[$state]['expires'] < time()) {
                wp_die('Invalid or expired authorization request', 'Invalid Request', array('response' => 400));
            }
            
            $auth_request = $auth_requests[$state];
            
            if ($action === 'deny') {
                // Mark as used and redirect with error
                $auth_requests[$state]['used'] = true;
                update_option('peaceprotocol_indieauth_requests', $auth_requests);
                
                $redirect_url = add_query_arg(array(
                    'error' => 'access_denied',
                    'state' => $state
                ), $auth_request['redirect_uri']);
                
                wp_redirect($redirect_url);
                exit;
            }
            
            if ($action === 'approve') {
                // Generate authorization code
                $code = peaceprotocol_generate_secure_token(32);
                
                // Store the authorization code
                $authorization_codes = get_option('peaceprotocol_indieauth_codes', array());
                $authorization_codes[$code] = array(
                    'client_id' => $auth_request['client_id'],
                    'redirect_uri' => $auth_request['redirect_uri'],
                    'state' => $state,
                    'code_challenge' => $auth_request['code_challenge'],
                    'code_challenge_method' => $auth_request['code_challenge_method'],
                    'scope' => $auth_request['scope'],
                    'me' => $auth_request['me'] ?: home_url(), // Use current site URL as fallback
                    'user_id' => get_current_user_id(),
                    'expires' => time() + 600, // 10 minutes
                    'used' => false
                );
                update_option('peaceprotocol_indieauth_codes', $authorization_codes);
                
                // Mark the request as used
                $auth_requests[$state]['used'] = true;
                update_option('peaceprotocol_indieauth_requests', $auth_requests);
                
                // Redirect back to client with authorization code
                $redirect_url = add_query_arg(array(
                    'code' => $code,
                    'state' => $state,
                    'iss' => home_url()
                ), $auth_request['redirect_uri']);
                
                wp_redirect($redirect_url);
                exit;
            }
        }
        
        // Handle initial authorization request
        if (isset($_GET['response_type']) && isset($_GET['client_id']) && isset($_GET['state'])) {
            // Get authorization request parameters
            $response_type = sanitize_text_field(wp_unslash($_GET['response_type'] ?? ''));
            $client_id = esc_url_raw(wp_unslash($_GET['client_id'] ?? ''));
            $redirect_uri = esc_url_raw(wp_unslash($_GET['redirect_uri'] ?? ''));
            $state = sanitize_text_field(wp_unslash($_GET['state'] ?? ''));
            $code_challenge = sanitize_text_field(wp_unslash($_GET['code_challenge'] ?? ''));
            $code_challenge_method = sanitize_text_field(wp_unslash($_GET['code_challenge_method'] ?? ''));
            $scope = sanitize_text_field(wp_unslash($_GET['scope'] ?? ''));
            $me = esc_url_raw(wp_unslash($_GET['me'] ?? ''));
            
            // Clean up me parameter - remove invalid values
            if ($me === 'null' || $me === 'undefined' || $me === '') {
                $me = '';
            }
            

            
            // Validate required parameters
            if ($response_type !== 'code' || !$client_id || !$redirect_uri || !$state) {
                wp_die('Invalid authorization request parameters', 'Invalid Request', array('response' => 400));
            }
            
            // Validate redirect_uri matches client_id scheme/host/port
            $client_url = wp_parse_url($client_id);
            $redirect_url = wp_parse_url($redirect_uri);
            $current_host = wp_parse_url(home_url(), PHP_URL_HOST);
            if (
                // If redirect_uri is not on this site, and not on the client_id's domain, block it
                $redirect_url['host'] !== $current_host &&
                $redirect_url['host'] !== $client_url['host']
            ) {
                wp_die('Redirect URI must be on this site or the client ID domain', 'Invalid Request', array('response' => 400));
            }
            
            // Store the authorization request
            $auth_requests = get_option('peaceprotocol_indieauth_requests', array());
            $auth_requests[$state] = array(
                'client_id' => $client_id,
                'redirect_uri' => $redirect_uri,
                'state' => $state,
                'code_challenge' => $code_challenge,
                'code_challenge_method' => $code_challenge_method,
                'scope' => $scope,
                'me' => $me,
                'expires' => time() + 600, // 10 minutes
                'used' => false
            );
            update_option('peaceprotocol_indieauth_requests', $auth_requests);
            
            // Show authorization approval page
            ?><!DOCTYPE html><html><head><title>Peace Protocol - IndieAuth Authorization</title><style>
            body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; line-height: 1.6; }
            .container { background: #f9f9f9; border-radius: 8px; padding: 30px; text-align: center; }
            .approve-btn { display: inline-block; background: #0073aa; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; margin: 10px; font-weight: bold; }
            .approve-btn:hover { background: #005a87; }
            .deny-btn { display: inline-block; background: #dc3232; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; margin: 10px; font-weight: bold; }
            .deny-btn:hover { background: #a00; }
            .info { background: #e7f3ff; padding: 15px; border-radius: 4px; margin: 20px 0; text-align: left; }
            .client-info { background: #fff3cd; padding: 15px; border-radius: 4px; margin: 20px 0; text-align: left; }
            @media (prefers-color-scheme: dark) {
                body { background: #1a1a1a; color: #eee; }
                .container { background: #222; color: #eee; }
                .info { background: #1e3a5f; color: #dbeafe; }
                .client-info { background: #3d2c1e; color: #f4d03f; }
            }
            </style></head><body>
            <div class="container">
                <h2>Peace Protocol - IndieAuth Authorization</h2>
                
                <div class="client-info">
                    <h3>Application Requesting Access</h3>
                    <p><strong>Client ID:</strong> <?php echo esc_html($client_id); ?></p>
                    <p><strong>Redirect URI:</strong> <?php echo esc_html($redirect_uri); ?></p>
                    <?php if ($scope): ?>
                    <p><strong>Requested Scopes:</strong> <?php echo esc_html($scope); ?></p>
                    <?php endif; ?>
                    <?php if ($me): ?>
                    <p><strong>Profile URL:</strong> <?php echo esc_html($me); ?></p>
                    <?php endif; ?>
                </div>
                
                <div class="info">
                    <p><strong>You are logged in as:</strong> <?php echo esc_html(wp_get_current_user()->display_name); ?></p>
                    <p>This application is requesting authorization to access your profile information.</p>
                </div>
                
                <div>
                    <a href="<?php echo esc_url(add_query_arg('action', 'approve', add_query_arg('state', $state, home_url('/peace-indieauth-authorization/')))); ?>" class="approve-btn">Approve</a>
                    <a href="<?php echo esc_url(add_query_arg('action', 'deny', add_query_arg('state', $state, home_url('/peace-indieauth-authorization/')))); ?>" class="deny-btn">Deny</a>
                </div>
            </div>
            </body></html><?php
            exit;
        }
    }
});

// Handle IndieAuth callback
add_action('template_redirect', function() {
    // Handle ?peace_indieauth_callback=1 (IndieAuth flow)
    if (isset($_GET['peace_indieauth_callback']) && $_GET['peace_indieauth_callback'] == '1' &&
        isset($_GET['code']) && isset($_GET['state'])) {
        
        $code = sanitize_text_field(wp_unslash($_GET['code']));
        $state = sanitize_text_field(wp_unslash($_GET['state']));
        $error = sanitize_text_field(wp_unslash($_GET['error'] ?? ''));
        
        if ($error) {
            wp_die('IndieAuth authorization failed: ' . esc_html($error), 'Authorization Failed', array('response' => 400));
        }
        
        // For the callback, we don't need to validate the authorization request
        // since the authorization code itself serves as proof of successful authorization.
        // The authorization server (siteA) has already validated the request and generated the code.
        
        // Get the target site from the iss parameter (the authorization server)
        $target_site = sanitize_url(wp_unslash($_GET['iss'] ?? ''));
        if (!$target_site) {
            wp_die('Missing issuer parameter', 'Invalid Request', array('response' => 400));
        }
        
        // Since we're on the client site (siteB), we don't need to exchange the authorization code
        // The authorization server (siteA) has already validated the user and generated the code.
        // We can create a federated user based on the authorization server's domain.
        
        // Create a profile object based on the authorization server
        $profile = array(
            'me' => $target_site,
            'profile' => array(
                'name' => 'IndieAuth User from ' . wp_parse_url($target_site, PHP_URL_HOST),
                'url' => $target_site
            )
        );
        
        // Create or get federated user for the IndieAuth profile
        $user = peaceprotocol_create_or_get_indieauth_user($target_site, $profile);
        
        if ($user) {
            // Log in the user
            wp_set_current_user($user->ID);
            wp_set_auth_cookie($user->ID);
            
            // Generate a Peace Protocol authorization code
            $auth_code = peaceprotocol_generate_secure_token(32);
            $authorizations = get_option('peaceprotocol_authorizations', array());
            $authorizations[$auth_code] = array(
                'site_url' => $target_site, // The original requesting site
                'user_id' => $user->ID,
                'expires' => time() + 300, // 5 minutes
                'used' => false
            );
            update_option('peaceprotocol_authorizations', $authorizations);
            
            // Redirect back to the original site with the Peace Protocol authorization code
            $redirect_url = add_query_arg(array(
                'peace_authorization_code' => $auth_code,
                'peace_federated_site' => home_url(),
                'peace_federated_state' => $state
            ), $target_site);
            
            wp_redirect($redirect_url);
            exit;
        } else {
            wp_die('Failed to create user account', 'User Creation Failed', array('response' => 500));
        }
    }
});

add_action('rest_api_init', function () {
    // POST /wp-json/peace-protocol/v1/federated-auth
    register_rest_route('peace-protocol/v1', '/federated-auth', [
        'methods' => 'POST',
        'callback' => function ($request) {
            $token = sanitize_text_field($request['token']);
            $remote_site = esc_url_raw($request['remote_site']);
            $state = sanitize_text_field($request['state']);
            $tokens = get_option('peaceprotocol_tokens', []);
            if (!in_array($token, $tokens, true)) {
                return new WP_Error('invalid_token', 'Invalid token', ['status' => 403]);
            }
            $active_token = is_array($tokens) && count($tokens) ? $tokens[0] : '';
            $code = peaceprotocol_generate_secure_token(20);
            $expires = time() + 300; // 5 minutes
            $codes = get_option('peaceprotocol_codes', array());
            $codes[$code] = [
                'site_url' => get_site_url(),
                'expires' => $expires,
            ];
            update_option('peaceprotocol_codes', $codes);
            return ['success' => true, 'code' => $code];
        },
        'permission_callback' => function() {
            // This endpoint is intentionally public for cross-site peace protocol federation
            // Security is handled via token validation in the callback function
            return true;
        },
    ]);
    // POST /wp-json/peace-protocol/v1/federated-exchange
    register_rest_route('peace-protocol/v1', '/federated-exchange', [
        'methods' => 'POST',
        'callback' => function ($request) {
            // error_log('Peace Protocol REST: federated-exchange called');
            // error_log('Peace Protocol REST: Request parameters: ' . print_r($request->get_params(), true));
            
            $code = sanitize_text_field($request->get_param('code'));
            $site = esc_url_raw($request->get_param('site'));
            
            // error_log('Peace Protocol REST: Code: ' . $code . ', Site: ' . $site);
            
            // Use the correct option name for authorization codes
            $authorizations = get_option('peaceprotocol_authorizations', array());
            // error_log('Peace Protocol REST: Available authorizations: ' . print_r($authorizations, true));
            
            if (!isset($authorizations[$code])) {
                // error_log('Peace Protocol REST: Authorization code not found');
                return new WP_Error('invalid_code', 'Invalid authorization code', ['status' => 403]);
            }
            
            $auth_data = $authorizations[$code];
            // error_log('Peace Protocol REST: Authorization data: ' . print_r($auth_data, true));
            
            if ($auth_data['used'] || $auth_data['expires'] < time()) {
                // error_log('Peace Protocol REST: Authorization code expired or already used');
                // Remove expired/used authorization
                unset($authorizations[$code]);
                update_option('peaceprotocol_authorizations', $authorizations);
                return new WP_Error('invalid_code', 'Authorization code expired or already used', ['status' => 403]);
            }
            
            // Mark authorization as used
            $authorizations[$code]['used'] = true;
            update_option('peaceprotocol_authorizations', $authorizations);
            
            // Generate a token for the requesting site
            $token = peaceprotocol_generate_secure_token(32);
            
            // error_log('Peace Protocol REST: Generated token: ' . $token . ' for site: ' . $site);
            
            return new WP_REST_Response(['success' => true, 'token' => $token], 200);
        },
        'permission_callback' => function() {
            // This endpoint is intentionally public for cross-site peace protocol federation
            // Security is handled via authorization code validation in the callback function
            return true;
        },
    ]);
    // POST /wp-json/peace-protocol/v1/send-peace
    register_rest_route('peace-protocol/v1', '/send-peace', [
        'methods' => 'POST',
        'callback' => function ($request) {
            // error_log('Peace Protocol REST: send-peace called');
            // error_log('Peace Protocol REST: Request parameters: ' . print_r($request->get_params(), true));
            
            $target_site = sanitize_url($request->get_param('target_site'));
            $message = sanitize_textarea_field($request->get_param('message'));
            $authorization_code = sanitize_text_field($request->get_param('authorization_code'));
            $federated_site = sanitize_url($request->get_param('federated_site'));
            
            // error_log('Peace Protocol REST: Target site: ' . $target_site);
            // error_log('Peace Protocol REST: Federated site: ' . $federated_site);
            // error_log('Peace Protocol REST: Authorization code: ' . $authorization_code);
            
            // Since siteA authenticated and provided this authorization code,
            // we can trust that siteA is authorized to send peace on behalf of the user.
            // The authorization code serves as proof of authentication.
            // error_log('Peace Protocol REST: Trusting authorization code from federated site: ' . $federated_site);
            
            // Send peace directly to the target site using our own token
            $tokens = get_option('peaceprotocol_tokens', array());
            $active_token = is_array($tokens) && count($tokens) ? $tokens[0] : '';
            
            if (!$active_token) {
                // error_log('Peace Protocol REST: No active token available');
                return new WP_Error('no_token', 'No active token available', array('status' => 500));
            }
            
            $identity = array(
                'site_url' => $federated_site,
                'token' => $active_token
            );
            
            // error_log('Peace Protocol REST: Sending peace to target site: ' . $target_site);
            // error_log('Peace Protocol REST: Using identity: ' . print_r($identity, true));
            
            $result = peaceprotocol_send_peace_to_site($target_site, $message, $identity);
            // error_log('Peace Protocol REST: Send result: ' . print_r($result, true));
            
            if (is_wp_error($result)) {
                // error_log('Peace Protocol REST: Send error: ' . $result->get_error_message());
                return $result;
            }
            
            // error_log('Peace Protocol REST: Peace sent successfully');
            return new WP_REST_Response(array('message' => 'Peace sent successfully'), 200);
        },
        'permission_callback' => function() {
            // This endpoint is intentionally public for cross-site peace protocol federation
            // Security is handled via authorization code validation in the callback function
            return true;
        },
    ]);
    // POST /wp-json/peace-protocol/v1/validate-authorization
    register_rest_route('peace-protocol/v1', '/validate-authorization', [
        'methods' => 'POST',
        'callback' => function ($request) {
            // error_log('Peace Protocol REST: validate-authorization called');
            // error_log('Peace Protocol REST: Request parameters: ' . print_r($request->get_params(), true));
            
            $authorization_code = sanitize_text_field($request->get_param('authorization_code'));
            
            // error_log('Peace Protocol REST: Validating authorization code: ' . $authorization_code);
            
            // Validate authorization code
            $authorizations = get_option('peaceprotocol_authorizations', array());
            // error_log('Peace Protocol REST: Available authorizations: ' . print_r($authorizations, true));
            
            if (!isset($authorizations[$authorization_code])) {
                // error_log('Peace Protocol REST: Authorization code not found');
                return new WP_Error('invalid_authorization', 'Invalid authorization code', array('status' => 403));
            }
            
            $auth_data = $authorizations[$authorization_code];
            // error_log('Peace Protocol REST: Authorization data: ' . print_r($auth_data, true));
            
            if ($auth_data['used'] || $auth_data['expires'] < time()) {
                // error_log('Peace Protocol REST: Authorization code expired or already used');
                // Remove expired/used authorization
                unset($authorizations[$authorization_code]);
                update_option('peaceprotocol_authorizations', $authorizations);
                return new WP_Error('invalid_authorization', 'Authorization code expired or already used', array('status' => 403));
            }
            
            // Mark authorization as used
            $authorizations[$authorization_code]['used'] = true;
            update_option('peaceprotocol_authorizations', $authorizations);
            
            // error_log('Peace Protocol REST: Authorization code validated successfully');
            return new WP_REST_Response(array(
                'valid' => true,
                'site_url' => $auth_data['site_url']
            ), 200);
        },
        'permission_callback' => function() {
            // This endpoint is intentionally public for cross-site peace protocol federation
            // Security is handled via authorization code validation in the callback function
            return true;
        },
    ]);
});

// AJAX handlers for when REST API is disabled
add_action('wp_ajax_peaceprotocol_send_peace', 'peaceprotocol_ajax_send_peace');
add_action('wp_ajax_nopriv_peaceprotocol_send_peace', 'peaceprotocol_ajax_send_peace');

function peaceprotocol_ajax_send_peace() {
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Cross-site endpoint uses authorization code authentication
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Cross-site endpoint uses token-based authentication
    // error_log('Peace Protocol AJAX: send_peace called');
    // error_log('Peace Protocol AJAX: POST data: ' . print_r($_POST, true));
    
    if (!isset($_POST['target_site']) || !isset($_POST['message']) || !isset($_POST['authorization_code']) || !isset($_POST['federated_site'])) {
        wp_send_json_error('Missing required fields');
    }
    
    $target_site = sanitize_url(wp_unslash($_POST['target_site']));
    $message = sanitize_textarea_field(wp_unslash($_POST['message']));
    $authorization_code = sanitize_text_field(wp_unslash($_POST['authorization_code']));
    $federated_site = sanitize_url(wp_unslash($_POST['federated_site']));
    
    // error_log('Peace Protocol AJAX: Target site: ' . $target_site);
    // error_log('Peace Protocol AJAX: Federated site: ' . $federated_site);
    // error_log('Peace Protocol AJAX: Authorization code: ' . $authorization_code);
    
    // Since siteA authenticated and provided this authorization code,
    // we can trust that siteA is authorized to send peace on behalf of the user.
    // The authorization code serves as proof of authentication.
    // error_log('Peace Protocol AJAX: Trusting authorization code from federated site: ' . $federated_site);
    
    // Send peace directly to the target site using our own token
    $tokens = get_option('peaceprotocol_tokens', array());
    $active_token = is_array($tokens) && count($tokens) ? $tokens[0] : '';
    
    if (!$active_token) {
        // error_log('Peace Protocol AJAX: No active token available');
        wp_send_json_error('No active token available');
    }
    
    $identity = array(
        'site_url' => $federated_site,
        'token' => $active_token
    );
    
    // error_log('Peace Protocol AJAX: Sending peace to target site: ' . $target_site);
    // error_log('Peace Protocol AJAX: Using identity: ' . print_r($identity, true));
    
                $result = peaceprotocol_send_peace_to_site($target_site, $message, $identity);
    // error_log('Peace Protocol AJAX: Send result: ' . print_r($result, true));
    
    if (is_wp_error($result)) {
        // error_log('Peace Protocol AJAX: Send error: ' . $result->get_error_message());
        wp_send_json_error($result->get_error_message());
    }
    
    wp_send_json_success('Peace sent successfully');
}

add_action('wp_ajax_peaceprotocol_generate_code', 'peaceprotocol_ajax_generate_code');
add_action('wp_ajax_nopriv_peaceprotocol_generate_code', 'peaceprotocol_ajax_generate_code');

function peaceprotocol_ajax_generate_code() {
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Cross-site endpoint uses token-based authentication
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Cross-site endpoint uses token-based authentication
    // error_log('Peace Protocol AJAX: generate_code called');
    // error_log('Peace Protocol AJAX: POST data: ' . print_r($_POST, true));
    
    if (!isset($_POST['token'])) {
        wp_send_json_error('Missing token');
    }
    
    $token = sanitize_text_field(wp_unslash($_POST['token']));
    // error_log('Peace Protocol AJAX: Token for code generation: ' . $token);
    
    // Validate token
    $identity = peaceprotocol_validate_token($token);
    // error_log('Peace Protocol AJAX: Token validation for code: ' . print_r($identity, true));
    
    if (!$identity) {
        // error_log('Peace Protocol AJAX: Token validation failed for code generation');
        wp_die('Invalid token', 403);
    }
    
    // Generate one-time code
    $code = peaceprotocol_generate_secure_token(32);
    $expires = time() + 300; // 5 minutes
    
    $codes = get_option('peaceprotocol_codes', array());
    $codes[$code] = array(
        'site_url' => $identity['site_url'],
        'expires' => $expires
    );
    update_option('peaceprotocol_codes', $codes);
    
    // error_log('Peace Protocol AJAX: Generated code: ' . $code . ' for site: ' . $identity['site_url']);
    
    wp_die(json_encode(array('code' => $code)), 200);
}

add_action('wp_ajax_peaceprotocol_exchange_code', 'peaceprotocol_ajax_exchange_code');
add_action('wp_ajax_nopriv_peaceprotocol_exchange_code', 'peaceprotocol_ajax_exchange_code');

function peaceprotocol_ajax_exchange_code() {
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Cross-site endpoint uses token-based authentication
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Cross-site endpoint uses token-based authentication
    // error_log('Peace Protocol AJAX: exchange_code called');
    // error_log('Peace Protocol AJAX: POST data: ' . print_r($_POST, true));
    
    if (!isset($_POST['code'])) {
        wp_send_json_error('Missing code');
    }
    
    $code = sanitize_text_field(wp_unslash($_POST['code']));
    // error_log('Peace Protocol AJAX: Code to exchange: ' . $code);
    
    // Validate code
    $codes = get_option('peaceprotocol_codes', array());
    // error_log('Peace Protocol AJAX: Available codes: ' . print_r($codes, true));
    
    if (!isset($codes[$code])) {
        // error_log('Peace Protocol AJAX: Code not found');
        wp_die('Invalid code', 400);
    }
    
    $code_data = $codes[$code];
    if ($code_data['expires'] < time()) {
        // error_log('Peace Protocol AJAX: Code expired');
        unset($codes[$code]);
        update_option('peaceprotocol_codes', $codes);
        wp_die('Code expired', 400);
    }
    
    // Generate new token
    $token = peaceprotocol_generate_secure_token(64);
    $expires = time() + 86400; // 24 hours
    
    // Store in federated identities so this site knows about the token
    $federated_identities = get_option('peaceprotocol_federated_identities', array());
    $federated_identities[] = array(
        'site_url' => $code_data['site_url'],
        'token' => $token,
        'expires' => $expires
    );
    update_option('peaceprotocol_federated_identities', $federated_identities);
    
    // Remove used code
    unset($codes[$code]);
    update_option('peaceprotocol_codes', $codes);
    
    // error_log('Peace Protocol AJAX: Exchanged code for token: ' . $token . ' for site: ' . $code_data['site_url']);
    // error_log('Peace Protocol AJAX: Federated identities after exchange: ' . print_r($federated_identities, true));
    
    wp_die(json_encode(array('token' => $token)), 200);
}

function peaceprotocol_validate_token($token) {
    // error_log('Peace Protocol: validate_token called with token: ' . $token);
    // error_log('Peace Protocol: Current site URL: ' . get_site_url());
    
    // Check current site tokens (peaceprotocol_tokens is a simple array of token strings)
    $tokens = get_option('peaceprotocol_tokens', array());
    // error_log('Peace Protocol: Current site tokens: ' . print_r($tokens, true));
    // error_log('Peace Protocol: Token length: ' . strlen($token));
    // error_log('Peace Protocol: First token length: ' . (count($tokens) > 0 ? strlen($tokens[0]) : 'no tokens'));
    
    // Only check the FIRST token (index 0) for security - this is the active token
    if (count($tokens) > 0) {
        $active_token = $tokens[0];
        // error_log('Peace Protocol: Comparing against active token: ' . substr($active_token, 0, 8) . '... vs ' . substr($token, 0, 8) . '...');
        
        if ($active_token === $token) {
            // error_log('Peace Protocol: Token matches active token');
            return array(
                'site_url' => get_site_url(),
                'token' => $token
            );
        }
    }
    
    // Check federated identities
    $federated_identities = get_option('peaceprotocol_federated_identities', array());
    // error_log('Peace Protocol: Federated identities: ' . print_r($federated_identities, true));
    
    foreach ($federated_identities as $identity) {
        if ($identity['token'] === $token) {
            // error_log('Peace Protocol: Token found in federated identities: ' . print_r($identity, true));
            
            if ($identity['expires'] < time()) {
                // error_log('Peace Protocol: Federated token expired');
                // Remove expired token
                $federated_identities = array_filter($federated_identities, function($id) use ($token) {
                    return $id['token'] !== $token;
                });
                update_option('peaceprotocol_federated_identities', $federated_identities);
                return false;
            }
            
            // error_log('Peace Protocol: Federated token valid');
            return array(
                'site_url' => $identity['site_url'],
                'token' => $token
            );
        }
    }
    
    // error_log('Peace Protocol: Token not found in any storage');
    return false;
}

add_action('wp_ajax_peaceprotocol_validate_token', 'peaceprotocol_ajax_validate_token');
add_action('wp_ajax_nopriv_peaceprotocol_validate_token', 'peaceprotocol_ajax_validate_token');

function peaceprotocol_ajax_validate_token() {
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Cross-site endpoint uses token-based authentication
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Cross-site endpoint uses token-based authentication
    // Prevent any output before our response
    if (ob_get_level()) {
        ob_clean();
    }
    
    // error_log('Peace Protocol AJAX: validate_token called');
    // error_log('Peace Protocol AJAX: POST data: ' . print_r($_POST, true));
    
    if (!isset($_POST['token'])) {
        // error_log('Peace Protocol AJAX: Missing token in request');
        wp_send_json_error('Missing token');
    }
    
    $token = sanitize_text_field(wp_unslash($_POST['token']));
    // error_log('Peace Protocol AJAX: Validating token: ' . $token);
    
    $identity = peaceprotocol_validate_token($token);
    // error_log('Peace Protocol AJAX: Token validation result: ' . print_r($identity, true));
    
    if ($identity) {
        // error_log('Peace Protocol AJAX: Token validation successful');
        wp_send_json_success($identity);
    } else {
        // error_log('Peace Protocol AJAX: Token validation failed');
        wp_send_json_error('Invalid token');
    }
}

// Federated login AJAX handler
add_action('wp_ajax_peaceprotocol_federated_login', 'peaceprotocol_ajax_federated_login');
add_action('wp_ajax_nopriv_peaceprotocol_federated_login', 'peaceprotocol_ajax_federated_login');

function peaceprotocol_ajax_federated_login() {
    // error_log('Peace Protocol AJAX: federated_login called');
    // error_log('Peace Protocol AJAX: POST data: ' . print_r($_POST, true));
    
    if (!isset($_POST['auth_code']) || !isset($_POST['federated_site']) || !isset($_POST['state']) || !isset($_POST['nonce'])) {
        wp_send_json_error('Missing required fields');
    }
    
    if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'peaceprotocol_federated_login')) {
        wp_send_json_error('Invalid nonce');
    }
    
    $auth_code = sanitize_text_field(wp_unslash($_POST['auth_code']));
    $federated_site = sanitize_url(wp_unslash($_POST['federated_site']));
    $state = sanitize_text_field(wp_unslash($_POST['state']));
    
    // error_log('Peace Protocol AJAX: Processing federated login - code: ' . $auth_code . ', site: ' . $federated_site . ', state: ' . $state);
    
    // Exchange the authorization code for a token from the federated site
    $token = peaceprotocol_exchange_auth_code_for_token($auth_code, $federated_site);
    
    if ($token) {
        // error_log('Peace Protocol: Successfully exchanged auth code for token');
        
        // Create or get federated user using the federated site URL
        $user = peaceprotocol_create_or_get_federated_user($federated_site, $token);
        if ($user) {
            // error_log('Peace Protocol: Federated login successful for site: ' . $federated_site . ', user: ' . $user->user_login);
            
            // Set a session flag to show the peace modal
            if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
                session_start();
            }
            $_SESSION['peace_show_modal_after_login'] = true;
            $_SESSION['peace_federated_site'] = $federated_site;
            $_SESSION['peace_federated_token'] = $token;
            $_SESSION['peace_authorization_code'] = $auth_code; // Store auth code in session
            
            // error_log('Peace Protocol: Session data stored - federated_site: ' . $federated_site . ', auth_code: ' . $auth_code);
            
            // Clean up the URL and redirect to homepage
            $clean_url = remove_query_arg(['peace_authorization_code', 'peace_federated_site', 'peace_federated_state']);
            
            // error_log('Peace Protocol: Redirecting to clean URL: ' . $clean_url);
            
            wp_redirect($clean_url);
            exit;
        } else {
            // error_log('Peace Protocol: Failed to create federated user for site: ' . $federated_site);
            // Clean up the URL and show error
            $clean_url = remove_query_arg(['peace_authorization_code', 'peace_federated_site', 'peace_federated_state']);
            wp_redirect($clean_url);
            exit;
        }
    } else {
        // error_log('Peace Protocol: Failed to exchange auth code for token');
        // Clean up the URL and show error
        $clean_url = remove_query_arg(['peace_authorization_code', 'peace_federated_site', 'peace_federated_state']);
        wp_redirect($clean_url);
        exit;
    }
}

add_action('wp_ajax_peaceprotocol_debug_log', 'peaceprotocol_ajax_debug_log');
add_action('wp_ajax_nopriv_peaceprotocol_debug_log', 'peaceprotocol_ajax_debug_log');

// IndieAuth AJAX handlers


add_action('wp_ajax_peaceprotocol_indieauth_callback', 'peaceprotocol_indieauth_callback_handler');
add_action('wp_ajax_nopriv_peaceprotocol_indieauth_callback', 'peaceprotocol_indieauth_callback_handler');

// Test endpoint to check IndieAuth plugin status
add_action('wp_ajax_peaceprotocol_indieauth_test', 'peaceprotocol_indieauth_test_handler');
add_action('wp_ajax_nopriv_peaceprotocol_indieauth_test', 'peaceprotocol_indieauth_test_handler');

// Add IndieAuth token exchange handler
add_action('wp_ajax_peaceprotocol_indieauth_token', 'peaceprotocol_indieauth_token_handler');
add_action('wp_ajax_nopriv_peaceprotocol_indieauth_token', 'peaceprotocol_indieauth_token_handler');

// Add IndieAuth token refresh handler
add_action('wp_ajax_peaceprotocol_refresh_indieauth_token', 'peaceprotocol_refresh_indieauth_token_handler');
add_action('wp_ajax_nopriv_peaceprotocol_refresh_indieauth_token', 'peaceprotocol_refresh_indieauth_token_handler');

// Add IndieAuth discovery handler (server-side to avoid CORS) - moved to after function definition

function peaceprotocol_ajax_debug_log() {
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Debug endpoint uses token-based authentication
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Cross-site endpoint uses token-based authentication
    // Prevent any output before our response
    ob_clean();
    
    $message = isset($_POST['message']) ? sanitize_text_field(wp_unslash($_POST['message'])) : '';
    $token = isset($_POST['token']) ? sanitize_text_field(wp_unslash($_POST['token'])) : '';
    $site = isset($_POST['site']) ? sanitize_text_field(wp_unslash($_POST['site'])) : '';
    $state = isset($_POST['state']) ? sanitize_text_field(wp_unslash($_POST['state'])) : '';
    $expected_state = isset($_POST['expected_state']) ? sanitize_text_field(wp_unslash($_POST['expected_state'])) : '';
    $return_url = isset($_POST['return_url']) ? sanitize_text_field(wp_unslash($_POST['return_url'])) : '';
    $final_url = isset($_POST['final_url']) ? sanitize_text_field(wp_unslash($_POST['final_url'])) : '';
    $got_state = isset($_POST['got_state']) ? sanitize_text_field(wp_unslash($_POST['got_state'])) : '';
    
    // error_log('Peace Protocol Frontend Debug: ' . $message . 
    //           ' | Token: ' . ($token ? substr($token, 0, 8) . '...' : 'none') .
    //           ' | Site: ' . $site .
    //           ' | State: ' . $state .
    //           ' | Expected State: ' . $expected_state .
    //           ' | Got State: ' . $got_state .
    //           ' | Return URL: ' . $return_url .
    //           ' | Final URL: ' . $final_url);
    
    // Send a simple text response instead of JSON
    wp_die('OK', 'OK', array('response' => 200));
}

// Clean up expired authorizations
function peaceprotocol_cleanup_expired_authorizations() {
    $authorizations = get_option('peaceprotocol_authorizations', array());
    $cleaned = false;
    
    foreach ($authorizations as $code => $auth_data) {
        if ($auth_data['used'] || $auth_data['expires'] < time()) {
            unset($authorizations[$code]);
            $cleaned = true;
        }
    }
    
    if ($cleaned) {
        update_option('peaceprotocol_authorizations', $authorizations);
        // error_log('Peace Protocol: Cleaned up expired authorizations');
    }
}

// Clean up on plugin load
add_action('init', 'peaceprotocol_cleanup_expired_authorizations');
add_action('init', 'peaceprotocol_cleanup_expired_indieauth_requests');

// Function to send peace to a target site
function peaceprotocol_send_peace_to_site($target_site, $message, $identity) {
    // error_log('Peace Protocol: send_peace_to_site called');
    // error_log('Peace Protocol: Target site: ' . $target_site);
    // error_log('Peace Protocol: Message: ' . $message);
    // error_log('Peace Protocol: Identity: ' . print_r($identity, true));
    
    // Try REST API first
    $rest_url = $target_site . '/wp-json/peace-protocol/v1/receive';
    $rest_response = wp_remote_post($rest_url, array(
        'headers' => array('Content-Type' => 'application/json'),
        'body' => json_encode(array(
            'target_site' => $target_site,
            'message' => $message,
            'token' => $identity['token'],
            'from_site' => $identity['site_url']
        )),
        'timeout' => 2
    ));
    
    if (!is_wp_error($rest_response)) {
        $rest_status = wp_remote_retrieve_response_code($rest_response);
        $rest_body = wp_remote_retrieve_body($rest_response);
        // error_log('Peace Protocol: REST response status: ' . $rest_status);
        // error_log('Peace Protocol: REST response body: ' . $rest_body);
        
        if ($rest_status === 200) {
            // error_log('Peace Protocol: Peace sent successfully via REST API');
            return true;
        }
    } else {
        // error_log('Peace Protocol: REST API failed: ' . $rest_response->get_error_message());
    }
    
    // Fall back to AJAX
    // For cross-site requests, we need to construct the AJAX URL manually
    // Use our helper function to get the correct admin-ajax.php path
    $ajax_url = peaceprotocol_get_admin_ajax_url($target_site);
    $ajax_response = wp_remote_post($ajax_url, array(
        'headers' => array('Content-Type' => 'application/x-www-form-urlencoded'),
        'body' => http_build_query(array(
            'action' => 'peaceprotocol_receive_peace',
            'from_site' => $identity['site_url'],
            'token' => $identity['token'],
            'note' => $message
        )),
        'timeout' => 2
    ));
    
    if (!is_wp_error($ajax_response)) {
        $ajax_status = wp_remote_retrieve_response_code($ajax_response);
        $ajax_body = wp_remote_retrieve_body($ajax_response);
        // error_log('Peace Protocol: AJAX response status: ' . $ajax_status);
        // error_log('Peace Protocol: AJAX response body: ' . $ajax_body);
        
        if ($ajax_status === 200) {
            $ajax_data = json_decode($ajax_body, true);
            if ($ajax_data && isset($ajax_data['success']) && $ajax_data['success']) {
                // error_log('Peace Protocol: Peace sent successfully via AJAX');
                return true;
            }
        }
    } else {
        // error_log('Peace Protocol: AJAX failed: ' . $ajax_response->get_error_message());
    }
    
    // error_log('Peace Protocol: Both REST and AJAX failed');
    return new WP_Error('send_failed', 'Failed to send peace to target site');
}

// Function to subscribe this site to a feed
function peaceprotocol_subscribe_to_feed($feed_url) {
    // error_log('Peace Protocol: Subscribing to feed: ' . $feed_url);
    
    // Get current subscriptions - use the option that the admin page reads from
    $subscriptions = get_option('peaceprotocol_feeds', array());
    
    // Check if already subscribed
    if (in_array($feed_url, $subscriptions)) {
        // error_log('Peace Protocol: Already subscribed to feed: ' . $feed_url);
        return true;
    }
    
    // Add to subscriptions
    $subscriptions[] = $feed_url;
    update_option('peaceprotocol_feeds', $subscriptions);
    
    // error_log('Peace Protocol: Successfully subscribed to feed: ' . $feed_url);
    // error_log('Peace Protocol: All subscriptions after adding: ' . print_r($subscriptions, true));
    return true;
}

// Test AJAX handler to verify AJAX is working
add_action('wp_ajax_peaceprotocol_test', 'peaceprotocol_ajax_test');
add_action('wp_ajax_nopriv_peaceprotocol_test', 'peaceprotocol_ajax_test');

function peaceprotocol_ajax_test() {
    if (ob_get_level()) {
        ob_clean();
    }
    // error_log('Peace Protocol: Test AJAX handler called');
    wp_send_json_success('AJAX is working');
}

// AJAX handler for completing authorization (both logged-in and non-logged-in)
add_action('wp_ajax_peaceprotocol_complete_auth', 'peaceprotocol_ajax_complete_auth');
add_action('wp_ajax_nopriv_peaceprotocol_complete_auth', 'peaceprotocol_ajax_complete_auth');

function peaceprotocol_ajax_complete_auth() {
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Cross-site endpoint uses token-based authentication
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Cross-site endpoint uses token-based authentication
    // Prevent any output before our response
    if (ob_get_level()) {
        ob_clean();
    }
    
    // Debug logging (commented out for production)
    // error_log('Peace Protocol: peaceprotocol_complete_auth called');
    // error_log('Peace Protocol: POST data: ' . print_r($_POST, true));
    
    try {
        // Check if user is logged in as admin OR if they have a valid token
        $user_authorized = false;
        $token_authorized = false;
        
        if (is_user_logged_in() && current_user_can('manage_options')) {
            $user_authorized = true;
            // error_log('Peace Protocol: User authorized via admin login');
        }
        
        // If not logged in, check if they have a valid token
        if (!$user_authorized && isset($_POST['token'])) {
            $token = sanitize_text_field(wp_unslash($_POST['token']));
            $identity = peaceprotocol_validate_token($token);
            if ($identity) {
                $token_authorized = true;
                // error_log('Peace Protocol: User authorized via token validation');
            }
        }
        
        if (!$user_authorized && !$token_authorized) {
            // error_log('Peace Protocol: User not authorized - logged in: ' . (is_user_logged_in() ? 'yes' : 'no') . ', can manage options: ' . (current_user_can('manage_options') ? 'yes' : 'no') . ', has token: ' . (isset($_POST['token']) ? 'yes' : 'no'));
            wp_send_json_error('Not authorized');
        }
        
        // Get return_site and state from the request
        $return_site = isset($_POST['return_site']) ? esc_url_raw(wp_unslash($_POST['return_site'])) : '';
        $state = isset($_POST['state']) ? sanitize_text_field(wp_unslash($_POST['state'])) : '';
        
        // error_log('Peace Protocol: Return site: ' . $return_site . ', State: ' . $state);
        
        if (!$return_site || !$state) {
            // error_log('Peace Protocol: Missing return_site or state');
            wp_send_json_error('Missing return site or state');
        }
        
        // error_log('Peace Protocol: User authorized, generating auth code');
        
        // Generate authorization code
        $auth_code = peaceprotocol_generate_secure_token(32);
        $expires = time() + 300; // 5 minutes
        
        // error_log('Peace Protocol: Generated auth code: ' . $auth_code . ' (length: ' . strlen($auth_code) . ')');
        
        // Store authorization code
        $authorizations = get_option('peaceprotocol_authorizations', array());
        // error_log('Peace Protocol: Current authorizations before adding: ' . print_r($authorizations, true));
        
        $authorizations[$auth_code] = array(
            'site_url' => get_site_url(),
            'return_site' => $return_site,
            'expires' => $expires,
            'used' => false
        );
        
        $update_result = update_option('peaceprotocol_authorizations', $authorizations);
        // error_log('Peace Protocol: Authorization code storage result: ' . ($update_result ? 'success' : 'failed'));
        // error_log('Peace Protocol: Authorizations after adding: ' . print_r($authorizations, true));
        
        // Subscribe to the return site's feed
        $subscribe_result = peaceprotocol_subscribe_to_feed($return_site);
        // error_log('Peace Protocol: Subscribe to feed result: ' . ($subscribe_result ? 'success' : 'failed'));
        
        // Build redirect URL with the original return_site and state
        $redirect_url = $return_site;
        $separator = strpos($return_site, '?') !== false ? '&' : '?';
        $redirect_url .= $separator . 'peace_authorization_code=' . $auth_code . '&peace_federated_site=' . get_site_url() . '&peace_federated_state=' . $state;
        
        // error_log('Peace Protocol: Redirect URL: ' . $redirect_url);
        
        $response_data = array('redirect_url' => $redirect_url);
        // error_log('Peace Protocol: Sending response: ' . print_r($response_data, true));
        // error_log('Peace Protocol: Response data type: ' . gettype($response_data));
        // error_log('Peace Protocol: Response data keys: ' . print_r(array_keys($response_data), true));
        
        wp_send_json_success($response_data);
        
    } catch (Exception $e) {
        // error_log('Peace Protocol: Exception in complete_auth: ' . $e->getMessage());
        // error_log('Peace Protocol: Exception trace: ' . $e->getTraceAsString());
        wp_send_json_error('Internal error: ' . $e->getMessage());
    } catch (Error $e) {
        // error_log('Peace Protocol: Error in complete_auth: ' . $e->getMessage());
        // error_log('Peace Protocol: Error trace: ' . $e->getTraceAsString());
        wp_send_json_error('Internal error: ' . $e->getMessage());
    }
}

// AJAX handler for completing IndieAuth authentication
add_action('wp_ajax_peaceprotocol_complete_indieauth_auth', 'peaceprotocol_ajax_complete_indieauth_auth');
add_action('wp_ajax_nopriv_peaceprotocol_complete_indieauth_auth', 'peaceprotocol_ajax_complete_indieauth_auth');

function peaceprotocol_ajax_complete_indieauth_auth() {
    // Prevent any output before our response
    if (ob_get_level()) {
        ob_clean();
    }
    
    try {
        // Check if user is logged in (should be logged in from IndieAuth)
        if (!is_user_logged_in()) {
            wp_send_json_error('Not logged in');
        }
        
        // Get target_site and state from the request
        $target_site = isset($_POST['target_site']) ? esc_url_raw(wp_unslash($_POST['target_site'])) : '';
        $state = isset($_POST['state']) ? sanitize_text_field(wp_unslash($_POST['state'])) : '';
        
        if (!$target_site || !$state) {
            wp_send_json_error('Missing target site or state');
        }
        
        // Get the current user
        $user = wp_get_current_user();
        if (!$user) {
            wp_send_json_error('No current user');
        }
        
        // Generate authorization code
        $auth_code = peaceprotocol_generate_secure_token(32);
        $expires = time() + 300; // 5 minutes
        
        // Store authorization code
        $authorizations = get_option('peaceprotocol_authorizations', array());
        $authorizations[$auth_code] = array(
            'site_url' => get_site_url(),
            'return_site' => $target_site,
            'expires' => $expires,
            'used' => false,
            'auth_type' => 'indieauth',
            'user_id' => $user->ID
        );
        update_option('peaceprotocol_authorizations', $authorizations);
        
        // Subscribe to the target site's feed
        peaceprotocol_subscribe_to_feed($target_site);
        
        // Build redirect URL with the authorization code
        $redirect_url = $target_site;
        $separator = strpos($target_site, '?') !== false ? '&' : '?';
        $redirect_url .= $separator . 'peace_authorization_code=' . $auth_code . '&peace_federated_site=' . get_site_url() . '&peace_federated_state=' . $state;
        
        $response_data = array('redirect_url' => $redirect_url);
        wp_send_json_success($response_data);
        
    } catch (Exception $e) {
        wp_send_json_error('Internal error: ' . $e->getMessage());
    } catch (Error $e) {
        wp_send_json_error('Internal error: ' . $e->getMessage());
    }
}

// Handle federated authorization code return on page load
add_action('template_redirect', function() {
    // error_log('Peace Protocol: template_redirect hook called');
    // error_log('Peace Protocol: Current URL: ' . esc_html($_SERVER['REQUEST_URI'] ?? ''));
    // error_log('Peace Protocol: Full URL: ' . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . esc_html($_SERVER['HTTP_HOST'] ?? '') . esc_html($_SERVER['REQUEST_URI'] ?? ''));
    // error_log('Peace Protocol: GET parameters: ' . print_r(array_map('esc_html', $_GET), true));
    // error_log('Peace Protocol: QUERY_STRING: ' . esc_html($_SERVER['QUERY_STRING'] ?? ''));
    // error_log('Peace Protocol: HTTP_REFERER: ' . (isset($_SERVER['HTTP_REFERER']) ? esc_html($_SERVER['HTTP_REFERER']) : 'none'));
    
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Cross-site endpoint uses authorization code authentication
    if (
        isset($_GET['peace_authorization_code']) &&
        isset($_GET['peace_federated_site']) &&
        isset($_GET['peace_federated_state'])
    ) {
        // error_log('Peace Protocol: Authorization code parameters found in URL');
        
        $auth_code = sanitize_text_field(wp_unslash($_GET['peace_authorization_code']));
        $federated_site = esc_url_raw(wp_unslash($_GET['peace_federated_site']));
        $state = sanitize_text_field(wp_unslash($_GET['peace_federated_state']));

        // error_log('Peace Protocol: Authorization code return detected - code: ' . $auth_code . ', site: ' . $federated_site . ', state: ' . $state);

        // Check if this is an IndieAuth authorization code (from our own site)
        $authorizations = get_option('peaceprotocol_authorizations', array());
        if (isset($authorizations[$auth_code]) && !$authorizations[$auth_code]['used'] && $authorizations[$auth_code]['expires'] > time()) {
            // This is an IndieAuth authorization code from our own site
            error_log('Peace Protocol: Processing IndieAuth authorization code from own site');
            
            $auth_data = $authorizations[$auth_code];
            $user = get_user_by('ID', $auth_data['user_id']);
            
            if ($user) {
                // Mark the authorization code as used
                $authorizations[$auth_code]['used'] = true;
                update_option('peaceprotocol_authorizations', $authorizations);
                
                // Log in the user
                wp_set_current_user($user->ID);
                wp_set_auth_cookie($user->ID);
                
                error_log('Peace Protocol: IndieAuth user logged in successfully: ' . $user->user_login);
                
                // Set session data to show the peace modal
                if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
                    session_start();
                }
                $_SESSION['peace_show_modal_after_login'] = true;
                $_SESSION['peace_federated_site'] = $federated_site;
                $_SESSION['peace_federated_token'] = 'indieauth_' . $user->ID;
                $_SESSION['peace_authorization_code'] = $auth_code;
                
                // Clean up the URL and redirect to homepage
                $clean_url = remove_query_arg(['peace_authorization_code', 'peace_federated_site', 'peace_federated_state']);
                wp_redirect($clean_url);
                exit;
            } else {
                error_log('Peace Protocol: IndieAuth user not found: ' . $auth_data['user_id']);
                $clean_url = remove_query_arg(['peace_authorization_code', 'peace_federated_site', 'peace_federated_state']);
                wp_redirect($clean_url);
                exit;
            }
        } else {
            // This is a regular federated authorization code, try to exchange it for a token
            error_log('Peace Protocol: Processing regular federated authorization code');
            
            $token = peaceprotocol_exchange_auth_code_for_token($auth_code, $federated_site);
            
            if ($token) {
                // error_log('Peace Protocol: Successfully exchanged auth code for token');
                
                // Create or get federated user using the federated site URL
                $user = peaceprotocol_create_or_get_federated_user($federated_site, $token);
                if ($user) {
                    // error_log('Peace Protocol: Federated login successful for site: ' . $federated_site . ', user: ' . $user->user_login);
                    
                    // Set a session flag to show the peace modal
                    if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
                        session_start();
                    }
                    $_SESSION['peace_show_modal_after_login'] = true;
                    $_SESSION['peace_federated_site'] = $federated_site;
                    $_SESSION['peace_federated_token'] = $token;
                    $_SESSION['peace_authorization_code'] = $auth_code; // Store auth code in session
                    
                    // error_log('Peace Protocol: Session data stored - federated_site: ' . $federated_site . ', auth_code: ' . $auth_code);
                    
                    // Clean up the URL and redirect to homepage
                    $clean_url = remove_query_arg(['peace_authorization_code', 'peace_federated_site', 'peace_federated_state']);
                    
                    // error_log('Peace Protocol: Redirecting to clean URL: ' . $clean_url);
                    
                    wp_redirect($clean_url);
                    exit;
                } else {
                    // error_log('Peace Protocol: Failed to create federated user for site: ' . $federated_site);
                    // Clean up the URL and show error
                    $clean_url = remove_query_arg(['peace_authorization_code', 'peace_federated_site', 'peace_federated_state']);
                    wp_redirect($clean_url);
                    exit;
                }
            } else {
                // error_log('Peace Protocol: Failed to exchange auth code for token');
                // Clean up the URL and show error
                $clean_url = remove_query_arg(['peace_authorization_code', 'peace_federated_site', 'peace_federated_state']);
                wp_redirect($clean_url);
                exit;
            }
        }
    } else {
        // error_log('Peace Protocol: No authorization code parameters found in URL');
    }
}); // Close the anonymous function

// Removed old IndieAuth callback handler from init hook - now handled in parse_request

// Check for session flag to show peace modal after federated login
add_action('wp_footer', function() {
    // Check if session is already started to avoid headers already sent error
    if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
        session_start();
    }
    
    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Session data is controlled by our own code
    if (isset($_SESSION['peace_show_modal_after_login']) && $_SESSION['peace_show_modal_after_login']) {
        // Get the session data before clearing it
        $federated_site = esc_url_raw($_SESSION['peace_federated_site'] ?? '');
        $federated_token = sanitize_text_field($_SESSION['peace_federated_token'] ?? '');
        $auth_code = sanitize_text_field($_SESSION['peace_authorization_code'] ?? '');
        
        // Clear the session flag
        unset($_SESSION['peace_show_modal_after_login']);
        unset($_SESSION['peace_federated_site']);
        unset($_SESSION['peace_federated_token']);
        unset($_SESSION['peace_authorization_code']);
        
        // Show the peace modal
        ?>
        <script>
        (function() {
            // console.log('[Peace Protocol] Session flag detected, setting up localStorage and showing peace modal');
            // console.log('[Peace Protocol] Session data - federated_site:', '<?php echo esc_js($federated_site); ?>');
            // console.log('[Peace Protocol] Session data - auth_code:', '<?php echo esc_js($auth_code); ?>');
            
            // Set up localStorage identity for the federated user
            const identities = JSON.parse(localStorage.getItem('peace-protocol-identities') || '[]');
            const newIdentity = {
                site: '<?php echo esc_js($federated_site); ?>',
                token: '<?php echo esc_js($federated_token); ?>'
            };
            
            // Remove any existing identity for this site
            const filteredIdentities = identities.filter(id => id.site !== '<?php echo esc_js($federated_site); ?>');
            
            // Add the new identity
            filteredIdentities.push(newIdentity);
            
            localStorage.setItem('peace-protocol-identities', JSON.stringify(filteredIdentities));
            // console.log('[Peace Protocol] Set up localStorage identity for federated user:', newIdentity);
            
            // Set up the authorization code in localStorage
            const authCode = '<?php echo esc_js($auth_code); ?>';
            if (authCode) {
                let authorizations = [];
                const stored = localStorage.getItem('peace-protocol-authorizations');
                if (stored) {
                    authorizations = JSON.parse(stored);
                    if (!Array.isArray(authorizations)) authorizations = [];
                }
                
                // Add the authorization code
                const newAuth = {
                    site: '<?php echo esc_js($federated_site); ?>',
                    code: authCode,
                    timestamp: Date.now()
                };
                
                // Remove any existing authorization for this site
                authorizations = authorizations.filter(auth => auth.site !== '<?php echo esc_js($federated_site); ?>');
                
                // Add the new authorization
                authorizations.push(newAuth);
                
                localStorage.setItem('peace-protocol-authorizations', JSON.stringify(authorizations));
                // console.log('[Peace Protocol] Set up authorization code for federated user:', newAuth);
            } else {
                // console.log('[Peace Protocol] No authorization code found in session');
            }
            
            // Small delay to ensure localStorage is set up before showing modal
            setTimeout(function() {
                if (window.peaceProtocolShowSendPeaceModal) {
                    // console.log('[Peace Protocol] Showing peace modal from session flag');
                    window.peaceProtocolShowSendPeaceModal();
                } else {
                    // console.log('[Peace Protocol] Peace modal function not available from session flag');
                }
            }, 500);
        })();
        </script>
        <?php
    }
});

// Function to exchange authorization code for token from federated site
function peaceprotocol_exchange_auth_code_for_token($auth_code, $federated_site) {
    // error_log('Peace Protocol: Exchanging auth code for token - code: ' . $auth_code . ', site: ' . $federated_site);
    
    // Make a request to the federated site to exchange the auth code for a token
    $response = wp_remote_post($federated_site . '/wp-json/peace-protocol/v1/federated-exchange', array(
        'body' => array(
            'code' => $auth_code,
            'site' => get_site_url()
        ),
        'timeout' => 30
    ));
    
    // error_log('Peace Protocol: Exchange response: ' . print_r($response, true));
    
    if (is_wp_error($response)) {
        // error_log('Peace Protocol: Exchange request failed: ' . $response->get_error_message());
        return false;
    }
    
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    
    // error_log('Peace Protocol: Exchange response body: ' . $body);
    // error_log('Peace Protocol: Exchange response data: ' . print_r($data, true));
    
    if ($data && isset($data['token'])) {
        // error_log('Peace Protocol: Successfully received token from federated site');
        return $data['token'];
    } else {
        // error_log('Peace Protocol: No token in exchange response');
        return false;
    }
}

// Function to create or get a federated user
function peaceprotocol_create_or_get_federated_user($federated_site, $token) {
    // error_log('Peace Protocol: create_or_get_federated_user called for site: ' . $federated_site . ', token: ' . substr($token, 0, 8) . '...');
    
    // Check if this is an IndieAuth user by looking at the authorization data
    $authorizations = get_option('peaceprotocol_authorizations', array());
    $is_indieauth_user = false;
    $indieauth_user_id = null;
    
    foreach ($authorizations as $auth_code => $auth_data) {
        if ($auth_data['site_url'] === $federated_site && 
            isset($auth_data['auth_type']) && 
            $auth_data['auth_type'] === 'indieauth' &&
            isset($auth_data['user_id'])) {
            $is_indieauth_user = true;
            $indieauth_user_id = $auth_data['user_id'];
            break;
        }
    }
    
    // If this is an IndieAuth user, get the user directly
    if ($is_indieauth_user && $indieauth_user_id) {
        $user = get_user_by('ID', $indieauth_user_id);
        if ($user) {
            // Log in the IndieAuth user
            wp_set_current_user($user->ID);
            wp_set_auth_cookie($user->ID);
            return $user;
        }
    }
    
    // First, check if a federated user already exists for this site
    $existing_users = get_users(array(
        'meta_key' => 'federated_site',
        'meta_value' => $federated_site,
        'number' => 1
    ));
    
    if (!empty($existing_users)) {
        $existing_user = $existing_users[0];
        // error_log('Peace Protocol: Found existing federated user for site: ' . $federated_site . ' - username: ' . $existing_user->user_login);
        
        // Update the token in case it changed
        update_user_meta($existing_user->ID, 'federated_token', $token);
        
        // Log in the existing user
        wp_set_current_user($existing_user->ID);
        wp_set_auth_cookie($existing_user->ID);
        
        return $existing_user;
    }
    
    // No existing user found, create a new one
    // error_log('Peace Protocol: No existing federated user found for site: ' . $federated_site . ', creating new user');
    
    // Extract domain name for cleaner username
    $parsed_url = wp_parse_url($federated_site);
    $domain = $parsed_url['host'];
    $clean_domain = preg_replace('/[^a-zA-Z0-9]/', '', $domain); // Remove non-alphanumeric chars
    
    // Try the clean domain name first
    $username = $clean_domain;
    
    // Check if username is available, if not, try with peace_ prefix
    if (username_exists($username)) {
        $username = 'peace_' . $clean_domain;
        // error_log('Peace Protocol: Clean domain username taken, using peace_ prefix: ' . $username);
    }
    
    // Create new federated user
    $email = $username . '@' . $domain;
    $display_name = 'Federated User from ' . $domain;
    
    // error_log('Peace Protocol: Creating new federated user - username: ' . $username . ', email: ' . $email);
    
    $user_id = wp_create_user($username, wp_generate_password(), $email);
    if (is_wp_error($user_id)) {
        // error_log('Peace Protocol: Failed to create federated user: ' . $user_id->get_error_message());
        return false;
    }
    
    // Set user role and metadata
    $user = new WP_User($user_id);
    $user->set_role('federated_peer');
    update_user_meta($user_id, 'federated_site', $federated_site);
    update_user_meta($user_id, 'federated_token', $token);
    update_user_meta($user_id, 'display_name', $display_name);
    
    // error_log('Peace Protocol: Federated user created successfully: ' . $user->user_login);
    
    // Log in the new user
    wp_set_current_user($user_id);
    wp_set_auth_cookie($user_id);
    
    return $user;
}

// Helper function to get IndieAuth endpoint with fallbacks
function peaceprotocol_get_indieauth_endpoint($type, $site_url = null) {
    if (!$site_url) {
        $site_url = home_url();
    }
    
    error_log('Peace Protocol: Getting IndieAuth endpoint for type: ' . $type . ', site: ' . $site_url);
    
    // For the current site, use the official IndieAuth functions if available
    if ($site_url === home_url() && function_exists('indieauth_get_endpoint')) {
        $endpoint = indieauth_get_endpoint($type);
        error_log('Peace Protocol: Using official IndieAuth endpoint: ' . $endpoint);
        return $endpoint;
    }
    
    // For remote sites or when IndieAuth plugin is not available, use discovery
    // Don't use admin-ajax URLs as they can cause admin redirects
    $endpoint = '';
    switch ($type) {
        case 'authorization':
            // Use the site's own authorization endpoint if available
            $endpoint = $site_url . '/?indieauth_authorization';
            break;
        case 'token':
            // Use the site's own token endpoint if available
            $endpoint = $site_url . '/?indieauth_token';
            break;
        default:
            // Use a generic endpoint
            $endpoint = $site_url . '/?indieauth_' . $type;
            break;
    }
    
    error_log('Peace Protocol: Using fallback endpoint: ' . $endpoint);
    return $endpoint;
}



// IndieAuth callback handler - now handled in template_redirect like regular Peace Protocol
function peaceprotocol_indieauth_callback_handler() {
    // This function is kept for compatibility but the actual handling is done in template_redirect
}

// Exchange IndieAuth authorization code for profile
function peaceprotocol_indieauth_exchange_code($code, $auth_request, $target_site = null) {
    // Use our own token endpoint
    $token_endpoint = home_url('/peace-indieauth-token/');
    
    // Get the code verifier from the authorization request
    $code_verifier = $auth_request['code_verifier'] ?? null;
    
    // Make POST request to our token endpoint
    $response = wp_remote_post($token_endpoint, array(
        'body' => array(
            'grant_type' => 'authorization_code',
            'code' => $code,
            'client_id' => $auth_request['client_id'],
            'redirect_uri' => $auth_request['redirect_uri'],
            'code_verifier' => $code_verifier
        ),
        'timeout' => 30
    ));
    
    if (is_wp_error($response)) {
        error_log('Peace Protocol: Token exchange error: ' . $response->get_error_message());
        return false;
    }
    
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    
    if (!$data || isset($data['error'])) {
        error_log('Peace Protocol: Token exchange failed: ' . ($data['error_description'] ?? 'Unknown error'));
        return false;
    }
    
    return $data;
}

// Test handler to check IndieAuth plugin status
function peaceprotocol_indieauth_test_handler() {
    $status = array();
    
    // Check if IndieAuth plugin class exists
    $status['class_exists'] = class_exists('IndieAuth_Plugin');
    
    // Check if functions exist
    $status['function_indieauth_get_endpoint'] = function_exists('indieauth_get_endpoint');
    $status['function_indieauth_get_me'] = function_exists('indieauth_get_me');
    $status['function_indieauth_get_client_id'] = function_exists('indieauth_get_client_id');
    
    // Try to get endpoints
    if (function_exists('indieauth_get_endpoint')) {
        $status['authorization_endpoint'] = indieauth_get_endpoint('authorization');
        $status['token_endpoint'] = indieauth_get_endpoint('token');
    } else {
        $status['authorization_endpoint'] = 'function_not_available';
        $status['token_endpoint'] = 'function_not_available';
    }
    
    // Check if user is logged in
    $status['user_logged_in'] = is_user_logged_in();
    $status['current_user_id'] = get_current_user_id();
    
    // Check if user can manage options (admin)
    $status['can_manage_options'] = current_user_can('manage_options');
    
    wp_send_json($status);
}

// Create or get IndieAuth federated user
function peaceprotocol_create_or_get_indieauth_user($profile_url, $profile_data) {
    // Parse domain from profile URL
    $parsed_url = wp_parse_url($profile_url);
    if (!$parsed_url || !isset($parsed_url['host'])) {
        return false;
    }
    
    $domain = $parsed_url['host'];
    $clean_domain = preg_replace('/[^a-zA-Z0-9]/', '', $domain); // Remove non-alphanumeric chars (same as regular Peace Protocol)
    
    // First, check if a federated user already exists for this domain (from regular Peace Protocol)
    $existing_users = get_users(array(
        'meta_key' => 'federated_site',
        'meta_value' => $profile_url,
        'number' => 1
    ));
    
    if (!empty($existing_users)) {
        $existing_user = $existing_users[0];
        // Update the user with IndieAuth metadata
        update_user_meta($existing_user->ID, 'indieauth_profile_url', $profile_url);
        update_user_meta($existing_user->ID, 'indieauth_profile_data', $profile_data);
        
        // Update display name from profile if available
        if (isset($profile_data['profile']['name'])) {
            update_user_meta($existing_user->ID, 'display_name', $profile_data['profile']['name']);
        }
        
        // Log in the existing user
        wp_set_current_user($existing_user->ID);
        wp_set_auth_cookie($existing_user->ID);
        
        return $existing_user;
    }
    
    // Try the clean domain name first (same as regular Peace Protocol)
    $username = $clean_domain;
    
    // Check if username is available, if not, try with peace_ prefix
    if (username_exists($username)) {
        $username = 'peace_' . $clean_domain;
    }
    
    // Check if user already exists by username
    $user = get_user_by('login', $username);
    
    if (!$user) {
        // Create new user
        $user_id = wp_create_user($username, wp_generate_password(64, true, true), $username . '@' . $domain);
        
        if (is_wp_error($user_id)) {
            return false;
        }
        
        // Set user role and metadata
        $user = new WP_User($user_id);
        $user->set_role('federated_peer');
        update_user_meta($user_id, 'federated_site', $profile_url); // Same as regular Peace Protocol
        update_user_meta($user_id, 'indieauth_profile_url', $profile_url);
        update_user_meta($user_id, 'indieauth_profile_data', $profile_data);
        update_user_meta($user_id, 'display_name', 'Federated User from ' . $domain); // Same as regular Peace Protocol
        
        // Set display name from profile if available
        if (isset($profile_data['profile']['name'])) {
            update_user_meta($user_id, 'display_name', $profile_data['profile']['name']);
        }
        
        // Store profile information
        if (isset($profile_data['profile'])) {
            update_user_meta($user_id, 'indieauth_profile', $profile_data['profile']);
            
            // Store individual profile fields
            if (isset($profile_data['profile']['name'])) {
                update_user_meta($user_id, 'indieauth_name', $profile_data['profile']['name']);
            }
            if (isset($profile_data['profile']['email'])) {
                update_user_meta($user_id, 'indieauth_email', $profile_data['profile']['email']);
            }
            if (isset($profile_data['profile']['photo'])) {
                update_user_meta($user_id, 'indieauth_photo', $profile_data['profile']['photo']);
            }
            if (isset($profile_data['profile']['url'])) {
                update_user_meta($user_id, 'indieauth_url', $profile_data['profile']['url']);
            }
        }
        
        // Store access token if provided
        if (isset($profile_data['access_token'])) {
            update_user_meta($user_id, 'indieauth_access_token', $profile_data['access_token']);
        }
        
        // Store refresh token if provided
        if (isset($profile_data['refresh_token'])) {
            update_user_meta($user_id, 'indieauth_refresh_token', $profile_data['refresh_token']);
        }
        
        // Store token expiration if provided
        if (isset($profile_data['expires_in'])) {
            $expires_at = time() + intval($profile_data['expires_in']);
            update_user_meta($user_id, 'indieauth_token_expires_at', $expires_at);
        }
        
        // Store scope if provided
        if (isset($profile_data['scope'])) {
            update_user_meta($user_id, 'indieauth_scope', $profile_data['scope']);
        }
        
        // If IndieAuth plugin is available, try to get additional profile info
        if (class_exists('IndieAuth_Plugin') && function_exists('indieauth_get_me')) {
            $me = indieauth_get_me();
            if ($me) {
                update_user_meta($user_id, 'indieauth_me', $me);
            }
        }
    } else {
        // Update existing user's profile data
        update_user_meta($user->ID, 'indieauth_profile_data', $profile_data);
        
        // Update display name from profile if available
        if (isset($profile_data['profile']['name'])) {
            update_user_meta($user->ID, 'display_name', $profile_data['profile']['name']);
        }
        
        // Update profile information
        if (isset($profile_data['profile'])) {
            update_user_meta($user->ID, 'indieauth_profile', $profile_data['profile']);
            
            // Update individual profile fields
            if (isset($profile_data['profile']['name'])) {
                update_user_meta($user->ID, 'indieauth_name', $profile_data['profile']['name']);
            }
            if (isset($profile_data['profile']['email'])) {
                update_user_meta($user->ID, 'indieauth_email', $profile_data['profile']['email']);
            }
            if (isset($profile_data['profile']['photo'])) {
                update_user_meta($user->ID, 'indieauth_photo', $profile_data['profile']['photo']);
            }
            if (isset($profile_data['profile']['url'])) {
                update_user_meta($user->ID, 'indieauth_url', $profile_data['profile']['url']);
            }
        }
        
        // Update access token if provided
        if (isset($profile_data['access_token'])) {
            update_user_meta($user->ID, 'indieauth_access_token', $profile_data['access_token']);
        }
        
        // Update refresh token if provided
        if (isset($profile_data['refresh_token'])) {
            update_user_meta($user->ID, 'indieauth_refresh_token', $profile_data['refresh_token']);
        }
        
        // Update token expiration if provided
        if (isset($profile_data['expires_in'])) {
            $expires_at = time() + intval($profile_data['expires_in']);
            update_user_meta($user->ID, 'indieauth_token_expires_at', $expires_at);
        }
        
        // Update scope if provided
        if (isset($profile_data['scope'])) {
            update_user_meta($user->ID, 'indieauth_scope', $profile_data['scope']);
        }
        
        // Update IndieAuth 'me' property if available
        if (class_exists('IndieAuth_Plugin') && function_exists('indieauth_get_me')) {
            $me = indieauth_get_me();
            if ($me) {
                update_user_meta($user->ID, 'indieauth_me', $me);
            }
        }
    }
    
    return $user;
}

// Clean up expired IndieAuth requests
function peaceprotocol_cleanup_expired_indieauth_requests() {
    $auth_requests = get_option('peaceprotocol_indieauth_requests', array());
    $cleaned = false;
    
    foreach ($auth_requests as $state => $auth_data) {
        if ($auth_data['used'] || $auth_data['expires'] < time()) {
            unset($auth_requests[$state]);
            $cleaned = true;
        }
    }
    
    if ($cleaned) {
        update_option('peaceprotocol_indieauth_requests', $auth_requests);
    }
}

// IndieAuth token exchange handler
function peaceprotocol_indieauth_token_handler() {
    $code = sanitize_text_field($_POST['code'] ?? '');
    $code_verifier = sanitize_text_field($_POST['code_verifier'] ?? '');
    $state = sanitize_text_field($_POST['state'] ?? '');
    $iss = sanitize_text_field($_POST['iss'] ?? '');
    $target_site = sanitize_text_field($_POST['target_site'] ?? '');
    $metadata_str = sanitize_text_field($_POST['metadata'] ?? '');
    
    if (!$code || !$code_verifier || !$state || !$target_site) {
        wp_send_json_error('Missing required parameters');
        return;
    }
    
    // Parse metadata if provided
    $metadata = null;
    if ($metadata_str) {
        try {
            $metadata = json_decode($metadata_str, true);
        } catch (Exception $e) {
            error_log('Peace Protocol: Failed to parse IndieAuth metadata: ' . $e->getMessage());
        }
    }
    
    // Get the stored auth request to validate the code
    $auth_requests = get_option('peaceprotocol_indieauth_requests', array());
    $auth_request = null;
    
    foreach ($auth_requests as $stored_state => $request_data) {
        if ($request_data['code'] === $code && !$request_data['used']) {
            $auth_request = $request_data;
            break;
        }
    }
    
    if (!$auth_request) {
        wp_send_json_error('Invalid or expired authorization code');
        return;
    }
    
    // Check if code is expired (10 minutes)
    if ($auth_request['expires'] < time()) {
        wp_send_json_error('Authorization code expired');
        return;
    }
    
    // Validate state parameter
    if ($auth_request['state'] !== $state) {
        wp_send_json_error('Invalid state parameter');
        return;
    }
    
    // Validate code verifier using PKCE
                $expected_challenge = peaceprotocol_base64url_encode(hash('sha256', $code_verifier, true));
    if ($auth_request['code_challenge'] !== $expected_challenge) {
        wp_send_json_error('Invalid code verifier');
        return;
    }
    
    // Mark the code as used
    $auth_requests[$state]['used'] = true;
    update_option('peaceprotocol_indieauth_requests', $auth_requests);
    
    // Exchange the code for user profile information
    try {
        // Use discovered token endpoint if available, otherwise fall back to authorization endpoint
        $token_endpoint = null;
        $is_authorization_endpoint = false;
        
        if ($metadata && isset($metadata['token_endpoint'])) {
            $token_endpoint = $metadata['token_endpoint'];
        } else {
            // Fall back to authorization endpoint for profile-only requests
            $token_endpoint = $metadata['authorization_endpoint'] ?? null;
            $is_authorization_endpoint = true;
        }
        
        if (!$token_endpoint) {
            wp_send_json_error('No token endpoint available');
            return;
        }
        
        // Fetch client information from client_id URL for security
        $client_info = null;
        $client_id_url = home_url();
        
        // Don't fetch if client_id is localhost (security measure)
        $parsed_client_id = wp_parse_url($client_id_url);
        if ($parsed_client_id && isset($parsed_client_id['host'])) {
            $host = $parsed_client_id['host'];
            if ($host !== '127.0.0.1' && $host !== 'localhost' && $host !== '[::1]') {
                $client_response = wp_remote_get($client_id_url, array(
                    'timeout' => 30,
                    'headers' => array(
                        'Accept' => 'application/json,text/html'
                    )
                ));
                
                if (!is_wp_error($client_response)) {
                    $content_type = wp_remote_retrieve_header($client_response, 'content-type');
                    if (strpos($content_type, 'application/json') !== false) {
                        $client_info = json_decode(wp_remote_retrieve_body($client_response), true);
                    }
                }
                
                error_log("Peace Protocol: Fetched client info from {$client_id_url}: " . json_encode($client_info));
            }
        }
        
        // Make the token exchange request
        $response = wp_remote_post($token_endpoint, array(
            'body' => array(
                'grant_type' => 'authorization_code',
                'code' => $code,
                'client_id' => home_url(),
                'redirect_uri' => home_url() . '/?peace_indieauth_callback=1',
                'code_verifier' => $code_verifier
            ),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error('Token exchange request failed: ' . $response->get_error_message());
            return;
        }
        
        $body = wp_remote_retrieve_body($response);
        $profile_data = json_decode($body, true);
        
        if (!$profile_data) {
            wp_send_json_error('Invalid JSON response from token endpoint');
            return;
        }
        
        if (!isset($profile_data['me'])) {
            wp_send_json_error('Missing "me" property in token endpoint response');
            return;
        }
        
        // Check for OAuth 2.0 error responses
        if (isset($profile_data['error'])) {
            $error_description = isset($profile_data['error_description']) ? $profile_data['error_description'] : '';
            wp_send_json_error('Token endpoint error: ' . $profile_data['error'] . ($error_description ? ' - ' . $error_description : ''));
            return;
        }
        
        // Authorization Server Confirmation: Verify the returned profile URL
        $returned_profile_url = $profile_data['me'];
        $original_domain = $target_site;
        
        // Check if the returned profile URL is an exact match or was encountered during discovery
        $profile_urls_encountered = array($original_domain);
        if ($metadata && isset($metadata['issuer'])) {
            $profile_urls_encountered[] = $metadata['issuer'];
        }
        
        $needs_verification = true;
        foreach ($profile_urls_encountered as $encountered_url) {
            if ($returned_profile_url === $encountered_url) {
                $needs_verification = false;
                break;
            }
        }
        
        // If verification is needed, discover the authorization server from the returned profile URL
        if ($needs_verification) {
            $returned_metadata = peaceprotocol_discover_indieauth_metadata($returned_profile_url);
            if (!$returned_metadata || !isset($returned_metadata['authorization_endpoint'])) {
                wp_send_json_error('Failed to verify authorization server for returned profile URL');
                return;
            }
            
            // Verify the authorization endpoints match
            $original_auth_endpoint = $metadata['authorization_endpoint'] ?? '';
            $returned_auth_endpoint = $returned_metadata['authorization_endpoint'] ?? '';
            
            if ($original_auth_endpoint !== $returned_auth_endpoint) {
                wp_send_json_error('Authorization server mismatch for returned profile URL');
                return;
            }
            
            error_log("Peace Protocol: Verified authorization server for returned profile URL: {$returned_profile_url}");
        }
        
        // Create or get the IndieAuth user
        $user = peaceprotocol_create_or_get_indieauth_user($profile_data['me'], $profile_data);
        
        if (!$user) {
            wp_send_json_error('Failed to create user account');
            return;
        }
        
        // Generate a Peace Protocol token for this user
        $token = peaceprotocol_generate_secure_token(64);
        
        // Store the token in user meta
        update_user_meta($user->ID, 'peaceprotocol_token', $token);
        update_user_meta($user->ID, 'peaceprotocol_site', $target_site);
        update_user_meta($user->ID, 'peaceprotocol_auth_method', 'indieauth');
        update_user_meta($user->ID, 'peaceprotocol_profile_url', $profile_data['me']);
        
        // Log the successful IndieAuth login
        error_log("Peace Protocol: Successful IndieAuth login for user {$user->ID} from site {$target_site}");
        
        wp_send_json_success([
            'message' => 'IndieAuth login successful',
            'user_id' => $user->ID,
            'profile_url' => $profile_data['me'],
            'site' => $target_site,
            'auth_method' => 'indieauth'
        ]);
        
    } catch (Exception $e) {
        error_log('Peace Protocol: IndieAuth token exchange error: ' . $e->getMessage());
        wp_send_json_error('Token exchange failed: ' . $e->getMessage());
    }
}

// Helper function for base64url encoding
function peaceprotocol_base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

// Discover IndieAuth metadata function moved to top of file to avoid function order issues

// Refresh IndieAuth access token
function peaceprotocol_refresh_indieauth_token($user_id, $metadata = null) {
    $refresh_token = get_user_meta($user_id, 'indieauth_refresh_token', true);
    $target_site = get_user_meta($user_id, 'peaceprotocol_site', true);
    
    if (!$refresh_token || !$target_site) {
        return false;
    }
    
    // If no metadata provided, discover it
    if (!$metadata) {
        $metadata = peaceprotocol_discover_indieauth_metadata($target_site);
        if (!$metadata) {
            return false;
        }
    }
    
    // Use token endpoint for refresh
    $token_endpoint = $metadata['token_endpoint'] ?? null;
    if (!$token_endpoint) {
        error_log("Peace Protocol: No token endpoint available for refresh");
        return false;
    }
    
    // Make refresh request
    $response = wp_remote_post($token_endpoint, array(
        'body' => array(
            'grant_type' => 'refresh_token',
            'refresh_token' => $refresh_token,
            'client_id' => home_url()
        ),
        'timeout' => 30
    ));
    
    if (is_wp_error($response)) {
        error_log('Peace Protocol: Refresh token request failed: ' . $response->get_error_message());
        return false;
    }
    
    $body = wp_remote_retrieve_body($response);
    $token_data = json_decode($body, true);
    
    if (!$token_data || isset($token_data['error'])) {
        $error = isset($token_data['error']) ? $token_data['error'] : 'Invalid response';
        error_log("Peace Protocol: Refresh token error: {$error}");
        return false;
    }
    
    // Update stored tokens
    if (isset($token_data['access_token'])) {
        update_user_meta($user_id, 'indieauth_access_token', $token_data['access_token']);
    }
    
    if (isset($token_data['refresh_token'])) {
        update_user_meta($user_id, 'indieauth_refresh_token', $token_data['refresh_token']);
    }
    
    if (isset($token_data['expires_in'])) {
        $expires_at = time() + intval($token_data['expires_in']);
        update_user_meta($user_id, 'indieauth_token_expires_at', $expires_at);
    }
    
    if (isset($token_data['scope'])) {
        update_user_meta($user_id, 'indieauth_scope', $token_data['scope']);
    }
    
    error_log("Peace Protocol: Successfully refreshed access token for user {$user_id}");
    return $token_data;
}

// Introspect IndieAuth access token
function peaceprotocol_introspect_indieauth_token($access_token, $metadata = null) {
    if (!$metadata) {
        // We need to discover metadata, but we don't have the target site
        // This would need to be called with metadata for now
        return false;
    }
    
    $introspection_endpoint = $metadata['introspection_endpoint'] ?? null;
    if (!$introspection_endpoint) {
        error_log("Peace Protocol: No introspection endpoint available");
        return false;
    }
    
    // Make introspection request
    $response = wp_remote_post($introspection_endpoint, array(
        'body' => array(
            'token' => $access_token
        ),
        'timeout' => 30
    ));
    
    if (is_wp_error($response)) {
        error_log('Peace Protocol: Token introspection failed: ' . $response->get_error_message());
        return false;
    }
    
    $body = wp_remote_retrieve_body($response);
    $introspection_data = json_decode($body, true);
    
    if (!$introspection_data) {
        error_log("Peace Protocol: Invalid introspection response");
        return false;
    }
    
    return $introspection_data;
}

// Fetch user info from IndieAuth userinfo endpoint
function peaceprotocol_fetch_indieauth_userinfo($access_token, $metadata = null) {
    if (!$metadata) {
        // We need to discover metadata, but we don't have the target site
        // This would need to be called with metadata for now
        return false;
    }
    
    $userinfo_endpoint = $metadata['userinfo_endpoint'] ?? null;
    if (!$userinfo_endpoint) {
        error_log("Peace Protocol: No userinfo endpoint available");
        return false;
    }
    
    // Make userinfo request
    $response = wp_remote_get($userinfo_endpoint, array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $access_token,
            'Accept' => 'application/json'
        ),
        'timeout' => 30
    ));
    
    if (is_wp_error($response)) {
        error_log('Peace Protocol: Userinfo request failed: ' . $response->get_error_message());
        return false;
    }
    
    $body = wp_remote_retrieve_body($response);
    $userinfo_data = json_decode($body, true);
    
    if (!$userinfo_data) {
        error_log("Peace Protocol: Invalid userinfo response");
        return false;
    }
    
    return $userinfo_data;
}

// AJAX handler for IndieAuth discovery (server-side to avoid CORS)
function peaceprotocol_discover_indieauth_handler() {
    error_log("Peace Protocol: IndieAuth discovery handler called");
    
    try {
        // Verify nonce for security
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'] ?? '')), 'peaceprotocol_indieauth')) {
            error_log("Peace Protocol: Invalid nonce in IndieAuth discovery request");
            wp_send_json_error('Invalid nonce');
            return;
        }
        
        // Simple test first
        if (!isset($_POST['url'])) {
            error_log("Peace Protocol: No URL in POST data");
            wp_send_json_error('No URL provided');
            return;
        }
        
        $url = sanitize_text_field($_POST['url']);
        error_log("Peace Protocol: IndieAuth discovery requested for URL: {$url}");
        
        if (!$url) {
            error_log("Peace Protocol: Empty URL provided for IndieAuth discovery");
            wp_send_json_error('No URL provided');
            return;
        }
        
        // Validate URL
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            error_log("Peace Protocol: Invalid URL format for IndieAuth discovery: {$url}");
            wp_send_json_error('Invalid URL format');
            return;
        }
        
        error_log("Peace Protocol: About to call discovery function for: {$url}");
        
        // Use our existing server-side discovery function
        $metadata = peaceprotocol_discover_indieauth_metadata($url);
        
        if ($metadata) {
            error_log("Peace Protocol: IndieAuth discovery successful for {$url}: " . json_encode($metadata));
            wp_send_json_success($metadata);
        } else {
            error_log("Peace Protocol: IndieAuth discovery failed for {$url}");
            wp_send_json_error('Failed to discover IndieAuth metadata');
        }
    } catch (Exception $e) {
        error_log("Peace Protocol: Exception in IndieAuth discovery handler: " . $e->getMessage());
        error_log("Peace Protocol: Exception trace: " . $e->getTraceAsString());
        wp_send_json_error('Internal server error: ' . $e->getMessage());
    } catch (Error $e) {
        error_log("Peace Protocol: Fatal error in IndieAuth discovery handler: " . $e->getMessage());
        error_log("Peace Protocol: Error trace: " . $e->getTraceAsString());
        wp_send_json_error('Internal server error: ' . $e->getMessage());
    }
}

// Register the IndieAuth discovery AJAX handler after the function is defined
add_action('wp_ajax_peaceprotocol_discover_indieauth', 'peaceprotocol_discover_indieauth_handler');
add_action('wp_ajax_nopriv_peaceprotocol_discover_indieauth', 'peaceprotocol_discover_indieauth_handler');

// AJAX handler for refreshing IndieAuth tokens
function peaceprotocol_refresh_indieauth_token_handler() {
    // Verify nonce for security
            if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'] ?? '')), 'peaceprotocol_indieauth')) {
        wp_send_json_error('Invalid nonce');
        return;
    }
    
    $user_id = intval($_POST['user_id'] ?? 0);
    
    if (!$user_id) {
        wp_send_json_error('Invalid user ID');
        return;
    }
    
    // Check if user exists and has IndieAuth data
    $user = get_user_by('ID', $user_id);
    if (!$user) {
        wp_send_json_error('User not found');
        return;
    }
    
    $auth_method = get_user_meta($user_id, 'peaceprotocol_auth_method', true);
    if ($auth_method !== 'indieauth') {
        wp_send_json_error('User not authenticated via IndieAuth');
        return;
    }
    
    // Attempt to refresh the token
            $result = peaceprotocol_refresh_indieauth_token($user_id);
    
    if ($result) {
        wp_send_json_success([
            'message' => 'Token refreshed successfully',
            'access_token' => $result['access_token'] ?? null,
            'expires_in' => $result['expires_in'] ?? null,
            'scope' => $result['scope'] ?? null
        ]);
    } else {
        wp_send_json_error('Failed to refresh token');
    }
}

// Register rewrite endpoints for IndieAuth
add_action('init', function() {
    add_rewrite_rule('^peace-indieauth-callback/?$', 'index.php?peace_indieauth_callback=1', 'top');
    add_rewrite_rule('^peace-indieauth-authorization/?$', 'index.php?peace_indieauth_authorization=1', 'top');
    add_rewrite_rule('^peace-indieauth-token/?$', 'index.php?peace_indieauth_token=1', 'top');
    add_rewrite_tag('%peace_indieauth_callback%', '1');
    add_rewrite_tag('%peace_indieauth_authorization%', '1');
    add_rewrite_tag('%peace_indieauth_token%', '1');
});

// Flush rewrite rules on plugin activation
register_activation_hook(__FILE__, function() {
    add_rewrite_rule('^peace-indieauth-callback/?$', 'index.php?peace_indieauth_callback=1', 'top');
    add_rewrite_rule('^peace-indieauth-authorization/?$', 'index.php?peace_indieauth_authorization=1', 'top');
    add_rewrite_rule('^peace-indieauth-token/?$', 'index.php?peace_indieauth_token=1', 'top');
    add_rewrite_tag('%peace_indieauth_callback%', '1');
    add_rewrite_tag('%peace_indieauth_authorization%', '1');
    add_rewrite_tag('%peace_indieauth_token%', '1');
    flush_rewrite_rules();
});

// Removed old IndieAuth callback handler from template_redirect hook - now handled in parse_request

// Handle IndieAuth callback and token exchange endpoints
add_action('parse_request', function($wp) {
    // Check if this is our IndieAuth callback endpoint
    if (isset($wp->request) && $wp->request === 'peace-indieauth-callback') {
        
        // Debug logging
        error_log('Peace Protocol: IndieAuth callback received');
        error_log('Peace Protocol: GET parameters: ' . print_r(array_map('esc_html', $_GET), true));
        error_log('Peace Protocol: Request URI: ' . esc_html($_SERVER['REQUEST_URI'] ?? ''));
        
        // Get callback parameters
        $code = sanitize_text_field(wp_unslash($_GET['code'] ?? ''));
        $state = sanitize_text_field(wp_unslash($_GET['state'] ?? ''));
        $error = sanitize_text_field(wp_unslash($_GET['error'] ?? ''));
        $iss = esc_url_raw(wp_unslash($_GET['iss'] ?? ''));
        
        if ($error) {
            error_log('Peace Protocol: IndieAuth callback error: ' . $error);
            wp_die('IndieAuth authorization failed: ' . esc_html($error), 'Authorization Failed', array('response' => 400));
        }
        
        if (!$code || !$state || !$iss) {
            error_log('Peace Protocol: Missing required callback parameters');
            wp_die('Missing required callback parameters', 'Invalid Request', array('response' => 400));
        }
        
        // Get the authorization server domain from iss parameter
        $auth_server_domain = wp_parse_url($iss, PHP_URL_HOST);
        if (!$auth_server_domain) {
            error_log('Peace Protocol: Invalid iss parameter: ' . $iss);
            wp_die('Invalid authorization server', 'Invalid Request', array('response' => 400));
        }
        
        error_log('Peace Protocol: Authorization server domain: ' . esc_html($auth_server_domain));
        
        // Create or get federated user based on the authorization server domain
        $user = peaceprotocol_create_or_get_indieauth_user($iss, array('me' => $iss));
        
        if (!$user) {
            error_log('Peace Protocol: Failed to create/get IndieAuth user');
            wp_die('Failed to create user account', 'User Creation Failed', array('response' => 500));
        }
        
        error_log('Peace Protocol: User created/found: ' . $user->ID);
        
        // Log in the user
        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID);
        
        error_log('Peace Protocol: User logged in successfully');
        
        // Generate a Peace Protocol authorization code
        $auth_code = peaceprotocol_generate_secure_token(32);
        $authorizations = get_option('peaceprotocol_authorizations', array());
        $authorizations[$auth_code] = array(
            'site_url' => $iss, // The authorization server (authenticating site)
            'user_id' => $user->ID,
            'expires' => time() + 300, // 5 minutes
            'used' => false
        );
        update_option('peaceprotocol_authorizations', $authorizations);
        
        error_log('Peace Protocol: Generated authorization code: ' . $auth_code);
        
        // Set session data to show the peace modal after login
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            session_start();
        }
        $_SESSION['peace_show_modal_after_login'] = true;
        $_SESSION['peace_federated_site'] = $iss;
        $_SESSION['peace_federated_token'] = 'indieauth_' . $user->ID; // Use a token identifier for IndieAuth users
        $_SESSION['peace_authorization_code'] = $auth_code;
        
        error_log('Peace Protocol: Session data stored for IndieAuth user');
        
        // Redirect to the homepage with the peace modal
        $redirect_url = add_query_arg(array(
            'peace_show_modal' => '1'
        ), home_url());
        
        error_log('Peace Protocol: Redirecting to homepage with peace modal: ' . $redirect_url);
        
        wp_redirect($redirect_url);
        exit;
    }
    
    // Check if this is our IndieAuth token exchange endpoint
    if (isset($wp->request) && $wp->request === 'peace-indieauth-token') {
        
        // Only handle POST requests for token exchange
        if (sanitize_text_field($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            exit('Method Not Allowed');
        }
        
        // Get POST parameters
        $grant_type = sanitize_text_field(wp_unslash($_POST['grant_type'] ?? ''));
        $code = sanitize_text_field(wp_unslash($_POST['code'] ?? ''));
        $client_id = esc_url_raw(wp_unslash($_POST['client_id'] ?? ''));
        $redirect_uri = esc_url_raw(wp_unslash($_POST['redirect_uri'] ?? ''));
        $code_verifier = sanitize_text_field(wp_unslash($_POST['code_verifier'] ?? ''));
        
        // Validate required parameters
        if ($grant_type !== 'authorization_code' || !$code || !$client_id || !$redirect_uri) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo wp_json_encode(['error' => 'invalid_request', 'error_description' => 'Missing required parameters']);
            exit;
        }
        
        // Get the authorization code
        $authorization_codes = get_option('peaceprotocol_indieauth_codes', array());
        if (!isset($authorization_codes[$code]) || $authorization_codes[$code]['used'] || $authorization_codes[$code]['expires'] < time()) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo wp_json_encode(['error' => 'invalid_grant', 'error_description' => 'Invalid or expired authorization code']);
            exit;
        }
        
        $auth_code_data = $authorization_codes[$code];
        
        // Validate client_id and redirect_uri match
        if ($auth_code_data['client_id'] !== $client_id || $auth_code_data['redirect_uri'] !== $redirect_uri) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo wp_json_encode(['error' => 'invalid_grant', 'error_description' => 'Client ID or redirect URI mismatch']);
            exit;
        }
        
        // Validate PKCE if present
        if ($auth_code_data['code_challenge']) {
            if (!$code_verifier) {
                http_response_code(400);
                header('Content-Type: application/json');
                echo wp_json_encode(['error' => 'invalid_request', 'error_description' => 'Code verifier required']);
                exit;
            }
            
            // Verify code challenge
            $expected_challenge = peaceprotocol_base64url_encode(hash('sha256', $code_verifier, true));
            if ($expected_challenge !== $auth_code_data['code_challenge']) {
                http_response_code(400);
                header('Content-Type: application/json');
                echo wp_json_encode(['error' => 'invalid_grant', 'error_description' => 'Invalid code verifier']);
                exit;
            }
        }
        
        // Mark code as used
        $authorization_codes[$code]['used'] = true;
        update_option('peaceprotocol_indieauth_codes', $authorization_codes);
        
        // Get user data
        $user = get_user_by('ID', $auth_code_data['user_id']);
        if (!$user) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo wp_json_encode(['error' => 'server_error', 'error_description' => 'User not found']);
            exit;
        }
        
        // Generate access token
        $access_token = peaceprotocol_generate_secure_token(32);
        $expires_in = 3600; // 1 hour
        
        // Store access token
        $access_tokens = get_option('peaceprotocol_indieauth_access_tokens', array());
        $access_tokens[$access_token] = array(
            'user_id' => $user->ID,
            'client_id' => $client_id,
            'scope' => $auth_code_data['scope'],
            'expires' => time() + $expires_in,
            'created' => time()
        );
        update_option('peaceprotocol_indieauth_access_tokens', $access_tokens);
        
        // Return token response
        header('Content-Type: application/json');
        $response = array(
            'access_token' => $access_token,
            'token_type' => 'Bearer',
            'scope' => $auth_code_data['scope'],
            'me' => $auth_code_data['me'] ?: home_url()
        );
        
        if ($expires_in) {
            $response['expires_in'] = $expires_in;
        }
        
        echo wp_json_encode($response);
        exit;
    }
});

// Helper function to get the correct admin-ajax.php URL for a given site
function peaceprotocol_get_admin_ajax_url($site_url = null) {
    if (!$site_url) {
        $site_url = get_site_url();
    }
    
    // Use WordPress functions to get the correct admin URL
    $admin_url = admin_url('admin-ajax.php');
    
    // If we're generating for a different site, we need to construct it manually
    // but we can still use the relative path from admin_url()
    if ($site_url !== get_site_url()) {
        // Extract the path from admin_url() and append to the target site
        $admin_path = wp_parse_url($admin_url, PHP_URL_PATH);
        return rtrim($site_url, '/') . $admin_path;
    }
    
    return $admin_url;
}

// Helper function for pages outside WordPress environment
function peaceprotocol_get_admin_ajax_url_external($site_url) {
    // For external pages, we need to construct the URL manually
    // Most WordPress installations use the standard path, but we can be more robust
    $site_url = rtrim($site_url, '/');
    
    // Try to detect if this is a subdirectory installation
    $parsed = wp_parse_url($site_url);
    $path = isset($parsed['path']) ? $parsed['path'] : '';
    
    // If the site URL has a path (subdirectory), append admin-ajax.php to that path
    if ($path && $path !== '/') {
        return $site_url . '/wp-admin/admin-ajax.php';
    }
    
    // Standard installation
    return $site_url . '/wp-admin/admin-ajax.php';
}










