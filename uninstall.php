<?php
defined('WP_UNINSTALL_PLUGIN') || exit;

delete_option('peace_tokens');
delete_option('peace_protocol_authorizations');
delete_option('peace_protocol_federated_identities');
delete_option('peace_protocol_hide_button');
delete_option('peace_feeds');
delete_option('peace_tokens_last_rotation');
delete_option('peace_last_sent');
delete_option('peace_hide_auto_button');
delete_option('peace_button_position');
delete_option('peace_federated_codes');
delete_option('peace_protocol_banned_users');
delete_option('peace_protocol_ban_reasons');
delete_option('peace_protocol_codes');
delete_option('peace_protocol_version');
delete_option('peace_protocol_js_version');
delete_option('peace_token');

// Clean up federated users
$federated_users = get_users(array('role' => 'federated_peer'));
foreach ($federated_users as $user) {
    wp_delete_user($user->ID);
}

// Remove the federated_peer role
remove_role('federated_peer');

$logs = get_posts([
    'post_type' => 'peace_log',
    'numberposts' => -1,
    'post_status' => 'any',
    'fields' => 'ids',
]);

foreach ($logs as $log_id) {
    wp_delete_post($log_id, true);
}
