<?php
namespace Zig3d_Widgets;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * حذفِ بخش‌های خالیِ صفحهٔ محصول.
 *
 * صفحهٔ تکِ محصول از چند سکشنِ المنتوری تشکیل شده که هرکدام یک ویجتِ ما را
 * دربر می‌گیرند: مشخصات فنی، قابلیت‌ها، توضیحات، ویدیو، دانلودها، و یک
 * ریپیترِ جت‌اینجینِ خام («چرا») که ما نساخته‌ایمش. هر ویجتِ خودمان از قبل
 * روی خروجیِ خالی سکوت می‌کند (‎return;‎ بدونِ اکو، جز نوتیسِ ادیتور که پشتِ
 * ‎is_edit_mode()‎ است) — یعنی خودِ ویجت هیچ‌وقت چیزی چاپ نمی‌کند؛ اما
 * سکشنِ المنتوریِ *دورش* (پدینگ، پس‌زمینه، تیتر احتمالیِ همان سکشن) هنوز
 * چاپ می‌شود و روی صفحه یک فضایِ خالی باقی می‌گذارد.
 *
 * راهِ درست این نیست که هر سکشن بفهمد چه چیزی داخلش هست — تنظیماتِ واقعیِ
 * هر نمونهٔ ویجت (مثلاً کلید‌متایِ دلخواهِ ادمین در ویجتِ اسناد) فقط داخلِ
 * دادهٔ خودِ المنتور است و از بیرون در دسترس نیست. راهِ قابلِ‌اتکا این
 * است که کل صفحه رندر شود، بعد بررسی کنیم آیا نشانه‌یِ رندرِ واقعیِ هر
 * ویجت (کلاسِ ریشهٔ خودش) داخلِ سکشنِ متناظر هست یا نه — اگر نبود، همان
 * سکشن از HTMLِ نهایی حذف می‌شود.
 */
final class Product_Section_Guard {

    /**
     * کلاسِ سکشنِ بیرونی => نشانهٔ «این ویجت واقعاً چیزی رندر کرد».
     *
     * برای پنج موردِ اول نشانه یک کلاسِ CSS است (ریشهٔ خروجیِ همان ویجت).
     * موردِ «چرا» ریپیترِ جت‌اینجینِ خامی است که ما نساخته‌ایم و کلاسِ
     * ریشه‌اش را نمی‌دانیم، پس با یک تابعِ سنجشِ متفاوت بررسی می‌شود
     * (‎self::WHY_SECTION‎، پایین‌تر).
     */
    private const SECTION_MARKERS = [
        'zig-product-Specifications' => '.zig-specs',
        'zig-product-ability'        => '.zig-feature',
        'zig-product-description'    => '.zig-description',
        'zig-product-video'          => '.zig-product-video',
        'zig-product-downloads'      => '.zig-documents',
    ];

    /** سکشنی که با تابعِ سنجشِ جداگانه بررسی می‌شود، نه با کلاسِ نشانه */
    private const WHY_SECTION = 'zig-product-why';

    public static function boot(): void {
        add_action('template_redirect', [self::class, 'maybe_start_buffer']);
    }

    public static function maybe_start_buffer(): void {
        if (!self::should_guard()) {
            return;
        }

        ob_start([self::class, 'filter_html']);
    }

    /**
     * فقط در بازدیدِ واقعیِ صفحهٔ محصول در سایت — نه در ادیتور/پیش‌نمایشِ
     * المنتور (وگرنه ادمین هیچ‌وقت نوتیسِ «چرا خالی است» را نمی‌بیند)، نه
     * در ادمین، نه در فیدها/رست/آژاکس.
     */
    private static function should_guard(): bool {
        if (is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return false;
        }

        if (!function_exists('is_singular') || !is_singular('product')) {
            return false;
        }

        if (self::is_elementor_editing()) {
            return false;
        }

        return true;
    }

    private static function is_elementor_editing(): bool {
        if (!class_exists('\Elementor\Plugin') || !isset(\Elementor\Plugin::$instance)) {
            return false;
        }

        $editor = \Elementor\Plugin::$instance;

        $edit_mode = isset($editor->editor) && $editor->editor->is_edit_mode();
        $preview_mode = isset($editor->preview) && method_exists($editor->preview, 'is_preview_mode') && $editor->preview->is_preview_mode();

        return $edit_mode || $preview_mode;
    }

