<?php
namespace Zig3d_Widgets\Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Background;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Text_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;
use Zig3d_Widgets\Plugin;
use Zig3d_Widgets\Price;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * نمایش قیمت محصول ووکامرس.
 *
 *     div.zig-price
 *       span.zig-price__prefix        «شروع از»
 *       span.zig-price__badge         «۲۰٪ تخفیف»
 *       del.zig-price__old            قیمت پیشین (فقط در حالت تخفیف)
 *       ins.zig-price__now            قیمت فعلی
 *         bdi.zig-price__value        عدد + واحد
 *         span.zig-price__sep         «تا» (حالت بازه)
 *         bdi.zig-price__value        بیشترین قیمت (حالت بازه)
 *       span.zig-price__suffix        یادداشت مالیات
 *
 * منطق محاسبه عمداً اینجا نیست؛ در ‎Zig3d_Widgets\Price‎ است تا بدون بالا
 * آوردن المنتور قابل تست باشد. این کلاس فقط کنترل‌ها و مارک‌آپ است.
 */
final class Product_Price extends Widget_Base {

    public function get_name(): string {
        return 'zig3d-product-price';
    }

    public function get_title(): string {
        return __('قیمت محصول', 'zig3d-widgets');
    }

    public function get_icon(): string {
        return 'eicon-product-price';
    }

    public function get_categories(): array {
        return [Plugin::CATEGORY];
    }

    public function get_keywords(): array {
        return ['price', 'woocommerce', 'product', 'sale', 'discount', 'قیمت', 'تخفیف', 'محصول', 'ووکامرس'];
    }

    public function get_style_depends(): array {
        return ['zig3d-widgets'];
    }

    public function has_widget_inner_wrapper(): bool {
        return false;
    }

    protected function register_controls(): void {
        $this->register_product_section();
        $this->register_display_section();
        $this->register_states_section();
        $this->register_layout_section();
        $this->register_now_style_section();
        $this->register_old_style_section();
        $this->register_badge_style_section();
        $this->register_extras_style_section();
    }

    /* =====================================================================
     * محتوا: محصول
     * =================================================================== */

