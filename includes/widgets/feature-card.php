<?php
namespace Zig3d_Widgets\Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Text_Shadow;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;
use Zig3d_Widgets\Markup;
use Zig3d_Widgets\Plugin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * کارت ویژگی: آیکون + عنوان + توضیحات.
 *
 * ساختار خروجی عمداً کم‌عمق است — یک ریشه، یک جعبهٔ آیکون، یک ستون متن —
 * چون هر لایهٔ اضافه یک گره‌ی دیگر برای مرورگر و یک سلکتور دیگر برای
 * نگه‌داری است:
 *
 *     .zig-card                     ریشه (در صورت وجود پیوند، خودش <a> می‌شود)
 *       .zig-icon                   جعبهٔ آیکون
 *       .zig-card__body
 *         .zig-card__title
 *         .zig-card__text
 */
final class Feature_Card extends Widget_Base {

    use Traits\Icon;
    use Traits\Link;
    use Traits\Box;

    public function get_name(): string {
        return 'zig3d-feature-card';
    }

    public function get_title(): string {
        return __('کارت ویژگی', 'zig3d-widgets');
    }

    public function get_icon(): string {
        return 'eicon-icon-box';
    }

    public function get_categories(): array {
        return [Plugin::CATEGORY];
    }

    public function get_keywords(): array {
        return ['card', 'icon', 'box', 'feature', 'service', 'کارت', 'آیکون', 'ویژگی', 'خدمات'];
    }

    public function get_style_depends(): array {
        return ['zig3d-widgets'];
    }

    /**
     * المنتور به‌طور پیش‌فرض یک ‎<div class="elementor-widget-container">‎ دور
     * خروجی می‌پیچد. این ویجت به آن نیازی ندارد و نبودنش یک گره کمتر در DOM و
     * یک لایه کمتر برای عبور استایل‌هاست.
     */
    public function has_widget_inner_wrapper(): bool {
        return false;
    }

    protected function register_controls(): void {
        $this->register_content_section();
        $this->register_layout_section();
        $this->register_card_style_section();
        $this->register_icon_style_section();
        $this->register_text_style_section();
    }

    /* =====================================================================
     * محتوا
     * =================================================================== */

    private function register_content_section(): void {
        $this->start_controls_section(
            'content_section',
            ['label' => __('محتوا', 'zig3d-widgets')]
        );

        $this->add_icon_content_controls();

        $this->add_control(
            'title',
            [
                'label'       => __('عنوان', 'zig3d-widgets'),
                'type'        => Controls_Manager::TEXTAREA,
                'rows'        => 2,
                'dynamic'     => ['active' => true],
                'default'     => __('بررسی و پیشنهاد میلینگ ماشین مناسب', 'zig3d-widgets'),
                'separator'   => 'before',
                'description' => __('برای برجسته‌کردن بخشی از متن می‌توانید آن را داخل &lt;span&gt; بگذارید.', 'zig3d-widgets'),
            ]
        );

        $this->add_control(
            'title_tag',
            [
                'label'       => __('تگ عنوان', 'zig3d-widgets'),
                'type'        => Controls_Manager::SELECT,
                'default'     => 'h3',
                'options'     => Markup::TAGS,
                'description' => __('ترتیب تگ‌ها را در صفحه رعایت کنید؛ هم برای سئو و هم برای کاربران صفحه‌خوان مهم است.', 'zig3d-widgets'),
            ]
        );

        $this->add_control(
            'text',
            [
                'label'   => __('توضیحات', 'zig3d-widgets'),
                'type'    => Controls_Manager::TEXTAREA,
                'rows'    => 4,
                'dynamic' => ['active' => true],
                'default' => __('بررسی حجم تولید، نوع متریال، فرآیند کاری و زیرساخت برای پیشنهاد دستگاه مناسب.', 'zig3d-widgets'),
            ]
        );

        $this->add_link_control(
            'link',
            [
                'label'       => __('پیوند کارت', 'zig3d-widgets'),
                'separator'   => 'before',
                'description' => __('اگر آدرسی بگذارید، کل کارت قابل کلیک می‌شود و با صفحه‌کلید هم قابل دسترسی خواهد بود.', 'zig3d-widgets'),
            ]
        );

        $this->end_controls_section();
    }

