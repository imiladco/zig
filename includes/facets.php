<?php
namespace Zig3d_Widgets;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * شمارش و وضعیتِ گزینه‌های فیلتر.
 *
 * قاعدهٔ اصلی، «خودحذف‌کن» بودن است: عددی که کنار هر گزینه می‌آید باید به
 * این سؤال جواب بدهد — «اگر این را هم بزنم، چند نتیجه می‌ماند؟» — و برای
 * جواب‌دادن به آن، قیدِ خودِ همان گروه نباید در محاسبه باشد.
 *
 * چرا این‌قدر مهم است: با شمارشِ ساده‌لوحانه (AND روی همه‌چیز، از جمله خودِ
 * گروه) به‌محض اینکه کاربر «UP3D» را بزند، بقیهٔ برندها صفر می‌شوند. یعنی
 * دقیقاً همان لحظه‌ای که چندانتخابی معنا پیدا می‌کند، رابط می‌گوید انتخاب
 * دوم بی‌فایده است. این تنها باگی نیست که خروجی درست به نظر می‌رسد و
 * تجربه را خراب می‌کند، ولی از رایج‌ترین‌هاست.
 *
 * پس برای هر گروه ‎F‎:
 *
 *     شمارشِ گزینهٔ X در F
 *         = کوئری پایهٔ آرشیو
 *         + همهٔ گروه‌های فعال به‌جز F
 *         + گزینهٔ X
 *
 * و «کوئری پایهٔ آرشیو» یعنی کل قیدهای ثابت — دسته، وضعیت انتشار، دیده‌شدن
 * در فهرست، سیاست نمایش ناموجود — نه فقط چک‌باکس‌ها. جاافتادن همین بند،
 * پیاده‌سازی‌ای می‌سازد که ظاهراً درست است ولی عددهایش با فهرست نمی‌خواند.
 *
 * ترکیب انتخاب‌ها: درون یک گروه ‎OR‎، بین گروه‌ها ‎AND‎. یعنی
 * ‎(UP3D یا VHF) و (۵ محور) و (خشک یا تر)‎ — که همان چیزی است که کاربر از
 * تیک‌زدن دو برند انتظار دارد.
 */
final class Facets {

    /** انتخاب چندگانه درون یک گروه: هرکدام کافی است */
    public const OP_OR = 'or';

    /**
     * انتخاب چندگانه درون یک گروه: همه با هم لازم‌اند.
     *
     * برای ویژگی‌هایی که واقعاً جمع‌شدنی‌اند (مثلاً «سازگار با X» و «سازگار
     * با Y» روی یک دستگاه). پیش‌فرض نیست چون در بیشتر ویژگی‌ها نتیجه را به
     * صفر می‌رساند و کاربر فکر می‌کند فروشگاه خالی است.
     */
    public const OP_AND = 'and';

    /** نام جدول جست‌وجوی ویژگی‌های ووکامرس، بدون پیشوند */
    public const LOOKUP_TABLE = 'wc_product_attributes_lookup';

    /* =====================================================================
     * برنامهٔ شمارش
     * =================================================================== */

    /**
     * قیدهایی که برای شمردن گزینه‌های یک گروه باید اعمال شوند.
     *
     * همان بند ۲ الگوریتم: خودِ گروه کنار می‌رود، بقیه می‌مانند.
     */
    public static function constraints_for(Query_State $state, string $facet): Query_State {
        return $state->without($facet);
    }

    /**
     * کلید کشِ شمارش یک گروه.
     *
     * کلید عمداً از وضعیتِ خودحذف‌شده ساخته می‌شود، نه از وضعیت خام. اگر
     * وضعیت خام مبنا بود، شمارشِ برند برای ‎brand=up3d + axis=5‎ و برای
     * ‎brand=vhf + axis=5‎ دو ورودی جدا می‌گرفت، در حالی که هر دو دقیقاً یک
     * عدد می‌دهند — چون برند در محاسبهٔ برند حذف می‌شود. یعنی هم کش بیهوده
     * بزرگ می‌شد و هم نرخ اصابتش نصف.
     *
     * ‎$context‎ باید هر چیزی را که کوئری پایه را عوض می‌کند در خود داشته
     * باشد: دسته، جست‌وجو، و سیاست نمایش ناموجود.
     */
    public static function cache_key(string $context, string $facet, Query_State $state): string {
        return sprintf(
            'zig3d_fc_%s_%s_%s',
            substr(md5($context), 0, 8),
            substr(md5($facet), 0, 6),
            self::constraints_for($state, $facet)->fingerprint()
        );
    }

