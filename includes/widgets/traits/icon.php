<?php
namespace Zig3d_Widgets\Widgets\Traits;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Background;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
use Zig3d_Widgets\Selector;
use Zig3d_Widgets\Svg;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * آیکون با چهار منبع مختلف، به‌علاوهٔ همهٔ استایل‌های جعبهٔ آیکون.
 *
 * چرا چهار منبع و نه فقط کتابخانهٔ آیکون:
 *
 *   • «کتابخانه» برای آیکون‌های استاندارد، که مزیتش رنگ‌پذیری با currentColor است.
 *   • «تصویر/SVG» برای آیکون‌های اختصاصی برند. SVG به‌صورت inline و
 *     پاک‌سازی‌شده درج می‌شود تا با CSS رنگ بگیرد؛ بقیهٔ فرمت‌ها با
 *     wp_get_attachment_image می‌آیند تا srcset و ابعاد و lazy-loading را
 *     خودِ وردپرس بسازد و صفحه CLS نگیرد.
 *   • «متن» برای نشانه‌های ترتیبی مثل «الف» یا «۰۱» — همان چیزی که در طراحی
 *     زیگ روی کارت‌ها دیده می‌شود. بدون این، کاربر مجبور بود برای هر حرف یک
 *     تصویر بسازد.
 *   • «بدون آیکون» تا خودِ ویجت بدون آیکون هم کامل باشد.
 */
trait Icon {

    /* =====================================================================
     * کنترل‌های محتوا
     * =================================================================== */

    /**
     * @param string $prefix پیشوند نام کنترل‌ها؛ اگر ویجتی بیش از یک آیکون داشته باشد یکتاش می‌کند.
     */
    protected function add_icon_content_controls(string $prefix = 'icon'): void {
        $this->add_control(
            $prefix . '_source',
            [
                'label'   => __('نوع آیکون', 'zig3d-widgets'),
                'type'    => Controls_Manager::CHOOSE,
                'default' => 'icon',
                'options' => [
                    'none'  => ['title' => __('بدون آیکون', 'zig3d-widgets'), 'icon' => 'eicon-ban'],
                    'icon'  => ['title' => __('کتابخانه', 'zig3d-widgets'), 'icon' => 'eicon-star'],
                    'image' => ['title' => __('تصویر / SVG', 'zig3d-widgets'), 'icon' => 'eicon-image'],
                    'text'  => ['title' => __('متن', 'zig3d-widgets'), 'icon' => 'eicon-t-letter'],
                ],
                'toggle'  => false,
            ]
        );

        $this->add_control(
            $prefix,
            [
                'label'     => __('آیکون', 'zig3d-widgets'),
                'type'      => Controls_Manager::ICONS,
                'default'   => ['value' => 'fas fa-cube', 'library' => 'fa-solid'],
                'condition' => [$prefix . '_source' => 'icon'],
            ]
        );

        $this->add_control(
            $prefix . '_image',
            [
                'label'       => __('تصویر یا SVG', 'zig3d-widgets'),
                'type'        => Controls_Manager::MEDIA,
                'media_types' => ['image', 'svg'],
                'dynamic'     => ['active' => true],
                'condition'   => [$prefix . '_source' => 'image'],
            ]
        );

        $this->add_control(
            $prefix . '_image_size',
            [
                'label'       => __('اندازهٔ فایل تصویر', 'zig3d-widgets'),
                'type'        => Controls_Manager::SELECT,
                'default'     => 'thumbnail',
                'options'     => $this->get_image_size_options(),
                'description' => __('روی SVG اثری ندارد. برای آیکون کوچک، اندازهٔ کوچک انتخاب کنید تا فایل سنگین دانلود نشود.', 'zig3d-widgets'),
                'condition'   => [$prefix . '_source' => 'image'],
            ]
        );

        $this->add_control(
            $prefix . '_text',
            [
                'label'       => __('متن آیکون', 'zig3d-widgets'),
                'type'        => Controls_Manager::TEXT,
                'dynamic'     => ['active' => true],
                'default'     => __('الف', 'zig3d-widgets'),
                'placeholder' => __('مثلاً الف یا ۰۱', 'zig3d-widgets'),
                'condition'   => [$prefix . '_source' => 'text'],
            ]
        );

        $this->add_control(
            $prefix . '_label',
            [
                'label'       => __('توضیح برای صفحه‌خوان', 'zig3d-widgets'),
                'type'        => Controls_Manager::TEXT,
                'separator'   => 'before',
                'description' => __('خالی بگذارید تا آیکون تزئینی در نظر گرفته شود و صفحه‌خوان از رویش رد شود — که برای اکثر آیکون‌ها درست است. فقط وقتی پر کنید که آیکون خودش معنایی دارد که در متن نیامده.', 'zig3d-widgets'),
                'condition'   => [$prefix . '_source!' => 'none'],
            ]
        );
    }

