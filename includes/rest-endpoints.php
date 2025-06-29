<?php
defined('ABSPATH') || exit;

function peace_protocol_receive_peace($request) {
    // error_log('Peace Protocol REST: receive_peace called');
    // error_log('Peace Protocol REST: Request parameters: ' . print_r($request->get_params(), true));
    
    $target_site = sanitize_url($request->get_param('target_site'));
    $message = sanitize_textarea_field($request->get_param('message'));
    $token = sanitize_text_field($request->get_param('token'));
    
    // error_log('Peace Protocol REST: Target site: ' . $target_site);
    // error_log('Peace Protocol REST: Token: ' . $token);
    
    // Validate token
    $identity = peace_protocol_validate_token($token);
    // error_log('Peace Protocol REST: Token validation result: ' . print_r($identity, true));
    
    if (!$identity) {
        // error_log('Peace Protocol REST: Token validation failed');
        return new WP_Error('invalid_token', 'Invalid token', array('status' => 403));
    }
    
    // Check if the sending site's user is banned (for federated users)
    if (function_exists('peace_protocol_is_user_banned') && peace_protocol_is_user_banned()) {
        // error_log('Peace Protocol REST: Banned user attempted to send peace');
        return new WP_Error('user_banned', 'You are banned from sending peace', array('status' => 403));
    }
    
    // error_log('Peace Protocol REST: Identity validated: ' . print_r($identity, true));
    
    // Save Peace Log directly (don't call send_peace_to_site which would create infinite loop)
    $log_id = wp_insert_post([
        'post_type' => 'peace_log',
        'post_title' => 'Peace from ' . $identity['site_url'],
        'post_content' => $message,
        'post_status' => 'publish',
        'meta_input' => ['from_site' => $identity['site_url'], 'note' => $message]
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
        'callback' => 'peace_protocol_receive_peace',
            'permission_callback' => function() {
                // Allow all requests for federated peace protocol
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

// AJAX fallback for receiving peace (when REST API is disabled)
add_action('wp_ajax_peace_protocol_receive_peace', function() {
    // error_log('Peace Protocol: AJAX receive_peace handler called (logged in)');
    
    // Check if current user is banned
    if (function_exists('peace_protocol_is_user_banned') && peace_protocol_is_user_banned()) {
        // error_log('Peace Protocol: Banned user attempted to receive peace via AJAX');
        wp_send_json_error('You are banned from receiving peace', 403);
    }
    
    if (!isset($_POST['from_site']) || !isset($_POST['token']) || !isset($_POST['note'])) {
        // error_log('Peace Protocol: AJAX missing required fields');
        wp_send_json_error('Missing required fields');
    }
    
    $from = sanitize_text_field(wp_unslash($_POST['from_site']));
    $token = trim(wp_unslash($_POST['token']));
    // Decode HTML entities in case WordPress is encoding them
    $token = html_entity_decode($token, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $note = sanitize_text_field(wp_unslash($_POST['note']));
    
    // error_log('Peace Protocol: AJAX from_site=' . $from . ', token=' . substr($token, 0, 8) . '..., note=' . $note);

    // Check if token is valid for the from_site
    // First check current site's tokens
    $tokens = get_option('peace_tokens', []);
    // error_log('Peace Protocol: AJAX peace_tokens: ' . print_r($tokens, true));
    $token_valid = in_array($token, $tokens, true);
    
    // If not valid in current site, check if it's a federated token
    if (!$token_valid) {
        // Check if this token was issued via federated exchange
        $codes = get_option('peace_federated_codes', []);
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
        'post_type' => 'peace_log',
        'post_title' => 'Peace from ' . $from,
        'post_content' => $note,
        'post_status' => 'publish',
        'meta_input' => ['from_site' => $from, 'note' => $note]
    ]);

    // error_log('Peace Protocol: AJAX peace log created with ID: ' . $log_id);
    wp_send_json_success(['log_id' => $log_id]);
});

add_action('wp_ajax_nopriv_peace_protocol_receive_peace', function() {
    // error_log('Peace Protocol: AJAX receive_peace handler called (not logged in)');
    if (!isset($_POST['from_site']) || !isset($_POST['token']) || !isset($_POST['note'])) {
        // error_log('Peace Protocol: AJAX missing required fields (nopriv)');
        wp_send_json_error('Missing required fields');
    }
    
    $from = sanitize_text_field(wp_unslash($_POST['from_site']));
    $token = trim(wp_unslash($_POST['token']));
    // Decode HTML entities in case WordPress is encoding them
    $token = html_entity_decode($token, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $note = sanitize_text_field(wp_unslash($_POST['note']));
    
    // error_log('Peace Protocol: AJAX nopriv from_site=' . $from . ', token=' . substr($token, 0, 8) . '..., note=' . $note);

    // Check if token is valid for the from_site
    // First check current site's tokens
    $tokens = get_option('peace_tokens', []);
    $token_valid = in_array($token, $tokens, true);
    
    // If not valid in current site, check if it's a federated token
    if (!$token_valid) {
        // Check if this token was issued via federated exchange
        $codes = get_option('peace_federated_codes', []);
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
        'post_type' => 'peace_log',
        'post_title' => 'Peace from ' . $from,
        'post_content' => $note,
        'post_status' => 'publish',
        'meta_input' => ['from_site' => $from, 'note' => $note]
    ]);

    // error_log('Peace Protocol: AJAX nopriv peace log created with ID: ' . $log_id);
    wp_send_json_success(['log_id' => $log_id]);
});

// AJAX fallback for federated auth (when REST API is disabled)
add_action('wp_ajax_peace_protocol_federated_auth', function() {
    if (!isset($_POST['token']) || !isset($_POST['remote_site']) || !isset($_POST['state'])) {
        wp_send_json_error('Missing required fields');
    }
    
    $token = sanitize_text_field(wp_unslash($_POST['token']));
    $remote_site = esc_url_raw(wp_unslash($_POST['remote_site']));
    $state = sanitize_text_field(wp_unslash($_POST['state']));
    
    $tokens = get_option('peace_tokens', []);
    if (!in_array($token, $tokens, true)) {
        wp_send_json_error('Invalid token', 403);
    }
    
    $active_token = is_array($tokens) && count($tokens) ? $tokens[0] : '';
    $code = wp_generate_password(20, false, false);
    $expires = time() + 300; // 5 minutes
    $codes = get_option('peace_protocol_codes', array());
    $codes[$code] = [
        'site_url' => get_site_url(),
        'expires' => $expires,
    ];
    update_option('peace_protocol_codes', $codes);
    wp_send_json_success(['code' => $code]);
});

add_action('wp_ajax_nopriv_peace_protocol_federated_auth', function() {
    if (!isset($_POST['token']) || !isset($_POST['remote_site']) || !isset($_POST['state'])) {
        wp_send_json_error('Missing required fields');
    }
    
    $token = sanitize_text_field(wp_unslash($_POST['token']));
    $remote_site = esc_url_raw(wp_unslash($_POST['remote_site']));
    $state = sanitize_text_field(wp_unslash($_POST['state']));
    
    $tokens = get_option('peace_tokens', []);
    if (!in_array($token, $tokens, true)) {
        wp_send_json_error('Invalid token', 403);
    }
    
    $active_token = is_array($tokens) && count($tokens) ? $tokens[0] : '';
    $code = wp_generate_password(20, false, false);
    $expires = time() + 300; // 5 minutes
    $codes = get_option('peace_protocol_codes', array());
    $codes[$code] = [
        'site_url' => get_site_url(),
        'expires' => $expires,
    ];
    update_option('peace_protocol_codes', $codes);
    wp_send_json_success(['code' => $code]);
});

// AJAX fallback for federated exchange (when REST API is disabled)
add_action('wp_ajax_peace_protocol_federated_exchange', 'peace_protocol_ajax_federated_exchange');
add_action('wp_ajax_nopriv_peace_protocol_federated_exchange', 'peace_protocol_ajax_federated_exchange');

function peace_protocol_ajax_federated_exchange() {
    // error_log('Peace Protocol AJAX: federated_exchange called');
    // error_log('Peace Protocol AJAX: POST data: ' . print_r($_POST, true));
    
    if (!isset($_POST['code']) || !isset($_POST['site'])) {
        wp_send_json_error('Missing required fields');
    }
    
    $code = sanitize_text_field(wp_unslash($_POST['code']));
    $site = esc_url_raw(wp_unslash($_POST['site']));
    
    // error_log('Peace Protocol AJAX: Code: ' . $code . ', Site: ' . $site);
    
    // Use the new option names
    $codes = get_option('peace_protocol_codes', array());
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
        update_option('peace_protocol_codes', $codes);
        wp_die('Code expired', 403);
    }
    
    // Generate new token for this site
    $token = wp_generate_password(64, false);
    $expires = time() + 86400; // 24 hours
    
    // Store in federated identities so this site knows about the token
    $federated_identities = get_option('peace_protocol_federated_identities', array());
    $federated_identities[] = array(
        'site_url' => $code_data['site_url'],
        'token' => $token,
        'expires' => $expires
    );
    update_option('peace_protocol_federated_identities', $federated_identities);
    
    // Remove used code
    unset($codes[$code]);
    update_option('peace_protocol_codes', $codes);
    
    // error_log('Peace Protocol AJAX: Exchanged code for token: ' . $token . ' for site: ' . $code_data['site_url']);
    // error_log('Peace Protocol AJAX: Federated identities after exchange: ' . print_r($federated_identities, true));
    
    wp_die(json_encode(array('success' => true, 'token' => $token)), 200);
}

// Federated login endpoints
add_action('template_redirect', function () {
    // Handle ?peace_get_token=1&return_site=...&state=... (normal flow)
    if (isset($_GET['peace_get_token']) && $_GET['peace_get_token'] == '1' && isset($_GET['return_site']) && isset($_GET['state'])) {
        $return_site = esc_url_raw(wp_unslash($_GET['return_site']));
        $state = sanitize_text_field(wp_unslash($_GET['state']));
        
        // error_log('Peace Protocol: peace_get_token called with return_site: ' . $return_site . ', state: ' . $state);
        
        // Check if user is admin and has tokens
        if (current_user_can('manage_options') && get_option('peace_tokens')) {
            $tokens = get_option('peace_tokens', []);
            if (empty($tokens)) {
                // error_log('Peace Protocol: No tokens available');
                return;
            }
            
            // Generate authorization code
            $auth_code = wp_generate_password(32, false, false);
            $expires = time() + 300; // 5 minutes
            
            // Store authorization code
            $authorizations = get_option('peace_protocol_authorizations', array());
            $authorizations[$auth_code] = array(
                'site_url' => get_site_url(),
                'return_site' => $return_site,
                'expires' => $expires,
                'used' => false
            );
            update_option('peace_protocol_authorizations', $authorizations);
            
            // error_log('Peace Protocol: Generated authorization code: ' . $auth_code . ' for return site: ' . $return_site);
            
            // Subscribe this site (siteA) to the return site's (siteB) feed
            // error_log('Peace Protocol: Subscribing ' . get_site_url() . ' to ' . $return_site . ' feed');
            peace_protocol_subscribe_to_feed($return_site);
            
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
                xhr.open('POST', siteUrl + '/wp-admin/admin-ajax.php', true);
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
                                authXhr.open('POST', siteUrl + '/wp-admin/admin-ajax.php', true);
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
                                                subscribeXhr.open('POST', siteUrl + '/wp-admin/admin-ajax.php', true);
                                                subscribeXhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                                                subscribeXhr.send('action=peace_protocol_subscribe_feed&feed_url=' + encodeURIComponent(<?php echo json_encode($return_site); ?>) + '&nonce=' + encodeURIComponent(<?php echo json_encode(wp_create_nonce('peace_protocol_subscribe_feed')); ?>));
                                                
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
                                authXhr.send('action=peace_protocol_complete_auth&return_site=' + encodeURIComponent(<?php echo json_encode($return_site); ?>) + '&state=' + encodeURIComponent(<?php echo json_encode($state); ?>) + '&token=' + encodeURIComponent(token));
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
                xhr.send('action=peace_protocol_validate_token&token=' + encodeURIComponent(token));
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
                xhr.open('POST', '<?php echo esc_url(admin_url('admin-ajax.php')); ?>', true);
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
                xhr.send('action=peace_protocol_complete_auth&return_site=' + encodeURIComponent(returnSite) + '&state=' + encodeURIComponent(state));
            });
            </script>
            </body></html><?php
            exit;
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
            $tokens = get_option('peace_tokens', []);
            if (!in_array($token, $tokens, true)) {
                return new WP_Error('invalid_token', 'Invalid token', ['status' => 403]);
            }
            $active_token = is_array($tokens) && count($tokens) ? $tokens[0] : '';
            $code = wp_generate_password(20, false, false);
            $expires = time() + 300; // 5 minutes
            $codes = get_option('peace_protocol_codes', array());
            $codes[$code] = [
                'site_url' => get_site_url(),
                'expires' => $expires,
            ];
            update_option('peace_protocol_codes', $codes);
            return ['success' => true, 'code' => $code];
        },
        'permission_callback' => '__return_true',
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
            $authorizations = get_option('peace_protocol_authorizations', array());
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
                update_option('peace_protocol_authorizations', $authorizations);
                return new WP_Error('invalid_code', 'Authorization code expired or already used', ['status' => 403]);
            }
            
            // Mark authorization as used
            $authorizations[$code]['used'] = true;
            update_option('peace_protocol_authorizations', $authorizations);
            
            // Generate a token for the requesting site
            $token = wp_generate_password(32, false, false);
            
            // error_log('Peace Protocol REST: Generated token: ' . $token . ' for site: ' . $site);
            
            return new WP_REST_Response(['success' => true, 'token' => $token], 200);
        },
        'permission_callback' => function() {
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
            $tokens = get_option('peace_tokens', array());
            $active_token = is_array($tokens) && count($tokens) ? $tokens[0] : '';
            
            if (!$active_token) {
                // error_log('Peace Protocol REST: No active token available');
                return new WP_Error('no_token', 'No active token available', array('status' => 500));
            }
            
            $identity = array(
                'site_url' => get_site_url(),
                'token' => $active_token
            );
            
            // error_log('Peace Protocol REST: Sending peace to target site: ' . $target_site);
            // error_log('Peace Protocol REST: Using identity: ' . print_r($identity, true));
            
            $result = peace_protocol_send_peace_to_site($target_site, $message, $identity);
            // error_log('Peace Protocol REST: Send result: ' . print_r($result, true));
            
            if (is_wp_error($result)) {
                // error_log('Peace Protocol REST: Send error: ' . $result->get_error_message());
                return $result;
            }
            
            // error_log('Peace Protocol REST: Peace sent successfully');
            return new WP_REST_Response(array('message' => 'Peace sent successfully'), 200);
        },
        'permission_callback' => function() {
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
            $authorizations = get_option('peace_protocol_authorizations', array());
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
                update_option('peace_protocol_authorizations', $authorizations);
                return new WP_Error('invalid_authorization', 'Authorization code expired or already used', array('status' => 403));
            }
            
            // Mark authorization as used
            $authorizations[$authorization_code]['used'] = true;
            update_option('peace_protocol_authorizations', $authorizations);
            
            // error_log('Peace Protocol REST: Authorization code validated successfully');
            return new WP_REST_Response(array(
                'valid' => true,
                'site_url' => $auth_data['site_url']
            ), 200);
        },
        'permission_callback' => function() {
            return true;
        },
    ]);
});

