<?php
namespace Zig3d_Widgets;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * تنظیماتِ «حذفِ سکشن‌هایِ خالی» — رویِ خودِ سندِ تم‌بیلدرِ Single Product،
 * تبِ «تنظیمات» (همان‌جا که Page Layout/HTML Tag هست)، نه یک صفحهٔ جداگانه
 * در پنلِ وردپرس. دلیلش این است که این تنظیمات فقط به *همین* قالب مربوط
 * است — جایی که خودِ سازنده‌اش قبلاً هست، نه جایی تازه که باید پیدایش کرد.
 *
 * ‎Product_Section_Guard‎ همین کلاس را در زمانِ رندر می‌خواند
 * (‎self::resolve()‎)؛ خودِ آن کلاس هیچ‌چیزی از المنتور نمی‌داند — تفکیکِ
 * «کجا تنظیم می‌شود» از «چطور اعمال می‌شود» عمدی است، همان چیزی که
 * ‎filter_html()‎ را بدونِ وردپرس هم قابلِ‌تست نگه داشته.
 */
final class Product_Section_Settings {

    public const SECTION_KEYS = ['specs', 'ability', 'description', 'why', 'video', 'downloads'];

    private const LABELS = [
        'specs'       => 'مشخصاتِ فنی',
        'ability'     => 'قابلیت‌ها',
        'description' => 'توضیحات',
        'why'         => 'چرا (ریپیترِ جت‌اینجین)',
        'video'       => 'گالریِ ویدیو',
        'downloads'   => 'دانلودها',
    ];

    /** پیش‌فرض‌ها همان کلاس‌هایی‌اند که خودِ سایت الان به‌کار می‌برد */
    public const DEFAULT_CLASSES = [
        'specs'       => 'zig-product-Specifications',
        'ability'     => 'zig-product-ability',
        'description' => 'zig-product-description',
        'why'         => 'zig-product-why',
        'video'       => 'zig-product-video',
        'downloads'   => 'zig-product-downloads',
    ];

    public static function boot(): void {
        add_action('elementor/documents/register_controls', [self::class, 'register_controls']);
    }

    /**
     * @param \Elementor\Core\Base\Document $document
     */
    public static function register_controls($document): void {
        if (!self::applies_to($document)) {
            return;
        }

        $document->start_controls_section(
            'zig_section_guard',
            [
                'label' => __('سکشن‌های صفحهٔ محصول (زیگ)', 'zig3d-widgets'),
                'tab'   => \Elementor\Controls_Manager::TAB_SETTINGS,
            ]
        );

        $document->add_control(
            'zig_guard_enabled',
            [
                'label'       => __('حذفِ خودکارِ سکشن‌های خالی', 'zig3d-widgets'),
                'type'        => \Elementor\Controls_Manager::SWITCHER,
                'label_on'    => __('فعال', 'zig3d-widgets'),
                'label_off'   => __('غیرفعال', 'zig3d-widgets'),
                'default'     => 'yes',
                'description' => __(
                    'وقتی فعال است، هر سکشنِ زیر که آیتمِ اصلی‌اش برایِ محصولِ در حالِ نمایش خالی باشد، از خروجیِ صفحه حذف می‌شود (فقط در سایت — در ادیتور و پیش‌نمایش همیشه نمایش داده می‌شود).',
                    'zig3d-widgets'
                ),
            ]
        );

        foreach (self::SECTION_KEYS as $key) {
            $document->add_control(
                'zig_section_heading_' . $key,
                [
                    'label'     => self::LABELS[$key],
                    'type'      => \Elementor\Controls_Manager::HEADING,
                    'separator' => 'before',
                    'condition' => ['zig_guard_enabled' => 'yes'],
                ]
            );

            $document->add_control(
                'zig_section_enabled_' . $key,
                [
                    'label'     => __('حذف کن اگر خالی بود', 'zig3d-widgets'),
                    'type'      => \Elementor\Controls_Manager::SWITCHER,
                    'label_on'  => __('بله', 'zig3d-widgets'),
                    'label_off' => __('نه، همیشه نمایش بده', 'zig3d-widgets'),
                    'default'   => 'yes',
                    'condition' => ['zig_guard_enabled' => 'yes'],
                ]
            );

            $document->add_control(
                'zig_section_class_' . $key,
                [
                    'label'       => __('کلاسِ CSSِ سکشن در صفحه', 'zig3d-widgets'),
                    'type'        => \Elementor\Controls_Manager::TEXT,
                    'default'     => self::DEFAULT_CLASSES[$key],
                    'placeholder' => self::DEFAULT_CLASSES[$key],
                    'condition'   => [
                        'zig_guard_enabled' => 'yes',
                        'zig_section_enabled_' . $key => 'yes',
                    ],
                ]
            );
        }

        $document->end_controls_section();
    }

