<?php
namespace Zig3d_Widgets\Admin;

use Zig3d_Widgets\Spec_Schema;
use Zig3d_Widgets\Spec_Store;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * بخش «گروه‌بندیِ مشخصاتِ این دسته» در صفحهٔ ویرایشِ دستهٔ محصول.
 *
 * خواهرِ ‎Category_Filters‎ — همان جا (صفحهٔ دسته، نه تنظیماتِ ویجت، به همان
 * دلیل: دسته یک چیز است و تنظیمش هم باید یک جا بماند)، ولی رابطش عمداً
 * ساده‌تر: فقط یک رادیو («بدونِ گروه‌بندی» / «قالب: …») و بس. نه چک‌باکسِ
 * ردیف‌به‌ردیف، نه بازنویسیِ محلی — چون آن‌ها اینجا معنایی ندارند: مبنایی
 * که بازنویسی رویش بنشیند وجود ندارد (‎Spec_Schema‎ چیزی «خودکار» کشف
 * نمی‌کند)، و دسته‌ای که واقعاً گروه‌بندیِ متفاوتی می‌خواهد یک قالبِ دیگر
 * انتخاب می‌کند — نه یک تنظیمِ محلیِ گم‌شدنی.
 */
final class Category_Specs {

    private const NONCE = 'zig3d_category_specs';

    public static function boot(): void {
        add_action(Spec_Store::TAXONOMY . '_edit_form_fields', [self::class, 'render'], 21, 1);
        add_action('edited_' . Spec_Store::TAXONOMY, [self::class, 'save'], 10, 1);
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

        $binding = Spec_Store::binding((int) $term->term_id);
        $schemas = Spec_Store::schemas();

        echo '<tr class="form-field"><th scope="row"><label>'
            . esc_html__('گروه‌بندیِ مشخصاتِ این دسته', 'zig3d-widgets')
            . '</label></th><td>';

        wp_nonce_field(self::NONCE, self::NONCE . '_nonce');

        self::render_mode($binding, $schemas);
        self::render_notes($binding, $schemas);

        echo '</td></tr>';
    }

    private static function render_mode(array $binding, array $schemas): void {
        printf(
            '<p><label><input type="radio" name="zig3d_spec_mode" value="%1$s"%2$s> %3$s</label></p>',
            esc_attr(Spec_Schema::MODE_NONE),
            checked(Spec_Schema::MODE_NONE, $binding['mode'], false),
            esc_html__('بدونِ گروه‌بندی', 'zig3d-widgets')
        );

        if (!$schemas) {
            printf(
                '<p class="description">%s <a href="%s">%s</a></p>',
                esc_html__('هنوز قالبی ساخته نشده.', 'zig3d-widgets'),
                esc_url(Spec_Schemas_Page::url()),
                esc_html__('ساختنِ اولین قالب', 'zig3d-widgets')
            );

            return;
        }

        // قالبی که این دسته به آن وصل است ولی دیگر وجود ندارد
        if ('' !== $binding['schema'] && !isset($schemas[$binding['schema']])) {
            $schemas = [$binding['schema'] => [
                'label' => sprintf(
                    /* translators: %s: شناسهٔ قالب */
                    __('%s — پیدا نشد', 'zig3d-widgets'),
                    $binding['schema']
                ),
            ]] + $schemas;
        }

        printf(
            '<p><label><input type="radio" name="zig3d_spec_mode" value="%1$s"%2$s> %3$s</label> ',
            esc_attr(Spec_Schema::MODE_SCHEMA),
            checked(Spec_Schema::MODE_SCHEMA, $binding['mode'], false),
            esc_html__('قالب:', 'zig3d-widgets')
        );

        echo '<select name="zig3d_spec_schema">';

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

    private static function render_notes(array $binding, array $schemas): void {
        $resolved = Spec_Schema::resolve($binding, $schemas);

        foreach ($resolved['notes'] as $note) {
            printf('<p class="description" style="color:#b32d2e">%s</p>', esc_html($note));
        }

        printf(
            '<p class="description"><a href="%s">%s</a></p>',
            esc_url(Spec_Schemas_Page::url()),
            esc_html__('مدیریتِ قالب‌های مشخصات فنی', 'zig3d-widgets')
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

        $mode = isset($_POST['zig3d_spec_mode'])
            ? sanitize_text_field(wp_unslash($_POST['zig3d_spec_mode']))
            : Spec_Schema::MODE_NONE;

        $schema = isset($_POST['zig3d_spec_schema'])
            ? sanitize_text_field(wp_unslash($_POST['zig3d_spec_schema']))
            : '';

        Spec_Store::save_binding($term_id, [
            'mode'   => $mode,
            'schema' => $schema,
        ]);
    }
}
