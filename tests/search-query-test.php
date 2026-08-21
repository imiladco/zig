<?php
/**
 * کوئریِ سرچ.
 *
 * این تست‌ها روی همان مرزی می‌ایستند که بقیهٔ افزونه رعایت می‌کند: هرچه
 * *تصمیم* است (کدام Tier، کدام کلیدِ کش، رفتنِ ‎has_more‎) بدونِ دیتابیس
 * سنجیده می‌شود؛ هرچه *اجرا* است (‎run_query()‎/‎hydrate()‎ که ‎$wpdb‎/
 * ‎WP_Query‎ صدا می‌زنند) دست‌نخورده می‌ماند — دقیقاً همان جایی که
 * ‎Attributes::base_sql()‎ هم تست نشده.
 *
 * تنها استثنا ‎hydrate_posts()‎ است: بدنهٔ ‎hydrate()‎ جدا شده تا بشود
 * پرسید «آیا پرایمِ batch یک‌بار صدا زده می‌شود؟» بدونِ نیاز به یک
 * ‎WP_Query‎ واقعی — دقیقاً همان چیزی که این تست ثابت می‌کند: اگر روزی
 * کسی ‎update_post_thumbnail_cache()‎ را داخلِ یک حلقه بگذارد، اینجا رد
 * می‌شود، قبل از اینکه رویِ استیجینگ به‌عنوانِ صد کوئری اضافه دیده شود.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/woocommerce-stub.php';

$root = dirname(__DIR__);

require_once $root . '/includes/search-normalizer.php';
require_once $root . '/includes/search-query.php';

use Zig3d_Widgets\Search_Query;

/* ==========================================================================
 * normalize_args
 * ======================================================================= */

Tests::group('کوئریِ سرچ › پاک‌سازیِ آرگومان‌ها');

$defaults = Search_Query::normalize_args([]);

Tests::same('نوعِ پستِ پیش‌فرض', $defaults['post_type'], 'product');
Tests::same('سقفِ پیش‌فرض', $defaults['limit'], Search_Query::DEFAULT_LIMIT);
Tests::same('فیلدِ پیش‌فرض فقط عنوان است', $defaults['search_fields'], ['title']);

Tests::same('عددِ منفی به پیش‌فرض برمی‌گردد', Search_Query::normalize_args(['limit' => -5])['limit'], Search_Query::DEFAULT_LIMIT);
Tests::same('سقفِ بالاتر از حد، بریده می‌شود', Search_Query::normalize_args(['limit' => 999])['limit'], Search_Query::MAX_LIMIT);

Tests::same(
    'فیلدِ ناشناخته کنار گذاشته می‌شود',
    Search_Query::normalize_args(['search_fields' => ['title', 'content', 'sku']])['search_fields'],
    ['title', 'sku']
);

Tests::same(
    'تاکسونومیِ تکراری یکی می‌شود',
    Search_Query::normalize_args(['match_taxonomies' => ['product_cat', 'product_cat', 'product_brand']])['match_taxonomies'],
    ['product_cat', 'product_brand']
);

Tests::same(
    'جفتِ مترادفِ ناقص حذف می‌شود',
    Search_Query::normalize_args(['synonym_pairs' => [['میلینگ', 'فرز'], ['x', ''], 'not-an-array']])['synonym_pairs'],
    [['میلینگ', 'فرز']]
);

/* ==========================================================================
 * tier_groups
 * ======================================================================= */

Tests::group('کوئریِ سرچ › گروه‌بندیِ Tier');

$variants = [
    ['tier' => 3, 'text' => 'سه بعدی'],
    ['tier' => 1, 'text' => '3بعدی'],
    ['tier' => 3, 'text' => '3 بعدی'],
    ['tier' => 0, 'text' => 'نادیده'],   // Tier نامعتبر
    ['tier' => 2, 'text' => ''],          // متنِ خالی
];

$groups = Search_Query::tier_groups($variants);

Tests::same('ترتیبِ Tierها صعودی است', array_keys($groups), [1, 3]);
Tests::same('Tier ۱ فقط متنِ خودش را دارد', $groups[1], ['3بعدی']);
Tests::same('Tier ۳ هر دو متن را دارد', $groups[3], ['سه بعدی', '3 بعدی']);
Tests::ok('Tierِ نامعتبر حذف شده', !isset($groups[0]));
Tests::ok('Tierِ متنِ خالی هم', !isset($groups[2]));

