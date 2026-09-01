<?php
/**
 * حذفِ سکشنِ خالیِ سوالاتِ متداول.
 *
 * تکنیک و حتی متدهایِ کمکی عیناً همان ‎Product_Section_Guard‎ است —
 * همان سنجه‌هایِ حیاتی (بایت‌هایِ صفحه دست‌نخورده می‌مانند، اسکیپِ HTML
 * نمی‌شکند، کلاسِ آلوده تزریق نمی‌شود) اینجا هم تکرار می‌شوند، چون این
 * فایلِ مستقل همان کدِ حساس را دوباره دارد، نه فقط همان رفتار را.
 */

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/faq-section-guard.php';

use Zig3d_Widgets\Faq_Section_Guard;

function zig_faq_section_hidden(string $html): bool {
    if (false === strpos($html, '<style id="zig-faq-empty-sections">')) {
        return false;
    }

    $style = substr($html, strpos($html, '<style id="zig-faq-empty-sections">'));

    return false !== strpos($style, '.zig-faq-section{display:none');
}

Tests::group('نگهبانِ سکشنِ FAQ › سازگاری با کالبکِ واقعیِ ob_start');

ob_start(static fn (string $html): string => Faq_Section_Guard::filter_html($html));
echo '<html><body><section class="zig-faq-section"><div class="zig-faq">x</div></section></body></html>';
$ob_out = ob_get_clean();

Tests::keeps('کالبکِ ob_start بدونِ TypeError اجرا می‌شود', (string) $ob_out, 'zig-faq');

Tests::group('نگهبانِ سکشنِ FAQ › صفحه‌ای بدونِ سکشنِ FAQ');

Tests::same(
    'صفحهٔ بی‌ربط دست‌نخورده می‌ماند',
    Faq_Section_Guard::filter_html('<html><body><p>سلام</p></body></html>'),
    '<html><body><p>سلام</p></body></html>'
);

Tests::group('نگهبانِ سکشنِ FAQ › پر/خالی');

$with_items = '<html><body>'
    . '<section class="zig-faq-section"><h2>سوالات متداول</h2><div class="zig-faq"><details class="zig-faq__item"><summary>س</summary></details></div></section>'
    . '<p>بعدی</p></body></html>';

Tests::keeps('سکشنِ پر نگه داشته می‌شود', Faq_Section_Guard::filter_html($with_items), 'zig-faq__item');

$without_items = '<html><body>'
    . '<section class="zig-faq-section"><h2>سوالات متداول</h2></section>'
    . '<p>بعدی</p></body></html>';

$filtered = Faq_Section_Guard::filter_html($without_items);
Tests::ok('سکشنِ خالی هاید می‌شود', zig_faq_section_hidden($filtered));
Tests::keeps('بقیهٔ صفحه دست‌نخورده می‌ماند', $filtered, 'بعدی');
Tests::keeps('حتی وقتی خالی، خودِ سکشن از HTML حذف نمی‌شود (فقط هاید)', $filtered, '<section class="zig-faq-section">');

/*
 * نوتیسِ ادیتور («هنوز سوالی اضافه نشده») هم یک markerِ .zig-faq نیست —
 * پس اگر روزی این متن در ادیتور دیده شود، این نگهبان (که آن‌جا اصلاً
 * اجرا نمی‌شود چون should_guard() پیش‌نمایش را رد می‌کند) قرار نیست
 * آن را هم به‌اشتباه به‌عنوانِ «پر» بشناسد.
 */
$editor_notice_only = '<html><body>'
    . '<section class="zig-faq-section"><div class="zig-notice">هنوز سوالی اضافه نشده</div></section>'
    . '</body></html>';
Tests::ok('نوتیسِ خالیِ ادیتور به‌تنهایی «پر» حساب نمی‌شود', zig_faq_section_hidden(Faq_Section_Guard::filter_html($editor_notice_only)));

Tests::group('نگهبانِ سکشنِ FAQ › چند سکشن هم‌زمان');

$mixed = '<html><body>'
    . '<section class="zig-faq-section"><div class="zig-faq">پر</div></section>'
    . '<section class="zig-faq-section"><p>خالی</p></section>'
    . '</body></html>';

$mixed_filtered = Faq_Section_Guard::filter_html($mixed);
Tests::keeps('سکشنِ پر می‌ماند', $mixed_filtered, 'پر');
Tests::ok('حداقل یکی از سکشن‌هایِ خالی باعثِ تزریقِ استایلِ هاید می‌شود', zig_faq_section_hidden($mixed_filtered));

