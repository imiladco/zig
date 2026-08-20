<?php
/**
 * منویِ اصلی.
 *
 * چهار چیز سنجیده می‌شود که هیچ‌کدام در پنلِ المنتور خطایی نشان نمی‌دهند:
 *
 *   ۱. درختِ منو — تودرتویی، و ‎current‎ی که والدِ صفحهٔ باز را هم فعال
 *      می‌کند (وگرنه در صفحهٔ زیرمنو، زیرخطِ والد خاموش می‌شود).
 *   ۲. انتخابِ شکلِ پنل: قالبِ المنتور بر مگامنو، مگامنو بر فهرستِ ساده.
 *   ۳. شمارش فقط برایِ آیتمی که واقعاً به دستهٔ محصول وصل است.
 *   ۴. باز شدن بدونِ جاوااسکریپت — ‎:hover‎ و ‎:focus-within‎ هر دو.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/elementor-stub.php';
require_once __DIR__ . '/lib/woocommerce-stub.php';

$root = dirname(__DIR__);

require_once $root . '/includes/selector.php';
require_once $root . '/includes/markup.php';
require_once $root . '/includes/design-icons.php';
require_once $root . '/includes/menu-tree.php';
require_once $root . '/includes/widgets/traits/box.php';
require_once $root . '/includes/widgets/menu.php';

use Zig3d_Widgets\Menu_Tree;
use Zig3d_Widgets\Widgets\Menu;

/* --------------------------------------------------------------------------
 * فهرستِ ساختگی — همان شکلی که وردپرس برمی‌گرداند: تخت، با menu_item_parent
 * ----------------------------------------------------------------------- */

$GLOBALS['__zig_menu_items'] = [];

if (!function_exists('wp_get_nav_menu_items')) {
    function wp_get_nav_menu_items($menu_id, $args = []) {
        return $GLOBALS['__zig_menu_items'][$menu_id] ?? [];
    }
}
if (!function_exists('wp_get_nav_menus')) {
    function wp_get_nav_menus($args = []) {
        return [(object) ['term_id' => 7, 'name' => 'منویِ اصلی']];
    }
}
if (!function_exists('get_term')) {
    function get_term($id, $taxonomy = '') {
        $counts = $GLOBALS['__zig_term_counts'] ?? [];

        return isset($counts[$id]) ? (object) ['term_id' => $id, 'count' => $counts[$id]] : null;
    }
}
if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) { return false; }
}
if (!function_exists('wp_count_posts')) {
    function wp_count_posts($type = 'post') { return (object) ['publish' => 250]; }
}

/*
 * کارت‌ها بدونِ ساختنِ ‎WP_Query‎ی ساختگی سنجیده می‌شوند.
 *
 * دلیلش هم‌زیستی است: همهٔ فایل‌هایِ سنجه در یک پروسه اجرا می‌شوند و
 * ‎WP_Query‎ و ‎update_post_thumbnail_cache‎ را فایل‌هایِ دیگر با رفتارِ
 * خودشان تعریف کرده‌اند. تعریفِ دوباره‌شان اینجا یا بی‌اثر می‌ماند یا
 * سنجهٔ آن‌ها را می‌شکند — یک بار همین شد.
 *
 * پس به‌جایِ کوئری، خودِ کشِ ‎popular_products‎ از پیش پر می‌شود. مسیرِ
 * کش هم مسیرِ واقعیِ محصول است، نه یک درِ پشتی.
 */
if (!function_exists('get_the_post_thumbnail')) {
    function get_the_post_thumbnail($id = 0, $size = '', $attr = []) { return ''; }
}

/** کلیدِ کشِ پرفروش‌ترین‌ها، همان‌طور که ‎Menu_Tree‎ می‌سازدش */
function zig_popular_key(int $term, int $limit): string {
    return 'zig3d_menu_pop_' . (int) get_option('zig3d_menu_cache_version', 1) . '_' . $term . '_' . $limit;
}

$GLOBALS['__zig_term_counts'] = [11 => 141, 12 => 95];

