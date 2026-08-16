<?php
namespace Zig3d_Widgets\Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Repeater;
use Elementor\Widget_Base;
use Zig3d_Widgets\Download_Archive_Data;
use Zig3d_Widgets\Plugin;

if (!defined('ABSPATH')) { exit; }

final class Software_Info_Table extends Widget_Base {
    public function get_name(): string { return 'zig3d-software-info-table'; }
    public function get_title(): string { return __('جدول مشخصات نرم‌افزار', 'zig3d-widgets'); }
    public function get_icon(): string { return 'eicon-table'; }
    public function get_categories(): array { return [Plugin::CATEGORY]; }
    public function get_style_depends(): array { return ['zig3d-widgets']; }
    public function has_widget_inner_wrapper(): bool { return false; }

    protected function register_controls(): void {
        $this->start_controls_section('content', ['label' => __('محتوا', 'zig3d-widgets')]);
        $this->add_control('show_when_empty', [
            'label' => __('نمایش ویجت در صورت نبود داده', 'zig3d-widgets'),
            'type' => Controls_Manager::SWITCHER,
            'default' => '',
        ]);

        $repeater = new Repeater();
        $repeater->add_control('enabled', ['label' => __('فعال‌سازی ردیف', 'zig3d-widgets'), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes']);
        $repeater->add_control('label', ['label' => __('عنوان ردیف', 'zig3d-widgets'), 'type' => Controls_Manager::TEXT, 'default' => '']);
        $repeater->add_control('source', ['label' => __('منبع مقدار', 'zig3d-widgets'), 'type' => Controls_Manager::SELECT, 'options' => $this->source_options(), 'default' => '']);
        $repeater->add_control('format', [
            'label' => __('نوع نمایش مقدار', 'zig3d-widgets'),
            'type' => Controls_Manager::SELECT,
            'options' => [
                'auto' => __('خودکار', 'zig3d-widgets'),
                'plain' => __('متن ساده', 'zig3d-widgets'),
                'date' => __('تاریخ', 'zig3d-widgets'),
                'multi' => __('چندمقداری', 'zig3d-widgets'),
                'raw' => __('متای خام', 'zig3d-widgets'),
            ],
            'default' => 'auto',
        ]);
        $repeater->add_control('separator', ['label' => __('جداکننده مقادیر چندتایی', 'zig3d-widgets'), 'type' => Controls_Manager::TEXT, 'default' => ' / ']);
        $repeater->add_control('hide_empty', ['label' => __('مخفی‌کردن اگر خالی بود', 'zig3d-widgets'), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes']);
        $this->add_control('rows', [
            'label' => __('ردیف‌ها', 'zig3d-widgets'),
            'type' => Controls_Manager::REPEATER,
            'fields' => $repeater->get_controls(),
            'title_field' => '{{{ label }}}',
            'default' => $this->default_rows(),
        ]);
        $this->end_controls_section();

        $this->start_controls_section('container_style', ['label' => __('کادر جدول', 'zig3d-widgets'), 'tab' => Controls_Manager::TAB_STYLE]);
        $root = '{{WRAPPER}} .zig-software-info-table__list';
        $this->add_control('container_background', ['label' => __('پس‌زمینه', 'zig3d-widgets'), 'type' => Controls_Manager::COLOR, 'selectors' => [$root => 'background-color: {{VALUE}};']]);
        $this->add_control('container_border_color', ['label' => __('رنگ کادر', 'zig3d-widgets'), 'type' => Controls_Manager::COLOR, 'selectors' => [$root => 'border-color: {{VALUE}};']]);
        $this->add_control('container_border_width', ['label' => __('ضخامت کادر', 'zig3d-widgets'), 'type' => Controls_Manager::SLIDER, 'selectors' => [$root => 'border-width: {{SIZE}}{{UNIT}};']]);
        $this->add_control('container_radius', ['label' => __('گردی گوشه‌ها', 'zig3d-widgets'), 'type' => Controls_Manager::SLIDER, 'selectors' => [$root => 'border-radius: {{SIZE}}{{UNIT}};']]);
        $this->end_controls_section();

        $this->start_controls_section('rows_style', ['label' => __('ردیف‌ها', 'zig3d-widgets'), 'tab' => Controls_Manager::TAB_STYLE]);
        $this->add_control('row_divider_color', ['label' => __('رنگ جداکننده ردیف‌ها', 'zig3d-widgets'), 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .zig-software-info-table__row' => 'border-color: {{VALUE}};']]);
        $this->add_responsive_control('row_min_height', [
            'label' => __('حداقل ارتفاع ردیف', 'zig3d-widgets'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => ['px', 'rem'],
            'range' => ['px' => ['min' => 36, 'max' => 120], 'rem' => ['min' => 2, 'max' => 8, 'step' => 0.1]],
            'default' => ['unit' => 'px', 'size' => 60],
            'selectors' => ['{{WRAPPER}} .zig-software-info-table' => '--zig-info-row-min-height: {{SIZE}}{{UNIT}};'],
        ]);
        $this->add_responsive_control('row_vertical_padding', ['label' => __('فاصله عمودی ردیف', 'zig3d-widgets'), 'type' => Controls_Manager::SLIDER, 'selectors' => ['{{WRAPPER}} .zig-software-info-table' => '--zig-info-row-y: {{SIZE}}{{UNIT}};']]);
        $this->add_responsive_control('row_horizontal_padding', ['label' => __('فاصله افقی ردیف', 'zig3d-widgets'), 'type' => Controls_Manager::SLIDER, 'selectors' => ['{{WRAPPER}} .zig-software-info-table' => '--zig-info-row-x: {{SIZE}}{{UNIT}};']]);
        $this->end_controls_section();

        $this->start_controls_section('labels_style', ['label' => __('عنوان مشخصات', 'zig3d-widgets'), 'tab' => Controls_Manager::TAB_STYLE]);
        $this->add_group_control(Group_Control_Typography::get_type(), ['name' => 'label_typography', 'selector' => '{{WRAPPER}} .zig-software-info-table__label']);
        $this->add_control('label_color', ['label' => __('رنگ عنوان', 'zig3d-widgets'), 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .zig-software-info-table__label' => 'color: {{VALUE}};']]);
        $this->add_responsive_control('label_width', ['label' => __('عرض ستون عنوان', 'zig3d-widgets'), 'type' => Controls_Manager::SLIDER, 'selectors' => ['{{WRAPPER}} .zig-software-info-table' => '--zig-info-label-width: {{SIZE}}{{UNIT}};']]);
        $this->end_controls_section();

        $this->start_controls_section('values_style', ['label' => __('مقادیر', 'zig3d-widgets'), 'tab' => Controls_Manager::TAB_STYLE]);
        $this->add_group_control(Group_Control_Typography::get_type(), ['name' => 'value_typography', 'selector' => '{{WRAPPER}} .zig-software-info-table__value']);
        $this->add_control('value_color', ['label' => __('رنگ مقدار', 'zig3d-widgets'), 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .zig-software-info-table__value' => 'color: {{VALUE}};']]);
        $this->end_controls_section();
    }

    private function source_options(): array {
        $options = ['' => __('— انتخاب نشده —', 'zig3d-widgets'), '@cached_file_size' => __('حجم فایل خودکار (ذخیره‌شده)', 'zig3d-widgets')];
        $compatible = ['text', 'textarea', 'wysiwyg', 'editor', 'date', 'datetime', 'date-time', 'select', 'multiselect', 'multi-select', 'checkbox', 'radio', 'number', 'url'];
        foreach (Download_Archive_Data::field_schema() as $field) {
            if (in_array($field['type'], $compatible, true)) {
                $options[$field['key']] = sprintf('%s — %s [%s]', $field['label'], $field['key'], $field['type']);
            }
        }
        foreach (Download_Archive_Data::taxonomy_options(false) as $taxonomy => $label) {
            $options['@taxonomy:' . $taxonomy] = sprintf(__('تاکسونومی: %s', 'zig3d-widgets'), $label);
        }
        return $options;
    }

    private function default_rows(): array {
        $definitions = [
            ['نسخه', ['software_version', 'version'], 'auto'],
            ['حجم فایل', ['@cached_file_size'], 'plain'],
            ['تاریخ به‌روزرسانی', ['release_date', 'updated_date'], 'date'],
            ['سیستم‌عامل‌های سازگار', ['supported_os'], 'multi'],
            ['مدل‌های دستگاه سازگار', ['compatible_models'], 'multi'],
            ['برند نرم‌افزار', ['software_brand', 'brand'], 'auto'],
            ['نوع فایل', ['file_type'], 'auto'],
            ['معماری سیستم', ['system_architecture', 'architecture'], 'auto'],
            ['پردازنده', ['processor', 'cpu'], 'auto'],
            ['حافظه RAM', ['ram', 'memory'], 'auto'],
            ['کارت گرافیک', ['graphics_card', 'gpu'], 'auto'],
            ['سیستم‌عامل', ['operating_system', 'os_requirement'], 'auto'],
        ];
        $available = Download_Archive_Data::field_schema();
        $rows = [];
        foreach ($definitions as [$label, $candidates, $format]) {
            $source = '';
            foreach ($candidates as $candidate) {
                if ('@cached_file_size' === $candidate || isset($available[$candidate])) { $source = $candidate; break; }
            }
            $rows[] = ['enabled' => 'yes', 'label' => $label, 'source' => $source, 'format' => $format, 'separator' => ' / ', 'hide_empty' => 'yes'];
        }
        return $rows;
    }

    protected function render(): void {
        $settings = $this->get_settings_for_display();
        $post_id = function_exists('get_queried_object_id') ? (int) get_queried_object_id() : 0;
        if ($post_id <= 0 && function_exists('get_the_ID')) { $post_id = (int) get_the_ID(); }
        $post_type = Download_Archive_Data::post_type();
        if ($post_id <= 0 || '' === $post_type || get_post_type($post_id) !== $post_type) { return; }

        $rendered = [];
        foreach ((array) ($settings['rows'] ?? $this->default_rows()) as $row) {
            if ('yes' !== ($row['enabled'] ?? 'yes') || '' === trim((string) ($row['label'] ?? ''))) { continue; }
            $values = $this->row_values($post_id, (string) ($row['source'] ?? ''), (string) ($row['format'] ?? 'auto'));
            if (!$values && 'yes' === ($row['hide_empty'] ?? 'yes')) { continue; }
            $rendered[] = [$row, $values];
        }
        if (!$rendered && 'yes' !== ($settings['show_when_empty'] ?? '')) { return; }

        echo '<section class="zig-software-info-table" dir="rtl"><dl class="zig-software-info-table__list">';
        foreach ($rendered as [$row, $values]) {
            echo '<div class="zig-software-info-table__row"><dt class="zig-software-info-table__label">' . esc_html($row['label']) . '</dt><dd class="zig-software-info-table__value">';
            $separator = (string) ($row['separator'] ?? ' / ');
            foreach ($values as $index => $value) {
                if ($index > 0) { echo '<span class="zig-software-info-table__separator" aria-hidden="true">' . esc_html($separator) . '</span>'; }
                echo '<bdi dir="auto">' . esc_html($value) . '</bdi>';
            }
            echo '</dd></div>';
        }
        echo '</dl></section>';
    }

    /** @return string[] */
    private function row_values(int $post_id, string $source, string $format): array {
        if ('' === $source) { return []; }
        if ('@cached_file_size' === $source) {
            $bytes = (int) get_post_meta($post_id, Download_Archive_Data::SIZE_META, true);
            return $bytes > 0 ? [size_format($bytes, 1)] : [];
        }
        if (0 === strpos($source, '@taxonomy:')) {
            $taxonomy = substr($source, strlen('@taxonomy:'));
            if ('' === $taxonomy || !function_exists('wp_get_post_terms')) { return []; }
            $terms = wp_get_post_terms($post_id, $taxonomy, ['fields' => 'names']);
            return is_wp_error($terms) ? [] : array_values(array_filter(array_map('trim', (array) $terms), 'strlen'));
        }
        $raw = get_post_meta($post_id, $source, true);
        $schema = Download_Archive_Data::field_schema()[$source] ?? [];
        if ('auto' === $format) {
            $type = (string) ($schema['type'] ?? 'text');
            $format = in_array($type, ['date', 'datetime', 'date-time'], true) ? 'date' : (is_array($raw) || !empty($schema['options']) ? 'multi' : 'plain');
        }
        if ('date' === $format) {
            $value = trim((string) $raw);
            if ('' === $value) { return []; }
            $timestamp = ctype_digit($value) ? (int) $value : strtotime($value);
            return $timestamp ? [wp_date(get_option('date_format'), $timestamp)] : [$value];
        }
        if ('multi' === $format || is_array($raw)) {
            return array_values(array_filter(array_map('trim', Download_Archive_Data::display_values($source, $raw)), 'strlen'));
        }
        $value = trim(wp_strip_all_tags((string) $raw));
        return '' === $value ? [] : [$value];
    }
}
