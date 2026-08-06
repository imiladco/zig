<?php
/**
 * دامنهٔ سلکتورهای تولیدشده.
 *
 * هر سلکتوری که یک کنترل المنتور تولید می‌کند باید به ‎{{WRAPPER}}‎ مقید
 * باشد. اگر نباشد، قاعده به کل صفحه نشت می‌کند: هر نمونهٔ دیگری از همان
 * ویجت (و حتی ویجت‌های دیگر) همان قاعده را می‌گیرند و در عمل، آخرین
 * نمونه‌ای که المنتور CSS‌اش را چاپ کرده برندهٔ همه می‌شود.
 *
 * چرا این سنجه لازم بود: این دقیقاً یک بار اتفاق افتاد. سلکتوری با دو بخش
 * نوشته شده بود و ‎{{WRAPPER}}‎ فقط به بخش اول چسبیده بود —
 *
 *     {{WRAPPER}} .zig-btn:active .zig-btn__icon svg, .zig-btn__icon svg *
 *                                                     ^^^^^^^^^^^^^^^^^^^^ سراسری
 *
 * خروجی رندر کاملاً درست بود و هیچ تستی چیزی نمی‌دید؛ فقط رنگ آیکونِ همهٔ
 * دکمه‌های صفحه به هم ریخته بود. تنها راه گرفتنش، نگاه‌کردن به خودِ
 * سلکتورهاست.
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
require_once $root . '/includes/widgets/traits/icon.php';
require_once $root . '/includes/widgets/traits/box.php';
require_once $root . '/includes/widgets/traits/pulse.php';
require_once $root . '/includes/widgets/feature-card.php';
require_once $root . '/includes/widgets/bullet-list.php';
require_once $root . '/includes/widgets/button.php';
require_once $root . '/includes/widgets/product-price.php';
require_once $root . '/includes/widgets/product-stock.php';

use Zig3d_Widgets\Selector;

$widgets = [
    'کارت ویژگی'    => \Zig3d_Widgets\Widgets\Feature_Card::class,
    'لیست عنوان‌ها' => \Zig3d_Widgets\Widgets\Bullet_List::class,
    'دکمه'          => \Zig3d_Widgets\Widgets\Button::class,
    'قیمت محصول'    => \Zig3d_Widgets\Widgets\Product_Price::class,
    'وضعیت موجودی'  => \Zig3d_Widgets\Widgets\Product_Stock::class,
];

foreach ($widgets as $label => $class) {
    Tests::group('سلکتورها › ' . $label);

    $selectors = zig_collect_selectors($class);

    Tests::ok('سلکتوری ثبت شده', count($selectors) > 10, sprintf('تعداد: %d', count($selectors)));

    $leaked = [];

    foreach ($selectors as [$control, $selector]) {
        foreach (explode(',', $selector) as $part) {
            $part = trim($part);

            if ('' === $part) {
                continue;
            }

            if (false === strpos($part, '{{WRAPPER}}')) {
                $leaked[] = $control . ' → ' . $part;
            }
        }
    }

    Tests::ok(
        'همهٔ بخش‌های سلکتور به ویجت مقیدند',
        [] === $leaked,
        implode(' | ', array_unique($leaked))
    );

    /*
     * بخش تکراری هم نشانهٔ خطاست: یعنی جایی سلکتور با الحاق رشته ساخته شده
     * و یک بخش دو بار آمده. بی‌ضرر است ولی تقریباً همیشه یعنی ترکیب اشتباه
     * انجام شده.
     */
    $duplicated = [];

    foreach ($selectors as [$control, $selector]) {
        $parts = array_map('trim', explode(',', $selector));

        if (count($parts) !== count(array_unique($parts))) {
            $duplicated[] = $control;
        }
    }

    Tests::ok(
        'هیچ سلکتوری بخش تکراری ندارد',
        [] === $duplicated,
        implode('، ', array_unique($duplicated))
    );
}

/* ==========================================================================
 * خودِ ابزار ترکیب
 * ======================================================================= */

Tests::group('سلکتور › ترکیب');

Tests::same(
    'والد و فرزندِ تک‌بخشی',
    Selector::descend('{{WRAPPER}} .a', '.b'),
    '{{WRAPPER}} .a .b'
);

Tests::same(
    'والدِ چندبخشی',
    Selector::descend('{{WRAPPER}} .a, {{WRAPPER}} .b', '.c'),
    '{{WRAPPER}} .a .c, {{WRAPPER}} .b .c'
);

/*
 * همان حالتی که باگ را ساخت: فرزند خودش کاما داشت و پیاده‌سازی قبلی فقط
 * والد را می‌شکست، پس بخش دومِ فرزند بدون هیچ والدی رها می‌شد.
 */
Tests::same(
    'فرزندِ چندبخشی هم شکسته می‌شود',
    Selector::descend('{{WRAPPER}} .a', '.b, .b *'),
    '{{WRAPPER}} .a .b, {{WRAPPER}} .a .b *'
);

Tests::same(
    'هر دو طرف چندبخشی، ضرب دکارتی',
    Selector::descend('{{W}} .a, {{W}} .b', '.c, .d'),
    '{{W}} .a .c, {{W}} .a .d, {{W}} .b .c, {{W}} .b .d'
);

Tests::same(
    'فاصله‌های اضافه پاک می‌شوند',
    Selector::descend('  {{W}} .a  ,  {{W}} .b ', '  .c  '),
    '{{W}} .a .c, {{W}} .b .c'
);

Tests::same('فرزند خالی، والد را دست‌نخورده برمی‌گرداند', Selector::descend('{{W}} .a', '  '), '{{W}} .a');
Tests::same('والد خالی، رشتهٔ خالی', Selector::descend('  ', '.b'), '');
