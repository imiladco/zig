<?php
/**
 * ویجتِ شمارش‌گر.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/elementor-stub.php';
require_once __DIR__ . '/lib/woocommerce-stub.php';

$root = dirname(__DIR__);
require_once $root . '/includes/markup.php';
require_once $root . '/includes/price.php';
require_once $root . '/includes/counter-source.php';
require_once $root . '/includes/download-archive-data.php';
require_once $root . '/includes/widgets/counter.php';

use Zig3d_Widgets\Widgets\Counter;

/*
 * ‎get_term()‎/‎wp_count_posts()‎ از ‎lib/woocommerce-stub.php‎ می‌آیند —
 * تعریفِ مشترک، نه محلی (نگاه کنید به توضیحِ همان‌جا و در
 * ‎counter-source-test.php‎).
 */
$GLOBALS['__zig_terms'] = [
    5 => (object) ['term_id' => 5, 'name' => 'اخبار', 'count' => 12],
];

$GLOBALS['__zig_wp_post_counts'] = [
    'post' => (object) ['publish' => 87],
];

$render = static fn (array $settings): string => zig_render(Counter::class, $settings);

Tests::group('شمارش‌گر › دسته‌بندی بلاگ');

$specific = $render(['source_type' => 'blog_category', 'category_scope' => 'specific', 'category_id' => 5]);
Tests::keeps('عدد رندر می‌شود', $specific, '<span class="zig-counter__number">۱۲</span>');
Tests::blocks('بدونِ متنِ قبل/بعد چیزی اضافه نمی‌آید', $specific, 'zig-counter__prefix');

$all = $render(['source_type' => 'blog_category', 'category_scope' => 'all']);
Tests::keeps('«همهٔ نوشته‌ها» مجموعِ کل را نشان می‌دهد', $all, '۸۷');

$missing = $render(['source_type' => 'blog_category', 'category_scope' => 'specific', 'category_id' => 0]);
Tests::same('دسته‌ای انتخاب نشده — چیزی روی سایت چاپ نمی‌شود', $missing, '');

Tests::group('شمارش‌گر › متنِ قبل/بعد');

$with_text = $render([
    'source_type'    => 'blog_category',
    'category_scope' => 'specific',
    'category_id'    => 5,
    'prefix_text'    => 'بیشتر از',
    'suffix_text'    => 'نوشته',
]);
Tests::keeps('متنِ قبل رندر می‌شود', $with_text, '<span class="zig-counter__prefix">بیشتر از</span>');
Tests::keeps('متنِ بعد رندر می‌شود', $with_text, '<span class="zig-counter__suffix">نوشته</span>');

/* ترتیب: prefix باید قبل از number باشد، suffix بعدش — یعنی خودِ HTML markup ترتیبِ درست را می‌سازد */
Tests::ok(
    'ترتیبِ DOM: پیشوند، بعد عدد، بعد پسوند',
    strpos($with_text, 'zig-counter__prefix') < strpos($with_text, 'zig-counter__number')
        && strpos($with_text, 'zig-counter__number') < strpos($with_text, 'zig-counter__suffix')
);

$html_in_text = $render([
    'source_type'    => 'blog_category',
    'category_scope' => 'specific',
    'category_id'    => 5,
    'prefix_text'    => '<script>alert(1)</script>',
]);
Tests::blocks('اسکریپتِ داخلِ متنِ قبل اسکیپ می‌شود', $html_in_text, '<script>');

Tests::group('شمارش‌گر › قالب‌بندیِ عدد');

$latin = $render(['source_type' => 'blog_category', 'category_scope' => 'specific', 'category_id' => 5, 'persian_digits' => '']);
Tests::keeps('بدونِ ارقامِ فارسی، عدد لاتین می‌ماند', $latin, '>12<');

$GLOBALS['__zig_wp_post_counts']['post'] = (object) ['publish' => 12345];
$separated = $render(['source_type' => 'blog_category', 'category_scope' => 'all', 'persian_digits' => '', 'thousands_separator' => 'yes']);
Tests::keeps('جداکنندهٔ هزارگان با علامتِ فارسی', $separated, '12٬345');
$GLOBALS['__zig_wp_post_counts']['post'] = (object) ['publish' => 87];

Tests::group('شمارش‌گر › دانلودها');

final class Zig_Counter_Test_Meta_Boxes {
    public function get_fields_for_context($context, $post_type): array {
        return [];
    }
}
final class Zig_Counter_Test_CPT_Manager {
    public function get_items(): array {
        return [['id' => 8, 'slug' => 'zig-downloads']];
    }
}

/* ریست کردنِ کشِ استاتیکِ Download_Archive_Data، دقیقاً مثلِ download-archive-test.php */
$reflection = new ReflectionClass(\Zig3d_Widgets\Download_Archive_Data::class);
$post_type_property = $reflection->getProperty('post_type');
$post_type_property->setAccessible(true);
$post_type_property->setValue(null, '');

$GLOBALS['__zig_registered_post_types'] = ['zig-downloads'];
$GLOBALS['__zig_jet_engine'] = (object) [
    'meta_boxes' => new Zig_Counter_Test_Meta_Boxes(),
    'cpt'        => new Zig_Counter_Test_CPT_Manager(),
];
$GLOBALS['__zig_wp_post_counts']['zig-downloads'] = (object) ['publish' => 34];

Tests::keeps(
    'با پست‌تایپِ دانلودهای resolve‌شده، عدد رندر می‌شود',
    $render(['source_type' => 'downloads']),
    '۳۴'
);

$post_type_property->setValue(null, '');
$GLOBALS['__zig_jet_engine'] = null;

Tests::same(
    'بدونِ جت‌اینجین، چیزی روی سایت چاپ نمی‌شود',
    $render(['source_type' => 'downloads']),
    ''
);
