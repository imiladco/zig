<?php
/**
 * ویجتِ سرچ.
 *
 * سه چیز اینجا سنجیده می‌شود که هرکدام اگر بشکنند، هیچ خطایی در پنلِ
 * المنتور نشان نمی‌دهند:
 *
 *   ۱. پلِ بینِ کنترل‌ها و ‎Search_Query‎ (‎search_args()‎/‎hydrate_args()‎) —
 *      اگر نامِ یک کلید اینجا با نامی که ‎Search_Endpoint‎ می‌خواند فرق کند،
 *      هر جست‌وجویی بی‌صدا با تنظیماتِ پیش‌فرض اجرا می‌شود، نه تنظیماتِ
 *      واقعیِ ادمین.
 *   ۲. قراردادِ کلیک: ردیفِ محصول به پرمالینکِ خودش می‌رود، چیپ به صفحهٔ
 *      نتایج — این دو نباید هیچ‌وقت جا‌به‌جا شوند.
 *   ۳. دامنهٔ سلکتورها، همان سنجه‌ای که در بقیهٔ ویجت‌های این افزونه هست.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/elementor-stub.php';
require_once __DIR__ . '/lib/woocommerce-stub.php';

$root = dirname(__DIR__);

require_once $root . '/includes/selector.php';
require_once $root . '/includes/design-icons.php';
require_once $root . '/includes/attributes.php';
require_once $root . '/includes/archive-query.php';
require_once $root . '/includes/search-normalizer.php';
require_once $root . '/includes/search-query.php';
require_once $root . '/includes/search-endpoint.php';
require_once $root . '/includes/widgets/traits/box.php';
require_once $root . '/includes/widgets/search.php';

use Zig3d_Widgets\Widgets\Search;

if (!function_exists('get_search_link')) {
    function get_search_link($query = '') {
        return 'https://zig3d.test/?s=' . rawurlencode((string) $query);
    }
}
if (!function_exists('home_url')) {
    function home_url($path = '/') { return 'https://zig3d.test' . $path; }
}
if (!function_exists('rest_url')) {
    function rest_url($path = '') { return 'https://zig3d.test/wp-json/' . ltrim((string) $path, '/'); }
}
if (!function_exists('admin_url')) {
    function admin_url($path = '') { return 'https://zig3d.test/wp-admin/' . ltrim((string) $path, '/'); }
}

$GLOBALS['__zig_post'] = 0;

$widget = zig_widget(Search::class);
$render = static function (array $settings) use ($widget): string {
    return $widget->zig_render($settings);
};

/* ==========================================================================
 * پلِ تنظیمات › search_args()
 * ======================================================================= */

Tests::group('ویجتِ سرچ › search_args');

$args = $widget->search_args([
    'result_limit'     => 12,
    'search_fields'    => ['title', 'sku'],
    'category_source'  => 'product_cat',
    'brand_source'     => 'product_brand',
    'synonym_pairs'    => [
        ['term_a' => 'میلینگ', 'term_b' => 'فرز'],
        ['term_a' => 'ناقص', 'term_b' => ''],   // باید حذف شود
    ],
]);

Tests::same('نوعِ پست همیشه محصول است', $args['post_type'], 'product');
Tests::same('سقفِ نتیجه از تنظیمات می‌آید', $args['limit'], 12);
Tests::same('فیلدهایِ جست‌وجو از تنظیمات می‌آید', $args['search_fields'], ['title', 'sku']);
Tests::same('هر دو تاکسونومی به match_taxonomies می‌رسند', $args['match_taxonomies'], ['product_cat', 'product_brand']);
Tests::same('فقط جفتِ کاملِ مترادف می‌ماند', $args['synonym_pairs'], [['میلینگ', 'فرز']]);

$args_no_brand = $widget->search_args(['category_source' => 'product_cat', 'brand_source' => '']);
Tests::same('تاکسونومیِ خالی وارد match_taxonomies نمی‌شود', $args_no_brand['match_taxonomies'], ['product_cat']);

$args_defaults = $widget->search_args([]);
Tests::same('بدونِ تنظیم، فقط عنوان جست‌وجو می‌شود', $args_defaults['search_fields'], ['title']);

/* ==========================================================================
 * پلِ تنظیمات › hydrate_args()
 * ======================================================================= */

