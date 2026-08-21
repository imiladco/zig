<?php
/**
 * حداقلِ المنتور برای تست.
 *
 * هدف: بشود کنترل‌های یک ویجت را ثبت کرد و فهرستشان را برداشت، بدون نصب
 * المنتور. همه‌چیز مشروط اعلام می‌شود تا این فایل کنار المنتورِ واقعی هم
 * قابل بارگذاری باشد.
 *
 * ثبت‌ها با نشانه‌گذاریِ باز/بسته ذخیره می‌شوند (SECTION/‎/SECTION‎ و
 * TABS/‎/TABS‎) چون بخش مهمی از چیزی که می‌سنجیم «تعادل» است: سکشنِ بسته‌نشده
 * یا تبِ تودرتو پنل المنتور را بی‌صدا خراب می‌کند و در PHP هیچ خطایی نمی‌دهد.
 */

namespace Elementor {

if (!class_exists('Elementor\Controls_Manager')) {

class Controls_Manager {
    const TAB_CONTENT = 'content';
    const TAB_STYLE   = 'style';
    const TEXT        = 'text';
    const TEXTAREA    = 'textarea';
    const NUMBER      = 'number';
    const SELECT      = 'select';
    const SELECT2     = 'select2';
    const SWITCHER    = 'switcher';
    const COLOR       = 'color';
    const SLIDER      = 'slider';
    const CHOOSE      = 'choose';
    const DIMENSIONS  = 'dimensions';
    const HEADING     = 'heading';
    const RAW_HTML    = 'raw_html';
    const MEDIA       = 'media';
    const ICONS       = 'icons';
    const URL         = 'url';
    const REPEATER    = 'repeater';
    const DIVIDER     = 'divider';
}

abstract class Group_Control_Base {
    public static function get_type() { return static::class; }
}
class Group_Control_Typography extends Group_Control_Base {}
class Group_Control_Border extends Group_Control_Base {}
class Group_Control_Box_Shadow extends Group_Control_Base {}
class Group_Control_Background extends Group_Control_Base {}
class Group_Control_Text_Shadow extends Group_Control_Base {}

class Repeater {
    public function add_control($name, $args = []) {
        $GLOBALS['ZIG'][] = 'repeater:' . $name;
    }
    public function get_controls() { return []; }
}

class Widget_Base {
    public function add_control($name, $args = []) {
        $GLOBALS['ZIG'][] = $name;
        self::zig_record_selectors($name, $args);
    }
    public function add_responsive_control($name, $args = []) {
        $GLOBALS['ZIG'][] = $name;
        self::zig_record_selectors($name, $args);
    }
    public function add_group_control($type, $args = []) {
        $name = 'group:' . ($args['name'] ?? '?');
        $GLOBALS['ZIG'][] = $name;

        // گروه‌کنترل‌ها سلکتورهایشان را از همین یک رشته می‌سازند
        if (isset($args['selector'])) {
            $GLOBALS['ZIG_SELECTORS'][] = [$name, (string) $args['selector'], ''];
        }
    }

    /**
     * ثبت هر سلکتوری که کنترل تولید می‌کند، برای سنجهٔ دامنه.
     *
     * بدون این، سلکتوری که ‎{{WRAPPER}}‎ ندارد در تست‌ها نامرئی است: خروجی
     * رندر درست می‌ماند و فقط CSS تولیدشده به کل صفحه نشت می‌کند.
     */
    private static function zig_record_selectors($name, $args) {
        foreach ((array) ($args['selectors'] ?? []) as $selector => $rule) {
            $GLOBALS['ZIG_SELECTORS'][] = [$name, (string) $selector, (string) $rule];
        }
    }
    public function start_controls_section($name, $args = []) { $GLOBALS['ZIG'][] = 'SECTION:' . $name; }
    public function end_controls_section() { $GLOBALS['ZIG'][] = '/SECTION'; }
    public function start_controls_tabs($name, $args = []) { $GLOBALS['ZIG'][] = 'TABS:' . $name; }
    public function end_controls_tabs() { $GLOBALS['ZIG'][] = '/TABS'; }
    public function start_controls_tab($name, $args = []) { $GLOBALS['ZIG'][] = 'TAB:' . $name; }
    public function end_controls_tab() { $GLOBALS['ZIG'][] = '/TAB'; }
    public function get_id() { return 'testid'; }
    public function get_title() { return 'test'; }

    /* ------------------------------------------------------------------
     * ویژگی‌های رندر
     *
     * این‌ها عمداً no-op نیستند: بخش زیادی از چیزی که در رندر می‌تواند خراب
     * شود — کلاسِ جاافتاده، ‎href‎ اسکیپ‌نشده، ‎rel‎ فراموش‌شده — فقط وقتی دیده
     * می‌شود که خروجی واقعاً ساخته شود.
     * ---------------------------------------------------------------- */

    /** @var array<string,array<string,string[]>> */
    protected array $zig_attributes = [];

    /**
     * رفتارِ پیش‌فرض («افزودن»، نه «جایگزینی») عمداً دقیقاً مثلِ خودِ
     * المنتور است: ویجتی که در یک حلقه (مثلاً یک کارت به‌ازایِ هر ردیف)
     * همین یک کلید را دوباره صدا می‌زند، بدونِ ‎overwrite: true‎ باید مقادیرِ
     * قبلی‌اش را «ببیند» — دقیقاً همان باگی که این تست گرفت.
     */
    public function add_render_attribute($key, $name = null, $value = null, $overwrite = false) {
        if (is_array($name)) {
            foreach ($name as $attr => $val) {
                $this->add_render_attribute($key, $attr, $val, $overwrite);
            }

            return $this;
        }

        if ($overwrite) {
            unset($this->zig_attributes[$key][$name]);
        }

        foreach ((array) $value as $single) {
            if ('' === $single || null === $single) {
                continue;
            }
            $this->zig_attributes[$key][$name][] = (string) $single;
        }

        return $this;
    }

    /** مطابقِ ‎Element_Base::remove_render_attribute‎ خودِ المنتور. */
    public function remove_render_attribute($key, $name = null, $value = null) {
        if (null === $name) {
            unset($this->zig_attributes[$key]);

            return $this;
        }

        if (null === $value) {
            unset($this->zig_attributes[$key][$name]);

            return $this;
        }

        $this->zig_attributes[$key][$name] = array_values(array_diff($this->zig_attributes[$key][$name] ?? [], (array) $value));

        return $this;
    }

    public function add_link_attributes($key, $link = []) {
        if (!empty($link['url'])) {
            $this->add_render_attribute($key, 'href', esc_url($link['url']));
        }

        $rel = [];

        if (!empty($link['is_external'])) {
            $this->add_render_attribute($key, 'target', '_blank');
            $rel[] = 'noopener';
            $rel[] = 'noreferrer';
        }
        if (!empty($link['nofollow'])) {
            $rel[] = 'nofollow';
        }
        if ($rel) {
            $this->add_render_attribute($key, 'rel', implode(' ', $rel));
        }

        return $this;
    }

    public function add_inline_editing_attributes($key, $mode = null) {}

    /**
     * بدون فاصلهٔ ابتدایی — دقیقاً مثل ‎Utils::render_html_attributes‎ خودِ
     * المنتور. اگر اینجا فاصله اضافه شود، خروجی تست با خروجی واقعی فرق
     * می‌کند و سنجه‌هایی که روی رشتهٔ خروجی کار می‌کنند بی‌دلیل قرمز می‌شوند.
     */
    public function get_render_attribute_string($key) {
        $parts = [];

        foreach ($this->zig_attributes[$key] ?? [] as $name => $values) {
            $parts[] = sprintf('%s="%s"', $name, esc_attr(implode(' ', $values)));
        }

        return implode(' ', $parts);
    }

    public function print_render_attribute_string($key) {
        echo $this->get_render_attribute_string($key);
    }

    public function get_repeater_setting_key($f, $r, $i) { return $r . '.' . $i . '.' . $f; }

    /**
     * ‎get_settings()‎ عمداً می‌ترکد، نه اینکه مقداری برگرداند.
     *
     * در المنتورِ واقعی این متد برایِ آماده‌کردنِ تنظیمات سراغِ
     * ‎get_controls()‎ می‌رود و آن هم اگر کنترل‌ها هنوز ثبت نشده باشند
     * ‎register_controls()‎ را صدا می‌زند. پس هر فراخوانیِ ‎get_settings()‎
     * از داخلِ ‎register_controls()‎ یک بازگشتِ بی‌پایان است که پنلِ ویجت
     * را در ویرایشگر کاملاً از کار می‌اندازد — بی‌آنکه PHP خطایی بدهد.
     *
     * یک بار همین افتاد (ویجتِ منو: پنل بالا نمی‌آمد و مگامنو هیچ‌وقت
     * گزینه‌ای برایِ انتخاب نداشت) و هیچ سنجه‌ای نگرفتش. حالا می‌گیرد.
     */
    public function get_settings($key = null) {
        throw new \RuntimeException(
            'get_settings() هنگامِ ثبتِ کنترل‌ها صدا زده شد؛ در المنتور این یعنی بازگشتِ بی‌پایان.'
        );
    }

    /** خروجی رندر با تنظیمات داده‌شده — نقطهٔ ورود تست‌ها */
    public function zig_render(array $settings): string {
        $this->zig_attributes = [];
        $this->zig_settings   = $settings;

        $method = new \ReflectionMethod($this, 'render');
        $method->setAccessible(true);

        ob_start();
        $method->invoke($this);

        return (string) ob_get_clean();
    }

    /** @var array */
    protected array $zig_settings = [];

    public function get_settings_for_display($key = null) {
        return null === $key ? $this->zig_settings : ($this->zig_settings[$key] ?? null);
    }
}

class Icons_Manager {
    public static function render_icon($icon, $attrs = []) { echo '<svg></svg>'; }
}

class Utils {
    public static function get_placeholder_image_src() { return ''; }
}

}

}

