<?php
namespace Zig3d_Widgets\Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;
use Zig3d_Widgets\Feature_Repeater;
use Zig3d_Widgets\Markup;
use Zig3d_Widgets\Plugin;
use Zig3d_Widgets\Price;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * قابلیت‌های محصول، تب‌به‌تب — دادهٔ ریپیترِ JetEngine (‎feature_showcase‎)
 * روی خودِ محصول، نه چیزی که این ویجت بسازد یا مدیریت کند.
 *
 *     div.zig-feature
 *       div.zig-feature__intro                فقط اگر یکی از فیلدهای معرفی پر باشد
 *         span.zig-feature__kicker
 *         h2.zig-feature__intro-title
 *         p.zig-feature__intro-desc
 *       div.zig-feature__nav                   فقط اگر بیش از یک قابلیتِ معتبر باشد
 *         button.zig-feature__nav-arrow--prev
 *         div.zig-feature__tablist[role=tablist]
 *           button.zig-feature__tab[role=tab] × N
 *         button.zig-feature__nav-arrow--next
 *       div.zig-feature__panels
 *         div.zig-feature__panel[role=tabpanel] × N
 *           span.zig-feature__glow--content     فقط اگر Glowِ محلی روشن باشد
 *           span.zig-feature__glow--media       فقط اگر Glow روشن *و* تصویر داشته باشد
 *           div.zig-feature__media              فقط اگر تصویر داشته باشد
 *             img
 *           div.zig-feature__content
 *             div.zig-feature__meta              فقط اگر ایندکس یا لیبل نمایشی باشد
 *               span.zig-feature__index
 *               span.zig-feature__label
 *             h3.zig-feature__feature-title      فقط اگر عنوان پر و نمایشی باشد
 *             p.zig-feature__feature-desc        فقط اگر توضیح پر و نمایشی باشد
 *
 * چرا دادهٔ ریپیتر جدا از رندر است (‎Feature_Repeater‎): همان مرزی که
 * ‎Spec_Value‎ و کارتِ محصول هم دارند — «این محصول چه دارد» از «چطور چاپ
 * شود». اینجا هیچ HTMLای در آن کلاس نیست؛ فقط خواندنِ متا، نرمال‌سازیِ
 * فرمت‌هایِ فیلدِ تصویرِ JetEngine، و فیلترِ سطرهایِ بی‌لیبل.
 *
 * چرا Tab، نه Accordion یا Carousel: خودِ درخواست صریح است — بدونِ
 * autoplay، بدونِ اسلایدِ بزرگ. الگوی واقعیِ ARIA Tabs با فعال‌سازیِ
 * دستی پیاده شده: پیکان‌های چپ/راست فقط فوکوس را جابه‌جا می‌کنند،
 * Enter/Space انتخاب را عوض می‌کند — هم برای این‌که در Roving Tabindex
 * فقط یک تب هر بار در توالیِ Tab است، هم چون خودِ درخواست این دو کلید را
 * جدا از پیکان‌ها فهرست کرده.
 *
 * چرا این ویجت هیچ‌وقت پس‌زمینهٔ سکشن نمی‌سازد: ‎.zig-feature‎ِ ریشه
 * transparent است و هیچ background/padding/max-width/دکورِ سکشنی روی
 * خودش ندارد — قبلاً (نسخه‌های پیشین) داشت و دقیقاً همین باعث می‌شد
 * ویجت رویِ هر سکشنی مثلِ یک جعبهٔ تیرهٔ کامل بیفتد. سکشن، پس‌زمینه،
 * فاصله و گردیِ *بیرونی* را المنتور (Advanced tabِ خودِ ویجت یا خودِ
 * سکشن) می‌دهد؛ این‌جا فقط تب‌ها و پنلِ داخلی self-contained استایل
 * دارند.
 */
final class Product_Feature_Showcase extends Widget_Base {

    public function get_name(): string {
        return 'zig3d-feature-showcase';
    }

    public function get_title(): string {
        return __('نمایشِ قابلیت‌های محصول', 'zig3d-widgets');
    }

    public function get_icon(): string {
        return 'eicon-tabs';
    }

    public function get_categories(): array {
        return [Plugin::CATEGORY];
    }

    public function get_keywords(): array {
        return ['features', 'tabs', 'showcase', 'jetengine', 'repeater', 'قابلیت', 'تب', 'محصول'];
    }

    public function get_style_depends(): array {
        return ['zig3d-widgets'];
    }

    /**
     * بدونِ این اسکریپت هم قابلیتِ اول کامل و قابل‌خواندن است — همهٔ
     * پنل‌ها در HTML هستند، فقط پنل‌هایِ غیرِفعال با ‎hidden‎ پنهان‌اند و
     * بدونِ جاوااسکریپت راهی برایِ باز کردنشان نیست. فایل فقط سوییچِ
     * فعال/غیرفعال، کیبورد، ناوبریِ سرریز، و انیمیشن را رویش سوار می‌کند.
     */
    public function get_script_depends(): array {
        return ['zig3d-feature-showcase'];
    }

    public function has_widget_inner_wrapper(): bool {
        return false;
    }

    protected function register_controls(): void {
        $this->register_intro_section();
        $this->register_visibility_section();
        $this->register_navigation_section();
        $this->register_editor_section();

        $this->register_intro_style_section();
        $this->register_tabs_style_section();
        $this->register_panel_style_section();
        $this->register_media_style_section();
        $this->register_content_style_section();
        $this->register_glow_style_section();
        $this->register_motion_style_section();
    }

    /* =====================================================================
     * محتوا
     * =================================================================== */

