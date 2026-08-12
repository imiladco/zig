<?php
namespace Zig3d_Widgets\Admin;

use Zig3d_Widgets\Attributes;
use Zig3d_Widgets\Spec_Schema;
use Zig3d_Widgets\Spec_Store;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * صفحهٔ «قالب‌های مشخصات فنی» زیرِ منویِ محصولات.
 *
 * خواهرِ ‎Schemas_Page‎ است — همان ایده («یک قالبِ مشترک که چند دسته به آن
 * وصل می‌شوند»)، همان تصمیمِ حذف («اول بگو چند دسته وصل‌اند»)، همان انتخابِ
 * شناسه/برچسب. تنها فرقِ واقعی، شکلِ خودِ داده است: آنجا یک فهرستِ تختِ
 * تاکسونومی از میانِ مجموعه‌ای *معلوم و محدود* (همهٔ ویژگی‌های فروشگاه)
 * انتخاب می‌شد، پس یک جدولِ چک‌باکس کافی بود. اینجا هر گروه چند آیتمِ
 * دلخواه دارد و آیتم‌ها از نُه نوعِ مبدأ می‌آیند — یک درختِ *نامحدود*، نه
 * انتخاب از یک فهرستِ ثابت.
 *
 * همین یک تفاوت است که این صفحه را از قاعدهٔ «بدونِ جاوااسکریپت»یِ
 * ‎Schemas_Page‎ جدا می‌کند: افزودن/حذف/جابه‌جاییِ ردیف‌های نامحدود بدونِ
 * جاوااسکریپت یعنی یا سقفِ ثابتی روی تعدادِ آیتم‌ها (که یک روز کم می‌آید)،
 * یا رفرشِ کاملِ صفحه به‌ازای هر ردیف. اسکریپتِ اینجا کوچک و خودمختار است:
 * فقط کپی‌کردنِ یک ‎<template>‎ و جابه‌جاییِ DOM — هیچ کتابخانه‌ای، هیچ
 * ساخت (build) ای. شمارهٔ داخلِ ‎name="..."‎ هرگز عوض نمی‌شود؛ ترتیب فقط از
 * جای واقعیِ ردیف در DOM خوانده می‌شود، پس جابه‌جاکردن یعنی جابه‌جاکردنِ خودِ
 * عنصر، نه بازشماریِ index ها.
 */
final class Spec_Schemas_Page {

    private const SLUG  = 'zig3d-spec-schemas';
    private const NONCE = 'zig3d_spec_schemas';

    public static function boot(): void {
        add_action('admin_menu', [self::class, 'register'], 21);
    }

