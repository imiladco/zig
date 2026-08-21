<?php
/**
 * ویجتِ اطلاعاتِ تماس.
 *
 * سه چیز سنجیده می‌شود که هیچ‌کدام در پنلِ المنتور خطایی نشان نمی‌دهند:
 *
 *   ۱. معناشناسیِ فهرست — نقطهٔ جداکننده نباید موردِ فهرست شود.
 *   ۲. آیکون‌ها باید همان فایل‌هایِ صادرشده از فیگما باشند، نه معادلِ
 *      تقریبیِ کتابخانه — همان اشتباهی که یک‌بار در سرچ افتاد.
 *   ۳. آیتمِ بی‌مقصد نباید ‎<a>‎ بشود؛ لینکی که جایی نمی‌برد فوکوس
 *      می‌گیرد و کاربرِ کیبورد را سرگردان می‌کند.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/elementor-stub.php';

$root = dirname(__DIR__);

require_once $root . '/includes/selector.php';
require_once $root . '/includes/markup.php';
require_once $root . '/includes/design-icons.php';
require_once $root . '/includes/widgets/traits/box.php';
require_once $root . '/includes/widgets/contact-bar.php';

use Zig3d_Widgets\Widgets\Contact_Bar;

$widget = zig_widget(Contact_Bar::class);
$render = static function (array $settings) use ($widget): string {
    return $widget->zig_render($settings);
};

$design_items = [
    ['_id' => 'a1', 'icon_preset' => 'phone', 'label' => '031-34415816', 'emphasis' => 'yes', 'link' => ['url' => 'tel:03134415816']],
    ['_id' => 'b2', 'icon_preset' => 'whatsapp', 'label' => 'واتساپ', 'link' => ['url' => 'https://wa.me/989123456789']],
    ['_id' => 'c3', 'icon_preset' => 'telegram', 'label' => 'تلگرام', 'link' => ['url' => 'https://t.me/zig3d']],
];

$html = $render(['items' => $design_items]);

/* ==========================================================================
 * ساختار
 * ======================================================================= */

Tests::group('اطلاعاتِ تماس › ساختار');

Tests::keeps('فهرستِ صریح رندر می‌شود', $html, '<ul class="zig-contact" role="list">');

/*
 * ‎role="list"‎ تزئینی نیست: ‎list-style: none‎ در سافاری معناشناسیِ
 * فهرست را از بین می‌برد و صفحه‌خوان دیگر «فهرست، ۳ مورد» نمی‌گوید.
 */
Tests::same('دقیقاً سه مورد، نه بیشتر', substr_count($html, '<li class="zig-contact__item'), 3);

/*
 * نقطهٔ جداکننده نباید عنصرِ واقعی باشد. اگر ‎<li>‎ی جدا می‌شد،
 * صفحه‌خوان «فهرست، ۵ مورد» می‌گفت که دوتاشان هیچ محتوایی ندارند.
 */
Tests::blocks('نقطه موردِ فهرست نیست', $html, 'zig-contact__sep');
Tests::blocks('و عنصرِ جدایی هم برایش ساخته نمی‌شود', $html, 'zig-contact__dot');

$css = file_get_contents($root . '/assets/css/zig3d-widgets.css');
$section = zig_css_section($css, 'اطلاعاتِ تماس');

Tests::keeps('نقطه با ::before کشیده می‌شود', $section, '.zig-contact li.zig-contact__item::before');
Tests::keeps('و رویِ اولین مورد نمی‌آید', $section, ':first-child::before');

/* ==========================================================================
 * قراردادِ لینک
 * ======================================================================= */

Tests::group('اطلاعاتِ تماس › لینک');

Tests::keeps('شماره لینکِ tel می‌شود', $html, 'href="tel:03134415816"');
Tests::keeps('واتساپ مقصدِ خودش را دارد', $html, 'wa.me');
Tests::keeps('تلگرام هم', $html, 't.me');

/*
 * آیتمِ بی‌مقصد ‎<span>‎ می‌ماند. ‎<a>‎ی بدونِ ‎href‎ فوکوس می‌گیرد و جایی
 * نمی‌برد — بدترین حالت برایِ کاربرِ کیبورد.
 */
$html_plain = $render([
    'items' => [['_id' => 'x', 'icon_preset' => 'phone', 'label' => '031-34415816']],
]);

Tests::keeps('بدونِ پیوند، span رندر می‌شود', $html_plain, '<span class="zig-contact__link">');
Tests::blocks('و هیچ لینکی ساخته نمی‌شود', $html_plain, '<a ');

/*
 * ‎<bdi>‎ لازم است نه تزئینی: شمارهٔ لاتین داخلِ نوارِ راست‌به‌چپ، بدونِ
 * ایزوله خط‌تیره‌اش آن‌طرفِ عدد می‌افتد.
 */
Tests::keeps('متن داخلِ bdi است', $html, '<bdi>031-34415816</bdi>');

/* ==========================================================================
 * آیکون‌ها — همان‌هایی که از فیگما آمده‌اند
 * ======================================================================= */

Tests::group('اطلاعاتِ تماس › آیکون‌ها');