Tests::group('ویجتِ سرچ › hydrate_args');

$hydrate = $widget->hydrate_args(['category_source' => 'product_cat', 'brand_source' => 'product_brand']);

Tests::same('تاکسونومیِ دسته درست منتقل می‌شود', $hydrate['category_taxonomy'], 'product_cat');
Tests::same('تاکسونومیِ برند درست منتقل می‌شود', $hydrate['brand_taxonomy'], 'product_brand');
Tests::same('نوعِ پست همیشه محصول است', $hydrate['post_type'], 'product');

/* ==========================================================================
 * رندر › حالتِ بستهٔ پیش‌فرض (S0)
 * ======================================================================= */

Tests::group('ویجتِ سرچ › رندرِ اسکلت (S0)');

$html = $render([
    'placeholder_text'    => 'جستجوی محصول',
    'search_icon'         => ['value' => 'fas fa-search', 'library' => 'fa-solid'],
    'clear_icon'          => ['value' => 'fas fa-times', 'library' => 'fa-solid'],
    'empty_icon'          => ['value' => 'fas fa-search', 'library' => 'fa-solid'],
    'popular_icon'        => ['value' => 'fas fa-arrow-up-right-from-square', 'library' => 'fa-solid'],
    'chevron_icon'        => ['value' => 'fas fa-chevron-left', 'library' => 'fa-solid'],
    'more_icon'           => ['value' => 'fas fa-arrow-left', 'library' => 'fa-solid'],
    'enable_recent'       => 'yes',
    'popular_searches'    => [['label' => 'میلینگ ماشین'], ['label' => '  ']],
]);

Tests::keeps('یک shellِ واحد رندر می‌شود', $html, 'zig-search__shell');
Tests::keeps('فیلد داخلِ shell است', $html, 'zig-search__field');
Tests::keeps('پنل داخلِ shell است، در ابتدا مخفی', $html, 'zig-search__panel');
Tests::ok('پنل با ویژگیِ hidden رندر می‌شود — یعنی S0 فقط فیلد را نشان می‌دهد', (bool) preg_match('/zig-search__panel[^>]*\bhidden\b/', $html));
Tests::keeps('placeholder روی input نشسته', $html, 'placeholder="جستجوی محصول"');
Tests::keeps('role=combobox رویِ input هست (WAI-ARIA)', $html, 'role="combobox"');
Tests::keeps('aria-controls به id پنل اشاره می‌کند', $html, 'aria-controls="zig-search-panel-testid"');
Tests::keeps('role=listbox روی پنل هست', $html, 'role="listbox"');

Tests::same(
    'برچسبِ فقط-فاصله از جستجوهایِ پرطرفدار حذف می‌شود',
    substr_count($html, 'zig-search__chip--popular'),
    1
);

/*
 * طبقِ طرحِ تأییدشده (مرجعِ «Overlay+Border»)، هر دو حالتِ بسته — چه
 * خالی، چه با مقدارِ حفظ‌شده — بدونِ ضربدرند؛ و هر چهار حالتِ باز، حتی
 * «پیش فرض»ی که فیلدش خالی است، ضربدر دارند. یعنی این دکمه به *باز
 * بودنِ پنل* گره خورده نه به تایپ‌شدنِ متن. رندرِ سمتِ سرور همیشه بسته
 * است، پس همیشه ‎hidden‎ شروع می‌شود.
 */
Tests::ok(
    'دکمهٔ پاک‌کردن با ویژگیِ hidden رندر می‌شود — یعنی حالتِ پیش‌فرض بدونِ X است',
    false !== strpos($html, 'zig-search__clear" hidden')
);

/*
 * ترتیبِ آیکون نسبت به متن مهم است، نه فقط وجودش: در راست‌به‌چپ، آیکونی
 * که *بعدِ* متن بیاید سمتِ چپِ چیپ می‌نشیند — همان‌جا که طرح گذاشته. اگر
 * کسی جایشان را عوض کند، آیکون به سمتِ راست می‌پرد بدونِ اینکه هیچ تستی
 * بشکند، مگر این.
 */
