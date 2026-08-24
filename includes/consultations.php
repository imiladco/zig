<?php
namespace Zig3d_Widgets;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ذخیره‌سازیِ درخواست‌هایِ مشاوره — یک جدولِ اختصاصی، نه پست‌تایپ.
 *
 * این‌ها لاگِ سادهٔ یک کنش‌اند (کسی فرم را پر کرد)، نه محتوایی که نیاز به
 * ویرایشگرِ وردپرس، بازبینی، یا postmeta داشته باشد — دقیقاً همان
 * اضافه‌باری که در باگِ حافظهٔ ویجتِ توضیحاتِ همین افزونه (v1.55.0) به
 * فاتالِ ۲ گیگابایتی رسید. یک ردیف، یک درج، بدونِ بازبینی.
 *
 * نصب/ارتقاء با یک نسخهٔ جدا از نسخهٔ خودِ افزونه رهگیری می‌شود
 * (‎SCHEMA_OPTION‎)، نه با ‎register_activation_hook‎ — این افزونه اصلاً
 * از آن استفاده نمی‌کند (نگاه کنید به ‎Plugin::maybe_flush_after_update()‎)
 * چون نصب‌کننده‌های زیادی افزونه را فقط آپلود/به‌روزرسانی می‌کنند، نه
 * غیرفعال/فعال؛ بررسیِ نسخه روی هر ‎init‎ همان تضمین را می‌دهد و از فعال‌سازیِ
 * دستی هم مستقل است.
 */
final class Consultations {

    private const SCHEMA_OPTION  = 'zig3d_consultations_schema_version';
    private const SCHEMA_VERSION = '1';

    public const PER_PAGE = 20;

    /** بیشترین طولِ هرکدام، برایِ همسانی با ستونِ دیتابیس (نگاه کنید به ‎maybe_upgrade()‎) */
    private const MAX_NAME    = 190;
    private const MAX_PHONE   = 32;
    private const MAX_MESSAGE = 2000;
    private const MAX_URL     = 500;
    private const MAX_TITLE   = 255;

    public static function table(): string {
        global $wpdb;

        return $wpdb->prefix . 'zig3d_consultations';
    }

    /**
     * ساخت/ارتقاءِ جدول.
     *
     * ‎dbDelta()‎ خودش idempotent است (فقط تفاوتِ اسکیما را اعمال می‌کند)،
     * ولی صدازدنش هزینه دارد (چند کوئری برایِ خواندنِ ساختارِ فعلی) — پس
     * یک بررسیِ نسخهٔ ارزان جلویِ آن را می‌گیرد تا فقط وقتی واقعاً چیزی
     * عوض شده اجرا شود.
     */
    public static function maybe_upgrade(): void {
        if (get_option(self::SCHEMA_OPTION) === self::SCHEMA_VERSION) {
            return;
        }

        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table            = self::table();
        $charset_collate  = $wpdb->get_charset_collate();

        dbDelta("CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(" . self::MAX_NAME . ") NOT NULL,
            phone VARCHAR(" . self::MAX_PHONE . ") NOT NULL,
            message TEXT NULL,
            source_url VARCHAR(" . self::MAX_URL . ") NULL,
            source_title VARCHAR(" . self::MAX_TITLE . ") NULL,
            product_id BIGINT UNSIGNED NULL,
            product_name VARCHAR(" . self::MAX_TITLE . ") NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY created_at (created_at)
        ) {$charset_collate};");

        update_option(self::SCHEMA_OPTION, self::SCHEMA_VERSION, false);
    }

    /* =====================================================================
     * پاک‌سازی — بخشِ خالص، بدونِ دیتابیس
     * =================================================================== */

    /**
     * ‎09‎ + کدِ اپراتورِ معتبر + ۷ رقمِ آخر — همان الگویی که سمتِ کلاینت
     * (‎zig3d-consultation.js‎) هم استفاده می‌شود؛ اینجا هم لازم است چون
     * این متد باید بدونِ اعتماد به جاوااسکریپت (مثلاً درخواستِ مستقیم به
     * آژاکس) هم درست کار کند.
     */
    private const IR_MOBILE_PATTERN = '/^09(0[1-5]|1[0-9]|2[0-2]|3[0-9]|9[0-9])\d{7}$/';

    /**
     * تبدیلِ فرمت‌هایِ رایجِ شماره‌یِ موبایلِ ایرانی —‎+98912...‎،
     * ‎0098912...‎، ‎98912...‎، یا بدونِ صفرِ ابتدایی (‎912...‎) — به
     * ساختارِ یکتایِ ‎09xxxxxxxxx‎. منطقش عیناً با ‎normalizeIranianMobile‎یِ
     * سمتِ جاوااسکریپت یکی است.
     */
    public static function normalize_phone(string $raw): string {
        $had_plus = 0 === strpos($raw, '+');
        $digits   = preg_replace('/[^0-9]/', '', $raw);

        if ($had_plus && 0 === strpos($digits, '98')) {
            return '0' . substr($digits, 2);
        }

        if (0 === strpos($digits, '0098')) {
            return '0' . substr($digits, 4);
        }

        if (12 === strlen($digits) && 0 === strpos($digits, '98')) {
            return '0' . substr($digits, 2);
        }

        if (10 === strlen($digits) && 0 === strpos($digits, '9')) {
            return '0' . $digits;
        }

        return $digits;
    }

