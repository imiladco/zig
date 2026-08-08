<?php
/**
 * شمارش فست.
 *
 * این فایل عمداً روی سه چیز تمرکز دارد که هر سه «خروجی درست به نظر می‌رسد
 * ولی غلط است» می‌سازند:
 *
 *   ۱. خودحذف‌کن نبودن ⇒ بعد از اولین انتخاب، بقیهٔ گزینه‌های همان گروه صفر
 *      می‌شوند و چندانتخابی عملاً می‌میرد.
 *   ۲. کلید کشی که قید خودحذف‌شده را در خود دارد ⇒ کش کار می‌کند ولی هیچ‌وقت
 *      اصابت نمی‌کند.
 *   ۳. غیرفعال‌کردنِ گزینهٔ انتخاب‌شده‌ای که صفر شده ⇒ کاربر در بن‌بست
 *      می‌ماند و راهی برای برداشتن تیک ندارد.
 *
 * هیچ‌کدام از این سه، در خروجی HTML چیزی نشان نمی‌دهند.
 */

require_once __DIR__ . '/bootstrap.php';

$root = dirname(__DIR__);

require_once $root . '/includes/query-state.php';
require_once $root . '/includes/facets.php';

use Zig3d_Widgets\Facets;
use Zig3d_Widgets\Query_State;

/* ==========================================================================
 * خودحذف‌کنی
 * ======================================================================= */

Tests::group('فست › خودحذف‌کنی');

$state = Query_State::create([
    'pa_brand'   => ['up3d'],
    'pa_axis'    => ['5-axis'],
    'pa_milling' => ['dry'],
]);

Tests::same(
    'قید خودِ گروه در شمارش همان گروه نیست',
    array_keys(Facets::constraints_for($state, 'pa_brand')->filters()),
    ['pa_axis', 'pa_milling']
);

Tests::same(
    'ولی قید گروه‌های دیگر می‌ماند',
    Facets::constraints_for($state, 'pa_brand')->selected('pa_axis'),
    ['5-axis']
);

Tests::same(
    'برای گروه دیگر، همان قاعده با گروه دیگر',
    array_keys(Facets::constraints_for($state, 'pa_axis')->filters()),
    ['pa_brand', 'pa_milling']
);

/* ==========================================================================
 * کلید کش
 * ======================================================================= */

Tests::group('فست › کلید کش');

/*
 * دو کاربر با برندهای متفاوت ولی بقیهٔ فیلترها یکسان، برای گروهِ برند دقیقاً
 * یک عدد می‌گیرند — چون برند در محاسبهٔ برند حذف می‌شود. اگر کلید کش از
 * وضعیت خام ساخته می‌شد، این دو ورودیِ جدا می‌گرفتند: کش دو برابر بزرگ‌تر
 * برای همان یک جواب.
 */
$one = Query_State::create(['pa_brand' => ['up3d'], 'pa_axis' => ['5-axis']]);
$two = Query_State::create(['pa_brand' => ['vhf'],  'pa_axis' => ['5-axis']]);

Tests::same(
    'تفاوت در گروهِ خودحذف‌شده، کلید را عوض نمی‌کند',
    Facets::cache_key('cat-12', 'pa_brand', $one),
    Facets::cache_key('cat-12', 'pa_brand', $two)
);

Tests::ok(
    'ولی تفاوت در گروه دیگر، کلید را عوض می‌کند',
    Facets::cache_key('cat-12', 'pa_brand', $one)
        !== Facets::cache_key('cat-12', 'pa_brand', Query_State::create(['pa_axis' => ['3-axis']]))
);

Tests::ok(
    'گروه‌های متفاوت، کلید متفاوت',
    Facets::cache_key('cat-12', 'pa_brand', $one) !== Facets::cache_key('cat-12', 'pa_axis', $one)
);

/*
 * دستهٔ متفاوت یعنی کوئری پایهٔ متفاوت. بدون این، شمارشِ دستهٔ «فرزها» روی
 * دستهٔ «پرینترها» سرو می‌شد — عددهایی که هیچ ربطی به فهرست زیرشان ندارند.
 */
