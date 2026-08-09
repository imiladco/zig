<?php
namespace Zig3d_Widgets;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * جمع‌کردن دادهٔ یک کارت محصول، پیش از رندر.
 *
 * چرا جدا از رندر: کارت هشت بخش دارد و هر بخش می‌تواند نباشد — رپیتر پر
 * نشده، برند تعریف نشده، توضیح خالی مانده. اگر این تصمیم‌ها لای مارک‌آپ
 * پخش شوند، هر کدام یک ‎if‎ می‌شود که فقط با نگاه‌کردن به صفحه پیدا
 * می‌شود، و «کارت شکسته» چیزی است که در تست رندر هم درست به نظر می‌رسد.
 *
 * پس اینجا تصمیم گرفته می‌شود چه چیزی هست، و آنجا فقط چاپ می‌شود.
 *
 * خروجی همیشه همان کلیدها را دارد، حتی وقتی خالی‌اند. یعنی رندر می‌تواند
 * بی‌قید و شرط بخواند و خودش تصمیم بگیرد که خالی را چاپ نکند — به‌جای
 * اینکه هر بار ‎isset‎ بزند و یکی را جا بیندازد.
 */
final class Product_Card {

    /** شکل خالی، تا هر مسیر بازگشتی همان کلیدها را داشته باشد */
    private const EMPTY = [
        'id'          => 0,
        'url'         => '',
        'title'       => '',
        'image'       => '',
        'suggested'   => false,
        'brand'       => '',
        'description' => '',
        'features'    => [],
        'stock'       => [],
        'price'       => [],
        'price_mode'  => Card::PRICE_HIDDEN,
        'price_label' => '',
        'cta'         => Card::CTA_DETAILS,
    ];

    /**
     * دادهٔ کارت.
     *
     * @param array $fields {
     *     @type string $suggested_meta   کلید متای چک‌باکس «پیشنهاد».
     *     @type string $description_meta کلید متای توضیح کوتاه.
     *     @type string $features_meta    کلید متای رپیتر ویژگی‌ها.
     *     @type string $features_field   نام زیرفیلدِ برچسب داخل رپیتر.
     *     @type string $brand_taxonomy   تاکسونومی برند.
     *     @type int    $features_max     سقف ویژگی‌ها.
     *     @type string $variable_mode    ‎min‎ یا ‎range‎ برای محصول چندقیمتی.
     *     @type string $no_price         ‎inquiry‎ یا ‎hidden‎.
     *     @type string $cta_mode         ‎auto‎ یا یکی از حالت‌های صریح.
     * }
     */
    public static function data(\WC_Product $product, array $fields = []): array {
        $fields += [
            'suggested_meta'   => '',
            'description_meta' => '',
            'features_meta'    => '',
            'features_field'   => '',
            'brand_taxonomy'   => '',
            'features_max'     => 3,
            'variable_mode'    => 'min',
            'no_price'         => Card::PRICE_INQUIRY,
            'cta_mode'         => Card::CTA_AUTO,
        ];

        $id    = $product->get_id();
        $price = Price::data($product, (string) $fields['variable_mode']);
        $state = Card::price_state($price, (string) $fields['no_price']);

        return [
            'id'          => $id,
            'url'         => (string) get_permalink($id),
            'title'       => (string) $product->get_name(),
            'image'       => self::image($id),
            'suggested'   => self::flag($id, (string) $fields['suggested_meta']),
            'brand'       => self::brand($id, (string) $fields['brand_taxonomy']),
            'description' => self::text($id, (string) $fields['description_meta']),
            'features'    => self::features($product, $fields),
            'stock'       => Stock::state($product),
            'price'       => $price,
            'price_mode'  => $state['mode'],
            'price_label' => $state['label'],
            'cta'         => Card::cta_mode((string) $fields['cta_mode'], $state['mode']),
        ] + self::EMPTY;
    }

    /* =====================================================================
     * بخش‌ها
     * =================================================================== */

