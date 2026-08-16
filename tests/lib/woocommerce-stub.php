<?php
/**
 * حداقلِ ووکامرس برای تست منطق قیمت.
 *
 * هدف این نیست که ووکامرس شبیه‌سازی شود؛ هدف این است که هر شاخهٔ تصمیمِ
 * کلاس Price بدون نصب ووکامرس قابل اجرا باشد — متغیر، گروهی، تخفیف،
 * مالیات، گزینهٔ پنهان، و سقف تعداد گزینه.
 *
 * ‎wc_get_price_to_display‎ عمداً یک ضریب قابل تنظیم دارد. اگر مقدارش را
 * یک بگذاریم، تستی که «آیا قیمت نمایشی به کار رفته یا خام» را می‌سنجد هیچ
 * تفاوتی نمی‌بیند و بی‌اثر می‌شود. با ضریب ۱٫۰۹ (مثل مالیات) آن تفاوت
 * قابل مشاهده است.
 */

namespace {

    if (!class_exists('WC_Product')) {

        /**
         * محصول آزمایشی.
         *
         * فقط همان متدهایی را دارد که Price صدا می‌زند. اگر روزی Price متد
         * تازه‌ای لازم داشته باشد، تست با خطای «متد وجود ندارد» می‌شکند —
         * که بهتر از سبز ماندنِ بی‌سروصداست.
         */
        class WC_Product {

            /** @var array<string,mixed> */
            protected array $props;

            /** @var array<int,WC_Product> */
            public static array $registry = [];

            public function __construct(array $props = []) {
                $this->props = $props + [
                    'id'         => 0,
                    'type'       => 'simple',
                    'price'      => '',
                    'regular'    => '',
                    'sale'       => '',
                    'on_sale'    => false,
                    'children'   => [],
                    'visible'    => true,
                    'purchasable' => true,
                    'exists'     => true,
                    'suffix'     => '',
                    'min'        => '',
                    'max'        => '',
                    // موجودی
                    'name'      => '',
                    'meta'      => [],
                    'terms'     => [],
                    'attrs'     => [],
                    'thumb'     => '',
                    'status'    => 'instock',
                    'in_stock'  => null,   // null = از status حساب شود
                    'managing'  => false,
                    'qty'       => null,
                    'backorder' => false,
                    'low'       => '',
                    // گالری
                    'image'     => 0,
                    'gallery'   => [],
                    // مشخصاتِ فنی (Spec_Value)
                    'sku'          => '',
                    'attr_map'     => [],
                    'rating_count' => 0,
                    'rating_avg'   => '0',
                    'weight'       => '',
                    'length'       => '',
                    'width'        => '',
                    'height'       => '',
                    'post_terms'   => [],
                    'cat_ids'      => [],
                ];

                if ($this->props['id']) {
                    self::$registry[$this->props['id']] = $this;
                }
            }

            public function exists() { return (bool) $this->props['exists']; }
            public function get_id() { return (int) $this->props['id']; }
            public function is_type($type) { return $type === $this->props['type']; }
            public function get_price() { return $this->props['price']; }
            public function get_regular_price() { return $this->props['regular']; }
            public function get_sale_price() { return $this->props['sale']; }
            public function is_on_sale() { return (bool) $this->props['on_sale']; }
            public function get_children() { return $this->props['children']; }
            public function is_purchasable() { return (bool) $this->props['purchasable']; }
            public function variation_is_visible() { return (bool) $this->props['visible']; }
            public function get_price_suffix() { return $this->props['suffix']; }
            public function get_name() { return $this->props['name']; }
            public function get_attributes() { return $this->props['attrs']; }
            public function get_image_id() { return (int) $this->props['image']; }
            public function get_gallery_image_ids() { return $this->props['gallery']; }
            public function get_status() { return $this->props['status_post'] ?? 'publish'; }

            /**
             * ‎WC_DateTime‎ واقعی، برای پارامترِ کش‌بستنِ ‎?v=‎ در گالری کافی
             * نیست؛ فقط ‎getTimestamp()‎ لازم است.
             */
            public function get_date_modified() {
                if (!isset($this->props['modified'])) {
                    return null;
                }

                return new class((int) $this->props['modified']) {
                    private int $ts;
                    public function __construct(int $ts) { $this->ts = $ts; }
                    public function getTimestamp(): int { return $this->ts; }
                };
            }

            public function zig_meta() { return $this->props['meta']; }
            public function zig_terms() { return $this->props['terms']; }
            public function zig_thumb() { return $this->props['thumb']; }

            public function get_variation_price($which = 'min', $display = false) {
                return $this->props['max' === $which ? 'max' : 'min'];
            }

            /* ---------------------- موجودی ---------------------- */

            public function get_stock_status() { return $this->props['status']; }
            public function managing_stock() { return (bool) $this->props['managing']; }
            public function get_stock_quantity() { return $this->props['qty']; }
            public function get_low_stock_amount() { return $this->props['low']; }

            /**
             * پیش‌فرضِ ووکامرس: هم ‎instock‎ و هم ‎onbackorder‎ «موجود» حساب
             * می‌شوند. همین رفتار است که تشخیص وضعیت را غیربدیهی می‌کند، پس
             * استاب هم باید همان را داشته باشد وگرنه تست چیز دیگری می‌سنجد.
             */
            public function is_in_stock() {
                if (null !== $this->props['in_stock']) {
                    return (bool) $this->props['in_stock'];
                }

                return in_array($this->props['status'], ['instock', 'onbackorder'], true);
            }

            public function is_on_backorder($qty_in_cart = 0) {
                if (!$this->props['backorder'] || !$this->props['managing']) {
                    return false;
                }

                return (int) $this->props['qty'] - (int) $qty_in_cart < 1;
            }

            /* ---------------------- مشخصاتِ فنی (Spec_Value) ---------------------- */

            public function get_sku() { return $this->props['sku']; }
            public function get_attribute($taxonomy) { return $this->props['attr_map'][$taxonomy] ?? ''; }
            public function get_rating_count() { return (int) $this->props['rating_count']; }
            public function get_average_rating() { return $this->props['rating_avg']; }
            public function get_weight() { return $this->props['weight']; }
            public function get_length() { return $this->props['length']; }
            public function get_width() { return $this->props['width']; }
            public function get_height() { return $this->props['height']; }
            public function zig_post_terms() { return $this->props['post_terms']; }
            public function zig_cat_ids() { return $this->props['cat_ids']; }
        }
    }

