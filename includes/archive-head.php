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

    /**
     * آیا نگهبان چیزی را برید؟
     *
     * لازم است چون نگهبان ‎$_GET‎ را *عوض* می‌کند: بعد از آن،
     * ‎oversized_filters()‎ روی ورودیِ بریده‌شده چیزی پیدا نمی‌کند و آدرسی
     * که باید ‎404‎ می‌گرفت، ‎200‎ می‌گرفت. یعنی خودِ محافظت، تشخیص را
     * پاک می‌کرد.
     */
    private static bool $capped = false;

    public static function boot(): void {
        /*
         * نگهبان روی ‎request‎ می‌نشیند و نه ‎template_redirect‎، و این
         * تفاوت کل نکته است.
         *
         * ‎WC_Query::pre_get_posts()‎ روی ‎pre_get_posts‎ (اولویت پیش‌فرض)
         * اجرا می‌شود و همان‌جا ‎tax_query‎ ناوبری لایه‌ای را می‌سازد.
         * ‎template_redirect‎ *بعد* از اجرای کوئری اصلی است؛ یعنی هر
         * تصمیمی آنجا، بعد از پرداختِ هزینه گرفته می‌شود.
         *
         * ‎request‎ داخل ‎WP::parse_request()‎ صدا زده می‌شود — پیش از
         * ‎WP::query_posts()‎ و پیش از اولین باری که ووکامرس ‎$_GET‎ را
         * می‌خواند (نتیجه‌اش را هم در یک ثابت ایستا کش می‌کند، پس بعدش
         * دیگر دیر است).
         */
        add_filter('request', [self::class, 'guard'], 1);

        /*
         * اولویت ۲۰، یعنی *بعد* از ‎WC_Query::pre_get_posts()‎ که روی
         * پیش‌فرض می‌نشیند. آنجا ووکامرس ‎tax_query‎ خودش را ساخته و ما
         * رویش اضافه می‌کنیم، نه به‌جایش.
         */
        add_action('pre_get_posts', [self::class, 'filter_main_query'], 20);

        add_action('template_redirect', [self::class, 'decide'], 1);
    }

    /**
     * قیدهایی که ووکامرس نمی‌شناسد، روی کوئری *اصلی*.
     *
     * ویژگی‌ها (‎pa_*‎) را خودِ ووکامرس از ‎$_GET‎ می‌خواند و اعمال می‌کند.
     * ولی برند تاکسونومی دیگری است و ووکامرس هیچ ناوبری لایه‌ای برایش
     * ندارد — یعنی اگر فقط ویجت اعمالش کند، سند و ویجت دو چیز متفاوت
     * می‌شوند: گرید یازده محصول نشان می‌دهد و شمارشی که ‎Archive_Head‎
     * تصمیم ‎404‎ و ‎canonical‎ را از آن می‌گیرد، سی‌ویک.
     *
     * همان درسِ صفحه‌بندی، این بار روی فیلتر: هر قیدی که کاربر می‌بیند
     * باید روی هر دو کوئری بنشیند.
     *
     * @param \WP_Query $query
     */
    public static function filter_main_query($query): void {
        if (!$query instanceof \WP_Query || !$query->is_main_query() || $query->is_singular() || is_admin()) {
            return;
        }

        if (!function_exists('is_shop') || !(is_shop() || is_product_taxonomy())) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $params = is_array($_GET) ? wp_unslash($_GET) : [];

        $extra = [];

        foreach (Archive_Query::honored(self::facets()) as $taxonomy => $operator) {
            /*
             * ویژگی‌ها رد می‌شوند: ووکامرس همین حالا اعمالشان کرده و
             * افزودن دوبارهٔ همان قید، فقط یک ‎JOIN‎ تکراری است.
             */
            if (0 === strpos($taxonomy, Query_State::ATTRIBUTE_PREFIX)) {
                continue;
            }

            $param = Query_State::param_for($taxonomy);

            if (!isset($params[$param]) || !is_string($params[$param])) {
                continue;
            }

            $terms = array_values(array_filter(array_map('sanitize_title', explode(',', $params[$param]))));

            if (!$terms || !taxonomy_exists($taxonomy)) {
                continue;
            }

            $extra[] = [
                'taxonomy' => $taxonomy,
                'field'    => 'slug',
                'terms'    => array_slice(array_unique($terms), 0, Query_State::MAX_TERMS),
                'operator' => Facets::OP_AND === $operator ? 'AND' : 'IN',
            ];
        }

        if (!$extra) {
            return;
        }

        $existing = (array) $query->get('tax_query');

        /*
         * گروه‌بندی، نه ادغام تخت: اگر بندهای ووکامرس ‎relation‎ خودشان را
         * داشته باشند، تخت‌کردن آن ‎relation‎ را روی قیدهای ما هم اعمال
         * می‌کرد — یعنی یک ‎OR‎ داخلی می‌توانست قید برند را اختیاری کند.
         */
        $query->set('tax_query', $existing
            ? ['relation' => 'AND', $existing, array_merge(['relation' => 'AND'], $extra)]
            : array_merge(['relation' => 'AND'], $extra));
    }

    /* =====================================================================
     * نگهبان
     * =================================================================== */

    /**
     * بریدن ورودی، پیش از آنکه به کوئری تبدیل شود.
     *
     * ‎$_GET‎ عوض می‌شود و این تنها راه است: ‎WC_Query‎ مستقیم از ‎$_GET‎
     * می‌خواند و هیچ فیلتری روی ویژگی‌های انتخاب‌شده‌اش ندارد که بشود از
     * بیرون محدودش کرد. تنها گزینهٔ دیگر این بود که بگذاریم کوئری سنگین
     * اجرا شود و بعد ‎404‎ بدهیم — که یعنی سقف، سرور را محافظت نکرده.
     *
     * ‎paged‎ از دو راه می‌آید: ‎?paged=…‎ که در ‎$_GET‎ است، و
     * ‎/page/…/‎ که از بازنویسی می‌آید و فقط در متغیرهای کوئری. هر دو
     * بریده می‌شوند، وگرنه یکی از دو راه باز می‌ماند.
     *
     * @param array $vars متغیرهای کوئری وردپرس
     */
    public static function guard($vars) {
        if (is_admin() || wp_doing_ajax()) {
            return $vars;
        }

        /*
         * A named post-type request is a singular candidate. Archive query
         * controls must not cap or rewrite its request variables before
         * WordPress has had a chance to resolve that object.
         */
        if (is_array($vars) && isset($vars['name']) && '' !== (string) $vars['name']) {
            return $vars;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $params = $_GET;

        if (is_array($params)) {
            $capped = Query_State::cap_params($params);

            if ($capped !== $params) {
                self::$capped = true;

                // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.VIP.SuperGlobalInputUsage
                $_GET = $capped;
            }
        }

        if (is_array($vars) && isset($vars[Query_State::PAGE_PARAM])
            && (int) $vars[Query_State::PAGE_PARAM] > Query_State::MAX_PAGE
        ) {
            self::$capped = true;

            $vars[Query_State::PAGE_PARAM] = Query_State::MAX_PAGE;
        }

        return $vars;
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
        // Archive SEO/status decisions must never run for a resolved single.
        if (is_singular()) {
            return;
        }

        if (!self::applies()) {
            return;
        }

        $params = self::params();

        if (null === $params) {
            return;
        }

        $state   = Query_State::from_request($params, Archive_Query::honored_taxonomies(self::facets()));
        $invalid = [] !== self::invalid($params);

        /*
         * ترتیب مهم است و این را نصب واقعی نشان داد.
         *
         * قبلاً ریدایرکتِ صریح‌کردن اپراتور اول اجرا می‌شد. روی آدرسی با
         * هفتاد ترم، نتیجه‌اش این بود که همان هفتاد ترم دوباره در مقصد
         * ریدایرکت بنشینند — یعنی یک رفت‌وبرگشت اضافه، برای رسیدن به
         * آدرسی که بعدش ‎404‎ می‌گرفت.
         *
         * آدرسی که قرار است «وجود ندارد» اعلام شود، کانونیکال‌کردن ندارد.
         */
        if (!$invalid && self::normalize($params, $state)) {
            return;
        }

        global $wp_query;

        $decision = Seo::head(
            self::base_url(),
            $state,
            self::operators(),
            self::policy(),
            isset($wp_query) ? (int) $wp_query->found_posts : null,
            isset($wp_query) ? (int) $wp_query->max_num_pages : 0,
            $invalid
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

        if (is_singular() || !is_main_query() || !function_exists('is_shop')) {
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
     * آدرس‌هایی که گوگل برایشان ‎404‎ خواسته: ادعای بی‌صاحب و فیلتر تکراری.
     *
     * فهرست مجاز از ‎Archive_Query::honored_taxonomies()‎ می‌آید و این
     * انتخاب، خودش نگهدارندهٔ یک قاعده است: چیزی «شناخته‌شده» شمرده می‌شود
     * که واقعاً روی کوئری اثر بگذارد. اگر اینجا فهرست دومی می‌ساختیم، دو
     * تعریف از یک کلمه داشتیم و روزی یکی‌شان جلو می‌افتاد — آن روز یا
     * صفحهٔ سالم ‎404‎ می‌گرفت، یا آدرسِ تکراری ‎200‎.
     *
     * صفحه‌بندیِ ناموجود — سومین مورد فهرست گوگل — اینجا نیست چون از
     * شمارش می‌آید نه از شکل آدرس؛ ‎Seo::state()‎ آن را جدا می‌سنجد.
     *
     * @return string[]
     */
    private static function invalid(array $params): array {
        $taxonomies = Archive_Query::honored_taxonomies(self::facets());

        $invalid = array_merge(
            Query_State::unknown_filters($params, $taxonomies),
            Query_State::duplicate_filters($params, self::query_string()),
            Query_State::oversized_filters($params, $taxonomies)
        );

        /*
         * چیزی که نگهبان بریده، دیگر در ‎$params‎ نیست — پس
         * ‎oversized_filters()‎ نمی‌بیندش. بدون این خط، محافظت از سرور
         * تشخیصِ ‎404‎ را پاک می‌کرد: آدرسِ سی‌گروهی سبک اجرا می‌شد و بعد
         * ‎200‎ می‌گرفت، یعنی همان آدرسِ تکراری که قرار بود نماند.
         *
         * ‎query_string()‎ رشتهٔ خام را از ‎$_SERVER‎ می‌خواند و دست‌نخورده
         * مانده، ولی به آن تکیه نمی‌کنیم: روی محیط‌هایی که در دسترس نیست،
         * تشخیص بی‌صدا از بین می‌رفت.
         */
        if (self::$capped) {
            $invalid[] = Query_State::FILTER_PREFIX . '*';
        }

        return $invalid;
    }

    /**
     * صریح‌کردن اپراتور در آدرس، اگر مبهم مانده باشد.
     *
     * چرا اصلاً لازم است: ‎?filter_color=red,blue‎ بدون ‎query_type_color‎
     * برای ووکامرس یعنی ‎AND‎ و برای طرحِ ما یعنی ‎OR‎. روی آرشیو واقعی هر
     * دو اجرا می‌شوند — ووکامرس کوئری اصلی را، ما کوئری ویجت را — و
     * ‎Archive_Head‎ تصمیم ‎404‎ را از شمارشِ اولی می‌گیرد در حالی که کاربر
     * نتیجهٔ دومی را می‌بیند.
     *
     * به‌جای اینکه یکی تسلیم دیگری شود، ابهام برداشته می‌شود.
     *
     * ‎302‎ و نه ‎301‎: مدیر می‌تواند فردا همان گروه را از ‎OR‎ به ‎AND‎
     * ببرد. مرورگرها ‎301‎ را سرسختانه کش می‌کنند و آن‌وقت بازدیدکننده‌ای
     * که یک بار این مسیر را رفته، تا پاک‌کردن کش مرورگرش به آدرسِ قدیمی
     * فرستاده می‌شود. این صفحه‌ها ‎noindex‎ هستند، پس ‎301‎ سودی هم ندارد.
     *
     * حلقه نمی‌سازد چون مقصد، خودش دیگر چیزی برای اصلاح ندارد: بعد از
     * نشستنِ ‎query_type_x=or‎، محاسبهٔ بعدی ‎set‎ و ‎remove‎ خالی می‌دهد.
     *
     * @return bool آیا ریدایرکت شد
     */
    private static function normalize(array $params, Query_State $state): bool {
        if (headers_sent()) {
            return false;
        }

        $fixes = $state->query_type_fixes($params, Archive_Query::honored(self::facets()));

        if (!$fixes['set'] && !$fixes['remove']) {
            return false;
        }

        /*
         * آدرسِ فعلی، نه آدرسِ بازساخته. ‎utm_*‎ و هر چیز دیگری که ما
         * نمی‌شناسیم باید سر جایش بماند: ریدایرکتی که پارامتر کمپین را
         * می‌اندازد، گزارش‌های تبلیغات را می‌شکند و کسی هم به این کد شک
         * نمی‌کند.
         */
        $url = home_url(add_query_arg([]));

        if ($fixes['remove']) {
            $url = remove_query_arg($fixes['remove'], $url);
        }

        if ($fixes['set']) {
            $url = add_query_arg($fixes['set'], $url);
        }

        /*
         * ‎wp_redirect()‎ اگر فیلتری آدرس را خالی کند ‎false‎ می‌دهد و
         * هیچ هدری نمی‌فرستد؛ ‎exit‎ در آن حالت یعنی صفحهٔ سفید.
         */
        if (!wp_safe_redirect($url, 302)) {
            return false;
        }

        exit;
    }

    /**
     * رشتهٔ خام پرس‌وجو.
     *
     * ‎$_GET‎ کافی نیست: ‎?filter_brand=a&filter_brand=b‎ در PHP فقط ‎b‎
     * می‌شود و ‎a‎ بی‌صدا ناپدید می‌شود. تنها جایی که آن تکرار هنوز دیده
     * می‌شود همین‌جاست. اگر در دسترس نباشد، رشتهٔ خالی برمی‌گردد و سنجش
     * تکرارِ کلید بی‌سروصدا کنار می‌رود — که از ‎404‎ دادن به صفحهٔ سالم
     * بهتر است.
     */
    private static function query_string(): string {
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $raw = $_SERVER['QUERY_STRING'] ?? '';

        return is_string($raw) ? $raw : '';
    }

    /** گروه‌های فیلترِ دستهٔ جاری، اگر روی دسته باشیم */
    private static function facets(): array {
        $term = get_queried_object();

        if (!$term instanceof \WP_Term || Schema_Store::TAXONOMY !== $term->taxonomy) {
            return [];
        }

        return Schema_Store::for_term((int) $term->term_id);
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
        return Filter_Schema::operators(self::facets());
    }
}
