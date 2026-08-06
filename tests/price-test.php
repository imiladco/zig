<?php
/**
 * منطق قیمت.
 *
 * پرریسک‌ترین بخش افزونه. عدد غلط یعنی مشتری چیزی می‌بیند که نمی‌تواند
 * بخرد، یا فروشگاه چیزی می‌فروشد که نمی‌خواسته — و هیچ‌کدام خطای PHP
 * نمی‌دهند. هر شاخهٔ تصمیم اینجا جداگانه سنجیده می‌شود.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/woocommerce-stub.php';
require_once dirname(__DIR__) . '/includes/price.php';

use Zig3d_Widgets\Price;

/* ==========================================================================
 * محصول ساده
 * ======================================================================= */

Tests::group('قیمت › محصول ساده');

zig_reset_products();

$plain = new WC_Product(['id' => 1, 'price' => '1000', 'regular' => '1000']);
$data  = Price::data($plain);

Tests::same('قیمت خوانده می‌شود', $data['current'], '1000');
Tests::same('بدون تخفیف است', $data['on_sale'], false);
Tests::same('قیمت دارد', $data['has_price'], true);
Tests::same('چندقیمتی نیست', $data['is_multi'], false);

$sale = new WC_Product([
    'id' => 2, 'price' => '800', 'regular' => '1000', 'sale' => '800', 'on_sale' => true,
]);
$data = Price::data($sale);

Tests::same('قیمت فعلی، قیمت تخفیف‌خورده است', $data['current'], '800');
Tests::same('قیمت پیشین، قیمت عادی است', $data['old'], '1000');
Tests::same('تخفیف تشخیص داده می‌شود', $data['on_sale'], true);
Tests::same('درصد تخفیف درست است', $data['percent'], 20);
Tests::same('مبلغ صرفه‌جویی درست است', $data['saved'], '200');

/*
 * محصولی که ‎_regular_price‎ ندارد نادر است ولی وجود دارد (ایمپورت ناقص،
 * افزونهٔ قیمت‌گذاری). بدون این شاخه، قیمتش خالی رندر می‌شد.
 */
$only_price = new WC_Product(['id' => 3, 'price' => '500', 'regular' => '']);

Tests::same('نبودِ قیمت عادی، از ‎_price‎ جبران می‌شود', Price::data($only_price)['current'], '500');

Tests::group('قیمت › حالت‌های مرزی محصول ساده');

$no_price = new WC_Product(['id' => 4, 'price' => '', 'regular' => '']);
$data     = Price::data($no_price);

Tests::same('محصول بی‌قیمت، قیمت ندارد', $data['has_price'], false);
Tests::same('و رایگان هم حساب نمی‌شود', $data['is_free'], false);

/*
 * «صفر» با «خالی» یکی نیست: اولی یعنی رایگان و باید نمایش داده شود، دومی
 * یعنی هنوز قیمت‌گذاری نشده و باید پنهان شود یا «تماس بگیرید» بدهد.
 */
$free = new WC_Product(['id' => 5, 'price' => '0', 'regular' => '0']);
$data = Price::data($free);

Tests::same('قیمت صفر، قیمت محسوب می‌شود', $data['has_price'], true);
Tests::same('و رایگان علامت می‌خورد', $data['is_free'], true);

/*
 * قیمت تخفیفِ بزرگ‌تر یا مساویِ قیمت عادی، تخفیف نیست. بدون این بررسی،
 * درصدِ صفر یا منفی روی بج چاپ می‌شد.
 */
$fake_sale = new WC_Product([
    'id' => 6, 'price' => '1000', 'regular' => '1000', 'sale' => '1000', 'on_sale' => true,
]);
$data = Price::data($fake_sale);

Tests::same('تخفیف صفر، تخفیف نیست', $data['on_sale'], false);
Tests::same('و قیمت پیشین پاک می‌شود', $data['old'], '');

$negative = new WC_Product([
    'id' => 7, 'price' => '1200', 'regular' => '1000', 'sale' => '1200', 'on_sale' => true,
]);

Tests::same('قیمت «تخفیف» گران‌تر، تخفیف نیست', Price::data($negative)['on_sale'], false);