Tests::ok(
    'در چیپِ پرطرفدار، آیکون بعدِ متن می‌آید (یعنی سمتِ چپ در RTL)',
    (bool) preg_match('/zig-search__chip--popular[^>]*>\s*<bdi>[^<]*<\/bdi>\s*<svg/', $html)
);

/*
 * ‎<bdi>‎ نه تزئین است: برچسبِ چیپ می‌تواند ترکیبِ فارسی و لاتین باشد
 * («UP3D میلینگ») و بدونِ ایزوله، الگوریتمِ دوجهته تکهٔ لاتین را جابه‌جا
 * نشان می‌دهد.
 */
Tests::keeps('برچسبِ چیپ داخلِ bdi است', $html, '<bdi>میلینگ ماشین</bdi>');

Tests::ok(
    'دکمهٔ «بیشتر» هم متن‌اول-آیکون‌دوم است',
    (bool) preg_match('/zig-search__more[^>]*>\s*<span>[^<]*<\/span>\s*<svg/', $html)
);

/*
 * چیپِ «جستجویِ اخیر» در طرح هیچ دکمهٔ حذفِ تکی‌ای ندارد — فقط لینکِ
 * سطحِ‌بخشِ «پاک‌کردن». اگر این کلاس برگردد، یعنی دوباره چیزی اضافه شده
 * که در طرح نیست.
 */
Tests::ok('هیچ دکمهٔ حذفِ تکیِ چیپ رندر نمی‌شود', false === strpos($html, 'zig-search__chip-remove'));

/* ==========================================================================
 * قراردادِ کلیک — چیپِ پرطرفدار به صفحهٔ نتایج می‌رود
 * ======================================================================= */

Tests::group('ویجتِ سرچ › قراردادِ کلیک (چیپ)');

Tests::keeps(
    'چیپِ پرطرفدار یک لینکِ واقعی به صفحهٔ نتایج است، نه یک دکمهٔ بدونِ href',
    $html,
    '<a class="zig-search__chip zig-search__chip--popular" href="https://zig3d.test/?s=' . rawurlencode('میلینگ ماشین')
);

/*
 * دامنهٔ صفحهٔ نتایج باید همان دامنهٔ اورلی باشد. بدونِ ‎post_type=product‎
 * کاربر از فهرستی از محصولات به سرچِ عمومیِ وردپرس می‌رسد که نوشته و
 * برگه هم دارد — همان ناهماهنگی‌ای که هیچ خطایی نمی‌دهد و فقط نتیجه را
 * بی‌ربط می‌کند.
 */
Tests::keeps('و دامنه‌اش هم مثلِ خودِ اورلی فقط محصول است', $html, 'post_type=product');
Tests::keeps('قالبِ آدرسِ سمتِ کلاینت هم همان دامنه را دارد', $html, 'data-results-url-template="https://zig3d.test/?s=zzzZIGQUERYzzz&amp;post_type=product"');

/* ==========================================================================
 * تنظیماتِ رفتار در data-* — چیزی که جاوااسکریپت می‌خواند
 * ======================================================================= */

Tests::group('ویجتِ سرچ › تنظیماتِ در دسترسِ کلاینت');

$html_cfg = $render([
    'min_chars'     => 3,
    'debounce_ms'   => 450,
    'enable_shortcut' => 'yes',
    'enable_recent' => 'yes',
    'recent_max'    => 7,
    'recent_expiry_days' => 14,
]);

Tests::keeps('حداقلِ کاراکتر در data-min-chars هست', $html_cfg, 'data-min-chars="3"');
Tests::keeps('تأخیر در data-debounce هست', $html_cfg, 'data-debounce="450"');
Tests::keeps('شناسهٔ ویجت برایِ رزولوشنِ سمتِ سرور می‌رود', $html_cfg, 'data-widget-id="testid"');
Tests::keeps('نامِ اکشنِ admin-ajax با Search_Endpoint::ACTION یکی است', $html_cfg, 'data-ajax-action="' . \Zig3d_Widgets\Search_Endpoint::ACTION . '"');
Tests::keeps('سقفِ تعدادِ تاریخچه در data-recent-max هست', $html_cfg, 'data-recent-max="7"');
Tests::keeps('انقضایِ تاریخچه در data-recent-expiry-days هست', $html_cfg, 'data-recent-expiry-days="14"');

/* ==========================================================================
 * کنترل‌ها و سلکتورها
 * ======================================================================= */

