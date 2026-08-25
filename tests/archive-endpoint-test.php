<?php
/**
 * رفعِ باگِ «کلیکِ صفحه‌بندی در آرشیوِ دانلود نوارِ آدرس را به ریشهٔ سایت
 * می‌بَرد» (‎site.com/?paged=2‎ به‌جایِ لینکِ واقعیِ همان صفحه).
 *
 * ریشهٔ باگ: ‎Download_Archive::document_id()‎ اول سراغِ
 * ‎\Elementor\Plugin::$instance->documents->get_current()‎ می‌رود.
 * ‎Archive_Endpoint::widget()‎ عنصرِ ویجت را مستقیم از دادهٔ خامِ سند
 * می‌سازد (‎create_element_instance()‎)، نه از مسیرِ رندرِ عادیِ سند —
 * پس ‎get_current()‎ روی یک درخواستِ آژاکسِ تازه همیشه ‎null‎ می‌ماند و
 * ‎document_id()‎ به ‎get_the_ID()‎ سقوط می‌کند، که در ‎admin-ajax.php‎
 * همیشه ‎۰‎ است. ‎get_permalink(0)‎ی وردپرس هم ‎false‎ می‌دهد، پس
 * ‎base_url()‎ به ‎home_url('/')‎ سقوط می‌کند — همان‌جا که کاربر لینکِ
 * صفحه‌بندی را «کلِ آدرس حذف‌شده، فقط ریشهٔ سایت» می‌بیند.
 *
 * تعمیر: ‎Archive_Endpoint::widget()‎ حالا با ‎switch_to_document()‎ی
 * خودِ المنتور، سندِ درستی که از ‎post_id‎ی درخواست پیدا کرده را «سندِ
 * جاری» می‌کند — این تست همان مکانیزم را مستقل از کلِ زنجیرهٔ آژاکس
 * می‌سنجد.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/elementor-stub.php';
require_once __DIR__ . '/lib/woocommerce-stub.php';
require_once __DIR__ . '/lib/elementor-frontend-stub.php';

$root = dirname(__DIR__);
require_once $root . '/includes/markup.php';
require_once $root . '/includes/price.php';
require_once $root . '/includes/download-archive-data.php';
require_once $root . '/includes/widgets/download-archive.php';

use Zig3d_Widgets\Widgets\Download_Archive;

Tests::group('نقطهٔ آژاکسِ آرشیو › مکانیزمِ document_id() (باگِ نوارِ آدرس)');

$widget = zig_widget(Download_Archive::class);

$document_id = new ReflectionMethod($widget, 'document_id');
$document_id->setAccessible(true);
$base_url = new ReflectionMethod($widget, 'base_url');
$base_url->setAccessible(true);

/*
 * وضعیتِ باگ‌دار: هیچ سندی سوییچ نشده — دقیقاً همان چیزی که پیش از این
 * تعمیر، رویِ هر درخواستِ آژاکسِ تازه اتفاق می‌افتاد.
 */
$GLOBALS['__zig_post'] = 0;

Tests::same(
    'بدونِ سندِ سوییچ‌شده، document_id() به get_the_ID() سقوط می‌کند (۰ در admin-ajax.php)',
    0,
    $document_id->invoke($widget)
);

/*
 * استابِ get_permalink() در این سوییت همیشه رشته می‌دهد (حتی برایِ
 * شناسهٔ ۰)، نه false مثلِ وردپرسِ واقعی — پس اینجا نمی‌شود خودِ
 * سقوطِ نهاییِ base_url() به home_url() را دقیقاً شبیه‌سازی کرد. چیزی
 * که این‌جا سنجیده می‌شود این است: base_url() همان شناسه‌ای را که
 * document_id() می‌دهد به get_permalink() پاس می‌دهد — یعنی وقتی آن
 * شناسه (به‌خاطرِ نبودِ سندِ سوییچ‌شده) اشتباه است، لینکِ نهایی هم
 * روی همان شناسهٔ اشتباه ساخته می‌شود، نه لینکِ واقعیِ صفحه.
 */
Tests::same(
    'base_url() همان شناسهٔ اشتباهِ document_id() را به get_permalink() می‌دهد',
    'https://zig3d.test/?p=0',
    $base_url->invoke($widget)
);

/*
 * تعمیر: همان کاری که Archive_Endpoint::widget() حالا انجام می‌دهد.
 */
$document = new \Elementor\Zig_Stub_Document(4321);
\Elementor\Plugin::$instance->documents->switch_to_document($document);

Tests::same(
    'با سندِ سوییچ‌شده، document_id() از get_current() می‌آید نه get_the_ID()',
    4321,
    $document_id->invoke($widget)
);
Tests::same(
    'base_url() هم لینکِ واقعیِ همان سند را می‌سازد، نه ریشهٔ سایت',
    'https://zig3d.test/?p=4321',
    $base_url->invoke($widget)
);

\Elementor\Plugin::$instance->documents->restore_document();

Tests::same(
    'بعدِ restore_document()، دوباره به get_the_ID() برمی‌گردد (پشته درست تخلیه می‌شود)',
    0,
    $document_id->invoke($widget)
);

Tests::group('نقطهٔ آژاکسِ آرشیو › اتصالِ واقعیِ سوییچ در endpoint');

$endpoint_source = file_get_contents($root . '/includes/archive-endpoint.php');

Tests::keeps(
    'widget() قبلِ ساختِ عنصر، سند را با switch_to_document سندِ جاری می‌کند',
    $endpoint_source,
    "\\Elementor\\Plugin::\$instance->documents->switch_to_document(\$document);"
);
Tests::keeps(
    'respond() در پایان restore_document را صدا می‌زند',
    $endpoint_source,
    "\\Elementor\\Plugin::\$instance->documents->restore_document();"
);
Tests::ok(
    'ترتیبِ درست: سوییچ پیش از ساختنِ عنصر (create_element_instance) می‌آید',
    strpos($endpoint_source, 'switch_to_document($document)') < strpos($endpoint_source, 'create_element_instance($data)')
);
Tests::ok(
    'هر دو صدازدن پشتِ method_exists محافظت شده‌اند — نسخه‌هایِ قدیمی‌ترِ المنتور هم فاتال نمی‌گیرند',
    2 === substr_count($endpoint_source, "method_exists(\\Elementor\\Plugin::\$instance->documents, '")
);
