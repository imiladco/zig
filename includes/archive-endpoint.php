<?php
namespace Zig3d_Widgets;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * نقطهٔ آژاکسِ آرشیو.
 *
 * قرارداد پاسخ در ‎Archive_Response‎ نوشته شده و اینجا فقط اجرا می‌شود.
 * ولی یک تصمیم مالِ همین‌جاست و مهم‌ترین تصمیم امنیتیِ این فایل است:
 *
 *     **تنظیمات ویجت از دیتابیس خوانده می‌شود، نه از درخواست.**
 *
 * راه ساده‌تر این بود که کلاینت تنظیماتش را بفرستد و ما رندر کنیم. آن‌وقت
 * هرکسی می‌توانست ‎per_page=100000‎ یا دستهٔ دلخواه یا حتی شناسهٔ قالبِ
 * دیگری بفرستد؛ یعنی یک ابزار پیمایشِ محتوا و یک راه ساده برای از پا
 * درآوردن سرور، از یک آدرس بدون احراز هویت. پس فقط ‎post_id‎ و
 * ‎widget_id‎ می‌آیند و بقیه از همان سندی خوانده می‌شود که مدیر ساخته.
 *
 * چیزی که از کلاینت *باید* بیاید، وضعیت است: کدام فیلتر، چه ترتیبی، صفحهٔ
 * چندم. و آن هم به شکل رشتهٔ پرس‌وجوی همان آدرس می‌آید، نه چند فیلد جدا —
 * تا دقیقاً همان چیزی پارس شود که رندر سرور هم پارس می‌کند. دو پارسر برای
 * یک قرارداد، یعنی روزی که یکی‌شان ‎query_type‎ را بفهمد و دیگری نه.
 */
final class Archive_Endpoint {

    public const ACTION = 'zig3d_archive';

    /** نام اکشن نانس؛ عمداً همان اکشن است تا جایی برای اشتباه تایپی نماند */
    public const NONCE = 'zig3d_archive';

    public static function boot(): void {
        add_action('wp_ajax_' . self::ACTION, [self::class, 'handle']);
        add_action('wp_ajax_nopriv_' . self::ACTION, [self::class, 'handle']);
    }

    public static function url(): string {
        return admin_url('admin-ajax.php');
    }

    public static function nonce(): string {
        return wp_create_nonce(self::NONCE);
    }

    /* =====================================================================
     * درخواست
     * =================================================================== */

    public static function handle(): void {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $post = wp_unslash($_POST);

        if (!is_array($post)) {
            self::fail(Archive_Response::FAIL_REQUEST);
        }

        if (!wp_verify_nonce((string) ($post['nonce'] ?? ''), self::NONCE)) {
            /*
             * نانس وردپرس عمر دارد (پیش‌فرض ۲۴ ساعت). صفحه‌ای که یک شب باز
             * مانده، فردا صبح با هر کلیک ۴۰۳ می‌گیرد — و این دقیقاً همان
             * چیزی است که دکمهٔ «تلاش مجدد» برایش هست: کلاینت نانس تازه
             * می‌گیرد و دوباره می‌فرستد. پس ۴۰۳، نه یک پاکتِ خالی.
             */
            self::fail(Archive_Response::FAIL_NONCE);
        }

        $widget = self::widget((int) ($post['post_id'] ?? 0), (string) ($post['widget_id'] ?? ''));

        if (null === $widget) {
            self::fail(Archive_Response::FAIL_REQUEST);
        }

        self::respond(
            $widget,
            self::params((string) ($post['query'] ?? '')),
            (int) ($post['term_id'] ?? 0)
        );
    }

    /**
     * وضعیت، از رشتهٔ پرس‌وجوی آدرسِ مقصد.
     *
     * ‎parse_str()‎ همان کاری را می‌کند که PHP روی ‎$_GET‎ می‌کند — از جمله
     * نگه‌داشتن فقط آخرین مقدارِ کلید تکراری. یعنی همان ورودی، همان
     * برداشت، همان وضعیت.
     */
    private static function params(string $query): array {
        $params = [];

        parse_str(ltrim($query, '?'), $params);

        return is_array($params) ? $params : [];
    }

    /* =====================================================================
     * ویجت
     * =================================================================== */

