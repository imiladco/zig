<?php
namespace Zig3d_Widgets;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * زمانِ تخمینیِ مطالعه — از رویِ تعدادِ کلماتِ محتوای پست.
 *
 * مثلِ ‎Price‎/‎Stock‎/‎Configurator‎، عمداً جدا از المنتور و بدونِ هیچ
 * فرمت‌بندیِ نمایشی: نه رقمِ فارسی، نه واحدِ «دقیقه». فقط یک عددِ صحیح
 * برمی‌گرداند؛ متنِ نهایی (با هر الگویی که کاربر در پنل بخواهد) کارِ
 * ویجت است.
 *
 * برخلافِ قیمت/موجودی که از افزونهٔ شخصِ ثالث خوانده می‌شوند، این عدد
 * افزونهٔ منبع ندارد — محاسبه‌اش همان کارِ استانداردِ همهٔ پلاگین‌های
 * «زمانِ مطالعه» است: تعدادِ کلمه ÷ سرعتِ متوسطِ خواندن.
 */
final class Reading_Time {

    /** سرعتِ متوسطِ مطالعه — کلمه در دقیقه؛ همان عددِ رایج در اکثرِ افزونه‌های مشابه */
    public const DEFAULT_WPM = 200;

    /**
     * دقیقه‌های تخمینی — همیشه دستِ‌کم ۱، حتی برایِ محتوایِ خیلی کوتاه.
     */
    public static function minutes(string $content, int $wpm = self::DEFAULT_WPM): int {
        $wpm = max(1, $wpm);
        $words = self::word_count($content);

        return max(1, (int) ceil($words / $wpm));
    }

    /**
     * تعدادِ کلمه — بدونِ تگ/شورت‌کد/موجودیتِ HTML.
     *
     * از ‎wp_strip_all_tags()‎ استفاده نمی‌شود چون پرچمِ ‎remove_breaks‎ش
     * تگ‌های بلاک را با رشتهٔ خالی (نه فاصله) جایگزین می‌کند — یعنی
     * ‎</p><p>‎ی بینِ دو پاراگراف می‌تواند آخرین کلمهٔ یکی را به اولین
     * کلمهٔ بعدی بچسباند. اینجا تگ‌ها با یک فاصله جایگزین می‌شوند تا مرزِ
     * کلمه همیشه درست بماند.
     */
    private static function word_count(string $content): int {
        if (function_exists('strip_shortcodes')) {
            $content = (string) strip_shortcodes($content);
        }

        $text = (string) preg_replace('/<[^>]*>/', ' ', $content);
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');

        /*
         * ‎\s‎ حتی زیرِ پرچمِ ‎/u‎ فقط فاصله‌های ASCII را می‌شناسد؛ ‎&nbsp;‎
         * بعدِ decode می‌شود U+00A0 (فاصلهٔ نیم‌فاصله‌ایِ یونیکد)، نه یک
         * فاصلهٔ معمولی — بدونِ این جایگزینی، دو کلمهٔ کنارِ یک ‎&nbsp;‎
         * یک کلمهٔ چسبیده شمرده می‌شدند.
         */
        $text = str_replace("\xC2\xA0", ' ', $text);
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));

        if ('' === $text) {
            return 0;
        }

        $words = preg_split('/\s+/u', $text);

        return false === $words ? 0 : count($words);
    }
}
