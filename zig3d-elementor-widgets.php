<?php
/**
 * Plugin Name:       ZIG3D Elementor Widgets
 * Plugin URI:        https://zig3d.com
 * Description:       ویجت‌های اختصاصی المنتور برای وب‌سایت گروه زیگ (ZIG3D)
 * Version:           1.90.0
 * Author:            imiladco
 * Author URI:        https://zig3d.com
 * Text Domain:       zig3d-widgets
 * Domain Path:       /languages
 * Requires PHP:      7.4
 * Requires at least: 6.0
 * Elementor tested up to: 3.30
 *
 * @package Zig3d_Widgets
 */

if (!defined('ABSPATH')) {
    exit;
}

define('ZIG3D_WIDGETS_VERSION', '1.90.0');
define('ZIG3D_WIDGETS_FILE', __FILE__);
define('ZIG3D_WIDGETS_PATH', plugin_dir_path(__FILE__));
define('ZIG3D_WIDGETS_URL', plugin_dir_url(__FILE__));

define('ZIG3D_WIDGETS_MIN_ELEMENTOR', '3.13.0');
define('ZIG3D_WIDGETS_MIN_PHP', '7.4');

/**
 * بارگذاری افزونه بعد از لود شدن همهٔ افزونه‌ها، تا وجود المنتور قابل بررسی باشد.
 */
function zig3d_widgets_init(): void {

    // ترجمه‌ها روی init لود می‌شوند نه زودتر، وگرنه وردپرس ۶.۷ به بعد
    // نوتیس _load_textdomain_just_in_time می‌دهد.
    add_action('init', static function (): void {
        load_plugin_textdomain(
            'zig3d-widgets',
            false,
            dirname(plugin_basename(ZIG3D_WIDGETS_FILE)) . '/languages'
        );
    });

    if (version_compare(PHP_VERSION, ZIG3D_WIDGETS_MIN_PHP, '<')) {
        add_action('admin_notices', 'zig3d_widgets_notice_php');
        return;
    }

    if (!did_action('elementor/loaded')) {
        add_action('admin_notices', 'zig3d_widgets_notice_elementor');
        return;
    }

    if (defined('ELEMENTOR_VERSION') && version_compare(ELEMENTOR_VERSION, ZIG3D_WIDGETS_MIN_ELEMENTOR, '<')) {
        add_action('admin_notices', 'zig3d_widgets_notice_elementor_version');
        return;
    }

    require_once ZIG3D_WIDGETS_PATH . 'includes/plugin.php';
    \Zig3d_Widgets\Plugin::instance();
}
add_action('plugins_loaded', 'zig3d_widgets_init');

/**
 * نمایش یک اعلان ادمین. همهٔ اعلان‌های افزونه از همین‌جا می‌گذرند تا مارک‌آپ
 * و اسکیپ در یک جا بماند.
 *
 * @param string $message متن آمادهٔ نمایش (اسکیپ‌نشده).
 */
function zig3d_widgets_notice(string $message): void {
    printf(
        '<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
        esc_html($message)
    );
}

function zig3d_widgets_notice_php(): void {
    zig3d_widgets_notice(
        sprintf(
            /* translators: %s: نسخهٔ موردنیاز PHP */
            __('افزونهٔ «ویجت‌های زیگ» به PHP نسخهٔ %s یا بالاتر نیاز دارد.', 'zig3d-widgets'),
            ZIG3D_WIDGETS_MIN_PHP
        )
    );
}

function zig3d_widgets_notice_elementor(): void {
    zig3d_widgets_notice(
        __('افزونهٔ «ویجت‌های زیگ» برای کار کردن به افزونهٔ المنتور نیاز دارد. لطفاً المنتور را نصب و فعال کنید.', 'zig3d-widgets')
    );
}

function zig3d_widgets_notice_elementor_version(): void {
    zig3d_widgets_notice(
        sprintf(
            /* translators: %s: نسخهٔ موردنیاز المنتور */
            __('افزونهٔ «ویجت‌های زیگ» به المنتور نسخهٔ %s یا بالاتر نیاز دارد. لطفاً المنتور را به‌روزرسانی کنید.', 'zig3d-widgets'),
            ZIG3D_WIDGETS_MIN_ELEMENTOR
        )
    );
}
