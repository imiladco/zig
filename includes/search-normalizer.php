<?php
namespace Zig3d_Widgets;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * نرمال‌سازیِ متنِ فارسی برایِ سرچ — خالص، بدونِ هیچ وابستگی‌ای به وردپرس.
 *
 * مسئله‌ای که حل می‌کند مالِ *ورودیِ کاربر* است، نه مالِ عنوانِ محصول:
 * ادمین یک‌بار عنوان را تایپ می‌کند (با یک کیبورد، یک عادت)؛ هزاران
 * مشتری هرکدام با کیبوردِ خودشان («ي» عربی به‌جایِ «ی» فارسی، رقمِ
 * عربی/فارسی/لاتین، با یا بدونِ ZWNJ) همان چیز را می‌نویسند. پس این
 * کلاس *کوئری* را به چند شکلِ محتملِ سطحی بسط می‌دهد — نه اینکه بخواهد
 * دیتابیس را بازنویسی کند (به همین دلیل «ایندکسِ سایه» ساخته نمی‌شود؛
 * نگاه کنید به داک‌بلاکِ ‎Search_Query‎).
 *
 * چهار لایه، هرکدام یک Tier با هزینهٔ false-positive بیشتر:
 *
 *   Tier 1  خودِ عبارت، فقط با یکدست‌سازیِ نویسه‌ای (ی/ک/رقم/اعراب)
 *   Tier 2  نحوهٔ پیوستنِ کلمات فرق دارد (فاصله/ZWNJ/چسبیده)
 *   Tier 3  رقم↔حرف («3 بعدی» ↔ «سه بعدی» ↔ «3D»)
 *   Tier 4  مترادفِ دستیِ ادمین («میلینگ» ↔ «فرز»)
 */
final class Search_Normalizer {

    /** یکدست‌سازیِ نویسه‌به‌نویسه: عربی→فارسی، رقم‌های عربی/فارسی→لاتین */
    private const CHAR_MAP = [
        // عربی → فارسی
        'ي' => 'ی', 'ك' => 'ک', 'ة' => 'ه',
        'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا',
        'ؤ' => 'و', 'ئ' => 'ی',
        // رقمِ فارسی → لاتین
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
        '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        // رقمِ عربی (اندیک) → لاتین
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
    ];

    /**
     * اعراب/تشدید و کشیدگی — حذف می‌شوند چون در تایپِ روزمره تصادفی
     * حاضر/غایب‌اند.
     *
     * فهرستِ صریح است نه دامنهٔ رجکس، و این عمدی است: ‎sql_expr()‎ باید
     * *همین* نویسه‌ها را از ستون هم حذف کند و ‎REPLACE‎ی SQL دامنه
     * نمی‌فهمد. با یک دامنهٔ گشادِ رجکس در PHP و یک فهرستِ محدود در SQL،
     * نرمال‌سازی نامتقارن می‌شد — دقیقاً همان حفره‌ای که این فهرست می‌بندد.
     *
     * پوشش: حرکاتِ ‎U+064B..U+0658‎، الفِ خنجریِ ‎U+0670‎، و تطویلِ
     * ‎U+0640‎. اینها همان چیزهایی‌اند که در متنِ فارسی/عربیِ واقعی دیده
     * می‌شوند؛ نشانه‌هایِ قرآنیِ کمیاب‌تر عمداً بیرون‌اند تا هر دو طرف
     * دقیقاً یک فهرست را بشناسند.
     */
    private const DIACRITICS = [
        "\xD9\x8B", "\xD9\x8C", "\xD9\x8D", "\xD9\x8E", "\xD9\x8F",
        "\xD9\x90", "\xD9\x91", "\xD9\x92", "\xD9\x93", "\xD9\x94",
        "\xD9\x95", "\xD9\x96", "\xD9\x97", "\xD9\x98",
        "\xD9\xB0",
        "\xD9\x80",
    ];

    private const ZWNJ = "\xE2\x80\x8C"; // U+200C

