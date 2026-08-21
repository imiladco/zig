<?php
namespace Zig3d_Widgets;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * کوئریِ سرچ: چه چیزی مچ شد، و چطور برایِ نمایش آماده می‌شود.
 *
 * دو سطح، عمداً از هم جدا — و این جدایی مالِ تمیزی نیست، مالِ کش است:
 *
 *   سطح A («چه چیزی مچ شد» — ‎match()‎)
 *     نتیجهٔ جست‌وجو: فهرستِ شناسه‌ها به ترتیبِ رتبه، و اینکه نتیجهٔ
 *     بیشتری هست یا نه. این بخش گران است (چند ‎LIKE‎ روی چند وریانتِ
 *     نرمال‌شده) و کش می‌شود، پشتِ یک شمارندهٔ نسخه که با تغییرِ محتوا
 *     جلو می‌رود — نه TTLِ تنها، وگرنه محصولی که همین الان ویرایش شده
 *     تا پایانِ TTL کهنه می‌ماند.
 *
 *   سطح B («چطور نمایش داده شود» — ‎hydrate()‎)
 *     عنوان، لینک، تصویرِ بندانگشتی، برچسبِ دسته/برند. این بخش کش
 *     *نمی‌شود*: منبعِ کشِ خودش را از کشِ آبجکتِ خودِ وردپرس می‌گیرد
 *     (‎update_post_thumbnail_cache‎/‎update_object_term_cache‎ که اینجا
 *     صدا زده می‌شوند) و هر بار تازه رندر می‌شود. اگر روزی
 *     ‎category_source‎/‎brand_source‎ (اینکه کدام تاکسونومی به‌عنوانِ
 *     برچسب نشان داده شود) روی خودِ *مچ‌شدن* اثر بگذارد، جایش این‌جا
 *     نیست — باید به ‎match_taxonomies‎ در سطحِ A منتقل شود تا در امضایِ
 *     کش هم بیاید.
 *
 * دو مسیرِ مچ، یکی‌شده در یک کوئری:
 *
 *   Pass A  عنوان/‎SKU‎ خودِ محصول، با ‎LIKE‎ روی وریانت‌هایِ
 *           ‎Search_Normalizer‎.
 *   Pass B  نامِ ترم (دسته/برند)، چون منبعِ حقیقتش خودِ ‎wp_terms‎ است
 *           و همیشه تازه — برخلافِ یک ایندکسِ سایه که می‌توانست بعدِ
 *           تغییرِ نامِ ترم کهنه بماند. به همین دلیل، فازِ یک هیچ
 *           ایندکسِ دنرمال‌شده‌ای نمی‌سازد؛ فقط زمانِ کوئری بسط می‌دهد.
 *
 * رتبه‌بندی دو لایه دارد و امتیازش ‎Tier×۱۰ + وزنِ فیلد‎ است:
 *
 *   لایهٔ اول  همان چهار Tierِ ‎Search_Normalizer‎ — هرچه Tier کوچک‌تر،
 *             بالاتر (تطابقِ دقیق بالاتر از بسطِ مترادف).
 *   لایهٔ دوم  در *همان* Tier: عنوان > کدِ محصول > نامِ ترم. بدونِ این،
 *             محصولی که نامش دقیقاً همان عبارت است می‌تواند زیرِ
 *             محصولی بیفتد که فقط دسته‌اش آن اسم را دارد.
 *
 * عملکرد یک هدف است، نه یک ترفندِ منجمد — به‌جایِ ‎fields => 'ids'‎ که
 * بدونِ batch-priming می‌توانست N+1 بسازد:
 *
 *   P1  هیچ رفت‌وبرگشتی با تعدادِ نتیجه رشد نمی‌کند (۳ نتیجه همان تعداد
 *       کوئری را می‌گیرد که ۳۰ تا).
 *   P2  ‎no_found_rows = true‎ الزامی — هیچ ‎SQL_CALC_FOUND_ROWS‎ای.
 *   P3  «نتیجهٔ بیشتر هست؟» با گرفتنِ ‎limit+1‎ و دورریختنِ اضافه —
 *       هیچ کوئریِ ‎COUNT‎ی.
 *   P4  متا/ترم/بندانگشتی همه batch-primed (‎update_post_thumbnail_cache‎،
 *       ‎update_object_term_cache‎ روی کلِ نتیجه، نه یک صدا به ازایِ هر ردیف).
 *   P5  ثابت‌شده با یک تستِ استاب/شمارنده: صدازدنِ batch loaderها *یک‌بار*،
 *       نه اثباتِ «کوئریِ O(1)» — این هارنس دیتابیسِ واقعی ندارد؛ شمارشِ
 *       واقعیِ کوئری روی استیجینگ با Query Monitor سنجیده می‌شود.
 *
 * مرزِ pure/impure دقیقاً همان‌جایی است که بقیهٔ افزونه رعایتش می‌کند
 * (نگاه کنید به ‎Facets::count_sql()‎ در برابرِ ‎Attributes::counts()‎):
 * تابع‌هایی که SQL می‌سازند، ورودی‌هایشان از پیش escape/quote شده‌اند و
 * خودشان به ‎$wpdb‎ دست نمی‌زنند — پس بدونِ وردپرس هم قابلِ سنجش‌اند.
 * فقط لایهٔ اجراکننده (‎run_query()‎/‎hydrate()‎) به ‎$wpdb‎/‎WP_Query‎
 * وصل است و همان‌جا هم می‌ماند، تست‌نشده — دقیقاً مثلِ ‎Attributes::base_sql()‎.
 */
