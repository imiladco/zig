<?php
namespace Zig3d_Widgets\Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Repeater;
use Elementor\Widget_Base;
use Zig3d_Widgets\Markup;
use Zig3d_Widgets\Plugin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * لیست عنوان‌ها با بولت.
 *
 *     ul.zig-list                 ظرف فلکس
 *       li.zig-list__item         آیتم فلکس
 *         span.zig-list__bullet   بولت (تزئینی)
 *         span|a.zig-list__label  عنوان
 *
 * چرا ‎<ul>/<li>‎ و نه چند ‎<div>‎: این واقعاً یک فهرست است و صفحه‌خوان باید
 * «فهرست، ۳ مورد» را اعلام کند. نکتهٔ ظریف اینکه ‎list-style: none‎ در سافاری
 * معناشناسی فهرست را از بین می‌برد؛ برای همین ‎role="list"‎ صریح روی ‎<ul>‎
 * می‌نشیند تا در همهٔ مرورگرها فهرست بماند.
 */
final class Bullet_List extends Widget_Base {

    use Traits\Box;

    public function get_name(): string {
        return 'zig3d-bullet-list';
    }

    public function get_title(): string {
        return __('لیست عنوان‌ها', 'zig3d-widgets');
    }

    public function get_icon(): string {
        return 'eicon-bullet-list';
    }

    public function get_categories(): array {
        return [Plugin::CATEGORY];
    }

    public function get_keywords(): array {
        return ['list', 'bullet', 'items', 'features', 'لیست', 'بولت', 'عناوین', 'ویژگی'];
    }

    public function get_style_depends(): array {
        return ['zig3d-widgets'];
    }

    public function has_widget_inner_wrapper(): bool {
        return false;
    }

    protected function register_controls(): void {
        $this->register_items_section();
        $this->register_layout_section();
        $this->register_item_layout_section();
        $this->register_bullet_style_section();
        $this->register_item_style_section();
        $this->register_label_style_section();
    }

    /* =====================================================================
     * محتوا
     * =================================================================== */

