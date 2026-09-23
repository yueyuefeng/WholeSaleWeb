<?php
defined('ABSPATH') || exit;
require_once __DIR__.'/selection-seed.php';
/** Explicit, additive demo installation. Existing pages, settings and products are preserved. */
WP_CLI::add_command('shadowalker seed', function () {
    if (!class_exists('WooCommerce')) { WP_CLI::error('Activate WooCommerce first.'); }
    $pages = [
        'home' => ['Home', ''],
        'contact' => ['Chat with Shadowalker', '[shadowalker_chat]'],
        'our-story' => ['Go your own way.', '<p>Shadowalker is built around a simple idea: there is more than one way to move. We bring together electric mobility for everyday routes, open water and the places in between.</p><h2>A ride for your world.</h2><p>Explore golf carts, electric boats, electric bicycles and bicycle conversion components. Talk to our team about compatibility and destination requirements before choosing your next ride.</p>'],
        'shipping-returns' => ['Shipping & returns', '<p><strong>Pre-launch policy draft — not a shipping or refund promise.</strong></p><p>Shipping availability, freight rates, delivery times and return arrangements have not yet been published. Please contact us for a destination-specific quote.</p><p>Before launch, the store operator must publish verified dispatch times, delivery areas, battery transport restrictions, duties, return window, return address, refund method and warranty terms.</p>'],
        'privacy-policy' => ['Privacy policy', '<p><strong>Pre-launch policy draft — requires operator and legal review.</strong></p><p>The inquiry form collects your name, email, destination country, quantity and message to respond to your request. Inquiries are stored in this WordPress installation and may be sent to the store administrator by email. An HMAC of the connection IP and email is stored temporarily for rate limiting.</p><p>Chat messages, language and the selected product are stored in this WordPress installation. A random access secret in the browser tab allows the visitor to read or delete that conversation. Access expires after 30 days; scheduled cleanup removes expired conversations. No email is required. When AI assistance is enabled, recent messages may be sent to the configured AI processor. Do not send payment details or sensitive information. Before launch identify that processor and its retention and transfer arrangements.</p><p>Saved product selections contain chosen options, bicycle brand/model if supplied, measurements, destination country and quantity. A random share link grants access for 30 days. Anyone with that link can see the selection, but not the conversation. Starting a chat from a plan stores a copy with the chat and may send it to the configured AI processor. Deleting a chat does not revoke the separate selection link; each record follows its own retention period.</p><p>Before launch add the legal entity, contact details, hosting and payment processors, retention periods, data transfer arrangements, user rights, cookie choices and request process. Configure WooCommerce customer data retention separately. Requests can be processed through WordPress Tools → Export/Erase Personal Data. Copies in email and backups require separate retention handling.</p>'],
        'terms' => ['Terms of sale', '<p><strong>Pre-launch policy draft — requires operator and legal review.</strong></p><p>Before accepting orders, publish the legal seller, contract process, supported markets, taxes/duties, accepted currencies, payment/refund rules, product restrictions, warranty terms and dispute contact. Sample catalog specifications are illustrative only.</p>'],
    ];
    foreach ($pages as $slug => [$title, $content]) {
        if (!get_page_by_path($slug)) {
            $id = wp_insert_post(['post_type' => 'page', 'post_status' => 'publish', 'post_name' => $slug, 'post_title' => $title, 'post_content' => $content], true);
            if (is_wp_error($id)) { WP_CLI::error($id->get_error_message()); }
            if ($slug === 'contact') { update_post_meta($id, '_wp_page_template', 'page-contact.php'); }
        }
    }
    if (!get_option('sw_seed_initialized')) {
        update_option('show_on_front', 'page');
        update_option('page_on_front', get_page_by_path('home')->ID);
        if (!get_option('wp_page_for_privacy_policy')) { update_option('wp_page_for_privacy_policy', get_page_by_path('privacy-policy')->ID); }
        // Safe demonstration settings only on the explicit first seed.
        update_option('blog_public', 0);
        update_option('sw_demo_mode', 1);
        update_option('sw_seed_initialized', 1);
    }
    WC_Install::create_pages();
    $categories = ['golf-carts' => 'Golf carts', 'electric-boats' => 'Electric boats', 'electric-bikes' => 'Electric bikes', 'conversion-kits' => 'Conversion kits'];
    $terms = [];
    foreach ($categories as $slug => $name) {
        $term = get_term_by('slug', $slug, 'product_cat');
        if ($term) { $terms[$slug] = $term->term_id; continue; }
        $result = wp_insert_term($name, 'product_cat', ['slug' => $slug]);
        if (is_wp_error($result)) { WP_CLI::error($result->get_error_message()); }
        $terms[$slug] = $result['term_id'];
    }
    $products = [
        ['SW-DEMO-CART', 'Trail / Electric golf cart', 'golf-carts', '', true, 'cart', "Seating | 4 seats (sample)\nBattery | Configuration on request\nUse | Private-property applications; confirm local rules"],
        ['SW-DEMO-BOAT', 'Drift / Electric day boat', 'electric-boats', '', true, 'boat', "Propulsion | Electric (sample)\nConfiguration | Quotation required\nUse | Confirm local boating requirements"],
        ['SW-DEMO-BIKE', 'Roam / Everyday e-bike', 'electric-bikes', '1299', false, 'bike', "Motor | 250 W (sample)\nBattery | 36 V (sample)\nCompatibility | Confirm market requirements"],
        ['SW-DEMO-KIT', 'Spark / Conversion kit', 'conversion-kits', '399', false, 'kit', "Motor | Rear hub (sample)\nWheel size | Confirm before purchase\nIncludes | Motor, controller and display (sample)"],
        ['SW-DEMO-BATTERY', 'Core / Battery pack', 'conversion-kits', '249', false, 'kit', "Voltage | 36 V (sample)\nFit | Confirm connector and mounting\nShipping | Battery transport review required"],
        ['SW-DEMO-CONTROLLER', 'Link / Motor controller', 'conversion-kits', '79', false, 'kit', "System | 36 V (sample)\nCompatibility | Match motor, display and wiring\nInstallation | Qualified installer recommended"],
    ];
    foreach ($products as [$sku, $name, $category, $price, $quote, $art, $specs]) {
        if (wc_get_product_id_by_sku($sku)) { continue; }
        $product = new WC_Product_Simple();
        $product->set_name($name); $product->set_sku($sku); $product->set_status('publish');
        $product->set_regular_price($price); $product->set_category_ids([$terms[$category]]);
        $product->set_description('<p>Meet ' . esc_html($name) . '. Part of the Shadowalker demonstration collection.</p><p><strong>Demonstration only.</strong> Illustrations, pricing and specifications are placeholders. Replace them with verified product data, compatibility information, delivery terms and approved photography before sale.</p>');
        $product->set_short_description('An electric way to explore. Demo product — confirm specifications and destination availability with our team.');
        $product->set_manage_stock(true); $product->set_stock_quantity(10);
        $product->update_meta_data('_sw_quote_only', $quote ? 'yes' : 'no');
        $product->update_meta_data('_sw_specs', $specs); $product->update_meta_data('_sw_art', $art);
        $product->update_meta_data('_sw_demo', 'yes'); $product->save();
    }
    sw_selection_seed();
    flush_rewrite_rules();
    WP_CLI::success('Demo pages and products added. Existing products and pages preserved. Replace all sample content before launch.');
});
