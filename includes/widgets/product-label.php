<?php
namespace Zig3d_Widgets\Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Background;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;
use Zig3d_Widgets\Markup;
use Zig3d_Widgets\Plugin;
use Zig3d_Widgets\Price;
use Zig3d_Widgets\Schema_Store;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * لیبل محصول — یک نشانِ کوچکِ قرصی که روی گوشهٔ کارتِ محصول می‌نشیند.
 *
 *     span.zig-label.zig-label--discount    «۱۰٪ تخفیف»
 *     span.zig-label.zig-label--bestseller  «پرفروش» + آیکونِ شعله
 *
 * دو نوع، دو شرطِ کاملاً متفاوت برای «نمایش داده شود یا نه»:
 *
 *   • تخفیف: از منطقِ واقعیِ قیمتِ ووکامرس می‌آید (‎Price::data()‎ — همان
 *     چیزی که ویجتِ «قیمت محصول» برای بجِ خودش می‌خواند)، نه از چیزی که
 *     مدیر دستی تنظیم کرده باشد. محصولِ بدونِ تخفیفِ واقعی، لیبل نمی‌گیرد.
 *   • پرفروش: برخلافِ نامش، از آمارِ فروش نمی‌آید — صرفاً «این محصول در
 *     یکی از دسته‌هایی هست که مدیر از پنل انتخاب کرده». ساده و دستی، چون
 *     دقیقاً همین را خواسته شده: مدیر مشخص می‌کند کدام دسته‌ها «پرفروش»
 *     حساب می‌شوند، نه الگوریتم.
 */
final class Product_Label extends Widget_Base {

    public function get_name(): string {
        return 'zig3d-product-label';
    }

    public function get_title(): string {
        return __('لیبل محصول', 'zig3d-widgets');
    }

    public function get_icon(): string {
        return 'eicon-price-list';
    }

    public function get_categories(): array {
        return [Plugin::CATEGORY];
    }

    public function get_keywords(): array {
        return ['label', 'badge', 'discount', 'sale', 'bestseller', 'لیبل', 'برچسب', 'تخفیف', 'پرفروش'];
    }

    public function get_style_depends(): array {
        return ['zig3d-widgets'];
    }

    protected function register_controls(): void {
        $this->register_product_section();
        $this->register_type_section();
        $this->register_discount_section();
        $this->register_bestseller_section();
        $this->register_discount_style_section();
        $this->register_bestseller_style_section();
    }

    /* =====================================================================
     * محتوا › محصول
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

        $this->end_controls_section();
    }

    /* =====================================================================
     * محتوا › نوع لیبل
     * =================================================================== */

    private function register_type_section(): void {
        $this->start_controls_section(
            'type_section',
            ['label' => __('نوع لیبل', 'zig3d-widgets')]
        );

        $this->add_control(
            'label_type',
            [
                'label'   => __('نوع', 'zig3d-widgets'),
                'type'    => Controls_Manager::SELECT,
                'default' => 'discount',
                'options' => [
                    'discount'   => __('درصد تخفیف', 'zig3d-widgets'),
                    'bestseller' => __('پرفروش', 'zig3d-widgets'),
                ],
            ]
        );

        $this->end_controls_section();
    }

    /* =====================================================================
     * محتوا › تخفیف
     * =================================================================== */

    private function register_discount_section(): void {
        $this->start_controls_section(
            'discount_section',
            [
                'label'     => __('تخفیف', 'zig3d-widgets'),
                'condition' => ['label_type' => 'discount'],
            ]
        );

        $this->add_control(
            'label_template',
            [
                'label'       => __('قالب متن', 'zig3d-widgets'),
                'type'        => Controls_Manager::TEXT,
                'default'     => '{value}% تخفیف',
                'description' => __('‏{value} جای عددِ درصد می‌نشیند.', 'zig3d-widgets'),
            ]
        );

        $this->add_control(
            'persian_digits',
            [
                'label'        => __('ارقام فارسی', 'zig3d-widgets'),
                'type'         => Controls_Manager::SWITCHER,
                'default'      => 'yes',
                'return_value' => 'yes',
            ]
        );

        $this->add_control(
            'min_percent',
            [
                'label'       => __('حداقل درصدِ نمایش', 'zig3d-widgets'),
                'type'        => Controls_Manager::NUMBER,
                'min'         => 0,
                'max'         => 100,
                'default'     => 0,
                'description' => __('اگر درصدِ تخفیفِ واقعیِ محصول کمتر از این عدد باشد، لیبل در سایت نمایش داده نمی‌شود.', 'zig3d-widgets'),
            ]
        );

        $this->end_controls_section();
    }

