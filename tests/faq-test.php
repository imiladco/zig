<?php
/**
 * ویجتِ «سوالات متداول» — رندرِ واقعی، نه فقط ثبتِ کنترل‌ها.
 *
 * منبعِ داده دقیقاً همان الگویِ ‎Documents‎ است: ریپیترِ JetEngine Meta Box
 * رویِ پستِ جاری، با کلیدِ متا و کلیدِ هر زیرفیلد از پنلِ المنتور — پس همان
 * ‎get_post_meta()‎ی عمومیِ ‎lib/woocommerce-stub.php‎ کافی است.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/elementor-stub.php';
require_once __DIR__ . '/lib/woocommerce-stub.php';

$root = dirname(__DIR__);
require_once $root . '/includes/markup.php';
require_once $root . '/includes/widgets/faq.php';

use Zig3d_Widgets\Widgets\Faq;

/*
 * ‎get_queried_object()‎ (نه صرفاً ‎_id‎) — برایِ سنجیدنِ مسیرِ تازهٔ
 * «ترمِ جاری» در ‎Faq::read_current_meta()‎. پیش‌فرض ‎null‎ می‌ماند
 * (یعنی «پست») مگر یک سناریو صریحاً آن را به یک ‎WP_Term‎ عوض کند و
 * بعدش خودش پاک کند — تا فایل‌هایِ تستِ دیگر که این تابع را فقط برایِ
 * موجودیتش لازم دارند (مثلِ آرشیوِ محصولات) هم‌چنان ‎null‎ ببینند.
 */
if (!function_exists('get_queried_object')) {
    function get_queried_object() {
        return $GLOBALS['__zig_queried_object'] ?? null;
    }
}

$POST_ID = 801;
$GLOBALS['__zig_queried'] = $POST_ID;
$GLOBALS['__zig_queried_object'] = null;
$GLOBALS['__zig_post_meta'][$POST_ID] = [];

Tests::group('ویجتِ سوالات متداول');

$widget = zig_widget(Faq::class);

$fields = [
    'meta_key'     => 'faq',
    'field_title'  => 'faq_question',
    'field_answer' => 'faq_answer',
    'source_post_id' => 0,
];

$render = static function (array $overrides = []) use ($widget, $fields): string {
    return $widget->zig_render(array_merge($fields, $overrides));
};

/* ------------------------------------------------------------------
 * بدونِ ردیف
 * ---------------------------------------------------------------- */
Tests::same('بدونِ متایِ ریپیتر، خروجی خالی است (نه edit mode)', '', $render());

/* ------------------------------------------------------------------
 * سطرها + فیلترِ سطرهایِ بدونِ سوال
 * ---------------------------------------------------------------- */
$GLOBALS['__zig_post_meta'][$POST_ID]['faq'] = [
    ['faq_question' => 'در چه زمینه‌ای فعالیت می‌کند؟', 'faq_answer' => 'دندانپزشکیِ دیجیتال.'],
    // سوالِ بدونِ پاسخ: باید بازهم رندر شود، فقط بدونِ .zig-faq__answer
    ['faq_question' => 'آیا نمایندگی دارید؟', 'faq_answer' => ''],
    // سطرِ بدونِ سوال: کلاً حذف می‌شود
    ['faq_question' => '', 'faq_answer' => 'این هیچ‌وقت دیده نمی‌شود'],
    // سطرِ غیرِآرایه‌ای در ریپیتر: نادیده گرفته می‌شود، نه خطا
    'یک رشتهٔ نامعتبر',
];

$html = $render();

Tests::same('دو سطرِ معتبر رندر می‌شوند، بقیه حذف می‌شوند', 2, substr_count($html, 'zig-faq__item'));
Tests::ok('متنِ سوالِ اول در خروجی هست', false !== strpos($html, 'در چه زمینه‌ای فعالیت می‌کند؟'));
Tests::ok('پاسخِ سوالِ اول در خروجی هست', false !== strpos($html, 'دندانپزشکیِ دیجیتال.'));
Tests::same('فقط یک ‎.zig-faq__answer‎ — سوالِ بدونِ پاسخ آن را رندر نمی‌کند', 1, substr_count($html, 'zig-faq__answer'));
Tests::ok('سوالِ خالی حذف شده', false === strpos($html, 'این هیچ‌وقت دیده نمی‌شود'));

