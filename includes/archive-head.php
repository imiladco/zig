<?php
namespace Zig3d_Widgets;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * نشاندن تصمیم‌های سئو در ‎<head>‎ و فرستادن ‎404‎ برای فیلترِ بی‌نتیجه.
 *
 * چرا اینجا و نه داخل ویجت: ویجت در ‎<body>‎ رندر می‌شود، یعنی وقتی که
 * ‎wp_head‎ خیلی وقت است اجرا شده و هدرهای HTTP هم رفته‌اند. یک تصمیم سئو
 * که در ‎<head>‎ نمی‌نشیند، تصمیم نیست.
 *
 * و در واقع درست‌تر هم هست: «این آدرس ایندکس شود یا نه» خاصیت *صفحه* است،
 * نه خاصیت ویجتی که رویش نشسته. اگر دو ویجت روی یک صفحه باشند، یک
 * ‎canonical‎ بیشتر نداریم.
 *
 * تمام تصمیم‌ها در ‎Seo‎ گرفته می‌شوند و بدون وردپرس تست شده‌اند؛ اینجا فقط
 * سیم‌کشی است — کِی، کجا، و با چه احترامی به افزونه‌های دیگر.
 */
final class Archive_Head {

    /** وضعیت محاسبه‌شدهٔ همین درخواست، یا ‎null‎ اگر اینجا کاری نداریم */
    private static ?array $decision = null;

    public static function boot(): void {
        add_action('template_redirect', [self::class, 'decide'], 1);
    }

    /**
     * وضعیتی که همین درخواست گرفت، یا ‎null‎ اگر اینجا کاری نداشتیم.
     *
     * ویجت از همین می‌خواند تا ‎data-zig-state‎ روی ‎<div>‎ ریشه دقیقاً همان
     * چیزی باشد که کد HTTP را تعیین کرده. اگر ویجت خودش دوباره حساب می‌کرد،
     * دو محاسبهٔ مستقل داشتیم روی یک سؤال — و روزی می‌رسید که سرور ‎404‎
     * بفرستد و DOM با خوش‌رویی ‎ok‎ بگوید.
     */
    public static function page_state(): ?string {
        return self::$decision['state'] ?? null;
    }

    /* =====================================================================
     * تصمیم
     * =================================================================== */

    /**
     * روی ‎template_redirect‎، چون هم کوئری اصلی اجرا شده (پس تعداد نتیجه
     * را می‌دانیم) و هم هنوز چیزی چاپ نشده (پس می‌شود هدر فرستاد).
     */
    public static function decide(): void {
        if (!self::applies()) {
            return;
        }

        $params = self::params();

        if (null === $params) {
            return;
        }

        global $wp_query;

        $decision = Seo::head(
            self::base_url(),
            Query_State::from_request($params, Attributes::all()),
            self::operators(),
            self::policy(),
            isset($wp_query) ? (int) $wp_query->found_posts : null,
            isset($wp_query) ? (int) $wp_query->max_num_pages : 0,
            [] !== self::unknown($params)
        );

        self::$decision = $decision;

        if ($decision['not_found']) {
            self::send_not_found();
        }

        self::attach_output();
    }

    /**
     * فقط روی آرشیو محصولاتِ واقعی.
     *
     * دلیلش بیشتر از احتیاط است: شمارش نتیجه‌ها از کوئری اصلی می‌آید و
     * کوئری اصلی فقط روی همین صفحه‌هاست که فیلترهای ‎filter_*‎ را اعمال
     * می‌کند (ووکامرس خودش این کار را می‌کند، و همین یکی از سودهای
     * پذیرفتن قرارداد آدرسِ اوست). روی یک برگهٔ دلخواه المنتور، آن عدد به
     * چیز دیگری اشاره دارد و ‎404‎ دادن بر اساسش فاجعه است.
     */
    private static function applies(): bool {
        if (is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return false;
        }

        if (!is_main_query() || !function_exists('is_shop')) {
            return false;
        }

        /*
         * پیش‌نمایش المنتور هم کنار می‌رود: ادیتور صفحه را داخل iframe لود
         * می‌کند و یک ‎404‎ آنجا یعنی طراح فکر کند ویجتش خراب است.
         */
        if (class_exists('\Elementor\Plugin') && \Elementor\Plugin::$instance->preview->is_preview_mode()) {
            return false;
        }

        return is_shop() || is_product_taxonomy();
    }