Tests::group('ویجتِ سرچ › کنترل‌ها');

$controls = zig_collect_controls(Search::class);

foreach ([
    'min_chars', 'debounce_ms', 'result_limit', 'search_fields', 'enable_shortcut',
    'category_source', 'brand_source',
    'placeholder_text', 'empty_message', 'products_heading_text', 'more_button_text', 'recent_heading_text', 'clear_history_text', 'popular_heading_text',
    'search_icon', 'clear_icon', 'chevron_icon', 'empty_icon', 'recent_icon', 'popular_icon', 'more_icon',
    'popular_searches', 'enable_recent', 'recent_max', 'recent_expiry_days', 'synonym_pairs',
    'TABS:field_box_tabs', 'field_height', 'field_icon_size', 'field_clear_size', 'field_icon_color', 'group:field_typography',
    'TABS:shell_box_tabs', 'panel_gap', 'panel_max_height',
    'group:section_title_typography', 'group:clear_history_typography',
    'TABS:product_box_tabs', 'product_image_size', 'group:product_title_typography', 'group:product_meta_typography', 'product_chevron_color',
    'TABS:recent_chip_box_tabs', 'recent_chip_text_color',
    'TABS:popular_chip_box_tabs', 'popular_chip_text_color',
    'TABS:more_button_box_tabs', 'group:more_button_typography',
    'empty_icon_size', 'empty_icon_color', 'group:empty_text_typography',
] as $control) {
    Tests::ok('کنترل موجود است: ' . $control, in_array($control, $controls, true));
}

/*
 * طرح هیچ دکمهٔ حذفِ تکیِ چیپ ندارد — فقط لینکِ سطحِ‌بخشِ «پاک‌کردن».
 * این کنترل‌ها نباید برگردند، وگرنه یعنی همان اضافه‌کاریِ قبلی دوباره
 * تکرار شده.
 */
foreach (['remove_icon', 'recent_chip_remove_color'] as $control) {
    Tests::ok('کنترلِ حذف‌شده برنمی‌گردد: ' . $control, !in_array($control, $controls, true));
}

Tests::group('ویجتِ سرچ › دامنهٔ سلکتورها');

foreach (zig_collect_selectors(Search::class) as [$control, $selector]) {
    Tests::ok('سلکتورِ ' . $control . ' به {{WRAPPER}} مقید است', false !== strpos($selector, '{{WRAPPER}}'));
}

/* ==========================================================================
 * وزنِ انتخاب‌گرهایِ CSS
 *
 * این گروه یک باگِ واقعی را نگه می‌دارد که دوبار افتاد و هیچ تستی
 * نمی‌گرفتش: قواعدِ تک‌کلاسی (‎.zig-search__input‎) از استایلِ فرمِ
 * قالب/ووکامرس (‎.elementor input[type="text"]‎ و هم‌خانواده‌هایش)
 * ضعیف‌ترند، پس رویِ سایتِ واقعی ورودی کادرِ سفیدِ قالب را می‌گرفت و
 * لینک‌ها آبیِ زیرخط‌دار می‌شدند — در حالی که خروجیِ رندر کاملاً درست
 * بود و همهٔ سنجه‌ها سبز.
 *
 * دو شرط، و هر دو لازم‌اند:
 *   • هر قاعده با ریشهٔ ‎.zig-search‎ شروع شود (وزن را به ‎(0,2,0)‎ می‌برد).
 *   • رویِ تگ‌هایی که قالب‌ها استایلشان می‌دهند، نامِ تگ هم بیاید.
 * ======================================================================= */

Tests::group('ویجتِ سرچ › وزنِ انتخاب‌گرهایِ CSS');

$css = file_get_contents($root . '/assets/css/zig3d-widgets.css');
$section = substr($css, strpos($css, "\n   سرچ\n"));

preg_match_all('/(?m)^([^@{}\/\s][^{}]*?)\s*\{/', $section, $matches);

$unscoped = [];

foreach ($matches[1] as $group) {
    foreach (explode(',', $group) as $selector) {
        $selector = trim($selector);

        if ('' === $selector || 0 === strpos($selector, '.zig-search')) {
            continue;
        }

        $unscoped[] = $selector;
    }
}

