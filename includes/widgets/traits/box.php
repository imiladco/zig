<?php
namespace Zig3d_Widgets\Widgets\Traits;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Background;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * استایل‌های «جعبه» که بین ویجت‌ها مشترک‌اند: پس‌زمینه، حاشیه، گردی گوشه،
 * سایه، فاصلهٔ داخلی، جابه‌جایی در هاور و مدت گذار.
 *
 * هر سه ویجت این افزونه در نهایت یک جعبه‌اند که در حالت عادی و هاور فرق
 * می‌کند. بدون این trait، همان بیست کنترل سه بار نوشته می‌شد و — تجربهٔ
 * ثابت‌شده — هر بار یکی‌شان از قلم می‌افتاد و فقط در یک ویجت قابل تنظیم بود.
 */
trait Box {

    /**
     * تب‌های عادی/هاور برای یک جعبه.
     *
     * @param string $prefix      پیشوند نام کنترل‌ها (باید در ویجت یکتا باشد).
     * @param string $selector    سلکتور جعبه، نسبت به ‎{{WRAPPER}}‎.
     * @param string $hover_scope سلکتوری که هاور روی آن حالت هاور را فعال می‌کند؛
     *                            برای کارت یعنی «هاور روی هر جای کارت»، نه فقط خود جعبه.
     * @param string $padding_extra اعلان‌های اضافی که کنار خودِ ‎padding‎ نوشته
     *                            می‌شوند. برای جعبه‌ای لازم است که چیدمانش به
     *                            مقدارِ پدینگ وابسته است و باید همان عدد را در
     *                            یک متغیر هم داشته باشد — وگرنه با تغییرِ پدینگ
     *                            از تبِ استایل، آن چیدمان روی مقدارِ قدیمی
     *                            جا می‌ماند.
     */
    protected function add_box_style_tabs(
        string $prefix,
        string $selector,
        string $hover_scope,
        string $padding_extra = ''
    ): void {
        $box = '{{WRAPPER}} ' . $selector;

        /*
         * وقتی خودِ جعبه همان چیزی است که هاور رویش می‌افتد، سلکتور باید
         * ‎.x:hover‎ باشد نه ‎.x:hover .x‎ — دومی یعنی «یک .x تودرتو» که هیچ‌وقت
         * وجود ندارد و کل تب هاور بی‌اثر می‌شود.
         */
        $same  = ($selector === $hover_scope);
        $hover = $same
            ? $box . ':hover'
            : '{{WRAPPER}} ' . $hover_scope . ':hover ' . $selector;

        // فوکوسِ صفحه‌کلید عمداً همان ظاهرِ هاور را می‌گیرد. اگر فقط هاور
        // استایل داشته باشد، کاربری که با Tab حرکت می‌کند هیچ بازخوردی
        // نمی‌بیند — ایرادی که در تست دستی تقریباً هیچ‌وقت دیده نمی‌شود.
        $focus = $same
            ? $box . ':focus-within'
            : '{{WRAPPER}} ' . $hover_scope . ':focus-within ' . $selector;

        $this->start_controls_tabs($prefix . '_box_tabs');

        $this->start_controls_tab($prefix . '_box_normal', ['label' => __('عادی', 'zig3d-widgets')]);
        $this->add_box_state_controls($prefix, 'normal', $box);
        $this->end_controls_tab();

        $this->start_controls_tab($prefix . '_box_hover', ['label' => __('هاور', 'zig3d-widgets')]);
        $this->add_box_state_controls($prefix, 'hover', $hover . ', ' . $focus);

        $this->add_responsive_control(
            $prefix . '_box_translate_y',
            [
                'label'      => __('جابه‌جایی عمودی', 'zig3d-widgets'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range'      => ['px' => ['min' => -40, 'max' => 40]],
                'selectors'  => [$hover . ', ' . $focus => '--zig-box-translate-y: {{SIZE}}px;'],
            ]
        );

        $this->add_control(
            $prefix . '_box_scale',
            [
                'label'     => __('بزرگ‌نمایی', 'zig3d-widgets'),
                'type'      => Controls_Manager::SLIDER,
                'range'     => ['px' => ['min' => 0.8, 'max' => 1.2, 'step' => 0.005]],
                'selectors' => [$hover . ', ' . $focus => '--zig-box-scale: {{SIZE}};'],
            ]
        );

        $this->end_controls_tab();
        $this->end_controls_tabs();

        $this->add_responsive_control(
            $prefix . '_box_padding',
            [
                'label'      => __('فاصلهٔ داخلی', 'zig3d-widgets'),
                'type'       => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', 'rem', '%'],
                'separator'  => 'before',
                'selectors'  => [
                    $box => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};' . $padding_extra,
                ],
            ]
        );

        $this->add_control(
            $prefix . '_box_transition',
            [
                'label'      => __('مدت گذار', 'zig3d-widgets'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['ms'],
                'range'      => ['ms' => ['min' => 0, 'max' => 1500, 'step' => 10]],
                'default'    => ['size' => 250, 'unit' => 'ms'],
                'selectors'  => [$box => '--zig-transition: {{SIZE}}ms;'],
            ]
        );
    }

    /** کنترل‌هایی که در هر دو حالت یکسان‌اند */
    private function add_box_state_controls(string $prefix, string $state, string $selector): void {
        $this->add_group_control(
            Group_Control_Background::get_type(),
            [
                'name'     => $prefix . '_box_background_' . $state,
                'label'    => __('پس‌زمینه', 'zig3d-widgets'),
                'types'    => ['classic', 'gradient'],
                'selector' => $selector,
            ]
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name'     => $prefix . '_box_border_' . $state,
                'selector' => $selector,
            ]
        );

        $this->add_responsive_control(
            $prefix . '_box_radius_' . $state,
            [
                'label'      => __('گردی گوشه', 'zig3d-widgets'),
                'type'       => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'selectors'  => [$selector => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
            ]
        );

        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            [
                'name'     => $prefix . '_box_shadow_' . $state,
                'selector' => $selector,
            ]
        );
    }
}
