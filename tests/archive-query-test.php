<?php
/**
 * کوئری آرشیو.
 *
 * یک قاعده اینجا سنجیده می‌شود: فهرست و شمارش از یک کوئری پایه می‌آیند.
 * اگر روزی کسی برای گرید و برای اعداد کنار فیلترها دو کوئری متفاوت بسازد،
 * هیچ خطایی نمی‌آید — فقط کاربر «۷» می‌بیند، کلیک می‌کند و ۹ محصول
 * می‌آید.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/woocommerce-stub.php';

$root = dirname(__DIR__);

require_once $root . '/includes/query-state.php';
require_once $root . '/includes/facets.php';
require_once $root . '/includes/filter-schema.php';
require_once $root . '/includes/schema-store.php';
require_once $root . '/includes/sorting.php';
require_once $root . '/includes/archive-query.php';

use Zig3d_Widgets\Archive_Query;
use Zig3d_Widgets\Facets;
use Zig3d_Widgets\Query_State;
use Zig3d_Widgets\Sorting;

zig_reset_products();

// فروشگاه واقعی همیشه ترم‌های دیده‌شدن را دارد؛ بدون آن‌ها بندِ visibility
// اصلاً ساخته نمی‌شود و بقیهٔ سنجه‌ها چیز دیگری را می‌سنجند.
$GLOBALS['__zig_visibility'] = ['exclude-from-catalog' => 31, 'outofstock' => 32];

/* ==========================================================================
 * قیدهای ثابت
 * ======================================================================= */

Tests::group('کوئری آرشیو › قیدهای ثابت');

$base = Archive_Query::base_args();

Tests::same('فقط محصول', $base['post_type'], 'product');
Tests::same('فقط منتشرشده', $base['post_status'], 'publish');

/*
 * محصول چسبان در آرشیو فروشگاه بی‌معناست و ترتیبِ انتخاب‌شدهٔ کاربر را
 * بی‌صدا به‌هم می‌ریزد.
 */
Tests::ok('چسبان‌ها نادیده گرفته می‌شوند', $base['ignore_sticky_posts']);

$scoped = Archive_Query::base_args(['categories' => [12, 0, '15', -3]]);
$clause = end($scoped['tax_query']);

Tests::same('دسته‌ها به کوئری می‌آیند', $clause['terms'], [12, 15]);
Tests::ok('و زیر‌دسته‌ها هم', $clause['include_children']);

Tests::same(
    'محصولات مستثنا',
    Archive_Query::base_args(['exclude' => [7, 'x', 9]])['post__not_in'],
    [7, 9]
);

Tests::same('جست‌وجو', Archive_Query::base_args(['search' => '  فرز  '])['s'], 'فرز');
Tests::ok('جست‌وجوی خالی چیزی اضافه نمی‌کند', !isset(Archive_Query::base_args(['search' => '   '])['s']));

/*
 * وقتی بیش از یک بند هست، relation لازم است. بدون آن وردپرس پیش‌فرض AND
 * می‌گذارد که همان است، ولی صریح‌بودن یعنی اگر بعداً بندی با relation خودش
 * اضافه شد، جای خطا نماند.
 */
$two = Archive_Query::base_args(['categories' => [5]]);

Tests::same('چند بند، relation صریح می‌گیرند', $two['tax_query']['relation'] ?? '', 'AND');

/* ==========================================================================
 * دیده‌شدن
 * ======================================================================= */

Tests::group('کوئری آرشیو › دیده‌شدن');

$GLOBALS['__zig_options'] = ['woocommerce_hide_out_of_stock_items' => 'no'];

$clauses = Archive_Query::visibility_clauses();

/*
 * محصولی که مدیر عمداً از فهرست بیرون گذاشته نباید در گرید ما ظاهر شود؛
 * وگرنه ویجت ما تنظیم خودِ ووکامرس را دور می‌زند.
 */
Tests::same('پنهان‌از‌فهرست همیشه کنار می‌رود', $clauses[0]['terms'], [31]);
Tests::same('و با NOT IN', $clauses[0]['operator'], 'NOT IN');
Tests::same('روی شناسهٔ ترم‌تاکسونومی', $clauses[0]['field'], 'term_taxonomy_id');

$GLOBALS['__zig_options'] = ['woocommerce_hide_out_of_stock_items' => 'yes'];

Tests::same(
    'ناموجودها فقط وقتی فروشگاه خواسته',
    Archive_Query::visibility_clauses()[0]['terms'],
    [31, 32]
);

Tests::ok('و سیاست فروشگاه خوانده می‌شود', Archive_Query::hides_out_of_stock());

$GLOBALS['__zig_options'] = [];