/* ------------------------------------------------------------------
 * قراردادِ کلیک: خودِ سوال ‎<summary>‎ است، نه لینک/دکمه — کنشِ باز/بستنِ
 * محتوایِ همین صفحه است، نه ناوبری.
 * ---------------------------------------------------------------- */
Tests::ok('سرستون summary است', false !== strpos($html, '<summary class="zig-faq__question">'));
Tests::ok('آیتم details است — بدونِ جاوااسکریپت هم باز/بسته می‌شود', false !== strpos($html, '<details class="zig-faq__item"'));

/* ------------------------------------------------------------------
 * اسکیپ: عنوان/پاسخِ حاویِ HTML باید با wp_kses عبور کند، نه raw
 * ---------------------------------------------------------------- */
$GLOBALS['__zig_post_meta'][$POST_ID]['faq'] = [
    ['faq_question' => 'سوال با <script>alert(1)</script> و <strong>پررنگ</strong>', 'faq_answer' => 'پاسخ با <b>خط</b><br>بعدی'],
];
$xss_html = $render();
Tests::ok('برچسبِ غیرِمجاز حذف می‌شود', false === strpos($xss_html, '<script>'));
Tests::ok('برچسبِ مجاز (strong/b/br) می‌ماند', false !== strpos($xss_html, '<strong>پررنگ</strong>') && false !== strpos($xss_html, '<b>خط</b><br>بعدی'));

/* ------------------------------------------------------------------
 * گزینهٔ اول باز باشد
 * ---------------------------------------------------------------- */
$GLOBALS['__zig_post_meta'][$POST_ID]['faq'] = [
    ['faq_question' => 'اول', 'faq_answer' => 'یک'],
    ['faq_question' => 'دوم', 'faq_answer' => 'دو'],
];
$open_html = $render();
Tests::ok('پیش‌فرض: فقط اولین آیتم open است', 1 === substr_count($open_html, ' open') && strpos($open_html, ' open') < strpos($open_html, 'دوم'));

$closed_html = $render(['expand_first' => '']);
Tests::same('خاموش‌کردنِ «گزینهٔ اول باز باشد» هیچ آیتمی را باز نمی‌گذارد', 0, substr_count($closed_html, ' open'));

/* ------------------------------------------------------------------
 * شناسهٔ پستِ دستی — منبعِ داده می‌تواند از پستی غیرِ جاری بیاید
 * ---------------------------------------------------------------- */
$OTHER_ID = 802;
$GLOBALS['__zig_post_meta'][$OTHER_ID] = ['faq' => [
    ['faq_question' => 'سوالِ پستِ دیگر', 'faq_answer' => ''],
]];
$manual_html = $render(['source_post_id' => $OTHER_ID]);
Tests::ok('شناسهٔ پستِ دستی از پستِ جاری اولویت دارد', false !== strpos($manual_html, 'سوالِ پستِ دیگر'));

/* ------------------------------------------------------------------
 * ریپیتر رویِ ترمِ جاری (نه پست) — رفعِ باگِ گزارش‌شده: کاربر ریپیتر را
 * رویِ «Taxonomy Meta»یِ JetEngine ساخته بود (کلیدی مثلِ
 * ‎zig3d-faq-terms‎)، پس روی آرشیوِ آن ترم چیزی استخراج نمی‌شد — چون
 * get_post_meta() با شناسهٔ ترم دنبالِ جدولِ اشتباه می‌گشت.
 * ---------------------------------------------------------------- */
$TERM_ID = 55;
$GLOBALS['__zig_term_meta'][$TERM_ID]['zig3d-faq-terms'] = [
    ['zig3d-faq-terms-title' => 'سوالِ رویِ ترم', 'zig3d-faq-terms-answere' => 'پاسخِ رویِ ترم'],
];
$GLOBALS['__zig_queried_object'] = new WP_Term('دسته‌بندیِ نمونه', 'sample-cat', $TERM_ID);

$term_html = $widget->zig_render([
    'meta_key'        => 'zig3d-faq-terms',
    'field_title'     => 'zig3d-faq-terms-title',
    'field_answer'    => 'zig3d-faq-terms-answere',
    'source_post_id'  => 0,
]);

Tests::ok('رویِ آرشیوِ یک ترم، از term_meta خوانده می‌شود نه post_meta', false !== strpos($term_html, 'سوالِ رویِ ترم') && false !== strpos($term_html, 'پاسخِ رویِ ترم'));

