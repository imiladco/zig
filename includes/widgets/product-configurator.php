<?php
namespace Zig3d_Widgets\Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Background;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Text_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Icons_Manager;
use Elementor\Widget_Base;
use Zig3d_Widgets\Configurator;
use Zig3d_Widgets\Design_Icons;
use Zig3d_Widgets\Plugin;
use Zig3d_Widgets\Price;
use Zig3d_Widgets\Rate_Price;
use Zig3d_Widgets\Stock;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * انتخاب کانفیگ محصول — قیمت محصول عادی و متغیر، بدون افزودن به سبد خرید.
 *
 *     div.zig-configurator
 *       script.zig-configurator__data      دادهٔ واریانت‌ها (فقط محصولِ متغیر)
 *       div.zig-configurator__card         جعبهٔ تیره — فقط تا زیرِ قیمت/موجودی
 *         div.zig-configurator__header
 *           h3.zig-configurator__title
 *           p.zig-configurator__subtitle
 *         div.zig-configurator__fields     یک کشو به‌ازای هر ویژگیِ واریانت‌ساز
 *           div.zig-configurator__field
 *             label.zig-configurator__label
 *             span.zig-configurator__select-wrap
 *               select.zig-configurator__select
 *               span.zig-configurator__chevron
 *         div.zig-configurator__bottom     ردیفِ قیمت (چپ) و موجودی/زمان (راست)
 *           div.zig-configurator__price
 *             bdi.zig-configurator__value
 *               span.zig-configurator__amount
 *               span.zig-configurator__unit
 *           div.zig-configurator__side
 *             div.zig-configurator__stock.zig-configurator__stock--{bucket}
 *               span.zig-configurator__stock-label
 *               span.zig-configurator__stock-dot
 *             div.zig-configurator__updated
 *               span.zig-configurator__updated-label
 *               span.zig-configurator__updated-value
 *       div.zig-configurator__actions      بیرونِ جعبهٔ تیره
 *         a|button.zig-configurator__btn.zig-configurator__btn--secondary
 *         a|button.zig-configurator__btn.zig-configurator__btn--primary
 *
 * هیچ «افزودن به سبد خرید»ی اینجا نیست؛ دو دکمهٔ پایین فقط پیوندند، رفتار
 * کلیکشان بعداً مشخص می‌شود. کشوها دقیقاً همان ویژگی‌هایی‌اند که خودِ
 * محصول برای واریانت‌سازی استفاده کرده — یکی، دوتا، یا بیشتر، نه لزوماً
 * «کانفیگ» و «متریال». منطقِ محاسبه در ‎Zig3d_Widgets\Configurator‎،
 * ‎Price‎، ‎Stock‎ و ‎Rate_Price‎ است تا بدون بالا آوردن المنتور قابل
 * تست بماند؛ این کلاس فقط کنترل‌ها و مارک‌آپ است.
 *
 * دادهٔ همهٔ واریانت‌ها یک‌جا در HTML تعبیه می‌شود و سوییچ بین ترکیب‌ها
 * سمتِ کلاینت است (‎zig3d-configurator.js‎) — نه یک درخواستِ آژاکس به
 * ازای هر تغییرِ دراپ‌داون؛ تعدادِ گزینه‌های یک محصول برای این توجیه
 * کوچک است.
 */
final class Product_Configurator extends Widget_Base {

    use Traits\Link;

    /** نگاشتِ آیکونِ فلشِ کشو به فایلِ صادرشده از فیگما */
    private const DESIGN_ICONS = [
        'chevron_icon' => 'chevron-down',
    ];

    /**
     * سه وضعیتِ موجودی — بدون تعداد، چون این فروشگاه ردیابیِ موجودی ندارد.
     *
     * ‎Stock::state()‎ پنج خروجی دارد؛ اینجا با ‎backorder:true, lowstock:false‎
     * سه‌تایشان اصلاً رخ نمی‌دهند و ‎IN_STOCK‎/‎AVAILABLE‎ هر دو یک برچسبِ
     * واحد می‌گیرند — دقیقاً همان چیزی که کاربر خواسته.
     */
    private function stock_states(): array {
        return [
            'instock'    => ['label' => __('موجود در انبار', 'zig3d-widgets'), 'default' => __('موجود در انبار', 'zig3d-widgets'), 'color' => '#34D399'],
            'preorder'   => ['label' => __('پیش‌فروش', 'zig3d-widgets'), 'default' => __('پیش‌فروش', 'zig3d-widgets'), 'color' => '#60A5FA'],
            'outofstock' => ['label' => __('ناموجود', 'zig3d-widgets'), 'default' => __('ناموجود', 'zig3d-widgets'), 'color' => '#F87171'],
        ];
    }

    /** خروجیِ ‎Stock::state()‎ را به یکی از سه بجِ این ویجت می‌فشرد */
    private function stock_bucket(string $state): string {
        if (Stock::BACKORDER === $state) {
            return 'preorder';
        }

        if (Stock::OUT_OF_STOCK === $state) {
            return 'outofstock';
        }

        return 'instock';
    }

    public function get_name(): string {
        return 'zig3d-product-configurator';
    }

    public function get_title(): string {
        return __('کانفیگ محصول', 'zig3d-widgets');
    }

    public function get_icon(): string {
        return 'eicon-price-table';
    }

    public function get_categories(): array {
        return [Plugin::CATEGORY];
    }

    public function get_keywords(): array {
        return ['configurator', 'variable', 'attribute', 'price', 'stock', 'کانفیگ', 'محصول متغیر', 'ویژگی', 'قیمت', 'موجودی'];
    }

    public function get_style_depends(): array {
        return ['zig3d-widgets'];
    }

    public function get_script_depends(): array {
        return ['zig3d-configurator'];
    }

    public function has_widget_inner_wrapper(): bool {
        return false;
    }

    protected function register_controls(): void {
        $this->register_product_section();
        $this->register_header_section();
        $this->register_fields_section();
        $this->register_price_section();
        $this->register_stock_section();
        $this->register_updated_section();
        $this->register_icons_section();
        $this->register_buttons_section();

        $this->register_container_style_section();
        $this->register_header_style_section();
        $this->register_fields_style_section();
        $this->register_price_style_section();
        $this->register_stock_style_section();
        $this->register_updated_style_section();
        $this->register_buttons_style_section();
    }

    /* =====================================================================
     * محتوا: محصول
     * =================================================================== */

