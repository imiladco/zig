<?php
/**
 * وضعیت آرشیو.
 *
 * چیزی که اینجا سنجیده می‌شود بیشتر از «آیا مقدار درست ذخیره شد» است:
 * پایداریِ امضا. کل کش شمارش فست روی این بنا شده که دو مسیر متفاوتِ کلیک
 * که به یک نتیجه می‌رسند، دقیقاً یک کلید بسازند. اگر این نباشد، کش نه غلط
 * می‌شود و نه خطا می‌دهد — فقط بی‌سروصدا بی‌اثر می‌شود و کسی نمی‌فهمد.
 */

require_once __DIR__ . '/bootstrap.php';

$root = dirname(__DIR__);

require_once $root . '/includes/query-state.php';

use Zig3d_Widgets\Query_State;

/* ==========================================================================
 * نرمال‌سازی
 * ======================================================================= */

Tests::group('وضعیت › نرمال‌سازی');

$a = Query_State::create(['pa_brand' => ['vhf', 'up3d'], 'pa_axis' => ['5-axis']]);
$b = Query_State::create(['pa_axis' => ['5-axis'], 'pa_brand' => ['up3d', 'vhf']]);

Tests::same('ترتیب کلیک روی امضا اثر ندارد', $a->signature(), $b->signature());
Tests::same('و روی اثر انگشت هم', $a->fingerprint(), $b->fingerprint());

Tests::same(
    'ترم تکراری یک بار می‌ماند',
    Query_State::create(['pa_brand' => ['up3d', 'up3d', 'vhf']])->selected('pa_brand'),
    ['up3d', 'vhf']
);

Tests::same(
    'گروه بدون ترم معتبر کلاً حذف می‌شود',
    Query_State::create(['pa_brand' => ['', '  ']])->filters(),
    []
);

Tests::same(
    'رشتهٔ کاماخورده هم پذیرفته می‌شود',
    Query_State::create(['pa_brand' => 'up3d,vhf'])->selected('pa_brand'),
    ['up3d', 'vhf']
);

/*
 * اسلاگ فارسی در وردپرس کاملاً معتبر است. پاک‌کردن حروف غیرلاتین یعنی آن
 * فیلتر هیچ‌وقت چیزی پیدا نمی‌کند — و چون خطایی نمی‌دهد، شبیه «محصولی با
 * این ویژگی نداریم» به نظر می‌رسد.
 */
Tests::same(
    'اسلاگ فارسی دست‌نخورده می‌ماند',
    Query_State::create(['pa_axis' => ['۵-محور']])->selected('pa_axis'),
    ['۵-محور']
);

Tests::same(
    'کاراکترهای ساختاریِ آدرس از اسلاگ پاک می‌شوند',
    Query_State::create(['pa_brand' => ['up3d&foo=bar']])->selected('pa_brand'),
    ['up3dfoobar']
);

/* ==========================================================================
 * خواندن از آدرس
 * ======================================================================= */

Tests::group('وضعیت › آدرس');

$state = Query_State::from_request(
    [
        'filter_brand'   => 'up3d,vhf',
        'filter_axis'    => '5-axis',
        'filter_secret'  => 'anything',
        'orderby'        => 'price',
        'paged'          => '3',
    ],
    ['pa_brand', 'pa_axis'],
    ['price', 'date']
);

Tests::same('تاکسونومی مجاز خوانده می‌شود', $state->selected('pa_brand'), ['up3d', 'vhf']);

/*
 * بدون فهرست سفید، هر ‎?filter_x=y‎ به یک tax_query تبدیل می‌شد و کافی بود
 * کسی نام یک تاکسونومی سنگین را حدس بزند تا کوئری دلخواه بسازد.
 */
Tests::same('تاکسونومیِ خارج از فهرست سفید نادیده گرفته می‌شود', $state->selected('pa_secret'), []);

