<?php
/**
 * دادهٔ Repeaterِ «قابلیت‌های محصول» (فیلدِ JetEngine ‎feature_showcase‎).
 *
 * اینجا فقط لایهٔ داده سنجیده می‌شود — نرمال‌سازی و فیلترها — نه رندر.
 * هیچ Elementor یا HTMLای اینجا نیست.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/woocommerce-stub.php';

$root = dirname(__DIR__);

require_once $root . '/includes/markup.php';
require_once $root . '/includes/feature-repeater.php';

use Zig3d_Widgets\Feature_Repeater;

/* ==========================================================================
 * حالت‌هایِ خالی
 * ======================================================================= */

Tests::group('Repeaterِ قابلیت‌ها › خالی');

zig_reset_products();

Tests::same('محصولِ بدونِ این متا → آرایهٔ خالی', Feature_Repeater::rows(0), []);

$noMeta = new WC_Product(['id' => 1, 'meta' => []]);
Tests::same('متایِ ثبت‌نشده → آرایهٔ خالی', Feature_Repeater::rows($noMeta->get_id()), []);

$emptyRepeater = new WC_Product(['id' => 2, 'meta' => ['feature_showcase' => []]]);
Tests::same('Repeaterِ خالی → آرایهٔ خالی', Feature_Repeater::rows($emptyRepeater->get_id()), []);

$notArray = new WC_Product(['id' => 3, 'meta' => ['feature_showcase' => 'garbage']]);
Tests::same('مقدارِ غیرِآرایه‌ای → آرایهٔ خالی', Feature_Repeater::rows($notArray->get_id()), []);

/* ==========================================================================
 * فیلترِ سطرها
 * ======================================================================= */

Tests::group('Repeaterِ قابلیت‌ها › فیلترِ سطرها');

zig_reset_products();

$rows = [
    // بدونِ لیبل — باید کنار برود
    ['feature_showcase_label' => '', 'feature_showcase_title' => 'عنوان', 'feature_showcase_dec' => 'توضیح'],
    // لیبل فقط فاصله/تگِ خالی — همچنان «خالی» است
    ['feature_showcase_label' => '  <br>  ', 'feature_showcase_title' => 'x'],
    // سطرِ معتبر
    ['feature_showcase_label' => 'محفظه', 'feature_showcase_title' => 'محفظهٔ کاری', 'feature_showcase_dec' => 'توضیحِ محفظه', 'feature_showcase_icon' => 55],
    // سطرِ کاملاً خالی (نه حتی کلید) — نباید خطا بدهد
    [],
    // چیزی که اصلاً آرایه نیست
    'not-a-row',
    // سطرِ معتبرِ دوم
    ['feature_showcase_label' => 'کنترل', 'feature_showcase_title' => '', 'feature_showcase_dec' => ''],
];

$product = new WC_Product(['id' => 10, 'meta' => ['feature_showcase' => $rows]]);
$result  = Feature_Repeater::rows($product->get_id());

Tests::same('فقط سطرهایِ با لیبل باقی می‌مانند', count($result), 2);
Tests::same('اولین سطرِ معتبر، لیبلِ درست دارد', $result[0]['label'], 'محفظه');
Tests::same('عنوان از همان سطر می‌آید', $result[0]['title'], 'محفظهٔ کاری');
Tests::same('توضیح از همان سطر می‌آید', $result[0]['description'], 'توضیحِ محفظه');
Tests::same('دومین سطرِ معتبر، لیبلِ درست دارد', $result[1]['label'], 'کنترل');
Tests::same('عنوانِ خالی → رشتهٔ خالی (نه چیزِ دیگر)', $result[1]['title'], '');
Tests::same('توضیحِ خالی → رشتهٔ خالی', $result[1]['description'], '');

/* ==========================================================================
 * نرمال‌سازیِ تصویر
 * ======================================================================= */

Tests::group('Repeaterِ قابلیت‌ها › نرمال‌سازیِ تصویر');

zig_reset_products();

function zig_feature_image_for(string $label, $icon) {
    $product = new WC_Product([
        'id'   => random_int(1000, 999999),
        'meta' => ['feature_showcase' => [['feature_showcase_label' => $label, 'feature_showcase_icon' => $icon]]],
    ]);

    return Feature_Repeater::rows($product->get_id())[0]['image'];
}

Tests::same('شناسهٔ عددی', zig_feature_image_for('a', 42), ['id' => 42, 'url' => '']);
Tests::same('شناسهٔ به‌صورتِ رشتهٔ عددی', zig_feature_image_for('b', '42'), ['id' => 42, 'url' => '']);
Tests::same('آدرسِ خام (رشتهٔ غیرِعددی)', zig_feature_image_for('c', 'https://x.test/a.jpg'), ['id' => 0, 'url' => 'https://x.test/a.jpg']);
Tests::same('آرایه با کلیدِ id', zig_feature_image_for('d', ['id' => 7, 'url' => 'https://x.test/b.jpg']), ['id' => 7, 'url' => '']);
Tests::same('آرایه فقط با کلیدِ url', zig_feature_image_for('e', ['url' => 'https://x.test/c.jpg']), ['id' => 0, 'url' => 'https://x.test/c.jpg']);
Tests::same('آرایه با کلیدِ ID بزرگ', zig_feature_image_for('f', ['ID' => 9]), ['id' => 9, 'url' => '']);
Tests::same('مقدارِ null → تصویرِ خالی', zig_feature_image_for('g', null), ['id' => 0, 'url' => '']);
Tests::same('رشتهٔ خالی → تصویرِ خالی', zig_feature_image_for('h', ''), ['id' => 0, 'url' => '']);
Tests::same('صفر → تصویرِ خالی (نه شناسهٔ صفر)', zig_feature_image_for('i', 0), ['id' => 0, 'url' => '']);
Tests::same('آرایهٔ بی‌ربط بدونِ کلیدِ شناخته‌شده → تصویرِ خالی', zig_feature_image_for('j', ['foo' => 'bar']), ['id' => 0, 'url' => '']);
