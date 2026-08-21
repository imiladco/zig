<?php
/**
 * کمکی‌های مارک‌آپ.
 */

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/markup.php';

use Zig3d_Widgets\Markup;

Tests::group('مارک‌آپ › تگ عنوان');

Tests::same('تگ معتبر پذیرفته می‌شود', Markup::tag('h2'), 'h2');
Tests::same('حروف بزرگ نرمال می‌شود', Markup::tag('H4'), 'h4');
Tests::same('فاصلهٔ اضافه حذف می‌شود', Markup::tag('  p '), 'p');
Tests::same('تگ ناشناخته به پیش‌فرض برمی‌گردد', Markup::tag('script'), 'div');
Tests::same('پیش‌فرض دلخواه رعایت می‌شود', Markup::tag('', 'h3'), 'h3');

/*
 * مهم‌ترین سنجهٔ این فایل: مقدارِ دستکاری‌شده در دیتابیس نباید بتواند تگ
 * دلخواه بسازد. اگر روزی کسی فهرست سفید را دور بزند، خروجی رندر مستقیماً
 * به مارک‌آپ تزریق می‌شود.
 */
foreach (['script', 'iframe', 'a href=x', 'h2 onclick=alert(1)', '../div', 'h2>'] as $evil) {
    Tests::same('تگ خطرناک رد می‌شود: ' . $evil, Markup::tag($evil), 'div');
}

Tests::group('مارک‌آپ › متن درون‌خطی');

Tests::keeps('span نگه داشته می‌شود', Markup::text('سلام <span class="x">دنیا</span>'), '<span');
Tests::keeps('strong نگه داشته می‌شود', Markup::text('<strong>مهم</strong>'), '<strong');
Tests::keeps('br نگه داشته می‌شود', Markup::text('خط<br>بعد'), '<br');
Tests::blocks('script حذف می‌شود', Markup::text('<script>alert(1)</script>سلام'), '<script');
Tests::blocks('iframe حذف می‌شود', Markup::text('<iframe src="x"></iframe>'), '<iframe');

/*
 * تگ ‎<a>‎ عمداً مجاز نیست.
 *
 * دلیلش امنیت نیست، ساختار است: عنوانِ کارت ممکن است خودش داخل یک ‎<a>‎
 * باشد و لینکِ تودرتو مارک‌آپ نامعتبر می‌سازد؛ مرورگر آن را باز می‌کند و
 * چیدمان به هم می‌ریزد.
 */
Tests::blocks('لینک تودرتو مجاز نیست', Markup::text('<a href="#">لینک</a>'), '<a ');

Tests::group('مارک‌آپ › تشخیص خالی بودن');

Tests::same('متن واقعی پر است', Markup::filled('سلام'), true);
Tests::same('رشتهٔ خالی، خالی است', Markup::filled(''), false);
Tests::same('فقط فاصله، خالی است', Markup::filled('   '), false);
Tests::same('فقط br، خالی است', Markup::filled('<br>'), false);
Tests::same('فقط span تهی، خالی است', Markup::filled('<span></span>'), false);
Tests::same('span با متن، پر است', Markup::filled('<span>x</span>'), true);
Tests::same('صفر یک مقدار واقعی است', Markup::filled('0'), true);

/*
 * چرا این مهم است: کاربر فیلد را با یک ‎<br>‎ یا چند فاصله «خالی» می‌کند و
 * بررسی سادهٔ ‎'' !== $value‎ آن را پر می‌بیند. نتیجه یک عنصر تهی با پدینگ و
 * حاشیه است که در طراحی به‌صورت یک فاصلهٔ بی‌دلیل دیده می‌شود.
 */
Tests::same('ترکیب فاصله و br، خالی است', Markup::filled(" <br> \n "), false);

/* ==========================================================================
 * رنگِ هر حالت موجودی
 *
 * کلاس‌های CSS از ثابت‌های Stock ساخته می‌شوند (‎zig-card__stock--{state}‎).
 * اگر روزی حالتی اضافه شود و قاعده‌اش نه، آن لیبل بی‌رنگ می‌ماند — و
 * بی‌رنگ یعنی «نامعلوم»، که بدترین چیزی است که یک لیبل موجودی می‌تواند
 * بگوید.
 * ======================================================================= */

Tests::group('نشانه‌گذاری › رنگ حالت‌های موجودی');

$css = (string) file_get_contents(dirname(__DIR__) . '/assets/css/zig3d-widgets.css');

foreach (Zig3d_Widgets\Stock::STATES as $state) {
    Tests::ok(
        'حالت «' . $state . '» قاعدهٔ رنگ دارد',
        false !== strpos($css, '.zig-card__stock--' . $state . ' ') || false !== strpos($css, '.zig-card__stock--' . $state . ',')
    );
}