// AJAX handlers for when REST API is disabled
add_action('wp_ajax_peace_protocol_send_peace', 'peace_protocol_ajax_send_peace');
add_action('wp_ajax_nopriv_peace_protocol_send_peace', 'peace_protocol_ajax_send_peace');

function peace_protocol_ajax_send_peace() {
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
    $tokens = get_option('peace_tokens', array());
    $active_token = is_array($tokens) && count($tokens) ? $tokens[0] : '';
    
    if (!$active_token) {
        // error_log('Peace Protocol AJAX: No active token available');
        wp_die('No active token available', 500);
    }
    
    $identity = array(
        'site_url' => get_site_url(),
        'token' => $active_token
    );
    
    // error_log('Peace Protocol AJAX: Sending peace to target site: ' . $target_site);
    // error_log('Peace Protocol AJAX: Using identity: ' . print_r($identity, true));
    
    $result = peace_protocol_send_peace_to_site($target_site, $message, $identity);
    // error_log('Peace Protocol AJAX: Send result: ' . print_r($result, true));
    
    if (is_wp_error($result)) {
        // error_log('Peace Protocol AJAX: Send error: ' . $result->get_error_message());
        wp_die(esc_html($result->get_error_message()), 400);
    }
    
    wp_die('Peace sent successfully', 200);
}

