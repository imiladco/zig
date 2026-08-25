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
 * سوالاتِ متداول (FAQ)، آکاردئونی.
 *
 * منبعِ داده — دقیقاً مثلِ ‎Documents‎ — یک ریپیترِ JetEngine Meta Box رویِ
 * پستِ جاری است، نه چیزی که این ویجت بسازد یا مدیریت کند. کلیدِ متا و
 * کلیدِ هر زیرفیلد (سوال/پاسخ) از پنلِ المنتور تنظیم می‌شود تا با هر
 * کانفیگِ JetEngine هم‌اهنگ بماند، نه هاردکد.
 *
 * ساختار و مکانیزمِ آکاردئون از ‎Product_Specs‎ وام گرفته شده: بومیِ
 * ‎<details>/<summary>‎ (بدونِ جاوااسکریپت هم کاملاً کاربردی)، باکس‌هایِ
 * بستهٔ پشتِ‌سرهم یک کارتِ یکپارچه می‌سازند و باکسِ باز از آن‌ها جدا
 * می‌شود — همان UI/UX که خواسته شد، فقط استایلِ پیش‌فرض طبقِ طرحِ تازه
 * فرق می‌کند، نه رفتار.
 *
 *     div.zig-faq
 *       details.zig-faq__item[open]
 *         summary.zig-faq__question
 *           span.zig-faq__question-text
 *           span.zig-faq__toggle
 *             svg.zig-faq__chevron
 *         div.zig-faq__answer
 */
final class Faq extends Widget_Base {

    public function get_name(): string {
        return 'zig3d-faq';
    }

    public function get_title(): string {
        return __('سوالات متداول', 'zig3d-widgets');
    }

    public function get_icon(): string {
        return 'eicon-help-o';
    }

    public function get_categories(): array {
        return [Plugin::CATEGORY];
    }

    public function get_keywords(): array {
        return ['faq', 'accordion', 'questions', 'answers', 'jetengine', 'سوال', 'پاسخ', 'اکاردئون', 'متداول'];
    }

    public function get_style_depends(): array {
        return ['zig3d-widgets'];
    }

    /**
     * بدونِ این اسکریپت هم آکاردئون کار می‌کند — بومیِ ‎<details>‎، بدونِ
     * انیمیشن و بدونِ تک‌بازشو. فایل فقط آن دو رفتار را اضافه می‌کند
     * (رجوع کنید به ‎zig3d-faq.js‎).
     */
    public function get_script_depends(): array {
        return ['zig3d-faq'];
    }

    public function has_widget_inner_wrapper(): bool {
        return false;
    }

    protected function register_controls(): void {
        $this->register_source_section();
        $this->register_fields_section();
        $this->register_layout_section();
        $this->register_box_style_section();
        $this->register_question_style_section();
        $this->register_toggle_style_section();
        $this->register_answer_style_section();
    }

    /* =====================================================================
     * منبع داده
     * =================================================================== */

    private function register_source_section(): void {
        $this->start_controls_section(
            'source_section',
            ['label' => __('منبع داده', 'zig3d-widgets')]
        );

        $this->add_control(
            'meta_key',
            [
                'label'       => __('کلیدِ متای ریپیتر', 'zig3d-widgets'),
                'type'        => Controls_Manager::TEXT,
                'default'     => 'faq',
                'description' => __('نامِ فیلدِ ریپیترِ JetEngine Meta Box رویِ پستِ جاری.', 'zig3d-widgets'),
            ]
        );

        $this->add_control(
            'source_post_id',
            [
                'label'       => __('شناسهٔ پست (دستی)', 'zig3d-widgets'),
                'type'        => Controls_Manager::NUMBER,
                'min'         => 0,
                'default'     => 0,
                'description' => __('خالی یا صفر یعنی پستِ جاری. فقط وقتی لازم است که از پستی غیرِ جاری خوانده شود.', 'zig3d-widgets'),
            ]
        );

        $this->end_controls_section();
    }

