<?php
/**
 * Plugin Name: Peace Protocol
 * Plugin URI: https://wilcosky.com/peace-protocol
 * Description: A decentralized way for WordPress admins to share peace, respect, and follow each other with cryptographic handshakes.
 * Version: 1.2.4
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

define('PEACEPROTOCOL_VERSION', '1.2.4');
define('PEACEPROTOCOL_DIR', plugin_dir_path(__FILE__));
define('PEACEPROTOCOL_URL', plugin_dir_url(__FILE__));

require_once PEACEPROTOCOL_DIR . 'includes/register-cpt.php';
require_once PEACEPROTOCOL_DIR . 'includes/rest-endpoints.php';
require_once PEACEPROTOCOL_DIR . 'includes/shortcodes.php';
require_once PEACEPROTOCOL_DIR . 'includes/enqueue-assets.php';
require_once PEACEPROTOCOL_DIR . 'includes/inline-scripts.php';
require_once PEACEPROTOCOL_DIR . 'includes/frontend-button.php';
require_once PEACEPROTOCOL_DIR . 'includes/admin-pages.php';
require_once PEACEPROTOCOL_DIR . 'includes/federated-users.php';
require_once PEACEPROTOCOL_DIR . 'includes/user-banning.php';

register_activation_hook(__FILE__, function () {
    if (!get_option('peaceprotocol_tokens')) {
        update_option('peaceprotocol_tokens', [peaceprotocol_generate_secure_token(32)]);
    }
    if (!get_option('peaceprotocol_feeds')) {
        update_option('peaceprotocol_feeds', []);
    }
    if (!get_option('peaceprotocol_banned_users')) {
        update_option('peaceprotocol_banned_users', []);
    }
    if (!get_option('peaceprotocol_ban_reasons')) {
        update_option('peaceprotocol_ban_reasons', []);
    }
    if (!get_option('peaceprotocol_indieauth_requests')) {
        update_option('peaceprotocol_indieauth_requests', []);
    }
    update_option('peaceprotocol_tokens_last_rotation', current_time('mysql'));
    
    // Set cache busting version for JavaScript
    update_option('peaceprotocol_js_version', PEACEPROTOCOL_VERSION . '.' . time());
    
    flush_rewrite_rules();
});

register_deactivation_hook(__FILE__, function () {
    // Clean up cache busting options
    delete_option('peaceprotocol_js_version');
    delete_option('peaceprotocol_version');
    
    flush_rewrite_rules();
});

add_action('init', function () {
    if (get_option('peaceprotocol_token')) {
        delete_option('peaceprotocol_token');
    }
    
    // Check if plugin version has changed and update cache busting version
    $current_version = get_option('peaceprotocol_version', '0.0.0');
    if ($current_version !== PEACEPROTOCOL_VERSION) {
        update_option('peaceprotocol_version', PEACEPROTOCOL_VERSION);
        update_option('peaceprotocol_js_version', PEACEPROTOCOL_VERSION . '.' . time());
    }
    

});

// Function to manually force cache busting
function peaceprotocol_force_cache_bust() {
    update_option('peaceprotocol_js_version', PEACEPROTOCOL_VERSION . '.' . time());
    return true;
}

// Add cache busting action to admin
add_action('admin_post_peaceprotocol_force_cache_bust', function() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'peaceprotocol_force_cache_bust')) {
        wp_die('Security check failed');
    }
    
    peaceprotocol_force_cache_bust();
    
    // Redirect back to admin page with success message
    wp_redirect(admin_url('options-general.php?page=peace-protocol&cache_busted=1'));
    exit;
});
