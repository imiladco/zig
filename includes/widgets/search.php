<?php
namespace Zig3d_Widgets\Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Icons_Manager;
use Elementor\Repeater;
use Elementor\Widget_Base;
use Zig3d_Widgets\Archive_Query;
use Zig3d_Widgets\Attributes;
use Zig3d_Widgets\Design_Icons;
use Zig3d_Widgets\Plugin;
use Zig3d_Widgets\Search_Endpoint;
use Zig3d_Widgets\Search_Query;
use Zig3d_Widgets\Selector;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * سرچِ ایجکسیِ محصولات.
 *
 * دیزاین دقیقاً از رویِ طرحِ تأییدشده پیاده شده — بدونِ هیچ بازطراحی —
 * و کاملاً از تبِ استایلِ المنتور قابلِ‌شخصی‌سازی است. شش حالت دارد:
 *
 *   S0  بسته/پیش‌فرض — فقط فیلد، بدونِ دکمهٔ ضربدر.
 *   S1  باز، خالی، بدونِ تاریخچه — فقط «جستجوهایِ پرطرفدار».
 *   S2  باز، خالی، با تاریخچه — «جستجوهایِ اخیر» + «پرطرفدار».
 *   S3  باز، نتیجه دارد — محصولات + دکمهٔ «بیشتر» (اگر لازم) + پرطرفدار.
 *   S4  باز، بدونِ نتیجه — آیکون+پیام + پرطرفدار.
 *   S5  بسته با مقدارِ حفظ‌شده (Esc/کلیکِ بیرون، بدونِ پاک‌شدنِ متن).
 *
 * «جستجوهایِ پرطرفدار» یک بخشِ ثابتِ همیشه‌حاضر است، در هر چهار حالتِ باز
 * (S1–S4) — چون به‌جایِ نتیجه نیست، *در کنارِ* نتیجه است.
 *
 * سویچِ بینِ حالت‌ها و منطقِ ایجکس در ‎assets/js/zig3d-search.js‎ است؛
 * اینجا فقط اسکلتِ اولیه (بسته/S0) رندر می‌شود — شاملِ همهٔ بخش‌ها، ولی
 * پنهان — تا جاوااسکریپت فقط نمایش/پنهان کند و لازم نباشد از صفر
 * HTMLِ پیچیده بسازد. تنها استثنا «جستجوهایِ پرطرفدار» است که چون
 * محتوایِ ثابتِ مدیریتی است (نه دادهٔ سرچ)، کاملاً سمتِ سرور رندر
 * می‌شود.
 *
 * قراردادِ کلیک — قطعی، تغییرناپذیر:
 *
 *   • ردیفِ محصول    → لینکِ واقعی به صفحهٔ محصول (پرمالینک).
 *   • چیپِ تاریخچه   → لینکِ واقعی به صفحهٔ نتایجِ سرچِ سایت (‎?s=…‎).
 *   • چیپِ پرطرفدار  → همان، لینکِ واقعیِ نتایج.
 *
 * صفحهٔ نتایج خودش (طراحیِ چیدمانش) عمداً بیرون از این ویجت است — فقط
 * لینک‌دهیِ درست لازم است، نه ساختنِ آن صفحه.
 *
 *     div.zig-search[data-zig-search]
 *       div.zig-search__shell                یک سطحِ سفیدِ واحد — نه دو کارت
 *         form.zig-search__field               فیلد، بالایِ همان shell
 *           button.zig-search__icon-btn        آیکونِ سرچ
 *           input.zig-search__input            role="combobox"
 *           button.zig-search__clear           ضربدر (فقط وقتی متن هست)
 *         div.zig-search__panel                زیرِ فیلد، همان shell
 *           div.zig-search__section--recent
 *           div.zig-search__section--products
 *           div.zig-search__section--empty
 *           div.zig-search__section--popular   ثابت، سمتِ سرور
 */
final class Search extends Widget_Base {

    use Traits\Box;

    public function get_name(): string {
        return 'zig3d-search';
    }

    public function get_title(): string {
        return __('سرچ محصولات', 'zig3d-widgets');
    }

    public function get_icon(): string {
        return 'eicon-search';
    }

    public function get_categories(): array {
        return [Plugin::CATEGORY];
    }

    public function get_keywords(): array {
        return ['search', 'ajax', 'live search', 'سرچ', 'جستجو', 'محصول'];
    }

    public function get_style_depends(): array {
        return ['zig3d-widgets'];
    }

    public function get_script_depends(): array {
        return ['zig3d-search'];
    }

    public function has_widget_inner_wrapper(): bool {
        return false;
    }

    protected function register_controls(): void {
        $this->register_behavior_section();
        $this->register_sources_section();
        $this->register_texts_section();
        $this->register_icons_section();
        $this->register_popular_section();
        $this->register_recent_section();
        $this->register_synonyms_section();

        $this->register_field_style_section();
        $this->register_shell_style_section();
        $this->register_headers_style_section();
        $this->register_product_style_section();
        $this->register_recent_chip_style_section();
        $this->register_popular_chip_style_section();
        $this->register_more_button_style_section();
        $this->register_empty_style_section();
    }

    /* =====================================================================
     * محتوا › رفتار
     * =================================================================== */