    /**
     * نگاشتِ نقشِ هر زیرفیلد به کلیدِ متایِ آن در سطرِ ریپیتر.
     *
     * «قابل تنظیم بودنِ فیلدها» یعنی کلیدها از پنل به‌دستِ کاربر می‌آیند،
     * نه هاردکد — تا هر کانفیگِ JetEngine‌ای بدونِ تغییرِ کد هم‌اهنگ بماند.
     */
    private function register_fields_section(): void {
        $this->start_controls_section(
            'fields_section',
            ['label' => __('فیلدها', 'zig3d-widgets')]
        );

        $this->add_control(
            'field_title',
            [
                'label'   => __('کلیدِ سوال (عنوان)', 'zig3d-widgets'),
                'type'    => Controls_Manager::TEXT,
                'default' => 'faq_question',
            ]
        );

        $this->add_control(
            'field_answer',
            [
                'label'   => __('کلیدِ پاسخ', 'zig3d-widgets'),
                'type'    => Controls_Manager::TEXT,
                'default' => 'faq_answer',
            ]
        );

        $this->end_controls_section();
    }

    private function register_layout_section(): void {
        $this->start_controls_section(
            'layout_section',
            ['label' => __('چیدمان', 'zig3d-widgets')]
        );

        $this->add_control(
            'expand_first',
            [
                'label'   => __('گزینهٔ اول باز باشد', 'zig3d-widgets'),
                'type'    => Controls_Manager::SWITCHER,
                'default' => 'yes',
            ]
        );

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل
     * =================================================================== */

    private function register_box_style_section(): void {
        $this->start_controls_section(
            'box_style_section',
            [
                'label' => __('باکس', 'zig3d-widgets'),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'box_bg',
            [
                'label'     => __('پس‌زمینه', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'default'   => '#FFFFFF',
                'selectors' => ['{{WRAPPER}} .zig-faq' => '--zig-faq-box-bg: {{VALUE}};'],
            ]
        );

        $this->add_control(
            'box_border_color',
            [
                'label'     => __('رنگِ حاشیه', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'default'   => '#EAECF0',
                'selectors' => ['{{WRAPPER}} .zig-faq' => '--zig-faq-box-border: {{VALUE}};'],
            ]
        );

        $this->add_control(
            'box_border_width',
            [
                'label'      => __('پهنایِ حاشیه', 'zig3d-widgets'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range'      => ['px' => ['min' => 0, 'max' => 6]],
                'default'    => ['size' => 1, 'unit' => 'px'],
                'selectors'  => ['{{WRAPPER}} .zig-faq' => '--zig-faq-box-border-width: {{SIZE}}{{UNIT}};'],
            ]
        );

        $this->add_control(
            'box_radius',
            [
                'label'       => __('شعاعِ گوشه‌ها', 'zig3d-widgets'),
                'type'        => Controls_Manager::SLIDER,
                'size_units'  => ['px'],
                'range'       => ['px' => ['min' => 0, 'max' => 40]],
                'default'     => ['size' => 16, 'unit' => 'px'],
                'description' => __('یک شعاعِ مشترک برایِ باکسِ باز و بلوکِ باکس‌هایِ بسته؛ فقط گوشه‌هایِ لبهٔ واقعیِ فهرست گرد می‌شوند.', 'zig3d-widgets'),
                'selectors'   => ['{{WRAPPER}} .zig-faq' => '--zig-faq-radius: {{SIZE}}{{UNIT}};'],
            ]
        );

        $this->add_control(
            'box_divider_color',
            [
                'label'       => __('رنگِ خطِ جداکننده', 'zig3d-widgets'),
                'type'        => Controls_Manager::COLOR,
                'default'     => '#EEF1F6',
                'description' => __('خطِ بینِ باکس‌هایِ بستهٔ پشتِ‌سرهم — همان یک خط، نه دو مرزِ روی هم.', 'zig3d-widgets'),
                'selectors'   => ['{{WRAPPER}} .zig-faq' => '--zig-faq-divider: {{VALUE}};'],
            ]
        );

        $this->add_responsive_control(
            'question_padding',
            [
                'label'      => __('فاصلهٔ داخلیِ ردیفِ سوال', 'zig3d-widgets'),
                'type'       => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', 'rem'],
                'default'    => ['top' => '14', 'right' => '16', 'bottom' => '14', 'left' => '16', 'unit' => 'px', 'isLinked' => false],
                'selectors'  => ['{{WRAPPER}} .zig-faq__question' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
            ]
        );

        $this->add_responsive_control(
            'open_gap',
            [
                'label'       => __('فاصلهٔ باکسِ باز از بسته‌هایِ اطراف', 'zig3d-widgets'),
                'type'        => Controls_Manager::SLIDER,
                'size_units'  => ['px'],
                'range'       => ['px' => ['min' => 0, 'max' => 60]],
                'default'     => ['size' => 12, 'unit' => 'px'],
                'selectors'   => ['{{WRAPPER}} .zig-faq' => '--zig-faq-open-gap: {{SIZE}}{{UNIT}};'],
            ]
        );

        $this->end_controls_section();
    }

    private function register_question_style_section(): void {
        $this->start_controls_section(
            'question_style_section',
            [
                'label' => __('سوال (عنوان)', 'zig3d-widgets'),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'     => 'question_typography',
                'label'    => __('تایپوگرافی (حالتِ عادی)', 'zig3d-widgets'),
                'selector' => '{{WRAPPER}} .zig-faq__question-text',
            ]
        );

        $this->add_control(
            'question_color',
            [
                'label'     => __('رنگِ متن (حالتِ عادی)', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => ['{{WRAPPER}} .zig-faq__question-text' => 'color: {{VALUE}};'],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'      => 'question_typography_open',
                'label'     => __('تایپوگرافی (سوالِ فعال/باز)', 'zig3d-widgets'),
                'selector'  => '{{WRAPPER}} .zig-faq__item[open] > .zig-faq__question .zig-faq__question-text',
                'separator' => 'before',
            ]
        );

        $this->add_control(
            'question_color_open',
            [
                'label'     => __('رنگِ متن (سوالِ فعال/باز)', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => ['{{WRAPPER}} .zig-faq__item[open] > .zig-faq__question .zig-faq__question-text' => 'color: {{VALUE}};'],
            ]
        );

        $this->add_control(
            'question_accent_color',
            [
                'label'       => __('رنگِ حلقهٔ فوکوسِ کیبوردی', 'zig3d-widgets'),
                'type'        => Controls_Manager::COLOR,
                'default'     => '#6D28D9',
                'separator'   => 'before',
                'description' => __('فقط وقتی سوال با Tab فوکوس می‌گیرد دیده می‌شود.', 'zig3d-widgets'),
                'selectors'   => ['{{WRAPPER}} .zig-faq' => '--zig-faq-accent: {{VALUE}};'],
            ]
        );

        $this->end_controls_section();
    }

    private function register_toggle_style_section(): void {
        $this->start_controls_section(
            'toggle_style_section',
            [
                'label' => __('نشانِ باز-بسته', 'zig3d-widgets'),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_responsive_control(
            'toggle_size',
            [
                'label'      => __('اندازهٔ نشان', 'zig3d-widgets'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range'      => ['px' => ['min' => 16, 'max' => 48]],
                'default'    => ['size' => 24, 'unit' => 'px'],
                'selectors'  => ['{{WRAPPER}} .zig-faq' => '--zig-faq-toggle-size: {{SIZE}}{{UNIT}};'],
            ]
        );

        $this->add_responsive_control(
            'toggle_icon_size',
            [
                'label'      => __('اندازهٔ آیکون', 'zig3d-widgets'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range'      => ['px' => ['min' => 8, 'max' => 32]],
                'default'    => ['size' => 16, 'unit' => 'px'],
                'selectors'  => ['{{WRAPPER}} .zig-faq' => '--zig-faq-toggle-icon-size: {{SIZE}}{{UNIT}};'],
            ]
        );

        $this->add_control(
            'toggle_radius',
            [
                'label'      => __('شعاعِ گوشه‌ها', 'zig3d-widgets'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range'      => ['px' => ['min' => 0, 'max' => 999]],
                'default'    => ['size' => 999, 'unit' => 'px'],
                'selectors'  => ['{{WRAPPER}} .zig-faq' => '--zig-faq-toggle-radius: {{SIZE}}{{UNIT}};'],
            ]
        );

        $this->add_control(
            'toggle_icon_color',
            [
                'label'       => __('رنگِ آیکون', 'zig3d-widgets'),
                'type'        => Controls_Manager::COLOR,
                'default'     => '#475467',
                'description' => __('یکسان در هر دو حالت — فقط پس‌زمینهٔ نشان بینِ باز/بسته فرق می‌کند.', 'zig3d-widgets'),
                'selectors'   => ['{{WRAPPER}} .zig-faq' => '--zig-faq-toggle-icon-color: {{VALUE}};'],
            ]
        );

        $this->add_control(
            'toggle_border_color',
            [
                'label'     => __('رنگِ مرز', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'default'   => '#E4E7EC',
                'selectors' => ['{{WRAPPER}} .zig-faq' => '--zig-faq-toggle-border: {{VALUE}};'],
            ]
        );

        $this->add_control(
            'toggle_closed_heading',
            [
                'label'     => __('حالتِ بسته', 'zig3d-widgets'),
                'type'      => Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        $this->add_control(
            'toggle_bg',
            [
                'label'     => __('پس‌زمینه', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'default'   => '#FFFFFF',
                'selectors' => ['{{WRAPPER}} .zig-faq' => '--zig-faq-toggle-bg: {{VALUE}};'],
            ]
        );

        $this->add_control(
            'toggle_open_heading',
            [
                'label'     => __('حالتِ فعال (باز)', 'zig3d-widgets'),
                'type'      => Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        $this->add_control(
            'toggle_bg_open',
            [
                'label'     => __('پس‌زمینه', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'default'   => '#F2F4F7',
                'selectors' => ['{{WRAPPER}} .zig-faq' => '--zig-faq-toggle-bg-open: {{VALUE}};'],
            ]
        );

        $this->end_controls_section();
    }

    private function register_answer_style_section(): void {
        $this->start_controls_section(
            'answer_style_section',
            [
                'label' => __('پاسخ', 'zig3d-widgets'),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'     => 'answer_typography',
                'selector' => '{{WRAPPER}} .zig-faq__answer',
            ]
        );

        $this->add_control(
            'answer_color',
            [
                'label'     => __('رنگِ متن', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => ['{{WRAPPER}} .zig-faq__answer' => 'color: {{VALUE}};'],
            ]
        );

        $this->add_control(
            'answer_gap',
            [
                'label'       => __('فاصله از سوال', 'zig3d-widgets'),
                'type'        => Controls_Manager::SLIDER,
                'size_units'  => ['px'],
                'range'       => ['px' => ['min' => 0, 'max' => 40]],
                'default'     => ['size' => 4, 'unit' => 'px'],
                'separator'   => 'before',
                'selectors'   => ['{{WRAPPER}} .zig-faq' => '--zig-faq-answer-gap: {{SIZE}}{{UNIT}};'],
            ]
        );

        $this->end_controls_section();
    }

    /* =====================================================================
     * رندر
     * =================================================================== */

    protected function render(): void {
        $settings = $this->get_settings_for_display();
        $rows     = $this->rows($settings);

        if (!$rows) {
            if ($this->is_editing()) {
                echo '<div class="zig-notice">' . esc_html__('ریپیترِ سوالاتِ متداول خالی است یا کلیدِ متا اشتباه است.', 'zig3d-widgets') . '</div>';
            }

            return;
        }

        $expand_first = 'yes' === ($settings['expand_first'] ?? 'yes');

        echo '<div class="zig-faq">';

        foreach ($rows as $i => $row) {
            $this->render_item($row, $expand_first && 0 === $i);
        }

        echo '</div>';
    }

    /**
     * خواندنِ ریپیتر از متا و فیلترِ سطرهایِ بدونِ سوال.
     *
     * @return array<int,array{title:string,answer:string}>
     */
    private function rows(array $settings): array {
        $meta_key = trim((string) ($settings['meta_key'] ?? ''));

        if ('' === $meta_key) {
            $this->debug('هیچ کاری نشد: کلیدِ متا خالی است.');

            return [];
        }

        $manual_id = absint($settings['source_post_id'] ?? 0);
        $raw       = $manual_id > 0
            ? $this->read_meta('post', $manual_id, $meta_key)
            : $this->read_current_meta($meta_key);

        if (!is_array($raw)) {
            return [];
        }

        $title_key  = trim((string) ($settings['field_title'] ?? ''));
        $answer_key = trim((string) ($settings['field_answer'] ?? ''));

        $result  = [];
        $skipped = 0;

        foreach ($raw as $row) {
            if (!is_array($row)) {
                continue;
            }

            $title = trim((string) ($row[$title_key] ?? ''));

            /*
             * بدونِ سوال، آیتم اصلاً معنا ندارد — چه رسد به اینکه بتوان
             * بازش کرد. این هم‌زمان قانونِ «سطرِ کاملاً خالی → skip» را هم
             * پوشش می‌دهد: سطری که هیچ فیلدی ندارد، سوال هم ندارد.
             *
             * علتِ رایجِ «همه‌چیز خالی است» دقیقاً همین‌جاست: کلیدِ
             * ‎field_title‎ی تنظیمات با کلیدِ واقعیِ سطر یکی نیست (مثلاً
             * فاصله/دَش اضافه، یا نامِ فیلد در JetEngine چیزِ دیگری است).
             * چون هر سطر ساختارِ متفاوتی ندارد، فقط سطرِ *اول* را لاگ
             * می‌کنیم — کلیدهایِ واقعی‌اش را نشان می‌دهد.
             */
            if (!Markup::filled($title)) {
                if (0 === $skipped) {
                    $this->debug(sprintf(
                        'سطر رد شد — کلیدِ عنوانِ تنظیم‌شده «%s» در این سطر مقدار ندارد. کلیدهایِ واقعیِ سطر: %s',
                        $title_key,
                        implode(', ', array_map('strval', array_keys($row)))
                    ));
                }

                ++$skipped;

                continue;
            }

            $answer = trim((string) ($row[$answer_key] ?? ''));

            $result[] = [
                'title'  => $title,
                'answer' => Markup::filled($answer) ? $answer : '',
            ];
        }

        $this->debug(sprintf(
            'نتیجه: %d سطرِ خام، %d سطرِ معتبر، %d سطرِ بدونِ عنوان رد شد. کلیدِ عنوان=«%s» کلیدِ پاسخ=«%s»',
            count($raw),
            count($result),
            $skipped,
            $title_key,
            $answer_key
        ));

        return $result;
    }

    /**
     * خواندنِ متایِ ریپیتر از هرچیزی که *واقعاً* «پرسیده‌شده» — پست یا
     * ترم، هرکدام که ‎get_queried_object()‎ برگرداند.
     *
     * چرا این تفکیک لازم است: رویِ آرشیوِ یک دسته/برچسب،
     * ‎get_queried_object()‎ یک ‎WP_Term‎ می‌دهد، نه ‎WP_Post‎. اگر ریپیترِ
     * JetEngine رویِ «Taxonomy Meta» ساخته شده باشد (نه «Post Meta»)،
     * دیتا در ‎wp_termmeta‎ نشسته، نه ‎wp_postmeta‎ — و
     * ‎get_post_meta($term_id, ...)‎ با شناسهٔ یک ترم همیشه خالی برمی‌گردد،
     * چون دنبالِ آن شناسه در جدولِ اشتباه می‌گردد. این دقیقاً همان چیزی
     * است که «سوالات استخراج نشدن» را توضیح می‌دهد وقتی ریپیتر رویِ ترم
     * تعریف شده (مثلاً کلیدِ متایی مثلِ ‎…-terms‎).
     */
    private function read_current_meta(string $meta_key) {
        $queried = function_exists('get_queried_object') ? get_queried_object() : null;

        if ($queried instanceof \WP_Term) {
            return $this->read_meta('term', (int) $queried->term_id, $meta_key);
        }

        return $this->read_meta('post', $this->current_post_id(), $meta_key);
    }

    /**
     * @return mixed
     */
    private function read_meta(string $kind, int $id, string $meta_key) {
        if ($id <= 0) {
            $this->debug(sprintf('شناسهٔ %s معتبر نیست (۰ یا کمتر) — چیزی خوانده نشد.', 'term' === $kind ? 'ترم' : 'پست'));

            return null;
        }

        $raw = 'term' === $kind
            ? get_term_meta($id, $meta_key, true)
            : get_post_meta($id, $meta_key, true);

        $this->debug(sprintf(
            'خواندنِ متا: نوع=%s شناسه=%d کلید=«%s» → %s',
            $kind,
            $id,
            $meta_key,
            $this->debug_repr($raw)
        ));

        return maybe_unserialize($raw);
    }

    /**
     * لاگِ دیباگ — فقط وقتی ‎WP_DEBUG‎ روشن است، در ‎debug.log‎ی خودِ
     * وردپرس. رویِ سایتِ زنده با ‎WP_DEBUG‎ی خاموش (پیش‌فرض) کاملاً بی‌اثر
     * است؛ هیچ اثری روی خروجیِ فرانت‌اند یا کارآیی ندارد.
     */
    private function debug(string $message): void {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        error_log('[zig3d-faq] ' . $message);
    }

    /**
     * نمایشِ خلاصهٔ یک مقدارِ خام برایِ لاگ — نه ‎print_r‎ی کامل (که برایِ
     * ریپیترهایِ بزرگ خطوطِ لاگ را غیرِقابل‌خواندن می‌کند)، فقط نوع و
     * اندازه/چند کلیدِ اول.
     */
    private function debug_repr($raw): string {
        if (is_array($raw)) {
            $keys = array_slice(array_map('strval', array_keys($raw)), 0, 5);

            return sprintf('array(%d) [%s%s]', count($raw), implode(', ', $keys), count($raw) > 5 ? ', …' : '');
        }

        if (is_string($raw)) {
            $preview = mb_substr($raw, 0, 120);

            return sprintf('string(%d) "%s%s"', strlen($raw), $preview, strlen($raw) > 120 ? '…' : '');
        }

        if (null === $raw) {
            return 'NULL';
        }

        return var_export($raw, true);
    }

    /**
     * @param array{title:string,answer:string} $row
     */
    private function render_item(array $row, bool $open): void {
        printf('<details class="zig-faq__item"%s>', $open ? ' open' : '');

        echo '<summary class="zig-faq__question">';
        printf('<span class="zig-faq__question-text">%s</span>', Markup::text($row['title']));
        echo '<span class="zig-faq__toggle">' . Markup::svg_icon('chevron', 'zig-faq__chevron') . '</span>';
        echo '</summary>';

        if (Markup::filled($row['answer'])) {
            printf('<div class="zig-faq__answer">%s</div>', Markup::text($row['answer']));
        }

        echo '</details>';
    }

    private function current_post_id(): int {
        if (function_exists('get_queried_object_id')) {
            $id = (int) get_queried_object_id();

            if ($id > 0) {
                return $id;
            }
        }

        return (int) get_the_ID();
    }

    private function is_editing(): bool {
        return class_exists('\Elementor\Plugin')
            && isset(\Elementor\Plugin::$instance->editor)
            && \Elementor\Plugin::$instance->editor->is_edit_mode();
    }
}
