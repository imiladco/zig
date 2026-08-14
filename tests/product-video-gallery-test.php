<?php
/**
 * ویجتِ «گالریِ ویدئویِ محصول» — رندرِ واقعی.
 *
 * لایهٔ دادهٔ خام (‎Video_Gallery_Field‎) در ‎video-gallery-field-test.php‎
 * سنجیده شده؛ اینجا فقط سنجیده می‌شود که رندرِ HTML طبقِ آن داده درست
 * است: آیتمِ اول همیشه ساختارِ «معرفی» دارد، بقیه ساختارِ «کارتِ ویدئو»،
 * گالریِ خالی کلِ ویجت را حذف می‌کند، و کلیدِ متافیلدِ قابل‌تنظیم واقعاً
 * اثر دارد.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/elementor-stub.php';
require_once __DIR__ . '/lib/woocommerce-stub.php';

$root = dirname(__DIR__);

require_once $root . '/includes/markup.php';
require_once $root . '/includes/price.php';
require_once $root . '/includes/video-gallery-field.php';
require_once $root . '/includes/widgets/product-video-gallery.php';

use Zig3d_Widgets\Widgets\Product_Video_Gallery;

/* ==========================================================================
 * بدونِ محصول / بدونِ گالری
 * ======================================================================= */

Tests::group('گالریِ ویدئو › بدونِ محصول یا بدونِ گالری');

zig_reset_products();

Tests::same('بدونِ محصولِ زمینه‌ای، چیزی رندر نمی‌شود', zig_render(Product_Video_Gallery::class, []), '');

$emptyGallery = new WC_Product(['id' => 1, 'meta' => []]);
$GLOBALS['product'] = $emptyGallery;

Tests::same('محصول هست ولی متایِ گالری نیست → چیزی رندر نمی‌شود (خارجِ ادیتور)', zig_render(Product_Video_Gallery::class, []), '');

/* ==========================================================================
 * پیامِ راهنما — فقط در ادیتور
 *
 * ‎editor_notice()‎ خصوصی است ولی ‎$is_editor‎ را صریح می‌گیرد (نه
 * فراخوانیِ داخلیِ ‎is_editing()‎ که به ‎\Elementor\Plugin‎ی واقعی نیاز
 * دارد و در استابِ سبکِ تست وجود ندارد) — دقیقاً همان الگویِ
 * ‎resolve_product()‎ در ویجتِ نمایشِ قابلیت‌ها؛ پس مستقیم با Reflection
 * و مقدارِ ‎true‎/‎false‎ سنجیده می‌شود.
 * ======================================================================= */

Tests::group('گالریِ ویدئو › پیامِ راهنما در ادیتور');

function zig_video_gallery_notice(bool $isEditor, string $message): string {
    $widget = zig_widget(Product_Video_Gallery::class);
    $method = new ReflectionMethod($widget, 'editor_notice');
    $method->setAccessible(true);

    ob_start();
    $method->invoke($widget, $isEditor, $message);

    return (string) ob_get_clean();
}

Tests::same('خارجِ ادیتور، هیچ‌چیز چاپ نمی‌شود', zig_video_gallery_notice(false, 'هیچ ویدئویی در x پیدا نشد'), '');

$editorHtml = zig_video_gallery_notice(true, 'هیچ ویدئویی در zig-product-video پیدا نشد');
Tests::ok('در ادیتور، پیامِ راهنما شاملِ کلیدِ متافیلد است', false !== strpos($editorHtml, 'zig-product-video'), $editorHtml);
Tests::ok('پیامِ راهنما با کلاسِ notice چاپ می‌شود', false !== strpos($editorHtml, 'zig-product-video__notice'), $editorHtml);

/* رندرِ کاملِ render() هم باید بدونِ گالری چیزی چاپ نکند، مستقل از ادیتور بودن یا نبودن — چون is_editing() خارجِ ادیتورِ واقعی همیشه false است */
zig_reset_products();
$noGallery = new WC_Product(['id' => 2, 'meta' => []]);
$GLOBALS['product'] = $noGallery;
Tests::same('render()ِ کامل هم بدونِ گالری چیزی چاپ نمی‌کند (خارجِ ادیتورِ واقعی)', zig_render(Product_Video_Gallery::class, []), '');

/* ==========================================================================
 * رندرِ واقعی — آیتمِ اول = معرفی، بقیه = کارتِ ویدئو
 * ======================================================================= */

Tests::group('گالریِ ویدئو › ساختارِ کارت‌ها');

