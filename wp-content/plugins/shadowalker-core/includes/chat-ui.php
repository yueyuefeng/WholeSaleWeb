<?php
defined('ABSPATH') || exit;
add_action('wp_enqueue_scripts', function () {
    $base = plugins_url('assets/', dirname(__DIR__) . '/shadowalker-core.php');
    wp_register_style('shadowalker-chat', $base . 'chat.css', [], '1.3.0');
    wp_register_script('shadowalker-chat', $base . 'chat.js', [], '1.3.0', true);
    wp_script_add_data('shadowalker-chat', 'strategy', 'defer');
    $post = get_post();
    if (is_page('contact') || is_page_template('page-contact.php') || ($post && has_shortcode($post->post_content, 'shadowalker_chat'))) {
        wp_enqueue_style('shadowalker-chat'); wp_enqueue_script('shadowalker-chat');
    }
});
add_shortcode('shadowalker_chat', function () {
    wp_enqueue_style('shadowalker-chat'); wp_enqueue_script('shadowalker-chat');
    $product_id = isset($_GET['product_id']) && is_scalar($_GET['product_id']) ? absint($_GET['product_id']) : 0;
    $product = get_post($product_id);
    if (!$product || $product->post_type !== 'product' || $product->post_status !== 'publish') { $product = null; $product_id = 0; }
    $plan=isset($_GET['plan'])?wp_unslash($_GET['plan']):''; $selection=null;
    if ($plan!=='') {
        $selection=sw_selection_read_plan($plan);
        if (!is_wp_error($selection)) { $product_id=$selection['product_id']; $product=get_post($product_id); }
    }
    $config = ['base' => rest_url('shadowalker/v1/chat'), 'nonce' => wp_create_nonce('sw_chat'), 'restNonce' => wp_create_nonce('wp_rest'), 'locale' => get_locale(), 'product_id' => $product_id, 'bot' => sw_chat_bot_enabled(), 'strings' => [
        'visitor' => __('You', 'shadowalker'), 'assistant' => __('AI assistant', 'shadowalker'), 'agent' => __('Shadowalker team', 'shadowalker'),
        'sending' => __('Sending…', 'shadowalker'), 'saved' => __('Message saved. Waiting for a team reply.', 'shadowalker'), 'ready' => __('AI assistance enabled', 'shadowalker'),
        'error' => __('Unable to connect. Your draft is kept; please try again.', 'shadowalker'), 'expired' => __('This conversation has expired. Start a new conversation to continue.', 'shadowalker'),
        'rate' => __('Too many requests. Please wait a minute and try again.', 'shadowalker'), 'full' => __('This conversation is full. Start a new conversation to continue.', 'shadowalker'),
        'confirm' => __('Delete this conversation and all its messages? This cannot be undone.', 'shadowalker'), 'deleted' => __('Conversation deleted. You can start again.', 'shadowalker'),
        'human' => __('Human support requested. Replies appear here when available.', 'shadowalker'), 'replied' => __('A team member has replied.', 'shadowalker'),
        'reload' => __('Your session expired. Reload this page; your draft is still here.', 'shadowalker'),
    ]];
    $config['plan']=is_string($plan)?$plan:'';
    $config['strings']['plan']=__('This plan is unavailable or has expired. Please create a new plan.', 'shadowalker');
    $privacy = get_privacy_policy_url() ?: home_url('/privacy-policy/');
    ob_start(); ?>
    <section class="sw-chat" data-chat-config="<?php echo esc_attr(wp_json_encode($config)); ?>" aria-label="<?php esc_attr_e('Chat with Shadowalker', 'shadowalker'); ?>">
      <aside class="sw-chat-side">
        <p class="eyebrow">SHADOWALKER / <?php esc_html_e('CONVERSATIONS', 'shadowalker'); ?></p>
        <h2><?php esc_html_e('Your next move starts here.', 'shadowalker'); ?></h2>
        <p><?php esc_html_e('Ask about a ride, a build, or a destination. No contact form required.', 'shadowalker'); ?></p>
        <div class="sw-chat-topics" aria-label="<?php esc_attr_e('Conversation starters', 'shadowalker'); ?>">
          <?php foreach ([__('Golf carts', 'shadowalker'), __('Electric boats', 'shadowalker'), __('Electric bikes', 'shadowalker'), __('Conversion kits', 'shadowalker')] as $topic) { ?>
          <button type="button" class="sw-chat-topic"><?php echo esc_html($topic); ?> <span aria-hidden="true">↗</span></button>
          <?php } ?>
        </div>
        <p class="sw-chat-note"><?php esc_html_e('Conversation access lasts 30 days. Keep this tab for replies; no email is required.', 'shadowalker'); ?></p>
      </aside>
      <div class="sw-chat-panel">
        <div class="sw-chat-header"><div><strong><?php esc_html_e('Chat with Shadowalker', 'shadowalker'); ?></strong><p><?php echo sw_chat_bot_enabled() ? esc_html__('AI assistance enabled', 'shadowalker') : esc_html__('Team inbox · replies are not instant', 'shadowalker'); ?></p></div><span aria-hidden="true">S∕</span></div>
        <?php if ($product) { ?><div class="sw-chat-product"><?php esc_html_e('Product', 'shadowalker'); ?>: <?php echo esc_html($product->post_title); ?></div><?php } ?>
        <?php if ($selection && !is_wp_error($selection)) { ?><details class="sw-chat-plan" open><summary><?php esc_html_e('Your selection is shared with our team', 'shadowalker'); ?></summary><pre><?php echo esc_html(implode("\n",sw_selection_summary($selection))); ?></pre></details><?php } elseif (is_wp_error($selection)) { ?><p role="alert"><?php esc_html_e('This plan is unavailable or has expired. Please create a new plan.', 'shadowalker'); ?></p><?php } ?>
        <div class="sw-chat-log" role="log" aria-live="polite" aria-relevant="additions" tabindex="0" aria-label="<?php esc_attr_e('Conversation', 'shadowalker'); ?>">
          <div class="sw-chat-message sw-chat-welcome"><span><?php esc_html_e('Welcome', 'shadowalker'); ?></span><p><?php esc_html_e('What would you like to explore? Send a message to start the conversation.', 'shadowalker'); ?></p></div>
        </div>
        <p class="sw-chat-status" role="status"></p>
        <form class="sw-chat-composer">
          <label class="screen-reader-text" for="sw-chat-text"><?php esc_html_e('Your message', 'shadowalker'); ?></label>
          <textarea id="sw-chat-text" name="message" rows="3" maxlength="2000" required placeholder="<?php esc_attr_e('Write your message…', 'shadowalker'); ?>"></textarea>
          <div class="sw-chat-send"><span><?php esc_html_e('Enter to send · Shift + Enter for a new line', 'shadowalker'); ?></span><button type="submit" class="button"><?php esc_html_e('Send message', 'shadowalker'); ?> ↗</button></div>
        </form>
        <div class="sw-chat-tools"><button type="button" data-handoff><?php esc_html_e('Ask for a person', 'shadowalker'); ?></button><button type="button" data-delete><?php esc_html_e('Delete conversation', 'shadowalker'); ?></button><button type="button" data-new hidden><?php esc_html_e('Start a new conversation', 'shadowalker'); ?></button></div>
        <p class="sw-chat-privacy"><?php esc_html_e('Avoid payment details or sensitive information. AI replies, when enabled, are labelled and may be inaccurate; confirm specifications with our team.', 'shadowalker'); ?> <a href="<?php echo esc_url($privacy); ?>"><?php esc_html_e('Privacy policy', 'shadowalker'); ?></a></p>
        <?php if (sw_chat_bot_enabled()) { ?><p class="sw-chat-privacy"><?php esc_html_e('Messages may be processed by our configured AI provider.', 'shadowalker'); ?></p><?php } ?>
        <noscript><p><?php esc_html_e('Enable JavaScript to chat, or use the detailed inquiry form below.', 'shadowalker'); ?></p></noscript>
      </div>
    </section>
    <?php return ob_get_clean();
});
