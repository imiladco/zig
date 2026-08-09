<?php
namespace Zig3d_Widgets;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ساختن کوئری آرشیو محصولات.
 *
 * یک قاعده اینجا حاکم است و بقیهٔ این فایل از آن می‌آید: **فهرست و شمارش
 * باید از یک کوئری پایه بیایند.**
 *
 * اگر دو جای متفاوت کوئری بسازند — یکی برای گرید و یکی برای اعداد کنار
 * فیلترها — دیر یا زود یکی قیدی می‌گیرد که دیگری ندارد. آن اختلاف هیچ
 * خطایی نمی‌دهد: کاربر «۷» را می‌بیند، کلیک می‌کند و ۹ محصول می‌آید. پس
 * ‎base_args()‎ تنها جایی است که قیدهای ثابت آرشیو نوشته می‌شوند، و هم
 * گرید و هم ‎Attributes‎ از همان‌جا می‌خوانند.
 *
 * قیدهای «ثابت» یعنی چیزهایی که به انتخاب کاربر ربطی ندارند: نوع پست،
 * وضعیت انتشار، دیده‌شدن در فهرست، سیاست نمایش ناموجودها، و دسته‌ای که
 * در آن هستیم. فیلترها و ترتیب و صفحه بعداً رویش سوار می‌شوند.
 *
 * این تفکیک فقط تمیزکاری نیست، شرط درستیِ شمارش است: ‎Attributes‎ باید
 * کوئری پایه را *بدون* ترتیب و صفحه بگیرد. ترتیب برای شمارش بی‌اثر است
 * ولی ‎JOIN‎ سنگین می‌آورد، و ‎paged‎ که وارد زیرکوئریِ شمارش شود یعنی
 * فقط محصولات همان صفحه شمرده می‌شوند — عددی که همیشه از ‎posts_per_page‎
 * کوچک‌تر است و کاملاً هم معقول به نظر می‌رسد.
 */
final class Archive_Query {

    /** حداکثر محصول در هر صفحه — سقفی برای جلوگیری از کوئری بی‌مرز */
    public const MAX_PER_PAGE = 96;

    /* =====================================================================
     * قیدهای ثابت
     * =================================================================== */

    /**
     * کوئری پایه: هرچه به انتخاب کاربر ربطی ندارد.
     *
     * @param array $scope {
     *     @type int[]  $categories شناسهٔ دسته‌ها؛ خالی یعنی «دستهٔ صفحهٔ جاری».
     *     @type int[]  $exclude    محصولاتی که هرگز نباید بیایند.
     *     @type string $search     عبارت جست‌وجو.
     * }
     */
    public static function base_args(array $scope = []): array {
        $args = [
            'post_type'           => 'product',
            'post_status'         => 'publish',
            'ignore_sticky_posts' => true,
            'tax_query'           => self::visibility_clauses(),
        ];

        $categories = self::ids($scope['categories'] ?? []);

        if ($categories) {
            $args['tax_query'][] = [
                'taxonomy'         => Schema_Store::TAXONOMY,
                'field'            => 'term_id',
                'terms'            => $categories,
                'include_children' => true,
            ];
        }

        $exclude = self::ids($scope['exclude'] ?? []);

        if ($exclude) {
            $args['post__not_in'] = $exclude;
        }

        $search = trim((string) ($scope['search'] ?? ''));

        if ('' !== $search) {
            $args['s'] = $search;
        }

        if (count($args['tax_query']) > 1) {
            $args['tax_query'] = array_merge(['relation' => 'AND'], $args['tax_query']);
        }

        return $args;
    }

