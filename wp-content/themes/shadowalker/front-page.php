<?php defined('ABSPATH') || exit; get_header(); ?>
<main id="main">
<section class="hero manifesto" aria-labelledby="manifesto-heading">
    <div class="hero-media"><?php sw_photo('hero', __('A lone cyclist overlooking a mountain landscape', 'shadowalker'), 'hero-photo', true); ?></div>
    <div class="hero-grid" aria-hidden="true"></div>
    <div class="hero-content wrap">
        <p class="eyebrow"><span class="status-dot"></span>SHADOWALKER / <?php esc_html_e('THE INDEPENDENT SPIRIT', 'shadowalker'); ?></p>
        <h1 id="manifesto-heading" lang="zh-CN"><span>人生来孤独，</span><span>唯有<span class="shadow-word">影</span>为永恒伴侣，</span><span class="manifesto-last">行者心态不虚此生。</span></h1>
        <p class="manifesto-translation"><?php esc_html_e('Born alone. Our shadow, an eternal companion. Walk your own path. Make this life count.', 'shadowalker'); ?></p>
        <div class="hero-actions"><a class="button" href="<?php echo esc_url(sw_shop_url()); ?>"><?php esc_html_e('Find your ride', 'shadowalker'); ?> <span aria-hidden="true">↗</span></a><a class="text-link" href="#collections"><?php esc_html_e('Explore the collection', 'shadowalker'); ?> <span aria-hidden="true">↓</span></a></div>
    </div>
    <div class="hero-bottom wrap"><span>01 / <?php esc_html_e('Go your own way.', 'shadowalker'); ?></span><span class="hero-coordinate">LAND · WATER · BEYOND</span><a href="#collections"><?php esc_html_e('Discover what moves you', 'shadowalker'); ?> ↓</a></div>
</section>
<div class="principles wrap"><span><b>01</b> <?php esc_html_e('Explore your possibilities', 'shadowalker'); ?></span><span><b>02</b> <?php esc_html_e('Choose your own pace', 'shadowalker'); ?></span><span><b>03</b> <?php esc_html_e('Make it electric', 'shadowalker'); ?></span></div>
<section class="section wrap" id="collections">
    <div class="section-heading"><div><p class="eyebrow">[ 01 — <?php esc_html_e('THE COLLECTION', 'shadowalker'); ?> ]</p><h2><?php esc_html_e('Four ways. Your way.', 'shadowalker'); ?></h2></div><a class="text-link" href="<?php echo esc_url(sw_shop_url()); ?>"><?php esc_html_e('All products', 'shadowalker'); ?> ↗</a></div>
    <div class="category-grid"><?php foreach (sw_categories() as $index => $category) { ?>
        <a class="category-card category-<?php echo esc_attr($category[3]); ?>" href="<?php echo esc_url(sw_category_url($category[0])); ?>">
            <?php sw_photo($category[3], $category[1]); ?><span class="category-number">0<?php echo (int) $index + 1; ?> / SHADOWALKER</span>
            <div class="category-copy"><h3><?php echo esc_html($category[1]); ?></h3><p><?php echo esc_html($category[2]); ?></p></div><span class="circle-arrow" aria-hidden="true">↗</span>
        </a>
    <?php } ?></div>
    <p class="photo-context"><?php esc_html_e('Scenes for inspiration. Explore each product for its actual configuration.', 'shadowalker'); ?></p>
</section>
<section class="conversion wrap">
    <div class="conversion-art"><?php sw_photo('kit', __('Close-up of a bicycle drivetrain and mechanical components', 'shadowalker')); ?><span class="component-tag">[ REBUILD / REIMAGINE ]</span></div>
    <div class="conversion-copy"><p class="eyebrow">[ 02 — <?php esc_html_e('BUILT FOR THE TINKERER', 'shadowalker'); ?> ]</p><h2><?php esc_html_e('Keep the bike.', 'shadowalker'); ?><br><em><?php esc_html_e('Change the journey.', 'shadowalker'); ?></em></h2><p><?php esc_html_e('Give a familiar ride a fresh start. Explore motors, batteries, controllers, and conversion kits. We can help you check compatibility before you choose.', 'shadowalker'); ?></p><div class="component-list"><span>01 / MOTOR</span><span>02 / BATTERY</span><span>03 / CONTROL</span></div><a class="button" href="<?php echo esc_url(sw_category_url('conversion-kits')); ?>"><?php esc_html_e('Explore conversion kits', 'shadowalker'); ?> ↗</a></div>
</section>
<section class="section wrap"><div class="section-heading"><div><p class="eyebrow">[ 03 — <?php esc_html_e('MEET YOUR NEXT ADVENTURE', 'shadowalker'); ?> ]</p><h2><?php esc_html_e('Ready for a different route.', 'shadowalker'); ?></h2></div><a class="text-link" href="<?php echo esc_url(sw_shop_url()); ?>"><?php esc_html_e('Browse the shop', 'shadowalker'); ?> ↗</a></div><?php if (class_exists('WooCommerce')) { echo do_shortcode('[products limit="4" columns="4" orderby="date" order="DESC" visibility="visible"]'); } else { ?><p><?php esc_html_e('Our collection is getting ready. Contact us to find your next ride.', 'shadowalker'); ?></p><?php } ?></section>
<section class="help-band wrap"><p class="eyebrow">[ 04 — <?php esc_html_e('LET’S FIND YOUR WAY', 'shadowalker'); ?> ]</p><h2><?php esc_html_e('A fleet. A first ride.', 'shadowalker'); ?><br><?php esc_html_e('A project of your own.', 'shadowalker'); ?></h2><p><?php esc_html_e('Tell us what you have in mind. We’ll help you explore the options.', 'shadowalker'); ?></p><a class="button" href="<?php echo esc_url(sw_page_url('contact')); ?>"><?php esc_html_e('Let’s talk', 'shadowalker'); ?> ↗</a><span class="giant-arrow" aria-hidden="true">↗</span></section>
</main><?php get_footer(); ?>
