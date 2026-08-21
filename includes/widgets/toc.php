<?php
namespace Zig3d_Widgets\Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;
use Zig3d_Widgets\Markup;
use Zig3d_Widgets\Plugin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * فهرست مطالب.
 *
 *     div.zig-toc                     ریشه
 *       div.zig-toc__header
 *         *.zig-toc__title            عنوان ویجت (نه یک سرتیتر واقعی صفحه)
 *       div.zig-toc__scroll           ناحیهٔ اسکرول‌شونده، با حداکثر ارتفاع
 *         ul.zig-toc__list            فهرست، خالی روی سرور
 *           li > a.zig-toc__item        (با جاوااسکریپت ساخته می‌شود)
 *             span.zig-toc__label
 *             span.zig-toc__index
 *         p.zig-toc__empty            پیام «چیزی پیدا نشد»، فقط در ادیتور
 *
 * چرا فهرست خالی از سمت سرور رندر می‌شود: سرتیترهایی که این ویجت باید
 * فهرستشان کند متعلق به ویجت‌های دیگرِ همان صفحه‌اند — چیزی که در زمان
 * رندرِ خودِ این ویجت هنوز وجود ندارد (ترتیب رندر ویجت‌ها تضمین‌شده نیست، و
 * محتوا می‌تواند از قالب سراسری یا حلقه بیاید). تنها نقطه‌ای که واقعاً
 * می‌تواند سرتیترهای رندرشدهٔ صفحه را ببیند، مرورگر است — پس برخلافِ بقیهٔ
 * ویجت‌های ساده‌ٔ این افزونه، اینجا اسکریپت تزئینی نیست: بدونِ
 * ‎assets/js/zig3d-toc.js‎ خودِ فهرست خالی می‌ماند.
 */
final class Toc extends Widget_Base {

    use Traits\Box;

    public function get_name(): string {
        return 'zig3d-toc';
    }

    public function get_title(): string {
        return __('فهرست مطالب', 'zig3d-widgets');
    }

    public function get_icon(): string {
        return 'eicon-table-of-contents';
    }

    public function get_categories(): array {
        return [Plugin::CATEGORY];
    }

    public function get_keywords(): array {
        return ['toc', 'table of contents', 'headings', 'فهرست', 'مطالب', 'سرتیتر', 'محتوا'];
    }

    public function get_style_depends(): array {
        return ['zig3d-widgets'];
    }

    public function get_script_depends(): array {
        return ['zig3d-toc'];
    }

    public function has_widget_inner_wrapper(): bool {
        return false;
    }

    protected function register_controls(): void {
        $this->register_general_section();
        $this->register_headings_section();
        $this->register_numbering_section();
        $this->register_behavior_section();
        $this->register_container_style_section();
        $this->register_header_style_section();
        $this->register_list_layout_section();
        $this->register_item_style_section();
        $this->register_index_style_section();
        $this->register_label_style_section();
    }

    /* =====================================================================
     * محتوا › عنوان
     * =================================================================== */

    private function register_general_section(): void {
        $this->start_controls_section(
            'general_section',
            ['label' => __('عنوان ویجت', 'zig3d-widgets')]
        );

        $this->add_control(
            'show_title',
            [
                'label'        => __('نمایش عنوان', 'zig3d-widgets'),
                'type'         => Controls_Manager::SWITCHER,
                'default'      => 'yes',
                'return_value' => 'yes',
            ]
        );

        $this->add_control(
            'title',
            [
                'label'       => __('متن عنوان', 'zig3d-widgets'),
                'type'        => Controls_Manager::TEXT,
                'dynamic'     => ['active' => true],
                'default'     => __('فهرست مطالب', 'zig3d-widgets'),
                'label_block' => true,
                'condition'   => ['show_title' => 'yes'],
            ]
        );

        $this->add_control(
            'title_tag',
            [
                'label'       => __('تگ عنوان', 'zig3d-widgets'),
                'type'        => Controls_Manager::SELECT,
                'default'     => 'div',
                'options'     => Markup::TAGS,
                'condition'   => ['show_title' => 'yes'],
                'description' => __('این عنوان، خودِ یک سرتیتر صفحه نیست؛ برچسبِ ویجت است. برای اکثر قالب‌ها div یا span مناسب‌تر است.', 'zig3d-widgets'),
            ]
        );

        $this->end_controls_section();
    }

