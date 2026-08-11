<?php
namespace Zig3d_Widgets;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * لایهٔ دیتابیسِ فیلترها: کدام ویژگی‌ها در این دسته هست، و هر گزینه چند
 * محصول دارد.
 *
 * تمام تصمیم‌ها در ‎Facets‎ و ‎Filter_Schema‎ گرفته می‌شوند؛ اینجا فقط عدد
 * می‌آید. این مرز عمدی است — آن دو کلاس بدون دیتابیس تست می‌شوند و این
 * یکی چیزی برای تصمیم‌گرفتن ندارد که تست‌نشده بماند.
 *
 * دو نکتهٔ فنی که کل کارایی این بخش به آن‌ها بند است:
 *
 * ۱. کوئری پایه هیچ‌وقت اجرا نمی‌شود، فقط ساخته می‌شود.
 *    وردپرس ‎$query->request‎ را قبل از فیلتر ‎posts_pre_query‎ می‌سازد، پس
 *    می‌شود SQL را برداشت و خودِ کوئری را کوتاه کرد. جایگزینش این بود که
 *    همهٔ شناسه‌ها را در PHP بخوانیم و در ‎IN (...)‎ بچینیم — که روی
 *    کاتالوگ بزرگ یعنی مگابایت‌ها آرایه و یک رشتهٔ SQL غول‌پیکر.
 *
 * ۲. واحدِ کوئری «گروه» است نه «گزینه».
 *    برای گروهی با پنجاه برند، یک ‎GROUP BY‎ اجرا می‌شود نه پنجاه کوئری.
 *    این تفاوت روی کاغذ کوچک است و در عمل تفاوت بین صفحه‌ای است که باز
 *    می‌شود و صفحه‌ای که تایم‌اوت می‌دهد.
 *
 * هر دو از الگوی ‎Internal\ProductFilters\FilterData‎ ووکامرس گرفته شده‌اند
 * — که مسیر *شمارشِ* امروزی ووکامرس است. مسیر *فیلترکردنِ* ووکامرس چیز
 * دیگری است و در ‎Facets::count_sql()‎ توضیح داده شده که چرا از آن پیروی
 * نمی‌کنیم.
 *
 * یک تفاوت عمدی با ووکامرس داریم: آن‌ها شناسه‌ها را می‌خوانند و به رشتهٔ
 * کاماجدا تبدیل می‌کنند، ما همان SQL را مستقیم زیرکوئری می‌گذاریم. این
 * یک رفت‌وبرگشت و materialize کردن یک آرایهٔ بزرگ در PHP را حذف می‌کند.
 *
 * ولی «حذف یک رفت‌وبرگشت» با «سریع‌تر» یکی نیست: بهینه‌ساز دیتابیس ممکن
 * است زیرکوئری را materialize کند یا پلن دیگری بگیرد. این طراحی مصرف
 * حافظهٔ PHP را قطعاً کم می‌کند؛ سرعت واقعی باید روی دیتابیس هدف با
 * ‎EXPLAIN ANALYZE‎ سنجیده شود.
 */
final class Attributes {

    /** شمارش‌ها فرارند: با هر سفارش و هر ویرایش موجودی عوض می‌شوند */
    private const COUNT_TTL = 300;

    /** فهرست ویژگی‌های یک دسته با تغییر محصول عوض می‌شود، نه با هر بازدید */
    private const DISCOVERY_TTL = 43200;

    /**
     * کلید نسخهٔ کش.
     *
     * پاک‌کردن کش با شمردن کلیدها ممکن نیست — نمی‌دانیم چند ترکیب فیلتر
     * ذخیره شده. پس به‌جای پاک‌کردن، نسخه را جلو می‌بریم: کلیدهای قدیمی
     * دیگر خوانده نمی‌شوند و خودشان منقضی می‌شوند.
     */
    private const VERSION_OPTION = 'zig3d_facet_cache_version';

    /* =====================================================================
     * کشف
     * =================================================================== */

