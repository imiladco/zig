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
    'HOUR_IN_SECONDS' => 3600,
    'DAY_IN_SECONDS'  => 86400,
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
if (!function_exists('esc_html_e')) {
    function esc_html_e($text, $domain = null) { echo htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('esc_attr_e')) {
    function esc_attr_e($text, $domain = null) { echo htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'); }
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
/**
 * ‎add_query_arg‎ی کافی برایِ چیزی که این افزونه ازش می‌خواهد: چسباندنِ
 * چند پارامتر به یک آدرس. تعریفش این‌جاست نه در یک فایلِ تست، چون بیش
 * از یک سنجه به آن نیاز دارد و تعریفِ پراکنده همان چیزی است که یک‌بار
 * در اجرایِ کاملِ سوییت به «تعریفِ دوباره» ختم شد.
 */
if (!function_exists('add_query_arg')) {
    function add_query_arg($args, $url = '') {
        if (!is_array($args)) {
            $args = [$args => $url];
            $url  = func_num_args() > 2 ? func_get_arg(2) : '';
        }

        return $url . (false === strpos((string) $url, '?') ? '?' : '&') . http_build_query($args);
    }
}
if (!function_exists('esc_url_raw')) {
    function esc_url_raw($url) { return filter_var((string) $url, FILTER_SANITIZE_URL); }
}
/** جفتِ ‎add_query_arg‎ بالا — پارامتر(ها) را از رشتهٔ کوئری پاک می‌کند */
if (!function_exists('remove_query_arg')) {
    function remove_query_arg($keys, $url = '') {
        $keys  = (array) $keys;
        $parts = parse_url((string) $url);
        $query = [];

        if (isset($parts['query'])) {
            parse_str($parts['query'], $query);

            foreach ($keys as $key) {
                unset($query[$key]);
            }
        }

        $base = ($parts['scheme'] ?? '') && ($parts['host'] ?? '')
            ? $parts['scheme'] . '://' . $parts['host'] . ($parts['path'] ?? '')
            : (string) ($parts['path'] ?? $url);

        return $query ? $base . '?' . http_build_query($query) : $base;
    }
}
if (!function_exists('rest_url')) {
    function rest_url($path = '') { return 'https://zig3d.test/wp-json/' . ltrim((string) $path, '/'); }
}
if (!function_exists('home_url')) {
    function home_url($path = '') { return 'https://zig3d.test' . ('' !== (string) $path ? '/' . ltrim((string) $path, '/') : ''); }
}
/** همتایِ سبک‌شدهٔ رفتارِ پیش‌فرضِ وردپرس: ‎?p={id}‎ روی خانه */
if (!function_exists('wp_get_shortlink')) {
    function wp_get_shortlink($id = 0) { return home_url('/?p=' . (int) $id); }
}
/**
 * فقط برایِ رشته‌هایِ سریالایزشدهٔ PHP — دقیقاً همان چیزی که Repeaterِ
 * JetEngine در ‎postmeta‎ ذخیره می‌کند. اگر ورودی از قبل آرایه باشد (مثلِ
 * فیکسچرهایِ تست که مستقیم آرایه می‌گذارند)، دست‌نخورده برمی‌گردد —
 * همان رفتارِ نسخهٔ واقعیِ وردپرس.
 */
if (!function_exists('maybe_unserialize')) {
    function maybe_unserialize($value) {
        if (!is_string($value)) {
            return $value;
        }

        if ('a:0:{}' === $value || preg_match('/^[aOs]:\d+:/', $value)) {
            $unserialized = @unserialize($value);

            return false !== $unserialized ? $unserialized : $value;
        }

        return $value;
    }
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
if (!function_exists('sanitize_title')) {
    /*
     * تقریبِ ‎sanitize_title()‎ی وردپرس — همان قاعده‌ای که پیش از این در
     * ‎Query_State::slug()‎ به‌عنوانِ جایگزینِ بی‌وردپرس نوشته شده بود
     * (کاراکترهایِ ساختاریِ آدرس حذف، فاصله به خط‌تیره). اینجا آمد چون
     * حالا مصرف‌کنندهٔ دومی هم دارد (Configurator) و نباید در دو فایلِ
     * تست دو تعریفِ متفاوت وجود داشته باشد — همان چیزی که یک بار باعثِ
     * ناسازگاریِ بی‌صدا شد.
     *
     * حروفِ فارسی دست‌نخورده می‌مانند: وردپرسِ واقعی هم برایِ
     * غیرلاتین‌ها آوانگاری نمی‌کند، فقط lower و رمزگشاییِ درصدی می‌کند.
     */
    function sanitize_title($title) {
        $title = mb_strtolower(trim(rawurldecode((string) $title)));
        $title = preg_replace('/[\x00-\x1F\x7F<>"\'`\\\\\/&?#,|=]+/u', '', $title);
        $title = preg_replace('/[\s_]+/u', '-', $title);

        return trim((string) $title, '-');
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
if (!function_exists('size_format')) {
    function size_format($bytes, $decimals = 0) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, $decimals) . ' ' . $units[$i];
    }
}
if (!function_exists('wpautop')) {
    /** ساده‌شدهٔ ‎wpautop‎ واقعی: فقط کافی است که با پاراگراف‌بندیِ خودِ ‎the_content‎ فرق کند تا سنجه‌ها بتوانند تشخیص بدهند کدام مسیر اجرا شده. */
    function wpautop($text) {
        $text = trim((string) $text);

        if ('' === $text) {
            return '';
        }

        $blocks = array_filter(array_map('trim', preg_split('/\n\s*\n/', $text)), 'strlen');

        return implode('', array_map(static fn($block) => '<p>' . $block . "</p>\n", $blocks));
    }
}
if (!function_exists('wp_date')) {
    function wp_date($format, $timestamp = null) {
        return date($format, $timestamp ?? time());
    }
}
if (!function_exists('date_i18n')) {
    function date_i18n($format, $timestamp = false) {
        return date($format, $timestamp !== false ? $timestamp : time());
    }
}

/* --------------------------------------------------------------------------
 * حداقلِ سیستم هوک
 *
 * فقط برای سنجیدن یک چیز هست: نگه‌داشتن و بازگرداندن فیلترهای مرتب‌سازی
 * ووکامرس. آن منطق دقیقاً همان جایی است که «وضعیت سراسری را کورکورانه
 * پاک نکن» تصمیم گرفته شد، و بدون یک ثبت‌کنندهٔ واقعی نمی‌شد سنجیدش.
 * ----------------------------------------------------------------------- */

/*
 * یک کال‌بک می‌تواند هم‌زمان روی چند اولویت بسته باشد — وردپرس هرکدام را
 * ورودی جدا حساب می‌کند. مدل‌نکردن این، تستِ «مالِ چه کسی است» را بی‌اثر
 * می‌کرد: دقیقاً همان‌جایی که ووکامرس روی ۱۰ ثبت می‌کند در حالی که کس
 * دیگری همان متد را روی ۱۲ بسته.
 */
if (!function_exists('add_filter')) {
    function add_filter($hook, $callback, $priority = 10, $args = 1) {
        $GLOBALS['__zig_filters'][$hook][zig_filter_id($callback)][(int) $priority] = [
            'callback' => $callback,
            'args'     => max(1, (int) $args),
        ];

        return true;
    }
}
if (!function_exists('apply_filters')) {
    /**
     * اجرای واقعی، نه فقط ثبت.
     *
     * نقاط توسعهٔ افزونه بدون این، تست‌نشده می‌مانند: می‌شد دید که کسی
     * ثبت شده، ولی نه اینکه مقدارش واقعاً به کجا می‌رسد — و تفاوت این دو
     * همان‌جایی است که یک فیلترِ بی‌اثر ماه‌ها بی‌سروصدا می‌ماند.
     */
    function apply_filters($hook, $value, ...$args) {
        $queue = [];

        foreach ($GLOBALS['__zig_filters'][$hook] ?? [] as $registered) {
            foreach ($registered as $priority => $entry) {
                $queue[$priority][] = $entry;
            }
        }

        ksort($queue, SORT_NUMERIC);

        foreach ($queue as $entries) {
            foreach ($entries as $entry) {
                $value = call_user_func_array(
                    $entry['callback'],
                    array_slice(array_merge([$value], $args), 0, $entry['args'])
                );
            }
        }

        return $value;
    }
}
if (!function_exists('remove_filter')) {
    function remove_filter($hook, $callback, $priority = 10) {
        unset($GLOBALS['__zig_filters'][$hook][zig_filter_id($callback)][(int) $priority]);

        if (empty($GLOBALS['__zig_filters'][$hook][zig_filter_id($callback)])) {
            unset($GLOBALS['__zig_filters'][$hook][zig_filter_id($callback)]);
        }

        return true;
    }
}
if (!function_exists('has_filter')) {
    /** مثل وردپرس: کمترین اولویتِ ثبت‌شده را برمی‌گرداند */
    function has_filter($hook, $callback = false) {
        if (false === $callback) {
            return !empty($GLOBALS['__zig_filters'][$hook]);
        }

        $found = $GLOBALS['__zig_filters'][$hook][zig_filter_id($callback)] ?? [];

        return $found ? min(array_keys($found)) : false;
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

/**
 * بخشِ یک ویجت از شیتِ مشترک، *فقط* تا شروعِ بخشِ بعدی.
 *
 * پیش از این هر تست از عنوانِ خودش تا آخرِ فایل را برمی‌داشت؛ درست بود
 * تا وقتی که بخشِ تازه‌ای ته فایل اضافه شد و ناگهان سنجهٔ «هر انتخاب‌گر
 * با ریشهٔ .zig-search شروع می‌شود» رویِ قواعدِ ویجتِ دیگری اجرا شد و
 * شکست. مرزِ بالا و پایین، هر دو لازم است.
 */
function zig_css_section(string $css, string $title): string {
    $start = strpos($css, "\n   " . $title . "\n");

    if (false === $start) {
        return '';
    }

    // از خودِ سرتیتر جلوتر می‌رویم تا بنرِ همین بخش، «بخشِ بعدی» شمرده نشود
    $body = substr($css, $start + strlen($title) + 5);
    $next = strpos($body, '/* ======');

    return false === $next ? $body : substr($body, 0, $next);
}

/**
 * بدنهٔ یک بلوکِ ‎@media‎ (یا هر بلوکِ آکولاددار)، از روی سرآیندش.
 *
 * برایِ سنجه‌هایی که باید *داخلِ* یک بلوکِ خاص را ببینند: «حرکتِ کم مدت
 * را صفر می‌کند» اگر رویِ کلِ فایل اجرا شود، با یک ‎0s‎ی از هر جایِ دیگر
 * هم پاس می‌شود و دیگر چیزی را تضمین نمی‌کند.
 */
function zig_css_block(string $css, string $header): string {
    $start = strpos($css, $header);

    if (false === $start) {
        return '';
    }

    $open = strpos($css, '{', $start);

    if (false === $open) {
        return '';
    }

    $depth = 0;
    $length = strlen($css);

    for ($i = $open; $i < $length; $i++) {
        if ('{' === $css[$i]) {
            $depth++;
        } elseif ('}' === $css[$i]) {
            $depth--;

            if (0 === $depth) {
                return substr($css, $open + 1, $i - $open - 1);
            }
        }
    }

    return '';
}

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
