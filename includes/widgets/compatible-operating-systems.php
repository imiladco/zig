<?php
namespace Zig3d_Widgets\Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;
use Zig3d_Widgets\Download_Archive_Data;
use Zig3d_Widgets\Plugin;

if (!defined('ABSPATH')) { exit; }

final class Compatible_Operating_Systems extends Widget_Base {
    public function get_name(): string { return 'zig3d-compatible-operating-systems'; }
    public function get_title(): string { return __('سیستم‌عامل‌های سازگار', 'zig3d-widgets'); }
    public function get_icon(): string { return 'eicon-device-desktop'; }
    public function get_categories(): array { return [Plugin::CATEGORY]; }
    public function get_style_depends(): array { return ['zig3d-widgets']; }
    public function has_widget_inner_wrapper(): bool { return false; }

    protected function register_controls(): void {
        $this->start_controls_section('content', ['label' => __('محتوا', 'zig3d-widgets')]);
        $this->add_control('show_heading', ['label' => __('نمایش عنوان', 'zig3d-widgets'), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes']);
        $this->add_control('heading', ['label' => __('متن عنوان', 'zig3d-widgets'), 'type' => Controls_Manager::TEXT, 'default' => __('سیستم‌عامل‌های سازگار', 'zig3d-widgets'), 'condition' => ['show_heading' => 'yes']]);
        $this->add_control('field', ['label' => __('منبع سیستم‌عامل', 'zig3d-widgets'), 'type' => Controls_Manager::SELECT, 'options' => Download_Archive_Data::field_options('multi'), 'default' => Download_Archive_Data::default_field('supported_os')]);
        $this->add_control('show_icon', ['label' => __('نمایش آیکون', 'zig3d-widgets'), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes']);
        $this->end_controls_section();

        $this->start_controls_section('heading_style', ['label' => __('عنوان', 'zig3d-widgets'), 'tab' => Controls_Manager::TAB_STYLE]);
        $this->add_group_control(Group_Control_Typography::get_type(), ['name' => 'heading_typography', 'selector' => '{{WRAPPER}} .zig-compatible-os__heading']);
        $this->add_control('heading_color', ['label' => __('رنگ', 'zig3d-widgets'), 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .zig-compatible-os__heading' => 'color: {{VALUE}};']]);
        $this->add_responsive_control('heading_gap', ['label' => __('فاصله عنوان تا آیتم‌ها', 'zig3d-widgets'), 'type' => Controls_Manager::SLIDER, 'selectors' => ['{{WRAPPER}} .zig-compatible-os' => '--zig-compat-heading-gap: {{SIZE}}{{UNIT}};']]);
        $this->end_controls_section();

        $this->start_controls_section('items_style', ['label' => __('سیستم‌عامل‌ها', 'zig3d-widgets'), 'tab' => Controls_Manager::TAB_STYLE]);
        $item = '{{WRAPPER}} .zig-compatible-os__item';
        $this->add_group_control(Group_Control_Typography::get_type(), ['name' => 'item_typography', 'selector' => $item]);
        foreach (['item_color' => ['رنگ متن', 'color'], 'item_background' => ['پس‌زمینه', 'background-color'], 'item_border_color' => ['رنگ حاشیه', 'border-color']] as $id => [$label, $property]) {
            $this->add_control($id, ['label' => __($label, 'zig3d-widgets'), 'type' => Controls_Manager::COLOR, 'selectors' => [$item => $property . ': {{VALUE}};']]);
        }
        $this->add_control('item_border_width', ['label' => __('ضخامت حاشیه', 'zig3d-widgets'), 'type' => Controls_Manager::DIMENSIONS, 'selectors' => [$item => 'border-width: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};']]);
        $this->add_control('item_radius', ['label' => __('گردی گوشه‌ها', 'zig3d-widgets'), 'type' => Controls_Manager::SLIDER, 'selectors' => [$item => 'border-radius: {{SIZE}}{{UNIT}};']]);
        $this->add_responsive_control('item_padding', ['label' => __('فاصله داخلی', 'zig3d-widgets'), 'type' => Controls_Manager::DIMENSIONS, 'selectors' => [$item => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};']]);
        $this->add_responsive_control('items_gap', ['label' => __('فاصله بین موارد', 'zig3d-widgets'), 'type' => Controls_Manager::SLIDER, 'selectors' => ['{{WRAPPER}} .zig-compatible-os__items' => 'gap: {{SIZE}}{{UNIT}};']]);
        $this->add_control('icon_size', ['label' => __('اندازه آیکون', 'zig3d-widgets'), 'type' => Controls_Manager::SLIDER, 'selectors' => ['{{WRAPPER}} .zig-compatible-os__icon' => 'inline-size: {{SIZE}}{{UNIT}}; block-size: {{SIZE}}{{UNIT}};']]);
        $this->add_control('icon_color', ['label' => __('رنگ آیکون', 'zig3d-widgets'), 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .zig-compatible-os__icon' => 'color: {{VALUE}};']]);
        $this->end_controls_section();
    }

    protected function render(): void {
        $settings = $this->get_settings_for_display();
        $field = (string) ($settings['field'] ?? Download_Archive_Data::default_field('supported_os'));
        $values = '' !== $field ? Download_Archive_Data::current_display_values($field) : [];
        if (!$values) { return; }
        echo '<section class="zig-compatible-os" dir="rtl">';
        if ('yes' === ($settings['show_heading'] ?? 'yes') && '' !== trim((string) ($settings['heading'] ?? ''))) {
            echo '<h3 class="zig-compatible-os__heading">' . esc_html($settings['heading']) . '</h3>';
        }
        echo '<div class="zig-compatible-os__items">';
        foreach ($values as $key => $label) {
            echo '<span class="zig-compatible-os__item">';
            if ('yes' === ($settings['show_icon'] ?? 'yes') && 0 === strpos(strtolower((string) $key), 'windows')) {
                echo '<svg class="zig-compatible-os__icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3.8 10.7 2.7v8.1H3V3.8Zm8.8-1.3L21 1.2v9.6h-9.2V2.5ZM3 12h7.7v8.1L3 19V12Zm8.8 0H21v9.6l-9.2-1.3V12Z" fill="currentColor"/></svg>';
            }
            echo '<bdi dir="ltr">' . esc_html($label) . '</bdi></span>';
        }
        echo '</div></section>';
    }
}
