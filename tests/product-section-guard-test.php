<?php
/**
 * حذفِ سکشن‌هایِ خالیِ صفحهٔ محصول.
 */

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/product-section-guard.php';

use Zig3d_Widgets\Product_Section_Guard;

Tests::group('نگهبانِ سکشن › صفحه‌ای بدون سکشنِ زیگ');

Tests::same(
    'صفحهٔ بی‌ربط دست‌نخورده می‌ماند',
    Product_Section_Guard::filter_html('<html><body><p>سلام</p></body></html>'),
    '<html><body><p>سلام</p></body></html>'
);

Tests::group('نگهبانِ سکشن › مشخصاتِ فنی');

$with_specs = '<html><body>'
    . '<section class="zig-product-Specifications"><div class="zig-specs"><dl><dt>وزن</dt><dd>۲ کیلو</dd></dl></div></section>'
    . '<p>بعدی</p></body></html>';

Tests::keeps('سکشنِ پر نگه داشته می‌شود', Product_Section_Guard::filter_html($with_specs), 'zig-specs');

$without_specs = '<html><body>'
    . '<section class="zig-product-Specifications"><div class="elementor-widget"></div></section>'
    . '<p>بعدی</p></body></html>';

$filtered = Product_Section_Guard::filter_html($without_specs);
Tests::blocks('سکشنِ خالی حذف می‌شود', $filtered, 'zig-product-Specifications');
Tests::keeps('بقیهٔ صفحه دست‌نخورده می‌ماند', $filtered, 'بعدی');

Tests::group('نگهبانِ سکشن › قابلیت‌ها/توضیحات/ویدیو/دانلود');

$cases = [
    'zig-product-ability'     => 'zig-feature',
    'zig-product-description' => 'zig-description',
    'zig-product-video'       => 'zig-product-video',
    'zig-product-downloads'   => 'zig-documents',
];

foreach ($cases as $section_class => $marker_class) {
    $filled = sprintf(
        '<html><body><section class="%s"><div class="%s">x</div></section></body></html>',
        $section_class,
        $marker_class
    );
    Tests::keeps(
        $section_class . ': پر نگه داشته می‌شود',
        Product_Section_Guard::filter_html($filled),
        $marker_class
    );

    $empty = sprintf(
        '<html><body><section class="%s"><div class="elementor-widget"></div></section></body></html>',
        $section_class
    );
    Tests::blocks(
        $section_class . ': خالی حذف می‌شود',
        Product_Section_Guard::filter_html($empty),
        $section_class
    );
}

Tests::group('نگهبانِ سکشن › چرا (ریپیترِ جت‌اینجینِ خام)');

$why_full = '<html><body><section class="zig-product-why">'
    . '<div><img src="/uploads/x.jpg" alt="عنوان"><div><h3>عنوان</h3><span>توضیح</span></div></div>'
    . '</section></body></html>';

Tests::keeps('آیتمِ کامل نگه داشته می‌شود', Product_Section_Guard::filter_html($why_full), 'zig-product-why');

$why_partial = '<html><body><section class="zig-product-why">'
    . '<div><img src="" alt=""><div><h3>فقط عنوان</h3><span></span></div></div>'
    . '</section></body></html>';

Tests::keeps('آیتمِ ناقص (فقط عنوان) هم نگه داشته می‌شود', Product_Section_Guard::filter_html($why_partial), 'zig-product-why');

$why_empty = '<html><body><section class="zig-product-why">'
    . '<div><img src="" alt=""><div><h3></h3><span></span></div></div>'
    . '</section></body></html>';

Tests::blocks('آیتمِ کاملاً خالی حذف می‌شود', Product_Section_Guard::filter_html($why_empty), 'zig-product-why');

Tests::group('نگهبانِ سکشن › پیکربندیِ سندِ المنتور (روشن/خاموش + کلاسِ دلخواه)');

$specs_empty = '<html><body><section class="zig-product-Specifications"><div class="elementor-widget"></div></section></body></html>';

Tests::keeps(
    'سکشنِ خاموش‌شده حتی خالی هم دست‌نخورده می‌ماند',
    Product_Section_Guard::filter_html($specs_empty, [
        'specs' => ['enabled' => false, 'class' => 'zig-product-Specifications'],
    ]),
    'zig-product-Specifications'
);

$custom_class_empty = '<html><body><section class="specs-custom"><div class="elementor-widget"></div></section></body></html>';

Tests::blocks(
    'کلاسِ سفارشی هم شناسایی و حذف می‌شود',
    Product_Section_Guard::filter_html($custom_class_empty, [
        'specs' => ['enabled' => true, 'class' => 'specs-custom'],
    ]),
    'specs-custom'
);

$custom_class_full = '<html><body><section class="specs-custom"><div class="zig-specs">x</div></section></body></html>';

Tests::keeps(
    'کلاسِ سفارشیِ پر نگه داشته می‌شود',
    Product_Section_Guard::filter_html($custom_class_full, [
        'specs' => ['enabled' => true, 'class' => 'specs-custom'],
    ]),
    'specs-custom'
);

Tests::group('نگهبانِ سکشن › چند سکشن هم‌زمان');

$mixed = '<html><body>'
    . '<section class="zig-product-Specifications"><div class="zig-specs">x</div></section>'
    . '<section class="zig-product-ability"><div class="elementor-widget"></div></section>'
    . '<section class="zig-product-description"><div class="zig-description">x</div></section>'
    . '</body></html>';

$mixed_filtered = Product_Section_Guard::filter_html($mixed);
Tests::keeps('مشخصاتِ پر می‌ماند', $mixed_filtered, 'zig-specs');
Tests::blocks('قابلیتِ خالی می‌رود', $mixed_filtered, 'zig-product-ability');
Tests::keeps('توضیحاتِ پر می‌ماند', $mixed_filtered, 'zig-description');
