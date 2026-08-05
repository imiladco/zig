<?php
namespace Zig3d_Widgets\Widgets\Traits;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * پیوند: کنترل، ویژگی‌ها و انتخاب تگ درست.
 *
 * دو نکته که همیشه از قلم می‌افتند و اینجا یک‌جا حل شده‌اند:
 *
 *   • ‎<a>‎ بدون href در HTML لینک نیست؛ نه فوکوس می‌گیرد نه صفحه‌خوان آن را
 *     می‌خواند. پس وقتی کاربر آدرسی وارد نکرده، تگ باید چیز دیگری باشد.
 *   • ‎target="_blank"‎ بدون ‎rel‎ درست، پنجرهٔ مقصد را به window.opener وصل
 *     می‌کند. المنتور خودش این را در add_link_attributes می‌سازد، پس ساخت
 *     دستیِ href هیچ‌وقت جایگزین آن نمی‌شود.
 */
trait Link {

    /**
     * کنترل پیوند.
     *
     * @param array $args بازنویسی هر کلیدی از تعریف کنترل.
     */
    protected function add_link_control(string $name = 'link', array $args = []): void {
        $this->add_control(
            $name,
            array_merge(
                [
                    'label'       => __('پیوند', 'zig3d-widgets'),
                    'type'        => \Elementor\Controls_Manager::URL,
                    'dynamic'     => ['active' => true],
                    'placeholder' => 'https://zig3d.com',
                    'options'     => ['url', 'is_external', 'nofollow', 'custom_attributes'],
                    'label_block' => true,
                ],
                $args
            )
        );
    }

    /** آیا این تنظیم، پیوند واقعی دارد؟ */
    protected function has_link(array $settings, string $name = 'link'): bool {
        return '' !== trim((string) ($settings[$name]['url'] ?? ''));
    }

    /**
     * ویژگی‌های پیوند را روی یک کلیدِ render می‌نشاند و تگ مناسب را برمی‌گرداند.
     *
     * @param string $fallback تگی که وقتی پیوندی در کار نیست به کار می‌رود.
     */
    protected function apply_link(array $settings, string $key, string $fallback = 'div', string $name = 'link'): string {
        if (!$this->has_link($settings, $name)) {
            return $fallback;
        }

        $this->add_link_attributes($key, $settings[$name]);

        return 'a';
    }
}
