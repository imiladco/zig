<?php
namespace Zig3d_Widgets;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * دادهٔ خامِ ویجتِ «انتخابِ کانفیگِ محصول» — کشوها و واریانت‌ها.
 *
 * مثلِ ‎Price‎ و ‎Stock‎، عمداً از المنتور جداست تا هر شاخهٔ منطق بدون بالا
 * آوردنِ ادیتور قابلِ تست باشد. هیچ فرمت‌بندیِ نمایشی اینجا نیست — نه رقمِ
 * فارسی، نه واحدِ پول، نه متنِ فارسیِ وضعیتِ موجودی. آن‌ها تصمیمِ ویجت‌اند
 * (از تنظیماتِ کاربر می‌آیند)، این کلاس فقط از ووکامرس می‌خواند.
 *
 * دو اتریبیوتِ ثابت («کانفیگِ دستگاه»، «متریال») در طرح نمونه بودند، نه
 * قاعده: اینجا هر اتریبیوتی که محصول واقعاً برایِ واریانت‌سازی استفاده
 * کرده خوانده می‌شود — یکی، دوتا، یا بیشتر.
 */
final class Configurator {

    /**
     * سقفِ تعدادِ واریانتی که بارگذاری می‌شود.
     *
     * همان دلیلِ ‎Price::MAX_VARIATIONS‎: محصولی با صدها گزینه نباید صدها
     * آبجکت بسازد. اینجا آستانه پایین‌تر است چون کلِ آرایه در HTML تعبیه
     * می‌شود (نه فقط یک عدد) و به مرورگر هم می‌رسد.
     */
    private const MAX_VARIATIONS = 120;

    /**
     * کشوهایِ ویجت — هر اتریبیوتی که واقعاً در واریانت‌سازی استفاده شده.
     *
     * @return array<int,array{key:string,label:string,options:array<int,array{value:string,label:string}>}>
     */
    public static function fields(\WC_Product $product): array {
        if (!$product->is_type('variable') || !method_exists($product, 'get_variation_attributes')) {
            return [];
        }

        $fields = [];

        foreach ($product->get_variation_attributes() as $key => $values) {
            $key = (string) $key;

            if (!is_array($values) || [] === $values) {
                continue;
            }

            $fields[] = [
                'key'     => $key,
                'label'   => function_exists('wc_attribute_label') ? (string) wc_attribute_label($key) : $key,
                'options' => self::options($key, $values),
            ];
        }

        return $fields;
    }

    /**
     * گزینه‌هایِ یک اتریبیوت، با برچسبِ نمایشی.
     *
     * دو مسیر چون ووکامرس دو نوع اتریبیوت دارد: تاکسونومی (‎pa_*‎، مقدارش
     * اسلاگِ ترم است و برچسبش نامِ ترم) و دلخواه (مقدارش خودِ متنِ نمایشی
     * است). برایِ دلخواه، مقدار با ‎sanitize_title()‎ به همان شکلی درمی‌آید
     * که ووکامرس در ذخیرهٔ خودِ واریانت به کار می‌برد — دقیقاً همان کاری
     * که ‎wc_dropdown_variation_attribute_options()‎ی خودِ ووکامرس می‌کند؛
     * بدونش مقدارِ گزینه با مقدارِ ذخیره‌شدهٔ واریانت یکی درنمی‌آمد و هیچ
     * ترکیبی مچ نمی‌شد.
     *
     * @param string[] $values
     * @return array<int,array{value:string,label:string}>
     */
    private static function options(string $key, array $values): array {
        $is_taxonomy = function_exists('taxonomy_exists') && taxonomy_exists($key);
        $out = [];

        foreach ($values as $value) {
            $value = (string) $value;

            if ('' === $value) {
                continue;
            }

            if ($is_taxonomy) {
                $term = function_exists('get_term_by') ? get_term_by('slug', $value, $key) : null;

                $out[] = [
                    'value' => $value,
                    'label' => ($term && !is_wp_error($term)) ? (string) $term->name : $value,
                ];

                continue;
            }

            $out[] = [
                'value' => function_exists('sanitize_title') ? sanitize_title($value) : $value,
                'label' => $value,
            ];
        }

        return $out;
    }

    /**
     * واریانت‌هایِ قابلِ‌خرید، با دادهٔ خامِ هر کدام.
     *
     * فقط واریانتِ دیده‌شدنی و قابلِ‌خرید — همان قیدی که ‎Price::children()‎
     * دارد و همان دلیل: واریانتِ پنهان یا ناموجودِ غیرقابلِ‌خرید نباید
     * مبنایِ انتخاب شود.
     *
     * @return array<int,array{id:int,attributes:array<string,string>,price:string,stock_state:string,updated_at:int}>
     */
    public static function variations(\WC_Product $product): array {
        if (!$product->is_type('variable') || !method_exists($product, 'get_children')) {
            return [];
        }

        if (!function_exists('wc_get_product')) {
            return [];
        }

        $ids = $product->get_children();

        if (count($ids) > self::MAX_VARIATIONS) {
            return [];
        }

        $rows = [];

        foreach ($ids as $id) {
            $variation = wc_get_product($id);

            if (!$variation instanceof \WC_Product || !$variation->exists()) {
                continue;
            }

            if (method_exists($variation, 'variation_is_visible') && !$variation->variation_is_visible()) {
                continue;
            }

            if (!$variation->is_purchasable()) {
                continue;
            }

            $price = Price::data($variation, 'min', false);

            if (!$price['has_price']) {
                continue;
            }

            $stock = Stock::state($variation, [
                'backorder' => true,
                'lowstock'  => false,
                'aggregate' => false,
            ]);

            $rows[] = [
                'id'          => $variation->get_id(),
                'attributes'  => method_exists($variation, 'get_attributes') ? (array) $variation->get_attributes() : [],
                'price'       => $price['current'],
                'stock_state' => $stock['state'],
                'updated_at'  => class_exists(__NAMESPACE__ . '\\Rate_Price') ? Rate_Price::updated_at_for($variation) : 0,
            ];
        }

        return $rows;
    }
}
