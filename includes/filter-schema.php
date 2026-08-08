<?php
namespace Zig3d_Widgets;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * تصمیم دربارهٔ اینکه یک دسته چه گروه‌های فیلتری نشان بدهد.
 *
 * سه راه وجود داشت و هر سه را سنجیدیم:
 *
 *   • فقط خودکار: هرچه در محصولاتِ این دسته هست نشان بده. درست کار می‌کند
 *     ولی مدیر هیچ اهرمی ندارد؛ ویژگی‌ای که تازه دارد اضافه می‌شود، تا
 *     اولین محصول ظاهر نمی‌شود.
 *   • فقط دستی: کنترل کامل، ولی با هر محصول تازه باید یادت باشد برگردی و
 *     فهرست را به‌روز کنی. چیزی که یادت می‌رود.
 *   • خودکار به‌علاوهٔ بازنویسی: هر دو.
 *
 * ولی «بازنویسی» جایی است که این‌جور معماری‌ها معمولاً می‌پوسند. اگر
 * لایه‌ها روی هم سوار شوند — طرح مشترک، ارث از دستهٔ والد، بازنویسی دسته،
 * بازنویسی خودِ ویجت — هیچ‌کس دیگر نمی‌تواند بگوید این گروه از کجا آمد.
 * پس عمداً فقط یک لایه بازنویسی وجود دارد و تخت است: روشن/خاموش و
 * «حتی اگر خالی بود نشان بده». هیچ لایهٔ دومی نیست.
 *
 * و چون حتی همان یک لایه هم می‌تواند گیج‌کننده شود، خروجی این کلاس علاوه
 * بر فهرست نهایی، ردّ تصمیم را هم برمی‌گرداند تا پنل بتواند صریح بگوید
 * «این دسته از طرح X می‌آید، با N بازنویسی».
 */
final class Filter_Schema {

    public const MODE_AUTO   = 'auto';
    public const MODE_SCHEMA = 'schema';

    /** شکل پیش‌فرض یک گروه فیلتر */
    private const FACET = [
        'taxonomy'   => '',
        'operator'   => Facets::OP_OR,
        'show_empty' => false,
        'semantics'  => Facets::SEMANTICS_PRODUCT,
    ];

    /* =====================================================================
     * حل نهایی
     * =================================================================== */

    /**
     * فهرست نهایی گروه‌های فیلتر برای یک دسته.
     *
     * @param array $binding    تنظیم ذخیره‌شدهٔ خودِ دسته.
     * @param array $schemas    طرح‌های مشترک، کلیدشده با نام.
     * @param array $discovered تاکسونومی‌هایی که واقعاً روی محصولات این دسته هست.
     *
     * @return array{facets:array<int,array{taxonomy:string,operator:string,show_empty:bool}>,mode:string,schema:string,overrides:int,notes:string[]}
     */
    public static function resolve(array $binding, array $schemas, array $discovered): array {
        $mode   = self::MODE_SCHEMA === ($binding['mode'] ?? self::MODE_AUTO) ? self::MODE_SCHEMA : self::MODE_AUTO;
        $name   = self::name((string) ($binding['schema'] ?? ''));
        $notes  = [];
        $base   = [];

        if (self::MODE_SCHEMA === $mode) {
            if (isset($schemas[$name]['facets'])) {
                $base = self::sanitize_facets((array) $schemas[$name]['facets']);
            } else {
                /*
                 * طرحی که پاک شده ولی هنوز جایی به آن ارجاع هست. سقوط به
                 * حالت خودکار عمدی است: سایدبار خالی، به چشم بازدیدکننده
                 * «فروشگاه خراب است» می‌آید، در حالی که یک فهرست خودکار
                 * تقریباً همیشه درست است. ولی این اتفاق در پنل باید دیده
                 * شود، وگرنه سال‌ها کسی نمی‌فهمد طرحش اعمال نمی‌شود.
                 */
                $mode    = self::MODE_AUTO;
                $notes[] = sprintf('طرح «%s» پیدا نشد؛ فهرست خودکار جایگزین شد.', $name);
                $name    = '';
            }
        }

        if (self::MODE_AUTO === $mode) {
            $base = self::sanitize_facets(self::from_discovered($discovered));
        }

        [$facets, $applied, $stale] = self::apply_overrides($base, (array) ($binding['overrides'] ?? []));

        foreach ($stale as $taxonomy) {
            $notes[] = sprintf('بازنویسی «%s» به گروهی اشاره می‌کند که دیگر در فهرست نیست.', $taxonomy);
        }

        return [
            'facets'    => $facets,
            'mode'      => $mode,
            'schema'    => $name,
            'overrides' => $applied,
            'notes'     => $notes,
        ];
    }

    /* =====================================================================
     * بازنویسی — تنها لایهٔ مجاز
     * =================================================================== */

