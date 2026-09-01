<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/elementor-stub.php';
require_once __DIR__ . '/lib/woocommerce-stub.php';

$root = dirname(__DIR__);
require_once $root . '/includes/markup.php';
require_once $root . '/includes/price.php';
require_once $root . '/includes/download-archive-data.php';
require_once $root . '/includes/widgets/download-archive.php';

/*
 * jet_engine()/post_type_exists()/sanitize_key()/get_object_taxonomies() از
 * ‎lib/woocommerce-stub.php‎ می‌آیند — یک تعریفِ مشترک برایِ همهٔ فایل‌هایِ
 * تستِ وابسته به JetEngine، تا در اجرایِ کاملِ سوییت (که همه در یک
 * پردازشِ PHP بارگذاری می‌شوند) تعریفِ فایلی دیگر جای این‌ها را نگیرد.
 */
zig_reset_jetengine();

/*
 * ‎new WP_Query()‎ در این سوییت به هیچ چیزِ واقعی وصل نیست؛
 * ‎lib/wp-query-stub.php‎ (استابِ مشترک، نه یک نسخهٔ محلی — همان‌جا
 * توضیح داده چرا) کافی است چون آنچه پایینِ این فایل سنجیده می‌شود شکلِ
 * ‎$args‎ است، نه اجرایِ کوئری. ‎render_pagination()‎ی همین فایل هم
 * زودتر از بلوکِ دسته‌بندی به ‎base_url()‎ → ‎get_queried_object()‎
 * می‌رسد، پس این استاب‌ها باید همین بالا باشند، نه کنارِ سنجه‌هایی که
 * واقعاً به آن‌ها نیاز دارند.
 */
require_once __DIR__ . '/lib/wp-query-stub.php';

// همان شکلِ menu-test.php — اگر آن فایل زودتر بار شود همان تعریف برنده
// می‌شود، ولی خروجی برایِ هر دو یکی است.
if (!function_exists('get_term_link')) {
    function get_term_link($term, $taxonomy = '') {
        // ورودیِ واقعیِ get_term_link() یا شناسه است یا خودِ WP_Term —
        // base_url() اینجا دومی را می‌فرستد.
        $id = is_object($term) ? (int) ($term->term_id ?? 0) : (int) $term;

        return 'https://zig3d.test/cat/' . $id;
    }
}

// همان قراردادِ faq-test.php: پیش‌فرض null، مگر سناریو صریح چیزی بگذارد.
if (!function_exists('get_queried_object')) {
    function get_queried_object() {
        return $GLOBALS['__zig_queried_object'] ?? null;
    }
}

final class Zig_Download_Test_Meta_Boxes {
    public function get_fields_for_context($context, $post_type): array {
        $GLOBALS['__zig_field_post_type'] = $post_type;
        return [
            ['name' => 'software_version', 'title' => 'نسخه نرم‌افزار', 'type' => 'text'],
            ['name' => 'release_date', 'title' => 'تاریخ بروزرسانی', 'type' => 'date'],
            ['name' => 'supported_os', 'title' => 'سیستم‌عامل', 'type' => 'checkbox', 'options' => [
                ['key' => 'windows-11', 'value' => 'Windows 11'],
            ]],
            ['name' => 'compatible_models', 'title' => 'مدل سازگار', 'type' => 'select', 'options' => [
                ['key' => 'up3d', 'value' => 'UP3D'],
                ['key' => 'up400', 'value' => 'UP400'],
            ]],
            ['name' => 'download_url', 'title' => 'آدرس دانلود', 'type' => 'url'],
            ['name' => 'installation_guide_url', 'title' => 'راهنمای نصب', 'type' => 'media'],
            ['name' => 'software_brand', 'title' => 'برند', 'type' => 'select'],
            ['name' => 'file_type', 'title' => 'نوع فایل', 'type' => 'text'],
            ['name' => 'system_architecture', 'title' => 'معماری', 'type' => 'radio'],
            ['name' => 'long_description', 'title' => 'توضیحات', 'type' => 'textarea'],
            ['name' => 'screenshots', 'title' => 'تصاویر', 'type' => 'gallery'],
            ['name' => 'rows', 'title' => 'ردیف‌ها', 'type' => 'repeater'],
        ];
    }
}
final class Zig_Download_Test_CPT_Manager {
    public function get_items(): array {
        return [
            ['id' => 3, 'slug' => 'irrelevant-key'],
            ['id' => 8, 'slug' => 'current-download-key'],
        ];
    }
}

Tests::group('Download Archive');

$reflection = new ReflectionClass(\Zig3d_Widgets\Download_Archive_Data::class);
foreach (['field_schema', 'taxonomies'] as $property_name) {
    $property = $reflection->getProperty($property_name);
    $property->setAccessible(true);
    $property->setValue(null, null);
}
$post_type_property = $reflection->getProperty('post_type');
$post_type_property->setAccessible(true);
$post_type_property->setValue(null, '');
$GLOBALS['__zig_registered_post_types'] = ['current-download-key'];
$GLOBALS['__zig_jet_engine'] = (object) ['meta_boxes' => new Zig_Download_Test_Meta_Boxes(), 'cpt' => new Zig_Download_Test_CPT_Manager()];
$GLOBALS['__zig_taxonomies'] = [
    (object) ['name' => 'software-category', 'labels' => (object) ['singular_name' => 'دسته نرم‌افزار']],
    (object) ['name' => 'software-label', 'labels' => (object) ['singular_name' => 'برچسب نرم‌افزار']],
];

