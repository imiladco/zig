<?php
/**
 * ‎Video_Gallery_Field::items()‎ — نرمال‌سازیِ همهٔ شکل‌هایِ ممکنِ فیلدِ
 * Galleryِ JetEngine + استخراجِ دادهٔ نمایشیِ هر ویدئو از خودِ پیوست.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/elementor-stub.php';
require_once __DIR__ . '/lib/woocommerce-stub.php';

$root = dirname(__DIR__);
require_once $root . '/includes/markup.php';
require_once $root . '/includes/video-gallery-field.php';

use Zig3d_Widgets\Video_Gallery_Field;

function zig_vg_reset(): void {
    \WC_Product::$registry = [];
    $GLOBALS['__zig_attachments'] = [];
}

/* =========================================================================
 * ورودی‌های نامعتبر/خالی
 * ======================================================================= */

Tests::group('گالریِ ویدئو › ورودیِ نامعتبر');

zig_vg_reset();
Tests::same('شناسهٔ محصولِ صفر → خالی', Video_Gallery_Field::items(0, 'zig-product-video'), []);

zig_vg_reset();
new \WC_Product(['id' => 5, 'meta' => ['zig-product-video' => [12]]]);
Tests::same('کلیدِ متایِ خالی → خالی', Video_Gallery_Field::items(5, ''), []);
Tests::same('کلیدِ متایِ فقط-فاصله → خالی', Video_Gallery_Field::items(5, '   '), []);

zig_vg_reset();
new \WC_Product(['id' => 5, 'meta' => []]);
Tests::same('متایِ ثبت‌نشده → خالی', Video_Gallery_Field::items(5, 'zig-product-video'), []);

zig_vg_reset();
new \WC_Product(['id' => 5, 'meta' => ['zig-product-video' => '']]);
Tests::same('متایِ رشتهٔ خالی → خالی', Video_Gallery_Field::items(5, 'zig-product-video'), []);

/* =========================================================================
 * شکل‌هایِ مختلفِ ذخیره‌سازی
 * ======================================================================= */

Tests::group('گالریِ ویدئو › شکل‌هایِ ورودی');

zig_vg_reset();
zig_register_attachment(12, ['title' => 'ویدئوی اول', 'url' => 'https://zig3d.test/v/12.mp4']);
new \WC_Product(['id' => 5, 'meta' => ['zig-product-video' => 12]]);
$items = Video_Gallery_Field::items(5, 'zig-product-video');
Tests::ok('شناسهٔ تکیِ عددی → یک آیتم', 1 === count($items), count($items));
Tests::same('URL از پیوست خوانده شده', $items[0]['url'] ?? null, 'https://zig3d.test/v/12.mp4');

zig_vg_reset();
zig_register_attachment(12, ['title' => 'اول', 'url' => 'https://zig3d.test/v/12.mp4']);
new \WC_Product(['id' => 5, 'meta' => ['zig-product-video' => '12']]);
Tests::ok('شناسهٔ تکیِ رشته‌ایِ عددی → یک آیتم', 1 === count(Video_Gallery_Field::items(5, 'zig-product-video')));

zig_vg_reset();
zig_register_attachment(12, ['title' => 'اول', 'url' => 'https://zig3d.test/v/12.mp4']);
zig_register_attachment(45, ['title' => 'دوم', 'url' => 'https://zig3d.test/v/45.mp4']);
new \WC_Product(['id' => 5, 'meta' => ['zig-product-video' => [12, 45]]]);
$items = Video_Gallery_Field::items(5, 'zig-product-video');
Tests::ok('آرایهٔ شناسه‌ها (عدد) → دو آیتم به همان ترتیب', 2 === count($items) && 'اول' === $items[0]['title'] && 'دوم' === $items[1]['title'], json_encode($items, JSON_UNESCAPED_UNICODE));

zig_vg_reset();
zig_register_attachment(12, ['title' => 'اول', 'url' => 'https://zig3d.test/v/12.mp4']);
zig_register_attachment(45, ['title' => 'دوم', 'url' => 'https://zig3d.test/v/45.mp4']);
new \WC_Product(['id' => 5, 'meta' => ['zig-product-video' => ['12', '45']]]);
Tests::ok('آرایهٔ شناسه‌ها (رشته) → دو آیتم', 2 === count(Video_Gallery_Field::items(5, 'zig-product-video')));

zig_vg_reset();
zig_register_attachment(12, ['title' => 'شناسه از آبجکت', 'url' => 'https://zig3d.test/v/12.mp4']);
new \WC_Product(['id' => 5, 'meta' => ['zig-product-video' => [['id' => 12, 'extra' => 'x']]]]);
$items = Video_Gallery_Field::items(5, 'zig-product-video');
Tests::ok('آرایه‌ای از آبجکت‌هایِ رسانه (کلیدِ id) → آیتم درست', 1 === count($items) && 'شناسه از آبجکت' === $items[0]['title'], json_encode($items, JSON_UNESCAPED_UNICODE));

