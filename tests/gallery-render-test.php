<?php
/**
 * مارک‌آپ گالری.
 *
 * دو چیز اینجا سنجیده می‌شود که هیچ‌کدام «خطا» تولید نمی‌کنند:
 *
 * ۱. لنگرها. هر بندانگشتی ‎href="#id"‎ دارد و باید دقیقاً به یکی از
 *    فریم‌ها برسد. اگر الگوی ساختِ ‎id‎ در دو تابعِ جدا نوشته شود و روزی
 *    یکی‌شان عوض شود، هیچ چیزی نمی‌شکند: صفحه رندر می‌شود، عکس‌ها سر
 *    جایشان‌اند، فقط کلیک روی بندانگشتی هیچ کاری نمی‌کند — و مسیرِ
 *    بدونِ JS بی‌صدا از بین می‌رود، همان مسیری که کسی دستی امتحانش
 *    نمی‌کند.
 *
 * ۲. حذفِ کنترل‌های بی‌معنی. محصولِ تک‌عکس نباید فلش و شمارنده و نوار
 *    بندانگشتی داشته باشد. اگر داشته باشد، خروجی «درست» به نظر می‌رسد و
 *    فقط کاربر یک فلشِ بی‌اثر می‌بیند.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/elementor-stub.php';
require_once __DIR__ . '/lib/woocommerce-stub.php';

$root = dirname(__DIR__);

require_once $root . '/includes/svg.php';
require_once $root . '/includes/markup.php';
require_once $root . '/includes/selector.php';
require_once $root . '/includes/price.php';
require_once $root . '/includes/stock.php';
require_once $root . '/includes/gallery.php';
require_once $root . '/includes/widgets/product-gallery.php';

use Zig3d_Widgets\Widgets\Product_Gallery;

/**
 * رندر گالری برای یک محصولِ ساختگی.
 *
 * ‎show_zoom‎ عمداً خاموش است: این کمکی مالِ سنجه‌های خودِ گالریِ اصلی
 * است (از قبلِ لایت‌باکس نوشته شده)، و لایت‌باکس هرچه گالری دارد را
 * دوباره تولید می‌کند — اگر روشن می‌ماند، شمارش‌های «چهار فریم» و
 * «چهار بندانگشتی» همه‌جا دوبرابر می‌شدند و این فایل باید کورکورانه
 * تکشان می‌کرد. سنجه‌های خودِ زوم/لایت‌باکس جدا و صریح‌اند.
 */
function zig_gallery(array $product, array $settings = []): string {
    new WC_Product($product + ['id' => 7, 'name' => 'دستگاه نمونه']);

    $GLOBALS['__zig_is_product'] = true;
    $GLOBALS['__zig_queried']    = $product['id'] ?? 7;

    return zig_render(Product_Gallery::class, $settings + [
        'with_featured' => 'yes',
        'show_nav'      => 'yes',
        'show_counter'  => 'yes',
        'show_thumbs'   => 'yes',
        'loop'          => 'yes',
        'show_zoom'     => '',
    ]);
}

/* ==========================================================================
 * ساختار پایه
 * ======================================================================= */

Tests::group('گالری › مارک‌آپ');

$html = zig_gallery(['id' => 7, 'image' => 11, 'gallery' => [12, 13, 14]]);

Tests::keeps('ریشه رندر می‌شود', $html, 'class="zig-gallery zig-gallery--fill"');
Tests::keeps('قلاب اسکریپت روی ریشه است', $html, 'data-zig-gallery');
Tests::keeps('ظرف اسکرول رندر می‌شود', $html, 'class="zig-gallery__frames"');
Tests::keeps('نوار بندانگشتی رندر می‌شود', $html, 'class="zig-gallery__thumbs"');

Tests::same(
    'به تعداد اسلایدها فریم هست',
    substr_count($html, 'class="zig-gallery__frame"'),
    4
);

Tests::same(
    'و به همان تعداد بندانگشتی',
    substr_count($html, 'class="zig-gallery__thumb-link"'),
    4
);

/*
 * تصویر شاخص باید اسلاید اول باشد. اگر روزی ترتیبِ ‎Gallery::order()‎
 * برعکس شود، همه‌چیز رندر می‌شود و فقط کاربر با تصویر اشتباه روبه‌رو
 * می‌شود — چیزی که فقط با دیدنِ خودِ فروشگاه معلوم می‌شود.
 */