    /**
     * چک‌باکسِ «پیشنهاد».
     *
     * مقادیری که وردپرس برای یک چک‌باکس ذخیره می‌کند یکدست نیستند: بسته به
     * اینکه فیلد از جت‌انجین آمده یا از یک متاباکس دستی، می‌تواند ‎'1'‎ یا
     * ‎'yes'‎ یا ‎'on'‎ یا ‎true‎ باشد. پذیرفتن فقط یکی از این‌ها یعنی ریبون
     * روی نصف محصول‌ها بی‌صدا نیاید.
     */
    private static function flag(int $id, string $key): bool {
        if ('' === $key) {
            return false;
        }

        $value = get_post_meta($id, $key, true);

        if (is_bool($value) || is_int($value)) {
            return (bool) $value;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'yes', 'true', 'on'], true);
    }

    private static function text(int $id, string $key): string {
        if ('' === $key) {
            return '';
        }

        return trim(wp_strip_all_tags((string) get_post_meta($id, $key, true)));
    }

    /**
     * برند: اولین ترمِ تاکسونومیِ تعیین‌شده.
     *
     * فقط اولی، عمداً. یک محصول می‌تواند چند ترم داشته باشد ولی کارت جای
     * یکی را دارد؛ چاپ‌کردن همه، چیدمان را می‌شکند و چاپ‌نکردن هیچ‌کدام
     * اطلاعات را دور می‌ریزد.
     */
    private static function brand(int $id, string $taxonomy): string {
        if ('' === $taxonomy) {
            return '';
        }

        $terms = get_the_terms($id, $taxonomy);

        if (!is_array($terms)) {
            return '';
        }

        foreach ($terms as $term) {
            if ($term instanceof \WP_Term) {
                return $term->name;
            }
        }

        return '';
    }

    /**
     * سه ویژگی وسط کارت، با زنجیرهٔ منبع.
     *
     * ترتیب عمدی است: رپیتر جت‌انجین ← ویژگی‌های ووکامرس ← هیچ. محصولی که
     * هنوز رپیترش پر نشده نباید کارتِ شکسته بدهد.
     */
    private static function features(\WC_Product $product, array $fields): array {
        return Card::features(
            [
                self::repeater($product->get_id(), (string) $fields['features_meta'], (string) $fields['features_field']),
                self::attributes($product),
            ],
            (int) $fields['features_max']
        );
    }

    /**
     * برچسب‌های یک رپیتر جت‌انجین.
     *
     * ‎$field‎ خالی یعنی «خودِ ردیف را متن حساب کن» — رپیترهای تک‌فیلدی
     * گاهی همین شکل ذخیره می‌شوند و بدون این حالت، فقط یک آرایه به رشته
     * تبدیل می‌شد.
     */
    private static function repeater(int $id, string $key, string $field): array {
        if ('' === $key) {
            return [];
        }

        $rows = get_post_meta($id, $key, true);

        if (!is_array($rows)) {
            return [];
        }

        $labels = [];

        foreach ($rows as $row) {
            if (is_array($row)) {
                $labels[] = (string) ('' === $field ? reset($row) : ($row[$field] ?? ''));

                continue;
            }

            $labels[] = (string) $row;
        }

        return $labels;
    }

    /**
     * ویژگی‌های ووکامرس، به‌عنوان منبع دوم.
     *
     * فقط ویژگی‌های «قابل نمایش» — همان‌هایی که مدیر تیک «نمایش در صفحهٔ
     * محصول» را برایشان زده. بقیه معمولاً ویژگی‌های گزینه‌سازند و در کارت
     * معنایی ندارند.
     */
    private static function attributes(\WC_Product $product): array {
        if (!method_exists($product, 'get_attributes')) {
            return [];
        }

        $labels = [];

        foreach ($product->get_attributes() as $attribute) {
            if (!is_object($attribute) || !method_exists($attribute, 'get_visible') || !$attribute->get_visible()) {
                continue;
            }

            $name = method_exists($attribute, 'get_name') ? (string) $attribute->get_name() : '';
            $term = self::first_term($product->get_id(), $attribute);

            if ('' !== $term) {
                $labels[] = $term;

                continue;
            }

            if ('' !== $name) {
                $labels[] = $name;
            }
        }

        return $labels;
    }

    private static function first_term(int $id, $attribute): string {
        if (!method_exists($attribute, 'is_taxonomy') || !$attribute->is_taxonomy()) {
            $options = method_exists($attribute, 'get_options') ? (array) $attribute->get_options() : [];

            return (string) (reset($options) ?: '');
        }

        $terms = get_the_terms($id, (string) $attribute->get_name());

        if (!is_array($terms)) {
            return '';
        }

        foreach ($terms as $term) {
            if ($term instanceof \WP_Term) {
                return $term->name;
            }
        }

        return '';
    }

    /**
     * تصویر شاخص.
     *
     * اندازهٔ ‎woocommerce_thumbnail‎ چون همان چیزی است که ووکامرس برای
     * فهرست‌ها می‌سازد؛ استفاده از اندازهٔ کامل یعنی هر کارت چند صد کیلوبایت
     * تصویر بگیرد که مرورگر بعد کوچکش می‌کند.
     */
    private static function image(int $id): string {
        $size = function_exists('wc_get_image_size') ? 'woocommerce_thumbnail' : 'medium';

        return (string) get_the_post_thumbnail($id, $size, ['loading' => 'lazy', 'decoding' => 'async']);
    }
}
