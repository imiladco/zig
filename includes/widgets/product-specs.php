<?php
namespace Zig3d_Widgets\Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;
use Zig3d_Widgets\Markup;
use Zig3d_Widgets\Plugin;
use Zig3d_Widgets\Price;
use Zig3d_Widgets\Spec_Store;
use Zig3d_Widgets\Spec_Value;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * مشخصاتِ فنیِ محصول، گروه‌بندی‌شده — همان چیزی که در «گروه‌های مشخصاتِ
 * فنی» (پنلِ مدیریت) ساخته و به دسته‌هایِ محصول وصل می‌شود.
 *
 *     div.zig-specs
 *       details.zig-specs__group[open]      یک گروه (یا div، در حالتِ «تخت»)
 *         summary.zig-specs__group-title     عنوانِ گروه — کلیک‌پذیر (یا div)
 *           span.zig-specs__group-title-text
 *           svg.zig-specs__chevron
 *         dl.zig-specs__list
 *           div.zig-specs__row
 *             dt.zig-specs__label
 *             dd.zig-specs__value
 *
 * چرا ‎<details>/<summary>‎ برایِ آکاردئون: باز/بسته‌شدن رفتارِ بومیِ
 * مرورگر است — صفر بایت جاوااسکریپت، صفحه‌خوان خودش «باز»/«بسته» را
 * اعلام می‌کند، و بدونِ CSS/JS هم هرچیزی که ‎open‎ نداشته باز نمی‌شود ولی
 * محتوایش هنوز در DOM و قابلِ جست‌وجوست (نه display:none که موتورهای
 * جست‌وجو گاهی نادیده می‌گیرند). حالتِ «تخت» (غیرِ‌آکاردئونی) از همین
 * مارک‌آپ با تگِ ساده‌تر (‎div‎ به‌جایِ ‎details‎) استفاده می‌کند تا کاربر
 * نتواند چیزی را ببندد که قرار است همیشه باز بماند — چیزی که با فقط
 * گذاشتنِ ‎open‎ رویِ ‎details‎ ممکن نیست، چون کلیک رویِ ‎summary‎ باز هم
 * می‌بندَدش.
 *
 * منطقِ «این محصول رویِ این مشخصه چه مقداری دارد» در ‎Spec_Value‎ است، نه
 * اینجا — دقیقاً همان مرزی که کارتِ محصول هم بینِ «چه چیزی هست» و «چطور
 * چاپ شود» گذاشته. اینجا فقط تصمیم می‌گیرد کدام گروه‌ها به این محصول
 * می‌خورند و چطور رندر شوند.
 */
final class Product_Specs extends Widget_Base {

    public function get_name(): string {
        return 'zig3d-product-specs';
    }

    public function get_title(): string {
        return __('مشخصاتِ فنی محصول', 'zig3d-widgets');
    }

    public function get_icon(): string {
        return 'eicon-table';
    }

    public function get_categories(): array {
        return [Plugin::CATEGORY];
    }

    public function get_keywords(): array {
        return ['specs', 'specifications', 'attributes', 'table', 'مشخصات', 'ویژگی', 'جدول', 'فنی'];
    }

    public function get_style_depends(): array {
        return ['zig3d-widgets'];
    }

    /**
     * بدونِ این اسکریپت هم آکاردئون کار می‌کند — بومیِ ‎<details>‎، بدونِ
     * انیمیشن و بدونِ تک‌بازشو. فایل فقط آن دو رفتار را اضافه می‌کند.
     */
    public function get_script_depends(): array {
        return ['zig3d-specs'];
    }

    public function has_widget_inner_wrapper(): bool {
        return false;
    }

    protected function register_controls(): void {
        $this->register_product_section();
        $this->register_layout_section();
        $this->register_group_style_section();
        $this->register_header_style_section();
        $this->register_header_open_style_section();
        $this->register_toggle_style_section();
        $this->register_body_style_section();
        $this->register_row_style_section();
        $this->register_closed_group_style_section();
    }

    /* =====================================================================
     * محتوا
     * =================================================================== */

