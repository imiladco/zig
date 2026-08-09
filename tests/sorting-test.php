<?php
/**
 * ترتیب محصولات.
 *
 * ترتیب غلط، هیچ خطایی نمی‌دهد. فهرست همان تعداد محصول را نشان می‌دهد و
 * فقط چیدمانش آن نیست که کاربر خواسته — و کسی که «ارزان‌ترین» را زده و
 * گران‌ترین را می‌بیند، معمولاً به جای گزارش‌دادن، صفحه را می‌بندد.
 */

require_once __DIR__ . '/bootstrap.php';

$root = dirname(__DIR__);

require_once $root . '/includes/sorting.php';

use Zig3d_Widgets\Sorting;

/* ==========================================================================
 * پاک‌سازی فهرست
 * ======================================================================= */

Tests::group('ترتیب › پاک‌سازی فهرست');

$options = Sorting::sanitize_options([
    ['type' => 'popularity', 'label' => 'پرفروش‌ترین'],
    ['type' => 'price'],
    ['type' => 'nonsense',   'label' => 'چیزی که وجود ندارد'],
    ['type' => 'meta',       'label' => 'بدون فیلد'],
    ['type' => 'meta',       'label' => 'وزن', 'meta_key' => '_weight'],
]);

Tests::same('نوع ناشناخته کنار می‌رود', count($options), 3);
Tests::same('برچسب خالی از خودِ نوع پر می‌شود', $options[1]['label'], 'ارزان‌ترین');

/*
 * «فیلد دلخواه» بدون کلید متا، یک پیل است که کلیک می‌شود و هیچ اتفاقی
 * نمی‌افتد. رندرنشدنش بهتر از رندرشدنِ بی‌اثر است.
 */
Tests::same('فیلد دلخواه بدون کلید متا رندر نمی‌شود', $options[2]['meta_key'], '_weight');

Tests::same('کلیدها یکتا هستند', count(array_unique(Sorting::keys($options))), 3);

$duplicated = Sorting::sanitize_options([
    ['type' => 'meta', 'meta_key' => '_a'],
    ['type' => 'meta', 'meta_key' => '_a'],
]);

/*
 * دو ردیف با یک کلید یعنی دومی هرگز انتخاب نمی‌شود؛ در پنل کاملاً سالم به
 * نظر می‌رسد و فقط با کلیک‌کردن کشف می‌شود.
 */
Tests::same('کلید تکراری پسوند می‌گیرد', count(array_unique(Sorting::keys($duplicated))), 2);

Tests::same('کلید دلخواهِ مدیر مقدم است', Sorting::sanitize_options([
    ['type' => 'date', 'key' => 'newest'],
])[0]['key'], 'newest');

/* ==========================================================================
 * جهت
 * ======================================================================= */

Tests::group('ترتیب › جهت');

/*
 * ووکامرس جهتِ قیمت را داخل خودِ orderby کدگذاری کرده (‎price‎ و
 * ‎price-desc‎). اگر کلید جهتِ جدا هم بپذیریم، دو منبع متناقض داریم و
 * نتیجه به ترتیب اعمال بستگی پیدا می‌کند.
 */
Tests::same(
    'جهتِ دلخواه روی نوع غیرجهت‌دار اثر ندارد',
    Sorting::sanitize_options([['type' => 'price', 'order' => 'DESC']])[0]['order'],
    'ASC'
);

Tests::same(
    'ولی روی نوع جهت‌دار می‌نشیند',
    Sorting::sanitize_options([['type' => 'title', 'order' => 'ASC']])[0]['order'],
    'ASC'
);

Tests::same(
    'و پیش‌فرضِ نوع جهت‌دار نزولی است',
    Sorting::sanitize_options([['type' => 'date']])[0]['order'],
    'DESC'
);

/* ==========================================================================
 * انتخاب
 * ======================================================================= */

Tests::group('ترتیب › انتخاب');

$list = Sorting::sanitize_options([
    ['type' => 'popularity'],
    ['type' => 'price'],
]);

Tests::same('کلید موجود پیدا می‌شود', Sorting::resolve($list, 'price')['type'], 'price');

/*
 * آدرسِ بوکمارک‌شده یا ایندکس‌شده نباید بعد از تغییر تنظیمات ویجت، صفحهٔ
 * خراب بدهد.
 */
Tests::same('کلید ناشناخته به گزینهٔ اول برمی‌گردد', Sorting::resolve($list, 'ghost')['type'], 'popularity');
Tests::same('فهرست خالی، چیزی برنمی‌گرداند', Sorting::resolve([], 'price'), null);

/* ==========================================================================
 * زمینهٔ جست‌وجو
 * ======================================================================= */

Tests::group('ترتیب › زمینهٔ جست‌وجو');

