<?php
namespace Zig3d_Widgets\Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Background;
use Elementor\Group_Control_Typography;
use Elementor\Repeater;
use Elementor\Widget_Base;
use Zig3d_Widgets\Design_Icons;
use Zig3d_Widgets\Markup;
use Zig3d_Widgets\Menu_Tree;
use Zig3d_Widgets\Plugin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * منویِ اصلی — نوارِ دسکتاپ با زیرمنو.
 *
 *     nav.zig-menu
 *       ul.zig-menu__bar
 *         li.zig-menu__item
 *           a.zig-menu__link  ← کلیک همیشه می‌بَرد
 *           div.zig-menu__panel--list | --mega | --template
 *
 * ساختار از فهرستِ وردپرس می‌آید، نه از ریپیترِ داخلِ ویجت. سه دلیل که
 * هیچ‌کدام سلیقه‌ای نیستند: ترتیب و تودرتویی را مدیر با کشیدن‌ورهاکردن
 * می‌چیند، افزونه‌هایِ چندزبانه رویِ همان منو کار می‌کنند، و
 * ‎current-menu-item‎ را خودِ وردپرس می‌گذارد — که تنها راهِ درستِ
 * تشخیصِ آیتمِ فعال برایِ زیرخطِ طرح است.
 *
 * باز شدن با هاور است و کلیک همچنان به مقصدِ خودِ آیتم می‌رود. این یعنی
 * باز شدن هیچ جاوااسکریپتی لازم ندارد: ‎:hover‎ برایِ موس و
 * ‎:focus-within‎ برایِ کیبورد، هر دو در CSS. اسکریپت فقط برایِ Esc و
 * حالتِ لمسی می‌آید.
 *
 * سه شکلِ زیرمنو، چون طرح سه‌تا دارد نه یکی:
 *
 *   list      فهرستِ ساده با فلش — شکلِ «دانلود نرم‌افزار».
 *   mega      سه‌ستونه با CTA و شمارش و کارتِ محصول — فقط «محصولات».
 *   template  یک قالبِ المنتور، برایِ هر چیزی بیرونِ این دو.
 */
final class Menu extends Widget_Base {

    use Traits\Box;

    public function get_name(): string {
        return 'zig3d-menu';
    }

    public function get_title(): string {
        return __('منویِ اصلی', 'zig3d-widgets');
    }

    public function get_icon(): string {
        return 'eicon-nav-menu';
    }

    public function get_categories(): array {
        return [Plugin::CATEGORY];
    }

    public function get_keywords(): array {
        return ['menu', 'nav', 'megamenu', 'منو', 'فهرست', 'مگامنو'];
    }

    public function get_style_depends(): array {
        return ['zig3d-widgets'];
    }

    public function has_widget_inner_wrapper(): bool {
        return false;
    }

    protected function register_controls(): void {
        $this->register_source_section();
        $this->register_submenu_section();
        $this->register_bar_style_section();
        $this->register_panel_style_section();
        $this->register_mega_style_section();
        $this->register_card_style_section();
    }

    /* =====================================================================
     * محتوا › منبع
     * =================================================================== */

    private function register_source_section(): void {
        $this->start_controls_section('source_section', ['label' => __('منبعِ منو', 'zig3d-widgets')]);

        $menus = $this->menu_options();

        if ([] === $menus) {
            $this->add_control('no_menus', [
                'type'            => Controls_Manager::RAW_HTML,
                'raw'             => __('هنوز هیچ فهرستی ساخته نشده. از «نمایش ← فهرست‌ها» یکی بسازید و آیتم‌هایش را همان‌جا بچینید.', 'zig3d-widgets'),
                'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
            ]);
        }

        $this->add_control('menu_id', [
            'label'       => __('فهرست', 'zig3d-widgets'),
            'type'        => Controls_Manager::SELECT,
            'options'     => $menus,
            'default'     => (string) (array_key_first($menus) ?? ''),
            'description' => __('آیتم‌ها، ترتیب و زیرمنوها همه در «نمایش ← فهرست‌ها» تعریف می‌شوند.', 'zig3d-widgets'),
        ]);

        $this->add_control('aria_label', [
            'label'       => __('نامِ ناوبری برایِ صفحه‌خوان', 'zig3d-widgets'),
            'type'        => Controls_Manager::TEXT,
            'default'     => __('منویِ اصلی', 'zig3d-widgets'),
            'description' => __('اگر بیش از یک ناوبری در صفحه باشد، صفحه‌خوان با همین نام از هم تفکیکشان می‌کند.', 'zig3d-widgets'),
        ]);

        $this->end_controls_section();
    }

