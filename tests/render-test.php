<?php
/**
 * خروجی رندر ویجت‌ها.
 *
 * مسیر رندر جایی است که اشتباهاتش گران تمام می‌شود: تگ نامعتبر، محتوای
 * اسکیپ‌نشده، ‎<a>‎ بدون href، یا عنصر تهی که فقط یک فاصلهٔ بی‌دلیل در طراحی
 * می‌سازد. هیچ‌کدام خطای PHP نمی‌دهند.
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
require_once $root . '/includes/consultation-endpoint.php';
require_once $root . '/includes/widgets/traits/link.php';
require_once $root . '/includes/widgets/traits/consultation-trigger.php';
require_once $root . '/includes/widgets/traits/icon.php';
require_once $root . '/includes/widgets/traits/box.php';
require_once $root . '/includes/widgets/traits/pulse.php';
require_once $root . '/includes/widgets/feature-card.php';
require_once $root . '/includes/widgets/bullet-list.php';
require_once $root . '/includes/widgets/button.php';
require_once $root . '/includes/widgets/product-price.php';
require_once $root . '/includes/widgets/product-stock.php';
require_once $root . '/includes/widgets/documents.php';

use Zig3d_Widgets\Widgets\Bullet_List;
use Zig3d_Widgets\Widgets\Button;
use Zig3d_Widgets\Widgets\Documents;
use Zig3d_Widgets\Widgets\Feature_Card;
use Zig3d_Widgets\Widgets\Product_Price;
use Zig3d_Widgets\Widgets\Product_Stock;
use Zig3d_Widgets\Stock;

/* ==========================================================================
 * کارت ویژگی
 * ======================================================================= */

Tests::group('رندر › کارت ویژگی');

$card = zig_render(Feature_Card::class, [
    'icon_source' => 'text',
    'icon_text'   => 'الف',
    'title'       => 'بررسی و پیشنهاد میلینگ ماشین مناسب',
    'title_tag'   => 'h3',
    'text'        => 'بررسی حجم تولید و نوع متریال.',
]);

Tests::keeps('ریشهٔ کارت رندر می‌شود', $card, 'class="zig-card"');
Tests::keeps('عنوان با تگ انتخابی می‌آید', $card, '<h3 class="zig-card__title"');
Tests::keeps('توضیحات رندر می‌شود', $card, 'zig-card__text');
Tests::keeps('آیکون متنی رندر می‌شود', $card, 'zig-icon__text');
Tests::keeps('آیکون تزئینی از دید صفحه‌خوان پنهان است', $card, 'aria-hidden="true"');

/*
 * ‎<span>‎ فقط محتوای درون‌خطی می‌پذیرد. اگر بدنهٔ کارت ‎<span>‎ باشد و عنوان
 * ‎<h3>‎، مرورگر مارک‌آپ را بازچینی می‌کند و ساختاری که سلکتورهای استایل
 * رویش حساب کرده‌اند از بین می‌رود.
 */
Tests::keeps('بدنهٔ کارت div است نه span', $card, '<div class="zig-card__body">');

Tests::group('رندر › کارت ویژگی، حالت‌های مرزی');

Tests::same(
    'کارتِ کاملاً خالی چیزی رندر نمی‌کند',
    trim(zig_render(Feature_Card::class, ['icon_source' => 'none', 'title' => '', 'text' => ''])),
    ''
);

Tests::same(
    'عنوانِ فقط-فاصله هم خالی حساب می‌شود',
    trim(zig_render(Feature_Card::class, ['icon_source' => 'none', 'title' => ' <br> ', 'text' => ''])),
    ''
);

$linked = zig_render(Feature_Card::class, [
    'icon_source' => 'none',
    'title'       => 'عنوان',
    'link'        => ['url' => 'https://zig3d.com/about', 'is_external' => 'on', 'nofollow' => 'on'],
]);

Tests::keeps('کارتِ لینک‌دار به a تبدیل می‌شود', $linked, '<a ');
Tests::keeps('آدرس در خروجی هست', $linked, 'https://zig3d.com/about');
Tests::keeps('کلاس حالت لینک‌دار اضافه می‌شود', $linked, 'zig-card--linked');
Tests::keeps('target خارجی تنظیم می‌شود', $linked, 'target="_blank"');
Tests::keeps('rel امن همراهش می‌آید', $linked, 'noopener');
Tests::keeps('nofollow رعایت می‌شود', $linked, 'nofollow');

$unlinked = zig_render(Feature_Card::class, ['icon_source' => 'none', 'title' => 'عنوان']);

// ‎<a>‎ بدون href نه فوکوس می‌گیرد نه لینک به حساب می‌آید
Tests::blocks('بدون پیوند، تگ a نمی‌سازد', $unlinked, '<a ');

Tests::group('رندر › کارت ویژگی، اسکیپ');

