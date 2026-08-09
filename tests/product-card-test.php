<?php
/**
 * مونتاژ دادهٔ کارت محصول.
 *
 * کارت هشت بخش دارد و هر بخش می‌تواند نباشد. «کارت شکسته» چیزی است که در
 * تست رندر هم درست به نظر می‌رسد — خروجی HTML معتبر است، فقط سه حباب خالی
 * وسطش نشسته یا ریبونی که باید می‌آمد نیامده.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/woocommerce-stub.php';

$root = dirname(__DIR__);

require_once $root . '/includes/price.php';
require_once $root . '/includes/stock.php';
require_once $root . '/includes/card.php';
require_once $root . '/includes/product-card.php';

use Zig3d_Widgets\Card;
use Zig3d_Widgets\Product_Card;

zig_reset_products();

$fields = [
    'suggested_meta'   => '_zig_suggested',
    'description_meta' => '_zig_summary',
    'features_meta'    => '_zig_specs',
    'features_field'   => 'label',
    'brand_taxonomy'   => 'pa_brand',
];

/* ==========================================================================
 * کارت کامل
 * ======================================================================= */

Tests::group('کارت › کامل');

new WC_Product([
    'id'      => 10,
    'name'    => 'فرز پنج محور',
    'price'   => '120000000',
    'regular' => '120000000',
    'thumb'   => 900,
    'meta'    => [
        '_zig_suggested' => '1',
        '_zig_summary'   => 'میلینگ ۵ محور خشک با تعویض خودکار ابزار',
        '_zig_specs'     => [
            ['label' => 'خشک'],
            ['label' => '۵ محور'],
            ['label' => 'تعویض خودکار'],
            ['label' => 'چهارمی که نباید بیاید'],
        ],
    ],
    'terms'   => ['pa_brand' => [new WP_Term('UP3D', 'up3d', 5)]],
]);

$card = Product_Card::data(WC_Product::$registry[10], $fields);

Tests::same('عنوان', $card['title'], 'فرز پنج محور');
Tests::same('برند', $card['brand'], 'UP3D');
Tests::same('توضیح', $card['description'], 'میلینگ ۵ محور خشک با تعویض خودکار ابزار');
Tests::ok('ریبون پیشنهاد', $card['suggested']);
Tests::same('سه ویژگی، نه بیشتر', count($card['features']), 3);
Tests::same('و از زیرفیلد درست', $card['features'][1], '۵ محور');
/*
 * تصویر فقط شناسه است، نه تگ. اسکیپ و مارک‌آپ هر دو کار لحظهٔ خروجی‌اند —
 * و همین باعث می‌شود این داده برای پاسخ AJAX و دادهٔ ساختاریافته هم قابل
 * استفاده باشد.
 */
Tests::same('تصویر، شناسهٔ اتچمنت', $card['image'], 900);
Tests::same('حالت قیمت', $card['price_mode'], Card::PRICE_NUMERIC);
Tests::same('و لیبلش دقیق است، نه «از»', $card['price_label'], Card::LABEL_EXACT);
Tests::same('اقدام خودکار روی محصول قیمت‌دار', $card['cta'], Card::CTA_DETAILS);

/* ==========================================================================
 * چک‌باکس «پیشنهاد»
 * ======================================================================= */

Tests::group('کارت › پیشنهاد');

/*
 * وردپرس برای یک چک‌باکس مقادیر یکدستی ذخیره نمی‌کند: بسته به اینکه فیلد از
 * جت‌انجین آمده یا از یک متاباکس دستی، می‌تواند هرکدام از این‌ها باشد.
 * پذیرفتن فقط یکی یعنی ریبون روی نصف محصول‌ها بی‌صدا نیاید.
 */
foreach (['1', 'yes', 'true', 'on', 'YES', 1, true] as $index => $value) {
    new WC_Product(['id' => 100 + $index, 'meta' => ['_zig_suggested' => $value]]);

    Tests::ok(
        sprintf('مقدار %s پذیرفته می‌شود', var_export($value, true)),
        Product_Card::data(WC_Product::$registry[100 + $index], $fields)['suggested']
    );
}

foreach (['', '0', 'no', 'false', 0, false] as $index => $value) {
    new WC_Product(['id' => 200 + $index, 'meta' => ['_zig_suggested' => $value]]);

    Tests::ok(
        sprintf('و %s نه', var_export($value, true)),
        !Product_Card::data(WC_Product::$registry[200 + $index], $fields)['suggested']
    );
}

/* ==========================================================================
 * زنجیرهٔ ویژگی‌ها
 * ======================================================================= */

Tests::group('کارت › زنجیرهٔ ویژگی‌ها');

/*
 * محصولی که هنوز رپیترش پر نشده نباید کارتِ شکسته بدهد؛ باید از منبع بعدی
 * پر شود.
 */
new WC_Product([
    'id'    => 20,
    'meta'  => ['_zig_specs' => []],
    'attrs' => [
        new Zig_Test_Attribute(['name' => 'نوع فرزکاری', 'options' => ['خشک']]),
        new Zig_Test_Attribute(['name' => 'پنهان', 'visible' => false, 'options' => ['نباید بیاید']]),
    ],
]);

$fallback = Product_Card::data(WC_Product::$registry[20], $fields);

Tests::same('رپیتر خالی، به ویژگی‌های ووکامرس می‌رسد', $fallback['features'], ['خشک']);

/*
 * ویژگی‌های نامرئی معمولاً ویژگی‌های گزینه‌سازند و در کارت معنایی ندارند.
 */
Tests::blocks('ویژگی نامرئی نمی‌آید', implode('|', $fallback['features']), 'نباید بیاید');

