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
 * یک صفحهٔ واحد، نه یک فهرست به‌علاوهٔ یک فرمِ ویرایشِ جدا: همهٔ گروه‌ها
 * همین‌جا، به‌شکلِ گریدی از جعبه‌ها، هم‌زمان دیده و ویرایش می‌شوند. هر
 * جعبه یک گروه است — عنوان + مشخصه‌ها — و کلِ گرید با درگ‌اند‌دراپِ بومی
 * قابلِ چیدمان است: جعبه‌ها را جابه‌جا کن تا ترتیبِ گروه‌ها عوض شود،
 * داخلِ هر جعبه مشخصه‌ها را جابه‌جا کن تا ترتیبِ همان گروه عوض شود.
 * دکمهٔ «ذخیره» یکی است، برایِ کلِ گرید — نه یک رفت‌وبرگشتِ صفحه به‌ازایِ
 * هر گروه.
 *
 * نسخهٔ قبلی این صفحه را دو تکه کرده بود (فهرست، و زیرِ آن یک فرمِ ویرایشِ
 * تک‌گروهی که با ‎?group=‎ باز می‌شد) — همان چیزی که «خرابش کرد»: برایِ
 * دیدنِ همهٔ گروه‌ها یک‌جا، یا چیدنشان کنارِ هم، باید بینِ صفحه‌ها رفت‌وآمد
 * می‌شد. اینجا هیچ ناوبریِ دومی نیست.
 */
final class Spec_Groups_Page {

    private const SLUG  = 'zig3d-spec-groups';
    private const NONCE = 'zig3d_spec_groups';

    /** متنِ گزینهٔ «سفارشی» در کمبوباکسِ انتخابِ ویژگی */
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

