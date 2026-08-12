<?php
/**
 * سلامتِ فهرست کنترل‌های المنتور.
 *
 * این‌ها خطاهایی را می‌گیرند که PHP هیچ‌وقت گزارش نمی‌کند و فقط در پنل
 * المنتور — آن هم به‌صورت «یک بخش عجیب غیب شده» — دیده می‌شوند:
 *
 *   • نام تکراری کنترل: ثبت دوم اولی را بی‌صدا کنار می‌زند، پس یکی از دو
 *     تنظیم برای همیشه بی‌اثر می‌ماند.
 *   • سکشن یا تبِ بسته‌نشده: بقیهٔ کنترل‌ها داخل آن گم می‌شوند.
 *   • تبِ تودرتو: المنتور از آن پشتیبانی نمی‌کند و رندر پنل می‌شکند.
 *   • نام تکراری گروه‌کنترل: مثل نام تکراری کنترل، فقط دیرتر معلوم می‌شود.
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
require_once $root . '/includes/widgets/traits/link.php';
require_once $root . '/includes/widgets/traits/icon.php';
require_once $root . '/includes/widgets/traits/box.php';
require_once $root . '/includes/widgets/traits/pulse.php';
require_once $root . '/includes/widgets/feature-card.php';
require_once $root . '/includes/widgets/bullet-list.php';
require_once $root . '/includes/widgets/button.php';
require_once $root . '/includes/widgets/product-price.php';
require_once $root . '/includes/widgets/product-stock.php';
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
require_once $root . '/includes/widgets/product-gallery.php';

$widgets = [
    'کارت ویژگی'    => \Zig3d_Widgets\Widgets\Feature_Card::class,
    'لیست عنوان‌ها' => \Zig3d_Widgets\Widgets\Bullet_List::class,
    'دکمه'          => \Zig3d_Widgets\Widgets\Button::class,
    'قیمت محصول'    => \Zig3d_Widgets\Widgets\Product_Price::class,
    'وضعیت موجودی'  => \Zig3d_Widgets\Widgets\Product_Stock::class,
    'آرشیو محصولات' => \Zig3d_Widgets\Widgets\Product_Archive::class,
    'گالری محصول'   => \Zig3d_Widgets\Widgets\Product_Gallery::class,
];

foreach ($widgets as $label => $class) {
    Tests::group('کنترل‌ها › ' . $label);

    $entries = zig_collect_controls($class);

    /*
     * گالری محصول موقتاً استثناست: پورتِ مستقیمِ شورت‌کدِ الماس‌آرا است و
     * عمداً هیچ کنترلِ استایلی ندارد — طراحی در دورِ اصلاحاتِ بعدی به آن
     * اضافه می‌شود. بقیهٔ ویجت‌ها همان آستانهٔ قبلی را دارند.
     */
    $min = 'گالری محصول' === $label ? 1 : 20;

    Tests::ok('کنترلی ثبت شده', count($entries) > $min, sprintf('تعداد: %d', count($entries)));

    /* ---------------------- نام‌های تکراری ---------------------- */

    $names = array_filter(
        $entries,
        static fn(string $entry): bool => !preg_match('#^(/|SECTION:|TABS:|TAB:)#', $entry)
    );

    $duplicates = array_keys(array_filter(array_count_values($names), static fn(int $n): bool => $n > 1));

    Tests::ok(
        'هیچ نام تکراری‌ای نیست',
        [] === $duplicates,
        implode('، ', $duplicates)
    );

    /* ---------------------- تعادل سکشن و تب ---------------------- */

    $section = 0;
    $tabs    = 0;
    $tab     = 0;
    $nested  = false;
    $orphan  = false;

    foreach ($entries as $entry) {
        if (0 === strpos($entry, 'SECTION:')) {
            ++$section;
            continue;
        }
        if ('/SECTION' === $entry) {
            --$section;
            continue;
        }
        if (0 === strpos($entry, 'TABS:')) {
            // بازکردن گروه تب در حالی که یکی باز است، یعنی تودرتو
            if ($tabs > 0) {
                $nested = true;
            }
            ++$tabs;
            continue;
        }
        if ('/TABS' === $entry) {
            --$tabs;
            continue;
        }
        if (0 === strpos($entry, 'TAB:')) {
            if (0 === $tabs) {
                $orphan = true;
            }
            ++$tab;
            continue;
        }
        if ('/TAB' === $entry) {
            --$tab;
        }
    }

    Tests::same('همهٔ سکشن‌ها بسته شده‌اند', $section, 0);
    Tests::same('همهٔ گروه‌های تب بسته شده‌اند', $tabs, 0);
    Tests::same('همهٔ تب‌ها بسته شده‌اند', $tab, 0);
    Tests::ok('هیچ تبِ تودرتویی نیست', !$nested);
    Tests::ok('هیچ تبی بیرون از گروه نیست', !$orphan);

    /* ---------------------- کنترل بیرون از سکشن ---------------------- */

    /*
     * کنترلی که بیرون از هر سکشن ثبت شود در پنل اصلاً دیده نمی‌شود. چون
     * خطایی هم نمی‌دهد، تنها راهِ گرفتنش همین است.
     */
    $depth   = 0;
    $outside = [];

    foreach ($entries as $entry) {
        if (0 === strpos($entry, 'SECTION:')) {
            ++$depth;
            continue;
        }
        if ('/SECTION' === $entry) {
            --$depth;
            continue;
        }
        if (0 === $depth && !preg_match('#^(/|TABS:|TAB:)#', $entry)) {
            $outside[] = $entry;
        }
    }

    Tests::ok('هیچ کنترلی بیرون از سکشن نیست', [] === $outside, implode('، ', $outside));
}

