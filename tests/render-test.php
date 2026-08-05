<?php
/**
 * خروجی رندر ویجت‌ها.
 *
 * مسیر رندر جایی است که اشتباهاتش گران تمام می‌شود: تگ نامعتبر، محتوای
 * اسکیپ‌نشده، ‎<a>‎ بدون href، یا عنصر تهی که فقط یک فاصلهٔ بی‌دلیل در طراحی
 * می‌سازد. هیچ‌کدام خطای PHP نمی‌دهند.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/elementor-stub.php';

$root = dirname(__DIR__);

require_once $root . '/includes/svg.php';
require_once $root . '/includes/markup.php';
require_once $root . '/includes/widgets/traits/link.php';
require_once $root . '/includes/widgets/traits/icon.php';
require_once $root . '/includes/widgets/traits/box.php';
require_once $root . '/includes/widgets/feature-card.php';
require_once $root . '/includes/widgets/bullet-list.php';
require_once $root . '/includes/widgets/button.php';

use Zig3d_Widgets\Widgets\Bullet_List;
use Zig3d_Widgets\Widgets\Button;
use Zig3d_Widgets\Widgets\Feature_Card;

/** یک نمونهٔ تازه از ویجت، بدون سازندهٔ المنتور */
function zig_widget(string $class) {
    return (new ReflectionClass($class))->newInstanceWithoutConstructor();
}

function zig_render(string $class, array $settings): string {
    return zig_widget($class)->zig_render($settings);
}

/* ==========================================================================
 * کارت ویژگی
 * ======================================================================= */

Tests::group('رندر › کارت ویژگی');

$card = zig_render(Feature_Card::class, [
    'icon_source' => 'text',
    'icon_text'   => 'الف',
    'title'       => 'بررسی و پیشنهاد میلینگ ماشین مناسب',
    'title_tag'   => 'h3',
    'text'        => 'بررسی حجم تولید و نوع متریال.',
]);

Tests::keeps('ریشهٔ کارت رندر می‌شود', $card, 'class="zig-card"');
Tests::keeps('عنوان با تگ انتخابی می‌آید', $card, '<h3 class="zig-card__title"');
Tests::keeps('توضیحات رندر می‌شود', $card, 'zig-card__text');
Tests::keeps('آیکون متنی رندر می‌شود', $card, 'zig-icon__text');
Tests::keeps('آیکون تزئینی از دید صفحه‌خوان پنهان است', $card, 'aria-hidden="true"');

/*
 * ‎<span>‎ فقط محتوای درون‌خطی می‌پذیرد. اگر بدنهٔ کارت ‎<span>‎ باشد و عنوان
 * ‎<h3>‎، مرورگر مارک‌آپ را بازچینی می‌کند و ساختاری که سلکتورهای استایل
 * رویش حساب کرده‌اند از بین می‌رود.
 */
Tests::keeps('بدنهٔ کارت div است نه span', $card, '<div class="zig-card__body">');

Tests::group('رندر › کارت ویژگی، حالت‌های مرزی');

Tests::same(
    'کارتِ کاملاً خالی چیزی رندر نمی‌کند',
    trim(zig_render(Feature_Card::class, ['icon_source' => 'none', 'title' => '', 'text' => ''])),
    ''
);

Tests::same(
    'عنوانِ فقط-فاصله هم خالی حساب می‌شود',
    trim(zig_render(Feature_Card::class, ['icon_source' => 'none', 'title' => ' <br> ', 'text' => ''])),
    ''
);

$linked = zig_render(Feature_Card::class, [
    'icon_source' => 'none',
    'title'       => 'عنوان',
    'link'        => ['url' => 'https://zig3d.com/about', 'is_external' => 'on', 'nofollow' => 'on'],
]);

Tests::keeps('کارتِ لینک‌دار به a تبدیل می‌شود', $linked, '<a ');
Tests::keeps('آدرس در خروجی هست', $linked, 'https://zig3d.com/about');
Tests::keeps('کلاس حالت لینک‌دار اضافه می‌شود', $linked, 'zig-card--linked');
Tests::keeps('target خارجی تنظیم می‌شود', $linked, 'target="_blank"');
Tests::keeps('rel امن همراهش می‌آید', $linked, 'noopener');
Tests::keeps('nofollow رعایت می‌شود', $linked, 'nofollow');

$unlinked = zig_render(Feature_Card::class, ['icon_source' => 'none', 'title' => 'عنوان']);

