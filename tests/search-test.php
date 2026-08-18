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
 * طبقِ طرحِ تأییدشده (مرجعِ «Overlay+Border»)، حالتِ بستهٔ پیش‌فرض هیچ
 * ضربدری ندارد — فقط جای‌گزین + آیکونِ سرچ. دکمه در DOM هست (تا
 * جاوااسکریپت مجبور نباشد بسازدش) ولی با ‎hidden‎ شروع می‌شود؛ فقط با
 * تایپ‌شدنِ متن نمایان می‌شود.
 */
Tests::ok(
    'دکمهٔ پاک‌کردن با ویژگیِ hidden رندر می‌شود — یعنی حالتِ پیش‌فرض بدونِ X است',
    false !== strpos($html, 'zig-search__clear" hidden')
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
    '<a class="zig-search__chip zig-search__chip--popular" href="https://zig3d.test/?s=' . rawurlencode('میلینگ ماشین') . '"'
);

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
    'placeholder_text', 'empty_message', 'more_button_text', 'recent_heading_text', 'clear_history_text', 'popular_heading_text',
    'search_icon', 'clear_icon', 'chevron_icon', 'empty_icon', 'recent_icon', 'popular_icon',
    'popular_searches', 'enable_recent', 'recent_max', 'recent_expiry_days', 'synonym_pairs',
    'TABS:field_box_tabs', 'field_icon_size', 'field_icon_color', 'group:field_typography',
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
