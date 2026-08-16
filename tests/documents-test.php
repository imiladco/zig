<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/elementor-stub.php';
require_once __DIR__ . '/lib/woocommerce-stub.php';

$root = dirname(__DIR__);
require_once $root . '/includes/markup.php';
require_once $root . '/includes/widgets/traits/box.php';
require_once $root . '/includes/widgets/documents.php';

/*
 * برخلافِ ویجت‌های آرشیوِ دانلود، اسناد به schemaی JetEngine (jet_engine()/
 * post_type_exists()) وابسته نیست — کلیدِ متایِ ریپیتر و کلیدِ هر فیلد از
 * پنلِ المنتور به‌صورتِ متنِ آزاد گرفته می‌شود، پس فقط به get_post_meta()ی
 * عمومیِ ‎lib/woocommerce-stub.php‎ نیاز داریم (رجیستریِ ‎__zig_post_meta‎).
 *
 * استابِ get_settings_for_display() برخلافِ المنتورِ واقعی مقادیرِ
 * پیش‌فرضِ کنترل‌ها را خودکار جایگزین نمی‌کند؛ پس هر ‎field_*‎ی که ویجت به آن
 * نیاز دارد باید صریحاً در هر ‎zig_render()‎ پاس داده شود — دقیقاً همان قراردادِ
 * بقیهٔ فایل‌های تست.
 */
$POST_ID = 701;
$GLOBALS['__zig_queried'] = $POST_ID;
$GLOBALS['__zig_post_meta'][$POST_ID] = [];

Tests::group('Documents widget');

$widget = zig_widget(\Zig3d_Widgets\Widgets\Documents::class);

$fields = [
    'meta_key' => 'documents',
    'field_file' => 'document_file',
    'field_title' => 'document_title',
    'field_format' => 'document_format',
    'field_language' => 'document_language',
    'field_version' => 'document_version',
    'field_date' => 'document_date',
    'field_size' => '',
];
$render = static function (array $overrides = []) use ($widget, $fields): string {
    return $widget->zig_render(array_merge($fields, $overrides));
};

/* ------------------------------------------------------------------
 * بدونِ ردیف
 * ---------------------------------------------------------------- */
Tests::same('Missing meta renders nothing on the frontend', '', $render());

/* ------------------------------------------------------------------
 * نرمال‌سازیِ سه‌گانهٔ فیلدِ فایل + سطرِ بدونِ فایل حذف می‌شود
 * ---------------------------------------------------------------- */
zig_register_attachment(55, ['url' => 'https://zig3d.test/files/manual.pdf']);
$GLOBALS['__zig_post_meta'][$POST_ID]['documents'] = [
    // ۱) شناسهٔ پیوستِ عددی
    ['document_file' => 55, 'document_title' => 'راهنمای نصب <b>کامل</b>', 'document_format' => 'pdf', 'document_language' => 'فارسی', 'document_version' => '2.0', 'document_date' => 1700000000],
    // ۲) آدرسِ رشته‌ای بدونِ پسوند در مسیر (bare domain — پوششِ باگِ pathinfo(null))
    ['document_file' => 'https://cdn.zig3d.test', 'document_title' => '', 'document_date' => 'بهار ۱۴۰۳'],
    // ۳) آبجکتِ رسانه با کلیدِ url
    ['document_file' => ['url' => 'https://zig3d.test/files/readme.txt'], 'document_title' => 'Readme'],
    // سطرِ بدونِ فایل: باید کامل حذف شود
    ['document_file' => '', 'document_title' => 'نادیده گرفته می‌شود'],
    // سطرِ غیرِآرایه‌ای در ریپیتر: باید نادیده گرفته شود، نه خطا
    'یک رشتهٔ نامعتبر',
];

$html = $render();
Tests::same('Three valid rows render, invalid/fileless rows are skipped', 3, substr_count($html, 'zig-documents__card'));
Tests::ok('HTML-bearing title is escaped, not stripped raw', false !== strpos($html, '&lt;b&gt;') && false === strpos($html, '<b>کامل</b>'));
Tests::ok('Explicit format is uppercased', false !== strpos($html, '>PDF<'));
Tests::ok('Bare-domain file URL yields no format badge and no PHP warning leaks into output', false === strpos($html, 'Deprecated') && false === strpos($html, 'Warning'));
Tests::ok('Attachment id resolves to its stored URL as the href', false !== strpos($html, 'href="https://zig3d.test/files/manual.pdf"'));
Tests::ok('Media-object url shape resolves', false !== strpos($html, 'href="https://zig3d.test/files/readme.txt"'));
Tests::ok('Empty title renders no title span, filled titles still do', 2 === substr_count($html, 'zig-documents__title'));
Tests::ok('Numeric timestamp date formats to Y/m/d', false !== strpos($html, date('Y/m/d', 1700000000)));
Tests::ok('Pre-formatted date string passes through unchanged', false !== strpos($html, 'بهار ۱۴۰۳'));
Tests::ok('Card is a single anchor with no nested interactive element', false === strpos($html, '<button') && 3 === substr_count($html, '<a '));
Tests::ok('Filled title becomes the card aria-label', false !== strpos($html, 'aria-label="راهنمای نصب &lt;b&gt;کامل&lt;/b&gt;"'));