    /* =====================================================================
     * محتوا › تگ‌های عنوان
     * =================================================================== */

    private function register_headings_section(): void {
        $this->start_controls_section(
            'headings_section',
            ['label' => __('سرتیترهای صفحه', 'zig3d-widgets')]
        );

        $heading_tags = array_intersect_key(Markup::TAGS, array_flip(['h1', 'h2', 'h3', 'h4', 'h5', 'h6']));

        $this->add_control(
            'heading_tags',
            [
                'label'       => __('تگ‌های عنوان قابل فهرست‌شدن', 'zig3d-widgets'),
                'type'        => Controls_Manager::SELECT2,
                'multiple'    => true,
                'options'     => $heading_tags,
                'default'     => ['h2', 'h3', 'h4'],
                'label_block' => true,
                'description' => __('فقط سرتیترهایی که یکی از این تگ‌ها را دارند وارد فهرست می‌شوند.', 'zig3d-widgets'),
            ]
        );

        $this->add_control(
            'container_selector',
            [
                'label'       => __('سلکتور ناحیهٔ محتوا', 'zig3d-widgets'),
                'type'        => Controls_Manager::TEXT,
                'placeholder' => '.elementor, article, .entry-content',
                'label_block' => true,
                'separator'   => 'before',
                'description' => __('اگر خالی بماند، کل صفحه جست‌وجو می‌شود (به‌جز خودِ این ویجت). برای محدودکردن جست‌وجو به یک بخش خاص، سلکتور CSS آن را بنویسید.', 'zig3d-widgets'),
            ]
        );

        $this->add_control(
            'exclude_selector',
            [
                'label'       => __('سلکتور نادیده‌گرفتن', 'zig3d-widgets'),
                'type'        => Controls_Manager::TEXT,
                'placeholder' => '.zig-toc-ignore',
                'label_block' => true,
                'description' => __('سرتیترهایی که داخل عنصرِ منطبق با این سلکتور باشند، در فهرست نمی‌آیند.', 'zig3d-widgets'),
            ]
        );

        $this->add_control(
            'min_heading_count',
            [
                'label'       => __('حداقل تعداد برای نمایش', 'zig3d-widgets'),
                'type'        => Controls_Manager::NUMBER,
                'min'         => 0,
                'max'         => 20,
                'default'     => 2,
                'description' => __('اگر تعداد سرتیترهای پیداشده کمتر از این عدد باشد، کل ویجت در سایت مخفی می‌شود.', 'zig3d-widgets'),
            ]
        );

        $this->end_controls_section();
    }

    /* =====================================================================
     * محتوا › شماره‌گذاری
     * =================================================================== */

    private function register_numbering_section(): void {
        $this->start_controls_section(
            'numbering_section',
            ['label' => __('شماره‌گذاری', 'zig3d-widgets')]
        );

        $this->add_control(
            'show_numbering',
            [
                'label'        => __('نمایش شماره', 'zig3d-widgets'),
                'type'         => Controls_Manager::SWITCHER,
                'default'      => 'yes',
                'return_value' => 'yes',
            ]
        );

        $this->add_control(
            'numbering_digits',
            [
                'label'     => __('نوع ارقام', 'zig3d-widgets'),
                'type'      => Controls_Manager::SELECT,
                'default'   => 'latin',
                'options'   => [
                    'latin'   => __('لاتین (01)', 'zig3d-widgets'),
                    'persian' => __('فارسی (۰۱)', 'zig3d-widgets'),
                ],
                'condition' => ['show_numbering' => 'yes'],
            ]
        );

        $this->add_control(
            'numbering_pad',
            [
                'label'        => __('صفر ابتدایی', 'zig3d-widgets'),
                'type'         => Controls_Manager::SWITCHER,
                'default'      => 'yes',
                'return_value' => 'yes',
                'description'  => __('مثال: 01 به‌جای 1.', 'zig3d-widgets'),
                'condition'    => ['show_numbering' => 'yes'],
            ]
        );

        $this->end_controls_section();
    }