/* ==========================================================================
 * split_has_more
 * ======================================================================= */

Tests::group('کوئریِ سرچ › تشخیصِ «بیشتر هست؟» (P3)');

$fetched = [11, 12, 13, 14]; // limit=3 + یکی اضافه، بدونِ هیچ کوئریِ COUNT

$split = Search_Query::split_has_more($fetched, 3);

Tests::same('فقط به‌اندازهٔ سقف برمی‌گردد', $split['ids'], [11, 12, 13]);
Tests::ok('و علامتِ «بیشتر» بالا می‌رود', $split['has_more']);

$exact = Search_Query::split_has_more([1, 2, 3], 3);

Tests::same('دقیقاً هم‌اندازهٔ سقف، همه برمی‌گردند', $exact['ids'], [1, 2, 3]);
Tests::ok('و «بیشتر» نیست', !$exact['has_more']);

/* ==========================================================================
 * match_sql — تابعِ خالص
 * ======================================================================= */

Tests::group('کوئریِ سرچ › ساختِ SQL (خالص)');

$tables = [
    'posts'              => 'wp_posts',
    'postmeta'           => 'wp_postmeta',
    'term_relationships' => 'wp_term_relationships',
    'term_taxonomy'      => 'wp_term_taxonomy',
    'terms'              => 'wp_terms',
];

$title_only = Search_Query::match_sql(
    [1 => ["'%فرز%'"]],
    [],
    ['title'],
    $tables
);

Tests::keeps('عنوان در WHERE هست', $title_only['where'], 'wp_posts.post_title');
Tests::blocks('بدونِ sku، JOINی ساخته نمی‌شود', $title_only['join'], 'wp_postmeta');
Tests::blocks('بدونِ تاکسونومی، زیرکوئریِ ترم نیست', $title_only['where'], 'wp_term_relationships');
Tests::keeps('ORDER BY یک CASE است', $title_only['orderby'], 'CASE');
Tests::keeps('و Tier ۱ + وزنِ عنوان به امتیازِ ۱۰ می‌رسد', $title_only['orderby'], 'THEN 10');

$with_sku = Search_Query::match_sql(
    [1 => ["'%فرز%'"]],
    [],
    ['title', 'sku'],
    $tables
);

Tests::keeps('با sku، JOIN ساخته می‌شود', $with_sku['join'], 'wp_postmeta');
Tests::keeps('و meta_value هم در WHERE هست', $with_sku['where'], 'zig_search_sku.meta_value');
Tests::keeps('JOIN روی _sku است', $with_sku['join'], "meta_key = '_sku'");

$with_terms = Search_Query::match_sql(
    [1 => ["'%فرز%'"]],
    ["'product_cat'", "'product_brand'"],
    ['title'],
    $tables
);

Tests::keeps('با تاکسونومی، زیرکوئریِ ترم ساخته می‌شود', $with_terms['where'], 'wp_term_relationships');
Tests::keeps('و هر دو تاکسونومی در IN هست', $with_terms['where'], "'product_cat','product_brand'");

$multi_tier = Search_Query::match_sql(
    [1 => ["'%الف%'"], 3 => ["'%ب%'"]],
    [],
    ['title'],
    $tables
);

Tests::keeps('Tier ۳ هم در CASE هست', $multi_tier['orderby'], 'THEN 30');
Tests::same('بدونِ هیچ Tierی، همه‌چیز خالی است', Search_Query::match_sql([], [], ['title'], $tables), ['where' => '', 'orderby' => '', 'join' => '']);

/* ==========================================================================
 * cache_key
 * ======================================================================= */

Tests::group('کوئریِ سرچ › کلیدِ کش');

$args_a = Search_Query::normalize_args(['limit' => 6, 'search_fields' => ['title']]);
$args_b = Search_Query::normalize_args(['limit' => 6, 'search_fields' => ['title']]);
$args_c = Search_Query::normalize_args(['limit' => 6, 'search_fields' => ['title', 'sku']]);

Tests::same(
    'یک ورودیِ یکسان، همیشه یک کلید',
    Search_Query::cache_key(1, 'فرز', $args_a, false),
    Search_Query::cache_key(1, 'فرز', $args_b, false)
);

