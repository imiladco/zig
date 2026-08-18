<?php
namespace Zig3d_Widgets;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * نقطهٔ دسترسیِ سرچ — یک کلاس، دو در.
 *
 * ‎Archive_Endpoint‎ فقط از راهِ ‎admin-ajax.php‎ می‌رود، چون درخواستش
 * سنگین و کم‌تکرار است (یک کلیکِ فیلتر). این‌جا برعکس است: هر کلیدی که
 * کاربر می‌زند یک درخواستِ سبک می‌فرستد، و ‎admin-ajax.php‎ برایِ همان
 * تکرار قیمتش سنگین‌تر است — کلِ محیطِ ادمین را برایِ هر کاراکتر بالا
 * می‌آورد. پس مسیرِ اصلی REST است (‎permission_callback‎ ی خودش، معنایِ
 * ‎GET‎، قابلِ کش‌شدن پشتِ CDN) و ‎admin-ajax.php‎ فقط دری دومی است برای
 * زمانی که مسیرِ اول به هر دلیلی (مسدودشدنِ ‎/wp-json/‎ از راهِ امنیتی،
 * افزونهٔ دیگری که REST را خاموش کرده) در دسترس نیست. هر دو یک منطقِ
 * واحد را صدا می‌زنند — ‎process()‎ — تا رفتار در دو در یکی بماند.
 *
 * چیزی که اینجا هم مثلِ ‎Archive_Endpoint‎ عمداً تکرار می‌شود، مهم‌ترین
 * تصمیمِ امنیتیِ این فایل است:
 *
 *     **تنظیماتِ ویجت از دیتابیس خوانده می‌شود، نه از درخواست.**
 *
 * از کلاینت فقط ‎q‎ (عبارتِ جست‌وجو) و ‎post_id‎/‎widget_id‎ (این‌که کدام
 * نمونهٔ ویجت) می‌آید؛ سقفِ نتیجه، فیلدهایِ جست‌وجو، منبعِ دسته/برند،
 * مترادف‌ها — همه از همان سندی خوانده می‌شوند که مدیر در المنتور ساخته.
 *
 * و برخلافِ ‎Archive_Endpoint‎، اینجا **نانس نیست** — نه فراموشی، یک
 * تصمیم:
 *
 *   ۱. این نقطه فقط می‌خواند. هیچ نوشتنی در کار نیست که نانس معمولاً
 *      جلویِ CSRF اش را می‌گیرد.
 *   ۲. خودِ داکِ ‎Archive_Endpoint‎ هم اعتراف می‌کند که نانس روی یک نقطهٔ
 *      عمومیِ بدونِ احرازِ هویت، در برابرِ یک تماس‌گیرندهٔ مصمم محافظتِ
 *      واقعی نمی‌دهد — هرکسی که بخواهد، همان نانس را از سورسِ صفحه
 *      برمی‌دارد.
 *   ۳. و اینجا نانس یک باگِ واقعی هم می‌ساخت: صفحه‌ای که پشتِ کشِ صفحه یا
 *      CDN نشسته، نانسِ همان لحظهٔ ساختِ کش را در HTML دارد. بعدِ عمرِ
 *      نانس (پیش‌فرضِ وردپرس ۲۴ ساعت)، هر کاربرِ واقعی — نه مهاجم — با
 *      اولین کاراکتری که تایپ می‌کند ‎403‎ می‌گیرد، تا کشِ صفحه خودش را
 *      تازه کند. برایِ نقطه‌ای که در هر تایپ صدا زده می‌شود، این یعنی
 *      شکستِ مکرر برایِ کاربرانِ عادی، نه یک سپر برایِ مهاجم.
 *
 * جایِ نانس را این‌ها می‌گیرند: ‎permission_callback => '__return_true'‎ی
 * صریح (نه پنهان)، سقفِ طولِ ورودی، محدودیتِ نرخِ نرم، و همان قاعدهٔ
 * «تنظیمات از دیتابیس» که بالا آمد.
 */
final class Search_Endpoint {

    private static bool $booted = false;

    public const ACTION = 'zig3d_search';

    /** بیشترین طولِ عبارتِ جست‌وجو — یک باکسِ سرچ، نه یک فرمِ فیلتر */
    public const MAX_QUERY = 80;

    /**
     * محدودیتِ نرخ — نرم و محافظه‌کارانه.
     *
     * این یک مرزِ امنیتی نیست؛ فقط جلویِ اسکریپتی را می‌گیرد که هزار
     * کاراکتر را در یک ثانیه به این نقطه شلیک می‌کند. عددش عمداً سخاوتمند
     * است — کاربرِ واقعی که تند تایپ می‌کند نباید هیچ‌وقت به آن برسد.
     */
    private const RATE_LIMIT_WINDOW = 60;
    private const RATE_LIMIT_MAX    = 40;

    public static function boot(): void {
        if (self::$booted) {
            return;
        }

        self::$booted = true;

        add_action('rest_api_init', [self::class, 'register_rest_route']);
        add_action('wp_ajax_' . self::ACTION, [self::class, 'handle_ajax']);
        add_action('wp_ajax_nopriv_' . self::ACTION, [self::class, 'handle_ajax']);
    }

    /* =====================================================================
     * درِ اول — REST
     * =================================================================== */

