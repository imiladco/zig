<?php
/**
 * لایهٔ دادهٔ شمارش‌گر — «چی رو از کجا بشماریم».
 */

require_once __DIR__ . '/bootstrap.php';
/*
 * ‎get_term()‎/‎is_wp_error()‎/‎WP_Error‎/‎wp_count_posts()‎ از اینجا
 * می‌آیند — تعریفِ مشترک، نه محلی. یک‌بار همین‌جا (قبلاً به‌اشتباه محلی
 * تعریف شده بود) ترتیبِ الفباییِ ‎glob()‎ باعث شد این فایل زودتر از
 * ‎menu-test.php‎ لود شود و نسخهٔ خودش را جای نسخهٔ آن فایل بنشاند —
 * ده‌ها سنجهٔ بی‌ربط در ‎menu-test.php‎ شکست.
 */
require_once __DIR__ . '/lib/woocommerce-stub.php';
require_once dirname(__DIR__) . '/includes/counter-source.php';

use Zig3d_Widgets\Counter_Source;

$GLOBALS['__zig_terms'] = [
    5 => (object) ['term_id' => 5, 'name' => 'اخبار', 'count' => 12],
    6 => (object) ['term_id' => 6, 'name' => 'دستهٔ خالی', 'count' => 0],
];

$GLOBALS['__zig_wp_post_counts'] = [
    'post' => (object) ['publish' => 87],
];

Tests::group('شمارش‌گر › دسته‌بندی بلاگ');

Tests::same(
    'یک دستهٔ خاص — عددِ ‎count‎یِ خودِ ترم',
    Counter_Source::count(Counter_Source::TYPE_BLOG_CATEGORY, ['scope' => 'specific', 'category_id' => 5]),
    12
);

Tests::same(
    'دستهٔ خالی — صفر، نه null (شمردیم، هیچی نبود)',
    Counter_Source::count(Counter_Source::TYPE_BLOG_CATEGORY, ['scope' => 'specific', 'category_id' => 6]),
    0
);

Tests::same(
    'دستهٔ ناموجود — null (نتوانستیم بشماریم)',
    Counter_Source::count(Counter_Source::TYPE_BLOG_CATEGORY, ['scope' => 'specific', 'category_id' => 999]),
    null
);

Tests::same(
    'دسته‌ای انتخاب نشده (شناسهٔ صفر) — null',
    Counter_Source::count(Counter_Source::TYPE_BLOG_CATEGORY, ['scope' => 'specific', 'category_id' => 0]),
    null
);

Tests::same(
    'همهٔ نوشته‌ها — مجموعِ کلِ نوشته‌های منتشرشده',
    Counter_Source::count(Counter_Source::TYPE_BLOG_CATEGORY, ['scope' => 'all']),
    87
);

Tests::same(
    'وقتی scope داده نشود پیش‌فرض «همه» است',
    Counter_Source::count(Counter_Source::TYPE_BLOG_CATEGORY, ['category_id' => 5]),
    87
);

$GLOBALS['__zig_terms'][7] = new WP_Error();

Tests::same(
    'WP_Error از get_term هم null می‌دهد، نه فاتال',
    Counter_Source::count(Counter_Source::TYPE_BLOG_CATEGORY, ['scope' => 'specific', 'category_id' => 7]),
    null
);

Tests::group('شمارش‌گر › دانلودها');

/*
 * ‎Download_Archive_Data‎ عمداً لود نمی‌شود — دقیقاً همان حالتی که وقتی
 * جت‌اینجین فعال نیست پیش می‌آید. ‎Counter_Source‎ باید بدونِ فاتال ‎null‎
 * برگرداند.
 */
Tests::same(
    'بدونِ Download_Archive_Data — null، نه فاتال',
    Counter_Source::count(Counter_Source::TYPE_DOWNLOADS),
    null
);

Tests::group('شمارش‌گر › نوعِ نامعتبر');

Tests::same(
    'نوعِ ناشناخته — null',
    Counter_Source::count('چیزِ-عجیب'),
    null
);
