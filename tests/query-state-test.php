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

/*
 * ‎query_type_*‎ نوشته می‌شود ولی خوانده نمی‌شود. اپراتور یک تصمیم طرحِ
 * فیلتر است؛ اگر آدرس هم می‌توانست عوضش کند، بازدیدکننده می‌توانست معنای
 * فیلتری را عوض کند که مدیر عمداً روی AND گذاشته.
 */
Tests::same(
    'اپراتور از آدرس خوانده نمی‌شود',
    Query_State::from_request(
        ['filter_brand' => 'a,b', 'query_type_brand' => 'and'],
        ['pa_brand']
    )->to_query_vars(),
    ['filter_brand' => 'a,b', 'query_type_brand' => 'or']
);

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

/* --------------------------------------------------------------------------
 * اپراتور در آدرس
 *
 * ووکامرس وقتی ‎query_type_*‎ نباشد ‎and‎ فرض می‌کند
 * (‎WC_Query::get_layered_nav_chosen_attributes()‎). یعنی
 * ‎?filter_brand=up3d,vhf‎ به‌تنهایی «هم UP3D و هم VHF» است — که برای دو
 * برند همیشه صفر نتیجه می‌دهد. لینکِ اشتراکی، رندر سمت سرور، دکمهٔ back و
 * حالت بدون جاوااسکریپت همه از همین آدرس می‌خوانند.
 * ----------------------------------------------------------------------- */

Tests::same(
    'دو ترم در یک گروه، اپراتور را صریح می‌نویسد',
    Query_State::create(['pa_brand' => ['up3d', 'vhf']])->to_query_vars(),
    ['filter_brand' => 'up3d,vhf', 'query_type_brand' => 'or']
);

/*
 * با یک ترم، ‎and‎ و ‎or‎ دقیقاً یک نتیجه می‌دهند. نوشتنش فقط یک آدرس دوم
 * برای همان محتوا می‌ساخت که canonical باید حلش می‌کرد.
 */
Tests::same(
    'با یک ترم نوشته نمی‌شود',
    Query_State::create(['pa_brand' => ['up3d']])->to_query_vars(),
    ['filter_brand' => 'up3d']
);

/*
 * گروهی که مدیر عمداً روی AND گذاشته، همان پیش‌فرض ووکامرس است و چیزی
 * لازم ندارد.
 */
Tests::same(
    'گروه AND چیزی اضافه نمی‌کند',
    Query_State::create(['pa_fit' => ['x', 'y']])->to_query_vars(['pa_fit' => 'and']),
    ['filter_fit' => 'x,y']
);

Tests::same('pa_brand ⇒ query_type_brand', Query_State::query_type_for('pa_brand'), 'query_type_brand');

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

/* ==========================================================================
 * پارامترهای ناشناخته
 *
 * ‎from_request()‎ این‌ها را بی‌صدا می‌اندازد، که برای رندرشدن صفحه درست
 * است ولی برای *معنای* آدرس نه: ‎?filter_ghost=x‎ چیزی را ادعا می‌کند که
 * وجود ندارد و نباید ۲۰۰ بگیرد.
 * ======================================================================= */

Tests::group('وضعیت › پارامتر ناشناخته');

$known = ['pa_brand', 'pa_axis'];

Tests::same(
    'پارامتر شناخته‌شده ناشناخته نیست',
    Query_State::unknown_filters(['filter_brand' => 'up3d'], $known),
    []
);

Tests::same(
    'پارامتر بی‌صاحب گزارش می‌شود',
    Query_State::unknown_filters(['filter_ghost' => 'x'], $known),
    ['filter_ghost']
);

Tests::same(
    'چیزی که اصلاً فیلتر نیست، کاری به آن نداریم',
    Query_State::unknown_filters(['orderby' => 'price', 'paged' => '2', 's' => 'cnc'], $known),
    []
);

