<?php
namespace Zig3d_Widgets;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ترتیب محصولات.
 *
 * چرا این کلاس هست و چرا مدیر «‎orderby‎» را دستی تایپ نمی‌کند: مرتب‌سازی
 * ووکامرس فقط یک ‎orderby‎ نیست. مرتب‌سازی بر اساس قیمت در ووکامرس با
 * فیلترهای ‎posts_clauses‎ انجام می‌شود، «پرفروش‌ترین» به یک متای شمارش
 * فروش وصل است، و امتیاز از یک متای دیگر می‌آید. یک فیلد متنی که هرچه
 * دلش خواست بپذیرد، یعنی مدیر باید همهٔ این جزئیات را بداند — و روزی که
 * ووکامرس یکی‌شان را عوض کند، هیچ‌کس نمی‌فهمد چرا ترتیب صفحه بی‌سروصدا
 * غلط شده.
 *
 * پس فهرست انواع بسته است و ترجمه به کوئری همیشه از خودِ ووکامرس عبور
 * می‌کند. تنها استثنا «فیلد دلخواه» است که ذاتاً چیزی است که ووکامرس
 * نمی‌شناسد.
 *
 * این مرز، بیرون از این کلاس هم برقرار است: جاوااسکریپت فقط کلید ترتیب را
 * می‌فرستد و هیچ‌وقت خودش ‎meta_query‎ نمی‌سازد. معنای کوئری تماماً مال
 * PHP است.
 */
final class Sorting {

    /**
     * انواع ترتیبِ شناخته‌شده.
     *
     * ‎directional‎ یعنی «صعودی/نزولی» برای این نوع معنا دارد. برای قیمت
     * ندارد، چون ووکامرس جهت را داخل خودِ ‎orderby‎ کدگذاری کرده
     * (‎price‎ و ‎price-desc‎)؛ گذاشتن یک کلید جهتِ جدا کنارش، دو منبع
     * متناقض می‌ساخت.
     */
    public const TYPES = [
        'default' => [
            'label'       => 'پیش‌فرض فروشگاه',
            'orderby'     => 'menu_order',
            'order'       => 'ASC',
            'directional' => false,
            'search_only' => false,
        ],
        'popularity' => [
            'search_only' => false,
            'label'       => 'پرفروش‌ترین',
            'orderby'     => 'popularity',
            'order'       => 'DESC',
            'directional' => false,
        ],
        'rating' => [
            'search_only' => false,
            'label'       => 'بیشترین امتیاز',
            'orderby'     => 'rating',
            'order'       => 'DESC',
            'directional' => false,
        ],
        'date' => [
            'search_only' => false,
            'label'       => 'جدیدترین',
            'orderby'     => 'date',
            'order'       => 'DESC',
            'directional' => true,
        ],
        'price' => [
            'search_only' => false,
            'label'       => 'ارزان‌ترین',
            'orderby'     => 'price',
            'order'       => 'ASC',
            'directional' => false,
        ],
        'price-desc' => [
            'search_only' => false,
            'label'       => 'گران‌ترین',
            'orderby'     => 'price-desc',
            'order'       => 'DESC',
            'directional' => false,
        ],
        'title' => [
            'search_only' => false,
            'label'       => 'بر اساس نام',
            'orderby'     => 'title',
            'order'       => 'ASC',
            'directional' => true,
        ],
        'meta' => [
            'search_only' => false,
            'label'       => 'فیلد دلخواه',
            'orderby'     => '',
            'order'       => 'DESC',
            'directional' => true,
        ],

        /*
         * «مرتبط‌ترین» فقط با جست‌وجو معنا دارد و فقط با جست‌وجو *کار*
         * می‌کند: ‎ORDER BY relevance‎ در وردپرس از عبارت‌های جست‌وجو ساخته
         * می‌شود و بدون آن‌ها SQL نامعتبر می‌دهد. ووکامرس خودش هم برای همین
         * یک نگهبان دارد که جست‌وجوهای فقط‌منفی («‎-چیزی‎») را رد می‌کند.
         */
        'relevance' => [
            'search_only' => true,
            'label'       => 'مرتبط‌ترین',
            'orderby'     => 'relevance',
            'order'       => 'DESC',
            'directional' => false,
        ],
    ];

    /* =====================================================================
     * پاک‌سازی فهرست گزینه‌ها
     * =================================================================== */

