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
    public function order_by_price_asc_post_clauses($args) {
        $args['orderby'] = 'ZIG_ORDER';

        return $args;
    }

    public function order_by_price_desc_post_clauses($args) { return $args; }
    public function order_by_popularity_post_clauses($args) { return $args; }
    public function order_by_rating_post_clauses($args) { return $args; }
    public function remove_ordering_args() {}

    /**
     * همان کاری که ووکامرس می‌کند: هم آرایه می‌دهد، هم فیلتر ثبت می‌کند.
     *
     * بدون این، تستِ «پل ساخته شد» چیزی را نمی‌سنجید — چون اثر جانبی‌ای در
     * کار نبود که محدودش کنیم.
     */
    public function get_catalog_ordering_args($orderby = '', $order = '') {
        if ('price' === $orderby) {
            add_filter('posts_clauses', [$this, 'order_by_price_asc_post_clauses'], 10);
        }

        return ['orderby' => $orderby, 'order' => $order];
    }
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

/* ==========================================================================
 * محدودشدن به یک کوئری
 * ======================================================================= */

Tests::group('ترتیب › دامنه');

/**
 * کوئری آزمایشی. فقط باید یک شیء باشد که بشود با ‎===‎ مقایسه‌اش کرد.
 */
final class Zig_Fake_Query {}

zig_reset_filters();

/*
 * ‎scope()‎ باید فیلتری را که خودش باعث ثبتش شده از حالت سراسری دربیاورد و
 * به یک پل تبدیل کند. اگر این کار نشود، در تمام مدت اجرای کوئری ما هر
 * کوئری تودرتویی — هر افزونه‌ای که به ‎pre_get_posts‎ وصل باشد — یک JOIN و
 * ORDER BY قیمت می‌گیرد که هیچ‌کس نخواسته.
 */
$ours   = new Zig_Fake_Query();
$nested = new Zig_Fake_Query();

$handle = Sorting::scope(['type' => 'price'], $ours, $wc);

Tests::ok(
    'فیلترِ سراسریِ ووکامرس دیگر بسته نیست',
    false === has_filter('posts_clauses', [$wc, 'order_by_price_asc_post_clauses'])
);

Tests::same('و یک پل جایش نشسته', count($handle['bridges']), 1);

// حالا رفتار پل را روی دو کوئری متفاوت می‌سنجیم
[$bridge, $priority] = $handle['bridges'][0];

$clauses = ['join' => '', 'orderby' => 'ORIGINAL'];

Tests::same(
    'کوئریِ تودرتو دست‌نخورده رد می‌شود',
    $bridge($clauses, $nested)['orderby'],
    'ORIGINAL'
);

Tests::same(
    'ولی کوئریِ خودمان مرتب‌سازی می‌گیرد',
    $bridge($clauses, $ours)['orderby'],
    'ZIG_ORDER'
);

Tests::same('و پل با همان اولویت بسته شده', $priority, 10);

Sorting::unscope($handle);

Tests::ok('بعد از کار، هیچ پلی نمی‌ماند', !has_filter('posts_clauses'));

/* --------------------------------------------------------------------------
 * وقتی همان متد از قبل روی اولویت دیگری بسته است
 *
 * حالت ظریفی که بررسیِ «وجود دارد یا نه» را بی‌اثر می‌کند: یک کال‌بک
 * می‌تواند هم‌زمان روی چند اولویت بسته باشد و وردپرس هرکدام را ورودی جدا
 * حساب می‌کند. اگر کسی متد را روی ۱۲ بسته باشد و ووکامرس روی ۱۰ ثبتش
 * کند، «از قبل بود» درست است ولی ثبتِ تازه مالِ ماست.
 * ----------------------------------------------------------------------- */

zig_reset_filters();

add_filter('posts_clauses', [$wc, 'order_by_price_asc_post_clauses'], 12);

$handle = Sorting::scope(['type' => 'price'], $ours, $wc);

Tests::same('ثبتِ تازه به پل تبدیل می‌شود', count($handle['bridges']), 1);
Tests::same('و پل همان اولویتِ ثبتِ تازه را می‌گیرد', $handle['bridges'][0][1], 10);

/*
 * و مالِ دیگری دست‌نخورده می‌ماند: نه پاکش می‌کنیم، نه به پل تبدیلش
 * می‌کنیم.
 */
Tests::same(
    'ثبتِ قبلی روی اولویت خودش می‌ماند',
    has_filter('posts_clauses', [$wc, 'order_by_price_asc_post_clauses']),
    12
);

Sorting::unscope($handle);

Tests::same(
    'و بعد از پایان کار هم فقط همان می‌ماند',
    has_filter('posts_clauses', [$wc, 'order_by_price_asc_post_clauses']),
    12
);

/*
 * ولی وقتی اولویت‌ها یکی باشند، ثبتِ ووکامرس در وردپرس idempotent است و
 * چیز تازه‌ای اضافه نمی‌شود — پس پلی هم لازم نیست و نباید ورودیِ کسِ
 * دیگری را برداریم.
 */
zig_reset_filters();

add_filter('posts_clauses', [$wc, 'order_by_price_asc_post_clauses'], 10);

$handle = Sorting::scope(['type' => 'price'], $ours, $wc);

Tests::same('اولویتِ یکسان، پل نمی‌سازد', count($handle['bridges']), 0);
Tests::same(
    'و ورودیِ دیگری دست‌نخورده می‌ماند',
    has_filter('posts_clauses', [$wc, 'order_by_price_asc_post_clauses']),
    10
);

zig_reset_filters();

Tests::same('ترتیبِ null هیچ پلی نمی‌سازد', Sorting::scope(null, $ours, $wc)['bridges'], []);
Tests::same('و آرگومانی هم نمی‌دهد', Sorting::scope(null, $ours, $wc)['args'], []);

/*
 * نوعی که اثر جانبی ندارد هم نباید پلی بسازد — وگرنه یک بستن و برداشتنِ
 * بی‌فایده به ازای هر کوئری داشتیم.
 */
Tests::same(
    'نوع بدون اثر جانبی هم پلی نمی‌سازد',
    count(Sorting::scope(['type' => 'title', 'order' => 'ASC'], $ours, $wc)['bridges']),
    0
);

zig_reset_filters();
