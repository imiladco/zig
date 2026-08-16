<?php
namespace Zig3d_Widgets\Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;
use Zig3d_Widgets\Markup;
use Zig3d_Widgets\Plugin;
use Zig3d_Widgets\Price;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * توضیحات — یک بخشِ متنیِ عنوان‌دار، از هر جایی که «توضیح»ِ محتوایِ جاری
 * ممکن است باشد: توضیحاتِ محصولِ ووکامرس، محتوایِ نوشته، خلاصهٔ نوشته، یا
 * یک فیلدِ سفارشیِ متا (هر پست‌تایپی — JetEngine یا هرچی).
 *
 * جانشینِ ویجتیِ بخشِ «معرفیِ محصول»یِ shortcodeِ قدیمیِ
 * ‎product_description‎ (که فقط رویِ صفحهٔ محصول و فقط رویِ توضیحاتِ
 * ووکامرس کار می‌کرد)، حالا به‌عنوانِ ویجتِ المنتوریِ عمومی — قابلِ
 * جاگذاری در هر Loop/قالب/پستی، نه فقط محصول.
 *
 *     div.zig-description
 *       header.zig-description__head        عنوان + آیکونِ اختیاری
 *         img.zig-description__icon
 *         h2.zig-description__heading
 *       div.zig-description__body            HTMLِ فیلترشده/پاک‌سازی‌شده
 *
 * چرا منبعِ «خودکار» پیش‌فرض است: اکثرِ استفاده‌ها یا رویِ صفحهٔ محصول‌اند
 * یا رویِ نوشته/برگه — و در هر دو حالت یک منبعِ بدیهی وجود دارد. کاربری
 * که می‌خواهد صراحتاً یک منبعِ دیگر (مثلاً یک فیلدِ سفارشی) را نشان دهد،
 * می‌تواند صریحاً انتخاب کند.
 */
final class Description extends Widget_Base {

    use Traits\Box;

    public function get_name(): string {
        return 'zig3d-description';
    }

    public function get_title(): string {
        return __('توضیحات', 'zig3d-widgets');
    }

    public function get_icon(): string {
        return 'eicon-text';
    }

    public function get_categories(): array {
        return [Plugin::CATEGORY];
    }

    public function get_keywords(): array {
        return ['description', 'content', 'excerpt', 'toc', 'توضیحات', 'محتوا', 'معرفی'];
    }

    public function get_style_depends(): array {
        return ['zig3d-widgets'];
    }

    public function has_widget_inner_wrapper(): bool {
        return false;
    }

    protected function register_controls(): void {
        $this->register_source_section();
        $this->register_heading_section();
        $this->register_container_style_section();
        $this->register_heading_style_section();
        $this->register_body_style_section();
    }

    /* =====================================================================
     * منبعِ محتوا
     * =================================================================== */

    private function register_source_section(): void {
        $this->start_controls_section(
            'source_section',
            ['label' => __('منبعِ محتوا', 'zig3d-widgets')]
        );

        $this->add_control(
            'source',
            [
                'label'   => __('منبع', 'zig3d-widgets'),
                'type'    => Controls_Manager::SELECT,
                'options' => [
                    'auto'                => __('خودکار (توضیحاتِ محصول، وگرنه محتوایِ نوشته)', 'zig3d-widgets'),
                    'product_description' => __('توضیحاتِ محصول (ووکامرس)', 'zig3d-widgets'),
                    'post_content'        => __('محتوایِ نوشته', 'zig3d-widgets'),
                    'post_excerpt'        => __('خلاصهٔ نوشته', 'zig3d-widgets'),
                    'custom_field'        => __('فیلدِ سفارشی (متا)', 'zig3d-widgets'),
                ],
                'default' => 'auto',
            ]
        );

        $this->add_control(
            'custom_field_key',
            [
                'label'       => __('کلیدِ فیلدِ متا', 'zig3d-widgets'),
                'type'        => Controls_Manager::TEXT,
                'default'     => '',
                'condition'   => ['source' => 'custom_field'],
                'description' => __('نامِ فیلدِ سفارشیِ رویِ پستِ جاری (مثلاً یک فیلدِ متن/WYSIWYGِ JetEngine).', 'zig3d-widgets'),
            ]
        );

        $this->add_control(
            'product_id',
            [
                'label'       => __('شناسهٔ محصول (دستی)', 'zig3d-widgets'),
                'type'        => Controls_Manager::NUMBER,
                'min'         => 0,
                'default'     => 0,
                'condition'   => ['source' => ['auto', 'product_description']],
                'description' => __('خالی یا صفر یعنی محصولِ جاری (یا محصولِ حلقه، در Loop Grid).', 'zig3d-widgets'),
            ]
        );

        $this->add_control(
            'render_filters',
            [
                'label'       => __('اجرایِ فیلترهایِ محتوا', 'zig3d-widgets'),
                'type'        => Controls_Manager::SWITCHER,
                'default'     => 'yes',
                'description' => __('شورت‌کد/امبد/پاراگراف‌بندیِ خودکار را رویِ متن اجرا می‌کند (فیلترِ the_content).', 'zig3d-widgets'),
            ]
        );

        $this->end_controls_section();
    }

