<?php
/**
 * ویجتِ «نمایشِ قابلیت‌های محصول» — رندرِ واقعی و منطقِ «محصولِ
 * پیش‌نمایش».
 *
 * ‎controls-test.php‎/‎selectors-test.php‎ سلامتِ کنترل‌ها را می‌سنجند؛
 * ‎feature-repeater-test.php‎ لایهٔ دادهٔ خام را. اینجا سنجیده می‌شود که
 * با یک محصولِ واقعی، خروجیِ HTML درست است — سطرِ بی‌لیبل اصلاً چاپ
 * نمی‌شود، فیلدِ خالی تگِ خالی نمی‌سازد، شمارهٔ نمایشی از رویِ
 * ایندکسِ *بعدِ فیلتر* است — و این‌که کنترلِ «محصولِ پیش‌نمایش» فقط در
 * حالتِ ادیتور اثر دارد، نه رویِ سایتِ واقعی.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/elementor-stub.php';
require_once __DIR__ . '/lib/woocommerce-stub.php';

$root = dirname(__DIR__);

require_once $root . '/includes/svg.php';
require_once $root . '/includes/markup.php';
require_once $root . '/includes/price.php';
require_once $root . '/includes/feature-repeater.php';
require_once $root . '/includes/widgets/product-feature-showcase.php';

use Zig3d_Widgets\Widgets\Product_Feature_Showcase;

/**
 * ‎resolve_product()‎ خصوصی است — همان دلیلِ همیشگی: رفتارش وابسته به
 * ‎is_editing()‎ است که استابِ تست شبیه‌سازی نمی‌کند (کلاسِ
 * ‎\Elementor\Plugin‎ در محیطِ تست اصلاً وجود ندارد)، پس مستقیم با
 * Reflection صدا زده می‌شود — هم با ‎$is_editor=false‎ (سایتِ واقعی) هم
 * ‎true‎ (ادیتور).
 */
function zig_feature_resolve(array $settings, bool $is_editor) {
    $widget = zig_widget(Product_Feature_Showcase::class);
    $method = new ReflectionMethod($widget, 'resolve_product');
    $method->setAccessible(true);

    return $method->invoke($widget, $settings, $is_editor);
}

/* ==========================================================================
 * محصولِ پیش‌نمایش — فقط ادیتور
 * ======================================================================= */

Tests::group('نمایشِ قابلیت‌ها › محصولِ پیش‌نمایش');

zig_reset_products();

$current = new WC_Product(['id' => 1, 'meta' => []]);
$other   = new WC_Product(['id' => 9, 'meta' => []]);
$GLOBALS['product'] = $current;

Tests::same(
    'در سایتِ واقعی (is_editor=false)، «محصولِ پیش‌نمایش» نادیده گرفته می‌شود',
    zig_feature_resolve(['preview_product_id' => 9], false)->get_id(),
    1
);

Tests::same(
    'در ادیتور، «محصولِ پیش‌نمایش» محصولِ زمینه را می‌پوشاند',
    zig_feature_resolve(['preview_product_id' => 9], true)->get_id(),
    9
);

Tests::same(
    'در ادیتور، پیش‌نمایشِ خالی (۰) یعنی همان محصولِ زمینه',
    zig_feature_resolve(['preview_product_id' => 0], true)->get_id(),
    1
);

Tests::same(
    'در ادیتور، پیش‌نمایشِ ناموجود هم به محصولِ زمینه برمی‌گردد',
    zig_feature_resolve(['preview_product_id' => 424242], true)->get_id(),
    1
);

/* ==========================================================================
 * بدونِ داده
 * ======================================================================= */

Tests::group('نمایشِ قابلیت‌ها › بدونِ داده');

zig_reset_products();

$noProduct = new WC_Product(['id' => 1, 'meta' => []]);
$GLOBALS['product'] = $noProduct;

Tests::same('ریپیترِ خالی → کل ویجت رندر نمی‌شود', zig_render(Product_Feature_Showcase::class, []), '');

$oneRowLabelOnly = new WC_Product([
    'id'   => 2,
    'meta' => ['feature_showcase' => [['feature_showcase_label' => 'تنها قابلیت']]],
]);
$GLOBALS['product'] = $oneRowLabelOnly;

