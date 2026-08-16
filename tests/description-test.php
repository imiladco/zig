<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/elementor-stub.php';
require_once __DIR__ . '/lib/woocommerce-stub.php';

$root = dirname(__DIR__);
require_once $root . '/includes/markup.php';
require_once $root . '/includes/price.php';
require_once $root . '/includes/widgets/traits/box.php';
require_once $root . '/includes/widgets/description.php';

/*
 * ‎wc_get_product()‎/‎WC_Product‎/‎get_the_title()‎/‎get_the_excerpt()‎/
 * ‎get_post_field()‎/‎get_post_meta()‎ از ‎lib/woocommerce-stub.php‎ می‌آیند —
 * تعریفِ مشترک. پستِ عمومی (غیرِمحصول) از رجیستریِ ‎__zig_posts‎ می‌آید
 * (‎zig_register_post()‎)، جدا از محصول و جدا از پیوست.
 */
zig_reset_products();
$GLOBALS['product'] = null;
$GLOBALS['__zig_posts'] = [];
$GLOBALS['__zig_post_meta'] = [];
$GLOBALS['__zig_queried'] = 0;
$GLOBALS['__zig_post'] = 0;

Tests::group('Description widget');

$widget = zig_widget(\Zig3d_Widgets\Widgets\Description::class);
$render = static function (array $settings) use ($widget): string {
    return $widget->zig_render($settings);
};

/* ------------------------------------------------------------------
 * توضیحاتِ محصول
 * ---------------------------------------------------------------- */
new \WC_Product(['id' => 10, 'name' => 'دستگاه X', 'description' => "متنِ معرفیِ محصول با <strong>تگ</strong>."]);

$html = $render(['source' => 'product_description', 'product_id' => 10, 'show_heading' => 'yes', 'heading_text' => 'توضیحات', 'append_title' => 'yes']);
Tests::ok('Product description renders inside the body', false !== strpos($html, 'متنِ معرفیِ محصول با <strong>تگ</strong>.'));
Tests::ok('Heading appends the resolved title when requested', false !== strpos($html, '<h2 class="zig-description__heading">توضیحات دستگاه X</h2>'));
Tests::ok('Body wrapper is present', false !== strpos($html, '<div class="zig-description__body">'));

$html_no_id = $render(['source' => 'product_description', 'product_id' => 999]);
Tests::same('Non-existent explicit product id renders nothing', '', $html_no_id);

/* ------------------------------------------------------------------
 * خودکار: محصول در اولویت است، بعد پستِ عمومی
 * ---------------------------------------------------------------- */
$GLOBALS['product'] = \WC_Product::$registry[10];
Tests::ok('Auto source picks up the Loop product global', false !== strpos($render(['source' => 'auto']), 'متنِ معرفیِ محصول با <strong>تگ</strong>.'));
$GLOBALS['product'] = null;

zig_register_post(701, ['title' => 'یک نوشتهٔ ساده', 'content' => 'محتوایِ نوشته اینجاست.']);
$GLOBALS['__zig_queried'] = 701;
Tests::ok('Auto source falls back to post content when there is no product', false !== strpos($render(['source' => 'auto']), 'محتوایِ نوشته اینجاست.'));

/* ------------------------------------------------------------------
 * محتوایِ نوشته / خلاصه / فیلدِ سفارشی
 * ---------------------------------------------------------------- */
Tests::ok('post_content source reads the post body', false !== strpos($render(['source' => 'post_content']), 'محتوایِ نوشته اینجاست.'));

zig_register_post(701, ['title' => 'یک نوشتهٔ ساده', 'content' => 'محتوایِ نوشته اینجاست.', 'excerpt' => 'خلاصهٔ کوتاه.']);
Tests::ok('post_excerpt source reads the excerpt, not the content', false !== strpos($render(['source' => 'post_excerpt']), 'خلاصهٔ کوتاه.') && false === strpos($render(['source' => 'post_excerpt']), 'محتوایِ نوشته اینجاست.'));

$GLOBALS['__zig_post_meta'][701]['custom_desc'] = 'متنِ فیلدِ سفارشی.';
Tests::ok('custom_field source reads the configured meta key', false !== strpos($render(['source' => 'custom_field', 'custom_field_key' => 'custom_desc']), 'متنِ فیلدِ سفارشی.'));
Tests::same('custom_field with no key configured renders nothing', '', $render(['source' => 'custom_field', 'custom_field_key' => '']));
Tests::same('custom_field with an unset key renders nothing', '', $render(['source' => 'custom_field', 'custom_field_key' => 'missing_key']));

