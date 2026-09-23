<?php
defined('ABSPATH') || exit;

add_action('init', function () {
    foreach (['sw_chat', 'sw_chat_message'] as $type) {
        register_post_type($type, ['public' => false, 'publicly_queryable' => false, 'show_ui' => false, 'show_in_rest' => false, 'exclude_from_search' => true, 'rewrite' => false, 'supports' => []]);
    }
    if (!wp_next_scheduled('sw_chat_cleanup')) { wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'sw_chat_cleanup'); }
});

function sw_chat_error(string $code, int $status = 400): WP_Error {
    return new WP_Error($code, $code, ['status' => $status]);
}
function sw_chat_bot_enabled(): bool {
    return has_filter('sw_chat_bot_reply') || (defined('SW_CHAT_BOT_URL') && defined('SW_CHAT_BOT_KEY') && SW_CHAT_BOT_URL && SW_CHAT_BOT_KEY);
}
function sw_chat_staff(): bool { return current_user_can('manage_woocommerce') || current_user_can('manage_options'); }
function sw_chat_limit(string $bucket, int $max, int $seconds): bool {
    $key = 'sw_chat_rate_' . hash_hmac('sha256', $bucket . ':' . intdiv(time(), $seconds), wp_salt('nonce'));
    $count = (int) get_transient($key);
    if ($count >= $max) { return false; }
    set_transient($key, $count + 1, $seconds);
    return true;
}
function sw_chat_lock(int $id): bool {
    $key = 'sw_chat_lock_' . $id;
    if ((int) get_option($key) && (int) get_option($key) < time() - 60) { delete_option($key); }
    return add_option($key, time(), '', false);
}
function sw_chat_unlock(int $id): void { delete_option('sw_chat_lock_' . $id); }
function sw_chat_create_permission(WP_REST_Request $request) {
    $origin = $request->get_header('origin');
    $allowed_origins = [];
    foreach ([home_url(), site_url()] as $url) {
        $parts = wp_parse_url($url);
        $port = $parts['port'] ?? ($parts['scheme'] === 'https' ? 443 : 80);
        $default_port = ($parts['scheme'] === 'https' && $port === 443) || ($parts['scheme'] === 'http' && $port === 80);
        $allowed_origins[] = $parts['scheme'] . '://' . $parts['host'] . ($default_port ? '' : ':' . $port);
    }
    if ($origin && !in_array($origin, $allowed_origins, true)) { return sw_chat_error('chat_origin', 403); }
    if (!wp_verify_nonce($request->get_header('x-shadowalker-nonce'), 'sw_chat')) { return sw_chat_error('chat_nonce', 403); }
    return true;
}
function sw_chat_permission(WP_REST_Request $request) {
    $id = (int) $request['id'];
    $post = get_post($id);
    $token = (string) $request->get_header('x-shadowalker-token');
    if (!$post || $post->post_type !== 'sw_chat' || $post->post_status !== 'private' || !preg_match('/^[a-f0-9]{64}$/D', $token) || !hash_equals((string) get_post_meta($id, '_sw_token', true), hash('sha256', $token)) || (int) get_post_meta($id, '_sw_expires', true) <= time()) {
        return sw_chat_error('chat_session', 403);
    }
    return true;
}
function sw_chat_messages(int $id): array {
    $posts = get_posts(['post_type' => 'sw_chat_message', 'post_status' => 'private', 'post_parent' => $id, 'numberposts' => 100, 'orderby' => 'ID', 'order' => 'ASC']);
    return array_map(fn($post) => ['id' => $post->ID, 'role' => get_post_meta($post->ID, '_sw_role', true), 'text' => $post->post_content, 'time' => get_post_time('c', true, $post)], $posts);
}
function sw_chat_snapshot(int $id): WP_REST_Response {
    $response = new WP_REST_Response(['id' => $id, 'mode' => get_post_meta($id, '_sw_mode', true), 'messages' => sw_chat_messages($id)]);
    $response->header('Cache-Control', 'no-store, private');
    return $response;
}
function sw_chat_append(int $id, string $role, string $text, string $request_id = '') {
    $message = wp_insert_post(wp_slash(['post_type' => 'sw_chat_message', 'post_status' => 'private', 'post_parent' => $id, 'post_content' => $text, 'post_title' => $role]), true);
    if (is_wp_error($message) || !$message) { return sw_chat_error('chat_storage', 503); }
    update_post_meta($message, '_sw_role', $role);
    if ($request_id) { update_post_meta($message, '_sw_request', $request_id); }
    update_post_meta($id, '_sw_updated', time());
    return $message;
}
/** Server-side adapter contract: return plain text, null (handoff), or WP_Error. Never expose credentials to JS. */
function sw_chat_bot_context(int $id): array {
    return ['conversation_id' => $id, 'locale' => get_post_meta($id, '_sw_locale', true), 'product_id' => (int) get_post_meta($id, '_sw_product', true), 'messages' => array_slice(sw_chat_messages($id), -20)];
}
function sw_chat_http_reply(array $context, string $url, string $key) {
    if (wp_parse_url($url, PHP_URL_SCHEME) !== 'https') { return sw_chat_error('chat_bot_config'); }
    $result = wp_safe_remote_post($url, ['timeout' => 8, 'redirection' => 0, 'limit_response_size' => 16384, 'headers' => ['Authorization' => 'Bearer ' . $key, 'Content-Type' => 'application/json'], 'body' => wp_json_encode($context)]);
    if (is_wp_error($result) || wp_remote_retrieve_response_code($result) !== 200) { return sw_chat_error('chat_bot_unavailable', 503); }
    $body = json_decode(wp_remote_retrieve_body($result), true);
    return is_array($body) && isset($body['reply']) && is_string($body['reply']) ? $body['reply'] : sw_chat_error('chat_bot_response', 503);
}
function sw_chat_bot_reply(int $id) {
    $context = sw_chat_bot_context($id);
    try {
        $reply = apply_filters('sw_chat_bot_reply', null, $context);
        if ($reply !== null || has_filter('sw_chat_bot_reply')) { return $reply; }
        if (!sw_chat_bot_enabled()) { return null; }
        return sw_chat_http_reply($context, (string) SW_CHAT_BOT_URL, (string) SW_CHAT_BOT_KEY);
    } catch (Throwable $error) { return sw_chat_error('chat_bot_unavailable', 503); }
}
add_action('rest_api_init', function () {
    register_rest_route('shadowalker/v1', '/chat', ['methods' => 'POST', 'permission_callback' => 'sw_chat_create_permission', 'callback' => function ($request) {
        if (!sw_chat_limit('create:' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 12, HOUR_IN_SECONDS)) { return sw_chat_error('chat_rate', 429); }
        $locale = $request->get_param('locale');
        if (!is_string($locale) || !in_array($locale, ['en_US', 'zh_CN', 'de_DE', 'fr_FR', 'es_ES'], true)) { $locale = 'en_US'; }
        $product_id = $request->get_param('product_id');
        $product_id = is_scalar($product_id) ? absint($product_id) : 0;
        if (get_post_type($product_id) !== 'product' || get_post_status($product_id) !== 'publish') { $product_id = 0; }
        $token = bin2hex(random_bytes(32));
        $id = wp_insert_post(['post_type' => 'sw_chat', 'post_status' => 'private', 'post_title' => 'Chat / ' . gmdate('Y-m-d H:i')], true);
        if (is_wp_error($id) || !$id) { return sw_chat_error('chat_storage', 503); }
        update_post_meta($id, '_sw_token', hash('sha256', $token));
        update_post_meta($id, '_sw_locale', $locale);
        update_post_meta($id, '_sw_product', $product_id);
        update_post_meta($id, '_sw_mode', sw_chat_bot_enabled() ? 'bot' : 'human');
        update_post_meta($id, '_sw_expires', time() + 30 * DAY_IN_SECONDS);
        update_post_meta($id, '_sw_updated', time());
        $response = new WP_REST_Response(['id' => $id, 'token' => $token], 201);
        $response->header('Cache-Control', 'no-store, private');
        return $response;
    }]);
    register_rest_route('shadowalker/v1', '/chat/(?P<id>\d+)', [
        ['methods' => 'GET', 'permission_callback' => 'sw_chat_permission', 'callback' => function ($request) {
            $id = (int) $request['id'];
            if (!sw_chat_limit('read:' . $id, 120, MINUTE_IN_SECONDS)) { return sw_chat_error('chat_rate', 429); }
            return sw_chat_snapshot($id);
        }],
        ['methods' => 'DELETE', 'permission_callback' => 'sw_chat_permission', 'callback' => function ($request) {
            $id = (int) $request['id'];
            if (!sw_chat_lock($id)) { return sw_chat_error('chat_busy', 409); }
            try { if (is_wp_error($access = sw_chat_permission($request))) { return $access; } sw_chat_delete($id); return new WP_REST_Response(['deleted' => true]); }
            finally { sw_chat_unlock($id); }
        }],
    ]);
    register_rest_route('shadowalker/v1', '/chat/(?P<id>\d+)/messages', ['methods' => 'POST', 'permission_callback' => 'sw_chat_permission', 'callback' => function ($request) {
        $id = (int) $request['id'];
        $raw = $request->get_param('text'); $client_id = $request->get_param('request_id');
        if (!is_string($raw) || strlen($raw) > 8000 || !is_string($client_id) || !preg_match('/^[a-zA-Z0-9-]{16,64}$/D', $client_id)) { return sw_chat_error('chat_input', 422); }
        $text = trim(sanitize_textarea_field($raw));
        if ($text === '' || mb_strlen($text) > 2000) { return sw_chat_error('chat_input', 422); }
        if (!sw_chat_lock($id)) { return sw_chat_error('chat_busy', 409); }
        try {
            if (is_wp_error($access = sw_chat_permission($request))) { return $access; }
            $existing = get_posts(['post_type' => 'sw_chat_message', 'post_status' => 'private', 'post_parent' => $id, 'meta_key' => '_sw_request', 'meta_value' => $client_id, 'numberposts' => 1]);
            if ($existing) { return sw_chat_snapshot($id); }
            if (count(sw_chat_messages($id)) >= 98) { return sw_chat_error('chat_full', 409); }
            if (!sw_chat_limit('send:' . $id, 30, MINUTE_IN_SECONDS) || !sw_chat_limit('send-ip:' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 120, HOUR_IN_SECONDS)) { return sw_chat_error('chat_rate', 429); }
            $message = sw_chat_append($id, 'visitor', $text, $client_id);
            if (is_wp_error($message)) { return $message; }
            update_post_meta($id, '_sw_unread', 'yes');
            if (get_post_meta($id, '_sw_mode', true) === 'bot') {
                $reply = sw_chat_bot_reply($id);
                if (is_string($reply)) { $reply = trim(sanitize_textarea_field($reply)); }
                if (is_string($reply) && $reply !== '' && mb_strlen($reply) <= 2000) {
                    $saved = sw_chat_append($id, 'assistant', $reply);
                    if (is_wp_error($saved)) { update_post_meta($id, '_sw_mode', 'human'); }
                } else { update_post_meta($id, '_sw_mode', 'human'); }
            }
            return sw_chat_snapshot($id);
        } finally { sw_chat_unlock($id); }
    }]);
    register_rest_route('shadowalker/v1', '/chat/(?P<id>\d+)/handoff', ['methods' => 'POST', 'permission_callback' => 'sw_chat_permission', 'callback' => function ($request) {
        $id = (int) $request['id'];
        if (!sw_chat_lock($id)) { return sw_chat_error('chat_busy', 409); }
        try { if (is_wp_error($access = sw_chat_permission($request))) { return $access; } update_post_meta($id, '_sw_mode', 'human'); update_post_meta($id, '_sw_unread', 'yes'); return sw_chat_snapshot($id); }
        finally { sw_chat_unlock($id); }
    }]);
});
// Errors and successful chat responses must never be shared by browser/CDN caches.
add_filter('rest_post_dispatch', function ($result, $server, $request) {
    if (str_starts_with($request->get_route(), '/shadowalker/v1/chat')) { $result->header('Cache-Control', 'no-store, private'); }
    return $result;
}, 10, 3);
function sw_chat_delete(int $id): void {
    foreach (sw_chat_messages($id) as $message) { wp_delete_post($message['id'], true); }
    wp_delete_post($id, true);
}
add_action('sw_chat_cleanup', function () {
    $ids = get_posts(['post_type' => 'sw_chat', 'post_status' => 'private', 'numberposts' => 100, 'fields' => 'ids', 'meta_query' => [['key' => '_sw_expires', 'value' => time(), 'compare' => '<=', 'type' => 'NUMERIC']]]);
    foreach ($ids as $id) { if (sw_chat_lock($id)) { try { sw_chat_delete($id); } finally { sw_chat_unlock($id); } } }
    if (count($ids) === 100) { wp_schedule_single_event(time() + MINUTE_IN_SECONDS, 'sw_chat_cleanup'); }
});
require_once __DIR__ . '/chat-ui.php';
require_once __DIR__ . '/chat-admin.php';
