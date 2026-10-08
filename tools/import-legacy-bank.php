<?php
/**
 * استيراد بنك أسئلة قديم بصيغة SQL إلى منصة الرخصة المهنية.
 *
 * لماذا هذه الأداة؟
 *   ملفات بنوك الأسئلة الجاهزة (مثل ملف «تجميعات الرخصة المهنية») مكتوبة عادةً بصيغة:
 *       INSERT INTO tracks (name, slug, description, display_order) VALUES (...);
 *       INSERT INTO questions (id, category, source, stem, option_a..d, correct_answer) VALUES (...);
 *   وهذه الصيغة لا تعمل على المنصة:
 *     • أسماء الأعمدة مختلفة (المنصة تستخدم track_id/category_id/source_id/content_hash...).
 *     • أغلب الأسئلة بلا إجابة صحيحة (NULL)، وتخمين الإجابة ممنوع.
 *     • النص المستخرج من PDF كثيراً ما يكون مصاباً بتشويه حروف.
 *
 * ماذا تفعل الأداة؟
 *   1. تحلّل الملف القديم ولا تنفّذه.
 *   2. تصلح الأخطاء الطباعية المؤكّدة آلياً (ترتيب الهمزة، الحروف المفصولة، المسافات).
 *   3. تصنّف كل سؤال على مجالات المنصة آلياً (تصنيف مقترح قابل للتعديل).
 *   4. تكتشف التكرار (تطابق تام + تشابه عالٍ).
 *   5. تُنتج ملفات SQL آمنة + قائمة مراجعة CSV + تقريراً عربياً، ولا تُدخل شيئاً في قاعدة البيانات.
 *
 * الاستخدام (من مجلد المشروع):
 *   php tools/import-legacy-bank.php --file=/path/legacy.sql --out=/path/output
 *
 * خيارات:
 *   --file=...        مسار ملف SQL القديم (مطلوب)
 *   --out=...         مجلد الإخراج (مطلوب) — تُنشأ فيه الملفات
 *   --batch=...       اسم الدُفعة الذي سيظهر في لوحة التحكم (افتراضي: اسم الملف)
 *   --track=2         معرّف المسار للإدخال الفوري (2 = التربوي العام، 1 = التخصصي)
 *   --min-score=2     الحد الأدنى لنقاط التصنيف الآلي (2 = توصيتان على الأقل)
 *   --chunk=100       عدد الصفوف في كل جملة INSERT (لتجاوز حد حجم الطلب في phpMyAdmin)
 *   --split=0         عدد الملفات لتقسيم صفوف المراجعة (0 = ملف واحد)
 *   --no-dedupe       تعطيل كشف التكرار
 *   --quiet           إخراج مختصر
 *
 * ملاحظة: الأداة لا تلمس قاعدة بياناتك إطلاقاً؛ كل ما تنتجه ملفات تراجعها أنت.
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

if (!is_cli()) {
    http_response_code(403);
    exit('هذا الملف يعمل من سطر الأوامر فقط.');
}

use App\LegacyBankImporter;

$quiet = cli_has_flag('quiet');
$file = cli_option('file', '');
$outDir = cli_option('out', '');
$batchName = cli_option('batch', '');
$trackId = (int) cli_option('track', '2');
$minScore = (int) (cli_option('min-score', '2') ?: '2');
$chunk = max(1, (int) (cli_option('chunk', '100') ?: '100'));
$split = max(0, (int) (cli_option('split', '0') ?: '0'));
$dedupe = !cli_has_flag('no-dedupe');

function out_line(string $message, bool $quiet = false): void
{
    if (!$quiet) {
        fwrite(STDOUT, $message . "\n");
    }
}

if ($file === '' || !is_file($file)) {
    fwrite(STDERR, "خطأ: حدّد مسار ملف SQL بصيغة --file=/path/legacy.sql\n");
    exit(1);
}
if ($outDir === '') {
    fwrite(STDERR, "خطأ: حدّد مجلد الإخراج بصيغة --out=/path/output\n");
    exit(1);
}
if (!is_dir($outDir) && !mkdir($outDir, 0775, true) && !is_dir($outDir)) {
    fwrite(STDERR, "خطأ: تعذّر إنشاء مجلد الإخراج: {$outDir}\n");
    exit(1);
}
$outDir = rtrim($outDir, '/\\');

out_line('══════════════════════════════════════════════════════', $quiet);
out_line('  استيراد بنك أسئلة قديم — LegacyBankImporter v' . LegacyBankImporter::VERSION, $quiet);
out_line('══════════════════════════════════════════════════════', $quiet);
out_line('  الملف المصدر: ' . $file, $quiet);

$sql = (string) file_get_contents($file);
out_line('  حجم الملف: ' . number_format(strlen($sql) / 1024, 1) . ' كيلوبايت', $quiet);

$parsed = LegacyBankImporter::parseLegacySql($sql);
out_line('  المسارات المذكورة في الملف: ' . count($parsed['tracks']), $quiet);
out_line('  الأسئلة المقروءة: ' . number_format(count($parsed['questions'])), $quiet);
if ($parsed['questions'] === []) {
    fwrite(STDERR, "خطأ: لم يُعثر على أي جملة INSERT INTO questions ... VALUES في الملف.\n");
    fwrite(STDERR, "تأكد من أن الملف يحتوي الجملة بهذه الصيغة (حتى لو اختلفت أسماء الأعمدة).\n");
    exit(1);
}

$batchName = $batchName !== '' ? $batchName : pathinfo($file, PATHINFO_FILENAME);

out_line('  … جارٍ فحص النصوص والتصنيف', $quiet);
$result = LegacyBankImporter::process($parsed['questions'], [
    'min_score' => $minScore,
    'dedupe'    => $dedupe,
]);
$rows = $result['rows'];
$summary = $result['summary'];

// ------------------------------------------------ الملفات الناتجة
$files = [];

// 1) تصحيح جملة tracks
$tracksFile = $outDir . '/00_tracks_categories.sql';
file_put_contents($tracksFile, LegacyBankImporter::tracksSql());
$files[] = ['00_tracks_categories.sql', 'تصحيح جملة المسارات (tracks) + ضمان مسارَي المنصة'];

// 2) المصادر
$sourcesFile = $outDir . '/10_sources.sql';
$sourcesSql = "-- =====================================================================\n"
    . "--  مصادر الأسئلة كما وردت في الملف القديم\n"
    . "--  أنشأتها أداة الاستيراد تلقائياً من عمود source في ملفك\n"
    . "-- =====================================================================\n"
    . "SET NAMES utf8mb4;\n\n";
foreach (array_keys($summary['by_source']) as $sourceName) {
    $sourcesSql .= 'INSERT INTO `sources` (`name`, `type`, `license_note`, `notes`, `is_active`)'
        . ' SELECT ' . LegacyBankImporter::sqlString($sourceName) . ", 'pdf', "
        . LegacyBankImporter::sqlString('ملف قديم قدّمه مالك المنصة — لم تُراجَع أسئلته بعد. إقرار حق الاستخدام مسؤولية المالك.')
        . ', ' . LegacyBankImporter::sqlString('أُنشئ تلقائياً عند استيراد الملف: ' . basename($file))
        . ', 1 WHERE NOT EXISTS (SELECT 1 FROM `sources` WHERE `name` = '
        . LegacyBankImporter::sqlString($sourceName) . ");\n";
}
file_put_contents($sourcesFile, $sourcesSql);
$files[] = ['10_sources.sql', 'إنشاء المصادر (لا يكرر الموجود)'];

// 3) صفوف المراجعة (import_staging) — مقسّمة إلى ملفات اختيارياً
$rowGroups = [];
if ($split > 1) {
    $size = (int) ceil(count($rows) / $split);
    $rowGroups = array_chunk($rows, max(1, $size));
} else {
    $rowGroups = [$rows];
}
$stagingFiles = [];
foreach ($rowGroups as $index => $group) {
    $name = $split > 1
        ? sprintf('20_staging_part%02d.sql', $index + 1)
        : '20_staging.sql';
    $path = $outDir . '/' . $name;
    $groupSummary = LegacyBankImporter::summarize($group, ['repairs' => $summary['repairs']]);
    $groupBatch = $split > 1 ? $batchName . ' (جزء ' . ($index + 1) . ')' : $batchName;
    file_put_contents($path, LegacyBankImporter::stagingSql($group, $groupSummary, $groupBatch, basename($file), $split <= 1, $chunk));
    $stagingFiles[] = $name;
}
$files[] = [implode(', ', $stagingFiles), 'صفوف منطقة المراجعة (import_staging) — لا تُدخل الأسئلة نفسها'];

// 4) الإدخال الفوري للأسئلة المستوفية
$clean = LegacyBankImporter::cleanRows($rows);
$readyFile = $outDir . '/30_questions_ready.sql';
file_put_contents($readyFile, LegacyBankImporter::promoteSql($rows, $trackId, ['only_clean' => true]));
$files[] = ['30_questions_ready.sql', 'إدخال مباشر للأسئلة المستوفية للشروط (' . count($clean) . ' سؤالاً) — اختياري'];

// 5) قائمة المراجعة
$csvFile = $outDir . '/40_review.csv';
file_put_contents($csvFile, LegacyBankImporter::reviewCsv($rows));
$files[] = ['40_review.csv', 'قائمة المراجعة البشرية (تُفتح في Excel)'];

// 6) التقرير
$reportFile = $outDir . '/REPORT.md';
file_put_contents($reportFile, LegacyBankImporter::reportMarkdown($summary, $rows, [
    'file'  => basename($file),
    'batch' => $batchName,
]));
$files[] = ['REPORT.md', 'التقرير الكامل بالأرقام'];

// 7) ملخص آلي
$jsonFile = $outDir . '/summary.json';
file_put_contents($jsonFile, (string) json_encode([
    'tool'    => 'LegacyBankImporter v' . LegacyBankImporter::VERSION,
    'source'  => basename($file),
    'batch'   => $batchName,
    'track'   => $trackId,
    'summary' => $summary,
    'clean'   => count($clean),
    'files'   => array_column($files, 0),
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
$files[] = ['summary.json', 'ملخص آلي للقراءة البرمجية'];

// ------------------------------------------------ الملخص على الشاشة
out_line('', $quiet);
out_line('  ── النتيجة ────────────────────────────────────────', $quiet);
out_line('  إجمالي الأسئلة          : ' . number_format((int) $summary['total']), $quiet);
out_line('  صالحة داخلياً           : ' . number_format((int) $summary['valid']), $quiet);
out_line('  غير صالحة               : ' . number_format((int) $summary['invalid']), $quiet);
out_line('  تحتاج مراجعة بشرية      : ' . number_format((int) $summary['needs_review']), $quiet);
out_line('  بلا إجابة صحيحة         : ' . number_format((int) $summary['missing_answer']), $quiet);
out_line('  مكررة                   : ' . number_format((int) ($summary['duplicates_exact'] + $summary['duplicates_near'])), $quiet);
out_line('  نص مصاب بتشويه          : ' . number_format((int) $summary['flagged_text']), $quiet);
out_line('  بلا مجال محدّد           : ' . number_format((int) $summary['uncategorized']), $quiet);
out_line('  جاهزة للإدخال الفوري    : ' . number_format(count($clean)), $quiet);
out_line('', $quiet);
out_line('  الملفات الناتجة في: ' . $outDir, $quiet);
foreach ($files as [$name, $description]) {
    out_line('   • ' . $name . '  —  ' . $description, $quiet);
}
out_line('', $quiet);
out_line('  الخطوة التالية: نفّذ 00 ثم 10 ثم 20 في phpMyAdmin، ثم راجع من لوحة التحكم → استيراد الأسئلة.', $quiet);
out_line('  لم تُدخل الأداة أي سؤال في قاعدة بياناتك.', $quiet);
out_line('', $quiet);
exit(0);
