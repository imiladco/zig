<?php
namespace Zig3d_Widgets\Widgets;

use Elementor\Controls_Manager;
use Elementor\Repeater;
use Elementor\Widget_Base;
use Zig3d_Widgets\Archive_Endpoint;
use Zig3d_Widgets\Archive_Head;
use Zig3d_Widgets\Archive_Query;
use Zig3d_Widgets\Attributes;
use Zig3d_Widgets\Card;
use Zig3d_Widgets\Facets;
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

        $this->add_responsive_control('columns', [
            'label'   => __('تعداد ستون', 'zig3d-widgets'),
            'type'    => Controls_Manager::NUMBER,
            'default' => 3,
            'min'     => 1,
            'max'     => 6,
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

        $this->add_control('filters_clear', [
            'label'     => __('متن «پاک‌کردن همه»', 'zig3d-widgets'),
            'type'      => Controls_Manager::TEXT,
            'default'   => __('پاک‌کردن همه', 'zig3d-widgets'),
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

        $this->end_controls_section();
    }

    private function section_card(): void {
        $this->start_controls_section('sec_card', [
            'label' => __('کارت محصول', 'zig3d-widgets'),
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

        $this->add_control('label_price_exact', [
            'label'   => __('پیشوند «قیمت»', 'zig3d-widgets'),
            'type'    => Controls_Manager::TEXT,
            'default' => __('قیمت', 'zig3d-widgets'),
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
            'default'   => __('استعلامی', 'zig3d-widgets'),
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
            'default'    => ['size' => 280, 'unit' => 'px'],
            'range'      => ['px' => ['min' => 180, 'max' => 480], '%' => ['min' => 15, 'max' => 40]],
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
     * پدینگ ۳۲/۱۶/۱۶، شعاع ۱۲، پس‌زمینهٔ ‎#F7F7FA‎ و عرض تصویر ۱۶۲ با نسبت
     * ۱:۱. گذاشتنشان به‌عنوان *پیش‌فرضِ کنترل* و نه مقدار ثابت در CSS، تنها
     * راهی است که هم خروجی از روز اول درست باشد و هم بعداً بدون دست‌زدن به
     * کد قابل تغییر بماند.
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
            'selectors'  => ['{{WRAPPER}} .zig-card__media' => 'height: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_responsive_control('media_padding', [
            'label'      => __('فاصلهٔ داخلی', 'zig3d-widgets'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', 'rem'],
            'default'    => ['top' => 32, 'right' => 16, 'bottom' => 16, 'left' => 16, 'unit' => 'px', 'isLinked' => false],
            'selectors'  => [
                '{{WRAPPER}} .zig-card__media' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('media_radius', [
            'label'      => __('شعاع گوشه', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', 'rem'],
            'default'    => ['size' => 12, 'unit' => 'px'],
            'range'      => ['px' => ['min' => 0, 'max' => 48]],
            'selectors'  => ['{{WRAPPER}} .zig-card__media' => 'border-radius: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_control('media_background', [
            'label'     => __('پس‌زمینه', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'default'   => '#F7F7FA',
            'selectors' => ['{{WRAPPER}} .zig-card__media' => 'background-color: {{VALUE}};'],
        ]);

        $this->add_responsive_control('media_gap', [
            'label'      => __('فاصلهٔ عناصر داخل ظرف', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'default'    => ['size' => 2, 'unit' => 'px'],
            'range'      => ['px' => ['min' => 0, 'max' => 32]],
            'selectors'  => ['{{WRAPPER}} .zig-card__media' => 'gap: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_responsive_control('image_width', [
            'label'      => __('عرض تصویر', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', '%'],
            'default'    => ['size' => 162, 'unit' => 'px'],
            'range'      => ['px' => ['min' => 60, 'max' => 400], '%' => ['min' => 20, 'max' => 100]],
            'selectors'  => ['{{WRAPPER}} .zig-card__image' => 'width: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_control('image_fit', [
            'label'     => __('نحوهٔ جاگیری', 'zig3d-widgets'),
            'type'      => Controls_Manager::SELECT,
            'default'   => 'contain',
            'options'   => [
                'contain' => __('کامل دیده شود', 'zig3d-widgets'),
                'cover'   => __('کادر را پر کند', 'zig3d-widgets'),
            ],
            'selectors' => ['{{WRAPPER}} .zig-card__image' => 'object-fit: {{VALUE}};'],
        ]);

        $this->end_controls_section();
    }

    private function section_style_card(): void {
        $this->start_controls_section('sty_card', [
            'label' => __('کارت', 'zig3d-widgets'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_control('card_background', [
            'label'     => __('پس‌زمینه', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .zig-card' => 'background-color: {{VALUE}};'],
        ]);

        $this->add_responsive_control('card_padding', [
            'label'      => __('فاصلهٔ داخلی', 'zig3d-widgets'),
            'type'       => Controls_Manager::DIMENSIONS,
            'size_units' => ['px', 'rem'],
            'selectors'  => [
                '{{WRAPPER}} .zig-card' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('card_radius', [
            'label'      => __('شعاع گوشه', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', 'rem'],
            'default'    => ['size' => 16, 'unit' => 'px'],
            'range'      => ['px' => ['min' => 0, 'max' => 48]],
            'selectors'  => ['{{WRAPPER}} .zig-card' => 'border-radius: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_responsive_control('card_gap', [
            'label'      => __('فاصلهٔ بخش‌های کارت', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px', 'rem'],
            'default'    => ['size' => 8, 'unit' => 'px'],
            'range'      => ['px' => ['min' => 0, 'max' => 40]],
            'selectors'  => ['{{WRAPPER}} .zig-card__body' => 'gap: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_control('brand_color', [
            'label'     => __('رنگ برند', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'separator' => 'before',
            'selectors' => ['{{WRAPPER}} .zig-card__brand' => 'color: {{VALUE}};'],
        ]);

        $this->add_control('title_color', [
            'label'     => __('رنگ عنوان', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .zig-card__title, {{WRAPPER}} .zig-card__title a' => 'color: {{VALUE}};'],
        ]);

        $this->add_control('desc_color', [
            'label'     => __('رنگ توضیح', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .zig-card__desc' => 'color: {{VALUE}};'],
        ]);

        $this->add_control('feature_background', [
            'label'     => __('پس‌زمینهٔ حباب ویژگی', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'separator' => 'before',
            'selectors' => ['{{WRAPPER}} .zig-card__feature' => 'background-color: {{VALUE}};'],
        ]);

        $this->add_control('feature_color', [
            'label'     => __('رنگ متن حباب', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .zig-card__feature' => 'color: {{VALUE}};'],
        ]);

        $this->add_control('ribbon_background', [
            'label'     => __('پس‌زمینهٔ ریبون پیشنهاد', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'separator' => 'before',
            'selectors' => ['{{WRAPPER}} .zig-card__ribbon' => 'background-color: {{VALUE}};'],
        ]);

        $this->add_control('ribbon_color', [
            'label'     => __('رنگ متن ریبون', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .zig-card__ribbon' => 'color: {{VALUE}};'],
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
            'selectors' => ['{{WRAPPER}} .zig-price__amount' => 'color: {{VALUE}};'],
        ]);

        $this->add_control('price_prefix_color', [
            'label'     => __('رنگ پیشوند', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .zig-price__prefix' => 'color: {{VALUE}};'],
        ]);

        $this->add_control('price_unit_color', [
            'label'     => __('رنگ واحد پول', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .zig-price__unit' => 'color: {{VALUE}};'],
        ]);

        /*
         * «استعلامی» رنگ مستقل دارد چون معنایش هم مستقل است: عدد نیست،
         * یک وضعیت است. یکی‌کردنش با رنگ عدد، دو چیز متفاوت را شبیه هم
         * نشان می‌داد.
         */
        $this->add_control('price_inquiry_color', [
            'label'     => __('رنگ متن استعلامی', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .zig-price__inquiry' => 'color: {{VALUE}};'],
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
            'selectors' => ['{{WRAPPER}} .zig-card__cta' => 'color: {{VALUE}};'],
        ]);

        $this->add_control('cta_background', [
            'label'     => __('پس‌زمینه', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .zig-card__cta' => 'background-color: {{VALUE}};'],
        ]);

        $this->end_controls_tab();

        $this->start_controls_tab('cta_hover', ['label' => __('هاور', 'zig3d-widgets')]);

        $this->add_control('cta_color_hover', [
            'label'     => __('رنگ متن', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .zig-card__cta:hover, {{WRAPPER}} .zig-card__cta:focus-visible' => 'color: {{VALUE}};'],
        ]);

        $this->add_control('cta_background_hover', [
            'label'     => __('پس‌زمینه', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .zig-card__cta:hover, {{WRAPPER}} .zig-card__cta:focus-visible' => 'background-color: {{VALUE}};'],
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
            'selectors'  => ['{{WRAPPER}} .zig-card__cta' => 'border-radius: {{SIZE}}{{UNIT}};'],
        ]);

        $this->end_controls_section();
    }

    private function section_style_chrome(): void {
        $this->start_controls_section('sty_chrome', [
            'label' => __('فیلتر، ترتیب و صفحه‌بندی', 'zig3d-widgets'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $this->add_control('facet_title_color', [
            'label'     => __('رنگ عنوان گروه فیلتر', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .zig-facet__title' => 'color: {{VALUE}};'],
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

        $this->add_control('sort_color', [
            'label'     => __('رنگ پیل ترتیب', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'separator' => 'before',
            'selectors' => ['{{WRAPPER}} .zig-sorts__pill' => 'color: {{VALUE}};'],
        ]);

        $this->add_control('sort_active_background', [
            'label'     => __('پس‌زمینهٔ پیل فعال', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .zig-sorts__pill.is-active' => 'background-color: {{VALUE}};'],
        ]);

        $this->add_control('sort_active_color', [
            'label'     => __('رنگ متن پیل فعال', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .zig-sorts__pill.is-active' => 'color: {{VALUE}};'],
        ]);

        $this->add_control('page_color', [
            'label'     => __('رنگ شمارهٔ صفحه', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'separator' => 'before',
            'selectors' => ['{{WRAPPER}} .zig-page' => 'color: {{VALUE}};'],
        ]);

        $this->add_control('page_current_background', [
            'label'     => __('پس‌زمینهٔ صفحهٔ جاری', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .zig-page.is-current' => 'background-color: {{VALUE}};'],
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

        if ($this->has_sidebar($settings, $facets)) {
            echo '<aside class="zig-archive__filters" aria-label="'
                . esc_attr($settings['filters_title'] ?? '') . '" data-zig-part="facets">';
            echo $this->fragment('facets', $context);
            echo '</aside>';
        }

        echo '<div class="zig-archive__main">';

        $this->render_toolbar($settings, $sorts, $state, (int) $query->found_posts, $operators);

        echo '<div data-zig-part="grid">' . $this->fragment('grid', $context) . '</div>';
        echo '<div data-zig-part="pagination">' . $this->fragment('pagination', $context) . '</div>';

        $this->render_error($settings);

        echo '</div></div>';

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
        $per_page = Archive_Query::per_page((int) ($settings['per_page'] ?? 9));

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
        $url = $this->base_url();

        printf('<h2 class="zig-filters__title">%s</h2>', esc_html($settings['filters_title'] ?? ''));

        if ($state->is_filtered()) {
            printf(
                '<a class="zig-filters__clear" href="%s" data-zig-clear="1">%s</a>',
                esc_url(Seo::url($url, $state->cleared(), $operators)),
                esc_html($settings['filters_clear'] ?? '')
            );
        }

        foreach ($facets as $facet) {
            $this->render_facet($facet, $state, $base, $context, $operators, $url);
        }
    }

    private function render_facet(array $facet, Query_State $state, array $base, string $context, array $operators, string $url): void {
        $taxonomy = $facet['taxonomy'];

        $options = Facets::options(
            Attributes::terms($taxonomy),
            Attributes::counts($base, $state, $taxonomy, $operators, $context, $facet['semantics']),
            $state->selected($taxonomy)
        );

        if (!Facets::group_is_visible($options, $facet['show_empty'])) {
            return;
        }

        printf(
            '<details class="zig-facet" open><summary class="zig-facet__title">%s</summary><ul class="zig-facet__list">',
            esc_html(Attributes::label($taxonomy))
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

        if ('yes' === ($settings['count_on'] ?? '')) {
            echo '<div data-zig-part="count">';
            $this->render_count($settings, $found);
            echo '</div>';
        }

        if ('yes' === ($settings['sorting_on'] ?? '') && $sorts) {
            $this->render_sorts($sorts, $state, $operators);
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

        echo '<ul class="zig-sorts">';

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

        echo '</ul>';
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
     * کارت داخلی.
     *
     * ‎<article>‎ و ‎<h3>‎ واقعی، نه ‎<div>‎ با فونت بزرگ: خزنده‌ها — و
     * اسکرین‌ریدرها — ساختار را از تگ می‌خوانند نه از استایل.
     */
    private function render_card(array $card, array $settings): void {
        echo '<article class="zig-card">';

        $this->render_card_media($card, $settings);

        echo '<div class="zig-card__body">';

        if ('' !== $card['brand']) {
            printf('<p class="zig-card__brand">%s</p>', esc_html($card['brand']));
        }

        printf(
            '<h3 class="zig-card__title"><a href="%s">%s</a></h3>',
            esc_url($card['url']),
            esc_html($card['title'])
        );

        if ('' !== $card['description']) {
            printf('<p class="zig-card__desc">%s</p>', esc_html($card['description']));
        }

        if ($card['features']) {
            echo '<ul class="zig-card__features">';

            foreach ($card['features'] as $feature) {
                printf('<li class="zig-card__feature">%s</li>', esc_html($feature));
            }

            echo '</ul>';
        }

        $this->render_card_price($card, $settings);
        $this->render_card_cta($card, $settings);

        echo '</div></article>';
    }

    private function render_card_media(array $card, array $settings): void {
        echo '<div class="zig-card__media">';

        if ($card['suggested'] && '' !== ($settings['label_suggested'] ?? '')) {
            printf('<span class="zig-card__ribbon">%s</span>', esc_html($settings['label_suggested']));
        }

        $state = (string) ($card['stock']['state'] ?? '');
        $label = (string) ($settings['label_stock_' . $state] ?? '');

        if ('' !== $label) {
            printf(
                '<span class="zig-card__stock zig-card__stock--%s">%s</span>',
                esc_attr($state),
                esc_html($label)
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
            printf(
                '<span class="zig-price__inquiry">%s</span>',
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
            '<span class="zig-price__amount">%s</span>',
            esc_html(Price::persian(Price::format((string) $card['price']['current'])))
        );

        if ('' !== ($settings['currency'] ?? '')) {
            printf('<span class="zig-price__unit">%s</span>', esc_html($settings['currency']));
        }

        echo '</div>';
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
            '<a class="zig-card__cta zig-btn" href="%s">%s</a>',
            esc_url($href),
            esc_html($label)
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
