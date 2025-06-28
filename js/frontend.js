(() => {
    console.log('[Peace Protocol] frontend.js loaded');
    const LS_KEY = 'peace-protocol-identities';
    
    // Utility function to check if REST API is available
    async function isRestApiAvailable() {
        try {
            const response = await fetch('/wp-json/', { method: 'HEAD' });
            return response.ok;
        } catch (e) {
            return false;
        }
    }
    
    function getIdentities() {
        try {
            const val = localStorage.getItem(LS_KEY);
            if (!val) return [];
            const arr = JSON.parse(val);
            if (Array.isArray(arr)) return arr;
        } catch (e) {}
        return [];
    }

    function setIdentities(identities) {
        localStorage.setItem(LS_KEY, JSON.stringify(identities));
    }

    // Always set up the global functions for the button/modal system
    window.peaceProtocolGetIdentities = getIdentities;
    window.peaceProtocolSetIdentities = setIdentities;

    // Debug logging
    console.log('Peace Protocol: Global functions set up');
    console.log('Peace Protocol: Identities found:', getIdentities().length);

    // Always-available send peace modal function
    window.peaceProtocolShowSendPeaceModal = function() {
        console.log('[Peace Protocol] ShowSendPeaceModal called (frontend.js version)');
        
        // Check if we have any identities
        const identities = getIdentities();
        console.log('[Peace Protocol] Available identities:', identities);
        
        if (identities.length > 0) {
            // Use the first available identity
            const selectedIdentity = identities[0];
            console.log('[Peace Protocol] Using identity:', selectedIdentity);
            
            // Create and show the modal
            showPeaceModal(selectedIdentity);
        } else {
            console.log('[Peace Protocol] No identities found');
            alert('Peace Protocol: No identities found. Please visit your site\'s admin to generate a token.');
        }
    };

    // Function to show the peace modal
    function showPeaceModal(identity) {
        // Remove existing modal if any
        const existingModal = document.getElementById('peace-modal-overlay');
        if (existingModal) {
            existingModal.remove();
        }

        // Create modal HTML
        const modalHTML = `
            <div id="peace-modal-overlay" style="
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.7);
                display: flex;
                justify-content: center;
                align-items: center;
                z-index: 10000;
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            ">
                <div style="
                    background: white;
                    padding: 30px;
                    border-radius: 12px;
                    max-width: 400px;
                    width: 90%;
                    text-align: center;
                    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
                ">
                    <h2 style="margin: 0 0 20px 0; color: #333;">✌️ Give Peace?</h2>
                    <p style="margin: 0 0 20px 0; color: #666;">Do you want to give peace to this site?</p>
                    
                    <div style="margin: 0 0 20px 0;">
                        <label for="peace-note" style="display: block; margin-bottom: 8px; color: #333; font-weight: bold;">Note (optional):</label>
                        <textarea id="peace-note" placeholder="Add a note..." style="
                            width: 100%;
                            padding: 12px;
                            border: 2px solid #ddd;
                            border-radius: 6px;
                            font-size: 14px;
                            resize: vertical;
                            min-height: 80px;
                            font-family: inherit;
                        " maxlength="50"></textarea>
                        <div id="peace-note-counter" style="text-align: right; margin-top: 4px; color: #999; font-size: 12px;">0/50</div>
                    </div>
                    
                    <div style="margin: 0 0 20px 0; padding: 10px; background: #f8f9fa; border-radius: 6px; font-size: 14px; color: #666;">
                        Sending as: <strong>${identity.site}</strong>
                    </div>
                    
                    <div style="display: flex; gap: 10px; justify-content: center;">
                        <button id="peace-send-btn" style="
                            background: #0073aa;
                            color: white;
                            border: none;
                            padding: 12px 24px;
                            border-radius: 6px;
                            cursor: pointer;
                            font-size: 16px;
                            font-weight: bold;
                        ">Send Peace</button>
                        <button id="peace-cancel-btn" style="
                            background: #6c757d;
                            color: white;
                            border: none;
                            padding: 12px 24px;
                            border-radius: 6px;
                            cursor: pointer;
                            font-size: 16px;
                        ">Cancel</button>
                    </div>
                    
                    <div id="peace-success" style="display: none; margin-top: 20px;">
                        <div style="font-size: 24px; margin-bottom: 10px;">✅</div>
                        <p style="margin: 0; color: #28a745; font-weight: bold;">Peace sent successfully!</p>
                    </div>
                    
                    <div id="peace-error" style="display: none; margin-top: 20px;">
                        <div style="font-size: 24px; margin-bottom: 10px;">❌</div>
                        <p id="peace-error-text" style="margin: 0; color: #dc3545;"></p>
                    </div>
                </div>
            </div>
        `;

        // Add modal to page
        document.body.insertAdjacentHTML('beforeend', modalHTML);

        // Get modal elements
        const modal = document.getElementById('peace-modal-overlay');
        const noteField = document.getElementById('peace-note');
        const noteCounter = document.getElementById('peace-note-counter');
        const sendBtn = document.getElementById('peace-send-btn');
        const cancelBtn = document.getElementById('peace-cancel-btn');
        const successDiv = document.getElementById('peace-success');
        const errorDiv = document.getElementById('peace-error');
        const errorText = document.getElementById('peace-error-text');

        // Set up note counter
        noteField.addEventListener('input', function() {
            const length = this.value.length;
            noteCounter.textContent = `${length}/50`;
        });

        // Set up send button
        sendBtn.addEventListener('click', function() {
            const note = noteField.value.trim();
            
            console.log('[Peace Protocol] Send button clicked, note:', note);
            
            // Show loading state
            sendBtn.style.display = 'none';
            cancelBtn.style.display = 'none';
            
            console.log('[Peace Protocol] Loading state shown, sending peace...');
            
            // Send peace
            sendPeace(identity, note, function(success, message) {
                console.log('[Peace Protocol] Peace send callback - success:', success, 'message:', message);
                sendBtn.style.display = 'inline-block';
                cancelBtn.style.display = 'inline-block';
                
                if (success) {
                    successDiv.style.display = 'block';
                    console.log('[Peace Protocol] Showing success state');
                    // Auto-close after 2 seconds
                    setTimeout(() => {
                        modal.remove();
                    }, 2000);
                } else {
                    errorText.textContent = message || 'Failed to send peace';
                    errorDiv.style.display = 'block';
                    console.log('[Peace Protocol] Showing error state:', message);
                }
            });
        });

        // Set up cancel button
        cancelBtn.addEventListener('click', function() {
            modal.remove();
        });

        // Close on overlay click
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                modal.remove();
            }
        });

        // Focus on note field
        noteField.focus();
    }

    // Function to send peace
    function sendPeace(identity, note, callback) {
        console.log('[Peace Protocol] Sending peace as:', identity.site, 'with note:', note);
        
        // Try REST API first
        fetch('/wp-json/peace-protocol/v1/send-peace', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                target_site: window.location.origin,
                message: note,
                authorization_code: identity.token,
                federated_site: identity.site
            })
        })
        .then(response => {
            console.log('[Peace Protocol] REST response status:', response.status);
            if (response.ok) {
                return response.json();
            } else {
                throw new Error(`REST failed with status ${response.status}`);
            }
        })
        .then(data => {
            console.log('[Peace Protocol] REST response data:', data);
            if (data && data.message) {
                callback(true, data.message);
            } else {
                callback(true, 'Peace sent successfully');
            }
        })
        .catch(error => {
            console.log('[Peace Protocol] REST failed, trying AJAX:', error);
            
            // Fall back to AJAX
            const formData = new FormData();
            formData.append('action', 'peace_protocol_send_peace');
            formData.append('target_site', window.location.origin);
            formData.append('message', note);
            formData.append('authorization_code', identity.token);
            formData.append('federated_site', identity.site);
            
            fetch('/wp-admin/admin-ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('[Peace Protocol] AJAX response status:', response.status);
                if (response.ok) {
                    return response.text();
                } else {
                    throw new Error(`AJAX failed with status ${response.status}`);
                }
            })
            .then(data => {
                console.log('[Peace Protocol] AJAX response:', data);
                callback(true, 'Peace sent successfully');
            })
            .catch(ajaxError => {
                console.log('[Peace Protocol] AJAX also failed:', ajaxError);
                callback(false, 'Failed to send peace. Please try again.');
            });
        });
    }

    // Federated login logic
    window.peaceProtocolFederatedLogin = function(options = {}) {
        console.log('[Peace Protocol] FederatedLogin called', options);
        // options: { onComplete(site, token), onError(err) }
        
        // Check if we already have a valid token for this site
        const identities = getIdentities();
        const currentSite = window.location.origin;
        const existingIdentity = identities.find(id => id.site === currentSite);
        
        if (existingIdentity && existingIdentity.token) {
            console.log('[Peace Protocol] Found existing token for current site, using it');
            if (options.onComplete) {
                options.onComplete(currentSite, existingIdentity.token);
            }
            return;
        }
        
        // No existing token, show modal to enter domain
        console.log('[Peace Protocol] No existing token found, showing modal');
        
        // Wait for the modal function to be available
        function tryShowModal() {
            if (window.peaceProtocolShowFederatedModal) {
                console.log('[Peace Protocol] ShowFederatedModal function exists, showing modal');
                window.peaceProtocolShowFederatedModal(function(domain) {
                    console.log('[Peace Protocol] Modal callback triggered with domain:', domain);
                    // Redirect to home site with return URL and a random state
                    const state = Math.random().toString(36).slice(2) + Date.now();
                    const returnUrl = encodeURIComponent(window.location.href.split('#')[0]);
                    // We'll use localStorage to remember the state
                    localStorage.setItem('peace-federated-state', state);
                    localStorage.setItem('peace-federated-return', window.location.href.split('#')[0]);
                    // Redirect to /?peace_get_token=1&return_site={currentSite}&state={state}
                    const currentSite = window.location.origin;
                    const url = domain + '/?peace_get_token=1&return_site=' + encodeURIComponent(currentSite) + '&state=' + encodeURIComponent(state);
                    console.log('[Peace Protocol] About to redirect to:', url);
                    
                    // Direct redirect
                    window.location.href = url;
                });
            } else {
                console.log('[Peace Protocol] ShowFederatedModal function not available yet, retrying...');
                setTimeout(tryShowModal, 100);
            }
        }
        
        tryShowModal();
    };

    // Always-available function to show federated login modal (even if identity is present)
    window.peaceProtocolShowFederatedLoginModal = function() {
        // Check if we already have a valid token for this site
        const identities = getIdentities();
        const currentSite = window.location.origin;
        const existingIdentity = identities.find(id => id.site === currentSite);
        
        if (existingIdentity && existingIdentity.token) {
            console.log('[Peace Protocol] Found existing token for current site in ShowFederatedLoginModal');
            return; // Don't show modal if we already have a token
        }
        
        if (window.peaceProtocolShowFederatedModal) {
            window.peaceProtocolShowFederatedModal(function(domain) {
                // Same as above
                const state = Math.random().toString(36).slice(2) + Date.now();
                const returnUrl = encodeURIComponent(window.location.href.split('#')[0]);
                localStorage.setItem('peace-federated-state', state);
                localStorage.setItem('peace-federated-return', window.location.href.split('#')[0]);
                const currentSite = window.location.origin;
                const url = domain + '/?peace_get_token=1&return_site=' + encodeURIComponent(currentSite) + '&state=' + encodeURIComponent(state);
                window.location.href = url;
            });
        }
    };

    // On page load, check for peace federated code in URL (only when returning from another site)
    (function() {
        const url = new URL(window.location.href);
        const code = url.searchParams.get('peace_federated_code');
        const site = url.searchParams.get('peace_federated_site');
        const state = url.searchParams.get('peace_federated_state');
        
        // Check for peace_authorization_code (after direct authorization request)
        const authCode = url.searchParams.get('peace_authorization_code');
        const authSite = url.searchParams.get('peace_federated_site');
        const authState = url.searchParams.get('peace_federated_state');
        
        // Log what we found in the URL
        console.log('[Peace Protocol] URL params - authCode:', authCode, 'site:', authSite, 'state:', authState);
        
        // Note: Authorization code handling is now done server-side in template_redirect
        // The server will process the code, create the federated user, and redirect
        
        // Only run code exchange if we have all parameters AND we're returning from another site
        if (code && site && state) {
            const expectedState = localStorage.getItem('peace-federated-state');
            const returnUrl = localStorage.getItem('peace-federated-return');
            
            // Only proceed if we have a valid state and we're returning from the expected site
            if (state !== expectedState || !returnUrl) {
                console.log('[Peace Protocol] Invalid state or missing return URL, cleaning up');
                cleanUpUrl();
                return;
            }
            
            console.log('[Peace Protocol] Processing code exchange for site:', site);
            
            // Try REST API first, then fall back to AJAX
            async function tryFederatedExchange() {
                // Simple approach: redirect to source site to get the active token directly
                const authUrl = site + '/?peace_get_token=1&return_site=' + encodeURIComponent(window.location.origin) + '&state=' + encodeURIComponent(state);
                console.log('[Peace Protocol] Redirecting to get token:', authUrl);
                window.location.href = authUrl;
            }
            
            tryFederatedExchange();
        }
        
        // Check if we should show the send peace modal (after returning from federated login)
        const showModal = url.searchParams.get('peace_show_modal');
        console.log('[Peace Protocol] peace_show_modal parameter:', showModal);
        
        if (showModal === '1') {
            console.log('[Peace Protocol] Showing send peace modal after federated login');
            // Clean up the URL
            const cleanUrl = window.location.origin + window.location.pathname;
            window.history.replaceState({}, document.title, cleanUrl);
            
            // Show the send peace modal immediately
            if (window.peaceProtocolShowSendPeaceModal) {
                console.log('[Peace Protocol] Calling peaceProtocolShowSendPeaceModal');
                window.peaceProtocolShowSendPeaceModal();
            } else {
                console.log('[Peace Protocol] Send peace modal function not available');
                console.log('[Peace Protocol] Available window functions:', Object.keys(window).filter(key => key.includes('peace')));
                
                // Try again after a short delay in case the function loads later
                setTimeout(() => {
                    if (window.peaceProtocolShowSendPeaceModal) {
                        console.log('[Peace Protocol] Send peace modal function now available, calling it');
                        window.peaceProtocolShowSendPeaceModal();
                    } else {
                        console.log('[Peace Protocol] Send peace modal function still not available after delay');
                    }
                }, 1000);
            }
        }
        
        function cleanUpUrl() {
            const cleanUrl = window.location.origin + window.location.pathname;
            window.history.replaceState({}, document.title, cleanUrl);
        }
    })();
})(); 