/** یک آیتمِ منو با کمترین فیلدهایی که وردپرس می‌دهد */
function zig_menu_item(int $id, string $title, int $parent = 0, array $extra = []): object {
    return (object) array_merge([
        'ID'               => $id,
        'title'            => $title,
        'url'              => 'https://zig3d.test/' . $id,
        'target'           => '',
        'xfn'              => '',
        'classes'          => [],
        'menu_item_parent' => (string) $parent,
        'type'             => 'custom',
        'object'           => 'custom',
        'object_id'        => 0,
    ], $extra);
}

$category = static fn(int $term): array => ['type' => 'taxonomy', 'object' => 'product_cat', 'object_id' => $term];

$GLOBALS['__zig_menu_items'][7] = [
    zig_menu_item(1, 'محصولات', 0, ['classes' => ['current-menu-ancestor']]),
    zig_menu_item(11, 'انواع قطعات', 1, $category(11)),
    zig_menu_item(111, 'قطعات میلینگ ماشین', 11),
    zig_menu_item(12, 'انواع مواد و متریال', 1, $category(12)),
    zig_menu_item(2, 'دانلود نرم‌افزار', 0),
    zig_menu_item(21, 'نرم‌افزار میلینگ ماشین', 2),
    zig_menu_item(22, 'نرم‌افزار اسکنر رومیزی', 2),
    zig_menu_item(3, 'خدمات', 0),
];

/* ==========================================================================
 * درختِ منو
 * ======================================================================= */

Tests::group('منو › درخت');

$tree = Menu_Tree::build(7);

Tests::same('سه آیتمِ سطحِ اول', count($tree), 3);
Tests::same('اولی محصولات است', $tree[0]['title'], 'محصولات');
Tests::same('و دو فرزند دارد', count($tree[0]['children']), 2);
Tests::same('فرزندِ فرزند هم خوانده می‌شود', count($tree[0]['children'][0]['children']), 1);
Tests::same('آیتمِ بی‌فرزند، فرزندِ خالی دارد', $tree[2]['children'], []);

/*
 * ‎current-menu-ancestor‎ هم باید «فعال» حساب شود. اگر فقط
 * ‎current-menu-item‎ سنجیده می‌شد، در صفحهٔ یک زیرمنو زیرخطِ والد
 * خاموش می‌ماند — دقیقاً همان حالتی که طرح نشان می‌دهد.
 */
Tests::ok('والدِ صفحهٔ باز هم فعال است', $tree[0]['current']);
Tests::ok('و آیتمِ بی‌ربط فعال نیست', !$tree[2]['current']);

/* آیتمِ دسته ترم دارد، آیتمِ لینکِ دلخواه ندارد */
Tests::same('آیتمِ دسته شناسهٔ ترم دارد', $tree[0]['children'][0]['term_id'], 11);
Tests::same('آیتمِ لینکِ دلخواه ندارد', $tree[1]['term_id'], 0);

Tests::same('شمارشِ ترم از ووکامرس می‌آید', Menu_Tree::term_count(11), 141);
Tests::same('ترمِ ناموجود صفر می‌دهد', Menu_Tree::term_count(999), 0);
Tests::same('شمارشِ کلِ فروشگاه', Menu_Tree::shop_count(), 250);

/* ==========================================================================
 * گزینه‌هایِ پنل
 *
 * این‌ها هیچ‌وقت در خروجیِ رندر دیده نمی‌شوند و فقط در پنلِ المنتور
 * معلوم می‌شوند — آن هم به شکلِ «یک کشویِ خالی» که هیچ خطایی نمی‌دهد.
 * ======================================================================= */

Tests::group('منو › گزینه‌هایِ پنل');

$widget_class = Menu::class;

$options = (function () use ($widget_class): array {
    $method = new ReflectionMethod($widget_class, 'top_level_options');
    $method->setAccessible(true);

    return $method->invoke(zig_widget($widget_class));
})();