/*
 * ‎?filter_brand=‎ معمولاً از یک فرمِ ارسال‌شده می‌آید، نه از دست‌کاری.
 * ۴۰۴ دادن به آن، رفتار عادی مرورگر را می‌شکند.
 */
Tests::same(
    'پارامتر خالی ادعایی نمی‌کند',
    Query_State::unknown_filters(['filter_ghost' => '', 'filter_other' => '   '], $known),
    []
);

/*
 * ‎?filter_ghost[]=x‎ آدرس کاملاً معتبری است. تبدیل مستقیمش به رشته در
 * PHP 8 اخطار می‌دهد — همان‌جایی که تست معمولاً نمی‌رسد.
 */
Tests::same(
    'مقدار آرایه‌ای هم گزارش می‌شود، بدون اخطار',
    Query_State::unknown_filters(['filter_ghost' => ['x']], $known),
    ['filter_ghost']
);

Tests::same(
    'و آرایهٔ خالی، ادعایی نیست',
    Query_State::unknown_filters(['filter_ghost' => []], $known),
    []
);


/*
 * ‎filter_‎ خالی هیچ تاکسونومی‌ای نمی‌سازد؛ ولی یک ادعای بی‌معنا هست و
 * ‎taxonomy_for()‎ هم رشتهٔ خالی می‌دهد، پس هیچ‌وقت شناخته نمی‌شود.
 */
Tests::same(
    'پیشوند تنها هم ناشناخته است',
    Query_State::unknown_filters(['filter_' => 'x'], $known),
    ['filter_']
);

/* ==========================================================================
 * فیلتر تکراری
 *
 * «مطمئن شوید ترتیب منطقی فیلترها همیشه یکسان می‌ماند و هیچ فیلتر
 * تکراری‌ای نمی‌تواند وجود داشته باشد» — و گوگل برای همان‌ها ۴۰۴ خواسته.
 * دلیلش ساده است: ?filter_brand=up3d,up3d همان چیزی را نشان می‌دهد که
 * ?filter_brand=up3d، پس یک آدرس دوم رایگان برای یک محتوا — و چون تکرار
 * حد ندارد، تعدادشان هم بی‌نهایت است.
 * ======================================================================= */

Tests::group('وضعیت › فیلتر تکراری');

Tests::same(
    'ترم تکراری داخل یک گروه',
    Query_State::duplicate_filters(['filter_brand' => 'up3d,up3d']),
    ['filter_brand']
);

Tests::same(
    'ترم‌های متفاوت تکرار نیستند',
    Query_State::duplicate_filters(['filter_brand' => 'up3d,vhf']),
    []
);

/*
 * normalize() این را بی‌صدا یکی می‌کند — یعنی بدون این سنجش، آدرس ۲۰۰
 * می‌گرفت و هیچ‌جا معلوم نمی‌شد چیزی تکراری بوده.
 */
Tests::same(
    'و وضعیت واقعاً یکی‌شان می‌کند',
    Query_State::create(['pa_brand' => ['up3d', 'up3d']])->selected('pa_brand'),
    ['up3d']
);

Tests::same(
    'نحو آرایه‌ای هم همین‌طور',
    Query_State::duplicate_filters(['filter_brand' => ['up3d', 'up3d']]),
    ['filter_brand']
);

/*
 * ?filter_brand=a&filter_brand=b در PHP فقط b می‌شود و a بی‌صدا ناپدید
 * می‌شود. تنها جایی که آن تکرار هنوز دیده می‌شود، رشتهٔ خام است.
 */
Tests::same(
    'کلید تکراری فقط از رشتهٔ خام دیده می‌شود',
    Query_State::duplicate_filters(['filter_brand' => 'vhf'], 'filter_brand=up3d&filter_brand=vhf'),
    ['filter_brand']
);

Tests::same(
    'و بدون رشتهٔ خام، دیده نمی‌شود',
    Query_State::duplicate_filters(['filter_brand' => 'vhf']),
    []
);

/*
 * filter_brand[] نحو آرایه‌ای خودِ PHP است، نه کلید تکراری: مقدارهایش
 * سالم می‌رسند و اگر تکراری باشند، سنجش اول می‌گیردشان.
 */
