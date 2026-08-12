<?php
namespace Zig3d_Widgets;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * تصمیم دربارهٔ اینکه یک دسته چه گروه‌بندیِ مشخصاتی نشان بدهد.
 *
 * دقیقاً همان مسئلهٔ ‎Filter_Schema‎ («یک قالبِ مشترک که چند دسته به آن وصل
 * می‌شوند»)، برای مصرفِ دیگری: به‌جای فهرستِ تخت گروه‌های فیلترِ سایدبار،
 * اینجا یک درختِ دوسطحی است — گروه‌های آکاردئون، هرکدام با چند آیتمِ
 * مشخصه (‎«تعداد محور: ۵ محور»‎).
 *
 * منبعِ هر آیتم دقیقاً همان مجموعه‌ایست که ویجتِ «ویژگی‌های محصول» دارد
 * (ویژگی/دسته/برچسب/SKU/امتیاز/موجودی/وزن/ابعاد/فیلد دلخواه) — این عمداً
 * است: قالب‌های مشخصات فنی ادامهٔ همان کارند، فقط چند آیتم را زیرِ یک
 * عنوانِ مشترک جمع می‌کنند.
 *
 * یک تفاوتِ عمدی با ‎Filter_Schema‎: اینجا هیچ لایهٔ بازنویسی‌ای نیست. اتصالِ
 * دسته فقط دو حالت دارد — بدونِ گروه‌بندی، یا یک قالبِ مشخص، بدونِ هیچ
 * دست‌کاریِ محلی. دلیلش را خودِ ‎Filter_Schema‎ نوشته: «بازنویسی جایی است که
 * این‌جور معماری‌ها معمولاً می‌پوسند» — و اینجا حتی بیشتر صدق می‌کند، چون
 * بازنویسیِ یک درختِ گروه/آیتم (نه یک فهرستِ تخت از تاکسونومی) خودش به‌زودی
 * یک زبانِ جداگانه می‌شود. دسته‌ای که واقعاً گروه‌بندیِ متفاوتی می‌خواهد،
 * قالبِ خودش را می‌سازد؛ کپی‌کردنِ یک قالب و تغییرِ کمی از آن، دو کلیک است.
 */
final class Spec_Schema {

    public const MODE_NONE   = 'none';
    public const MODE_SCHEMA = 'schema';

    /** انواعِ مبدأِ هر آیتم — همان مجموعهٔ ویجتِ «ویژگی‌های محصول» */
    public const SOURCES = [
        'attribute', 'category', 'tag', 'sku', 'rating', 'stock', 'weight', 'dimensions', 'custom_meta',
    ];

    /* =====================================================================
     * حل نهایی
     * =================================================================== */

    /**
     * فهرستِ نهاییِ گروه‌های یک دسته.
     *
     * @param array $binding تنظیمِ ذخیره‌شدهٔ خودِ دسته: {mode, schema}
     * @param array $schemas قالب‌های مشترک، کلیدشده با نام
     *
     * @return array{groups:array,mode:string,schema:string,notes:string[]}
     */
    public static function resolve(array $binding, array $schemas): array {
        $mode   = self::MODE_SCHEMA === ($binding['mode'] ?? self::MODE_NONE) ? self::MODE_SCHEMA : self::MODE_NONE;
        $name   = self::name((string) ($binding['schema'] ?? ''));
        $notes  = [];
        $groups = [];

        if (self::MODE_SCHEMA === $mode) {
            if (isset($schemas[$name]['groups'])) {
                $groups = $schemas[$name]['groups'];
            } else {
                /*
                 * قالبی که پاک شده ولی هنوز جایی به آن ارجاع هست. سقوط به
                 * «بدون گروه‌بندی» عمدی است — همان‌طور که ‎Filter_Schema‎ به
                 * فهرستِ خودکار سقوط می‌کند — ولی این اتفاق باید در پنل دیده
                 * شود، وگرنه مدیر سال‌ها فکر می‌کند قالبش هنوز اعمال می‌شود.
                 */
                $mode    = self::MODE_NONE;
                $notes[] = sprintf('قالب «%s» پیدا نشد؛ بدونِ گروه‌بندی نمایش داده می‌شود.', $name);
                $name    = '';
            }
        }

        return [
            'groups' => $groups,
            'mode'   => $mode,
            'schema' => $name,
            'notes'  => $notes,
        ];
    }

    /* =====================================================================
     * پاک‌سازی
     * =================================================================== */

    /**
     * @param array<int,array> $groups
     * @return array<int,array{label:string,items:array}>
     */
    public static function sanitize_groups(array $groups): array {
        $out = [];

        foreach ($groups as $group) {
            if (!is_array($group)) {
                continue;
            }

            $items = self::sanitize_items((array) ($group['items'] ?? []));

            /*
             * گروهِ بی‌آیتم فقط یک عنوانِ خالی در آکاردئون است — یک بخشِ
             * بازشونده که هیچ‌چیز داخلش نیست.
             */
            if (!$items) {
                continue;
            }

            $out[] = [
                'label' => self::text((string) ($group['label'] ?? '')),
                'items' => $items,
            ];
        }

        return $out;
    }

    /**
     * @param array<int,array> $items
     * @return array<int,array{source:string,attribute:string,custom_attribute:string,meta_key:string,label:string}>
     */
    public static function sanitize_items(array $items): array {
        $out = [];

        foreach ($items as $item) {
            $clean = self::sanitize_item(is_array($item) ? $item : []);

            if (null !== $clean) {
                $out[] = $clean;
            }
        }

        return $out;
    }

    /** یک آیتم، یا ‎null‎ اگر هیچ مبدأِ واقعی‌ای معلوم نکند */
    public static function sanitize_item(array $item): ?array {
        $source = in_array($item['source'] ?? '', self::SOURCES, true) ? $item['source'] : 'attribute';

        $attribute = self::taxonomy((string) ($item['attribute'] ?? ''));
        if ('' === $attribute) {
            $attribute = 'custom';
        }

        $custom_attribute = self::text((string) ($item['custom_attribute'] ?? ''));

        $clean = [
            'source'           => $source,
            'attribute'        => $attribute,
            'custom_attribute' => $custom_attribute,
            'meta_key'         => self::key((string) ($item['meta_key'] ?? '')),
            'label'            => self::text((string) ($item['label'] ?? '')),
        ];

        // نه ویژگیِ سراسری انتخاب شده، نه نامی برای ویژگیِ سفارشی — یعنی هیچی
        if ('attribute' === $source && 'custom' === $attribute && '' === $custom_attribute) {
            return null;
        }

        if ('custom_meta' === $source && '' === $clean['meta_key']) {
            return null;
        }

        return $clean;
    }

    /* =====================================================================
     * کمکی‌ها
     * =================================================================== */

    private static function taxonomy(string $value): string {
        $value = strtolower(trim($value));

        return 'custom' === $value ? 'custom' : (string) preg_replace('/[^a-z0-9_\-]/', '', $value);
    }

    private static function key(string $value): string {
        return (string) preg_replace('/[^a-zA-Z0-9_\-]/', '', trim($value));
    }

    private static function text(string $value): string {
        return trim(wp_strip_all_tags($value));
    }

    private static function name(string $value): string {
        return (string) preg_replace('/[^a-z0-9_\-]/', '', strtolower(trim($value)));
    }
}