/* ------------------------------------------------------------------
 * فیلترها روشن/خاموش — رفتار قابل‌تشخیص
 * ---------------------------------------------------------------- */
zig_register_post(702, ['title' => 'دو پاراگراف', 'content' => "پاراگرافِ اول.\n\nپاراگرافِ دوم."]);
$GLOBALS['__zig_queried'] = 702;
$filtered = $render(['source' => 'post_content', 'render_filters' => 'yes']);
$plain = $render(['source' => 'post_content', 'render_filters' => '']);
Tests::ok('render_filters=yes leaves raw content untouched by wpautop (the_content is a no-op here)', false === strpos($filtered, '<p>پاراگرافِ اول.</p>'));
Tests::ok('render_filters=off runs wpautop and wraps each paragraph', false !== strpos($plain, '<p>پاراگرافِ اول.</p>') && false !== strpos($plain, '<p>پاراگرافِ دوم.</p>'));

/* ------------------------------------------------------------------
 * خالی بودن — بدونِ shell
 * ---------------------------------------------------------------- */
zig_register_post(703, ['title' => 'خالی', 'content' => '   ']);
$GLOBALS['__zig_queried'] = 703;
Tests::same('Whitespace-only content renders nothing (no empty shell)', '', $render(['source' => 'post_content']));
$GLOBALS['__zig_queried'] = 0;
Tests::same('No resolvable post at all renders nothing', '', $render(['source' => 'post_content']));

/* ------------------------------------------------------------------
 * عنوان و آیکون
 * ---------------------------------------------------------------- */
$GLOBALS['__zig_queried'] = 701;
$no_heading = $render(['source' => 'post_content', 'show_heading' => '']);
Tests::ok('show_heading=off omits the header entirely', false === strpos($no_heading, 'zig-description__head') && false !== strpos($no_heading, 'zig-description__body'));

$with_icon = $render(['source' => 'post_content', 'show_heading' => 'yes', 'heading_text' => '<script>x</script>', 'show_icon' => 'yes', 'heading_icon' => ['url' => 'https://zig3d.test/icon.svg']]);
Tests::ok('Heading text is HTML-escaped', false !== strpos($with_icon, '&lt;script&gt;x&lt;/script&gt;') && false === strpos($with_icon, '<script>x</script>'));
Tests::ok('Icon renders as an escaped, decorative <img>', false !== strpos($with_icon, '<img class="zig-description__icon" src="https://zig3d.test/icon.svg" alt="" />'));

$icon_off = $render(['source' => 'post_content', 'show_heading' => 'yes', 'show_icon' => '', 'heading_icon' => ['url' => 'https://zig3d.test/icon.svg']]);
Tests::ok('show_icon=off omits the <img> even when a URL is configured', false === strpos($icon_off, '<img'));

/* ------------------------------------------------------------------
 * کنترل‌ها، سلکتورها، ثبت
 * ---------------------------------------------------------------- */
$controls = zig_collect_controls(\Zig3d_Widgets\Widgets\Description::class);
foreach ([
    'source', 'custom_field_key', 'product_id', 'render_filters',
    'show_heading', 'heading_text', 'append_title', 'show_icon', 'heading_icon',
    'TABS:container_box_tabs', 'container_box_padding', 'container_box_transition',
    'group:heading_typography', 'heading_color', 'icon_size', 'heading_gap', 'heading_spacing',
    // متن
    'group:body_typography', 'paragraph_spacing', 'text_align', 'body_padding',
    'TABS:body_color_tabs', 'body_color', 'body_hover_color', 'group:body_text_shadow',
    // عنوان‌هایِ متن
    'group:inner_heading_typography', 'inner_heading_color',
    'h2_margin', 'h3_margin', 'h4_margin', 'h5_margin', 'h6_margin',
    // لینک‌ها و بولد
    'group:link_typography', 'TABS:link_state_tabs', 'link_color', 'link_underline', 'link_hover_color', 'link_hover_underline',
    'bold_color', 'bold_font_weight',
    // لیست‌ها
    'list_style_type', 'marker_color', 'marker_size', 'list_indent', 'list_item_spacing', 'list_spacing', 'list_color', 'group:list_typography',
    // نقل‌قول
    'group:quote_typography', 'quote_color', 'quote_background', 'quote_border_color', 'quote_border_width', 'quote_padding',
    // جدول‌ها
    'table_border_color', 'table_border_width', 'table_radius', 'cell_padding', 'cell_text_align', 'table_spacing',
    'th_background', 'th_color', 'group:th_typography',
    'td_background', 'td_zebra_background', 'td_color', 'group:td_typography',
] as $control) {
    Tests::ok('Control exists: ' . $control, in_array($control, $controls, true));
}
foreach (zig_collect_selectors(\Zig3d_Widgets\Widgets\Description::class) as [$control, $selector]) {
    Tests::ok('Selector scoped: ' . $control, false !== strpos($selector, '{{WRAPPER}}'));
}

