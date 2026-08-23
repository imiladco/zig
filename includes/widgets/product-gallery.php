<?php
namespace Zig3d_Widgets\Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Icons_Manager;
use Elementor\Widget_Base;
use Zig3d_Widgets\Plugin;
use Zig3d_Widgets\Price;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * پورتِ مستقیمِ ویجتِ «گالری محصول» از افزونهٔ almasara-elementor-widgets
 * (‎includes/widgets/product-gallery.php‎ آنجا)، فقط با تعویضِ پیشوندها:
 *
 *     amw-pg          → zig-gallery
 *     amw-pg-modal     → zig-gallery-modal
 *     --amw-pg-*       → --zig-gal-*
 *     almasara/v1      → zig3d/v1   (REST)
 *     AlmasaraModal    → Zig3dModal (کنترلرِ مشترکِ مودال، در zig3d-modal.js)
 *
 * معماری هم همان است، نه یک بازطراحیِ تازه: تصویرِ شاخص + تامبنیل‌های
 * مربعی (بدون اسلایدر)، تامبنیلِ آخر با اورلی+سه‌نقطه/بجِ +N اگر تصاویرِ
 * بیشتری باشد، و مودالی که فقط بعدِ بازشدن، از راهِ یک REST عمومی و
 * قابلِ‌کش، آدرسِ تصاویرِ کامل را ایجکسی می‌گیرد — نه همه‌چیز از اول در
 * HTML. تنها چیزی که این‌جا اضافه شده، سکشنِ کوچکِ «محصول» است (شناسهٔ
 * محصولِ دستی)، چون بقیهٔ ویجت‌های این افزونه همین قرارداد را دارند؛
 * منبعِ اصلی بدونش، محصولِ لوپِ جاری یا — در ادیتور — آخرین محصولِ
 * منتشرشده را نشان می‌دهد.
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
        return ['gallery', 'product', 'images', 'modal', 'lightbox', 'woocommerce', 'گالری', 'محصول', 'تصاویر', 'مودال'];
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

    /* =====================================================================
     * کنترل‌ها
     * =================================================================== */

    protected function register_controls(): void {
        $this->register_product_section();
        $this->register_images_content_controls();
        $this->register_modal_content_controls();

        $this->register_card_style_controls();
        $this->register_main_image_style_controls();
        $this->register_main_nav_style_controls();
        $this->register_thumbs_style_controls();
        $this->register_modal_style_controls();
    }

    /** محتوا — محصول */
    private function register_product_section(): void {
        $this->start_controls_section('section_product', [
            'label' => __('محصول', 'zig3d-widgets'),
        ]);

        $this->add_control('product_id', [
            'label'       => __('شناسهٔ محصول', 'zig3d-widgets'),
            'type'        => Controls_Manager::NUMBER,
            'min'         => 0,
            'dynamic'     => ['active' => true],
            'description' => __('خالی بگذارید تا محصول جاری استفاده شود — چه در صفحهٔ محصول، چه داخل حلقهٔ فروشگاه یا قالب حلقهٔ المنتور. در ادیتور، اگر محصولی پیدا نشود، آخرین محصولِ منتشرشده برای پیش‌نمایش نشان داده می‌شود.', 'zig3d-widgets'),
        ]);

        $this->end_controls_section();
    }

    /** محتوا — تصاویر */
    private function register_images_content_controls(): void {
        $this->start_controls_section('section_images', [
            'label' => __('تصاویر', 'zig3d-widgets'),
            'tab'   => Controls_Manager::TAB_CONTENT,
        ]);

        $this->add_control('thumbs_count', [
            'label'       => __('تعداد تامبنیل‌ها (دسکتاپ)', 'zig3d-widgets'),
            'type'        => Controls_Manager::NUMBER,
            'min'         => 1,
            'max'         => 10,
            'default'     => 4,
            'description' => __('تعداد تامبنیل‌های قابل نمایش کنار تصویر شاخص در دسکتاپ. تامبنیل‌ها همیشه مربعی‌اند و اسلاید نمی‌شوند. اگر تصاویر بیشتری در گالری باشد، روی تامبنیل آخر اورلی می‌نشیند.', 'zig3d-widgets'),
            'selectors'   => [
                '{{WRAPPER}} .zig-gallery' => '--zig-gal-thumbs: {{VALUE}};',
            ],
        ]);

        $this->add_control('mobile_layout', [
            'label'       => __('چیدمان موبایل', 'zig3d-widgets'),
            'type'        => Controls_Manager::SELECT,
            'default'     => 'alternating',
            'options'     => [
                'alternating' => __('نوار اسکرولی یکی‌درمیون (۱ بزرگ، ۲ کوچک)', 'zig3d-widgets'),
                'match'       => __('همان چیدمان دسکتاپ', 'zig3d-widgets'),
            ],
            'description' => __('نوار افقی قابل سوایپ: تصویر شاخص تمام‌ارتفاع و مربع، بعد ستونی از ۲ مربع کوچک، بعد تصویر بزرگ بعدی و همین‌طور یکی‌درمیون. تمام تصاویر گالری نمایش داده می‌شوند.', 'zig3d-widgets'),
        ]);

        $this->add_responsive_control('mobile_height', [
            'label'       => __('ارتفاع نوار گالری موبایل', 'zig3d-widgets'),
            'type'        => Controls_Manager::SLIDER,
            'size_units'  => ['px', 'vw'],
            'range'       => [
                'px' => ['min' => 150, 'max' => 600],
                'vw' => ['min' => 30, 'max' => 100],
            ],
            'default'     => ['size' => 85, 'unit' => 'vw'],
            'description' => __('عرض تصویر بزرگ = همین ارتفاع (مربع ۱:۱)؛ ارتفاع و عرض دو مربع کوچک هم از همین مقدار منهای گپ محاسبه می‌شود.', 'zig3d-widgets'),
            'selectors'   => [
                '{{WRAPPER}} .zig-gallery' => '--zig-gal-mh: {{SIZE}}{{UNIT}};',
            ],
            'condition'   => ['mobile_layout' => 'alternating'],
        ]);

        $this->add_control('show_mobile_counter', [
            'label'       => __('نشانگر پایین گالری موبایل', 'zig3d-widgets'),
            'type'        => Controls_Manager::SWITCHER,
            'default'     => 'yes',
            'description' => __('پیل شامل نوار پیشرفت اسکرول + تعداد تصاویر، پایین نوار گالری.', 'zig3d-widgets'),
            'condition'   => ['mobile_layout' => 'alternating'],
        ]);

        $this->add_control('show_dots', [
            'label'   => __('نمایش سه‌نقطه روی تامبنیل آخر', 'zig3d-widgets'),
            'type'    => Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);

        $this->add_control('show_more_count', [
            'label'       => __('نمایش تعداد تصاویر باقی‌مانده (+N)', 'zig3d-widgets'),
            'type'        => Controls_Manager::SWITCHER,
            'default'     => '',
            'description' => __('بجی مثل «+۱۸» روی تامبنیل آخر.', 'zig3d-widgets'),
        ]);

        $this->add_control('heading_main_nav', [
            'label'     => __('ناوبری تصویر شاخص', 'zig3d-widgets'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        /*
         * این فلش‌ها مستقل از تامبنیل‌هایند: تامبنیل همیشه فقط مودال را باز
         * می‌کند (خودِ کاربر همین رفتار را خواسته)، ولی این فلش‌ها بدونِ
         * مودال، خودِ تصویرِ شاخص را بینِ همهٔ تصاویرِ گالری (نه فقط
         * تامبنیل‌هایِ دیده‌شدنی) عوض می‌کنند.
         */
        $this->add_control('show_main_nav', [
            'label'       => __('فلش‌های قبلی/بعدی', 'zig3d-widgets'),
            'type'        => Controls_Manager::SWITCHER,
            'default'     => 'yes',
            'description' => __('کاربر با این فلش‌ها، بدونِ بازکردنِ مودال، بینِ همهٔ تصاویرِ گالری روی خودِ تصویرِ شاخص جابه‌جا می‌شود. کلیکِ خودِ تامبنیل‌ها همچنان فقط مودال را باز می‌کند.', 'zig3d-widgets'),
        ]);

        $this->add_control('main_nav_prev_icon', [
            'label'       => __('آیکون فلش قبلی', 'zig3d-widgets'),
            'type'        => Controls_Manager::ICONS,
            'default'     => ['value' => '', 'library' => ''],
            'description' => __('خالی یعنی فلشِ پیش‌فرضِ همین ویجت.', 'zig3d-widgets'),
            'condition'   => ['show_main_nav' => 'yes'],
        ]);

        $this->add_control('main_nav_next_icon', [
            'label'       => __('آیکون فلش بعدی', 'zig3d-widgets'),
            'type'        => Controls_Manager::ICONS,
            'default'     => ['value' => '', 'library' => ''],
            'description' => __('خالی یعنی فلشِ پیش‌فرضِ همین ویجت.', 'zig3d-widgets'),
            'condition'   => ['show_main_nav' => 'yes'],
        ]);

        $this->end_controls_section();
    }

    /** محتوا — مودال */
    private function register_modal_content_controls(): void {
        $this->start_controls_section('section_modal', [
            'label' => __('مودال گالری', 'zig3d-widgets'),
            'tab'   => Controls_Manager::TAB_CONTENT,
        ]);

        $this->add_control('modal_enable', [
            'label'       => __('باز شدن مودال با کلیک', 'zig3d-widgets'),
            'type'        => Controls_Manager::SWITCHER,
            'default'     => 'yes',
            'description' => __('تصاویر کامل فقط بعد از باز شدن مودال و به‌صورت ایجکسی لود می‌شوند.', 'zig3d-widgets'),
        ]);

        $this->add_control('show_tab', [
            'label'     => __('نمایش تب بالای مودال', 'zig3d-widgets'),
            'type'      => Controls_Manager::SWITCHER,
            'default'   => 'yes',
            'condition' => ['modal_enable' => 'yes'],
        ]);

        $this->add_control('tab_label', [
            'label'     => __('متن تب', 'zig3d-widgets'),
            'type'      => Controls_Manager::TEXT,
            'default'   => __('رسمی', 'zig3d-widgets'),
            'condition' => [
                'modal_enable' => 'yes',
                'show_tab'     => 'yes',
            ],
        ]);

        $this->add_control('modal_animation', [
            'label'     => __('انیمیشن ورود/خروج', 'zig3d-widgets'),
            'type'      => Controls_Manager::SELECT,
            'default'   => 'fade-scale',
            'options'   => [
                'fade'       => __('محو ساده (fade)', 'zig3d-widgets'),
                'fade-scale' => __('محو + بزرگ‌نمایی نرم (پیشنهادی)', 'zig3d-widgets'),
                'slide-up'   => __('اسلاید از پایین', 'zig3d-widgets'),
                'zoom'       => __('بزرگ‌نمایی از کوچک', 'zig3d-widgets'),
            ],
            'condition' => ['modal_enable' => 'yes'],
        ]);

        $this->add_control('modal_duration', [
            'label'     => __('مدت انیمیشن (میلی‌ثانیه)', 'zig3d-widgets'),
            'type'      => Controls_Manager::SLIDER,
            'range'     => ['px' => ['min' => 100, 'max' => 800]],
            'default'   => ['size' => 260],
            'selectors' => [
                '{{WRAPPER}} .zig-gallery-modal' => '--zig-gal-modal-dur: {{SIZE}}ms;',
            ],
            'condition' => ['modal_enable' => 'yes'],
        ]);

        $this->end_controls_section();
    }

    /**
     * استایل — کارتِ گالری.
     *
     * تصویرِ شاخص و تامبنیل‌ها هر دو داخلِ همین یک جعبه‌اند (‎.zig-gallery‎
     * خودش)، نه دو ناحیهٔ بصریِ جدا — پس، پس‌زمینه/بوردر/رادیوس/پدینگ اینجا
     * رویِ خودِ ریشه می‌نشیند.
     */
    private function register_card_style_controls(): void {
        $this->start_controls_section('section_style_card', [
            'label' => __('کارت گالری', 'zig3d-widgets'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_control('card_bg', [
            'label'     => __('رنگ پس‌زمینه', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .zig-gallery' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->add_group_control(Group_Control_Border::get_type(), [
            'name'     => 'card_border',
            'selector' => '{{WRAPPER}} .zig-gallery',
        ]);

        $this->add_responsive_control('card_radius', [
            'label'      => __('گردی گوشه', 'zig3d-widgets'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%'],
            'selectors'  => [
                '{{WRAPPER}} .zig-gallery' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('card_padding', [
            'label'      => __('پدینگ داخلی', 'zig3d-widgets'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', 'em', '%'],
            'selectors'  => [
                '{{WRAPPER}} .zig-gallery' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->add_group_control(Group_Control_Box_Shadow::get_type(), [
            'name'     => 'card_shadow',
            'selector' => '{{WRAPPER}} .zig-gallery',
        ]);

        $this->end_controls_section();
    }

    /*
     * فاصلهٔ تصویرِ شاخص تا تامبنیل‌ها همچنان یک کنترلِ مجزاست
     * (‎main_spacing‎، تویِ بخشِ «تصویر شاخص»)، نه اینجا — همان
     * ‎--zig-gal-rgap‎ را می‌نویسد؛ دوباره‌نویسیِ همان متغیر با اسمِ دیگر در
     * این بخش فقط دو کنترل برایِ یک اثر می‌ساخت.
     */

    /** استایل — تصویر شاخص */
    private function register_main_image_style_controls(): void {
        $this->start_controls_section('section_style_main', [
            'label' => __('تصویر شاخص', 'zig3d-widgets'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_responsive_control('main_ratio', [
            'label'     => __('نسبت ابعاد', 'zig3d-widgets'),
            'type'      => Controls_Manager::SELECT,
            'default'   => '1 / 1',
            'options'   => [
                'auto'   => __('خودکار (ابعاد اصلی)', 'zig3d-widgets'),
                '1 / 1'  => __('مربع (۱:۱)', 'zig3d-widgets'),
                '4 / 3'  => '4:3',
                '3 / 4'  => '3:4',
                '16 / 9' => '16:9',
            ],
            'selectors' => [
                '{{WRAPPER}} .zig-gallery__main' => 'aspect-ratio: {{VALUE}};',
            ],
        ]);

        $this->add_control('main_fit', [
            'label'     => __('نحوه جای‌گیری تصویر', 'zig3d-widgets'),
            'type'      => Controls_Manager::SELECT,
            'default'   => 'contain',
            'options'   => [
                'cover'   => __('کاور (کات از وسط)', 'zig3d-widgets'),
                'contain' => __('کامل داخل کادر', 'zig3d-widgets'),
                'fill'    => __('کشیده', 'zig3d-widgets'),
            ],
            'selectors' => [
                '{{WRAPPER}} .zig-gallery__main img' => 'object-fit: {{VALUE}};',
            ],
            'condition' => ['main_ratio!' => 'auto'],
        ]);

        $this->add_control('main_position', [
            'label'     => __('نقطه برش', 'zig3d-widgets'),
            'type'      => Controls_Manager::SELECT,
            'default'   => 'center center',
            'options'   => [
                'center center' => __('وسط', 'zig3d-widgets'),
                'top center'    => __('بالا', 'zig3d-widgets'),
                'bottom center' => __('پایین', 'zig3d-widgets'),
                'center right'  => __('راست', 'zig3d-widgets'),
                'center left'   => __('چپ', 'zig3d-widgets'),
            ],
            'selectors' => [
                '{{WRAPPER}} .zig-gallery__main img' => 'object-position: {{VALUE}};',
            ],
            'condition' => [
                'main_ratio!' => 'auto',
                'main_fit'    => 'cover',
            ],
        ]);

        $this->add_control('main_bg', [
            'label'       => __('رنگ پس‌زمینه', 'zig3d-widgets'),
            'type'        => Controls_Manager::COLOR,
            'description' => __('برای حالت «کامل داخل کادر» یا وقتی پدینگ می‌دهید دیده می‌شود.', 'zig3d-widgets'),
            'selectors'   => [
                '{{WRAPPER}} .zig-gallery__main' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->add_responsive_control('main_padding', [
            'label'       => __('پدینگ داخلی', 'zig3d-widgets'),
            'type'        => Controls_Manager::DIMENSIONS,
            'size_units'  => ['px', 'em', '%'],
            'description' => __('فاصله تصویر از لبه‌های کادر.', 'zig3d-widgets'),
            'selectors'   => [
                '{{WRAPPER}} .zig-gallery__main' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('main_spacing', [
            'label'      => __('فاصله تا تامبنیل‌ها (سطر)', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', 'em'],
            'range'      => ['px' => ['min' => 0, 'max' => 100]],
            'selectors'  => [
                '{{WRAPPER}} .zig-gallery' => '--zig-gal-rgap: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_group_control(Group_Control_Border::get_type(), [
            'name'     => 'main_border',
            'label'    => __('حاشیه', 'zig3d-widgets'),
            'selector' => '{{WRAPPER}} .zig-gallery__main',
        ]);

        $this->add_responsive_control('main_radius', [
            'label'      => __('رادیوس', 'zig3d-widgets'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%'],
            'selectors'  => [
                '{{WRAPPER}} .zig-gallery__main' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}; overflow: hidden;',
            ],
        ]);

        $this->add_group_control(Group_Control_Box_Shadow::get_type(), [
            'name'     => 'main_shadow',
            'label'    => __('سایه', 'zig3d-widgets'),
            'selector' => '{{WRAPPER}} .zig-gallery__main',
        ]);

        $this->add_control('main_hover_opacity', [
            'label'     => __('شفافیت در هاور', 'zig3d-widgets'),
            'type'      => Controls_Manager::SLIDER,
            'range'     => ['px' => ['min' => 0.1, 'max' => 1, 'step' => 0.05]],
            'selectors' => [
                '{{WRAPPER}} .zig-gallery__main:hover img' => 'opacity: {{SIZE}};',
            ],
        ]);

        $this->add_control('main_hover_scale', [
            'label'     => __('بزرگ‌نمایی در هاور', 'zig3d-widgets'),
            'type'      => Controls_Manager::SLIDER,
            'range'     => ['px' => ['min' => 1, 'max' => 1.5, 'step' => 0.01]],
            'selectors' => [
                '{{WRAPPER}} .zig-gallery__main:hover img' => 'transform: scale({{SIZE}});',
            ],
        ]);

        $this->end_controls_section();
    }

    /** استایل — فلش‌های ناوبریِ تصویر شاخص */
    private function register_main_nav_style_controls(): void {
        $this->start_controls_section('section_style_main_nav', [
            'label'     => __('فلش‌های ناوبری تصویر شاخص', 'zig3d-widgets'),
            'tab'       => Controls_Manager::TAB_STYLE,
            'condition' => ['show_main_nav' => 'yes'],
        ]);

        $this->add_responsive_control('main_nav_size', [
            'label'      => __('اندازه', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 24, 'max' => 80]],
            'selectors'  => [
                '{{WRAPPER}} .zig-gallery__nav' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('main_nav_icon_size', [
            'label'      => __('اندازهٔ آیکون', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 8, 'max' => 40]],
            'selectors'  => [
                '{{WRAPPER}} .zig-gallery__nav svg, {{WRAPPER}} .zig-gallery__nav i' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}}; font-size: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('main_nav_offset', [
            'label'       => __('فاصله از لبه', 'zig3d-widgets'),
            'type'        => Controls_Manager::SLIDER,
            'size_units'  => ['px'],
            'range'       => ['px' => ['min' => 0, 'max' => 60]],
            'description' => __('فاصلهٔ افقیِ هر فلش تا لبهٔ همان سمتِ تصویرِ شاخص.', 'zig3d-widgets'),
            'selectors'   => [
                '{{WRAPPER}} .zig-gallery__nav' => '--zig-gal-nav-offset: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_control('main_nav_color', [
            'label'     => __('رنگ آیکون', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .zig-gallery__nav' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_control('main_nav_bg', [
            'label'     => __('رنگ پس‌زمینه', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .zig-gallery__nav' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->add_control('main_nav_hover_bg', [
            'label'     => __('رنگ پس‌زمینه در هاور', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .zig-gallery__nav:hover' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->add_responsive_control('main_nav_radius', [
            'label'      => __('گردی گوشه', 'zig3d-widgets'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%'],
            'selectors'  => [
                '{{WRAPPER}} .zig-gallery__nav' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->add_group_control(Group_Control_Border::get_type(), [
            'name'     => 'main_nav_border',
            'selector' => '{{WRAPPER}} .zig-gallery__nav',
        ]);

        $this->add_group_control(Group_Control_Box_Shadow::get_type(), [
            'name'     => 'main_nav_shadow',
            'selector' => '{{WRAPPER}} .zig-gallery__nav',
        ]);

        $this->end_controls_section();
    }

    /** استایل — تامبنیل‌ها */
    private function register_thumbs_style_controls(): void {
        $this->start_controls_section('section_style_thumbs', [
            'label' => __('تامبنیل‌ها', 'zig3d-widgets'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_control('heading_strip', [
            'label' => __('نوار گالری', 'zig3d-widgets'),
            'type'  => Controls_Manager::HEADING,
        ]);

        $this->add_control('strip_bg', [
            'label'     => __('رنگ پس‌زمینه نوار', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .zig-gallery__strip' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->add_responsive_control('strip_padding', [
            'label'      => __('پدینگ نوار', 'zig3d-widgets'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', 'em', '%'],
            'selectors'  => [
                '{{WRAPPER}} .zig-gallery__strip' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->add_group_control(Group_Control_Border::get_type(), [
            'name'     => 'strip_border',
            'label'    => __('حاشیه نوار', 'zig3d-widgets'),
            'selector' => '{{WRAPPER}} .zig-gallery__strip',
        ]);

        $this->add_responsive_control('thumbs_gap', [
            'label'      => __('فاصله بین تامبنیل‌ها (ستون)', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', 'em'],
            'range'      => ['px' => ['min' => 0, 'max' => 60]],
            'default'    => ['size' => 12, 'unit' => 'px'],
            'selectors'  => [
                '{{WRAPPER}} .zig-gallery' => '--zig-gal-cgap: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_control('heading_thumb', [
            'label'     => __('تک‌تکِ تامبنیل‌ها', 'zig3d-widgets'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_control('thumb_fit', [
            'label'     => __('نحوه جای‌گیری تصویر', 'zig3d-widgets'),
            'type'      => Controls_Manager::SELECT,
            'default'   => 'contain',
            'options'   => [
                'cover'   => __('کاور (کات از وسط)', 'zig3d-widgets'),
                'contain' => __('کامل داخل کارت', 'zig3d-widgets'),
                'fill'    => __('کشیده', 'zig3d-widgets'),
            ],
            'selectors' => [
                '{{WRAPPER}} .zig-gallery__thumb img' => 'object-fit: {{VALUE}};',
            ],
        ]);

        $this->add_control('thumb_bg', [
            'label'       => __('رنگ پس‌زمینه کارت', 'zig3d-widgets'),
            'type'        => Controls_Manager::COLOR,
            'default'     => '#ffffff',
            'description' => __('برای حالت «کامل داخل کارت» یا وقتی پدینگ می‌دهید دیده می‌شود.', 'zig3d-widgets'),
            'selectors'   => [
                '{{WRAPPER}} .zig-gallery__thumb' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->add_responsive_control('thumb_padding', [
            'label'       => __('پدینگ داخلی کارت', 'zig3d-widgets'),
            'type'        => Controls_Manager::DIMENSIONS,
            'size_units'  => ['px', 'em', '%'],
            'default'     => ['top' => '10', 'right' => '10', 'bottom' => '10', 'left' => '10', 'unit' => 'px', 'isLinked' => true],
            'description' => __('فاصله تصویر از لبه‌های کارت — برای ظاهر کارتی.', 'zig3d-widgets'),
            'selectors'   => [
                '{{WRAPPER}} .zig-gallery__thumb' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->add_control('thumb_position', [
            'label'     => __('نقطه برش کاور', 'zig3d-widgets'),
            'type'      => Controls_Manager::SELECT,
            'default'   => 'center center',
            'options'   => [
                'center center' => __('وسط', 'zig3d-widgets'),
                'top center'    => __('بالا', 'zig3d-widgets'),
                'bottom center' => __('پایین', 'zig3d-widgets'),
                'center right'  => __('راست', 'zig3d-widgets'),
                'center left'   => __('چپ', 'zig3d-widgets'),
            ],
            'selectors' => [
                '{{WRAPPER}} .zig-gallery__thumb img' => 'object-position: {{VALUE}};',
            ],
        ]);

        $this->add_group_control(Group_Control_Border::get_type(), [
            'name'     => 'thumb_border',
            'label'    => __('حاشیه', 'zig3d-widgets'),
            'selector' => '{{WRAPPER}} .zig-gallery__thumb',
        ]);

        $this->add_responsive_control('thumb_radius', [
            'label'      => __('رادیوس کارت', 'zig3d-widgets'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%'],
            'default'    => ['top' => '16', 'right' => '16', 'bottom' => '16', 'left' => '16', 'unit' => 'px', 'isLinked' => true],
            'selectors'  => [
                '{{WRAPPER}} .zig-gallery__thumb' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}; overflow: hidden;',
            ],
        ]);

        $this->add_responsive_control('thumb_img_radius', [
            'label'       => __('رادیوس تصویر', 'zig3d-widgets'),
            'type'        => Controls_Manager::DIMENSIONS,
            'size_units'  => ['px', '%'],
            'description' => __('وقتی پدینگِ کارت داده‌اید و تصویر کوچک‌تر از کارت است، رادیوسِ خودِ تصویر را جدا تنظیم کنید.', 'zig3d-widgets'),
            'selectors'   => [
                '{{WRAPPER}} .zig-gallery__thumb img' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->add_control('thumb_opacity', [
            'label'     => __('شفافیت عادی', 'zig3d-widgets'),
            'type'      => Controls_Manager::SLIDER,
            'range'     => ['px' => ['min' => 0.1, 'max' => 1, 'step' => 0.05]],
            'selectors' => [
                '{{WRAPPER}} .zig-gallery__thumb img' => 'opacity: {{SIZE}};',
            ],
        ]);

        $this->add_control('thumb_hover_opacity', [
            'label'     => __('شفافیت در هاور', 'zig3d-widgets'),
            'type'      => Controls_Manager::SLIDER,
            'range'     => ['px' => ['min' => 0.1, 'max' => 1, 'step' => 0.05]],
            'selectors' => [
                '{{WRAPPER}} .zig-gallery__thumb:hover img' => 'opacity: {{SIZE}};',
            ],
        ]);

        $this->add_control('thumb_active_opacity', [
            'label'       => __('شفافیت در حالت فعال', 'zig3d-widgets'),
            'type'        => Controls_Manager::SLIDER,
            'range'       => ['px' => ['min' => 0.1, 'max' => 1, 'step' => 0.05]],
            'description' => __('لحظهٔ کلیک (‎:active‎).', 'zig3d-widgets'),
            'selectors'   => [
                '{{WRAPPER}} .zig-gallery__thumb:active img' => 'opacity: {{SIZE}};',
            ],
        ]);

        $this->add_control('thumb_hover_border_color', [
            'label'     => __('رنگ حاشیه در هاور', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .zig-gallery__thumb:hover' => 'border-color: {{VALUE}};',
            ],
        ]);

        /*
         * «فعال» یعنی همین تامبنیل الان رویِ تصویرِ شاخص نمایش داده می‌شود —
         * وضعیتش را جاوااسکریپتِ فلش‌هایِ ناوبری (نه کلیکِ خودِ تامبنیل، که
         * فقط مودال را باز می‌کند) با کلاسِ ‎is-active‎ مدیریت می‌کند.
         */
        $this->add_control('thumb_active_border_color', [
            'label'     => __('رنگ حاشیه در حالت فعال', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .zig-gallery__thumb.is-active' => 'border-color: {{VALUE}};',
            ],
        ]);

        $this->add_group_control(Group_Control_Box_Shadow::get_type(), [
            'name'     => 'thumb_shadow',
            'label'    => __('سایه کارت', 'zig3d-widgets'),
            'selector' => '{{WRAPPER}} .zig-gallery__thumb',
        ]);

        $this->add_control('heading_overlay', [
            'label'     => __('اورلی تصاویر بیشتر', 'zig3d-widgets'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_control('overlay_color', [
            'label'     => __('رنگ اورلی', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'default'   => 'rgba(16, 24, 40, 0.6)',
            'selectors' => [
                '{{WRAPPER}} .zig-gallery__more-overlay' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->add_control('dots_color', [
            'label'     => __('رنگ سه‌نقطه', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#ffffff',
            'selectors' => [
                '{{WRAPPER}} .zig-gallery__dots span' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->add_responsive_control('dots_size', [
            'label'      => __('اندازه سه‌نقطه', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 2, 'max' => 20]],
            'default'    => ['size' => 6, 'unit' => 'px'],
            'selectors'  => [
                '{{WRAPPER}} .zig-gallery__dots span' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
            ],
            'condition'  => ['show_dots' => 'yes'],
        ]);

        $this->add_control('dots_direction', [
            'label'     => __('جهت سه‌نقطه', 'zig3d-widgets'),
            'type'      => Controls_Manager::SELECT,
            'default'   => 'row',
            'options'   => [
                'row'    => __('افقی', 'zig3d-widgets'),
                'column' => __('عمودی', 'zig3d-widgets'),
            ],
            'selectors' => [
                '{{WRAPPER}} .zig-gallery__dots' => 'flex-direction: {{VALUE}};',
            ],
            'condition' => ['show_dots' => 'yes'],
        ]);

        $this->add_responsive_control('dots_gap', [
            'label'      => __('فاصله نقطه‌ها', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 1, 'max' => 24]],
            'selectors'  => [
                '{{WRAPPER}} .zig-gallery__dots' => 'gap: {{SIZE}}{{UNIT}};',
            ],
            'condition'  => ['show_dots' => 'yes'],
        ]);

        $this->add_responsive_control('more_blur', [
            'label'       => __('بلور تصویر آخر', 'zig3d-widgets'),
            'type'        => Controls_Manager::SLIDER,
            'size_units'  => ['px'],
            'range'       => ['px' => ['min' => 0, 'max' => 20]],
            'description' => __('محو کردن تصویر تامبنیل «تصاویر بیشتر».', 'zig3d-widgets'),
            'selectors'   => [
                '{{WRAPPER}} .zig-gallery__thumb--more img' => 'filter: blur({{SIZE}}{{UNIT}});',
            ],
        ]);

        /* ---------------- بج +N ---------------- */
        $this->add_control('heading_more_count', [
            'label'     => __('بج تعداد (+N)', 'zig3d-widgets'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
            'condition' => ['show_more_count' => 'yes'],
        ]);

        $this->add_control('more_count_position', [
            'label'     => __('موقعیت', 'zig3d-widgets'),
            'type'      => Controls_Manager::SELECT,
            'default'   => 'top-start',
            'options'   => [
                'top-start'    => __('بالا - ابتدا', 'zig3d-widgets'),
                'top-end'      => __('بالا - انتها', 'zig3d-widgets'),
                'bottom-start' => __('پایین - ابتدا', 'zig3d-widgets'),
                'bottom-end'   => __('پایین - انتها', 'zig3d-widgets'),
                'center'       => __('وسط', 'zig3d-widgets'),
            ],
            'condition' => ['show_more_count' => 'yes'],
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'      => 'more_count_typography',
            'selector'  => '{{WRAPPER}} .zig-gallery__more-count',
            'condition' => ['show_more_count' => 'yes'],
        ]);

        $this->add_control('more_count_color', [
            'label'     => __('رنگ متن', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .zig-gallery__more-count' => 'color: {{VALUE}};',
            ],
            'condition' => ['show_more_count' => 'yes'],
        ]);

        $this->add_control('more_count_bg', [
            'label'     => __('رنگ پس‌زمینه', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .zig-gallery__more-count' => 'background-color: {{VALUE}};',
            ],
            'condition' => ['show_more_count' => 'yes'],
        ]);

        $this->add_responsive_control('more_count_padding', [
            'label'      => __('پدینگ', 'zig3d-widgets'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', 'em'],
            'selectors'  => [
                '{{WRAPPER}} .zig-gallery__more-count' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
            'condition'  => ['show_more_count' => 'yes'],
        ]);

        $this->add_responsive_control('more_count_radius', [
            'label'      => __('رادیوس', 'zig3d-widgets'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%'],
            'selectors'  => [
                '{{WRAPPER}} .zig-gallery__more-count' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
            'condition'  => ['show_more_count' => 'yes'],
        ]);

        /* ---------------- نشانگر موبایل ---------------- */
        $this->add_control('heading_counter', [
            'label'     => __('نشانگر گالری موبایل', 'zig3d-widgets'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
            'condition' => ['show_mobile_counter' => 'yes', 'mobile_layout' => 'alternating'],
        ]);

        $this->add_control('counter_bg', [
            'label'     => __('رنگ پس‌زمینه پیل', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .zig-gallery__counter' => 'background-color: {{VALUE}};',
            ],
            'condition' => ['show_mobile_counter' => 'yes', 'mobile_layout' => 'alternating'],
        ]);

        $this->add_control('counter_color', [
            'label'     => __('رنگ متن و آیکون', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .zig-gallery__counter' => 'color: {{VALUE}};',
            ],
            'condition' => ['show_mobile_counter' => 'yes', 'mobile_layout' => 'alternating'],
        ]);

        $this->add_control('counter_track_color', [
            'label'     => __('رنگ زمینه نوار پیشرفت', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .zig-gallery__counter-bar' => 'background-color: {{VALUE}};',
            ],
            'condition' => ['show_mobile_counter' => 'yes', 'mobile_layout' => 'alternating'],
        ]);

        $this->add_control('counter_fill_color', [
            'label'     => __('رنگ نوار پیشرفت', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .zig-gallery__counter-fill' => 'background-color: {{VALUE}};',
            ],
            'condition' => ['show_mobile_counter' => 'yes', 'mobile_layout' => 'alternating'],
        ]);

        $this->end_controls_section();
    }

    /** استایل — مودال */
    private function register_modal_style_controls(): void {
        $this->start_controls_section('section_style_modal', [
            'label'     => __('مودال', 'zig3d-widgets'),
            'tab'       => Controls_Manager::TAB_STYLE,
            'condition' => ['modal_enable' => 'yes'],
        ]);

        $this->add_control('modal_backdrop', [
            'label'     => __('رنگ پس‌زمینه مودال', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'default'   => 'rgba(255, 255, 255, 0.98)',
            'selectors' => [
                '{{WRAPPER}} .zig-gallery-modal' => 'background-color: {{VALUE}};',
            ],
        ]);

        /* ---------------- تب بالا ---------------- */
        $this->add_control('heading_modal_tab', [
            'label'     => __('تب بالا', 'zig3d-widgets'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
            'condition' => ['show_tab' => 'yes'],
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'      => 'modal_tab_typography',
            'selector'  => '{{WRAPPER}} .zig-gallery-modal__tab',
            'condition' => ['show_tab' => 'yes'],
        ]);

        $this->add_control('modal_tab_color', [
            'label'     => __('رنگ متن', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .zig-gallery-modal__tab' => 'color: {{VALUE}};',
            ],
            'condition' => ['show_tab' => 'yes'],
        ]);

        $this->add_control('modal_tab_bg', [
            'label'     => __('رنگ پس‌زمینه', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .zig-gallery-modal__tab' => 'background-color: {{VALUE}};',
            ],
            'condition' => ['show_tab' => 'yes'],
        ]);

        /* ---------------- دکمه بستن ---------------- */
        $this->add_control('heading_modal_close', [
            'label'     => __('دکمه بستن', 'zig3d-widgets'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_responsive_control('close_size', [
            'label'      => __('اندازه', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 16, 'max' => 80]],
            'selectors'  => [
                '{{WRAPPER}} .zig-gallery-modal__close' => 'font-size: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_control('close_color', [
            'label'     => __('رنگ', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .zig-gallery-modal__close' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_control('close_color_hover', [
            'label'     => __('رنگ در هاور', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .zig-gallery-modal__close:hover' => 'color: {{VALUE}};',
            ],
        ]);

        /* ---------------- فلش‌های مودال ---------------- */
        $this->add_control('heading_modal_nav', [
            'label'     => __('فلش‌های قبلی/بعدی', 'zig3d-widgets'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_responsive_control('nav_size', [
            'label'      => __('اندازه دکمه', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 24, 'max' => 96]],
            'selectors'  => [
                '{{WRAPPER}} .zig-gallery-modal__nav' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_control('nav_color', [
            'label'     => __('رنگ فلش', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .zig-gallery-modal__nav' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_control('nav_bg', [
            'label'     => __('رنگ پس‌زمینه', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .zig-gallery-modal__nav' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->add_control('nav_color_hover', [
            'label'     => __('رنگ فلش در هاور', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .zig-gallery-modal__nav:hover' => 'color: {{VALUE}};',
            ],
        ]);

        $this->add_control('nav_bg_hover', [
            'label'     => __('رنگ پس‌زمینه در هاور', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [
                '{{WRAPPER}} .zig-gallery-modal__nav:hover' => 'background-color: {{VALUE}};',
            ],
        ]);

        $this->add_responsive_control('nav_radius', [
            'label'      => __('رادیوس', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', '%'],
            'range'      => [
                'px' => ['min' => 0, 'max' => 50],
                '%'  => ['min' => 0, 'max' => 50],
            ],
            'selectors'  => [
                '{{WRAPPER}} .zig-gallery-modal__nav' => 'border-radius: {{SIZE}}{{UNIT}};',
            ],
        ]);

        /* ---------------- تصویر مودال ---------------- */
        $this->add_control('heading_modal_image', [
            'label'     => __('تصویر بزرگ', 'zig3d-widgets'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_responsive_control('modal_img_height', [
            'label'     => __('حداکثر ارتفاع (نسبت به صفحه)', 'zig3d-widgets'),
            'type'      => Controls_Manager::SLIDER,
            'size_units' => ['vh'],
            'range'      => ['vh' => ['min' => 30, 'max' => 95]],
            'default'    => ['size' => 72, 'unit' => 'vh'],
            'selectors'  => [
                '{{WRAPPER}} .zig-gallery-modal__img' => 'max-height: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('modal_img_radius', [
            'label'      => __('رادیوس', 'zig3d-widgets'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%'],
            'selectors'  => [
                '{{WRAPPER}} .zig-gallery-modal__img' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        /* ---------------- نوار تامبنیل مودال ---------------- */
        $this->add_control('heading_modal_strip', [
            'label'     => __('نوار تامبنیل پایین', 'zig3d-widgets'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_responsive_control('strip_size', [
            'label'      => __('اندازه تامبنیل', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 32, 'max' => 160]],
            'default'    => ['size' => 64, 'unit' => 'px'],
            'selectors'  => [
                '{{WRAPPER}} .zig-gallery-modal__strip img' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('strip_gap', [
            'label'      => __('فاصله', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 40]],
            'selectors'  => [
                '{{WRAPPER}} .zig-gallery-modal__strip' => 'gap: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('strip_radius', [
            'label'      => __('رادیوس تامبنیل', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', '%'],
            'range'      => [
                'px' => ['min' => 0, 'max' => 40],
                '%'  => ['min' => 0, 'max' => 50],
            ],
            'selectors'  => [
                '{{WRAPPER}} .zig-gallery-modal__strip img' => 'border-radius: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_control('strip_active_color', [
            'label'     => __('رنگ کادر تامبنیل فعال', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#1a2b4a',
            'selectors' => [
                '{{WRAPPER}} .zig-gallery-modal__strip .is-active img' => 'border-color: {{VALUE}};',
            ],
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * رندر
     * =================================================================== */

    protected function render(): void {
        if (!function_exists('wc_get_product')) {
            $this->editor_notice(__('این ویجت به ووکامرس فعال نیاز دارد.', 'zig3d-widgets'));

            return;
        }

        $settings  = $this->get_settings_for_display();
        $is_editor = $this->is_editing();
        $product   = $this->resolve_product($settings, $is_editor);

        if (!$product) {
            $this->editor_notice(__('محصولی پیدا نشد. شناسهٔ محصول را وارد کنید یا ویجت را داخل صفحه/قالب محصول بگذارید.', 'zig3d-widgets'));

            return;
        }

        $main_id     = (int) $product->get_image_id();
        $gallery_ids = array_map('intval', $product->get_gallery_image_ids());

        if (!$main_id && empty($gallery_ids)) {
            $this->editor_notice(__('این محصول تصویر شاخص یا گالری ندارد.', 'zig3d-widgets'));

            return;
        }

        // اگر تصویر شاخص نبود، اولین تصویر گالری جایش را بگیرد
        if (!$main_id) {
            $main_id = array_shift($gallery_ids);
        }

        $count     = max(1, (int) ($settings['thumbs_count'] ?? 4));
        $remaining = max(0, count($gallery_ids) - $count);
        $total     = 1 + count($gallery_ids);

        $modal_enabled  = 'yes' === $settings['modal_enable'];
        $mobile_layout  = (string) ($settings['mobile_layout'] ?? 'alternating');
        $anim           = (string) ($settings['modal_animation'] ?? 'fade-scale');
        $anim_whitelist = ['fade', 'fade-scale', 'slide-up', 'zoom'];
        if (!in_array($anim, $anim_whitelist, true)) {
            $anim = 'fade-scale';
        }
        $trigger_tag = $modal_enabled ? 'button' : 'div';

        /*
         * فلش‌های ناوبری، تصویر شاخص را با کلیک عوض می‌کنند — کاری جدا از
         * بازکردنِ مودال. اگر تصویرِ شاخص هم برایِ باز کردنِ مودال کلیک‌پذیر
         * باشد و هم فلش داخلش باشد، تگش نمی‌تواند ‎<button>‎ بماند: تودرتوییِ
         * ‎<button>‎ داخلِ ‎<button>‎ در HTML نامعتبر است. پس فقط در همین یک
         * حالت (مودال روشن و فلش هم روشن)، ‎<div role="button" tabindex="0">‎
         * جای ‎<button>‎ را می‌گیرد — قابل‌کلیک و قابل‌فوکوس هنوز هست، فقط
         * تگش عوض شده. تامبنیل‌ها این مشکل را ندارند، همان ‎<button>‎ی قبلی‌اند.
         */
        $show_main_nav = 'yes' === ($settings['show_main_nav'] ?? 'yes') && $total > 1;
        $main_tag      = ($modal_enabled && !$show_main_nav) ? 'button' : 'div';
        $main_attrs    = '';

        if ($modal_enabled) {
            $main_attrs = ('button' === $main_tag ? ' type="button"' : ' role="button" tabindex="0"')
                . ' data-index="0" aria-label="' . esc_attr__('بزرگ‌نمایی تصویر', 'zig3d-widgets') . '"';
        }

        $this->add_render_attribute('wrapper', 'class', [
            'zig-gallery',
            'zig-gallery--mobile-' . ('match' === $mobile_layout ? 'match' : 'alt'),
        ]);

        if ($modal_enabled) {
            /*
             * پارامتر v از زمان آخرین ویرایش محصول ساخته می‌شود؛ با هر آپدیت
             * محصول URL عوض و کش مرورگر/CDN خودکار باطل می‌شود (cache-busting).
             */
            $modified = $product->get_date_modified();
            $endpoint = add_query_arg(
                'v',
                $modified ? $modified->getTimestamp() : 0,
                rest_url('zig3d/v1/product-gallery/' . $product->get_id())
            );

            $this->add_render_attribute('wrapper', [
                'data-endpoint' => esc_url_raw($endpoint),
                'data-total'    => (string) $total,
            ]);
        }

        ?>
        <div <?php $this->print_render_attribute_string('wrapper'); ?>>

            <div class="zig-gallery__strip">

            <<?php echo $main_tag; // phpcs:ignore ?> class="zig-gallery__main"<?php echo $main_attrs; // phpcs:ignore ?><?php if ($show_main_nav) : ?> data-large="<?php echo esc_url(wp_get_attachment_image_url($main_id, 'large') ?: ''); ?>" data-alt="<?php echo esc_attr(get_post_meta($main_id, '_wp_attachment_image_alt', true)); ?>"<?php endif; ?>>
                <?php echo wp_get_attachment_image($main_id, 'large', false, ['loading' => 'eager']); ?>
                <?php if ($show_main_nav) : ?>
                    <button type="button" class="zig-gallery__nav zig-gallery__nav--prev" aria-label="<?php echo esc_attr__('تصویر قبلی', 'zig3d-widgets'); ?>">
                        <?php echo $this->render_main_nav_icon($settings, 'main_nav_prev_icon', 'm9 6 6 6-6 6'); // phpcs:ignore WordPress.Security.EscapeOutput -- در render_main_nav_icon اسکیپ شده ?>
                    </button>
                    <button type="button" class="zig-gallery__nav zig-gallery__nav--next" aria-label="<?php echo esc_attr__('تصویر بعدی', 'zig3d-widgets'); ?>">
                        <?php echo $this->render_main_nav_icon($settings, 'main_nav_next_icon', 'm15 6-6 6 6 6'); // phpcs:ignore WordPress.Security.EscapeOutput -- در render_main_nav_icon اسکیپ شده ?>
                    </button>
                <?php endif; ?>
            </<?php echo $main_tag; // phpcs:ignore ?>>

            <?php
            /*
             * تمام تصاویر گالری به صورت flat فرزند مستقیم .zig-gallery__strip
             * رندر می‌شوند — دسکتاپ: گرید N ستونه، فقط N تای اول نمایش، بقیه
             * با --extra مخفی. موبایل (یکی‌درمیون): نوار اسکرول افقی.
             */
            foreach ($gallery_ids as $i => $attachment_id) :
                $data_index   = $i + 1;
                $is_visible   = $i < $count;
                $is_last_more = $is_visible && $i === $count - 1 && $remaining > 0;

                $classes = ['zig-gallery__thumb'];
                if (!$is_visible) {
                    $classes[] = 'zig-gallery__thumb--extra';
                }
                if ($is_last_more) {
                    $classes[] = 'zig-gallery__thumb--more';
                }
                ?>
                <<?php echo $trigger_tag; // phpcs:ignore ?> class="<?php echo esc_attr(implode(' ', $classes)); ?>" <?php echo $modal_enabled ? 'type="button" data-index="' . esc_attr($data_index) . '" aria-label="' . esc_attr__('بزرگ‌نمایی تصویر', 'zig3d-widgets') . '"' : ''; ?><?php if ($show_main_nav) : ?> data-large="<?php echo esc_url(wp_get_attachment_image_url($attachment_id, 'large') ?: ''); ?>" data-alt="<?php echo esc_attr(get_post_meta($attachment_id, '_wp_attachment_image_alt', true)); ?>"<?php endif; ?>>
                    <?php echo wp_get_attachment_image($attachment_id, 'medium', false, ['loading' => 'lazy']); ?>
                    <?php if ($is_last_more) : ?>
                        <span class="zig-gallery__more-overlay" aria-hidden="true">
                            <?php if ('yes' === $settings['show_more_count']) :
                                $position_whitelist = ['top-start', 'top-end', 'bottom-start', 'bottom-end', 'center'];
                                $badge_position = in_array($settings['more_count_position'] ?? '', $position_whitelist, true) ? $settings['more_count_position'] : 'top-start';
                                ?>
                                <span class="zig-gallery__more-count zig-gallery__more-count--<?php echo esc_attr($badge_position); ?>">+<?php echo esc_html($remaining); ?></span>
                            <?php endif; ?>
                            <?php if ('yes' === $settings['show_dots']) : ?>
                                <span class="zig-gallery__dots"><span></span><span></span><span></span></span>
                            <?php endif; ?>
                        </span>
                    <?php endif; ?>
                </<?php echo $trigger_tag; // phpcs:ignore ?>>
            <?php endforeach; ?>

            </div><!-- /.zig-gallery__strip -->

            <?php if ('alternating' === $mobile_layout && 'yes' === ($settings['show_mobile_counter'] ?? 'yes')) : ?>
                <div class="zig-gallery__counter" aria-hidden="true">
                    <span class="zig-gallery__counter-bar"><span class="zig-gallery__counter-fill"></span></span>
                    <span class="zig-gallery__counter-num"><?php echo esc_html(number_format_i18n($total)); ?></span>
                    <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.5-3.5a2 2 0 0 0-2.8 0L7 19"/></svg>
                </div>
            <?php endif; ?>

            <?php if ($modal_enabled) : ?>
                <div class="zig-gallery-modal zig-gallery-modal--anim-<?php echo esc_attr($anim); ?>" role="dialog" aria-modal="true" aria-hidden="true" aria-label="<?php echo esc_attr__('گالری تصاویر محصول', 'zig3d-widgets'); ?>">
                    <button type="button" class="zig-gallery-modal__close" aria-label="<?php echo esc_attr__('بستن', 'zig3d-widgets'); ?>">
                        <svg viewBox="0 0 24 24" width="1em" height="1em" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
                    </button>

                    <?php if ('yes' === $settings['show_tab'] && '' !== $settings['tab_label']) : ?>
                        <div class="zig-gallery-modal__tab"><?php echo esc_html($settings['tab_label']); ?></div>
                    <?php endif; ?>

                    <div class="zig-gallery-modal__stage">
                        <button type="button" class="zig-gallery-modal__nav zig-gallery-modal__nav--prev" aria-label="<?php echo esc_attr__('تصویر قبلی', 'zig3d-widgets'); ?>">
                            <svg viewBox="0 0 24 24" width="1.2em" height="1.2em" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 6 6 6-6 6"/></svg>
                        </button>

                        <div class="zig-gallery-modal__imgwrap">
                            <span class="zig-gallery-modal__spinner" hidden></span>
                            <img class="zig-gallery-modal__img" alt="">
                        </div>

                        <button type="button" class="zig-gallery-modal__nav zig-gallery-modal__nav--next" aria-label="<?php echo esc_attr__('تصویر بعدی', 'zig3d-widgets'); ?>">
                            <svg viewBox="0 0 24 24" width="1.2em" height="1.2em" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 6-6 6 6 6"/></svg>
                        </button>
                    </div>

                    <div class="zig-gallery-modal__strip" role="tablist"></div>
                </div>
            <?php endif; ?>

        </div>
        <?php
    }

    /**
     * تعیینِ محصول.
     *
     * منبعِ اصلی (‎Traits\Intro_Row::resolve_product()‎) شناسهٔ دستی ندارد؛
     * همیشه محصولِ لوپِ جاری را می‌گیرد و در ادیتور آخرین محصولِ منتشرشده
     * را پیش‌نمایش می‌دهد. اینجا هر دو رفتار حفظ شده: اول همان زنجیرهٔ
     * منعطفِ ‎Price::resolve()‎ (شناسهٔ دستی، محصولِ سراسری، کوئریِ صفحهٔ
     * محصول) که بقیهٔ ویجت‌های این افزونه هم دارند، و فقط اگر آن هم چیزی
     * پیدا نکرد و در ادیتور/پیش‌نمایش هستیم، به آخرین محصول برمی‌گردیم —
     * تا کشیدنِ ویجت روی صفحهٔ خالی هم چیزی برای نشان‌دادن داشته باشد.
     */
    private function resolve_product(array $settings, bool $is_editor) {
        $product = Price::resolve(absint($settings['product_id'] ?? 0));

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
     * آیکونِ یکی از فلش‌هایِ ناوبری — کنترلِ ‎ICONS‎ی کاربر اگر ست شده باشد،
     * وگرنه همان فلشِ ساده‌ای که خودِ مودال هم برایِ ناوبری‌اش دارد
     * (‎render()‎، دکمه‌هایِ ‎zig-gallery-modal__nav‎) — یک زبانِ بصریِ واحد
     * برایِ هر دو ناوبری، تا کاربر بدونِ ست‌کردنِ چیزی هم یک فلشِ معنادار ببیند.
     */
    private function render_main_nav_icon(array $settings, string $control_key, string $fallback_path): string {
        $icon = $settings[$control_key] ?? [];

        if (!empty($icon['value'])) {
            ob_start();
            Icons_Manager::render_icon($icon, ['aria-hidden' => 'true']);

            return (string) ob_get_clean();
        }

        return sprintf(
            '<svg viewBox="0 0 24 24" width="1em" height="1em" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="%s"/></svg>',
            esc_attr($fallback_path)
        );
    }

    /**
     * پیام راهنما، فقط داخل ادیتور — در سایت هیچ‌چیز چاپ نمی‌شود.
     */
    private function editor_notice(string $message): void {
        if (!$this->is_editing()) {
            return;
        }

        printf('<div class="zig-gallery__notice">%s</div>', esc_html($message));
    }

    private function is_editing(): bool {
        return class_exists('\Elementor\Plugin')
            && isset(\Elementor\Plugin::$instance->editor)
            && \Elementor\Plugin::$instance->editor->is_edit_mode();
    }
}
