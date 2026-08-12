<?php
/**
 * پاک‌سازیِ یک گروهِ مشخصاتِ فنی.
 *
 * چیزی که اینجا محافظت می‌شود، شکلِ آیتم‌هاست: هر مشخصه باید یک مبدأِ
 * *واقعی* معلوم کند، وگرنه یک ردیفِ خالی در آکاردئون می‌نشیند که هیچ
 * ارزشی ندارد.
 */

require_once __DIR__ . '/bootstrap.php';

$root = dirname(__DIR__);

require_once $root . '/includes/spec-group.php';

use Zig3d_Widgets\Spec_Group;

/* ==========================================================================
 * پاک‌سازیِ آیتم
 * ======================================================================= */

Tests::group('گروهِ مشخصات › پاک‌سازیِ آیتم');

Tests::same(
    'ویژگیِ سراسری معتبر می‌ماند',
    Spec_Group::sanitize_item(['source' => 'attribute', 'attribute' => 'pa_brand'])['attribute'],
    'pa_brand'
);

Tests::same(
    'ویژگیِ انتخاب‌نشده هیچی نیست — نامِ آزاد این‌جا راه ندارد',
    Spec_Group::sanitize_item(['source' => 'attribute', 'attribute' => '']),
    null
);

Tests::same(
    'مقدارِ ناسالمِ ویژگی به یک اسلاگِ امن پاک می‌شود، نه دور نمی‌ریزد',
    Spec_Group::sanitize_item(['source' => 'attribute', 'attribute' => 'PA_Brand!!'])['attribute'],
    'pa_brand'
);

Tests::same(
    'مبدأِ نامعتبر به ویژگی برمی‌گردد',
    Spec_Group::sanitize_item(['source' => 'ghost', 'attribute' => 'pa_brand'])['source'],
    'attribute'
);

Tests::same('متایِ بی‌کلید هیچی نیست', Spec_Group::sanitize_item(['source' => 'custom_meta', 'meta_key' => '']), null);

Tests::same(
    'متایِ با کلید می‌ماند',
    Spec_Group::sanitize_item(['source' => 'custom_meta', 'meta_key' => 'spindle_rpm'])['meta_key'],
    'spindle_rpm'
);

foreach (['category', 'tag', 'sku', 'rating', 'stock', 'weight', 'dimensions'] as $source) {
    Tests::ok(
        "منبعِ «{$source}» بدونِ فیلدِ دیگری هم می‌ماند",
        null !== Spec_Group::sanitize_item(['source' => $source])
    );
}

Tests::same(
    'برچسبِ سفارشی، تگ را از دست می‌دهد نه متن را',
    Spec_Group::sanitize_item(['source' => 'dimensions', 'label' => '<b>ابعاد</b> کلی'])['label'],
    'ابعاد کلی'
);

/* ==========================================================================
 * پاک‌سازیِ گروه
 * ======================================================================= */

Tests::group('گروهِ مشخصات › پاک‌سازیِ گروه');

Tests::same('گروهِ بی‌آیتم هیچی نیست', Spec_Group::sanitize(['label' => 'خالی', 'items' => []]), null);

Tests::same(
    'گروهی که همهٔ آیتم‌هایش نامعتبرند هم هیچی نیست',
    Spec_Group::sanitize(['label' => 'همه نامعتبر', 'items' => [['source' => 'custom_meta', 'meta_key' => '']]]),
    null
);

$clean = Spec_Group::sanitize([
    'label' => 'سیستم ماشین‌کاری',
    'items' => [
        ['source' => 'attribute', 'attribute' => 'pa_axis-count'],
        ['source' => 'custom_meta', 'meta_key' => ''], // نامعتبر، دور ریخته می‌شود
        ['source' => 'weight'],
    ],
]);

Tests::same('برچسبِ گروه می‌ماند', $clean['label'], 'سیستم ماشین‌کاری');
Tests::same('آیتمِ نامعتبر از میانِ گروه دور می‌ریزد، نه کلِ گروه', count($clean['items']), 2);
