<?php
namespace Zig3d_Widgets;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * منطق قیمت ووکامرس، جدا از المنتور.
 *
 * چرا جدا: قیمت پرریسک‌ترین چیزی است که این افزونه نمایش می‌دهد. عدد غلط
 * یعنی مشتری چیزی می‌بیند که نمی‌تواند بخرد، یا فروشگاه چیزی می‌فروشد که
 * نمی‌خواسته. وقتی این منطق داخل متد رندرِ یک ویجت المنتور باشد، تنها راه
 * سنجیدنش بالا آوردن کل المنتور است — که در عمل یعنی سنجیده نمی‌شود.
 *
 * اینجا هیچ وابستگی‌ای به المنتور نیست: ورودی یک ‎WC_Product‎ است و خروجی یک
 * آرایهٔ ساده. یعنی هر شاخهٔ منطق — متغیر، گروهی، تخفیف، مالیات، رایگان —
 * مستقیم قابل تست است.
 */
final class Price {

    /**
     * سقف تعداد گزینه‌ای که برای پیدا کردن ارزان‌ترین، بارگذاری می‌شود.
     *
     * محصولی با صدها گزینه نباید صدها آبجکت بسازد. از یک حدی به بعد ارزشِ
     * «قیمت پیشینِ دقیقاً همان گزینه» هزینهٔ لود همه را توجیه نمی‌کند و به
     * مقدار تجمیعیِ خودِ ووکامرس اکتفا می‌شود.
     */
    private const MAX_VARIATIONS = 60;

    /** شکل خالیِ خروجی، تا هر مسیر بازگشتی همان کلیدها را داشته باشد */
    private const EMPTY = [
        'has_price' => false,
        'is_free'   => false,
        'is_multi'  => false,
        'is_range'  => false,
        'on_sale'   => false,
        'current'   => '',
        'old'       => '',
        'max'       => '',
        'percent'   => 0,
        'saved'     => '',
    ];

    /* =====================================================================
     * پیدا کردن محصول
     * =================================================================== */

    /**
     * محصولی که باید قیمتش نمایش داده شود.
     *
     * ترتیب عمدی است و از «مشخص‌ترین» به «کلی‌ترین» می‌رود:
     *
     *   ۱. شناسه‌ای که کاربر صریحاً داده.
     *   ۲. متغیر سراسری ‎$product‎ — چیزی که ووکامرس در حلقهٔ فروشگاه و
     *      المنتور در قالب حلقه ست می‌کنند. بدون این، ویجت داخل یک Loop
     *      Grid همیشه قیمت یک محصول ثابت را نشان می‌داد.
     *   ۳. آبجکت کوئری‌شده، برای صفحهٔ تکیِ محصول.
     *   ۴. پستِ جاری، برای قالب‌های سفارشی.
     */
    public static function resolve(int $product_id = 0) {
        if (!function_exists('wc_get_product')) {
            return null;
        }

        if ($product_id > 0) {
            return self::valid(wc_get_product($product_id));
        }

        if (isset($GLOBALS['product']) && $GLOBALS['product'] instanceof \WC_Product) {
            return self::valid($GLOBALS['product']);
        }

        if (function_exists('is_product') && is_product()) {
            $queried = get_queried_object_id();

            if ($queried) {
                return self::valid(wc_get_product($queried));
            }
        }

        $current = get_the_ID();

        return $current ? self::valid(wc_get_product($current)) : null;
    }

    /** فقط محصولی که واقعاً وجود دارد */
    private static function valid($product) {
        return ($product instanceof \WC_Product && $product->exists()) ? $product : null;
    }

    /* =====================================================================
     * محاسبهٔ قیمت
     * =================================================================== */

