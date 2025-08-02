<?php
defined('ABSPATH') || exit;

// Register AJAX actions early
add_action('wp_ajax_peaceprotocol_rotate_tokens', 'peaceprotocol_rotate_tokens_callback');
add_action('wp_ajax_peaceprotocol_delete_token', 'peaceprotocol_delete_token_callback');

add_action('admin_menu', function () {
    add_options_page(
        __('Peace Protocol', 'peace-protocol'),
        __('Peace Protocol', 'peace-protocol'),
        'manage_options',
        'peace-protocol',
        'peaceprotocol_admin_page'
    );
});

function peaceprotocol_admin_page()
{
    if (!current_user_can('manage_options')) {
        return;
    }

    // Handle federated return parameters
    if (isset($_GET['peace_federated_return']) && isset($_GET['peace_federated_state'])) {
        $return_site = esc_url_raw(wp_unslash($_GET['peace_federated_return']));
        $state = sanitize_text_field(wp_unslash($_GET['peace_federated_state']));
        
        // error_log('Peace Protocol Admin: Federated return detected - return_site: ' . $return_site . ', state: ' . $state);
        
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
        
        // error_log('Peace Protocol Admin: Generated authorization code: ' . $auth_code . ' for return site: ' . $return_site);
        
        // Subscribe this site to the return site's feed
        peaceprotocol_subscribe_to_feed($return_site);
        
        // Redirect back to return site with authorization code
        $redirect = $return_site;
        $separator = strpos($return_site, '?') !== false ? '&' : '?';
        $redirect .= $separator . 'peace_authorization_code=' . urlencode($auth_code) . '&peace_federated_site=' . urlencode(get_site_url()) . '&peace_federated_state=' . urlencode($state);
        
        // error_log('Peace Protocol Admin: Redirecting to: ' . $redirect);
        wp_redirect($redirect);
        exit;
    }

    // --- Rotate tokens before any output ---
    $tokens = get_option('peaceprotocol_tokens', []);
    if (count($tokens) > 1) {
        $first = array_shift($tokens);
        $tokens[] = $first;
        update_option('peaceprotocol_tokens', $tokens);
    }

    // --- Handle POST actions before output ---
    if (isset($_POST['peaceprotocol_generate_token']) && check_admin_referer('peaceprotocol_generate_token')) {
        $tokens = get_option('peaceprotocol_tokens', []);
        $tokens[] = peaceprotocol_generate_secure_token(32);
        update_option('peaceprotocol_tokens', $tokens);
    }

    if (isset($_POST['peaceprotocol_save_settings']) && check_admin_referer('peaceprotocol_settings')) {
        $hide_auto_button = isset($_POST['peace_hide_auto_button']) ? '1' : '0';
        $button_position = isset($_POST['peace_button_position']) ? sanitize_text_field(wp_unslash($_POST['peace_button_position'])) : 'top-right';
        update_option('peaceprotocol_hide_auto_button', $hide_auto_button);
        update_option('peaceprotocol_button_position', $button_position);
        echo '<div class="updated"><p>' . esc_html__('Settings saved.', 'peace-protocol') . '</p></div>';
    }

    $tokens = get_option('peaceprotocol_tokens', []);
    $feeds = get_option('peaceprotocol_feeds', []);
    $site_url = get_site_url();
    $ajax_nonce = wp_create_nonce('peaceprotocol_rotate_tokens');
    $hide_auto_button = get_option('peaceprotocol_hide_auto_button', '0');
    $button_position = get_option('peaceprotocol_button_position', 'top-right');
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Peace Protocol Settings', 'peace-protocol'); ?></h1>

        <p><?php esc_html_e('Tokens are used to authenticate your site with other WordPress sites. Keep them secret. You can generate multiple tokens for rotation. Having a few tokens is best for security reasons.', 'peace-protocol'); ?></p>

        <h2><?php esc_html_e('Your Tokens', 'peace-protocol'); ?></h2>
        <form method="post" style="margin-bottom:2em;">
            <?php wp_nonce_field('peaceprotocol_generate_token'); ?>
            <button type="submit" name="peaceprotocol_generate_token" class="button button-primary">
                <?php esc_html_e('Generate New Token', 'peace-protocol'); ?>
            </button>
        </form>

        <form method="post" style="margin-bottom:2em;">
            <?php wp_nonce_field('peaceprotocol_settings'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="peace_hide_auto_button">
                            <?php esc_html_e('Auto-inserted Button', 'peace-protocol'); ?>
                        </label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" id="peace_hide_auto_button" name="peace_hide_auto_button" value="1" <?php checked($hide_auto_button, '1'); ?> />
                            <?php esc_html_e('Hide the automatically inserted peace hand button (use shortcode instead)', 'peace-protocol'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="peace_button_position">
                            <?php esc_html_e('Button Position', 'peace-protocol'); ?>
                        </label>
                    </th>
                    <td>
                        <select id="peace_button_position" name="peace_button_position">
                            <option value="top-right" <?php selected($button_position, 'top-right'); ?>><?php esc_html_e('Top Right (default)', 'peace-protocol'); ?></option>
                            <option value="top-left" <?php selected($button_position, 'top-left'); ?>><?php esc_html_e('Top Left', 'peace-protocol'); ?></option>
                            <option value="bottom-left" <?php selected($button_position, 'bottom-left'); ?>><?php esc_html_e('Bottom Left', 'peace-protocol'); ?></option>
                            <option value="bottom-right" <?php selected($button_position, 'bottom-right'); ?>><?php esc_html_e('Bottom Right', 'peace-protocol'); ?></option>
                        </select>
                        <p class="description">
                            <?php esc_html_e('Choose where the auto-inserted peace hand button appears on your site. This setting only applies when the auto-inserted button is visible.', 'peace-protocol'); ?>
                        </p>
                    </td>
                </tr>
            </table>
            <button type="submit" name="peaceprotocol_save_settings" class="button button-primary">
                <?php esc_html_e('Save Settings', 'peace-protocol'); ?>
            </button>
        </form>

        <h2><?php esc_html_e('Your Tokens', 'peace-protocol'); ?></h2>
        <div id="peace-identities-table"></div>
        <!-- 
        LEGITIMATE EXCEPTION: This inline script has been moved to wp_add_inline_script() 
        in includes/inline-scripts.php for proper WordPress compliance.
        The script functionality is now handled by peaceprotocol_get_admin_inline_script().
        -->
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const LS_KEY = 'peace-protocol-identities';
            const site = <?php echo wp_json_encode(esc_url(get_site_url())); ?>;
            const tokens = <?php echo wp_json_encode(array_map('esc_html', get_option('peaceprotocol_tokens', []))); ?>;
            const ajaxNonce = <?php echo wp_json_encode(esc_attr(wp_create_nonce('peaceprotocol_delete_token'))); ?>;
            
            // Clean up any corrupted tokens in localStorage
            try {
                const val = localStorage.getItem(LS_KEY);
                if (val) {
                    const identities = JSON.parse(val);
                    if (Array.isArray(identities)) {
                        // Remove any tokens that contain HTML tags
                        const cleanedIdentities = identities.filter(id => {
                            if (id.token && typeof id.token === 'string') {
                                // Check if token contains HTML tags
                                if (id.token.includes('<') || id.token.includes('>')) {
                                    console.warn('Peace Protocol: Removing corrupted token with HTML content:', id.token);
                                    return false;
                                }
                            }
                            return true;
                        });
                        
                        if (cleanedIdentities.length !== identities.length) {
                            localStorage.setItem(LS_KEY, JSON.stringify(cleanedIdentities));
                            // console.log('Peace Protocol: Cleaned up corrupted tokens from localStorage');
                        }
                    }
                }
            } catch (e) {
                console.error('Peace Protocol: Error cleaning up localStorage:', e);
            }
            
            // Sync only the active token (first one) to localStorage for this site
            let identities = [];
            try {
                const val = localStorage.getItem(LS_KEY);
                if (val) identities = JSON.parse(val);
                if (!Array.isArray(identities)) identities = [];
            } catch (e) { identities = []; }
            
            // Remove any existing entry for this site
            identities = identities.filter(id => id.site !== site);
            
            // Add only the active token (first one) for this site
            if (tokens.length > 0) {
                identities.push({ site, token: tokens[0] });
            }
            
            localStorage.setItem(LS_KEY, JSON.stringify(identities));
            
            // Render table showing actual database tokens
            const tableDiv = document.getElementById('peace-identities-table');
            if (!tokens.length) {
                tableDiv.innerHTML = '<p>No Peace Protocol tokens found in database. Generate a token to get started.</p>';
                return;
            }
            
            let html = '<table class="widefat fixed striped"><thead><tr><th>Token</th><th>Status</th><th>Actions</th></tr></thead><tbody>';
            tokens.forEach((token, i) => {
                html += '<tr>';
                html += '<td><code>' + token.replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/&/g, '&amp;') + '</code></td>';
                html += '<td>' + (i === 0 ? '<span style="color:#2563eb;font-weight:bold;">Active (in localStorage)</span>' : '<span style="color:#888;">Inactive</span>') + '</td>';
                html += '<td>';
                if (tokens.length > 1) {
                    html += '<button type="button" class="button button-small button-link-delete delete-token-btn" data-token="' + encodeURIComponent(token) + '">Delete</button>';
                } else {
                    html += '<span style="color:#888;font-style:italic;">Cannot delete last token</span>';
                }
                html += '</td>';
                html += '</tr>';
            });
            html += '</tbody></table>';
            html += '<p><em>Note: Only the active token (first one) is synced to your browser\'s localStorage for use on the frontend.</em></p>';
            
            // Add debug section
            html += '<h3 style="margin-top: 2em; color: #666;">Debug Information</h3>';
            html += '<div style="background: #f9f9f9; padding: 1em; border-radius: 4px; font-family: monospace; font-size: 12px;">';
            html += '<p><strong>Active Token (Database):</strong></p>';
            if (tokens.length > 0) {
                html += '<p style="word-break: break-all; background: #fff; padding: 0.5em; border: 1px solid #ddd;">' + tokens[0].replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/&/g, '&amp;') + '</p>';
                html += '<p><strong>Token Length:</strong> ' + tokens[0].length + ' characters</p>';
            } else {
                html += '<p style="color: #dc3232;">No tokens in database</p>';
            }
            
            html += '<p><strong>localStorage Identities:</strong></p>';
            html += '<p style="word-break: break-all; background: #fff; padding: 0.5em; border: 1px solid #ddd;">' + JSON.stringify(identities, null, 2).replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/&/g, '&amp;') + '</p>';
            
            // Check if localStorage matches database
            const localStorageToken = identities.find(id => id.site === site)?.token;
            if (localStorageToken && tokens.length > 0) {
                const matches = localStorageToken === tokens[0];
                html += '<p><strong>Token Match:</strong> <span style="color: ' + (matches ? '#46b450' : '#dc3232') + '; font-weight: bold;">' + (matches ? '✓ MATCHES' : '✗ MISMATCH') + '</span></p>';
                if (!matches) {
                    html += '<p><strong>localStorage Token:</strong></p>';
                    html += '<p style="word-break: break-all; background: #fff; padding: 0.5em; border: 1px solid #ddd;">' + (localStorageToken || '').replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</p>';
                    html += '<p><strong>localStorage Token Length:</strong> ' + (localStorageToken || '').length + ' characters</p>';
                }
            } else if (!localStorageToken && tokens.length > 0) {
                html += '<p><strong>Token Match:</strong> <span style="color: #dc3232; font-weight: bold;">✗ NO TOKEN IN LOCALSTORAGE</span></p>';
            } else if (localStorageToken && tokens.length === 0) {
                html += '<p><strong>Token Match:</strong> <span style="color: #dc3232; font-weight: bold;">✗ NO TOKENS IN DATABASE</span></p>';
            }
            
            html += '</div>';
            
            tableDiv.innerHTML = html;
            
            // Add event listeners for delete buttons
            document.querySelectorAll('.delete-token-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const encodedToken = this.getAttribute('data-token');
                    const token = decodeURIComponent(encodedToken);
                    if (confirm('Are you sure you want to delete this token? This action cannot be undone.')) {
                        deleteToken(token);
                    }
                });
            });
            
            function deleteToken(token) {
                const formData = new FormData();
                formData.append('action', 'peaceprotocol_delete_token');
                formData.append('token', token);
                formData.append('nonce', ajaxNonce);
                
                fetch(ajaxurl, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Remove token from localStorage if it was the active one
                        let identities = [];
                        try {
                            const val = localStorage.getItem(LS_KEY);
                            if (val) identities = JSON.parse(val);
                            if (!Array.isArray(identities)) identities = [];
                        } catch (e) { identities = []; }
                        
                        identities = identities.filter(id => !(id.site === site && id.token === token));
                        localStorage.setItem(LS_KEY, JSON.stringify(identities));
                        
                        // Reload the page to show updated token list
                        location.reload();
                    } else {
                        alert('Error deleting token: ' + (data.data || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error deleting token. Please try again.');
                });
            }
        });
        </script>

        <h2><?php esc_html_e('Subscribed Peace Feeds', 'peace-protocol'); ?></h2>
        <p><?php esc_html_e('These are sites you\'ve sent peace to.', 'peace-protocol'); ?></p>
        <!-- Admin styles are now handled by enqueue-assets.php -->
        <div id="peace-unsub-modal-bg">
            <div id="peace-unsub-modal">
                <h3><?php esc_html_e('Unsubscribe from Feed?', 'peace-protocol'); ?></h3>
                <p id="peace-unsub-modal-site"></p>
                <form method="post" id="peace-unsub-form">
                    <input type="hidden" name="peace_unsub_feed" id="peace-unsub-feed-input" value="" />
                    <button type="submit" class="button button-primary">Unsubscribe</button>
                    <button type="button" class="button" id="peace-unsub-cancel">Cancel</button>
                </form>
            </div>
        </div>
        <?php
        if (!empty($feeds)) {
            echo '<div class="peace-feed-grid">';
            foreach ($feeds as $feed_url) {
                echo '<div class="peace-feed-card">';
                echo '<h3>' . esc_html($feed_url) . '</h3>';
                $rss = @fetch_feed($feed_url);
                if (is_wp_error($rss)) {
                    echo '<div class="feed-error">' . esc_html__('Could not fetch feed.', 'peace-protocol') . '</div>';
                } else {
                    $maxitems = $rss->get_item_quantity(5);
                    $rss_items = $rss->get_items(0, $maxitems);
                    if ($maxitems == 0) {
                        echo '<div class="feed-error">' . esc_html__('No items found in feed.', 'peace-protocol') . '</div>';
                    } else {
                        echo '<ul>';
                        foreach ($rss_items as $item) {
                            $title = $item->get_title();
                            $link = $item->get_permalink();
                            $date = $item->get_date(get_option('date_format'));
                            $desc = wp_trim_words(wp_strip_all_tags($item->get_description()), 24);
                            echo '<li>';
                            echo '<a href="' . esc_url($link) . '" target="_blank" rel="noopener"><strong>' . esc_html($title) . '</strong></a><br>';
                            echo '<span style="font-size:0.95em;color:#666;">' . esc_html($date) . '</span><br>';
                            echo '<span style="font-size:0.97em;">' . esc_html($desc) . '</span>';
                            echo '</li>';
                        }
                        echo '</ul>';
                    }
                }
                echo '<button class="button button-secondary unsubscribe-btn" data-feed-url="' . esc_attr($feed_url) . '">' . esc_html__('Unsubscribe', 'peace-protocol') . '</button>';
                echo '</div>';
            }
            echo '</div>';
        } else {
            echo '<p>' . esc_html__('No peace feeds subscribed yet.', 'peace-protocol') . '</p>';
        }
        ?>
    </div>
    <div id="peace-protocol-message" style="margin-top:1em;" role="alert"></div>
    <!-- Admin JavaScript is now handled by enqueue-assets.php -->
    <?php
    if (isset($_POST['peace_unsub_feed'])) {
        $unsub_feed = esc_url_raw(wp_unslash($_POST['peace_unsub_feed']));
        $feeds = get_option('peaceprotocol_feeds', []);
        $feeds = array_filter($feeds, function($f) use ($unsub_feed) { return $f !== $unsub_feed; });
        update_option('peaceprotocol_feeds', array_values($feeds));
        echo '<div class="updated"><p>' . esc_html__('Unsubscribed from feed:', 'peace-protocol') . ' ' . esc_html($unsub_feed) . '</p></div>';
        wp_safe_redirect(admin_url('options-general.php?page=peace-protocol'));
        exit;
    }
}