    private function register_product_section(): void {
        $this->start_controls_section(
            'product_section',
            ['label' => __('محصول', 'zig3d-widgets')]
        );

        $this->add_control(
            'product_id',
            [
                'label'       => __('شناسهٔ محصول', 'zig3d-widgets'),
                'type'        => Controls_Manager::NUMBER,
                'min'         => 0,
                'dynamic'     => ['active' => true],
                'description' => __('خالی بگذارید تا محصول جاری استفاده شود — چه در صفحهٔ محصول، چه داخل حلقهٔ فروشگاه یا قالب حلقهٔ المنتور.', 'zig3d-widgets'),
            ]
        );

        $this->add_control(
            'fields_auto_note',
            [
                'type'            => Controls_Manager::RAW_HTML,
                'raw'             => __('برای محصول متغیر، کشوها خودکار از رویِ ویژگی‌هایی ساخته می‌شوند که خودِ محصول برای واریانت‌سازی استفاده کرده — یکی، دوتا یا بیشتر، هر چقدر باشد. برای محصول عادی، فقط قیمت و موجودی نمایش داده می‌شود.', 'zig3d-widgets'),
                'content_classes' => 'elementor-descriptor',
                'separator'       => 'before',
            ]
        );

        $this->end_controls_section();
    }

    /* =====================================================================
     * محتوا: عنوان
     * =================================================================== */

    private function register_header_section(): void {
        $this->start_controls_section(
            'header_section',
            ['label' => __('عنوان', 'zig3d-widgets')]
        );

        $this->add_control(
            'show_header',
            [
                'label'        => __('نمایش عنوان', 'zig3d-widgets'),
                'type'         => Controls_Manager::SWITCHER,
                'default'      => 'yes',
                'return_value' => 'yes',
            ]
        );

        $this->add_control(
            'title',
            [
                'label'       => __('عنوان', 'zig3d-widgets'),
                'type'        => Controls_Manager::TEXT,
                'dynamic'     => ['active' => true],
                'default'     => __('انتخاب کانفیگ محصول', 'zig3d-widgets'),
                'label_block' => true,
                'condition'   => ['show_header' => 'yes'],
            ]
        );

        $this->add_control(
            'subtitle',
            [
                'label'       => __('زیرعنوان', 'zig3d-widgets'),
                'type'        => Controls_Manager::TEXTAREA,
                'dynamic'     => ['active' => true],
                'default'     => __('قیمت نهایی محصول پس از انتخاب کانفیگ نمایش داده می‌شود.', 'zig3d-widgets'),
                'condition'   => ['show_header' => 'yes'],
            ]
        );

        $this->end_controls_section();
    }

    /* =====================================================================
     * محتوا: کشوها
     * =================================================================== */

    private function register_fields_section(): void {
        $this->start_controls_section(
            'fields_section',
            ['label' => __('کشوهای ویژگی', 'zig3d-widgets')]
        );

        $this->add_control(
            'placeholder_text',
            [
                'label'   => __('متن جای‌گیر', 'zig3d-widgets'),
                'type'    => Controls_Manager::TEXT,
                'default' => __('انتخاب کنید', 'zig3d-widgets'),
                'dynamic' => ['active' => true],
            ]
        );

        $this->add_control(
            'cascade_note',
            [
                'type'            => Controls_Manager::RAW_HTML,
                'raw'             => __('انتخابِ هر کشو گزینه‌های ناسازگار در کشوهای دیگر را غیرفعال می‌کند — همان رفتاری که خودِ ووکامرس در دراپ‌داونِ واریانت دارد.', 'zig3d-widgets'),
                'content_classes' => 'elementor-descriptor',
            ]
        );

        $this->end_controls_section();
    }

    /* =====================================================================
     * محتوا: قیمت
     * =================================================================== */

    private function register_price_section(): void {
        $this->start_controls_section(
            'price_section',
            ['label' => __('قیمت', 'zig3d-widgets')]
        );

        $this->add_control(
            'price_note',
            [
                'type'            => Controls_Manager::RAW_HTML,
                'raw'             => __('پیش‌فرض، کمترین قیمتِ محصول است. با انتخابِ کاملِ کشوها، قیمت و بجِ موجودی و زمانِ به‌روزرسانی هم‌زمان با همان ترکیب هماهنگ می‌شوند.', 'zig3d-widgets'),
                'content_classes' => 'elementor-descriptor',
            ]
        );

        $this->add_control(
            'persian_digits',
            [
                'label'        => __('ارقام فارسی', 'zig3d-widgets'),
                'type'         => Controls_Manager::SWITCHER,
                'default'      => 'yes',
                'return_value' => 'yes',
                'separator'    => 'before',
            ]
        );

        $this->add_control(
            'currency_text',
            [
                'label'       => __('متن واحد پول', 'zig3d-widgets'),
                'type'        => Controls_Manager::TEXT,
                'placeholder' => Price::currency(),
                'description' => __('خالی = نماد پیش‌فرض ووکامرس.', 'zig3d-widgets'),
            ]
        );

        $this->add_control(
            'free_text',
            [
                'label'       => __('متن قیمت صفر', 'zig3d-widgets'),
                'type'        => Controls_Manager::TEXT,
                'default'     => __('رایگان', 'zig3d-widgets'),
                'description' => __('خالی بگذارید تا عددِ صفر نمایش داده شود.', 'zig3d-widgets'),
                'separator'   => 'before',
            ]
        );

        $this->end_controls_section();
    }

    /* =====================================================================
     * محتوا: موجودی
     * =================================================================== */

    private function register_stock_section(): void {
        $this->start_controls_section(
            'stock_section',
            ['label' => __('موجودی', 'zig3d-widgets')]
        );

        $this->add_control(
            'show_stock',
            [
                'label'        => __('نمایش بج موجودی', 'zig3d-widgets'),
                'type'         => Controls_Manager::SWITCHER,
                'default'      => 'yes',
                'return_value' => 'yes',
            ]
        );

        foreach ($this->stock_states() as $key => $state) {
            $this->add_control(
                'text_' . $key,
                [
                    'label'       => $state['label'],
                    'type'        => Controls_Manager::TEXT,
                    'default'     => $state['default'],
                    'dynamic'     => ['active' => true],
                    'label_block' => true,
                    'condition'   => ['show_stock' => 'yes'],
                ]
            );
        }

        $this->end_controls_section();
    }

    /* =====================================================================
     * محتوا: زمان به‌روزرسانی
     * =================================================================== */

