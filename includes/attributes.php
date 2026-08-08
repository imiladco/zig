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
     * در دسترس بودن
     * =================================================================== */

    public static function lookup_table(): string {
        global $wpdb;

        return $wpdb->prefix . Facets::LOOKUP_TABLE;
    }

    /**
     * آیا جدول جست‌وجوی ویژگی‌های ووکامرس روشن و پر است؟
     *
     * نام گزینه از خودِ ووکامرس آمده — ‎Filterer::filtering_via_lookup_table_is_active()‎.
     * بررسی وجود جدول هم اضافه است چون روشن‌بودن گزینه تضمین نمی‌کند
     * بازتولید تمام شده باشد؛ جدولِ نیمه‌پر، عددهای کمتر از واقعیت می‌دهد.
     *
     * وقتی خاموش است، مسیر جایگزین کندتر است ولی — با شکل کوئری ما —
     * غلط‌تر نیست. توضیح کامل در ‎Facets::count_sql_terms()‎.
     */
    public static function lookup_enabled(): bool {
        if ('yes' !== get_option('woocommerce_attribute_lookup_enabled', 'no')) {
            return false;
        }

        global $wpdb;

        $table = self::lookup_table();

        return (bool) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
    }

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

        if (self::lookup_enabled()) {
            $sql = 'SELECT DISTINCT lookup.taxonomy FROM ' . self::lookup_table() . ' AS lookup'
                . ' WHERE lookup.product_or_parent_id IN (' . $base . ')';
        } else {
            $sql = 'SELECT DISTINCT tt.taxonomy FROM ' . $wpdb->term_relationships . ' AS tr'
                . ' INNER JOIN ' . $wpdb->term_taxonomy . ' AS tt ON tt.term_taxonomy_id = tr.term_taxonomy_id'
                . ' WHERE tr.object_id IN (' . $base . ')';
        }

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
        bool $in_stock_only = false
    ): ?array {
        /*
         * ثابتِ این کلاس: عددِ نادرست هرگز نمایش داده نمی‌شود — حتی با
         * هشدار در پنل.
         *
         * تنها قیدی که مسیر جایگزین نمی‌تواند بیان کند، ‎in_stock‎ است
         * (ستونش فقط در جدول جست‌وجوی ووکامرس وجود دارد). امروز به‌صورت
         * پیش‌فرض از آن استفاده نمی‌کنیم، چون شمارش باید دقیقاً همان قیدهای
         * فهرست را داشته باشد. ولی اگر روزی کسی روشنش کند و جدول خاموش
         * باشد، اینجا باید بایستد نه اینکه عددِ بزرگ‌ترِ بی‌سروصدا بدهد.
         */
        if ($in_stock_only && !self::lookup_enabled()) {
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

        if (self::lookup_enabled()) {
            $sql = Facets::count_sql(self::lookup_table(), $base, $in_stock_only);
        } else {
            $sql = Facets::count_sql_terms($wpdb->term_relationships, $wpdb->term_taxonomy, $base);
        }

        /*
         * چرا ‎prepare()‎ روی کل رشته اجرا نمی‌شود: کوئری پایه می‌تواند
         * قانوناً ‎%‎ داشته باشد — هر جست‌وجویی یک ‎LIKE '%…%'‎ می‌سازد — و
         * ‎prepare()‎ آن را جای‌نگهدار می‌خواند و با «تعداد آرگومان‌ها
         * نمی‌خواند» شکست می‌خورد. نام تاکسونومی هم بالادست به
         * ‎[a-z0-9_-]‎ محدود شده و اینجا دوباره بررسی می‌شود، پس چیزی برای
         * فرار از کوتیشن نمی‌ماند.
         */
        if ('' === $facet || preg_match('/[^a-z0-9_\-]/', $facet)) {
            return null;
        }

        $rows = $wpdb->get_results(str_replace('%s', "'" . esc_sql($facet) . "'", $sql));

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
        ] + self::term_order($taxonomy));

        if (!is_array($terms)) {
            return [];
        }

        $out = [];

        foreach ($terms as $term) {
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
     * ترتیب گزینه‌ها، همان که مدیر برای این ویژگی انتخاب کرده.
     *
     * ووکامرس برای هر ویژگی یک ترتیب جدا نگه می‌دارد و «۵ محور، ۳ محور،
     * ۸ محور» فقط با ترتیب دستی درست می‌شود؛ الفبایی یا عددی، فهرست را
     * به‌هم می‌ریزد. نادیده‌گرفتنش یعنی چیدمانی که مدیر ساخته، در سایدبار
     * دیده نمی‌شود.
     */
    private static function term_order(string $taxonomy): array {
        $order = function_exists('wc_attribute_orderby') ? wc_attribute_orderby($taxonomy) : 'name';

        switch ($order) {
            case 'menu_order':
                return ['orderby' => 'meta_value_num', 'meta_key' => 'order_' . $taxonomy, 'order' => 'ASC'];

            case 'name_num':
                return ['orderby' => 'name_num', 'order' => 'ASC'];

            case 'id':
                return ['orderby' => 'id', 'order' => 'ASC'];

            default:
                return ['orderby' => 'name', 'order' => 'ASC'];
        }
    }

    /** برچسب گروه، همان که مدیر در ووکامرس گذاشته */
    public static function label(string $taxonomy): string {
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

        $query = new \WP_Query();
        $query->query($args);

        remove_filter('posts_pre_query', $short_circuit, 100);

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

    /** تاکسونومی‌های ویژگی که ووکامرس می‌شناسد، به ترتیب خودش */
    private static function known_taxonomies(): array {
        if (function_exists('wc_get_attribute_taxonomy_names')) {
            return array_values((array) wc_get_attribute_taxonomy_names());
        }

        return [];
    }
}
