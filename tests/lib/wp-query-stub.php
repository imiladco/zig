<?php
/**
 * استابِ مشترکِ ‎WP_Query‎.
 *
 * دو مصرف‌کننده دارد و شکلش اجتماعِ نیازهایِ هر دو است — نه یکی محلی
 * برای هرکدام: پیش از این فایل، ‎archive-head-test.php‎ و
 * ‎download-archive-test.php‎ هرکدام یک ‎class WP_Query‎ی محلیِ خودشان را
 * پشتِ ‎class_exists()‎ تعریف می‌کردند. چون همهٔ فایل‌های تست در یک
 * پردازشِ PHP و به ترتیبِ الفباییِ ‎glob()‎ بار می‌شوند، ‎archive-head-test.php‎
 * (که زودتر می‌آید) همیشه برنده می‌شد — و نسخهٔ ساده‌ترش (بدونِ ذخیرهٔ
 * ‎$args‎، بدونِ ‎found_posts‎/‎max_num_pages‎) برایِ
 * ‎Download_Archive::context()‎ که رویِ همین خاصیت‌ها حساب باز کرده،
 * بی‌صدا کافی نبود.
 *
 * پس یک تعریف، جایی که بقیهٔ استاب‌های مشترک هستند — نه دو نسخهٔ رقیب که
 * کدام‌شان بار می‌شود به حرفِ اولِ نامِ فایل بند است.
 */

if (!class_exists('WP_Query')) {
    class WP_Query {

        /** ‎archive-head-test.php‎: کوئریِ اصلی، صریح روی ‎true‎/‎false‎ */
        public bool $singular = false;

        /** ‎archive-head-test.php‎: هر چه با set() نوشته شود، اینجا ضبط می‌شود */
        public array $writes = [];

        /** ‎download-archive-test.php‎: همان آرگومان‌هایی که ساخته شده — چیزی که آنجا واقعاً سنجیده می‌شود */
        public array $args = [];

        public int $found_posts = 4;
        public int $max_num_pages = 1;

        public function __construct(array $args = []) {
            $this->args = $args;
        }

        public function is_main_query(): bool {
            return true;
        }

        public function is_singular(): bool {
            return $this->singular;
        }

        public function get($key) {
            return null;
        }

        public function set($key, $value): void {
            $this->writes[$key] = $value;
        }
    }
}
