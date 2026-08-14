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
 * گالریِ ویدئویِ محصول — دادهٔ فیلدِ Galleryِ JetEngine روی خودِ محصول
 * (کلیدِ متایِ قابل‌تنظیم، پیش‌فرض ‎zig-product-video‎)، نه چیزی که این
 * ویجت بسازد یا مدیریت کند.
 *
 *     div.zig-product-video
 *       div.zig-product-video__player
 *         video.zig-product-video__video      ‎<source>‎ی آیتمِ فعال
 *         button.zig-product-video__play
 *         span.zig-product-video__duration
 *       div.zig-product-video__sidebar
 *         div.zig-product-video__list
 *           button.zig-product-video__card--intro[is-active]   آیتمِ اول همیشه
 *           button.zig-product-video__card × (تعداد آیتم − ۱)
 *
 * چرا آیتمِ اول «معرفی» است، نه یک ویدئوی معمولی: طبقِ طرحِ مرجع، اولین
 * آیتمِ گالری هم‌زمان ویدئویِ پیش‌فرضِ پلیر *و* کارتِ معرفیِ کناری است —
 * ساختارش (بدونِ آیکونِ play، با توضیحِ کامل) وابسته به «شمارهٔ آیتم»
 * است، نه به «فعال بودن»؛ اگر بعداً کارتِ دیگری کلیک شود، آن کارت هم
 * رنگِ فعال می‌گیرد، ولی کارتِ اول همیشه همان ساختارِ معرفی را دارد — این
 * دو مستقل از هم‌اند.
 *
 * چرا چیدمانِ Grid با ‎player‎ اول در DOM: در Gridِ راست‌به‌چپ، ستونِ *اول*
 * تعریف‌شده در ‎grid-template-columns‎ سمتِ راستِ صفحه می‌نشیند. طرحِ مرجع
 * صراحتاً می‌خواهد لیست چپ باشد و پلیر راست — مستقل از جهتِ کلیِ RTL. با
 * قرار دادنِ پلیر اول در DOM و اولین ستون، بدونِ نیاز به ‎order‎، هم
 * ترتیبِ دیداریِ دسکتاپ درست از آب درمی‌آید هم پشته‌شدنِ موبایل (که طبقِ
 * طرح باید «اول پلیر، بعد لیست» باشد) به‌طورِ طبیعی همان ترتیبِ DOM را
 * می‌گیرد، بدونِ قاعدهٔ اضافه.
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
                'default'    => ['size' => 32, 'unit' => 'px'],
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

        printf('<div class="zig-product-video" data-zig-video-gallery>');

        $this->render_player($first);
        $this->render_sidebar($items);

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
     */
    private function render_player(array $item): void {
        echo '<div class="zig-product-video__player">';

        printf(
            '<video class="zig-product-video__video" src="%s" preload="metadata" playsinline%s></video>',
            esc_url($item['url']),
            $item['poster']['url'] ? ' poster="' . esc_url($item['poster']['url']) . '"' : ''
        );

        printf(
            '<button type="button" class="zig-product-video__play" aria-label="%s">%s</button>',
            esc_attr__('پخشِ ویدئو', 'zig3d-widgets'),
            self::play_icon()
        );

        if (Markup::filled($item['duration'])) {
            printf('<span class="zig-product-video__duration">%s</span>', esc_html($item['duration']));
        }

        echo '</div>';
    }

    /**
     * @param array<int,array{id:int,url:string,title:string,description:string,duration:string,poster:array{id:int,url:string}}> $items
     */
    private function render_sidebar(array $items): void {
        echo '<div class="zig-product-video__sidebar">';
        echo '<div class="zig-product-video__list">';

        foreach ($items as $i => $item) {
            $is_intro = (0 === $i);

            printf(
                '<button type="button" class="zig-product-video__card%s%s" data-index="%d" data-src="%s" data-poster="%s" data-duration="%s" aria-pressed="%s">',
                $is_intro ? ' zig-product-video__card--intro' : '',
                $is_intro ? ' is-active' : '',
                $i,
                esc_url($item['url']),
                esc_url($item['poster']['url']),
                esc_attr($item['duration']),
                $is_intro ? 'true' : 'false'
            );

            if ($is_intro) {
                if (Markup::filled($item['title'])) {
                    printf('<span class="zig-product-video__title">%s</span>', Markup::text($item['title']));
                }
                if (Markup::filled($item['description'])) {
                    printf('<span class="zig-product-video__desc">%s</span>', Markup::text($item['description']));
                }
            } else {
                echo '<span class="zig-product-video__icon" aria-hidden="true">' . self::play_icon() . '</span>';
                echo '<span class="zig-product-video__body">';
                if (Markup::filled($item['title'])) {
                    printf('<span class="zig-product-video__title">%s</span>', Markup::text($item['title']));
                }
                if (Markup::filled($item['duration'])) {
                    printf('<span class="zig-product-video__meta">%s</span>', esc_html($item['duration']));
                }
                echo '</span>';
            }

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