    public static function register_rest_route(): void {
        register_rest_route('zig3d/v1', '/search', [
            'methods'             => 'GET',
            'permission_callback' => '__return_true',
            'callback'            => [self::class, 'handle_rest'],
        ]);
    }

    public static function handle_rest(\WP_REST_Request $request) {
        $result = self::process([
            'q'         => (string) $request->get_param('q'),
            'post_id'   => (int) $request->get_param('post_id'),
            'widget_id' => (string) $request->get_param('widget_id'),
        ], self::client_ip());

        if (is_wp_error($result)) {
            return $result;
        }

        $response = rest_ensure_response($result);

        /*
         * فرستاده می‌شود ولی تکیه‌گاه نیست — معماری باید بدونِ هیچ CDNی هم
         * درست کار کند (کشِ واقعیِ تازگی همان کشِ نسخه‌دارِ سمتِ سرور در
         * ‎Search_Query‎ است). این فقط یک بهینه‌سازیِ اختیاری برایِ جایی
         * است که CDN واقعاً پشتِ سر هست.
         */
        $response->header('Cache-Control', 'public, max-age=30');

        return $response;
    }

    /* =====================================================================
     * درِ دوم — admin-ajax (فقط برایِ خرابیِ مسیرِ اول)
     * =================================================================== */

    public static function handle_ajax(): void {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- عمداً بدونِ نانس؛ نگاه کنید به داک‌بلاکِ بالایِ کلاس
        $params = wp_unslash($_REQUEST);

        $result = self::process([
            'q'         => (string) ($params['q'] ?? ''),
            'post_id'   => (int) ($params['post_id'] ?? 0),
            'widget_id' => (string) ($params['widget_id'] ?? ''),
        ], self::client_ip());

        if (is_wp_error($result)) {
            $data = $result->get_error_data();

            wp_send_json_error(['code' => $result->get_error_code()], (int) (is_array($data) ? ($data['status'] ?? 500) : 500));

            return;
        }

        wp_send_json_success($result);
    }

    /* =====================================================================
     * منطقِ مشترک
     * =================================================================== */

    /**
     * @param array{q:string,post_id:int,widget_id:string} $params
     * @return array{query:string,results:array,has_more:bool}|\WP_Error
     */
    public static function process(array $params, string $ip) {
        if (self::rate_limited($ip)) {
            return new \WP_Error('zig3d_rate_limited', 'Too many search requests.', ['status' => 429]);
        }

        $q = trim((string) ($params['q'] ?? ''));

        if ('' === $q || mb_strlen($q) > self::MAX_QUERY) {
            return new \WP_Error('zig3d_invalid_query', 'Invalid search query.', ['status' => 400]);
        }

        $widget = self::widget((int) ($params['post_id'] ?? 0), (string) ($params['widget_id'] ?? ''));

        if (null === $widget) {
            return new \WP_Error('zig3d_invalid_widget', 'Unknown search widget instance.', ['status' => 400]);
        }

        $settings = $widget->get_settings_for_display();

        $match   = Search_Query::match($q, $widget->search_args($settings));
        $results = $match['ids'] ? Search_Query::hydrate($match['ids'], $widget->hydrate_args($settings)) : [];

        return [
            'query'    => $q,
            'results'  => $results,
            'has_more' => $match['has_more'],
        ];
    }

    /* =====================================================================
     * ویجت — همان الگویِ ‎Archive_Endpoint::widget()‎
     * =================================================================== */

    private static function widget(int $post_id, string $widget_id): ?\Elementor\Widget_Base {
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

        return $element instanceof Widgets\Search ? $element : null;
    }

    /* =====================================================================
     * محدودیتِ نرخ
     * =================================================================== */

    private static function rate_limited(string $ip): bool {
        if ('' === $ip) {
            // نمی‌دانیم کیست؛ محدودنکردن بهتر از مسدودکردنِ همه پشتِ یک
            // پراکسیِ ناشناخته است.
            return false;
        }

        $key   = 'zig3d_search_rl_' . md5($ip);
        $count = (int) get_transient($key);

        if ($count >= self::RATE_LIMIT_MAX) {
            return true;
        }

        set_transient($key, $count + 1, self::RATE_LIMIT_WINDOW);

        return false;
    }

    /**
     * آدرسِ کلاینت — با فرضِ عدمِ اعتماد به پیش‌فرض.
     *
     * ‎X-Forwarded-For‎ را خودِ کلاینت هم می‌تواند بفرستد؛ بدونِ یک
     * پراکسیِ واسطِ قابلِ‌اعتماد که مقدارِ قبلی را دور بریزد و خودش
     * بنویسدش، اعتمادکورکورانه به این هدر یعنی محدودیتِ نرخ با جعلِ یک
     * هدر دور زده می‌شود — امنیتش دقیقاً برعکس می‌شود. پس فقط با فعال‌سازیِ
     * صریح (یک فیلتر، نه پیش‌فرض) اعتماد می‌شود.
     */
    private static function client_ip(): string {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';

        $trust_forwarded = (bool) apply_filters('zig3d_search_trust_forwarded_for', false);

        if ($trust_forwarded && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $chain = explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip    = trim($chain[0]);
        }

        return $ip;
    }
}
