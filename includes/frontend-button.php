<?php
defined('ABSPATH') || exit;

add_action('wp_enqueue_scripts', function () {
    // Remove front page check so federated login works on any page
    // if (!is_front_page()) {
    //     return;
    // }
    
    // Enhanced cache busting strategy
    $js_file = PEACE_PROTOCOL_DIR . 'js/frontend.js';
    
    // Method 1: Use file modification time (most reliable for development)
    $file_version = file_exists($js_file) ? filemtime($js_file) : PEACE_PROTOCOL_VERSION;
    
    // Method 2: Use plugin version (good for production releases)
    $plugin_version = PEACE_PROTOCOL_VERSION;
    
    // Method 3: Use stored cache busting version from database
    $stored_version = get_option('peace_protocol_js_version', $plugin_version);
    
    // Method 4: Combine all methods for maximum cache busting
    $js_version = $file_version . '.' . $stored_version;
    
    // Alternative: Use file hash for even more precise cache busting
    // $file_hash = file_exists($js_file) ? md5_file($js_file) : PEACE_PROTOCOL_VERSION;
    // $js_version = $file_hash;
    
    wp_enqueue_script('peace-protocol-frontend', PEACE_PROTOCOL_URL . 'js/frontend.js', ['jquery'], $js_version, true);

    wp_localize_script('peace-protocol-frontend', 'peaceData', [
        'restUrl' => rest_url('peace-protocol/v1/receive'),
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('wp_rest'),
        'federatedLoginNonce' => wp_create_nonce('peace_protocol_federated_login'),
        'i18n_confirm' => __('Do you want to give peace to this site?', 'peace-protocol'),
        'i18n_yes' => __('Yes', 'peace-protocol'),
        'i18n_no' => __('Cancel', 'peace-protocol'),
        'i18n_note' => __('Optional note (max 50 characters):', 'peace-protocol'),
        'i18n_send' => __('Send Peace', 'peace-protocol'),
        'i18n_cancel' => __('Cancel', 'peace-protocol'),
        'siteUrl' => get_site_url(),
        'version' => $js_version, // Add version to peaceData for debugging
        'debug' => [
            'fileVersion' => $file_version,
            'pluginVersion' => $plugin_version,
            'storedVersion' => $stored_version,
            'finalVersion' => $js_version,
            'fileExists' => file_exists($js_file),
            'fileModTime' => file_exists($js_file) ? filemtime($js_file) : 'N/A',
        ],
    ]);
    
    // Add script to clear ban flags and ensure proper function loading
    wp_add_inline_script('peace-protocol-frontend', '
    // Clear any ban flags that might be preventing Peace Protocol from working
    if (typeof localStorage !== "undefined" && localStorage.getItem("peace-protocol-banned") === "true") {
        // console.log("[Peace Protocol] Clearing ban flag that was preventing function loading");
        localStorage.removeItem("peace-protocol-banned");
    }
    ', 'before');
});

add_action('wp_footer', function () {
    // Remove front page check so federated login works on any page
    // if (!is_front_page()) {
    //     return;
    // }
    $hide_auto_button = get_option('peace_hide_auto_button', '0');
    if ($hide_auto_button === '1') {
        return;
    }
    
    // Debug logging
    // error_log('[Peace Protocol] wp_footer action running');
    // error_log('[Peace Protocol] hide_auto_button: ' . $hide_auto_button);
    // error_log('[Peace Protocol] function_exists: ' . (function_exists('peace_protocol_render_hand_button') ? 'true' : 'false'));
    
    if (!$hide_auto_button && function_exists('peace_protocol_render_hand_button')) {
        peace_protocol_render_hand_button();
        // error_log('[Peace Protocol] Button rendered');
    } else {
        // error_log('[Peace Protocol] Function does not exist!');
    }
});

// REST API endpoint for subscribing to a feed after sending peace
add_action('rest_api_init', function () {
    register_rest_route('peace-protocol/v1', '/subscribe', [
        'methods' => 'POST',
        'callback' => function ($request) {
            $feed_url = esc_url_raw($request['feed_url']);
            if (!$feed_url) {
                return new WP_Error('missing_feed_url', 'Missing feed_url', ['status' => 400]);
            }
            $feeds = get_option('peace_feeds', []);
            if (!in_array($feed_url, $feeds, true)) {
                $feeds[] = $feed_url;
                update_option('peace_feeds', $feeds);
            }
            return ['success' => true];
        },
        'permission_callback' => '__return_true',
    ]);
});

// AJAX handler for subscribing to a feed after sending peace (backup for sites with REST API disabled)
add_action('wp_ajax_peace_protocol_subscribe_feed', function() {
    if (!isset($_POST['feed_url']) || !isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'peace_protocol_subscribe_feed')) {
        wp_send_json_error('Missing feed_url or invalid nonce');
    }
    $feed_url = esc_url_raw(wp_unslash($_POST['feed_url']));
    if (!$feed_url) {
        wp_send_json_error('Invalid feed_url');
    }
    $feeds = get_option('peace_feeds', []);
    if (!in_array($feed_url, $feeds, true)) {
        $feeds[] = $feed_url;
        update_option('peace_feeds', $feeds);
    }
    wp_send_json_success();
});

add_action('wp_ajax_nopriv_peace_protocol_subscribe_feed', function() {
    if (!isset($_POST['feed_url']) || !isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'peace_protocol_subscribe_feed')) {
        wp_send_json_error('Missing feed_url or invalid nonce');
    }
    $feed_url = esc_url_raw(wp_unslash($_POST['feed_url']));
    if (!$feed_url) {
        wp_send_json_error('Invalid feed_url');
    }
    $feeds = get_option('peace_feeds', []);
    if (!in_array($feed_url, $feeds, true)) {
        $feeds[] = $feed_url;
        update_option('peace_feeds', $feeds);
    }
    wp_send_json_success();
});
