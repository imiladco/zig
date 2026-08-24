<?php
namespace Zig3d_Widgets\Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Text_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;
use Zig3d_Widgets\Markup;
use Zig3d_Widgets\Plugin;
use Zig3d_Widgets\Price;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * توضیحات — یک بخشِ متنیِ عنوان‌دار، از هر جایی که «توضیح»ِ محتوایِ جاری
 * ممکن است باشد: توضیحاتِ محصولِ ووکامرس، محتوایِ نوشته، خلاصهٔ نوشته، یا
 * یک فیلدِ سفارشیِ متا (هر پست‌تایپی — JetEngine یا هرچی).
 *
 * جانشینِ ویجتیِ بخشِ «معرفیِ محصول»یِ shortcodeِ قدیمیِ
 * ‎product_description‎ (که فقط رویِ صفحهٔ محصول و فقط رویِ توضیحاتِ
 * ووکامرس کار می‌کرد)، حالا به‌عنوانِ ویجتِ المنتوریِ عمومی — قابلِ
 * جاگذاری در هر Loop/قالب/پستی، نه فقط محصول.
 *
 *     div.zig-description
 *       header.zig-description__head        عنوان + آیکونِ اختیاری
 *         img.zig-description__icon
 *         h2.zig-description__heading
 *       div.zig-description__body            HTMLِ فیلترشده/پاک‌سازی‌شده
 *
 * چرا منبعِ «خودکار» پیش‌فرض است: اکثرِ استفاده‌ها یا رویِ صفحهٔ محصول‌اند
 * یا رویِ نوشته/برگه — و در هر دو حالت یک منبعِ بدیهی وجود دارد. کاربری
 * که می‌خواهد صراحتاً یک منبعِ دیگر (مثلاً یک فیلدِ سفارشی) را نشان دهد،
 * می‌تواند صریحاً انتخاب کند.
 */
final class Description extends Widget_Base {

    use Traits\Box;

    public function get_name(): string {
        return 'zig3d-description';
    }

    public function get_title(): string {
        return __('توضیحات', 'zig3d-widgets');
    }

    public function get_icon(): string {
        return 'eicon-text';
    }

    public function get_categories(): array {
        return [Plugin::CATEGORY];
    }

    public function get_keywords(): array {
        return ['description', 'content', 'excerpt', 'toc', 'توضیحات', 'محتوا', 'معرفی'];
    }

    public function get_style_depends(): array {
        return ['zig3d-widgets'];
    }

    public function has_widget_inner_wrapper(): bool {
        return false;
    }

    protected function register_controls(): void {
        $this->register_source_section();
        $this->register_heading_section();
        $this->register_container_style_section();
        $this->register_heading_style_section();
        $this->register_text_style_section();
        $this->register_inner_headings_style_section();
        $this->register_links_style_section();
        $this->register_list_style_section();
        $this->register_quote_style_section();
        $this->register_table_style_section();
    }

    /* =====================================================================
     * منبعِ محتوا
     * =================================================================== */

    private function register_source_section(): void {
        $this->start_controls_section(
            'source_section',
            ['label' => __('منبعِ محتوا', 'zig3d-widgets')]
        );

        $this->add_control(
            'source',
            [
                'label'   => __('منبع', 'zig3d-widgets'),
                'type'    => Controls_Manager::SELECT,
                'options' => [
                    'auto'                => __('خودکار (توضیحاتِ محصول، وگرنه محتوایِ نوشته)', 'zig3d-widgets'),
                    'product_description' => __('توضیحاتِ محصول (ووکامرس)', 'zig3d-widgets'),
                    'post_content'        => __('محتوایِ نوشته', 'zig3d-widgets'),
                    'post_excerpt'        => __('خلاصهٔ نوشته', 'zig3d-widgets'),
                    'custom_field'        => __('فیلدِ سفارشی (متا)', 'zig3d-widgets'),
                ],
                'default' => 'auto',
            ]
        );

        $this->add_control(
            'custom_field_key',
            [
                'label'       => __('کلیدِ فیلدِ متا', 'zig3d-widgets'),
                'type'        => Controls_Manager::TEXT,
                'default'     => '',
                'condition'   => ['source' => 'custom_field'],
                'description' => __('نامِ فیلدِ سفارشیِ رویِ پستِ جاری (مثلاً یک فیلدِ متن/WYSIWYGِ JetEngine).', 'zig3d-widgets'),
            ]
        );

        $this->add_control(
            'product_id',
            [
                'label'       => __('شناسهٔ محصول (دستی)', 'zig3d-widgets'),
                'type'        => Controls_Manager::NUMBER,
                'min'         => 0,
                'default'     => 0,
                'condition'   => ['source' => ['auto', 'product_description']],
                'description' => __('خالی یا صفر یعنی محصولِ جاری (یا محصولِ حلقه، در Loop Grid).', 'zig3d-widgets'),
            ]
        );

        $this->add_control(
            'render_filters',
            [
                'label'       => __('اجرایِ فیلترهایِ محتوا', 'zig3d-widgets'),
                'type'        => Controls_Manager::SWITCHER,
                'default'     => 'yes',
                'description' => __('شورت‌کد/امبد/پاراگراف‌بندیِ خودکار را رویِ متن اجرا می‌کند (فیلترِ the_content).', 'zig3d-widgets'),
            ]
        );

        $this->end_controls_section();
    }