    private function register_product_section(): void {
        $this->start_controls_section(
            'product_section',
            ['label' => __('محصول', 'zig3d-widgets')]
        );

        $this->add_control(
            'product_id',
            [
                'label'       => __('شناسهٔ محصول', 'zig3d-widgets'),
                'type'        => Controls_Manager::NUMBER,
                'min'         => 0,
                'dynamic'     => ['active' => true],
                'description' => __('خالی بگذارید تا محصول جاری استفاده شود — چه در صفحهٔ محصول، چه داخل حلقهٔ فروشگاه یا قالب حلقهٔ المنتور.', 'zig3d-widgets'),
            ]
        );

        $this->add_control(
            'variable_heading',
            [
                'label'     => __('محصول متغیر و گروهی', 'zig3d-widgets'),
                'type'      => Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        $this->add_control(
            'variable_mode',
            [
                'label'   => __('نحوهٔ نمایش', 'zig3d-widgets'),
                'type'    => Controls_Manager::SELECT,
                'default' => 'min',
                'options' => [
                    'min'   => __('کمترین قیمت («شروع از …»)', 'zig3d-widgets'),
                    'range' => __('بازهٔ کامل (کمترین تا بیشترین)', 'zig3d-widgets'),
                ],
            ]
        );

        $this->add_control(
            'prefix_text',
            [
                'label'     => __('پیشوند', 'zig3d-widgets'),
                'type'      => Controls_Manager::TEXT,
                'default'   => __('شروع از', 'zig3d-widgets'),
                'dynamic'   => ['active' => true],
                'condition' => ['variable_mode' => 'min'],
            ]
        );

        $this->add_control(
            'range_separator',
            [
                'label'     => __('جداکنندهٔ بازه', 'zig3d-widgets'),
                'type'      => Controls_Manager::TEXT,
                'default'   => __('تا', 'zig3d-widgets'),
                'condition' => ['variable_mode' => 'range'],
            ]
        );

        $this->add_control(
            'range_note',
            [
                'type'            => Controls_Manager::RAW_HTML,
                'raw'             => __('در حالت بازه، بج تخفیف و قیمت پیشین نمایش داده نمی‌شوند: یک درصد تخفیفِ واحد برای بازه‌ای از قیمت‌ها معنا ندارد و عددی می‌سازد که هیچ گزینه‌ای واقعاً ندارد.', 'zig3d-widgets'),
                'content_classes' => 'elementor-descriptor',
                'condition'       => ['variable_mode' => 'range'],
            ]
        );

        $this->end_controls_section();
    }

    /* =====================================================================
     * محتوا: اجزای نمایش
     * =================================================================== */

    private function register_display_section(): void {
        $this->start_controls_section(
            'display_section',
            ['label' => __('نمایش', 'zig3d-widgets')]
        );

        $this->add_control(
            'show_old',
            [
                'label'        => __('قیمت پیشین', 'zig3d-widgets'),
                'type'         => Controls_Manager::SWITCHER,
                'default'      => 'yes',
                'return_value' => 'yes',
            ]
        );

        $this->add_control(
            'show_badge',
            [
                'label'        => __('بج تخفیف', 'zig3d-widgets'),
                'type'         => Controls_Manager::SWITCHER,
                'default'      => 'yes',
                'return_value' => 'yes',
            ]
        );

        $this->add_control(
            'badge_mode',
            [
                'label'     => __('محتوای بج', 'zig3d-widgets'),
                'type'      => Controls_Manager::SELECT,
                'default'   => 'percent',
                'options'   => [
                    'percent' => __('درصد تخفیف', 'zig3d-widgets'),
                    'amount'  => __('مبلغ صرفه‌جویی', 'zig3d-widgets'),
                ],
                'condition' => ['show_badge' => 'yes'],
            ]
        );

        $this->add_control(
            'badge_template',
            [
                'label'       => __('قالب متن بج', 'zig3d-widgets'),
                'type'        => Controls_Manager::TEXT,
                'default'     => '{value}٪',
                'description' => __('‏{value} جای عدد می‌نشیند. مثال: «{value}٪ تخفیف» یا «‎{value} تومان کمتر».', 'zig3d-widgets'),
                'condition'   => ['show_badge' => 'yes'],
            ]
        );

        $this->add_control(
            'currency_heading',
            [
                'label'     => __('واحد پول', 'zig3d-widgets'),
                'type'      => Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        $this->add_control(
            'currency_text',
            [
                'label'       => __('متن واحد پول', 'zig3d-widgets'),
                'type'        => Controls_Manager::TEXT,
                'placeholder' => Price::currency(),
                'description' => __('خالی = نماد پیش‌فرض ووکامرس.', 'zig3d-widgets'),
            ]
        );

        $this->add_control(
            'currency_on_now',
            [
                'label'        => __('روی قیمت فعلی', 'zig3d-widgets'),
                'type'         => Controls_Manager::SWITCHER,
                'default'      => 'yes',
                'return_value' => 'yes',
            ]
        );

        $this->add_control(
            'currency_on_old',
            [
                'label'        => __('روی قیمت پیشین', 'zig3d-widgets'),
                'type'         => Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'condition'    => ['show_old' => 'yes'],
            ]
        );

        $this->add_control(
            'persian_digits',
            [
                'label'        => __('ارقام فارسی', 'zig3d-widgets'),
                'type'         => Controls_Manager::SWITCHER,
                'default'      => 'yes',
                'return_value' => 'yes',
                'separator'    => 'before',
            ]
        );

        $this->add_control(
            'show_tax_suffix',
            [
                'label'        => __('یادداشت مالیات ووکامرس', 'zig3d-widgets'),
                'type'         => Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'description'  => __('همان متنی که ووکامرس در تنظیمات مالیات برای نمایش کنار قیمت تعریف کرده (مثلاً «شامل مالیات»).', 'zig3d-widgets'),
            ]
        );

        $this->add_control(
            'schema',
            [
                'label'        => __('نشانه‌گذاری ساختاریافته', 'zig3d-widgets'),
                'type'         => Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'separator'    => 'before',
                'description'  => __('ویژگی‌های itemprop برای موتور جست‌وجو. اگر افزونهٔ سئوی شما خودش قیمت را در JSON-LD می‌فرستد، خاموش بگذارید تا داده تکراری نشود.', 'zig3d-widgets'),
            ]
        );

        $this->end_controls_section();
    }

    /* =====================================================================
     * محتوا: حالت‌های خاص
     * =================================================================== */

    private function register_states_section(): void {
        $this->start_controls_section(
            'states_section',
            ['label' => __('حالت‌های خاص', 'zig3d-widgets')]
        );

        $this->add_control(
            'free_text',
            [
                'label'       => __('متن قیمت صفر', 'zig3d-widgets'),
                'type'        => Controls_Manager::TEXT,
                'default'     => __('رایگان', 'zig3d-widgets'),
                'description' => __('خالی بگذارید تا عددِ صفر نمایش داده شود.', 'zig3d-widgets'),
            ]
        );

        /*
         * «قیمت ندارد» با «قیمتش صفر است» یکی نیست و رفتارشان هم نباید یکی
         * باشد: اولی یعنی هنوز قیمت‌گذاری نشده، دومی یعنی رایگان.
         */
        $this->add_control(
            'empty_behavior',
            [
                'label'     => __('وقتی محصول قیمت ندارد', 'zig3d-widgets'),
                'type'      => Controls_Manager::SELECT,
                'default'   => 'hide',
                'options'   => [
                    'hide' => __('چیزی نمایش نده', 'zig3d-widgets'),
                    'text' => __('متن جایگزین نشان بده', 'zig3d-widgets'),
                ],
            ]
        );

        $this->add_control(
            'empty_text',
            [
                'label'     => __('متن جایگزین', 'zig3d-widgets'),
                'type'      => Controls_Manager::TEXT,
                'default'   => __('تماس بگیرید', 'zig3d-widgets'),
                'dynamic'   => ['active' => true],
                'condition' => ['empty_behavior' => 'text'],
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
            [
                'label' => __('چیدمان', 'zig3d-widgets'),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_responsive_control(
            'direction',
            [
                'label'     => __('جهت', 'zig3d-widgets'),
                'type'      => Controls_Manager::CHOOSE,
                'default'   => 'row',
                'options'   => [
                    'row'            => ['title' => __('افقی', 'zig3d-widgets'), 'icon' => 'eicon-arrow-left'],
                    'row-reverse'    => ['title' => __('افقیِ معکوس', 'zig3d-widgets'), 'icon' => 'eicon-arrow-right'],
                    'column'         => ['title' => __('عمودی', 'zig3d-widgets'), 'icon' => 'eicon-arrow-down'],
                    'column-reverse' => ['title' => __('عمودیِ معکوس', 'zig3d-widgets'), 'icon' => 'eicon-arrow-up'],
                ],
                'toggle'    => false,
                'selectors' => ['{{WRAPPER}} .zig-price' => 'flex-direction: {{VALUE}};'],
            ]
        );

        $this->add_responsive_control(
            'justify',
            [
                'label'     => __('توزیع', 'zig3d-widgets'),
                'type'      => Controls_Manager::CHOOSE,
                'options'   => [
                    'flex-start'    => ['title' => __('ابتدا', 'zig3d-widgets'), 'icon' => 'eicon-flex eicon-justify-start-h'],
                    'center'        => ['title' => __('وسط', 'zig3d-widgets'), 'icon' => 'eicon-flex eicon-justify-center-h'],
                    'flex-end'      => ['title' => __('انتها', 'zig3d-widgets'), 'icon' => 'eicon-flex eicon-justify-end-h'],
                    'space-between' => ['title' => __('فاصلهٔ بین', 'zig3d-widgets'), 'icon' => 'eicon-flex eicon-justify-space-between-h'],
                ],
                'selectors' => ['{{WRAPPER}} .zig-price' => 'justify-content: {{VALUE}};'],
            ]
        );

        $this->add_responsive_control(
            'align',
            [
                'label'     => __('تراز', 'zig3d-widgets'),
                'type'      => Controls_Manager::CHOOSE,
                'default'   => 'center',
                'options'   => [
                    'flex-start' => ['title' => __('ابتدا', 'zig3d-widgets'), 'icon' => 'eicon-align-start-v'],
                    'center'     => ['title' => __('وسط', 'zig3d-widgets'), 'icon' => 'eicon-align-center-v'],
                    'flex-end'   => ['title' => __('انتها', 'zig3d-widgets'), 'icon' => 'eicon-align-end-v'],
                    'baseline'   => ['title' => __('خط پایه', 'zig3d-widgets'), 'icon' => 'eicon-align-stretch-v'],
                ],
                'selectors' => ['{{WRAPPER}} .zig-price' => 'align-items: {{VALUE}};'],
            ]
        );

        $this->add_responsive_control(
            'wrap',
            [
                'label'     => __('شکستن به خط بعد', 'zig3d-widgets'),
                'type'      => Controls_Manager::SELECT,
                'default'   => 'wrap',
                'options'   => [
                    'wrap'   => __('بشکند', 'zig3d-widgets'),
                    'nowrap' => __('نشکند', 'zig3d-widgets'),
                ],
                'selectors' => ['{{WRAPPER}} .zig-price' => 'flex-wrap: {{VALUE}};'],
            ]
        );

        $this->add_responsive_control(
            'column_gap',
            [
                'label'      => __('فاصلهٔ افقی', 'zig3d-widgets'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px', 'em', 'rem'],
                'range'      => ['px' => ['min' => 0, 'max' => 80]],
                'default'    => ['size' => 8, 'unit' => 'px'],
                'selectors'  => ['{{WRAPPER}} .zig-price' => 'column-gap: {{SIZE}}{{UNIT}};'],
            ]
        );

        $this->add_responsive_control(
            'row_gap',
            [
                'label'      => __('فاصلهٔ عمودی', 'zig3d-widgets'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px', 'em', 'rem'],
                'range'      => ['px' => ['min' => 0, 'max' => 60]],
                'default'    => ['size' => 4, 'unit' => 'px'],
                'selectors'  => ['{{WRAPPER}} .zig-price' => 'row-gap: {{SIZE}}{{UNIT}};'],
            ]
        );

        /*
         * ترتیب اجزا با ‎order‎ فلکس تنظیم می‌شود، نه با جابه‌جایی در مارک‌آپ.
         * دلیلش دسترسی‌پذیری است: ترتیب خواندنِ صفحه‌خوان و ترتیب فوکوس از
         * مارک‌آپ می‌آید، و مارک‌آپ باید همیشه منطقی بماند — بج، بعد قیمت
         * پیشین، بعد قیمت فعلی.
         */
        $this->add_control(
            'order_heading',
            [
                'label'     => __('ترتیب نمایش', 'zig3d-widgets'),
                'type'      => Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        foreach ([
            'badge'  => __('بج تخفیف', 'zig3d-widgets'),
            'old'    => __('قیمت پیشین', 'zig3d-widgets'),
            'now'    => __('قیمت فعلی', 'zig3d-widgets'),
            'prefix' => __('پیشوند', 'zig3d-widgets'),
            'suffix' => __('یادداشت مالیات', 'zig3d-widgets'),
        ] as $part => $label) {
            $this->add_control(
                'order_' . $part,
                [
                    'label'     => $label,
                    'type'      => Controls_Manager::NUMBER,
                    'min'       => -20,
                    'max'       => 20,
                    'selectors' => ['{{WRAPPER}} .zig-price__' . $part => 'order: {{VALUE}};'],
                ]
            );
        }

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل
     * =================================================================== */

    private function register_now_style_section(): void {
        $this->start_controls_section(
            'now_style_section',
            [
                'label' => __('قیمت فعلی', 'zig3d-widgets'),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_amount_style_controls('now', '.zig-price__now');

        $this->end_controls_section();
    }

    private function register_old_style_section(): void {
        $this->start_controls_section(
            'old_style_section',
            [
                'label'     => __('قیمت پیشین', 'zig3d-widgets'),
                'tab'       => Controls_Manager::TAB_STYLE,
                'condition' => ['show_old' => 'yes'],
            ]
        );

        $this->add_control(
            'old_line',
            [
                'label'     => __('خط روی قیمت', 'zig3d-widgets'),
                'type'      => Controls_Manager::SELECT,
                'default'   => 'line-through',
                'options'   => [
                    'line-through' => __('خط وسط', 'zig3d-widgets'),
                    'none'         => __('بدون خط', 'zig3d-widgets'),
                ],
                'selectors' => ['{{WRAPPER}} .zig-price__old' => 'text-decoration-line: {{VALUE}};'],
            ]
        );

        $this->add_control(
            'old_line_color',
            [
                'label'     => __('رنگ خط', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => ['{{WRAPPER}} .zig-price__old' => 'text-decoration-color: {{VALUE}};'],
                'condition' => ['old_line' => 'line-through'],
            ]
        );

        $this->add_control(
            'old_line_thickness',
            [
                'label'      => __('ضخامت خط', 'zig3d-widgets'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range'      => ['px' => ['min' => 1, 'max' => 8]],
                'selectors'  => ['{{WRAPPER}} .zig-price__old' => 'text-decoration-thickness: {{SIZE}}px;'],
                'condition'  => ['old_line' => 'line-through'],
            ]
        );

        $this->add_amount_style_controls('old', '.zig-price__old');

        $this->end_controls_section();
    }

    /** تایپوگرافی، رنگ، جعبه و واحد پول — مشترک بین قیمت فعلی و پیشین */
    private function add_amount_style_controls(string $prefix, string $selector): void {
        $box = '{{WRAPPER}} ' . $selector;

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'     => $prefix . '_typography',
                'selector' => $box,
            ]
        );

        $this->add_control(
            $prefix . '_color',
            [
                'label'     => __('رنگ', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [$box => 'color: {{VALUE}};'],
            ]
        );

        $this->add_group_control(
            Group_Control_Text_Shadow::get_type(),
            [
                'name'     => $prefix . '_text_shadow',
                'selector' => $box,
            ]
        );

        $this->add_control(
            $prefix . '_unit_heading',
            [
                'label'     => __('واحد پول', 'zig3d-widgets'),
                'type'      => Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'     => $prefix . '_unit_typography',
                'selector' => $box . ' .zig-price__unit',
            ]
        );

        $this->add_control(
            $prefix . '_unit_color',
            [
                'label'     => __('رنگ واحد', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [$box . ' .zig-price__unit' => 'color: {{VALUE}};'],
            ]
        );

        $this->add_responsive_control(
            $prefix . '_unit_gap',
            [
                'label'      => __('فاصلهٔ عدد تا واحد', 'zig3d-widgets'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px', 'em'],
                'range'      => ['px' => ['min' => 0, 'max' => 24]],
                'selectors'  => [$box => '--zig-unit-gap: {{SIZE}}{{UNIT}};'],
            ]
        );

        $this->add_control(
            $prefix . '_box_heading',
            [
                'label'     => __('جعبه', 'zig3d-widgets'),
                'type'      => Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        $this->add_group_control(
            Group_Control_Background::get_type(),
            [
                'name'     => $prefix . '_background',
                'types'    => ['classic', 'gradient'],
                'selector' => $box,
            ]
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name'     => $prefix . '_border',
                'selector' => $box,
            ]
        );

        $this->add_responsive_control(
            $prefix . '_radius',
            [
                'label'      => __('گردی گوشه', 'zig3d-widgets'),
                'type'       => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'selectors'  => [$box => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
            ]
        );

        $this->add_responsive_control(
            $prefix . '_padding',
            [
                'label'      => __('فاصلهٔ داخلی', 'zig3d-widgets'),
                'type'       => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em'],
                'selectors'  => [$box => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
            ]
        );

        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            [
                'name'     => $prefix . '_shadow',
                'selector' => $box,
            ]
        );
    }

    private function register_badge_style_section(): void {
        $this->start_controls_section(
            'badge_style_section',
            [
                'label'     => __('بج تخفیف', 'zig3d-widgets'),
                'tab'       => Controls_Manager::TAB_STYLE,
                'condition' => ['show_badge' => 'yes'],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'     => 'badge_typography',
                'selector' => '{{WRAPPER}} .zig-price__badge',
            ]
        );

        $this->add_control(
            'badge_color',
            [
                'label'     => __('رنگ متن', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => ['{{WRAPPER}} .zig-price__badge' => 'color: {{VALUE}};'],
            ]
        );

        $this->add_group_control(
            Group_Control_Background::get_type(),
            [
                'name'     => 'badge_background',
                'types'    => ['classic', 'gradient'],
                'selector' => '{{WRAPPER}} .zig-price__badge',
            ]
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name'     => 'badge_border',
                'selector' => '{{WRAPPER}} .zig-price__badge',
            ]
        );

        $this->add_responsive_control(
            'badge_radius',
            [
                'label'      => __('گردی گوشه', 'zig3d-widgets'),
                'type'       => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'selectors'  => ['{{WRAPPER}} .zig-price__badge' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
            ]
        );

        $this->add_responsive_control(
            'badge_padding',
            [
                'label'      => __('فاصلهٔ داخلی', 'zig3d-widgets'),
                'type'       => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em'],
                'selectors'  => ['{{WRAPPER}} .zig-price__badge' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
            ]
        );

        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            [
                'name'     => 'badge_shadow',
                'selector' => '{{WRAPPER}} .zig-price__badge',
            ]
        );

        $this->end_controls_section();
    }

    private function register_extras_style_section(): void {
        $this->start_controls_section(
            'extras_style_section',
            [
                'label' => __('پیشوند، جداکننده و یادداشت', 'zig3d-widgets'),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        foreach ([
            'prefix' => [__('پیشوند', 'zig3d-widgets'), '.zig-price__prefix'],
            'sep'    => [__('جداکنندهٔ بازه', 'zig3d-widgets'), '.zig-price__sep'],
            'suffix' => [__('یادداشت مالیات', 'zig3d-widgets'), '.zig-price__suffix'],
            'empty'  => [__('متن جایگزین', 'zig3d-widgets'), '.zig-price__empty'],
        ] as $key => $spec) {
            [$label, $selector] = $spec;

            $this->add_control(
                $key . '_style_heading',
                [
                    'label'     => $label,
                    'type'      => Controls_Manager::HEADING,
                    'separator' => 'before',
                ]
            );

            $this->add_group_control(
                Group_Control_Typography::get_type(),
                [
                    'name'     => $key . '_typography',
                    'selector' => '{{WRAPPER}} ' . $selector,
                ]
            );

            $this->add_control(
                $key . '_color',
                [
                    'label'     => __('رنگ', 'zig3d-widgets'),
                    'type'      => Controls_Manager::COLOR,
                    'selectors' => ['{{WRAPPER}} ' . $selector => 'color: {{VALUE}};'],
                ]
            );
        }

        $this->end_controls_section();
    }

    /* =====================================================================
     * رندر
     * =================================================================== */

    protected function render(): void {
        $settings = $this->get_settings_for_display();

        if (!function_exists('wc_get_product')) {
            $this->editor_notice(__('این ویجت به ووکامرس فعال نیاز دارد.', 'zig3d-widgets'));

            return;
        }

        $product = Price::resolve(absint($settings['product_id'] ?? 0));

        if (null === $product) {
            $this->editor_notice(__('محصولی پیدا نشد. شناسهٔ محصول را وارد کنید یا ویجت را داخل صفحه/قالب محصول بگذارید.', 'zig3d-widgets'));

            return;
        }

        $price = Price::data($product, (string) ($settings['variable_mode'] ?? 'min'));

        if (!$price['has_price']) {
            $this->render_empty($settings);

            return;
        }

        $this->render_price($settings, $product, $price);
    }

    /**
     * پیام راهنما، فقط داخل ادیتور.
     *
     * در سایت هیچ‌چیز چاپ نمی‌شود: بازدیدکننده نه می‌تواند کاری بکند و نه
     * باید بداند کدام ویجت تنظیم نشده.
     */
    private function editor_notice(string $message): void {
        if (!$this->is_editing()) {
            return;
        }

        printf('<div class="zig-price__notice">%s</div>', esc_html($message));
    }

    private function is_editing(): bool {
        return class_exists('\Elementor\Plugin')
            && isset(\Elementor\Plugin::$instance->editor)
            && \Elementor\Plugin::$instance->editor->is_edit_mode();
    }

    private function render_empty(array $settings): void {
        if ('text' !== ($settings['empty_behavior'] ?? 'hide')) {
            $this->editor_notice(__('این محصول قیمت ندارد؛ در سایت چیزی نمایش داده نمی‌شود.', 'zig3d-widgets'));

            return;
        }

        $text = trim((string) ($settings['empty_text'] ?? ''));

        if ('' === $text) {
            return;
        }

        printf(
            '<div class="zig-price zig-price--empty"><span class="zig-price__empty">%s</span></div>',
            esc_html($text)
        );
    }

    private function render_price(array $settings, \WC_Product $product, array $price): void {
        $persian  = 'yes' === ($settings['persian_digits'] ?? 'yes');
        $currency = Price::currency((string) ($settings['currency_text'] ?? ''));

        // بج و قیمت پیشین در حالت بازه معنا ندارند؛ توضیحش در کنترل آمده
        $show_old   = !$price['is_range'] && $price['on_sale']
            && 'yes' === ($settings['show_old'] ?? 'yes') && '' !== $price['old'];
        $show_badge = !$price['is_range'] && $price['on_sale']
            && 'yes' === ($settings['show_badge'] ?? 'yes');

        $classes = ['zig-price'];

        if ($price['on_sale']) {
            $classes[] = 'zig-price--on-sale';
        }
        if ($price['is_range']) {
            $classes[] = 'zig-price--range';
        }

        $schema = 'yes' === ($settings['schema'] ?? '')
            ? ' itemprop="offers" itemscope itemtype="https://schema.org/Offer"'
            : '';

        printf('<div class="%s"%s>', esc_attr(implode(' ', $classes)), $schema); // phpcs:ignore WordPress.Security.EscapeOutput -- ثابت

        $this->render_prefix($settings, $price);

        if ($show_badge) {
            $this->render_badge($settings, $price, $persian, $currency);
        }

        if ($show_old) {
            $unit = 'yes' === ($settings['currency_on_old'] ?? '') ? $currency : '';

            printf(
                '<del class="zig-price__old">%s%s</del>',
                '<span class="zig-price__sr">' . esc_html__('قیمت پیشین:', 'zig3d-widgets') . '</span>',
                $this->amount_html($price['old'], $unit, $persian) // phpcs:ignore WordPress.Security.EscapeOutput -- در amount_html اسکیپ شده
            );
        }

        $this->render_now($settings, $price, $persian, $currency, $show_old);
        $this->render_suffix($settings, $product);

        echo '</div>';
    }

    /**
     * پیشوند «شروع از».
     *
     * فقط برای محصول متغیر یا گروهی در حالت «کمترین قیمت». روی محصول ساده
     * این عبارت به مشتری می‌گوید قیمت‌های دیگری هم هست — که وجود ندارند. و
     * در حالت بازه، خودِ بازه گویاست و پیشوند فقط تکرار است.
     */
    private function render_prefix(array $settings, array $price): void {
        if (!$price['is_multi'] || $price['is_range']) {
            return;
        }

        if ('min' !== ($settings['variable_mode'] ?? 'min')) {
            return;
        }

        $prefix = trim((string) ($settings['prefix_text'] ?? ''));

        if ('' === $prefix) {
            return;
        }

        printf('<span class="zig-price__prefix">%s</span>', esc_html($prefix));
    }

    private function render_badge(array $settings, array $price, bool $persian, string $currency): void {
        $mode = (string) ($settings['badge_mode'] ?? 'percent');

        if ('amount' === $mode) {
            $value = Price::format($price['saved']);
            $label = sprintf(
                /* translators: %s: مبلغ صرفه‌جویی */
                __('%s صرفه‌جویی', 'zig3d-widgets'),
                $value . ' ' . $currency
            );
        } else {
            $value = (string) $price['percent'];
            $label = sprintf(
                /* translators: %s: درصد تخفیف */
                __('%s درصد تخفیف', 'zig3d-widgets'),
                $value
            );
        }

        if ($persian) {
            $value = Price::persian($value);
            $label = Price::persian($label);
        }

        $template = (string) ($settings['badge_template'] ?? '{value}٪');
        $text     = str_replace('{value}', $value, $template);

        /*
         * ‎aria-label‎ صریح است چون «۲۰٪» به‌تنهایی برای صفحه‌خوان مبهم است —
         * بیست درصد چه؟ تخفیف یا مالیات؟ متن دیداری کوتاه می‌ماند و معنا
         * کامل منتقل می‌شود.
         */
        printf(
            '<span class="zig-price__badge" aria-label="%s">%s</span>',
            esc_attr($label),
            esc_html($text)
        );
    }

    private function render_now(
        array $settings,
        array $price,
        bool $persian,
        string $currency,
        bool $show_old
    ): void {
        $unit      = 'yes' === ($settings['currency_on_now'] ?? 'yes') ? $currency : '';
        $free_text = trim((string) ($settings['free_text'] ?? ''));

        $schema = 'yes' === ($settings['schema'] ?? '')
            ? sprintf(' itemprop="price" content="%s"', esc_attr($price['current']))
            : '';

        /*
         * ‎<ins>‎ فقط وقتی که ‎<del>‎ قبلش آمده باشد.
         *
         * ‎<ins>‎ یعنی «متن اضافه‌شده» و بدون ‎<del>‎ متناظر، برای صفحه‌خوان
         * بی‌معناست. همان الگویی که خودِ ووکامرس به کار می‌برد.
         */
        $tag = $show_old ? 'ins' : 'span';

        printf('<%s class="zig-price__now"%s>', $tag, $schema); // phpcs:ignore WordPress.Security.EscapeOutput -- تگ ثابت و اسکیمای اسکیپ‌شده

        if ($show_old) {
            printf('<span class="zig-price__sr">%s</span>', esc_html__('قیمت فعلی:', 'zig3d-widgets'));
        }

        if ($price['is_free'] && '' !== $free_text) {
            printf('<span class="zig-price__free">%s</span>', esc_html($free_text));
        } else {
            echo $this->amount_html($price['current'], $unit, $persian); // phpcs:ignore WordPress.Security.EscapeOutput -- در amount_html اسکیپ شده
        }

        if ($price['is_range'] && '' !== $price['max']) {
            $separator = trim((string) ($settings['range_separator'] ?? ''));

            if ('' !== $separator) {
                printf('<span class="zig-price__sep">%s</span>', esc_html($separator));
            }

            echo $this->amount_html($price['max'], $unit, $persian); // phpcs:ignore WordPress.Security.EscapeOutput -- در amount_html اسکیپ شده
        }

        printf('</%s>', $tag); // phpcs:ignore WordPress.Security.EscapeOutput -- تگ ثابت

        if ('yes' === ($settings['schema'] ?? '') && function_exists('get_woocommerce_currency')) {
            printf('<meta itemprop="priceCurrency" content="%s" />', esc_attr(get_woocommerce_currency()));
        }
    }

    private function render_suffix(array $settings, \WC_Product $product): void {
        if ('yes' !== ($settings['show_tax_suffix'] ?? '')) {
            return;
        }

        if (!method_exists($product, 'get_price_suffix')) {
            return;
        }

        $suffix = trim((string) $product->get_price_suffix());

        if ('' === $suffix) {
            return;
        }

        // خروجی ووکامرس خودش مارک‌آپ دارد (‎<small class="woocommerce-price-suffix">‎)
        printf('<span class="zig-price__suffix">%s</span>', wp_kses_post($suffix));
    }

    /**
     * عدد + واحد پول.
     *
     * ‎<bdi>‎ اجباری است. عدد در متن راست‌به‌چپ یک «اجرای چپ‌به‌راست» است و
     * بدون جداسازی دوطرفه، الگوریتم دوجهته‌ی یونیکد می‌تواند عدد و واحد را
     * جابه‌جا کند — مثلاً «تومان ۱۲۳» به‌جای «۱۲۳ تومان». خودِ ووکامرس هم به
     * همین دلیل ‎<bdi>‎ می‌گذارد.
     */
    private function amount_html(string $raw, string $unit, bool $persian): string {
        $amount = Price::format($raw);

        if ($persian) {
            $amount = Price::persian($amount);
        }

        $html = '<bdi class="zig-price__value"><span class="zig-price__amount">'
            . esc_html($amount) . '</span>';

        if ('' !== $unit) {
            $html .= '<span class="zig-price__unit">' . esc_html($unit) . '</span>';
        }

        return $html . '</bdi>';
    }
}
