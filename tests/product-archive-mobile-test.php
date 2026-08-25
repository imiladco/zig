<?php
/**
 * نوارِ موبایل و شیت‌هایِ آرشیوِ محصولات.
 *
 * دو متدِ رندرِ تازه (‎render_mobile_bar‎/‎render_sort_sheet‎) مستقیم با
 * ریفلکشن سنجیده می‌شوند — همان تکنیکی که ‎download-archive-test.php‎ برایِ
 * صفحه‌بندی/فیلترهایِ فعال به‌کار برد — چون ساختنِ یک ‎WP_Query‎یِ واقعی
 * برایِ فراخوانیِ کاملِ ‎render()‎ از حوصلهٔ این سوییتِ استاب‌محور بیرون
 * است. چیزی که اینجا مهم است مارک‌آپ و کلاس‌ها و قراردادِ کلیک است، نه
 * خودِ کوئری (که تست‌هایِ خودِ آرشیو پوششش می‌دهند).
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/elementor-stub.php';
require_once __DIR__ . '/lib/woocommerce-stub.php';

$root = dirname(__DIR__);
require_once $root . '/includes/svg.php';
require_once $root . '/includes/markup.php';
require_once $root . '/includes/price.php';
require_once $root . '/includes/stock.php';
require_once $root . '/includes/selector.php';
require_once $root . '/includes/widgets/traits/link.php';
require_once $root . '/includes/query-state.php';
require_once $root . '/includes/facets.php';
require_once $root . '/includes/filter-schema.php';
require_once $root . '/includes/schema-store.php';
require_once $root . '/includes/sorting.php';
require_once $root . '/includes/attributes.php';
require_once $root . '/includes/archive-query.php';
require_once $root . '/includes/seo.php';
require_once $root . '/includes/card.php';
require_once $root . '/includes/product-card.php';
require_once $root . '/includes/widgets/product-archive.php';

use Zig3d_Widgets\Query_State;
use Zig3d_Widgets\Sorting;
use Zig3d_Widgets\Widgets\Product_Archive;

/*
 * ‎base_url()‎ برایِ ساختنِ ‎href‎ی گزینه‌هایِ ترتیب سراغِ ‎queried_term()‎ →
 * ‎get_queried_object()‎ می‌رود. اینجا محصولی/دسته‌ای در کار نیست، پس
 * ‎null‎ برمی‌گردانیم تا مسیرِ fallbackِ ‎home_url()‎ گرفته شود.
 */
if (!function_exists('get_queried_object')) {
    function get_queried_object() {
        return null;
    }
}

$widget = new Product_Archive();

$sorts = Sorting::sanitize_options([
    ['label' => 'پرفروش‌ترین', 'type' => 'popularity'],
    ['label' => 'جدیدترین', 'type' => 'date'],
    ['label' => 'ارزان‌ترین', 'type' => 'price'],
]);

// وضعیتی که «جدیدترین» (گزینهٔ دوم) را انتخاب کرده — تا برچسبِ نوار و
// گزینهٔ فعالِ شیت را بسنجیم
$second = Sorting::available($sorts, false)[1]['key'];
$state  = Query_State::create([], $second, 1);

$call = static function (string $method, array $args) use ($widget): string {
    $ref = new ReflectionMethod($widget, $method);
    $ref->setAccessible(true);
    ob_start();
    $ref->invokeArgs($widget, $args);
    return (string) ob_get_clean();
};

Tests::group('آرشیوِ موبایل › نوارِ فیلتر/ترتیب');

$settings = ['filters_title' => 'فیلتر ها', 'sorting_on' => 'yes'];

$bar = $call('render_mobile_bar', [$settings, $sorts, $state, true, true, 'flt-1', 'srt-1']);

Tests::keeps('نوارِ موبایل رندر می‌شود', $bar, 'zig-archive__mbar');
Tests::keeps('دکمهٔ فیلتر، دکمه است نه لینک', $bar, '<button type="button" class="zig-archive__mbar-btn zig-archive__mbar-btn--filter"');
Tests::keeps('دکمهٔ فیلتر به شیتِ فیلتر اشاره می‌کند', $bar, 'data-zig-open="filters" aria-expanded="false" aria-controls="flt-1"');
Tests::keeps('دکمهٔ ترتیب به شیتِ ترتیب اشاره می‌کند', $bar, 'data-zig-open="sort" aria-expanded="false" aria-controls="srt-1"');
Tests::keeps('متنِ «فیلتر ها» از عنوانِ فیلتر می‌آید', $bar, 'فیلتر ها');
Tests::keeps('برچسبِ ترتیب، گزینهٔ فعال را نشان می‌دهد', $bar, '<span class="zig-archive__mbar-text" data-zig-sort-label>جدیدترین</span>');

/*
 * ترتیبِ DOM: دکمهٔ فیلتر پیش از دکمهٔ ترتیب — با ‎justify-content:
 * space-between‎ در RTL یعنی فیلتر سمتِ راست و ترتیب سمتِ چپ، همان چیدمانِ
 * فیگما. جای دیداری کارِ CSS است، نه ترتیبِ HTML.
 */
