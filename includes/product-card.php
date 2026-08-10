<?php
namespace Zig3d_Widgets;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * جمع‌کردن دادهٔ یک کارت محصول، پیش از رندر.
 *
 * چرا جدا از رندر: کارت هشت بخش دارد و هر بخش می‌تواند نباشد — رپیتر پر
 * نشده، برند تعریف نشده، توضیح خالی مانده. اگر این تصمیم‌ها لای مارک‌آپ
 * پخش شوند، هر کدام یک ‎if‎ می‌شود که فقط با نگاه‌کردن به صفحه پیدا
 * می‌شود، و «کارت شکسته» چیزی است که در تست رندر هم درست به نظر می‌رسد.
 *
 * پس اینجا تصمیم گرفته می‌شود چه چیزی هست، و آنجا فقط چاپ می‌شود.
 *
 * خروجی همیشه همان کلیدها را دارد، حتی وقتی خالی‌اند. یعنی رندر می‌تواند
 * بی‌قید و شرط بخواند و خودش تصمیم بگیرد که خالی را چاپ نکند — به‌جای
 * اینکه هر بار ‎isset‎ بزند و یکی را جا بیندازد.
 *
 * و هیچ‌چیز اینجا اسکیپ نمی‌شود. مقدارها خام برمی‌گردند و اسکیپ در لحظهٔ
 * خروجی انجام می‌شود — همان قاعدهٔ «دیر اسکیپ کن» که وردپرس توصیه می‌کند.
 * دلیلش این است که اسکیپ به *زمینه* وابسته است: همان رشته در متن
 * ‎esc_html()‎ می‌خواهد، در صفت ‎esc_attr()‎، و در ‎href‎ چیز دیگری. اگر
 * اینجا اسکیپ شود، یا در جای اشتباه اسکیپ شده یا جای دیگری دوباره اسکیپ
 * می‌شود و کاربر ‎&amp;‎ می‌بیند.
 *
 * به همین دلیل هیچ مارک‌آپی هم اینجا ساخته نمی‌شود: تصویر فقط شناسه است، نه
 * تگ ‎<img>‎. غیر از تفکیک تمیزتر، همین باعث می‌شود این داده برای پاسخ
 * AJAX و برای دادهٔ ساختاریافته هم قابل استفاده باشد.
 *
 * تنها استثنا ‎wp_strip_all_tags()‎ روی توضیح است، و آن اسکیپ نیست بلکه
 * *عادی‌سازیِ محتواست*: آن جایگاه یک خط متن ساده است و اگر متایی تگ داشته
 * باشد، خروجیِ اسکیپ‌شده‌اش تگ‌های قابل‌دیدن به کاربر نشان می‌داد.
 */
final class Product_Card {

    /** شکل خالی، تا هر مسیر بازگشتی همان کلیدها را داشته باشد */
    private const EMPTY = [
        'id'          => 0,
        'url'         => '',
        'title'       => '',
        'image'       => 0,
        'suggested'   => false,
        'brand'       => '',
        'category'    => '',
        'description' => '',
        'features'    => [],
        'stock'       => [],
        'price'       => [],
        'price_mode'  => Card::PRICE_HIDDEN,
        'price_label' => '',
        'cta'         => Card::CTA_DETAILS,
    ];

    /**
     * دادهٔ کارت.
     *
     * @param array $fields {
     *     @type string $suggested_meta   کلید متای چک‌باکس «پیشنهاد».
     *     @type string $description_meta کلید متای توضیح کوتاه.
     *     @type string $features_meta    کلید متای رپیتر ویژگی‌ها.
     *     @type string $features_field   نام زیرفیلدِ برچسب داخل رپیتر.
     *     @type string $brand_taxonomy   تاکسونومی برند.
     *     @type int    $features_max     سقف ویژگی‌ها.
     *     @type string $variable_mode    ‎min‎ یا ‎range‎ برای محصول چندقیمتی.
     *     @type string $no_price         ‎inquiry‎ یا ‎hidden‎.
     *     @type string $cta_mode         ‎auto‎ یا یکی از حالت‌های صریح.
     * }
     */
    public static function data(\WC_Product $product, array $fields = []): array {
        $fields += [
            'suggested_meta'   => '',
            'description_meta' => '',
            'features_meta'    => '',
            'features_field'   => '',
            'brand_taxonomy'   => '',
            'features_attrs'   => [],
            'features_max'     => 3,
            'variable_mode'    => 'min',
            'no_price'         => Card::PRICE_INQUIRY,
            'cta_mode'         => Card::CTA_AUTO,
        ];

        $id    = $product->get_id();
        /*
         * مسیر سبک، عمداً. توضیح کاملش در ‎Price::data()‎ است؛ خلاصه‌اش این
         * است که مسیر عمیق برای پانزده کارت، صدها بارگذاری محصول می‌شود.
         */
        $price = Price::data($product, (string) $fields['variable_mode'], false);
        $state = Card::price_state($price, (string) $fields['no_price']);

        return [
            'id'          => $id,
            'url'         => (string) get_permalink($id),
            'title'       => (string) $product->get_name(),
            'image'       => (int) get_post_thumbnail_id($id),
            'suggested'   => self::flag($id, (string) $fields['suggested_meta']),
            'brand'       => self::brand($id, (string) $fields['brand_taxonomy']),
            'category'    => self::category($id),
            'description' => self::text($id, (string) $fields['description_meta']),
            'features'    => self::features($product, $fields),
            'stock'       => Stock::state($product),
            'price'       => $price,
            'price_mode'  => $state['mode'],
            'price_label' => $state['label'],
            'cta'         => Card::cta_mode((string) $fields['cta_mode'], $state['mode']),
        ] + self::EMPTY;
    }

