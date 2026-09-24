<?php
defined('ABSPATH') || exit;

add_action('after_setup_theme', function () {
    load_theme_textdomain('shadowalker', get_template_directory() . '/languages');
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('custom-logo', ['height' => 64, 'width' => 240, 'flex-width' => true]);
    add_theme_support('html5', ['search-form', 'gallery', 'caption', 'style', 'script']);
    add_theme_support('woocommerce', ['product_grid' => ['default_columns' => 3]]);
    add_theme_support('wc-product-gallery-zoom');
    add_theme_support('wc-product-gallery-lightbox');
    add_theme_support('wc-product-gallery-slider');
    register_nav_menus(['primary' => __('Main navigation', 'shadowalker'), 'footer' => __('Footer navigation', 'shadowalker')]);
});
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style('shadowalker', get_template_directory_uri() . '/assets/css/store.css', [], '1.3.0');
    wp_enqueue_style('shadowalker-tech', get_template_directory_uri() . '/assets/css/tech.css', ['shadowalker'], '1.3.0');
    wp_enqueue_script('shadowalker', get_template_directory_uri() . '/assets/js/store.js', [], '1.0.0', true);
    wp_script_add_data('shadowalker', 'strategy', 'defer');
});
function sw_shop_url(): string {
    return function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/shop/');
}
function sw_page_url(string $slug): string {
    $page = get_page_by_path($slug);
    if (!$page) { return home_url('/' . $slug . '/'); }
    $id = apply_filters('wpml_object_id', $page->ID, 'page', true);
    return get_permalink($id);
}
function sw_category_url(string $slug): string {
    $term = get_term_by('slug', $slug, 'product_cat');
    if (!$term) { return sw_shop_url(); }
    $id = apply_filters('wpml_object_id', $term->term_id, 'product_cat', true);
    $url = get_term_link((int) $id, 'product_cat');
    return is_wp_error($url) ? sw_shop_url() : $url;
}
function sw_categories(): array {
    return [
        ['golf-carts', __('Golf carts', 'shadowalker'), __('A quieter way around.', 'shadowalker'), 'cart'],
        ['electric-boats', __('Electric boats', 'shadowalker'), __('Leave only a ripple.', 'shadowalker'), 'boat'],
        ['electric-bikes', __('Electric bikes', 'shadowalker'), __('Make every day a detour.', 'shadowalker'), 'bike'],
        ['conversion-kits', __('Conversion kits', 'shadowalker'), __('Your bike. A new possibility.', 'shadowalker'), 'kit'],
    ];
}
function sw_art(string $name, string $alt = '', string $class = ''): void {
    $allowed = ['cart', 'boat', 'bike', 'kit', 'landscape'];
    if (!in_array($name, $allowed, true)) { $name = 'bike'; }
    printf('<img class="%s" src="%s" alt="%s" width="800" height="600" loading="%s" decoding="async">', esc_attr($class), esc_url(get_template_directory_uri() . '/assets/images/' . $name . '.svg'), esc_attr($alt), $name === 'landscape' ? 'eager' : 'lazy');
}
/** Local editorial photography; uploaded product photographs take precedence. */
function sw_photo(string $name, string $alt = '', string $class = '', bool $priority = false): void {
    if (!in_array($name, ['hero', 'cart', 'boat', 'bike', 'kit'], true)) { return; }
    $dimensions = [1200, 800];
    $path = get_template_directory() . '/assets/photos/' . $name . '.webp';
    if (is_file($path)) { $size = wp_getimagesize($path); if ($size) { $dimensions = [$size[0], $size[1]]; } }
    printf('<img class="%s" src="%s" alt="%s" width="%d" height="%d" loading="%s" decoding="async"%s>', esc_attr($class), esc_url(get_template_directory_uri() . '/assets/photos/' . $name . '.webp'), esc_attr($alt), $dimensions[0], $dimensions[1], $priority ? 'eager' : 'lazy', $priority ? ' fetchpriority="high"' : '');
}
function sw_languages(): void {
    $languages = apply_filters('wpml_active_languages', null, ['skip_missing' => 1]);
    if (!$languages || count($languages) < 2) { return; }
    echo '<details class="language-menu"><summary>' . esc_html__('Language', 'shadowalker') . '</summary><ul>';
    foreach ($languages as $language) {
        printf('<li><a href="%s" lang="%s" hreflang="%s" %s>%s</a></li>', esc_url($language['url']), esc_attr($language['language_code']), esc_attr($language['language_code']), $language['active'] ? 'aria-current="true"' : '', esc_html($language['native_name']));
    }
    echo '</ul></details>';
}
add_filter('woocommerce_show_page_title', '__return_true');
add_filter('loop_shop_columns', function () { return 3; });
add_filter('woocommerce_product_add_to_cart_text', function ($text, $product) {
    return function_exists('sw_is_quote_product') && sw_is_quote_product($product) ? __('View details', 'shadowalker') : $text;
}, 10, 2);
add_filter('body_class', function ($classes) { $classes[] = 'shadowalker'; return $classes; });