$fields = \Zig3d_Widgets\Download_Archive_Data::field_options();
Tests::same('Canonical JetEngine CPT ID is fixed', 8, \Zig3d_Widgets\Download_Archive_Data::JETENGINE_CPT_ID);
Tests::same('CPT ID resolves to its registered WordPress key', 'current-download-key', \Zig3d_Widgets\Download_Archive_Data::post_type());
Tests::ok('JetEngine fields are discovered by stable meta key', isset($fields['software_version'], $fields['supported_os']));
Tests::ok('JetEngine field labels include key and type', false !== strpos($fields['supported_os'], 'supported_os') && false !== strpos($fields['supported_os'], '[checkbox]'));
Tests::ok('Repeater fields are excluded from archive mappings', !isset($fields['rows']));
Tests::ok('Gallery fields are excluded from archive mappings', !isset($fields['screenshots']));
Tests::ok('Date mapping only exposes date-compatible fields', isset(\Zig3d_Widgets\Download_Archive_Data::field_options('date')['release_date']) && !isset(\Zig3d_Widgets\Download_Archive_Data::field_options('date')['software_version']));
Tests::ok('Multi-value mapping exposes checkbox/select fields', isset(\Zig3d_Widgets\Download_Archive_Data::field_options('multi')['supported_os'], \Zig3d_Widgets\Download_Archive_Data::field_options('multi')['compatible_models']));
Tests::ok('URL mapping exposes URL/media fields', isset(\Zig3d_Widgets\Download_Archive_Data::field_options('url')['download_url'], \Zig3d_Widgets\Download_Archive_Data::field_options('url')['installation_guide_url']));
Tests::same('Expected field defaults use stable meta keys', 'download_url', \Zig3d_Widgets\Download_Archive_Data::default_field('download_url'));

$fields_property = $reflection->getProperty('field_schema');
$fields_property->setAccessible(true);
$fields_property->setValue(null, null);
$post_type_property->setValue(null, '');
$GLOBALS['__zig_jet_engine'] = null;
Tests::same('Early JetEngine unavailability does not resolve a false key', '', \Zig3d_Widgets\Download_Archive_Data::post_type());
Tests::same('Missing JetEngine degrades to the empty mapping option', ['' => '— انتخاب نشده —'], \Zig3d_Widgets\Download_Archive_Data::field_options());
$GLOBALS['__zig_jet_engine'] = (object) ['meta_boxes' => new Zig_Download_Test_Meta_Boxes(), 'cpt' => new Zig_Download_Test_CPT_Manager()];
Tests::ok('An early unavailable result is retryable in the same request', isset(\Zig3d_Widgets\Download_Archive_Data::field_options()['software_version']));
Tests::same('Field discovery uses the resolved post-type key', 'current-download-key', $GLOBALS['__zig_field_post_type']);

$taxonomies = \Zig3d_Widgets\Download_Archive_Data::taxonomy_options();
Tests::ok('Downloads taxonomies are discovered by slug', isset($taxonomies['software-category'], $taxonomies['software-label']));
Tests::same('Taxonomy discovery uses the resolved post-type key', 'current-download-key', end($GLOBALS['__zig_taxonomy_calls']));
Tests::ok('Expected taxonomy defaults remain slug based', 'software-category' === \Zig3d_Widgets\Download_Archive_Data::default_taxonomy('software-category'));

$values = \Zig3d_Widgets\Download_Archive_Data::values(['up400' => 'true', 'up560' => '1', 'up300' => 'false']);
Tests::same('Checkbox maps omit false values', ['up400', 'up560'], $values);
Tests::same('JSON checkbox maps are normalized', ['Windows 11'], \Zig3d_Widgets\Download_Archive_Data::values('{"Windows 11":"true","macOS":"false"}'));
Tests::same('Windows machine key maps to its human label', 'Windows 11', \Zig3d_Widgets\Download_Archive_Data::option_label('supported_os', 'windows-11'));
Tests::same('UP3D machine key maps to its human label', 'UP3D', \Zig3d_Widgets\Download_Archive_Data::option_label('compatible_models', 'up3d'));
Tests::same('UP400 machine key maps to its human label', 'UP400', \Zig3d_Widgets\Download_Archive_Data::option_label('compatible_models', 'up400'));
Tests::same('Display mapping preserves machine keys for query state', ['up400' => 'UP400'], \Zig3d_Widgets\Download_Archive_Data::display_values('compatible_models', ['up400' => 'true', 'up3d' => 'false']));
Tests::same('Selected devices follow JetEngine option order', ['up3d' => 'UP3D', 'up400' => 'UP400'], \Zig3d_Widgets\Download_Archive_Data::display_values('compatible_models', ['up400' => 'true', 'up3d' => 'true']));

