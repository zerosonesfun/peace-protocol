<?php
defined('WP_UNINSTALL_PLUGIN') || exit;

// Delete current options (with new prefix)
delete_option('peaceprotocol_tokens');
delete_option('peaceprotocol_authorizations');
delete_option('peaceprotocol_federated_identities');
delete_option('peaceprotocol_hide_button');
delete_option('peaceprotocol_feeds');
delete_option('peaceprotocol_tokens_last_rotation');
delete_option('peaceprotocol_last_sent');
delete_option('peaceprotocol_hide_auto_button');
delete_option('peaceprotocol_button_position');
delete_option('peaceprotocol_federated_codes');
delete_option('peaceprotocol_banned_users');
delete_option('peaceprotocol_ban_reasons');
delete_option('peaceprotocol_codes');
delete_option('peaceprotocol_version');
delete_option('peaceprotocol_js_version');
delete_option('peaceprotocol_token');
delete_option('peaceprotocol_indieauth_requests');
delete_option('peaceprotocol_indieauth_codes');
delete_option('peaceprotocol_indieauth_access_tokens');

// Clean up old options (with old prefixes) that might still exist
delete_option('peace_tokens');
delete_option('peace_authorizations');
delete_option('peace_federated_identities');
delete_option('peace_hide_button');
delete_option('peace_feeds');
delete_option('peace_tokens_last_rotation');
delete_option('peace_last_sent');
delete_option('peace_hide_auto_button');
delete_option('peace_button_position');
delete_option('peace_federated_codes');
delete_option('peace_banned_users');
delete_option('peace_ban_reasons');
delete_option('peace_codes');
delete_option('peace_version');
delete_option('peace_js_version');
delete_option('peace_token');
delete_option('peace_indieauth_requests');
delete_option('peace_indieauth_codes');
delete_option('peace_indieauth_access_tokens');

// Clean up old options with peace_protocol_ prefix
delete_option('peace_protocol_tokens');
delete_option('peace_protocol_authorizations');
delete_option('peace_protocol_federated_identities');
delete_option('peace_protocol_hide_button');
delete_option('peace_protocol_feeds');
delete_option('peace_protocol_tokens_last_rotation');
delete_option('peace_protocol_last_sent');
delete_option('peace_protocol_hide_auto_button');
delete_option('peace_protocol_button_position');
delete_option('peace_protocol_federated_codes');
delete_option('peace_protocol_banned_users');
delete_option('peace_protocol_ban_reasons');
delete_option('peace_protocol_codes');
delete_option('peace_protocol_version');
delete_option('peace_protocol_js_version');
delete_option('peace_protocol_token');
delete_option('peace_protocol_indieauth_requests');
delete_option('peace_protocol_indieauth_codes');
delete_option('peace_protocol_indieauth_access_tokens');

// Clean up federated users
$federated_users = get_users(array('role' => 'federated_peer'));
foreach ($federated_users as $user) {
    wp_delete_user($user->ID);
}

// Remove the federated_peer role
remove_role('federated_peer');

$logs = get_posts([
    'post_type' => 'peaceprotocol_log',
    'numberposts' => -1,
    'post_status' => 'any',
    'fields' => 'ids',
]);

foreach ($logs as $log_id) {
    wp_delete_post($log_id, true);
}