zig_reset_products();
zig_register_attachment(101, [
    'alt'         => 'محفظهٔ کاری بزرگ',
    'description' => 'توضیحِ کاملِ ویدئوی اول، دو تا سه خط.',
    'url'         => 'https://zig3d.test/v/101.mp4',
    'meta'        => ['length_formatted' => '12:34'],
]);
zig_register_attachment(102, [
    'alt'  => 'نصب و راه‌اندازی',
    'url'  => 'https://zig3d.test/v/102.mp4',
    'meta' => ['length_formatted' => '8:15'],
]);
zig_register_attachment(103, [
    'alt'  => 'فرزکاری زیرکونیا',
    'url'  => 'https://zig3d.test/v/103.mp4',
    'meta' => ['length_formatted' => '5:48'],
]);

$product = new WC_Product([
    'id'    => 3,
    'meta'  => ['zig-product-video' => [101, 102, 103]],
]);
$GLOBALS['product'] = $product;

$html = zig_render(Product_Video_Gallery::class, []);

Tests::ok('ریشهٔ ویجت رندر شده', false !== strpos($html, 'zig-product-video" data-zig-video-gallery'), $html);
/*
 * شمارشِ ‎data-index="‎، نه ‎zig-product-video__card‎: کارتِ معرفی هم
 * کلاسِ خودش را دارد هم ‎--intro‎ی که با همان رشته شروع می‌شود، پس
 * substr_count رویِ خودِ نامِ کلاس برایِ کارتِ اول دوبار می‌شمارد.
 */
Tests::ok('سه کارت ساخته شده', 3 === substr_count($html, 'data-index="'), $html);
Tests::ok('فقط یک کارت کلاسِ intro دارد', 1 === substr_count($html, 'zig-product-video__card--intro'), $html);
Tests::ok('همان کارتِ اول است که is-active دارد', 1 === preg_match('/zig-product-video__card--intro is-active"/', $html) || 1 === preg_match('/zig-product-video__card is-active zig-product-video__card--intro"/', $html), $html);
Tests::ok('عنوانِ آیتمِ اول (از Alt) در کارتِ معرفی هست', false !== strpos($html, 'محفظهٔ کاری بزرگ'), $html);
Tests::ok('توضیحِ آیتمِ اول در کارتِ معرفی هست', false !== strpos($html, 'توضیحِ کاملِ ویدئوی اول'), $html);
Tests::ok('پلیر با ویدئویِ آیتمِ اول پر شده', false !== strpos($html, 'https://zig3d.test/v/101.mp4'), $html);
Tests::ok('duration badgeِ پلیر مقدارِ آیتمِ اول را دارد', false !== strpos($html, '12:34'), $html);
Tests::ok('کارت‌هایِ بعدی آیکونِ play دارند (نه توضیح)', 2 === substr_count($html, 'zig-product-video__icon'), $html);
Tests::ok('کارت‌هایِ بعدی متادیتایِ مدت‌زمان دارند', false !== strpos($html, 'zig-product-video__meta">8:15') || false !== strpos($html, '8:15'), $html);

/* ==========================================================================
 * فیلدِ خالی — عنوان/توضیح — خروجی را خراب نمی‌کند
 * ======================================================================= */

Tests::group('گالریِ ویدئو › فیلدهایِ خالی');

zig_reset_products();
zig_register_attachment(201, ['url' => 'https://zig3d.test/v/201.mp4']); // بدونِ alt/title/caption/description/duration
zig_register_attachment(202, ['alt' => 'دومی', 'url' => 'https://zig3d.test/v/202.mp4']); // بدونِ duration

$product = new WC_Product(['id' => 4, 'meta' => ['zig-product-video' => [201, 202]]]);
$GLOBALS['product'] = $product;

$html = zig_render(Product_Video_Gallery::class, []);

Tests::ok('خروجی هنوز رندر می‌شود، خرابی‌ای پیش نمی‌آید', false !== strpos($html, 'zig-product-video'), $html);
Tests::ok('کارتِ دوم بدونِ متادیتایِ مدت‌زمانِ خالی رندر می‌شود (تگِ meta چاپ نمی‌شود)', 0 === substr_count($html, 'zig-product-video__meta"></span>'), $html);

/* ==========================================================================
 * کلیدِ متافیلدِ قابل‌تنظیم
 * ======================================================================= */

Tests::group('گالریِ ویدئو › کلیدِ متافیلدِ سفارشی');

zig_reset_products();
zig_register_attachment(301, ['alt' => 'با کلیدِ دیگر', 'url' => 'https://zig3d.test/v/301.mp4']);

$product = new WC_Product(['id' => 5, 'meta' => ['custom-video-field' => [301]]]);
$GLOBALS['product'] = $product;

Tests::same('کلیدِ پیش‌فرض روی این محصول چیزی پیدا نمی‌کند', zig_render(Product_Video_Gallery::class, []), '');

$html = zig_render(Product_Video_Gallery::class, ['meta_field_key' => 'custom-video-field']);
Tests::ok('کلیدِ سفارشی درست خوانده می‌شود', false !== strpos($html, 'با کلیدِ دیگر'), $html);
