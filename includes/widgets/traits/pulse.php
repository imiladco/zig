<?php
namespace Zig3d_Widgets\Widgets\Traits;

use Elementor\Controls_Manager;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * تپشِ نشان.
 *
 * یک هالهٔ آرام که از نشان بیرون می‌زند و محو می‌شود. کارش جلب توجه به یک
 * آیتم است بدون آنکه چیزی در چیدمان تکان بخورد.
 *
 * چرا مشترک: دو ویجت (وضعیت موجودی و لیست عنوان‌ها) همین را می‌خواهند و
 * تنها فرقشان این است که «چه وقت» روشن شود. اگر هر کدام نسخهٔ خودش را
 * داشت، دو keyframes و دو قاعدهٔ prefers-reduced-motion می‌شد که با اولین
 * تغییر از هم جدا می‌افتادند.
 *
 * روشن‌کردنش با کلاس ‎zig-pulse‎ روی خودِ نشان است — نه روی ریشه — تا در
 * فهرست بشود فقط یک آیتم را تپنده کرد.
 */
trait Pulse {

    /**
     * کلاسی که شیت با آن تپش را روشن می‌کند.
     *
     * به‌جای ثابتِ trait (که تا پیش از PHP 8.2 خطای parse می‌دهد) از یک
     * متدِ استاتیک استفاده شده تا با پایین‌ترین نسخهٔ پشتیبانی‌شدهٔ پلاگین
     * (PHP 7.4) هم سازگار بماند.
     */
    private static function pulse_class(): string {
        return 'zig-pulse';
    }

    /**
     * کنترل‌های تنظیم تپش.
     *
     * @param string $selector  سلکتور نشان، نسبت به ‎{{WRAPPER}}‎.
     * @param array  $condition شرط نمایش این کنترل‌ها در پنل.
     */
    protected function add_pulse_controls(string $selector, array $condition = []): void {
        $target = '{{WRAPPER}} ' . $selector;

        $this->add_control(
            'pulse_duration',
            [
                'label'      => __('مدت هر تپش', 'zig3d-widgets'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['ms'],
                'range'      => ['ms' => ['min' => 400, 'max' => 5000, 'step' => 50]],
                'default'    => ['size' => 1800, 'unit' => 'ms'],
                'selectors'  => [$target => '--zig-pulse-duration: {{SIZE}}ms;'],
                'condition'  => $condition,
            ]
        );

        $this->add_control(
            'pulse_scale',
            [
                'label'       => __('گسترهٔ هاله', 'zig3d-widgets'),
                'type'        => Controls_Manager::SLIDER,
                'range'       => ['px' => ['min' => 1.2, 'max' => 5, 'step' => 0.1]],
                'default'     => ['size' => 2.6],
                'description' => __('چند برابر اندازهٔ نشان باز شود.', 'zig3d-widgets'),
                'selectors'   => [$target => '--zig-pulse-scale: {{SIZE}};'],
                'condition'   => $condition,
            ]
        );

        $this->add_control(
            'pulse_opacity',
            [
                'label'     => __('شدت هاله', 'zig3d-widgets'),
                'type'      => Controls_Manager::SLIDER,
                'range'     => ['px' => ['min' => 0.1, 'max' => 1, 'step' => 0.05]],
                'default'   => ['size' => 0.55],
                'selectors' => [$target => '--zig-pulse-opacity: {{SIZE}};'],
                'condition' => $condition,
            ]
        );

        $this->add_control(
            'pulse_note',
            [
                'type'            => Controls_Manager::RAW_HTML,
                'raw'             => __('تپش یک حرکت تکرارشوندهٔ بی‌پایان است، پس با تنظیم سیستمیِ «حرکت کمتر» خودبه‌خود خاموش می‌شود. رنگ و متن سر جایشان می‌مانند، پس هیچ اطلاعاتی از دست نمی‌رود.', 'zig3d-widgets'),
                'content_classes' => 'elementor-descriptor',
                'condition'       => $condition,
            ]
        );
    }
}
