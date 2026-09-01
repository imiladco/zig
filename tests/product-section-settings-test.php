<?php
/**
 * تشخیصِ سندِ Single Product — بدونِ فال‌بکِ رده‌نامِ کلاس.
 */

require_once __DIR__ . '/bootstrap.php';
/*
 * بدونِ این، ‎\Elementor\Controls_Manager‎ وجود ندارد و ثبتِ کنترل‌ها با
 * ‎Error‎ می‌ترکد — که ‎register_controls()‎ عمداً می‌گیردش تا ادیتورِ
 * کاربر نمیرد. نتیجه‌اش در تست یک شکستِ گیج‌کننده بود («هیچ کنترلی ثبت
 * نشد») که علتش هیچ ربطی به خودِ کلاس نداشت.
 */
require_once __DIR__ . '/lib/elementor-stub.php';
require_once dirname(__DIR__) . '/includes/product-section-settings.php';

use Zig3d_Widgets\Product_Section_Settings;

Tests::group('تنظیماتِ سکشنِ محصول › applies_to — فقط تطبیقِ دقیقِ نامِ سند');

$applies_to = new ReflectionMethod(Product_Section_Settings::class, 'applies_to');
$applies_to->setAccessible(true);

$product_document = new class {
    public function get_name(): string {
        return 'product';
    }
};

Tests::ok(
    'سندِ Single Product واقعی پذیرفته می‌شود',
    true === $applies_to->invoke(null, $product_document)
);

$other_document = new class {
    public function get_name(): string {
        return 'page';
    }
};

Tests::ok(
    'سندِ نامرتبط (page) رد می‌شود',
    false === $applies_to->invoke(null, $other_document)
);

/*
 * قبل از این فیکس، یک فال‌بکِ ‎stripos(get_class($document), 'product')‎
 * هم بود — یعنی هر سندی که تصادفاً «product» تویِ نامِ کلاسش داشت (مثلِ
 * سندِ آرشیوِ محصول، نه Single Product) هم قبول می‌شد. همان چیزی که با
 * ثبتِ کنترل‌هایِ اضافه رویِ سندهایِ نامرتبط، «شروطِ نمایش»یِ المنتورپرو و
 * ذخیره‌سازیِ سند را خراب کرد (v1.48.0). این کلاس دقیقاً همان سناریو را
 * شبیه‌سازی می‌کند: نامِ کلاسش شاملِ «Product» است ولی ‎get_name()‎
 * چیزِ دیگری برمی‌گرداند.
 */
final class Zig3d_Fake_Product_Archive_Document {
    public function get_name(): string {
        return 'product-archive';
    }
}

Tests::ok(
    'سندِ نامرتبطی که نامِ کلاسش شاملِ «Product» است هم رد می‌شود (نه فقط از رویِ رشته)',
    false === $applies_to->invoke(null, new Zig3d_Fake_Product_Archive_Document())
);

Tests::group('تنظیماتِ سکشنِ محصول › applies_to — ورودی‌هایِ نامعتبر');

Tests::ok('null رد می‌شود', false === $applies_to->invoke(null, null));
Tests::ok('رشته رد می‌شود', false === $applies_to->invoke(null, 'product'));

$no_get_name = new class {
};

Tests::ok(
    'شیءِ بدونِ متدِ get_name رد می‌شود',
    false === $applies_to->invoke(null, $no_get_name)
);

Tests::group('تنظیماتِ سکشنِ محصول › محافظ‌هایِ ثبتِ کنترل');

/*
 * سندِ ساختگی‌ای که رفتارِ لازمِ ‎Controls_Stack‎ را دارد و می‌شمارد چند بار
 * سکشن باز شد. دو سنجهٔ اینجا هر دو از تجربهٔ واقعیِ همین پروژه می‌آیند:
 *
 *   ۱) ثبتِ دوباره رویِ همان استک «Cannot redeclare control» می‌دهد. آن
 *      فاتال نیست، ولی رویِ ‎admin-ajax.php‎ی ادیتور نوتیس واردِ بدنهٔ
 *      پاسخ می‌شود، JSON را خراب می‌کند و ذخیره بی‌صدا می‌شکند.
 *   ۲) اگر ثبت وسطِ کار بترکد، سکشنِ بازمانده کنترل‌هایِ *بعدیِ* خودِ
 *      المنتور را می‌بلعد. پس باید بسته شود و خطا هم ادیتور را نکشد.
 */
final class Zig_Fake_Product_Doc {
    public int $opened = 0;
    public int $closed = 0;
    public array $controls = [];
    public bool $explode = false;

    public function get_name(): string { return 'product'; }

    /** @return array<string,mixed>|null */
    public function get_controls($id = null) {
        return null === $id ? $this->controls : ($this->controls[$id] ?? null);
    }

    public function start_controls_section($id, $args = []): void {
        $this->opened++;
        if ($this->explode) {
            throw new RuntimeException('شبیه‌سازیِ خطایِ المنتور');
        }
    }

    public function add_control($id, $args = []): void { $this->controls[$id] = $args; }
    public function end_controls_section(): void { $this->closed++; }
}

$doc = new Zig_Fake_Product_Doc();

Product_Section_Settings::register_controls($doc);
Tests::same('بارِ اول کنترل‌ها ثبت می‌شوند', $doc->opened, 1);
Tests::ok('کنترلِ کلیدِ اصلی ساخته شد', null !== $doc->get_controls('zig_guard_enabled'));
Tests::ok('کنترلِ هر شش سکشن ساخته شد', null !== $doc->get_controls('zig_section_class_downloads'));

Product_Section_Settings::register_controls($doc);
Tests::same('بارِ دوم دوباره ثبت نمی‌شود (جلوگیری از Cannot redeclare control)', $doc->opened, 1);

$broken = new Zig_Fake_Product_Doc();
$broken->explode = true;

$survived = true;
try {
    Product_Section_Settings::register_controls($broken);
} catch (\Throwable $e) {
    $survived = false;
}

Tests::ok('خطایِ وسطِ ثبت به بیرون درز نمی‌کند', $survived);
Tests::same('و سکشنِ نیمه‌باز بسته می‌شود', $broken->closed, 1);
