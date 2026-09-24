<?php defined('ABSPATH') || exit; get_header(); ?>
<main id="main" class="wrap content-page"><?php while (have_posts()) { the_post(); ?><article id="post-<?php the_ID(); ?>"><p class="eyebrow">SHADOWALKER</p><h1><?php the_title(); ?></h1><div class="entry-content"><?php the_content(); wp_link_pages(); ?></div></article><?php } ?></main><?php get_footer(); ?>