Tests::ok(
    'زمینهٔ متفاوت (دسته/جست‌وجو)، کلید متفاوت',
    Facets::cache_key('cat-12', 'pa_brand', $one) !== Facets::cache_key('cat-99', 'pa_brand', $one)
);

Tests::ok(
    'صفحه روی کلید اثر ندارد',
    Facets::cache_key('c', 'pa_brand', $one) === Facets::cache_key('c', 'pa_brand', $one->with_page(5))
);

/* ==========================================================================
 * وضعیت گزینه‌ها
 * ======================================================================= */

Tests::group('فست › وضعیت گزینه‌ها');

$terms = [
    ['slug' => 'up3d',   'label' => 'UP3D',   'term_id' => 11],
    ['slug' => 'vhf',    'label' => 'VHF',    'term_id' => 12],
    ['slug' => 'aidite', 'label' => 'Aidite', 'term_id' => 13],
];

$options = Facets::options($terms, ['up3d' => 8, 'vhf' => 3, 'aidite' => 0], ['up3d']);

Tests::same('تعداد گزینه‌ها حفظ می‌شود', count($options), 3);
Tests::same('شمارش خوانده می‌شود', $options[0]['count'], 8);
Tests::ok('گزینهٔ انتخاب‌شده علامت می‌خورد', $options[0]['selected']);
Tests::ok('و غیرفعال نیست', !$options[0]['disabled']);
Tests::ok('گزینهٔ پرشمار فعال است', !$options[1]['disabled']);

/*
 * گزینهٔ صفر می‌ماند ولی غیرفعال. حذف‌کردنش ارتفاع سایدبار را با هر درخواست
 * عوض می‌کند و گزینه‌ها زیر دست کاربر جابه‌جا می‌شوند.
 */
Tests::same('گزینهٔ صفر حذف نمی‌شود', $options[2]['slug'], 'aidite');
Tests::ok('ولی غیرفعال می‌شود', $options[2]['disabled']);
Tests::same('و عددش صفرِ واقعی است', $options[2]['count'], 0);

/*
 * مهم‌ترین حالت مرزی: انتخابی که به‌خاطر فیلتر دیگری صفر شده. اگر غیرفعال
 * شود، کاربر نمی‌تواند تیکش را بردارد و تنها راه خروج، ریست‌کردن همه‌چیز
 * است.
 */
$trapped = Facets::options($terms, ['up3d' => 0, 'vhf' => 5, 'aidite' => 2], ['up3d']);

Tests::ok('انتخابِ صفرشده همچنان قابل برداشتن است', !$trapped[0]['disabled']);
Tests::ok('و همچنان انتخاب‌شده نشان داده می‌شود', $trapped[0]['selected']);

/*
 * «عددِ غلط از نبودِ عدد بدتر است»: هر عددی که کنار گزینه می‌نشیند یک وعده
 * است. وقتی شمارش در دسترس نیست، سکوت درست‌تر از حدس است.
 */
$unknown = Facets::options($terms, null, ['up3d']);

Tests::same('شمارشِ در دسترس نبودن، عددی نمی‌سازد', $unknown[0]['count'], null);
Tests::ok('و هیچ گزینه‌ای را غیرفعال نمی‌کند', !$unknown[1]['disabled'] && !$unknown[2]['disabled']);

Tests::same('ترمِ بدون اسلاگ کنار گذاشته می‌شود', count(Facets::options([['label' => 'x']], null, [])), 0);
Tests::same('ترمِ بدون برچسب، اسلاگ را برچسب می‌کند', Facets::options([['slug' => 'x']], null, [])[0]['label'], 'x');

/* ==========================================================================
 * نمایش گروه
 * ======================================================================= */

Tests::group('فست › نمایش گروه');

$all_zero = Facets::options($terms, ['up3d' => 0, 'vhf' => 0, 'aidite' => 0], []);

