<?php
namespace Zig3d_Widgets;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * یک گروهِ مشخصاتِ فنی: یک عنوان + چند مشخصه.
 *
 * قبلاً یک لایهٔ «قالب» هم رویِ این می‌نشست — چند گروه با هم زیرِ یک نامِ
 * مشترک بسته‌بندی می‌شدند و دسته به آن بسته‌بندی وصل می‌شد. آن لایه حذف شد:
 * «گروه‌بندیِ خودِ گروه‌ها» یک سطحِ اضافه بود که هیچ‌چیزی به آن نمی‌ارزید.
 * حالا خودِ گروه واحدِ چندبارمصرف است — در ‎Spec_Store‎ در یک کتابخانهٔ تخت
 * نگه داشته می‌شود و هر دسته مستقیماً چند گروه از همان کتابخانه را،
 * به‌ترتیبِ دلخواه، انتخاب می‌کند.
 *
 * مبدأِ هر مشخصه دقیقاً همان مجموعه‌ایست که ویجتِ «ویژگی‌های محصول» دارد
 * (ویژگی/دسته/برچسب/SKU/امتیاز/موجودی/وزن/طول/عرض/ارتفاع/فیلدِ دلخواه) —
 * عمداً، تا ویجتِ نمایشِ گروه‌بندی‌شده بعداً همان منطقِ resolve_value را
 * بشناسد.
 *
 * ابعاد سه فیلدِ جداست («طول»/«عرض»/«ارتفاع»)، نه یک «ابعاد»ِ واحد — دقیقاً
 * همان‌طور که خودِ ووکامرس در برگهٔ «حمل‌ونقل»ِ محصول نگهشان می‌دارد. یک
 * مشخصه‌ی «ابعاد» ترکیبی معنایی نداشت: مدیر می‌خواست فقط «طول» را با
 * برچسبِ خودش در یک گروه بگذارد، نه هر سه‌تا را قاطی‌شده در یک ردیف.
 */
final class Spec_Group {

    /** انواعِ مبدأِ هر مشخصه */
    public const SOURCES = [
        'attribute', 'category', 'tag', 'sku', 'rating', 'stock', 'weight', 'length', 'width', 'height', 'custom_meta',
    ];

    /**
     * برچسبِ خوانایِ هر مبدأ — هم پنلِ مدیریت (کمبوباکسِ «نوعِ مقدار») و هم
     * ویجتِ نمایش (‎Spec_Value‎، برایِ برچسبِ پیش‌فرضِ مشخصه‌ای که عنوانِ
     * دلخواه ندارد) از همین یک نسخه می‌خوانند — تا رنگ‌عوض‌کردنِ یک نام در
     * پنل، فراموش نشود که در صفحهٔ محصول هم عوض شود.
     *
     * @return array<string,string>
     */
    public static function source_labels(): array {
        return [
            'attribute'   => __('ویژگی محصول', 'zig3d-widgets'),
            'category'    => __('دسته‌بندی', 'zig3d-widgets'),
            'tag'         => __('برچسب‌ها', 'zig3d-widgets'),
            'sku'         => __('شناسهٔ محصول (SKU)', 'zig3d-widgets'),
            'rating'      => __('امتیازِ خریداران', 'zig3d-widgets'),
            'stock'       => __('وضعیتِ موجودی', 'zig3d-widgets'),
            'weight'      => __('وزن', 'zig3d-widgets'),
            'length'      => __('طول', 'zig3d-widgets'),
            'width'       => __('عرض', 'zig3d-widgets'),
            'height'      => __('ارتفاع', 'zig3d-widgets'),
            'custom_meta' => __('فیلدِ دلخواه (متا)', 'zig3d-widgets'),
        ];
    }

    /**
     * یک گروه، یا ‎null‎ اگر بعدِ پاک‌سازی هیچ مشخصهٔ معتبری نماند.
     *
     * @return array{label:string,items:array}|null
     */
    public static function sanitize(array $group): ?array {
        $items = self::sanitize_items((array) ($group['items'] ?? []));

        // گروهِ بی‌مشخصه فقط یک عنوانِ خالی در آکاردئون است
        if (!$items) {
            return null;
        }

        return [
            'label' => self::text((string) ($group['label'] ?? '')),
            'items' => $items,
        ];
    }

    /**
     * @param array<int,array> $items
     * @return array<int,array{source:string,attribute:string,meta_key:string,label:string}>
     */
    public static function sanitize_items(array $items): array {
        $out = [];

        foreach ($items as $item) {
            $clean = self::sanitize_item(is_array($item) ? $item : []);

            if (null !== $clean) {
                $out[] = $clean;
            }
        }

        return $out;
    }

    /**
     * یک مشخصه، یا ‎null‎ اگر هیچ مبدأِ واقعی‌ای معلوم نکند.
     *
     * ‎attribute‎ همیشه یک تاکسونومیِ واقعیِ ووکامرس است (‎pa_...‎) — نامِ
     * دلخواه/تایپی این‌جا راه ندارد. کاربر یک ویژگی از فهرستِ همان چیزی که
     * در ووکامرس ساخته انتخاب می‌کند، نه یک رشتهٔ آزاد؛ اگر واقعاً یک
     * مقدارِ کاملاً سفارشی لازم باشد، مبدأ می‌شود ‎custom_meta‎.
     */
    public static function sanitize_item(array $item): ?array {
        $source = in_array($item['source'] ?? '', self::SOURCES, true) ? $item['source'] : 'attribute';

        $attribute = self::taxonomy((string) ($item['attribute'] ?? ''));

        $clean = [
            'source'    => $source,
            'attribute' => $attribute,
            'meta_key'  => self::key((string) ($item['meta_key'] ?? '')),
            'label'     => self::text((string) ($item['label'] ?? '')),
        ];

        // هیچ ویژگی‌ای انتخاب نشده — یعنی هیچی
        if ('attribute' === $source && '' === $attribute) {
            return null;
        }

        if ('custom_meta' === $source && '' === $clean['meta_key']) {
            return null;
        }

        return $clean;
    }

    /* =====================================================================
     * کمکی‌ها
     * =================================================================== */

    private static function taxonomy(string $value): string {
        return (string) preg_replace('/[^a-z0-9_\-]/', '', strtolower(trim($value)));
    }

    private static function key(string $value): string {
        return (string) preg_replace('/[^a-zA-Z0-9_\-]/', '', trim($value));
    }

    private static function text(string $value): string {
        return trim(wp_strip_all_tags($value));
    }
}
