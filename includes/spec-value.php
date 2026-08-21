<?php
namespace Zig3d_Widgets;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * مقدارِ واقعیِ یک مشخصه، برایِ یک محصولِ مشخص.
 *
 * ‎Spec_Group‎ عمداً چیزی از ووکامرس نمی‌داند — فقط شکلِ داده را پاک
 * می‌کند، بدونِ پایگاه‌داده، بدونِ ‎WC_Product‎، کاملاً تست‌پذیر. اینجا دقیقاً
 * برعکس است: این کلاس هیچ‌کاری با پاک‌سازی ندارد، فقط یک آیتمِ ازپیش‌پاک‌شده
 * را می‌گیرد و می‌پرسد «رویِ *این* محصول، این چیست؟» — همان مرزی که
 * ‎Product_Card‎ هم بینِ «چه چیزی هست» و «چطور رندر شود» گذاشته.
 *
 * خروجیِ خالی یعنی این مشخصه رویِ این محصول چیزی برایِ نشان‌دادن ندارد —
 * محصولی که وزن ندارد، ویژگیِ انتخاب‌شده رویش تنظیم نشده، یا کلیدِ متایش
 * خالی است. تصمیمِ «ردیفِ خالی نمایش داده نشود» با فراخواننده است (ویجت)،
 * نه اینجا؛ این کلاس فقط می‌گوید خالی است یا نه.
 */
final class Spec_Value {

    /**
     * برچسب + مقدار، یا ‎null‎ اگر این مشخصه رویِ این محصول مقداری ندارد.
     *
     * @param array{source:string,attribute:string,meta_key:string,label:string} $item
     * @return array{label:string,value:string}|null
     */
    public static function resolve(\WC_Product $product, array $item): ?array {
        $source = $item['source'] ?? 'attribute';
        $value  = self::value($product, $source, (string) ($item['attribute'] ?? ''), (string) ($item['meta_key'] ?? ''));

        if ('' === $value) {
            return null;
        }

        $label = '' !== ($item['label'] ?? '') ? $item['label'] : self::default_label($source, (string) ($item['attribute'] ?? ''), (string) ($item['meta_key'] ?? ''));

        return ['label' => $label, 'value' => $value];
    }

    private static function value(\WC_Product $product, string $source, string $attribute, string $meta_key): string {
        switch ($source) {
            case 'attribute':
                return '' === $attribute || !method_exists($product, 'get_attribute')
                    ? ''
                    : trim((string) $product->get_attribute($attribute));

            case 'category':
                return self::terms($product->get_id(), 'product_cat');

            case 'tag':
                return self::terms($product->get_id(), 'product_tag');

            case 'sku':
                return trim((string) $product->get_sku());

            case 'rating':
                return self::rating($product);

            case 'stock':
                return self::stock_label($product);

            case 'weight':
                return self::with_unit($product->get_weight(), 'woocommerce_weight_unit');

            case 'length':
                return self::with_unit($product->get_length(), 'woocommerce_dimension_unit');

            case 'width':
                return self::with_unit($product->get_width(), 'woocommerce_dimension_unit');

            case 'height':
                return self::with_unit($product->get_height(), 'woocommerce_dimension_unit');

            case 'custom_meta':
                return '' === $meta_key ? '' : trim(wp_strip_all_tags((string) get_post_meta($product->get_id(), $meta_key, true)));
        }

        return '';
    }

    /** برچسبِ پیش‌فرض، وقتی عنوانِ دلخواهی نوشته نشده */
    private static function default_label(string $source, string $attribute, string $meta_key): string {
        if ('attribute' === $source && '' !== $attribute && function_exists('wc_attribute_label')) {
            return (string) wc_attribute_label($attribute);
        }

        // فیلدِ دلخواه هیچ نامِ ثبت‌شده‌ای ندارد — کلیدِ خودش تنها راهنماست
        if ('custom_meta' === $source) {
            return $meta_key;
        }

        return Spec_Group::source_labels()[$source] ?? $source;
    }

    private static function terms(int $product_id, string $taxonomy): string {
        $terms = function_exists('wp_get_post_terms') ? wp_get_post_terms($product_id, $taxonomy, ['fields' => 'names']) : [];

        if (!is_array($terms)) {
            return '';
        }

        return implode(__('، ', 'zig3d-widgets'), array_map('trim', $terms));
    }

    /** میانگینِ امتیاز، فقط وقتی حداقل یک نظرِ واقعی پشتش باشد */
    private static function rating(\WC_Product $product): string {
        if (!method_exists($product, 'get_rating_count') || 0 === (int) $product->get_rating_count()) {
            return '';
        }

        $average = (float) $product->get_average_rating();

        return sprintf(
            /* translators: %s: امتیاز، از پنج */
            __('%s از ۵', 'zig3d-widgets'),
            function_exists('number_format_i18n') ? number_format_i18n($average, 1) : (string) $average
        );
    }

    private static function stock_label(\WC_Product $product): string {
        if (!method_exists($product, 'get_stock_status')) {
            return '';
        }

        $status  = $product->get_stock_status();
        $options = function_exists('wc_get_product_stock_status_options') ? wc_get_product_stock_status_options() : [];

        return (string) ($options[$status] ?? '');
    }

    /** عدد + واحد، فقط اگر عدد واقعاً چیزی برایِ نشان‌دادن داشته باشد */
    private static function with_unit($raw, string $unit_option): string {
        $number = trim((string) $raw);

        if ('' === $number) {
            return '';
        }

        $unit = function_exists('get_option') ? (string) get_option($unit_option) : '';

        return '' === $unit ? $number : $number . ' ' . $unit;
    }
}