    private function register_items_section(): void {
        $this->start_controls_section(
            'items_section',
            ['label' => __('آیتم‌ها', 'zig3d-widgets')]
        );

        $repeater = new Repeater();

        $repeater->add_control(
            'label',
            [
                'label'       => __('عنوان', 'zig3d-widgets'),
                'type'        => Controls_Manager::TEXT,
                'dynamic'     => ['active' => true],
                'default'     => __('آیتم جدید', 'zig3d-widgets'),
                'label_block' => true,
            ]
        );

        $repeater->add_control(
            'link',
            [
                'label'       => __('پیوند', 'zig3d-widgets'),
                'type'        => Controls_Manager::URL,
                'dynamic'     => ['active' => true],
                'placeholder' => 'https://zig3d.com',
                'options'     => ['url', 'is_external', 'nofollow', 'custom_attributes'],
                'label_block' => true,
            ]
        );

        $repeater->add_control(
            'is_active',
            [
                'label'        => __('حالت فعال', 'zig3d-widgets'),
                'type'         => Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'description'  => __('برای مشخص‌کردن آیتم جاری. استایلش در تب «آیتم» جداگانه تنظیم می‌شود.', 'zig3d-widgets'),
            ]
        );

        $repeater->add_control(
            'show_bullet',
            [
                'label'        => __('نمایش بولت', 'zig3d-widgets'),
                'type'         => Controls_Manager::SWITCHER,
                'default'      => 'yes',
                'return_value' => 'yes',
            ]
        );

        // رنگ اختصاصی، بدون آنکه لازم باشد کاربر برای یک آیتم متفاوت، کل
        // ویجت را کپی کند
        $repeater->add_control(
            'item_bullet_color',
            [
                'label'     => __('رنگ بولت این آیتم', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => ['{{WRAPPER}} {{CURRENT_ITEM}} .zig-list__bullet' => '--zig-bullet-color: {{VALUE}};'],
                'condition' => ['show_bullet' => 'yes'],
            ]
        );

        $repeater->add_control(
            'item_label_color',
            [
                'label'     => __('رنگ عنوان این آیتم', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => ['{{WRAPPER}} {{CURRENT_ITEM}} .zig-list__label' => 'color: {{VALUE}};'],
            ]
        );

        $this->add_control(
            'items',
            [
                'label'       => __('آیتم‌ها', 'zig3d-widgets'),
                'type'        => Controls_Manager::REPEATER,
                'fields'      => $repeater->get_controls(),
                'title_field' => '{{{ label }}}',
                'default'     => [
                    ['label' => __('تیم مهندسی مکانیک', 'zig3d-widgets')],
                    ['label' => __('تمرکز تخصصی بر سیستم‌های میلینگ', 'zig3d-widgets')],
                    ['label' => __('تأمین قطعات و خدمات فنی', 'zig3d-widgets')],
                ],
            ]
        );

        $this->add_control(
            'label_tag',
            [
                'label'       => __('تگ عنوان', 'zig3d-widgets'),
                'type'        => Controls_Manager::SELECT,
                'default'     => 'span',
                'options'     => Markup::TAGS,
                'separator'   => 'before',
                'description' => __('برای فهرست‌های تزئینی span مناسب است؛ اگر هر آیتم واقعاً یک سرتیتر است، تگ سرتیتر انتخاب کنید.', 'zig3d-widgets'),
            ]
        );

        $this->end_controls_section();
    }

    /* =====================================================================
     * چیدمان ظرف
     * =================================================================== */

    private function register_layout_section(): void {
        $this->start_controls_section(
            'layout_section',
            ['label' => __('چیدمان فهرست', 'zig3d-widgets')]
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
                'selectors' => ['{{WRAPPER}} .zig-list' => 'flex-direction: {{VALUE}};'],
            ]
        );

        /*
         * عمداً SELECT است و نه SWITCHER: کلید خاموش، مقدار خالی می‌دهد و
         * المنتور برای مقدار خالی هیچ قاعده‌ای تولید نمی‌کند — یعنی «خاموش»
         * هیچ‌وقت به ‎nowrap‎ تبدیل نمی‌شد و گزینه بی‌اثر می‌ماند.
         */
        $this->add_responsive_control(
            'wrap',
            [
                'label'       => __('شکستن به خط بعد', 'zig3d-widgets'),
                'type'        => Controls_Manager::SELECT,
                'default'     => 'wrap',
                'options'     => [
                    'wrap'         => __('بشکند', 'zig3d-widgets'),
                    'nowrap'       => __('نشکند (یک خط)', 'zig3d-widgets'),
                    'wrap-reverse' => __('بشکند، معکوس', 'zig3d-widgets'),
                ],
                'description' => __('«نشکند» با فهرست بلند سرریز افقی می‌سازد؛ فقط وقتی مناسب است که تعداد آیتم‌ها کم و ثابت باشد.', 'zig3d-widgets'),
                'selectors'   => ['{{WRAPPER}} .zig-list' => 'flex-wrap: {{VALUE}};'],
            ]
        );

        $this->add_responsive_control(
            'justify',
            [
                'label'     => __('توزیع در راستای اصلی', 'zig3d-widgets'),
                'type'      => Controls_Manager::CHOOSE,
                'options'   => [
                    'flex-start'    => ['title' => __('ابتدا', 'zig3d-widgets'), 'icon' => 'eicon-flex eicon-justify-start-h'],
                    'center'        => ['title' => __('وسط', 'zig3d-widgets'), 'icon' => 'eicon-flex eicon-justify-center-h'],
                    'flex-end'      => ['title' => __('انتها', 'zig3d-widgets'), 'icon' => 'eicon-flex eicon-justify-end-h'],
                    'space-between' => ['title' => __('فاصلهٔ بین', 'zig3d-widgets'), 'icon' => 'eicon-flex eicon-justify-space-between-h'],
                    'space-around'  => ['title' => __('فاصلهٔ اطراف', 'zig3d-widgets'), 'icon' => 'eicon-flex eicon-justify-space-around-h'],
                ],
                'selectors' => ['{{WRAPPER}} .zig-list' => 'justify-content: {{VALUE}};'],
            ]
        );

        $this->add_responsive_control(
            'align',
            [
                'label'     => __('تراز در راستای فرعی', 'zig3d-widgets'),
                'type'      => Controls_Manager::CHOOSE,
                'options'   => [
                    'flex-start' => ['title' => __('ابتدا', 'zig3d-widgets'), 'icon' => 'eicon-align-start-v'],
                    'center'     => ['title' => __('وسط', 'zig3d-widgets'), 'icon' => 'eicon-align-center-v'],
                    'flex-end'   => ['title' => __('انتها', 'zig3d-widgets'), 'icon' => 'eicon-align-end-v'],
                    'stretch'    => ['title' => __('کشیده', 'zig3d-widgets'), 'icon' => 'eicon-align-stretch-v'],
                ],
                'selectors' => ['{{WRAPPER}} .zig-list' => 'align-items: {{VALUE}};'],
            ]
        );

        // فاصلهٔ ردیف و ستون جداگانه: در حالت افقیِ شکسته‌شونده تقریباً همیشه
        // فاصلهٔ عمودی باید کمتر از افقی باشد
        $this->add_responsive_control(
            'column_gap',
            [
                'label'      => __('فاصلهٔ افقی', 'zig3d-widgets'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px', 'em', 'rem', '%'],
                'range'      => ['px' => ['min' => 0, 'max' => 160]],
                'default'    => ['size' => 28, 'unit' => 'px'],
                'selectors'  => ['{{WRAPPER}} .zig-list' => 'column-gap: {{SIZE}}{{UNIT}};'],
            ]
        );

        $this->add_responsive_control(
            'row_gap',
            [
                'label'      => __('فاصلهٔ عمودی', 'zig3d-widgets'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px', 'em', 'rem'],
                'range'      => ['px' => ['min' => 0, 'max' => 120]],
                'default'    => ['size' => 12, 'unit' => 'px'],
                'selectors'  => ['{{WRAPPER}} .zig-list' => 'row-gap: {{SIZE}}{{UNIT}};'],
            ]
        );

        $this->end_controls_section();
    }

    /* =====================================================================
     * چیدمان آیتم (خصوصیات فلکس‌آیتم)
     * =================================================================== */

    private function register_item_layout_section(): void {
        $this->start_controls_section(
            'item_layout_section',
            ['label' => __('چیدمان آیتم', 'zig3d-widgets')]
        );

        $this->add_responsive_control(
            'item_grow',
            [
                'label'       => __('پرکردن فضای خالی', 'zig3d-widgets'),
                'type'        => Controls_Manager::NUMBER,
                'min'         => 0,
                'max'         => 10,
                'description' => __('عدد ۱ یعنی آیتم‌ها فضای باقی‌مانده را بین خودشان تقسیم کنند.', 'zig3d-widgets'),
                'selectors'   => ['{{WRAPPER}} .zig-list__item' => 'flex-grow: {{VALUE}};'],
            ]
        );

        $this->add_responsive_control(
            'item_shrink',
            [
                'label'       => __('اجازهٔ فشرده‌شدن', 'zig3d-widgets'),
                'type'        => Controls_Manager::NUMBER,
                'min'         => 0,
                'max'         => 10,
                'description' => __('صفر یعنی آیتم هرگز از عرض طبیعی‌اش کوچک‌تر نشود.', 'zig3d-widgets'),
                'selectors'   => ['{{WRAPPER}} .zig-list__item' => 'flex-shrink: {{VALUE}};'],
            ]
        );

        $this->add_responsive_control(
            'item_basis',
            [
                'label'      => __('عرض پایه', 'zig3d-widgets'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px', '%', 'em'],
                'range'      => [
                    'px' => ['min' => 0, 'max' => 600],
                    '%'  => ['min' => 0, 'max' => 100],
                ],
                'selectors'  => ['{{WRAPPER}} .zig-list__item' => 'flex-basis: {{SIZE}}{{UNIT}};'],
            ]
        );

        $this->add_responsive_control(
            'item_align_self',
            [
                'label'     => __('تراز فردی آیتم', 'zig3d-widgets'),
                'type'      => Controls_Manager::CHOOSE,
                'options'   => [
                    'flex-start' => ['title' => __('ابتدا', 'zig3d-widgets'), 'icon' => 'eicon-align-start-v'],
                    'center'     => ['title' => __('وسط', 'zig3d-widgets'), 'icon' => 'eicon-align-center-v'],
                    'flex-end'   => ['title' => __('انتها', 'zig3d-widgets'), 'icon' => 'eicon-align-end-v'],
                    'stretch'    => ['title' => __('کشیده', 'zig3d-widgets'), 'icon' => 'eicon-align-stretch-v'],
                ],
                'selectors' => ['{{WRAPPER}} .zig-list__item' => 'align-self: {{VALUE}};'],
            ]
        );

        $this->add_responsive_control(
            'bullet_gap',
            [
                'label'      => __('فاصلهٔ بولت تا عنوان', 'zig3d-widgets'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px', 'em', 'rem'],
                'range'      => ['px' => ['min' => 0, 'max' => 40]],
                'default'    => ['size' => 8, 'unit' => 'px'],
                'separator'  => 'before',
                'selectors'  => ['{{WRAPPER}} .zig-list__item' => 'gap: {{SIZE}}{{UNIT}};'],
            ]
        );

        $this->add_responsive_control(
            'bullet_position',
            [
                'label'     => __('جای بولت', 'zig3d-widgets'),
                'type'      => Controls_Manager::CHOOSE,
                'default'   => 'row',
                'options'   => [
                    'row'         => ['title' => __('ابتدای عنوان', 'zig3d-widgets'), 'icon' => 'eicon-h-align-right'],
                    'row-reverse' => ['title' => __('انتهای عنوان', 'zig3d-widgets'), 'icon' => 'eicon-h-align-left'],
                ],
                'toggle'    => false,
                'selectors' => ['{{WRAPPER}} .zig-list__item' => 'flex-direction: {{VALUE}};'],
            ]
        );

        $this->add_responsive_control(
            'item_cross_align',
            [
                'label'     => __('تراز بولت نسبت به عنوان', 'zig3d-widgets'),
                'type'      => Controls_Manager::CHOOSE,
                'default'   => 'center',
                'options'   => [
                    'flex-start' => ['title' => __('بالا', 'zig3d-widgets'), 'icon' => 'eicon-align-start-v'],
                    'center'     => ['title' => __('وسط', 'zig3d-widgets'), 'icon' => 'eicon-align-center-v'],
                    'baseline'   => ['title' => __('خط پایه', 'zig3d-widgets'), 'icon' => 'eicon-align-stretch-v'],
                ],
                'description' => __('برای عنوان چندخطی، «بالا» بولت را کنار خط اول نگه می‌دارد.', 'zig3d-widgets'),
                'selectors'   => ['{{WRAPPER}} .zig-list__item' => 'align-items: {{VALUE}};'],
            ]
        );

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل بولت
     * =================================================================== */

    private function register_bullet_style_section(): void {
        $this->start_controls_section(
            'bullet_style_section',
            [
                'label' => __('بولت', 'zig3d-widgets'),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'bullet_shape',
            [
                'label'   => __('شکل', 'zig3d-widgets'),
                'type'    => Controls_Manager::SELECT,
                'default' => 'circle',
                'options' => [
                    'circle'  => __('دایره', 'zig3d-widgets'),
                    'rounded' => __('مربع گرد', 'zig3d-widgets'),
                    'square'  => __('مربع', 'zig3d-widgets'),
                    'diamond' => __('لوزی', 'zig3d-widgets'),
                    'dash'    => __('خط تیره', 'zig3d-widgets'),
                    'icon'    => __('آیکون دلخواه', 'zig3d-widgets'),
                ],
            ]
        );

        $this->add_control(
            'bullet_icon',
            [
                'label'     => __('آیکون', 'zig3d-widgets'),
                'type'      => Controls_Manager::ICONS,
                'default'   => ['value' => 'fas fa-check', 'library' => 'fa-solid'],
                'condition' => ['bullet_shape' => 'icon'],
            ]
        );

        $this->add_responsive_control(
            'bullet_size',
            [
                'label'      => __('اندازه', 'zig3d-widgets'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px', 'em', 'rem'],
                'range'      => ['px' => ['min' => 2, 'max' => 48]],
                'default'    => ['size' => 7, 'unit' => 'px'],
                'selectors'  => ['{{WRAPPER}} .zig-list__bullet' => '--zig-bullet-size: {{SIZE}}{{UNIT}};'],
            ]
        );

        $this->add_responsive_control(
            'bullet_offset',
            [
                'label'       => __('جابه‌جایی عمودی', 'zig3d-widgets'),
                'type'        => Controls_Manager::SLIDER,
                'size_units'  => ['px'],
                'range'       => ['px' => ['min' => -20, 'max' => 20]],
                'description' => __('برای هم‌ترازی دقیق بولت با خط اول متن.', 'zig3d-widgets'),
                'selectors'   => ['{{WRAPPER}} .zig-list__bullet' => '--zig-bullet-offset: {{SIZE}}px;'],
            ]
        );

        $this->start_controls_tabs('bullet_tabs');

        $this->start_controls_tab('bullet_tab_normal', ['label' => __('عادی', 'zig3d-widgets')]);
        $this->add_bullet_color_control('bullet_color', '{{WRAPPER}} .zig-list__bullet', '#7C3AED');
        $this->end_controls_tab();

        $this->start_controls_tab('bullet_tab_hover', ['label' => __('هاور', 'zig3d-widgets')]);
        $this->add_bullet_color_control(
            'bullet_color_hover',
            '{{WRAPPER}} .zig-list__item:hover .zig-list__bullet, {{WRAPPER}} .zig-list__item:focus-within .zig-list__bullet'
        );
        $this->end_controls_tab();

        $this->start_controls_tab('bullet_tab_active', ['label' => __('فعال', 'zig3d-widgets')]);
        $this->add_bullet_color_control('bullet_color_active', '{{WRAPPER}} .zig-list__item--active .zig-list__bullet');
        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->end_controls_section();
    }

    private function add_bullet_color_control(string $name, string $selector, string $default = ''): void {
        $this->add_control(
            $name,
            [
                'label'     => __('رنگ', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'default'   => $default,
                'selectors' => [$selector => '--zig-bullet-color: {{VALUE}};'],
            ]
        );
    }

    /* =====================================================================
     * استایل آیتم
     * =================================================================== */

    private function register_item_style_section(): void {
        $this->start_controls_section(
            'item_style_section',
            [
                'label' => __('آیتم', 'zig3d-widgets'),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_box_style_tabs('item', '.zig-list__item', '.zig-list__item');

        $this->add_control(
            'active_heading',
            [
                'label'     => __('حالت فعال', 'zig3d-widgets'),
                'type'      => Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        $this->add_control(
            'active_background',
            [
                'label'     => __('پس‌زمینه', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => ['{{WRAPPER}} .zig-list__item--active' => 'background-color: {{VALUE}};'],
            ]
        );

        $this->add_control(
            'active_border_color',
            [
                'label'     => __('رنگ حاشیه', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => ['{{WRAPPER}} .zig-list__item--active' => 'border-color: {{VALUE}};'],
            ]
        );

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل عنوان
     * =================================================================== */

    private function register_label_style_section(): void {
        $this->start_controls_section(
            'label_style_section',
            [
                'label' => __('عنوان', 'zig3d-widgets'),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'     => 'label_typography',
                'selector' => '{{WRAPPER}} .zig-list__label',
            ]
        );

        $this->start_controls_tabs('label_tabs');

        $this->start_controls_tab('label_tab_normal', ['label' => __('عادی', 'zig3d-widgets')]);
        $this->add_control(
            'label_color',
            [
                'label'     => __('رنگ', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => ['{{WRAPPER}} .zig-list__label' => 'color: {{VALUE}};'],
            ]
        );
        $this->end_controls_tab();

        $this->start_controls_tab('label_tab_hover', ['label' => __('هاور', 'zig3d-widgets')]);
        $this->add_control(
            'label_color_hover',
            [
                'label'     => __('رنگ', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .zig-list__item:hover .zig-list__label, {{WRAPPER}} .zig-list__item:focus-within .zig-list__label' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'label_decoration_hover',
            [
                'label'     => __('خط زیر متن', 'zig3d-widgets'),
                'type'      => Controls_Manager::SELECT,
                'default'   => '',
                'options'   => [
                    ''             => __('بدون تغییر', 'zig3d-widgets'),
                    'underline'    => __('خط زیر', 'zig3d-widgets'),
                    'line-through' => __('خط وسط', 'zig3d-widgets'),
                    'none'         => __('حذف خط', 'zig3d-widgets'),
                ],
                'selectors' => [
                    '{{WRAPPER}} .zig-list__item:hover .zig-list__label, {{WRAPPER}} .zig-list__item:focus-within .zig-list__label' => 'text-decoration: {{VALUE}};',
                ],
            ]
        );
        $this->end_controls_tab();

        $this->start_controls_tab('label_tab_active', ['label' => __('فعال', 'zig3d-widgets')]);
        $this->add_control(
            'label_color_active',
            [
                'label'     => __('رنگ', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => ['{{WRAPPER}} .zig-list__item--active .zig-list__label' => 'color: {{VALUE}};'],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'     => 'label_typography_active',
                'selector' => '{{WRAPPER}} .zig-list__item--active .zig-list__label',
            ]
        );
        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->end_controls_section();
    }

    /* =====================================================================
     * رندر
     * =================================================================== */

    protected function render(): void {
        $settings = $this->get_settings_for_display();
        $items    = $settings['items'] ?? [];

        if (empty($items) || !is_array($items)) {
            return;
        }

        $tag         = Markup::tag($settings['label_tag'] ?? 'span', 'span');
        $shape       = (string) ($settings['bullet_shape'] ?? 'circle');
        $bullet_icon = 'icon' === $shape ? ($settings['bullet_icon'] ?? []) : [];

        // یک بار بیرون از حلقه: تکرارش برای هر آیتم فقط کار اضافه است
        $bullet_class = 'zig-list__bullet zig-list__bullet--' . sanitize_html_class($shape, 'circle');

        $rendered = 0;
        ob_start();

        foreach ($items as $index => $item) {
            $label = (string) ($item['label'] ?? '');

            if (!Markup::filled($label)) {
                continue;
            }

            ++$rendered;

            $item_key = 'item_' . $index;
            $this->add_render_attribute($item_key, 'class', ['zig-list__item', 'elementor-repeater-item-' . ($item['_id'] ?? '')]);

            $is_active = 'yes' === ($item['is_active'] ?? '');

            if ($is_active) {
                $this->add_render_attribute($item_key, 'class', 'zig-list__item--active');
                // aria-current معنای «این مورد، مورد جاری است» را می‌رساند —
                // چیزی که رنگ متفاوت به‌تنهایی منتقل نمی‌کند
                $this->add_render_attribute($item_key, 'aria-current', 'true');
            }

            $label_key  = 'label_' . $index;
            $label_tag  = $tag;
            $has_link   = '' !== trim((string) ($item['link']['url'] ?? ''));

            $this->add_render_attribute($label_key, 'class', 'zig-list__label');

            if ($has_link) {
                $this->add_link_attributes($label_key, $item['link']);
                $label_tag = 'a';
            }

            $repeater_key = $this->get_repeater_setting_key('label', 'items', $index);
            $this->add_inline_editing_attributes($repeater_key, 'none');
            ?>
            <li <?php $this->print_render_attribute_string($item_key); ?>>
                <?php if ('yes' === ($item['show_bullet'] ?? 'yes')) : ?>
                    <span class="<?php echo esc_attr($bullet_class); ?>" aria-hidden="true"><?php
                        if (!empty($bullet_icon['value'])) {
                            \Elementor\Icons_Manager::render_icon($bullet_icon, ['aria-hidden' => 'true']);
                        }
                    ?></span>
                <?php endif; ?>
                <<?php echo $label_tag; // phpcs:ignore WordPress.Security.EscapeOutput -- Markup::tag یا مقدار ثابت 'a' ?> <?php $this->print_render_attribute_string($label_key); ?> <?php $this->print_render_attribute_string($repeater_key); ?>><?php
                    echo Markup::text($label); // phpcs:ignore WordPress.Security.EscapeOutput -- wp_kses با فهرست سفید درون‌خطی
                ?></<?php echo $label_tag; // phpcs:ignore WordPress.Security.EscapeOutput -- Markup::tag یا مقدار ثابت 'a' ?>>
            </li>
            <?php
        }

        $list = (string) ob_get_clean();

        // اگر همهٔ آیتم‌ها خالی بودند، ‎<ul>‎ تهی نباید چاپ شود
        if (0 === $rendered) {
            return;
        }

        // role="list" صریح است چون list-style:none در سافاری معناشناسی فهرست
        // را حذف می‌کند و آن‌وقت صفحه‌خوان «فهرست، ۳ مورد» را اعلام نمی‌کند
        printf('<ul class="zig-list" role="list">%s</ul>', $list); // phpcs:ignore WordPress.Security.EscapeOutput -- بالا ساخته و اسکیپ شده
    }
}