Tests::same(
    'کروشه تکرار حساب نمی‌شود',
    Query_State::duplicate_filters(['filter_brand' => ['up3d', 'vhf']], 'filter_brand[]=up3d&filter_brand[]=vhf'),
    []
);

Tests::same(
    'پارامتر غیرفیلتر تکراری، کار ما نیست',
    Query_State::duplicate_filters([], 'utm_source=a&utm_source=b'),
    []
);

Tests::same(
    'رشتهٔ خام خالی چیزی نمی‌شکند',
    Query_State::duplicate_filters(['filter_brand' => 'up3d'], ''),
    []
);

/*
 * اسلاگ‌ها قبل از مقایسه پاک‌سازی می‌شوند، وگرنه UP3D و up3d دو ترم
 * متفاوت شمرده می‌شدند در حالی که دیتابیس یکی‌شان می‌داند.
 */
Tests::same(
    'تفاوت حروف بزرگ و کوچک تکرار است',
    Query_State::duplicate_filters(['filter_brand' => 'UP3D,up3d']),
    ['filter_brand']
);

Tests::same(
    'مقدار خالیِ وسط، تکرار نیست',
    Query_State::duplicate_filters(['filter_brand' => 'up3d,,vhf']),
    []
);

/* ==========================================================================
 * قاعدهٔ «شناخته‌شده یعنی اعمال‌شده»
 *
 * هر پارامتری که ناشناخته شمرده نشود، باید واقعاً روی وضعیت اثر بگذارد.
 * نقض این قاعده بی‌صداست: آدرس ۲۰۰ می‌گیرد، کاربر فکر می‌کند فیلتر اعمال
 * شده، و همان محتوای بدون فیلتر را می‌بیند.
 * ======================================================================= */

Tests::group('وضعیت › شناخته‌شده یعنی اعمال‌شده');

$cases = [
    'همه شناخته‌شده'   => [['filter_brand' => 'up3d', 'filter_axis' => '5'], ['pa_brand', 'pa_axis']],
    'یکی ناشناخته'     => [['filter_brand' => 'up3d', 'filter_ghost' => 'x'], ['pa_brand']],
    'هیچ‌کدام شناخته'  => [['filter_a' => '1', 'filter_b' => '2'], []],
    'کنار پارامترهای دیگر' => [['filter_brand' => 'up3d', 'orderby' => 'price', 'paged' => '2'], ['pa_brand']],
];

foreach ($cases as $name => [$params, $honored]) {
    $applied = [];

    foreach (array_keys(Query_State::from_request($params, $honored)->filters()) as $taxonomy) {
        $applied[] = Query_State::param_for($taxonomy);
    }

    $unknown = Query_State::unknown_filters($params, $honored);

    foreach (array_keys($params) as $key) {
        if (0 !== strpos((string) $key, 'filter_') || '' === trim((string) $params[$key])) {
            continue;
        }

        Tests::ok(
            $name . ' › ' . $key . ' یا اعمال می‌شود یا ناشناخته است',
            in_array($key, $applied, true) !== in_array($key, $unknown, true)
        );
    }
}

/* ==========================================================================
 * صریح‌کردن اپراتور در آدرس
 *
 * ووکامرس وقتی query_type_* نباشد، and فرض می‌کند:
 *
 *     $chosen[$taxonomy]['query_type'] = $query_type
 *         ? $query_type
 *         : apply_filters('woocommerce_layered_nav_default_query_type', 'and');
 *
 * یعنی ?filter_color=red,blue بدون آن پارامتر برای ووکامرس «هم قرمز و هم
 * آبی» است و برای طرحِ ما «قرمز یا آبی». روی آرشیو واقعی هر دو اجرا
 * می‌شوند: شمارشِ ۴۰۴ از کوئری ووکامرس می‌آید و گرید از کوئری ما.
 * ======================================================================= */

