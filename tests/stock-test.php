<?php
/**
 * تشخیص وضعیت موجودی.
 *
 * فضای حالت‌های ووکامرس از آنچه به نظر می‌رسد بزرگ‌تر است: سه وضعیت
 * ذخیره‌شده، ضربدر «مدیریت موجودی روشن یا خاموش»، ضربدر «تعداد ثبت شده یا
 * نه»، ضربدر «پیش‌خرید مجاز یا نه». هر ترکیب اینجا جداگانه سنجیده می‌شود،
 * چون هیچ‌کدام خطای PHP نمی‌دهند — فقط وضعیت غلط روی صفحه می‌نشیند.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/woocommerce-stub.php';
require_once dirname(__DIR__) . '/includes/stock.php';

use Zig3d_Widgets\Stock;

/** یک محصول با وضعیت موجودی مشخص */
function zig_stock_product(array $props): WC_Product {
    return new WC_Product($props + ['id' => ++$GLOBALS['__zig_seq']]);
}

$GLOBALS['__zig_seq'] = 5000;

/** فقط نام حالت، برای خوانایی سنجه‌ها */
function zig_state(WC_Product $product, array $options = []): string {
    return Stock::state($product, $options)['state'];
}

/* ==========================================================================
 * حالت‌های اصلی
 * ======================================================================= */

Tests::group('موجودی › تعداد ثبت‌شده');

zig_reset_products();

Tests::same(
    'تعداد ۵ یعنی موجود در انبار',
    zig_state(zig_stock_product(['status' => 'instock', 'managing' => true, 'qty' => 5])),
    Stock::IN_STOCK
);

Tests::same(
    'تعداد ۱ هم موجود در انبار است',
    zig_state(zig_stock_product(['status' => 'instock', 'managing' => true, 'qty' => 1])),
    Stock::IN_STOCK
);

/*
 * تعداد صفر ولی وضعیت هنوز «موجود» — یعنی افزونه‌ای وضعیت را همگام نکرده.
 * گفتنِ «موجود در انبار» دربارهٔ چیزی که صفر عدد در انبار دارد دروغ است، پس
 * به «موجود» تنزل می‌کند نه بالاتر.
 */
Tests::same(
    'تعداد صفر، «در انبار» نمی‌شود',
    zig_state(zig_stock_product(['status' => 'instock', 'managing' => true, 'qty' => 0])),
    Stock::AVAILABLE
);

Tests::same(
    'تعداد منفی هم همین‌طور',
    zig_state(zig_stock_product(['status' => 'instock', 'managing' => true, 'qty' => -3])),
    Stock::AVAILABLE
);

Tests::group('موجودی › بدون شمارش');

/*
 * این حالت در صورت‌مسئله نبود ولی وجودش اجباری است: فروشگاهی که «مدیریت
 * موجودی» را روشن نکرده هیچ عددی ندارد. بدون این حالت، چنین محصولی نه در
 * «تعداد ≥ ۱» می‌گنجید و نه ناموجود بود — یعنی هیچ وضعیتی نمی‌گرفت.
 */
Tests::same(
    'موجود بدون مدیریت موجودی',
    zig_state(zig_stock_product(['status' => 'instock', 'managing' => false, 'qty' => null])),
    Stock::AVAILABLE
);

Tests::same(
    'مدیریت روشن ولی تعداد ثبت‌نشده',
    zig_state(zig_stock_product(['status' => 'instock', 'managing' => true, 'qty' => null])),
    Stock::AVAILABLE
);

Tests::group('موجودی › ناموجود');

Tests::same(
    'وضعیت ناموجود',
    zig_state(zig_stock_product(['status' => 'outofstock', 'in_stock' => false])),
    Stock::OUT_OF_STOCK
);

/*
 * هر دو نشانه بررسی می‌شوند چون افزونه‌های موجودی گاهی یکی را دست می‌زنند و
 * دیگری را نه. اگر فقط به وضعیت ذخیره‌شده تکیه می‌کردیم، محصولی که واقعاً
 * قابل خرید نیست «موجود» اعلام می‌شد.
 */
Tests::same(
    'وضعیت instock ولی is_in_stock منفی، باز هم ناموجود',
    zig_state(zig_stock_product(['status' => 'instock', 'in_stock' => false, 'managing' => true, 'qty' => 9])),
    Stock::OUT_OF_STOCK
);