zig_vg_reset();
new \WC_Product(['id' => 5, 'meta' => ['zig-product-video' => [['url' => 'https://external.test/vid.mp4']]]]);
$items = Video_Gallery_Field::items(5, 'zig-product-video');
Tests::ok('آبجکتِ رسانه با فقط url (بدونِ id) → آیتمِ بدونِ پیوست', 1 === count($items) && 'https://external.test/vid.mp4' === $items[0]['url'] && 0 === $items[0]['id'], json_encode($items, JSON_UNESCAPED_UNICODE));

zig_vg_reset();
new \WC_Product(['id' => 5, 'meta' => ['zig-product-video' => 'https://external.test/single.mp4']]);
$items = Video_Gallery_Field::items(5, 'zig-product-video');
Tests::ok('آدرسِ تکیِ خارجی (نه عدد) → یک آیتم', 1 === count($items) && 'https://external.test/single.mp4' === $items[0]['url'], json_encode($items, JSON_UNESCAPED_UNICODE));

zig_vg_reset();
zig_register_attachment(12, ['title' => 'اول', 'url' => 'https://zig3d.test/v/12.mp4']);
zig_register_attachment(45, ['title' => 'دوم', 'url' => 'https://zig3d.test/v/45.mp4']);
new \WC_Product(['id' => 5, 'meta' => ['zig-product-video' => '[12,45]']]);
Tests::ok('رشتهٔ JSON-مانند (آرایه) → دو آیتم', 2 === count(Video_Gallery_Field::items(5, 'zig-product-video')));

zig_vg_reset();
zig_register_attachment(12, ['title' => 'اول', 'url' => 'https://zig3d.test/v/12.mp4']);
zig_register_attachment(45, ['title' => 'دوم', 'url' => 'https://zig3d.test/v/45.mp4']);
new \WC_Product(['id' => 5, 'meta' => ['zig-product-video' => '{"0":"12","1":"45"}']]);
Tests::ok('رشتهٔ JSON-مانند (آبجکت) → دو آیتم', 2 === count(Video_Gallery_Field::items(5, 'zig-product-video')));

zig_vg_reset();
zig_register_attachment(12, ['title' => 'اول', 'url' => 'https://zig3d.test/v/12.mp4']);
zig_register_attachment(45, ['title' => 'دوم', 'url' => 'https://zig3d.test/v/45.mp4']);
new \WC_Product(['id' => 5, 'meta' => ['zig-product-video' => '12,45']]);
Tests::ok('رشتهٔ شناسه‌هایِ کاما-جدا → دو آیتم', 2 === count(Video_Gallery_Field::items(5, 'zig-product-video')));

zig_vg_reset();
zig_register_attachment(12, ['title' => 'اول', 'url' => 'https://zig3d.test/v/12.mp4']);
zig_register_attachment(45, ['title' => 'دوم', 'url' => 'https://zig3d.test/v/45.mp4']);
new \WC_Product(['id' => 5, 'meta' => ['zig-product-video' => serialize([12, 45])]]);
Tests::ok('آرایهٔ سریالایزشده → دو آیتم', 2 === count(Video_Gallery_Field::items(5, 'zig-product-video')));

/* =========================================================================
 * فیلترِ آیتم‌هایِ بی‌فایده — بدونِ خراب‌شدنِ خروجی
 * ======================================================================= */

Tests::group('گالریِ ویدئو › فیلترِ آیتم‌هایِ بی‌فایده');

zig_vg_reset();
zig_register_attachment(12, ['title' => 'معتبر', 'url' => 'https://zig3d.test/v/12.mp4']);
new \WC_Product(['id' => 5, 'meta' => ['zig-product-video' => [0, '', 12, ['no' => 'id-or-url']]]]);
$items = Video_Gallery_Field::items(5, 'zig-product-video');
Tests::ok('آیتم‌هایِ بی‌شناسه/بی‌آدرس بی‌صدا حذف می‌شوند، بقیه سالم می‌مانند', 1 === count($items) && 'معتبر' === $items[0]['title'], json_encode($items, JSON_UNESCAPED_UNICODE));

/* =========================================================================
 * اولویتِ عنوان و توضیح
 * ======================================================================= */

Tests::group('گالریِ ویدئو › عنوان و توضیح');

zig_vg_reset();
zig_register_attachment(12, ['alt' => 'آلت‌تکست', 'title' => 'عنوانِ رسانه', 'url' => 'https://zig3d.test/v/12.mp4']);
new \WC_Product(['id' => 5, 'meta' => ['zig-product-video' => 12]]);
Tests::same('عنوان: اول Alt Text', Video_Gallery_Field::items(5, 'zig-product-video')[0]['title'], 'آلت‌تکست');

zig_vg_reset();
zig_register_attachment(12, ['alt' => '', 'title' => 'عنوانِ رسانه', 'url' => 'https://zig3d.test/v/12.mp4']);
new \WC_Product(['id' => 5, 'meta' => ['zig-product-video' => 12]]);
Tests::same('عنوان: Alt خالی → Media Title', Video_Gallery_Field::items(5, 'zig-product-video')[0]['title'], 'عنوانِ رسانه');

