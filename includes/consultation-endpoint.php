<?php
namespace Zig3d_Widgets;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * نقطهٔ آژاکسِ ثبتِ درخواستِ مشاوره.
 *
 * بر خلافِ ‎Likes_Endpoint‎/‎Search_Endpoint‎ (که عمداً بدونِ نانس‌اند، چون
 * پشتِ کشِ صفحه می‌مانند و نانس آنجا در برابرِ یک تماس‌گیرندهٔ مصمم
 * محافظتِ واقعی نمی‌دهد)، این یکی یک کنشِ واقعیِ کاربر است: کسی روی
 * دکمه کلیک کرده، مودال همان لحظه باز شده، پس نانسِ همان لحظه هم تازه
 * است. همان الگویِ ‎Archive_Endpoint‎: نانس + شکستِ صریح با کدِ HTTP، نه
 * پاکتِ ‎200‎ با ‎success:false‎.
 *
 * دو لایهٔ ضدِاسپمِ اضافی که ‎Archive_Endpoint‎ نیازی بهشان نداشت چون فقط
 * می‌خواند:
 *
 *   • هانی‌پات (‎website‎) — فیلدی که سمتِ کلاینت با CSS مخفی است؛ رباتی
 *     که فرم را خودکار پر می‌کند معمولاً هر ورودیِ موجود در DOM را پر
 *     می‌کند. پرشدنش یعنی ربات، و پاسخ عمداً «موفق» است تا رباتی که
 *     نتیجه را می‌سنجد چیزی برایِ تطبیق‌دادن نداشته باشد.
 *   • ریت‌لیمیتِ IP مثلِ ‎Likes_Endpoint‎ — سقفِ پایین‌تر از آنجا (اینجا هر
 *     درخواست یعنی یک انسان باید بعداً تماس بگیرد، نه فقط یک شمارنده).
 */
final class Consultation_Endpoint {

    private static bool $booted = false;

    public const ACTION        = 'zig3d_consultation';
    public const ACTION_DELETE = 'zig3d_consultation_delete';
    public const NONCE         = 'zig3d_consultation';

    /*
     * سقفِ پیش‌فرض عمداً سخاوتمند است، نه ۵ تایِ قبلی: با قابلیتِ «ثبتِ
     * خودکار در هر صفحه»، یک کاربرِ واقعی که چند محصول را می‌بیند و رویِ
     * هرکدام درخواست می‌دهد، به‌سرعت به سقفِ پایین می‌خورد؛ ضمناً کاربرانِ
     * موبایل پشتِ یک NATِ اپراتور IP مشترک دارند. با فیلترِ
     * ‎zig3d_consultation_rate_limit_max‎ قابلِ تنظیم است.
     */
    private const RATE_LIMIT_WINDOW = 300;
    private const RATE_LIMIT_MAX    = 30;

    public static function boot(): void {
        if (self::$booted) {
            return;
        }

        self::$booted = true;

        add_action('wp_ajax_' . self::ACTION, [self::class, 'handle']);
        add_action('wp_ajax_nopriv_' . self::ACTION, [self::class, 'handle']);
        add_action('wp_ajax_' . self::ACTION_DELETE, [self::class, 'handle_delete']);
        add_action('wp_ajax_nopriv_' . self::ACTION_DELETE, [self::class, 'handle_delete']);
    }

    public static function url(): string {
        return admin_url('admin-ajax.php');
    }

    public static function nonce(): string {
        return wp_create_nonce(self::NONCE);
    }

    public static function handle(): void {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- نانس دستی چند خط پایین‌تر بررسی می‌شود
        $post = wp_unslash($_POST);

        if (!is_array($post)) {
            self::fail('bad_request', 400);

            return;
        }

        if (!wp_verify_nonce((string) ($post['nonce'] ?? ''), self::NONCE)) {
            self::fail('bad_nonce', 403);

            return;
        }

        if ('' !== trim((string) ($post['website'] ?? ''))) {
            // هانی‌پات پر شده: به ربات نشانه‌ای که رد شده داده نمی‌شود.
            wp_send_json_success(['ok' => true]);

            return;
        }

        if (self::rate_limited(self::client_ip())) {
            self::fail('rate_limited', 429);

            return;
        }

        $submission = Consultations::sanitize_submission($post);

        if (null === $submission) {
            self::fail('invalid', 422);

            return;
        }

        $row = Consultations::insert($submission, Consultations::sanitize_source($post));

        if (false === $row) {
            self::fail('save_failed', 500);

            return;
        }

        wp_send_json_success(['ok' => true, 'id' => $row['id'], 'delete_token' => $row['delete_token']]);
    }

    /**
     * حذفِ همان درخواستی که سمتِ کلاینت خودکار (بدونِ نمایشِ فرم، از رویِ
     * نام/شمارهٔ ذخیره‌شده در ‎localStorage‎) ثبت شده بود — کاربر رویِ
     * «حذف درخواست و ویرایش مشخصات» زده. نانس همان نانسِ ثبت است (کنشِ
     * جداگانه‌ای نیست که نانسِ خودش را بخواهد)؛ اثباتِ مالکیتِ ردیف با
     * توکنی است که فقط همان درخواست‌کننده در جیبش دارد.
     */
    public static function handle_delete(): void {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- نانس دستی چند خط پایین‌تر بررسی می‌شود
        $post = wp_unslash($_POST);

        if (!is_array($post)) {
            self::fail('bad_request', 400);

            return;
        }

        if (!wp_verify_nonce((string) ($post['nonce'] ?? ''), self::NONCE)) {
            self::fail('bad_nonce', 403);

            return;
        }

        if (self::rate_limited(self::client_ip())) {
            self::fail('rate_limited', 429);

            return;
        }

        $id    = absint($post['id'] ?? 0);
        $token = sanitize_text_field((string) ($post['delete_token'] ?? ''));

        if (!Consultations::delete_by_token($id, $token)) {
            self::fail('not_found', 404);

            return;
        }

        wp_send_json_success(['ok' => true]);
    }

    private static function fail(string $code, int $status): void {
        wp_send_json_error(['code' => $code], $status);
    }

    private static function rate_limited(string $ip): bool {
        if ('' === $ip) {
            return false;
        }

        $key   = 'zig3d_consult_rl_' . md5($ip);
        $count = (int) get_transient($key);
        $max   = (int) apply_filters('zig3d_consultation_rate_limit_max', self::RATE_LIMIT_MAX);

        if ($max > 0 && $count >= $max) {
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
