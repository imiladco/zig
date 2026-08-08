<?php
namespace Zig3d_Widgets\Admin;

use Zig3d_Widgets\Attributes;
use Zig3d_Widgets\Filter_Schema;
use Zig3d_Widgets\Schema_Store;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * بخش «فیلترهای این دسته» در صفحهٔ ویرایش دستهٔ محصول.
 *
 * چرا اینجا و نه در تنظیمات ویجت: ویجت می‌تواند در چند صفحه باشد. اگر
 * تنظیمِ هر دسته داخل نمونهٔ ویجت می‌نشست، با بیست دسته و سه صفحه، تنظیمات
 * شروع می‌کردند به تکثیرشدن و هیچ‌کس نمی‌فهمید کدام‌شان برنده است. دسته یک
 * چیز است، پس تنظیمش هم یک جا می‌ماند.
 *
 * و چرا صفحهٔ ویرایش دسته و نه یک منوی تازه: مدیر از قبل اینجا می‌آید.
 * منوی جدید یعنی یک جای دیگر که باید یادش بماند وجود دارد.
 *
 * کل رابط عمداً یک لایه است: حذف، افزودن، و «حتی اگر خالی بود نشان بده».
 * هر چیز بیشتری، همان زنجیرهٔ بازنویسی می‌شد که ‎Filter_Schema‎ برای
 * نداشتنش نوشته شده.
 */
final class Category_Filters {

    private const NONCE = 'zig3d_category_filters';

    public static function boot(): void {
        add_action(Schema_Store::TAXONOMY . '_edit_form_fields', [self::class, 'render'], 20, 1);
        add_action('edited_' . Schema_Store::TAXONOMY, [self::class, 'save'], 10, 1);
    }

    /* =====================================================================
     * نمایش
     * =================================================================== */

    /**
     * @param \WP_Term $term
     */
    public static function render($term): void {
        if (!$term instanceof \WP_Term || !current_user_can('manage_product_terms')) {
            return;
        }

        $binding    = Schema_Store::binding((int) $term->term_id);
        $schemas    = Schema_Store::schemas();
        $discovered = self::discover($term);
        $resolved   = Filter_Schema::resolve($binding, $schemas, $discovered);

        /*
         * فهرست چک‌باکس‌ها اجتماع دو چیز است: آنچه در فهرست نهایی هست و
         * آنچه در محصولات این دسته پیدا شده. بدون دومی، مدیر نمی‌توانست
         * ویژگی‌ای را که خودش قبلاً خاموش کرده دوباره روشن کند — چون از
         * فهرست نهایی افتاده بود و دیگر دیده نمی‌شد.
         */
        $rows = self::rows($resolved['facets'], $discovered, $binding['overrides']);

        echo '<tr class="form-field"><th scope="row"><label>'
            . esc_html__('فیلترهای این دسته', 'zig3d-widgets')
            . '</label></th><td>';

        wp_nonce_field(self::NONCE, self::NONCE . '_nonce');

        self::render_mode($binding, $schemas);
        self::render_rows($rows);
        self::render_others($rows);
        self::render_notes($resolved);

        echo '</td></tr>';
    }