add_action('wp_ajax_peace_protocol_generate_code', 'peace_protocol_ajax_generate_code');
add_action('wp_ajax_nopriv_peace_protocol_generate_code', 'peace_protocol_ajax_generate_code');

function peace_protocol_ajax_generate_code() {
    // error_log('Peace Protocol AJAX: generate_code called');
    // error_log('Peace Protocol AJAX: POST data: ' . print_r($_POST, true));
    
    if (!isset($_POST['token'])) {
        wp_send_json_error('Missing token');
    }
    
    $token = sanitize_text_field(wp_unslash($_POST['token']));
    // error_log('Peace Protocol AJAX: Token for code generation: ' . $token);
    
    // Validate token
    $identity = peace_protocol_validate_token($token);
    // error_log('Peace Protocol AJAX: Token validation for code: ' . print_r($identity, true));
    
    if (!$identity) {
        // error_log('Peace Protocol AJAX: Token validation failed for code generation');
        wp_die('Invalid token', 403);
    }
    
    // Generate one-time code
    $code = wp_generate_password(32, false);
    $expires = time() + 300; // 5 minutes
    
    $codes = get_option('peace_protocol_codes', array());
    $codes[$code] = array(
        'site_url' => $identity['site_url'],
        'expires' => $expires
    );
    update_option('peace_protocol_codes', $codes);
    
    // error_log('Peace Protocol AJAX: Generated code: ' . $code . ' for site: ' . $identity['site_url']);
    
    wp_die(json_encode(array('code' => $code)), 200);
}

