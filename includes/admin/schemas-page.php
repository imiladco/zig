<?php
namespace Zig3d_Widgets\Admin;

use Zig3d_Widgets\Attributes;
use Zig3d_Widgets\Facets;
use Zig3d_Widgets\Schema_Store;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * صفحهٔ «طرح‌های فیلتر» زیر منوی محصولات.
 *
 * بدون این صفحه، ‎Schema_Store‎ می‌توانست طرح بخواند ولی هیچ راهی برای
 * ساختنش نبود — و انتخابِ «طرح مشترک» در صفحهٔ دسته، همیشه یک فهرست خالی
 * می‌داد.
 *
 * دو تصمیم که رابط را ساده نگه می‌دارند:
 *
 *   • هیچ جاوااسکریپتی. کل فرم با ‎<input>‎ و ‎<select>‎ کار می‌کند و ترتیب
 *     گروه‌ها با یک عدد مشخص می‌شود، نه با کشیدن و رها کردن. کشیدن قشنگ‌تر
 *     است ولی یعنی این صفحه هم به یک باندل JS و نگهداری‌اش گره بخورد،
 *     برای کاری که سالی چند بار انجام می‌شود.
 *
 *   • حذف، اول می‌گوید چند دسته به این طرح وصل‌اند. طرحی که پاک شود ولی
 *     ارجاعش بماند، آن دسته‌ها را بی‌صدا به حالت خودکار می‌اندازد و
 *     هشدارش را فقط کسی می‌بیند که سراغ همان دسته برود.
 */
final class Schemas_Page {

    private const SLUG  = 'zig3d-filter-schemas';
    private const NONCE = 'zig3d_filter_schemas';

    public static function boot(): void {
        add_action('admin_menu', [self::class, 'register'], 20);
    }

    public static function register(): void {
        add_submenu_page(
            'edit.php?post_type=product',
            __('طرح‌های فیلتر', 'zig3d-widgets'),
            __('طرح‌های فیلتر', 'zig3d-widgets'),
            'manage_woocommerce',
            self::SLUG,
            [self::class, 'render']
        );
    }

    public static function url(string $schema = ''): string {
        $args = ['post_type' => 'product', 'page' => self::SLUG];

        if ('' !== $schema) {
            $args['schema'] = $schema;
        }

        return add_query_arg($args, admin_url('edit.php'));
    }

    /* =====================================================================
     * نمایش
     * =================================================================== */