    /**
     * بندهای «دیده‌شدن»، همان‌طور که خودِ ووکامرس می‌سازد.
     *
     * دو چیز جدا هستند و قاطی‌کردنشان خطای رایجی است:
     *
     *   • ‎exclude-from-catalog‎ یعنی مدیر گفته این محصول در فهرست‌ها نیاید
     *     (فقط با لینک مستقیم). این همیشه اعمال می‌شود.
     *   • ‎outofstock‎ فقط وقتی که فروشگاه «پنهان‌کردن ناموجودها» را روشن
     *     کرده باشد.
     *
     * بدون اولی، محصولی که مدیر عمداً از فهرست بیرون گذاشته در گرید ما
     * ظاهر می‌شد — یعنی ویجت ما تنظیم خودِ ووکامرس را دور می‌زد.
     */
    public static function visibility_clauses(): array {
        if (!function_exists('wc_get_product_visibility_term_ids')) {
            return [];
        }

        $terms  = wc_get_product_visibility_term_ids();
        $hidden = [];

        if (!empty($terms['exclude-from-catalog'])) {
            $hidden[] = (int) $terms['exclude-from-catalog'];
        }

        if (self::hides_out_of_stock() && !empty($terms['outofstock'])) {
            $hidden[] = (int) $terms['outofstock'];
        }

        if (!$hidden) {
            return [];
        }

        return [[
            'taxonomy' => 'product_visibility',
            'field'    => 'term_taxonomy_id',
            'terms'    => $hidden,
            'operator' => 'NOT IN',
        ]];
    }

    /** سیاست فروشگاه دربارهٔ نمایش ناموجودها */
    public static function hides_out_of_stock(): bool {
        return 'yes' === get_option('woocommerce_hide_out_of_stock_items', 'no');
    }

    /* =====================================================================
     * سوارکردن انتخاب کاربر
     * =================================================================== */

    /**
     * کوئری کامل: پایه + فیلترها + ترتیب + صفحه.
     *
     * @param array       $base      خروجی ‎base_args()‎.
     * @param Query_State $state     انتخاب‌های کاربر.
     * @param array       $operators تاکسونومی ⇒ ‎or‎/‎and‎، از طرح فیلتر.
     * ⚠ وقتی ‎$sort‎ داده شود، این تابع اثر جانبیِ سراسری دارد (توضیح در
     * ‎run()‎). مستقیم صدایش نزنید مگر خودتان ‎Sorting::release()‎ را جفت
     * کنید؛ راه درست ‎run()‎ است.
     *
     * @param array|null  $sort      گزینهٔ ترتیب، یا ‎null‎ برای پیش‌فرض.
     * @param int         $per_page  تعداد در هر صفحه.
     */
    public static function build(
        array $base,
        Query_State $state,
        array $operators = [],
        ?array $sort = null,
        int $per_page = 12
    ): array {
        $args = self::with_filters($base, $state, $operators);

        if (null !== $sort) {
            /*
             * ترتیب می‌تواند ‎meta_key‎ بیاورد و باید روی آرگومان‌های پایه
             * بنشیند، نه زیرشان: اگر آرایه‌ها برعکس ادغام شوند، هر ترتیبی
             * بی‌صدا بی‌اثر می‌ماند و فهرست همیشه به ترتیب پیش‌فرض می‌آید.
             */
            $args = array_merge($args, Sorting::query_args($sort));
        }

        $args['posts_per_page'] = self::per_page($per_page);
        $args['paged']          = $state->page();

        return $args;
    }

