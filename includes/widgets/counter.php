<?php
namespace Zig3d_Widgets\Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;
use Zig3d_Widgets\Counter_Source;
use Zig3d_Widgets\Markup;
use Zig3d_Widgets\Plugin;
use Zig3d_Widgets\Price;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * شمارش‌گر — یک عددِ زندهٔ سایت (تعدادِ نوشته‌های یک دسته، تعدادِ فایل‌های
 * آرشیوِ دانلود، …) با متنِ دلخواه قبل/بعدش.
 *
 *     div.zig-counter
 *       span.zig-counter__prefix   فقط اگر متنِ «قبل» پر باشد
 *       span.zig-counter__number
 *       span.zig-counter__suffix   فقط اگر متنِ «بعد» پر باشد
 *
 * منطقِ «چطور شمرده می‌شود» اینجا نیست — در ‎Counter_Source‎ است. این
 * ویجت فقط تنظیماتِ پنل را به آرگومانِ آن کلاس ترجمه می‌کند و عدد را
 * قالب‌بندی (جداکنندهٔ هزارگان، ارقامِ فارسی) و چاپ می‌کند. اضافه‌کردنِ
 * منبعِ تازه در آینده یعنی یک گزینهٔ تازه در ‎source_type‎ + یک ‎case‎ی
 * تازه در ‎Counter_Source‎، نه دست‌کاریِ این فایل.
 */
final class Counter extends Widget_Base {

    public function get_name(): string {
        return 'zig3d-counter';
    }

    public function get_title(): string {
        return __('شمارش‌گر', 'zig3d-widgets');
    }

    public function get_icon(): string {
        return 'eicon-counter';
    }

    public function get_categories(): array {
        return [Plugin::CATEGORY];
    }

    public function get_keywords(): array {
        return ['counter', 'count', 'number', 'stats', 'شمارش', 'شمارنده', 'شمارش‌گر', 'عدد', 'آمار'];
    }

    public function get_style_depends(): array {
        return ['zig3d-widgets'];
    }

    protected function register_controls(): void {
        $this->register_source_section();
        $this->register_text_section();
        $this->register_number_style_section();
        $this->register_text_style_section();
        $this->register_layout_style_section();
    }

    /* =====================================================================
     * محتوا › منبع
     * =================================================================== */

    private function register_source_section(): void {
        $this->start_controls_section(
            'source_section',
            ['label' => __('منبع', 'zig3d-widgets')]
        );

        $this->add_control(
            'source_type',
            [
                'label'   => __('چی رو بشماریم', 'zig3d-widgets'),
                'type'    => Controls_Manager::SELECT,
                'default' => Counter_Source::TYPE_BLOG_CATEGORY,
                'options' => [
                    Counter_Source::TYPE_BLOG_CATEGORY => __('دسته‌بندی بلاگ', 'zig3d-widgets'),
                    Counter_Source::TYPE_DOWNLOADS     => __('دانلودها', 'zig3d-widgets'),
                ],
            ]
        );

        $this->add_control(
            'category_scope',
            [
                'label'     => __('از کجا بشماریم', 'zig3d-widgets'),
                'type'      => Controls_Manager::SELECT,
                'default'   => 'specific',
                'options'   => [
                    'specific' => __('یک دستهٔ خاص', 'zig3d-widgets'),
                    'all'      => __('همهٔ نوشته‌ها (مجموعِ همهٔ دسته‌ها)', 'zig3d-widgets'),
                ],
                'condition' => ['source_type' => Counter_Source::TYPE_BLOG_CATEGORY],
            ]
        );

        $this->add_control(
            'category_id',
            [
                'label'       => __('دسته', 'zig3d-widgets'),
                'type'        => Controls_Manager::SELECT2,
                'label_block' => true,
                'options'     => $this->blog_category_options(),
                'condition'   => [
                    'source_type'    => Counter_Source::TYPE_BLOG_CATEGORY,
                    'category_scope' => 'specific',
                ],
            ]
        );

        $this->add_control(
            'downloads_note',
            [
                'type'            => Controls_Manager::RAW_HTML,
                'raw'             => __('تعدادِ کلِ پست‌های منتشرشده در پست‌تایپِ آرشیوِ دانلود.', 'zig3d-widgets'),
                'content_classes' => 'elementor-descriptor',
                'condition'       => ['source_type' => Counter_Source::TYPE_DOWNLOADS],
            ]
        );

        $this->end_controls_section();
    }

