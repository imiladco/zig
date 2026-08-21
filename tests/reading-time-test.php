<?php
/**
 * زمانِ تخمینیِ مطالعه — تعدادِ کلمه ÷ سرعت، همیشه دستِ‌کم یک دقیقه.
 */

require_once __DIR__ . '/bootstrap.php';

$root = dirname(__DIR__);

require_once $root . '/includes/reading-time.php';

use Zig3d_Widgets\Reading_Time;

if (!function_exists('strip_shortcodes')) {
    function strip_shortcodes($content) {
        return (string) preg_replace('/\[[^\]]*\]/', '', $content);
    }
}

Tests::group('زمانِ مطالعه › شمارشِ کلمه');

Tests::same('محتوایِ خالی هم یک دقیقه است', Reading_Time::minutes(''), 1);

$short = str_repeat('کلمه ', 50); // ۵۰ کلمه
Tests::same('۵۰ کلمه با سرعتِ ۲۰۰ یک دقیقه است', Reading_Time::minutes($short, 200), 1);

$long = str_repeat('کلمه ', 450); // ۴۵۰ کلمه
Tests::same('۴۵۰ کلمه با سرعتِ ۲۰۰ سه دقیقه است (رند به بالا)', Reading_Time::minutes($long, 200), 3);

$exact = str_repeat('کلمه ', 400); // دقیقاً دو برابرِ سرعت
Tests::same('تقسیمِ دقیق رند نمی‌شود', Reading_Time::minutes($exact, 200), 2);

Tests::group('زمانِ مطالعه › پاک‌سازیِ محتوا');

$html = '<p>یک</p><p>دو</p><p>سه</p>';
/*
 * اگر تگ‌ها با رشتهٔ خالی جایگزین می‌شدند (نه فاصله)، «یک»/«دو»/«سه»
 * به هم می‌چسبیدند و می‌شدند یک کلمه؛ اینجا با سرعتِ خیلی پایین (۱)
 * تفاوت را می‌بینیم: سه کلمهٔ واقعی با سرعتِ ۱ باید ۳ دقیقه بدهد.
 */
Tests::same('سه تگ، سه کلمهٔ جدا', Reading_Time::minutes($html, 1), 3);

$shortcode = 'متنِ ساده [gallery ids="1,2,3"] با شورت‌کد';
Tests::same('شورت‌کد از شمارش حذف می‌شود', Reading_Time::minutes($shortcode, 1), 4);

/*
 * ‎&nbsp;‎ بعدِ decode می‌شود فاصلهٔ نیم‌فاصله‌ایِ یونیکد (U+00A0)، نه یک
 * فاصلهٔ معمولیِ ASCII — اگر جایگزین نشود، «یک»/«دو»/«سه» به هم
 * می‌چسبند و یک کلمه می‌شوند، نه سه‌تا.
 */
$entities = 'یک&nbsp;دو&nbsp;سه';
Tests::same('موجودیتِ nbsp هم مرزِ کلمه است، نه چسباننده', Reading_Time::minutes($entities, 1), 3);

Tests::group('زمانِ مطالعه › سرعتِ نامعتبر');

Tests::same('سرعتِ صفر یا منفی به یک محدود می‌شود', Reading_Time::minutes($short, 0), 50);