// ‎<a>‎ بدون href نه فوکوس می‌گیرد نه لینک به حساب می‌آید
Tests::blocks('بدون پیوند، تگ a نمی‌سازد', $unlinked, '<a ');

Tests::group('رندر › کارت ویژگی، اسکیپ');

$dirty = zig_render(Feature_Card::class, [
    'icon_source' => 'text',
    'icon_text'   => '<script>alert(1)</script>',
    'title'       => 'سلام <script>alert(2)</script> <span class="ok">دنیا</span>',
    'title_tag'   => 'script',
    'text'        => '<img src=x onerror=alert(3)>',
]);

Tests::blocks('اسکریپت داخل عنوان حذف می‌شود', $dirty, '<script>alert(2)');
Tests::blocks('اسکریپت داخل متن آیکون حذف می‌شود', $dirty, '<script>alert(1)');
Tests::blocks('تگ img تزریق‌شده حذف می‌شود', $dirty, '<img');
Tests::blocks('رویداد onerror حذف می‌شود', $dirty, 'onerror');
Tests::keeps('span مجاز باقی می‌ماند', $dirty, '<span class="ok">');

// تگ عنوانِ دستکاری‌شده نباید به مارک‌آپ تبدیل شود
Tests::keeps('تگ عنوان غیرمجاز به تگ امن برمی‌گردد', $dirty, '<h3 class="zig-card__title"');
Tests::blocks('و هیچ عنصر script نمی‌سازد', $dirty, '<script');

/* ==========================================================================
 * لیست عنوان‌ها
 * ======================================================================= */

Tests::group('رندر › لیست عنوان‌ها');

$list = zig_render(Bullet_List::class, [
    'label_tag'    => 'span',
    'bullet_shape' => 'circle',
    'items'        => [
        ['label' => 'تیم مهندسی مکانیک', '_id' => 'aaa', 'show_bullet' => 'yes'],
        ['label' => 'تأمین قطعات', '_id' => 'bbb', 'show_bullet' => 'yes', 'is_active' => 'yes'],
        ['label' => '', '_id' => 'ccc'],
    ],
]);

Tests::keeps('فهرست با ul رندر می‌شود', $list, '<ul class="zig-list"');
Tests::keeps('نقش فهرست صریح اعلام می‌شود', $list, 'role="list"');
Tests::same('آیتم خالی رندر نمی‌شود', substr_count($list, '<li '), 2);
Tests::keeps('کلاس ریپیتر برای استایل تک‌آیتمی هست', $list, 'elementor-repeater-item-aaa');
Tests::keeps('آیتم فعال کلاس می‌گیرد', $list, 'zig-list__item--active');
Tests::keeps('آیتم فعال aria-current دارد', $list, 'aria-current="true"');
Tests::keeps('بولت با کلاس شکل می‌آید', $list, 'zig-list__bullet--circle');
Tests::keeps('بولت تزئینی است', $list, 'aria-hidden="true"');

Tests::same(
    'فهرست بدون آیتم معتبر، هیچ ul تهی‌ای نمی‌سازد',
    trim(zig_render(Bullet_List::class, ['items' => [['label' => '  '], ['label' => '<br>']]])),
    ''
);

Tests::same(
    'نبودِ آیتم‌ها هم خروجی ندارد',
    trim(zig_render(Bullet_List::class, ['items' => []])),
    ''
);

$linked_list = zig_render(Bullet_List::class, [
    'items' => [['label' => 'درباره ما', '_id' => 'a', 'link' => ['url' => 'https://zig3d.com']]],
]);

Tests::keeps('آیتم لینک‌دار به a تبدیل می‌شود', $linked_list, '<a class="zig-list__label"');

$plain_list = zig_render(Bullet_List::class, [
    'items' => [['label' => 'بدون لینک', '_id' => 'a']],
]);

Tests::blocks('آیتم بدون لینک، a نمی‌سازد', $plain_list, '<a ');

Tests::group('رندر › لیست، شکل بولت دستکاری‌شده');

$evil_shape = zig_render(Bullet_List::class, [
    'bullet_shape' => 'circle" onload="alert(1)',
    'items'        => [['label' => 'x', '_id' => 'a', 'show_bullet' => 'yes']],
]);

/*
 * چیزی که واقعاً اهمیت دارد این نیست که رشتهٔ «onload» در خروجی نباشد —
 * ممکن است بی‌ضرر داخل نام کلاس چسبیده باشد. اهمیتش این است که مقدار
 * نتواند از کوتیشنِ ویژگی بیرون بزند و ویژگی تازه‌ای بسازد. پس خودِ کلاس
 * سنجیده می‌شود، نه وجود یک زیررشته.
 */