/*
 * فهرستِ گزینه‌ها نباید به ‎menu_id‎ی انتخاب‌شده وابسته باشد. در لحظهٔ
 * ثبتِ کنترل‌ها هنوز هیچ فهرستی انتخاب نشده، پس وابستگی یعنی کشویِ
 * «کدام آیتم مگامنو باشد؟» همیشه خالی — و مگامنو عملاً غیرقابلِ
 * روشن‌کردن. دقیقاً همین اتفاق افتاده بود.
 */
Tests::ok('گزینه‌ها بدونِ انتخابِ فهرست هم پر می‌شوند', count($options) > 0, 'تعداد: ' . count($options));
Tests::ok('و آیتم‌هایِ سطحِ اول را دارند', in_array('محصولات', $options, true));

/* کلیدها همان شناسه‌ای‌اند که ‎panel_kind()‎ با آن مقایسه می‌کند */
Tests::ok('کلیدشان شناسهٔ آیتم است', isset($options['1']));

/* ==========================================================================
 * رندر
 * ======================================================================= */

Tests::group('منو › رندر');

$widget = zig_widget($widget_class);
$render = static function (array $settings) use ($widget): string {
    return $widget->zig_render($settings);
};

$html = $render(['menu_id' => 7, 'aria_label' => 'منویِ اصلی', 'mega_item' => '1', 'show_counts' => 'yes']);

Tests::keeps('ناوبری نام دارد', $html, 'aria-label="منویِ اصلی"');
Tests::keeps('نوار یک فهرستِ صریح است', $html, '<ul class="zig-menu__bar" role="list">');

/* آیتمِ فعال باید هم کلاس بگیرد هم aria-current — رنگ به‌تنهایی کافی نیست */
Tests::keeps('آیتمِ فعال کلاس می‌گیرد', $html, 'zig-menu__item--current');
Tests::keeps('و برایِ صفحه‌خوان هم اعلام می‌شود', $html, 'aria-current="page"');

/* فلش فقط رویِ آیتمی که واقعاً پنل دارد */
Tests::same('دو آیتم پنل دارند', substr_count($html, 'zig-menu__item--has-panel'), 2);
Tests::same('و دو فلش رندر شده', substr_count($html, 'zig-menu__chevron'), 2);

Tests::keeps('محصولات مگامنو گرفته', $html, 'zig-menu__panel--mega');
Tests::keeps('دانلود نرم‌افزار فهرستِ ساده', $html, 'zig-menu__panel--list');

/* شمارش از ووکامرس، با جداکنندهٔ هزارگان */
Tests::keeps('شمارشِ ستون نمایش داده می‌شود', $html, '141 محصول');
Tests::keeps('و ستونِ دوم هم', $html, '95 محصول');

$html_no_counts = $render(['menu_id' => 7, 'mega_item' => '1', 'show_counts' => '']);
// نه رشتهٔ «محصول» — که داخلِ عنوانِ «محصولات» هم هست — بلکه خودِ عنصر
Tests::blocks('کلیدِ خاموش، شمارش را می‌برد', $html_no_counts, 'zig-menu__count');

/*
 * ترتیبِ اولویتِ شکلِ پنل: قالبِ المنتور صریح‌ترین انتخابِ مدیر است و
 * باید بر مگامنو بچربد، وگرنه تنظیمِ قالب بی‌صدا نادیده گرفته می‌شود.
 */
$html_template = $render([
    'menu_id'         => 7,
    'mega_item'       => '1',
    'template_panels' => [['_id' => 't', 'item_id' => '1', 'template_id' => '55']],
]);

Tests::keeps('قالبِ المنتور بر مگامنو می‌چربد', $html_template, 'zig-menu__panel--template');
Tests::blocks('و مگامنو دیگر رندر نمی‌شود', $html_template, 'zig-menu__panel--mega');

/* بدونِ فهرست، هیچ خروجی — نه یک ناوبریِ خالی */
Tests::same('فهرستِ خالی خروجی ندارد', $render(['menu_id' => 999]), '');

/* --------------------------------------------------------------------------
 * ستونِ «محبوب‌ترین‌ها»
 *
 * ستونِ آخرِ مگامنو یکی از دسته‌هایِ فهرست نیست — ستونی جداست که به کلِ
 * فروشگاه وصل است. اگر دوباره به «اولین فرزند» گره بخورد، هم جایش در طرح
 * غلط می‌شود هم یک دسته از فهرست می‌افتد.
 * ----------------------------------------------------------------------- */

