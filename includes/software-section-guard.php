<?php
namespace Zig3d_Widgets;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * حذفِ سکشن‌های خالیِ صفحهٔ تکِ نرم‌افزار — همان کاری که
 * ‎Product_Section_Guard‎ برایِ صفحهٔ محصول می‌کند، ولی با یک تفاوتِ
 * بنیادی در روشِ هدف‌گیری.
 *
 * چرا کلاس‌محور نیست: نگهبانِ محصول به کلاسِ CSSی تکیه می‌کند که ادمین
 * دستی رویِ هر سکشن می‌گذارد (‎zig-product-ability‎ و…). رویِ قالبِ
 * «Single Download» هیچ سکشنی ‎_css_classes‎ ندارد — یعنی آن روش
 * این‌جا هیچ‌چیز پیدا نمی‌کند و بی‌صدا بی‌اثر می‌ماند. به‌جایش این کلاس
 * سکشن‌ها را از رویِ **شناسهٔ خودِ المنتور** (‎data-id‎) هدف می‌گیرد، و
 * آن شناسه‌ها را در زمانِ اجرا از خودِ قالب می‌خواند — پس نه ادمین باید
 * چیزی تنظیم کند، نه شناسه‌ای در کد هاردکد می‌شود.
 *
 * قاعدهٔ ایمنی (مهم‌ترین بخش): یک سکشن فقط وقتی نامزدِ حذف است که
 * *همهٔ* ویجت‌هایِ محتوایی‌اش یا مالِ خودمان باشند یا صرفاً «قابِ» همان
 * سکشن (تیتر/پاراگراف/جداکننده). سکشنی که چیزِ دیگری هم دارد — مثلِ
 * سکشنِ بالایِ همان قالب که کنارِ «سیستم‌عامل‌های سازگار» عکس و دکمه و
 * کارت هم دارد — هیچ‌وقت حذف نمی‌شود، حتی اگر ویجتِ ما داخلش خالی
 * دربیاید. بدونِ این قاعده، یک فیلدِ خالی می‌توانست کلِ هدرِ صفحه را
 * ببرد.
 */
final class Software_Section_Guard {

    /**
     * ‎widgetType‎ی ویجت‌هایِ خودمان => کلاسِ ریشه‌ای که *فقط وقتی واقعاً
     * چیزی رندر کردند* چاپ می‌شود. نبودِ این کلاس داخلِ سکشن یعنی آن
     * ویجت این‌بار ساکت مانده.
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
     * محتوایِ اصلی نیامد، خودشان هم باید بروند (تیترِ «اطلاعات فنی» بدونِ
     * جدول بی‌معنا است). حضورشان مانعِ نامزدشدنِ سکشن نمی‌شود.
     *
     * هم نام‌هایِ کلاسیکِ المنتور و هم معادل‌هایِ اتمیکِ (V4) همان‌ها.
     */
    private const CHROME_WIDGETS = [
        'heading', 'e-heading',
        'text-editor', 'e-paragraph',
        'divider', 'e-divider',
        'spacer', 'e-spacer',
    ];

    public static function boot(): void {
        add_action('template_redirect', [self::class, 'maybe_start_buffer']);
    }

    public static function maybe_start_buffer(): void {
        if (!self::should_guard()) {
            return;
        }

        // بستارِ تک‌آرگومانی لازم است: ‎ob_start‎ کالبک را با دو آرگومان
        // (‎$buffer, $phase‎) صدا می‌زند و ‎$phase‎ی عددی به پارامترِ دومِ
        // آرایه‌ایِ ‎filter_html()‎ می‌خورد — همان تلهٔ ‎Product_Section_Guard‎.
        ob_start(static fn (string $html): string => self::filter_html($html));
    }

