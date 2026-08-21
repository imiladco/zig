<?php
namespace Zig3d_Widgets\Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Icons_Manager;
use Elementor\Repeater;
use Elementor\Widget_Base;
use Zig3d_Widgets\Design_Icons;
use Zig3d_Widgets\Markup;
use Zig3d_Widgets\Plugin;
use Zig3d_Widgets\Selector;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * نوارِ راه‌هایِ تماس — شماره، واتساپ، تلگرام، کنارِ هم با نقطهٔ جداکننده.
 *
 *     ul.zig-contact                 ظرفِ فلکس
 *       li.zig-contact__item         هر راهِ تماس (نقطه با ::before می‌آید)
 *         a|span.zig-contact__link
 *           span.zig-contact__icon
 *           span.zig-contact__label
 *
 * چرا ‎<ul>/<li>‎: این واقعاً فهرستی از راه‌هایِ تماس است و صفحه‌خوان باید
 * «فهرست، ۳ مورد» را اعلام کند. ‎role="list"‎ صریح می‌آید چون
 * ‎list-style: none‎ در سافاری معناشناسیِ فهرست را از بین می‌برد.
 *
 * چرا نقطه ‎::before‎ است و نه یک عنصر: نقطه جداکنندهٔ دیداری است، نه یک
 * موردِ فهرست. اگر ‎<li>‎ی جدا می‌شد، صفحه‌خوان «فهرست، ۵ مورد» می‌گفت و
 * دو موردش هیچ محتوایی نداشتند.
 *
 * چرا هر آیتم می‌تواند لینک نباشد: در طرح، شماره و واتساپ و تلگرام هر سه
 * مقصد دارند، ولی مدیر ممکن است یکی را فقط برایِ نمایش بگذارد. آیتمِ
 * بی‌مقصد ‎<span>‎ می‌شود، نه ‎<a href="#">‎ی که فوکوس می‌گیرد و جایی
 * نمی‌برد.
 */
final class Contact_Bar extends Widget_Base {

    use Traits\Box;

    /** نگاشتِ پیش‌تنظیم‌ها به فایل‌هایِ صادرشده از فیگما */
    private const PRESET_ICONS = [
        'phone'    => 'phone',
        'whatsapp' => 'whatsapp',
        'telegram' => 'telegram',
    ];

    public function get_name(): string {
        return 'zig3d-contact-bar';
    }

    public function get_title(): string {
        return __('اطلاعاتِ تماس', 'zig3d-widgets');
    }

    public function get_icon(): string {
        return 'eicon-call-to-action';
    }

    public function get_categories(): array {
        return [Plugin::CATEGORY];
    }

    public function get_keywords(): array {
        return ['contact', 'phone', 'whatsapp', 'telegram', 'تماس', 'شماره', 'واتساپ', 'تلگرام'];
    }

    public function get_style_depends(): array {
        return ['zig3d-widgets'];
    }

    public function has_widget_inner_wrapper(): bool {
        return false;
    }

    protected function register_controls(): void {
        $this->register_items_section();
        $this->register_layout_style_section();
        $this->register_text_style_section();
        $this->register_icon_style_section();
    }

    /* =====================================================================
     * محتوا › آیتم‌ها
     * =================================================================== */