final class Search_Query {

    /** سه‌تا، همان تعدادی که قابِ «حاوی نتیجه» نشان می‌دهد */
    public const DEFAULT_LIMIT = 3;
    public const MAX_LIMIT     = 40;

    /** فرارِ کش تا وقتی نسخه جلو نرفته — شبکهٔ ایمنی، نه ضامنِ اصلیِ تازگی */
    private const CACHE_TTL = 300;

    /** کلیدِ نسخهٔ کش — همان الگویِ ‎Attributes::VERSION_OPTION‎ */
    private const VERSION_OPTION = 'zig3d_search_cache_version';

    /* =====================================================================
     * سطح A — چه چیزی مچ شد (کش می‌شود)
     * =================================================================== */

    /**
     * @param array $args {
     *     @type string   $post_type         پیش‌فرض ‎product‎.
     *     @type int      $limit             حداکثرِ نتیجه.
     *     @type string[] $search_fields     زیرمجموعه‌ای از ‎['title','sku']‎.
     *     @type string[] $match_taxonomies  تاکسونومی‌هایی که نامِ ترمشان هم جست‌وجو می‌شود.
     *     @type array    $synonym_pairs     جفت‌های ‎[a, b]‎ از تنظیماتِ ویجت.
     * }
     * @return array{ids:int[],has_more:bool}
     */
    public static function match(string $raw_query, array $args = []): array {
        $args       = self::normalize_args($args);
        $normalized = Search_Normalizer::normalize($raw_query);

        if ('' === $normalized) {
            return ['ids' => [], 'has_more' => false];
        }

        $key    = self::cache_key(self::version(), $normalized, $args, Archive_Query::hides_out_of_stock());
        $cached = get_transient($key);

        if (is_array($cached) && isset($cached['ids'], $cached['has_more'])) {
            return $cached;
        }

        $variants = Search_Normalizer::variants($raw_query, $args['synonym_pairs']);
        $result   = $variants ? self::run_query($variants, $args) : ['ids' => [], 'has_more' => false];

        set_transient($key, $result, self::CACHE_TTL);

        return $result;
    }