    /** @return array<int,string> */
    private function blog_category_options(): array {
        $terms = get_terms(['taxonomy' => 'category', 'hide_empty' => false]);

        if (!is_array($terms)) {
            return [];
        }

        $options = [];

        foreach ($terms as $term) {
            if ($term instanceof \WP_Term) {
                $options[$term->term_id] = $term->name;
            }
        }

        return $options;
    }

    /* =====================================================================
     * محتوا › متن
     * =================================================================== */

    private function register_text_section(): void {
        $this->start_controls_section(
            'text_section',
            ['label' => __('متن', 'zig3d-widgets')]
        );

        $this->add_control(
            'prefix_text',
            [
                'label'       => __('متنِ قبل از عدد', 'zig3d-widgets'),
                'type'        => Controls_Manager::TEXT,
                'default'     => '',
                'placeholder' => __('مثلاً «بیشتر از»', 'zig3d-widgets'),
            ]
        );

        $this->add_control(
            'suffix_text',
            [
                'label'       => __('متنِ بعد از عدد', 'zig3d-widgets'),
                'type'        => Controls_Manager::TEXT,
                'default'     => '',
                'placeholder' => __('مثلاً «نوشته»', 'zig3d-widgets'),
            ]
        );

        $this->add_control(
            'persian_digits',
            [
                'label'        => __('ارقام فارسی', 'zig3d-widgets'),
                'type'         => Controls_Manager::SWITCHER,
                'default'      => 'yes',
                'return_value' => 'yes',
            ]
        );

        $this->add_control(
            'thousands_separator',
            [
                'label'        => __('جداکنندهٔ هزارگان (٬)', 'zig3d-widgets'),
                'type'         => Controls_Manager::SWITCHER,
                'default'      => '',
                'return_value' => 'yes',
            ]
        );

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل › عدد
     * =================================================================== */

    private function register_number_style_section(): void {
        $this->start_controls_section(
            'number_style_section',
            [
                'label' => __('عدد', 'zig3d-widgets'),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'number_color',
            [
                'label'     => __('رنگ', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => ['{{WRAPPER}} .zig-counter__number' => 'color: {{VALUE}};'],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'     => 'number_typography',
                'selector' => '{{WRAPPER}} .zig-counter__number',
            ]
        );

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل › متنِ قبل/بعد
     * =================================================================== */

    private function register_text_style_section(): void {
        $this->start_controls_section(
            'text_style_section',
            [
                'label' => __('متنِ قبل/بعد', 'zig3d-widgets'),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'text_color',
            [
                'label'     => __('رنگ', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .zig-counter__prefix' => 'color: {{VALUE}};',
                    '{{WRAPPER}} .zig-counter__suffix' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'     => 'text_typography',
                'selector' => '{{WRAPPER}} .zig-counter__prefix, {{WRAPPER}} .zig-counter__suffix',
            ]
        );

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل › چیدمان
     * =================================================================== */

    private function register_layout_style_section(): void {
        $this->start_controls_section(
            'layout_style_section',
            [
                'label' => __('چیدمان', 'zig3d-widgets'),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_responsive_control(
            'items_gap',
            [
                'label'          => __('فاصلهٔ بینِ متن و عدد', 'zig3d-widgets'),
                'type'           => Controls_Manager::SLIDER,
                'size_units'     => ['px', 'em'],
                'range'          => ['px' => ['min' => 0, 'max' => 60]],
                'default'        => ['size' => 6, 'unit' => 'px'],
                'mobile_default' => ['size' => 4, 'unit' => 'px'],
                'selectors'      => ['{{WRAPPER}} .zig-counter' => '--zig-counter-gap: {{SIZE}}{{UNIT}};'],
            ]
        );

        $this->add_control(
            'vertical_align',
            [
                'label'     => __('ترازِ عمودی', 'zig3d-widgets'),
                'type'      => Controls_Manager::CHOOSE,
                'default'   => 'baseline',
                'options'   => [
                    'baseline' => ['title' => __('خط‌مبنا (پیشنهادی برای متن)', 'zig3d-widgets'), 'icon' => 'eicon-v-align-bottom'],
                    'center'   => ['title' => __('وسط', 'zig3d-widgets'), 'icon' => 'eicon-v-align-middle'],
                ],
                'toggle'    => false,
                'selectors' => ['{{WRAPPER}} .zig-counter' => 'align-items: {{VALUE}};'],
            ]
        );

        $this->add_responsive_control(
            'align',
            [
                'label'     => __('چیدمانِ افقی', 'zig3d-widgets'),
                'type'      => Controls_Manager::CHOOSE,
                'options'   => [
                    'flex-start' => ['title' => __('ابتدا', 'zig3d-widgets'), 'icon' => 'eicon-text-align-left'],
                    'center'     => ['title' => __('وسط', 'zig3d-widgets'), 'icon' => 'eicon-text-align-center'],
                    'flex-end'   => ['title' => __('انتها', 'zig3d-widgets'), 'icon' => 'eicon-text-align-right'],
                ],
                'selectors' => ['{{WRAPPER}} .zig-counter' => 'justify-content: {{VALUE}};'],
            ]
        );

        $this->end_controls_section();
    }

    /* =====================================================================
     * رندر
     * =================================================================== */

    protected function render(): void {
        $settings = $this->get_settings_for_display();
        $type     = (string) ($settings['source_type'] ?? Counter_Source::TYPE_BLOG_CATEGORY);

        $count = Counter_Source::count($type, $this->source_args($settings, $type));

        if (null === $count) {
            $this->notice($this->empty_reason($type));

            return;
        }

        $number = $this->format_number($count, $settings);
        $prefix = trim((string) ($settings['prefix_text'] ?? ''));
        $suffix = trim((string) ($settings['suffix_text'] ?? ''));

        echo '<div class="zig-counter">';

        if (Markup::filled($prefix)) {
            printf('<span class="zig-counter__prefix">%s</span>', esc_html($prefix));
        }

        printf('<span class="zig-counter__number">%s</span>', esc_html($number));

        if (Markup::filled($suffix)) {
            printf('<span class="zig-counter__suffix">%s</span>', esc_html($suffix));
        }

        echo '</div>';
    }

    /** @return array<string,mixed> */
    private function source_args(array $settings, string $type): array {
        if (Counter_Source::TYPE_BLOG_CATEGORY !== $type) {
            return [];
        }

        return [
            'scope'       => (string) ($settings['category_scope'] ?? 'specific'),
            'category_id' => absint($settings['category_id'] ?? 0),
        ];
    }

    private function format_number(int $count, array $settings): string {
        $number = 'yes' === ($settings['thousands_separator'] ?? '')
            ? number_format($count, 0, '', '٬')
            : (string) $count;

        return 'yes' === ($settings['persian_digits'] ?? 'yes') ? Price::persian($number) : $number;
    }

    private function empty_reason(string $type): string {
        if (Counter_Source::TYPE_DOWNLOADS === $type) {
            return __('پست‌تایپِ آرشیوِ دانلود پیدا نشد — از فعال‌بودنِ جت‌اینجین و ثبتِ آن پست‌تایپ مطمئن شوید.', 'zig3d-widgets');
        }

        return __('دسته‌ای انتخاب نشده — از بخشِ «منبع» یک دسته را انتخاب کنید یا «همهٔ نوشته‌ها» را بزنید.', 'zig3d-widgets');
    }

    /** پیام راهنما، فقط داخل ادیتور */
    private function notice(string $message): void {
        $editing = class_exists('\Elementor\Plugin')
            && isset(\Elementor\Plugin::$instance->editor)
            && \Elementor\Plugin::$instance->editor->is_edit_mode();

        if (!$editing) {
            return;
        }

        printf('<div class="zig-counter__notice">%s</div>', esc_html($message));
    }
}
