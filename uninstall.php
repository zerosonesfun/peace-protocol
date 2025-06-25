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
delete_option('peace_federated_codes');

$logs = get_posts([
    'post_type' => 'peace_log',
    'numberposts' => -1,
    'post_status' => 'any',
    'fields' => 'ids',
]);

foreach ($logs as $log_id) {
    wp_delete_post($log_id, true);
}