Tests::ok(
    'تغییرِ search_fields کلید را عوض می‌کند',
    Search_Query::cache_key(1, 'فرز', $args_a, false) !== Search_Query::cache_key(1, 'فرز', $args_c, false)
);

Tests::ok(
    'تغییرِ عبارتِ جست‌وجو کلید را عوض می‌کند',
    Search_Query::cache_key(1, 'فرز', $args_a, false) !== Search_Query::cache_key(1, 'میلینگ', $args_a, false)
);

Tests::ok(
    'بالارفتنِ نسخه کلید را عوض می‌کند — این همان راهِ فرارِ کش با تغییرِ محتواست',
    Search_Query::cache_key(1, 'فرز', $args_a, false) !== Search_Query::cache_key(2, 'فرز', $args_a, false)
);

Tests::ok(
    'قانونِ موجودی هم داخلِ کلید است',
    Search_Query::cache_key(1, 'فرز', $args_a, false) !== Search_Query::cache_key(1, 'فرز', $args_a, true)
);

/* ==========================================================================
 * hydrate_posts — P4/P5: پرایمِ batch یک‌بار، نه به ازایِ هر ردیف
 * ======================================================================= */

Tests::group('کوئریِ سرچ › batch-priming (P4/P5)');

$GLOBALS['__zig_thumb_calls'] = 0;
$GLOBALS['__zig_term_calls']  = 0;

if (!function_exists('update_post_thumbnail_cache')) {
    function update_post_thumbnail_cache($query) { $GLOBALS['__zig_thumb_calls']++; }
}
if (!function_exists('update_object_term_cache')) {
    function update_object_term_cache($ids, $type) { $GLOBALS['__zig_term_calls']++; }
}
if (!function_exists('wp_get_attachment_image_src')) {
    function wp_get_attachment_image_src($id, $size = 'thumbnail') { return false; }
}
if (!function_exists('taxonomy_exists')) {
    function taxonomy_exists($tax) { return true; }
}

/*
 * پستِ آزمایشی فقط ‎->ID‎ لازم دارد — همان قراردادی که ‎hydrate_posts()‎
 * می‌پذیرد، دقیقاً برایِ اینکه بدونِ ‎WP_Post‎ی واقعی هم سنجیده شود.
 */
function zig_fake_posts(array $ids): array {
    return array_map(static fn(int $id) => (object) ['ID' => $id], $ids);
}

$three = zig_fake_posts([101, 102, 103]);
$thirty = zig_fake_posts(range(201, 230));

$GLOBALS['__zig_thumb_calls'] = 0;
$GLOBALS['__zig_term_calls']  = 0;

$result_3 = Search_Query::hydrate_posts($three, (object) [], 'product', ['category_taxonomy' => 'product_cat']);

Tests::same('برایِ ۳ نتیجه، پرایمِ بندانگشتی یک‌بار صدا خورده', $GLOBALS['__zig_thumb_calls'], 1);
Tests::same('و پرایمِ ترم هم یک‌بار', $GLOBALS['__zig_term_calls'], 1);
Tests::same('و همچنان ۳ ردیفِ شکل‌گرفته برمی‌گردد', count($result_3), 3);
Tests::same('شناسه‌ها به همان ترتیب حفظ می‌شوند', array_column($result_3, 'id'), [101, 102, 103]);

$GLOBALS['__zig_thumb_calls'] = 0;
$GLOBALS['__zig_term_calls']  = 0;

$result_30 = Search_Query::hydrate_posts($thirty, (object) [], 'product', ['category_taxonomy' => 'product_cat']);

Tests::same(
    'برایِ ۳۰ نتیجه هم پرایمِ بندانگشتی همچنان فقط یک‌بار صدا خورده — رشد نکرده با تعداد',
    $GLOBALS['__zig_thumb_calls'],
    1
);
Tests::same('و پرایمِ ترم هم همچنان یک‌بار', $GLOBALS['__zig_term_calls'], 1);
Tests::same('و ۳۰ ردیف شکل گرفته', count($result_30), 30);

/*
 * بدونِ هیچ تاکسونومیِ درخواست‌شده، پرایمِ ترم اصلاً صدا زده نشود — صدا
 * زدنِ بی‌قیدوشرط یعنی یک کوئریِ اضافه برایِ ویجتی که اصلاً دسته/برند
 * نشان نمی‌دهد.
 */
