<?php
/**
 * تشخیصِ سندِ Single Product — بدونِ فال‌بکِ رده‌نامِ کلاس.
 */

require_once __DIR__ . '/bootstrap.php';
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
