<?php
defined('ABSPATH') || exit;

// Register AJAX actions
add_action('wp_ajax_peace_protocol_ban_user', 'peace_protocol_ban_user_callback');

// Add ban action link to users list
add_filter('user_row_actions', function($actions, $user) {
    // Only show ban/unban for administrators
    if (!current_user_can('manage_options')) {
        return $actions;
    }
    
    // Don't allow banning yourself
    if ($user->ID === get_current_user_id()) {
        return $actions;
    }
    
    $banned_users = get_option('peace_protocol_banned_users', array());
    $is_banned = in_array($user->ID, $banned_users);
    
    if ($is_banned) {
        $actions['peace_unban'] = '<a href="#" class="peace-unban-user" data-user-id="' . esc_attr($user->ID) . '" data-user-name="' . esc_attr($user->display_name) . '">' . __('Unban', 'peace-protocol') . '</a>';
    } else {
        $actions['peace_ban'] = '<a href="#" class="peace-ban-user" data-user-id="' . esc_attr($user->ID) . '" data-user-name="' . esc_attr($user->display_name) . '">' . __('Ban', 'peace-protocol') . '</a>';
    }
    
    return $actions;
}, 10, 2);

// Add modal HTML to admin footer
add_action('admin_footer-users.php', function() {
    if (!current_user_can('manage_options')) {
        return;
    }
    ?>
    <div id="peace-ban-modal-bg" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); z-index:100001; align-items:center; justify-content:center;">
        <div id="peace-ban-modal" style="background:#fff; border-radius:8px; padding:2em; max-width:500px; margin:auto; text-align:center;">
            <h3 id="peace-ban-modal-title"><?php esc_html_e('Ban User', 'peace-protocol'); ?></h3>
            <p id="peace-ban-modal-user"></p>
            <form method="post" id="peace-ban-form">
                <input type="hidden" name="peace_ban_user_id" id="peace-ban-user-id" value="" />
                <input type="hidden" name="peace_ban_action" id="peace-ban-action" value="" />
                <div style="margin: 1em 0;">
                    <label for="peace-ban-reason" style="display:block; text-align:left; margin-bottom:0.5em; font-weight:bold;"><?php esc_html_e('Reason for ban:', 'peace-protocol'); ?></label>
                    <textarea id="peace-ban-reason" name="peace_ban_reason" rows="4" style="width:100%; padding:0.5em; border:1px solid #ddd; border-radius:4px; font-family:inherit;" placeholder="<?php esc_attr_e('Please provide a reason for banning this user...', 'peace-protocol'); ?>"></textarea>
                </div>
                <button type="submit" class="button button-primary" id="peace-ban-submit"><?php esc_html_e('Ban User', 'peace-protocol'); ?></button>
                <button type="button" class="button" id="peace-ban-cancel"><?php esc_html_e('Cancel', 'peace-protocol'); ?></button>
            </form>
        </div>
    </div>
    
    <style>
    #peace-ban-modal-bg { display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); z-index:100001; align-items:center; justify-content:center; }
    #peace-ban-modal { background:#fff; border-radius:8px; padding:2em; max-width:500px; margin:auto; text-align:center; }
    #peace-ban-modal button {
        margin-top: 0.5rem;
        margin-right: 0.5rem;
    }
    #peace-ban-modal .button {
        display: inline-block;
        padding: 0.5em 1.2em;
        font-size: 1em;
        border-radius: 4px;
        border: none;
        background: #f3f4f6;
        color: #222;
        cursor: pointer;
        transition: background 0.2s;
        box-shadow: 0 1px 2px rgba(0,0,0,0.03);
    }
    #peace-ban-modal .button-primary {
        background: #dc3232;
        color: #fff;
    }
    #peace-ban-modal .button:hover, #peace-ban-modal .button-primary:hover {
        background: #b91c1c;
        color: #fff;
    }
    @media (prefers-color-scheme: dark) {
        #peace-ban-modal {
            background: #222;
            color: #eee;
        }
        #peace-ban-modal textarea {
            background: #333;
            color: #eee;
            border-color: #555;
        }
    }
    </style>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var banModalBg = document.getElementById('peace-ban-modal-bg');
        var banModalTitle = document.getElementById('peace-ban-modal-title');
        var banModalUser = document.getElementById('peace-ban-modal-user');
        var banUserId = document.getElementById('peace-ban-user-id');
        var banAction = document.getElementById('peace-ban-action');
        var banReason = document.getElementById('peace-ban-reason');
        var banSubmit = document.getElementById('peace-ban-submit');
        var banCancel = document.getElementById('peace-ban-cancel');
        var banForm = document.getElementById('peace-ban-form');
        
        // Ban user
        document.querySelectorAll('.peace-ban-user').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                var userId = this.getAttribute('data-user-id');
                var userName = this.getAttribute('data-user-name');
                
                banModalTitle.textContent = '<?php esc_html_e('Ban User', 'peace-protocol'); ?>';
                banModalUser.textContent = '<?php esc_html_e('Ban user:', 'peace-protocol'); ?> ' + userName;
                banUserId.value = userId;
                banAction.value = 'ban';
                banReason.value = '';
                banSubmit.textContent = '<?php esc_html_e('Ban User', 'peace-protocol'); ?>';
                banSubmit.className = 'button button-primary';
                banModalBg.style.display = 'flex';
            });
        });
        
        // Unban user
        document.querySelectorAll('.peace-unban-user').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                var userId = this.getAttribute('data-user-id');
                var userName = this.getAttribute('data-user-name');
                
                banModalTitle.textContent = '<?php esc_html_e('Unban User', 'peace-protocol'); ?>';
                banModalUser.textContent = '<?php esc_html_e('Unban user:', 'peace-protocol'); ?> ' + userName;
                banUserId.value = userId;
                banAction.value = 'unban';
                banReason.value = '';
                banSubmit.textContent = '<?php esc_html_e('Unban User', 'peace-protocol'); ?>';
                banSubmit.className = 'button button-primary';
                banModalBg.style.display = 'flex';
            });
        });
        
        banCancel.addEventListener('click', function() {
            banModalBg.style.display = 'none';
        });
        
        banForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            var formData = new FormData();
            formData.append('action', 'peace_protocol_ban_user');
            formData.append('user_id', banUserId.value);
            formData.append('ban_action', banAction.value);
            formData.append('reason', banReason.value);
            formData.append('nonce', '<?php echo wp_create_nonce('peace_protocol_ban_user'); ?>');
            
            fetch(ajaxurl, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error: ' + (data.data || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error processing request. Please try again.');
            });
        });
    });
    </script>
    <?php
});

