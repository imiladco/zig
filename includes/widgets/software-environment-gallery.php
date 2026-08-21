<?php
namespace Zig3d_Widgets\Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;
use Zig3d_Widgets\Download_Archive_Data;
use Zig3d_Widgets\Plugin;

if (!defined('ABSPATH')) { exit; }

final class Software_Environment_Gallery extends Widget_Base {
    public function get_name(): string { return 'zig3d-software-environment-gallery'; }
    public function get_title(): string { return __('گالری محیط نرم‌افزار', 'zig3d-widgets'); }
    public function get_icon(): string { return 'eicon-gallery-grid'; }
    public function get_categories(): array { return [Plugin::CATEGORY]; }
    public function get_style_depends(): array { return ['zig3d-widgets']; }
    public function get_script_depends(): array { return ['zig3d-software-gallery']; }
    public function has_widget_inner_wrapper(): bool { return false; }

    protected function register_controls(): void {
        $this->start_controls_section('content', ['label' => __('محتوا', 'zig3d-widgets')]);
        $this->add_control('show_heading', ['label' => __('نمایش عنوان', 'zig3d-widgets'), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes']);
        $this->add_control('heading', ['label' => __('عنوان', 'zig3d-widgets'), 'type' => Controls_Manager::TEXT, 'default' => __('محیط نرم‌افزار', 'zig3d-widgets'), 'condition' => ['show_heading' => 'yes']]);
        $this->add_control('show_description', ['label' => __('نمایش توضیحات', 'zig3d-widgets'), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes']);
        $this->add_control('description', ['label' => __('توضیحات', 'zig3d-widgets'), 'type' => Controls_Manager::TEXTAREA, 'default' => '', 'condition' => ['show_description' => 'yes']]);
        $this->add_control('gallery_field', ['label' => __('منبع گالری', 'zig3d-widgets'), 'type' => Controls_Manager::SELECT, 'options' => Download_Archive_Data::gallery_field_options(), 'default' => Download_Archive_Data::default_gallery_field()]);
        $this->add_control('image_fit', ['label' => __('نحوه نمایش تصویر', 'zig3d-widgets'), 'type' => Controls_Manager::SELECT, 'default' => 'cover', 'options' => ['cover' => __('Cover', 'zig3d-widgets'), 'contain' => __('Contain', 'zig3d-widgets')], 'selectors' => ['{{WRAPPER}} .zig-software-gallery__main-image' => 'object-fit: {{VALUE}};']]);
        $this->add_control('hide_empty', ['label' => __('مخفی کردن ویجت در صورت نبود تصویر', 'zig3d-widgets'), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes']);
        $this->add_control('show_arrows', ['label' => __('نمایش فلش‌های ناوبری', 'zig3d-widgets'), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes']);
        $this->add_control('show_thumbnails', ['label' => __('نمایش تصاویر کوچک', 'zig3d-widgets'), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes']);
        $this->end_controls_section();

        $this->start_controls_section('header_style', ['label' => __('سربرگ', 'zig3d-widgets'), 'tab' => Controls_Manager::TAB_STYLE]);
        $this->add_group_control(Group_Control_Typography::get_type(), ['name' => 'title_typography', 'selector' => '{{WRAPPER}} .zig-software-gallery__title']);
        $this->add_control('title_color', ['label' => __('رنگ عنوان', 'zig3d-widgets'), 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .zig-software-gallery__title' => 'color: {{VALUE}};']]);
        $this->add_group_control(Group_Control_Typography::get_type(), ['name' => 'description_typography', 'selector' => '{{WRAPPER}} .zig-software-gallery__description']);
        $this->add_control('description_color', ['label' => __('رنگ توضیحات', 'zig3d-widgets'), 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .zig-software-gallery__description' => 'color: {{VALUE}};']]);
        $this->add_responsive_control('title_description_gap', ['label' => __('فاصله عنوان تا توضیحات', 'zig3d-widgets'), 'type' => Controls_Manager::SLIDER, 'selectors' => ['{{WRAPPER}} .zig-software-gallery__description' => 'margin-block-start: {{SIZE}}{{UNIT}};']]);
        $this->add_responsive_control('header_gallery_gap', ['label' => __('فاصله سربرگ تا گالری', 'zig3d-widgets'), 'type' => Controls_Manager::SLIDER, 'selectors' => ['{{WRAPPER}} .zig-software-gallery__viewer' => 'margin-block-start: {{SIZE}}{{UNIT}};']]);
        $this->end_controls_section();

        $this->start_controls_section('main_style', ['label' => __('تصویر اصلی', 'zig3d-widgets'), 'tab' => Controls_Manager::TAB_STYLE]);
        $this->add_control('main_ratio', ['label' => __('نسبت تصویر اصلی', 'zig3d-widgets'), 'type' => Controls_Manager::SELECT, 'default' => '16 / 9', 'options' => ['16 / 9' => '16:9', '4 / 3' => '4:3', '3 / 2' => '3:2'], 'selectors' => ['{{WRAPPER}} .zig-software-gallery__stage' => 'aspect-ratio: {{VALUE}};']]);
        $this->add_control('main_background', ['label' => __('پس‌زمینه', 'zig3d-widgets'), 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .zig-software-gallery__stage' => 'background-color: {{VALUE}};']]);
        $this->add_control('main_radius', ['label' => __('گردی گوشه‌ها', 'zig3d-widgets'), 'type' => Controls_Manager::SLIDER, 'selectors' => ['{{WRAPPER}} .zig-software-gallery__stage' => 'border-radius: {{SIZE}}{{UNIT}};']]);
        $this->end_controls_section();

        $this->start_controls_section('navigation_style', ['label' => __('ناوبری', 'zig3d-widgets'), 'tab' => Controls_Manager::TAB_STYLE]);
        $this->add_control('nav_button_size', ['label' => __('اندازه دکمه', 'zig3d-widgets'), 'type' => Controls_Manager::SLIDER, 'selectors' => ['{{WRAPPER}} .zig-software-gallery__arrow' => 'inline-size: {{SIZE}}{{UNIT}}; block-size: {{SIZE}}{{UNIT}};']]);
        $this->add_control('nav_icon_size', ['label' => __('اندازه فلش', 'zig3d-widgets'), 'type' => Controls_Manager::SLIDER, 'selectors' => ['{{WRAPPER}} .zig-software-gallery__arrow svg' => 'inline-size: {{SIZE}}{{UNIT}}; block-size: {{SIZE}}{{UNIT}};']]);
        $this->start_controls_tabs('nav_tabs');
        $this->start_controls_tab('nav_normal', ['label' => __('عادی', 'zig3d-widgets')]);
        $this->add_control('nav_color', ['label' => __('رنگ فلش', 'zig3d-widgets'), 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .zig-software-gallery__arrow' => 'color: {{VALUE}};']]);
        $this->add_control('nav_background', ['label' => __('پس‌زمینه', 'zig3d-widgets'), 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .zig-software-gallery__arrow' => 'background-color: {{VALUE}};']]);
        $this->end_controls_tab();
        $this->start_controls_tab('nav_hover', ['label' => __('هاور', 'zig3d-widgets')]);
        $this->add_control('nav_hover_color', ['label' => __('رنگ فلش', 'zig3d-widgets'), 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .zig-software-gallery__arrow:hover' => 'color: {{VALUE}};']]);
        $this->add_control('nav_hover_background', ['label' => __('پس‌زمینه', 'zig3d-widgets'), 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .zig-software-gallery__arrow:hover' => 'background-color: {{VALUE}};']]);
        $this->end_controls_tab();
        $this->end_controls_tabs();
        $this->add_control('nav_inset', ['label' => __('فاصله افقی', 'zig3d-widgets'), 'type' => Controls_Manager::SLIDER, 'selectors' => ['{{WRAPPER}} .zig-software-gallery__arrow--previous' => 'inset-inline-start: {{SIZE}}{{UNIT}};', '{{WRAPPER}} .zig-software-gallery__arrow--next' => 'inset-inline-end: {{SIZE}}{{UNIT}};']]);
        $this->end_controls_section();

        $this->start_controls_section('thumb_style', ['label' => __('تصاویر کوچک', 'zig3d-widgets'), 'tab' => Controls_Manager::TAB_STYLE]);
        $this->add_responsive_control('thumb_gap', ['label' => __('فاصله تصاویر', 'zig3d-widgets'), 'type' => Controls_Manager::SLIDER, 'selectors' => ['{{WRAPPER}} .zig-software-gallery__thumbs' => 'gap: {{SIZE}}{{UNIT}};']]);
        $this->add_responsive_control('thumb_padding', ['label' => __('فاصله داخلی', 'zig3d-widgets'), 'type' => Controls_Manager::DIMENSIONS, 'selectors' => ['{{WRAPPER}} .zig-software-gallery__thumb' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};']]);
        foreach (['thumb_background' => ['پس‌زمینه', 'background-color'], 'thumb_border_color' => ['رنگ حاشیه', 'border-color'], 'thumb_selected_border' => ['رنگ حاشیه انتخاب‌شده', 'border-color']] as $id => [$label, $property]) {
            $selector = 'thumb_selected_border' === $id ? '{{WRAPPER}} .zig-software-gallery__thumb[aria-current="true"]' : '{{WRAPPER}} .zig-software-gallery__thumb';
            $this->add_control($id, ['label' => __($label, 'zig3d-widgets'), 'type' => Controls_Manager::COLOR, 'selectors' => [$selector => $property . ': {{VALUE}};']]);
        }
        $this->add_control('thumb_border_width', ['label' => __('ضخامت حاشیه', 'zig3d-widgets'), 'type' => Controls_Manager::SLIDER, 'selectors' => ['{{WRAPPER}} .zig-software-gallery__thumb' => 'border-width: {{SIZE}}{{UNIT}};']]);
        $this->add_control('thumb_radius', ['label' => __('گردی گوشه‌ها', 'zig3d-widgets'), 'type' => Controls_Manager::SLIDER, 'selectors' => ['{{WRAPPER}} .zig-software-gallery__thumb' => 'border-radius: {{SIZE}}{{UNIT}};']]);
        $this->end_controls_section();
    }

    private function item_data(array $item, int $post_id): array {
        $id = (int) ($item['id'] ?? 0);
        $fallback = function_exists('get_the_title') ? (string) get_the_title($post_id) : '';
        if ($id > 0) {
            $src = (string) (wp_get_attachment_image_url($id, 'large') ?: '');
            $alt = trim((string) get_post_meta($id, '_wp_attachment_image_alt', true));
            if ('' === $alt && function_exists('get_the_title')) { $alt = (string) get_the_title($id); }
            $srcset = function_exists('wp_get_attachment_image_srcset') ? (string) wp_get_attachment_image_srcset($id, 'large') : '';
            $sizes = function_exists('wp_get_attachment_image_sizes') ? (string) wp_get_attachment_image_sizes($id, 'large') : '';
            return ['id' => $id, 'src' => $src, 'srcset' => $srcset, 'sizes' => $sizes, 'alt' => $alt ?: $fallback];
        }
        return ['id' => 0, 'src' => esc_url_raw((string) ($item['url'] ?? '')), 'srcset' => '', 'sizes' => '', 'alt' => $fallback];
    }

    private function image_html(array $data, string $size, array $attrs): string {
        if ($data['id'] > 0) { return (string) wp_get_attachment_image($data['id'], $size, false, $attrs + ['alt' => $data['alt']]); }
        return '<img src="' . esc_url($data['src']) . '" alt="' . esc_attr($data['alt']) . '" ' . ($attrs['loading'] ? 'loading="' . esc_attr($attrs['loading']) . '" ' : '') . 'class="' . esc_attr($attrs['class'] ?? '') . '" />';
    }

    protected function render(): void {
        $settings = $this->get_settings_for_display();
        $field = (string) ($settings['gallery_field'] ?? Download_Archive_Data::default_gallery_field());
        $post_id = function_exists('get_queried_object_id') ? (int) get_queried_object_id() : (int) get_the_ID();
        $raw_items = '' !== $field ? Download_Archive_Data::current_gallery_items($field, $post_id) : [];
        $items = [];
        foreach ($raw_items as $item) {
            $data = $this->item_data($item, $post_id);
            if ('' !== $data['src']) { $items[] = $data; }
        }
        if (!$items) { return; }
        $multiple = count($items) > 1;
        echo '<section class="zig-software-gallery" dir="rtl" data-zig-software-gallery>';
        $title = trim((string) ($settings['heading'] ?? ''));
        $description = trim((string) ($settings['description'] ?? ''));
        if (('yes' === ($settings['show_heading'] ?? 'yes') && '' !== $title) || ('yes' === ($settings['show_description'] ?? 'yes') && '' !== $description)) {
            echo '<header class="zig-software-gallery__header">';
            if ('yes' === ($settings['show_heading'] ?? 'yes') && '' !== $title) { echo '<h2 class="zig-software-gallery__title">' . esc_html($title) . '</h2>'; }
            if ('yes' === ($settings['show_description'] ?? 'yes') && '' !== $description) { echo '<p class="zig-software-gallery__description">' . esc_html($description) . '</p>'; }
            echo '</header>';
        }
        echo '<div class="zig-software-gallery__viewer"><div class="zig-software-gallery__stage">';
        echo $this->image_html($items[0], 'large', ['class' => 'zig-software-gallery__main-image', 'loading' => 'eager', 'fetchpriority' => 'high']);
        if ($multiple && 'yes' === ($settings['show_arrows'] ?? 'yes')) {
            echo '<button type="button" class="zig-software-gallery__arrow zig-software-gallery__arrow--previous" data-gallery-previous aria-label="' . esc_attr__('تصویر قبلی', 'zig3d-widgets') . '"><svg viewBox="0 0 16 16" aria-hidden="true"><path d="m10 3-5 5 5 5"/></svg></button>';
            echo '<button type="button" class="zig-software-gallery__arrow zig-software-gallery__arrow--next" data-gallery-next aria-label="' . esc_attr__('تصویر بعدی', 'zig3d-widgets') . '"><svg viewBox="0 0 16 16" aria-hidden="true"><path d="m6 3 5 5-5 5"/></svg></button>';
        }
        echo '</div></div>';
        if ($multiple && 'yes' === ($settings['show_thumbnails'] ?? 'yes')) {
            echo '<div class="zig-software-gallery__thumbs" aria-label="' . esc_attr__('تصاویر گالری', 'zig3d-widgets') . '">';
            foreach ($items as $index => $data) {
                echo '<button type="button" class="zig-software-gallery__thumb" data-gallery-index="' . esc_attr((string) $index) . '" data-src="' . esc_url($data['src']) . '" data-srcset="' . esc_attr($data['srcset']) . '" data-sizes="' . esc_attr($data['sizes']) . '" data-alt="' . esc_attr($data['alt']) . '" aria-label="' . esc_attr(sprintf(__('نمایش تصویر %d', 'zig3d-widgets'), $index + 1)) . '"' . (0 === $index ? ' aria-current="true"' : '') . '>';
                echo $this->image_html($data, 'medium', ['class' => 'zig-software-gallery__thumb-image', 'loading' => 'lazy']);
                echo '</button>';
            }
            echo '</div>';
        }
        echo '</section>';
    }
}