    /** فقط ۰ تا ۱۰ — دامنه‌ای که واقعاً در نام‌گذاریِ محصول («سه‌بعدی»، «چهار محوره») پیش می‌آید */
    private const DIGIT_TO_WORD = [
        0 => 'صفر', 1 => 'یک', 2 => 'دو', 3 => 'سه', 4 => 'چهار', 5 => 'پنج',
        6 => 'شش', 7 => 'هفت', 8 => 'هشت', 9 => 'نه', 10 => 'ده',
    ];

    /** پسوندهایی که بعدِ رقم/حرفِ عدد می‌آیند و «این یک عددِ توصیفی است» را نشان می‌دهند */
    private const NUMERIC_SUFFIXES = ['بعدی', 'بعد', 'محوره', 'محور'];

    /** سقفِ کلِ وریانت‌ها — از انفجارِ WHERE در SQL جلوگیری می‌کند */
    public const MAX_VARIANTS = 8;

    /**
     * همان یکدست‌سازیِ ‎normalize()‎، ولی به‌صورتِ یک عبارتِ SQL رویِ یک
     * ستون — تا *هر دو طرفِ* مقایسه نرمال شوند، نه فقط کوئری.
     *
     * چرا این لازم است و چرا وریانت‌سازی جوابش نبود: ادمین ممکن است
     * عنوان را با «ماشين» (یِ عربی) یا «۳» (رقمِ فارسی) ذخیره کرده باشد.
     * اگر فقط کوئری را canonical کنیم، «ماشین»ِ نرمال‌شده هیچ‌وقت آن
     * ردیف را پیدا نمی‌کند. راهِ دیگر این بود که برایِ هر ترکیبِ ممکنِ
     * ی/ي و ک/ك یک وریانت بسازیم — که با nتا حرف می‌شود ۲ⁿ الگو و
     * WHERE را منفجر می‌کند، آن هم بدونِ اینکه *قطعی* باشد.
     *
     * این‌طور دقیق است، نه حدسی: ستون همان نگاشتی را می‌خورد که کوئری
     * خورده، پس دو طرف در یک فضایِ canonical مقایسه می‌شوند.
     *
     * هزینه‌اش هم آن‌قدری نیست که به نظر می‌رسد: الگویِ ‎'%…%'‎ از قبل
     * هیچ ایندکسی را قابلِ استفاده نمی‌کند، پس ‎REPLACE‎ فقط چند عملیاتِ
     * رشته‌ای به هر ردیفِ همان اسکنِ ناگزیر اضافه می‌کند — و نتیجه هم
     * پشتِ کشِ نسخه‌دارِ ‎Search_Query‎ می‌نشیند.
     *
     * ورودی نامِ ستون است (‎wp_posts.post_title‎)، نه دادهٔ کاربر؛ مقادیرِ
     * نگاشت هم ثابت‌هایِ همین کلاس‌اند، پس هیچ رشتهٔ بیرونی وارد SQL
     * نمی‌شود.
     */
    public static function sql_expr(string $column): string {
        $expr = $column;

        foreach (self::CHAR_MAP as $from => $to) {
            $expr = "REPLACE({$expr}, '{$from}', '{$to}')";
        }

        // همان اعرابی که ‎normalize()‎ از کوئری برمی‌دارد، از ستون هم
        // برداشته می‌شود — وگرنه تقارن فقط تا نیمهٔ راه بود.
        foreach (self::DIACRITICS as $mark) {
            $expr = "REPLACE({$expr}, '{$mark}', '')";
        }

        return $expr;
    }

    /**
     * یکدست‌سازیِ نویسه‌ای — Tier 1. ساختارِ فاصله‌گذاری (ZWNJ در برابرِ
     * فاصله) عمداً دست‌نخورده می‌ماند؛ آن تفاوت مالِ Tier 2 است.
     *
     * جفتِ SQLیِ این تابع ‎sql_expr()‎ است و باید همیشه *همان* نگاشت را
     * اعمال کند؛ اگر یکی عوض شود و دیگری نه، دو طرفِ مقایسه از هم
     * واگرا می‌شوند و سرچ بی‌صدا نتیجه گم می‌کند.
     */
    public static function normalize(string $text): string {
        $text = strtr($text, self::CHAR_MAP);
        $text = str_replace(self::DIACRITICS, '', $text);
        $text = (string) preg_replace('/[ \t\r\n]+/u', ' ', $text);

        return trim($text);
    }

