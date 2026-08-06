<?php
namespace Zig3d_Widgets;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * تشخیص وضعیت موجودی محصول.
 *
 * مثل کلاس Price، عمداً از المنتور جداست تا هر شاخهٔ تصمیم مستقیم قابل تست
 * باشد. و شاخه‌ها اینجا کم نیستند — همین است که این ویجت را از یک
 * ‎if (is_in_stock())‎ ساده جدا می‌کند.
 *
 * چرا ‎is_in_stock()‎ به‌تنهایی کافی نیست:
 *
 *   • برای محصولِ «در پیش‌خرید» هم true برمی‌گرداند. یعنی محصولی که موجود
 *     نیست ولی سفارش‌پذیر است، «موجود» اعلام می‌شود.
 *   • تفاوتی بین «۵ عدد در انبار» و «موجود، بدون شمارش» نمی‌گذارد. این دو
 *     برای مشتری یکی نیستند و برای فروشنده هم.
 *   • آستانهٔ «رو به اتمام» ووکامرس را نمی‌بیند.
 *
 * ووکامرس سه وضعیت ذخیره می‌کند (‎instock‎، ‎outofstock‎، ‎onbackorder‎) ولی
 * فضای واقعیِ حالت‌ها بزرگ‌تر است، چون «مدیریت موجودی» می‌تواند روشن یا
 * خاموش باشد و تعداد می‌تواند ثبت شده باشد یا نه. این کلاس آن فضا را به
 * پنج حالتِ روشن تبدیل می‌کند.
 */
final class Stock {

    /** تعداد ثبت شده و بیشتر از آستانه */
    public const IN_STOCK = 'instock';

    /** تعداد ثبت شده و کم — فقط وقتی کاربر این حالت را روشن کرده باشد */
    public const LOW_STOCK = 'lowstock';

    /**
     * موجود، ولی تعدادش شمرده نمی‌شود.
     *
     * این حالت در صورت‌مسئله نبود ولی وجودش اجباری است: فروشگاهی که
     * «مدیریت موجودی» را روشن نکرده هیچ عددی ندارد، پس نه در «تعداد ≥ ۱»
     * می‌گنجد و نه ناموجود است. بدون این حالت، آن محصول‌ها هیچ وضعیتی
     * نمی‌گرفتند.
     */
    public const AVAILABLE = 'available';

    /** در پیش‌خرید / قابل سفارش */
    public const BACKORDER = 'onbackorder';

    /** ناموجود */
    public const OUT_OF_STOCK = 'outofstock';

    /** ترتیب ثابت حالت‌ها، برای ساختن کنترل‌ها و تست */
    public const STATES = [
        self::IN_STOCK,
        self::LOW_STOCK,
        self::AVAILABLE,
        self::BACKORDER,
        self::OUT_OF_STOCK,
    ];

    /** سقف تعداد فرزندی که برای جمع‌زدن موجودی خوانده می‌شود */
    private const MAX_CHILDREN = 60;

    /** نگاشت حالت به واژگان schema.org */
    private const SCHEMA = [
        self::IN_STOCK     => 'https://schema.org/InStock',
        self::LOW_STOCK    => 'https://schema.org/LimitedAvailability',
        self::AVAILABLE    => 'https://schema.org/InStock',
        self::BACKORDER    => 'https://schema.org/BackOrder',
        self::OUT_OF_STOCK => 'https://schema.org/OutOfStock',
    ];

    /* =====================================================================
     * تشخیص
     * =================================================================== */

    /**
     * وضعیت موجودی.
     *
     * @param array $options {
     *     @type bool     $backorder     حالت پیش‌خرید شناخته شود؟
     *     @type bool     $lowstock      حالت «رو به اتمام» شناخته شود؟
     *     @type int|null $low_threshold آستانهٔ دلخواه؛ null یعنی از ووکامرس بخوان.
     *     @type bool     $aggregate     موجودی گزینه‌های محصول متغیر جمع زده شود؟
     * }
     * @return array{state:string,quantity:int|null,managed:bool,schema:string}
     */
    public static function state(\WC_Product $product, array $options = []): array {
        $options += [
            'backorder'     => true,
            'lowstock'      => false,
            'low_threshold' => null,
            'aggregate'     => false,
        ];

        $managed  = (bool) $product->managing_stock();
        $quantity = self::quantity($product, $managed, (bool) $options['aggregate']);
        $state    = self::resolve_state($product, $quantity, $options);

        return [
            'state'    => $state,
            'quantity' => $quantity,
            'managed'  => $managed,
            'schema'   => self::SCHEMA[$state] ?? self::SCHEMA[self::AVAILABLE],
        ];
    }

