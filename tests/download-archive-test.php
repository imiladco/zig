<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/elementor-stub.php';
require_once __DIR__ . '/lib/woocommerce-stub.php';

$root = dirname(__DIR__);
require_once $root . '/includes/markup.php';
require_once $root . '/includes/download-archive-data.php';
require_once $root . '/includes/widgets/download-archive.php';

/*
 * jet_engine()/post_type_exists()/sanitize_key()/get_object_taxonomies() از
 * ‎lib/woocommerce-stub.php‎ می‌آیند — یک تعریفِ مشترک برایِ همهٔ فایل‌هایِ
 * تستِ وابسته به JetEngine، تا در اجرایِ کاملِ سوییت (که همه در یک
 * پردازشِ PHP بارگذاری می‌شوند) تعریفِ فایلی دیگر جای این‌ها را نگیرد.
 */
zig_reset_jetengine();

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
Tests::ok('Archive supports real search, sorts and result count', false !== strpos($widget_source, "'s' => \$state['search']") && false !== strpos($widget_source, 'data-zig-part="count"') && false !== strpos($widget_source, 'data-zig-sort'));
Tests::ok('No applied-filters duplicate UI exists', false === strpos($widget_source, 'applied-filters'));
Tests::ok('RTL/LTR values use bidi isolation', false !== strpos($widget_source, '<bdi>') && false !== strpos($widget_source, 'dir="ltr"'));
Tests::ok('Toolbar belongs to the main archive column', false !== strpos($widget_source, 'zig-download-archive__main"><div class="zig-download-archive__toolbar">'));
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
Tests::ok('Frontend asset version was advanced for the current widget assets', false !== strpos(file_get_contents($root . '/zig3d-elementor-widgets.php'), "define('ZIG3D_WIDGETS_VERSION', '1.22.0')"));
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