// شناسهٔ پستِ دستی هنوز پست را می‌خواهد، حتی وقتی get_queried_object() ترم است
$GLOBALS['__zig_post_meta'][$OTHER_ID]['faq'] = [
    ['faq_question' => 'سوالِ پستِ دستی زیرِ آرشیوِ ترم', 'faq_answer' => ''],
];
$manual_over_term_html = $render(['source_post_id' => $OTHER_ID]);
Tests::ok('شناسهٔ پستِ دستی حتی رویِ آرشیوِ ترم هم بر خودِ ترم اولویت دارد', false !== strpos($manual_over_term_html, 'سوالِ پستِ دستی زیرِ آرشیوِ ترم') && false === strpos($manual_over_term_html, 'سوالِ رویِ ترم'));

$GLOBALS['__zig_queried_object'] = null;

/* ------------------------------------------------------------------
 * کنترل‌ها و ثبت
 * ---------------------------------------------------------------- */
$controls = zig_collect_controls(Faq::class);
foreach ([
    'meta_key', 'source_post_id',
    'field_title', 'field_answer',
    'expand_first',
    'box_bg', 'box_border_color', 'box_border_width', 'box_radius', 'box_divider_color', 'question_padding', 'open_gap',
    'group:question_typography', 'question_color',
    'group:question_typography_open', 'question_color_open',
    'question_accent_color',
    'toggle_size', 'toggle_icon_size', 'toggle_radius', 'toggle_icon_color', 'toggle_border_color', 'toggle_bg', 'toggle_bg_open',
    'group:answer_typography', 'answer_color', 'answer_gap',
] as $control) {
    Tests::ok('کنترل موجود است: ' . $control, in_array($control, $controls, true));
}

foreach (zig_collect_selectors(Faq::class) as [$control, $selector]) {
    Tests::ok('سلکتورِ محدود به این ویجت: ' . $control, false !== strpos($selector, '{{WRAPPER}}'));
}

$plugin_source  = file_get_contents($root . '/includes/plugin.php');
$widget_source  = file_get_contents($root . '/includes/widgets/faq.php');
$css_source     = file_get_contents($root . '/assets/css/zig3d-widgets.css');
$js_source      = file_get_contents($root . '/assets/js/zig3d-faq.js');

Tests::ok('ویجت ثبت شده', false !== strpos($plugin_source, "'faq' => Widgets\\Faq::class"));
Tests::ok('اسکریپتِ زیگ-فک ثبت شده', false !== strpos($plugin_source, "'zig3d-faq'") && false !== strpos($plugin_source, 'zig3d-faq.js'));
Tests::ok('get_script_depends به zig3d-faq اشاره می‌کند', false !== strpos($widget_source, "['zig3d-faq']"));

/*
 * دقتِ فیگما: باکس، جداکننده، عنوان (عادی/فعال)، نشان، پاسخ — همان
 * مقادیرِ دقیقی که کاربر داد، نه گرد‌شده یا حدسی.
 */
