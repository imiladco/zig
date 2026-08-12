<?php
/**
 * قالبِ گروه‌بندیِ مشخصاتِ فنیِ هر دسته.
 *
 * برخلافِ ‎Filter_Schema‎، اینجا هیچ لایهٔ بازنویسی‌ای نیست — فقط دو حالت:
 * بدونِ گروه‌بندی، یا یک قالبِ مشخص. چیزی که این فایل محافظت می‌کند این
 * است که آن سادگی دست‌نخورده بماند و سقوطِ «قالبِ حذف‌شده» بی‌صدا نباشد.
 */

require_once __DIR__ . '/bootstrap.php';

$root = dirname(__DIR__);

require_once $root . '/includes/spec-schema.php';

use Zig3d_Widgets\Spec_Schema;

$schemas = [
    'cnc' => [
        'label'  => 'دستگاه CNC',
        'groups' => [
            [
                'label' => 'سیستم ماشین‌کاری',
                'items' => [
                    ['source' => 'attribute', 'attribute' => 'pa_axis-count', 'label' => 'تعداد محور'],
                    ['source' => 'custom_meta', 'meta_key' => 'spindle_rpm', 'label' => 'حداکثر دور اسپیندل'],
                ],
            ],
            [
                'label' => 'ابعاد و زیرساخت',
                'items' => [
                    ['source' => 'dimensions'],
                ],
            ],
        ],
    ],
];

/* ==========================================================================
 * حل نهایی
 * ======================================================================= */

Tests::group('مشخصات › حل نهایی');

Tests::same(
    'بدونِ اتصال یعنی بدونِ گروه‌بندی',
    Spec_Schema::resolve([], $schemas)['mode'],
    Spec_Schema::MODE_NONE
);

Tests::same(
    'حالتِ بدونِ گروه‌بندی فهرستِ خالی می‌دهد',
    Spec_Schema::resolve(['mode' => 'none'], $schemas)['groups'],
    []
);

$bound = Spec_Schema::resolve(['mode' => 'schema', 'schema' => 'cnc'], $schemas);

Tests::same('گروه‌ها از قالب می‌آیند', count($bound['groups']), 2);
Tests::same('نامِ قالب ثبت می‌شود', $bound['schema'], 'cnc');
Tests::same('بدونِ هشدار', $bound['notes'], []);
Tests::same('عنوانِ اولین گروه', $bound['groups'][0]['label'], 'سیستم ماشین‌کاری');

/*
 * قالبی که پاک شده ولی ارجاعش مانده. سقوط به «بدونِ گروه‌بندی» عمدی است،
 * ولی بی‌صدا نه — وگرنه مدیر سال‌ها فکر می‌کند قالبش هنوز اعمال می‌شود.
 */
$missing = Spec_Schema::resolve(['mode' => 'schema', 'schema' => 'gone'], $schemas);

Tests::same('قالبِ ناموجود به «بدونِ گروه‌بندی» سقوط می‌کند', $missing['mode'], Spec_Schema::MODE_NONE);
Tests::same('فهرست خالی می‌ماند', $missing['groups'], []);
Tests::same('ولی بی‌صدا نه', count($missing['notes']), 1);
Tests::keeps('و اسمِ قالب در هشدار می‌آید', $missing['notes'][0], 'gone');

/* ==========================================================================
 * پاک‌سازیِ آیتم
 * ======================================================================= */

Tests::group('مشخصات › پاک‌سازیِ آیتم');

Tests::same(
    'ویژگیِ سراسری معتبر می‌ماند',
    Spec_Schema::sanitize_item(['source' => 'attribute', 'attribute' => 'pa_brand'])['attribute'],
    'pa_brand'
);

Tests::same(
    'ویژگیِ سفارشیِ با نام معتبر می‌ماند',
    Spec_Schema::sanitize_item(['source' => 'attribute', 'attribute' => 'custom', 'custom_attribute' => 'رنگ'])['custom_attribute'],
    'رنگ'
);

Tests::same(
    'ویژگیِ سفارشیِ بی‌نام هیچی نیست',
    Spec_Schema::sanitize_item(['source' => 'attribute', 'attribute' => 'custom', 'custom_attribute' => '  ']),
    null
);

Tests::same(
    'مبدأِ نامعتبر به ویژگی برمی‌گردد',
    Spec_Schema::sanitize_item(['source' => 'ghost', 'attribute' => 'pa_brand'])['source'],
    'attribute'
);

Tests::same(
    'متایِ بی‌کلید هیچی نیست',
    Spec_Schema::sanitize_item(['source' => 'custom_meta', 'meta_key' => '']),
    null
);

Tests::same(
    'متایِ با کلید می‌ماند',
    Spec_Schema::sanitize_item(['source' => 'custom_meta', 'meta_key' => 'spindle_rpm'])['meta_key'],
    'spindle_rpm'
);

/*
 * منابعی که سؤالِ اضافه نمی‌پرسند (دسته/برچسب/SKU/امتیاز/موجودی/وزن/ابعاد)
 * همیشه معنا دارند — چیزی برای اعتبارسنجیِ بیشتر نیست.
 */
foreach (['category', 'tag', 'sku', 'rating', 'stock', 'weight', 'dimensions'] as $source) {
    Tests::ok(
        "منبعِ «{$source}» بدونِ فیلدِ دیگری هم می‌ماند",
        null !== Spec_Schema::sanitize_item(['source' => $source])
    );
}

Tests::same(
    'برچسبِ سفارشی، تگ را از دست می‌دهد نه متن را',
    Spec_Schema::sanitize_item(['source' => 'dimensions', 'label' => '<b>ابعاد</b> کلی'])['label'],
    'ابعاد کلی'
);

/* ==========================================================================
 * پاک‌سازیِ گروه‌ها
 * ======================================================================= */

Tests::group('مشخصات › پاک‌سازیِ گروه‌ها');

Tests::same(
    'گروهِ بی‌آیتم کنار می‌رود',
    Spec_Schema::sanitize_groups([['label' => 'خالی', 'items' => []]]),
    []
);

Tests::same(
    'گروهی که همهٔ آیتم‌هایش نامعتبرند هم کنار می‌رود',
    Spec_Schema::sanitize_groups([[
        'label' => 'همه نامعتبر',
        'items' => [['source' => 'custom_meta', 'meta_key' => '']],
    ]]),
    []
);

$clean_groups = Spec_Schema::sanitize_groups([
    [
        'label' => 'سیستم ماشین‌کاری',
        'items' => [
            ['source' => 'attribute', 'attribute' => 'pa_axis-count'],
            ['source' => 'custom_meta', 'meta_key' => ''], // نامعتبر، دور ریخته می‌شود
            ['source' => 'weight'],
        ],
    ],
]);

Tests::same('گروهِ معتبر می‌ماند', count($clean_groups), 1);
Tests::same('آیتمِ نامعتبر از میانِ گروه دور می‌ریزد، نه کلِ گروه', count($clean_groups[0]['items']), 2);

Tests::same(
    'مقدارِ غیرآرایه به‌عنوانِ گروه کنار می‌رود',
    Spec_Schema::sanitize_groups(['not-an-array']),
    []
);
