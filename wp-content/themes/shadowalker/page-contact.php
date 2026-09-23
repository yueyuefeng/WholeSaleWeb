<?php
/** Template Name: Shadowalker Chat */
defined('ABSPATH') || exit;
get_header(); ?>
<main id="main" class="wrap content-page sw-chat-page">
    <h1><?php esc_html_e('Let’s talk.', 'shadowalker'); ?></h1>
    <?php echo do_shortcode('[shadowalker_chat]'); ?>
    <details class="sw-chat-quote"><summary><?php esc_html_e('Prefer a detailed quote?', 'shadowalker'); ?></summary><?php echo do_shortcode('[shadowalker_inquiry]'); ?></details>
</main>
<?php get_footer(); ?>
