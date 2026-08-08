<?php
namespace Zig3d_Widgets;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * وضعیت آرشیو: کدام فیلترها فعال‌اند، با چه ترتیبی، و صفحهٔ چندم.
 *
 * چرا یک شیء جدا و نه چند متغیر پراکنده: این ویجت هم‌زمان فیلتر، ترتیب،
 * صفحه، صفحات لودشده، شمارش فست و تاریخچهٔ مرورگر را نگه می‌دارد. اگر هر
 * کدام جای خودش خوانده شود — یکی از ‎$_GET‎، یکی از DOM، یکی از یک متغیر
 * سراسری — آن‌وقت «وضعیت فعلی» دیگر یک چیز نیست، چند روایت است که فقط
 * معمولاً با هم می‌خوانند. سرِ دکمهٔ back مرورگر همان‌جاست که از هم می‌پاشند.
 *
 * شیء تغییرناپذیر است: هر تغییری یک نمونهٔ تازه می‌سازد. یعنی نمی‌شود
 * وضعیتی را که برای ساختن کلید کش استفاده شده، بعد از آن بی‌سروصدا عوض کرد.
 *
 * قرارداد آدرس عمداً همان قرارداد خودِ ووکامرس است (‎filter_brand=up3d,vhf‎)
 * نه یک قرارداد تازه. دلیلش این نیست که قشنگ‌تر است؛ این است که لینک‌های
 * موجود، ابزارک لایه‌ای خودِ ووکامرس، و هر کاری که تا امروز روی سئوی این
 * آدرس‌ها شده، همه با همین شکل کار می‌کنند.
 */
final class Query_State {

    /** پیشوند پارامتر فیلتر در آدرس، مطابق ووکامرس */
    public const FILTER_PREFIX = 'filter_';

    /**
     * پیشوند پارامتر اپراتور، مطابق ووکامرس.
     *
     * وجودش اختیاری نیست. ووکامرس وقتی این پارامتر نباشد، ‎and‎ فرض می‌کند:
     *
     *     $chosen[$taxonomy]['query_type'] = $query_type
     *         ? $query_type
     *         : apply_filters('woocommerce_layered_nav_default_query_type', 'and');
     *                                                                      ^^^^^
     *
     * یعنی ‎?filter_brand=up3d,vhf‎ به‌تنهایی «هم UP3D و هم VHF» معنا
     * می‌دهد — که برای دو برند، همیشه صفر نتیجه است. ما درون هر گروه ‎OR‎
     * می‌خواهیم، پس باید صریح بگوییم.
     */
    public const QUERY_TYPE_PREFIX = 'query_type_';

    /** پیشوندی که ووکامرس به تاکسونومیِ ویژگی‌ها می‌دهد */
    public const ATTRIBUTE_PREFIX = 'pa_';

    /** پارامتر ترتیب — همان چیزی که ووکامرس می‌خواند */
    public const SORT_PARAM = 'orderby';

    /** پارامتر صفحه — همان چیزی که وردپرس می‌خواند */
    public const PAGE_PARAM = 'paged';

    /** @var array<string,string[]> تاکسونومی ⇒ اسلاگ ترم‌های انتخاب‌شده */
    private array $filters;

    private string $sort;

    private int $page;

    private function __construct(array $filters, string $sort, int $page) {
        $this->filters = $filters;
        $this->sort    = $sort;
        $this->page    = $page;
    }

    /* =====================================================================
     * ساخت
     * =================================================================== */

    /**
     * @param array<string,string[]|string> $filters تاکسونومی ⇒ ترم‌ها
     * @param string                        $sort    کلید ترتیب؛ خالی یعنی پیش‌فرض فروشگاه
     * @param int                           $page    صفحه، از ۱
     */
    public static function create(array $filters = [], string $sort = '', int $page = 1): self {
        return new self(self::normalize($filters), self::clean_sort($sort), max(1, $page));
    }

