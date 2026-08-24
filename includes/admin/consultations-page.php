<?php
namespace Zig3d_Widgets\Admin;

use Zig3d_Widgets\Consultations;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * فهرستِ درخواست‌هایِ مشاوره.
 *
 * منویِ سطحِ‌بالا، نه زیرِ «محصولات» مثلِ ‎Schemas_Page‎/‎Spec_Groups_Page‎:
 * درخواستِ مشاوره لزوماً از یک محصول نمی‌آید — دکمهٔ ویجتِ عمومیِ «دکمه»
 * هم می‌تواند بازکنندهٔ همین فرم باشد، پس زیرِ منویِ ووکامرس جایش نیست.
 * به همین دلیل هم ثبتش (نگاه کنید به ‎Plugin::boot_consultations_admin()‎)
 * پشتِ ‎class_exists('WooCommerce')‎ نیست.
 */
final class Consultations_Page {

    private const SLUG = 'zig3d-consultations';

    public static function boot(): void {
        add_action('admin_menu', [self::class, 'register']);
    }

    public static function register(): void {
        add_menu_page(
            __('مشاوره‌ها', 'zig3d-widgets'),
            __('مشاوره‌ها', 'zig3d-widgets'),
            'manage_options',
            self::SLUG,
            [self::class, 'render'],
            'dashicons-format-chat',
            30
        );
    }

    /* =====================================================================
     * نمایش
     * =================================================================== */

    public static function render(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- فقط شمارهٔ صفحه، بدونِ نوشتن
        $page = isset($_GET['paged']) ? max(1, absint(wp_unslash($_GET['paged']))) : 1;

        $result = Consultations::page($page);

        echo '<div class="wrap">';
        printf('<h1>%s</h1>', esc_html__('مشاوره‌ها', 'zig3d-widgets'));

        if (!$result['rows']) {
            printf('<p>%s</p>', esc_html__('هنوز درخواستِ مشاوره‌ای ثبت نشده.', 'zig3d-widgets'));
            echo '</div>';

            return;
        }

        self::render_table($result['rows']);
        self::render_pagination($page, (int) $result['total']);

        echo '</div>';
    }

    private static function render_table(array $rows): void {
        echo '<table class="widefat striped"><thead><tr>';

        foreach ([
            __('تاریخ', 'zig3d-widgets'),
            __('نام', 'zig3d-widgets'),
            __('شماره', 'zig3d-widgets'),
            __('توضیحات', 'zig3d-widgets'),
            __('محصول', 'zig3d-widgets'),
            __('صفحهٔ ثبت', 'zig3d-widgets'),
        ] as $heading) {
            printf('<th>%s</th>', esc_html($heading));
        }

        echo '</tr></thead><tbody>';

        foreach ($rows as $row) {
            echo '<tr>';
            printf('<td>%s</td>', esc_html(self::format_date((string) ($row['created_at'] ?? ''))));
            printf('<td>%s</td>', esc_html((string) ($row['name'] ?? '')));
            printf(
                '<td><a href="tel:%1$s">%2$s</a></td>',
                esc_attr((string) ($row['phone'] ?? '')),
                esc_html((string) ($row['phone'] ?? ''))
            );

            $message = trim((string) ($row['message'] ?? ''));
            printf('<td>%s</td>', '' !== $message ? esc_html($message) : '<span style="opacity:.6">—</span>');

            echo '<td>' . self::product_cell($row) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput -- خروجیِ همین فایل، از قبل اسکیپ‌شده
            echo '<td>' . self::source_cell($row) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput -- خروجیِ همین فایل، از قبل اسکیپ‌شده
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    private static function product_cell(array $row): string {
        $name = trim((string) ($row['product_name'] ?? ''));

        if ('' === $name) {
            return '<span style="opacity:.6">—</span>';
        }

        $product_id = (int) ($row['product_id'] ?? 0);
        $edit_link  = $product_id > 0 ? get_edit_post_link($product_id) : '';

        if ($edit_link) {
            return sprintf('<a href="%s">%s</a>', esc_url($edit_link), esc_html($name));
        }

        return esc_html($name);
    }

    private static function source_cell(array $row): string {
        $url = trim((string) ($row['source_url'] ?? ''));

        if ('' === $url) {
            return '<span style="opacity:.6">—</span>';
        }

        $title = trim((string) ($row['source_title'] ?? ''));

        return sprintf(
            '<a href="%1$s" target="_blank" rel="noopener">%2$s</a>',
            esc_url($url),
            esc_html('' !== $title ? $title : $url)
        );
    }

    private static function format_date(string $mysql_datetime): string {
        if ('' === $mysql_datetime) {
            return '';
        }

        $timestamp = strtotime($mysql_datetime);

        return $timestamp ? date_i18n('Y/m/d H:i', $timestamp) : $mysql_datetime;
    }

    private static function render_pagination(int $page, int $total): void {
        $pages = (int) ceil($total / Consultations::PER_PAGE);

        if ($pages < 2) {
            return;
        }

        echo '<p class="tablenav-pages" style="margin-top:12px">';

        for ($i = 1; $i <= $pages; $i++) {
            if ($i === $page) {
                printf('<span class="tablenav-pages-navspan button disabled">%d</span> ', $i);

                continue;
            }

            printf(
                '<a class="button" href="%s">%d</a> ',
                esc_url(add_query_arg(['page' => self::SLUG, 'paged' => $i], admin_url('admin.php'))),
                $i
            );
        }

        echo '</p>';
    }
}
