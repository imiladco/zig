<?php
namespace Zig3d_Widgets\Admin;

use Zig3d_Widgets\Attributes;
use Zig3d_Widgets\Spec_Group;
use Zig3d_Widgets\Spec_Store;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * صفحهٔ «گروه‌های مشخصات فنی» زیرِ منویِ محصولات.
 *
 * کتابخانهٔ تختِ گروه‌ها — هر گروه یک واحدِ مستقلِ چندبارمصرف است، نه
 * بسته‌بندی‌شده داخلِ یک «قالب». فهرست + جست‌وجو اینجا، و ویرایشِ هر گروه
 * (عنوان + مشخصه‌ها) در یک پنلِ جدا از همین صفحه، با پیشوندِ ‎?group=‎.
 *
 * رابطِ سازندهٔ مشخصه‌ها (این صفحه) و گروه‌چینِ دسته (‎Category_Specs‎) هر
 * دو با جاوااسکریپتِ خودمختار و درگ‌اند‌دراپِ بومی ساخته شده‌اند — نه برایِ
 * زیبایی، برایِ مقیاس: فروشگاهی با صدها ویژگی و ده‌ها گروه، با دکمهٔ
 * ▲/▼ یا شمارهٔ ترتیب، سریع خسته‌کننده می‌شود. هر جا جاوااسکریپت نرسد،
 * چک‌باکس‌های خامِ زیرش هنوز کار می‌کنند — فقط بی‌درگ‌ودراپ.
 */
final class Spec_Groups_Page {

    private const SLUG  = 'zig3d-spec-groups';
    private const NONCE = 'zig3d_spec_groups';

    /** متنی که در کمبوباکس برای گزینهٔ «سفارشی» نشان داده می‌شود — هم در دیتالیست، هم در مقدارِ اولیهٔ ردیف */
    private const CUSTOM_ATTRIBUTE_LABEL = 'ویژگیِ سفارشی…';

    public static function boot(): void {
        add_action('admin_menu', [self::class, 'register'], 21);
    }

    public static function register(): void {
        add_submenu_page(
            'edit.php?post_type=product',
            __('گروه‌های مشخصات فنی', 'zig3d-widgets'),
            __('گروه‌های مشخصات فنی', 'zig3d-widgets'),
            'manage_woocommerce',
            self::SLUG,
            [self::class, 'render']
        );
    }