    public static function register(): void {
        add_submenu_page(
            'edit.php?post_type=product',
            __('قالب‌های مشخصات فنی', 'zig3d-widgets'),
            __('قالب‌های مشخصات فنی', 'zig3d-widgets'),
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

        $notice  = self::handle_post();
        $schemas = Spec_Store::schemas();

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $editing = isset($_GET['schema']) ? sanitize_key(wp_unslash($_GET['schema'])) : '';

        echo '<div class="wrap">';
        printf('<h1>%s</h1>', esc_html__('قالب‌های مشخصات فنی', 'zig3d-widgets'));

        printf(
            '<p class="description">%s</p>',
            esc_html__(
                'یک قالب، چند گروهِ آکاردئونی است — هر گروه با یک عنوان و چند مشخصه. چند دستهٔ محصول می‌توانند به یک قالب وصل شوند.',
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
                esc_html__('هنوز قالبی ساخته نشده. تا آن‌وقت هیچ دسته‌ای مشخصاتِ گروه‌بندی‌شده نشان نمی‌دهد.', 'zig3d-widgets')
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
            $using = Spec_Store::categories_using($name);
            $items = array_sum(array_map(static fn(array $g): int => count($g['items']), $schema['groups']));

            echo '<tr>';
            printf('<td><strong>%s</strong><br><code>%s</code></td>', esc_html($schema['label']), esc_html($name));

            printf(
                '<td>%s</td>',
                esc_html(sprintf(
                    /* translators: 1: تعداد گروه، 2: تعداد مشخصه */
                    _n('%1$d گروه (%2$d مشخصه)', '%1$d گروه (%2$d مشخصه)', count($schema['groups']), 'zig3d-widgets'),
                    count($schema['groups']),
                    $items
                ))
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
                __('این قالب در %d دسته استفاده شده. با حذفش، آن دسته‌ها بدونِ گروه‌بندی می‌مانند. ادامه؟', 'zig3d-widgets'),
                count($using)
            )
            : __('این قالب حذف شود؟', 'zig3d-widgets');

        echo '<form method="post" style="display:inline">';
        wp_nonce_field(self::NONCE, self::NONCE . '_nonce');
        printf('<input type="hidden" name="zig3d_spec_delete" value="%s">', esc_attr($name));
        printf(
            '<button type="submit" class="button-link delete" onclick="return confirm(%s)">%s</button>',
            esc_attr(wp_json_encode($warning)),
            esc_html__('حذف', 'zig3d-widgets')
        );
        echo '</form>';
    }

    private static function render_form(array $schemas, string $editing): void {
        $schema = $schemas[$editing] ?? null;
        $groups = $schema['groups'] ?? [];

        printf(
            '<h2>%s</h2>',
            $schema
                ? esc_html(sprintf(/* translators: %s: نام قالب */ __('ویرایش «%s»', 'zig3d-widgets'), $schema['label']))
                : esc_html__('قالب تازه', 'zig3d-widgets')
        );

        echo '<form method="post" id="zig3d-spec-form"><table class="form-table"><tbody>';

        wp_nonce_field(self::NONCE, self::NONCE . '_nonce');

        self::field(
            __('شناسه', 'zig3d-widgets'),
            sprintf(
                '<input type="text" name="zig3d_spec_name" value="%s" class="regular-text" pattern="[a-z0-9_\-]+" required%s>',
                esc_attr($editing),
                $schema ? ' readonly' : ''
            ),
            $schema
                ? __('شناسه بعد از ساخت عوض نمی‌شود، چون دسته‌ها با همین به قالب وصل‌اند.', 'zig3d-widgets')
                : __('فقط حروف کوچک لاتین، عدد، خط تیره و زیرخط.', 'zig3d-widgets')
        );

        self::field(
            __('نام نمایشی', 'zig3d-widgets'),
            sprintf(
                '<input type="text" name="zig3d_spec_label" value="%s" class="regular-text">',
                esc_attr($schema['label'] ?? '')
            )
        );

        echo '</tbody></table>';

        self::render_groups($groups);

        submit_button($schema ? __('ذخیرهٔ قالب', 'zig3d-widgets') : __('ساختن قالب', 'zig3d-widgets'));

        if ($schema) {
            printf('<a href="%s" class="button">%s</a>', esc_url(self::url()), esc_html__('انصراف', 'zig3d-widgets'));
        }

        echo '</form>';

        self::render_script();
    }

    /* =====================================================================
     * سازندهٔ گروه‌ها/آیتم‌ها
     * =================================================================== */

    private static function render_groups(array $groups): void {
        echo '<h2>' . esc_html__('گروه‌ها', 'zig3d-widgets') . '</h2>';
        echo '<div id="zig3d-spec-groups">';

        foreach ($groups as $g => $group) {
            self::render_group($g, $group);
        }

        echo '</div>';

        printf(
            '<p><button type="button" class="button button-primary" id="zig3d-spec-add-group">%s</button></p>',
            esc_html__('+ افزودنِ گروه', 'zig3d-widgets')
        );

        // قالبِ خامِ یک گروهِ تازه، برایِ کپی‌شدن با جاوااسکریپت
        echo '<template id="zig3d-spec-group-template">';
        self::render_group('__G__', ['label' => '', 'items' => []]);
        echo '</template>';

        // قالبِ خامِ یک آیتمِ تازه
        echo '<template id="zig3d-spec-item-template">';
        self::render_item('__G__', '__I__', []);
        echo '</template>';
    }

    /**
     * @param int|string $g
     * @param array{label:string,items:array} $group
     */
    private static function render_group($g, array $group): void {
        printf('<div class="zig3d-spec-group" style="border:1px solid #dcdcde;border-radius:4px;padding:12px;margin-bottom:12px;background:#fff">');

        echo '<p style="display:flex;gap:8px;align-items:center">';
        printf(
            '<input type="text" name="zig3d_spec_groups[%s][label]" value="%s" class="regular-text" placeholder="%s" style="flex:1">',
            esc_attr((string) $g),
            esc_attr($group['label'] ?? ''),
            esc_attr__('عنوانِ گروه — مثلاً «سیستم ماشین‌کاری»', 'zig3d-widgets')
        );
        echo '<button type="button" class="button" data-zig3d-move="up" title="' . esc_attr__('جابه‌جایی به بالا', 'zig3d-widgets') . '">▲</button>';
        echo '<button type="button" class="button" data-zig3d-move="down" title="' . esc_attr__('جابه‌جایی به پایین', 'zig3d-widgets') . '">▼</button>';
        echo '<button type="button" class="button-link-delete" data-zig3d-remove-group>' . esc_html__('حذفِ گروه', 'zig3d-widgets') . '</button>';
        echo '</p>';

        echo '<table class="widefat striped"><thead><tr>'
            . '<th style="width:14%">' . esc_html__('مبدأ', 'zig3d-widgets') . '</th>'
            . '<th>' . esc_html__('ویژگی / کلید', 'zig3d-widgets') . '</th>'
            . '<th style="width:20%">' . esc_html__('عنوانِ دلخواه', 'zig3d-widgets') . '</th>'
            . '<th style="width:110px"></th>'
            . '</tr></thead><tbody data-zig3d-items>';

        foreach ($group['items'] as $i => $item) {
            self::render_item($g, $i, $item);
        }

        echo '</tbody></table>';

        printf(
            '<p><button type="button" class="button" data-zig3d-add-item>%s</button></p>',
            esc_html__('+ افزودنِ مشخصه', 'zig3d-widgets')
        );

        echo '</div>';
    }

    /**
     * @param int|string $g
     * @param int|string $i
     */
    private static function render_item($g, $i, array $item): void {
        $source           = $item['source'] ?? 'attribute';
        $attribute        = $item['attribute'] ?? 'custom';
        $custom_attribute = $item['custom_attribute'] ?? '';
        $meta_key         = $item['meta_key'] ?? '';
        $label            = $item['label'] ?? '';
        $name             = sprintf('zig3d_spec_groups[%s][items][%s]', $g, $i);

        echo '<tr class="zig3d-spec-item">';

        echo '<td>';
        echo '<select name="' . esc_attr($name) . '[source]" data-zig3d-source>';
        foreach (self::source_labels() as $value => $labelText) {
            printf('<option value="%s"%s>%s</option>', esc_attr($value), selected($value, $source, false), esc_html($labelText));
        }
        echo '</select>';
        echo '</td>';

        echo '<td>';

        echo '<span data-zig3d-when="attribute"' . ('attribute' === $source ? '' : ' style="display:none"') . '>';
        echo '<select name="' . esc_attr($name) . '[attribute]" data-zig3d-attribute style="width:100%">';
        printf('<option value="custom"%s>%s</option>', selected('custom', $attribute, false), esc_html__('ویژگی سفارشی (غیرسراسری)', 'zig3d-widgets'));
        foreach (Attributes::all() as $taxonomy) {
            printf('<option value="%s"%s>%s</option>', esc_attr($taxonomy), selected($taxonomy, $attribute, false), esc_html(Attributes::label($taxonomy)));
        }
        echo '</select>';
        printf(
            '<input type="text" name="%s[custom_attribute]" value="%s" placeholder="%s" style="width:100%%;margin-top:4px"%s>',
            esc_attr($name),
            esc_attr($custom_attribute),
            esc_attr__('نامِ ویژگیِ سفارشی، همان‌طور که در تبِ «ویژگی‌ها»ی محصول نوشته شده', 'zig3d-widgets'),
            'custom' === $attribute ? '' : ' style="display:none"'
        );
        echo '</span>';

        printf(
            '<input type="text" name="%s[meta_key]" value="%s" placeholder="%s" style="width:100%%" data-zig3d-when="custom_meta"%s>',
            esc_attr($name),
            esc_attr($meta_key),
            esc_attr__('کلیدِ متا', 'zig3d-widgets'),
            'custom_meta' === $source ? '' : ' style="display:none"'
        );

        echo '</td>';

        printf(
            '<td><input type="text" name="%s[label]" value="%s" placeholder="%s" style="width:100%%"></td>',
            esc_attr($name),
            esc_attr($label),
            esc_attr__('خالی = عنوانِ پیش‌فرض', 'zig3d-widgets')
        );

        echo '<td style="white-space:nowrap">';
        echo '<button type="button" class="button" data-zig3d-move="up" title="' . esc_attr__('بالا', 'zig3d-widgets') . '">▲</button> ';
        echo '<button type="button" class="button" data-zig3d-move="down" title="' . esc_attr__('پایین', 'zig3d-widgets') . '">▼</button> ';
        echo '<button type="button" class="button-link-delete" data-zig3d-remove-item>' . esc_html__('حذف', 'zig3d-widgets') . '</button>';
        echo '</td>';

        echo '</tr>';
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

    /**
     * اسکریپتِ خودمختارِ سازنده: کپی‌کردنِ ‎<template>‎، جابه‌جاییِ DOM، و
     * نمایش/پنهانیِ فیلدهای وابسته به «مبدأ». شمارهٔ ‎[%s]‎ در ‎name‎ها
     * هرگز عوض نمی‌شود — یک ردیفِ تازه شمارهٔ خودش را از ‎Date.now()‎ می‌گیرد
     * و دیگر دست نمی‌خورد؛ ترتیب فقط از جای واقعیِ ردیف در DOM خوانده
     * می‌شود، پس جابه‌جاکردن یعنی جابه‌جاکردنِ خودِ عنصر.
     */
    private static function render_script(): void {
        ?>
        <script>
        (function () {
            'use strict';

            var GROUPS = document.getElementById('zig3d-spec-groups');
            var GROUP_TPL = document.getElementById('zig3d-spec-group-template');
            var ITEM_TPL = document.getElementById('zig3d-spec-item-template');

            if (!GROUPS || !GROUP_TPL || !ITEM_TPL) {
                return;
            }

            function uid() {
                return 'n' + Date.now().toString(36) + Math.floor(Math.random() * 1e4).toString(36);
            }

            function renameAttr(el, attr, group, item) {
                var value = el.getAttribute(attr);
                if (null === value) {
                    return;
                }
                value = value.replace('__G__', group);
                if (null !== item) {
                    value = value.replace('__I__', item);
                }
                el.setAttribute(attr, value);
            }

            function stampIds(root, group, item) {
                root.querySelectorAll('[name]').forEach(function (el) {
                    renameAttr(el, 'name', group, item);
                });
            }

            function applySourceVisibility(row) {
                var select = row.querySelector('[data-zig3d-source]');
                if (!select) {
                    return;
                }

                var sync = function () {
                    var value = select.value;
                    row.querySelectorAll('[data-zig3d-when]').forEach(function (el) {
                        el.style.display = el.getAttribute('data-zig3d-when') === value ? '' : 'none';
                    });
                };

                select.addEventListener('change', sync);
                sync();
            }

            function applyAttributeVisibility(row) {
                var select = row.querySelector('[data-zig3d-attribute]');
                if (!select) {
                    return;
                }

                var custom = row.querySelector('input[name*="[custom_attribute]"]');
                if (!custom) {
                    return;
                }

                var sync = function () {
                    custom.style.display = 'custom' === select.value ? '' : 'none';
                };

                select.addEventListener('change', sync);
                sync();
            }

            function wireItemRow(row) {
                applySourceVisibility(row);
                applyAttributeVisibility(row);

                row.querySelectorAll('[data-zig3d-move]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        move(row, btn.getAttribute('data-zig3d-move'));
                    });
                });

                var remove = row.querySelector('[data-zig3d-remove-item]');
                if (remove) {
                    remove.addEventListener('click', function () {
                        row.remove();
                    });
                }
            }

            function wireGroup(group) {
                group.querySelectorAll(':scope > p [data-zig3d-move]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        move(group, btn.getAttribute('data-zig3d-move'));
                    });
                });

                var remove = group.querySelector('[data-zig3d-remove-group]');
                if (remove) {
                    remove.addEventListener('click', function () {
                        group.remove();
                    });
                }

                var addItem = group.querySelector('[data-zig3d-add-item]');
                if (addItem) {
                    addItem.addEventListener('click', function () {
                        addItemTo(group);
                    });
                }

                group.querySelectorAll('.zig3d-spec-item').forEach(wireItemRow);
            }

            function move(el, direction) {
                if ('up' === direction && el.previousElementSibling) {
                    el.parentNode.insertBefore(el, el.previousElementSibling);
                } else if ('down' === direction && el.nextElementSibling) {
                    el.parentNode.insertBefore(el.nextElementSibling, el);
                }
            }

            function addItemTo(group) {
                var body = group.querySelector('[data-zig3d-items]');
                var groupId = group.getAttribute('data-zig3d-gid');
                var frag = ITEM_TPL.content.cloneNode(true);
                var row = frag.querySelector('.zig3d-spec-item');

                stampIds(frag, groupId, uid());
                body.appendChild(frag);
                wireItemRow(row);
            }

            function addGroup() {
                var gid = uid();
                var frag = GROUP_TPL.content.cloneNode(true);
                var group = frag.querySelector('.zig3d-spec-group');

                group.setAttribute('data-zig3d-gid', gid);
                stampIds(frag, gid, null);
                GROUPS.appendChild(frag);
                wireGroup(group);
            }

            GROUPS.querySelectorAll('.zig3d-spec-group').forEach(function (group, index) {
                // گروه‌های موجود شناسهٔ عددیِ خودشان (اندیسِ PHP) را دارند
                var input = group.querySelector('input[name^="zig3d_spec_groups["]');
                var match = input && input.name.match(/zig3d_spec_groups\[([^\]]+)\]/);
                group.setAttribute('data-zig3d-gid', match ? match[1] : String(index));
                wireGroup(group);
            });

