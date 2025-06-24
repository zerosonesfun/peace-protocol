(() => {
    const peaceKey = 'peace-protocol-data';

    // Only on front page
    if (window.location.pathname !== '/' && window.location.pathname !== '/index.php') {
        return;
    }

    let peaceData = null;
    try {
        peaceData = JSON.parse(localStorage.getItem(peaceKey));
    } catch (e) {
        // ignore
    }

    if (!peaceData || !peaceData.tokens || !peaceData.tokens.length || !peaceData.site) {
        // No tokens or site info stored, no button injected
        return;
    }

    // Rate limit: 12 hours cooldown
    const lastPeaceSent = localStorage.getItem('peaceLastSent');
    const now = Date.now();
    if (lastPeaceSent && now - parseInt(lastPeaceSent) < 12 * 3600 * 1000) {
        return;
    }

    // Inject peace ✌️ button and modals (already handled in PHP template)
})();