namespace {

    if (!defined('ABSPATH')) {
        define('ABSPATH', true);
    }

    if (!function_exists('get_intermediate_image_sizes')) {
        function get_intermediate_image_sizes() {
            return ['thumbnail', 'medium', 'large'];
        }
    }
    /**
     * تصویر پیوست.
     *
     * قبلاً رشتهٔ خالی برمی‌گرداند، و این برای ویجت‌هایی که تصویر جزء
     * تزئیناتشان بود کافی بود. برای گالری نیست: آنجا کلِ خروجی همین
     * تصویرهاست و با رشتهٔ خالی، تستِ ترتیبِ اسلایدها روی یک صفحهٔ بی‌عکس
     * اجرا می‌شد و همیشه سبز می‌ماند.
     *
     * شناسه و اندازه در خروجی می‌آیند تا بشود سنجید *کدام* پیوست با
     * *کدام* اندازه درخواست شده — وگرنه هر ‎<img>‎ی شبیه هر ‎<img>‎ دیگری
     * است و جابه‌جا شدنِ اندازهٔ صحنه و بندانگشتی دیده نمی‌شود.
     */
    if (!function_exists('wp_get_attachment_image')) {
        function wp_get_attachment_image($id, $size = 'thumbnail', $icon = false, $attr = []) {
            $out = '<img src="https://zig3d.test/img/' . (int) $id . '.jpg"'
                . ' data-size="' . htmlspecialchars((string) $size, ENT_QUOTES) . '"';

            foreach ((array) $attr as $key => $value) {
                $out .= ' ' . $key . '="' . htmlspecialchars((string) $value, ENT_QUOTES) . '"';
            }

            return $out . ' />';
        }
    }
    /** آدرسِ یک پیوست؛ شناسه و اندازه در خودِ رشته می‌آیند تا قابل سنجش باشند */
    if (!function_exists('wp_get_attachment_image_url')) {
        function wp_get_attachment_image_url($id, $size = 'thumbnail') {
            if ((int) $id <= 0) {
                return false;
            }

            return 'https://zig3d.test/full/' . (int) $id . '-' . (string) $size . '.jpg';
        }
    }
    /**
     * رندر یک ویجت بیرون از المنتور.
     *
     * اینجا و نه در فایل تست: دو فایل تست به آن نیاز داشتند و هر کدام
     * نسخهٔ خودش را تعریف کرده بود، تا روزی که هر دو در یک اجرا لود شدند
     * و PHP با «تعریف دوباره» مرد. یک تعریف، جایی که بقیهٔ استاب‌ها هستند.
     */
    if (!function_exists('zig_widget')) {
        function zig_widget(string $class) {
            return (new \ReflectionClass($class))->newInstanceWithoutConstructor();
        }
    }