set_transient(zig_popular_key(0, 3), [901, 902, 903], 0);

$html_cards = $render([
    'menu_id'       => 7,
    'mega_item'     => '1',
    'mega_cards'    => 3,
    'show_counts'   => 'yes',
    'popular_title' => 'محبوب‌ترین ها',
    'popular_cta'   => 'مشاهده تمام محصولات',
]);

Tests::same('هر دو دسته ستونِ فهرستی گرفتند', substr_count($html_cards, 'zig-menu__col-body'), 2);
Tests::same('و یک ستونِ کارت', substr_count($html_cards, 'zig-menu__col--cards'), 1);
Tests::same('سه کارت رندر شد', substr_count($html_cards, 'zig-menu__card"'), 3);

Tests::keeps('عنوانِ ستونِ کارت‌ها از تنظیمات می‌آید', $html_cards, 'محبوب‌ترین ها');
Tests::keeps('متنِ دکمه‌اش هم', $html_cards, 'مشاهده تمام محصولات');

/* شمارشِ این ستون کلِ فروشگاه است، نه شمارشِ یک دسته */
Tests::keeps('شمارشش کلِ فروشگاه است', $html_cards, '250 محصول');

/* ستونِ کارت آخر است — بعد از هر دو ستونِ فهرستی */
Tests::ok(
    'ستونِ کارت در انتهایِ مگامنو است',
    strrpos($html_cards, 'zig-menu__col-body') < strpos($html_cards, 'zig-menu__col--cards')
);

/* صفر یعنی ستون اصلاً نباشد، نه ستونِ خالی */
$html_zero = $render(['menu_id' => 7, 'mega_item' => '1', 'mega_cards' => 0]);

Tests::blocks('صفر، ستونِ کارت را کاملاً می‌برد', $html_zero, 'zig-menu__col--cards');

/*
 * متنِ دکمهٔ ستونِ دسته از فیلدِ توضیحِ همان آیتم می‌آید، نه از عنوانش —
 * در طرح این دو با هم فرق دارند.
 */
$GLOBALS['__zig_menu_items'][8] = [
    zig_menu_item(1, 'محصولات'),
    zig_menu_item(11, 'انواع قطعات', 1, $category(11) + ['description' => 'دسته بندی قطعات یدکی']),
];

$html_desc = $render(['menu_id' => 8, 'mega_item' => '1', 'mega_cards' => 0]);

Tests::keeps('دکمه متنِ توضیحِ آیتم را می‌گیرد', $html_desc, 'دسته بندی قطعات یدکی');
Tests::keeps('و عنوانِ ستون سرِ جایش می‌ماند', $html_desc, '<span class="zig-menu__col-title"><bdi>انواع قطعات</bdi>');

/* ==========================================================================
 * کشویِ موبایل
 *
 * نسخهٔ موبایل *همان درخت* است با ناوبریِ دیگر. چیزهایی که اینجا سنجیده
 * می‌شوند، همان‌هایی‌اند که در پنلِ المنتور سالم به نظر می‌رسند ولی رویِ
 * گوشی می‌شکنند.
 * ======================================================================= */

Tests::group('منو › کشویِ موبایل');

$mobile = $render([
    'menu_id'     => 7,
    'mega_item'   => '1',
    'mega_cards'  => 0,
    'show_counts' => 'yes',
    'close_label' => 'بستن',
    'back_label'  => 'بازگشت',
    'open_label'  => 'منو',
]);

Tests::keeps('دکمهٔ بازکننده هست', $mobile, 'class="zig-menu__trigger"');
Tests::keeps('و به کشو وصل است', $mobile, 'aria-controls="zig-menu-sheet-testid"');
Tests::keeps('کشو همان شناسه را دارد', $mobile, 'id="zig-menu-sheet-testid"');
Tests::keeps('کشو یک دیالوگ است', $mobile, 'role="dialog"');

