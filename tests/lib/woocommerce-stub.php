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
                    'status'    => 'instock',
                    'in_stock'  => null,   // null = از status حساب شود
                    'managing'  => false,
                    'qty'       => null,
                    'backorder' => false,
                    'low'       => '',
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

    /** پاک‌کردن وضعیت بین گروه‌های تست */
    function zig_reset_products(): void {
        \WC_Product::$registry = [];
        $GLOBALS['__zig_tax']        = 1.0;
        $GLOBALS['__zig_decimals']   = 0;
        $GLOBALS['__zig_is_product'] = false;
        $GLOBALS['__zig_queried']    = 0;
        $GLOBALS['__zig_post']       = 0;
        unset($GLOBALS['product']);
    }
}