    /**
     * دادهٔ قیمت، آمادهٔ نمایش.
     *
     * ‎$deep‎ تفاوت «یک محصول» و «پانزده کارت» است.
     *
     * برای محصول چندقیمتی، مسیر عمیق همهٔ گزینه‌ها را با ‎wc_get_product()‎
     * بار می‌کند تا ارزان‌ترینِ *قابل خرید* و قیمت پیشینِ دقیقاً همان گزینه
     * را پیدا کند. روی صفحهٔ یک محصول این درست‌ترین کار است.
     *
     * در یک گرید فاجعه است: پانزده کارت × تا شصت گزینه یعنی صدها بارگذاری
     * محصول در یک درخواست. مسیر سبک به‌جایش مقدار تجمیعیِ خودِ ووکامرس را
     * می‌گیرد که در ترنزینت ‎wc_var_prices_{id}‎ کش شده و عملاً رایگان است.
     *
     * هزینه‌اش: بج تخفیف روی محصول متغیر در گرید نمی‌آید. که اتفاقاً ضرر
     * نیست — همان بج از روی مقادیر تجمیعی، درصدی می‌ساخت که هیچ گزینه‌ای
     * واقعاً نداشت (توضیحش در ‎aggregate()‎).
     *
     * @param string $variable_mode 'min' یا 'range' — فقط برای محصول متغیر و گروهی معنا دارد.
     * @param bool   $deep          گزینه‌ها تک‌تک خوانده شوند؟ در فهرست، نه.
     * @return array{has_price:bool,is_free:bool,is_multi:bool,is_range:bool,on_sale:bool,current:string,old:string,max:string,percent:int,saved:string}
     */
    public static function data(\WC_Product $product, string $variable_mode = 'min', bool $deep = true): array {
        $multi = self::multi_price_type($product);

        $data = $multi
            ? self::multi_data($product, $variable_mode, $deep)
            : self::simple_data($product);

        /*
         * ویجت باید بداند قیمت از محصولی با چند قیمت آمده یا نه — وگرنه
         * پیشوندِ «شروع از» روی محصول ساده هم چاپ می‌شود و به مشتری می‌گوید
         * قیمت‌های دیگری هم هست که وجود ندارند.
         */
        $data['is_multi'] = $multi;

        return self::finalize($data);
    }

    /**
     * محصولی که قیمتش از فرزندانش می‌آید، نه از خودش.
     *
     * محصول گروهی هم مثل متغیر است: ‎get_price()‎ رشتهٔ خالی برمی‌گرداند و
     * قیمت باید از فرزندان بیاید. نسخه‌های قبلی فقط متغیر را می‌شناختند و
     * محصول گروهی بی‌قیمت رندر می‌شد.
     */
    private static function multi_price_type(\WC_Product $product): bool {
        return $product->is_type('variable') || $product->is_type('grouped');
    }

    private static function multi_data(\WC_Product $product, string $variable_mode, bool $deep = true): array {
        $data = self::EMPTY;

        if ('range' === $variable_mode) {
            $range = self::price_range($product, $deep);

            if ('' !== $range['min'] && '' !== $range['max'] && (float) $range['max'] > (float) $range['min']) {
                $data['current']   = $range['min'];
                $data['max']       = $range['max'];
                $data['is_range']  = true;
                $data['has_price'] = true;

                return $data;
            }
            // بازه‌ای در کار نیست (همهٔ گزینه‌ها یک قیمت دارند) → مثل حالت min
        }

        /*
         * قیمت فعلی و پیشین باید از یک گزینهٔ واحد بیایند.
         *
         * کمترین قیمتِ فعال و کمترین قیمتِ عادی می‌توانند متعلق به دو گزینهٔ
         * متفاوت باشند؛ مقایسهٔ آن دو، درصد تخفیفی می‌سازد که هیچ گزینه‌ای
         * واقعاً ندارد. این خطا در نگاه اول دیده نمی‌شود چون عدد «معقول»
         * به نظر می‌رسد.
         */
        /*
         * مسیر سبک مستقیم سراغ مقدار تجمیعی می‌رود. بدون قیمت پیشین، که
         * عمدی است و در ‎aggregate()‎ توضیح داده شده.
         */
        $cheapest = $deep ? self::cheapest_child($product) : self::aggregate($product);

        $data['current'] = $cheapest['price'];

        if ('' !== $cheapest['regular'] && (float) $cheapest['regular'] > (float) $cheapest['price']) {
            $data['old']     = $cheapest['regular'];
            $data['on_sale'] = true;
        }

        return $data;
    }

