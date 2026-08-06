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

$widgets = [
    'کارت ویژگی'    => \Zig3d_Widgets\Widgets\Feature_Card::class,
    'لیست عنوان‌ها' => \Zig3d_Widgets\Widgets\Bullet_List::class,
    'دکمه'          => \Zig3d_Widgets\Widgets\Button::class,
    'قیمت محصول'    => \Zig3d_Widgets\Widgets\Product_Price::class,
    'وضعیت موجودی'  => \Zig3d_Widgets\Widgets\Product_Stock::class,
];

foreach ($widgets as $label => $class) {
    Tests::group('کنترل‌ها › ' . $label);

    $entries = zig_collect_controls($class);

    Tests::ok('کنترلی ثبت شده', count($entries) > 20, sprintf('تعداد: %d', count($entries)));

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