    /**
     * بدنهٔ سنجش‌پذیرِ بدونِ وردپرس — امضایش دقیقاً همان چیزی است که
     * ‎ob_start‎ صدا می‌زند، پس هم به‌عنوانِ کال‌بک و هم مستقیم در تست
     * قابلِ‌فراخوانی است.
     */
    public static function filter_html(string $html): string {
        if ('' === trim($html) || false === stripos($html, 'zig-product-')) {
            return $html;
        }

        $doc = new \DOMDocument();

        $previous = libxml_use_internal_errors(true);
        $loaded = $doc->loadHTML(
            '<?xml encoding="utf-8" ?>' . $html,
            LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NOBLANKS
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            return $html;
        }

        $xpath = new \DOMXPath($doc);
        $removed = false;

        foreach (self::SECTION_MARKERS as $section_class => $marker_selector) {
            foreach (self::find_by_class($xpath, $section_class) as $section) {
                if (self::has_descendant_class($xpath, $section, ltrim($marker_selector, '.'))) {
                    continue;
                }

                $section->parentNode->removeChild($section);
                $removed = true;
            }
        }

        foreach (self::find_by_class($xpath, self::WHY_SECTION) as $section) {
            if (self::why_section_has_content($section)) {
                continue;
            }

            $section->parentNode->removeChild($section);
            $removed = true;
        }

        if (!$removed) {
            return $html;
        }

        $out = $doc->saveHTML();

        if (false === $out) {
            return $html;
        }

        /*
         * ‎libxml‎ بدونِ این، حروفِ غیرِ ASCII (یعنی تقریباً کلِ متنِ فارسیِ
         * صفحه) را به‌جایِ UTF-8 به موجودیتِ عددی (‎&#1576;‎...) تبدیل
         * می‌کند. معتبر است، ولی حجم را چند برابر می‌کند و خروجی را از
         * آنچه خودِ وردپرس قبلاً چاپ کرده بود عوض می‌کند؛ اینجا برمی‌گردانیمش.
         */
        $out = mb_convert_encoding($out, 'UTF-8', 'HTML-ENTITIES');

        return self::strip_wrapper($out);
    }

    /** @return \DOMElement[] */
    private static function find_by_class(\DOMXPath $xpath, string $class): array {
        $query = sprintf(
            './/*[contains(concat(" ", normalize-space(@class), " "), " %s ")]',
            $class
        );

        $nodes = [];

        foreach ($xpath->query($query) as $node) {
            if ($node instanceof \DOMElement) {
                $nodes[] = $node;
            }
        }

        return $nodes;
    }

    private static function has_descendant_class(\DOMXPath $xpath, \DOMElement $context, string $class): bool {
        $query = sprintf(
            './/*[contains(concat(" ", normalize-space(@class), " "), " %s ")]',
            $class
        );

        return $xpath->query($query, $context)->length > 0;
    }

    /**
     * سکشنِ «چرا» ریپیترِ خامِ جت‌اینجین است — ‎<img src="%_field_84297%"
     * alt="%_field_17356%">‎ و ‎<h3>%_field_17356%</h3>‎/‎<span>%_field_39835%</span>‎
     * برایِ هر آیتم. حداقل یک آیتمِ ناقص هم کافی است، پس معیارِ خالی‌بودن
     * این است: هیچ ‎<img>‎یِ با ‎src‎یِ غیرخالی، و هیچ متنِ غیرخالی در هیچ
     * ‎<h3>‎/‎<span>‎ی داخلِ این سکشن نیست.
     */
    private static function why_section_has_content(\DOMElement $section): bool {
        foreach ($section->getElementsByTagName('img') as $img) {
            if ('' !== trim((string) $img->getAttribute('src'))) {
                return true;
            }
        }

        foreach (['h3', 'span'] as $tag) {
            foreach ($section->getElementsByTagName($tag) as $node) {
                if ('' !== trim((string) $node->textContent)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * ‎DOMDocument::saveHTML()‎ رویِ کلِ سند، تگِ ‎<?xml ...?>‎یِ کمکیِ بالا
     * را هم به‌عنوانِ یک کامنت/پردازش‌دستور برمی‌گرداند — باید حذف شود
     * وگرنه به ابتدایِ هر پاسخِ HTML اضافه می‌شود.
     */
    private static function strip_wrapper(string $html): string {
        return (string) preg_replace('/^<\?xml encoding="utf-8" \?>\s*/', '', $html, 1);
    }
}
