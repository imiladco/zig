<?php
namespace Zig3d_Widgets;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * جای نگهداریِ گروه‌هایِ مشخصاتِ فنی.
 *
 * یک کتابخانهٔ تختِ گروه‌ها، در یک گزینهٔ سراسری — هر گروه یک بار تعریف
 * می‌شود و هر دسته مستقیماً چند گروه از همین کتابخانه را، به‌ترتیبِ
 * دلخواه، انتخاب می‌کند. اتصالِ هر دسته (همان فهرستِ نام‌ها، به‌ترتیب) در
 * متایِ خودِ همان دسته — دقیقاً همان دو تصمیمِ ‎Schema_Store‎ و به همان
 * دلیل‌ها: گروهِ مشترک یک‌بار تعریف می‌شود چون معمولاً به چند دسته می‌خورد،
 * و اتصالِ دسته یک‌جا می‌ماند چون دسته یک چیز است.
 *
 * تفاوتش با نسخهٔ قبلی: آنجا یک لایهٔ «قالب» هم بود — دسته به یک قالب وصل
 * می‌شد و قالب چند گروه را بسته‌بندی می‌کرد. آن لایه حذف شد؛ گروه‌بندیِ
 * خودِ گروه‌ها یک سطحِ اضافه بود. حالا انتخاب مستقیم است: دسته می‌گوید
 * «این گروه‌ها، به این ترتیب» — بدونِ واسطه.
 */
final class Spec_Store {

    /** گزینه‌ای که کتابخانهٔ گروه‌ها در آن می‌نشیند */
    public const OPTION = 'zig3d_spec_groups';

    /** متایِ دسته: فهرستِ نامِ گروه‌ها، به ترتیبِ نمایش */
    public const TERM_META = '_zig3d_spec_groups';

    /** تاکسونومیِ دسته‌بندیِ محصولاتِ ووکامرس */
    public const TAXONOMY = 'product_cat';

    /* =====================================================================
     * کتابخانهٔ گروه‌ها
     * =================================================================== */

    private static ?array $cache = null;
    private static ?array $usage = null;

    /** @return array<string,array{label:string,items:array}> */
    public static function groups(): array {
        if (null === self::$cache) {
            self::$cache = self::sanitize_groups((array) get_option(self::OPTION, []));
        }

        return self::$cache;
    }

    public static function save_groups(array $groups): bool {
        $groups      = self::sanitize_groups($groups);
        self::$cache = $groups;

        // update_option با مقدارِ بی‌تغییر false می‌دهد — یعنی «چیزی ننوشتم»، نه «نشد»
        if (get_option(self::OPTION) === $groups) {
            return true;
        }

        return (bool) update_option(self::OPTION, $groups, false);
    }

    /** ذخیرهٔ یک گروهِ تکی — نقطهٔ ورودِ فرمِ ویرایشِ یک گروه */
    public static function save_group(string $name, array $group): bool {
        $name  = self::name($name);
        $clean = Spec_Group::sanitize($group);

        if ('' === $name || null === $clean) {
            return false;
        }

        $groups         = self::groups();
        $groups[$name]  = $clean;

        return self::save_groups($groups);
    }

    public static function delete_group(string $name): bool {
        $name   = self::name($name);
        $groups = self::groups();

        if ('' === $name || !isset($groups[$name])) {
            return false;
        }

        unset($groups[$name]);

        return self::save_groups($groups);
    }

    /* =====================================================================
     * اتصالِ دسته
     * =================================================================== */

    public static function register(): void {
        register_term_meta(self::TAXONOMY, self::TERM_META, [
            'type'              => 'array',
            'single'            => true,
            'show_in_rest'      => false,
            'sanitize_callback' => [self::class, 'sanitize_category_groups'],
            'auth_callback'     => static fn(): bool => current_user_can('manage_product_terms'),
        ]);
    }

    /** @return string[] فهرستِ نامِ گروه‌ها، به ترتیبِ نمایش */
    public static function category_groups(int $term_id): array {
        $stored = get_term_meta($term_id, self::TERM_META, true);

        return self::sanitize_category_groups(is_array($stored) ? $stored : []);
    }

    public static function save_category_groups(int $term_id, array $names): bool {
        $names       = self::sanitize_category_groups($names);
        self::$usage = null;

        if (!$names) {
            return (bool) delete_term_meta($term_id, self::TERM_META);
        }

        return (bool) update_term_meta($term_id, self::TERM_META, $names);
    }

    /** @return \WP_Term[] */
    public static function categories_using(string $group): array {
        $group = self::name($group);

        return '' === $group ? [] : (self::usage()[$group] ?? []);
    }

    /** @return array<string,\WP_Term[]> */
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

            foreach (self::category_groups((int) $term->term_id) as $name) {
                $usage[$name][] = $term;
            }
        }

        return self::$usage = $usage;
    }

    /**
     * گروه‌هایِ نهاییِ یک دسته، به‌ترتیب — تنها راهِ درستِ پرسیدنِ این سؤال.
     *
     * ارجاع به گروهی که دیگر در کتابخانه نیست (پاک شده) بی‌صدا نمی‌افتد؛
     * در ‎notes‎ گزارش می‌شود تا مدیر بفهمد چرا صفحهٔ محصول یک گروه کم دارد.
     *
     * @return array{groups:array,notes:string[]}
     */
    public static function resolve(int $term_id): array {
        $library = self::groups();
        $groups  = [];
        $notes   = [];

        foreach (self::category_groups($term_id) as $name) {
            if (isset($library[$name])) {
                $groups[] = $library[$name] + ['name' => $name];

                continue;
            }

            $notes[] = sprintf('گروهِ «%s» دیگر وجود ندارد و از این دسته افتاد.', $name);
        }

        return ['groups' => $groups, 'notes' => $notes];
    }

    /* =====================================================================
     * پاک‌سازی
     * =================================================================== */

    /** @return array<string,array{label:string,items:array}> */
    public static function sanitize_groups(array $groups): array {
        $clean = [];

        foreach ($groups as $name => $group) {
            $name = self::name((string) $name);

            if ('' === $name || !is_array($group)) {
                continue;
            }

            $sanitized = Spec_Group::sanitize($group);

            if (null !== $sanitized) {
                $clean[$name] = $sanitized;
            }
        }

        return $clean;
    }

    /** @return string[] */
    public static function sanitize_category_groups(array $names): array {
        $clean = [];

        foreach ($names as $name) {
            $name = self::name((string) $name);

            // تکراری نادیده گرفته می‌شود؛ اولین جایگاهش می‌ماند
            if ('' !== $name && !in_array($name, $clean, true)) {
                $clean[] = $name;
            }
        }

        return $clean;
    }

    private static function name(string $value): string {
        return (string) preg_replace('/[^a-z0-9_\-]/', '', strtolower(trim($value)));
    }
}
