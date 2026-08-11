<?php
namespace Zig3d_Widgets\Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;
use Zig3d_Widgets\Gallery;
use Zig3d_Widgets\Markup;
use Zig3d_Widgets\Plugin;
use Zig3d_Widgets\Price;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * گالری تصاویر محصول.
 *
 * ساختار خروجی — شش ناحیه، دقیقاً به همان سبکِ کارتِ آرشیو:
 *
 *     figure.zig-gallery                        ۱ کل گالری
 *       div.zig-gallery__stage                  ۲ صحنه
 *         div.zig-gallery__frames               ظرفِ اسکرول (snap)
 *           div.zig-gallery__frame              ۳ هر تصویر
 *             img.zig-gallery__image
 *         button.zig-gallery__nav--prev         ۴ دکمه‌های حرکت
 *         button.zig-gallery__nav--next
 *         p.zig-gallery__counter                ۵ شمارنده
 *       ul.zig-gallery__thumbs                  ۶ بندانگشتی‌ها
 *         li.zig-gallery__thumb > a
 *
 * سه تفاوت با گالریِ قالب الماس‌آرا، و هر سه عمدی:
 *
 * ۱. یک فهرستِ واحد. آنجا تصویر شاخص در بخش اصلی بود و بندانگشتی‌ها از
 *    گالری می‌آمدند؛ یعنی شاخص بندانگشتی نداشت و بندانگشتیِ اول چیزی جز
 *    آنچه در بخش اصلی بود باز می‌کرد. توضیح کاملش در ‎Zig3d_Widgets\Gallery‎.
 *
 * ۲. حرکت روی خودِ صحنه است، نه در یک مودالِ تمام‌صفحه. طرح، فلش و شمارنده
 *    را روی همان کادر می‌خواهد؛ مودال یک لایهٔ دیگر است که — اگر لازم شد —
 *    روی همین ساختار سوار می‌شود، نه جایگزینش.
 *
 * ۳. بدون جاوااسکریپت هم گالری است، نه یک تصویر تنها. ظرفِ فریم‌ها یک
 *    ناحیهٔ ‎scroll-snap‎ افقی است و بندانگشتی‌ها ‎<a href="#...">‎ واقعی‌اند:
 *    لمس و کشیدن، و کلیک روی بندانگشتی، هر دو بدون یک بایت JS کار می‌کنند.
 *
 * و به همین دلیل، دو کنترلی که *بدون* JS کار نمی‌کنند — فلش‌ها و شمارنده —
 * تا وقتی اسکریپت بالا نیامده اصلاً دیده نمی‌شوند (کلاس ‎is-ready‎ روی
 * ریشه). فلشی که کلیک می‌شود و کاری نمی‌کند، و شمارنده‌ای که بعد از یک
 * کشیدنِ انگشت عدد اشتباه نشان می‌دهد، هر دو بدتر از نبودنشان‌اند.
 */
final class Product_Gallery extends Widget_Base {

    public function get_name(): string {
        return 'zig3d-product-gallery';
    }

    public function get_title(): string {
        return __('گالری محصول', 'zig3d-widgets');
    }

    public function get_icon(): string {
        return 'eicon-product-images';
    }

    public function get_categories(): array {
        return [Plugin::CATEGORY];
    }

    public function get_keywords(): array {
        return ['gallery', 'images', 'slider', 'carousel', 'گالری', 'تصاویر', 'اسلایدر'];
    }

    public function get_style_depends(): array {
        return ['zig3d-widgets'];
    }

    public function get_script_depends(): array {
        return ['zig3d-gallery'];
    }

    public function has_widget_inner_wrapper(): bool {
        return false;
    }

    protected function register_controls(): void {
        $this->section_product();
        $this->section_behaviour();
        $this->section_layout();
        $this->section_style_stage();
        $this->section_style_nav();
        $this->section_style_counter();
        $this->section_style_thumbs();
    }

    /* =====================================================================
     * محتوا: محصول
     * =================================================================== */

