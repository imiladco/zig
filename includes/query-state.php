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
     * @return array<string,string>
     */
    public function to_query_vars(): array {
        $vars = [];

        foreach ($this->filters as $taxonomy => $terms) {
            $vars[self::param_for($taxonomy)] = implode(',', $terms);
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

    /** نام تاکسونومی: فقط حروف کوچک، عدد، خط تیره و زیرخط */
    private static function key(string $value): string {
        return (string) preg_replace('/[^a-z0-9_\-]/', '', strtolower(trim($value)));
    }

    /**
     * اسلاگ ترم.
     *
     * برخلاف نام تاکسونومی، اینجا حروف غیرلاتین باید بمانند: اسلاگ فارسی
     * («۵-محور») در وردپرس کاملاً معتبر است و پاک‌کردنش یعنی آن فیلتر هرگز
     * چیزی پیدا نمی‌کند. پس فقط کاراکترهایی حذف می‌شوند که در آدرس یا
     * کوئری معنای ساختاری دارند.
     */
    private static function slug(string $value): string {
        $value = trim(rawurldecode($value));
        $value = (string) preg_replace('/[\x00-\x1F\x7F<>"\'`\\\\\/&?#,|=\s]+/u', '', $value);

        return $value;
    }

    /** کلید ترتیب: مثل نام تاکسونومی، ولی نقطه هم مجاز است */
    private static function clean_sort(string $value): string {
        return (string) preg_replace('/[^a-z0-9_\-.]/', '', strtolower(trim($value)));
    }
}
