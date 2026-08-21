<?php
namespace Zig3d_Widgets\Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Background;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Typography;
use Elementor\Icons_Manager;
use Elementor\Widget_Base;
use Zig3d_Widgets\Likes;
use Zig3d_Widgets\Likes_Endpoint;
use Zig3d_Widgets\Design_Icons;
use Zig3d_Widgets\Plugin;
use Zig3d_Widgets\Price;
use Zig3d_Widgets\Reading_Time;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * نوارِ متایِ پست — زمانِ مطالعه، تعدادِ دیدگاه، تعدادِ لایک.
 *
 *     div.zig-post-meta
 *       span.zig-post-meta__reading-time
 *       div.zig-post-meta__actions                      فقط اگر دیدگاه یا لایک روشن باشد
 *         a.zig-post-meta__item.zig-post-meta__comments   پیوندِ واقعی به بخشِ دیدگاه‌ها
 *           span.zig-post-meta__count
 *           span.zig-post-meta__icon
 *         button.zig-post-meta__item.zig-post-meta__like[.is-liked]   دکمهٔ سوییچ، نه پیوند
 *           span.zig-post-meta__count
 *           span.zig-post-meta__icon
 *             span.zig-post-meta__icon-outline
 *             span.zig-post-meta__icon-filled
 *
 * زمانِ مطالعه از رویِ محتوایِ خودِ پست محاسبه می‌شود (‎Reading_Time‎، بدونِ
 * افزونهٔ واسط). دیدگاه از هستهٔ وردپرس می‌آید. لایک تنها بخشی است که این
 * افزونه خودش داده‌اش را نگه می‌دارد (‎Likes‎/‎Likes_Endpoint‎) — چون سایت
 * افزونهٔ لایکِ دیگری ندارد.
 *
 * چرا دیدگاه ‎<a href>‎ است ولی لایک ‎<button>‎: اولی ناوبری‌ست (به بخشِ
 * دیدگاه‌ها می‌رود)، دومی یک کنش/سوییچ است که صفحه را عوض نمی‌کند — دقیقاً
 * تمایزی که در سرتاسرِ این افزونه رعایت شده.
 */
final class Post_Meta extends Widget_Base {

    private const DESIGN_ICONS = [
        'comment_icon'     => 'comment',
        'like_icon'        => 'heart',
        'like_icon_active' => 'heart-filled',
    ];

    public function get_name(): string {
        return 'zig3d-post-meta';
    }

    public function get_title(): string {
        return __('متای پست', 'zig3d-widgets');
    }

    public function get_icon(): string {
        return 'eicon-post-info';
    }

    public function get_categories(): array {
        return [Plugin::CATEGORY];
    }

    public function get_keywords(): array {
        return ['post', 'meta', 'comment', 'like', 'reading time', 'پست', 'دیدگاه', 'لایک', 'زمان مطالعه'];
    }

    public function get_style_depends(): array {
        return ['zig3d-widgets'];
    }

    public function get_script_depends(): array {
        return ['zig3d-post-meta'];
    }

    public function has_widget_inner_wrapper(): bool {
        return false;
    }

    protected function register_controls(): void {
        $this->register_post_section();
        $this->register_reading_time_section();
        $this->register_comments_section();
        $this->register_likes_section();
        $this->register_icons_section();

        $this->register_container_style_section();
        $this->register_reading_time_style_section();
        $this->register_comments_style_section();
        $this->register_likes_style_section();
    }

    /* =====================================================================
     * محتوا: پست
     * =================================================================== */

    private function register_post_section(): void {
        $this->start_controls_section('post_section', ['label' => __('پست', 'zig3d-widgets')]);

        $this->add_control(
            'post_id',
            [
                'label'       => __('شناسهٔ پست', 'zig3d-widgets'),
                'type'        => Controls_Manager::NUMBER,
                'min'         => 0,
                'dynamic'     => ['active' => true],
                'description' => __('خالی بگذارید تا پستِ جاری استفاده شود — چه در صفحهٔ تکی، چه داخل حلقهٔ آرشیو یا قالب حلقهٔ المنتور.', 'zig3d-widgets'),
            ]
        );

        $this->end_controls_section();
    }

