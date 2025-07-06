<?php
defined('ABSPATH') || exit;

add_shortcode('peace_log_wall', function () {
    $logs = get_posts([
        'post_type' => 'peace_log',
        'posts_per_page' => 10,
        'post_status' => 'publish'
    ]);

    if (!$logs) return '<p>No peace logs yet.</p>';

    $output = '<style>.peace-log-wall{padding-left:1.3em;max-width:600px;margin:1.5em auto;list-style:disc;}.peace-log-wall li{margin-bottom:1.1em;line-height:1.5;word-break:break-word;}.peace-log-wall strong{color:#2563eb;}@media(max-width:600px){.peace-log-wall{padding-left:1em;max-width:98vw;}}</style>';
    $output .= '<ul class="peace-log-wall">';
    foreach ($logs as $log) {
        $from = get_post_meta($log->ID, 'from_site', true);
        $note = get_post_meta($log->ID, 'note', true);
        $output .= '<li><strong>' . esc_html($from) . '</strong>: ' . esc_html($note) . '</li>';
    }
    $output .= '</ul>';
    return $output;
});

// Reusable function for peace hand button/modal markup
if (!function_exists('peace_protocol_render_hand_button')) {
    function peace_protocol_render_hand_button() {
        // Get the button position setting
        $button_position = get_option('peace_button_position', 'top-right');
        
        // Generate CSS based on position
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
        
        // Since output buffering is already active, let's just output directly
        ?>
        <style>
            #peace-protocol-button {
                position: fixed;
                <?php echo esc_html($position_css); ?>
                background: transparent;
                border: none;
                font-size: 2rem;
                cursor: pointer;
                z-index: 99999;
            }
            body.admin-bar #peace-protocol-button {
                <?php 
                if (strpos($button_position, 'top') === 0) {
                    echo 'top: 3rem;';
                }
                ?>
            }
            #peace-modal {
                display: none;
                position: fixed;
                top: 0; left: 0; right: 0; bottom: 0;
                background: rgba(0,0,0,0.75);
                z-index: 100000;
                align-items: center;
                justify-content: center;
            }
            #peace-modal-content {
                background: #fff;
                max-width: 400px;
                padding: 1rem;
                border-radius: 0.5rem;
                color: #333;
            }
            @media (prefers-color-scheme: dark) {
                #peace-modal-content {
                    background: #222;
                    color: #eee;
                }
            }
            #peace-modal textarea {
                width: 100%;
                max-width: 100%;
                height: 3rem;
                margin-top: 0.5rem;
                resize: none;
            }
            #peace-modal button {
                margin-top: 0.5rem;
                margin-right: 0.5rem;
            }
            /* Fallback for .button if theme does not style it */
            #peace-modal .button {
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
            #peace-modal .button-primary {
                background: #2563eb;
                color: #fff;
            }
            #peace-modal .button:hover, #peace-modal .button-primary:hover {
                background: #1e40af;
                color: #fff;
            }
            #peace-success-modal {
                display: none;
                position: fixed;
                top: 0; left: 0; right: 0; bottom: 0;
                background: rgba(0,0,0,0.75);
                z-index: 100000;
                align-items: center;
                justify-content: center;
            }
            #peace-success-modal button {
                margin-top: 0.5rem;
                margin-right: 0.5rem;
            }
            /* Fallback for .button if theme does not style it - for success modal */
            #peace-success-modal .button {
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
            #peace-success-modal .button-primary {
                background: #2563eb;
                color: #fff;
            }
            #peace-success-modal .button:hover, #peace-success-modal .button-primary:hover {
                background: #1e40af;
                color: #fff;
            }
            #peace-redirect-modal button {
                margin-top: 1rem;
            }
            /* Fallback for .button if theme does not style it - for redirect modal */
            #peace-redirect-modal .button {
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
            #peace-redirect-modal .button-primary {
                background: #2563eb;
                color: #fff;
            }
            #peace-redirect-modal .button:hover, #peace-redirect-modal .button-primary:hover {
                background: #1e40af;
                color: #fff;
            }
            /* Fallback for .button if theme does not style it - for federated modal */
            #peace-federated-modal .button {
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
            #peace-federated-modal .button-primary {
                background: #2563eb;
                color: #fff;
            }
            #peace-federated-modal .button:hover, #peace-federated-modal .button-primary:hover {
                background: #1e40af;
                color: #fff;
            }
            /* Default styling for federated modal content */
            #peace-federated-modal-content {
                background: #fff;
                color: #333;
            }
            /* Dark mode support for federated modal */
            @media (prefers-color-scheme: dark) {
                #peace-federated-modal-content {
                    background: #222;
                    color: #eee;
                }
            }
            /* Dark mode support for redirect modal */
            @media (prefers-color-scheme: dark) {
                #peace-redirect-modal-content {
                    background: #222;
                    color: #eee;
                }
            }
        </style>
        <button id="peace-protocol-button" title="<?php esc_attr_e('Give Peace ✌️', 'peace-protocol'); ?>" style="position: fixed; <?php echo esc_html($position_css); ?> background: transparent; border: none; font-size: 2rem; cursor: pointer; z-index: 99999;">✌️</button>
        <?php
        echo '<!-- Peace Protocol: Button HTML output -->';
        ?>
        <div id="peace-modal" role="dialog" aria-modal="true" aria-labelledby="peace-modal-title">
            <div id="peace-modal-content">
                <h2 id="peace-modal-title"><?php esc_html_e('Give Peace?', 'peace-protocol'); ?></h2>
                <p id="peace-modal-question"><?php esc_html_e('Do you want to give peace to this site?', 'peace-protocol'); ?></p>
                <textarea id="peace-note" maxlength="50" placeholder="<?php esc_attr_e('Optional note (max 50 characters)', 'peace-protocol'); ?>"></textarea>
                <div id="peace-note-counter" style="font-size:0.95em;color:#666;text-align:right;margin-bottom:0.3em;">0/50</div>
                <div style="display:flex;align-items:center;justify-content:space-between;gap:0.5em;flex-wrap:wrap;">
                    <div id="peace-sending-as" style="font-size:0.95em;color:#666;flex:1 1 100%;margin-bottom:0.2em;text-align:left;"></div>
                    <button id="peace-send" class="button button-primary"><?php esc_html_e('Send Peace', 'peace-protocol'); ?></button>
                    <button id="peace-cancel" class="button"><?php esc_html_e('Cancel', 'peace-protocol'); ?></button>
                </div>
                <div style="margin-top:0.7em;text-align:right;">
                    <a href="#" id="peace-switch-site-link" style="font-size:0.97em;color:#2563eb;text-decoration:underline;cursor:pointer;">
                        <?php /* esc_html_e('', 'peace-protocol'); */ ?>
                    </a>
                </div>
            </div>
        </div>
        <?php
        echo '<!-- Peace Protocol: Modal HTML output -->';
        ?>
        <div id="peace-success-modal" role="dialog" aria-modal="true" aria-labelledby="peace-success-title" style="display:none;">
            <div id="peace-modal-content">
                <h2 id="peace-success-title"><?php esc_html_e('Peace Sent!', 'peace-protocol'); ?></h2>
                <p id="peace-success-message">✌️ <?php esc_html_e('Peace sent successfully!', 'peace-protocol'); ?></p>
                <div>
                    <button id="peace-success-ok" class="button button-primary">OK</button>
                </div>
            </div>
        </div>
        <?php
        echo '<!-- Peace Protocol: Success modal HTML output -->';
        ?>
        <!-- Initial choice modal -->
        <div id="peace-choice-modal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.75);z-index:100001;align-items:center;justify-content:center;">
          <div id="peace-choice-modal-content" style="background:#fff;max-width:400px;padding:1.5rem;border-radius:0.5rem;color:#333;text-align:center;">
            <h2><?php esc_html_e('Choose Login Method', 'peace-protocol'); ?></h2>
            <p><?php esc_html_e('How would you like to authenticate?', 'peace-protocol'); ?></p>
            <div style="display:flex;flex-direction:column;gap:0.8em;margin:1.5em 0;">
              <button id="peace-protocol-login-btn" class="button button-primary" style="padding:0.8em;font-size:1em;">
                <?php esc_html_e('Login with Peace Protocol', 'peace-protocol'); ?>
              </button>
              <button id="peace-indieauth-login-btn" class="button button-primary" style="padding:0.8em;font-size:1em;">
                <?php esc_html_e('Login with IndieAuth', 'peace-protocol'); ?>
              </button>
              <button id="peace-what-is-btn" class="button" style="padding:0.8em;font-size:1em;">
                <?php esc_html_e('What\'s this?', 'peace-protocol'); ?>
              </button>
            </div>
            <button id="peace-choice-cancel" class="button">Cancel</button>
          </div>
        </div>
        
        <style>
        /* Button styling for new modals */
        #peace-choice-modal .button,
        #peace-info-modal .button {
            display: inline-block;
            padding: 8px 16px;
            margin: 4px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: #f8f9fa;
            color: #333;
            text-decoration: none;
            cursor: pointer;
            font-size: 14px;
            line-height: 1.4;
            transition: all 0.2s ease;
        }
        
        #peace-choice-modal .button:hover,
        #peace-info-modal .button:hover {
            background: #e9ecef;
            border-color: #adb5bd;
        }
        
        #peace-choice-modal .button-primary,
        #peace-info-modal .button-primary {
            background: #0073aa;
            border-color: #0073aa;
            color: white;
        }
        
        #peace-choice-modal .button-primary:hover,
        #peace-info-modal .button-primary:hover {
            background: #005a87;
            border-color: #005a87;
        }
        
        @media (prefers-color-scheme: dark) {
            #peace-choice-modal-content {
                background: #1a1a1a !important;
                color: #eee !important;
            }
            
            #peace-info-modal-content {
                background: #1a1a1a !important;
                color: #eee !important;
            }
            
            #peace-federated-domain {
                border-bottom-color: #666 !important;
                color: #eee !important;
            }
            
            #peace-federated-domain:focus {
                border-bottom-color: #fff !important;
            }
            
            #peace-note {
                border-bottom-color: #666 !important;
                color: #eee !important;
            }
            
            #peace-note:focus {
                border-bottom-color: #fff !important;
            }
            
            #peace-info-modal-content a {
                color: #60a5fa !important;
            }
            
            #peace-info-modal-content a:hover {
                color: #93c5fd !important;
            }
            
            #peace-choice-modal .button,
            #peace-info-modal .button {
                background: #333 !important;
                border-color: #555 !important;
                color: #eee !important;
            }
            
            #peace-choice-modal .button:hover,
            #peace-info-modal .button:hover {
                background: #444 !important;
                border-color: #666 !important;
            }
            
            #peace-choice-modal .button-primary,
            #peace-info-modal .button-primary {
                background: #0073aa !important;
                border-color: #0073aa !important;
                color: white !important;
            }
            
            #peace-choice-modal .button-primary:hover,
            #peace-info-modal .button-primary:hover {
                background: #005a87 !important;
                border-color: #005a87 !important;
            }
        }
        </style>

        <!-- Site URL modal (reused for both methods) -->
        <div id="peace-federated-modal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.75);z-index:100002;align-items:center;justify-content:center;">
          <div id="peace-federated-modal-content" style="max-width:400px;padding:1rem;border-radius:0.5rem;">
            <h2 id="peace-federated-title"><?php esc_html_e('Log in as your site', 'peace-protocol'); ?></h2>
            <p><?php esc_html_e('Enter your domain (e.g. https://yoursite.com):', 'peace-protocol'); ?></p>
            <input type="text" id="peace-federated-domain" style="width:100%;margin-bottom:0.7em;color:inherit;" placeholder="https://yoursite.com" />
            <div style="display:flex;align-items:center;justify-content:flex-end;gap:0.5em;">
              <button id="peace-federated-cancel" class="button">Cancel</button>
              <button id="peace-federated-submit" class="button button-primary">Continue</button>
            </div>
            <div id="peace-federated-error" style="color:#b91c1c;font-size:0.97em;margin-top:0.5em;display:none;"></div>
            <div style="margin-top:1em;text-align:center;">
              <a href="#" id="peace-send-as-current-site" style="font-size:0.97em;color:#2563eb;text-decoration:underline;cursor:pointer;">
                <?php /* esc_html_e('', 'peace-protocol'); */ ?>
              </a>
            </div>
          </div>
        </div>

        <!-- Info modal -->
        <div id="peace-info-modal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.75);z-index:100003;align-items:center;justify-content:center;">
          <div id="peace-info-modal-content" style="background:#fff;max-width:500px;padding:1.5rem;border-radius:0.5rem;color:#333;">
            <h2><?php esc_html_e('What is Peace Protocol?', 'peace-protocol'); ?></h2>
            <p><?php esc_html_e('Peace Protocol is a decentralized authentication system that allows WordPress administrators to securely authenticate across different websites without sharing passwords or personal information.', 'peace-protocol'); ?></p>
            <p><?php esc_html_e('It works by using cryptographic tokens and secure handshakes between sites, ensuring that only authorized administrators can access federated features.', 'peace-protocol'); ?></p>
            <p><?php esc_html_e('This plugin supports both the original Peace Protocol method and the IndieAuth standard for maximum compatibility.', 'peace-protocol'); ?></p>
            <div style="text-align:center;margin-top:1.5em;">
              <a href="https://github.com/zerosonesfun/peace-protocol" target="_blank" style="color:#2563eb;text-decoration:underline;">
                <?php esc_html_e('Learn more on GitHub', 'peace-protocol'); ?>
              </a>
            </div>
            <div style="text-align:center;margin-top:1em;">
              <button id="peace-info-close" class="button button-primary">Close</button>
            </div>
          </div>
        </div>
        <?php
        echo '<!-- Peace Protocol: Federated modal HTML output -->';
        ?>
        <div id="peace-redirect-modal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.75);z-index:100002;align-items:center;justify-content:center;">
          <div id="peace-redirect-modal-content" style="background:#fff;max-width:500px;padding:1.5rem;border-radius:0.5rem;color:#333;text-align:center;">
            <h2><?php esc_html_e('Start Peace Protocol', 'peace-protocol'); ?></h2>
            <p><?php esc_html_e('Click the following link to start the peace protocol:', 'peace-protocol'); ?></p>
            <div style="margin:1.5em 0;">
              <a id="peace-redirect-link" href="#" style="display:inline-block;padding:0.8em 1.5em;background:#2563eb;color:#fff;text-decoration:none;border-radius:0.3em;font-weight:bold;font-size:1.1em;">
                <?php esc_html_e('Continue to Site', 'peace-protocol'); ?>
              </a>
            </div>
            <p style="font-size:0.9em;color:#666;margin-top:1em;">
              <?php esc_html_e('This will open your site in a new tab to complete the authentication process.', 'peace-protocol'); ?>
            </p>
            <button id="peace-redirect-cancel" class="button">Cancel</button>
          </div>
        </div>
        <?php
        echo '<!-- Peace Protocol: Redirect modal HTML output -->';
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Define ajaxurl for AJAX fallbacks
            const ajaxurl = (typeof peaceData !== 'undefined' && peaceData.ajaxurl) ? peaceData.ajaxurl : '<?php echo esc_url(admin_url('admin-ajax.php')); ?>';
            
            const btn = document.getElementById('peace-protocol-button');
            const modal = document.getElementById('peace-modal');
            const sendBtn = document.getElementById('peace-send');
            const cancelBtn = document.getElementById('peace-cancel');
            const noteEl = document.getElementById('peace-note');
            const successModal = document.getElementById('peace-success-modal');
            const successOk = document.getElementById('peace-success-ok');
            const noteCounter = document.getElementById('peace-note-counter');
            const sendingAs = document.getElementById('peace-sending-as');
            const switchSiteLink = document.getElementById('peace-switch-site-link');

            const getIdentities = window.peaceProtocolGetIdentities || function() { return []; };
            let selectedIdentity = null;
            const canonicalSiteUrl = (typeof peaceData !== 'undefined' && peaceData.siteUrl) ? peaceData.siteUrl : window.location.origin;

            function refreshIdentities() {
                const identities = getIdentities();
                
                // Check if we have any federated authorizations (sites we can send peace as)
                let authorizations = [];
                try {
                    const stored = localStorage.getItem('peace-protocol-authorizations');
                    if (stored) authorizations = JSON.parse(stored);
                    if (!Array.isArray(authorizations)) authorizations = [];
                } catch (e) {
                    authorizations = [];
                }
                
                if (authorizations.length > 0) {
                    const federatedSite = authorizations[0].site;
                    
                    // Find or create identity for the federated site
                    let federatedIdentity = identities.find(id => id.site === federatedSite);
                    if (!federatedIdentity) {
                        // Create a placeholder identity for the federated site
                        federatedIdentity = { site: federatedSite, token: 'federated-auth' };
                    }
                    
                    selectedIdentity = federatedIdentity;
                } else {
                    // No federated authorizations, use current site identity
                    selectedIdentity = identities.find(id => id.site === canonicalSiteUrl) || null;
                    if (!selectedIdentity && identities.length > 0) {
                        selectedIdentity = identities[identities.length - 1]; // Most recent
                    }
                }
                
                return identities;
            }

            btn.addEventListener('click', () => {
                // Show the choice modal first
                if (window.peaceProtocolShowChoiceModal) {
                    window.peaceProtocolShowChoiceModal();
                }
            });

            cancelBtn.addEventListener('click', () => {
                modal.style.display = 'none';
            });

            sendBtn.addEventListener('click', async () => {
                const identities = refreshIdentities();
                const note = noteEl.value.trim();
                if (note.length > 50) {
                    alert('Note must be 50 characters or less.');
                    return;
                }
                // Use selected identity
                if (!selectedIdentity || !selectedIdentity.token || !selectedIdentity.site) {
                    alert('No valid Peace Protocol identity found in this browser. Please visit your site\'s admin to generate a token.');
                    modal.style.display = 'none';
                    return;
                }

                // Optimistic UI: disable button & close modal
                sendBtn.disabled = true;
                modal.style.display = 'none';

                // Try REST API first, then fall back to AJAX
                async function trySendPeace() {
                    // Get authorization for the federated site (the site we're sending peace as)
                    let authorizations = [];
                    try {
                        const stored = localStorage.getItem('peace-protocol-authorizations');
                        if (stored) authorizations = JSON.parse(stored);
                        if (!Array.isArray(authorizations)) authorizations = [];
                    } catch (e) {
                        authorizations = [];
                    }
                    
                    // Find authorization for the selected identity site
                    const auth = authorizations.find(a => a.site === selectedIdentity.site);
                    
                    if (!auth) {
                        throw new Error('No authorization found for site ' + selectedIdentity.site + '. Please authenticate first.');
                    }
                    
                    // Since siteA authenticated and provided a valid authorization code,
                    // we can trust that siteA is authorized to send peace on behalf of the user.
                    // We'll verify the authorization code locally and send peace directly.
                    
                    try {
                        // Send peace directly using the authorization code
                        const response = await fetch('/wp-json/peace-protocol/v1/send-peace', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({
                                target_site: window.location.origin,
                                message: note,
                                authorization_code: auth.code,
                                federated_site: selectedIdentity.site,
                            }),
                        });

                        if (response.ok) {
                            return true; // Success
                        } else {
                            throw new Error('REST API failed, trying AJAX');
                        }
                    } catch (err) {
                        // Fall back to AJAX
                        const formData = new FormData();
                        formData.append('action', 'peace_protocol_send_peace');
                        formData.append('target_site', window.location.origin);
                        formData.append('message', note);
                        formData.append('authorization_code', auth.code);
                        formData.append('federated_site', selectedIdentity.site);
                        
                        const ajaxResponse = await fetch(ajaxurl, {
                            method: 'POST',
                            body: formData
                        });
                        
                        if (!ajaxResponse.ok) {
                            throw new Error('AJAX also failed');
                        }
                        
                        const ajaxData = await ajaxResponse.json();
                        if (!ajaxData.success) {
                            throw new Error(ajaxData.data || 'AJAX request failed');
                        }
                        
                        return true; // Success
                    }
                }

                try {
                    await trySendPeace();

                    // Peace sent successfully
                    document.getElementById('peace-success-message').textContent = '✌️ <?php esc_html_e('Peace sent successfully!', 'peace-protocol'); ?>';
                    successModal.style.display = 'flex';
                    successOk.focus();
                } catch (err) {
                    // Check if this is a token error (403)
                    if (err.message && (err.message.includes('403') || err.message.includes('Invalid token'))) {
                        // Remove the invalid token from localStorage
                        let identities = window.peaceProtocolGetIdentities ? window.peaceProtocolGetIdentities() : [];
                        identities = identities.filter(id => id.site !== selectedIdentity.site);
                        if (window.peaceProtocolSetIdentities) window.peaceProtocolSetIdentities(identities);
                        
                        // Close the modal
                        modal.style.display = 'none';
                        
                        // Show federated login modal to get a fresh token
                        if (window.peaceProtocolShowFederatedModal) {
                            window.peaceProtocolShowFederatedModal(function(domain) {
                                if (window.peaceProtocolFederatedLogin) {
                                    window.peaceProtocolFederatedLogin();
                                }
                            });
                        }
                    } else {
                        alert('Failed to send peace. Please try again.\n' + (err && err.message ? err.message : ''));
                    }
                }
                sendBtn.disabled = false;
            });

            successOk.addEventListener('click', () => {
                successModal.style.display = 'none';
            });

            // Live character counter for note
            noteEl.addEventListener('input', function() {
                noteCounter.textContent = noteEl.value.length + '/50';
            });
            // Initialize counter on modal open
            function resetNoteCounter() {
                noteCounter.textContent = noteEl.value.length + '/50';
            }

            // Choice modal elements
            const choiceModal = document.getElementById('peace-choice-modal');
            const peaceProtocolLoginBtn = document.getElementById('peace-protocol-login-btn');
            const peaceIndieAuthLoginBtn = document.getElementById('peace-indieauth-login-btn');
            const peaceWhatIsBtn = document.getElementById('peace-what-is-btn');
            const peaceChoiceCancel = document.getElementById('peace-choice-cancel');

            // Info modal elements
            const infoModal = document.getElementById('peace-info-modal');
            const peaceInfoClose = document.getElementById('peace-info-close');

            // Federated login modal logic
            const federatedModal = document.getElementById('peace-federated-modal');
            const federatedDomain = document.getElementById('peace-federated-domain');
            const federatedCancel = document.getElementById('peace-federated-cancel');
            const federatedSubmit = document.getElementById('peace-federated-submit');
            const federatedError = document.getElementById('peace-federated-error');
            const sendAsCurrentSite = document.getElementById('peace-send-as-current-site');
            const federatedTitle = document.getElementById('peace-federated-title');

            // Redirect modal elements
            const redirectModal = document.getElementById('peace-redirect-modal');
            const redirectLink = document.getElementById('peace-redirect-link');
            const redirectCancel = document.getElementById('peace-redirect-cancel');

            // Function to show redirect modal
            window.peaceProtocolShowRedirectModal = function(url) {
                redirectLink.href = url;
                redirectModal.style.display = 'flex';
                
                // Handle redirect link click
                redirectLink.onclick = function(e) {
                    // The link will open in new tab due to target="_blank"
                };
                
                // Handle cancel button
                redirectCancel.onclick = function() {
                    redirectModal.style.display = 'none';
                };
            };

            // Function to show choice modal
            window.peaceProtocolShowChoiceModal = function() {
                choiceModal.style.display = 'flex';
            };

            // Choice modal event handlers
            peaceProtocolLoginBtn.addEventListener('click', function() {
                choiceModal.style.display = 'none';
                // Show federated modal for Peace Protocol
                window.peaceProtocolShowFederatedModal(function(domain) {
                    // This will trigger the regular Peace Protocol flow
                    if (window.peaceProtocolFederatedLogin) {
                        window.peaceProtocolFederatedLogin();
                    }
                }, 'peace-protocol');
            });

            peaceIndieAuthLoginBtn.addEventListener('click', function() {
                choiceModal.style.display = 'none';
                // Show federated modal for IndieAuth
                window.peaceProtocolShowFederatedModal(function(domain) {
                    // This will trigger the IndieAuth flow
                    if (window.peaceProtocolIndieAuthLogin) {
                        window.peaceProtocolIndieAuthLogin(domain);
                    }
                }, 'indieauth');
            });

            peaceWhatIsBtn.addEventListener('click', function() {
                choiceModal.style.display = 'none';
                infoModal.style.display = 'flex';
            });

            peaceChoiceCancel.addEventListener('click', function() {
                choiceModal.style.display = 'none';
            });

            // Info modal event handlers
            peaceInfoClose.addEventListener('click', function() {
                infoModal.style.display = 'none';
            });

            // Function to show send peace modal
            window.peaceProtocolShowSendPeaceModal = function() {
                // Check if user is banned (if we're on a banned page)
                if (window.location.search.includes('peace_banned=1')) {
                    alert('You are banned from sending peace.');
                    return;
                }
                
                // Check localStorage for ban flag
                if (localStorage.getItem('peace-protocol-banned') === 'true') {
                    alert('You are banned from sending peace.');
                    return;
                }
                
                // Additional ban check - look for any ban indicators on the page
                const bannedElements = document.querySelectorAll('.banned-message, [data-banned="true"]');
                if (bannedElements.length > 0) {
                    alert('You are banned from sending peace.');
                    return;
                }
                
                refreshIdentities();
                
                if (selectedIdentity && selectedIdentity.token && selectedIdentity.site) {
                    document.getElementById('peace-modal-title').textContent = 'Send Peace?';
                    document.getElementById('peace-modal-question').textContent = 'You may send a message to this site below.';
                    noteEl.style.display = 'block';
                    sendBtn.style.display = 'block';
                    cancelBtn.textContent = 'Cancel';
                    cancelBtn.focus();
                    sendingAs.textContent = 'Sending as: ' + selectedIdentity.site;
                    modal.style.display = 'flex';
                    resetNoteCounter();
                } else {
                    // No identity found, show federated login modal instead
                    if (window.peaceProtocolShowFederatedModal) {
                        window.peaceProtocolShowFederatedModal(function(domain) {
                            // This will trigger the federated login flow
                            if (window.peaceProtocolFederatedLogin) {
                                window.peaceProtocolFederatedLogin();
                            }
                        });
                    }
                }
            };

            window.peaceProtocolShowFederatedModal = function(cb, authMethod = 'peace-protocol') {
              federatedModal.style.display = 'flex';
              federatedDomain.value = '';
              federatedError.style.display = 'none';
              federatedDomain.focus();
              
              // Update title based on authentication method
              if (authMethod === 'indieauth') {
                federatedTitle.textContent = 'Login with IndieAuth';
              } else {
                federatedTitle.textContent = 'Login with Peace Protocol';
              }
              
              federatedSubmit.onclick = function(e) {
                e.preventDefault(); // Prevent form submission/page reload
                const domain = federatedDomain.value.trim().replace(/\/$/, '');
                if (!domain.match(/^https?:\/\//)) {
                  federatedError.textContent = 'Please enter a valid URL (including https://)';
                  federatedError.style.display = 'block';
                  federatedDomain.focus();
                  return;
                }
                federatedModal.style.display = 'none';
                if (cb) cb(domain);
              };
              federatedCancel.onclick = function() {
                federatedModal.style.display = 'none';
              };
              // Handle "Send peace as this site" link
              if (sendAsCurrentSite) {
                sendAsCurrentSite.onclick = function(e) {
                  e.preventDefault();
                  federatedModal.style.display = 'none';
                  // Check if we have an identity for the current site
                  refreshIdentities();
                  if (selectedIdentity && selectedIdentity.token && selectedIdentity.site) {
                    document.getElementById('peace-modal-title').textContent = 'Give Peace?';
                    document.getElementById('peace-modal-question').textContent = 'Do you want to give peace to this site?';
                    noteEl.style.display = 'block';
                    sendBtn.style.display = 'block';
                    cancelBtn.textContent = 'Cancel';
                    cancelBtn.focus();
                    sendingAs.textContent = 'Sending as: ' + selectedIdentity.site;
                    modal.style.display = 'flex';
                    resetNoteCounter();
                  } else {
                    alert('No valid Peace Protocol identity found for this site. Please visit your site\'s admin to generate a token.');
                  }
                };
              }
            };

            // Function to handle regular Peace Protocol login
            window.peaceProtocolFederatedLogin = function() {
                // Get the domain from the federated modal
                const domain = federatedDomain.value.trim().replace(/\/$/, '');
                if (!domain.match(/^https?:\/\//)) {
                    alert('Please enter a valid URL (including https://)');
                    return;
                }
                
                // Original Peace Protocol flow: redirect directly to siteA
                const state = Math.random().toString(36).slice(2) + Date.now();
                const returnUrl = encodeURIComponent(window.location.href.split('#')[0]);
                localStorage.setItem('peace-federated-state', state);
                localStorage.setItem('peace-federated-return', window.location.href.split('#')[0]);
                const currentSite = window.location.origin;
                const url = domain + '/?peace_get_token=1&return_site=' + encodeURIComponent(currentSite) + '&state=' + encodeURIComponent(state);
                
                // Direct redirect (no modal)
                window.location.href = url;
            };

            // Function to handle IndieAuth login
            window.peaceProtocolIndieAuthLogin = async function(domain) {
                // Validate the domain parameter
                if (!domain || !domain.match(/^https?:\/\//)) {
                    alert('Please enter a valid URL (including https://)');
                    return;
                }
                
                try {
                    // Step 1: Discover IndieAuth metadata using server-side discovery to avoid CORS
                    console.log('IndieAuth: Starting discovery for domain:', domain);
                    const metadata = await discoverIndieAuthMetadataServerSide(domain);
                    if (!metadata || !metadata.authorization_endpoint) {
                        throw new Error('Could not discover IndieAuth authorization endpoint');
                    }
                    
                    // Generate PKCE code verifier and challenge
                    const codeVerifier = generateCodeVerifier();
                    const codeChallenge = await generateCodeChallenge(codeVerifier);
                    const state = Math.random().toString(36).slice(2) + Date.now();
                    
                    // Store PKCE data in localStorage, keyed by state
                    localStorage.setItem('peace-indieauth-code-verifier-' + state, codeVerifier);
                    localStorage.setItem('peace-indieauth-state', state);
                    localStorage.setItem('peace-indieauth-return-url', window.location.href.split('#')[0]);
                    localStorage.setItem('peace-indieauth-target-site', domain);
                    localStorage.setItem('peace-indieauth-metadata', JSON.stringify(metadata));
                    
                    // Build IndieAuth authorization URL using our custom endpoint
                    const authUrl = new URL(domain + '/?peace_indieauth_auth=1');
                    authUrl.searchParams.set('client_id', window.location.origin);
                    authUrl.searchParams.set('redirect_uri', window.location.origin + '/peace-indieauth-callback/');
                    authUrl.searchParams.set('state', state);
                    authUrl.searchParams.set('code_challenge', codeChallenge);
                    authUrl.searchParams.set('code_challenge_method', 'S256');
                    authUrl.searchParams.set('scope', 'profile email');
                    // Set me parameter to the site where the user is authenticating (siteA)
                    console.log('IndieAuth: Setting me parameter to:', domain);
                    authUrl.searchParams.set('me', domain);
                    // Pass the authorization endpoint as a URL parameter
                    authUrl.searchParams.set('authorization_endpoint', metadata.authorization_endpoint);
                    
                    // Debug logging
                    console.log('IndieAuth: Authorization URL parameters:', {
                        client_id: window.location.origin,
                        redirect_uri: window.location.origin + '/peace-indieauth-callback/',
                        me: domain,
                        state: state,
                        scope: 'profile email'
                    });
                    console.log('IndieAuth: Full authorization URL:', authUrl.toString());
                    
                    // Log the values server-side for debugging
                    const formData = new FormData();
                    formData.append('action', 'peace_protocol_debug_log');
                    formData.append('message', 'IndieAuth: Setting me parameter to: ' + domain + ', authUrl: ' + authUrl.toString());
                    fetch(ajaxurl, {
                        method: 'POST',
                        body: formData
                    }).catch(() => {}); // Ignore errors
                    
                    // Redirect to our custom auth endpoint
                    window.location.href = authUrl.toString();
                } catch (error) {
                    console.error('IndieAuth discovery failed:', error);
                    console.error('Discovery details:', {
                        domain: domain,
                        error: error.message,
                        stack: error.stack
                    });
                    
                    // More detailed error message
                    let errorMessage = 'Failed to discover IndieAuth endpoint. ';
                    if (error.message.includes('HTTP')) {
                        errorMessage += 'The site may not be accessible or may be blocking requests.';
                    } else if (error.message.includes('No IndieAuth metadata')) {
                        errorMessage += 'The site does not appear to support IndieAuth. Make sure the site has IndieAuth metadata or authorization endpoints configured.';
                    } else if (error.message.includes('Metadata missing')) {
                        errorMessage += 'The site has IndieAuth links but the metadata is incomplete.';
                    } else {
                        errorMessage += 'Please make sure the site supports IndieAuth.';
                    }
                    
                    alert(errorMessage);
                }
            };

            // PKCE helper functions for IndieAuth
            function generateCodeVerifier() {
              const array = new Uint8Array(32);
              crypto.getRandomValues(array);
              return base64UrlEncode(array);
            }
            
            function generateCodeChallenge(codeVerifier) {
              const encoder = new TextEncoder();
              const data = encoder.encode(codeVerifier);
              return crypto.subtle.digest('SHA-256', data).then(hash => {
                return base64UrlEncode(new Uint8Array(hash));
              });
            }
            
            function base64UrlEncode(buffer) {
              const base64 = btoa(String.fromCharCode(...buffer));
              return base64.replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '');
            }
            
            // Server-side IndieAuth discovery function (avoids CORS issues)
            async function discoverIndieAuthMetadataServerSide(url) {
              try {
                console.log('IndieAuth discovery: Starting server-side discovery for URL:', url);
                
                const formData = new FormData();
                formData.append('action', 'peace_protocol_discover_indieauth');
                formData.append('url', url);
                formData.append('_wpnonce', '<?php echo wp_create_nonce("peace_protocol_indieauth"); ?>');
                
                const response = await fetch(ajaxurl, {
                  method: 'POST',
                  body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                  console.log('IndieAuth discovery: Server-side discovery successful:', result.data);
                  return result.data;
                } else {
                  throw new Error(result.data || 'Server-side discovery failed');
                }
              } catch (error) {
                console.error('IndieAuth discovery: Server-side discovery error:', error);
                throw error;
              }
            }
            
            // Client-side IndieAuth discovery function (fallback)
            async function discoverIndieAuthMetadata(url) {
              try {
                console.log('IndieAuth discovery: Starting discovery for URL:', url);
                
                // Step 1: Fetch the user's URL to find indieauth-metadata link
                let response;
                try {
                  response = await fetch(url, {
                    method: 'GET',
                    headers: {
                      'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8'
                    }
                  });
                } catch (fetchError) {
                  console.error('IndieAuth discovery: Fetch failed, trying HEAD request:', fetchError);
                  // Try HEAD request as fallback
                  try {
                    response = await fetch(url, {
                      method: 'HEAD',
                      headers: {
                        'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8'
                      }
                    });
                  } catch (headError) {
                    console.error('IndieAuth discovery: HEAD request also failed:', headError);
                    throw new Error(`Failed to fetch URL: ${fetchError.message}. This might be due to CORS restrictions.`);
                  }
                }
                
                console.log('IndieAuth discovery: Response status:', response.status, response.statusText);
                
                if (!response.ok) {
                  throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                
                // Step 2: Look for indieauth-metadata in Link headers first
                const linkHeader = response.headers.get('Link');
                console.log('IndieAuth discovery: Link header found:', linkHeader);
                let metadataUrl = null;
                
                if (linkHeader) {
                  const links = linkHeader.split(',').map(link => {
                    const [url, ...rels] = link.split(';');
                    const rel = rels.find(r => r.includes('rel='));
                    return {
                      url: url.trim().replace(/[<>]/g, ''),
                      rel: rel ? rel.split('=')[1].replace(/"/g, '').trim() : ''
                    };
                  });
                  
                  console.log('IndieAuth discovery: Parsed links:', links);
                  
                  const indieauthLink = links.find(link => link.rel === 'indieauth-metadata');
                  if (indieauthLink) {
                    metadataUrl = new URL(indieauthLink.url, url).href;
                    console.log('IndieAuth discovery: Found metadata URL in Link header:', metadataUrl);
                  }
                }
                
                // Step 3: If no Link header, parse HTML for <link> tags
                if (!metadataUrl) {
                  console.log('IndieAuth discovery: No metadata URL in Link header, checking HTML');
                  const html = await response.text();
                  const parser = new DOMParser();
                  const doc = parser.parseFromString(html, 'text/html');
                  
                  const linkElement = doc.querySelector('link[rel="indieauth-metadata"]');
                  if (linkElement) {
                    metadataUrl = new URL(linkElement.href, url).href;
                    console.log('IndieAuth discovery: Found metadata URL in HTML:', metadataUrl);
                  } else {
                    console.log('IndieAuth discovery: No indieauth-metadata link found in HTML');
                  }
                }
                
                // Step 4: If still no metadata URL, try legacy discovery
                if (!metadataUrl) {
                  console.log('IndieAuth discovery: Trying legacy discovery for authorization_endpoint');
                  
                  // Try to find authorization_endpoint directly
                  if (linkHeader) {
                    const links = linkHeader.split(',').map(link => {
                      const [url, ...rels] = link.split(';');
                      const rel = rels.find(r => r.includes('rel='));
                      return {
                        url: url.trim().replace(/[<>]/g, ''),
                        rel: rel ? rel.split('=')[1].replace(/"/g, '').trim() : ''
                      };
                    });
                    
                    const authLink = links.find(link => link.rel === 'authorization_endpoint');
                    if (authLink) {
                      console.log('IndieAuth discovery: Found authorization_endpoint in Link header:', authLink.url);
                      return {
                        authorization_endpoint: new URL(authLink.url, url).href,
                        token_endpoint: null
                      };
                    }
                  }
                  
                  // Try HTML link elements for legacy discovery
                  const html = await response.text();
                  const parser = new DOMParser();
                  const doc = parser.parseFromString(html, 'text/html');
                  
                  const authLink = doc.querySelector('link[rel="authorization_endpoint"]');
                  if (authLink) {
                    console.log('IndieAuth discovery: Found authorization_endpoint in HTML:', authLink.href);
                    return {
                      authorization_endpoint: new URL(authLink.href, url).href,
                      token_endpoint: null
                    };
                  }
                  
                  console.log('IndieAuth discovery: No authorization_endpoint found in legacy discovery');
                  throw new Error('No IndieAuth metadata or authorization endpoint found');
                }
                
                // Step 5: Fetch the metadata document
                console.log('IndieAuth discovery: Fetching metadata from:', metadataUrl);
                const metadataResponse = await fetch(metadataUrl, {
                  method: 'GET',
                  headers: {
                    'Accept': 'application/json'
                  }
                });
                
                console.log('IndieAuth discovery: Metadata response status:', metadataResponse.status, metadataResponse.statusText);
                
                if (!metadataResponse.ok) {
                  throw new Error(`Metadata fetch failed: ${metadataResponse.status}`);
                }
                
                const metadata = await metadataResponse.json();
                console.log('IndieAuth discovery: Parsed metadata:', metadata);
                
                // Validate required fields
                if (!metadata.authorization_endpoint) {
                  throw new Error('Metadata missing authorization_endpoint');
                }
                
                console.log('IndieAuth discovery: Successfully discovered endpoints:', {
                  authorization_endpoint: metadata.authorization_endpoint,
                  token_endpoint: metadata.token_endpoint || 'not provided'
                });
                
                return metadata;
              } catch (error) {
                console.error('IndieAuth discovery error:', error);
                throw error;
              }
            }

            // Handle IndieAuth callback - now handled server-side in template_redirect
            // The server will process the callback and show the completion screen

            if (switchSiteLink) {
                switchSiteLink.addEventListener('click', function(e) {
                    e.preventDefault();
                    if (window.peaceProtocolShowFederatedLoginModal) {
                        window.peaceProtocolShowFederatedLoginModal();
                    }
                });
            }
        });
        </script>
        <?php
        // Since we're outputting directly, just return empty string
        return '';
    }
}

// Add peace hand button shortcode
add_shortcode('peace_hand_button', function () {
    return peace_protocol_render_hand_button();
});