Tests::ok(
    'اسلاید اول، تصویر شاخص است',
    false !== strpos($html, 'data-zig-index="0"')
        && strpos($html, '/img/11.jpg') > strpos($html, 'data-zig-index="0"')
        && strpos($html, '/img/11.jpg') < strpos($html, 'data-zig-index="1"'),
    'ترتیب اسلایدها'
);

/* ==========================================================================
 * لنگرها
 * ======================================================================= */

Tests::group('گالری › لنگرها');

preg_match_all('/<div class="zig-gallery__frame" id="([^"]+)"/', $html, $frames);
preg_match_all('/class="zig-gallery__thumb-link" href="#([^"]+)"/', $html, $links);

Tests::same('هر فریم شناسهٔ خودش را دارد', count($frames[1]), 4);
Tests::same('هر بندانگشتی یک لنگر دارد', count($links[1]), 4);

Tests::ok(
    'شناسه‌ها یکتا هستند',
    count($frames[1]) === count(array_unique($frames[1])),
    implode('، ', $frames[1])
);

Tests::same(
    'هر لنگر دقیقاً به فریمِ متناظرش می‌رسد',
    array_diff($links[1], $frames[1]),
    []
);

Tests::same(
    'و ترتیبشان هم یکی است',
    $links[1],
    $frames[1]
);

/*
 * شناسه باید شناسهٔ *نمونه* را داشته باشد، نه یک ثابت. دو گالری در یک
 * صفحه — مثلاً در یک اسلایدر مقایسه — با شناسهٔ ثابت، لنگرهای تکراری
 * می‌ساختند و کلیک در گالری دوم، گالری اول را جابه‌جا می‌کرد.
 */
Tests::ok(
    'شناسه به نمونهٔ ویجت گره خورده است',
    false !== strpos($frames[1][0], 'testid'),
    $frames[1][0]
);

/* ==========================================================================
 * محصول تک‌عکس
 * ======================================================================= */

Tests::group('گالری › تک‌عکس');

$one = zig_gallery(['id' => 8, 'image' => 21, 'gallery' => []]);

Tests::keeps('خودِ گالری رندر می‌شود', $one, 'class="zig-gallery__frames"');
Tests::blocks('ولی فلشی در کار نیست', $one, 'zig-gallery__nav');
Tests::blocks('و شمارنده‌ای هم نیست', $one, 'zig-gallery__counter');
Tests::blocks('و نوار بندانگشتی هم نه', $one, 'zig-gallery__thumbs');

/*
 * تکراری هم همین است: شاخصی که در گالری هم آمده، یک اسلاید است نه دو —
 * پس این محصول هم «تک‌عکس» حساب می‌شود و نباید کنترلِ حرکت بگیرد.
 */
$dup = zig_gallery(['id' => 9, 'image' => 31, 'gallery' => [31]]);

Tests::blocks('شاخصِ تکراری، فلش نمی‌سازد', $dup, 'zig-gallery__nav');

Tests::same(
    'و فقط یک فریم دارد',
    substr_count($dup, 'class="zig-gallery__frame"'),
    1
);

/* ==========================================================================
 * تنظیم‌ها
 * ======================================================================= */

Tests::group('گالری › تنظیم‌ها');

$sizes = zig_gallery(
    ['id' => 10, 'image' => 41, 'gallery' => [42]],
    ['image_size' => 'large', 'thumb_size' => 'thumbnail']
);

Tests::keeps('اندازهٔ صحنه اعمال می‌شود', $sizes, 'data-size="large"');
Tests::keeps('و اندازهٔ بندانگشتی جدا از آن است', $sizes, 'data-size="thumbnail"');

/*
 * اسلاید اول ‎eager‎ است و بقیه ‎lazy‎: بزرگ‌ترین تصویرِ صفحهٔ محصول
 * همین است و ‎lazy‎ کردنش مستقیم به LCP می‌خورد.
 */
Tests::keeps('اسلاید اول تنبل نیست', $sizes, 'loading="eager"');
Tests::keeps('ولی بقیه هستند', $sizes, 'loading="lazy"');

/*
 * فقط تصویرِ اول اولویتِ دانلود بالا می‌گیرد؛ آن یکی معمولاً بزرگ‌ترین
 * عنصرِ صفحهٔ محصول است. اگر همه این ویژگی را بگیرند، مرورگر هیچ اولویتی
 * نمی‌بیند و خودِ کنترل بی‌اثر می‌شود.
 */