    /**
     * نمونهٔ ویجت، ساخته‌شده از دادهٔ ذخیره‌شدهٔ همان صفحه.
     *
     * سه بار «نه» گفته می‌شود و هر سه لازم است: سندی که وجود ندارد، سندی
     * که منتشر نشده (پس بازدیدکنندهٔ عادی حق دیدنش را ندارد)، و عنصری که
     * ویجت *ما* نیست — وگرنه این آدرس تبدیل می‌شد به راهی برای رندرکردن
     * هر عنصر هر صفحه‌ای، از جمله پیش‌نویس‌ها.
     */
    private static function widget(int $post_id, string $widget_id): ?Widgets\Product_Archive {
        if ($post_id < 1 || '' === $widget_id || !class_exists('\Elementor\Plugin')) {
            return null;
        }

        if ('publish' !== get_post_status($post_id)) {
            return null;
        }

        $document = \Elementor\Plugin::$instance->documents->get($post_id);

        if (!$document) {
            return null;
        }

        $data = \Elementor\Utils::find_element_recursive($document->get_elements_data(), $widget_id);

        if (!is_array($data) || 'widget' !== ($data['elType'] ?? '')) {
            return null;
        }

        $element = \Elementor\Plugin::$instance->elements_manager->create_element_instance($data);

        /*
         * ‎instanceof‎ و نه مقایسهٔ نام: نام را داده‌ای که از دیتابیس آمده
         * تعیین می‌کند و اگر روزی عنصر دیگری همان نام را داشت، این تابع
         * چیزی برمی‌گرداند که متدهای ما را ندارد و خطای مرگ‌بار می‌دهد.
         */
        return $element instanceof Widgets\Product_Archive ? $element : null;
    }

    /* =====================================================================
     * پاسخ
     * =================================================================== */

    private static function respond(Widgets\Product_Archive $widget, array $params, int $term_id): void {
        $settings = $widget->get_settings_for_display();
        $context  = $widget->context($settings, $params, $term_id);

        /** @var \WP_Query $query */
        $query = $context['query'];

        $fragments = [
            'grid'       => $widget->fragment('grid', $context),
            'pagination' => $widget->fragment('pagination', $context),
        ];

        /*
         * سایدبار و شمارش فقط وقتی می‌روند که واقعاً رندر می‌شوند.
         *
         * کلیدِ نبوده در قرارداد یعنی «دست نزن»، و همین است که یک کلیکِ
         * صفحه‌بندی را از جایگزین‌کردنِ بی‌دلیلِ سایدبار نجات می‌دهد —
         * جایگزینی‌ای که فوکوس را هم با خودش می‌برد.
         */
        if ($widget->has_sidebar($settings, $context['facets'])) {
            $fragments['facets'] = $widget->fragment('facets', $context);
        }

        if ('yes' === ($settings['count_on'] ?? '')) {
            $fragments['count'] = $widget->fragment('count', $context);
        }

        wp_reset_postdata();

        wp_send_json_success(Archive_Response::envelope(
            $context['page_state'],
            [
                'page'  => $context['state']->page(),
                'pages' => (int) $query->max_num_pages,
                'found' => (int) $query->found_posts,
                'url'   => Seo::url(self::base_url(), $context['state'], $context['operators']),
            ],
            $fragments
        ));
    }

    /**
     * آدرس پایه‌ای که ‎history‎ رویش ساخته می‌شود.
     *
     * از ارجاع‌دهنده می‌آید و نه از تنظیمات: کاربر ممکن است روی هر صفحه‌ای
     * باشد که این ویجت رویش نشسته. ‎wp_validate_redirect‎ نگهبانش است تا
     * یک ارجاع‌دهندهٔ جعلی، آدرسِ سایتِ دیگری را در نوار آدرس کاربر ننشاند.
     */
    private static function base_url(): string {
        $referer = wp_get_referer();

        if (!is_string($referer) || '' === $referer) {
            return home_url('/');
        }

        $referer = wp_validate_redirect($referer, home_url('/'));

        $parts = wp_parse_url($referer);

        /*
         * فقط طرح و میزبان و مسیر. رشتهٔ پرس‌وجوی ارجاع‌دهنده عمداً دور
         * ریخته می‌شود: آن وضعیتِ *قبلی* است و اگر بماند، آدرسِ history
         * دو نسخه از هر پارامتر می‌گیرد.
         */
        return ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '') . ($parts['path'] ?? '/');
    }

    /* =====================================================================
     * شکست
     * =================================================================== */

    /**
     * شکستِ فنی — بدون پاکت.
     *
     * ‎wp_send_json_error()‎ به‌تنهایی ‎200‎ می‌دهد و آن دقیقاً همان چیزی
     * است که قرارداد برای جلوگیری‌اش نوشته شد: کلاینت ‎response.ok‎ را
     * می‌بیند، دنبال ‎state‎ می‌گردد، پیدا نمی‌کند، و بی‌صدا هیچ کاری
     * نمی‌کند. پس کد HTTP صریح می‌آید.
     */
    private static function fail(string $code): void {
        wp_send_json_error(['code' => $code], Archive_Response::failure_status($code));
    }
}
