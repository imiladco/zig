<?php
/**
 * لایکِ پست — شمارش، وضعیتِ «آیا این توکن لایک کرده»، و سوییچ.
 *
 * توکنِ خام هیچ‌وقت مستقیم مقایسه نمی‌شود؛ همیشه هش‌شده در متا می‌نشیند،
 * پس یک سنجه هم دقیقاً همین را می‌سنجد: مقدارِ ذخیره‌شده هرگز خودِ
 * توکنِ خام نیست.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/woocommerce-stub.php';

$root = dirname(__DIR__);

require_once $root . '/includes/likes.php';

use Zig3d_Widgets\Likes;

Tests::group('لایک › شمارش و وضعیت');

$GLOBALS['__zig_post_meta'] = [];

Tests::same('پستِ بی‌لایک صفر است', Likes::count(101), 0);
Tests::same('بدونِ توکن، لایک‌نکرده است', Likes::has_liked(101, ''), false);
Tests::same('شناسهٔ نامعتبر صفر می‌دهد', Likes::count(0), 0);

Tests::group('لایک › سوییچ');

$first = Likes::toggle(202, 'visitor-a');

Tests::same('اولین سوییچ لایک می‌کند', $first['liked'], true);
Tests::same('شمارش یک شد', $first['count'], 1);
Tests::ok(
    'مقدارِ ذخیره‌شده خودِ توکنِ خام نیست',
    !in_array('visitor-a', $GLOBALS['__zig_post_meta'][202]['_zig_like_tokens'], true)
);

$second_visitor = Likes::toggle(202, 'visitor-b');

Tests::same('توکنِ دومی هم لایک می‌کند', $second_visitor['liked'], true);
Tests::same('شمارش دو شد', $second_visitor['count'], 2);

Tests::same('توکنِ اول از دیدِ has_liked لایک‌کرده است', Likes::has_liked(202, 'visitor-a'), true);
Tests::same('توکنِ سوم لایک‌نکرده است', Likes::has_liked(202, 'visitor-c'), false);

$undo = Likes::toggle(202, 'visitor-a');

Tests::same('سوییچِ دوباره برمی‌دارد', $undo['liked'], false);
Tests::same('شمارش به یک برگشت', $undo['count'], 1);
Tests::same('دیگر لایک‌کرده نیست', Likes::has_liked(202, 'visitor-a'), false);
Tests::same('توکنِ دوم دست‌نخورده ماند', Likes::has_liked(202, 'visitor-b'), true);

/* پستِ دیگر، از پستِ ۲۰۲ کاملاً مستقل است */
Tests::same('پستِ دیگر همچنان صفر است', Likes::count(303), 0);

/* شناسه/توکنِ نامعتبر برایِ toggle هم صدا نمی‌زند */
$invalid = Likes::toggle(0, 'visitor-a');
Tests::same('شناسهٔ نامعتبر تغییری نمی‌دهد', $invalid['liked'], false);

$invalid_token = Likes::toggle(202, '');
Tests::same('توکنِ خالی تغییری نمی‌دهد', $invalid_token['liked'], false);
