<?php
defined('ABSPATH') || exit;

// Frontend button rendering is now handled in enqueue-assets.php

add_action('wp_footer', function () {
    // Remove front page check so federated login works on any page
    // if (!is_front_page()) {
    //     return;
    // }
    $hide_auto_button = get_option('peaceprotocol_hide_auto_button', '0');
    if ($hide_auto_button === '1') {
        return;
    }
    
    // Debug logging
    // error_log('[Peace Protocol] wp_footer action running');
    // error_log('[Peace Protocol] hide_auto_button: ' . $hide_auto_button);
    // error_log('[Peace Protocol] function_exists: ' . (function_exists('peaceprotocol_render_hand_button') ? 'true' : 'false'));
    
    if (!$hide_auto_button && function_exists('peaceprotocol_render_hand_button')) {
        peaceprotocol_render_hand_button();
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
            $feeds = get_option('peaceprotocol_feeds', []);
            if (!in_array($feed_url, $feeds, true)) {
                $feeds[] = $feed_url;
                update_option('peaceprotocol_feeds', $feeds);
            }
            return ['success' => true];
        },
        'permission_callback' => function() {
            // This endpoint is intentionally public for cross-site peace protocol federation
            // Security is handled via feed URL validation in the callback function
            return true;
        },
    ]);
});

// AJAX handler for subscribing to a feed after sending peace (backup for sites with REST API disabled)
add_action('wp_ajax_peaceprotocol_subscribe_feed', function() {
    if (!isset($_POST['feed_url']) || !isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'peaceprotocol_subscribe_feed')) {
        wp_send_json_error('Missing feed_url or invalid nonce');
    }
    $feed_url = esc_url_raw(wp_unslash($_POST['feed_url']));
    if (!$feed_url) {
        wp_send_json_error('Invalid feed_url');
    }
    $feeds = get_option('peaceprotocol_feeds', []);
    if (!in_array($feed_url, $feeds, true)) {
        $feeds[] = $feed_url;
        update_option('peaceprotocol_feeds', $feeds);
    }
    wp_send_json_success();
});

add_action('wp_ajax_nopriv_peaceprotocol_subscribe_feed', function() {
    if (!isset($_POST['feed_url']) || !isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'peaceprotocol_subscribe_feed')) {
        wp_send_json_error('Missing feed_url or invalid nonce');
    }
    $feed_url = esc_url_raw(wp_unslash($_POST['feed_url']));
    if (!$feed_url) {
        wp_send_json_error('Invalid feed_url');
    }
    $feeds = get_option('peaceprotocol_feeds', []);
    if (!in_array($feed_url, $feeds, true)) {
        $feeds[] = $feed_url;
        update_option('peaceprotocol_feeds', $feeds);
    }
    wp_send_json_success();
});
