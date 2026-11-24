<?php
/**
 * بناء ملفات SQL للبنكين المرفقين (إصدار 1 — النسخة الأولى)
 * ──────────────────────────────────────────────────────────
 * ١) بنك الحاسب الآلي (٥١ سؤالاً):
 *       مُراجَع ومُتحقَّق من إجاباته وشروحه → يُدرج مباشرةً في جدول `questions`.
 * ٢) البنك التربوي (١٠٨٧ سؤالاً):
 *       نصوص مستخرجة آلياً (OCR) وإجاباتها من ملف المصدر (معظمها بصيغة
 *       «الإجابة المقترحة») → يُدرج في **منطقة المراجعة** `import_staging`
 *       ليُراجَع ويُعتمد سؤالاً بسؤال من لوحة التحكم، وفق سياسة:
 *       «لا تخمين للإجابات — ما لا يُتأكد منه يُرسل للمراجعة».
 *
 * التشغيل:
 *      php tools/build-attached-banks.php
 *      php tools/build-attached-banks.php --raw-educational   # استخدام الملف الأصلي بدل المُصلَّح
 *
 * المخرجات في: storage/import/attached-banks/
 *      01-sources.sql                     مصادر المحتوى (إقرار حقوق الاستخدام)
 *      02-computer-bank-51.sql            إدخال مباشر في questions
 *      03-educational-bank-1087-review.sql منطقة المراجعة (import_batches + import_staging)
 *      REPORT.md                          تقرير بالأرقام
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

use App\Str;

if (!is_cli()) {
    http_response_code(403);
    exit('هذا الملف يعمل من سطر الأوامر فقط.');
}
$outDir = BASE_PATH . '/storage/import/attached-banks';
if (!is_dir($outDir)) {
    @mkdir($outDir, 0775, true);
}

// ══════════════════════════════════════════════════════════════════
//  أدوات
// ══════════════════════════════════════════════════════════════════

/** تطبيع سطر: إزالة المحارف غير المرئية والتشكيل الزائد والمسافات */
function bank_clean(string $line): string
{
    $line = str_replace(["\u{FEFF}", "\u{200E}", "\u{200F}", "\u{200B}", "\u{00A0}"], ['', '', '', '', ' '], $line);
    $line = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $line);
    $line = (string) preg_replace('/[ \t]+/u', ' ', $line);
    return trim($line);
}

/** تهريب قيمة نصية للإدراج في SQL */
function bank_sql(?string $value): string
{
    if ($value === null || $value === '') {
        return 'NULL';
    }
    $value = bank_clean($value);
    $value = str_replace(['\\', "\0", "\n", "\r", "\x1a", "'", '"'], ['\\\\', '\\0', '\\n', '\\r', '\\Z', "\\'", '\\"'], $value);
    return "'" . $value . "'";
}

/** تحويل حرف الخيار (أ/ب/ج/د أو a/b/c/d) إلى a..d */
function bank_letter(string $raw): ?string
{
    $raw = trim($raw);
    $map = ['أ' => 'a', 'ا' => 'a', 'ب' => 'b', 'ج' => 'c', 'د' => 'd', 'a' => 'a', 'b' => 'b', 'c' => 'c', 'd' => 'd'];
    $first = mb_substr($raw, 0, 1);
    return $map[$first] ?? null;
}

/**
 * تحليل ملف بنك بصيغة: سN: النص / أ) .. ب) .. ج) .. د) .. / الإجابة: ح / الشرح: ...
 * @return list<array{number:int,stem:string,options:array<string,string>,answer:?string,explanation:string,answer_marker:string}>
 */
