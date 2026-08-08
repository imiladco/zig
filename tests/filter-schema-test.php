<?php
/**
 * طرح فیلتر هر دسته.
 *
 * چیزی که اینجا محافظت می‌شود، بیشتر از درستیِ خروجی است: مهار پیچیدگی.
 * فقط یک لایه بازنویسی مجاز است و تخت. تستِ «لایهٔ دوم وجود ندارد» را
 * نمی‌شود نوشت، ولی می‌شود شکل خروجی را چنان بست که اضافه‌کردن لایهٔ دوم
 * بدون شکستن این فایل ممکن نباشد.
 */

require_once __DIR__ . '/bootstrap.php';

$root = dirname(__DIR__);

require_once $root . '/includes/query-state.php';
require_once $root . '/includes/facets.php';
require_once $root . '/includes/filter-schema.php';

use Zig3d_Widgets\Facets;
use Zig3d_Widgets\Filter_Schema;

$schemas = [
    'milling' => [
        'label'  => 'فرزکاری',
        'facets' => [
            ['taxonomy' => 'pa_brand'],
            ['taxonomy' => 'pa_milling_type'],
            ['taxonomy' => 'pa_axis_count'],
            ['taxonomy' => 'pa_material'],
        ],
    ],
];

$discovered = ['pa_brand', 'pa_axis_count'];

/* ==========================================================================
 * حالت خودکار
 * ======================================================================= */

Tests::group('طرح › خودکار');

$auto = Filter_Schema::resolve(['mode' => 'auto'], $schemas, $discovered);

Tests::same('فهرست از کشف می‌آید', Filter_Schema::taxonomies($auto['facets']), ['pa_brand', 'pa_axis_count']);
Tests::same('حالت ثبت می‌شود', $auto['mode'], 'auto');
Tests::same('بدون بازنویسی', $auto['overrides'], 0);
Tests::same('و بدون هشدار', $auto['notes'], []);

Tests::same(
    'نبودِ حالت یعنی خودکار',
    Filter_Schema::resolve([], $schemas, $discovered)['mode'],
    'auto'
);

Tests::same(
    'کشفِ کلید⇒مقدار هم پذیرفته می‌شود',
    Filter_Schema::taxonomies(Filter_Schema::resolve([], [], ['pa_brand' => ['label' => 'برند']])['facets']),
    ['pa_brand']
);

/* ==========================================================================
 * حالت طرح مشترک
 * ======================================================================= */

Tests::group('طرح › مشترک');

$shared = Filter_Schema::resolve(['mode' => 'schema', 'schema' => 'milling'], $schemas, $discovered);

Tests::same(
    'فهرست از طرح می‌آید، نه از کشف',
    Filter_Schema::taxonomies($shared['facets']),
    ['pa_brand', 'pa_milling_type', 'pa_axis_count', 'pa_material']
);

/*
 * همین است که «حتی اگر هنوز محصولی این ویژگی را ندارد» را ممکن می‌کند:
 * طرح می‌گوید چه چیزی باشد، کشف فقط می‌گوید چه چیزی هست.
 */
Tests::ok(
    'ویژگی‌ای که هیچ محصولی ندارد هم می‌آید',
    in_array('pa_material', Filter_Schema::taxonomies($shared['facets']), true)
);

Tests::same('نام طرح ثبت می‌شود', $shared['schema'], 'milling');

/*
 * طرحی که پاک شده ولی ارجاعش مانده. سایدبار خالی به بازدیدکننده «فروشگاه
 * خراب است» می‌گوید؛ فهرست خودکار تقریباً همیشه درست است. ولی اگر این
 * سقوط بی‌صدا باشد، مدیر سال‌ها فکر می‌کند طرحش اعمال می‌شود.
 */
$missing = Filter_Schema::resolve(['mode' => 'schema', 'schema' => 'gone'], $schemas, $discovered);

Tests::same('طرح ناموجود به خودکار سقوط می‌کند', $missing['mode'], 'auto');
Tests::same('و همان فهرست کشف را می‌دهد', Filter_Schema::taxonomies($missing['facets']), $discovered);
Tests::same('ولی بی‌صدا نه', count($missing['notes']), 1);
Tests::keeps('و اسم طرح در هشدار می‌آید', $missing['notes'][0], 'gone');

/* ==========================================================================
 * بازنویسی — تنها لایهٔ مجاز
 * ======================================================================= */

Tests::group('طرح › بازنویسی');

$trimmed = Filter_Schema::resolve(
    [
        'mode'      => 'schema',
        'schema'    => 'milling',
        'overrides' => ['pa_material' => ['enabled' => false]],
    ],
    $schemas,
    $discovered
);

Tests::same(
    'گروه خاموش‌شده از فهرست می‌افتد',
    Filter_Schema::taxonomies($trimmed['facets']),
    ['pa_brand', 'pa_milling_type', 'pa_axis_count']
);

Tests::same('و شمرده می‌شود', $trimmed['overrides'], 1);

$pinned = Filter_Schema::resolve(
    [
        'mode'      => 'schema',
        'schema'    => 'milling',
        'overrides' => ['pa_material' => ['show_empty' => true]],
    ],
    $schemas,
    $discovered
);

