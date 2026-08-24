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
     * کلیدِ سکشن (همان کلیدهایِ ‎Product_Section_Settings::SECTION_KEYS‎) =>
     * نشانهٔ «این ویجت واقعاً چیزی رندر کرد». برای پنج موردِ اول نشانه یک
     * کلاسِ CSS است (ریشهٔ خروجیِ همان ویجت، ثابت و غیرقابلِ‌تنظیم — این
     * جزئیاتِ کد است، نه چیزی که ادمین باید عوض کند). موردِ «چرا» ریپیترِ
     * جت‌اینجینِ خامی است که ما نساخته‌ایم و کلاسِ ریشه‌اش را نمی‌دانیم، پس
     * با یک تابعِ سنجشِ متفاوت بررسی می‌شود (‎self::why_section_has_content‎).
     *
     * کلاسِ سکشنِ *بیرونی* (چیزی که این نشانه‌ها را باید داخلش پیدا کند)
     * و اینکه اصلاً این سنجش برایِ هر کلید فعال باشد یا نه، از بیرون
     * می‌آید (‎$config‎) — رویِ سندِ Single Product در المنتور تنظیم
     * می‌شود، نگاه کنید به ‎Product_Section_Settings‎.
     */
    private const WIDGET_MARKERS = [
        'specs'       => '.zig-specs',
        'ability'     => '.zig-feature',
        'description' => '.zig-description',
        'video'       => '.zig-product-video',
        'downloads'   => '.zig-documents',
    ];

    /** کلیدی که با تابعِ سنجشِ جداگانه بررسی می‌شود، نه با کلاسِ نشانه */
    private const WHY_KEY = 'why';

    public static function boot(): void {
        add_action('template_redirect', [self::class, 'maybe_start_buffer']);
    }

    public static function maybe_start_buffer(): void {
        if (!self::should_guard()) {
            return;
        }

        /*
         * موقتاً همیشه پیکربندیِ پیش‌فرض (‎null‎ → ‎default_config()‎) —
         * ‎Product_Section_Settings::resolve()‎ که سند/تنظیماتِ المنتور
         * را می‌خواند فعلاً کنار گذاشته شده (نگاه کنید به توضیحِ
         * ‎Plugin::boot_filters()‎)، چون رویِ سایت مشکل ایجاد کرد.
         *
         * حتماً از یک بستارِ تک‌آرگومانی صدا زده شود، نه ارجاعِ مستقیمِ
         * ‎[self::class, 'filter_html']‎: ‎ob_start‎ کالبکش را با *دو*
         * آرگومان صدا می‌زند (‎$buffer, $phase‎)، و چون ‎filter_html‎ حالا
         * پارامترِ دومش را ‎?array‎ تایپ کرده، آن ‎$phase‎ی عددی مستقیم به
         * ‎$config‎ می‌رسید و ‎TypeError‎ می‌داد — دقیقاً همان‌جا که خودم
         * تستش کردم و گرفتم، پیش از اینکه به این نسخه برسد.
         */
        ob_start(static fn (string $html): string => self::filter_html($html));
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
     * پیکربندیِ پیش‌فرض — همان چیزی که پیش از وجودِ تنظیماتِ سند استفاده
     * می‌شد؛ وقتی ‎$config‎ در ‎filter_html()‎ داده نشود (مثلاً از تست)
     * همین به‌کار می‌رود.
     *
     * @return array<string,array{enabled:bool,class:string}>
     */
    private static function default_config(): array {
        if (class_exists(__NAMESPACE__ . '\\Product_Section_Settings')) {
            $config = [];

            foreach (Product_Section_Settings::SECTION_KEYS as $key) {
                $config[$key] = ['enabled' => true, 'class' => Product_Section_Settings::DEFAULT_CLASSES[$key]];
            }

            return $config;
        }

        // نسخهٔ پشتیبان اگر فایلِ تنظیمات به هر دلیلی لود نشده باشد
        return [
            'specs'       => ['enabled' => true, 'class' => 'zig-product-Specifications'],
            'ability'     => ['enabled' => true, 'class' => 'zig-product-ability'],
            'description' => ['enabled' => true, 'class' => 'zig-product-description'],
            'why'         => ['enabled' => true, 'class' => 'zig-product-why'],
            'video'       => ['enabled' => true, 'class' => 'zig-product-video'],
            'downloads'   => ['enabled' => true, 'class' => 'zig-product-downloads'],
        ];
    }

    /**
     * بدنهٔ سنجش‌پذیرِ بدونِ وردپرس — کالبکِ ‎ob_start‎ همیشه از یک بستارِ
     * تک‌آرگومانی صدایش می‌زند (نگاه کنید به ‎maybe_start_buffer‎)، ولی
     * خودِ متد هم مستقیماً در تست، با یا بدونِ ‎$config‎، قابلِ‌فراخوانی است.
     *
     * @param array<string,array{enabled:bool,class:string}>|null $config
     */
    public static function filter_html(string $html, ?array $config = null): string {
        $config ??= self::default_config();

        if ('' === trim($html) || !self::may_contain_a_section($html, $config)) {
            return $html;
        }

        /*
         * ‎DOMDocument::loadHTML()‎ رویِ کلِ HTMLِ صفحه (نه فقط ناحیهٔ
         * محصول) چند برابرِ حجمِ همین رشته حافظه می‌گیرد — رویِ صفحاتِ
         * سنگین (هدر/مگامنو/محصولاتِ مرتبط/فوتر) این می‌تواند از سقفِ
         * ‎memory_limit‎ رد شود. فاتالِ حافظهٔ گزارش‌شده معمولاً جایِ دیگری
         * (مثلاً یک کوئریِ ‎wpdb‎یِ بعدی) ظاهر می‌شود، چون PHP فاتال را
         * همان‌جا گزارش می‌کند که آخرین ‎emalloc‎ شکست خورده، نه جایی که
         * واقعاً حافظه مصرف شد. بدونِ دسترسیِ زنده به سایت نمی‌شود این
         * پارس را با اطمینان به یک ناحیهٔ کوچک‌تر محدود کرد (نمی‌دانیم
         * ساختارِ دقیقِ خروجیِ المنتور/تم را)، پس به‌جایِ ریسکِ فاتال، وقتی
         * حاشیهٔ حافظهٔ کافی نیست این حذفِ خودکار را فقط برایِ همین
         * درخواست ساکت کنار می‌گذاریم — صفحه با سکشن‌هایِ خالی (نه خراب)
         * رندر می‌شود، که همیشه بهتر از صفحهٔ سفید است.
         */
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
        $removed = false;

        foreach (self::WIDGET_MARKERS as $key => $marker_selector) {
            if (!self::section_enabled($config, $key)) {
                continue;
            }

            foreach (self::find_by_class($xpath, self::section_class($config, $key)) as $section) {
                if (self::has_descendant_class($xpath, $section, ltrim($marker_selector, '.'))) {
                    continue;
                }

                $section->parentNode->removeChild($section);
                $removed = true;
            }
        }

        if (self::section_enabled($config, self::WHY_KEY)) {
            foreach (self::find_by_class($xpath, self::section_class($config, self::WHY_KEY)) as $section) {
                if (self::why_section_has_content($section)) {
                    continue;
                }

                $section->parentNode->removeChild($section);
                $removed = true;
            }
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

    /**
     * آیا به‌اندازهٔ کافی حاشیهٔ حافظه برایِ پارسِ امنِ این HTML هست؟
     *
     * ‎DOMDocument::loadHTML()‎ در عمل حدودِ ۵ تا ۱۰ برابرِ حجمِ رشتهٔ
     * ورودی حافظه می‌گیرد (گرهِ درخت + attribute mapها)، بعلاوهٔ یک
     * کپیِ کاملِ خروجی در ‎saveHTML()‎/‎mb_convert_encoding()‎. ضریبِ ۱۲
     * عمداً سخاوتمندانه است — هدف جلوگیریِ قطعی از فاتال است، نه
     * تخمینِ دقیق.
     *
     * پارامترهایِ اختیاریِ آخر فقط برایِ تست‌اند؛ در استفادهٔ واقعی همیشه
     * از ‎ini_get('memory_limit')‎ و ‎memory_get_usage(true)‎یِ واقعی
     * استفاده می‌شود.
     */
    private static function has_memory_headroom(string $html, ?string $memory_limit = null, ?int $current_usage = null): bool {
        $memory_limit ??= (string) ini_get('memory_limit');
        $limit_bytes = self::parse_memory_limit($memory_limit);

        // نامحدود (‎-1‎) یا مقدارِ نامفهوم/صفر: بررسی معنا ندارد، اجازه بده.
        if ($limit_bytes <= 0) {
            return true;
        }

        $current_usage ??= memory_get_usage(true);
        $estimated_need = strlen($html) * 12;

        // ۱۵٪ حاشیهٔ اضافه برایِ بقیهٔ درخواست (چیزی که بعدِ این فیلتر هنوز اجرا می‌شود).
        $safety_ceiling = (int) ($limit_bytes * 0.85);

        return ($current_usage + $estimated_need) < $safety_ceiling;
    }

    /** رشتهٔ ‎memory_limit‎یِ php.ini (مثلِ ‎"256M"‎، ‎"1G"‎، ‎"-1"‎) را به بایت تبدیل می‌کند. */
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

    /**
     * پیش‌بررسیِ ارزان قبلِ باز کردنِ ‎DOMDocument‎: اگر هیچ‌کدام از
     * کلاس‌هایِ سکشنِ فعال حتی به‌صورتِ رشته هم در HTML نیست، پارس‌کردنِ
     * کلِ صفحه بی‌فایده است. حالا که کلاسِ هر سکشن قابلِ‌تنظیم است (نه
     * فقط پیشوندِ ثابتِ ‎zig-product-‎)، این بررسی باید رویِ همان کلاس‌هایِ
     * واقعیِ پیکربندی‌شده انجام شود.
     *
     * @param array<string,array{enabled:bool,class:string}> $config
     */
    private static function may_contain_a_section(string $html, array $config): bool {
        $keys = array_merge(array_keys(self::WIDGET_MARKERS), [self::WHY_KEY]);

        foreach ($keys as $key) {
            if (!self::section_enabled($config, $key)) {
                continue;
            }

            if (false !== stripos($html, self::section_class($config, $key))) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string,array{enabled:bool,class:string}> $config */
    private static function section_enabled(array $config, string $key): bool {
        return (bool) ($config[$key]['enabled'] ?? true);
    }

    /** @param array<string,array{enabled:bool,class:string}> $config */
    private static function section_class(array $config, string $key): string {
        $class = trim((string) ($config[$key]['class'] ?? ''));

        return '' !== $class ? $class : (self::default_config()[$key]['class'] ?? '');
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