    /**
     * وضعیت، از روی همان پارامترهایی که ووکامرس هم می‌خواند.
     *
     * فهرست سفید از ویژگی‌های شناخته‌شدهٔ فروشگاه می‌آید، نه از طرح فیلترِ
     * دسته: اینجا سؤال «چه چیزی نمایش داده شود» نیست، «آدرس چه چیزی را
     * ادعا می‌کند» است. اگر کسی ‎?filter_x=y‎ بگذارد که در طرح نیست،
     * ووکامرس آن را اعمال می‌کند و ما هم باید همان را ببینیم.
     */
    private static function params(): ?array {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $params = wp_unslash($_GET);

        /*
         * آرایهٔ خالی هم برمی‌گردد، نه ‎null‎.
         *
         * قبلاً فقط وقتی چیزی تصمیم‌گرفتنی بود که پارامتری در آدرس باشد.
         * ولی دستهٔ خالیِ بدونِ هیچ پارامتری هم یک تصمیم دارد: ‎noindex‎.
         * برگرداندن ‎null‎ یعنی آن حالت هیچ‌وقت دیده نشود.
         */
        return is_array($params) ? $params : null;
    }

    /**
     * پارامترهای ‎filter_*‎ که هیچ‌کس ادعایشان را ندارد.
     *
     * وجودشان یعنی آدرس چیزی را می‌گوید که در این فروشگاه معنا ندارد —
     * همان «ترکیب بی‌معنا»یی که گوگل در فهرست ‎404‎ آورده. ولی سه چیز را
     * باید از هم جدا نگه داشت:
     *
     *   • ویژگی‌های فروشگاه: از ‎Attributes::all()‎، نه از طرح فیلترِ دسته.
     *     سؤال «آدرس چه چیزی را ادعا می‌کند» است، نه «سایدبار چه چیزی نشان
     *     می‌دهد»؛ ویژگی‌ای که در سایدبارِ این دسته نیست ولی وجود دارد، هنوز
     *     یک ادعای معتبر است و ووکامرس هم اعمالش می‌کند.
     *
     *   • پارامترهای خودِ ووکامرس: بلاک «وضعیت موجودی» آدرسِ
     *     ‎?filter_stock_status=instock‎ می‌سازد. اگر آن را نشناسیم، به
     *     آدرسی که خودِ ووکامرس ساخته ‎404‎ می‌دهیم.
     *
     *   • هر چیز دیگری که افزونه‌ای اضافه کرده: فیلتر دارد، چون فهرستِ بستهٔ
     *     ما نمی‌تواند از افزونه‌های نصب‌نشده خبر داشته باشد و هزینهٔ اشتباه
     *     اینجا ‎404‎ روی صفحه‌ای سالم است.
     *
     * @return string[]
     */
    private static function unknown(array $params): array {
        $taxonomies = Attributes::all();

        /**
         * تاکسونومی‌هایی که ‎filter_*‎ آن‌ها معتبر شمرده می‌شود.
         *
         * @param string[] $taxonomies
         */
        $taxonomies = (array) apply_filters('zig3d_known_filter_taxonomies', $taxonomies);

        /*
         * ‎stock_status‎ تاکسونومی نیست، ولی ‎unknown_filters()‎ فقط نام
         * پارامتر را می‌سنجد و ‎param_for()‎ پیشوند ‎pa_‎ را می‌اندازد؛ پس
         * دادنش به همین فهرست، دقیقاً ‎filter_stock_status‎ را مجاز می‌کند.
         */
        $taxonomies[] = 'stock_status';

        return Query_State::unknown_filters($params, $taxonomies);
    }

    /* =====================================================================
     * خروجی
     * =================================================================== */

    /**
     * سپردن کار به افزونهٔ سئو، وگرنه چاپ مستقیم.
     *
     * دو ‎canonical‎ روی یک صفحه از نداشتنش بدتر است: موتور جستجو یکی را
     * انتخاب می‌کند و شما نمی‌دانید کدام. پس اگر یوست یا رنک‌مث هست،
     * تصمیم‌مان را از راه فیلترهای خودشان می‌دهیم و چیزی چاپ نمی‌کنیم.
     */
    private static function attach_output(): void {
        if (defined('WPSEO_VERSION')) {
            add_filter('wpseo_canonical', [self::class, 'filter_canonical'], 20);
            add_filter('wpseo_robots_array', [self::class, 'filter_yoast_robots'], 20);

            return;
        }

        if (class_exists('RankMath')) {
            add_filter('rank_math/frontend/canonical', [self::class, 'filter_canonical'], 20);
            add_filter('rank_math/frontend/robots', [self::class, 'filter_rank_math_robots'], 20);

            return;
        }

        add_action('wp_head', [self::class, 'print_tags'], 1);
    }

