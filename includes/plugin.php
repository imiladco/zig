<?php
namespace Zig3d_Widgets;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * هستهٔ افزونه: ثبت دسته، ویجت‌ها و دارایی‌ها.
 *
 * هر پنج ویجت فعلی کاملاً با CSS کار می‌کنند: حالت‌های هاور و فوکوس و
 * افکت‌ها همه اعلانی‌اند. یعنی صفر بایت JS، بدون هزینهٔ اجرا روی نخ اصلی و
 * بدون هیچ وابستگی‌ای که بتواند نصفه‌کاره بماند.
 *
 * این قاعده تا وقتی برقرار است که ویجتی وضعیت نداشته باشد. آرشیو محصولات
 * دارد — فیلتر، ترتیب، صفحه، تاریخچهٔ مرورگر — و اولین ویجتی خواهد بود که
 * جاوااسکریپت لازم دارد. آنجا هم قاعده این است: HTML اولیه کامل از PHP
 * می‌آید و JS فقط رفتار را رویش سوار می‌کند.
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
        'product-stock' => Widgets\Product_Stock::class,
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

        add_action('init', [$this, 'boot_filters'], 5);

        if (is_admin()) {
            add_action('init', [$this, 'boot_admin'], 6);
        }

        $this->watch_facet_cache();
    }

    /* =====================================================================
     * فیلترهای آرشیو
     * =================================================================== */

    /**
     * لایهٔ فیلترها — در سایت و پنل هر دو.
     *
     * ثبت متای دسته باید همه‌جا انجام شود، نه فقط در پنل: بدون آن،
     * ‎sanitize_callback‎ روی هر نوشتنِ دیگری (مثلاً از یک اسکریپت مهاجرت)
     * اعمال نمی‌شود.
     */
    public function boot_filters(): void {
        if (!class_exists('WooCommerce')) {
            return;
        }

        foreach (['query-state', 'facets', 'filter-schema', 'schema-store', 'sorting', 'attributes', 'archive-query'] as $file) {
            require_once ZIG3D_WIDGETS_PATH . 'includes/' . $file . '.php';
        }

        Schema_Store::register();
    }

    /**
     * پنل: بخش «فیلترهای این دسته» و صفحهٔ طرح‌های مشترک.
     *
     * فقط با ووکامرس معنا دارد — بدون آن نه ‎product_cat‎ هست و نه ویژگی‌ای
     * برای فهرست‌کردن.
     */
    public function boot_admin(): void {
        if (!class_exists('WooCommerce')) {
            return;
        }

        require_once ZIG3D_WIDGETS_PATH . 'includes/admin/category-filters.php';
        require_once ZIG3D_WIDGETS_PATH . 'includes/admin/schemas-page.php';

        Admin\Category_Filters::boot();
        Admin\Schemas_Page::boot();
    }

    /**
     * باطل‌کردن کشِ شمارش فست.
     *
     * شمارش‌ها با TTL کوتاه هم منقضی می‌شوند، ولی TTL تنها ضامنِ کهنه‌نبودن
     * نیست: مدیری که محصولی را منتشر می‌کند و بلافاصله آرشیو را باز می‌کند،
     * باید عدد تازه ببیند نه چیزی که تا پنج دقیقهٔ دیگر درست می‌شود.
     *
     * پاک‌کردن واقعیِ کلیدها ممکن نیست — نمی‌دانیم چند ترکیب فیلتر ذخیره
     * شده — پس ‎flush()‎ فقط شمارهٔ نسخه را جلو می‌برد و کلیدهای قدیمی
     * خودشان می‌میرند.
     */
    private function watch_facet_cache(): void {
        /*
         * هوک‌های خودِ ووکامرس، نه ‎save_post‎: آن یکی برای پیش‌نویس خودکار و
         * بازبینی هم صدا زده می‌شود و کش را بی‌دلیل دور می‌ریخت.
         */
        foreach (['woocommerce_update_product', 'woocommerce_delete_product'] as $hook) {
            add_action($hook, [$this, 'flush_facet_cache'], 20);
        }

        /*
         * ترم‌ها هم مهم‌اند: ترم تازه یعنی یک گزینهٔ تازه در سایدبار. ولی این
         * هوک‌ها برای *هر* تاکسونومی صدا زده می‌شوند — هر برچسب نوشته، هر
         * دستهٔ وبلاگ. بدون بررسی، ویرایش یک برچسب بی‌ربط کل شمارش‌های
         * فروشگاه را باطل می‌کرد.
         */
        foreach (['created_term', 'edited_term', 'delete_term'] as $hook) {
            add_action($hook, [$this, 'flush_facet_cache_for_term'], 20, 3);
        }
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
        require_once ZIG3D_WIDGETS_PATH . 'includes/selector.php';
        require_once ZIG3D_WIDGETS_PATH . 'includes/price.php';
        require_once ZIG3D_WIDGETS_PATH . 'includes/stock.php';
        require_once ZIG3D_WIDGETS_PATH . 'includes/widgets/traits/link.php';
        require_once ZIG3D_WIDGETS_PATH . 'includes/widgets/traits/icon.php';
        require_once ZIG3D_WIDGETS_PATH . 'includes/widgets/traits/box.php';
        require_once ZIG3D_WIDGETS_PATH . 'includes/widgets/traits/pulse.php';

        foreach (self::WIDGETS as $file => $class) {
            /*
             * ویجت‌های فروشگاهی بدون ووکامرس فقط ورودی‌های بی‌فایده‌ای در
             * پنل‌اند که کاربر رویشان کلیک می‌کند و چیزی نمی‌بیند. پس اصلاً
             * ثبت نمی‌شوند.
             */
            if (0 === strpos($file, 'product-') && !class_exists('WooCommerce')) {
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

    public function flush_facet_cache(): void {
        require_once ZIG3D_WIDGETS_PATH . 'includes/attributes.php';

        Attributes::flush();
    }

    /**
     * @param int    $term_id
     * @param int    $tt_id
     * @param string $taxonomy
     */
    public function flush_facet_cache_for_term($term_id, $tt_id = 0, $taxonomy = ''): void {
        $taxonomy = (string) $taxonomy;

        if ('product_cat' !== $taxonomy && 0 !== strpos($taxonomy, 'pa_')) {
            return;
        }

        $this->flush_facet_cache();
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
