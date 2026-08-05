<?php
namespace Zig3d_Widgets;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * کمکی‌های ساخت خروجی HTML.
 *
 * هر جایی که مقدارِ واردشدهٔ کاربر به مارک‌آپ تبدیل می‌شود از همین‌جا رد
 * می‌شود، تا قواعد اسکیپ در یک نقطه بماند و «این یکی را یادم رفت» ممکن نباشد.
 */
final class Markup {

    /**
     * تگ‌هایی که کاربر می‌تواند برای عنوان انتخاب کند.
     *
     * این‌ها هم فهرست کشویی کنترل را می‌سازند و هم فهرست سفیدِ اعتبارسنجی
     * هنگام رندر — یعنی مقدار دستکاری‌شده در دیتابیس هم نمی‌تواند تگ دلخواه
     * تولید کند.
     */
    public const TAGS = [
        'h1'   => 'H1',
        'h2'   => 'H2',
        'h3'   => 'H3',
        'h4'   => 'H4',
        'h5'   => 'H5',
        'h6'   => 'H6',
        'p'    => 'P',
        'div'  => 'DIV',
        'span' => 'SPAN',
    ];

    /** تگ معتبر یا پیش‌فرض */
    public static function tag($tag, string $fallback = 'div'): string {
        $tag = strtolower(trim((string) $tag));

        return isset(self::TAGS[$tag]) ? $tag : $fallback;
    }

    /**
     * تگ‌های درون‌خطی مجاز در عنوان و توضیحات.
     *
     * هدف این است که کاربر بتواند بخشی از متن را برجسته کند (‎<span>‎ با
     * کلاس، ‎<br>‎ برای شکستن خط) بدون آنکه بتواند مارک‌آپ دلخواه — از جمله
     * ‎<script>‎ یا رویدادهای درون‌خطی — تزریق کند. style عمداً مجاز نیست:
     * همه‌چیز باید از پنل استایل بیاید تا خروجی یکدست بماند.
     */
    public static function inline_html(): array {
        return [
            'span'   => ['class' => []],
            'strong' => [],
            'b'      => [],
            'em'     => [],
            'i'      => [],
            'u'      => [],
            'mark'   => ['class' => []],
            'small'  => [],
            'sub'    => [],
            'sup'    => [],
            'br'     => [],
            'bdi'    => [],
            'wbr'    => [],
        ];
    }

    /** متن با تگ‌های درون‌خطی مجاز */
    public static function text(string $value): string {
        return wp_kses($value, self::inline_html());
    }

    /**
     * آیا این رشته بعد از حذف تگ‌ها و فاصله‌ها چیزی دارد؟
     *
     * ‎'' !== $value‎ کافی نیست: کاربر خیلی وقت‌ها فیلد را با یک ‎<br>‎ یا چند
     * فاصله «خالی» می‌کند و آن‌وقت یک عنصر تهی با پدینگ و حاشیه رندر می‌شد.
     */
    public static function filled($value): bool {
        return '' !== trim(wp_strip_all_tags((string) $value));
    }
}
