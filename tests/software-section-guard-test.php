<?php
require_once __DIR__ . '/bootstrap.php';

$root = dirname(__DIR__);
require_once $root . '/includes/software-section-guard.php';

use Zig3d_Widgets\Software_Section_Guard;

/*
 * ساختارِ *واقعیِ* قالبِ «Single Download | 2026» (#15453) روی zig3d.com،
 * از گزارشِ بازرسِ زنده. تستِ ساختگی این‌جا کم‌ارزش بود: کلِ نکتهٔ این
 * نگهبان تصمیم‌گیری درست روی همین درختِ بخصوص است — به‌ویژه سکشنِ اولِ
 * صفحه که کنارِ ویجت‌هایِ ما عکس/کارت/دکمه هم دارد و *نباید* هرگز حذف شود.
 */
$template = [
    // ۱) هدر: سیستم‌عامل/دستگاه‌هایِ سازگارِ ما + تصویر و کارت و دکمهٔ دیگران
    ['id' => '27a2f5e', 'elType' => 'e-flexbox', 'elements' => [
        ['id' => '1f194f5', 'elType' => 'e-flexbox', 'elements' => [
            ['id' => '50fb691', 'elType' => 'widget', 'widgetType' => 'e-image', 'elements' => []],
            ['id' => '6d905e8', 'elType' => 'widget', 'widgetType' => 'e-heading', 'elements' => []],
        ]],
        ['id' => '9117327', 'elType' => 'e-flexbox', 'elements' => [
            ['id' => '24fa73c', 'elType' => 'widget', 'widgetType' => 'zig3d-compatible-operating-systems', 'elements' => []],
            ['id' => '9398c7c', 'elType' => 'widget', 'widgetType' => 'zig3d-compatible-devices', 'elements' => []],
        ]],
        ['id' => '9be8bfa', 'elType' => 'widget', 'widgetType' => 'zig3d-feature-card', 'elements' => []],
        ['id' => '1e959e0', 'elType' => 'widget', 'widgetType' => 'zig3d-button', 'elements' => []],
    ]],
    // ۲) معرفی: تیتر + توضیحاتِ ما  → نامزدِ حذف
    ['id' => '1048df3', 'elType' => 'e-flexbox', 'elements' => [
        ['id' => 'ed9cba8', 'elType' => 'widget', 'widgetType' => 'e-heading', 'elements' => []],
        ['id' => 'b4c31fb', 'elType' => 'widget', 'widgetType' => 'zig3d-description', 'elements' => []],
    ]],
    // ۳) گالریِ محیطِ نرم‌افزار، تنها  → نامزدِ حذف
    ['id' => 'f729f32', 'elType' => 'e-flexbox', 'elements' => [
        ['id' => 'db19947', 'elType' => 'widget', 'widgetType' => 'zig3d-software-environment-gallery', 'elements' => []],
    ]],
    // ۴) اطلاعاتِ فنی: تیتر + جدولِ ما  → نامزدِ حذف
    ['id' => '8eedd29', 'elType' => 'e-flexbox', 'elements' => [
        ['id' => '4a62a8f', 'elType' => 'widget', 'widgetType' => 'e-heading', 'elements' => []],
        ['id' => 'f6e3344', 'elType' => 'widget', 'widgetType' => 'zig3d-software-info-table', 'elements' => []],
    ]],
    // ۵) راهنمایِ نصب: ریپیترِ خامِ جت‌اینجین  → مالِ ما نیست، دست‌نخورده
    ['id' => 'c32ae35', 'elType' => 'e-flexbox', 'elements' => [
        ['id' => '364c3af', 'elType' => 'widget', 'widgetType' => 'e-heading', 'elements' => []],
        ['id' => '5cb2d74', 'elType' => 'widget', 'widgetType' => 'jet-listing-dynamic-repeater', 'elements' => []],
    ]],
    // ۶) نرم‌افزارهایِ مرتبط: گریدِ جت‌اینجین  → مالِ ما نیست، دست‌نخورده
    ['id' => 'e773cd4', 'elType' => 'e-flexbox', 'elements' => [
        ['id' => 'c22bc8f', 'elType' => 'widget', 'widgetType' => 'jet-listing-grid', 'elements' => []],
    ]],
];

$sections = Software_Section_Guard::sections_from_template($template);

Tests::group('نگهبانِ سکشنِ نرم‌افزار › کشفِ سکشن‌ها از قالب');

