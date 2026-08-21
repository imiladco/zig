<?php
/**
 * ذخیره‌سازی طرح فیلتر.
 *
 * هرچه از دیتابیس می‌آید مشکوک است: ممکن است از نسخهٔ قدیمی افزونه مانده
 * باشد، ممکن است کسی دستی عوضش کرده باشد، ممکن است طرحی که به آن ارجاع
 * دارد سال‌ها پیش پاک شده باشد. هیچ‌کدام از این‌ها خطا نمی‌دهند؛ فقط
 * سایدبار یک چیز دیگری نشان می‌دهد.
 */

require_once __DIR__ . '/bootstrap.php';

$root = dirname(__DIR__);

require_once $root . '/includes/query-state.php';
require_once $root . '/includes/facets.php';
require_once $root . '/includes/filter-schema.php';
require_once $root . '/includes/schema-store.php';

use Zig3d_Widgets\Facets;
use Zig3d_Widgets\Filter_Schema;
use Zig3d_Widgets\Schema_Store;

/* ==========================================================================
 * طرح‌های مشترک
 * ======================================================================= */

Tests::group('ذخیره‌سازی › طرح‌های مشترک');

$clean = Schema_Store::sanitize_schemas([
    'milling' => [
        'label'  => 'فرزکاری',
        'facets' => [['taxonomy' => 'pa_brand'], ['taxonomy' => 'pa_axis']],
    ],
    'Printers 3D!' => [
        'label'  => 'پرینترها',
        'facets' => [['taxonomy' => 'pa_tech']],
    ],
]);

Tests::same('نام طرح به شکل امن درمی‌آید', array_keys($clean), ['milling', 'printers3d']);
Tests::same('برچسب نگه داشته می‌شود', $clean['milling']['label'], 'فرزکاری');
Tests::same('گروه‌ها پاک‌سازی می‌شوند', count($clean['milling']['facets']), 2);

/*
 * طرحی بدون هیچ گروهی، فقط یک گزینهٔ دیگر در دراپ‌داون مدیر است که
 * انتخابش سایدبار را خالی می‌کند — و بعد ساعت‌ها دنبال دلیلش می‌گردد.
 */
Tests::same(
    'طرح بی‌گروه ذخیره نمی‌شود',
    Schema_Store::sanitize_schemas(['empty' => ['label' => 'خالی', 'facets' => []]]),
    []
);

Tests::same(
    'برچسب خالی، نام طرح را می‌گیرد',
    Schema_Store::sanitize_schemas(['milling' => ['facets' => ['pa_brand']]])['milling']['label'],
    'milling'
);

// اسکریپت با محتوایش می‌رود، نه فقط تگش — همان کاری که خودِ وردپرس می‌کند
Tests::same(
    'تگ از برچسب پاک می‌شود',
    Schema_Store::sanitize_schemas([
        'x' => ['label' => '<script>bad</script>فرز', 'facets' => ['pa_brand']],
    ])['x']['label'],
    'فرز'
);

Tests::same(
    'و تگ ساده هم می‌رود ولی متنش می‌ماند',
    Schema_Store::sanitize_schemas([
        'y' => ['label' => '<b>فرز</b>کاری', 'facets' => ['pa_brand']],
    ])['y']['label'],
    'فرزکاری'
);

Tests::same('نام بی‌اعتبار کنار می‌رود', Schema_Store::sanitize_schemas(['!!!' => ['facets' => ['pa_x']]]), []);
Tests::same('مقدار غیرآرایه کنار می‌رود', Schema_Store::sanitize_schemas(['x' => 'string']), []);

/* ==========================================================================
 * اتصال دسته
 * ======================================================================= */

Tests::group('ذخیره‌سازی › اتصال دسته');

Tests::same(
    'ورودی خالی، اتصال پیش‌فرض می‌دهد',
    Schema_Store::sanitize_binding([]),
    ['mode' => Filter_Schema::MODE_AUTO, 'schema' => '', 'overrides' => []]
);

Tests::same(
    'حالت ناشناخته به خودکار برمی‌گردد',
    Schema_Store::sanitize_binding(['mode' => 'ghost'])['mode'],
    Filter_Schema::MODE_AUTO
);

Tests::same(
    'حالت طرح شناخته می‌شود',
    Schema_Store::sanitize_binding(['mode' => 'schema', 'schema' => 'Milling'])['schema'],
    'milling'
);

/* ==========================================================================
 * بازنویسی‌ها
 * ======================================================================= */

Tests::group('ذخیره‌سازی › بازنویسی‌ها');

$binding = Schema_Store::sanitize_binding([
    'overrides' => [
        'pa_material' => ['enabled' => false],
        'pa_coating'  => ['show_empty' => true],
        'pa_brand'    => ['enabled' => true],
        'pa_axis'     => ['enabled' => true, 'show_empty' => false],
    ],
]);

Tests::same('خاموش‌کردن ثبت می‌شود', $binding['overrides']['pa_material'], ['enabled' => false]);
Tests::same('نمایش گروه خالی ثبت می‌شود', $binding['overrides']['pa_coating'], ['show_empty' => true]);