$dirty = zig_render(Feature_Card::class, [
    'icon_source' => 'text',
    'icon_text'   => '<script>alert(1)</script>',
    'title'       => 'سلام <script>alert(2)</script> <span class="ok">دنیا</span>',
    'title_tag'   => 'script',
    'text'        => '<img src=x onerror=alert(3)>',
]);

Tests::blocks('اسکریپت داخل عنوان حذف می‌شود', $dirty, '<script>alert(2)');
Tests::blocks('اسکریپت داخل متن آیکون حذف می‌شود', $dirty, '<script>alert(1)');
Tests::blocks('تگ img تزریق‌شده حذف می‌شود', $dirty, '<img');
Tests::blocks('رویداد onerror حذف می‌شود', $dirty, 'onerror');
Tests::keeps('span مجاز باقی می‌ماند', $dirty, '<span class="ok">');

// تگ عنوانِ دستکاری‌شده نباید به مارک‌آپ تبدیل شود
Tests::keeps('تگ عنوان غیرمجاز به تگ امن برمی‌گردد', $dirty, '<h3 class="zig-card__title"');
Tests::blocks('و هیچ عنصر script نمی‌سازد', $dirty, '<script');

/* ==========================================================================
 * لیست عنوان‌ها
 * ======================================================================= */

Tests::group('رندر › لیست عنوان‌ها');

$list = zig_render(Bullet_List::class, [
    'label_tag'    => 'span',
    'bullet_shape' => 'circle',
    'items'        => [
        ['label' => 'تیم مهندسی مکانیک', '_id' => 'aaa', 'show_bullet' => 'yes'],
        ['label' => 'تأمین قطعات', '_id' => 'bbb', 'show_bullet' => 'yes', 'is_active' => 'yes'],
        ['label' => '', '_id' => 'ccc'],
    ],
]);

Tests::keeps('فهرست با ul رندر می‌شود', $list, '<ul class="zig-list"');
Tests::keeps('نقش فهرست صریح اعلام می‌شود', $list, 'role="list"');
Tests::same('آیتم خالی رندر نمی‌شود', substr_count($list, '<li '), 2);
Tests::keeps('کلاس ریپیتر برای استایل تک‌آیتمی هست', $list, 'elementor-repeater-item-aaa');
Tests::keeps('آیتم فعال کلاس می‌گیرد', $list, 'zig-list__item--active');
Tests::keeps('آیتم فعال aria-current دارد', $list, 'aria-current="true"');
Tests::keeps('بولت با کلاس شکل می‌آید', $list, 'zig-list__bullet--circle');
Tests::keeps('بولت تزئینی است', $list, 'aria-hidden="true"');

Tests::same(
    'فهرست بدون آیتم معتبر، هیچ ul تهی‌ای نمی‌سازد',
    trim(zig_render(Bullet_List::class, ['items' => [['label' => '  '], ['label' => '<br>']]])),
    ''
);

Tests::same(
    'نبودِ آیتم‌ها هم خروجی ندارد',
    trim(zig_render(Bullet_List::class, ['items' => []])),
    ''
);

$linked_list = zig_render(Bullet_List::class, [
    'items' => [['label' => 'درباره ما', '_id' => 'a', 'link' => ['url' => 'https://zig3d.com']]],
]);

Tests::keeps('آیتم لینک‌دار به a تبدیل می‌شود', $linked_list, '<a class="zig-list__label"');

$plain_list = zig_render(Bullet_List::class, [
    'items' => [['label' => 'بدون لینک', '_id' => 'a']],
]);

Tests::blocks('آیتم بدون لینک، a نمی‌سازد', $plain_list, '<a ');

Tests::group('رندر › لیست، شکل بولت دستکاری‌شده');

$evil_shape = zig_render(Bullet_List::class, [
    'bullet_shape' => 'circle" onload="alert(1)',
    'items'        => [['label' => 'x', '_id' => 'a', 'show_bullet' => 'yes']],
]);

/*
 * چیزی که واقعاً اهمیت دارد این نیست که رشتهٔ «onload» در خروجی نباشد —
 * ممکن است بی‌ضرر داخل نام کلاس چسبیده باشد. اهمیتش این است که مقدار
 * نتواند از کوتیشنِ ویژگی بیرون بزند و ویژگی تازه‌ای بسازد. پس خودِ کلاس
 * سنجیده می‌شود، نه وجود یک زیررشته.
 */
preg_match('/class="([^"]*zig-list__bullet[^"]*)"/', $evil_shape, $matches);

Tests::ok(
    'کلاس بولت فقط کاراکتر مجاز دارد',
    (bool) preg_match('/^[A-Za-z0-9 _-]+$/', $matches[1] ?? '!'),
    $matches[1] ?? 'کلاسی پیدا نشد'
);

Tests::blocks('هیچ ویژگی رویدادی ساخته نمی‌شود', $evil_shape, 'onload=');

/* ==========================================================================
 * دکمه
 * ======================================================================= */

Tests::group('رندر › دکمه');