/* دکمهٔ آیکون‌تنها باید نامِ خوانا داشته باشد وگرنه صفحه‌خوان «دکمه» می‌گوید */
Tests::keeps('دکمه نامِ خوانا دارد', $mobile, '<span class="zig-menu__sr">منو</span>');

/*
 * هر دو متن رویِ خودِ دکمه‌اند تا اسکریپت مجبور نشود رشتهٔ فارسی هاردکد
 * کند — و مدیر بتواند از تبِ محتوا عوضشان کند.
 */
Tests::keeps('متنِ بستن روی دکمه است', $mobile, 'data-close-label="بستن"');
Tests::keeps('متنِ بازگشت هم', $mobile, 'data-back-label="بازگشت"');

/* هر دو آیکون در DOM‌اند و CSS یکی را نشان می‌دهد — نه ساختنِ HTML در JS */
Tests::keeps('آیکونِ بستن هست', $mobile, "data-icon=\"close\"");
Tests::keeps('آیکونِ بازگشت هم', $mobile, "data-icon=\"back\"");

/* لایهٔ ریشه باز است و لایه‌هایِ تودرتو بسته */
Tests::keeps('لایهٔ ریشه هست', $mobile, 'data-level="root"');
Tests::blocks('و پنهان نیست', $mobile, 'data-level="root" hidden');
Tests::keeps('لایهٔ فرزند پنهان است', $mobile, 'data-level="1" hidden');

/*
 * ردیفِ فرزنددار دکمه است نه پیوند: تپ رویش تو می‌رود. اگر پیوند بماند،
 * تپ صفحه را عوض می‌کند و کشو اصلاً لایهٔ دوم را نشان نمی‌دهد.
 */
Tests::keeps('ردیفِ فرزنددار دکمه است', $mobile, '<button type="button" class="zig-menu__m-link" data-open-level="1"');
Tests::keeps('و آیتمِ بی‌فرزند پیوند', $mobile, '<a class="zig-menu__m-link" href="https://zig3d.test/3"');

/*
 * چون تپ رویِ والد تو می‌رود، تنها راهِ رسیدن به *خودِ* صفحهٔ والد ردیفِ
 * «مشاهده …» است. نبودنش یعنی صفحهٔ «محصولات» از موبایل غیرقابلِ دسترس.
 */
Tests::keeps('ردیفِ «مشاهده …» در لایهٔ فرزند هست', $mobile, 'مشاهده محصولات');
Tests::keeps('و به خودِ والد می‌رود', $mobile, '<a class="zig-menu__m-link" href="https://zig3d.test/1"');

/* شمارش در ردیف‌هایِ دستهٔ محصول */
Tests::keeps('ردیفِ دسته شمارش دارد', $mobile, 'zig-menu__m-count');

/* لایهٔ سوم هم ساخته می‌شود، نه فقط دو سطح */
Tests::keeps('لایهٔ سوم هم رندر شده', $mobile, 'data-level="11" hidden');

/* پرده برایِ بستن با تپِ بیرون */
Tests::keeps('پرده هست', $mobile, 'class="zig-menu__backdrop"');

/* قالبِ پایین فقط وقتی انتخاب شده باشد */
Tests::blocks('بدونِ قالب، بلوکِ پایین نمی‌آید', $mobile, 'zig-menu__sheet-foot');

/* جهتِ باز شدن یک کلاس است تا CSS سه چیدمانِ متفاوت بسازد */
Tests::keeps('جهتِ پیش‌فرض لبهٔ شروع است', $mobile, 'zig-menu--sheet-start');
Tests::keeps(
    'و تنظیم‌شدنی است',
    $render(['menu_id' => 7, 'sheet_from' => 'bottom']),
    'zig-menu--sheet-bottom'
);

/* مقدارِ ناشناخته به پیش‌فرض برمی‌گردد، نه به کلاسِ بی‌معنی */
Tests::keeps(
    'مقدارِ ناشناخته بی‌اثر است',
    $render(['menu_id' => 7, 'sheet_from' => '"><script>']),
    'zig-menu--sheet-start'
);