    /**
     * خواندن وضعیت از پارامترهای آدرس.
     *
     * فهرست سفید اجباری است و پیش‌فرضی ندارد. بدون آن، هر پارامتری که با
     * ‎filter_‎ شروع شود به یک ‎tax_query‎ تبدیل می‌شد و کافی بود کسی
     * ‎?filter_anything=x‎ صدا بزند تا کوئری‌های دلخواه بسازد.
     *
     * ‎query_type_*‎ عمداً خوانده نمی‌شود، با اینکه نوشته می‌شود. اپراتور هر
     * گروه یک تصمیم طرحِ فیلتر است و از ‎Filter_Schema‎ می‌آید؛ اگر آدرس هم
     * می‌توانست عوضش کند، دو منبع برای یک چیز داشتیم و بازدیدکننده می‌توانست
     * با دست‌کاری آدرس، معنای فیلتری را عوض کند که مدیر عمداً روی ‎AND‎
     * گذاشته. نوشتنش فقط برای این است که ابزارک‌های خودِ ووکامرس همان لینک
     * را درست بخوانند.
     *
     * @param array    $params        معمولاً ‎$_GET‎ — خام و غیرقابل‌اعتماد.
     * @param string[] $taxonomies    تاکسونومی‌های مجاز، مثل ‎pa_brand‎.
     * @param string[] $sorts         کلیدهای ترتیبِ تعریف‌شده در ویجت.
     */
    public static function from_request(array $params, array $taxonomies, array $sorts = []): self {
        $filters = [];

        foreach ($taxonomies as $taxonomy) {
            $taxonomy = self::key($taxonomy);

            if ('' === $taxonomy) {
                continue;
            }

            $param = self::param_for($taxonomy);

            if (!isset($params[$param])) {
                continue;
            }

            $filters[$taxonomy] = self::split($params[$param]);
        }

        $sort = isset($params[self::SORT_PARAM]) ? self::clean_sort((string) $params[self::SORT_PARAM]) : '';

        // ترتیبی که در ویجت تعریف نشده یعنی ترتیبِ پیش‌فرض، نه خطا: آدرسِ
        // قدیمیِ بوکمارک‌شده نباید صفحه را بشکند.
        if ($sorts && !in_array($sort, $sorts, true)) {
            $sort = '';
        }

        $page = isset($params[self::PAGE_PARAM]) ? (int) $params[self::PAGE_PARAM] : 1;

        return self::create($filters, $sort, $page);
    }

    /* =====================================================================
     * خواندن
     * =================================================================== */

    /** @return array<string,string[]> */
    public function filters(): array {
        return $this->filters;
    }

    public function sort(): string {
        return $this->sort;
    }

    public function page(): int {
        return $this->page;
    }

    /** ترم‌های انتخاب‌شدهٔ یک تاکسونومی */
    public function selected(string $taxonomy): array {
        return $this->filters[self::key($taxonomy)] ?? [];
    }

    public function has(string $taxonomy, string $term): bool {
        return in_array(self::slug($term), $this->selected($taxonomy), true);
    }

    public function is_filtered(): bool {
        return [] !== $this->filters;
    }

    /** تعداد کل ترم‌های انتخاب‌شده در همهٔ گروه‌ها */
    public function count(): int {
        $total = 0;

        foreach ($this->filters as $terms) {
            $total += count($terms);
        }

        return $total;
    }

    /* =====================================================================
     * تغییر — همیشه نمونهٔ تازه
     * =================================================================== */

    /**
     * وضعیت بدون قیدهای یک تاکسونومی.
     *
     * قلبِ شمارشِ «خودحذف‌کن»: برای شمردن گزینه‌های گروه برند، خودِ برند
     * نباید در قید باشد وگرنه بعد از انتخاب یک برند، بقیهٔ برندها صفر
     * می‌شوند و کاربر نمی‌تواند برند دومی اضافه کند.
     */
    public function without(string $taxonomy): self {
        $taxonomy = self::key($taxonomy);

        if (!isset($this->filters[$taxonomy])) {
            return $this;
        }

        $filters = $this->filters;
        unset($filters[$taxonomy]);

        return new self($filters, $this->sort, $this->page);
    }

    /** جایگزینی ترم‌های یک تاکسونومی؛ آرایهٔ خالی یعنی حذف کل گروه */
    public function with(string $taxonomy, array $terms): self {
        $filters = $this->filters;

        $filters[self::key($taxonomy)] = $terms;

        return new self(self::normalize($filters), $this->sort, $this->page);
    }

    /** روشن/خاموش کردن یک ترم — همان کاری که یک کلیک روی چک‌باکس می‌کند */
    public function toggle(string $taxonomy, string $term): self {
        $terms = $this->selected($taxonomy);
        $slug  = self::slug($term);

        $terms = in_array($slug, $terms, true)
            ? array_values(array_diff($terms, [$slug]))
            : array_merge($terms, [$slug]);

        // هر تغییر فیلتر، صفحه‌بندی را از اول شروع می‌کند: ماندن روی صفحهٔ ۷
        // بعد از باریک‌کردن نتیجه به ۲ صفحه، یعنی صفحهٔ خالی.
        return (new self($this->filters, $this->sort, 1))->with($taxonomy, $terms);
    }