Tests::ok(
    'فیلتر پیش از ترتیب در DOM می‌آید (راست/چپ کارِ CSS است)',
    strpos($bar, '--filter') < strpos($bar, '--sort')
);

/*
 * دو آیکونِ تازه (طبقِ اسپکِ دقیقِ فیگما): filled-path، نه stroke-based
 * ژنریکِ قبلی — با viewBoxِ خودشان، نه یک شبکهٔ مشترک.
 */
Tests::keeps('آیکونِ فیلتر کلاسِ اختصاصی دارد', $bar, 'zig-archive__mbar-icon--filter');
Tests::keeps('آیکونِ ترتیب کلاسِ اختصاصی دارد', $bar, 'zig-archive__mbar-icon--sort');
Tests::keeps('آیکونِ فیلتر viewBoxِ دقیقِ فیگما (۱۵×۱۴) را دارد', $bar, 'viewBox="0 0 15 14"');
Tests::keeps('آیکونِ ترتیب viewBoxِ دقیقِ فیگما (۲۱×۲۰) را دارد', $bar, 'viewBox="0 0 21 20"');
Tests::keeps('رنگِ آیکون از ‎currentColor‎ می‌آید (کنترل‌پذیر، مستقلِ از رنگِ برچسب)', $bar, 'fill="currentColor"');

$bar_no_filter = $call('render_mobile_bar', [$settings, $sorts, $state, false, true, 'flt-1', 'srt-1']);
Tests::blocks('بدونِ سایدبار، دکمهٔ فیلتر نمی‌آید', $bar_no_filter, '--filter');
Tests::keeps('ولی دکمهٔ ترتیب می‌ماند', $bar_no_filter, '--sort');

$bar_none = $call('render_mobile_bar', [$settings, $sorts, $state, false, false, 'flt-1', 'srt-1']);
Tests::same('بدونِ فیلتر و ترتیب، نوار اصلاً رندر نمی‌شود', $bar_none, '');

Tests::group('آرشیوِ موبایل › شیتِ ترتیب');

$sheet = $call('render_sort_sheet', [['sort_sheet_title' => 'مرتب‌سازی'] + $settings, $sorts, $state, 'srt-1', []]);

Tests::keeps('شیتِ ترتیب با شناسهٔ درست رندر می‌شود', $sheet, 'id="srt-1" class="zig-archive__sheet zig-archive__sheet--sort" data-zig-sheet="sort"');
Tests::keeps('دستگیرهٔ کشیدن دارد', $sheet, 'zig-archive__sheet-handle');
Tests::keeps('عنوانِ شیت از تنظیمات می‌آید', $sheet, '<h2 class="zig-archive__sheet-title">مرتب‌سازی</h2>');
Tests::keeps('بدنه اسلاتِ مشترکِ ترتیب است تا swap هر دو جا را به‌روز کند', $sheet, 'class="zig-archive__sheet-body" data-zig-part="sorts"');
Tests::keeps('گزینه‌هایِ ترتیب همان لیستِ مشترک‌اند', $sheet, 'class="zig-sorts"');

/*
 * گزینه‌هایِ ترتیب داخلِ شیت هم پیوندِ واقعی می‌مانند — قراردادِ کلیکِ
 * افزونه: چیزی که ناوبری/تغییرِ وضعیت است باید ‎<a href>‎ باشد، حتی وقتی
 * جاوااسکریپت آن را می‌گیرد. فقط دکمه‌هایِ بازکنندهٔ شیت ‎<button>‎اند.
 */
Tests::keeps('گزینه‌هایِ ترتیب پیوندِ واقعی‌اند', $sheet, '<a class="zig-sorts__pill');
Tests::keeps('گزینهٔ فعال (جدیدترین) در شیت is-active است', $sheet, 'is-active');

$sheet_no_title = $call('render_sort_sheet', [['sort_sheet_title' => ''] + $settings, $sorts, $state, 'srt-1', []]);
Tests::blocks('عنوانِ خالی، تیترِ شیت را حذف می‌کند', $sheet_no_title, 'zig-archive__sheet-title');

Tests::group('آرشیوِ موبایل › رگرسیونِ ستون و چرومِ فیلتر');

$widget_src = file_get_contents($root . '/includes/widgets/product-archive.php');
$css_src    = file_get_contents($root . '/assets/css/zig3d-widgets.css');

/*
 * باگِ «موبایل=۱ ولی ۲ ستون»: کنترلِ ستون باید پیش‌فرضِ بریک‌پوینتی
 * داشته باشد و ریستِ سختِ ‎@media(max-width:879px){...repeat(2)}‎ باید
 * رفته باشد — وگرنه آن ریست باز متغیرِ کنترل را نادیده می‌گیرد.
 */
