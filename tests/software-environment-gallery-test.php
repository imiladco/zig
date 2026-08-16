<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/elementor-stub.php';
require_once __DIR__ . '/lib/woocommerce-stub.php';

$root = dirname(__DIR__);

/*
 * jet_engine()/post_type_exists()/get_post_type()/get_queried_object_id()/
 * get_the_title()/get_post_meta() از ‎lib/woocommerce-stub.php‎ می‌آیند —
 * تعریفِ مشترک، همان دلیلِ download-archive-test.php. تصاویرِ گالری
 * واقعاً پیوست‌اند، پس با ‎zig_register_attachment()‎ ثبت می‌شوند؛ خودِ
 * پستِ CPT («دانلود») از رجیستریِ عمومیِ ‎__zig_post_meta‎.
 */
zig_reset_jetengine();
$POST_ID = 501;

final class Zig_Gallery_CPT { public function get_items(): array { return [['id' => 8, 'slug' => 'download-current']]; } }
final class Zig_Gallery_Fields { public function get_fields_for_context($context, $type): array { return [
    ['name' => 'software_gallery', 'title' => 'Software gallery', 'type' => 'gallery'],
    ['name' => 'poster', 'title' => 'Poster', 'type' => 'media'],
    ['name' => 'title', 'title' => 'Title', 'type' => 'text'],
]; } }
$GLOBALS['__zig_jet_engine'] = (object) ['cpt' => new Zig_Gallery_CPT(), 'meta_boxes' => new Zig_Gallery_Fields()];
$GLOBALS['__zig_registered_post_types'] = ['download-current'];
$GLOBALS['__zig_queried'] = $POST_ID;
$GLOBALS['__zig_post_types'][$POST_ID] = 'download-current';

require_once $root . '/includes/download-archive-data.php';
require_once $root . '/includes/widgets/software-environment-gallery.php';

$reflection = new ReflectionClass(\Zig3d_Widgets\Download_Archive_Data::class);
foreach (['field_schema', 'gallery_fields', 'taxonomies'] as $name) {
    $property = $reflection->getProperty($name); $property->setAccessible(true); $property->setValue(null, null);
}
$property = $reflection->getProperty('post_type'); $property->setAccessible(true); $property->setValue(null, '');

Tests::group('Software environment gallery');
$options = \Zig3d_Widgets\Download_Archive_Data::gallery_field_options();
Tests::ok('Gallery discovery includes only gallery-compatible fields', isset($options['software_gallery'], $options['poster']) && !isset($options['title']));
Tests::same('Software gallery is the schema-backed default', 'software_gallery', \Zig3d_Widgets\Download_Archive_Data::default_gallery_field());

$mixed = \Zig3d_Widgets\Download_Archive_Data::gallery_items(['12', ['id' => 13], ['attachment_id' => 14], ['url' => 'https://zig3d.test/custom.jpg'], 12, 0, '']);
Tests::same('Gallery formats normalize, deduplicate and retain order', [['id' => 12, 'url' => ''], ['id' => 13, 'url' => ''], ['id' => 14, 'url' => ''], ['id' => 0, 'url' => 'https://zig3d.test/custom.jpg']], $mixed);
Tests::same('Comma-separated attachment IDs normalize', [21, 22, 23], array_column(\Zig3d_Widgets\Download_Archive_Data::gallery_items('21,22,23'), 'id'));

$widget = new \Zig3d_Widgets\Widgets\Software_Environment_Gallery();
$GLOBALS['__zig_post_meta'][$POST_ID]['software_gallery'] = '';
Tests::same('Empty gallery renders no frontend shell', '', $widget->zig_render(['gallery_field' => 'software_gallery']));
$GLOBALS['__zig_post_meta'][$POST_ID]['software_gallery'] = [31];
zig_register_attachment(31, ['alt' => 'UPCAM workspace']);
$single = $widget->zig_render(['gallery_field' => 'software_gallery', 'show_heading' => 'yes', 'heading' => 'محیط نرم‌افزار', 'show_description' => 'yes', 'description' => 'UPCAM 3.0', 'show_arrows' => 'yes', 'show_thumbnails' => 'yes']);
Tests::ok('Single image has heading and responsive main image', false !== strpos($single, 'zig-software-gallery__title') && false !== strpos($single, 'data-size="large"') && false !== strpos($single, 'alt="UPCAM workspace"'));
Tests::ok('Single image omits redundant navigation', false === strpos($single, 'data-gallery-next') && false === strpos($single, 'zig-software-gallery__thumbs'));

$GLOBALS['__zig_post_meta'][$POST_ID]['software_gallery'] = [31, 32, 33, 34, 35, 36];
$multiple = $widget->zig_render(['gallery_field' => 'software_gallery', 'show_arrows' => 'yes', 'show_thumbnails' => 'yes']);
Tests::same('Six images render six thumbnail buttons', 6, substr_count($multiple, 'data-gallery-index='));
Tests::ok('Multiple gallery exposes loop controls and selected state', false !== strpos($multiple, 'data-gallery-previous') && false !== strpos($multiple, 'data-gallery-next') && 1 === substr_count($multiple, 'aria-current="true"'));

$controls = zig_collect_controls(\Zig3d_Widgets\Widgets\Software_Environment_Gallery::class);
foreach (['show_heading', 'heading', 'show_description', 'description', 'gallery_field', 'image_fit', 'hide_empty', 'show_arrows', 'show_thumbnails', 'main_ratio', 'thumb_gap'] as $control) {
    Tests::ok('Control exists: ' . $control, in_array($control, $controls, true));
}
$plugin = file_get_contents($root . '/includes/plugin.php');
$js = file_get_contents($root . '/assets/js/zig3d-software-gallery.js');
$css = file_get_contents($root . '/assets/css/zig3d-widgets.css');
Tests::ok('Widget and on-demand script are registered', false !== strpos($plugin, "'software-environment-gallery' => Widgets\\Software_Environment_Gallery::class") && false !== strpos($plugin, "'zig3d-software-gallery'"));
Tests::ok('Controller loops and scopes instances', false !== strpos($js, '(index + thumbs.length) % thumbs.length') && false !== strpos($js, "closest('[data-zig-software-gallery]')"));
Tests::ok('Responsive thumbnail layouts auto-fit on desktop and use three mobile columns', false !== strpos($css, 'grid-template-columns: repeat(auto-fit, minmax(110px, 1fr));') && false !== strpos($css, 'grid-template-columns: repeat(3, minmax(0, 1fr));'));
