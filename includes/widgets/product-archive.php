<?php
namespace Zig3d_Widgets\Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Repeater;
use Elementor\Widget_Base;
use Zig3d_Widgets\Archive_Endpoint;
use Zig3d_Widgets\Archive_Head;
use Zig3d_Widgets\Archive_Query;
use Zig3d_Widgets\Attributes;
use Zig3d_Widgets\Card;
use Zig3d_Widgets\Facets;
use Zig3d_Widgets\Markup;
use Zig3d_Widgets\Plugin;
use Zig3d_Widgets\Price;
use Zig3d_Widgets\Product_Card;
use Zig3d_Widgets\Query_State;
use Zig3d_Widgets\Schema_Store;
use Zig3d_Widgets\Seo;
use Zig3d_Widgets\Sorting;
use Zig3d_Widgets\Stock;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * آرشیو محصولات: سایدبار فیلتر، نوار ترتیب، گرید و صفحه‌بندی.
 *
 * اولین ویجت این افزونه که جاوااسکریپت لازم دارد، و همان‌جا یک قاعده
 * حاکم است: **هرچه اینجا رندر می‌شود، بدون جاوااسکریپت هم کار می‌کند.**
 *
 * هر گزینهٔ فیلتر، هر پیل ترتیب و هر شمارهٔ صفحه یک ‎<a href>‎ واقعی است
 * که به آدرس همان حالت اشاره می‌کند. جاوااسکریپت بعداً روی همان‌ها سوار
 * می‌شود و به‌جای پیمایش، محتوا را عوض می‌کند. دو دلیل دارد و هیچ‌کدام
 * تزئینی نیست:
 *
 *   • صفحه بدون JS کار می‌کند — روی اتصال ضعیف، وسط بارگذاری، یا وقتی
 *     یک اسکریپت دیگر خطا داده و بقیه را متوقف کرده.
 *   • خزنده‌ها راهی به صفحهٔ دوم و به حالت‌های فیلترشده دارند. خزنده‌های
 *     هوش مصنوعی جاوااسکریپت اجرا نمی‌کنند؛ برایشان یک ‎<button>‎ با
 *     ‎onclick‎ یعنی بن‌بست.
 *
 * تصمیم‌های سئوی خودِ صفحه (‎noindex‎، ‎canonical‎، ‎404‎) اینجا نیستند —
 * در ‎Archive_Head‎اند، چون تا زمان رندر این ویجت، ‎<head>‎ خیلی وقت است
 * بسته شده.
 */
final class Product_Archive extends Widget_Base {

    public function get_name(): string {
        return 'zig3d-product-archive';
    }

    public function get_title(): string {
        return __('آرشیو محصولات زیگ', 'zig3d-widgets');
    }

    public function get_icon(): string {
        return 'eicon-products';
    }

    public function get_categories(): array {
        return [Plugin::CATEGORY];
    }

    public function get_style_depends(): array {
        return ['zig3d-widgets'];
    }

    /**
     * تنها ویجت این افزونه که اسکریپت دارد.
     *
     * از راه ‎get_script_depends‎ می‌آید و نه ‎wp_enqueue_script‎ سراسری:
     * صفحه‌ای که این ویجت رویش نیست، هیچ فایلی لود نمی‌کند.
     */
    public function get_script_depends(): array {
        return ['zig3d-archive'];
    }

    public function get_keywords(): array {
        return ['product', 'archive', 'filter', 'shop', 'محصول', 'فیلتر', 'فروشگاه'];
    }

    /**
     * این ویجت هرگز نباید کش شود.
     *
     * المنتور پیش‌فرض را روی «پویا» گذاشته، پس امروز هم بدون این متد درست
     * کار می‌کند. صریح نوشتنش برای این است که خروجی این ویجت به
     * ‎$_GET‎ بند است — فیلتر، ترتیب و صفحه — و اگر روزی کسی این متد را
     * ‎false‎ کند (یا پیش‌فرض عوض شود)، همهٔ بازدیدکننده‌ها نتیجهٔ فیلترشدهٔ
     * *اولین* کسی را می‌بینند که صفحه را باز کرده. هیچ خطایی هم نمی‌دهد.
     */
    protected function is_dynamic_content(): bool {
        return true;
    }

    /* =====================================================================
     * کنترل‌ها
     * =================================================================== */

    protected function register_controls(): void {
        $this->section_query();
        $this->section_fields();
        $this->section_filters();
        $this->section_sorting();
        $this->section_card();
        $this->section_pagination();
        $this->section_states();

        $this->section_style_grid();
        $this->section_style_media();
        $this->section_style_card();
        $this->section_style_stock();
        $this->section_style_price();
        $this->section_style_chrome();
        $this->section_style_mobile();
    }

    private function section_query(): void {
        $this->start_controls_section('sec_query', [
            'label' => __('محصولات', 'zig3d-widgets'),
        ]);

        $this->add_control('source', [
            'label'   => __('دامنه', 'zig3d-widgets'),
            'type'    => Controls_Manager::SELECT,
            'default' => 'archive',
            'options' => [
                'archive' => __('همین صفحهٔ آرشیو', 'zig3d-widgets'),
                'custom'  => __('دسته‌های انتخابی', 'zig3d-widgets'),
            ],
        ]);

        $this->add_control('categories', [
            'label'       => __('دسته‌ها', 'zig3d-widgets'),
            'type'        => Controls_Manager::SELECT2,
            'multiple'    => true,
            'options'     => $this->category_options(),
            'condition'   => ['source' => 'custom'],
            'label_block' => true,
        ]);

        $this->add_control('per_page', [
            'label'   => __('تعداد در هر صفحه', 'zig3d-widgets'),
            'type'    => Controls_Manager::NUMBER,
            'default' => 9,
            'min'     => 1,
            'max'     => Archive_Query::MAX_PER_PAGE,
        ]);

        /*
         * ‎tablet_default‎/‎mobile_default‎ جای آن ریستِ سختِ CSS را می‌گیرند
         * که قبلاً «‎@media(max-width:879px){grid-template-columns:repeat(2)}‎»
         * بود. آن ریست ‎grid-template-columns‎ را *مستقیم* می‌نوشت، پس متغیرِ
         * ‎--zig-archive-columns‎ی که این کنترل عوض می‌کرد را نادیده می‌گرفت:
         * مدیر موبایل را ۱ می‌گذاشت و باز ۲ ستون می‌دید. با پیش‌فرضِ
         * بریک‌پوینتی، خودِ کنترل مقدارِ هر تیر را می‌نویسد (۲ تبلت، ۲
         * موبایل، مگر اینکه مدیر عوضش کند) و دیگر هیچ ریستِ سختی لازم نیست.
         */
        $this->add_responsive_control('columns', [
            'label'          => __('تعداد ستون', 'zig3d-widgets'),
            'type'           => Controls_Manager::NUMBER,
            'default'        => 3,
            'tablet_default' => 2,
            'mobile_default' => 2,
            'min'            => 1,
            'max'            => 6,
            'selectors' => [
                '{{WRAPPER}} .zig-archive__grid' => '--zig-archive-columns: {{VALUE}};',
            ],
        ]);

        $this->add_control('card_source', [
            'label'       => __('قالب کارت', 'zig3d-widgets'),
            'type'        => Controls_Manager::SELECT,
            'default'     => 'internal',
            'options'     => [
                'internal' => __('داخلی (طبق دیزاین)', 'zig3d-widgets'),
                'template' => __('قالب المنتور یا آیتم جت‌انجین', 'zig3d-widgets'),
            ],
            'separator'   => 'before',
        ]);

        $this->add_control('card_template', [
            'label'     => __('قالب', 'zig3d-widgets'),
            'type'      => Controls_Manager::SELECT2,
            'options'   => $this->template_options(),
            'condition' => ['card_source' => 'template'],
        ]);

        $this->end_controls_section();
    }

    /**
     * کلیدهای متا.
     *
     * هیچ‌کدام پیش‌فرض ندارند و این عمدی است: یک کلید حدسی که در این نصب
     * وجود ندارد، بی‌سروصدا خالی برمی‌گردد و کارت بدون آن بخش رندر می‌شود.
     * خالی‌بودنِ فیلد در پنل دست‌کم پیداست.
     */
    private function section_fields(): void {
        $this->start_controls_section('sec_fields', [
            'label' => __('فیلدهای محصول', 'zig3d-widgets'),
        ]);

        $this->add_control('meta_suggested', [
            'label'       => __('کلید متای «پیشنهاد»', 'zig3d-widgets'),
            'type'        => Controls_Manager::TEXT,
            'description' => __('چک‌باکسی که اگر فعال باشد، ریبون «پیشنهاد» می‌آید.', 'zig3d-widgets'),
        ]);

        $this->add_control('meta_description', [
            'label' => __('کلید متای توضیح کوتاه', 'zig3d-widgets'),
            'type'  => Controls_Manager::TEXT,
        ]);

        $this->add_control('meta_features', [
            'label'       => __('کلید متای رپیتر ویژگی‌ها', 'zig3d-widgets'),
            'type'        => Controls_Manager::TEXT,
            'separator'   => 'before',
        ]);

        $this->add_control('meta_features_field', [
            'label'       => __('نام زیرفیلد برچسب', 'zig3d-widgets'),
            'type'        => Controls_Manager::TEXT,
            'description' => __('خالی بگذارید اگر هر ردیف فقط یک مقدار دارد.', 'zig3d-widgets'),
            'condition'   => ['meta_features!' => ''],
        ]);

        $this->add_control('features_attrs', [
            'label'       => __('ویژگی‌های ووکامرس (جایگزین رپیتر)', 'zig3d-widgets'),
            'type'        => Controls_Manager::SELECT2,
            'multiple'    => true,
            'options'     => $this->attribute_options(),
            'label_block' => true,
            'description' => __('خالی یعنی خودکار: ویژگی‌های نمایشی که برای گزینه‌سازی استفاده نشده‌اند.', 'zig3d-widgets'),
        ]);

        $this->add_control('features_max', [
            'label'   => __('حداکثر ویژگی', 'zig3d-widgets'),
            'type'    => Controls_Manager::NUMBER,
            'default' => 3,
            'min'     => 1,
            'max'     => 6,
        ]);

        $this->add_control('brand_taxonomy', [
            'label'     => __('ویژگیِ برند', 'zig3d-widgets'),
            'type'      => Controls_Manager::SELECT,
            'options'   => ['' => __('— بدون برند —', 'zig3d-widgets')] + $this->attribute_options(),
            'separator' => 'before',
        ]);

        $this->end_controls_section();
    }

    private function section_filters(): void {
        $this->start_controls_section('sec_filters', [
            'label' => __('فیلترها', 'zig3d-widgets'),
        ]);

        $this->add_control('filters_on', [
            'label'     => __('نمایش سایدبار فیلتر', 'zig3d-widgets'),
            'type'      => Controls_Manager::SWITCHER,
            'default'   => 'yes',
        ]);

        $this->add_control('filters_title', [
            'label'     => __('عنوان', 'zig3d-widgets'),
            'type'      => Controls_Manager::TEXT,
            'default'   => __('فیلترها', 'zig3d-widgets'),
            'condition' => ['filters_on' => 'yes'],
        ]);

        $this->add_control('filters_notice', [
            'type'      => Controls_Manager::RAW_HTML,
            'raw'       => $this->filters_notice(),
            'condition' => ['filters_on' => 'yes'],
        ]);

        $this->add_control('filters_position', [
            'label'     => __('جای سایدبار', 'zig3d-widgets'),
            'type'      => Controls_Manager::SELECT,
            'default'   => 'start',
            'options'   => [
                'start' => __('ابتدای ردیف', 'zig3d-widgets'),
                'end'   => __('انتهای ردیف', 'zig3d-widgets'),
            ],
            'condition' => ['filters_on' => 'yes'],
        ]);

        $this->add_control('filters_active_title', [
            'label'     => __('عنوان «فیلترهای اعمال شده»', 'zig3d-widgets'),
            'type'      => Controls_Manager::TEXT,
            'default'   => __('فیلترهای اعمال شده', 'zig3d-widgets'),
            'condition' => ['filters_on' => 'yes'],
        ]);

        $this->add_control('filters_active_text', [
            'label'       => __('متن شمارندهٔ فیلتر فعال', 'zig3d-widgets'),
            'type'        => Controls_Manager::TEXT,
            'default'     => __('{count} فیلتر فعال', 'zig3d-widgets'),
            'description' => __('‌{count} با تعداد فیلترهای فعال جایگزین می‌شود.', 'zig3d-widgets'),
            'condition'   => ['filters_on' => 'yes'],
        ]);

        /*
         * گزینه‌ای که هیچ نتیجه‌ای ندارد: بماند یا برود؟
         *
         * پیش‌فرض «بماند»، چون حذف‌شدنش دو هزینه دارد: ارتفاع پنل با هر
         * درخواست می‌پرد و کاربر جای گزینه‌ای را که یک لحظه پیش دیده بود گم
         * می‌کند، و مهم‌تر، «۵ محور نداریم» خودش یک اطلاعات است — با
         * نبودنش، کاربر فکر می‌کند فروشگاه اصلاً چنین چیزی ندارد.
         *
         * ولی روی فروشگاهی با صدها ترم، همان گزینه‌های صفر بیشتر فهرست را
         * می‌گیرند. پس تصمیمش با مدیر است.
         *
         * گزینهٔ *انتخاب‌شده* هیچ‌وقت حذف نمی‌شود، حتی وقتی صفر است — وگرنه
         * کاربر قیدی می‌داشت که می‌بیندش ولی نمی‌تواند برش دارد.
         */
        $this->add_control('show_empty_options', [
            'label'        => __('نمایش گزینه‌های بدون نتیجه', 'zig3d-widgets'),
            'type'         => Controls_Manager::SWITCHER,
            'default'      => 'yes',
            'description'  => __('گزینه‌هایی که با فیلترهای فعلی هیچ محصولی ندارند، خاکستری و غیرفعال نشان داده می‌شوند.', 'zig3d-widgets'),
            'condition'    => ['filters_on' => 'yes'],
        ]);

        $this->add_control('filters_clear', [
            'label'     => __('متن «حذف همه»', 'zig3d-widgets'),
            'type'      => Controls_Manager::TEXT,
            'default'   => __('حذف همه', 'zig3d-widgets'),
            'condition' => ['filters_on' => 'yes'],
        ]);

        /*
         * تأخیر اعمال فیلتر. برای چک‌باکس — که رویدادش گسسته است، نه تایپِ
         * پیوسته — چیزی حدود یک‌چهارم ثانیه کافی است: آن‌قدر که چند تیکِ
         * پشت‌سرهم یک درخواست شوند، و آن‌قدر کوتاه که کاربر منتظر نماند.
         */
        $this->add_control('filters_debounce', [
            'label'     => __('تأخیر اعمال (میلی‌ثانیه)', 'zig3d-widgets'),
            'type'      => Controls_Manager::NUMBER,
            'default'   => 250,
            'min'       => 0,
            'max'       => 1000,
            'step'      => 50,
            'condition' => ['filters_on' => 'yes'],
        ]);

        $this->end_controls_section();
    }

