<?php
namespace Zig3d_Widgets;

if (!defined('ABSPATH')) {
    exit;
}

final class Download_Archive_Data {
    public const JETENGINE_CPT_ID = 8;
    public const SIZE_META = '_zig_download_size_bytes';
    public const SIZE_SOURCE_META = '_zig_download_size_source';
    public const SIZE_HUMAN_META = '_zig_download_size_human';
    private const BACKFILL_OPTION = 'zig3d_download_size_human_backfilled';

    private static ?array $field_schema = null;
    private static ?array $gallery_fields = null;
    private static ?array $taxonomies = null;
    private static string $post_type = '';
    private static bool $save_hook_registered = false;

    public static function boot(): void {
        add_action('init', [self::class, 'register_save_hook'], 99);
        add_action('admin_init', [self::class, 'backfill_human_size']);
    }

    /**
     * یک‌باره، خودکار: پست‌هایی که از قبل ‎SIZE_META‎ (بایتِ خام) کش‌شده
     * دارند ولی ‎SIZE_HUMAN_META‎ (رشتهٔ فارسی) هنوز ندارند را پر می‌کند.
     *
     * چرا لازم است: ‎refresh_file_size()‎ فقط وقتی ‎SIZE_HUMAN_META‎ را
     * می‌نویسد که واقعاً یک دورِ کاملِ محاسبهٔ حجم اجرا شود — و آن دور
     * فقط زمانی اجرا می‌شود که ‎download_url‎ عوض شود یا هنوز هیچ حجمی
     * کش نشده باشد. پست‌هایی که *قبل* از افزوده‌شدنِ ‎SIZE_HUMAN_META‎
     * ذخیره شده‌اند (حجمِ بایت از قبل کش شده، لینک هم عوض نشده)، دیگر
     * هیچ‌وقت آن دور را دوباره اجرا نمی‌کنند — پس بدونِ این پاسِ
     * یک‌باره، برایِ همیشه ‎SIZE_HUMAN_META‎شان خالی می‌ماند.
     *
     * با یک آپشن نشانه‌گذاری می‌شود تا فقط یک‌بار اجرا شود — هزینهٔ
     * اضافه فقط رویِ اولین بازدیدِ ادمین بعد از این آپدیت است.
     */
    public static function backfill_human_size(): void {
        if (get_option(self::BACKFILL_OPTION)) {
            return;
        }

        $post_type = self::post_type();
        if ('' === $post_type) {
            return;
        }

        $query = new \WP_Query([
            'post_type'      => $post_type,
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'meta_query'     => [
                'relation' => 'AND',
                [
                    'key'     => self::SIZE_META,
                    'value'   => 0,
                    'compare' => '>',
                    'type'    => 'NUMERIC',
                ],
                [
                    'relation' => 'OR',
                    ['key' => self::SIZE_HUMAN_META, 'compare' => 'NOT EXISTS'],
                    ['key' => self::SIZE_HUMAN_META, 'value' => '', 'compare' => '='],
                ],
            ],
        ]);

        foreach ($query->posts as $post_id) {
            $bytes = (int) get_post_meta($post_id, self::SIZE_META, true);
            if ($bytes > 0) {
                update_post_meta($post_id, self::SIZE_HUMAN_META, self::persian_size($bytes));
            }
        }

        update_option(self::BACKFILL_OPTION, true, false);
    }

    public static function post_type(): string {
        if ('' !== self::$post_type && post_type_exists(self::$post_type)) {
            return self::$post_type;
        }
        if (!function_exists('jet_engine')) {
            return '';
        }

        $engine = jet_engine();
        if (!is_object($engine) || !isset($engine->cpt) || !is_object($engine->cpt) || !method_exists($engine->cpt, 'get_items')) {
            return '';
        }

        // JetEngine's CPT manager returns the same normalized id/slug records
        // that it passes to register_post_type().
        foreach ((array) $engine->cpt->get_items() as $item) {
            if (!is_array($item) || self::JETENGINE_CPT_ID !== (int) ($item['id'] ?? 0)) {
                continue;
            }
            $post_type = sanitize_key((string) ($item['slug'] ?? ''));
            if ('' !== $post_type && post_type_exists($post_type)) {
                self::$post_type = $post_type;
                return self::$post_type;
            }
        }
        return '';
    }