    /**
     * ردیف‌های خامِ تکرارشونده را به گزینه‌های معتبر تبدیل می‌کند.
     *
     * کلیدِ هر گزینه همان چیزی است که در آدرس می‌نشیند، پس باید یکتا باشد.
     * دو ردیف با یک کلید یعنی یکی از آن دو هرگز انتخاب نمی‌شود — و چون
     * ظاهرش در پنل درست است، کشفش فقط با کلیک‌کردن ممکن می‌شود.
     *
     * @param array<int,array> $rows
     * @return array<int,array{key:string,label:string,type:string,order:string,meta_key:string,meta_type:string}>
     */
    public static function sanitize_options(array $rows): array {
        $options = [];
        $used    = [];

        foreach ($rows as $index => $row) {
            $type = (string) ($row['type'] ?? 'default');

            if (!isset(self::TYPES[$type])) {
                continue;
            }

            $meta_key = self::meta_key((string) ($row['meta_key'] ?? ''));

            // «فیلد دلخواه» بدون فیلد، یک دکمه است که هیچ کاری نمی‌کند
            if ('meta' === $type && '' === $meta_key) {
                continue;
            }

            $key = self::unique_key($row, $type, $meta_key, $index, $used);

            $used[$key] = true;

            $options[] = [
                'key'       => $key,
                'label'     => trim((string) ($row['label'] ?? '')) ?: self::TYPES[$type]['label'],
                'type'      => $type,
                'order'     => self::order($row, $type),
                'meta_key'  => $meta_key,
                'meta_type' => 'text' === ($row['meta_type'] ?? 'num') ? 'text' : 'num',
            ];
        }

        return $options;
    }

    /** کلیدهای موجود در فهرست — همان فهرست سفیدِ اعتبارسنجی آدرس */
    public static function keys(array $options): array {
        return array_column($options, 'key');
    }

    /* =====================================================================
     * انتخاب
     * =================================================================== */

    /**
     * گزینهٔ فعال.
     *
     * کلید ناشناخته به گزینهٔ اول برمی‌گردد نه به خطا: آدرسی که کاربر
     * بوکمارک کرده یا موتور جستجو ایندکس کرده نباید بعد از تغییر تنظیمات
     * ویجت، صفحهٔ خراب بدهد.
     *
     * @param array<int,array> $options
     */
    public static function resolve(array $options, string $key, bool $has_search = true): ?array {
        $options = self::available($options, $has_search);

        foreach ($options as $option) {
            if ($option['key'] === $key) {
                return $option;
            }
        }

        return $options[0] ?? null;
    }

    /* =====================================================================
     * ترجمه به کوئری
     * =================================================================== */

    /**
     * آرگومان‌های ‎WP_Query‎ برای یک گزینه.
     *
     * برای انواع استاندارد، ترجمه به خودِ ووکامرس سپرده می‌شود. این فقط
     * «تمیزتر» نیست: ‎get_catalog_ordering_args()‎ در همان فراخوانی،
     * فیلترهای ‎posts_clauses‎ لازم برای مرتب‌سازی قیمت را هم ثبت می‌کند.
     * بازنویسی دستیِ خروجی‌اش، آن اثر جانبی را از دست می‌داد — و بدتر،
     * مرتب‌سازی قیمتِ ما با ‎_price‎ برای محصول متغیر غلط می‌شد، چون
     * ووکامرس برای صعودی ‎min_price‎ و برای نزولی ‎max_price‎ می‌گذارد.
     *
     * ⚠ این تابع اثر جانبیِ سراسری دارد و باید حتماً با ‎release()‎ جفت شود.
     *
     * نگاه کنید به خودِ ووکامرس:
     *
     *     public function order_by_price_asc_post_clauses( $args ) {
     *         $args['join']    = $this->append_product_sorting_table_join(…);
     *         $args['orderby'] = ' wc_product_meta_lookup.min_price ASC, … ';
     *
     * پارامتر دومی به نام ‎$query‎ در کار نیست — یعنی این فیلتر اصلاً
     * *نمی‌تواند* بفهمد روی کدام کوئری نشسته و به هر ‎WP_Query‎ بعدی در
     * همان درخواست می‌چسبد. خودِ ووکامرس آن را روی ‎the_posts‎ برمی‌دارد
     * (‎remove_product_query_filters()‎)، ولی آن هوک فقط برای کوئری اصلی
     * بسته شده. کوئری‌های ما آنجا نیستند.
     *
     * برای ما این یعنی: یک JOIN ناخواسته روی ‎wc_product_meta_lookup‎ و یک
     * ‎ORDER BY‎ داخل زیرکوئریِ شمارش فست. پس ‎Archive_Query::run()‎ تنها
     * راه درستِ اجراست و خودش جفت‌شدن را تضمین می‌کند.
     */
    public static function query_args(array $option): array {
        if ('meta' === ($option['type'] ?? '')) {
            return [
                'orderby'  => 'num' === ($option['meta_type'] ?? 'num') ? 'meta_value_num' : 'meta_value',
                'order'    => $option['order'] ?? 'DESC',
                'meta_key' => $option['meta_key'] ?? '',
            ];
        }

        $orderby = self::TYPES[$option['type']]['orderby'] ?? 'menu_order';
        $order   = $option['order'] ?? 'ASC';

        if (function_exists('WC') && isset(WC()->query) && method_exists(WC()->query, 'get_catalog_ordering_args')) {
            return WC()->query->get_catalog_ordering_args($orderby, $order);
        }

        return self::fallback_args($orderby, $order);
    }

