<?php
namespace Zig3d_Widgets\Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Background;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Text_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;
use Zig3d_Widgets\Markup;
use Zig3d_Widgets\Plugin;
use Zig3d_Widgets\Selector;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * دکمه.
 *
 *     div.zig-btn-wrap            فقط برای تراز و عرض
 *       a|button.zig-btn
 *         span.zig-btn__text
 *         span.zig-btn__icon      (تزئینی)
 *
 * سه تصمیمی که این ویجت را از یک «لینکِ استایل‌خورده» جدا می‌کند:
 *
 *   • انتخاب تگ درست. ‎<a>‎ جایی است که کاربر را به آدرسی می‌برد و ‎<button>‎
 *     جایی که کاری در همین صفحه انجام می‌شود. اشتباه گرفتنشان یعنی مرورگر
 *     رفتار درست (باز کردن در تب جدید، ارسال فرم، نقش در صفحه‌خوان) را
 *     نمی‌دهد. اینجا تگ به‌طور خودکار از وجود پیوند تصمیم گرفته می‌شود و در
 *     صورت نیاز دستی هم قابل تغییر است.
 *   • حالت فوکوس، هم‌ردیف حالت هاور تنظیم می‌شود. دکمه‌ای که فقط هاور دارد
 *     برای کاربر صفحه‌کلید عملاً بی‌بازخورد است.
 *   • حرکت‌ها به ‎prefers-reduced-motion‎ احترام می‌گذارند (در شیت اعمال شده).
 */
final class Button extends Widget_Base {

    use Traits\Link;

    /**
     * دامنهٔ «هاور یا فوکوس».
     *
     * یک بار تعریف می‌شود چون در چند کنترل تکرار می‌شود و هر بار نوشتنش
     * دوباره، یک فرصت دیگر برای جاانداختن ‎{{WRAPPER}}‎ روی یکی از دو بخش
     * است — همان اشتباهی که یک بار رنگ آیکون همهٔ دکمه‌های صفحه را به هم
     * ریخت.
     */
    private const HOVER = '{{WRAPPER}} .zig-btn:hover, {{WRAPPER}} .zig-btn:focus-visible';

    public function get_name(): string {
        return 'zig3d-button';
    }

    public function get_title(): string {
        return __('دکمه', 'zig3d-widgets');
    }

    public function get_icon(): string {
        return 'eicon-button';
    }

    public function get_categories(): array {
        return [Plugin::CATEGORY];
    }

    public function get_keywords(): array {
        return ['button', 'link', 'cta', 'دکمه', 'لینک', 'فراخوان'];
    }

    public function get_style_depends(): array {
        return ['zig3d-widgets'];
    }

    public function has_widget_inner_wrapper(): bool {
        return false;
    }

    protected function register_controls(): void {
        $this->register_content_section();
        $this->register_layout_section();
        $this->register_button_style_section();
        $this->register_text_style_section();
        $this->register_icon_style_section();
        $this->register_focus_section();
    }

    /* =====================================================================
     * محتوا
     * =================================================================== */