add_action('wp_ajax_peace_protocol_exchange_code', 'peace_protocol_ajax_exchange_code');
add_action('wp_ajax_nopriv_peace_protocol_exchange_code', 'peace_protocol_ajax_exchange_code');

function peace_protocol_ajax_exchange_code() {
    // error_log('Peace Protocol AJAX: exchange_code called');
    // error_log('Peace Protocol AJAX: POST data: ' . print_r($_POST, true));
    
    if (!isset($_POST['code'])) {
        wp_send_json_error('Missing code');
    }
    
    $code = sanitize_text_field(wp_unslash($_POST['code']));
    // error_log('Peace Protocol AJAX: Code to exchange: ' . $code);
    
    // Validate code
    $codes = get_option('peace_protocol_codes', array());
    // error_log('Peace Protocol AJAX: Available codes: ' . print_r($codes, true));
    
    if (!isset($codes[$code])) {
        // error_log('Peace Protocol AJAX: Code not found');
        wp_die('Invalid code', 400);
    }
    
    $code_data = $codes[$code];
    if ($code_data['expires'] < time()) {
        // error_log('Peace Protocol AJAX: Code expired');
        unset($codes[$code]);
        update_option('peace_protocol_codes', $codes);
        wp_die('Code expired', 400);
    }
    
    // Generate new token
    $token = wp_generate_password(64, false);
    $expires = time() + 86400; // 24 hours
    
    // Store in federated identities so this site knows about the token
    $federated_identities = get_option('peace_protocol_federated_identities', array());
    $federated_identities[] = array(
        'site_url' => $code_data['site_url'],
        'token' => $token,
        'expires' => $expires
    );
    update_option('peace_protocol_federated_identities', $federated_identities);
    
    // Remove used code
    unset($codes[$code]);
    update_option('peace_protocol_codes', $codes);
    
    // error_log('Peace Protocol AJAX: Exchanged code for token: ' . $token . ' for site: ' . $code_data['site_url']);
    // error_log('Peace Protocol AJAX: Federated identities after exchange: ' . print_r($federated_identities, true));
    
    wp_die(json_encode(array('token' => $token)), 200);
}

