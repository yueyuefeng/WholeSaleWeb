<?php
defined('ABSPATH') || exit;
add_action('init', function () { register_post_type('sw_plan',['public'=>false,'publicly_queryable'=>false,'show_ui'=>false,'show_in_rest'=>false,'exclude_from_search'=>true,'rewrite'=>false]); });
function sw_selection_url(string $page, array $args=[]): string {
    $url=function_exists('sw_page_url')?sw_page_url($page):home_url('/'.$page.'/');
    return add_query_arg($args,$url);
}
function sw_selection_prepare($raw) {
    if (!is_array($raw)) { return sw_selection_error(); }
    $product=sw_selection_product($raw['product_id']??null);
    if (!$product) { return sw_selection_error('selection_product',404); }
    $mode=$raw['mode']??'';
    if (!is_string($mode) || !in_array($mode,['build','fit'],true)) { return sw_selection_error(); }
    $profile=sw_selection_profile($product);
    $snapshot=['product_id'=>$product->get_id(),'name'=>$product->get_name(),'sku'=>$product->get_sku(),'kind'=>$profile['kind'],'mode'=>$mode,'revision'=>sw_selection_revision($product),'data_version'=>$profile['version'],'verified'=>$profile['verified'],'created_at'=>gmdate('c')];
    $country=$raw['country']??''; $quantity=$raw['quantity']??1;
    if (!is_string($country) || ($country!=='' && (!class_exists('WC_Countries') || !isset((new WC_Countries())->get_countries()[$country]))) || !is_scalar($quantity) || !ctype_digit((string)$quantity) || $quantity<1 || $quantity>10000) { return sw_selection_error(); }
    $snapshot['country']=$country; $snapshot['quantity']=(int)$quantity;
    if ($mode==='build') {
        if (!in_array($profile['kind'],['cart','boat'],true)) { return sw_selection_error('selection_family'); }
        $groups=sw_selection_options($product); $choices=$raw['choices']??null;
        if (!$groups || !is_array($choices) || count($choices)!==count($groups)) { return sw_selection_error('selection_choices'); }
        $snapshot['choices']=[]; $snapshot['includes']=[];
        foreach ($groups as $group) {
            $chosen=$choices[$group['id']]??null;
            if (!is_string($chosen)) { return sw_selection_error('selection_choices'); }
            $option=null; foreach ($group['options'] as $candidate) { if ($candidate['id']===$chosen) { $option=$candidate; break; } }
            if (!$option) { return sw_selection_error('selection_choices'); }
            foreach ($option['requires'] as $key=>$value) { if (($choices[$key]??null)!==$value) { return sw_selection_error('selection_conflict'); } }
            foreach ($option['excludes'] as $key=>$value) { if (($choices[$key]??null)===$value) { return sw_selection_error('selection_conflict'); } }
            $snapshot['choices'][]=['group'=>$group['label'],'value'=>$option['label'],'group_id'=>$group['id'],'option_id'=>$option['id']];
            $snapshot['includes']=array_values(array_unique(array_merge($snapshot['includes'],$option['includes'])));
        }
    } else {
        if ($profile['kind']!=='kit') { return sw_selection_error('selection_family'); }
        $bicycle=$raw['bicycle']??[];
        if (!is_array($bicycle) || array_diff(array_keys($bicycle),['brand','model'])) { return sw_selection_error(); }
        $snapshot['bicycle']=[];
        foreach (['brand','model'] as $key) {
            if (!is_string($bicycle[$key]??'') || mb_strlen($bicycle[$key]??'')>100) { return sw_selection_error(); }
            $snapshot['bicycle'][$key]=sanitize_text_field($bicycle[$key]??'');
        }
        $measurements=sw_selection_measurements($raw['measurements']??null);
        if (is_wp_error($measurements)) { return $measurements; }
        $snapshot['measurements']=$measurements; $snapshot['result']=sw_selection_evaluate($product,$measurements);
    }
    return $snapshot;
}
function sw_selection_read_plan($code) {
    if (!is_string($code) || !preg_match('/^[a-f0-9]{32}$/D',$code)) { return sw_selection_error('selection_plan',404); }
    $posts=get_posts(['post_type'=>'sw_plan','post_status'=>'private','posts_per_page'=>1,'meta_key'=>'_sw_plan_hash','meta_value'=>hash('sha256',$code)]);
    if (!$posts || (int)get_post_meta($posts[0]->ID,'_sw_expires',true)<=time()) { return sw_selection_error('selection_plan',404); }
    $snapshot=get_post_meta($posts[0]->ID,'_sw_snapshot',true);
    if (!is_array($snapshot) || !sw_selection_product($snapshot['product_id']??null)) { return sw_selection_error('selection_plan',404); }
    return sw_selection_refresh_context($snapshot);
}
function sw_selection_refresh_context(array $snapshot): array {
    $product=sw_selection_product($snapshot['product_id']??null);
    $snapshot['stale']=!$product || ($snapshot['revision']??'')!==sw_selection_revision($product);
    if ($snapshot['stale'] && isset($snapshot['result'])) { $snapshot['result']['status']='review'; }
    return $snapshot;
}
function sw_selection_result_label(string $status): string {
    return ['match'=>__('Initial match', 'shadowalker'),'mismatch'=>__('Not a match', 'shadowalker'),'review'=>__('Needs expert review', 'shadowalker')][$status]??__('Needs expert review', 'shadowalker');
}
function sw_selection_summary(array $snapshot): array {
    $lines=[__('Product', 'shadowalker').': '.$snapshot['name']];
    if (!empty($snapshot['reference'])) { $lines[]=__('Plan reference', 'shadowalker').': '.$snapshot['reference']; }
    if (!empty($snapshot['stale'])) { $lines[]=__('Product data has changed. Please reconfirm this plan with our team.', 'shadowalker'); }
    if (empty($snapshot['verified'])) { $lines[]=__('Unverified or demonstration data. Our team must confirm the details.', 'shadowalker'); }
    foreach ($snapshot['choices']??[] as $choice) { $lines[]=$choice['group'].': '.$choice['value']; }
    foreach (['brand'=>__('Bicycle brand', 'shadowalker'),'model'=>__('Bicycle model', 'shadowalker')] as $key=>$label) { if (!empty($snapshot['bicycle'][$key])) { $lines[]=$label.': '.$snapshot['bicycle'][$key]; } }
    foreach ($snapshot['measurements']??[] as $key=>$value) {
        if (!isset(sw_selection_fit_fields()[$key])) { continue; }
        $display=$value===''?__('To be confirmed', 'shadowalker'):(isset(sw_selection_fit_choices()[$key]) ? (sw_selection_fit_choices()[$key][$value]??(string)$value) : (string)$value);
        if ($value!=='' && isset(sw_selection_fields()[$key])) { $display.=' '.sw_selection_fields()[$key][1]; }
        $lines[]=sw_selection_fit_fields()[$key].': '.$display;
    }
    if (isset($snapshot['result'])) {
        $lines[]=sw_selection_result_label($snapshot['result']['status']);
        foreach (['mismatch'=>__('Different specification', 'shadowalker'),'unknown'=>__('Confirmation needed', 'shadowalker')] as $type=>$label) {
            foreach ($snapshot['result'][$type]??[] as $key) { $lines[]=$label.': '.(sw_selection_fit_fields()[$key]??$key); }
        }
        $lines[]=__('This is an initial screening, not installation approval. Frame, fork, connectors and installation still need checking.', 'shadowalker');
    }
    if (!empty($snapshot['includes'])) { $lines[]=__('Included in your selection', 'shadowalker').': '.implode(', ',$snapshot['includes']); }
    if (!empty($snapshot['country']) && class_exists('WC_Countries')) { $lines[]=__('Destination country', 'shadowalker').': '.((new WC_Countries())->get_countries()[$snapshot['country']]??$snapshot['country']); }
    $lines[]=__('Quantity', 'shadowalker').': '.($snapshot['quantity']??1);
    $lines[]=__('Configuration and delivery are subject to confirmation. This is not an order or a price quote.', 'shadowalker');
    return $lines;
}
add_action('rest_api_init', function () {
    register_rest_route('shadowalker/v1','/selection/plan',['methods'=>'POST','permission_callback'=>'sw_chat_create_permission','callback'=>function ($request) {
        if (!sw_chat_limit('selection:'.($_SERVER['REMOTE_ADDR']??'unknown'),30,HOUR_IN_SECONDS)) { return sw_selection_error('chat_rate',429); }
        $snapshot=sw_selection_prepare($request->get_json_params());
        if (is_wp_error($snapshot)) { return $snapshot; }
        $code=bin2hex(random_bytes(16)); $snapshot['reference']='SW-'.strtoupper(substr($code,0,10));
        $id=wp_insert_post(['post_type'=>'sw_plan','post_status'=>'private','post_title'=>$snapshot['reference']],true);
        if (is_wp_error($id) || !$id) { return sw_selection_error('chat_storage',503); }
        update_post_meta($id,'_sw_plan_hash',hash('sha256',$code)); update_post_meta($id,'_sw_snapshot',$snapshot); update_post_meta($id,'_sw_expires',time()+30*DAY_IN_SECONDS);
        return new WP_REST_Response(['plan'=>$code,'summary'=>sw_selection_summary($snapshot),'url'=>sw_selection_url($snapshot['mode']==='build'?'build':'compatibility',['plan'=>$code]),'chat_url'=>sw_selection_url('contact',['plan'=>$code])],201);
    }]);
});
add_filter('rest_post_dispatch',function ($result,$server,$request) { if (str_starts_with($request->get_route(),'/shadowalker/v1/selection')) { $result->header('Cache-Control','no-store, private'); } return $result; },10,3);
function sw_selection_private_page(): bool {
    $post=get_post();
    return is_page(['compare','build','compatibility']) || (isset($_GET['plan']) && is_page()) || ($post && (bool)preg_match('/\[shadowalker_(compare|build|fit)\b/',$post->post_content));
}
add_action('template_redirect',function () {
    if (sw_selection_private_page()) { nocache_headers(); header('X-Robots-Tag: noindex, follow',true); }
});
add_filter('wp_robots',function ($robots) { if (sw_selection_private_page()) { $robots['noindex']=true; unset($robots['index']); } return $robots; });
add_action('sw_chat_cleanup',function () {
    $ids=get_posts(['post_type'=>'sw_plan','post_status'=>'private','numberposts'=>100,'fields'=>'ids','meta_query'=>[['key'=>'_sw_expires','value'=>time(),'compare'=>'<=','type'=>'NUMERIC']]]);
    foreach ($ids as $id) { wp_delete_post($id,true); }
    if (count($ids)===100) { wp_schedule_single_event(time()+MINUTE_IN_SECONDS,'sw_chat_cleanup'); }
});
require_once __DIR__.'/selection-ui.php';