    /**
     * پاک‌سازیِ آرگومان‌هایِ عمومی — بدونِ آن، یک تنظیمِ دستکاری‌شده
     * (‎limit‎ منفی، ‎search_fields‎ ناشناخته) کلیدِ کش را بی‌صدا خراب
     * می‌کرد یا کوئریِ بی‌مرز می‌ساخت.
     */
    public static function normalize_args(array $args): array {
        $limit = (int) ($args['limit'] ?? self::DEFAULT_LIMIT);
        $limit = $limit < 1 ? self::DEFAULT_LIMIT : min($limit, self::MAX_LIMIT);

        $fields = array_values(array_intersect(
            array_map('strval', (array) ($args['search_fields'] ?? ['title'])),
            ['title', 'sku']
        ));

        if (!$fields) {
            $fields = ['title'];
        }

        $taxonomies = array_values(array_unique(array_filter(
            array_map('strval', (array) ($args['match_taxonomies'] ?? []))
        )));

        $pairs = [];

        foreach ((array) ($args['synonym_pairs'] ?? []) as $pair) {
            if (is_array($pair) && isset($pair[0], $pair[1]) && '' !== trim((string) $pair[0]) && '' !== trim((string) $pair[1])) {
                $pairs[] = [(string) $pair[0], (string) $pair[1]];
            }
        }

        return [
            'post_type'        => (string) ($args['post_type'] ?? 'product'),
            'limit'            => $limit,
            'search_fields'    => $fields,
            'match_taxonomies' => $taxonomies,
            'synonym_pairs'    => $pairs,
        ];
    }

    /**
     * وریانت‌هایِ ‎Search_Normalizer::variants()‎ را بر اساسِ Tier گروه
     * می‌کند — همان شکلی که ‎match_sql()‎ برایِ ساختنِ ‎CASE‎ نیاز دارد.
     *
     * @param array<int,array{tier:int,text:string}> $variants
     * @return array<int,string[]> Tier ⇒ متن‌ها، مرتب بر اساسِ Tier
     */
    public static function tier_groups(array $variants): array {
        $groups = [];

        foreach ($variants as $variant) {
            $tier = (int) ($variant['tier'] ?? 0);
            $text = trim((string) ($variant['text'] ?? ''));

            if ($tier < 1 || '' === $text) {
                continue;
            }

            $groups[$tier][] = $text;
        }

        ksort($groups);

        return $groups;
    }

    /**
     * جداکردنِ «صفحهٔ فعلی» از «آیا بیشتر هم هست» — بدونِ کوئریِ ‎COUNT‎
     * (P3). ورودی همان چیزی است که با ‎posts_per_page = limit + 1‎
     * می‌آید: اگر یکی زیادتر برگشته، همان یکی نشانِ «بیشتر هست» است و
     * خودش دور ریخته می‌شود.
     *
     * @param int[] $ids
     * @return array{ids:int[],has_more:bool}
     */
    public static function split_has_more(array $ids, int $limit): array {
        $ids      = array_values($ids);
        $has_more = count($ids) > $limit;

        return [
            'ids'      => $has_more ? array_slice($ids, 0, $limit) : $ids,
            'has_more' => $has_more,
        ];
    }

