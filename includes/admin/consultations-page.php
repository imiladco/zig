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

        self::handle_delete();

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- فقط شمارهٔ صفحه، بدونِ نوشتن
        $page = isset($_GET['paged']) ? max(1, absint(wp_unslash($_GET['paged']))) : 1;

        $result = Consultations::page($page);

        echo '<div class="wrap">';
        printf('<h1>%s</h1>', esc_html__('مشاوره‌ها', 'zig3d-widgets'));

        self::render_deleted_notice();

        if (!$result['rows']) {
            printf('<p>%s</p>', esc_html__('هنوز درخواستِ مشاوره‌ای ثبت نشده.', 'zig3d-widgets'));
            echo '</div>';

            return;
        }

        self::render_table($result['rows']);
        self::render_pagination($page, (int) $result['total']);

        echo '</div>';
    }

    /**
     * حذفِ گروهی (فرمِ POST) یا حذفِ تکی (پیوندِ کنشِ ردیف، GET) — هر دو
     * قبل از هر خروجی، تا بشود بعدش با ‎wp_safe_redirect()‎ به‌جایِ
     * دوباره‌ارسالِ فرم روی رفرش، صفحه را دوباره ساخت.
     */
    private static function handle_delete(): void {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- نانس دستی چند خط پایین‌تر بررسی می‌شود
        $post = wp_unslash($_POST);

        if (isset($post['zig3d_consultations_bulk_delete'])) {
            if (!wp_verify_nonce((string) ($post['_wpnonce'] ?? ''), 'zig3d_consultations_bulk_delete')) {
                wp_die(esc_html__('نشستِ صفحه منقضی شده؛ دوباره تلاش کنید.', 'zig3d-widgets'));
            }

            $ids = isset($post['ids']) ? array_map('absint', (array) $post['ids']) : [];

            self::redirect_after_delete(Consultations::delete($ids));
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- نانس دستی چند خط پایین‌تر بررسی می‌شود
        $get = wp_unslash($_GET);

        if (isset($get['zig3d_action'], $get['id']) && 'delete' === $get['zig3d_action']) {
            $id = absint($get['id']);

            if (!wp_verify_nonce((string) ($get['_wpnonce'] ?? ''), 'zig3d_consultations_delete_' . $id)) {
                wp_die(esc_html__('نشستِ صفحه منقضی شده؛ دوباره تلاش کنید.', 'zig3d-widgets'));
            }

            self::redirect_after_delete(Consultations::delete([$id]));
        }
    }

    private static function redirect_after_delete(int $deleted): void {
        wp_safe_redirect(add_query_arg(['page' => self::SLUG, 'deleted' => $deleted], admin_url('admin.php')));

        exit;
    }

    private static function render_deleted_notice(): void {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- فقط شمارهٔ نمایشی، بدونِ نوشتن
        if (!isset($_GET['deleted'])) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- همان بالا
        $deleted = absint(wp_unslash($_GET['deleted']));

        if ($deleted < 1) {
            return;
        }

        printf(
            '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
            esc_html(
                sprintf(
                    /* translators: %d: تعدادِ ردیفِ حذف‌شده */
                    _n('%d درخواست حذف شد.', '%d درخواست حذف شد.', $deleted, 'zig3d-widgets'),
                    $deleted
                )
            )
        );
    }

    private static function render_table(array $rows): void {
        echo '<form method="post">';
        wp_nonce_field('zig3d_consultations_bulk_delete');

        self::render_bulk_delete_button();

        echo '<table class="widefat striped"><thead><tr>';
        echo '<th style="width:2em"><input type="checkbox" id="zig3d-consultations-select-all"></th>';

        foreach ([
            __('تاریخ', 'zig3d-widgets'),
            __('نام', 'zig3d-widgets'),
            __('شماره', 'zig3d-widgets'),
            __('توضیحات', 'zig3d-widgets'),
            __('صفحهٔ ثبت', 'zig3d-widgets'),
            '',
        ] as $heading) {
            printf('<th>%s</th>', esc_html($heading));
        }

        echo '</tr></thead><tbody>';

        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);

            echo '<tr>';
            printf('<td><input type="checkbox" name="ids[]" value="%d"></td>', $id);
            printf('<td>%s</td>', esc_html(self::format_date((string) ($row['created_at'] ?? ''))));
            printf('<td>%s</td>', esc_html((string) ($row['name'] ?? '')));
            printf(
                '<td><a href="tel:%1$s">%2$s</a></td>',
                esc_attr((string) ($row['phone'] ?? '')),
                esc_html((string) ($row['phone'] ?? ''))
            );

            $message = trim((string) ($row['message'] ?? ''));
            printf('<td>%s</td>', '' !== $message ? esc_html($message) : '<span style="opacity:.6">—</span>');

            echo '<td>' . self::source_cell($row) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput -- خروجیِ همین فایل، از قبل اسکیپ‌شده
            echo '<td>' . self::delete_link($id) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput -- خروجیِ همین فایل، از قبل اسکیپ‌شده
            echo '</tr>';
        }

        echo '</tbody></table>';

        self::render_bulk_delete_button();

        echo '</form>';

        self::render_select_all_script();
    }

    private static function render_bulk_delete_button(): void {
        printf(
            '<button type="submit" name="zig3d_consultations_bulk_delete" value="1" class="button" onclick="return confirm(%s)">%s</button>',
            esc_attr(wp_json_encode(__('آیتم‌هایِ انتخاب‌شده حذف شوند؟', 'zig3d-widgets'))),
            esc_html__('حذفِ موارد انتخاب‌شده', 'zig3d-widgets')
        );
    }

    private static function delete_link(int $id): string {
        $url = wp_nonce_url(
            add_query_arg(['page' => self::SLUG, 'zig3d_action' => 'delete', 'id' => $id], admin_url('admin.php')),
            'zig3d_consultations_delete_' . $id
        );

        return sprintf(
            '<a href="%1$s" class="button-link-delete" onclick="return confirm(%2$s)">%3$s</a>',
            esc_url($url),
            esc_attr(wp_json_encode(__('این درخواست حذف شود؟', 'zig3d-widgets'))),
            esc_html__('حذف', 'zig3d-widgets')
        );
    }

    /** «انتخابِ همه»یِ سرِ جدول — بدونِ آن، تیک‌زدنِ تک‌به‌تک برایِ حذفِ گروهی خسته‌کننده می‌شود. */
    private static function render_select_all_script(): void {
        ?>
        <script>
        document.getElementById('zig3d-consultations-select-all').addEventListener('change', function () {
            var checked = this.checked;
            document.querySelectorAll('input[name="ids[]"]').forEach(function (box) {
                box.checked = checked;
            });
        });
        </script>
        <?php
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
