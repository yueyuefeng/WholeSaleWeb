<?php
defined('ABSPATH') || exit;

function sw_selection_kinds(): array {
    return ['cart' => __('Golf carts', 'shadowalker'), 'boat' => __('Electric boats', 'shadowalker'), 'bike' => __('Electric bikes', 'shadowalker'), 'kit' => __('Conversion kits', 'shadowalker')];
}
/** Canonical units are deliberately fixed; missing is not zero. */
function sw_selection_fields(): array {
    return [
        'seats' => [__('Seats', 'shadowalker'), '', 1, 100, ['cart','boat']],
        'power_w' => [__('Rated motor power', 'shadowalker'), 'W', 1, 2000000, ['cart','boat','bike','kit']],
        'battery_wh' => [__('Battery energy', 'shadowalker'), 'Wh', 1, 2000000, ['cart','boat','bike']],
        'voltage_v' => [__('System voltage', 'shadowalker'), 'V', 1, 1000, ['cart','boat','bike','kit']],
        'weight_kg' => [__('Weight', 'shadowalker'), 'kg', 0.01, 100000, ['cart','boat','bike','kit']],
        'payload_kg' => [__('Payload', 'shadowalker'), 'kg', 1, 100000, ['cart','boat','bike']],
        'range_km' => [__('Tested range', 'shadowalker'), 'km', 1, 2000, ['cart','bike']],
        'range_nm' => [__('Tested range', 'shadowalker'), 'NM', 1, 2000, ['boat']],
        'length_m' => [__('Length', 'shadowalker'), 'm', 0.1, 100, ['boat']],
        'draft_m' => [__('Draft', 'shadowalker'), 'm', 0.1, 20, ['boat']],
        'wheel_in' => [__('Wheel size', 'shadowalker'), 'in', 6, 36, ['bike','kit']],
        'axle_mm' => [__('Dropout spacing', 'shadowalker'), 'mm', 50, 250, ['kit']],
        'rider_min_cm' => [__('Minimum rider height', 'shadowalker'), 'cm', 80, 230, ['bike']],
        'rider_max_cm' => [__('Maximum rider height', 'shadowalker'), 'cm', 80, 230, ['bike']],
    ];
}
function sw_selection_content_fields(): array {
    return ['highlights' => __('Highlights', 'shadowalker'), 'use' => __('Designed for', 'shadowalker'), 'limits' => __('Check before choosing', 'shadowalker'), 'included' => __('Included in the box', 'shadowalker'), 'delivery' => __('Delivery information', 'shadowalker'), 'warranty' => __('Warranty and support', 'shadowalker'), 'conditions' => __('Test conditions', 'shadowalker')];
}
function sw_selection_error(string $code = 'selection_input', int $status = 422): WP_Error { return new WP_Error($code, $code, ['status' => $status]); }
function sw_selection_product($id) {
    if (!is_scalar($id) || !ctype_digit((string) $id)) { return false; }
    $p = function_exists('wc_get_product') ? wc_get_product((int) $id) : false;
    return $p && $p->get_status() === 'publish' && !$p->is_type('variation') && !get_post_field('post_password', $p->get_id()) ? $p : false;
}
function sw_selection_kind($product): string {
    $profile = $product->get_meta('_sw_profile');
    if (is_array($profile) && is_string($profile['kind'] ?? null) && isset(sw_selection_kinds()[$profile['kind']])) { return $profile['kind']; }
    foreach (['golf-carts'=>'cart','electric-boats'=>'boat','electric-bikes'=>'bike','conversion-kits'=>'kit'] as $slug=>$kind) {
        if (has_term($slug, 'product_cat', $product->get_id())) { return $kind; }
    }
    return '';
}
function sw_selection_validate_profile($raw) {
    if (!is_array($raw) || !is_string($raw['kind'] ?? null) || !isset(sw_selection_kinds()[$raw['kind']]) || !is_array($raw['values'] ?? []) || !is_array($raw['content'] ?? [])) { return sw_selection_error(); }
    $out = ['kind'=>$raw['kind'], 'verified'=>($raw['verified'] ?? '') === 'yes', 'source'=>'', 'version'=>'', 'values'=>[], 'content'=>[]];
    foreach (['source'=>240,'version'=>60] as $field=>$max) {
        if (!is_string($raw[$field] ?? '')) { return sw_selection_error(); }
        $out[$field] = sanitize_text_field($raw[$field] ?? '');
        if (mb_strlen($out[$field]) > $max) { return sw_selection_error(); }
    }
    foreach (sw_selection_fields() as $key=>[$label,$unit,$min,$max,$kinds]) {
        if (!in_array($out['kind'], $kinds, true)) { continue; }
        $value = $raw['values'][$key] ?? '';
        if ($value === '' || $value === null) { continue; }
        if (!is_scalar($value) || !is_numeric($value) || !is_finite((float) $value) || (float) $value < $min || (float) $value > $max || ($key === 'seats' && floor((float) $value) !== (float) $value)) { return sw_selection_error(); }
        $out['values'][$key] = (float) $value;
    }
    if (isset($out['values']['rider_min_cm'], $out['values']['rider_max_cm']) && $out['values']['rider_min_cm'] > $out['values']['rider_max_cm']) { return sw_selection_error(); }
    foreach (sw_selection_content_fields() as $key=>$label) {
        if (!is_string($raw['content'][$key] ?? '')) { return sw_selection_error(); }
        $out['content'][$key] = sanitize_textarea_field($raw['content'][$key] ?? '');
        if (mb_strlen($out['content'][$key]) > 1500) { return sw_selection_error(); }
    }
    if ($out['verified'] && (!$out['source'] || !$out['version'])) { return sw_selection_error('selection_provenance'); }
    if ($out['verified'] && (isset($out['values']['range_km']) || isset($out['values']['range_nm'])) && !$out['content']['conditions']) { return sw_selection_error('selection_conditions'); }
    return $out;
}
function sw_selection_profile($product): array {
    $raw = $product->get_meta('_sw_profile');
    if (!is_array($raw)) { $raw = []; }
    $raw['kind'] = sw_selection_kind($product);
    $raw['verified'] = in_array($raw['verified'] ?? false, [true, 'yes'], true) ? 'yes' : '';
    $clean = sw_selection_validate_profile($raw);
    if (is_wp_error($clean)) { $clean = ['kind'=>sw_selection_kind($product),'verified'=>false,'source'=>'','version'=>'','values'=>[],'content'=>[]]; }
    if ($product->get_meta('_sw_demo') === 'yes') { $clean['verified'] = false; }
    return $clean;
}
function sw_selection_value($product, string $key): string {
    $profile = sw_selection_profile($product);
    if (!isset($profile['values'][$key])) { return __('To be confirmed', 'shadowalker'); }
    $value = $profile['values'][$key];
    return number_format_i18n($value, floor($value) === $value ? 0 : 2) . ' ' . sw_selection_fields()[$key][1];
}
/** A configuration is an inquiry specification, never a client-provided price or order. */
function sw_selection_validate_options($raw) {
    if (!is_array($raw) || !array_is_list($raw) || count($raw)>6) { return sw_selection_error('selection_options'); }
    $out=[]; $refs=[];
    foreach ($raw as $group) {
        if (!is_array($group) || !is_string($group['id'] ?? null) || !preg_match('/^[a-z][a-z0-9_-]{0,29}$/D',$group['id']) || isset($refs[$group['id']]) || !is_string($group['label'] ?? null) || !$group['label'] || mb_strlen($group['label'])>80 || !is_array($group['options'] ?? null) || !array_is_list($group['options']) || count($group['options'])<1 || count($group['options'])>10) { return sw_selection_error('selection_options'); }
        $g=['id'=>$group['id'],'label'=>sanitize_text_field($group['label']),'options'=>[]]; $refs[$g['id']]=[];
        foreach ($group['options'] as $option) {
            if (!is_array($option) || !is_string($option['id'] ?? null) || !preg_match('/^[a-z][a-z0-9_-]{0,29}$/D',$option['id']) || isset($refs[$g['id']][$option['id']]) || !is_string($option['label'] ?? null) || !$option['label'] || mb_strlen($option['label'])>100) { return sw_selection_error('selection_options'); }
            $o=['id'=>$option['id'],'label'=>sanitize_text_field($option['label'])]; $refs[$g['id']][$o['id']]=true;
            foreach (['requires','excludes'] as $rule) {
                $o[$rule]=$option[$rule] ?? [];
                if (!is_array($o[$rule]) || count($o[$rule])>6) { return sw_selection_error('selection_options'); }
                foreach ($o[$rule] as $key=>$value) { if (!is_string($key) || !is_string($value)) { return sw_selection_error('selection_options'); } }
            }
            $o['includes']=$option['includes'] ?? [];
            if (!is_array($o['includes']) || !array_is_list($o['includes']) || count($o['includes'])>10) { return sw_selection_error('selection_options'); }
            foreach ($o['includes'] as &$item) { if (!is_string($item) || mb_strlen($item)>100) { return sw_selection_error('selection_options'); } $item=sanitize_text_field($item); } unset($item);
            $g['options'][]=$o;
        }
        $out[]=$g;
    }
    foreach ($out as $group) { foreach ($group['options'] as $option) { foreach (['requires','excludes'] as $rule) { foreach ($option[$rule] as $gid=>$oid) { if (!isset($refs[$gid][$oid])) { return sw_selection_error('selection_options'); } } } } }
    return $out;
}
function sw_selection_options($product): array {
    $result=sw_selection_validate_options($product->get_meta('_sw_options') ?: []);
    return is_wp_error($result) ? [] : $result;
}
function sw_selection_fit_fields(): array {
    return ['wheel_in'=>__('Wheel size', 'shadowalker'),'axle_mm'=>__('Dropout spacing', 'shadowalker'),'voltage_v'=>__('System voltage', 'shadowalker'),'mount'=>__('Motor position', 'shadowalker'),'brake'=>__('Brake type', 'shadowalker')];
}
function sw_selection_fit_choices(): array {
    return ['mount'=>['front'=>__('Front wheel', 'shadowalker'),'rear'=>__('Rear wheel', 'shadowalker'),'mid'=>__('Mid-drive', 'shadowalker')], 'brake'=>['disc'=>__('Disc brake', 'shadowalker'),'rim'=>__('Rim brake', 'shadowalker')]];
}
function sw_selection_validate_fit($raw) {
    if (!is_array($raw)) { return sw_selection_error('selection_rules'); }
    $out=[];
    foreach (sw_selection_fit_fields() as $key=>$label) {
        if (!isset($raw[$key])) { continue; }
        if (!is_array($raw[$key]) || !array_is_list($raw[$key]) || count($raw[$key])>20 || !$raw[$key]) { return sw_selection_error('selection_rules'); }
        $out[$key]=[];
        foreach ($raw[$key] as $value) {
            if (isset(sw_selection_fit_choices()[$key])) { if (!is_string($value) || !isset(sw_selection_fit_choices()[$key][$value])) { return sw_selection_error('selection_rules'); } }
            else { $field=sw_selection_fields()[$key]; if (!is_scalar($value) || !is_numeric($value) || !is_finite((float)$value) || $value<$field[2] || $value>$field[3]) { return sw_selection_error('selection_rules'); } $value=(float)$value; }
            $out[$key][]=$value;
        }
    }
    if (array_diff(array_keys($raw),array_keys(sw_selection_fit_fields()))) { return sw_selection_error('selection_rules'); }
    return $out;
}
function sw_selection_measurements($raw) {
    if (!is_array($raw) || array_diff(array_keys($raw),array_keys(sw_selection_fit_fields()))) { return sw_selection_error(); }
    $out=[];
    foreach (sw_selection_fit_fields() as $key=>$label) {
        $value=$raw[$key] ?? '';
        if ($value === '' || $value === null) { $out[$key]=''; continue; }
        $test=sw_selection_validate_fit([$key=>[$value]]);
        if (is_wp_error($test)) { return $test; }
        $out[$key]=$test[$key][0];
    }
    return $out;
}
function sw_selection_evaluate($product, array $measurements): array {
    $profile=sw_selection_profile($product); $rules=sw_selection_validate_fit($product->get_meta('_sw_fit') ?: []);
    $unknown=[]; $mismatch=[];
    foreach (sw_selection_fit_fields() as $key=>$label) {
        if (!$profile['verified'] || is_wp_error($rules) || !isset($rules[$key]) || ($measurements[$key] ?? '') === '') { $unknown[]=$key; continue; }
        if (!in_array($measurements[$key],$rules[$key],true)) { $mismatch[]=$key; }
    }
    return ['status'=>$mismatch ? 'mismatch' : ($unknown ? 'review' : 'match'), 'unknown'=>$unknown, 'mismatch'=>$mismatch];
}
function sw_selection_revision($product): string {
    return substr(hash('sha256',wp_json_encode([$product->get_name(),$product->get_sku(),sw_selection_profile($product),sw_selection_options($product),$product->get_meta('_sw_fit')])),0,16);
}
require_once __DIR__ . '/selection-admin.php';
