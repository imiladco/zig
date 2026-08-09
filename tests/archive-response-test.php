<?php
/**
 * قرارداد پاسخ آژاکس.
 *
 * چیزی که این تست‌ها نگه می‌دارند یک تصمیم است: «نتیجه‌ای پیدا نشد» یک
 * خطای فنی نیست و نباید با تایم‌اوت و ‎500‎ و نانسِ منقضی یک مسیر رابط
 * کاربری داشته باشد. اگر روزی این خط شکسته شود، هیچ چیزی خطا نمی‌دهد —
 * فقط کاربری که یک برند نایاب انتخاب کرده، آلرت قرمزِ «تلاش مجدد»
 * می‌گیرد و هرچه بزندش همان برمی‌گردد.
 */

require_once __DIR__ . '/bootstrap.php';

$root = dirname(__DIR__);

require_once $root . '/includes/query-state.php';
require_once $root . '/includes/seo.php';
require_once $root . '/includes/archive-response.php';

use Zig3d_Widgets\Archive_Response;
use Zig3d_Widgets\Seo;

/* ==========================================================================
 * پاکت
 * ======================================================================= */

Tests::group('پاسخ › پاکت');

$meta = ['page' => 3, 'pages' => 7, 'found' => 42, 'url' => 'https://x.test/c/?paged=3'];

$envelope = Archive_Response::envelope(Seo::STATE_OK, $meta, [
    'grid'       => '<li>a</li>',
    'pagination' => '<nav></nav>',
]);

Tests::same('وضعیت حمل می‌شود', $envelope['state'], Seo::STATE_OK);
Tests::same('صفحه', $envelope['page'], 3);
Tests::same('تعداد صفحه‌ها', $envelope['pages'], 7);
Tests::same('شمارش', $envelope['found'], 42);
Tests::same('آدرس از سرور می‌آید', $envelope['url'], 'https://x.test/c/?paged=3');
Tests::same('قطعهٔ گرید', $envelope['grid'], '<li>a</li>');

/*
 * کلیدِ نبوده یعنی «دست نزن». یک کلیک صفحه‌بندی سایدبار را عوض نمی‌کند و
 * فرستادنش فقط یک جایگزینی بی‌دلیل DOM است — که فوکوس را هم می‌پراند.
 */
Tests::ok('قطعهٔ نفرستاده در پاکت نیست', !array_key_exists('facets', $envelope));
Tests::ok('و قطعهٔ فرستاده هست', array_key_exists('pagination', $envelope));

Tests::same('صفحهٔ صفر معنا ندارد', Archive_Response::envelope(Seo::STATE_OK, ['page' => 0])['page'], 1);
Tests::same('شمارش منفی هم', Archive_Response::envelope(Seo::STATE_OK, ['found' => -5])['found'], 0);

/* ==========================================================================
 * وضعیت
 * ======================================================================= */

Tests::group('پاسخ › وضعیت');

foreach (Archive_Response::STATES as $state) {
    Tests::same('وضعیت شناخته‌شده دست‌نخورده می‌ماند: ' . $state, Archive_Response::state($state), $state);
}

/*
 * کلاینتِ قدیمی وضعیتِ تازه را نمی‌شناسد و روی یک ‎switch‎ بی‌تطبیق هیچ
 * حالتی را رندر نمی‌کند — کاربر یک ناحیهٔ خالی می‌بیند بدون هیچ خطایی.
 */
Tests::same('وضعیت ناشناخته به ok برمی‌گردد', Archive_Response::state('whatever'), Seo::STATE_OK);
Tests::same('و در پاکت هم همین‌طور', Archive_Response::envelope('whatever', [])['state'], Seo::STATE_OK);

/*
 * فهرست وضعیت‌ها باید دقیقاً همان ماشین وضعیت سئو باشد. اگر آنجا حالتی
 * اضافه شود و اینجا نه، پاکت آن را بی‌صدا به ‎ok‎ تبدیل می‌کند و صفحه‌ای
 * که ۴۰۴ گرفته، در DOM می‌گوید همه‌چیز مرتب است.
 */
Tests::same('هر پنج حالت پوشش داده شده‌اند', count(Archive_Response::STATES), 5);
Tests::ok('و invalid یکی از آن‌هاست', in_array(Seo::STATE_INVALID, Archive_Response::STATES, true));

/* ==========================================================================
 * خالی، نه خطا
 * ======================================================================= */

Tests::group('پاسخ › خالی در برابر خطا');

Tests::ok('نتیجهٔ خالیِ فیلترشده، حالت خالی است', Archive_Response::shows_empty(Seo::STATE_FILTERED_EMPTY));
Tests::ok('دستهٔ خالی هم', Archive_Response::shows_empty(Seo::STATE_EMPTY));
Tests::ok('صفحهٔ ناموجود هم — چیزی برای نشان‌دادن ندارد', Archive_Response::shows_empty(Seo::STATE_PAGE_MISSING));

Tests::ok('حالت عادی نه', !Archive_Response::shows_empty(Seo::STATE_OK));

/*
 * ‎?filter_ghost=x‎ معمولاً یک لینک کهنه است. ووکامرس پارامتر ناشناخته را
 * اعمال نمی‌کند، پس دسته محتوایش را دارد: صفحه ۴۰۴ و noindex می‌گیرد —
 * تصمیمی که مالِ خزنده است — ولی کاربر به‌جای صفحهٔ خالی، همان دسته را
 * می‌بیند.
 */
Tests::ok('و آدرسِ بی‌معنا هم گرید دارد', !Archive_Response::shows_empty(Seo::STATE_INVALID));

/* ==========================================================================
 * خطای فنی
 * ======================================================================= */

Tests::group('پاسخ › خطای فنی');

/*
 * پیش‌فرض ‎wp_send_json_error()‎ هم ۲۰۰ است. شکستِ نانسی که با ۲۰۰
 * برگردد، کلاینت را وادار می‌کند دنبال ‎state‎ بگردد که وجود ندارد — و
 * همان دو مسیری که این کلاس جدایشان کرده، دوباره یکی می‌شوند.
 */
Tests::same('نانس ۴۰۳ می‌گیرد', Archive_Response::failure_status(Archive_Response::FAIL_NONCE), 403);
Tests::same('درخواست بدشکل ۴۰۰', Archive_Response::failure_status(Archive_Response::FAIL_REQUEST), 400);
Tests::same('خطای سرور ۵۰۰', Archive_Response::failure_status(Archive_Response::FAIL_SERVER), 500);

Tests::same('کد ناشناخته هم ۵۰۰ می‌شود، نه ۲۰۰', Archive_Response::failure_status('who-knows'), 500);

/*
 * هیچ خطای فنی‌ای نباید ۲xx بگیرد: تنها تمایزی که کلاینت دارد همین است.
 */
foreach (Archive_Response::FAILURES as $code => $status) {
    Tests::ok('خطای «' . $code . '» هیچ‌وقت موفق شمرده نمی‌شود', $status >= 400);
}