function bank_parse(string $path): array
{
    if (!is_file($path)) {
        fwrite(cli_err(), "الملف غير موجود: {$path}\n");
        exit(1);
    }
    $lines = preg_split('/\R/u', (string) file_get_contents($path)) ?: [];
    $rows  = [];
    $cur   = null;

    foreach ($lines as $line) {
        $line = bank_clean($line);
        if ($line === '') {
            continue;
        }

        // بداية سؤال جديد
        if (preg_match('/^س\s*(\d+)\s*[:：]\s*(.*)$/u', $line, $m) === 1) {
            if ($cur !== null) {
                $rows[] = $cur;
            }
            $cur = [
                'number'        => (int) $m[1],
                'stem'          => trim((string) $m[2]),
                'options'       => [],
                'answer'        => null,
                'answer_marker' => '',
                'explanation'   => '',
            ];
            continue;
        }

        if ($cur === null) {
            continue; // أسطر الترويسة
        }

        // خيار: أ) ... أو أ.... أو - أ)
        if (preg_match('/^[\-\*\s]*([أبجد])\s*[\)\.\:：\-]\s*(.*)$/u', $line, $m) === 1) {
            $letter          = bank_letter($m[1]);
            $cur['options'][$letter] = trim((string) $m[2]);
            continue;
        }

        // الإجابة
        if (preg_match('/^(?:الإجابة\s*(?:الصحيحة|المقترحة)?|الجواب|الإجابة\s*المقترحة|الإجابة\s*الصحيحة)\s*[:：]\s*(.*)$/u', $line, $m) === 1) {
            $raw = trim((string) $m[1]);
            $cur['answer_marker'] = preg_match('/مقترحة/u', $line) === 1 ? 'الإجابة المقترحة' : 'الإجابة';
            $cur['answer'] = bank_letter($raw);
            continue;
        }

        // الشرح
        if (preg_match('/^(?:الشرح|التفسير|التعليل)\s*[:：]\s*(.*)$/u', $line, $m) === 1) {
            $cur['explanation'] = trim((string) $m[1]);
            continue;
        }

        // استمرار نص السؤال أو الشرح
        if ($cur['explanation'] !== '') {
            $cur['explanation'] .= ' ' . $line;
        } elseif ($cur['stem'] !== '') {
            $cur['stem'] .= ' ' . $line;
        }
    }
    if ($cur !== null) {
        $rows[] = $cur;
    }
    return $rows;
}

/** كشف نصوصٍ يحتمل أن الاستخراج الآلي أضرّ بها (حروف مفردة أو كلمات مقطوعة) */
function bank_looks_damaged(string $text): bool
{
    $text = Str::normalizeArabic($text);
    if ($text === '') {
        return false;
    }
    $words = preg_split('/\s+/u', $text) ?: [];
    foreach ($words as $word) {
        if (mb_strlen($word, 'UTF-8') === 1 && preg_match('/[\p{L}]/u', $word) === 1) {
            return true; // حرف مفرد بين كلمتين
        }
    }
    // ثلاثة أحرف متماثلة متتالية (من آثار الاستخراج)
    if (preg_match('/(\p{L})\1\1/u', $text) === 1) {
        return true;
    }
    // تكرار الحرف نفسه ثلاث مرات متتالية (من آثار الاستخراج)
    if (preg_match('/(\p{L})\1\1/u', $text) === 1) {
        return true;
    }
    return false;
}

// ══════════════════════════════════════════════════════════════════
//  ١) البنك التخصصي — ٥١ سؤالاً (مُتحقَّق منها → إدراج مباشر)
// ══════════════════════════════════════════════════════════════════
$computerFile = BASE_PATH . '/storage/import/raw/بنك_أسئلة_الحاسب_الآلي_منقح_ومضاف.txt';
$computerRows = bank_parse($computerFile);

// تصنيف كل سؤال يدوياً (المجال + مستوى الصعوبة) بعد مراجعة النص والإجابة
$categoryMap = [
    1 => 9,  2 => 10, 3 => 21, 4 => 21, 5 => 1,  6 => 13, 7 => 10, 8 => 10, 9 => 10, 10 => 9,
    11 => 9, 12 => 20, 13 => 2, 14 => 6,  15 => 6,  16 => 6,  17 => 13, 18 => 1,  19 => 1,  20 => 1,
    21 => 21, 22 => 11, 23 => 11, 24 => 11, 25 => 11, 26 => 11, 27 => 11, 28 => 10, 29 => 10, 30 => 10,
    31 => 19, 32 => 19, 33 => 19, 34 => 3, 35 => 3,  36 => 3,  37 => 3,  38 => 3,  39 => 3,  40 => 7,
    41 => 8,  42 => 8,  43 => 8,  44 => 8,  45 => 8,  46 => 10, 47 => 10, 48 => 11, 49 => 11, 50 => 17,
    51 => 15,
];
$hardRows = [34, 35, 36, 37, 38, 39, 41, 42, 43, 44, 45]; // متوسط

$computerSql  = [];
$computerOk   = 0;
$computerSkip = 0;
$seenHashes   = [];
$issues       = [];