    /* =====================================================================
     * بخش‌ها
     * =================================================================== */

    /**
     * چک‌باکسِ «پیشنهاد».
     *
     * مقادیری که وردپرس برای یک چک‌باکس ذخیره می‌کند یکدست نیستند: بسته به
     * اینکه فیلد از جت‌انجین آمده یا از یک متاباکس دستی، می‌تواند ‎'1'‎ یا
     * ‎'yes'‎ یا ‎'on'‎ یا ‎true‎ باشد. پذیرفتن فقط یکی از این‌ها یعنی ریبون
     * روی نصف محصول‌ها بی‌صدا نیاید.
     */
    private static function flag(int $id, string $key): bool {
        if ('' === $key) {
            return false;
        }

        $value = get_post_meta($id, $key, true);

        if (is_bool($value) || is_int($value)) {
            return (bool) $value;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'yes', 'true', 'on'], true);
    }

    private static function text(int $id, string $key): string {
        if ('' === $key) {
            return '';
        }

        return trim(wp_strip_all_tags((string) get_post_meta($id, $key, true)));
    }

    /**
     * برند: اولین ترمِ تاکسونومیِ تعیین‌شده.
     *
     * فقط اولی، عمداً. یک محصول می‌تواند چند ترم داشته باشد ولی کارت جای
     * یکی را دارد؛ چاپ‌کردن همه، چیدمان را می‌شکند و چاپ‌نکردن هیچ‌کدام
     * اطلاعات را دور می‌ریزد.
     */
    /**
     * دستهٔ محصول، برای خطِ بالای عنوان.
     *
     * عمیق‌ترین دسته انتخاب می‌شود، نه اولی که ووکامرس برمی‌گرداند: محصولی
     * که هم در «فرز CNC» است و هم در زیرشاخهٔ «۵ محور»، باید مشخص‌ترش را
     * نشان بدهد. اولی معمولاً کلی‌ترین است و هیچ چیزی به کاربر نمی‌گوید.
     *
     * ‎product_visibility‎ و ترم‌های داخلی خودبه‌خود کنار می‌مانند چون فقط
     * از ‎product_cat‎ می‌پرسیم.
     */
    private static function category(int $id): string {
        $terms = get_the_terms($id, Schema_Store::TAXONOMY);

        if (!is_array($terms)) {
            return '';
        }

        $best  = null;
        $depth = -1;

        foreach ($terms as $term) {
            if (!$term instanceof \WP_Term) {
                continue;
            }

            $level = count(get_ancestors($term->term_id, Schema_Store::TAXONOMY, 'taxonomy'));

            if ($level > $depth) {
                $depth = $level;
                $best  = $term;
            }
        }

        return $best ? (string) $best->name : '';
    }

    private static function brand(int $id, string $taxonomy): string {
        if ('' === $taxonomy) {
            return '';
        }

        $terms = get_the_terms($id, $taxonomy);

        if (!is_array($terms)) {
            return '';
        }

        foreach ($terms as $term) {
            if ($term instanceof \WP_Term) {
                return $term->name;
            }
        }

        return '';
    }

    /**
     * سه ویژگی وسط کارت، با زنجیرهٔ منبع.
     *
     * ترتیب عمدی است: رپیتر جت‌انجین ← ویژگی‌های ووکامرس ← هیچ. محصولی که
     * هنوز رپیترش پر نشده نباید کارتِ شکسته بدهد.
     */
    private static function features(\WC_Product $product, array $fields): array {
        return Card::features(
            [
                self::repeater($product->get_id(), (string) $fields['features_meta'], (string) $fields['features_field']),
                self::attributes($product, (array) $fields['features_attrs']),
            ],
            (int) $fields['features_max']
        );
    }

    /**
     * برچسب‌های یک رپیتر جت‌انجین.
     *
     * ‎$field‎ خالی یعنی «خودِ ردیف را متن حساب کن» — رپیترهای تک‌فیلدی
     * گاهی همین شکل ذخیره می‌شوند و بدون این حالت، فقط یک آرایه به رشته
     * تبدیل می‌شد.
     */
    private static function repeater(int $id, string $key, string $field): array {
        if ('' === $key) {
            return [];
        }

        $rows = get_post_meta($id, $key, true);

        if (!is_array($rows)) {
            return [];
        }

        $labels = [];

        foreach ($rows as $row) {
            if (is_array($row)) {
                $labels[] = (string) ('' === $field ? reset($row) : ($row[$field] ?? ''));

                continue;
            }

            $labels[] = (string) $row;
        }

        return $labels;
    }

    /**
     * ویژگی‌های ووکامرس، به‌عنوان منبع دوم.
     *
     * فهرست صریح مقدم است و ترتیبش هم رعایت می‌شود: کارت سه حباب دارد و
     * «کدام سه‌تا» یک تصمیم طراحی است، نه چیزی که باید از ترتیب ذخیره‌سازی
     * دربیاید.
     *
     * وقتی فهرستی نیست، دو شرط اعمال می‌شود و هر دو لازم‌اند:
     *
     *   • ‎get_visible()‎ — یعنی مدیر گفته این را به مشتری نشان بده. ولی
     *     دقیقاً همین و بس. سند خودِ ووکامرس می‌گوید
     *     «If is visible on Product's additional info tab» — نه «فنی
     *     است»، نه «گزینه‌ساز نیست». استفاده از آن به‌عنوان معیار
     *     «ویژگی فنی»، دو چیز بی‌ربط را یکی گرفتن است.
     *
     *   • ‎!get_variation()‎ — یعنی این ویژگی گزینهٔ خرید نمی‌سازد. رنگ و
     *     سایز در کارت یک دستگاه صنعتی معنایی ندارند؛ آن‌ها انتخاب‌های
     *     خریدند نه مشخصهٔ دستگاه.
     *
     * این دو در ووکامرس محورهای مستقل‌اند و یک ویژگی می‌تواند هر دو را
     * داشته باشد. جداکردنشان همان تفاوت «آنچه نمایش داده می‌شود» و
     * «آنچه خریدنی است» است.
     *
     * @param string[] $allowed فهرست صریح تاکسونومی‌ها؛ خالی یعنی خودکار.
     */
    private static function attributes(\WC_Product $product, array $allowed = []): array {
        if (!method_exists($product, 'get_attributes')) {
            return [];
        }

        /*
         * «خالی» یعنی واقعاً خالی، نه «یک عضوِ خالی».
         *
         * کنترل چندانتخابیِ المنتور وقتی چیزی انتخاب نشده ‎''‎ برمی‌گرداند
         * نه ‎[]‎، و ‎(array) ''‎ می‌شود ‎['']‎ — آرایه‌ای که *ناخالی* است.
         * نتیجه‌اش این بود که حالت خودکار بی‌صدا خاموش می‌شد و نوار
         * ویژگی‌ها روی هر سایتی که این فیلد را دست نزده بود غیبش می‌زد؛
         * چیزی که در تست رندر هم درست به نظر می‌رسید، چون کارت فقط یک
         * بخش کمتر داشت.
         *
         * تعریفِ «خالی» مال همین‌جاست، پس پاکسازی هم همین‌جاست نه در
         * فراخواننده — وگرنه هر فراخوانندهٔ تازه باید دوباره یادش بماند.
         */
        $allowed = array_values(array_filter(
            array_map(static fn($name): string => trim((string) $name), $allowed),
            static fn(string $name): bool => '' !== $name
        ));

        $attributes = [];

        foreach ($product->get_attributes() as $attribute) {
            if (!is_object($attribute) || !method_exists($attribute, 'get_name')) {
                continue;
            }

            $attributes[(string) $attribute->get_name()] = $attribute;
        }

        $order = $allowed ?: array_keys(array_filter($attributes, [self::class, 'is_feature']));
        $labels = [];

        foreach ($order as $name) {
            $attribute = $attributes[(string) $name] ?? null;

            if (null === $attribute) {
                continue;
            }

            $term = self::first_term($product->get_id(), $attribute);

            if ('' !== $term) {
                $labels[] = $term;

                continue;
            }

            $labels[] = (string) $attribute->get_name();
        }

        return $labels;
    }

    /** ویژگی‌ای که در حالت خودکار به کارت می‌آید */
    private static function is_feature($attribute): bool {
        if (!method_exists($attribute, 'get_visible') || !$attribute->get_visible()) {
            return false;
        }

        return !method_exists($attribute, 'get_variation') || !$attribute->get_variation();
    }

    private static function first_term(int $id, $attribute): string {
        if (!method_exists($attribute, 'is_taxonomy') || !$attribute->is_taxonomy()) {
            $options = method_exists($attribute, 'get_options') ? (array) $attribute->get_options() : [];

            return (string) (reset($options) ?: '');
        }

        $terms = get_the_terms($id, (string) $attribute->get_name());

        if (!is_array($terms)) {
            return '';
        }

        foreach ($terms as $term) {
            if ($term instanceof \WP_Term) {
                return $term->name;
            }
        }

        return '';
    }
}