$controls = zig_collect_controls(\Zig3d_Widgets\Widgets\Download_Archive::class);
Tests::ok('No Post Type Elementor control is exposed', !in_array('post_type', $controls, true));
foreach (['title_field', 'description_field', 'primary_taxonomy', 'label_taxonomy', 'download_url', 'file_type', 'supported_os', 'compatible_models', 'models_preview_desktop', 'models_preview_mobile', 'filter_category_source', 'filter_os_source', 'search_placeholder'] as $control) {
    Tests::ok('Content mapping control exists: ' . $control, in_array($control, $controls, true));
}
foreach (['sec_card_style', 'sec_header_style', 'sec_compatibility_style', 'sec_labels_style', 'sec_metadata_style', 'sec_actions_style'] as $section) {
    Tests::ok('Component Style section exists: ' . $section, in_array('SECTION:' . $section, $controls, true));
}
foreach (['card_background', 'card_border_color', 'card_radius', 'card_padding', 'group:card_title_typography', 'card_title_color', 'group:card_description_typography', 'card_description_color', 'group:model_typography', 'model_colors', 'model_background', 'model_border_color', 'model_radius', 'group:os_typography', 'os_colors', 'meta_label_color', 'meta_value_color', 'group:primary_cta_typography', 'primary_cta_background', 'primary_cta_color', 'primary_cta_hover_background', 'primary_cta_radius', 'group:secondary_cta_typography', 'secondary_cta_color', 'secondary_cta_hover', 'secondary_cta_radius'] as $control) {
    Tests::ok('Existing Style control ID remains compatible: ' . $control, in_array($control, $controls, true));
}
foreach (['TABS:primary_cta_state_tabs', 'TAB:primary_cta_normal_tab', 'TAB:primary_cta_hover_tab', 'TABS:secondary_cta_state_tabs', 'TAB:secondary_cta_normal_tab', 'TAB:secondary_cta_hover_tab'] as $state) {
    Tests::ok('Action state group exists: ' . $state, in_array($state, $controls, true));
}

$widget_source = file_get_contents($root . '/includes/widgets/download-archive.php');
Tests::ok('Style registration is split by UI component', false !== strpos($widget_source, 'register_card_style_controls()') && false !== strpos($widget_source, 'register_compatibility_style_controls()') && false !== strpos($widget_source, 'register_action_style_controls()'));
Tests::ok('Responsive controls stay with their components', false !== strpos($widget_source, "add_responsive_control('card_padding'") && false !== strpos($widget_source, "add_responsive_control('model_gap'") && false !== strpos($widget_source, "add_responsive_control('actions_gap'"));
Tests::ok('Badge styling is conditional on a configured label taxonomy', false !== strpos($widget_source, "'condition' => ['label_taxonomy!' => '']"));
Tests::ok('OS Style controls remain scoped to Footer metadata', false !== strpos($widget_source, "'selector' => '{{WRAPPER}} .zig-download-card__meta-os-value'") && false === strpos($widget_source, 'zig-download-card__os-group'));
$data_source = file_get_contents($root . '/includes/download-archive-data.php');
$plugin_source = file_get_contents($root . '/includes/plugin.php');
$css_source = file_get_contents($root . '/assets/css/zig3d-widgets.css');
$js_source = file_get_contents($root . '/assets/js/zig3d-archive.js');
Tests::ok('Widget registration is present', false !== strpos($plugin_source, "'download-archive' => Widgets\\Download_Archive::class"));
Tests::ok('WP_Query uses the resolved string key', false !== strpos($widget_source, "'post_type' => \$post_type") && false === strpos($widget_source, "'post_type' => Download_Archive_Data::JETENGINE_CPT_ID"));
Tests::ok('No legacy post-type slug is an identity contract', false === strpos($widget_source . $data_source, "'downloads'") && false === strpos($widget_source . $data_source, 'software-downloads'));
Tests::ok('Frontend cards read only cached file size', false !== strpos($widget_source, 'Download_Archive_Data::SIZE_META') && false === strpos($widget_source, 'wp_safe_remote_'));
Tests::ok('Remote size discovery uses a dynamically resolved save hook', false !== strpos($data_source, "'save_post_' . \$post_type") && false !== strpos($data_source, 'wp_safe_remote_head'));
Tests::ok('Human-readable size is synced next to the raw byte meta, not instead of it', false !== strpos($data_source, 'update_post_meta($post_id, self::SIZE_META, $bytes)') && false !== strpos($data_source, 'update_post_meta($post_id, self::SIZE_HUMAN_META, self::persian_size($bytes))'));
Tests::ok('persian_size(): zero/negative bytes yield no value (never a fabricated size)', '' === \Zig3d_Widgets\Download_Archive_Data::persian_size(0) && '' === \Zig3d_Widgets\Download_Archive_Data::persian_size(-5));
Tests::ok('persian_size(): sub-kilobyte stays in whole بایت', 'بایت' === explode(' ', \Zig3d_Widgets\Download_Archive_Data::persian_size(512))[1]);
Tests::ok('persian_size(): ~850MB renders as expected', '850 مگابایت' === \Zig3d_Widgets\Download_Archive_Data::persian_size(891289600));
Tests::ok('persian_size(): exact power-of-1024 has no decimal', '2 گیگابایت' === \Zig3d_Widgets\Download_Archive_Data::persian_size(2 * 1024 * 1024 * 1024));
Tests::ok('persian_size(): non-exact values keep one decimal', '1.5 گیگابایت' === \Zig3d_Widgets\Download_Archive_Data::persian_size((int) (1.5 * 1024 * 1024 * 1024)));
/*
 * refresh_file_size() فقط وقتی SIZE_HUMAN_META را می‌نویسد که یک دورِ
 * کاملِ محاسبهٔ حجم اجرا شود. پست‌هایی که *قبل* از افزوده‌شدنِ
 * SIZE_HUMAN_META ذخیره شده بودند (لینک عوض نشده، بایت از قبل کش شده)
 * دیگر آن دور را دوباره اجرا نمی‌کردند — بدونِ رفعِ زیر، برایِ همیشه
 * SIZE_HUMAN_META شان خالی می‌ماند (باگِ واقعیِ گزارش‌شده).
 */
