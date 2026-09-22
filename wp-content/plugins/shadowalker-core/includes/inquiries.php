<?php
defined('ABSPATH') || exit;
add_action('init', function () {
    register_post_type('sw_inquiry', [
        'labels' => ['name' => __('Inquiries', 'shadowalker'), 'singular_name' => __('Inquiry', 'shadowalker')],
        'public' => false, 'publicly_queryable' => false, 'show_ui' => true, 'show_in_rest' => false,
        'exclude_from_search' => true, 'menu_icon' => 'dashicons-email-alt', 'supports' => ['title', 'editor'],
        'capabilities' => ['edit_post' => 'manage_woocommerce', 'read_post' => 'manage_woocommerce', 'delete_post' => 'manage_woocommerce', 'edit_posts' => 'manage_woocommerce', 'edit_others_posts' => 'manage_woocommerce', 'publish_posts' => 'manage_woocommerce', 'read_private_posts' => 'manage_woocommerce', 'delete_posts' => 'manage_woocommerce', 'delete_private_posts' => 'manage_woocommerce', 'delete_published_posts' => 'manage_woocommerce', 'delete_others_posts' => 'manage_woocommerce', 'edit_private_posts' => 'manage_woocommerce', 'edit_published_posts' => 'manage_woocommerce', 'create_posts' => 'do_not_allow'],
        'map_meta_cap' => false,
    ]);
});
function sw_post_string(string $key): string {
    return isset($_POST[$key]) && is_string($_POST[$key]) ? wp_unslash($_POST[$key]) : '';
}
function sw_validate_inquiry(array $data) {
    $name = sanitize_text_field($data['name'] ?? '');
    $email = sanitize_email($data['email'] ?? '');
    $country = sanitize_text_field($data['country'] ?? '');
    $message = sanitize_textarea_field($data['message'] ?? '');
    $quantity_raw = (string) ($data['quantity'] ?? '');
    $quantity = ctype_digit($quantity_raw) ? (int) $quantity_raw : 0;
    $countries = class_exists('WC_Countries') ? (new WC_Countries())->get_countries() : [];
    if (strlen($name) < 2 || strlen($name) > 120 || !is_email($email) || strlen($email) > 254 || !isset($countries[$country]) || strlen($message) < 10 || strlen($message) > 5000 || $quantity < 1 || $quantity > 10000 || empty($data['consent'])) {
        return new WP_Error('invalid', __('Check your name, email, country, quantity, message and privacy consent.', 'shadowalker'));
    }
    return compact('name', 'email', 'country', 'message', 'quantity');
}
add_shortcode('shadowalker_inquiry', function () {
    if (!class_exists('WooCommerce')) { return '<p>' . esc_html__('The inquiry form is currently unavailable.', 'shadowalker') . '</p>'; }
    $product_id = isset($_GET['product_id']) && is_scalar($_GET['product_id']) ? absint($_GET['product_id']) : 0;
    $product = $product_id ? wc_get_product($product_id) : false;
    if ($product && $product->get_status() !== 'publish') { $product = false; }
    $return_url = get_permalink();
    $privacy_url = get_privacy_policy_url() ?: (function_exists('sw_page_url') ? sw_page_url('privacy-policy') : home_url('/privacy-policy/'));
    ob_start();
    if (isset($_GET['inquiry']) && $_GET['inquiry'] === 'received') {
        echo '<div class="sw-notice" role="status">' . esc_html__('Thank you. Your inquiry has been saved. Our team will review it.', 'shadowalker') . '</div>';
    }
    ?>
    <form class="sw-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
    <?php wp_nonce_field('sw_inquiry', 'sw_nonce'); ?>
    <input type="hidden" name="action" value="sw_inquiry"><input type="hidden" name="return_url" value="<?php echo esc_url($return_url); ?>"><input type="hidden" name="product_id" value="<?php echo $product ? (int) $product->get_id() : 0; ?>">
    <?php if ($product) { ?><p><strong><?php esc_html_e('Product', 'shadowalker'); ?>:</strong> <?php echo esc_html($product->get_name()); ?></p><?php } ?>
    <div class="honeypot" aria-hidden="true"><label>Leave this empty<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
    <div class="sw-form-grid"><label><?php esc_html_e('Your name', 'shadowalker'); ?> *<input name="name" required maxlength="120" autocomplete="name"></label><label><?php esc_html_e('Email address', 'shadowalker'); ?> *<input type="email" name="email" required maxlength="254" autocomplete="email"></label><label><?php esc_html_e('Destination country', 'shadowalker'); ?> *<select name="country" required autocomplete="country"><option value=""><?php esc_html_e('Select country', 'shadowalker'); ?></option><?php foreach ((new WC_Countries())->get_countries() as $code => $name) { ?><option value="<?php echo esc_attr($code); ?>"><?php echo esc_html($name); ?></option><?php } ?></select></label><label><?php esc_html_e('Quantity', 'shadowalker'); ?> *<input type="number" name="quantity" min="1" max="10000" step="1" value="1" required></label></div>
    <label><?php esc_html_e('Tell us about your project', 'shadowalker'); ?> *<textarea name="message" minlength="10" maxlength="5000" required></textarea></label>
    <label class="consent"><input type="checkbox" name="consent" value="1" required><span><?php esc_html_e('I agree to the processing of my inquiry as described in the', 'shadowalker'); ?> <a href="<?php echo esc_url($privacy_url); ?>"><?php esc_html_e('Privacy policy', 'shadowalker'); ?></a>.</span></label>
    <button class="button" type="submit"><?php esc_html_e('Send inquiry', 'shadowalker'); ?> ↗</button></form>
    <?php return ob_get_clean();
});
add_action('admin_post_sw_inquiry', 'sw_submit_inquiry');
add_action('admin_post_nopriv_sw_inquiry', 'sw_submit_inquiry');
function sw_submit_inquiry(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { wp_die('Method not allowed.', '', ['response' => 405]); }
    if (!wp_verify_nonce(sw_post_string('sw_nonce'), 'sw_inquiry')) { wp_die(esc_html__('Your session expired. Reload the form and try again.', 'shadowalker'), '', ['response' => 403, 'back_link' => true]); }
    if (sw_post_string('website') !== '') { wp_die('Submission rejected.', '', ['response' => 400]); }
    $fields = [];
    foreach (['name', 'email', 'country', 'message', 'quantity', 'consent'] as $field) { $fields[$field] = sw_post_string($field); }
    $result = sw_validate_inquiry($fields);
    if (is_wp_error($result)) { wp_die(esc_html($result->get_error_message()), '', ['response' => 422, 'back_link' => true]); }
    // Only the direct peer is trusted. Configure real client IP at the trusted proxy, never trust arbitrary forwarding headers.
    $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : 'unknown';
    $ip_key = 'sw_ip_' . hash_hmac('sha256', $ip, wp_salt('nonce'));
    $email_key = 'sw_em_' . hash_hmac('sha256', strtolower($result['email']), wp_salt('nonce'));
    if ((int) get_transient($ip_key) >= 10 || get_transient($email_key)) { wp_die(esc_html__('Please wait before sending another inquiry.', 'shadowalker'), '', ['response' => 429, 'back_link' => true]); }
    $product_id = absint(sw_post_string('product_id'));
    $product = function_exists('wc_get_product') ? wc_get_product($product_id) : false;
    if ($product && $product->get_status() !== 'publish') { $product = false; }
    $product_name = $product ? $product->get_name() . ' (#' . $product->get_id() . ')' : 'General inquiry';
    $content = "Name: {$result['name']}\nEmail: {$result['email']}\nCountry: {$result['country']}\nQuantity: {$result['quantity']}\nProduct: $product_name\nConsent: yes\n\n{$result['message']}";
    $id = wp_insert_post(wp_slash(['post_type' => 'sw_inquiry', 'post_status' => 'private', 'post_title' => $result['name'] . ' — ' . $product_name, 'post_content' => $content]), true);
    if (is_wp_error($id) || !$id) { wp_die(esc_html__('We could not save your inquiry. Please try again.', 'shadowalker'), '', ['response' => 503, 'back_link' => true]); }
    update_post_meta($id, '_sw_email', $result['email']);
    update_post_meta($id, '_sw_consent_at', gmdate('c'));
    update_post_meta($id, '_sw_product_id', $product ? $product->get_id() : 0);
    set_transient($ip_key, (int) get_transient($ip_key) + 1, HOUR_IN_SECONDS);
    set_transient($email_key, 1, MINUTE_IN_SECONDS);
    // Persist first. A failed notification never loses the inquiry; inspect this flag in the admin list.
    $sent = wp_mail(get_option('admin_email'), '[Shadowalker] Inquiry #' . $id, $content);
    update_post_meta($id, '_sw_notification_sent', $sent ? 'yes' : 'no');
    $return = wp_validate_redirect(esc_url_raw(sw_post_string('return_url')), home_url('/contact/'));
    wp_safe_redirect(add_query_arg('inquiry', 'received', $return), 303);
    exit;
}
add_filter('manage_sw_inquiry_posts_columns', function ($columns) { $columns['sw_mail'] = __('Notification', 'shadowalker'); return $columns; });
add_action('manage_sw_inquiry_posts_custom_column', function ($column, $id) {
    if ($column === 'sw_mail') { echo get_post_meta($id, '_sw_notification_sent', true) === 'yes' ? esc_html__('Sent', 'shadowalker') : esc_html__('Check email delivery', 'shadowalker'); }
}, 10, 2);
add_filter('wp_privacy_personal_data_exporters', function ($exporters) {
    $exporters['shadowalker-inquiries'] = ['exporter_friendly_name' => 'Shadowalker inquiries', 'callback' => 'sw_export_inquiries'];
    return $exporters;
});
function sw_export_inquiries($email, $page = 1): array {
    $posts = get_posts(['post_type' => 'sw_inquiry', 'post_status' => 'private', 'posts_per_page' => 50, 'paged' => (int) $page, 'meta_key' => '_sw_email', 'meta_value' => sanitize_email($email)]);
    $data = [];
    foreach ($posts as $post) { $data[] = ['group_id' => 'shadowalker-inquiries', 'group_label' => 'Shadowalker inquiries', 'item_id' => 'inquiry-' . $post->ID, 'data' => [['name' => 'Inquiry', 'value' => $post->post_content], ['name' => 'Date', 'value' => $post->post_date_gmt]]]; }
    return ['data' => $data, 'done' => count($posts) < 50];
}
add_filter('wp_privacy_personal_data_erasers', function ($erasers) {
    $erasers['shadowalker-inquiries'] = ['eraser_friendly_name' => 'Shadowalker inquiries', 'callback' => 'sw_erase_inquiries'];
    return $erasers;
});
function sw_erase_inquiries($email, $page = 1): array {
    // Always take the first remaining page because deletion shifts the result set.
    $posts = get_posts(['post_type' => 'sw_inquiry', 'post_status' => 'private', 'posts_per_page' => 50, 'meta_key' => '_sw_email', 'meta_value' => sanitize_email($email)]);
    $removed = false; $retained = false;
    foreach ($posts as $post) { if (wp_delete_post($post->ID, true)) { $removed = true; } else { $retained = true; } }
    return ['items_removed' => $removed, 'items_retained' => $retained, 'messages' => $retained ? ['Some inquiries could not be deleted. Please review them manually.'] : [], 'done' => count($posts) < 50 || $retained];
}