    public static function url(): string {
        return add_query_arg(['post_type' => 'product', 'page' => self::SLUG], admin_url('edit.php'));
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

        self::render_styles();

        echo '<div class="wrap zig3d-spec-wrap">';
        printf('<h1>%s</h1>', esc_html__('گروه‌های مشخصات فنی', 'zig3d-widgets'));

        printf(
            '<p class="description">%s</p>',
            esc_html__(
                'هر جعبه یک گروهِ آکاردئونی است — یک عنوان و چند مشخصه. جعبه‌ها را بکشید تا ترتیبشان عوض شود؛ هر دستهٔ محصول از صفحهٔ ویرایشِ خودش چند گروه از همین فهرست را انتخاب می‌کند.',
                'zig3d-widgets'
            )
        );

        if ('' !== $notice) {
            printf('<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html($notice));
        }

        self::render_grid($groups);

        echo '</div>';
    }

    private static function render_grid(array $groups): void {
        echo '<div class="zig3d-spec-search"><input type="text" id="zig3d-spec-search" placeholder="'
            . esc_attr__('جست‌وجو در گروه‌ها…', 'zig3d-widgets') . '"></div>';

        echo '<form method="post" id="zig3d-spec-form">';
        wp_nonce_field(self::NONCE, self::NONCE . '_nonce');

        echo '<div class="zig3d-spec-grid" id="zig3d-spec-grid">';

        foreach ($groups as $name => $group) {
            self::render_box($name, $group, count(Spec_Store::categories_using($name)));
        }

        echo '<button type="button" class="zig3d-spec-box zig3d-spec-box--add" id="zig3d-add-box">'
            . '<span class="dashicons dashicons-plus-alt2"></span> ' . esc_html__('گروهِ تازه', 'zig3d-widgets')
            . '</button>';

        echo '</div>';

        printf(
            '<p class="zig3d-spec-save"><button type="submit" class="button button-primary button-hero">%s</button></p>',
            esc_html__('ذخیرهٔ همه', 'zig3d-widgets')
        );

        echo '</form>';

        self::render_box_template();
        self::render_item_datalist();
        self::render_script();
    }

    /**
     * @param int|string $box_id شناسهٔ نمایشیِ جعبه در DOM (نه نامِ ذخیره‌شده — آن در ‎existing_name‎ است)
     */
    private static function render_box(string $existing_name, array $group, int $usage): void {
        $box_id = 'b' . preg_replace('/[^a-z0-9]/', '', $existing_name ?: uniqid());

        printf(
            '<div class="zig3d-spec-box" draggable="true" data-usage="%d">',
            $usage
        );

        echo '<div class="zig3d-spec-box__head">';
        echo '<span class="zig3d-spec-box__handle dashicons dashicons-move" aria-hidden="true" title="'
            . esc_attr__('برایِ جابه‌جاییِ گروه بکشید', 'zig3d-widgets') . '"></span>';
        printf(
            '<input type="text" class="zig3d-spec-box__title" name="zig3d_groups[%s][label]" value="%s" placeholder="%s">',
            esc_attr($box_id),
            esc_attr($group['label'] ?? ''),
            esc_attr__('عنوانِ گروه', 'zig3d-widgets')
        );
        echo '<button type="button" class="zig3d-spec-box__remove" data-zig3d-remove-box title="' . esc_attr__('حذفِ گروه', 'zig3d-widgets') . '">'
            . '<span class="dashicons dashicons-trash"></span></button>';
        echo '</div>';

        printf('<input type="hidden" name="zig3d_groups[%s][existing_name]" value="%s">', esc_attr($box_id), esc_attr($existing_name));

        if ($usage > 0) {
            printf(
                '<div class="zig3d-spec-box__usage">%s</div>',
                esc_html(sprintf(
                    /* translators: %d: تعداد دسته */
                    _n('در %d دسته استفاده می‌شود', 'در %d دسته استفاده می‌شود', $usage, 'zig3d-widgets'),
                    $usage
                ))
            );
        }

        echo '<ul class="zig3d-spec-box__items" data-zig3d-items>';
        foreach (($group['items'] ?? []) as $i => $item) {
            self::render_item($box_id, $i, $item);
        }
        echo '</ul>';

        printf(
            '<button type="button" class="zig3d-spec-box__add-item" data-zig3d-add-item>%s</button>',
            esc_html__('+ مشخصه', 'zig3d-widgets')
        );

        echo '</div>';
    }

    /**
     * @param string     $box_id
     * @param int|string $i
     */
    private static function render_item(string $box_id, $i, array $item): void {
        $source           = $item['source'] ?? 'attribute';
        $attribute        = $item['attribute'] ?? 'custom';
        $custom_attribute = $item['custom_attribute'] ?? '';
        $meta_key         = $item['meta_key'] ?? '';
        $label            = $item['label'] ?? '';
        $name             = sprintf('zig3d_groups[%s][items][%s]', $box_id, $i);
        $attr_text        = 'custom' === $attribute ? self::CUSTOM_ATTRIBUTE_LABEL : Attributes::label($attribute);

        echo '<li class="zig3d-spec-row" draggable="true">';
        echo '<span class="zig3d-spec-row__handle dashicons dashicons-menu" aria-hidden="true"></span>';

        echo '<select name="' . esc_attr($name) . '[source]" class="zig3d-spec-row__source" data-zig3d-source>';
        foreach (self::source_labels() as $value => $labelText) {
            printf('<option value="%s"%s>%s</option>', esc_attr($value), selected($value, $source, false), esc_html($labelText));
        }
        echo '</select>';

        echo '<span class="zig3d-spec-row__attr">';
        echo '<span data-zig3d-when="attribute"' . ('attribute' === $source ? '' : ' hidden') . '>';
        printf(
            '<input type="text" class="zig3d-combobox__input" list="zig3d-attr-options" value="%s" placeholder="%s" autocomplete="off">',
            esc_attr($attr_text),
            esc_attr__('نامِ ویژگی…', 'zig3d-widgets')
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
        printf(
            '<input type="text" name="%s[meta_key]" value="%s" placeholder="%s" data-zig3d-when="custom_meta"%s>',
            esc_attr($name),
            esc_attr($meta_key),
            esc_attr__('کلیدِ متا', 'zig3d-widgets'),
            'custom_meta' === $source ? '' : ' hidden'
        );
        echo '</span>';

        printf(
            '<input type="text" class="zig3d-spec-row__label" name="%s[label]" value="%s" placeholder="%s">',
            esc_attr($name),
            esc_attr($label),
            esc_attr__('عنوانِ دلخواه', 'zig3d-widgets')
        );

        echo '<button type="button" class="zig3d-spec-row__remove" data-zig3d-remove-item title="' . esc_attr__('حذف', 'zig3d-widgets') . '">'
            . '<span class="dashicons dashicons-no-alt"></span></button>';

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

    /** جعبه/ردیفِ خام، برایِ کپی‌شدن با جاوااسکریپت */
    private static function render_box_template(): void {
        echo '<template id="zig3d-box-template">';
        self::render_box('', ['label' => '', 'items' => []], 0);
        echo '</template>';

        echo '<template id="zig3d-item-template">';
        self::render_item('__B__', '__I__', []);
        echo '</template>';
    }

    private static function render_item_datalist(): void {
        echo '<datalist id="zig3d-attr-options">';
        printf('<option value="%s" data-slug="custom">', esc_attr(self::CUSTOM_ATTRIBUTE_LABEL));
        foreach (Attributes::all() as $taxonomy) {
            printf('<option value="%s" data-slug="%s">', esc_attr(Attributes::label($taxonomy)), esc_attr($taxonomy));
        }
        echo '</datalist>';
    }

    /* =====================================================================
     * استایل
     * =================================================================== */

    private static function render_styles(): void {
        ?>
        <style>
        .zig3d-spec-wrap .zig3d-spec-search { margin: 14px 0; max-width: 360px; }
        .zig3d-spec-wrap .zig3d-spec-search input { width: 100%; padding: 6px 10px; }
        .zig3d-spec-grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 16px; align-items: start;
        }
        .zig3d-spec-box {
            background: #fff; border: 1px solid #dcdcde; border-radius: 6px; padding: 14px;
            cursor: grab;
        }
        .zig3d-spec-box.zig3d-dragging { opacity: .35; }
        .zig3d-spec-box.zig3d-drop-target { outline: 2px dashed #2271b1; outline-offset: 2px; }
        .zig3d-spec-box[hidden] { display: none; }
        .zig3d-spec-box__head { display: flex; align-items: center; gap: 6px; margin-bottom: 4px; }
        .zig3d-spec-box__handle { color: #8c8f94; flex-shrink: 0; cursor: grab; }
        .zig3d-spec-box__title { flex: 1; font-weight: 600; border: 1px solid transparent; padding: 4px 6px; border-radius: 3px; background: transparent; }
        .zig3d-spec-box__title:hover, .zig3d-spec-box__title:focus { border-color: #dcdcde; background: #fbfbfc; }
        .zig3d-spec-box__remove { flex-shrink: 0; border: 0; background: none; color: #b32d2e; cursor: pointer; padding: 2px; }
        .zig3d-spec-box__usage { font-size: 11px; color: #757575; margin-bottom: 6px; }
        .zig3d-spec-box__items { list-style: none; margin: 6px 0; padding: 0; }
        .zig3d-spec-row {
            display: flex; align-items: center; gap: 4px; padding: 4px;
            border: 1px solid #f0f0f1; border-radius: 3px; background: #fbfbfc;
            margin-bottom: 4px; cursor: grab; flex-wrap: wrap;
        }
        .zig3d-spec-row.zig3d-dragging { opacity: .35; }
        .zig3d-spec-row__handle { color: #b5b5b5; flex-shrink: 0; }
        .zig3d-spec-row__source { flex: 1 1 100%; font-size: 12px; }
        .zig3d-spec-row__attr { flex: 1 1 100%; display: flex; gap: 4px; }
        .zig3d-spec-row__attr input { flex: 1; min-width: 0; font-size: 12px; }
        .zig3d-spec-row__label { flex: 1 1 100%; font-size: 12px; }
        .zig3d-spec-row__remove { flex-shrink: 0; border: 0; background: none; color: #b32d2e; cursor: pointer; }
        .zig3d-spec-box__add-item { width: 100%; border: 1px dashed #dcdcde; background: none; border-radius: 3px; padding: 4px; cursor: pointer; color: #2271b1; font-size: 12px; }
        .zig3d-spec-box--add {
            display: flex; align-items: center; justify-content: center; gap: 6px;
            border: 2px dashed #c3c4c7; background: none; color: #2271b1; cursor: pointer;
            min-height: 80px; font-size: 14px;
        }
        .zig3d-spec-save { margin-top: 20px; }
        </style>
        <?php
    }

    /* =====================================================================
     * اسکریپت
     * =================================================================== */

    private static function render_script(): void {
        ?>
        <script>
        (function () {
            'use strict';

            var GRID = document.getElementById('zig3d-spec-grid');
            var BOX_TPL = document.getElementById('zig3d-box-template');
            var ITEM_TPL = document.getElementById('zig3d-item-template');
            if (!GRID) { return; }

            function uid() {
                return Date.now().toString(36) + Math.floor(Math.random() * 1e4).toString(36);
            }

            /*
             * جابه‌جاکردنِ عنصرِ درگ‌شونده تا کنارِ هدف — نه همیشه «قبلش».
             * اگر فقط ‎insertBefore(dragging, target)‎ صدا زده شود، کشیدن رو
             * به جلو (روی همسایهٔ بعدی) هیچ اثری ندارد: عنصر همین الان هم
             * درست قبلِ همان همسایه است، پس این فراخوانی هیچ چیزی را جابه‌جا
             * نمی‌کند. جهت را از ترتیبِ فعلیِ DOM می‌خوانیم: اگر هدف *بعدِ*
             * عنصرِ درگ‌شونده باشد (یعنی داریم به جلو می‌کشیم)، عنصر را
             * *بعدِ* هدف می‌گذاریم؛ اگر هدف *قبلِ* آن باشد، *قبلش* می‌گذاریم.
             */
            function moveNear(container, dragging, target) {
                var pos = dragging.compareDocumentPosition(target);
                if (pos & Node.DOCUMENT_POSITION_FOLLOWING) {
                    container.insertBefore(dragging, target.nextSibling);
                } else if (pos & Node.DOCUMENT_POSITION_PRECEDING) {
                    container.insertBefore(dragging, target);
                }
            }

            /* ---------------- ردیفِ مشخصه ---------------- */

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
                var input = row.querySelector('.zig3d-combobox__input');
                var hidden = row.querySelector('[data-zig3d-combobox-value]');
                var list = document.getElementById('zig3d-attr-options');
                if (!input || !hidden) { return; }

                var sync = function () {
                    var text = input.value.trim();
                    var match = list && text ? list.querySelector('option[value="' + CSS.escape(text) + '"]') : null;
                    hidden.value = match ? match.getAttribute('data-slug') : 'custom';
                };
                input.addEventListener('change', sync);
                input.addEventListener('blur', sync);
            }

            function wireItemRow(row) {
                wireSourceVisibility(row);
                wireCombobox(row);

                var remove = row.querySelector('[data-zig3d-remove-item]');
                if (remove) { remove.addEventListener('click', function () { row.remove(); }); }

                row.addEventListener('dragstart', function (e) {
                    if (e.target.closest('input, select, textarea, button')) {
                        e.preventDefault();
                        return;
                    }
                    e.stopPropagation();
                    row.classList.add('zig3d-dragging');
                    e.dataTransfer.effectAllowed = 'move';
                });
                row.addEventListener('dragend', function (e) {
                    e.stopPropagation();
                    row.classList.remove('zig3d-dragging');
                });
            }

            function wireItemList(list) {
                list.addEventListener('dragover', function (e) {
                    var dragging = list.querySelector('.zig3d-dragging');
                    if (!dragging) { return; }
                    e.preventDefault();
                    e.stopPropagation();
                    var target = e.target.closest('.zig3d-spec-row');
                    if (target && target !== dragging && target.parentNode === list) {
                        moveNear(list, dragging, target);
                    } else if (!target && e.target === list) {
                        // فضایِ خالیِ زیرِ آخرین ردیف — یعنی «همین‌جا، انتها»
                        list.appendChild(dragging);
                    }
                });
            }

            function stampBoxIds(root, boxId) {
                root.querySelectorAll('[name]').forEach(function (el) {
                    el.setAttribute('name', el.getAttribute('name').replace('__B__', boxId).replace('__I__', uid()));
                });
            }

            function addItemTo(box) {
                var list = box.querySelector('[data-zig3d-items]');
                var boxId = box.getAttribute('data-zig3d-box-id');
                var frag = ITEM_TPL.content.cloneNode(true);
                var row = frag.querySelector('.zig3d-spec-row');
                stampBoxIds(frag, boxId);
                list.appendChild(frag);
                wireItemRow(row);
            }

            /* ---------------- جعبهٔ گروه ---------------- */

            function boxId(box) {
                var hidden = box.querySelector('input[name*="[existing_name]"]');
                var match = hidden && hidden.name.match(/zig3d_groups\[([^\]]+)\]/);
                return match ? match[1] : uid();
            }

            function wireBox(box) {
                box.setAttribute('data-zig3d-box-id', boxId(box));

                var list = box.querySelector('[data-zig3d-items]');
                if (list) {
                    wireItemList(list);
                    list.querySelectorAll('.zig3d-spec-row').forEach(wireItemRow);
                }

                var addItem = box.querySelector('[data-zig3d-add-item]');
                if (addItem) { addItem.addEventListener('click', function () { addItemTo(box); }); }

                var remove = box.querySelector('[data-zig3d-remove-box]');
                if (remove) {
                    remove.addEventListener('click', function () {
                        var usage = parseInt(box.getAttribute('data-usage') || '0', 10);
                        if (usage > 0) {
                            var msg = <?php echo wp_json_encode(__('این گروه در دسته‌هایی استفاده می‌شود. با حذف و ذخیره، از فهرستِ آن دسته‌ها هم می‌افتد. حذف شود؟', 'zig3d-widgets')); ?>;
                            if (!window.confirm(msg)) { return; }
                        }
                        box.remove();
                    });
                }

                box.addEventListener('dragstart', function (e) {
                    /*
                     * جعبه کلاً draggable است تا کشیدن از هرجایش کار کند، ولی
                     * این یعنی مکث روی یک ورودی یا دکمه هم می‌تواند درگِ کل
                     * جعبه را شروع کند و اجازهٔ کلیک/تایپ را بگیرد. اگر شروعِ
                     * درگ از یک فیلد یا ردیفِ مشخصه (که خودش جدا draggable
                     * است) بوده، جعبه درگ نمی‌شود.
                     */
                    if (e.target.closest('input, select, textarea, button, .zig3d-spec-row')) {
                        e.preventDefault();
                        return;
                    }
                    box.classList.add('zig3d-dragging');
                    e.dataTransfer.effectAllowed = 'move';
                });
                box.addEventListener('dragend', function () {
                    box.classList.remove('zig3d-dragging');
                    GRID.querySelectorAll('.zig3d-drop-target').forEach(function (el) {
                        el.classList.remove('zig3d-drop-target');
                    });
                });
                box.addEventListener('dragenter', function (e) {
                    if (GRID.querySelector('.zig3d-dragging') && box !== GRID.querySelector('.zig3d-dragging')) {
                        box.classList.add('zig3d-drop-target');
                    }
                });
                box.addEventListener('dragleave', function () {
                    box.classList.remove('zig3d-drop-target');
                });
            }

            /*
             * جابه‌جایی رویِ خودِ ‎dragover‎ انجام می‌شود، نه ‎drop‎ — همان
             * تکنیکِ فهرستِ مشخصه‌ها. رویدادِ ‎drop‎ برایِ درگ‌اند‌دراپِ بومی
             * نیاز به یک زنجیرهٔ کاملِ dragenter→dragover→drop دارد که هر سه
             * تا با preventDefault درست تنظیم شوند؛ جابه‌جاییِ زنده رویِ
             * dragover هم ساده‌تر است و هم قابل‌اعتمادتر (بازخوردِ آنی هم
             * می‌دهد، کاربر می‌بیند جعبه کجا می‌نشیند، نه فقط بعدِ رهاکردن).
             */
            GRID.addEventListener('dragover', function (e) {
                var dragging = GRID.querySelector('.zig3d-spec-box.zig3d-dragging');
                if (!dragging) { return; }
                e.preventDefault();

                var target = e.target.closest('.zig3d-spec-box');
                if (target && target !== dragging) {
                    moveNear(GRID, dragging, target);
                }
            });

            GRID.querySelectorAll('.zig3d-spec-box').forEach(wireBox);

            var addBox = document.getElementById('zig3d-add-box');
            if (addBox && BOX_TPL) {
                addBox.addEventListener('click', function () {
                    var id = uid();
                    var frag = BOX_TPL.content.cloneNode(true);
                    var box = frag.querySelector('.zig3d-spec-box');
                    stampBoxIds(frag, id);
                    GRID.insertBefore(frag, addBox);
                    wireBox(box);
                    box.querySelector('.zig3d-spec-box__title').focus();
                });
            }

            var search = document.getElementById('zig3d-spec-search');
            if (search) {
                search.addEventListener('input', function () {
                    var q = search.value.trim().toLowerCase();
                    GRID.querySelectorAll('.zig3d-spec-box:not(.zig3d-spec-box--add)').forEach(function (box) {
                        var title = box.querySelector('.zig3d-spec-box__title');
                        var hay = title ? title.value.toLowerCase() : '';
                        box.hidden = q !== '' && hay.indexOf(q) === -1;
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

        if (!isset($_POST['zig3d_groups']) || !is_array($_POST['zig3d_groups'])) {
            return '';
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $boxes = self::deep_sanitize(wp_unslash($_POST['zig3d_groups']));

        $groups = [];
        $used   = [];

        foreach ($boxes as $box) {
            $label = trim((string) ($box['label'] ?? ''));
            $items = array_values((array) ($box['items'] ?? []));

            $clean = Spec_Group::sanitize(['label' => $label, 'items' => $items]);

            // جعبهٔ بی‌مشخصه — چه تازه رها شده باشد، چه عمداً خالی شده — ذخیره نمی‌شود
            if (null === $clean) {
                continue;
            }

            $existing = sanitize_key((string) ($box['existing_name'] ?? ''));
            $slug     = ('' !== $existing) ? $existing : self::unique_slug($label, $used);

            if (isset($used[$slug])) {
                $slug = self::unique_slug($label . '-' . (count($used) + 1), $used);
            }

            $used[$slug]    = true;
            $groups[$slug]  = $clean;
        }

        Spec_Store::save_groups($groups);

        return __('گروه‌ها ذخیره شدند.', 'zig3d-widgets');
    }

    private static function unique_slug(string $label, array $used): string {
        $base = sanitize_key($label);

        if ('' === $base) {
            $base = 'group';
        }

        $slug = $base;
        $i    = 2;

        while (isset($used[$slug])) {
            $slug = $base . '-' . $i;
            ++$i;
        }

        return $slug;
    }

    /** @return mixed */
    private static function deep_sanitize($value) {
        if (is_array($value)) {
            return array_map([self::class, 'deep_sanitize'], $value);
        }

        return sanitize_text_field((string) $value);
    }
}