    private function register_product_section(): void {
        $this->start_controls_section(
            'product_section',
            ['label' => __('محصول', 'zig3d-widgets')]
        );

        $this->add_control(
            'product_id',
            [
                'label'       => __('شناسهٔ محصول', 'zig3d-widgets'),
                'type'        => Controls_Manager::NUMBER,
                'min'         => 0,
                'dynamic'     => ['active' => true],
                'description' => __('خالی بگذارید تا محصول جاری استفاده شود — چه در صفحهٔ محصول، چه داخل حلقهٔ فروشگاه یا قالب حلقهٔ المنتور.', 'zig3d-widgets'),
            ]
        );

        $this->add_control(
            'groups_note',
            [
                'type'            => Controls_Manager::RAW_HTML,
                'raw'             => sprintf(
                    /* translators: %s: لینک صفحهٔ گروه‌های مشخصات فنی */
                    __('گروه‌ها و مشخصه‌هایشان از %s ساخته می‌شوند و از دسته‌بندیِ محصول می‌آیند؛ اینجا چیزی برای انتخابِ دستی نیست.', 'zig3d-widgets'),
                    '<strong>' . esc_html__('«گروه‌های مشخصات فنی»', 'zig3d-widgets') . '</strong>'
                ),
                'content_classes' => 'elementor-descriptor',
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
            'layout_mode',
            [
                'label'   => __('نوعِ نمایش', 'zig3d-widgets'),
                'type'    => Controls_Manager::SELECT,
                'default' => 'accordion',
                'options' => [
                    'accordion' => __('آکاردئونی (باز/بسته‌شدنی)', 'zig3d-widgets'),
                    'flat'      => __('جدولیِ ساده (همه باز)', 'zig3d-widgets'),
                ],
            ]
        );

        $this->add_control(
            'expand_first',
            [
                'label'     => __('گروهِ اول باز باشد', 'zig3d-widgets'),
                'type'      => Controls_Manager::SWITCHER,
                'default'   => 'yes',
                'condition' => ['layout_mode' => 'accordion'],
            ]
        );

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل
     * =================================================================== */

    private function register_group_style_section(): void {
        $this->start_controls_section(
            'group_style_section',
            [
                'label' => __('آکاردئون / گروه', 'zig3d-widgets'),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'card_bg',
            [
                'label'     => __('پس‌زمینهٔ کارتِ بسته', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'default'   => '#FFFFFF',
                'selectors' => ['{{WRAPPER}} .zig-specs' => '--zig-specs-card-bg: {{VALUE}};'],
            ]
        );

        $this->add_control(
            'card_bg_open',
            [
                'label'     => __('پس‌زمینهٔ کارتِ باز', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'default'   => '#FFFFFF',
                'condition' => ['layout_mode' => 'accordion'],
                'selectors' => ['{{WRAPPER}} .zig-specs' => '--zig-specs-card-bg-open: {{VALUE}};'],
            ]
        );

        $this->add_control(
            'card_border_color',
            [
                'label'     => __('رنگِ مرزِ کارتِ بسته', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'default'   => '#E4E5EA',
                'selectors' => ['{{WRAPPER}} .zig-specs' => '--zig-specs-card-border: {{VALUE}};'],
            ]
        );

        $this->add_control(
            'card_border_width',
            [
                'label'      => __('پهنایِ مرزِ کارت', 'zig3d-widgets'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range'      => ['px' => ['min' => 0, 'max' => 6]],
                'default'    => ['size' => 1, 'unit' => 'px'],
                /*
                 * همان پهنا رویِ کارتِ باز و بسته، هر دو — طرح فقط رنگِ مرز را
                 * بینِ باز/بسته فرق می‌گذارد، نه پهنایش.
                 */
                'selectors'  => ['{{WRAPPER}} .zig-specs' => '--zig-specs-card-border-width: {{SIZE}}{{UNIT}};'],
            ]
        );

        $this->add_control(
            'accent_border_color',
            [
                'label'     => __('رنگِ مرزِ کارتِ باز', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'default'   => '#D8C8FB',
                'condition' => ['layout_mode' => 'accordion'],
                'selectors' => ['{{WRAPPER}} .zig-specs' => '--zig-specs-accent-border: {{VALUE}};'],
            ]
        );

        $this->add_control(
            'group_radius',
            [
                'label'       => __('شعاعِ گوشه‌های کارت', 'zig3d-widgets'),
                'type'        => Controls_Manager::SLIDER,
                'size_units'  => ['px'],
                'range'       => ['px' => ['min' => 0, 'max' => 40]],
                'default'     => ['size' => 16, 'unit' => 'px'],
                'description' => __('یک شعاعِ مشترک برایِ کارتِ باز و بلوکِ کارت‌هایِ بسته؛ فقط گوشه‌هایِ لبهٔ واقعیِ فهرست گرد می‌شوند.', 'zig3d-widgets'),
                'selectors'   => ['{{WRAPPER}} .zig-specs' => '--zig-specs-radius: {{SIZE}}{{UNIT}};'],
            ]
        );

        $this->add_responsive_control(
            'open_card_gap',
            [
                'label'       => __('فاصلهٔ کارتِ باز از بسته‌های اطراف', 'zig3d-widgets'),
                'type'        => Controls_Manager::SLIDER,
                'size_units'  => ['px'],
                'range'       => ['px' => ['min' => 0, 'max' => 60]],
                'default'     => ['size' => 21, 'unit' => 'px'],
                'condition'   => ['layout_mode' => 'accordion'],
                /*
                 * رویِ متغیرِ CSS، نه مستقیم رویِ ‎margin-block‎: خودِ استایل‌شیت
                 * یک استثنایِ ‎:first-child‎/‎:last-child‎ (بدونِ فاصله در لبه)
                 * دارد که به همین متغیر بند است. اگر اینجا مستقیم
                 * ‎margin-block‎ می‌نوشتیم، آن استثنا با تزریقِ ‎<style>‎ِ
                 * المنتور (که دیرتر از فایلِ استایل لود می‌شود) بی‌صدا
                 * می‌شکست.
                 */
                'selectors'   => ['{{WRAPPER}} .zig-specs' => '--zig-specs-open-gap: {{SIZE}}{{UNIT}};'],
            ]
        );

        $this->end_controls_section();
    }

    private function register_header_style_section(): void {
        $this->start_controls_section(
            'header_style_section',
            [
                'label' => __('سرستون', 'zig3d-widgets'),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'     => 'group_title_typography',
                'label'    => __('تایپوگرافیِ عنوان', 'zig3d-widgets'),
                'selector' => '{{WRAPPER}} .zig-specs__group-title-text',
            ]
        );

        $this->add_control(
            'group_title_color',
            [
                'label'     => __('رنگِ متن', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => ['{{WRAPPER}} .zig-specs__group-title-text' => 'color: {{VALUE}};'],
            ]
        );

        $this->add_control(
            'header_bg',
            [
                'label'     => __('پس‌زمینه (حالتِ بسته)', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'default'   => '#FFFFFF',
                'separator' => 'before',
                'selectors' => ['{{WRAPPER}} .zig-specs' => '--zig-specs-header-bg: {{VALUE}};'],
            ]
        );

        $this->add_control(
            'group_header_bg_hover',
            [
                'label'     => __('پس‌زمینه در هاور', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'default'   => '#F6F3FD',
                'selectors' => ['{{WRAPPER}} .zig-specs' => '--zig-specs-header-bg-hover: {{VALUE}};'],
            ]
        );

        $this->add_responsive_control(
            'header_height',
            [
                'label'       => __('حداقلِ ارتفاع', 'zig3d-widgets'),
                'type'        => Controls_Manager::SLIDER,
                'size_units'  => ['px'],
                'range'       => ['px' => ['min' => 40, 'max' => 160]],
                'default'     => ['size' => 81, 'unit' => 'px'],
                'description' => __('حالتِ بازِ سرستون همیشه ۴px کمتر از همین عدد می‌ماند — طبقِ نسبتِ طرح.', 'zig3d-widgets'),
                'selectors'   => ['{{WRAPPER}} .zig-specs' => '--zig-specs-header-height: {{SIZE}}{{UNIT}};'],
            ]
        );

        $this->add_responsive_control(
            'group_title_padding',
            [
                'label'      => __('فاصلهٔ داخلی', 'zig3d-widgets'),
                'type'       => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', 'rem'],
                'selectors'  => ['{{WRAPPER}} .zig-specs__group-title' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
            ]
        );

        $this->add_control(
            'header_border_bottom_color',
            [
                'label'     => __('رنگِ مرزِ پایین (حالتِ بسته)', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'default'   => 'transparent',
                'separator' => 'before',
                'selectors' => ['{{WRAPPER}} .zig-specs' => '--zig-specs-header-border-bottom: {{VALUE}};'],
            ]
        );

        $this->add_control(
            'header_border_bottom_width',
            [
                'label'      => __('پهنایِ مرزِ پایین (حالتِ بسته)', 'zig3d-widgets'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range'      => ['px' => ['min' => 0, 'max' => 6]],
                'default'    => ['size' => 0, 'unit' => 'px'],
                'selectors'  => ['{{WRAPPER}} .zig-specs' => '--zig-specs-header-border-bottom-width: {{SIZE}}{{UNIT}};'],
            ]
        );

        $this->add_control(
            'group_accent_color',
            [
                'label'       => __('رنگِ حلقهٔ فوکوسِ کیبوردی', 'zig3d-widgets'),
                'type'        => Controls_Manager::COLOR,
                'default'     => '#7B5CFF',
                'separator'   => 'before',
                'description' => __('فقط وقتی سرستون با Tab فوکوس می‌گیرد دیده می‌شود.', 'zig3d-widgets'),
                'selectors'   => ['{{WRAPPER}} .zig-specs' => '--zig-specs-accent: {{VALUE}};'],
            ]
        );

        $this->end_controls_section();
    }

    private function register_header_open_style_section(): void {
        $this->start_controls_section(
            'header_open_style_section',
            [
                'label'     => __('سرستونِ باز (حالتِ فعال)', 'zig3d-widgets'),
                'tab'       => Controls_Manager::TAB_STYLE,
                'condition' => ['layout_mode' => 'accordion'],
            ]
        );

        $this->add_control(
            'header_text_color_open',
            [
                'label'     => __('رنگِ متن', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'default'   => '#1E1E2D',
                'selectors' => ['{{WRAPPER}} .zig-specs' => '--zig-specs-title-color-open: {{VALUE}};'],
            ]
        );

        $this->add_control(
            'group_header_bg_open',
            [
                'label'       => __('پس‌زمینه', 'zig3d-widgets'),
                'type'        => Controls_Manager::COLOR,
                'default'     => '#FBF9FF',
                'description' => __('سرستونِ بسته با این یکی فرق می‌کند؛ اینجا فقط رگهٔ خیلی‌کمِ یاسیِ سرستونِ بازشده را کنترل می‌کند.', 'zig3d-widgets'),
                'selectors'   => ['{{WRAPPER}} .zig-specs' => '--zig-specs-header-bg-open: {{VALUE}};'],
            ]
        );

        $this->add_control(
            'header_border_open_color',
            [
                'label'       => __('رنگِ مرزِ پایین', 'zig3d-widgets'),
                'type'        => Controls_Manager::COLOR,
                'default'     => '#ECE4FB',
                'separator'   => 'before',
                'description' => __('همین خط، مرزِ بینِ سرستون و جدولِ زیرش هم هست — کنترلِ جداگانه‌ای برایِ «Divider جدول» ساخته نشده تا دو کنترل رویِ یک خط اثر نگذارند.', 'zig3d-widgets'),
                'selectors'   => ['{{WRAPPER}} .zig-specs' => '--zig-specs-divider-open: {{VALUE}};'],
            ]
        );

        $this->add_control(
            'header_border_open_width',
            [
                'label'      => __('پهنایِ مرزِ پایین', 'zig3d-widgets'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range'      => ['px' => ['min' => 0, 'max' => 6]],
                'default'    => ['size' => 1, 'unit' => 'px'],
                'selectors'  => ['{{WRAPPER}} .zig-specs' => '--zig-specs-divider-width: {{SIZE}}{{UNIT}};'],
            ]
        );

        $this->end_controls_section();
    }

    private function register_toggle_style_section(): void {
        $this->start_controls_section(
            'toggle_style_section',
            [
                'label'     => __('فلش/نشانِ باز-بسته', 'zig3d-widgets'),
                'tab'       => Controls_Manager::TAB_STYLE,
                'condition' => ['layout_mode' => 'accordion'],
            ]
        );

        $this->add_responsive_control(
            'badge_size',
            [
                'label'      => __('اندازهٔ نشان', 'zig3d-widgets'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range'      => ['px' => ['min' => 24, 'max' => 64]],
                'default'    => ['size' => 40, 'unit' => 'px'],
                'selectors'  => ['{{WRAPPER}} .zig-specs' => '--zig-specs-badge-size: {{SIZE}}{{UNIT}};'],
            ]
        );

        $this->add_responsive_control(
            'chevron_size',
            [
                'label'      => __('اندازهٔ آیکون', 'zig3d-widgets'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range'      => ['px' => ['min' => 10, 'max' => 32]],
                'default'    => ['size' => 16, 'unit' => 'px'],
                'selectors'  => ['{{WRAPPER}} .zig-specs' => '--zig-specs-chevron-size: {{SIZE}}{{UNIT}};'],
            ]
        );

        $this->add_control(
            'badge_radius',
            [
                'label'      => __('شعاعِ گوشه‌ها', 'zig3d-widgets'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range'      => ['px' => ['min' => 0, 'max' => 32]],
                'default'    => ['size' => 10, 'unit' => 'px'],
                'selectors'  => ['{{WRAPPER}} .zig-specs' => '--zig-specs-badge-radius: {{SIZE}}{{UNIT}};'],
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
            'badge_bg',
            [
                'label'     => __('پس‌زمینه', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'default'   => '#F8F8FB',
                'selectors' => ['{{WRAPPER}} .zig-specs' => '--zig-specs-badge-bg: {{VALUE}};'],
            ]
        );

        $this->add_control(
            'group_chevron_icon_color',
            [
                'label'     => __('رنگِ آیکون', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'default'   => '#8B8B99',
                'selectors' => ['{{WRAPPER}} .zig-specs' => '--zig-specs-badge-icon: {{VALUE}};'],
            ]
        );

        $this->add_control(
            'group_chevron_border_color',
            [
                'label'     => __('رنگِ مرز', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'default'   => '#E7E7EE',
                'selectors' => ['{{WRAPPER}} .zig-specs' => '--zig-specs-badge-border: {{VALUE}};'],
            ]
        );

        $this->add_control(
            'badge_border_width',
            [
                'label'      => __('پهنایِ مرز', 'zig3d-widgets'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range'      => ['px' => ['min' => 0, 'max' => 6, 'step' => 0.5]],
                'default'    => ['size' => 1.5, 'unit' => 'px'],
                'selectors'  => ['{{WRAPPER}} .zig-specs' => '--zig-specs-badge-border-width: {{SIZE}}{{UNIT}};'],
            ]
        );

        $this->add_control(
            'toggle_open_heading',
            [
                'label'     => __('حالتِ فعال (گروهِ باز)', 'zig3d-widgets'),
                'type'      => Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        $this->add_control(
            'badge_bg_open',
            [
                'label'     => __('پس‌زمینه', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'default'   => '#F8F8FB',
                'selectors' => ['{{WRAPPER}} .zig-specs' => '--zig-specs-badge-bg-open: {{VALUE}};'],
            ]
        );

        $this->add_control(
            'badge_icon_open',
            [
                'label'     => __('رنگِ آیکون', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'default'   => '#7B5CFF',
                'selectors' => ['{{WRAPPER}} .zig-specs' => '--zig-specs-badge-icon-open: {{VALUE}};'],
            ]
        );

        $this->add_control(
            'badge_border_open',
            [
                'label'     => __('رنگِ مرز', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'default'   => '#7B5CFF',
                'selectors' => ['{{WRAPPER}} .zig-specs' => '--zig-specs-badge-border-open: {{VALUE}};'],
            ]
        );

        $this->end_controls_section();
    }

    private function register_body_style_section(): void {
        $this->start_controls_section(
            'body_style_section',
            [
                'label' => __('بدنه/جدول', 'zig3d-widgets'),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'body_bg',
            [
                'label'     => __('پس‌زمینهٔ بدنه', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'default'   => 'transparent',
                'selectors' => ['{{WRAPPER}} .zig-specs__list' => 'background-color: {{VALUE}};'],
            ]
        );

        $this->add_responsive_control(
            'body_padding',
            [
                'label'      => __('فاصلهٔ داخلیِ بدنه', 'zig3d-widgets'),
                'type'       => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', 'rem'],
                'selectors'  => ['{{WRAPPER}} .zig-specs__list' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
            ]
        );

        $this->add_control(
            'body_divider_note',
            [
                'type'            => Controls_Manager::RAW_HTML,
                'raw'             => __('رنگ/پهنایِ خطِ جداکنندهٔ بینِ سرستون و بدنه، در بخشِ «سرستونِ باز» تنظیم می‌شود — همان یک خط است.', 'zig3d-widgets'),
                'content_classes' => 'elementor-descriptor',
                'separator'       => 'before',
            ]
        );

        $this->end_controls_section();
    }

    private function register_row_style_section(): void {
        $this->start_controls_section(
            'row_style_section',
            [
                'label' => __('ردیف‌ها', 'zig3d-widgets'),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'     => 'label_typography',
                'label'    => __('تایپوگرافیِ برچسب', 'zig3d-widgets'),
                'selector' => '{{WRAPPER}} .zig-specs__label',
            ]
        );

        $this->add_control(
            'label_color',
            [
                'label'     => __('رنگِ برچسب', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => ['{{WRAPPER}} .zig-specs__label' => 'color: {{VALUE}};'],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'      => 'value_typography',
                'label'     => __('تایپوگرافیِ مقدار', 'zig3d-widgets'),
                'selector'  => '{{WRAPPER}} .zig-specs__value',
                'separator' => 'before',
            ]
        );

        $this->add_control(
            'value_color',
            [
                'label'     => __('رنگِ مقدار', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => ['{{WRAPPER}} .zig-specs__value' => 'color: {{VALUE}};'],
            ]
        );

        $this->add_control(
            'row_bg',
            [
                'label'     => __('پس‌زمینهٔ ردیف', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'default'   => '#FFFFFF',
                'separator' => 'before',
                'selectors' => ['{{WRAPPER}} .zig-specs' => '--zig-specs-row-bg: {{VALUE}};'],
            ]
        );

        $this->add_control(
            'row_divider_color',
            [
                'label'     => __('رنگِ خطِ جداکنندهٔ ردیف‌ها', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'default'   => '#ECEEF2',
                'selectors' => ['{{WRAPPER}} .zig-specs' => '--zig-specs-row-divider: {{VALUE}};'],
            ]
        );

        $this->add_control(
            'row_bg_hover',
            [
                'label'     => __('پس‌زمینهٔ ردیف در هاور', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'default'   => '#F7F5FD',
                'selectors' => ['{{WRAPPER}} .zig-specs' => '--zig-specs-row-bg-hover: {{VALUE}};'],
            ]
        );

        $this->add_responsive_control(
            'row_min_height',
            [
                'label'      => __('حداقلِ ارتفاعِ هر ردیف', 'zig3d-widgets'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range'      => ['px' => ['min' => 40, 'max' => 120]],
                'default'    => ['size' => 70, 'unit' => 'px'],
                'selectors'  => ['{{WRAPPER}} .zig-specs__row' => 'min-height: {{SIZE}}{{UNIT}};'],
            ]
        );

        $this->add_responsive_control(
            'row_padding_x',
            [
                'label'      => __('فاصلهٔ داخلیِ افقیِ ردیف', 'zig3d-widgets'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range'      => ['px' => ['min' => 0, 'max' => 60]],
                'default'    => ['size' => 20, 'unit' => 'px'],
                'selectors'  => ['{{WRAPPER}} .zig-specs__row' => 'padding-inline: {{SIZE}}{{UNIT}};'],
            ]
        );

        $this->add_responsive_control(
            'row_padding_y',
            [
                'label'      => __('فاصلهٔ داخلیِ عمودیِ ردیف', 'zig3d-widgets'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range'      => ['px' => ['min' => 0, 'max' => 40]],
                'default'    => ['size' => 0, 'unit' => 'px'],
                'description' => __('علاوه بر حداقلِ ارتفاع اضافه می‌شود؛ برایِ برچسب/مقدارِ چندخطی مفید است.', 'zig3d-widgets'),
                'selectors'  => ['{{WRAPPER}} .zig-specs__row' => 'padding-block: {{SIZE}}{{UNIT}};'],
            ]
        );

        $this->end_controls_section();
    }

    private function register_closed_group_style_section(): void {
        $this->start_controls_section(
            'closed_group_style_section',
            [
                'label'     => __('گروه‌هایِ بسته', 'zig3d-widgets'),
                'tab'       => Controls_Manager::TAB_STYLE,
                'condition' => ['layout_mode' => 'accordion'],
            ]
        );

        $this->add_control(
            'closed_group_note',
            [
                'type'            => Controls_Manager::RAW_HTML,
                'raw'             => __('پس‌زمینه، مرز و شعاعِ گروه‌هایِ بسته از بخشِ «آکاردئون / گروه» می‌آیند — همان کنترل‌ها رویِ کارتِ بسته هم اثر دارند. اینجا فقط خطِ جداکنندهٔ بینِ گروه‌هایِ بستهٔ پشتِ‌سرهم است.', 'zig3d-widgets'),
                'content_classes' => 'elementor-descriptor',
            ]
        );

        $this->add_control(
            'closed_divider_color',
            [
                'label'     => __('رنگِ خطِ جداکنندهٔ بینِ گروه‌هایِ بسته', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'default'   => '#E4E5EA',
                'selectors' => ['{{WRAPPER}} .zig-specs' => '--zig-specs-closed-divider: {{VALUE}};'],
            ]
        );

        $this->end_controls_section();
    }

    /* =====================================================================
     * رندر
     * =================================================================== */

    protected function render(): void {
        $settings = $this->get_settings_for_display();

        if (!function_exists('wc_get_product')) {
            $this->notice(__('این ویجت به ووکامرس فعال نیاز دارد.', 'zig3d-widgets'));

            return;
        }

        $product = Price::resolve(absint($settings['product_id'] ?? 0));

        if (null === $product) {
            $this->notice(__('محصولی پیدا نشد. شناسهٔ محصول را وارد کنید یا ویجت را داخل صفحه/قالب محصول بگذارید.', 'zig3d-widgets'));

            return;
        }

        $term_ids = wc_get_product_terms($product->get_id(), Spec_Store::TAXONOMY, ['fields' => 'ids']);
        $term_ids = is_array($term_ids) ? $term_ids : [];

        $groups = $this->resolved_groups($product, $term_ids);

        if (!$groups) {
            $this->notice($this->empty_reason($term_ids));

            return;
        }

        $accordion = 'flat' !== ($settings['layout_mode'] ?? 'accordion');
        $expand_first = 'yes' === ($settings['expand_first'] ?? 'yes');

        echo '<div class="zig-specs">';

        foreach ($groups as $i => $group) {
            $this->render_group($group, $accordion, $accordion && $expand_first && 0 === $i);
        }

        echo '</div>';
    }

    /**
     * گروه‌هایِ نهایی برایِ این محصول: از همهٔ دسته‌هایِ محصول جمع می‌شود
     * (نه فقط یکی)، هر گروه فقط یک‌بار — اولین جایی که دیده شد — و هر گروه
     * فقط با آیتم‌هایی که رویِ *این* محصول واقعاً مقدار دارند.
     *
     * چرا همهٔ دسته‌ها، نه فقط «دستهٔ اصلی»: ووکامرسِ خالص هیچ مفهومِ
     * «دستهٔ اصلی» ندارد (آن یک قابلیتِ افزونه‌های سئوست)، و محصولی که در
     * چند دسته‌ست معمولاً می‌خواهد مشخصاتِ همهٔ آن دسته‌ها را داشته باشد.
     *
     * @return array<int,array{label:string,rows:array<int,array{label:string,value:string}>}>
     */
    private function resolved_groups(\WC_Product $product, array $term_ids): array {
        $seen   = [];
        $result = [];

        foreach ($term_ids as $term_id) {
            $resolved = Spec_Store::resolve((int) $term_id);

            foreach ($resolved['groups'] as $group) {
                $name = (string) ($group['name'] ?? '');

                if ('' === $name || isset($seen[$name])) {
                    continue;
                }

                $rows = [];

                foreach ((array) ($group['items'] ?? []) as $item) {
                    $row = Spec_Value::resolve($product, (array) $item);

                    if (null !== $row) {
                        $rows[] = $row;
                    }
                }

                // گروهی که هیچ‌کدام از مشخصه‌هایش رویِ این محصول مقدار ندارد، اصلاً نمی‌آید
                if (!$rows) {
                    continue;
                }

                $seen[$name] = true;
                $result[]    = ['label' => (string) ($group['label'] ?? ''), 'rows' => $rows];
            }
        }

        return $result;
    }

    /**
     * پیامِ خالی‌بودن قبلاً یک جمله بود که همیشه یک چیز می‌گفت — «نه دسته
     * گروه دارد، نه مشخصه‌ای مقدار» — یعنی مدیری که هر سه مرحله را انجام
     * داده بود (ویژگیِ محصول را پر کرده، گروه را در کتابخانه ساخته) باز هم
     * همان پیام را می‌دید و نمی‌فهمید کدام مرحله جا افتاده. زنجیره سه حلقه
     * دارد — این تابع می‌گوید دقیقاً کدام حلقه پاره است:
     *
     *   ۱) محصول اصلاً دسته ندارد
     *   ۲) دسته(ها) هست ولی هیچ‌کدام از «گروه‌های مشخصات فنی» را انتخاب نکرده‌اند
     *   ۳) گروه انتخاب شده، ولی هیچ‌کدام از مشخصه‌هایش رویِ *این* محصول مقدار ندارد
     *
     * @param int[] $term_ids
     */
    private function empty_reason(array $term_ids): string {
        if (!$term_ids) {
            return __(
                'این محصول هیچ دسته‌بندی‌ای ندارد — بدونِ دسته، هیچ گروهِ مشخصاتی به آن وصل نمی‌شود. از صفحهٔ ویرایشِ محصول یک دسته انتخاب کنید.',
                'zig3d-widgets'
            );
        }

        foreach ($term_ids as $term_id) {
            if (Spec_Store::category_groups((int) $term_id)) {
                return __(
                    'گروه‌ها به دستهٔ این محصول وصل‌اند، ولی هیچ‌کدام از مشخصه‌هایشان رویِ *این* محصول مقدار ندارد — مثلاً ویژگیِ انتخاب‌شده رویِ این محصول تنظیم نشده، یا وزن/ابعادش خالی است. مقدارها را در برگهٔ «ویژگی‌ها»/«حمل‌ونقل» محصول کامل کنید.',
                    'zig3d-widgets'
                );
            }
        }

        return __(
            'دستهٔ این محصول هنوز هیچ گروهی از «گروه‌های مشخصات فنی» انتخاب نکرده. از صفحهٔ ویرایشِ همان دسته، بخشِ «گروه‌های مشخصاتِ این دسته» را باز کنید و گروه‌های موردنظر را تیک بزنید.',
            'zig3d-widgets'
        );
    }

    /**
     * @param array{label:string,rows:array<int,array{label:string,value:string}>} $group
     */
    private function render_group(array $group, bool $accordion, bool $open): void {
        $tag       = $accordion ? 'details' : 'div';
        $title_tag = $accordion ? 'summary' : 'div';

        printf('<%s class="zig-specs__group"%s>', $tag, $accordion && $open ? ' open' : '');

        printf('<%s class="zig-specs__group-title">', $title_tag);
        printf('<span class="zig-specs__group-title-text">%s</span>', esc_html($group['label']));

        if ($accordion) {
            echo '<span class="zig-specs__chevron-badge">' . Markup::svg_icon('chevron', 'zig-specs__chevron') . '</span>';
        }

        printf('</%s>', $title_tag);

        echo '<dl class="zig-specs__list">';

        foreach ($group['rows'] as $row) {
            echo '<div class="zig-specs__row">';
            printf('<dt class="zig-specs__label">%s</dt>', Markup::text($row['label']));
            printf('<dd class="zig-specs__value">%s</dd>', Markup::text($row['value']));
            echo '</div>';
        }

        echo '</dl>';

        printf('</%s>', $tag);
    }

    private function notice(string $message): void {
        $editing = class_exists('\Elementor\Plugin')
            && isset(\Elementor\Plugin::$instance->editor)
            && \Elementor\Plugin::$instance->editor->is_edit_mode();

        if (!$editing) {
            return;
        }

        printf('<div class="zig-specs__notice">%s</div>', esc_html($message));
    }
}