/* ==========================================================================
 * استایل
 * ======================================================================= */

Tests::group('منو › استایل');

$css     = file_get_contents($root . '/assets/css/zig3d-widgets.css');
$section = zig_css_section($css, 'منویِ اصلی');

/*
 * باز شدن باید بدونِ جاوااسکریپت کار کند و *هر دو* حالت لازم‌اند:
 * ‎:hover‎ برایِ موس، ‎:focus-within‎ برایِ کیبورد. اگر دومی بیفتد،
 * کاربرِ کیبورد هیچ‌وقت زیرمنو را نمی‌بیند و هیچ تستِ دیگری نمی‌گیردش.
 */
Tests::keeps('هاور پنل را باز می‌کند', $section, ':hover > .zig-menu__panel');
Tests::keeps('فوکوسِ کیبورد هم', $section, ':focus-within > .zig-menu__panel');

/* پنهان‌شدن با visibility است تا گذار مقدارِ شروع داشته باشد */
Tests::keeps('پنل با visibility پنهان می‌شود', $section, 'visibility 0s linear var(--zig-menu-anim, 150ms)');

/* لبهٔ زیرمنویِ ساده با لبهٔ عنوانِ آیتم یکی می‌شود */
Tests::keeps('لبهٔ زیرمنو با لبهٔ عنوانِ آیتم یکی می‌شود', $section, 'inset-inline-start: var(--zig-menu-item-pad-inline, 16px);');

/*
 * مگامنو وسطِ ویوپورت. جابه‌جاییِ افقی از راهِ متغیر می‌آید نه یک
 * ‎transform‎ی جدا — چون ‎transform‎ی خودِ پنل تا خوردنش را هم دارد و
 * نوشتنِ دوباره‌اش یکی از آن دو را پاک می‌کرد.
 */
Tests::keeps('مگامنو وسطِ صفحه می‌نشیند', $section, '--zig-menu-panel-shift: -50%;');
Tests::keeps('و جابه‌جایی داخلِ همان transform است', $section, 'transform: translateX(var(--zig-menu-panel-shift, 0))');

$unscoped = [];

preg_match_all('/(?m)^([^@{}\/\s][^{}]*?)\s*\{/', $section, $matches);

foreach ($matches[1] as $group) {
    foreach (explode(',', $group) as $selector) {
        $selector = trim($selector);

        if ('' === $selector || 0 === strpos($selector, '.zig-menu') || 0 === strpos($selector, 'nav.zig-menu')) {
            continue;
        }

        $unscoped[] = $selector;
    }
}

Tests::same('هر انتخاب‌گر با ریشهٔ .zig-menu شروع می‌شود', $unscoped, []);

/*
 * زیرخط باید در هاور و فوکوس هم بیاید، نه فقط رویِ صفحهٔ جاری. و باید
 * *همیشه* در DOM باشد و فقط محو/پیدا شود — اگر ساختِ ‎::after‎ به هاور
 * گره بخورد، گذاری در کار نیست و خط جهشی ظاهر می‌شود.
 */
Tests::keeps('زیرخط بی‌قید ساخته می‌شود', $section, ".zig-menu a.zig-menu__link .zig-menu__label::after {");
Tests::keeps('هاور زیرخط را روشن می‌کند', $section, ':hover > a.zig-menu__link .zig-menu__label::after');
Tests::keeps('فوکوسِ کیبورد هم زیرخط می‌گیرد', $section, ':focus-within > a.zig-menu__link .zig-menu__label::after');

/*
 * تا خوردنِ پنل. ‎perspective‎ باید داخلِ خودِ ‎transform‎ باشد نه رویِ
 * والد: پنلِ مگا ‎fixed‎ است و پرسپکتیوِ والد به آن نمی‌رسد.
 */
Tests::keeps('پنل تا می‌خورد', $section, 'rotateX(var(--zig-menu-fold, 90deg))');
Tests::keeps('و پرسپکتیو داخلِ همان transform است', $section, 'perspective(var(--zig-menu-perspective, 1400px))');
Tests::keeps('باز که شد، صاف می‌ایستد', $section, 'rotateX(0deg)');