    private static function simple_data(\WC_Product $product): array {
        $data = self::EMPTY;

        $regular = $product->get_regular_price();
        $sale    = $product->get_sale_price();

        if ($product->is_on_sale() && '' !== $sale && null !== $sale) {
            $data['current'] = self::display($product, $sale);
            $data['old']     = self::display($product, $regular);
            $data['on_sale'] = '' !== $data['old'];

            return $data;
        }

        // محصولی که فقط ‎_price‎ دارد و ‎_regular_price‎ ندارد نادر ولی ممکن است
        $base = ('' === $regular || null === $regular) ? $product->get_price() : $regular;

        $data['current'] = self::display($product, $base);

        return $data;
    }

    /** پرکردن فیلدهای مشتق‌شده از روی مقادیر خام */
    private static function finalize(array $data): array {
        $data['has_price'] = ('' !== $data['current']);

        if (!$data['has_price']) {
            return $data;
        }

        // «۰» یعنی رایگان، نه «بی‌قیمت». تفاوتشان مهم است: اولی باید «رایگان»
        // نشان دهد و دومی باید کلاً پنهان شود یا «تماس بگیرید» بدهد.
        $data['is_free'] = (0.0 === (float) $data['current']);

        if ($data['on_sale'] && '' !== $data['old']) {
            $old = (float) $data['old'];
            $now = (float) $data['current'];

            if ($old > 0 && $old > $now) {
                $data['percent'] = (int) round(($old - $now) / $old * 100);
                $data['saved']   = (string) ($old - $now);
            } else {
                // قیمت پیشینِ کوچک‌تر یا مساوی، تخفیف نیست
                $data['on_sale'] = false;
                $data['old']     = '';
            }
        }

        return $data;
    }

    /* =====================================================================
     * کمکی‌های محصول چندقیمتی
     * =================================================================== */

    /**
     * کمترین و بیشترین قیمت.
     *
     * برای محصول متغیر از مقدار تجمیعیِ خودِ ووکامرس استفاده می‌شود که کش
     * دارد؛ برای گروهی باید فرزندان پیمایش شوند چون ووکامرس چنین مقداری
     * برایشان نگه نمی‌دارد.
     *
     * @return array{min:string,max:string}
     */
    private static function price_range(\WC_Product $product, bool $deep = true): array {
        if ($product->is_type('variable') && method_exists($product, 'get_variation_price')) {
            return [
                'min' => (string) $product->get_variation_price('min', true),
                'max' => (string) $product->get_variation_price('max', true),
            ];
        }

        /*
         * محصول گروهی مقدار تجمیعی ندارد و بازه‌اش فقط با پیمایش فرزندان
         * درمی‌آید. در مسیر سبک این کار انجام نمی‌شود و بازه‌ای هم اعلام
         * نمی‌شود؛ نتیجه به حالت «کمترین قیمت» برمی‌گردد.
         */
        if (!$deep) {
            return ['min' => '', 'max' => ''];
        }

        $prices = [];

        foreach (self::children($product) as $child) {
            $price = self::display($child, $child->get_price());

            if ('' !== $price) {
                $prices[] = (float) $price;
            }
        }

        if (!$prices) {
            return ['min' => '', 'max' => ''];
        }

        return ['min' => (string) min($prices), 'max' => (string) max($prices)];
    }

    /**
     * ارزان‌ترین فرزندِ قابل خرید، همراه قیمت عادیِ همان فرزند.
     *
     * @return array{price:string,regular:string}
     */
    private static function cheapest_child(\WC_Product $product): array {
        $best = ['price' => '', 'regular' => ''];

        foreach (self::children($product) as $child) {
            $price = self::display($child, $child->get_price());

            if ('' === $price) {
                continue;
            }

            if ('' === $best['price'] || (float) $price < (float) $best['price']) {
                $best = [
                    'price'   => $price,
                    'regular' => self::display($child, $child->get_regular_price()),
                ];
            }
        }

        return '' === $best['price'] ? self::aggregate($product) : $best;
    }