function peace_protocol_validate_token($token) {
    // error_log('Peace Protocol: validate_token called with token: ' . $token);
    // error_log('Peace Protocol: Current site URL: ' . get_site_url());
    
    // Check current site tokens (peace_tokens is a simple array of token strings)
    $tokens = get_option('peace_tokens', array());
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
    $federated_identities = get_option('peace_protocol_federated_identities', array());
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
                update_option('peace_protocol_federated_identities', $federated_identities);
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

add_action('wp_ajax_peace_protocol_validate_token', 'peace_protocol_ajax_validate_token');
add_action('wp_ajax_nopriv_peace_protocol_validate_token', 'peace_protocol_ajax_validate_token');

function peace_protocol_ajax_validate_token() {
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
    
    $token = trim(wp_unslash($_POST['token']));
    // Decode HTML entities in case WordPress is encoding them
    $token = html_entity_decode($token, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    // error_log('Peace Protocol AJAX: Validating token: ' . $token);
    
    $identity = peace_protocol_validate_token($token);
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
add_action('wp_ajax_peace_protocol_federated_login', 'peace_protocol_ajax_federated_login');
add_action('wp_ajax_nopriv_peace_protocol_federated_login', 'peace_protocol_ajax_federated_login');

function peace_protocol_ajax_federated_login() {
    // error_log('Peace Protocol AJAX: federated_login called');
    // error_log('Peace Protocol AJAX: POST data: ' . print_r($_POST, true));
    
    if (!isset($_POST['auth_code']) || !isset($_POST['federated_site']) || !isset($_POST['state']) || !isset($_POST['nonce'])) {
        wp_send_json_error('Missing required fields');
    }
    
    if (!wp_verify_nonce($_POST['nonce'], 'peace_protocol_federated_login')) {
        wp_send_json_error('Invalid nonce');
    }
    
    $auth_code = sanitize_text_field(wp_unslash($_POST['auth_code']));
    $federated_site = sanitize_url(wp_unslash($_POST['federated_site']));
    $state = sanitize_text_field(wp_unslash($_POST['state']));
    
    // error_log('Peace Protocol AJAX: Processing federated login - code: ' . $auth_code . ', site: ' . $federated_site . ', state: ' . $state);
    
    // Exchange the authorization code for a token from the federated site
    $token = peace_protocol_exchange_auth_code_for_token($auth_code, $federated_site);
    
    if ($token) {
        // error_log('Peace Protocol: Successfully exchanged auth code for token');
        
        // Create or get federated user using the federated site URL
        $user = peace_protocol_create_or_get_federated_user($federated_site, $token);
        if ($user) {
            // error_log('Peace Protocol: Federated login successful for site: ' . $federated_site . ', user: ' . $user->user_login);
            
            // Set a session flag to show the peace modal
            if (!session_id()) {
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

add_action('wp_ajax_peace_protocol_debug_log', 'peace_protocol_ajax_debug_log');
add_action('wp_ajax_nopriv_peace_protocol_debug_log', 'peace_protocol_ajax_debug_log');

function peace_protocol_ajax_debug_log() {
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
function peace_protocol_cleanup_expired_authorizations() {
    $authorizations = get_option('peace_protocol_authorizations', array());
    $cleaned = false;
    
    foreach ($authorizations as $code => $auth_data) {
        if ($auth_data['used'] || $auth_data['expires'] < time()) {
            unset($authorizations[$code]);
            $cleaned = true;
        }
    }
    
    if ($cleaned) {
        update_option('peace_protocol_authorizations', $authorizations);
        // error_log('Peace Protocol: Cleaned up expired authorizations');
    }
}

// Clean up on plugin load
add_action('init', 'peace_protocol_cleanup_expired_authorizations');

// Function to send peace to a target site
function peace_protocol_send_peace_to_site($target_site, $message, $identity) {
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
            'token' => $identity['token']
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
    $ajax_url = $target_site . '/wp-admin/admin-ajax.php';
    $ajax_response = wp_remote_post($ajax_url, array(
        'headers' => array('Content-Type' => 'application/x-www-form-urlencoded'),
        'body' => http_build_query(array(
            'action' => 'peace_protocol_receive_peace',
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
function peace_protocol_subscribe_to_feed($feed_url) {
    // error_log('Peace Protocol: Subscribing to feed: ' . $feed_url);
    
    // Get current subscriptions - use the option that the admin page reads from
    $subscriptions = get_option('peace_feeds', array());
    
    // Check if already subscribed
    if (in_array($feed_url, $subscriptions)) {
        // error_log('Peace Protocol: Already subscribed to feed: ' . $feed_url);
        return true;
    }
    
    // Add to subscriptions
    $subscriptions[] = $feed_url;
    update_option('peace_feeds', $subscriptions);
    
    // error_log('Peace Protocol: Successfully subscribed to feed: ' . $feed_url);
    // error_log('Peace Protocol: All subscriptions after adding: ' . print_r($subscriptions, true));
    return true;
}

// Test AJAX handler to verify AJAX is working
add_action('wp_ajax_peace_protocol_test', 'peace_protocol_ajax_test');
add_action('wp_ajax_nopriv_peace_protocol_test', 'peace_protocol_ajax_test');

function peace_protocol_ajax_test() {
    if (ob_get_level()) {
        ob_clean();
    }
    // error_log('Peace Protocol: Test AJAX handler called');
    wp_send_json_success('AJAX is working');
}

// AJAX handler for completing authorization (both logged-in and non-logged-in)
add_action('wp_ajax_peace_protocol_complete_auth', 'peace_protocol_ajax_complete_auth');
add_action('wp_ajax_nopriv_peace_protocol_complete_auth', 'peace_protocol_ajax_complete_auth');

function peace_protocol_ajax_complete_auth() {
    // Prevent any output before our response
    if (ob_get_level()) {
        ob_clean();
    }
    
    // Enable error reporting for debugging
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    
    // error_log('Peace Protocol: peace_protocol_complete_auth called');
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
            $token = trim(wp_unslash($_POST['token']));
            // Decode HTML entities in case WordPress is encoding them
            $token = html_entity_decode($token, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $identity = peace_protocol_validate_token($token);
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
        $auth_code = wp_generate_password(32, false, false);
        $expires = time() + 300; // 5 minutes
        
        // error_log('Peace Protocol: Generated auth code: ' . $auth_code . ' (length: ' . strlen($auth_code) . ')');
        
        // Store authorization code
        $authorizations = get_option('peace_protocol_authorizations', array());
        // error_log('Peace Protocol: Current authorizations before adding: ' . print_r($authorizations, true));
        
        $authorizations[$auth_code] = array(
            'site_url' => get_site_url(),
            'return_site' => $return_site,
            'expires' => $expires,
            'used' => false
        );
        
        $update_result = update_option('peace_protocol_authorizations', $authorizations);
        // error_log('Peace Protocol: Authorization code storage result: ' . ($update_result ? 'success' : 'failed'));
        // error_log('Peace Protocol: Authorizations after adding: ' . print_r($authorizations, true));
        
        // Subscribe to the return site's feed
        $subscribe_result = peace_protocol_subscribe_to_feed($return_site);
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

// Handle federated authorization code return on page load
add_action('template_redirect', function() {
    // error_log('Peace Protocol: template_redirect hook called');
    // error_log('Peace Protocol: Current URL: ' . $_SERVER['REQUEST_URI']);
    // error_log('Peace Protocol: Full URL: ' . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
    // error_log('Peace Protocol: GET parameters: ' . print_r($_GET, true));
    // error_log('Peace Protocol: QUERY_STRING: ' . $_SERVER['QUERY_STRING']);
    // error_log('Peace Protocol: HTTP_REFERER: ' . (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'none'));
    
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

        // Exchange the authorization code for a token from the federated site
        $token = peace_protocol_exchange_auth_code_for_token($auth_code, $federated_site);
        
        if ($token) {
            // error_log('Peace Protocol: Successfully exchanged auth code for token');
            
            // Create or get federated user using the federated site URL
            $user = peace_protocol_create_or_get_federated_user($federated_site, $token);
            if ($user) {
                // error_log('Peace Protocol: Federated login successful for site: ' . $federated_site . ', user: ' . $user->user_login);
                
                // Set a session flag to show the peace modal
                if (!session_id()) {
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
    } else {
        // error_log('Peace Protocol: No authorization code parameters found in URL');
    }
});

// Check for session flag to show peace modal after federated login
add_action('wp_footer', function() {
    if (!session_id()) {
        session_start();
    }
    
    if (isset($_SESSION['peace_show_modal_after_login']) && $_SESSION['peace_show_modal_after_login']) {
        // Get the session data before clearing it
        $federated_site = $_SESSION['peace_federated_site'] ?? '';
        $federated_token = $_SESSION['peace_federated_token'] ?? '';
        $auth_code = $_SESSION['peace_authorization_code'] ?? '';
        
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
function peace_protocol_exchange_auth_code_for_token($auth_code, $federated_site) {
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
function peace_protocol_create_or_get_federated_user($federated_site, $token) {
    // error_log('Peace Protocol: create_or_get_federated_user called for site: ' . $federated_site . ', token: ' . substr($token, 0, 8) . '...');
    
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
    $parsed_url = parse_url($federated_site);
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
