<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/elementor-stub.php';
require_once __DIR__ . '/lib/woocommerce-stub.php';

$root = dirname(__DIR__);

/*
 * jet_engine()/post_type_exists()/get_post_type()/get_post_meta()/… از
 * ‎lib/woocommerce-stub.php‎ می‌آیند (تعریفِ مشترک — همان دلیلِ
 * download-archive-test.php). ‎sanitize_html_class()‎ هم از
 * ‎tests/bootstrap.php‎ می‌آید (همیشه بارگذاری می‌شود).
 */
zig_reset_jetengine();
$POST_ID = 91;

final class Zig_Compat_CPT_Manager {
    public function get_items(): array { return [['id' => 8, 'slug' => 'current-download-key']]; }
}
final class Zig_Compat_Meta_Boxes {
    public function get_fields_for_context($context, $post_type): array {
        $models = [];
        for ($i = 1; $i <= 24; ++$i) { $models[] = ['key' => 'model-' . $i, 'value' => 'MODEL ' . $i]; }
        return [
            ['name' => 'supported_os', 'title' => 'OS', 'type' => 'checkbox', 'options' => [
                ['key' => 'windows-10', 'value' => 'Windows 10'],
                ['key' => 'windows-11', 'value' => 'Windows 11'],
            ]],
            ['name' => 'compatible_models', 'title' => 'Models', 'type' => 'checkbox', 'options' => $models],
        ];
    }
}

require_once $root . '/includes/download-archive-data.php';
require_once $root . '/includes/widgets/compatible-operating-systems.php';
require_once $root . '/includes/widgets/compatible-devices.php';

$reflection = new ReflectionClass(\Zig3d_Widgets\Download_Archive_Data::class);
foreach (['field_schema', 'taxonomies'] as $name) {
    $property = $reflection->getProperty($name);
    $property->setAccessible(true);
    $property->setValue(null, null);
}
$post_type = $reflection->getProperty('post_type');
$post_type->setAccessible(true);
$post_type->setValue(null, '');
$GLOBALS['__zig_jet_engine'] = (object) ['cpt' => new Zig_Compat_CPT_Manager(), 'meta_boxes' => new Zig_Compat_Meta_Boxes()];
$GLOBALS['__zig_registered_post_types'] = ['current-download-key'];
$GLOBALS['__zig_queried'] = $POST_ID;
$GLOBALS['__zig_post_types'][$POST_ID] = 'current-download-key';

Tests::group('Download compatibility widgets');

$os = new \Zig3d_Widgets\Widgets\Compatible_Operating_Systems();
$devices = new \Zig3d_Widgets\Widgets\Compatible_Devices();

$GLOBALS['__zig_post_meta'][$POST_ID] = [];
Tests::same('No OS renders no widget shell', '', $os->zig_render(['field' => 'supported_os', 'show_heading' => 'yes', 'heading' => 'OS', 'show_icon' => 'yes']));
Tests::same('No devices renders no widget shell', '', $devices->zig_render(['field' => 'compatible_models']));

$GLOBALS['__zig_post_meta'][$POST_ID]['supported_os'] = ['windows-11' => 'true', 'windows-10' => 'true', 'legacy' => 'false'];
$os_output = $os->zig_render(['field' => 'supported_os', 'show_heading' => 'yes', 'heading' => 'سیستم‌عامل‌های سازگار', 'show_icon' => 'yes']);
Tests::ok('OS uses human labels and omits raw keys', false !== strpos($os_output, 'Windows 10') && false !== strpos($os_output, 'Windows 11') && false === strpos($os_output, 'windows-11'));
Tests::ok('False OS values are omitted', false === strpos($os_output, 'legacy'));
Tests::ok('OS values have Bidi isolation', 2 === substr_count($os_output, '<bdi dir="ltr">'));
Tests::ok('Windows values receive the local SVG icon', 2 === substr_count($os_output, 'zig-compatible-os__icon'));

