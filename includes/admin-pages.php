<?php
defined('ABSPATH') || exit;

add_action('admin_menu', function () {
    add_options_page(
        __('Peace Protocol', 'peace-protocol'),
        __('Peace Protocol', 'peace-protocol'),
        'manage_options',
        'peace-protocol',
        'peace_protocol_admin_page'
    );
});

function peace_protocol_admin_page()
{
    if (!current_user_can('manage_options')) {
        return;
    }

    // Handle federated return parameters
    if (isset($_GET['peace_federated_return']) && isset($_GET['peace_federated_state'])) {
        $return_site = esc_url_raw($_GET['peace_federated_return']);
        $state = sanitize_text_field($_GET['peace_federated_state']);
        
        error_log('Peace Protocol Admin: Federated return detected - return_site: ' . $return_site . ', state: ' . $state);
        
        // Generate authorization code
        $auth_code = wp_generate_password(32, false);
        $expires = time() + 3600; // 1 hour
        
        // Store authorization code
        $authorizations = get_option('peace_protocol_authorizations', array());
        $authorizations[$auth_code] = array(
            'site_url' => get_site_url(),
            'return_site' => $return_site,
            'expires' => $expires,
            'used' => false
        );
        update_option('peace_protocol_authorizations', $authorizations);
        
        error_log('Peace Protocol Admin: Generated authorization code: ' . $auth_code . ' for return site: ' . $return_site);
        
        // Subscribe this site to the return site's feed
        peace_protocol_subscribe_to_feed($return_site);
        
        // Redirect back to return site with authorization code
        $redirect = $return_site;
        $separator = strpos($return_site, '?') !== false ? '&' : '?';
        $redirect .= $separator . 'peace_authorization_code=' . urlencode($auth_code) . '&peace_federated_site=' . urlencode(get_site_url()) . '&peace_federated_state=' . urlencode($state);
        
        error_log('Peace Protocol Admin: Redirecting to: ' . $redirect);
        wp_redirect($redirect);
        exit;
    }

    // --- Rotate tokens before any output ---
    $tokens = get_option('peace_tokens', []);
    if (count($tokens) > 1) {
        $first = array_shift($tokens);
        $tokens[] = $first;
        update_option('peace_tokens', $tokens);
    }

    // --- Handle POST actions before output ---
    if (isset($_POST['peace_protocol_generate_token']) && check_admin_referer('peace_protocol_generate_token')) {
        $tokens = get_option('peace_tokens', []);
        $tokens[] = wp_generate_password(32, true, true);
        update_option('peace_tokens', $tokens);
    }
    if (isset($_POST['peace_protocol_delete_token']) && isset($_POST['token_to_delete']) && check_admin_referer('peace_protocol_delete_token')) {
        $token_to_delete = sanitize_text_field($_POST['token_to_delete']);
        $tokens = get_option('peace_tokens', []);
        $tokens = array_filter($tokens, fn($t) => $t !== $token_to_delete);
        update_option('peace_tokens', array_values($tokens));
    }

    if (isset($_POST['peace_protocol_save_settings']) && check_admin_referer('peace_protocol_settings')) {
        $hide_auto_button = isset($_POST['peace_hide_auto_button']) ? '1' : '0';
        update_option('peace_hide_auto_button', $hide_auto_button);
        echo '<div class="updated"><p>' . esc_html__('Settings saved.', 'peace-protocol') . '</p></div>';
    }

    $tokens = get_option('peace_tokens', []);
    $feeds = get_option('peace_feeds', []);
    $site_url = get_site_url();
    $ajax_nonce = wp_create_nonce('peace_protocol_rotate_tokens');
    $hide_auto_button = get_option('peace_hide_auto_button', '0');
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Peace Protocol Settings', 'peace-protocol'); ?></h1>

        <p><?php esc_html_e('Tokens are used to authenticate your site when sending peace. Keep them secret. You can generate multiple tokens for rotation. Feeds are sites you have interacted with.', 'peace-protocol'); ?></p>

        <h2><?php esc_html_e('Your Tokens', 'peace-protocol'); ?></h2>
        <form method="post" style="margin-bottom:2em;">
            <?php wp_nonce_field('peace_protocol_generate_token'); ?>
            <button type="submit" name="peace_protocol_generate_token" class="button button-primary">
                <?php esc_html_e('Generate New Token', 'peace-protocol'); ?>
            </button>
        </form>

        <form method="post" style="margin-bottom:2em;">
            <?php wp_nonce_field('peace_protocol_settings'); ?>
            <label>
                <input type="checkbox" name="peace_hide_auto_button" value="1" <?php checked(get_option('peace_hide_auto_button', '0'), '1'); ?> />
                <?php esc_html_e('Hide the automatically inserted peace hand button (use shortcode instead)', 'peace-protocol'); ?>
            </label>
            <button type="submit" name="peace_protocol_save_settings" class="button button-primary">
                <?php esc_html_e('Save Settings', 'peace-protocol'); ?>
            </button>
        </form>

        <h2><?php esc_html_e('Your Site Identities (in this browser)', 'peace-protocol'); ?></h2>
        <div id="peace-identities-table"></div>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const LS_KEY = 'peace-protocol-identities';
            const site = <?php echo json_encode(get_site_url()); ?>;
            const tokens = <?php echo json_encode(get_option('peace_tokens', [])); ?>;
            
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
            
            let html = '<table class="widefat fixed striped"><thead><tr><th>Token</th><th>Status</th></tr></thead><tbody>';
            tokens.forEach((token, i) => {
                html += '<tr>';
                html += '<td><code>' + token + '</code></td>';
                html += '<td>' + (i === 0 ? '<span style="color:#2563eb;font-weight:bold;">Active (in localStorage)</span>' : '<span style="color:#888;">Inactive</span>') + '</td>';
                html += '</tr>';
            });
            html += '</tbody></table>';
            html += '<p><em>Note: Only the active token (first one) is synced to your browser\'s localStorage for use on the frontend.</em></p>';
            tableDiv.innerHTML = html;
        });
        </script>

        <h2><?php esc_html_e('Subscribed Peace Feeds', 'peace-protocol'); ?></h2>
        <p><?php esc_html_e('These are sites you sent peace to.', 'peace-protocol'); ?></p>
        <style>
        .peace-feed-grid { display: flex; flex-wrap: wrap; gap: 1.5em; margin: 0 -0.75em; }
        .peace-feed-card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            padding: 1em;
            margin: 0.75em;
            flex: 1 1 320px;
            min-width: 280px;
            max-width: 420px;
            display: flex;
            flex-direction: column;
        }
        .peace-feed-card h3 { margin-top: 0; font-size: 1.1em; }
        .peace-feed-card ul { padding-left: 1.2em; margin: 0; }
        .peace-feed-card li { margin-bottom: 0.7em; }
        .peace-feed-card .feed-error { color: #b91c1c; font-size: 0.95em; }
        .peace-feed-card .unsubscribe-btn { margin-top: 0.7em; }
        @media (max-width: 700px) {
            .peace-feed-grid { flex-direction: column; gap: 0.5em; }
            .peace-feed-card { max-width: 100%; min-width: 0; }
        }
        #peace-unsub-modal-bg { display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); z-index:100001; align-items:center; justify-content:center; }
        #peace-unsub-modal { background:#fff; border-radius:8px; padding:2em; max-width:340px; margin:auto; text-align:center; }
        #peace-unsub-modal button {
            margin-top: 0.5rem;
            margin-right: 0.5rem;
        }
        /* Fallback for .button if theme does not style it */
        #peace-unsub-modal .button {
            display: inline-block;
            padding: 0.5em 1.2em;
            font-size: 1em;
            border-radius: 4px;
            border: none;
            background: #f3f4f6;
            color: #222;
            cursor: pointer;
            transition: background 0.2s;
            box-shadow: 0 1px 2px rgba(0,0,0,0.03);
        }
        #peace-unsub-modal .button-primary {
            background: #2563eb;
            color: #fff;
        }
        #peace-unsub-modal .button:hover, #peace-unsub-modal .button-primary:hover {
            background: #1e40af;
            color: #fff;
        }
        /* Admin page button styling */
        button[name="peace_protocol_save_settings"] {
            margin-left: 1em;
        }
        /* Dark mode support for admin unsubscribe modal */
        @media (prefers-color-scheme: dark) {
            #peace-unsub-modal {
                background: #222;
                color: #eee;
            }
        }
        </style>
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
    <script>
    // Always define ajaxurl globally, using wp_json_encode for bulletproof JS
    var ajaxurl = <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>;
    (function() {
        var tokens = <?php echo wp_json_encode($tokens); ?>;
        var site = <?php echo wp_json_encode($site_url); ?>;
        var peaceKey = 'peace-protocol-data';
        var data = { tokens: tokens, site: site };
        var messageDiv = document.getElementById('peace-protocol-message');
        var ajaxNonce = <?php echo wp_json_encode($ajax_nonce); ?>;

        // Token rotation logic
        if (tokens.length > 1) {
            var idx = 0;
            try {
                var prev = JSON.parse(localStorage.getItem(peaceKey));
                if (prev && prev.tokens && prev.tokens.length === tokens.length && prev.tokens[0] === tokens[0]) {
                    idx = (prev._idx || 0) + 1;
                }
            } catch(e) {}
            if (idx >= tokens.length) idx = 0;
            tokens = tokens.slice(idx).concat(tokens.slice(0, idx));
            data.tokens = tokens;
            data._idx = idx;
            // Update peace_tokens option in DB via AJAX
            var xhr = new XMLHttpRequest();
            xhr.open('POST', ajaxurl, true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onload = function() {
                if (xhr.status === 200 && xhr.responseText.indexOf('success') !== -1) {
                    messageDiv.innerHTML = '<div class="notice notice-success" style="padding:10px;">Token rotated successfully.</div>';
                } else {
                    messageDiv.innerHTML = '<div class="notice notice-error" style="padding:10px;">Token rotation failed. Please reload the page.</div>';
                }
            };
            xhr.onerror = function() {
                messageDiv.innerHTML = '<div class="notice notice-error" style="padding:10px;">Token rotation failed. Please check your connection.</div>';
            };
            xhr.send('action=peace_protocol_rotate_tokens&tokens=' + encodeURIComponent(JSON.stringify(tokens)) + '&nonce=' + encodeURIComponent(ajaxNonce));
        }
        localStorage.setItem(peaceKey, JSON.stringify(data));
    })();
    </script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var unsubModalBg = document.getElementById('peace-unsub-modal-bg');
        var unsubModalSite = document.getElementById('peace-unsub-modal-site');
        var unsubFeedInput = document.getElementById('peace-unsub-feed-input');
        var unsubCancel = document.getElementById('peace-unsub-cancel');
        var unsubForm = document.getElementById('peace-unsub-form');
        document.querySelectorAll('.unsubscribe-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                unsubModalSite.textContent = this.getAttribute('data-feed-url');
                unsubFeedInput.value = this.getAttribute('data-feed-url');
                unsubModalBg.style.display = 'flex';
            });
        });
        unsubCancel.addEventListener('click', function() {
            unsubModalBg.style.display = 'none';
        });
    });
    </script>
    <?php
    if (isset($_POST['peace_unsub_feed'])) {
        $unsub_feed = esc_url_raw(wp_unslash($_POST['peace_unsub_feed']));
        $feeds = get_option('peace_feeds', []);
        $feeds = array_filter($feeds, function($f) use ($unsub_feed) { return $f !== $unsub_feed; });
        update_option('peace_feeds', array_values($feeds));
        echo '<div class="updated"><p>' . esc_html__('Unsubscribed from feed:', 'peace-protocol') . ' ' . esc_html($unsub_feed) . '</p></div>';
        wp_safe_redirect(admin_url('options-general.php?page=peace-protocol'));
        exit;
    }
}

add_action('wp_ajax_peace_protocol_rotate_tokens', function () {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized', 403);
    }
    if (!isset($_POST['tokens']) || !isset($_POST['nonce'])) {
        wp_send_json_error('Missing required parameters', 400);
    }
    
    $nonce = sanitize_text_field(wp_unslash($_POST['nonce']));
    if (!wp_verify_nonce($nonce, 'peace_protocol_rotate_tokens')) {
        wp_send_json_error('Invalid nonce', 400);
    }
    
    $tokens_raw = sanitize_textarea_field(wp_unslash($_POST['tokens']));
    $tokens = json_decode($tokens_raw, true);
    if (!is_array($tokens)) {
        wp_send_json_error('Invalid tokens', 400);
    }
    update_option('peace_tokens', $tokens);
    wp_send_json_success();
});
