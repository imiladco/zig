<?php
/**
 * مقدارِ واقعیِ یک مشخصه، رویِ یک محصولِ مشخص.
 *
 * ‎Spec_Group‎ فقط شکلِ داده را می‌سنجد؛ اینجا سنجیده می‌شود که همان دادهٔ
 * پاک، رویِ یک ‎WC_Product‎ی مشخص، چه مقداری می‌دهد — و کِی چیزی نمی‌دهد
 * (محصولی که وزن ندارد، ویژگیِ انتخاب‌شده رویش تنظیم نشده).
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/woocommerce-stub.php';

$root = dirname(__DIR__);

require_once $root . '/includes/spec-group.php';
require_once $root . '/includes/spec-value.php';

use Zig3d_Widgets\Spec_Value;

/* ==========================================================================
 * ویژگی
 * ======================================================================= */

Tests::group('مقدارِ مشخصه › ویژگی');

zig_reset_products();

$product = new WC_Product(['id' => 1, 'attr_map' => ['pa_color' => 'قرمز، آبی']]);

$resolved = Spec_Value::resolve($product, ['source' => 'attribute', 'attribute' => 'pa_color', 'meta_key' => '', 'label' => '']);
Tests::same('مقدارِ ویژگی خوانده می‌شود', $resolved['value'], 'قرمز، آبی');
Tests::same('برچسبِ پیش‌فرض از wc_attribute_label می‌آید', $resolved['label'], 'pa_color');

$withLabel = Spec_Value::resolve($product, ['source' => 'attribute', 'attribute' => 'pa_color', 'meta_key' => '', 'label' => 'رنگِ بدنه']);
Tests::same('عنوانِ دلخواه برچسبِ پیش‌فرض را می‌پوشاند', $withLabel['label'], 'رنگِ بدنه');

Tests::same(
    'ویژگیِ تنظیم‌نشده رویِ این محصول هیچی نیست',
    Spec_Value::resolve($product, ['source' => 'attribute', 'attribute' => 'pa_material', 'meta_key' => '', 'label' => '']),
    null
);

/* ==========================================================================
 * دسته/برچسب/SKU
 * ======================================================================= */

Tests::group('مقدارِ مشخصه › دسته/برچسب/SKU');

zig_reset_products();

$product = new WC_Product([
    'id'         => 2,
    'sku'        => 'CNC-4X',
    'post_terms' => [
        'product_cat' => ['ماشین‌آلات', 'CNC'],
        'product_tag' => ['صنعتی'],
    ],
]);

Tests::same('دسته‌ها با «، » پیوند می‌خورند', Spec_Value::resolve($product, ['source' => 'category', 'attribute' => '', 'meta_key' => '', 'label' => ''])['value'], 'ماشین‌آلات، CNC');
Tests::same('برچسب‌ها هم همین‌طور', Spec_Value::resolve($product, ['source' => 'tag', 'attribute' => '', 'meta_key' => '', 'label' => ''])['value'], 'صنعتی');
Tests::same('SKU مستقیم خوانده می‌شود', Spec_Value::resolve($product, ['source' => 'sku', 'attribute' => '', 'meta_key' => '', 'label' => ''])['value'], 'CNC-4X');

Tests::same(
    'SKUی خالی هیچی نیست',
    Spec_Value::resolve(new WC_Product(['id' => 3]), ['source' => 'sku', 'attribute' => '', 'meta_key' => '', 'label' => '']),
    null
);

/* ==========================================================================
 * امتیاز و موجودی
 * ======================================================================= */

Tests::group('مقدارِ مشخصه › امتیاز و موجودی');

zig_reset_products();

$rated = new WC_Product(['id' => 4, 'rating_count' => 12, 'rating_avg' => '4.6']);
Tests::same('امتیازِ محصولِ نظرخورده', Spec_Value::resolve($rated, ['source' => 'rating', 'attribute' => '', 'meta_key' => '', 'label' => ''])['value'], '4.6 از ۵');

$unrated = new WC_Product(['id' => 5, 'rating_count' => 0]);
Tests::same(
    'محصولِ بی‌نظر امتیازی نشان نمی‌دهد',
    Spec_Value::resolve($unrated, ['source' => 'rating', 'attribute' => '', 'meta_key' => '', 'label' => '']),
    null
);

$stocked = new WC_Product(['id' => 6, 'status' => 'onbackorder']);
Tests::same(
    'وضعیتِ موجودی از نگاشتِ ووکامرس می‌آید',
    Spec_Value::resolve($stocked, ['source' => 'stock', 'attribute' => '', 'meta_key' => '', 'label' => ''])['value'],
    'قابل پیش‌سفارش'
);

/* ==========================================================================
 * وزن/طول/عرض/ارتفاع
 * ======================================================================= */

Tests::group('مقدارِ مشخصه › وزن و ابعاد');

zig_reset_products();
$GLOBALS['__zig_options']['woocommerce_weight_unit']    = 'kg';
$GLOBALS['__zig_options']['woocommerce_dimension_unit'] = 'cm';

$dims = new WC_Product(['id' => 7, 'weight' => '12.5', 'length' => '40', 'width' => '', 'height' => '20']);

Tests::same('وزن با واحد می‌آید', Spec_Value::resolve($dims, ['source' => 'weight', 'attribute' => '', 'meta_key' => '', 'label' => ''])['value'], '12.5 kg');
Tests::same('طول با واحد می‌آید', Spec_Value::resolve($dims, ['source' => 'length', 'attribute' => '', 'meta_key' => '', 'label' => ''])['value'], '40 cm');
Tests::same(
    'عرضِ خالی هیچی نیست — نه یک واحدِ تنها',
    Spec_Value::resolve($dims, ['source' => 'width', 'attribute' => '', 'meta_key' => '', 'label' => '']),
    null
);
Tests::same('ارتفاع با واحد می‌آید', Spec_Value::resolve($dims, ['source' => 'height', 'attribute' => '', 'meta_key' => '', 'label' => ''])['value'], '20 cm');

/* ==========================================================================
 * فیلدِ دلخواه (متا)
 * ======================================================================= */

Tests::group('مقدارِ مشخصه › فیلدِ دلخواه');

zig_reset_products();

$meta = new WC_Product(['id' => 8, 'meta' => ['spindle_rpm' => '24000', 'empty_key' => '']]);

Tests::same(
    'مقدارِ متا خوانده می‌شود',
    Spec_Value::resolve($meta, ['source' => 'custom_meta', 'attribute' => '', 'meta_key' => 'spindle_rpm', 'label' => ''])['value'],
    '24000'
);
Tests::same(
    'برچسبِ پیش‌فرضِ متا خودِ کلید است',
    Spec_Value::resolve($meta, ['source' => 'custom_meta', 'attribute' => '', 'meta_key' => 'spindle_rpm', 'label' => ''])['label'],
    'spindle_rpm'
);
Tests::same(
    'متایِ خالی هیچی نیست',
    Spec_Value::resolve($meta, ['source' => 'custom_meta', 'attribute' => '', 'meta_key' => 'empty_key', 'label' => '']),
    null
);