    private function register_intro_section(): void {
        $this->start_controls_section(
            'intro_section',
            ['label' => __('معرفیِ بخش', 'zig3d-widgets')]
        );

        $this->add_control(
            'intro_kicker',
            [
                'label'       => __('چشمک/Kicker', 'zig3d-widgets'),
                'type'        => Controls_Manager::TEXT,
                'default'     => 'PRODUCT CAPABILITIES',
                'dynamic'     => ['active' => true],
                'description' => __('خالی بگذارید تا اصلاً چاپ نشود.', 'zig3d-widgets'),
            ]
        );

        $this->add_control(
            'intro_title',
            [
                'label'   => __('عنوانِ بخش', 'zig3d-widgets'),
                'type'    => Controls_Manager::TEXT,
                'default' => __('قابلیت‌های کلیدی دستگاه', 'zig3d-widgets'),
                'dynamic' => ['active' => true],
            ]
        );

        $this->add_control(
            'intro_description',
            [
                'label'       => __('توضیحِ بخش', 'zig3d-widgets'),
                'type'        => Controls_Manager::TEXTAREA,
                'default'     => __('شش قابلیت عملیاتی که روی دقت، زمان تولید و نگهداری روزانه اثر مستقیم دارند.', 'zig3d-widgets'),
                'dynamic'     => ['active' => true],
                'description' => __('متنِ ثابت است، به تعدادِ واقعیِ قابلیت‌ها وابسته نیست — اگر تعداد فرق دارد، همین‌جا دستی ویرایش کنید.', 'zig3d-widgets'),
            ]
        );

        $this->end_controls_section();
    }