    public function with_sort(string $sort): self {
        return new self($this->filters, self::clean_sort($sort), 1);
    }

    public function with_page(int $page): self {
        return new self($this->filters, $this->sort, max(1, $page));
    }

    /** پاک‌کردن همهٔ فیلترها، با حفظ ترتیب */
    public function cleared(): self {
        return new self([], $this->sort, 1);
    }

    /* =====================================================================
     * آدرس و کلید کش
     * =================================================================== */

    /**
     * پارامترهای آدرس، بدون مقادیر پیش‌فرض.
     *
     * پیش‌فرض‌ها حذف می‌شوند تا آدرسِ «هیچ فیلتری فعال نیست» دقیقاً همان
     * آدرس خام دسته بماند؛ وگرنه دو آدرس متفاوت با یک محتوا می‌ساختیم و
     * canonical باید مشکلی را حل می‌کرد که خودمان درست کرده‌ایم.
     *
     * ‎query_type_*‎ فقط وقتی نوشته می‌شود که واقعاً نتیجه را عوض کند: با یک
     * ترم انتخاب‌شده، ‎and‎ و ‎or‎ دقیقاً یک چیزند و نوشتنش فقط یک آدرسِ
     * دوم برای همان محتوا می‌ساخت.
     *
     * @param array<string,string> $operators تاکسونومی ⇒ ‎or‎ / ‎and‎
     * @return array<string,string>
     */
    public function to_query_vars(array $operators = []): array {
        $vars = [];

        foreach ($this->filters as $taxonomy => $terms) {
            $vars[self::param_for($taxonomy)] = implode(',', $terms);

            if (count($terms) < 2) {
                continue;
            }

            $operator = strtolower((string) ($operators[$taxonomy] ?? 'or'));

            // ‎and‎ همان پیش‌فرض ووکامرس است و نوشتنش چیزی اضافه نمی‌کند
            if ('and' !== $operator) {
                $vars[self::query_type_for($taxonomy)] = 'or';
            }
        }

        if ('' !== $this->sort) {
            $vars[self::SORT_PARAM] = $this->sort;
        }

        if ($this->page > 1) {
            $vars[self::PAGE_PARAM] = (string) $this->page;
        }

        return $vars;
    }

    /**
     * امضای متنیِ وضعیت — پایدار و مستقل از ترتیب کلیک کاربر.
     *
     * بدون مرتب‌سازی، «برند بعد محور» و «محور بعد برند» دو کلید کش متفاوت
     * می‌ساختند برای یک نتیجهٔ یکسان: کش دو برابر بزرگ‌تر و نصفِ اثرش.
     *
     * صفحه عمداً داخل امضا نیست. شمارش فست به صفحه ربطی ندارد و اگر داخل
     * می‌آمد، هر صفحه کش جدا می‌گرفت برای عددی که در همهٔ صفحات یکی است.
     */
    public function signature(): string {
        $parts = [];

        foreach ($this->filters as $taxonomy => $terms) {
            $parts[] = $taxonomy . '=' . implode('|', $terms);
        }

        return implode('&', $parts);
    }

    /** هش کوتاه امضا، برای جاهایی که طول کلید مهم است */
    public function fingerprint(): string {
        return substr(md5($this->signature()), 0, 12);
    }

    /* =====================================================================
     * نگاشت تاکسونومی ⇄ پارامتر
     * =================================================================== */

    /** ‎pa_axis_count‎ ⇒ ‎filter_axis_count‎ */
    public static function param_for(string $taxonomy): string {
        $taxonomy = self::key($taxonomy);

        if (0 === strpos($taxonomy, self::ATTRIBUTE_PREFIX)) {
            $taxonomy = substr($taxonomy, strlen(self::ATTRIBUTE_PREFIX));
        }

        return self::FILTER_PREFIX . $taxonomy;
    }

    /** ‎pa_brand‎ ⇒ ‎query_type_brand‎ */
    public static function query_type_for(string $taxonomy): string {
        return self::QUERY_TYPE_PREFIX . substr(self::param_for($taxonomy), strlen(self::FILTER_PREFIX));
    }