Tests::same(
    'هر انتخاب‌گرِ بخشِ سرچ با ریشهٔ .zig-search شروع می‌شود',
    $unscoped,
    []
);

/*
 * ورودی سخت‌ترین حالت است و دوبار شکست، هر بار یک پله بالاتر:
 *
 *   ۱) ‎[type="text"]‎ در CSS هم‌وزنِ یک کلاس است، پس
 *      ‎.elementor input[type="text"]‎ خودش ‎(0,2,1)‎ می‌شود و زنجیرهٔ
 *      دوکلاسی را *مساوی* می‌کند.
 *   ۲) «استایلِ سراسری»ِ المنتور یک پله جلوتر است:
 *      ‎.elementor-kit-N input:not([type="button"]):not([type="submit"])‎
 *      — چون ‎:not()‎ وزنِ آرگومانش را می‌گیرد، این ‎(0,3,1)‎ است و با
 *      زنجیرهٔ سه‌کلاسیِ ما مساوی می‌شود. شیتِ کیت دیرتر لود می‌شود، پس
 *      سایه و حاشیه و پدینگش رویِ ورودی می‌نشست.
 *
 * جوابِ هر دو یکی است: یک عنصرِ بیشتر، نه یک کلاسِ بیشتر — کلاسِ بیشتر
 * تبِ استایل را (که ‎(0,4,0)‎ است) می‌کشت.
 */
foreach ([
    '.zig-search form.zig-search__field input.zig-search__input',
    '.zig-search form.zig-search__field button.zig-search__icon-btn',
    '.zig-search form.zig-search__field button.zig-search__clear',
    '.zig-search div.zig-search__section-head button.zig-search__clear-history',
] as $selector) {
    Tests::keeps('زنجیرهٔ ' . $selector . ' نامِ تگِ والد را هم دارد', $section, $selector);
}

/*
 * و هیچ‌کدام از فرزندانِ فیلد نباید به شکلِ ضعیف‌ترِ قبلی برگردند —
 * همان چیزی که باگ را ساخت و از رندر معلوم نمی‌شد.
 *
 * سنجه رویِ انتخاب‌گرهایِ پارس‌شده اجرا می‌شود نه رویِ متنِ خام، وگرنه
 * همین توضیحاتِ بالا هم «تطبیق» حساب می‌شدند.
 */
$parsed = [];

foreach ($matches[1] as $group) {
    foreach (explode(',', $group) as $selector) {
        $selector = trim($selector);

        if ('' !== $selector) {
            $parsed[] = $selector;
        }
    }
}

foreach ([
    '.zig-search .zig-search__field ',
    '.zig-search .zig-search__section-head button',
] as $weak) {
    Tests::same(
        'وزنِ ضعیفِ قبلی برنمی‌گردد: ' . $weak,
        array_values(array_filter($parsed, static fn(string $s): bool => 0 === strpos($s, $weak))),
        []
    );
}

/*
 * سقفِ بازه هم باید بماند: اگر روزی کسی یک کلاسِ چهارم به این زنجیره‌ها
 * اضافه کند، به ‎(0,4,x)‎ می‌رسیم و تبِ استایل بی‌اثر می‌شود.
 */
foreach ($matches[1] as $group) {
    foreach (explode(',', $group) as $selector) {
        $selector = trim($selector);

        if ('' === $selector) {
            continue;
        }

        // ‎:hover‎/‎:focus‎ و شبه‌کلاس‌ها عمداً شمرده نمی‌شوند: قاعدهٔ
        // حالت طبیعتاً یک پله بالاتر است و کنترلِ همان حالت هم هست.
        $base    = preg_replace('/:[a-z-]+(\([^)]*\))?/', '', $selector) ?? $selector;
        $classes = preg_match_all('/\.[a-zA-Z_-][\w-]*/', $base);

        if ($classes <= 3) {
            continue;
        }

        Tests::ok('انتخاب‌گر بیش از سه کلاس ندارد: ' . $selector, false);
    }
}

