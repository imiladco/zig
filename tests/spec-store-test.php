<?php
/**
 * ذخیره‌سازیِ کتابخانهٔ گروه‌هایِ مشخصاتِ فنی.
 *
 * هرچه از دیتابیس می‌آید مشکوک است: گروهی که پاک شده ولی دسته‌ای هنوز به
 * نامش ارجاع دارد، فهرستِ نامِ گروهی که کسی دستی خراب کرده، تکراری در
 * فهرستِ یک دسته. هیچ‌کدام خطا نمی‌دهند؛ فقط صفحهٔ محصول یک گروه کم دارد.
 */

require_once __DIR__ . '/bootstrap.php';

$root = dirname(__DIR__);

require_once $root . '/includes/spec-group.php';
require_once $root . '/includes/spec-store.php';

use Zig3d_Widgets\Spec_Store;

/* ==========================================================================
 * کتابخانهٔ گروه‌ها
 * ======================================================================= */

Tests::group('ذخیره‌سازیِ مشخصات › کتابخانه');

$clean = Spec_Store::sanitize_groups([
    'machining' => [
        'label' => 'سیستم ماشین‌کاری',
        'items' => [['source' => 'weight']],
    ],
    'Dimensions & Infra!' => [
        'label' => 'ابعاد و زیرساخت',
        'items' => [['source' => 'dimensions']],
    ],
]);

Tests::same('نامِ گروه به شکلِ امن درمی‌آید', array_keys($clean), ['machining', 'dimensionsinfra']);
Tests::same('برچسب نگه داشته می‌شود', $clean['machining']['label'], 'سیستم ماشین‌کاری');

Tests::same(
    'گروهِ بی‌آیتمِ معتبر ذخیره نمی‌شود',
    Spec_Store::sanitize_groups(['empty' => ['label' => 'خالی', 'items' => []]]),
    []
);

Tests::same('نامِ بی‌اعتبار کنار می‌رود', Spec_Store::sanitize_groups(['!!!' => ['items' => [['source' => 'weight']]]]), []);
Tests::same('مقدارِ غیرآرایه کنار می‌رود', Spec_Store::sanitize_groups(['x' => 'string']), []);

Tests::same(
    'تگ از برچسب پاک می‌شود',
    Spec_Store::sanitize_groups([
        'x' => ['label' => '<script>bad</script>CNC', 'items' => [['source' => 'weight']]],
    ])['x']['label'],
    'CNC'
);

/* ==========================================================================
 * فهرستِ گروه‌هایِ یک دسته
 * ======================================================================= */

Tests::group('ذخیره‌سازیِ مشخصات › فهرستِ دسته');

Tests::same(
    'ترتیب حفظ می‌شود',
    Spec_Store::sanitize_category_groups(['b', 'a', 'c']),
    ['b', 'a', 'c']
);

Tests::same(
    'تکراری فقط یک بار می‌ماند، در اولین جایگاهش',
    Spec_Store::sanitize_category_groups(['a', 'b', 'a']),
    ['a', 'b']
);

Tests::same('نامِ خالی دور ریخته می‌شود', Spec_Store::sanitize_category_groups(['a', '', '  ']), ['a']);

Tests::same(
    'نامِ ناامن هم پاک می‌شود',
    Spec_Store::sanitize_category_groups(['Machining!'])[0],
    'machining'
);

/* ==========================================================================
 * حلِ نهایی
 * ======================================================================= */

Tests::group('ذخیره‌سازیِ مشخصات › حلِ نهایی');

/*
 * چسبیدنِ کتابخانه و فهرستِ دسته باید مستقیم آزمون‌پذیر باشد، بدونِ
 * دیتابیس — پس هر دو تابع را روی همان کش‌های استاتیک صدا نمی‌زنیم؛ به‌جایش
 * می‌سنجیم که پاک‌سازیِ هرکدام جدا درست کار می‌کند و ترکیبشان در
 * ‎resolve()‎ (که خودش get_option/get_term_meta لازم دارد) در تستِ
 * یکپارچگی، نه اینجا، سنجیده می‌شود.
 */
Tests::same(
    'گروهِ گم‌شده در پاک‌سازیِ فهرستِ دسته حذف نمی‌شود — فقط در resolve',
    Spec_Store::sanitize_category_groups(['gone', 'machining']),
    ['gone', 'machining']
);
