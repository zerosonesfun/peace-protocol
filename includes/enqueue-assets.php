<?php
defined('ABSPATH') || exit;

// Enqueue frontend assets
add_action('wp_enqueue_scripts', function () {
    // Enhanced cache busting strategy
    $js_file = PEACEPROTOCOL_DIR . 'js/frontend.js';
    
    // Method 1: Use file modification time (most reliable for development)
    $file_version = file_exists($js_file) ? filemtime($js_file) : PEACEPROTOCOL_VERSION;
    
    // Method 2: Use plugin version (good for production releases)
    $plugin_version = PEACEPROTOCOL_VERSION;
    
    // Method 3: Use stored cache busting version from database
    $stored_version = get_option('peaceprotocol_js_version', $plugin_version);
    
    // Method 4: Combine all methods for maximum cache busting
    $js_version = $file_version . '.' . $stored_version;
    
    wp_enqueue_script('peace-protocol-frontend', PEACEPROTOCOL_URL . 'js/frontend.js', ['jquery'], $js_version, true);

    wp_localize_script('peace-protocol-frontend', 'peaceprotocolData', [
        'restUrl' => rest_url('peace-protocol/v1/receive'),
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('wp_rest'),
        'federatedLoginNonce' => wp_create_nonce('peaceprotocol_federated_login'),
        'i18n_confirm' => __('Do you want to give peace to this site?', 'peace-protocol'),
        'i18n_yes' => __('Yes', 'peace-protocol'),
        'i18n_no' => __('Cancel', 'peace-protocol'),
        'i18n_note' => __('Optional note (max 50 characters):', 'peace-protocol'),
        'i18n_send' => __('Send Peace', 'peace-protocol'),
        'i18n_cancel' => __('Cancel', 'peace-protocol'),
        'siteUrl' => get_site_url(),
        'version' => $js_version, // Add version to peaceprotocolData for debugging
        'debug' => [
            'fileVersion' => $file_version,
            'pluginVersion' => $plugin_version,
            'storedVersion' => $stored_version,
            'finalVersion' => $js_version,
            'fileExists' => file_exists($js_file),
            'fileModTime' => file_exists($js_file) ? filemtime($js_file) : 'N/A',
        ],
    ]);
    
    // Add script to clear ban flags and ensure proper function loading
    wp_add_inline_script('peace-protocol-frontend', '
    // Clear any ban flags that might be preventing Peace Protocol from working
    if (typeof localStorage !== "undefined" && localStorage.getItem("peace-protocol-banned") === "true") {
        // console.log("[Peace Protocol] Clearing ban flag that was preventing function loading");
        localStorage.removeItem("peace-protocol-banned");
    }
    
    // Ensure ajaxurl is globally available as fallback
    if (typeof window.ajaxurl === "undefined" && typeof peaceprotocolData !== "undefined" && peaceprotocolData.ajaxurl) {
        window.ajaxurl = peaceprotocolData.ajaxurl;
    }
    ', 'before');
});

// Enqueue admin assets
add_action('admin_enqueue_scripts', function ($hook) {
    // Only enqueue on our plugin page
    if ($hook !== 'settings_page_peace-protocol') {
        return;
    }
    
    wp_enqueue_script('peace-protocol-admin', PEACEPROTOCOL_URL . 'js/admin.js', ['jquery'], PEACEPROTOCOL_VERSION, true);
    
    // Get tokens and site data for localStorage
    $tokens = get_option('peaceprotocol_tokens', []);
    $site_url = get_site_url();
    
    wp_localize_script('peace-protocol-admin', 'peaceprotocolAdminData', [
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('peaceprotocol_admin'),
        'tokens' => $tokens,
        'site' => $site_url,
        'i18n_confirm_delete' => __('Are you sure you want to delete this token?', 'peace-protocol'),
        'i18n_confirm_unsub' => __('Are you sure you want to unsubscribe from this feed?', 'peace-protocol'),
    ]);
    
    // Add admin-specific inline script
    wp_add_inline_script('peace-protocol-admin', '
    // Always define ajaxurl globally, using wp_json_encode for bulletproof JS
    var ajaxurl = peaceprotocolAdminData.ajaxurl;
    ', 'before');
});

// Enqueue styles for frontend
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style('peace-protocol-frontend', PEACEPROTOCOL_URL . 'css/frontend.css', [], PEACEPROTOCOL_VERSION);
    
    // Add inline styles for dynamic positioning
    $button_position = get_option('peaceprotocol_button_position', 'top-right');
    $position_css = '';
    
    switch ($button_position) {
        case 'top-left':
            $position_css = 'top: 1rem; left: 1rem;';
            break;
        case 'bottom-left':
            $position_css = 'bottom: 1rem; left: 1rem;';
            break;
        case 'bottom-right':
            $position_css = 'bottom: 1rem; right: 1rem;';
            break;
        case 'top-right':
        default:
            $position_css = 'top: 1rem; right: 1rem;';
            break;
    }
    
    $admin_bar_adjustment = '';
    if (strpos($button_position, 'top') === 0) {
        $admin_bar_adjustment = '
        body.admin-bar #peace-protocol-button {
            top: 3rem;
        }';
    }
    
    wp_add_inline_style('peace-protocol-frontend', "
    #peace-protocol-button {
        position: fixed;
        {$position_css}
        background: transparent;
        border: none;
        font-size: 2rem;
        cursor: pointer;
        z-index: 99999;
    }
    {$admin_bar_adjustment}
    ");
});

// Enqueue styles for admin
add_action('admin_enqueue_scripts', function ($hook) {
    // Only enqueue on our plugin page
    if ($hook !== 'settings_page_peace-protocol') {
        return;
    }
    
    wp_enqueue_style('peace-protocol-admin', PEACEPROTOCOL_URL . 'css/admin.css', [], PEACEPROTOCOL_VERSION);
});

// Enqueue styles for user banning page
add_action('admin_enqueue_scripts', function ($hook) {
    // Enqueue on users.php page (where ban/unban actions are available)
    if ($hook !== 'users.php') {
        return;
    }
    
    wp_enqueue_script('peace-protocol-ban-users', PEACEPROTOCOL_URL . 'js/ban-users.js', ['jquery'], PEACEPROTOCOL_VERSION, true);
    
    wp_localize_script('peace-protocol-ban-users', 'peaceprotocolBanData', [
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('peaceprotocol_ban_user'),
        'i18n_ban_user' => __('Ban User', 'peace-protocol'),
        'i18n_unban_user' => __('Unban User', 'peace-protocol'),
        'i18n_ban_user_text' => __('Ban user:', 'peace-protocol'),
        'i18n_unban_user_text' => __('Unban user:', 'peace-protocol'),
    ]);
    
    wp_enqueue_style('peace-protocol-ban-users', PEACEPROTOCOL_URL . 'css/ban-users.css', [], PEACEPROTOCOL_VERSION);
});

// Enqueue styles for shortcode
add_action('wp_enqueue_scripts', function () {
    // Only enqueue if shortcode is used on the page
    global $post;
    if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'peaceprotocol_log_wall')) {
        wp_enqueue_style('peace-protocol-shortcode', PEACEPROTOCOL_URL . 'css/shortcode.css', [], PEACEPROTOCOL_VERSION);
    }
}); 