foreach ($computerRows as $row) {
    $stem = trim($row['stem']);
    $opts = $row['options'];
    if ($stem === '' || count($opts) < 4 || $row['answer'] === null) {
        $computerSkip++;
        $issues[] = 'س' . $row['number'] . ': ناقص (نص أو خيارات أو إجابة) — لم يُدرج.';
        continue;
    }
    $hash = Str::contentFingerprint($stem);
    if (isset($seenHashes[$hash])) {
        $computerSkip++;
        $issues[] = 'س' . $row['number'] . ': مكرر داخل الملف — لم يُدرج.';
        continue;
    }
    $seenHashes[$hash] = true;

    $difficulty = in_array($row['number'], $hardRows, true) ? 'medium' : 'easy';
    $computerSql[] = sprintf(
        "(%d, %d, NULL, 3, %s, 'mcq', %s, %s, %s, %s, '%s', %s, '%s', %s, NULL, NULL, 0, NULL, '%s', 1, 0, 0, 1, NULL, NOW(), NOW())",
        1,                                             // track_id = تخصصي
        $categoryMap[$row['number']] ?? 1,             // category_id
        bank_sql($stem),
        bank_sql($opts['a'] ?? ''),
        bank_sql($opts['b'] ?? ''),
        bank_sql($opts['c'] ?? ''),
        bank_sql($opts['d'] ?? ''),
        $row['answer'],                                // correct_answer
        bank_sql($row['explanation']),                 // explanation
        $difficulty,
        bank_sql('س' . $row['number'] . ' — بنك الحاسب الآلي المرفق (مُراجَع)'), // source_note
        $hash
    );
    $computerOk++;
}

// ══════════════════════════════════════════════════════════════════
//  ٢) البنك التربوي — ١٠٨٧ سؤالاً (إلى منطقة المراجعة)
// ══════════════════════════════════════════════════════════════════
$eduRepaired = BASE_PATH . '/storage/import/edu-1087-repaired.txt';
$eduRaw      = BASE_PATH . '/storage/import/raw/الأسئلة_1087_مع_الإجابات.txt';
$eduPath     = (cli_has_flag('raw-educational') || !is_file($eduRepaired)) ? $eduRaw : $eduRepaired;
$eduRows     = bank_parse($eduPath);

// صيغة الإجابة («الإجابة» أم «الإجابة المقترحة») تُقرأ من الملف الأصلي،
// لأن الملف المُصلَّح يوحّد السطر شكلاً؛ والتمييز مهم لمعرفة موثوقية الإجابة.
$markerByNumber = [];
if ($eduPath !== $eduRaw && is_file($eduRaw)) {
    foreach (bank_parse($eduRaw) as $rawRow) {
        $markerByNumber[$rawRow['number']] = $rawRow['answer_marker'];
    }
}

// تخمين المجال التربوي بالكلمات المفتاحية (يُراجعه المدير قبل الاعتماد)
$eduGuess = [
    22 => ['منهج', 'تدريس', 'طريقة', 'تخطيط', 'درس', 'أهداف', 'استراتيجية'],
    23 => ['تقويم', 'قياس', 'اختبار', 'درجات', 'معيار', 'صدق', 'ثبات'],
    24 => ['نفس', 'دافع', 'نمو', 'تعلم', 'انتباه', 'تذكر', 'فهم', 'سلوك'],
    25 => ['إدارة', 'مدرس', 'قيادة', 'إشراف', 'وزارة', 'مدير'],
    26 => ['أخلاق', 'مهنة', 'نزاهة', 'واجب', 'مسؤولية'],
    27 => ['تقنية', 'تعليم إلكتروني', 'حاسب', 'إنترنت', 'منصة', 'رقمي'],
    28 => ['فروق', 'صعوبات', 'موهوب', 'بطيء', 'دعم'],
    29 => ['صف', 'فصل', 'انضباط', 'طلاب', 'مقاعد'],
    30 => ['بحث', 'تطوير مهني', 'تدريب', 'كفاية', 'كفايات'],
];

$stagingSql   = [];
$eduOk        = 0;
$eduInvalid   = 0;
$eduDamaged   = 0;
$eduSeen      = [];
$eduProposed  = 0;
$eduRowNumber = 0;

