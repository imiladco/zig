<?php
namespace Zig3d_Widgets;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * دادهٔ Repeaterِ «قابلیت‌های محصول» — یک فیلدِ JetEngine روی خودِ محصول
 * (‎feature_showcase‎)، نه چیزی که این افزونه بسازد یا مدیریت کند.
 *
 * چرا جدا از ویجت: همان تفکیکِ ‎Spec_Value‎ — «این محصول چه دارد» از
 * «چطور رندر شود». اینجا هیچ HTML یا Elementor نیست؛ فقط خواندنِ متا،
 * نرمال‌سازیِ فرمت‌هایِ مختلفِ فیلدِ تصویرِ JetEngine، و فیلترکردنِ
 * سطرهایی که طبقِ قانون نباید نمایش داده شوند.
 */
final class Feature_Repeater {

    public const META_KEY = 'feature_showcase';

    /**
     * سطرهایِ معتبر، به‌ترتیبِ Repeater، بعدِ فیلترِ سطرهایِ بی‌لیبل/خالی.
     *
     * شمارهٔ نمایشیِ ‎۰۱‎، ‎۰۲‎ … اینجا ساخته نمی‌شود — آن کارِ رندر است،
     * چون به شمارشِ *بعدِ* فیلتر نیاز دارد که همین‌جا با ایندکسِ آرایهٔ
     * خروجی به‌طور طبیعی به‌دست می‌آید (کارِ فراخوان است، نه این تابع).
     *
     * @return array<int,array{label:string,title:string,description:string,image:array{id:int,url:string}}>
     */
    public static function rows(int $product_id): array {
        if ($product_id <= 0) {
            return [];
        }

        $raw = maybe_unserialize(get_post_meta($product_id, self::META_KEY, true));

        if (!is_array($raw)) {
            return [];
        }

        $result = [];

        foreach ($raw as $row) {
            if (!is_array($row)) {
                continue;
            }

            /*
             * بدونِ لیبل، Tabِ قابل‌استفاده‌ای نمی‌شود ساخت — کل سطر کنار
             * می‌رود. این هم‌زمان قانونِ «سطرِ کاملاً خالی → skip» را هم
             * پوشش می‌دهد: سطری که هیچ فیلدی ندارد، لیبل هم ندارد.
             */
            $label = trim((string) ($row['feature_showcase_label'] ?? ''));

            if (!Markup::filled($label)) {
                continue;
            }

            $title       = trim((string) ($row['feature_showcase_title'] ?? ''));
            $description = trim((string) ($row['feature_showcase_dec'] ?? ''));

            $result[] = [
                'label'       => $label,
                'title'       => Markup::filled($title) ? $title : '',
                'description' => Markup::filled($description) ? $description : '',
                'image'       => self::normalize_image($row['feature_showcase_icon'] ?? null),
            ];
        }

        return $result;
    }

    /**
     * فیلدِ تصویرِ JetEngine — با وجودِ نامش (‎…_icon‎) یک تصویرِ واقعی
     * است، نه آیکونِ UI. JetEngine Media بسته به تنظیمِ فیلد، سه شکل
     * مختلف ذخیره می‌کند و هر سه اینجا پشتیبانی می‌شوند:
     *
     *   ۱) فقط شناسهٔ پیوست (عدد یا رشتهٔ عددی)
     *   ۲) فقط آدرس (رشتهٔ غیرِعددی)
     *   ۳) آرایه‌ای با کلیدِ ‎id‎/‎ID‎/‎attachment_id‎ یا ‎url‎/‎src‎/‎source‎
     *
     * شناسه، وقتی در دسترس است، ترجیح داده می‌شود — چون فقط با شناسه
     * می‌شود از ‎wp_get_attachment_image()‎ و ‎srcset‎ی واقعی استفاده کرد؛
     * آدرسِ خام فقط یک ‎<img>‎ ساده و بدونِ ‎srcset‎ می‌شود.
     *
     * @param mixed $raw
     * @return array{id:int,url:string}
     */
    private static function normalize_image($raw): array {
        $empty = ['id' => 0, 'url' => ''];

        if (is_numeric($raw)) {
            $id = (int) $raw;

            return $id > 0 ? ['id' => $id, 'url' => ''] : $empty;
        }

        if (is_string($raw)) {
            $url = trim($raw);

            return '' !== $url ? ['id' => 0, 'url' => $url] : $empty;
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
