<?php
namespace Zig3d_Widgets;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * حذفِ سکشنِ خالیِ سوالاتِ متداول.
 *
 * درخواستِ کاربر: اگر ویجتِ FAQ هیچ سوالی رندر نکرد (نه ریپیتر چیزی
 * داشت، نه آیتمِ دستی‌ای پر بود)، سکشنِ المنتوریِ *دورِ* آن —
 * ‎.zig-faq-section‎، کلاسی که خودِ ادمین رویِ آن سکشن می‌گذارد — کلاً
 * از صفحه پنهان شود. خودِ ویجت از قبل رویِ خروجیِ خالی سکوت می‌کند
 * (‎return;‎ بدونِ اکو، جز نوتیسِ ادیتور)، ولی سکشنِ دورش (پدینگ،
 * پس‌زمینه، تیترِ احتمالیِ همان سکشن) هنوز چاپ می‌شود و یک فضایِ خالی
 * روی صفحه می‌ماند.
 *
 * چرا یک کلاسِ جدا و مستقل از ‎Product_Section_Guard‎، نه یک ورودیِ
 * تازه در ‎WIDGET_MARKERS‎ی همان کلاس: آن نگهبان عمداً فقط رویِ صفحهٔ
 * تکِ محصول اجرا می‌شود (‎is_singular('product')‎) — چون پنج سکشنِ
 * دیگرش فقط از همان قالب می‌آیند. FAQ اما رویِ آرشیوِ یک ترم هم به‌کار
 * می‌رود (رجوع کنید به ‎Faq::read_current_meta()‎) و می‌تواند رویِ هر
 * صفحه‌ای بنشیند. باز کردنِ محدودیتِ صفحهٔ آن کلاسِ دیگر یعنی ریسکِ
 * رگرسیون رویِ پنج سکشنی که همین حالا درست کار می‌کنند — دقیقاً همان
 * کلاسی که کامنتِ خودش می‌گوید «دو بار قبلاً خروجیِ صفحه را خراب کرده»؛
 * یک فایلِ کوچک و مستقل امن‌تر است تا دستکاریِ آن.
 *
 * تکنیک عیناً همان ‎Product_Section_Guard‎ است — نگاه کنید به داک‌بلاکِ
 * ‎hide_style()‎ی آن‌جا برایِ چراییِ «هایدکردن با CSS» به‌جایِ «حذف از
 * DOM»: سریالایزِ دوباره اسکیپِ HTML را می‌شکند، پس اینجا هم سند فقط
 * *خوانده* می‌شود و خروجی صرفاً یک الحاق است، نه بازنویسی.
 */
final class Faq_Section_Guard {

    private const SECTION_CLASS = 'zig-faq-section';
    private const MARKER_CLASS = 'zig-faq';

    public static function boot(): void {
        add_action('template_redirect', [self::class, 'maybe_start_buffer']);
    }

    public static function maybe_start_buffer(): void {
        if (!self::should_guard()) {
            return;
        }

        ob_start(static fn (string $html): string => self::filter_html($html));
    }

    /**
     * هر بازدیدِ واقعیِ فرانت‌اند — نه فقط صفحهٔ محصول، چون FAQ می‌تواند
     * رویِ هر قالبی بنشیند. نه در ادمین/آژاکس/رست، نه در ادیتور/
     * پیش‌نمایشِ المنتور (وگرنه ادمین هیچ‌وقت نوتیسِ «سوالی اضافه نشده»
     * را نمی‌بیند تا بفهمد چرا سکشنش خالی است).
     */
    private static function should_guard(): bool {
        if (is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return false;
        }

        return !self::is_elementor_editing();
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

    /**
     * بدنهٔ سنجش‌پذیرِ بدونِ وردپرس — کالبکِ ‎ob_start‎ همیشه از یک بستارِ
     * تک‌آرگومانی صدایش می‌زند (نگاه کنید به ‎maybe_start_buffer‎)، ولی
     * خودِ متد هم مستقیماً در تست قابلِ‌فراخوانی است.
     */
    public static function filter_html(string $html): string {
        /*
         * پیش‌بررسیِ ارزان قبلِ باز کردنِ ‎DOMDocument‎: اگر کلاسِ سکشن
         * حتی به‌صورتِ رشته هم در HTML نیست، پارس‌کردنِ کلِ صفحه بی‌فایده
         * است — همان چیزی که این نگهبان را روی صفحاتِ بی‌ربط تقریباً
         * بی‌هزینه نگه می‌دارد.
         */
        if ('' === trim($html) || false === stripos($html, self::SECTION_CLASS)) {
            return $html;
        }

        if (!self::has_memory_headroom($html)) {
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
        $has_empty = false;

        foreach (self::find_by_class($xpath, self::SECTION_CLASS) as $section) {
            if (self::has_descendant_class($xpath, $section, self::MARKER_CLASS)) {
                continue;
            }

            $has_empty = true;

            break;
        }

        if (!$has_empty) {
            return $html;
        }

        return $html . sprintf(
            '<style id="zig-faq-empty-sections">.%s{display:none !important}</style>',
            self::SECTION_CLASS
        );
    }

    /** همان محاسبهٔ ‎Product_Section_Guard::has_memory_headroom()‎ — رجوع کنید به داک‌بلاکِ آن‌جا */
    private static function has_memory_headroom(string $html, ?string $memory_limit = null, ?int $current_usage = null): bool {
        $memory_limit ??= (string) ini_get('memory_limit');
        $limit_bytes = self::parse_memory_limit($memory_limit);

        if ($limit_bytes <= 0) {
            return true;
        }

        $current_usage ??= memory_get_usage(true);
        $estimated_need = strlen($html) * 12;

        $safety_ceiling = (int) ($limit_bytes * 0.85);

        return ($current_usage + $estimated_need) < $safety_ceiling;
    }

    private static function parse_memory_limit(string $memory_limit): int {
        $memory_limit = trim($memory_limit);

        if ('' === $memory_limit || '-1' === $memory_limit) {
            return -1;
        }

        if (!preg_match('/^(\d+)([KMG]?)$/i', $memory_limit, $matches)) {
            return -1;
        }

        $value = (int) $matches[1];
        $unit = strtoupper($matches[2] ?? '');

        $multiplier = ['K' => 1024, 'M' => 1024 ** 2, 'G' => 1024 ** 3][$unit] ?? 1;

        return $value * $multiplier;
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
}