    /**
     * اجرای کوئری آرشیو — تنها راه سانکشن‌شده.
     *
     * ‎build()‎ به‌تنهایی امن نیست و این تنها دلیل وجود این تابع است.
     *
     * ‎Sorting::query_args()‎ برای قیمت و پرفروش‌ترین و امتیاز، یک فیلتر
     * ‎posts_clauses‎ سراسری ثبت می‌کند که *نمی‌تواند* بفهمد روی کدام کوئری
     * نشسته — امضایش پارامتر ‎$query‎ ندارد. خودِ ووکامرس آن را روی
     * ‎the_posts‎ برمی‌دارد، ولی آن هوک فقط برای کوئری اصلی بسته شده.
     *
     * یعنی اگر ما ثبت کنیم و پاک نکنیم، فیلتر روی هر ‎WP_Query‎ بعدی در
     * همان درخواست می‌نشیند: یک JOIN ناخواسته روی
     * ‎wc_product_meta_lookup‎ و یک ‎ORDER BY‎ که داخل زیرکوئریِ شمارش فست
     * هم می‌رود. هیچ‌کدام خطا نمی‌دهند؛ فقط جای دیگری از صفحه بی‌صدا عوض
     * می‌شود.
     *
     * پس ثبت، اجرا و پاک‌سازی در یک تابع می‌مانند و ‎finally‎ تضمین می‌کند
     * حتی اگر کوئری استثنا بدهد، فیلتر جا نماند. یک ‎release()‎ ابتدایی هم
     * هست، چون افزونهٔ دیگری ممکن است قبل از ما همین کار را کرده و پاک
     * نکرده باشد.
     */
    public static function run(
        array $base,
        Query_State $state,
        array $operators = [],
        ?array $sort = null,
        int $per_page = 12
    ): \WP_Query {
        Sorting::release();

        try {
            return new \WP_Query(self::build($base, $state, $operators, $sort, $per_page));
        } finally {
            Sorting::release();
        }
    }

    /**
     * افزودن ‎tax_query‎ فیلترها، بدون از دست دادن قیدهای ثابت.
     *
     * نکتهٔ ظریف: بندهای پایه و بندهای فیلتر در یک آرایهٔ تخت ادغام
     * نمی‌شوند، بلکه هرکدام یک گروه می‌مانند. اگر تخت می‌شدند و یکی از
     * دو طرف ‎relation‎ خودش را داشت، آن ‎relation‎ روی کل مجموعه اعمال
     * می‌شد — یعنی یک ‎OR‎ داخلی می‌توانست قید دیده‌شدن را هم اختیاری کند.
     */
    public static function with_filters(array $args, Query_State $state, array $operators = []): array {
        $filters = Facets::tax_query($state, $operators);

        if (!$filters) {
            return $args;
        }

        $existing = $args['tax_query'] ?? [];

        $args['tax_query'] = $existing
            ? ['relation' => 'AND', $existing, $filters]
            : $filters;

        return $args;
    }

    /**
     * شناسه‌های معتبر.
     *
     * شناسهٔ منفی یا صفر در وردپرس معنایی ندارد ولی خطا هم نمی‌دهد — فقط
     * یک بند ‎tax_query‎ می‌سازد که هیچ‌وقت چیزی پیدا نمی‌کند و کل آرشیو را
     * خالی می‌کند. یک تنظیم دست‌کاری‌شده یا یک تکرارشوندهٔ نیمه‌پرشدهٔ
     * المنتور، دقیقاً همین را می‌فرستد.
     *
     * @return int[]
     */
    private static function ids($value): array {
        $ids = array_map('intval', (array) $value);

        return array_values(array_filter($ids, static fn(int $id): bool => $id > 0));
    }

    /** تعداد در هر صفحه، با سقف */
    public static function per_page(int $per_page): int {
        if ($per_page < 1) {
            return 12;
        }

        return min($per_page, self::MAX_PER_PAGE);
    }

    /* =====================================================================
     * کلید زمینه
     * =================================================================== */

    /**
     * شناسهٔ یکتای «این آرشیو».
     *
     * کلید کشِ شمارش فست از این ساخته می‌شود، پس باید هرچه کوئری پایه را
     * عوض می‌کند در خود داشته باشد. جاافتادن حتی یکی از این‌ها یعنی
     * شمارش‌های یک دسته روی دستهٔ دیگری سرو می‌شوند — عددهایی که هیچ ربطی
     * به فهرست زیرشان ندارند و هیچ‌وقت هم شبیه باگ به نظر نمی‌رسند.
     */
    public static function context_key(array $base): string {
        $parts = [
            'tax'    => $base['tax_query'] ?? [],
            'search' => $base['s'] ?? '',
            'not_in' => $base['post__not_in'] ?? [],
            'oos'    => self::hides_out_of_stock() ? 1 : 0,
        ];

        return substr(md5(wp_json_encode($parts)), 0, 16);
    }
}