    /**
     * پاک‌سازیِ ورودیِ فرم. نام اجباری است؛ شماره هم اجباری است و هم باید
     * بعدِ نرمال‌سازی با الگویِ شماره‌یِ موبایلِ ایرانی جور باشد —
     * خالی‌بودن یا نامعتبربودنِ هرکدام یعنی ورودیِ نامعتبر، نه ثبتِ ردیفِ
     * نصفه/بی‌فرمت.
     *
     * @return array{name:string,phone:string,message:string}|null
     */
    public static function sanitize_submission(array $raw): ?array {
        $name    = trim(sanitize_text_field((string) ($raw['name'] ?? '')));
        $phone   = self::normalize_phone((string) ($raw['phone'] ?? ''));
        $message = trim(sanitize_textarea_field((string) ($raw['message'] ?? '')));

        if ('' === $name || 1 !== preg_match(self::IR_MOBILE_PATTERN, $phone)) {
            return null;
        }

        return [
            'name'    => mb_substr($name, 0, self::MAX_NAME),
            'phone'   => mb_substr($phone, 0, self::MAX_PHONE),
            'message' => mb_substr($message, 0, self::MAX_MESSAGE),
        ];
    }

    /**
     * پاک‌سازیِ منبعِ ثبت — از کدام صفحه و رویِ کدام محصول.
     *
     * @return array{url:string,title:string,product_id:int,product_name:string}
     */
    public static function sanitize_source(array $raw): array {
        return [
            'url'          => mb_substr(esc_url_raw((string) ($raw['source_url'] ?? '')), 0, self::MAX_URL),
            'title'        => mb_substr(sanitize_text_field((string) ($raw['source_title'] ?? '')), 0, self::MAX_TITLE),
            'product_id'   => isset($raw['product_id']) ? absint($raw['product_id']) : 0,
            'product_name' => mb_substr(sanitize_text_field((string) ($raw['product_name'] ?? '')), 0, self::MAX_TITLE),
        ];
    }

    /**
     * پاک‌سازیِ فهرستِ شناسه‌ها برایِ حذفِ گروهی — فقط اعدادِ مثبت، بدونِ
     * تکرار، بدونِ صفر.
     *
     * @return array<int,int>
     */
    public static function sanitize_ids(array $raw): array {
        $ids = array_map('absint', $raw);
        $ids = array_values(array_unique(array_filter($ids)));

        return $ids;
    }

    /* =====================================================================
     * نوشتن/خواندن — دیتابیسِ واقعی
     * =================================================================== */

    /**
     * ثبتِ یک درخواست.
     *
     * @param array{name:string,phone:string,message:string}                        $submission
     * @param array{url:string,title:string,product_id:int,product_name:string}     $source
     * @return int|false شناسهٔ ردیفِ تازه، یا false اگر درج شکست خورد
     */
    public static function insert(array $submission, array $source) {
        self::maybe_upgrade();

        global $wpdb;

        $result = $wpdb->insert(
            self::table(),
            [
                'name'         => $submission['name'],
                'phone'        => $submission['phone'],
                'message'      => '' !== $submission['message'] ? $submission['message'] : null,
                'source_url'   => '' !== $source['url'] ? $source['url'] : null,
                'source_title' => '' !== $source['title'] ? $source['title'] : null,
                'product_id'   => $source['product_id'] > 0 ? $source['product_id'] : null,
                'product_name' => '' !== $source['product_name'] ? $source['product_name'] : null,
                'created_at'   => current_time('mysql'),
            ],
            ['%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s']
        );

        return $result ? (int) $wpdb->insert_id : false;
    }

    /**
     * یک صفحه از فهرست، تازه‌ترین اول.
     *
     * @return array{rows:array<int,array<string,mixed>>,total:int}
     */
    public static function page(int $page, int $per_page = self::PER_PAGE): array {
        self::maybe_upgrade();

        global $wpdb;

        $page     = max(1, $page);
        $per_page = max(1, min(100, $per_page));
        $offset   = ($page - 1) * $per_page;
        $table    = self::table();

        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}"); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- بدونِ ورودیِ کاربر

        $rows = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$table} ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d", $per_page, $offset),
            ARRAY_A
        );

        return ['rows' => is_array($rows) ? $rows : [], 'total' => $total];
    }

    /**
     * حذفِ یک یا چند ردیف. شناسه‌هایِ نامعتبر/تکراری خودشان اینجا هم
     * دوباره پاک می‌شوند تا این متد بدونِ عبور از ‎sanitize_ids()‎ هم امن
     * بماند.
     *
     * @return int تعدادِ ردیفِ واقعاً حذف‌شده
     */
    public static function delete(array $ids): int {
        $ids = self::sanitize_ids($ids);

        if (!$ids) {
            return 0;
        }

        self::maybe_upgrade();

        global $wpdb;

        $table        = self::table();
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));

        $deleted = $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE id IN ({$placeholders})", $ids)); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- نامِ جدول، نه ورودیِ کاربر؛ شناسه‌ها با prepare() جای‌گذاری می‌شوند

        return false !== $deleted ? (int) $deleted : 0;
    }
}
