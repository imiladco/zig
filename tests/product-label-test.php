<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/elementor-stub.php';
require_once __DIR__ . '/lib/woocommerce-stub.php';

$root = dirname(__DIR__);
require_once $root . '/includes/markup.php';
require_once $root . '/includes/price.php';
require_once $root . '/includes/schema-store.php';
require_once $root . '/includes/widgets/product-label.php';

use Zig3d_Widgets\Widgets\Product_Label;

/*
 * برخلافِ نامش، حالتِ «پرفروش» از آمارِ فروش نمی‌آید — فقط از عضویتِ
 * محصول در یکی از دسته‌هایی که مدیر انتخاب کرده. حالتِ «تخفیف» برعکس،
 * کاملاً از منطقِ واقعیِ قیمتِ ووکامرس می‌آید (‎Price::data()‎) و نه از
 * چیزی که کسی دستی گفته باشد.
 */

Tests::group('رندر › لیبل محصول، تخفیف');

zig_reset_products();

new WC_Product(['id' => 7001, 'price' => '80000', 'regular' => '100000', 'sale' => '80000', 'on_sale' => true]);

$discount = zig_render(Product_Label::class, [
    'product_id'     => 7001,
    'label_type'     => 'discount',
    'label_template' => '{value}% تخفیف',
    'persian_digits' => 'yes',
]);

Tests::keeps('ریشه با کلاسِ نوع رندر می‌شود', $discount, 'class="zig-label zig-label--discount"');
Tests::keeps('درصد با ارقامِ فارسی جای‌گذاری می‌شود', $discount, '۲۰% تخفیف');

$latin = zig_render(Product_Label::class, [
    'product_id'     => 7001,
    'label_type'     => 'discount',
    'label_template' => '{value}% تخفیف',
    'persian_digits' => '',
]);

Tests::keeps('ارقام لاتین هم پشتیبانی می‌شود', $latin, '20% تخفیف');

new WC_Product(['id' => 7002, 'price' => '50000', 'regular' => '50000', 'on_sale' => false]);

Tests::same(
    'محصولِ بدونِ تخفیفِ واقعی، چیزی رندر نمی‌کند',
    trim(zig_render(Product_Label::class, ['product_id' => 7002, 'label_type' => 'discount'])),
    ''
);

new WC_Product(['id' => 7003, 'price' => '95000', 'regular' => '100000', 'sale' => '95000', 'on_sale' => true]);

Tests::same(
    'درصدِ کمتر از حداقلِ تنظیم‌شده، چیزی رندر نمی‌کند',
    trim(zig_render(Product_Label::class, [
        'product_id' => 7003, 'label_type' => 'discount', 'min_percent' => 10,
    ])),
    ''
);

$above_min = zig_render(Product_Label::class, [
    'product_id' => 7001, 'label_type' => 'discount', 'min_percent' => 10, 'persian_digits' => '',
]);

Tests::keeps('درصدِ بالاترِ حداقل، رندر می‌شود', $above_min, '20% تخفیف');

Tests::same(
    'محصولِ ناموجود در سایت، چیزی رندر نمی‌کند',
    trim(zig_render(Product_Label::class, ['product_id' => 999999, 'label_type' => 'discount'])),
    ''
);

Tests::group('رندر › لیبل محصول، پرفروش');

zig_reset_products();

new WC_Product([
    'id'    => 7101,
    'terms' => ['product_cat' => [new WP_Term('نان', 'nan', 5), new WP_Term('شیرینی', 'shirini', 6)]],
]);
new WC_Product(['id' => 7102, 'terms' => ['product_cat' => [new WP_Term('نوشیدنی', 'noshidani', 9)]]]);

$bestseller = zig_render(Product_Label::class, [
    'product_id'      => 7101,
    'label_type'      => 'bestseller',
    'categories'      => [5],
    'bestseller_text' => 'پرفروش',
    'show_icon'       => 'yes',
]);

Tests::keeps('ریشه با کلاسِ نوع رندر می‌شود', $bestseller, 'class="zig-label zig-label--bestseller"');
Tests::keeps('متن رندر می‌شود', $bestseller, 'پرفروش');
Tests::keeps('آیکونِ شعله رندر می‌شود', $bestseller, 'zig-label__icon');
Tests::keeps('svg آیکون تزئینی است', $bestseller, 'aria-hidden="true"');

$no_icon = zig_render(Product_Label::class, [
    'product_id' => 7101, 'label_type' => 'bestseller', 'categories' => [5], 'show_icon' => '',
]);

Tests::blocks('بدونِ نمایشِ آیکون، svg نمی‌آید', $no_icon, 'zig-label__icon');

Tests::same(
    'محصولِ خارج از دسته‌های انتخاب‌شده، چیزی رندر نمی‌کند',
    trim(zig_render(Product_Label::class, [
        'product_id' => 7102, 'label_type' => 'bestseller', 'categories' => [5, 6],
    ])),
    ''
);

Tests::same(
    'بدونِ هیچ دستهٔ انتخاب‌شده‌ای، چیزی رندر نمی‌کند',
    trim(zig_render(Product_Label::class, [
        'product_id' => 7101, 'label_type' => 'bestseller', 'categories' => [],
    ])),
    ''
);

$custom_text = zig_render(Product_Label::class, [
    'product_id' => 7101, 'label_type' => 'bestseller', 'categories' => [6], 'bestseller_text' => 'محبوب',
]);

Tests::keeps('متنِ دلخواه رندر می‌شود', $custom_text, 'محبوب');

Tests::group('رندر › لیبل محصول، اسکیپ');

new WC_Product(['id' => 7104, 'terms' => ['product_cat' => [new WP_Term('نان', 'nan', 5)]]]);

$dirty = zig_render(Product_Label::class, [
    'product_id'      => 7104,
    'label_type'      => 'bestseller',
    'categories'      => [5],
    'bestseller_text' => 'پرفروش <script>alert(1)</script>',
]);

Tests::blocks('اسکریپت داخل متن حذف می‌شود', $dirty, '<script');