$html = zig_render(Product_Feature_Showcase::class, []);

/* ==========================================================================
 * تک‌قابلیتی → ناوبری مخفی
 * ======================================================================= */

Tests::group('نمایشِ قابلیت‌ها › تک‌قابلیتی');

Tests::ok('با یک قابلیتِ معتبر، ناوبری (تب‌لیست) اصلاً رندر نمی‌شود', false === strpos($html, 'zig-feature__tablist'));
Tests::ok('ولی خودِ محتوا هست', false !== strpos($html, 'تنها قابلیت'));
Tests::ok('پنل بدونِ role=tabpanel رندر می‌شود (چیزی برای ارجاع نیست)', false === strpos($html, 'role="tabpanel"'));

/* ==========================================================================
 * فیلترِ سطر و فیلدهایِ خالی، در سطحِ رندر
 * ======================================================================= */

Tests::group('نمایشِ قابلیت‌ها › رندرِ فیلدهایِ خالی');

zig_reset_products();

$rows = [
    ['feature_showcase_label' => '', 'feature_showcase_title' => 'باید نیاید'],
    ['feature_showcase_label' => 'اول', 'feature_showcase_title' => 'عنوانِ اول', 'feature_showcase_dec' => ''],
    ['feature_showcase_label' => 'دوم', 'feature_showcase_title' => '', 'feature_showcase_dec' => 'توضیحِ دوم'],
];

$product = new WC_Product(['id' => 3, 'meta' => ['feature_showcase' => $rows]]);
$GLOBALS['product'] = $product;

$html = zig_render(Product_Feature_Showcase::class, []);

Tests::ok('سطرِ بی‌لیبل اصلاً در خروجی نیست', false === strpos($html, 'باید نیاید'));
Tests::ok('دو تبِ معتبر ساخته می‌شود', 2 === substr_count($html, 'role="tab"'));
Tests::ok('شمارهٔ اولین تبِ معتبر ۰۱ است (نه ایندکسِ خامِ ۰۰)', false !== strpos($html, '۰۱'));
Tests::ok('شمارهٔ دومین تبِ معتبر ۰۲ است', false !== strpos($html, '۰۲'));
Tests::ok('توضیحِ خالیِ سطرِ اول تگِ p نساخته (صفر یا یک zig-feature__feature-desc)', 1 === substr_count($html, 'zig-feature__feature-desc'));
Tests::ok('عنوانِ خالیِ سطرِ دوم تگِ h3 نساخته (فقط یکی)', 1 === substr_count($html, 'zig-feature__feature-title'));

/* ==========================================================================
 * بدونِ تصویر → کلاسِ content-only
 * ======================================================================= */

Tests::group('نمایشِ قابلیت‌ها › بدونِ تصویر');

zig_reset_products();

$rows = [
    ['feature_showcase_label' => 'با عکس', 'feature_showcase_icon' => 'https://x.test/a.jpg'],
    ['feature_showcase_label' => 'بدونِ عکس'],
];
$product = new WC_Product(['id' => 4, 'meta' => ['feature_showcase' => $rows]]);
$GLOBALS['product'] = $product;

$html = zig_render(Product_Feature_Showcase::class, []);

preg_match_all('/<div class="([^"]*)" id="[^"]+-panel-\d+"/', $html, $panelTags);
$panelClasses = $panelTags[1] ?? [];

Tests::same('دو پنل ساخته می‌شود', count($panelClasses), 2);
Tests::ok('پنلِ اول (دارایِ عکس) کلاسِ no-media ندارد', false === strpos($panelClasses[0] ?? '', 'no-media'));
Tests::ok('پنلِ دوم (بدونِ عکس) کلاسِ no-media دارد', false !== strpos($panelClasses[1] ?? '', 'no-media'));
Tests::ok('فقط پنلِ اول تگِ media دارد', 1 === substr_count($html, 'zig-feature__media"'));

/* ==========================================================================
 * محصولِ نامعتبر
 * ======================================================================= */

Tests::group('نمایشِ قابلیت‌ها › محصولِ نامعتبر');

unset($GLOBALS['product']);
zig_reset_products();

Tests::same('بدونِ هیچ محصولِ زمینه‌ای، چیزی رندر نمی‌شود', zig_render(Product_Feature_Showcase::class, []), '');