$btn = zig_render(Button::class, [
    'text' => 'درباره ZIG3D',
    'size' => 'lg',
    'link' => ['url' => 'https://zig3d.com/about'],
]);

Tests::keeps('با پیوند، تگ a می‌شود', $btn, '<a ');
Tests::keeps('کلاس اندازه اعمال می‌شود', $btn, 'zig-btn--lg');
Tests::keeps('متن در span خودش است', $btn, 'zig-btn__text');
Tests::blocks('لینک نباید ویژگی type بگیرد', $btn, 'type=');

$plain = zig_render(Button::class, ['text' => 'ارسال', 'tag' => 'button', 'button_type' => 'submit']);

Tests::keeps('بدون پیوند، تگ button می‌شود', $plain, '<button ');
Tests::keeps('نوع دکمه اعمال می‌شود', $plain, 'type="submit"');

/*
 * ‎<a>‎ بدون href فوکوس‌پذیر نیست و صفحه‌خوان آن را لینک نمی‌داند. پس انتخاب
 * صریحِ «لینک» بدون آدرس هم باید به عنصر معتبر برگردد.
 */
$forced = zig_render(Button::class, ['text' => 'بدون آدرس', 'tag' => 'a']);

Tests::keeps('انتخاب «لینک» بدون آدرس به button برمی‌گردد', $forced, '<button ');
Tests::blocks('و هیچ a بی‌href نمی‌سازد', $forced, '<a ');

$evil_type = zig_render(Button::class, ['text' => 'x', 'tag' => 'button', 'button_type' => 'submit" onclick="alert(1)']);

Tests::blocks('نوع دکمهٔ دستکاری‌شده رد می‌شود', $evil_type, 'onclick');
Tests::keeps('و به مقدار امن برمی‌گردد', $evil_type, 'type="button"');

Tests::group('رندر › دکمه، فرمِ مشاوره');

$consult_btn = zig_render(Button::class, [
    'text'             => 'مشاوره بگیرید',
    'link'             => ['url' => 'https://zig3d.com/should-be-ignored'],
    'consultation_on'  => 'yes',
]);

Tests::keeps('روشن‌بودنِ فرمِ مشاوره تگ را button می‌کند، نه a', $consult_btn, '<button ');
Tests::blocks('و هیچ a بی‌ربط نمی‌سازد', $consult_btn, '<a ');
Tests::blocks('پیوندِ تنظیم‌شده نادیده گرفته می‌شود', $consult_btn, 'should-be-ignored');
Tests::keeps('نشانهٔ data-zig-consultation چاپ می‌شود', $consult_btn, 'data-zig-consultation="1"');
Tests::keeps('آدرسِ آژاکس هم', $consult_btn, 'data-zig-consultation-endpoint=');
Tests::keeps('و نانس هم', $consult_btn, 'data-zig-consultation-nonce=');
Tests::blocks(
    'بدونِ محصول (ویجتِ دکمه)، ویژگیِ نامِ محصول ساخته نمی‌شود',
    $consult_btn,
    'data-zig-consultation-product-name'
);

$link_only_btn = zig_render(Button::class, ['text' => 'مشاوره بگیرید', 'link' => ['url' => 'https://zig3d.com/x']]);

Tests::blocks('خاموش (پیش‌فرض)، هیچ data-zig-consultation-ای نیست', $link_only_btn, 'data-zig-consultation');
Tests::keeps('و رفتارِ عادیِ لینک دست‌نخورده می‌ماند', $link_only_btn, '<a ');

Tests::group('رندر › دکمه، دسترسی‌پذیری');

$icon_only = zig_render(Button::class, [
    'text' => '',
    'icon' => ['value' => 'fas fa-arrow-left', 'library' => 'fa-solid'],
]);

// دکمهٔ فقط-آیکون بدون نام، برای صفحه‌خوان فقط «دکمه» است
Tests::keeps('دکمهٔ فقط-آیکون نامی برای صفحه‌خوان می‌گیرد', $icon_only, 'aria-label=');

$labelled = zig_render(Button::class, ['text' => 'بیشتر', 'aria_label' => 'اطلاعات بیشتر دربارهٔ زیگ']);

Tests::keeps('برچسب دستی اعمال می‌شود', $labelled, 'aria-label="اطلاعات بیشتر دربارهٔ زیگ"');

Tests::same(
    'دکمهٔ بدون متن و بدون آیکون چیزی رندر نمی‌کند',
    trim(zig_render(Button::class, ['text' => ''])),
    ''
);

Tests::group('رندر › دکمه، جای آیکون');

$icon_start = zig_render(Button::class, [
    'text'          => 'برگشت',
    'icon'          => ['value' => 'fas fa-arrow-right', 'library' => 'fa-solid'],
    'icon_position' => 'start',
]);

