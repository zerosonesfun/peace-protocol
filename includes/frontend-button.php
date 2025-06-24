<?php
defined('ABSPATH') || exit;

add_action('wp_enqueue_scripts', function () {
    if (!is_front_page()) {
        return;
    }
    wp_enqueue_script('peace-protocol-frontend', PEACE_PROTOCOL_URL . 'js/frontend.js', ['jquery'], PEACE_PROTOCOL_VERSION, true);

    wp_localize_script('peace-protocol-frontend', 'peaceData', [
        'restUrl' => rest_url('pass-the-peace/v1/receive'),
        'nonce' => wp_create_nonce('wp_rest'),
        'i18n_confirm' => __('Do you want to give peace to this site?', 'peace-protocol'),
        'i18n_yes' => __('Yes', 'peace-protocol'),
        'i18n_no' => __('Cancel', 'peace-protocol'),
        'i18n_note' => __('Optional note (max 50 characters):', 'peace-protocol'),
        'i18n_send' => __('Send Peace', 'peace-protocol'),
        'i18n_cancel' => __('Cancel', 'peace-protocol'),
    ]);
});

add_action('wp_footer', function () {
    if (!is_front_page()) {
        return;
    }
    ?>
    <style>
        #peace-protocol-button {
            position: fixed;
            top: 1rem;
            right: 1rem;
            background: transparent;
            border: none;
            font-size: 2rem;
            cursor: pointer;
            z-index: 99999;
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
    </style>
    <button id="peace-protocol-button" title="<?php esc_attr_e('Give Peace ✌️', 'peace-protocol'); ?>">✌️</button>

    <div id="peace-modal" role="dialog" aria-modal="true" aria-labelledby="peace-modal-title">
        <div id="peace-modal-content">
            <h2 id="peace-modal-title"><?php esc_html_e('Give Peace?', 'peace-protocol'); ?></h2>
            <p id="peace-modal-question"><?php esc_html_e('Do you want to give peace to this site?', 'peace-protocol'); ?></p>
            <textarea id="peace-note" maxlength="50" placeholder="<?php esc_attr_e('Optional note (max 50 characters)', 'peace-protocol'); ?>"></textarea>
            <div>
                <button id="peace-send"><?php esc_html_e('Send Peace', 'peace-protocol'); ?></button>
                <button id="peace-cancel"><?php esc_html_e('Cancel', 'peace-protocol'); ?></button>
            </div>
        </div>
    </div>
    <script>
    (() => {
        const btn = document.getElementById('peace-protocol-button');
        const modal = document.getElementById('peace-modal');
        const sendBtn = document.getElementById('peace-send');
        const cancelBtn = document.getElementById('peace-cancel');
        const noteEl = document.getElementById('peace-note');

        btn.addEventListener('click', () => {
            modal.style.display = 'flex';
            noteEl.value = '';
            noteEl.focus();
        });

        cancelBtn.addEventListener('click', () => {
            modal.style.display = 'none';
        });

        sendBtn.addEventListener('click', async () => {
            const note = noteEl.value.trim();
            if (note.length > 50) {
                alert('<?php echo esc_js(__('Note must be 50 characters or less.', 'peace-protocol')); ?>');
                return;
            }
            // Read token and site info from localStorage
            let peaceData = null;
            try {
                peaceData = JSON.parse(localStorage.getItem('peace-protocol-data'));
            } catch {}
            if (!peaceData || !peaceData.tokens || !peaceData.tokens.length || !peaceData.site) {
                alert('<?php echo esc_js(__('You must be logged in as a site admin with Peace Protocol enabled.', 'peace-protocol')); ?>');
                modal.style.display = 'none';
                return;
            }

            // Optimistic UI: disable button & close modal
            sendBtn.disabled = true;
            modal.style.display = 'none';

            try {
                const response = await fetch('<?php echo esc_url(rest_url('pass-the-peace/v1/receive')); ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': '<?php echo wp_create_nonce('wp_rest'); ?>',
                    },
                    body: JSON.stringify({
                        from_site: peaceData.site,
                        token: peaceData.tokens[0],
                        note,
                    }),
                });

                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }

                // Optionally refresh or emit event here
                alert('<?php echo esc_js(__('Peace sent! ✌️', 'peace-protocol')); ?>');
            } catch (err) {
                alert('<?php echo esc_js(__('Failed to send peace. Please try again.', 'peace-protocol')); ?>');
            }
            sendBtn.disabled = false;
        });
    })();
    </script>
    <?php
});
