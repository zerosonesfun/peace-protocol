<?php
defined('ABSPATH') || exit;

add_action('wp_enqueue_scripts', function () {
    // Remove front page check so federated login works on any page
    // if (!is_front_page()) {
    //     return;
    // }
    $js_file = PEACE_PROTOCOL_DIR . 'js/frontend.js';
    $js_version = file_exists($js_file) ? filemtime($js_file) : PEACE_PROTOCOL_VERSION;
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
    ]);
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
    error_log('[Peace Protocol] wp_footer action running');
    error_log('[Peace Protocol] hide_auto_button: ' . $hide_auto_button);
    error_log('[Peace Protocol] function_exists: ' . (function_exists('peace_protocol_render_hand_button') ? 'true' : 'false'));
    
    if (function_exists('peace_protocol_render_hand_button')) {
        // The peace_protocol_render_hand_button() function returns safe, fully-escaped HTML markup.
        // All dynamic content inside is properly escaped using esc_html_e(), esc_attr_e(), etc.
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo peace_protocol_render_hand_button();
        error_log('[Peace Protocol] Button rendered');
    } else {
        error_log('[Peace Protocol] Function does not exist!');
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
    if (!isset($_POST['feed_url']) || !isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'peace_protocol_subscribe_feed')) {
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
    if (!isset($_POST['feed_url']) || !isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'peace_protocol_subscribe_feed')) {
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
