<?php
defined('ABSPATH') || exit;
add_action('woocommerce_product_options_general_product_data', function () {
    global $product_object;
    if (!$product_object) { return; }
    $profile=sw_selection_profile($product_object);
    echo '<div class="options_group"><h3 style="padding:0 12px">' . esc_html__('Product selection data', 'shadowalker') . '</h3><input type="hidden" name="sw_selection_present" value="1">';
    woocommerce_wp_select(['id'=>'sw_kind','name'=>'sw_profile[kind]','label'=>__('Product family', 'shadowalker'),'options'=>[''=>__('Select a product', 'shadowalker')]+sw_selection_kinds(),'value'=>$profile['kind']]);
    foreach (['source'=>__('Published source', 'shadowalker'),'version'=>__('Data version', 'shadowalker')] as $key=>$label) { woocommerce_wp_text_input(['id'=>'sw_'.$key,'name'=>'sw_profile['.$key.']','label'=>$label,'value'=>$profile[$key]]); }
    woocommerce_wp_checkbox(['id'=>'sw_verified','name'=>'sw_profile[verified]','label'=>__('Reviewed product data', 'shadowalker'),'value'=>$profile['verified']?'yes':'no','description'=>__('Only mark reviewed after checking source, specifications and compatibility rules. Demo products remain unverified.', 'shadowalker')]);
    foreach (sw_selection_fields() as $key=>[$label,$unit,$min,$max,$kinds]) {
        woocommerce_wp_text_input(['id'=>'sw_'.$key,'name'=>'sw_profile[values]['.$key.']','label'=>$label.($unit?' ('.$unit.')':''),'type'=>'number','value'=>$profile['values'][$key]??'','custom_attributes'=>['min'=>$min,'max'=>$max,'step'=>$key==='seats'?'1':'any'],'description'=>implode(', ',array_map(fn($kind)=>sw_selection_kinds()[$kind],$kinds))]);
    }
    foreach (sw_selection_content_fields() as $key=>$label) { woocommerce_wp_textarea_input(['id'=>'sw_content_'.$key,'name'=>'sw_profile[content]['.$key.']','label'=>$label,'value'=>$profile['content'][$key]??'']); }
    foreach (['options'=>__('Configuration rules (JSON)', 'shadowalker'),'fit'=>__('Compatibility rules (JSON)', 'shadowalker')] as $key=>$label) {
        $value=$product_object->get_meta('_sw_'.$key);
        woocommerce_wp_textarea_input(['id'=>'sw_'.$key,'name'=>'sw_'.$key,'label'=>$label,'value'=>is_array($value)?wp_json_encode($value,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE):'','description'=>__('See the selection tools manual for the schema. Leave empty to require manual confirmation.', 'shadowalker')]);
    }
    echo '</div>';
});
add_action('woocommerce_admin_process_product_object', function ($product) {
    if (!isset($_POST['sw_selection_present'])) { return; }
    $profile=sw_selection_validate_profile(isset($_POST['sw_profile'])?wp_unslash($_POST['sw_profile']):null);
    $options=sw_selection_validate_options(sw_post_string('sw_options')!==''?json_decode(sw_post_string('sw_options'),true):[]);
    $fit=sw_selection_validate_fit(sw_post_string('sw_fit')!==''?json_decode(sw_post_string('sw_fit'),true):[]);
    foreach ([$profile,$options,$fit] as $result) {
        if (is_wp_error($result)) { WC_Admin_Meta_Boxes::add_error(__('Selection data was not saved. Check numbers, source/version and JSON rules; previous data is preserved.', 'shadowalker')); return; }
    }
    $product->update_meta_data('_sw_profile',$profile); $product->update_meta_data('_sw_options',$options); $product->update_meta_data('_sw_fit',$fit);
});
