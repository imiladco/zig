<?php
require_once __DIR__ . '/bootstrap.php';

$root = dirname(__DIR__);
require_once $root . '/includes/software-section-guard.php';

use Zig3d_Widgets\Software_Section_Guard;

/**
 * جانشینِ ‎\Elementor\Element_Base‎ — فقط همان چهار متدی که نگهبان
 * واقعاً صدا می‌زند. با این، مسیرِ ‎before_render‎/‎after_render‎ بدونِ
 * بالاآوردنِ المنتور قابلِ‌سنجش است.
 */
final class Zig_Fake_Element {
    private string $id;
    private string $type;
    private string $name;
    private array $children;

    public function __construct(string $id, string $type, string $name = '', array $children = []) {
        $this->id = $id;
        $this->type = $type;
        $this->name = $name;
        $this->children = $children;
    }

    public function get_id(): string { return $this->id; }
    public function get_type(): string { return $this->type; }
    public function get_name(): string { return $this->name; }
    public function get_children(): array { return $this->children; }
}

$widget = static fn (string $id, string $name): Zig_Fake_Element => new Zig_Fake_Element($id, 'widget', $name);
$box = static fn (string $id, array $children): Zig_Fake_Element => new Zig_Fake_Element($id, 'container', '', $children);

/*
 * ساختارِ *واقعیِ* قالبِ «Single Download | 2026» (#15453) روی zig3d.com،
 * از گزارشِ بازرسِ زنده. ساختارِ ساختگی این‌جا کم‌ارزش بود: کلِ نکتهٔ
 * نگهبان تصمیمِ درست روی همین درختِ بخصوص است — به‌ویژه سکشنِ هدر که
 * کنارِ ویجت‌هایِ ما عکس/کارت/دکمه هم دارد و *نباید* هرگز حذف شود.
 */
$header  = $box('27a2f5e', [
    $box('1f194f5', [$widget('50fb691', 'e-image'), $widget('6d905e8', 'e-heading')]),
    $box('9117327', [$widget('24fa73c', 'zig3d-compatible-operating-systems'), $widget('9398c7c', 'zig3d-compatible-devices')]),
    $widget('9be8bfa', 'zig3d-feature-card'),
    $widget('1e959e0', 'zig3d-button'),
]);
$intro   = $box('1048df3', [$widget('ed9cba8', 'e-heading'), $widget('b4c31fb', 'zig3d-description')]);
$gallery = $box('f729f32', [$widget('db19947', 'zig3d-software-environment-gallery')]);
$specs   = $box('8eedd29', [$widget('4a62a8f', 'e-heading'), $widget('f6e3344', 'zig3d-software-info-table')]);
$install = $box('c32ae35', [$widget('364c3af', 'e-heading'), $widget('5cb2d74', 'jet-listing-dynamic-repeater')]);
$related = $box('e773cd4', [$widget('c22bc8f', 'jet-listing-grid')]);

Tests::group('نگهبانِ سکشنِ نرم‌افزار › قاعدهٔ نامزدی');

Tests::ok('سکشنِ «معرفی» (تیتر + توضیحات) نامزد است',
    ['zig-description'] === Software_Section_Guard::markers_for_widgets(['e-heading', 'zig3d-description']));
Tests::ok('تیتر «قاب» است، نه محتوا — مانعِ نامزدشدن نمی‌شود',
    ['zig-software-info-table'] === Software_Section_Guard::markers_for_widgets(['e-heading', 'zig3d-software-info-table']));
Tests::ok('سکشنِ هدر — چون عکس/کارت/دکمهٔ دیگران هم دارد — هرگز نامزد نمی‌شود',
    [] === Software_Section_Guard::markers_for_widgets(['e-image', 'e-heading', 'zig3d-compatible-operating-systems', 'zig3d-feature-card', 'zig3d-button']));
Tests::ok('سکشنِ ریپیترِ جت‌اینجین دست‌نخورده می‌ماند',
    [] === Software_Section_Guard::markers_for_widgets(['e-heading', 'jet-listing-dynamic-repeater']));
Tests::ok('سکشنِ بدونِ هیچ ویجتِ ما نامزد نمی‌شود',
    [] === Software_Section_Guard::markers_for_widgets(['e-heading', 'e-paragraph']));
Tests::ok('چند ویجتِ ما در یک سکشن، همهٔ نشانه‌هایشان جمع می‌شود',
    ['zig-compatible-os', 'zig-compatible-devices'] === Software_Section_Guard::markers_for_widgets(['zig3d-compatible-operating-systems', 'zig3d-compatible-devices']));

Tests::group('نگهبانِ سکشنِ نرم‌افزار › همان قاعده روی دادهٔ خامِ قالب');

