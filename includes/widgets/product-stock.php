<?php
namespace Zig3d_Widgets\Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Background;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;
use Zig3d_Widgets\Markup;
use Zig3d_Widgets\Plugin;
use Zig3d_Widgets\Price;
use Zig3d_Widgets\Stock;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * وضعیت موجودی محصول.
 *
 *     div.zig-stock.zig-stock--{state}
 *       span.zig-stock__bullet     نشان (تزئینی)
 *       span.zig-stock__label      متن وضعیت
 *
 * همیشه دقیقاً یک وضعیت رندر می‌شود. تشخیصش در ‎Zig3d_Widgets\Stock‎ است تا
 * جدا از المنتور قابل تست بماند؛ این کلاس فقط کنترل‌ها و مارک‌آپ است.
 *
 * هر وضعیت کلاس خودش را می‌گیرد، پس هر پنج حالت استایل کاملاً مستقل دارند
 * بی‌آنکه لازم باشد چیزی در خروجی تکرار شود.
 */
final class Product_Stock extends Widget_Base {

    /**
     * برچسب و متن پیش‌فرض هر وضعیت.
     *
     * متن پیش‌فرضِ «موجود» عمداً همان «موجود در انبار» است. آن حالت در
     * صورت‌مسئله نبود ولی وجودش اجباری است (فروشگاهی که موجودی را نمی‌شمارد
     * هیچ عددی ندارد)، و با این پیش‌فرض، رفتار بیرون از جعبه دقیقاً همان
     * چیزی است که انتظار می‌رود — ولی هر وقت لازم شد قابل جدا کردن است.
     */
    private function states(): array {
        return [
            Stock::IN_STOCK => [
                'label'   => __('موجود در انبار', 'zig3d-widgets'),
                'default' => __('موجود در انبار', 'zig3d-widgets'),
                'color'   => '#1B8A4B',
                'note'    => __('تعداد ثبت شده و دست‌کم یک عدد است.', 'zig3d-widgets'),
            ],
            Stock::LOW_STOCK => [
                'label'   => __('رو به اتمام', 'zig3d-widgets'),
                'default' => __('تنها {qty} عدد باقی مانده', 'zig3d-widgets'),
                'color'   => '#B26A00',
                'note'    => __('تعداد ثبت شده و از آستانه کمتر یا مساوی است.', 'zig3d-widgets'),
            ],
            Stock::AVAILABLE => [
                'label'   => __('موجود (بدون شمارش)', 'zig3d-widgets'),
                'default' => __('موجود در انبار', 'zig3d-widgets'),
                'color'   => '#1B8A4B',
                'note'    => __('محصول موجود است ولی مدیریت موجودی برایش روشن نیست، پس عددی در کار نیست.', 'zig3d-widgets'),
            ],
            Stock::BACKORDER => [
                'label'   => __('پیش‌خرید', 'zig3d-widgets'),
                'default' => __('قابل سفارش', 'zig3d-widgets'),
                'color'   => '#2B6CB0',
                'note'    => __('موجود نیست ولی ووکامرس اجازهٔ سفارش می‌دهد.', 'zig3d-widgets'),
            ],
            Stock::OUT_OF_STOCK => [
                'label'   => __('ناموجود', 'zig3d-widgets'),
                'default' => __('تماس برای موجودی', 'zig3d-widgets'),
                'color'   => '#B02A2A',
                'note'    => __('هیچ راهی برای سفارش وجود ندارد.', 'zig3d-widgets'),
            ],
        ];
    }

    public function get_name(): string {
        return 'zig3d-product-stock';
    }

    public function get_title(): string {
        return __('وضعیت موجودی', 'zig3d-widgets');
    }

    public function get_icon(): string {
        return 'eicon-product-stock';
    }

    public function get_categories(): array {
        return [Plugin::CATEGORY];
    }

    public function get_keywords(): array {
        return ['stock', 'availability', 'inventory', 'backorder', 'موجودی', 'انبار', 'وضعیت', 'پیش‌خرید'];
    }

    public function get_style_depends(): array {
        return ['zig3d-widgets'];
    }

    public function has_widget_inner_wrapper(): bool {
        return false;
    }