zig_vg_reset();
zig_register_attachment(12, ['description' => 'توضیحِ کامل', 'caption' => 'کپشن', 'url' => 'https://zig3d.test/v/12.mp4']);
new \WC_Product(['id' => 5, 'meta' => ['zig-product-video' => 12]]);
Tests::same('توضیح: اول Description', Video_Gallery_Field::items(5, 'zig-product-video')[0]['description'], 'توضیحِ کامل');

zig_vg_reset();
zig_register_attachment(12, ['description' => '', 'caption' => 'کپشن', 'url' => 'https://zig3d.test/v/12.mp4']);
new \WC_Product(['id' => 5, 'meta' => ['zig-product-video' => 12]]);
Tests::same('توضیح: Description خالی → Caption', Video_Gallery_Field::items(5, 'zig-product-video')[0]['description'], 'کپشن');

zig_vg_reset();
zig_register_attachment(12, ['url' => 'https://zig3d.test/v/12.mp4']);
new \WC_Product(['id' => 5, 'meta' => ['zig-product-video' => 12]]);
$item = Video_Gallery_Field::items(5, 'zig-product-video')[0];
Tests::same('بدونِ عنوان/توضیح: رشتهٔ خالی، نه خطا', $item['title'], '');
Tests::same('بدونِ عنوان/توضیح: توضیح هم خالی', $item['description'], '');

/* =========================================================================
 * مدت‌زمان
 * ======================================================================= */

Tests::group('گالریِ ویدئو › مدت‌زمان');

zig_vg_reset();
zig_register_attachment(12, ['url' => 'https://zig3d.test/v/12.mp4', 'meta' => ['length_formatted' => '5:48']]);
new \WC_Product(['id' => 5, 'meta' => ['zig-product-video' => 12]]);
Tests::same('مدت از length_formatted خوانده می‌شود', Video_Gallery_Field::items(5, 'zig-product-video')[0]['duration'], '5:48');

zig_vg_reset();
zig_register_attachment(12, ['url' => 'https://zig3d.test/v/12.mp4', 'caption' => '8:15']);
new \WC_Product(['id' => 5, 'meta' => ['zig-product-video' => 12]]);
Tests::same('بدونِ length_formatted: Captionِ شبیهِ مدت‌زمان پذیرفته می‌شود', Video_Gallery_Field::items(5, 'zig-product-video')[0]['duration'], '8:15');

zig_vg_reset();
zig_register_attachment(12, ['url' => 'https://zig3d.test/v/12.mp4', 'caption' => 'یک توضیحِ متنیِ معمولی']);
new \WC_Product(['id' => 5, 'meta' => ['zig-product-video' => 12]]);
Tests::same('بدونِ length_formatted: Captionِ متنیِ عادی به‌عنوانِ مدت‌زمان پذیرفته نمی‌شود', Video_Gallery_Field::items(5, 'zig-product-video')[0]['duration'], '');

zig_vg_reset();
zig_register_attachment(12, ['url' => 'https://zig3d.test/v/12.mp4', 'meta' => ['length_formatted' => '1:05:30']]);
new \WC_Product(['id' => 5, 'meta' => ['zig-product-video' => 12]]);
Tests::same('فرمتِ h:mm:ss هم به‌عنوانِ مدت‌زمان پذیرفته می‌شود', Video_Gallery_Field::items(5, 'zig-product-video')[0]['duration'], '1:05:30');

/* =========================================================================
 * Poster
 * ======================================================================= */

Tests::group('گالریِ ویدئو › Poster');

zig_vg_reset();
zig_register_attachment(12, ['url' => 'https://zig3d.test/v/12.mp4', 'thumbnail_id' => 77]);
new \WC_Product(['id' => 5, 'meta' => ['zig-product-video' => 12], 'thumb' => 999]);
$item = Video_Gallery_Field::items(5, 'zig-product-video')[0];
Tests::ok('Poster: اول thumbnailِ خودِ ویدئو', 77 === $item['poster']['id'], json_encode($item['poster'], JSON_UNESCAPED_UNICODE));

zig_vg_reset();
zig_register_attachment(12, ['url' => 'https://zig3d.test/v/12.mp4']);
new \WC_Product(['id' => 5, 'meta' => ['zig-product-video' => 12], 'thumb' => 999]);
$item = Video_Gallery_Field::items(5, 'zig-product-video')[0];
Tests::ok('Poster: بدونِ thumbnailِ ویدئو → تصویرِ شاخصِ محصول', 999 === $item['poster']['id'], json_encode($item['poster'], JSON_UNESCAPED_UNICODE));

zig_vg_reset();
new \WC_Product(['id' => 5, 'meta' => ['zig-product-video' => [['url' => 'https://external.test/v.mp4']]]]);
$item = Video_Gallery_Field::items(5, 'zig-product-video')[0];
Tests::same('Poster: بدونِ پیوست و بدونِ تصویرِ شاخصِ محصول → خالی', $item['poster'], ['id' => 0, 'url' => '']);