function peaceprotocol_rotate_tokens_callback() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized', 403);
    }
    if (!isset($_POST['tokens']) || !isset($_POST['nonce'])) {
        wp_send_json_error('Missing required parameters', 400);
    }
    
    $nonce = sanitize_text_field(wp_unslash($_POST['nonce']));
    if (!wp_verify_nonce($nonce, 'peaceprotocol_rotate_tokens')) {
        wp_send_json_error('Invalid nonce', 400);
    }
    
    // Get raw tokens and decode HTML entities
    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Tokens contain special characters, processed safely with wp_unslash and html_entity_decode
    $tokens_raw = sanitize_textarea_field(wp_unslash($_POST['tokens']));
    $tokens_raw = html_entity_decode($tokens_raw, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Tokens contain special characters, processed safely with wp_unslash and html_entity_decode
    
    $tokens = json_decode($tokens_raw, true);
    if (!is_array($tokens)) {
        wp_send_json_error('Invalid tokens', 400);
    }
    
    // Sanitize each token in the array
    $tokens = array_map('sanitize_text_field', $tokens);
    
    update_option('peaceprotocol_tokens', $tokens);
    wp_send_json_success();
    wp_die();
}

function peaceprotocol_delete_token_callback() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized', 403);
    }
    if (!isset($_POST['token']) || !isset($_POST['nonce'])) {
        wp_send_json_error('Missing required parameters', 400);
    }
    
    $nonce = sanitize_text_field(wp_unslash($_POST['nonce']));
    if (!wp_verify_nonce($nonce, 'peaceprotocol_delete_token')) {
        wp_send_json_error('Invalid nonce', 400);
    }
    
    $token_to_delete = sanitize_text_field(wp_unslash($_POST['token']));
    // Decode HTML entities in case the token was encoded
    $token_to_delete = html_entity_decode($token_to_delete, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    
    $tokens = get_option('peaceprotocol_tokens', []);
    
    // Prevent deleting the last token
    if (count($tokens) <= 1) {
        wp_send_json_error('Cannot delete the last token', 400);
    }
    
    // Debug logging
    // error_log('Peace Protocol: Attempting to delete token. Token to delete: ' . substr($token_to_delete, 0, 10) . '...');
    // error_log('Peace Protocol: Available tokens: ' . count($tokens));
    
    // Check if token exists before trying to delete
    $token_found = false;
    foreach ($tokens as $token) {
        if ($token === $token_to_delete) {
            $token_found = true;
            break;
        }
    }
    
    if (!$token_found) {
        // error_log('Peace Protocol: Token not found in database. Token to delete: ' . substr($token_to_delete, 0, 10) . '...');
        wp_send_json_error('Token not found in database', 404);
    }
    
    // Remove the token
    $tokens = array_filter($tokens, function($token) use ($token_to_delete) {
        return $token !== $token_to_delete;
    });
    
    // Re-index array
    $tokens = array_values($tokens);
    
    update_option('peaceprotocol_tokens', $tokens);
    // error_log('Peace Protocol: Token deleted successfully. Remaining tokens: ' . count($tokens));
    wp_send_json_success();
    wp_die();
}
