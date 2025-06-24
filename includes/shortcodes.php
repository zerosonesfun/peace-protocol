<?php
defined('ABSPATH') || exit;

add_shortcode('peace_log_wall', function () {
    $logs = get_posts([
        'post_type' => 'peace_log',
        'posts_per_page' => 10,
        'post_status' => 'publish'
    ]);

    if (!$logs) return '<p>No peace logs yet.</p>';

    $output = '<ul class="peace-log-wall">';
    foreach ($logs as $log) {
        $from = get_post_meta($log->ID, 'from_site', true);
        $note = get_post_meta($log->ID, 'note', true);
        $output .= '<li><strong>' . esc_html($from) . '</strong>: ' . esc_html($note) . '</li>';
    }
    $output .= '</ul>';
    return $output;
});
