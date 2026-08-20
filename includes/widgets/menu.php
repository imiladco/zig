<?php
namespace Zig3d_Widgets\Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Background;
use Elementor\Group_Control_Typography;
use Elementor\Icons_Manager;
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

    /**
     * نگاشتِ هر جایگاهِ آیکون به فایلِ صادرشده از فیگما.
     *
     * ‎trigger_icon‎ تنها موردی است که در طرح نیست: فریم‌هایِ موبایل از
     * *بازِ* کشو شروع می‌شوند و دکمهٔ بازکننده را نشان نمی‌دهند. پس یک
     * آیکونِ سه‌خطیِ ساده با همان قلمِ بقیه (میله‌هایِ ۲ پیکسلیِ گرد)
     * ساخته شد و مثلِ بقیه قابلِ جایگزینی است.
     */
    private const DESIGN_ICONS = [
        'bar_chevron_icon' => 'chevron-down',
        'row_chevron_icon' => 'chevron',
        'cta_icon'         => 'arrow-left',
        'trigger_icon'     => 'menu',
        'close_icon'       => 'close',
        'back_icon'        => 'arrow-right',
        'mobile_arrow'     => 'arrow-left',
    ];

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

    public function get_script_depends(): array {
        return ['zig3d-menu'];
    }

    public function has_widget_inner_wrapper(): bool {
        return false;
    }

    protected function register_controls(): void {
        $this->register_source_section();
        $this->register_submenu_section();
        $this->register_mobile_section();
        $this->register_icons_section();
        $this->register_bar_style_section();
        $this->register_panel_style_section();
        $this->register_mega_style_section();
        $this->register_card_style_section();
        $this->register_sheet_style_section();
    }

    /* =====================================================================
     * محتوا › موبایل
     * =================================================================== */

    private function register_mobile_section(): void {
        $this->start_controls_section('mobile_section', ['label' => __('کشویِ موبایل', 'zig3d-widgets')]);

        $this->add_control('open_label', [
            'label'       => __('نامِ دکمهٔ بازکننده', 'zig3d-widgets'),
            'type'        => Controls_Manager::TEXT,
            'default'     => __('منو', 'zig3d-widgets'),
            'description' => __('دیده نمی‌شود؛ فقط صفحه‌خوان می‌خوانَدش. دکمه در طرح آیکونِ تنهاست و بدونِ این نام، صفحه‌خوان فقط «دکمه» می‌گوید.', 'zig3d-widgets'),
        ]);

        $this->add_control('close_label', [
            'label'   => __('متنِ بستن', 'zig3d-widgets'),
            'type'    => Controls_Manager::TEXT,
            'default' => __('بستن', 'zig3d-widgets'),
        ]);

        $this->add_control('back_label', [
            'label'       => __('متنِ بازگشت', 'zig3d-widgets'),
            'type'        => Controls_Manager::TEXT,
            'default'     => __('بازگشت', 'zig3d-widgets'),
            'description' => __('همان دکمه است: در لایهٔ اول می‌بندد، در لایه‌هایِ تودرتو یک پله برمی‌گردد.', 'zig3d-widgets'),
        ]);

        $this->add_control('sheet_logo', [
            'label'       => __('لوگویِ بالایِ کشو', 'zig3d-widgets'),
            'type'        => Controls_Manager::MEDIA,
            'description' => __('خالی بگذارید تا اصلاً نیاید.', 'zig3d-widgets'),
        ]);

        $this->add_control('sheet_template', [
            'label'       => __('قالبِ پایینِ کشو', 'zig3d-widgets'),
            'type'        => Controls_Manager::SELECT,
            'options'     => $this->template_options(),
            'label_block' => true,
            'description' => __('در طرح، بلوکِ راه‌هایِ تماس است. چون محتوایش ربطی به منو ندارد، یک قالبِ المنتور می‌گیرد — مثلاً همان ویجتِ «اطلاعاتِ تماس».', 'zig3d-widgets'),
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * محتوا › آیکون‌ها
     * =================================================================== */

    private function register_icons_section(): void {
        $this->start_controls_section('icons_section', ['label' => __('آیکون‌ها', 'zig3d-widgets')]);

        /*
         * همان سویچِ ویجتِ سرچ و به همان دلیل: کنترلِ ICONSِ المنتور
         * «هیچ آیکون» و «دست‌نخورده» را از هم تفکیک نمی‌کند، پس معنیِ
         * «خالی» باید جایی صریح تعیین شود.
         */
        $this->add_control('design_icons', [
            'label'        => __('آیکون‌هایِ پیش‌فرضِ طرح', 'zig3d-widgets'),
            'type'         => Controls_Manager::SWITCHER,
            'default'      => 'yes',
            'label_on'     => __('روشن', 'zig3d-widgets'),
            'label_off'    => __('خاموش', 'zig3d-widgets'),
            'return_value' => 'yes',
            'description'  => __('روشن باشد، هر آیکونی که خالی بگذارید همان SVGی طرح را می‌گیرد. خاموشش کنید تا آیکونِ خالی واقعاً حذف شود.', 'zig3d-widgets'),
        ]);

        $icons = [
            'bar_chevron_icon' => __('فلشِ آیتمِ نوار', 'zig3d-widgets'),
            'row_chevron_icon' => __('فلشِ ردیفِ زیرمنو', 'zig3d-widgets'),
            'cta_icon'         => __('آیکونِ دکمهٔ ستونِ مگامنو', 'zig3d-widgets'),
            'trigger_icon'     => __('آیکونِ دکمهٔ بازکنندهٔ موبایل', 'zig3d-widgets'),
            'close_icon'       => __('آیکونِ بستنِ کشو', 'zig3d-widgets'),
            'back_icon'        => __('آیکونِ بازگشتِ کشو', 'zig3d-widgets'),
            'mobile_arrow'     => __('فلشِ ردیفِ فرزنددارِ موبایل', 'zig3d-widgets'),
        ];

        foreach ($icons as $key => $label) {
            $this->add_control($key, [
                'label'       => $label,
                'type'        => Controls_Manager::ICONS,
                'default'     => ['value' => '', 'library' => ''],
                'description' => __('خالی یعنی آیکونِ خودِ طرح.', 'zig3d-widgets'),
            ]);
        }

        $this->end_controls_section();
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

        /*
         * ستون‌هایِ دسته: ردیف‌هایشان از فهرستِ وردپرس می‌آیند (چون
         * پیوندِ واقعیِ دسته‌ها آنجاست) ولی *متنِ* دکمه و سرتیتر دستی
         * است. در طرح این دو با هم فرق دارند — «دسته بندی قطعات یدکی» در
         * برابرِ «انواع قطعات» — و هیچ فیلدی در فهرستِ وردپرس نیست که
         * این تفاوت را طبیعی نگه دارد.
         *
         * تطبیق ترتیبی است: ردیفِ اول همان ستونِ اول. این‌طور پیش‌فرض‌ها
         * بدونِ دانستنِ شناسهٔ آیتم‌ها همان لحظه کار می‌کنند.
         */
        $column = new Repeater();

        $column->add_control('column_title', [
            'label'       => __('عنوانِ ستون', 'zig3d-widgets'),
            'type'        => Controls_Manager::TEXT,
            'label_block' => true,
            'description' => __('خالی یعنی همان عنوانِ آیتم در فهرستِ وردپرس.', 'zig3d-widgets'),
        ]);

        $column->add_control('column_cta', [
            'label'       => __('متنِ دکمه', 'zig3d-widgets'),
            'type'        => Controls_Manager::TEXT,
            'label_block' => true,
        ]);

        $this->add_control('mega_columns', [
            'label'       => __('ستون‌هایِ مگامنو', 'zig3d-widgets'),
            'type'        => Controls_Manager::REPEATER,
            'fields'      => $column->get_controls(),
            'title_field' => '{{{ column_title || column_cta }}}',
            'condition'   => ['mega_item!' => ''],
            'default'     => [
                [
                    'column_title' => __('انواع قطعات', 'zig3d-widgets'),
                    'column_cta'   => __('دسته بندی قطعات یدکی', 'zig3d-widgets'),
                ],
                [
                    'column_title' => __('انواع مواد و متریال', 'zig3d-widgets'),
                    'column_cta'   => __('دسته بندی مواد و متریال دندانسازی', 'zig3d-widgets'),
                ],
            ],
            'description' => __('ردیفِ اول ستونِ اول است، ردیفِ دوم ستونِ دوم. ردیف‌هایِ زیرِ هر ستون از خودِ فهرستِ وردپرس می‌آیند.', 'zig3d-widgets'),
        ]);

        $this->add_control('mega_cards', [
            'label'       => __('تعدادِ کارتِ محصول در مگامنو', 'zig3d-widgets'),
            'type'        => Controls_Manager::NUMBER,
            'min'         => 0,
            'max'         => 6,
            'default'     => 3,
            'condition'   => ['mega_item!' => ''],
            'separator'   => 'before',
            'description' => __('کارت‌ها یک ستونِ جداگانه در انتهایِ مگامنو می‌سازند. اگر فهرستِ زیر پر باشد همان‌ها می‌آیند؛ خالی که باشد، پرفروش‌ترین‌هایِ ووکامرس خودکار پر می‌شوند. صفر یعنی این ستون اصلاً نباشد.', 'zig3d-widgets'),
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

        /*
         * کارت‌هایِ دستی. پر بودنِ این فهرست بر کوئریِ خودکار می‌چربد،
         * چون انتخابِ صریحِ مدیر است — ولی خالی گذاشتنش هنوز همان رفتارِ
         * «خودکار از ووکامرس، با کش» را می‌دهد.
         */
        $card = new Repeater();

        $card->add_control('card_title', [
            'label'       => __('نام', 'zig3d-widgets'),
            'type'        => Controls_Manager::TEXT,
            'label_block' => true,
        ]);

        $card->add_control('card_image', [
            'label' => __('تصویر', 'zig3d-widgets'),
            'type'  => Controls_Manager::MEDIA,
        ]);

        $card->add_control('card_link', [
            'label'       => __('پیوند', 'zig3d-widgets'),
            'type'        => Controls_Manager::URL,
            'label_block' => true,
        ]);

        $this->add_control('mega_card_items', [
            'label'       => __('کارت‌هایِ دستی', 'zig3d-widgets'),
            'type'        => Controls_Manager::REPEATER,
            'fields'      => $card->get_controls(),
            'title_field' => '{{{ card_title }}}',
            'condition'   => ['mega_item!' => '', 'mega_cards!' => '0'],
            'default'     => [
                ['card_title' => __('کوره سینتر زیرکونیا', 'zig3d-widgets')],
                ['card_title' => __('اسکنر سه بعدی', 'zig3d-widgets')],
                ['card_title' => __('میلینگ ماشین', 'zig3d-widgets')],
            ],
            'description' => __('خالی‌شان کنید تا به‌جایشان پرفروش‌ترین‌هایِ ووکامرس بیایند.', 'zig3d-widgets'),
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
     * آیتم‌هایِ سطحِ اولِ *همهٔ* فهرست‌ها — برایِ کنترل‌هایی که باید به یک
     * آیتمِ مشخص اشاره کنند.
     *
     * دو نکته که هر دو یک بار اشتباه شدند:
     *
     * ۱. اینجا نباید ‎get_settings()‎ صدا زده شود. این تابع از داخلِ
     *    ‎register_controls()‎ فراخوانی می‌شود و ‎get_settings()‎ برایِ
     *    آماده‌کردنِ تنظیمات دوباره سراغِ همان ثبتِ کنترل‌ها می‌رود —
     *    یعنی بازگشتِ بی‌پایان. نتیجه‌اش این بود که پنلِ ویجت در ویرایشگر
     *    اصلاً بالا نمی‌آمد.
     *
     * ۲. حتی اگر بازگشتی هم نبود، در لحظهٔ ثبتِ کنترل‌ها هنوز ‎menu_id‎
     *    انتخاب نشده. پس فهرستِ گزینه‌ها خالی می‌ماند و «کدام آیتم مگامنو
     *    باشد؟» هیچ‌وقت گزینه‌ای نداشت — مگامنو عملاً غیرقابلِ روشن‌کردن
     *    بود.
     *
     * راهِ درست: به ‎menu_id‎ کاری نداشته باشیم. شناسهٔ آیتمِ منو در
     * وردپرس سراسری یکتاست، پس آیتم‌هایِ همهٔ فهرست‌ها می‌توانند کنارِ هم
     * بیایند؛ نامِ فهرست جلویشان می‌آید تا وقتی چند فهرست هست هم روشن
     * بماند کدام از کجاست.
     *
     * @return array<string,string>
     */
    private function top_level_options(): array {
        if (!function_exists('wp_get_nav_menus')) {
            return [];
        }

        $menus   = wp_get_nav_menus();
        $several = count($menus) > 1;
        $options = [];

        foreach ($menus as $menu) {
            foreach (Menu_Tree::build((int) $menu->term_id) as $node) {
                $options[(string) $node['id']] = $several
                    ? $node['title'] . ' — ' . $menu->name
                    : $node['title'];
            }
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

        $this->add_control('scrim_heading', [
            'label'     => __('پردهٔ پشتِ زیرمنو', 'zig3d-widgets'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_control('scrim_color', [
            'label'       => __('رنگِ پرده', 'zig3d-widgets'),
            'type'        => Controls_Manager::COLOR,
            'selectors'   => [$root => '--zig-menu-scrim: {{VALUE}};'],
            'description' => __('وقتی زیرمنو باز است، پشتِ صفحه تیره می‌شود تا چشم رویِ خودِ زیرمنو بنشیند. کاملاً شفافش کنید تا اصلاً دیده نشود.', 'zig3d-widgets'),
        ]);

        $this->add_control('anim_heading', [
            'label'     => __('حرکت', 'zig3d-widgets'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_control('panel_anim', [
            'label'      => __('زمانِ باز و بسته شدن', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['ms'],
            'range'      => ['ms' => ['min' => 0, 'max' => 900, 'step' => 10]],
            'selectors'  => [$root => '--zig-menu-anim: {{SIZE}}{{UNIT}};'],
        ]);

        /*
         * زاویهٔ تا خوردن. ۹۰ درجه یعنی پنل کاملاً لبه‌به‌لبه شروع کند
         * (تا خوردنِ کامل)؛ عددهایِ کمتر همان حس را نرم‌تر می‌دهند.
         */
        $this->add_control('panel_fold', [
            'label'       => __('زاویهٔ تا خوردن', 'zig3d-widgets'),
            'type'        => Controls_Manager::SLIDER,
            'size_units'  => ['deg'],
            'range'       => ['deg' => ['min' => 0, 'max' => 90]],
            'selectors'   => [$root => '--zig-menu-fold: {{SIZE}}{{UNIT}};'],
            'description' => __('پنل از بالا لولا می‌خورد و باز می‌شود. صفر یعنی بدونِ تا خوردن، فقط محو شدن.', 'zig3d-widgets'),
        ]);

        $this->add_control('panel_perspective', [
            'label'       => __('عمقِ پرسپکتیو', 'zig3d-widgets'),
            'type'        => Controls_Manager::SLIDER,
            'size_units'  => ['px'],
            'range'       => ['px' => ['min' => 200, 'max' => 4000, 'step' => 50]],
            'selectors'   => [$root => '--zig-menu-perspective: {{SIZE}}{{UNIT}};'],
            'description' => __('هرچه کمتر، تا خوردن اغراق‌آمیزتر. عددِ بزرگ حرکت را تخت‌تر می‌کند.', 'zig3d-widgets'),
        ]);

        $this->add_control('underline_grow', [
            'label'       => __('کشیدگیِ اولیهٔ زیرخط', 'zig3d-widgets'),
            'type'        => Controls_Manager::SLIDER,
            'range'       => ['px' => ['min' => 0, 'max' => 1, 'step' => 0.05]],
            'selectors'   => [$root => '--zig-menu-underline-grow: {{SIZE}};'],
            'description' => __('زیرخط از این نسبت تا عرضِ کامل باز می‌شود. یک یعنی بدونِ کشیدگی.', 'zig3d-widgets'),
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
     * استایل › کشویِ موبایل
     * =================================================================== */

    private function register_sheet_style_section(): void {
        $this->start_controls_section('sheet_style_section', [
            'label' => __('کشویِ موبایل', 'zig3d-widgets'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $root = '{{WRAPPER}} .zig-menu';

        /*
         * طرح فقط کشویِ *باز* را نشان می‌دهد و نمی‌گوید از کدام لبه
         * می‌آید. به‌جایِ حدس‌زدن، خودِ جهت یک تنظیم شد.
         */
        $this->add_control('sheet_from', [
            'label'   => __('از کدام لبه باز شود', 'zig3d-widgets'),
            'type'    => Controls_Manager::SELECT,
            'default' => 'start',
            'options' => [
                'start'  => __('لبهٔ شروع (در راست‌چین: راست)', 'zig3d-widgets'),
                'end'    => __('لبهٔ پایان (در راست‌چین: چپ)', 'zig3d-widgets'),
                'bottom' => __('پایین', 'zig3d-widgets'),
            ],
        ]);

        /*
         * برک‌پوینت عمداً کنترل ندارد: عددش باید داخلِ ‎@media‎ بنشیند و
         * ‎@media‎ متغیرِ CSS نمی‌خوانَد. کنترلی که بنویسم بی‌اثر می‌ماند
         * و بدتر از نبودنش است. عدد ۱۰۲۳ است — همان مرزِ تبلتِ المنتور.
         */
        $this->add_control('trigger_color', [
            'label'     => __('رنگِ دکمهٔ بازکننده', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [$root => '--zig-menu-trigger-color: {{VALUE}};'],
        ]);

        $this->add_control('trigger_size', [
            'label'      => __('اندازهٔ آیکونِ بازکننده', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 12, 'max' => 48]],
            'selectors'  => [$root => '--zig-menu-trigger-size: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_control('sheet_bg', [
            'label'     => __('پس‌زمینهٔ کشو', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [$root => '--zig-menu-sheet-bg: {{VALUE}};'],
        ]);

        $this->add_control('sheet_backdrop', [
            'label'     => __('رنگِ پرده', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [$root => '--zig-menu-sheet-backdrop: {{VALUE}};'],
        ]);

        $this->add_responsive_control('sheet_width', [
            'label'      => __('عرضِ کشو', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', '%', 'vw'],
            'range'      => ['px' => ['min' => 240, 'max' => 720], '%' => ['min' => 40, 'max' => 100], 'vw' => ['min' => 40, 'max' => 100]],
            'selectors'  => [$root => '--zig-menu-sheet-width: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_responsive_control('sheet_pad', [
            'label'      => __('فاصلهٔ داخلیِ کشو', 'zig3d-widgets'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px'],
            'selectors'  => [
                $root => '--zig-menu-sheet-pad-block: {{TOP}}{{UNIT}}; --zig-menu-sheet-pad-inline: {{RIGHT}}{{UNIT}};',
            ],
        ]);

        $this->add_control('sheet_gap', [
            'label'      => __('فاصلهٔ سربرگ تا فهرست', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 80]],
            'selectors'  => [$root => '--zig-menu-sheet-gap: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_control('sheet_back_heading', [
            'label'     => __('دکمهٔ بستن/بازگشت', 'zig3d-widgets'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'     => 'sheet_back_typography',
            'selector' => '{{WRAPPER}} .zig-menu__sheet-back-label',
        ]);

        $this->add_control('sheet_back_color', [
            'label'     => __('رنگ', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [$root => '--zig-menu-back-color: {{VALUE}};'],
        ]);

        $this->add_control('sheet_back_bg', [
            'label'     => __('پس‌زمینه', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [$root => '--zig-menu-back-bg: {{VALUE}};'],
        ]);

        $this->add_control('sheet_back_radius', [
            'label'      => __('گردیِ گوشه', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 32]],
            'selectors'  => [$root => '--zig-menu-back-radius: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_control('sheet_back_pad', [
            'label'      => __('فاصلهٔ داخلی', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 40]],
            'selectors'  => [$root => '--zig-menu-back-pad: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_control('sheet_back_gap', [
            'label'      => __('فاصلهٔ متن تا آیکون', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 32]],
            'selectors'  => [$root => '--zig-menu-back-gap: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_control('sheet_back_icon_size', [
            'label'      => __('اندازهٔ آیکون', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 8, 'max' => 32]],
            'selectors'  => [$root => '--zig-menu-back-icon-size: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_control('sheet_logo_height', [
            'label'      => __('ارتفاعِ لوگو', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 16, 'max' => 120]],
            'selectors'  => [$root => '--zig-menu-sheet-logo-height: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_control('sheet_card_heading', [
            'label'     => __('کارتِ فهرست', 'zig3d-widgets'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_control('sheet_card_bg', [
            'label'     => __('پس‌زمینه', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [$root => '--zig-menu-m-card-bg: {{VALUE}};'],
        ]);

        $this->add_control('sheet_card_radius', [
            'label'      => __('گردیِ گوشه', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 40]],
            'selectors'  => [$root => '--zig-menu-m-card-radius: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_control('sheet_card_pad', [
            'label'      => __('فاصلهٔ داخلی', 'zig3d-widgets'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px'],
            'selectors'  => [
                $root => '--zig-menu-m-card-pad-block: {{TOP}}{{UNIT}}; --zig-menu-m-card-pad-inline: {{RIGHT}}{{UNIT}};',
            ],
        ]);

        $this->add_control('sheet_row_heading', [
            'label'     => __('ردیف‌ها', 'zig3d-widgets'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'     => 'sheet_row_typography',
            'selector' => '{{WRAPPER}} .zig-menu__m-label',
        ]);

        $this->add_control('sheet_row_color', [
            'label'     => __('رنگِ متن', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [$root => '--zig-menu-m-color: {{VALUE}};'],
        ]);

        $this->add_control('sheet_row_pad', [
            'label'      => __('فاصلهٔ عمودی', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 40]],
            'selectors'  => [$root => '--zig-menu-m-pad: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_control('sheet_row_divider', [
            'label'     => __('رنگِ خطِ جداکننده', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [$root => '--zig-menu-m-divider: {{VALUE}};'],
        ]);

        $this->add_control('sheet_row_arrow_size', [
            'label'      => __('اندازهٔ فلش', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 8, 'max' => 32]],
            'selectors'  => [$root => '--zig-menu-m-arrow-size: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_control('sheet_count_color', [
            'label'     => __('رنگِ شمارش', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [$root => '--zig-menu-m-count-color: {{VALUE}};'],
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

        /*
         * جهتِ باز شدنِ کشو یک کلاس است نه یک متغیرِ CSS: مقادیرش سه
         * چیدمانِ متفاوت‌اند (نه سه عدد)، و هر کدام مجموعهٔ خودش از
         * ‎inset‎ و ‎transform‎ را لازم دارد.
         */
        $from = (string) ($settings['sheet_from'] ?? 'start');

        printf(
            '<nav class="zig-menu zig-menu--sheet-%s" data-zig-menu aria-label="%s">',
            esc_attr(in_array($from, ['start', 'end', 'bottom'], true) ? $from : 'start'),
            esc_attr((string) ($settings['aria_label'] ?? ''))
        );

        $this->render_trigger($settings);

        echo '<ul class="zig-menu__bar" role="list">';

        foreach ($nodes as $node) {
            $this->render_item($node, $settings);
        }

        echo '</ul>';

        /* پردهٔ پشتِ زیرمنویِ دسکتاپ — تزئینی، پس از دیدِ صفحه‌خوان بیرون */
        echo '<div class="zig-menu__scrim" aria-hidden="true"></div>';

        $this->render_sheet($nodes, $settings);

        echo '</nav>';
    }

    /* =====================================================================
     * موبایل
     *
     * نسخهٔ موبایل همان درخت است با ناوبریِ دیگری: به‌جایِ باز شدنِ پنل
     * زیرِ آیتم، یک کشویِ تمام‌صفحه که لایه‌به‌لایه تو می‌رود. هر دو
     * نسخه از یک درخت رندر می‌شوند و هیچ‌کدام دیگری را با
     * ‎display: none‎ی شرطی تقلید نمی‌کند — کدام یک دیده شود را فقط
     * برک‌پوینت تعیین می‌کند.
     *
     * همهٔ لایه‌ها همان اول در DOM هستند و جابه‌جاییِ بینشان فقط عوض
     * کردنِ یک صفت است. دلیلش رفتارِ صفحه‌خوان و دکمهٔ بازگشت است: با
     * ساختنِ لایه در لحظه، فوکوس هر بار می‌پرید.
     * =================================================================== */

    private function render_trigger(array $settings): void {
        printf(
            '<button type="button" class="zig-menu__trigger" aria-expanded="false" aria-controls="%s">',
            esc_attr($this->sheet_id())
        );

        printf('<span class="zig-menu__trigger-icon" aria-hidden="true">%s</span>', $this->render_icon($settings, 'trigger_icon'));
        printf('<span class="zig-menu__sr">%s</span>', esc_html($this->label($settings, 'open_label', __('منو', 'zig3d-widgets'))));

        echo '</button>';
    }

    private function render_sheet(array $nodes, array $settings): void {
        echo '<div class="zig-menu__backdrop"></div>';

        printf('<div class="zig-menu__sheet" id="%s" role="dialog" aria-modal="true" aria-label="%s">',
            esc_attr($this->sheet_id()),
            esc_attr($this->label($settings, 'aria_label', __('منو', 'zig3d-widgets')))
        );

        echo '<div class="zig-menu__sheet-head">';

        /*
         * یک دکمه، دو کار: در لایهٔ صفر می‌بندد و در لایه‌هایِ تودرتو یک
         * پله برمی‌گردد. متن و آیکونش را اسکریپت عوض می‌کند، پس هر دو
         * حالت همین‌جا در دسترسِ اسکریپت گذاشته می‌شوند تا رشتهٔ فارسی
         * داخلِ جاوااسکریپت هاردکد نشود و از تبِ محتوا قابلِ تغییر بماند.
         */
        printf(
            '<button type="button" class="zig-menu__sheet-back" data-close-label="%s" data-back-label="%s">',
            esc_attr($this->label($settings, 'close_label', __('بستن', 'zig3d-widgets'))),
            esc_attr($this->label($settings, 'back_label', __('بازگشت', 'zig3d-widgets')))
        );

        printf('<span class="zig-menu__sheet-back-label">%s</span>', esc_html($this->label($settings, 'close_label', __('بستن', 'zig3d-widgets'))));
        printf('<span class="zig-menu__sheet-back-icon" data-icon="close" aria-hidden="true">%s</span>', $this->render_icon($settings, 'close_icon'));
        printf('<span class="zig-menu__sheet-back-icon" data-icon="back" aria-hidden="true">%s</span>', $this->render_icon($settings, 'back_icon'));

        echo '</button>';

        $logo = (array) ($settings['sheet_logo'] ?? []);

        if ('' !== (string) ($logo['url'] ?? '')) {
            printf(
                '<span class="zig-menu__sheet-logo"><img src="%s" alt="" /></span>',
                esc_url((string) $logo['url'])
            );
        }

        echo '</div>';

        echo '<div class="zig-menu__sheet-body">';

        $this->render_sheet_level($nodes, '', $settings);

        foreach ($nodes as $node) {
            $this->render_sheet_branch($node, $settings);
        }

        echo '</div>';

        $template = (int) ($settings['sheet_template'] ?? 0);

        if ($template > 0) {
            echo '<div class="zig-menu__sheet-foot">';
            $this->render_template_panel($template);
            echo '</div>';
        }

        echo '</div>';
    }

    /** لایه‌هایِ تودرتو، برایِ هر آیتمی که فرزند دارد */
    private function render_sheet_branch(array $node, array $settings): void {
        if ([] === $node['children']) {
            return;
        }

        $this->render_sheet_level($node['children'], (string) $node['id'], $settings, $node);

        foreach ($node['children'] as $child) {
            $this->render_sheet_branch($child, $settings);
        }
    }

    /**
     * یک لایهٔ کشو.
     *
     * ‎$parent‎ی که داده شود یعنی این لایه تودرتوست و باید ردیفِ
     * «مشاهده …» را بالایِ خودش داشته باشد — همان ردیفی که در طرح هست و
     * تنها راهِ رسیدن به *خودِ* صفحهٔ والد است، چون در موبایل تپ رویِ
     * ردیفِ والد تو می‌رود نه به صفحه.
     */
    private function render_sheet_level(array $nodes, string $key, array $settings, ?array $parent = null): void {
        printf(
            '<ul class="zig-menu__level" role="list" data-level="%s"%s>',
            esc_attr('' === $key ? 'root' : $key),
            null === $parent ? '' : ' hidden'
        );

        if (null !== $parent) {
            printf('<li class="zig-menu__m-item"><a class="zig-menu__m-link" href="%s">', esc_url($parent['url']));
            printf(
                '<span class="zig-menu__m-label"><bdi>%s</bdi></span>',
                esc_html(sprintf(
                    /* translators: %s: نامِ دستهٔ والد */
                    __('مشاهده %s', 'zig3d-widgets'),
                    $parent['title']
                ))
            );
            $this->render_m_count($parent['term_id'] > 0 ? Menu_Tree::term_count($parent['term_id']) : Menu_Tree::shop_count(), $settings);
            echo '</a></li>';
        }

        foreach ($nodes as $node) {
            $has_children = [] !== $node['children'];

            echo '<li class="zig-menu__m-item">';

            /*
             * ردیفِ فرزنددار دکمه است نه پیوند: تپ رویش یک پله تو می‌رود.
             * رفتن به خودِ صفحه‌اش کارِ ردیفِ «مشاهده …» در لایهٔ بعدی
             * است — همان تفکیکی که طرح دارد.
             */
            if ($has_children) {
                printf(
                    '<button type="button" class="zig-menu__m-link" data-open-level="%s" aria-expanded="false">',
                    esc_attr((string) $node['id'])
                );
            } else {
                printf(
                    '<a class="zig-menu__m-link" href="%s"%s>',
                    esc_url($node['url']),
                    $node['current'] ? ' aria-current="page"' : ''
                );
            }

            printf('<span class="zig-menu__m-label"><bdi>%s</bdi></span>', esc_html($node['title']));

            $this->render_m_count($node['term_id'] > 0 ? Menu_Tree::term_count($node['term_id']) : 0, $settings);

            if ($has_children) {
                printf(
                    '<span class="zig-menu__m-arrow" aria-hidden="true">%s</span>',
                    $this->render_icon($settings, 'mobile_arrow')
                );
            }

            echo $has_children ? '</button>' : '</a>';
            echo '</li>';
        }

        echo '</ul>';
    }

    private function render_m_count(int $count, array $settings): void {
        if ($count <= 0 || 'yes' !== ($settings['show_counts'] ?? 'yes')) {
            return;
        }

        printf(
            '<span class="zig-menu__m-count"><bdi>%s</bdi></span>',
            esc_html(sprintf(
                /* translators: %s: تعدادِ محصول */
                __('%s محصول', 'zig3d-widgets'),
                number_format_i18n($count)
            ))
        );
    }

    private function label(array $settings, string $key, string $fallback): string {
        $value = trim((string) ($settings[$key] ?? ''));

        return '' !== $value ? $value : $fallback;
    }

    /**
     * آیکونِ یک جایگاه: انتخابِ مدیر، وگرنه SVGی خودِ طرح.
     *
     * همان قراردادِ ویجتِ سرچ، با همان دلیل: کنترلِ ICONSِ المنتور حالتِ
     * «هیچ» ندارد، پس سویچِ ‎design_icons‎ معنیِ «خالی» را تعیین می‌کند.
     */
    private function render_icon(array $settings, string $key): string {
        $icon = $settings[$key] ?? [];

        if (!empty($icon['value'])) {
            ob_start();
            Icons_Manager::render_icon($icon, ['aria-hidden' => 'true']);

            return (string) ob_get_clean();
        }

        if ('yes' !== ($settings['design_icons'] ?? 'yes')) {
            return '';
        }

        return isset(self::DESIGN_ICONS[$key])
            ? Design_Icons::get(self::DESIGN_ICONS[$key])
            : '';
    }

    /** شناسهٔ یکتا برایِ ‎aria-controls‎ — چند ویجت در یک صفحه ممکن است */
    private function sheet_id(): string {
        return 'zig-menu-sheet-' . $this->get_id();
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
                $this->render_icon($settings, 'bar_chevron_icon')
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
            printf('<span class="zig-menu__sub-chevron" aria-hidden="true">%s</span>', $this->render_icon($settings, 'row_chevron_icon'));
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
        $overrides = array_values((array) ($settings['mega_columns'] ?? []));

        foreach ($node['children'] as $index => $column) {
            /*
             * تطبیقِ ترتیبی: ردیفِ n اُمِ تنظیمات، ستونِ n اُم. ستونی که
             * ردیفی نداشته باشد دست‌نخورده از فهرستِ وردپرس می‌آید، پس
             * افزودنِ یک دستهٔ تازه چیزی را نمی‌شکند.
             */
            $override = $overrides[$index] ?? [];

            $column['title'] = $this->pick($override, 'column_title', $column['title']);

            /*
             * متنِ دکمه سه جا را به ترتیب می‌گردد: تنظیماتِ همین ستون،
             * فیلدِ توضیحِ آیتم در فهرستِ وردپرس، و آخر عنوانِ خودش.
             */
            $column['description'] = $this->pick($override, 'column_cta', (string) ($column['description'] ?? ''));

            echo '<div class="zig-menu__col">';

            $this->render_cta($column, $settings);

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

        $manual = $this->manual_cards($settings, $limit);
        $ids    = [] === $manual ? Menu_Tree::popular_products(0, $limit) : [];

        if ([] === $manual && [] === $ids) {
            return;
        }

        echo '<div class="zig-menu__col zig-menu__col--cards">';

        $this->render_cta([
            'url'         => $node['url'],
            'title'       => $node['title'],
            'description' => (string) ($settings['popular_cta'] ?? ''),
        ], $settings);

        $this->render_col_head(
            (string) ($settings['popular_title'] ?? ''),
            Menu_Tree::shop_count(),
            $settings
        );

        if ([] !== $manual) {
            $this->render_manual_cards($manual);
        } else {
            $this->render_cards($ids);
        }

        echo '</div>';
    }

    /**
     * کارت‌هایی که مدیر دستی نوشته — فقط ردیف‌هایی که واقعاً نامی دارند.
     *
     * ردیفِ بی‌نام یعنی مدیر ردیف را اضافه کرده و هنوز پرش نکرده؛ رندرش
     * یک کارتِ خالی می‌ساخت.
     *
     * @return array<int,array<string,mixed>>
     */
    private function manual_cards(array $settings, int $limit): array {
        $rows = [];

        foreach ((array) ($settings['mega_card_items'] ?? []) as $row) {
            if ('' === trim((string) ($row['card_title'] ?? ''))) {
                continue;
            }

            $rows[] = $row;

            if (count($rows) >= $limit) {
                break;
            }
        }

        return $rows;
    }

    /** @param array<int,array<string,mixed>> $rows */
    private function render_manual_cards(array $rows): void {
        echo '<div class="zig-menu__cards">';

        foreach ($rows as $index => $row) {
            $url   = trim((string) ($row['card_link']['url'] ?? ''));
            $image = (string) ($row['card_image']['url'] ?? '');

            /*
             * بی‌پیوند، کارت یک ‎span‎ است نه ‎a‎ی بی‌مقصد — همان قراردادِ
             * ویجتِ اطلاعاتِ تماس. لینکی که جایی نمی‌برد، برایِ صفحه‌خوان
             * و کیبورد یک ایستگاهِ بی‌فایده است.
             */
            if ('' !== $url) {
                $key = 'card_' . $index;
                $this->add_render_attribute($key, 'class', 'zig-menu__card');
                $this->add_link_attributes($key, $row['card_link']);

                printf('<a %s>', $this->get_render_attribute_string($key));
            } else {
                echo '<span class="zig-menu__card">';
            }

            if ('' !== $image) {
                printf(
                    '<span class="zig-menu__card-media"><img class="zig-menu__card-image" src="%s" alt="" loading="lazy" /></span>',
                    esc_url($image)
                );
            }

            printf(
                '<span class="zig-menu__card-title"><bdi>%s</bdi></span>',
                esc_html((string) $row['card_title'])
            );

            echo '' !== $url ? '</a>' : '</span>';
        }

        echo '</div>';
    }

    /** اولین مقدارِ ناخالیِ تنظیمات، وگرنه پیش‌فرض */
    private function pick(array $row, string $key, string $fallback): string {
        $value = trim((string) ($row[$key] ?? ''));

        return '' !== $value ? $value : $fallback;
    }

    /**
     * دکمهٔ بالایِ ستون.
     *
     * متنش در طرح با عنوانِ ستون فرق دارد («دسته بندی قطعات یدکی» در
     * برابرِ «انواع قطعات»)، پس از فیلدِ *توضیحِ* همان آیتم در فهرستِ
     * وردپرس خوانده می‌شود — فیلدی که برایِ همین‌جور چیزی هست و در پنلِ
     * منو با «گزینه‌هایِ صفحه» روشن می‌شود. نبودش یعنی همان عنوان.
     */
    private function render_cta(array $column, array $settings): void {
        $label = '' !== trim((string) ($column['description'] ?? ''))
            ? (string) $column['description']
            : (string) $column['title'];

        printf('<a class="zig-menu__cta" href="%s">', esc_url($column['url']));
        printf('<span class="zig-menu__cta-label"><bdi>%s</bdi></span>', esc_html($label));
        printf('<span class="zig-menu__cta-icon" aria-hidden="true">%s</span>', $this->render_icon($settings, 'cta_icon'));
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