    /**
     * Tier 2 — همان عبارت، با فاصله‌گذاریِ متفاوت. چون نمی‌دانیم عنوانِ
     * محصول با فاصله نوشته شده یا ZWNJ یا چسبیده، هر سه شکل از رویِ
     * ورودی ساخته می‌شود.
     *
     * @return string[]
     */
    public static function joining_variants(string $normalized): array {
        if ('' === $normalized || false === strpos($normalized, self::ZWNJ) && false === strpos($normalized, ' ')) {
            return [];
        }

        $spaced = self::collapse_spaces(str_replace(self::ZWNJ, ' ', $normalized));
        $joined = str_replace([self::ZWNJ, ' '], '', $normalized);
        $zwnj_joined = (string) preg_replace('/ +/u', self::ZWNJ, $normalized);

        return self::unique_non_empty([$spaced, $joined, $zwnj_joined], $normalized);
    }

    /**
     * Tier 3 — رقم↔حرف، فقط وقتی بلافاصله قبل از یک پسوندِ شناخته‌شده
     * («بعدی»، «محوره») بیاید؛ وگرنه هر عددی در هر متنی («۱۲۰۰۰۰ تومان»)
     * تبدیل می‌شد و نتیجه بی‌ربط می‌داد.
     *
     * @return string[]
     */
    public static function numeric_variants(string $normalized): array {
        $spaced = self::collapse_spaces(str_replace(self::ZWNJ, ' ', $normalized));
        $suffix_pattern = implode('|', array_map('preg_quote', self::NUMERIC_SUFFIXES));

        $variants = [];

        // رقم → حرف: «3 بعدی» / «3بعدی» → «سه بعدی»
        $variants[] = preg_replace_callback(
            '/(\d+)\s*(' . $suffix_pattern . ')/u',
            static function (array $m): string {
                $n = (int) $m[1];

                return isset(self::DIGIT_TO_WORD[$n]) ? self::DIGIT_TO_WORD[$n] . ' ' . $m[2] : $m[0];
            },
            $spaced
        );

        // حرف → رقم: «سه بعدی» / «سه‌بعدی» → «3 بعدی»
        $word_to_digit = array_flip(self::DIGIT_TO_WORD);
        $word_pattern = implode('|', array_map('preg_quote', array_keys($word_to_digit)));
        $variants[] = preg_replace_callback(
            '/(' . $word_pattern . ')\s*(' . $suffix_pattern . ')/u',
            static function (array $m) use ($word_to_digit): string {
                return $word_to_digit[$m[1]] . ' ' . $m[2];
            },
            $spaced
        );

        // نشانه‌گذاریِ لاتین: «3D» → «سه بعدی»
        $variants[] = preg_replace_callback(
            '/(\d+)\s*[dD]\b/u',
            static function (array $m): string {
                $n = (int) $m[1];

                return isset(self::DIGIT_TO_WORD[$n]) ? self::DIGIT_TO_WORD[$n] . ' بعدی' : $m[0];
            },
            $spaced
        );

        return self::unique_non_empty($variants, $normalized);
    }

    /**
     * Tier 4 — مترادفِ دستیِ ادمین. هر جفت دوطرفه است: هرکدام از دو طرف
     * در عبارت دیده شود، طرفِ دیگر هم به‌عنوانِ وریانت اضافه می‌شود.
     *
     * @param array<int,array{0:string,1:string}> $pairs جفت‌های *ازقبل‌نرمال‌شده*
     * @return string[]
     */
    public static function synonym_variants(string $normalized, array $pairs): array {
        $variants = [];

        foreach ($pairs as $pair) {
            $a = trim((string) ($pair[0] ?? ''));
            $b = trim((string) ($pair[1] ?? ''));

            if ('' === $a || '' === $b) {
                continue;
            }

            if (self::contains($normalized, $a)) {
                $variants[] = self::collapse_spaces(str_ireplace($a, $b, $normalized));
            } elseif (self::contains($normalized, $b)) {
                $variants[] = self::collapse_spaces(str_ireplace($b, $a, $normalized));
            }
        }

        return self::unique_non_empty($variants, $normalized);
    }