/* پرده پشتِ زیرمنو */
Tests::keeps('پرده هست', $section, '.zig-menu .zig-menu__scrim {');
Tests::keeps('و با هاورِ آیتمِ پنل‌دار می‌آید', $section, ':has(li.zig-menu__item--has-panel:hover) .zig-menu__scrim');

/*
 * پرده تمامِ ویوپورت را می‌گیرد. بدونِ ‎pointer-events: none‎ همان لحظه
 * که ظاهر می‌شود جلویِ هاورِ خودِ منو را می‌گیرد و منو بی‌وقفه باز و
 * بسته می‌شود — حلقه‌ای که فقط در مرورگر دیده می‌شود.
 */
Tests::keeps('پرده جلویِ هاور را نمی‌گیرد', $section, 'pointer-events: none;');

/*
 * با حرکتِ کم، زاویه هم باید صفر شود نه فقط مدت: با مدتِ صفر و زاویهٔ
 * ۹۰، پنل لبه‌به‌لبه و جهشی ظاهر می‌شد.
 */
$reduced_desktop = zig_css_block($section, '@media (prefers-reduced-motion: reduce)');

Tests::keeps('حرکتِ کم، تا خوردن را برمی‌دارد', $reduced_desktop, '--zig-menu-fold: 0deg;');

/* لینک‌ها نامِ تگ می‌گیرند وگرنه رنگ/زیرخطِ قالب رویشان می‌نشیند */
foreach (['a.zig-menu__link', 'a.zig-menu__sub-link', 'a.zig-menu__cta', 'a.zig-menu__card'] as $selector) {
    Tests::keeps('انتخاب‌گرِ ' . $selector . ' نامِ تگ دارد', $section, $selector);
}

/*
 * ستون‌هایِ مگامنو در طرح خطِ میانی ندارند — تنها خطشان زیرِ سرتیتر است.
 * اگر قاعدهٔ جداکننده بی‌دامنه بماند، مگامنو هم خط‌خطی می‌شود و هیچ سنجهٔ
 * دیگری نمی‌گیردش.
 */
Tests::blocks(
    'جداکننده به زیرمنویِ ساده محدود است',
    $section,
    "\n.zig-menu li.zig-menu__sub-item + li.zig-menu__sub-item {"
);

/* ==========================================================================
 * پوششِ کنترل‌ها
 *
 * هر متغیرِ CSSی که این بخش می‌خوانَد باید یک کنترلِ نویسنده در ویجت
 * داشته باشد، وگرنه بخشی از دیزاین از تبِ استایل قابلِ تغییر نیست. این
 * سنجه خودکار است: با افزودنِ هر متغیرِ تازه بدونِ کنترل، خودش می‌شکند.
 * ======================================================================= */

Tests::group('منو › پوششِ کنترل‌ها');

preg_match_all('/var\(\s*(--zig-menu-[a-z0-9-]+)/', $section, $var_matches);

$used          = array_values(array_unique($var_matches[1]));
$widget_source = file_get_contents($root . '/includes/widgets/menu.php');

preg_match_all('/(--zig-menu-[a-z0-9-]+)\s*:/', $widget_source, $written_matches);

/*
 * دو راهِ پذیرفته برایِ «تنظیم‌شدنی بودن»:
 *
 *   ۱. یک کنترلِ المنتور مقدارش را می‌نویسد — حالتِ عادی.
 *   ۲. خودِ CSS جایی مقدارش را می‌گذارد — متغیرهایِ داخلیِ چیدمان، مثلِ
 *      جابه‌جاییِ افقیِ مگامنو، که تنظیمِ کاربر نیستند و نباید کنترل
 *      بگیرند.
 *
 * چیزی که این سنجه جلویش را می‌گیرد همچنان همان است: متغیری که هیچ‌جا
 * مقدار نمی‌گیرد، یعنی گوشه‌ای از دیزاین که از تبِ استایل در دسترس نیست.
 */
preg_match_all('/(?m)^\s*(--zig-menu-[a-z0-9-]+)\s*:/', $section, $css_set_matches);