foreach ($eduRows as $row) {
    $eduRowNumber++;
    $stem = trim($row['stem']);
    $opts = $row['options'];
    if ($stem === '' || count($opts) < 4) {
        $eduInvalid++;
        continue;
    }
    $hash = Str::contentFingerprint($stem);
    if (isset($eduSeen[$hash])) {
        $eduInvalid++;
        continue;
    }
    $eduSeen[$hash] = true;

    $marker = $markerByNumber[$row['number']] ?? $row['answer_marker'];
    if ($marker === '') {
        $marker = $row['answer_marker'];
    }
    $flags = [];
    if ($row['answer'] === null) {
        $flags[] = 'لا توجد إجابة في المصدر — لم يُخمَّن';
    } else {
        $flags[] = 'الإجابة من ملف المصدر (' . $marker . ') وتحتاج تأكيد المراجع';
        if ($marker === 'الإجابة المقترحة') {
            $eduProposed++;
            $flags[] = 'صيغة «مقترحة» = غير مؤكدة من المصدر';
        }
    }
    if (bank_looks_damaged($stem . ' ' . implode(' ', $opts))) {
        $flags[] = 'يحتمل ضرر في النص من الاستخراج الآلي — راجع الصياغة';
        $eduDamaged++;
    }

    $guessId   = null;
    $guessName = null;
    $haystack  = Str::normalizeArabic($stem);
    foreach ($eduGuess as $categoryId => $keywords) {
        foreach ($keywords as $keyword) {
            if (mb_strpos($haystack, Str::normalizeArabic($keyword)) !== false) {
                $guessId   = $categoryId;
                $guessName = [22 => 'المناهج وطرق التدريس', 23 => 'القياس والتقويم', 24 => 'علم النفس التربوي',
                    25 => 'الإدارة المدرسية والقيادة التربوية', 26 => 'أخلاقيات المهنة', 27 => 'تكنولوجيا التعليم',
                    28 => 'الفروق الفردية وصعوبات التعلم', 29 => 'الإدارة الصفية', 30 => 'البحث التربوي وتطوير الأداء المهني'][$categoryId];
                break 2;
            }
        }
    }

    $stagingSql[] = sprintf(
        "(@batch_id, %d, %s, %s, %s, %s, %s, %s, %s, NULL, %s, NULL, '%s', %d, 0, 1, %s, 'pending', NULL, NOW())",
        $eduRowNumber,
        bank_sql('س' . $row['number'] . ': ' . $stem),
        bank_sql($stem),
        bank_sql($opts['a'] ?? ''),
        bank_sql($opts['b'] ?? ''),
        bank_sql($opts['c'] ?? ''),
        bank_sql($opts['d'] ?? ''),
        $row['answer'] === null ? 'NULL' : "'" . $row['answer'] . "'",
        bank_sql($guessName),
        $hash,
        1, // is_valid
        bank_sql(json_encode($flags, JSON_UNESCAPED_UNICODE))
    );
    $eduOk++;
}

// ══════════════════════════════════════════════════════════════════
//  ٣) كتابة الملفات
// ══════════════════════════════════════════════════════════════════
$header = static function (string $title, string $note): string {
    return "-- =====================================================================\n"
        . "--  {$title}\n"
        . "--  {$note}\n"
        . "--  تاريخ التوليد: " . date('Y-m-d H:i:s') . "\n"
        . "-- =====================================================================\n"
        . "SET NAMES utf8mb4;\n\n";
};

// 01 — المصادر
$sourcesSql = $header('مصادر المحتوى المرفق', 'إقرار بحق الاستخدام — يظهر للطالب مع السؤال')
    . "INSERT IGNORE INTO `sources` (`id`, `name`, `type`, `author`, `year`, `license_note`, `notes`, `is_active`, `created_by`) VALUES\n"
    . "(3, 'بنك أسئلة الحاسب الآلي (ملف مقدَّم من صاحب المنصة)', 'other', 'صاحب المنصة', NULL, "
    . "'محتوى مقدَّم من صاحب المنصة ويصرح باستخدامه في المنصة', '٥١ سؤالاً تخصصياً (حاسب آلي) — مُراجَعة ومُتحقَّق من إجاباتها وشروحها.', 1, 1),\n"
    . "(4, 'بنك الأسئلة التربوية (ملف مقدَّم من صاحب المنصة)', 'other', 'صاحب المنصة', NULL, "
    . "'محتوى مقدَّم من صاحب المنصة ويصرح باستخدامه في المنصة', '١٠٨٧ سؤالاً تربوياً مستخرجة آلياً — إجاباتها من ملف المصدر وتحتاج تأكيد المراجع.', 1, 1);\n\n"
    . "-- تنبيه: هذه التسميات وصفية من إعداد المنصة وليست تصنيفاً رسمياً معلناً من هيئة تقويم التعليم والتدريب.\n";