foreach (['phone', 'whatsapp', 'telegram'] as $name) {
    $path = $root . '/assets/icons/' . $name . '.svg';
    Tests::ok('فایلِ آیکون هست: ' . $name, is_file($path));

    $svg = is_file($path) ? (string) file_get_contents($path) : '';

    /*
     * رنگ باید ‎currentColor‎ باشد، وگرنه رنگی که فیگما داخلِ ‎path‎
     * نوشته سرِ جایش می‌ماند و کنترلِ رنگِ تبِ استایل بی‌اثر می‌شود.
     */
    Tests::ok(
        'رنگش currentColor است: ' . $name,
        false !== strpos($svg, 'currentColor') && !preg_match('/#[0-9a-fA-F]{3,6}/', $svg)
    );

    /*
     * جعبهٔ بیرونیِ هر سه در طرح ۱۴ در ۱۴ است — حتی وقتی خودِ لیف
     * کوچک‌تر است و با ‎transform‎ سرِ جایش نشسته. اگر ‎viewBox‎ اندازهٔ
     * لیف می‌شد، آیکون‌ها کنارِ هم ناهم‌اندازه در می‌آمدند.
     */
    Tests::keeps('جعبه‌اش ۱۴ در ۱۴ است: ' . $name, $svg, 'viewBox="0 0 14 14"');
}

/* پیش‌تنظیم یعنی همان SVGی فیگما، بدونِ اینکه مدیر کاری کند */
Tests::keeps('ذره‌بینِ گوشی از فایلِ فیگما می‌آید', $html, 'M5.26852 5.81723C7.59548 8.14354');
Tests::keeps('واتساپ هم', $html, 'M6.6232 0.500001C3.2882 0.501039');
Tests::keeps('تلگرام هم', $html, 'M11.9484 1.7418C11.7366 1.68988');

/* «بدونِ آیکون» باید واقعاً هیچ آیکونی نگذارد */
$html_no_icon = $render([
    'items' => [['_id' => 'x', 'icon_preset' => 'none', 'label' => 'واتساپ']],
]);

Tests::blocks('پیش‌تنظیمِ «بدونِ آیکون» چیزی نمی‌گذارد', $html_no_icon, 'zig-contact__icon');

/* ==========================================================================
 * فیلترِ سطرها و پررنگی
 * ======================================================================= */

Tests::group('اطلاعاتِ تماس › سطرها');

$html_mixed = $render([
    'items' => [
        ['_id' => 'a', 'icon_preset' => 'phone', 'label' => '031-34415816'],
        ['_id' => 'b', 'icon_preset' => 'whatsapp', 'label' => '   '],
        ['_id' => 'c', 'icon_preset' => 'telegram', 'label' => 'تلگرام'],
    ],
]);

// سطرِ بی‌متن فقط یک آیکونِ بی‌معنی است — کنار می‌رود
Tests::same('سطرِ فقط-فاصله حذف می‌شود', substr_count($html_mixed, '<li class="zig-contact__item'), 2);

Tests::same('هیچ سطرِ معتبری، هیچ خروجی', $render(['items' => []]), '');
Tests::same('سطرهایِ همه-خالی هم خروجی ندارند', $render(['items' => [['_id' => 'a', 'label' => '']]]), '');

/* در طرح فقط شماره نیم‌ضخیم است */
Tests::keeps('کلیدِ پررنگ کلاسِ خودش را می‌گذارد', $html, 'zig-contact__item--emphasis');
Tests::same(
    'و فقط رویِ همان یک سطر',
    substr_count($html, 'zig-contact__item--emphasis'),
    1
);

/* ==========================================================================
 * دامنهٔ سلکتورها و پوششِ کنترل‌ها
 * ======================================================================= */

Tests::group('اطلاعاتِ تماس › استایل');

preg_match_all('/(?m)^([^@{}\/\s][^{}]*?)\s*\{/', $section, $matches);

$unscoped = [];

foreach ($matches[1] as $group) {
    foreach (explode(',', $group) as $selector) {
        $selector = trim($selector);

        if ('' === $selector || 0 === strpos($selector, '.zig-contact') || 0 === strpos($selector, 'ul.zig-contact')) {
            continue;
        }

        $unscoped[] = $selector;
    }
}

Tests::same('هر انتخاب‌گر با ریشهٔ .zig-contact شروع می‌شود', $unscoped, []);

/* لینک باید نامِ تگ بگیرد، وگرنه رنگ/زیرخطِ قالب رویش می‌نشیند */
Tests::keeps('انتخاب‌گرِ لینک نامِ تگ دارد', $section, 'a.zig-contact__link');

/*
 * همان سنجهٔ خودکارِ بخشِ شیت: هر متغیری که CSS می‌خواند باید کنترلی
 * داشته باشد که بنویسدش. فهرستِ دستی دیر یا زود از کد عقب می‌افتد.
 */
preg_match_all('/var\(\s*(--zig-contact-[a-z-]+)/', $section, $used);

$written = '';

foreach (zig_collect_selectors(Contact_Bar::class) as $entry) {
    $written .= $entry[2];
}

foreach (array_unique($used[1]) as $variable) {
    Tests::ok('کنترل دارد: ' . $variable, false !== strpos($written, $variable));
}