    private function register_items_section(): void {
        $this->start_controls_section('items_section', ['label' => __('راه‌هایِ تماس', 'zig3d-widgets')]);

        $repeater = new Repeater();

        /*
         * پیش‌تنظیم به‌جایِ «همیشه از کتابخانه انتخاب کن»: سه آیکونِ این
         * طرح صادرشدهٔ خودِ فیگما هستند و نه معادلِ تقریبی. اگر پیش‌فرض
         * یک گلیفِ کتابخانه بود، خروجی از همان اول با طرح فرق می‌کرد.
         */
        $repeater->add_control('icon_preset', [
            'label'   => __('آیکون', 'zig3d-widgets'),
            'type'    => Controls_Manager::SELECT,
            'default' => 'phone',
            'options' => [
                'phone'    => __('تماس', 'zig3d-widgets'),
                'whatsapp' => __('واتساپ', 'zig3d-widgets'),
                'telegram' => __('تلگرام', 'zig3d-widgets'),
                'custom'   => __('انتخاب از کتابخانه', 'zig3d-widgets'),
                'none'     => __('بدونِ آیکون', 'zig3d-widgets'),
            ],
        ]);

        $repeater->add_control('icon', [
            'label'     => __('آیکونِ دلخواه', 'zig3d-widgets'),
            'type'      => Controls_Manager::ICONS,
            'default'   => ['value' => '', 'library' => ''],
            'condition' => ['icon_preset' => 'custom'],
        ]);

        $repeater->add_control('label', [
            'label'       => __('متن', 'zig3d-widgets'),
            'type'        => Controls_Manager::TEXT,
            'dynamic'     => ['active' => true],
            'default'     => __('واتساپ', 'zig3d-widgets'),
            'label_block' => true,
        ]);

        $repeater->add_control('link', [
            'label'       => __('پیوند', 'zig3d-widgets'),
            'type'        => Controls_Manager::URL,
            'dynamic'     => ['active' => true],
            'placeholder' => 'tel:03134415816',
            'options'     => ['url', 'is_external', 'nofollow', 'custom_attributes'],
            'label_block' => true,
            'description' => __('برایِ شماره ‎tel:‎، برایِ واتساپ ‎https://wa.me/…‎ و برایِ تلگرام ‎https://t.me/…‎. خالی بگذارید تا فقط متن نمایش داده شود.', 'zig3d-widgets'),
        ]);

        /*
         * در طرح، شمارهٔ تماس نیم‌ضخیم است و دو مورد دیگر عادی. این یک
         * تفاوتِ فی‌البداهه نیست؛ شماره لنگرِ اصلیِ نوار است.
         */
        $repeater->add_control('emphasis', [
            'label'        => __('پررنگ', 'zig3d-widgets'),
            'type'         => Controls_Manager::SWITCHER,
            'return_value' => 'yes',
        ]);

        $this->add_control('items', [
            'label'       => __('آیتم‌ها', 'zig3d-widgets'),
            'type'        => Controls_Manager::REPEATER,
            'fields'      => $repeater->get_controls(),
            'title_field' => '{{{ label }}}',
            'default'     => [
                [
                    'icon_preset' => 'phone',
                    'label'       => '031-34415816',
                    'emphasis'    => 'yes',
                ],
                [
                    'icon_preset' => 'whatsapp',
                    'label'       => __('واتساپ', 'zig3d-widgets'),
                ],
                [
                    'icon_preset' => 'telegram',
                    'label'       => __('تلگرام', 'zig3d-widgets'),
                ],
            ],
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل › چیدمان
     * =================================================================== */

    private function register_layout_style_section(): void {
        $this->start_controls_section('layout_style_section', [
            'label' => __('چیدمان', 'zig3d-widgets'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $root = '{{WRAPPER}} .zig-contact';

        $this->add_responsive_control('align', [
            'label'     => __('تراز', 'zig3d-widgets'),
            'type'      => Controls_Manager::CHOOSE,
            'options'   => [
                'flex-start' => ['title' => __('شروع', 'zig3d-widgets'), 'icon' => 'eicon-text-align-right'],
                'center'     => ['title' => __('وسط', 'zig3d-widgets'), 'icon' => 'eicon-text-align-center'],
                'flex-end'   => ['title' => __('پایان', 'zig3d-widgets'), 'icon' => 'eicon-text-align-left'],
            ],
            'selectors' => [$root => 'justify-content: {{VALUE}};'],
        ]);

        /*
         * فاصله دو بار به کار می‌رود: یک‌بار بینِ نقطه و آیتم، یک‌بار بینِ
         * آیتم و نقطهٔ بعدی. یعنی فاصلهٔ دیداریِ دو آیتم برابرِ دو برابرِ
         * این عدد به‌علاوهٔ خودِ نقطه است — همان ۲۰ + ۵ + ۲۰ی طرح.
         */
        $this->add_responsive_control('gap', [
            'label'      => __('فاصله تا نقطهٔ جداکننده', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 80]],
            'selectors'  => [$root => '--zig-contact-gap: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_control('wrap', [
            'label'        => __('شکستنِ خط در عرضِ کم', 'zig3d-widgets'),
            'type'         => Controls_Manager::SWITCHER,
            'default'      => 'yes',
            'return_value' => 'wrap',
            'selectors'    => [$root => 'flex-wrap: {{VALUE}};'],
            'description'  => __('خاموش کنید تا نوار همیشه در یک خط بماند، حتی اگر از عرضِ ظرف بزند بیرون.', 'zig3d-widgets'),
        ]);

        $this->add_control('dot_heading', [
            'label'     => __('نقطهٔ جداکننده', 'zig3d-widgets'),
            'type'      => Controls_Manager::HEADING,
            'separator' => 'before',
        ]);

        $this->add_control('dot_size', [
            'label'      => __('اندازه', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 20]],
            'selectors'  => [$root => '--zig-contact-dot-size: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_control('dot_color', [
            'label'     => __('رنگ', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [$root => '--zig-contact-dot-color: {{VALUE}};'],
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل › متن
     * =================================================================== */

    private function register_text_style_section(): void {
        $this->start_controls_section('text_style_section', [
            'label' => __('متن', 'zig3d-widgets'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $label = '{{WRAPPER}} .zig-contact__label';

        $this->add_group_control(Group_Control_Typography::get_type(), [
            'name'     => 'label_typography',
            'selector' => $label,
        ]);

        $this->start_controls_tabs('label_tabs');

        $this->start_controls_tab('label_tab_normal', ['label' => __('عادی', 'zig3d-widgets')]);

        $this->add_control('label_color', [
            'label'     => __('رنگِ متن', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .zig-contact' => '--zig-contact-text-color: {{VALUE}};'],
        ]);

        $this->end_controls_tab();

        $this->start_controls_tab('label_tab_hover', ['label' => __('هاور', 'zig3d-widgets')]);

        /*
         * هاور فقط رویِ آیتم‌هایِ لینک‌دار معنی دارد؛ آیتمِ بی‌مقصد
         * ‎<span>‎ است و هیچ‌وقت این حالت را نمی‌گیرد.
         */
        $this->add_control('label_color_hover', [
            'label'     => __('رنگِ متن', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [Selector::descend('{{WRAPPER}} a.zig-contact__link:hover', '.zig-contact__label') => 'color: {{VALUE}};'],
        ]);

        $this->add_control('icon_color_hover', [
            'label'     => __('رنگِ آیکون', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [Selector::descend('{{WRAPPER}} a.zig-contact__link:hover', '.zig-contact__icon') => 'color: {{VALUE}};'],
        ]);

        $this->end_controls_tab();
        $this->end_controls_tabs();

        $this->add_control('emphasis_weight', [
            'label'       => __('ضخامتِ آیتمِ پررنگ', 'zig3d-widgets'),
            'type'        => Controls_Manager::SELECT,
            'separator'   => 'before',
            'default'     => '',
            'options'     => [
                ''    => __('پیش‌فرض (۶۰۰)', 'zig3d-widgets'),
                '500' => '500',
                '600' => '600',
                '700' => '700',
                '800' => '800',
            ],
            'selectors'   => ['{{WRAPPER}} .zig-contact' => '--zig-contact-emphasis-weight: {{VALUE}};'],
            'description' => __('فقط رویِ آیتم‌هایی اثر دارد که کلیدِ «پررنگ»شان روشن است.', 'zig3d-widgets'),
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * استایل › آیکون
     * =================================================================== */

    private function register_icon_style_section(): void {
        $this->start_controls_section('icon_style_section', [
            'label' => __('آیکون', 'zig3d-widgets'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ]);

        $root = '{{WRAPPER}} .zig-contact';

        $this->add_responsive_control('icon_size', [
            'label'      => __('اندازه', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 8, 'max' => 48]],
            'selectors'  => [$root => '--zig-contact-icon-size: {{SIZE}}{{UNIT}};'],
        ]);

        $this->add_control('icon_color', [
            'label'     => __('رنگ', 'zig3d-widgets'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => [$root => '--zig-contact-icon-color: {{VALUE}};'],
        ]);

        $this->add_responsive_control('icon_gap', [
            'label'      => __('فاصله تا متن', 'zig3d-widgets'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => ['px'],
            'range'      => ['px' => ['min' => 0, 'max' => 40]],
            'selectors'  => [$root => '--zig-contact-icon-gap: {{SIZE}}{{UNIT}};'],
        ]);

        $this->end_controls_section();
    }

    /* =====================================================================
     * رندر
     * =================================================================== */

    protected function render(): void {
        $settings = $this->get_settings_for_display();
        $items    = $settings['items'] ?? [];

        if (empty($items) || !is_array($items)) {
            return;
        }

        $rendered = 0;
        ob_start();

        foreach ($items as $index => $item) {
            $label = (string) ($item['label'] ?? '');

            // بدونِ متن، آیتم فقط یک آیکونِ بی‌معنی است — کلِ سطر کنار می‌رود
            if (!Markup::filled($label)) {
                continue;
            }

            ++$rendered;
            $this->render_item($item, $index, $label);
        }

        $list = ob_get_clean();

        if (0 === $rendered) {
            return;
        }

        printf('<ul class="zig-contact" role="list">%s</ul>', $list);
    }

    private function render_item(array $item, int $index, string $label): void {
        $classes = ['zig-contact__item', 'elementor-repeater-item-' . ($item['_id'] ?? '')];

        if ('yes' === ($item['emphasis'] ?? '')) {
            $classes[] = 'zig-contact__item--emphasis';
        }

        printf('<li class="%s">', esc_attr(implode(' ', $classes)));

        $url = trim((string) ($item['link']['url'] ?? ''));

        if ('' !== $url) {
            $key = 'link_' . $index;
            $this->add_render_attribute($key, 'class', 'zig-contact__link');
            $this->add_link_attributes($key, $item['link']);

            printf('<a %s>', $this->get_render_attribute_string($key));
        } else {
            echo '<span class="zig-contact__link">';
        }

        $icon = $this->render_icon($item);

        if ('' !== $icon) {
            printf('<span class="zig-contact__icon" aria-hidden="true">%s</span>', $icon);
        }

        /*
         * ‎<bdi>‎ اینجا لازم است نه تزئینی: شمارهٔ «031-34415816» لاتین
         * است داخلِ نوارِ راست‌به‌چپ، و بدونِ ایزوله الگوریتمِ دوجهته
         * خط‌تیره را آن‌طرف عدد می‌اندازد.
         */
        printf('<span class="zig-contact__label"><bdi>%s</bdi></span>', esc_html($label));

        echo '' !== $url ? '</a>' : '</span>';
        echo '</li>';
    }

    /**
     * آیکونِ یک سطر: پیش‌تنظیمِ صادرشده از فیگما، یا انتخابِ مدیر، یا هیچ.
     */
    private function render_icon(array $item): string {
        $preset = (string) ($item['icon_preset'] ?? 'phone');

        if ('none' === $preset) {
            return '';
        }

        if ('custom' === $preset) {
            $icon = $item['icon'] ?? [];

            if (empty($icon['value'])) {
                return '';
            }

            ob_start();
            Icons_Manager::render_icon($icon, ['aria-hidden' => 'true']);

            return (string) ob_get_clean();
        }

        return isset(self::PRESET_ICONS[$preset])
            ? Design_Icons::get(self::PRESET_ICONS[$preset])
            : '';
    }
}
