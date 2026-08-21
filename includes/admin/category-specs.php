<?php
namespace Zig3d_Widgets\Admin;

use Zig3d_Widgets\Spec_Store;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * بخش «گروه‌های مشخصاتِ این دسته» در صفحهٔ ویرایشِ دستهٔ محصول.
 *
 * خواهرِ ‎Category_Filters‎ (همان‌جا: دسته یک چیز است، تنظیمش هم یک‌جا
 * می‌ماند)، ولی رابطش برایِ مقیاس ساخته شده: یک فروشگاهِ بزرگ ده‌ها گروه
 * دارد، و انتخاب/ترتیب‌دادنِ آن‌ها با یک چک‌باکسِ سادهٔ کشویی خسته‌کننده
 * می‌شود. اینجا یک فهرستِ واحد است — همهٔ گروه‌ها، با چک‌باکس، جست‌وجوپذیر و
 * درگ‌اند‌دراپ برایِ ترتیب.
 *
 * نکتهٔ فنی: هیچ فیلدِ «ترتیب» ی در کار نیست. مرورگر چک‌باکس‌های هم‌نام را
 * دقیقاً به‌ترتیبِ ظاهرشدنشان در DOM ارسال می‌کند — پس جابه‌جاکردنِ خودِ
 * ردیف‌ها (با درگ) دقیقاً همان کاری‌ست که لازم است، بدونِ هیچ شمارهٔ
 * جداگانه‌ای برایِ همگام نگه‌داشتن. بدونِ جاوااسکریپت هم فرم کار می‌کند —
 * فقط بدونِ جابه‌جایی، با همان ترتیبی که سرور رندر کرده (گروه‌هایِ
 * انتخاب‌شده اول، به ترتیبِ فعلی‌شان؛ بقیه بعدش، به ترتیبِ الفبا).
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

        $library  = Spec_Store::groups();
        $selected = Spec_Store::category_groups((int) $term->term_id);
        $resolved = Spec_Store::resolve((int) $term->term_id);

        echo '<tr class="form-field"><th scope="row"><label>'
            . esc_html__('گروه‌های مشخصاتِ این دسته', 'zig3d-widgets')
            . '</label></th><td>';

        wp_nonce_field(self::NONCE, self::NONCE . '_nonce');

        if (!$library) {
            printf(
                '<p class="description">%s <a href="%s">%s</a></p>',
                esc_html__('هنوز گروهی ساخته نشده.', 'zig3d-widgets'),
                esc_url(Spec_Groups_Page::url()),
                esc_html__('ساختنِ اولین گروه', 'zig3d-widgets')
            );

            echo '</td></tr>';

            return;
        }

        self::render_styles();
        self::render_picker($library, $selected);

        foreach ($resolved['notes'] as $note) {
            printf('<p class="description" style="color:#b32d2e">%s</p>', esc_html($note));
        }

        printf(
            '<p class="description"><a href="%s">%s</a></p>',
            esc_url(Spec_Groups_Page::url()),
            esc_html__('مدیریتِ گروه‌های مشخصات فنی', 'zig3d-widgets')
        );

        echo '</td></tr>';
    }

    private static function render_styles(): void {
        ?>
        <style>
        .zig3d-cat-specs { max-width: 480px; }
        .zig3d-cat-specs__search { margin-bottom: 8px; }
        .zig3d-cat-specs__search input { width: 100%; padding: 6px 10px; }
        .zig3d-cat-specs__list { list-style: none; margin: 0; padding: 0; max-height: 360px; overflow-y: auto; border: 1px solid #dcdcde; border-radius: 4px; }
        .zig3d-cat-specs__row {
            display: flex; align-items: center; gap: 8px; padding: 7px 10px;
            border-bottom: 1px solid #f0f0f1; background: #fff; cursor: grab;
        }
        .zig3d-cat-specs__row:last-child { border-bottom: 0; }
        .zig3d-cat-specs__row.zig3d-cat-specs__row--on { background: #f0f6fc; }
        .zig3d-cat-specs__row.zig3d-dragging { opacity: .4; }
        .zig3d-cat-specs__handle { color: #8c8f94; flex-shrink: 0; }
        .zig3d-cat-specs__row label { flex: 1; display: flex; align-items: baseline; gap: 6px; cursor: pointer; }
        .zig3d-cat-specs__meta { color: #757575; font-size: 12px; }
        </style>
        <?php
    }

    /**
     * @param array<string,array{label:string,items:array}> $library
     * @param string[] $selected
     */
    private static function render_picker(array $library, array $selected): void {
        // انتخاب‌شده‌ها اول، به همان ترتیبِ فعلی؛ بقیه بعدش، به ترتیبِ الفبا
        $rest = array_diff(array_keys($library), $selected);
        usort($rest, static fn(string $a, string $b): int => strcmp($library[$a]['label'], $library[$b]['label']));

        $order = array_merge(array_values(array_intersect($selected, array_keys($library))), $rest);

        echo '<div class="zig3d-cat-specs">';
        echo '<div class="zig3d-cat-specs__search"><input type="text" id="zig3d-cat-specs-search" placeholder="'
            . esc_attr__('جست‌وجو…', 'zig3d-widgets') . '"></div>';

        echo '<ul class="zig3d-cat-specs__list" id="zig3d-cat-specs-list">';

        foreach ($order as $name) {
            $group = $library[$name];
            $on    = in_array($name, $selected, true);

            printf(
                '<li class="zig3d-cat-specs__row%s" draggable="true" data-zig3d-search="%s">',
                $on ? ' zig3d-cat-specs__row--on' : '',
                esc_attr($group['label'] . ' ' . $name)
            );
            echo '<span class="zig3d-cat-specs__handle dashicons dashicons-menu" aria-hidden="true"></span>';
            echo '<label>';
            printf(
                '<input type="checkbox" name="zig3d_spec_groups[]" value="%s"%s data-zig3d-toggle>',
                esc_attr($name),
                $on ? ' checked' : ''
            );
            printf('<span>%s</span>', esc_html($group['label']));
            printf(
                '<span class="zig3d-cat-specs__meta">%s</span>',
                esc_html(sprintf(
                    /* translators: %d: تعداد مشخصه */
                    _n('%d مشخصه', '%d مشخصه', count($group['items']), 'zig3d-widgets'),
                    count($group['items'])
                ))
            );
            echo '</label>';
            echo '</li>';
        }

        echo '</ul>';
        echo '<p class="description">' . esc_html__('برای ترتیب، ردیف‌ها را بکشید. فقط تیک‌خورده‌ها روی صفحهٔ محصول نمایش داده می‌شوند.', 'zig3d-widgets') . '</p>';
        echo '</div>';

        self::render_script();
    }

    /**
     * درگ‌اند‌دراپِ بومی برایِ ترتیب، جست‌وجویِ زنده، و جابه‌جاکردنِ خودکارِ
     * ردیف به بالای فهرست وقتی تیک می‌خورد (تا انتخاب‌شده‌ها همیشه یک‌جا و
     * بالایِ فهرست بمانند، حتی وقتی جست‌وجو خالی می‌شود).
     */
    private static function render_script(): void {
        ?>
        <script>
        (function () {
            'use strict';

            var LIST = document.getElementById('zig3d-cat-specs-list');
            if (!LIST) { return; }

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

            LIST.addEventListener('dragover', function (e) {
                e.preventDefault();
                var dragging = LIST.querySelector('.zig3d-dragging');
                if (!dragging) { return; }
                var after = afterElement(LIST, e.clientY);
                if (null === after) {
                    LIST.appendChild(dragging);
                } else {
                    LIST.insertBefore(dragging, after);
                }
            });

            LIST.querySelectorAll('.zig3d-cat-specs__row').forEach(function (row) {
                row.addEventListener('dragstart', function (e) {
                    row.classList.add('zig3d-dragging');
                    e.dataTransfer.effectAllowed = 'move';
                });
                row.addEventListener('dragend', function () {
                    row.classList.remove('zig3d-dragging');
                });

                var checkbox = row.querySelector('[data-zig3d-toggle]');
                checkbox.addEventListener('change', function () {
                    row.classList.toggle('zig3d-cat-specs__row--on', checkbox.checked);
                    if (checkbox.checked) {
                        // به بالای بخشِ «انتخاب‌شده‌ها» — یعنی زیرِ آخرین ردیفِ تیک‌خورده
                        var rows = Array.prototype.filter.call(LIST.children, function (el) { return el !== row; });
                        var lastOn = null;
                        rows.forEach(function (el) {
                            if (el.classList.contains('zig3d-cat-specs__row--on')) { lastOn = el; }
                        });
                        if (lastOn) {
                            lastOn.after(row);
                        } else {
                            LIST.prepend(row);
                        }
                    }
                });
            });

            var search = document.getElementById('zig3d-cat-specs-search');
            if (search) {
                search.addEventListener('input', function () {
                    var q = search.value.trim().toLowerCase();
                    LIST.querySelectorAll('.zig3d-cat-specs__row').forEach(function (row) {
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

    public static function save(int $term_id): void {
        if (!isset($_POST[self::NONCE . '_nonce'])) {
            return;
        }

        $nonce = sanitize_text_field(wp_unslash($_POST[self::NONCE . '_nonce']));

        if (!wp_verify_nonce($nonce, self::NONCE) || !current_user_can('manage_product_terms')) {
            return;
        }

        $names = self::posted_list('zig3d_spec_groups');

        Spec_Store::save_category_groups($term_id, $names);
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
}
