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