    public static function register_save_hook(): void {
        if (self::$save_hook_registered) {
            return;
        }
        $post_type = self::post_type();
        if ('' === $post_type) {
            return;
        }
        self::$save_hook_registered = true;
        add_action('save_post_' . $post_type, [self::class, 'refresh_file_size'], 20, 3);
    }

    public static function jetengine_ready(): bool {
        if (!function_exists('jet_engine')) {
            return false;
        }

        $engine = jet_engine();
        return is_object($engine)
            && isset($engine->meta_boxes)
            && is_object($engine->meta_boxes)
            && method_exists($engine->meta_boxes, 'get_fields_for_context');
    }

    /** @return array<string,array{key:string,label:string,type:string,options:array<string,string>}> */
    public static function field_schema(): array {
        if (null !== self::$field_schema) {
            return self::$field_schema;
        }
        if (!self::jetengine_ready()) {
            return [];
        }

        $engine = jet_engine();
        $post_type = self::post_type();
        if ('' === $post_type) {
            return [];
        }
        $raw = $engine->meta_boxes->get_fields_for_context('post_type', $post_type);
        $schema = [];
        foreach ((array) $raw as $raw_key => $field) {
            if (is_object($field)) {
                $field = method_exists($field, 'get_args') ? $field->get_args() : get_object_vars($field);
            }
            if (!is_array($field)) {
                continue;
            }
            $key = sanitize_key((string) ($field['name'] ?? $field['id'] ?? (is_string($raw_key) ? $raw_key : '')));
            $type = sanitize_key((string) ($field['type'] ?? 'text')) ?: 'text';
            if ('' === $key || in_array($type, ['repeater', 'gallery'], true) || !empty($field['repeater']) || !empty($field['parent'])) {
                continue;
            }
            $label = trim(wp_strip_all_tags((string) ($field['title'] ?? $field['label'] ?? $key)));
            $schema[$key] = [
                'key' => $key,
                'label' => $label ?: $key,
                'type' => $type,
                'options' => self::normalize_options($field['options'] ?? []),
            ];
        }

        // A not-yet-populated JetEngine registry must remain retryable in this request.
        if ($schema) {
            self::$field_schema = $schema;
        }
        return $schema;
    }

    public static function field_options(string $role = 'all'): array {
        $allowed = self::types_for_role($role);
        $options = ['' => __('— انتخاب نشده —', 'zig3d-widgets')];
        foreach (self::field_schema() as $field) {
            if ($allowed && !in_array($field['type'], $allowed, true)) {
                continue;
            }
            $options[$field['key']] = sprintf('%s — %s [%s]', $field['label'], $field['key'], $field['type']);
        }
        return $options;
    }

    /** @return array<string,string> Gallery/media meta key => editor label. */
    public static function gallery_field_options(): array {
        $options = ['' => __('— انتخاب نشده —', 'zig3d-widgets')];
        foreach (self::gallery_fields() as $field) {
            $options[$field['key']] = sprintf('%s — %s [%s]', $field['label'], $field['key'], $field['type']);
        }
        return $options;
    }

    /** @return array<string,array{key:string,label:string,type:string}> */
    private static function gallery_fields(): array {
        if (null !== self::$gallery_fields) {
            return self::$gallery_fields;
        }
        if (!self::jetengine_ready() || '' === ($post_type = self::post_type())) {
            return [];
        }
        $fields = [];
        foreach ((array) jet_engine()->meta_boxes->get_fields_for_context('post_type', $post_type) as $raw_key => $field) {
            if (is_object($field)) {
                $field = method_exists($field, 'get_args') ? $field->get_args() : get_object_vars($field);
            }
            if (!is_array($field) || !empty($field['repeater']) || !empty($field['parent'])) {
                continue;
            }
            $key = sanitize_key((string) ($field['name'] ?? $field['id'] ?? (is_string($raw_key) ? $raw_key : '')));
            $type = sanitize_key((string) ($field['type'] ?? ''));
            if ('' === $key || !in_array($type, ['gallery', 'media'], true)) {
                continue;
            }
            $label = trim(wp_strip_all_tags((string) ($field['title'] ?? $field['label'] ?? $key)));
            $fields[$key] = ['key' => $key, 'label' => $label ?: $key, 'type' => $type];
        }
        if ($fields) {
            self::$gallery_fields = $fields;
        }
        return $fields;
    }

