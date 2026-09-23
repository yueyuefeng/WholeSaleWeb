<?php
/** Disposable WordPress only: wp eval-file tests/chat.php */
if (!defined('ABSPATH')) { throw new RuntimeException('WordPress required.'); }
$assertions = 0; $ids = []; $previous_user = get_current_user_id();
$_SERVER['REMOTE_ADDR'] = 'test-' . wp_generate_uuid4();
$assert = function ($ok, $message) use (&$assertions) { if (!$ok) { throw new RuntimeException('FAIL: ' . $message); } $assertions++; echo "PASS $message\n"; };
$call = function ($method, $path, $body = [], $token = '') {
    $request = new WP_REST_Request($method, '/shadowalker/v1/chat' . $path);
    $request->set_header('x-shadowalker-nonce', wp_create_nonce('sw_chat'));
    if ($token) { $request->set_header('x-shadowalker-token', $token); }
    $request->set_body_params($body);
    return rest_do_request($request);
};
$bot = function ($reply, $context) { return 'Test AI reply / ' . $context['locale']; };
$broken = function () { throw new RuntimeException('Provider unavailable'); };
$http_mock = null;
try {
    wp_set_current_user(0);
    $a = $call('POST', '', ['locale' => 'zh_CN']); $ids[] = $a->get_data()['id']; $a = $a->get_data();
    $b = $call('POST', ''); $ids[] = $b->get_data()['id']; $b = $b->get_data();
    $assert(strlen($a['token']) === 64 && $a['token'] !== $b['token'], 'unique visitor secrets');
    $assert(get_post_meta($a['id'], '_sw_token', true) !== $a['token'], 'only token hash is stored');
    foreach (['GET','DELETE'] as $method) { $assert($call($method, '/' . $a['id'], [], $b['token'])->get_status() === 403, 'deny cross-session ' . $method); }
    $assert($call('GET', '/' . $a['id'])->get_status() === 403, 'deny missing secret');
    $payload = ['text' => 'Need a conversion kit', 'request_id' => wp_generate_uuid4()];
    $assert($call('POST', '/' . $a['id'] . '/messages', $payload, $b['token'])->get_status() === 403, 'deny cross-session message');
    $assert($call('POST', '/' . $a['id'] . '/messages', ['text' => ['invalid'], 'request_id' => $payload['request_id']], $a['token'])->get_status() === 422, 'reject array message');
    $assert($call('POST', '/' . $a['id'] . '/messages', ['text' => str_repeat('x', 2001), 'request_id' => $payload['request_id']], $a['token'])->get_status() === 422, 'enforce length limit');
    sw_chat_lock($a['id']);
    $assert($call('POST', '/' . $a['id'] . '/messages', $payload, $a['token'])->get_status() === 409, 'reject concurrent writes');
    sw_chat_unlock($a['id']);
    $response = $call('POST', '/' . $a['id'] . '/messages', $payload, $a['token']);
    $assert($response->get_status() === 200 && count($response->get_data()['messages']) === 1, 'persist visitor message without fake reply');
    $response = $call('POST', '/' . $a['id'] . '/messages', $payload, $a['token']);
    $assert(count($response->get_data()['messages']) === 1, 'retry does not duplicate message');
    $response = $call('GET', '/' . $a['id'], [], $a['token']);
    $assert($response->get_data()['messages'][0]['text'] === $payload['text'], 'history survives independent requests');
    $assert(str_contains($response->get_headers()['Cache-Control'], 'no-store'), 'history is not cacheable');
    $assert(!get_post_type_object('sw_chat')->show_in_rest && !get_post_type_object('sw_chat_message')->public, 'no public chat collections');
    $assert(is_wp_error(sw_chat_staff_reply($a['id'], 'Unauthorized')), 'anonymous cannot send staff replies');
    $admins = get_users(['role' => 'administrator', 'number' => 1]); wp_set_current_user($admins[0]->ID);
    $assert(sw_chat_staff_reply($a['id'], 'Please share your wheel size.') === true, 'authorized staff reply persists');
    wp_set_current_user(0);
    $response = $call('GET', '/' . $a['id'], [], $a['token']);
    $assert($response->get_data()['messages'][1]['role'] === 'agent', 'visitor receives staff reply');
    add_filter('sw_chat_bot_reply', $bot, 10, 2); update_post_meta($a['id'], '_sw_mode', 'bot');
    $payload['request_id'] = wp_generate_uuid4();
    $response = $call('POST', '/' . $a['id'] . '/messages', $payload, $a['token']);
    $messages = $response->get_data()['messages'];
    $assert(end($messages)['role'] === 'assistant' && str_contains(end($messages)['text'], 'zh_CN'), 'adapter receives locale and replies as AI');
    $count = count($messages);
    $call('POST', '/' . $a['id'] . '/handoff', [], $a['token']);
    $payload['request_id'] = wp_generate_uuid4();
    $response = $call('POST', '/' . $a['id'] . '/messages', $payload, $a['token']);
    $assert(count($response->get_data()['messages']) === $count + 1 && $response->get_data()['mode'] === 'human', 'handoff stops bot replies');
    remove_filter('sw_chat_bot_reply', $bot, 10); add_filter('sw_chat_bot_reply', $broken); update_post_meta($a['id'], '_sw_mode', 'bot');
    $payload['request_id'] = wp_generate_uuid4();
    $response = $call('POST', '/' . $a['id'] . '/messages', $payload, $a['token']);
    $assert($response->get_status() === 200 && $response->get_data()['mode'] === 'human', 'provider failure keeps message and hands off');
    remove_filter('sw_chat_bot_reply', $broken);
    // Exercise the external adapter contract without contacting any real provider.
    $http_mock = function ($pre, $args, $url) use ($assert) {
        $body = json_decode($args['body'], true);
        $assert($url === 'https://bot.example.test/chat' && $args['headers']['Authorization'] === 'Bearer test-only-key' && $args['redirection'] === 0 && $args['timeout'] === 8, 'HTTPS adapter uses server credential and bounded request');
        $assert($body['locale'] === 'zh_CN' && !isset($body['token']) && count($body['messages']) <= 20, 'HTTPS adapter sends bounded conversation context without visitor secret');
        return ['response' => ['code' => 200], 'headers' => [], 'body' => '{"reply":"Adapter test reply"}'];
    };
    add_filter('pre_http_request', $http_mock, 10, 3);
    $assert(sw_chat_http_reply(sw_chat_bot_context($a['id']), 'https://bot.example.test/chat', 'test-only-key') === 'Adapter test reply', 'HTTPS adapter parses reply contract');
    remove_filter('pre_http_request', $http_mock, 10);
    $bucket = wp_generate_uuid4(); $assert(sw_chat_limit($bucket, 1, 60) && !sw_chat_limit($bucket, 1, 60), 'rate limiter rejects excess');
    update_post_meta($b['id'], '_sw_expires', time() - 1);
    $assert($call('GET', '/' . $b['id'], [], $b['token'])->get_status() === 403, 'expired secret cannot read');
    do_action('sw_chat_cleanup'); $assert(!get_post($b['id']), 'expired session is cleaned up');
    $message_id = $response->get_data()['messages'][0]['id'];
    $assert($call('DELETE', '/' . $a['id'], [], $a['token'])->get_status() === 200 && !get_post($a['id']) && !get_post($message_id), 'owner deletion removes session and messages');
} finally {
    remove_filter('sw_chat_bot_reply', $bot, 10); remove_filter('sw_chat_bot_reply', $broken);
    if ($http_mock) { remove_filter('pre_http_request', $http_mock, 10); }
    foreach ($ids as $id) { sw_chat_delete($id); sw_chat_unlock($id); }
    wp_set_current_user($previous_user);
}
echo "$assertions chat assertions passed.\n";