    /* =====================================================================
     * محتوا › پرفروش
     * =================================================================== */

    private function register_bestseller_section(): void {
        $this->start_controls_section(
            'bestseller_section',
            [
                'label'     => __('پرفروش', 'zig3d-widgets'),
                'condition' => ['label_type' => 'bestseller'],
            ]
        );

        $this->add_control(
            'categories',
            [
                'label'       => __('دسته‌های «پرفروش»', 'zig3d-widgets'),
                'type'        => Controls_Manager::SELECT2,
                'multiple'    => true,
                'options'     => $this->category_options(),
                'label_block' => true,
                'description' => __('این لیبل فقط برای محصولاتِ همین دسته‌ها نمایش داده می‌شود — نه بر اساسِ آمارِ فروش، بلکه دقیقاً بر اساسِ همین انتخاب.', 'zig3d-widgets'),
            ]
        );

        $this->add_control(
            'bestseller_text',
            [
                'label'   => __('متن', 'zig3d-widgets'),
                'type'    => Controls_Manager::TEXT,
                'default' => __('پرفروش', 'zig3d-widgets'),
            ]
        );

        $this->add_control(
            'show_icon',
            [
                'label'        => __('نمایش آیکونِ شعله', 'zig3d-widgets'),
                'type'         => Controls_Manager::SWITCHER,
                'default'      => 'yes',
                'return_value' => 'yes',
            ]
        );

        $this->end_controls_section();
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

    /* =====================================================================
     * استایل › تخفیف
     * =================================================================== */

    private function register_discount_style_section(): void {
        $this->start_controls_section(
            'discount_style_section',
            [
                'label'     => __('ظاهر (تخفیف)', 'zig3d-widgets'),
                'tab'       => Controls_Manager::TAB_STYLE,
                'condition' => ['label_type' => 'discount'],
            ]
        );

        $this->register_label_style_controls('discount');

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل › پرفروش
     * =================================================================== */

    private function register_bestseller_style_section(): void {
        $this->start_controls_section(
            'bestseller_style_section',
            [
                'label'     => __('ظاهر (پرفروش)', 'zig3d-widgets'),
                'tab'       => Controls_Manager::TAB_STYLE,
                'condition' => ['label_type' => 'bestseller'],
            ]
        );

        $this->register_label_style_controls('bestseller');

        $this->add_control(
            'icon_heading',
            [
                'label'     => __('آیکون', 'zig3d-widgets'),
                'type'      => Controls_Manager::HEADING,
                'separator' => 'before',
                'condition' => ['show_icon' => 'yes'],
            ]
        );

        /*
         * بدونِ کنترلِ رنگِ جداگانه: آیکون با ‎stroke="currentColor"‎ رسم
         * می‌شود، پس همیشه هم‌رنگِ متنِ همان لیبل است — دقیقاً مثلِ نشانِ
         * وضعیتِ موجودی. یک رنگِ کمتر برای هماهنگ‌نگه‌داشتن، نه یک تنظیمِ
         * کمتر برای کاربر.
         */
        $this->add_responsive_control(
            'icon_size',
            [
                'label'      => __('اندازه', 'zig3d-widgets'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range'      => ['px' => ['min' => 8, 'max' => 32]],
                'default'    => ['size' => 14, 'unit' => 'px'],
                'condition'  => ['show_icon' => 'yes'],
                'selectors'  => ['{{WRAPPER}} .zig-label__icon' => '--zig-label-icon-size: {{SIZE}}{{UNIT}};'],
            ]
        );

        $this->end_controls_section();
    }

    /** کنترل‌های استایلِ مشترکِ هر دو نوع، با پیشوندِ جدا تا مقادیرشان مستقل بماند */
    private function register_label_style_controls(string $prefix): void {
        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'     => $prefix . '_typography',
                'selector' => '{{WRAPPER}} .zig-label',
            ]
        );

        $this->add_control(
            $prefix . '_color',
            [
                'label'     => __('رنگ متن', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => ['{{WRAPPER}} .zig-label' => 'color: {{VALUE}};'],
            ]
        );

        $this->add_group_control(
            Group_Control_Background::get_type(),
            [
                'name'     => $prefix . '_background',
                'types'    => ['classic', 'gradient'],
                'selector' => '{{WRAPPER}} .zig-label',
            ]
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name'     => $prefix . '_border',
                'selector' => '{{WRAPPER}} .zig-label',
            ]
        );

        $this->add_responsive_control(
            $prefix . '_radius',
            [
                'label'      => __('گردی گوشه', 'zig3d-widgets'),
                'type'       => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'selectors'  => ['{{WRAPPER}} .zig-label' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
            ]
        );

        $this->add_responsive_control(
            $prefix . '_padding',
            [
                'label'      => __('فاصلهٔ داخلی', 'zig3d-widgets'),
                'type'       => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em'],
                'selectors'  => ['{{WRAPPER}} .zig-label' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'],
            ]
        );

        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            [
                'name'     => $prefix . '_shadow',
                'selector' => '{{WRAPPER}} .zig-label',
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

        if ('bestseller' === ($settings['label_type'] ?? 'discount')) {
            $this->render_bestseller($settings, $product);

            return;
        }

        $this->render_discount($settings, $product);
    }

    private function render_discount(array $settings, \WC_Product $product): void {
        $price = Price::data($product);

        if (!$price['has_price'] || $price['is_range'] || !$price['on_sale'] || $price['percent'] <= 0) {
            $this->notice(__('این محصول تخفیفِ فعالی ندارد؛ در سایت چیزی نمایش داده نمی‌شود.', 'zig3d-widgets'));

            return;
        }

        $min_percent = max(0, (int) ($settings['min_percent'] ?? 0));

        if ($price['percent'] < $min_percent) {
            $this->notice(__('درصدِ تخفیف کمتر از حداقلِ تنظیم‌شده است؛ در سایت چیزی نمایش داده نمی‌شود.', 'zig3d-widgets'));

            return;
        }

        $value = (string) $price['percent'];

        if ('yes' === ($settings['persian_digits'] ?? 'yes')) {
            $value = Price::persian($value);
        }

        $template = (string) ($settings['label_template'] ?? '{value}% تخفیف');
        $text     = str_replace('{value}', $value, $template);

        if (!Markup::filled($text)) {
            return;
        }

        printf(
            '<span class="zig-label zig-label--discount">%s</span>',
            esc_html($text)
        );
    }

    private function render_bestseller(array $settings, \WC_Product $product): void {
        $category_ids = array_filter(array_map('absint', (array) ($settings['categories'] ?? [])));

        if (empty($category_ids)) {
            $this->notice(__('برای نمایشِ این لیبل، حداقل یک دسته را از بخشِ محتوا انتخاب کنید.', 'zig3d-widgets'));

            return;
        }

        if (!$this->product_in_categories($product, $category_ids)) {
            $this->notice(__('این محصول در هیچ‌کدام از دسته‌های انتخاب‌شده نیست؛ در سایت چیزی نمایش داده نمی‌شود.', 'zig3d-widgets'));

            return;
        }

        $text = (string) ($settings['bestseller_text'] ?? __('پرفروش', 'zig3d-widgets'));

        if (!Markup::filled($text)) {
            return;
        }

        $icon = '';

        if ('yes' === ($settings['show_icon'] ?? 'yes')) {
            $icon = '<span class="zig-label__icon" aria-hidden="true">' . Markup::svg_icon('flame', '', 14) . '</span>';
        }

        printf(
            '<span class="zig-label zig-label--bestseller">%s%s</span>',
            esc_html($text),
            $icon // phpcs:ignore WordPress.Security.EscapeOutput -- Markup::svg_icon خودش امن است
        );
    }

    /** آیا محصول در یکی از این دسته‌هاست؟ */
    private function product_in_categories(\WC_Product $product, array $term_ids): bool {
        $terms = get_the_terms($product->get_id(), Schema_Store::TAXONOMY);

        if (!is_array($terms)) {
            return false;
        }

        foreach ($terms as $term) {
            if ($term instanceof \WP_Term && in_array($term->term_id, $term_ids, true)) {
                return true;
            }
        }

        return false;
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
