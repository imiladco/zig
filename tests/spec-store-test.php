<?php
/**
 * ذخیره‌سازیِ قالب‌های مشخصات فنی.
 *
 * خواهرِ ‎schema-store-test.php‎؛ همان بی‌اعتمادی به هرچه از دیتابیس
 * می‌آید، برای یک ساختارِ دوسطحی به‌جای فهرستِ تخت.
 */

require_once __DIR__ . '/bootstrap.php';

$root = dirname(__DIR__);

require_once $root . '/includes/spec-schema.php';
require_once $root . '/includes/spec-store.php';

use Zig3d_Widgets\Spec_Schema;
use Zig3d_Widgets\Spec_Store;

/* ==========================================================================
 * قالب‌های مشترک
 * ======================================================================= */

Tests::group('ذخیره‌سازیِ مشخصات › قالب‌ها');

$clean = Spec_Store::sanitize_schemas([
    'cnc' => [
        'label'  => 'دستگاه CNC',
        'groups' => [
            ['label' => 'سیستم ماشین‌کاری', 'items' => [['source' => 'weight']]],
        ],
    ],
    'Laser Cutters!' => [
        'label'  => 'برش لیزری',
        'groups' => [
            ['label' => 'قدرت', 'items' => [['source' => 'dimensions']]],
        ],
    ],
]);

Tests::same('نامِ قالب به شکلِ امن درمی‌آید', array_keys($clean), ['cnc', 'lasercutters']);
Tests::same('برچسب نگه داشته می‌شود', $clean['cnc']['label'], 'دستگاه CNC');
Tests::same('گروه‌ها پاک‌سازی می‌شوند', count($clean['cnc']['groups']), 1);

Tests::same(
    'قالبِ بی‌گروهِ معتبر ذخیره نمی‌شود',
    Spec_Store::sanitize_schemas(['empty' => ['label' => 'خالی', 'groups' => []]]),
    []
);

Tests::same(
    'قالبی که همهٔ گروه‌هایش بی‌آیتم‌اند هم ذخیره نمی‌شود',
    Spec_Store::sanitize_schemas(['x' => ['groups' => [['label' => 'گ', 'items' => []]]]]),
    []
);

Tests::same(
    'برچسبِ خالی، نامِ قالب را می‌گیرد',
    Spec_Store::sanitize_schemas(['cnc' => ['groups' => [['items' => [['source' => 'weight']]]]]])['cnc']['label'],
    'cnc'
);

Tests::same(
    'تگ از برچسب پاک می‌شود',
    Spec_Store::sanitize_schemas([
        'x' => ['label' => '<script>bad</script>CNC', 'groups' => [['items' => [['source' => 'weight']]]]],
    ])['x']['label'],
    'CNC'
);

Tests::same('نامِ بی‌اعتبار کنار می‌رود', Spec_Store::sanitize_schemas(['!!!' => ['groups' => []]]), []);
Tests::same('مقدارِ غیرآرایه کنار می‌رود', Spec_Store::sanitize_schemas(['x' => 'string']), []);

/* ==========================================================================
 * اتصالِ دسته
 * ======================================================================= */

Tests::group('ذخیره‌سازیِ مشخصات › اتصالِ دسته');

Tests::same(
    'ورودیِ خالی، اتصالِ پیش‌فرض می‌دهد',
    Spec_Store::sanitize_binding([]),
    ['mode' => Spec_Schema::MODE_NONE, 'schema' => '']
);

Tests::same(
    'حالتِ ناشناخته به «بدونِ گروه‌بندی» برمی‌گردد',
    Spec_Store::sanitize_binding(['mode' => 'ghost'])['mode'],
    Spec_Schema::MODE_NONE
);

Tests::same(
    'حالتِ قالب شناخته می‌شود',
    Spec_Store::sanitize_binding(['mode' => 'schema', 'schema' => 'CNC'])['schema'],
    'cnc'
);

/*
 * بدونِ لایهٔ بازنویسی — کلیدِ اضافه‌ای که کسی بفرستد باید بی‌اثر بماند،
 * نه اینکه در اتصالِ ذخیره‌شده سر دربیاورد.
 */
Tests::same(
    'کلیدِ اضافه (مثلاً overrides) نادیده گرفته می‌شود',
    Spec_Store::sanitize_binding(['mode' => 'schema', 'schema' => 'cnc', 'overrides' => ['x' => 'y']]),
    ['mode' => Spec_Schema::MODE_SCHEMA, 'schema' => 'cnc']
);

/* ==========================================================================
 * چسبیدن به لایهٔ تصمیم
 * ======================================================================= */

Tests::group('ذخیره‌سازیِ مشخصات › اتصال به تصمیم');

$resolved = Spec_Schema::resolve(
    Spec_Store::sanitize_binding(['mode' => 'schema', 'schema' => 'cnc']),
    Spec_Store::sanitize_schemas([
        'cnc' => [
            'label'  => 'دستگاه CNC',
            'groups' => [['label' => 'سیستم ماشین‌کاری', 'items' => [['source' => 'weight']]]],
        ],
    ])
);

Tests::same('قالب اعمال می‌شود', count($resolved['groups']), 1);
Tests::same('و هشداری در کار نیست', $resolved['notes'], []);