    /**
     * ساختنِ بندهایِ ‎WHERE‎/‎ORDER BY‎/‎JOIN‎ — تابعِ خالص.
     *
     * ورودی‌ها از پیش quote/escape شده‌اند (مثلِ ‎"'%foo%'"‎)؛ این تابع
     * فقط رشته می‌چیند، به ‎$wpdb‎ دست نمی‌زند — دقیقاً همان مرزی که
     * ‎Facets::count_sql()‎ رعایت می‌کند.
     *
     * @param array<int,string[]> $tier_groups_quoted Tier ⇒ الگوهایِ ‎LIKE‎ی از پیش quote‌شده
     * @param string[]             $taxonomies_quoted  نامِ تاکسونومی‌ها، از پیش quote‌شده
     * @param array<string,string> $tables              {posts,postmeta,term_relationships,term_taxonomy,terms}
     * @return array{where:string,orderby:string,join:string}
     */
    public static function match_sql(array $tier_groups_quoted, array $taxonomies_quoted, array $search_fields, array $tables): array {
        $posts = $tables['posts'] ?? 'wp_posts';

        $match_title = in_array('title', $search_fields, true);
        $match_sku   = in_array('sku', $search_fields, true);

        $join = '';

        if ($match_sku) {
            $join = " LEFT JOIN {$tables['postmeta']} AS zig_search_sku"
                . " ON zig_search_sku.post_id = {$posts}.ID AND zig_search_sku.meta_key = '_sku'";
        }

        /*
         * هر دو طرفِ مقایسه نرمال می‌شوند، نه فقط کوئری — وگرنه عنوانی
         * که ادمین با «ي»ِ عربی ذخیره کرده هیچ‌وقت با کوئریِ canonical
         * پیدا نمی‌شود. نگاه کنید به داک‌بلاکِ ‎Search_Normalizer::sql_expr()‎.
         */
        $title_expr = Search_Normalizer::sql_expr("{$posts}.post_title");
        $sku_expr   = Search_Normalizer::sql_expr('zig_search_sku.meta_value');
        $term_expr  = Search_Normalizer::sql_expr('t.name');

        $term_source = '';

        if ($taxonomies_quoted) {
            $term_source = "SELECT tr.object_id FROM {$tables['term_relationships']} AS tr"
                . " INNER JOIN {$tables['term_taxonomy']} AS tt ON tt.term_taxonomy_id = tr.term_taxonomy_id"
                . " INNER JOIN {$tables['terms']} AS t ON t.term_id = tt.term_id"
                . ' WHERE tt.taxonomy IN (' . implode(',', $taxonomies_quoted) . ')';
        }

        /*
         * امتیاز = Tier×۱۰ + وزنِ فیلد. وزنِ فیلد همان ترتیبی است که
         * قفل شد: عنوان > کدِ محصول > نامِ ترم. بدونِ این، سه محصولی که
         * همگی در Tier 1 مچ شده‌اند به ترتیبِ دلخواهِ دیتابیس می‌آیند و
         * محصولی که *نامش* دقیقاً همان عبارت است می‌تواند زیرِ محصولی
         * بیفتد که فقط دسته‌اش آن اسم را دارد.
         */
        $ranked = [];
        $all    = [];

        foreach ($tier_groups_quoted as $tier => $patterns) {
            if (!$patterns) {
                continue;
            }

            $tier      = (int) $tier;
            $per_field = [];

            if ($match_title) {
                $per_field[0] = '(' . implode(' OR ', array_map(
                    static fn(string $p): string => "{$title_expr} LIKE {$p}",
                    $patterns
                )) . ')';
            }

            if ($match_sku) {
                $per_field[1] = '(' . implode(' OR ', array_map(
                    static fn(string $p): string => "{$sku_expr} LIKE {$p}",
                    $patterns
                )) . ')';
            }

            if ('' !== $term_source) {
                $name_or = implode(' OR ', array_map(
                    static fn(string $p): string => "{$term_expr} LIKE {$p}",
                    $patterns
                ));

                $per_field[2] = "{$posts}.ID IN ({$term_source} AND ({$name_or}))";
            }

            foreach ($per_field as $weight => $sql) {
                $ranked[$tier * 10 + $weight] = $sql;
                $all[] = $sql;
            }
        }

        if (!$all) {
            return ['where' => '', 'orderby' => '', 'join' => ''];
        }

        ksort($ranked);

        $case = 'CASE ';

        foreach ($ranked as $score => $sql) {
            $case .= "WHEN {$sql} THEN {$score} ";
        }

        $case .= 'ELSE 9999 END';

        return [
            'where'   => '(' . implode(' OR ', $all) . ')',
            'orderby' => $case . ' ASC',
            'join'    => $join,
        ];
    }