Tests::ok(
    'کنترلِ ستون پیش‌فرضِ تبلت/موبایل دارد',
    false !== strpos($widget_src, "'tablet_default' => 2") && false !== strpos($widget_src, "'mobile_default' => 2")
);
Tests::ok(
    'ریستِ سختِ «۲ ستون در ≤۸۷۹» حذف شده',
    false === strpos($css_src, 'grid-template-columns: repeat(2, minmax(0, 1fr));')
        || false === strpos($css_src, '@media (max-width: 879px)')
);

/*
 * شیتِ فیلتر باید *همان* سایدبارِ دسکتاپ را نشان دهد — کلاهِ بنفشِ
 * ‎::before‎ و سربرگِ شیشه‌ای نباید در موبایل خاموش شوند.
 */
Tests::blocks('کلاهِ بنفشِ فیلتر در موبایل خاموش نشده', $css_src, '.zig-archive__filters::before {
		content: none;');

/*
 * حاشیهٔ نوارِ قرصی طبقِ اسپکِ دقیقِ فیگما یک‌دست نیست — هر ضلع مقدارِ
 * خودش را دارد (بالا ۲، راست ۱، پایین ۱، چپ ۲)، نه ‎۲ ۲ ۱ ۱‎ی نسخهٔ اول.
 */
Tests::keeps('حاشیهٔ نوار per-side دقیقِ فیگما است', $css_src, 'border-width: 2px 1px 1px 2px;');
Tests::blocks('حاشیهٔ نادرستِ نسخهٔ اول برنگشته', $css_src, 'border-width: 2px 2px 1px 1px;');

/*
 * رنگِ آیکون‌هایِ نوار مستقل از رنگِ برچسب و استایل‌پذیر است — نه
 * ‎currentColor‎ی به‌ارث‌رسیده از دکمهٔ بنفش/خاکستری.
 */
Tests::keeps('رنگِ آیکون‌هایِ نوار متغیرِ CSSِ مستقلِ خودش را دارد', $css_src, '--zig-mbar-icon-color, rgba(23, 23, 27, 0.9)');

/*
 * دکمه‌هایِ نوار ‎<button>‎ی خام‌اند؛ ‎reset.css‎ سراسریِ تم مستقیم روی
 * ‎button:hover/:focus‎ (شبه‌کلاس+نوع) می‌نشیند. یک کلاسِ تنها گاهی از
 * این جفت می‌بازد یا (رنگ) اصلاً رقیب ندارد — پس هر سلکتورِ ما باید
 * زنجیرهٔ ‎.zig-archive__mbar .zig-archive__mbar-btn…‎ باشد، نه کلاسِ تنها،
 * و رنگ باید رویِ هاور/فوکوس/اکتیو هم صریح تکرار شود.
 */
Tests::keeps('ریستِ پایهٔ دکمه با زنجیرِ دو کلاس نوشته شده (نه کلاسِ تنها)', $css_src, '.zig-archive__mbar .zig-archive__mbar-btn {
		display: inline-flex;
		align-items: center;
		gap: 8px;
		margin: 0;
		padding: 0;
		background: none;
		background-color: transparent;
		border: 0;
		box-shadow: none;
		text-shadow: none;
		text-decoration: none;');
Tests::keeps('حالتِ هاور/فوکوس/اکتیوِ دکمه هم با همان زنجیر خنثی شده', $css_src, '.zig-archive__mbar .zig-archive__mbar-btn:hover,
	.zig-archive__mbar .zig-archive__mbar-btn:focus,
	.zig-archive__mbar .zig-archive__mbar-btn:active {
		background: none;
		background-color: transparent;
		text-shadow: none;
		text-decoration: none;
	}');
Tests::keeps('برچسبِ متنیِ داخلِ دکمه هم تکس‌شادو ندارد', $css_src, '.zig-archive__mbar .zig-archive__mbar-text {
		text-shadow: none;
	}');
Tests::keeps('رنگِ برچسبِ فیلتر رویِ هاور/فوکوس/اکتیو هم صریح تکرار شده', $css_src, '.zig-archive__mbar .zig-archive__mbar-btn--filter:hover,
	.zig-archive__mbar .zig-archive__mbar-btn--filter:focus,
	.zig-archive__mbar .zig-archive__mbar-btn--filter:active {
		color: var(--zig-mbar-filter-color, #5a23b5);
	}');
Tests::keeps('رنگِ برچسبِ ترتیب رویِ هاور/فوکوس/اکتیو هم صریح تکرار شده', $css_src, '.zig-archive__mbar .zig-archive__mbar-btn--sort:hover,
	.zig-archive__mbar .zig-archive__mbar-btn--sort:focus,
	.zig-archive__mbar .zig-archive__mbar-btn--sort:active {
		color: var(--zig-mbar-sort-color, #686673);
	}');
Tests::keeps('دکمه‌هایِ نوار حلقهٔ فوکوسِ کیبورد دارند', $css_src, '.zig-archive__mbar-btn:focus-visible {');
