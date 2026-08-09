<?php
namespace Zig3d_Widgets;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * جای نگهداری طرح‌های فیلتر.
 *
 * دو چیز ذخیره می‌شود و جایشان عمدی است:
 *
 *   • طرح‌های مشترک، در یک گزینهٔ سراسری. چون یک طرح («فرزها») معمولاً به
 *     چند دسته می‌خورد و کپی‌کردنش یعنی روزی که ویژگی تازه‌ای اضافه شود،
 *     باید یادت باشد همهٔ کپی‌ها را به‌روز کنی. یادت نمی‌ماند.
 *
 *   • اتصالِ هر دسته، در متای خودِ همان دسته. نه در تنظیمات ویجت — چون
 *     ویجت می‌تواند در چند صفحه باشد و آن‌وقت یک دسته چند تنظیم متناقض
 *     داشت. دسته یک چیز است، پس تنظیمش هم باید یک جا باشد.
 *
 * پاک‌سازی، بخش جدی این فایل است و جدا از ذخیره‌سازی نوشته شده تا بشود
 * بدون دیتابیس سنجیدش. مقدارِ ذخیره‌شده در دیتابیس همیشه مشکوک است: هم
 * ممکن است از نسخهٔ قدیمی افزونه مانده باشد، هم ممکن است کسی دستی
 * عوضش کرده باشد.
 */
final class Schema_Store {

    /** گزینه‌ای که طرح‌های مشترک در آن می‌نشینند */
    public const OPTION = 'zig3d_filter_schemas';

    /** متای دسته که می‌گوید این دسته به کدام طرح وصل است */
    public const TERM_META = '_zig3d_filter_schema';

    /** تاکسونومی دسته‌بندی محصولات ووکامرس */
    public const TAXONOMY = 'product_cat';

    /* =====================================================================
     * خواندن و نوشتن
     * =================================================================== */

    /** نتیجهٔ پاک‌سازی‌شده، تا در یک درخواست چند بار ساخته نشود */
    private static ?array $cache = null;

    /** نگاشت «طرح ⇒ دسته‌ها»، با همان منطق */
    private static ?array $usage = null;

    /**
     * طرح‌های مشترک.
     *
     * ‎get_option()‎ خودش کش دارد ولی پاک‌سازی ندارد، و این تابع در رندر یک
     * آرشیو به ازای هر گروه فیلتر صدا زده می‌شود. پاک‌سازیِ تکراریِ یک
     * آرایهٔ ثابت، کارِ بی‌خودی است که در نگاه اول دیده نمی‌شود.
     *
     * @return array<string,array{label:string,facets:array}>
     */
    public static function schemas(): array {
        if (null === self::$cache) {
            self::$cache = self::sanitize_schemas((array) get_option(self::OPTION, []));
        }

        return self::$cache;
    }

    public static function save_schemas(array $schemas): bool {
        $schemas     = self::sanitize_schemas($schemas);
        self::$cache = $schemas;

        /*
         * ‎update_option()‎ وقتی مقدار عوض نشده باشد ‎false‎ می‌دهد — که یعنی
         * «چیزی ننوشتم»، نه «نشد». برگرداندنِ خامش، هر ذخیرهٔ بدون‌تغییر را
         * به چشم مدیر یک شکست نشان می‌داد.
         */
        if (get_option(self::OPTION) === $schemas) {
            return true;
        }

        return (bool) update_option(self::OPTION, $schemas, false);
    }

    /**
     * ثبت متای دسته.
     *
     * بدون ثبت هم ‎get_term_meta()‎ کار می‌کند، ولی آن‌وقت این متا در REST
     * و در هر ابزار همگام‌سازی، یک ردیف بی‌صاحب است. ‎show_in_rest‎ عمداً
     * خاموش است: این تنظیم فقط از صفحهٔ دسته معنا دارد و بازکردنش روی REST
     * یعنی یک راه دیگر برای نوشتنش که پاک‌سازی‌اش را دور می‌زند.
     */
    public static function register(): void {
        register_term_meta(self::TAXONOMY, self::TERM_META, [
            'type'              => 'array',
            'single'            => true,
            'show_in_rest'      => false,
            'sanitize_callback' => [self::class, 'sanitize_binding'],
            'auth_callback'     => static fn(): bool => current_user_can('manage_product_terms'),
        ]);
    }

