<?php
namespace Zig3d_Widgets;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * دادهٔ منو — درختِ فهرستِ وردپرس، به‌علاوهٔ چیزهایی که خودِ فهرست ندارد.
 *
 * چرا فهرستِ وردپرس و نه ریپیترِ داخلِ ویجت: ترتیب و تودرتویی را مدیر با
 * کشیدن‌ورهاکردن می‌چیند، افزونه‌هایِ چندزبانه رویِ همان منو کار می‌کنند،
 * و مهم‌تر از همه ‎current-menu-item‎ را خودِ وردپرس می‌گذارد — که تنها
 * راهِ درستِ تشخیصِ آیتمِ فعال برایِ زیرخطِ طرح است. ریپیتر یعنی
 * دوباره‌نویسیِ همه‌چیز و از دست دادنِ هر سه.
 *
 * ولی فهرستِ وردپرس دو دادهٔ این طرح را ندارد: شمارشِ محصول («۱۴۱ محصول»)
 * و کارت‌هایِ محصول در مگامنو. آن دو از ووکامرس می‌آیند و پلشان همین
 * است: آیتمی که در فهرست از نوعِ *دستهٔ محصول* باشد، ‎term_id‎ دارد و از
 * رویِ آن هم شمارش درمی‌آید هم محصولات. آیتمِ «لینکِ دلخواه» این‌ها را
 * ندارد و طبیعتاً نشانشان نمی‌دهد — نه اینکه خطا بدهد.
 *
 * تفکیکِ همیشگیِ این افزونه برقرار است: اینجا هیچ HTMLی نیست.
 */
final class Menu_Tree {

    /**
     * کشِ کارت‌هایِ مگامنو با جلوبردنِ نسخه باطل می‌شود، نه با پاک‌کردنِ
     * کلید — همان الگویِ ‎Search_Query‎ و فست‌ها. نمی‌دانیم چند دسته کش
     * شده‌اند، ولی می‌دانیم همه‌شان کلیدِ نسخه‌دار دارند.
     */
    private const VERSION_OPTION = 'zig3d_menu_cache_version';

    private const TTL = 6 * HOUR_IN_SECONDS;

    /** @var array<string,mixed> حافظهٔ درون‌درخواستی */
    private static array $memo = [];

    /**
     * درختِ یک فهرست، حداکثر دو سطح (طرح بیشتر از این ندارد).
     *
     * @return array<int,array{id:int,title:string,url:string,target:string,rel:string,current:bool,term_id:int,children:array}>
     */
    public static function build(int $menu_id): array {
        if ($menu_id <= 0 || !function_exists('wp_get_nav_menu_items')) {
            return [];
        }

        $key = 'tree_' . $menu_id;

        if (isset(self::$memo[$key])) {
            return self::$memo[$key];
        }

        $items = wp_get_nav_menu_items($menu_id, ['update_post_term_cache' => false]);

        if (!is_array($items) || [] === $items) {
            return self::$memo[$key] = [];
        }

        /*
         * کلاس‌هایِ وابسته به صفحهٔ جاری (‎current-menu-item‎ و
         * ‎current-menu-ancestor‎) را ‎wp_get_nav_menu_items()‎ نمی‌گذارد؛
         * کارِ همین تابع است که ‎wp_nav_menu()‎ صدایش می‌زند. بدونش هیچ
         * آیتمی هیچ‌وقت «فعال» نمی‌شود و زیرخطِ طرح هرگز دیده نمی‌شود.
         */
        if (function_exists('_wp_menu_item_classes_by_context')) {
            _wp_menu_item_classes_by_context($items);
        }

        $by_parent = [];

        foreach ($items as $item) {
            $by_parent[(int) $item->menu_item_parent][] = $item;
        }

        return self::$memo[$key] = self::branch($by_parent, 0);
    }

    /** @param array<int,array<int,object>> $by_parent */
    private static function branch(array $by_parent, int $parent): array {
        if (empty($by_parent[$parent])) {
            return [];
        }

        $nodes = [];

        foreach ($by_parent[$parent] as $item) {
            $classes = is_array($item->classes ?? null) ? $item->classes : [];

            $nodes[] = [
                'id'       => (int) $item->ID,
                'title'    => (string) ($item->title ?? ''),
                'url'      => (string) ($item->url ?? ''),
                'target'   => (string) ($item->target ?? ''),
                'rel'      => (string) ($item->xfn ?? ''),
                // متنِ دکمهٔ ستونِ مگامنو؛ در طرح با عنوانِ ستون فرق دارد
                'description' => (string) ($item->description ?? ''),
                'classes'  => $classes,
                // «فعال» یعنی خودِ صفحه یا یکی از فرزندانش باز است — دومی
                // هم لازم است، وگرنه در صفحهٔ یک زیرمنو، والدش خاموش می‌شود
                'current'  => (bool) array_intersect(
                    ['current-menu-item', 'current-menu-ancestor', 'current-menu-parent'],
                    $classes
                ),
                'term_id'  => self::term_id($item),
                'children' => self::branch($by_parent, (int) $item->ID),
            ];
        }

        return $nodes;
    }

    /** شناسهٔ ترم، فقط وقتی آیتم واقعاً دستهٔ محصول باشد */
    private static function term_id(object $item): int {
        if ('taxonomy' !== ($item->type ?? '') || 'product_cat' !== ($item->object ?? '')) {
            return 0;
        }

        return (int) ($item->object_id ?? 0);
    }