    private function register_updated_section(): void {
        $this->start_controls_section(
            'updated_section',
            ['label' => __('زمان به‌روزرسانی قیمت', 'zig3d-widgets')]
        );

        $this->add_control(
            'updated_note',
            [
                'type'            => Controls_Manager::RAW_HTML,
                'raw'             => __('این زمان از افزونهٔ «نوسان» (نرخِ ارز) خوانده می‌شود، نه اینجا ساخته می‌شود؛ برای محصول/واریانتی که نرخ‌محور نیست، این بخش خودکار پنهان می‌ماند.', 'zig3d-widgets'),
                'content_classes' => 'elementor-descriptor',
            ]
        );

        $this->add_control(
            'show_updated',
            [
                'label'        => __('نمایش', 'zig3d-widgets'),
                'type'         => Controls_Manager::SWITCHER,
                'default'      => 'yes',
                'return_value' => 'yes',
                'separator'    => 'before',
            ]
        );

        $this->add_control(
            'updated_label',
            [
                'label'     => __('برچسب', 'zig3d-widgets'),
                'type'      => Controls_Manager::TEXT,
                'default'   => __('به‌روزرسانی قیمت:', 'zig3d-widgets'),
                'dynamic'   => ['active' => true],
                'condition' => ['show_updated' => 'yes'],
            ]
        );

        $this->end_controls_section();
    }

    /* =====================================================================
     * محتوا: آیکون‌ها
     * =================================================================== */

    private function register_icons_section(): void {
        $this->start_controls_section(
            'icons_section',
            ['label' => __('آیکون‌ها', 'zig3d-widgets')]
        );

        $this->add_control(
            'design_icons',
            [
                'label'        => __('آیکون‌های پیش‌فرض طرح', 'zig3d-widgets'),
                'type'         => Controls_Manager::SWITCHER,
                'default'      => 'yes',
                'label_on'     => __('روشن', 'zig3d-widgets'),
                'label_off'    => __('خاموش', 'zig3d-widgets'),
                'return_value' => 'yes',
                'description'  => __('روشن باشد، فلشِ خالی همان SVGی طرح را می‌گیرد. خاموشش کنید تا آیکونِ خالی واقعاً حذف شود.', 'zig3d-widgets'),
            ]
        );

        $this->add_control(
            'chevron_icon',
            [
                'label'       => __('آیکون فلش کشو', 'zig3d-widgets'),
                'type'        => Controls_Manager::ICONS,
                'default'     => ['value' => '', 'library' => ''],
                'description' => __('خالی یعنی آیکونِ خودِ طرح.', 'zig3d-widgets'),
            ]
        );

        $this->end_controls_section();
    }

    /* =====================================================================
     * محتوا: دکمه‌ها
     * =================================================================== */