    /**
     * تاکسونومی‌های ویژگی که واقعاً روی محصولات این کوئری هستند.
     *
     * همان رفتاری که «فیلترهای مرتبط با همین دسته» را ممکن می‌کند: اجتماع
     * ویژگی‌های همهٔ محصولات دسته، نه فهرست ثابت کل فروشگاه.
     *
     * ترتیب از خودِ ووکامرس می‌آید تا با ترتیبی که مدیر در صفحهٔ ویژگی‌ها
     * چیده یکی باشد؛ ترتیب الفبایی یا ترتیب دیتابیس، هر بار جای گروه‌ها را
     * عوض می‌کرد.
     *
     * @param array  $base_args آرگومان‌های ‎WP_Query‎ آرشیو.
     * @param string $context   شناسهٔ یکتای زمینه، برای کش.
     * @return string[]
     */
    public static function discover(array $base_args, string $context): array {
        $key    = 'zig3d_fd_' . substr(md5(self::version() . '|' . $context), 0, 16);
        $cached = get_transient($key);

        if (is_array($cached)) {
            return $cached;
        }

        $known = self::known_taxonomies();

        if (!$known) {
            return [];
        }

        global $wpdb;

        $base = self::base_sql($base_args);

        if ('' === $base) {
            return [];
        }

        /*
         * محدودکردن به فهرست شناخته‌شده در خودِ SQL، نه بعد از خواندن: بدون
         * آن، هر دسته و برچسب و ترمِ دیده‌شدنِ محصول هم برمی‌گشت و روی
         * کاتالوگ بزرگ، بیشترِ ردیف‌های خوانده‌شده دور ریخته می‌شدند.
         */
        $escaped = array_map(
            static fn(string $taxonomy): string => "'" . esc_sql(self::taxonomy_name($taxonomy)) . "'",
            $known
        );

        $sql = 'SELECT DISTINCT tt.taxonomy FROM ' . $wpdb->term_relationships . ' AS tr'
            . ' INNER JOIN ' . $wpdb->term_taxonomy . ' AS tt ON tt.term_taxonomy_id = tr.term_taxonomy_id'
            . ' WHERE tt.taxonomy IN (' . implode(',', $escaped) . ')'
            . ' AND tr.object_id IN (' . $base . ')';

        $found = $wpdb->get_col($sql);
        $found = is_array($found) ? array_map('strval', $found) : [];

        // ترتیب از فهرست شناخته‌شده می‌آید، نه از دیتابیس
        $ordered = array_values(array_intersect($known, $found));

        set_transient($key, $ordered, self::DISCOVERY_TTL);

        return $ordered;
    }

    /* =====================================================================
     * شمارش
     * =================================================================== */