    private function register_content_section(): void {
        $this->start_controls_section(
            'content_section',
            ['label' => __('محتوا', 'zig3d-widgets')]
        );

        $this->add_control(
            'text',
            [
                'label'       => __('متن', 'zig3d-widgets'),
                'type'        => Controls_Manager::TEXT,
                'dynamic'     => ['active' => true],
                'default'     => __('درباره ZIG3D', 'zig3d-widgets'),
                'label_block' => true,
            ]
        );

        $this->add_link_control();

        $this->add_control(
            'tag',
            [
                'label'       => __('نوع عنصر', 'zig3d-widgets'),
                'type'        => Controls_Manager::SELECT,
                'default'     => 'auto',
                'options'     => [
                    'auto'   => __('خودکار', 'zig3d-widgets'),
                    'a'      => __('لینک (a)', 'zig3d-widgets'),
                    'button' => __('دکمه (button)', 'zig3d-widgets'),
                ],
                'description' => __('«خودکار» یعنی اگر پیوند داده باشید لینک و در غیر این صورت دکمه. معمولاً همین درست است.', 'zig3d-widgets'),
            ]
        );

        $this->add_control(
            'button_type',
            [
                'label'     => __('نوع دکمه', 'zig3d-widgets'),
                'type'      => Controls_Manager::SELECT,
                'default'   => 'button',
                'options'   => [
                    'button' => __('ساده', 'zig3d-widgets'),
                    'submit' => __('ارسال فرم', 'zig3d-widgets'),
                    'reset'  => __('بازنشانی فرم', 'zig3d-widgets'),
                ],
                'condition' => ['tag' => 'button'],
            ]
        );

        $this->add_control(
            'aria_label',
            [
                'label'       => __('برچسب برای صفحه‌خوان', 'zig3d-widgets'),
                'type'        => Controls_Manager::TEXT,
                'label_block' => true,
                'description' => __('اگر دکمه فقط آیکون دارد یا متنش خارج از متنِ اطراف مبهم است («اینجا»، «بیشتر») حتماً پرش کنید.', 'zig3d-widgets'),
            ]
        );

        $this->add_control(
            'icon_heading',
            [
                'label'     => __('آیکون', 'zig3d-widgets'),
                'type'      => Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        $this->add_control(
            'icon',
            [
                'label' => __('آیکون', 'zig3d-widgets'),
                'type'  => Controls_Manager::ICONS,
            ]
        );

        $this->add_control(
            'icon_position',
            [
                'label'     => __('جای آیکون', 'zig3d-widgets'),
                'type'      => Controls_Manager::CHOOSE,
                'default'   => 'end',
                'options'   => [
                    'start' => ['title' => __('ابتدای متن', 'zig3d-widgets'), 'icon' => 'eicon-h-align-right'],
                    'end'   => ['title' => __('انتهای متن', 'zig3d-widgets'), 'icon' => 'eicon-h-align-left'],
                ],
                'toggle'    => false,
                'condition' => ['icon[value]!' => ''],
            ]
        );

        $this->end_controls_section();
    }

    /* =====================================================================
     * چیدمان
     * =================================================================== */

    private function register_layout_section(): void {
        $this->start_controls_section(
            'layout_section',
            ['label' => __('چیدمان', 'zig3d-widgets')]
        );

        $this->add_control(
            'size',
            [
                'label'       => __('اندازه', 'zig3d-widgets'),
                'type'        => Controls_Manager::SELECT,
                'default'     => 'md',
                'options'     => [
                    'sm' => __('کوچک', 'zig3d-widgets'),
                    'md' => __('متوسط', 'zig3d-widgets'),
                    'lg' => __('بزرگ', 'zig3d-widgets'),
                    'xl' => __('خیلی بزرگ', 'zig3d-widgets'),
                ],
                'description' => __('نقطهٔ شروعِ فاصله و اندازهٔ متن. هر کنترلی که پایین‌تر تنظیم کنید بر آن می‌چربد.', 'zig3d-widgets'),
            ]
        );

        $this->add_responsive_control(
            'align',
            [
                'label'     => __('تراز', 'zig3d-widgets'),
                'type'      => Controls_Manager::CHOOSE,
                'options'   => [
                    'flex-start' => ['title' => __('ابتدا', 'zig3d-widgets'), 'icon' => 'eicon-text-align-right'],
                    'center'     => ['title' => __('وسط', 'zig3d-widgets'), 'icon' => 'eicon-text-align-center'],
                    'flex-end'   => ['title' => __('انتها', 'zig3d-widgets'), 'icon' => 'eicon-text-align-left'],
                    'stretch'    => ['title' => __('تمام‌عرض', 'zig3d-widgets'), 'icon' => 'eicon-text-align-justify'],
                ],
                'default'   => 'flex-start',
                'selectors' => ['{{WRAPPER}} .zig-btn-wrap' => 'align-items: {{VALUE}};'],
            ]
        );

        $this->add_responsive_control(
            'min_width',
            [
                'label'       => __('کمینهٔ عرض', 'zig3d-widgets'),
                'type'        => Controls_Manager::SLIDER,
                'size_units'  => ['px', '%', 'em'],
                'range'       => [
                    'px' => ['min' => 0, 'max' => 600],
                    '%'  => ['min' => 0, 'max' => 100],
                ],
                'description' => __('برای هم‌عرض کردن چند دکمه کنار هم.', 'zig3d-widgets'),
                'selectors'   => ['{{WRAPPER}} .zig-btn' => 'min-width: {{SIZE}}{{UNIT}};'],
            ]
        );

        $this->add_responsive_control(
            'icon_gap',
            [
                'label'      => __('فاصلهٔ آیکون تا متن', 'zig3d-widgets'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px', 'em', 'rem'],
                'range'      => ['px' => ['min' => 0, 'max' => 60]],
                'default'    => ['size' => 8, 'unit' => 'px'],
                'condition'  => ['icon[value]!' => ''],
                'selectors'  => ['{{WRAPPER}} .zig-btn' => 'gap: {{SIZE}}{{UNIT}};'],
            ]
        );

        $this->add_responsive_control(
            'padding',
            [
                'label'      => __('فاصلهٔ داخلی', 'zig3d-widgets'),
                'type'       => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', 'rem'],
                'separator'  => 'before',
                'selectors'  => ['{{WRAPPER}} .zig-btn' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
            ]
        );

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل دکمه
     * =================================================================== */

    private function register_button_style_section(): void {
        $this->start_controls_section(
            'button_style_section',
            [
                'label' => __('دکمه', 'zig3d-widgets'),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'hover_effect',
            [
                'label'   => __('افکت هاور', 'zig3d-widgets'),
                'type'    => Controls_Manager::SELECT,
                'default' => 'lift',
                'options' => [
                    ''       => __('بدون افکت', 'zig3d-widgets'),
                    'lift'   => __('بالا آمدن', 'zig3d-widgets'),
                    'sink'   => __('پایین رفتن', 'zig3d-widgets'),
                    'grow'   => __('بزرگ شدن', 'zig3d-widgets'),
                    'shrink' => __('کوچک شدن', 'zig3d-widgets'),
                    'sweep'  => __('عبور درخشش', 'zig3d-widgets'),
                ],
            ]
        );

        $this->add_control(
            'transition',
            [
                'label'      => __('مدت گذار', 'zig3d-widgets'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['ms'],
                'range'      => ['ms' => ['min' => 0, 'max' => 1500, 'step' => 10]],
                'default'    => ['size' => 220, 'unit' => 'ms'],
                'selectors'  => ['{{WRAPPER}} .zig-btn' => '--zig-transition: {{SIZE}}ms;'],
            ]
        );

        $this->start_controls_tabs('button_tabs');

        $this->start_controls_tab('button_tab_normal', ['label' => __('عادی', 'zig3d-widgets')]);
        $this->add_button_state_controls('normal', '{{WRAPPER}} .zig-btn');
        $this->end_controls_tab();

        /*
         * هاور و فوکوس یک سلکتور مشترک دارند.
         *
         * ‎:focus-visible‎ و نه ‎:focus‎: مرورگر فقط وقتی آن را می‌دهد که فوکوس
         * از صفحه‌کلید آمده باشد. با ‎:focus‎ ساده، هر کلیک ماوس هم حالت فوکوس
         * را می‌چسباند و دکمه بعد از کلیک «گیر کرده» به نظر می‌رسد.
         */
        $this->start_controls_tab('button_tab_hover', ['label' => __('هاور و فوکوس', 'zig3d-widgets')]);
        $this->add_button_state_controls('hover', self::HOVER);
        $this->end_controls_tab();

        $this->start_controls_tab('button_tab_active', ['label' => __('فشرده', 'zig3d-widgets')]);
        $this->add_button_state_controls('active', '{{WRAPPER}} .zig-btn:active');

        $this->add_control(
            'active_scale',
            [
                'label'       => __('بزرگ‌نمایی هنگام فشردن', 'zig3d-widgets'),
                'type'        => Controls_Manager::SLIDER,
                'range'       => ['px' => ['min' => 0.8, 'max' => 1.1, 'step' => 0.005]],
                'default'     => ['size' => 0.97],
                'description' => __('بازخورد لمسیِ کوچک هنگام کلیک. مقدار ۱ یعنی بدون تغییر.', 'zig3d-widgets'),
                'selectors'   => ['{{WRAPPER}} .zig-btn:active' => '--zig-btn-press: {{SIZE}};'],
            ]
        );
        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->end_controls_section();
    }

    /** کنترل‌های مشترک هر سه حالت دکمه */
    private function add_button_state_controls(string $state, string $selector): void {
        $this->add_group_control(
            Group_Control_Background::get_type(),
            [
                'name'     => 'background_' . $state,
                'label'    => __('پس‌زمینه', 'zig3d-widgets'),
                'types'    => ['classic', 'gradient'],
                'selector' => $selector,
            ]
        );

        $this->add_control(
            'text_color_' . $state,
            [
                'label'     => __('رنگ متن و آیکون', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    // رنگ روی خودِ دکمه می‌نشیند تا متن و فونت‌آیکون هر دو
                    // ارث ببرند، و صریح روی SVG چون آیکون‌های صادرشده رنگ را
                    // معمولاً روی path می‌نویسند
                    $selector => 'color: {{VALUE}};',
                    Selector::descend($selector, '.zig-btn__icon svg, .zig-btn__icon svg *') => 'fill: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name'     => 'border_' . $state,
                'selector' => $selector,
            ]
        );

        $this->add_responsive_control(
            'radius_' . $state,
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
                'name'     => 'shadow_' . $state,
                'selector' => $selector,
            ]
        );
    }

    /* =====================================================================
     * استایل متن
     * =================================================================== */

    private function register_text_style_section(): void {
        $this->start_controls_section(
            'text_style_section',
            [
                'label' => __('متن', 'zig3d-widgets'),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'     => 'typography',
                'selector' => '{{WRAPPER}} .zig-btn',
            ]
        );

        $this->add_group_control(
            Group_Control_Text_Shadow::get_type(),
            [
                'name'     => 'text_shadow',
                'selector' => '{{WRAPPER}} .zig-btn__text',
            ]
        );

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل آیکون
     * =================================================================== */

    private function register_icon_style_section(): void {
        $this->start_controls_section(
            'icon_style_section',
            [
                'label'     => __('آیکون', 'zig3d-widgets'),
                'tab'       => Controls_Manager::TAB_STYLE,
                'condition' => ['icon[value]!' => ''],
            ]
        );

        $this->add_responsive_control(
            'icon_size',
            [
                'label'      => __('اندازه', 'zig3d-widgets'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px', 'em', 'rem'],
                'range'      => ['px' => ['min' => 6, 'max' => 80]],
                'selectors'  => ['{{WRAPPER}} .zig-btn__icon' => '--zig-icon-size: {{SIZE}}{{UNIT}};'],
            ]
        );

        $this->add_control(
            'icon_rotate',
            [
                'label'      => __('چرخش', 'zig3d-widgets'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['deg'],
                'range'      => ['deg' => ['min' => -180, 'max' => 180]],
                'selectors'  => ['{{WRAPPER}} .zig-btn__icon' => '--zig-icon-rotate: {{SIZE}}deg;'],
            ]
        );

        $this->add_control(
            'icon_slide',
            [
                'label'       => __('حرکت آیکون در هاور', 'zig3d-widgets'),
                'type'        => Controls_Manager::SLIDER,
                'size_units'  => ['px'],
                'range'       => ['px' => ['min' => -30, 'max' => 30]],
                'default'     => ['size' => 4, 'unit' => 'px'],
                'description' => __('عدد مثبت یعنی حرکت به سمت انتهای خط؛ در قالب راست‌به‌چپ خودش برعکس می‌شود.', 'zig3d-widgets'),
                'selectors'   => ['{{WRAPPER}} .zig-btn' => '--zig-btn-slide: {{SIZE}}px;'],
            ]
        );

        $this->add_control(
            'icon_color_hover',
            [
                'label'     => __('رنگ آیکون در هاور', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'separator' => 'before',
                'selectors' => [
                    Selector::descend(self::HOVER, '.zig-btn__icon')       => 'color: {{VALUE}};',
                    Selector::descend(self::HOVER, '.zig-btn__icon svg *') => 'fill: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_section();
    }

    /* =====================================================================
     * حلقهٔ فوکوس
     * =================================================================== */

    private function register_focus_section(): void {
        $this->start_controls_section(
            'focus_section',
            [
                'label' => __('حلقهٔ فوکوس', 'zig3d-widgets'),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'focus_note',
            [
                'type'            => Controls_Manager::RAW_HTML,
                'raw'             => __('حلقهٔ فوکوس فقط وقتی دیده می‌شود که کاربر با صفحه‌کلید (کلید Tab) روی دکمه برود، نه با کلیک ماوس. حذف کاملش دسترسی‌پذیری را می‌شکند؛ به‌جای حذف، رنگش را با طرح هماهنگ کنید.', 'zig3d-widgets'),
                'content_classes' => 'elementor-descriptor',
            ]
        );

        $this->add_control(
            'focus_color',
            [
                'label'     => __('رنگ', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => ['{{WRAPPER}} .zig-btn' => '--zig-focus-color: {{VALUE}};'],
            ]
        );

        $this->add_control(
            'focus_width',
            [
                'label'      => __('ضخامت', 'zig3d-widgets'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range'      => ['px' => ['min' => 0, 'max' => 10]],
                'selectors'  => ['{{WRAPPER}} .zig-btn' => '--zig-focus-width: {{SIZE}}px;'],
            ]
        );

        $this->add_control(
            'focus_offset',
            [
                'label'      => __('فاصله از لبه', 'zig3d-widgets'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range'      => ['px' => ['min' => 0, 'max' => 12]],
                'selectors'  => ['{{WRAPPER}} .zig-btn' => '--zig-focus-offset: {{SIZE}}px;'],
            ]
        );

        $this->end_controls_section();
    }

    /* =====================================================================
     * رندر
     * =================================================================== */

    protected function render(): void {
        $settings = $this->get_settings_for_display();

        $text      = (string) ($settings['text'] ?? '');
        $has_text  = Markup::filled($text);
        $icon      = $settings['icon'] ?? [];
        $has_icon  = !empty($icon['value']);
        $aria      = trim((string) ($settings['aria_label'] ?? ''));

        if (!$has_text && !$has_icon) {
            return;
        }

        $tag = $this->resolve_tag($settings);

        $this->add_render_attribute('button', 'class', [
            'zig-btn',
            'zig-btn--' . sanitize_html_class((string) ($settings['size'] ?? 'md'), 'md'),
        ]);

        if ($has_icon) {
            $position = 'start' === ($settings['icon_position'] ?? 'end') ? 'start' : 'end';
            $this->add_render_attribute('button', 'class', 'zig-btn--icon-' . $position);
        }

        $effect = (string) ($settings['hover_effect'] ?? '');

        if ('' !== $effect) {
            $this->add_render_attribute('button', 'class', 'zig-btn--' . sanitize_html_class($effect));
        }

        if ('a' === $tag) {
            $this->add_link_attributes('button', $settings['link']);
        } else {
            $this->add_render_attribute('button', 'type', $this->resolve_button_type($settings));
        }

        /*
         * برچسب صریح، یا اجباراً وقتی دکمه فقط آیکون دارد.
         *
         * دکمهٔ بدون نامِ قابل‌خواندن برای صفحه‌خوان فقط «دکمه» اعلام می‌شود.
         * پس اگر متنی در کار نیست و کاربر هم برچسبی نداده، به‌جای رها کردنش
         * از عنوان ویجت استفاده می‌کنیم — نه ایده‌آل، ولی از هیچ بهتر است و
         * توضیح کنترل هم کاربر را به سمت مقدار درست هدایت می‌کند.
         */
        if ('' !== $aria) {
            $this->add_render_attribute('button', 'aria-label', $aria);
        } elseif (!$has_text) {
            $this->add_render_attribute('button', 'aria-label', $this->get_title());
        }

        $this->add_inline_editing_attributes('text', 'none');

        $icon_html = '';

        if ($has_icon) {
            ob_start();
            \Elementor\Icons_Manager::render_icon($icon, ['aria-hidden' => 'true']);
            $icon_html = '<span class="zig-btn__icon" aria-hidden="true">' . ob_get_clean() . '</span>';
        }
        ?>
        <div class="zig-btn-wrap">
            <<?php echo $tag; // phpcs:ignore WordPress.Security.EscapeOutput -- فقط 'a' یا 'button' ?> <?php $this->print_render_attribute_string('button'); ?>>
                <?php
                if ('start' === ($settings['icon_position'] ?? 'end')) {
                    echo $icon_html; // phpcs:ignore WordPress.Security.EscapeOutput -- خروجی Icons_Manager
                }
                ?>
                <?php if ($has_text) : ?>
                    <span class="zig-btn__text" <?php $this->print_render_attribute_string('text'); ?>><?php
                        echo Markup::text($text); // phpcs:ignore WordPress.Security.EscapeOutput -- wp_kses با فهرست سفید درون‌خطی
                    ?></span>
                <?php endif; ?>
                <?php
                if ('start' !== ($settings['icon_position'] ?? 'end')) {
                    echo $icon_html; // phpcs:ignore WordPress.Security.EscapeOutput -- خروجی Icons_Manager
                }
                ?>
            </<?php echo $tag; // phpcs:ignore WordPress.Security.EscapeOutput -- فقط 'a' یا 'button' ?>>
        </div>
        <?php
    }

    /**
     * تگ نهایی.
     *
     * ‎<a>‎ بدون href نه فوکوس‌پذیر است و نه برای صفحه‌خوان لینک به حساب
     * می‌آید. پس حتی اگر کاربر صریحاً «لینک» را انتخاب کرده باشد، بدون آدرس
     * به ‎<button>‎ برمی‌گردیم — یک دکمهٔ بی‌کار، ولی دست‌کم عنصری معتبر.
     */
    private function resolve_tag(array $settings): string {
        $choice = (string) ($settings['tag'] ?? 'auto');

        if ('button' === $choice) {
            return 'button';
        }

        return $this->has_link($settings) ? 'a' : 'button';
    }

    private function resolve_button_type(array $settings): string {
        $type = (string) ($settings['button_type'] ?? 'button');

        return in_array($type, ['button', 'submit', 'reset'], true) ? $type : 'button';
    }
}
