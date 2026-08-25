<?php
namespace Zig3d_Widgets\Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;
use Zig3d_Widgets\Archive_Endpoint;
use Zig3d_Widgets\Download_Archive_Data;
use Zig3d_Widgets\Markup;
use Zig3d_Widgets\Plugin;
use Zig3d_Widgets\Price;

if (!defined('ABSPATH')) {
    exit;
}

final class Download_Archive extends Widget_Base {
    private ?array $facet_post_ids = null;
    public function get_name(): string { return 'zig3d-download-archive'; }
    public function get_title(): string { return __('آرشیو دانلود', 'zig3d-widgets'); }
    public function get_icon(): string { return 'eicon-download-button'; }
    public function get_categories(): array { return [Plugin::CATEGORY]; }
    public function get_style_depends(): array { return ['zig3d-widgets']; }
    public function get_script_depends(): array { return ['zig3d-archive']; }
    public function has_widget_inner_wrapper(): bool { return false; }

    protected function register_controls(): void {
        $taxes = Download_Archive_Data::taxonomy_options();

        $this->start_controls_section('sec_data', ['label' => __('منبع داده', 'zig3d-widgets')]);
        $this->add_control('per_page', ['label' => __('تعداد در صفحه', 'zig3d-widgets'), 'type' => Controls_Manager::NUMBER, 'default' => 9, 'min' => 1, 'max' => 48]);
        if (!Download_Archive_Data::jetengine_ready()) {
            $this->add_control('jetengine_notice', ['type' => Controls_Manager::RAW_HTML, 'raw' => __('JetEngine فعال نیست؛ نگاشت فیلدهای سفارشی در دسترس نیست.', 'zig3d-widgets')]);
        }
        $this->end_controls_section();

        $this->start_controls_section('sec_mapping', ['label' => __('نگاشت فیلدهای کارت', 'zig3d-widgets')]);
        $title_fields = Download_Archive_Data::field_options('title');
        $title_fields[''] = __('عنوان نوشته وردپرس', 'zig3d-widgets');
        $description_fields = Download_Archive_Data::field_options('description');
        $description_fields[''] = __('خلاصه نوشته وردپرس', 'zig3d-widgets');
        $description_fields['__post_content'] = __('محتوای نوشته وردپرس', 'zig3d-widgets');
        $this->mapping('title_field', __('منبع عنوان', 'zig3d-widgets'), $title_fields, '');
        $this->mapping('description_field', __('منبع توضیح', 'zig3d-widgets'), $description_fields, '');
        $this->mapping('primary_taxonomy', __('تاکسونومی دسته اصلی', 'zig3d-widgets'), $taxes, Download_Archive_Data::default_taxonomy('software-category'));
        $this->mapping('label_taxonomy', __('تاکسونومی برچسب', 'zig3d-widgets'), $taxes, Download_Archive_Data::default_taxonomy('software-label'));
        foreach ([
            'software_version' => [__('نسخه نرم‌افزار', 'zig3d-widgets'), 'version'],
            'release_date' => [__('تاریخ بروزرسانی', 'zig3d-widgets'), 'date'],
            'supported_os' => [__('سیستم‌عامل‌های سازگار', 'zig3d-widgets'), 'multi'],
            'compatible_models' => [__('مدل‌های دستگاه سازگار', 'zig3d-widgets'), 'multi'],
            'download_url' => [__('آدرس دانلود', 'zig3d-widgets'), 'url'],
            'file_type' => [__('نوع فایل (داده معنایی مدیر)', 'zig3d-widgets'), 'choice'],
            'software_brand' => [__('برند نرم‌افزار', 'zig3d-widgets'), 'choice'],
            'system_architecture' => [__('معماری سیستم', 'zig3d-widgets'), 'choice'],
            'details_url' => [__('آدرس جزئیات (خالی = پیوند نوشته)', 'zig3d-widgets'), 'url'],
        ] as $id => [$label, $role]) {
            $this->mapping($id, $label, Download_Archive_Data::field_options($role), Download_Archive_Data::default_field($id));
        }
        $this->end_controls_section();

        $this->start_controls_section('sec_filters', ['label' => __('نگاشت فیلترها', 'zig3d-widgets')]);
        $this->add_control('filters_on', ['label' => __('نمایش فیلترها', 'zig3d-widgets'), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes']);
        $this->add_control('hide_small_groups', ['label' => __('پنهان‌کردن گروه‌های کمتر از ۲ گزینه', 'zig3d-widgets'), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes']);
        $this->filter_slot('category', __('نوع نرم‌افزار', 'zig3d-widgets'), $taxes, Download_Archive_Data::default_taxonomy('software-category'));
        $this->filter_slot('models', __('مدل دستگاه سازگار', 'zig3d-widgets'), Download_Archive_Data::field_options('multi'), Download_Archive_Data::default_field('compatible_models'));
        $this->filter_slot('os', __('سیستم‌عامل', 'zig3d-widgets'), Download_Archive_Data::field_options('multi'), Download_Archive_Data::default_field('supported_os'));
        $this->filter_slot('brand', __('برند', 'zig3d-widgets'), Download_Archive_Data::field_options('choice'), Download_Archive_Data::default_field('software_brand'));
        $this->filter_slot('type', __('نوع فایل', 'zig3d-widgets'), Download_Archive_Data::field_options('choice'), Download_Archive_Data::default_field('file_type'));
        $this->filter_slot('arch', __('معماری سیستم', 'zig3d-widgets'), Download_Archive_Data::field_options('choice'), Download_Archive_Data::default_field('system_architecture'));
        $this->end_controls_section();

        $this->start_controls_section('sec_toolbar', ['label' => __('نوار ابزار آرشیو', 'zig3d-widgets')]);
        $this->add_control('scope_title', ['label' => __('عنوان محدوده', 'zig3d-widgets'), 'type' => Controls_Manager::TEXT, 'default' => __('همه نرم‌افزارها', 'zig3d-widgets')]);
        $this->add_control('search_placeholder', ['label' => __('متن جستجو', 'zig3d-widgets'), 'type' => Controls_Manager::TEXT, 'default' => __('جستجوی نام نرم‌افزار یا مدل دستگاه...', 'zig3d-widgets')]);
        $this->add_control('count_on', ['label' => __('نمایش تعداد نتایج', 'zig3d-widgets'), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes']);
        $this->add_control('sorting_on', ['label' => __('نمایش مرتب‌سازی', 'zig3d-widgets'), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes']);
        $this->end_controls_section();

        $this->start_controls_section('sec_pagination', ['label' => __('صفحه‌بندی', 'zig3d-widgets')]);
        /*
         * همان چهار کنترل و همان معنا که در آرشیو محصولات هست — پیمایشِ
         * این ویجت هم از همان زیرساختِ مشترک (‎zig3d-archive.js‎، همان
         * ‎data-zig-scroll-max‎/‎data-zig-restore‎) استفاده می‌کند، پس
         * تنظیماتش هم باید یکی باشد.
         */
        $this->add_control('scroll_pages', [
            'label'       => __('حداکثر صفحات اسکرول خودکار', 'zig3d-widgets'),
            'type'        => Controls_Manager::NUMBER,
            'default'     => 2,
            'min'         => 0,
            'max'         => 20,
            'description' => __('صفر یعنی همیشه صفحه‌بندی صریح.', 'zig3d-widgets'),
        ]);
        $this->add_control('restore_state', [
            'label'       => __('بازگرداندن موقعیت هنگام برگشت', 'zig3d-widgets'),
            'type'        => Controls_Manager::SWITCHER,
            'default'     => 'yes',
            'description' => __('صفحه و موقعیت اسکرول، هنگام برگشت از صفحهٔ جزئیات.', 'zig3d-widgets'),
        ]);
        $this->add_control('label_prev', ['label' => __('متن «قبلی»', 'zig3d-widgets'), 'type' => Controls_Manager::TEXT, 'default' => __('قبلی', 'zig3d-widgets')]);
        $this->add_control('label_next', ['label' => __('متن «بعدی»', 'zig3d-widgets'), 'type' => Controls_Manager::TEXT, 'default' => __('بعدی', 'zig3d-widgets')]);
        $this->end_controls_section();

        $this->start_controls_section('sec_cta', ['label' => __('محتوای کارت و CTA', 'zig3d-widgets')]);
        $this->add_control('models_preview_desktop', ['label' => __('تعداد پیش‌نمایش مدل در دسکتاپ', 'zig3d-widgets'), 'type' => Controls_Manager::NUMBER, 'default' => 6, 'min' => 1, 'max' => 12]);
        $this->add_control('models_preview_mobile', ['label' => __('تعداد پیش‌نمایش مدل در موبایل', 'zig3d-widgets'), 'type' => Controls_Manager::NUMBER, 'default' => 4, 'min' => 1, 'max' => 10]);
        $this->add_control('download_text', ['label' => __('متن دانلود', 'zig3d-widgets'), 'type' => Controls_Manager::TEXT, 'default' => __('دانلود', 'zig3d-widgets')]);
        $this->add_control('details_text', ['label' => __('متن جزئیات', 'zig3d-widgets'), 'type' => Controls_Manager::TEXT, 'default' => __('مشاهده جزئیات', 'zig3d-widgets')]);
        $this->add_control('empty_text', ['label' => __('پیام نتیجه خالی', 'zig3d-widgets'), 'type' => Controls_Manager::TEXT, 'default' => __('نرم‌افزاری با این فیلترها پیدا نشد.', 'zig3d-widgets')]);
        $this->end_controls_section();

        $this->register_style_controls();

        $this->start_controls_section('sec_behavior', ['label' => __('رفتار', 'zig3d-widgets')]);
        $this->add_control('filters_debounce', ['label' => __('تأخیر جستجو/فیلتر (ms)', 'zig3d-widgets'), 'type' => Controls_Manager::NUMBER, 'default' => 300, 'min' => 0, 'max' => 1000]);
        $this->end_controls_section();
    }

    private function register_style_controls(): void {
        $this->register_card_style_controls();
        $this->register_header_style_controls();
        $this->register_compatibility_style_controls();
        $this->register_badge_style_controls();
        $this->register_metadata_style_controls();
        $this->register_action_style_controls();
    }

    private function register_card_style_controls(): void {
        $card = '{{WRAPPER}} .zig-download-card';
        $this->start_controls_section('sec_card_style', ['label' => __('کارت', 'zig3d-widgets'), 'tab' => Controls_Manager::TAB_STYLE]);
        $this->add_control('card_background', ['label' => __('پس‌زمینه', 'zig3d-widgets'), 'type' => Controls_Manager::COLOR, 'selectors' => [$card => 'background-color: {{VALUE}};']]);
        $this->start_controls_tabs('card_state_tabs');
        $this->start_controls_tab('card_normal_tab', ['label' => __('عادی', 'zig3d-widgets')]);
        $this->add_control('card_border_color', ['label' => __('رنگ حاشیه', 'zig3d-widgets'), 'type' => Controls_Manager::COLOR, 'selectors' => [$card => 'border-color: {{VALUE}};']]);
        $this->add_group_control(Group_Control_Box_Shadow::get_type(), ['name' => 'card_box_shadow', 'selector' => $card]);
        $this->end_controls_tab();
        $this->start_controls_tab('card_hover_tab', ['label' => __('هاور', 'zig3d-widgets')]);
        $this->add_control('card_hover_border_color', ['label' => __('رنگ حاشیه', 'zig3d-widgets'), 'type' => Controls_Manager::COLOR, 'selectors' => [$card . ':hover' => 'border-color: {{VALUE}};']]);
        $this->add_group_control(Group_Control_Box_Shadow::get_type(), ['name' => 'card_hover_box_shadow', 'selector' => $card . ':hover']);
        $this->end_controls_tab();
        $this->end_controls_tabs();
        $this->add_control('card_border_width', ['label' => __('ضخامت حاشیه', 'zig3d-widgets'), 'type' => Controls_Manager::DIMENSIONS, 'selectors' => [$card => 'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};']]);
        $this->add_responsive_control('card_radius', ['label' => __('گردی گوشه‌ها', 'zig3d-widgets'), 'type' => Controls_Manager::SLIDER, 'range' => ['px' => ['min' => 0, 'max' => 40]], 'selectors' => [$card => 'border-radius: {{SIZE}}{{UNIT}};']]);
        $this->add_responsive_control('card_padding', ['label' => __('فاصله داخلی', 'zig3d-widgets'), 'type' => Controls_Manager::DIMENSIONS, 'selectors' => [$card => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};']]);
        $this->add_responsive_control('card_list_gap', ['label' => __('فاصله بین کارت‌ها', 'zig3d-widgets'), 'type' => Controls_Manager::SLIDER, 'range' => ['px' => ['min' => 0, 'max' => 80]], 'selectors' => ['{{WRAPPER}} .zig-download-list' => 'gap: {{SIZE}}{{UNIT}};']]);
        $this->add_control('footer_divider_color', ['label' => __('رنگ جداکننده پایین کارت', 'zig3d-widgets'), 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .zig-download-card__footer' => 'border-block-start-color: {{VALUE}};']]);
        $this->add_control('card_transition_duration', ['label' => __('مدت تغییر حالت', 'zig3d-widgets'), 'type' => Controls_Manager::SLIDER, 'size_units' => ['ms'], 'range' => ['ms' => ['min' => 0, 'max' => 1000]], 'selectors' => [$card => 'transition-duration: {{SIZE}}{{UNIT}};']]);
        $this->end_controls_section();
    }

    private function register_header_style_controls(): void {
        $this->start_controls_section('sec_header_style', ['label' => __('سربرگ و توضیحات', 'zig3d-widgets'), 'tab' => Controls_Manager::TAB_STYLE]);
        $this->add_control('header_category_heading', ['label' => __('دسته‌بندی', 'zig3d-widgets'), 'type' => Controls_Manager::HEADING]);
        $this->add_group_control(Group_Control_Typography::get_type(), ['name' => 'category_typography', 'condition' => ['primary_taxonomy!' => ''], 'selector' => '{{WRAPPER}} .zig-download-card__category']);
        $this->add_control('category_color', ['label' => __('رنگ دسته‌بندی', 'zig3d-widgets'), 'type' => Controls_Manager::COLOR, 'condition' => ['primary_taxonomy!' => ''], 'selectors' => ['{{WRAPPER}} .zig-download-card__category' => 'color: {{VALUE}};']]);
        $this->add_control('header_title_heading', ['label' => __('عنوان', 'zig3d-widgets'), 'type' => Controls_Manager::HEADING, 'separator' => 'before']);
        $this->add_group_control(Group_Control_Typography::get_type(), ['name' => 'card_title_typography', 'selector' => '{{WRAPPER}} .zig-download-card__title']);
        $this->add_control('card_title_color', ['label' => __('رنگ عنوان', 'zig3d-widgets'), 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .zig-download-card__title' => 'color: {{VALUE}};']]);
        $this->add_control('header_description_heading', ['label' => __('توضیحات', 'zig3d-widgets'), 'type' => Controls_Manager::HEADING, 'separator' => 'before']);
        $this->add_group_control(Group_Control_Typography::get_type(), ['name' => 'card_description_typography', 'selector' => '{{WRAPPER}} .zig-download-card__description']);
        $this->add_control('card_description_color', ['label' => __('رنگ توضیحات', 'zig3d-widgets'), 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .zig-download-card__description' => 'color: {{VALUE}};']]);
        $this->add_control('header_spacing_heading', ['label' => __('فاصله‌ها', 'zig3d-widgets'), 'type' => Controls_Manager::HEADING, 'separator' => 'before']);
        $this->add_responsive_control('category_title_spacing', ['label' => __('فاصله دسته‌بندی تا عنوان', 'zig3d-widgets'), 'type' => Controls_Manager::SLIDER, 'range' => ['px' => ['min' => 0, 'max' => 60]], 'selectors' => ['{{WRAPPER}} .zig-download-card__category' => 'margin-block-end: {{SIZE}}{{UNIT}};']]);
        $this->add_responsive_control('title_description_spacing', ['label' => __('فاصله عنوان تا توضیحات', 'zig3d-widgets'), 'type' => Controls_Manager::SLIDER, 'range' => ['px' => ['min' => 0, 'max' => 60]], 'selectors' => ['{{WRAPPER}} .zig-download-card__description' => 'margin-block-start: {{SIZE}}{{UNIT}};']]);
        $this->add_responsive_control('description_compatibility_spacing', ['label' => __('فاصله زیر توضیحات', 'zig3d-widgets'), 'type' => Controls_Manager::SLIDER, 'range' => ['px' => ['min' => 0, 'max' => 80]], 'selectors' => ['{{WRAPPER}} .zig-download-card__description + .zig-download-card__compatibility' => 'margin-block-start: {{SIZE}}{{UNIT}};']]);
        $this->end_controls_section();
    }

    private function register_compatibility_style_controls(): void {
        $this->start_controls_section('sec_compatibility_style', ['label' => __('دستگاه‌های سازگار', 'zig3d-widgets'), 'tab' => Controls_Manager::TAB_STYLE, 'condition' => ['compatible_models!' => '']]);
        $this->add_control('compatibility_heading_label', ['label' => __('عنوان بخش', 'zig3d-widgets'), 'type' => Controls_Manager::HEADING]);
        $this->add_group_control(Group_Control_Typography::get_type(), ['name' => 'compatibility_heading_typography', 'selector' => '{{WRAPPER}} .zig-download-card__compatibility-head']);
        $this->add_control('compatibility_heading_color', ['label' => __('رنگ عنوان بخش', 'zig3d-widgets'), 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .zig-download-card__compatibility-head' => 'color: {{VALUE}};']]);
        $this->add_control('compatibility_count_color', ['label' => __('رنگ تعداد مدل‌ها', 'zig3d-widgets'), 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .zig-download-card__models-count' => 'color: {{VALUE}};']]);
        $this->add_control('compatibility_models_heading', ['label' => __('مدل‌های دستگاه', 'zig3d-widgets'), 'type' => Controls_Manager::HEADING, 'separator' => 'before']);
        $this->add_group_control(Group_Control_Typography::get_type(), ['name' => 'model_typography', 'selector' => '{{WRAPPER}} .zig-download-card__model']);
        $this->add_control('model_colors', ['label' => __('رنگ متن', 'zig3d-widgets'), 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .zig-download-card__model' => 'color: {{VALUE}};']]);
        $this->add_control('model_background', ['label' => __('پس‌زمینه', 'zig3d-widgets'), 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .zig-download-card__model' => 'background-color: {{VALUE}};']]);
        $this->add_control('model_border_color', ['label' => __('رنگ حاشیه', 'zig3d-widgets'), 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .zig-download-card__model' => 'border-color: {{VALUE}};']]);
        $this->add_control('model_border_width', ['label' => __('ضخامت حاشیه', 'zig3d-widgets'), 'type' => Controls_Manager::DIMENSIONS, 'selectors' => ['{{WRAPPER}} .zig-download-card__model' => 'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};']]);
        $this->add_control('model_radius', ['label' => __('گردی گوشه‌ها', 'zig3d-widgets'), 'type' => Controls_Manager::SLIDER, 'range' => ['px' => ['min' => 0, 'max' => 24]], 'selectors' => ['{{WRAPPER}} .zig-download-card__model' => 'border-radius: {{SIZE}}{{UNIT}};']]);
        $this->add_responsive_control('model_padding', ['label' => __('فاصله داخلی', 'zig3d-widgets'), 'type' => Controls_Manager::DIMENSIONS, 'selectors' => ['{{WRAPPER}} .zig-download-card__model' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};']]);
        $this->add_responsive_control('model_gap', ['label' => __('فاصله بین مدل‌ها', 'zig3d-widgets'), 'type' => Controls_Manager::SLIDER, 'range' => ['px' => ['min' => 0, 'max' => 40]], 'selectors' => ['{{WRAPPER}} .zig-download-card__models' => 'gap: {{SIZE}}{{UNIT}};']]);
        $this->add_control('compatibility_toggle_heading', ['label' => __('مشاهده بیشتر', 'zig3d-widgets'), 'type' => Controls_Manager::HEADING, 'separator' => 'before']);
        $this->add_group_control(Group_Control_Typography::get_type(), ['name' => 'models_toggle_typography', 'selector' => '{{WRAPPER}} .zig-download-card__models-toggle']);
        $this->start_controls_tabs('models_toggle_state_tabs');
        $this->start_controls_tab('models_toggle_normal_tab', ['label' => __('عادی', 'zig3d-widgets')]);
        $this->add_control('models_toggle_color', ['label' => __('رنگ لینک', 'zig3d-widgets'), 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .zig-download-card__models-toggle' => 'color: {{VALUE}};']]);
        $this->end_controls_tab();
        $this->start_controls_tab('models_toggle_hover_tab', ['label' => __('هاور', 'zig3d-widgets')]);
        $this->add_control('models_toggle_hover_color', ['label' => __('رنگ هاور', 'zig3d-widgets'), 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .zig-download-card__models-toggle:hover' => 'color: {{VALUE}};']]);
        $this->end_controls_tab();
        $this->end_controls_tabs();
        $this->add_control('compatibility_spacing_heading', ['label' => __('فاصله‌ها', 'zig3d-widgets'), 'type' => Controls_Manager::HEADING, 'separator' => 'before']);
        $this->add_responsive_control('compatibility_heading_gap', ['label' => __('فاصله عنوان تا مدل‌ها', 'zig3d-widgets'), 'type' => Controls_Manager::SLIDER, 'range' => ['px' => ['min' => 0, 'max' => 60]], 'selectors' => ['{{WRAPPER}} .zig-download-card__compatibility-head' => 'margin-block-end: {{SIZE}}{{UNIT}};']]);
        $this->add_responsive_control('compatibility_toggle_gap', ['label' => __('فاصله مدل‌ها تا مشاهده بیشتر', 'zig3d-widgets'), 'type' => Controls_Manager::SLIDER, 'range' => ['px' => ['min' => 0, 'max' => 60]], 'selectors' => ['{{WRAPPER}} .zig-download-card__models-toggle' => 'margin-block-start: {{SIZE}}{{UNIT}};']]);
        $this->add_responsive_control('compatibility_footer_gap', ['label' => __('فاصله تا اطلاعات فایل', 'zig3d-widgets'), 'type' => Controls_Manager::SLIDER, 'range' => ['px' => ['min' => 0, 'max' => 100]], 'selectors' => ['{{WRAPPER}} .zig-download-card__footer' => 'margin-block-start: {{SIZE}}{{UNIT}};']]);
        $this->end_controls_section();
    }

    private function register_badge_style_controls(): void {
        $this->start_controls_section('sec_labels_style', ['label' => __('برچسب‌ها', 'zig3d-widgets'), 'tab' => Controls_Manager::TAB_STYLE, 'condition' => ['label_taxonomy!' => '']]);
        $selector = '{{WRAPPER}} .zig-download-card__labels span';
        $this->add_group_control(Group_Control_Typography::get_type(), ['name' => 'label_typography', 'selector' => $selector]);
        $this->add_control('label_color', ['label' => __('رنگ متن', 'zig3d-widgets'), 'type' => Controls_Manager::COLOR, 'selectors' => [$selector => 'color: {{VALUE}};']]);
        $this->add_control('label_background', ['label' => __('پس‌زمینه', 'zig3d-widgets'), 'type' => Controls_Manager::COLOR, 'selectors' => [$selector => 'background-color: {{VALUE}};']]);
        $this->add_control('label_border_color', ['label' => __('رنگ حاشیه', 'zig3d-widgets'), 'type' => Controls_Manager::COLOR, 'selectors' => [$selector => 'border-color: {{VALUE}};']]);
        $this->add_control('label_radius', ['label' => __('گردی گوشه‌ها', 'zig3d-widgets'), 'type' => Controls_Manager::SLIDER, 'range' => ['px' => ['min' => 0, 'max' => 30]], 'selectors' => [$selector => 'border-radius: {{SIZE}}{{UNIT}};']]);
        $this->add_responsive_control('label_padding', ['label' => __('فاصله داخلی', 'zig3d-widgets'), 'type' => Controls_Manager::DIMENSIONS, 'selectors' => [$selector => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};']]);
        $this->end_controls_section();
    }

    private function register_metadata_style_controls(): void {
        $this->start_controls_section('sec_metadata_style', ['label' => __('اطلاعات فایل', 'zig3d-widgets'), 'tab' => Controls_Manager::TAB_STYLE]);
        $this->add_control('metadata_labels_heading', ['label' => __('برچسب اطلاعات', 'zig3d-widgets'), 'type' => Controls_Manager::HEADING]);
        $this->add_group_control(Group_Control_Typography::get_type(), ['name' => 'meta_label_typography', 'selector' => '{{WRAPPER}} .zig-download-card__meta dt']);
        $this->add_control('meta_label_color', ['label' => __('رنگ برچسب', 'zig3d-widgets'), 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .zig-download-card__meta dt' => 'color: {{VALUE}};']]);
        $this->add_control('metadata_values_heading', ['label' => __('مقدار اطلاعات', 'zig3d-widgets'), 'type' => Controls_Manager::HEADING, 'separator' => 'before']);
        $this->add_group_control(Group_Control_Typography::get_type(), ['name' => 'meta_value_typography', 'selector' => '{{WRAPPER}} .zig-download-card__meta dd']);
        $this->add_control('meta_value_color', ['label' => __('رنگ مقدار', 'zig3d-widgets'), 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .zig-download-card__meta dd' => 'color: {{VALUE}};']]);
        $this->add_responsive_control('metadata_items_gap', ['label' => __('فاصله بین اطلاعات', 'zig3d-widgets'), 'type' => Controls_Manager::SLIDER, 'range' => ['px' => ['min' => 0, 'max' => 60]], 'selectors' => ['{{WRAPPER}} .zig-download-card__meta' => 'gap: {{SIZE}}{{UNIT}};']]);
        $this->add_responsive_control('metadata_footer_spacing', ['label' => __('فاصله داخلی ناحیه پایین', 'zig3d-widgets'), 'type' => Controls_Manager::DIMENSIONS, 'selectors' => ['{{WRAPPER}} .zig-download-card__footer' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};']]);
        $this->add_control('metadata_os_heading', ['label' => __('سیستم‌عامل', 'zig3d-widgets'), 'type' => Controls_Manager::HEADING, 'separator' => 'before']);
        $this->add_group_control(Group_Control_Typography::get_type(), ['name' => 'os_typography', 'label' => __('تایپوگرافی اختصاصی سیستم‌عامل', 'zig3d-widgets'), 'condition' => ['supported_os!' => ''], 'selector' => '{{WRAPPER}} .zig-download-card__meta-os-value']);
        $this->add_control('os_colors', ['label' => __('رنگ اختصاصی سیستم‌عامل', 'zig3d-widgets'), 'type' => Controls_Manager::COLOR, 'condition' => ['supported_os!' => ''], 'selectors' => ['{{WRAPPER}} .zig-download-card__meta-os-value' => 'color: {{VALUE}};']]);
        $this->end_controls_section();
    }

    private function register_action_style_controls(): void {
        $this->start_controls_section('sec_actions_style', ['label' => __('دکمه‌ها', 'zig3d-widgets'), 'tab' => Controls_Manager::TAB_STYLE]);
        $this->add_control('download_button_heading', ['label' => __('دکمه دانلود', 'zig3d-widgets'), 'type' => Controls_Manager::HEADING]);
        $this->add_group_control(Group_Control_Typography::get_type(), ['name' => 'primary_cta_typography', 'selector' => '{{WRAPPER}} .zig-download-card__download']);
        $this->start_controls_tabs('primary_cta_state_tabs');
        $this->start_controls_tab('primary_cta_normal_tab', ['label' => __('عادی', 'zig3d-widgets')]);
        $this->add_control('primary_cta_color', ['label' => __('رنگ متن', 'zig3d-widgets'), 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .zig-download-card__download' => 'color: {{VALUE}};']]);
        $this->add_control('primary_cta_background', ['label' => __('پس‌زمینه', 'zig3d-widgets'), 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .zig-download-card__download' => 'background-color: {{VALUE}};']]);
        $this->add_control('primary_cta_border_color', ['label' => __('رنگ حاشیه', 'zig3d-widgets'), 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .zig-download-card__download' => 'border-color: {{VALUE}};']]);
        $this->end_controls_tab();
        $this->start_controls_tab('primary_cta_hover_tab', ['label' => __('هاور', 'zig3d-widgets')]);
        $this->add_control('primary_cta_hover_color', ['label' => __('رنگ متن', 'zig3d-widgets'), 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .zig-download-card__download:hover' => 'color: {{VALUE}};']]);
        $this->add_control('primary_cta_hover_background', ['label' => __('پس‌زمینه', 'zig3d-widgets'), 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .zig-download-card__download:hover' => 'background-color: {{VALUE}};']]);
        $this->add_control('primary_cta_hover_border_color', ['label' => __('رنگ حاشیه', 'zig3d-widgets'), 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .zig-download-card__download:hover' => 'border-color: {{VALUE}};']]);
        $this->end_controls_tab();
        $this->end_controls_tabs();
        $this->add_control('primary_cta_border_width', ['label' => __('ضخامت حاشیه', 'zig3d-widgets'), 'type' => Controls_Manager::DIMENSIONS, 'selectors' => ['{{WRAPPER}} .zig-download-card__download' => 'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};']]);
        $this->add_control('primary_cta_radius', ['label' => __('گردی گوشه‌ها', 'zig3d-widgets'), 'type' => Controls_Manager::SLIDER, 'range' => ['px' => ['min' => 0, 'max' => 30]], 'selectors' => ['{{WRAPPER}} .zig-download-card__download' => 'border-radius: {{SIZE}}{{UNIT}};']]);
        $this->add_responsive_control('primary_cta_padding', ['label' => __('فاصله داخلی', 'zig3d-widgets'), 'type' => Controls_Manager::DIMENSIONS, 'selectors' => ['{{WRAPPER}} .zig-download-card__download' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};']]);
        $this->add_control('details_button_heading', ['label' => __('دکمه مشاهده جزئیات', 'zig3d-widgets'), 'type' => Controls_Manager::HEADING, 'separator' => 'before']);
        $this->add_group_control(Group_Control_Typography::get_type(), ['name' => 'secondary_cta_typography', 'selector' => '{{WRAPPER}} .zig-download-card__details']);
        $this->start_controls_tabs('secondary_cta_state_tabs');
        $this->start_controls_tab('secondary_cta_normal_tab', ['label' => __('عادی', 'zig3d-widgets')]);
        $this->add_control('secondary_cta_color', ['label' => __('رنگ متن و حاشیه', 'zig3d-widgets'), 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .zig-download-card__details' => 'color: {{VALUE}}; border-color: {{VALUE}};']]);
        $this->add_control('secondary_cta_background', ['label' => __('پس‌زمینه', 'zig3d-widgets'), 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .zig-download-card__details' => 'background-color: {{VALUE}};']]);
        $this->end_controls_tab();
        $this->start_controls_tab('secondary_cta_hover_tab', ['label' => __('هاور', 'zig3d-widgets')]);
        $this->add_control('secondary_cta_hover', ['label' => __('رنگ متن و حاشیه', 'zig3d-widgets'), 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .zig-download-card__details:hover' => 'color: {{VALUE}}; border-color: {{VALUE}};']]);
        $this->add_control('secondary_cta_hover_background', ['label' => __('پس‌زمینه', 'zig3d-widgets'), 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .zig-download-card__details:hover' => 'background-color: {{VALUE}};']]);
        $this->end_controls_tab();
        $this->end_controls_tabs();
        $this->add_control('secondary_cta_border_width', ['label' => __('ضخامت حاشیه', 'zig3d-widgets'), 'type' => Controls_Manager::DIMENSIONS, 'selectors' => ['{{WRAPPER}} .zig-download-card__details' => 'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};']]);
        $this->add_control('secondary_cta_radius', ['label' => __('گردی گوشه‌ها', 'zig3d-widgets'), 'type' => Controls_Manager::SLIDER, 'range' => ['px' => ['min' => 0, 'max' => 30]], 'selectors' => ['{{WRAPPER}} .zig-download-card__details' => 'border-radius: {{SIZE}}{{UNIT}};']]);
        $this->add_responsive_control('secondary_cta_padding', ['label' => __('فاصله داخلی', 'zig3d-widgets'), 'type' => Controls_Manager::DIMENSIONS, 'selectors' => ['{{WRAPPER}} .zig-download-card__details' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};']]);
        $this->add_control('actions_layout_heading', ['label' => __('چیدمان دکمه‌ها', 'zig3d-widgets'), 'type' => Controls_Manager::HEADING, 'separator' => 'before']);
        $this->add_responsive_control('actions_gap', ['label' => __('فاصله بین دکمه‌ها', 'zig3d-widgets'), 'type' => Controls_Manager::SLIDER, 'range' => ['px' => ['min' => 0, 'max' => 50]], 'selectors' => ['{{WRAPPER}} .zig-download-card__actions' => 'gap: {{SIZE}}{{UNIT}};']]);
        $this->add_responsive_control('actions_alignment', ['label' => __('تراز دکمه‌ها', 'zig3d-widgets'), 'type' => Controls_Manager::CHOOSE, 'options' => ['start' => ['title' => __('ابتدا', 'zig3d-widgets'), 'icon' => 'eicon-align-start-h'], 'center' => ['title' => __('وسط', 'zig3d-widgets'), 'icon' => 'eicon-align-center-h'], 'end' => ['title' => __('انتها', 'zig3d-widgets'), 'icon' => 'eicon-align-end-h']], 'selectors' => ['{{WRAPPER}} .zig-download-card__actions' => 'justify-content: {{VALUE}};']]);
        $this->end_controls_section();
    }

    private function mapping(string $id, string $label, array $options, string $default): void {
        $this->add_control($id, ['label' => $label, 'type' => Controls_Manager::SELECT, 'options' => $options, 'default' => $default]);
    }

    private function filter_slot(string $id, string $label, array $options, string $default): void {
        $this->add_control('filter_' . $id . '_on', ['label' => sprintf(__('فعال: %s', 'zig3d-widgets'), $label), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes']);
        $this->add_control('filter_' . $id . '_label', ['label' => sprintf(__('عنوان: %s', 'zig3d-widgets'), $label), 'type' => Controls_Manager::TEXT, 'default' => $label]);
        $this->add_control('filter_' . $id . '_source', ['label' => sprintf(__('منبع: %s', 'zig3d-widgets'), $label), 'type' => Controls_Manager::SELECT, 'options' => $options, 'default' => $default]);
    }

    protected function render(): void {
        $post_type = Download_Archive_Data::post_type();
        if ('' === $post_type) {
            if ($this->is_edit_mode()) {
                echo '<div class="zig-download-archive__notice">' . esc_html(sprintf(__('پیکربندی CPT جت‌انجین با شناسه %d قابل حل نیست.', 'zig3d-widgets'), Download_Archive_Data::JETENGINE_CPT_ID)) . '</div>';
            }
            return;
        }

        $settings = $this->get_settings_for_display();
        $ctx = $this->context($settings);
        $this->add_render_attribute('root', [
            'class' => 'zig-archive zig-download-archive',
            'data-zig-archive' => '1',
            'data-zig-state' => $ctx['found'] > 0 ? 'ok' : 'empty',
            'data-zig-debounce' => (string) (int) ($settings['filters_debounce'] ?? 300),
            'data-zig-scroll-max' => (string) (int) ($settings['scroll_pages'] ?? 0),
            'data-zig-restore' => 'yes' === ($settings['restore_state'] ?? '') ? '1' : '0',
            'data-zig-page' => (string) $ctx['page'], 'data-zig-pages' => (string) max(1, $ctx['pages']),
            'data-zig-endpoint' => Archive_Endpoint::url(), 'data-zig-nonce' => Archive_Endpoint::nonce(),
            'data-zig-post' => (string) $this->document_id(), 'data-zig-widget' => $this->get_id(), 'data-zig-term' => '0',
        ]);
        echo '<section ' . $this->get_render_attribute_string('root') . '>';

        $has_sidebar = $this->has_sidebar($settings, $ctx['facets']);
        $filters_id  = 'zig-download-filters-' . $this->get_id();

        echo '<div class="zig-download-archive__layout">';
        /*
         * ‎<div data-zig-part="pagination">‎، نه ‎<nav>‎ — دقیقاً مثلِ آرشیوِ
         * محصولات: خودِ ‎render_pagination()‎ عنصرِ ‎<nav>‎ی واقعی را چاپ
         * می‌کند (یا وقتی یک صفحه بیشتر نیست، هیچ‌چیز)؛ اگر بیرونش هم
         * ‎<nav>‎ بود، یک ‎<nav>‎ی تودرتو می‌ساخت.
         */
        echo '<main class="zig-archive__main zig-download-archive__main">';
        $this->render_mobile_bar($ctx, $has_sidebar, $filters_id);
        echo '<div class="zig-download-archive__toolbar">' . $this->fragment('toolbar', $ctx) . '</div><div data-zig-part="grid">' . $this->fragment('grid', $ctx) . '</div><div data-zig-part="pagination">' . $this->fragment('pagination', $ctx) . '</div></main>';

        if ($has_sidebar) {
            /*
             * همان الگویِ شیتِ موبایلِ آرشیوِ محصولات: دستگیره و دکمهٔ
             * بازگشت خواهرِ اسلاتِ ‎facets‎اند نه فرزندش — وگرنه هر تیکِ
             * فیلتر (که فقط همین اسلات را عوض می‌کند) پاکشان می‌کرد.
             * ‎.zig-archive__filters‎ی مشترک (همان کلاسِ سایدبارِ دسکتاپِ
             * آرشیوِ محصولات) خودش زیرِ ۷۶۷px به شیتِ تمام‌ارتفاعِ
             * ‎position:fixed‎ تبدیل می‌شود، بدونِ هیچ CSSِ تازه‌ای اینجا.
             */
            printf(
                '<aside id="%s" class="zig-archive__filters zig-download-archive__filters" aria-label="%s">',
                esc_attr($filters_id),
                esc_attr__('فیلترها', 'zig3d-widgets')
            );
            echo '<span class="zig-archive__sheet-handle" aria-hidden="true"></span>';
            echo '<div class="zig-archive__facets" data-zig-part="facets">' . $this->fragment('facets', $ctx) . '</div>';
            printf(
                '<button type="button" class="zig-archive__sheet-back" data-zig-close>%s<span>%s</span></button>',
                Markup::svg_icon('arrow', 'zig-archive__sheet-back-icon'),
                esc_html__('بازگشت', 'zig3d-widgets')
            );
            echo '</aside>';
        }

        echo '</div>';
        echo '<div class="zig-archive__sheet-backdrop" data-zig-sheet-backdrop hidden></div>';
        echo '<div class="zig-archive__error" hidden><button class="zig-archive__retry" type="button">' . esc_html__('تلاش دوباره', 'zig3d-widgets') . '</button></div></section>';
        wp_reset_postdata();
    }

    /**
     * نوارِ قرصیِ موبایل: دکمهٔ فیلتر (اگر سایدباری هست) + سرچ.
     *
     * بدونِ ترتیب — این ویجت اصلاً مرتب‌سازی ندارد — پس بخشِ عمدهٔ نوار را
     * سرچ می‌گیرد (‎flex: 1 1 auto‎ی خودِ فرم، نه چیدمانِ دو‌پیلِ ثابتِ
     * آرشیوِ محصولات).
     *
     * همان کلاس‌ها/دیتا-اتریبیوت‌هایِ ‎.zig-archive__mbar‎/‎data-zig-mbar‎/
     * ‎data-zig-open‎ی آرشیوِ محصولات به‌کار رفته — یعنی همان چرومِ CSS و
     * همان ‎bindSheets()‎/‎toggleSheet()‎یِ جاوااسکریپت، بدونِ هیچ کدِ
     * تازه‌ای مخصوصِ این ویجت.
     */
    private function render_mobile_bar(array $ctx, bool $has_sidebar, string $filters_id): void {
        $settings = $ctx['settings'];

        echo '<div class="zig-archive__mbar zig-archive__mbar--search" data-zig-mbar>';

        if ($has_sidebar) {
            printf(
                '<button type="button" class="zig-archive__mbar-btn zig-archive__mbar-btn--filter" data-zig-open="filters" aria-expanded="false" aria-controls="%s">%s<span class="zig-archive__mbar-text">%s</span></button>',
                esc_attr($filters_id),
                Markup::svg_icon_filled('mbar-filter', 'zig-archive__mbar-icon zig-archive__mbar-icon--filter'),
                esc_html__('فیلترها', 'zig3d-widgets')
            );
        }

        /*
         * همان ‎data-zig-search‎ی فرمِ جستجویِ دسکتاپ — یعنی دومین عنصری
         * که با این اتریبیوت پیدا می‌شود، نه یکیِ جدا. جاوااسکریپت هر دو
         * را می‌بندد و مقدارشان را با هم هم‌گام نگه می‌دارد.
         */
        printf(
            '<form class="zig-archive__mbar-search" role="search">'
                . '<label class="screen-reader-text" for="zig-download-search-mobile-%1$s">%2$s</label>'
                . '<input id="zig-download-search-mobile-%1$s" type="search" name="s" value="%3$s" placeholder="%4$s" data-zig-search>'
                . '</form>',
            esc_attr($this->get_id()),
            esc_html__('جستجوی نرم‌افزار', 'zig3d-widgets'),
            esc_attr($ctx['state']['search']),
            esc_attr($settings['search_placeholder'] ?? '')
        );

        echo '</div>';
    }

    public function context(array $settings, ?array $params = null, int $term_id = 0): array {
        $params = null === $params ? (is_array($_GET) ? wp_unslash($_GET) : []) : $params;
        $state = $this->state($settings, $params);
        $post_type = Download_Archive_Data::post_type();
        $args = ['post_type' => $post_type, 'post_status' => 'publish', 'posts_per_page' => max(1, min(48, (int) ($settings['per_page'] ?? 9))), 'paged' => $state['page'], 's' => $state['search']];
        $this->apply_sort($args, $state['sort'], $settings);
        $this->apply_filters($args, $state['filters'], $settings);
        $query = new \WP_Query($args);
        return ['settings' => $settings, 'query' => $query, 'state' => $state, 'facets' => $this->facet_definitions($settings), 'sorts' => ['updated', 'newest', 'title'], 'page' => $state['page'], 'pages' => (int) $query->max_num_pages, 'found' => (int) $query->found_posts, 'url' => $this->state_url($state), 'page_state' => $query->found_posts ? 'ok' : 'empty'];
    }

    public function fragment(string $name, array $ctx): string {
        ob_start();
        if ('toolbar' === $name) $this->render_toolbar($ctx);
        elseif ('facets' === $name) $this->render_facets($ctx);
        elseif ('grid' === $name) $this->render_grid($ctx);
        elseif ('pagination' === $name) $this->render_pagination($ctx);
        elseif ('count' === $name) echo esc_html(sprintf(__('%d نرم‌افزار', 'zig3d-widgets'), $ctx['found']));
        elseif ('sorts' === $name) $this->render_sorts($ctx);
        return (string) ob_get_clean();
    }

    public function has_sidebar(array $settings, array $facets): bool { return 'yes' === ($settings['filters_on'] ?? '') && [] !== $facets; }

    private function state(array $settings, array $params): array {
        $filters = [];
        foreach ($this->facet_definitions($settings) as $facet) {
            $value = sanitize_text_field((string) ($params['filter_' . $facet['key']] ?? ''));
            $filters[$facet['key']] = array_values(array_filter(array_map('sanitize_text_field', explode(',', $value))));
        }
        $sort = sanitize_key((string) ($params['orderby'] ?? 'updated'));
        return ['page' => max(1, (int) ($params['paged'] ?? 1)), 'search' => sanitize_text_field((string) ($params['s'] ?? '')), 'sort' => in_array($sort, ['updated', 'newest', 'title'], true) ? $sort : 'updated', 'filters' => $filters];
    }

    private function facet_definitions(array $s): array {
        $defs = [];
        foreach (['category' => 'primary_taxonomy', 'models' => 'compatible_models', 'os' => 'supported_os', 'brand' => 'software_brand', 'type' => 'file_type', 'arch' => 'system_architecture'] as $slot => $mapping) {
            $source = (string) ($s['filter_' . $slot . '_source'] ?? $s[$mapping] ?? '');
            if ('yes' !== ($s['filter_' . $slot . '_on'] ?? '') || '' === $source) continue;
            $defs[] = ['key' => sanitize_key($source), 'kind' => 'category' === $slot ? 'taxonomy' : 'meta', 'label' => (string) ($s['filter_' . $slot . '_label'] ?? '')];
        }
        return $defs;
    }

    private function apply_sort(array &$args, string $sort, array $s): void {
        if ('title' === $sort) { $args['orderby'] = 'title'; $args['order'] = 'ASC'; }
        elseif ('newest' === $sort || empty($s['release_date'])) { $args['orderby'] = 'date'; $args['order'] = 'DESC'; }
        else { $args['meta_key'] = sanitize_key((string) $s['release_date']); $args['orderby'] = 'meta_value'; $args['order'] = 'DESC'; }
    }

    private function apply_filters(array &$args, array $filters, array $s): void {
        $tax = []; $meta = ['relation' => 'AND'];
        foreach ($this->facet_definitions($s) as $facet) {
            $values = $filters[$facet['key']] ?? [];
            if (!$values) continue;
            if ('taxonomy' === $facet['kind']) $tax[] = ['taxonomy' => $facet['key'], 'field' => 'slug', 'terms' => $values];
            else {
                $group = ['relation' => 'OR'];
                foreach ($values as $value) {
                    $group[] = ['key' => $facet['key'], 'value' => $value, 'compare' => '='];
                    $group[] = ['key' => $facet['key'], 'value' => '"' . $value . '";s:4:"true"', 'compare' => 'LIKE'];
                    $group[] = ['key' => $facet['key'], 'value' => '"' . $value . '":"true"', 'compare' => 'LIKE'];
                    $group[] = ['key' => $facet['key'], 'value' => 'i:[0-9]+;s:[0-9]+:"' . preg_quote($value, '/') . '"', 'compare' => 'REGEXP'];
                }
                $meta[] = $group;
            }
        }
        if ($tax) $args['tax_query'] = array_merge(['relation' => 'AND'], $tax);
        if (count($meta) > 1) $args['meta_query'] = $meta;
    }

    /** @return array<string,array{label:string,count:int}> */
    private function facet_options(array $facet): array {
        if ('taxonomy' === $facet['kind']) {
            $terms = get_terms(['taxonomy' => $facet['key'], 'hide_empty' => true]);
            $out = []; foreach (is_wp_error($terms) ? [] : $terms as $term) $out[$term->slug] = ['label' => $term->name, 'count' => (int) $term->count];
            return $out;
        }
        if (null === $this->facet_post_ids) {
            $this->facet_post_ids = get_posts(['post_type' => Download_Archive_Data::post_type(), 'post_status' => 'publish', 'fields' => 'ids', 'posts_per_page' => -1, 'no_found_rows' => true]);
        }
        $ids = $this->facet_post_ids;
        $out = [];
        foreach ($ids as $id) {
            foreach (Download_Archive_Data::display_values($facet['key'], get_post_meta($id, $facet['key'], true)) as $value => $label) {
                if (!isset($out[$value])) $out[$value] = ['label' => $label, 'count' => 0];
                $out[$value]['count']++;
            }
        }
        uasort($out, static fn(array $a, array $b): int => strnatcasecmp($a['label'], $b['label']));
        return $out;
    }

    /**
     * فیلترهای اعمال‌شده، به‌صورت چیپ — همان بخشی که آرشیوِ محصولات دارد
     * و اینجا نبود (‎Product_Archive::render_active()‎). چیپ‌ها و «پاک‌کردنِ
     * همه» با کلاس‌هایِ *یکسان* ساخته می‌شوند (‎zig-filters__active‎/
     * ‎zig-filters__chip‎/‎zig-filters__clear‎)، پس CSSِ مشترکِ همان بخش
     * بدونِ هیچ تغییری اینجا هم اعمال می‌شود.
     *
     * برچسبِ هر چیپ از ‎facet_options()‎ می‌آید (همان چیزی که خودِ گروه
     * برای نمایشِ گزینه‌ها استفاده می‌کند)، نه از مقدارِ خام — وگرنه چیپِ
     * یک مدلِ دستگاه مثلاً «up400» نشان می‌داد نه برچسبِ خوانای آن.
     */
    private function render_active(array $ctx): void {
        $filters = $ctx['state']['filters'];

        if (!array_filter($filters)) {
            return;
        }

        $chips = [];

        foreach ($ctx['facets'] as $facet) {
            $selected = $filters[$facet['key']] ?? [];

            if (!$selected) {
                continue;
            }

            $options = $this->facet_options($facet);

            foreach ($selected as $value) {
                $chips[] = [
                    'key'   => $facet['key'],
                    'value' => (string) $value,
                    // مقدارِ بی‌گزینه هم چیپ می‌گیرد، وگرنه قیدی می‌ماند که
                    // کاربر می‌بیندش ولی نمی‌تواند برش دارد
                    'label' => (string) ($options[$value]['label'] ?? $value),
                ];
            }
        }

        if (!$chips) {
            return;
        }

        echo '<div class="zig-filters__active"><div class="zig-filters__active-head">';
        printf('<h3 class="zig-filters__active-title">%s</h3>', esc_html__('فیلترهای فعال', 'zig3d-widgets'));

        $cleared = $ctx['state'];
        $cleared['page'] = 1;
        $cleared['filters'] = array_fill_keys(array_keys($filters), []);

        printf(
            '<a class="zig-filters__clear" href="%s" data-zig-clear="1">%s%s</a>',
            esc_url($this->state_url($cleared)),
            esc_html__('پاک‌کردنِ همه', 'zig3d-widgets'),
            Markup::svg_icon('trash', 'zig-filters__clear-icon')
        );

        echo '</div><ul class="zig-filters__chips">';

        foreach ($chips as $chip) {
            $next = $ctx['state'];
            $next['page'] = 1;
            $next['filters'][$chip['key']] = array_values(array_diff($next['filters'][$chip['key']], [$chip['value']]));

            printf(
                '<li class="zig-filters__chip"><a href="%1$s" rel="nofollow" data-zig-toggle="filter_%2$s|%3$s">'
                    . '<span class="zig-filters__chip-text">%4$s</span>%5$s'
                    . '<span class="zig-sr">%6$s</span></a></li>',
                esc_url($this->state_url($next)),
                esc_attr($chip['key']),
                esc_attr($chip['value']),
                esc_html($chip['label']),
                Markup::svg_icon('trash', 'zig-filters__chip-icon'),
                esc_html__('— حذف این فیلتر', 'zig3d-widgets')
            );
        }

        echo '</ul></div>';
    }

    private function render_facets(array $ctx): void {
        $active_count = array_sum(array_map('count', $ctx['state']['filters']));
        echo '<div class="zig-filters__card"><div class="zig-filters__head"><h2 class="zig-filters__title"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M4 6h16M7 12h10M10 18h4"/></svg>' . esc_html__('فیلترها', 'zig3d-widgets') . '</h2>';
        if ($active_count > 0) printf('<span class="zig-filters__badge">%s</span>', esc_html(sprintf(__('%d فیلتر فعال', 'zig3d-widgets'), $active_count)));
        echo '</div>';
        $this->render_active($ctx);
        echo '<div class="zig-filters__groups">';
        $group_index = 0;
        foreach ($ctx['facets'] as $facet) {
            $options = $this->facet_options($facet);
            if (!$options || ('yes' === ($ctx['settings']['hide_small_groups'] ?? '') && count($options) < 2)) continue;
            $has_selected = !empty($ctx['state']['filters'][$facet['key']]);
            $open = $has_selected || 0 === $group_index++;
            $group_id = 'zig-download-facet-' . sanitize_html_class($this->get_id() . '-' . $facet['key']);
            printf('<details class="zig-facet%s"%s><summary class="zig-facet__title" aria-expanded="%s" aria-controls="%s"><span class="zig-facet__name">%s</span><svg class="zig-facet__chevron" aria-hidden="true" viewBox="0 0 20 20"><path d="m6 8 4 4 4-4"/></svg></summary><ul class="zig-facet__list" id="%s">', $has_selected ? ' is-active' : '', $open ? ' open' : '', $open ? 'true' : 'false', esc_attr($group_id), esc_html($facet['label']), esc_attr($group_id));
            foreach ($options as $value => $option) {
                $selected = in_array((string) $value, $ctx['state']['filters'][$facet['key']] ?? [], true);
                $next = $ctx['state']; $next['page'] = 1; $next['filters'][$facet['key']] = $selected ? array_values(array_diff($next['filters'][$facet['key']], [$value])) : array_merge($next['filters'][$facet['key']], [$value]);
                printf('<li class="zig-facet__item%s"><a href="%s" data-zig-toggle="filter_%s|%s"%s><span class="zig-facet__box" aria-hidden="true"></span><bdi dir="auto">%s</bdi><span class="zig-facet__count">%d</span></a></li>', $selected ? ' is-selected' : '', esc_url($this->state_url($next)), esc_attr($facet['key']), esc_attr($value), $selected ? ' aria-current="true"' : '', esc_html($option['label']), (int) $option['count']);
            }
            echo '</ul></details>';
        }
        echo '</div></div>';
    }

    private function render_toolbar(array $ctx): void {
        $s = $ctx['settings'];
        echo '<div class="zig-download-toolbar__scope"><strong>' . esc_html($s['scope_title'] ?? '') . '</strong>';
        if ('yes' === ($s['count_on'] ?? 'yes')) echo '<span data-zig-part="count">' . esc_html(sprintf(__('%d نرم‌افزار', 'zig3d-widgets'), $ctx['found'])) . '</span>';
        echo '</div>';
        printf('<form class="zig-download-toolbar__search" role="search"><label class="screen-reader-text" for="zig-download-search-%s">%s</label><input id="zig-download-search-%s" type="search" name="s" value="%s" placeholder="%s" data-zig-search></form>', esc_attr($this->get_id()), esc_html__('جستجوی نرم‌افزار', 'zig3d-widgets'), esc_attr($this->get_id()), esc_attr($ctx['state']['search']), esc_attr($s['search_placeholder'] ?? ''));
        if ('yes' === ($s['sorting_on'] ?? 'yes')) { echo '<div class="zig-download-toolbar__sort"><span class="zig-download-toolbar__sort-label"><svg aria-hidden="true" viewBox="0 0 20 20"><path d="M5 6h10M7 10h6M9 14h2"/></svg>' . esc_html__('مرتب‌سازی:', 'zig3d-widgets') . '</span><span data-zig-part="sorts">'; $this->render_sorts($ctx); echo '</span></div>'; }
    }

    private function render_sorts(array $ctx): void {
        foreach (['updated' => __('آخرین بروزرسانی', 'zig3d-widgets'), 'newest' => __('جدیدترین انتشار', 'zig3d-widgets'), 'title' => __('الفبایی', 'zig3d-widgets')] as $key => $label) {
            $next = $ctx['state']; $next['sort'] = $key; $next['page'] = 1;
            printf('<a class="zig-download-sort%s" href="%s" data-zig-sort="%s">%s</a>', $ctx['state']['sort'] === $key ? ' is-active' : '', esc_url($this->state_url($next)), esc_attr($key), esc_html($label));
        }
    }

    private function render_grid(array $ctx): void {
        if (!$ctx['query']->have_posts()) { echo '<div class="zig-download-archive__empty">' . esc_html($ctx['settings']['empty_text'] ?? '') . '</div>'; return; }
        echo '<div class="zig-download-list">'; while ($ctx['query']->have_posts()) { $ctx['query']->the_post(); $this->render_card(get_the_ID(), $ctx['settings']); } echo '</div>';
    }

    private function render_card(int $id, array $s): void {
        $title = $this->text($id, $s['title_field'] ?? '', get_the_title($id));
        $desc = $this->text($id, $s['description_field'] ?? '', get_the_excerpt($id));
        $download = !empty($s['download_url']) ? esc_url((string) get_post_meta($id, $s['download_url'], true)) : '';
        $details = !empty($s['details_url']) ? esc_url((string) get_post_meta($id, $s['details_url'], true)) : get_permalink($id);
        $primary_taxonomy = (string) ($s['primary_taxonomy'] ?? '');
        $label_taxonomy = (string) ($s['label_taxonomy'] ?? '');
        $category = $this->terms($id, $primary_taxonomy);
        $labels = '' !== $label_taxonomy && $label_taxonomy !== $primary_taxonomy ? $this->terms($id, $label_taxonomy) : [];
        $models_key = (string) ($s['compatible_models'] ?? '');
        $os_key = (string) ($s['supported_os'] ?? '');
        $models = $models_key ? Download_Archive_Data::display_values($models_key, get_post_meta($id, $models_key, true)) : [];
        $systems = $os_key ? Download_Archive_Data::display_values($os_key, get_post_meta($id, $os_key, true)) : [];
        echo '<article class="zig-download-card">';
        if ($category) echo '<div class="zig-download-card__category">' . esc_html($category[0]) . '</div>';
        if ($labels) { echo '<div class="zig-download-card__labels">'; foreach ($labels as $label) echo '<span>' . esc_html($label) . '</span>'; echo '</div>'; }
        printf('<h3 class="zig-download-card__title"><a href="%s"><bdi dir="auto">%s</bdi></a></h3>', esc_url($details), esc_html($title));
        if (Markup::filled($desc)) echo '<p class="zig-download-card__description">' . esc_html($desc) . '</p>';
        if ($models) $this->render_compatibility($id, $models, $s);
        ob_start();
        $this->meta($id, $s['software_version'] ?? '', __('نسخه', 'zig3d-widgets'));
        $bytes = (int) get_post_meta($id, Download_Archive_Data::SIZE_META, true); if ($bytes > 0) printf('<div><dt>%s</dt><dd dir="ltr"><bdi>%s</bdi></dd></div>', esc_html__('حجم', 'zig3d-widgets'), esc_html(size_format($bytes, 1)));
        $this->meta($id, $s['release_date'] ?? '', __('بروزرسانی', 'zig3d-widgets'));
        $this->meta_values($systems, __('سیستم‌عامل', 'zig3d-widgets'), 'zig-download-card__meta-os-value');
        $metadata = (string) ob_get_clean();
        if ('' !== $metadata || $download || $details) {
            echo '<footer class="zig-download-card__footer">';
            if ('' !== $metadata) echo '<dl class="zig-download-card__meta">' . $metadata . '</dl>';
            if ($download || $details) {
                echo '<div class="zig-download-card__actions">';
                if ($download) printf('<a class="zig-download-card__download" href="%s" download>%s</a>', $download, esc_html($s['download_text'] ?? ''));
                if ($details) printf('<a class="zig-download-card__details" href="%s">%s</a>', esc_url($details), esc_html($s['details_text'] ?? ''));
                echo '</div>';
            }
            echo '</footer>';
        }
        echo '</article>';
    }

    /** @param array<string,string> $models */
    private function render_compatibility(int $id, array $models, array $s): void {
        $desktop_limit = max(1, min(12, (int) ($s['models_preview_desktop'] ?? 6)));
        $mobile_limit = max(1, min(10, (int) ($s['models_preview_mobile'] ?? 4)));
        $total = count($models);
        $list_id = 'zig-download-models-' . sanitize_html_class($this->get_id() . '-' . $id);
        echo '<section class="zig-download-card__compatibility" aria-label="' . esc_attr__('سازگاری', 'zig3d-widgets') . '">';
        if ($models) {
            printf('<h4 class="zig-download-card__compatibility-head"><span>%s</span><span class="zig-download-card__compatibility-separator" aria-hidden="true">·</span><span class="zig-download-card__models-count">%s</span></h4>', esc_html__('دستگاه‌های سازگار', 'zig3d-widgets'), esc_html(sprintf(__('%d مدل', 'zig3d-widgets'), $total)));
            printf('<div class="zig-download-card__models" id="%s">', esc_attr($list_id));
            $index = 0;
            foreach ($models as $label) {
                $classes = ['zig-download-card__model'];
                if ($index >= $desktop_limit) $classes[] = 'is-desktop-overflow';
                if ($index >= $mobile_limit) $classes[] = 'is-mobile-overflow';
                printf('<span class="%s"><bdi dir="%s">%s</bdi></span>', esc_attr(implode(' ', $classes)), esc_attr($this->value_direction($label)), esc_html($label));
                $index++;
            }
            echo '</div>';
            if ($total > min($desktop_limit, $mobile_limit)) {
                $desktop_remaining = max(0, $total - $desktop_limit);
                $mobile_remaining = max(0, $total - $mobile_limit);
                printf('<button class="zig-download-card__models-toggle%s%s" type="button" aria-expanded="false" aria-controls="%s"><span class="zig-download-card__toggle-more zig-download-card__toggle-more--desktop">%s</span><span class="zig-download-card__toggle-more zig-download-card__toggle-more--mobile">%s</span><span class="zig-download-card__toggle-less">%s</span><svg class="zig-download-card__toggle-chevron" aria-hidden="true" viewBox="0 0 16 16"><path d="m4 6 4 4 4-4"/></svg></button>', 0 === $desktop_remaining ? ' is-desktop-hidden' : '', 0 === $mobile_remaining ? ' is-mobile-hidden' : '', esc_attr($list_id), esc_html(sprintf(__('مشاهده %d دستگاه دیگر', 'zig3d-widgets'), $desktop_remaining)), esc_html(sprintf(__('مشاهده %d دستگاه دیگر', 'zig3d-widgets'), $mobile_remaining)), esc_html__('نمایش کمتر', 'zig3d-widgets'));
            }
        }
        echo '</section>';
    }

    private function text(int $id, string $key, string $fallback): string { $value = '__post_content' === $key ? get_post_field('post_content', $id) : ($key ? get_post_meta($id, $key, true) : ''); return Markup::filled($value) ? trim(wp_strip_all_tags((string) $value)) : $fallback; }
    private function terms(int $id, string $tax): array { if (!$tax) return []; $terms = get_the_terms($id, $tax); return is_wp_error($terms) || !$terms ? [] : array_map(fn($t) => $t->name, $terms); }
    private function meta(int $id, string $key, string $label): void { if (!$key) return; $value = get_post_meta($id, $key, true); if (!Markup::filled($value) || is_array($value)) return; $display = Download_Archive_Data::option_label($key, (string) $value); printf('<div><dt>%s</dt><dd><bdi dir="%s">%s</bdi></dd></div>', esc_html($label), esc_attr($this->value_direction($display)), esc_html($display)); }
    /** @param array<string,string> $values */
    private function meta_values(array $values, string $label, string $value_class): void {
        if (!$values) return;
        echo '<div><dt>' . esc_html($label) . '</dt><dd class="zig-download-card__meta-values">';
        foreach ($values as $value) printf('<bdi class="%s" dir="%s">%s</bdi>', esc_attr($value_class), esc_attr($this->value_direction($value)), esc_html($value));
        echo '</dd></div>';
    }
    private function value_direction(string $value): string { return preg_match('/[\x{0600}-\x{06FF}]/u', $value) ? 'rtl' : 'ltr'; }

    /**
     * دقیقاً همان مارک‌آپ/کلاس‌هایِ صفحه‌بندیِ آرشیوِ محصولات
     * (‎Product_Archive::render_pagination()‎) — پیوندِ قبلی/بعدی با
     * ‎rel=prev/next‎، شمارهٔ صفحهٔ جاری با ‎aria-current‎، و ارقامِ فارسی.
     * چون هر دو ویجت رویِ همان ‎zig3d-archive.js‎ سوارند، همین شباهتِ
     * مارک‌آپ کافی است تا CSSِ مشترک (‎.zig-archive__pagination‎/
     * ‎.zig-page--prev‎/‎.zig-page--next‎) بدونِ هیچ تغییرِ CSSای اعمال شود.
     */
    private function render_pagination(array $ctx): void {
        if ($ctx['pages'] < 2) {
            return;
        }

        $s    = $ctx['settings'];
        $page = $ctx['page'];

        echo '<nav class="zig-archive__pagination" aria-label="' . esc_attr__('صفحه‌بندی', 'zig3d-widgets') . '">';

        if ($page > 1) {
            $prev = $ctx['state'];
            $prev['page'] = $page - 1;
            printf(
                '<a class="zig-page zig-page--prev" href="%s" rel="prev" data-zig-goto="%s">%s</a>',
                esc_url($this->state_url($prev)),
                (string) ($page - 1),
                esc_html($s['label_prev'] ?? '')
            );
        }

        for ($number = 1; $number <= $ctx['pages']; ++$number) {
            if ($number === $page) {
                printf(
                    '<span class="zig-page is-current" aria-current="page">%s</span>',
                    esc_html(Price::persian((string) $number))
                );

                continue;
            }

            $next = $ctx['state'];
            $next['page'] = $number;
            printf(
                '<a class="zig-page" href="%s" data-zig-goto="%s">%s</a>',
                esc_url($this->state_url($next)),
                (string) $number,
                esc_html(Price::persian((string) $number))
            );
        }

        if ($page < $ctx['pages']) {
            $forward = $ctx['state'];
            $forward['page'] = $page + 1;
            printf(
                '<a class="zig-page zig-page--next" href="%s" rel="next" data-zig-goto="%s">%s</a>',
                esc_url($this->state_url($forward)),
                (string) ($page + 1),
                esc_html($s['label_next'] ?? '')
            );
        }

        echo '</nav>';
    }

    private function state_url(array $state): string {
        $args = []; if ($state['search']) $args['s'] = $state['search']; if ('updated' !== $state['sort']) $args['orderby'] = $state['sort']; if ($state['page'] > 1) $args['paged'] = $state['page'];
        foreach ($state['filters'] as $key => $values) if ($values) $args['filter_' . $key] = implode(',', $values);
        return add_query_arg($args, remove_query_arg(array_merge(['s', 'orderby', 'paged'], array_map(fn($k) => 'filter_' . $k, array_keys($state['filters']))), $this->base_url()));
    }
    private function base_url(): string { return get_permalink($this->document_id()) ?: home_url('/'); }
    private function document_id(): int { if (class_exists('\\Elementor\\Plugin')) { $doc = \Elementor\Plugin::$instance->documents->get_current(); if ($doc) return (int) $doc->get_main_id(); } return (int) get_the_ID(); }
    private function is_edit_mode(): bool { return class_exists('\\Elementor\\Plugin') && isset(\Elementor\Plugin::$instance->editor) && \Elementor\Plugin::$instance->editor->is_edit_mode(); }
}
