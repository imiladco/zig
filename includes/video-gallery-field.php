<?php
namespace Zig3d_Widgets;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * دادهٔ گالریِ ویدئوهایِ محصول — یک فیلدِ Gallery از JetEngine روی خودِ
 * محصول، با کلیدی که ادمین در ویجت تعیین می‌کند (پیش‌فرض
 * ‎zig-product-video‎)، نه چیزی که این افزونه بسازد یا مدیریت کند.
 *
 * چرا جدا از ویجت: همان مرزِ ‎Feature_Repeater‎/‎Spec_Value‎ — «این محصول
 * چه ویدئویی دارد» از «چطور رندر شود». اینجا هیچ HTML یا Elementor نیست؛
 * فقط خواندنِ متا، نرمال‌سازیِ شکل‌هایِ مختلفِ فیلدِ Galleryِ JetEngine، و
 * درآوردنِ دادهٔ نمایشیِ هر ویدئو از خودِ پیوست.
 *
 * چرا این‌قدر شکلِ ورودی: فیلدِ Galleryِ JetEngine بسته به نسخه/تنظیم،
 * یکی از این‌ها را در پست‌متا ذخیره می‌کند — و همه باید بدونِ خالی‌شدنِ
 * ویجت پشتیبانی شوند:
 *
 *   • شناسهٔ پیوستِ تکی (عدد یا رشتهٔ عددی)
 *   • آرایهٔ شناسه‌ها (‎[12, 45, 78]‎ یا رشته‌ای)
 *   • آرایه‌ای از آبجکت‌هایِ رسانه (‎[['id'=>12,...], ...]‎)
 *   • آدرسِ تکی (رشتهٔ غیرِعددی)
 *   • آرایهٔ سریالایز‌شده (با ‎maybe_unserialize‎ باز می‌شود)
 *   • رشتهٔ JSON-مانند (‎"[12,45]"‎ یا ‎'{"0":"12"}'‎)
 *   • رشتهٔ شناسه‌هایِ کاما-جدا (‎"12,45,78"‎ — قالبِ خامِ رایجِ
 *     فیلدهایِ Galleryِ JetEngine وقتی به‌صورتِ متن ذخیره شده‌اند)
 */
final class Video_Gallery_Field {

    /**
     * آیتم‌هایِ معتبر، به‌ترتیبِ Gallery.
     *
     * آیتمی که نه شناسهٔ پیوست دارد نه آدرسِ قابل‌پخش، حذف می‌شود — نه
     * این‌که با placeholderی خالی رندر شود.
     *
     * @return array<int,array{
     *     id:int, url:string, title:string, description:string,
     *     duration:string, poster:array{id:int,url:string}
     * }>
     */
    public static function items(int $product_id, string $meta_key): array {
        $meta_key = trim($meta_key);

        if ($product_id <= 0 || '' === $meta_key) {
            return [];
        }

        $raw = maybe_unserialize(get_post_meta($product_id, $meta_key, true));
        $entries = self::normalize_list($raw);
        $product_poster = self::resolve_product_poster($product_id);

        $result = [];

        foreach ($entries as $entry) {
            $item = self::build_item($entry, $product_poster);

            if (null !== $item) {
                $result[] = $item;
            }
        }

        return $result;
    }

    /**
     * خروجیِ خامِ متا را به یک فهرستِ ساده از ورودی‌هایِ تک‌آیتمی تبدیل
     * می‌کند — پیش از این‌که هرکدام جداگانه نرمال شوند.
     *
     * @param mixed $raw
     * @return array<int,mixed>
     */
    private static function normalize_list($raw): array {
        if (is_array($raw)) {
            return array_values($raw);
        }

        if (is_numeric($raw)) {
            return [$raw];
        }

        if (!is_string($raw)) {
            return [];
        }

        $trimmed = trim($raw);

        if ('' === $trimmed) {
            return [];
        }

        $first = $trimmed[0];

        if ('[' === $first || '{' === $first) {
            $decoded = json_decode($trimmed, true);

            if (is_array($decoded)) {
                return array_values($decoded);
            }
        }

        if (false !== strpos($trimmed, ',')) {
            return array_map('trim', explode(',', $trimmed));
        }

        return [$trimmed];
    }