    /** @return array{mode:string,schema:string,overrides:array} */
    public static function binding(int $term_id): array {
        $stored = get_term_meta($term_id, self::TERM_META, true);

        return self::sanitize_binding(is_array($stored) ? $stored : []);
    }

    /**
     * دسته‌هایی که به یک طرح مشترک وصل‌اند.
     *
     * برای حذف لازم است. طرحی که پاک شود ولی هنوز چهار دسته به آن ارجاع
     * داشته باشند، هر چهار دسته را بی‌صدا به حالت خودکار می‌اندازد —
     * ‎Filter_Schema‎ هشدارش را می‌دهد، ولی فقط وقتی کسی سراغ آن دسته
     * برود. بهتر است همان لحظهٔ حذف بدانیم.
     *
     * @return \WP_Term[]
     */
    public static function categories_using(string $schema): array {
        $schema = self::name($schema);

        return '' === $schema ? [] : (self::usage()[$schema] ?? []);
    }

    /**
     * نگاشت «هر طرح ⇒ دسته‌هایی که به آن وصل‌اند».
     *
     * یک بار ساخته می‌شود، نه یک بار به ازای هر طرح. جدول طرح‌ها به ازای هر
     * ردیف یک بار می‌پرسد «چه کسی از این استفاده می‌کند؟» و بدون این
     * نگاشت، با پنج طرح، پنج بار کل دسته‌ها پیمایش می‌شد.
     *
     * @return array<string,\WP_Term[]>
     */
    public static function usage(): array {
        if (null !== self::$usage) {
            return self::$usage;
        }

        $usage = [];

        $terms = get_terms([
            'taxonomy'   => self::TAXONOMY,
            'hide_empty' => false,
        ]);

        if (!is_array($terms)) {
            return self::$usage = $usage;
        }

        foreach ($terms as $term) {
            if (!$term instanceof \WP_Term) {
                continue;
            }

            $binding = self::binding((int) $term->term_id);

            if (Filter_Schema::MODE_SCHEMA === $binding['mode'] && '' !== $binding['schema']) {
                $usage[$binding['schema']][] = $term;
            }
        }

        return self::$usage = $usage;
    }

    public static function save_binding(int $term_id, array $binding): bool {
        $binding = self::sanitize_binding($binding);

        /*
         * نگاشتِ حافظه‌ای دیگر معتبر نیست. با ذخیره‌ای که بلافاصله بعدش
         * چیزی رندر می‌شود، شمارندهٔ «چند دسته از این طرح استفاده می‌کنند»
         * عددِ قبل از ذخیره را نشان می‌داد.
         */
        self::$usage = null;

        /*
         * اتصالِ کاملاً پیش‌فرض ذخیره نمی‌شود، پاک می‌شود. وگرنه هر دسته‌ای
         * که یک بار صفحه‌اش باز و ذخیره شده، یک ردیف متای بی‌معنا می‌گیرد و
         * بعداً هیچ راهی نیست بفهمی کدام دسته واقعاً تنظیم دارد.
         */
        if (self::is_default($binding)) {
            return (bool) delete_term_meta($term_id, self::TERM_META);
        }

        return (bool) update_term_meta($term_id, self::TERM_META, $binding);
    }

    /**
     * فهرست نهایی گروه‌های فیلتر یک دسته، با ردّ تصمیم.
     *
     * تنها جایی که ذخیره‌سازی و منطق به هم می‌رسند. خودِ تصمیم در
     * ‎Filter_Schema‎ گرفته می‌شود؛ اینجا فقط ورودی‌هایش جمع می‌شود.
     */
    public static function resolve(int $term_id, array $discovered): array {
        return Filter_Schema::resolve(self::binding($term_id), self::schemas(), $discovered);
    }