    /**
     * اعمال دلتای تخت روی فهرست پایه.
     *
     * فقط دو چیز می‌تواند عوض شود: اینکه گروه اصلاً باشد یا نه، و اینکه
     * وقتی خالی است نشان داده شود یا نه. ترتیب، اپراتور و ساختار دست
     * نمی‌خورند — چون آن‌ها همان چیزی‌اند که «طرح مشترک» را مشترک نگه
     * می‌دارند. اگر دسته‌ای واقعاً ساختار متفاوتی می‌خواهد، طرح خودش را
     * دارد؛ نه یک زنجیرهٔ بازنویسی که کسی نتواند دنبالش را بگیرد.
     *
     * @return array{0:array,1:int,2:string[]} فهرست، تعداد اعمال‌شده، بازنویسی‌های بی‌هدف
     */
    private static function apply_overrides(array $facets, array $overrides): array {
        if (!$overrides) {
            return [$facets, 0, []];
        }

        $known   = array_column($facets, 'taxonomy');
        $applied = 0;
        $stale   = [];

        foreach ($overrides as $taxonomy => $rule) {
            $taxonomy = self::taxonomy((string) $taxonomy);

            if ('' === $taxonomy || !in_array($taxonomy, $known, true)) {
                if ('' !== $taxonomy) {
                    $stale[] = $taxonomy;
                }

                continue;
            }

            ++$applied;
        }

        $out = [];

        foreach ($facets as $facet) {
            $rule = $overrides[$facet['taxonomy']] ?? null;

            if (null === $rule) {
                $out[] = $facet;

                continue;
            }

            if (array_key_exists('enabled', (array) $rule) && !$rule['enabled']) {
                continue;
            }

            if (array_key_exists('show_empty', (array) $rule)) {
                $facet['show_empty'] = (bool) $rule['show_empty'];
            }

            $out[] = $facet;
        }

        return [$out, $applied, $stale];
    }

    /* =====================================================================
     * پاک‌سازی
     * =================================================================== */

    /**
     * فهرست کشف‌شده را به شکل گروه فیلتر درمی‌آورد.
     *
     * ورودی می‌تواند فهرست سادهٔ نام تاکسونومی‌ها باشد یا آرایه‌های کامل —
     * چون لایهٔ کشف بسته به اینکه برچسب‌ها را هم آورده باشد یا نه، یکی از
     * این دو شکل را می‌دهد.
     */
    private static function from_discovered(array $discovered): array {
        $facets = [];

        foreach ($discovered as $key => $item) {
            if (!is_array($item)) {
                $facets[] = ['taxonomy' => is_string($key) ? $key : (string) $item];

                continue;
            }

            // شکل «تاکسونومی ⇒ اطلاعاتش»: نام در کلید است نه داخل مقدار
            if (!isset($item['taxonomy']) && is_string($key)) {
                $item['taxonomy'] = $key;
            }

            $facets[] = $item;
        }

        return $facets;
    }

    /**
     * @param array<int,array> $facets
     * @return array<int,array{taxonomy:string,operator:string,show_empty:bool}>
     */
    public static function sanitize_facets(array $facets): array {
        $out  = [];
        $seen = [];

        foreach ($facets as $facet) {
            $facet   = is_array($facet) ? $facet : ['taxonomy' => $facet];
            $taxonomy = self::taxonomy((string) ($facet['taxonomy'] ?? ''));

            // گروه تکراری یعنی یک ویژگی دو بار در سایدبار — که علاوه بر
            // زشتی، دو منبع متناقض برای یک انتخاب می‌سازد.
            if ('' === $taxonomy || isset($seen[$taxonomy])) {
                continue;
            }

            $seen[$taxonomy] = true;

            $out[] = [
                'taxonomy'   => $taxonomy,
                'operator'   => Facets::OP_AND === ($facet['operator'] ?? '') ? Facets::OP_AND : Facets::OP_OR,
                'show_empty' => !empty($facet['show_empty']),
                /*
                 * پیش‌فرض «ویژگی محصول» است و امروز تنها مقدار پشتیبانی‌شده.
                 * وجودِ این کلید یک تصمیم رو به آینده است: فیلترهایی مثل
                 * «رنگ موجود» معنای دیگری دارند و باید مسیر خودشان را
                 * بگیرند، نه اینکه بی‌سروصدا سوار این یکی شوند.
                 */
                'semantics'  => Facets::SEMANTICS_VARIATION === ($facet['semantics'] ?? '')
                    ? Facets::SEMANTICS_VARIATION
                    : Facets::SEMANTICS_PRODUCT,
            ];
        }

        return $out;
    }

    /** اپراتورهای هر گروه، به شکلی که ‎Facets::tax_query()‎ می‌خواهد */
    public static function operators(array $facets): array {
        $operators = [];

        foreach ($facets as $facet) {
            $operators[$facet['taxonomy']] = $facet['operator'];
        }

        return $operators;
    }

    /** فقط نام تاکسونومی‌ها — همان فهرست سفیدِ خواندن آدرس */
    public static function taxonomies(array $facets): array {
        return array_column($facets, 'taxonomy');
    }

    private static function taxonomy(string $value): string {
        return (string) preg_replace('/[^a-z0-9_\-]/', '', strtolower(trim($value)));
    }

    private static function name(string $value): string {
        return (string) preg_replace('/[^a-z0-9_\-]/', '', strtolower(trim($value)));
    }
}