    /**
     * شمارش گزینه‌های یک گروه، با قاعدهٔ خودحذف‌کنی.
     *
     * ‎null‎ یعنی «نمی‌دانم» و باید همان‌طور هم بالا برود: رابط در این حالت
     * هیچ عددی نشان نمی‌دهد و هیچ گزینه‌ای را غیرفعال نمی‌کند. عددِ حدسی از
     * نبودِ عدد بدتر است، چون کنار هر گزینه یک وعده می‌نشیند.
     *
     * @return array<string,int>|null اسلاگ ترم ⇒ تعداد
     */
    public static function counts(
        array $base_args,
        Query_State $state,
        string $facet,
        array $operators,
        string $context,
        string $semantics = Facets::SEMANTICS_PRODUCT
    ): ?array {
        /*
         * ثابت: فستهای این ویجت مشخصهٔ فنیِ محصول‌اند و نباید به‌عنوان
         * فیلترِ موجودیِ گزینه استفاده شوند.
         *
         * مسیر «موجودیِ گزینه» ساخته نشده. برگرداندن عددِ سطح محصول برای
         * آن، فیلتری می‌ساخت که ادعای «موجود» دارد و ناموجود هم برمی‌گرداند
         * — و چون عدد معقولی نشان می‌دهد، هیچ‌وقت کسی شک نمی‌کرد.
         */
        if (Facets::SEMANTICS_PRODUCT !== $semantics) {
            return null;
        }

        $key    = Facets::cache_key(self::version() . '|' . $context, $facet, $state);
        $cached = get_transient($key);

        if (is_array($cached)) {
            return $cached;
        }

        /*
         * بند ۲ الگوریتم: قید خودِ این گروه کنار می‌رود، بقیه می‌مانند — و
         * کوئری پایهٔ آرشیو با همهٔ قیدهای ثابتش دست‌نخورده می‌ماند. جاافتادن
         * همین دومی، پیاده‌سازی‌ای می‌سازد که ظاهراً درست است ولی عددهایش با
         * فهرست نمی‌خواند.
         */
        $constrained = Facets::constraints_for($state, $facet);
        $args        = self::with_filters($base_args, $constrained, $operators);
        $base        = self::base_sql($args);

        if ('' === $base) {
            return null;
        }

        global $wpdb;

        /*
         * ‎prepare()‎ اینجا قابل استفاده نیست و این محدودیت خودِ وردپرس است،
         * نه انتخاب ما: کوئری پایه یک زیرکوئریِ آمادهٔ ‎WP_Query‎ است و
         * می‌تواند قانوناً ‎%‎ داشته باشد (هر جست‌وجویی یک ‎LIKE '%…%'‎
         * می‌سازد). ‎prepare()‎ آن را جای‌نگهدار می‌خواند و شکست می‌خورد.
         *
         * ووکامرس دقیقاً به همین بن‌بست رسیده و همین راه را رفته:
         * ‎esc_sql(wc_sanitize_taxonomy_name(...))‎ و درج مستقیم، با این
         * توضیح در سورس — «We can't use $wpdb->prepare() here because using
         * %s with $wpdb->prepare() for a subquery won't work».
         */
        $name = self::taxonomy_name($facet);

        /*
         * نام خالی یعنی ‎tt.taxonomy = ''‎ که هیچ ردیفی نمی‌آورد و شمارش را
         * سراسر صفر می‌کند — و صفرِ سراسری یعنی همهٔ گزینه‌ها غیرفعال
         * می‌شوند. «نمی‌دانم» درست‌تر از «هیچ‌کدام» است.
         */
        if ('' === $name) {
            return null;
        }

        $sql = Facets::count_sql(
            $wpdb->term_relationships,
            $wpdb->term_taxonomy,
            esc_sql($name),
            $base
        );

        $rows = $wpdb->get_results($sql);

        if (!is_array($rows)) {
            return null;
        }

        // نگاشت شناسه به اسلاگ یک بار ساخته می‌شود، نه یک get_term به ازای
        // هر ردیف: گروهی با پنجاه برند وگرنه پنجاه خواندن اضافه داشت.
        $slugs = [];

        foreach (self::terms($facet) as $term) {
            $slugs[$term['term_id']] = $term['slug'];
        }

        $counts = [];

        foreach ($rows as $row) {
            $slug = $slugs[(int) $row->term_id] ?? '';

            if ('' !== $slug) {
                $counts[$slug] = (int) $row->product_count;
            }
        }

        set_transient($key, $counts, self::COUNT_TTL);

        return $counts;
    }

    /* =====================================================================
     * ترم‌ها
     * =================================================================== */

    /**
     * گزینه‌های یک گروه، به همان ترتیبی که در پنل ووکامرس چیده شده.
     *
     * ‎hide_empty‎ عمداً خاموش است: تصمیم دربارهٔ گزینهٔ صفر جای دیگری گرفته
     * می‌شود (‎Facets::options()‎) و آنجا فرقِ «صفر در این فیلتر» با «اصلاً
     * وجود ندارد» را می‌داند. اگر اینجا حذف می‌شدند، آن تصمیم هیچ‌وقت به
     * گزینه نمی‌رسید و ارتفاع سایدبار با هر درخواست می‌پرید.
     *
     * @return array<int,array{slug:string,label:string,term_id:int}>
     */
    public static function terms(string $taxonomy): array {
        $terms = get_terms([
            'taxonomy'   => $taxonomy,
            'hide_empty' => false,
            'orderby'    => 'name',
            'order'      => 'ASC',
        ]);

        if (!is_array($terms)) {
            return [];
        }

        $out = [];

        foreach (self::order($terms, $taxonomy) as $term) {
            if (!$term instanceof \WP_Term) {
                continue;
            }

            $out[] = [
                'slug'    => $term->slug,
                'label'   => $term->name,
                'term_id' => (int) $term->term_id,
            ];
        }

        return $out;
    }