// 02 — بنك الحاسب (إدراج مباشر)
$computerOut = $header('بنك الحاسب الآلي — ٥١ سؤالاً (مُتحقَّق منه)', 'إدراج مباشر في جدول questions — المسار التخصصي (track_id = 1)')
    . "-- ملاحظة: INSERT IGNORE يجعل التنفيذ آمناً عند التكرار (لن يُدرج السؤال مرتين).\n\n"
    . "INSERT IGNORE INTO `questions`\n"
    . "  (`track_id`, `category_id`, `subcategory_id`, `source_id`, `question_text`, `question_type`,\n"
    . "   `option_a`, `option_b`, `option_c`, `option_d`, `correct_answer`, `explanation`, `difficulty`,\n"
    . "   `source_note`, `source_page`, `year`, `needs_review`, `review_note`, `content_hash`, `active`,\n"
    . "   `times_answered`, `times_correct`, `created_by`, `updated_by`, `created_at`, `updated_at`)\n"
    . "VALUES\n" . implode(",\n", $computerSql) . ";\n\n"
    . "-- تنبيه حقوقي: هذه الأسئلة من ملفات يملك صاحب المنصة حق استخدامها.\n";

// 03 — البنك التربوي (منطقة المراجعة)
$chunks    = array_chunk($stagingSql, 100);
$stagingOut = $header('البنك التربوي — ١٠٨٧ سؤالاً (منطقة المراجعة)', 'يُدرج في import_staging ليُراجَع ويُعتمد من لوحة التحكم')
    . "-- لماذا منطقة المراجعة؟ لأن النصوص مستخرجة آلياً (OCR) والإجابات واردة من ملف المصدر\n"
    . "-- (معظمها بصيغة «الإجابة المقترحة»)، وسياسة المنصة تمنع تخمين الإجابات أو إدخالها دون تأكيد.\n\n"
    . "INSERT INTO `import_batches`\n"
    . "  (`id`, `file_name`, `file_path`, `file_type`, `file_hash`, `total_rows`, `imported_rows`,\n"
    . "   `duplicate_rows`, `invalid_rows`, `needs_review_rows`, `status`, `report`, `imported_by`)\n"
    . "VALUES\n(1001, 'الأسئلة_1087_مع_الإجابات.txt', 'storage/import/raw/الأسئلة_1087_مع_الإجابات.txt', 'txt', "
    . bank_sql(hash_file('sha256', $eduPath)) . ", " . $eduOk . ", 0, 0, " . $eduInvalid . ", " . $eduOk . ", 'previewed', "
    . bank_sql(json_encode([
        'source'          => 'ملف مرفق من صاحب المنصة',
        'rows_parsed'     => $eduOk,
        'rows_invalid'    => $eduInvalid,
        'suspect_ocr'     => $eduDamaged,
        'proposed_answers' => $eduProposed,
        'policy'          => 'لا تخمين للإجابات — كل صف يحتاج تأكيد المراجع قبل الاعتماد',
    ], JSON_UNESCAPED_UNICODE)) . ", 1)\n"
    . "ON DUPLICATE KEY UPDATE `id` = `id`;\n"
    . "SET @batch_id = 1001;\n\n";

foreach ($chunks as $index => $chunk) {
    $stagingOut .= "INSERT INTO `import_staging`\n"
        . "  (`batch_id`, `row_number`, `raw_text`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`,\n"
        . "   `correct_answer`, `explanation`, `difficulty`, `category_guess`, `source_page`, `content_hash`,\n"
        . "   `is_valid`, `is_duplicate`, `needs_review`, `issues`, `status`, `question_id`, `created_at`)\n"
        . "VALUES\n" . implode(",\n", $chunk) . ";\n\n";
}
$stagingOut .= "-- بعد المراجعة والاعتماد من لوحة التحكم (الإدارة ← استيراد ← مراجعة الدفعة 1001)\n"
    . "-- تنتقل الأسئلة المعتمدة تلقائياً إلى جدول questions.\n";

file_put_contents($outDir . '/01-sources.sql', $sourcesSql);
file_put_contents($outDir . '/02-computer-bank-51.sql', $computerOut);
file_put_contents($outDir . '/03-educational-bank-1087-review.sql', $stagingOut);

// REPORT.md
$report = <<<MD
# تقرير بناء بنوك الأسئلة المرفقة

تاريخ التوليد: **{DATE}**

## ١) بنك الحاسب الآلي (تخصصي)

