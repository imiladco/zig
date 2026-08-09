<?php
/**
 * راه‌انداز تست‌ها.
 *
 * تست‌ها عمداً به نصب وردپرس نیاز ندارند: هدفشان منطق خالصِ افزونه است —
 * پاک‌سازی SVG، کمکی‌های مارک‌آپ، و سلامتِ فهرست کنترل‌های المنتور. همین
 * باعث می‌شود در هر محیطی و در CI بدون هیچ سرویس جانبی اجرا شوند.
 *
 * اجرا:  php tests/run.php
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', true);
}

foreach ([
    'KB_IN_BYTES'     => 1024,
    'WEEK_IN_SECONDS' => 604800,
] as $name => $value) {
    if (!defined($name)) {
        define($name, $value);
    }
}

/* --------------------------------------------------------------------------
 * حداقلِ توابع وردپرس
 *
 * همه با function_exists محافظت شده‌اند تا این فایل بتواند کنار یک وردپرس
 * واقعی هم بارگذاری شود.
 * ----------------------------------------------------------------------- */

if (!function_exists('__')) {
    function __($text, $domain = null) { return $text; }
}
if (!function_exists('esc_html__')) {
    function esc_html__($text, $domain = null) { return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('esc_attr__')) {
    function esc_attr__($text, $domain = null) { return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('wp_kses_post')) {
    function wp_kses_post($text) { return (string) $text; }
}
if (!function_exists('esc_html')) {
    function esc_html($text) { return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('esc_attr')) {
    function esc_attr($text) { return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('esc_url')) {
    function esc_url($url) { return filter_var((string) $url, FILTER_SANITIZE_URL); }
}
if (!function_exists('absint')) {
    function absint($value) { return abs((int) $value); }
}
if (!function_exists('sanitize_html_class')) {
    function sanitize_html_class($class, $fallback = '') {
        $class = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $class);

        return '' === $class ? $fallback : $class;
    }
}
if (!function_exists('wp_strip_all_tags')) {
    function wp_strip_all_tags($text) {
        return trim(strip_tags(preg_replace('@<(script|style)[^>]*?>.*?</\\1>@si', '', (string) $text)));
    }
}
if (!function_exists('wp_kses')) {
    /**
     * جایگزین سبکِ wp_kses برای تست.
     *
     * عمداً «تقریبی» نیست بلکه سخت‌گیرتر است: هر تگی که در فهرست سفید نباشد
     * کامل حذف می‌شود. اگر تستی با این نسخه سبز شود ولی با نسخهٔ واقعی نه،
     * یعنی فهرست سفیدمان مشکل دارد — که دقیقاً همان چیزی است که می‌خواهیم
     * بگیریم.
     */
    function wp_kses($text, $allowed) {
        $tags = '';

        foreach (array_keys((array) $allowed) as $tag) {
            $tags .= '<' . $tag . '>';
        }

        return strip_tags((string) $text, $tags);
    }
}
if (!function_exists('get_transient')) {
    function get_transient($key) { return $GLOBALS['__zig_transients'][$key] ?? false; }
}
if (!function_exists('set_transient')) {
    function set_transient($key, $value, $ttl = 0) {
        $GLOBALS['__zig_transients'][$key] = $value;

        return true;
    }
}

/* --------------------------------------------------------------------------
 * حداقلِ سیستم هوک
 *
 * فقط برای سنجیدن یک چیز هست: نگه‌داشتن و بازگرداندن فیلترهای مرتب‌سازی
 * ووکامرس. آن منطق دقیقاً همان جایی است که «وضعیت سراسری را کورکورانه
 * پاک نکن» تصمیم گرفته شد، و بدون یک ثبت‌کنندهٔ واقعی نمی‌شد سنجیدش.
 * ----------------------------------------------------------------------- */

if (!function_exists('add_filter')) {
    function add_filter($hook, $callback, $priority = 10, $args = 1) {
        $GLOBALS['__zig_filters'][$hook][zig_filter_id($callback)] = $priority;

        return true;
    }
}
if (!function_exists('remove_filter')) {
    function remove_filter($hook, $callback, $priority = 10) {
        unset($GLOBALS['__zig_filters'][$hook][zig_filter_id($callback)]);

        return true;
    }
}
if (!function_exists('has_filter')) {
    function has_filter($hook, $callback = false) {
        if (false === $callback) {
            return !empty($GLOBALS['__zig_filters'][$hook]);
        }

        return $GLOBALS['__zig_filters'][$hook][zig_filter_id($callback)] ?? false;
    }
}
if (!function_exists('zig_filter_id')) {
    function zig_filter_id($callback): string {
        if (is_array($callback)) {
            return (is_object($callback[0]) ? spl_object_hash($callback[0]) : (string) $callback[0]) . '::' . $callback[1];
        }

        return is_string($callback) ? $callback : spl_object_hash($callback);
    }
}
if (!function_exists('zig_reset_filters')) {
    function zig_reset_filters(): void {
        $GLOBALS['__zig_filters'] = [];
    }
}

/* --------------------------------------------------------------------------
 * چارچوب کوچک assert
 * ----------------------------------------------------------------------- */

final class Tests {

    private static int $passed = 0;
    private static array $failures = [];
    private static string $group = '';

    public static function group(string $name): void {
        self::$group = $name;
        printf("\n\033[1m%s\033[0m\n", $name);
    }

    public static function ok(string $label, bool $condition, string $detail = ''): void {
        if ($condition) {
            ++self::$passed;
            printf("  \033[32m✓\033[0m %s\n", $label);

            return;
        }

        self::$failures[] = self::$group . ' › ' . $label . ($detail ? ' — ' . $detail : '');
        printf("  \033[31m✗ %s\033[0m%s\n", $label, $detail ? ' — ' . $detail : '');
    }

    /** برابری دقیق، با نمایش هر دو مقدار در صورت شکست */
    public static function same(string $label, $actual, $expected): void {
        self::ok(
            $label,
            $actual === $expected,
            $actual === $expected ? '' : sprintf('got %s, expected %s', var_export($actual, true), var_export($expected, true))
        );
    }

    /** رشته‌ای که نباید در خروجی باشد */
    public static function blocks(string $label, string $haystack, string $needle): void {
        self::ok(
            $label,
            false === stripos($haystack, $needle),
            false === stripos($haystack, $needle) ? '' : sprintf('«%s» در خروجی ماند: %s', $needle, $haystack)
        );
    }

    /** رشته‌ای که باید در خروجی باشد */
    public static function keeps(string $label, string $haystack, string $needle): void {
        self::ok(
            $label,
            false !== stripos($haystack, $needle),
            false !== stripos($haystack, $needle) ? '' : sprintf('«%s» در خروجی نیست: %s', $needle, $haystack)
        );
    }

    /** کد خروج: صفر یعنی همه‌چیز سبز */
    public static function summary(): int {
        printf("\n%s\n", str_repeat('─', 60));

        if (!self::$failures) {
            printf("\033[32mهمهٔ %d سنجه پاس شد\033[0m\n", self::$passed);

            return 0;
        }

        printf("\033[31m%d شکست\033[0m، %d پاس\n\n", count(self::$failures), self::$passed);

        foreach (self::$failures as $failure) {
            printf("  • %s\n", $failure);
        }

        return 1;
    }
}
