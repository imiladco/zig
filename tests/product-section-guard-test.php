<?php
/**
 * حذفِ سکشن‌هایِ خالیِ صفحهٔ محصول.
 */

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/product-section-guard.php';

use Zig3d_Widgets\Product_Section_Guard;

Tests::group('نگهبانِ سکشن › سازگاری با کالبکِ واقعیِ ob_start');

/*
 * ‎ob_start()‎ کالبکش را با *دو* آرگومان صدا می‌زند: ‎(string $buffer, int
 * $phase)‎. اگر روزی کسی ‎maybe_start_buffer()‎ را طوری عوض کند که دوباره
 * ‎filter_html‎ (که پارامترِ دومش تایپ‌شدهٔ ‎?array‎ است) را مستقیم به
 * ‎ob_start‎ بدهد نه از پشتِ یک بستارِ تک‌آرگومانی، آن ‎$phase‎ی عددی به
 * ‎$config‎ می‌رسد و ‎TypeError‎ می‌دهد — دقیقاً باگی که یک‌بار پیش از پوش
 * با تست گرفته شد. این تست همان مسیرِ واقعی را شبیه‌سازی می‌کند.
 */
ob_start(static fn (string $html): string => Product_Section_Guard::filter_html($html));
echo '<html><body><section class="zig-product-Specifications"><div class="zig-specs">x</div></section></body></html>';
$ob_out = ob_get_clean();

Tests::keeps('کالبکِ ob_start بدونِ TypeError اجرا می‌شود', (string) $ob_out, 'zig-specs');

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

Tests::group('نگهبانِ سکشن › حاشیهٔ حافظه — جلوگیری از فاتال به‌جایِ ریسک‌کردن');

$headroom = new ReflectionMethod(Product_Section_Guard::class, 'has_memory_headroom');
$headroom->setAccessible(true);

Tests::ok(
    'وقتی حافظهٔ کافی هست، اجازهٔ پارس داده می‌شود',
    true === $headroom->invoke(null, str_repeat('x', 1000), '256M', 10 * 1024 * 1024)
);

Tests::ok(
    'وقتی مصرفِ فعلی + حجمِ HTML به سقف نزدیک است، پارس رد می‌شود',
    false === $headroom->invoke(null, str_repeat('x', 5 * 1024 * 1024), '64M', 60 * 1024 * 1024)
);

Tests::ok(
    'memory_limit نامحدود (-1) همیشه اجازه می‌دهد',
    true === $headroom->invoke(null, str_repeat('x', 50 * 1024 * 1024), '-1', 500 * 1024 * 1024)
);

$parse_limit = new ReflectionMethod(Product_Section_Guard::class, 'parse_memory_limit');
$parse_limit->setAccessible(true);

Tests::same('پارسِ "256M"', $parse_limit->invoke(null, '256M'), 256 * 1024 * 1024);
Tests::same('پارسِ "1G"', $parse_limit->invoke(null, '1G'), 1024 * 1024 * 1024);
Tests::same('پارسِ "512K"', $parse_limit->invoke(null, '512K'), 512 * 1024);
Tests::same('پارسِ "-1" یعنی نامحدود', $parse_limit->invoke(null, '-1'), -1);

Tests::group('نگهبانِ سکشن › تشخیصِ آیفریمِ پیش‌نمایشِ ادیتور (elementor-preview)');

/*
 * آیفریمِ پیش‌نمایشِ ادیتور نه ‎is_admin()‎ است نه ‎wp_doing_ajax()‎ — یک
 * لودِ عادیِ فرانتِ همین صفحهٔ محصول است. تنها نشانهٔ قابلِ‌اتکا (بدونِ
 * وابستگی به initialize‌شدنِ آبجکتِ داخلیِ المنتور) پارامترِ کوئریِ
 * ‎elementor-preview‎ است که خودِ المنتور رویِ src آیفریم می‌گذارد.
 */
$is_editing = new ReflectionMethod(Product_Section_Guard::class, 'is_elementor_editing');
$is_editing->setAccessible(true);

$original_get = $_GET;

$_GET['elementor-preview'] = '123';
Tests::ok(
    'با elementor-preview در کوئری‌استرینگ، بدونِ نیاز به آبجکتِ المنتور، پیش‌نمایش تشخیص داده می‌شود',
    true === $is_editing->invoke(null)
);

unset($_GET['elementor-preview']);
Tests::ok(
    'بدونِ elementor-preview و بدونِ کلاسِ المنتور، پیش‌نمایش تشخیص داده نمی‌شود',
    false === $is_editing->invoke(null)
);

$_GET = $original_get;