Tests::ok('Cached-size early return still backfills a missing human meta', false !== strpos($data_source, "'' === (string) get_post_meta(\$post_id, self::SIZE_HUMAN_META, true)") && false !== strpos($data_source, 'update_post_meta($post_id, self::SIZE_HUMAN_META, self::persian_size($cached_bytes))'));
Tests::ok('A one-time admin backfill exists for posts saved before this meta existed', false !== strpos($data_source, "add_action('admin_init', [self::class, 'backfill_human_size'])") && false !== strpos($data_source, 'BACKFILL_OPTION'));
Tests::ok('Backfill query targets posts with a cached size but no human string yet', false !== strpos($data_source, "'key'     => self::SIZE_META") && false !== strpos($data_source, "'compare' => '>'") && false !== strpos($data_source, "'compare' => 'NOT EXISTS'"));
Tests::ok('Archive supports real search, sorts and result count', false !== strpos($widget_source, "'s' => \$state['search']") && false !== strpos($widget_source, 'data-zig-part="count"') && false !== strpos($widget_source, 'data-zig-sort'));
Tests::ok('No applied-filters duplicate UI exists', false === strpos($widget_source, 'applied-filters'));
Tests::ok('RTL/LTR values use bidi isolation', false !== strpos($widget_source, '<bdi>') && false !== strpos($widget_source, 'dir="ltr"'));
Tests::ok('Toolbar belongs to the main archive column', false !== strpos($widget_source, '<main class="zig-archive__main zig-download-archive__main">') && strpos($widget_source, '<main class="zig-archive__main zig-download-archive__main">') < strpos($widget_source, '<div class="zig-download-archive__toolbar">'));

/*
 * Mobile chrome, ported from Product_Archive's pill bar + bottom sheet:
 * a filter button (when a sidebar exists) plus a search form that takes
 * up most of the bar's width — this widget has no sort, so there is no
 * sort side to the pill. The old inline show/hide toggle
 * (.zig-download-archive__filter-trigger / .is-filters-open) is retired
 * in favor of the shared .zig-archive__mbar / .zig-archive__filters sheet
 * machinery, which needs no widget-specific CSS or JS of its own.
 */
Tests::ok('Mobile bar is rendered with the shared chrome hook', false !== strpos($widget_source, 'class="zig-archive__mbar zig-archive__mbar--search" data-zig-mbar'));
Tests::ok('Mobile filter button opens the shared filter sheet', false !== strpos($widget_source, 'data-zig-open="filters"'));
Tests::ok('Mobile search form carries the shared data-zig-search hook', false !== strpos($widget_source, 'class="zig-archive__mbar-search"') && false !== strpos($widget_source, 'data-zig-search>'));
Tests::ok('Mobile search has no decorative icon (not needed per user feedback)', false === strpos($widget_source, 'mbar-search-icon') && false === strpos($widget_source, "svg_icon('search'"));

/*
 * Elementor's kit CSS carries a global rule for text-like fields —
 * .elementor-kit-8 input:not([type="button"]):not([type="submit"]), ...
 * .elementor-field-textual { box-shadow: ...; } — whose two :not()
 * clauses out-specify a lone-class selector. The mobile search input
 * must use the same three-class compound-root chain the desktop search
 * input already relies on, or the kit's shadow bleeds through.
 */