// Handle banned user page - SIMPLE VERSION
add_action('init', function() {
    if (isset($_GET['peace_banned']) && $_GET['peace_banned'] === '1') {
        // Output a simple banned page and exit
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Access Denied</title>
    <style>
        body { 
            background: #000; 
            color: #fff; 
            font-family: "Courier New", monospace; 
            margin: 0; 
            padding: 0; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            min-height: 100vh; 
            text-align: center;
            font-size: 16px;
            line-height: 1.6;
        }
        .banned-message { 
            max-width: 600px; 
            padding: 2rem; 
            line-height: 1.6;
        }
        .banned-message h1 { 
            color: #ff0000; 
            margin-bottom: 1rem;
            font-size: 2rem;
            font-weight: bold;
        }
        .banned-message p { 
            margin-bottom: 1rem;
            font-size: 1.1rem;
        }
        .banned-reason { 
            background: #333; 
            padding: 1rem; 
            border-radius: 4px; 
            margin-top: 1rem;
            border-left: 4px solid #ff0000;
        }
    </style>
</head>
<body>
    <div class="banned-message">
        <h1>🚫 ACCESS DENIED</h1>
        <p>You are banned from accessing this site.</p>';
        
        // Get ban reason if available - try to get user ID from URL first
        $user_id = null;
        if (isset($_GET['user_id']) && is_numeric($_GET['user_id'])) {
            $user_id = intval($_GET['user_id']);
        } else {
            // Fallback to current user if they\'re still logged in
            $current_user = wp_get_current_user();
            if ($current_user->exists()) {
                $user_id = $current_user->ID;
            }
        }
        
        if ($user_id) {
            $ban_reasons = get_option('peace_protocol_ban_reasons', array());
            $reason = $ban_reasons[$user_id] ?? '';
            if ($reason) {
                echo '<div class="banned-reason">';
                echo '<strong>Reason:</strong> ' . esc_html($reason);
                echo '</div>';
            }
        }
        
        echo '<p>If you believe this is an error, please contact the site administrator.</p>
    </div>
    
    <script>
    // Clear all Peace Protocol data from localStorage when banned
    (function() {
        // console.log("[Peace Protocol] User is banned - clearing all Peace Protocol data");
        
        // Clear Peace Protocol identities
        localStorage.removeItem("peace-protocol-identities");
        
        // Clear any other Peace Protocol related data
        const keysToRemove = [];
        for (let i = 0; i < localStorage.length; i++) {
            const key = localStorage.key(i);
            if (key && key.includes("peace")) {
                keysToRemove.push(key);
            }
        }
        keysToRemove.forEach(key => localStorage.removeItem(key));
        
        // Set a flag to indicate this user was banned
        localStorage.setItem("peace-protocol-banned", "true");
        
        // Override any Peace Protocol functions to prevent usage
        if (typeof window !== "undefined") {
            // Block the send peace modal function
            window.peaceProtocolShowSendPeaceModal = function() {
                alert("You are banned from sending peace.");
                return false;
            };
            
            // Block federated login
            window.peaceProtocolFederatedLogin = function() {
                alert("You are banned from using Peace Protocol features.");
                return false;
            };
            
            // Block any other Peace Protocol functions
            window.peaceProtocolShowFederatedModal = function() {
                alert("You are banned from using Peace Protocol features.");
                return false;
            };
            
            // console.log("[Peace Protocol] All Peace Protocol functions blocked for banned user");
        }
    })();
    </script>
</body>
</html>';
        exit;
    }
});