Tests::ok('گروهی که همه‌چیزش صفر است پنهان می‌شود', !Facets::group_is_visible($all_zero));
Tests::ok('مگر مدیر عمداً پینش کرده باشد', Facets::group_is_visible($all_zero, true));

Tests::ok(
    'گروهی با یک انتخاب فعال، حتی با شمارش صفر می‌ماند',
    Facets::group_is_visible(Facets::options($terms, ['up3d' => 0, 'vhf' => 0, 'aidite' => 0], ['up3d']))
);

Tests::ok(
    'نبودِ شمارش دلیل پنهان‌کردن نیست',
    Facets::group_is_visible(Facets::options($terms, null, []))
);

Tests::ok('گروه بی‌گزینه حتی با پین هم نمایش داده نمی‌شود', !Facets::group_is_visible([], true));

/* ==========================================================================
 * ترجمه به tax_query
 * ======================================================================= */

Tests::group('فست › tax_query');

$single = Facets::tax_query(Query_State::create(['pa_brand' => ['up3d', 'vhf']]));

Tests::same('یک گروه، یک بند بدون relation', count($single), 1);
Tests::same('چندانتخابی درون گروه یعنی IN', $single[0]['operator'], 'IN');
Tests::same('هر دو ترم می‌آیند', $single[0]['terms'], ['up3d', 'vhf']);

/*
 * ‎include_children‎ باید خاموش باشد: ویژگی‌های ووکامرس سلسله‌مراتبی
 * نیستند، ولی کسی می‌تواند دستی زیرترم بسازد و آن‌وقت انتخاب یک ترم،
 * بی‌سروصدا نتایج ترم‌های دیگری را هم می‌آورد.
 */
Tests::ok('زیرترم‌ها وارد نمی‌شوند', false === $single[0]['include_children']);

$and = Facets::tax_query(
    Query_State::create(['pa_fit' => ['x', 'y']]),
    ['pa_fit' => Facets::OP_AND]
);

Tests::same('گروهی که اپراتورش AND است', $and[0]['operator'], 'AND');

$multi = Facets::tax_query(Query_State::create([
    'pa_brand' => ['up3d'],
    'pa_axis'  => ['5-axis'],
]));

Tests::same('بین گروه‌ها relation اضافه می‌شود', $multi['relation'] ?? '', 'AND');
Tests::same('و هر دو بند سر جایشان‌اند', count($multi) - 1, 2);

Tests::same('وضعیت بدون فیلتر، tax_query خالی', Facets::tax_query(Query_State::create()), []);

/* ==========================================================================
 * SQL شمارش
 * ======================================================================= */

Tests::group('فست › SQL');

$sql = Facets::count_sql('wp_wc_product_attributes_lookup', 'SELECT ID FROM wp_posts');

/*
 * بدون DISTINCT، محصولی که چند گزینه دارد چند بار شمرده می‌شود و عددها
 * بی‌سروصدا از تعداد واقعی بزرگ‌تر می‌شوند — دقیقاً همان‌قدر بزرگ‌تر که
 * «معقول» به نظر برسد.
 */
Tests::keeps('شمارش روی محصول یکتاست', $sql, 'COUNT(DISTINCT lookup.product_or_parent_id)');

/*
 * واحد بهینه‌سازی «گروه» است نه «گزینه»: یک GROUP BY برای پنجاه برند، نه
 * پنجاه کوئری.
 */
Tests::keeps('همهٔ گزینه‌ها با یک GROUP BY', $sql, 'GROUP BY lookup.term_id');
Tests::keeps('کوئری پایه به‌صورت زیرکوئری می‌آید', $sql, 'IN (SELECT ID FROM wp_posts)');
Tests::keeps('تاکسونومی به‌صورت پارامتر می‌ماند', $sql, 'lookup.taxonomy = %s');
Tests::blocks('بدون فیلتر موجودی وقتی خواسته نشده', $sql, 'in_stock');

Tests::keeps(
    'و با فیلتر موجودی وقتی خواسته شده',
    Facets::count_sql('t', 'SELECT ID FROM wp_posts', true),
    'lookup.in_stock = 1'
);