new WC_Product(['id' => 21]);

Tests::same('هیچ منبعی نبود، هیچ', Product_Card::data(WC_Product::$registry[21], $fields)['features'], []);

/*
 * رپیتر تک‌فیلدی گاهی بدون نام زیرفیلد ذخیره می‌شود؛ بدون این حالت، هر
 * ردیف یک آرایه‌به‌رشته می‌شد.
 */
new WC_Product(['id' => 22, 'meta' => ['_zig_specs' => [['خشک'], ['۵ محور']]]]);

Tests::same(
    'رپیتر بدون نام زیرفیلد هم خوانده می‌شود',
    Product_Card::data(WC_Product::$registry[22], ['features_meta' => '_zig_specs'])['features'],
    ['خشک', '۵ محور']
);

/* ==========================================================================
 * بخش‌های غایب
 * ======================================================================= */

Tests::group('کارت › بخش‌های غایب');

new WC_Product(['id' => 30, 'name' => 'بدون هیچ']);

$bare = Product_Card::data(WC_Product::$registry[30], []);

/*
 * خروجی همیشه همان کلیدها را دارد، حتی وقتی خالی‌اند. یعنی رندر می‌تواند
 * بی‌قید و شرط بخواند به‌جای اینکه هر بار isset بزند و یکی را جا بیندازد.
 */
foreach (['id', 'url', 'title', 'image', 'suggested', 'brand', 'description', 'features', 'stock', 'price', 'price_mode', 'price_label', 'cta'] as $key) {
    Tests::ok(sprintf('کلید «%s» همیشه هست', $key), array_key_exists($key, $bare));
}

Tests::same('بدون کلید متا، ریبون نمی‌آید', $bare['suggested'], false);
Tests::same('و توضیحی هم نه', $bare['description'], '');
Tests::same('و برندی هم نه', $bare['brand'], '');

/* ==========================================================================
 * قیمت و اقدام
 * ======================================================================= */

Tests::group('کارت › قیمت و اقدام');

new WC_Product(['id' => 40, 'name' => 'استعلامی']);

$inquiry = Product_Card::data(WC_Product::$registry[40], []);

Tests::same('محصول بی‌قیمت، استعلامی', $inquiry['price_mode'], Card::PRICE_INQUIRY);

/*
 * رابطهٔ قیمت و اقدام فقط یک پیش‌فرض است: محصول استعلامی به «استعلام»
 * می‌رود، ولی مدیر می‌تواند هر چیز دیگری بگذارد.
 */
Tests::same('و اقدامش استعلام', $inquiry['cta'], Card::CTA_INQUIRY);

Tests::same(
    'ولی انتخاب صریح می‌چربد',
    Product_Card::data(WC_Product::$registry[40], ['cta_mode' => Card::CTA_CONSULTATION])['cta'],
    Card::CTA_CONSULTATION
);

Tests::same(
    'و می‌شود قیمت را کلاً پنهان کرد',
    Product_Card::data(WC_Product::$registry[40], ['no_price' => Card::PRICE_HIDDEN])['price_mode'],
    Card::PRICE_HIDDEN
);

/* ==========================================================================
 * صلاحیت ویژگی
 * ======================================================================= */

Tests::group('کارت › صلاحیت ویژگی');

/*
 * get_visible() و get_variation() در ووکامرس دو محور مستقل‌اند و سند خودش
 * صریح است: «If is visible on Product's additional info tab» و «If is used
 * for variations». استفاده از اولی به‌عنوان معیارِ «ویژگی فنی»، دو چیز
 * بی‌ربط را یکی گرفتن است.
 */
new WC_Product([
    'id'    => 60,
    'attrs' => [
        new Zig_Test_Attribute(['name' => 'نوع فرزکاری', 'options' => ['خشک']]),
        new Zig_Test_Attribute(['name' => 'رنگ', 'variation' => true, 'options' => ['قرمز']]),
        new Zig_Test_Attribute(['name' => 'پنهان', 'visible' => false, 'options' => ['نباید بیاید']]),
    ],
]);

$auto = Product_Card::data(WC_Product::$registry[60], [])['features'];

Tests::same('ویژگی نمایشیِ غیرگزینه‌ساز می‌آید', $auto, ['خشک']);

/*
 * رنگ در کارت یک دستگاه صنعتی معنایی ندارد: انتخاب خرید است، نه مشخصهٔ
 * دستگاه. و مهم‌تر، «نمایشی» بودنش هیچ ربطی به این نداشت.
 */
Tests::blocks('ویژگی گزینه‌ساز نمی‌آید، حتی اگر نمایشی باشد', implode('|', $auto), 'قرمز');
Tests::blocks('ویژگی نانمایشی هم نه', implode('|', $auto), 'نباید بیاید');

/*
 * فهرست صریح مقدم است و ترتیبش هم رعایت می‌شود: کارت سه حباب دارد و
 * «کدام سه‌تا» یک تصمیم طراحی است، نه چیزی که از ترتیب ذخیره‌سازی دربیاید.
 */
$picked = Product_Card::data(
    WC_Product::$registry[60],
    ['features_attrs' => ['رنگ', 'نوع فرزکاری']]
)['features'];

Tests::same('فهرست صریح، حتی گزینه‌ساز را هم می‌آورد', $picked, ['قرمز', 'خشک']);

Tests::same(
    'نامِ ناموجود در فهرست، کنار می‌رود',
    Product_Card::data(WC_Product::$registry[60], ['features_attrs' => ['نیست', 'خشک‌کن']])['features'],
    []
);