    /**
     * محدود به سندِ Single Product تم‌بیلدر — نه هر صفحه/پستِ عادی. نامِ
     * دقیقِ نوعِ سند در نسخه‌های مختلفِ المنتور پرو ‎product‎ است؛ اگر
     * روزی عوض شد، بررسیِ دومِ رده‌نامِ کلاس همچنان این کنترل‌ها را رویِ
     * سندِ درست نگه می‌دارد.
     *
     * @param mixed $document
     */
    private static function applies_to($document): bool {
        if (!is_object($document) || !method_exists($document, 'get_name')) {
            return false;
        }

        if ('product' === $document->get_name()) {
            return true;
        }

        return false !== stripos(get_class($document), 'product');
    }

    /**
     * پیکربندیِ مؤثر برایِ محصولِ در حالِ نمایش. اگر سندی در کار نبود یا
     * این کنترل‌ها رویش ثبت نشده بودند (قالبِ محصول از تم‌بیلدر نیست، یا
     * هنوز کسی این تب را باز نکرده)، پیش‌فرض‌ها برمی‌گردند — یعنی خودِ
     * مکانیزم بدونِ هیچ تنظیمی هم به‌طورِ پیش‌فرض فعال است.
     *
     * @return array<string,array{enabled:bool,class:string}>
     */
    public static function resolve(): array {
        $config = [];

        foreach (self::SECTION_KEYS as $key) {
            $config[$key] = ['enabled' => true, 'class' => self::DEFAULT_CLASSES[$key]];
        }

        if (!class_exists('\Elementor\Plugin') || !isset(\Elementor\Plugin::$instance->documents)) {
            return $config;
        }

        /*
         * ‎get_doc_for_frontend()‎ در نسخه‌هایِ قدیم‌ترِ المنتور بدونِ
         * آرگومان هم کار می‌کرد، ولی نسخه‌هایِ تازه‌تر ‎$post_id‎ را
         * الزامی کرده‌اند (بدونش ‎ArgumentCountError‎ می‌دهد و کل صفحه
         * سفید می‌شود). ‎get_queried_object_id()‎ همان شناسهٔ محصولی است
         * که ‎is_singular('product')‎ در ‎should_guard()‎ رویش تأیید شده.
         */
        $post_id = get_queried_object_id();

        if ($post_id <= 0) {
            return $config;
        }

        $document = \Elementor\Plugin::$instance->documents->get_doc_for_frontend($post_id);

        if (!$document) {
            return $config;
        }

        $settings = $document->get_settings();

        if (!is_array($settings) || !array_key_exists('zig_guard_enabled', $settings)) {
            return $config;
        }

        if ('yes' !== ($settings['zig_guard_enabled'] ?? 'yes')) {
            foreach ($config as $key => $_) {
                $config[$key]['enabled'] = false;
            }

            return $config;
        }

        foreach (self::SECTION_KEYS as $key) {
            $config[$key]['enabled'] = 'yes' === ($settings['zig_section_enabled_' . $key] ?? 'yes');

            $class = trim((string) ($settings['zig_section_class_' . $key] ?? ''));

            if ('' !== $class) {
                $config[$key]['class'] = $class;
            }
        }

        return $config;
    }
}
