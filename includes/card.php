<?php
namespace Zig3d_Widgets;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * تصمیم‌های کارت محصول: قیمت چطور نشان داده شود، دکمه چه کاری بکند، و
 * ویژگی‌ها از کجا بیایند.
 *
 * مهم‌ترین قاعدهٔ این فایل، چیزی است که *نمی‌کند*: «تماس بگیرید» را حالتِ
 * قیمت حساب نمی‌کند.
 *
 * وسوسه‌اش هست — سه لیبل «قیمت از / قیمت / تماس بگیرید» کنار هم می‌نشینند
 * و انگار یک محورند. ولی نیستند. این دو محصول را ببینید:
 *
 *     محصول A: قابل سفارش + قیمت استعلامی
 *     محصول B: آماده تحویل + قیمت استعلامی
 *
 * از نظر قیمت یکی‌اند، ولی کاری که کاربر باید بکند می‌تواند فرق کند: یکی
 * «درخواست قیمت»، یکی «تماس با کارشناس». اگر تماس داخل حالتِ قیمت باشد،
 * عوض‌کردن متن دکمه یعنی دست‌زدن به منطق قیمت، و «فیلتر محصولات دارای
 * قیمت» دیگر معنای روشنی ندارد.
 *
 * پس سه محور مستقل داریم و هر سه جدا محاسبه می‌شوند:
 *
 *     موجودی   ← کلاس Stock
 *     قیمت     ← numeric | inquiry | hidden
 *     اقدام    ← details | inquiry | consultation | custom
 *
 * رابطهٔ بین قیمت و اقدام فقط در حد یک پیش‌فرضِ هوشمند است، نه یک قفل:
 * محصول بی‌قیمت پیش‌فرض «استعلام» می‌گیرد، ولی مدیر می‌تواند هر چیز دیگری
 * بگذارد.
 */
final class Card {

    /* حالت قیمت */
    public const PRICE_NUMERIC = 'numeric';
    public const PRICE_INQUIRY = 'inquiry';
    public const PRICE_HIDDEN  = 'hidden';

    /* کدام لیبلِ قیمت */
    public const LABEL_FROM    = 'from';
    public const LABEL_EXACT   = 'exact';
    public const LABEL_INQUIRY = 'inquiry';

    /* حالت اقدام */
    public const CTA_DETAILS      = 'details';
    public const CTA_INQUIRY      = 'inquiry';
    public const CTA_CONSULTATION = 'consultation';
    public const CTA_CUSTOM       = 'custom';
    public const CTA_AUTO         = 'auto';

    /* =====================================================================
     * قیمت
     * =================================================================== */

    /**
     * حالت قیمت و اینکه کدام لیبل باید بنشیند.
     *
     * ‎$price‎ همان خروجی ‎Price::data()‎ است؛ این کلاس قیمت را دوباره
     * محاسبه نمی‌کند، فقط دربارهٔ نمایشش تصمیم می‌گیرد.
     *
     * @param array  $price   خروجی ‎Price::data()‎.
     * @param string $no_price رفتار محصول بی‌قیمت: ‎inquiry‎ یا ‎hidden‎.
     * @return array{mode:string,label:string}
     */
    public static function price_state(array $price, string $no_price = self::PRICE_INQUIRY): array {
        if (empty($price['has_price'])) {
            return [
                'mode'  => self::PRICE_HIDDEN === $no_price ? self::PRICE_HIDDEN : self::PRICE_INQUIRY,
                'label' => self::PRICE_HIDDEN === $no_price ? '' : self::LABEL_INQUIRY,
            ];
        }

        /*
         * «قیمت از» فقط وقتی که واقعاً چند قیمت در کار باشد.
         *
         * محصول متغیری که همهٔ گزینه‌هایش یک قیمت دارند، از نظر ووکامرس
         * متغیر است ولی از نظر مشتری نیست: «شروع از ۱۲ میلیون» روی چیزی که
         * دقیقاً ۱۲ میلیون است، به مشتری می‌گوید قیمت‌های دیگری هم هست —
         * که نیست.
         */
        $multi = !empty($price['is_range'])
            || (!empty($price['is_multi']) && '' !== (string) ($price['max'] ?? '') && $price['max'] !== $price['current']);

        return [
            'mode'  => self::PRICE_NUMERIC,
            'label' => $multi ? self::LABEL_FROM : self::LABEL_EXACT,
        ];
    }

    /* =====================================================================
     * اقدام
     * =================================================================== */

    /**
     * حالت دکمهٔ کارت.
     *
     * ‎auto‎ یعنی «خودت تصمیم بگیر»: محصول بی‌قیمت به استعلام می‌رود و
     * بقیه به جزئیات. هر مقدار صریحِ دیگری، همان می‌ماند — حتی اگر با
     * حالت قیمت جور نباشد، چون آن هم یک تصمیم کسب‌وکاری معتبر است.
     */
    public static function cta_mode(string $configured, string $price_mode): string {
        $known = [self::CTA_DETAILS, self::CTA_INQUIRY, self::CTA_CONSULTATION, self::CTA_CUSTOM];

        if (in_array($configured, $known, true)) {
            return $configured;
        }

        return self::PRICE_INQUIRY === $price_mode ? self::CTA_INQUIRY : self::CTA_DETAILS;
    }

    /* =====================================================================
     * ویژگی‌های وسط کارت
     * =================================================================== */

    /**
     * اولین منبعی که واقعاً چیزی دارد، بریده‌شده به سقف.
     *
     * زنجیره عمدی است: رپیتر جت‌انجین ← ویژگی‌های ووکامرس ← هیچ. محصولی که
     * هنوز رپیترش پر نشده نباید کارتِ شکسته بدهد؛ باید یا از منبع بعدی پر
     * شود یا آن بخش کلاً نیاید. حالت سوم — یعنی سه حباب خالی — بدترین
     * گزینه است و دقیقاً همان چیزی است که بدون این تابع اتفاق می‌افتد.
     *
     * @param array<int,array<int,string>> $sources منابع، به ترتیب اولویت.
     * @return string[]
     */
    public static function features(array $sources, int $max = 3): array {
        if ($max < 1) {
            return [];
        }

        foreach ($sources as $source) {
            $items = [];

            foreach ((array) $source as $item) {
                $item = trim(wp_strip_all_tags((string) $item));

                if ('' === $item) {
                    continue;
                }

                $items[] = $item;

                if (count($items) >= $max) {
                    break;
                }
            }

            if ($items) {
                return $items;
            }
        }

        return [];
    }
}
