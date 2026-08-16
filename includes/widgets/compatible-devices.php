<?php
namespace Zig3d_Widgets\Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;
use Zig3d_Widgets\Download_Archive_Data;
use Zig3d_Widgets\Plugin;

if (!defined('ABSPATH')) { exit; }

final class Compatible_Devices extends Widget_Base {
    public function get_name(): string { return 'zig3d-compatible-devices'; }
    public function get_title(): string { return __('دستگاه‌های سازگار', 'zig3d-widgets'); }
    public function get_icon(): string { return 'eicon-tags'; }
    public function get_categories(): array { return [Plugin::CATEGORY]; }
    public function get_style_depends(): array { return ['zig3d-widgets']; }
    public function has_widget_inner_wrapper(): bool { return false; }

    protected function register_controls(): void {
        $this->start_controls_section('content', ['label' => __('محتوا', 'zig3d-widgets')]);
        $this->add_control('show_heading', ['label' => __('نمایش عنوان', 'zig3d-widgets'), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes']);
        $this->add_control('heading', ['label' => __('متن عنوان', 'zig3d-widgets'), 'type' => Controls_Manager::TEXT, 'default' => __('دستگاه‌های سازگار', 'zig3d-widgets'), 'condition' => ['show_heading' => 'yes']]);
        $this->add_control('field', ['label' => __('منبع مدل دستگاه', 'zig3d-widgets'), 'type' => Controls_Manager::SELECT, 'options' => Download_Archive_Data::field_options('multi'), 'default' => Download_Archive_Data::default_field('compatible_models')]);
        $this->add_control('preview_desktop', ['label' => __('تعداد دستگاه‌های قابل نمایش در دسکتاپ', 'zig3d-widgets'), 'type' => Controls_Manager::NUMBER, 'default' => 5, 'min' => 1, 'max' => 30]);
        $this->add_control('preview_mobile', ['label' => __('تعداد دستگاه‌های قابل نمایش در موبایل', 'zig3d-widgets'), 'type' => Controls_Manager::NUMBER, 'default' => 4, 'min' => 1, 'max' => 20]);
        $this->add_control('show_more_link', ['label' => __('نمایش لینک مشاهده بیشتر', 'zig3d-widgets'), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes']);
        $this->add_control('more_text', ['label' => __('متن لینک', 'zig3d-widgets'), 'type' => Controls_Manager::TEXT, 'default' => __('مشاهده بیشتر', 'zig3d-widgets'), 'condition' => ['show_more_link' => 'yes']]);
        $this->add_control('more_url', ['label' => __('لینک مشاهده بیشتر', 'zig3d-widgets'), 'type' => Controls_Manager::URL, 'placeholder' => '#compatible-devices-details', 'options' => ['url', 'is_external', 'nofollow', 'custom_attributes'], 'condition' => ['show_more_link' => 'yes']]);
        $this->end_controls_section();

        $this->start_controls_section('heading_style', ['label' => __('عنوان', 'zig3d-widgets'), 'tab' => Controls_Manager::TAB_STYLE]);
        $this->add_group_control(Group_Control_Typography::get_type(), ['name' => 'heading_typography', 'selector' => '{{WRAPPER}} .zig-compatible-devices__heading']);
        $this->add_control('heading_color', ['label' => __('رنگ', 'zig3d-widgets'), 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .zig-compatible-devices__heading' => 'color: {{VALUE}};']]);
        $this->add_responsive_control('heading_gap', ['label' => __('فاصله عنوان تا مدلها', 'zig3d-widgets'), 'type' => Controls_Manager::SLIDER, 'selectors' => ['{{WRAPPER}} .zig-compatible-devices' => '--zig-compat-heading-gap: {{SIZE}}{{UNIT}};']]);
        $this->end_controls_section();

        $this->start_controls_section('models_style', ['label' => __('مدل دستگاه', 'zig3d-widgets'), 'tab' => Controls_Manager::TAB_STYLE]);
        $model = '{{WRAPPER}} .zig-compatible-devices__item';
        $this->add_group_control(Group_Control_Typography::get_type(), ['name' => 'model_typography', 'selector' => $model]);
        foreach (['model_color' => ['رنگ متن', 'color'], 'model_background' => ['پس‌زمینه', 'background-color'], 'model_border_color' => ['رنگ حاشیه', 'border-color']] as $id => [$label, $property]) {
            $this->add_control($id, ['label' => __($label, 'zig3d-widgets'), 'type' => Controls_Manager::COLOR, 'selectors' => [$model => $property . ': {{VALUE}};']]);
        }
        $this->add_control('model_border_width', ['label' => __('ضخامت حاشیه', 'zig3d-widgets'), 'type' => Controls_Manager::DIMENSIONS, 'selectors' => [$model => 'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};']]);
        $this->add_control('model_radius', ['label' => __('گردی گوشه‌ها', 'zig3d-widgets'), 'type' => Controls_Manager::SLIDER, 'selectors' => [$model => 'border-radius: {{SIZE}}{{UNIT}};']]);
        $this->add_responsive_control('model_padding', ['label' => __('فاصله داخلی', 'zig3d-widgets'), 'type' => Controls_Manager::DIMENSIONS, 'selectors' => [$model => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};']]);
        $this->add_responsive_control('models_gap', ['label' => __('فاصله بین مدلها', 'zig3d-widgets'), 'type' => Controls_Manager::SLIDER, 'selectors' => ['{{WRAPPER}} .zig-compatible-devices__items' => 'gap: {{SIZE}}{{UNIT}};']]);
        $this->end_controls_section();

        $this->start_controls_section('toggle_style', ['label' => __('مشاهده بیشتر', 'zig3d-widgets'), 'tab' => Controls_Manager::TAB_STYLE]);
        $this->add_group_control(Group_Control_Typography::get_type(), ['name' => 'toggle_typography', 'selector' => '{{WRAPPER}} .zig-compatible-devices__more-link']);
        $this->start_controls_tabs('toggle_tabs');
        $this->start_controls_tab('toggle_normal', ['label' => __('عادی', 'zig3d-widgets')]);
        $this->add_control('toggle_color', ['label' => __('رنگ', 'zig3d-widgets'), 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .zig-compatible-devices__more-link' => 'color: {{VALUE}};']]);
        $this->add_control('toggle_background', ['label' => __('پس‌زمینه', 'zig3d-widgets'), 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .zig-compatible-devices__more-link' => 'background-color: {{VALUE}};']]);
        $this->add_control('toggle_border_color', ['label' => __('رنگ حاشیه', 'zig3d-widgets'), 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .zig-compatible-devices__more-link' => 'border-color: {{VALUE}};']]);
        $this->add_control('toggle_icon_color', ['label' => __('رنگ فلش', 'zig3d-widgets'), 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .zig-compatible-devices__chevron' => 'color: {{VALUE}};']]);
        $this->end_controls_tab();
        $this->start_controls_tab('toggle_hover', ['label' => __('هاور', 'zig3d-widgets')]);
        $this->add_control('toggle_hover_color', ['label' => __('رنگ هاور', 'zig3d-widgets'), 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .zig-compatible-devices__more-link:hover' => 'color: {{VALUE}};']]);
        $this->add_control('toggle_hover_background', ['label' => __('پس‌زمینه هاور', 'zig3d-widgets'), 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .zig-compatible-devices__more-link:hover' => 'background-color: {{VALUE}};']]);
        $this->add_control('toggle_hover_border_color', ['label' => __('رنگ حاشیه هاور', 'zig3d-widgets'), 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .zig-compatible-devices__more-link:hover' => 'border-color: {{VALUE}};']]);
        $this->add_control('toggle_hover_icon_color', ['label' => __('رنگ فلش هاور', 'zig3d-widgets'), 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .zig-compatible-devices__more-link:hover .zig-compatible-devices__chevron' => 'color: {{VALUE}};']]);
        $this->end_controls_tab();
        $this->end_controls_tabs();
        $this->add_control('toggle_radius', ['label' => __('گردی گوشه‌ها', 'zig3d-widgets'), 'type' => Controls_Manager::SLIDER, 'selectors' => ['{{WRAPPER}} .zig-compatible-devices__more-link' => 'border-radius: {{SIZE}}{{UNIT}};']]);
        $this->add_responsive_control('toggle_padding', ['label' => __('فاصله داخلی', 'zig3d-widgets'), 'type' => Controls_Manager::DIMENSIONS, 'selectors' => ['{{WRAPPER}} .zig-compatible-devices__more-link' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};']]);
        $this->add_responsive_control('toggle_gap', ['label' => __('فاصله لینک مشاهده بیشتر', 'zig3d-widgets'), 'type' => Controls_Manager::SLIDER, 'selectors' => ['{{WRAPPER}} .zig-compatible-devices' => '--zig-compat-more-gap: {{SIZE}}{{UNIT}};']]);
        $this->add_control('link_icon_gap', ['label' => __('فاصله متن و فلش', 'zig3d-widgets'), 'type' => Controls_Manager::SLIDER, 'selectors' => ['{{WRAPPER}} .zig-compatible-devices__more-link' => 'gap: {{SIZE}}{{UNIT}};']]);
        $this->add_control('chevron_size', ['label' => __('اندازه فلش', 'zig3d-widgets'), 'type' => Controls_Manager::SLIDER, 'selectors' => ['{{WRAPPER}} .zig-compatible-devices__chevron' => 'inline-size: {{SIZE}}{{UNIT}}; block-size: {{SIZE}}{{UNIT}};']]);
        $this->end_controls_section();
    }

    protected function render(): void {
        $settings = $this->get_settings_for_display();
        $field = (string) ($settings['field'] ?? Download_Archive_Data::default_field('compatible_models'));
        $values = '' !== $field ? Download_Archive_Data::current_display_values($field) : [];
        if (!$values) { return; }
        $desktop = max(1, min(30, (int) ($settings['preview_desktop'] ?? 5)));
        $mobile = max(1, min(20, (int) ($settings['preview_mobile'] ?? 4)));
        echo '<section class="zig-compatible-devices" dir="rtl">';
        if ('yes' === ($settings['show_heading'] ?? 'yes') && '' !== trim((string) ($settings['heading'] ?? ''))) {
            echo '<h3 class="zig-compatible-devices__heading">' . esc_html($settings['heading']) . '</h3>';
        }
        echo '<div class="zig-compatible-devices__items">';
        $index = 0;
        foreach ($values as $label) {
            $classes = ['zig-compatible-devices__item'];
            if ($index >= $desktop) { $classes[] = 'is-desktop-overflow'; }
            if ($index >= $mobile) { $classes[] = 'is-mobile-overflow'; }
            echo '<span class="' . esc_attr(implode(' ', $classes)) . '"><bdi dir="ltr">' . esc_html($label) . '</bdi></span>';
            ++$index;
        }
        echo '</div>';
        $link = is_array($settings['more_url'] ?? null) ? $settings['more_url'] : [];
        $link_text = trim((string) ($settings['more_text'] ?? __('مشاهده بیشتر', 'zig3d-widgets')));
        if ('yes' === ($settings['show_more_link'] ?? 'yes') && '' !== $link_text && !empty($link['url'])) {
            $this->add_render_attribute('more_link', 'class', 'zig-compatible-devices__more-link');
            $this->add_link_attributes('more_link', $link);
            echo '<a ' . $this->get_render_attribute_string('more_link') . '><span>' . esc_html($link_text) . '</span><svg class="zig-compatible-devices__chevron" viewBox="0 0 16 16" aria-hidden="true"><path d="m10 4-4 4 4 4"/></svg></a>';
        }
        echo '</section>';
    }
}