// Handle ban/unban AJAX request
function peace_protocol_ban_user_callback() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized', 403);
    }
    
    if (!isset($_POST['user_id']) || !isset($_POST['ban_action']) || !isset($_POST['nonce'])) {
        wp_send_json_error('Missing required parameters', 400);
    }
    
    $nonce = sanitize_text_field(wp_unslash($_POST['nonce']));
    if (!wp_verify_nonce($nonce, 'peace_protocol_ban_user')) {
        wp_send_json_error('Invalid nonce', 400);
    }
    
    $user_id = intval($_POST['user_id']);
    $ban_action = sanitize_text_field(wp_unslash($_POST['ban_action']));
    $reason = sanitize_textarea_field(wp_unslash($_POST['reason'] ?? ''));
    
    $user = get_user_by('id', $user_id);
    if (!$user) {
        wp_send_json_error('User not found', 404);
    }
    
    // Don't allow banning yourself
    if ($user_id === get_current_user_id()) {
        wp_send_json_error('You cannot ban yourself', 400);
    }
    
    $banned_users = get_option('peace_protocol_banned_users', array());
    $ban_reasons = get_option('peace_protocol_ban_reasons', array());
    
    if ($ban_action === 'ban') {
        if (!in_array($user_id, $banned_users)) {
            $banned_users[] = $user_id;
            $ban_reasons[$user_id] = $reason;
            update_option('peace_protocol_banned_users', $banned_users);
            update_option('peace_protocol_ban_reasons', $ban_reasons);
            
            wp_send_json_success('User banned successfully');
        } else {
            wp_send_json_error('User is already banned', 400);
        }
    } elseif ($ban_action === 'unban') {
        if (in_array($user_id, $banned_users)) {
            $banned_users = array_diff($banned_users, array($user_id));
            unset($ban_reasons[$user_id]);
            update_option('peace_protocol_banned_users', array_values($banned_users));
            update_option('peace_protocol_ban_reasons', $ban_reasons);
            
            wp_send_json_success('User unbanned successfully');
        } else {
            wp_send_json_error('User is not banned', 400);
        }
    } else {
        wp_send_json_error('Invalid action', 400);
    }
}

