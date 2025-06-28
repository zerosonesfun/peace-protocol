<?php
defined('ABSPATH') || exit;

// Register federated_peer role on plugin activation
add_action('init', function() {
    // Only add the role if it doesn't exist
    if (!get_role('federated_peer')) {
        $result = add_role('federated_peer', __('Federated Peer', 'peace-protocol'), array(
            'read' => true,
            'edit_posts' => false,
            'delete_posts' => false,
            'publish_posts' => false,
            'upload_files' => false,
            'edit_pages' => false,
            'edit_others_posts' => false,
            'edit_others_pages' => false,
            'delete_pages' => false,
            'delete_others_posts' => false,
            'delete_others_pages' => false,
            'manage_categories' => false,
            'moderate_comments' => false,
            'manage_links' => false,
            'import' => false,
            'unfiltered_html' => false,
            'edit_dashboard' => false,
            'update_plugins' => false,
            'delete_plugins' => false,
            'install_plugins' => false,
            'update_themes' => false,
            'install_themes' => false,
            'update_core' => false,
            'list_users' => false,
            'remove_users' => false,
            'add_users' => false,
            'promote_users' => false,
            'edit_theme_options' => false,
            'delete_themes' => false,
            'export' => false,
            'manage_network' => false,
            'manage_sites' => false,
            'manage_network_users' => false,
            'manage_network_themes' => false,
            'manage_network_options' => false,
            'upgrade_network' => false,
            'setup_network' => false,
            'activate_plugins' => false,
            'deactivate_plugins' => false,
            'edit_plugins' => false,
            'edit_themes' => false,
            'edit_files' => false,
            'edit_users' => false,
            'create_users' => false,
            'delete_users' => false,
            'unfiltered_upload' => false,
            'edit_private_posts' => false,
            'read_private_posts' => false,
            'edit_private_pages' => false,
            'read_private_pages' => false,
            'edit_published_posts' => false,
            'publish_posts' => false,
            'delete_published_posts' => false,
            'edit_published_pages' => false,
            'publish_pages' => false,
            'delete_published_pages' => false,
            'delete_private_pages' => false,
            'delete_private_posts' => false,
            'delete_posts' => false,
            'delete_pages' => false,
            'delete_others_pages' => false,
            'delete_others_posts' => false,
            'edit_others_pages' => false,
            'edit_others_posts' => false,
            'edit_pages' => false,
            'edit_posts' => false,
            'read' => true,
        ));
        
        if (is_wp_error($result)) {
            error_log('Peace Protocol: Failed to create federated_peer role: ' . $result->get_error_message());
        }
    }
});

// Block admin access for federated users
add_action('admin_init', function() {
    if (is_user_logged_in() && current_user_can('federated_peer') && !current_user_can('manage_options')) {
        wp_die(__('Access denied. Federated users cannot access the admin area.', 'peace-protocol'), 403);
    }
});

// Block admin bar for federated users
add_action('after_setup_theme', function() {
    if (is_user_logged_in() && current_user_can('federated_peer') && !current_user_can('manage_options')) {
        show_admin_bar(false);
    }
});

// Create or get federated user for a site
function peace_protocol_get_or_create_federated_user($site_url) {
    error_log('Peace Protocol: get_or_create_federated_user called with site_url: ' . $site_url);
    
    // Sanitize site URL to create username
    $site_domain = parse_url($site_url, PHP_URL_HOST);
    if (!$site_domain) {
        error_log('Peace Protocol: Failed to parse domain from site_url: ' . $site_url);
        return false;
    }
    
    error_log('Peace Protocol: Parsed domain: ' . $site_domain);
    
    // Create username from domain (replace dots with underscores)
    $username = 'federated_' . str_replace('.', '_', $site_domain);
    error_log('Peace Protocol: Generated username: ' . $username);
    
    // Check if user already exists
    $user = get_user_by('login', $username);
    
    if (!$user) {
        error_log('Peace Protocol: User does not exist, creating new federated user');
        
        // Create new federated user
        $user_id = wp_create_user($username, wp_generate_password(64, true, true), $username . '@' . $site_domain);
        
        if (is_wp_error($user_id)) {
            error_log('Peace Protocol: Failed to create federated user: ' . $user_id->get_error_message());
            return false;
        }
        
        error_log('Peace Protocol: Created user with ID: ' . $user_id);
        
        // Set role to federated_peer
        $user = new WP_User($user_id);
        $user->set_role('federated_peer');
        
        // Set display name to site domain
        $update_result = wp_update_user(array(
            'ID' => $user_id,
            'display_name' => $site_domain,
            'first_name' => $site_domain,
            'last_name' => '(' . $site_url . ')'
        ));
        
        if (is_wp_error($update_result)) {
            error_log('Peace Protocol: Failed to update user display name: ' . $update_result->get_error_message());
        }
        
        error_log('Peace Protocol: Created federated user: ' . $username . ' for site: ' . $site_url);
    } else {
        error_log('Peace Protocol: Found existing federated user: ' . $username);
    }
    
    return $user;
}