Tests::ok('Mobile search input beats the Elementor kit field defaults on specificity', false !== strpos($css_source, '.zig-archive.zig-download-archive .zig-archive__mbar-search input[type="search"] {'));
Tests::ok('Mobile search input resets box-shadow at the same specificity it is set', false !== strpos($css_source, '.zig-archive.zig-download-archive .zig-archive__mbar-search input[type="search"] {
		flex: 1 1 auto;
		min-width: 0;
		margin: 0;
		padding: 0;
		border: 0;
		background: none;
		box-shadow: none;'));
Tests::ok('Sheet handle and back button are siblings of the facets slot, not inside it', strpos($widget_source, 'data-zig-part="facets"') < strpos($widget_source, 'zig-archive__sheet-back'));
Tests::ok('Legacy inline filter toggle is fully retired', false === strpos($widget_source, 'filter-trigger') && false === strpos($widget_source, 'is-filters-open'));
Tests::ok('Legacy inline filter toggle CSS is fully retired', false === strpos($css_source, '.zig-download-archive__filter-trigger') && false === strpos($css_source, '.is-filters-open'));
Tests::ok('JS binds every [data-zig-search] element, not just the first', false !== strpos($js_source, 'querySelectorAll(\'[data-zig-search]\')') && false === strpos($js_source, "root.querySelector('[data-zig-search]')"));
Tests::ok('Main and sidebar structural classes remain present', false !== strpos($widget_source, 'zig-download-archive__main') && false !== strpos($widget_source, 'zig-download-archive__filters'));
Tests::ok('Facet counts are generated from one batched post-id pass', false !== strpos($widget_source, "\$out[\$value]['count']++") && false === strpos($widget_source, 'foreach ($options as $value => $option) { new \\WP_Query'));
Tests::ok('Primary and label taxonomies cannot duplicate', false !== strpos($widget_source, "\$label_taxonomy !== \$primary_taxonomy"));
Tests::ok('Missing label taxonomy renders no badge collection', false !== strpos($widget_source, "'' !== \$label_taxonomy") && false !== strpos($widget_source, ': []'));
Tests::ok('Download CTA keeps its primary class', false !== strpos($widget_source, 'zig-download-card__download'));
Tests::ok('Accordion summaries expose expanded state and body ownership', false !== strpos($widget_source, 'aria-expanded="%s" aria-controls="%s"') && false !== strpos($widget_source, 'class="zig-facet__list" id="%s"'));
Tests::ok('Chevron has a dedicated scoped fixed-size stroke treatment', false !== strpos($css_source, '.zig-download-archive .zig-facet__chevron') && false !== strpos($css_source, 'inline-size: 14px') && false !== strpos($css_source, 'fill: none'));
Tests::ok('Closed Download facets remove their bodies from layout', false !== strpos($css_source, '.zig-download-archive .zig-facet:not([open]) > .zig-facet__list { display: none; }'));
Tests::ok('Native facet toggles synchronize explicit expanded state', false !== strpos($js_source, "addEventListener('toggle'") && false !== strpos($js_source, "title.setAttribute('aria-expanded'"));
Tests::ok('Cards contain no placeholder initial or monogram', false === strpos($widget_source, 'zig-download-card__initial') && false === strpos($widget_source, 'zig-download-card__monogram'));
Tests::ok('Compatibility uses individual semantic model tokens', false !== strpos($widget_source, 'zig-download-card__model') && false !== strpos($widget_source, '<bdi dir="%s">%s</bdi>'));
Tests::ok('Compatibility expansion is an accessible local button', false !== strpos($widget_source, 'zig-download-card__models-toggle') && false !== strpos($widget_source, 'aria-expanded="false" aria-controls="%s"'));
Tests::ok('Compatibility uses one heading with its dynamic total', false !== strpos($widget_source, 'zig-download-card__compatibility-separator') && false !== strpos($widget_source, 'zig-download-card__models-count') && false === strpos($widget_source, 'zig-download-card__compatibility-title'));
Tests::ok('Compatibility heading cannot distribute its count to another edge', false !== strpos($css_source, '.zig-download-card__compatibility-head') && false === strpos($css_source, '.zig-download-card__compatibility-head { display: flex'));
/*
 * The point of this check is cache busting: the version that ships these
 * assets must not be older than the one they were written for. It used to
 * pin the literal '1.24.0', which meant every later release broke a test
 * about a widget it had not touched. A floor keeps the guarantee and drops
 * the false alarm.
 */
preg_match(
    "/define\('ZIG3D_WIDGETS_VERSION', '([^']+)'\)/",
    (string) file_get_contents($root . '/zig3d-elementor-widgets.php'),
    $version_match
);
Tests::ok(
    'Frontend asset version was advanced for the current widget assets',
    isset($version_match[1]) && version_compare($version_match[1], '1.24.0', '>=')
);
Tests::ok('Header regions explicitly own the RTL start edge', false !== strpos($css_source, '.zig-download-card__category,') && false !== strpos($css_source, 'direction: rtl;') && false !== strpos($css_source, 'text-align: start;'));
Tests::ok('Compatibility chips use RTL start wrapping without distribution', false !== strpos($css_source, '.zig-download-card__models { display: flex; flex-wrap: wrap; justify-content: flex-start;') && false === strpos($css_source, '.zig-download-card__models { display: flex; flex-wrap: wrap; justify-content: space-between;'));
Tests::ok('Every expandable region includes widget and post identity', false !== strpos($widget_source, "sanitize_html_class(\$this->get_id() . '-' . \$id)"));
Tests::ok('Show-more uses a dedicated small SVG chevron', false !== strpos($widget_source, 'zig-download-card__toggle-chevron') && false !== strpos($css_source, '.zig-download-card__toggle-chevron'));
Tests::ok('Collapsed overflow models are removed from layout', false !== strpos($css_source, '.zig-download-card:not(.is-models-expanded) .zig-download-card__model.is-desktop-overflow { display: none; }'));
Tests::ok('Show-more button has a Download-scoped contrast reset', false !== strpos($css_source, '.zig-download-archive .zig-download-card__models-toggle') && false !== strpos($css_source, 'opacity: 1') && false !== strpos($css_source, 'text-shadow: none'));
Tests::ok('Standalone body OS component was removed', false === strpos($widget_source, 'zig-download-card__os-group') && false === strpos($widget_source, 'zig-download-card__os-token'));
Tests::ok('OS is generated through the Footer metadata path', false !== strpos($widget_source, "\$this->meta_values(\$systems") && false !== strpos($widget_source, 'zig-download-card__meta-os-value'));
Tests::ok('Metadata and CTAs share a semantic footer', false !== strpos($widget_source, '<footer class="zig-download-card__footer">'));
Tests::ok('Frontend metadata remains optional and cached-size only', false !== strpos($widget_source, "if ('' !== \$metadata)") && false === strpos($widget_source, 'wp_safe_remote_'));
Tests::ok('Delegated model toggle survives AJAX fragment replacement', false !== strpos($js_source, "closest('.zig-download-card__models-toggle')") && false !== strpos($js_source, "classList.toggle('is-models-expanded'"));

$widget = new \Zig3d_Widgets\Widgets\Download_Archive();
$compatibility = new ReflectionMethod($widget, 'render_compatibility');
$compatibility->setAccessible(true);
$render_compatibility = static function (array $models, int $limit) use ($widget, $compatibility): string {
    ob_start();
    $compatibility->invoke($widget, 42, $models, ['models_preview_desktop' => $limit, 'models_preview_mobile' => $limit]);
    return (string) ob_get_clean();
};
$three_models = ['one' => 'ONE', 'two' => 'TWO', 'three' => 'THREE'];
$exact_output = $render_compatibility($three_models, 3);
$overflow_output = $render_compatibility($three_models + ['four' => 'FOUR'], 3);
Tests::ok('Exact preview limit renders no expand button', false === strpos($exact_output, 'zig-download-card__models-toggle'));
Tests::ok('Preview limit plus one renders an expand button', false !== strpos($overflow_output, 'zig-download-card__models-toggle'));
Tests::ok('Remaining-device count is calculated dynamically', false !== strpos($overflow_output, '1'));
$meta_values = new ReflectionMethod($widget, 'meta_values');
$meta_values->setAccessible(true);
$render_os_meta = static function (array $values) use ($widget, $meta_values): string {
    ob_start();
    $meta_values->invoke($widget, $values, 'Operating System', 'zig-download-card__meta-os-value');
    return (string) ob_get_clean();
};
$one_os = $render_os_meta(['windows-11' => 'Windows 11']);
$multiple_os = $render_os_meta(['windows-10' => 'Windows 10', 'windows-11' => 'Windows 11']);
Tests::ok('One OS renders as a Footer metadata item', false !== strpos($one_os, '<dt>Operating System</dt>') && false !== strpos($one_os, 'Windows 11'));
Tests::ok('Multiple OS values render individually', 2 === substr_count($multiple_os, 'zig-download-card__meta-os-value') && false !== strpos($multiple_os, 'Windows 10') && false !== strpos($multiple_os, 'Windows 11'));
Tests::ok('No OS renders no metadata wrapper', '' === $render_os_meta([]));
Tests::ok('OS Footer values retain Bidi isolation', false !== strpos($one_os, '<bdi class="zig-download-card__meta-os-value" dir="ltr">'));
Tests::ok('Old OS body selectors leave no reserved space', false === strpos($css_source, '.zig-download-card__os-group') && false === strpos($css_source, '.zig-download-card__os-token'));

/*
 * Pagination and active-filters parity with Product Archive.
 *
 * render_pagination()/render_active() are exercised directly via
 * reflection with a hand-built $ctx array — the same technique the file
 * already uses above for render_compatibility()/meta_values() — because
 * building a real WP_Query for a full context() call is out of scope for
 * this stub-driven suite. What matters here is markup/class parity, not
 * the query itself (already covered by Product Archive's own tests).
 */
$render_pagination = new ReflectionMethod($widget, 'render_pagination');
$render_pagination->setAccessible(true);
$render_active = new ReflectionMethod($widget, 'render_active');
$render_active->setAccessible(true);

$base_state = ['page' => 1, 'search' => '', 'sort' => 'updated', 'filters' => ['category' => [], 'os' => []]];

$render_pag = static function (int $page, int $pages) use ($widget, $render_pagination, $base_state): string {
    $ctx = ['settings' => ['label_prev' => 'قبلی', 'label_next' => 'بعدی'], 'state' => array_merge($base_state, ['page' => $page]), 'page' => $page, 'pages' => $pages];
    ob_start();
    $render_pagination->invoke($widget, $ctx);
    return (string) ob_get_clean();
};

$single_page = $render_pag(1, 1);
Tests::same('Single page renders no pagination nav', '', $single_page);

$middle_page = $render_pag(2, 3);
Tests::ok('Pagination uses the same <nav> landmark as Product Archive', false !== strpos($middle_page, '<nav class="zig-archive__pagination"'));
Tests::ok('Prev link is present with rel=prev on a middle page', false !== strpos($middle_page, 'zig-page--prev') && false !== strpos($middle_page, 'rel="prev"'));
Tests::ok('Next link is present with rel=next on a middle page', false !== strpos($middle_page, 'zig-page--next') && false !== strpos($middle_page, 'rel="next"'));
Tests::ok('Current page is an aria-current span, not a link', false !== strpos($middle_page, '<span class="zig-page is-current" aria-current="page">'));
Tests::ok('Page numbers render with Persian digits', false !== strpos($middle_page, '۲') && false !== strpos($middle_page, '۳'));

$first_page = $render_pag(1, 3);
Tests::blocks('First page renders no prev link', $first_page, 'zig-page--prev');

$last_page = $render_pag(3, 3);
Tests::blocks('Last page renders no next link', $last_page, 'zig-page--next');

$render_active_html = static function (array $filters) use ($widget, $render_active, $base_state): string {
    $ctx = [
        'settings' => [],
        'state'    => array_merge($base_state, ['filters' => $filters]),
        'facets'   => [
            ['key' => 'category', 'kind' => 'taxonomy', 'label' => 'نوع نرم‌افزار'],
            ['key' => 'os', 'kind' => 'meta', 'label' => 'سیستم‌عامل'],
        ],
    ];
    ob_start();
    $render_active->invoke($widget, $ctx);
    return (string) ob_get_clean();
};

Tests::same('No active filters renders nothing', '', $render_active_html(['category' => [], 'os' => []]));

$with_filters = $render_active_html(['category' => ['3d-printer'], 'os' => ['windows-11', 'macos']]);
Tests::ok('Active filters use the same chip classes as Product Archive', false !== strpos($with_filters, 'zig-filters__active') && false !== strpos($with_filters, 'zig-filters__chips') && false !== strpos($with_filters, 'zig-filters__chip'));
Tests::same('Every selected value renders exactly one chip', 3, substr_count($with_filters, 'zig-filters__chip"'));
Tests::ok('A clear-all link is present', false !== strpos($with_filters, 'zig-filters__clear') && false !== strpos($with_filters, 'data-zig-clear="1"'));
Tests::ok('Each chip toggles its own facet/value off', false !== strpos($with_filters, 'data-zig-toggle="filter_category|3d-printer"') && false !== strpos($with_filters, 'data-zig-toggle="filter_os|windows-11"'));

$widget_source = file_get_contents($root . '/includes/widgets/download-archive.php');
Tests::ok('Pagination behavior controls exist: scroll_pages/restore_state/label_prev/label_next', in_array('scroll_pages', $controls, true) && in_array('restore_state', $controls, true) && in_array('label_prev', $controls, true) && in_array('label_next', $controls, true));
Tests::ok('Root attributes wire scroll/restore settings, not hardcoded zeros', false !== strpos($widget_source, "'data-zig-scroll-max' => (string) (int) (\$settings['scroll_pages']") && false !== strpos($widget_source, "'data-zig-restore' => 'yes' === (\$settings['restore_state']"));
Tests::ok('Pagination outer slot is a plain div, not a nested <nav>', false !== strpos($widget_source, '<div data-zig-part="pagination">') && false === strpos($widget_source, '<nav data-zig-part="pagination">'));

Tests::group('Download Archive › category-page scoping (/downloads/<term>)');

/*
 * تولیدِ واقعی با ‎'objects'‎ صدا می‌زند (‎Download_Archive_Data::taxonomy_options()‎
 * همین را می‌خواهد)؛ این استابِ مشترک اصلاً به آرگومانِ دوم توجه نمی‌کند —
 * پس برایِ این بلوک موقتاً به شکلِ فهرستِ نام‌ها برمی‌گردد (که خودِ کدِ
 * تولید هم با ‎'names'‎ صریح می‌خواهد)، و در پایان به همان شکلِ اصلی
 * برمی‌گردد تا بقیهٔ فایل (که به شکلِ آبجکتی نیاز دارند) دست‌نخورده بمانند.
 */
$original_taxonomies = $GLOBALS['__zig_taxonomies'];
$GLOBALS['__zig_taxonomies'] = ['software-category', 'software-label'];

$category_term = new \WP_Term('نرم‌افزار فرز', 'milling-machine-software', 91);
$category_term->taxonomy = 'software-category';

$unrelated_term = new \WP_Term('برچسبِ بی‌ربط', 'irrelevant-tag', 55);
$unrelated_term->taxonomy = 'post_tag';

$GLOBALS['__zig_queried_object'] = $category_term;
$ctx = (new \Zig3d_Widgets\Widgets\Download_Archive())->context([], []);
Tests::same(
    'روی آرشیوِ واقعیِ یک ترم، کوئری به همان دسته قید می‌خورد — نه کلِ کاتالوگ',
    [[
        'taxonomy'         => 'software-category',
        'field'            => 'term_id',
        'terms'            => [91],
        'include_children' => true,
    ]],
    $ctx['query']->args['tax_query'] ?? null
);
Tests::same('term_id به بدنهٔ context() هم برمی‌گردد (برایِ data-zig-term)', 91, $ctx['term_id']);

$GLOBALS['__zig_queried_object'] = $unrelated_term;
$ctx_unrelated = (new \Zig3d_Widgets\Widgets\Download_Archive())->context([], []);
Tests::ok(
    'ترمی از تاکسونومیِ بی‌ربط (نه از تاکسونومی‌هایِ خودِ این CPT) نادیده گرفته می‌شود',
    !isset($ctx_unrelated['query']->args['tax_query'])
);
Tests::same('term_id هم صفر می‌ماند', 0, $ctx_unrelated['term_id']);

$GLOBALS['__zig_queried_object'] = null;
$ctx_plain = (new \Zig3d_Widgets\Widgets\Download_Archive())->context([], []);
Tests::ok(
    'بدونِ هیچ ترمِ جاری‌ای (ویجت روی یک برگهٔ دلخواه)، هیچ قیدی اضافه نمی‌شود — رفتارِ قبلی برایِ همین حالت دست‌نخورده',
    !isset($ctx_plain['query']->args['tax_query'])
);

/*
 * مسیرِ آژاکس: کلاینت فقط یک عددِ term_id می‌فرستد (از data-zig-term)، نه
 * نامِ تاکسونومی — resolve_term() باید آن را رویِ تاکسونومی‌هایِ خودِ این
 * CPT بسنجد، نه هر ترمی که در تاکسونومیِ دیگری همین شناسه را دارد.
 */
$GLOBALS['__zig_terms'][91] = $category_term;
$GLOBALS['__zig_terms'][55] = $unrelated_term;
$GLOBALS['__zig_queried_object'] = null; // admin-ajax.php هیچ کوئریِ واقعی‌ای ندارد

Tests::same(
    'term_idِ فرستاده‌شده از آژاکس هم همان قید را می‌سازد',
    91,
    (new \Zig3d_Widgets\Widgets\Download_Archive())->context([], [], 91)['term_id']
);
Tests::same(
    'ولی term_idِ متعلق به تاکسونومیِ دیگر (۵۵) پذیرفته نمی‌شود',
    0,
    (new \Zig3d_Widgets\Widgets\Download_Archive())->context([], [], 55)['term_id']
);

/*
 * فیلترِ فعالِ سایدبار (filter_<taxonomy>) باید با قیدِ صفحه AND شود، نه
 * جایگزینش — وگرنه هر تیکِ سایدبار رویِ همین صفحه، دستهٔ خودِ صفحه را دور
 * می‌زد. کلیدِ واقعیِ این فیلتر نامِ تاکسونومی است
 * (‎facet_definitions()‎ → ‎sanitize_key($source)‎)، نه اسمِ اسلاتِ
 * ‎category‎.
 */
$GLOBALS['__zig_queried_object'] = $category_term;
$ctx_filtered = (new \Zig3d_Widgets\Widgets\Download_Archive())->context(
    ['filter_category_on' => 'yes', 'primary_taxonomy' => 'software-category'],
    ['filter_software-category' => 'cnc']
);
$merged = $ctx_filtered['query']->args['tax_query'] ?? null;
Tests::same('هر دو گروه با هم می‌آیند، نه یکی جایِ دیگری', 'AND', $merged['relation'] ?? '');
Tests::same('گروهِ اولِ آن همان قیدِ صفحه است (ترمِ ۹۱)', [91], $merged[0][0]['terms'] ?? null);
Tests::same('گروهِ دومِ آن همان فیلترِ انتخابیِ کاربر است (cnc)', ['cnc'], $merged[1][0]['terms'] ?? null);

Tests::group('Download Archive › base_url() روی آرشیوِ واقعیِ یک ترم');

$base_url_method = new ReflectionMethod(\Zig3d_Widgets\Widgets\Download_Archive::class, 'base_url');
$base_url_method->setAccessible(true);

$GLOBALS['__zig_queried_object'] = $category_term;
Tests::same(
    'روی آرشیوِ ترم، base_url() از get_term_link() می‌آید — نه سندِ قالبِ المنتور',
    'https://zig3d.test/cat/91',
    $base_url_method->invoke(new \Zig3d_Widgets\Widgets\Download_Archive())
);

$GLOBALS['__zig_queried_object'] = null;
$GLOBALS['__zig_taxonomies'] = $original_taxonomies;