$template = [
    ['id' => '27a2f5e', 'elements' => [
        ['id' => 'x', 'widgetType' => 'e-image'],
        ['id' => 'y', 'widgetType' => 'zig3d-compatible-devices'],
        ['id' => 'z', 'widgetType' => 'zig3d-button'],
    ]],
    ['id' => '1048df3', 'elements' => [['id' => 'a', 'widgetType' => 'e-heading'], ['id' => 'b', 'widgetType' => 'zig3d-description']]],
    ['id' => 'f729f32', 'elements' => [['id' => 'c', 'widgetType' => 'zig3d-software-environment-gallery']]],
    ['id' => '8eedd29', 'elements' => [['id' => 'd', 'widgetType' => 'e-heading'], ['id' => 'e', 'widgetType' => 'zig3d-software-info-table']]],
    ['id' => 'c32ae35', 'elements' => [['id' => 'f', 'widgetType' => 'jet-listing-dynamic-repeater']]],
];
$sections = Software_Section_Guard::sections_from_template($template);

Tests::ok('فقط سه سکشنِ درست نامزد می‌شوند', ['1048df3', 'f729f32', '8eedd29'] === array_keys($sections));
Tests::ok('هدر و ریپیتر بیرون می‌مانند', !isset($sections['27a2f5e']) && !isset($sections['c32ae35']));
Tests::ok('المنت‌هایِ بی‌شناسه/غیرآرایه نادیده گرفته می‌شوند',
    [] === Software_Section_Guard::sections_from_template([null, 'x', ['elements' => []]]));
Tests::ok('شناسهٔ حاوی کاراکترِ خطرناک رد می‌شود',
    [] === Software_Section_Guard::sections_from_template([['id' => 'a"]<script>', 'elements' => [['id' => 'q', 'widgetType' => 'zig3d-description']]]]));

Tests::group('نگهبانِ سکشنِ نرم‌افزار › چرخهٔ رندر (بافرِ سکشن، نه کلِ صفحه)');

/*
 * ‎should_guard()‎ رویِ CLI به وردپرس نیاز دارد؛ این‌جا مستقیم مسیرِ
 * تصمیم را می‌سنجیم: بافر باز می‌شود، محتوا (یا نبودش) چاپ می‌شود، و
 * ‎after_render‎ تصمیم می‌گیرد نگه دارد یا دور بریزد.
 */
$run = static function (Zig_Fake_Element $section, string $rendered): string {
    $markers = Software_Section_Guard::markers_for_widgets(
        array_map(static fn ($w) => $w->get_name(), array_filter($section->get_children(), static fn ($c) => 'widget' === $c->get_type()))
    );

    ob_start();
    echo '<div data-id="' . $section->get_id() . '">' . $rendered . '</div>';
    $html = (string) ob_get_clean();

    return Software_Section_Guard::has_marker($html, $markers) ? $html : '';
};

Tests::ok('سکشنی که ویجتش چیزی رندر کرده، کاملاً چاپ می‌شود',
    '' !== $run($intro, '<div class="zig-description">متن</div>'));
Tests::ok('سکشنی که ویجتش ساکت مانده، هیچ چاپ نمی‌شود — نه اینکه با CSS پنهان شود',
    '' === $run($intro, ''));
Tests::ok('تیترِ تنها هم سکشن را نجات نمی‌دهد (تیترِ بی‌جدول بی‌معناست)',
    '' === $run($specs, '<h2>اطلاعات فنی</h2>'));
Tests::ok('جدولِ رندرشده سکشن را نگه می‌دارد',
    '' !== $run($specs, '<h2>اطلاعات فنی</h2><table class="zig-software-info-table"></table>'));
Tests::ok('گالریِ رندرشده سکشن را نگه می‌دارد',
    '' !== $run($gallery, '<div class="zig-software-gallery"></div>'));

Tests::ok('نبودِ نشانه با آرایهٔ خالیِ نشانه‌ها هرگز true نمی‌شود',
    false === Software_Section_Guard::has_marker('<div class="zig-description"></div>', []));
Tests::ok('نشانه در هر جایِ خروجیِ سکشن پذیرفته می‌شود',
    true === Software_Section_Guard::has_marker('<a><b><i class="x zig-faq y"></i></b></a>', ['zig-faq']));

Tests::group('نگهبانِ سکشنِ نرم‌افزار › ایمنیِ سکشن‌هایِ غیرِنامزد');

foreach ([['هدر', $header], ['راهنمای نصب', $install], ['مرتبط‌ها', $related]] as [$label, $section]) {
    $names = [];
    $collect = static function ($el) use (&$collect, &$names): void {
        if ('widget' === $el->get_type()) { $names[] = $el->get_name(); return; }
        foreach ($el->get_children() as $child) { $collect($child); }
    };
    $collect($section);

    Tests::ok('سکشنِ «' . $label . '» هرگز نامزدِ حذف نمی‌شود', [] === Software_Section_Guard::markers_for_widgets($names));
}