    public static function default_gallery_field(string $key = 'software_gallery'): string {
        return array_key_exists($key, self::gallery_fields()) ? $key : '';
    }

    /** @return array<int,array{id:int,url:string}> */
    public static function current_gallery_items(string $field_key, int $post_id = 0): array {
        $post_id = $post_id > 0 ? $post_id : (function_exists('get_queried_object_id') ? (int) get_queried_object_id() : 0);
        if ($post_id <= 0 && function_exists('get_the_ID')) {
            $post_id = (int) get_the_ID();
        }
        $post_type = self::post_type();
        if ($post_id <= 0 || '' === $post_type || get_post_type($post_id) !== $post_type) {
            return [];
        }
        return self::gallery_items(get_post_meta($post_id, $field_key, true));
    }

    /** @return array<int,array{id:int,url:string}> */
    public static function gallery_items($raw): array {
        $raw = maybe_unserialize($raw);
        if (is_string($raw)) {
            $value = trim($raw);
            if ('' === $value) { return []; }
            $decoded = json_decode($value, true);
            $raw = is_array($decoded) ? $decoded : (false !== strpos($value, ',') ? explode(',', $value) : [$value]);
        }
        if (!is_array($raw)) { return []; }
        $items = [];
        $walk = static function ($value) use (&$walk, &$items): void {
            $value = maybe_unserialize($value);
            if (is_array($value)) {
                $id = (int) ($value['id'] ?? $value['ID'] ?? $value['attachment_id'] ?? 0);
                $url = esc_url_raw((string) ($value['url'] ?? ''));
                if ($id > 0 || '' !== $url) {
                    $items[] = ['id' => $id, 'url' => $url];
                    return;
                }
                foreach ($value as $nested) { $walk($nested); }
                return;
            }
            if (is_numeric($value) && (int) $value > 0) {
                $items[] = ['id' => (int) $value, 'url' => ''];
            } elseif (is_string($value) && '' !== ($url = esc_url_raw(trim($value)))) {
                $items[] = ['id' => 0, 'url' => $url];
            }
        };
        $walk($raw);
        $seen = [];
        return array_values(array_filter($items, static function (array $item) use (&$seen): bool {
            $key = $item['id'] > 0 ? 'id:' . $item['id'] : 'url:' . $item['url'];
            if (isset($seen[$key])) { return false; }
            $seen[$key] = true;
            return true;
        }));
    }

    /** @return string[] */
    private static function types_for_role(string $role): array {
        $groups = [
            'title' => ['text', 'select'],
            'description' => ['text', 'textarea', 'wysiwyg', 'editor'],
            'version' => ['text', 'select', 'number'],
            'date' => ['date', 'datetime', 'date-time'],
            'multi' => ['checkbox', 'select', 'multiselect', 'multi-select', 'radio'],
            'url' => ['text', 'url', 'media'],
            'choice' => ['text', 'select', 'radio'],
        ];
        return $groups[$role] ?? [];
    }

    /** @return array<string,string> */
    private static function normalize_options($raw): array {
        $map = [];
        foreach ((array) $raw as $key => $option) {
            if (is_array($option)) {
                $machine = (string) ($option['key'] ?? $option['value'] ?? '');
                $label = (string) ($option['label'] ?? $option['name'] ?? $option['value'] ?? $machine);
            } elseif (is_object($option)) {
                $machine = (string) ($option->key ?? $option->value ?? '');
                $label = (string) ($option->label ?? $option->name ?? $option->value ?? $machine);
            } else {
                $machine = is_string($key) ? $key : (string) $option;
                $label = (string) $option;
            }
            $machine = trim($machine);
            if ('' !== $machine) {
                $map[$machine] = trim(wp_strip_all_tags($label)) ?: $machine;
            }
        }
        return $map;
    }

