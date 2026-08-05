<?php
/**
 * پاک‌سازی SVG.
 *
 * با فعال بودن آپلود SVG در وردپرس، هر کسی که دسترسی رسانه دارد می‌تواند
 * فایل دلخواه بگذارد. چون این افزونه SVG را به‌صورت inline در صفحه درج
 * می‌کند (تا با CSS رنگ بگیرد)، پاک‌سازی تنها چیزی است که بین آن فایل و
 * اجرای اسکریپت در مرورگر بازدیدکننده ایستاده.
 *
 * هر مورد اینجا یک بردار واقعی است، نه یک نمونهٔ ساختگی.
 */

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/svg.php';

use Zig3d_Widgets\Svg;

/** چند رشته که هیچ‌کدام نباید در خروجی بمانند */
function zig_blocks_all(string $label, string $output, array $needles): void {
    foreach ($needles as $needle) {
        Tests::blocks($label . ' — ' . $needle, $output, $needle);
    }
}

Tests::group('SVG › اسکریپت و رویداد');

zig_blocks_all(
    'رویداد بدون کوتیشن',
    Svg::sanitize('<svg xmlns="http://www.w3.org/2000/svg"><rect onload=alert(1) width="10"/></svg>'),
    ['onload', 'alert']
);

zig_blocks_all(
    'رویداد با کوتیشن',
    Svg::sanitize('<svg xmlns="http://www.w3.org/2000/svg"><rect onload="alert(1)"/></svg>'),
    ['onload', 'alert']
);

zig_blocks_all(
    'رویداد با فاصلهٔ اضافه دور مساوی',
    Svg::sanitize("<svg xmlns='http://www.w3.org/2000/svg'><rect  onmouseover = 'alert(1)' /></svg>"),
    ['onmouseover', 'alert']
);

zig_blocks_all(
    'تگ script',
    Svg::sanitize('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script><path d="M0 0"/></svg>'),
    ['<script', 'alert']
);

zig_blocks_all(
    'animate با onbegin',
    Svg::sanitize('<svg xmlns="http://www.w3.org/2000/svg"><animate onbegin="alert(1)" attributeName="x"/></svg>'),
    ['onbegin', '<animate']
);

zig_blocks_all(
    'foreignObject با HTML داخلش',
    Svg::sanitize('<svg xmlns="http://www.w3.org/2000/svg"><foreignObject><img src=x onerror=alert(1)></foreignObject></svg>'),
    ['foreignObject', 'onerror']
);

Tests::group('SVG › آدرس و CSS');

Tests::blocks(
    'use با href از نوع data:',
    Svg::sanitize('<svg xmlns="http://www.w3.org/2000/svg"><use href="data:image/svg+xml;base64,PHN2Zz4="/></svg>'),
    'data:'
);

Tests::blocks(
    'href به آدرس بیرونی',
    Svg::sanitize('<svg xmlns="http://www.w3.org/2000/svg"><use href="https://evil.example/x.svg#a"/></svg>'),
    'evil.example'
);

Tests::blocks(
    'لینک javascript:',
    Svg::sanitize('<svg xmlns="http://www.w3.org/2000/svg"><a href="javascript:alert(1)"><path d="M0 0"/></a></svg>'),
    'javascript'
);

// فاصله و کاراکتر کنترلی، ترفند کلاسیکِ پنهان‌کردن طرح‌واره است
Tests::blocks(
    'javascript: با کاراکتر کنترلی وسطش',
    Svg::sanitize("<svg xmlns=\"http://www.w3.org/2000/svg\"><use href=\"java\tscript:alert(1)\"/></svg>"),
    'alert'
);

Tests::blocks(
    'style با ‎@import‎',
    Svg::sanitize('<svg xmlns="http://www.w3.org/2000/svg"><rect style="@import url(//evil.example)"/></svg>'),
    '@import'
);

Tests::group('SVG › موجودیت‌ها');

/*
 * ‎LIBXML_NOENT‎ برخلاف نامش موجودیت‌ها را «باز می‌کند» نه غیرفعال. راه درست،
 * ردکردن سند پیش از پارس است — چون بعد از پارس، بازشدن اتفاق افتاده.
 */
Tests::same(
    'XXE با موجودیت خارجی کاملاً رد می‌شود',
    Svg::sanitize(
        '<?xml version="1.0"?><!DOCTYPE svg [<!ENTITY xxe SYSTEM "file:///etc/passwd">]>'
        . '<svg xmlns="http://www.w3.org/2000/svg"><text>&xxe;</text></svg>'
    ),
    ''
);

Tests::same(
    'موجودیت داخلی هم باز نمی‌شود',
    Svg::sanitize(
        '<?xml version="1.0"?><!DOCTYPE svg [<!ENTITY a "AAAA">]>'
        . '<svg xmlns="http://www.w3.org/2000/svg"><text>&a;&a;&a;</text></svg>'
    ),
    ''
);

Tests::same(
    'DOCTYPE ساده هم پذیرفته نمی‌شود',
    Svg::sanitize('<!DOCTYPE svg><svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0"/></svg>'),
    ''
);

Tests::group('SVG › محتوای سالم');

$icon = Svg::sanitize(
    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none">'
    . '<path d="M12 2L2 7l10 5 10-5-10-5z" fill="#7C3AED" stroke="#333" stroke-width="1.5"/>'
    . '</svg>'
);

Tests::keeps('مسیر حفظ می‌شود', $icon, '<path');
Tests::keeps('viewBox حفظ می‌شود', $icon, 'viewBox');
Tests::keeps('fill حفظ می‌شود', $icon, 'fill');
Tests::keeps('stroke حفظ می‌شود', $icon, 'stroke');

/*
 * ‎<use href="#id">‎ یعنی ارجاع به قطعه‌ای در همان فایل — الگوی رایج آیکون‌های
 * صادرشده از فیگما. اگر پاک‌سازی این را هم بگیرد، آیکون‌های سالم خالی
 * می‌شوند؛ یک مورد واقعی که فقط با همین سنجه دیده شد.
 */
Tests::keeps(
    'ارجاع داخلی مجاز است',
    Svg::sanitize('<svg xmlns="http://www.w3.org/2000/svg"><defs><path id="a" d="M0 0"/></defs><use href="#a"/></svg>'),
    'href="#a"'
);

Tests::keeps(
    'گرادیان حفظ می‌شود',
    Svg::sanitize(
        '<svg xmlns="http://www.w3.org/2000/svg"><defs><linearGradient id="g">'
        . '<stop offset="0" stop-color="#fff"/></linearGradient></defs><rect fill="url(#g)"/></svg>'
    ),
    'linearGradient'
);

Tests::same('ورودی غیر SVG رد می‌شود', Svg::sanitize('<div>hello</div>'), '');
Tests::same('ورودی خالی رد می‌شود', Svg::sanitize(''), '');
Tests::same('XML بدشکل رد می‌شود', Svg::sanitize('<svg><path d="M0 0"'), '');