Tests::same('ترتیب خوانده می‌شود', $state->sort(), 'price');
Tests::same('صفحه خوانده می‌شود', $state->page(), 3);

Tests::same(
    'ترتیب ناشناخته به پیش‌فرض برمی‌گردد',
    Query_State::from_request(['orderby' => 'nonsense'], [], ['price'])->sort(),
    ''
);

Tests::same('صفحهٔ صفر یا منفی به یک برمی‌گردد', Query_State::create([], '', -4)->page(), 1);

Tests::same('pa_axis_count ⇒ filter_axis_count', Query_State::param_for('pa_axis_count'), 'filter_axis_count');
Tests::same('و برعکس', Query_State::taxonomy_for('filter_axis_count'), 'pa_axis_count');
Tests::same('پارامتری که فیلتر نیست، خالی برمی‌گردد', Query_State::taxonomy_for('orderby'), '');

/* ==========================================================================
 * پارامترهای خروجی
 * ======================================================================= */

Tests::group('وضعیت › پارامترهای خروجی');

Tests::same(
    'وضعیت خالی هیچ پارامتری نمی‌سازد',
    Query_State::create()->to_query_vars(),
    []
);

Tests::same(
    'صفحهٔ یک در آدرس نمی‌آید',
    Query_State::create([], '', 1)->to_query_vars(),
    []
);

Tests::same(
    'فیلتر و ترتیب و صفحه با نام‌های ووکامرسی',
    Query_State::create(['pa_brand' => ['up3d']], 'price', 2)->to_query_vars(),
    ['filter_brand' => 'up3d', 'orderby' => 'price', 'paged' => '2']
);

/* ==========================================================================
 * تغییر وضعیت
 * ======================================================================= */

Tests::group('وضعیت › تغییر');

$base = Query_State::create(['pa_brand' => ['up3d'], 'pa_axis' => ['5-axis']], 'price', 4);

Tests::same('without فقط همان گروه را برمی‌دارد', $base->without('pa_brand')->filters(), ['pa_axis' => ['5-axis']]);
Tests::same('و بقیهٔ وضعیت را دست نمی‌زند', $base->without('pa_brand')->sort(), 'price');
Tests::same('گروه ناموجود، همان نمونه را برمی‌گرداند', $base->without('pa_none')->filters(), $base->filters());

Tests::same('toggle ترم تازه را اضافه می‌کند', $base->toggle('pa_brand', 'vhf')->selected('pa_brand'), ['up3d', 'vhf']);
Tests::same('toggle ترم موجود را برمی‌دارد', $base->toggle('pa_brand', 'up3d')->selected('pa_brand'), []);

/*
 * ماندن روی صفحهٔ ۴ بعد از باریک‌کردن نتیجه به دو صفحه، یعنی صفحهٔ خالی —
 * که کاربر آن را «فیلتر چیزی پیدا نکرد» می‌خواند.
 */
Tests::same('هر تغییر فیلتر، صفحه را به اول برمی‌گرداند', $base->toggle('pa_brand', 'vhf')->page(), 1);
Tests::same('تغییر ترتیب هم همین‌طور', $base->with_sort('date')->page(), 1);

Tests::same('پاک‌کردن فیلترها، ترتیب را نگه می‌دارد', $base->cleared()->sort(), 'price');
Tests::same('و فیلترها را خالی می‌کند', $base->cleared()->filters(), []);

Tests::same('شمارش کل انتخاب‌ها', $base->count(), 2);
Tests::ok('has برای ترم انتخاب‌شده', $base->has('pa_brand', 'up3d'));
Tests::ok('و برای ترم دیگر نه', !$base->has('pa_brand', 'vhf'));

// تغییرناپذیری: نمونهٔ اصلی نباید با هیچ‌کدام از این‌ها عوض شده باشد
Tests::same('نمونهٔ اصلی دست‌نخورده مانده', $base->filters(), ['pa_axis' => ['5-axis'], 'pa_brand' => ['up3d']]);
