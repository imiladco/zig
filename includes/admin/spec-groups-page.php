<?php
namespace Zig3d_Widgets\Admin;

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
 * ظاهر یک دورِ جداگانه پرداخت شد (تولبارِ چسبان با جست‌وجو/افزودن/ذخیره،
 * کارت‌های سایه‌دار با سلسله‌مراتبِ روشن بینِ گروه/مشخصه/فیلد، حالت‌های
 * خالی/نامعتبر/درحالِ‌ذخیره) بدونِ دست‌زدن به منطقِ درگ‌اند‌دراپ یا ساختارِ
 * ذخیره — همان چیزی که پایینِ فایل است، دست‌نخورده.
 */
final class Spec_Groups_Page {

    private const SLUG  = 'zig3d-spec-groups';
    private const NONCE = 'zig3d_spec_groups';

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

        echo '<div class="wrap zig3d-spec-wrap"><div class="zig3d-spec-panel">';

        self::render_toolbar($notice);
        self::render_grid($groups);

        echo '</div></div>';
    }

    /** تیترِ صفحه + جست‌وجو + افزودن + ذخیره، همه یک‌جا و چسبان */
    private static function render_toolbar(string $notice): void {
        echo '<div class="zig3d-spec-toolbar">';

        echo '<div class="zig3d-spec-toolbar__intro">';
        printf('<h1 class="zig3d-spec-toolbar__title">%s</h1>', esc_html__('گروه‌های مشخصات فنی', 'zig3d-widgets'));
        printf(
            '<p class="zig3d-spec-toolbar__desc">%s</p>',
            esc_html__(
                'هر کارت یک گروهِ آکاردئونی است — یک عنوان و چند مشخصه. کارت‌ها را بکشید تا ترتیبشان عوض شود؛ هر دستهٔ محصول از صفحهٔ ویرایشِ خودش چند گروه از همین فهرست را انتخاب می‌کند.',
                'zig3d-widgets'
            )
        );
        echo '</div>';

        echo '<div class="zig3d-spec-toolbar__actions">';

        echo '<label class="zig3d-spec-search">';
        echo '<span class="dashicons dashicons-search zig3d-spec-search__icon" aria-hidden="true"></span>';
        printf(
            '<input type="text" id="zig3d-spec-search" placeholder="%s">',
            esc_attr__('جست‌وجو در گروه‌ها…', 'zig3d-widgets')
        );
        echo '</label>';

        printf(
            '<button type="button" class="zig3d-btn zig3d-btn--ghost" data-zig3d-add-box><span class="dashicons dashicons-plus-alt2"></span>%s</button>',
            esc_html__('افزودنِ گروه', 'zig3d-widgets')
        );

        printf(
            '<button type="submit" form="zig3d-spec-form" class="zig3d-btn zig3d-btn--primary" id="zig3d-save-groups"><span class="zig3d-btn__label">%s</span></button>',
            esc_html__('ذخیرهٔ همه', 'zig3d-widgets')
        );

        echo '</div></div>';

        if ('' !== $notice) {
            printf('<div class="notice notice-success is-dismissible zig3d-spec-notice"><p>%s</p></div>', esc_html($notice));
        }
    }

    private static function render_grid(array $groups): void {
        echo '<form method="post" id="zig3d-spec-form">';
        wp_nonce_field(self::NONCE, self::NONCE . '_nonce');

        echo '<div class="zig3d-spec-grid" id="zig3d-spec-grid">';

        foreach ($groups as $name => $group) {
            self::render_box($name, $group, count(Spec_Store::categories_using($name)));
        }

        /*
         * کارتِ «افزودن» فقط وقتی کتابخانه خالی است — یعنی خودش تنها راهِ
         * افزودن است. وقتی حداقل یک گروه هست، دکمهٔ تولبار همان کار را
         * می‌کند و تکرارِ همان دعوت‌به‌عمل در انتهایِ گرید فقط شلوغی است.
         */
        if (!$groups) {
            self::render_add_card();
        }

        echo '</div>';

        echo '</form>';

        self::render_box_template();
        self::render_script();
    }

    /** کارتِ «افزودنِ گروه» — فقط حالتِ خالی: وقتی هنوز هیچ گروهی نیست */
    private static function render_add_card(): void {
        echo '<button type="button" class="zig3d-spec-box zig3d-spec-box--add" data-zig3d-add-box>';
        echo '<span class="zig3d-spec-box--add__icon dashicons dashicons-plus-alt2" aria-hidden="true"></span>';
        printf('<span class="zig3d-spec-box--add__label">%s</span>', esc_html__('گروهِ تازه', 'zig3d-widgets'));
        echo '</button>';
    }

    /**
     * @param string $existing_name نامِ ذخیره‌شده؛ برایِ گروهِ تازه خالی است
     */
    private static function render_box(string $existing_name, array $group, int $usage): void {
        $box_id = 'b' . preg_replace('/[^a-z0-9]/', '', $existing_name ?: uniqid());
        $count  = count($group['items'] ?? []);

        printf('<div class="zig3d-spec-box" draggable="true" data-usage="%d">', $usage);

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

        echo '<div class="zig3d-spec-box__meta">';
        printf(
            '<span class="zig3d-spec-box__count" data-zig3d-count>%s</span>',
            esc_html(sprintf(
                /* translators: %d: تعداد مشخصه */
                _n('%d مشخصه', '%d مشخصه', $count, 'zig3d-widgets'),
                $count
            ))
        );
        if ($usage > 0) {
            printf(
                '<span class="zig3d-spec-box__usage">%s</span>',
                esc_html(sprintf(
                    /* translators: %d: تعداد دسته */
                    _n('در %d دسته', 'در %d دسته', $usage, 'zig3d-widgets'),
                    $usage
                ))
            );
        }
        echo '</div><!-- /.zig3d-spec-box__meta — پایانِ سرستون؛ خطِ زیرش مرزِ بدنه است -->';

        printf('<input type="hidden" name="zig3d_groups[%s][existing_name]" value="%s">', esc_attr($box_id), esc_attr($existing_name));

        echo '<ul class="zig3d-spec-box__items" data-zig3d-items>';
        foreach (($group['items'] ?? []) as $i => $item) {
            self::render_item($box_id, $i, $item);
        }
        echo '</ul>';

        printf(
            '<button type="button" class="zig3d-spec-box__add-item" data-zig3d-add-item>'
                . '<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>%s</button>',
            esc_html__('افزودنِ مشخصه', 'zig3d-widgets')
        );

        echo '</div>';
    }

    /**
     * @param string     $box_id
     * @param int|string $i
     */
    private static function render_item(string $box_id, $i, array $item): void {
        $source    = $item['source'] ?? 'attribute';
        $attribute = $item['attribute'] ?? '';
        $meta_key  = $item['meta_key'] ?? '';
        $label     = $item['label'] ?? '';
        $name      = sprintf('zig3d_groups[%s][items][%s]', $box_id, $i);
        $options   = self::attribute_options();

        /*
         * «ناتمام» یعنی هنوز چیزی برایِ نشان‌دادن ندارد — نه ویژگی‌ای
         * انتخاب شده، نه کلیدِ متایی نوشته شده. دو جا از همین یک پرچم
         * استفاده می‌کنند: هم متنِ خلاصه (بندِ ۴)، هم اینکه آکاردئون بازِ
         * پیش‌فرض بماند یا نه (بندِ ۳) — مشخصهٔ تازه‌افزوده هم دقیقاً از
         * همین راه باز می‌ماند، چون خودش هم ناتمام است، بدونِ نیاز به
         * پرچمِ جداگانه‌ای برایِ «تازه بودن».
         */
        $incomplete = ('attribute' === $source && '' === $attribute) || ('custom_meta' === $source && '' === $meta_key);
        [$summaryPrimary, $summarySecondary] = self::item_summary($source, $attribute, $meta_key, $label, $options, $incomplete);

        printf('<li class="zig3d-spec-row%s" draggable="true">', $incomplete ? '' : ' zig3d-spec-row--collapsed');

        echo '<div class="zig3d-spec-row__header" data-zig3d-row-toggle>';
        echo '<span class="zig3d-spec-row__handle dashicons dashicons-menu" aria-hidden="true"></span>';
        echo '<span class="zig3d-spec-row__summary">';
        printf('<span class="zig3d-spec-row__summary-primary">%s</span>', esc_html($summaryPrimary));
        // همیشه در DOM — جاوااسکریپت بعداً همین عنصر را زنده به‌روز می‌کند، نه اینکه بسازدش
        printf(
            '<span class="zig3d-spec-row__summary-secondary"%s>%s</span>',
            '' === $summarySecondary ? ' hidden' : '',
            esc_html($summarySecondary)
        );
        echo '</span>';
        echo '<button type="button" class="zig3d-spec-row__remove" data-zig3d-remove-item title="' . esc_attr__('حذف', 'zig3d-widgets') . '">'
            . '<span class="dashicons dashicons-no-alt"></span></button>';
        echo '<span class="zig3d-spec-row__chevron dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>';
        echo '</div>';

        echo '<div class="zig3d-spec-row__panel">';
        echo '<div class="zig3d-spec-row__body">';

        echo '<label class="zig3d-spec-field zig3d-spec-field--source">';
        echo '<span class="zig3d-spec-field__label">' . esc_html__('نوعِ مقدار', 'zig3d-widgets') . '</span>';
        echo '<select name="' . esc_attr($name) . '[source]" class="zig3d-spec-field__control" data-zig3d-source>';
        foreach (self::source_labels() as $value => $labelText) {
            printf('<option value="%s"%s>%s</option>', esc_attr($value), selected($value, $source, false), esc_html($labelText));
        }
        echo '</select>';
        echo '</label>';

        echo '<div class="zig3d-spec-field zig3d-spec-field--attr">';
        echo '<span class="zig3d-spec-field__label">' . esc_html__('ویژگی', 'zig3d-widgets') . '</span>';
        echo '<span data-zig3d-when="attribute"' . ('attribute' === $source ? '' : ' hidden') . '>';
        /*
         * فقط انتخاب از فهرستِ واقعیِ ووکامرس — نه یک ورودیِ آزاد. اگر
         * فروشگاه هنوز ویژگیِ سراسری‌ای نساخته، سلکت غیرفعال است و همان
         * پیام را نشان می‌دهد؛ راهِ نوشتنِ نامِ دلخواه (وقتی واقعاً لازم
         * باشد) مبدأِ «فیلدِ دلخواه» است، نه این‌جا.
         */
        if ($options) {
            printf('<select name="%s[attribute]" class="zig3d-spec-field__control">', esc_attr($name));
            printf(
                '<option value=""%s>%s</option>',
                selected('', $attribute, false),
                esc_html__('انتخابِ ویژگیِ محصول', 'zig3d-widgets')
            );
            foreach ($options as $taxonomy => $attrLabel) {
                printf(
                    '<option value="%s"%s>%s</option>',
                    esc_attr($taxonomy),
                    selected($taxonomy, $attribute, false),
                    esc_html($attrLabel)
                );
            }
            echo '</select>';
        } else {
            // اگر قبلاً مقداری ذخیره شده (مثلاً فروشگاه موقتاً ویژگی ندارد)، با ذخیرهٔ دوباره پاک نشود
            if ('' !== $attribute) {
                printf('<input type="hidden" name="%s[attribute]" value="%s">', esc_attr($name), esc_attr($attribute));
            }
            printf(
                '<select class="zig3d-spec-field__control" disabled><option>%s</option></select>',
                esc_html__('هیچ ویژگی ووکامرسی یافت نشد', 'zig3d-widgets')
            );
            printf(
                '<p class="zig3d-spec-field__hint"><a href="%s" target="_blank" rel="noopener">%s</a></p>',
                esc_url(admin_url('edit.php?post_type=product&page=product_attributes')),
                esc_html__('از اینجا یک ویژگیِ محصول بسازید', 'zig3d-widgets')
            );
        }
        echo '</span>';
        printf(
            '<input type="text" class="zig3d-spec-field__control" name="%s[meta_key]" value="%s" placeholder="%s" data-zig3d-when="custom_meta"%s>',
            esc_attr($name),
            esc_attr($meta_key),
            esc_attr__('کلیدِ متا', 'zig3d-widgets'),
            'custom_meta' === $source ? '' : ' hidden'
        );
        echo '</div>';

        echo '<label class="zig3d-spec-field zig3d-spec-field--label">';
        echo '<span class="zig3d-spec-field__label">' . esc_html__('عنوانِ دلخواه', 'zig3d-widgets') . '</span>';
        printf(
            '<input type="text" class="zig3d-spec-field__control" name="%s[label]" value="%s" placeholder="%s">',
            esc_attr($name),
            esc_attr($label),
            esc_attr__('خالی = پیش‌فرض', 'zig3d-widgets')
        );
        echo '</label>';

        echo '</div>'; // .zig3d-spec-row__body
        echo '</div>'; // .zig3d-spec-row__panel

        echo '</li>';
    }

    /**
     * متنِ خلاصهٔ حالتِ بسته: خط اول چیزی‌ست که این مشخصه واقعاً نشان
     * می‌دهد (برچسبِ دلخواه، یا نامِ ویژگی/کلیدِ متا/برچسبِ مبدأ)، خط دوم
     * نوعِ مبدأ — مگر وقتی چیزی برایِ نشان‌دادن نیست، که آن‌وقت هر دو خط
     * صریحاً می‌گویند «هنوز کامل نشده».
     *
     * @param array<string,string> $options
     * @return array{0:string,1:string}
     */
    private static function item_summary(string $source, string $attribute, string $meta_key, string $label, array $options, bool $incomplete): array {
        if ($incomplete) {
            return [
                __('مشخصهٔ تازه', 'zig3d-widgets'),
                'custom_meta' === $source
                    ? __('هنوز کلیدِ متا تعیین نشده', 'zig3d-widgets')
                    : __('هنوز ویژگی انتخاب نشده', 'zig3d-widgets'),
            ];
        }

        $sourceLabel = self::source_labels()[$source] ?? $source;
        $resolved    = 'attribute' === $source ? ($options[$attribute] ?? $attribute) : ('custom_meta' === $source ? $meta_key : $sourceLabel);
        $primary     = '' !== $label ? $label : $resolved;

        // اگر خطِ دوم چیزِ تازه‌ای نمی‌گوید (همان چیزی‌ست که خطِ اول گفت)، تکرار نشود
        return [$primary, $primary === $sourceLabel ? '' : $sourceLabel];
    }

    /** کش‌شده برایِ همین رندر — چند بار در هر ردیف پرسیده می‌شود */
    private static ?array $attribute_options = null;

    /**
     * ویژگی‌هایِ واقعیِ ووکامرس، همان‌هایی که در Products › Attributes
     * ساخته شده‌اند — تاکسونومی => برچسبِ خوانا.
     *
     * عمداً مستقیم از ‎wc_get_attribute_taxonomies()‎ می‌آید، نه از
     * ‎Attributes::all()‎ (که برایِ فیلترهایِ سایدبار است و برند/تاکسونومی‌های
     * دیگر را هم قاطی می‌کند). این‌جا فقط همان جدولِ ووکامرس معتبر است —
     * دقیقاً منبعی که قرار است.
     */
    private static function attribute_options(): array {
        if (null !== self::$attribute_options) {
            return self::$attribute_options;
        }

        $options = [];

        if (function_exists('wc_get_attribute_taxonomies') && function_exists('wc_attribute_taxonomy_name')) {
            foreach ((array) wc_get_attribute_taxonomies() as $attribute) {
                $taxonomy = (string) wc_attribute_taxonomy_name((string) ($attribute->attribute_name ?? ''));

                if ('' === $taxonomy) {
                    continue;
                }

                $label = trim((string) ($attribute->attribute_label ?? ''));
                $options[$taxonomy] = '' !== $label ? $label : $taxonomy;
            }
        }

        return self::$attribute_options = $options;
    }

    /**
     * @return array<string,string>
     * @see Spec_Group::source_labels() منبعِ واحد؛ اینجا فقط نامِ کوتاه‌تر است
     */
    private static function source_labels(): array {
        return Spec_Group::source_labels();
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

    /* =====================================================================
     * استایل
     * =================================================================== */

    private static function render_styles(): void {
        ?>
        <style>
        .zig3d-spec-wrap {
            --zig3d-primary: #7B5CFF;
            --zig3d-primary-dark: #6D28D9;
            --zig3d-primary-light: #C4B5FD;
            --zig3d-bg: #F8FAFC;
            --zig3d-card: #FFFFFF;
            --zig3d-border: #E2E8F0;
            --zig3d-text: #111827;
            --zig3d-text-muted: #64748B;
            --zig3d-danger: #EF4444;
            --zig3d-field-border: #CBD5E1;
            --zig3d-field-bg: #F8FAFC;
            --zig3d-field-item-border: #E5E7EB;
            font-family: 'Yekan Bakh FaNum', Vazirmatn, -apple-system, BlinkMacSystemFont, 'Segoe UI', Tahoma, sans-serif;
            direction: rtl;
        }

        .zig3d-spec-wrap * { box-sizing: border-box; }

        .zig3d-spec-panel {
            max-width: 1280px;
            margin: 20px auto 0;
            background: var(--zig3d-bg);
            padding: 32px;
            border-radius: 18px;
            color: var(--zig3d-text);
        }

        /* ---------------- تولبار ---------------- */

        .zig3d-spec-toolbar {
            position: sticky;
            top: 32px;
            z-index: 10;
            display: flex;
            flex-wrap: wrap;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            background: var(--zig3d-bg);
            padding-bottom: 20px;
        }

        .zig3d-spec-toolbar__title {
            margin: 0 0 4px;
            font-size: 22px;
            font-weight: 800;
            color: var(--zig3d-text);
        }

        .zig3d-spec-toolbar__desc {
            margin: 0;
            max-width: 640px;
            font-size: 13px;
            font-weight: 400;
            line-height: 1.9;
            color: var(--zig3d-text-muted);
        }

        .zig3d-spec-toolbar__actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .zig3d-spec-search {
            position: relative;
            display: flex;
            align-items: center;
        }

        .zig3d-spec-search__icon {
            position: absolute;
            inset-inline-start: 12px;
            color: var(--zig3d-text-muted);
            pointer-events: none;
            font-size: 16px;
        }

        .zig3d-spec-search input {
            width: 220px;
            height: 40px;
            padding: 0 12px 0 12px;
            padding-inline-start: 34px;
            border: 1px solid var(--zig3d-field-border);
            border-radius: 10px;
            background: var(--zig3d-card);
            font-size: 13px;
            color: var(--zig3d-text);
            transition: border-color .15s ease, box-shadow .15s ease;
        }

        .zig3d-spec-search input:focus {
            outline: none;
            border-color: var(--zig3d-primary);
            box-shadow: 0 0 0 3px rgba(123, 92, 255, .12);
        }

        /* ---------------- دکمه‌ها ---------------- */

        .zig3d-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            height: 40px;
            padding: 0 18px;
            border-radius: 8px;
            border: 1px solid transparent;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            white-space: nowrap;
            transition: background-color .15s ease, border-color .15s ease, box-shadow .15s ease, opacity .15s ease;
        }

        .zig3d-btn .dashicons { font-size: 16px; width: 16px; height: 16px; }

        .zig3d-btn--primary {
            background: var(--zig3d-primary);
            color: #fff;
        }
        .zig3d-btn--primary:hover { background: var(--zig3d-primary-dark); }
        .zig3d-btn--primary:focus-visible {
            outline: none;
            box-shadow: 0 0 0 3px rgba(123, 92, 255, .25);
        }
        .zig3d-btn--primary[disabled],
        .zig3d-btn--primary.is-saving {
            opacity: .65;
            cursor: default;
        }

        .zig3d-btn--ghost {
            background: var(--zig3d-card);
            border-color: var(--zig3d-border);
            color: var(--zig3d-text);
        }
        .zig3d-btn--ghost:hover {
            border-color: var(--zig3d-primary);
            color: var(--zig3d-primary-dark);
        }

        .zig3d-btn--block { width: 100%; }

        .zig3d-spec-notice { margin: 0 0 16px; }

        /* ---------------- گرید ---------------- */

        .zig3d-spec-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            /*
             * پیش‌فرضِ grid این است که همهٔ کارت‌هایِ یک ردیف را هم‌قد کند —
             * یعنی همان گروهِ خالی که تازه compact شد، کنارِ یک گروهِ پر
             * دوباره کشیده و بلند می‌شد. با start هر کارت قدِ خودش را می‌گیرد.
             */
            align-items: start;
            gap: 20px;
        }

        @media (max-width: 1100px) {
            .zig3d-spec-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }

        @media (max-width: 700px) {
            .zig3d-spec-panel { padding: 20px; border-radius: 12px; }
            .zig3d-spec-grid { grid-template-columns: minmax(0, 1fr); }
            .zig3d-spec-toolbar { position: static; }
        }

        /* ---------------- کارتِ گروه ---------------- */

        .zig3d-spec-box {
            display: flex;
            flex-direction: column;
            background: var(--zig3d-card);
            border: 1px solid var(--zig3d-border);
            border-radius: 16px;
            box-shadow: 0 14px 34px rgba(15, 23, 42, .05);
            padding: 16px;
            cursor: grab;
            transition: box-shadow .15s ease, border-color .15s ease;
        }

        .zig3d-spec-box:hover {
            box-shadow: 0 16px 38px rgba(15, 23, 42, .09);
            border-color: #D9DEE7;
        }

        .zig3d-spec-box.zig3d-dragging {
            opacity: .5;
            box-shadow: 0 18px 40px rgba(15, 23, 42, .14);
        }

        .zig3d-spec-box.zig3d-drop-target {
            border-color: var(--zig3d-primary);
            box-shadow: 0 0 0 3px rgba(123, 92, 255, .14);
        }

        .zig3d-spec-box[hidden] { display: none; }

        .zig3d-spec-box__head {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .zig3d-spec-box__handle {
            flex-shrink: 0;
            color: #94A3B8;
            cursor: grab;
            font-size: 15px;
            opacity: .8;
        }
        .zig3d-spec-box__handle:active { cursor: grabbing; }

        .zig3d-spec-box__title {
            flex: 1;
            min-width: 0;
            height: 36px;
            /*
             * مرزِ پیش‌فرض همرنگِ فیلدهایِ دیگر است، نه شفاف — شفاف‌بودن
             * یعنی خودِ تعریفِ CSS چیزی نمی‌گوید و اگر جایی (حتی موقتاً) کلاسِ
             * نامعتبر اشتباه بنشیند، هیچ مرزِ خنثایی برایِ برگشتن نیست.
             */
            border: 1px solid var(--zig3d-field-border);
            border-radius: 10px;
            padding: 0 10px;
            background: var(--zig3d-field-bg);
            font-size: 15px;
            font-weight: 700;
            color: var(--zig3d-text);
            transition: border-color .15s ease, background-color .15s ease, box-shadow .15s ease;
        }
        .zig3d-spec-box__title:hover { background: var(--zig3d-card); }
        .zig3d-spec-box__title:focus {
            outline: none;
            background: var(--zig3d-card);
            border-color: var(--zig3d-primary);
            box-shadow: 0 0 0 3px rgba(123, 92, 255, .12);
        }
        /*
         * فقط بعدِ لمس‌شدن (blur حداقل یک‌بار) و خالی‌بودن قرمز می‌شود —
         * جاوااسکریپت مسئولِ همین قاعده است؛ این کلاس هرگز در بارگذاریِ
         * اول یا برایِ کارتِ تازه‌ساخته‌شده نمی‌نشیند.
         */
        .zig3d-spec-box__title.is-invalid {
            border-color: var(--zig3d-danger);
            background: #FFF7F7;
            box-shadow: 0 0 0 3px rgba(239, 68, 68, .12);
        }

        .zig3d-spec-box__remove {
            flex-shrink: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 30px;
            height: 30px;
            border: 0;
            border-radius: 8px;
            background: #FEF2F2;
            color: var(--zig3d-danger);
            cursor: pointer;
            transition: background-color .15s ease;
        }
        .zig3d-spec-box__remove:hover { background: #FEE2E2; }

        .zig3d-spec-box__meta {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 10px 0 12px;
            padding: 0 0 12px;
            padding-inline-start: 24px;
            border-bottom: 1px solid var(--zig3d-border);
        }

        .zig3d-spec-box__count {
            font-size: 12px;
            font-weight: 600;
            color: var(--zig3d-text-muted);
        }

        .zig3d-spec-box__usage {
            font-size: 11px;
            font-weight: 600;
            color: var(--zig3d-primary-dark);
            background: rgba(123, 92, 255, .1);
            border-radius: 999px;
            padding: 2px 8px;
        }

        .zig3d-spec-box__items {
            list-style: none;
            margin: 0 0 10px;
            padding: 0;
            display: flex;
            flex-direction: column;
            gap: 10px;
            flex: 1;
        }

        /*
         * حالتِ خالی دیگر یک جعبهٔ بزرگِ نقطه‌چین نیست — فقط یک خطِ کوتاهِ
         * راهنما، چون دکمهٔ «افزودنِ مشخصه» همین زیرش هست و خودش کنشِ لازم
         * را می‌دهد؛ تکرارِ یک قاب برایِ همین یک پیام لازم نیست.
         */
        .zig3d-spec-box__items:empty {
            display: block;
            margin: 0 0 8px;
            padding: 0;
            text-align: start;
            font-size: 12px;
            color: var(--zig3d-text-muted);
        }
        .zig3d-spec-box__items:empty::before {
            content: "هنوز مشخصه‌ای اضافه نشده";
        }

        /* ---------------- ردیفِ مشخصه (آکاردئون) ---------------- */

        /*
         * وقتی گروهی پنج‌شش مشخصه دارد، سه فیلدِ کامل برایِ هرکدام یعنی
         * کارتِ گروه چند صفحه بلند می‌شود — همان چیزی که خوانایی را
         * می‌خورد. بسته، هر مشخصه فقط یک ردیفِ خلاصه است؛ با کلیک رویِ
         * سرستون باز می‌شود. حالتِ پیش‌فرض (باز/بسته) در PHP تصمیم گرفته
         * می‌شود (‎render_item()‎)، نه اینجا — این‌جا فقط ظاهرِ دو حالت است.
         */
        .zig3d-spec-row {
            background: var(--zig3d-field-bg);
            border: 1px solid var(--zig3d-border);
            border-radius: 12px;
            transition: background-color .15s ease, border-color .15s ease;
        }
        .zig3d-spec-row:hover { border-color: var(--zig3d-primary-light); }

        .zig3d-spec-row.zig3d-dragging { opacity: .5; }

        .zig3d-spec-row:not(.zig3d-spec-row--collapsed) {
            background: var(--zig3d-card);
            border-color: var(--zig3d-primary-light);
        }
        .zig3d-spec-row--collapsed .zig3d-spec-row__panel { display: none; }

        .zig3d-spec-row__header {
            display: flex;
            align-items: center;
            gap: 8px;
            min-height: 52px;
            padding: 8px 10px;
            cursor: pointer;
        }

        .zig3d-spec-row__handle {
            flex-shrink: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 18px;
            min-height: 18px;
            color: #94A3B8;
            font-size: 14px;
            opacity: .8;
            cursor: grab;
        }
        .zig3d-spec-row:active .zig3d-spec-row__handle { cursor: grabbing; }

        .zig3d-spec-row__summary {
            flex: 1;
            min-width: 0;
            display: flex;
            align-items: baseline;
            gap: 8px;
            overflow: hidden;
        }

        .zig3d-spec-row__summary-primary {
            font-size: 13px;
            font-weight: 600;
            color: var(--zig3d-text);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .zig3d-spec-row__summary-secondary {
            flex-shrink: 0;
            font-size: 11.5px;
            color: var(--zig3d-text-muted);
        }

        .zig3d-spec-row__chevron {
            flex-shrink: 0;
            color: var(--zig3d-text-muted);
            font-size: 16px;
            transition: transform .15s ease;
        }
        .zig3d-spec-row--collapsed .zig3d-spec-row__chevron { transform: rotate(-90deg); }

        .zig3d-spec-row__panel { padding: 0 14px 14px; }

        .zig3d-spec-row__body {
            display: grid;
            grid-template-columns: minmax(0, 1fr);
            gap: 8px;
            padding-top: 4px;
            border-top: 1px solid var(--zig3d-border);
        }

        .zig3d-spec-field {
            display: flex;
            flex-direction: column;
            gap: 4px;
            min-width: 0;
        }

        .zig3d-spec-field--attr { position: relative; }

        .zig3d-spec-field__label {
            font-size: 12px;
            font-weight: 600;
            color: var(--zig3d-text-muted);
        }

        .zig3d-spec-field__control {
            width: 100%;
            height: 40px;
            padding: 0 10px;
            border: 1px solid var(--zig3d-field-border);
            border-radius: 10px;
            background: var(--zig3d-card);
            font-size: 12.5px;
            color: var(--zig3d-text);
            transition: border-color .15s ease, box-shadow .15s ease;
        }

        .zig3d-spec-field__control:focus {
            outline: none;
            border-color: var(--zig3d-primary);
            box-shadow: 0 0 0 3px rgba(123, 92, 255, .12);
        }

        .zig3d-spec-field__control:disabled {
            background: var(--zig3d-field-bg);
            color: var(--zig3d-text-muted);
            cursor: not-allowed;
        }

        .zig3d-spec-field__hint {
            margin: 6px 0 0;
            font-size: 11px;
            line-height: 1.6;
            color: var(--zig3d-text-muted);
        }
        .zig3d-spec-field__hint a { color: var(--zig3d-primary-dark); text-decoration: underline; }

        .zig3d-spec-row__remove {
            flex-shrink: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            border: 0;
            border-radius: 8px;
            background: #FEF2F2;
            color: var(--zig3d-danger);
            cursor: pointer;
            transition: background-color .15s ease;
        }
        .zig3d-spec-row__remove:hover { background: #FEE2E2; }

        /* ---------------- افزودنِ مشخصه ---------------- */

        .zig3d-spec-box__add-item {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            width: 100%;
            height: 38px;
            border: 1px dashed var(--zig3d-field-border);
            border-radius: 8px;
            background: transparent;
            color: var(--zig3d-primary-dark);
            font-size: 12.5px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color .15s ease, border-color .15s ease;
        }
        .zig3d-spec-box__add-item:hover {
            background: rgba(123, 92, 255, .06);
            border-color: var(--zig3d-primary);
        }
        .zig3d-spec-box__add-item .dashicons { font-size: 14px; width: 14px; height: 14px; }

        /* ---------------- کارتِ افزودنِ گروه ---------------- */

        .zig3d-spec-box--add {
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 100%;
            border: 1.5px dashed rgba(123, 92, 255, .35);
            background: rgba(123, 92, 255, .03);
            color: var(--zig3d-primary-dark);
            cursor: pointer;
        }
        .zig3d-spec-box--add:hover {
            border-color: var(--zig3d-primary);
            background: rgba(123, 92, 255, .07);
            box-shadow: 0 10px 30px rgba(15, 23, 42, .04);
        }
        .zig3d-spec-box--add__icon { font-size: 20px; }
        .zig3d-spec-box--add__label { font-size: 14px; font-weight: 700; }
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

            /**
             * نمایانیِ فیلدها فقط به یک سیگنال بند است: مقدارِ «نوعِ
             * مقدار». دیگر زیرمبدأِ «سفارشی» ای در کارِ ویژگی نیست — سلکتِ
             * ویژگی فقط از فهرستِ واقعیِ ووکامرس انتخاب می‌کند، پس یک
             * سیگنالِ دوم لازم نبود.
             */
            function wireSourceVisibility(row) {
                var select = row.querySelector('[data-zig3d-source]');
                if (!select) { return; }

                var attrWrap = row.querySelector('[data-zig3d-when="attribute"]');
                var attrSelect = attrWrap ? attrWrap.querySelector('select[name*="[attribute]"]') : null;
                var metaIn = row.querySelector('[data-zig3d-when="custom_meta"]');

                var sync = function () {
                    if (attrWrap) { attrWrap.hidden = 'attribute' !== select.value; }
                    if (metaIn) { metaIn.hidden = 'custom_meta' !== select.value; }
                };

                select.addEventListener('change', function () {
                    /*
                     * تعویضِ مبدأ یعنی فیلدِ مبدأِ قبلی دیگر معنا ندارد —
                     * پاک می‌شود تا داده‌ای که دیگر دیده نمی‌شود بی‌سروصدا
                     * زیرِ فرم نماند.
                     */
                    if ('attribute' !== select.value && attrSelect) { attrSelect.value = ''; }
                    if ('custom_meta' !== select.value && metaIn) { metaIn.value = ''; }
                    sync();
                });

                sync();
            }

            /** شمارشِ زندهٔ «N مشخصه» بالایِ جعبه — فقط بازخوردِ چشمی، ذخیره از رویِ خودِ DOM حساب می‌شود */
            function refreshCount(box) {
                var badge = box.querySelector('[data-zig3d-count]');
                if (!badge) { return; }
                var n = box.querySelectorAll('.zig3d-spec-row').length;
                badge.textContent = n + ' ' + <?php echo wp_json_encode(__('مشخصه', 'zig3d-widgets')); ?>;
            }

            /**
             * باز/بسته‌شدنِ آکاردئونِ یک مشخصه. حالتِ پیش‌فرض (کدام مشخصه
             * باز باشد) در PHP تصمیم گرفته شده؛ اینجا فقط toggleِ زنده‌اش
             * است. کلیک روی خودِ دستهٔ درگ toggle نمی‌کند — کاربری که
             * می‌خواهد بکشد نباید هر بار اول باز/بسته‌اش کند.
             */
            function wireRowToggle(row) {
                var header = row.querySelector('[data-zig3d-row-toggle]');
                if (!header) { return; }

                header.addEventListener('click', function (e) {
                    if (e.target.closest('button, .zig3d-spec-row__handle')) { return; }
                    row.classList.toggle('zig3d-spec-row--collapsed');
                });
            }

            /**
             * متنِ خلاصهٔ حالتِ بسته را زنده نگه می‌دارد — همان منطقِ
             * ‎item_summary()‎ سمتِ PHP، ولی این‌بار بدونِ رفت‌وبرگشت به
             * سرور، چون کاربر ممکن است ویژگی را عوض کند و بدونِ ذخیره
             * ردیف را ببندد؛ اگر خلاصه به‌روز نشود، ردیفِ بسته دروغ
             * می‌گوید.
             */
            function wireRowSummary(row) {
                var primaryEl = row.querySelector('.zig3d-spec-row__summary-primary');
                var secondaryEl = row.querySelector('.zig3d-spec-row__summary-secondary');
                var sourceSelect = row.querySelector('[data-zig3d-source]');
                if (!primaryEl || !secondaryEl || !sourceSelect) { return; }

                var NEW_LABEL = <?php echo wp_json_encode(__('مشخصهٔ تازه', 'zig3d-widgets')); ?>;
                var NO_ATTR = <?php echo wp_json_encode(__('هنوز ویژگی انتخاب نشده', 'zig3d-widgets')); ?>;
                var NO_META = <?php echo wp_json_encode(__('هنوز کلیدِ متا تعیین نشده', 'zig3d-widgets')); ?>;

                var update = function () {
                    var source = sourceSelect.value;
                    var sourceLabel = sourceSelect.options[sourceSelect.selectedIndex].text;
                    var attrSelect = row.querySelector('[data-zig3d-when="attribute"] select:not(:disabled)');
                    var metaIn = row.querySelector('[data-zig3d-when="custom_meta"]');
                    var labelIn = row.querySelector('.zig3d-spec-field--label input');

                    var incomplete = ('attribute' === source && (!attrSelect || '' === attrSelect.value))
                        || ('custom_meta' === source && metaIn && '' === metaIn.value.trim());

                    if (incomplete) {
                        primaryEl.textContent = NEW_LABEL;
                        secondaryEl.textContent = 'custom_meta' === source ? NO_META : NO_ATTR;
                        secondaryEl.hidden = false;
                        return;
                    }

                    var resolved = 'attribute' === source
                        ? attrSelect.options[attrSelect.selectedIndex].text
                        : ('custom_meta' === source ? metaIn.value.trim() : sourceLabel);

                    var primary = (labelIn && labelIn.value.trim()) || resolved;

                    primaryEl.textContent = primary;
                    secondaryEl.hidden = primary === sourceLabel;
                    secondaryEl.textContent = sourceLabel;
                };

                row.addEventListener('change', update);
                row.addEventListener('input', update);
            }

            function wireItemRow(row, box) {
                wireSourceVisibility(row);
                wireRowToggle(row);
                wireRowSummary(row);

                var remove = row.querySelector('[data-zig3d-remove-item]');
                if (remove) {
                    remove.addEventListener('click', function () {
                        row.remove();
                        refreshCount(box);
                    });
                }

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
                wireItemRow(row, box);
                refreshCount(box);
                // مشخصهٔ تازه خودش ناتمام است، پس آکاردئونش از PHP باز آمده — فقط دیدش می‌کنیم
                row.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                var firstField = row.querySelector('[data-zig3d-source]');
                if (firstField) { firstField.focus(); }
            }

            /* ---------------- جعبهٔ گروه ---------------- */

            function boxId(box) {
                var hidden = box.querySelector('input[name*="[existing_name]"]');
                var match = hidden && hidden.name.match(/zig3d_groups\[([^\]]+)\]/);
                return match ? match[1] : uid();
            }

            /*
             * قرمزشدن فقط بعدِ اولین blur مجاز است — نه در بارگذاریِ اول، نه
             * تا وقتی کاربر اصلاً به فیلد سر نزده. پرچمِ ‎touched‎ همین قاعده
             * را نگه می‌دارد؛ پیش از آن ‎sync‎ کاری نمی‌کند.
             */
            function wireTitleValidation(box) {
                var title = box.querySelector('.zig3d-spec-box__title');
                if (!title) { return; }
                var touched = false;
                var sync = function () {
                    if (!touched) { return; }
                    title.classList.toggle('is-invalid', '' === title.value.trim());
                };
                title.addEventListener('blur', function () { touched = true; sync(); });
                title.addEventListener('input', sync);
            }

            function wireBox(box) {
                box.setAttribute('data-zig3d-box-id', boxId(box));

                var list = box.querySelector('[data-zig3d-items]');
                if (list) {
                    wireItemList(list);
                    list.querySelectorAll('.zig3d-spec-row').forEach(function (row) { wireItemRow(row, box); });
                }

                wireTitleValidation(box);

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

            GRID.querySelectorAll('.zig3d-spec-box').forEach(function (box) {
                if (!box.classList.contains('zig3d-spec-box--add')) { wireBox(box); }
            });

            /* افزودنِ گروه — هم دکمهٔ تولبار، هم کارتِ خط‌چینِ انتهایِ گرید، هر دو همین یکی را صدا می‌زنند */
            function addGroup() {
                if (!BOX_TPL) { return; }
                var addCard = GRID.querySelector('.zig3d-spec-box--add');
                var id = uid();
                var frag = BOX_TPL.content.cloneNode(true);
                var box = frag.querySelector('.zig3d-spec-box');
                stampBoxIds(frag, id);
                if (addCard) {
                    GRID.insertBefore(frag, addCard);
                    // با اولین گروه، حالتِ خالی دیگر برقرار نیست — کارتِ خط‌چین برود
                    addCard.remove();
                } else {
                    GRID.appendChild(frag);
                }
                wireBox(box);
                refreshCount(box);
                box.querySelector('.zig3d-spec-box__title').focus();
                box.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }

            document.querySelectorAll('[data-zig3d-add-box]').forEach(function (trigger) {
                trigger.addEventListener('click', addGroup);
            });

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

            /* حالتِ «در حالِ ذخیره» — فقط بازخوردِ چشمی برایِ فاصلهٔ کوتاهِ تا رفرشِ صفحه */
            var form = document.getElementById('zig3d-spec-form');
            if (form) {
                form.addEventListener('submit', function () {
                    /*
                     * سرور برایِ عنوانِ خالی خودش یک نامِ پیش‌فرض می‌سازد، پس
                     * ارسال را نمی‌بندیم — فقط لحظهٔ ذخیره هم همان قرمزیِ
                     * touched را رویِ فیلدهایِ هنوز خالی نشان می‌دهیم.
                     */
                    GRID.querySelectorAll('.zig3d-spec-box__title').forEach(function (title) {
                        if ('' === title.value.trim()) {
                            title.classList.add('is-invalid');
                        }
                    });
                    document.querySelectorAll('.zig3d-btn--primary').forEach(function (btn) {
                        btn.disabled = true;
                        btn.classList.add('is-saving');
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