Tests::keeps('کلاس جای آیکون اعمال می‌شود', $icon_start, 'zig-btn--icon-start');
Tests::ok(
    'آیکون پیش از متن می‌آید',
    strpos($icon_start, 'zig-btn__icon') < strpos($icon_start, 'zig-btn__text')
);

$icon_end = zig_render(Button::class, [
    'text'          => 'ادامه',
    'icon'          => ['value' => 'fas fa-arrow-left', 'library' => 'fa-solid'],
    'icon_position' => 'end',
]);

Tests::ok(
    'آیکون پس از متن می‌آید',
    strpos($icon_end, 'zig-btn__icon') > strpos($icon_end, 'zig-btn__text')
);

/* ==========================================================================
 * قیمت محصول
 * ======================================================================= */

Tests::group('رندر › قیمت محصول');

zig_reset_products();

$on_sale = new WC_Product([
    'id' => 9001, 'price' => '80000', 'regular' => '100000', 'sale' => '80000', 'on_sale' => true,
]);

$price = zig_render(Product_Price::class, [
    'product_id'      => 9001,
    'show_old'        => 'yes',
    'show_badge'      => 'yes',
    'badge_mode'      => 'percent',
    'badge_template'  => '{value}٪',
    'currency_text'   => 'تومان',
    'currency_on_now' => 'yes',
    'persian_digits'  => 'yes',
]);

Tests::keeps('ریشهٔ قیمت رندر می‌شود', $price, 'class="zig-price');
Tests::keeps('حالت تخفیف کلاس می‌گیرد', $price, 'zig-price--on-sale');
Tests::keeps('قیمت فعلی رندر می‌شود', $price, 'zig-price__now');
Tests::keeps('عدد با جداکننده و ارقام فارسی', $price, '۸۰,۰۰۰');
Tests::keeps('واحد پول کنارش می‌آید', $price, 'تومان');
Tests::keeps('بج درصد رندر می‌شود', $price, '۲۰٪');

/*
 * ‎<del>‎ و ‎<ins>‎ همان الگویی است که خودِ ووکامرس به کار می‌برد و برای
 * صفحه‌خوان معنای «قبلاً این بود، حالا این است» را می‌رساند. ‎<span>‎ ساده
 * فقط دو عدد پشت سر هم است.
 */
Tests::keeps('قیمت پیشین داخل del است', $price, '<del class="zig-price__old"');
Tests::keeps('قیمت فعلی داخل ins است', $price, '<ins class="zig-price__now"');
Tests::keeps('متن راهنمای صفحه‌خوان هست', $price, 'zig-price__sr');

/*
 * عدد در متن راست‌به‌چپ یک «اجرای چپ‌به‌راست» است؛ بدون ‎<bdi>‎ الگوریتم
 * دوجهتهٔ یونیکد می‌تواند عدد و واحد را جابه‌جا کند.
 */
Tests::keeps('عدد داخل bdi است', $price, '<bdi class="zig-price__value">');
Tests::keeps('بج برای صفحه‌خوان توضیح دارد', $price, 'aria-label=');

Tests::group('رندر › قیمت، بدون تخفیف');

$plain_price = new WC_Product(['id' => 9002, 'price' => '50000', 'regular' => '50000']);

$out = zig_render(Product_Price::class, [
    'product_id' => 9002, 'show_old' => 'yes', 'show_badge' => 'yes', 'persian_digits' => '',
]);

Tests::blocks('بدون تخفیف، قیمت پیشین نیست', $out, '<del');
Tests::blocks('بدون تخفیف، بج نیست', $out, 'zig-price__badge');

// بدون <del> متناظر، <ins> بی‌معناست
Tests::blocks('و ins هم نمی‌آید', $out, '<ins');
Tests::keeps('ولی قیمت فعلی هست', $out, '50,000');

Tests::group('رندر › قیمت، حالت‌های خاص');

$no_price = new WC_Product(['id' => 9003, 'price' => '', 'regular' => '']);

Tests::same(
    'محصول بی‌قیمت با تنظیم «پنهان»، چیزی رندر نمی‌کند',
    trim(zig_render(Product_Price::class, ['product_id' => 9003, 'empty_behavior' => 'hide'])),
    ''
);

$fallback = zig_render(Product_Price::class, [
    'product_id'     => 9003,
    'empty_behavior' => 'text',
    'empty_text'     => 'تماس بگیرید',
]);

Tests::keeps('متن جایگزین نمایش داده می‌شود', $fallback, 'تماس بگیرید');
Tests::keeps('و کلاس حالت خالی می‌گیرد', $fallback, 'zig-price--empty');

$free = new WC_Product(['id' => 9004, 'price' => '0', 'regular' => '0']);

$free_out = zig_render(Product_Price::class, [
    'product_id' => 9004, 'free_text' => 'رایگان', 'persian_digits' => '',
]);

Tests::keeps('قیمت صفر، متن رایگان می‌گیرد', $free_out, 'رایگان');
Tests::blocks('و عدد صفر چاپ نمی‌شود', $free_out, 'zig-price__amount');

