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
    /**
     * آیکون خطی، به‌صورت SVG درون‌خطی.
     *
     * چرا درون‌خطی و نه فونت آیکون یا فایل جدا: این‌ها سه شکل ثابت‌اند که
     * هیچ‌وقت عوض نمی‌شوند و مجموعاً چند صد بایت‌اند. یک درخواست HTTP
     * اضافه یا یک وابستگی به کتابخانهٔ آیکون، برای سه مسیرِ ‎path‎ توجیه
     * ندارد — و آیکونی که دیر می‌رسد، دکمه‌ای می‌سازد که یک لحظه خالی است.
     *
     * ‎currentColor‎ یعنی رنگش از متنِ اطرافش می‌آید، پس کنترل رنگِ پنل در
     * المنتور خودبه‌خود آیکون را هم می‌گیرد.
     *
     * ‎aria-hidden‎ چون هر سه تزئینی‌اند: کنارشان همیشه متن هست.
     */
    public static function svg_icon(string $name, string $class = ''): string {
        $paths = [
            'filter'   => '<path d="M3 5h18M6 12h12M10 19h4"/>',
            'trash'    => '<path d="M4 7h16M9 7V5h6v2M6 7l1 13h10l1-13M10 11v6M14 11v6"/>',
            'chevron'  => '<path d="M6 9l6 6 6-6"/>',
            'arrow'    => '<path d="M19 12H5M11 18l-6-6 6-6"/>',
            'sort'     => '<path d="M4 6h16M7 12h10M10 18h4"/>',
            'phone'    => '<path d="M6 3h4l2 5-3 2a12 12 0 005 5l2-3 5 2v4a2 2 0 01-2 2A16 16 0 014 5a2 2 0 012-2z"/>',
            'close'    => '<path d="M6 6l12 12M18 6L6 18"/>',
        ];

        if (!isset($paths[$name])) {
            return '';
        }

        return sprintf(
            '<svg class="%s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"'
                . ' stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%s</svg>',
            esc_attr(trim('zig-icon-line ' . $class)),
            $paths[$name]
        );
    }

    public static function filled($value): bool {
        return '' !== trim(wp_strip_all_tags((string) $value));
    }
}