preg_match('/class="([^"]*zig-list__bullet[^"]*)"/', $evil_shape, $matches);

Tests::ok(
    'کلاس بولت فقط کاراکتر مجاز دارد',
    (bool) preg_match('/^[A-Za-z0-9 _-]+$/', $matches[1] ?? '!'),
    $matches[1] ?? 'کلاسی پیدا نشد'
);

Tests::blocks('هیچ ویژگی رویدادی ساخته نمی‌شود', $evil_shape, 'onload=');

/* ==========================================================================
 * دکمه
 * ======================================================================= */

Tests::group('رندر › دکمه');

$btn = zig_render(Button::class, [
    'text' => 'درباره ZIG3D',
    'size' => 'lg',
    'link' => ['url' => 'https://zig3d.com/about'],
]);

Tests::keeps('با پیوند، تگ a می‌شود', $btn, '<a ');
Tests::keeps('کلاس اندازه اعمال می‌شود', $btn, 'zig-btn--lg');
Tests::keeps('متن در span خودش است', $btn, 'zig-btn__text');
Tests::blocks('لینک نباید ویژگی type بگیرد', $btn, 'type=');

$plain = zig_render(Button::class, ['text' => 'ارسال', 'tag' => 'button', 'button_type' => 'submit']);

Tests::keeps('بدون پیوند، تگ button می‌شود', $plain, '<button ');
Tests::keeps('نوع دکمه اعمال می‌شود', $plain, 'type="submit"');

/*
 * ‎<a>‎ بدون href فوکوس‌پذیر نیست و صفحه‌خوان آن را لینک نمی‌داند. پس انتخاب
 * صریحِ «لینک» بدون آدرس هم باید به عنصر معتبر برگردد.
 */
$forced = zig_render(Button::class, ['text' => 'بدون آدرس', 'tag' => 'a']);

Tests::keeps('انتخاب «لینک» بدون آدرس به button برمی‌گردد', $forced, '<button ');
Tests::blocks('و هیچ a بی‌href نمی‌سازد', $forced, '<a ');

$evil_type = zig_render(Button::class, ['text' => 'x', 'tag' => 'button', 'button_type' => 'submit" onclick="alert(1)']);

Tests::blocks('نوع دکمهٔ دستکاری‌شده رد می‌شود', $evil_type, 'onclick');
Tests::keeps('و به مقدار امن برمی‌گردد', $evil_type, 'type="button"');

Tests::group('رندر › دکمه، دسترسی‌پذیری');

$icon_only = zig_render(Button::class, [
    'text' => '',
    'icon' => ['value' => 'fas fa-arrow-left', 'library' => 'fa-solid'],
]);

// دکمهٔ فقط-آیکون بدون نام، برای صفحه‌خوان فقط «دکمه» است
Tests::keeps('دکمهٔ فقط-آیکون نامی برای صفحه‌خوان می‌گیرد', $icon_only, 'aria-label=');

$labelled = zig_render(Button::class, ['text' => 'بیشتر', 'aria_label' => 'اطلاعات بیشتر دربارهٔ زیگ']);

Tests::keeps('برچسب دستی اعمال می‌شود', $labelled, 'aria-label="اطلاعات بیشتر دربارهٔ زیگ"');

Tests::same(
    'دکمهٔ بدون متن و بدون آیکون چیزی رندر نمی‌کند',
    trim(zig_render(Button::class, ['text' => ''])),
    ''
);

Tests::group('رندر › دکمه، جای آیکون');

$icon_start = zig_render(Button::class, [
    'text'          => 'برگشت',
    'icon'          => ['value' => 'fas fa-arrow-right', 'library' => 'fa-solid'],
    'icon_position' => 'start',
]);

Tests::keeps('کلاس جای آیکون اعمال می‌شود', $icon_start, 'zig-btn--icon-start');
Tests::ok(
    'آیکون پیش از متن می‌آید',
    strpos($icon_start, 'zig-btn__icon') < strpos($icon_start, 'zig-btn__text')
);

$icon_end = zig_render(Button::class, [
    'text'          => 'ادامه',
    'icon'          => ['value' => 'fas fa-arrow-left', 'library' => 'fa-solid'],
    'icon_position' => 'end',
]);

Tests::ok(
    'آیکون پس از متن می‌آید',
    strpos($icon_end, 'zig-btn__icon') > strpos($icon_end, 'zig-btn__text')
);