Tests::group('وضعیت › صریح‌کردن اپراتور');

$or_group = Query_State::create(['pa_color' => ['red', 'blue']]);

Tests::same(
    'گروه OR با دو ترم، پارامتر می‌خواهد',
    $or_group->query_type_fixes(['filter_color' => 'red,blue'], ['pa_color' => 'or']),
    ['set' => ['query_type_color' => 'or'], 'remove' => []]
);

Tests::same(
    'و وقتی هست، کاری لازم نیست',
    $or_group->query_type_fixes(
        ['filter_color' => 'red,blue', 'query_type_color' => 'or'],
        ['pa_color' => 'or']
    ),
    ['set' => [], 'remove' => []]
);

/*
 * and همان پیش‌فرض ووکامرس است؛ نوشتنش فقط یک آدرس دوم برای همان محتوا
 * می‌سازد.
 */
Tests::same(
    'گروه AND چیزی نمی‌خواهد',
    $or_group->query_type_fixes(['filter_color' => 'red,blue'], ['pa_color' => 'and']),
    ['set' => [], 'remove' => []]
);

Tests::same(
    'و پارامتر مخالفِ طرح برداشته می‌شود',
    $or_group->query_type_fixes(
        ['filter_color' => 'red,blue', 'query_type_color' => 'or'],
        ['pa_color' => 'and']
    ),
    ['set' => [], 'remove' => ['query_type_color']]
);

/*
 * با یک ترم، and و or دقیقاً یک چیزند: پارامتر بی‌اثر است و فقط آدرس
 * تکراری می‌سازد.
 */
Tests::same(
    'یک ترم، پارامتر بی‌اثر است',
    Query_State::create(['pa_color' => ['red']])->query_type_fixes(
        ['filter_color' => 'red', 'query_type_color' => 'or'],
        ['pa_color' => 'or']
    ),
    ['set' => [], 'remove' => ['query_type_color']]
);

Tests::same(
    'پارامتر یتیم هم برداشته می‌شود',
    Query_State::create()->query_type_fixes(['query_type_ghost' => 'or'], []),
    ['set' => [], 'remove' => ['query_type_ghost']]
);

/*
 * حلقه نمی‌سازد: مقصدِ ریدایرکت خودش دیگر چیزی برای اصلاح ندارد.
 */
$fixed = ['filter_color' => 'red,blue', 'query_type_color' => 'or'];

Tests::same(
    'اصلاحِ اصلاح‌شده، خالی است',
    Query_State::from_request($fixed, ['pa_color'])->query_type_fixes($fixed, ['pa_color' => 'or']),
    ['set' => [], 'remove' => []]
);

/* ==========================================================================
 * مقدار غیررشته‌ای
 *
 * ووکامرس صریحاً می‌اندازدش:
 *
 *     if ( 0 === strpos( $key, 'filter_' ) ) {
 *         if ( ! is_string( $value ) ) { continue; }
 *
 * اگر ما اعمالش کنیم، گرید با شمارشی که Archive_Head از کوئری اصلی
 * خوانده نمی‌خواند.
 * ======================================================================= */

Tests::group('وضعیت › مقدار غیررشته‌ای');

Tests::same(
    'آرایه اعمال نمی‌شود، مثل ووکامرس',
    Query_State::from_request(['filter_brand' => ['up3d']], ['pa_brand'])->filters(),
    []
);

Tests::same(
    'و ادعای بی‌صاحب گزارش می‌شود',
    Query_State::unknown_filters(['filter_brand' => ['up3d']], ['pa_brand']),
    ['filter_brand']
);

Tests::same(
    'رشته همچنان اعمال می‌شود',
    Query_State::from_request(['filter_brand' => 'up3d'], ['pa_brand'])->selected('pa_brand'),
    ['up3d']
);

/* ==========================================================================
 * سقف پیچیدگی
 *
 * اینجا و نه در نقطهٔ آژاکس، چون همان حمله از راه GET هم می‌آید و کوئری
 * اصلی ووکامرس را هم اجرا می‌کند. هر گروه فیلتر یک JOIN اضافه می‌کند و
 * هزینه خطی نیست.
 * ======================================================================= */