/*
 * عرضِ فیلد نباید با باز شدن تغییر کند.
 *
 * پوسته در حالتِ باز ‎absolute‎ است؛ اگر لبه‌هایش صفر باشند، هم‌عرضِ
 * ریشه می‌شود و پدینگِ افقی از *داخل* می‌خورد — یعنی فیلدِ ۵۰۰ پیکسلی
 * لحظهٔ باز شدن ۴۸۴ می‌شود. در طرح برعکس است: فیلد ۵۰۰ می‌ماند و قاب
 * ۵۱۶ می‌شود. جبران با لبه‌هایِ منفی انجام می‌شود.
 */
Tests::keeps('لبهٔ چپِ پوسته به‌اندازهٔ پدینگ بیرون کشیده می‌شود', $section, 'left: calc(var(--zig-search-shell-pad-left, 8px) * -1);');
Tests::keeps('لبهٔ راستِ پوسته هم همین‌طور', $section, 'right: calc(var(--zig-search-shell-pad-right, 8px) * -1);');
Tests::blocks('لبه‌هایِ پوسته دیگر صفر نیستند', $section, 'inset-inline: 0;');

/*
 * و همان کنترلِ پدینگ باید این دو متغیر را هم بنویسد، وگرنه تغییرِ
 * پدینگ از تبِ استایل، جبران را رویِ عددِ قدیمی جا می‌گذارد و قاب
 * نامتقارن می‌شود — چیزی که در رندر هیچ نشانه‌ای ندارد.
 */
$padding_rules = array_values(array_filter(
    zig_collect_selectors(Search::class),
    static fn(array $entry): bool => 'shell_box_padding' === $entry[0]
));

Tests::ok('کنترلِ پدینگِ پوسته وجود دارد', [] !== $padding_rules);

foreach ($padding_rules as $entry) {
    Tests::ok(
        'کنترلِ پدینگ، متغیرهایِ جبرانِ لبه را هم می‌نویسد',
        false !== strpos($entry[2], '--zig-search-shell-pad-left')
            && false !== strpos($entry[2], '--zig-search-shell-pad-right')
    );
}

/* لینک‌ها هم نامِ تگ می‌گیرند، وگرنه رنگ/زیرخطِ قالب رویشان می‌نشیند */
foreach (['a.zig-search__chip', 'a.zig-search__product', 'a.zig-search__more'] as $selector) {
    Tests::keeps('انتخاب‌گرِ لینکِ ' . $selector . ' نامِ تگ دارد', $section, $selector);
}

/* ==========================================================================
 * اورلی، خطا، و پیش‌فرض‌هایِ قفل‌شده
 * ======================================================================= */

Tests::group('ویجتِ سرچ › اورلی و خطا');

/*
 * بدونِ این لایه، پنلِ بازشده رویِ محتوا می‌نشیند ولی هیچ چیزی پشتش را
 * جدا نمی‌کند — نه بصری و نه برایِ کلیک.
 */
Tests::keeps('لایهٔ تیره رندر می‌شود', $html, 'zig-search__backdrop');
Tests::ok(
    'و در حالتِ بسته پنهان است',
    (bool) preg_match('/zig-search__backdrop"[^>]*\bhidden\b/', $html)
);

/*
 * «خطای فنی» و «نتیجه‌ای نبود» دو چیزند. یکی‌کردنشان یعنی کاربری که
 * شبکه‌اش قطع شده خیال می‌کند محصولی وجود ندارد.
 */
Tests::keeps('بخشِ خطا جدا از بخشِ بدونِ نتیجه است', $html, 'zig-search__section--error');
Tests::keeps('و role=alert دارد', $html, 'role="alert"');
Tests::keeps('پیامِ ۴۲۹ جدا از پیامِ خطایِ عمومی حمل می‌شود', $html, 'data-rate-limit-message=');

Tests::group('ویجتِ سرچ › پیش‌فرض‌ها');

Tests::same('سقفِ نتیجه سه است — همان تعدادی که قابِ طرح نشان می‌دهد', \Zig3d_Widgets\Search_Query::DEFAULT_LIMIT, 3);
Tests::keeps('و پیش‌فرضِ تاریخچه چهار است', $html, 'data-recent-max="4"');

$controls_all = zig_collect_controls(Search::class);

foreach (['error_message', 'rate_limit_message', 'more_button_width'] as $control) {
    Tests::ok('کنترل موجود است: ' . $control, in_array($control, $controls_all, true));
}