Tests::same(
    'ناموجود بر تعداد مثبت مقدم است',
    zig_state(zig_stock_product(['status' => 'outofstock', 'in_stock' => false, 'managing' => true, 'qty' => 4])),
    Stock::OUT_OF_STOCK
);

/* ==========================================================================
 * پیش‌خرید
 * ======================================================================= */

Tests::group('موجودی › پیش‌خرید');

/*
 * ‎is_in_stock()‎ برای محصول در پیش‌خرید true برمی‌گرداند. یعنی هر کدی که
 * فقط از آن استفاده کند، محصولی را که در انبار نیست «موجود» اعلام می‌کند.
 */
$manual = zig_stock_product(['status' => 'onbackorder', 'in_stock' => true, 'managing' => false]);

Tests::same('وضعیت دستیِ پیش‌خرید', zig_state($manual), Stock::BACKORDER);

// مسیر دوم: موجودی مدیریت می‌شود، به صفر رسیده و پیش‌خرید مجاز است
$auto = zig_stock_product([
    'status' => 'instock', 'in_stock' => true, 'managing' => true, 'qty' => 0, 'backorder' => true,
]);

Tests::same('پیش‌خریدِ محاسبه‌شده از تعداد', zig_state($auto), Stock::BACKORDER);

Tests::same(
    'پیش‌خرید بر تعداد صفر مقدم است',
    zig_state($auto, ['backorder' => true]),
    Stock::BACKORDER
);

/*
 * وقتی کاربر این حالت را نمی‌خواهد، محصول به «موجود» برمی‌گردد نه «موجود
 * در انبار» — چون تعدادش صفر است.
 */
Tests::same(
    'با خاموش بودن حالت، به «موجود» برمی‌گردد',
    zig_state($manual, ['backorder' => false]),
    Stock::AVAILABLE
);

Tests::same(
    'و «موجود در انبار» نمی‌شود',
    zig_state($auto, ['backorder' => false]),
    Stock::AVAILABLE
);

// ناموجودی همچنان بر پیش‌خرید مقدم است
Tests::same(
    'ناموجود بر پیش‌خرید مقدم است',
    zig_state(zig_stock_product(['status' => 'onbackorder', 'in_stock' => false])),
    Stock::OUT_OF_STOCK
);

/* ==========================================================================
 * رو به اتمام
 * ======================================================================= */

Tests::group('موجودی › رو به اتمام');

$low = zig_stock_product(['status' => 'instock', 'managing' => true, 'qty' => 2, 'low' => 3]);

Tests::same('خاموش، پس موجود در انبار', zig_state($low), Stock::IN_STOCK);
Tests::same('روشن، پس رو به اتمام', zig_state($low, ['lowstock' => true]), Stock::LOW_STOCK);

Tests::same(
    'مساوی آستانه هم رو به اتمام است',
    zig_state(
        zig_stock_product(['status' => 'instock', 'managing' => true, 'qty' => 3, 'low' => 3]),
        ['lowstock' => true]
    ),
    Stock::LOW_STOCK
);

Tests::same(
    'بالاتر از آستانه، موجود در انبار',
    zig_state(
        zig_stock_product(['status' => 'instock', 'managing' => true, 'qty' => 4, 'low' => 3]),
        ['lowstock' => true]
    ),
    Stock::IN_STOCK
);

// آستانهٔ دلخواه کاربر بر آستانهٔ خودِ محصول می‌چربد
Tests::same(
    'آستانهٔ دلخواه مقدم است',
    zig_state(
        zig_stock_product(['status' => 'instock', 'managing' => true, 'qty' => 8, 'low' => 3]),
        ['lowstock' => true, 'low_threshold' => 10]
    ),
    Stock::LOW_STOCK
);

// و آستانهٔ خودِ محصول بر تنظیم سراسری
$GLOBALS['__zig_options']['woocommerce_notify_low_stock_amount'] = 20;

Tests::same(
    'آستانهٔ محصول بر تنظیم سراسری مقدم است',
    zig_state(
        zig_stock_product(['status' => 'instock', 'managing' => true, 'qty' => 10, 'low' => 3]),
        ['lowstock' => true]
    ),
    Stock::IN_STOCK
);