    /**
     * مرتب‌سازی در PHP، نه در SQL.
     *
     * این را یک فروشگاه واقعی یاد داد. ووکامرس برای هر ویژگی یک «ترتیب»
     * دارد و پیش‌فرضِ ویژگیِ تازه ‎menu_order‎ است. ترجمهٔ مستقیمش به
     * ‎get_terms()‎ می‌شود:
     *
     *     'orderby' => 'meta_value_num', 'meta_key' => 'order_pa_brand'
     *
     * و آن یک ‎INNER JOIN‎ روی ‎termmeta‎ می‌سازد. ترمی که هیچ‌وقت در صفحهٔ
     * «ترتیب ترم‌ها» جابه‌جا نشده، اصلاً ردیف متایی ندارد — پس می‌افتد.
     * وقتی هیچ‌کدام جابه‌جا نشده باشند، *همه* می‌افتند و کل گروه فیلتر از
     * سایدبار غیب می‌شود. بدون خطا، بدون لاگ.
     *
     * خودِ ووکامرس هم همین کار را می‌کند (‎_wc_get_product_terms_menu_order()‎):
     * ترم‌ها را ساده می‌گیرد و بعد در PHP با پیش‌فرض صفر مرتب می‌کند.
     *
     * ‎name_num‎ هم همین‌طور: ‎get_terms()‎ اصلاً نمی‌شناسدش و بی‌صدا به
     * ‎name‎ برمی‌گردد — یعنی «۱۰ محور» قبل از «۹ محور» می‌نشیند.
     *
     * @param \WP_Term[] $terms
     * @return \WP_Term[]
     */
    private static function order(array $terms, string $taxonomy): array {
        $order = function_exists('wc_attribute_orderby') ? wc_attribute_orderby($taxonomy) : 'name';

        if ('menu_order' === $order) {
            $key = 'order_' . $taxonomy;

            usort($terms, static function ($a, $b) use ($key): int {
                $first  = (int) get_term_meta($a->term_id, $key, true);
                $second = (int) get_term_meta($b->term_id, $key, true);

                // ترتیب برابر یعنی الفبایی، وگرنه ترتیب به شانسِ خواندن بند است
                return $first === $second ? strcmp($a->name, $b->name) : $first <=> $second;
            });

            return $terms;
        }

        if ('name_num' === $order) {
            usort($terms, static fn($a, $b): int => ((float) $a->name) <=> ((float) $b->name));

            return $terms;
        }

        if ('id' === $order) {
            usort($terms, static fn($a, $b): int => ((int) $a->term_id) <=> ((int) $b->term_id));
        }

        return $terms;
    }

    /** برچسب گروه، همان که مدیر در ووکامرس گذاشته */
    public static function label(string $taxonomy): string {
        /*
         * ‎wc_attribute_label()‎ فقط ویژگی‌ها را می‌شناسد و برای هر چیز
         * دیگری همان نام خام را برمی‌گرداند — یعنی گروه برند در سایدبار
         * «product_brand» نوشته می‌شد. پس تاکسونومی‌های غیرویژگی اول از
         * برچسبِ خودشان پرسیده می‌شوند.
         */
        if (0 !== strpos($taxonomy, Query_State::ATTRIBUTE_PREFIX) && function_exists('get_taxonomy')) {
            $object = get_taxonomy($taxonomy);

            if ($object) {
                return (string) ($object->labels->singular_name ?: $object->label);
            }
        }

        if (function_exists('wc_attribute_label')) {
            return (string) wc_attribute_label($taxonomy);
        }

        $object = get_taxonomy($taxonomy);

        return $object ? (string) $object->labels->singular_name : $taxonomy;
    }

    /* =====================================================================
     * کش
     * =================================================================== */

    /**
     * جلو بردن نسخه — به‌جای پاک‌کردن کلیدها.
     *
     * روی هوک‌های تغییر محصول و ترم بسته می‌شود. پاک‌کردن واقعی ممکن نیست
     * چون نمی‌دانیم چند ترکیب فیلتر ذخیره شده؛ ولی کلیدی که دیگر خوانده
     * نمی‌شود، خودش منقضی می‌شود.
     */
    public static function flush(): void {
        update_option(self::VERSION_OPTION, self::version() + 1, false);
    }

    private static function version(): int {
        return (int) get_option(self::VERSION_OPTION, 1);
    }

    /* =====================================================================
     * ساخت کوئری
     * =================================================================== */