$with_relevance = Sorting::sanitize_options([
    ['type' => 'relevance'],
    ['type' => 'price'],
]);

/*
 * «مرتبط‌ترین» بدون جست‌وجو نه‌تنها بی‌معناست، ‎ORDER BY relevance‎ در
 * وردپرس از عبارت‌های جست‌وجو ساخته می‌شود و بدون آن‌ها SQL نامعتبر
 * می‌دهد. پس اصلاً نباید رندر شود، نه اینکه رندر شود و موقع کلیک صفحه را
 * بشکند.
 */
Tests::same('با جست‌وجو، هر دو گزینه هستند', count(Sorting::available($with_relevance, true)), 2);
Tests::same('بدون جست‌وجو، مرتبط‌ترین کنار می‌رود', count(Sorting::available($with_relevance, false)), 1);

Tests::same(
    'و اگر کسی کلیدش را در آدرس بگذارد، به گزینهٔ معتبر برمی‌گردد',
    Sorting::resolve($with_relevance, 'relevance', false)['type'],
    'price'
);

Tests::same(
    'ولی با جست‌وجو انتخاب می‌شود',
    Sorting::resolve($with_relevance, 'relevance', true)['type'],
    'relevance'
);

/* ==========================================================================
 * چرخهٔ عمر فیلترهای ووکامرس
 * ======================================================================= */

Tests::group('ترتیب › اثر جانبی');

/*
 * ‎get_catalog_ordering_args()‎ برای این سه نوع یک فیلتر ‎posts_clauses‎
 * سراسری ثبت می‌کند که امضایش پارامتر ‎$query‎ ندارد — یعنی نمی‌تواند
 * بفهمد روی کدام کوئری نشسته و به هر ‎WP_Query‎ بعدی می‌چسبد. خودِ ووکامرس
 * آن را روی ‎the_posts‎ برمی‌دارد، ولی آن هوک فقط برای کوئری اصلی بسته
 * شده.
 */
foreach (['price', 'price-desc', 'popularity', 'rating'] as $type) {
    Tests::ok(
        sprintf('«%s» اثر جانبی سراسری دارد', $type),
        Sorting::has_side_effects(['type' => $type])
    );
}

foreach (['default', 'date', 'title', 'meta'] as $type) {
    Tests::ok(
        sprintf('«%s» ندارد', $type),
        !Sorting::has_side_effects(['type' => $type])
    );
}

// بدون ووکامرس هم باید بی‌خطر باشد، چون دفاعی صدا زده می‌شود
Sorting::release();
Tests::ok('پاک‌سازی بدون ووکامرس خطا نمی‌دهد', true);

/* ==========================================================================
 * ترجمه به کوئری
 * ======================================================================= */

Tests::group('ترتیب › ترجمه به کوئری');

$meta = Sorting::query_args([
    'type'      => 'meta',
    'order'     => 'ASC',
    'meta_key'  => '_zig_axis',
    'meta_type' => 'num',
]);

Tests::same('فیلد دلخواه عددی', $meta['orderby'], 'meta_value_num');
Tests::same('با همان کلید متا', $meta['meta_key'], '_zig_axis');
Tests::same('و همان جهت', $meta['order'], 'ASC');

Tests::same(
    'فیلد دلخواه متنی',
    Sorting::query_args(['type' => 'meta', 'meta_key' => '_x', 'meta_type' => 'text'])['orderby'],
    'meta_value'
);

/*
 * بدون ووکامرس، ترجمهٔ جایگزین. ادعای برابری با ووکامرس ندارد — فقط جلوی
 * مرتب‌سازی تصادفی را می‌گیرد.
 */
Tests::same('قیمت صعودی', Sorting::fallback_args('price')['meta_key'], '_price');
Tests::same('و جهتش', Sorting::fallback_args('price')['order'], 'ASC');
Tests::same('قیمت نزولی', Sorting::fallback_args('price-desc')['order'], 'DESC');
Tests::same('پرفروش‌ترین از total_sales', Sorting::fallback_args('popularity')['meta_key'], 'total_sales');
Tests::same('امتیاز از میانگین ووکامرس', Sorting::fallback_args('rating')['meta_key'], '_wc_average_rating');
Tests::same('ناشناخته به ترتیب دستی برمی‌گردد', Sorting::fallback_args('ghost')['orderby'], 'menu_order title');

/*
 * مرتب‌سازی بر اساس تاریخ باید شناسه را هم بچسباند: دو محصول که در یک
 * ثانیه ثبت شده‌اند بدون آن، ترتیب غیرقطعی می‌گیرند و بین دو صفحه تکرار یا
 * حذف می‌شوند.
 */
Tests::keeps('تاریخ با شناسه گره می‌خورد', Sorting::fallback_args('date')['orderby'], 'ID');