    private function section_sorting(): void {
        $this->start_controls_section('sec_sorting', [
            'label' => __('ترتیب', 'zig3d-widgets'),
        ]);

        $this->add_control('sorting_on', [
            'label'   => __('نمایش نوار ترتیب', 'zig3d-widgets'),
            'type'    => Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);

        $this->add_control('count_on', [
            'label'     => __('نمایش تعداد نتیجه', 'zig3d-widgets'),
            'type'      => Controls_Manager::SWITCHER,
            'default'   => 'yes',
            'condition' => ['sorting_on' => 'yes'],
        ]);

        $this->add_control('count_text', [
            'label'       => __('قالب متن تعداد', 'zig3d-widgets'),
            'type'        => Controls_Manager::TEXT,
            'default'     => __('{count} محصول', 'zig3d-widgets'),
            'condition'   => ['sorting_on' => 'yes', 'count_on' => 'yes'],
            'description' => __('‎{count}‎ با عدد جایگزین می‌شود.', 'zig3d-widgets'),
        ]);

        $repeater = new Repeater();

        $repeater->add_control('label', [
            'label' => __('برچسب', 'zig3d-widgets'),
            'type'  => Controls_Manager::TEXT,
        ]);

        $repeater->add_control('type', [
            'label'   => __('نوع', 'zig3d-widgets'),
            'type'    => Controls_Manager::SELECT,
            'default' => 'default',
            'options' => $this->sort_type_options(),
        ]);

        /*
         * جهت فقط برای انواعی که جهت‌شان معنا دارد. ووکامرس جهتِ قیمت را
         * داخل خودِ نوع کدگذاری کرده و کنترل جدا، دو منبع متناقض می‌ساخت.
         */
        $repeater->add_control('order', [
            'label'     => __('جهت', 'zig3d-widgets'),
            'type'      => Controls_Manager::SELECT,
            'default'   => 'DESC',
            'options'   => [
                'DESC' => __('نزولی', 'zig3d-widgets'),
                'ASC'  => __('صعودی', 'zig3d-widgets'),
            ],
            'condition' => ['type' => $this->directional_types()],
        ]);

        $repeater->add_control('meta_key', [
            'label'     => __('کلید متا', 'zig3d-widgets'),
            'type'      => Controls_Manager::TEXT,
            'condition' => ['type' => 'meta'],
        ]);

        $repeater->add_control('meta_type', [
            'label'     => __('نوع مقدار', 'zig3d-widgets'),
            'type'      => Controls_Manager::SELECT,
            'default'   => 'num',
            'options'   => [
                'num'  => __('عددی', 'zig3d-widgets'),
                'text' => __('متنی', 'zig3d-widgets'),
            ],
            'condition' => ['type' => 'meta'],
        ]);

        $this->add_control('sorting_options', [
            'label'       => __('گزینه‌ها', 'zig3d-widgets'),
            'type'        => Controls_Manager::REPEATER,
            'fields'      => $repeater->get_controls(),
            'title_field' => '{{{ label }}}',
            'condition'   => ['sorting_on' => 'yes'],
            'default'     => [
                ['label' => __('پرفروش‌ترین', 'zig3d-widgets'), 'type' => 'popularity'],
                ['label' => __('جدیدترین', 'zig3d-widgets'), 'type' => 'date'],
                ['label' => __('ارزان‌ترین', 'zig3d-widgets'), 'type' => 'price'],
                ['label' => __('گران‌ترین', 'zig3d-widgets'), 'type' => 'price-desc'],
            ],
        ]);

        /*
         * فقط در موبایل دیده می‌شود: عنوانِ بالایِ شیتِ مرتب‌سازی. خالی
         * یعنی بدونِ عنوان — همان الگویِ بقیهٔ متن‌هایِ اختیاریِ این ویجت.
         */
        $this->add_control('sort_sheet_title', [
            'label'     => __('عنوانِ شیتِ ترتیب (موبایل)', 'zig3d-widgets'),
            'type'      => Controls_Manager::TEXT,
            'default'   => __('مرتب‌سازی', 'zig3d-widgets'),
            'condition' => ['sorting_on' => 'yes'],
        ]);

        $this->end_controls_section();
    }

    private function section_card(): void {
        $this->start_controls_section('sec_card', [
            'label' => __('کارت محصول', 'zig3d-widgets'),
        ]);

        /*
         * خطِ دسته پیش‌فرض خاموش است.
         *
         * در دیزاین نیست و کارت هم بدون آن کامل است: چیزی که بالای عنوان
         * دیده می‌شود برند است، نه دسته. ولی جایگاهش می‌ماند، چون در
         * آرشیوی که چند دستهٔ خواهر را کنار هم می‌آورد واقعاً به کار
         * می‌آید.
         */
        $this->add_control('show_category', [
            'label'       => __('نمایش دستهٔ محصول', 'zig3d-widgets'),
            'type'        => Controls_Manager::SWITCHER,
            'default'     => '',
            'description' => __('عمیق‌ترین دسته‌ای که محصول در آن است. بالای عنوان می‌نشیند.', 'zig3d-widgets'),
        ]);

        $this->add_control('label_suggested', [
            'label'   => __('متن ریبون پیشنهاد', 'zig3d-widgets'),
            'type'    => Controls_Manager::TEXT,
            'default' => __('پیشنهاد', 'zig3d-widgets'),
        ]);

        $this->add_control('title_lines', [
            'label'   => __('حداکثر خط عنوان', 'zig3d-widgets'),
            'type'    => Controls_Manager::NUMBER,
            'default' => 2,
            'min'     => 1,
            'max'     => 5,
            'selectors' => [
                '{{WRAPPER}} .zig-card__title' => '-webkit-line-clamp: {{VALUE}};',
            ],
        ]);

        $this->add_control('desc_lines', [
            'label'   => __('حداکثر خط توضیح', 'zig3d-widgets'),
            'type'    => Controls_Manager::NUMBER,
            'default' => 2,
            'min'     => 1,
            'max'     => 5,
            'selectors' => [
                '{{WRAPPER}} .zig-card__desc' => '-webkit-line-clamp: {{VALUE}};',
            ],
        ]);

        /* --- موجودی: هر حالت، متن خودش --- */

        $this->add_control('stock_heading', [
            'label'     => __('لیبل‌های موجودی', 'zig3d-widgets'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        foreach ($this->stock_labels() as $state => $label) {
            $this->add_control('label_stock_' . $state, [
                'label'   => $label['title'],
                'type'    => Controls_Manager::TEXT,
                'default' => $label['default'],
            ]);
        }

        /* --- قیمت: سه حالت مستقل --- */

        $this->add_control('price_heading', [
            'label'     => __('قیمت', 'zig3d-widgets'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_control('variable_mode', [
            'label'   => __('محصول چندقیمتی', 'zig3d-widgets'),
            'type'    => Controls_Manager::SELECT,
            'default' => 'min',
            'options' => [
                'min'   => __('کمترین قیمت', 'zig3d-widgets'),
                'range' => __('بازهٔ قیمت', 'zig3d-widgets'),
            ],
        ]);

        $this->add_control('label_price_from', [
            'label'   => __('پیشوند «قیمت از»', 'zig3d-widgets'),
            'type'    => Controls_Manager::TEXT,
            'default' => __('قیمت از', 'zig3d-widgets'),
        ]);

        /*
         * پیشوندِ قیمتِ دقیق پیش‌فرض ندارد، و این عمدی است: در دیزاین،
         * قیمتِ قطعی تنها می‌آید و فقط قیمتِ «از» پیشوند می‌گیرد — چون
         * پیشوند آنجا اطلاعات اضافه می‌کند («این کمترینش است»)، ولی روی
         * قیمت قطعی فقط یک کلمهٔ تکراری کنار عدد است.
         */
        $this->add_control('label_price_exact', [
            'label'       => __('پیشوند «قیمت»', 'zig3d-widgets'),
            'type'        => Controls_Manager::TEXT,
            'default'     => '',
            'placeholder' => __('بدون پیشوند', 'zig3d-widgets'),
        ]);

        $this->add_control('no_price', [
            'label'   => __('محصول بدون قیمت', 'zig3d-widgets'),
            'type'    => Controls_Manager::SELECT,
            'default' => Card::PRICE_INQUIRY,
            'options' => [
                Card::PRICE_INQUIRY => __('متن استعلامی', 'zig3d-widgets'),
                Card::PRICE_HIDDEN  => __('پنهان', 'zig3d-widgets'),
            ],
        ]);

        $this->add_control('label_price_inquiry', [
            'label'     => __('متن استعلامی', 'zig3d-widgets'),
            'type'      => Controls_Manager::TEXT,
            'default'   => __('استعلام قیمت', 'zig3d-widgets'),
            'condition' => ['no_price' => Card::PRICE_INQUIRY],
        ]);

        $this->add_control('currency', [
            'label'       => __('واحد پول', 'zig3d-widgets'),
            'type'        => Controls_Manager::TEXT,
            'default'     => __('تومان', 'zig3d-widgets'),
        ]);

        /* --- اقدام --- */

        $this->add_control('cta_heading', [
            'label'     => __('دکمهٔ کارت', 'zig3d-widgets'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        /*
         * حالت «خودکار» رابطهٔ قیمت و اقدام را فقط به‌عنوان یک پیش‌فرض
         * برقرار می‌کند، نه یک قفل: محصول استعلامی به استعلام می‌رود، ولی
         * هر انتخاب صریحی بر آن می‌چربد.
         */
        $this->add_control('cta_mode', [
            'label'   => __('اقدام', 'zig3d-widgets'),
            'type'    => Controls_Manager::SELECT,
            'default' => Card::CTA_AUTO,
            'options' => [
                Card::CTA_AUTO         => __('خودکار', 'zig3d-widgets'),
                Card::CTA_DETAILS      => __('جزئیات محصول', 'zig3d-widgets'),
                Card::CTA_INQUIRY      => __('استعلام قیمت', 'zig3d-widgets'),
                Card::CTA_CONSULTATION => __('درخواست مشاوره', 'zig3d-widgets'),
            ],
        ]);

        foreach ($this->cta_labels() as $mode => $label) {
            $this->add_control('label_cta_' . $mode, [
                'label'   => $label['title'],
                'type'    => Controls_Manager::TEXT,
                'default' => $label['default'],
            ]);

            if ('' !== $label['url']) {
                $this->add_control('url_cta_' . $mode, [
                    'label' => $label['url'],
                    'type'  => Controls_Manager::URL,
                ]);
            }
        }

        $this->end_controls_section();
    }

    private function section_pagination(): void {
        $this->start_controls_section('sec_pagination', [
            'label' => __('صفحه‌بندی', 'zig3d-widgets'),
        ]);

        /*
         * سقف اسکرول خودکار. تا این صفحه، محصولات به انتهای گرید اضافه
         * می‌شوند؛ از آن به بعد ناوبری صریح می‌آید. الگویی که موبایل
         * Google Shopping هم می‌رود: محتوا با اسکرول می‌آید ولی هر صفحهٔ
         * منطقی آدرس خودش را دارد.
         */
        $this->add_control('scroll_pages', [
            'label'       => __('حداکثر صفحات اسکرول خودکار', 'zig3d-widgets'),
            'type'        => Controls_Manager::NUMBER,
            'default'     => 2,
            'min'         => 0,
            'max'         => 20,
            'description' => __('صفر یعنی همیشه صفحه‌بندی صریح.', 'zig3d-widgets'),
        ]);

        $this->add_control('restore_state', [
            'label'       => __('بازگرداندن موقعیت هنگام برگشت', 'zig3d-widgets'),
            'type'        => Controls_Manager::SWITCHER,
            'default'     => 'yes',
            'description' => __('صفحه و موقعیت اسکرول، هنگام برگشت از صفحهٔ محصول.', 'zig3d-widgets'),
        ]);

        $this->add_control('label_prev', [
            'label'   => __('متن «قبلی»', 'zig3d-widgets'),
            'type'    => Controls_Manager::TEXT,
            'default' => __('قبلی', 'zig3d-widgets'),
        ]);

        $this->add_control('label_next', [
            'label'   => __('متن «بعدی»', 'zig3d-widgets'),
            'type'    => Controls_Manager::TEXT,
            'default' => __('بعدی', 'zig3d-widgets'),
        ]);

        $this->end_controls_section();
    }

    private function section_states(): void {
        $this->start_controls_section('sec_states', [
            'label' => __('حالت‌ها', 'zig3d-widgets'),
        ]);

        $this->add_control('empty_template', [
            'label'   => __('قالب «نتیجه‌ای نیست»', 'zig3d-widgets'),
            'type'    => Controls_Manager::SELECT2,
            'options' => $this->template_options(),
        ]);

        $this->add_control('empty_title', [
            'label'     => __('عنوان پیش‌فرض', 'zig3d-widgets'),
            'type'      => Controls_Manager::TEXT,
            'default'   => __('محصولی با این فیلترها پیدا نشد', 'zig3d-widgets'),
            'condition' => ['empty_template' => ''],
        ]);

        $this->add_control('error_heading', [
            'label'     => __('خطای بارگذاری', 'zig3d-widgets'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_control('error_text', [
            'label'   => __('متن', 'zig3d-widgets'),
            'type'    => Controls_Manager::TEXT,
            'default' => __('مشکلی در بارگذاری محصولات پیش آمد.', 'zig3d-widgets'),
        ]);

        $this->add_control('error_retry', [
            'label'   => __('متن دکمهٔ تلاش مجدد', 'zig3d-widgets'),
            'type'    => Controls_Manager::TEXT,
            'default' => __('تلاش مجدد', 'zig3d-widgets'),
        ]);

        $this->add_control('error_position', [
            'label'   => __('جای پیام', 'zig3d-widgets'),
            'type'    => Controls_Manager::SELECT,
            'default' => 'bottom-center',
            'options' => [
                'bottom-center' => __('پایین، وسط', 'zig3d-widgets'),
                'bottom-start'  => __('پایین، ابتدای صفحه', 'zig3d-widgets'),
                'bottom-end'    => __('پایین، انتهای صفحه', 'zig3d-widgets'),
                'top-grid'      => __('بالای گرید', 'zig3d-widgets'),
            ],
        ]);

        $this->add_control('error_anim', [
            'label'       => __('نحوهٔ ظاهرشدن', 'zig3d-widgets'),
            'type'        => Controls_Manager::SELECT,
            'default'     => 'slide',
            'options'     => [
                'slide' => __('سُر خوردن', 'zig3d-widgets'),
                'fade'  => __('محو', 'zig3d-widgets'),
                'none'  => __('بدون حرکت', 'zig3d-widgets'),
            ],
            'description' => __('اگر کاربر «حرکت کمتر» را روشن کرده باشد، حرکت خودکار حذف می‌شود.', 'zig3d-widgets'),
        ]);

        $this->end_controls_section();
    }



    /* =====================================================================
     * تب استایل
     * =================================================================== */

    private function section_style_grid(): void {
        $this->start_controls_section('sty_grid', [
            'label' => __('گرید', 'zig3d-widgets'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_responsive_control('grid_gap', [
            'label'      => __('فاصلهٔ کارت‌ها', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', 'rem'],
            'default'    => ['size' => 24, 'unit' => 'px'],
            'range'      => ['px' => ['min' => 0, 'max' => 80]],
            'selectors'  => [
                '{{WRAPPER}} .zig-archive__grid' => 'gap: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('sidebar_width', [
            'label'      => __('عرض سایدبار', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', '%'],
            /*
             * ۳۱۲ و نه ۲۸۰: ظرف بیرونیِ پنل حالا ۱۶ پیکسل پدینگ در هر
             * طرف دارد، پس اگر ستون همان ۲۸۰ می‌ماند، خودِ کارت ۳۲ پیکسل
             * باریک‌تر از قبل می‌شد. این عدد عرضِ *ستون* است، و پدینگ
             * تزئین است نه محتوا.
             */
            'default'    => ['size' => 312, 'unit' => 'px'],
            'range'      => ['px' => ['min' => 200, 'max' => 520], '%' => ['min' => 15, 'max' => 40]],
            'selectors'  => [
                '{{WRAPPER}} .zig-archive' => '--zig-archive-sidebar: {{SIZE}}{{UNIT}};',
            ],
            'condition'  => ['filters_on' => 'yes'],
        ]);

        $this->add_responsive_control('layout_gap', [
            'label'      => __('فاصلهٔ سایدبار تا گرید', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', 'rem'],
            'default'    => ['size' => 32, 'unit' => 'px'],
            'range'      => ['px' => ['min' => 0, 'max' => 96]],
            'selectors'  => [
                '{{WRAPPER}} .zig-archive' => 'gap: {{SIZE}}{{UNIT}};',
            ],
            'condition'  => ['filters_on' => 'yes'],
        ]);

        $this->end_controls_section();
    }

    /**
     * ظرف تصویر.
     *
     * پیش‌فرض‌ها دقیقاً همان چیزی‌اند که در دیزاین آمده — ارتفاع ۲۱۰،
     * پدینگ ۳۲/۱۶/۱۶، شعاع ۱۲، پس‌زمینهٔ ‎#F7F7FA‎ و عرض تصویر ۱۶۲.
     *
     * هر کنترل یک *متغیر* می‌نویسد، نه یک اعلانِ مستقیم. تفاوتش این است
     * که همان پیش‌فرض‌ها در ‎var()‎های شیت هم هستند، پس اگر فایل CSS سندِ
     * المنتور به صفحه نرسد — رندر بیرون از سند، قالب‌ساز دیگر، پیش‌نمایش
     * خام — کارت باز هم شکل دیزاین را دارد. با اعلان مستقیم، آن حالت‌ها
     * کارتِ بی‌ارتفاع و بی‌پدینگ می‌دادند.
     */
    private function section_style_media(): void {
        $this->start_controls_section('sty_media', [
            'label' => __('تصویر محصول', 'zig3d-widgets'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_responsive_control('media_height', [
            'label'      => __('ارتفاع ظرف', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', 'vh'],
            'default'    => ['size' => 210, 'unit' => 'px'],
            'range'      => ['px' => ['min' => 100, 'max' => 480]],
            'selectors'  => ['{{WRAPPER}} .zig-card' => '--zig-media-height: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_responsive_control('media_padding', [
            'label'      => __('فاصلهٔ داخلی', 'zig3d-widgets'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', 'rem'],
            'default'    => ['top' => 32, 'right' => 16, 'bottom' => 16, 'left' => 16, 'unit' => 'px', 'isLinked' => false],
            'selectors'  => [
                '{{WRAPPER}} .zig-card' => '--zig-media-padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('media_radius', [
            'label'      => __('شعاع گوشه', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', 'rem'],
            'default'    => ['size' => 12, 'unit' => 'px'],
            'range'      => ['px' => ['min' => 0, 'max' => 48]],
            'selectors'  => ['{{WRAPPER}} .zig-card' => '--zig-media-radius: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_control('media_background', [
            'label'     => __('پس‌زمینه', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#F7F7FA',
            'selectors' => ['{{WRAPPER}} .zig-card' => '--zig-media-bg: {{VALUE}};'],
        ]);

        $this->add_responsive_control('media_gap', [
            'label'      => __('فاصلهٔ عناصر داخل ظرف', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'default'    => ['size' => 2, 'unit' => 'px'],
            'range'      => ['px' => ['min' => 0, 'max' => 32]],
            'selectors'  => ['{{WRAPPER}} .zig-card' => '--zig-media-gap: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_responsive_control('image_width', [
            'label'      => __('عرض تصویر', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', '%'],
            'default'    => ['size' => 162, 'unit' => 'px'],
            'range'      => ['px' => ['min' => 60, 'max' => 400], '%' => ['min' => 20, 'max' => 100]],
            'selectors'  => ['{{WRAPPER}} .zig-card' => '--zig-image-width: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_control('image_fit', [
            'label'     => __('نحوهٔ جاگیری', 'zig3d-widgets'),
            'type'      => Controls_Manager::SELECT,
            'default'   => 'contain',
            'options'   => [
                'contain' => __('کامل دیده شود', 'zig3d-widgets'),
                'cover'   => __('کادر را پر کند', 'zig3d-widgets'),
            ],
            'selectors' => ['{{WRAPPER}} .zig-card' => '--zig-image-fit: {{VALUE}};'],
        ]);

        $this->end_controls_section();
    }

    private function section_style_card(): void {
        $this->start_controls_section('sty_card', [
            'label' => __('کارت', 'zig3d-widgets'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        /*
         * فاصله و ترتیب اینجا نیستند و نبودنشان عمدی است: بخش «ساختار
         * کارت» صاحبِ آن‌هاست. دو کنترل برای یک خاصیت، یعنی هر بار یکی از
         * دو مقدار بی‌صدا برنده می‌شود و مدیر نمی‌فهمد چرا اسلایدری که
         * کشیده کاری نمی‌کند.
         */
        $this->add_control('card_background', [
            'label'     => __('پس‌زمینه', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .zig-card' => '--zig-card-bg: {{VALUE}};'],
        ]);

        $this->add_control('card_border_color', [
            'label'     => __('رنگ کادر', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .zig-card' => '--zig-card-border: {{VALUE}};'],
        ]);

        $this->add_responsive_control('card_radius', [
            'label'      => __('شعاع گوشه', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', 'rem'],
            'default'    => ['size' => 16, 'unit' => 'px'],
            'range'      => ['px' => ['min' => 0, 'max' => 48]],
            'selectors'  => ['{{WRAPPER}} .zig-card' => '--zig-card-radius: {{SIZE}}{{UNIT}};'],
        ]);

        /*
         * هاور، همان‌طور که در دیزاین سه کارتِ اول نشان داده شده: فقط رنگ
         * کادر و سایه. هیچ جابه‌جایی‌ای در کار نیست — کارتی که زیر
         * مکان‌نما بالا می‌پرد، هدفی را که کاربر داشت به آن می‌رسید از زیر
         * دستش می‌کشد.
         */
        $this->add_control('card_hover_heading', [
            'label'     => __('حالت هاور', 'zig3d-widgets'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_control('card_hover_border', [
            'label'     => __('رنگ کادر در هاور', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .zig-card' => '--zig-card-hover-border: {{VALUE}};'],
        ]);

        $this->add_control('brand_color', [
            'label'     => __('رنگ برند', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'separator' => 'before',
            'selectors' => ['{{WRAPPER}} .zig-card' => '--zig-card-brand-color: {{VALUE}};'],
        ]);

        $this->add_control('title_color', [
            'label'     => __('رنگ عنوان', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .zig-card' => '--zig-title-color: {{VALUE}};'],
        ]);

        $this->add_control('desc_color', [
            'label'     => __('رنگ توضیح', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .zig-card' => '--zig-desc-color: {{VALUE}};'],
        ]);

        /*
         * ویژگی‌ها یک نوارند، نه چند حباب — پس رنگ هم روی خودِ نوار
         * می‌نشیند. تا وقتی این کنترل ‎.zig-card__feature‎ (تکِ ‎<li>‎) را
         * هدف می‌گرفت، مدیر رنگ را عوض می‌کرد و هیچ اتفاقی نمی‌افتاد:
         * پس‌زمینه مال ظرف بود، نه بچه‌ها.
         */
        $this->add_control('feature_background', [
            'label'     => __('پس‌زمینهٔ نوار ویژگی‌ها', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'separator' => 'before',
            'selectors' => ['{{WRAPPER}} .zig-card' => '--zig-features-bg: {{VALUE}};'],
        ]);

        $this->add_control('feature_color', [
            'label'     => __('رنگ متن ویژگی‌ها', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .zig-card' => '--zig-features-color: {{VALUE}};'],
        ]);

        $this->add_control('ribbon_background', [
            'label'     => __('پس‌زمینهٔ ریبون پیشنهاد', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'separator' => 'before',
            'selectors' => ['{{WRAPPER}} .zig-card' => '--zig-ribbon-bg: {{VALUE}};'],
        ]);

        $this->add_control('ribbon_color', [
            'label'     => __('رنگ متن ریبون', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .zig-card' => '--zig-ribbon-color: {{VALUE}};'],
        ]);

        $this->end_controls_section();
    }

    /**
     * موجودی — هر حالت، استایل مستقل خودش.
     *
     * یک ست رنگ مشترک با کلاس متفاوت کافی نبود: «آماده تحویل» و «ناموجود»
     * باید در یک نگاه از هم جدا باشند، و آن تفاوت چیزی است که مدیر باید
     * بتواند خودش تنظیمش کند، نه اینکه در CSS دفن شده باشد.
     */
    private function section_style_stock(): void {
        /* ---------------------------------------------------------------
         * ساختار کارت: شش ناحیه، هرکدام فاصله و ترتیب خودش
         *
         * ‎order‎ فقط جای *دیداری* را عوض می‌کند و ترتیب DOM دست‌نخورده
         * می‌ماند — پس صفحه‌خوان و خزنده همان ترتیب منطقی را می‌بینند،
         * حتی وقتی مدیر تصویر را زیر متن برده.
         * ------------------------------------------------------------ */

        $this->start_controls_section('sty_layout', [
            'label' => __('ساختار کارت', 'zig3d-widgets'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        foreach ($this->card_areas() as $key => $area) {
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
                    '{{WRAPPER}} .zig-card' => '--zig-' . $area['var'] . '-padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]);

            if (!empty($area['gap'])) {
                $this->add_responsive_control($key . '_gap', [
                    'label'      => __('فاصلهٔ داخلی اجزا', 'zig3d-widgets'),
                    'type'       => Controls_Manager::SLIDER,
                    'size_units' => ['px', 'rem'],
                    'range'      => ['px' => ['min' => 0, 'max' => 60]],
                    'selectors'  => [
                        '{{WRAPPER}} .zig-card' => '--zig-' . $area['var'] . '-gap: {{SIZE}}{{UNIT}};',
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
                        '{{WRAPPER}} .zig-card' => '--zig-' . $area['var'] . '-order: {{VALUE}};',
                    ],
                ]);
            }
        }

        $this->add_control('divider_heading', [
            'label'     => __('خط جداکنندهٔ بالای پا', 'zig3d-widgets'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_control('divider_color', [
            'label'     => __('رنگ خط', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .zig-card' => '--zig-divider-color: {{VALUE}};'],
        ]);

        $this->add_responsive_control('divider_width', [
            'label'      => __('ضخامت خط', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 8]],
            'selectors'  => ['{{WRAPPER}} .zig-card' => '--zig-divider-width: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_responsive_control('foot_margin', [
            'label'      => __('فاصله تا بالای خط', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', 'rem'],
            'range'      => ['px' => ['min' => 0, 'max' => 60]],
            'selectors'  => ['{{WRAPPER}} .zig-card' => '--zig-foot-margin: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_control('category_heading', [
            'label'     => __('دستهٔ محصول', 'zig3d-widgets'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_control('category_color', [
            'label'     => __('رنگ دسته', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .zig-card' => '--zig-category-color: {{VALUE}}; --zig-category-opacity: 1;'],
        ]);

        $this->add_responsive_control('features_radius', [
            'label'      => __('گِردی گوشهٔ نوار ویژگی‌ها', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', 'rem'],
            'range'      => ['px' => ['min' => 0, 'max' => 30]],
            'selectors'  => ['{{WRAPPER}} .zig-card' => '--zig-features-radius: {{SIZE}}{{UNIT}};'],
            'separator'  => 'before',
        ]);

        $this->end_controls_section();

        $this->start_controls_section('sty_stock', [
            'label' => __('لیبل موجودی', 'zig3d-widgets'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $defaults = [
            Stock::IN_STOCK     => ['#0F7B3E', '#E8F6EE'],
            Stock::AVAILABLE    => ['#0F7B3E', '#E8F6EE'],
            Stock::BACKORDER    => ['#8A5A00', '#FDF3E0'],
            Stock::OUT_OF_STOCK => ['#8A1C1C', '#FBEBEB'],
        ];

        foreach ($this->stock_labels() as $state => $label) {
            $this->add_control('stock_color_' . $state, [
                'label'     => sprintf(
                    /* translators: %s: نام حالت موجودی */
                    __('رنگ متن — %s', 'zig3d-widgets'),
                    $label['title']
                ),
                'type'      => Controls_Manager::COLOR,
                'default'   => $defaults[$state][0],
                'selectors' => [
                    '{{WRAPPER}} .zig-card__stock--' . $state => 'color: {{VALUE}};',
                ],
            ]);

            $this->add_control('stock_background_' . $state, [
                'label'     => sprintf(
                    /* translators: %s: نام حالت موجودی */
                    __('پس‌زمینه — %s', 'zig3d-widgets'),
                    $label['title']
                ),
                'type'      => Controls_Manager::COLOR,
                'default'   => $defaults[$state][1],
                'selectors' => [
                    '{{WRAPPER}} .zig-card__stock--' . $state => 'background-color: {{VALUE}};',
                ],
            ]);
        }

        $this->add_responsive_control('stock_radius', [
            'label'      => __('شعاع گوشه', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'default'    => ['size' => 999, 'unit' => 'px'],
            'range'      => ['px' => ['min' => 0, 'max' => 999]],
            'separator'  => 'before',
            'selectors'  => ['{{WRAPPER}} .zig-card__stock' => 'border-radius: {{SIZE}}{{UNIT}};'],
        ]);

        $this->end_controls_section();
    }

    private function section_style_price(): void {
        $this->start_controls_section('sty_price', [
            'label' => __('قیمت و دکمه', 'zig3d-widgets'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_control('price_color', [
            'label'     => __('رنگ عدد', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .zig-card' => '--zig-price-color: {{VALUE}};'],
        ]);

        $this->add_responsive_control('price_size', [
            'label'      => __('اندازهٔ عدد', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', 'rem'],
            'default'    => ['size' => 1.05, 'unit' => 'rem'],
            'range'      => [
                'px'  => ['min' => 10, 'max' => 40],
                'rem' => ['min' => 0.6, 'max' => 2.5, 'step' => 0.05],
            ],
            'selectors'  => ['{{WRAPPER}} .zig-card' => '--zig-price-size: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_control('price_prefix_color', [
            'label'     => __('رنگ پیشوند', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .zig-card' => '--zig-price-prefix-color: {{VALUE}};'],
        ]);

        $this->add_control('price_unit_color', [
            'label'     => __('رنگ واحد پول', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .zig-card' => '--zig-price-unit-color: {{VALUE}};'],
        ]);

        /*
         * «استعلامی» رنگ مستقل دارد چون معنایش هم مستقل است: عدد نیست،
         * یک وضعیت است. یکی‌کردنش با رنگ عدد، دو چیز متفاوت را شبیه هم
         * نشان می‌داد.
         */
        $this->add_control('price_inquiry_color', [
            'label'     => __('رنگ متن استعلامی', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .zig-card' => '--zig-price-inquiry-color: {{VALUE}};'],
        ]);

        $this->add_control('cta_heading_style', [
            'label'     => __('دکمه', 'zig3d-widgets'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->start_controls_tabs('cta_tabs');

        $this->start_controls_tab('cta_normal', ['label' => __('عادی', 'zig3d-widgets')]);

        $this->add_control('cta_color', [
            'label'     => __('رنگ متن', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .zig-card' => '--zig-cta-color: {{VALUE}};'],
        ]);

        /*
         * فلش رنگ مستقل دارد چون در دیزاین هم مستقل است: متنِ دکمه در حالت
         * عادی تیره است و فلش، رنگِ برند. یک کنترلِ مشترک یعنی یا فلش
         * تیره می‌شود یا متن بنفش — هیچ‌کدام آن چیزی نیست که کشیده شده.
         */
        $this->add_control('cta_icon_color', [
            'label'     => __('رنگ فلش', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .zig-card' => '--zig-cta-icon-color: {{VALUE}};'],
        ]);

        $this->add_control('cta_background', [
            'label'     => __('پس‌زمینه', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .zig-card' => '--zig-cta-bg: {{VALUE}};'],
        ]);

        $this->add_control('cta_border', [
            'label'     => __('رنگ کادر', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .zig-card' => '--zig-cta-border: {{VALUE}};'],
        ]);

        $this->end_controls_tab();

        $this->start_controls_tab('cta_hover', ['label' => __('هاور', 'zig3d-widgets')]);

        $this->add_control('cta_color_hover', [
            'label'     => __('رنگ متن', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .zig-card' => '--zig-cta-hover-color: {{VALUE}};'],
        ]);

        $this->add_control('cta_background_hover', [
            'label'     => __('پس‌زمینه', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .zig-card' => '--zig-cta-hover-bg: {{VALUE}};'],
        ]);

        $this->add_control('cta_border_hover', [
            'label'     => __('رنگ کادر', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .zig-card' => '--zig-cta-hover-border: {{VALUE}};'],
        ]);

        $this->end_controls_tab();
        $this->end_controls_tabs();

        $this->add_responsive_control('cta_radius', [
            'label'      => __('شعاع گوشه', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', 'rem'],
            'default'    => ['size' => 12, 'unit' => 'px'],
            'range'      => ['px' => ['min' => 0, 'max' => 48]],
            'separator'  => 'before',
            'selectors'  => ['{{WRAPPER}} .zig-card' => '--zig-cta-radius: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_responsive_control('cta_padding', [
            'label'      => __('فاصلهٔ داخلی', 'zig3d-widgets'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', 'rem'],
            'selectors'  => [
                '{{WRAPPER}} .zig-card' => '--zig-cta-padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->end_controls_section();
    }

    private function section_style_chrome(): void {
        $this->start_controls_section('sty_chrome', [
            'label' => __('فیلتر، ترتیب و صفحه‌بندی', 'zig3d-widgets'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        /* ---------------------------------------------------------------
         * پنل فیلتر
         *
         * هر مقداری که در CSS پایه یک متغیر است، اینجا یک کنترل دارد.
         * سلکتور روی ‎.zig-archive__filters‎ می‌نشیند و متغیر را عوض می‌کند،
         * نه خودِ خاصیت را — یعنی یک کنترل رنگ، هم‌زمان همهٔ جاهایی را که
         * آن رنگ به کار می‌رود می‌گیرد.
         * ------------------------------------------------------------ */

        $this->add_control('filters_panel_heading', [
            'label' => __('پنل فیلتر', 'zig3d-widgets'),
            'type'  => Controls_Manager::HEADING,
        ]);

        $this->add_control('filters_outer_bg', [
            'label'       => __('پس‌زمینهٔ ظرف بیرونی', 'zig3d-widgets'),
            'type'        => Controls_Manager::COLOR,
            'description' => __('حاشیهٔ دور کارت را هم می‌گیرد، پشتِ نوار رنگی.', 'zig3d-widgets'),
            'selectors'   => ['{{WRAPPER}} .zig-archive__filters' => '--zig-filters-outer-bg: {{VALUE}};'],
        ]);

        $this->add_control('filters_bg', [
            'label'     => __('پس‌زمینهٔ پنل', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .zig-archive__filters' => '--zig-filters-bg: {{VALUE}};'],
        ]);

        $this->add_responsive_control('filters_radius', [
            'label'      => __('گِردی گوشهٔ پنل', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', 'rem'],
            'range'      => ['px' => ['min' => 0, 'max' => 40]],
            'selectors'  => ['{{WRAPPER}} .zig-archive__filters' => '--zig-filters-radius: {{SIZE}}{{UNIT}};'],
        ]);

        /*
         * سایه روی ظرفِ بیرونی است، نه کارتِ درون — کارت ‎overflow: hidden‎
         * دارد و سایه‌ای که پشتِ آن مرز بماند بریده می‌شود.
         *
         * گروه‌کنترلِ خودِ المنتور، نه چند اسلایدرِ جدا: سایه یک‌جا روشن یا
         * خاموش می‌شود، و تا وقتی خاموش است، پیش‌فرضِ خودِ شیت
         * (‎--zig-filters-shadow‎) دست‌نخورده می‌ماند. با اسلایدرهای جدا،
         * حالتِ «سایهٔ خالی یعنی هیچ» را باید دوباره از صفر می‌ساختیم —
         * چیزی که این کنترل رایگان می‌دهد.
         */
        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            [
                'name'     => 'filters_shadow',
                'selector' => '{{WRAPPER}} .zig-archive__filters',
            ]
        );

        /*
         * نوار رنگیِ بالای پنل، سه کنترلِ جدا.
         *
         * جداکردنشان لازم بود چون سه چیزِ مستقل‌اند و در دیزاین هم سه عدد
         * جدا دارند: کارت چقدر از بالا پایین آمده (۲۴)، خودِ نوار چقدر
         * بلند است (۴۰)، و کارت از دو طرف چقدر تو رفته (۱۶). با یک عدد،
         * هر تغییری در یکی دو تای دیگر را هم می‌بُرد.
         *
         * صفرکردنِ ارتفاع، نوار را کامل برمی‌دارد.
         */
        $this->add_responsive_control('filters_cap', [
            'label'      => __('فاصلهٔ کارت از بالای پنل', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 120]],
            'selectors'  => ['{{WRAPPER}} .zig-archive__filters' => '--zig-filters-cap: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_responsive_control('filters_cap_height', [
            'label'      => __('ارتفاع نوار رنگی', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 160]],
            'selectors'  => ['{{WRAPPER}} .zig-archive__filters' => '--zig-filters-cap-height: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_responsive_control('filters_inset', [
            'label'      => __('تورفتگی کارت از دو طرف', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 64]],
            'selectors'  => ['{{WRAPPER}} .zig-archive__filters' => '--zig-filters-inset: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_control('filters_cap_bg', [
            'label'     => __('رنگ نوار بالای پنل', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .zig-archive__filters' => '--zig-filters-cap-bg: {{VALUE}};'],
        ]);

        $this->add_responsive_control('filters_outer_radius', [
            'label'      => __('گِردی گوشهٔ ظرف بیرونی', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', 'rem'],
            'range'      => ['px' => ['min' => 0, 'max' => 48]],
            'selectors'  => ['{{WRAPPER}} .zig-archive__filters' => '--zig-filters-outer-radius: {{SIZE}}{{UNIT}};'],
        ]);

        /*
         * سقف ارتفاع پنل، با واحدهای وابسته به نمایشگر.
         *
         * پیش‌فرض ‎calc(100vh - 96px)‎ در CSS نشسته و اینجا فقط اگر مدیر
         * چیزی بگذارد جایش را می‌گیرد. ‎vh‎ در فهرست واحدهاست چون پنلِ
         * چسبنده باید نسبت به *پنجره* سقف بگیرد نه نسبت به محتوا — با ‎px‎
         * ثابت، روی نمایشگر کوتاه باز هم از کادر می‌زند بیرون.
         */
        $this->add_responsive_control('filters_max', [
            'label'      => __('حداکثر ارتفاع پنل (اسکرول)', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', 'vh'],
            'range'      => [
                'px' => ['min' => 200, 'max' => 1600],
                'vh' => ['min' => 30, 'max' => 100],
            ],
            'selectors'  => ['{{WRAPPER}} .zig-archive__filters' => '--zig-filters-max: {{SIZE}}{{UNIT}};'],
        ]);

        /*
         * سربرگ شیشه است: رنگش باید *نیمه‌شفاف* بماند، وگرنه بنفشِ پشتش
         * را می‌پوشاند و بلور دیگر چیزی برای نشان‌دادن ندارد. توضیحِ کنترل
         * همین را می‌گوید تا کسی رنگ توپر نگذارد و بعد دنبال دلیلِ
         * ازبین‌رفتن افکت بگردد.
         */
        $this->add_control('filters_head_bg', [
            'label'       => __('پس‌زمینهٔ سربرگ', 'zig3d-widgets'),
            'type'        => Controls_Manager::COLOR,
            'description' => __('نیمه‌شفاف بگذارید تا رنگِ پشتِ سربرگ از آن بتراود.', 'zig3d-widgets'),
            'selectors'   => ['{{WRAPPER}} .zig-archive__filters' => '--zig-filters-head-bg: {{VALUE}};'],
        ]);

        $this->add_responsive_control('filters_head_blur', [
            'label'      => __('شدت بلورِ سربرگ', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 60]],
            'selectors'  => ['{{WRAPPER}} .zig-archive__filters' => '--zig-filters-head-blur: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_control('filters_head_color', [
            'label'     => __('رنگ متن سربرگ', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .zig-archive__filters' => '--zig-filters-head-color: {{VALUE}};'],
        ]);

        $this->add_control('filters_badge_bg', [
            'label'     => __('پس‌زمینهٔ شمارندهٔ فیلتر فعال', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .zig-archive__filters' => '--zig-filters-badge-bg: {{VALUE}};'],
        ]);

        $this->add_control('filters_badge_color', [
            'label'     => __('رنگ شمارندهٔ فیلتر فعال', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .zig-archive__filters' => '--zig-filters-badge-color: {{VALUE}};'],
        ]);

        $this->add_control('filters_chip_bg', [
            'label'     => __('پس‌زمینهٔ چیپ فیلتر', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .zig-archive__filters' => '--zig-filters-chip-bg: {{VALUE}};'],
        ]);

        $this->add_control('filters_chip_color', [
            'label'     => __('رنگ متن چیپ', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .zig-archive__filters' => '--zig-filters-chip-color: {{VALUE}};'],
        ]);

        $this->add_responsive_control('filters_active_padding', [
            'label'      => __('فاصلهٔ داخلی بخش فیلترهای اعمال‌شده', 'zig3d-widgets'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', 'rem'],
            'selectors'  => [
                '{{WRAPPER}} .zig-archive__filters' => '--zig-filters-active-padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('filters_active_gap', [
            'label'      => __('فاصلهٔ عنوان تا چیپ‌ها', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', 'rem'],
            'range'      => ['px' => ['min' => 0, 'max' => 48]],
            'selectors'  => ['{{WRAPPER}} .zig-archive__filters' => '--zig-filters-active-gap: {{SIZE}}{{UNIT}};'],
        ]);

        /*
         * سقف ارتفاعِ ردیفِ چیپ‌ها، با اسکرولِ خودش.
         *
         * کاربری که هشت فیلتر را با هم زده، هشت چیپ می‌بیند؛ بدون سقف
         * همین یک ردیف به‌اندازهٔ کل بقیهٔ پنل بلند می‌شود. صفرکردنش
         * سقف را برمی‌دارد.
         */
        $this->add_responsive_control('filters_chips_max', [
            'label'      => __('حداکثر ارتفاع چیپ‌ها (اسکرول)', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'default'    => ['size' => 70, 'unit' => 'px'],
            'range'      => ['px' => ['min' => 0, 'max' => 400]],
            'selectors'  => ['{{WRAPPER}} .zig-archive__filters' => '--zig-filters-chips-max: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_control('facet_list_bg', [
            'label'     => __('پس‌زمینهٔ فهرست گزینه‌ها', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .zig-archive__filters' => '--zig-facet-list-bg: {{VALUE}};'],
        ]);

        /*
         * فاصلهٔ بالای تشک تا زیرِ عنوان — جدا از پدینگِ خودِ عنوان.
         */
        $this->add_responsive_control('facet_list_top', [
            'label'      => __('فاصله تا زیرِ عنوان', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', 'rem'],
            'range'      => ['px' => ['min' => 0, 'max' => 40]],
            'selectors'  => ['{{WRAPPER}} .zig-archive__filters' => '--zig-facet-list-top: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_control('facet_item_bg', [
            'label'     => __('پس‌زمینهٔ گزینه', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .zig-archive__filters' => '--zig-facet-item-bg: {{VALUE}};'],
        ]);

        /*
         * سقفِ ارتفاعِ فهرستِ *هر گروه*، جدا از سقفِ کل پنل.
         *
         * دو سقف لازم است چون دو مشکل جدا را حل می‌کنند: این یکی نمی‌گذارد
         * یک گروهِ شصت‌تایی بقیهٔ گروه‌ها را از دید بیندازد، و آن یکی
         * نمی‌گذارد کل پنل از بلندی پنجره بگذرد.
         */
        $this->add_responsive_control('facet_list_max', [
            'label'      => __('حداکثر ارتفاع فهرست هر گروه (اسکرول)', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', 'vh'],
            'range'      => [
                'px' => ['min' => 80, 'max' => 900],
                'vh' => ['min' => 10, 'max' => 80],
            ],
            'selectors'  => ['{{WRAPPER}} .zig-archive__filters' => '--zig-facet-list-max: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_control('scroll_thumb', [
            'label'     => __('رنگ نوار اسکرول', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .zig-archive__filters' => '--zig-scroll-thumb: {{VALUE}};'],
        ]);

        $this->add_control('scroll_thumb_hover', [
            'label'     => __('رنگ نوار اسکرول در هاور', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .zig-archive__filters' => '--zig-scroll-thumb-hover: {{VALUE}};'],
        ]);

        /*
         * دو عدد جدا: کلِ عرضِ نوار، و فاصلهٔ تیغه از لبه‌اش.
         *
         * عرضِ *دیده‌شدهٔ* تیغه تفاضل این دوتاست، ولی ناحیهٔ گرفتنِ ماوس
         * همان عرض کامل می‌ماند. یکی‌کردنشان یعنی یا تیغهٔ باریک با هدفِ
         * کلیکِ باریک، یا هدفِ درشت با تیغهٔ درشت.
         */
        $this->add_responsive_control('scroll_size', [
            'label'      => __('عرض نوار اسکرول', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 4, 'max' => 24]],
            'selectors'  => ['{{WRAPPER}} .zig-archive__filters' => '--zig-scroll-size: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_responsive_control('scroll_pad', [
            'label'      => __('فاصلهٔ تیغه از لبهٔ نوار', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 8]],
            'selectors'  => ['{{WRAPPER}} .zig-archive__filters' => '--zig-scroll-pad: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_control('facet_selected_bg', [
            'label'     => __('پس‌زمینهٔ گزینهٔ انتخاب‌شده', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .zig-archive__filters' => '--zig-facet-selected-bg: {{VALUE}};'],
        ]);

        $this->add_control('facet_selected_border', [
            'label'     => __('کادر گزینهٔ انتخاب‌شده', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .zig-archive__filters' => '--zig-facet-selected-border: {{VALUE}};'],
        ]);

        $this->add_control('facet_box_checked', [
            'label'     => __('رنگ چک‌باکس تیک‌خورده', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .zig-archive__filters' => '--zig-facet-box-checked: {{VALUE}};'],
        ]);

        $this->add_responsive_control('facet_item_radius', [
            'label'      => __('گِردی گوشهٔ گزینه', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', 'rem'],
            'range'      => ['px' => ['min' => 0, 'max' => 24]],
            'selectors'  => ['{{WRAPPER}} .zig-archive__filters' => '--zig-facet-item-radius: {{SIZE}}{{UNIT}};'],
        ]);

        /* --- عنوان گروه --- */

        $this->add_control('facet_title_color', [
            'label'     => __('رنگ عنوان گروه فیلتر', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'separator' => 'before',
            'selectors' => ['{{WRAPPER}} .zig-facet__title' => 'color: {{VALUE}};'],
        ]);

        $this->add_responsive_control('facet_title_padding', [
            'label'      => __('فاصلهٔ داخلی عنوان گروه', 'zig3d-widgets'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', 'rem'],
            'selectors'  => [
                '{{WRAPPER}} .zig-archive__filters' => '--zig-facet-title-padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->add_control('facet_title_bg', [
            'label'     => __('پس‌زمینهٔ عنوان گروه', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .zig-archive__filters' => '--zig-facet-title-bg: {{VALUE}};'],
        ]);

        $this->add_control('facet_title_hover_bg', [
            'label'     => __('پس‌زمینهٔ عنوان در هاور', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .zig-archive__filters' => '--zig-facet-title-hover-bg: {{VALUE}};'],
        ]);

        $this->add_control('facet_title_open_bg', [
            'label'     => __('پس‌زمینهٔ عنوان در حالت باز', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .zig-archive__filters' => '--zig-facet-title-open-bg: {{VALUE}};'],
        ]);

        $this->add_control('facet_title_open_color', [
            'label'     => __('رنگ متن عنوان در حالت باز', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .zig-archive__filters' => '--zig-facet-title-open-color: {{VALUE}};'],
        ]);

        $this->add_control('facet_chevron_color', [
            'label'     => __('رنگ فلش گروه', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .zig-archive__filters' => '--zig-facet-chevron-color: {{VALUE}};'],
        ]);

        $this->add_control('facet_chevron_open_color', [
            'label'     => __('رنگ فلش در حالت باز', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .zig-archive__filters' => '--zig-facet-chevron-open-color: {{VALUE}};'],
        ]);

        /*
         * بولتِ کنار عنوان: نشانهٔ «این گروه فیلتر فعال دارد».
         *
         * صفر کردنِ اندازه برش می‌دارد، بدون اینکه لازم باشد قاعده‌ای
         * لغو شود.
         */
        $this->add_control('facet_bullet_bg', [
            'label'     => __('رنگ بولتِ گروه فعال', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .zig-archive__filters' => '--zig-facet-bullet-bg: {{VALUE}};'],
        ]);

        $this->add_responsive_control('facet_bullet_size', [
            'label'      => __('اندازهٔ بولت', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 20]],
            'selectors'  => ['{{WRAPPER}} .zig-archive__filters' => '--zig-facet-bullet-size: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_control('facet_option_color', [
            'label'     => __('رنگ گزینه', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .zig-facet__item a' => 'color: {{VALUE}};'],
        ]);

        $this->add_control('facet_selected_color', [
            'label'     => __('رنگ گزینهٔ انتخاب‌شده', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .zig-facet__item.is-selected a' => 'color: {{VALUE}};'],
        ]);

        $this->add_control('facet_count_color', [
            'label'     => __('رنگ شمارنده', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .zig-facet__count' => 'color: {{VALUE}};'],
        ]);

        /* --- نوار بالا --- */

        $this->add_control('toolbar_background', [
            'label'     => __('پس‌زمینهٔ نوار بالا', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'separator' => 'before',
            'selectors' => ['{{WRAPPER}} .zig-archive' => '--zig-toolbar-bg: {{VALUE}};'],
        ]);

        $this->add_control('toolbar_border', [
            'label'     => __('رنگ کادر نوار بالا', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .zig-archive' => '--zig-toolbar-border: {{VALUE}};'],
        ]);

        $this->add_responsive_control('toolbar_radius', [
            'label'      => __('گِردی گوشهٔ نوار بالا', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', 'rem'],
            'range'      => ['px' => ['min' => 0, 'max' => 40]],
            'selectors'  => ['{{WRAPPER}} .zig-archive' => '--zig-toolbar-radius: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_responsive_control('toolbar_padding', [
            'label'      => __('فاصلهٔ داخلی نوار بالا', 'zig3d-widgets'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', 'rem'],
            'selectors'  => [
                '{{WRAPPER}} .zig-archive' => '--zig-toolbar-padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->add_control('count_color', [
            'label'     => __('رنگ متن شمارش', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .zig-archive' => '--zig-count-color: {{VALUE}};'],
        ]);

        $this->add_control('sort_color', [
            'label'     => __('رنگ پیل ترتیب', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .zig-archive' => '--zig-sort-color: {{VALUE}};'],
        ]);

        $this->add_control('sort_hover_color', [
            'label'     => __('رنگ پیل در هاور', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .zig-archive' => '--zig-sort-hover-color: {{VALUE}};'],
        ]);

        $this->add_control('sort_hover_background', [
            'label'     => __('پس‌زمینهٔ پیل در هاور', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .zig-archive' => '--zig-sort-hover-bg: {{VALUE}};'],
        ]);

        $this->add_control('sort_active_background', [
            'label'     => __('پس‌زمینهٔ پیل فعال', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .zig-archive' => '--zig-sort-active-bg: {{VALUE}};'],
        ]);

        $this->add_control('sort_active_color', [
            'label'     => __('رنگ متن پیل فعال', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .zig-archive' => '--zig-sort-active-color: {{VALUE}};'],
        ]);

        $this->add_responsive_control('sort_radius', [
            'label'      => __('گِردی گوشهٔ پیل', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 999]],
            'selectors'  => ['{{WRAPPER}} .zig-archive' => '--zig-sort-radius: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_control('page_color', [
            'label'     => __('رنگ شمارهٔ صفحه', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'separator' => 'before',
            'selectors' => ['{{WRAPPER}} .zig-archive' => '--zig-page-color: {{VALUE}};'],
        ]);

        $this->add_control('page_border', [
            'label'     => __('رنگ کادر صفحه', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .zig-archive' => '--zig-page-border: {{VALUE}};'],
        ]);

        $this->add_control('page_current_background', [
            'label'     => __('پس‌زمینهٔ صفحهٔ جاری', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .zig-archive' => '--zig-page-current-bg: {{VALUE}};'],
        ]);

        $this->add_control('page_current_color', [
            'label'     => __('رنگ متن صفحهٔ جاری', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .zig-archive' => '--zig-page-current-color: {{VALUE}};'],
        ]);

        $this->add_responsive_control('page_radius', [
            'label'      => __('گِردی گوشهٔ صفحه', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', 'rem'],
            'range'      => ['px' => ['min' => 0, 'max' => 40]],
            'selectors'  => ['{{WRAPPER}} .zig-archive' => '--zig-page-radius: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_control('error_background', [
            'label'     => __('پس‌زمینهٔ پیام خطا', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'separator' => 'before',
            'selectors' => ['{{WRAPPER}} .zig-archive__error' => 'background-color: {{VALUE}};'],
        ]);

        $this->add_control('error_color', [
            'label'     => __('رنگ متن خطا', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .zig-archive__error' => 'color: {{VALUE}};'],
        ]);

        $this->end_controls_section();
    }

    /**
     * استایلِ چرومِ موبایل — نوارِ قرصیِ فیلتر/ترتیب و دو شیت.
     *
     * همهٔ مقادیر متغیرند و پیش‌فرض‌ها دقیقاً از فیگما (node 263:28018)
     * می‌آیند: پس‌زمینهٔ ‎#FBFAFD‎، رادیوسِ ۴۸، رنگِ برچسبِ فیلتر ‎#5A23B5‎
     * (رنگِ برندِ زیگ) و برچسبِ ترتیب ‎#686673‎. کنترل‌ها فقط زیرِ موبایل
     * اثر دیداری دارند، ولی همیشه ثبت می‌شوند — رندرِ ادیتور برای هر
     * دستگاهی یک‌جور است.
     */
    private function section_style_mobile(): void {
        $this->start_controls_section('sty_mobile', [
            'label' => __('نوارِ موبایل (فیلتر/ترتیب)', 'zig3d-widgets'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $bar = '{{WRAPPER}} .zig-archive__mbar';

        $this->add_control('mbar_bg', [
            'label'     => __('پس‌زمینهٔ نوار', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [$bar => '--zig-mbar-bg: {{VALUE}};'],
        ]);

        $this->add_control('mbar_border', [
            'label'     => __('رنگِ حاشیهٔ نوار', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [$bar => '--zig-mbar-border: {{VALUE}};'],
        ]);

        $this->add_responsive_control('mbar_radius', [
            'label'      => __('گردیِ گوشهٔ نوار', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 60]],
            'selectors'  => [$bar => '--zig-mbar-radius: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_responsive_control('mbar_padding', [
            'label'      => __('فاصلهٔ داخلیِ نوار', 'zig3d-widgets'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px'],
            'selectors'  => [$bar => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
        ]);

        $this->add_control('mbar_filter_color', [
            'label'     => __('رنگِ «فیلتر ها»', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [$bar => '--zig-mbar-filter-color: {{VALUE}};'],
        ]);

        $this->add_control('mbar_sort_color', [
            'label'     => __('رنگِ برچسبِ ترتیب', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [$bar => '--zig-mbar-sort-color: {{VALUE}};'],
        ]);

        $this->add_control('sheet_heading', [
            'label'     => __('شیت‌ها', 'zig3d-widgets'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_control('sheet_bg', [
            'label'     => __('پس‌زمینهٔ شیت', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}}' => '--zig-sheet-bg: {{VALUE}};'],
        ]);

        $this->add_control('sheet_handle_color', [
            'label'     => __('رنگِ دستگیرهٔ کشیدن', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}}' => '--zig-sheet-handle: {{VALUE}};'],
        ]);

        $this->add_control('sheet_accent', [
            'label'       => __('رنگِ برندِ گزینهٔ فعال', 'zig3d-widgets'),
            'type'        => Controls_Manager::COLOR,
            'selectors'   => ['{{WRAPPER}}' => '--zig-sheet-accent: {{VALUE}};'],
            'description' => __('برایِ گزینهٔ ترتیبِ انتخاب‌شده در شیتِ موبایل.', 'zig3d-widgets'),
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * رندر
     * =================================================================== */

    protected function render(): void {
        if (!class_exists('WooCommerce')) {
            return;
        }

        $settings = $this->get_settings_for_display();
        $sorts    = Sorting::sanitize_options((array) ($settings['sorting_options'] ?? []));
        $context = $this->context($settings);

        $base      = $context['base'];
        $facets    = $context['facets'];
        $state     = $context['state'];
        $operators = $context['operators'];
        $query     = $context['query'];

        $this->add_render_attribute('root', [
            'class'                => 'zig-archive zig-archive--filters-' . ($settings['filters_position'] ?? 'start'),
            'data-zig-archive'     => '1',

            /*
             * وضعیت صفحه، همان‌جا که جاوااسکریپت هم آن را می‌گذارد.
             *
             * بعد از یک درخواست آژاکس، ‎state‎ از بدنهٔ پاسخ می‌آید و روی
             * همین صفت می‌نشیند. اگر رندر سرور آن را ننویسد، بارِ اول با
             * هر بارِ بعدی فرق می‌کند: CSSای که به ‎[data-zig-state]‎ وصل
             * است تا اولین کلیک کار نمی‌کند و هیچ‌کس هم متوجه نمی‌شود، چون
             * کلیک اول همیشه سریع می‌آید.
             */
            'data-zig-state'       => $context['page_state'],
            'data-zig-debounce'    => (string) (int) ($settings['filters_debounce'] ?? 250),
            'data-zig-scroll-max'  => (string) (int) ($settings['scroll_pages'] ?? 0),
            'data-zig-restore'     => 'yes' === ($settings['restore_state'] ?? '') ? '1' : '0',
            'data-zig-page'        => (string) $state->page(),
            'data-zig-pages'       => (string) max(1, (int) $query->max_num_pages),
            'data-zig-endpoint'    => Archive_Endpoint::url(),
            'data-zig-nonce'       => Archive_Endpoint::nonce(),
            /*
             * شناسهٔ *سند* المنتور، نه پستِ داخل حلقه.
             *
             * این را نصب واقعی نشان داد: روی آرشیو دسته، ‎get_the_ID()‎
             * شناسهٔ اولین محصولِ حلقه را می‌دهد. نقطهٔ آژاکس با آن دنبال
             * ‎_elementor_data‎ می‌گردد، پیدا نمی‌کند و ‎400‎ می‌دهد — یعنی
             * هر کلیک فیلتر آلرت «تلاش مجدد» می‌گیرد، روی صفحه‌ای که در
             * نگاه اول کاملاً سالم رندر شده.
             */
            'data-zig-post'        => (string) $this->document_id(),
            'data-zig-term'        => (string) ($this->queried_term()->term_id ?? 0),
            'data-zig-widget'      => (string) $this->get_id(),
        ]);

        echo '<div ' . $this->get_render_attribute_string('root') . '>';

        $has_sidebar = $this->has_sidebar($settings, $facets);
        $show_sorts  = 'yes' === ($settings['sorting_on'] ?? '') && $sorts;

        /*
         * شناسه‌هایِ یکتا برایِ ‎aria-controls‎: دکمه‌هایِ نوارِ موبایل باید
         * به شیتِ متناظرشان اشاره کنند، و در یک صفحه می‌شود چند آرشیو بود.
         */
        $filters_id = 'zig-archive-filters-' . $this->get_id();
        $sort_id    = 'zig-archive-sort-' . $this->get_id();

        if ($has_sidebar) {
            /*
             * دستگیرهٔ کشیدن، خواهرِ اسلاتِ ‎facets‎ است نه فرزندش —
             * ‎swap('facets')‎ محتوایِ ‎[data-zig-part=facets]‎ را با
             * ‎innerHTML‎ عوض می‌کند و اگر دستگیره داخلش بود، بعدِ اولین
             * فیلتر پاک می‌شد. پس اسلات به یک ‎<div>‎ی درونی منتقل شده و
             * دستگیره کنارش می‌ماند.
             */
            printf(
                '<aside id="%s" class="zig-archive__filters" aria-label="%s">',
                esc_attr($filters_id),
                esc_attr($settings['filters_title'] ?? '')
            );
            echo '<span class="zig-archive__sheet-handle" aria-hidden="true"></span>';
            echo '<div class="zig-archive__facets" data-zig-part="facets">' . $this->fragment('facets', $context) . '</div>';
            echo '</aside>';
        }

        echo '<div class="zig-archive__main">';

        $this->render_mobile_bar($settings, $sorts, $state, $has_sidebar, (bool) $show_sorts, $filters_id, $sort_id);

        $this->render_toolbar($settings, $sorts, $state, (int) $query->found_posts, $operators);

        echo '<div data-zig-part="grid">' . $this->fragment('grid', $context) . '</div>';
        echo '<div data-zig-part="pagination">' . $this->fragment('pagination', $context) . '</div>';

        $this->render_error($settings);

        echo '</div>';

        if ($show_sorts) {
            $this->render_sort_sheet($settings, $sorts, $state, $sort_id, $operators);
        }

        if ($has_sidebar || $show_sorts) {
            // لایهٔ تیرهٔ مشترکِ هر دو شیت — فقط در موبایل دیده می‌شود.
            echo '<div class="zig-archive__sheet-backdrop" data-zig-sheet-backdrop hidden></div>';
        }

        echo '</div>';

        wp_reset_postdata();
    }

    /* =====================================================================
     * قطعه‌ها
     *
     * رندر سرور و پاسخ آژاکس از یک مسیر می‌آیند. اگر دو مسیر می‌بودند،
     * اختلافشان همان‌جایی ظاهر می‌شد که کسی نگاه نمی‌کند: بارِ اول درست، و
     * بعد از اولین کلیک یک کلاس کم، یک ‎aria-current‎ جامانده، یک شمارشِ
     * قدیمی.
     * =================================================================== */

    /**
     * همه‌چیزِ لازم برای یک بار رندر.
     *
     * ‎$_GET‎ فقط اینجا خوانده می‌شود — یا در مسیر آژاکس، از رشتهٔ پرس‌وجویی
     * که کلاینت فرستاده. بقیهٔ متدها هرچه لازم دارند را از همین آرایه
     * می‌گیرند.
     *
     * @param array|null $params جایگزین ‎$_GET‎، برای درخواست آژاکس
     */
    public function context(array $settings, ?array $params = null, int $term_id = 0): array {
        /*
         * دستهٔ فرستاده‌شده یک *راهنمایی* است، نه دستور.
         *
         * تاکسونومی از ثابت می‌آید نه از درخواست، و ‎get_term()‎ با
         * تاکسونومیِ صریح، ترمی را که مالِ آن تاکسونومی نیست ‎WP_Error‎
         * می‌کند — پس هم وجود و هم عضویت سنجیده می‌شوند.
         *
         * و وقتی منبع دستی است، اصلاً پذیرفته نمی‌شود: آنجا دامنه را
         * تنظیمات تعیین می‌کند و قبول‌کردن دستهٔ کلاینت یعنی سایدبار و
         * آدرس پایه به دسته‌ای اشاره کنند که گرید نشانش نمی‌دهد.
         */
        if ($term_id > 0 && 'custom' !== ($settings['source'] ?? 'archive')) {
            $term = get_term($term_id, Schema_Store::TAXONOMY);

            // ترمی که وجود ندارد یعنی «کل فروشگاه»، نه خطا: آدرس کهنه
            // نباید درخواست را بشکند، فقط دامنه‌اش بازتر می‌شود.
            $this->term = $term instanceof \WP_Term ? $term : null;
        }

        $sorts    = Sorting::sanitize_options((array) ($settings['sorting_options'] ?? []));
        $base     = Archive_Query::base_args($this->scope($settings));
        $key      = Archive_Query::context_key($base);
        $facets   = $this->facets();
        $state    = $this->state($facets, $sorts, $params);
        $sort     = Sorting::resolve($sorts, $state->sort(), isset($base['s']));
        $per_page = $this->per_page($settings, $params);

        /*
         * اپراتور هر گروه از یک جا می‌آید و از همان‌جا هم به کوئری، هم به
         * لینک‌ها، هم به شمارش‌ها می‌رود. اگر لینک‌ها آن را نگیرند — که
         * تا همین امروز نمی‌گرفتند — لینکِ «مرتب‌سازی» روی گروهی که مدیر
         * ‎AND‎ گذاشته، ‎query_type=or‎ می‌نویسد و معنای فیلتر را وسط
         * کلیک عوض می‌کند.
         */
        $operators = Archive_Query::honored($facets);

        $query = Archive_Query::run($base, $state, $operators, $sort, $per_page);

        /*
         * وردپرس متا و ترم‌های یک کوئری را یک‌جا می‌خواند ولی پستِ تصویر
         * شاخص را نه؛ بدون این، هر کارت یک کوئری جدا برای اتچمنتش می‌زند.
         */
        update_post_thumbnail_cache($query);

        return [
            'settings'   => $settings,
            'sorts'      => $sorts,
            'base'       => $base,
            'key'        => $key,
            'facets'     => $facets,
            'state'      => $state,
            'operators'  => $operators,
            'query'      => $query,
            'base_url'   => $this->base_url(),
            'page_state' => $this->page_state($settings, $query, $state, $params),
        ];
    }

    /**
     * یک قطعه، به‌صورت رشته.
     *
     * بافر خروجی چون رندرکننده‌های داخلی ‎echo‎ می‌کنند و بازنویسی همه‌شان
     * به «رشته برگردان» یعنی دو نسخه از یک منطق تا وقتی که یکی‌شان عقب
     * بماند.
     */
    public function fragment(string $name, array $ctx): string {
        $settings = $ctx['settings'];

        ob_start();

        switch ($name) {
            case 'facets':
                $this->render_facets($settings, $ctx['facets'], $ctx['state'], $ctx['base'], $ctx['key'], $ctx['operators']);
                break;

            case 'grid':
                $this->render_grid($settings, $ctx['query'], $ctx['state'], $ctx['operators']);
                break;

            case 'pagination':
                $this->render_pagination($settings, $ctx['state'], (int) $ctx['query']->max_num_pages, $ctx['operators']);
                break;

            case 'count':
                $this->render_count($settings, (int) $ctx['query']->found_posts);
                break;

            case 'sorts':
                $this->render_sorts($ctx['sorts'], $ctx['state'], $ctx['operators']);
                break;
        }

        return (string) ob_get_clean();
    }

    /** آیا سایدبار اصلاً رندر می‌شود */
    public function has_sidebar(array $settings, array $facets): bool {
        return 'yes' === ($settings['filters_on'] ?? '') && [] !== $facets;
    }

    /* ---------------------------------------------------------------- */

    /**
     * دامنهٔ کوئری.
     *
     * روی یک آرشیو واقعی، دسته از خودِ صفحه می‌آید نه از تنظیمات: ویجتی که
     * روی قالب دستهٔ محصول نشسته باید همان دسته را نشان بدهد، وگرنه یک
     * قالب برای همهٔ دسته‌ها بی‌معنا می‌شود.
     */
    /**
     * دسته‌ای که این ویجت رویش نشسته.
     *
     * روی رندر سرور همان ‎get_queried_object()‎ است. روی درخواست آژاکس
     * چیزی برای پرسیدن نیست — ‎admin-ajax.php‎ نه دسته‌ای دارد نه شرطی‌ای —
     * پس دسته صریح داده می‌شود.
     *
     * سه جا به آن نیاز دارند (دامنهٔ کوئری، طرح فیلتر، آدرس پایه) و همین
     * دلیل وجود این متد است: اگر هرکدام جدا ‎get_queried_object()‎ صدا
     * می‌زد، مسیر آژاکس باید سه بار جداگانه وصله می‌شد و اولین جایی که
     * فراموش می‌شد، بی‌صدا کل فروشگاه را به‌جای یک دسته نشان می‌داد.
     */
    private ?\WP_Term $term = null;

    /**
     * سندی که این ویجت در آن ذخیره شده.
     *
     * ‎get_the_ID()‎ فقط وقتی درست است که ویجت روی یک برگهٔ معمولی باشد.
     * روی قالبِ آرشیو — که جای اصلی این ویجت است — پستِ جاری یکی از
     * محصولات حلقه است و سند جای دیگری است.
     */
    /**
     * تعداد در هر صفحه — و روی آرشیو واقعی، مالِ ووکامرس.
     *
     * این را مرورگر یاد داد و از جنس همان واگرایی همیشگی است. ویجت با
     * ۹ تا در صفحه، صفحه‌بندیِ چهارصفحه‌ای می‌ساخت؛ ولی *سند* را کوئری
     * اصلی صفحه‌بندی می‌کند و آن ۱۰ تایی بود، یعنی سه صفحه. لینکِ «صفحهٔ
     * ۴»ی که خودِ ویجت رندر کرده بود، به آدرسی می‌رفت که وردپرس ۴۰۴
     * می‌داد — بدون جاوااسکریپت مستقیم، و با جاوااسکریپت سرِ اولین رفرش.
     *
     * دو راه بود: یا کوئری اصلی را با ویجت هماهنگ کنیم، یا برعکس. دومی
     * انتخاب شد چون «اندازهٔ صفحهٔ آرشیو» ذاتاً خاصیت *آرشیو* است نه یک
     * ویجت روی آن، و ووکامرس همان را با ‎loop_shop_per_page‎ در اختیار
     * مدیر گذاشته. اولی هم شدنی بود ولی باید پیش از ‎pre_get_posts‎
     * می‌دانستیم ویجت چه تنظیمی دارد — یعنی رزولوشنِ زودهنگامِ قالب
     * المنتور، که هزینه و شکنندگی‌اش از خودِ مسئله بیشتر است.
     *
     * تنظیم ویجت روی منبع دستی سر جایش می‌ماند؛ آنجا آرشیوی در کار نیست
     * که با آن بجنگد. مسیر آژاکس هم همان عدد سند را می‌گیرد، وگرنه صفحهٔ
     * دوم دو معنای متفاوت پیدا می‌کرد.
     */
    private function per_page(array $settings, ?array $params = null): int {
        $setting = Archive_Query::per_page((int) ($settings['per_page'] ?? 9));

        if ('custom' === ($settings['source'] ?? 'archive')) {
            return $setting;
        }

        /*
         * از خودِ کوئری اصلی، نه از ‎loop_shop_per_page‎.
         *
         * آن فیلتر را ووکامرس فقط *در جریان* حلقهٔ محصولات می‌بندد؛ صدا
         * زدنش بیرون از آن زمینه، مقدار خام گزینهٔ وردپرس را می‌دهد که
         * چیز دیگری است. تنها عددی که قطعاً درست است، همانی است که سند
         * واقعاً با آن صفحه‌بندی شده.
         */
        if (function_exists('is_product_taxonomy') && (is_shop() || is_product_taxonomy())) {
            global $wp_query;

            $archive = isset($wp_query) ? (int) $wp_query->get('posts_per_page') : 0;

            if ($archive > 0) {
                return Archive_Query::per_page($archive);
            }
        }

        /*
         * روی آژاکس، کوئری اصلی وجود ندارد. پس همان فرمولی بازسازی می‌شود
         * که ووکامرس خودش برای پیش‌فرض به کار می‌برد، از داخل همان فیلتر —
         * تا قالبی که عدد را عوض کرده، اینجا هم دیده شود.
         */
        if (null !== $params && function_exists('wc_get_default_products_per_row')) {
            $archive = (int) apply_filters(
                'loop_shop_per_page',
                wc_get_default_products_per_row() * wc_get_default_product_rows_per_page()
            );

            if ($archive > 0) {
                return Archive_Query::per_page($archive);
            }
        }

        return $setting;
    }

    private function document_id(): int {
        if (class_exists('\Elementor\Plugin')) {
            $document = \Elementor\Plugin::$instance->documents->get_current();

            if ($document) {
                return (int) $document->get_main_id();
            }
        }

        return (int) get_the_ID();
    }

    private function queried_term(): ?\WP_Term {
        if ($this->term instanceof \WP_Term) {
            return $this->term;
        }

        $term = get_queried_object();

        return $term instanceof \WP_Term ? $term : null;
    }

    private function scope(array $settings): array {
        if ('custom' === ($settings['source'] ?? 'archive')) {
            return ['categories' => (array) ($settings['categories'] ?? [])];
        }

        $term = $this->queried_term();

        return [
            'categories' => $term instanceof \WP_Term ? [(int) $term->term_id] : [],
            'search'     => is_search() ? (string) get_search_query() : '',
        ];
    }

    /** گروه‌های فیلتر این دسته، از طرحِ خودِ دسته */
    private function facets(): array {
        $term = $this->queried_term();

        if (!$term instanceof \WP_Term || Schema_Store::TAXONOMY !== $term->taxonomy) {
            return [];
        }

        return Schema_Store::for_term((int) $term->term_id);
    }

    /**
     * وضعیت صفحه — همان چیزی که کد HTTP از آن آمده.
     *
     * روی آرشیو واقعی از ‎Archive_Head‎ خوانده می‌شود، نه دوباره حساب.
     * دلیلش دقت نیست، *یکی‌بودن* است: ‎Archive_Head‎ روی ‎template_redirect‎
     * تصمیم گرفته و شاید ‎404‎ فرستاده باشد؛ اگر ویجت مستقل حساب کند، کافی
     * است یکی از ورودی‌ها کمی فرق کند تا هدر بگوید «وجود ندارد» و DOM
     * بگوید ‎ok‎.
     *
     * ولی وقتی منبع دستی است، آن تصمیم اصلاً به این ویجت ربطی ندارد:
     * ‎Archive_Head‎ کوئری *اصلی* صفحه را شمرده و این ویجت دسته‌های دیگری
     * را نشان می‌دهد. آنجا وضعیت از کوئری خودمان می‌آید — و ‎invalid‎ هم
     * نمی‌گیرد، چون ادعای آدرس مالِ صفحه است نه مالِ ویجتی که رویش نشسته.
     */
    private function page_state(array $settings, \WP_Query $query, Query_State $state, ?array $params = null): string {
        /*
         * روی رندر سرور، همان تصمیمی که کد HTTP از آن آمده. روی آژاکس
         * ‎null‎ است — آنجا درخواستی به ‎template_redirect‎ نرسیده — و
         * وضعیت از همان قواعد، ولی روی پارامترهای فرستاده‌شده، دوباره
         * حساب می‌شود.
         */
        if (null === $params && 'custom' !== ($settings['source'] ?? 'archive')) {
            $shared = Archive_Head::page_state();

            if (null !== $shared) {
                return $shared;
            }
        }

        $honored = Archive_Query::honored_taxonomies($this->facets());

        $invalid = null !== $params && [] !== array_merge(
            Query_State::unknown_filters($params, $honored),
            Query_State::duplicate_filters($params),
            Query_State::oversized_filters($params, $honored)
        );

        return Seo::state($state, (int) $query->found_posts, (int) $query->max_num_pages, $invalid);
    }

    /**
     * وضعیت، از روی آدرس.
     *
     * فهرست مجاز از ‎Archive_Query::honored_taxonomies()‎ می‌آید، نه فقط از
     * طرحِ فیلترِ این دسته. تفاوتش مهم است: ویژگی‌ای که در سایدبار نیست ولی
     * در فروشگاه ثبت شده، هنوز روی کوئری *اصلی* اعمال می‌شود چون ووکامرس
     * خودش اعمالش می‌کند. اگر ویجت نادیده‌اش بگیرد، شمارشی که ‎Archive_Head‎
     * از کوئری اصلی خوانده با گریدی که ویجت نشان می‌دهد فرق می‌کند — و آن
     * اختلاف می‌تواند یعنی سرور ‎404‎ بفرستد و گرید محصول نشان بدهد.
     *
     * سایدبار همچنان فقط طرح را نشان می‌دهد؛ «چه چیزی نمایش داده شود» و
     * «آدرس چه چیزی را ادعا می‌کند» دو سؤال جدا هستند.
     */
    private function state(array $facets, array $sorts, ?array $params = null): Query_State {
        if (null === $params) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $params = is_array($_GET) ? wp_unslash($_GET) : [];
        }

        return Query_State::from_request(
            $params,
            Archive_Query::honored_taxonomies($facets),
            Sorting::keys($sorts)
        );
    }

    /** آدرس پایه، بدون هیچ پارامتری */
    private function base_url(): string {
        $term = $this->queried_term();

        if ($term instanceof \WP_Term) {
            $link = get_term_link($term);

            if (is_string($link)) {
                return $link;
            }
        }

        return function_exists('wc_get_page_permalink') ? (string) wc_get_page_permalink('shop') : home_url('/');
    }

    /* ---------------------------------------------------------------- */

    /**
     * درونهٔ سایدبار — بدون خودِ ‎<aside>‎.
     *
     * ظرف در ‎render()‎ می‌ماند و فقط محتوایش عوض می‌شود. اگر ظرف هم جزو
     * قطعه بود، هر بار جایگزین می‌شد و فوکوسِ کاربری که همین حالا روی یک
     * چک‌باکس بود می‌پرید به ‎<body>‎ — بدترین اتفاقی که برای کاربر کیبورد
     * وسط فیلترکردن می‌افتد.
     */
    private function render_facets(array $settings, array $facets, Query_State $state, array $base, string $context, array $operators = []): void {
        $url    = $this->base_url();
        $active = $state->count();

        /*
         * یک ظرفِ سفید داخلِ ظرفِ رنگی.
         *
         * در دیزاین، بالای پنل یک نوار رنگِ برند از زیرِ کارت بیرون زده.
         * می‌شد آن را با ‎::before‎ کشید، ولی آن‌وقت ارتفاعش به یک عدد
         * جادویی در CSS گره می‌خورد و کنترل المنتور نمی‌توانست تمیز
         * عوضش کند. با دو ظرف، نوار همان ‎padding-block-start‎ ظرف بیرونی
         * است — یک متغیر، بدون عنصر شبح.
         */
        echo '<div class="zig-filters__card">';

        /*
         * سربرگ پنل.
         *
         * شمارندهٔ «چند فیلتر فعال» فقط تزئین نیست: وقتی گروه‌ها بسته
         * باشند، تنها نشانهٔ اینکه اصلاً فیلتری در کار است همین عدد است.
         */
        echo '<div class="zig-filters__head">';

        printf(
            '<h2 class="zig-filters__title">%s%s</h2>',
            Markup::svg_icon('filter', 'zig-filters__icon'),
            esc_html($settings['filters_title'] ?? '')
        );

        if ($active > 0) {
            printf(
                '<span class="zig-filters__badge">%s</span>',
                esc_html(str_replace('{count}', Price::persian((string) $active), (string) ($settings['filters_active_text'] ?? '')))
            );
        }

        echo '</div>';

        $this->render_active($settings, $facets, $state, $operators, $url);

        echo '<div class="zig-filters__groups">';

        foreach ($facets as $facet) {
            $this->render_facet($facet, $state, $base, $context, $operators, $url, $settings);
        }

        echo '</div></div>';
    }

    /**
     * فیلترهای اعمال‌شده، به‌صورت چیپ.
     *
     * *همهٔ* فیلترهای فعال می‌آیند، نه فقط گروه‌های باز — و همین نکته‌اش
     * است. کاربری که گروه «برند» را بسته و بعد پایین صفحه رفته، هیچ راهی
     * ندارد بفهمد چرا نتیجه‌ها کم‌اند مگر اینکه هر گروه را باز کند. این
     * ردیف، تمام قیدهای فعال را یک‌جا نشان می‌دهد و هرکدام را جدا
     * برمی‌دارد.
     *
     * برچسب هر چیپ از ترم می‌آید نه از اسلاگ: «۵ محور»، نه «5-axis».
     */
    private function render_active(array $settings, array $facets, Query_State $state, array $operators, string $url): void {
        if (!$state->is_filtered()) {
            return;
        }

        $chips = [];

        foreach ($facets as $facet) {
            $taxonomy = (string) $facet['taxonomy'];
            $selected = $state->selected($taxonomy);

            if (!$selected) {
                continue;
            }

            $labels = [];

            foreach (Attributes::terms($taxonomy) as $term) {
                $labels[(string) $term['slug']] = (string) $term['label'];
            }

            foreach ($selected as $slug) {
                $chips[] = [
                    'taxonomy' => $taxonomy,
                    'slug'     => (string) $slug,
                    // اسلاگِ بی‌ترم هم چیپ می‌گیرد، وگرنه قیدی می‌ماند که
                    // کاربر می‌بیندش ولی نمی‌تواند برش دارد
                    'label'    => $labels[(string) $slug] ?? (string) $slug,
                ];
            }
        }

        if (!$chips) {
            return;
        }

        echo '<div class="zig-filters__active">';
        echo '<div class="zig-filters__active-head">';

        printf('<h3 class="zig-filters__active-title">%s</h3>', esc_html($settings['filters_active_title'] ?? ''));

        printf(
            '<a class="zig-filters__clear" href="%s" data-zig-clear="1">%s%s</a>',
            esc_url(Seo::url($url, $state->cleared(), $operators)),
            esc_html($settings['filters_clear'] ?? ''),
            Markup::svg_icon('trash', 'zig-filters__clear-icon')
        );

        echo '</div><ul class="zig-filters__chips">';

        foreach ($chips as $chip) {
            printf(
                '<li class="zig-filters__chip"><a href="%1$s" rel="nofollow" data-zig-toggle="%2$s|%3$s">'
                    . '<span class="zig-filters__chip-text">%4$s</span>%5$s'
                    . '<span class="zig-sr">%6$s</span></a></li>',
                esc_url(Seo::url($url, $state->toggle($chip['taxonomy'], $chip['slug']), $operators)),
                esc_attr(Query_State::param_for($chip['taxonomy'])),
                esc_attr($chip['slug']),
                esc_html($chip['label']),
                Markup::svg_icon('trash', 'zig-filters__chip-icon'),
                esc_html__('— حذف این فیلتر', 'zig3d-widgets')
            );
        }

        echo '</ul></div>';
    }

    private function render_facet(array $facet, Query_State $state, array $base, string $context, array $operators, string $url, array $settings = []): void {
        $taxonomy = $facet['taxonomy'];

        $options = Facets::options(
            Attributes::terms($taxonomy),
            Attributes::counts($base, $state, $taxonomy, $operators, $context, $facet['semantics']),
            $state->selected($taxonomy)
        );

        /*
         * ترتیب این دو مهم است و برعکسش باگ می‌سازد.
         *
         * دیدپذیریِ *گروه* از روی فهرست کامل تصمیم گرفته می‌شود، نه از روی
         * فهرستِ فیلترشده. اگر اول گزینه‌های صفر را می‌انداختیم، هر گروهی که
         * همه‌اش صفر بود فهرستِ خالی می‌داد و ‎group_is_visible()‎ حتی
         * پینِ مدیر («حتی اگر خالی بود نشان بده») را هم نمی‌دید.
         */
        if (!Facets::group_is_visible($options, $facet['show_empty'])) {
            return;
        }

        $options = Facets::visible_options(
            $options,
            'yes' === ($settings['show_empty_options'] ?? 'yes')
        );

        if (!$options) {
            return;
        }

        /*
         * گروهی که انتخابی دارد باز می‌ماند، بقیه بسته.
         *
         * با پنج گروهِ همیشه‌باز، سایدبار چند برابر ارتفاع صفحه می‌شود و
         * کاربر باید تا انتها اسکرول کند تا ببیند چه چیزهای دیگری هست.
         * ولی گروهی که کاربر در آن انتخابی کرده باید باز بماند، وگرنه
         * انتخابش را از دست‌رفته می‌بیند.
         */
        /*
         * ‎is-active‎ جدا از ‎open‎ است و باید هم باشد.
         *
         * ‎open‎ می‌گوید گروه *الان* باز است — و کاربر می‌تواند هر گروهی
         * را باز کند. ‎is-active‎ می‌گوید این گروه انتخابی دارد، که چیز
         * دیگری است و بعد از بستنِ گروه هم درست می‌ماند. بولتِ کنار
         * عنوان از این یکی می‌آید، وگرنه با بستنِ گروه غیب می‌شد —
         * دقیقاً همان لحظه‌ای که تنها نشانهٔ «اینجا فیلتری فعال است»
         * همان بولت است.
         */
        $selected = $state->selected($taxonomy);

        printf(
            '<details class="zig-facet%1$s"%2$s><summary class="zig-facet__title">'
                . '<span class="zig-facet__name">%3$s</span>%4$s</summary><ul class="zig-facet__list">',
            $selected ? ' is-active' : '',
            $selected ? ' open' : '',
            esc_html(Attributes::label($taxonomy)),
            Markup::svg_icon('chevron', 'zig-facet__chevron')
        );

        foreach ($options as $option) {
            $this->render_option($taxonomy, $option, $state, $operators, $url);
        }

        echo '</ul></details>';
    }

    /**
     * یک گزینهٔ فیلتر.
     *
     * ‎<a href>‎ واقعی است، نه چک‌باکس تنها: بدون جاوااسکریپت هم باید کار
     * کند و خزنده هم باید بتواند دنبالش برود.
     *
     * ولی همین‌جا یک مرز هست که راحت رد می‌شود: چون *ظاهرش* چک‌باکس است،
     * وسوسه می‌شود ‎role="checkbox"‎ یا ‎aria-pressed‎ بگیرد. هیچ‌کدام درست
     * نیست. این عنصر از نظر رفتاری یک پیوند ناوبری است — فعال‌کردنش صفحه
     * را عوض می‌کند، نه یک وضعیت را. و ‎aria-pressed‎ اصلاً روی ‎link‎
     * پشتیبانی نمی‌شود؛ می‌ماند به‌عنوان صفتی که یا نادیده گرفته می‌شود یا
     * بدتر، وضعیتی را اعلام می‌کند که رفتار عنصر تأییدش نمی‌کند.
     *
     * پس وضعیت از راه *نام دسترس‌پذیر* گفته می‌شود: متن پنهانی که می‌گوید
     * فعال‌کردن این پیوند چه می‌کند. برای گزینهٔ انتخاب‌شده «حذف فیلتر»
     * است، نه «انتخاب‌شده» — چون کاری که انجام می‌شود همان است، و صفحه‌خوان
     * باید عمل را بگوید نه فقط حالت را.
     *
     * چک‌باکس تصویری ‎aria-hidden‎ می‌ماند تا همان حرف دو بار زده نشود.
     *
     * گزینهٔ صفرِ انتخاب‌نشده لینک نمی‌شود ولی حذف هم نمی‌شود — ارتفاع
     * سایدبار نباید با هر درخواست بپرد.
     */
    private function render_option(string $taxonomy, array $option, Query_State $state, array $operators, string $url): void {
        $count = null === $option['count'] ? '' : sprintf(
            '<span class="zig-facet__count">%s</span>',
            esc_html(Price::persian((string) $option['count']))
        );

        $label = esc_html($option['label']) . $count;

        if ($option['disabled']) {
            printf(
                '<li class="zig-facet__item is-disabled"><span aria-disabled="true">%s</span></li>',
                $label
            );

            return;
        }

        $action = sprintf(
            '<span class="zig-sr">%s</span>',
            esc_html(
                $option['selected']
                    ? __('— حذف این فیلتر', 'zig3d-widgets')
                    : __('— افزودن این فیلتر', 'zig3d-widgets')
            )
        );

        /*
         * ‎data-zig-toggle‎ کنار ‎href‎، و این تکرار نیست.
         *
         * ‎href‎ عکسِ لحظه‌ای از وضعیت *همین رندر* است. اگر کاربر دو فیلتر
         * را سریع پشت سر هم بزند، لینک دوم هنوز اولی را نمی‌شناسد — چون
         * وقتی رندر شد، اولی هنوز کلیک نشده بود. پیمایش واقعی این مشکل را
         * ندارد (هر کلیک یک رفت‌وبرگشت کامل است) ولی آژاکس دارد.
         *
         * پس جاوااسکریپت به‌جای دنبال‌کردن ‎href‎، همین دلتا را روی وضعیتی
         * که خودش نگه داشته اعمال می‌کند. بدون JS، ‎href‎ همان کار همیشگی
         * را می‌کند.
         */
        printf(
            '<li class="zig-facet__item%1$s"><a href="%2$s" rel="nofollow" data-zig-toggle="%5$s|%6$s">'
                . '<span class="zig-facet__box" aria-hidden="true"></span>%3$s%4$s</a></li>',
            $option['selected'] ? ' is-selected' : '',
            esc_url(Seo::url($url, $state->toggle($taxonomy, $option['slug']), $operators)),
            $label,
            $action,
            esc_attr(Query_State::param_for($taxonomy)),
            esc_attr($option['slug'])
        );
    }

    /* ---------------------------------------------------------------- */

    private function render_toolbar(array $settings, array $sorts, Query_State $state, int $found, array $operators = []): void {
        if ('yes' !== ($settings['sorting_on'] ?? '') && 'yes' !== ($settings['count_on'] ?? '')) {
            return;
        }

        echo '<div class="zig-archive__toolbar">';

        if ('yes' === ($settings['sorting_on'] ?? '') && $sorts) {
            /*
             * نوار ترتیب هم یک قطعه است، و این را مرورگر یاد داد: بدون
             * آن، کاربر روی «ارزان‌ترین» کلیک می‌کرد، گرید درست مرتب
             * می‌شد، ولی پیلِ فعال و ‎aria-current‎ روی گزینهٔ قبلی
             * می‌ماندند — یعنی صفحه می‌گفت هنوز «جدیدترین» فعال است.
             */
            echo '<div data-zig-part="sorts">';
            $this->render_sorts($sorts, $state, $operators);
            echo '</div>';
        }

        /*
         * ترتیب اول، شمارش آخر — همان چیدمانی که در دیزاین آمده.
         *
         * ترتیب DOM هم همین را می‌خواهد و اتفاقی نیست: «چطور مرتب کنم» یک
         * کنترل است و «چندتا شد» نتیجهٔ آن. کاربر صفحه‌خوان اول ابزار را
         * می‌شنود و بعد خروجی‌اش را، نه برعکس.
         *
         * جای *دیداری*‌شان به ‎space-between‎ سپرده شده، پس در RTL و LTR
         * هر کدام سرِ خودش می‌نشیند بدون قاعدهٔ جهت‌دار.
         */
        if ('yes' === ($settings['count_on'] ?? '')) {
            echo '<div data-zig-part="count">';
            $this->render_count($settings, $found);
            echo '</div>';
        }

        echo '</div>';
    }

    /**
     * شمارش نتیجه‌ها.
     *
     * ‎aria-live="polite"‎ چون بعد از هر فیلتر عوض می‌شود و کاربر
     * اسکرین‌ریدر وگرنه هیچ نشانه‌ای از اینکه چیزی تغییر کرده نمی‌گیرد.
     *
     * ‎polite‎ و نه ‎assertive‎: این یک نتیجه است، نه یک بن‌بست؛ نباید
     * وسط جملهٔ در حال خواندن بپرد. آلرت خطا آن یکی است.
     */
    private function render_count(array $settings, int $found): void {
        printf(
            '<p class="zig-archive__count" aria-live="polite">%s</p>',
            esc_html(str_replace(
                '{count}',
                Price::persian((string) $found),
                (string) ($settings['count_text'] ?? '')
            ))
        );
    }

    private function render_sorts(array $sorts, Query_State $state, array $operators = []): void {
        $url     = $this->base_url();
        $current = Sorting::resolve($sorts, $state->sort());

        echo '<div class="zig-sorts__wrap">';

        /*
         * برچسب «ترتیب :» یک ‎<span>‎ است نه ‎<label>‎.
         *
         * ‎<label>‎ باید به یک کنترل فرم اشاره کند و اینجا هیچ کنترلی نیست،
         * چند لینک است. برچسبِ بی‌مقصد در بعضی صفحه‌خوان‌ها اصلاً خوانده
         * نمی‌شود؛ نامِ دسترس‌پذیرِ خودِ فهرست کار را می‌کند.
         */
        printf(
            '<span class="zig-sorts__label" aria-hidden="true">%s%s</span>',
            Markup::svg_icon('sort', 'zig-sorts__icon'),
            esc_html__('ترتیب :', 'zig3d-widgets')
        );

        printf('<ul class="zig-sorts" aria-label="%s">', esc_attr__('ترتیب نمایش', 'zig3d-widgets'));

        foreach (Sorting::available($sorts, false) as $option) {
            $active = $current && $option['key'] === $current['key'];

            printf(
                '<li class="zig-sorts__item"><a class="zig-sorts__pill%1$s" href="%2$s"%3$s data-zig-sort="%5$s">%4$s</a></li>',
                $active ? ' is-active' : '',
                esc_url(Seo::url($url, $state->with_sort($option['key']), $operators)),
                $active ? ' aria-current="true"' : '',
                esc_html($option['label']),
                esc_attr($option['key'])
            );
        }

        echo '</ul></div>';
    }

    /* =====================================================================
     * موبایل: نوارِ فیلتر/مرتب‌سازی + شیتِ مرتب‌سازی
     *
     * در دسکتاپ، سایدبارِ فیلتر و نوارِ ترتیب همان‌جا که بودند می‌مانند —
     * این‌ها فقط زیرِ ۷۶۷px دیده می‌شوند (‎display:none‎ بالاتر) و همان
     * بریک‌پوینتی است که خودِ المنتور و ویجتِ سرچ «موبایل» می‌دانند.
     *
     * چرا رندرِ همیشگی و نه شرطی سمتِ سرور: کشِ صفحه برایِ یک عرض ذخیره
     * می‌شود؛ اگر مارک‌آپ به عرض وابسته بود، بازدیدکنندهٔ بعدی با عرضِ دیگر
     * نسخهٔ اشتباه را می‌گرفت. پس همه‌چیز چاپ می‌شود و CSS تصمیم می‌گیرد.
     * =================================================================== */

    /**
     * نوارِ موبایل: قرصی با دو دکمه — راست «فیلتر ها»، چپ برچسبِ ترتیبِ
     * فعال. هر دو ‎<button>‎اند نه ‎<a>‎، چون کنش‌اند (بازکردنِ شیت) نه
     * ناوبری — همان تمایزی که در سرتاسرِ این افزونه رعایت شده. خودِ
     * گزینه‌هایِ ترتیب داخلِ شیت پیوندِ واقعی می‌مانند.
     */
    private function render_mobile_bar(array $settings, array $sorts, Query_State $state, bool $has_sidebar, bool $show_sorts, string $filters_id, string $sort_id): void {
        if (!$has_sidebar && !$show_sorts) {
            return;
        }

        echo '<div class="zig-archive__mbar" data-zig-mbar>';

        if ($has_sidebar) {
            printf(
                '<button type="button" class="zig-archive__mbar-btn zig-archive__mbar-btn--filter" data-zig-open="filters" aria-expanded="false" aria-controls="%s">%s<span class="zig-archive__mbar-text">%s</span></button>',
                esc_attr($filters_id),
                Markup::svg_icon('filter', 'zig-archive__mbar-icon'),
                esc_html($settings['filters_title'] ?? __('فیلتر ها', 'zig3d-widgets'))
            );
        }

        if ($show_sorts) {
            $current = Sorting::resolve($sorts, $state->sort());
            $label   = $current ? (string) $current['label'] : '';

            printf(
                '<button type="button" class="zig-archive__mbar-btn zig-archive__mbar-btn--sort" data-zig-open="sort" aria-expanded="false" aria-controls="%s"><span class="zig-archive__mbar-text" data-zig-sort-label>%s</span>%s</button>',
                esc_attr($sort_id),
                esc_html($label),
                Markup::svg_icon('sort', 'zig-archive__mbar-icon')
            );
        }

        echo '</div>';
    }

    /**
     * شیتِ مرتب‌سازیِ موبایل — از پایین بالا می‌آید، اما برخلافِ شیتِ فیلتر
     * تمام‌ارتفاع نیست: ارتفاعش به‌اندازهٔ محتواست و به پایین چسبیده. خودِ
     * فهرستِ گزینه‌ها همان ‎render_sorts()‎ است — پس ‎swap('sorts')‎ که در
     * جاوااسکریپت روی *همهٔ* اسلات‌هایِ ‎sorts‎ اجرا می‌شود، این و نوارِ
     * دسکتاپ را با هم به‌روز نگه می‌دارد.
     */
    private function render_sort_sheet(array $settings, array $sorts, Query_State $state, string $sort_id, array $operators): void {
        printf('<div id="%s" class="zig-archive__sheet zig-archive__sheet--sort" data-zig-sheet="sort" hidden>', esc_attr($sort_id));
        echo '<div class="zig-archive__sheet-card">';
        echo '<span class="zig-archive__sheet-handle" aria-hidden="true"></span>';

        $title = trim((string) ($settings['sort_sheet_title'] ?? ''));

        if ('' !== $title) {
            printf('<h2 class="zig-archive__sheet-title">%s</h2>', esc_html($title));
        }

        echo '<div class="zig-archive__sheet-body" data-zig-part="sorts">';
        $this->render_sorts($sorts, $state, $operators);
        echo '</div></div></div>';
    }

    /* ---------------------------------------------------------------- */

    private function render_grid(array $settings, \WP_Query $query, Query_State $state, array $operators = []): void {
        if (!$query->have_posts()) {
            $this->render_empty($settings, $state, (int) $query->found_posts, $operators);

            return;
        }

        $fields = $this->card_fields($settings);

        echo '<ul class="zig-archive__grid">';

        while ($query->have_posts()) {
            $query->the_post();

            $product = function_exists('wc_get_product') ? wc_get_product(get_the_ID()) : null;

            if (!$product instanceof \WC_Product) {
                continue;
            }

            echo '<li class="zig-archive__cell">';

            if ('template' === ($settings['card_source'] ?? 'internal') && !empty($settings['card_template'])) {
                $this->render_template((int) $settings['card_template']);
            } else {
                $this->render_card(Product_Card::data($product, $fields), $settings);
            }

            echo '</li>';
        }

        echo '</ul>';
    }

    private function card_fields(array $settings): array {
        return [
            'suggested_meta'   => (string) ($settings['meta_suggested'] ?? ''),
            'description_meta' => (string) ($settings['meta_description'] ?? ''),
            'features_meta'    => (string) ($settings['meta_features'] ?? ''),
            'features_field'   => (string) ($settings['meta_features_field'] ?? ''),
            'features_attrs'   => (array) ($settings['features_attrs'] ?? []),
            'features_max'     => (int) ($settings['features_max'] ?? 3),
            'brand_taxonomy'   => (string) ($settings['brand_taxonomy'] ?? ''),
            'variable_mode'    => (string) ($settings['variable_mode'] ?? 'min'),
            'no_price'         => (string) ($settings['no_price'] ?? Card::PRICE_INQUIRY),
            'cta_mode'         => (string) ($settings['cta_mode'] ?? Card::CTA_AUTO),
        ];
    }

    /**
     * کارت محصول — شش ناحیه، هرکدام یک ظرفِ نام‌دار.
     *
     * ‎<article>‎ و ‎<h3>‎ واقعی، نه ‎<div>‎ با فونت بزرگ: خزنده‌ها — و
     * اسکرین‌ریدرها — ساختار را از تگ می‌خوانند نه از استایل.
     *
     * ساختار عمداً تودرتوست و هر لایه دلیل دارد:
     *
     *   ‎zig-card‎              ستون اصلی
     *     ‎__media‎             تصویر و ریبون          (۱)
     *     ‎__body‎              هرچه بین تصویر و پاست  (۲)
     *       ‎__meta‎            برند و لیبل موجودی     (۳)
     *       ‎__text‎            دسته، عنوان، توضیح     (۴)
     *       ‎__features‎        ویژگی‌ها               (۵)
     *     ‎__foot‎              قیمت و دکمه            (۶)
     *
     * چرا ظرف‌های جدا و نه یک ستون تخت: چون هرکدام باید فاصله، ترتیب و
     * چینشِ خودش را داشته باشد. با ساختار تخت، «گپ بین برند و عنوان» و
     * «گپ بین عنوان و ویژگی‌ها» یک عدد می‌شدند و تغییر یکی، آن یکی را هم
     * می‌برد.
     *
     * ‎__body‎ کشیده می‌شود تا ‎__foot‎ به کف بچسبد. بدون آن، کارتی که
     * توضیح کوتاه‌تری دارد دکمه‌اش وسط می‌ماند و ردیف دکمه‌ها در گرید
     * پله‌پله می‌شود.
     *
     * خط جداکننده، ‎border-top‎ خودِ ‎__foot‎ است نه یک ‎<hr>‎: یک عنصر
     * کمتر، و وقتی مدیر پا را خاموش کند خطش هم با خودش می‌رود.
     */
    private function render_card(array $card, array $settings): void {
        echo '<article class="zig-card">';

        /* ۱ */
        $this->render_card_media($card, $settings);

        echo '<div class="zig-card__body">';

        /* ۳ */
        $this->render_card_meta($card, $settings);

        /* ۴ */
        echo '<div class="zig-card__text">';

        if ('yes' === ($settings['show_category'] ?? '') && '' !== $card['category']) {
            printf('<p class="zig-card__category">%s</p>', esc_html($card['category']));
        }

        printf(
            '<h3 class="zig-card__title"><a href="%s">%s</a></h3>',
            esc_url($card['url']),
            esc_html($card['title'])
        );

        if ('' !== $card['description']) {
            printf('<p class="zig-card__desc">%s</p>', esc_html($card['description']));
        }

        echo '</div>';

        /* ۵ */
        $this->render_card_features($card);

        echo '</div>';

        /* ۶ */
        echo '<div class="zig-card__foot">';

        $this->render_card_price($card, $settings);
        $this->render_card_cta($card, $settings);

        echo '</div></article>';
    }

    /**
     * ردیف برند و موجودی.
     *
     * لیبل موجودی از روی تصویر آمده پایین و کنار برند نشسته. روی تصویر،
     * روی هر عکسِ روشنی که مدیر آپلود کند خوانا نبود — و یک کنترل رنگ
     * نمی‌توانست هم‌زمان جوابِ عکس روشن و تیره را بدهد.
     */
    private function render_card_meta(array $card, array $settings): void {
        $state = (string) ($card['stock']['state'] ?? '');
        $label = (string) ($settings['label_stock_' . $state] ?? '');
        $brand = (string) $card['brand'];

        if ('' === $brand && '' === $label) {
            return;
        }

        echo '<div class="zig-card__meta">';

        if ('' !== $brand) {
            printf('<p class="zig-card__brand">%s</p>', esc_html($brand));
        }

        if ('' !== $label) {
            printf(
                '<span class="zig-card__stock zig-card__stock--%s">'
                    . '<span class="zig-card__dot" aria-hidden="true"></span>%s</span>',
                esc_attr($state),
                esc_html($label)
            );
        }

        echo '</div>';
    }

    /**
     * ویژگی‌ها — یک نوار، با نقطه بین‌شان.
     *
     * قبلاً هر ویژگی چیپ جدا بود و سه چیپِ کنار هم، سه بلوکِ رنگی می‌ساخت
     * که چشم را از عنوان می‌دزدید. یک نوارِ واحد همان اطلاعات را می‌دهد و
     * یک عنصر بصری است نه سه‌تا.
     *
     * جداکننده ‎aria-hidden‎ است: صفحه‌خوان نباید «نقطه» بخواند. ولی
     * ‎<li>‎ها سر جایشان می‌مانند، چون این واقعاً یک فهرست است.
     */
    private function render_card_features(array $card): void {
        if (!$card['features']) {
            return;
        }

        echo '<ul class="zig-card__features">';

        foreach (array_values($card['features']) as $index => $feature) {
            printf(
                '%s<li class="zig-card__feature">%s</li>',
                $index > 0 ? '<li class="zig-card__sep" aria-hidden="true"></li>' : '',
                esc_html($feature)
            );
        }

        echo '</ul>';
    }

    private function render_card_media(array $card, array $settings): void {
        echo '<div class="zig-card__media">';

        if ($card['suggested'] && '' !== ($settings['label_suggested'] ?? '')) {
            printf(
                '<span class="zig-card__ribbon">'
                    . '<span class="zig-card__dot" aria-hidden="true"></span>%s</span>',
                esc_html($settings['label_suggested'])
            );
        }

        if ($card['image'] > 0) {
            echo wp_get_attachment_image(
                $card['image'],
                'woocommerce_thumbnail',
                false,
                ['class' => 'zig-card__image', 'loading' => 'lazy', 'decoding' => 'async']
            );
        }

        echo '</div>';
    }

    private function render_card_price(array $card, array $settings): void {
        if (Card::PRICE_HIDDEN === $card['price_mode']) {
            return;
        }

        echo '<div class="zig-card__price zig-price">';

        if (Card::PRICE_INQUIRY === $card['price_mode']) {
            /*
             * «استعلام قیمت» یک عمل است نه یک قیمت، پس آیکون تلفن می‌گیرد
             * و در ردیف خودش وسط می‌نشیند — تا با «۶٬۵۰۰٬۰۰۰٬۰۰۰ تومان»
             * که یک *مقدار* است اشتباه گرفته نشود.
             */
            printf(
                '<span class="zig-price__inquiry">%s%s</span>',
                Markup::svg_icon('phone', 'zig-price__icon'),
                esc_html($settings['label_price_inquiry'] ?? '')
            );

            echo '</div>';

            return;
        }

        $prefix = Card::LABEL_FROM === $card['price_label']
            ? (string) ($settings['label_price_from'] ?? '')
            : (string) ($settings['label_price_exact'] ?? '');

        if ('' !== $prefix) {
            printf('<span class="zig-price__prefix">%s</span>', esc_html($prefix));
        }

        printf(
            '<span class="zig-price__value"><span class="zig-price__amount">%s</span>',
            esc_html(Price::persian(Price::format((string) $card['price']['current'])))
        );

        if ('' !== ($settings['currency'] ?? '')) {
            printf('<span class="zig-price__unit">%s</span>', esc_html($settings['currency']));
        }

        echo '</span></div>';
    }

    private function render_card_cta(array $card, array $settings): void {
        $label = (string) ($settings['label_cta_' . $card['cta']] ?? '');

        if ('' === $label) {
            return;
        }

        /*
         * «جزئیات» همیشه به خودِ محصول می‌رود؛ بقیه به آدرسی که مدیر داده،
         * و اگر نداده باز به محصول — یک دکمهٔ بدون مقصد بدتر از دکمه‌ای است
         * که جای پیش‌فرضش می‌رود.
         */
        $custom = (string) ($settings['url_cta_' . $card['cta']]['url'] ?? '');
        $href   = ('' !== $custom && Card::CTA_DETAILS !== $card['cta']) ? $custom : $card['url'];

        printf(
            '<a class="zig-card__cta zig-btn" href="%s"><span class="zig-card__cta-text">%s</span>%s</a>',
            esc_url($href),
            esc_html($label),
            Markup::svg_icon('arrow', 'zig-card__cta-icon')
        );
    }

    private function render_template(int $id): void {
        if (!class_exists('\Elementor\Plugin')) {
            return;
        }

        echo \Elementor\Plugin::$instance->frontend->get_builder_content_for_display($id, true);
    }

    /* ---------------------------------------------------------------- */

    /**
     * حالت «چیزی نیست».
     *
     * دو حالتِ کاملاً متفاوت یک ظاهر دارند و باید از هم جدا شوند:
     *
     *   • واقعاً هیچ محصولی با این فیلترها نیست.
     *   • محصول هست، ولی در *این صفحه* نیست — آدرسی با ‎paged‎ی که از
     *     تعداد صفحه‌های این ترکیب بیشتر است.
     *
     * دومی با پیمایش داخل ویجت پیش نمی‌آید (هر تغییر فیلتر صفحه را به اول
     * برمی‌گرداند) ولی از بوکمارک و نوار آدرس می‌آید. نشان‌دادن «چیزی پیدا
     * نشد» به کسی که ۴۰ محصول در انتظارش است، دروغ است — و راه خروجی هم
     * نمی‌دهد. پس اینجا صریح می‌گوییم و یک پیوند به صفحهٔ اولِ *همین*
     * فیلترها می‌گذاریم.
     */
    private function render_empty(array $settings, Query_State $state, int $found, array $operators = []): void {
        echo '<div class="zig-archive__empty">';

        if ($found > 0 && $state->page() > 1) {
            printf(
                '<p class="zig-archive__empty-title">%s</p><a class="zig-archive__empty-link" href="%s">%s</a>',
                esc_html__('این صفحه از نتایجِ فعلی وجود ندارد.', 'zig3d-widgets'),
                esc_url(Seo::url($this->base_url(), $state->with_page(1), $operators)),
                esc_html__('رفتن به صفحهٔ اول همین فیلترها', 'zig3d-widgets')
            );

            echo '</div>';

            return;
        }

        if (!empty($settings['empty_template'])) {
            $this->render_template((int) $settings['empty_template']);
        } else {
            printf('<p class="zig-archive__empty-title">%s</p>', esc_html($settings['empty_title'] ?? ''));
        }

        echo '</div>';
    }

    /**
     * صفحه‌بندی صریح — که همیشه رندر می‌شود، حتی وقتی اسکرول خودکار روشن
     * است.
     *
     * جاوااسکریپت بعداً تصمیم می‌گیرد پنهانش کند یا نه. رندرنکردنش یعنی
     * بدون JS راهی به صفحهٔ دوم نماند و خزنده هم چیزی برای دنبال‌کردن
     * نداشته باشد.
     */
    private function render_pagination(array $settings, Query_State $state, int $pages, array $operators = []): void {
        if ($pages < 2) {
            return;
        }

        $url  = $this->base_url();
        $page = $state->page();

        echo '<nav class="zig-archive__pagination" aria-label="' . esc_attr__('صفحه‌بندی', 'zig3d-widgets') . '">';

        if ($page > 1) {
            printf(
                '<a class="zig-page zig-page--prev" href="%s" rel="prev" data-zig-goto="%s">%s</a>',
                esc_url(Seo::url($url, $state->with_page($page - 1), $operators)),
                (string) ($page - 1),
                esc_html($settings['label_prev'] ?? '')
            );
        }

        for ($number = 1; $number <= $pages; ++$number) {
            if ($number === $page) {
                printf(
                    '<span class="zig-page is-current" aria-current="page">%s</span>',
                    esc_html(Price::persian((string) $number))
                );

                continue;
            }

            printf(
                '<a class="zig-page" href="%s" data-zig-goto="%s">%s</a>',
                esc_url(Seo::url($url, $state->with_page($number), $operators)),
                (string) $number,
                esc_html(Price::persian((string) $number))
            );
        }

        if ($page < $pages) {
            printf(
                '<a class="zig-page zig-page--next" href="%s" rel="next" data-zig-goto="%s">%s</a>',
                esc_url(Seo::url($url, $state->with_page($page + 1), $operators)),
                (string) ($page + 1),
                esc_html($settings['label_next'] ?? '')
            );
        }

        echo '</nav>';
    }

    /**
     * پیام خطا — در HTML هست ولی پنهان، تا جاوااسکریپت لازم نباشد چیزی
     * بسازد.
     *
     * ‎role="alert"‎ و ‎aria-live="assertive"‎ چون این پیام یک بن‌بست را
     * اعلام می‌کند: کاربر منتظر محصولاتی است که نیامده‌اند و باید همان
     * لحظه بفهمد.
     */
    private function render_error(array $settings): void {
        printf(
            '<div class="zig-archive__error zig-archive__error--%s zig-archive__error--anim-%s"'
                . ' role="alert" aria-live="assertive" hidden>'
                . '<p class="zig-archive__error-text">%s</p>'
                . '<button type="button" class="zig-archive__retry">%s</button>'
                . '</div>',
            esc_attr($settings['error_position'] ?? 'bottom-center'),
            esc_attr($settings['error_anim'] ?? 'slide'),
            esc_html($settings['error_text'] ?? ''),
            esc_html($settings['error_retry'] ?? '')
        );
    }

    /* =====================================================================
     * گزینه‌های کنترل
     * =================================================================== */

    /**
     * شش ناحیهٔ کارت، برای ساختن کنترل‌ها.
     *
     * یک فهرست و یک حلقه، نه شش بار کپی‌ودیسِ سه کنترل. اضافه‌کردن ناحیهٔ
     * هفتم یک سطر است، و مهم‌تر: هیچ‌وقت نمی‌شود ناحیه‌ای داشت که سهواً
     * یکی از سه کنترلش جا افتاده باشد.
     *
     * ‎gap‎ برای ناحیه‌هایی که بیش از یک فرزند دارند؛ تصویر یکی بیشتر
     * ندارد و یک کنترلِ بی‌اثر، فقط چیزی است که مدیر امتحان می‌کند و فکر
     * می‌کند خراب است.
     */
    private function card_areas(): array {
        return [
            /*
             * «کل کارت» ترتیب ندارد، و این حذفِ عمدی است: کارت تنها فرزندِ
             * سلولِ گرید است و ‎order‎ روی یک عنصرِ تنها هیچ کاری نمی‌کند.
             * کنترلی که وجود دارد ولی اثر ندارد، بدتر از نبودنش است.
             */
            'area_card'     => ['label' => __('۱ کل کارت', 'zig3d-widgets'), 'var' => 'card', 'gap' => true, 'order' => false],
            'area_media'    => ['label' => __('۲ تصویر', 'zig3d-widgets'), 'var' => 'media', 'gap' => false, 'order' => true],
            'area_body'     => ['label' => __('۳ بدنه', 'zig3d-widgets'), 'var' => 'body', 'gap' => true, 'order' => true],
            'area_meta'     => ['label' => __('۴ برند و موجودی', 'zig3d-widgets'), 'var' => 'meta', 'gap' => true, 'order' => true],
            'area_text'     => ['label' => __('۵ عنوان و توضیح', 'zig3d-widgets'), 'var' => 'text', 'gap' => true, 'order' => true],
            'area_features' => ['label' => __('۶ ویژگی‌ها', 'zig3d-widgets'), 'var' => 'features', 'gap' => true, 'order' => true],
            'area_foot'     => ['label' => __('۷ قیمت و دکمه', 'zig3d-widgets'), 'var' => 'foot', 'gap' => true, 'order' => true],
        ];
    }

    private function category_options(): array {
        $terms = get_terms(['taxonomy' => Schema_Store::TAXONOMY, 'hide_empty' => false]);

        if (!is_array($terms)) {
            return [];
        }

        $options = [];

        foreach ($terms as $term) {
            if ($term instanceof \WP_Term) {
                $options[$term->term_id] = $term->name;
            }
        }

        return $options;
    }

    private function attribute_options(): array {
        $options = [];

        foreach (Attributes::all() as $taxonomy) {
            $options[$taxonomy] = Attributes::label($taxonomy);
        }

        return $options;
    }

    /**
     * قالب‌های ذخیره‌شدهٔ المنتور و آیتم‌های جت‌انجین، در یک فهرست.
     *
     * هر دو در عمل یک چیزند — یک قالب ذخیره‌شده که با شناسه‌اش صدا زده
     * می‌شود — و جداکردنشان در پنل فقط یک دراپ‌داون اضافه می‌ساخت.
     */
    private function template_options(): array {
        $posts = get_posts([
            'post_type'      => ['elementor_library', 'jet-engine'],
            'posts_per_page' => 100,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC',
        ]);

        $options = ['' => __('— بدون قالب —', 'zig3d-widgets')];

        foreach ((array) $posts as $post) {
            $options[$post->ID] = $post->post_title;
        }

        return $options;
    }

    private function sort_type_options(): array {
        $options = [];

        foreach (Sorting::TYPES as $key => $type) {
            $options[$key] = $type['label'];
        }

        return $options;
    }

    /** انواعی که کنترل جهت برایشان معنا دارد */
    private function directional_types(): array {
        $types = [];

        foreach (Sorting::TYPES as $key => $type) {
            if (!empty($type['directional'])) {
                $types[] = $key;
            }
        }

        return $types;
    }

    private function stock_labels(): array {
        return [
            Stock::IN_STOCK     => ['title' => __('موجود', 'zig3d-widgets'), 'default' => __('آماده تحویل', 'zig3d-widgets')],
            Stock::AVAILABLE    => ['title' => __('موجود (بدون شمارش)', 'zig3d-widgets'), 'default' => __('آماده تحویل', 'zig3d-widgets')],
            Stock::BACKORDER    => ['title' => __('پیش‌خرید', 'zig3d-widgets'), 'default' => __('پیش فروش', 'zig3d-widgets')],
            Stock::OUT_OF_STOCK => ['title' => __('ناموجود', 'zig3d-widgets'), 'default' => __('ناموجود', 'zig3d-widgets')],
        ];
    }

    private function cta_labels(): array {
        return [
            Card::CTA_DETAILS      => ['title' => __('متن «جزئیات»', 'zig3d-widgets'), 'default' => __('جزئیات محصول', 'zig3d-widgets'), 'url' => ''],
            Card::CTA_INQUIRY      => ['title' => __('متن «استعلام»', 'zig3d-widgets'), 'default' => __('استعلام قیمت', 'zig3d-widgets'), 'url' => __('لینک استعلام', 'zig3d-widgets')],
            Card::CTA_CONSULTATION => ['title' => __('متن «مشاوره»', 'zig3d-widgets'), 'default' => __('درخواست مشاوره', 'zig3d-widgets'), 'url' => __('لینک مشاوره', 'zig3d-widgets')],
        ];
    }

    /**
     * یادآوری اینکه فهرست فیلترها اینجا تعریف نمی‌شود.
     *
     * بدون این، اولین سؤال هر کسی که ویجت را باز می‌کند همین است — و
     * جوابش سه کلیک آن‌طرف‌تر در صفحهٔ ویرایش دسته است.
     */
    private function filters_notice(): string {
        return sprintf(
            '<div class="elementor-control-field-description">%s</div>',
            esc_html__(
                'فهرست گروه‌های فیلتر برای هر دسته، در صفحهٔ ویرایش همان دسته تعریف می‌شود — نه اینجا. این‌طور یک دسته یک تنظیم دارد، نه یکی به ازای هر صفحه‌ای که ویجت رویش نشسته.',
                'zig3d-widgets'
            )
        );
    }
}