/* ==========================================================================
 * مالیات
 * ======================================================================= */

Tests::group('قیمت › مالیات');

zig_reset_products();
$GLOBALS['__zig_tax'] = 1.09;

$taxed = new WC_Product(['id' => 10, 'price' => '1000', 'regular' => '1000']);

/*
 * ‎get_price()‎ عددِ ذخیره‌شده را می‌دهد و از تنظیمات مالیاتی بی‌خبر است.
 * اگر ویجت همان را چاپ کند، قیمت صفحهٔ محصول با قیمت سبد خرید نمی‌خواند.
 */
Tests::same('قیمت نمایشی به کار می‌رود نه خام', Price::data($taxed)['current'], '1090');

$taxed_sale = new WC_Product([
    'id' => 11, 'price' => '800', 'regular' => '1000', 'sale' => '800', 'on_sale' => true,
]);
$data = Price::data($taxed_sale);

Tests::same('قیمت پیشین هم مالیات می‌گیرد', $data['old'], '1090');
Tests::same('درصد تخفیف با مالیات هم درست می‌ماند', $data['percent'], 20);

$GLOBALS['__zig_tax'] = 1.0;

/* ==========================================================================
 * محصول متغیر
 * ======================================================================= */

Tests::group('قیمت › محصول متغیر');

zig_reset_products();

/*
 * چیدمان این سه گزینه عمدی است.
 *
 * ارزان‌ترین گزینه ۱۰۲ است (۱۰۰۰) و قیمت عادیِ خودش ۱۵۰۰ است. ولی گزینهٔ
 * ۱۰۳ قیمت عادیِ کمتری دارد (۱۲۰۰). پس اگر کد به‌اشتباه «کمترین قیمت عادیِ
 * کل محصول» را بردارد، عدد ۱۲۰۰ درمی‌آید و درصد ۱۷ می‌شود به‌جای ۳۳.
 *
 * بدون این اختلاف، هر دو پیاده‌سازی — درست و غلط — یک جواب می‌دادند و سنجه
 * چیزی را ثابت نمی‌کرد.
 */
new WC_Product(['id' => 101, 'price' => '3000', 'regular' => '3000']);
new WC_Product(['id' => 102, 'price' => '1000', 'regular' => '1500']);
new WC_Product(['id' => 103, 'price' => '1200', 'regular' => '1200']);

$variable = new WC_Product([
    'id' => 100, 'type' => 'variable', 'children' => [101, 102, 103],
    'min' => '1000', 'max' => '3000',
]);

$data = Price::data($variable, 'min');

Tests::same('ارزان‌ترین گزینه مبنا می‌شود', $data['current'], '1000');
Tests::same('چندقیمتی علامت می‌خورد', $data['is_multi'], true);

/*
 * مهم‌ترین سنجهٔ این فایل.
 *
 * ارزان‌ترین قیمتِ فعال (۱۰۰۰) و ارزان‌ترین قیمتِ عادی (۱۵۰۰) باید از یک
 * گزینهٔ واحد بیایند. اگر کد کمترین قیمت عادیِ کل محصول را بردارد، به
 * گزینهٔ ۱۰۳ می‌رسد (۲۰۰۰) و درصدی می‌سازد که هیچ گزینه‌ای واقعاً ندارد.
 */
Tests::same('قیمت پیشین از همان گزینه می‌آید', $data['old'], '1500');
Tests::same('درصد تخفیف از همان گزینه حساب می‌شود', $data['percent'], 33);

Tests::group('قیمت › متغیر، حالت بازه');

$data = Price::data($variable, 'range');

Tests::same('کمترین قیمت بازه', $data['current'], '1000');
Tests::same('بیشترین قیمت بازه', $data['max'], '3000');
Tests::same('بازه علامت می‌خورد', $data['is_range'], true);

/*
 * وقتی همهٔ گزینه‌ها یک قیمت دارند، «بازه» معنا ندارد و باید به حالت
 * کمترین برگردد — وگرنه «۱۰۰۰ تا ۱۰۰۰» چاپ می‌شد.
 */