// Check for banned users on every page load
add_action('template_redirect', function() {
    if (is_user_logged_in()) {
        $current_user = wp_get_current_user();
        $banned_users = get_option('peace_protocol_banned_users', array());
        
        if (in_array($current_user->ID, $banned_users)) {
            // Log out the user
            wp_logout();
            
            // Redirect to banned page with user ID so we can show the reason
            wp_redirect(home_url('/?peace_banned=1&user_id=' . $current_user->ID));
            exit;
        } else {
            // User is not banned, clear any ban flag from localStorage
            add_action('wp_footer', function() {
                echo '<script>
                // Clear ban flag if user is no longer banned
                if (typeof localStorage !== "undefined" && localStorage.getItem("peace-protocol-banned") === "true") {
                    localStorage.removeItem("peace-protocol-banned");
                    // console.log("[Peace Protocol] Ban flag cleared - user is no longer banned");
                    
                    // Restore Peace Protocol functions if they were overridden
                    if (typeof window !== "undefined") {
                        // Check if the functions were overridden by the ban logic
                        if (window.peaceProtocolFederatedLogin && window.peaceProtocolFederatedLogin.toString().includes("You are banned")) {
                            // console.log("[Peace Protocol] Restoring Peace Protocol functions after ban flag cleared");
                            // The functions will be restored when the main frontend.js loads
                            // We just need to clear the ban flag so the main functions can take over
                        }
                    }
                }
                </script>';
            });
        }
    }
});

// Add ban status to user profile
add_action('show_user_profile', function($user) {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    $banned_users = get_option('peace_protocol_banned_users', array());
    $ban_reasons = get_option('peace_protocol_ban_reasons', array());
    $is_banned = in_array($user->ID, $banned_users);
    $ban_reason = $ban_reasons[$user->ID] ?? '';
    
    echo '<h3>' . esc_html__('Peace Protocol Ban Status', 'peace-protocol') . '</h3>';
    echo '<table class="form-table">';
    echo '<tr>';
    echo '<th scope="row">' . esc_html__('Ban Status', 'peace-protocol') . '</th>';
    echo '<td>';
    if ($is_banned) {
        echo '<span style="color: #dc3232; font-weight: bold;">' . esc_html__('BANNED', 'peace-protocol') . '</span>';
        if ($ban_reason) {
            echo '<br><small style="color: #666;">' . esc_html__('Reason:', 'peace-protocol') . ' ' . esc_html($ban_reason) . '</small>';
        }
    } else {
        echo '<span style="color: #46b450;">' . esc_html__('Not banned', 'peace-protocol') . '</span>';
    }
    echo '</td>';
    echo '</tr>';
    echo '</table>';
});

// Add ban status to edit user profile
add_action('edit_user_profile', function($user) {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    $banned_users = get_option('peace_protocol_banned_users', array());
    $ban_reasons = get_option('peace_protocol_ban_reasons', array());
    $is_banned = in_array($user->ID, $banned_users);
    $ban_reason = $ban_reasons[$user->ID] ?? '';
    
    echo '<h3>' . esc_html__('Peace Protocol Ban Status', 'peace-protocol') . '</h3>';
    echo '<table class="form-table">';
    echo '<tr>';
    echo '<th scope="row">' . esc_html__('Ban Status', 'peace-protocol') . '</th>';
    echo '<td>';
    if ($is_banned) {
        echo '<span style="color: #dc3232; font-weight: bold;">' . esc_html__('BANNED', 'peace-protocol') . '</span>';
        if ($ban_reason) {
            echo '<br><small style="color: #666;">' . esc_html__('Reason:', 'peace-protocol') . ' ' . esc_html($ban_reason) . '</small>';
        }
    } else {
        echo '<span style="color: #46b450;">' . esc_html__('Not banned', 'peace-protocol') . '</span>';
    }
    echo '</td>';
    echo '</tr>';
    echo '</table>';
});

// Add ban status column to users list
add_filter('manage_users_columns', function($columns) {
    $columns['peace_ban_status'] = __('Ban Status', 'peace-protocol');
    return $columns;
});

