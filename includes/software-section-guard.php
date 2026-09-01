<?php
namespace Zig3d_Widgets;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * حذفِ سکشن‌هایِ خالیِ صفحهٔ تکِ نرم‌افزار.
 *
 * هدف همان چیزی است که ‎Product_Section_Guard‎ برایِ صفحهٔ محصول
 * می‌کند، ولی روشش عوض شده — و دلیلش ارزشِ نوشتن دارد:
 *
 * نگهبانِ محصول کلِ صفحه را با ‎ob_start()‎ می‌گیرد، با ‎DOMDocument‎
 * پارس می‌کند، و سکشنِ خالی را با یک ‎<style>‎ی الحاقی *مخفی* می‌کند.
 * رویِ صفحهٔ واقعیِ همین سایت آن مسیر ۲۹۷ کیلوبایت HTML را پارس می‌کند
 * (~۱۰ms، ~۴MB) و در نهایت سکشن همچنان در خروجی و در DOM می‌ماند —
 * فقط دیده نمی‌شود. برایِ خزنده‌ها و مصرف‌کننده‌هایِ ماشینی این یعنی
 * محتوایِ خالی هنوز آن‌جاست؛ دقیقاً برعکسِ کاری که در لایهٔ schema
 * داریم می‌کنیم.
 *
 * این‌جا به‌جایش از نقطهٔ اتصالِ *رسمیِ* خودِ المنتور استفاده می‌شود:
 * ‎elementor/frontend/before_render‎ و ‎after_render‎ که به‌ازایِ هر
 * المنت اجرا می‌شوند — همان مکانیزمی که خودِ Elementor Pro برایِ
 * Display Conditions به‌کار می‌برد. سکشنِ نامزد در ‎before_render‎ بافر
 * می‌شود و در ‎after_render‎ اگر معلوم شد چیزی رندر نکرده، بافرش دور
 * ریخته می‌شود — یعنی واقعاً از خروجی حذف می‌شود، نه پنهان.
 *
 * نتیجه: بافر فقط به اندازهٔ همان سکشن (چند کیلوبایت)، بدونِ پارسِ
 * DOMِ کلِ صفحه، بدونِ وابستگی به رندرشدنِ ‎data-id‎ یا نامِ کلاس‌هایِ
 * المنتور.
 *
 * قاعدهٔ ایمنی دست‌نخورده مانده: سکشن فقط وقتی نامزد است که *همهٔ*
 * ویجت‌هایِ محتوایی‌اش یا مالِ خودمان باشند یا قابِ همان سکشن
 * (تیتر/پاراگراف/جداکننده). سکشنی که چیزِ دیگری هم دارد — مثلِ هدرِ
 * همین قالب که کنارِ «سیستم‌عامل‌هایِ سازگار» عکس و کارت و دکمه هم
 * دارد — هرگز حذف نمی‌شود.
 */
final class Software_Section_Guard {

    /**
     * ‎widgetType‎ی ویجت‌هایِ خودمان => کلاسِ ریشه‌ای که *فقط وقتی واقعاً
     * چیزی رندر کردند* چاپ می‌شود. نبودِ این کلاس در خروجیِ سکشن یعنی
     * آن ویجت این‌بار ساکت مانده.
     */
    private const WIDGET_MARKERS = [
        'zig3d-description'                   => 'zig-description',
        'zig3d-software-info-table'           => 'zig-software-info-table',
        'zig3d-software-environment-gallery'  => 'zig-software-gallery',
        'zig3d-compatible-devices'            => 'zig-compatible-devices',
        'zig3d-compatible-operating-systems'  => 'zig-compatible-os',
        'zig3d-documents'                     => 'zig-documents',
        'zig3d-faq'                           => 'zig-faq',
    ];

    /**
     * ویجت‌هایی که «محتوا» حساب نمی‌شوند — قابِ همان سکشن‌اند و اگر
     * محتوایِ اصلی نیامد، خودشان هم باید بروند (تیترِ «اطلاعات فنی»
     * بدونِ جدول بی‌معنا است). حضورشان مانعِ نامزدشدنِ سکشن نمی‌شود.
     *
     * هم نام‌هایِ کلاسیکِ المنتور و هم معادل‌هایِ اتمیکِ (V4) — رویِ این
     * سایت آزمایشِ ‎e_atomic_elements‎ روشن است و همهٔ المنت‌هایِ قالب
     * از خانوادهٔ ‎e-*‎ هستند.
     */
    private const CHROME_WIDGETS = [
        'heading', 'e-heading',
        'text-editor', 'e-paragraph',
        'divider', 'e-divider',
        'spacer', 'e-spacer',
    ];

