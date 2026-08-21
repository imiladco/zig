<?php
namespace Zig3d_Widgets;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * لایکِ پست — از صفر، بدونِ حساب‌کاربری.
 *
 * چون سایت افزونهٔ لایکِ دیگری ندارد که با آن یکی شویم، شمارش خودِ این
 * کلاس است: هر بازدیدکننده یک توکنِ ناشناسِ ماندگار (کوکی؛ منطقِ ساختن و
 * خواندنِ آن کوکی در ‎Likes_Endpoint‎ است، نه اینجا) دارد و توکنش
 * هش‌شده در متایِ همان پست نگه داشته می‌شود — یک پست، یک لیستِ توکن،
 * تعداد = طولِ لیست. بدونِ جدولِ اختصاصی، چون اینجا نیازی به مقیاسِ
 * میلیونی نیست و از پیچیدگیِ یک migration/activation hook بی‌نیاز
 * می‌ماند.
 *
 * هش نه برایِ امنیت که برایِ کوتاه/یک‌دست‌ماندنِ مقدارِ ذخیره‌شده — توکنِ
 * خام هیچ‌وقت در دیتابیس نمی‌ماند.
 */
final class Likes {

    private const META_TOKENS = '_zig_like_tokens';

    /** تعدادِ لایک — طولِ لیستِ توکن‌های همان پست */
    public static function count(int $post_id): int {
        if ($post_id <= 0) {
            return 0;
        }

        return count(self::tokens($post_id));
    }

    /** آیا همین بازدیدکننده (با این توکن) قبلاً لایک کرده؟ */
    public static function has_liked(int $post_id, string $token): bool {
        if ($post_id <= 0 || '' === $token) {
            return false;
        }

        return in_array(self::hash($token), self::tokens($post_id), true);
    }

    /**
     * سوییچ — اگر توکن قبلاً لایک کرده، برمی‌دارد؛ وگرنه اضافه می‌کند.
     *
     * @return array{count:int,liked:bool}
     */
    public static function toggle(int $post_id, string $token): array {
        if ($post_id <= 0 || '' === $token) {
            return ['count' => self::count($post_id), 'liked' => false];
        }

        $hash   = self::hash($token);
        $tokens = self::tokens($post_id);
        $index  = array_search($hash, $tokens, true);

        if (false === $index) {
            $tokens[] = $hash;
            $liked    = true;
        } else {
            unset($tokens[$index]);
            $tokens = array_values($tokens);
            $liked  = false;
        }

        update_post_meta($post_id, self::META_TOKENS, $tokens);

        return ['count' => count($tokens), 'liked' => $liked];
    }

    /** @return string[] */
    private static function tokens(int $post_id): array {
        $tokens = get_post_meta($post_id, self::META_TOKENS, true);

        if (!is_array($tokens)) {
            return [];
        }

        return array_values(array_filter($tokens, 'is_string'));
    }

    public static function hash(string $token): string {
        return hash('sha256', $token);
    }
}