    private static function render_mode(array $binding, array $schemas): void {
        $mode = $binding['mode'];

        printf(
            '<p><label><input type="radio" name="zig3d_filter_mode" value="%1$s"%2$s> %3$s</label></p>',
            esc_attr(Filter_Schema::MODE_AUTO),
            checked(Filter_Schema::MODE_AUTO, $mode, false),
            esc_html__('خودکار — هر ویژگی‌ای که روی محصولات این دسته هست', 'zig3d-widgets')
        );

        if (!$schemas) {
            printf(
                '<p class="description">%s</p>',
                esc_html__('هنوز طرح مشترکی ساخته نشده. تا آن‌وقت، حالت خودکار کار می‌کند.', 'zig3d-widgets')
            );

            return;
        }

        /*
         * اگر دسته به طرحی وصل است که دیگر وجود ندارد، آن شناسه در دراپ‌داون
         * نیست و ‎selected‎ روی هیچ گزینه‌ای نمی‌نشیند — یعنی فرم بی‌صدا
         * طرحِ اول را انتخاب‌شده نشان می‌داد و اولین ذخیره، اتصال را عوض
         * می‌کرد بدون اینکه کسی چیزی زده باشد.
         */
        if ('' !== $binding['schema'] && !isset($schemas[$binding['schema']])) {
            $schemas = [$binding['schema'] => [
                'label' => sprintf(
                    /* translators: %s: شناسهٔ طرح */
                    __('%s — پیدا نشد', 'zig3d-widgets'),
                    $binding['schema']
                ),
            ]] + $schemas;
        }

        printf(
            '<p><label><input type="radio" name="zig3d_filter_mode" value="%1$s"%2$s> %3$s</label> ',
            esc_attr(Filter_Schema::MODE_SCHEMA),
            checked(Filter_Schema::MODE_SCHEMA, $mode, false),
            esc_html__('طرح مشترک:', 'zig3d-widgets')
        );

        echo '<select name="zig3d_filter_schema">';

        foreach ($schemas as $name => $schema) {
            printf(
                '<option value="%s"%s>%s</option>',
                esc_attr($name),
                selected($name, $binding['schema'], false),
                esc_html($schema['label'])
            );
        }

        echo '</select></p>';
    }

    /**
     * @param array<int,array{taxonomy:string,label:string,active:bool,show_empty:bool,present:bool}> $rows
     */
    private static function render_rows(array $rows): void {
        if (!$rows) {
            printf(
                '<p class="description">%s</p>',
                esc_html__('هیچ ویژگی‌ای روی محصولات این دسته پیدا نشد.', 'zig3d-widgets')
            );

            return;
        }

        echo '<ul style="margin:12px 0">';

        foreach ($rows as $row) {
            echo '<li style="margin-bottom:6px">';

            printf(
                '<label><input type="checkbox" name="zig3d_filter_on[]" value="%s"%s> %s</label>',
                esc_attr($row['taxonomy']),
                checked($row['active'], true, false),
                esc_html($row['label'])
            );

            /*
             * «حتی اگر خالی بود» فقط کنار ویژگی‌ای معنا دارد که الان روی هیچ
             * محصولی نیست؛ برای بقیه فقط یک تیک اضافه است که کاری نمی‌کند.
             */
            if (!$row['present']) {
                printf(
                    ' <label style="opacity:.75"><input type="checkbox" name="zig3d_filter_empty[]" value="%s"%s> %s</label>',
                    esc_attr($row['taxonomy']),
                    checked($row['show_empty'], true, false),
                    esc_html__('حتی اگر محصولی نداشت نشان بده', 'zig3d-widgets')
                );
            }

            echo '</li>';
        }

        echo '</ul>';
    }

    /**
     * بقیهٔ ویژگی‌های فروشگاه، برای افزودن.
     *
     * بدون این بخش، «حالت کاستوم» فقط می‌توانست چیزی را کم کند. ولی حالت
     * پرتکرارِ واقعی این است: ویژگی‌ای تعریف شده، محصولاتش دارند می‌آیند، و
     * مدیر می‌خواهد گروهش از همین حالا در سایدبار باشد.
     *
     * @param array<int,array{taxonomy:string}> $rows
     */
    private static function render_others(array $rows): void {
        $shown  = array_column($rows, 'taxonomy');
        $others = array_diff(Attributes::all(), $shown);

        if (!$others) {
            return;
        }

        printf(
            '<details><summary>%s</summary><ul style="margin:8px 0">',
            esc_html__('افزودن ویژگی دیگری از فروشگاه', 'zig3d-widgets')
        );

        foreach ($others as $taxonomy) {
            printf(
                '<li style="margin-bottom:6px"><label><input type="checkbox" name="zig3d_filter_on[]" value="%1$s"> %2$s</label>'
                    . ' <label style="opacity:.75"><input type="checkbox" name="zig3d_filter_empty[]" value="%1$s"> %3$s</label></li>',
                esc_attr($taxonomy),
                esc_html(Attributes::label($taxonomy)),
                esc_html__('حتی اگر محصولی نداشت نشان بده', 'zig3d-widgets')
            );
        }

        echo '</ul></details>';
    }

