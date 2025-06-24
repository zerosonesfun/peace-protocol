<?php
defined('ABSPATH') || exit;

add_action('rest_api_init', function () {
    register_rest_route('pass-the-peace/v1', '/receive', [
        'methods' => 'POST',
        'callback' => 'peace_protocol_receive_peace',
        'permission_callback' => '__return_true'
    ]);
});

function peace_protocol_receive_peace($request) {
    $from = sanitize_text_field($request['from_site']);
    $token = sanitize_text_field($request['token']);
    $note = sanitize_text_field($request['note']);

    $tokens = get_option('peace_tokens', []);
    if (!in_array($token, $tokens, true)) {
        return new WP_Error('invalid_token', 'Invalid token', ['status' => 403]);
    }

    // Save Peace Log
    $log_id = wp_insert_post([
        'post_type' => 'peace_log',
        'post_title' => 'Peace from ' . $from,
        'post_content' => $note,
        'post_status' => 'publish',
        'meta_input' => ['from_site' => $from, 'note' => $note]
    ]);

    // Subscribe to RSS feed
    $feeds = get_option('peace_feeds', []);
    if (!in_array($from, $feeds)) {
        $feeds[] = $from;
        update_option('peace_feeds', $feeds);
    }

    return ['success' => true, 'log_id' => $log_id];
}