/*
 * بازنویسیِ بی‌اثر دور ریخته می‌شود. نگه‌داشتنش بی‌ضرر به نظر می‌رسد ولی
 * شمارندهٔ «این دسته N بازنویسی دارد» را دروغ می‌کند — و آن شمارنده تنها
 * چیزی است که به مدیر می‌گوید فهرستش دست‌کاری شده.
 */
Tests::ok('بازنویسیِ بی‌اثر ذخیره نمی‌شود', !isset($binding['overrides']['pa_brand']));

/*
 * «افزوده» استثناست: حتی وقتی تنها کلید است هم معنا دارد، چون یعنی گروهی
 * که در فهرست پایه نیست باید بیاید.
 */
Tests::same(
    'نشانِ افزودن حتی به‌تنهایی هم می‌ماند',
    Schema_Store::sanitize_binding(['overrides' => ['pa_new' => ['added' => true]]])['overrides'],
    ['pa_new' => ['added' => true]]
);

Tests::same(
    'و همراه نمایشِ خالی هم می‌آید',
    Schema_Store::sanitize_binding([
        'overrides' => ['pa_new' => ['added' => true, 'show_empty' => true]],
    ])['overrides']['pa_new'],
    ['added' => true, 'show_empty' => true]
);
Tests::ok('حتی وقتی هر دو کلید هست ولی هر دو پیش‌فرض‌اند', !isset($binding['overrides']['pa_axis']));
Tests::same('پس فقط دو بازنویسی می‌ماند', count($binding['overrides']), 2);

// ترتیب قطعی، تا مقایسهٔ دو اتصال بی‌معنا نشود
Tests::same('ترتیب بازنویسی‌ها قطعی است', array_keys($binding['overrides']), ['pa_coating', 'pa_material']);

Tests::same(
    'قاعدهٔ غیرآرایه کنار می‌رود',
    Schema_Store::sanitize_binding(['overrides' => ['pa_x' => 'off']])['overrides'],
    []
);

/* ==========================================================================
 * چسبیدن به لایهٔ تصمیم
 * ======================================================================= */

Tests::group('ذخیره‌سازی › اتصال به تصمیم');

/*
 * خروجی پاک‌شدهٔ این کلاس باید مستقیم به ‎Filter_Schema‎ خورانده شود. اگر
 * شکلشان از هم جدا بیفتد، هیچ خطایی نمی‌آید — فقط بازنویسی‌ها بی‌اثر
 * می‌مانند.
 */
$resolved = Filter_Schema::resolve(
    Schema_Store::sanitize_binding([
        'mode'      => 'schema',
        'schema'    => 'milling',
        'overrides' => ['pa_material' => ['enabled' => false]],
    ]),
    Schema_Store::sanitize_schemas([
        'milling' => [
            'label'  => 'فرزکاری',
            'facets' => ['pa_brand', 'pa_material'],
        ],
    ]),
    []
);

Tests::same('طرح اعمال می‌شود', Filter_Schema::taxonomies($resolved['facets']), ['pa_brand']);
Tests::same('و بازنویسی شمرده می‌شود', $resolved['overrides'], 1);
Tests::same('و هشداری در کار نیست', $resolved['notes'], []);

/* ==========================================================================
 * ورودیِ فرمِ صفحهٔ طرح‌ها
 * ======================================================================= */

Tests::group('ذخیره‌سازی › ورودی فرم');

/*
 * فرم برای هر گروه یک عدد ترتیب می‌فرستد. آن عدد فقط برای مرتب‌سازی است و
 * نباید در چیزی که ذخیره می‌شود بماند — وگرنه شکل گروه‌ها بین «ساخته‌شده از
 * فرم» و «ساخته‌شده از کد» فرق می‌کرد و مقایسه‌شان بی‌معنا می‌شد.
 */
$from_form = Schema_Store::sanitize_schemas([
    'milling' => [
        'label'  => 'فرزکاری',
        'facets' => [
            ['taxonomy' => 'pa_brand', 'operator' => 'or', 'show_empty' => false, 'order' => 2, '_seq' => 0],
            ['taxonomy' => 'pa_axis', 'operator' => 'and', 'show_empty' => true, 'order' => 1, '_seq' => 1],
        ],
    ],
]);

Tests::same(
    'کلیدهای کمکیِ فرم وارد ذخیره‌سازی نمی‌شوند',
    array_keys($from_form['milling']['facets'][0]),
    ['taxonomy', 'operator', 'show_empty', 'semantics']
);

Tests::same('ترتیبِ داده‌شده حفظ می‌شود', $from_form['milling']['facets'][0]['taxonomy'], 'pa_brand');
Tests::same('اپراتور از فرم می‌آید', $from_form['milling']['facets'][1]['operator'], Facets::OP_AND);
Tests::ok('و نمایشِ خالی هم', $from_form['milling']['facets'][1]['show_empty']);

Tests::same(
    'معنای فست هم از ذخیره‌سازی رد می‌شود',
    Schema_Store::sanitize_schemas([
        'x' => ['facets' => [['taxonomy' => 'pa_color', 'semantics' => 'variation']]],
    ])['x']['facets'][0]['semantics'],
    Facets::SEMANTICS_VARIATION
);