    /**
     * SQL کوئری پایه، بدون اجرا کردنش.
     *
     * ‎posts_pre_query‎ بعد از ساخته‌شدن ‎$query->request‎ صدا زده می‌شود، پس
     * برگرداندن یک آرایهٔ خالی از آن، کوئری را کوتاه می‌کند ولی SQL ساخته
     * شده سر جایش می‌ماند.
     *
     * ترتیب هم عمداً به شناسه برگردانده می‌شود: مرتب‌سازی برای شمارش بی‌اثر
     * است ولی می‌تواند join سنگین روی جدول متا بیاورد.
     */
    private static function base_sql(array $args): string {
        if (!class_exists('WP_Query')) {
            return '';
        }

        $args = array_merge($args, [
            'fields'                 => 'ids',
            'posts_per_page'         => -1,
            'no_found_rows'          => true,
            'ignore_sticky_posts'    => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
            'orderby'                => 'ID',
            'order'                  => 'ASC',
        ]);

        unset($args['paged'], $args['offset'], $args['meta_key']);

        $short_circuit = static fn() => [];

        add_filter('posts_pre_query', $short_circuit, 100);

        /*
         * مرتب‌سازی برای بازهٔ این کوئری کنار گذاشته می‌شود، نه پاک.
         *
         * ‎orderby => 'ID'‎ بالا کافی نیست: فیلتر ‎posts_clauses‎ روی *خروجی*
         * SQL می‌نشیند، نه روی آرگومان‌ها. اگر فعال بماند، این زیرکوئری یک
         * ‎JOIN‎ روی ‎wc_product_meta_lookup‎ می‌گیرد — و آن ‎INNER JOIN‎
         * است، پس محصولاتی که ردیفی در آن جدول ندارند از شمارش می‌افتند.
         *
         * ‎Archive_Query::run()‎ دیگر چیزی سراسری جا نمی‌گذارد، پس تنها
         * منبع باقی‌مانده افزونه‌های دیگرند. مالکش ما نیستیم، پس عکس
         * می‌گیریم و در ‎finally‎ دقیقاً همان را برمی‌گردانیم؛ اگر چیزی
         * بسته نباشد، اصلاً چیزی لمس نمی‌شود.
         *
         * چرا ‎suppress_filters => true‎ نه — وسوسه‌کننده است و در نگاه اول
         * تمیزتر، ولی در ‎WP_Query::get_posts()‎ همان شرط که
         * ‎posts_clauses‎ را می‌پوشاند، ‎posts_where‎ را هم می‌پوشاند. یعنی
         * قیدی که یک افزونهٔ عضویت یا دسترسی روی ‎posts_where‎ گذاشته، از
         * شمارش می‌افتاد ولی در فهرست می‌ماند — و شمارش از فهرست بزرگ‌تر
         * می‌شد. دقیقاً همان چیزی که کل این معماری برای نداشتنش ساخته شد.
         * (‎posts_pre_query‎ برعکس، بی‌قید و شرط اجرا می‌شود، پس کوتاه‌کردن
         * کوئری سر جایش می‌ماند.)
         */
        $snapshot = Sorting::suspend();

        try {
            $query = new \WP_Query();
            $query->query($args);
        } finally {
            Sorting::restore($snapshot);
            remove_filter('posts_pre_query', $short_circuit, 100);
        }

        $sql = (string) $query->request;

        /*
         * اگر به هر دلیل SQL ساخته نشد، رشتهٔ خالی برمی‌گردد و بالادست
         * ‎null‎ می‌شود. تزریق یک زیرکوئریِ ناقص، به‌جای «شمارش ندارم»، خطای
         * SQL می‌داد.
         */
        return false === strpos($sql, 'SELECT') ? '' : $sql;
    }

    /** افزودن فیلترهای فعال به آرگومان‌های پایه */
    private static function with_filters(array $args, Query_State $state, array $operators): array {
        $tax = Facets::tax_query($state, $operators);

        if (!$tax) {
            return $args;
        }

        $existing = $args['tax_query'] ?? [];

        if (!$existing) {
            $args['tax_query'] = $tax;

            return $args;
        }

        // قیدهای ثابتِ آرشیو (دسته، دیده‌شدن) باید بمانند، نه اینکه
        // فیلترهای کاربر جایشان را بگیرند.
        $args['tax_query'] = ['relation' => 'AND', $existing, $tax];

        return $args;
    }

