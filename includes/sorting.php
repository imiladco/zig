<?php
namespace Zig3d_Widgets;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ترتیب محصولات.
 *
 * چرا این کلاس هست و چرا مدیر «‎orderby‎» را دستی تایپ نمی‌کند: مرتب‌سازی
 * ووکامرس فقط یک ‎orderby‎ نیست. مرتب‌سازی بر اساس قیمت در ووکامرس با
 * فیلترهای ‎posts_clauses‎ انجام می‌شود، «پرفروش‌ترین» به یک متای شمارش
 * فروش وصل است، و امتیاز از یک متای دیگر می‌آید. یک فیلد متنی که هرچه
 * دلش خواست بپذیرد، یعنی مدیر باید همهٔ این جزئیات را بداند — و روزی که
 * ووکامرس یکی‌شان را عوض کند، هیچ‌کس نمی‌فهمد چرا ترتیب صفحه بی‌سروصدا
 * غلط شده.
 *
 * پس فهرست انواع بسته است و ترجمه به کوئری همیشه از خودِ ووکامرس عبور
 * می‌کند. تنها استثنا «فیلد دلخواه» است که ذاتاً چیزی است که ووکامرس
 * نمی‌شناسد.
 *
 * این مرز، بیرون از این کلاس هم برقرار است: جاوااسکریپت فقط کلید ترتیب را
 * می‌فرستد و هیچ‌وقت خودش ‎meta_query‎ نمی‌سازد. معنای کوئری تماماً مال
 * PHP است.
 */
final class Sorting {

    /**
     * انواع ترتیبِ شناخته‌شده.
     *
     * ‎directional‎ یعنی «صعودی/نزولی» برای این نوع معنا دارد. برای قیمت
     * ندارد، چون ووکامرس جهت را داخل خودِ ‎orderby‎ کدگذاری کرده
     * (‎price‎ و ‎price-desc‎)؛ گذاشتن یک کلید جهتِ جدا کنارش، دو منبع
     * متناقض می‌ساخت.
     */
    public const TYPES = [
        'default' => [
            'label'       => 'پیش‌فرض فروشگاه',
            'orderby'     => 'menu_order',
            'order'       => 'ASC',
            'directional' => false,
        ],
        'popularity' => [
            'label'       => 'پرفروش‌ترین',
            'orderby'     => 'popularity',
            'order'       => 'DESC',
            'directional' => false,
        ],
        'rating' => [
            'label'       => 'بیشترین امتیاز',
            'orderby'     => 'rating',
            'order'       => 'DESC',
            'directional' => false,
        ],
        'date' => [
            'label'       => 'جدیدترین',
            'orderby'     => 'date',
            'order'       => 'DESC',
            'directional' => true,
        ],
        'price' => [
            'label'       => 'ارزان‌ترین',
            'orderby'     => 'price',
            'order'       => 'ASC',
            'directional' => false,
        ],
        'price-desc' => [
            'label'       => 'گران‌ترین',
            'orderby'     => 'price-desc',
            'order'       => 'DESC',
            'directional' => false,
        ],
        'title' => [
            'label'       => 'بر اساس نام',
            'orderby'     => 'title',
            'order'       => 'ASC',
            'directional' => true,
        ],
        'meta' => [
            'label'       => 'فیلد دلخواه',
            'orderby'     => '',
            'order'       => 'DESC',
            'directional' => true,
        ],
    ];

    /* =====================================================================
     * پاک‌سازی فهرست گزینه‌ها
     * =================================================================== */

    /**
     * ردیف‌های خامِ تکرارشونده را به گزینه‌های معتبر تبدیل می‌کند.
     *
     * کلیدِ هر گزینه همان چیزی است که در آدرس می‌نشیند، پس باید یکتا باشد.
     * دو ردیف با یک کلید یعنی یکی از آن دو هرگز انتخاب نمی‌شود — و چون
     * ظاهرش در پنل درست است، کشفش فقط با کلیک‌کردن ممکن می‌شود.
     *
     * @param array<int,array> $rows
     * @return array<int,array{key:string,label:string,type:string,order:string,meta_key:string,meta_type:string}>
     */
    public static function sanitize_options(array $rows): array {
        $options = [];
        $used    = [];

        foreach ($rows as $index => $row) {
            $type = (string) ($row['type'] ?? 'default');

            if (!isset(self::TYPES[$type])) {
                continue;
            }

            $meta_key = self::meta_key((string) ($row['meta_key'] ?? ''));

            // «فیلد دلخواه» بدون فیلد، یک دکمه است که هیچ کاری نمی‌کند
            if ('meta' === $type && '' === $meta_key) {
                continue;
            }

            $key = self::unique_key($row, $type, $meta_key, $index, $used);

            $used[$key] = true;

            $options[] = [
                'key'       => $key,
                'label'     => trim((string) ($row['label'] ?? '')) ?: self::TYPES[$type]['label'],
                'type'      => $type,
                'order'     => self::order($row, $type),
                'meta_key'  => $meta_key,
                'meta_type' => 'text' === ($row['meta_type'] ?? 'num') ? 'text' : 'num',
            ];
        }

        return $options;
    }

