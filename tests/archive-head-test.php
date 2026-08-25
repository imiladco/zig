<?php
require_once __DIR__ . '/bootstrap.php';

$root = dirname(__DIR__);

if (!function_exists('is_admin')) {
    function is_admin() { return false; }
}
if (!function_exists('wp_doing_ajax')) {
    function wp_doing_ajax() { return false; }
}
if (!function_exists('is_main_query')) {
    function is_main_query() { return true; }
}
if (!function_exists('is_singular')) {
    function is_singular($post_types = '') { return (bool) ($GLOBALS['__zig_is_singular'] ?? false); }
}
if (!function_exists('is_shop')) {
    function is_shop() { return (bool) ($GLOBALS['__zig_is_shop'] ?? false); }
}
if (!function_exists('is_product_taxonomy')) {
    function is_product_taxonomy() { return (bool) ($GLOBALS['__zig_is_product_taxonomy'] ?? false); }
}
if (!function_exists('status_header')) {
    function status_header($code) { $GLOBALS['__zig_status_headers'][] = (int) $code; }
}

if (!class_exists('WP_Query')) {
    class WP_Query {
        public bool $singular = false;
        public array $writes = [];

        public function is_main_query(): bool { return true; }
        public function is_singular(): bool { return $this->singular; }
        public function get($key) { return null; }
        public function set($key, $value): void { $this->writes[$key] = $value; }
    }
}

require_once $root . '/includes/query-state.php';
require_once $root . '/includes/archive-head.php';

use Zig3d_Widgets\Archive_Head;
use Zig3d_Widgets\Query_State;

Tests::group('Archive Head routing boundaries');

$_GET = ['filter_brand' => str_repeat('x,', Query_State::MAX_TERMS + 5)];
$single_vars = ['post_type' => 'downloads', 'name' => 'upcam-3-0', 'paged' => Query_State::MAX_PAGE + 10];
$guarded = Archive_Head::guard($single_vars);

Tests::same('Named CPT request vars are preserved by the request guard', $guarded, $single_vars);
Tests::same('Named CPT request query parameters are not archive-capped', $_GET['filter_brand'], str_repeat('x,', Query_State::MAX_TERMS + 5));

$query = new WP_Query();
$query->singular = true;
$GLOBALS['__zig_is_shop'] = true;
Archive_Head::filter_main_query($query);
Tests::same('A singular main query receives no archive query writes', $query->writes, []);

$GLOBALS['__zig_is_singular'] = true;
$GLOBALS['__zig_status_headers'] = [];
Archive_Head::decide();
Tests::same('A singular request receives no archive status header', $GLOBALS['__zig_status_headers'], []);
Tests::same('A singular request receives no archive SEO decision', Archive_Head::page_state(), null);

$applies = new ReflectionMethod(Archive_Head::class, 'applies');
$applies->setAccessible(true);
Tests::ok('Archive applicability rejects singular requests even when Woo reports shop context', false === $applies->invoke(null));

$GLOBALS['__zig_is_singular'] = false;
Tests::ok('The genuine Woo archive applicability path remains enabled', true === $applies->invoke(null));

$source = file_get_contents($root . '/includes/archive-head.php');
Tests::ok('No hardcoded Downloads slug or Woo redirect workaround was introduced', false === strpos($source, "is_singular('downloads')") && false === strpos($source, 'wc_template_redirect'));

/*
 * باگِ /shop: قبلاً facets() روی هر صفحه‌ای که queried_object یک WP_Term
 * نبود — یعنی خودِ فروشگاه — بی‌قید‌و‌شرط [] می‌داد. سایدبار ویجت هم با
 * دقیقاً همین شرط ([] === facets) هایید می‌شد، پس /shop همیشه بدونِ هیچ
 * فیلتری رندر می‌شد، حتی وقتی محصولات ویژگیِ فیلترپذیر داشتند.
 *
 * اینجا با ریفلکشن فراخوانی نمی‌شود چون facets() به Schema_Store (و از
 * آن‌جا Archive_Query/Attributes) نیاز دارد که این فایلِ تست عمداً کوچک
 * نگه داشته نمی‌کند؛ به‌جایش سیم‌کشیِ خودِ کد سنجیده می‌شود — همان تکنیکِ
 * دو خط بالاتر. مطابقتش با متدِ هم‌نامِ ویجت (product-archive.php) در
 * تستِ خودِ آن ویجت است.
 */
Tests::keeps(
    'facets() روی is_shop() به‌جایِ [] به Schema_Store::for_term(0) می‌رود',
    $source,
    "if (function_exists('is_shop') && is_shop()) {\n            return Schema_Store::for_term(0);"
);