    /* =====================================================================
     * پاک‌سازی
     * =================================================================== */

    /**
     * @param array $schemas خام، از گزینه یا از فرم.
     * @return array<string,array{label:string,facets:array}>
     */
    public static function sanitize_schemas(array $schemas): array {
        $clean = [];

        foreach ($schemas as $name => $schema) {
            $name = self::name((string) $name);

            if ('' === $name || !is_array($schema)) {
                continue;
            }

            $facets = Filter_Schema::sanitize_facets((array) ($schema['facets'] ?? []));

            /*
             * طرح بدون هیچ گروهی، یک نام است و بس. نگه‌داشتنش فقط یک گزینهٔ
             * دیگر در دراپ‌داونِ مدیر می‌گذارد که انتخابش سایدبار را خالی
             * می‌کند — و بعد ساعت‌ها دنبال دلیلش می‌گردد.
             */
            if (!$facets) {
                continue;
            }

            $clean[$name] = [
                'label'  => self::label((string) ($schema['label'] ?? ''), $name),
                'facets' => $facets,
            ];
        }

        return $clean;
    }

    /**
     * @return array{mode:string,schema:string,overrides:array<string,array>}
     */
    public static function sanitize_binding(array $binding): array {
        $mode = Filter_Schema::MODE_SCHEMA === ($binding['mode'] ?? '')
            ? Filter_Schema::MODE_SCHEMA
            : Filter_Schema::MODE_AUTO;

        return [
            'mode'      => $mode,
            'schema'    => self::name((string) ($binding['schema'] ?? '')),
            'overrides' => self::sanitize_overrides((array) ($binding['overrides'] ?? [])),
        ];
    }

    /**
     * بازنویسی‌ها: فقط دو کلید، و فقط وقتی واقعاً چیزی را عوض می‌کنند.
     *
     * بازنویسیِ بی‌اثر (‎enabled = true‎ بدون هیچ چیز دیگر) دور ریخته
     * می‌شود. نگه‌داشتنش بی‌ضرر به نظر می‌رسد ولی شمارندهٔ «این دسته N
     * بازنویسی دارد» را دروغ می‌کند — و آن شمارنده تنها چیزی است که به مدیر
     * می‌گوید فهرستش دست‌کاری شده.
     */
    private static function sanitize_overrides(array $overrides): array {
        $clean = [];

        foreach ($overrides as $taxonomy => $rule) {
            $taxonomy = self::name((string) $taxonomy);

            if ('' === $taxonomy || !is_array($rule)) {
                continue;
            }

            $out = [];

            // فقط «خاموش» معنا دارد؛ «روشن» همان حالت پیش‌فرض است
            if (array_key_exists('enabled', $rule) && !$rule['enabled']) {
                $out['enabled'] = false;
            }

            /*
             * «افزوده» برخلاف بقیه، حتی وقتی تنها کلید است هم معنا دارد:
             * یعنی گروهی که در فهرست پایه نیست باید بیاید. همین نشان است که
             * افزودنِ عمدی را از ارجاعِ ماندهٔ یک گروه پاک‌شده جدا می‌کند.
             */
            if (!empty($rule['added'])) {
                $out['added'] = true;
            }

            // و اینجا فقط «نشان بده»، چون «نشان نده» هم پیش‌فرض است
            if (!empty($rule['show_empty'])) {
                $out['show_empty'] = true;
            }

            if ($out) {
                $clean[$taxonomy] = $out;
            }
        }

        ksort($clean, SORT_STRING);

        return $clean;
    }

    /** اتصالی که هیچ چیزی را از حالت پیش‌فرض عوض نمی‌کند */
    private static function is_default(array $binding): bool {
        return Filter_Schema::MODE_AUTO === $binding['mode']
            && [] === $binding['overrides'];
    }

    private static function name(string $value): string {
        return (string) preg_replace('/[^a-z0-9_\-]/', '', strtolower(trim($value)));
    }

    private static function label(string $value, string $fallback): string {
        $value = trim(wp_strip_all_tags($value));

        return '' === $value ? $fallback : $value;
    }
}