/* ==========================================================================
 * دسترسی‌پذیری — چیزهایی که در الگویِ combobox جا افتاده بودند
 *
 * ‎role="option"‎ آگاهانه ماند (نگاه کنید به کامنتِ ‎buildProductRow()‎)،
 * ولی دو حفره‌اش ربطی به آن تصمیم نداشت و پر شد.
 * ======================================================================= */

Tests::group('ویجتِ سرچ › دسترسی‌پذیری');

/*
 * ‎aria-activedescendant‎ فقط گزینهٔ *فعال* را اعلام می‌کند؛ خودِ «سه
 * نتیجه آمد» هیچ‌جا گفته نمی‌شد و کاربرِ نابینا بعدِ تایپ سکوت می‌شنید.
 */
Tests::keeps('ناحیهٔ اعلامِ زنده رندر می‌شود', $html, 'zig-search__status');
Tests::keeps('و polite است نه assertive', $html, 'aria-live="polite"');
Tests::keeps('role=status دارد', $html, 'role="status"');
Tests::keeps('قالبِ متنِ اعلام همراهش می‌رود', $html, 'data-template=');

$js = file_get_contents($root . '/assets/js/zig3d-search.js');

/*
 * جایگاهِ گزینه باید صریح باشد: صفحه‌خوان در یک listboxِ ساخته‌شده با
 * جاوااسکریپت — که بخش‌هایِ پنهانِ کناری هم دارد — نمی‌تواند «۱ از ۳» را
 * از رویِ DOM قابلِ‌اتکا حدس بزند.
 */
Tests::keeps('جایگاهِ هر گزینه صریح اعلام می‌شود', $js, 'aria-posinset');
Tests::keeps('و اندازهٔ مجموعه هم', $js, 'aria-setsize');

/* بستن باید اعلامِ کهنه را هم پاک کند، وگرنه دوباره خوانده می‌شود */
Tests::keeps('بستن ناحیهٔ اعلام را خالی می‌کند', $js, "this.status.textContent = '';");

$controls_a11y = zig_collect_controls(Search::class);
Tests::ok('متنِ اعلام قابلِ ترجمه/تنظیم است', in_array('results_announcement', $controls_a11y, true));

/* ==========================================================================
 * آیکون‌هایِ طرح
 *
 * پیش از این، پیش‌فرضِ هر آیکون یک معادلِ Font Awesome بود که «شبیهِ»
 * آیکونِ فیگما بود نه خودش — و همان باعث می‌شد خروجی با طرح یکی نباشد.
 * حالا فایل‌هایِ ‎assets/icons/*.svg‎ صادرشدهٔ همان نودها هستند و این
 * گروه سه چیز را می‌بندد: اینکه واقعاً درج می‌شوند، اینکه انتخابِ مدیر
 * بر آن‌ها می‌چربد، و اینکه رنگ‌پذیر می‌مانند.
 * ======================================================================= */

Tests::group('ویجتِ سرچ › آیکون‌هایِ طرح');

$icon_files = [
    'search'      => 'ذره‌بینِ فیلد و حالتِ خالی',
    'close'       => 'ضربدرِ پاک‌کردن',
    'chevron'     => 'فلشِ ردیفِ محصول',
    'arrow-left'  => 'فلشِ دکمهٔ بیشتر',
    'trending-up' => 'آیکونِ چیپِ پرطرفدار',
];

foreach ($icon_files as $file => $label) {
    $path = $root . '/assets/icons/' . $file . '.svg';
    Tests::ok('فایلِ آیکون وجود دارد: ' . $file . ' — ' . $label, is_file($path));

    $svg = is_file($path) ? (string) file_get_contents($path) : '';

    /*
     * بدونِ ‎currentColor‎، رنگی که فیگما داخلِ ‎path‎ نوشته سرِ جایش
     * می‌ماند و کنترلِ رنگِ تبِ استایل هیچ اثری ندارد.
     */
    Tests::ok(
        'رنگش currentColor است نه رنگِ ثابتِ فیگما: ' . $file,
        false !== strpos($svg, 'currentColor') && !preg_match('/#[0-9a-fA-F]{3,6}/', $svg)
    );

    // ‎viewBox‎ همان اندازهٔ نودِ فیگماست؛ بدونش مقیاس‌دهیِ CSS می‌شکند.
    Tests::ok('viewBox دارد: ' . $file, false !== strpos($svg, 'viewBox='));
}