    /**
     * برداشتن فیلترهای مرتب‌سازیِ ووکامرس.
     *
     * چیزی خراب نمی‌شود اگر وقتی چیزی ثبت نشده هم صدا زده شود؛
     * ‎remove_filter()‎ روی فیلتر نبوده بی‌اثر است. پس می‌شود دفاعی هم
     * صدایش زد — و باید، چون افزونهٔ دیگری هم ممکن است
     * ‎get_catalog_ordering_args()‎ را بدون پاک‌سازی صدا زده باشد.
     */
    public static function release(): void {
        if (function_exists('WC') && isset(WC()->query) && method_exists(WC()->query, 'remove_ordering_args')) {
            WC()->query->remove_ordering_args();
        }
    }

    /**
     * آیا این نوع، فیلتر سراسری ثبت می‌کند؟
     *
     * فقط برای خوانایی و تست است؛ ‎release()‎ به‌هرحال بی‌خطر است.
     */
    public static function has_side_effects(array $option): bool {
        return in_array(self::TYPES[$option['type'] ?? '']['orderby'] ?? '', ['price', 'price-desc', 'popularity', 'rating'], true);
    }

    /**
     * گزینه‌هایی که در این زمینه معنا دارند.
     *
     * «مرتبط‌ترین» بدون جست‌وجو نه‌تنها بی‌معناست، ‎ORDER BY‎ نامعتبر
     * می‌سازد. پس به‌جای اینکه در فهرست بماند و موقع کلیک صفحه را بشکند،
     * اصلاً رندر نمی‌شود.
     */
    public static function available(array $options, bool $has_search): array {
        if ($has_search) {
            return $options;
        }

        return array_values(array_filter(
            $options,
            static fn(array $option): bool => empty(self::TYPES[$option['type']]['search_only'])
        ));
    }

    /**
     * ترجمهٔ جایگزین، برای وقتی ووکامرس در دسترس نیست.
     *
     * عمداً ناقص است و ادعای برابری با ووکامرس ندارد؛ فقط جلوی مرتب‌سازیِ
     * تصادفی را می‌گیرد. جای واقعیِ استفاده‌اش، تست‌هاست.
     */
    public static function fallback_args(string $orderby, string $order = 'ASC'): array {
        switch ($orderby) {
            case 'popularity':
                return ['orderby' => 'meta_value_num', 'order' => 'DESC', 'meta_key' => 'total_sales'];

            case 'rating':
                return ['orderby' => 'meta_value_num', 'order' => 'DESC', 'meta_key' => '_wc_average_rating'];

            case 'price':
                return ['orderby' => 'meta_value_num', 'order' => 'ASC', 'meta_key' => '_price'];

            case 'price-desc':
                return ['orderby' => 'meta_value_num', 'order' => 'DESC', 'meta_key' => '_price'];

            case 'date':
                return ['orderby' => 'date ID', 'order' => 'DESC' === strtoupper($order) ? 'DESC' : 'ASC'];

            case 'title':
                return ['orderby' => 'title', 'order' => 'DESC' === strtoupper($order) ? 'DESC' : 'ASC'];

            case 'rand':
                return ['orderby' => 'rand'];

            default:
                return ['orderby' => 'menu_order title', 'order' => 'ASC'];
        }
    }

    /* =====================================================================
     * کمکی‌ها
     * =================================================================== */

    private static function order(array $row, string $type): string {
        if (!self::TYPES[$type]['directional']) {
            return self::TYPES[$type]['order'];
        }

        return 'ASC' === strtoupper((string) ($row['order'] ?? '')) ? 'ASC' : 'DESC';
    }

    /**
     * کلیدِ آدرس.
     *
     * ترجیح با کلیدِ خودِ مدیر است، بعد نوع، و در آخر پسوند عددی — چون
     * می‌شود دو گزینهٔ «فیلد دلخواه» با دو متای متفاوت داشت که هر دو نوعشان
     * ‎meta‎ است.
     */
    private static function unique_key(array $row, string $type, string $meta_key, int $index, array $used): string {
        $key = self::slug((string) ($row['key'] ?? ''));

        if ('' === $key) {
            $key = 'meta' === $type ? self::slug($meta_key) : $type;
        }

        if ('' === $key) {
            $key = 'sort';
        }

        if (!isset($used[$key])) {
            return $key;
        }

        return $key . '-' . ($index + 1);
    }

    private static function slug(string $value): string {
        return (string) preg_replace('/[^a-z0-9_\-.]/', '', strtolower(trim($value)));
    }

    /** کلید متا: همان مجموعه‌ای که وردپرس برای نام متا می‌پذیرد */
    private static function meta_key(string $value): string {
        return (string) preg_replace('/[^A-Za-z0-9_\-]/', '', trim($value));
    }
}