    /* =====================================================================
     * عنوان
     * =================================================================== */

    private function register_heading_section(): void {
        $this->start_controls_section(
            'heading_section',
            ['label' => __('عنوان', 'zig3d-widgets')]
        );

        $this->add_control('show_heading', ['label' => __('نمایشِ عنوان', 'zig3d-widgets'), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes']);
        $this->add_control('heading_text', ['label' => __('متنِ عنوان', 'zig3d-widgets'), 'type' => Controls_Manager::TEXT, 'default' => __('توضیحات', 'zig3d-widgets'), 'condition' => ['show_heading' => 'yes']]);
        $this->add_control('append_title', ['label' => __('افزودنِ عنوانِ نوشته/محصول', 'zig3d-widgets'), 'type' => Controls_Manager::SWITCHER, 'default' => '', 'condition' => ['show_heading' => 'yes'], 'description' => __('مثلاً «توضیحات» می‌شود «توضیحاتِ نامِ‌محصول».', 'zig3d-widgets')]);
        $this->add_control('show_icon', ['label' => __('نمایشِ آیکون', 'zig3d-widgets'), 'type' => Controls_Manager::SWITCHER, 'default' => '', 'condition' => ['show_heading' => 'yes']]);
        $this->add_control('heading_icon', ['label' => __('تصویرِ آیکون', 'zig3d-widgets'), 'type' => Controls_Manager::MEDIA, 'default' => ['url' => ''], 'condition' => ['show_heading' => 'yes', 'show_icon' => 'yes']]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل
     * =================================================================== */

    private function register_container_style_section(): void {
        $this->start_controls_section(
            'container_style_section',
            ['label' => __('کادر', 'zig3d-widgets'), 'tab' => Controls_Manager::TAB_STYLE]
        );

        $this->add_box_style_tabs('container', '.zig-description', '.zig-description');

        $this->end_controls_section();
    }

    private function register_heading_style_section(): void {
        $this->start_controls_section(
            'heading_style_section',
            ['label' => __('عنوان', 'zig3d-widgets'), 'tab' => Controls_Manager::TAB_STYLE, 'condition' => ['show_heading' => 'yes']]
        );

        $this->add_group_control(Group_Control_Typography::get_type(), ['name' => 'heading_typography', 'selector' => '{{WRAPPER}} .zig-description__heading']);
        $this->add_control('heading_color', ['label' => __('رنگِ عنوان', 'zig3d-widgets'), 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .zig-description__heading' => 'color: {{VALUE}};']]);
        $this->add_responsive_control('icon_size', ['label' => __('اندازهٔ آیکون', 'zig3d-widgets'), 'type' => Controls_Manager::SLIDER, 'size_units' => ['px'], 'range' => ['px' => ['min' => 12, 'max' => 64]], 'default' => ['size' => 24, 'unit' => 'px'], 'condition' => ['show_icon' => 'yes'], 'selectors' => ['{{WRAPPER}} .zig-description__icon' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};']]);
        $this->add_responsive_control('heading_gap', ['label' => __('فاصلهٔ آیکون تا متنِ عنوان', 'zig3d-widgets'), 'type' => Controls_Manager::SLIDER, 'range' => ['px' => ['min' => 0, 'max' => 40]], 'condition' => ['show_icon' => 'yes'], 'selectors' => ['{{WRAPPER}} .zig-description__head' => 'gap: {{SIZE}}{{UNIT}};']]);
        $this->add_responsive_control('heading_spacing', ['label' => __('فاصلهٔ عنوان تا متن', 'zig3d-widgets'), 'type' => Controls_Manager::SLIDER, 'range' => ['px' => ['min' => 0, 'max' => 80]], 'default' => ['size' => 16, 'unit' => 'px'], 'selectors' => ['{{WRAPPER}} .zig-description__head' => 'margin-block-end: {{SIZE}}{{UNIT}};']]);

        $this->end_controls_section();
    }

    /**
     * متنِ بدنه از ‎the_content‎/فیلدِ سفارشی می‌آید — یعنی هر تگی از ‎<p>‎ تا
     * ‎<h3>‎ تا ‎<table>‎ ممکن است داخلش باشد. این بخش پیش‌فرضِ کلیِ متن و
     * ظاهرِ خودِ باکسِ متن را نگه می‌دارد؛ تیتر/لینک/فهرست/جدول هرکدام
     * بخشِ استایلِ اختصاصیِ خودشان را دارند — همان سطحِ جزئیاتی که در
     * استایلِ سایتِ الماس‌آرا برایِ این بخش جداگانه تنظیم شده بود.
     */
    private function register_text_style_section(): void {
        $this->start_controls_section(
            'text_style_section',
            ['label' => __('متن', 'zig3d-widgets'), 'tab' => Controls_Manager::TAB_STYLE]
        );

        $this->add_group_control(Group_Control_Typography::get_type(), ['name' => 'body_typography', 'selector' => '{{WRAPPER}} .zig-description__body']);
        $this->add_responsive_control('paragraph_spacing', ['label' => __('فاصلهٔ بین پاراگراف‌ها', 'zig3d-widgets'), 'type' => Controls_Manager::SLIDER, 'range' => ['px' => ['min' => 0, 'max' => 48]], 'selectors' => ['{{WRAPPER}} .zig-description__body' => '--zig-description-p-gap: {{SIZE}}{{UNIT}};']]);
        $this->add_responsive_control('text_align', [
            'label'     => __('چینشِ متن', 'zig3d-widgets'),
            'type'      => Controls_Manager::CHOOSE,
            'options'   => [
                'right'   => ['title' => __('راست', 'zig3d-widgets'), 'icon' => 'eicon-text-align-right'],
                'center'  => ['title' => __('وسط', 'zig3d-widgets'), 'icon' => 'eicon-text-align-center'],
                'left'    => ['title' => __('چپ', 'zig3d-widgets'), 'icon' => 'eicon-text-align-left'],
                'justify' => ['title' => __('بلوکی', 'zig3d-widgets'), 'icon' => 'eicon-text-align-justify'],
            ],
            'selectors' => ['{{WRAPPER}} .zig-description__body' => 'text-align: {{VALUE}};'],
        ]);
        $this->add_responsive_control('body_padding', ['label' => __('پدینگ', 'zig3d-widgets'), 'type' => Controls_Manager::DIMENSIONS, 'size_units' => ['px', 'em', '%'], 'selectors' => ['{{WRAPPER}} .zig-description__body' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};']]);

        $this->start_controls_tabs('body_color_tabs');
        $this->start_controls_tab('body_color_normal_tab', ['label' => __('عادی', 'zig3d-widgets')]);
        $this->add_control('body_color', ['label' => __('رنگ', 'zig3d-widgets'), 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .zig-description__body' => 'color: {{VALUE}};']]);
        $this->end_controls_tab();
        $this->start_controls_tab('body_color_hover_tab', ['label' => __('هاور', 'zig3d-widgets')]);
        $this->add_control('body_hover_color', ['label' => __('رنگِ هاور', 'zig3d-widgets'), 'type' => Controls_Manager::COLOR, 'description' => __('وقتی موس رویِ کلِ کادر باشد.', 'zig3d-widgets'), 'selectors' => ['{{WRAPPER}}:hover .zig-description__body' => 'color: {{VALUE}};']]);
        $this->end_controls_tab();
        $this->end_controls_tabs();

        $this->add_group_control(Group_Control_Text_Shadow::get_type(), ['name' => 'body_text_shadow', 'label' => __('سایهٔ متن', 'zig3d-widgets'), 'selector' => '{{WRAPPER}} .zig-description__body']);

        $this->end_controls_section();
    }

    /**
     * تیترهایِ *داخلِ* بدنه (h2 تا h6 — نه عنوانِ خودِ ویجت که بخشِ جداگانه
     * دارد). h1 عمداً نیست: داخلِ یک بخشِ توضیحات، h1 معمولاً یعنی خودِ
     * عنوانِ صفحه تکرار شده. تایپوگرافی/رنگ مشترک‌اند؛ فاصلهٔ بالا/پایین
     * هرکدام مستقل، چون در محتوایِ واقعی h2 و h4 به‌ندرت فاصلهٔ یکسان
     * می‌خواهند.
     */
    private function register_inner_headings_style_section(): void {
        $this->start_controls_section(
            'inner_headings_style_section',
            ['label' => __('عنوان‌هایِ متن (h2–h6)', 'zig3d-widgets'), 'tab' => Controls_Manager::TAB_STYLE]
        );

        $inner_headings = '{{WRAPPER}} .zig-description__body h2, {{WRAPPER}} .zig-description__body h3, {{WRAPPER}} .zig-description__body h4, {{WRAPPER}} .zig-description__body h5, {{WRAPPER}} .zig-description__body h6';

        $this->add_group_control(Group_Control_Typography::get_type(), ['name' => 'inner_heading_typography', 'label' => __('تایپوگرافی (همهٔ عنوان‌ها)', 'zig3d-widgets'), 'selector' => $inner_headings]);
        $this->add_control('inner_heading_color', ['label' => __('رنگ (همهٔ عنوان‌ها)', 'zig3d-widgets'), 'type' => Controls_Manager::COLOR, 'selectors' => [$inner_headings => 'color: {{VALUE}};']]);

        $this->add_control('inner_heading_spacing_notice', ['type' => Controls_Manager::RAW_HTML, 'raw' => __('فاصلهٔ بالا و پایینِ هر عنوان را جداگانه تنظیم کنید:', 'zig3d-widgets'), 'content_classes' => 'elementor-panel-alert elementor-panel-alert-info', 'separator' => 'before']);

        foreach (['h2', 'h3', 'h4', 'h5', 'h6'] as $tag) {
            $this->add_responsive_control(
                $tag . '_margin',
                [
                    'label'      => sprintf(__('فاصلهٔ %s (بالا/پایین)', 'zig3d-widgets'), strtoupper($tag)),
                    'type'       => Controls_Manager::DIMENSIONS,
                    'size_units' => ['px', 'em'],
                    'default'    => ['top' => '20', 'right' => '0', 'bottom' => '0', 'left' => '0', 'unit' => 'px'],
                    'selectors'  => ['{{WRAPPER}} .zig-description__body ' . $tag => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
                ]
            );
        }

        $this->end_controls_section();
    }

    private function register_links_style_section(): void {
        $this->start_controls_section(
            'links_bold_style_section',
            ['label' => __('لینک‌ها و بولد', 'zig3d-widgets'), 'tab' => Controls_Manager::TAB_STYLE]
        );

        $underline_options = [
            ''          => __('پیش‌فرض', 'zig3d-widgets'),
            'none'      => __('بدونِ خط', 'zig3d-widgets'),
            'underline' => __('همیشه', 'zig3d-widgets'),
        ];

        $this->add_control('links_heading', ['label' => __('لینک‌ها', 'zig3d-widgets'), 'type' => Controls_Manager::HEADING]);
        $this->add_group_control(Group_Control_Typography::get_type(), ['name' => 'link_typography', 'selector' => '{{WRAPPER}} .zig-description__body a']);
        $this->start_controls_tabs('link_state_tabs');
        $this->start_controls_tab('link_normal_tab', ['label' => __('عادی', 'zig3d-widgets')]);
        $this->add_control('link_color', ['label' => __('رنگِ لینک', 'zig3d-widgets'), 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .zig-description__body a' => 'color: {{VALUE}};']]);
        $this->add_control('link_underline', ['label' => __('خطِ زیر', 'zig3d-widgets'), 'type' => Controls_Manager::SELECT, 'options' => $underline_options, 'default' => '', 'selectors' => ['{{WRAPPER}} .zig-description__body a' => 'text-decoration-line: {{VALUE}};']]);
        $this->end_controls_tab();
        $this->start_controls_tab('link_hover_tab', ['label' => __('هاور', 'zig3d-widgets')]);
        $this->add_control('link_hover_color', ['label' => __('رنگِ هاور', 'zig3d-widgets'), 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .zig-description__body a:hover' => 'color: {{VALUE}};']]);
        $this->add_control('link_hover_underline', ['label' => __('خطِ زیر در هاور', 'zig3d-widgets'), 'type' => Controls_Manager::SELECT, 'options' => $underline_options, 'default' => '', 'selectors' => ['{{WRAPPER}} .zig-description__body a:hover' => 'text-decoration-line: {{VALUE}};']]);
        $this->end_controls_tab();
        $this->end_controls_tabs();

        $this->add_control('bold_heading', ['label' => __('متنِ بولد (strong/b)', 'zig3d-widgets'), 'type' => Controls_Manager::HEADING, 'separator' => 'before']);
        $this->add_control('bold_color', ['label' => __('رنگ', 'zig3d-widgets'), 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .zig-description__body strong, {{WRAPPER}} .zig-description__body b' => 'color: {{VALUE}};']]);
        $this->add_control('bold_font_weight', ['label' => __('وزنِ فونت', 'zig3d-widgets'), 'type' => Controls_Manager::SELECT, 'options' => ['' => __('پیش‌فرض', 'zig3d-widgets'), '500' => '500', '600' => '600', '700' => '700', '800' => '800', '900' => '900'], 'default' => '', 'selectors' => ['{{WRAPPER}} .zig-description__body strong, {{WRAPPER}} .zig-description__body b' => 'font-weight: {{VALUE}};']]);

        $this->end_controls_section();
    }

    private function register_list_style_section(): void {
        $this->start_controls_section(
            'list_style_section',
            ['label' => __('لیست‌ها (بولت‌ها)', 'zig3d-widgets'), 'tab' => Controls_Manager::TAB_STYLE]
        );

        $lists = '{{WRAPPER}} .zig-description__body ul, {{WRAPPER}} .zig-description__body ol';

        $this->add_control('list_style_type', [
            'label'     => __('شکلِ بولت', 'zig3d-widgets'),
            'type'      => Controls_Manager::SELECT,
            'options'   => [
                ''        => __('پیش‌فرض', 'zig3d-widgets'),
                'disc'    => __('دایرهٔ پر', 'zig3d-widgets'),
                'circle'  => __('دایرهٔ توخالی', 'zig3d-widgets'),
                'square'  => __('مربع', 'zig3d-widgets'),
                'decimal' => __('عدد', 'zig3d-widgets'),
                'none'    => __('بدونِ بولت', 'zig3d-widgets'),
            ],
            'default'   => '',
            'selectors' => [$lists => 'list-style-type: {{VALUE}};'],
        ]);
        $this->add_control('marker_color', ['label' => __('رنگِ بولت/شماره', 'zig3d-widgets'), 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .zig-description__body li::marker' => 'color: {{VALUE}};']]);
        $this->add_responsive_control('marker_size', ['label' => __('اندازهٔ بولت/شماره', 'zig3d-widgets'), 'type' => Controls_Manager::SLIDER, 'size_units' => ['px'], 'range' => ['px' => ['min' => 8, 'max' => 32]], 'default' => ['size' => 16, 'unit' => 'px'], 'selectors' => ['{{WRAPPER}} .zig-description__body li::marker' => 'font-size: {{SIZE}}{{UNIT}};']]);
        $this->add_responsive_control('list_indent', ['label' => __('تورفتگیِ لیست', 'zig3d-widgets'), 'type' => Controls_Manager::SLIDER, 'range' => ['px' => ['min' => 0, 'max' => 60]], 'default' => ['size' => 24, 'unit' => 'px'], 'selectors' => [$lists => 'padding-inline-start: {{SIZE}}{{UNIT}};']]);
        $this->add_responsive_control('list_item_spacing', ['label' => __('فاصلهٔ بین آیتم‌ها', 'zig3d-widgets'), 'type' => Controls_Manager::SLIDER, 'range' => ['px' => ['min' => 0, 'max' => 32]], 'default' => ['size' => 6, 'unit' => 'px'], 'selectors' => ['{{WRAPPER}} .zig-description__body' => '--zig-description-li-gap: {{SIZE}}{{UNIT}};']]);
        $this->add_responsive_control('list_spacing', ['label' => __('فاصلهٔ لیست از متنِ اطراف', 'zig3d-widgets'), 'type' => Controls_Manager::SLIDER, 'range' => ['px' => ['min' => 0, 'max' => 60]], 'selectors' => [$lists => 'margin-block: {{SIZE}}{{UNIT}};']]);
        $this->add_control('list_color', ['label' => __('رنگِ متنِ آیتم‌ها', 'zig3d-widgets'), 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .zig-description__body li' => 'color: {{VALUE}};']]);
        $this->add_group_control(Group_Control_Typography::get_type(), ['name' => 'list_typography', 'label' => __('تایپوگرافیِ آیتم‌ها', 'zig3d-widgets'), 'selector' => '{{WRAPPER}} .zig-description__body li']);

        $this->end_controls_section();
    }

    private function register_quote_style_section(): void {
        $this->start_controls_section(
            'quote_style_section',
            ['label' => __('نقل‌قول', 'zig3d-widgets'), 'tab' => Controls_Manager::TAB_STYLE]
        );

        $this->add_group_control(Group_Control_Typography::get_type(), ['name' => 'quote_typography', 'selector' => '{{WRAPPER}} .zig-description__body blockquote']);
        $this->add_control('quote_color', ['label' => __('رنگِ متنِ نقل‌قول', 'zig3d-widgets'), 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .zig-description__body blockquote' => 'color: {{VALUE}};']]);
        $this->add_control('quote_background', ['label' => __('پس‌زمینه', 'zig3d-widgets'), 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .zig-description__body blockquote' => 'background-color: {{VALUE}};']]);
        $this->add_control('quote_border_color', ['label' => __('رنگِ خطِ کنارِ نقل‌قول', 'zig3d-widgets'), 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .zig-description__body blockquote' => 'border-inline-start-color: {{VALUE}};']]);
        $this->add_responsive_control('quote_border_width', ['label' => __('ضخامتِ خطِ کناره', 'zig3d-widgets'), 'type' => Controls_Manager::SLIDER, 'range' => ['px' => ['min' => 0, 'max' => 10]], 'selectors' => ['{{WRAPPER}} .zig-description__body blockquote' => 'border-inline-start-width: {{SIZE}}{{UNIT}};']]);
        $this->add_responsive_control('quote_padding', ['label' => __('پدینگ', 'zig3d-widgets'), 'type' => Controls_Manager::DIMENSIONS, 'size_units' => ['px', 'em'], 'selectors' => ['{{WRAPPER}} .zig-description__body blockquote' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};']]);

        $this->end_controls_section();
    }

    /**
     * جدول ممکن است از یک بلوکِ گوتنبرگ یا شورت‌کد در ‎the_content‎ بیاید —
     * افزونه خودش هیچ جدولی نمی‌سازد، فقط قابل‌استایل نگه‌اش می‌دارد.
     */
    private function register_table_style_section(): void {
        $this->start_controls_section(
            'table_style_section',
            ['label' => __('جدول‌ها', 'zig3d-widgets'), 'tab' => Controls_Manager::TAB_STYLE]
        );

        $cells = '{{WRAPPER}} .zig-description__body th, {{WRAPPER}} .zig-description__body td';

        $this->add_control('table_border_color', ['label' => __('رنگِ خطوطِ جدول', 'zig3d-widgets'), 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .zig-description__body table' => 'border-color: {{VALUE}};', $cells => 'border-color: {{VALUE}};']]);
        $this->add_responsive_control('table_border_width', ['label' => __('ضخامتِ خطوط', 'zig3d-widgets'), 'type' => Controls_Manager::SLIDER, 'size_units' => ['px'], 'range' => ['px' => ['min' => 0, 'max' => 6]], 'selectors' => ['{{WRAPPER}} .zig-description__body table' => 'border-width: {{SIZE}}{{UNIT}};', $cells => 'border-width: {{SIZE}}{{UNIT}};']]);
        $this->add_responsive_control('table_radius', ['label' => __('رادیوسِ جدول', 'zig3d-widgets'), 'type' => Controls_Manager::SLIDER, 'range' => ['px' => ['min' => 0, 'max' => 24]], 'selectors' => ['{{WRAPPER}} .zig-description__body table' => 'border-radius: {{SIZE}}{{UNIT}};']]);
        $this->add_responsive_control('cell_padding', ['label' => __('پدینگِ سلول‌ها', 'zig3d-widgets'), 'type' => Controls_Manager::DIMENSIONS, 'size_units' => ['px', 'em'], 'selectors' => [$cells => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};']]);
        $this->add_responsive_control('cell_text_align', [
            'label'     => __('چینشِ متنِ سلول‌ها', 'zig3d-widgets'),
            'type'      => Controls_Manager::CHOOSE,
            'options'   => [
                'right'  => ['title' => __('راست', 'zig3d-widgets'), 'icon' => 'eicon-text-align-right'],
                'center' => ['title' => __('وسط', 'zig3d-widgets'), 'icon' => 'eicon-text-align-center'],
                'left'   => ['title' => __('چپ', 'zig3d-widgets'), 'icon' => 'eicon-text-align-left'],
            ],
            'selectors' => [$cells => 'text-align: {{VALUE}};'],
        ]);
        $this->add_responsive_control('table_spacing', ['label' => __('فاصلهٔ جدول از متنِ اطراف', 'zig3d-widgets'), 'type' => Controls_Manager::SLIDER, 'range' => ['px' => ['min' => 0, 'max' => 60]], 'selectors' => ['{{WRAPPER}} .zig-description__body table' => 'margin-block: {{SIZE}}{{UNIT}};']]);

        $this->add_control('thead_heading', ['label' => __('سطرِ عنوان (th)', 'zig3d-widgets'), 'type' => Controls_Manager::HEADING, 'separator' => 'before']);
        $this->add_control('th_background', ['label' => __('پس‌زمینه', 'zig3d-widgets'), 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .zig-description__body th' => 'background-color: {{VALUE}};']]);
        $this->add_control('th_color', ['label' => __('رنگِ متن', 'zig3d-widgets'), 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .zig-description__body th' => 'color: {{VALUE}};']]);
        $this->add_group_control(Group_Control_Typography::get_type(), ['name' => 'th_typography', 'selector' => '{{WRAPPER}} .zig-description__body th']);

        $this->add_control('tbody_heading', ['label' => __('سلول‌ها (td)', 'zig3d-widgets'), 'type' => Controls_Manager::HEADING, 'separator' => 'before']);
        $this->add_control('td_background', ['label' => __('پس‌زمینه', 'zig3d-widgets'), 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .zig-description__body td' => 'background-color: {{VALUE}};']]);
        $this->add_control('td_zebra_background', ['label' => __('پس‌زمینهٔ سطرهایِ زوج (زبرا)', 'zig3d-widgets'), 'type' => Controls_Manager::COLOR, 'description' => __('برایِ خوانایی جدول‌هایِ بلند، سطرها یکی‌درمیان این رنگ را می‌گیرند.', 'zig3d-widgets'), 'selectors' => ['{{WRAPPER}} .zig-description__body tbody tr:nth-child(even) td' => 'background-color: {{VALUE}};']]);
        $this->add_control('td_color', ['label' => __('رنگِ متن', 'zig3d-widgets'), 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .zig-description__body td' => 'color: {{VALUE}};']]);
        $this->add_group_control(Group_Control_Typography::get_type(), ['name' => 'td_typography', 'selector' => '{{WRAPPER}} .zig-description__body td']);

        $this->end_controls_section();
    }

    /* =====================================================================
     * رندر
     * =================================================================== */

    protected function render(): void {
        $settings = $this->get_settings_for_display();
        $resolved = $this->resolve($settings);

        if (null === $resolved) {
            $this->notice(__('محتوایی برایِ نمایش پیدا نشد — منبع را بررسی کنید یا ویجت را داخلِ صفحه/قالبِ محصول یا نوشته بگذارید.', 'zig3d-widgets'));

            return;
        }

        $this->add_render_attribute('root', 'class', 'zig-description');

        printf('<div %s>', $this->get_render_attribute_string('root'));

        if ('yes' === ($settings['show_heading'] ?? 'yes')) {
            $this->render_heading($settings, $resolved);
        }

        printf('<div class="zig-description__body">%s</div>', $resolved['html']);

        echo '</div>';
    }

    /** @param array{html:string,title:string} $resolved */
    private function render_heading(array $settings, array $resolved): void {
        $text = trim((string) ($settings['heading_text'] ?? ''));

        if ('yes' === ($settings['append_title'] ?? '') && Markup::filled($resolved['title'])) {
            $text = '' !== $text ? $text . ' ' . $resolved['title'] : $resolved['title'];
        }

        if ('' === $text && '' === ($this->icon_html($settings))) {
            return;
        }

        echo '<header class="zig-description__head">';
        echo $this->icon_html($settings); // phpcs:ignore WordPress.Security.EscapeOutput -- از esc_url/esc_attr در icon_html ساخته می‌شود
        if ('' !== $text) {
            printf('<h2 class="zig-description__heading">%s</h2>', esc_html($text));
        }
        echo '</header>';
    }

    private function icon_html(array $settings): string {
        if ('yes' !== ($settings['show_icon'] ?? '')) {
            return '';
        }

        $url = (string) ($settings['heading_icon']['url'] ?? '');

        if (!Markup::filled($url)) {
            return '';
        }

        return sprintf('<img class="zig-description__icon" src="%s" alt="" />', esc_url($url));
    }

    /**
     * @return array{html:string,title:string}|null
     */
    private function resolve(array $settings): ?array {
        $source = (string) ($settings['source'] ?? 'auto');
        $render_filters = 'yes' === ($settings['render_filters'] ?? 'yes');

        if ('auto' === $source) {
            $source = null !== Price::resolve(absint($settings['product_id'] ?? 0)) ? 'product_description' : 'post_content';
        }

        if ('product_description' === $source) {
            $product = Price::resolve(absint($settings['product_id'] ?? 0));

            if (!$product) {
                return null;
            }

            $raw = (string) $product->get_description();

            return $this->finish($raw, (string) $product->get_name(), $render_filters);
        }

        $post_id = $this->current_post_id();

        if ($post_id <= 0) {
            return null;
        }

        $title = (string) get_the_title($post_id);

        if ('post_excerpt' === $source) {
            $raw = (string) get_the_excerpt($post_id);

            return $this->finish($raw, $title, $render_filters);
        }

        if ('custom_field' === $source) {
            $key = trim((string) ($settings['custom_field_key'] ?? ''));

            if ('' === $key) {
                return null;
            }

            $raw = get_post_meta($post_id, $key, true);

            return is_array($raw) ? null : $this->finish((string) $raw, $title, $render_filters);
        }

        // 'post_content' — پیش‌فرض/fallback.
        $raw = (string) get_post_field('post_content', $post_id);

        return $this->finish($raw, $title, $render_filters);
    }

    /**
     * قفلِ ورودِ دوباره — نگاه کنید به ‎apply_content_filters()‎.
     */
    private static bool $applying_content_filters = false;

    /** @return array{html:string,title:string}|null */
    private function finish(string $raw, string $title, bool $render_filters): ?array {
        if (!Markup::filled($raw)) {
            return null;
        }

        $html = $render_filters ? self::apply_content_filters($raw) : wpautop($raw);

        return ['html' => wp_kses_post($html), 'title' => $title];
    }

    /**
     * ‎the_content‎ را اجرا می‌کند، ولی *بدونِ* رندرِ بازگشتیِ سندِ المنتور.
     *
     * المنتور ‎Frontend::apply_builder_in_content()‎ را رویِ ‎the_content‎
     * می‌نشاند. آن متد، اگر پستِ جاری دادهٔ المنتوری داشته باشد، یک سندِ
     * *کامل* را از نو رندر می‌کند. یعنی هر بار که این ویجت محتوایش را از
     * فیلترها می‌گذراند، عملاً کلِ یک صفحهٔ المنتوری دوباره ساخته می‌شود —
     * و اگر آن صفحه خودش گریدی (مثلِ Listing Grid جت‌اینجین) داشته باشد،
     * آن گرید رویِ ده‌ها پست حلقه می‌زند و هر آیتم باز محتوایِ المنتوریِ
     * دیگری رندر می‌کند؛ آیتم‌هایی که می‌توانند دوباره همین ویجت را داشته
     * باشند. رشدِ حاصل نمایی است، نه خطی.
     *
     * این دقیقاً همان چیزی بود که رویِ سایت، هنگامِ ذخیرهٔ سندِ Single
     * Product در ادیتور، حافظه را از ~۱۷۰ مگابایت به بیش از ۲ گیگابایت
     * می‌رساند و درخواست را با فاتالِ حافظه می‌کشت (خطایِ ۵۰۰). مسیرِ
     * ذخیره حساس‌تر هم هست: المنتور برایِ ساختنِ نسخهٔ متنیِ صفحه
     * (‎DB::save_plain_text()‎) رندرِ *همهٔ* ویجت‌ها را صدا می‌زند، پس این
     * انفجار در هر ذخیره تکرار می‌شد.
     *
     * راهِ حل، حذفِ موقتِ همان یک کالبک است — نه دور زدنِ کلِ ‎the_content‎:
     * شورت‌کدها، ‎wpautop‎، امبدها و بقیهٔ فیلترها باید سرِ جایشان بمانند،
     * چون کاربر از این ویجت همان‌ها را انتظار دارد. اولویتِ واقعی هم با
     * ‎has_filter()‎ خوانده می‌شود، نه فرضِ ۱۰، تا اگر کسی جای دیگری
     * دوباره‌اش نشانده باشد همان‌جا برگردد.
     *
     * قفلِ ‎$applying_content_filters‎ لایهٔ دومِ دفاع است: اگر با هر مسیرِ
     * دیگری (فیلترِ شخصِ ثالث، شورت‌کدی که خودش محتوا رندر می‌کند) باز هم
     * تودرتو شویم، لایهٔ داخلی به ‎wpautop‎ ساده برمی‌گردد به‌جایِ اینکه
     * زنجیره را عمیق‌تر کند.
     */
    private static function apply_content_filters(string $raw): string {
        if (self::$applying_content_filters) {
            return wpautop($raw);
        }

        $frontend = (class_exists('\Elementor\Plugin') && isset(\Elementor\Plugin::$instance->frontend))
            ? \Elementor\Plugin::$instance->frontend
            : null;

        $callback = null;
        $priority = false;

        if ($frontend && method_exists($frontend, 'apply_builder_in_content')) {
            $callback = [$frontend, 'apply_builder_in_content'];
            $priority = has_filter('the_content', $callback);

            if (false !== $priority) {
                remove_filter('the_content', $callback, (int) $priority);
            }
        }

        self::$applying_content_filters = true;

        try {
            $html = (string) apply_filters('the_content', $raw);
        } finally {
            self::$applying_content_filters = false;

            if (null !== $callback && false !== $priority) {
                add_filter('the_content', $callback, (int) $priority);
            }
        }

        return $html;
    }

    private function current_post_id(): int {
        if (isset($GLOBALS['post']) && $GLOBALS['post'] instanceof \WP_Post) {
            return (int) $GLOBALS['post']->ID;
        }

        $queried = function_exists('get_queried_object_id') ? (int) get_queried_object_id() : 0;

        if ($queried > 0) {
            return $queried;
        }

        return (int) get_the_ID();
    }

    private function notice(string $message): void {
        $editing = class_exists('\Elementor\Plugin')
            && isset(\Elementor\Plugin::$instance->editor)
            && \Elementor\Plugin::$instance->editor->is_edit_mode();

        if (!$editing) {
            return;
        }

        printf('<div class="zig-description__notice">%s</div>', esc_html($message));
    }
}