    protected function register_controls(): void {
        $this->register_product_section();
        $this->register_states_section();
        $this->register_bullet_section();
        $this->register_layout_section();
        $this->register_state_style_section();
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
            'variable_note',
            [
                'type'            => Controls_Manager::RAW_HTML,
                'raw'             => __('برای محصول متغیر، وضعیتِ کلیِ محصول نمایش داده می‌شود نه وضعیت یک گزینهٔ خاص — چون تا وقتی مشتری گزینه‌ای انتخاب نکرده، گزینهٔ مشخصی وجود ندارد. خودِ ووکامرس هم همین کار را می‌کند.', 'zig3d-widgets'),
                'content_classes' => 'elementor-descriptor',
            ]
        );

        $this->add_control(
            'aggregate',
            [
                'label'        => __('جمع‌زدن موجودی گزینه‌ها', 'zig3d-widgets'),
                'type'         => Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'description'  => __('برای محصول متغیری که موجودی را در سطح گزینه‌ها نگه می‌دارد. پیش‌فرض خاموش است چون هر گزینه یک بار خواندن از دیتابیس است؛ در یک فهرست با ده‌ها کارت این هزینه جمع می‌شود.', 'zig3d-widgets'),
            ]
        );

        $this->add_control(
            'schema',
            [
                'label'        => __('نشانه‌گذاری ساختاریافته', 'zig3d-widgets'),
                'type'         => Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'separator'    => 'before',
                'description'  => __('اعلام وضعیت موجودی به موتور جست‌وجو. اگر افزونهٔ سئوی شما خودش این را در JSON-LD می‌فرستد، خاموش بگذارید تا داده تکراری نشود.', 'zig3d-widgets'),
            ]
        );