    /**
     * هر چهار Tier با هم، مرتب و بدونِ تکرار — چیزی که ‎Search_Query‎
     * مستقیم برایِ ساختنِ WHERE/ORDER BY مصرف می‌کند.
     *
     * @param array<int,array{0:string,1:string}> $synonym_pairs
     * @return array<int,array{tier:int,text:string}>
     */
    public static function variants(string $raw_query, array $synonym_pairs = []): array {
        $normalized = self::normalize($raw_query);

        if ('' === $normalized) {
            return [];
        }

        $by_tier = [1 => [$normalized]];

        $joining = self::joining_variants($normalized);
        if ($joining) {
            $by_tier[2] = $joining;
        }

        $numeric = self::numeric_variants($normalized);
        if ($numeric) {
            $by_tier[3] = $numeric;
        }

        $synonyms = self::synonym_variants($normalized, $synonym_pairs);
        if ($synonyms) {
            $by_tier[4] = $synonyms;
        }

        $seen = [];
        $result = [];

        foreach ($by_tier as $tier => $texts) {
            foreach ($texts as $text) {
                $key = self::lower($text);

                if (isset($seen[$key])) {
                    continue;
                }

                $seen[$key] = true;
                $result[] = ['tier' => $tier, 'text' => $text];

                if (count($result) >= self::MAX_VARIANTS) {
                    return $result;
                }
            }
        }

        return $result;
    }

    /* =====================================================================
     * mbstring، اگر بود
     *
     * افزونه نباید رویِ میزبانی که ‎mbstring‎ ندارد کشنده شود. هر سه
     * کاربردِ اینجا جایگزینِ امن دارند:
     *
     *   • کوچک‌کردن فقط برایِ کلیدِ یکتاسازی است و تنها رویِ حروفِ لاتین
     *     اثر دارد — ‎strtolower‎ی بایتی همان کار را می‌کند و به بایت‌هایِ
     *     UTF-8ی فارسی (که همه ≥ 0x80 اند) دست نمی‌زند.
     *   • جست‌وجویِ زیررشته رویِ UTF-8 بایتی هم درست است، چون کدگذاری
     *     خودهمگام است و یک دنبالهٔ معتبر نمی‌تواند وسطِ دنبالهٔ دیگری
     *     تصادفاً پیدا شود.
     *   • شمارشِ طول باید نویسه‌ای بماند، نه بایتی — وگرنه یک عبارتِ
     *     کوتاهِ فارسی الکی از سقف رد می‌شود. ‎preg_match_all‎ با پرچمِ
     *     ‎u‎ همان شمارشِ نویسه‌ای را بدونِ ‎mbstring‎ می‌دهد.
     * =================================================================== */

    public static function lower(string $text): string {
        return function_exists('mb_strtolower') ? mb_strtolower($text) : strtolower($text);
    }

    public static function contains(string $haystack, string $needle): bool {
        return function_exists('mb_stripos')
            ? false !== mb_stripos($haystack, $needle)
            : false !== stripos($haystack, $needle);
    }

    public static function length(string $text): int {
        if (function_exists('mb_strlen')) {
            return (int) mb_strlen($text);
        }

        return (int) preg_match_all('/./u', $text);
    }

    private static function collapse_spaces(string $text): string {
        return trim((string) preg_replace('/ +/u', ' ', $text));
    }

    /** @param (string|null)[] $candidates @return string[] */
    private static function unique_non_empty(array $candidates, string $exclude): array {
        $out = [];
        $seen = [];

        foreach ($candidates as $candidate) {
            if (null === $candidate || '' === $candidate || $candidate === $exclude) {
                continue;
            }

            $key = self::lower($candidate);

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $out[] = $candidate;
        }

        return $out;
    }
}