Tests::group('نگهبانِ سکشنِ FAQ › حاشیهٔ حافظه — جلوگیری از فاتال به‌جایِ ریسک‌کردن');

$headroom = new ReflectionMethod(Faq_Section_Guard::class, 'has_memory_headroom');
$headroom->setAccessible(true);

Tests::ok(
    'وقتی حافظهٔ کافی هست، اجازهٔ پارس داده می‌شود',
    true === $headroom->invoke(null, str_repeat('x', 1000), '256M', 10 * 1024 * 1024)
);
Tests::ok(
    'وقتی مصرفِ فعلی + حجمِ HTML به سقف نزدیک است، پارس رد می‌شود',
    false === $headroom->invoke(null, str_repeat('x', 5 * 1024 * 1024), '64M', 60 * 1024 * 1024)
);
Tests::ok(
    'memory_limit نامحدود (-1) همیشه اجازه می‌دهد',
    true === $headroom->invoke(null, str_repeat('x', 50 * 1024 * 1024), '-1', 500 * 1024 * 1024)
);

Tests::group('نگهبانِ سکشنِ FAQ › تشخیصِ آیفریمِ پیش‌نمایشِ ادیتور (elementor-preview)');

$is_editing = new ReflectionMethod(Faq_Section_Guard::class, 'is_elementor_editing');
$is_editing->setAccessible(true);

$original_get = $_GET;

$_GET['elementor-preview'] = '123';
Tests::ok(
    'با elementor-preview در کوئری‌استرینگ، بدونِ نیاز به آبجکتِ المنتور، پیش‌نمایش تشخیص داده می‌شود',
    true === $is_editing->invoke(null)
);

unset($_GET['elementor-preview']);
Tests::ok(
    'بدونِ elementor-preview و بدونِ کلاسِ المنتور، پیش‌نمایش تشخیص داده نمی‌شود',
    false === $is_editing->invoke(null)
);

$_GET = $original_get;

Tests::group('نگهبانِ سکشنِ FAQ › بایت‌هایِ صفحه دست‌نخورده می‌مانند');

$real = '<html lang="fa" dir="rtl"><body>'
    . '<div class="elementor-widget" data-settings="{&quot;url&quot;:&quot;https://a.test/?x=1&amp;y=2&quot;}">x</div>'
    . '<p>شرکتِ الف &amp; ب — نرخ &lt;۵٪&gt; و «ویژه» ✅</p>'
    . '<section class="zig-faq-section"><p>خالی</p></section>'
    . '</body></html>';

$out = Faq_Section_Guard::filter_html($real);

Tests::ok('سکشنِ خالی هنوز درست هاید می‌شود', zig_faq_section_hidden($out));
Tests::ok('خروجی دقیقاً با همان بایت‌هایِ ورودی شروع می‌شود', 0 === strpos($out, $real));
Tests::keeps('اسکیپِ &quot; در data-attribute دست‌نخورده', $out, '&quot;url&quot;');
Tests::keeps('اسکیپِ &amp; در URL دست‌نخورده', $out, 'x=1&amp;y=2');
Tests::keeps('اسکیپِ &lt;…&gt; در متن دست‌نخورده', $out, '&lt;۵٪&gt;');
Tests::keeps('گیومهٔ فارسی و ایموجی سالم', $out, '«ویژه» ✅');
Tests::blocks('براکتِ خام واردِ متن نشده', $out, 'نرخ <۵٪>');

Tests::group('نگهبانِ سکشنِ FAQ › ثبت و پیوند به ویجت');

$plugin_source = file_get_contents(dirname(__DIR__) . '/includes/plugin.php');
Tests::ok('boot_faq روی init ثبت شده', false !== strpos($plugin_source, "add_action('init', [\$this, 'boot_faq'], 5);"));
Tests::ok('boot_faq نگهبان را بوت می‌کند', false !== strpos($plugin_source, 'Faq_Section_Guard::boot();'));
Tests::keeps('boot_faq بدونِ ووکامرس هم اجرا می‌شود — فقط پشتِ is_admin() است، نه WooCommerce', $plugin_source, 'public function boot_faq(): void {
        if (is_admin()) {
            return;
        }');

$faq_source = file_get_contents(dirname(__DIR__) . '/includes/widgets/faq.php');
Tests::ok('کلاسِ مارکرِ خودِ ویجت zig-faq است — همان چیزی که نگهبان دنبالش می‌گردد', false !== strpos($faq_source, "echo '<div class=\"zig-faq\">';"));
