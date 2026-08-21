<?php
namespace Zig3d_Widgets\Widgets;

use Elementor\Controls_Manager;
use Elementor\Widget_Base;
use Zig3d_Widgets\Markup;
use Zig3d_Widgets\Plugin;
use Zig3d_Widgets\Price;
use Zig3d_Widgets\Video_Gallery_Field;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * گالریِ ویدئویِ محصول از فیلد Galleryِ JetEngine روی محصول. همهٔ آیتم‌ها
 * ساختار یکسان دارند؛ آیتم نخست فقط حالت فعال اولیه را دریافت می‌کند.
 * ترتیب DOM عمداً Player، Current Info، Playlist است تا موبایل بدون
 * جابه‌جایی مصنوعی همین سلسله‌مراتب را داشته باشد.
 */
final class Product_Video_Gallery extends Widget_Base {

    public function get_name(): string {
        return 'zig3d-product-video-gallery';
    }

    public function get_title(): string {
        return __('گالریِ ویدئویِ محصول', 'zig3d-widgets');
    }

    public function get_icon(): string {
        return 'eicon-play';
    }

    public function get_categories(): array {
        return [Plugin::CATEGORY];
    }

    public function get_keywords(): array {
        return ['video', 'gallery', 'jetengine', 'player', 'ویدئو', 'گالری', 'محصول', 'پلیر'];
    }

    public function get_style_depends(): array {
        return ['zig3d-widgets'];
    }

    /**
     * بدونِ این اسکریپت، اولین ویدئو (که همان کارتِ معرفی است) کاملاً
     * قابل‌پخش است — ‎<video>‎ی HTML بومی با ‎controls‎ی مرورگر جایگزینِ
     * دکمهٔ سفارشی می‌شود. فایل فقط سوییچِ کارت‌ها، دکمهٔ پخشِ سفارشی، و
     * ریست‌شدنِ خودکارِ آیکونِ پخش را رویش سوار می‌کند.
     */
    public function get_script_depends(): array {
        return ['zig3d-product-video'];
    }

    public function has_widget_inner_wrapper(): bool {
        return false;
    }

    protected function register_controls(): void {
        $this->register_content_section();
        $this->register_layout_style_section();
        $this->register_color_style_section();
    }

    /* =====================================================================
     * محتوا
     * =================================================================== */