Tests::ok(
    'فقط سکشن‌هایی که محتوایشان کاملاً از ویجت‌هایِ ماست نامزد می‌شوند',
    ['1048df3', 'f729f32', '8eedd29'] === array_keys($sections)
);
Tests::ok(
    'سکشنِ هدر — که عکس/کارت/دکمهٔ دیگران هم دارد — هرگز نامزد نمی‌شود',
    !isset($sections['27a2f5e'])
);
Tests::ok(
    'سکشن‌هایِ ریپیتر/گریدِ جت‌اینجین دست‌نخورده می‌مانند',
    !isset($sections['c32ae35']) && !isset($sections['e773cd4'])
);
Tests::ok(
    'تیتر «قاب» است، نه محتوا — مانعِ نامزدشدن نمی‌شود',
    ['zig-description'] === $sections['1048df3']
);
Tests::ok(
    'نشانهٔ هر سکشن کلاسِ ریشهٔ همان ویجت است',
    ['zig-software-gallery'] === $sections['f729f32']
        && ['zig-software-info-table'] === $sections['8eedd29']
);

Tests::group('نگهبانِ سکشنِ نرم‌افزار › تصمیمِ حذف روی HTMLِ رندرشده');

$render = static function (bool $description, bool $gallery, bool $table): string {
    return '<html><body>'
        . '<div data-id="1048df3">' . ($description ? '<div class="zig-description">متن</div>' : '') . '</div>'
        . '<div data-id="f729f32">' . ($gallery ? '<div class="zig-software-gallery">گالری</div>' : '') . '</div>'
        . '<div data-id="8eedd29">' . ($table ? '<div class="zig-software-info-table">جدول</div>' : '') . '</div>'
        . '</body></html>';
};

$all_rendered = Software_Section_Guard::filter_html($render(true, true, true), $sections);
Tests::ok('وقتی همه چیزی رندر کرده‌اند، هیچ استایلی اضافه نمی‌شود', false === strpos($all_rendered, 'zig-software-empty-sections'));

$none_rendered = Software_Section_Guard::filter_html($render(false, false, false), $sections);
Tests::ok('هر سه سکشنِ خالی با data-id هایشان هاید می‌شوند', false !== strpos($none_rendered, '[data-id="1048df3"]')
    && false !== strpos($none_rendered, '[data-id="f729f32"]')
    && false !== strpos($none_rendered, '[data-id="8eedd29"]'));

$partial = Software_Section_Guard::filter_html($render(true, false, true), $sections);
Tests::ok('فقط سکشنِ خالی هاید می‌شود، نه آن‌هایی که محتوا دارند',
    false !== strpos($partial, '[data-id="f729f32"]')
    && false === strpos($partial, '[data-id="1048df3"]')
    && false === strpos($partial, '[data-id="8eedd29"]'));

Tests::ok('خروجی همیشه الحاق است — بایت‌هایِ اصلیِ صفحه دست‌نخورده می‌مانند',
    0 === strpos($partial, $render(true, false, true)));

$absent = Software_Section_Guard::filter_html('<html><body><div data-id="zzzzzzz"></div></body></html>', $sections);
Tests::ok('اگر هیچ‌کدام از شناسه‌ها در صفحه نبود، HTML بی‌تغییر برمی‌گردد',
    '<html><body><div data-id="zzzzzzz"></div></body></html>' === $absent);

Tests::ok('بدونِ نگاشتِ سکشن، هیچ کاری انجام نمی‌شود', '<p>x</p>' === Software_Section_Guard::filter_html('<p>x</p>', []));
Tests::ok('HTMLِ خالی بی‌تغییر برمی‌گردد', '' === Software_Section_Guard::filter_html('', $sections));

Tests::group('نگهبانِ سکشنِ نرم‌افزار › مقاومت در برابرِ ورودیِ بد');

Tests::ok('المنت‌هایِ بی‌شناسه/غیرآرایه نادیده گرفته می‌شوند',
    [] === Software_Section_Guard::sections_from_template([null, 'x', ['elType' => 'e-flexbox']]));
Tests::ok('شناسهٔ حاوی کاراکترِ خطرناک وارد سلکتور نمی‌شود',
    [] === Software_Section_Guard::sections_from_template([
        ['id' => 'a"]{}<script>', 'elType' => 'e-flexbox', 'elements' => [
            ['id' => 'x', 'elType' => 'widget', 'widgetType' => 'zig3d-description', 'elements' => []],
        ]],
    ]));
Tests::ok('سکشنِ بدونِ هیچ ویجتِ ما نامزد نمی‌شود',
    [] === Software_Section_Guard::sections_from_template([
        ['id' => 'abc123', 'elType' => 'e-flexbox', 'elements' => [
            ['id' => 'h', 'elType' => 'widget', 'widgetType' => 'e-heading', 'elements' => []],
        ]],
    ]));