    private function register_behavior_section(): void {
        $this->start_controls_section('behavior_section', ['label' => __('رفتار', 'zig3d-widgets')]);

        $this->add_control('min_chars', [
            'label'   => __('حداقلِ کاراکتر برایِ شروعِ جست‌وجو', 'zig3d-widgets'),
            'type'    => Controls_Manager::NUMBER,
            'min'     => 1,
            'max'     => 10,
            'default' => 2,
        ]);

        $this->add_control('debounce_ms', [
            'label'   => __('تأخیرِ جست‌وجو (میلی‌ثانیه)', 'zig3d-widgets'),
            'type'    => Controls_Manager::NUMBER,
            'min'     => 0,
            'max'     => 2000,
            'step'    => 50,
            'default' => 300,
        ]);

        $this->add_control('result_limit', [
            'label'       => __('حداکثرِ محصولِ نمایشی', 'zig3d-widgets'),
            'type'        => Controls_Manager::NUMBER,
            'min'         => 1,
            'max'         => Search_Query::MAX_LIMIT,
            'default'     => Search_Query::DEFAULT_LIMIT,
            'description' => __('بیشتر از این تعداد، پشتِ دکمهٔ «نمایشِ محصولاتِ بیشتر» می‌رود.', 'zig3d-widgets'),
        ]);

        $this->add_control('search_fields', [
            'label'   => __('جست‌وجو در', 'zig3d-widgets'),
            'type'    => Controls_Manager::SELECT2,
            'multiple' => true,
            'options' => [
                'title' => __('عنوانِ محصول', 'zig3d-widgets'),
                'sku'   => __('کدِ محصول (SKU)', 'zig3d-widgets'),
            ],
            'default' => ['title'],
        ]);

        $this->add_control('enable_shortcut', [
            'label'        => __('میان‌برِ صفحه‌کلید (Ctrl/Cmd + K)', 'zig3d-widgets'),
            'type'         => Controls_Manager::SWITCHER,
            'default'      => 'yes',
            'return_value' => 'yes',
            'description'  => __('با زدنِ این میان‌بر، فیلدِ سرچ فوکوس می‌گیرد — از هرجایِ صفحه.', 'zig3d-widgets'),
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * محتوا › منبعِ دسته/برند
     * =================================================================== */

    private function register_sources_section(): void {
        $this->start_controls_section('sources_section', ['label' => __('منبعِ دسته و برند', 'zig3d-widgets')]);

        $options = $this->taxonomy_source_options();

        $this->add_control('category_source', [
            'label'       => __('منبعِ برچسبِ دسته', 'zig3d-widgets'),
            'type'        => Controls_Manager::SELECT,
            'options'     => $options,
            'default'     => 'product_cat',
            'description' => __('زیرِ عنوانِ هر محصول نشان داده می‌شود — مثلاً «میلینگ ماشین». همچنین برایِ جست‌وجو در نامِ ترم هم استفاده می‌شود.', 'zig3d-widgets'),
        ]);

        $this->add_control('brand_source', [
            'label'   => __('منبعِ برچسبِ برند', 'zig3d-widgets'),
            'type'    => Controls_Manager::SELECT,
            'options' => $options,
            'default' => '',
        ]);

        $this->end_controls_section();
    }

    /**
     * تاکسونومی‌هایِ قابلِ‌انتخاب برایِ برچسبِ دسته/برند — همان فهرستی که
     * واقعاً رویِ محصولات نشسته، نه یک لیستِ ثابت که در فروشگاه‌هایِ بدونِ
     * برندِ ثبت‌شده گزینه‌ای بی‌فایده نشان بدهد.
     *
     * @return array<string,string>
     */
    private function taxonomy_source_options(): array {
        $options = ['' => __('— نمایش داده نشود —', 'zig3d-widgets')];

        if (!class_exists('WooCommerce') || !function_exists('taxonomy_exists')) {
            return $options;
        }

        $candidates = array_unique(array_merge(
            ['product_cat', 'product_tag'],
            Archive_Query::BRAND_TAXONOMIES,
            function_exists('wc_get_attribute_taxonomy_names') ? wc_get_attribute_taxonomy_names() : []
        ));

        foreach ($candidates as $taxonomy) {
            if (taxonomy_exists($taxonomy)) {
                $options[$taxonomy] = class_exists(Attributes::class) ? Attributes::label($taxonomy) : $taxonomy;
            }
        }

        return $options;
    }

    /* =====================================================================
     * محتوا › متن‌ها
     * =================================================================== */

    private function register_texts_section(): void {
        $this->start_controls_section('texts_section', ['label' => __('متن‌ها', 'zig3d-widgets')]);

        $this->add_control('placeholder_text', [
            'label'   => __('متنِ جای‌گزین در فیلد', 'zig3d-widgets'),
            'type'    => Controls_Manager::TEXT,
            'dynamic' => ['active' => true],
            'default' => __('جست‌وجویِ محصول…', 'zig3d-widgets'),
        ]);

        $this->add_control('empty_message', [
            'label'   => __('پیامِ بدونِ نتیجه', 'zig3d-widgets'),
            'type'    => Controls_Manager::TEXT,
            'dynamic' => ['active' => true],
            'default' => __('همچین نتیجه‌ای پیدا نکردیم', 'zig3d-widgets'),
        ]);

        $this->add_control('results_announcement', [
            'label'       => __('اعلامِ تعدادِ نتیجه (برایِ صفحه‌خوان)', 'zig3d-widgets'),
            'type'        => Controls_Manager::TEXT,
            'default'     => __('%s نتیجه پیدا شد', 'zig3d-widgets'),
            'description' => __('‎%s‎ جایِ تعداد می‌نشیند. این متن دیده نمی‌شود؛ فقط صفحه‌خوان می‌خوانَدش.', 'zig3d-widgets'),
        ]);

        $this->add_control('error_message', [
            'label'   => __('پیامِ خطایِ فنی', 'zig3d-widgets'),
            'type'    => Controls_Manager::TEXT,
            'dynamic' => ['active' => true],
            'default' => __('جست‌وجو انجام نشد. دوباره تلاش کنید.', 'zig3d-widgets'),
        ]);

        $this->add_control('rate_limit_message', [
            'label'       => __('پیامِ درخواستِ بیش‌ازحد', 'zig3d-widgets'),
            'type'        => Controls_Manager::TEXT,
            'dynamic'     => ['active' => true],
            'default'     => __('کمی آرام‌تر — چند لحظه دیگر دوباره تلاش کنید.', 'zig3d-widgets'),
            'description' => __('وقتی نشان داده می‌شود که تعدادِ جست‌وجوها در بازهٔ کوتاه از حد گذشته باشد.', 'zig3d-widgets'),
        ]);

        $this->add_control('products_heading_text', [
            'label'   => __('عنوانِ بخشِ محصولات', 'zig3d-widgets'),
            'type'    => Controls_Manager::TEXT,
            'dynamic' => ['active' => true],
            'default' => __('محصولات', 'zig3d-widgets'),
        ]);

        $this->add_control('more_button_text', [
            'label'   => __('متنِ دکمهٔ «نمایشِ بیشتر»', 'zig3d-widgets'),
            'type'    => Controls_Manager::TEXT,
            'dynamic' => ['active' => true],
            'default' => __('مشاهده نتایج بیشتر', 'zig3d-widgets'),
        ]);

        $this->add_control('recent_heading_text', [
            'label'   => __('عنوانِ بخشِ «جستجوهایِ اخیر»', 'zig3d-widgets'),
            'type'    => Controls_Manager::TEXT,
            'dynamic' => ['active' => true],
            'default' => __('جستجوهایِ اخیر', 'zig3d-widgets'),
        ]);

        $this->add_control('clear_history_text', [
            'label'   => __('متنِ «پاک‌کردنِ تاریخچه»', 'zig3d-widgets'),
            'type'    => Controls_Manager::TEXT,
            'dynamic' => ['active' => true],
            'default' => __('پاک‌کردن', 'zig3d-widgets'),
        ]);

        $this->add_control('popular_heading_text', [
            'label'   => __('عنوانِ بخشِ «جستجوهایِ پرطرفدار»', 'zig3d-widgets'),
            'type'    => Controls_Manager::TEXT,
            'dynamic' => ['active' => true],
            'default' => __('جستجوهایِ پرطرفدار', 'zig3d-widgets'),
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * محتوا › آیکون‌ها
     * =================================================================== */

    /**
     * نگاشتِ هر جایگاهِ آیکون به فایلِ صادرشده از فیگما.
     *
     * ‎recent_icon‎ عمداً اینجا نیست: چیپِ «جستجوهایِ اخیر» در طرح آیکون
     * ندارد. نبودنش در این جدول یعنی پیش‌فرضش «هیچ» است، بی‌آنکه لازم
     * باشد جایی استثنا بنویسیم.
     */
    private const DESIGN_ICONS = [
        'search_icon'  => 'search',
        'clear_icon'   => 'close',
        'chevron_icon' => 'chevron',
        'more_icon'    => 'arrow-left',
        'empty_icon'   => 'search',
        'popular_icon' => 'trending-up',
    ];

    private function register_icons_section(): void {
        $this->start_controls_section('icons_section', ['label' => __('آیکون‌ها', 'zig3d-widgets')]);

        /*
         * چرا این سویچ لازم است:
         *
         * کنترلِ ICONSِ المنتور حالتِ «هیچ آیکونی» ندارد — پاک‌کردنِ آیکون
         * و «اصلاً دست‌نزدن» هر دو یک مقدارِ خالی می‌دهند. اگر خالی همیشه
         * یعنی «از آیکونِ طرح استفاده کن»، دیگر هیچ راهی برایِ برداشتنِ
         * آیکون نمی‌ماند؛ اگر همیشه یعنی «هیچ»، آیکونِ طرح از دسترس خارج
         * می‌شود.
         *
         * پس یک سویچ: روشن (پیش‌فرض) یعنی هر جایگاهِ خالی به SVGی فیگما
         * برمی‌گردد؛ خاموش یعنی خالی واقعاً خالی است و آیکون‌ها فقط از
         * همین کنترل‌ها می‌آیند.
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

        /*
         * پیش‌فرضِ همهٔ کنترل‌ها خالی است، نه یک گلیفِ Font Awesome.
         *
         * قبلاً هر کدام یک معادلِ «شبیه» داشتند و همان باعث شد خروجی با
         * طرح یکی نباشد. حالا پیش‌فرضِ دیداری از ‎DESIGN_ICONS‎ می‌آید و
         * این کنترل‌ها فقط برایِ جایگزینی‌اند.
         */
        $icons = [
            'search_icon'  => __('آیکونِ سرچ', 'zig3d-widgets'),
            'clear_icon'   => __('آیکونِ پاک‌کردن (ضربدر)', 'zig3d-widgets'),
            'chevron_icon' => __('آیکونِ فلشِ ردیفِ محصول', 'zig3d-widgets'),
            'more_icon'    => __('آیکونِ دکمهٔ «نمایشِ بیشتر»', 'zig3d-widgets'),
            'empty_icon'   => __('آیکونِ حالتِ بدونِ نتیجه', 'zig3d-widgets'),
            'recent_icon'  => __('آیکونِ چیپِ تاریخچه', 'zig3d-widgets'),
            'popular_icon' => __('آیکونِ چیپِ پرطرفدار', 'zig3d-widgets'),
        ];

        foreach ($icons as $key => $label) {
            $this->add_control($key, [
                'label'       => $label,
                'type'        => Controls_Manager::ICONS,
                'default'     => ['value' => '', 'library' => ''],
                'description' => isset(self::DESIGN_ICONS[$key])
                    ? __('خالی یعنی آیکونِ خودِ طرح.', 'zig3d-widgets')
                    : __('در طرح این چیپ آیکون ندارد؛ خالی یعنی بدونِ آیکون.', 'zig3d-widgets'),
            ]);
        }

        $this->end_controls_section();
    }

    /* =====================================================================
     * محتوا › جستجوهایِ پرطرفدار
     * =================================================================== */

    private function register_popular_section(): void {
        $this->start_controls_section('popular_section', ['label' => __('جستجوهایِ پرطرفدار', 'zig3d-widgets')]);

        /*
         * دستی، نه محاسبه‌شده از آمارِ واقعیِ سرچ — یک تصمیمِ آگاهانه: تا
         * زمانی که هیچ ابزارِ تحلیلِ سرچی در کار نیست، «پرطرفدار» فقط از
         * زبانِ مدیرِ فروشگاه معنا دارد، نه از یک جدولِ خالی.
         */
        $repeater = new Repeater();

        $repeater->add_control('label', [
            'label'       => __('عبارت', 'zig3d-widgets'),
            'type'        => Controls_Manager::TEXT,
            'dynamic'     => ['active' => true],
            'default'     => __('عبارتِ جدید', 'zig3d-widgets'),
            'label_block' => true,
        ]);

        $this->add_control('popular_searches', [
            'label'       => __('عبارت‌ها', 'zig3d-widgets'),
            'type'        => Controls_Manager::REPEATER,
            'fields'      => $repeater->get_controls(),
            'title_field' => '{{{ label }}}',
            'default'     => [],
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * محتوا › جستجوهایِ اخیر
     * =================================================================== */

    private function register_recent_section(): void {
        $this->start_controls_section('recent_section', ['label' => __('جستجوهایِ اخیر', 'zig3d-widgets')]);

        $this->add_control('enable_recent', [
            'label'        => __('نمایشِ جستجوهایِ اخیرِ کاربر', 'zig3d-widgets'),
            'type'         => Controls_Manager::SWITCHER,
            'default'      => 'yes',
            'return_value' => 'yes',
            'description'  => __('در مرورگرِ خودِ کاربر ذخیره می‌شود (localStorage) — نه رویِ سرور.', 'zig3d-widgets'),
        ]);

        $this->add_control('recent_max', [
            'label'     => __('حداکثرِ تعداد', 'zig3d-widgets'),
            'type'      => Controls_Manager::NUMBER,
            'min'       => 1,
            'max'       => 20,
            'default'   => 4,
            'condition' => ['enable_recent' => 'yes'],
        ]);

        $this->add_control('recent_expiry_days', [
            'label'     => __('انقضا (روز)', 'zig3d-widgets'),
            'type'      => Controls_Manager::NUMBER,
            'min'       => 1,
            'max'       => 365,
            'default'   => 30,
            'condition' => ['enable_recent' => 'yes'],
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * محتوا › مترادف‌ها
     * =================================================================== */

    private function register_synonyms_section(): void {
        $this->start_controls_section('synonyms_section', [
            'label' => __('مترادف‌هایِ جست‌وجو', 'zig3d-widgets'),
        ]);

        $this->add_control('synonyms_hint', [
            'type' => Controls_Manager::RAW_HTML,
            'raw'  => __('مثلاً «میلینگ» و «فرز»: هرکدام تایپ شود، محصولاتِ آن‌یکی هم پیدا می‌شوند.', 'zig3d-widgets'),
            'content_classes' => 'elementor-descriptor',
        ]);

        $repeater = new Repeater();

        $repeater->add_control('term_a', [
            'label'       => __('عبارتِ اول', 'zig3d-widgets'),
            'type'        => Controls_Manager::TEXT,
            'label_block' => true,
        ]);

        $repeater->add_control('term_b', [
            'label'       => __('عبارتِ دوم', 'zig3d-widgets'),
            'type'        => Controls_Manager::TEXT,
            'label_block' => true,
        ]);

        $this->add_control('synonym_pairs', [
            'label'       => __('جفت‌ها', 'zig3d-widgets'),
            'type'        => Controls_Manager::REPEATER,
            'fields'      => $repeater->get_controls(),
            'title_field' => '{{{ term_a }}} ↔ {{{ term_b }}}',
            'default'     => [],
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل › فیلد
     * =================================================================== */

    private function register_field_style_section(): void {
        $this->start_controls_section('field_style_section', [
            'label' => __('فیلدِ سرچ', 'zig3d-widgets'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_box_style_tabs('field', '.zig-search__field', '.zig-search__field');

        /*
         * ارتفاعِ پایه فقط همین‌جاست — و ‎min-height‎ است نه ‎height‎: در
         * حالتِ بسته قدِ فیلد را به طرح می‌رساند، ولی اگر کاربر فونت را
         * بزرگ کند، فیلد به‌جایِ بریدنِ متن رشد می‌کند.
         */
        $this->add_responsive_control('field_height', [
            'label'      => __('ارتفاعِ پایهٔ فیلد', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 32, 'max' => 120]],
            'selectors'  => ['{{WRAPPER}} .zig-search' => '--zig-search-field-height: {{SIZE}}{{UNIT}};'],
        ]);

        /*
         * دو اندازهٔ جدا، چون در طرح هم جدا هستند: ذره‌بین ۲۲ و ضربدر ۱۶.
         * یک کنترلِ مشترک، این تفاوت را از بین می‌برد.
         */
        $this->add_responsive_control('field_icon_size', [
            'label'     => __('اندازهٔ آیکونِ سرچ', 'zig3d-widgets'),
            'type'      => Controls_Manager::SLIDER,
            'range'     => ['px' => ['min' => 8, 'max' => 60]],
            'selectors' => ['{{WRAPPER}} .zig-search__field' => '--zig-search-icon-size: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_responsive_control('field_clear_size', [
            'label'     => __('اندازهٔ آیکونِ پاک‌کردن', 'zig3d-widgets'),
            'type'      => Controls_Manager::SLIDER,
            'range'     => ['px' => ['min' => 8, 'max' => 60]],
            'selectors' => ['{{WRAPPER}} .zig-search__field' => '--zig-search-clear-icon-size: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_control('field_icon_color', [
            'label'     => __('رنگِ آیکونِ سرچ', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [Selector::descend('{{WRAPPER}} .zig-search__icon-btn', 'svg, i') => 'fill: {{VALUE}}; color: {{VALUE}};'],
        ]);

        $this->add_control('field_clear_color', [
            'label'     => __('رنگِ آیکونِ پاک‌کردن', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [Selector::descend('{{WRAPPER}} .zig-search__clear', 'svg, i') => 'fill: {{VALUE}}; color: {{VALUE}};'],
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'     => 'field_typography',
            'label'    => __('تایپوگرافیِ متنِ فیلد', 'zig3d-widgets'),
            'selector' => '{{WRAPPER}} .zig-search__input',
        ]);

        $this->add_control('field_text_color', [
            'label'     => __('رنگِ متن', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .zig-search__input' => 'color: {{VALUE}};'],
        ]);

        $this->add_control('field_placeholder_color', [
            'label'     => __('رنگِ متنِ جای‌گزین', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [Selector::descend('{{WRAPPER}} .zig-search__input', '::placeholder') => 'color: {{VALUE}};'],
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل › پوسته و پنل
     * =================================================================== */

    private function register_shell_style_section(): void {
        $this->start_controls_section('shell_style_section', [
            'label' => __('پوسته و پنل', 'zig3d-widgets'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        /*
         * ‎.is-open‎ در انتخاب‌گر عمدی است: کارتِ سفید فقط در حالتِ باز
         * وجود دارد. در حالتِ بسته پوسته هیچ ظاهری ندارد و فقط قرصِ
         * خاکستریِ فیلد دیده می‌شود — اگر این قید نبود، یک حلقهٔ سفیدِ
         * بی‌دلیل دورِ فیلدِ بسته می‌افتاد.
         */
        /*
         * «پوسته» و «سطح» عمداً دو عنصرِ جدا هستند و این تقسیم از یک باگِ
         * واقعی درآمد:
         *
         * قاب باید موقعِ بسته‌شدن محو شود، ولی *جعبه*ش نباید تکان بخورد.
         * وقتی هر دو رویِ یک عنصر بودند، تنها راهِ محو کردن این بود که
         * کلاسِ حالت برداشته شود — و با برداشتنش، پدینگ و لبه‌هایی که
         * تبِ استایل نوشته بود هم می‌پریدند، چون سلکتورشان همان کلاس را
         * داشت. نتیجه: کارت در همان فریمِ اول جمع می‌شد و پنل و فیلد
         * کشیده می‌شدند.
         *
         * حالا پس‌زمینه/حاشیه/گردی/سایه رویِ ‎__surface‎ می‌نشینند که فقط
         * یک لایهٔ ‎inset: 0‎ی بی‌محتواست و شفافیتش محو می‌شود؛ و پدینگ
         * رویِ خودِ پوسته می‌ماند که تا آخرِ گذار دست‌نخورده است.
         *
         * پدینگ علاوه بر خودش در چهار متغیر هم نوشته می‌شود، چون CSS همان
         * عددها را برایِ کشیدنِ لبه‌ها به بیرون لازم دارد. یک منبعِ حقیقت
         * یعنی این دو هیچ‌وقت نمی‌توانند از هم جدا بیفتند.
         */
        $this->add_box_style_tabs(
            'shell',
            '.zig-search__surface',
            '.zig-search__surface',
            [
                '{{WRAPPER}} .zig-search.is-open .zig-search__shell' =>
                    'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'
                    . ' --zig-search-shell-pad-top: {{TOP}}{{UNIT}};'
                    . ' --zig-search-shell-pad-right: {{RIGHT}}{{UNIT}};'
                    . ' --zig-search-shell-pad-bottom: {{BOTTOM}}{{UNIT}};'
                    . ' --zig-search-shell-pad-left: {{LEFT}}{{UNIT}};',
            ]
        );

        /*
         * باز و بستهٔ اورلی یک محوشدنِ ساده است و هیچ چیزی جابه‌جا
         * نمی‌شود: فیلد در هر دو حالت دقیقاً سرِ جای خودش است و فقط
         * قابِ سفید و پنل ظاهر/ناپدید می‌شوند. مقدارِ صفر یعنی بدونِ
         * انیمیشن — همان چیزی که جاوااسکریپت هم از مرورگر می‌خواند و
         * بلافاصله پنل را می‌بندد.
         */
        $this->add_control('overlay_transition', [
            'label'      => __('مدتِ باز و بسته شدن', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['ms'],
            'range'      => ['ms' => ['min' => 0, 'max' => 600, 'step' => 10]],
            'default'    => ['size' => 150, 'unit' => 'ms'],
            'selectors'  => ['{{WRAPPER}} .zig-search' => '--zig-search-anim: {{SIZE}}ms;'],
        ]);

        $this->add_control('shell_width', [
            'label'      => __('عرضِ ویجت', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', '%'],
            'range'      => ['px' => ['min' => 160, 'max' => 800], '%' => ['min' => 10, 'max' => 100]],
            'default'    => ['size' => 100, 'unit' => '%'],
            'selectors'  => ['{{WRAPPER}} .zig-search' => '--zig-search-width: {{SIZE}}{{UNIT}};'],
            'description' => __('فقط حالتِ بسته ارتفاعِ پایه دارد؛ همهٔ حالت‌هایِ باز از رویِ گرید+فاصله+پدینگِ محتوا شکل می‌گیرند، نه ارتفاعِ ثابت.', 'zig3d-widgets'),
        ]);

        $this->add_responsive_control('panel_gap', [
            'label'      => __('فاصلهٔ بینِ بخش‌هایِ پنل', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 60]],
            'selectors'  => ['{{WRAPPER}} .zig-search__panel' => '--zig-search-panel-gap: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_responsive_control('panel_padding', [
            'label'      => __('فاصلهٔ داخلیِ پنل', 'zig3d-widgets'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', 'em'],
            'selectors'  => ['{{WRAPPER}} .zig-search__panel' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
        ]);

        $this->add_responsive_control('panel_max_height', [
            'label'       => __('حداکثرِ ارتفاعِ پنل', 'zig3d-widgets'),
            'type'        => Controls_Manager::SLIDER,
            'size_units'  => ['px', 'vh'],
            'range'       => ['px' => ['min' => 200, 'max' => 1000], 'vh' => ['min' => 20, 'max' => 100]],
            'selectors'   => ['{{WRAPPER}} .zig-search__panel' => 'max-height: {{SIZE}}{{UNIT}}; overflow-y: auto;'],
            'description' => __('فقط یک شیرِ اطمینان برایِ سرریز، نه ارتفاعِ اصلیِ پنل — ارتفاعِ اصلی از محتوا می‌آید.', 'zig3d-widgets'),
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل › عنوانِ بخش‌ها
     * =================================================================== */

    private function register_headers_style_section(): void {
        $this->start_controls_section('headers_style_section', [
            'label' => __('عنوانِ بخش‌ها', 'zig3d-widgets'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'     => 'section_title_typography',
            'label'    => __('تایپوگرافیِ عنوان', 'zig3d-widgets'),
            'selector' => '{{WRAPPER}} .zig-search__section-title',
        ]);

        $this->add_control('section_title_color', [
            'label'     => __('رنگِ عنوان', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .zig-search__section-title' => 'color: {{VALUE}};'],
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'     => 'clear_history_typography',
            'label'    => __('تایپوگرافیِ «پاک‌کردن»', 'zig3d-widgets'),
            'selector' => '{{WRAPPER}} .zig-search__clear-history',
        ]);

        $this->add_control('clear_history_color', [
            'label'     => __('رنگِ «پاک‌کردن»', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .zig-search__clear-history' => 'color: {{VALUE}};'],
        ]);

        $this->add_control('clear_history_hover_color', [
            'label'     => __('رنگِ «پاک‌کردن» در هاور', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .zig-search__clear-history:hover' => 'color: {{VALUE}};'],
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل › ردیفِ محصول
     * =================================================================== */

    private function register_product_style_section(): void {
        $this->start_controls_section('product_style_section', [
            'label' => __('ردیفِ محصول', 'zig3d-widgets'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_box_style_tabs('product', '.zig-search__product', '.zig-search__product');

        $this->add_responsive_control('product_image_size', [
            'label'      => __('اندازهٔ تصویر', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 24, 'max' => 160]],
            'default'    => ['size' => 56, 'unit' => 'px'],
            'selectors'  => ['{{WRAPPER}} .zig-search__product' => '--zig-search-product-image: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_responsive_control('product_image_radius', [
            'label'      => __('گردیِ گوشهٔ تصویر', 'zig3d-widgets'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', '%'],
            'selectors'  => ['{{WRAPPER}} .zig-search__product-image' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'     => 'product_title_typography',
            'label'    => __('تایپوگرافیِ عنوانِ محصول', 'zig3d-widgets'),
            'selector' => '{{WRAPPER}} .zig-search__product-title',
        ]);

        $this->add_control('product_title_color', [
            'label'     => __('رنگِ عنوانِ محصول', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .zig-search__product-title' => 'color: {{VALUE}};'],
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'     => 'product_meta_typography',
            'label'    => __('تایپوگرافیِ برچسبِ دسته/برند', 'zig3d-widgets'),
            'selector' => '{{WRAPPER}} .zig-search__product-meta',
        ]);

        $this->add_control('product_meta_color', [
            'label'     => __('رنگِ برچسبِ دسته/برند', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .zig-search__product-meta' => 'color: {{VALUE}};'],
        ]);

        $this->add_control('product_dot_size', [
            'label'     => __('اندازهٔ نقطهٔ جداکننده', 'zig3d-widgets'),
            'type'      => Controls_Manager::SLIDER,
            'range'     => ['px' => ['min' => 2, 'max' => 16]],
            'default'   => ['size' => 5, 'unit' => 'px'],
            'selectors' => ['{{WRAPPER}} .zig-search__product-meta' => '--zig-search-product-dot-size: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_control('product_dot_color', [
            'label'     => __('رنگِ نقطهٔ جداکننده', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .zig-search__product-meta' => '--zig-search-product-dot-color: {{VALUE}};'],
        ]);

        /*
         * اندازه فقط ارتفاع را می‌نویسد، نه عرض را: فلشِ طرح مربع نیست
         * (۶٫۸۹ در ۱۲) و نوشتنِ هر دو، کشیده‌اش می‌کرد. عرض را CSS از
         * روی ‎viewBox‎ درمی‌آورد.
         */
        $this->add_control('product_chevron_size', [
            'label'     => __('اندازهٔ فلش', 'zig3d-widgets'),
            'type'      => Controls_Manager::SLIDER,
            'range'     => ['px' => ['min' => 6, 'max' => 40]],
            'default'   => ['size' => 12, 'unit' => 'px'],
            'selectors' => ['{{WRAPPER}} .zig-search__product-chevron' => '--zig-search-chevron-size: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_control('product_chevron_color', [
            'label'     => __('رنگِ فلش', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [Selector::descend('{{WRAPPER}} .zig-search__product-chevron', 'svg, i') => 'fill: {{VALUE}}; color: {{VALUE}};'],
        ]);

        /*
         * ناوبریِ کیبورد (↑/↓) طبقِ الگویِ WAI-ARIA combobox فوکوس را رویِ
         * خودِ input نگه می‌دارد — نه رویِ ردیف — پس هیچ ‎:hover‎/‎:focus‎ی
         * واقعی رخ نمی‌دهد و بازخوردِ بصری باید از یک کلاسِ جاوااسکریپتی
         * بیاید، نه از حالتِ هاورِ بالا.
         */
        $this->add_control('product_active_background', [
            'label'       => __('پس‌زمینه در انتخابِ کیبورد', 'zig3d-widgets'),
            'type'        => Controls_Manager::COLOR,
            'separator'   => 'before',
            'selectors'   => ['{{WRAPPER}} .zig-search__product.is-active' => 'background-color: {{VALUE}};'],
            'description' => __('وقتی با کلیدهای بالا/پایین بینِ محصولات جابه‌جا می‌شوید.', 'zig3d-widgets'),
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل › چیپ‌هایِ اخیر / پرطرفدار
     * =================================================================== */

    private function register_recent_chip_style_section(): void {
        $this->start_controls_section('recent_chip_style_section', [
            'label' => __('چیپ‌هایِ جستجویِ اخیر', 'zig3d-widgets'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $this->register_chip_style_controls('recent', '.zig-search__chip--recent');

        $this->end_controls_section();
    }

    private function register_popular_chip_style_section(): void {
        $this->start_controls_section('popular_chip_style_section', [
            'label' => __('چیپ‌هایِ جستجویِ پرطرفدار', 'zig3d-widgets'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $this->register_chip_style_controls('popular', '.zig-search__chip--popular');

        $this->end_controls_section();
    }

    /** مشترکِ بینِ دو نوع چیپ — همان کنترل‌ها، دو انتخاب‌گرِ متفاوت */
    private function register_chip_style_controls(string $prefix, string $selector): void {
        $this->add_box_style_tabs($prefix . '_chip', $selector, $selector);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'     => $prefix . '_chip_typography',
            'selector' => '{{WRAPPER}} ' . $selector,
        ]);

        $this->add_control($prefix . '_chip_text_color', [
            'label'     => __('رنگِ متن', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} ' . $selector => 'color: {{VALUE}};'],
        ]);

        $this->add_control($prefix . '_chip_icon_color', [
            'label'     => __('رنگِ آیکون', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [Selector::descend('{{WRAPPER}} ' . $selector, 'svg, i') => 'fill: {{VALUE}}; color: {{VALUE}};'],
        ]);
    }

    /* =====================================================================
     * استایل › دکمهٔ نمایشِ بیشتر
     * =================================================================== */

    private function register_more_button_style_section(): void {
        $this->start_controls_section('more_button_style_section', [
            'label' => __('دکمهٔ «نمایشِ بیشتر»', 'zig3d-widgets'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_box_style_tabs('more_button', '.zig-search__more', '.zig-search__more');

        $this->add_responsive_control('more_button_width', [
            'label'      => __('عرضِ دکمه', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', '%'],
            'range'      => ['px' => ['min' => 80, 'max' => 600], '%' => ['min' => 10, 'max' => 100]],
            'selectors'  => ['{{WRAPPER}} .zig-search__more' => 'width: {{SIZE}}{{UNIT}};'],
            'description' => __('خالی بگذارید تا دکمه به‌اندازهٔ متنش جمع‌وجور بماند — همان چیزی که در طرح است.', 'zig3d-widgets'),
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'     => 'more_button_typography',
            'selector' => '{{WRAPPER}} .zig-search__more',
        ]);

        $this->add_control('more_button_color', [
            'label'     => __('رنگِ متن', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .zig-search__more' => 'color: {{VALUE}};'],
        ]);

        $this->add_control('more_button_hover_color', [
            'label'     => __('رنگِ متن در هاور', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .zig-search__more:hover' => 'color: {{VALUE}};'],
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل › حالتِ بدونِ نتیجه
     * =================================================================== */

    private function register_empty_style_section(): void {
        $this->start_controls_section('empty_style_section', [
            'label' => __('حالتِ بدونِ نتیجه', 'zig3d-widgets'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_control('empty_icon_size', [
            'label'     => __('اندازهٔ آیکون', 'zig3d-widgets'),
            'type'      => Controls_Manager::SLIDER,
            'range'     => ['px' => ['min' => 16, 'max' => 120]],
            // ۲۲ همان اندازه‌ای است که در طرح دارد — همان ذره‌بینِ فیلد،
            // نه یک آیکونِ بزرگ‌ترِ حالتِ خالی.
            'default'   => ['size' => 22, 'unit' => 'px'],
            'selectors' => [Selector::descend('{{WRAPPER}} .zig-search__empty-icon', 'svg, i') => 'font-size: {{SIZE}}{{UNIT}}; width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_control('empty_icon_color', [
            'label'     => __('رنگِ آیکون', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [Selector::descend('{{WRAPPER}} .zig-search__empty-icon', 'svg, i') => 'fill: {{VALUE}}; color: {{VALUE}};'],
        ]);

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'     => 'empty_text_typography',
            'label'    => __('تایپوگرافیِ پیام', 'zig3d-widgets'),
            'selector' => '{{WRAPPER}} .zig-search__empty-text',
        ]);

        $this->add_control('empty_text_color', [
            'label'     => __('رنگِ پیام', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .zig-search__empty-text' => 'color: {{VALUE}};'],
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * تنظیماتِ کوئری — پلِ بینِ کنترل‌هایِ المنتور و Search_Query
     * =================================================================== */

    /** @return array مطابقِ ورودیِ ‎Search_Query::match()‎ */
    public function search_args(array $settings): array {
        return [
            'post_type'        => 'product',
            'limit'            => (int) ($settings['result_limit'] ?? Search_Query::DEFAULT_LIMIT),
            'search_fields'    => (array) ($settings['search_fields'] ?? ['title']),
            'match_taxonomies' => array_values(array_filter([
                (string) ($settings['category_source'] ?? ''),
                (string) ($settings['brand_source'] ?? ''),
            ])),
            'synonym_pairs'    => $this->synonym_pairs($settings),
        ];
    }

    /** @return array مطابقِ ورودیِ ‎Search_Query::hydrate()‎ */
    public function hydrate_args(array $settings): array {
        return [
            'post_type'         => 'product',
            'category_taxonomy' => (string) ($settings['category_source'] ?? ''),
            'brand_taxonomy'    => (string) ($settings['brand_source'] ?? ''),
        ];
    }

    /** @return array<int,array{0:string,1:string}> */
    private function synonym_pairs(array $settings): array {
        $pairs = [];

        foreach ((array) ($settings['synonym_pairs'] ?? []) as $row) {
            $a = trim((string) ($row['term_a'] ?? ''));
            $b = trim((string) ($row['term_b'] ?? ''));

            if ('' !== $a && '' !== $b) {
                $pairs[] = [$a, $b];
            }
        }

        return $pairs;
    }

    /** @return string[] برچسب‌هایِ غیرِخالی، به همان ترتیبِ Repeater */
    private function popular_labels(array $settings): array {
        $labels = [];

        foreach ((array) ($settings['popular_searches'] ?? []) as $row) {
            $label = trim((string) ($row['label'] ?? ''));

            if ('' !== $label) {
                $labels[] = $label;
            }
        }

        return $labels;
    }

    /**
     * سندِ Elementorِ در حالِ رندر — نه لزوماً پستِ جاری.
     *
     * برایِ ویجتی که در یک قالبِ سراسری (هدر/فوتر) نشسته، «پستِ جاری» صفحه‌ای
     * است که کاربر می‌بیند، نه خودِ قالب — و ‎Search_Endpoint::widget()‎ باید
     * دقیقاً همان سندی را بخواند که این ویجت داخلش ذخیره شده، وگرنه هیچ‌وقت
     * پیدایش نمی‌کند. همان الگویِ ‎Product_Archive::document_id()‎.
     */
    private function document_id(): int {
        if (class_exists('\Elementor\Plugin')) {
            $document = \Elementor\Plugin::$instance->documents->get_current();

            if ($document) {
                return (int) $document->get_main_id();
            }
        }

        return (int) get_the_ID();
    }

    /* =====================================================================
     * رندر
     * =================================================================== */

    protected function render(): void {
        $settings = $this->get_settings_for_display();
        $panel_id = 'zig-search-panel-' . $this->get_id();

        printf(
            '<div class="zig-search" data-zig-search %s>',
            $this->config_attributes($settings)
        );

        /*
         * لایهٔ تیره، *بیرون* از پوسته و زیرِ آن: پوسته در حالتِ باز
         * ‎position: absolute‎ می‌شود و رویِ محتوا می‌نشیند، ولی بدونِ این
         * لایه هیچ چیزی پشتش را از بقیهٔ صفحه جدا نمی‌کند — نه بصری، نه
         * برایِ کلیک. ‎position: fixed‎ است تا کلِ ویوپورت را بگیرد، حتی
         * وقتی ویجت داخلِ یک هدرِ باریک نشسته باشد.
         */
        echo '<div class="zig-search__backdrop" hidden></div>';

        /*
         * تنها چیزی که به صفحه‌خوان می‌گوید «نتیجه‌ها عوض شدند».
         *
         * در الگویِ combobox، جابه‌جاییِ ‎aria-activedescendant‎ فقط گزینهٔ
         * *فعال* را اعلام می‌کند؛ اینکه اصلاً چند نتیجه آمد یا اینکه
         * فهرست تازه شد، هیچ‌جا گفته نمی‌شود. کاربرِ نابینا تایپ می‌کرد و
         * سکوت می‌شنید تا وقتی خودش فلش بزند.
         *
         * ‎polite‎ نه ‎assertive‎: با هر کاراکتر عوض می‌شود و قطعِ مکررِ
         * کلامِ صفحه‌خوان از سکوت بدتر است.
         */
        printf(
            '<div class="zig-search__status" role="status" aria-live="polite" data-template="%s"></div>',
            esc_attr((string) ($settings['results_announcement'] ?? ''))
        );

        echo '<div class="zig-search__shell">';
        // پوستِ کارت: یک لایهٔ تزئینیِ محض که فقط شفافیتش محو می‌شود،
        // تا جعبهٔ پوسته موقعِ بسته‌شدن دست‌نخورده بماند.
        echo '<span class="zig-search__surface" aria-hidden="true"></span>';

        $this->render_field($settings, $panel_id);
        $this->render_panel($settings, $panel_id);

        echo '</div>';

        $this->render_icon_templates($settings);

        echo '</div>';
    }

    /**
     * نشانه‌ای که ‎rawurlencode‎/ساختارِ بازنویسیِ لینکِ نتایج دست‌نخورده
     * می‌گذاردش — فقط حروف و رقمِ لاتین. جاوااسکریپت همین رشته را با
     * کوئریِ واقعی (encodeURIComponent‌شده) عوض می‌کند تا لینکِ چیپِ
     * تاریخچه/دکمهٔ «بیشتر» — که متنشان فقط سمتِ کلاینت معلوم می‌شود —
     * دقیقاً همان ساختارِ URLی را بگیرد که ‎get_search_link()‎ی سمتِ سرور
     * برایِ چیپ‌هایِ پرطرفدار می‌سازد؛ نه یک ‎؟s=‎ی دستی که روی سایت‌هایی
     * با بازنویسیِ لینکِ سرچِ سفارشی غلط از آب درمی‌آید.
     */
    private const RESULTS_URL_PLACEHOLDER = 'zzzZIGQUERYzzz';

    private function config_attributes(array $settings): string {
        $attrs = [
            'data-min-chars'            => (int) ($settings['min_chars'] ?? 2),
            'data-debounce'             => (int) ($settings['debounce_ms'] ?? 300),
            'data-shortcut'             => 'yes' === ($settings['enable_shortcut'] ?? 'yes') ? '1' : '0',
            'data-post-id'              => $this->document_id(),
            'data-widget-id'            => $this->get_id(),
            'data-rest-url'             => esc_url(rest_url('zig3d/v1/search')),
            'data-ajax-url'             => esc_url(admin_url('admin-ajax.php')),
            'data-ajax-action'          => Search_Endpoint::ACTION,
            'data-recent-enabled'       => 'yes' === ($settings['enable_recent'] ?? 'yes') ? '1' : '0',
            'data-recent-max'           => (int) ($settings['recent_max'] ?? 4),
            'data-recent-expiry-days'   => (int) ($settings['recent_expiry_days'] ?? 30),
            'data-recent-storage-key'   => 'zig3d_search_recent_' . $this->get_id(),
            'data-results-url-template' => esc_url($this->results_url(self::RESULTS_URL_PLACEHOLDER)),
        ];

        $html = '';

        foreach ($attrs as $name => $value) {
            $html .= sprintf(' %s="%s"', $name, esc_attr((string) $value));
        }

        return trim($html);
    }

    private function render_field(array $settings, string $panel_id): void {
        printf('<form class="zig-search__field" role="search" action="%s" method="get">', esc_url(home_url('/')));

        printf(
            '<button type="button" class="zig-search__icon-btn" tabindex="-1" aria-hidden="true">%s</button>',
            $this->render_icon($settings, 'search_icon')
        );

        printf(
            '<input type="text" name="s" class="zig-search__input" role="combobox" aria-expanded="false"'
            . ' aria-haspopup="listbox" aria-autocomplete="list" aria-controls="%s" autocomplete="off" placeholder="%s" />',
            esc_attr($panel_id),
            esc_attr((string) ($settings['placeholder_text'] ?? ''))
        );

        /*
         * X به *باز بودنِ پنل* گره خورده، نه به اینکه متنی تایپ شده باشد:
         * در طرح، هر چهار حالتِ باز — حتی «پیش فرض» که فیلدش خالی است —
         * این دکمه را دارند، و هر دو حالتِ بسته (S0 و S5، حتی وقتی مقدارِ
         * تایپ‌شده در فیلد مانده) ندارند. یعنی نقشش «بستنِ اورلی» است، نه
         * «پاک‌کردنِ متن». پس اینجا ‎hidden‎ شروع می‌شود و جاوااسکریپت
         * هم‌زمان با باز/بسته‌شدنِ پنل جابه‌جایش می‌کند.
         */
        printf(
            '<button type="button" class="zig-search__clear" hidden aria-label="%s">%s</button>',
            esc_attr__('پاک‌کردن جست‌وجو', 'zig3d-widgets'),
            $this->render_icon($settings, 'clear_icon')
        );

        echo '</form>';
    }

    private function render_panel(array $settings, string $panel_id): void {
        printf('<div class="zig-search__panel" id="%s" role="listbox" hidden>', esc_attr($panel_id));

        // بخش‌هایِ داده‌محور — پوسته‌شان سمتِ سرور است، محتوایشان سمتِ کلاینت
        $this->render_recent_section_shell($settings);
        $this->render_products_section_shell($settings);
        $this->render_empty_section_shell($settings);
        $this->render_error_section_shell($settings);

        // تنها بخشِ کاملاً سمتِ سرور، چون دادهٔ ثابتِ مدیریتی است
        $this->render_popular_section($settings);

        echo '</div>';
    }

    private function render_recent_section_shell(array $settings): void {
        if ('yes' !== ($settings['enable_recent'] ?? 'yes')) {
            return;
        }

        echo '<div class="zig-search__section zig-search__section--recent" hidden>';
        echo '<div class="zig-search__section-head">';
        printf('<span class="zig-search__section-title">%s</span>', esc_html((string) ($settings['recent_heading_text'] ?? '')));
        printf(
            '<button type="button" class="zig-search__clear-history">%s</button>',
            esc_html((string) ($settings['clear_history_text'] ?? ''))
        );
        echo '</div>';
        echo '<div class="zig-search__chips" data-role="recent-chips"></div>';
        echo '</div>';
    }

    private function render_products_section_shell(array $settings): void {
        echo '<div class="zig-search__section zig-search__section--products" hidden>';
        echo '<div class="zig-search__section-head">';
        printf('<span class="zig-search__section-title">%s</span>', esc_html((string) ($settings['products_heading_text'] ?? '')));
        echo '</div>';
        echo '<div class="zig-search__products" data-role="products"></div>';

        /*
         * لینکِ واقعی، نه دکمه: سقفِ نتیجه یک تصمیمِ سمتِ سرور است (از
         * تنظیماتِ ویجت) و کلاینت اجازه ندارد با یک عددِ بزرگ‌تر دوباره
         * بخواهدش — «بیشتر» یعنی برو صفحهٔ نتایج، نه AJAXِ صفحه‌بندی‌شده.
         * جاوااسکریپت فقط ‎href‎ را با کوئریِ جاری پر می‌کند، دقیقاً همان
         * کاری که با چیپ‌ها می‌کند.
         */
        printf(
            '<a class="zig-search__more" hidden><span>%s</span>%s</a>',
            esc_html((string) ($settings['more_button_text'] ?? '')),
            $this->render_icon($settings, 'more_icon')
        );
        echo '</div>';
    }

    private function render_empty_section_shell(array $settings): void {
        echo '<div class="zig-search__section zig-search__section--empty" hidden>';
        printf('<span class="zig-search__empty-icon">%s</span>', $this->render_icon($settings, 'empty_icon'));
        printf('<p class="zig-search__empty-text">%s</p>', esc_html((string) ($settings['empty_message'] ?? '')));
        echo '</div>';
    }

    /**
     * خطایِ فنی جایِ خودش را دارد، نه جایِ «نتیجه‌ای نبود».
     *
     * این تفکیک همان چیزی است که در قراردادِ آرشیو هم هست: «چیزی پیدا
     * نشد» یک وضعیتِ عادیِ محتواست، ولی «درخواست به سرور نرسید» یک خطای
     * فنی است. یکی‌کردنشان یعنی کاربری که شبکه‌اش قطع شده، خیال می‌کند
     * محصولی وجود ندارد.
     */
    private function render_error_section_shell(array $settings): void {
        echo '<div class="zig-search__section zig-search__section--error" hidden role="alert">';
        printf(
            '<p class="zig-search__error-text" data-message="%s" data-rate-limit-message="%s">%s</p>',
            esc_attr((string) ($settings['error_message'] ?? '')),
            esc_attr((string) ($settings['rate_limit_message'] ?? '')),
            esc_html((string) ($settings['error_message'] ?? ''))
        );
        echo '</div>';
    }

    private function render_popular_section(array $settings): void {
        $labels = $this->popular_labels($settings);

        if (!$labels) {
            return;
        }

        echo '<div class="zig-search__section zig-search__section--popular">';
        echo '<div class="zig-search__section-head">';
        printf('<span class="zig-search__section-title">%s</span>', esc_html((string) ($settings['popular_heading_text'] ?? '')));
        echo '</div>';
        echo '<div class="zig-search__chips" data-role="popular-chips">';

        foreach ($labels as $label) {
            printf(
                // آیکون *بعدِ* متن — در راست‌به‌چپ یعنی سمتِ چپِ چیپ، همان‌جا که طرح گذاشته
                '<a class="zig-search__chip zig-search__chip--popular" href="%s"><bdi>%s</bdi>%s</a>',
                esc_url($this->results_url($label)),
                esc_html($label),
                $this->render_icon($settings, 'popular_icon')
            );
        }

        echo '</div>';
        echo '</div>';
    }

    /**
     * قالب‌هایِ آیکونی که جاوااسکریپت برایِ چیپ‌ها/ردیف‌هایِ داینامیک از
     * رویشان clone می‌کند — یک‌بار رندر می‌شود، هر بار در سمتِ کلاینت
     * تکثیر می‌شود؛ نه اینکه JS خودش کتابخانهٔ آیکون را بشناسد.
     */
    private function render_icon_templates(array $settings): void {
        $templates = [
            'recent-icon'  => 'recent_icon',
            'chevron-icon' => 'chevron_icon',
        ];

        foreach ($templates as $slot => $key) {
            printf('<template data-zig-icon="%s">%s</template>', esc_attr($slot), $this->render_icon($settings, $key));
        }
    }

    /**
     * آیکونِ یک جایگاه: انتخابِ مدیر اگر چیزی انتخاب کرده، وگرنه SVGی
     * خودِ طرح.
     *
     * ترتیب عمداً همین است. آیکونِ طرح «پیش‌فرض» است نه «اجبار»؛ لحظه‌ای
     * که مدیر چیزی از کتابخانه انتخاب کند، همان می‌نشیند.
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

    /**
     * آدرسِ صفحهٔ نتایج — با همان دامنه‌ای که خودِ اورلی دارد.
     *
     * ‎get_search_link()‎ به‌تنهایی سرچِ عمومیِ وردپرس را می‌دهد: نوشته و
     * برگه هم می‌آیند. ولی این ویجت فقط محصول جست‌وجو می‌کند؛ اگر «مشاهدهٔ
     * نتایجِ بیشتر» به سرچِ عمومی برود، کاربر از فهرستی از محصولات به
     * فهرستی می‌رسد که نصفش برگهٔ «تماس با ما» است. پس ‎post_type=product‎
     * روی آدرس می‌ماند تا دو طرف یک چیز را بگویند.
     */
    private function results_url(string $query): string {
        $url = function_exists('get_search_link')
            ? get_search_link($query)
            : add_query_arg('s', rawurlencode($query), home_url('/'));

        return add_query_arg('post_type', 'product', $url);
    }
}