    /**
     * فهرست‌هایِ ثبت‌شده، برایِ کنترلِ انتخاب.
     *
     * @return array<string,string>
     */
    private function menu_options(): array {
        if (!function_exists('wp_get_nav_menus')) {
            return [];
        }

        $options = [];

        foreach (wp_get_nav_menus() as $menu) {
            $options[(string) $menu->term_id] = $menu->name;
        }

        return $options;
    }

    /* =====================================================================
     * محتوا › زیرمنوها
     * =================================================================== */

    private function register_submenu_section(): void {
        $this->start_controls_section('submenu_section', ['label' => __('زیرمنوها', 'zig3d-widgets')]);

        /*
         * *ساختار* در فهرستِ وردپرس است، ولی *شکلِ نمایش* اینجا — چون
         * شکل یک تصمیمِ ظاهری است و جایش همان‌جایی است که بقیهٔ ظاهر
         * تنظیم می‌شود، نه لابه‌لایِ تنظیماتِ فهرست.
         */
        $this->add_control('mega_item', [
            'label'       => __('کدام آیتم مگامنو باشد؟', 'zig3d-widgets'),
            'type'        => Controls_Manager::SELECT,
            'options'     => ['' => __('هیچ‌کدام', 'zig3d-widgets')] + $this->top_level_options(),
            'default'     => '',
            'description' => __('مگامنو سه‌ستونه است با شمارشِ محصول و کارت — همان چیزی که برایِ «محصولات» طراحی شده. بقیهٔ آیتم‌ها فهرستِ ساده می‌گیرند.', 'zig3d-widgets'),
        ]);

        $this->add_control('mega_cards', [
            'label'       => __('تعدادِ کارتِ محصول در مگامنو', 'zig3d-widgets'),
            'type'        => Controls_Manager::NUMBER,
            'min'         => 0,
            'max'         => 6,
            'default'     => 3,
            'condition'   => ['mega_item!' => ''],
            'description' => __('کارت‌ها یک ستونِ جداگانه در انتهایِ مگامنو می‌سازند — پرفروش‌ترین‌هایِ فروشگاه، خودکار از ووکامرس. صفر یعنی این ستون اصلاً نباشد.', 'zig3d-widgets'),
        ]);

        $this->add_control('popular_title', [
            'label'     => __('عنوانِ ستونِ کارت‌ها', 'zig3d-widgets'),
            'type'      => Controls_Manager::TEXT,
            'default'   => __('محبوب‌ترین ها', 'zig3d-widgets'),
            'condition' => ['mega_item!' => '', 'mega_cards!' => '0'],
        ]);

        $this->add_control('popular_cta', [
            'label'       => __('متنِ دکمهٔ ستونِ کارت‌ها', 'zig3d-widgets'),
            'type'        => Controls_Manager::TEXT,
            'default'     => __('مشاهده تمام محصولات', 'zig3d-widgets'),
            'condition'   => ['mega_item!' => '', 'mega_cards!' => '0'],
            'description' => __('پیوندش همان پیوندِ خودِ آیتمِ مگامنو در فهرستِ وردپرس است.', 'zig3d-widgets'),
        ]);

        $this->add_control('show_counts', [
            'label'        => __('نمایشِ شمارشِ محصول', 'zig3d-widgets'),
            'type'         => Controls_Manager::SWITCHER,
            'default'      => 'yes',
            'return_value' => 'yes',
            'description'  => __('عدد از خودِ ووکامرس خوانده می‌شود. آیتمی که در فهرست به یک دستهٔ محصول وصل نباشد، شمارش نمی‌گیرد.', 'zig3d-widgets'),
        ]);

        $repeater = new Repeater();

        $repeater->add_control('item_id', [
            'label'   => __('آیتمِ منو', 'zig3d-widgets'),
            'type'    => Controls_Manager::SELECT,
            'options' => $this->top_level_options(),
        ]);

        $repeater->add_control('template_id', [
            'label'       => __('قالبِ المنتور', 'zig3d-widgets'),
            'type'        => Controls_Manager::SELECT,
            'options'     => $this->template_options(),
            'label_block' => true,
        ]);

        $this->add_control('template_panels', [
            'label'       => __('زیرمنو از قالبِ المنتور', 'zig3d-widgets'),
            'type'        => Controls_Manager::REPEATER,
            'fields'      => $repeater->get_controls(),
            'title_field' => '{{{ item_id }}}',
            'separator'   => 'before',
            'description' => __('برایِ آیتمی که زیرمنویش باید کاملاً دلخواه باشد. بر هر دو شکلِ دیگر می‌چربد.', 'zig3d-widgets'),
        ]);

        $this->end_controls_section();
    }

