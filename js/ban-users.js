// Peace Protocol Ban Users JavaScript

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
    if (document.querySelectorAll('.peace-ban-user').length > 0) {
        document.querySelectorAll('.peace-ban-user').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                var userId = this.getAttribute('data-user-id');
                var userName = this.getAttribute('data-user-name');
                
                banModalTitle.textContent = peaceprotocolBanData.i18n_ban_user;
                banModalUser.textContent = peaceprotocolBanData.i18n_ban_user_text + ' ' + userName;
                banUserId.value = userId;
                banAction.value = 'ban';
                banReason.value = '';
                banSubmit.textContent = peaceprotocolBanData.i18n_ban_user;
                banSubmit.className = 'button button-primary';
                banModalBg.style.display = 'flex';
            });
        });
    }
    
    // Unban user
    if (document.querySelectorAll('.peace-unban-user').length > 0) {
        document.querySelectorAll('.peace-unban-user').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                var userId = this.getAttribute('data-user-id');
                var userName = this.getAttribute('data-user-name');
                
                banModalTitle.textContent = peaceprotocolBanData.i18n_unban_user;
                banModalUser.textContent = peaceprotocolBanData.i18n_unban_user_text + ' ' + userName;
                banUserId.value = userId;
                banAction.value = 'unban';
                banReason.value = '';
                banSubmit.textContent = peaceprotocolBanData.i18n_unban_user;
                banSubmit.className = 'button button-primary';
                banModalBg.style.display = 'flex';
            });
        });
    }
    
    // Handle modal cancel
    if (banCancel) {
        banCancel.addEventListener('click', function() {
            banModalBg.style.display = 'none';
        });
    }
    
    // Handle form submission
    if (banForm) {
        banForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            var formData = new FormData();
            formData.append('action', 'peaceprotocol_ban_user');
            formData.append('user_id', banUserId.value);
            formData.append('ban_action', banAction.value);
            formData.append('reason', banReason.value);
            formData.append('nonce', peaceprotocolBanData.nonce);
            
            fetch(peaceprotocolBanData.ajaxurl, {
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
}); 