Tests::same(
    'محصول ناموجود در سایت چیزی رندر نمی‌کند',
    trim(zig_render(Product_Price::class, ['product_id' => 999999])),
    ''
);

Tests::group('رندر › قیمت، محصول متغیر');

zig_reset_products();

new WC_Product(['id' => 9101, 'price' => '1000', 'regular' => '1000']);
new WC_Product(['id' => 9102, 'price' => '3000', 'regular' => '3000']);

$variable = new WC_Product([
    'id' => 9100, 'type' => 'variable', 'children' => [9101, 9102], 'min' => '1000', 'max' => '3000',
]);

$min_out = zig_render(Product_Price::class, [
    'product_id' => 9100, 'variable_mode' => 'min', 'prefix_text' => 'شروع از', 'persian_digits' => '',
]);

Tests::keeps('پیشوند برای محصول متغیر می‌آید', $min_out, 'شروع از');

$range_out = zig_render(Product_Price::class, [
    'product_id'      => 9100,
    'variable_mode'   => 'range',
    'range_separator' => 'تا',
    'persian_digits'  => '',
]);

Tests::keeps('بازه کلاس می‌گیرد', $range_out, 'zig-price--range');
Tests::keeps('جداکنندهٔ بازه می‌آید', $range_out, 'zig-price__sep');
Tests::keeps('کمترین قیمت', $range_out, '1,000');
Tests::keeps('بیشترین قیمت', $range_out, '3,000');
Tests::blocks('در حالت بازه پیشوند نمی‌آید', $range_out, 'شروع از');

/*
 * «شروع از» روی محصول ساده به مشتری می‌گوید قیمت‌های دیگری هم هست — که
 * وجود ندارند.
 */
$simple_prefix = zig_render(Product_Price::class, [
    'product_id' => 9101, 'variable_mode' => 'min', 'prefix_text' => 'شروع از',
]);

Tests::blocks('پیشوند روی محصول ساده نمی‌آید', $simple_prefix, 'شروع از');

Tests::group('رندر › قیمت، اسکیمای ساختاریافته');

$schema = zig_render(Product_Price::class, ['product_id' => 9101, 'schema' => 'yes']);

Tests::keeps('itemtype اعلام می‌شود', $schema, 'schema.org/Offer');
Tests::keeps('قیمت خام در content می‌آید', $schema, 'itemprop="price"');
Tests::keeps('واحد پول هم اعلام می‌شود', $schema, 'priceCurrency');

$no_schema = zig_render(Product_Price::class, ['product_id' => 9101]);

Tests::blocks('پیش‌فرض خاموش است', $no_schema, 'itemprop');

/* ==========================================================================
 * وضعیت موجودی
 * ======================================================================= */

Tests::group('رندر › وضعیت موجودی');

zig_reset_products();

/** تنظیمات پایه با متن‌های پیش‌فرض هر وضعیت */
function zig_stock_settings(array $overrides = []): array {
    return $overrides + [
        'show_bullet'          => 'yes',
        'bullet_shape'         => 'circle',
        'enable_backorder'     => 'yes',
        'persian_digits'       => 'yes',
        'text_instock'         => 'موجود در انبار',
        'text_lowstock'        => 'تنها {qty} عدد باقی مانده',
        'text_available'       => 'موجود در انبار',
        'text_onbackorder'     => 'قابل سفارش',
        'text_outofstock'      => 'تماس برای موجودی',
    ];
}

new WC_Product(['id' => 8001, 'status' => 'instock', 'managing' => true, 'qty' => 5]);

$in = zig_render(Product_Stock::class, zig_stock_settings(['product_id' => 8001]));

Tests::keeps('کلاس وضعیت روی ریشه می‌نشیند', $in, 'zig-stock--instock');
Tests::keeps('متن وضعیت رندر می‌شود', $in, 'موجود در انبار');
Tests::keeps('نشان رندر می‌شود', $in, 'zig-stock__bullet--circle');
Tests::keeps('نشان تزئینی است', $in, 'aria-hidden="true"');

// «در نهایت فقط یک وضعیت نمایش داده می‌شود»
Tests::same('فقط یک وضعیت رندر می‌شود', substr_count($in, 'class="zig-stock zig-stock--'), 1);
Tests::blocks('و متن وضعیت‌های دیگر نمی‌آید', $in, 'تماس برای موجودی');

new WC_Product(['id' => 8002, 'status' => 'outofstock', 'in_stock' => false]);

$out = zig_render(Product_Stock::class, zig_stock_settings(['product_id' => 8002]));

Tests::keeps('ناموجود کلاس خودش را می‌گیرد', $out, 'zig-stock--outofstock');
Tests::keeps('و متن خودش را', $out, 'تماس برای موجودی');
Tests::blocks('و متن موجود نمی‌آید', $out, 'موجود در انبار');

new WC_Product(['id' => 8003, 'status' => 'onbackorder', 'in_stock' => true]);