            var addBtn = document.getElementById('zig3d-spec-add-group');
            if (addBtn) {
                addBtn.addEventListener('click', addGroup);
            }
        })();
        </script>
        <?php
    }

    /* =====================================================================
     * ذخیره
     * =================================================================== */

    /** @return string پیامِ موفقیت، یا رشتهٔ خالی */
    private static function handle_post(): string {
        if (!isset($_POST[self::NONCE . '_nonce'])) {
            return '';
        }

        $nonce = sanitize_text_field(wp_unslash($_POST[self::NONCE . '_nonce']));

        if (!wp_verify_nonce($nonce, self::NONCE) || !current_user_can('manage_woocommerce')) {
            return '';
        }

        if (isset($_POST['zig3d_spec_delete'])) {
            return self::delete(sanitize_key(wp_unslash($_POST['zig3d_spec_delete'])));
        }

        return self::save();
    }

    private static function delete(string $name): string {
        $schemas = Spec_Store::schemas();

        if (!isset($schemas[$name])) {
            return '';
        }

        unset($schemas[$name]);
        Spec_Store::save_schemas($schemas);

        /*
         * ارجاعِ همان قالب در دسته‌ها پاک نمی‌شود، عمداً — همان دلیلِ
         * ‎Schemas_Page‎: اگر مدیر اشتباهی حذف کرده و دوباره با همان شناسه
         * بسازد، همه‌چیز سرِ جایش برمی‌گردد.
         */
        return __('قالب حذف شد. دسته‌هایی که به آن وصل بودند تا ساختِ دوباره‌اش بدونِ گروه‌بندی می‌مانند.', 'zig3d-widgets');
    }

    private static function save(): string {
        $name = isset($_POST['zig3d_spec_name'])
            ? sanitize_key(wp_unslash($_POST['zig3d_spec_name']))
            : '';

        if ('' === $name) {
            return '';
        }

        $groups = self::posted_groups();

        if (!Spec_Schema::sanitize_groups($groups)) {
            return __('دست‌کم یک گروه با یک مشخصه لازم است؛ قالبِ خالی ذخیره نمی‌شود.', 'zig3d-widgets');
        }

        $schemas = Spec_Store::schemas();

        $schemas[$name] = [
            'label'  => isset($_POST['zig3d_spec_label'])
                ? sanitize_text_field(wp_unslash($_POST['zig3d_spec_label']))
                : '',
            'groups' => $groups,
        ];

        Spec_Store::save_schemas($schemas);

        return __('قالب ذخیره شد.', 'zig3d-widgets');
    }

    /**
     * خواندنِ آرایهٔ تودرتویِ فرم. پی‌اچ‌پی خودش ‎name="a[x][items][y][z]"‎
     * را به ‎$_POST['a']['x']['items']['y']['z']‎ تبدیل می‌کند؛ اینجا فقط
     * پاک‌سازیِ سطحیِ رشته‌ها لازم است، بقیه را ‎Spec_Schema::sanitize_groups‎
     * انجام می‌دهد.
     *
     * @return array<int|string,array>
     */
    private static function posted_groups(): array {
        if (!isset($_POST['zig3d_spec_groups']) || !is_array($_POST['zig3d_spec_groups'])) {
            return [];
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        return self::deep_sanitize(wp_unslash($_POST['zig3d_spec_groups']));
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
