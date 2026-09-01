<?php
namespace Zig3d_Widgets\Widgets\Traits;

use Elementor\Controls_Manager;
use Zig3d_Widgets\Consultation_Endpoint;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * دکمه‌ای که به‌جایِ پیوند، فرمِ کوتاهِ درخواستِ مشاوره را باز می‌کند.
 *
 * چرا مشترک: هم ویجتِ عمومیِ «دکمه» و هم دکمهٔ فرعیِ «کانفیگ محصول» باید
 * بتوانند همین رفتار را بگیرند — دقیقاً همان درخواستی که کاربر داد
 * («نهایت برای ویجت دکمه خود این افزونه هم می‌خوام استفاده‌اش کنم»).
 * کنترل، تشخیصِ فعال‌بودن، و ویژگی‌هایِ رندر یک‌جا اینجا نوشته می‌شوند تا
 * دو ویجت یک منطق را دو بار پیاده نکنند.
 *
 * مودالِ واقعی و منطقِ ارسال کاملاً سمتِ کلاینت‌اند (‎zig3d-consultation.js‎)؛
 * این طرف فقط چهار چیز رویِ دکمه می‌گذارد: نشانه‌ای که جاوااسکریپت با آن
 * دکمه را پیدا کند، آدرس/نانسِ آژاکس، و — اگر در دسترس بود — شناسه/نامِ
 * محصول برایِ ثبت در ردیفِ دیتابیس.
 */
trait Consultation_Trigger {

    /**
     * کنترلِ روشن/خاموش.
     *
     * پیش‌فرض «خاموش» است — این ویجت‌ها از قبل روی سایت نصب‌اند و پیوندِ
     * فعلیِ دکمه نباید با ارتقاءِ افزونه بی‌صدا عوض شود؛ مدیر باید صریحاً
     * روشنش کند.
     *
     * @param string $prefix پیشوندِ نامِ کنترل، برایِ ویجتی که چند دکمه دارد
     *                       (مثلاً ‎'primary_'‎ در کانفیگ محصول).
     */
    protected function add_consultation_controls(string $prefix = ''): void {
        $this->add_control(
            $prefix . 'consultation_on',
            [
                'label'        => __('باز کردنِ فرمِ مشاوره', 'zig3d-widgets'),
                'type'         => Controls_Manager::SWITCHER,
                'default'      => 'no',
                'label_on'     => __('روشن', 'zig3d-widgets'),
                'label_off'    => __('خاموش', 'zig3d-widgets'),
                'return_value' => 'yes',
                'description'  => __('روشن باشد، کلیک به‌جایِ پیوندِ بالا یک فرمِ کوتاهِ درخواستِ مشاوره (نام، شماره، توضیحات) باز می‌کند و پیوند نادیده گرفته می‌شود.', 'zig3d-widgets'),
            ]
        );
    }

    /** آیا این دکمه، به‌جایِ پیوند، فرمِ مشاوره باز می‌کند؟ */
    protected function consultation_active(array $settings, string $prefix = ''): bool {
        return 'yes' === ($settings[$prefix . 'consultation_on'] ?? 'no');
    }

    /**
     * ویژگی‌هایِ رندرِ لازم برایِ کلیکِ بازکنندهٔ فرم.
     *
     * تگ همیشه ‎button‎ است، نه ‎a‎: این یک کنشِ همین صفحه است، نه ناوبری
     * به آدرسی دیگر — همان تمایزی که خودِ ویجتِ «دکمه» در داک‌بلاکِ کلاسش
     * جدی گرفته.
     */
    protected function apply_consultation_attributes(string $render_key, ?\WC_Product $product = null): void {
        $this->add_render_attribute($render_key, [
            'data-zig-consultation'          => '1',
            'data-zig-consultation-endpoint' => Consultation_Endpoint::url(),
            'data-zig-consultation-nonce'    => Consultation_Endpoint::nonce(),
        ]);

        if (null !== $product) {
            $this->add_render_attribute($render_key, [
                'data-zig-consultation-product-id'   => (string) $product->get_id(),
                'data-zig-consultation-product-name' => $product->get_name(),
            ]);
        }
    }
}