$back = zig_render(Product_Stock::class, zig_stock_settings(['product_id' => 8003]));

Tests::keeps('پیش‌خرید کلاس خودش را می‌گیرد', $back, 'zig-stock--onbackorder');
Tests::keeps('و متن «قابل سفارش»', $back, 'قابل سفارش');

$back_off = zig_render(Product_Stock::class, zig_stock_settings([
    'product_id' => 8003, 'enable_backorder' => '',
]));

Tests::keeps('با خاموش بودن، به وضعیت «موجود» می‌افتد', $back_off, 'zig-stock--available');
Tests::blocks('و متن پیش‌خرید نمی‌آید', $back_off, 'قابل سفارش');

Tests::group('رندر › موجودی، جای‌گذاری تعداد');

new WC_Product(['id' => 8004, 'status' => 'instock', 'managing' => true, 'qty' => 2, 'low' => 3]);

$low = zig_render(Product_Stock::class, zig_stock_settings([
    'product_id' => 8004, 'enable_lowstock' => 'yes',
]));

Tests::keeps('تعداد در متن می‌نشیند', $low, 'تنها ۲ عدد باقی مانده');

$latin = zig_render(Product_Stock::class, zig_stock_settings([
    'product_id' => 8004, 'enable_lowstock' => 'yes', 'persian_digits' => '',
]));

Tests::keeps('ارقام لاتین هم پشتیبانی می‌شود', $latin, 'تنها 2 عدد');

/*
 * وقتی تعدادی در کار نیست، نشانه و فاصله‌های دو طرفش با هم حذف می‌شوند —
 * وگرنه «تنها  عدد» با دو فاصله چاپ می‌شد.
 */
new WC_Product(['id' => 8005, 'status' => 'instock', 'managing' => false]);

$no_qty = zig_render(Product_Stock::class, zig_stock_settings([
    'product_id'     => 8005,
    'text_available' => 'موجود {qty} در انبار',
]));

Tests::keeps('نشانهٔ تعداد بدون فاصلهٔ اضافه حذف می‌شود', $no_qty, 'موجود در انبار');
Tests::blocks('و {qty} خام باقی نمی‌ماند', $no_qty, '{qty}');

Tests::group('رندر › موجودی، حالت‌های مرزی');

Tests::same(
    'وضعیت پنهان‌شده چیزی رندر نمی‌کند',
    trim(zig_render(Product_Stock::class, zig_stock_settings([
        'product_id'    => 8002,
        'hidden_states' => [Stock::OUT_OF_STOCK],
    ]))),
    ''
);

Tests::same(
    'متن خالی هم چیزی رندر نمی‌کند',
    trim(zig_render(Product_Stock::class, zig_stock_settings([
        'product_id'      => 8002,
        'text_outofstock' => '   ',
    ]))),
    ''
);

Tests::same(
    'محصول ناموجود در سایت چیزی رندر نمی‌کند',
    trim(zig_render(Product_Stock::class, zig_stock_settings(['product_id' => 999999]))),
    ''
);

$no_bullet = zig_render(Product_Stock::class, zig_stock_settings([
    'product_id' => 8001, 'show_bullet' => '',
]));

Tests::blocks('بدون نشان، عنصر نشان رندر نمی‌شود', $no_bullet, 'zig-stock__bullet');
Tests::keeps('ولی متن سر جایش است', $no_bullet, 'موجود در انبار');

Tests::group('رندر › موجودی، اسکیپ و اسکیما');

$dirty_stock = zig_render(Product_Stock::class, zig_stock_settings([
    'product_id'   => 8001,
    'text_instock' => 'موجود <script>alert(1)</script> <span class="ok">حالا</span>',
    'bullet_shape' => 'circle" onload="alert(1)',
]));

Tests::blocks('اسکریپت داخل متن وضعیت حذف می‌شود', $dirty_stock, '<script');
Tests::keeps('span مجاز باقی می‌ماند', $dirty_stock, '<span class="ok">');
Tests::blocks('شکل دستکاری‌شده ویژگی تازه نمی‌سازد', $dirty_stock, 'onload=');

$schema_stock = zig_render(Product_Stock::class, zig_stock_settings([
    'product_id' => 8001, 'schema' => 'yes',
]));

Tests::keeps('اسکیمای Offer اعلام می‌شود', $schema_stock, 'schema.org/Offer');
Tests::keeps('و وضعیت موجودی به‌صورت لینک', $schema_stock, 'itemprop="availability"');
Tests::keeps('با نشانی درست', $schema_stock, 'schema.org/InStock');

Tests::blocks(
    'پیش‌فرض خاموش است',
    zig_render(Product_Stock::class, zig_stock_settings(['product_id' => 8001])),
    'itemprop'
);

/* ==========================================================================
 * تپش نشان (مشترک بین لیست و وضعیت موجودی)
 * ======================================================================= */