    private function register_buttons_section(): void {
        $this->start_controls_section(
            'buttons_section',
            ['label' => __('دکمه‌ها', 'zig3d-widgets')]
        );

        $this->add_control(
            'buttons_note',
            [
                'type'            => Controls_Manager::RAW_HTML,
                'raw'             => __('این ویجت افزودن به سبد خرید ندارد؛ این دو فقط پیوندند. هر کدام خالی بماند، اصلاً رندر نمی‌شود.', 'zig3d-widgets'),
                'content_classes' => 'elementor-descriptor',
            ]
        );

        $this->add_control(
            'secondary_heading',
            [
                'label'     => __('دکمهٔ اول (ثانویه)', 'zig3d-widgets'),
                'type'      => Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        $this->add_control(
            'secondary_text',
            [
                'label'       => __('متن', 'zig3d-widgets'),
                'type'        => Controls_Manager::TEXT,
                'dynamic'     => ['active' => true],
                'default'     => __('دریافت مشاورهٔ تخصصی', 'zig3d-widgets'),
                'label_block' => true,
            ]
        );

        $this->add_link_control('secondary_link');

        $this->add_control(
            'primary_heading',
            [
                'label'     => __('دکمهٔ دوم (اصلی)', 'zig3d-widgets'),
                'type'      => Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        $this->add_control(
            'primary_text',
            [
                'label'       => __('متن', 'zig3d-widgets'),
                'type'        => Controls_Manager::TEXT,
                'dynamic'     => ['active' => true],
                'default'     => __('درخواست پیش‌فاکتور', 'zig3d-widgets'),
                'label_block' => true,
            ]
        );

        $this->add_link_control('primary_link');

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل: جعبه
     * =================================================================== */

    private function register_container_style_section(): void {
        $this->start_controls_section(
            'container_style_section',
            [
                'label' => __('جعبه', 'zig3d-widgets'),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_responsive_control(
            'content_gap',
            [
                'label'      => __('فاصلهٔ جعبه تا دکمه‌ها', 'zig3d-widgets'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px', 'em', 'rem'],
                'range'      => ['px' => ['min' => 0, 'max' => 60]],
                'default'    => ['size' => 24, 'unit' => 'px'],
                'selectors'  => ['{{WRAPPER}} .zig-configurator' => 'gap: {{SIZE}}{{UNIT}};'],
            ]
        );

        $this->add_group_control(
            Group_Control_Background::get_type(),
            [
                'name'     => 'container_background',
                'types'    => ['classic', 'gradient'],
                'selector' => '{{WRAPPER}} .zig-configurator__card',
            ]
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name'     => 'container_border',
                'selector' => '{{WRAPPER}} .zig-configurator__card',
            ]
        );

        $this->add_responsive_control(
            'container_radius',
            [
                'label'      => __('گردی گوشه', 'zig3d-widgets'),
                'type'       => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'selectors'  => ['{{WRAPPER}} .zig-configurator__card' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
            ]
        );

        $this->add_responsive_control(
            'container_padding',
            [
                'label'      => __('فاصلهٔ داخلی', 'zig3d-widgets'),
                'type'       => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', 'rem'],
                'selectors'  => ['{{WRAPPER}} .zig-configurator__card' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
            ]
        );

        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            [
                'name'     => 'container_shadow',
                'selector' => '{{WRAPPER}} .zig-configurator__card',
            ]
        );

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل: عنوان
     * =================================================================== */

    private function register_header_style_section(): void {
        $this->start_controls_section(
            'header_style_section',
            [
                'label'     => __('عنوان', 'zig3d-widgets'),
                'tab'       => Controls_Manager::TAB_STYLE,
                'condition' => ['show_header' => 'yes'],
            ]
        );

        $this->add_responsive_control(
            'header_gap',
            [
                'label'      => __('فاصلهٔ عنوان تا زیرعنوان', 'zig3d-widgets'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px', 'em'],
                'range'      => ['px' => ['min' => 0, 'max' => 30]],
                'default'    => ['size' => 8, 'unit' => 'px'],
                'selectors'  => ['{{WRAPPER}} .zig-configurator__header' => 'gap: {{SIZE}}{{UNIT}};'],
            ]
        );

        $this->add_control(
            'title_heading',
            [
                'label'     => __('عنوان', 'zig3d-widgets'),
                'type'      => Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'     => 'title_typography',
                'selector' => '{{WRAPPER}} .zig-configurator__title',
            ]
        );

        $this->add_control(
            'title_color',
            [
                'label'     => __('رنگ', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => ['{{WRAPPER}} .zig-configurator__title' => 'color: {{VALUE}};'],
            ]
        );

        $this->add_control(
            'subtitle_heading',
            [
                'label'     => __('زیرعنوان', 'zig3d-widgets'),
                'type'      => Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'     => 'subtitle_typography',
                'selector' => '{{WRAPPER}} .zig-configurator__subtitle',
            ]
        );

        $this->add_control(
            'subtitle_color',
            [
                'label'     => __('رنگ', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => ['{{WRAPPER}} .zig-configurator__subtitle' => 'color: {{VALUE}};'],
            ]
        );

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل: کشوها
     * =================================================================== */

    private function register_fields_style_section(): void {
        $this->start_controls_section(
            'fields_style_section',
            [
                'label' => __('کشوهای ویژگی', 'zig3d-widgets'),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_responsive_control(
            'fields_column_gap',
            [
                'label'      => __('فاصلهٔ افقی بین کشوها', 'zig3d-widgets'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px', 'em'],
                'range'      => ['px' => ['min' => 0, 'max' => 60]],
                'default'    => ['size' => 16, 'unit' => 'px'],
                'selectors'  => ['{{WRAPPER}} .zig-configurator__fields' => 'column-gap: {{SIZE}}{{UNIT}};'],
            ]
        );

        $this->add_responsive_control(
            'fields_row_gap',
            [
                'label'      => __('فاصلهٔ عمودی بین ردیف‌ها', 'zig3d-widgets'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px', 'em'],
                'range'      => ['px' => ['min' => 0, 'max' => 60]],
                'default'    => ['size' => 16, 'unit' => 'px'],
                'selectors'  => ['{{WRAPPER}} .zig-configurator__fields' => 'row-gap: {{SIZE}}{{UNIT}};'],
            ]
        );

        $this->add_control(
            'label_heading',
            [
                'label'     => __('برچسب کشو', 'zig3d-widgets'),
                'type'      => Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'     => 'label_typography',
                'selector' => '{{WRAPPER}} .zig-configurator__label',
            ]
        );

        $this->add_control(
            'label_color',
            [
                'label'     => __('رنگ', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => ['{{WRAPPER}} .zig-configurator__label' => 'color: {{VALUE}};'],
            ]
        );

        $this->add_control(
            'select_heading',
            [
                'label'     => __('کادر انتخاب', 'zig3d-widgets'),
                'type'      => Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'     => 'select_typography',
                'selector' => '{{WRAPPER}} .zig-configurator__select',
            ]
        );

        $this->add_control(
            'select_color',
            [
                'label'     => __('رنگ متن', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => ['{{WRAPPER}} .zig-configurator__select' => 'color: {{VALUE}};'],
            ]
        );

        $this->add_control(
            'select_placeholder_color',
            [
                'label'       => __('رنگ متن جای‌گیر', 'zig3d-widgets'),
                'type'        => Controls_Manager::COLOR,
                'selectors'   => ['{{WRAPPER}} .zig-configurator__select:invalid, {{WRAPPER}} .zig-configurator__select option[value=""]' => 'color: {{VALUE}};'],
                'description' => __('رنگِ متنِ «انتخاب کنید» تا وقتی چیزی انتخاب نشده.', 'zig3d-widgets'),
            ]
        );

        $this->add_group_control(
            Group_Control_Background::get_type(),
            [
                'name'     => 'select_background',
                'types'    => ['classic', 'gradient'],
                'selector' => '{{WRAPPER}} .zig-configurator__select-wrap',
            ]
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name'     => 'select_border',
                'selector' => '{{WRAPPER}} .zig-configurator__select-wrap',
            ]
        );

        $this->add_responsive_control(
            'select_radius',
            [
                'label'      => __('گردی گوشه', 'zig3d-widgets'),
                'type'       => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'selectors'  => ['{{WRAPPER}} .zig-configurator__select-wrap' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
            ]
        );

        $this->add_responsive_control(
            'select_padding',
            [
                'label'      => __('فاصلهٔ داخلی', 'zig3d-widgets'),
                'type'       => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em'],
                /*
                 * طرح ‎paddingLeft:14, paddingRight:14, paddingTop:15,
                 * paddingBottom:15‎ می‌دهد — چون آنجا فلش یک فرزندِ فلکسِ
                 * جداست، نه آیکونی که رویِ متن می‌نشیند. اینجا با ‎<select>‎
                 * واقعی، سمتی که فلش رویش می‌نشیند (فیزیکی چپ، چون در
                 * راست‌به‌چپ ‎inset-inline-end‎ یعنی چپ) باید فضای بیشتری
                 * داشته باشد تا متن زیرِ فلش نرود؛ سمتِ متن (راست) همان ۱۴
                 * طرح می‌ماند.
                 */
                'default'    => ['top' => '15', 'right' => '14', 'bottom' => '15', 'left' => '38', 'unit' => 'px', 'isLinked' => false],
                'selectors'  => ['{{WRAPPER}} .zig-configurator__select' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
            ]
        );

        $this->add_control(
            'chevron_heading',
            [
                'label'     => __('آیکون فلش', 'zig3d-widgets'),
                'type'      => Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        $this->add_responsive_control(
            'chevron_size',
            [
                'label'       => __('عرض', 'zig3d-widgets'),
                'type'        => Controls_Manager::SLIDER,
                'size_units'  => ['px'],
                'range'       => ['px' => ['min' => 6, 'max' => 40]],
                'default'     => ['size' => 12, 'unit' => 'px'],
                'selectors'   => ['{{WRAPPER}} .zig-configurator__chevron' => 'width: {{SIZE}}{{UNIT}};'],
                'description' => __('فقط عرض؛ ارتفاع خودکار و متناسب با نسبتِ خودِ آیکون است (این فلش مربع نیست).', 'zig3d-widgets'),
            ]
        );

        $this->add_control(
            'chevron_color',
            [
                'label'     => __('رنگ', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .zig-configurator__chevron' => 'color: {{VALUE}};',
                    '{{WRAPPER}} .zig-configurator__chevron svg, {{WRAPPER}} .zig-configurator__chevron svg *' => 'fill: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل: قیمت
     * =================================================================== */

    private function register_price_style_section(): void {
        $this->start_controls_section(
            'price_style_section',
            [
                'label' => __('قیمت', 'zig3d-widgets'),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_amount_style_controls('amount', '.zig-configurator__price');

        $this->end_controls_section();
    }

    /** تایپوگرافی، رنگ، جعبه و واحد پول برای بلوکِ قیمت — همان الگویِ ویجتِ قیمتِ محصول */
    private function add_amount_style_controls(string $prefix, string $selector): void {
        $box = '{{WRAPPER}} ' . $selector;

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'     => $prefix . '_typography',
                'selector' => $box,
            ]
        );

        $this->add_control(
            $prefix . '_color',
            [
                'label'     => __('رنگ', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [$box => 'color: {{VALUE}};'],
            ]
        );

        $this->add_group_control(
            Group_Control_Text_Shadow::get_type(),
            [
                'name'     => $prefix . '_text_shadow',
                'selector' => $box,
            ]
        );

        $this->add_control(
            $prefix . '_unit_heading',
            [
                'label'     => __('واحد پول', 'zig3d-widgets'),
                'type'      => Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'     => $prefix . '_unit_typography',
                'selector' => $box . ' .zig-configurator__unit',
            ]
        );

        $this->add_control(
            $prefix . '_unit_color',
            [
                'label'     => __('رنگ واحد', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [$box . ' .zig-configurator__unit' => 'color: {{VALUE}};'],
            ]
        );

        $this->add_responsive_control(
            $prefix . '_unit_gap',
            [
                'label'      => __('فاصلهٔ عدد تا واحد', 'zig3d-widgets'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px', 'em'],
                'range'      => ['px' => ['min' => 0, 'max' => 24]],
                'selectors'  => [$box => '--zig-unit-gap: {{SIZE}}{{UNIT}};'],
            ]
        );

        $this->add_control(
            $prefix . '_box_heading',
            [
                'label'     => __('جعبه', 'zig3d-widgets'),
                'type'      => Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        $this->add_group_control(
            Group_Control_Background::get_type(),
            [
                'name'     => $prefix . '_background',
                'types'    => ['classic', 'gradient'],
                'selector' => $box,
            ]
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name'     => $prefix . '_border',
                'selector' => $box,
            ]
        );

        $this->add_responsive_control(
            $prefix . '_radius',
            [
                'label'      => __('گردی گوشه', 'zig3d-widgets'),
                'type'       => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'selectors'  => [$box => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
            ]
        );

        $this->add_responsive_control(
            $prefix . '_padding',
            [
                'label'      => __('فاصلهٔ داخلی', 'zig3d-widgets'),
                'type'       => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em'],
                'selectors'  => [$box => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
            ]
        );

        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            [
                'name'     => $prefix . '_shadow',
                'selector' => $box,
            ]
        );
    }

    /* =====================================================================
     * استایل: موجودی
     * =================================================================== */

    private function register_stock_style_section(): void {
        $this->start_controls_section(
            'stock_style_section',
            [
                'label'     => __('بج موجودی', 'zig3d-widgets'),
                'tab'       => Controls_Manager::TAB_STYLE,
                'condition' => ['show_stock' => 'yes'],
            ]
        );

        $this->start_controls_tabs('stock_tabs');

        foreach ($this->stock_states() as $key => $state) {
            $this->start_controls_tab('stock_tab_' . $key, ['label' => $state['label']]);
            $this->add_stock_state_controls($key, $state);
            $this->end_controls_tab();
        }

        $this->end_controls_tabs();

        $this->end_controls_section();
    }

    private function add_stock_state_controls(string $key, array $state): void {
        $box = '{{WRAPPER}} .zig-configurator__stock--' . $key;

        $this->add_control(
            $key . '_color',
            [
                'label'     => __('رنگ متن', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'default'   => $state['color'],
                'selectors' => [$box => 'color: {{VALUE}};'],
            ]
        );

        $this->add_group_control(
            Group_Control_Background::get_type(),
            [
                'name'     => $key . '_background',
                'label'    => __('پس‌زمینه', 'zig3d-widgets'),
                'types'    => ['classic', 'gradient'],
                'selector' => $box,
            ]
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name'     => $key . '_border',
                'selector' => $box,
            ]
        );

        $this->add_responsive_control(
            $key . '_radius',
            [
                'label'      => __('گردی گوشه', 'zig3d-widgets'),
                'type'       => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'selectors'  => [$box => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
            ]
        );

        $this->add_responsive_control(
            $key . '_padding',
            [
                'label'      => __('فاصلهٔ داخلی', 'zig3d-widgets'),
                'type'       => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', 'rem'],
                'selectors'  => [$box => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'     => $key . '_typography',
                'selector' => $box,
            ]
        );

        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            [
                'name'     => $key . '_shadow',
                'selector' => $box,
            ]
        );
    }

    /* =====================================================================
     * استایل: زمان به‌روزرسانی
     * =================================================================== */

    private function register_updated_style_section(): void {
        $this->start_controls_section(
            'updated_style_section',
            [
                'label'     => __('زمان به‌روزرسانی', 'zig3d-widgets'),
                'tab'       => Controls_Manager::TAB_STYLE,
                'condition' => ['show_updated' => 'yes'],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'     => 'updated_typography',
                'selector' => '{{WRAPPER}} .zig-configurator__updated',
            ]
        );

        $this->add_control(
            'updated_color',
            [
                'label'     => __('رنگ', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => ['{{WRAPPER}} .zig-configurator__updated' => 'color: {{VALUE}};'],
            ]
        );

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل: دکمه‌ها
     * =================================================================== */

    private function register_buttons_style_section(): void {
        $this->start_controls_section(
            'buttons_style_section',
            [
                'label' => __('دکمه‌ها', 'zig3d-widgets'),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_responsive_control(
            'buttons_gap',
            [
                'label'      => __('فاصلهٔ بین دو دکمه', 'zig3d-widgets'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px', 'em'],
                'range'      => ['px' => ['min' => 0, 'max' => 40]],
                'default'    => ['size' => 24, 'unit' => 'px'],
                'selectors'  => ['{{WRAPPER}} .zig-configurator__actions' => 'gap: {{SIZE}}{{UNIT}};'],
            ]
        );

        $this->add_responsive_control(
            'buttons_padding',
            [
                'label'      => __('فاصلهٔ داخلی هر دکمه', 'zig3d-widgets'),
                'type'       => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em'],
                'default'    => ['top' => '15', 'right' => '22', 'bottom' => '15', 'left' => '22', 'unit' => 'px', 'isLinked' => false],
                'selectors'  => ['{{WRAPPER}} .zig-configurator__btn' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
            ]
        );

        $this->add_responsive_control(
            'buttons_radius',
            [
                'label'      => __('گردی گوشه', 'zig3d-widgets'),
                'type'       => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'selectors'  => ['{{WRAPPER}} .zig-configurator__btn' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
            ]
        );

        /*
         * دو بعد (کدام دکمه × کدام حالت) ولی فقط یک لایهٔ تب — المنتور
         * تب‌های تودرتو را پشتیبانی نمی‌کند. تایپوگرافیِ هر دکمه چون بینِ
         * دو حالتش مشترک است بیرون از تب‌ها می‌آید؛ چهار تبِ تخت رنگ و
         * جعبهٔ هر ترکیب را جدا نگه می‌دارند.
         */
        $this->add_control(
            'secondary_typography_heading',
            [
                'label'     => __('تایپوگرافی دکمهٔ اول', 'zig3d-widgets'),
                'type'      => Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'     => 'secondary_typography',
                'selector' => '{{WRAPPER}} .zig-configurator__btn--secondary',
            ]
        );

        $this->add_control(
            'primary_typography_heading',
            [
                'label'     => __('تایپوگرافی دکمهٔ دوم', 'zig3d-widgets'),
                'type'      => Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'     => 'primary_typography',
                'selector' => '{{WRAPPER}} .zig-configurator__btn--primary',
            ]
        );

        $this->start_controls_tabs('buttons_tabs');

        $this->start_controls_tab('secondary_normal_tab', ['label' => __('اول › عادی', 'zig3d-widgets')]);
        $this->add_button_state_controls('secondary', 'normal', '{{WRAPPER}} .zig-configurator__btn--secondary');
        $this->end_controls_tab();

        $this->start_controls_tab('secondary_hover_tab', ['label' => __('اول › هاور', 'zig3d-widgets')]);
        $this->add_button_state_controls(
            'secondary',
            'hover',
            '{{WRAPPER}} .zig-configurator__btn--secondary:hover, {{WRAPPER}} .zig-configurator__btn--secondary:focus-visible'
        );
        $this->end_controls_tab();

        $this->start_controls_tab('primary_normal_tab', ['label' => __('دوم › عادی', 'zig3d-widgets')]);
        $this->add_button_state_controls('primary', 'normal', '{{WRAPPER}} .zig-configurator__btn--primary');
        $this->end_controls_tab();

        $this->start_controls_tab('primary_hover_tab', ['label' => __('دوم › هاور', 'zig3d-widgets')]);
        $this->add_button_state_controls(
            'primary',
            'hover',
            '{{WRAPPER}} .zig-configurator__btn--primary:hover, {{WRAPPER}} .zig-configurator__btn--primary:focus-visible'
        );
        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->end_controls_section();
    }

    private function add_button_state_controls(string $key, string $state, string $selector): void {
        $this->add_control(
            $key . '_color_' . $state,
            [
                'label'     => __('رنگ متن', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [$selector => 'color: {{VALUE}};'],
            ]
        );

        $this->add_group_control(
            Group_Control_Background::get_type(),
            [
                'name'     => $key . '_background_' . $state,
                'label'    => __('پس‌زمینه', 'zig3d-widgets'),
                'types'    => ['classic', 'gradient'],
                'selector' => $selector,
            ]
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name'     => $key . '_border_' . $state,
                'selector' => $selector,
            ]
        );

        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            [
                'name'     => $key . '_shadow_' . $state,
                'selector' => $selector,
            ]
        );
    }

    /* =====================================================================
     * رندر
     * =================================================================== */

    protected function render(): void {
        $settings = $this->get_settings_for_display();

        if (!function_exists('wc_get_product')) {
            $this->notice(__('این ویجت به ووکامرس فعال نیاز دارد.', 'zig3d-widgets'));

            return;
        }

        $product = Price::resolve(absint($settings['product_id'] ?? 0));

        if (null === $product) {
            $this->notice(__('محصولی پیدا نشد. شناسهٔ محصول را وارد کنید یا ویجت را داخل صفحه/قالب محصول بگذارید.', 'zig3d-widgets'));

            return;
        }

        $fields     = Configurator::fields($product);
        $variations = [] !== $fields ? Configurator::variations($product) : [];
        $can_select = [] !== $fields && [] !== $variations;

        $display = $this->resolve_display($product, $variations);

        if (!$display['has_price']) {
            $this->notice(__('این محصول قیمت ندارد.', 'zig3d-widgets'));

            return;
        }

        $this->render_card($settings, $fields, $variations, $display, $can_select);
    }

    /**
     * وضعیتِ پیش‌فرضِ نمایش — پیش از انتخاب.
     *
     * برای محصولِ متغیر، دقیقاً همان واریانتی که کمترین قیمت را دارد
     * مبنا می‌شود؛ اینطوری قیمت، بجِ موجودی و زمانِ به‌روزرسانی هر سه از
     * یک ترکیبِ واقعی می‌آیند، نه سه منبعِ جدا که ممکن است به‌هم نخورند.
     *
     * @return array{has_price:bool,current:string,stock_state:string,updated_at:int}
     */
    private function resolve_display(\WC_Product $product, array $variations): array {
        if ([] !== $variations) {
            $cheapest = null;

            foreach ($variations as $row) {
                if (null === $cheapest || (float) $row['price'] < (float) $cheapest['price']) {
                    $cheapest = $row;
                }
            }

            return [
                'has_price'   => true,
                'current'     => $cheapest['price'],
                'stock_state' => $cheapest['stock_state'],
                'updated_at'  => (int) $cheapest['updated_at'],
            ];
        }

        $price = Price::data($product, 'min');

        if (!$price['has_price']) {
            return ['has_price' => false, 'current' => '', 'stock_state' => Stock::OUT_OF_STOCK, 'updated_at' => 0];
        }

        $stock = Stock::state($product, ['backorder' => true, 'lowstock' => false, 'aggregate' => false]);

        return [
            'has_price'   => true,
            'current'     => $price['current'],
            'stock_state' => $stock['state'],
            'updated_at'  => Rate_Price::updated_at($product->get_id()),
        ];
    }

    private function render_card(array $settings, array $fields, array $variations, array $display, bool $can_select): void {
        $persian  = 'yes' === ($settings['persian_digits'] ?? 'yes');
        $currency = Price::currency((string) ($settings['currency_text'] ?? ''));

        echo '<div class="zig-configurator">';

        if ($can_select) {
            $this->render_payload($settings, $fields, $variations, $display, $persian);
        }

        /*
         * جعبهٔ تیرهٔ کارت فقط دور عنوان/کشوها/قیمت را می‌گیرد؛ طبقِ طرح
         * دو دکمهٔ پایین بیرونِ همین جعبه‌اند، نه داخلش.
         */
        echo '<div class="zig-configurator__card">';

        $this->render_header($settings);

        if ($can_select) {
            $this->render_fields($settings, $fields);
        }

        echo '<div class="zig-configurator__bottom">';
        $this->render_price($settings, $display, $persian, $currency);
        echo '<div class="zig-configurator__side">';
        $this->render_stock($settings, $display, $can_select);
        $this->render_updated($settings, $display, $variations, $persian);
        echo '</div></div>';

        echo '</div>';

        $this->render_actions($settings);

        echo '</div>';
    }

    private function render_header(array $settings): void {
        if ('yes' !== ($settings['show_header'] ?? 'yes')) {
            return;
        }

        $title    = trim((string) ($settings['title'] ?? ''));
        $subtitle = trim((string) ($settings['subtitle'] ?? ''));

        if ('' === $title && '' === $subtitle) {
            return;
        }

        echo '<div class="zig-configurator__header">';

        if ('' !== $title) {
            printf('<h3 class="zig-configurator__title">%s</h3>', esc_html($title));
        }

        if ('' !== $subtitle) {
            printf('<p class="zig-configurator__subtitle">%s</p>', esc_html($subtitle));
        }

        echo '</div>';
    }

    private function render_fields(array $settings, array $fields): void {
        $placeholder = trim((string) ($settings['placeholder_text'] ?? ''));

        if ('' === $placeholder) {
            $placeholder = __('انتخاب کنید', 'zig3d-widgets');
        }

        $chevron = $this->render_chevron($settings);

        echo '<div class="zig-configurator__fields">';

        foreach ($fields as $field) {
            $id = 'zig-cfg-' . $this->get_id() . '-' . sanitize_html_class($field['key']);

            printf('<div class="zig-configurator__field">');
            printf('<label class="zig-configurator__label" for="%s">%s</label>', esc_attr($id), esc_html($field['label']));
            echo '<span class="zig-configurator__select-wrap">';
            printf('<select class="zig-configurator__select" id="%s" data-key="%s">', esc_attr($id), esc_attr($field['key']));
            printf('<option value="" selected disabled>%s</option>', esc_html($placeholder));

            foreach ($field['options'] as $option) {
                printf('<option value="%s">%s</option>', esc_attr($option['value']), esc_html($option['label']));
            }

            echo '</select>';

            if ('' !== $chevron) {
                printf('<span class="zig-configurator__chevron" aria-hidden="true">%s</span>', $chevron); // phpcs:ignore WordPress.Security.EscapeOutput -- خروجی Icons_Manager یا SVGی طرح
            }

            echo '</span></div>';
        }

        echo '</div>';
    }

    private function render_chevron(array $settings): string {
        $icon = $settings['chevron_icon'] ?? [];

        if (!empty($icon['value'])) {
            ob_start();
            Icons_Manager::render_icon($icon, ['aria-hidden' => 'true']);

            return (string) ob_get_clean();
        }

        if ('yes' !== ($settings['design_icons'] ?? 'yes')) {
            return '';
        }

        return isset(self::DESIGN_ICONS['chevron_icon'])
            ? Design_Icons::get(self::DESIGN_ICONS['chevron_icon'])
            : '';
    }

    private function render_price(array $settings, array $display, bool $persian, string $currency): void {
        $free_text = trim((string) ($settings['free_text'] ?? ''));
        $is_free   = 0.0 === (float) $display['current'];

        echo '<div class="zig-configurator__price">';

        if ($is_free && '' !== $free_text) {
            printf('<bdi class="zig-configurator__value"><span class="zig-configurator__free">%s</span></bdi>', esc_html($free_text));
        } else {
            echo $this->amount_html($display['current'], $currency, $persian); // phpcs:ignore WordPress.Security.EscapeOutput -- در amount_html اسکیپ شده
        }

        echo '</div>';
    }

    /**
     * عدد + واحد پول.
     *
     * ‎<bdi>‎ اجباری است — همان دلیلِ ویجتِ قیمتِ محصول: بدونش الگوریتمِ
     * دوجهته‌ی یونیکد می‌تواند عدد و واحد را در متنِ راست‌به‌چپ جابه‌جا کند.
     */
    private function amount_html(string $raw, string $unit, bool $persian): string {
        $amount = Price::format($raw);

        if ($persian) {
            $amount = Price::persian($amount);
        }

        $html = '<bdi class="zig-configurator__value"><span class="zig-configurator__amount">'
            . esc_html($amount) . '</span>';

        if ('' !== $unit) {
            $html .= '<span class="zig-configurator__unit">' . esc_html($unit) . '</span>';
        }

        return $html . '</bdi>';
    }

    /**
     * بجِ موجودی.
     *
     * وقتی کشوها فعالند (‎$can_select‎)، جعبه همیشه چاپ می‌شود — حتی اگر
     * برچسبِ وضعیتِ پیش‌فرض خالی گذاشته شده — و فقط با ‎hidden‎ پنهان
     * می‌ماند؛ وگرنه اسکریپت هیچ عنصری برایِ نشان‌دادنِ بجِ واریانتی که
     * برچسبش پر است در اختیار نمی‌داشت. برایِ محصولِ ساده که هیچ‌وقت عوض
     * نمی‌شود، برچسبِ خالی یعنی همان چیزی که بوده: هیچ.
     */
    private function render_stock(array $settings, array $display, bool $can_select): void {
        if ('yes' !== ($settings['show_stock'] ?? 'yes')) {
            return;
        }

        $bucket = $this->stock_bucket($display['stock_state']);
        $label  = trim((string) ($settings['text_' . $bucket] ?? ''));

        if ('' === $label && !$can_select) {
            return;
        }

        printf(
            '<div class="zig-configurator__stock zig-configurator__stock--%s"%s><span class="zig-configurator__stock-label">%s</span><span class="zig-configurator__stock-dot" aria-hidden="true"></span></div>',
            esc_attr($bucket),
            '' === $label ? ' hidden' : '',
            esc_html($label)
        );
    }

    /**
     * خط زمانِ به‌روزرسانی.
     *
     * وقتی نه پیش‌فرض و نه هیچ واریانتی زمانی برای نمایش دارند (محصول اصلاً
     * نرخ‌محور نیست)، این بخش کلاً چاپ نمی‌شود — نه یک برچسبِ بی‌مقدار.
     * اگر پیش‌فرض زمان ندارد ولی دست‌کم یک واریانت دارد، عنصر با ‎hidden‎
     * می‌آید تا اسکریپت با انتخابِ همان ترکیب بتواند نمایانش کند.
     */
    private function render_updated(array $settings, array $display, array $variations, bool $persian): void {
        if ('yes' !== ($settings['show_updated'] ?? 'yes')) {
            return;
        }

        $default_value = $this->updated_value((int) ($display['updated_at'] ?? 0), $persian);

        if ('' === $default_value && !$this->any_rate_based($variations)) {
            return;
        }

        $label = trim((string) ($settings['updated_label'] ?? ''));

        printf('<div class="zig-configurator__updated"%s>', '' === $default_value ? ' hidden' : '');

        if ('' !== $label) {
            printf('<span class="zig-configurator__updated-label">%s</span> ', esc_html($label));
        }

        printf('<span class="zig-configurator__updated-value">%s</span>', esc_html($default_value));

        echo '</div>';
    }

    private function any_rate_based(array $variations): bool {
        foreach ($variations as $row) {
            if (((int) $row['updated_at']) > 0) {
                return true;
            }
        }

        return false;
    }

    private function updated_value(int $timestamp, bool $persian): string {
        $text = Rate_Price::format($timestamp);

        if ('' === $text) {
            return '';
        }

        return $persian ? Price::persian($text) : $text;
    }

    private function render_actions(array $settings): void {
        $has_secondary = '' !== trim((string) ($settings['secondary_text'] ?? ''));
        $has_primary   = '' !== trim((string) ($settings['primary_text'] ?? ''));

        if (!$has_secondary && !$has_primary) {
            return;
        }

        echo '<div class="zig-configurator__actions">';

        if ($has_secondary) {
            $this->render_button($settings, 'secondary');
        }

        if ($has_primary) {
            $this->render_button($settings, 'primary');
        }

        echo '</div>';
    }

    /**
     * یکی از دو دکمه.
     *
     * پیوندِ واقعی وقتی آدرسی داده شده — ‎add_link_attributes()‎ خودش
     * ‎target‎/‎rel‎/ویژگی‌های دلخواه را می‌سازد. بدونِ آدرس، ‎<button>‎ی
     * غیرفعال از نظرِ ناوبری می‌آید تا رفتارِ کلیک بعداً (وقتی مشخص شود)
     * رویش سوار شود — نه یک ‎<a>‎ بدونِ ‎href‎ که نه فوکوس می‌گیرد نه لینکی
     * برایِ صفحه‌خوان است.
     */
    private function render_button(array $settings, string $key): void {
        $text       = trim((string) ($settings[$key . '_text'] ?? ''));
        $render_key = 'btn_' . $key;

        $this->add_render_attribute($render_key, 'class', ['zig-configurator__btn', 'zig-configurator__btn--' . $key]);

        $tag = $this->apply_link($settings, $render_key, 'button', $key . '_link');

        if ('button' === $tag) {
            $this->add_render_attribute($render_key, 'type', 'button');
        }

        printf('<%s %s>', $tag, $this->get_render_attribute_string($render_key)); // phpcs:ignore WordPress.Security.EscapeOutput -- تگ ثابت، ویژگی‌ها از رندرِ المنتور
        echo esc_html($text);
        printf('</%s>', $tag); // phpcs:ignore WordPress.Security.EscapeOutput -- تگ ثابت
    }

    /**
     * دادهٔ واریانت‌ها برایِ سوییچِ سمتِ کلاینت.
     *
     * متن‌های آمادهٔ نمایش (نه اعداد خام) تعبیه می‌شوند — قیمتِ فرمت‌شده،
     * برچسبِ فارسیِ موجودی، متنِ زمانِ به‌روزرسانی — تا ‎zig3d-configurator.js‎
     * هیچ منطقِ نمایشی را دوباره نسازد و با سمتِ سرور واگرا نشود.
     */
    private function render_payload(array $settings, array $fields, array $variations, array $display, bool $persian): void {
        $keys = array_map(static fn(array $field): string => $field['key'], $fields);

        $rows = [];

        foreach ($variations as $row) {
            $bucket = $this->stock_bucket($row['stock_state']);

            $rows[] = [
                'id'      => (int) $row['id'],
                'attrs'   => (object) $row['attributes'],
                'amount'  => $this->plain_amount($row['price'], $persian),
                'bucket'  => $bucket,
                'stock'   => trim((string) ($settings['text_' . $bucket] ?? '')),
                'updated' => $this->updated_value((int) $row['updated_at'], $persian),
            ];
        }

        $default_bucket = $this->stock_bucket($display['stock_state']);

        $payload = [
            'keys'    => $keys,
            'rows'    => $rows,
            'default' => [
                'amount'  => $this->plain_amount($display['current'], $persian),
                'bucket'  => $default_bucket,
                'stock'   => trim((string) ($settings['text_' . $default_bucket] ?? '')),
                'updated' => $this->updated_value((int) ($display['updated_at'] ?? 0), $persian),
            ],
        ];

        $json = wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        printf(
            '<script type="application/json" class="zig-configurator__data">%s</script>',
            str_replace('</script', '<\/script', false === $json ? '{}' : $json) // phpcs:ignore WordPress.Security.EscapeOutput -- JSONِ ساختاریافته، نه HTML
        );
    }

    private function plain_amount(string $raw, bool $persian): string {
        $amount = Price::format($raw);

        return $persian ? Price::persian($amount) : $amount;
    }

    /** پیام راهنما، فقط داخل ادیتور */
    private function notice(string $message): void {
        $editing = class_exists('\Elementor\Plugin')
            && isset(\Elementor\Plugin::$instance->editor)
            && \Elementor\Plugin::$instance->editor->is_edit_mode();

        if (!$editing) {
            return;
        }

        printf('<div class="zig-price__notice">%s</div>', esc_html($message));
    }
}
