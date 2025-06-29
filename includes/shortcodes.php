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
                <?php echo $position_css; ?>
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
        <button id="peace-protocol-button" title="<?php esc_attr_e('Give Peace ✌️', 'peace-protocol'); ?>" style="position: fixed; <?php echo esc_attr($position_css); ?> background: transparent; border: none; font-size: 2rem; cursor: pointer; z-index: 99999;">✌️</button>
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
                        <?php esc_html_e('', 'peace-protocol'); ?>
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
        <div id="peace-federated-modal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.75);z-index:100001;align-items:center;justify-content:center;">
          <div id="peace-federated-modal-content" style="max-width:400px;padding:1rem;border-radius:0.5rem;">
            <h2><?php esc_html_e('Log in as your site', 'peace-protocol'); ?></h2>
            <p><?php esc_html_e('Enter your domain (e.g. https://yoursite.com):', 'peace-protocol'); ?></p>
            <input type="text" id="peace-federated-domain" style="width:100%;margin-bottom:0.7em;color:inherit;" placeholder="https://yoursite.com" />
            <div style="display:flex;align-items:center;justify-content:flex-end;gap:0.5em;">
              <button id="peace-federated-cancel" class="button">Cancel</button>
              <button id="peace-federated-submit" class="button button-primary">Continue</button>
            </div>
            <div id="peace-federated-error" style="color:#b91c1c;font-size:0.97em;margin-top:0.5em;display:none;"></div>
            <div style="margin-top:1em;text-align:center;border-top:1px solid #eee;padding-top:1em;">
              <a href="#" id="peace-send-as-current-site" style="font-size:0.97em;color:#2563eb;text-decoration:underline;cursor:pointer;">
                <?php esc_html_e('', 'peace-protocol'); ?>
              </a>
              <a href="https://github.com/zerosonesfun/peace-protocol" id="what-is-peace-protocol" style="font-size:0.97em;color:#2563eb;text-decoration:underline;cursor:pointer;">
                <?php esc_html_e('What is this?', 'peace-protocol'); ?>
              </a>
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
                // Always show federated login modal first, so user can choose which site to send peace as
                if (window.peaceProtocolFederatedLogin) {
                    window.peaceProtocolFederatedLogin();
                }
            });

            cancelBtn.addEventListener('click', () => {
                modal.style.display = 'none';
            });

            sendBtn.addEventListener('click', async () => {
                const identities = refreshIdentities();
                const note = noteEl.value.trim();
                if (note.length > 50) {
                    alert('<?php echo esc_js(__('Note must be 50 characters or less.', 'peace-protocol')); ?>');
                    return;
                }
                // Use selected identity
                if (!selectedIdentity || !selectedIdentity.token || !selectedIdentity.site) {
                    alert('<?php echo esc_js(__('No valid Peace Protocol identity found in this browser. Please visit your site\'s admin to generate a token.', 'peace-protocol')); ?>');
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
                        alert('<?php echo esc_js(__('Failed to send peace. Please try again.', 'peace-protocol')); ?>' + '\n' + (err && err.message ? err.message : ''));
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

            // Federated login modal logic
            const federatedModal = document.getElementById('peace-federated-modal');
            const federatedDomain = document.getElementById('peace-federated-domain');
            const federatedCancel = document.getElementById('peace-federated-cancel');
            const federatedSubmit = document.getElementById('peace-federated-submit');
            const federatedError = document.getElementById('peace-federated-error');
            const sendAsCurrentSite = document.getElementById('peace-send-as-current-site');

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

            window.peaceProtocolShowFederatedModal = function(cb) {
              federatedModal.style.display = 'flex';
              federatedDomain.value = '';
              federatedError.style.display = 'none';
              federatedDomain.focus();
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
                    alert('<?php echo esc_js(__('No valid Peace Protocol identity found for this site. Please visit your site\'s admin to generate a token.', 'peace-protocol')); ?>');
                  }
                };
              }
            };

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