/* پیش‌فرضِ خالی + سویچِ روشن ⇒ SVGی طرح، نه هیچ */
$html_design = $render([
    'placeholder_text' => 'جستجوی محصول',
    'popular_searches' => [['label' => 'میلینگ ماشین']],
]);

Tests::keeps(
    'با تنظیماتِ خالی، ذره‌بینِ طرح درج می‌شود',
    $html_design,
    'M8.92723 1.375C4.76282 1.375'
);
Tests::keeps('ضربدرِ طرح هم درج می‌شود', $html_design, 'rotate(-45 8 8)');
Tests::keeps('نمودارِ صعودیِ چیپِ پرطرفدار درج می‌شود', $html_design, 'M1.5 8.5L4.5 5.5L6.5 7.5L10.5 3.5');
Tests::keeps('فلشِ ردیفِ محصول در قالبِ سمتِ کلاینت می‌رود', $html_design, 'data-zig-icon="chevron-icon"');

/*
 * چیپِ «جستجوهایِ اخیر» در طرح آیکون ندارد. قالبش باید خالی برود، وگرنه
 * جاوااسکریپت روی هر چیپِ تاریخچه یک آیکونِ اضافه می‌گذارد.
 */
Tests::keeps('قالبِ چیپِ تاریخچه خالی است', $html_design, '<template data-zig-icon="recent-icon"></template>');

/*
 * انتخابِ مدیر باید بر آیکونِ طرح بچربد. هر دو جایگاهی که از
 * ‎search.svg‎ استفاده می‌کنند (فیلد و حالتِ خالی) عوض می‌شوند، وگرنه
 * ماندنِ مسیرِ SVG در خروجی نشانهٔ چیزی نیست.
 */
$html_custom = $render([
    'placeholder_text' => 'جستجوی محصول',
    'search_icon'      => ['value' => 'fas fa-magnifying-glass', 'library' => 'fa-solid'],
    'empty_icon'       => ['value' => 'fas fa-magnifying-glass', 'library' => 'fa-solid'],
]);

Tests::blocks(
    'انتخابِ مدیر جایِ آیکونِ طرح را می‌گیرد',
    $html_custom,
    'M8.92723 1.375C4.76282 1.375'
);

/* خاموش‌کردنِ سویچ یعنی خالی واقعاً خالی */
$html_off = $render([
    'placeholder_text' => 'جستجوی محصول',
    'design_icons'     => '',
]);

Tests::blocks('با خاموش‌بودنِ سویچ، آیکونِ طرح درج نمی‌شود', $html_off, 'M8.92723 1.375C4.76282 1.375');

/* نامِ خارج از الگو نباید به فایلی بیرونِ پوشهٔ آیکون‌ها برسد */
Tests::same('نامِ نامعتبر رشتهٔ خالی می‌دهد', \Zig3d_Widgets\Design_Icons::get('../../wp-config'), '');
Tests::same('آیکونِ ناموجود هم رشتهٔ خالی می‌دهد', \Zig3d_Widgets\Design_Icons::get('nope'), '');

/* اندازه‌ها و رنگ‌ها باید از تبِ استایل بیایند — از جمله دو موردِ تازه */
$controls_icons = zig_collect_controls(Search::class);

foreach (['design_icons', 'product_dot_size', 'product_dot_color', 'product_chevron_size'] as $control) {
    Tests::ok('کنترل موجود است: ' . $control, in_array($control, $controls_icons, true));
}

/*
 * فلش تنها آیکونِ غیرمربعِ طرح است. اگر کنترلِ اندازه هم عرض و هم ارتفاع
 * را بنویسد، با هر تغییرِ اندازه کشیده می‌شود.
 */
$chevron_rules = array_filter(
    zig_collect_selectors(Search::class),
    static fn(array $entry): bool => 'product_chevron_size' === $entry[0]
);

Tests::ok('کنترلِ اندازهٔ فلش سلکتور دارد', [] !== $chevron_rules);

foreach ($chevron_rules as $entry) {
    Tests::ok(
        'اندازهٔ فلش عرض را سفت نمی‌کند',
        false === strpos($entry[2], 'width:')
    );
}
