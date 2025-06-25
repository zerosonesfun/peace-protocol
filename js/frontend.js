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

    // Federated login logic
    window.peaceProtocolFederatedLogin = function(options = {}) {
        console.log('[Peace Protocol] FederatedLogin called', options);
        // options: { onComplete(site, token), onError(err) }
        // Always show modal to enter domain, regardless of existing identities
        
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
        
        // Handle authorization code return (after direct authorization request)
        if (authCode && authSite && authState) {
            const expectedState = localStorage.getItem('peace-federated-state');
            const returnUrl = localStorage.getItem('peace-federated-return');
            
            console.log('[Peace Protocol] Expected state:', expectedState, 'Got state:', authState);
            console.log('[Peace Protocol] Return URL:', returnUrl);
            
            // Only proceed if we have a valid state and we're returning from the expected site
            if (authState !== expectedState || !returnUrl) {
                console.log('[Peace Protocol] Invalid state or missing return URL, cleaning up');
                console.log('[Peace Protocol] Expected state:', expectedState, 'Got state:', authState);
                console.log('[Peace Protocol] Return URL:', returnUrl);
                cleanUpUrl();
                return;
            }
            
            console.log('[Peace Protocol] Processing authorization code return for site:', authSite);
            console.log('[Peace Protocol] Authorization code received:', authCode);
            
            // Store the authorization code in localStorage
            let authorizations = [];
            try {
                const stored = localStorage.getItem('peace-protocol-authorizations');
                console.log('[Peace Protocol] Raw stored authorizations:', stored);
                if (stored) authorizations = JSON.parse(stored);
                if (!Array.isArray(authorizations)) authorizations = [];
            } catch (e) {
                console.error('[Peace Protocol] Error parsing stored authorizations:', e);
                authorizations = [];
            }
            
            console.log('[Peace Protocol] Current authorizations before storage:', authorizations);
            
            // Remove any existing authorization for this site
            authorizations = authorizations.filter(auth => auth.site !== authSite);
            console.log('[Peace Protocol] Authorizations after filtering:', authorizations);
            
            // Add new authorization
            authorizations.push({ site: authSite, code: authCode });
            console.log('[Peace Protocol] Authorizations after adding new code:', authorizations);
            
            // Store in localStorage
            localStorage.setItem('peace-protocol-authorizations', JSON.stringify(authorizations));
            console.log('[Peace Protocol] Authorizations stored in localStorage');
            
            // Verify storage worked
            const storedAuthorizations = JSON.parse(localStorage.getItem('peace-protocol-authorizations') || '[]');
            console.log('[Peace Protocol] Verification - stored authorizations:', storedAuthorizations);
            
            localStorage.removeItem('peace-federated-state');
            localStorage.removeItem('peace-federated-return');
            
            // Clean up URL and redirect back
            cleanUpUrl();
            if (returnUrl) {
                // Add a parameter to trigger the send peace modal
                const separator = returnUrl.includes('?') ? '&' : '?';
                const finalUrl = returnUrl + separator + 'peace_show_modal=1';
                console.log('[Peace Protocol] Redirecting to:', finalUrl);
                window.location.href = finalUrl;
            }
            return;
        } else {
            console.log('[Peace Protocol] No authorization code parameters found in URL');
        }
        
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
        if (showModal === '1') {
            console.log('[Peace Protocol] Showing send peace modal after federated login');
            // Clean up the URL
            const cleanUrl = window.location.origin + window.location.pathname;
            window.history.replaceState({}, document.title, cleanUrl);
            
            // Show the send peace modal after a short delay to ensure everything is loaded
            setTimeout(() => {
                if (window.peaceProtocolShowSendPeaceModal) {
                    window.peaceProtocolShowSendPeaceModal();
                } else {
                    console.log('[Peace Protocol] Send peace modal function not available');
                }
            }, 500);
        }
        
        function cleanUpUrl() {
            const cleanUrl = window.location.origin + window.location.pathname;
            window.history.replaceState({}, document.title, cleanUrl);
        }
    })();
})(); 