<?php
namespace Zig3d_Widgets;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ساختن سلکتورهای چندبخشی برای کنترل‌های المنتور.
 *
 * چرا این کلاس وجود دارد: الحاق سادهٔ رشته‌ها اینجا بی‌سروصدا غلط از آب
 * درمی‌آید. یک سلکتور CSS با کاما در واقع چند سلکتور مستقل است، و هر
 * پسوندی که به انتهای رشته بچسبد فقط به آخرینشان می‌خورد:
 *
 *     '{{W}} .a, {{W}} .b' . ' *'   →   '{{W}} .a, {{W}} .b *'
 *                                        ^^^^^^^^^ این بدون ' *' ماند
 *
 * و بدتر، وقتی خودِ فرزند کاما داشته باشد، بخش دومش هیچ والدی نمی‌گیرد و
 * به یک قاعدهٔ سراسری تبدیل می‌شود که به کل صفحه نشت می‌کند:
 *
 *     '{{W}} .a' . ' .b, .b *'      →   '{{W}} .a .b, .b *'
 *                                                     ^^^^ سراسری
 *
 * دقیقاً همین دومی یک بار اتفاق افتاد و رنگ آیکون همهٔ دکمه‌های صفحه را به
 * هم ریخت، بی‌آنکه خروجی رندر یا هیچ تستی چیزی نشان بدهد.
 */
final class Selector {

    /**
     * ترکیبِ «هر والد با هر فرزند».
     *
     * ضرب دکارتی است نه الحاق: با دو والد و دو فرزند، چهار سلکتور می‌سازد.
     * همین تنها راهی است که هیچ ترکیبی از قلم نیفتد و هیچ بخشی بدون والد
     * نماند.
     */
    public static function descend(string $parents, string $children): string {
        $parents  = self::parts($parents);
        $children = self::parts($children);

        if (!$parents) {
            return '';
        }

        // فرزندِ خالی یعنی «فقط همان والدها»
        if (!$children) {
            return implode(', ', $parents);
        }

        $out = [];

        foreach ($parents as $parent) {
            foreach ($children as $child) {
                $out[] = $parent . ' ' . $child;
            }
        }

        return implode(', ', $out);
    }

    /**
     * چند سلکتور را به یک رشتهٔ واحد می‌چسباند، بدون بخش تکراری یا خالی.
     */
    public static function join(string ...$selectors): string {
        $parts = [];

        foreach ($selectors as $selector) {
            foreach (self::parts($selector) as $part) {
                $parts[] = $part;
            }
        }

        return implode(', ', array_unique($parts));
    }

    /**
     * شکستن یک سلکتور به بخش‌هایش، با حذف فاصله و بخش‌های خالی.
     *
     * @return string[]
     */
    private static function parts(string $selector): array {
        $parts = array_map('trim', explode(',', $selector));

        return array_values(array_filter($parts, static fn(string $part): bool => '' !== $part));
    }
}