Tests::same(
    'فقط یک تصویر اولویتِ بالا دارد',
    substr_count($sizes, 'fetchpriority="high"'),
    1
);

Tests::ok(
    'و آن یکی، اسلاید اول است',
    strpos($sizes, 'fetchpriority="high"') < strpos($sizes, 'data-zig-index="1"'),
    'ترتیب fetchpriority'
);

$capped = zig_gallery(
    ['id' => 11, 'image' => 51, 'gallery' => [52, 53, 54, 55]],
    ['max' => 3]
);

Tests::same(
    'سقف تعداد اعمال می‌شود',
    substr_count($capped, 'class="zig-gallery__frame"'),
    3
);

$nofeat = zig_gallery(
    ['id' => 12, 'image' => 61, 'gallery' => [62, 63]],
    ['with_featured' => '']
);

Tests::blocks('شاخصِ خاموش رندر نمی‌شود', $nofeat, '/img/61.jpg');
Tests::keeps('ولی گالری سر جایش است', $nofeat, '/img/62.jpg');

$noloop = zig_gallery(['id' => 13, 'image' => 71, 'gallery' => [72]], ['loop' => '']);

Tests::keeps('حالت غیرچرخشی به اسکریپت اعلام می‌شود', $noloop, 'data-zig-loop="0"');

/*
 * حالتِ پرکننده یک کلاس روی ریشه است نه یک متغیر، چون یک *چیدمانِ* دیگر
 * است نه یک مقدار: آنجا عرض از ‎flex‎ می‌آید و ارتفاع از ‎aspect-ratio‎.
 * با متغیر، باید همان دو خاصیت را در هر دو حالت با مقدارهای خنثی
 * می‌نوشتیم.
 */
$fixed = zig_gallery(['id' => 14, 'image' => 81, 'gallery' => [82]], ['thumbs_fill' => '']);

Tests::blocks('حالت اندازهٔ ثابت، کلاس پرکننده نمی‌گیرد', $fixed, 'zig-gallery--fill');

/* ==========================================================================
 * دسترسی‌پذیری
 * ======================================================================= */

Tests::group('گالری › دسترسی');

Tests::keeps('بندانگشتی اول از ابتدا فعال است', $html, 'aria-current="true"');

Tests::same(
    'و فقط یکی فعال است',
    substr_count($html, 'aria-current="true"'),
    1
);

/*
 * شمارنده برای صفحه‌خوان پنهان است: یک تکه متنِ «۱ / ۴» که به هیچ چیزی
 * وصل نیست، بیشتر گیج‌کننده است تا مفید — همان اطلاعات از برچسبِ
 * بندانگشتی‌ها می‌رسد.
 */
Tests::keeps('شمارنده از صفحه‌خوان پنهان است', $html, 'class="zig-gallery__counter" aria-hidden="true"');

Tests::keeps('ظرف اسکرول با صفحه‌کلید قابل رسیدن است', $html, 'tabindex="0"');

/*
 * ‎alt‎ بندانگشتی عمداً خالی است و متنِ جایگزین روی خودِ لینک نشسته:
 * وگرنه صفحه‌خوان برای هر بندانگشتی دو بار یک چیز می‌خواند — یک بار نام
 * محصول و یک بار «تصویر ۲».
 */
Tests::keeps('بندانگشتی برچسب متنی دارد', $html, 'aria-label="تصویر ۱"');
Tests::keeps('و تصویرش alt خالی دارد', $html, 'class="zig-gallery__thumb-image" loading="lazy" decoding="async" alt=""');

/* ==========================================================================
 * دکمهٔ بزرگ‌نمایی و لایت‌باکس
 * ======================================================================= */

Tests::group('گالری › بزرگ‌نمایی');

$zoomed = zig_gallery(
    ['id' => 20, 'image' => 91, 'gallery' => [92, 93]],
    ['show_zoom' => 'yes', 'lightbox_size' => 'large']
);

