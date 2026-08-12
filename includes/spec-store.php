<?php
namespace Zig3d_Widgets;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * جای نگهداریِ قالب‌های مشخصات فنی.
 *
 * عیناً همان تصمیمِ ‎Schema_Store‎ و به همان دو دلیل: قالب‌های مشترک در یک
 * گزینهٔ سراسری (چون یک قالب معمولاً به چند دسته می‌خورد)، و اتصالِ هر دسته
 * در متای خودِ همان دسته (چون دسته یک چیز است و تنظیمش هم باید یک جا بماند).
 * پاک‌سازی هم همان‌جا — جدا از ذخیره‌سازی — تا بدونِ دیتابیس سنجیدنی باشد.
 */
final class Spec_Store {

    /** گزینه‌ای که قالب‌های مشترک در آن می‌نشینند */
    public const OPTION = 'zig3d_spec_schemas';

    /** متای دسته که می‌گوید این دسته به کدام قالب وصل است */
    public const TERM_META = '_zig3d_spec_schema';

    /** تاکسونومی دسته‌بندی محصولات ووکامرس */
    public const TAXONOMY = 'product_cat';

    /* =====================================================================
     * خواندن و نوشتن
     * =================================================================== */

    private static ?array $cache = null;
    private static ?array $usage = null;

    /** @return array<string,array{label:string,groups:array}> */
    public static function schemas(): array {
        if (null === self::$cache) {
            self::$cache = self::sanitize_schemas((array) get_option(self::OPTION, []));
        }

        return self::$cache;
    }

    public static function save_schemas(array $schemas): bool {
        $schemas     = self::sanitize_schemas($schemas);
        self::$cache = $schemas;

        // update_option با مقدارِ بی‌تغییر false می‌دهد — که یعنی «چیزی ننوشتم»، نه «نشد»
        if (get_option(self::OPTION) === $schemas) {
            return true;
        }

        return (bool) update_option(self::OPTION, $schemas, false);
    }

    public static function register(): void {
        register_term_meta(self::TAXONOMY, self::TERM_META, [
            'type'              => 'array',
            'single'            => true,
            'show_in_rest'      => false,
            'sanitize_callback' => [self::class, 'sanitize_binding'],
            'auth_callback'     => static fn(): bool => current_user_can('manage_product_terms'),
        ]);
    }

    /** @return array{mode:string,schema:string} */
    public static function binding(int $term_id): array {
        $stored = get_term_meta($term_id, self::TERM_META, true);

        return self::sanitize_binding(is_array($stored) ? $stored : []);
    }

    /** @return \WP_Term[] */
    public static function categories_using(string $schema): array {
        $schema = self::name($schema);

        return '' === $schema ? [] : (self::usage()[$schema] ?? []);
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

            $binding = self::binding((int) $term->term_id);

            if (Spec_Schema::MODE_SCHEMA === $binding['mode'] && '' !== $binding['schema']) {
                $usage[$binding['schema']][] = $term;
            }
        }

        return self::$usage = $usage;
    }

    public static function save_binding(int $term_id, array $binding): bool {
        $binding = self::sanitize_binding($binding);

        // نگاشتِ حافظه‌ای دیگر معتبر نیست
        self::$usage = null;

        // اتصالِ کاملاً پیش‌فرض ذخیره نمی‌شود، پاک می‌شود
        if (self::is_default($binding)) {
            return (bool) delete_term_meta($term_id, self::TERM_META);
        }

        return (bool) update_term_meta($term_id, self::TERM_META, $binding);
    }

    /** گروه‌های نهاییِ یک دسته — تنها راهِ درستِ پرسیدن این سؤال */
    public static function resolve(int $term_id): array {
        return Spec_Schema::resolve(self::binding($term_id), self::schemas());
    }

    /* =====================================================================
     * پاک‌سازی
     * =================================================================== */

    /** @return array<string,array{label:string,groups:array}> */
    public static function sanitize_schemas(array $schemas): array {
        $clean = [];

        foreach ($schemas as $name => $schema) {
            $name = self::name((string) $name);

            if ('' === $name || !is_array($schema)) {
                continue;
            }

            $groups = Spec_Schema::sanitize_groups((array) ($schema['groups'] ?? []));

            /*
             * قالبِ بی‌گروه فقط یک گزینهٔ دیگر در دراپ‌داونِ مدیر است که
             * انتخابش صفحهٔ محصول را خالی می‌کند.
             */
            if (!$groups) {
                continue;
            }

            $clean[$name] = [
                'label'  => self::label((string) ($schema['label'] ?? ''), $name),
                'groups' => $groups,
            ];
        }

        return $clean;
    }

    /** @return array{mode:string,schema:string} */
    public static function sanitize_binding(array $binding): array {
        $mode = Spec_Schema::MODE_SCHEMA === ($binding['mode'] ?? '')
            ? Spec_Schema::MODE_SCHEMA
            : Spec_Schema::MODE_NONE;

        return [
            'mode'   => $mode,
            'schema' => self::name((string) ($binding['schema'] ?? '')),
        ];
    }

    /** اتصالی که هیچ چیزی را از حالتِ پیش‌فرض عوض نمی‌کند */
    private static function is_default(array $binding): bool {
        return Spec_Schema::MODE_NONE === $binding['mode'];
    }

    private static function name(string $value): string {
        return (string) preg_replace('/[^a-z0-9_\-]/', '', strtolower(trim($value)));
    }

    private static function label(string $value, string $fallback): string {
        $value = trim(wp_strip_all_tags($value));

        return '' === $value ? $fallback : $value;
    }
}