Tests::same(
    'نبودِ آستانهٔ محصول، تنظیم سراسری',
    zig_state(
        zig_stock_product(['status' => 'instock', 'managing' => true, 'qty' => 10, 'low' => '']),
        ['lowstock' => true]
    ),
    Stock::LOW_STOCK
);

unset($GLOBALS['__zig_options']);

/* ==========================================================================
 * تعداد و جمع‌زدن گزینه‌ها
 * ======================================================================= */

Tests::group('موجودی › تعداد');

zig_reset_products();

$counted = new WC_Product(['id' => 6000, 'status' => 'instock', 'managing' => true, 'qty' => 7]);

Tests::same('تعداد برگردانده می‌شود', Stock::state($counted)['quantity'], 7);
Tests::same('و علامت مدیریت موجودی', Stock::state($counted)['managed'], true);

$uncounted = new WC_Product(['id' => 6001, 'status' => 'instock', 'managing' => false]);

Tests::same('بدون مدیریت، تعداد null است', Stock::state($uncounted)['quantity'], null);

Tests::group('موجودی › جمع‌زدن گزینه‌ها');

zig_reset_products();

new WC_Product(['id' => 6101, 'managing' => true, 'qty' => 3]);
new WC_Product(['id' => 6102, 'managing' => true, 'qty' => 4]);
new WC_Product(['id' => 6103, 'managing' => false, 'qty' => null]);

$variable = new WC_Product([
    'id' => 6100, 'type' => 'variable', 'status' => 'instock',
    'managing' => false, 'children' => [6101, 6102, 6103],
]);

Tests::same('خاموش، تعدادی جمع نمی‌شود', Stock::state($variable)['quantity'], null);

$aggregated = Stock::state($variable, ['aggregate' => true]);

Tests::same('روشن، موجودی گزینه‌ها جمع می‌شود', $aggregated['quantity'], 7);
Tests::same('و وضعیت هم بر همان مبنا تعیین می‌شود', $aggregated['state'], Stock::IN_STOCK);

// سقف: محصول با گزینه‌های زیاد نباید ده‌ها بار از دیتابیس بخواند
zig_reset_products();

$many = [];

for ($i = 1; $i <= 70; $i++) {
    $id     = 7000 + $i;
    $many[] = $id;
    new WC_Product(['id' => $id, 'managing' => true, 'qty' => 1]);
}

$huge = new WC_Product([
    'id' => 6999, 'type' => 'variable', 'status' => 'instock', 'managing' => false, 'children' => $many,
]);

Tests::same('از سقف که بگذرد، جمع نمی‌زند', Stock::state($huge, ['aggregate' => true])['quantity'], null);

/* ==========================================================================
 * نشانه‌گذاری ساختاریافته
 * ======================================================================= */

Tests::group('موجودی › schema.org');

zig_reset_products();

$map = [
    Stock::IN_STOCK     => 'InStock',
    Stock::AVAILABLE    => 'InStock',
    Stock::LOW_STOCK    => 'LimitedAvailability',
    Stock::BACKORDER    => 'BackOrder',
    Stock::OUT_OF_STOCK => 'OutOfStock',
];

$samples = [
    Stock::IN_STOCK     => [['status' => 'instock', 'managing' => true, 'qty' => 5], []],
    Stock::AVAILABLE    => [['status' => 'instock', 'managing' => false], []],
    Stock::LOW_STOCK    => [['status' => 'instock', 'managing' => true, 'qty' => 1, 'low' => 3], ['lowstock' => true]],
    Stock::BACKORDER    => [['status' => 'onbackorder', 'in_stock' => true], []],
    Stock::OUT_OF_STOCK => [['status' => 'outofstock', 'in_stock' => false], []],
];

foreach ($samples as $expected => [$props, $options]) {
    $result = Stock::state(zig_stock_product($props), $options);

    Tests::same('حالت ' . $expected, $result['state'], $expected);
    Tests::keeps('نشانی schema برای ' . $expected, $result['schema'], $map[$expected]);
}

/*
 * هر پنج حالت باید نگاشت داشته باشند. اگر روزی حالت تازه‌ای اضافه شود و
 * نگاشتش جا بماند، خروجی به‌جای خطا یک نشانیِ اشتباه می‌دهد.
 */
Tests::same('همهٔ حالت‌ها پوشش داده شده‌اند', count(Stock::STATES), count($map));
