<?php
/**
 * ترميم النصوص العربية المستخرجة ضوئياً (OCR) قبل استيرادها.
 *
 * يُعالج الأنماط الشائعة في ملفات التجميعات الممسوحة:
 *   1) «ال» المشوّهة: األ / اال / أال / اآل / اإل / ال ا
 *   2) حرف «ه» الزائد داخل الكلمة: الطهالب ← الطالب، كتابهة ← كتابة
 *   3) المسافات المبعثرة داخل الكلمة: التدري س ← التدريس، مواض ع ← مواضع
 *   4) توحيد سطر الإجابة إلى «الإجابة: حرف»
 *
 * المنهج: قواعد حرفية ثابتة + إصلاح يعتمد على تكرار الكلمات داخل النص نفسه
 * (الكلمة الصحيحة تتكرر في الملف أكثر من نظيرتها المشوّهة).
 *
 * ⚠ تنبيه أداء: الأداة بطيئة على الملفات الكبيرة (تعتمد على similar_text لكل كلمة).
 *   ملف ٤٥٠ ك.ب قد يستغرق أكثر من ١٥ دقيقة. للملفات الكبيرة:
 *     - قسّم الملف إلى أجزاء (مثلاً ١٠٠ سؤال لكل جزء) وشغّل الأداة على كل جزء، أو
 *     - استخدم أدوات خارجية أسرع، ثم شغّل هذه الأداة على الأجزاء المشكوك فيها فقط.
 *
 * الاستخدام:
 *   php tools/ocr-repair.php --in=ملف.txt --out=ملف_مرمم.txt [--report]
 *
 * ملاحظة: الأداة ترمّم النص آلياً ولا تُخمّن أي إجابة؛ أسئلة الإجابة تبقى
 * كما في المصدر وتخضع لمراجعة بشرية قبل الإدراج (قائمة المراجعة).
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

use App\Str;

if (!is_cli()) {
    http_response_code(403);
    exit('هذا الملف يعمل من سطر الأوامر فقط.');
}

$in  = (string) cli_option('in', '');
$out = (string) cli_option('out', '');
$showReport = cli_has_flag('report');

if ($in === '') {
    fwrite(cli_err(), "حدد ملف الإدخال: php tools/ocr-repair.php --in=file.txt --out=repaired.txt\n");
    exit(64);
}
if (!is_file($in)) {
    fwrite(cli_err(), "الملف غير موجود: {$in}\n");
    exit(1);
}

$text = (string) file_get_contents($in);
$stats = [];
$repaired = OcrRepair::repair($text, $stats);

if ($out !== '') {
    $dir = dirname($out);
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        fwrite(cli_err(), "تعذّر إنشاء المجلد: {$dir}\n");
        exit(1);
    }
    file_put_contents($out, $repaired);
    fwrite(cli_out(), "✔ كُتب الملف المرمَّم: {$out}\n");
} else {
    echo $repaired;
}

if ($showReport || $out !== '') {
    fwrite(cli_out(), "\nتقرير الترميم:\n");
    foreach ($stats as $key => $value) {
        fwrite(cli_out(), sprintf("  • %-34s %d\n", $key, $value));
    }
}

/**
 * مرمّم النصوص العربية المستخرجة ضوئياً.
 */
