<?php
defined('ABSPATH') || exit;

add_action('init', function () {
    $labels = [
        'name' => __('Peace Logs', 'peace-protocol'),
        'singular_name' => __('Peace Log', 'peace-protocol'),
        'menu_name' => __('Peace Logs', 'peace-protocol'),
        'name_admin_bar' => __('Peace Log', 'peace-protocol'),
        'add_new' => __('Add New', 'peace-protocol'),
        'add_new_item' => __('Add New Peace Log', 'peace-protocol'),
        'new_item' => __('New Peace Log', 'peace-protocol'),
        'edit_item' => __('Edit Peace Log', 'peace-protocol'),
        'view_item' => __('View Peace Log', 'peace-protocol'),
        'all_items' => __('All Peace Logs', 'peace-protocol'),
        'search_items' => __('Search Peace Logs', 'peace-protocol'),
        'not_found' => __('No peace logs found.', 'peace-protocol'),
        'not_found_in_trash' => __('No peace logs found in Trash.', 'peace-protocol'),
    ];

    register_post_type('peaceprotocol_log', [
        'labels' => $labels,
        'public' => false,
        'show_ui' => true,
        'show_in_menu' => true,
        'menu_icon' => 'dashicons-heart',
        'supports' => ['title', 'editor', 'custom-fields'],
        'capability_type' => 'post',
        'map_meta_cap' => true,
        'exclude_from_search' => true,
        'has_archive' => false,
    ]);
});

// Add custom column to peaceprotocol_log CPT admin table for the optional message (note)
add_filter('manage_peaceprotocol_log_posts_columns', function($columns) {
    $columns['note'] = __('Message', 'peace-protocol');
    return $columns;
});
add_action('manage_peaceprotocol_log_posts_custom_column', function($column, $post_id) {
    if ($column === 'note') {
        $note = get_post_meta($post_id, 'note', true);
        echo esc_html($note);
    }
}, 10, 2);