    /**
     * سکشنی که همین حالا بافرش را ما باز کرده‌ایم:
     * ‎[شناسه، کلاس‌هایِ نشانه، سطحِ بافر هنگامِ باز کردن]‎.
     *
     * تک‌عضوی است چون فقط بیرونی‌ترین نامزد را می‌گیریم — نامزدِ تودرتو
     * داخلِ همان بافر رندر می‌شود و نیازی به بافرِ دوم نیست.
     *
     * @var array{0:string,1:string[],2:int}|null
     */
    private static ?array $open = null;

    /** @var bool|null کشِ همان‌درخواستیِ ‎should_guard()‎ — به‌ازایِ هر المنت صدا زده می‌شود */
    private static ?bool $active = null;

    public static function boot(): void {
        add_action('elementor/frontend/before_render', [self::class, 'before_render']);
        add_action('elementor/frontend/after_render', [self::class, 'after_render']);
    }

    /** @param mixed $element ‎\Elementor\Element_Base‎ */
    public static function before_render($element): void {
        if (null !== self::$open || !self::should_guard() || !self::is_container($element)) {
            return;
        }

        $markers = self::markers_for($element);

        if (!$markers) {
            return;
        }

        self::$open = [(string) $element->get_id(), $markers, ob_get_level()];

        ob_start();
    }

    /** @param mixed $element ‎\Elementor\Element_Base‎ */
    public static function after_render($element): void {
        if (null === self::$open) {
            return;
        }

        [$id, $markers, $level] = self::$open;

        if ($id !== (string) $element->get_id()) {
            return;
        }

        self::$open = null;

        /*
         * اگر بینِ ‎before‎ و ‎after‎ کسِ دیگری بافرِ ما را بسته باشد،
         * ‎ob_get_clean()‎ خروجیِ *او* را می‌دزدد. در آن حالت دست نمی‌زنیم
         * — سکشن سالم چاپ می‌شود، فقط این‌بار حذف نمی‌شود. شکستِ بی‌ضرر،
         * نه صفحهٔ خراب.
         */
        if (ob_get_level() <= $level) {
            return;
        }

        $html = (string) ob_get_clean();

        if (self::has_marker($html, $markers)) {
            echo $html; // phpcs:ignore WordPress.Security.EscapeOutput -- خروجیِ خودِ المنتور، دست‌نخورده

            return;
        }

        // هیچ نشانه‌ای نبود: سکشن چیزی برای نشان‌دادن نداشت و چاپ نمی‌شود.
    }

    /**
     * @param string[] $markers
     */
    public static function has_marker(string $html, array $markers): bool {
        foreach ($markers as $marker) {
            if (false !== strpos($html, $marker)) {
                return true;
            }
        }

        return false;
    }

    /* ------------------------------------------------------------------ */

    private static function should_guard(): bool {
        if (null !== self::$active) {
            return self::$active;
        }

        self::$active = self::resolve_should_guard();

        return self::$active;
    }

    private static function resolve_should_guard(): bool {
        if (is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return false;
        }

        $post_type = self::post_type();

        if ('' === $post_type || !function_exists('is_singular') || !is_singular($post_type)) {
            return false;
        }

        return !self::is_elementor_editing();
    }

    private static function post_type(): string {
        return class_exists(__NAMESPACE__ . '\\Download_Archive_Data')
            ? (string) Download_Archive_Data::post_type()
            : '';
    }

    /** همان چکِ ‎Product_Section_Guard::is_elementor_editing()‎ — رجوع کنید به توضیحِ آن‌جا */
    private static function is_elementor_editing(): bool {
        if (isset($_GET['elementor-preview'])) {
            return true;
        }

        if (!class_exists('\Elementor\Plugin') || !isset(\Elementor\Plugin::$instance)) {
            return false;
        }

        $editor = \Elementor\Plugin::$instance;

        $edit_mode = isset($editor->editor) && $editor->editor->is_edit_mode();
        $preview_mode = isset($editor->preview) && method_exists($editor->preview, 'is_preview_mode') && $editor->preview->is_preview_mode();

        return $edit_mode || $preview_mode;
    }