final class OcrRepair
{
    /** كلمات شائعة التلف في ملفات التجميعات — تُصحَّح مباشرة. */
    private const WORD_FIXES = [
        // «ال» المشوّهة في الكلمات الشائعة
        'االهداف' => 'الأهداف', 'األهداف' => 'الأهداف', 'أالهداف' => 'الأهداف', 'الاهداف' => 'الأهداف',
        'االهداف ' => 'الأهداف ', 'األهداف ' => 'الأهداف ',
        'االفكار' => 'الأفكار', 'األفكار' => 'الأفكار', 'أالفكار' => 'الأفكار',
        'االتية' => 'الآتية', 'اآلتية' => 'الآتية',
        'االنشطة' => 'الأنشطة', 'األنشطة' => 'الأنشطة', 'الانشطة' => 'الأنشطة',
        'االسلوب' => 'الأسلوب', 'األسلوب' => 'الأسلوب', 'الاسلوب' => 'الأسلوب',
        'االستقصا' => 'الاستقصاء', 'االستقصاء' => 'الاستقصاء', 'الاستقصا' => 'الاستقصاء',
        'االجابة' => 'الإجابة', 'اإلجابة' => 'الإجابة',
        'اليجابية' => 'الإيجابية', 'إاليجابية' => 'الإيجابية',
        'الحتياجات' => 'الاحتياجات',
        'الصلحيات' => 'الصلاحيات', 'الصلاحيه' => 'الصلاحية',
        'اكلاهما' => 'كلاهما', 'اكلهما' => 'كلاهما',
        'اولى' => 'الأولى', 'الاولي' => 'الأولى',
        // كلمات بـ«ه» زائدة متكررة
        'الطهالب' => 'الطالب', 'الطالهب' => 'الطالب', 'الطالب' => 'الطالب', 'الطلالب' => 'الطالب',
        'الطهالبة' => 'الطالبة', 'الطالهبة' => 'الطالبة',
        'كتابهة' => 'كتابة', 'كتابته' => 'كتابته',
        'المهعلم' => 'المعلم', 'المعلهم' => 'المعلم',
        'المهدرسة' => 'المدرسة', 'المدر هسة' => 'المدرسة',
        'زمالئه' => 'زملائه', 'زامله' => 'زامله',
        'السهؤال' => 'السؤال', 'السهؤال' => 'السؤال',
        'الهحصة' => 'الحصة', 'الحصهة' => 'الحصة',
        'مها' => 'ما', 'مهها' => 'ما', 'قهام' => 'قام', 'قههام' => 'قام',
        'يهوم' => 'يقوم', 'يهقوم' => 'يقوم',
        'هذا' => 'هذا', 'هذة' => 'هذه', 'هذة' => 'هذه',
        // مسافات داخلية شائعة
        'التدري س' => 'التدريس', 'التد ريس' => 'التدريس',
        'الرئ يسة' => 'الرئيسة', 'الر ئيسة' => 'الرئيسة',
        'كيف ية' => 'كيفية', 'كيف يه' => 'كيفية',
        'مواض ع' => 'مواضع',
        'ل ك' => 'لك',
    ];

    /**
     * يرمّم النص ويجمع إحصاءات.
     * @param array<string,int> $stats
     */
    public static function repair(string $text, array &$stats = []): string
    {
        $stats = ['إجمالي_الحروف_قبل' => mb_strlen($text, 'UTF-8')];

        // 0) تطبيع المسافات البيضاء ونهايات الأسطر
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\n{3,}/u', "\n\n", $text) ?? $text;

        // 1) «ال» المشوّهة — قواعد حرفية عامة
        $before = $text;
        $pairs = [
            'األ' => 'الأ', 'اآل' => 'الآ', 'اإل' => 'الإ', 'أال' => 'الأ',
            'إال' => 'الإ', 'آال' => 'الآ',
        ];
        foreach ($pairs as $bad => $good) {
            $text = str_replace($bad, $good, $text);
        }
        $text = str_replace('ال ا', 'ال', $text);   // «ال اصلحيات» ← «الصلحيات»
        $text = str_replace('اال', 'ال', $text);    // «االتية» ← «التية» ثم تُصحَّح بالقاموس
        $stats['تصحيح_ال_المشوهة'] = self::diffCount($before, $text);

        // 2) الحرف «ه» المزدوج (قههام ← قام)
        $before = $text;
        $text = (string) preg_replace('/هه/u', '', $text);
        $stats['حذف_ه_المزدوجة'] = self::diffCount($before, $text);

        // 3) قاموس الكلمات الشائعة
        $before = $text;
        foreach (self::WORD_FIXES as $bad => $good) {
            if ($bad === $good) {
                continue;
            }
            $text = str_replace($bad, $good, $text);
        }
        $stats['قاموس_الكلمات'] = self::diffCount($before, $text);

        // 4) إصلاح بالتكرار: «ه» الزائدة والمسافات الداخلية
        $freq = self::wordFrequency($text);
        $before = $text;
        $text = self::repairByFrequency($text, $freq, $stats);
        $stats['إصلاح_بالتكرار'] = self::diffCount($before, $text);

        // 5) توحيد سطر الإجابة
        $before = $text;
        $text = (string) preg_replace(
            '/^\s*(?:الإجابة|الاجابة|الجواب|الإجابة الصحيحة|الاجابة الصحيحة)(?:\s*المقترحة)?\s*[:：\-]?\s*([أبجد])\b.*$/mu',
            'الإجابة: $1',
            $text
        );
        $stats['توحيد_سطر_الإجابة'] = self::diffCount($before, $text);

        // 6) تنظيف أخير للمسافات
        $text = (string) preg_replace('/[ ]{2,}/u', ' ', $text);
        $text = (string) preg_replace('/^\s+|\s+$/u', '', $text);

        $stats['إجمالي_الحروف_بعد'] = mb_strlen($text, 'UTF-8');

        return $text;
    }