    public static function print_tags(): void {
        if (null === self::$decision) {
            return;
        }

        printf(
            '<meta name="robots" content="%s">' . "\n",
            esc_attr(self::$decision['robots'])
        );

        /*
         * روی ‎404‎ کانونیکالی در کار نیست — نه مالِ ما و نه مالِ وردپرس.
         * توضیحش در ‎Seo::head()‎ است: اشاره‌دادن به آدرسی دیگر روی صفحه‌ای
         * که وجود ندارد، دو پیام متناقض است.
         */
        if ('' === self::$decision['canonical']) {
            remove_action('wp_head', 'rel_canonical');

            return;
        }

        printf(
            '<link rel="canonical" href="%s">' . "\n",
            esc_url(self::$decision['canonical'])
        );

        /*
         * وردپرس خودش هم یک ‎canonical‎ چاپ می‌کند و آن یکی پارامترهای ما
         * را نمی‌شناسد. برداشتنش بعد از چاپ خودمان انجام می‌شود تا اگر این
         * تابع به هر دلیل اجرا نشد، صفحه بدون کانونیکال نماند.
         */
        remove_action('wp_head', 'rel_canonical');
    }

    /**
     * @param string $canonical
     */
    public static function filter_canonical($canonical): string {
        if (null === self::$decision) {
            return (string) $canonical;
        }

        // رشتهٔ خالی به یوست و رنک‌مث هم می‌گوید چیزی چاپ نکنند
        return self::$decision['canonical'];
    }

    /**
     * یوست آرایه‌ای از دستورها می‌دهد؛ فقط ‎index‎ و ‎follow‎ عوض می‌شوند تا
     * بقیهٔ تنظیماتش (مثل ‎max-image-preview‎) دست‌نخورده بماند.
     *
     * @param array $robots
     */
    public static function filter_yoast_robots($robots): array {
        $robots = (array) $robots;

        if (null === self::$decision) {
            return $robots;
        }

        return array_merge($robots, self::$decision['directives']);
    }

    /**
     * @param array $robots
     */
    public static function filter_rank_math_robots($robots): array {
        $robots = (array) $robots;

        if (null === self::$decision) {
            return $robots;
        }

        unset($robots['index'], $robots['noindex']);

        foreach (self::$decision['directives'] as $directive) {
            $robots[$directive] = $directive;
        }

        return $robots;
    }

    /* =====================================================================
     * ‎404‎
     * =================================================================== */

    /**
     * وضعیت ‎404‎، ولی بدون رفتن به قالب ۴۰۴.
     *
     * گوگل صریح گفته آدرسِ مشکل‌دار خودش ‎404‎ بدهد و به صفحهٔ خطای عمومی
     * فرستاده نشود. اگر ‎$wp_query->set_404()‎ صدا زده شود، وردپرس قالب
     * ۴۰۴ را رندر می‌کند و کاربر سایدبار فیلتر را از دست می‌دهد — یعنی
     * راهی برای برداشتن همان فیلتری که بن‌بست ساخته نمی‌ماند.
     *
     * پس فقط وضعیت عوض می‌شود و صفحه همان‌طور رندر می‌شود، با پیام
     * «چیزی پیدا نشد» و فیلترهای فعالِ قابل‌برداشتن.
     */
    private static function send_not_found(): void {
        if (headers_sent()) {
            return;
        }

        status_header(404);
        nocache_headers();
    }

    /* =====================================================================
     * تنظیمات
     * =================================================================== */

    /**
     * سیاست ایندکس.
     *
     * فیلتر دارد چون این یک تصمیم کسب‌وکاری است نه فنی: فروشگاهی که روی
     * صفحهٔ «برند X» کار سئویی کرده باید بتواند ایندکسش کند.
     */
    private static function policy(): string {
        return (string) apply_filters('zig3d_seo_index_policy', Seo::INDEX_CLEAN);
    }

    /** آدرس پایه، بدون هیچ پارامتری */
    private static function base_url(): string {
        if (is_shop() && function_exists('wc_get_page_permalink')) {
            return (string) wc_get_page_permalink('shop');
        }

        $term = get_queried_object();

        if ($term instanceof \WP_Term) {
            $link = get_term_link($term);

            if (is_string($link)) {
                return $link;
            }
        }

        return home_url(add_query_arg([]));
    }

    /**
     * اپراتور هر گروه، برای بازتولید درستِ ‎query_type_*‎ در کانونیکال.
     */
    private static function operators(): array {
        $term = get_queried_object();

        if (!$term instanceof \WP_Term || Schema_Store::TAXONOMY !== $term->taxonomy) {
            return [];
        }

        $resolved = Schema_Store::resolve((int) $term->term_id, []);

        return Filter_Schema::operators($resolved['facets']);
    }
}