Tests::group('رندر › تپش، لیست عنوان‌ها');

$pulse_items = [
    ['label' => 'مورد عادی', '_id' => 'p1', 'show_bullet' => 'yes'],
    ['label' => 'مورد تازه', '_id' => 'p2', 'show_bullet' => 'yes', 'item_pulse' => 'yes'],
];

$none = zig_render(Bullet_List::class, ['items' => $pulse_items, 'bullet_pulse' => '']);

Tests::same('فقط همان یک آیتم می‌تپد', substr_count($none, 'zig-pulse'), 1);

/*
 * کلاس تپش باید روی خودِ بولت باشد نه روی ریشه؛ وگرنه نمی‌شود در یک فهرست
 * فقط یک آیتم را تپنده کرد — که کاربرد اصلی همین است.
 */
Tests::keeps('کلاس روی خودِ بولت می‌نشیند', $none, 'zig-list__bullet--circle zig-pulse');
Tests::blocks('و روی ریشه نمی‌نشیند', $none, 'zig-list zig-pulse');

$all = zig_render(Bullet_List::class, ['items' => $pulse_items, 'bullet_pulse' => 'yes']);

Tests::same('کلید سراسری همه را می‌تپاند', substr_count($all, 'zig-pulse'), 2);

$off = zig_render(Bullet_List::class, [
    'items'        => [['label' => 'بی‌تپش', '_id' => 'p3', 'show_bullet' => 'yes']],
    'bullet_pulse' => '',
]);

Tests::blocks('بدون هیچ‌کدام، تپشی نیست', $off, 'zig-pulse');

// بولتی که اصلاً رندر نمی‌شود نباید کلاس تپش بگیرد
$hidden_bullet = zig_render(Bullet_List::class, [
    'items'        => [['label' => 'بدون بولت', '_id' => 'p4', 'show_bullet' => '', 'item_pulse' => 'yes']],
    'bullet_pulse' => 'yes',
]);

Tests::blocks('بدون بولت، تپشی هم نیست', $hidden_bullet, 'zig-pulse');

Tests::group('رندر › تپش، وضعیت موجودی');

zig_reset_products();

new WC_Product(['id' => 8501, 'status' => 'instock', 'managing' => true, 'qty' => 2, 'low' => 3]);

$stock_pulse = zig_render(Product_Stock::class, zig_stock_settings([
    'product_id'      => 8501,
    'enable_lowstock' => 'yes',
    'bullet_pulse'    => 'yes',
    'pulse_states'    => [Stock::LOW_STOCK],
]));

Tests::keeps('وضعیت هدف می‌تپد', $stock_pulse, 'zig-pulse');
Tests::keeps('و کلاس روی خودِ نشان است', $stock_pulse, 'zig-stock__bullet--circle zig-pulse');

new WC_Product(['id' => 8502, 'status' => 'instock', 'managing' => true, 'qty' => 20]);

$other_state = zig_render(Product_Stock::class, zig_stock_settings([
    'product_id'      => 8502,
    'enable_lowstock' => 'yes',
    'bullet_pulse'    => 'yes',
    'pulse_states'    => [Stock::LOW_STOCK],
]));

Tests::blocks('وضعیت دیگر نمی‌تپد', $other_state, 'zig-pulse');

$every_state = zig_render(Product_Stock::class, zig_stock_settings([
    'product_id'   => 8502,
    'bullet_pulse' => 'yes',
    'pulse_states' => [],
]));

Tests::keeps('فهرست خالی یعنی همهٔ وضعیت‌ها', $every_state, 'zig-pulse');

/* ==========================================================================
 * اسناد قابل دانلود
 * ======================================================================= */

Tests::group('رندر › اسناد قابل دانلود');

/*
 * یک پست (اینجا: محصولِ استابی) با متایِ ریپیترِ JetEngine. فیلدِ فایل
 * به‌صورت شناسهٔ پیوست می‌آید تا مسیرِ «wp_get_attachment_url + حجم از
 * get_attached_file» هم گرفته شود.
 */
$GLOBALS['__zig_attachments'][9101] = [
    'url'  => 'https://example.com/files/catalog.pdf',
    'file' => '/var/www/wp-content/uploads/catalog.pdf',
    'alt'  => '',
];

$GLOBALS['__zig_file'][9101] = '/var/www/wp-content/uploads/catalog.pdf';

new WC_Product([
    'id'   => 9100,
    'meta' => [
        'documents' => [
            [
                'document_file'     => 9101,
                'document_title'    => 'کاتالوگ دستگاه',
                'document_format'   => '',
                'document_language' => 'فارسی',
                'document_version'  => 'نسخهٔ ۲',
                'document_date'     => '۱۴۰۳/۰۵/۱۰',
                'document_size'      => '',
            ],
        ],
    ],
]);