    /* =====================================================================
     * ترجمه به کوئری وردپرس
     * =================================================================== */

    /**
     * ‎tax_query‎ متناظر با وضعیت.
     *
     * @param array<string,string> $operators تاکسونومی ⇒ ‎or‎ / ‎and‎
     */
    public static function tax_query(Query_State $state, array $operators = []): array {
        $clauses = [];

        foreach ($state->filters() as $taxonomy => $terms) {
            $operator = ($operators[$taxonomy] ?? self::OP_OR) === self::OP_AND ? 'AND' : 'IN';

            $clauses[] = [
                'taxonomy'         => $taxonomy,
                'field'            => 'slug',
                'terms'            => $terms,
                'operator'         => $operator,
                'include_children' => false,
            ];
        }

        // بین گروه‌ها همیشه AND — این همان چیزی است که «باریک‌کردن نتیجه»
        // را معنادار می‌کند.
        return count($clauses) > 1 ? array_merge(['relation' => 'AND'], $clauses) : $clauses;
    }

    /* =====================================================================
     * SQL شمارش
     * =================================================================== */

    /**
     * کوئریِ گروهیِ شمارش برای یک گروه فیلتر.
     *
     * واحد بهینه‌سازی «گروه» است نه «گزینه». یعنی برای گروهی با پنجاه برند،
     * یک کوئری با ‎GROUP BY‎ اجرا می‌شود، نه پنجاه کوئری. این تفاوت روی
     * کاغذ کوچک به نظر می‌رسد و در عمل تفاوت بین صفحه‌ای است که باز می‌شود
     * و صفحه‌ای که تایم‌اوت می‌دهد.
     *
     * ‎COUNT(DISTINCT …)‎ هم اختیاری نیست: یک محصول در جدول جست‌وجو به ازای
     * هر ترکیب تاکسونومی/ترم یک ردیف دارد، و محصول متغیر به ازای هر گزینه.
     * بدون ‎DISTINCT‎، محصولی که چند گزینه دارد چند بار شمرده می‌شود و
     * عددها بی‌سروصدا از تعداد واقعی بزرگ‌تر می‌شوند.
     *
     * @param string $table    نام کامل جدول، با پیشوند دیتابیس.
     * @param string $base_sql زیرکوئری‌ای که شناسهٔ محصولات پایه را می‌دهد.
     */
    public static function count_sql(string $table, string $base_sql, bool $in_stock_only = false): string {
        $sql = 'SELECT lookup.term_id AS term_id, COUNT(DISTINCT lookup.product_or_parent_id) AS product_count'
            . ' FROM ' . $table . ' AS lookup'
            . ' WHERE lookup.taxonomy = %s'
            . ' AND lookup.product_or_parent_id IN (' . $base_sql . ')';

        if ($in_stock_only) {
            $sql .= ' AND lookup.in_stock = 1';
        }

        return $sql . ' GROUP BY lookup.term_id';
    }

    /**
     * همان شمارش، از روی رابطهٔ ترم‌ها — برای وقتی جدول جست‌وجو خاموش است.
     *
     * دقیقاً معادلِ مسیر بالا نیست و نباید وانمود کند که هست: ویژگی‌هایی که
     * فقط روی گزینه‌های یک محصول متغیر تعریف شده‌اند اینجا دیده نمی‌شوند، و
     * ستون موجودی هم وجود ندارد. یعنی عددها می‌توانند از واقعیت کمی
     * بزرگ‌تر باشند.
     *
     * پس این مسیر، جایگزین نیست؛ فقط جلوی «سایدبار بدون هیچ عددی» را
     * می‌گیرد تا وقتی مدیر جدول را روشن کند. پنل باید این را صریح بگوید.
     */
    public static function count_sql_terms(string $relationships, string $taxonomies, string $base_sql): string {
        return 'SELECT tt.term_id AS term_id, COUNT(DISTINCT tr.object_id) AS product_count'
            . ' FROM ' . $relationships . ' AS tr'
            . ' INNER JOIN ' . $taxonomies . ' AS tt ON tt.term_taxonomy_id = tr.term_taxonomy_id'
            . ' WHERE tt.taxonomy = %s'
            . ' AND tr.object_id IN (' . $base_sql . ')'
            . ' GROUP BY tt.term_id';
    }

