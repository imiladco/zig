<?php
/**
 * زمانِ به‌روزرسانیِ قیمت — از افزونهٔ نرخِ ارز.
 *
 * افزونهٔ واقعی (mns-woocommerce-rate-based-products) اینجا نصب نیست و
 * هستهٔ خودش هم رمزنگاری‌شده است، پس استاب‌های زیر دقیقاً همان API‌یِ
 * عمومی‌ای را می‌سازند که از رویِ فایل‌هایِ ساده‌متنِ خودِ افزونه
 * (ویجت‌ها، شورت‌کدها، متاباکس‌ها) استخراج شد:
 *
 *     new \MNS\Navasan\Includes\Product($id)
 *         ->get_product()      // اعتبار
 *         ->get_active()       // 'yes' یا خالی — قراردادِ چک‌باکسِ ووکامرس
 *         ->get_currency_id()
 *
 *     mns_navasan()->get_currency($currency_id)
 *         ->get_update_time()  // Unix timestamp
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/woocommerce-stub.php';

$root = dirname(__DIR__);

require_once $root . '/includes/rate-price.php';

use Zig3d_Widgets\Rate_Price;

/* --------------------------------------------------------------------------
 * استابِ افزونهٔ نوسان
 * ----------------------------------------------------------------------- */

$GLOBALS['__zig_mns_products'] = [];
$GLOBALS['__zig_mns_currencies'] = [];

if (!class_exists('MNS_Currency_Stub')) {
    final class MNS_Currency_Stub {
        private int $update_time;

        public function __construct(int $update_time) {
            $this->update_time = $update_time;
        }

        public function get_update_time(): int { return $this->update_time; }
    }
}

if (!class_exists('MNS_Product_Stub')) {
    final class MNS_Product_Stub {
        private array $data;

        public function __construct(int $id) {
            $this->data = $GLOBALS['__zig_mns_products'][$id] ?? [];
        }

        public function get_product() { return [] !== $this->data; }
        public function get_active() { return $this->data['active'] ?? ''; }
        public function get_currency_id() { return $this->data['currency_id'] ?? 0; }
    }
}

if (!function_exists('mns_navasan')) {
    function mns_navasan() {
        return new class {
            public function get_currency($id) {
                return $GLOBALS['__zig_mns_currencies'][$id] ?? null;
            }
        };
    }
}

/*
 * ‎class_alias‎ چون نمی‌شود ‎namespace MNS\Navasan\Includes‎ را در همین
 * فایل باز کرد و از ‎class Product‎ استفاده کرد — این فایل خودش در
 * فضای نامِ سراسری اجرا می‌شود و ‎Rate_Price‎ دقیقاً همان نامِ کاملِ
 * کلاس را صدا می‌زند.
 */
if (!class_exists('\MNS\Navasan\Includes\Product')) {
    class_alias('MNS_Product_Stub', '\MNS\Navasan\Includes\Product');
}

/* ==========================================================================
 * نبودِ افزونه
 * ======================================================================= */

Tests::group('زمانِ به‌روزرسانیِ قیمت › افزونه نصب نیست');

/*
 * این گروه را نمی‌شود با حذفِ کلاس سنجید — کلاس بالا برایِ همین فایل
 * تعریف شده و تا آخرِ پروسه می‌ماند. پس مستقیم رفتارِ «دادهٔ محصول
 * نیست» سنجیده می‌شود، همان چیزی که وقتی افزونه غایب باشد هم پیش می‌آید.
 */
Tests::same('محصولِ ثبت‌نشده صفر می‌دهد', Rate_Price::updated_at(999), 0);
Tests::same('نمایشش هم خالی است', Rate_Price::display(999), '');

/* ==========================================================================
 * محصولِ نرخ‌محور
 * ======================================================================= */

Tests::group('زمانِ به‌روزرسانیِ قیمت › نرخ‌محور');

$GLOBALS['__zig_mns_products'][501] = ['active' => 'yes', 'currency_id' => 7];
$GLOBALS['__zig_mns_currencies'][7] = new MNS_Currency_Stub(1_700_000_000);

Tests::same('زمان از ارزِ همان محصول می‌آید', Rate_Price::updated_at(501), 1_700_000_000);

$GLOBALS['__zig_options']['date_format'] = 'Y-m-d';
$GLOBALS['__zig_options']['time_format'] = 'H:i';