        $this->end_controls_section();
    }

    /* =====================================================================
     * محتوا: وضعیت‌ها
     * =================================================================== */

    private function register_states_section(): void {
        $this->start_controls_section(
            'states_section',
            ['label' => __('وضعیت‌ها', 'zig3d-widgets')]
        );

        $this->add_control(
            'enable_backorder',
            [
                'label'        => __('حالت پیش‌خرید', 'zig3d-widgets'),
                'type'         => Controls_Manager::SWITCHER,
                'default'      => 'yes',
                'return_value' => 'yes',
                'description'  => __('اگر خاموش باشد، محصولِ در پیش‌خرید با متن «موجود» نمایش داده می‌شود — نه «موجود در انبار»، چون تعدادش صفر است.', 'zig3d-widgets'),
            ]
        );

        $this->add_control(
            'enable_lowstock',
            [
                'label'        => __('حالت رو به اتمام', 'zig3d-widgets'),
                'type'         => Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'description'  => __('وضعیت جداگانه برای وقتی تعداد از آستانه کمتر است. خاموش یعنی همان «موجود در انبار».', 'zig3d-widgets'),
            ]
        );

        $this->add_control(
            'low_threshold',
            [
                'label'       => __('آستانهٔ رو به اتمام', 'zig3d-widgets'),
                'type'        => Controls_Manager::NUMBER,
                'min'         => 1,
                'description' => __('خالی = آستانهٔ خودِ محصول، وگرنه تنظیم سراسری ووکامرس.', 'zig3d-widgets'),
                'condition'   => ['enable_lowstock' => 'yes'],
            ]
        );

        $this->add_control(
            'texts_heading',
            [
                'label'     => __('متن هر وضعیت', 'zig3d-widgets'),
                'type'      => Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        $this->add_control(
            'qty_note',
            [
                'type'            => Controls_Manager::RAW_HTML,
                'raw'             => __('در متن‌ها می‌توانید <code>{qty}</code> بگذارید تا جای تعداد موجودی بنشیند. اگر تعدادی در کار نباشد، خودش و فاصله‌های اضافه‌اش حذف می‌شوند.', 'zig3d-widgets'),
                'content_classes' => 'elementor-descriptor',
            ]
        );

        foreach ($this->states() as $key => $state) {
            $this->add_control(
                'text_' . $key,
                [
                    'label'       => $state['label'],
                    'type'        => Controls_Manager::TEXT,
                    'default'     => $state['default'],
                    'dynamic'     => ['active' => true],
                    'label_block' => true,
                    'description' => $state['note'],
                    'condition'   => $this->state_condition($key),
                ]
            );
        }

        $this->add_control(
            'persian_digits',
            [
                'label'        => __('ارقام فارسی برای {qty}', 'zig3d-widgets'),
                'type'         => Controls_Manager::SWITCHER,
                'default'      => 'yes',
                'return_value' => 'yes',
                'separator'    => 'before',
            ]
        );

        $this->add_control(
            'hide_heading',
            [
                'label'     => __('پنهان‌کردن', 'zig3d-widgets'),
                'type'      => Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        $this->add_control(
            'hidden_states',
            [
                'label'       => __('این وضعیت‌ها نمایش داده نشوند', 'zig3d-widgets'),
                'type'        => Controls_Manager::SELECT2,
                'multiple'    => true,
                'label_block' => true,
                'options'     => array_map(
                    static fn(array $state): string => $state['label'],
                    $this->states()
                ),
                'description' => __('مثلاً وقتی می‌خواهید فقط هشدار ناموجودی دیده شود و حالت عادی چیزی اضافه نکند.', 'zig3d-widgets'),
            ]
        );

        $this->end_controls_section();
    }

    /**
     * شرط نمایش کنترل‌های یک وضعیت.
     *
     * دو حالتِ اختیاری وقتی خاموش‌اند هیچ‌وقت رندر نمی‌شوند، پس کنترل‌هایشان
     * هم نباید در پنل جا بگیرند و کاربر را سردرگم کنند.
     */
    private function state_condition(string $key): array {
        if (Stock::BACKORDER === $key) {
            return ['enable_backorder' => 'yes'];
        }

        if (Stock::LOW_STOCK === $key) {
            return ['enable_lowstock' => 'yes'];
        }

        return [];
    }

    /* =====================================================================
     * محتوا: نشان
     * =================================================================== */

    private function register_bullet_section(): void {
        $this->start_controls_section(
            'bullet_section',
            ['label' => __('نشان', 'zig3d-widgets')]
        );

        $this->add_control(
            'show_bullet',
            [
                'label'        => __('نمایش نشان', 'zig3d-widgets'),
                'type'         => Controls_Manager::SWITCHER,
                'default'      => 'yes',
                'return_value' => 'yes',
            ]
        );

        $this->add_control(
            'bullet_shape',
            [
                'label'     => __('شکل', 'zig3d-widgets'),
                'type'      => Controls_Manager::SELECT,
                'default'   => 'circle',
                'options'   => [
                    'circle'  => __('دایره', 'zig3d-widgets'),
                    'rounded' => __('مربع گرد', 'zig3d-widgets'),
                    'square'  => __('مربع', 'zig3d-widgets'),
                    'diamond' => __('لوزی', 'zig3d-widgets'),
                    'dash'    => __('خط تیره', 'zig3d-widgets'),
                    'ring'    => __('حلقه', 'zig3d-widgets'),
                ],
                'condition' => ['show_bullet' => 'yes'],
            ]
        );

        $this->add_responsive_control(
            'bullet_size',
            [
                'label'      => __('اندازه', 'zig3d-widgets'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px', 'em', 'rem'],
                'range'      => ['px' => ['min' => 2, 'max' => 40]],
                'default'    => ['size' => 8, 'unit' => 'px'],
                'selectors'  => ['{{WRAPPER}} .zig-stock__bullet' => '--zig-bullet-size: {{SIZE}}{{UNIT}};'],
                'condition'  => ['show_bullet' => 'yes'],
            ]
        );

        $this->add_responsive_control(
            'bullet_offset',
            [
                'label'       => __('جابه‌جایی عمودی', 'zig3d-widgets'),
                'type'        => Controls_Manager::SLIDER,
                'size_units'  => ['px'],
                'range'       => ['px' => ['min' => -20, 'max' => 20]],
                'description' => __('برای هم‌ترازی دقیق نشان با خط متن.', 'zig3d-widgets'),
                'selectors'   => ['{{WRAPPER}} .zig-stock__bullet' => '--zig-bullet-offset: {{SIZE}}px;'],
                'condition'   => ['show_bullet' => 'yes'],
            ]
        );

        $this->add_control(
            'bullet_pulse',
            [
                'label'        => __('تپش نشان', 'zig3d-widgets'),
                'type'         => Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'description'  => __('یک هالهٔ آرامِ ضربان‌دار دور نشان. با تنظیم سیستمیِ «حرکت کمتر» خودبه‌خود خاموش می‌شود.', 'zig3d-widgets'),
                'condition'    => ['show_bullet' => 'yes'],
            ]
        );

        $this->add_control(
            'pulse_states',
            [
                'label'       => __('فقط برای این وضعیت‌ها', 'zig3d-widgets'),
                'type'        => Controls_Manager::SELECT2,
                'multiple'    => true,
                'label_block' => true,
                'default'     => [Stock::LOW_STOCK],
                'options'     => array_map(
                    static fn(array $state): string => $state['label'],
                    $this->states()
                ),
                'description' => __('خالی = همهٔ وضعیت‌ها.', 'zig3d-widgets'),
                'condition'   => ['show_bullet' => 'yes', 'bullet_pulse' => 'yes'],
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
            'align',
            [
                'label'     => __('تراز', 'zig3d-widgets'),
                'type'      => Controls_Manager::CHOOSE,
                'default'   => 'flex-start',
                'options'   => [
                    'flex-start' => ['title' => __('ابتدا', 'zig3d-widgets'), 'icon' => 'eicon-text-align-right'],
                    'center'     => ['title' => __('وسط', 'zig3d-widgets'), 'icon' => 'eicon-text-align-center'],
                    'flex-end'   => ['title' => __('انتها', 'zig3d-widgets'), 'icon' => 'eicon-text-align-left'],
                    'stretch'    => ['title' => __('تمام‌عرض', 'zig3d-widgets'), 'icon' => 'eicon-text-align-justify'],
                ],
                'selectors' => ['{{WRAPPER}} .zig-stock-wrap' => 'align-items: {{VALUE}};'],
            ]
        );

        $this->add_responsive_control(
            'bullet_position',
            [
                'label'     => __('جای نشان', 'zig3d-widgets'),
                'type'      => Controls_Manager::CHOOSE,
                'default'   => 'row',
                'options'   => [
                    'row'         => ['title' => __('ابتدای متن', 'zig3d-widgets'), 'icon' => 'eicon-h-align-right'],
                    'row-reverse' => ['title' => __('انتهای متن', 'zig3d-widgets'), 'icon' => 'eicon-h-align-left'],
                ],
                'toggle'    => false,
                'selectors' => ['{{WRAPPER}} .zig-stock' => 'flex-direction: {{VALUE}};'],
                'condition' => ['show_bullet' => 'yes'],
            ]
        );

        $this->add_responsive_control(
            'gap',
            [
                'label'      => __('فاصلهٔ نشان تا متن', 'zig3d-widgets'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px', 'em', 'rem'],
                'range'      => ['px' => ['min' => 0, 'max' => 40]],
                'default'    => ['size' => 7, 'unit' => 'px'],
                'selectors'  => ['{{WRAPPER}} .zig-stock' => 'gap: {{SIZE}}{{UNIT}};'],
                'condition'  => ['show_bullet' => 'yes'],
            ]
        );

        $this->add_responsive_control(
            'min_width',
            [
                'label'       => __('کمینهٔ عرض', 'zig3d-widgets'),
                'type'        => Controls_Manager::SLIDER,
                'size_units'  => ['px', '%', 'em'],
                'range'       => [
                    'px' => ['min' => 0, 'max' => 400],
                    '%'  => ['min' => 0, 'max' => 100],
                ],
                'description' => __('برای هم‌عرض ماندن نشان‌ها وقتی متن وضعیت‌ها طول متفاوت دارد.', 'zig3d-widgets'),
                'selectors'   => ['{{WRAPPER}} .zig-stock' => 'min-width: {{SIZE}}{{UNIT}};'],
            ]
        );

        $this->add_responsive_control(
            'justify',
            [
                'label'     => __('توزیع محتوای داخلی', 'zig3d-widgets'),
                'type'      => Controls_Manager::CHOOSE,
                'options'   => [
                    'flex-start'    => ['title' => __('ابتدا', 'zig3d-widgets'), 'icon' => 'eicon-flex eicon-justify-start-h'],
                    'center'        => ['title' => __('وسط', 'zig3d-widgets'), 'icon' => 'eicon-flex eicon-justify-center-h'],
                    'space-between' => ['title' => __('فاصلهٔ بین', 'zig3d-widgets'), 'icon' => 'eicon-flex eicon-justify-space-between-h'],
                ],
                'selectors' => ['{{WRAPPER}} .zig-stock' => 'justify-content: {{VALUE}};'],
            ]
        );

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل هر وضعیت
     * =================================================================== */

    private function register_state_style_section(): void {
        $this->start_controls_section(
            'state_style_section',
            [
                'label' => __('وضعیت‌ها', 'zig3d-widgets'),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        /*
         * تایپوگرافی و اندازه‌های مشترک بیرون از تب‌ها.
         *
         * در عمل تقریباً همیشه فقط رنگ و پس‌زمینهٔ وضعیت‌ها فرق دارد و بقیه
         * یکسان است. اگر همه‌چیز داخل تب‌ها بود، کاربر باید یک اندازهٔ قلم
         * را پنج بار تنظیم می‌کرد. هر کدام از این‌ها در تبِ خودِ وضعیت قابل
         * بازنویسی است.
         */
        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'     => 'shared_typography',
                'label'    => __('تایپوگرافی (همهٔ وضعیت‌ها)', 'zig3d-widgets'),
                'selector' => '{{WRAPPER}} .zig-stock',
            ]
        );

        $this->add_responsive_control(
            'shared_padding',
            [
                'label'      => __('فاصلهٔ داخلی (همه)', 'zig3d-widgets'),
                'type'       => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', 'rem', '%'],
                'selectors'  => ['{{WRAPPER}} .zig-stock' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
            ]
        );

        $this->add_responsive_control(
            'shared_radius',
            [
                'label'      => __('گردی گوشه (همه)', 'zig3d-widgets'),
                'type'       => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'selectors'  => ['{{WRAPPER}} .zig-stock' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
            ]
        );

        $this->start_controls_tabs('state_tabs');

        foreach ($this->states() as $key => $state) {
            $this->start_controls_tab(
                'tab_' . $key,
                [
                    'label'     => $state['label'],
                    'condition' => $this->state_condition($key),
                ]
            );

            $this->add_state_style_controls($key, $state);

            $this->end_controls_tab();
        }

        $this->end_controls_tabs();

        $this->end_controls_section();
    }

    /** مجموعهٔ کامل کنترل‌های یک وضعیت */
    private function add_state_style_controls(string $key, array $state): void {
        $box    = '{{WRAPPER}} .zig-stock--' . $key;
        $bullet = $box . ' .zig-stock__bullet';

        $this->add_control(
            $key . '_color',
            [
                'label'     => __('رنگ متن', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'default'   => $state['color'],
                'selectors' => [$box => 'color: {{VALUE}};'],
            ]
        );

        /*
         * رنگ نشان پیش‌فرض ندارد و به ‎currentColor‎ می‌افتد، پس بدون هیچ
         * تنظیمی هم‌رنگ متنِ همان وضعیت است. تنظیمش فقط وقتی لازم است که
         * کاربر عمداً بخواهد فرق داشته باشند.
         */
        $this->add_control(
            $key . '_bullet_color',
            [
                'label'     => __('رنگ نشان', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [$bullet => '--zig-bullet-color: {{VALUE}};'],
                'condition' => ['show_bullet' => 'yes'],
            ]
        );

        $this->add_group_control(
            Group_Control_Background::get_type(),
            [
                'name'     => $key . '_background',
                'label'    => __('پس‌زمینه', 'zig3d-widgets'),
                'types'    => ['classic', 'gradient'],
                'selector' => $box,
            ]
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name'     => $key . '_border',
                'selector' => $box,
            ]
        );

        $this->add_responsive_control(
            $key . '_radius',
            [
                'label'       => __('گردی گوشه', 'zig3d-widgets'),
                'type'        => Controls_Manager::DIMENSIONS,
                'size_units'  => ['px', 'em', '%'],
                'description' => __('بر مقدار مشترک بالا می‌چربد.', 'zig3d-widgets'),
                'selectors'   => [$box => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
            ]
        );

        $this->add_responsive_control(
            $key . '_padding',
            [
                'label'       => __('فاصلهٔ داخلی', 'zig3d-widgets'),
                'type'        => Controls_Manager::DIMENSIONS,
                'size_units'  => ['px', 'em', 'rem', '%'],
                'description' => __('بر مقدار مشترک بالا می‌چربد.', 'zig3d-widgets'),
                'selectors'   => [$box => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'     => $key . '_typography',
                'label'    => __('تایپوگرافی', 'zig3d-widgets'),
                'selector' => $box,
            ]
        );

        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            [
                'name'     => $key . '_shadow',
                'selector' => $box,
            ]
        );
    }

    /* =====================================================================
     * رندر
     * =================================================================== */

    protected function render(): void {
        $settings = $this->get_settings_for_display();

        if (!function_exists('wc_get_product')) {
            $this->notice(__('این ویجت به ووکامرس فعال نیاز دارد.', 'zig3d-widgets'));

            return;
        }

        $product = Price::resolve(absint($settings['product_id'] ?? 0));

        if (null === $product) {
            $this->notice(__('محصولی پیدا نشد. شناسهٔ محصول را وارد کنید یا ویجت را داخل صفحه/قالب محصول بگذارید.', 'zig3d-widgets'));

            return;
        }

        $status = Stock::state($product, [
            'backorder'     => 'yes' === ($settings['enable_backorder'] ?? 'yes'),
            'lowstock'      => 'yes' === ($settings['enable_lowstock'] ?? ''),
            'low_threshold' => '' === ($settings['low_threshold'] ?? '') ? null : (int) $settings['low_threshold'],
            'aggregate'     => 'yes' === ($settings['aggregate'] ?? ''),
        ]);

        $state = $status['state'];

        $hidden = (array) ($settings['hidden_states'] ?? []);

        if (in_array($state, $hidden, true)) {
            $this->notice(__('این وضعیت در تنظیمات پنهان شده است.', 'zig3d-widgets'));

            return;
        }

        $label = $this->label($settings, $state, $status['quantity']);

        if (!Markup::filled($label)) {
            $this->notice(__('متن این وضعیت خالی است.', 'zig3d-widgets'));

            return;
        }

        $this->render_status($settings, $state, $status, $label);
    }

    private function render_status(array $settings, string $state, array $status, string $label): void {
        $classes = ['zig-stock', 'zig-stock--' . $state];

        if ('yes' === ($settings['show_bullet'] ?? 'yes')) {
            $classes[] = 'zig-stock--has-bullet';
        }

        if ($this->should_pulse($settings, $state)) {
            $classes[] = 'zig-stock--pulse';
        }

        $schema = '';

        if ('yes' === ($settings['schema'] ?? '')) {
            $schema = ' itemprop="offers" itemscope itemtype="https://schema.org/Offer"';
        }

        printf(
            '<div class="zig-stock-wrap"><div class="%s"%s>',
            esc_attr(implode(' ', $classes)),
            $schema // phpcs:ignore WordPress.Security.EscapeOutput -- رشتهٔ ثابت
        );

        if ('yes' === ($settings['show_bullet'] ?? 'yes')) {
            printf(
                '<span class="zig-stock__bullet zig-stock__bullet--%s" aria-hidden="true"></span>',
                esc_attr(sanitize_html_class((string) ($settings['bullet_shape'] ?? 'circle'), 'circle'))
            );
        }

        printf(
            '<span class="zig-stock__label">%s</span>',
            Markup::text($label) // phpcs:ignore WordPress.Security.EscapeOutput -- wp_kses با فهرست سفید درون‌خطی
        );

        if ('' !== $schema) {
            printf('<link itemprop="availability" href="%s" />', esc_url($status['schema']));
        }

        echo '</div></div>';
    }

    /**
     * متن وضعیت، با جای‌گذاری ‎{qty}‎.
     *
     * وقتی تعدادی در کار نیست، فقط خودِ نشانه حذف نمی‌شود؛ فاصله‌های دو
     * طرفش هم جمع می‌شوند. وگرنه «تنها  عدد باقی مانده» با دو فاصله چاپ
     * می‌شد — چیزی که در بازبینی متن دیده نمی‌شود ولی روی صفحه پیداست.
     */
    private function label(array $settings, string $state, ?int $quantity): string {
        $text = (string) ($settings['text_' . $state] ?? '');

        if (false === strpos($text, '{qty}')) {
            return trim($text);
        }

        if (null === $quantity) {
            return trim((string) preg_replace('/\s*\{qty\}\s*/u', ' ', $text));
        }

        $formatted = 'yes' === ($settings['persian_digits'] ?? 'yes')
            ? Price::persian((string) $quantity)
            : (string) $quantity;

        return trim(str_replace('{qty}', $formatted, $text));
    }

    private function should_pulse(array $settings, string $state): bool {
        if ('yes' !== ($settings['bullet_pulse'] ?? '') || 'yes' !== ($settings['show_bullet'] ?? 'yes')) {
            return false;
        }

        $only = array_filter((array) ($settings['pulse_states'] ?? []));

        return [] === $only || in_array($state, $only, true);
    }

    /** پیام راهنما، فقط داخل ادیتور */
    private function notice(string $message): void {
        $editing = class_exists('\Elementor\Plugin')
            && isset(\Elementor\Plugin::$instance->editor)
            && \Elementor\Plugin::$instance->editor->is_edit_mode();

        if (!$editing) {
            return;
        }

        printf('<div class="zig-price__notice">%s</div>', esc_html($message));
    }
}