    public static function url(string $group = ''): string {
        $args = ['post_type' => 'product', 'page' => self::SLUG];

        if ('' !== $group) {
            $args['group'] = $group;
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
        $groups = Spec_Store::groups();

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $editing = isset($_GET['group']) ? sanitize_key(wp_unslash($_GET['group'])) : '';

        self::render_styles();

        echo '<div class="wrap zig3d-spec-wrap">';
        printf('<h1>%s</h1>', esc_html__('گروه‌های مشخصات فنی', 'zig3d-widgets'));

        printf(
            '<p class="description">%s</p>',
            esc_html__(
                'هر گروه یک بخشِ آکاردئونی است — یک عنوان و چند مشخصه. هر گروه یک‌بار اینجا تعریف می‌شود؛ هر دستهٔ محصول از صفحهٔ ویرایشِ خودش چند گروه از همین فهرست را، به‌ترتیبِ دلخواه، انتخاب می‌کند.',
                'zig3d-widgets'
            )
        );

        if ('' !== $notice) {
            printf('<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html($notice));
        }

        self::render_table($groups, $editing);
        self::render_form($groups, $editing);

        echo '</div>';
    }

    private static function render_table(array $groups, string $editing): void {
        printf(
            '<p><a href="%s" class="button button-primary">%s</a></p>',
            esc_url(self::url('new')),
            esc_html__('+ گروهِ تازه', 'zig3d-widgets')
        );

        if (!$groups) {
            printf('<p>%s</p>', esc_html__('هنوز گروهی ساخته نشده.', 'zig3d-widgets'));

            return;
        }

        echo '<div class="zig3d-spec-search"><input type="text" id="zig3d-groups-search" placeholder="'
            . esc_attr__('جست‌وجو در گروه‌ها…', 'zig3d-widgets') . '"></div>';

        echo '<table class="widefat striped" id="zig3d-groups-table" style="margin-bottom:24px"><thead><tr>';

        foreach ([
            __('نام', 'zig3d-widgets'),
            __('مشخصه‌ها', 'zig3d-widgets'),
            __('دسته‌های استفاده‌کننده', 'zig3d-widgets'),
            '',
        ] as $heading) {
            printf('<th>%s</th>', esc_html($heading));
        }

        echo '</tr></thead><tbody>';

        foreach ($groups as $name => $group) {
            $using = Spec_Store::categories_using($name);
            $active = $name === $editing ? ' zig3d-row-active' : '';

            printf('<tr class="zig3d-groups-row%s" data-zig3d-search="%s">', esc_attr($active), esc_attr($group['label'] . ' ' . $name));

            printf('<td><strong>%s</strong><br><code>%s</code></td>', esc_html($group['label']), esc_html($name));

            printf(
                '<td>%s</td>',
                esc_html(implode('، ', array_map(
                    static fn(array $item): string => '' !== $item['label'] ? $item['label'] : self::item_default_label($item),
                    $group['items']
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

    private static function item_default_label(array $item): string {
        if ('attribute' === $item['source']) {
            return 'custom' === $item['attribute']
                ? $item['custom_attribute']
                : Attributes::label($item['attribute']);
        }

        $labels = [
            'category'    => __('دسته‌بندی', 'zig3d-widgets'),
            'tag'         => __('برچسب‌ها', 'zig3d-widgets'),
            'sku'         => __('SKU', 'zig3d-widgets'),
            'rating'      => __('امتیاز', 'zig3d-widgets'),
            'stock'       => __('موجودی', 'zig3d-widgets'),
            'weight'      => __('وزن', 'zig3d-widgets'),
            'dimensions'  => __('ابعاد', 'zig3d-widgets'),
            'custom_meta' => $item['meta_key'],
        ];

        return $labels[$item['source']] ?? '';
    }

    /**
     * @param \WP_Term[] $using
     */
    private static function render_delete_button(string $name, array $using): void {
        $warning = $using
            ? sprintf(
                /* translators: %d: تعداد دسته */
                __('این گروه در %d دسته استفاده شده. با حذفش، از فهرستِ آن دسته‌ها هم می‌افتد. ادامه؟', 'zig3d-widgets'),
                count($using)
            )
            : __('این گروه حذف شود؟', 'zig3d-widgets');

        echo '<form method="post" style="display:inline">';
        wp_nonce_field(self::NONCE, self::NONCE . '_nonce');
        printf('<input type="hidden" name="zig3d_group_delete" value="%s">', esc_attr($name));
        printf(
            '<button type="submit" class="button-link delete" onclick="return confirm(%s)">%s</button>',
            esc_attr(wp_json_encode($warning)),
            esc_html__('حذف', 'zig3d-widgets')
        );
        echo '</form>';
    }

    /* =====================================================================
     * فرمِ ویرایشِ یک گروه
     * =================================================================== */

    private static function render_form(array $groups, string $editing): void {
        if ('' === $editing) {
            return;
        }

        $is_new = 'new' === $editing || !isset($groups[$editing]);
        $group  = $is_new ? ['label' => '', 'items' => []] : $groups[$editing];
        $name   = $is_new ? '' : $editing;

        echo '<div class="zig3d-spec-editor">';

        printf(
            '<h2>%s</h2>',
            $is_new
                ? esc_html__('گروهِ تازه', 'zig3d-widgets')
                : esc_html(sprintf(/* translators: %s: نام گروه */ __('ویرایشِ «%s»', 'zig3d-widgets'), $group['label']))
        );

        echo '<form method="post" id="zig3d-group-form">';
        wp_nonce_field(self::NONCE, self::NONCE . '_nonce');

        echo '<table class="form-table"><tbody>';

        self::field(
            __('شناسه', 'zig3d-widgets'),
            sprintf(
                '<input type="text" name="zig3d_group_name" value="%s" class="regular-text" pattern="[a-z0-9_\-]+" required%s>',
                esc_attr($name),
                $is_new ? '' : ' readonly'
            ),
            $is_new
                ? __('فقط حروف کوچک لاتین، عدد، خط تیره و زیرخط.', 'zig3d-widgets')
                : __('شناسه بعد از ساخت عوض نمی‌شود، چون دسته‌ها با همین به گروه وصل‌اند.', 'zig3d-widgets')
        );

        self::field(
            __('عنوانِ گروه', 'zig3d-widgets'),
            sprintf(
                '<input type="text" name="zig3d_group_label" value="%s" class="regular-text" placeholder="%s">',
                esc_attr($group['label']),
                esc_attr__('مثلاً «سیستم ماشین‌کاری»', 'zig3d-widgets')
            )
        );

        echo '</tbody></table>';

        self::render_items($group['items']);

        submit_button($is_new ? __('ساختنِ گروه', 'zig3d-widgets') : __('ذخیرهٔ گروه', 'zig3d-widgets'));

        printf('<a href="%s" class="button">%s</a>', esc_url(self::url()), esc_html__('انصراف', 'zig3d-widgets'));

        echo '</form></div>';

        self::render_script();
    }

    private static function render_items(array $items): void {
        echo '<h3>' . esc_html__('مشخصه‌ها', 'zig3d-widgets') . '</h3>';
        echo '<ul id="zig3d-spec-items" class="zig3d-spec-items">';

        foreach ($items as $i => $item) {
            self::render_item($i, $item);
        }

        echo '</ul>';

        printf(
            '<p><button type="button" class="button button-primary" id="zig3d-add-item">%s</button></p>',
            esc_html__('+ افزودنِ مشخصه', 'zig3d-widgets')
        );

        echo '<template id="zig3d-item-template">';
        self::render_item('__I__', []);
        echo '</template>';

        echo '<datalist id="zig3d-attr-options">';
        printf('<option value="%s" data-slug="custom">', esc_attr(self::CUSTOM_ATTRIBUTE_LABEL));
        foreach (Attributes::all() as $taxonomy) {
            printf('<option value="%s" data-slug="%s">', esc_attr(Attributes::label($taxonomy)), esc_attr($taxonomy));
        }
        echo '</datalist>';
    }

    /**
     * @param int|string $i
     */
    private static function render_item($i, array $item): void {
        $source           = $item['source'] ?? 'attribute';
        $attribute        = $item['attribute'] ?? 'custom';
        $custom_attribute = $item['custom_attribute'] ?? '';
        $meta_key         = $item['meta_key'] ?? '';
        $label            = $item['label'] ?? '';
        $name             = sprintf('zig3d_spec_items[%s]', $i);
        $attr_text        = 'custom' === $attribute ? self::CUSTOM_ATTRIBUTE_LABEL : Attributes::label($attribute);

        echo '<li class="zig3d-spec-row" draggable="true">';
        echo '<span class="zig3d-spec-row__handle dashicons dashicons-menu" aria-hidden="true"></span>';

        echo '<span class="zig3d-spec-row__field zig3d-spec-row__source">';
        echo '<select name="' . esc_attr($name) . '[source]" data-zig3d-source>';
        foreach (self::source_labels() as $value => $labelText) {
            printf('<option value="%s"%s>%s</option>', esc_attr($value), selected($value, $source, false), esc_html($labelText));
        }
        echo '</select>';
        echo '</span>';

        echo '<span class="zig3d-spec-row__field zig3d-spec-row__attr">';

        echo '<span data-zig3d-when="attribute"' . ('attribute' === $source ? '' : ' hidden') . '>';
        echo '<span class="zig3d-combobox" data-zig3d-combobox>';
        printf(
            '<input type="text" class="zig3d-combobox__input" list="zig3d-attr-options" value="%s" placeholder="%s" autocomplete="off">',
            esc_attr($attr_text),
            esc_attr__('نامِ ویژگی، یا «سفارشی»…', 'zig3d-widgets')
        );
        printf('<input type="hidden" name="%s[attribute]" value="%s" data-zig3d-combobox-value>', esc_attr($name), esc_attr($attribute));
        echo '</span>';
        printf(
            '<input type="text" name="%s[custom_attribute]" value="%s" placeholder="%s" data-zig3d-when="custom"%s>',
            esc_attr($name),
            esc_attr($custom_attribute),
            esc_attr__('نامِ ویژگیِ سفارشی', 'zig3d-widgets'),
            'custom' === $attribute ? '' : ' hidden'
        );
        echo '</span>';

        printf(
            '<input type="text" class="zig3d-spec-row__field" name="%s[meta_key]" value="%s" placeholder="%s" data-zig3d-when="custom_meta"%s>',
            esc_attr($name),
            esc_attr($meta_key),
            esc_attr__('کلیدِ متا', 'zig3d-widgets'),
            'custom_meta' === $source ? '' : ' hidden'
        );

        echo '</span>';

        printf(
            '<input type="text" class="zig3d-spec-row__field zig3d-spec-row__label" name="%s[label]" value="%s" placeholder="%s">',
            esc_attr($name),
            esc_attr($label),
            esc_attr__('عنوانِ دلخواه — خالی = پیش‌فرض', 'zig3d-widgets')
        );

        echo '<button type="button" class="button-link-delete zig3d-spec-row__remove" data-zig3d-remove-item title="'
            . esc_attr__('حذف', 'zig3d-widgets') . '"><span class="dashicons dashicons-trash"></span></button>';

        echo '</li>';
    }

    /** @return array<string,string> */
    private static function source_labels(): array {
        return [
            'attribute'   => __('ویژگی محصول', 'zig3d-widgets'),
            'category'    => __('دسته‌بندی', 'zig3d-widgets'),
            'tag'         => __('برچسب‌ها', 'zig3d-widgets'),
            'sku'         => __('شناسهٔ محصول (SKU)', 'zig3d-widgets'),
            'rating'      => __('امتیازِ خریداران', 'zig3d-widgets'),
            'stock'       => __('وضعیتِ موجودی', 'zig3d-widgets'),
            'weight'      => __('وزن', 'zig3d-widgets'),
            'dimensions'  => __('ابعاد', 'zig3d-widgets'),
            'custom_meta' => __('فیلدِ دلخواه (متا)', 'zig3d-widgets'),
        ];
    }

    /* =====================================================================
     * استایل و اسکریپت
     * =================================================================== */

    private static function render_styles(): void {
        ?>
        <style>
        .zig3d-spec-wrap .zig3d-spec-search { margin: 12px 0; max-width: 360px; }
        .zig3d-spec-wrap .zig3d-spec-search input { width: 100%; padding: 6px 10px; }
        .zig3d-spec-wrap tr.zig3d-row-active { background: #f0f6fc; }
        .zig3d-spec-wrap tr[hidden] { display: none; }
        .zig3d-spec-editor {
            background: #fff; border: 1px solid #dcdcde; border-radius: 6px;
            padding: 20px 24px; margin-top: 16px; max-width: 900px;
        }
        .zig3d-spec-items { list-style: none; margin: 8px 0; padding: 0; max-width: 100%; }
        .zig3d-spec-row {
            display: flex; align-items: center; gap: 8px; padding: 8px 6px;
            border: 1px solid #dcdcde; border-radius: 4px; background: #fbfbfc;
            margin-bottom: 6px; cursor: grab;
        }
        .zig3d-spec-row.zig3d-dragging { opacity: .4; }
        .zig3d-spec-row__handle { color: #8c8f94; cursor: grab; flex-shrink: 0; }
        .zig3d-spec-row__field { min-width: 0; }
        .zig3d-spec-row__source { flex: 0 0 150px; }
        .zig3d-spec-row__attr { flex: 1 1 220px; min-width: 0; }
        .zig3d-spec-row__attr input[type="text"] { width: 100%; }
        .zig3d-spec-row__label { flex: 1 1 200px; }
        .zig3d-spec-row__remove { flex-shrink: 0; color: #b32d2e; }
        .zig3d-combobox { position: relative; display: block; }
        .zig3d-combobox__input { width: 100%; }
        </style>
        <?php
    }

    /**
     * فقط دو رفتار: درگ‌اند‌دراپِ بومیِ ردیف‌ها (بدونِ کتابخانه)، و
     * نمایش/پنهانیِ فیلدهای وابسته به «مبدأ». شمارهٔ داخلِ ‎name‎ هرگز
     * بازشماری نمی‌شود؛ ردیفِ تازه شمارهٔ خودش را از ‎Date.now()‎ می‌گیرد.
     */
    private static function render_script(): void {
        ?>
        <script>
        (function () {
            'use strict';

            var LIST = document.getElementById('zig3d-spec-items');
            var TPL = document.getElementById('zig3d-item-template');

            function uid() {
                return 'n' + Date.now().toString(36) + Math.floor(Math.random() * 1e4).toString(36);
            }

            function wireSourceVisibility(row) {
                var select = row.querySelector('[data-zig3d-source]');
                if (!select) { return; }
                var sync = function () {
                    row.querySelectorAll('[data-zig3d-when]').forEach(function (el) {
                        el.hidden = el.getAttribute('data-zig3d-when') !== select.value;
                    });
                };
                select.addEventListener('change', sync);
                sync();
            }

            function wireCombobox(row) {
                var box = row.querySelector('[data-zig3d-combobox]');
                if (!box) { return; }
                var input = box.querySelector('.zig3d-combobox__input');
                var hidden = box.querySelector('[data-zig3d-combobox-value]');
                var list = document.getElementById('zig3d-attr-options');

                var sync = function () {
                    var text = input.value.trim();
                    // متنِ ورودی همیشه همان چیزی است که در دیتالیست دیده می‌شود
                    // («value» گزینه، نه «label» — مرورگرها بعدِ انتخاب همان را در
                    // فیلد می‌گذارند)، پس همان را برای پیداکردنِ اسلاگ جست‌وجو می‌کنیم.
                    var match = list && text ? list.querySelector('option[value="' + CSS.escape(text) + '"]') : null;
                    hidden.value = match ? match.getAttribute('data-slug') : 'custom';
                };

                input.addEventListener('change', sync);
                input.addEventListener('blur', sync);
            }

            function wireRow(row) {
                wireSourceVisibility(row);
                wireCombobox(row);

                var remove = row.querySelector('[data-zig3d-remove-item]');
                if (remove) {
                    remove.addEventListener('click', function () { row.remove(); });
                }

                row.addEventListener('dragstart', function (e) {
                    row.classList.add('zig3d-dragging');
                    e.dataTransfer.effectAllowed = 'move';
                });
                row.addEventListener('dragend', function () {
                    row.classList.remove('zig3d-dragging');
                });
            }

            function afterElement(container, y) {
                var rows = Array.prototype.filter.call(container.children, function (el) {
                    return el !== container.querySelector('.zig3d-dragging');
                });
                var closest = { offset: -Infinity, element: null };
                rows.forEach(function (el) {
                    var box = el.getBoundingClientRect();
                    var offset = y - box.top - box.height / 2;
                    if (offset < 0 && offset > closest.offset) {
                        closest = { offset: offset, element: el };
                    }
                });
                return closest.element;
            }

            function wireList(list) {
                list.addEventListener('dragover', function (e) {
                    e.preventDefault();
                    var dragging = list.querySelector('.zig3d-dragging');
                    if (!dragging) { return; }
                    var after = afterElement(list, e.clientY);
                    if (null === after) {
                        list.appendChild(dragging);
                    } else {
                        list.insertBefore(dragging, after);
                    }
                });
            }

            if (LIST && TPL) {
                LIST.querySelectorAll('.zig3d-spec-row').forEach(wireRow);
                wireList(LIST);

                var addBtn = document.getElementById('zig3d-add-item');
                if (addBtn) {
                    addBtn.addEventListener('click', function () {
                        var frag = TPL.content.cloneNode(true);
                        var row = frag.querySelector('.zig3d-spec-row');
                        row.querySelectorAll('[name]').forEach(function (el) {
                            el.setAttribute('name', el.getAttribute('name').replace('__I__', uid()));
                        });
                        LIST.appendChild(frag);
                        wireRow(row);
                    });
                }
            }

            var search = document.getElementById('zig3d-groups-search');
            var table = document.getElementById('zig3d-groups-table');
            if (search && table) {
                search.addEventListener('input', function () {
                    var q = search.value.trim().toLowerCase();
                    table.querySelectorAll('tbody tr').forEach(function (row) {
                        var hay = (row.getAttribute('data-zig3d-search') || '').toLowerCase();
                        row.hidden = q !== '' && hay.indexOf(q) === -1;
                    });
                });
            }
        })();
        </script>
        <?php
    }

    /* =====================================================================
     * ذخیره
     * =================================================================== */

    private static function handle_post(): string {
        if (!isset($_POST[self::NONCE . '_nonce'])) {
            return '';
        }

        $nonce = sanitize_text_field(wp_unslash($_POST[self::NONCE . '_nonce']));

        if (!wp_verify_nonce($nonce, self::NONCE) || !current_user_can('manage_woocommerce')) {
            return '';
        }

        if (isset($_POST['zig3d_group_delete'])) {
            return self::delete(sanitize_key(wp_unslash($_POST['zig3d_group_delete'])));
        }

        if (isset($_POST['zig3d_group_name'])) {
            return self::save();
        }

        return '';
    }

    private static function delete(string $name): string {
        if (!Spec_Store::delete_group($name)) {
            return '';
        }

        return __('گروه حذف شد. دسته‌هایی که به آن وصل بودند، دیگر نشانش نمی‌دهند.', 'zig3d-widgets');
    }

    private static function save(): string {
        $name = sanitize_key(wp_unslash($_POST['zig3d_group_name']));

        if ('' === $name) {
            return '';
        }

        $label = isset($_POST['zig3d_group_label'])
            ? sanitize_text_field(wp_unslash($_POST['zig3d_group_label']))
            : '';

        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $items = isset($_POST['zig3d_spec_items']) && is_array($_POST['zig3d_spec_items'])
            ? self::deep_sanitize(wp_unslash($_POST['zig3d_spec_items']))
            : [];

        if (!Spec_Store::save_group($name, ['label' => $label, 'items' => array_values($items)])) {
            return __('دست‌کم یک مشخصهٔ معتبر لازم است؛ گروهِ بی‌مشخصه ذخیره نمی‌شود.', 'zig3d-widgets');
        }

        return __('گروه ذخیره شد.', 'zig3d-widgets');
    }

    /** @return mixed */
    private static function deep_sanitize($value) {
        if (is_array($value)) {
            return array_map([self::class, 'deep_sanitize'], $value);
        }

        return sanitize_text_field((string) $value);
    }

    /* =====================================================================
     * کمکی‌ها
     * =================================================================== */

    private static function field(string $label, string $control, string $description = ''): void {
        printf('<tr><th scope="row">%s</th><td>%s', esc_html($label), $control);

        if ('' !== $description) {
            printf('<p class="description">%s</p>', esc_html($description));
        }

        echo '</td></tr>';
    }
}