    /* =====================================================================
     * چیدمان
     * =================================================================== */

    private function register_layout_section(): void {
        $this->start_controls_section(
            'layout_section',
            ['label' => __('چیدمان', 'zig3d-widgets')]
        );

        /*
         * جهت‌ها منطقی‌اند (row / row-reverse) نه فیزیکی (چپ / راست): در
         * قالبِ راست‌به‌چپ، «شروع» یعنی راست و در چپ‌به‌راست یعنی چپ. با
         * مقدار فیزیکی، همین ویجت روی نسخهٔ انگلیسی سایت آینه‌ای می‌شد.
         */
        $this->add_responsive_control(
            'icon_position',
            [
                'label'                => __('جای آیکون', 'zig3d-widgets'),
                'type'                 => Controls_Manager::CHOOSE,
                'default'              => 'row',
                'options'              => [
                    'column'         => ['title' => __('بالا', 'zig3d-widgets'), 'icon' => 'eicon-v-align-top'],
                    'row'            => ['title' => __('ابتدای متن', 'zig3d-widgets'), 'icon' => 'eicon-h-align-right'],
                    'row-reverse'    => ['title' => __('انتهای متن', 'zig3d-widgets'), 'icon' => 'eicon-h-align-left'],
                    'column-reverse' => ['title' => __('پایین', 'zig3d-widgets'), 'icon' => 'eicon-v-align-bottom'],
                ],
                'toggle'    => false,
                'selectors' => ['{{WRAPPER}} .zig-card' => 'flex-direction: {{VALUE}};'],
            ]
        );

        $this->add_responsive_control(
            'align_items',
            [
                'label'     => __('تراز عمودی آیکون و متن', 'zig3d-widgets'),
                'type'      => Controls_Manager::CHOOSE,
                'default'   => 'center',
                'options'   => [
                    'flex-start' => ['title' => __('ابتدا', 'zig3d-widgets'), 'icon' => 'eicon-align-start-v'],
                    'center'     => ['title' => __('وسط', 'zig3d-widgets'), 'icon' => 'eicon-align-center-v'],
                    'flex-end'   => ['title' => __('انتها', 'zig3d-widgets'), 'icon' => 'eicon-align-end-v'],
                    'stretch'    => ['title' => __('کشیده', 'zig3d-widgets'), 'icon' => 'eicon-align-stretch-v'],
                ],
                'selectors' => ['{{WRAPPER}} .zig-card' => 'align-items: {{VALUE}};'],
            ]
        );

        $this->add_responsive_control(
            'text_align',
            [
                'label'     => __('تراز متن', 'zig3d-widgets'),
                'type'      => Controls_Manager::CHOOSE,
                'options'   => [
                    'start'   => ['title' => __('ابتدا', 'zig3d-widgets'), 'icon' => 'eicon-text-align-right'],
                    'center'  => ['title' => __('وسط', 'zig3d-widgets'), 'icon' => 'eicon-text-align-center'],
                    'end'     => ['title' => __('انتها', 'zig3d-widgets'), 'icon' => 'eicon-text-align-left'],
                    'justify' => ['title' => __('هم‌تراز', 'zig3d-widgets'), 'icon' => 'eicon-text-align-justify'],
                ],
                'selectors' => ['{{WRAPPER}} .zig-card__body' => 'text-align: {{VALUE}};'],
            ]
        );

        $this->add_responsive_control(
            'gap',
            [
                'label'      => __('فاصلهٔ آیکون تا متن', 'zig3d-widgets'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px', 'em', 'rem'],
                'range'      => ['px' => ['min' => 0, 'max' => 120]],
                'default'    => ['size' => 16, 'unit' => 'px'],
                'selectors'  => ['{{WRAPPER}} .zig-card' => 'gap: {{SIZE}}{{UNIT}};'],
            ]
        );

        $this->add_responsive_control(
            'title_gap',
            [
                'label'      => __('فاصلهٔ عنوان تا توضیحات', 'zig3d-widgets'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px', 'em', 'rem'],
                'range'      => ['px' => ['min' => 0, 'max' => 60]],
                'default'    => ['size' => 6, 'unit' => 'px'],
                'selectors'  => ['{{WRAPPER}} .zig-card__body' => 'gap: {{SIZE}}{{UNIT}};'],
            ]
        );

        $this->add_responsive_control(
            'card_height',
            [
                'label'       => __('کمینهٔ ارتفاع کارت', 'zig3d-widgets'),
                'type'        => Controls_Manager::SLIDER,
                'size_units'  => ['px', 'vh'],
                'range'       => ['px' => ['min' => 0, 'max' => 600]],
                'description' => __('برای هم‌قد کردن چند کارت کنار هم مفید است.', 'zig3d-widgets'),
                'selectors'   => ['{{WRAPPER}} .zig-card' => 'min-height: {{SIZE}}{{UNIT}};'],
            ]
        );

        $this->add_control(
            'icon_shrink',
            [
                'label'        => __('آیکون کوچک نشود', 'zig3d-widgets'),
                'type'         => Controls_Manager::SWITCHER,
                'default'      => 'yes',
                'description'  => __('با متن بلند، فلکس‌باکس آیکون را فشرده می‌کند و جعبه‌اش بیضی می‌شود. این گزینه جلویش را می‌گیرد.', 'zig3d-widgets'),
                'return_value' => 'yes',
                'selectors'    => ['{{WRAPPER}} .zig-icon' => 'flex-shrink: 0;'],
            ]
        );

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل
     * =================================================================== */

    private function register_card_style_section(): void {
        $this->start_controls_section(
            'card_style_section',
            [
                'label' => __('کارت', 'zig3d-widgets'),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_box_style_tabs('card', '.zig-card', '.zig-card');

        $this->add_control(
            'focus_heading',
            [
                'label'       => __('حلقهٔ فوکوس', 'zig3d-widgets'),
                'type'        => Controls_Manager::HEADING,
                'separator'   => 'before',
                'condition'   => ['link[url]!' => ''],
            ]
        );

        $this->add_control(
            'focus_note',
            [
                'type'            => Controls_Manager::RAW_HTML,
                'raw'             => __('فقط روی کارتِ لینک‌دار و فقط وقتی کاربر با کلید Tab روی آن برود دیده می‌شود.', 'zig3d-widgets'),
                'content_classes' => 'elementor-descriptor',
                'condition'       => ['link[url]!' => ''],
            ]
        );

        $this->add_control(
            'focus_color',
            [
                'label'     => __('رنگ', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => ['{{WRAPPER}} .zig-card' => '--zig-focus-color: {{VALUE}};'],
                'condition' => ['link[url]!' => ''],
            ]
        );

        $this->add_control(
            'focus_width',
            [
                'label'      => __('ضخامت', 'zig3d-widgets'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range'      => ['px' => ['min' => 0, 'max' => 10]],
                'selectors'  => ['{{WRAPPER}} .zig-card' => '--zig-focus-width: {{SIZE}}px;'],
                'condition'  => ['link[url]!' => ''],
            ]
        );

        $this->add_control(
            'focus_offset',
            [
                'label'      => __('فاصله از لبه', 'zig3d-widgets'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range'      => ['px' => ['min' => 0, 'max' => 12]],
                'selectors'  => ['{{WRAPPER}} .zig-card' => '--zig-focus-offset: {{SIZE}}px;'],
                'condition'  => ['link[url]!' => ''],
            ]
        );

        $this->end_controls_section();
    }

    private function register_icon_style_section(): void {
        $this->start_controls_section(
            'icon_style_section',
            [
                'label'     => __('آیکون', 'zig3d-widgets'),
                'tab'       => Controls_Manager::TAB_STYLE,
                'condition' => ['icon_source!' => 'none'],
            ]
        );

        // دامنهٔ هاور «کل کارت» است: کاربر انتظار دارد با بردن نشانگر روی هر
        // جای کارت، آیکون هم واکنش نشان دهد — نه فقط وقتی دقیقاً روی آیکون است.
        $this->add_icon_style_controls('.zig-icon', '.zig-card');

        $this->end_controls_section();
    }

    private function register_text_style_section(): void {
        $this->start_controls_section(
            'text_style_section',
            [
                'label' => __('عنوان و توضیحات', 'zig3d-widgets'),
                'tab'   => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'title_heading',
            ['label' => __('عنوان', 'zig3d-widgets'), 'type' => Controls_Manager::HEADING]
        );

        $this->add_text_block_controls('title', '.zig-card__title', '.zig-card');
        $this->add_line_clamp_control('title_lines', '.zig-card__title', 10);

        $this->add_control(
            'text_heading',
            [
                'label'     => __('توضیحات', 'zig3d-widgets'),
                'type'      => Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        $this->add_text_block_controls('text', '.zig-card__text', '.zig-card');
        $this->add_line_clamp_control('text_lines', '.zig-card__text', 20);

        $this->end_controls_section();
    }

    /**
     * محدودکردن تعداد خطوط با «…».
     *
     * چهار ویژگیِ لازم عمداً با هم در یک اعلان می‌آیند. اگر ‎display‎ و
     * ‎overflow‎ را جداگانه و همیشه در شیت می‌گذاشتیم، متن حتی بدون فعال بودن
     * این گزینه هم به ‎-webkit-box‎ تبدیل می‌شد و رفتار ترازش عوض می‌شد. این‌طور
     * وقتی کاربر عددی وارد نکرده، المنتور هیچ قاعده‌ای تولید نمی‌کند.
     */
    private function add_line_clamp_control(string $name, string $selector, int $max): void {
        $this->add_responsive_control(
            $name,
            [
                'label'       => __('حداکثر تعداد خط', 'zig3d-widgets'),
                'type'        => Controls_Manager::NUMBER,
                'min'         => 1,
                'max'         => $max,
                'description' => __('خالی بگذارید تا محدودیتی نباشد.', 'zig3d-widgets'),
                'selectors'   => [
                    '{{WRAPPER}} ' . $selector => 'display: -webkit-box; -webkit-box-orient: vertical; -webkit-line-clamp: {{VALUE}}; line-clamp: {{VALUE}}; overflow: hidden;',
                ],
            ]
        );
    }

    /**
     * تایپوگرافی و رنگ یک بلوک متنی، با حالت عادی و هاور.
     *
     * حالت هاور به هاورِ «کل کارت» گره خورده، نه به خودِ متن — همان رفتاری
     * که از یک کارت انتظار می‌رود.
     */
    private function add_text_block_controls(string $prefix, string $selector, string $hover_scope): void {
        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name'     => $prefix . '_typography',
                'selector' => '{{WRAPPER}} ' . $selector,
            ]
        );

        $this->add_group_control(
            Group_Control_Text_Shadow::get_type(),
            [
                'name'     => $prefix . '_text_shadow',
                'selector' => '{{WRAPPER}} ' . $selector,
            ]
        );

        $this->start_controls_tabs($prefix . '_color_tabs');

        $this->start_controls_tab($prefix . '_color_normal', ['label' => __('عادی', 'zig3d-widgets')]);
        $this->add_control(
            $prefix . '_color',
            [
                'label'     => __('رنگ', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => ['{{WRAPPER}} ' . $selector => 'color: {{VALUE}};'],
            ]
        );
        $this->end_controls_tab();

        $this->start_controls_tab($prefix . '_color_hover_tab', ['label' => __('هاور', 'zig3d-widgets')]);
        $this->add_control(
            $prefix . '_color_hover',
            [
                'label'     => __('رنگ', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} ' . $hover_scope . ':hover ' . $selector          => 'color: {{VALUE}};',
                    '{{WRAPPER}} ' . $hover_scope . ':focus-within ' . $selector   => 'color: {{VALUE}};',
                ],
            ]
        );
        $this->end_controls_tab();

        $this->end_controls_tabs();
    }

    /* =====================================================================
     * رندر
     * =================================================================== */

    protected function render(): void {
        $settings = $this->get_settings_for_display();

        $icon  = $this->render_icon($settings);
        $title = (string) ($settings['title'] ?? '');
        $text  = (string) ($settings['text'] ?? '');

        $has_title = Markup::filled($title);
        $has_text  = Markup::filled($text);

        // کارتِ بی‌محتوا چیزی جز یک جعبهٔ خالی با پدینگ نیست
        if ('' === $icon && !$has_title && !$has_text) {
            return;
        }

        $this->add_render_attribute('card', 'class', 'zig-card');
        $tag = $this->apply_link($settings, 'card', 'div');

        if ('a' === $tag) {
            $this->add_render_attribute('card', 'class', 'zig-card--linked');
        }

        $title_tag = Markup::tag($settings['title_tag'] ?? 'h3', 'h3');

        // فیلدهای متنی در ادیتور مستقیماً قابل ویرایش می‌شوند
        $this->add_inline_editing_attributes('title', 'none');
        $this->add_inline_editing_attributes('text', 'none');
        ?>
        <<?php echo $tag; // phpcs:ignore WordPress.Security.EscapeOutput -- از فهرست سفید Markup::tag و مقدار ثابت می‌آید ?> <?php $this->print_render_attribute_string('card'); ?>>
            <?php echo $icon; // phpcs:ignore WordPress.Security.EscapeOutput -- SVG پاک‌سازی‌شده و متن اسکیپ‌شده در render_icon ?>

            <?php if ($has_title || $has_text) : ?>
                <?php
                /*
                 * ‎<div>‎ و نه ‎<span>‎: عنوان می‌تواند ‎<h3>‎ باشد و ‎<span>‎ فقط
                 * محتوای درون‌خطی می‌پذیرد. قرار دادن سرتیتر داخل ‎<span>‎ مارک‌آپ
                 * را نامعتبر می‌کند و مرورگر آن را بازچینی می‌کند — یعنی همان
                 * ساختاری که سلکتورهای استایل رویش حساب کرده‌اند از بین می‌رود.
                 * قرار گرفتن ‎<div>‎ داخل ‎<a>‎ در HTML5 مجاز است (مدل محتوای
                 * شفاف)، پس حالت «کارتِ لینک‌دار» هم معتبر می‌ماند.
                 */
                ?>
                <div class="zig-card__body">
                    <?php if ($has_title) : ?>
                        <<?php echo $title_tag; // phpcs:ignore WordPress.Security.EscapeOutput -- Markup::tag ?> class="zig-card__title" <?php $this->print_render_attribute_string('title'); ?>><?php
                            echo Markup::text($title); // phpcs:ignore WordPress.Security.EscapeOutput -- wp_kses با فهرست سفید درون‌خطی
                        ?></<?php echo $title_tag; // phpcs:ignore WordPress.Security.EscapeOutput -- Markup::tag ?>>
                    <?php endif; ?>

                    <?php if ($has_text) : ?>
                        <p class="zig-card__text" <?php $this->print_render_attribute_string('text'); ?>><?php
                            echo Markup::text($text); // phpcs:ignore WordPress.Security.EscapeOutput -- wp_kses با فهرست سفید درون‌خطی
                        ?></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </<?php echo $tag; // phpcs:ignore WordPress.Security.EscapeOutput -- از فهرست سفید Markup::tag و مقدار ثابت می‌آید ?>>
        <?php
    }
}
