<?php
/**
 * Plugin Name: Peace Protocol
 * Plugin URI: https://wilcosky.com/peace-protocol
 * Description: A decentralized way for WordPress admins to share peace, respect, and follow each other with cryptographic handshakes.
 * Version: 1.2.0
 * Requires at least: 6.0
 * Tested up to: 6.5
 * Requires PHP: 7.4
 * Author: Billy Wilcosky
 * Author URI: https://wilcosky.com
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: peace-protocol
 * Domain Path: /languages
 */

defined('ABSPATH') || exit;

define('PEACE_PROTOCOL_VERSION', '1.2.0');
define('PEACE_PROTOCOL_DIR', plugin_dir_path(__FILE__));
define('PEACE_PROTOCOL_URL', plugin_dir_url(__FILE__));

require_once PEACE_PROTOCOL_DIR . 'includes/register-cpt.php';
require_once PEACE_PROTOCOL_DIR . 'includes/rest-endpoints.php';
require_once PEACE_PROTOCOL_DIR . 'includes/shortcodes.php';
require_once PEACE_PROTOCOL_DIR . 'includes/frontend-button.php';
require_once PEACE_PROTOCOL_DIR . 'includes/admin-pages.php';
require_once PEACE_PROTOCOL_DIR . 'includes/federated-users.php';
require_once PEACE_PROTOCOL_DIR . 'includes/user-banning.php';

register_activation_hook(__FILE__, function () {
    if (!get_option('peace_tokens')) {
        update_option('peace_tokens', [trim(wp_generate_password(32, true, true))]);
    }
    if (!get_option('peace_feeds')) {
        update_option('peace_feeds', []);
    }
    if (!get_option('peace_protocol_banned_users')) {
        update_option('peace_protocol_banned_users', []);
    }
    if (!get_option('peace_protocol_ban_reasons')) {
        update_option('peace_protocol_ban_reasons', []);
    }
    if (!get_option('peace_protocol_indieauth_requests')) {
        update_option('peace_protocol_indieauth_requests', []);
    }
    update_option('peace_tokens_last_rotation', current_time('mysql'));
    
    // Set cache busting version for JavaScript
    update_option('peace_protocol_js_version', PEACE_PROTOCOL_VERSION . '.' . time());
    
    flush_rewrite_rules();
});

register_deactivation_hook(__FILE__, function () {
    // Clean up cache busting options
    delete_option('peace_protocol_js_version');
    delete_option('peace_protocol_version');
    
    flush_rewrite_rules();
});

add_action('init', function () {
    if (get_option('peace_token')) {
        delete_option('peace_token');
    }
    
    // Check if plugin version has changed and update cache busting version
    $current_version = get_option('peace_protocol_version', '0.0.0');
    if ($current_version !== PEACE_PROTOCOL_VERSION) {
        update_option('peace_protocol_version', PEACE_PROTOCOL_VERSION);
        update_option('peace_protocol_js_version', PEACE_PROTOCOL_VERSION . '.' . time());
    }
    
    load_plugin_textdomain('peace-protocol', false, dirname(plugin_basename(__FILE__)) . '/languages');
});

// Function to manually force cache busting
function peace_protocol_force_cache_bust() {
    update_option('peace_protocol_js_version', PEACE_PROTOCOL_VERSION . '.' . time());
    return true;
}

// Add cache busting action to admin
add_action('admin_post_peace_protocol_force_cache_bust', function() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'peace_protocol_force_cache_bust')) {
        wp_die('Security check failed');
    }
    
    peace_protocol_force_cache_bust();
    
    // Redirect back to admin page with success message
    wp_redirect(admin_url('options-general.php?page=peace-protocol&cache_busted=1'));
    exit;
});
