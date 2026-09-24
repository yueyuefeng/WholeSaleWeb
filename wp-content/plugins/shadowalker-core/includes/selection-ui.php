<?php
defined('ABSPATH') || exit;
add_filter('the_title',function ($title,$id=0) {
    if (is_admin() || get_post_type($id)!=='page') { return $title; }
    $titles=['compare'=>['Compare products',__('Compare products', 'shadowalker')],'build'=>['Configure your ride',__('Configure your ride', 'shadowalker')],'compatibility'=>['Check compatibility',__('Check compatibility', 'shadowalker')]];
    $entry=$titles[get_post_field('post_name',$id)]??null;
    return $entry && $title===$entry[0] ? $entry[1] : $title;
},10,2);
add_action('wp_enqueue_scripts', function () {
    $base=plugins_url('assets/',dirname(__DIR__).'/shadowalker-core.php');
    wp_register_style('shadowalker-selection',$base.'selection.css',[],'1.3.1');
    wp_register_script('shadowalker-selection',$base.'selection.js',[],'1.3.1',true);
    wp_script_add_data('shadowalker-selection','strategy','defer');
    $post=get_post();
    if ((function_exists('is_woocommerce') && is_woocommerce()) || is_page(['compare','build','compatibility']) || (is_page() && isset($_GET['plan'])) || ($post && preg_match('/\[shadowalker_(compare|build|fit)\b/',$post->post_content))) {
        sw_selection_assets();
    }
});
function sw_selection_assets(): void {
    wp_enqueue_style('shadowalker-selection'); wp_enqueue_script('shadowalker-selection');
    static $configured=false; if ($configured) { return; } $configured=true;
    wp_add_inline_script('shadowalker-selection','window.swSelection='.wp_json_encode([
        'endpoint'=>rest_url('shadowalker/v1/selection/plan'),'nonce'=>wp_create_nonce('sw_chat'),'restNonce'=>wp_create_nonce('wp_rest'),'compare'=>sw_selection_url('compare'),'locale'=>get_locale(),
        'strings'=>['compare'=>__('Compare products', 'shadowalker'),'clear'=>__('Clear selection', 'shadowalker'),'limit'=>__('Choose up to three products from the same category.', 'shadowalker'),
        'error'=>__('Unable to save. Check your entries and try again.', 'shadowalker'),'conflict'=>__('These options cannot be combined. Check the configuration rules below.', 'shadowalker'),
        'saving'=>__('Saving…', 'shadowalker'),'saved'=>__('Plan saved', 'shadowalker'),'chat'=>__('Discuss this plan', 'shadowalker'),'share'=>__('Open saved plan', 'shadowalker'),
        'download'=>__('Download summary', 'shadowalker'),'removed'=>__('Removed from comparison', 'shadowalker'),'added'=>__('Added to comparison', 'shadowalker'),
        'copy'=>__('Copy plan link', 'shadowalker'),'copied'=>__('Link copied', 'shadowalker'),'copyFallback'=>__('Copy this link to share your plan.', 'shadowalker'),
        'draft'=>__('Your draft has been restored in this tab.', 'shadowalker'),'reset'=>__('Clear draft', 'shadowalker'),'draftNote'=>__('Your draft stays in this tab for up to 24 hours.', 'shadowalker'),
        'changed'=>__('Your selection has changed. Save again before discussing this plan.', 'shadowalker'),
        'compareHelp'=>__('Select two or three different products from the same category.', 'shadowalker'),
        'search'=>__('Search this product list', 'shadowalker'),'noOptions'=>__('No matching products in this list.', 'shadowalker'),
        'noDifference'=>__('No differences in the displayed specifications.', 'shadowalker')]
    ],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT).';','before');
}
function sw_selection_badge($product): string {
    return sw_selection_profile($product)['verified'] ? __('Reviewed product data', 'shadowalker') : __('Unverified or demonstration data. Our team must confirm the details.', 'shadowalker');
}
function sw_selection_compare_button($product): void {
    if (!sw_selection_kind($product)) { return; }
    echo '<button type="button" class="sw-compare-add" aria-pressed="false" data-compare-id="'.esc_attr($product->get_id()).'" data-kind="'.esc_attr(sw_selection_kind($product)).'">'.esc_html__('Compare products', 'shadowalker').'</button>';
}
add_action('woocommerce_after_shop_loop_item',function () { global $product; if ($product) { sw_selection_compare_button($product); } },20);
add_action('woocommerce_single_product_summary',function () {
    global $product; if (!$product || !sw_selection_kind($product)) { return; }
    $profile=sw_selection_profile($product);
    echo '<section class="sw-product-facts"><p class="sw-data-status">'.esc_html(sw_selection_badge($product)).'</p><dl>';
    $count=0; foreach (sw_selection_fields() as $key=>[$label,$unit,$min,$max,$kinds]) {
        if (!in_array($profile['kind'],$kinds,true)) { continue; }
        if ($count++>=6) { break; }
        echo '<div><dt>'.esc_html($label).'</dt><dd>'.esc_html(sw_selection_value($product,$key)).'</dd></div>';
    }
    echo '</dl><div class="sw-selection-actions">';
    if (in_array($profile['kind'],['cart','boat'],true)) { echo '<a class="button" href="'.esc_url(sw_selection_url('build',['product_id'=>$product->get_id()])).'">'.esc_html__('Configure your ride', 'shadowalker').' ↗</a>'; }
    if ($profile['kind']==='kit') { echo '<a class="button" href="'.esc_url(sw_selection_url('compatibility',['product_id'=>$product->get_id()])).'">'.esc_html__('Check compatibility', 'shadowalker').' ↗</a>'; }
    sw_selection_compare_button($product); echo '</div></section>';
},25);
add_action('woocommerce_after_single_product_summary',function () {
    global $product; if (!$product || !sw_selection_kind($product)) { return; }
    $profile=sw_selection_profile($product);
    echo '<section class="sw-product-details"><p class="eyebrow">SHADOWALKER / '.esc_html__('Product details', 'shadowalker').'</p><div class="sw-detail-grid">';
    foreach (sw_selection_content_fields() as $key=>$label) {
        echo '<article><h3>'.esc_html($label).'</h3><p>'.nl2br(esc_html($profile['content'][$key]??'' ?: __('To be confirmed', 'shadowalker'))).'</p></article>';
    }
    echo '</div><p class="sw-data-status">'.esc_html__('Data source / version', 'shadowalker').': '.esc_html(($profile['source']?:__('To be confirmed', 'shadowalker')).' / '.($profile['version']?:'—')).'</p></section>';
},5);
add_filter('woocommerce_product_tabs',function ($tabs) {
    global $product; if (!$product || !sw_selection_kind($product)) { return $tabs; }
    $tabs['sw_specs']=['title'=>__('Specifications', 'shadowalker'),'priority'=>25,'callback'=>function () {
        global $product; $kind=sw_selection_kind($product);
        echo '<p>'.esc_html(sw_selection_badge($product)).'</p><table class="sw-specs"><tbody>';
        foreach (sw_selection_fields() as $key=>[$label,$unit,$min,$max,$kinds]) { if (in_array($kind,$kinds,true)) { echo '<tr><th scope="row">'.esc_html($label).'</th><td>'.esc_html(sw_selection_value($product,$key)).'</td></tr>'; } }
        echo '</tbody></table>'; if ($product->get_meta('_sw_specs')) { sw_specs_tab(); }
    }]; return $tabs;
},20);
function sw_selection_catalog(array $kinds=[],array $selected=[]): array {
    if (!function_exists('wc_get_products')) { return []; }
    $catalog=wc_get_products(['status'=>'publish','visibility'=>'catalog','limit'=>200,'orderby'=>'title','order'=>'ASC']);
    // A direct product link must still work when its product is beyond the picker limit.
    foreach ($selected as $id) { $p=sw_selection_product($id); if ($p) { $catalog[]=$p; } }
    $unique=[]; foreach ($catalog as $p) { $unique[$p->get_id()]=$p; }
    return array_values(array_filter($unique,function ($p) use ($kinds) {
        return sw_selection_product($p->get_id()) && sw_selection_kind($p) && (!$kinds || in_array(sw_selection_kind($p),$kinds,true));
    }));
}
function sw_selection_product_select(string $name,array $catalog,int $selected=0): void {
    echo '<select name="'.esc_attr($name).'" aria-label="'.esc_attr__('Product', 'shadowalker').'">';
    echo '<option value="">'.esc_html__('Choose a product', 'shadowalker').'</option>';
    foreach ($catalog as $p) { echo '<option value="'.esc_attr($p->get_id()).'" data-kind="'.esc_attr(sw_selection_kind($p)).'" '.selected($selected,$p->get_id(),false).'>'.esc_html($p->get_name()).'</option>'; }
    echo '</select>';
}
add_shortcode('shadowalker_compare',function () {
    sw_selection_assets(); $raw=$_GET['ids']??''; $ids=[]; $invalid=false;
    if (is_array($raw)) { $raw=implode(',',array_filter($raw,'is_scalar')); }
    if (!is_string($raw) || strlen($raw)>80 || ($raw!=='' && !preg_match('/^[0-9,]+$/D',$raw))) { $invalid=true; }
    else { $ids=array_values(array_unique(array_filter(array_map('absint',explode(',',$raw))))); }
    if (count($ids)>3) { $invalid=true; }
    $products=[]; $kind='';
    foreach (array_slice($ids,0,3) as $id) { $p=sw_selection_product($id); if (!$p || !sw_selection_kind($p) || ($kind && $kind!==sw_selection_kind($p))) { $invalid=true; break; } $kind=sw_selection_kind($p); $products[]=$p; }
    if ($invalid) { $products=[]; }
    ob_start(); echo '<section class="sw-selection" aria-label="'.esc_attr__('Compare products', 'shadowalker').'"><p>'.esc_html__('Choose up to three products from the same category.', 'shadowalker').'</p>';
    $catalog=sw_selection_catalog([],array_map(fn($p)=>$p->get_id(),$products)); echo '<form method="get" class="sw-compare-picker">';
    for ($i=0;$i<3;$i++) { sw_selection_product_select('ids[]',$catalog,isset($products[$i])?$products[$i]->get_id():0); }
    echo '<button type="submit" class="button">'.esc_html__('Compare products', 'shadowalker').'</button></form>';
    if ($invalid) { echo '<p role="alert">'.esc_html__('Choose up to three products from the same category.', 'shadowalker').'</p>'; }
    if (count($products)>=2) {
        echo '<label class="sw-difference-toggle"><input type="checkbox" data-differences-only> '.esc_html__('Show differences only', 'shadowalker').'</label><p class="sw-difference-status" role="status"></p>';
        echo '<p class="sw-scroll-hint">'.esc_html__('Scroll sideways to compare all products.', 'shadowalker').'</p><div class="sw-compare-scroll" tabindex="0" role="region" aria-label="'.esc_attr__('Compare products', 'shadowalker').'"><table class="sw-compare-table"><thead><tr><th scope="col">'.esc_html__('Specifications', 'shadowalker').'</th>';
        foreach ($products as $p) { echo '<th scope="col"><a href="'.esc_url($p->get_permalink()).'">'.esc_html($p->get_name()).'</a><small>'.esc_html(sw_selection_badge($p)).'</small></th>'; } echo '</tr></thead><tbody>';
        foreach (sw_selection_fields() as $key=>[$label,$unit,$min,$max,$kinds]) {
            if (!in_array($kind,$kinds,true)) { continue; }
            $values=array_map(fn($p)=>sw_selection_value($p,$key),$products);
            echo '<tr'.(count(array_unique($values))>1?' class="sw-difference"':'').'><th scope="row">'.esc_html($label).'</th>'; foreach ($values as $value) { echo '<td>'.esc_html($value).'</td>'; } echo '</tr>';
        }
        foreach (['conditions'=>__('Test conditions', 'shadowalker'),'limits'=>__('Check before choosing', 'shadowalker'),'warranty'=>__('Warranty and support', 'shadowalker')] as $key=>$label) {
            $values=array_map(fn($p)=>sw_selection_profile($p)['content'][$key]??'' ?: __('To be confirmed', 'shadowalker'),$products);
            echo '<tr'.(count(array_unique($values))>1?' class="sw-difference"':'').'><th scope="row">'.esc_html($label).'</th>'; foreach ($values as $value) { echo '<td>'.esc_html($value).'</td>'; } echo '</tr>';
        }
        echo '<tr data-always-show><th scope="row">'.esc_html__('Data source / version', 'shadowalker').'</th>'; foreach ($products as $p) { $profile=sw_selection_profile($p); echo '<td>'.esc_html(($profile['source']?:'—').' / '.($profile['version']?:'—')).'</td>'; } echo '</tr></tbody><tfoot><tr><th scope="row">'.esc_html__('Chat with Shadowalker', 'shadowalker').'</th>'; foreach ($products as $p) { echo '<td><a href="'.esc_url(sw_selection_url('contact',['product_id'=>$p->get_id()])).'">'.esc_html__('Talk to us', 'shadowalker').' ↗</a></td>'; } echo '</tr></tfoot></table></div>';
    }
    echo '</section>'; return ob_get_clean();
});
function sw_selection_saved_view(string $mode): ?string {
    if (!isset($_GET['plan'])) { return null; }
    $snapshot=sw_selection_read_plan(wp_unslash($_GET['plan']));
    if (is_wp_error($snapshot)) { return '<p role="alert">'.esc_html__('This plan is unavailable or has expired. Please create a new plan.', 'shadowalker').'</p>'; }
    $summary=sw_selection_summary($snapshot); $code=$_GET['plan']; ob_start();
    echo '<section class="sw-selection sw-plan-result"><h2>'.esc_html__('Plan saved', 'shadowalker').'</h2><p>'.esc_html__('This link shares your product selection for 30 days. It does not share your conversation.', 'shadowalker').'</p><pre>'.esc_html(implode("\n",$summary)).'</pre><a class="button" href="'.esc_url(sw_selection_url('contact',['plan'=>$code])).'">'.esc_html__('Discuss this plan', 'shadowalker').' ↗</a> <button type="button" data-download-summary>'.esc_html__('Download summary', 'shadowalker').'</button> <button type="button" data-copy-plan="'.esc_url(sw_selection_url($snapshot['mode']==='build'?'build':'compatibility',['plan'=>$code])).'">'.esc_html__('Copy plan link', 'shadowalker').'</button><p class="sw-copy-status" role="status"></p> <a href="'.esc_url(sw_selection_url($mode==='build'?'build':'compatibility',['product_id'=>$snapshot['product_id']])).'">'.esc_html__('Create a new plan', 'shadowalker').'</a></section>';
    return ob_get_clean();
}
function sw_selection_form(string $mode): string {
    sw_selection_assets(); $saved=sw_selection_saved_view($mode); if ($saved!==null) { return $saved; }
    $id=$_GET['product_id']??''; $catalog=sw_selection_catalog($mode==='build'?['cart','boat']:['kit'],[$id]); $product=sw_selection_product($id);
    if ($product && !in_array($product->get_id(),array_map(fn($p)=>$p->get_id(),$catalog),true)) { $product=false; }
    ob_start(); echo '<section class="sw-selection" aria-label="'.esc_attr($mode==='build'?__('Configure your ride', 'shadowalker'):__('Check compatibility', 'shadowalker')).'"><p class="eyebrow">SHADOWALKER / '.esc_html__('Selection studio', 'shadowalker').'</p>';
    echo '<form method="get" class="sw-product-picker">'; sw_selection_product_select('product_id',$catalog,$product?$product->get_id():0); echo '<button type="submit" class="button">'.esc_html__('Choose a product', 'shadowalker').'</button></form>';
    if (!$product) { echo '</section>'; return ob_get_clean(); }
    echo '<p class="sw-data-status">'.esc_html(sw_selection_badge($product)).'</p>';
    $groups=sw_selection_options($product);
    if ($mode==='build' && !$groups) { echo '<p>'.esc_html__('Configuration options are awaiting confirmation. Please talk to our team.', 'shadowalker').'</p><a class="button" href="'.esc_url(sw_selection_url('contact',['product_id'=>$product->get_id()])).'">'.esc_html__('Chat with Shadowalker', 'shadowalker').'</a></section>'; return ob_get_clean(); }
    echo '<form class="sw-selection-form" data-mode="'.esc_attr($mode).'" data-product="'.esc_attr($product->get_id()).'" data-revision="'.esc_attr(sw_selection_revision($product)).'" data-rules="'.esc_attr(wp_json_encode($groups)).'"><div class="sw-form-grid">';
    if ($mode==='build') {
        foreach ($groups as $group) { echo '<label>'.esc_html($group['label']).'<select required name="choices['.esc_attr($group['id']).']"><option value="">'.esc_html__('Choose an option', 'shadowalker').'</option>'; foreach ($group['options'] as $option) { echo '<option value="'.esc_attr($option['id']).'">'.esc_html($option['label']).'</option>'; } echo '</select></label>'; }
    } else {
        foreach (['brand'=>__('Bicycle brand', 'shadowalker'),'model'=>__('Bicycle model', 'shadowalker')] as $key=>$label) { echo '<label>'.esc_html($label).'<input type="text" name="bicycle['.esc_attr($key).']" maxlength="100" placeholder="'.esc_attr__('I do not know', 'shadowalker').'"></label>'; }
        foreach (sw_selection_fit_fields() as $key=>$label) {
            echo '<label>'.esc_html($label).(isset(sw_selection_fields()[$key])?' ('.esc_html(sw_selection_fields()[$key][1]).')':'');
            if (isset(sw_selection_fit_choices()[$key])) { echo '<select name="measurements['.esc_attr($key).']"><option value="">'.esc_html__('I do not know', 'shadowalker').'</option>'; foreach (sw_selection_fit_choices()[$key] as $value=>$text) { echo '<option value="'.esc_attr($value).'">'.esc_html($text).'</option>'; } echo '</select>'; }
            else { $field=sw_selection_fields()[$key]; echo '<input type="number" step="any" min="'.esc_attr($field[2]).'" max="'.esc_attr($field[3]).'" name="measurements['.esc_attr($key).']" placeholder="'.esc_attr__('I do not know', 'shadowalker').'">'; } echo '</label>';
        }
    }
    echo '<label>'.esc_html__('Destination country', 'shadowalker').'<select name="country"><option value="">'.esc_html__('To be confirmed', 'shadowalker').'</option>'; foreach ((new WC_Countries())->get_countries() as $code=>$name) { echo '<option value="'.esc_attr($code).'">'.esc_html($name).'</option>'; } echo '</select></label><label>'.esc_html__('Quantity', 'shadowalker').'<input name="quantity" type="number" min="1" max="10000" step="1" value="1" required></label></div>';
    if ($mode==='build') {
        $labels=[]; foreach ($groups as $g) { foreach ($g['options'] as $o) { $labels[$g['id']][$o['id']]=$g['label'].' / '.$o['label']; } }
        echo '<ul class="sw-selection-rules">'; foreach ($groups as $g) { foreach ($g['options'] as $o) { foreach (['requires'=>__('Requires', 'shadowalker'),'excludes'=>__('Cannot combine with', 'shadowalker')] as $rule=>$label) { foreach ($o[$rule] as $gid=>$oid) { echo '<li>'.esc_html($labels[$g['id']][$o['id']].' — '.$label.': '.$labels[$gid][$oid]).'</li>'; } } if ($o['includes']) { echo '<li>'.esc_html($labels[$g['id']][$o['id']].' — '.__('Included in your selection', 'shadowalker').': '.implode(', ',$o['includes'])).'</li>'; } } } echo '</ul>';
    } else {
        echo '<details class="sw-measure-guide"><summary>'.esc_html__('How to check your bicycle', 'shadowalker').'</summary><svg viewBox="0 0 360 140" width="360" height="140" role="img" aria-label="'.esc_attr__('Dropout spacing', 'shadowalker').'"><path d="M80 15V105H104V78 M280 15V105H256V78" fill="none" stroke="#a4f9dd" stroke-width="6"/><path d="M108 94H252 M119 87L108 94L119 101 M241 87L252 94L241 101" fill="none" stroke="#eff8f7" stroke-width="2"/><text x="180" y="125" fill="#eff8f7" text-anchor="middle" font-size="14">mm</text></svg><p>'.esc_html__('Read wheel size on the tyre. Measure dropout spacing between the inner faces of the fork or frame where the axle sits, not the outside width. Read voltage from the battery label. If unsure, leave the field blank and ask our team.', 'shadowalker').'</p><p>'.esc_html__('This is an initial screening, not installation approval. Frame, fork, connectors and installation still need checking.', 'shadowalker').'</p></details>';
    }
    echo '<p>'.esc_html__('Configuration and delivery are subject to confirmation. This is not an order or a price quote.', 'shadowalker').'</p><p>'.esc_html__('This link shares your product selection for 30 days. It does not share your conversation.', 'shadowalker').'</p><button class="button" type="submit">'.esc_html__('Save and review plan', 'shadowalker').' ↗</button><p class="sw-selection-status" role="status"></p></form><div class="sw-plan-result" hidden aria-live="polite"></div><noscript>'.esc_html__('Enable JavaScript to save a plan, or contact our team.', 'shadowalker').'</noscript></section>';
    return ob_get_clean();
}
add_shortcode('shadowalker_build',fn()=>sw_selection_form('build'));
add_shortcode('shadowalker_fit',fn()=>sw_selection_form('fit'));
