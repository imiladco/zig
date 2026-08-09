<?php
/**
 * سئوی حالت‌های فیلترشده.
 *
 * هیچ‌کدام از این تصمیم‌ها در صفحه دیده نمی‌شوند. یک ‎canonical‎ اشتباه،
 * صفحه را دقیقاً همان‌طور رندر می‌کند که درستش را — و ماه‌ها بعد معلوم
 * می‌شود نصف صفحه‌های عمیق از ایندکس بیرون افتاده‌اند.
 */

require_once __DIR__ . '/bootstrap.php';

$root = dirname(__DIR__);

require_once $root . '/includes/query-state.php';
require_once $root . '/includes/seo.php';

use Zig3d_Widgets\Query_State;
use Zig3d_Widgets\Seo;

if (!function_exists('add_query_arg')) {
    function add_query_arg($args, $url = '') {
        return $url . (false === strpos($url, '?') ? '?' : '&') . http_build_query($args);
    }
}

$clean    = Query_State::create();
$sorted   = Query_State::create([], 'price', 1);
$paged    = Query_State::create([], '', 3);
$single   = Query_State::create(['pa_brand' => ['up3d']]);
$two_terms = Query_State::create(['pa_brand' => ['up3d', 'vhf']]);
$two_groups = Query_State::create(['pa_brand' => ['up3d'], 'pa_axis' => ['5-axis']]);

/* ==========================================================================
 * ایندکس‌پذیری
 * ======================================================================= */

Tests::group('سئو › ایندکس');

Tests::ok('حالت بدون فیلتر همیشه ایندکس می‌شود', Seo::is_indexable($clean));

/*
 * ترتیب و صفحه انفجار ترکیبی نمی‌سازند — چند گزینهٔ ترتیب و چند صفحه. و
 * صفحهٔ سوم محتوای واقعیِ متفاوتی دارد که باید پیدا شود.
 */
Tests::ok('ترتیب، ایندکس‌پذیری را نمی‌شکند', Seo::is_indexable($sorted));
Tests::ok('صفحه هم همین‌طور', Seo::is_indexable($paged));

/*
 * با پنج گروه فیلتر و ده گزینه، تعداد آدرس‌های ممکن از تعداد محصولات
 * فروشگاه بیشتر می‌شود.
 */
Tests::ok('پیش‌فرض: هر فیلتری noindex می‌شود', !Seo::is_indexable($single));
Tests::ok('و ترکیب فیلترها هم', !Seo::is_indexable($two_groups));

Tests::ok('سیاست باز، همه را ایندکس می‌کند', Seo::is_indexable($two_groups, Seo::INDEX_ALL));

/* --------------------------------------------------------------------------
 * سیاست تک‌فیلتری
 *
 * برای وقتی «برند X» خودش عبارت جست‌وجوست. ولی فقط یک فیلتر و یک گزینه:
 * دو برندِ انتخاب‌شده هم یک گروه است، ولی محتوایش ترکیبی است و همان
 * انفجار را شروع می‌کند.
 * ----------------------------------------------------------------------- */

Tests::ok('تک‌گزینه ایندکس می‌شود', Seo::is_indexable($single, Seo::INDEX_SINGLE));
Tests::ok('دو گزینه در یک گروه، نه', !Seo::is_indexable($two_terms, Seo::INDEX_SINGLE));
Tests::ok('دو گروه هم نه', !Seo::is_indexable($two_groups, Seo::INDEX_SINGLE));

/* ==========================================================================
 * robots
 * ======================================================================= */

Tests::group('سئو › robots');

Tests::same('حالت تمیز', Seo::robots($clean), 'index, follow');
Tests::same('حالت فیلترشده', Seo::robots($single), 'noindex, follow');

/*
 * ‎follow‎ روی ‎noindex‎ هم می‌ماند. ‎nofollow‎ اینجا یعنی محصولاتی که فقط
 * از مسیر فیلتر پیدا می‌شوند، هیچ‌وقت کشف نشوند — که کاملاً غیر از چیزی
 * است که می‌خواستیم.
 */
Tests::keeps('لینک‌های داخل صفحه همچنان دنبال می‌شوند', Seo::robots($two_groups), 'follow');
Tests::blocks('و nofollow نمی‌شود', Seo::robots($two_groups), 'nofollow');

/* ==========================================================================
 * canonical
 * ======================================================================= */

Tests::group('سئو › canonical');

$base = 'https://zig3d.com/shop/';

Tests::same('حالت تمیز، به خودش', Seo::canonical($base, $clean), $base);

Tests::same(
    'حالت فیلترشده، به نسخهٔ بدون فیلتر',
    Seo::canonical($base, $single),
    $base
);

/*
 * صفحه در کانونیکال می‌ماند حتی وقتی فیلتر افتاده. کانونیکال‌کردن صفحهٔ
 * سوم به صفحهٔ اول یعنی گفتن «این تکراری است» دربارهٔ محصولاتی که جای
 * دیگری نیستند — خطای رایجی که کل صفحات عمیق را از ایندکس بیرون
 * می‌اندازد.
 */
$deep = Query_State::create(['pa_brand' => ['up3d']], '', 3);

Tests::keeps('صفحه در کانونیکال می‌ماند', Seo::canonical($base, $deep), 'paged=3');
Tests::blocks('ولی فیلتر می‌افتد', Seo::canonical($base, $deep), 'filter_brand');