Tests::keeps('دکمهٔ بزرگ‌نمایی رندر می‌شود', $zoomed, 'class="zig-gallery__zoom"');
Tests::keeps('لینکِ واقعی به تصویرِ اصلیِ اسلاید اول', $zoomed, 'href="https://zig3d.test/full/91-large.jpg"');
Tests::keeps('نشانهٔ باز کردنِ دیالوگ', $zoomed, 'aria-haspopup="dialog"');
Tests::keeps('دیالوگِ لایت‌باکس رندر می‌شود', $zoomed, '<dialog class="zig-gallery__lightbox zig-gallery--fill"');
Tests::keeps('و قلاب جاوااسکریپت رویش هست', $zoomed, 'data-zig-lightbox');
Tests::keeps('دکمهٔ بستن رندر می‌شود', $zoomed, 'data-zig-lightbox-close');

/*
 * ‎Gallery‎ی داخلِ لایت‌باکس چرخشی‌بودن را از خودِ دیالوگ می‌خواند، نه از
 * ‎<figure>‎. اگر این مقدار روی دیالوگ نمی‌نشست، فلشِ قبلی روی اسلایدِ
 * اول همیشه غیرفعال می‌ماند — حتی وقتی مدیر چرخشی را روشن کرده.
 * ‎(?=...)‎ چون هر دو ‎<figure>‎ و ‎<dialog>‎ همین صفت را دارند و ترتیبشان
 * در رشته مهم نیست.
 */
Tests::same(
    'دیالوگ هم مقدارِ چرخشی را می‌گیرد',
    substr_count($zoomed, 'data-zig-loop="1"'),
    2 // یکی روی <figure>، یکی روی <dialog>
);

$noloop_zoomed = zig_gallery(
    ['id' => 23, 'image' => 98, 'gallery' => [99]],
    ['show_zoom' => 'yes', 'loop' => '']
);

Tests::same(
    'و وقتی خاموش است، هر دو صفر می‌گیرند',
    substr_count($noloop_zoomed, 'data-zig-loop="0"'),
    2
);

preg_match('/id="([^"]+)"[^>]*data-zig-lightbox/', $zoomed, $dialog_id);
preg_match('/data-zig-zoom aria-haspopup="dialog" aria-controls="([^"]+)"/', $zoomed, $controls);

Tests::ok(
    'دیالوگ شناسه دارد',
    !empty($dialog_id[1]),
    'یافت نشد'
);

Tests::same(
    '‎aria-controls‎ دکمه دقیقاً همان شناسهٔ دیالوگ است',
    $controls[1] ?? null,
    $dialog_id[1] ?? null
);

/*
 * فریم‌های داخلِ لایت‌باکس دوبارهٔ همان اسلایدهایند، پس اگر شناسه‌شان با
 * فریم‌های صحنهٔ اصلی یکی می‌ماند، دو عنصر در صفحه یک ‎id‎ داشتند — و
 * لنگرِ بندانگشتیِ لایت‌باکس به فریمِ صحنهٔ اصلی می‌پرید، نه فریمِ خودش.
 */
preg_match_all('/id="(zig-gal-testid[^"]*)"/', $zoomed, $all_ids);

Tests::ok(
    'همهٔ شناسه‌های صفحه یکتا هستند',
    count($all_ids[1]) === count(array_unique($all_ids[1])),
    implode('، ', array_diff_assoc($all_ids[1], array_unique($all_ids[1])))
);

Tests::same(
    'به همان تعداد اسلاید، فریم در لایت‌باکس هم هست',
    substr_count($zoomed, 'data-zig-index="0"'),
    2 // یکی در صحنهٔ اصلی، یکی در لایت‌باکس
);

/*
 * اسلایدِ اولِ لایت‌باکس نباید ‎eager‎/‎fetchpriority‎ بگیرد — تا باز نشدنِ
 * دیالوگ اصلاً دیده نمی‌شود، پس این اولویت آنجا فقط رقیبِ دانلودِ همان
 * تصویرِ واقعاً روی صفحه است.
 */
Tests::same(
    'فقط یک تصویر در کل صفحه اولویتِ بالا دارد',
    substr_count($zoomed, 'fetchpriority="high"'),
    1
);

Tests::same(
    'اندازهٔ لایت‌باکس جدا از اندازهٔ صحنه است',
    substr_count($zoomed, 'data-size="large"'),
    3 // سه اسلاید، هرکدام یک تصویر در لایت‌باکس
);

/* --------------------------------------------------------------------------
 * خاموش‌کردن
 * -------------------------------------------------------------------------- */

$nozoom = zig_gallery(
    ['id' => 21, 'image' => 95, 'gallery' => [96]],
    ['show_zoom' => '']
);