    /* =====================================================================
     * محتوا › رفتار پیمایش
     * =================================================================== */

    private function register_behavior_section(): void {
        $this->start_controls_section(
            'behavior_section',
            ['label' => __('رفتار پیمایش', 'zig3d-widgets')]
        );

        $this->add_control(
            'highlight_active',
            [
                'label'        => __('تشخیص خودکار بخش در حال مطالعه', 'zig3d-widgets'),
                'type'         => Controls_Manager::SWITCHER,
                'default'      => 'yes',
                'return_value' => 'yes',
                'description'  => __('هنگام اسکرول صفحه، آیتمِ متناظر با سرتیترِ در حال نمایش، استایل «فعال» می‌گیرد.', 'zig3d-widgets'),
            ]
        );

        $this->add_control(
            'smooth_scroll',
            [
                'label'        => __('پیمایش نرم', 'zig3d-widgets'),
                'type'         => Controls_Manager::SWITCHER,
                'default'      => 'yes',
                'return_value' => 'yes',
            ]
        );

        $this->add_control(
            'scroll_offset',
            [
                'label'       => __('فاصله از بالای صفحه', 'zig3d-widgets'),
                'type'        => Controls_Manager::NUMBER,
                'min'         => 0,
                'max'         => 400,
                'default'     => 0,
                'description' => __('برای هدرهای چسبان: همان ارتفاع هدر را اینجا بگذارید تا سرتیتر زیرش پنهان نشود.', 'zig3d-widgets'),
            ]
        );

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل › ظرف
     * =================================================================== */

    private function register_container_style_section(): void {
        $this->start_controls_section(
            'container_style_section',
            [
                'label' => __('ظرف', 'zig3d-widgets'),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_box_style_tabs('toc', '.zig-toc', '.zig-toc');

        $this->add_control(
            'scroll_heading',
            [
                'label'     => __('اسکرول', 'zig3d-widgets'),
                'type'      => Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        $this->add_responsive_control(
            'max_height',
            [
                'label'      => __('حداکثر ارتفاع فهرست', 'zig3d-widgets'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px', 'vh'],
                'range'      => [
                    'px' => ['min' => 80, 'max' => 900],
                    'vh' => ['min' => 10, 'max' => 100],
                ],
                'default'     => ['size' => 320, 'unit' => 'px'],
                'selectors'   => [
                    '{{WRAPPER}} .zig-toc__scroll' => 'max-height: {{SIZE}}{{UNIT}}; overflow-y: auto;',
                ],
                'description' => __('اگر فهرست بلندتر از این ارتفاع باشد، اسکرول می‌خورد.', 'zig3d-widgets'),
            ]
        );

        /*
         * نام‌های متغیر (‎scroll_thumb‎/‎scroll_size‎/‎scroll_pad‎) و متغیرهای
         * CSSِ متناظرشان (‎--zig-scroll-*‎) عمداً همان‌هایی هستند که پنلِ
         * فیلترِ آرشیوِ محصولات به کار می‌برد — یک زبانِ اسکرول برایِ کلِ
         * افزونه، نه اختراعِ یک نامِ تازه برایِ همین یک ویجت.
         */
        $this->add_control(
            'scroll_thumb',
            [
                'label'     => __('رنگ نوار اسکرول', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => ['{{WRAPPER}} .zig-toc__scroll' => '--zig-scroll-thumb: {{VALUE}};'],
            ]
        );

        $this->add_control(
            'scroll_thumb_hover',
            [
                'label'     => __('رنگ نوار اسکرول در هاور', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => ['{{WRAPPER}} .zig-toc__scroll' => '--zig-scroll-thumb-hover: {{VALUE}};'],
            ]
        );

        $this->add_responsive_control(
            'scroll_size',
            [
                'label'      => __('عرض نوار اسکرول', 'zig3d-widgets'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range'      => ['px' => ['min' => 4, 'max' => 24]],
                'selectors'  => ['{{WRAPPER}} .zig-toc__scroll' => '--zig-scroll-size: {{SIZE}}{{UNIT}};'],
            ]
        );

        $this->add_responsive_control(
            'scroll_pad',
            [
                'label'      => __('فاصلهٔ تیغه از لبهٔ نوار', 'zig3d-widgets'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range'      => ['px' => ['min' => 0, 'max' => 8]],
                'selectors'  => ['{{WRAPPER}} .zig-toc__scroll' => '--zig-scroll-pad: {{SIZE}}{{UNIT}};'],
            ]
        );

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل › عنوان ویجت
     * =================================================================== */

    private function register_header_style_section(): void {
        $this->start_controls_section(
            'header_style_section',
            [
                'label'     => __('عنوان ویجت', 'zig3d-widgets'),
                'tab'       => Controls_Manager::TAB_STYLE,
                'condition' => ['show_title' => 'yes'],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'     => 'title_typography',
                'selector' => '{{WRAPPER}} .zig-toc__title',
            ]
        );

        $this->add_control(
            'title_color',
            [
                'label'     => __('رنگ', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => ['{{WRAPPER}} .zig-toc__title' => 'color: {{VALUE}};'],
            ]
        );

        $this->add_responsive_control(
            'header_padding',
            [
                'label'      => __('فاصلهٔ داخلی ردیف عنوان', 'zig3d-widgets'),
                'type'       => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', 'rem', '%'],
                'separator'  => 'before',
                'selectors'  => ['{{WRAPPER}} .zig-toc__header' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
            ]
        );

        $this->add_control(
            'header_border_color',
            [
                'label'     => __('رنگ خط جداکننده', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => ['{{WRAPPER}} .zig-toc__header' => 'border-color: {{VALUE}};'],
            ]
        );

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل › چیدمان فهرست
     * =================================================================== */

    private function register_list_layout_section(): void {
        $this->start_controls_section(
            'list_layout_section',
            [
                'label' => __('چیدمان فهرست', 'zig3d-widgets'),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_responsive_control(
            'list_direction',
            [
                'label'     => __('جهت', 'zig3d-widgets'),
                'type'      => Controls_Manager::CHOOSE,
                'default'   => 'column',
                'options'   => [
                    'column'         => ['title' => __('عمودی', 'zig3d-widgets'), 'icon' => 'eicon-arrow-down'],
                    'column-reverse' => ['title' => __('عمودیِ معکوس', 'zig3d-widgets'), 'icon' => 'eicon-arrow-up'],
                    'row'            => ['title' => __('افقی', 'zig3d-widgets'), 'icon' => 'eicon-arrow-left'],
                    'row-reverse'    => ['title' => __('افقیِ معکوس', 'zig3d-widgets'), 'icon' => 'eicon-arrow-right'],
                ],
                'toggle'    => false,
                'selectors' => ['{{WRAPPER}} .zig-toc__list' => 'flex-direction: {{VALUE}};'],
            ]
        );

        $this->add_responsive_control(
            'list_wrap',
            [
                'label'     => __('شکستن به خط بعد', 'zig3d-widgets'),
                'type'      => Controls_Manager::SELECT,
                'default'   => 'nowrap',
                'options'   => [
                    'nowrap'       => __('نشکند', 'zig3d-widgets'),
                    'wrap'         => __('بشکند', 'zig3d-widgets'),
                    'wrap-reverse' => __('بشکند، معکوس', 'zig3d-widgets'),
                ],
                'selectors' => ['{{WRAPPER}} .zig-toc__list' => 'flex-wrap: {{VALUE}};'],
            ]
        );

        $this->add_responsive_control(
            'list_row_gap',
            [
                'label'      => __('فاصلهٔ بین آیتم‌ها', 'zig3d-widgets'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px', 'em', 'rem'],
                'range'      => ['px' => ['min' => 0, 'max' => 60]],
                'default'    => ['size' => 0, 'unit' => 'px'],
                'selectors'  => ['{{WRAPPER}} .zig-toc__list' => 'row-gap: {{SIZE}}{{UNIT}};'],
            ]
        );

        $this->add_responsive_control(
            'list_column_gap',
            [
                'label'      => __('فاصلهٔ افقی (حالت افقی)', 'zig3d-widgets'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px', 'em', 'rem'],
                'range'      => ['px' => ['min' => 0, 'max' => 80]],
                'default'    => ['size' => 12, 'unit' => 'px'],
                'selectors'  => ['{{WRAPPER}} .zig-toc__list' => 'column-gap: {{SIZE}}{{UNIT}};'],
            ]
        );

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل › آیتم
     * =================================================================== */

    private function register_item_style_section(): void {
        $this->start_controls_section(
            'item_style_section',
            [
                'label' => __('آیتم', 'zig3d-widgets'),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_box_style_tabs('toc_item', '.zig-toc__item', '.zig-toc__item');

        $this->add_control(
            'active_heading',
            [
                'label'     => __('حالت فعال (در حال مطالعه)', 'zig3d-widgets'),
                'type'      => Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        $this->add_control(
            'active_background',
            [
                'label'     => __('پس‌زمینه', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'default'   => '#F3EEFE',
                'selectors' => ['{{WRAPPER}} .zig-toc__item--active' => 'background-color: {{VALUE}};'],
            ]
        );

        $this->add_control(
            'active_border_color',
            [
                'label'     => __('رنگ حاشیه', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => ['{{WRAPPER}} .zig-toc__item--active' => 'border-color: {{VALUE}};'],
            ]
        );

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل › شمارهٔ ردیف
     * =================================================================== */

    private function register_index_style_section(): void {
        $this->start_controls_section(
            'index_style_section',
            [
                'label'     => __('شمارهٔ ردیف', 'zig3d-widgets'),
                'tab'       => Controls_Manager::TAB_STYLE,
                'condition' => ['show_numbering' => 'yes'],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'     => 'index_typography',
                'selector' => '{{WRAPPER}} .zig-toc__index',
            ]
        );

        $this->add_control(
            'index_color',
            [
                'label'     => __('رنگ', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => ['{{WRAPPER}} .zig-toc__index' => 'color: {{VALUE}};'],
            ]
        );

        $this->add_control(
            'index_color_active',
            [
                'label'     => __('رنگ در حالت فعال', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'default'   => '#7C3AED',
                'selectors' => ['{{WRAPPER}} .zig-toc__item--active .zig-toc__index' => 'color: {{VALUE}};'],
            ]
        );

        $this->add_responsive_control(
            'index_min_width',
            [
                'label'      => __('کمترین عرض (هم‌ترازی ستونی)', 'zig3d-widgets'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px', 'em'],
                'range'      => ['px' => ['min' => 0, 'max' => 80]],
                'separator'  => 'before',
                'selectors'  => ['{{WRAPPER}} .zig-toc__index' => 'min-width: {{SIZE}}{{UNIT}};'],
            ]
        );

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل › عنوان آیتم
     * =================================================================== */

    private function register_label_style_section(): void {
        $this->start_controls_section(
            'label_style_section',
            [
                'label' => __('عنوان آیتم', 'zig3d-widgets'),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'     => 'label_typography',
                'selector' => '{{WRAPPER}} .zig-toc__label',
            ]
        );

        $this->start_controls_tabs('label_tabs');

        $this->start_controls_tab('label_tab_normal', ['label' => __('عادی', 'zig3d-widgets')]);
        $this->add_control(
            'label_color',
            [
                'label'     => __('رنگ', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => ['{{WRAPPER}} .zig-toc__label' => 'color: {{VALUE}};'],
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
                    '{{WRAPPER}} .zig-toc__item:hover .zig-toc__label, {{WRAPPER}} .zig-toc__item:focus-visible .zig-toc__label' => 'color: {{VALUE}};',
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
                'default'   => '#5B21B6',
                'selectors' => ['{{WRAPPER}} .zig-toc__item--active .zig-toc__label' => 'color: {{VALUE}};'],
            ]
        );
        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'     => 'label_typography_active',
                'selector' => '{{WRAPPER}} .zig-toc__item--active .zig-toc__label',
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

        $tags = (array) ($settings['heading_tags'] ?? []);
        $tags = array_values(array_filter($tags, static fn($tag): bool => (bool) preg_match('/^h[1-6]$/', (string) $tag)));

        // بدون هیچ تگی برای جست‌وجو، هیچ‌چیز قابل فهرست‌شدن نیست
        if (empty($tags)) {
            return;
        }

        $show_title = 'yes' === ($settings['show_title'] ?? 'yes');
        $title      = (string) ($settings['title'] ?? '');
        $title_tag  = Markup::tag($settings['title_tag'] ?? 'div', 'div');
        $min_count  = max(0, (int) ($settings['min_heading_count'] ?? 2));
        $offset     = max(0, (int) ($settings['scroll_offset'] ?? 0));

        $this->add_render_attribute('root', 'class', 'zig-toc');
        $this->add_render_attribute(
            'root',
            [
                'data-zig-toc'   => '1',
                'data-tags'      => implode(',', $tags),
                'data-scope'     => (string) ($settings['container_selector'] ?? ''),
                'data-exclude'   => (string) ($settings['exclude_selector'] ?? ''),
                'data-min'       => (string) $min_count,
                'data-offset'    => (string) $offset,
                'data-smooth'    => 'yes' === ($settings['smooth_scroll'] ?? 'yes') ? 'yes' : 'no',
                'data-spy'       => 'yes' === ($settings['highlight_active'] ?? 'yes') ? 'yes' : 'no',
                'data-numbering' => 'yes' === ($settings['show_numbering'] ?? 'yes') ? 'yes' : 'no',
                'data-digits'    => 'persian' === ($settings['numbering_digits'] ?? 'latin') ? 'persian' : 'latin',
                'data-pad'       => 'yes' === ($settings['numbering_pad'] ?? 'yes') ? 'yes' : 'no',
            ]
        );

        if ($this->is_editing()) {
            $this->add_render_attribute('root', 'data-editor', '1');
        }
        ?>
        <div <?php $this->print_render_attribute_string('root'); ?>>
            <?php if ($show_title && Markup::filled($title)) : ?>
                <div class="zig-toc__header">
                    <<?php echo $title_tag; // phpcs:ignore WordPress.Security.EscapeOutput -- Markup::tag ?> class="zig-toc__title"><?php
                        echo Markup::text($title); // phpcs:ignore WordPress.Security.EscapeOutput -- wp_kses با فهرست سفید درون‌خطی
                    ?></<?php echo $title_tag; // phpcs:ignore WordPress.Security.EscapeOutput -- Markup::tag ?>>
                </div>
            <?php endif; ?>
            <div class="zig-toc__scroll">
                <ul class="zig-toc__list" role="list"></ul>
                <p class="zig-toc__empty" hidden><?php echo esc_html__('با تنظیمات فعلی سرتیتری روی صفحه پیدا نشد. این پیام فقط داخل ادیتور دیده می‌شود.', 'zig3d-widgets'); ?></p>
            </div>
        </div>
        <?php
    }

    private function is_editing(): bool {
        return class_exists('\Elementor\Plugin')
            && isset(\Elementor\Plugin::$instance->editor)
            && \Elementor\Plugin::$instance->editor->is_edit_mode();
    }
}