/* ------------------------------------------------------------------
 * حجم: مقدارِ دستی بر مسیرِ فیزیکیِ پیوست اولویت دارد
 * ---------------------------------------------------------------- */
$tmp = tempnam(sys_get_temp_dir(), 'zig_doc_');
file_put_contents($tmp, str_repeat('x', 2048));
$GLOBALS['__zig_file'][55] = $tmp;
$GLOBALS['__zig_post_meta'][$POST_ID]['documents'] = [
    ['document_file' => 55, 'document_title' => 'اندازهٔ خودکار'],
];
Tests::ok('Missing explicit size falls back to the attachment file size on disk', false !== strpos($render(), '2 KB'));

$GLOBALS['__zig_post_meta'][$POST_ID]['documents'] = [
    ['document_file' => 55, 'document_title' => 'اندازهٔ دستی', 'document_size' => '999 MB'],
];
$manual_size_html = $render(['field_size' => 'document_size']);
Tests::ok('Explicit size field overrides the on-disk lookup', false !== strpos($manual_size_html, '999 MB') && false === strpos($manual_size_html, '2 KB'));
unlink($tmp);
unset($GLOBALS['__zig_file'][55]);

$GLOBALS['__zig_post_meta'][$POST_ID]['documents'] = [
    ['document_file' => 'https://zig3d.test/files/external.pdf', 'document_title' => 'فایل خارجی'],
];
Tests::ok('A non-attachment URL prints no size rather than a wrong one', false === strpos($render(), 'zig-documents__meta-plain'));

/* ------------------------------------------------------------------
 * سوییچ‌های نمایش
 * ---------------------------------------------------------------- */
$GLOBALS['__zig_post_meta'][$POST_ID]['documents'] = [
    ['document_file' => 'https://zig3d.test/files/x.pdf', 'document_title' => 'سند', 'document_format' => 'pdf', 'document_language' => 'EN', 'document_version' => '1.0', 'document_date' => '۱۴۰۳/۰۱/۰۱'],
];
$hidden_html = $render(['show_format' => '', 'show_language' => '', 'show_version' => '', 'show_date' => '', 'show_size' => '', 'show_download_label' => '']);
Tests::ok('All badges/plain-meta/download-label switches can be turned off independently', false === strpos($hidden_html, 'zig-documents__meta"') && false === strpos($hidden_html, 'zig-documents__meta-plain') && false === strpos($hidden_html, 'zig-documents__download'));

Tests::ok('Default download label renders when enabled', false !== strpos($render(['download_label' => 'دانلود']), 'zig-documents__download'));

/* ------------------------------------------------------------------
 * کنترل‌ها و ثبت
 * ---------------------------------------------------------------- */
$controls = zig_collect_controls(\Zig3d_Widgets\Widgets\Documents::class);
foreach ([
    'meta_key', 'source_post_id',
    'field_file', 'field_title', 'field_format', 'field_language', 'field_version', 'field_date', 'field_size',
    'show_format', 'show_language', 'show_version', 'show_date', 'show_size', 'show_download_label', 'download_label',
    'columns', 'gap', 'card_padding', 'text_align',
    'TABS:card_box_tabs', 'card_box_padding', 'card_box_transition',
    'icon_color', 'icon_bg', 'icon_size', 'icon_radius', 'icon_gap',
    'group:title_typography', 'title_color', 'title_color_hover',
    'group:meta_typography', 'meta_color', 'meta_bg', 'meta_radius', 'meta_plain_color',
    'group:download_typography', 'download_color', 'group:download_border', 'download_bg', 'download_radius',
] as $control) {
    Tests::ok('Control exists: ' . $control, in_array($control, $controls, true));
}
foreach (zig_collect_selectors(\Zig3d_Widgets\Widgets\Documents::class) as [$control, $selector]) {
    Tests::ok('Selector scoped: ' . $control, false !== strpos($selector, '{{WRAPPER}}'));
}

$plugin_source = file_get_contents($root . '/includes/plugin.php');
$css_source = file_get_contents($root . '/assets/css/zig3d-widgets.css');
Tests::ok('Widget is registered', false !== strpos($plugin_source, "'documents' => Widgets\\Documents::class"));
Tests::ok('Widget has no script dependency — pure <a href> markup as documented', !method_exists($widget, 'get_script_depends'));
Tests::ok('Card, icon and download-pill classes are styled', false !== strpos($css_source, '.zig-documents__card') && false !== strpos($css_source, '.zig-documents__icon') && false !== strpos($css_source, '.zig-documents__download'));
Tests::ok('Grid column count is a configurable CSS custom property fallback', false !== strpos($css_source, 'repeat(var(--zig-documents-cols, 3)'));
