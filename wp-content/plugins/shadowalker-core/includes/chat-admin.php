<?php
defined('ABSPATH') || exit;
add_action('admin_menu', function () {
    add_menu_page(__('Chat inbox', 'shadowalker'), __('Chat inbox', 'shadowalker'), current_user_can('manage_options') ? 'manage_options' : 'manage_woocommerce', 'shadowalker-chat', 'sw_chat_admin_page', 'dashicons-format-chat', 57);
});
function sw_chat_staff_reply(int $id, string $text) {
    if (!sw_chat_staff()) { return sw_chat_error('chat_forbidden', 403); }
    $text = trim(sanitize_textarea_field($text));
    if ($text === '' || mb_strlen($text) > 2000) { return sw_chat_error('chat_input', 422); }
    if (!sw_chat_lock($id)) { return sw_chat_error('chat_busy', 409); }
    try {
        if (get_post_type($id) !== 'sw_chat' || get_post_status($id) !== 'private' || (int) get_post_meta($id, '_sw_expires', true) <= time()) { return sw_chat_error('chat_session', 404); }
        if (count(sw_chat_messages($id)) >= 100) { return sw_chat_error('chat_full', 409); }
        $result = sw_chat_append($id, 'agent', $text);
        if (is_wp_error($result)) { return $result; }
        update_post_meta($id, '_sw_mode', 'human');
        update_post_meta($id, '_sw_unread', 'no');
        return true;
    } finally { sw_chat_unlock($id); }
}
add_action('admin_post_sw_chat_reply', function () {
    if (!sw_chat_staff()) { wp_die('Forbidden', '', ['response' => 403]); }
    check_admin_referer('sw_chat_reply');
    $id = absint(sw_post_string('conversation'));
    $result = sw_chat_staff_reply($id, sw_post_string('reply'));
    if (is_wp_error($result)) { wp_die(esc_html($result->get_error_code()), '', ['response' => $result->get_error_data()['status'], 'back_link' => true]); }
    wp_safe_redirect(admin_url('admin.php?page=shadowalker-chat&conversation=' . $id)); exit;
});
function sw_chat_admin_page(): void {
    if (!sw_chat_staff()) { return; }
    $id = isset($_GET['conversation']) && is_scalar($_GET['conversation']) ? absint($_GET['conversation']) : 0;
    echo '<div class="wrap"><h1>' . esc_html__('Chat inbox', 'shadowalker') . '</h1><p>' . esc_html__('Refresh to check for new messages. Replies are shown in the visitor’s open chat tab.', 'shadowalker') . '</p>';
    if ($id && get_post_type($id) === 'sw_chat') {
        echo '<p><a href="' . esc_url(admin_url('admin.php?page=shadowalker-chat')) . '">← ' . esc_html__('Chat inbox', 'shadowalker') . '</a></p>';
        $product = get_post((int) get_post_meta($id, '_sw_product', true));
        echo '<h2>#' . (int) $id . ' · ' . esc_html(get_post_meta($id, '_sw_locale', true)) . '</h2>';
        if ($product && $product->post_type === 'product') { echo '<p>' . esc_html__('Product', 'shadowalker') . ': ' . esc_html($product->post_title) . '</p>'; }
        $selection=get_post_meta($id,'_sw_selection',true);
        if (is_array($selection)) { echo '<h3>'.esc_html__('Plan reference', 'shadowalker').'</h3><pre style="max-width:850px;white-space:pre-wrap;overflow-wrap:anywhere">'.esc_html(implode("\n",sw_selection_summary(sw_selection_refresh_context($selection)))).'</pre>'; }
        echo '<div style="max-width:850px;background:white;padding:20px;border:1px solid #ccd0d4">';
        foreach (sw_chat_messages($id) as $message) {
            $roles = ['visitor' => __('Visitor', 'shadowalker'), 'agent' => __('Shadowalker team', 'shadowalker'), 'assistant' => __('AI assistant', 'shadowalker')];
            echo '<article style="padding:14px;border-bottom:1px solid #ddd"><strong>' . esc_html($roles[$message['role']] ?? $message['role']) . '</strong> <small>' . esc_html($message['time']) . '</small><p style="white-space:pre-wrap;overflow-wrap:anywhere">' . esc_html($message['text']) . '</p></article>';
        }
        echo '</div><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="max-width:850px;margin-top:20px">';
        wp_nonce_field('sw_chat_reply');
        echo '<input type="hidden" name="action" value="sw_chat_reply"><input type="hidden" name="conversation" value="' . (int) $id . '"><label for="sw-staff-reply">' . esc_html__('Your reply', 'shadowalker') . '</label><textarea id="sw-staff-reply" name="reply" maxlength="2000" required rows="5" style="display:block;width:100%;margin:10px 0"></textarea>';
        submit_button(__('Send reply', 'shadowalker')); echo '</form>';
    } else {
        $page = isset($_GET['paged']) && is_scalar($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
        $query = new WP_Query(['post_type' => 'sw_chat', 'post_status' => 'private', 'posts_per_page' => 30, 'paged' => $page, 'meta_key' => '_sw_updated', 'orderby' => 'meta_value_num', 'order' => 'DESC']);
        echo '<table class="widefat striped"><thead><tr><th>ID</th><th>' . esc_html__('Language', 'shadowalker') . '</th><th>' . esc_html__('Status', 'shadowalker') . '</th><th>' . esc_html__('Last activity', 'shadowalker') . '</th></tr></thead><tbody>';
        foreach ($query->posts as $chat) {
            echo '<tr><td><a href="' . esc_url(admin_url('admin.php?page=shadowalker-chat&conversation=' . $chat->ID)) . '">#' . (int) $chat->ID . '</a></td><td>' . esc_html(get_post_meta($chat->ID, '_sw_locale', true)) . '</td><td>' . (get_post_meta($chat->ID, '_sw_unread', true) === 'yes' ? esc_html__('Needs reply', 'shadowalker') : esc_html__('Replied', 'shadowalker')) . '</td><td>' . esc_html(wp_date('Y-m-d H:i', (int) get_post_meta($chat->ID, '_sw_updated', true))) . '</td></tr>';
        }
        echo '</tbody></table>';
        echo wp_kses_post(paginate_links(['base' => add_query_arg('paged', '%#%', admin_url('admin.php?page=shadowalker-chat')), 'format' => '', 'current' => $page, 'total' => $query->max_num_pages]));
    }
    echo '</div>';
}