$selected = [];
for ($i = 1; $i <= 24; ++$i) { $selected['model-' . $i] = 'true'; }
$GLOBALS['__zig_post_meta'][$POST_ID]['compatible_models'] = $selected;
$device_output = $devices->zig_render(['field' => 'compatible_models', 'show_heading' => 'yes', 'heading' => 'دستگاه‌های سازگار', 'preview_desktop' => 5, 'preview_mobile' => 4, 'show_more_link' => 'yes', 'more_text' => 'مشاهده بیشتر', 'more_url' => ['url' => '#compatible-devices-details', 'nofollow' => true]]);
Tests::ok('Devices preserve schema order and human labels', strpos($device_output, 'MODEL 1') < strpos($device_output, 'MODEL 2') && false === strpos($device_output, 'model-24'));
Tests::same('All selected devices render as semantic items', 24, substr_count($device_output, '<span class="zig-compatible-devices__item'));
Tests::same('Desktop overflow starts after the configured preview', 19, substr_count($device_output, 'is-desktop-overflow'));
Tests::same('Mobile overflow starts after the configured preview', 20, substr_count($device_output, 'is-mobile-overflow'));
Tests::ok('Show More is a native anchor destination', false !== strpos($device_output, '<a ') && false !== strpos($device_output, 'href="#compatible-devices-details"') && false !== strpos($device_output, 'rel="nofollow"'));
Tests::ok('Show More has no disclosure contract', false === strpos($device_output, '<button') && false === strpos($device_output, 'aria-expanded') && false === strpos($device_output, 'aria-controls'));

$GLOBALS['__zig_post_meta'][$POST_ID]['compatible_models'] = array_slice($selected, 0, 5, true);
$exact_output = $devices->zig_render(['field' => 'compatible_models', 'preview_desktop' => 5, 'preview_mobile' => 5, 'show_more_link' => 'yes', 'more_url' => ['url' => '#details']]);
Tests::ok('Navigation link is independent from the preview count', false !== strpos($exact_output, 'zig-compatible-devices__more-link'));
$disabled_output = $devices->zig_render(['field' => 'compatible_models', 'show_more_link' => '', 'more_url' => ['url' => '#details']]);
Tests::ok('Disabled navigation link renders no anchor', false === strpos($disabled_output, 'zig-compatible-devices__more-link'));

foreach ([
    \Zig3d_Widgets\Widgets\Compatible_Operating_Systems::class => ['show_heading', 'heading', 'field', 'show_icon', 'group:heading_typography', 'group:item_typography', 'items_gap'],
    \Zig3d_Widgets\Widgets\Compatible_Devices::class => ['show_heading', 'heading', 'field', 'preview_desktop', 'preview_mobile', 'show_more_link', 'more_text', 'more_url', 'group:model_typography', 'group:toggle_typography', 'toggle_color', 'toggle_background', 'toggle_border_color', 'toggle_icon_color', 'toggle_hover_color', 'toggle_hover_background', 'toggle_hover_border_color', 'toggle_hover_icon_color', 'toggle_radius', 'toggle_padding', 'link_icon_gap'],
] as $class => $expected) {
    $controls = zig_collect_controls($class);
    foreach ($expected as $control) { Tests::ok($class . ' control: ' . $control, in_array($control, $controls, true)); }
    foreach (zig_collect_selectors($class) as [$control, $selector]) { Tests::ok($class . ' selector scoped: ' . $control, false !== strpos($selector, '{{WRAPPER}}')); }
}

$plugin = file_get_contents($root . '/includes/plugin.php');
$css = file_get_contents($root . '/assets/css/zig3d-widgets.css');
Tests::ok('Both widgets are registered', false !== strpos($plugin, "'compatible-operating-systems' => Widgets\\Compatible_Operating_Systems::class") && false !== strpos($plugin, "'compatible-devices' => Widgets\\Compatible_Devices::class"));
Tests::ok('Devices widget has no disclosure script dependency', !method_exists($devices, 'get_script_depends') && false === strpos($plugin, 'zig3d-download-compatibility'));
Tests::ok('Navigation link owns its outlined normal and visited surfaces', false !== strpos($css, '.zig-compatible-devices a.zig-compatible-devices__more-link:visited') && false !== strpos($css, 'border: 1px solid #ddc9ff;') && false !== strpos($css, 'background: #fff;'));
Tests::ok('Standalone widget CSS has no disclosure state selectors', false === strpos($css, '.zig-compatible-devices.is-expanded') && false === strpos($css, '.zig-compatible-devices [aria-expanded]'));
Tests::ok('Desktop compatibility roots are horizontal full-width flex rows', false !== strpos($css, ".zig-compatible-os,\n.zig-compatible-devices {") && false !== strpos($css, 'inline-size: 100%;') && false !== strpos($css, 'align-items: center;') && false === strpos($css, '.zig-compatible-devices {\n\tdisplay: flex;\n\tflex-direction: column;'));
Tests::ok('Desktop item lists own the flexible middle region', false !== strpos($css, 'flex: 1 1 auto;') && false !== strpos($css, 'min-inline-size: 0;') && false !== strpos($css, 'flex-wrap: wrap;'));
Tests::ok('Mobile stacking is isolated to the established breakpoint', false !== strpos($css, '@media (max-width: 767px)') && false !== strpos($css, 'flex-direction: column;') && false !== strpos($css, 'align-items: stretch;'));
