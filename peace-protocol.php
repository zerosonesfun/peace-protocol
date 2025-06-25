<?php
/**
 * Plugin Name: Peace Protocol
 * Plugin URI: https://wilcosky.com/peace-protocol
 * Description: A decentralized way for WordPress admins to share peace, respect, and follow each other with cryptographic handshakes.
 * Version: 1.0.1
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

define('PEACE_PROTOCOL_VERSION', '1.0.1');
define('PEACE_PROTOCOL_DIR', plugin_dir_path(__FILE__));
define('PEACE_PROTOCOL_URL', plugin_dir_url(__FILE__));

require_once PEACE_PROTOCOL_DIR . 'includes/register-cpt.php';
require_once PEACE_PROTOCOL_DIR . 'includes/rest-endpoints.php';
require_once PEACE_PROTOCOL_DIR . 'includes/shortcodes.php';
require_once PEACE_PROTOCOL_DIR . 'includes/frontend-button.php';
require_once PEACE_PROTOCOL_DIR . 'includes/admin-pages.php';

register_activation_hook(__FILE__, function () {
    if (!get_option('peace_tokens')) {
        update_option('peace_tokens', [wp_generate_password(32, true, true)]);
    }
    if (!get_option('peace_feeds')) {
        update_option('peace_feeds', []);
    }
    update_option('peace_tokens_last_rotation', current_time('mysql'));
    flush_rewrite_rules();
});

add_action('init', function () {
    if (get_option('peace_token')) {
        delete_option('peace_token');
    }
    load_plugin_textdomain('peace-protocol', false, dirname(plugin_basename(__FILE__)) . '/languages');
});