    /**
     * آیتم‌هایِ سطحِ اولِ فهرستِ انتخاب‌شده — برایِ کنترل‌هایی که باید به
     * یک آیتمِ مشخص اشاره کنند.
     *
     * @return array<string,string>
     */
    private function top_level_options(): array {
        $menu_id = (int) ($this->get_settings('menu_id') ?? 0);
        $options = [];

        foreach (Menu_Tree::build($menu_id) as $node) {
            $options[(string) $node['id']] = $node['title'];
        }

        return $options;
    }

    /** @return array<string,string> */
    private function template_options(): array {
        $options = ['' => __('— انتخاب کنید —', 'zig3d-widgets')];

        if (!function_exists('get_posts')) {
            return $options;
        }

        $templates = get_posts([
            'post_type'      => 'elementor_library',
            'posts_per_page' => 100,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC',
            'no_found_rows'  => true,
        ]);

        foreach ($templates as $template) {
            $options[(string) $template->ID] = $template->post_title;
        }

        return $options;
    }

    /* =====================================================================
     * استایل › نوار
     * =================================================================== */

    private function register_bar_style_section(): void {
        $this->start_controls_section('bar_style_section', [
            'label' => __('نوارِ منو', 'zig3d-widgets'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $root = '{{WRAPPER}} .zig-menu';

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'     => 'bar_typography',
            'selector' => '{{WRAPPER}} .zig-menu__label',
        ]);

        $this->start_controls_tabs('bar_tabs');

        $this->start_controls_tab('bar_tab_normal', ['label' => __('عادی', 'zig3d-widgets')]);

        $this->add_control('bar_color', [
            'label'     => __('رنگِ متن', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [$root => '--zig-menu-color: {{VALUE}};'],
        ]);

        $this->end_controls_tab();

        $this->start_controls_tab('bar_tab_hover', ['label' => __('هاور', 'zig3d-widgets')]);

        $this->add_control('bar_color_hover', [
            'label'     => __('رنگِ متن', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [$root => '--zig-menu-color-hover: {{VALUE}};'],
        ]);

        $this->add_control('bar_bg_hover', [
            'label'     => __('پس‌زمینه', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [$root => '--zig-menu-item-bg-hover: {{VALUE}};'],
        ]);

        $this->end_controls_tab();

        $this->start_controls_tab('bar_tab_active', ['label' => __('فعال', 'zig3d-widgets')]);

        $this->add_control('bar_color_active', [
            'label'     => __('رنگِ متن', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [$root => '--zig-menu-color-active: {{VALUE}};'],
        ]);

        /*
         * زیرخطِ آیتمِ فعال. در طرح ۸۸ در ۴ است ولی عرضش را ثابت
         * نمی‌نویسیم: عرضِ متنِ هر آیتم فرق می‌کند و عددِ ثابت فقط برایِ
         * «محصولات» درست در می‌آمد.
         */
        $this->add_control('bar_underline_color', [
            'label'     => __('رنگِ زیرخط', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [$root => '--zig-menu-underline-color: {{VALUE}};'],
        ]);

        $this->add_control('bar_underline_height', [
            'label'      => __('ضخامتِ زیرخط', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 12]],
            'selectors'  => [$root => '--zig-menu-underline-height: {{SIZE}}{{UNIT}};'],
        ]);

        $this->end_controls_tab();
        $this->end_controls_tabs();

        $this->add_responsive_control('bar_gap', [
            'label'      => __('فاصلهٔ آیتم‌ها', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'separator'  => 'before',
            'range'      => ['px' => ['min' => 0, 'max' => 40]],
            'selectors'  => [$root => '--zig-menu-gap: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_responsive_control('bar_item_padding', [
            'label'      => __('فاصلهٔ داخلیِ آیتم', 'zig3d-widgets'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', 'em'],
            'selectors'  => [
                $root => '--zig-menu-item-pad-block: {{TOP}}{{UNIT}}; --zig-menu-item-pad-inline: {{RIGHT}}{{UNIT}};',
            ],
        ]);

        $this->add_control('bar_item_radius', [
            'label'      => __('گردیِ گوشهٔ آیتم', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 40]],
            'selectors'  => [$root => '--zig-menu-item-radius: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_control('chevron_gap', [
            'label'      => __('فاصلهٔ فلش تا متن', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 40]],
            'selectors'  => [$root => '--zig-menu-chevron-gap: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_control('chevron_size', [
            'label'      => __('اندازهٔ فلش', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 6, 'max' => 32]],
            'selectors'  => [$root => '--zig-menu-chevron-size: {{SIZE}}{{UNIT}};'],
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل › پنل
     * =================================================================== */

    private function register_panel_style_section(): void {
        $this->start_controls_section('panel_style_section', [
            'label' => __('پنلِ زیرمنو', 'zig3d-widgets'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $root = '{{WRAPPER}} .zig-menu';

        /*
         * دو عرضِ جدا، چون طرح دو رفتارِ جدا دارد: مگامنو یک نوارِ پهنِ
         * وسط‌چینِ صفحه است، فهرستِ ساده یک ستونِ باریک که لبه‌اش با
         * لبهٔ عنوانِ خودِ آیتم یکی می‌شود.
         */
        $this->add_responsive_control('mega_width', [
            'label'      => __('عرضِ مگامنو', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', 'vw'],
            'range'      => ['px' => ['min' => 600, 'max' => 1920], 'vw' => ['min' => 50, 'max' => 100]],
            'selectors'  => [$root => '--zig-menu-mega-width: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_responsive_control('list_width', [
            'label'      => __('عرضِ زیرمنویِ ساده', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 160, 'max' => 600]],
            'selectors'  => [$root => '--zig-menu-list-width: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_control('panel_bg', [
            'label'     => __('پس‌زمینه', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [$root => '--zig-menu-panel-bg: {{VALUE}};'],
        ]);

        $this->add_control('panel_radius', [
            'label'      => __('گردیِ گوشه‌هایِ پایین', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 60]],
            'selectors'  => [$root => '--zig-menu-panel-radius: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_responsive_control('panel_padding', [
            'label'      => __('فاصلهٔ داخلی', 'zig3d-widgets'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px'],
            'selectors'  => [
                $root => '--zig-menu-panel-pad-block: {{BOTTOM}}{{UNIT}}; --zig-menu-panel-pad-inline: {{RIGHT}}{{UNIT}};',
            ],
        ]);

        $this->add_control('panel_row_heading', [
            'label'     => __('ردیف‌ها', 'zig3d-widgets'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'     => 'panel_row_typography',
            'selector' => '{{WRAPPER}} .zig-menu__sub-label',
        ]);

        $this->add_control('panel_row_color', [
            'label'     => __('رنگِ متن', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [$root => '--zig-menu-sub-color: {{VALUE}};'],
        ]);

        $this->add_control('panel_row_pad', [
            'label'      => __('فاصلهٔ عمودیِ ردیف', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 40]],
            'selectors'  => [$root => '--zig-menu-sub-pad: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_control('panel_divider_color', [
            'label'     => __('رنگِ خطِ جداکننده', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [$root => '--zig-menu-sub-divider: {{VALUE}};'],
        ]);

        $this->add_control('panel_row_chevron_size', [
            'label'      => __('اندازهٔ فلشِ ردیف', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 6, 'max' => 24]],
            'selectors'  => [$root => '--zig-menu-sub-chevron-size: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_control('panel_head_heading', [
            'label'     => __('سرتیترِ ستون', 'zig3d-widgets'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_control('col_title_color', [
            'label'     => __('رنگِ عنوان', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [$root => '--zig-menu-col-title-color: {{VALUE}};'],
        ]);

        $this->add_control('count_color', [
            'label'     => __('رنگِ شمارش', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [$root => '--zig-menu-count-color: {{VALUE}};'],
        ]);

        $this->add_control('panel_anim', [
            'label'      => __('زمانِ باز و بسته شدن', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['ms'],
            'range'      => ['ms' => ['min' => 0, 'max' => 600, 'step' => 10]],
            'separator'  => 'before',
            'selectors'  => [$root => '--zig-menu-anim: {{SIZE}}{{UNIT}};'],
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل › مگامنو
     * =================================================================== */

    /**
     * چیدمانِ داخلیِ مگامنو: ستون‌ها و دکمهٔ بالایِ هر ستون.
     *
     * جدا از بخشِ «پنل» است چون فقط یک آیتم مگامنو می‌گیرد و بقیهٔ
     * تنظیماتِ اینجا برایِ زیرمنویِ ساده بی‌معنی‌اند.
     */
    private function register_mega_style_section(): void {
        $this->start_controls_section('mega_style_section', [
            'label' => __('مگامنو', 'zig3d-widgets'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $root = '{{WRAPPER}} .zig-menu';

        $this->add_responsive_control('mega_pad', [
            'label'      => __('فاصلهٔ داخلیِ مگامنو', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 80]],
            'selectors'  => [$root => '--zig-menu-mega-pad: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_responsive_control('mega_gap', [
            'label'      => __('فاصلهٔ بینِ ستون‌ها', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 80]],
            'selectors'  => [$root => '--zig-menu-mega-gap: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_responsive_control('col_width', [
            'label'      => __('عرضِ ستون', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 160, 'max' => 480]],
            'selectors'  => [$root => '--zig-menu-col-width: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_control('col_gap', [
            'label'      => __('فاصلهٔ دکمه تا سرتیتر', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 60]],
            'selectors'  => [$root => '--zig-menu-col-gap: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_control('col_body_gap', [
            'label'      => __('فاصلهٔ سرتیتر تا ردیف‌ها', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 60]],
            'selectors'  => [$root => '--zig-menu-col-body-gap: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_control('mega_row_pad', [
            'label'      => __('فاصلهٔ عمودیِ ردیفِ مگامنو', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 40]],
            'selectors'  => [$root => '--zig-menu-mega-sub-pad: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_control('cta_heading', [
            'label'     => __('دکمهٔ بالایِ ستون', 'zig3d-widgets'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'     => 'cta_typography',
            'selector' => '{{WRAPPER}} .zig-menu__cta-label',
        ]);

        $this->add_control('cta_color', [
            'label'     => __('رنگِ متن', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [$root => '--zig-menu-cta-color: {{VALUE}};'],
        ]);

        /*
         * پس‌زمینهٔ دکمه در طرح گرادیان است، پس کنترلِ گروهیِ پس‌زمینه —
         * نه یک انتخاب‌گرِ رنگِ ساده که گرادیان را از دست می‌داد.
         *
         * انتخاب‌گر عمداً نامِ تگ دارد: قاعدهٔ پایه ‎.zig-menu a.zig-menu__cta‎
         * است، یعنی ‎(0,2,1)‎. با ‎{{WRAPPER}} .zig-menu__cta‎ که ‎(0,2,0)‎
         * می‌شد، انتخابِ مدیر زیرِ پیش‌فرضِ خودمان دفن می‌شد — همان تلهٔ
         * وزنی که یک بار با کیتِ المنتور خوردیم.
         */
        $this->add_group_control(Group_Control_Background::get_type(), [
            'name'     => 'cta_bg',
            'types'    => ['classic', 'gradient'],
            'selector' => '{{WRAPPER}} .zig-menu a.zig-menu__cta',
        ]);

        $this->add_responsive_control('cta_padding', [
            'label'      => __('فاصلهٔ داخلی', 'zig3d-widgets'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px'],
            'selectors'  => [
                $root => '--zig-menu-cta-pad-block: {{TOP}}{{UNIT}}; --zig-menu-cta-pad-inline: {{RIGHT}}{{UNIT}};',
            ],
        ]);

        $this->add_control('cta_gap', [
            'label'      => __('فاصلهٔ متن تا آیکون', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 60]],
            'selectors'  => [$root => '--zig-menu-cta-gap: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_control('cta_icon_size', [
            'label'      => __('اندازهٔ آیکون', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 8, 'max' => 40]],
            'selectors'  => [$root => '--zig-menu-cta-icon-size: {{SIZE}}{{UNIT}};'],
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل › کارت‌هایِ محصول
     * =================================================================== */

    private function register_card_style_section(): void {
        $this->start_controls_section('card_style_section', [
            'label'     => __('کارت‌هایِ محصول', 'zig3d-widgets'),
            'tab'       => Controls_Manager::TAB_STYLE,
            // بی‌معنی است وقتی کارتی رندر نمی‌شود
            'condition' => ['mega_cards!' => '0'],
        ]);

        $root = '{{WRAPPER}} .zig-menu';

        $this->add_responsive_control('card_cols', [
            'label'     => __('تعدادِ ستون', 'zig3d-widgets'),
            'type'      => Controls_Manager::NUMBER,
            'min'       => 1,
            'max'       => 6,
            'selectors' => [$root => '--zig-menu-card-cols: {{VALUE}};'],
        ]);

        $this->add_responsive_control('card_gap', [
            'label'      => __('فاصلهٔ بینِ کارت‌ها', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 60]],
            'selectors'  => [$root => '--zig-menu-card-gap: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_control('card_bg', [
            'label'     => __('پس‌زمینه', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [$root => '--zig-menu-card-bg: {{VALUE}};'],
        ]);

        $this->add_control('card_border_color', [
            'label'     => __('رنگِ خطِ دور', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [$root => '--zig-menu-card-border: {{VALUE}};'],
        ]);

        $this->add_control('card_radius', [
            'label'      => __('گردیِ گوشه', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 40]],
            'selectors'  => [$root => '--zig-menu-card-radius: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_responsive_control('card_padding', [
            'label'      => __('فاصلهٔ داخلی', 'zig3d-widgets'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px'],
            'selectors'  => [
                $root => '--zig-menu-card-pad-top: {{TOP}}{{UNIT}}; --zig-menu-card-pad-bottom: {{BOTTOM}}{{UNIT}}; --zig-menu-card-pad-inline: {{RIGHT}}{{UNIT}};',
            ],
        ]);

        $this->add_control('card_inner_gap', [
            'label'      => __('فاصلهٔ تصویر تا عنوان', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 60]],
            'selectors'  => [$root => '--zig-menu-card-inner-gap: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_control('card_grid_heading', [
            'label'     => __('شبکهٔ پس‌زمینه', 'zig3d-widgets'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_control('card_grid_color', [
            'label'     => __('رنگِ خطوط', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [$root => '--zig-menu-card-grid: {{VALUE}};'],
        ]);

        $this->add_control('card_grid_step', [
            'label'      => __('فاصلهٔ خطوط', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 4, 'max' => 64]],
            'selectors'  => [$root => '--zig-menu-card-grid-step: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_control('card_title_heading', [
            'label'     => __('عنوانِ کارت', 'zig3d-widgets'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'     => 'card_title_typography',
            'selector' => '{{WRAPPER}} .zig-menu__card-title',
        ]);

        $this->add_control('card_title_color', [
            'label'     => __('رنگِ متن', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [$root => '--zig-menu-card-title-color: {{VALUE}};'],
        ]);

        $this->add_control('card_title_bg', [
            'label'     => __('پس‌زمینه', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [$root => '--zig-menu-card-title-bg: {{VALUE}};'],
        ]);

        $this->add_control('card_title_radius', [
            'label'      => __('گردیِ گوشه', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 40]],
            'selectors'  => [$root => '--zig-menu-card-title-radius: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_control('card_title_pad', [
            'label'      => __('فاصلهٔ داخلی', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 40]],
            'selectors'  => [$root => '--zig-menu-card-title-pad: {{SIZE}}{{UNIT}};'],
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * رندر
     * =================================================================== */

    protected function render(): void {
        $settings = $this->get_settings_for_display();
        $nodes    = Menu_Tree::build((int) ($settings['menu_id'] ?? 0));

        if ([] === $nodes) {
            return;
        }

        printf(
            '<nav class="zig-menu" data-zig-menu aria-label="%s">',
            esc_attr((string) ($settings['aria_label'] ?? ''))
        );

        echo '<ul class="zig-menu__bar" role="list">';

        foreach ($nodes as $node) {
            $this->render_item($node, $settings);
        }

        echo '</ul>';
        echo '</nav>';
    }

    private function render_item(array $node, array $settings): void {
        $panel      = $this->panel_kind($node, $settings);
        $has_panel  = '' !== $panel;

        $classes = ['zig-menu__item'];

        if ($node['current']) {
            $classes[] = 'zig-menu__item--current';
        }

        if ($has_panel) {
            $classes[] = 'zig-menu__item--has-panel';
        }

        printf('<li class="%s">', esc_attr(implode(' ', $classes)));

        /*
         * ‎aria-current‎ فقط وقتی می‌آید که واقعاً همین صفحه باشد. رنگِ
         * متفاوت به‌تنهایی این را به صفحه‌خوان نمی‌رساند.
         */
        printf(
            '<a class="zig-menu__link" href="%s"%s%s>',
            esc_url($node['url']),
            $node['current'] ? ' aria-current="page"' : '',
            '' !== $node['target'] ? ' target="' . esc_attr($node['target']) . '"' : ''
        );

        printf('<span class="zig-menu__label"><bdi>%s</bdi></span>', esc_html($node['title']));

        if ($has_panel) {
            printf(
                '<span class="zig-menu__chevron" aria-hidden="true">%s</span>',
                Design_Icons::get('chevron-down')
            );
        }

        echo '</a>';

        if ($has_panel) {
            $this->render_panel($panel, $node, $settings);
        }

        echo '</li>';
    }

    /**
     * کدام شکلِ زیرمنو؟ رشتهٔ خالی یعنی این آیتم اصلاً پنل ندارد.
     *
     * ترتیب عمدی است: قالبِ المنتور بر همه‌چیز می‌چربد، چون صریح‌ترین
     * انتخابِ مدیر است.
     */
    private function panel_kind(array $node, array $settings): string {
        if (0 !== $this->template_for($node['id'], $settings)) {
            return 'template';
        }

        if ((string) $node['id'] === (string) ($settings['mega_item'] ?? '')) {
            return 'mega';
        }

        return [] !== $node['children'] ? 'list' : '';
    }

    private function template_for(int $item_id, array $settings): int {
        foreach ((array) ($settings['template_panels'] ?? []) as $row) {
            if ((string) $item_id === (string) ($row['item_id'] ?? '')) {
                return (int) ($row['template_id'] ?? 0);
            }
        }

        return 0;
    }

    private function render_panel(string $kind, array $node, array $settings): void {
        printf('<div class="zig-menu__panel zig-menu__panel--%s">', esc_attr($kind));

        if ('template' === $kind) {
            $this->render_template_panel($this->template_for($node['id'], $settings));
        } elseif ('mega' === $kind) {
            $this->render_mega_panel($node, $settings);
        } else {
            $this->render_list_panel($node['children'], $settings);
        }

        echo '</div>';
    }

    /** شکلِ «دانلود نرم‌افزار»: فهرستِ ساده با فلش */
    private function render_list_panel(array $children, array $settings): void {
        if ([] === $children) {
            return;
        }

        echo '<ul class="zig-menu__sub" role="list">';

        foreach ($children as $child) {
            echo '<li class="zig-menu__sub-item">';
            printf('<a class="zig-menu__sub-link" href="%s">', esc_url($child['url']));
            printf('<span class="zig-menu__sub-label"><bdi>%s</bdi></span>', esc_html($child['title']));
            printf('<span class="zig-menu__sub-chevron" aria-hidden="true">%s</span>', Design_Icons::get('chevron'));
            echo '</a>';
            echo '</li>';
        }

        echo '</ul>';
    }

    /** شکلِ «محصولات»: ستون‌ها، هر کدام CTA + سرتیترِ شمارش‌دار + محتوا */
    private function render_mega_panel(array $node, array $settings): void {
        if ([] === $node['children']) {
            return;
        }

        echo '<div class="zig-menu__mega">';

        /*
         * هر دستهٔ فرزند یک ستونِ فهرستی می‌شود، به ترتیبِ خودِ فهرستِ
         * وردپرس. مدیر با کشیدن‌ورهاکردن جایشان را عوض می‌کند.
         */
        foreach ($node['children'] as $column) {
            echo '<div class="zig-menu__col">';

            $this->render_cta($column);

            /*
             * فاصلهٔ سرتیتر تا ردیف‌ها در ستونِ فهرستی ۹ پیکسل است و در
             * ستونِ کارت‌ها ۱۶ — همان تفاوتی که خودِ طرح دارد و از
             * ساختارش هم پیداست: آنجا سرتیتر و ردیف‌ها یک لفافِ مشترک
             * دارند، اینجا ندارند. پس فقط ستونِ فهرستی لفاف می‌گیرد.
             */
            echo '<div class="zig-menu__col-body">';
            $this->render_col_head($column['title'], $column['term_id'] > 0 ? Menu_Tree::term_count($column['term_id']) : 0, $settings);
            $this->render_list_panel($column['children'], $settings);
            echo '</div>';

            echo '</div>';
        }

        $this->render_popular_column($node, $settings);

        echo '</div>';
    }

    /**
     * ستونِ «محبوب‌ترین‌ها» — آخرین ستونِ مگامنو.
     *
     * ستونِ جداست، نه یکی از دسته‌هایِ فهرست: دکمه‌اش به خودِ صفحهٔ
     * «محصولات» می‌رود، شمارشش کلِ فروشگاه است و محتوایش پرفروش‌ترین
     * محصولات — هیچ‌کدام به یک دستهٔ خاص وابسته نیستند.
     */
    private function render_popular_column(array $node, array $settings): void {
        $limit = max(0, (int) ($settings['mega_cards'] ?? 3));

        if ($limit <= 0) {
            return;
        }

        $ids = Menu_Tree::popular_products(0, $limit);

        if ([] === $ids) {
            return;
        }

        echo '<div class="zig-menu__col zig-menu__col--cards">';

        $this->render_cta([
            'url'         => $node['url'],
            'title'       => $node['title'],
            'description' => (string) ($settings['popular_cta'] ?? ''),
        ]);

        $this->render_col_head(
            (string) ($settings['popular_title'] ?? ''),
            Menu_Tree::shop_count(),
            $settings
        );

        $this->render_cards($ids);

        echo '</div>';
    }

    /**
     * دکمهٔ بالایِ ستون.
     *
     * متنش در طرح با عنوانِ ستون فرق دارد («دسته بندی قطعات یدکی» در
     * برابرِ «انواع قطعات»)، پس از فیلدِ *توضیحِ* همان آیتم در فهرستِ
     * وردپرس خوانده می‌شود — فیلدی که برایِ همین‌جور چیزی هست و در پنلِ
     * منو با «گزینه‌هایِ صفحه» روشن می‌شود. نبودش یعنی همان عنوان.
     */
    private function render_cta(array $column): void {
        $label = '' !== trim((string) ($column['description'] ?? ''))
            ? (string) $column['description']
            : (string) $column['title'];

        printf('<a class="zig-menu__cta" href="%s">', esc_url($column['url']));
        printf('<span class="zig-menu__cta-label"><bdi>%s</bdi></span>', esc_html($label));
        printf('<span class="zig-menu__cta-icon" aria-hidden="true">%s</span>', Design_Icons::get('arrow-left'));
        echo '</a>';
    }

    private function render_col_head(string $title, int $count, array $settings): void {
        if ('yes' !== ($settings['show_counts'] ?? 'yes')) {
            $count = 0;
        }

        echo '<div class="zig-menu__col-head">';
        printf('<span class="zig-menu__col-title"><bdi>%s</bdi></span>', esc_html($title));

        if ($count > 0) {
            printf(
                '<span class="zig-menu__count"><bdi>%s</bdi></span>',
                esc_html(sprintf(
                    /* translators: %s: تعدادِ محصول */
                    __('%s محصول', 'zig3d-widgets'),
                    number_format_i18n($count)
                ))
            );
        }

        echo '</div>';
    }

    /** @param int[] $ids */
    private function render_cards(array $ids): void {
        /*
         * یک پاسِ دسته‌ای برایِ تصاویر، پیش از حلقه. بدونِ این، هر کارت
         * یک کوئریِ جدا برایِ پیوستِ تصویرِ شاخص می‌زد — همان N+1ی که در
         * سرچ هم بسته شد.
         */
        if (function_exists('update_post_thumbnail_cache')) {
            update_post_thumbnail_cache(new \WP_Query([
                'post_type'      => 'product',
                'post__in'       => $ids,
                'posts_per_page' => count($ids),
                'no_found_rows'  => true,
            ]));
        }

        echo '<div class="zig-menu__cards">';

        foreach ($ids as $id) {
            printf('<a class="zig-menu__card" href="%s">', esc_url((string) get_permalink($id)));

            $thumb = get_the_post_thumbnail($id, 'medium', ['class' => 'zig-menu__card-image', 'loading' => 'lazy']);

            if ('' !== (string) $thumb) {
                echo '<span class="zig-menu__card-media">' . $thumb . '</span>';
            }

            printf(
                '<span class="zig-menu__card-title"><bdi>%s</bdi></span>',
                esc_html((string) get_the_title($id))
            );

            echo '</a>';
        }

        echo '</div>';
    }

    private function render_template_panel(int $template_id): void {
        if ($template_id <= 0 || !class_exists('\Elementor\Plugin')) {
            return;
        }

        echo \Elementor\Plugin::$instance->frontend->get_builder_content_for_display($template_id);
    }
}