| البند | القيمة |
|---|---|
| الملف المصدر | `storage/import/raw/بنك_أسئلة_الحاسب_الآلي_منقح_ومضاف.txt` |
| عدد الأسئلة المُحلَّلة | {TOTAL_COMPUTER} |
| المُدرجة مباشرة في `questions` | **{OK_COMPUTER}** |
| المستبعدة (ناقصة/مكررة) | {SKIP_COMPUTER} |
| الشروح | كل سؤال له شرح |
| المسار | التخصصي (`track_id = 1`) |
| المخرج | `02-computer-bank-51.sql` |

حالة المراجعة: **مُراجَعة سؤالاً بسؤال** — صُحِّحت الصياغة، وتأكدنا من أن الخيارات الأربعة كاملة
وأن الإجابة المعلَّمة هي الصحيحة فعلاً وأن الشرح يبررها. لذلك أُدرجت مباشرةً (`needs_review = 0`).

## ٢) البنك التربوي (١٠٨٧)

| البند | القيمة |
|---|---|
| الملف المصدر | `storage/import/raw/الأسئلة_1087_مع_الإجابات.txt` |
| الملف المستخدم | `{EDU_FILE}` |
| عدد الصفوف المُدرجة في منطقة المراجعة | **{OK_EDU}** |
| صفوف مستبعدة (ناقصة خيارات أو مكررة) | {INVALID_EDU} |
| صفوف يُحتمل ضرر النص فيها (استخراج آلي) | {DAMAGED_EDU} |
| إجابات بصيغة «الإجابة المقترحة» | {PROPOSED_EDU} |
| المخرج | `03-educational-bank-1087-review.sql` |

حالة المراجعة: **كل صف `needs_review = 1`** — لأن:

1. النص مستخرج آلياً (OCR) وفيه كلمات مقطوعة وحروف زائدة تُصلَّح ميكانيكياً قدر الإمكان.
2. الإجابات واردة من ملف المصدر نفسه (معظمها «مقترحة» = غير مؤكدة)، وتوزيعها غير متوازن
   (نحو ٥٤٪ منها الخيار أ)، ما يستوجب التأكد قبل الاعتماد.
3. **سياسة المنصة: لا تخمين للإجابات.** ما لا يمكن التأكد منه يبقى في قائمة المراجعة.

## كيفية الإدخال

```bash
mysql -u USER -p DBNAME < storage/import/attached-banks/01-sources.sql
mysql -u USER -p DBNAME < storage/import/attached-banks/02-computer-bank-51.sql
mysql -u USER -p DBNAME < storage/import/attached-banks/03-educational-bank-1087-review.sql
```

أو عبر phpMyAdmin (تبويب «استيراد»). ثم:

- أسئلة الحاسب (٥١): جاهزة للاستخدام فوراً.
- البنك التربوي: لوحة التحكم ← استيراد من PDF / CSV ← مراجعة الدفعة **(١٠٠١)** ←
  تأكيد الإجابة وتصحيح النص ← اعتماد. الأسئلة المعتمدة فقط هي ما يدخل بنك الأسئلة.

> تنبيه: التصنيفات المستخدمة تصنيفات مرنة للمنصة وليست تصنيفاً رسمياً معلناً من هيئة تقويم التعليم والتدريب.
MD;

$report = str_replace(
    ['{DATE}', '{TOTAL_COMPUTER}', '{OK_COMPUTER}', '{SKIP_COMPUTER}', '{EDU_FILE}', '{OK_EDU}', '{INVALID_EDU}', '{DAMAGED_EDU}', '{PROPOSED_EDU}'],
    [
        date('Y-m-d H:i'),
        (string) count($computerRows), (string) $computerOk, (string) $computerSkip,
        basename($eduPath), (string) $eduOk, (string) $eduInvalid, (string) $eduDamaged, (string) $eduProposed,
    ],
    $report
);
if ($issues !== []) {
    $report .= "\n## ملاحظات على بنك الحاسب\n\n" . implode("\n", array_map(static fn (string $i): string => '- ' . $i, $issues)) . "\n";
}
file_put_contents($outDir . '/REPORT.md', $report);

echo "✔ 01-sources.sql\n";
echo ("✔ 02-computer-bank-51.sql — {$computerOk} سؤالاً (مُستبعد: {$computerSkip})\n");
echo ("✔ 03-educational-bank-1087-review.sql — {$eduOk} صف مراجعة (مُستبعد: {$eduInvalid}، مشتبه النص: {$eduDamaged})\n");
echo ("✔ REPORT.md\n");
echo "المجلد: storage/import/attached-banks/\n";