    /* =====================================================================
     * عنوان
     * =================================================================== */

    private function register_heading_section(): void {
        $this->start_controls_section(
            'heading_section',
            ['label' => __('عنوان', 'zig3d-widgets')]
        );

        $this->add_control('show_heading', ['label' => __('نمایشِ عنوان', 'zig3d-widgets'), 'type' => Controls_Manager::SWITCHER, 'default' => 'yes']);
        $this->add_control('heading_text', ['label' => __('متنِ عنوان', 'zig3d-widgets'), 'type' => Controls_Manager::TEXT, 'default' => __('توضیحات', 'zig3d-widgets'), 'condition' => ['show_heading' => 'yes']]);
        $this->add_control('append_title', ['label' => __('افزودنِ عنوانِ نوشته/محصول', 'zig3d-widgets'), 'type' => Controls_Manager::SWITCHER, 'default' => '', 'condition' => ['show_heading' => 'yes'], 'description' => __('مثلاً «توضیحات» می‌شود «توضیحاتِ نامِ‌محصول».', 'zig3d-widgets')]);
        $this->add_control('show_icon', ['label' => __('نمایشِ آیکون', 'zig3d-widgets'), 'type' => Controls_Manager::SWITCHER, 'default' => '', 'condition' => ['show_heading' => 'yes']]);
        $this->add_control('heading_icon', ['label' => __('تصویرِ آیکون', 'zig3d-widgets'), 'type' => Controls_Manager::MEDIA, 'default' => ['url' => ''], 'condition' => ['show_heading' => 'yes', 'show_icon' => 'yes']]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل
     * =================================================================== */

    private function register_container_style_section(): void {
        $this->start_controls_section(
            'container_style_section',
            ['label' => __('کادر', 'zig3d-widgets'), 'tab' => Controls_Manager::TAB_STYLE]
        );

        $this->add_box_style_tabs('container', '.zig-description', '.zig-description');

        $this->end_controls_section();
    }

    private function register_heading_style_section(): void {
        $this->start_controls_section(
            'heading_style_section',
            ['label' => __('عنوان', 'zig3d-widgets'), 'tab' => Controls_Manager::TAB_STYLE, 'condition' => ['show_heading' => 'yes']]
        );

        $this->add_group_control(Group_Control_Typography::get_type(), ['name' => 'heading_typography', 'selector' => '{{WRAPPER}} .zig-description__heading']);
        $this->add_control('heading_color', ['label' => __('رنگِ عنوان', 'zig3d-widgets'), 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .zig-description__heading' => 'color: {{VALUE}};']]);
        $this->add_responsive_control('icon_size', ['label' => __('اندازهٔ آیکون', 'zig3d-widgets'), 'type' => Controls_Manager::SLIDER, 'size_units' => ['px'], 'range' => ['px' => ['min' => 12, 'max' => 64]], 'default' => ['size' => 24, 'unit' => 'px'], 'condition' => ['show_icon' => 'yes'], 'selectors' => ['{{WRAPPER}} .zig-description__icon' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};']]);
        $this->add_responsive_control('heading_gap', ['label' => __('فاصلهٔ آیکون تا متنِ عنوان', 'zig3d-widgets'), 'type' => Controls_Manager::SLIDER, 'range' => ['px' => ['min' => 0, 'max' => 40]], 'condition' => ['show_icon' => 'yes'], 'selectors' => ['{{WRAPPER}} .zig-description__head' => 'gap: {{SIZE}}{{UNIT}};']]);
        $this->add_responsive_control('heading_spacing', ['label' => __('فاصلهٔ عنوان تا متن', 'zig3d-widgets'), 'type' => Controls_Manager::SLIDER, 'range' => ['px' => ['min' => 0, 'max' => 80]], 'default' => ['size' => 16, 'unit' => 'px'], 'selectors' => ['{{WRAPPER}} .zig-description__head' => 'margin-block-end: {{SIZE}}{{UNIT}};']]);

        $this->end_controls_section();
    }

    /**
     * متنِ بدنه از ‎the_content‎/فیلدِ سفارشی می‌آید — یعنی هر تگی از ‎<p>‎ تا
     * ‎<h3>‎ تا ‎<blockquote>‎ ممکن است داخلش باشد. یک ‎body_typography‎ی
     * تک برایِ همه کافی نیست: تیترهایِ داخلِ متن اندازه/وزنِ پیش‌فرضِ
     * مرورگر را دارند و پاراگراف/فهرست/نقل‌قول هرکدام معمولاً ظاهرِ
     * مستقلِ خودشان را می‌خواهند — دقیقاً همان جزئیاتی که در استایلِ
     * سایتِ الماس‌آرا برایِ این بخش جداگانه تنظیم شده بود.
     */
    private function register_body_style_section(): void {
        $this->start_controls_section(
            'body_style_section',
            ['label' => __('متن', 'zig3d-widgets'), 'tab' => Controls_Manager::TAB_STYLE]
        );

        $this->add_control('body_defaults_heading', ['label' => __('پیش‌فرضِ متن', 'zig3d-widgets'), 'type' => Controls_Manager::HEADING]);
        $this->add_group_control(Group_Control_Typography::get_type(), ['name' => 'body_typography', 'label' => __('تایپوگرافیِ پیش‌فرض', 'zig3d-widgets'), 'selector' => '{{WRAPPER}} .zig-description__body']);
        $this->add_control('body_color', ['label' => __('رنگِ پیش‌فرض', 'zig3d-widgets'), 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .zig-description__body' => 'color: {{VALUE}};']]);

        $this->add_control('paragraph_heading', ['label' => __('پاراگراف', 'zig3d-widgets'), 'type' => Controls_Manager::HEADING, 'separator' => 'before']);
        $this->add_group_control(Group_Control_Typography::get_type(), ['name' => 'paragraph_typography', 'selector' => '{{WRAPPER}} .zig-description__body p']);
        $this->add_control('paragraph_color', ['label' => __('رنگِ پاراگراف', 'zig3d-widgets'), 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .zig-description__body p' => 'color: {{VALUE}};']]);
        $this->add_responsive_control('paragraph_spacing', ['label' => __('فاصلهٔ بین پاراگراف‌ها', 'zig3d-widgets'), 'type' => Controls_Manager::SLIDER, 'range' => ['px' => ['min' => 0, 'max' => 48]], 'selectors' => ['{{WRAPPER}} .zig-description__body' => '--zig-description-p-gap: {{SIZE}}{{UNIT}};']]);

        $this->add_control('inner_heading_heading', ['label' => __('تیترهایِ داخلِ متن (h1-h6)', 'zig3d-widgets'), 'type' => Controls_Manager::HEADING, 'separator' => 'before']);
        $this->add_group_control(Group_Control_Typography::get_type(), ['name' => 'inner_heading_typography', 'selector' => '{{WRAPPER}} .zig-description__body h1, {{WRAPPER}} .zig-description__body h2, {{WRAPPER}} .zig-description__body h3, {{WRAPPER}} .zig-description__body h4, {{WRAPPER}} .zig-description__body h5, {{WRAPPER}} .zig-description__body h6']);
        $this->add_control('inner_heading_color', ['label' => __('رنگِ تیترها', 'zig3d-widgets'), 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .zig-description__body h1, {{WRAPPER}} .zig-description__body h2, {{WRAPPER}} .zig-description__body h3, {{WRAPPER}} .zig-description__body h4, {{WRAPPER}} .zig-description__body h5, {{WRAPPER}} .zig-description__body h6' => 'color: {{VALUE}};']]);

        $this->add_control('list_heading', ['label' => __('فهرست‌ها', 'zig3d-widgets'), 'type' => Controls_Manager::HEADING, 'separator' => 'before']);
        $this->add_group_control(Group_Control_Typography::get_type(), ['name' => 'list_typography', 'selector' => '{{WRAPPER}} .zig-description__body li']);
        $this->add_control('list_color', ['label' => __('رنگِ آیتم‌هایِ فهرست', 'zig3d-widgets'), 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .zig-description__body li' => 'color: {{VALUE}};']]);
        $this->add_responsive_control('list_item_spacing', ['label' => __('فاصلهٔ بین آیتم‌ها', 'zig3d-widgets'), 'type' => Controls_Manager::SLIDER, 'range' => ['px' => ['min' => 0, 'max' => 32]], 'selectors' => ['{{WRAPPER}} .zig-description__body' => '--zig-description-li-gap: {{SIZE}}{{UNIT}};']]);

        $this->add_control('link_heading', ['label' => __('لینک‌ها', 'zig3d-widgets'), 'type' => Controls_Manager::HEADING, 'separator' => 'before']);
        $this->add_group_control(Group_Control_Typography::get_type(), ['name' => 'link_typography', 'selector' => '{{WRAPPER}} .zig-description__body a']);
        $this->start_controls_tabs('link_state_tabs');
        $this->start_controls_tab('link_normal_tab', ['label' => __('عادی', 'zig3d-widgets')]);
        $this->add_control('link_color', ['label' => __('رنگِ لینک', 'zig3d-widgets'), 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .zig-description__body a' => 'color: {{VALUE}};']]);
        $this->end_controls_tab();
        $this->start_controls_tab('link_hover_tab', ['label' => __('هاور', 'zig3d-widgets')]);
        $this->add_control('link_hover_color', ['label' => __('رنگِ هاور', 'zig3d-widgets'), 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .zig-description__body a:hover' => 'color: {{VALUE}};']]);
        $this->end_controls_tab();
        $this->end_controls_tabs();

        $this->add_control('quote_heading', ['label' => __('نقل‌قول', 'zig3d-widgets'), 'type' => Controls_Manager::HEADING, 'separator' => 'before']);
        $this->add_group_control(Group_Control_Typography::get_type(), ['name' => 'quote_typography', 'selector' => '{{WRAPPER}} .zig-description__body blockquote']);
        $this->add_control('quote_color', ['label' => __('رنگِ متنِ نقل‌قول', 'zig3d-widgets'), 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .zig-description__body blockquote' => 'color: {{VALUE}};']]);
        $this->add_control('quote_border_color', ['label' => __('رنگِ خطِ کنارِ نقل‌قول', 'zig3d-widgets'), 'type' => Controls_Manager::COLOR, 'selectors' => ['{{WRAPPER}} .zig-description__body blockquote' => 'border-inline-start-color: {{VALUE}};']]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * رندر
     * =================================================================== */

    protected function render(): void {
        $settings = $this->get_settings_for_display();
        $resolved = $this->resolve($settings);

        if (null === $resolved) {
            $this->notice(__('محتوایی برایِ نمایش پیدا نشد — منبع را بررسی کنید یا ویجت را داخلِ صفحه/قالبِ محصول یا نوشته بگذارید.', 'zig3d-widgets'));

            return;
        }

        $this->add_render_attribute('root', 'class', 'zig-description');

        printf('<div %s>', $this->get_render_attribute_string('root'));

        if ('yes' === ($settings['show_heading'] ?? 'yes')) {
            $this->render_heading($settings, $resolved);
        }

        printf('<div class="zig-description__body">%s</div>', $resolved['html']);

        echo '</div>';
    }

    /** @param array{html:string,title:string} $resolved */
    private function render_heading(array $settings, array $resolved): void {
        $text = trim((string) ($settings['heading_text'] ?? ''));

        if ('yes' === ($settings['append_title'] ?? '') && Markup::filled($resolved['title'])) {
            $text = '' !== $text ? $text . ' ' . $resolved['title'] : $resolved['title'];
        }

        if ('' === $text && '' === ($this->icon_html($settings))) {
            return;
        }

        echo '<header class="zig-description__head">';
        echo $this->icon_html($settings); // phpcs:ignore WordPress.Security.EscapeOutput -- از esc_url/esc_attr در icon_html ساخته می‌شود
        if ('' !== $text) {
            printf('<h2 class="zig-description__heading">%s</h2>', esc_html($text));
        }
        echo '</header>';
    }

    private function icon_html(array $settings): string {
        if ('yes' !== ($settings['show_icon'] ?? '')) {
            return '';
        }

        $url = (string) ($settings['heading_icon']['url'] ?? '');

        if (!Markup::filled($url)) {
            return '';
        }

        return sprintf('<img class="zig-description__icon" src="%s" alt="" />', esc_url($url));
    }

    /**
     * @return array{html:string,title:string}|null
     */
    private function resolve(array $settings): ?array {
        $source = (string) ($settings['source'] ?? 'auto');
        $render_filters = 'yes' === ($settings['render_filters'] ?? 'yes');

        if ('auto' === $source) {
            $source = null !== Price::resolve(absint($settings['product_id'] ?? 0)) ? 'product_description' : 'post_content';
        }

        if ('product_description' === $source) {
            $product = Price::resolve(absint($settings['product_id'] ?? 0));

            if (!$product) {
                return null;
            }

            $raw = (string) $product->get_description();

            return $this->finish($raw, (string) $product->get_name(), $render_filters);
        }

        $post_id = $this->current_post_id();

        if ($post_id <= 0) {
            return null;
        }

        $title = (string) get_the_title($post_id);

        if ('post_excerpt' === $source) {
            $raw = (string) get_the_excerpt($post_id);

            return $this->finish($raw, $title, $render_filters);
        }

        if ('custom_field' === $source) {
            $key = trim((string) ($settings['custom_field_key'] ?? ''));

            if ('' === $key) {
                return null;
            }

            $raw = get_post_meta($post_id, $key, true);

            return is_array($raw) ? null : $this->finish((string) $raw, $title, $render_filters);
        }

        // 'post_content' — پیش‌فرض/fallback.
        $raw = (string) get_post_field('post_content', $post_id);

        return $this->finish($raw, $title, $render_filters);
    }

    /** @return array{html:string,title:string}|null */
    private function finish(string $raw, string $title, bool $render_filters): ?array {
        if (!Markup::filled($raw)) {
            return null;
        }

        $html = $render_filters ? (string) apply_filters('the_content', $raw) : wpautop($raw);

        return ['html' => wp_kses_post($html), 'title' => $title];
    }

    private function current_post_id(): int {
        if (isset($GLOBALS['post']) && $GLOBALS['post'] instanceof \WP_Post) {
            return (int) $GLOBALS['post']->ID;
        }

        $queried = function_exists('get_queried_object_id') ? (int) get_queried_object_id() : 0;

        if ($queried > 0) {
            return $queried;
        }

        return (int) get_the_ID();
    }

    private function notice(string $message): void {
        $editing = class_exists('\Elementor\Plugin')
            && isset(\Elementor\Plugin::$instance->editor)
            && \Elementor\Plugin::$instance->editor->is_edit_mode();

        if (!$editing) {
            return;
        }

        printf('<div class="zig-description__notice">%s</div>', esc_html($message));
    }
}