    /* =====================================================================
     * وضعیت نمایشیِ گزینه‌ها
     * =================================================================== */

    /**
     * ترکیب فهرست ترم‌ها با شمارش، و تصمیم دربارهٔ حالت هر گزینه.
     *
     * سه قاعده، که دومی و سومی مهم‌ترند:
     *
     *   • شمارش > ۰ ⇒ عادی.
     *   • شمارش = ۰ و انتخاب‌نشده ⇒ می‌ماند ولی غیرفعال. حذف‌نکردنش عمدی
     *     است: با هر درخواست، ارتفاع سایدبار عوض می‌شد و گزینه‌ها زیر دست
     *     کاربر بالا و پایین می‌پریدند.
     *   • شمارش = ۰ و انتخاب‌شده ⇒ همچنان فعال. اگر این استثنا نباشد، کاربر
     *     می‌تواند به بن‌بستی برسد که گزینه‌ای تیک خورده، نتیجه صفر شده، و
     *     خودِ آن گزینه غیرفعال شده — یعنی راهی برای برداشتنِ تیک نمانده جز
     *     ریست‌کردن همه‌چیز.
     *
     * ‎$counts === null‎ یعنی شمارش در دسترس نیست (کش خالی، جدول جست‌وجوی
     * ووکامرس خاموش، یا کاتالوگ بزرگ‌تر از سقف). آن‌وقت هیچ عددی نشان داده
     * نمی‌شود و هیچ گزینه‌ای غیرفعال نمی‌شود. عددِ غلط از نبودِ عدد بدتر
     * است: کنار هر گزینه یک وعده می‌نشیند، و وعدهٔ نادرست بدتر از سکوت است.
     *
     * @param array<int,array{slug:string,label:string,term_id?:int}> $terms
     * @param array<string,int>|null                                  $counts اسلاگ ⇒ تعداد
     * @param string[]                                                $selected
     * @return array<int,array{slug:string,label:string,term_id:int,count:int|null,selected:bool,disabled:bool}>
     */
    public static function options(array $terms, ?array $counts, array $selected): array {
        $options = [];

        foreach ($terms as $term) {
            $slug = (string) ($term['slug'] ?? '');

            if ('' === $slug) {
                continue;
            }

            $is_selected = in_array($slug, $selected, true);
            $count       = null === $counts ? null : (int) ($counts[$slug] ?? 0);

            $options[] = [
                'slug'     => $slug,
                'label'    => (string) ($term['label'] ?? $slug),
                'term_id'  => (int) ($term['term_id'] ?? 0),
                'count'    => $count,
                'selected' => $is_selected,
                'disabled' => (null !== $count && 0 === $count && !$is_selected),
            ];
        }

        return $options;
    }

    /**
     * آیا این گروه اصلاً رندر شود؟
     *
     * گروهی که همهٔ گزینه‌هایش صفر است و هیچ انتخابی هم ندارد، فقط فضا
     * می‌گیرد. ولی وقتی مدیر آن را عمداً پین کرده («حتی اگر خالی بود نشان
     * بده»)، تصمیمِ او بر این حساب می‌چربد — معمولاً چون ویژگی تازه‌ای است
     * که محصولاتش دارند اضافه می‌شوند.
     *
     * @param array<int,array{count:int|null,selected:bool}> $options
     */
    public static function group_is_visible(array $options, bool $show_empty = false): bool {
        if ($show_empty) {
            return [] !== $options;
        }

        foreach ($options as $option) {
            if ($option['selected']) {
                return true;
            }

            // شمارشِ در دسترس نبودن، دلیلِ پنهان‌کردن نیست
            if (null === $option['count'] || $option['count'] > 0) {
                return true;
            }
        }

        return false;
    }
}