    /**
     * شناسهٔ یکتایِ ترکیبِ «چه چیزی جست‌وجو شد + چه چیزی مچ‌شدن را عوض
     * می‌کند» — تابعِ خالص، نسخه از بیرون داده می‌شود تا بدونِ
     * ‎get_option()‎ هم قابلِ سنجش باشد.
     *
     * عمداً بیرون: ‎category_source‎/‎brand_source‎ی صرفاً نمایشی — آن‌ها
     * چیزی را که مچ می‌شود عوض نمی‌کنند، فقط برچسبِ زیرِ عنوان را. اگر
     * روزی روی رتبه‌بندی اثر گذاشتند، باید از راهِ ‎match_taxonomies‎ به
     * این امضا وارد شوند.
     */
    public static function cache_key(int $version, string $normalized_query, array $args, bool $hide_out_of_stock): string {
        $parts = [
            'q'   => $normalized_query,
            'pt'  => $args['post_type'],
            'lim' => $args['limit'],
            'sf'  => $args['search_fields'],
            'tax' => $args['match_taxonomies'],
            'syn' => $args['synonym_pairs'],
            'oos' => $hide_out_of_stock ? 1 : 0,
        ];

        return 'zig3d_search_' . substr(md5($version . '|' . json_encode($parts)), 0, 20);
    }

    /* =====================================================================
     * سطح A — اجرا (بدونِ تست، مثلِ ‎Attributes::base_sql()‎)
     * =================================================================== */

    private static function run_query(array $variants, array $args): array {
        global $wpdb;

        $tier_groups = self::tier_groups($variants);

        if (!$tier_groups) {
            return ['ids' => [], 'has_more' => false];
        }

        $quoted_by_tier = [];

        foreach ($tier_groups as $tier => $texts) {
            $quoted_by_tier[$tier] = array_map(
                static fn(string $t): string => "'%" . esc_sql($wpdb->esc_like($t)) . "%'",
                $texts
            );
        }

        $taxonomies_quoted = array_map(
            static fn(string $t): string => "'" . esc_sql($t) . "'",
            $args['match_taxonomies']
        );

        $tables = [
            'posts'              => $wpdb->posts,
            'postmeta'           => $wpdb->postmeta,
            'term_relationships' => $wpdb->term_relationships,
            'term_taxonomy'      => $wpdb->term_taxonomy,
            'terms'              => $wpdb->terms,
        ];

        $sql = self::match_sql($quoted_by_tier, $taxonomies_quoted, $args['search_fields'], $tables);

        if ('' === $sql['where']) {
            return ['ids' => [], 'has_more' => false];
        }

        $limit = $args['limit'];

        $query_args = [
            'post_type'           => $args['post_type'],
            'post_status'         => 'publish',
            'ignore_sticky_posts' => true,
            'no_found_rows'       => true, // P2
            'posts_per_page'      => $limit + 1, // P3
            'orderby'             => 'menu_order title',
            'order'               => 'ASC',
            'tax_query'           => Archive_Query::visibility_clauses(),
        ];

        $query = new \WP_Query();

        /*
         * پل‌بسته به هویتِ همین شیِ کوئری — نه یک فیلترِ سراسری که در
         * بازهٔ اجرای همین درخواست، کوئریِ افزونهٔ دیگری را هم دستکاری
         * کند. همان الگویِ ‎Sorting::scope()‎، ساده‌شده چون اینجا پلی‌ای
         * که مالِ کسِ دیگری باشد وجود ندارد — خودمان اضافه می‌کنیم و
         * خودمان برمی‌داریم.
         */
        $filter = static function (array $clauses, $running) use ($query, $sql): array {
            if ($running !== $query) {
                return $clauses;
            }

            $clauses['join']   .= ' ' . $sql['join'];
            $clauses['where']  .= ' AND ' . $sql['where'];
            $clauses['orderby'] = $sql['orderby'] . ', ' . $clauses['orderby'];

            return $clauses;
        };

        add_filter('posts_clauses', $filter, 20, 2);

        try {
            $query->query($query_args);
        } finally {
            remove_filter('posts_clauses', $filter, 20);
        }

        $ids = array_map(static fn($post): int => (int) $post->ID, $query->posts);

        return self::split_has_more($ids, $limit);
    }

    /** جلو بردنِ نسخه — همان الگویِ ‎Attributes::flush()‎ */
    public static function flush(): void {
        update_option(self::VERSION_OPTION, self::version() + 1, false);
    }

    private static function version(): int {
        return (int) get_option(self::VERSION_OPTION, 1);
    }

