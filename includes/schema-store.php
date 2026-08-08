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

    /** @return array<string,array{label:string,facets:array}> */
    public static function schemas(): array {
        return self::sanitize_schemas((array) get_option(self::OPTION, []));
    }

    public static function save_schemas(array $schemas): bool {
        return (bool) update_option(self::OPTION, self::sanitize_schemas($schemas), false);
    }

    /** @return array{mode:string,schema:string,overrides:array} */
    public static function binding(int $term_id): array {
        return self::sanitize_binding((array) get_term_meta($term_id, self::TERM_META, true));
    }

    public static function save_binding(int $term_id, array $binding): bool {
        $binding = self::sanitize_binding($binding);

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