    if (!function_exists('wc_get_product')) {
        function wc_get_product($id) {
            return \WC_Product::$registry[(int) $id] ?? null;
        }
    }

    if (!function_exists('wc_get_price_to_display')) {
        /** ضریبِ «مالیات» تا بشود دید قیمت نمایشی به کار رفته یا خام */
        function wc_get_price_to_display($product, $args = []) {
            $raw = $args['price'] ?? $product->get_price();

            if ('' === $raw || null === $raw) {
                return '';
            }

            return (string) ((float) $raw * ($GLOBALS['__zig_tax'] ?? 1.0));
        }
    }

    if (!function_exists('wc_get_price_decimals')) {
        function wc_get_price_decimals() { return $GLOBALS['__zig_decimals'] ?? 0; }
    }
    if (!function_exists('wc_get_price_decimal_separator')) {
        function wc_get_price_decimal_separator() { return '.'; }
    }
    if (!function_exists('wc_get_price_thousand_separator')) {
        function wc_get_price_thousand_separator() { return ','; }
    }
    if (!function_exists('get_woocommerce_currency_symbol')) {
        function get_woocommerce_currency_symbol($currency = '') {
            return $GLOBALS['__zig_symbol'] ?? '&#84;&#111;&#109;&#97;&#110;';
        }
    }
    if (!function_exists('get_woocommerce_currency')) {
        function get_woocommerce_currency() { return 'IRT'; }
    }
    if (!function_exists('is_product')) {
        function is_product() { return $GLOBALS['__zig_is_product'] ?? false; }
    }
    if (!function_exists('get_queried_object_id')) {
        function get_queried_object_id() { return $GLOBALS['__zig_queried'] ?? 0; }
    }
    if (!function_exists('get_option')) {
        function get_option($name, $default = false) {
            return $GLOBALS['__zig_options'][$name] ?? $default;
        }
    }
    if (!function_exists('get_the_ID')) {
        function get_the_ID() { return $GLOBALS['__zig_post'] ?? 0; }
    }

