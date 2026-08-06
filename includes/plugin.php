<?php
namespace Zig3d_Widgets;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * هستهٔ افزونه: ثبت دسته، ویجت‌ها و دارایی‌ها.
 *
 * افزونه عمداً هیچ جاوااسکریپتی به صفحه اضافه نمی‌کند. هر چهار ویجت کاملاً
 * با CSS کار می‌کنند: حالت‌های هاور و فوکوس و افکت‌ها همه اعلانی‌اند. یعنی
 * صفر بایت JS، بدون هزینهٔ اجرا روی نخ اصلی و بدون هیچ وابستگی‌ای که بتواند
 * نصفه‌کاره بماند.
 */
final class Plugin {

    public const CATEGORY = 'zig3d';

    /** کلید ذخیرهٔ نسخهٔ نصب‌شده، برای تشخیص به‌روزرسانی */
    private const VERSION_OPTION = 'zig3d_widgets_installed_version';

    private static ?self $instance = null;

    /** فایل هر ویجت => نام کلاس */
    private const WIDGETS = [
        'feature-card'  => Widgets\Feature_Card::class,
        'bullet-list'   => Widgets\Bullet_List::class,
        'button'        => Widgets\Button::class,
        'product-price' => Widgets\Product_Price::class,
    ];

    public static function instance(): self {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
        add_action('elementor/elements/categories_registered', [$this, 'register_category']);
        add_action('elementor/widgets/register', [$this, 'register_widgets']);

        // ثبت (نه enqueue): هر ویجت با get_style_depends خودش تصمیم می‌گیرد،
        // پس صفحه‌ای که ویجتی از این افزونه ندارد هیچ فایلی لود نمی‌کند.
        add_action('elementor/frontend/after_register_styles', [$this, 'register_styles']);

        // در ادیتور همیشه لود می‌شود تا پیش‌نمایش با سایت یکی باشد
        add_action('elementor/editor/after_enqueue_styles', [$this, 'enqueue_editor_styles']);

        add_action('init', [$this, 'maybe_flush_after_update'], 20);
    }

    /* =====================================================================
     * ثبت
     * =================================================================== */

    /**
     * دستهٔ اختصاصی در پنل المنتور.
     *
     * @param \Elementor\Elements_Manager $manager
     */
    public function register_category($manager): void {
        $manager->add_category(
            self::CATEGORY,
            [
                'title' => __('زیگ', 'zig3d-widgets'),
                'icon'  => 'eicon-elementor-square',
            ]
        );
    }

    /**
     * @param \Elementor\Widgets_Manager $manager
     */
    public function register_widgets($manager): void {
        require_once ZIG3D_WIDGETS_PATH . 'includes/svg.php';
        require_once ZIG3D_WIDGETS_PATH . 'includes/markup.php';
        require_once ZIG3D_WIDGETS_PATH . 'includes/price.php';
        require_once ZIG3D_WIDGETS_PATH . 'includes/widgets/traits/link.php';
        require_once ZIG3D_WIDGETS_PATH . 'includes/widgets/traits/icon.php';
        require_once ZIG3D_WIDGETS_PATH . 'includes/widgets/traits/box.php';

        foreach (self::WIDGETS as $file => $class) {
            /*
             * ویجت قیمت بدون ووکامرس فقط یک ورودی بی‌فایده در پنل است که
             * کاربر رویش کلیک می‌کند و چیزی نمی‌بیند. پس اصلاً ثبت نمی‌شود.
             */
            if ('product-price' === $file && !class_exists('WooCommerce')) {
                continue;
            }

            require_once ZIG3D_WIDGETS_PATH . 'includes/widgets/' . $file . '.php';
            $manager->register(new $class());
        }
    }

    public function register_styles(): void {
        wp_register_style(
            'zig3d-widgets',
            ZIG3D_WIDGETS_URL . 'assets/css/zig3d-widgets.css',
            [],
            ZIG3D_WIDGETS_VERSION
        );
    }

    public function enqueue_editor_styles(): void {
        $this->register_styles();
        wp_enqueue_style('zig3d-widgets');
    }

    /* =====================================================================
     * به‌روزرسانی
     * =================================================================== */

    /**
     * پاک‌کردن کش المنتور بعد از هر تغییر نسخه.
     *
     * المنتور CSS هر صفحه را در یک فایل جداگانه می‌سازد و تا وقتی چیزی آن را
     * باطل نکند همان را سرو می‌کند. بعد از به‌روزرسانی افزونه — که ممکن است
     * سلکتورها یا متغیرها عوض شده باشند — آن فایل کهنه است و سایت با استایل
     * قدیمی و ادیتور با استایل جدید رندر می‌شود. نتیجه: «در ادیتور درست است
     * ولی در سایت نه»، بدون هیچ نشانهٔ دیگری.
     *
     * نسخهٔ فایل CSS خودمان هم به همین ترتیب عوض می‌شود، پس کش مرورگر و CDN
     * هم با ?ver جدید باطل می‌شوند.
     */
    public function maybe_flush_after_update(): void {
        if (get_option(self::VERSION_OPTION) === ZIG3D_WIDGETS_VERSION) {
            return;
        }

        update_option(self::VERSION_OPTION, ZIG3D_WIDGETS_VERSION, false);

        if (class_exists('\Elementor\Plugin') && isset(\Elementor\Plugin::$instance->files_manager)) {
            \Elementor\Plugin::$instance->files_manager->clear_cache();
        }
    }
}
