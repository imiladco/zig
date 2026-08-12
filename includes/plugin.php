<?php
namespace Zig3d_Widgets;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * هستهٔ افزونه: ثبت دسته، ویجت‌ها و دارایی‌ها.
 *
 * پنج ویجت اول کاملاً با CSS کار می‌کنند: حالت‌های هاور و فوکوس و افکت‌ها
 * همه اعلانی‌اند. یعنی صفر بایت JS روی صفحه‌ای که فقط آن‌ها را دارد.
 *
 * آرشیو محصولات استثناست، چون وضعیت دارد: فیلتر، ترتیب، صفحه، تاریخچهٔ
 * مرورگر. قاعده آنجا هم برقرار است، فقط شکلش فرق می‌کند: HTML اولیه کامل
 * از PHP می‌آید و JS فقط رفتار را رویش سوار می‌کند — اگر لود نشود، صفحه
 * هنوز کار می‌کند، فقط کندتر. و اسکریپت از راه ‎get_script_depends‎ می‌آید،
 * پس صفحه‌ای که این ویجت رویش نیست همچنان صفر بایت می‌گیرد.
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
        'product-archive' => Widgets\Product_Archive::class,
        'product-gallery' => Widgets\Product_Gallery::class,
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

        // همان‌طور برای اسکریپت: ویجتی که JS لازم ندارد، هیچ فایلی نمی‌آورد
        add_action('elementor/frontend/after_register_scripts', [$this, 'register_scripts']);

        // در ادیتور همیشه لود می‌شود تا پیش‌نمایش با سایت یکی باشد
        add_action('elementor/editor/after_enqueue_styles', [$this, 'enqueue_editor_styles']);

        /*
         * نقطهٔ REST گالری. برخلافِ نقطهٔ آژاکسِ آرشیو (که وضعیتِ فیلتر و
         * صفحه از کلاینت می‌آید و باید نانس داشته باشد)، این یکی فقط
         * آدرسِ تصاویرِ یک محصولِ منتشرشده را برمی‌گرداند — دادهٔ کاملاً
         * عمومی، بدون نیاز به احراز هویت، پس صفحه/CDN هم می‌تواند کشش کند.
         */
        add_action('rest_api_init', [$this, 'register_rest_routes']);

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

        require_once ZIG3D_WIDGETS_PATH . 'includes/price.php';
        require_once ZIG3D_WIDGETS_PATH . 'includes/stock.php';

        foreach (['query-state', 'facets', 'filter-schema', 'schema-store', 'spec-schema', 'spec-store', 'sorting', 'attributes', 'archive-query', 'seo', 'archive-head', 'archive-response', 'archive-endpoint', 'card', 'product-card'] as $file) {
            require_once ZIG3D_WIDGETS_PATH . 'includes/' . $file . '.php';
        }

        Schema_Store::register();

        /*
         * قالب‌های مشخصات فنی؛ خواهرِ Schema_Store برای گروه‌بندیِ نمایشِ
         * مشخصات — نه فیلترِ سایدبار. ثبتِ متا هم به همان دلیل بیرون از
         * شرطِ ادمین است.
         */
        Spec_Store::register();

        /*
         * بیرون از شرط پایین، و این عمدی است: ‎admin-ajax.php‎ از نظر
         * وردپرس «پنل» است و ‎is_admin()‎ آنجا ‎true‎ می‌دهد. اگر داخل شرط
         * می‌رفت، نقطهٔ آژاکس هیچ‌وقت ثبت نمی‌شد و هر درخواست ‎0‎ برمی‌گرداند
         * — بدون هیچ خطایی، فقط یک ویجتی که کلیک‌هایش کار نمی‌کنند.
         */
        Archive_Endpoint::boot();

        /*
         * فقط در سایت. در پنل نه کوئری آرشیوی هست و نه ‎<head>‎ی که این
         * تصمیم‌ها به آن تعلق داشته باشند.
         */
        if (!is_admin()) {
            Archive_Head::boot();
        }
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
        require_once ZIG3D_WIDGETS_PATH . 'includes/admin/category-specs.php';
        require_once ZIG3D_WIDGETS_PATH . 'includes/admin/spec-schemas-page.php';

        Admin\Category_Filters::boot();
        Admin\Schemas_Page::boot();
        Admin\Category_Specs::boot();
        Admin\Spec_Schemas_Page::boot();
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

        /*
         * آرشیو به کل لایهٔ فیلتر تکیه دارد. ‎boot_filters()‎ روی ‎init‎
         * می‌نشیند و ثبت ویجت‌ها هم بعد از آن است، ولی المنتور در بعضی
         * مسیرها زودتر صدا می‌زند؛ ‎require_once‎ بی‌هزینه است و جای
         * «کلاس پیدا نشد» را می‌بندد.
         */
        if (class_exists('WooCommerce')) {
            $this->boot_filters();
        }

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

    /**
     * اسکریپت آرشیو.
     *
     * ‎in_footer‎ چون هیچ کاری قبل از رسیدن DOM ندارد. آرگومان آرایه‌ایِ
     * ‎strategy‎ عمداً استفاده نشده: از وردپرس ۶٫۳ آمده و این افزونه ۶٫۰ را
     * هم پشتیبانی می‌کند، جایی که آن آرایه به‌عنوان «درست است» تفسیر
     * می‌شود و رفتارش تصادفاً یکی درمی‌آید — تا روزی که نیاید.
     *
     * وابستگی خالی است: این فایل به jQuery نیاز ندارد. آوردن jQuery فقط
     * برای یک ‎querySelectorAll‎، هشتاد کیلوبایت به صفحه‌ای اضافه می‌کرد که
     * ممکن است اصلاً به آن نیاز نداشته باشد.
     */
    public function register_scripts(): void {
        wp_register_script(
            'zig3d-archive',
            ZIG3D_WIDGETS_URL . 'assets/js/zig3d-archive.js',
            [],
            ZIG3D_WIDGETS_VERSION,
            true
        );

        /*
         * کنترلرِ مشترکِ مودال‌ها فایلِ جدا دارد — نه چون گالری تنها
         * مصرف‌کننده‌اش می‌ماند، بلکه چون هر ویجتِ مودال‌دارِ بعدیِ این
         * افزونه باید همان حبسِ فوکوس/inert/Esc را بگیرد بدون کپی‌کردنِ
         * منطقش. گالری آن را به‌عنوانِ وابستگی می‌آورد تا قبل از خودش لود شود.
         */
        wp_register_script(
            'zig3d-modal',
            ZIG3D_WIDGETS_URL . 'assets/js/zig3d-modal.js',
            [],
            ZIG3D_WIDGETS_VERSION,
            true
        );

        /*
         * گالری فایل جدا دارد و نه بخشی از آرشیو، چون هیچ صفحه‌ای هر دو را
         * لازم ندارد: آرشیو در فهرست است و گالری در صفحهٔ محصول. یک فایلِ
         * مشترک یعنی صفحهٔ محصول کل منطق فیلتر و صفحه‌بندی را هم می‌گیرد.
         */
        wp_register_script(
            'zig3d-gallery',
            ZIG3D_WIDGETS_URL . 'assets/js/zig3d-gallery.js',
            ['zig3d-modal'],
            ZIG3D_WIDGETS_VERSION,
            true
        );
    }

    public function enqueue_editor_styles(): void {
        $this->register_styles();
        wp_enqueue_style('zig3d-widgets');
    }

    /**
     * نقطهٔ REST عمومی برایِ لودِ ایجکسیِ تصاویرِ گالریِ محصول.
     *
     * پورتِ مستقیمِ ‎rest_product_gallery()‎ از almasara-elementor-widgets؛
     * فقط مسیر از ‎almasara/v1‎ به ‎zig3d/v1‎ عوض شده.
     */
    public function register_rest_routes(): void {
        register_rest_route('zig3d/v1', '/product-gallery/(?P<id>\d+)', [
            'methods'             => 'GET',
            'permission_callback' => '__return_true',
            'args'                => [
                'id' => ['sanitize_callback' => 'absint'],
            ],
            'callback'            => [$this, 'rest_product_gallery'],
        ]);
    }

    /**
     * خروجی فقط URLِ تصاویر است (دادهٔ عمومی)، پس کشِ صفحه/CDN هم می‌تواند
     * کشش کند — به همین دلیل هم ‎permission_callback‎ باز است و نه پشتِ نانس.
     */
    public function rest_product_gallery($request) {
        if (!function_exists('wc_get_product')) {
            return new \WP_Error('woocommerce_missing', 'WooCommerce is not active.', ['status' => 500]);
        }

        $product = wc_get_product((int) $request['id']);
        if (!$product || 'publish' !== $product->get_status()) {
            return new \WP_Error('not_found', 'Product not found.', ['status' => 404]);
        }

        $ids = [];
        if ($product->get_image_id()) {
            $ids[] = (int) $product->get_image_id();
        }
        foreach ($product->get_gallery_image_ids() as $gallery_id) {
            $ids[] = (int) $gallery_id;
        }

        $images = [];
        foreach ($ids as $attachment_id) {
            $full = wp_get_attachment_image_src($attachment_id, 'large');
            if (!$full) {
                $full = wp_get_attachment_image_src($attachment_id, 'full');
            }
            if (!$full) {
                continue;
            }

            $thumb = wp_get_attachment_image_src($attachment_id, 'medium');

            $images[] = [
                'full'  => $full[0],
                'thumb' => $thumb ? $thumb[0] : $full[0],
                'alt'   => (string) get_post_meta($attachment_id, '_wp_attachment_image_alt', true),
            ];
        }

        $response = rest_ensure_response($images);
        $response->header('Cache-Control', 'public, max-age=3600');

        return $response;
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