    /* =====================================================================
     * شمارش
     * =================================================================== */

    /**
     * شمارشِ محصولاتِ یک دسته.
     *
     * کشِ جدا لازم ندارد: ‎count‎ ستونی در جدولِ ترم‌هاست و ‎get_term()‎ هم
     * خودش از کشِ آبجکتِ وردپرس می‌خواند. کشِ دوم فقط یک لایهٔ کهنگیِ
     * اضافه می‌ساخت.
     */
    public static function term_count(int $term_id): int {
        if ($term_id <= 0 || !function_exists('get_term')) {
            return 0;
        }

        $term = get_term($term_id, 'product_cat');

        return ($term && !is_wp_error($term)) ? (int) $term->count : 0;
    }

    /**
     * کلِ محصولاتِ منتشرشده — عددِ ردیفِ «مشاهده فروشگاه».
     */
    public static function shop_count(): int {
        if (isset(self::$memo['shop_count'])) {
            return self::$memo['shop_count'];
        }

        if (!function_exists('wp_count_posts')) {
            return self::$memo['shop_count'] = 0;
        }

        $counts = wp_count_posts('product');

        return self::$memo['shop_count'] = (int) ($counts->publish ?? 0);
    }

    /* =====================================================================
     * کارت‌هایِ مگامنو
     * =================================================================== */

    /**
     * پرفروش‌ترین محصولات — فقط شناسه‌ها.
     *
     * ‎$term_id‎ی صفر یعنی کلِ فروشگاه، نه «هیچ»: ستونِ «محبوب‌ترین‌ها» در
     * طرح به هیچ دسته‌ای وصل نیست و دکمه‌اش هم «مشاهده تمام محصولات»
     * است. پس فیلترِ دسته فقط وقتی به کوئری بسته می‌شود که واقعاً دسته‌ای
     * خواسته شده باشد.
     *
     * همان تفکیکِ سطحِ A/B سرچ: چیزی که کش می‌شود فهرستِ شناسه‌هاست، نه
     * مارک‌آپ. نمایش (عنوان، تصویر، پیوند) از کشِ آبجکتِ وردپرس می‌آید که
     * فراخوان با یک پاسِ دسته‌ای گرمش می‌کند.
     *
     * @return int[]
     */
    public static function popular_products(int $term_id, int $limit): array {
        if ($term_id < 0 || $limit <= 0) {
            return [];
        }

        $key = 'zig3d_menu_pop_' . self::version() . '_' . $term_id . '_' . $limit;

        if (isset(self::$memo[$key])) {
            return self::$memo[$key];
        }

        $cached = get_transient($key);

        if (is_array($cached)) {
            return self::$memo[$key] = $cached;
        }

        /*
         * سنجهٔ ‎WP_Query‎ عمداً *بعد* از کش است: وقتی کش گرم است هیچ
         * کوئری‌ای لازم نیست، پس نبودنِ کلاس هم نباید جلویِ پاسخ‌دادن را
         * بگیرد. جایِ قبلی‌اش یعنی کشِ گرم هم بی‌مصرف می‌ماند.
         */
        if (!class_exists('WP_Query')) {
            return [];
        }

        $args = [
            'post_type'              => 'product',
            'post_status'            => 'publish',
            'posts_per_page'         => $limit,
            'ignore_sticky_posts'    => true,
            // شمارشِ کلِ نتایج لازم نیست و یک ‎SQL_CALC_FOUND_ROWS‎ی گران
            // به کوئری اضافه می‌کرد
            'no_found_rows'          => true,
            'update_post_term_cache' => false,
            'orderby'                => 'meta_value_num',
            'meta_key'               => 'total_sales',
            'order'                  => 'DESC',
            'fields'                 => 'ids',
        ];

        if ($term_id > 0) {
            $args['tax_query'] = [
                [
                    'taxonomy'         => 'product_cat',
                    'field'            => 'term_id',
                    'terms'            => $term_id,
                    'include_children' => true,
                ],
            ];
        }

        $query = new \WP_Query($args);

        $ids = array_map('intval', (array) $query->posts);

        /*
         * نتیجهٔ خالی کش نمی‌شود. وگرنه فروشگاهی که هنوز محصولی ندارد —
         * یا لحظه‌ای که ووکامرس هنوز بالا نیامده — تا شش ساعت خالی
         * می‌ماند، حتی بعد از افزودنِ اولین محصول. کوئریِ دوباره در این
         * حالتِ نادر، از آن شش ساعت ارزان‌تر است.
         */
        if ([] === $ids) {
            return [];
        }

        set_transient($key, $ids, self::TTL);

        return self::$memo[$key] = $ids;
    }

    /* =====================================================================
     * نسخهٔ کش
     * =================================================================== */

    private static function version(): int {
        $version = (int) get_option(self::VERSION_OPTION, 1);

        return $version > 0 ? $version : 1;
    }

    /**
     * جلوبردنِ نسخه — هر کلیدِ قدیمی یک‌باره بی‌اثر می‌شود و خودش با
     * انقضایِ TTL جمع می‌شود.
     */
    public static function flush(): void {
        self::$memo = [];
        update_option(self::VERSION_OPTION, self::version() + 1, false);
    }
}
