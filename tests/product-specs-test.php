<?php
/**
 * ویجتِ «مشخصاتِ فنی محصول» — رندرِ واقعی، نه فقط ثبتِ کنترل‌ها.
 *
 * ‎controls-test.php‎/‎selectors-test.php‎ سلامتِ کنترل‌ها را می‌سنجند؛ اینجا
 * سنجیده می‌شود که با یک محصول و یک کتابخانهٔ گروهِ واقعی، خروجیِ HTML
 * درست است: گروه‌هایِ چند دسته یک‌جا می‌شوند و تکراری نمی‌مانند، مشخصه‌ای
 * که رویِ این محصول مقدار ندارد اصلاً چاپ نمی‌شود، و حالتِ آکاردئونی/تخت
 * تگِ درستش را می‌سازد.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/elementor-stub.php';
require_once __DIR__ . '/lib/woocommerce-stub.php';

$root = dirname(__DIR__);

require_once $root . '/includes/svg.php';
require_once $root . '/includes/markup.php';
require_once $root . '/includes/price.php';
require_once $root . '/includes/widgets/traits/box.php';
require_once $root . '/includes/spec-group.php';
require_once $root . '/includes/spec-store.php';
require_once $root . '/includes/spec-value.php';
require_once $root . '/includes/widgets/product-specs.php';

use Zig3d_Widgets\Spec_Store;
use Zig3d_Widgets\Widgets\Product_Specs;

/**
 * ‎Spec_Store‎ کشِ استاتیکِ خودش را دارد (‎cache‎/‎usage‎) — بینِ سناریوها
 * باید خالی شود، وگرنه سناریویِ دوم دادهٔ سناریویِ اول را می‌بیند.
 */
function zig_reset_spec_store(): void {
    $ref = new ReflectionClass(Spec_Store::class);

    foreach (['cache', 'usage'] as $prop) {
        $p = $ref->getProperty($prop);
        $p->setAccessible(true);
        $p->setValue(null, null);
    }
}

function zig_reset_specs(): void {
    zig_reset_products();
    zig_reset_spec_store();
    $GLOBALS['__zig_options']   = [];
    $GLOBALS['__zig_term_meta'] = [];
}

/* ==========================================================================
 * بدونِ داده
 * ======================================================================= */

Tests::group('ویجتِ مشخصاتِ فنی › بدونِ داده');

zig_reset_specs();

$noProduct = new WC_Product(['id' => 1, 'cat_ids' => []]);

Tests::same(
    'محصولی که هیچ گروهی از هیچ دسته‌ای نمی‌گیرد، خروجیِ خالی می‌دهد',
    zig_render(Product_Specs::class, ['product_id' => 1]),
    ''
);

/* ==========================================================================
 * مسیرِ کامل
 * ======================================================================= */

Tests::group('ویجتِ مشخصاتِ فنی › مسیرِ کامل');

zig_reset_specs();

$GLOBALS['__zig_options'][Spec_Store::OPTION] = [
    'machining' => [
        'label' => 'سیستم ماشین‌کاری',
        'items' => [
            ['source' => 'attribute', 'attribute' => 'pa_color', 'label' => ''],
            ['source' => 'attribute', 'attribute' => 'pa_material', 'label' => ''], // رویِ این محصول تنظیم نشده
            ['source' => 'custom_meta', 'meta_key' => 'spindle_rpm', 'label' => 'دورِ اسپیندل'],
        ],
    ],
    'dimensions' => [
        'label' => 'ابعاد',
        'items' => [['source' => 'length', 'label' => '']],
    ],
];

$GLOBALS['__zig_term_meta'][10][Spec_Store::TERM_META] = ['machining', 'dimensions'];

$product = new WC_Product([
    'id'       => 2,
    'cat_ids'  => [10],
    'attr_map' => ['pa_color' => 'قرمز'],
    'meta'     => ['spindle_rpm' => '24000'],
    'length'   => '', // خالی — یعنی این مشخصه رویِ این محصول چیزی ندارد
]);

$html = zig_render(Product_Specs::class, ['product_id' => 2, 'layout_mode' => 'accordion', 'expand_first' => 'yes']);

Tests::ok('گروهِ اول با details و open رندر می‌شود', false !== strpos($html, '<details class="zig-specs__group" open>'));
Tests::ok('عنوانِ گروه چاپ می‌شود', false !== strpos($html, 'سیستم ماشین‌کاری'));
Tests::ok('ویژگیِ دارایِ مقدار می‌آید', false !== strpos($html, '<dd class="zig-specs__value">قرمز</dd>'));
Tests::ok('برچسبِ دلخواهِ متا به‌جایِ کلیدِ خام می‌آید', false !== strpos($html, '<dt class="zig-specs__label">دورِ اسپیندل</dt>'));
Tests::ok('مقدارِ متا می‌آید', false !== strpos($html, '<dd class="zig-specs__value">24000</dd>'));

Tests::same(
    'مشخصه‌ای که این محصول برایش مقدار ندارد (pa_material) اصلاً ردیف نمی‌شود',
    substr_count($html, 'zig-specs__row'),
    2 // فقط pa_color و spindle_rpm — گروهِ «ابعاد» هم کلاً حذف شد چون length خالی بود
);

Tests::ok(
    'گروهِ «ابعاد» که هیچ مشخصهٔ پرمقداری نداشت اصلاً نمی‌آید',
    false === strpos($html, 'ابعاد')
);

/* ==========================================================================
 * حالتِ تخت
 * ======================================================================= */

Tests::group('ویجتِ مشخصاتِ فنی › حالتِ تخت');

$flat = zig_render(Product_Specs::class, ['product_id' => 2, 'layout_mode' => 'flat']);

Tests::ok('در حالتِ تخت از details خبری نیست', false === strpos($flat, '<details'));
Tests::ok('گروه با div رندر می‌شود', false !== strpos($flat, '<div class="zig-specs__group">'));
Tests::ok('فلشِ باز/بسته در حالتِ تخت نمی‌آید', false === strpos($flat, 'zig-specs__chevron'));

/* ==========================================================================
 * گروهِ اول بسته (وقتی expand_first خاموش است)
 * ======================================================================= */

Tests::group('ویجتِ مشخصاتِ فنی › expand_first خاموش');

$closed = zig_render(Product_Specs::class, ['product_id' => 2, 'layout_mode' => 'accordion', 'expand_first' => '']);

Tests::ok('هیچ گروهی open ندارد', false === strpos($closed, ' open>'));

/* ==========================================================================
 * چند دسته، همان گروه — تکراری نمی‌شود
 * ======================================================================= */

Tests::group('ویجتِ مشخصاتِ فنی › دوبارگی بینِ دسته‌ها');

zig_reset_specs();

$GLOBALS['__zig_options'][Spec_Store::OPTION] = [
    'machining' => [
        'label' => 'سیستم ماشین‌کاری',
        'items' => [['source' => 'attribute', 'attribute' => 'pa_color', 'label' => '']],
    ],
];

// هر دو دسته همان یک گروه را دارند
$GLOBALS['__zig_term_meta'][10][Spec_Store::TERM_META] = ['machining'];
$GLOBALS['__zig_term_meta'][11][Spec_Store::TERM_META] = ['machining'];

$dup = new WC_Product(['id' => 3, 'cat_ids' => [10, 11], 'attr_map' => ['pa_color' => 'سبز']]);

$html2 = zig_render(Product_Specs::class, ['product_id' => 3]);

Tests::same(
    'گروهِ مشترکِ دو دسته فقط یک‌بار می‌آید',
    substr_count($html2, 'zig-specs__group-title-text'),
    1
);
