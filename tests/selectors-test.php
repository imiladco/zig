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

use Zig3d_Widgets\Selector;

$widgets = [
    'کارت ویژگی'    => \Zig3d_Widgets\Widgets\Feature_Card::class,
    'لیست عنوان‌ها' => \Zig3d_Widgets\Widgets\Bullet_List::class,
    'دکمه'          => \Zig3d_Widgets\Widgets\Button::class,
    'قیمت محصول'    => \Zig3d_Widgets\Widgets\Product_Price::class,
    'وضعیت موجودی'  => \Zig3d_Widgets\Widgets\Product_Stock::class,
    'آرشیو محصولات' => \Zig3d_Widgets\Widgets\Product_Archive::class,
];

foreach ($widgets as $label => $class) {
    Tests::group('سلکتورها › ' . $label);

    $selectors = zig_collect_selectors($class);

    Tests::ok('سلکتوری ثبت شده', count($selectors) > 10, sprintf('تعداد: %d', count($selectors)));

    $leaked = [];

    foreach ($selectors as [$control, $selector, $rule]) {
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

    foreach ($selectors as [$control, $selector, $rule]) {
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

/* ==========================================================================
 * سیم‌کشیِ کنترل‌های تایپوگرافیک قیمت
 * ======================================================================= */

/*
 * این‌ها فقط CSS تولید می‌کنند و هیچ اثری در خروجی رندر ندارند، پس تنها
 * راه سنجیدنشان همین است: کدام کنترل، کدام ویژگی را، روی کدام عنصر
 * می‌نویسد. اشتباه در هر سه، بی‌سروصدا بی‌اثر می‌ماند.
 */

Tests::group('سلکتور › ارقام و فونت قیمت');

$price_rules = [];

foreach (zig_collect_selectors(\Zig3d_Widgets\Widgets\Product_Price::class) as [$control, $selector, $rule]) {
    $price_rules[$control][] = $selector . ' ⇒ ' . $rule;
}

Tests::ok(
    'نوع ارقام روی ریشهٔ قیمت می‌نشیند',
    isset($price_rules['figures'][0])
        && false !== strpos($price_rules['figures'][0], '{{WRAPPER}} .zig-price ⇒ font-variant-numeric'),
    $price_rules['figures'][0] ?? 'ثبت نشده'
);

/*
 * روی ریشه و نه روی ‎.zig-price__amount‎: عدد داخل بج تخفیف span جدا ندارد،
 * پس اگر روی amount می‌نشست، دو عدد در یک ردیف دو جور رندر می‌شدند.
 */
Tests::ok(
    'و نه فقط روی عدد، تا بج هم یکدست بماند',
    isset($price_rules['figures'][0]) && false === strpos($price_rules['figures'][0], '__amount'),
    $price_rules['figures'][0] ?? ''
);

Tests::ok(
    'ست سبکی روی واحد پول می‌نشیند',
    isset($price_rules['unit_feature'][0])
        && false !== strpos($price_rules['unit_feature'][0], '{{WRAPPER}} .zig-price__unit ⇒ font-feature-settings'),
    $price_rules['unit_feature'][0] ?? 'ثبت نشده'
);

// مقدار باید داخل گیومه برود، وگرنه font-feature-settings آن را نمی‌پذیرد
Tests::ok(
    'شمارهٔ ست داخل گیومه قرار می‌گیرد',
    isset($price_rules['unit_feature'][0])
        && false !== strpos($price_rules['unit_feature'][0], '"{{VALUE}}"'),
    $price_rules['unit_feature'][0] ?? ''
);

/*
 * فیلد دلخواه باید دیرتر از دراپ‌داون ثبت شود؛ ترتیبِ ثبت همان ترتیب تولید
 * CSS است و تنها چیزی است که «دلخواه بر انتخاب می‌چربد» را تضمین می‌کند.
 */
$order  = array_keys($price_rules);
$select = array_search('unit_feature', $order, true);
$custom = array_search('unit_feature_custom', $order, true);

Tests::ok(
    'فیلد دلخواه بعد از دراپ‌داون ثبت می‌شود',
    false !== $select && false !== $custom && $custom > $select,
    sprintf('دراپ‌داون %s، دلخواه %s', var_export($select, true), var_export($custom, true))
);

Tests::ok(
    'و بدون گیومه، چون کاربر خودش می‌نویسد',
    isset($price_rules['unit_feature_custom'][0])
        && false === strpos($price_rules['unit_feature_custom'][0], '"{{VALUE}}"'),
    $price_rules['unit_feature_custom'][0] ?? ''
);

Tests::ok(
    'جابه‌جایی عمودی هم روی واحد است',
    isset($price_rules['unit_offset'][0])
        && false !== strpos($price_rules['unit_offset'][0], '.zig-price__unit ⇒ transform'),
    $price_rules['unit_offset'][0] ?? 'ثبت نشده'
);

/* ==========================================================================
 * ایمپورت‌ها
 *
 * این را یک خطای مرگ‌بار روی نصب واقعی یاد داد: ‎Archive_Head::page_state()‎
 * به ویجت اضافه شده بود بدون ‎use‎ متناظرش. ویجت در فضای‌نام
 * ‎Zig3d_Widgets\Widgets‎ است، پس PHP دنبال
 * ‎Zig3d_Widgets\Widgets\Archive_Head‎ می‌گشت و پیدا نمی‌کرد.
 *
 * هیچ تستی نمی‌گرفتش چون رندر ویجت به المنتور و ووکامرس نیاز دارد و در
 * تست واحد اجرا نمی‌شود. ولی *خودِ فایل* را می‌شود خواند — و همین کافی
 * است.
 * ======================================================================= */

Tests::group('ایمپورت‌ها');

foreach (glob(dirname(__DIR__) . '/includes/widgets/*.php') as $file) {
    $source = (string) file_get_contents($file);
    $name   = basename($file);

    if (!preg_match('/^namespace\s+([^;]+);/m', $source, $ns) || 'Zig3d_Widgets\\Widgets' !== trim($ns[1])) {
        continue;
    }

    preg_match_all('/^use\s+Zig3d_Widgets\\\\([A-Za-z_]+);/m', $source, $imports);

    $known = array_flip($imports[1]);

    // کلاس‌های فضای‌نام ریشه، از روی فایل‌های واقعی
    foreach (glob(dirname(__DIR__) . '/includes/*.php') as $sibling) {
        $class = str_replace(' ', '_', ucwords(str_replace('-', ' ', basename($sibling, '.php'))));

        if (!preg_match('/\b' . preg_quote($class, '/') . '::/', $source)) {
            continue;
        }

        Tests::ok(
            $name . ' › ' . $class . ' ایمپورت شده',
            isset($known[$class])
        );
    }
}
