<?php
/**
 * Plugin Name: ZIG3D Archive Probe
 * Description: سنجه‌های سمت سرورِ آرشیو را روی یک نصب واقعی اجرا می‌کند. ابزار تست است، نه بخشی از افزونه.
 * Version:     1.0.0
 *
 * نصب: این فایل را در ‎wp-content/mu-plugins/‎ بگذارید.
 * اجرا: به‌عنوان مدیر، ‎?zig_probe=1‎ را به هر آدرس آرشیو اضافه کنید.
 *
 * چرا اصلاً وجود دارد: بخش زیادی از ‎tests/INTEGRATION.md‎ چیزهایی را
 * می‌سنجد که فقط با وردپرس و ووکامرس واقعی معنا دارند — اینکه ووکامرس چه
 * ورودی‌ای می‌بیند، در SQL نهایی چه می‌آید، و کد HTTP چه می‌شود. آن‌ها را
 * نمی‌شود در تست واحد شبیه‌سازی کرد و شبیه‌سازی‌شان بدتر از نداشتنشان است:
 * یک PASS دروغین.
 *
 * پس به‌جای چک‌لیست دستی، همان ادعاها اینجا کد شده‌اند تا روی سایت واقعی
 * با یک آدرس اجرا شوند.
 *
 * چیزی که *نمی‌سنجد*: هر چیزی که به مرورگر نیاز دارد — کلیک، فوکوس،
 * تاریخچه، آژاکس. آن‌ها در ‎INTEGRATION.md‎ جدا علامت خورده‌اند.
 *
 * @package Zig3d_Widgets_Tests
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Zig3d_Archive_Probe {

    /** @var array<int,array{id:string,ok:bool,note:string}> */
    private static array $results = [];

    public static function boot(): void {
        add_action('wp_footer', [self::class, 'run'], 9999);
        add_action('shutdown', [self::class, 'report'], 9999);
    }

    private static function active(): bool {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        return isset($_GET['zig_probe'])
            && current_user_can('manage_woocommerce')
            && class_exists('WC_Query')
            && class_exists('\Zig3d_Widgets\Query_State');
    }

    /* =====================================================================
     * اجرا
     * =================================================================== */

    /**
     * روی ‎wp_footer‎ و نه زودتر.
     *
     * ‎WC_Query::get_layered_nav_chosen_attributes()‎ نتیجه‌اش را در یک
     * ثابت ایستا کش می‌کند. خواندنش *بعد* از اجرای کوئری اصلی یعنی همان
     * چیزی را می‌بینیم که واقعاً استفاده شده، نه یک محاسبهٔ تازه.
     */
    public static function run(): void {
        if (!self::active()) {
            return;
        }

        $caps    = self::caps();
        $chosen  = WC_Query::get_layered_nav_chosen_attributes();
        $dropped = self::dropped();

        /* ---------------------------------------------------------------
         * الف) محافظت — ووکامرس فقط ورودی بریده‌شده را می‌بیند
         * ------------------------------------------------------------ */

        self::check(
            '۳۲ گروه‌هایی که ووکامرس می‌بیند',
            count($chosen) <= $caps['groups'],
            count($chosen) . ' <= ' . $caps['groups']
        );

        $worst = 0;

        foreach ($chosen as $attribute) {
            $worst = max($worst, count((array) ($attribute['terms'] ?? [])));
        }

        self::check(
            '۳۳ بیشترین ترم در یک گروه',
            $worst <= $caps['terms'],
            $worst . ' <= ' . $caps['terms']
        );

        $paged = (int) $GLOBALS['wp_query']->get('paged');

        self::check(
            '۳۴ صفحهٔ کوئری اصلی',
            $paged <= $caps['page'],
            $paged . ' <= ' . $caps['page']
        );

        $survivors = [];

        foreach ($chosen as $attribute) {
            foreach ((array) ($attribute['terms'] ?? []) as $term) {
                $survivors[] = (string) $term;
            }
        }

        $leaked = array_values(array_intersect($dropped, $survivors));

        self::check(
            '۳۵ اسلاگ بریده‌شده به ووکامرس نرسیده',
            [] === $leaked,
            $dropped ? count($dropped) . ' اسلاگ بریده شد؛ نشتی: ' . (count($leaked) ?: 'ندارد') : 'چیزی بریده نشد'
        );

        /* ---------------------------------------------------------------
         * ۳۶ و ۴۱ — از خودِ SQL
         * ------------------------------------------------------------ */

        $sql = self::product_sql();

        if (null === $sql) {
            self::skip('۳۶ و ۴۱ متن SQL', 'برای این دو، SAVEQUERIES باید در wp-config روشن باشد');
        } else {
            $in_sql = [];

            foreach ($dropped as $slug) {
                if (false !== strpos($sql, "'" . $slug . "'")) {
                    $in_sql[] = $slug;
                }
            }

            self::check(
                '۳۶ اسلاگ بریده‌شده در SQL نیست',
                [] === $in_sql,
                $in_sql ? implode(', ', array_slice($in_sql, 0, 5)) : 'تمیز'
            );

            $joins = substr_count(strtolower($sql), 'term_relationships');

            self::check(
                '۴۱ تعداد JOIN روی term_relationships (فقط نشانه)',
                $joins <= $caps['groups'] + 1,
                $joins . ' <= ' . ($caps['groups'] + 1)
            );
        }

        /* ---------------------------------------------------------------
         * ب) سئو — همان آدرس هنوز ۴۰۴ می‌گیرد
         * ------------------------------------------------------------ */

        $state  = \Zig3d_Widgets\Archive_Head::page_state();
        $status = http_response_code();

        if (null === $state) {
            self::skip('۳۷ تا ۴۰ سئو', 'این آدرس آرشیو محصول نیست، یا Archive_Head اینجا کاری ندارد');
        } else {
            $oversized = [] !== $dropped || self::page_was_capped();

            if ($oversized) {
                self::check('۳۷ کد HTTP', 404 === $status, (string) $status);
                self::check('۳۹ وضعیت صفحه', 'invalid' === $state, $state);
            } else {
                self::check('۴۴ آدرس بی‌گناه ۲۰۰ می‌گیرد', 200 === $status, (string) $status);
                self::check('۴۴ و وضعیتش invalid نیست', 'invalid' !== $state, $state);
            }
        }

        self::note('۳۸ و ۴۰ (robots و canonical) را در منبع صفحه ببینید — probe خروجی خودش را نمی‌خواند');
    }

    /* =====================================================================
     * ورودی
     * =================================================================== */

    /** @return array{groups:int,terms:int,page:int} */
    private static function caps(): array {
        return [
            'groups' => \Zig3d_Widgets\Query_State::MAX_GROUPS,
            'terms'  => \Zig3d_Widgets\Query_State::MAX_TERMS,
            'page'   => \Zig3d_Widgets\Query_State::MAX_PAGE,
        ];
    }

    /**
     * اسلاگ‌هایی که نگهبان انداخت.
     *
     * از رشتهٔ خام ‎QUERY_STRING‎ خوانده می‌شود و نه ‎$_GET‎: نگهبان ‎$_GET‎
     * را عوض کرده، پس تنها جایی که ورودی *اصلی* هنوز هست همین‌جاست. بدون
     * آن، این probe فقط می‌توانست بگوید «چیزی از سقف رد نشده» — که همیشه
     * درست است و هیچ چیزی را اثبات نمی‌کند.
     *
     * @return string[]
     */
    private static function dropped(): array {
        $raw = [];

        parse_str((string) ($_SERVER['QUERY_STRING'] ?? ''), $raw);

        if (!is_array($raw)) {
            return [];
        }

        $before = self::slugs($raw);
        $after  = self::slugs(\Zig3d_Widgets\Query_State::cap_params($raw));

        return array_values(array_diff($before, $after));
    }

    /** @return string[] */
    private static function slugs(array $params): array {
        $slugs = [];

        foreach ($params as $key => $value) {
            if (0 !== strpos((string) $key, 'filter_') || !is_string($value)) {
                continue;
            }

            foreach (explode(',', $value) as $term) {
                $term = sanitize_title(trim($term));

                if ('' !== $term) {
                    $slugs[] = $term;
                }
            }
        }

        return array_values(array_unique($slugs));
    }

    private static function page_was_capped(): bool {
        $raw = [];

        parse_str((string) ($_SERVER['QUERY_STRING'] ?? ''), $raw);

        return isset($raw['paged'])
            && (int) $raw['paged'] > \Zig3d_Widgets\Query_State::MAX_PAGE;
    }

    /**
     * کوئری اصلی محصولات، از لاگ ‎SAVEQUERIES‎.
     *
     * بزرگ‌ترین ‎SELECT‎ی که هم ‎wp_posts‎ دارد و هم ‎product‎ — بی‌عیب نیست
     * ولی روی یک آرشیو، همان کوئری‌ای است که دنبالش هستیم.
     */
    private static function product_sql(): ?string {
        global $wpdb;

        if (!defined('SAVEQUERIES') || !SAVEQUERIES || empty($wpdb->queries)) {
            return null;
        }

        $found = null;

        foreach ($wpdb->queries as $entry) {
            $sql = (string) ($entry[0] ?? '');

            if (false === stripos($sql, 'from ' . $wpdb->posts) || false === stripos($sql, 'product')) {
                continue;
            }

            if (null === $found || strlen($sql) > strlen($found)) {
                $found = $sql;
            }
        }

        return $found;
    }

    /* =====================================================================
     * گزارش
     * =================================================================== */

    private static function check(string $id, bool $ok, string $note = ''): void {
        self::$results[] = ['id' => $id, 'ok' => $ok, 'note' => $note];
    }

    private static function skip(string $id, string $why): void {
        self::$results[] = ['id' => $id, 'ok' => null, 'note' => $why];
    }

    private static function note(string $text): void {
        self::$results[] = ['id' => '', 'ok' => null, 'note' => $text];
    }

    public static function report(): void {
        if (!self::active() || !self::$results) {
            return;
        }

        $failed = 0;

        echo "\n<!-- ZIG3D ARCHIVE PROBE\n";

        foreach (self::$results as $result) {
            if (false === $result['ok']) {
                ++$failed;
            }

            $mark = null === $result['ok'] ? '·' : ($result['ok'] ? 'PASS' : 'FAIL');

            echo esc_html(sprintf("%-5s %s %s\n", $mark, $result['id'], $result['note'] ? '— ' . $result['note'] : ''));
        }

        echo esc_html(sprintf("\n%d سنجه، %d ناموفق\n", count(self::$results), $failed));
        echo "-->\n";
    }
}

Zig3d_Archive_Probe::boot();
