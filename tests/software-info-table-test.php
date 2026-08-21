<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/elementor-stub.php';
require_once __DIR__ . '/lib/woocommerce-stub.php';

$root = dirname(__DIR__);

/*
 * jet_engine()/post_type_exists()/get_post_type()/get_queried_object_id()/
 * get_post_meta()/get_object_taxonomies()/get_option() از
 * ‎lib/woocommerce-stub.php‎ می‌آیند — تعریفِ مشترک، همان دلیلِ
 * download-archive-test.php.
 */
zig_reset_jetengine();
$POST_ID = 601;
$GLOBALS['__zig_options']['date_format'] = 'Y-m-d';

final class Zig_Info_CPT { public function get_items(): array { return [['id' => 8, 'slug' => 'download-current']]; } }
final class Zig_Info_Fields { public function get_fields_for_context($context, $type): array { return [
    ['name' => 'software_version', 'title' => 'Version', 'type' => 'text'],
    ['name' => 'release_date', 'title' => 'Release date', 'type' => 'date'],
    ['name' => 'supported_os', 'title' => 'Operating systems', 'type' => 'checkbox', 'options' => [['key' => 'windows-10', 'label' => 'Windows 10'], ['key' => 'windows-11', 'label' => 'Windows 11']]],
    ['name' => 'compatible_models', 'title' => 'Models', 'type' => 'checkbox', 'options' => [['key' => 'up400', 'label' => 'UP400'], ['key' => 'up560', 'label' => 'UP560']]],
]; } }
$GLOBALS['__zig_jet_engine'] = (object) ['cpt' => new Zig_Info_CPT(), 'meta_boxes' => new Zig_Info_Fields()];
$GLOBALS['__zig_registered_post_types'] = ['download-current'];
$GLOBALS['__zig_queried'] = $POST_ID;
$GLOBALS['__zig_post_types'][$POST_ID] = 'download-current';

require_once $root . '/includes/download-archive-data.php';
require_once $root . '/includes/widgets/software-info-table.php';

$reflection = new ReflectionClass(\Zig3d_Widgets\Download_Archive_Data::class);
foreach (['field_schema', 'taxonomies'] as $name) { $property = $reflection->getProperty($name); $property->setAccessible(true); $property->setValue(null, null); }
$property = $reflection->getProperty('post_type'); $property->setAccessible(true); $property->setValue(null, '');

Tests::group('Software info table');
$widget = new \Zig3d_Widgets\Widgets\Software_Info_Table();
$GLOBALS['__zig_post_meta'][$POST_ID] = [];
Tests::same('Empty configured rows render no shell by default', '', $widget->zig_render(['rows' => [['enabled' => 'yes', 'label' => 'Version', 'source' => 'software_version', 'format' => 'plain', 'hide_empty' => 'yes']]]));

$GLOBALS['__zig_post_meta'][$POST_ID] = [
    'software_version' => '3.1.5',
    'supported_os' => ['windows-10' => true, 'windows-11' => true, 'legacy' => false],
    'compatible_models' => ['up560' => true, 'up400' => true],
    \Zig3d_Widgets\Download_Archive_Data::SIZE_META => 891289600,
];
$rows = [
    ['enabled' => 'yes', 'label' => 'نسخه', 'source' => 'software_version', 'format' => 'plain', 'separator' => ' / ', 'hide_empty' => 'yes'],
    ['enabled' => 'yes', 'label' => 'حجم فایل', 'source' => '@cached_file_size', 'format' => 'plain', 'separator' => ' / ', 'hide_empty' => 'yes'],
    ['enabled' => 'yes', 'label' => 'سیستم‌عامل‌های سازگار', 'source' => 'supported_os', 'format' => 'multi', 'separator' => ' / ', 'hide_empty' => 'yes'],
    ['enabled' => 'yes', 'label' => 'مدل‌ها', 'source' => 'compatible_models', 'format' => 'multi', 'separator' => ' / ', 'hide_empty' => 'yes'],
];
$html = $widget->zig_render(['rows' => $rows]);
Tests::ok('Semantic definition-list rows render', false !== strpos($html, '<dl') && 4 === substr_count($html, '<dt') && 4 === substr_count($html, '<dd'));
Tests::ok('Human OS labels render and raw keys do not', false !== strpos($html, 'Windows 10') && false !== strpos($html, 'Windows 11') && false === strpos($html, 'windows-10'));
Tests::ok('False checkbox entries are omitted', false === strpos($html, 'legacy'));
Tests::ok('JetEngine option order is stable', strpos($html, 'UP400') < strpos($html, 'UP560'));
Tests::ok('Cached size is used without remote requests', false !== strpos($html, '850 MB') && false === strpos(file_get_contents($root . '/includes/widgets/software-info-table.php'), 'wp_safe_remote_'));
Tests::ok('Technical values use isolated Bidi markup', false !== strpos($html, '<bdi dir="auto">3.1.5</bdi>'));

$controls = zig_collect_controls(\Zig3d_Widgets\Widgets\Software_Info_Table::class);
foreach (['show_when_empty', 'rows', 'container_background', 'row_divider_color', 'row_min_height', 'label_width', 'value_color'] as $control) {
    Tests::ok('Control exists: ' . $control, in_array($control, $controls, true));
}
$plugin = file_get_contents($root . '/includes/plugin.php');
$css = file_get_contents($root . '/assets/css/zig3d-widgets.css');
Tests::ok('Widget is registered without a script dependency', false !== strpos($plugin, "'software-info-table' => Widgets\\Software_Info_Table::class") && !method_exists($widget, 'get_script_depends'));
Tests::ok('Desktop rows use explicit reference-order label/value columns', false !== strpos($css, '.zig-software-info-table .zig-software-info-table__row') && false !== strpos($css, 'grid-template-columns: minmax(0, 1fr) minmax(0, var(--zig-info-label-width));') && false !== strpos($css, '.zig-software-info-table__label') && false !== strpos($css, 'grid-column: 2;') && false !== strpos($css, '.zig-software-info-table__value') && false !== strpos($css, 'grid-column: 1;'));
Tests::ok('Reference cells align right and own horizontal padding', false !== strpos($css, '.zig-software-info-table .zig-software-info-table__value') && false !== strpos($css, 'text-align: right;') && false !== strpos($css, 'padding-inline: var(--zig-info-row-x);'));
Tests::ok('Every value stretches to one shared RTL start edge', false !== strpos($css, 'justify-self: stretch;') && false !== strpos($css, 'inline-size: 100%;'));
Tests::ok('Desktop label and value share one explicit centered grid row', substr_count($css, 'grid-row: 1;') >= 2 && substr_count($css, 'align-self: center;') >= 2);
Tests::ok('Rows expose a content-safe configurable minimum height', false !== strpos($css, 'min-block-size: var(--zig-info-row-min-height);'));
Tests::ok('Mobile restores label/value stacking with explicit rows', false !== strpos($css, '.zig-software-info-table .zig-software-info-table__value { grid-row: 2; }'));
Tests::ok('Reference zebra rhythm is present', false !== strpos($css, '.zig-software-info-table__row:nth-child(odd)'));
Tests::ok('Table CSS is scoped and mobile rows stack', false !== strpos($css, '@media (max-width: 767px)') && false !== strpos($css, 'grid-template-columns: minmax(0, 1fr);'));
Tests::ok('Obsolete pre-contract table selectors are absent', false === strpos($css, '.zig-software-info__row') && false === strpos(file_get_contents($root . '/includes/widgets/software-info-table.php'), 'zig-software-info__'));