Tests::ok('پس‌زمینه/حاشیه/شعاعِ باکس دقیقِ فیگما', false !== strpos($css_source, '--zig-faq-box-bg: #FFFFFF;') && false !== strpos($css_source, '--zig-faq-box-border: #EAECF0;') && false !== strpos($css_source, '--zig-faq-radius: 16px;'));
Tests::ok('رنگِ جداکننده دقیقِ فیگما', false !== strpos($css_source, '--zig-faq-divider: #EEF1F6;'));
Tests::ok('عنوانِ عادی: 15px/400/20px', false !== strpos($css_source, "font-size: 15px;\n\tfont-weight: 400;\n\tline-height: 20px;"));
Tests::ok('عنوانِ فعال: 16px/600', false !== strpos($css_source, "font-size: 16px;\n\tfont-weight: 600;"));
Tests::ok('رنگِ عنوان (هر دو حالت) دقیقِ فیگما', false !== strpos($css_source, '--zig-faq-question-color: #101828;') && false !== strpos($css_source, '--zig-faq-question-color-open: #101828;'));
Tests::ok('پاسخ: 14px/400/26.6px، رنگِ #4B5563', false !== strpos($css_source, '--zig-faq-answer-color: #4B5563;') && false !== strpos($css_source, 'font-size: 14px;') && false !== strpos($css_source, 'line-height: 26.6px;'));
Tests::ok('گپِ پاسخ از باکسِ سوال ۴px است', false !== strpos($css_source, '--zig-faq-answer-gap: 4px;'));
Tests::ok('نشان: 24px، آیکون 16px، مرز #E4E7EC، بی‌نهایت‌گرد (999px نه عددِ فیگما)', false !== strpos($css_source, '--zig-faq-toggle-size: 24px;') && false !== strpos($css_source, '--zig-faq-toggle-icon-size: 16px;') && false !== strpos($css_source, '--zig-faq-toggle-border: #E4E7EC;') && false !== strpos($css_source, '--zig-faq-toggle-radius: 999px;') && false === strpos($css_source, '26843500'));
Tests::ok('پس‌زمینهٔ نشان: سفید در حالتِ عادی، #F2F4F7 در حالتِ فعال', false !== strpos($css_source, '--zig-faq-toggle-bg: #FFFFFF;') && false !== strpos($css_source, '--zig-faq-toggle-bg-open: #F2F4F7;'));
Tests::ok('رنگِ پیش‌فرضِ آیکونِ نشان دقیقِ فیگما است', false !== strpos($css_source, '--zig-faq-toggle-icon-color: #475467;'));
Tests::ok('قاعدهٔ نشانِ فعال فقط پس‌زمینه را عوض می‌کند، نه رنگِ آیکون را', false !== strpos($css_source, '.zig-faq__item[open] > .zig-faq__question .zig-faq__toggle {
	background: var(--zig-faq-toggle-bg-open, #F2F4F7);
}'));

/*
 * مکانیزمِ باکس‌هایِ بستهٔ پشتِ‌سرهم — همان الگویِ ‎.zig-specs__group‎:
 * overlap با margin منفی، رادیوس فقط رویِ لبهٔ واقعی.
 */
Tests::keeps('باکس‌هایِ بسته با overlap یک کارتِ یکپارچه می‌سازند', $css_source, '.zig-faq__item + .zig-faq__item {
	margin-top: calc(-1 * var(--zig-faq-box-border-width, 1px));');
Tests::keeps('باکسِ باز مستقل و با رادیوسِ کامل جدا می‌شود', $css_source, '.zig-faq__item[open] {
	border-radius: var(--zig-faq-radius, 16px);');

/* ------------------------------------------------------------------
 * JS: تک‌بازشو + انیمیشنِ ارتفاع، جداگانه از zig3d-specs.js
 * ---------------------------------------------------------------- */
Tests::ok('JS رویِ کلاس‌هایِ خودِ FAQ کار می‌کند، نه specs', false !== strpos($js_source, "':scope > .zig-faq__question'") && false !== strpos($js_source, "':scope > .zig-faq__answer'"));
Tests::ok('هوکِ المنتور به عنصرِ آماده‌شدنِ همین ویجت گوش می‌دهد', false !== strpos($js_source, "'frontend/element_ready/zig3d-faq.default'"));
Tests::ok('سوالِ بدونِ پاسخ هم قابلِ باز شدن است (content اختیاری)', false !== strpos($js_source, 'this.content ? this.content.offsetHeight : 0'));

/* ------------------------------------------------------------------
 * دیباگ: فقط پشتِ WP_DEBUG، بی‌اثر روی سایتِ زنده
 * ---------------------------------------------------------------- */
Tests::ok('لاگِ دیباگ پشتِ WP_DEBUG قفل است', false !== strpos($widget_source, "if (!defined('WP_DEBUG') || !WP_DEBUG) {"));
Tests::ok('لاگ با error_log می‌رود، نه echo رویِ فرانت‌اند', false !== strpos($widget_source, "error_log('[zig3d-faq] '"));
Tests::ok('لاگ نوع/شناسه/کلید و مقدارِ خام را می‌گوید', false !== strpos($widget_source, "'خواندنِ متا: نوع=%s شناسه=%d کلید=«%s» → %s'"));
Tests::ok('لاگِ نتیجه تعدادِ سطرهایِ خام/معتبر/ردشده را می‌گوید — کلیدِ عنوانِ اشتباه با اولین نگاه پیدا می‌شود', false !== strpos($widget_source, "'نتیجه: %d سطرِ خام، %d سطرِ معتبر، %d سطرِ بدونِ عنوان رد شد."));
