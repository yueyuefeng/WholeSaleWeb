<?php defined('ABSPATH') || exit; ?><!doctype html>
<html <?php language_attributes(); ?>>
<head><meta charset="<?php bloginfo('charset'); ?>"><meta name="viewport" content="width=device-width, initial-scale=1"><?php wp_head(); ?></head>
<body <?php body_class(); ?>><?php wp_body_open(); ?>
<a class="skip-link" href="#main"><?php esc_html_e('Skip to content', 'shadowalker'); ?></a>
<div class="announcement"><?php esc_html_e('A little electric. A lot of possibility.', 'shadowalker'); ?><span><?php esc_html_e('Find your next way to move', 'shadowalker'); ?> ↗</span></div>
<header class="site-header"><div class="header-inner">
<a class="brand" href="<?php echo esc_url(home_url('/')); ?>" aria-label="Shadowalker home"><span class="brand-mark" aria-hidden="true">s↗</span>SHADOWALKER<span class="brand-dot">®</span></a>
<button class="menu-toggle" aria-controls="primary-nav" aria-expanded="false"><?php esc_html_e('Menu', 'shadowalker'); ?> ☰</button>
<nav id="primary-nav" aria-label="<?php esc_attr_e('Main navigation', 'shadowalker'); ?>">
<?php if (has_nav_menu('primary')) { wp_nav_menu(['theme_location' => 'primary', 'container' => false, 'menu_class' => 'nav-links']); } else { ?>
<ul class="nav-links"><li><a href="<?php echo esc_url(sw_shop_url()); ?>"><?php esc_html_e('Explore products', 'shadowalker'); ?></a></li><li><a href="<?php echo esc_url(sw_page_url('our-story')); ?>"><?php esc_html_e('Our story', 'shadowalker'); ?></a></li><li><a href="<?php echo esc_url(sw_page_url('contact')); ?>"><?php esc_html_e('Talk to us', 'shadowalker'); ?></a></li></ul>
<?php } ?></nav>
<div class="header-tools"><?php sw_languages(); ?><a class="cart-link" href="<?php echo esc_url(function_exists('wc_get_cart_url') ? wc_get_cart_url() : sw_shop_url()); ?>"><?php esc_html_e('Bag', 'shadowalker'); ?> <span aria-hidden="true">↗</span></a></div>
</div></header>
