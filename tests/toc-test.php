<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/elementor-stub.php';
require_once __DIR__ . '/lib/woocommerce-stub.php';

$root = dirname(__DIR__);
require_once $root . '/includes/markup.php';
require_once $root . '/includes/widgets/traits/box.php';
require_once $root . '/includes/widgets/toc.php';

/*
 * این ویجت خودش هیچ سرتیتری رندر نمی‌کند — فهرست را جاوااسکریپت در مرورگر
 * از روی سرتیترهای واقعیِ صفحه می‌سازد (نگاه کنید به assets/js/zig3d-toc.js).
 * پس اینجا فقط ریشه و ویژگی‌های ‎data-*‎ سنجیده می‌شوند: همان چیزی که
 * اسکریپت برای تصمیم‌گیری می‌خواند.
 */

Tests::group('رندر › فهرست مطالب');

$toc = zig_render(\Zig3d_Widgets\Widgets\Toc::class, [
    'heading_tags'       => ['h2', 'h3'],
    'show_title'         => 'yes',
    'title'              => 'فهرست مطالب',
    'title_tag'          => 'div',
    'container_selector' => '.entry-content',
    'exclude_selector'   => '.zig-toc-ignore',
    'min_heading_count'  => 2,
    'scroll_offset'      => 80,
    'smooth_scroll'      => 'yes',
    'highlight_active'   => 'yes',
    'show_numbering'     => 'yes',
    'numbering_digits'   => 'persian',
    'numbering_pad'      => 'yes',
]);

Tests::keeps('ریشه رندر می‌شود', $toc, 'class="zig-toc"');
Tests::keeps('نشانهٔ فعال‌سازی جاوااسکریپت هست', $toc, 'data-zig-toc="1"');
Tests::keeps('تگ‌های انتخابی در data-tags می‌آیند', $toc, 'data-tags="h2,h3"');
Tests::keeps('سلکتور ناحیه منتقل می‌شود', $toc, 'data-scope=".entry-content"');
Tests::keeps('سلکتور نادیده‌گرفتن منتقل می‌شود', $toc, 'data-exclude=".zig-toc-ignore"');
Tests::keeps('حداقل تعداد منتقل می‌شود', $toc, 'data-min="2"');
Tests::keeps('فاصلهٔ اسکرول منتقل می‌شود', $toc, 'data-offset="80"');
Tests::keeps('پیمایش نرم منتقل می‌شود', $toc, 'data-smooth="yes"');
Tests::keeps('تشخیص خودکار منتقل می‌شود', $toc, 'data-spy="yes"');
Tests::keeps('شماره‌گذاری منتقل می‌شود', $toc, 'data-numbering="yes"');
Tests::keeps('نوع ارقام منتقل می‌شود', $toc, 'data-digits="persian"');
Tests::keeps('عنوان رندر می‌شود', $toc, 'zig-toc__title">فهرست مطالب<');
Tests::keeps('ul فهرست خالی روی سرور می‌آید', $toc, '<ul class="zig-toc__list" role="list"></ul>');
Tests::keeps('پیام حالت خالی هم می‌آید', $toc, 'zig-toc__empty');

Tests::same(
    'بدون هیچ تگ عنوانی، چیزی رندر نمی‌شود',
    trim(zig_render(\Zig3d_Widgets\Widgets\Toc::class, ['heading_tags' => []])),
    ''
);

$only_headings = zig_render(\Zig3d_Widgets\Widgets\Toc::class, [
    // تگ‌های نامعتبر (مثل p یا div که در تنظیمات نباید باشند ولی داده می‌تواند دستکاری شود) باید کنار گذاشته شوند
    'heading_tags' => ['h2', 'p', 'div', 'h4', 'script'],
]);

Tests::keeps('فقط تگ‌های سرتیتر واقعی می‌مانند', $only_headings, 'data-tags="h2,h4"');

$no_title = zig_render(\Zig3d_Widgets\Widgets\Toc::class, [
    'heading_tags' => ['h2'],
    'show_title'   => '',
]);

Tests::blocks('بدون نمایش عنوان، ردیف عنوان نمی‌آید', $no_title, 'zig-toc__header');

Tests::group('رندر › فهرست مطالب، اسکیپ');

$dirty_toc = zig_render(\Zig3d_Widgets\Widgets\Toc::class, [
    'heading_tags' => ['h2'],
    'show_title'   => 'yes',
    'title'        => 'عنوان <script>alert(1)</script> <span class="ok">امن</span>',
    'title_tag'    => 'script',
]);

Tests::blocks('اسکریپت داخل عنوان حذف می‌شود', $dirty_toc, '<script>alert(1)');
Tests::keeps('span مجاز باقی می‌ماند', $dirty_toc, '<span class="ok">');
Tests::keeps('تگ عنوانِ نامعتبر به تگ امن برمی‌گردد', $dirty_toc, '<div class="zig-toc__title"');
Tests::blocks('و هیچ عنصر script نمی‌سازد', $dirty_toc, '<script');