    /**
     * ‎filter_axis_count‎ ⇒ ‎pa_axis_count‎
     *
     * برمی‌گرداند رشتهٔ خالی اگر پارامتر اصلاً فیلتر نباشد، تا صدازننده
     * مجبور نشود خودش پیشوند را چک کند.
     */
    public static function taxonomy_for(string $param): string {
        $param = self::key($param);

        if (0 !== strpos($param, self::FILTER_PREFIX)) {
            return '';
        }

        $name = substr($param, strlen(self::FILTER_PREFIX));

        return '' === $name ? '' : self::ATTRIBUTE_PREFIX . $name;
    }

    /* =====================================================================
     * پاک‌سازی
     * =================================================================== */

    /**
     * مرتب‌سازی و یکتاسازیِ کل نقشهٔ فیلترها.
     *
     * سه کار در یک جا: اسلاگ‌های نامعتبر می‌افتند، تکراری‌ها یکی می‌شوند، و
     * ترتیب قطعی می‌شود. هر سه لازمِ یک کلید کشِ قابل‌اتکا هستند.
     *
     * @param array<string,string[]|string> $filters
     * @return array<string,string[]>
     */
    private static function normalize(array $filters): array {
        $clean = [];

        foreach ($filters as $taxonomy => $terms) {
            $taxonomy = self::key((string) $taxonomy);

            if ('' === $taxonomy) {
                continue;
            }

            $terms = is_array($terms) ? $terms : self::split($terms);
            $terms = array_filter(array_map([self::class, 'slug'], $terms), static fn(string $t): bool => '' !== $t);

            if (!$terms) {
                continue;
            }

            // دو کلید متفاوت می‌توانند بعد از پاک‌سازی یکی شوند؛ آن‌وقت دومی
            // نباید اولی را دور بریزد.
            $existing = $clean[$taxonomy] ?? [];
            $terms    = array_values(array_unique(array_merge($existing, $terms)));

            sort($terms, SORT_STRING);

            $clean[$taxonomy] = $terms;
        }

        ksort($clean, SORT_STRING);

        return $clean;
    }

    /** ‎'up3d,vhf'‎ ⇒ ‎['up3d','vhf']‎ */
    private static function split($value): array {
        if (is_array($value)) {
            return $value;
        }

        return explode(',', (string) $value);
    }

    /**
     * نام تاکسونومی.
     *
     * وقتی ووکامرس هست، از تابع خودش استفاده می‌شود. این وسواس نیست: کل
     * ارزش پذیرفتن قرارداد آدرس ووکامرس به این است که *دقیقاً* همان‌طور
     * خوانده شود. یک تفاوت کوچک در پاک‌سازی یعنی لینکی که ابزارک ووکامرس
     * ساخته، اینجا به فیلتر دیگری (یا هیچ) تبدیل می‌شود.
     */
    private static function key(string $value): string {
        $value = trim($value);

        if (function_exists('wc_sanitize_taxonomy_name')) {
            return (string) wc_sanitize_taxonomy_name($value);
        }

        return (string) preg_replace('/[^a-z0-9_\-]/', '', strtolower($value));
    }

    /**
     * اسلاگ ترم.
     *
     * باز هم از خودِ وردپرس: ووکامرس روی همین مقادیر ‎sanitize_title()‎
     * اجرا می‌کند و اسلاگ‌های غیرلاتین در دیتابیس به همان شکلِ کدشده ذخیره
     * شده‌اند. اگر خودمان چیز دیگری بسازیم، اسلاگ فارسی هیچ‌وقت با ردیف
     * دیتابیس نمی‌خورد و فیلتر بی‌سروصدا هیچ نتیجه‌ای نمی‌دهد.
     *
     * جایگزینِ بدون وردپرس فقط برای تست است و ادعای برابری ندارد.
     */
    private static function slug(string $value): string {
        $value = trim($value);

        if ('' === $value) {
            return '';
        }

        if (function_exists('sanitize_title')) {
            return (string) sanitize_title($value);
        }

        return (string) preg_replace('/[\x00-\x1F\x7F<>"\'`\\\\\/&?#,|=\s]+/u', '', rawurldecode($value));
    }

    /** کلید ترتیب: مثل نام تاکسونومی، ولی نقطه هم مجاز است */
    private static function clean_sort(string $value): string {
        return (string) preg_replace('/[^a-z0-9_\-.]/', '', strtolower(trim($value)));
    }
}