    private function register_content_section(): void {
        $this->start_controls_section(
            'section_video_source',
            ['label' => __('دادهٔ ویدئوها', 'zig3d-widgets')]
        );

        $this->add_control(
            'meta_field_key',
            [
                'label'       => __('کلیدِ متافیلدِ Gallery', 'zig3d-widgets'),
                'type'        => Controls_Manager::TEXT,
                'default'     => 'zig-product-video',
                'description' => __('نامِ فیلدِ Galleryِ JetEngine روی محصول که ویدئوها از آن خوانده می‌شوند.', 'zig3d-widgets'),
            ]
        );

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل
     * =================================================================== */

    private function register_layout_style_section(): void {
        $this->start_controls_section(
            'section_layout',
            [
                'label' => __('چیدمانِ کلی', 'zig3d-widgets'),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'max_width',
            [
                'label'      => __('حداکثرِ عرضِ کل', 'zig3d-widgets'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range'      => ['px' => ['min' => 800, 'max' => 1600]],
                'default'    => ['size' => 1280, 'unit' => 'px'],
                'selectors'  => ['{{WRAPPER}} .zig-product-video' => '--zig-pv-max-width: {{SIZE}}{{UNIT}};'],
            ]
        );

        $this->add_control(
            'sidebar_width',
            [
                'label'      => __('عرضِ ستونِ لیست', 'zig3d-widgets'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range'      => ['px' => ['min' => 260, 'max' => 420]],
                'default'    => ['size' => 320, 'unit' => 'px'],
                'selectors'  => ['{{WRAPPER}} .zig-product-video' => '--zig-pv-sidebar-width: {{SIZE}}{{UNIT}};'],
            ]
        );

        $this->add_control(
            'gap',
            [
                'label'      => __('فاصلهٔ بینِ دو ستون', 'zig3d-widgets'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range'      => ['px' => ['min' => 0, 'max' => 80]],
                'default'    => ['size' => 24, 'unit' => 'px'],
                'selectors'  => ['{{WRAPPER}} .zig-product-video' => '--zig-pv-gap: {{SIZE}}{{UNIT}};'],
            ]
        );

        $this->add_control(
            'player_min_height_desktop',
            [
                'label'      => __('حداقلِ ارتفاعِ پلیر (دسکتاپ)', 'zig3d-widgets'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range'      => ['px' => ['min' => 300, 'max' => 800]],
                'default'    => ['size' => 500, 'unit' => 'px'],
                'separator'  => 'before',
                'selectors'  => ['{{WRAPPER}} .zig-product-video' => '--zig-pv-player-min-h: {{SIZE}}{{UNIT}};'],
            ]
        );

        $this->add_control(
            'player_min_height_mobile',
            [
                'label'       => __('حداقلِ ارتفاعِ پلیر (موبایل)', 'zig3d-widgets'),
                'type'        => Controls_Manager::SLIDER,
                'size_units'  => ['px'],
                'range'       => ['px' => ['min' => 200, 'max' => 500]],
                'default'     => ['size' => 300, 'unit' => 'px'],
                'description' => __('زیرِ ۷۶۸px که ستون‌ها زیرِ هم می‌روند اعمال می‌شود.', 'zig3d-widgets'),
                'selectors'   => ['{{WRAPPER}} .zig-product-video' => '--zig-pv-player-min-h-mobile: {{SIZE}}{{UNIT}};'],
            ]
        );

        $this->end_controls_section();
    }

    private function register_color_style_section(): void {
        $this->start_controls_section(
            'section_colors',
            [
                'label' => __('رنگ‌ها', 'zig3d-widgets'),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'primary_color',
            [
                'label'       => __('رنگِ اصلی', 'zig3d-widgets'),
                'type'        => Controls_Manager::COLOR,
                'default'     => '#5B21B6',
                'description' => __('عنوانِ کارتِ فعال + آیکونِ دکمهٔ پخشِ وسطِ پلیر.', 'zig3d-widgets'),
                'selectors'   => ['{{WRAPPER}} .zig-product-video' => '--zig-pv-primary: {{VALUE}};'],
            ]
        );

        $this->add_control(
            'active_card_bg',
            [
                'label'     => __('پس‌زمینهٔ کارتِ فعال', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'default'   => '#F2ECFF',
                'selectors' => ['{{WRAPPER}} .zig-product-video' => '--zig-pv-active-bg: {{VALUE}};'],
            ]
        );

        $this->add_control(
            'player_bg_heading',
            [
                'label'       => __('پس‌زمینهٔ پلیر (پیشرفته)', 'zig3d-widgets'),
                'type'        => Controls_Manager::HEADING,
                'separator'   => 'before',
                'description' => __('پیش‌فرض یک گرادیانِ بنفش-تیرهٔ premium است؛ معمولاً نیازی به تغییر نیست.', 'zig3d-widgets'),
            ]
        );

        $this->add_control(
            'player_bg_from',
            [
                'label'     => __('شروعِ گرادیان', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'default'   => '#1B1030',
                'selectors' => ['{{WRAPPER}} .zig-product-video' => '--zig-pv-player-bg-from: {{VALUE}};'],
            ]
        );

        $this->add_control(
            'player_bg_to',
            [
                'label'     => __('پایانِ گرادیان', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'default'   => '#050308',
                'selectors' => ['{{WRAPPER}} .zig-product-video' => '--zig-pv-player-bg-to: {{VALUE}};'],
            ]
        );

        $this->end_controls_section();
    }

    /* =====================================================================
     * رندر
     * =================================================================== */

    protected function render(): void {
        $settings  = $this->get_settings_for_display();
        $is_editor = $this->is_editing();
        $meta_key  = trim((string) ($settings['meta_field_key'] ?? ''));

        if ('' === $meta_key) {
            $meta_key = 'zig-product-video';
        }

        $product = $this->resolve_product($is_editor);
        $items   = $product ? Video_Gallery_Field::items($product->get_id(), $meta_key) : [];

        if (!$items) {
            $this->editor_notice(
                $is_editor,
                sprintf(
                    /* translators: %s: کلیدِ متافیلد */
                    __('هیچ ویدئویی در %s پیدا نشد', 'zig3d-widgets'),
                    $meta_key
                )
            );

            return;
        }

        $first = $items[0];
        $is_single = (1 === count($items));

        printf('<div class="zig-product-video%s" data-zig-video-gallery>', $is_single ? ' zig-product-video--single' : '');

        $this->render_player($first, $items);

        $this->render_current_info($first, $items);

        if (!$is_single) {
            $this->render_sidebar($items);
        }

        echo '</div>';
    }

    /**
     * محصولی که فیلدِ گالری‌اش خوانده می‌شود — همان زنجیرهٔ منعطفِ
     * ‎Price::resolve()‎ (محصولِ لوپِ جاری/کوئریِ صفحهٔ محصول/پستِ جاری)
     * که بقیهٔ ویجت‌های این افزونه دارند؛ فقط اگر آن هم چیزی پیدا نکرد و
     * در ادیتور/پیش‌نمایش هستیم، به آخرین محصولِ منتشرشده برمی‌گردیم — تا
     * کشیدنِ ویجت روی صفحهٔ خالی هم چیزی برای پیش‌نمایش داشته باشد.
     */
    private function resolve_product(bool $is_editor) {
        $product = Price::resolve(0);

        if (null !== $product) {
            return $product;
        }

        $is_preview = $is_editor
            || (function_exists('is_preview') && is_preview())
            || (class_exists('\Elementor\Plugin') && isset(\Elementor\Plugin::$instance->preview) && \Elementor\Plugin::$instance->preview->is_preview_mode());

        if (!$is_preview || !function_exists('wc_get_products')) {
            return null;
        }

        $latest = wc_get_products(['limit' => 1, 'orderby' => 'date', 'order' => 'DESC', 'status' => 'publish']);

        return $latest ? $latest[0] : null;
    }

    /**
     * @param array{id:int,url:string,title:string,description:string,duration:string,poster:array{id:int,url:string}} $item
     * @param array<int,array{id:int,url:string,title:string,description:string,duration:string,poster:array{id:int,url:string}}> $items
     */
    private function render_player(array $item, array $items): void {
        echo '<div class="zig-product-video__player">';

        printf(
            '<video class="zig-product-video__video" src="%s" preload="none" playsinline controls%s></video>',
            esc_url($item['url']),
            $item['poster']['url'] ? ' poster="' . esc_url($item['poster']['url']) . '"' : ''
        );

        printf(
            '<button type="button" class="zig-product-video__play" aria-label="%s"><span class="zig-product-video__play-icon" aria-hidden="true">%s</span><span class="zig-product-video__play-label">%s</span></button>',
            esc_attr__('پخشِ ویدئو', 'zig3d-widgets'),
            self::play_icon(),
            esc_html__('پخشِ ویدئو', 'zig3d-widgets')
        );

        $has_duration = false;
        foreach ($items as $video) {
            if (Markup::filled($video['duration'])) {
                $has_duration = true;
                break;
            }
        }

        if ($has_duration) {
            printf('<span class="zig-product-video__duration"%s>%s</span>', Markup::filled($item['duration']) ? '' : ' hidden', esc_html($item['duration']));
        }

        echo '</div>';
    }

    /**
     * @param array{id:int,url:string,title:string,description:string,duration:string,poster:array{id:int,url:string}} $item
     * @param array<int,array{id:int,url:string,title:string,description:string,duration:string,poster:array{id:int,url:string}}> $items
     */
    private function render_current_info(array $item, array $items): void {
        $has_info = false;
        foreach ($items as $video) {
            if (Markup::filled($video['title']) || Markup::filled($video['description'])) {
                $has_info = true;
                break;
            }
        }

        if (!$has_info) {
            return;
        }

        printf('<div class="zig-product-video__current-info" data-zig-video-details%s>', Markup::filled($item['title']) || Markup::filled($item['description']) ? '' : ' hidden');
        printf('<strong class="zig-product-video__current-title" data-zig-video-title%s>%s</strong>', Markup::filled($item['title']) ? '' : ' hidden', Markup::text($item['title']));
        printf('<span class="zig-product-video__current-description" data-zig-video-desc%s>%s</span>', Markup::filled($item['description']) ? '' : ' hidden', Markup::text($item['description']));
        echo '</div>';
    }

    /**
     * @param array<int,array{id:int,url:string,title:string,description:string,duration:string,poster:array{id:int,url:string}}> $items
     */
    private function render_sidebar(array $items): void {
        echo '<div class="zig-product-video__sidebar">';
        echo '<div class="zig-product-video__list" aria-label="' . esc_attr__('ویدئوها', 'zig3d-widgets') . '">';

        foreach ($items as $i => $item) {
            $is_first = (0 === $i);
            printf(
                '<button type="button" class="zig-product-video__card%s" data-index="%d" data-src="%s" data-poster="%s" data-duration="%s"%s>',
                $is_first ? ' is-active' : '',
                $i,
                esc_url($item['url']),
                esc_url($item['poster']['url']),
                esc_attr($item['duration']),
                $is_first ? ' aria-current="true"' : ''
            );

            printf('<span class="zig-product-video__icon" aria-hidden="true">%s</span>', self::video_icon());

            echo '<span class="zig-product-video__body">';
            printf('<span class="zig-product-video__title"%s>%s</span>', Markup::filled($item['title']) ? '' : ' hidden', Markup::text($item['title']));
            printf('<span class="zig-product-video__description"%s>%s</span>', Markup::filled($item['description']) ? '' : ' hidden', Markup::text($item['description']));
            if (Markup::filled($item['duration'])) {
                printf('<span class="zig-product-video__meta"><span class="zig-product-video__clock" aria-hidden="true">%s</span>%s</span>', self::clock_icon(), esc_html($item['duration']));
            }
            echo '</span>';

            echo '</button>';
        }

        echo '</div>'; // .zig-product-video__list
        echo '</div>'; // .zig-product-video__sidebar
    }

    /**
     * مثلثِ پخش، درون‌خطی — همان فلسفهٔ ‎Markup::svg_icon()‎: سه شکلِ ثابت
     * ارزشِ درخواستِ HTTPِ جدا یا وابستگیِ فونتِ آیکون را ندارد.
     */
    private static function play_icon(): string {
        return '<svg viewBox="0 0 24 24" width="1em" height="1em" fill="currentColor" aria-hidden="true" focusable="false"><path d="M8 5v14l11-7z"/></svg>';
    }

    private static function video_icon(): string {
        return '<svg viewBox="0 0 24 24" width="1em" height="1em" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="3" y="5" width="18" height="14" rx="3"/><path d="m10 9 5 3-5 3V9z" fill="currentColor" stroke="none"/></svg>';
    }

    private static function clock_icon(): string {
        return '<svg viewBox="0 0 24 24" width="1em" height="1em" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/></svg>';
    }

    /**
     * ‎$is_editor‎ صریحاً پارامتر است، نه فراخوانیِ داخلیِ ‎is_editing()‎ —
     * همان دلیلِ ‎resolve_product()‎: تستِ واحد بدونِ نصبِ کاملِ المنتور
     * باید بتواند این تصمیم را از بیرون شبیه‌سازی کند.
     */
    private function editor_notice(bool $is_editor, string $message): void {
        if (!$is_editor) {
            return;
        }

        printf('<div class="zig-product-video__notice">%s</div>', esc_html($message));
    }

    private function is_editing(): bool {
        return class_exists('\Elementor\Plugin')
            && isset(\Elementor\Plugin::$instance->editor)
            && \Elementor\Plugin::$instance->editor->is_edit_mode();
    }
}