    /* =====================================================================
     * محتوا: زمان مطالعه
     * =================================================================== */

    private function register_reading_time_section(): void {
        $this->start_controls_section('reading_time_section', ['label' => __('زمان مطالعه', 'zig3d-widgets')]);

        $this->add_control(
            'show_reading_time',
            [
                'label'        => __('نمایش', 'zig3d-widgets'),
                'type'         => Controls_Manager::SWITCHER,
                'default'      => 'yes',
                'return_value' => 'yes',
            ]
        );

        $this->add_control(
            'reading_time_text',
            [
                'label'       => __('متن', 'zig3d-widgets'),
                'type'        => Controls_Manager::TEXT,
                'dynamic'     => ['active' => true],
                'default'     => __('{time} دقیقه مطالعه', 'zig3d-widgets'),
                'description' => __('‎{time}‎ با عددِ دقیقه جایگزین می‌شود.', 'zig3d-widgets'),
                'label_block' => true,
                'condition'   => ['show_reading_time' => 'yes'],
            ]
        );

        $this->add_control(
            'words_per_minute',
            [
                'label'       => __('سرعتِ مطالعه (کلمه در دقیقه)', 'zig3d-widgets'),
                'type'        => Controls_Manager::NUMBER,
                'min'         => 1,
                'default'     => Reading_Time::DEFAULT_WPM,
                'condition'   => ['show_reading_time' => 'yes'],
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

        $this->end_controls_section();
    }

    /* =====================================================================
     * محتوا: دیدگاه‌ها
     * =================================================================== */

    private function register_comments_section(): void {
        $this->start_controls_section('comments_section', ['label' => __('دیدگاه‌ها', 'zig3d-widgets')]);

        $this->add_control(
            'show_comments',
            [
                'label'        => __('نمایش', 'zig3d-widgets'),
                'type'         => Controls_Manager::SWITCHER,
                'default'      => 'yes',
                'return_value' => 'yes',
            ]
        );

        $this->add_control(
            'comments_aria_label',
            [
                'label'       => __('برچسبِ صفحه‌خوان', 'zig3d-widgets'),
                'type'        => Controls_Manager::TEXT,
                'default'     => __('{count} دیدگاه — مشاهدهٔ دیدگاه‌ها', 'zig3d-widgets'),
                'description' => __('‎{count}‎ با عددِ دیدگاه جایگزین می‌شود.', 'zig3d-widgets'),
                'label_block' => true,
                'condition'   => ['show_comments' => 'yes'],
            ]
        );

        $this->add_control(
            'comment_icon',
            [
                'label'       => __('آیکون', 'zig3d-widgets'),
                'type'        => Controls_Manager::ICONS,
                'default'     => ['value' => '', 'library' => ''],
                'description' => __('خالی یعنی آیکونِ پیش‌فرض.', 'zig3d-widgets'),
                'condition'   => ['show_comments' => 'yes'],
            ]
        );

        $this->end_controls_section();
    }

    /* =====================================================================
     * محتوا: لایک
     * =================================================================== */

    private function register_likes_section(): void {
        $this->start_controls_section('likes_section', ['label' => __('لایک', 'zig3d-widgets')]);

        $this->add_control(
            'show_likes',
            [
                'label'        => __('نمایش', 'zig3d-widgets'),
                'type'         => Controls_Manager::SWITCHER,
                'default'      => 'yes',
                'return_value' => 'yes',
            ]
        );

        $this->add_control(
            'like_aria_label',
            [
                'label'     => __('برچسبِ صفحه‌خوان (لایک‌نشده)', 'zig3d-widgets'),
                'type'      => Controls_Manager::TEXT,
                'default'   => __('لایک کردن', 'zig3d-widgets'),
                'condition' => ['show_likes' => 'yes'],
            ]
        );

        $this->add_control(
            'like_aria_label_active',
            [
                'label'     => __('برچسبِ صفحه‌خوان (لایک‌شده)', 'zig3d-widgets'),
                'type'      => Controls_Manager::TEXT,
                'default'   => __('برداشتنِ لایک', 'zig3d-widgets'),
                'condition' => ['show_likes' => 'yes'],
            ]
        );

        $this->add_control(
            'like_icon',
            [
                'label'       => __('آیکون (لایک‌نشده)', 'zig3d-widgets'),
                'type'        => Controls_Manager::ICONS,
                'default'     => ['value' => '', 'library' => ''],
                'description' => __('خالی یعنی آیکونِ پیش‌فرض.', 'zig3d-widgets'),
                'condition'   => ['show_likes' => 'yes'],
                'separator'   => 'before',
            ]
        );

        $this->add_control(
            'like_icon_active',
            [
                'label'       => __('آیکون (لایک‌شده)', 'zig3d-widgets'),
                'type'        => Controls_Manager::ICONS,
                'default'     => ['value' => '', 'library' => ''],
                'description' => __('خالی یعنی آیکونِ پیش‌فرض.', 'zig3d-widgets'),
                'condition'   => ['show_likes' => 'yes'],
            ]
        );

        $this->end_controls_section();
    }

    /* =====================================================================
     * محتوا: آیکون‌ها
     * =================================================================== */

    private function register_icons_section(): void {
        $this->start_controls_section('icons_section', ['label' => __('آیکون‌ها', 'zig3d-widgets')]);

        $this->add_control(
            'design_icons',
            [
                'label'        => __('آیکون‌های پیش‌فرض طرح', 'zig3d-widgets'),
                'type'         => Controls_Manager::SWITCHER,
                'default'      => 'yes',
                'label_on'     => __('روشن', 'zig3d-widgets'),
                'label_off'    => __('خاموش', 'zig3d-widgets'),
                'return_value' => 'yes',
                'description'  => __('روشن باشد، آیکونِ خالی‌مانده همان پیش‌فرضِ طرح را می‌گیرد. خاموشش کنید تا آیکونِ خالی واقعاً حذف شود.', 'zig3d-widgets'),
            ]
        );

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل: جعبه
     * =================================================================== */

    private function register_container_style_section(): void {
        $this->start_controls_section(
            'container_style_section',
            ['label' => __('جعبه', 'zig3d-widgets'), 'tab' => Controls_Manager::TAB_STYLE]
        );

        $this->add_responsive_control(
            'items_gap',
            [
                'label'      => __('فاصلهٔ بین آیتم‌ها', 'zig3d-widgets'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px', 'em'],
                'range'      => ['px' => ['min' => 0, 'max' => 60]],
                'default'    => ['size' => 24, 'unit' => 'px'],
                'selectors'  => ['{{WRAPPER}} .zig-post-meta' => 'gap: {{SIZE}}{{UNIT}};'],
            ]
        );

        $this->add_responsive_control(
            'items_align',
            [
                'label'   => __('چینش عمودی', 'zig3d-widgets'),
                'type'    => Controls_Manager::SELECT,
                'default' => 'center',
                'options' => [
                    'flex-start' => __('بالا', 'zig3d-widgets'),
                    'center'     => __('وسط', 'zig3d-widgets'),
                    'flex-end'   => __('پایین', 'zig3d-widgets'),
                ],
                'selectors' => ['{{WRAPPER}} .zig-post-meta' => 'align-items: {{VALUE}};'],
            ]
        );

        $this->add_responsive_control(
            'items_justify',
            [
                'label'     => __('چینش افقی', 'zig3d-widgets'),
                'type'      => Controls_Manager::SELECT,
                'default'   => 'flex-start',
                'options'   => [
                    'flex-start'    => __('راست/چپ (شروع)', 'zig3d-widgets'),
                    'center'        => __('وسط', 'zig3d-widgets'),
                    'flex-end'      => __('چپ/راست (پایان)', 'zig3d-widgets'),
                    'space-between' => __('فاصلهٔ مساوی (دوسر چسبیده)', 'zig3d-widgets'),
                    'space-around'  => __('فاصلهٔ مساوی دورِ هر آیتم', 'zig3d-widgets'),
                ],
                'selectors' => ['{{WRAPPER}} .zig-post-meta' => 'justify-content: {{VALUE}};'],
            ]
        );

        $this->add_control(
            'actions_heading',
            [
                'label'     => __('گروهِ دیدگاه و لایک', 'zig3d-widgets'),
                'type'      => Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        $this->add_responsive_control(
            'actions_gap',
            [
                'label'      => __('فاصلهٔ دیدگاه تا لایک', 'zig3d-widgets'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px', 'em'],
                'range'      => ['px' => ['min' => 0, 'max' => 60]],
                'default'    => ['size' => 24, 'unit' => 'px'],
                'selectors'  => ['{{WRAPPER}} .zig-post-meta__actions' => 'gap: {{SIZE}}{{UNIT}};'],
            ]
        );

        $this->add_responsive_control(
            'actions_justify',
            [
                'label'     => __('چینشِ افقیِ این گروه', 'zig3d-widgets'),
                'type'      => Controls_Manager::SELECT,
                'default'   => 'flex-start',
                'options'   => [
                    'flex-start'    => __('راست/چپ (شروع)', 'zig3d-widgets'),
                    'center'        => __('وسط', 'zig3d-widgets'),
                    'flex-end'      => __('چپ/راست (پایان)', 'zig3d-widgets'),
                    'space-between' => __('فاصلهٔ مساوی (دوسر چسبیده)', 'zig3d-widgets'),
                    'space-around'  => __('فاصلهٔ مساوی دورِ هر آیتم', 'zig3d-widgets'),
                ],
                'selectors' => ['{{WRAPPER}} .zig-post-meta__actions' => 'justify-content: {{VALUE}};'],
            ]
        );

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل: زمان مطالعه
     * =================================================================== */

    private function register_reading_time_style_section(): void {
        $this->start_controls_section(
            'reading_time_style_section',
            [
                'label'     => __('زمان مطالعه', 'zig3d-widgets'),
                'tab'       => Controls_Manager::TAB_STYLE,
                'condition' => ['show_reading_time' => 'yes'],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            ['name' => 'reading_time_typography', 'selector' => '{{WRAPPER}} .zig-post-meta__reading-time']
        );

        $this->add_control(
            'reading_time_color',
            [
                'label'     => __('رنگ', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => ['{{WRAPPER}} .zig-post-meta__reading-time' => 'color: {{VALUE}};'],
            ]
        );

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل: دیدگاه‌ها
     * =================================================================== */

    private function register_comments_style_section(): void {
        $this->start_controls_section(
            'comments_style_section',
            [
                'label'     => __('دیدگاه‌ها', 'zig3d-widgets'),
                'tab'       => Controls_Manager::TAB_STYLE,
                'condition' => ['show_comments' => 'yes'],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            ['name' => 'comments_typography', 'selector' => '{{WRAPPER}} .zig-post-meta__comments .zig-post-meta__count']
        );

        $this->add_responsive_control(
            'comments_icon_size',
            [
                'label'      => __('اندازهٔ آیکون', 'zig3d-widgets'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range'      => ['px' => ['min' => 8, 'max' => 40]],
                'default'    => ['size' => 16, 'unit' => 'px'],
                'selectors'  => ['{{WRAPPER}} .zig-post-meta__comments .zig-post-meta__icon svg' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};'],
            ]
        );

        $this->add_responsive_control(
            'comments_icon_box',
            [
                'label'      => __('اندازهٔ جعبهٔ آیکون', 'zig3d-widgets'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range'      => ['px' => ['min' => 16, 'max' => 60]],
                'default'    => ['size' => 32, 'unit' => 'px'],
                'selectors'  => ['{{WRAPPER}} .zig-post-meta__comments .zig-post-meta__icon' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};'],
            ]
        );

        $this->start_controls_tabs('comments_tabs');

        $this->start_controls_tab('comments_normal_tab', ['label' => __('عادی', 'zig3d-widgets')]);
        $this->add_comments_state_controls('', '{{WRAPPER}} .zig-post-meta__comments');
        $this->end_controls_tab();

        $this->start_controls_tab('comments_hover_tab', ['label' => __('هاور', 'zig3d-widgets')]);
        $this->add_comments_state_controls('hover_', '{{WRAPPER}} .zig-post-meta__comments:hover, {{WRAPPER}} .zig-post-meta__comments:focus-visible');
        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->end_controls_section();
    }

    private function add_comments_state_controls(string $prefix, string $selector): void {
        $this->add_control(
            $prefix . 'comments_color',
            [
                'label'     => __('رنگ متن و آیکون', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => [
                    $selector . ' .zig-post-meta__count' => 'color: {{VALUE}};',
                    $selector . ' .zig-post-meta__icon'  => 'color: {{VALUE}};',
                    /*
                     * ‎currentColor‎ خودش از ‎color‎ی بالا ارث می‌برد، ولی
                     * روی ‎stroke‎ی خودِ SVG هم صریح نشانده می‌شود — دقیقاً
                     * همان احتیاطی که ‎chevron_color‎ی کانفیگ‌گر برایِ
                     * ‎fill‎ دارد؛ اگر جایی (مثلاً CSSِ قالب) رویِ svg
                     * دست ببرد، این رنگ همچنان برنده است.
                     */
                    $selector . ' .zig-post-meta__icon svg' => 'stroke: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Background::get_type(),
            ['name' => $prefix . 'comments_icon_background', 'label' => __('پس‌زمینهٔ جعبهٔ آیکون', 'zig3d-widgets'), 'types' => ['classic'], 'selector' => $selector . ' .zig-post-meta__icon']
        );
    }

    /* =====================================================================
     * استایل: لایک
     * =================================================================== */

    private function register_likes_style_section(): void {
        $this->start_controls_section(
            'likes_style_section',
            [
                'label'     => __('لایک', 'zig3d-widgets'),
                'tab'       => Controls_Manager::TAB_STYLE,
                'condition' => ['show_likes' => 'yes'],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            ['name' => 'likes_typography', 'selector' => '{{WRAPPER}} .zig-post-meta__like .zig-post-meta__count']
        );

        $this->add_responsive_control(
            'likes_icon_size',
            [
                'label'      => __('اندازهٔ آیکون', 'zig3d-widgets'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range'      => ['px' => ['min' => 8, 'max' => 40]],
                'default'    => ['size' => 16, 'unit' => 'px'],
                'selectors'  => ['{{WRAPPER}} .zig-post-meta__like .zig-post-meta__icon svg' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};'],
            ]
        );

        $this->add_responsive_control(
            'likes_icon_box',
            [
                'label'      => __('اندازهٔ جعبهٔ آیکون', 'zig3d-widgets'),
                'type'       => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range'      => ['px' => ['min' => 16, 'max' => 60]],
                'default'    => ['size' => 32, 'unit' => 'px'],
                'selectors'  => ['{{WRAPPER}} .zig-post-meta__like .zig-post-meta__icon' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};'],
            ]
        );

        $this->start_controls_tabs('likes_tabs');

        $this->start_controls_tab('likes_normal_tab', ['label' => __('عادی', 'zig3d-widgets')]);
        $this->add_likes_state_controls('', '{{WRAPPER}} .zig-post-meta__like:not(.is-liked)');
        $this->end_controls_tab();

        $this->start_controls_tab('likes_hover_tab', ['label' => __('هاور', 'zig3d-widgets')]);
        $this->add_likes_state_controls('hover_', '{{WRAPPER}} .zig-post-meta__like:not(.is-liked):hover, {{WRAPPER}} .zig-post-meta__like:not(.is-liked):focus-visible');
        $this->end_controls_tab();

        $this->start_controls_tab('likes_active_tab', ['label' => __('لایک‌شده', 'zig3d-widgets')]);
        $this->add_likes_state_controls('active_', '{{WRAPPER}} .zig-post-meta__like.is-liked');
        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->end_controls_section();
    }

    private function add_likes_state_controls(string $prefix, string $selector): void {
        $color_selectors = [
            $selector . ' .zig-post-meta__count' => 'color: {{VALUE}};',
            $selector . ' .zig-post-meta__icon'  => 'color: {{VALUE}};',
            /*
             * ‎stroke‎ در همهٔ حالت‌ها بی‌ضرر است: رویِ آیکونِ خطیِ قلب
             * (خالی) اثر می‌کند، رویِ آیکونِ توپرش هیچ (چون stroke ندارد).
             */
            $selector . ' .zig-post-meta__icon svg' => 'stroke: {{VALUE}};',
        ];

        /*
         * ‎fill‎ فقط برایِ حالتِ «لایک‌شده» — چون آیکونِ توپرِ قلب رنگش را
         * از ‎fill‎ می‌گیرد، نه ‎stroke‎. اگر این‌جا هم عمومی می‌شد، آیکونِ
         * خطیِ حالتِ عادی/هاور (که عمداً ‎fill="none"‎ است) توپر می‌شد.
         */
        if ('active_' === $prefix) {
            $color_selectors[$selector . ' .zig-post-meta__icon svg'] = 'stroke: {{VALUE}}; fill: {{VALUE}};';
        }

        $this->add_control(
            $prefix . 'likes_color',
            [
                'label'     => __('رنگ متن و آیکون', 'zig3d-widgets'),
                'type'      => Controls_Manager::COLOR,
                'selectors' => $color_selectors,
            ]
        );

        $this->add_group_control(
            Group_Control_Background::get_type(),
            ['name' => $prefix . 'likes_icon_background', 'label' => __('پس‌زمینهٔ جعبهٔ آیکون', 'zig3d-widgets'), 'types' => ['classic'], 'selector' => $selector . ' .zig-post-meta__icon']
        );
    }

    /* =====================================================================
     * رندر
     * =================================================================== */

    protected function render(): void {
        $settings = $this->get_settings_for_display();
        $post_id  = $this->resolve_post_id(absint($settings['post_id'] ?? 0));

        if ($post_id <= 0) {
            $this->notice(__('پستی پیدا نشد. شناسهٔ پست را وارد کنید یا ویجت را داخل صفحه/قالب پست بگذارید.', 'zig3d-widgets'));

            return;
        }

        $this->add_render_attribute('root', [
            'class'            => 'zig-post-meta',
            'data-zig-post-meta' => '1',
            'data-rest-url'    => esc_url(rest_url('zig3d/v1/like')),
            'data-ajax-url'    => esc_url(admin_url('admin-ajax.php')),
            'data-ajax-action' => Likes_Endpoint::ACTION,
        ]);

        printf('<div %s>', $this->get_render_attribute_string('root')); // phpcs:ignore WordPress.Security.EscapeOutput -- از get_render_attribute_string، خودش اسکیپ‌شده

        $this->render_reading_time($settings, $post_id);

        $has_comments = 'yes' === ($settings['show_comments'] ?? 'yes');
        $has_likes    = 'yes' === ($settings['show_likes'] ?? 'yes');

        /*
         * دیدگاه و لایک در یک دیوِ مشترک — تا فاصله/چینشِ این دو، جدا از
         * فاصلهٔ زمانِ مطالعه تا این گروه، قابلِ‌تنظیم باشد. اگر هیچ‌کدام
         * روشن نباشد، دیوی هم در کار نیست.
         */
        if ($has_comments || $has_likes) {
            echo '<div class="zig-post-meta__actions">';
            $this->render_comments($settings, $post_id);
            $this->render_likes($settings, $post_id);
            echo '</div>';
        }

        echo '</div>';
    }

    /**
     * پستِ هدف — یا override دستی، یا پستِ جاری (همان الگویِ سایرِ ویجت‌های
     * غیرِ محصول در این افزونه).
     */
    private function resolve_post_id(int $override): int {
        if ($override > 0) {
            return 'publish' === get_post_status($override) ? $override : 0;
        }

        if (isset($GLOBALS['post']) && $GLOBALS['post'] instanceof \WP_Post) {
            return (int) $GLOBALS['post']->ID;
        }

        $queried = function_exists('get_queried_object_id') ? (int) get_queried_object_id() : 0;

        return $queried > 0 ? $queried : (int) get_the_ID();
    }

    private function render_reading_time(array $settings, int $post_id): void {
        if ('yes' !== ($settings['show_reading_time'] ?? 'yes')) {
            return;
        }

        $content = (string) get_post_field('post_content', $post_id);
        $wpm     = max(1, absint($settings['words_per_minute'] ?? Reading_Time::DEFAULT_WPM));
        $minutes = Reading_Time::minutes($content, $wpm);
        $number  = 'yes' === ($settings['persian_digits'] ?? 'yes') ? Price::persian((string) $minutes) : (string) $minutes;

        $template = trim((string) ($settings['reading_time_text'] ?? ''));
        $template = '' === $template ? '{time}' : $template;
        $text     = str_replace('{time}', $number, $template);

        printf('<span class="zig-post-meta__reading-time">%s</span>', esc_html($text));
    }

    private function render_comments(array $settings, int $post_id): void {
        if ('yes' !== ($settings['show_comments'] ?? 'yes')) {
            return;
        }

        $count   = (int) get_comments_number($post_id);
        $number  = 'yes' === ($settings['persian_digits'] ?? 'yes') ? Price::persian((string) $count) : (string) $count;
        $href    = get_comments_link($post_id);
        $label   = trim((string) ($settings['comments_aria_label'] ?? ''));
        $icon    = $this->render_icon($settings, 'comment_icon');

        $attrs = ['class' => ['zig-post-meta__item', 'zig-post-meta__comments'], 'href' => esc_url($href)];

        /*
         * ‎aria-label=""‎ی خالی بدتر از نبودنش است — طبقِ مشخصاتِ ARIA
         * یعنی «این پیوند عمداً بی‌نام است»، نه «برچسبِ پیش‌فرض را بگیر
         * (متنِ داخلش)». پس فقط وقتی مقدار دارد اضافه می‌شود.
         */
        if ('' !== $label) {
            $attrs['aria-label'] = str_replace('{count}', (string) $count, $label);
        }

        $this->add_render_attribute('comments', $attrs);

        printf('<a %s>', $this->get_render_attribute_string('comments')); // phpcs:ignore WordPress.Security.EscapeOutput -- از get_render_attribute_string، خودش اسکیپ‌شده
        printf('<span class="zig-post-meta__count">%s</span>', esc_html($number));

        if ('' !== $icon) {
            printf('<span class="zig-post-meta__icon" aria-hidden="true">%s</span>', $icon); // phpcs:ignore WordPress.Security.EscapeOutput -- خروجی Icons_Manager یا SVGی طرح
        }

        echo '</a>';
    }

    private function render_likes(array $settings, int $post_id): void {
        if ('yes' !== ($settings['show_likes'] ?? 'yes')) {
            return;
        }

        $count = Likes::count($post_id);
        $token = isset($_COOKIE[Likes_Endpoint::COOKIE]) ? (string) $_COOKIE[Likes_Endpoint::COOKIE] : '';
        $liked = '' !== $token && Likes::has_liked($post_id, $token);

        $number = 'yes' === ($settings['persian_digits'] ?? 'yes') ? Price::persian((string) $count) : (string) $count;

        $label_off = trim((string) ($settings['like_aria_label'] ?? ''));
        $label_on  = trim((string) ($settings['like_aria_label_active'] ?? ''));

        $classes = ['zig-post-meta__item', 'zig-post-meta__like'];

        if ($liked) {
            $classes[] = 'is-liked';
        }

        $attrs = [
            'type'         => 'button',
            'class'        => $classes,
            'aria-pressed' => $liked ? 'true' : 'false',
            'data-post-id' => (string) $post_id,
            'data-label-off' => $label_off,
            'data-label-on'  => $label_on,
        ];

        $active_label = $liked ? $label_on : $label_off;

        if ('' !== $active_label) {
            $attrs['aria-label'] = $active_label;
        }

        $this->add_render_attribute('like', $attrs);

        printf('<button %s>', $this->get_render_attribute_string('like')); // phpcs:ignore WordPress.Security.EscapeOutput -- از get_render_attribute_string، خودش اسکیپ‌شده
        printf('<span class="zig-post-meta__count">%s</span>', esc_html($number));

        echo '<span class="zig-post-meta__icon" aria-hidden="true">';
        printf('<span class="zig-post-meta__icon-outline">%s</span>', $this->render_icon($settings, 'like_icon')); // phpcs:ignore WordPress.Security.EscapeOutput -- خروجی Icons_Manager یا SVGی طرح
        printf('<span class="zig-post-meta__icon-filled">%s</span>', $this->render_icon($settings, 'like_icon_active')); // phpcs:ignore WordPress.Security.EscapeOutput -- خروجی Icons_Manager یا SVGی طرح
        echo '</span>';

        echo '</button>';
    }

    /** آیکونِ یک اسلات — انتخابِ کاربر از کتابخانه، وگرنه پیش‌فرضِ طرح (اگر روشن باشد) */
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