Tests::group('وضعیت › سقف');

$many_terms = [];

for ($i = 0; $i < Query_State::MAX_TERMS + 20; $i++) {
    $many_terms[] = 'term-' . $i;
}

Tests::same(
    'ترم‌های یک گروه بریده می‌شوند',
    count(Query_State::create(['pa_brand' => $many_terms])->selected('pa_brand')),
    Query_State::MAX_TERMS
);

$many_groups = [];

for ($i = 0; $i < Query_State::MAX_GROUPS + 8; $i++) {
    $many_groups['pa_g' . sprintf('%02d', $i)] = ['x'];
}

Tests::same(
    'گروه‌ها هم بریده می‌شوند',
    count(Query_State::create($many_groups)->filters()),
    Query_State::MAX_GROUPS
);

/*
 * بریدن بعد از مرتب‌سازی انجام می‌شود. اگر قبلش بود، ترتیب کلیک کاربر
 * تعیین می‌کرد کدام گروه بیفتد — یعنی دو آدرس یکسان، دو نتیجهٔ متفاوت و
 * دو کلید کش.
 */
Tests::same(
    'و انتخابشان قطعی است، نه وابسته به ترتیب ورودی',
    array_keys(Query_State::create($many_groups)->filters()),
    array_keys(Query_State::create(array_reverse($many_groups, true))->filters())
);

/*
 * ?paged=99999999 به وردپرس یک OFFSET نجومی می‌دهد. نتیجه خالی است ولی
 * دیتابیس برای رسیدن به آن خلأ کل مجموعه را می‌چیند.
 */
Tests::same(
    'شمارهٔ صفحه سقف دارد',
    Query_State::create([], '', 99999999)->page(),
    Query_State::MAX_PAGE
);

Tests::same('و صفحهٔ عادی دست‌نخورده می‌ماند', Query_State::create([], '', 3)->page(), 3);

/* --------------------------------------------------------------------------
 * و بریدن به‌تنهایی کافی نیست
 *
 * آدرسی با سی گروه، بعد از بریدن همان چیزی را نشان می‌دهد که نسخهٔ
 * دوازده‌گروهی‌اش — یعنی یک آدرس تکراریِ ۲۰۰. برخلاف اسلاگ ناشناخته،
 * اینجا نتیجه خالی هم نمی‌شود که filtered_empty بگیردش.
 * ------------------------------------------------------------------------ */

$honored = array_keys($many_groups);

$over_params = [];

foreach ($honored as $taxonomy) {
    $over_params[Query_State::param_for($taxonomy)] = 'x';
}

Tests::ok(
    'گروه‌های بیش از سقف گزارش می‌شوند',
    [] !== Query_State::oversized_filters($over_params, $honored)
);

Tests::same(
    'ولی تعداد مجاز نه',
    Query_State::oversized_filters(['filter_brand' => 'a,b,c'], ['pa_brand']),
    []
);

Tests::same(
    'ترم‌های بیش از سقف هم گزارش می‌شوند',
    Query_State::oversized_filters(['filter_brand' => implode(',', $many_terms)], ['pa_brand']),
    ['filter_brand']
);

/*
 * تکراری‌ها قبل از شمردن یکی می‌شوند، وگرنه ?filter_brand=a,a,a,… با پنجاه
 * تکرار «از سقف رد شده» حساب می‌شد در حالی که فقط یک ترم است.
 */
Tests::same(
    'تکرار، سقف را نمی‌شکند',
    Query_State::oversized_filters(
        ['filter_brand' => implode(',', array_fill(0, Query_State::MAX_TERMS + 10, 'a'))],
        ['pa_brand']
    ),
    []
);

Tests::same(
    'پارامتر ناشناخته کار این تابع نیست',
    Query_State::oversized_filters(['filter_ghost' => 'x'], ['pa_brand']),
    []
);

