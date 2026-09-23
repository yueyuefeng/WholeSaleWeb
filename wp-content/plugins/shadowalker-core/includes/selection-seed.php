<?php
defined('ABSPATH') || exit;
function sw_selection_seed(): void {
    foreach (['compare'=>['Compare products','[shadowalker_compare]'],'build'=>['Configure your ride','[shadowalker_build]'],'compatibility'=>['Check compatibility','[shadowalker_fit]']] as $slug=>[$title,$content]) {
        if (!get_page_by_path($slug)) { wp_insert_post(['post_type'=>'page','post_status'=>'publish','post_name'=>$slug,'post_title'=>$title,'post_content'=>$content]); }
    }
    $samples=[
        'SW-DEMO-CART'=>['cart',['seats'=>4]], 'SW-DEMO-BOAT'=>['boat',[]],
        'SW-DEMO-BIKE'=>['bike',['power_w'=>250,'voltage_v'=>36]],
        'SW-DEMO-KIT'=>['kit',['power_w'=>250,'voltage_v'=>36,'wheel_in'=>26,'axle_mm'=>135]],
        'SW-DEMO-BATTERY'=>['kit',['voltage_v'=>36]], 'SW-DEMO-CONTROLLER'=>['kit',['voltage_v'=>36]],
    ];
    foreach ($samples as $sku=>[$kind,$values]) {
        $p=wc_get_product(wc_get_product_id_by_sku($sku));
        if (!$p || $p->get_meta('_sw_demo')!=='yes' || $p->get_meta('_sw_profile')) { continue; }
        $p->update_meta_data('_sw_profile',['kind'=>$kind,'verified'=>false,'source'=>'Demonstration only','version'=>'demo-1','values'=>$values,'content'=>[]]);
        if ($kind==='cart') {
            $p->update_meta_data('_sw_options',[
                ['id'=>'seats','label'=>'Seats (demo)','options'=>[['id'=>'four','label'=>'4 seats'],['id'=>'six','label'=>'6 seats','requires'=>['battery'=>'extended']]]],
                ['id'=>'battery','label'=>'Battery (demo)','options'=>[['id'=>'standard','label'=>'Standard'],['id'=>'extended','label'=>'Extended']]],
                ['id'=>'canopy','label'=>'Canopy (demo)','options'=>[['id'=>'open','label'=>'Open'],['id'=>'roof','label'=>'With roof','includes'=>['Roof (demo)']]]]
            ]);
        }
        if ($kind==='boat') { $p->update_meta_data('_sw_options',[
            ['id'=>'use','label'=>'Intended use (demo)','options'=>[['id'=>'leisure','label'=>'Leisure'],['id'=>'fleet','label'=>'Commercial fleet']]],
            ['id'=>'charging','label'=>'Charging preference (demo)','options'=>[['id'=>'shore','label'=>'Shore charging'],['id'=>'review','label'=>'Review local facilities with our team']]]
        ]); }
        if ($sku==='SW-DEMO-KIT') { $p->update_meta_data('_sw_fit',['wheel_in'=>[26,27.5,28],'axle_mm'=>[135],'voltage_v'=>[36],'mount'=>['rear'],'brake'=>['disc','rim']]); }
        $p->save();
    }
}
WP_CLI::add_command('shadowalker selection-demo',function () {
    if (!class_exists('WooCommerce')) { WP_CLI::error('Activate WooCommerce first.'); }
    sw_selection_seed(); flush_rewrite_rules(); WP_CLI::success('Selection pages and missing demo metadata added. Existing data preserved; demo profiles are never verified.');
});