    /** فهرست اندازه‌های تصویر ثبت‌شده در وردپرس */
    private function get_image_size_options(): array {
        $options = ['full' => __('اصلی', 'zig3d-widgets')];

        if (function_exists('get_intermediate_image_sizes')) {
            foreach (get_intermediate_image_sizes() as $size) {
                $options[$size] = $size;
            }
        }

        return $options;
    }

    /* =====================================================================
     * کنترل‌های استایل
     * =================================================================== */

    /**
     * استایل کامل جعبهٔ آیکون، با تب عادی و هاور.
     *
     * @param string $hover_scope سلکتوری که هاور روی آن، حالت هاور آیکون را فعال می‌کند.
     *                           برای کارت، هاور روی «کل کارت» است نه روی خود آیکون.
     */
    protected function add_icon_style_controls(string $selector, string $hover_scope, string $prefix = 'icon'): void {
        $box = '{{WRAPPER}} ' . $selector;

        // اگر دامنهٔ هاور خودِ جعبه باشد، ‎.x:hover .x‎ به عنصری تودرتو اشاره
        // می‌کند که وجود ندارد؛ آن حالت باید ‎.x:hover‎ باشد.
        $scope     = $selector === $hover_scope
            ? $box
            : '{{WRAPPER}} ' . $hover_scope . ':hover ' . $selector;
        $box_hover = $selector === $hover_scope ? $box . ':hover' : $scope;

        /*
         * سه فرزند با یک فراخوانی ساخته می‌شوند، نه با الحاق رشته: الحاق،
         * پسوند را فقط به آخرین بخش می‌چسباند و بقیه بی‌صدا از قلم می‌افتند.
         */
        $glyph = Selector::descend($box, '> svg, > img, i');

        $this->add_responsive_control(
            $prefix . '_size',
            [
                'label'      => __('اندازهٔ آیکون', 'zig3d-widgets'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px', 'em', 'rem'],
                'range'      => [
                    'px'  => ['min' => 8, 'max' => 200],
                    'em'  => ['min' => 0.5, 'max' => 10, 'step' => 0.1],
                    'rem' => ['min' => 0.5, 'max' => 10, 'step' => 0.1],
                ],
                'default'    => ['size' => 24, 'unit' => 'px'],
                'selectors'  => [$box => '--zig-icon-size: {{SIZE}}{{UNIT}};'],
            ]
        );

        $this->add_responsive_control(
            $prefix . '_box_size',
            [
                'label'       => __('اندازهٔ جعبهٔ آیکون', 'zig3d-widgets'),
                'type'        => Controls_Manager::SLIDER,
                'size_units'  => ['px', 'em', 'rem'],
                'range'       => ['px' => ['min' => 16, 'max' => 240]],
                'default'     => ['size' => 44, 'unit' => 'px'],
                'description' => __('جعبه همیشه مربع می‌ماند تا آیکون دقیقاً وسط بیفتد.', 'zig3d-widgets'),
                'selectors'   => [$box => '--zig-icon-box: {{SIZE}}{{UNIT}};'],
            ]
        );

        $this->add_control(
            $prefix . '_rotate',
            [
                'label'      => __('چرخش', 'zig3d-widgets'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['deg'],
                'range'      => ['deg' => ['min' => -180, 'max' => 180]],
                'selectors'  => [$box => '--zig-icon-rotate: {{SIZE}}deg;'],
            ]
        );

        $this->start_controls_tabs($prefix . '_style_tabs');

        /* ---------------------------- عادی ---------------------------- */

        $this->start_controls_tab($prefix . '_tab_normal', ['label' => __('عادی', 'zig3d-widgets')]);
        $this->add_icon_state_controls($prefix, 'normal', $box, $glyph);
        $this->end_controls_tab();

        /* ---------------------------- هاور ---------------------------- */

        $this->start_controls_tab($prefix . '_tab_hover', ['label' => __('هاور', 'zig3d-widgets')]);

        $this->add_icon_state_controls(
            $prefix,
            'hover',
            $box_hover,
            Selector::descend($box_hover, '> svg, > img, i')
        );

        $this->add_control(
            $prefix . '_hover_scale',
            [
                'label'     => __('بزرگ‌نمایی در هاور', 'zig3d-widgets'),
                'type'      => Controls_Manager::SLIDER,
                'range'     => ['px' => ['min' => 0.5, 'max' => 2, 'step' => 0.01]],
                'selectors' => [$box_hover => '--zig-icon-scale: {{SIZE}};'],
            ]
        );

        $this->add_control(
            $prefix . '_hover_rotate',
            [
                'label'      => __('چرخش در هاور', 'zig3d-widgets'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['deg'],
                'range'      => ['deg' => ['min' => -180, 'max' => 180]],
                'selectors'  => [$box_hover => '--zig-icon-rotate: {{SIZE}}deg;'],
            ]
        );

        $this->end_controls_tab();
        $this->end_controls_tabs();

        $this->add_responsive_control(
            $prefix . '_padding',
            [
                'label'      => __('فاصلهٔ داخلی جعبه', 'zig3d-widgets'),
                'type'       => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'separator'  => 'before',
                'selectors'  => [$box => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'      => $prefix . '_typography',
                'label'     => __('تایپوگرافی متن آیکون', 'zig3d-widgets'),
                'selector'  => $box . ' .zig-icon__text',
                'condition' => [$prefix . '_source' => 'text'],
            ]
        );
    }

    /**
     * کنترل‌هایی که در هر دو تبِ عادی و هاور تکرار می‌شوند.
     *
     * جدا شدنشان صرفاً برای کوتاهی نیست: هر کنترلی که فقط در یک تب باشد،
     * حالت دیگر را «قابل تنظیم نشده» می‌گذارد و کاربر مجبور می‌شود با CSS
     * دستی جبرانش کند.
     */
    private function add_icon_state_controls(string $prefix, string $state, string $box, string $glyph): void {
        $this->add_control(
            $prefix . '_color_' . $state,
            [
                'label'     => __('رنگ آیکون', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    // رنگ روی خودِ جعبه می‌نشیند تا هم فونت‌آیکون (که از
                    // currentColor ارث می‌برد) و هم متن آن را بگیرند…
                    $box => 'color: {{VALUE}};',
                    // …و fill/stroke صریح روی SVG، چون فایل‌های صادرشده از
                    // فیگما تقریباً همیشه رنگ را روی خودِ path می‌نویسند و
                    // بدون این، currentColor هیچ اثری ندارد.
                    Selector::join($glyph, Selector::descend($glyph, '*')) => 'fill: {{VALUE}}; color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            $prefix . '_stroke_' . $state,
            [
                'label'       => __('رنگ خط SVG', 'zig3d-widgets'),
                'type'        => Controls_Manager::COLOR,
                'description' => __('فقط برای آیکون‌های خطی که به‌جای پرکردن، از stroke استفاده می‌کنند.', 'zig3d-widgets'),
                'selectors'   => [Selector::descend($glyph, '[stroke]') => 'stroke: {{VALUE}};'],
            ]
        );

        $this->add_group_control(
            Group_Control_Background::get_type(),
            [
                'name'     => $prefix . '_background_' . $state,
                'label'    => __('پس‌زمینهٔ جعبه', 'zig3d-widgets'),
                'types'    => ['classic', 'gradient'],
                'selector' => $box,
            ]
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name'     => $prefix . '_border_' . $state,
                'selector' => $box,
            ]
        );

        $this->add_responsive_control(
            $prefix . '_radius_' . $state,
            [
                'label'      => __('گردی گوشه', 'zig3d-widgets'),
                'type'       => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors'  => [$box => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
            ]
        );

        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            [
                'name'     => $prefix . '_shadow_' . $state,
                'selector' => $box,
            ]
        );

        $this->add_control(
            $prefix . '_opacity_' . $state,
            [
                'label'     => __('شفافیت', 'zig3d-widgets'),
                'type'      => Controls_Manager::SLIDER,
                'range'     => ['px' => ['min' => 0, 'max' => 1, 'step' => 0.01]],
                'selectors' => [$box => 'opacity: {{SIZE}};'],
            ]
        );
    }

    /* =====================================================================
     * رندر
     * =================================================================== */

    /**
     * مارک‌آپ آیکون. رشتهٔ خالی یعنی چیزی برای نمایش نیست.
     */
    protected function render_icon(array $settings, string $prefix = 'icon'): string {
        $source = (string) ($settings[$prefix . '_source'] ?? 'icon');

        if ('none' === $source) {
            return '';
        }

        $glyph = $this->render_icon_glyph($settings, $source, $prefix);

        if ('' === $glyph) {
            return '';
        }

        $label = trim((string) ($settings[$prefix . '_label'] ?? ''));

        /*
         * آیکونِ بی‌توضیح، تزئینی است و باید از درخت دسترسی‌پذیری بیرون بماند؛
         * وگرنه صفحه‌خوان نام فایل یا حرف تنها را می‌خواند و فقط سر و صدا
         * اضافه می‌کند. اگر کاربر توضیح داده، یعنی آیکون معنایی دارد که در
         * متن نیامده، پس باید خوانده شود.
         */
        $attributes = '' !== $label
            ? sprintf('role="img" aria-label="%s"', esc_attr($label))
            : 'aria-hidden="true"';

        return sprintf('<span class="zig-icon" %s>%s</span>', $attributes, $glyph);
    }

    private function render_icon_glyph(array $settings, string $source, string $prefix): string {
        if ('text' === $source) {
            $text = trim((string) ($settings[$prefix . '_text'] ?? ''));

            return '' === $text
                ? ''
                : '<span class="zig-icon__text">' . esc_html($text) . '</span>';
        }

        if ('image' === $source) {
            return $this->render_icon_image($settings, $prefix);
        }

        $icon = $settings[$prefix] ?? [];

        if (empty($icon['value'])) {
            return '';
        }

        ob_start();
        \Elementor\Icons_Manager::render_icon($icon, ['aria-hidden' => 'true']);

        return (string) ob_get_clean();
    }

    private function render_icon_image(array $settings, string $prefix): string {
        $image = $settings[$prefix . '_image'] ?? [];
        $id    = isset($image['id']) ? absint($image['id']) : 0;
        $url   = (string) ($image['url'] ?? '');

        if ('' === $url && 0 === $id) {
            return '';
        }

        // SVG درون‌خطی می‌آید تا با CSS رنگ بگیرد؛ داخل <img> این ممکن نیست.
        $svg = Svg::from_attachment($id);

        if ('' !== $svg) {
            return $svg;
        }

        if ($id > 0) {
            // وردپرس خودش srcset و sizes و width/height و lazy-loading را
            // می‌سازد — که یعنی نه پرش چیدمان (CLS) و نه دانلود بی‌مورد.
            $size = (string) ($settings[$prefix . '_image_size'] ?? 'thumbnail');
            $html = wp_get_attachment_image($id, $size, false, ['class' => 'zig-icon__img']);

            if ('' !== $html) {
                return $html;
            }
        }

        // پیوستی در کار نیست (مثلاً آدرس بیرونی از فیلد داینامیک)
        return sprintf(
            '<img class="zig-icon__img" src="%s" alt="" loading="lazy" decoding="async" />',
            esc_url($url)
        );
    }
}