Tests::keeps(
    'حالتِ ایندکس‌پذیر، کانونیکالش خودش است',
    Seo::canonical($base, $single, [], Seo::INDEX_SINGLE),
    'filter_brand=up3d'
);

/* ==========================================================================
 * نتیجهٔ خالی
 * ======================================================================= */

Tests::group('سئو › نتیجهٔ خالی');

/*
 * توصیهٔ صریح گوگل برای ناوبری وجهی: «Return an HTTP 404 status code when
 * a filter combination doesn't return results.» ترکیب فیلترها بی‌نهایت
 * آدرس می‌سازد و بیشترشان خالی‌اند؛ اگر همه ۲۰۰ بدهند، خزنده باید همه را
 * ببیند تا بفهمد چیزی ندارند.
 */
Tests::ok('فیلترِ بی‌نتیجه ۴۰۴ می‌گیرد', Seo::is_not_found($two_groups, 0));

/*
 * ولی «فیلترشده» شرط لازم است، نه فقط «خالی». دسته‌ای که هنوز محصولی
 * ندارد یک آدرس کاملاً معتبر است — مدیر همین امروز ساخته و فردا پرش
 * می‌کند. ۴۰۴ دادن به آن یعنی بیرون‌انداختن صفحه‌ای که خودمان ساخته‌ایم.
 */
Tests::ok('دستهٔ خالیِ بدون فیلتر، ۴۰۴ نمی‌گیرد', !Seo::is_not_found($clean, 0));

Tests::ok('و فیلترِ نتیجه‌دار هم نه', !Seo::is_not_found($two_groups, 5));

/* ==========================================================================
 * تگ‌های head
 * ======================================================================= */

Tests::group('سئو › head');

$head = Seo::head($base, $clean, [], Seo::INDEX_CLEAN, 12);

Tests::same('حالت تمیز، ایندکس‌پذیر', $head['robots'], 'index, follow');
Tests::same('و کانونیکالش خودش', $head['canonical'], $base);
Tests::ok('و ۴۰۴ نیست', !$head['not_found']);

$empty = Seo::head($base, $two_groups, [], Seo::INDEX_ALL, 0);

/*
 * حالتِ ۴۰۴ همیشه noindex می‌گیرد، حتی وقتی سیاست بازتر است. وضعیت ۴۰۴
 * به‌تنهایی کافی است، ولی صفحه‌ای که هنوز محتوا رندر می‌کند ممکن است جایی
 * «نرم» خوانده شود.
 */
Tests::ok('نتیجهٔ خالی، ۴۰۴ اعلام می‌شود', $empty['not_found']);
Tests::same('و noindex می‌گیرد حتی با سیاست باز', $empty['robots'], 'noindex, follow');

Tests::ok(
    'بدون دانستن تعداد، ادعای ۴۰۴ نمی‌شود',
    !Seo::head($base, $two_groups)['not_found']
);

/* ==========================================================================
 * لینک‌های واقعی
 * ======================================================================= */

Tests::group('سئو › لینک');

/*
 * هر چیزی که کلیک‌شدنی است باید یک href واقعی باشد، حتی وقتی JS رویش سوار
 * می‌شود: بدون آن صفحه بدون JS کار نمی‌کند، و خزنده‌های هوش مصنوعی که JS
 * اجرا نمی‌کنند هیچ راهی به صفحهٔ دوم ندارند.
 */
Tests::same('وضعیت خالی، همان آدرس پایه', Seo::url($base, $clean), $base);

$link = Seo::url($base, $two_terms, ['pa_brand' => 'or']);

Tests::keeps('فیلتر در آدرس می‌آید', $link, 'filter_brand');
Tests::keeps('و اپراتور هم، چون دو ترم است', $link, 'query_type_brand');

/* ==========================================================================
 * دادهٔ ساختاریافته
 * ======================================================================= */

Tests::group('سئو › ItemList');

$list = Seo::item_list([
    ['url' => 'https://zig3d.com/p/a', 'name' => 'الف'],
    ['url' => 'https://zig3d.com/p/b', 'name' => 'ب'],
]);

Tests::same('نوع درست', $list['@type'], 'ItemList');
Tests::same('تعداد آیتم‌ها', count($list['itemListElement']), 2);
Tests::same('شمارش از یک شروع می‌شود', $list['itemListElement'][0]['position'], 1);

/*
 * محصول اول صفحهٔ سوم، دوازدهمین محصول فهرست است نه اولی. اگر همه‌جا از
 * یک شروع شود، موتور جستجو چند فهرستِ متفاوت می‌بیند که همه ادعا دارند
 * از اول شروع شده‌اند.
 */
Tests::same(
    'ولی در صفحهٔ سوم از جای درست',
    Seo::item_list([['url' => 'https://zig3d.com/p/x']], 3, 6)['itemListElement'][0]['position'],
    13
);

Tests::same(
    'آیتم بدون آدرس کنار می‌رود',
    count(Seo::item_list([['name' => 'بی‌لینک']])['itemListElement']),
    0
);

Tests::ok(
    'و آیتم بدون نام، فقط آدرس می‌دهد',
    !isset(Seo::item_list([['url' => 'https://zig3d.com/p/x']])['itemListElement'][0]['name'])
);