    /**
     * فرزندانِ قابل نمایش و قابل خرید.
     *
     * گزینهٔ پنهان یا ناموجودِ غیرقابل‌خرید نباید مبنای قیمت شود؛ وگرنه صفحه
     * عددی نشان می‌دهد که مشتری اصلاً نمی‌تواند بخرد — و این دقیقاً همان
     * موردی است که به پشتیبانی گزارش می‌شود.
     *
     * @return \WC_Product[]
     */
    private static function children(\WC_Product $product): array {
        $ids = $product->get_children();

        if (count($ids) > self::MAX_VARIATIONS) {
            return [];
        }

        $children = [];

        foreach ($ids as $id) {
            $child = wc_get_product($id);

            if (!$child instanceof \WC_Product || !$child->exists()) {
                continue;
            }

            if (method_exists($child, 'variation_is_visible') && !$child->variation_is_visible()) {
                continue;
            }

            if (!$child->is_purchasable()) {
                continue;
            }

            $children[] = $child;
        }

        return $children;
    }

    /**
     * پناهگاه: کمترین قیمت تجمیعیِ ووکامرس، بدون قیمت پیشین.
     *
     * بدون قیمت پیشین، چون مقدار تجمیعی به هیچ گزینهٔ مشخصی گره نخورده و
     * ساختن درصد تخفیف از رویش همان خطایی است که بالاتر توضیح داده شد.
     */
    private static function aggregate(\WC_Product $product): array {
        if (!method_exists($product, 'get_variation_price')) {
            return ['price' => '', 'regular' => ''];
        }

        $min = $product->get_variation_price('min', true);

        return [
            'price'   => ('' === $min || null === $min) ? '' : (string) $min,
            'regular' => '',
        ];
    }

    /* =====================================================================
     * قالب‌بندی
     * =================================================================== */

    /**
     * قیمت خام را به قیمتِ نمایشی تبدیل می‌کند.
     *
     * ‎get_price()‎ و ‎get_regular_price()‎ عددِ ذخیره‌شده را می‌دهند و از
     * تنظیمات مالیاتی فروشگاه بی‌خبرند، در حالی که مسیر محصول متغیر
     * (‎get_variation_price('min', true)‎) قیمت نمایشی می‌دهد. بدون این
     * تبدیل، فروشگاهی که قیمت را بدون مالیات ذخیره و با مالیات نمایش
     * می‌دهد، برای محصول ساده عددی متفاوت از خودِ ووکامرس نشان می‌داد —
     * یعنی قیمت صفحهٔ محصول با قیمت سبد خرید نمی‌خواند.
     */
    private static function display(\WC_Product $product, $raw): string {
        if ('' === $raw || null === $raw) {
            return '';
        }

        if (!function_exists('wc_get_price_to_display')) {
            return (string) $raw;
        }

        return (string) wc_get_price_to_display($product, ['price' => $raw]);
    }

    /** عدد، با جداکننده و اعشارِ تنظیم‌شده در ووکامرس */
    public static function format(string $value): string {
        if ('' === $value) {
            return '';
        }

        return number_format(
            (float) $value,
            function_exists('wc_get_price_decimals') ? wc_get_price_decimals() : 0,
            function_exists('wc_get_price_decimal_separator') ? wc_get_price_decimal_separator() : '.',
            function_exists('wc_get_price_thousand_separator') ? wc_get_price_thousand_separator() : ','
        );
    }

    /**
     * ارقام لاتین به فارسی.
     *
     * فقط ارقام جایگزین می‌شوند و جداکننده‌ها دست نمی‌خورند؛ تبدیل ویرگول به
     * ویرگول فارسی کار این تابع نیست چون جداکننده از تنظیمات ووکامرس می‌آید
     * و کاربر می‌تواند هر چیزی بگذارد.
     */
    public static function persian(string $value): string {
        return strtr($value, [
            '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴',
            '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹',
        ]);
    }

    /** واحد پول: متن دلخواه کاربر، وگرنه نماد فروشگاه */
    public static function currency(string $custom = ''): string {
        $custom = trim($custom);

        if ('' !== $custom) {
            return $custom;
        }

        if (!function_exists('get_woocommerce_currency_symbol')) {
            return '';
        }

        // نماد ووکامرس اغلب موجودیت HTML است (‎&#8364;‎)؛ چون خروجی را خودمان
        // اسکیپ می‌کنیم، باید اول به کاراکتر واقعی برگردد وگرنه دوبار
        // اسکیپ‌شده روی صفحه دیده می‌شود.
        return html_entity_decode((string) get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8');
    }
}
