<?php
defined('ABSPATH') || exit;

add_action('admin_menu', function () {
    add_menu_page(
        __('Peace Protocol', 'peace-protocol'),
        __('Peace Protocol', 'peace-protocol'),
        'manage_options',
        'peace-protocol',
        'peace_protocol_admin_page',
        'dashicons-heart',
        100
    );
});

function peace_protocol_admin_page()
{
    if (!current_user_can('manage_options')) {
        return;
    }

    if (isset($_POST['peace_protocol_generate_token']) && check_admin_referer('peace_protocol_generate_token')) {
        $tokens = get_option('peace_tokens', []);
        $tokens[] = wp_generate_password(32, true, true);
        update_option('peace_tokens', $tokens);
        echo '<div class="updated"><p>' . __('New token generated.', 'peace-protocol') . '</p></div>';
    }

    if (isset($_POST['peace_protocol_delete_token']) && isset($_POST['token_to_delete']) && check_admin_referer('peace_protocol_delete_token')) {
        $token_to_delete = sanitize_text_field($_POST['token_to_delete']);
        $tokens = get_option('peace_tokens', []);
        $tokens = array_filter($tokens, fn($t) => $t !== $token_to_delete);
        update_option('peace_tokens', array_values($tokens));
        echo '<div class="updated"><p>' . __('Token deleted.', 'peace-protocol') . '</p></div>';
    }

    $tokens = get_option('peace_tokens', []);
    $feeds = get_option('peace_feeds', []);

    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Peace Protocol Settings', 'peace-protocol'); ?></h1>

        <h2><?php esc_html_e('Your Tokens', 'peace-protocol'); ?></h2>
        <form method="post" style="margin-bottom:2em;">
            <?php wp_nonce_field('peace_protocol_generate_token'); ?>
            <button type="submit" name="peace_protocol_generate_token" class="button button-primary">
                <?php esc_html_e('Generate New Token', 'peace-protocol'); ?>
            </button>
        </form>

        <table class="widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Token', 'peace-protocol'); ?></th>
                    <th><?php esc_html_e('Actions', 'peace-protocol'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tokens as $token): ?>
                    <tr>
                        <td><code><?php echo esc_html($token); ?></code></td>
                        <td>
                            <form method="post" style="display:inline;">
                                <?php wp_nonce_field('peace_protocol_delete_token'); ?>
                                <input type="hidden" name="token_to_delete" value="<?php echo esc_attr($token); ?>">
                                <button type="submit" name="peace_protocol_delete_token" class="button button-secondary" onclick="return confirm('<?php echo esc_js(__('Are you sure you want to delete this token?', 'peace-protocol')); ?>');">
                                    <?php esc_html_e('Delete', 'peace-protocol'); ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($tokens)): ?>
                    <tr><td colspan="2"><?php esc_html_e('No tokens found.', 'peace-protocol'); ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <h2><?php esc_html_e('Subscribed Peace Feeds', 'peace-protocol'); ?></h2>
        <?php if (!empty($feeds)): ?>
            <ul>
                <?php foreach ($feeds as $feed): ?>
                    <li><?php echo esc_html($feed); ?></li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p><?php esc_html_e('No peace feeds subscribed yet.', 'peace-protocol'); ?></p>
        <?php endif; ?>
    </div>
    <?php
}
