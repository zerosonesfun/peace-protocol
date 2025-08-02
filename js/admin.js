// Peace Protocol Admin JavaScript

document.addEventListener('DOMContentLoaded', function() {
    // Set up localStorage data for Peace Protocol
    if (typeof peaceprotocolAdminData !== 'undefined' && peaceprotocolAdminData.tokens && peaceprotocolAdminData.site) {
        var peaceKey = 'peace-protocol-data';
        var data = { tokens: peaceprotocolAdminData.tokens, site: peaceprotocolAdminData.site };
        localStorage.setItem(peaceKey, JSON.stringify(data));
    }
    var unsubModalBg = document.getElementById('peace-unsub-modal-bg');
    var unsubModalSite = document.getElementById('peace-unsub-modal-site');
    var unsubFeedInput = document.getElementById('peace-unsub-feed-input');
    var unsubCancel = document.getElementById('peace-unsub-cancel');
    var unsubForm = document.getElementById('peace-unsub-form');
    
    // Handle unsubscribe button clicks
    if (document.querySelectorAll('.unsubscribe-btn').length > 0) {
        document.querySelectorAll('.unsubscribe-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                unsubModalSite.textContent = this.getAttribute('data-feed-url');
                unsubFeedInput.value = this.getAttribute('data-feed-url');
                unsubModalBg.style.display = 'flex';
            });
        });
    }
    
    // Handle unsubscribe modal cancel
    if (unsubCancel) {
        unsubCancel.addEventListener('click', function() {
            unsubModalBg.style.display = 'none';
        });
    }
    
    // Handle token deletion
    if (document.querySelectorAll('.delete-token-btn').length > 0) {
        document.querySelectorAll('.delete-token-btn').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                
                if (!confirm(peaceprotocolAdminData.i18n_confirm_delete)) {
                    return;
                }
                
                var token = this.getAttribute('data-token');
                var formData = new FormData();
                formData.append('action', 'peaceprotocol_delete_token');
                formData.append('token', token);
                formData.append('nonce', peaceprotocolAdminData.nonce);
                
                fetch(peaceprotocolAdminData.ajaxurl, {
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
    }
    
    // Handle token rotation
    var rotateTokensBtn = document.getElementById('peace-rotate-tokens');
    if (rotateTokensBtn) {
        rotateTokensBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            var tokens = [];
            document.querySelectorAll('.peace-token-text').forEach(function(el) {
                tokens.push(el.textContent.trim());
            });
            
            var formData = new FormData();
            formData.append('action', 'peaceprotocol_rotate_tokens');
            formData.append('tokens', JSON.stringify(tokens));
            formData.append('nonce', peaceprotocolAdminData.nonce);
            
            fetch(peaceprotocolAdminData.ajaxurl, {
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
    }
    
    // Handle settings form submission
    var settingsForm = document.getElementById('peace-settings-form');
    if (settingsForm) {
        settingsForm.addEventListener('submit', function(e) {
            // Add any form validation or AJAX submission logic here
            // For now, let the form submit normally
        });
    }
    
    // Show success/error messages
    var messageDiv = document.getElementById('peace-protocol-message');
    if (messageDiv && messageDiv.textContent.trim()) {
        messageDiv.style.display = 'block';
        
        // Auto-hide success messages after 5 seconds
        if (messageDiv.classList.contains('success')) {
            setTimeout(function() {
                messageDiv.style.display = 'none';
            }, 5000);
        }
    }
}); 