<?php
namespace Zig3d_Widgets;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * زمانِ به‌روزرسانیِ قیمت، از افزونهٔ نرخِ ارز («نوسان»، mns-woocommerce-rate-based-products).
 *
 * چرا این کلاس وجود دارد: قیمتِ محصولاتِ این فروشگاه با نرخِ دلار عوض
 * می‌شود، پس عددِ قیمت به‌تنهایی گمراه‌کننده است — مشتری باید بداند این
 * عدد مالِ کِی است. آن افزونه خودش این زمان را نگه می‌دارد؛ اینجا فقط
 * خوانده می‌شود.
 *
 * وابستگی نرم است، مثلِ همهٔ ادغام‌هایِ این افزونه: هسته‌شان
 * (‎includes/Core.php‎) با ionCube رمزنگاری شده و از بیرون قابلِ خواندن
 * نیست، پس API‌یِ عمومی از رویِ نقطه‌هایِ فراخوانیِ ساده‌متنِ خودشان
 * (شورت‌کدها، ویجت‌هایِ المنتور، متاباکس‌ها) استخراج شد، نه از رویِ
 * سورسِ اصلی. اگر افزونه نصب نباشد یا غیرفعال شود، هر متد اینجا رشتهٔ
 * خالی می‌دهد — نه خطا.
 *
 * دانه‌بندی: هر واریانت جدا نرخ‌محور است یا نه (متاباکسِ محصول این
 * فیلدها را به‌ازایِ هر ردیفِ واریانت هم رندر می‌کند)، پس این کلاس با
 * شناسهٔ واریانت هم درست کار می‌کند — نه فقط شناسهٔ محصولِ والد.
 */
final class Rate_Price {

    /**
     * زمانِ آخرین به‌روزرسانیِ قیمتِ یک محصول یا واریانت، به‌صورتِ Unix
     * timestamp؛ صفر یعنی نرخ‌محور نیست یا افزونه در دسترس نیست.
     */
    public static function updated_at(int $product_id): int {
        if ($product_id <= 0 || !self::available()) {
            return 0;
        }

        $product = new \MNS\Navasan\Includes\Product($product_id);

        if (!$product->get_product()) {
            return 0;
        }

        /*
         * ‎get_active()‎ به قراردادِ چک‌باکسِ ووکامرس برمی‌گردد
         * (‎woocommerce_wp_checkbox‎) — یعنی ‎'yes'‎ یا خالی، نه بولی. هر
         * مقدارِ دیگری هم به «نرخ‌محور نیست» بسته می‌شود؛ اینجا
         * محافظه‌کاری امن‌تر از حدسِ خوش‌بینانه است.
         */
        if ('yes' !== (string) $product->get_active()) {
            return 0;
        }

        $currency_id = (int) $product->get_currency_id();

        if ($currency_id <= 0) {
            return 0;
        }

        $currency = mns_navasan()->get_currency($currency_id);

        if (!$currency || !method_exists($currency, 'get_update_time')) {
            return 0;
        }

        return (int) $currency->get_update_time();
    }

    /**
     * همان زمان، آماده برایِ نمایش — با فرمتِ تاریخ/ساعتِ خودِ سایت.
     *
     * همان الگویی که بقیهٔ افزونه برایِ تاریخ به کار می‌برد
     * (‎software-info-table.php‎، ‎documents.php‎): ‎wp_date()‎ با فرمتِ
     * تنظیماتِ وردپرس، نه یک فرمتِ ثابت اینجا. اگر سایت افزونهٔ تقویمِ
     * جلالی دارد که رویِ ‎wp_date‎/‎date_i18n‎ فیلتر می‌گذارد (که رسمِ
     * رایجِ سایت‌هایِ فارسی است)، همان تبدیل خودش اینجا هم اثر می‌کند —
     * این کلاس محاسبهٔ تقویم را دوباره نمی‌سازد.
     */
    public static function display(int $product_id): string {
        return self::format(self::updated_at($product_id));
    }

    /**
     * فرمت‌کردنِ یک Unix timestampِ از پیش خوانده‌شده.
     *
     * وقتی چند واریانت با هم بارگذاری می‌شوند (‎Configurator::variations()‎)،
     * هر کدام زمانِ خودش را با ‎updated_at()‎ یک‌بار از نوسان می‌خواند و نگه
     * می‌دارد؛ این متد همان قالب‌بندیِ ‎display()‎ را بدونِ خواندنِ دوبارهٔ
     * دیتابیس روی آن عدد اجرا می‌کند.
     */
    public static function format(int $time): string {
        if ($time <= 0) {
            return '';
        }

        $date = wp_date(get_option('date_format') ?: 'j F Y', $time);
        $time_str = wp_date(get_option('time_format') ?: 'H:i', $time);

        return false === $date || false === $time_str ? '' : trim($date . ' ، ' . $time_str);
    }

    private static function available(): bool {
        return class_exists('\MNS\Navasan\Includes\Product') && function_exists('mns_navasan');
    }
}
