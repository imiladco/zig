<?php
/**
 * دادهٔ خامِ ویجتِ «انتخابِ کانفیگِ محصول».
 *
 * سه چیز سنجیده می‌شود که هیچ‌کدام خطایِ PHP نمی‌دهند:
 *
 *   ۱. تعدادِ کشوها با محصول عوض می‌شود — نه همیشه دو تا.
 *   ۲. گزینهٔ اتریبیوتِ تاکسونومی نامِ ترم می‌گیرد، گزینهٔ اتریبیوتِ
 *      دلخواه با همان قاعده‌ای sanitize می‌شود که ووکامرس در ذخیرهٔ
 *      واریانت به کار می‌برد — وگرنه هیچ ترکیبی مچ نمی‌شود.
 *   ۳. واریانتِ پنهان/ناموجودِ غیرقابل‌خرید/بی‌قیمت از فهرست کنار می‌رود.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/woocommerce-stub.php';

$root = dirname(__DIR__);

require_once $root . '/includes/price.php';
require_once $root . '/includes/stock.php';
require_once $root . '/includes/rate-price.php';
require_once $root . '/includes/configurator.php';

use Zig3d_Widgets\Configurator;

/* --------------------------------------------------------------------------
 * تاکسونومیِ ساختگی — برایِ اتریبیوتِ ‎pa_material‎
 * ----------------------------------------------------------------------- */

$GLOBALS['__zig_taxonomies'] = ['pa_material' => true];
$GLOBALS['__zig_term_by_slug'] = [
    'pa_material' => [
        'glass'    => 'بلوک گلس سرامیک',
        'titanium' => 'تیتانیوم',
    ],
];

if (!function_exists('taxonomy_exists')) {
    function taxonomy_exists($taxonomy) {
        return isset($GLOBALS['__zig_taxonomies'][$taxonomy]);
    }
}
if (!function_exists('get_term_by')) {
    function get_term_by($field, $value, $taxonomy = '') {
        $name = $GLOBALS['__zig_term_by_slug'][$taxonomy][$value] ?? null;

        return null === $name ? false : (object) ['slug' => $value, 'name' => $name];
    }
}
if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) { return false; }
}

$GLOBALS['__zig_seq'] = 0;

/** یک محصولِ متغیر با اتریبیوت‌ها و واریانت‌هایِ ساختگی */
function zig_variable_product(array $attributes, array $variations): WC_Product {
    $ids = [];

    foreach ($variations as $v) {
        $variation = new WC_Product([
            'id'                   => ++$GLOBALS['__zig_seq'],
            'type'                 => 'variation',
            'regular'              => $v['price'] ?? '100',
            'variation_attrs'      => $v['attrs'] ?? [],
            'status'               => $v['status'] ?? 'instock',
            'visible'              => $v['visible'] ?? true,
            'purchasable'          => $v['purchasable'] ?? true,
        ]);

        $ids[] = $variation->get_id();
    }

    return new WC_Product([
        'id'                    => ++$GLOBALS['__zig_seq'],
        'type'                  => 'variable',
        'variation_attributes'  => $attributes,
        'children'              => $ids,
    ]);
}

/* ==========================================================================
 * کشوها
 * ======================================================================= */

Tests::group('کانفیگ › کشوها');

/* محصولِ ساده هیچ کشویی ندارد */
$simple = new WC_Product(['id' => ++$GLOBALS['__zig_seq'], 'type' => 'simple']);
Tests::same('محصولِ ساده کشو ندارد', Configurator::fields($simple), []);

/* یک اتریبیوت */
$one_attr = zig_variable_product(['config' => ['a', 'b']], []);
Tests::same('تعدادِ کشو با محصول عوض می‌شود', count(Configurator::fields($one_attr)), 1);

/* دو اتریبیوت، یکی تاکسونومی و یکی دلخواه */
$two_attr = zig_variable_product([
    'config'      => ['fast', 'slow'],
    'pa_material' => ['glass', 'titanium'],
], []);

