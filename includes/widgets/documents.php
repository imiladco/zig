<?php
namespace Zig3d_Widgets\Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;
use Zig3d_Widgets\Markup;
use Zig3d_Widgets\Plugin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * اسناد قابل دانلود.
 *
 * منبعِ داده یک ریپیترِ JetEngine Meta Box رویِ پستِ جاری است، نه چیزی که
 * این ویجت بسازد یا مدیریت کند. کلیدِ متا و کلیدِ هر فیلد از پنلِ المنتور
 * تنظیم می‌شود تا با هر کانفیگِ JetEngine هم‌اهنگ بماند.
 *
 *     div.zig-documents                ظرفِ گرید
 *       a.zig-documents__card          کل کارت = یک <a> (دانلود)
 *         div.zig-documents__top        ردیفِ بالا: آیکون + عنوان + نشان‌ها
 *           span.zig-documents__icon    آیکون ثابتِ فایل (در ظرفِ رنگی)
 *           span.zig-documents__body
 *             span.zig-documents__title
 *             ul.zig-documents__meta    نشان‌های فرمت/زبان/نسخه
 *               li.zig-documents__meta-item × N
 *         div.zig-documents__bottom      ردیفِ پایین: تاریخ·حجم + دکمهٔ دانلود
 *           div.zig-documents__meta-plain  تاریخ · حجم
 *           span.zig-documents__download  پیلِ «دانلود» + آیکون
 *
 * چرا کل کارت یک <a> است و دکمه‌ای درونش نیست: درخواست صریحاً «بدونِ دکمهٔ
 * تودرتو» خواست. یک لینکِ تودرتوی <a>/<button> در HTML نامعتبر است و
 * مرورگر آن را بازچینی می‌کند. این‌طور، کل سطح قابل‌کلیک است و با
 * صفحه‌کلید هم یک بار Tab به آن می‌رسد.
 *
 * بدونِ جاوااسکریپت: همه‌چیز پیوندِ واقعیِ <a href> است.
 */
final class Documents extends Widget_Base {

    use Traits\Box;

    public function get_name(): string {
        return 'zig3d-documents';
    }

    public function get_title(): string {
        return __('اسناد قابل دانلود', 'zig3d-widgets');
    }

    public function get_icon(): string {
        return 'eicon-document-file';
    }

    public function get_categories(): array {
        return [Plugin::CATEGORY];
    }

    public function get_keywords(): array {
        return ['documents', 'downloads', 'files', 'pdf', 'jetengine', 'سند', 'دانلود', 'فایل'];
    }

    public function get_style_depends(): array {
        return ['zig3d-widgets'];
    }

    public function has_widget_inner_wrapper(): bool {
        return false;
    }