Tests::blocks('بدونِ این تنظیم، دکمه رندر نمی‌شود', $nozoom, 'zig-gallery__zoom');
Tests::blocks('و دیالوگ هم اصلاً چاپ نمی‌شود', $nozoom, '<dialog');

/* --------------------------------------------------------------------------
 * محصولِ تک‌عکس: بزرگ‌نمایی همچنان معنا دارد
 * -------------------------------------------------------------------------- */

/*
 * برخلافِ فلش/شمارنده/بندانگشتی که برای یک عکس بی‌معنی‌اند، دیدنِ
 * بزرگ‌ترِ همان یک عکس هنوز فایده دارد — پس این سه‌تا با تک‌عکس حذف
 * می‌شوند ولی بزرگ‌نمایی نه.
 */
$single_zoom = zig_gallery(['id' => 22, 'image' => 97, 'gallery' => []], ['show_zoom' => 'yes']);

Tests::keeps('با تک‌عکس هم دکمه هست', $single_zoom, 'class="zig-gallery__zoom"');
Tests::keeps('دیالوگ هم هست', $single_zoom, '<dialog class="zig-gallery__lightbox');
Tests::blocks('ولی داخلش فلشی نیست', $single_zoom, 'zig-gallery__nav');
Tests::blocks('و نوار بندانگشتی هم نه', $single_zoom, 'zig-gallery__thumbs');

/* ==========================================================================
 * نشان
 * ======================================================================= */

Tests::group('گالری › نشان');

/*
 * پیش‌فرض خاموش است — این یک ادعاست («تصویرِ واقعی، نه رندر») که فقط
 * وقتی درست است که مدیر خودش تأییدش کند. اگر بی‌سروصدا روشن می‌ماند، هر
 * محصولی — حتی آن‌که فقط رندرِ سه‌بعدی دارد — همین برچسب را می‌گرفت.
 */
$default_badge = zig_gallery(['id' => 24, 'image' => 100, 'gallery' => [101]]);

Tests::blocks('پیش‌فرض، نشانی چاپ نمی‌شود', $default_badge, 'zig-gallery__badge');

$badged = zig_gallery(
    ['id' => 25, 'image' => 102, 'gallery' => [103]],
    ['show_badge' => 'yes', 'badge_text' => 'تصویر واقعی محصول']
);

Tests::keeps('با روشن‌کردنش، نشان رندر می‌شود', $badged, '<p class="zig-gallery__badge">تصویر واقعی محصول</p>');

/*
 * نشان اولین فرزندِ ‎<figure>‎ است — قبل از صحنه — چون با ‎align-self‎
 * جایگاهش را می‌گیرد، نه با ‎position: absolute‎. اگر جایش عوض شود،
 * دیگر بالای کارت نمی‌نشیند.
 */
Tests::ok(
    'و پیش از صحنه می‌آید',
    strpos($badged, 'zig-gallery__badge') < strpos($badged, 'zig-gallery__stage'),
    'ترتیب در DOM'
);

/*
 * متنِ خالی یعنی چیزی برای نمایش نیست — حتی اگر مدیر سوییچ را روشن
 * گذاشته باشد، چاپ‌کردن یک نشانِ خالی فقط یک قابِ بی‌معنی روی کارت
 * می‌گذاشت.
 */
$empty_badge = zig_gallery(
    ['id' => 26, 'image' => 104, 'gallery' => [105]],
    ['show_badge' => 'yes', 'badge_text' => '  ']
);

Tests::blocks('متنِ خالی، نشانی چاپ نمی‌کند', $empty_badge, 'zig-gallery__badge');

/*
 * خروجی خام است، اسکیپ‌شده در لحظهٔ چاپ — همان قاعدهٔ کل افزونه. اگر
 * متنِ نشان اینجا خام می‌ماند، یک مدیرِ بدخواه می‌توانست HTML تزریق کند.
 */
$xss_badge = zig_gallery(
    ['id' => 27, 'image' => 106, 'gallery' => [107]],
    ['show_badge' => 'yes', 'badge_text' => '<script>alert(1)</script>']
);

Tests::blocks('متنِ نشان اسکیپ می‌شود', $xss_badge, '<script>');
Tests::keeps('و به‌صورتِ متنِ امن باقی می‌ماند', $xss_badge, '&lt;script&gt;');