    /**
     * یک ورودیِ خام (شناسه/آدرس/آبجکتِ رسانه) را به آیتمِ نمایشیِ کامل
     * تبدیل می‌کند، یا ‎null‎ اگر قابل‌استفاده نبود.
     *
     * @param mixed $raw
     */
    private static function build_item($raw, array $product_poster): ?array {
        $media = self::normalize_media($raw);
        $id    = $media['id'];

        /*
         * منبعِ عنوان: اول Alt Text خودِ پیوست، اگر خالی بود Media Title —
         * دقیقاً طبقِ خواستِ صریح. منبعِ توضیح: اول Description
         * (post_content)، اگر خالی بود Caption. عنوان و توضیح دو زنجیرهٔ
         * مستقل دارند؛ Media Title فقط fallbackِ عنوان است و Caption فقط
         * fallbackِ توضیح.
         */
        $alt         = $id > 0 ? trim((string) get_post_meta($id, '_wp_attachment_image_alt', true)) : '';
        $media_title = $id > 0 ? trim((string) get_the_title($id)) : '';
        $title       = Markup::filled($alt) ? $alt : $media_title;

        $description_field = $id > 0 ? trim((string) get_post_field('post_content', $id)) : '';
        $caption            = $id > 0 ? trim((string) wp_get_attachment_caption($id)) : '';
        $description        = Markup::filled($description_field) ? $description_field : $caption;

        $duration = '';

        if ($id > 0) {
            $meta     = wp_get_attachment_metadata($id);
            $duration = trim((string) ($meta['length_formatted'] ?? ''));

            /*
             * fallback صریحاً خواسته شده: «Caption را به‌عنوان fallback
             * بررسی کند». چون Caption متنِ آزاد است (نه فیلدِ مدت‌زمان)،
             * فقط وقتی که واقعاً شکلِ یک مدت‌زمان دارد (mm:ss یا h:mm:ss)
             * به‌عنوانِ duration پذیرفته می‌شود — وگرنه یک کپشنِ توضیحی
             * ناخواسته زیرِ عنوانِ ویدئو ظاهر می‌شد.
             */
            if (!Markup::filled($duration) && self::looks_like_duration($caption)) {
                $duration = $caption;
            }
        }

        $url = $id > 0 ? (string) wp_get_attachment_url($id) : $media['url'];

        if (!Markup::filled($url)) {
            return null;
        }

        return [
            'id'          => $id,
            'url'         => $url,
            'title'       => $title,
            'description' => $description,
            'duration'    => $duration,
            'poster'      => self::resolve_poster($id, $product_poster),
        ];
    }

    private static function looks_like_duration(string $value): bool {
        return 1 === preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', trim($value));
    }

    /**
     * Poster/thumbnail ویدئو — اول تصویرِ شاخصِ خودِ پیوستِ ویدئو
     * (‎_thumbnail_id‎، همان مکانیزمِ استانداردِ وردپرس برایِ «تصویرِ
     * شاخصِ یک پست»، این‌جا روی خودِ پیوست)، اگر نبود تصویرِ شاخصِ محصول
     * — دقیقاً طبقِ ترتیبِ خواسته‌شده.
     *
     * @return array{id:int,url:string}
     */
    private static function resolve_poster(int $video_id, array $product_poster): array {
        if ($video_id > 0) {
            $poster_id = (int) get_post_meta($video_id, '_thumbnail_id', true);

            if ($poster_id > 0) {
                $url = wp_get_attachment_image_url($poster_id, 'large');

                if ($url) {
                    return ['id' => $poster_id, 'url' => (string) $url];
                }
            }
        }

        return $product_poster;
    }

    /**
     * Resolve the shared product fallback once per gallery rather than once
     * for every video item.
     *
     * @return array{id:int,url:string}
     */
    private static function resolve_product_poster(int $product_id): array {
        $product_thumb = (int) get_post_thumbnail_id($product_id);

        if ($product_thumb > 0) {
            $url = wp_get_attachment_image_url($product_thumb, 'large');

            if ($url) {
                return ['id' => $product_thumb, 'url' => (string) $url];
            }
        }

        return ['id' => 0, 'url' => ''];
    }

    /**
     * یک ورودیِ خامِ تکی را به ‎['id'=>int,'url'=>string]‎ می‌رساند — همان
     * سه شکلی که ‎Feature_Repeater::normalize_image()‎ هم پشتیبانی
     * می‌کند، به‌اضافهٔ رشتهٔ عددیِ خام (که این‌جا، برخلافِ آن‌جا، واقعاً
     * پیش می‌آید: «آرایه‌ای از شناسه‌ها»یِ رشته‌ای).
     *
     * @param mixed $raw
     * @return array{id:int,url:string}
     */
    private static function normalize_media($raw): array {
        $empty = ['id' => 0, 'url' => ''];

        if (is_numeric($raw)) {
            $id = (int) $raw;

            return $id > 0 ? ['id' => $id, 'url' => ''] : $empty;
        }

        if (is_string($raw)) {
            $value = trim($raw);

            return '' !== $value ? ['id' => 0, 'url' => $value] : $empty;
        }

        if (is_array($raw)) {
            foreach (['id', 'ID', 'attachment_id'] as $key) {
                if (!empty($raw[$key]) && is_numeric($raw[$key])) {
                    return ['id' => (int) $raw[$key], 'url' => ''];
                }
            }

            foreach (['url', 'src', 'source'] as $key) {
                if (!empty($raw[$key]) && is_string($raw[$key])) {
                    $url = trim($raw[$key]);

                    if ('' !== $url) {
                        return ['id' => 0, 'url' => $url];
                    }
                }
            }
        }

        return $empty;
    }
}