    /** @return array<string,string> */
    public static function option_map(string $field_key): array {
        return self::field_schema()[$field_key]['options'] ?? [];
    }

    public static function option_label(string $field_key, string $value): string {
        return self::option_map($field_key)[$value] ?? $value;
    }

    /** @return array<string,string> machine key => display label */
    public static function display_values(string $field_key, $raw): array {
        $selected = array_fill_keys(self::values($raw), true);
        $result = [];

        // JetEngine's configured option order is the stable presentation order.
        foreach (self::option_map($field_key) as $value => $label) {
            if (isset($selected[$value])) {
                $result[$value] = $label;
                unset($selected[$value]);
            }
        }
        // Preserve the normalized stored order for legacy values absent from the schema.
        foreach (array_keys($selected) as $value) {
            $result[$value] = $value;
        }
        return $result;
    }

    /** @return array<string,string> machine key => display label */
    public static function current_display_values(string $field_key, int $post_id = 0): array {
        $post_id = $post_id > 0 ? $post_id : (function_exists('get_queried_object_id') ? (int) get_queried_object_id() : 0);
        if ($post_id <= 0 && function_exists('get_the_ID')) {
            $post_id = (int) get_the_ID();
        }
        $post_type = self::post_type();
        if ($post_id <= 0 || '' === $post_type || get_post_type($post_id) !== $post_type) {
            return [];
        }
        return self::display_values($field_key, get_post_meta($post_id, $field_key, true));
    }

    public static function taxonomy_options(bool $optional = true): array {
        if (null === self::$taxonomies) {
            $resolved = [];
            $post_type = self::post_type();
            if ('' === $post_type) {
                return $optional ? ['' => __('— بدون تاکسونومی —', 'zig3d-widgets')] : [];
            }
            foreach ((array) get_object_taxonomies($post_type, 'objects') as $taxonomy) {
                if (!is_object($taxonomy) || empty($taxonomy->name)) {
                    continue;
                }
                $label = (string) ($taxonomy->labels->singular_name ?? $taxonomy->label ?? $taxonomy->name);
                $resolved[(string) $taxonomy->name] = sprintf('%s — %s', $label, $taxonomy->name);
            }
            if ($resolved) {
                self::$taxonomies = $resolved;
            }
        }

        return $optional
            ? ['' => __('— بدون تاکسونومی —', 'zig3d-widgets')] + (self::$taxonomies ?? [])
            : (self::$taxonomies ?? []);
    }

    public static function default_field(string $key): string {
        return array_key_exists($key, self::field_schema()) ? $key : '';
    }

    public static function default_taxonomy(string $slug): string {
        return array_key_exists($slug, self::taxonomy_options(false)) ? $slug : '';
    }

    /** @return string[] */
    public static function values($raw): array {
        $raw = maybe_unserialize($raw);
        if (is_string($raw)) {
            $trimmed = trim($raw);
            if ('' === $trimmed) {
                return [];
            }
            $decoded = json_decode($trimmed, true);
            $raw = is_array($decoded) ? $decoded : (false !== strpos($trimmed, ',') ? explode(',', $trimmed) : [$trimmed]);
        }
        if (!is_array($raw)) {
            return [];
        }

        $values = [];
        foreach ($raw as $key => $value) {
            if (!is_int($key) && self::truthy($value)) {
                $values[] = trim((string) $key);
            } elseif (is_int($key) && is_scalar($value) && !self::falsey($value)) {
                $values[] = trim((string) $value);
            }
        }
        return array_values(array_unique(array_filter($values, 'strlen')));
    }

    private static function truthy($value): bool {
        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
    }

    private static function falsey($value): bool {
        return in_array(strtolower(trim((string) $value)), ['', '0', 'false', 'no', 'off'], true);
    }

