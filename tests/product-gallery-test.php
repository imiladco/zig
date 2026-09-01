<?php
/**
 * ویجتِ «گالری محصول» — فلش‌هایِ ناوبریِ تصویرِ شاخص.
 *
 * فقط شاخهٔ جدید سنجیده می‌شود: تگِ ‎.zig-gallery__main‎ (که نباید هیچ‌وقت
 * ‎<button>‎یِ توش ‎<button>‎ باشد)، حضورِ ‎data-large‎ روی شاخص و همهٔ
 * تامبنیل‌ها (حتی ‎--extra‎های پنهان، چون فلش باید بتواند تا آخرِ گالری
 * برود، نه فقط تا آخرِ تامبنیل‌هایِ دیده‌شدنی)، و رندرشدنِ خودِ دو دکمه.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/elementor-stub.php';
require_once __DIR__ . '/lib/woocommerce-stub.php';

$root = dirname(__DIR__);

require_once $root . '/includes/price.php';
require_once $root . '/includes/widgets/product-gallery.php';

use Zig3d_Widgets\Widgets\Product_Gallery;

zig_reset_products();

/* ==========================================================================
 * تگِ تصویرِ شاخص — بدونِ تودرتوییِ button
 * ======================================================================= */

Tests::group('گالری محصول › تگِ تصویرِ شاخص');

$product = new WC_Product(['id' => 501, 'image' => 1, 'gallery' => [2, 3, 4, 5]]);
$GLOBALS['product'] = $product;

/*
 * پیش‌فرض: مودال روشن، فلش روشن → باید div باشد (نه button)، وگرنه
 * دکمه‌هایِ فلش تویِ آن، تودرتوییِ button-in-button و HTML نامعتبر می‌سازند.
 */
$out = zig_render(Product_Gallery::class, ['product_id' => 501, 'modal_enable' => 'yes', 'show_tab' => '']);

Tests::ok('با مودال+فلشِ هر دو روشن، شاخص div است', false !== strpos($out, '<div class="zig-gallery__main"'));
Tests::ok('نه button', false === strpos($out, '<button type="button" class="zig-gallery__main"'));
Tests::ok('نقشِ button برایِ دسترس‌پذیری هست', false !== strpos($out, 'role="button"'));
Tests::ok('data-index هنوز هست تا کلیک، مودال را باز کند', false !== strpos($out, 'data-index="0"'));

/* وقتی فلش خاموش است، همان رفتارِ قدیمی: شاخص خودش button است */
$out_no_nav = zig_render(Product_Gallery::class, ['product_id' => 501, 'modal_enable' => 'yes', 'show_tab' => '', 'show_main_nav' => '']);

Tests::ok('با فلشِ خاموش، شاخص دوباره خودش button است', false !== strpos($out_no_nav, '<button class="zig-gallery__main" type="button"'));
Tests::ok('و دیگر role="button" ندارد', false === strpos($out_no_nav, 'role="button"'));

/* وقتی مودال خاموش است، شاخص همیشه div بوده (بدون فلش هم همین‌طور) */
$out_no_modal = zig_render(Product_Gallery::class, ['product_id' => 501, 'modal_enable' => '']);

Tests::ok('با مودالِ خاموش، شاخص div است', false !== strpos($out_no_modal, '<div class="zig-gallery__main"'));
Tests::ok('و data-index ندارد (چیزی برای بازکردن نیست)', false === strpos($out_no_modal, 'data-index'));

/* ==========================================================================
 * data-large — روی شاخص و همهٔ تامبنیل‌ها، حتی extraهایِ پنهان
 * ======================================================================= */

Tests::group('گالری محصول › data-large برای ناوبریِ این‌لاین');

/* ‎thumbs_count‎ پیش‌فرض ۴ است؛ اینجا ۲ می‌گذاریم تا واریانتِ extra هم بسازد */
$out_extra = zig_render(Product_Gallery::class, ['product_id' => 501, 'modal_enable' => 'yes', 'show_tab' => '', 'thumbs_count' => 2, 'show_more_count' => '', 'show_dots' => '']);

Tests::ok('شاخص data-large دارد', false !== strpos($out_extra, 'zig-gallery__main" role="button" tabindex="0" data-index="0"') && false !== strpos($out_extra, 'data-large="https://zig3d.test/full/1-large.jpg"'));
Tests::ok('تامبنیلِ دیده‌شدنی data-large دارد', false !== strpos($out_extra, 'data-large="https://zig3d.test/full/2-large.jpg"'));
Tests::ok('تامبنیلِ extra (پنهان در دسکتاپ) هم data-large دارد — فلش باید تا آخرِ گالری برود', false !== strpos($out_extra, 'zig-gallery__thumb--extra') && false !== strpos($out_extra, 'data-large="https://zig3d.test/full/4-large.jpg"'));

/* با فلشِ خاموش، هیچ‌کدام data-large نمی‌گیرند — دادهٔ بی‌مصرف در HTML نمی‌ماند */
$out_extra_no_nav = zig_render(Product_Gallery::class, ['product_id' => 501, 'modal_enable' => 'yes', 'show_tab' => '', 'thumbs_count' => 2, 'show_main_nav' => '', 'show_more_count' => '', 'show_dots' => '']);

Tests::ok('بدونِ فلش، data-large اصلاً چاپ نمی‌شود', false === strpos($out_extra_no_nav, 'data-large'));

/* ==========================================================================
 * دکمه‌هایِ فلش — فقط وقتی بیش از یک تصویر است
 * ======================================================================= */

Tests::group('گالری محصول › دکمه‌های فلش');

Tests::ok('با چند تصویر، هر دو دکمهٔ فلش رندر می‌شوند', false !== strpos($out, 'zig-gallery__nav--prev') && false !== strpos($out, 'zig-gallery__nav--next'));

$single = new WC_Product(['id' => 502, 'image' => 10, 'gallery' => []]);
$GLOBALS['product'] = $single;

$out_single = zig_render(Product_Gallery::class, ['product_id' => 502, 'modal_enable' => 'yes', 'show_tab' => '']);

Tests::ok('با یک تصویرِ تنها، فلشی برایِ ناوبری معنا ندارد و رندر نمی‌شود', false === strpos($out_single, 'zig-gallery__nav'));
