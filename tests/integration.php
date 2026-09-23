<?php
/** Run with wp eval-file tests/integration.php against a seeded, disposable environment. */
if (!defined('ABSPATH') || !class_exists('WooCommerce')) { throw new RuntimeException('WordPress and WooCommerce required.'); }
$passed = 0;
$assert = function ($condition, $message) use (&$passed) { if (!$condition) { throw new RuntimeException('FAIL: ' . $message); } $passed++; echo "PASS: $message\n"; };
$ids = [];
try {
    $part = new WC_Product_Simple(); $part->set_name('Integration fixture'); $part->set_regular_price('99'); $part->set_status('publish'); $ids[] = $part->save();
    $assert($part->is_purchasable(), 'ordinary accessory is purchasable');
    $part->update_meta_data('_sw_quote_only', 'yes'); $part->save();
    $assert(!$part->is_purchasable(), 'quote-only product cannot be purchased');
    $assert(!apply_filters('woocommerce_add_to_cart_validation', true, $part->get_id(), 1, 0), 'direct cart addition is denied');
    $schema = apply_filters('woocommerce_structured_data_product', ['name' => 'Fixture', 'offers' => ['price' => '99']], $part);
    $assert(!isset($schema['offers']), 'quote-only product has no purchasable schema offer');
    $parent = new WC_Product_Variable(); $parent->set_name('Variable fixture'); $parent->set_status('publish'); $parent->update_meta_data('_sw_quote_only', 'yes'); $ids[] = $parent->save();
    $variation = new WC_Product_Variation(); $variation->set_parent_id($parent->get_id()); $variation->set_regular_price('100'); $variation->set_status('publish'); $ids[] = $variation->save();
    $assert(sw_is_quote_product($variation) && !$variation->is_purchasable(), 'variations inherit quote-only restriction');
    $part->update_meta_data('_sw_quote_only', 'no'); $part->save();
    $assert($part->is_purchasable(), 'turning quote mode off restores ordinary purchase');
    $input = ['name' => 'Test Person', 'email' => 'test@example.test', 'country' => 'US', 'quantity' => '2', 'message' => 'Please quote two sample products.', 'consent' => '1'];
    $assert(!is_wp_error(sw_validate_inquiry($input)), 'valid inquiry accepted');
    foreach (['email' => 'invalid', 'country' => 'ZZ', 'quantity' => '-2', 'consent' => '', 'message' => 'short'] as $field => $value) {
        $bad = $input; $bad[$field] = $value; $assert(is_wp_error(sw_validate_inquiry($bad)), 'reject invalid ' . $field);
    }
    $bad = $input; $bad['quantity'] = '2.5'; $assert(is_wp_error(sw_validate_inquiry($bad)), 'reject fractional quantity');
    $bad = $input; $bad['name'] = '<script>bad()</script> Customer'; $clean = sw_validate_inquiry($bad);
    $assert(!is_wp_error($clean) && !str_contains($clean['name'], '<script>'), 'strip markup from stored fields');
    $type = get_post_type_object('sw_inquiry');
    $assert(!$type->public && !$type->publicly_queryable && !$type->show_in_rest, 'inquiries are private and not exposed via REST');
    $assert(!get_role('subscriber')->has_cap($type->cap->edit_posts), 'subscriber cannot browse inquiries');
    $assert(get_role('shop_manager')->has_cap($type->cap->edit_posts), 'shop manager can manage inquiries');
    $contact = get_page_by_path('contact');
    $assert($contact && (has_shortcode($contact->post_content, 'shadowalker_chat') || has_shortcode($contact->post_content, 'shadowalker_inquiry')), 'contact page supports chat or preserved legacy inquiry content');
    $assert(count(wc_get_products(['sku' => 'SW-DEMO-KIT', 'limit' => -1])) === 1, 'seeded SKU exists once');
    $assert((string) get_option('blog_public') === '0', 'demo is not indexable');
    foreach (['zh_CN' => '高尔夫车', 'de_DE' => 'Golfcarts', 'fr_FR' => 'Voiturettes de golf', 'es_ES' => 'Carritos de golf'] as $locale => $expected) {
        unload_textdomain('shadowalker', true);
        load_textdomain('shadowalker', get_template_directory() . '/languages/' . $locale . '.mo', $locale);
        $assert(__('Golf carts', 'shadowalker') === $expected, 'load ' . $locale . ' catalog');
    }
} finally {
    foreach (array_reverse($ids) as $id) { $product = wc_get_product($id); if ($product) { $product->delete(true); } }
    if (WC()->session) { wc_clear_notices(); }
}
echo "$passed assertions passed.\n";