    /** @param mixed $element */
    private static function is_container($element): bool {
        return is_object($element)
            && method_exists($element, 'get_id')
            && method_exists($element, 'get_type')
            && method_exists($element, 'get_children')
            && 'widget' !== $element->get_type();
    }

    /**
     * کلاس‌هایِ نشانهٔ این سکشن — یا آرایهٔ خالی اگر اصلاً نامزد نیست.
     *
     * @param mixed $element
     * @return string[]
     */
    private static function markers_for($element): array {
        $widgets = [];
        self::collect_widget_names($element, $widgets);

        return self::markers_for_widgets($widgets);
    }

    /**
     * @param mixed $element
     * @param string[] $widgets
     */
    private static function collect_widget_names($element, array &$widgets): void {
        if (!is_object($element) || !method_exists($element, 'get_type')) {
            return;
        }

        if ('widget' === $element->get_type() && method_exists($element, 'get_name')) {
            $widgets[] = (string) $element->get_name();

            return;
        }

        if (!method_exists($element, 'get_children')) {
            return;
        }

        foreach ((array) $element->get_children() as $child) {
            self::collect_widget_names($child, $widgets);
        }
    }

    /**
     * قاعدهٔ نامزدی — تابعِ خالص، قلبِ تصمیمِ این کلاس.
     *
     * نامزد است اگر و فقط اگر: حداقل یک ویجتِ نشانه‌دارِ ما داشته باشد،
     * و هر ویجتِ دیگرش صرفاً قاب باشد. اولین ویجتِ ناشناس یعنی این سکشن
     * محتوایِ مستقل دارد و باید کاملاً رها شود.
     *
     * @param string[] $widgets
     * @return string[]
     */
    public static function markers_for_widgets(array $widgets): array {
        $markers = [];

        foreach ($widgets as $widget) {
            $widget = (string) $widget;

            if (isset(self::WIDGET_MARKERS[$widget])) {
                $markers[] = self::WIDGET_MARKERS[$widget];

                continue;
            }

            if (in_array($widget, self::CHROME_WIDGETS, true)) {
                continue;
            }

            return [];
        }

        return array_values(array_unique($markers));
    }

    /**
     * نگاشتِ «شناسهٔ سکشن => نشانه‌ها» از رویِ دادهٔ خامِ یک قالب.
     *
     * مسیرِ رندر (بالا) به این نیازی ندارد — درختِ زندهٔ المنت‌ها آن‌جا
     * در دسترس است. این‌جا می‌ماند چون همان قاعده را رویِ دادهٔ ذخیره‌شده
     * هم قابلِ‌سنجش می‌کند: می‌شود بدونِ بالاآوردنِ المنتور، رویِ
     * ‎_elementor_data‎ی واقعیِ یک قالب بررسی کرد که تصمیم چه خواهد بود.
     *
     * @param array<int,mixed> $data
     * @return array<string,string[]>
     */
    public static function sections_from_template(array $data): array {
        $out = [];

        foreach ($data as $section) {
            if (!is_array($section)) {
                continue;
            }

            $id = (string) ($section['id'] ?? '');

            if ('' === $id || !preg_match('/^[A-Za-z0-9_-]+$/', $id)) {
                continue;
            }

            $widgets = [];
            self::collect_widget_names_from_array($section, $widgets);

            $markers = self::markers_for_widgets($widgets);

            if ($markers) {
                $out[$id] = $markers;
            }
        }

        return $out;
    }

    /**
     * @param array<string,mixed> $element
     * @param string[] $widgets
     */
    private static function collect_widget_names_from_array(array $element, array &$widgets): void {
        $widget = (string) ($element['widgetType'] ?? '');

        if ('' !== $widget) {
            $widgets[] = $widget;
        }

        foreach ((array) ($element['elements'] ?? []) as $child) {
            if (is_array($child)) {
                self::collect_widget_names_from_array($child, $widgets);
            }
        }
    }

    /** فقط برایِ تست — هر درخواستِ واقعی خودش یک پردازشِ تازه است */
    public static function reset(): void {
        self::$open = null;
        self::$active = null;
    }
}