/* ==========================================================================
 * فهرست ویژگی‌ها، با دو منبع مستقل
 *
 * این روی استیج پیدا شد و از آن دسته باگ‌هایی است که هیچ خطایی نمی‌دهند:
 * ‎wc_get_attribute_taxonomy_names()‎ فهرست را از ترنزینت ووکامرس می‌خواند و
 * روی سایتی با کش شیء می‌تواند در یک درخواست خالی برگردد. چون همین فهرست
 * مبنای «کدام ‎filter_*‎ مجاز است» بود، صفحهٔ سالم ‎invalid‎ می‌گرفت — یعنی
 * ‎404‎ و ‎noindex‎.
 *
 * نشانه‌اش هم همین بود: یک آدرسِ یکسان، دو درخواست پشت سر هم، یکی
 * ‎invalid‎ و دیگری ‎ok‎.
 * ======================================================================= */

Tests::group('ویژگی‌ها › دو منبع');

$GLOBALS['__zig_attr_names'] = ['pa_brand', 'pa_axis'];
$GLOBALS['__zig_registered_taxonomies'] = ['product_cat', 'pa_brand', 'pa_axis'];

Tests::same(
    'در حالت عادی، همان فهرست ووکامرس',
    \Zig3d_Widgets\Attributes::all(),
    ['pa_brand', 'pa_axis']
);

/*
 * لحظهٔ باگ: ترنزینت سرد است و ووکامرس هیچ نمی‌دهد. تاکسونومی‌های
 * ثبت‌شده یک بار روی ‎init‎ نشسته‌اند و در حافظهٔ همین درخواست‌اند، پس
 * کشِ سرد نمی‌تواند خالی‌شان کند.
 */
$GLOBALS['__zig_attr_names'] = [];

Tests::same(
    'کشِ سردِ ووکامرس، فهرست را خالی نمی‌کند',
    \Zig3d_Widgets\Attributes::all(),
    ['pa_brand', 'pa_axis']
);

/*
 * و «هر تاکسونومی» را هم قبول نمی‌کند: پیشوند ‎pa_‎ را خودِ ووکامرس رزرو
 * کرده، پس همان مرزِ قبلی سرِ جایش می‌ماند.
 */
Tests::blocks(
    'تاکسونومیِ غیرویژگی وارد فهرست نمی‌شود',
    implode('|', \Zig3d_Widgets\Attributes::all()),
    'product_cat'
);

$GLOBALS['__zig_attr_names'] = [];
$GLOBALS['__zig_registered_taxonomies'] = [];