Tests::ok(
    'نمایشِ گروه خالی قابل روشن‌کردن است',
    $pinned['facets'][3]['show_empty']
);

Tests::ok(
    'و بقیهٔ گروه‌ها دست نمی‌خورند',
    !$pinned['facets'][0]['show_empty']
);

/*
 * بازنویسی روی گروهی که دیگر در طرح نیست. حذفش درست است، ولی سکوت نه:
 * مدیر باید بفهمد تنظیمی دارد که هیچ کاری نمی‌کند.
 */
$stale = Filter_Schema::resolve(
    [
        'mode'      => 'schema',
        'schema'    => 'milling',
        'overrides' => ['pa_ghost' => ['enabled' => false]],
    ],
    $schemas,
    $discovered
);

Tests::same('بازنویسیِ بی‌هدف اثری ندارد', count($stale['facets']), 4);
Tests::same('و به‌عنوان بازنویسی شمرده نمی‌شود', $stale['overrides'], 0);
Tests::same('ولی هشدار می‌دهد', count($stale['notes']), 1);

Tests::same(
    'بازنویسی در حالت خودکار هم کار می‌کند',
    Filter_Schema::taxonomies(
        Filter_Schema::resolve(
            ['mode' => 'auto', 'overrides' => ['pa_brand' => ['enabled' => false]]],
            $schemas,
            $discovered
        )['facets']
    ),
    ['pa_axis_count']
);

/* ==========================================================================
 * پاک‌سازی
 * ======================================================================= */

Tests::group('طرح › پاک‌سازی');

/*
 * یک ویژگی دو بار در سایدبار، علاوه بر زشتی، دو چک‌باکس برای یک انتخاب
 * می‌سازد که وضعیتشان با هم نمی‌خواند.
 */
Tests::same(
    'گروه تکراری یک بار می‌ماند',
    count(Filter_Schema::sanitize_facets([
        ['taxonomy' => 'pa_brand'],
        ['taxonomy' => 'pa_brand'],
    ])),
    1
);

Tests::same(
    'گروه بی‌نام کنار می‌رود',
    Filter_Schema::sanitize_facets([['taxonomy' => '  ']]),
    []
);

Tests::same(
    'رشتهٔ ساده هم به گروه تبدیل می‌شود',
    Filter_Schema::sanitize_facets(['pa_brand'])[0]['taxonomy'],
    'pa_brand'
);

Tests::same(
    'اپراتور پیش‌فرض OR است',
    Filter_Schema::sanitize_facets([['taxonomy' => 'pa_brand']])[0]['operator'],
    Facets::OP_OR
);

/*
 * AND درون گروه، در بیشتر ویژگی‌ها نتیجه را به صفر می‌رساند و کاربر فکر
 * می‌کند فروشگاه خالی است. پس فقط وقتی که صریحاً خواسته شده.
 */
Tests::same(
    'ولی AND هم پذیرفته می‌شود',
    Filter_Schema::sanitize_facets([['taxonomy' => 'pa_fit', 'operator' => 'and']])[0]['operator'],
    Facets::OP_AND
);

/*
 * معنای فست. پیش‌فرض «ویژگی محصول» است و امروز تنها مسیرِ ساخته‌شده.
 *
 * وجود این کلید یک تصمیم رو به آینده است: فیلتری مثل «رنگ موجود» سؤال
 * دیگری می‌پرسد و اگر بی‌سروصدا سوار مسیر محصول شود، ادعای «موجود» دارد و
 * ناموجود هم برمی‌گرداند — با عددی که کاملاً معقول به نظر می‌رسد.
 */
Tests::same(
    'معنای پیش‌فرض، ویژگی محصول است',
    Filter_Schema::sanitize_facets([['taxonomy' => 'pa_brand']])[0]['semantics'],
    Facets::SEMANTICS_PRODUCT
);

Tests::same(
    'معنای موجودیِ گزینه هم قابل بیان است',
    Filter_Schema::sanitize_facets([['taxonomy' => 'pa_color', 'semantics' => 'variation']])[0]['semantics'],
    Facets::SEMANTICS_VARIATION
);

Tests::same(
    'مقدار ناشناخته به ویژگی محصول برمی‌گردد',
    Filter_Schema::sanitize_facets([['taxonomy' => 'pa_x', 'semantics' => 'ghost']])[0]['semantics'],
    Facets::SEMANTICS_PRODUCT
);

Tests::same(
    'اپراتور نامعتبر به OR برمی‌گردد',
    Filter_Schema::sanitize_facets([['taxonomy' => 'pa_x', 'operator' => 'xor']])[0]['operator'],
    Facets::OP_OR
);

Tests::same(
    'نگاشت اپراتورها برای tax_query',
    Filter_Schema::operators(Filter_Schema::sanitize_facets([
        ['taxonomy' => 'pa_brand'],
        ['taxonomy' => 'pa_fit', 'operator' => 'and'],
    ])),
    ['pa_brand' => Facets::OP_OR, 'pa_fit' => Facets::OP_AND]
);