/* --------------------------------------------------------------------------
 * بریدن ورودی خام، پیش از ساختن کوئری
 *
 * سقف‌های normalize() فقط کوئری *ما* را می‌بندند. روی یک درخواست مستقیم
 * مرورگر، کوئری اصلی را ووکامرس می‌سازد و مستقیم از $_GET می‌خواند — و
 * هیچ فیلتری روی نتیجه‌اش ندارد. پس ورودی باید قبل از رسیدن به آن بریده
 * شود، وگرنه سرور اول یک JOIN سی‌تایی می‌سازد و بعد ما ۴۰۴ می‌دهیم.
 * ------------------------------------------------------------------------ */

Tests::group('وضعیت › بریدن ورودی خام');

$raw = [];

for ($i = 0; $i < Query_State::MAX_GROUPS + 8; $i++) {
    $raw['filter_g' . sprintf('%02d', $i)] = 'x';
}

$capped = Query_State::cap_params($raw);

Tests::same(
    'گروه‌های خام تا سقف بریده می‌شوند',
    count(array_filter(array_keys($capped), static fn(string $k): bool => 0 === strpos($k, 'filter_'))),
    Query_State::MAX_GROUPS
);

/*
 * query_type گروهی که افتاده هم باید برود، وگرنه یک پارامتر یتیم می‌ماند
 * که خودش یک آدرس تکراری می‌سازد.
 */
$with_types = $raw;

foreach (array_keys($raw) as $key) {
    $with_types[str_replace('filter_', 'query_type_', $key)] = 'or';
}

$capped_types = Query_State::cap_params($with_types);

Tests::same(
    'query_type گروه افتاده هم می‌رود',
    count(array_filter(array_keys($capped_types), static fn(string $k): bool => 0 === strpos($k, 'query_type_'))),
    Query_State::MAX_GROUPS
);

Tests::same(
    'ترم‌های خام هم بریده می‌شوند',
    count(explode(',', Query_State::cap_params(
        ['filter_brand' => implode(',', $many_terms)]
    )['filter_brand'])),
    Query_State::MAX_TERMS
);

Tests::same(
    'صفحهٔ نجومی بریده می‌شود',
    Query_State::cap_params(['paged' => '99999999'])['paged'],
    (string) Query_State::MAX_PAGE
);

/*
 * ورودی سالم باید *عیناً* برگردد. تساوی سخت است که نگهبان به آن تکیه
 * می‌کند تا بفهمد چیزی بریده شده یا نه؛ اگر این تابع ورودی بی‌گناه را هم
 * دست بزند، هر درخواستی «بریده‌شده» علامت می‌خورد و همه ۴۰۴ می‌گیرند.
 */
$innocent = ['filter_brand' => 'up3d,vhf', 'query_type_brand' => 'or', 'orderby' => 'price', 'paged' => '3', 'utm_source' => 'x'];

Tests::same('ورودی سالم دست‌نخورده برمی‌گردد', Query_State::cap_params($innocent), $innocent);

Tests::same('و آرایهٔ خالی هم', Query_State::cap_params([]), []);

/*
 * مقدار غیررشته‌ای را ووکامرس هم نمی‌خواند، پس گروه حساب نمی‌شود و سهمیهٔ
 * گروه‌های واقعی را نمی‌خورد.
 */
$mixed = ['filter_a' => ['x'], 'filter_b' => 'y'];

Tests::same('مقدار آرایه‌ای سهمیه نمی‌گیرد', Query_State::cap_params($mixed), $mixed);