$GLOBALS['__zig_term_calls'] = 0;
Search_Query::hydrate_posts($three, (object) [], 'product', []);
Tests::same('بدونِ category/brand، پرایمِ ترم صدا زده نمی‌شود', $GLOBALS['__zig_term_calls'], 0);

Tests::same('فهرستِ خالی چیزی برنمی‌گرداند', Search_Query::hydrate_posts([], (object) [], 'product', []), []);

/*
 * لینک‌ها از خودِ ‎get_permalink()‎ی استاب می‌آیند — سنجشِ این‌که واقعاً
 * صدا زده شده، نه یک URL دستی.
 */
Tests::keeps('لینک از get_permalink می‌آید', $result_3[0]['permalink'], (string) 101);
Tests::ok('بدونِ بندانگشتی، thumbnail خالی است', null === $result_3[0]['thumbnail']);

/* ==========================================================================
 * نرمال‌سازیِ دوطرفه و وزنِ ثانویه
 *
 * دو باگی که هیچ‌کدام خطا نمی‌دادند و فقط «نتیجه کم می‌آمد»:
 *
 *   ۱. ستون نرمال نمی‌شد. عنوانی که ادمین با «ي»ِ عربی ذخیره کرده بود،
 *      با کوئریِ canonicalشدهٔ «ی»دار هیچ‌وقت مچ نمی‌شد.
 *   ۲. کنترلِ «جستجو در» کامل اطاعت نمی‌شد: حتی وقتی فقط SKU انتخاب
 *      شده بود، ‎post_title‎ باز هم به WHERE اضافه می‌شد.
 * ======================================================================= */

Tests::group('کوئریِ سرچ › نرمال‌سازیِ دوطرفه');

$normalized_sql = Search_Query::match_sql([1 => ["'%ماشین%'"]], ["'product_cat'"], ['title', 'sku'], $tables);

foreach ([
    'عنوان'      => 'wp_posts.post_title',
    'کدِ محصول'  => 'zig_search_sku.meta_value',
    'نامِ ترم'   => 't.name',
] as $label => $column) {
    Tests::keeps(
        'ستونِ ' . $label . ' هم نرمال می‌شود، نه فقط کوئری',
        $normalized_sql['where'],
        "REPLACE(" . $column
    );
}

Tests::keeps('یِ عربی در نگاشتِ ستون هست', $normalized_sql['where'], "'ي', 'ی'");
Tests::keeps('کافِ عربی هم', $normalized_sql['where'], "'ك', 'ک'");
Tests::keeps('رقمِ فارسی هم', $normalized_sql['where'], "'۳', '3'");

Tests::group('کوئریِ سرچ › احترام به «جستجو در»');

$sku_only = Search_Query::match_sql([1 => ["'%X1%'"]], [], ['sku'], $tables);

Tests::ok(
    'با انتخابِ فقط SKU، هیچ شرطی رویِ post_title ساخته نمی‌شود',
    false === strpos($sku_only['where'], 'post_title')
);
Tests::keeps('و در عوض meta_value هست', $sku_only['where'], 'zig_search_sku.meta_value');

Tests::group('کوئریِ سرچ › وزنِ ثانویه (عنوان > SKU > ترم)');

$weighted = Search_Query::match_sql([1 => ["'%فرز%'"]], ["'product_cat'"], ['title', 'sku'], $tables);

/*
 * امتیاز = Tier×۱۰ + وزنِ فیلد. پس در Tier 1: عنوان ۱۰، SKU ۱۱، ترم ۱۲.
 * ترتیبِ ظاهرشدنشان در CASE هم باید همین باشد، چون ‎CASE‎ اولین شرطِ
 * درست را برمی‌دارد.
 */
Tests::keeps('عنوان امتیازِ ۱۰ می‌گیرد', $weighted['orderby'], 'THEN 10');
Tests::keeps('SKU امتیازِ ۱۱', $weighted['orderby'], 'THEN 11');
Tests::keeps('نامِ ترم امتیازِ ۱۲', $weighted['orderby'], 'THEN 12');

Tests::ok(
    'و در CASE به همین ترتیب می‌آیند — عنوان قبل از SKU، SKU قبل از ترم',
    strpos($weighted['orderby'], 'THEN 10') < strpos($weighted['orderby'], 'THEN 11')
        && strpos($weighted['orderby'], 'THEN 11') < strpos($weighted['orderby'], 'THEN 12')
);