    /** کلیدهای موجود در فهرست — همان فهرست سفیدِ اعتبارسنجی آدرس */
    public static function keys(array $options): array {
        return array_column($options, 'key');
    }

    /* =====================================================================
     * انتخاب
     * =================================================================== */

    /**
     * گزینهٔ فعال.
     *
     * کلید ناشناخته به گزینهٔ اول برمی‌گردد نه به خطا: آدرسی که کاربر
     * بوکمارک کرده یا موتور جستجو ایندکس کرده نباید بعد از تغییر تنظیمات
     * ویجت، صفحهٔ خراب بدهد.
     *
     * @param array<int,array> $options
     */
    public static function resolve(array $options, string $key): ?array {
        foreach ($options as $option) {
            if ($option['key'] === $key) {
                return $option;
            }
        }

        return $options[0] ?? null;
    }

    /* =====================================================================
     * ترجمه به کوئری
     * =================================================================== */

    /**
     * آرگومان‌های ‎WP_Query‎ برای یک گزینه.
     *
     * برای انواع استاندارد، ترجمه به خودِ ووکامرس سپرده می‌شود. این فقط
     * «تمیزتر» نیست: ‎get_catalog_ordering_args()‎ در همان فراخوانی،
     * فیلترهای ‎posts_clauses‎ لازم برای مرتب‌سازی قیمت را هم ثبت می‌کند.
     * بازنویسی دستیِ خروجی‌اش، آن اثر جانبی را از دست می‌داد و مرتب‌سازی
     * قیمت بی‌صدا از کار می‌افتاد.
     */
    public static function query_args(array $option): array {
        if ('meta' === ($option['type'] ?? '')) {
            return [
                'orderby'  => 'num' === ($option['meta_type'] ?? 'num') ? 'meta_value_num' : 'meta_value',
                'order'    => $option['order'] ?? 'DESC',
                'meta_key' => $option['meta_key'] ?? '',
            ];
        }

        $orderby = self::TYPES[$option['type']]['orderby'] ?? 'menu_order';
        $order   = $option['order'] ?? 'ASC';

        if (function_exists('WC') && isset(WC()->query) && method_exists(WC()->query, 'get_catalog_ordering_args')) {
            return WC()->query->get_catalog_ordering_args($orderby, $order);
        }

        return self::fallback_args($orderby, $order);
    }

    /**
     * ترجمهٔ جایگزین، برای وقتی ووکامرس در دسترس نیست.
     *
     * عمداً ناقص است و ادعای برابری با ووکامرس ندارد؛ فقط جلوی مرتب‌سازیِ
     * تصادفی را می‌گیرد. جای واقعیِ استفاده‌اش، تست‌هاست.
     */
    public static function fallback_args(string $orderby, string $order = 'ASC'): array {
        switch ($orderby) {
            case 'popularity':
                return ['orderby' => 'meta_value_num', 'order' => 'DESC', 'meta_key' => 'total_sales'];

            case 'rating':
                return ['orderby' => 'meta_value_num', 'order' => 'DESC', 'meta_key' => '_wc_average_rating'];

            case 'price':
                return ['orderby' => 'meta_value_num', 'order' => 'ASC', 'meta_key' => '_price'];

            case 'price-desc':
                return ['orderby' => 'meta_value_num', 'order' => 'DESC', 'meta_key' => '_price'];

            case 'date':
                return ['orderby' => 'date ID', 'order' => 'DESC' === strtoupper($order) ? 'DESC' : 'ASC'];

            case 'title':
                return ['orderby' => 'title', 'order' => 'DESC' === strtoupper($order) ? 'DESC' : 'ASC'];

            case 'rand':
                return ['orderby' => 'rand'];

            default:
                return ['orderby' => 'menu_order title', 'order' => 'ASC'];
        }
    }

    /* =====================================================================
     * کمکی‌ها
     * =================================================================== */

    private static function order(array $row, string $type): string {
        if (!self::TYPES[$type]['directional']) {
            return self::TYPES[$type]['order'];
        }

        return 'ASC' === strtoupper((string) ($row['order'] ?? '')) ? 'ASC' : 'DESC';
    }

    /**
     * کلیدِ آدرس.
     *
     * ترجیح با کلیدِ خودِ مدیر است، بعد نوع، و در آخر پسوند عددی — چون
     * می‌شود دو گزینهٔ «فیلد دلخواه» با دو متای متفاوت داشت که هر دو نوعشان
     * ‎meta‎ است.
     */
    private static function unique_key(array $row, string $type, string $meta_key, int $index, array $used): string {
        $key = self::slug((string) ($row['key'] ?? ''));

        if ('' === $key) {
            $key = 'meta' === $type ? self::slug($meta_key) : $type;
        }

        if ('' === $key) {
            $key = 'sort';
        }

        if (!isset($used[$key])) {
            return $key;
        }

        return $key . '-' . ($index + 1);
    }

    private static function slug(string $value): string {
        return (string) preg_replace('/[^a-z0-9_\-.]/', '', strtolower(trim($value)));
    }

    /** کلید متا: همان مجموعه‌ای که وردپرس برای نام متا می‌پذیرد */
    private static function meta_key(string $value): string {
        return (string) preg_replace('/[^A-Za-z0-9_\-]/', '', trim($value));
    }
}