    /**
     * ویژگی آزمایشی.
     *
     * فقط همان متدهایی را دارد که ‎Product_Card‎ صدا می‌زند؛ اگر روزی متد
     * تازه‌ای لازم شود، تست با خطا می‌شکند نه اینکه بی‌صدا سبز بماند.
     */
    if (!class_exists('Zig_Test_Attribute')) {
        class Zig_Test_Attribute {
            private array $props;

            public function __construct(array $props = []) {
                $this->props = $props + [
                    'name'      => '',
                    'visible'   => true,
                    'variation' => false,
                    'taxonomy'  => false,
                    'options'   => [],
                ];
            }

            public function get_name() { return $this->props['name']; }
            public function get_visible() { return (bool) $this->props['visible']; }
            public function get_variation() { return (bool) $this->props['variation']; }
            public function is_taxonomy() { return (bool) $this->props['taxonomy']; }
            public function get_options() { return $this->props['options']; }
        }
    }

    if (!function_exists('get_post_meta')) {
        function get_post_meta($id, $key = '', $single = false) {
            $product = \WC_Product::$registry[(int) $id] ?? null;

            if ($product) {
                $meta = $product->zig_meta();

                return $meta[$key] ?? '';
            }

            // پیوست (نه محصول): چند کلیدِ شناخته‌شده مستقیم از رجیستریِ
            // آزمایشیِ پیوست‌ها، بقیه از آرایهٔ ‎meta‎ی دلخواهِ همان پیوست.
            $att = $GLOBALS['__zig_attachments'][(int) $id] ?? null;

            if ($att) {
                if ('_wp_attachment_image_alt' === $key) {
                    return $att['alt'];
                }
                if ('_thumbnail_id' === $key) {
                    return $att['thumbnail_id'];
                }

                return $att['meta'][$key] ?? '';
            }

            // نه محصول، نه پیوست: پستِ عمومی (مثلاً یک CPTِ JetEngine مثلِ
            // «دانلود»)، رجیستریِ ساده‌ی سوم — ‎zig_register_post_meta()‎.
            return $GLOBALS['__zig_post_meta'][(int) $id][$key] ?? '';
        }
    }

    if (!function_exists('get_the_terms')) {
        function get_the_terms($id, $taxonomy) {
            $product = \WC_Product::$registry[(int) $id] ?? null;
            $terms   = $product ? $product->zig_terms() : [];

            return $terms[$taxonomy] ?? false;
        }
    }

    /**
     * متایِ یک پستِ عمومی (نه محصول، نه پیوست) — مثلاً یک CPTِ JetEngine.
     * ‎get_post_meta()‎ی بالا این رجیستری را به‌عنوانِ آخرین ردهٔ fallback
     * می‌خواند.
     */
    if (!isset($GLOBALS['__zig_post_meta'])) {
        $GLOBALS['__zig_post_meta'] = [];
    }
    if (!function_exists('zig_register_post_meta')) {
        function zig_register_post_meta(int $post_id, array $meta): void {
            $GLOBALS['__zig_post_meta'][$post_id] = $meta;
        }
    }