zig_reset_products();
new WC_Product(['id' => 201, 'price' => '900', 'regular' => '900']);
new WC_Product(['id' => 202, 'price' => '900', 'regular' => '900']);

$flat = new WC_Product([
    'id' => 200, 'type' => 'variable', 'children' => [201, 202], 'min' => '900', 'max' => '900',
]);
$data = Price::data($flat, 'range');

Tests::same('بازهٔ تک‌قیمتی، بازه نمی‌شود', $data['is_range'], false);
Tests::same('و به کمترین قیمت برمی‌گردد', $data['current'], '900');

Tests::group('قیمت › متغیر، گزینه‌های نامعتبر');

zig_reset_products();

// ارزان‌ترین گزینه، پنهان است
new WC_Product(['id' => 301, 'price' => '500', 'regular' => '500', 'visible' => false]);
new WC_Product(['id' => 302, 'price' => '900', 'regular' => '900']);

$hidden = new WC_Product([
    'id' => 300, 'type' => 'variable', 'children' => [301, 302], 'min' => '500', 'max' => '900',
]);

/*
 * گزینهٔ پنهان نباید مبنای قیمت شود؛ وگرنه صفحه عددی نشان می‌دهد که مشتری
 * هیچ راهی برای خریدنش ندارد.
 */
Tests::same('گزینهٔ پنهان مبنا نمی‌شود', Price::data($hidden)['current'], '900');

zig_reset_products();
new WC_Product(['id' => 401, 'price' => '400', 'regular' => '400', 'purchasable' => false]);
new WC_Product(['id' => 402, 'price' => '800', 'regular' => '800']);

$unbuyable = new WC_Product([
    'id' => 400, 'type' => 'variable', 'children' => [401, 402], 'min' => '400', 'max' => '800',
]);

Tests::same('گزینهٔ غیرقابل‌خرید مبنا نمی‌شود', Price::data($unbuyable)['current'], '800');

/*
 * اگر هیچ گزینهٔ قابل نمایشی نماند، باید به مقدار تجمیعیِ خودِ ووکامرس
 * برگردد — نه اینکه محصول بی‌قیمت رندر شود.
 */
zig_reset_products();
new WC_Product(['id' => 501, 'price' => '400', 'regular' => '400', 'purchasable' => false]);

$none = new WC_Product([
    'id' => 500, 'type' => 'variable', 'children' => [501], 'min' => '400', 'max' => '400',
]);
$data = Price::data($none);

Tests::same('پناهگاه تجمیعی به کار می‌آید', $data['current'], '400');
Tests::same('ولی بدون قیمت پیشین، چون به گزینه‌ای گره نخورده', $data['old'], '');

Tests::group('قیمت › متغیر، سقف تعداد گزینه');

zig_reset_products();

$ids = [];

for ($i = 1; $i <= 70; $i++) {
    $id    = 1000 + $i;
    $ids[] = $id;
    new WC_Product(['id' => $id, 'price' => (string) (100 * $i), 'regular' => (string) (200 * $i)]);
}

$huge = new WC_Product([
    'id' => 999, 'type' => 'variable', 'children' => $ids, 'min' => '100', 'max' => '7000',
]);
$data = Price::data($huge);

// ۷۰ گزینه از سقف ۶۰ بیشتر است، پس هیچ فرزندی لود نمی‌شود
Tests::same('از سقف که بگذرد به مقدار تجمیعی می‌رود', $data['current'], '100');
Tests::same('و قیمت پیشین نمی‌سازد', $data['old'], '');

/* ==========================================================================
 * محصول گروهی
 * ======================================================================= */

Tests::group('قیمت › محصول گروهی');

zig_reset_products();

new WC_Product(['id' => 601, 'price' => '2500', 'regular' => '3000']);
new WC_Product(['id' => 602, 'price' => '4000', 'regular' => '4000']);

/*
 * ‎get_price()‎ محصول گروهی رشتهٔ خالی است و قیمت باید از فرزندان بیاید.
 * نسخه‌های قبلیِ این ویجت فقط متغیر را می‌شناختند و محصول گروهی بی‌قیمت
 * رندر می‌شد.
 */