    private function register_visibility_section(): void {
        $this->start_controls_section(
            'content_visibility_section',
            ['label' => __('محتوایِ قابلیت', 'zig3d-widgets')]
        );

        $this->add_control(
            'show_index',
            [
                'label'   => __('نمایشِ شماره', 'zig3d-widgets'),
                'type'    => Controls_Manager::SWITCHER,
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_label',
            [
                'label'   => __('نمایشِ برچسب', 'zig3d-widgets'),
                'type'    => Controls_Manager::SWITCHER,
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_title',
            [
                'label'   => __('نمایشِ عنوان', 'zig3d-widgets'),
                'type'    => Controls_Manager::SWITCHER,
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_description',
            [
                'label'   => __('نمایشِ توضیح', 'zig3d-widgets'),
                'type'    => Controls_Manager::SWITCHER,
                'default' => 'yes',
            ]
        );

        $this->end_controls_section();
    }

    private function register_navigation_section(): void {
        $this->start_controls_section(
            'navigation_section',
            ['label' => __('ناوبری', 'zig3d-widgets')]
        );

        $this->add_control(
            'initial_active_item',
            [
                'label'       => __('قابلیتِ فعالِ اول', 'zig3d-widgets'),
                'type'        => Controls_Manager::NUMBER,
                'min'         => 1,
                'default'     => 1,
                'description' => __('شمارهٔ قابلیت (از ۱)؛ اگر از تعدادِ قابلیت‌هایِ معتبر بیشتر باشد، به اولی برمی‌گردد.', 'zig3d-widgets'),
            ]
        );

        $this->add_control(
            'show_overflow_arrows',
            [
                'label'       => __('پیکان‌هایِ ناوبری هنگامِ سرریز', 'zig3d-widgets'),
                'type'        => Controls_Manager::SWITCHER,
                'default'     => 'yes',
                'description' => __('حتی اگر روشن باشد، فقط وقتی ردیفِ تب‌ها واقعاً سرریز کند نمایش داده می‌شوند.', 'zig3d-widgets'),
            ]
        );

        $this->end_controls_section();
    }

    private function register_editor_section(): void {
        $this->start_controls_section(
            'editor_section',
            ['label' => __('ادیتور', 'zig3d-widgets')]
        );

        $this->add_control(
            'preview_product_id',
            [
                'label'       => __('محصولِ پیش‌نمایش', 'zig3d-widgets'),
                'type'        => Controls_Manager::NUMBER,
                'min'         => 0,
                'default'     => 0,
                'description' => __('فقط داخلِ ادیتورِ المنتور اثر دارد — منبعِ دادهٔ سایتِ واقعی همیشه محصولِ جاری است، نه این مقدار.', 'zig3d-widgets'),
            ]
        );

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل
     * =================================================================== */

    private function register_intro_style_section(): void {
        $this->start_controls_section(
            'fs_intro_style_section',
            [
                'label' => __('معرفی', 'zig3d-widgets'),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'     => 'kicker_typography',
                'label'    => __('تایپوگرافیِ چشمک', 'zig3d-widgets'),
                'selector' => '{{WRAPPER}} .zig-feature__kicker',
            ]
        );

        $this->add_control(
            'kicker_color',
            [
                'label'     => __('رنگِ چشمک', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'default'   => '#A78BFA',
                'selectors' => ['{{WRAPPER}} .zig-feature__kicker' => 'color: {{VALUE}};'],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'      => 'intro_title_typography',
                'label'     => __('تایپوگرافیِ عنوان', 'zig3d-widgets'),
                'selector'  => '{{WRAPPER}} .zig-feature__intro-title',
                'separator' => 'before',
            ]
        );

        $this->add_control(
            'intro_title_color',
            [
                'label'     => __('رنگِ عنوان', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'default'   => '#F5F5F8',
                'selectors' => ['{{WRAPPER}} .zig-feature__intro-title' => 'color: {{VALUE}};'],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'      => 'intro_desc_typography',
                'label'     => __('تایپوگرافیِ توضیح', 'zig3d-widgets'),
                'selector'  => '{{WRAPPER}} .zig-feature__intro-desc',
                'separator' => 'before',
            ]
        );

        $this->add_control(
            'intro_desc_color',
            [
                'label'     => __('رنگِ توضیح', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'default'   => '#9A9CAE',
                'selectors' => ['{{WRAPPER}} .zig-feature__intro-desc' => 'color: {{VALUE}};'],
            ]
        );

        $this->add_responsive_control(
            'intro_spacing',
            [
                'label'      => __('فاصله تا ردیفِ تب‌ها', 'zig3d-widgets'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range'      => ['px' => ['min' => 0, 'max' => 80]],
                'default'    => ['size' => 32, 'unit' => 'px'],
                'separator'  => 'before',
                'selectors'  => ['{{WRAPPER}} .zig-feature__intro' => 'margin-bottom: {{SIZE}}{{UNIT}};'],
            ]
        );

        $this->end_controls_section();
    }

    private function register_tabs_style_section(): void {
        $this->start_controls_section(
            'fs_tabs_style_section',
            [
                'label' => __('تب‌ها', 'zig3d-widgets'),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'     => 'tabs_typography',
                'label'    => __('تایپوگرافی', 'zig3d-widgets'),
                'selector' => '{{WRAPPER}} .zig-feature__tab',
            ]
        );

        $this->add_control(
            'tab_text_color',
            [
                'label'     => __('رنگِ متن', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'default'   => 'rgba(245,245,248,.7)',
                'selectors' => ['{{WRAPPER}} .zig-feature__tab' => 'color: {{VALUE}};'],
            ]
        );

        $this->add_control(
            'tab_bg',
            [
                'label'     => __('پس‌زمینه', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'default'   => '#15151F',
                'selectors' => ['{{WRAPPER}} .zig-feature__tab' => 'background-color: {{VALUE}};'],
            ]
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name'     => 'tab_border',
                'selector' => '{{WRAPPER}} .zig-feature__tab',
            ]
        );

        $this->add_responsive_control(
            'tab_radius',
            [
                'label'      => __('گردیِ گوشه‌ها', 'zig3d-widgets'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range'      => ['px' => ['min' => 0, 'max' => 40]],
                'default'    => ['size' => 12, 'unit' => 'px'],
                'selectors'  => ['{{WRAPPER}} .zig-feature__tab' => 'border-radius: {{SIZE}}{{UNIT}};'],
            ]
        );

        $this->add_responsive_control(
            'tab_height',
            [
                'label'      => __('حداقلِ ارتفاع', 'zig3d-widgets'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range'      => ['px' => ['min' => 28, 'max' => 72]],
                'default'    => ['size' => 44, 'unit' => 'px'],
                'selectors'  => ['{{WRAPPER}} .zig-feature__tab' => 'min-height: {{SIZE}}{{UNIT}};'],
            ]
        );

        $this->add_responsive_control(
            'tab_padding_x',
            [
                'label'      => __('فاصلهٔ داخلیِ افقی', 'zig3d-widgets'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range'      => ['px' => ['min' => 0, 'max' => 48]],
                'default'    => ['size' => 18, 'unit' => 'px'],
                'selectors'  => ['{{WRAPPER}} .zig-feature__tab' => 'padding-inline: {{SIZE}}{{UNIT}};'],
            ]
        );

        $this->add_responsive_control(
            'tabs_gap',
            [
                'label'      => __('فاصلهٔ بینِ تب‌ها', 'zig3d-widgets'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range'      => ['px' => ['min' => 0, 'max' => 32]],
                'default'    => ['size' => 10, 'unit' => 'px'],
                'selectors'  => ['{{WRAPPER}} .zig-feature__tablist' => 'gap: {{SIZE}}{{UNIT}};'],
            ]
        );

        $this->add_control(
            'tabs_hover_heading',
            [
                'label'     => __('هاور', 'zig3d-widgets'),
                'type'      => Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        $this->add_control(
            'tab_hover_bg',
            [
                'label'     => __('پس‌زمینه', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'default'   => '#1C1C2A',
                'selectors' => ['{{WRAPPER}} .zig-feature__tab:hover' => 'background-color: {{VALUE}};'],
            ]
        );

        $this->add_control(
            'tab_hover_text_color',
            [
                'label'     => __('رنگِ متن', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'default'   => '#F5F5F8',
                'selectors' => ['{{WRAPPER}} .zig-feature__tab:hover' => 'color: {{VALUE}};'],
            ]
        );

        $this->add_control(
            'tab_hover_border_color',
            [
                'label'     => __('رنگِ مرز', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => ['{{WRAPPER}} .zig-feature__tab:hover' => 'border-color: {{VALUE}};'],
            ]
        );

        $this->add_control(
            'tabs_active_heading',
            [
                'label'     => __('فعال (انتخاب‌شده)', 'zig3d-widgets'),
                'type'      => Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        /*
         * سلکتورِ فعال عمداً هم کلاس هم ویژگیِ آریا را با هم می‌گیرد
         * (‎.is-active[aria-selected="true"]‎) تا specificityاش از
         * ‎:hover‎ی بالا بیشتر باشد — یعنی وقتی موس رویِ تبِ فعال می‌ماند،
         * ظاهرِ «فعال» برنده است، نه رنگِ عمومیِ هاور؛ بدونِ نیاز به
         * ‎!important‎ یا وابستگی به ترتیبِ ثبتِ کنترل‌ها.
         *
         * پس‌زمینه یک گرادیانِ دورنگه است (نه رنگِ تخت) — همان چیزی که
         * برایِ تبِ فعال خواسته شده. دو کنترلِ رنگِ مستقل، نه یک
         * Group Controlِ گرادیانِ آماده، چون فقط دو نقطهٔ ثابت (شروع/پایان
         * با زاویهٔ ۱۳۵ درجه) لازم است، نه ویرایشگرِ کاملِ چند-Stopِ
         * گرادیان.
         */
        $this->add_control(
            'tab_active_bg_from',
            [
                'label'     => __('پس‌زمینه — شروعِ گرادیان', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'default'   => '#8B5CFF',
                'selectors' => ['{{WRAPPER}} .zig-feature' => '--zig-feature-tab-active-from: {{VALUE}};'],
            ]
        );

        $this->add_control(
            'tab_active_bg_to',
            [
                'label'     => __('پس‌زمینه — پایانِ گرادیان', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'default'   => '#6D3FFF',
                'selectors' => ['{{WRAPPER}} .zig-feature' => '--zig-feature-tab-active-to: {{VALUE}};'],
            ]
        );

        $this->add_control(
            'tab_active_text_color',
            [
                'label'     => __('رنگِ متن', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'default'   => '#FFFFFF',
                'selectors' => ['{{WRAPPER}} .zig-feature__tab.is-active[aria-selected="true"]' => 'color: {{VALUE}};'],
            ]
        );

        $this->add_control(
            'tab_active_border_color',
            [
                'label'       => __('رنگِ مرز', 'zig3d-widgets'),
                'type'        => Controls_Manager::COLOR,
                'description' => __('پیش‌فرض بدونِ مرز است — خودِ گرادیان ظاهرِ فعال را می‌سازد؛ این کنترل فقط برایِ کسی است که رویِ گرادیان هم مرز بخواهد.', 'zig3d-widgets'),
                'selectors'   => ['{{WRAPPER}} .zig-feature__tab.is-active[aria-selected="true"]' => 'border-color: {{VALUE}};'],
            ]
        );

        $this->add_control(
            'tabs_glow_note',
            [
                'type'            => Controls_Manager::RAW_HTML,
                'raw'             => __('شدت/رنگِ glowِ پشتِ تبِ فعال از بخشِ «Glow / جلوه‌ها» می‌آید — همان یک کنترلِ مشترک برایِ همهٔ glowهای ویجت، تا کنترلِ تکراری ساخته نشود.', 'zig3d-widgets'),
                'content_classes' => 'elementor-descriptor',
                'separator'       => 'before',
            ]
        );

        $this->end_controls_section();
    }

    private function register_media_style_section(): void {
        $this->start_controls_section(
            'fs_media_style_section',
            [
                'label' => __('رسانه', 'zig3d-widgets'),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'media_bg',
            [
                'label'     => __('پس‌زمینه', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'default'   => '#F3F3F6',
                'selectors' => ['{{WRAPPER}} .zig-feature__media' => 'background-color: {{VALUE}};'],
            ]
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name'     => 'media_border',
                'selector' => '{{WRAPPER}} .zig-feature__media',
            ]
        );

        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            [
                'name'     => 'media_shadow',
                'label'    => __('سایه', 'zig3d-widgets'),
                'selector' => '{{WRAPPER}} .zig-feature__media',
            ]
        );

        $this->add_responsive_control(
            'media_radius',
            [
                'label'      => __('گردیِ گوشه‌ها', 'zig3d-widgets'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range'      => ['px' => ['min' => 0, 'max' => 48]],
                'default'    => ['size' => 20, 'unit' => 'px'],
                'selectors'  => ['{{WRAPPER}} .zig-feature__media' => 'border-radius: {{SIZE}}{{UNIT}}; overflow: hidden;'],
            ]
        );

        $this->add_responsive_control(
            'media_padding',
            [
                'label'      => __('فاصلهٔ داخلی', 'zig3d-widgets'),
                'type'       => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', 'rem'],
                'default'    => ['top' => '24', 'right' => '24', 'bottom' => '24', 'left' => '24', 'unit' => 'px'],
                'selectors'  => ['{{WRAPPER}} .zig-feature__media' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
            ]
        );

        $this->add_responsive_control(
            'media_img_max_width',
            [
                'label'      => __('حداکثرِ عرضِ تصویر', 'zig3d-widgets'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px', '%'],
                'range'      => ['px' => ['min' => 80, 'max' => 800], '%' => ['min' => 10, 'max' => 100]],
                'default'    => ['size' => 100, 'unit' => '%'],
                'selectors'  => ['{{WRAPPER}} .zig-feature__media img' => 'max-width: {{SIZE}}{{UNIT}};'],
            ]
        );

        $this->add_responsive_control(
            'media_img_max_height',
            [
                'label'      => __('حداکثرِ ارتفاعِ تصویر', 'zig3d-widgets'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range'      => ['px' => ['min' => 100, 'max' => 700]],
                'default'    => ['size' => 360, 'unit' => 'px'],
                'selectors'  => ['{{WRAPPER}} .zig-feature__media img' => 'max-height: {{SIZE}}{{UNIT}};'],
            ]
        );

        $this->add_control(
            'media_object_fit',
            [
                'label'     => __('نوعِ تناسبِ تصویر', 'zig3d-widgets'),
                'type'      => Controls_Manager::SELECT,
                'default'   => 'contain',
                'options'   => [
                    'contain' => __('کامل و بدونِ برش (contain)', 'zig3d-widgets'),
                    'cover'   => __('پرکردنِ کادر با برش (cover)', 'zig3d-widgets'),
                ],
                'selectors' => ['{{WRAPPER}} .zig-feature__media img' => 'object-fit: {{VALUE}};'],
            ]
        );

        $this->end_controls_section();
    }

    private function register_content_style_section(): void {
        $this->start_controls_section(
            'fs_content_style_section',
            [
                'label' => __('محتوایِ قابلیت', 'zig3d-widgets'),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'     => 'label_typography',
                'label'    => __('تایپوگرافیِ برچسب', 'zig3d-widgets'),
                'selector' => '{{WRAPPER}} .zig-feature__label',
            ]
        );

        $this->add_control(
            'label_color',
            [
                'label'     => __('رنگِ برچسب', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'default'   => '#B7B8C6',
                'selectors' => ['{{WRAPPER}} .zig-feature__label' => 'color: {{VALUE}};'],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'      => 'index_typography',
                'label'     => __('تایپوگرافیِ شماره', 'zig3d-widgets'),
                'selector'  => '{{WRAPPER}} .zig-feature__index',
                'separator' => 'before',
            ]
        );

        $this->add_control(
            'index_bg',
            [
                'label'     => __('پس‌زمینهٔ شماره', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'default'   => '#7B5CFF',
                'selectors' => ['{{WRAPPER}} .zig-feature__index' => 'background-color: {{VALUE}};'],
            ]
        );

        $this->add_responsive_control(
            'index_radius',
            [
                'label'      => __('گردیِ گوشهٔ شماره', 'zig3d-widgets'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range'      => ['px' => ['min' => 0, 'max' => 40]],
                'default'    => ['size' => 8, 'unit' => 'px'],
                'selectors'  => ['{{WRAPPER}} .zig-feature__index' => 'border-radius: {{SIZE}}{{UNIT}};'],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'      => 'feature_title_typography',
                'label'     => __('تایپوگرافیِ عنوان', 'zig3d-widgets'),
                'selector'  => '{{WRAPPER}} .zig-feature__feature-title',
                'separator' => 'before',
            ]
        );

        $this->add_control(
            'feature_title_color',
            [
                'label'     => __('رنگِ عنوان', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'default'   => '#F5F5F8',
                'selectors' => ['{{WRAPPER}} .zig-feature__feature-title' => 'color: {{VALUE}};'],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'      => 'feature_desc_typography',
                'label'     => __('تایپوگرافیِ توضیح', 'zig3d-widgets'),
                'selector'  => '{{WRAPPER}} .zig-feature__feature-desc',
                'separator' => 'before',
            ]
        );

        $this->add_control(
            'feature_desc_color',
            [
                'label'     => __('رنگِ توضیح', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'default'   => '#9A9CAE',
                'selectors' => ['{{WRAPPER}} .zig-feature__feature-desc' => 'color: {{VALUE}};'],
            ]
        );

        $this->add_responsive_control(
            'content_spacing',
            [
                'label'      => __('فاصلهٔ بینِ بخش‌ها', 'zig3d-widgets'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range'      => ['px' => ['min' => 0, 'max' => 48]],
                'default'    => ['size' => 20, 'unit' => 'px'],
                'separator'  => 'before',
                'selectors'  => ['{{WRAPPER}} .zig-feature__content' => 'gap: {{SIZE}}{{UNIT}};'],
            ]
        );

        $this->add_responsive_control(
            'content_max_width',
            [
                'label'       => __('حداکثرِ عرضِ محتوا', 'zig3d-widgets'),
                'type'        => Controls_Manager::SLIDER,
                'size_units'  => ['px'],
                'range'       => ['px' => ['min' => 240, 'max' => 800]],
                'default'     => ['size' => 480, 'unit' => 'px'],
                'description' => __('در پنلِ بدونِ تصویر، این عدد رعایت می‌شود ولی سقفِ پیش‌فرضِ پهن‌تری هم دارد تا محتوا نصفِ پنل خالی نگذارد.', 'zig3d-widgets'),
                /*
                 * رویِ متغیرِ CSS، نه مستقیم رویِ ‎max-width‎: پنلِ بدونِ
                 * تصویر پیش‌فرضِ پهن‌تری برایِ همین ‎max-width‎ دارد (در
                 * استایل‌شیت، با سلکتورِ specificityِ بالاتر). اگر این
                 * کنترل مستقیم رویِ ‎.zig-feature__content‎ می‌نوشت، آن
                 * پیش‌فرضِ حالتِ بدونِ‌تصویر همیشه برنده می‌شد — حتی وقتی
                 * ادمین همین‌جا عددِ دلخواهش را گذاشته. با نوشتن رویِ
                 * متغیر، هر دو حالت همان یک مقدار را می‌خوانند؛ فقط وقتی
                 * ادمین چیزی نگذاشته، سقفِ پیش‌فرضِ هرکدام جدا اعمال
                 * می‌شود.
                 */
                'selectors'   => ['{{WRAPPER}} .zig-feature' => '--zig-feature-content-max: {{SIZE}}{{UNIT}};'],
            ]
        );

        $this->end_controls_section();
    }

    private function register_panel_style_section(): void {
        $this->start_controls_section(
            'fs_panel_style_section',
            [
                'label' => __('پنلِ اصلی (شیشه‌ای)', 'zig3d-widgets'),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'panel_glass_bg',
            [
                'label'       => __('پس‌زمینهٔ شیشه‌ای', 'zig3d-widgets'),
                'type'        => Controls_Manager::COLOR,
                'default'     => 'rgba(16,16,26,.55)',
                'description' => __('رنگی با شفافیت انتخاب کنید — شفافیتِ خودِ رنگ همان «نیمه‌شفافی»ِ شیشه را می‌سازد.', 'zig3d-widgets'),
                'selectors'   => ['{{WRAPPER}} .zig-feature' => '--zig-feature-panel-bg: {{VALUE}};'],
            ]
        );

        $this->add_responsive_control(
            'panel_blur',
            [
                'label'      => __('میزانِ Blurِ پشتِ پنل', 'zig3d-widgets'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range'      => ['px' => ['min' => 0, 'max' => 48]],
                'default'    => ['size' => 20, 'unit' => 'px'],
                'selectors'  => ['{{WRAPPER}} .zig-feature' => '--zig-feature-panel-blur: {{SIZE}}{{UNIT}};'],
            ]
        );

        $this->add_control(
            'panel_border_color',
            [
                'label'     => __('رنگِ مرز', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'default'   => '#FFFFFF',
                'selectors' => ['{{WRAPPER}} .zig-feature' => '--zig-feature-panel-border-color: {{VALUE}};'],
            ]
        );

        $this->add_control(
            'panel_border_opacity',
            [
                'label'       => __('شفافیتِ مرز', 'zig3d-widgets'),
                'type'        => Controls_Manager::SLIDER,
                'size_units'  => ['%'],
                'range'       => ['%' => ['min' => 0, 'max' => 100]],
                'default'     => ['size' => 10, 'unit' => '%'],
                'description' => __('مرزِ روشن و خیلی subtile؛ عددِ کم یعنی تقریباً نامرئی.', 'zig3d-widgets'),
                'selectors'   => ['{{WRAPPER}} .zig-feature' => '--zig-feature-panel-border-opacity: {{SIZE}}{{UNIT}};'],
            ]
        );

        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            [
                'name'     => 'panel_shadow',
                'label'    => __('سایه', 'zig3d-widgets'),
                'selector' => '{{WRAPPER}} .zig-feature__panel',
            ]
        );

        $this->add_responsive_control(
            'panel_radius',
            [
                'label'      => __('گردیِ گوشه‌ها', 'zig3d-widgets'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range'      => ['px' => ['min' => 0, 'max' => 60]],
                'default'    => ['size' => 28, 'unit' => 'px'],
                'selectors'  => ['{{WRAPPER}} .zig-feature__panel' => 'border-radius: {{SIZE}}{{UNIT}};'],
            ]
        );

        $this->add_responsive_control(
            'panel_padding',
            [
                'label'      => __('فاصلهٔ داخلی', 'zig3d-widgets'),
                'type'       => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', 'rem'],
                'default'    => ['top' => '32', 'right' => '32', 'bottom' => '32', 'left' => '32', 'unit' => 'px'],
                'selectors'  => ['{{WRAPPER}} .zig-feature__panel' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
            ]
        );

        $this->add_responsive_control(
            'panel_gap',
            [
                'label'      => __('فاصلهٔ بینِ رسانه و محتوا', 'zig3d-widgets'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range'      => ['px' => ['min' => 0, 'max' => 80]],
                'default'    => ['size' => 40, 'unit' => 'px'],
                'selectors'  => ['{{WRAPPER}} .zig-feature__panel' => 'column-gap: {{SIZE}}{{UNIT}};'],
            ]
        );

        $this->add_control(
            'panel_media_ratio',
            [
                'label'       => __('نسبتِ عرضِ رسانه', 'zig3d-widgets'),
                'type'        => Controls_Manager::NUMBER,
                'min'         => 0.5,
                'max'         => 3,
                'step'        => 0.05,
                'default'     => 1,
                'separator'   => 'before',
                'description' => __('نسبتِ عرضِ ستونِ رسانه به ستونِ محتوا — عددهایِ نسبی، نه پیکسل.', 'zig3d-widgets'),
                // ‎fr‎ همین‌جا به مقدار چسبانده می‌شود، نه در CSS — ‎var(--x)fr‎ نحوِ نامعتبر است
                'selectors'   => ['{{WRAPPER}} .zig-feature' => '--zig-feature-media-fr: {{VALUE}}fr;'],
            ]
        );

        $this->add_control(
            'panel_content_ratio',
            [
                'label'     => __('نسبتِ عرضِ محتوا', 'zig3d-widgets'),
                'type'      => Controls_Manager::NUMBER,
                'min'       => 0.5,
                'max'       => 3,
                'step'      => 0.05,
                'default'   => 1.35,
                'selectors' => ['{{WRAPPER}} .zig-feature' => '--zig-feature-content-fr: {{VALUE}}fr;'],
            ]
        );

        $this->end_controls_section();
    }

    /**
     * جلوه‌هایِ نورانی (Glow) — یک منبعِ رنگ/شدتِ مشترک برایِ هر چهار
     * محلی که glow دارند: پشتِ تبِ فعال، پشتِ بجِ شماره، لکهٔ ambientِ
     * پشتِ محتوا، و لکهٔ کوچک‌ترِ گوشهٔ رسانه. کنترلِ جداگانه برایِ
     * هرکدام نساختیم چون دقیقاً همان چیزی می‌شد که خودِ درخواست گفته
     * بود نسازیم — یک پنلِ استایلِ غول‌پیکر با ده‌ها کنترلِ تکراری.
     */
    private function register_glow_style_section(): void {
        $this->start_controls_section(
            'fs_glow_style_section',
            [
                'label' => __('Glow / جلوه‌ها', 'zig3d-widgets'),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'glow_enabled',
            [
                'label'   => __('فعال بودنِ Glowِ محلی', 'zig3d-widgets'),
                'type'    => Controls_Manager::SWITCHER,
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'glow_color',
            [
                'label'     => __('رنگ', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'default'   => '#7B5CFF',
                'condition' => ['glow_enabled' => 'yes'],
                'selectors' => ['{{WRAPPER}} .zig-feature' => '--zig-feature-glow-color: {{VALUE}};'],
            ]
        );

        $this->add_control(
            'glow_opacity',
            [
                'label'      => __('شدت (Opacity)', 'zig3d-widgets'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['%'],
                'range'      => ['%' => ['min' => 0, 'max' => 100]],
                'default'    => ['size' => 55, 'unit' => '%'],
                'condition'  => ['glow_enabled' => 'yes'],
                'selectors'  => ['{{WRAPPER}} .zig-feature' => '--zig-feature-glow-opacity: {{SIZE}}{{UNIT}};'],
            ]
        );

        $this->add_responsive_control(
            'glow_blur',
            [
                'label'      => __('میزانِ Blur', 'zig3d-widgets'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range'      => ['px' => ['min' => 10, 'max' => 140]],
                'default'    => ['size' => 60, 'unit' => 'px'],
                'condition'  => ['glow_enabled' => 'yes'],
                'selectors'  => ['{{WRAPPER}} .zig-feature' => '--zig-feature-glow-blur: {{SIZE}}{{UNIT}};'],
            ]
        );

        $this->add_responsive_control(
            'glow_spread',
            [
                'label'       => __('اندازه (Spread)', 'zig3d-widgets'),
                'type'        => Controls_Manager::SLIDER,
                'size_units'  => ['px'],
                'range'       => ['px' => ['min' => 60, 'max' => 320]],
                'default'     => ['size' => 140, 'unit' => 'px'],
                'condition'   => ['glow_enabled' => 'yes'],
                'description' => __('قطرِ لکه‌هایِ ambientِ پشتِ محتوا/رسانه؛ روی glowِ پشتِ تب و بجِ شماره اثر ندارد (آن‌ها سایه‌اند، نه لکه).', 'zig3d-widgets'),
                'selectors'   => ['{{WRAPPER}} .zig-feature' => '--zig-feature-glow-spread: {{SIZE}}{{UNIT}};'],
            ]
        );

        $this->add_control(
            'glow_animate_heading',
            [
                'label'     => __('انیمیشنِ Glow', 'zig3d-widgets'),
                'type'      => Controls_Manager::HEADING,
                'separator' => 'before',
                'condition' => ['glow_enabled' => 'yes'],
            ]
        );

        $this->add_control(
            'glow_animate',
            [
                'label'       => __('پالسِ ملایمِ زنده', 'zig3d-widgets'),
                'type'        => Controls_Manager::SWITCHER,
                'default'     => 'yes',
                'condition'   => ['glow_enabled' => 'yes'],
                'description' => __('یک تنفسِ خیلی‌کند و ظریف در opacity/اندازهٔ لکه‌ها؛ با prefers-reduced-motion خودکار خاموش می‌شود.', 'zig3d-widgets'),
            ]
        );

        $this->add_control(
            'glow_speed',
            [
                'label'     => __('سرعتِ پالس', 'zig3d-widgets'),
                'type'      => Controls_Manager::SLIDER,
                'size_units' => ['s'],
                'range'     => ['s' => ['min' => 2, 'max' => 10, 'step' => .5]],
                'default'   => ['size' => 5, 'unit' => 's'],
                'condition' => ['glow_enabled' => 'yes', 'glow_animate' => 'yes'],
                'selectors' => ['{{WRAPPER}} .zig-feature' => '--zig-feature-glow-speed: {{SIZE}}{{UNIT}};'],
            ]
        );

        $this->end_controls_section();
    }

    /**
     * موشن — فقط گذارِ سوییچِ بینِ قابلیت‌ها (fade/rise/stagger). باز/
     * بسته‌ای این‌جا نیست؛ این‌ها مستقیم رویِ متغیرهایِ CSSِ خوانده‌شده
     * توسطِ جاوااسکریپت می‌نشینند — نه تایمینگِ ثابتِ کدنویسی‌شده — چون
     * این‌بار (برخلافِ ویجتِ آکاردئونِ مشخصاتِ فنی) صریحاً به‌عنوانِ
     * Style Control خواسته شده.
     */
    private function register_motion_style_section(): void {
        $this->start_controls_section(
            'fs_motion_style_section',
            [
                'label' => __('موشن', 'zig3d-widgets'),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'motion_enabled',
            [
                'label'       => __('گذارِ نرمِ سوییچ', 'zig3d-widgets'),
                'type'        => Controls_Manager::SWITCHER,
                'default'     => 'yes',
                'description' => __('خاموش یعنی سوییچِ تب فوری است، بدونِ fade/rise. با prefers-reduced-motion هم مستقل از این کنترل خاموش می‌شود.', 'zig3d-widgets'),
            ]
        );

        $this->add_control(
            'motion_duration',
            [
                'label'      => __('مدتِ گذار', 'zig3d-widgets'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['ms'],
                'range'      => ['ms' => ['min' => 150, 'max' => 500, 'step' => 10]],
                'default'    => ['size' => 260, 'unit' => 'ms'],
                'condition'  => ['motion_enabled' => 'yes'],
                'selectors'  => ['{{WRAPPER}} .zig-feature' => '--zig-feature-motion-duration: {{SIZE}}{{UNIT}};'],
            ]
        );

        $this->add_control(
            'motion_easing',
            [
                'label'     => __('Easing', 'zig3d-widgets'),
                'type'      => Controls_Manager::SELECT,
                'default'   => 'cubic-bezier(.22,1,.36,1)',
                'condition' => ['motion_enabled' => 'yes'],
                'options'   => [
                    'cubic-bezier(.22,1,.36,1)' => __('نرم (پیشنهادی)', 'zig3d-widgets'),
                    'cubic-bezier(.16,1,.3,1)'  => __('سریع‌تر و تیزتر', 'zig3d-widgets'),
                    'ease-out'                  => __('سادهٔ استاندارد', 'zig3d-widgets'),
                ],
                // خودِ مقدارِ گزینه همان رشتهٔ easingِ واقعی است؛ نیازی به نگاشتِ جدا در PHP نیست
                'selectors' => ['{{WRAPPER}} .zig-feature' => '--zig-feature-motion-easing: {{VALUE}};'],
            ]
        );

        $this->end_controls_section();
    }

    /* =====================================================================
     * رندر
     * =================================================================== */

    protected function render(): void {
        $settings   = $this->get_settings_for_display();
        $is_editor  = $this->is_editing();
        $product    = $this->resolve_product($settings, $is_editor);

        if (null === $product) {
            return;
        }

        $rows = Feature_Repeater::rows($product->get_id());

        if (!$rows) {
            return;
        }

        $total  = count($rows);
        $active = absint($settings['initial_active_item'] ?? 1);
        $active = ($active >= 1 && $active <= $total) ? $active - 1 : 0;

        $uid     = 'zig-feat-' . $this->get_id();
        $has_nav = $total > 1;
        $glow    = 'yes' === ($settings['glow_enabled'] ?? 'yes');
        $motion  = 'yes' === ($settings['motion_enabled'] ?? 'yes');

        $root_classes = 'zig-feature';

        if (!$glow) {
            $root_classes .= ' zig-feature--no-glow';
        }

        if ('yes' !== ($settings['glow_animate'] ?? 'yes')) {
            $root_classes .= ' zig-feature--no-glow-animate';
        }

        printf(
            '<div class="%s"%s>',
            esc_attr($root_classes),
            $motion ? '' : ' data-zig-motion="off"'
        );

        $this->render_intro($settings);

        if ($has_nav) {
            $this->render_nav($rows, $active, $uid, $settings);
        }

        $this->render_panels($rows, $active, $uid, $settings, $is_editor, $has_nav, $glow);

        echo '</div>';
    }

    /**
     * محصولی که Repeaterش خوانده می‌شود.
     *
     * سایتِ واقعی همیشه از زنجیرهٔ ‎Price::resolve(0)‎ می‌آید — محصولِ
     * لوپِ جاری، یا کوئریِ صفحهٔ محصول، یا پستِ جاری؛ هیچ شناسهٔ دستی از
     * تنظیماتِ ویجت خوانده نمی‌شود. کنترلِ «محصولِ پیش‌نمایش» فقط وقتی
     * خوانده می‌شود که واقعاً داخلِ ادیتورِ المنتور باشیم — دقیقاً همان
     * چیزی که خواسته شده: «این کنترل نباید منبعِ frontend را عوض کند».
     */
    private function resolve_product(array $settings, bool $is_editor) {
        if ($is_editor) {
            $preview_id = absint($settings['preview_product_id'] ?? 0);

            if ($preview_id > 0) {
                $preview = Price::resolve($preview_id);

                if (null !== $preview) {
                    return $preview;
                }
            }
        }

        return Price::resolve(0);
    }

    private function render_intro(array $settings): void {
        $kicker = trim((string) ($settings['intro_kicker'] ?? ''));
        $title  = trim((string) ($settings['intro_title'] ?? ''));
        $desc   = trim((string) ($settings['intro_description'] ?? ''));

        $has_kicker = Markup::filled($kicker);
        $has_title  = Markup::filled($title);
        $has_desc   = Markup::filled($desc);

        if (!$has_kicker && !$has_title && !$has_desc) {
            return;
        }

        echo '<div class="zig-feature__intro">';

        if ($has_kicker) {
            printf('<span class="zig-feature__kicker">%s</span>', Markup::text($kicker));
        }

        if ($has_title) {
            printf('<h2 class="zig-feature__intro-title">%s</h2>', Markup::text($title));
        }

        if ($has_desc) {
            printf('<p class="zig-feature__intro-desc">%s</p>', Markup::text($desc));
        }

        echo '</div>';
    }

    /**
     * @param array<int,array{label:string,title:string,description:string,image:array{id:int,url:string}}> $rows
     */
    private function render_nav(array $rows, int $active, string $uid, array $settings): void {
        $arrows = 'yes' === ($settings['show_overflow_arrows'] ?? 'yes');

        echo '<div class="zig-feature__nav">';

        if ($arrows) {
            printf(
                '<button type="button" class="zig-feature__nav-arrow zig-feature__nav-arrow--prev" data-zig-feature-prev aria-label="%s">%s</button>',
                esc_attr__('قابلیتِ قبلی', 'zig3d-widgets'),
                self::arrow_svg('prev')
            );
        }

        echo '<div class="zig-feature__tablist" role="tablist" aria-label="' . esc_attr__('قابلیت‌های محصول', 'zig3d-widgets') . '">';

        foreach ($rows as $i => $row) {
            $is_active = ($i === $active);

            printf(
                '<button type="button" role="tab" id="%1$s-tab-%2$d" aria-controls="%1$s-panel-%2$d" aria-selected="%3$s" tabindex="%4$s" class="zig-feature__tab%5$s" data-zig-feature-tab="%2$d">%6$s</button>',
                esc_attr($uid),
                $i,
                $is_active ? 'true' : 'false',
                $is_active ? '0' : '-1',
                $is_active ? ' is-active' : '',
                Markup::text($row['label'])
            );
        }

        echo '</div>';

        if ($arrows) {
            printf(
                '<button type="button" class="zig-feature__nav-arrow zig-feature__nav-arrow--next" data-zig-feature-next aria-label="%s">%s</button>',
                esc_attr__('قابلیتِ بعدی', 'zig3d-widgets'),
                self::arrow_svg('next')
            );
        }

        echo '</div>';
    }

    /**
     * @param array<int,array{label:string,title:string,description:string,image:array{id:int,url:string}}> $rows
     */
    private function render_panels(array $rows, int $active, string $uid, array $settings, bool $is_editor, bool $has_nav, bool $glow): void {
        $show_index = 'yes' === ($settings['show_index'] ?? 'yes');
        $show_label = 'yes' === ($settings['show_label'] ?? 'yes');
        $show_title = 'yes' === ($settings['show_title'] ?? 'yes');
        $show_desc  = 'yes' === ($settings['show_description'] ?? 'yes');

        echo '<div class="zig-feature__panels">';

        foreach ($rows as $i => $row) {
            $is_active = ($i === $active);
            $has_image = $row['image']['id'] > 0 || '' !== $row['image']['url'];

            /*
             * وقتی فقط یک قابلیتِ معتبر است، ناوبری اصلاً رندر نمی‌شود
             * (‎render_nav‎ صدا زده نمی‌شود) — پس این‌جا هم نباید
             * ‎role="tabpanel"‎/‎aria-labelledby‎ی به تبی که در DOM
             * وجود ندارد اشاره کند؛ فقط یک بلوکِ محتوایِ ساده می‌شود.
             */
            printf(
                '<div class="zig-feature__panel%1$s" id="%2$s-panel-%3$d"%4$s%5$s>',
                $has_image ? '' : ' zig-feature__panel--no-media',
                esc_attr($uid),
                $i,
                $has_nav ? ' role="tabpanel" aria-labelledby="' . esc_attr($uid) . '-tab-' . $i . '" tabindex="0"' : '',
                $is_active ? '' : ' hidden'
            );

            /*
             * لکه‌هایِ Glowِ محلی — عنصرِ واقعیِ DOM (نه ‎::before‎)، چون
             * JS با WAAPI موقعِ سوییچِ تب opacityشان را انیمیت می‌کند و
             * پشتیبانیِ ‎animate()‎ رویِ pseudo-element هنوز یکدست نیست.
             * وقتی «Glow محلی» از استایل خاموش است، اصلاً چاپ نمی‌شوند —
             * نه این‌که با CSS پنهان بمانند.
             */
            if ($glow) {
                echo '<span class="zig-feature__glow zig-feature__glow--content" aria-hidden="true"></span>';

                if ($has_image) {
                    echo '<span class="zig-feature__glow zig-feature__glow--media" aria-hidden="true"></span>';
                }
            }

            if ($has_image) {
                echo '<div class="zig-feature__media">';
                echo self::render_image($row['image'], $row['title'] ?: $row['label'], $is_active || $is_editor);
                echo '</div>';
            }

            echo '<div class="zig-feature__content">';

            if (($show_index || ($show_label && Markup::filled($row['label'])))) {
                echo '<div class="zig-feature__meta">';

                if ($show_index) {
                    printf('<span class="zig-feature__index">%s</span>', esc_html(self::index_label($i)));
                }

                if ($show_label && Markup::filled($row['label'])) {
                    printf('<span class="zig-feature__label">%s</span>', Markup::text($row['label']));
                }

                echo '</div>';
            }

            if ($show_title && Markup::filled($row['title'])) {
                printf('<h3 class="zig-feature__feature-title">%s</h3>', Markup::text($row['title']));
            }

            if ($show_desc && Markup::filled($row['description'])) {
                printf('<p class="zig-feature__feature-desc">%s</p>', Markup::text($row['description']));
            }

            echo '</div>'; // .zig-feature__content
            echo '</div>'; // .zig-feature__panel
        }

        echo '</div>';
    }

    /**
     * شمارهٔ نمایشی — از ایندکسِ *بعدِ فیلتر* (پارامترِ ‎$i‎ همان ایندکسِ
     * آرایهٔ خروجیِ ‎Feature_Repeater::rows()‎ است، نه ایندکسِ خامِ
     * Repeater)، با ارقامِ محلی (فارسی روی سایتِ فارسی) — همان کمکیِ
     * مشترکی که در سرتاسرِ افزونه برایِ همین تبدیل استفاده می‌شود.
     */
    private static function index_label(int $i): string {
        return Price::persian(sprintf('%02d', $i + 1));
    }

    /**
     * @param array{id:int,url:string} $image
     */
    private static function render_image(array $image, string $alt, bool $eager): string {
        $loading = $eager ? 'eager' : 'lazy';

        if ($image['id'] > 0) {
            return (string) wp_get_attachment_image(
                $image['id'],
                'large',
                false,
                [
                    'class'   => 'zig-feature__img',
                    'alt'     => $alt,
                    'loading' => $loading,
                ]
            );
        }

        return sprintf(
            '<img class="zig-feature__img" src="%s" alt="%s" loading="%s" />',
            esc_url($image['url']),
            esc_attr($alt),
            esc_attr($loading)
        );
    }

    private static function arrow_svg(string $dir): string {
        // فلشِ «قبلی» رو به راست، «بعدی» رو به چپ — چون در آرایشِ راست‌به‌چپ،
        // تبِ قبلی (شمارهٔ کوچک‌تر) از نظرِ دیداری سمتِ راست است
        $path = 'prev' === $dir
            ? 'm9 6 6 6-6 6'
            : 'm15 6-6 6 6 6';

        return '<svg viewBox="0 0 24 24" width="1.1em" height="1.1em" fill="none" stroke="currentColor"'
            . ' stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="' . $path . '"/></svg>';
    }

    private function is_editing(): bool {
        return class_exists('\Elementor\Plugin')
            && isset(\Elementor\Plugin::$instance->editor)
            && \Elementor\Plugin::$instance->editor->is_edit_mode();
    }
}