    public static function refresh_file_size(int $post_id, $post = null, bool $update = false): void {
        if (wp_is_post_revision($post_id) || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) {
            return;
        }

        $key = (string) apply_filters('zig3d_download_url_meta_key', 'download_url', $post_id);
        $url = esc_url_raw((string) get_post_meta($post_id, $key, true));
        $old = (string) get_post_meta($post_id, self::SIZE_SOURCE_META, true);
        $cached_bytes = (int) get_post_meta($post_id, self::SIZE_META, true);
        if ($url === $old && $cached_bytes > 0) {
            // لینک عوض نشده، حجم از قبل کش شده — یک دورِ کاملِ محاسبه لازم
            // نیست، ولی اگر SIZE_HUMAN_META (مثلاً چون این پست قبل از
            // افزوده‌شدنش ذخیره شده بود) هنوز خالی است، همین‌جا پرش کن.
            if ('' === (string) get_post_meta($post_id, self::SIZE_HUMAN_META, true)) {
                update_post_meta($post_id, self::SIZE_HUMAN_META, self::persian_size($cached_bytes));
            }

            return;
        }

        delete_post_meta($post_id, self::SIZE_META);
        delete_post_meta($post_id, self::SIZE_HUMAN_META);
        update_post_meta($post_id, self::SIZE_SOURCE_META, $url);
        if ('' === $url || !wp_http_validate_url($url)) {
            return;
        }

        $response = wp_safe_remote_head($url, ['timeout' => 8, 'redirection' => 3]);
        $bytes = is_wp_error($response) ? 0 : (int) wp_remote_retrieve_header($response, 'content-length');
        if ($bytes <= 0) {
            $response = wp_safe_remote_get($url, [
                'timeout' => 8,
                'redirection' => 3,
                'headers' => ['Range' => 'bytes=0-0'],
                'limit_response_size' => 1,
            ]);
            $range = is_wp_error($response) ? '' : (string) wp_remote_retrieve_header($response, 'content-range');
            if (preg_match('/\/(\d+)$/', $range, $match)) {
                $bytes = (int) $match[1];
            }
        }
        if ($bytes > 0) {
            update_post_meta($post_id, self::SIZE_META, $bytes);
            update_post_meta($post_id, self::SIZE_HUMAN_META, self::persian_size($bytes));
        }
    }

    /**
     * حجمِ خوان‌پذیرِ فارسی («۸۵۰ مگابایت») — کنارِ ‎SIZE_META‎ی خام
     * (بایت) نگه داشته می‌شود، جایگزینش نمی‌شود. دلیلِ وجودش: خودِ
     * ویجتِ جدولِ مشخصات هنوز از ‎SIZE_META‎ی خام + ‎size_format()‎ی
     * وردپرس (واحدهایِ لاتین، مثلِ «MB») استفاده می‌کند — این متایِ
     * جدا، بدونِ دست‌زدن به آن مسیر، همان عدد را به‌شکلِ آماده برایِ
     * جاهایِ دیگر (مثلِ JetEngine) با واحدهایِ فارسی می‌گذارد.
     *
     * تبدیل با پایهٔ ۱۰۲۴ (کیلوبایت/مگابایت/... دودویی، نه اعشاریِ
     * ۱۰۰۰تایی) — همان مبنایی که ‎size_format()‎ی خودِ وردپرس هم
     * استفاده می‌کند، تا عددِ نمایش‌داده‌شده با ویجتِ اصلی هم‌خوان بماند.
     */
    public static function persian_size(int $bytes): string {
        if ($bytes <= 0) {
            return '';
        }

        $units = ['بایت', 'کیلوبایت', 'مگابایت', 'گیگابایت', 'ترابایت'];
        $value = (float) $bytes;
        $unit  = 0;

        while ($value >= 1024 && $unit < count($units) - 1) {
            $value /= 1024;
            $unit++;
        }

        $decimals = (0 === $unit || floor($value) === $value) ? 0 : 1;

        return number_format($value, $decimals) . ' ' . $units[$unit];
    }
}