    public static function render(): void {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        $notice = self::handle_post();
        $schemas = Schema_Store::schemas();

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $editing = isset($_GET['schema']) ? sanitize_key(wp_unslash($_GET['schema'])) : '';

        echo '<div class="wrap">';
        printf('<h1>%s</h1>', esc_html__('طرح‌های فیلتر', 'zig3d-widgets'));

        printf(
            '<p class="description">%s</p>',
            esc_html__(
                'یک طرح، فهرست ثابتی از گروه‌های فیلتر است که چند دسته می‌توانند به آن وصل شوند. هر دسته می‌تواند بعد از آن، گروهی را خاموش یا اضافه کند.',
                'zig3d-widgets'
            )
        );

        if ('' !== $notice) {
            printf('<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html($notice));
        }

        self::render_table($schemas);
        self::render_form($schemas, $editing);

        echo '</div>';
    }

    private static function render_table(array $schemas): void {
        if (!$schemas) {
            printf(
                '<p>%s</p>',
                esc_html__('هنوز طرحی ساخته نشده. تا آن‌وقت هر دسته از فهرست خودکار استفاده می‌کند.', 'zig3d-widgets')
            );

            return;
        }

        echo '<table class="widefat striped" style="margin-bottom:24px"><thead><tr>';

        foreach ([
            __('نام', 'zig3d-widgets'),
            __('گروه‌ها', 'zig3d-widgets'),
            __('دسته‌های وصل‌شده', 'zig3d-widgets'),
            '',
        ] as $heading) {
            printf('<th>%s</th>', esc_html($heading));
        }

        echo '</tr></thead><tbody>';

        foreach ($schemas as $name => $schema) {
            $using = Schema_Store::categories_using($name);

            echo '<tr>';
            printf('<td><strong>%s</strong><br><code>%s</code></td>', esc_html($schema['label']), esc_html($name));
            printf(
                '<td>%s</td>',
                esc_html(implode('، ', array_map(
                    static fn(array $facet): string => Attributes::label($facet['taxonomy']),
                    $schema['facets']
                )))
            );

            printf(
                '<td>%s</td>',
                $using
                    ? esc_html(implode('، ', wp_list_pluck($using, 'name')))
                    : '<span style="opacity:.6">' . esc_html__('هیچ', 'zig3d-widgets') . '</span>'
            );

            echo '<td>';
            printf('<a href="%s">%s</a> | ', esc_url(self::url($name)), esc_html__('ویرایش', 'zig3d-widgets'));
            self::render_delete_button($name, $using);
            echo '</td></tr>';
        }

        echo '</tbody></table>';
    }

    /**
     * @param \WP_Term[] $using
     */
    private static function render_delete_button(string $name, array $using): void {
        $warning = $using
            ? sprintf(
                /* translators: %d: تعداد دسته */
                __('این طرح در %d دسته استفاده شده. با حذفش، آن دسته‌ها به فهرست خودکار برمی‌گردند. ادامه؟', 'zig3d-widgets'),
                count($using)
            )
            : __('این طرح حذف شود؟', 'zig3d-widgets');

        echo '<form method="post" style="display:inline">';
        wp_nonce_field(self::NONCE, self::NONCE . '_nonce');
        printf('<input type="hidden" name="zig3d_schema_delete" value="%s">', esc_attr($name));
        printf(
            '<button type="submit" class="button-link delete" onclick="return confirm(%s)">%s</button>',
            esc_attr(wp_json_encode($warning)),
            esc_html__('حذف', 'zig3d-widgets')
        );
        echo '</form>';
    }

    private static function render_form(array $schemas, string $editing): void {
        $schema = $schemas[$editing] ?? null;
        $facets = $schema ? self::facet_map($schema['facets']) : [];

        printf(
            '<h2>%s</h2>',
            $schema
                ? esc_html(sprintf(/* translators: %s: نام طرح */ __('ویرایش «%s»', 'zig3d-widgets'), $schema['label']))
                : esc_html__('طرح تازه', 'zig3d-widgets')
        );

        echo '<form method="post"><table class="form-table"><tbody>';

        wp_nonce_field(self::NONCE, self::NONCE . '_nonce');

        self::field(
            __('شناسه', 'zig3d-widgets'),
            sprintf(
                '<input type="text" name="zig3d_schema_name" value="%s" class="regular-text" pattern="[a-z0-9_\-]+" required%s>',
                esc_attr($editing),
                $schema ? ' readonly' : ''
            ),
            $schema
                ? __('شناسه بعد از ساخت عوض نمی‌شود، چون دسته‌ها با همین به طرح وصل‌اند.', 'zig3d-widgets')
                : __('فقط حروف کوچک لاتین، عدد، خط تیره و زیرخط.', 'zig3d-widgets')
        );

        self::field(
            __('نام نمایشی', 'zig3d-widgets'),
            sprintf(
                '<input type="text" name="zig3d_schema_label" value="%s" class="regular-text">',
                esc_attr($schema['label'] ?? '')
            )
        );

        self::field(__('گروه‌های فیلتر', 'zig3d-widgets'), self::facets_html($facets));

        echo '</tbody></table>';

        submit_button($schema ? __('ذخیرهٔ طرح', 'zig3d-widgets') : __('ساختن طرح', 'zig3d-widgets'));

        if ($schema) {
            printf('<a href="%s" class="button">%s</a>', esc_url(self::url()), esc_html__('انصراف', 'zig3d-widgets'));
        }

        echo '</form>';
    }

    /**
     * جدول گروه‌ها.
     *
     * ترتیب با یک عدد مشخص می‌شود نه با کشیدن. عدد زشت‌تر است ولی بدون
     * جاوااسکریپت کار می‌کند و — مهم‌تر — وقتی ده گروه داری، تایپ‌کردن
     * «۳» سریع‌تر از کشیدن است.
     */
    private static function facets_html(array $facets): string {
        $all = Attributes::all();

        if (!$all) {
            return '<p>' . esc_html__('هنوز ویژگی‌ای در ووکامرس تعریف نشده.', 'zig3d-widgets') . '</p>';
        }

        $html = '<table class="widefat striped"><thead><tr>'
            . '<th>' . esc_html__('در طرح', 'zig3d-widgets') . '</th>'
            . '<th>' . esc_html__('ویژگی', 'zig3d-widgets') . '</th>'
            . '<th>' . esc_html__('ترتیب', 'zig3d-widgets') . '</th>'
            . '<th>' . esc_html__('چند انتخاب هم‌زمان', 'zig3d-widgets') . '</th>'
            . '<th>' . esc_html__('نمایش وقتی خالی است', 'zig3d-widgets') . '</th>'
            . '</tr></thead><tbody>';

        $position = 0;

        foreach ($all as $taxonomy) {
            $facet = $facets[$taxonomy] ?? null;
            $order = null === $facet ? '' : (string) ($facet['order'] ?? ++$position);

            $html .= '<tr>';

            $html .= sprintf(
                '<td><input type="checkbox" name="zig3d_schema_facets[]" value="%s"%s></td>',
                esc_attr($taxonomy),
                checked(null !== $facet, true, false)
            );

            $html .= sprintf('<td>%s<br><code>%s</code></td>', esc_html(Attributes::label($taxonomy)), esc_html($taxonomy));

            $html .= sprintf(
                '<td><input type="number" name="zig3d_schema_order[%s]" value="%s" min="1" step="1" style="width:70px"></td>',
                esc_attr($taxonomy),
                esc_attr($order)
            );

            /*
             * ‎AND‎ درون یک گروه در بیشتر ویژگی‌ها نتیجه را به صفر می‌رساند و
             * کاربر فکر می‌کند فروشگاه خالی است. پس پیش‌فرض ‎OR‎ است و متن
             * گزینه‌ها هم به زبان نتیجه نوشته شده، نه به زبان منطق بولی.
             */
            $html .= sprintf(
                '<td><select name="zig3d_schema_operator[%1$s]">'
                    . '<option value="%2$s"%4$s>%6$s</option>'
                    . '<option value="%3$s"%5$s>%7$s</option>'
                    . '</select></td>',
                esc_attr($taxonomy),
                esc_attr(Facets::OP_OR),
                esc_attr(Facets::OP_AND),
                selected(Facets::OP_OR, $facet['operator'] ?? Facets::OP_OR, false),
                selected(Facets::OP_AND, $facet['operator'] ?? '', false),
                esc_html__('هرکدام کافی است', 'zig3d-widgets'),
                esc_html__('همه با هم لازم‌اند', 'zig3d-widgets')
            );

            $html .= sprintf(
                '<td><input type="checkbox" name="zig3d_schema_empty[]" value="%s"%s></td>',
                esc_attr($taxonomy),
                checked(!empty($facet['show_empty']), true, false)
            );

            $html .= '</tr>';
        }

        return $html . '</tbody></table>';
    }

    /* =====================================================================
     * ذخیره
     * =================================================================== */

    /** @return string پیام موفقیت، یا رشتهٔ خالی */
    private static function handle_post(): string {
        if (!isset($_POST[self::NONCE . '_nonce'])) {
            return '';
        }

        $nonce = sanitize_text_field(wp_unslash($_POST[self::NONCE . '_nonce']));

        if (!wp_verify_nonce($nonce, self::NONCE) || !current_user_can('manage_woocommerce')) {
            return '';
        }

        if (isset($_POST['zig3d_schema_delete'])) {
            return self::delete(sanitize_key(wp_unslash($_POST['zig3d_schema_delete'])));
        }

        return self::save();
    }

    private static function delete(string $name): string {
        $schemas = Schema_Store::schemas();

        if (!isset($schemas[$name])) {
            return '';
        }

        unset($schemas[$name]);
        Schema_Store::save_schemas($schemas);

        /*
         * ارجاع‌های همان طرح در دسته‌ها پاک نمی‌شوند، عمداً. اگر مدیر طرح را
         * اشتباهی حذف کرده باشد و دوباره با همان شناسه بسازد، همه‌چیز سر
         * جایش برمی‌گردد. تا آن‌وقت ‎Filter_Schema‎ به حالت خودکار سقوط
         * می‌کند و در صفحهٔ دسته هشدار می‌دهد.
         */
        return __('طرح حذف شد. دسته‌هایی که به آن وصل بودند تا ساخت دوباره‌اش از فهرست خودکار استفاده می‌کنند.', 'zig3d-widgets');
    }

    private static function save(): string {
        $name = isset($_POST['zig3d_schema_name'])
            ? sanitize_key(wp_unslash($_POST['zig3d_schema_name']))
            : '';

        if ('' === $name) {
            return '';
        }

        $checked = self::posted_list('zig3d_schema_facets');
        $empty   = self::posted_list('zig3d_schema_empty');
        $orders  = self::posted_map('zig3d_schema_order');
        $ops     = self::posted_map('zig3d_schema_operator');

        $facets = [];

        foreach ($checked as $taxonomy) {
            $facets[] = [
                'taxonomy'   => $taxonomy,
                'operator'   => Facets::OP_AND === ($ops[$taxonomy] ?? '') ? Facets::OP_AND : Facets::OP_OR,
                'show_empty' => in_array($taxonomy, $empty, true),
                'order'      => (int) ($orders[$taxonomy] ?? 0),
            ];
        }

        /*
         * ترتیب پایدار: دو گروه با یک عدد نباید جایشان با هر ذخیره عوض شود.
         * ‎usort‎ در PHP 8 پایدار است ولی در ۷.۴ نه، و این افزونه هر دو را
         * پشتیبانی می‌کند.
         */
        $index = 0;

        foreach ($facets as &$facet) {
            $facet['_seq'] = $index++;
        }

        unset($facet);

        usort($facets, static function (array $a, array $b): int {
            return [$a['order'] ?: PHP_INT_MAX, $a['_seq']] <=> [$b['order'] ?: PHP_INT_MAX, $b['_seq']];
        });

        $schemas = Schema_Store::schemas();

        /*
         * تیک‌برداشتن از همهٔ گروه‌ها نباید به حذفِ خاموشِ طرح تبدیل شود.
         * ‎sanitize_schemas()‎ طرح بی‌گروه را دور می‌ریزد — که برای ورودی
         * خراب درست است، ولی اینجا یعنی مدیری که اشتباهی همه را برداشته،
         * طرحش را از دست بدهد و دسته‌های وصل‌شده هم بی‌صدا بیفتند. حذف باید
         * از دکمهٔ حذف بیاید، با شمارشِ دسته‌های وصل‌شده.
         */
        if (!$facets) {
            return __('دست‌کم یک ویژگی را تیک بزنید؛ طرح بی‌گروه ذخیره نمی‌شود.', 'zig3d-widgets');
        }

        $schemas[$name] = [
            'label'  => isset($_POST['zig3d_schema_label'])
                ? sanitize_text_field(wp_unslash($_POST['zig3d_schema_label']))
                : '',
            'facets' => $facets,
        ];

        Schema_Store::save_schemas($schemas);
        Attributes::flush();

        return __('طرح ذخیره شد.', 'zig3d-widgets');
    }

    /* =====================================================================
     * کمکی‌ها
     * =================================================================== */

    /** @return array<string,array{operator:string,show_empty:bool,order:int}> */
    private static function facet_map(array $facets): array {
        $map   = [];
        $order = 0;

        foreach ($facets as $facet) {
            $map[$facet['taxonomy']] = $facet + ['order' => ++$order];
        }

        return $map;
    }

    private static function field(string $label, string $control, string $description = ''): void {
        printf('<tr><th scope="row">%s</th><td>%s', esc_html($label), $control);

        if ('' !== $description) {
            printf('<p class="description">%s</p>', esc_html($description));
        }

        echo '</td></tr>';
    }

    /** @return string[] */
    private static function posted_list(string $key): array {
        if (!isset($_POST[$key]) || !is_array($_POST[$key])) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn($value): string => sanitize_key(wp_unslash((string) $value)),
            $_POST[$key]
        )));
    }

    /** @return array<string,string> */
    private static function posted_map(string $key): array {
        if (!isset($_POST[$key]) || !is_array($_POST[$key])) {
            return [];
        }

        $map = [];

        foreach ($_POST[$key] as $taxonomy => $value) {
            $taxonomy = sanitize_key(wp_unslash((string) $taxonomy));

            if ('' !== $taxonomy) {
                $map[$taxonomy] = sanitize_text_field(wp_unslash((string) $value));
            }
        }

        return $map;
    }
}