    /* =====================================================================
     * سطح B — چطور نمایش داده شود (کش نمی‌شود)
     * =================================================================== */

    /**
     * @param int[] $ids ترتیب همان ترتیبِ رتبه‌بندیِ ‎match()‎ است و حفظ می‌شود.
     * @param array $args {@type string $post_type, $category_taxonomy, $brand_taxonomy}
     * @return array<int,array{id:int,title:string,permalink:string,thumbnail:?array{url:string,alt:string},category:string,brand:string}>
     */
    public static function hydrate(array $ids, array $args = []): array {
        $ids = array_values(array_unique(array_map('intval', $ids)));

        if (!$ids) {
            return [];
        }

        $post_type = (string) ($args['post_type'] ?? 'product');

        $query = new \WP_Query([
            'post_type'           => $post_type,
            'post_status'         => 'publish',
            'post__in'            => $ids,
            'orderby'             => 'post__in',
            'posts_per_page'      => count($ids),
            'no_found_rows'       => true,
            'ignore_sticky_posts' => true,
        ]);

        return self::hydrate_posts($query->posts, $query, $post_type, $args);
    }

    /**
     * بدنهٔ ‎hydrate()‎، جدا از ساختِ ‎WP_Query‎ — تا بشود بدونِ دیتابیس
     * سنجیدش: پست‌های ازقبل‌واکشی‌شده (هر شیِ duck-typed با ‎->ID‎) و
     * نشانه‌ای که فقط دستِ ‎update_post_thumbnail_cache()‎ می‌رسد.
     *
     * دقیقاً همین‌جا P4/P5 تضمین می‌شود: هر دو پرایمِ batch یک‌بار برایِ
     * *کلِ* ‎$posts‎ صدا زده می‌شوند، نه یک‌بار به ازایِ هر پست.
     *
     * @param object[] $posts
     * @param mixed    $thumbnail_cache_query چیزی که ‎update_post_thumbnail_cache()‎ می‌پذیرد (یک ‎WP_Query‎)
     */
    public static function hydrate_posts(array $posts, $thumbnail_cache_query, string $post_type, array $args): array {
        if (!$posts) {
            return [];
        }

        if (function_exists('update_post_thumbnail_cache')) {
            update_post_thumbnail_cache($thumbnail_cache_query);
        }

        $category_tax = (string) ($args['category_taxonomy'] ?? '');
        $brand_tax    = (string) ($args['brand_taxonomy'] ?? '');
        $taxonomies   = array_values(array_unique(array_filter([$category_tax, $brand_tax])));

        if ($taxonomies && function_exists('update_object_term_cache')) {
            update_object_term_cache(array_map(static fn($post): int => (int) $post->ID, $posts), $post_type);
        }

        return array_map(
            static fn($post): array => self::shape($post, $category_tax, $brand_tax),
            $posts
        );
    }

    private static function shape($post, string $category_tax, string $brand_tax): array {
        $id = (int) $post->ID;

        return [
            'id'        => $id,
            'title'     => (string) get_the_title($id),
            'permalink' => (string) get_permalink($id),
            'thumbnail' => self::thumbnail($id),
            'category'  => '' !== $category_tax ? self::term_label($id, $category_tax) : '',
            'brand'     => '' !== $brand_tax ? self::term_label($id, $brand_tax) : '',
        ];
    }

    private static function thumbnail(int $post_id): ?array {
        $attachment_id = (int) get_post_thumbnail_id($post_id);

        if (!$attachment_id) {
            return null;
        }

        $src = wp_get_attachment_image_src($attachment_id, 'thumbnail');

        if (!$src) {
            return null;
        }

        return [
            'url' => (string) $src[0],
            'alt' => (string) get_post_meta($attachment_id, '_wp_attachment_image_alt', true),
        ];
    }

    private static function term_label(int $post_id, string $taxonomy): string {
        if (!taxonomy_exists($taxonomy)) {
            return '';
        }

        $terms = get_the_terms($post_id, $taxonomy);

        if (!is_array($terms) || !$terms) {
            return '';
        }

        return (string) $terms[0]->name;
    }
}