    private static function resolve_state(\WC_Product $product, ?int $quantity, array $options): string {
        $status = (string) $product->get_stock_status();

        /*
         * ناموجود بالاترین اولویت را دارد.
         *
         * هر دو شرط بررسی می‌شوند چون افزونه‌های موجودی گاهی یکی را دست
         * می‌زنند و دیگری را نه؛ اگر فقط به یکی تکیه کنیم، محصولی که واقعاً
         * قابل خرید نیست ممکن است «موجود» اعلام شود.
         */
        if ('outofstock' === $status || !$product->is_in_stock()) {
            return self::OUT_OF_STOCK;
        }

        if (self::is_backorder($product, $status)) {
            /*
             * وقتی کاربر این حالت را نمی‌خواهد، محصول به «موجود» برمی‌گردد
             * نه به «موجود در انبار»: تعدادش صفر یا منفی است و گفتنِ
             * «در انبار» دربارهٔ چیزی که در انبار نیست، دروغ است.
             */
            return $options['backorder'] ? self::BACKORDER : self::AVAILABLE;
        }

        if (null === $quantity) {
            return self::AVAILABLE;
        }

        // تعداد ثبت شده ولی صفر یا منفی، و پیش‌خریدی هم در کار نیست
        if ($quantity < 1) {
            return self::AVAILABLE;
        }

        if ($options['lowstock']) {
            $threshold = self::threshold($product, $options['low_threshold']);

            if ($threshold > 0 && $quantity <= $threshold) {
                return self::LOW_STOCK;
            }
        }

        return self::IN_STOCK;
    }

    /**
     * آیا محصول در حالت پیش‌خرید است؟
     *
     * دو مسیر متفاوت، چون دو سناریوی متفاوت‌اند:
     *   • مدیر دستی وضعیت را روی «در پیش‌خرید» گذاشته (بدون مدیریت موجودی).
     *   • موجودی مدیریت می‌شود، تعداد به صفر رسیده و پیش‌خرید مجاز است —
     *     اینجا وضعیت ذخیره‌شده ممکن است هنوز ‎instock‎ باشد.
     */
    private static function is_backorder(\WC_Product $product, string $status): bool {
        if ('onbackorder' === $status) {
            return true;
        }

        return method_exists($product, 'is_on_backorder') && $product->is_on_backorder();
    }

    /**
     * آستانهٔ «رو به اتمام».
     *
     * ترتیب: مقدار دلخواه کاربر ← آستانهٔ خودِ محصول ← تنظیم سراسری ووکامرس.
     */
    private static function threshold(\WC_Product $product, $custom): int {
        if (null !== $custom && '' !== $custom) {
            return (int) $custom;
        }

        if (method_exists($product, 'get_low_stock_amount')) {
            $own = $product->get_low_stock_amount();

            if ('' !== $own && null !== $own) {
                return (int) $own;
            }
        }

        if (function_exists('get_option')) {
            return (int) get_option('woocommerce_notify_low_stock_amount', 2);
        }

        return 0;
    }

    /* =====================================================================
     * تعداد
     * =================================================================== */

    /**
     * تعداد موجودی، یا null اگر شمرده نمی‌شود.
     *
     * ‎get_stock_quantity()‎ وقتی مدیریت موجودی خاموش است null می‌دهد — که
     * درست است، ولی برای محصول متغیری که موجودی را در سطح گزینه‌ها مدیریت
     * می‌کند هم null می‌دهد، در حالی که آنجا عددِ معناداری وجود دارد. گزینهٔ
     * «جمع‌زدن» همان را برمی‌دارد.
     */
    private static function quantity(\WC_Product $product, bool $managed, bool $aggregate): ?int {
        if ($managed) {
            $quantity = $product->get_stock_quantity();

            return null === $quantity ? null : (int) $quantity;
        }

        return $aggregate ? self::children_quantity($product) : null;
    }

    /**
     * جمع موجودی گزینه‌های یک محصول متغیر.
     *
     * پیش‌فرض خاموش است چون هر گزینه یک بار خواندن از دیتابیس است. روی
     * صفحهٔ یک محصول ناچیز است، ولی در یک فهرست با بیست کارت، بیست ضرب‌در
     * تعداد گزینه‌ها می‌شود — و آن دیگر ناچیز نیست.
     */
    private static function children_quantity(\WC_Product $product): ?int {
        if (!method_exists($product, 'get_children') || !function_exists('wc_get_product')) {
            return null;
        }

        $ids = $product->get_children();

        if (!$ids || count($ids) > self::MAX_CHILDREN) {
            return null;
        }

        $total = null;

        foreach ($ids as $id) {
            $child = wc_get_product($id);

            if (!$child instanceof \WC_Product || !$child->managing_stock()) {
                continue;
            }

            $quantity = $child->get_stock_quantity();

            if (null === $quantity) {
                continue;
            }

            $total = (int) $total + (int) $quantity;
        }

        return $total;
    }
}