Tests::ok('پیش‌فرض، ناموجودها پنهان نیستند', !Archive_Query::hides_out_of_stock());

/* ==========================================================================
 * سوارکردن انتخاب کاربر
 * ======================================================================= */

Tests::group('کوئری آرشیو › فیلتر و ترتیب');

$base  = Archive_Query::base_args(['categories' => [12]]);
$state = Query_State::create(['pa_brand' => ['up3d']], 'price', 3);

$built = Archive_Query::build($base, $state, ['pa_brand' => Facets::OP_OR], null, 9);

Tests::same('صفحه از وضعیت می‌آید', $built['paged'], 3);
Tests::same('تعداد در صفحه', $built['posts_per_page'], 9);

/*
 * قیدهای ثابت و فیلترهای کاربر هرکدام یک گروه می‌مانند. اگر تخت می‌شدند و
 * یکی از دو طرف relation خودش را داشت، آن relation روی کل مجموعه اعمال
 * می‌شد — یعنی یک OR داخلی می‌توانست قید دیده‌شدن را هم اختیاری کند.
 */
Tests::same('قیدهای ثابت و فیلترها با AND کنار هم', $built['tax_query']['relation'], 'AND');
Tests::same('و هر کدام یک گروه می‌مانند', count($built['tax_query']) - 1, 2);

Tests::ok(
    'بدون فیلتر، tax_query پایه دست‌نخورده می‌ماند',
    Archive_Query::with_filters($base, Query_State::create()) === $base
);

/*
 * ترتیب می‌تواند meta_key بیاورد و باید روی آرگومان‌های پایه بنشیند نه
 * زیرشان؛ ادغام برعکس یعنی هر ترتیبی بی‌صدا بی‌اثر می‌ماند.
 */
$sorted = Archive_Query::build(
    $base,
    Query_State::create(),
    [],
    Sorting::sanitize_options([['type' => 'price']])[0],
    12
);

Tests::same('ترتیب قیمت اعمال می‌شود', $sorted['meta_key'], '_price');
Tests::same('و جهتش', $sorted['order'], 'ASC');
Tests::same('و نوع پست دست نمی‌خورد', $sorted['post_type'], 'product');

/* ==========================================================================
 * سقف صفحه
 * ======================================================================= */

Tests::group('کوئری آرشیو › سقف');

Tests::same('عدد نامعتبر به پیش‌فرض برمی‌گردد', Archive_Query::per_page(0), 12);
Tests::same('منفی هم همین‌طور', Archive_Query::per_page(-5), 12);
Tests::same('عدد معقول دست نمی‌خورد', Archive_Query::per_page(24), 24);

/*
 * سقف، محافظ است نه سلیقه: یک تنظیم اشتباه (یا دست‌کاری‌شده) نباید بتواند
 * کوئریِ بی‌مرز بسازد.
 */
Tests::same('عدد بزرگ به سقف می‌خورد', Archive_Query::per_page(5000), Archive_Query::MAX_PER_PAGE);

/* ==========================================================================
 * کلید زمینه
 * ======================================================================= */

Tests::group('کوئری آرشیو › کلید زمینه');

/*
 * کلید کشِ شمارش از این ساخته می‌شود. جاافتادن هر کدام از این‌ها یعنی
 * شمارش‌های یک دسته روی دستهٔ دیگری سرو می‌شوند — عددهایی که هیچ‌وقت شبیه
 * باگ به نظر نمی‌رسند.
 */
$twelve = Archive_Query::context_key(Archive_Query::base_args(['categories' => [12]]));
$ninety = Archive_Query::context_key(Archive_Query::base_args(['categories' => [99]]));

Tests::ok('دستهٔ متفاوت، کلید متفاوت', $twelve !== $ninety);

Tests::ok(
    'جست‌وجوی متفاوت، کلید متفاوت',
    Archive_Query::context_key(Archive_Query::base_args(['search' => 'فرز'])) !== $twelve
);

Tests::ok(
    'فهرست مستثنا هم در کلید هست',
    Archive_Query::context_key(Archive_Query::base_args(['exclude' => [3]])) !== $twelve
);

Tests::same('همان ورودی، همان کلید', $twelve, Archive_Query::context_key(Archive_Query::base_args(['categories' => [12]])));

$GLOBALS['__zig_options'] = ['woocommerce_hide_out_of_stock_items' => 'yes'];

Tests::ok(
    'سیاست نمایش ناموجود هم کوئری پایه را عوض می‌کند، پس در کلید است',
    Archive_Query::context_key(Archive_Query::base_args(['categories' => [12]])) !== $twelve
);

$GLOBALS['__zig_options'] = [];