$written = array_unique(array_merge($written_matches[1], $css_set_matches[1]));

sort($used);

$uncontrolled = array_values(array_diff($used, $written));

Tests::ok('این بخش متغیر دارد', count($used) > 30);
Tests::same('هر متغیر یک کنترل دارد', $uncontrolled, []);

/* ==========================================================================
 * استایل › کشویِ موبایل
 * ======================================================================= */

Tests::group('منو › استایل › کشو');

/*
 * جدا شدنِ دو نسخه فقط کارِ برک‌پوینت است. اگر نوارِ دسکتاپ در موبایل
 * پنهان نشود، هر دو با هم دیده می‌شوند.
 */
Tests::keeps('نوارِ دسکتاپ در موبایل پنهان می‌شود', $section, 'ul.zig-menu__bar {
		display: none;');

/*
 * ‎translateX‎ فیزیکی است و در راست‌چین باید آینه شود، وگرنه کشو از لبهٔ
 * مخالف وارد می‌شود و از رویِ کلِ صفحه رد می‌شود.
 */
Tests::keeps('جهتِ کشو در راست‌چین آینه می‌شود', $section, '.zig-menu--sheet-start .zig-menu__sheet:dir(rtl)');

/* پنهان‌شدن با visibility تا گذار مقدارِ شروع داشته باشد */
Tests::keeps('کشو با visibility پنهان می‌شود', $section, 'visibility 0s linear var(--zig-menu-anim, 150ms)');

/*
 * ‎transition-duration: 0s‎ و نه ‎transition: none‎ — اسکریپت همین مدت را
 * می‌خوانَد تا بداند کِی کشو واقعاً پنهان شده. با ‎none‎ عدد صفر
 * برنمی‌گردد و کشو برایِ همیشه باز می‌مانْد.
 */
$reduced = zig_css_block($section, '@media (max-width: 1023px) and (prefers-reduced-motion: reduce)');

// توضیحات کنار می‌روند: خودِ همین قاعده در توضیحش «transition: none» را
// به‌عنوانِ کارِ *نکردنی* نام می‌برد و بدونِ این، سنجه رویِ متنِ توضیح می‌افتاد
$reduced_rules = preg_replace('#/\*.*?\*/#s', '', $reduced);

Tests::keeps('حرکتِ کم، مدت را صفر می‌کند', $reduced_rules, 'transition-duration: 0s;');
Tests::blocks('نه اینکه گذار را حذف کند', $reduced_rules, 'transition: none');

/* قفلِ اسکرول کلاسِ خودش را دارد، جدا از سرچ */
Tests::keeps('قفلِ اسکرول کلاسِ جدا دارد', $section, '.zig-menu-sheet-open');

/* ==========================================================================
 * اسکریپت
 * ======================================================================= */

Tests::group('منو › اسکریپت');

$js = file_get_contents($root . '/assets/js/zig3d-menu.js');

/*
 * عددِ برک‌پوینت نباید در جاوااسکریپت تکرار شود. تکرارش یعنی روزی که
 * CSS عوض شود، اسکریپت بی‌صدا با آن واگرا می‌شود.
 */
Tests::blocks('عددِ برک‌پوینت در اسکریپت تکرار نشده', $js, '1023');
Tests::keeps('به‌جایش display را می‌خوانَد', $js, "getComputedStyle(this.trigger).display");

/* مدتِ گذار هم از CSS خوانده می‌شود نه از یک ثابت */
Tests::keeps('مدتِ گذار از CSS می‌آید', $js, 'transitionDuration');

/* هیچ HTMLی از اسکریپت ساخته نمی‌شود */
Tests::blocks('اسکریپت innerHTML نمی‌نویسد', $js, 'innerHTML');

/* متنِ دکمه از HTML می‌آید تا ترجمه‌پذیر بماند */
Tests::keeps('متنِ دکمه از صفت خوانده می‌شود', $js, "getAttribute(nested ? 'data-back-label' : 'data-close-label')");

/* Esc باید ببندد */
Tests::keeps('Esc کشو را می‌بندد', $js, "'Escape' !== event.key");