    /** نام تاکسونومی، با همان تابعی که ووکامرس روی ورودی آدرس اجرا می‌کند */
    private static function taxonomy_name(string $taxonomy): string {
        if (function_exists('wc_sanitize_taxonomy_name')) {
            return (string) wc_sanitize_taxonomy_name($taxonomy);
        }

        return (string) preg_replace('/[^a-z0-9_\-]/', '', strtolower(trim($taxonomy)));
    }

    /**
     * همهٔ ویژگی‌های تعریف‌شدهٔ فروشگاه، به ترتیب خودِ ووکامرس.
     *
     * برای پنل لازم است: مدیر باید بتواند ویژگی‌ای را که هنوز هیچ محصولی در
     * این دسته ندارد هم اضافه کند — همان حالتی که «به‌زودی می‌آید» است.
     *
     * @return string[]
     */
    public static function all(): array {
        return self::known_taxonomies();
    }

    /**
     * تاکسونومی‌هایی که می‌شود روی آن‌ها فیلتر ساخت.
     *
     * ویژگی‌های ووکامرس، به‌علاوهٔ تاکسونومی برند.
     *
     * برند از نسخهٔ ۹٫۴ خودِ ووکامرس است (‎product_brand‎) و پنل مدیریت
     * جدا دارد؛ فروشگاه‌ها برندشان را آنجا نگه می‌دارند، نه به‌صورت
     * ویژگی. ولی ووکامرس آن را در ‎wc_get_attribute_taxonomy_names()‎
     * نمی‌آورد چون ویژگی نیست — پس بدون این، گروه «برند» هیچ‌وقت در
     * سایدبار نمی‌آمد، حتی وقتی آدرسش کار می‌کرد.
     */
    private static function known_taxonomies(): array {
        $known = [];

        if (class_exists(__NAMESPACE__ . '\\Archive_Query') && function_exists('taxonomy_exists')) {
            foreach (Archive_Query::BRAND_TAXONOMIES as $taxonomy) {
                if (taxonomy_exists($taxonomy)) {
                    $known[] = $taxonomy;
                }
            }
        }

        if (function_exists('wc_get_attribute_taxonomy_names')) {
            $known = array_merge($known, array_values((array) wc_get_attribute_taxonomy_names()));
        }

        /*
         * منبع دوم، و دلیلش یک باگ واقعی روی استیج است.
         *
         * ‎wc_get_attribute_taxonomy_names()‎ فهرست را از ترنزینت
         * ‎wc_attribute_taxonomies‎ می‌خواند. روی سایتی با کش شیء همان
         * فراخوانی می‌تواند در یک درخواست فهرست کامل بدهد و در درخواست
         * بعدی خالی — بی‌خطا و بی‌نشانه. و چون همین فهرست مبنای «کدام
         * ‎filter_*‎ مجاز است» بود، صفحهٔ سالم ‎invalid‎ می‌گرفت یعنی ‎404‎
         * و ‎noindex‎. روی استیج یک آدرسِ یکسان در دو درخواست پشت سر هم،
         * یک بار ‎invalid‎ داد و یک بار ‎ok‎.
         *
         * تاکسونومی‌های ثبت‌شده منبعی مستقل‌اند: ووکامرس یک بار روی ‎init‎
         * ثبتشان می‌کند و از آن به بعد در حافظهٔ همین درخواست می‌مانند، پس
         * کشِ سردِ وسطِ کار نمی‌تواند خالی‌شان کند.
         *
         * این «هر تاکسونومیِ ‎pa_‎داری را قبول کن» نیست: پیشوند را خودِ
         * ووکامرس رزرو کرده و ‎wc_attribute_taxonomy_name()‎ می‌سازدش، پس
         * هرچه با آن ثبت شده واقعاً ویژگیِ محصول است — همان چیزی که منبع
         * اول هم قرار بود بگوید.
         */
        if (function_exists('get_taxonomies')) {
            foreach ((array) get_taxonomies([], 'names') as $taxonomy) {
                if (0 === strpos((string) $taxonomy, 'pa_')) {
                    $known[] = (string) $taxonomy;
                }
            }
        }

        if (!function_exists('apply_filters')) {
            return array_values(array_unique($known));
        }

        /**
         * تاکسونومی‌های دیگری که این فروشگاه می‌خواهد فیلترشدنی باشند.
         *
         * @param string[] $known
         */
        return array_values(array_unique((array) apply_filters('zig3d_filterable_taxonomies', $known)));
    }
}
