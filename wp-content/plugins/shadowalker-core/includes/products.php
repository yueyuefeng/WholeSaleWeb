<?php
defined('ABSPATH') || exit;
add_filter('woocommerce_placeholder_img_src', function ($src) {
    global $product;
    if (!$product || !is_a($product, 'WC_Product') || $product->get_meta('_sw_demo') !== 'yes') { return $src; }
    $art = $product->get_meta('_sw_art');
    if (!in_array($art, ['cart', 'boat', 'bike', 'kit'], true) || get_template() !== 'shadowalker') { return $src; }
    return get_template_directory_uri() . '/assets/photos/' . $art . '.webp';
});
function sw_is_quote_product($product): bool {
    if (!$product || !is_a($product, 'WC_Product')) { return false; }
    if ($product->get_meta('_sw_quote_only') === 'yes') { return true; }
    if ($product->is_type('variation')) {
        $parent = wc_get_product($product->get_parent_id());
        return $parent && $parent->get_meta('_sw_quote_only') === 'yes';
    }
    return false;
}
add_filter('woocommerce_is_purchasable', function ($purchasable, $product) { return sw_is_quote_product($product) ? false : $purchasable; }, 10, 2);
add_filter('woocommerce_variation_is_purchasable', function ($purchasable, $product) { return sw_is_quote_product($product) ? false : $purchasable; }, 10, 2);
add_filter('woocommerce_add_to_cart_validation', function ($valid, $product_id, $quantity, $variation_id = 0) {
    if (sw_is_quote_product(wc_get_product($variation_id ?: $product_id))) {
        wc_add_notice(__('This product requires a quote. Please contact us.', 'shadowalker'), 'error');
        return false;
    }
    return $valid;
}, 10, 4);
add_filter('woocommerce_get_price_html', function ($price, $product) { return sw_is_quote_product($product) ? esc_html__('Price on request', 'shadowalker') : $price; }, 10, 2);
// A quote-only product must not advertise a purchasable offer in structured data.
add_filter('woocommerce_structured_data_product', function ($markup, $product) {
    if (sw_is_quote_product($product)) { unset($markup['offers']); }
    return $markup;
}, 10, 2);
add_action('woocommerce_single_product_summary', function () {
    global $product;
    if (!sw_is_quote_product($product)) { return; }
    $contact = function_exists('sw_page_url') ? sw_page_url('contact') : home_url('/contact/');
    echo '<div class="sw-quote-box"><p>' . esc_html__('Let’s discuss your configuration, destination and delivery requirements.', 'shadowalker') . '</p><a class="button" href="' . esc_url(add_query_arg('product_id', $product->get_id(), $contact)) . '">' . esc_html__('Request a quote', 'shadowalker') . ' ↗</a></div>';
}, 30);
add_action('woocommerce_product_options_general_product_data', function () {
    echo '<div class="options_group">';
    woocommerce_wp_checkbox(['id' => '_sw_quote_only', 'label' => __('Quote only', 'shadowalker'), 'description' => __('Disable online purchase for this product and its variations.', 'shadowalker')]);
    woocommerce_wp_textarea_input(['id' => '_sw_specs', 'label' => __('Technical specifications', 'shadowalker'), 'description' => __('One per line: label | value. Confirm all specifications before publishing.', 'shadowalker')]);
    echo '</div>';
});
add_action('woocommerce_admin_process_product_object', function ($product) {
    // WooCommerce verifies the product-edit nonce and capability before this hook.
    $product->update_meta_data('_sw_quote_only', isset($_POST['_sw_quote_only']) ? 'yes' : 'no');
    if (isset($_POST['_sw_specs']) && is_string($_POST['_sw_specs'])) {
        $product->update_meta_data('_sw_specs', sanitize_textarea_field(wp_unslash($_POST['_sw_specs'])));
    }
});
add_filter('woocommerce_product_tabs', function ($tabs) {
    global $product;
    if ($product && $product->get_meta('_sw_specs')) {
        $tabs['sw_specs'] = ['title' => __('Specifications', 'shadowalker'), 'priority' => 25, 'callback' => 'sw_specs_tab'];
    }
    return $tabs;
});
function sw_specs_tab(): void {
    global $product;
    echo '<table class="sw-specs"><tbody>';
    foreach (explode("\n", $product->get_meta('_sw_specs')) as $line) {
        $parts = array_map('trim', explode('|', $line, 2));
        if (count($parts) !== 2) { continue; }
        echo '<tr><th scope="row">' . esc_html($parts[0]) . '</th><td>' . esc_html($parts[1]) . '</td></tr>';
    }
    echo '</tbody></table>';
}
