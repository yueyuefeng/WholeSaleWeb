<?php
/**
 * Plugin Name: Shadowalker Core
 * Description: Mobility product specifications, quote-only sales, protected inquiries and explicit demo setup.
 * Version: 1.3.0
 * Requires at least: 6.6
 * Requires PHP: 8.2
 * Text Domain: shadowalker
 * License: GPL-2.0-or-later
 */
defined('ABSPATH') || exit;
require_once __DIR__ . '/includes/products.php';
require_once __DIR__ . '/includes/selection-data.php';
require_once __DIR__ . '/includes/selection.php';
require_once __DIR__ . '/includes/inquiries.php';
require_once __DIR__ . '/includes/chat.php';
register_deactivation_hook(__FILE__, function () { wp_clear_scheduled_hook('sw_chat_cleanup'); });
if (defined('WP_CLI') && WP_CLI) { require_once __DIR__ . '/includes/seed.php'; }
add_action('before_woocommerce_init', function () {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});
add_action('admin_notices', function () {
    if (!class_exists('WooCommerce') && current_user_can('activate_plugins')) {
        echo '<div class="notice notice-warning"><p>Shadowalker: activate WooCommerce to enable the product catalog and checkout.</p></div>';
    }
});
add_action('wp_body_open', function () {
    if (get_option('sw_demo_mode')) {
        echo '<div class="sw-demo-notice">' . esc_html__('Demo catalog — sample products, illustrations and prices. Not a live store.', 'shadowalker') . '</div>';
    }
});