    private function section_product(): void {
        $this->start_controls_section('product', ['label' => __('محصول', 'zig3d-widgets')]);

        $this->add_control('product_id', [
            'label'       => __('شناسهٔ محصول', 'zig3d-widgets'),
            'type'        => Controls_Manager::NUMBER,
            'min'         => 0,
            'dynamic'     => ['active' => true],
            'description' => __('خالی بگذارید تا محصول جاری استفاده شود — چه در صفحهٔ محصول، چه داخل قالب تکِ المنتور.', 'zig3d-widgets'),
        ]);

        $this->add_control('with_featured', [
            'label'        => __('تصویر شاخص هم یک اسلاید باشد', 'zig3d-widgets'),
            'type'         => Controls_Manager::SWITCHER,
            'default'      => 'yes',
            'return_value' => 'yes',
            'description'  => __('روشن یعنی شاخص اسلاید اول است و بندانگشتیِ خودش را دارد. خاموش فقط وقتی معنا دارد که شاخص عمداً یک تصویرِ تبلیغاتیِ جدا باشد.', 'zig3d-widgets'),
        ]);

        $this->add_control('max', [
            'label'       => __('حداکثر تعداد تصویر', 'zig3d-widgets'),
            'type'        => Controls_Manager::NUMBER,
            'min'         => 0,
            'default'     => 0,
            'description' => __('‎۰‎ یعنی بی‌سقف.', 'zig3d-widgets'),
        ]);

        $this->add_control('image_size', [
            'label'   => __('اندازهٔ تصویر صحنه', 'zig3d-widgets'),
            'type'    => Controls_Manager::SELECT,
            'default' => 'woocommerce_single',
            'options' => $this->size_options(),
        ]);

        $this->add_control('thumb_size', [
            'label'   => __('اندازهٔ بندانگشتی', 'zig3d-widgets'),
            'type'    => Controls_Manager::SELECT,
            'default' => 'woocommerce_thumbnail',
            'options' => $this->size_options(),
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * محتوا: رفتار
     * =================================================================== */

    private function section_behaviour(): void {
        $this->start_controls_section('behaviour', ['label' => __('رفتار', 'zig3d-widgets')]);

        $this->add_control('show_nav', [
            'label'        => __('دکمه‌های قبلی/بعدی', 'zig3d-widgets'),
            'type'         => Controls_Manager::SWITCHER,
            'default'      => 'yes',
            'return_value' => 'yes',
        ]);

        $this->add_control('loop', [
            'label'        => __('چرخشی', 'zig3d-widgets'),
            'type'         => Controls_Manager::SWITCHER,
            'default'      => 'yes',
            'return_value' => 'yes',
            'description'  => __('روشن: بعدیِ آخرین تصویر، اولی است. خاموش: دکمه در دو سرِ فهرست غیرفعال می‌شود.', 'zig3d-widgets'),
            'condition'    => ['show_nav' => 'yes'],
        ]);

        $this->add_control('show_counter', [
            'label'        => __('شمارنده', 'zig3d-widgets'),
            'type'         => Controls_Manager::SWITCHER,
            'default'      => 'yes',
            'return_value' => 'yes',
        ]);

        $this->add_control('persian_digits', [
            'label'        => __('ارقام فارسی', 'zig3d-widgets'),
            'type'         => Controls_Manager::SWITCHER,
            'default'      => 'yes',
            'return_value' => 'yes',
            'condition'    => ['show_counter' => 'yes'],
        ]);

        $this->add_control('show_thumbs', [
            'label'        => __('نوار بندانگشتی', 'zig3d-widgets'),
            'type'         => Controls_Manager::SWITCHER,
            'default'      => 'yes',
            'return_value' => 'yes',
        ]);

        $this->add_control('single_note', [
            'type'            => Controls_Manager::RAW_HTML,
            'raw'             => __('محصولی که فقط یک تصویر دارد، خودبه‌خود بدون فلش و شمارنده و نوار بندانگشتی رندر می‌شود — این تنظیم‌ها لازم نیست برای آن خاموش شوند.', 'zig3d-widgets'),
            'content_classes' => 'elementor-descriptor',
            'separator'       => 'before',
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * چیدمان: شش ناحیه
     *
     * یک فهرست و یک حلقه — همان الگوی ‎card_areas()‎ در آرشیو. اضافه‌شدن
     * ناحیهٔ هفتم یک سطر است، و ناحیه‌ای که یکی از کنترل‌هایش جا افتاده
     * باشد ممکن نیست.
     * =================================================================== */

    private function section_layout(): void {
        $this->start_controls_section('layout', [
            'label' => __('ساختار گالری', 'zig3d-widgets'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        foreach ($this->areas() as $key => $area) {
            $this->add_control($key . '_heading', [
                'label'     => $area['label'],
                'type'      => Controls_Manager::HEADING,
                'separator' => 'before',
            ]);

            $this->add_responsive_control($key . '_padding', [
                'label'      => __('فاصلهٔ درونی', 'zig3d-widgets'),
                'type'       => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'rem', '%'],
                'selectors'  => [
                    '{{WRAPPER}} .zig-gallery' => '--zig-gal-' . $area['var'] . '-padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]);

            if (!empty($area['gap'])) {
                $this->add_responsive_control($key . '_gap', [
                    'label'      => __('فاصلهٔ داخلی اجزا', 'zig3d-widgets'),
                    'type'       => Controls_Manager::SLIDER,
                    'size_units' => ['px', 'rem'],
                    'range'      => ['px' => ['min' => 0, 'max' => 60]],
                    'selectors'  => [
                        '{{WRAPPER}} .zig-gallery' => '--zig-gal-' . $area['var'] . '-gap: {{SIZE}}{{UNIT}};',
                    ],
                ]);
            }

            if (!empty($area['order'])) {
                $this->add_control($key . '_order', [
                    'label'     => __('ترتیب نمایش', 'zig3d-widgets'),
                    'type'      => Controls_Manager::NUMBER,
                    'min'       => 1,
                    'max'       => 9,
                    'selectors' => [
                        '{{WRAPPER}} .zig-gallery' => '--zig-gal-' . $area['var'] . '-order: {{VALUE}};',
                    ],
                ]);
            }
        }

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل: صحنه
     * =================================================================== */

    private function section_style_stage(): void {
        $this->start_controls_section('sty_stage', [
            'label' => __('صحنه', 'zig3d-widgets'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_responsive_control('stage_ratio', [
            'label'       => __('نسبت ابعاد', 'zig3d-widgets'),
            'type'        => Controls_Manager::SLIDER,
            'range'       => ['px' => ['min' => 0.4, 'max' => 2.5, 'step' => 0.01]],
            'default'     => ['size' => 1],
            'description' => __('عرض تقسیم بر ارتفاع. ‎۱‎ یعنی مربع — همان چیزی که طرح می‌خواهد.', 'zig3d-widgets'),
            'selectors'   => ['{{WRAPPER}} .zig-gallery' => '--zig-gal-ratio: {{SIZE}};'],
        ]);

        $this->add_control('stage_bg', [
            'label'     => __('پس‌زمینهٔ کادر', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .zig-gallery' => '--zig-gal-stage-bg: {{VALUE}};'],
        ]);

        $this->add_responsive_control('stage_radius', [
            'label'      => __('گِردی گوشه', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', 'rem', '%'],
            'range'      => ['px' => ['min' => 0, 'max' => 60]],
            'selectors'  => ['{{WRAPPER}} .zig-gallery' => '--zig-gal-stage-radius: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_control('image_fit', [
            'label'       => __('برازش تصویر', 'zig3d-widgets'),
            'type'        => Controls_Manager::SELECT,
            'default'     => 'contain',
            'options'     => [
                'contain' => __('کامل دیده شود', 'zig3d-widgets'),
                'cover'   => __('کادر را پر کند', 'zig3d-widgets'),
            ],
            'description' => __('«کامل دیده شود» پیش‌فرض است: بریدنِ عکسِ یک دستگاه صنعتی می‌تواند دقیقاً همان بخشی را حذف کند که مشتری دنبالش است.', 'zig3d-widgets'),
            'selectors'   => ['{{WRAPPER}} .zig-gallery' => '--zig-gal-image-fit: {{VALUE}};'],
        ]);

        $this->add_group_control(Group_Control_Box_Shadow::get_type(), [
            'name'     => 'stage_shadow',
            'selector' => '{{WRAPPER}} .zig-gallery__stage',
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل: دکمه‌های حرکت
     * =================================================================== */

    private function section_style_nav(): void {
        $this->start_controls_section('sty_nav', [
            'label'     => __('دکمه‌های حرکت', 'zig3d-widgets'),
            'tab'       => Controls_Manager::TAB_STYLE,
            'condition' => ['show_nav' => 'yes'],
        ]);

        $this->add_responsive_control('nav_size', [
            'label'      => __('قطر دکمه', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 24, 'max' => 80]],
            'selectors'  => ['{{WRAPPER}} .zig-gallery' => '--zig-gal-nav-size: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_responsive_control('nav_icon_size', [
            'label'      => __('اندازهٔ آیکون', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 10, 'max' => 40]],
            'selectors'  => ['{{WRAPPER}} .zig-gallery' => '--zig-gal-nav-icon: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_responsive_control('nav_inset', [
            'label'      => __('فاصله از لبهٔ کادر', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', 'rem'],
            'range'      => ['px' => ['min' => 0, 'max' => 60]],
            'selectors'  => ['{{WRAPPER}} .zig-gallery' => '--zig-gal-nav-inset: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_responsive_control('nav_radius', [
            'label'      => __('گِردی گوشه', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', '%'],
            'range'      => ['%' => ['min' => 0, 'max' => 50]],
            'selectors'  => ['{{WRAPPER}} .zig-gallery' => '--zig-gal-nav-radius: {{SIZE}}{{UNIT}};'],
        ]);

        $this->start_controls_tabs('nav_tabs');

        $this->start_controls_tab('nav_normal', ['label' => __('عادی', 'zig3d-widgets')]);
        $this->add_nav_colors('');
        $this->end_controls_tab();

        $this->start_controls_tab('nav_hover', ['label' => __('هاور', 'zig3d-widgets')]);
        $this->add_nav_colors('hover');
        $this->end_controls_tab();

        /*
         * تبِ سوم فقط وقتی معنا دارد که چرخش خاموش باشد — با چرخش، دکمه
         * هیچ‌وقت غیرفعال نمی‌شود و این تب یک بن‌بست است.
         */
        $this->start_controls_tab('nav_disabled', [
            'label'     => __('غیرفعال', 'zig3d-widgets'),
            'condition' => ['loop!' => 'yes'],
        ]);
        $this->add_nav_colors('off');
        $this->add_control('nav_off_opacity', [
            'label'     => __('شفافیت', 'zig3d-widgets'),
            'type'      => Controls_Manager::SLIDER,
            'range'     => ['px' => ['min' => 0, 'max' => 1, 'step' => 0.05]],
            'selectors' => ['{{WRAPPER}} .zig-gallery' => '--zig-gal-nav-off-opacity: {{SIZE}};'],
        ]);
        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->add_group_control(Group_Control_Box_Shadow::get_type(), [
            'name'      => 'nav_shadow',
            'selector'  => '{{WRAPPER}} .zig-gallery__nav',
            'separator' => 'before',
        ]);

        $this->end_controls_section();
    }

    /**
     * سه رنگِ یک حالتِ دکمه؛ ‎$state‎ خالی یعنی حالت عادی.
     *
     * نامِ کنترل با ‎_‎ ساخته می‌شود و نامِ متغیر با ‎-‎ — دو قرارداد
     * جدا که در یک تابع به هم می‌رسند. اگر یک رشتهٔ مشترک برای هر دو
     * به‌کار می‌رفت، یکی از دو طرف قرارداد خودش را می‌شکست.
     */
    private function add_nav_colors(string $state): void {
        $name = '' === $state ? '' : $state . '_';
        $var  = '' === $state ? '' : $state . '-';

        $colors = [
            'bg'     => __('پس‌زمینه', 'zig3d-widgets'),
            'color'  => __('رنگ آیکون', 'zig3d-widgets'),
            'border' => __('رنگ حاشیه', 'zig3d-widgets'),
        ];

        foreach ($colors as $key => $label) {
            $this->add_control('nav_' . $name . $key, [
                'label'     => $label,
                'type'      => Controls_Manager::COLOR,
                'selectors' => ['{{WRAPPER}} .zig-gallery' => '--zig-gal-nav-' . $var . $key . ': {{VALUE}};'],
            ]);
        }
    }

    /* =====================================================================
     * استایل: شمارنده
     * =================================================================== */

    private function section_style_counter(): void {
        $this->start_controls_section('sty_counter', [
            'label'     => __('شمارنده', 'zig3d-widgets'),
            'tab'       => Controls_Manager::TAB_STYLE,
            'condition' => ['show_counter' => 'yes'],
        ]);

        $this->add_control('counter_bg', [
            'label'     => __('پس‌زمینه', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .zig-gallery' => '--zig-gal-counter-bg: {{VALUE}};'],
        ]);

        $this->add_control('counter_color', [
            'label'     => __('رنگ متن', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .zig-gallery' => '--zig-gal-counter-color: {{VALUE}};'],
        ]);

        $this->add_control('counter_sep_color', [
            'label'       => __('رنگ خطِ جداکننده', 'zig3d-widgets'),
            'type'        => Controls_Manager::COLOR,
            'description' => __('رنگِ ‎/‎ و عددِ کل. پیش‌فرض کم‌رنگ‌تر از عددِ جاری است تا شمارهٔ فعلی برجسته بماند.', 'zig3d-widgets'),
            'selectors'   => ['{{WRAPPER}} .zig-gallery' => '--zig-gal-counter-dim: {{VALUE}};'],
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'     => 'counter_typography',
            'selector' => '{{WRAPPER}} .zig-gallery__counter',
        ]);

        $this->add_responsive_control('counter_padding', [
            'label'      => __('فاصلهٔ درونی', 'zig3d-widgets'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', 'rem'],
            'selectors'  => ['{{WRAPPER}} .zig-gallery' => '--zig-gal-counter-padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
        ]);

        $this->add_responsive_control('counter_gap', [
            'label'      => __('فاصلهٔ عدد تا جداکننده', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', 'rem'],
            'range'      => ['px' => ['min' => 0, 'max' => 24]],
            'selectors'  => ['{{WRAPPER}} .zig-gallery' => '--zig-gal-counter-gap: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_responsive_control('counter_radius', [
            'label'      => __('گِردی گوشه', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', 'rem'],
            'range'      => ['px' => ['min' => 0, 'max' => 40]],
            'selectors'  => ['{{WRAPPER}} .zig-gallery' => '--zig-gal-counter-radius: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_responsive_control('counter_offset', [
            'label'      => __('فاصله از کف کادر', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', 'rem'],
            'range'      => ['px' => ['min' => 0, 'max' => 80]],
            'selectors'  => ['{{WRAPPER}} .zig-gallery' => '--zig-gal-counter-offset: {{SIZE}}{{UNIT}};'],
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل: بندانگشتی‌ها
     * =================================================================== */

    private function section_style_thumbs(): void {
        $this->start_controls_section('sty_thumbs', [
            'label'     => __('بندانگشتی‌ها', 'zig3d-widgets'),
            'tab'       => Controls_Manager::TAB_STYLE,
            'condition' => ['show_thumbs' => 'yes'],
        ]);

        $this->add_control('thumbs_fill', [
            'label'        => __('پرکردن عرض نوار', 'zig3d-widgets'),
            'type'         => Controls_Manager::SWITCHER,
            'default'      => 'yes',
            'return_value' => 'yes',
            'description'  => __('روشن: بندانگشتی‌ها عرض نوار را بین خودشان تقسیم می‌کنند، پس چهار تصویر ردیف را پر می‌کنند. خاموش: هرکدام دقیقاً به اندازهٔ زیر می‌مانند و ردیف از یک سمت پر می‌شود.', 'zig3d-widgets'),
        ]);

        $this->add_responsive_control('thumb_size_px', [
            'label'       => __('اندازه', 'zig3d-widgets'),
            'type'        => Controls_Manager::SLIDER,
            'size_units'  => ['px', 'rem'],
            'range'       => ['px' => ['min' => 40, 'max' => 200]],
            'description' => __('در حالت پرکننده این عدد «کمینه» است: تا وقتی جا هست بندانگشتی‌ها بزرگ‌تر می‌شوند، و از این اندازه که کوچک‌تر شدند نوار به اسکرول می‌افتد. در غیر این صورت، اندازهٔ دقیق.', 'zig3d-widgets'),
            'selectors'   => ['{{WRAPPER}} .zig-gallery' => '--zig-gal-thumb-size: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_responsive_control('thumb_radius', [
            'label'      => __('گِردی گوشه', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', 'rem', '%'],
            'range'      => ['px' => ['min' => 0, 'max' => 40]],
            'selectors'  => ['{{WRAPPER}} .zig-gallery' => '--zig-gal-thumb-radius: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_responsive_control('thumb_ring', [
            'label'      => __('ضخامت قاب', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 8]],
            'selectors'  => ['{{WRAPPER}} .zig-gallery' => '--zig-gal-thumb-ring: {{SIZE}}{{UNIT}};'],
        ]);

        /*
         * قابِ فعال ‎outline‎ است نه ‎border‎، و فاصله‌اش ‎outline-offset‎:
         * این‌طور فعال‌شدنِ یک بندانگشتی اندازهٔ جعبه‌اش را عوض نمی‌کند و
         * کل نوار یک پیکسل تکان نمی‌خورد.
         */
        $this->add_responsive_control('thumb_ring_gap', [
            'label'      => __('فاصلهٔ قاب تا تصویر', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 12]],
            'selectors'  => ['{{WRAPPER}} .zig-gallery' => '--zig-gal-thumb-ring-gap: {{SIZE}}{{UNIT}};'],
        ]);

        $this->start_controls_tabs('thumb_tabs');

        $this->start_controls_tab('thumb_normal', ['label' => __('عادی', 'zig3d-widgets')]);
        $this->add_thumb_colors('');
        $this->end_controls_tab();

        $this->start_controls_tab('thumb_hover', ['label' => __('هاور', 'zig3d-widgets')]);
        $this->add_thumb_colors('hover');
        $this->end_controls_tab();

        $this->start_controls_tab('thumb_active', ['label' => __('فعال', 'zig3d-widgets')]);
        $this->add_thumb_colors('on');
        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->add_group_control(Group_Control_Border::get_type(), [
            'name'      => 'thumb_border',
            'selector'  => '{{WRAPPER}} .zig-gallery__thumb-link',
            'separator' => 'before',
        ]);

        $this->end_controls_section();
    }

    /** رنگ‌های یک حالتِ بندانگشتی؛ ‎$state‎ خالی یعنی حالت عادی */
    private function add_thumb_colors(string $state): void {
        $name = '' === $state ? '' : $state . '_';
        $var  = '' === $state ? '' : $state . '-';

        $this->add_control('thumb_' . $name . 'ring_color', [
            'label'     => __('رنگ قاب', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .zig-gallery' => '--zig-gal-thumb-' . $var . 'ring-color: {{VALUE}};'],
        ]);

        $this->add_control('thumb_' . $name . 'bg', [
            'label'     => __('پس‌زمینه', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .zig-gallery' => '--zig-gal-thumb-' . $var . 'bg: {{VALUE}};'],
        ]);

        $this->add_control('thumb_' . $name . 'opacity', [
            'label'     => __('شفافیت تصویر', 'zig3d-widgets'),
            'type'      => Controls_Manager::SLIDER,
            'range'     => ['px' => ['min' => 0, 'max' => 1, 'step' => 0.05]],
            'selectors' => ['{{WRAPPER}} .zig-gallery' => '--zig-gal-thumb-' . $var . 'opacity: {{SIZE}};'],
        ]);
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

        $data = Gallery::data($product, [
            'featured' => 'yes' === ($settings['with_featured'] ?? 'yes'),
            'max'      => (int) ($settings['max'] ?? 0),
        ]);

        if ($data['placeholder']) {
            $this->notice(__('این محصول هیچ تصویری ندارد.', 'zig3d-widgets'));

            return;
        }

        $this->render_gallery($data, $settings);
    }

    private function render_gallery(array $data, array $settings): void {
        $slides = $data['slides'];
        $total  = count($slides);

        /*
         * تکِ تصویر یعنی چیزی برای حرکت‌کردن نیست. سه بخشِ حرکتی حذف
         * می‌شوند — نه با CSS، که همچنان در DOM و در دسترسِ صفحه‌خوان
         * می‌ماندند، بلکه اصلاً چاپ نمی‌شوند.
         */
        $many = $total > 1;

        $base = 'zig-gal-' . $this->get_id();

        $classes = ['zig-gallery'];

        if ('yes' === ($settings['thumbs_fill'] ?? 'yes')) {
            $classes[] = 'zig-gallery--fill';
        }

        printf(
            '<figure class="%s" data-zig-gallery data-zig-loop="%s">',
            esc_attr(implode(' ', $classes)),
            esc_attr($many && 'yes' === ($settings['loop'] ?? 'yes') ? '1' : '0')
        );

        $this->render_stage($data, $settings, $base, $many);

        if ($many && 'yes' === ($settings['show_thumbs'] ?? 'yes')) {
            $this->render_thumbs($data, $settings, $base);
        }

        echo '</figure>';
    }

    /**
     * صحنه: ظرفِ اسکرولِ فریم‌ها، به‌علاوهٔ فلش‌ها و شمارنده.
     *
     * ‎tabindex="0"‎ روی ظرف عمدی است: یک ناحیهٔ اسکرول‌شونده باید با
     * صفحه‌کلید هم قابل پیمایش باشد، وگرنه کاربری که ماوس ندارد و JS هم
     * برایش بالا نیامده هیچ راهی به تصویر دوم ندارد.
     */
    private function render_stage(array $data, array $settings, string $base, bool $many): void {
        echo '<div class="zig-gallery__stage">';

        printf(
            '<div class="zig-gallery__frames" id="%s-frames" tabindex="0" role="group" aria-label="%s">',
            esc_attr($base),
            esc_attr__('تصاویر محصول', 'zig3d-widgets')
        );

        $size = (string) ($settings['image_size'] ?? 'woocommerce_single');

        foreach (array_values($data['slides']) as $index => $id) {
            printf(
                '<div class="zig-gallery__frame" id="%1$s-%2$d" data-zig-index="%2$d">',
                esc_attr($base),
                $index
            );

            $attr = [
                'class'    => 'zig-gallery__image',
                /*
                 * فقط اسلاید اول ‎eager‎ است. بقیه ‎lazy‎ می‌مانند چون در
                 * همان ابتدا بیرون از دیدند — ولی اولی اگر ‎lazy‎ باشد،
                 * بزرگ‌ترین عنصرِ صفحه دیرتر می‌آید و LCP را می‌سوزاند.
                 * ‎fetchpriority‎ همان تصمیم را یک قدم جلوتر می‌برد: به
                 * مرورگر می‌گوید حتی در صفِ دانلود هم این یکی را زودتر
                 * بگیرد، نه فقط دیرتر ‎lazy‎نکردنش.
                 */
                'loading'  => 0 === $index ? 'eager' : 'lazy',
                'decoding' => 'async',
                'alt'      => $data['title'],
            ];

            if (0 === $index) {
                $attr['fetchpriority'] = 'high';
            }

            echo wp_get_attachment_image($id, $size, false, $attr); // phpcs:ignore WordPress.Security.EscapeOutput -- خروجی خودِ وردپرس

            echo '</div>';
        }

        echo '</div>';

        if ($many && 'yes' === ($settings['show_nav'] ?? 'yes')) {
            $this->render_nav();
        }

        if ($many && 'yes' === ($settings['show_counter'] ?? 'yes')) {
            $this->render_counter(count($data['slides']), $settings);
        }

        echo '</div>';
    }

    /**
     * فلش‌ها.
     *
     * ‎<button type="button">‎ و نه ‎<a href="#...">‎: مقصدِ «بعدی» با هر
     * حرکت عوض می‌شود و یک ‎href‎ ثابت فقط یک بار درست است. لینکی که بار
     * دوم جای اشتباه می‌رود، از نبودنش بدتر است — و به همین دلیل تا وقتی
     * JS بالا نیامده، CSS این دو را نشان نمی‌دهد.
     */
    private function render_nav(): void {
        $buttons = [
            'prev' => __('تصویر قبلی', 'zig3d-widgets'),
            'next' => __('تصویر بعدی', 'zig3d-widgets'),
        ];

        foreach ($buttons as $key => $label) {
            printf(
                '<button type="button" class="zig-gallery__nav zig-gallery__nav--%1$s"'
                    . ' data-zig-step="%2$d" aria-label="%3$s">%4$s</button>',
                esc_attr($key),
                'prev' === $key ? -1 : 1,
                esc_attr($label),
                Markup::svg_icon('arrow', 'zig-gallery__nav-icon') // phpcs:ignore WordPress.Security.EscapeOutput -- SVG ثابت
            );
        }
    }

    /**
     * شمارنده.
     *
     * ‎aria-hidden‎ است و در عوض هر فریم برچسبِ خودش را دارد: صفحه‌خوان
     * «۲ / ۴» را به‌عنوان یک تکه متنِ شناور می‌خواند که به هیچ چیزی وصل
     * نیست، در حالی که همان اطلاعات از ‎aria-label‎ فریم درست‌تر می‌رسد.
     */
    private function render_counter(int $total, array $settings): void {
        $persian = 'yes' === ($settings['persian_digits'] ?? 'yes');

        printf(
            '<p class="zig-gallery__counter" aria-hidden="true">'
                . '<span class="zig-gallery__counter-now" data-zig-counter>%s</span>'
                . '<span class="zig-gallery__counter-sep">/</span>'
                . '<span class="zig-gallery__counter-all">%s</span></p>',
            esc_html(Gallery::label(0, $persian)),
            esc_html(Gallery::label($total - 1, $persian))
        );
    }

    /**
     * نوار بندانگشتی.
     *
     * ‎<a href="#frame">‎ واقعی، نه ‎<button>‎: بدون JS همین لینک‌ها ظرفِ
     * اسکرول را روی فریمِ مقصد می‌برند و گالری کار می‌کند. JS فقط جلوی
     * پرشِ صفحه را می‌گیرد و ‎aria-current‎ را جابه‌جا می‌کند.
     */
    private function render_thumbs(array $data, array $settings, string $base): void {
        $size    = (string) ($settings['thumb_size'] ?? 'woocommerce_thumbnail');
        $persian = 'yes' === ($settings['persian_digits'] ?? 'yes');

        printf(
            '<ul class="zig-gallery__thumbs" role="list" aria-label="%s">',
            esc_attr__('انتخاب تصویر', 'zig3d-widgets')
        );

        foreach (array_values($data['slides']) as $index => $id) {
            printf(
                '<li class="zig-gallery__thumb"><a class="zig-gallery__thumb-link" href="#%1$s-%2$d"'
                    . ' data-zig-goto="%2$d"%3$s aria-label="%4$s">',
                esc_attr($base),
                $index,
                0 === $index ? ' aria-current="true"' : '',
                esc_attr(sprintf(
                    /* translators: %s: شمارهٔ تصویر */
                    __('تصویر %s', 'zig3d-widgets'),
                    Gallery::label($index, $persian)
                ))
            );

            echo wp_get_attachment_image( // phpcs:ignore WordPress.Security.EscapeOutput -- خروجی خودِ وردپرس
                $id,
                $size,
                false,
                [
                    'class'    => 'zig-gallery__thumb-image',
                    'loading'  => 'lazy',
                    'decoding' => 'async',
                    'alt'      => '',
                ]
            );

            echo '</a></li>';
        }

        echo '</ul>';
    }

    /* =====================================================================
     * کمکی‌ها
     * =================================================================== */

    /**
     * اندازه‌های ثبت‌شدهٔ تصویر.
     *
     * از خودِ وردپرس خوانده می‌شود نه یک فهرست ثابت: قالب و افزونه‌ها
     * اندازه‌های خودشان را ثبت می‌کنند و یک فهرست دستی، همان‌ها را از
     * دسترس بیرون می‌گذاشت.
     */
    private function size_options(): array {
        $options = ['full' => __('اصلی (full)', 'zig3d-widgets')];

        foreach ((array) get_intermediate_image_sizes() as $size) {
            $options[$size] = $size;
        }

        return $options;
    }

    /**
     * ناحیه‌های گالری، برای ساختن کنترل‌ها.
     *
     * فقط چهارتا، و دو غایبش عمدی‌اند:
     *
     *   • فلش‌ها ناحیه نیستند. آن‌ها دو دایرهٔ شناور روی صحنه‌اند؛ «فاصلهٔ
     *     درونی»شان هیچ معنایی ندارد و آنچه واقعاً تنظیم می‌شود — فاصله از
     *     لبهٔ کادر — کنترل خودش را در بخش «دکمه‌های حرکت» دارد.
     *
     *   • شمارنده هم همین‌طور: پدینگ و گِردی و فاصله‌اش در بخش «شمارنده»
     *     است. اگر اینجا هم می‌آمد، دو کنترل یک متغیر را می‌نوشتند و
     *     کدامشان برنده است به ترتیب ثبت بستگی داشت — یعنی مدیر یکی را
     *     عوض می‌کرد و هیچ اتفاقی نمی‌افتاد.
     */
    private function areas(): array {
        return [
            /*
             * «کل گالری» ترتیب ندارد — تنها فرزندِ ریشه است و ‎order‎ روی
             * عنصر تنها هیچ کاری نمی‌کند.
             */
            'area_root'   => ['label' => __('۱ کل گالری', 'zig3d-widgets'), 'var' => 'root', 'gap' => true, 'order' => false],
            'area_stage'  => ['label' => __('۲ صحنه', 'zig3d-widgets'), 'var' => 'stage', 'gap' => false, 'order' => true],
            'area_frame'  => ['label' => __('۳ کادر هر تصویر', 'zig3d-widgets'), 'var' => 'frame', 'gap' => false, 'order' => false],
            'area_thumbs' => ['label' => __('۴ بندانگشتی‌ها', 'zig3d-widgets'), 'var' => 'thumbs', 'gap' => true, 'order' => true],
        ];
    }

    /** پیام راهنما، فقط داخل ادیتور */
    private function notice(string $message): void {
        $editing = class_exists('\Elementor\Plugin')
            && isset(\Elementor\Plugin::$instance->editor)
            && \Elementor\Plugin::$instance->editor->is_edit_mode();

        if (!$editing) {
            return;
        }

        printf('<div class="zig-price__notice">%s</div>', esc_html($message));
    }
}