$fields = Configurator::fields($two_attr);

Tests::same('هر دو کشو ساخته می‌شود', count($fields), 2);

$material_field = null;
foreach ($fields as $f) {
    if ('pa_material' === $f['key']) {
        $material_field = $f;
    }
}

Tests::ok('کشوی تاکسونومی پیدا شد', null !== $material_field);
Tests::same(
    'گزینهٔ تاکسونومی نامِ ترم می‌گیرد',
    $material_field['options'][0],
    ['value' => 'glass', 'label' => 'بلوک گلس سرامیک']
);

/* اتریبیوتِ دلخواه: مقدار sanitize می‌شود، برچسب همان متنِ خام می‌ماند */
$custom = zig_variable_product(['speed' => ['۵ محور']], []);
$custom_options = Configurator::fields($custom)[0]['options'];

Tests::same('مقدارِ گزینهٔ دلخواه sanitize می‌شود', $custom_options[0]['value'], '۵-محور');
Tests::same('برچسبش همان متنِ خام است', $custom_options[0]['label'], '۵ محور');

/* اتریبیوتِ بی‌گزینه، کشو نمی‌سازد */
$empty_attr = zig_variable_product(['ghost' => []], []);
Tests::same('اتریبیوتِ بی‌گزینه کشو نمی‌سازد', Configurator::fields($empty_attr), []);

/* ==========================================================================
 * واریانت‌ها
 * ======================================================================= */

Tests::group('کانفیگ › واریانت‌ها');

$product = zig_variable_product(
    ['config' => ['a', 'b'], 'material' => ['glass', 'titanium']],
    [
        ['price' => '100', 'attrs' => ['config' => 'a', 'material' => 'glass']],
        ['price' => '150', 'attrs' => ['config' => 'a', 'material' => 'titanium']],
        // پنهان — نباید در فهرست باشد
        ['price' => '999', 'attrs' => ['config' => 'b', 'material' => 'glass'], 'visible' => false],
        // غیرقابلِ‌خرید — نباید در فهرست باشد
        ['price' => '999', 'attrs' => ['config' => 'b', 'material' => 'titanium'], 'purchasable' => false],
    ]
);

$rows = Configurator::variations($product);

Tests::same('فقط واریانت‌هایِ دیده‌شدنی و قابلِ‌خرید', count($rows), 2);
Tests::same('اتریبیوتِ خودِ واریانت برمی‌گردد', $rows[0]['attributes'], ['config' => 'a', 'material' => 'glass']);
Tests::same('قیمتِ همان واریانت', $rows[0]['price'], '100');

/*
 * ‎AVAILABLE‎ درست است، نه ‎IN_STOCK‎: فیکسچر مدیریتِ موجودی را روشن
 * نکرده — دقیقاً همان چیزی که این فروشگاه واقعاً دارد (بدونِ تعداد).
 * ویجت این دو را در یک بجِ واحد («موجود در انبار») جمع می‌کند.
 */
Tests::same('وضعیتِ موجودیِ همان واریانت', $rows[0]['stock_state'], Zig3d_Widgets\Stock::AVAILABLE);

/* واریانتِ ناموجود هم در فهرست می‌ماند — قیمت دارد، فقط وضعیتش فرق دارد */
$with_oos = zig_variable_product(
    ['config' => ['a', 'b']],
    [
        ['price' => '100', 'attrs' => ['config' => 'a'], 'status' => 'instock'],
        ['price' => '120', 'attrs' => ['config' => 'b'], 'status' => 'outofstock'],
    ]
);

$oos_rows = Configurator::variations($with_oos);

Tests::same('واریانتِ ناموجود هم می‌ماند', count($oos_rows), 2);
Tests::same('وضعیتش ناموجود است', $oos_rows[1]['stock_state'], Zig3d_Widgets\Stock::OUT_OF_STOCK);

/* محصولِ ساده هیچ واریانتی ندارد */
Tests::same('محصولِ ساده واریانت ندارد', Configurator::variations($simple), []);