    /*
     * ------------------------------------------------------------------
     * JetEngine — یک مجموعهٔ کوچکِ استابِ *مشترک*، برایِ هر ویجت/کلاسی که
     * دادهٔ خودش را از یک CPTِ JetEngine می‌خواند (دانلود، سازگاری، …).
     *
     * چرا اینجا و نه در هر فایلِ تستِ جداگانه: چند فایلِ تستِ این افزونه
     * هر کدام نسخهٔ خودشان را از همین توابع تعریف کرده بودند — با
     * ‎function_exists()‎ محافظت‌شده، ولی چون ‎tests/run.php‎ همهٔ فایل‌ها
     * را در *یک* پردازشِ PHP بارگذاری می‌کند، فقط اولین تعریف (به ترتیبِ
     * الفباییِ glob) واقعاً اثر می‌کند و بقیه بی‌صدا نادیده گرفته می‌شوند —
     * حتی اگر رفتار/دادهٔ پشتِ‌صحنه‌شان کاملاً فرق داشته باشد. نتیجه: هر
     * فایل به‌تنهایی سبز بود، ولی در اجرای کاملِ سوییت به‌خاطرِ «نشتِ»
     * تعریفِ فایلِ دیگر، شکست می‌خورد. یک تعریفِ مشترک، با یک دسته‌گلوبالِ
     * قابلِ‌reset برایِ هر تست، این کلاس از باگ را کلاً حذف می‌کند.
     */
    if (!isset($GLOBALS['__zig_jet_engine'])) {
        $GLOBALS['__zig_jet_engine'] = null;
    }
    if (!isset($GLOBALS['__zig_registered_post_types'])) {
        $GLOBALS['__zig_registered_post_types'] = [];
    }
    if (!isset($GLOBALS['__zig_post_types'])) {
        $GLOBALS['__zig_post_types'] = [];
    }
    if (!isset($GLOBALS['__zig_taxonomies'])) {
        $GLOBALS['__zig_taxonomies'] = [];
    }
    if (!isset($GLOBALS['__zig_taxonomy_calls'])) {
        $GLOBALS['__zig_taxonomy_calls'] = [];
    }

    if (!function_exists('jet_engine')) {
        function jet_engine() { return $GLOBALS['__zig_jet_engine']; }
    }

    if (!function_exists('post_type_exists')) {
        function post_type_exists($post_type) {
            return in_array($post_type, $GLOBALS['__zig_registered_post_types'], true);
        }
    }