$grouped = new WC_Product(['id' => 600, 'type' => 'grouped', 'price' => '', 'children' => [601, 602]]);
$data    = Price::data($grouped, 'min');

Tests::same('قیمت گروهی از ارزان‌ترین فرزند می‌آید', $data['current'], '2500');
Tests::same('و تخفیف همان فرزند دیده می‌شود', $data['old'], '3000');
Tests::same('گروهی هم چندقیمتی است', $data['is_multi'], true);

$data = Price::data($grouped, 'range');

Tests::same('بازهٔ گروهی، کمترین', $data['current'], '2500');
Tests::same('بازهٔ گروهی، بیشترین', $data['max'], '4000');

/* ==========================================================================
 * پیدا کردن محصول
 * ======================================================================= */

Tests::group('قیمت › پیدا کردن محصول');

zig_reset_products();

$explicit = new WC_Product(['id' => 700, 'price' => '10']);
$looped   = new WC_Product(['id' => 701, 'price' => '20']);
$single   = new WC_Product(['id' => 702, 'price' => '30']);

Tests::same('شناسهٔ صریح مقدم است', Price::resolve(700)->get_id(), 700);

/*
 * ‎$GLOBALS['product']‎ همان چیزی است که ووکامرس در حلقهٔ فروشگاه و المنتور
 * در قالب حلقه ست می‌کنند. بدون این شاخه، ویجت داخل یک Loop Grid برای همهٔ
 * کارت‌ها قیمت یک محصول ثابت را نشان می‌داد.
 */
$GLOBALS['product'] = $looped;

Tests::same('در حلقه، محصول جاری برداشته می‌شود', Price::resolve()->get_id(), 701);
Tests::same('ولی شناسهٔ صریح باز هم مقدم است', Price::resolve(700)->get_id(), 700);

unset($GLOBALS['product']);
$GLOBALS['__zig_is_product'] = true;
$GLOBALS['__zig_queried']    = 702;

Tests::same('در صفحهٔ محصول، آبجکت کوئری‌شده', Price::resolve()->get_id(), 702);

$GLOBALS['__zig_is_product'] = false;
$GLOBALS['__zig_queried']    = 0;
$GLOBALS['__zig_post']       = 700;

Tests::same('در نهایت، پستِ جاری', Price::resolve()->get_id(), 700);

$GLOBALS['__zig_post'] = 0;

Tests::same('محصول ناموجود، null', Price::resolve(9999), null);

$gone = new WC_Product(['id' => 800, 'exists' => false]);

Tests::same('محصولی که وجود ندارد هم null', Price::resolve(800), null);

/* ==========================================================================
 * قالب‌بندی
 * ======================================================================= */

Tests::group('قیمت › قالب‌بندی');

zig_reset_products();

Tests::same('جداکنندهٔ هزارگان', Price::format('1234567'), '1,234,567');
Tests::same('رشتهٔ خالی، خالی می‌ماند', Price::format(''), '');

$GLOBALS['__zig_decimals'] = 2;
Tests::same('اعشار از تنظیمات ووکامرس می‌آید', Price::format('1234.5'), '1,234.50');
$GLOBALS['__zig_decimals'] = 0;

Tests::same('ارقام فارسی', Price::persian('1,234'), '۱,۲۳۴');
Tests::same('حروف دست نمی‌خورند', Price::persian('تومان 12'), 'تومان ۱۲');

Tests::group('قیمت › واحد پول');

Tests::same('متن دلخواه مقدم است', Price::currency('تومان'), 'تومان');
Tests::same('فاصلهٔ اضافه حذف می‌شود', Price::currency('  ریال '), 'ریال');

/*
 * نماد ووکامرس اغلب موجودیت HTML است. چون خروجی را خودمان اسکیپ می‌کنیم،
 * باید اول به کاراکتر واقعی برگردد وگرنه کاربر روی صفحه ‎&#84;‎ می‌بیند.
 */
$GLOBALS['__zig_symbol'] = '&#84;&#111;&#109;&#97;&#110;';

Tests::same('موجودیت HTML باز می‌شود', Price::currency(), 'Toman');
