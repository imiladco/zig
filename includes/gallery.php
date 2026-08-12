<?php
namespace Zig3d_Widgets;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * دادهٔ گالری یک محصول، پیش از رندر.
 *
 * همان قاعدهٔ ‎Product_Card‎: تصمیم‌ها اینجا، مارک‌آپ آنجا. هیچ ‎<img>‎ی
 * اینجا ساخته نمی‌شود و هیچ‌چیز اسکیپ نمی‌شود — فقط شناسهٔ پیوست برمی‌گردد
 * تا رندر خودش اندازهٔ درست و صفت‌های درست را انتخاب کند.
 *
 * مهم‌ترین تصمیم این فایل، چیزی است که گالریِ قالب الماس‌آرا نداشت:
 * **یک فهرست، نه دوتا.**
 *
 * آنجا تصویر شاخص در بخش اصلی می‌نشست و بندانگشتی‌ها از ‎gallery_image_ids‎
 * می‌آمدند. نتیجه‌اش یک ناهماهنگیِ خاموش بود: کلیک روی بندانگشتیِ اول،
 * تصویری را باز می‌کرد که در بخش اصلی نبود، و خودِ تصویر شاخص هیچ
 * بندانگشتی‌ای نداشت تا بشود به آن برگشت. شمارنده هم — اگر بود — یکی
 * کم می‌شمرد.
 *
 * پس اینجا یک فهرستِ مرتبِ واحد ساخته می‌شود که شاخص هم عضوش است، و هر
 * سه بخشِ رابط (صحنه، بندانگشتی، شمارنده) از همان یکی می‌خوانند. اگر
 * شاخص در گالری هم تکرار شده باشد، یک بار می‌آید نه دو بار.
 */
final class Gallery {

    /** شکل خالی، تا هر مسیر بازگشتی همان کلیدها را داشته باشد */
    private const EMPTY = [
        'slides'      => [],
        'all_slides'  => [],
        'placeholder' => true,
        'title'       => '',
        'url'         => '',
    ];

    /**
     * دادهٔ گالری.
     *
     * دو فهرست برمی‌گرداند، نه یکی: ‎slides‎ (که ممکن است با ‎max‎ بریده
     * شده باشد — برای صحنه و نوارِ بندانگشتیِ اصلی) و ‎all_slides‎ (کاملِ
     * بی‌سقف — برای لایت‌باکس).
     *
     * چرا دوتا: ‎max‎ برای این هست که *صفحه* شلوغ نشود — ده بندانگشتیِ
     * ریز زیرِ یک عکس. ولی این محدودیتِ *نمایشی* دلیل نمی‌شود که مشتری
     * دیگر نتواند بقیهٔ عکس‌های محصول را ببیند؛ آن‌ها فقط باید جای دیگری
     * — لایت‌باکس — در دسترس بمانند. اگر یک فهرست داشتیم، یا باید همه‌جا
     * می‌بریدیمش (و آن عکس‌های «اضافه» را از مشتری پنهان می‌کردیم) یا
     * هیچ‌جا (و کنترلِ ‎max‎ اصلاً اثری نداشت).
     *
     * @param array $fields {
     *     @type bool $featured آیا تصویر شاخص هم یک اسلاید باشد.
     *     @type int  $max     سقف تعداد اسلایدِ *نمایشی*؛ ‎0‎ یعنی بی‌سقف.
     * }
     * @return array{slides:int[],all_slides:int[],placeholder:bool,title:string,url:string}
     */
    public static function data(\WC_Product $product, array $fields = []): array {
        $fields += [
            'featured' => true,
            'max'      => 0,
        ];

        $featured_id = (int) $product->get_image_id();
        $gallery_ids = (array) $product->get_gallery_image_ids();
        $with_featured = (bool) $fields['featured'];

        $all_slides = self::order($featured_id, $gallery_ids, $with_featured, 0);
        $slides     = self::order($featured_id, $gallery_ids, $with_featured, (int) $fields['max']);

        return [
            'slides'      => $slides,
            'all_slides'  => $all_slides,
            'placeholder' => [] === $slides,
            'title'       => (string) $product->get_name(),
            'url'         => (string) get_permalink($product->get_id()),
        ] + self::EMPTY;
    }

    /**
     * فهرست نهاییِ اسلایدها.
     *
     * عمداً تابعی خالص است و به هیچ چیزِ وردپرسی دست نمی‌زند: کل منطقِ
     * «چه چیزی، به چه ترتیبی، چندتا» همین‌جاست و بدون وردپرس هم قابل تست
     * است — و همین‌جاست که خطاهای مرزی (شاخصِ نبود، تکرار، سقف صفر)
     * می‌توانند بدون راه‌اندازی یک فروشگاه واقعی دیده شوند.
     *
     * @param int   $featured      شناسهٔ تصویر شاخص؛ ‎0‎ یعنی ندارد.
     * @param array $gallery       شناسه‌های گالری، به ترتیب خودِ ووکامرس.
     * @param bool  $with_featured آیا شاخص هم بیاید.
     * @param int   $max           سقف؛ ‎0‎ یا کمتر یعنی بی‌سقف.
     * @return int[]
     */
    public static function order(int $featured, array $gallery, bool $with_featured = true, int $max = 0): array {
        $ids = [];

        if ($with_featured && $featured > 0) {
            $ids[] = $featured;
        }

        foreach ($gallery as $id) {
            $id = (int) $id;

            if ($id > 0) {
                $ids[] = $id;
            }
        }

        /*
         * ‎array_unique‎ ترتیبِ اولین ظهور را نگه می‌دارد، و همین چیزی است
         * که می‌خواهیم: شاخصی که در گالری هم هست، جای اولش را از دست
         * نمی‌دهد. ‎array_values‎ چون کلیدهای پراکنده در ‎foreach‎ی که
         * شماره‌گذاری می‌کند دردسر می‌شود.
         */
        $ids = array_values(array_unique($ids));

        if ($max > 0 && count($ids) > $max) {
            $ids = array_slice($ids, 0, $max);
        }

        return $ids;
    }

    /**
     * شمارهٔ نمایشیِ یک اسلاید.
     *
     * جدا از رندر، چون هم شمارنده و هم برچسبِ بندانگشتی‌ها به آن نیاز
     * دارند و اگر دو جا نوشته می‌شد، روزی یکی‌شان از ‎0‎ شروع می‌کرد.
     */
    public static function label(int $index, bool $persian = true): string {
        $number = (string) ($index + 1);

        return $persian ? Price::persian($number) : $number;
    }
}
