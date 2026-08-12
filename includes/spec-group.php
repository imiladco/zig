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
 * (ویژگی/دسته/برچسب/SKU/امتیاز/موجودی/وزن/ابعاد/فیلدِ دلخواه) — عمداً،
 * تا ویجتِ نمایشِ گروه‌بندی‌شده بعداً همان منطقِ resolve_value را بشناسد.
 */
final class Spec_Group {

    /** انواعِ مبدأِ هر مشخصه */
    public const SOURCES = [
        'attribute', 'category', 'tag', 'sku', 'rating', 'stock', 'weight', 'dimensions', 'custom_meta',
    ];

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
     * @return array<int,array{source:string,attribute:string,custom_attribute:string,meta_key:string,label:string}>
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

    /** یک مشخصه، یا ‎null‎ اگر هیچ مبدأِ واقعی‌ای معلوم نکند */
    public static function sanitize_item(array $item): ?array {
        $source = in_array($item['source'] ?? '', self::SOURCES, true) ? $item['source'] : 'attribute';

        $attribute = self::taxonomy((string) ($item['attribute'] ?? ''));
        if ('' === $attribute) {
            $attribute = 'custom';
        }

        $custom_attribute = self::text((string) ($item['custom_attribute'] ?? ''));

        $clean = [
            'source'           => $source,
            'attribute'        => $attribute,
            'custom_attribute' => $custom_attribute,
            'meta_key'         => self::key((string) ($item['meta_key'] ?? '')),
            'label'            => self::text((string) ($item['label'] ?? '')),
        ];

        // نه ویژگیِ سراسری انتخاب شده، نه نامی برایِ ویژگیِ سفارشی — یعنی هیچی
        if ('attribute' === $source && 'custom' === $attribute && '' === $custom_attribute) {
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
        $value = strtolower(trim($value));

        return 'custom' === $value ? 'custom' : (string) preg_replace('/[^a-z0-9_\-]/', '', $value);
    }

    private static function key(string $value): string {
        return (string) preg_replace('/[^a-zA-Z0-9_\-]/', '', trim($value));
    }

    private static function text(string $value): string {
        return trim(wp_strip_all_tags($value));
    }
}