$docs = zig_render(Documents::class, [
    'meta_key'            => 'documents',
    'source_post_id'      => 9100,
    'field_file'          => 'document_file',
    'field_title'         => 'document_title',
    'field_format'        => 'document_format',
    'field_language'      => 'document_language',
    'field_version'       => 'document_version',
    'field_date'          => 'document_date',
    'field_size'          => 'document_size',
    'show_format'         => 'yes',
    'show_language'       => 'yes',
    'show_version'        => 'yes',
    'show_date'           => 'yes',
    'show_size'           => 'yes',
    'show_download_label' => 'yes',
    'download_label'      => 'دانلود',
]);

Tests::keeps('گریدِ ریشه رندر می‌شود', $docs, 'class="zig-documents"');
Tests::keeps('کارت یک <a> است', $docs, '<a ');
Tests::keeps('href از فایل می‌آید', $docs, 'href="https://example.com/files/catalog.pdf"');
Tests::keeps('عنوان رندر می‌شود', $docs, 'zig-documents__title');
Tests::keeps('فرمت از پسوند استخراج می‌شود', $docs, '>PDF<');
Tests::keeps('زبان رندر می‌شود', $docs, 'فارسی');
Tests::keeps('نسخه رندر می‌شود', $docs, 'نسخهٔ ۲');
Tests::keeps('تاریخ همان‌طور که وارد شده', $docs, '۱۴۰۳/۰۵/۱۰');
Tests::blocks('دکمهٔ تودرتو نیست', $docs, '<button');
Tests::keeps('برچسب دانلود رندر می‌شود', $docs, 'zig-documents__download');
Tests::keeps('آیکون ثابت محلی', $docs, 'zig-documents__icon');

Tests::group('رندر › اسناد، حالت‌های مرزی');

/*
 * سطرِ بدونِ فایل نادیده گرفته می‌شود — قانونِ «بدونِ فایل، کارتِ
 * قابل‌دانلود‌ای نمی‌سازد».
 */
new WC_Product([
    'id'   => 9103,
    'meta' => [
        'documents' => [
            ['document_title' => 'بدون فایل', 'document_file' => ''],
            ['document_title' => 'با فایل', 'document_file' => 'https://example.com/x.docx'],
        ],
    ],
]);

$only_valid = zig_render(Documents::class, [
    'meta_key'       => 'documents',
    'source_post_id' => 9103,
    'field_file'     => 'document_file',
    'field_title'    => 'document_title',
    'show_download_label' => 'no',
]);

Tests::blocks('سطرِ بدونِ فایل چاپ نمی‌شود', $only_valid, 'بدون فایل');
Tests::keeps('سطرِ با فایل چاپ می‌شود', $only_valid, 'با فایل');

/*
 * متایِ نامعتبر (غیرِآرایه) → خروجیِ خالی، بدونِ خطا.
 */
new WC_Product(['id' => 9104, 'meta' => ['documents' => 'not-an-array']]);

$empty = zig_render(Documents::class, [
    'meta_key'       => 'documents',
    'source_post_id' => 9104,
]);

Tests::same('متای غیرآرایه چیزی چاپ نمی‌کند', trim($empty), '');

/*
 * حجم از فیلدِ دستیِ کاربر می‌آید وقتی کلیدِ آن تنظیم شده باشد — حتی اگر
 * استخراجِ حجم از فایلِ پیوست ممکن نباشد.
 */
new WC_Product([
    'id'   => 9105,
    'meta' => [
        'documents' => [
            [
                'document_title' => 'راهنمای نصب',
                'document_file'  => 'https://example.com/guide.pdf',
                'document_size'   => '۴٫۲ مگابایت',
            ],
        ],
    ],
]);

$explicit_size = zig_render(Documents::class, [
    'meta_key'       => 'documents',
    'source_post_id' => 9105,
    'field_file'     => 'document_file',
    'field_title'    => 'document_title',
    'field_size'     => 'document_size',
    'show_size'      => 'yes',
    'show_download_label' => 'no',
]);

Tests::keeps('حجمِ دستی چاپ می‌شود', $explicit_size, '۴٫۲ مگابایت');

/*
 * تاریخ به‌صورت timestamp — JetEngine Date field می‌تواند عدد برگرداند.
 * باید فرمت شود، نه اینکه عدد خام چاپ شود.
 */
new WC_Product([
    'id'   => 9106,
    'meta' => [
        'documents' => [
            [
                'document_title' => 'راهنمای کاربر',
                'document_file'  => 'https://example.com/user-guide.pdf',
                'document_date'  => 1715472000, // 2024-05-12
            ],
        ],
    ],
]);

$timestamp_date = zig_render(Documents::class, [
    'meta_key'       => 'documents',
    'source_post_id' => 9106,
    'field_file'     => 'document_file',
    'field_title'    => 'document_title',
    'field_date'     => 'document_date',
    'show_date'      => 'yes',
    'show_download_label' => 'no',
]);

Tests::keeps('تاریخِ timestamp فرمت می‌شود', $timestamp_date, '2024/05/12');