    /**
     * ردّ تصمیم، به زبان آدمیزاد.
     *
     * تنها چیزی است که جلوی «از کجا آمد؟» را می‌گیرد. بدون آن، مدیری که
     * دسته‌اش به یک طرح مشترک وصل است و دو بازنویسی هم دارد، هیچ راهی ندارد
     * بفهمد چرا سایدبار آن چیزی نیست که در طرح دیده.
     */
    private static function render_notes(array $resolved): void {
        if (Filter_Schema::MODE_SCHEMA === $resolved['mode']) {
            $summary = $resolved['overrides'] > 0
                ? sprintf(
                    /* translators: 1: نام طرح، 2: تعداد بازنویسی */
                    __('این دسته از طرح «%1$s» می‌آید، با %2$d بازنویسی محلی.', 'zig3d-widgets'),
                    $resolved['schema'],
                    $resolved['overrides']
                )
                : sprintf(
                    /* translators: %s: نام طرح */
                    __('این دسته دقیقاً از طرح «%s» می‌آید.', 'zig3d-widgets'),
                    $resolved['schema']
                );
        } else {
            $summary = $resolved['overrides'] > 0
                ? sprintf(
                    /* translators: %d: تعداد بازنویسی */
                    __('فهرست خودکار، با %d بازنویسی محلی.', 'zig3d-widgets'),
                    $resolved['overrides']
                )
                : __('فهرست خودکار، بدون بازنویسی.', 'zig3d-widgets');
        }

        printf('<p class="description">%s</p>', esc_html($summary));

        /*
         * فهرست خالی، بدترین حالتِ بی‌صداست: صفحهٔ آرشیو یک سایدبار خالی
         * می‌گیرد و هیچ‌جا نوشته نمی‌شود چرا. اینجا تنها جایی است که می‌شود
         * قبل از دیده‌شدنش گفت.
         */
        if (!$resolved['facets']) {
            printf(
                '<p class="description" style="color:#b32d2e">%s</p>',
                esc_html__('هیچ گروهی نمی‌ماند؛ سایدبار این دسته خالی رندر می‌شود.', 'zig3d-widgets')
            );
        }

        foreach ($resolved['notes'] as $note) {
            printf('<p class="description" style="color:#b32d2e">%s</p>', esc_html($note));
        }

        printf(
            '<p class="description"><a href="%s">%s</a></p>',
            esc_url(Schemas_Page::url()),
            esc_html__('مدیریت طرح‌های مشترک', 'zig3d-widgets')
        );
    }

    /* =====================================================================
     * ذخیره
     * =================================================================== */

    public static function save(int $term_id): void {
        if (!isset($_POST[self::NONCE . '_nonce'])) {
            return;
        }

        $nonce = sanitize_text_field(wp_unslash($_POST[self::NONCE . '_nonce']));

        if (!wp_verify_nonce($nonce, self::NONCE) || !current_user_can('manage_product_terms')) {
            return;
        }

        $mode = isset($_POST['zig3d_filter_mode'])
            ? sanitize_text_field(wp_unslash($_POST['zig3d_filter_mode']))
            : Filter_Schema::MODE_AUTO;

        $schema = isset($_POST['zig3d_filter_schema'])
            ? sanitize_text_field(wp_unslash($_POST['zig3d_filter_schema']))
            : '';

        $on    = self::posted_list('zig3d_filter_on');
        $empty = self::posted_list('zig3d_filter_empty');

        Schema_Store::save_binding($term_id, [
            'mode'      => $mode,
            'schema'    => $schema,
            'overrides' => self::overrides($term_id, $mode, $schema, $on, $empty),
        ]);

        // فهرست ویژگی‌های این دسته عوض شده؛ شمارش‌های کش‌شده دیگر معتبر نیستند
        Attributes::flush();
    }

