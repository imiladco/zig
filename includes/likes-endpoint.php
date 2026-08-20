<?php
namespace Zig3d_Widgets;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * نقطهٔ دسترسیِ سوییچِ لایک — یک کلاس، دو در، دقیقاً همان معماریِ
 * ‎Search_Endpoint‎ (REST اصلی، admin-ajax فقط برایِ شکستِ مسیرِ اول).
 *
 * تفاوتِ مهم با ‎Search_Endpoint‎: آنجا فقط می‌خواند، اینجا می‌نویسد
 * (شمارش تغییر می‌کند)، پس دو تصمیمِ اضافه لازم بود:
 *
 *   ۱. **بدونِ نانس، اینجا هم** — به همان دلیلِ مستندشدهٔ ‎Search_Endpoint‎:
 *      این یک نقطهٔ عمومیِ بدونِ احرازِ هویت است؛ نانس روی چنین نقطه‌ای
 *      در برابرِ یک تماس‌گیرندهٔ مصمم محافظتِ واقعی نمی‌دهد (همان نانس را
 *      از سورسِ صفحه برمی‌دارد) و پشتِ کشِ صفحه/CDN همان مشکلِ ۴۰۳ِ
 *      دوره‌ای را می‌سازد. جایِ نانس را کوکیِ ‎SameSite=Lax‎ می‌گیرد:
 *      یک فرمِ جعلیِ سایتِ دیگر نمی‌تواند کوکیِ همین بازدیدکننده را با
 *      خودش بفرستد، پس نهایتاً فقط می‌تواند توکنِ خودش را لایک/آن‌لایک
 *      کند — دقیقاً همان کاری که با یک درخواستِ مستقیم هم می‌توانست بکند.
 *   ۲. **محدودیتِ نرخِ سخت‌گیرانه‌تر** از سرچ — اینجا هر درخواست یک
 *      نوشتنِ دیتابیس است (‎update_post_meta‎)، نه فقط یک کوئری؛ سقفِ
 *      پایین‌تر جلویِ اسکریپتی را می‌گیرد که دکمه را صدها بار در دقیقه
 *      می‌زند.
 */
final class Likes_Endpoint {

    private static bool $booted = false;

    public const ACTION = 'zig3d_like_toggle';

    /** نامِ کوکیِ توکنِ ناشناس — اینجا خوانده و (در صورتِ نبودن) ساخته می‌شود */
    public const COOKIE = 'zig3d_visitor';

    public const RATE_LIMIT_WINDOW = 60;
    private const RATE_LIMIT_MAX    = 20;

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
        register_rest_route('zig3d/v1', '/like/(?P<id>\d+)', [
            'methods'             => 'POST',
            'permission_callback' => '__return_true',
            'args'                => [
                'id' => ['sanitize_callback' => 'absint'],
            ],
            'callback'            => [self::class, 'handle_rest'],
        ]);
    }

    public static function handle_rest(\WP_REST_Request $request) {
        $result = self::process((int) $request['id'], self::client_ip());

        if (is_wp_error($result)) {
            $data     = $result->get_error_data();
            $status   = (int) (is_array($data) ? ($data['status'] ?? 500) : 500);
            $response = new \WP_REST_Response(
                [
                    'code'    => $result->get_error_code(),
                    'message' => $result->get_error_message(),
                    'data'    => ['status' => $status],
                ],
                $status
            );

            foreach ((is_array($data) ? ($data['headers'] ?? []) : []) as $header => $value) {
                $response->header($header, $value);
            }

            return $response;
        }

        return rest_ensure_response($result);
    }

    /* =====================================================================
     * درِ دوم — admin-ajax (فقط برایِ خرابیِ مسیرِ اول)
     * =================================================================== */

    public static function handle_ajax(): void {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- عمداً بدونِ نانس؛ نگاه کنید به داک‌بلاکِ بالایِ کلاس
        $post_id = isset($_POST['post_id']) ? absint(wp_unslash($_POST['post_id'])) : 0;

        $result = self::process($post_id, self::client_ip());

        if (is_wp_error($result)) {
            $data   = $result->get_error_data();
            $status = (int) (is_array($data) ? ($data['status'] ?? 500) : 500);

            wp_send_json_error(['code' => $result->get_error_code()], $status);

            return;
        }

        wp_send_json_success($result);
    }

    /* =====================================================================
     * منطقِ مشترک
     * =================================================================== */

    /**
     * @return array{count:int,liked:bool}|\WP_Error
     */
    public static function process(int $post_id, string $ip) {
        if (self::rate_limited($ip)) {
            return new \WP_Error(
                'zig3d_rate_limited',
                'Too many like requests.',
                [
                    'status'  => 429,
                    'headers' => ['Retry-After' => (string) self::RATE_LIMIT_WINDOW],
                ]
            );
        }

        if (
            $post_id < 1
            || 'post' !== get_post_type($post_id)
            || 'publish' !== get_post_status($post_id)
        ) {
            return new \WP_Error('zig3d_invalid_post', 'Invalid post.', ['status' => 400]);
        }

        return Likes::toggle($post_id, self::visitor_token());
    }

    /**
     * توکنِ ناشناسِ همین بازدیدکننده — اگر کوکی از قبل باشد همان، وگرنه
     * تازه ساخته و همین‌جا ست می‌شود تا همین درخواست هم بلافاصله معتبرش
     * را استفاده کند.
     *
     * فرمتِ ‎[a-f0-9]{32,64}‎ روی کوکیِ ورودی چک می‌شود — نه برایِ امنیت
     * (توکن فقط یک شناسه است، نه رمز)، بلکه تا یک کوکیِ دستکاری‌شده یا
     * خیلی بلند مستقیم وارد ‎hash()‎ نشود.
     */
    private static function visitor_token(): string {
        $existing = isset($_COOKIE[self::COOKIE]) ? (string) $_COOKIE[self::COOKIE] : '';

        if ('' !== $existing && preg_match('/^[a-f0-9]{32,64}$/', $existing)) {
            return $existing;
        }

        $token = function_exists('wp_generate_password')
            ? wp_generate_password(32, false, false)
            : bin2hex(random_bytes(16));

        if (!headers_sent()) {
            setcookie(self::COOKIE, $token, [
                'expires'  => time() + 3 * (defined('YEAR_IN_SECONDS') ? YEAR_IN_SECONDS : 31536000),
                'path'     => '/',
                'secure'   => function_exists('is_ssl') && is_ssl(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }

        $_COOKIE[self::COOKIE] = $token;

        return $token;
    }

    private static function rate_limited(string $ip): bool {
        if ('' === $ip) {
            return false;
        }

        $key   = 'zig3d_like_rl_' . md5($ip);
        $count = (int) get_transient($key);

        if ($count >= self::RATE_LIMIT_MAX) {
            return true;
        }

        set_transient($key, $count + 1, self::RATE_LIMIT_WINDOW);

        return false;
    }

    /** همان منطقِ ‎Search_Endpoint::client_ip()‎ — تکرارِ عمدی، نه اشتراکِ کلاس‌ها */
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