    if (!function_exists('sanitize_key')) {
        function sanitize_key($key) {
            return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $key));
        }
    }

    /**
     * ‎get_post_type()‎ی واقعیِ وردپرس بدونِ آرگومان روی محصولِ/پستِ جاری
     * کار می‌کند؛ اینجا معادلش ‎get_queried_object_id()‎ است — همان‌طور که
     * ‎Download_Archive_Data‎ هم انتظار دارد.
     */
    if (!function_exists('get_post_type')) {
        function get_post_type($post_id = null) {
            $id = null === $post_id || 0 === $post_id ? (int) get_queried_object_id() : (int) $post_id;

            return $GLOBALS['__zig_post_types'][$id] ?? '';
        }
    }

    /**
     * فراخوانی‌ها هم در ‎__zig_taxonomy_calls‎ ثبت می‌شوند تا تستی که
     * می‌خواهد بسنجد «با کدام post_type صدا زده شد» بتواند مستقیم آخرین
     * (یا همهٔ) مقدار را بخواند.
     */
    if (!function_exists('get_object_taxonomies')) {
        function get_object_taxonomies($post_type, $output = 'names') {
            $GLOBALS['__zig_taxonomy_calls'][] = $post_type;

            return $GLOBALS['__zig_taxonomies'];
        }
    }

    /**
     * ‎wp_get_post_terms‎، جدا از ‎get_the_terms‎ چون Spec_Value فقط نامِ
     * ترم‌ها را می‌خواهد (‎fields => names‎)، نه شیءِ کامل — همان چیزی که
     * ‎post_terms‎ی محصولِ آزمایشی مستقیم نگه می‌دارد.
     */
    if (!function_exists('wp_get_post_terms')) {
        function wp_get_post_terms($id, $taxonomy, $args = []) {
            $product = \WC_Product::$registry[(int) $id] ?? null;
            $terms   = $product ? $product->zig_post_terms() : [];

            return $terms[$taxonomy] ?? [];
        }
    }

    if (!function_exists('wc_get_product_stock_status_options')) {
        function wc_get_product_stock_status_options() {
            return $GLOBALS['__zig_stock_status_options'] ?? [
                'instock'     => 'موجود در انبار',
                'outofstock'  => 'ناموجود',
                'onbackorder' => 'قابل پیش‌سفارش',
            ];
        }
    }

    if (!function_exists('number_format_i18n')) {
        function number_format_i18n($number, $decimals = 0) {
            return number_format((float) $number, (int) $decimals);
        }
    }

    /** شناسهٔ دسته‌هایِ محصول — ویجتِ مشخصاتِ فنی از همین‌ها گروه‌هایش را پیدا می‌کند */
    if (!function_exists('wc_get_product_terms')) {
        function wc_get_product_terms($product_id, $taxonomy, $args = []) {
            $product = \WC_Product::$registry[(int) $product_id] ?? null;

            return $product ? $product->zig_cat_ids() : [];
        }
    }

    /** فقط همان شکلِ ساده‌ای که Spec_Store لازم دارد: شناسهٔ دسته => آرایهٔ متا */
    if (!function_exists('get_term_meta')) {
        function get_term_meta($term_id, $key = '', $single = false) {
            $value = $GLOBALS['__zig_term_meta'][(int) $term_id][$key] ?? null;

            if (null === $value) {
                return $single ? '' : [];
            }

            return $single ? $value : [$value];
        }
    }

    if (!function_exists('get_permalink')) {
        function get_permalink($id = 0) { return 'https://zig3d.test/?p=' . (int) $id; }
    }

    if (!function_exists('get_post_thumbnail_id')) {
        function get_post_thumbnail_id($id = null) {
            $product = \WC_Product::$registry[(int) $id] ?? null;

            return $product ? (int) $product->zig_thumb() : 0;
        }
    }

    /*
     * ثبت‌نامِ پیوست‌های آزمایشی — برایِ ویجت‌هایی (مثلِ گالریِ ویدئوی
     * محصول) که مستقیم از فیلدهای خودِ پیوست (Alt/Title/Caption/
     * Description/متادیتا/URL) می‌خوانند، نه از محصول. جدا از
     * ‎WC_Product::$registry‎ چون یک پیوست، محصول نیست.
     *
     *     zig_register_attachment(12, ['title' => '...', 'alt' => '...']);
     */
    if (!isset($GLOBALS['__zig_attachments'])) {
        $GLOBALS['__zig_attachments'] = [];
    }

    if (!function_exists('zig_register_attachment')) {
        function zig_register_attachment(int $id, array $data): void {
            $GLOBALS['__zig_attachments'][$id] = $data + [
                'title'        => '',
                'alt'          => '',
                'caption'      => '',
                'description'  => '',
                'url'          => '',
                'meta'         => [],
                'thumbnail_id' => 0,
            ];
        }
    }

    if (!function_exists('get_the_title')) {
        function get_the_title($id = 0) {
            return $GLOBALS['__zig_attachments'][(int) $id]['title'] ?? '';
        }
    }

    if (!function_exists('wp_get_attachment_caption')) {
        function wp_get_attachment_caption($id = 0) {
            return $GLOBALS['__zig_attachments'][(int) $id]['caption'] ?? '';
        }
    }

    if (!function_exists('get_post_field')) {
        function get_post_field($field, $id = 0) {
            $att = $GLOBALS['__zig_attachments'][(int) $id] ?? null;

            if (!$att) {
                return '';
            }

            if ('post_content' === $field) {
                return $att['description'];
            }
            if ('post_excerpt' === $field) {
                return $att['caption'];
            }
            if ('post_title' === $field) {
                return $att['title'];
            }

            return '';
        }
    }

    if (!function_exists('wp_get_attachment_url')) {
        function wp_get_attachment_url($id = 0) {
            $url = $GLOBALS['__zig_attachments'][(int) $id]['url'] ?? '';

            return '' !== $url ? $url : false;
        }
    }

    if (!function_exists('wp_get_attachment_metadata')) {
        function wp_get_attachment_metadata($id = 0) {
            return $GLOBALS['__zig_attachments'][(int) $id]['meta'] ?? false;
        }
    }

    if (!class_exists('WP_Term')) {
        class WP_Term {
            public $name;
            public $slug;
            public $term_id;

            public function __construct(string $name = '', string $slug = '', int $term_id = 0) {
                $this->name    = $name;
                $this->slug    = $slug;
                $this->term_id = $term_id;
            }
        }
    }

    if (!function_exists('get_terms')) {
        function get_terms($args = []) { return $GLOBALS['__zig_wp_terms'] ?? []; }
    }
    if (!function_exists('get_posts')) {
        function get_posts($args = []) { return []; }
    }

    /**
     * لیستِ محصولات — فقط برایِ fallbackِ «آخرین محصولِ منتشرشده» در
     * ادیتور/پیش‌نمایشِ ویجت‌هایِ گالری. آرگومان‌ها عمداً نادیده گرفته
     * می‌شوند: تستی که از این استاب استفاده می‌کند خودش دقیقاً کنترل
     * می‌کند چه چیزی برگردد.
     */
    if (!function_exists('wc_get_products')) {
        function wc_get_products($args = []) {
            return $GLOBALS['__zig_wc_products'] ?? [];
        }
    }
    if (!function_exists('wc_get_attribute_taxonomy_names')) {
        function wc_get_attribute_taxonomy_names() { return $GLOBALS['__zig_attr_names'] ?? []; }
    }

    /*
     * تاکسونومی‌های *ثبت‌شده* — منبع دومِ فهرست ویژگی‌ها.
     *
     * جدا از ‎wc_get_attribute_taxonomy_names()‎ نگه داشته می‌شود، چون کل
     * نکتهٔ آن منبع دوم همین است که وقتی اولی خالی برمی‌گردد، این یکی
     * هنوز جواب بدهد.
     */
    if (!function_exists('get_taxonomies')) {
        function get_taxonomies($args = [], $output = 'names') { return $GLOBALS['__zig_registered_taxonomies'] ?? []; }
    }
    if (!function_exists('wc_attribute_label')) {
        function wc_attribute_label($name) { return (string) $name; }
    }

    if (!function_exists('wc_get_product_visibility_term_ids')) {
        function wc_get_product_visibility_term_ids() {
            return $GLOBALS['__zig_visibility'] ?? [
                'exclude-from-catalog' => 0,
                'outofstock'           => 0,
            ];
        }
    }

    if (!function_exists('wp_json_encode')) {
        function wp_json_encode($value, $flags = 0, $depth = 512) {
            return json_encode($value, (int) $flags | JSON_UNESCAPED_UNICODE, (int) $depth);
        }
    }

    /** پاک‌کردن وضعیت بین گروه‌های تست */
    function zig_reset_products(): void {
        \WC_Product::$registry = [];
        $GLOBALS['__zig_tax']        = 1.0;
        $GLOBALS['__zig_decimals']   = 0;
        $GLOBALS['__zig_is_product'] = false;
        $GLOBALS['__zig_queried']    = 0;
        $GLOBALS['__zig_post']       = 0;
        $GLOBALS['__zig_options']    = [];
        $GLOBALS['__zig_term_meta']  = [];
        $GLOBALS['__zig_attachments'] = [];
        unset($GLOBALS['product'], $GLOBALS['__zig_visibility'], $GLOBALS['__zig_wc_products']);
    }

    /**
     * پاک‌کردنِ وضعیتِ استابِ JetEngine بینِ فایل‌های تست — جدا از
     * ‎zig_reset_products()‎ چون این‌ها به محصولِ ووکامرس ربطی ندارند
     * (یک CPTِ عمومیِ JetEngine مثلِ «دانلود»).
     */
    function zig_reset_jetengine(): void {
        $GLOBALS['__zig_jet_engine']            = null;
        $GLOBALS['__zig_registered_post_types'] = [];
        $GLOBALS['__zig_post_types']            = [];
        $GLOBALS['__zig_taxonomies']            = [];
        $GLOBALS['__zig_taxonomy_calls']        = [];
        $GLOBALS['__zig_post_meta']             = [];
        $GLOBALS['__zig_queried']               = 0;
    }
}