    if (!function_exists('zig_render')) {
        function zig_render(string $class, array $settings): string {
            return zig_widget($class)->zig_render($settings);
        }
    }

    if (!function_exists('get_post_mime_type')) {
        function get_post_mime_type($id) { return $GLOBALS['__zig_mime'][$id] ?? false; }
    }
    if (!function_exists('get_attached_file')) {
        function get_attached_file($id) { return $GLOBALS['__zig_file'][$id] ?? false; }
    }

    $GLOBALS['ZIG']           = $GLOBALS['ZIG'] ?? [];
    $GLOBALS['ZIG_SELECTORS'] = $GLOBALS['ZIG_SELECTORS'] ?? [];

    /**
     * فهرست کامل ثبت‌های یک ویجت، به ترتیب.
     *
     * @return string[]
     */
    function zig_collect_controls(string $class): array {
        $GLOBALS['ZIG'] = [];

        $widget = (new ReflectionClass($class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod($widget, 'register_controls');
        $method->setAccessible(true);
        $method->invoke($widget);

        return $GLOBALS['ZIG'];
    }

    /**
     * همهٔ سلکتورهایی که یک ویجت تولید می‌کند.
     *
     * @return array<int,array{0:string,1:string,2:string}> [نام کنترل، سلکتور، قاعده]
     */
    function zig_collect_selectors(string $class): array {
        $GLOBALS['ZIG_SELECTORS'] = [];

        $widget = (new ReflectionClass($class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod($widget, 'register_controls');
        $method->setAccessible(true);
        $method->invoke($widget);

        return $GLOBALS['ZIG_SELECTORS'];
    }
}