    protected function register_controls(): void {
        $this->register_source_section();
        $this->register_fields_section();
        $this->register_visibility_section();
        $this->register_layout_section();
        $this->register_card_style_section();
        $this->register_icon_style_section();
        $this->register_text_style_section();
        $this->register_meta_style_section();
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
                'default'     => 'documents',
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

    /* =====================================================================
     * فیلدها
     * =================================================================== */

    /**
     * نگاشتِ نقشِ هر فیلد به کلیدِ متایِ آن در سطرِ ریپیتر.
     *
     * «قابل تنظیم بودنِ فیلدها» یعنی کلیدها از پنال به‌دستِ کاربر می‌آیند، نه
     * هاردکد — تا هر کانفیگِ JetEngine‌ای بدونِ تغییرِ کد هم‌اهنگ بماند.
     */
    private function register_fields_section(): void {
        $this->start_controls_section(
            'fields_section',
            ['label' => __('فیلدها', 'zig3d-widgets')]
        );

        $this->add_control(
            'field_file',
            [
                'label'       => __('کلیدِ فایل', 'zig3d-widgets'),
                'type'        => Controls_Manager::TEXT,
                'default'     => 'document_file',
                'description' => __('سطرِ بدونِ فایل نادیده گرفته می‌شود. آدرسِ فایل همان hrefِ کارت می‌شود.', 'zig3d-widgets'),
            ]
        );

        $this->add_control(
            'field_title',
            [
                'label'   => __('کلیدِ عنوان', 'zig3d-widgets'),
                'type'    => Controls_Manager::TEXT,
                'default' => 'document_title',
            ]
        );

        $this->add_control(
            'field_format',
            [
                'label'       => __('کلیدِ فرمت', 'zig3d-widgets'),
                'type'        => Controls_Manager::TEXT,
                'default'     => 'document_format',
                'description' => __('خالی بگذارید تا فرمت از خودِ فایل استخراج شود (پسوندِ آدرس).', 'zig3d-widgets'),
            ]
        );

        $this->add_control(
            'field_language',
            [
                'label'   => __('کلیدِ زبان', 'zig3d-widgets'),
                'type'    => Controls_Manager::TEXT,
                'default' => 'document_language',
            ]
        );

        $this->add_control(
            'field_version',
            [
                'label'   => __('کلیدِ نسخه', 'zig3d-widgets'),
                'type'    => Controls_Manager::TEXT,
                'default' => 'document_version',
            ]
        );

        $this->add_control(
            'field_date',
            [
                'label'       => __('کلیدِ تاریخ', 'zig3d-widgets'),
                'type'        => Controls_Manager::TEXT,
                'default'     => 'document_date',
                'description' => __('فیلدِ تاریخِ JetEngine را پشتیبانی می‌کند: timestamp یا رشتهٔ فرمت‌شده. رشتهٔ فرمت‌شده همان‌طور که هست نمایش داده می‌شود.', 'zig3d-widgets'),
            ]
        );

        $this->add_control(
            'field_size',
            [
                'label'       => __('کلیدِ حجم', 'zig3d-widgets'),
                'type'        => Controls_Manager::TEXT,
                'default'     => '',
                'description' => __('اختیاری. خالی بگذارید تا حجم از فایلِ پیوست استخراج شود؛ اگر نشد، اصلاً چاپ نمی‌شود.', 'zig3d-widgets'),
            ]
        );

        $this->end_controls_section();
    }

    /* =====================================================================
     * نمایش
     * =================================================================== */

    private function register_visibility_section(): void {
        $this->start_controls_section(
            'visibility_section',
            ['label' => __('نمایش', 'zig3d-widgets')]
        );

        foreach ([
            'show_format'   => __('فرمت', 'zig3d-widgets'),
            'show_language' => __('زبان', 'zig3d-widgets'),
            'show_version'  => __('نسخه', 'zig3d-widgets'),
            'show_date'     => __('تاریخ', 'zig3d-widgets'),
            'show_size'     => __('حجم', 'zig3d-widgets'),
        ] as $key => $label) {
            $this->add_control(
                $key,
                [
                    'label'   => $label,
                    'type'    => Controls_Manager::SWITCHER,
                    'default' => 'yes',
                ]
            );
        }

        $this->add_control(
            'show_download_label',
            [
                'label'   => __('برچسبِ «دانلود»', 'zig3d-widgets'),
                'type'    => Controls_Manager::SWITCHER,
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'download_label',
            [
                'label'     => __('متنِ برچسبِ دانلود', 'zig3d-widgets'),
                'type'      => Controls_Manager::TEXT,
                'default'   => __('دانلود', 'zig3d-widgets'),
                'condition' => ['show_download_label' => 'yes'],
            ]
        );

        $this->end_controls_section();
    }

    /* =====================================================================
     * چیدمان
     * =================================================================== */

    private function register_layout_section(): void {
        $this->start_controls_section(
            'layout_section',
            ['label' => __('چیدمان', 'zig3d-widgets')]
        );

        $this->add_responsive_control(
            'columns',
            [
                'label'              => __('تعدادِ ستون', 'zig3d-widgets'),
                'type'               => Controls_Manager::NUMBER,
                'min'                => 1,
                'max'                => 6,
                'default'            => 3,
                'selectors'          => [
                    '{{WRAPPER}} .zig-documents' => 'grid-template-columns: repeat({{VALUE}}, minmax(0, 1fr));',
                ],
            ]
        );

        $this->add_responsive_control(
            'gap',
            [
                'label'      => __('فاصلهٔ بین کارت‌ها', 'zig3d-widgets'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px', 'rem'],
                'range'      => ['px' => ['min' => 0, 'max' => 60]],
                'default'    => ['size' => 16, 'unit' => 'px'],
                'selectors'  => ['{{WRAPPER}} .zig-documents' => 'gap: {{SIZE}}{{UNIT}};'],
            ]
        );

        $this->add_responsive_control(
            'card_padding',
            [
                'label'      => __('فاصلهٔ داخلیِ کارت', 'zig3d-widgets'),
                'type'       => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', 'rem'],
                'default'    => ['top' => '20', 'right' => '20', 'bottom' => '20', 'left' => '20', 'unit' => 'px'],
                'selectors'  => ['{{WRAPPER}} .zig-documents__card' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
            ]
        );

        $this->add_responsive_control(
            'text_align',
            [
                'label'     => __('تراز متن', 'zig3d-widgets'),
                'type'      => Controls_Manager::CHOOSE,
                'options'   => [
                    'start'  => ['title' => __('ابتدا', 'zig3d-widgets'), 'icon' => 'eicon-text-align-right'],
                    'center' => ['title' => __('وسط', 'zig3d-widgets'), 'icon' => 'eicon-text-align-center'],
                    'end'    => ['title' => __('انتها', 'zig3d-widgets'), 'icon' => 'eicon-text-align-left'],
                ],
                'selectors' => ['{{WRAPPER}} .zig-documents__card' => 'text-align: {{VALUE}};'],
            ]
        );

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل
     * =================================================================== */

    private function register_card_style_section(): void {
        $this->start_controls_section(
            'card_style_section',
            [
                'label' => __('کارت', 'zig3d-widgets'),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_box_style_tabs('card', '.zig-documents__card', '.zig-documents__card');

        $this->end_controls_section();
    }

    private function register_icon_style_section(): void {
        $this->start_controls_section(
            'icon_style_section',
            [
                'label' => __('آیکون', 'zig3d-widgets'),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'icon_color',
            [
                'label'     => __('رنگِ آیکون', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => ['{{WRAPPER}} .zig-documents__icon' => 'color: {{VALUE}};'],
            ]
        );

        $this->add_control(
            'icon_bg',
            [
                'label'     => __('پس‌زمینهٔ ظرفِ آیکون', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => ['{{WRAPPER}} .zig-documents__icon' => 'background-color: {{VALUE}};'],
            ]
        );

        $this->add_responsive_control(
            'icon_size',
            [
                'label'      => __('اندازهٔ ظرف', 'zig3d-widgets'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px', 'rem'],
                'range'      => ['px' => ['min' => 24, 'max' => 96]],
                'default'    => ['size' => 48, 'unit' => 'px'],
                'selectors'  => [
                    '{{WRAPPER}} .zig-documents__icon' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'icon_radius',
            [
                'label'      => __('گردیِ گوشهٔ ظرف', 'zig3d-widgets'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px', '%'],
                'range'      => ['px' => ['min' => 0, 'max' => 40]],
                'default'    => ['size' => 14, 'unit' => 'px'],
                'selectors'  => ['{{WRAPPER}} .zig-documents__icon' => 'border-radius: {{SIZE}}{{UNIT}};'],
            ]
        );

        $this->add_responsive_control(
            'icon_gap',
            [
                'label'      => __('فاصله تا متن', 'zig3d-widgets'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px', 'rem'],
                'range'      => ['px' => ['min' => 0, 'max' => 40]],
                'default'    => ['size' => 14, 'unit' => 'px'],
                'selectors'  => ['{{WRAPPER}} .zig-documents__top' => 'gap: {{SIZE}}{{UNIT}};'],
            ]
        );

        $this->end_controls_section();
    }

    private function register_text_style_section(): void {
        $this->start_controls_section(
            'text_style_section',
            [
                'label' => __('عنوان', 'zig3d-widgets'),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'     => 'title_typography',
                'selector' => '{{WRAPPER}} .zig-documents__title',
            ]
        );

        $this->start_controls_tabs('title_color_tabs');

        $this->start_controls_tab('title_color_normal', ['label' => __('عادی', 'zig3d-widgets')]);
        $this->add_control(
            'title_color',
            [
                'label'     => __('رنگ', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => ['{{WRAPPER}} .zig-documents__title' => 'color: {{VALUE}};'],
            ]
        );
        $this->end_controls_tab();

        $this->start_controls_tab('title_color_hover', ['label' => __('هاور', 'zig3d-widgets')]);
        $this->add_control(
            'title_color_hover',
            [
                'label'     => __('رنگ', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .zig-documents__card:hover .zig-documents__title'        => 'color: {{VALUE}};',
                    '{{WRAPPER}} .zig-documents__card:focus-visible .zig-documents__title' => 'color: {{VALUE}};',
                ],
            ]
        );
        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->end_controls_section();
    }

    private function register_meta_style_section(): void {
        $this->start_controls_section(
            'meta_style_section',
            [
                'label' => __('مشخصات و برچسب دانلود', 'zig3d-widgets'),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'     => 'meta_typography',
                'label'    => __('تایپوگرافیِ نشان‌ها', 'zig3d-widgets'),
                'selector' => '{{WRAPPER}} .zig-documents__meta-item',
            ]
        );

        $this->add_control(
            'meta_color',
            [
                'label'     => __('رنگِ نشان‌ها', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => ['{{WRAPPER}} .zig-documents__meta-item' => 'color: {{VALUE}};'],
            ]
        );

        $this->add_control(
            'meta_bg',
            [
                'label'     => __('پس‌زمینهٔ نشان‌ها', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => ['{{WRAPPER}} .zig-documents__meta-item' => 'background-color: {{VALUE}};'],
            ]
        );

        $this->add_responsive_control(
            'meta_radius',
            [
                'label'      => __('گردیِ نشان‌ها', 'zig3d-widgets'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px', '%'],
                'range'      => ['px' => ['min' => 0, 'max' => 40]],
                'default'    => ['size' => 6, 'unit' => 'px'],
                'selectors'  => ['{{WRAPPER}} .zig-documents__meta-item' => 'border-radius: {{SIZE}}{{UNIT}};'],
            ]
        );

        $this->add_control(
            'meta_plain_color',
            [
                'label'     => __('رنگِ تاریخ/حجم', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'separator' => 'before',
                'selectors' => ['{{WRAPPER}} .zig-documents__meta-plain' => 'color: {{VALUE}};'],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'     => 'download_typography',
                'label'    => __('تایپوگرافیِ برچسب دانلود', 'zig3d-widgets'),
                'selector' => '{{WRAPPER}} .zig-documents__download',
                'separator' => 'before',
            ]
        );

        $this->add_control(
            'download_color',
            [
                'label'     => __('رنگِ برچسب دانلود', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => ['{{WRAPPER}} .zig-documents__download' => 'color: {{VALUE}};'],
            ]
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name'     => 'download_border',
                'selector' => '{{WRAPPER}} .zig-documents__download',
            ]
        );

        $this->add_control(
            'download_bg',
            [
                'label'     => __('پس‌زمینهٔ برچسب دانلود', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => ['{{WRAPPER}} .zig-documents__download' => 'background-color: {{VALUE}};'],
            ]
        );

        $this->add_responsive_control(
            'download_radius',
            [
                'label'      => __('گردیِ گوشه‌های برچسب دانلود', 'zig3d-widgets'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px', '%'],
                'range'      => ['px' => ['min' => 0, 'max' => 40]],
                'selectors'  => ['{{WRAPPER}} .zig-documents__download' => 'border-radius: {{SIZE}}{{UNIT}};'],
            ]
        );

        $this->end_controls_section();
    }

    /* =====================================================================
     * رندر
     * =================================================================== */

    protected function render(): void {
        $settings = $this->get_settings_for_display();

        $rows = $this->rows($settings);

        if (!$rows) {
            if ($this->is_editing()) {
                echo '<div class="zig-notice">' . esc_html__('ریپیترِ اسناد خالی است یا کلیدِ متا اشتباه است.', 'zig3d-widgets') . '</div>';
            }
            return;
        }

        $this->add_render_attribute('documents', 'class', 'zig-documents');
        ?>
        <div <?php $this->print_render_attribute_string('documents'); ?>>
            <?php foreach ($rows as $row) : ?>
                <?php $this->render_card($row, $settings); ?>
            <?php endforeach; ?>
        </div>
        <?php
    }

    /**
     * خواندنِ ریپیتر از متایِ پست و فیلترِ سطرهای بدونِ فایل.
     *
     * @return array<int,array{url:string,attachment_id:int,title:string,format:string,language:string,version:string,date:string,size:string}>
     */
    private function rows(array $settings): array {
        $meta_key = trim((string) ($settings['meta_key'] ?? ''));
        $post_id  = absint($settings['source_post_id'] ?? 0);

        if ($post_id <= 0) {
            $post_id = $this->current_post_id();
        }

        if ('' === $meta_key || $post_id <= 0) {
            return [];
        }

        $raw = maybe_unserialize(get_post_meta($post_id, $meta_key, true));

        if (!is_array($raw)) {
            return [];
        }

        $file_key     = trim((string) ($settings['field_file'] ?? ''));
        $title_key    = trim((string) ($settings['field_title'] ?? ''));
        $format_key   = trim((string) ($settings['field_format'] ?? ''));
        $language_key = trim((string) ($settings['field_language'] ?? ''));
        $version_key  = trim((string) ($settings['field_version'] ?? ''));
        $date_key     = trim((string) ($settings['field_date'] ?? ''));
        $size_key     = trim((string) ($settings['field_size'] ?? ''));

        $result = [];

        foreach ($raw as $row) {
            if (!is_array($row)) {
                continue;
            }

            $file = self::normalize_file($row[$file_key] ?? null);

            /*
             * بدونِ فایل، کارت قابل‌دانلود نیست — سطر کنار می‌رود. این هم‌زمان
             * قانونِ «سطرِ کاملاً خالی → skip» را هم پوشش می‌دهد.
             */
            if ('' === $file['url']) {
                continue;
            }

            $title = trim((string) ($row[$title_key] ?? ''));

            $result[] = [
                'url'           => $file['url'],
                'attachment_id' => $file['attachment_id'],
                'title'         => Markup::filled($title) ? $title : '',
                'format'        => $this->resolve_format($row, $format_key, $file),
                'language'      => trim((string) ($row[$language_key] ?? '')),
                'version'       => trim((string) ($row[$version_key] ?? '')),
                'date'          => $this->resolve_date($row, $date_key),
                'size'          => $this->resolve_size($row, $size_key, $file),
            ];
        }

        return $result;
    }

    /**
     * نرمال‌سازیِ فیلدِ فایلِ JetEngine — سه شکلِ ذخیرهٔ ممکن هم پشتیبانی می‌شود:
     *   ۱) فقط شناسهٔ پیوست (عدد/رشتهٔ عددی)
     *   ۲) فقط آدرس (رشتهٔ غیرِعددی)
     *   ۳) آرایه‌ای با کلیدِ id/ID/attachment_id یا url/src/source
     *
     * @param mixed $raw
     * @return array{url:string,attachment_id:int}
     */
    private static function normalize_file($raw): array {
        $empty = ['url' => '', 'attachment_id' => 0];

        if (is_numeric($raw)) {
            $id = (int) $raw;
            if ($id <= 0) {
                return $empty;
            }
            $url = (string) wp_get_attachment_url($id);

            return '' !== $url ? ['url' => $url, 'attachment_id' => $id] : $empty;
        }

        if (is_string($raw)) {
            $url = trim($raw);

            return '' !== $url ? ['url' => $url, 'attachment_id' => 0] : $empty;
        }

        if (is_array($raw)) {
            foreach (['id', 'ID', 'attachment_id'] as $key) {
                if (!empty($raw[$key]) && is_numeric($raw[$key])) {
                    $id = (int) $raw[$key];
                    $url = (string) wp_get_attachment_url($id);

                    return '' !== $url ? ['url' => $url, 'attachment_id' => $id] : $empty;
                }
            }

            foreach (['url', 'src', 'source'] as $key) {
                if (!empty($raw[$key]) && is_string($raw[$key])) {
                    $url = trim($raw[$key]);

                    return '' !== $url ? ['url' => $url, 'attachment_id' => 0] : $empty;
                }
            }
        }

        return $empty;
    }

    /**
     * فرمت: اولویت با مقدارِ دستیِ کاربر است؛ وگرنه از پسوندِ آدرسِ فایل.
     *
     * @param array{url:string,attachment_id:int} $file
     */
    private function resolve_format(array $row, string $format_key, array $file): string {
        if ('' !== $format_key) {
            $explicit = trim((string) ($row[$format_key] ?? ''));
            if (Markup::filled($explicit)) {
                return strtoupper($explicit);
            }
        }

        $ext = pathinfo((string) parse_url($file['url'], PHP_URL_PATH), PATHINFO_EXTENSION);

        return '' !== $ext ? strtoupper($ext) : '';
    }

    /**
     * حجمِ فایل — فقط وقتی قابلِ اعتماد است که فایل یک پیوستِ واقعی باشد و
     * مسیرِ فیزیکیِ آن رویِ دیسک در دسترس باشد. در غیرِ این‌صورت (فقط آدرس،
     * یا فایلِ خارجی) کارت بدونِ حجم چاپ می‌شود، نه با حجمِ غلط.
     *
     * @param array{url:string,attachment_id:int} $file
     */
    private function resolve_size(array $row, string $size_key, array $file): string {
        if ('' !== $size_key) {
            $explicit = trim((string) ($row[$size_key] ?? ''));
            if (Markup::filled($explicit)) {
                return $explicit;
            }
        }

        if ($file['attachment_id'] > 0) {
            $path = (string) get_attached_file($file['attachment_id']);

            if ('' !== $path && @is_file($path)) {
                $bytes = @filesize($path);

                if (false !== $bytes && $bytes > 0) {
                    return size_format($bytes);
                }
            }
        }

        return '';
    }

    /**
     * تاریخ — JetEngine Date field می‌تواند timestamp یا رشتهٔ فرمت‌شده برگرداند.
     *
     * @return string تاریخِ فرمت‌شده یا خالی
     */
    private function resolve_date(array $row, string $date_key): string {
        if ('' === $date_key) {
            return '';
        }

        $raw = $row[$date_key] ?? null;

        if (null === $raw || '' === $raw) {
            return '';
        }

        // اگر عدد باشد (timestamp)، فرمت کن
        if (is_numeric($raw)) {
            $timestamp = (int) $raw;
            if ($timestamp <= 0) {
                return '';
            }

            // از wp_date استفاده کن اگر موجود است، وگرنه date_i18n
            if (function_exists('wp_date')) {
                return wp_date('Y/m/d', $timestamp);
            }

            if (function_exists('date_i18n')) {
                return date_i18n('Y/m/d', $timestamp);
            }

            return date('Y/m/d', $timestamp);
        }

        // اگر رشته باشد، همان‌طور که هست برگردان (JetEngine قبلاً فرمت کرده)
        $formatted = trim((string) $raw);

        return Markup::filled($formatted) ? $formatted : '';
    }

    /**
     * @param array{url:string,attachment_id:int,title:string,format:string,language:string,version:string,date:string,size:string} $row
     */
    private function render_card(array $row, array $settings): void {
        $show_format   = 'yes' === ($settings['show_format'] ?? 'yes');
        $show_language = 'yes' === ($settings['show_language'] ?? 'yes');
        $show_version  = 'yes' === ($settings['show_version'] ?? 'yes');
        $show_date     = 'yes' === ($settings['show_date'] ?? 'yes');
        $show_size     = 'yes' === ($settings['show_size'] ?? 'yes');
        $show_download = 'yes' === ($settings['show_download_label'] ?? 'yes');

        $has_badge = ($show_format && '' !== $row['format'])
            || ($show_language && '' !== $row['language'])
            || ($show_version && '' !== $row['version']);

        $has_plain = ($show_date && '' !== $row['date'])
            || ($show_size && '' !== $row['size']);

        /*
         * ‎render_card()‎ برای هر ردیفِ ریپیتر یک بار صدا زده می‌شود، اما همه
         * روی همین یک کلیدِ رندر-اتریبیوت («card») کار می‌کنند. رفتارِ
         * پیش‌فرضِ ‎add_render_attribute‎ در المنتور «افزودن» است نه
         * «جایگزینی» — بدونِ ‎overwrite: true‎، href/class/aria-label کارتِ
         * دوم به کارتِ اول اضافه می‌شد (چند آدرس در یک href!)، نه جایگزینِ آن.
         */
        $this->add_render_attribute('card', 'class', 'zig-documents__card', true);
        $this->add_render_attribute('card', 'href', esc_url($row['url']), true);

        $title = $row['title'];
        if ('' !== $title) {
            $this->add_render_attribute('card', 'aria-label', $title, true);
        } else {
            $this->remove_render_attribute('card', 'aria-label');
        }

        $download_label = trim((string) ($settings['download_label'] ?? ''));

        printf(
            '<a %s>',
            $this->get_render_attribute_string('card') // phpcs:ignore WordPress.Security.EscapeOutput -- Elementor render attributes are escaped on set
        );

        echo '<div class="zig-documents__top">';

        echo $this->icon_svg(); // phpcs:ignore WordPress.Security.EscapeOutput -- SVG ثابتِ محلی

        echo '<span class="zig-documents__body">';

        if ('' !== $title) {
            printf('<span class="zig-documents__title">%s</span>', esc_html($title));
        }

        if ($has_badge) {
            echo '<ul class="zig-documents__meta" role="list">';

            if ($show_format && '' !== $row['format']) {
                printf('<li class="zig-documents__meta-item">%s</li>', esc_html($row['format']));
            }
            if ($show_language && '' !== $row['language']) {
                printf('<li class="zig-documents__meta-item">%s</li>', esc_html($row['language']));
            }
            if ($show_version && '' !== $row['version']) {
                printf('<li class="zig-documents__meta-item">%s</li>', esc_html($row['version']));
            }

            echo '</ul>';
        }

        echo '</span>'; // .zig-documents__body
        echo '</div>'; // .zig-documents__top

        echo '<div class="zig-documents__bottom">';

        if ($has_plain) {
            $plain_parts = [];
            if ($show_date && '' !== $row['date']) {
                $plain_parts[] = $row['date'];
            }
            if ($show_size && '' !== $row['size']) {
                $plain_parts[] = $row['size'];
            }

            printf('<div class="zig-documents__meta-plain">%s</div>', esc_html(implode(' · ', $plain_parts)));
        }

        if ($show_download && Markup::filled($download_label)) {
            printf(
                '<span class="zig-documents__download">%s%s</span>',
                $this->download_icon_svg(), // phpcs:ignore WordPress.Security.EscapeOutput -- SVG ثابتِ محلی
                esc_html($download_label)
            );
        }

        echo '</div>'; // .zig-documents__bottom

        echo '</a>';
    }

    /**
     * آیکونِ ثابتِ فایل — محلیِ همین ویجت.
     *
     * هیچ آیکونِ «دانلود/فایل»ِ قابل‌استفادهٔ دوباره در افزونه نیست
     * (Markup::svg_icon فقط فلتر/سطل/پیکان دارد)، پس یک مسیرِ ثابتِ اینجا
     * می‌نشیند. currentColor می‌گیرد تا با کنترلِ رنگ هم‌رنگ شود.
     */
    private function icon_svg(): string {
        return '<span class="zig-documents__icon" aria-hidden="true" focusable="false">'
            . '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"'
            . ' stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
            . '<path d="M14 3H7a2 2 0 00-2 2v14a2 2 0 002 2h10a2 2 0 002-2V8z"/>'
            . '<path d="M14 3v5h5"/>'
            . '<path d="M9 14h6M9 17h4"/>'
            . '</svg>'
            . '</span>';
    }

    /**
     * آیکونِ دانلودِ کوچک داخلِ پیلِ دانلود.
     */
    private function download_icon_svg(): string {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"'
            . ' stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
            . '<path d="M12 3v12"/>'
            . '<path d="M7 10l5 5 5-5"/>'
            . '<path d="M5 21h14"/>'
            . '</svg>';
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