/* --------------------------------------------------------------------------
 * چیزی که ووکامرس *می‌بیند*
 *
 * شمردن JOIN اثبات نیست: یک JOIN با IN(۵۰۰تایی) هم یک JOIN است. تنها
 * اثباتِ واقعی این است که ورودی را دقیقاً همان‌طور بخوانیم که
 * WC_Query::get_layered_nav_chosen_attributes() می‌خواند، و ببینیم چه
 * چیزی به دستش می‌رسد:
 *
 *     foreach ( $_GET as $key => $value ) {
 *         if ( 0 === strpos( $key, 'filter_' ) ) {
 *             if ( ! is_string( $value ) ) { continue; }
 *             $filter_terms = array_filter( array_map( 'sanitize_title', explode( ',', $value ) ) );
 *
 * پس همان را بازسازی می‌کنیم و روی ورودی‌های خصمانه می‌سنجیم.
 * ------------------------------------------------------------------------ */

Tests::group('وضعیت › آنچه ووکامرس می‌بیند');

/** خواندن ورودی دقیقاً به روش ووکامرس */
$woo_sees = static function (array $params): array {
    $seen = [];

    foreach ($params as $key => $value) {
        if (0 !== strpos((string) $key, 'filter_') || !is_string($value)) {
            continue;
        }

        $terms = array_filter(array_map('trim', explode(',', $value)));

        if ($terms) {
            $seen[(string) $key] = array_values($terms);
        }
    }

    return $seen;
};

$fifty_one = [];

for ($i = 0; $i < Query_State::MAX_TERMS + 1; $i++) {
    $fifty_one[] = 'slug-' . $i;
}

$attacks = [
    'گروه‌های زیاد'        => $raw,
    'ترم‌های زیاد'         => ['filter_brand' => implode(',', $many_terms)],
    'هر دو با هم'          => array_merge($raw, ['filter_brand' => implode(',', $many_terms)]),
    'گروه زیاد + صفحهٔ دور' => array_merge($raw, ['paged' => '99999999']),
    'ترم مرزی'             => ['filter_brand' => implode(',', $fifty_one)],
];

foreach ($attacks as $name => $attack) {
    $seen = $woo_sees(Query_State::cap_params($attack));

    Tests::ok(
        $name . ' › ووکامرس بیش از سقف گروه نمی‌بیند',
        count($seen) <= Query_State::MAX_GROUPS
    );

    $worst = 0;

    foreach ($seen as $terms) {
        $worst = max($worst, count($terms));
    }

    Tests::ok(
        $name . ' › و در هیچ گروهی بیش از سقف ترم',
        $worst <= Query_State::MAX_TERMS
    );
}

/*
 * سقف ترم *در هر گروه* است، نه روی مجموع — و این عمدی است: یک فست با
 * پنجاه انتخاب کاملاً مشروع است («همهٔ برندها»). یعنی بدترین حالتِ مجاز
 * دوازده گروه × پنجاه ترم است. عدد را صریح می‌نویسیم تا بودجه‌ای باشد که
 * انتخاب شده، نه چیزی که از قلم افتاده.
 */
$worst_case = Query_State::MAX_GROUPS * Query_State::MAX_TERMS;

Tests::ok('بدترین حالتِ مجاز، کران‌دار و آگاهانه است', $worst_case <= 600);

/*
 * و ترمی که افتاده باید *واقعاً* رفته باشد. اگر جایی در ورودی باقی بماند،
 * در SQL هم باقی می‌ماند.
 */
$dropped = Query_State::cap_params(['filter_brand' => implode(',', $fifty_one)]);

Tests::ok(
    'ترم بریده‌شده در ورودی نمی‌ماند',
    false === strpos($dropped['filter_brand'], 'slug-' . Query_State::MAX_TERMS)
);

Tests::ok(
    'ولی ترم‌های مجاز می‌مانند',
    false !== strpos($dropped['filter_brand'], 'slug-0')
);

/*
 * صفحه هم همان‌طور: چیزی که وردپرس به OFFSET تبدیل می‌کند.
 */
foreach (['99999999', '5001', (string) Query_State::MAX_PAGE] as $paged) {
    Tests::ok(
        'صفحهٔ «' . $paged . '» از سقف رد نمی‌شود',
        (int) Query_State::cap_params(['paged' => $paged])['paged'] <= Query_State::MAX_PAGE
    );
}