add_filter('manage_users_custom_column', function($value, $column_name, $user_id) {
    if ($column_name === 'peace_ban_status') {
        $banned_users = get_option('peace_protocol_banned_users', array());
        if (in_array($user_id, $banned_users)) {
            return '<span style="color: #dc3232; font-weight: bold;">' . esc_html__('BANNED', 'peace-protocol') . '</span>';
        } else {
            return '<span style="color: #46b450;">' . esc_html__('Active', 'peace-protocol') . '</span>';
        }
    }
    return $value;
}, 10, 3);

// Helper function to check if current user is banned
function peace_protocol_is_user_banned() {
    // Check if we're on the banned page
    if (isset($_GET['peace_banned']) && $_GET['peace_banned'] === '1') {
        return true;
    }
    
    // Check if user is logged in and banned
    if (is_user_logged_in()) {
        $current_user = wp_get_current_user();
        $banned_users = get_option('peace_protocol_banned_users', array());
        
        return in_array($current_user->ID, $banned_users);
    }
    
    return false;
}

// Helper function to check if a specific user is banned
function peace_protocol_is_user_id_banned($user_id) {
    $banned_users = get_option('peace_protocol_banned_users', array());
    return in_array($user_id, $banned_users);
}

// Block banned users from REST API endpoints
add_action('rest_api_init', function() {
    // Block banned users from all peace protocol endpoints
    add_filter('rest_pre_dispatch', function($result, $server, $request) {
        if (peace_protocol_is_user_banned()) {
            return new WP_Error('banned_user', 'You are banned from using this service', array('status' => 403));
        }
        return $result;
    }, 10, 3);
});

// Block banned users from AJAX endpoints
add_action('wp_ajax_peace_protocol_send_peace', function() {
    if (peace_protocol_is_user_banned()) {
        wp_send_json_error('You are banned from sending peace', 403);
    }
}, 1);

add_action('wp_ajax_peace_protocol_generate_code', function() {
    if (peace_protocol_is_user_banned()) {
        wp_send_json_error('You are banned from generating codes', 403);
    }
}, 1);

add_action('wp_ajax_peace_protocol_exchange_code', function() {
    if (peace_protocol_is_user_banned()) {
        wp_send_json_error('You are banned from exchanging codes', 403);
    }
}, 1);

add_action('wp_ajax_peace_protocol_federated_exchange', function() {
    if (peace_protocol_is_user_banned()) {
        wp_send_json_error('You are banned from federated exchange', 403);
    }
}, 1);

add_action('wp_ajax_peace_protocol_validate_token', function() {
    if (peace_protocol_is_user_banned()) {
        wp_send_json_error('You are banned from validating tokens', 403);
    }
}, 1);

add_action('wp_ajax_peace_protocol_federated_login', function() {
    if (peace_protocol_is_user_banned()) {
        wp_send_json_error('You are banned from federated login', 403);
    }
}, 1);

add_action('wp_ajax_peace_protocol_complete_auth', function() {
    if (peace_protocol_is_user_banned()) {
        wp_send_json_error('You are banned from completing authentication', 403);
    }
}, 1);

// Block banned users from frontend JavaScript
add_action('wp_footer', function() {
    if (peace_protocol_is_user_banned()) {
        echo '<script>
        // Block banned users from opening send peace modal
        document.addEventListener("DOMContentLoaded", function() {
            // Remove any existing peace protocol buttons
            var peaceButtons = document.querySelectorAll("[data-peace-protocol], .peace-protocol-button, [onclick*=\'peace\']");
            peaceButtons.forEach(function(btn) {
                btn.style.display = "none";
            });
            
            // Block any peace protocol modals
            var peaceModals = document.querySelectorAll(".peace-modal, [id*=\'peace\']");
            peaceModals.forEach(function(modal) {
                modal.style.display = "none";
            });
            
            // Override any peace protocol functions
            if (typeof window.openPeaceModal === "function") {
                window.openPeaceModal = function() {
                    alert("You are banned from using this feature.");
                    return false;
                };
            }
            
            if (typeof window.sendPeace === "function") {
                window.sendPeace = function() {
                    alert("You are banned from sending peace.");
                    return false;
                };
            }
        });
        </script>';
    }
}); 