$widget_source = file_get_contents($root . '/includes/widgets/description.php');
$plugin_source = file_get_contents($root . '/includes/plugin.php');
$css_source = file_get_contents($root . '/assets/css/zig3d-widgets.css');
Tests::ok('Widget is registered', false !== strpos($plugin_source, "'description' => Widgets\\Description::class"));
Tests::ok('Widget has no script dependency — purely server-rendered', !method_exists($widget, 'get_script_depends'));
Tests::ok('Output HTML is sanitized through wp_kses_post', 1 === substr_count($widget_source, 'wp_kses_post('));
Tests::ok('Body/head/heading/icon classes are styled', false !== strpos($css_source, '.zig-description__body') && false !== strpos($css_source, '.zig-description__head') && false !== strpos($css_source, '.zig-description__heading') && false !== strpos($css_source, '.zig-description__icon'));
Tests::ok('Body links, lists and images have generic prose styling', false !== strpos($css_source, '.zig-description__body a') && false !== strpos($css_source, '.zig-description__body ul,') && false !== strpos($css_source, '.zig-description__body img'));
Tests::ok('Container reuses the shared Box trait hover motion, not a fixed transform', false !== strpos($css_source, '.zig-description {') && false !== strpos($css_source, 'transform: translateY(var(--zig-box-translate-y, 0)) scale(var(--zig-box-scale, 1));'));
Tests::ok('List/link/inner-heading/quote/table each have an independently targetable selector', false !== strpos($widget_source, "'{{WRAPPER}} .zig-description__body li::marker'") && false !== strpos($widget_source, "'{{WRAPPER}} .zig-description__body a'") && false !== strpos($widget_source, "'{{WRAPPER}} .zig-description__body h2, {{WRAPPER}} .zig-description__body h3") && false !== strpos($widget_source, "'{{WRAPPER}} .zig-description__body blockquote'") && false !== strpos($widget_source, "'{{WRAPPER}} .zig-description__body th'"));
Tests::ok('List item spacing is a configurable custom property with a sane fallback', false !== strpos($widget_source, '--zig-description-li-gap: {{SIZE}}{{UNIT}};') && false !== strpos($css_source, 'margin-block-start: var(--zig-description-li-gap, 8px);'));
Tests::ok('Per-heading-level spacing controls default to top:20/rest:0, matching the reference', false !== strpos($widget_source, "'default'    => ['top' => '20', 'right' => '0', 'bottom' => '0', 'left' => '0', 'unit' => 'px']") && false !== strpos($widget_source, "foreach (['h2', 'h3', 'h4', 'h5', 'h6'] as \$tag)"));
Tests::ok('Tables are styled: borders, radius, header and zebra-striped body rows', false !== strpos($css_source, '.zig-description__body table') && false !== strpos($css_source, '.zig-description__body th') && false !== strpos($widget_source, "'{{WRAPPER}} .zig-description__body tbody tr:nth-child(even) td'"));
Tests::ok('Link underline is independently configurable per state (normal vs hover)', false !== strpos($widget_source, "'link_underline'") && false !== strpos($widget_source, "'link_hover_underline'"));
Tests::ok('Bold text has independent color and font-weight controls', false !== strpos($widget_source, "'{{WRAPPER}} .zig-description__body strong, {{WRAPPER}} .zig-description__body b'"));
