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

    /* ---------------------------------------------------------------------
     * سقف‌ها
     *
     * اینجا و نه در نقطهٔ آژاکس، چون حمله به آژاکس محدود نیست: همان
     * ‎?filter_a=…&filter_b=…‎ را می‌شود در نوار آدرس هم زد و کوئری اصلی
     * ووکامرس هم اجرا می‌شود. سقفی که فقط جلوی ‎POST‎ را بگیرد، فقط
     * آسان‌ترین راه را بسته.
     *
     * هزینه‌اش هم خطی نیست: هر گروه فیلتر یک ‎JOIN‎ روی
     * ‎term_relationships‎ اضافه می‌کند. دوازده گروه یعنی دوازده ‎JOIN‎، و
     * بهینه‌ساز MySQL از یک جایی به بعد نقشهٔ اجرا را رها می‌کند.
     *
     * عددها سخاوتمندند تا هیچ فروشگاه واقعی‌ای به آن‌ها نخورد: فروشگاهی با
     * دوازده گروه فیلترِ هم‌زمانِ فعال وجود ندارد، و پنجاه ترمِ انتخاب‌شده
     * در یک گروه یعنی کاربر عملاً «همه» را زده.
     * ------------------------------------------------------------------ */

    /** بیشترین گروه فیلترِ هم‌زمان */
    public const MAX_GROUPS = 12;

    /** بیشترین ترم در هر گروه */
    public const MAX_TERMS = 50;

    /**
     * بیشترین شمارهٔ صفحه.
     *
     * ‎?paged=99999999‎ به وردپرس یک ‎OFFSET‎ نجومی می‌دهد. نتیجه‌اش خالی
     * است ولی دیتابیس برای رسیدن به آن خلأ، کل مجموعه را می‌چیند.
     */
    public const MAX_PAGE = 5000;

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
        return new self(self::normalize($filters), self::clean_sort($sort), min(self::MAX_PAGE, max(1, $page)));
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

            if (!isset($params[$param]) || !self::honorable($params[$param])) {
                continue;
            }

            $filters[$taxonomy] = self::split($params[$param]);
        }

        $sort = self::clean_sort(self::scalar($params[self::SORT_PARAM] ?? ''));

        // ترتیبی که در ویجت تعریف نشده یعنی ترتیبِ پیش‌فرض، نه خطا: آدرسِ
        // قدیمیِ بوکمارک‌شده نباید صفحه را بشکند.
        if ($sorts && !in_array($sort, $sorts, true)) {
            $sort = '';
        }

        $page = (int) self::scalar($params[self::PAGE_PARAM] ?? 1);

        return self::create($filters, $sort, $page);
    }

    /**
     * پارامترهای ‎filter_*‎ که در آدرس بودند و پذیرفته نشدند.
     *
     * ‎from_request()‎ این‌ها را بی‌صدا می‌اندازد، که برای کار کردن صفحه
     * درست است ولی برای *معنای* آدرس نه: ‎?filter_ghost=x‎ آدرسی است که
     * چیزی را ادعا می‌کند که وجود ندارد. بی‌صدا انداختنش یعنی آن آدرس ‎200‎
     * بگیرد و نسخهٔ بدون فیلتر را نشان بدهد — همان «ترکیب بی‌معنا»یی که
     * گوگل صریحاً در فهرست ‎404‎ آورده.
     *
     * عمداً بیرون از خودِ شیء است: این یک تشخیصِ *پارس* است، نه بخشی از
     * وضعیت. اگر داخل شیء می‌ماند، ‎toggle()‎ و ‎with()‎ هم آن را با خودشان
     * می‌بردند و یک تشخیصِ کهنه به وضعیتِ تازه می‌چسبید.
     *
     * @param array    $params     همان ‎$_GET‎.
     * @param string[] $taxonomies تاکسونومی‌های مجاز.
     * @return string[] نام پارامترهای ناشناخته
     */
    public static function unknown_filters(array $params, array $taxonomies): array {
        $allowed = [];

        foreach ($taxonomies as $taxonomy) {
            $allowed[self::param_for(self::key((string) $taxonomy))] = true;
        }

        $unknown = [];

        foreach (array_keys($params) as $key) {
            $key = (string) $key;

            if (0 !== strpos($key, self::FILTER_PREFIX)) {
                continue;
            }

            /*
             * تاکسونومیِ شناخته‌شده هم اگر مقدارش اعمال‌نشدنی باشد، ادعای
             * بی‌صاحب است: ‎?filter_brand[]=up3d‎ را نه ووکامرس اعمال
             * می‌کند و نه ما. «شناخته‌شده» یعنی *این درخواست* اثر می‌گذارد،
             * نه اینکه اسمِ پارامتر آشناست.
             */
            if (isset($allowed[$key]) && self::honorable($params[$key] ?? null)) {
                continue;
            }

            /*
             * پارامتر خالی («‎?filter_brand=‎») ادعایی نمی‌کند و معمولاً از
             * یک فرمِ ارسال‌شده می‌آید، نه از دست‌کاری. ‎404‎ دادن به آن،
             * رفتار عادیِ مرورگر را می‌شکند.
             *
             * ‎?filter_ghost[]=x‎ هم آدرس معتبری است و آرایه می‌دهد؛ همان‌جا
             * که ‎scalar()‎ لازم می‌شود، وگرنه اینجا هم اخطارِ تبدیل آرایه به
             * رشته می‌گرفتیم.
             */
            $value = $params[$key] ?? '';

            if (is_array($value)) {
                $value = implode('', array_map(static fn($item): string => is_scalar($item) ? (string) $item : '', $value));
            } else {
                $value = self::scalar($value);
            }

            if ('' !== trim($value)) {
                $unknown[] = $key;
            }
        }

        return $unknown;
    }

    /**
     * پارامترهایی که از سقف رد شده‌اند.
     *
     * بریدن به‌تنهایی کافی نیست: آدرسی با سی گروه فیلتر، بعد از بریدن
     * همان چیزی را نشان می‌دهد که نسخهٔ دوازده‌گروهی‌اش — یعنی یک آدرسِ
     * تکراریِ ‎200‎، از همان جنسی که قرار بود ‎404‎ بگیرد. و برخلاف اسلاگ
     * ناشناخته، اینجا نتیجه خالی هم نمی‌شود که ‎filtered_empty‎ بگیردش.
     *
     * پس بریدن از خرابی سرور جلو می‌گیرد و این یکی از تکثیر آدرس.
     *
     * @param array    $params     همان ‎$_GET‎.
     * @param string[] $taxonomies تاکسونومی‌های مجاز.
     * @return string[]
     */
    public static function oversized_filters(array $params, array $taxonomies): array {
        $oversized = [];
        $groups    = 0;

        foreach ($taxonomies as $taxonomy) {
            $param = self::param_for(self::key((string) $taxonomy));

            if (!isset($params[$param]) || !self::honorable($params[$param])) {
                continue;
            }

            $terms = array_filter(
                array_map([self::class, 'slug'], self::split(self::scalar($params[$param]))),
                static fn(string $t): bool => '' !== $t
            );

            if (!$terms) {
                continue;
            }

            ++$groups;

            if (count(array_unique($terms)) > self::MAX_TERMS) {
                $oversized[] = $param;
            }
        }

        if ($groups > self::MAX_GROUPS) {
            $oversized[] = self::FILTER_PREFIX . '*';
        }

        return array_values(array_unique($oversized));
    }

    /**
     * پارامترهای ‎filter_*‎ که یک چیز را دو بار می‌گویند.
     *
     * گوگل در همان سند ناوبری وجهی، «فیلتر تکراری» را کنار «ترکیب
     * بی‌معنا» و «صفحه‌بندی ناموجود» گذاشته و برای هر سه ‎404‎ خواسته، و
     * جای دیگری صریح‌تر: «مطمئن شوید ترتیب منطقی فیلترها همیشه یکسان
     * می‌ماند و هیچ فیلتر تکراری‌ای نمی‌تواند وجود داشته باشد.»
     *
     * دلیلش هم همان دلیل همیشگی است: ‎?filter_brand=up3d,up3d‎ دقیقاً همان
     * چیزی را نشان می‌دهد که ‎?filter_brand=up3d‎، پس یک آدرسِ دومِ رایگان
     * برای یک محتوا. و چون تکرار حد ندارد، تعدادشان هم بی‌نهایت است.
     *
     * دو شکل دارد و هر دو باید دیده شوند:
     *
     *   • تکرار داخل یک گروه — ‎?filter_brand=up3d,up3d‎ یا
     *     ‎?filter_brand[]=up3d&filter_brand[]=UP3D‎. این را از ‎$params‎
     *     می‌شود دید، چون ‎normalize()‎ بی‌صدا یکی‌شان می‌کند.
     *
     *   • تکرار خودِ کلید — ‎?filter_brand=a&filter_brand=b‎. این را از
     *     ‎$params‎ *نمی‌شود* دید: PHP فقط آخری را نگه می‌دارد و اولی بی‌صدا
     *     ناپدید می‌شود. تنها جایی که هنوز هست، رشتهٔ خام پرس‌وجوست.
     *
     * ‎$query_string‎ خالی یعنی فقط شکل اول سنجیده می‌شود — روی محیط‌هایی
     * که ‎QUERY_STRING‎ در دسترس نیست، بی‌سروصدا کمتر سخت‌گیر می‌شویم، که
     * از ‎404‎ دادن به صفحهٔ سالم بهتر است.
     *
     * @param array  $params       همان ‎$_GET‎.
     * @param string $query_string رشتهٔ خام پرس‌وجو، معمولاً ‎$_SERVER['QUERY_STRING']‎.
     * @return string[] نام پارامترهای تکراری
     */
    public static function duplicate_filters(array $params, string $query_string = ''): array {
        $duplicates = [];

        foreach ($params as $key => $value) {
            $key = (string) $key;

            if (0 !== strpos($key, self::FILTER_PREFIX)) {
                continue;
            }

            $terms = [];

            foreach (is_array($value) ? $value : self::split(self::scalar($value)) as $term) {
                $slug = is_scalar($term) ? self::slug((string) $term) : '';

                if ('' !== $slug) {
                    $terms[] = $slug;
                }
            }

            if (count($terms) !== count(array_unique($terms))) {
                $duplicates[] = $key;
            }
        }

        foreach (self::repeated_keys($query_string) as $key) {
            $duplicates[] = $key;
        }

        return array_values(array_unique($duplicates));
    }

    /**
     * کلیدهای ‎filter_*‎ که در رشتهٔ خام بیش از یک بار آمده‌اند.
     *
     * ‎filter_brand[]‎ تکرار حساب نمی‌شود: آن نحوِ آرایه‌ایِ خودِ PHP است و
     * مقدارهایش سالم به ‎$params‎ می‌رسند، پس اگر تکراری در کار باشد حلقهٔ
     * بالا می‌گیردش. چیزی که اینجا دنبالش هستیم، کلیدِ بدونِ کروشه است که
     * PHP بی‌سروصدا رویش می‌نویسد.
     *
     * @return string[]
     */
    private static function repeated_keys(string $query_string): array {
        if ('' === trim($query_string)) {
            return [];
        }

        $seen     = [];
        $repeated = [];

        foreach (explode('&', $query_string) as $pair) {
            if ('' === $pair) {
                continue;
            }

            $name = urldecode(explode('=', $pair, 2)[0]);

            if (0 !== strpos($name, self::FILTER_PREFIX) || false !== strpos($name, '[')) {
                continue;
            }

            if (isset($seen[$name])) {
                $repeated[] = $name;
            }

            $seen[$name] = true;
        }

        return $repeated;
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
     * فاصلهٔ آدرسِ فعلی تا آدرسی که اپراتورش صریح است.
     *
     * مسئله‌ای که این حل می‌کند از یک جملهٔ ووکامرس می‌آید:
     *
     *     $chosen[$taxonomy]['query_type'] = $query_type
     *         ? $query_type
     *         : apply_filters('woocommerce_layered_nav_default_query_type', 'and');
     *
     * یعنی ‎?filter_color=red,blue‎ بدون ‎query_type_color‎ برای ووکامرس
     * «هم قرمز و هم آبی» است. طرحِ ما همان گروه را ‎OR‎ می‌داند. روی آرشیو
     * واقعی هر دو هم‌زمان اجرا می‌شوند: ووکامرس کوئری اصلی را می‌سازد (که
     * ‎Archive_Head‎ شمارشش را می‌خواند) و ویجت کوئری خودش را. دو معنا، دو
     * شمارش — و روزی که به هم برسند، سرور ‎404‎ می‌فرستد و گرید محصول نشان
     * می‌دهد.
     *
     * راه‌حل این نیست که یکی تسلیم دیگری شود، این است که آدرس دیگر مبهم
     * نماند: اگر گروهی ‎OR‎ است و بیش از یک ترم دارد، ‎query_type_x=or‎ در
     * آدرس بنشیند. آن‌وقت هر دو طرف یک چیز می‌خوانند.
     *
     * پارامترِ بی‌اثر هم برداشته می‌شود — ‎query_type_x=and‎ روی گروهی که
     * یک ترم دارد هیچ چیزی را عوض نمی‌کند و فقط یک آدرسِ دومِ رایگان برای
     * همان محتوا می‌سازد؛ همان تکراری که قرار بود نساخته شود.
     *
     * @param array                $params    همان ‎$_GET‎.
     * @param array<string,string> $operators تاکسونومی ⇒ ‎or‎ / ‎and‎
     * @return array{set:array<string,string>,remove:string[]}
     */
    public function query_type_fixes(array $params, array $operators = []): array {
        $desired = [];

        foreach ($this->filters as $taxonomy => $terms) {
            if (count($terms) < 2) {
                continue;
            }

            // ‎and‎ همان پیش‌فرض ووکامرس است؛ نوشتنش فقط آدرس دوم می‌سازد
            if ('and' === strtolower((string) ($operators[$taxonomy] ?? 'or'))) {
                continue;
            }

            $desired[self::query_type_for($taxonomy)] = 'or';
        }

        $set    = [];
        $remove = [];

        foreach ($desired as $param => $value) {
            if (self::scalar($params[$param] ?? '') !== $value) {
                $set[$param] = $value;
            }
        }

        foreach (array_keys($params) as $key) {
            $key = (string) $key;

            if (0 === strpos($key, self::QUERY_TYPE_PREFIX) && !isset($desired[$key])) {
                $remove[] = $key;
            }
        }

        return ['set' => $set, 'remove' => $remove];
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

            // بریدن، نه رد کردن: وضعیت باید همیشه ساخته شود، حتی از یک
            // آدرس دست‌کاری‌شده. اینکه آن آدرس ۴۰۴ بگیرد، تصمیم جای دیگری
            // است — ‎oversized_filters()‎ گزارشش می‌کند.
            $terms = array_slice($terms, 0, self::MAX_TERMS);

            // دو کلید متفاوت می‌توانند بعد از پاک‌سازی یکی شوند؛ آن‌وقت دومی
            // نباید اولی را دور بریزد.
            $existing = $clean[$taxonomy] ?? [];
            $terms    = array_values(array_unique(array_merge($existing, $terms)));

            sort($terms, SORT_STRING);

            $clean[$taxonomy] = $terms;
        }

        ksort($clean, SORT_STRING);

        /*
         * بعد از ‎ksort‎ بریده می‌شود تا کدام گروه‌ها می‌مانند قطعی باشد.
         * اگر قبلش بود، ترتیب کلیک کاربر تعیین می‌کرد کدام گروه بیفتد —
         * یعنی دو آدرسِ یکسان، دو نتیجهٔ متفاوت و دو کلید کش.
         */
        return array_slice($clean, 0, self::MAX_GROUPS, true);
    }

    /**
     * مقدار تک‌مقداریِ یک پارامتر آدرس.
     *
     * ‎?orderby[]=x‎ و ‎?paged[]=2‎ آدرس‌های کاملاً معتبری هستند و هرکسی
     * می‌تواند بسازدشان. تبدیل مستقیمشان به رشته در PHP 8 اخطار
     * «Array to string conversion» می‌دهد و مقدارِ ‎'Array'‎ می‌سازد — که
     * بعد از پاک‌سازی می‌شود ‎'array'‎ و ممکن است حتی به یک کلید واقعی
     * بخورد.
     */
    private static function scalar($value): string {
        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * آیا این مقدار اصلاً اعمال می‌شود؟
     *
     * ووکامرس مقدارِ غیررشته‌ای را صریحاً می‌اندازد:
     *
     *     if ( 0 === strpos( $key, 'filter_' ) ) {
     *         if ( ! is_string( $value ) ) {
     *             continue;
     *         }
     *
     * یعنی ‎?filter_brand[]=up3d‎ روی کوئری اصلی هیچ اثری ندارد. اگر ما
     * اعمالش کنیم، گریدِ ویجت با شمارشی که ‎Archive_Head‎ از کوئری اصلی
     * خوانده نمی‌خواند — و آن اختلاف می‌تواند یعنی سرور ‎404‎ بفرستد و
     * گرید محصول نشان بدهد.
     *
     * پس همان‌جا که ووکامرس می‌اندازد، ما هم می‌اندازیم؛ و
     * ‎unknown_filters()‎ همان مقدار را «ادعای بی‌صاحب» گزارش می‌کند، که
     * دقیقاً هم هست.
     */
    private static function honorable($value): bool {
        return is_string($value);
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

        /*
         * ‎strtolower‎ چون خودِ وردپرس هم همین کار را می‌کند
         * (‎sanitize_title_with_dashes()‎). بدون آن، ‎UP3D‎ و ‎up3d‎ اینجا
         * دو ترم متفاوت شمرده می‌شوند در حالی که دیتابیس یکی‌شان می‌داند.
         */
        return (string) preg_replace('/[\x00-\x1F\x7F<>"\'`\\\\\/&?#,|=\s]+/u', '', strtolower(rawurldecode($value)));
    }

    /** کلید ترتیب: مثل نام تاکسونومی، ولی نقطه هم مجاز است */
    private static function clean_sort(string $value): string {
        return (string) preg_replace('/[^a-z0-9_\-.]/', '', strtolower(trim($value)));
    }
}