// Log in federated user
function peace_protocol_login_federated_user($site_url) {
    error_log('Peace Protocol: login_federated_user called with site_url: ' . $site_url);
    
    $user = peace_protocol_get_or_create_federated_user($site_url);
    
    if (!$user) {
        error_log('Peace Protocol: Failed to get or create federated user for site: ' . $site_url);
        return false;
    }
    
    error_log('Peace Protocol: Got user object: ' . $user->user_login . ' (ID: ' . $user->ID . ')');
    
    // Log in the user
    wp_set_current_user($user->ID);
    wp_set_auth_cookie($user->ID, true);
    
    error_log('Peace Protocol: Logged in federated user: ' . $user->user_login . ' for site: ' . $site_url);
    return $user;
}

// Handle federated authorization code return
function peace_protocol_handle_federated_auth_return($auth_code, $federated_site, $state) {
    error_log('Peace Protocol: Handling federated auth return - code: ' . $auth_code . ', site: ' . $federated_site . ', state: ' . $state);
    
    // Validate authorization code
    $authorizations = get_option('peace_protocol_authorizations', array());
    error_log('Peace Protocol: All authorizations: ' . print_r($authorizations, true));
    
    if (!isset($authorizations[$auth_code])) {
        error_log('Peace Protocol: Invalid authorization code: ' . $auth_code);
        error_log('Peace Protocol: Available codes: ' . implode(', ', array_keys($authorizations)));
        return false;
    }
    
    $auth_data = $authorizations[$auth_code];
    error_log('Peace Protocol: Authorization data: ' . print_r($auth_data, true));
    
    // Check if code is expired
    if ($auth_data['expires'] < time()) {
        error_log('Peace Protocol: Authorization code expired: ' . $auth_code . ' (expires: ' . $auth_data['expires'] . ', current time: ' . time() . ')');
        unset($authorizations[$auth_code]);
        update_option('peace_protocol_authorizations', $authorizations);
        return false;
    }
    
    // Check if code was already used
    if ($auth_data['used']) {
        error_log('Peace Protocol: Authorization code already used: ' . $auth_code);
        return false;
    }
    
    error_log('Peace Protocol: Authorization code is valid, marking as used');
    
    // Mark code as used
    $authorizations[$auth_code]['used'] = true;
    update_option('peace_protocol_authorizations', $authorizations);
    
    // The federated_site is the site that generated the code (SiteA)
    // We want to create a federated user representing SiteA on the current site (SiteB)
    // This is because the user is coming from SiteA to SiteB
    error_log('Peace Protocol: Creating federated user representing site: ' . $federated_site);
    
    // Log in federated user representing the federated site (SiteA)
    $user = peace_protocol_login_federated_user($federated_site);
    
    if ($user) {
        error_log('Peace Protocol: Successfully logged in federated user for site: ' . $federated_site);
        return $user;
    } else {
        error_log('Peace Protocol: Failed to log in federated user for site: ' . $federated_site);
        return false;
    }
}

// Add federated user info to comment form
add_filter('comment_form_defaults', function($defaults) {
    if (is_user_logged_in() && current_user_can('federated_peer') && !current_user_can('manage_options')) {
        $current_user = wp_get_current_user();
        $defaults['title_reply'] = sprintf(__('Logged in as %s', 'peace-protocol'), $current_user->display_name);
    }
    return $defaults;
});

// Modify comment author for federated users
add_filter('get_comment_author', function($author, $comment_id, $comment) {
    if ($comment && $comment->user_id) {
        $user = get_user_by('id', $comment->user_id);
        if ($user && $user->has_cap('federated_peer') && !$user->has_cap('manage_options')) {
            $federated_site = get_user_meta($user->ID, 'federated_site', true);
            if ($federated_site) {
                return $user->display_name . ' (' . $federated_site . ')';
            } else {
                return $user->display_name . ' (Federated)';
            }
        }
    }
    return $author;
}, 10, 3); 