    /**
     * تبدیل تیک‌های فرم به بازنویسی.
     *
     * نکتهٔ ظریف: فرم می‌گوید «چه چیزی روشن است»، ولی ذخیره‌شده «چه چیزی با
     * فهرست پایه *فرق دارد*» است. اگر مستقیم فهرست روشن‌ها ذخیره می‌شد،
     * ویژگی‌ای که فردا به محصولات این دسته اضافه شود هیچ‌وقت ظاهر نمی‌شد —
     * چون آن روز در فهرست تیک‌خورده نبوده. یعنی «خودکار» دیگر خودکار نبود.
     *
     * پس فقط تفاوت‌ها ذخیره می‌شوند: خاموش‌شده‌ها، افزوده‌ها، و آن‌هایی که
     * باید حتی خالی هم دیده شوند.
     */
    private static function overrides(int $term_id, string $mode, string $schema, array $on, array $empty): array {
        $binding = ['mode' => $mode, 'schema' => $schema, 'overrides' => []];
        $base    = Filter_Schema::taxonomies(
            Filter_Schema::resolve($binding, Schema_Store::schemas(), self::discover(get_term($term_id)))['facets']
        );

        // ویژگی‌هایی که مدیر دستی روشن کرده ولی در فهرست پایه نیستند
        $known = array_values(array_unique(array_merge($base, $on)));

        $overrides = [];

        foreach ($known as $taxonomy) {
            if (!in_array($taxonomy, $on, true)) {
                $overrides[$taxonomy] = ['enabled' => false];

                continue;
            }

            $rule = [];

            // گروهی که در فهرست پایه نیست ولی مدیر تیکش زده، افزوده است
            if (!in_array($taxonomy, $base, true)) {
                $rule['added'] = true;
            }

            if (in_array($taxonomy, $empty, true)) {
                $rule['show_empty'] = true;
            }

            if ($rule) {
                $overrides[$taxonomy] = $rule;
            }
        }

        return $overrides;
    }

    /* =====================================================================
     * کمکی‌ها
     * =================================================================== */

    /**
     * ردیف‌های چک‌باکس.
     *
     * @return array<int,array{taxonomy:string,label:string,active:bool,show_empty:bool,present:bool}>
     */
    private static function rows(array $facets, array $discovered, array $overrides): array {
        $active = Filter_Schema::taxonomies($facets);
        $shown  = [];
        $rows   = [];

        foreach (array_merge($active, $discovered, array_keys($overrides)) as $taxonomy) {
            if (isset($shown[$taxonomy])) {
                continue;
            }

            $shown[$taxonomy] = true;

            $rows[] = [
                'taxonomy'   => $taxonomy,
                'label'      => Attributes::label($taxonomy),
                'active'     => in_array($taxonomy, $active, true),
                'show_empty' => !empty($overrides[$taxonomy]['show_empty']),
                'present'    => in_array($taxonomy, $discovered, true),
            ];
        }

        return $rows;
    }

    /**
     * @param \WP_Term|mixed $term
     * @return string[]
     */
    private static function discover($term): array {
        if (!$term instanceof \WP_Term) {
            return [];
        }

        return Attributes::discover(
            [
                'post_type'   => 'product',
                'post_status' => 'publish',
                'tax_query'   => [[
                    'taxonomy' => Schema_Store::TAXONOMY,
                    'field'    => 'term_id',
                    'terms'    => [(int) $term->term_id],
                ]],
            ],
            'cat-' . (int) $term->term_id
        );
    }

    /** @return string[] */
    private static function posted_list(string $key): array {
        if (!isset($_POST[$key]) || !is_array($_POST[$key])) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn($value): string => sanitize_text_field(wp_unslash((string) $value)),
            $_POST[$key]
        )));
    }
}
