<?php
namespace Zig3d_Widgets;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * لایهٔ دادهٔ ویجتِ «شمارش‌گر» — «چی رو از کجا بشماریم».
 *
 * ویجت خودش هیچ کوئری‌ای نمی‌زند؛ فقط ‎self::count()‎ را با نوعِ منبع و
 * چند آرگومان صدا می‌زند و یک عددِ آماده پس می‌گیرد. این جداسازی همان
 * دلیلِ همیشگی را دارد (مثلِ ‎Feature_Repeater‎/‎Spec_Store‎): منطقِ «هر
 * منبع چطور شمرده می‌شود» بدونِ برپاکردنِ کلِ المنتور قابلِ‌تست بماند، و
 * افزودنِ منبعِ تازه (که کاربر گفته «فعلاً محدود» یعنی قرار است اضافه شود)
 * فقط یک ‎case‎ی تازه اینجا باشد، نه دست‌کاریِ ویجت.
 *
 * ‎null‎ برمی‌گرداند وقتی منبع اصلاً قابلِ‌شمارش نیست (مثلاً پست‌تایپِ
 * دانلودها هنوز با JetEngine ثبت نشده) — تمایزش از ‎0‎ عمدی است: صفر یعنی
 * «شمردم، هیچی نبود»، ‎null‎ یعنی «نتوانستم بشمارم». ویجت این دو را
 * متفاوت نشان می‌دهد (اولی رویِ سایت هم صفر نشان می‌دهد، دومی فقط در
 * ادیتور نوتیس می‌دهد و در سایت هیچ‌چیز چاپ نمی‌کند).
 */
final class Counter_Source {

    public const TYPE_BLOG_CATEGORY = 'blog_category';
    public const TYPE_DOWNLOADS     = 'downloads';

    /**
     * @param array<string,mixed> $args
     */
    public static function count(string $type, array $args = []): ?int {
        switch ($type) {
            case self::TYPE_BLOG_CATEGORY:
                return self::count_blog_category($args);

            case self::TYPE_DOWNLOADS:
                return self::count_downloads();

            default:
                return null;
        }
    }

    /**
     * @param array{scope?:string,category_id?:int} $args
     */
    private static function count_blog_category(array $args): ?int {
        $scope = (string) ($args['scope'] ?? 'all');

        if ('specific' !== $scope) {
            // «تعدادِ کلِ نوشته‌ها از همهٔ دسته‌ها» — مجموعِ همان چیزی است
            // که هر نوشتهٔ منتشرشده در آن حساب می‌شود، صرفِ‌نظر از دسته.
            return self::published_count('post');
        }

        $term_id = absint($args['category_id'] ?? 0);

        if ($term_id <= 0) {
            return null;
        }

        $term = get_term($term_id, 'category');

        if (!$term || (function_exists('is_wp_error') && is_wp_error($term))) {
            return null;
        }

        // ‎WP_Term::$count‎ همان شمارشِ آمادهٔ خودِ وردپرس است — نیازی به
        // کوئریِ جدا نیست، و برخلافِ شمارشِ دستی، با کش/فیلترهایِ خودِ
        // هسته (مثلاً حذفِ پیش‌نویس) هماهنگ می‌ماند.
        return max(0, (int) ($term->count ?? 0));
    }

    /**
     * پست‌تایپِ دانلودها همان چیزی است که ویجتِ «آرشیوِ دانلود» رویش کار
     * می‌کند — با ‎Download_Archive_Data::post_type()‎ (که خودش از رویِ
     * شناسهٔ CPTِ جت‌اینجین resolve می‌کند) پیدا می‌شود، نه یک اسمِ ثابت،
     * چون این اسم می‌تواند رویِ هر سایتی فرق کند.
     */
    private static function count_downloads(): ?int {
        if (!class_exists(__NAMESPACE__ . '\\Download_Archive_Data')) {
            return null;
        }

        $post_type = Download_Archive_Data::post_type();

        if ('' === $post_type) {
            return null;
        }

        return self::published_count($post_type);
    }

    private static function published_count(string $post_type): int {
        $counts = wp_count_posts($post_type);

        return max(0, (int) ($counts->publish ?? 0));
    }
}
