<?php
/**
 * تصمیم‌های کارت محصول.
 *
 * این فایل بیش از هر چیز، یک مرز را نگه می‌دارد: قیمت و اقدام دو محور
 * مستقل‌اند. اگر روزی کسی «تماس بگیرید» را دوباره به حالت قیمت برگرداند،
 * باید اینجا بشکند — چون در خروجی HTML هیچ فرقی دیده نمی‌شود و فقط وقتی
 * معلوم می‌شود که بخواهی «محصولات دارای قیمت» را فیلتر کنی.
 */

require_once __DIR__ . '/bootstrap.php';

$root = dirname(__DIR__);

require_once $root . '/includes/card.php';

use Zig3d_Widgets\Card;

/* ==========================================================================
 * حالت قیمت
 * ======================================================================= */

Tests::group('کارت › حالت قیمت');

Tests::same(
    'قیمت ساده، لیبل دقیق',
    Card::price_state(['has_price' => true, 'current' => '1000']),
    ['mode' => Card::PRICE_NUMERIC, 'label' => Card::LABEL_EXACT]
);

Tests::same(
    'قیمت بازه‌ای، لیبل «از»',
    Card::price_state(['has_price' => true, 'is_range' => true, 'current' => '1000', 'max' => '2000'])['label'],
    Card::LABEL_FROM
);

/*
 * محصول متغیری که همهٔ گزینه‌هایش یک قیمت دارند، از نظر ووکامرس متغیر است
 * ولی از نظر مشتری نیست. «شروع از ۱۲ میلیون» روی چیزی که دقیقاً ۱۲ میلیون
 * است، وعدهٔ قیمت ارزان‌تری می‌دهد که وجود ندارد.
 */
Tests::same(
    'متغیرِ تک‌قیمتی، لیبل «از» نمی‌گیرد',
    Card::price_state(['has_price' => true, 'is_multi' => true, 'current' => '1000', 'max' => '1000'])['label'],
    Card::LABEL_EXACT
);

Tests::same(
    'ولی متغیرِ چندقیمتی می‌گیرد',
    Card::price_state(['has_price' => true, 'is_multi' => true, 'current' => '1000', 'max' => '3000'])['label'],
    Card::LABEL_FROM
);

Tests::same(
    'محصول بی‌قیمت پیش‌فرض استعلامی است',
    Card::price_state(['has_price' => false]),
    ['mode' => Card::PRICE_INQUIRY, 'label' => Card::LABEL_INQUIRY]
);

Tests::same(
    'و اگر خواسته شد، کلاً پنهان',
    Card::price_state(['has_price' => false], Card::PRICE_HIDDEN),
    ['mode' => Card::PRICE_HIDDEN, 'label' => '']
);

/*
 * صفر یعنی رایگان، نه بی‌قیمت. اولی باید عدد نشان بدهد و دومی استعلام —
 * قاطی‌شدنشان یعنی محصول رایگان، «تماس بگیرید» می‌گیرد.
 */
Tests::same(
    'رایگان همچنان قیمت عددی است',
    Card::price_state(['has_price' => true, 'is_free' => true, 'current' => '0'])['mode'],
    Card::PRICE_NUMERIC
);

/* ==========================================================================
 * حالت اقدام
 * ======================================================================= */

Tests::group('کارت › حالت اقدام');

Tests::same(
    'خودکار روی محصول قیمت‌دار ⇒ جزئیات',
    Card::cta_mode(Card::CTA_AUTO, Card::PRICE_NUMERIC),
    Card::CTA_DETAILS
);

Tests::same(
    'خودکار روی محصول استعلامی ⇒ استعلام',
    Card::cta_mode(Card::CTA_AUTO, Card::PRICE_INQUIRY),
    Card::CTA_INQUIRY
);

/*
 * رابطهٔ قیمت و اقدام فقط یک پیش‌فرض است، نه قفل. محصول استعلامی که مدیر
 * برایش «مشاوره» گذاشته باید مشاوره بماند — و همین است که این دو را دو
 * محور مستقل نگه می‌دارد.
 */
Tests::same(
    'انتخاب صریح بر پیش‌فرض می‌چربد',
    Card::cta_mode(Card::CTA_CONSULTATION, Card::PRICE_INQUIRY),
    Card::CTA_CONSULTATION
);

Tests::same(
    'حتی وقتی با حالت قیمت جور نیست',
    Card::cta_mode(Card::CTA_INQUIRY, Card::PRICE_NUMERIC),
    Card::CTA_INQUIRY
);

Tests::same(
    'مقدار ناشناخته مثل خودکار رفتار می‌کند',
    Card::cta_mode('ghost', Card::PRICE_INQUIRY),
    Card::CTA_INQUIRY
);

/* ==========================================================================
 * ویژگی‌ها
 * ======================================================================= */

Tests::group('کارت › ویژگی‌ها');

Tests::same(
    'منبع اول اگر چیزی داشته باشد، برنده است',
    Card::features([['الف', 'ب'], ['ج']]),
    ['الف', 'ب']
);

/*
 * محصولی که هنوز رپیترش پر نشده نباید کارتِ شکسته بدهد؛ باید از منبع بعدی
 * پر شود.
 */
Tests::same(
    'منبع خالی به بعدی می‌رسد',
    Card::features([[], ['ج', 'د']]),
    ['ج', 'د']
);

Tests::same(
    'منبعی که فقط فاصله دارد هم خالی حساب می‌شود',
    Card::features([['  ', ''], ['ج']]),
    ['ج']
);

/*
 * حالت سوم — سه حباب خالی — بدترین گزینه است: کارت به‌هم‌ریخته بدون هیچ
 * اطلاعاتی.
 */
Tests::same('هیچ منبعی نبود ⇒ هیچ', Card::features([[], []]), []);

Tests::same('سقف رعایت می‌شود', count(Card::features([['۱', '۲', '۳', '۴', '۵']])), 3);
Tests::same('سقف قابل تغییر است', count(Card::features([['۱', '۲', '۳', '۴']], 2)), 2);
Tests::same('سقف صفر یعنی هیچ', Card::features([['۱']], 0), []);

Tests::same('تگ‌ها از متن ویژگی پاک می‌شوند', Card::features([['<b>خشک</b>']]), ['خشک']);