    private static function should_guard(): bool {
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

    /**
     * بدنهٔ سنجش‌پذیر. ‎$sections‎ اگر داده شود (تست)، هیچ فراخوانیِ
     * وردپرسی لازم نیست — همان نگاشتِ «شناسهٔ سکشن => کلاس‌هایِ نشانه»
     * که در تولید از خودِ قالب خوانده می‌شود.
     *
     * @param array<string,string[]>|null $sections
     */
    public static function filter_html(string $html, ?array $sections = null): string {
        if ('' === trim($html)) {
            return $html;
        }

        $sections ??= self::sections_from_template();

        if (!$sections) {
            return $html;
        }

        // پیش‌بررسیِ ارزان: اگر هیچ‌کدام از این شناسه‌ها حتی به‌صورتِ رشته
        // در صفحه نیست، پارسِ کلِ سند بی‌فایده است.
        $present = false;

        foreach (array_keys($sections) as $id) {
            if (false !== strpos($html, 'data-id="' . $id . '"')) {
                $present = true;

                break;
            }
        }

        if (!$present || !self::has_memory_headroom($html)) {
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
        $empty = [];

        foreach ($sections as $id => $markers) {
            $section = self::find_by_data_id($xpath, (string) $id);

            if (null === $section) {
                continue;
            }

            foreach ((array) $markers as $marker) {
                if (self::has_descendant_class($xpath, $section, (string) $marker)) {
                    continue 2; // این سکشن چیزی رندر کرده — رهایش کن
                }
            }

            $empty[] = (string) $id;
        }

        if (!$empty) {
            return $html;
        }

        return $html . self::hide_style($empty);
    }

    /**
     * نگاشتِ «شناسهٔ سکشنِ سطح‌بالا => کلاس‌هایِ نشانهٔ داخلش»، مستقیم از
     * دادهٔ خودِ قالبِ فعالِ این نوعِ پست.
     *
     * @param array<int,mixed>|null $data
     * @return array<string,string[]>
     */
    public static function sections_from_template(?array $data = null): array {
        $data ??= self::template_data();

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
            self::collect_widgets($section, $widgets);

            $markers = [];
            $guardable = true;

            foreach ($widgets as $widget) {
                if (isset(self::WIDGET_MARKERS[$widget])) {
                    $markers[] = self::WIDGET_MARKERS[$widget];

                    continue;
                }

                if (in_array($widget, self::CHROME_WIDGETS, true)) {
                    continue;
                }

                // چیزی که نه مالِ ماست نه قاب — این سکشن محتوایِ مستقل
                // دارد و هیچ‌وقت نباید حذف شود.
                $guardable = false;

                break;
            }

            if ($guardable && $markers) {
                $out[$id] = array_values(array_unique($markers));
            }
        }

        return $out;
    }

    /**
     * @param array<string,mixed> $element
     * @param string[] $widgets
     */
    private static function collect_widgets(array $element, array &$widgets): void {
        $widget = (string) ($element['widgetType'] ?? '');

        if ('' !== $widget) {
            $widgets[] = $widget;
        }

        foreach ((array) ($element['elements'] ?? []) as $child) {
            if (is_array($child)) {
                self::collect_widgets($child, $widgets);
            }
        }
    }

    /** @return array<int,mixed> */
    private static function template_data(): array {
        $template_id = self::active_template_id();

        if ($template_id <= 0) {
            return [];
        }

        $raw = get_post_meta($template_id, '_elementor_data', true);

        if (!is_string($raw) || '' === $raw) {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * قالبِ Theme Builderی که شرطش این نوعِ پست است. به‌جایِ هاردکدکردنِ
     * یک شناسه، شرط‌هایِ ذخیره‌شدهٔ خودِ المنتور خوانده می‌شود — پس اگر
     * قالب عوض/بازسازی شد، این‌جا چیزی نباید تغییر کند.
     */
    private static function active_template_id(): int {
        $post_type = self::post_type();

        if ('' === $post_type) {
            return 0;
        }

        $templates = get_posts([
            'post_type'      => 'elementor_library',
            'post_status'    => 'publish',
            'posts_per_page' => 50,
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ]);

        $needle = 'singular/' . $post_type;

        foreach ((array) $templates as $template_id) {
            $conditions = get_post_meta((int) $template_id, '_elementor_conditions', true);

            foreach ((array) $conditions as $condition) {
                if (is_string($condition) && false !== strpos($condition, $needle)) {
                    return (int) $template_id;
                }
            }
        }

        return 0;
    }

    /**
     * هایدکردن با CSS، نه حذف از DOM — دلیلش عیناً همان است که در
     * ‎Product_Section_Guard::hide_style()‎ مفصل توضیح داده شده:
     * سریالایزِ دوبارهٔ سند اسکیپِ HTML را می‌شکند. این‌جا سند فقط
     * *خوانده* می‌شود و خروجی یک الحاقِ محض است.
     *
     * ‎!important‎ لازم است چون سلکتورِ ما تک‌ویژگی‌ای است و باید از
     * سلکتورهایِ چندکلاسهٔ خودِ المنتور بالاتر بایستد.
     *
     * @param string[] $ids
     */
    private static function hide_style(array $ids): string {
        $selectors = [];

        foreach ($ids as $id) {
            if (!preg_match('/^[A-Za-z0-9_-]+$/', $id)) {
                continue;
            }

            $selectors[] = sprintf('[data-id="%s"]', $id);
        }

        if (!$selectors) {
            return '';
        }

        return sprintf(
            '<style id="zig-software-empty-sections">%s{display:none !important}</style>',
            implode(',', $selectors)
        );
    }

    private static function find_by_data_id(\DOMXPath $xpath, string $id): ?\DOMElement {
        $nodes = $xpath->query(sprintf('.//*[@data-id="%s"]', $id));

        foreach ($nodes as $node) {
            if ($node instanceof \DOMElement) {
                return $node;
            }
        }

        return null;
    }

    private static function has_descendant_class(\DOMXPath $xpath, \DOMElement $context, string $class): bool {
        $query = sprintf(
            './/*[contains(concat(" ", normalize-space(@class), " "), " %s ")]',
            $class
        );

        return $xpath->query($query, $context)->length > 0;
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
}