Tests::keeps('نمایش با فرمتِ سایت است', Rate_Price::display(501), date('Y-m-d', 1_700_000_000));

/* --------------------------------------------------------------------------
 * دانه‌بندیِ واریانت — دو واریانتِ یک محصول می‌توانند وضعِ متفاوت داشته باشند
 * ----------------------------------------------------------------------- */

Tests::group('زمانِ به‌روزرسانیِ قیمت › دانه‌بندیِ واریانت');

$GLOBALS['__zig_mns_products'][601] = ['active' => 'yes', 'currency_id' => 7];
$GLOBALS['__zig_mns_products'][602] = []; // واریانتِ دیگر، نرخ‌محور نیست

Tests::same('واریانتِ نرخ‌محور زمان می‌دهد', Rate_Price::updated_at(601), 1_700_000_000);
Tests::same('واریانتِ عادیِ همان محصول صفر می‌دهد', Rate_Price::updated_at(602), 0);

/* ==========================================================================
 * حالت‌هایِ منفی
 * ======================================================================= */

Tests::group('زمانِ به‌روزرسانیِ قیمت › حالت‌هایِ منفی');

/* محصولی که در افزونهٔ نوسان اصلاً ثبت نشده */
Tests::same('محصولِ ناموجود در نوسان صفر می‌دهد', Rate_Price::updated_at(9999), 0);

/* «Rate Based» خاموش است */
$GLOBALS['__zig_mns_products'][701] = ['active' => '', 'currency_id' => 7];
Tests::same('نرخ‌محورِ خاموش صفر می‌دهد', Rate_Price::updated_at(701), 0);

/* نرخ‌محور روشن ولی دسته/ارزی انتخاب نشده */
$GLOBALS['__zig_mns_products'][702] = ['active' => 'yes', 'currency_id' => 0];
Tests::same('بدونِ ارزِ انتخاب‌شده صفر می‌دهد', Rate_Price::updated_at(702), 0);

/* ارزی که دیگر وجود ندارد (حذف‌شده) */
$GLOBALS['__zig_mns_products'][703] = ['active' => 'yes', 'currency_id' => 999];
Tests::same('ارزِ حذف‌شده صفر می‌دهد', Rate_Price::updated_at(703), 0);

Tests::same('شناسهٔ صفر یا منفی هم صفر می‌دهد', Rate_Price::updated_at(0), 0);

/* ==========================================================================
 * فرمت‌کردنِ زمانِ از پیش خوانده‌شده
 * ======================================================================= */

Tests::group('زمانِ به‌روزرسانیِ قیمت › فرمت مستقیم');

/*
 * این متد برایِ حلقهٔ واریانت‌هایِ کانفیگ‌گر است: هر واریانت زمانش را یک‌بار
 * با ‎updated_at()‎ می‌خواند و نگه می‌دارد؛ فرمت‌کردنش نباید دوباره به نوسان
 * سر بزند، فقط باید همان قالبِ ‎display()‎ را رویِ عددِ آماده اجرا کند.
 */
Tests::keeps('همان فرمتِ سایت، بدونِ خواندنِ دوباره', Rate_Price::format(1_700_000_000), date('Y-m-d', 1_700_000_000));
Tests::same('صفر چیزی نمی‌دهد', Rate_Price::format(0), '');
Tests::same('منفی هم چیزی نمی‌دهد', Rate_Price::format(-5), '');

/*
 * ساعت طبقِ طرح همیشه ۲۴ساعته است — برخلافِ تاریخ، به ‎time_format‎ِ
 * سایت وابسته نیست. پیش‌فرضِ خودِ وردپرس ‎'g:i a'‎ (۱۲ساعته با ق.ظ/ب.ظ)
 * است؛ همین را عمداً اینجا می‌گذاریم تا اگر کسی دوباره ‎get_option‎ را
 * برای ساعت اضافه کند، این سنجه قرمز شود.
 */
$GLOBALS['__zig_options']['time_format'] = 'g:i a';

Tests::same(
    'ساعت همیشه ۲۴ساعته است، حتی اگر تنظیماتِ سایت ۱۲ساعته باشد',
    Rate_Price::format(1_700_000_000),
    date('Y-m-d', 1_700_000_000) . ' ، ' . date('H:i', 1_700_000_000)
);
