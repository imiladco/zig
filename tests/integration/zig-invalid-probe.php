<?php
/**
 * Plugin Name: ZIG3D Invalid Probe
 * Description: می‌گوید چرا یک درخواست آژاکس وضعیت invalid گرفته. ابزار تشخیص است، نه بخشی از افزونه.
 * Version:     1.0.0
 *
 * نصب: این فایل را در ‎wp-content/mu-plugins/‎ بگذارید.
 * برداشتن: فایل را پاک کنید. هیچ چیز دیگری تغییر نمی‌دهد.
 *
 * چرا لازم شد: وضعیت ‎invalid‎ از سه سنجه می‌آید و هر سه روی محیط توسعه —
 * حتی با همان تاکسونومی‌ها و همان اسلاگ‌های فارسیِ کدشده — پاک برمی‌گردند.
 * یعنی تفاوت در *داده یا محیطِ همان سایت* است، نه در منطق. حدس‌زدن از این
 * نقطه به بعد فقط وقت است؛ این فایل به‌جایش واقعیت را می‌گوید.
 *
 * خروجی داخل خودِ پاسخ آژاکس، زیر کلید ‎zig_probe‎ می‌نشیند — پس فقط کافی
 * است یک فیلتر بزنی و همان پاسخ را نگاه کنی. چیزی در لاگ یا صفحه چاپ
 * نمی‌شود.
 *
 * @package Zig3d_Widgets_Tests
 */

if (!defined('ABSPATH')) {
    exit;
}

add_filter('wp_die_ajax_handler', static function ($handler) {
    return $handler;
});

/**
 * پاسخِ نقطهٔ پایانی را در راه خروج می‌گیرد و تشخیص را به آن می‌چسباند.
 *
 * ‎wp_send_json_success()‎ از ‎wp_json_encode‎ رد می‌شود و ما نمی‌توانیم
 * وسطش بپریم، پس به‌جای دست‌بردن در پاسخ، همان ورودی‌های تصمیم را دوباره
 * — و با همان توابع — حساب می‌کنیم و جدا برمی‌گردانیم. اگر عددها با
 * ‎state‎ی که در پاسخ آمده نخوانند، خودِ همان ناسازگاری جواب است.
 */
add_action('wp_ajax_zig3d_archive', 'zig3d_invalid_probe', 0);
add_action('wp_ajax_nopriv_zig3d_archive', 'zig3d_invalid_probe', 0);

function zig3d_invalid_probe(): void {
    if (!class_exists('\Zig3d_Widgets\Query_State') || !class_exists('\Zig3d_Widgets\Archive_Query')) {
        return;
    }

    // phpcs:disable WordPress.Security.NonceVerification.Missing
    $query = isset($_POST['query']) ? wp_unslash((string) $_POST['query']) : '';
    // phpcs:enable

    $params = [];
    parse_str(ltrim($query, '?'), $params);

    $honored = \Zig3d_Widgets\Archive_Query::honored_taxonomies([]);

    $report = [
        'query'      => $query,
        'filters'    => array_values(array_filter(
            array_keys($params),
            static fn($k): bool => 0 === strpos((string) $k, 'filter_')
        )),
        'honored'    => $honored,
        'attributes' => function_exists('wc_get_attribute_taxonomy_names')
            ? array_values((array) wc_get_attribute_taxonomy_names())
            : ['wc_get_attribute_taxonomy_names غایب است'],
        'unknown'    => \Zig3d_Widgets\Query_State::unknown_filters($params, $honored),
        'duplicate'  => \Zig3d_Widgets\Query_State::duplicate_filters($params),
        'oversized'  => \Zig3d_Widgets\Query_State::oversized_filters($params, $honored),
    ];

    /*
     * فقط وقتی چیزی برای گفتن هست هدر می‌رود، و کوتاه‌شده: هدرِ چندکیلوبایتی
     * را بعضی پراکسی‌ها بی‌صدا می‌اندازند و آن‌وقت نبودِ هدر به‌عنوان
     * «مشکلی نیست» خوانده می‌شود.
     */
    header('X-Zig-Probe: ' . substr(wp_json_encode($report, JSON_UNESCAPED_UNICODE), 0, 6000));
}
