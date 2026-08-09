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

/* ==========================================================================
 * نگه‌داشتن و بازگرداندن
 * ======================================================================= */

Tests::group('ترتیب › وضعیت سراسری');

/**
 * جای ‎WC()->query‎ — فقط برای اینکه فیلترها روی چیزی بنشینند.
 */
final class Zig_Fake_WC_Query {
    public function order_by_price_asc_post_clauses($args) { return $args; }
    public function order_by_price_desc_post_clauses($args) { return $args; }
    public function order_by_popularity_post_clauses($args) { return $args; }
    public function order_by_rating_post_clauses($args) { return $args; }
    public function remove_ordering_args() {}
}

$wc = new Zig_Fake_WC_Query();

zig_reset_filters();

/*
 * سناریوی اصلی: افزونهٔ دیگری (یا خودِ ووکامرس در میانهٔ چرخهٔ کوئری اصلی)
 * یک فیلتر ثبت کرده. اگر ما با ‎remove_ordering_args()‎ همه را پاک کنیم،
 * وضعیتی را نابود کرده‌ایم که مالکش نیستیم — و آن‌طرف هیچ‌وقت نمی‌فهمد چرا
 * مرتب‌سازی‌اش از کار افتاد.
 */
add_filter('posts_clauses', [$wc, 'order_by_popularity_post_clauses'], 17);

$snapshot = Sorting::suspend($wc);

Tests::same('عکسِ وضعیت، فیلترِ دیگری را ثبت می‌کند', $snapshot, ['order_by_popularity_post_clauses' => 17]);
Tests::ok(
    'و برای بازهٔ کار ما برداشته می‌شود',
    false === has_filter('posts_clauses', [$wc, 'order_by_popularity_post_clauses'])
);

// حالا فیلتر خودمان را می‌بندیم، مثل چیزی که ووکامرس داخل query_args می‌کند
add_filter('posts_clauses', [$wc, 'order_by_price_asc_post_clauses'], 10);

Sorting::restore($snapshot, $wc);

Tests::ok(
    'فیلتر خودمان بعد از کار برداشته می‌شود',
    false === has_filter('posts_clauses', [$wc, 'order_by_price_asc_post_clauses'])
);

/*
 * و مهم‌تر: مالِ دیگری دقیقاً همان‌طور که بود برمی‌گردد — با همان اولویت.
 * اولویت اگر جا بیفتد، ترتیب اجرای فیلترها عوض می‌شود و باگش از این هم
 * نامرئی‌تر است.
 */
Tests::same(
    'و مالِ دیگری با همان اولویت برمی‌گردد',
    has_filter('posts_clauses', [$wc, 'order_by_popularity_post_clauses']),
    17
);

zig_reset_filters();

Tests::same('وضعیت خالی، عکس خالی می‌دهد', Sorting::suspend($wc), []);

Sorting::restore([], $wc);

Tests::ok('و بازگرداندنِ عکس خالی چیزی اضافه نمی‌کند', !has_filter('posts_clauses'));

/*
 * ورودیِ دست‌کاری‌شده نباید بتواند هر متدی را به هوک ببندد. عکسِ وضعیت
 * از دیتابیس نمی‌آید، ولی ارزان‌ترین محافظ ممکن است و جلوی یک اشتباه
 * تایپی هم می‌گیرد.
 */
Sorting::restore(['some_other_method' => 10], $wc);

Tests::ok('متدِ خارج از فهرست بسته نمی‌شود', !has_filter('posts_clauses'));

zig_reset_filters();

// بدون ووکامرس هم باید بی‌خطر باشد
Tests::same('بدون ووکامرس، عکس خالی', Sorting::suspend(), []);
Sorting::restore([]);
Tests::ok('و بازگرداندن هم خطا نمی‌دهد', true);