    /** @return array<string,int> */
    private static function wordFrequency(string $text): array
    {
        $words = preg_split('/[^\p{Arabic}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $freq = [];
        foreach ($words as $word) {
            if (mb_strlen($word, 'UTF-8') < 3) {
                continue;
            }
            $freq[$word] = ($freq[$word] ?? 0) + 1;
        }
        return $freq;
    }

    /**
     * يحاول إصلاح كل كلمة: حذف «ه» زائدة، أو ضم مسافة داخلية،
     * بشرط أن تكون الصيغة الناتجة أكثر تكراراً في النص نفسه.
     * @param array<string,int> $freq @param array<string,int> $stats
     */
    private static function repairByFrequency(string $text, array $freq, array &$stats): string
    {
        $fixedH = 0;
        $fixedSpace = 0;
        $cache = [];

        $callback = static function (array $matches) use ($freq, &$cache, &$fixedH, &$fixedSpace): string {
            $word = $matches[0];
            if (isset($cache[$word])) {
                return $cache[$word];
            }
            $current = $freq[$word] ?? 1;
            $best = $word;
            $bestScore = $current;

            // أ) احذف كل «ه» غير أولية وجرّب
            $len = mb_strlen($word, 'UTF-8');
            for ($i = 1; $i < $len; $i++) {
                if (mb_substr($word, $i, 1, 'UTF-8') !== 'ه') {
                    continue;
                }
                $candidate = mb_substr($word, 0, $i, 'UTF-8') . mb_substr($word, $i + 1, null, 'UTF-8');
                if (mb_strlen($candidate, 'UTF-8') < 3) {
                    continue;
                }
                $score = $freq[$candidate] ?? 0;
                if ($score >= 3 && $score >= $current * 2 && $score > $bestScore) {
                    $best = $candidate;
                    $bestScore = $score;
                    $fixedH++;
                }
            }

            // ب) الكلمات المقطوعة بمسافة تُعالَج على مستوى السطر لاحقاً
            $cache[$word] = $best;

            return $best;
        };

        $text = (string) preg_replace_callback('/[\p{Arabic}]{3,}/u', $callback, $text);

        // ضم الكلمات المقطوعة: «الرئ يسة» ← «الرئيسة» إن كانت الصيغة الموصولة أكثر تكراراً
        $parts = preg_split('/([\p{Arabic}]{2,})/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
        $result = '';
        $i = 0;
        $count = count($parts);
        while ($i < $count) {
            $chunk = $parts[$i];
            $i++;
            // ابحث عن نمط: كلمة + مسافة + كلمة قصيرة (٢-٤ أحرف) تكملها
            if (
                $i + 2 < $count
                && trim($parts[$i]) === ''
                && preg_match('/^[\p{Arabic}]{2,}$/u', $chunk) === 1
                && preg_match('/^[\p{Arabic}]{2,4}$/u', $parts[$i + 1]) === 1
            ) {
                $joined = $chunk . $parts[$i + 1];
                $scoreJoined = $freq[$joined] ?? 0;
                $scoreA = $freq[$chunk] ?? 0;
                if ($scoreJoined >= 3 && $scoreJoined >= $scoreA) {
                    $result .= $joined;
                    $fixedSpace++;
                    $i += 2;
                    continue;
                }
            }
            $result .= $chunk;
        }

        $stats['حذف_ه_الزائدة'] = $fixedH;
        $stats['ضم_كلمات_مقطوعة'] = $fixedSpace;

        return $result;
    }

    private static function diffCount(string $before, string $after): int
    {
        $diff = mb_strlen($before, 'UTF-8') - mb_strlen($after, 'UTF-8');
        return abs($diff) + (int) (similar_text($before, $after) === 0 ? 1 : 0);
    }
}
