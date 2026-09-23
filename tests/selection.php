<?php
/** Disposable WP/WooCommerce integration: wp eval-file tests/selection.php */
if (!defined('ABSPATH')) { throw new RuntimeException('WordPress required'); }
$ids=[]; $count=0; $old_user=get_current_user_id(); $old_get=$_GET;
$_SERVER['REMOTE_ADDR']='selection-test-'.wp_generate_uuid4();
$assert=function ($ok,$message) use (&$count) { if (!$ok) { throw new RuntimeException('FAIL '.$message); } $count++; echo "PASS $message\n"; };
$call=function ($route,$body,$nonce=true,$origin='') {
    $r=new WP_REST_Request('POST','/shadowalker/v1/'.$route); $r->set_header('content-type','application/json'); $r->set_body(wp_json_encode($body));
    if ($nonce) { $r->set_header('x-shadowalker-nonce',wp_create_nonce('sw_chat')); } if ($origin) { $r->set_header('origin',$origin); }
    return rest_do_request($r);
};
$new=function ($kind) use (&$ids) { $p=new WC_Product_Simple(); $p->set_name('Selection fixture '.$kind); $p->set_status('publish'); $p->update_meta_data('_sw_profile',['kind'=>$kind,'verified'=>true,'source'=>'Test fixture only','version'=>'test-1','values'=>[]]); $ids[]=$p->save(); return $p; };
try {
    wp_set_current_user(0);
    $assert(is_wp_error(sw_selection_validate_profile(['kind'=>['kit']])), 'array kind rejected without fatal error');
    $assert(is_wp_error(sw_selection_validate_profile(['kind'=>'bike','values'=>['power_w'=>-1]])), 'negative power rejected');
    $assert(is_wp_error(sw_selection_validate_profile(['kind'=>'bike','values'=>['power_w'=>['x']]])), 'array numeric value rejected');
    $assert(is_wp_error(sw_selection_validate_profile(['kind'=>'bike','values'=>['rider_min_cm'=>190,'rider_max_cm'=>150]])), 'reversed height range rejected');
    $assert(is_wp_error(sw_selection_validate_profile(['kind'=>'cart','values'=>['seats'=>2.5]])), 'fractional seats rejected');
    $assert(is_wp_error(sw_selection_validate_profile(['kind'=>'bike','verified'=>'yes'])), 'reviewed profile requires source and version');
    $assert(is_wp_error(sw_selection_validate_profile(['kind'=>'bike','verified'=>'yes','source'=>'manual','version'=>'1','values'=>['range_km'=>90]])), 'range claim requires test conditions');
    $kit=$new('kit'); $other=$new('kit'); $cart=$new('cart');
    $rules=['wheel_in'=>[26,27.5],'axle_mm'=>[135],'voltage_v'=>[36],'mount'=>['rear'],'brake'=>['disc']];
    $kit->update_meta_data('_sw_fit',$rules); $kit->save();
    $measure=sw_selection_measurements(['wheel_in'=>'27.5','axle_mm'=>'135','voltage_v'=>'36','mount'=>'rear','brake'=>'disc']);
    $assert(sw_selection_evaluate($kit,$measure)['status']==='match','reviewed complete rules give initial match');
    $assert(sw_selection_evaluate($kit,[])['status']==='review','missing measurements require expert review');
    $bad=$measure; $bad['voltage_v']=48.0;
    $assert(sw_selection_evaluate($kit,$bad)['status']==='mismatch','known voltage mismatch rejected');
    $bad['wheel_in']=''; $result=sw_selection_evaluate($kit,$bad);
    $assert($result['status']==='mismatch' && in_array('wheel_in',$result['unknown'],true),'mismatch retains unknown criteria');
    $kit->update_meta_data('_sw_demo','yes'); $kit->save();
    $assert(sw_selection_evaluate($kit,$measure)['status']==='review','demo data never approves fit');
    $kit->delete_meta_data('_sw_demo'); $kit->update_meta_data('_sw_fit',['wheel_in'=>['bad']]); $kit->save();
    $assert(sw_selection_evaluate($kit,$measure)['status']==='review','malformed stored rules fail closed');
    $kit->update_meta_data('_sw_fit',$rules); $kit->save();
    $assert(is_wp_error(sw_selection_measurements(['mount'=>['rear']])),'nested measurement input rejected');
    $assert(is_wp_error(sw_selection_measurements(['approved'=>true])),'forged measurement keys rejected');
    $assert(is_wp_error(sw_selection_prepare(['mode'=>'fit','product_id'=>$kit->get_id(),'measurements'=>[],'bicycle'=>['brand'=>['bad']]])),'nested bicycle brand rejected');
    $bikeinfo=sw_selection_prepare(['mode'=>'fit','product_id'=>$kit->get_id(),'measurements'=>[],'bicycle'=>['brand'=>'Test brand','model'=>'Test model']]);
    $assert($bikeinfo['bicycle']['model']==='Test model' && $bikeinfo['result']['status']==='review','bicycle model accompanies measurements without granting approval');
    $options=[['id'=>'seats','label'=>'Seats','options'=>[['id'=>'four','label'=>'4'],['id'=>'six','label'=>'6','requires'=>['battery'=>'big']]]],['id'=>'battery','label'=>'Battery','options'=>[['id'=>'small','label'=>'Small'],['id'=>'big','label'=>'Big','includes'=>['Battery']]]]];
    $assert(!is_wp_error(sw_selection_validate_options($options)),'configuration rules accept valid references');
    $invalid=$options; $invalid[0]['options'][1]['requires']=['absent'=>'missing'];
    $assert(is_wp_error(sw_selection_validate_options($invalid)),'configuration rejects broken references');
    $cart->update_meta_data('_sw_options',$options); $cart->save();
    $build=['mode'=>'build','product_id'=>$cart->get_id(),'choices'=>['seats'=>'six','battery'=>'big'],'quantity'=>'2','country'=>'US'];
    $assert(!is_wp_error(sw_selection_prepare($build)),'valid build accepted');
    $invalid=$build; $invalid['choices']['battery']='small';
    $assert(is_wp_error(sw_selection_prepare($invalid)),'required option enforced on server');
    $forbidden=$options; $forbidden[0]['options'][0]['excludes']=['battery'=>'big']; $cart->update_meta_data('_sw_options',$forbidden); $cart->save();
    $invalid=$build; $invalid['choices']['seats']='four';
    $assert(is_wp_error(sw_selection_prepare($invalid)),'excluded option enforced on server');
    $cart->update_meta_data('_sw_options',$options); $cart->save();
    foreach (['quantity'=>'1.5','country'=>'ZZ','choices'=>['forged'=>'x'],'product_id'=>[$cart->get_id()]] as $key=>$value) { $invalid=$build; $invalid[$key]=$value; $assert(is_wp_error(sw_selection_prepare($invalid)),'reject invalid '.$key); }
    $invalid=$build; $invalid['mode']='fit'; $invalid['measurements']=[];
    $assert(is_wp_error(sw_selection_prepare($invalid)),'cross-family selection rejected');
    $assert($call('selection/plan',$build,false)->get_status()===403,'plan requires CSRF nonce');
    $assert($call('selection/plan',$build,true,'https://untrusted.example')->get_status()===403,'cross-origin plan rejected');
    $response=$call('selection/plan',$build); $data=$response->get_data();
    $assert($response->get_status()===201 && preg_match('/^[a-f0-9]{32}$/D',$data['plan']),'save returns unguessable plan code');
    $plan=sw_selection_read_plan($data['plan']);
    $plan_posts=get_posts(['post_type'=>'sw_plan','post_status'=>'private','meta_key'=>'_sw_plan_hash','meta_value'=>hash('sha256',$data['plan'])]); $plan_id=$plan_posts[0]->ID; $ids[]=$plan_id;
    $assert(get_post_meta($plan_id,'_sw_plan_hash',true)!==$data['plan'],'plan access code stored only as hash');
    $assert($plan['quantity']===2 && $plan['includes']===['Battery'] && !$plan['stale'],'saved plan preserves server-derived choices');
    $assert(is_wp_error(sw_selection_read_plan(str_repeat('f',32))),'unknown plan inaccessible');
    $assert(!get_post_type_object('sw_plan')->show_in_rest && !get_post_type_object('sw_plan')->public,'no public plan collection');
    $assert($call('chat',['plan'=>str_repeat('f',32)])->get_status()===404,'chat rejects nonexistent plan');
    $chat=$call('chat',['plan'=>$data['plan'],'product_id'=>$kit->get_id(),'selection'=>['verified'=>true,'name'=>'forged']])->get_data(); $ids[]=$chat['id'];
    $context=sw_chat_bot_context($chat['id']);
    $assert($context['product_id']===$cart->get_id() && $context['selection']['name']===$cart->get_name(),'chat trusts saved plan, not forged product or snapshot');
    $assert(!isset($context['plan']) && !isset($context['selection']['plan']),'AI context excludes plan access secret');
    $cart->set_name('Changed fixture'); $cart->save();
    $assert(sw_selection_read_plan($data['plan'])['stale'] && sw_chat_bot_context($chat['id'])['selection']['stale'],'saved plan and AI context detect changed product');
    $fit=$call('selection/plan',['product_id'=>$kit->get_id(),'mode'=>'fit','measurements'=>$measure])->get_data();
    $fit_posts=get_posts(['post_type'=>'sw_plan','post_status'=>'private','meta_key'=>'_sw_plan_hash','meta_value'=>hash('sha256',$fit['plan'])]); $ids[]=$fit_posts[0]->ID;
    $assert(sw_selection_read_plan($fit['plan'])['result']['status']==='match','fit plan stores evaluated result');
    $kit->update_meta_data('_sw_fit',[]); $kit->save();
    $assert(sw_selection_read_plan($fit['plan'])['result']['status']==='review','stale fit cannot retain match');
    update_post_meta($plan_id,'_sw_expires',time()-1);
    $assert(is_wp_error(sw_selection_read_plan($data['plan'])),'expired plan unavailable');
    $kit->set_status('draft'); $kit->save();
    $assert(is_wp_error(sw_selection_read_plan($fit['plan'])),'unpublished product plan unavailable');
    $kit->set_status('publish'); $kit->save();
    $_GET=['ids'=>$kit->get_id().','.$other->get_id()]; $html=do_shortcode('[shadowalker_compare]');
    $assert(str_contains($html,'sw-compare-table') && str_contains($html,'To be confirmed'),'same-category comparison renders unknowns');
    $_GET=['ids'=>$kit->get_id().','.$cart->get_id()]; $html=do_shortcode('[shadowalker_compare]');
    $assert(!str_contains($html,'<table'),'cross-category comparison blocked');
    $_GET=[]; $assert(str_contains(do_shortcode('[shadowalker_compare]'),'sw-compare-picker'),'empty comparison renders without error');
    foreach (['compare','build','compatibility'] as $slug) { $assert((bool)get_page_by_path($slug),'tool page exists: '.$slug); }
} finally {
    $_GET=$old_get; wp_set_current_user($old_user);
    foreach (array_reverse($ids) as $id) { wp_delete_post($id,true); }
}
echo "$count selection assertions passed.\n";
