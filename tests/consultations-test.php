<?php
/**
 * پاک‌سازیِ ورودیِ فرمِ مشاوره.
 *
 * همان مرزی که ‎search-query-test.php‎ رعایت می‌کند: هرچه *تصمیم* است
 * (نام/شماره خالی یعنی چه، سقفِ طول کجاست) بدونِ دیتابیس سنجیده می‌شود؛
 * هرچه *اجرا* است (‎maybe_upgrade()‎/‎insert()‎/‎page()‎ که ‎$wpdb‎ صدا
 * می‌زنند) دست‌نخورده می‌ماند — این افزونه هیچ استابِ ‎$wpdb‎ ندارد و این
 * فایل هم یکی نمی‌سازد.
 */

require_once __DIR__ . '/bootstrap.php';

$root = dirname(__DIR__);

require_once $root . '/includes/consultations.php';

use Zig3d_Widgets\Consultations;

/* ==========================================================================
 * sanitize_submission
 * ======================================================================= */

Tests::group('مشاوره › پاک‌سازیِ ورودی');

Tests::same(
    'نام و شماره پر، توضیحات خالی',
    Consultations::sanitize_submission(['name' => 'میلاد', 'phone' => '09121234567', 'message' => '']),
    ['name' => 'میلاد', 'phone' => '09121234567', 'message' => '']
);

Tests::same(
    'دورِ فاصله‌ها بریده می‌شود',
    Consultations::sanitize_submission(['name' => '  میلاد  ', 'phone' => ' 0912 ', 'message' => '  سلام  ']),
    ['name' => 'میلاد', 'phone' => '0912', 'message' => 'سلام']
);

Tests::same('نامِ خالی ⇒ null', Consultations::sanitize_submission(['name' => '', 'phone' => '0912']), null);
Tests::same('شمارهٔ خالی ⇒ null', Consultations::sanitize_submission(['name' => 'میلاد', 'phone' => '']), null);
Tests::same(
    'نامِ فقط‌فاصله هم خالی حساب می‌شود',
    Consultations::sanitize_submission(['name' => '   ', 'phone' => '0912']),
    null
);
Tests::same('بدونِ هیچ کلیدی ⇒ null', Consultations::sanitize_submission([]), null);

Tests::same(
    'برچسبِ HTML از نام/توضیحات حذف می‌شود',
    Consultations::sanitize_submission(['name' => '<b>میلاد</b>', 'phone' => '0912', 'message' => '<script>x</script>سلام'])['name'],
    'میلاد'
);

$long_name = str_repeat('ا', 300);
Tests::same(
    'نامِ خیلی بلند به سقف بریده می‌شود',
    mb_strlen(Consultations::sanitize_submission(['name' => $long_name, 'phone' => '0912'])['name']),
    190
);

$long_message = str_repeat('ب', 3000);
Tests::same(
    'توضیحاتِ خیلی بلند هم به سقفِ خودش بریده می‌شود',
    mb_strlen(Consultations::sanitize_submission(['name' => 'میلاد', 'phone' => '0912', 'message' => $long_message])['message']),
    2000
);

/* ==========================================================================
 * sanitize_source
 * ======================================================================= */

Tests::group('مشاوره › پاک‌سازیِ منبع');

Tests::same(
    'منبعِ کامل',
    Consultations::sanitize_source([
        'source_url'   => 'https://zig3d.com/product/x',
        'source_title' => 'محصولِ x',
        'product_id'   => '15277',
        'product_name' => 'محصولِ x',
    ]),
    [
        'url'          => 'https://zig3d.com/product/x',
        'title'        => 'محصولِ x',
        'product_id'   => 15277,
        'product_name' => 'محصولِ x',
    ]
);

Tests::same(
    'بدونِ هیچ کلیدی، آرایهٔ خالیِ امن',
    Consultations::sanitize_source([]),
    ['url' => '', 'title' => '', 'product_id' => 0, 'product_name' => '']
);

Tests::same(
    'شناسهٔ محصولِ منفی هم عددِ مثبتِ نگاشته‌شده می‌گیرد (absint)',
    Consultations::sanitize_source(['product_id' => -5])['product_id'],
    5
);

Tests::same(
    'شناسهٔ محصولِ رشته‌ایِ غیرعددی به صفر می‌رسد',
    Consultations::sanitize_source(['product_id' => 'abc'])['product_id'],
    0
);

/* ==========================================================================
 * sanitize_ids
 * ======================================================================= */

Tests::group('مشاوره › پاک‌سازیِ شناسه‌هایِ حذفِ گروهی');

Tests::same('اعدادِ رشته‌ای هم عدد می‌شوند', Consultations::sanitize_ids(['3', '5', '1']), [3, 5, 1]);
Tests::same('تکراری‌ها یک‌بار می‌مانند', Consultations::sanitize_ids(['4', '4', '4']), [4]);
Tests::same(
    'صفر/غیرعددی حذف می‌شوند؛ منفی مطلق‌شان می‌ماند (absint)',
    Consultations::sanitize_ids(['0', '-3', 'abc', '7']),
    [3, 7]
);
Tests::same('آرایهٔ خالی ⇒ آرایهٔ خالی', Consultations::sanitize_ids([]), []);
