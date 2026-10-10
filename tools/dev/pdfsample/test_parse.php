<?php
require_once '/home/user/lokmen-G/src/autoload.php';
$base = '/home/user/lokmen-G/tools/dev/pdfsample/';
foreach (['sample_ar.pdf', 'sample_en.pdf'] as $file) {
    $pdf = App\PdfExtractor::extractText($base . $file);
    echo "=== $file : extract ok=" . var_export($pdf['ok'], true) . " ===\n";
    $rows = App\QuestionImporter::parseText($pdf['text']);
    echo "parsed rows: " . count($rows) . "\n";
    foreach ($rows as $i => $row) {
        echo "--- row " . ($i + 1) . " ---\n";
        echo "Q: " . $row['question_text'] . "\n";
        foreach (['a', 'b', 'c', 'd'] as $letter) {
            if (!empty($row['option_' . $letter])) {
                echo "  $letter) " . $row['option_' . $letter] . "\n";
            }
        }
        echo "answer: " . var_export($row['correct_answer'], true) . " valid=" . $row['is_valid'] . " review=" . $row['needs_review'] . "\n";
        if (!empty($row['issues'])) {
            echo "issues: " . implode(' | ', $row['issues']) . "\n";
        }
    }
    echo "\n";
}
// اختبار نص يدوي بصيغة عربية وصيغة مجهولة الإجابة
$manual = "1) ما هو أعلى جبل في العالم؟\nأ) إيفرست\nب) كليمنجارو\nج) مونت بلانك\nد) الألب\nالإجابة: أ\n\n2) كم عدد أيام السنة الهجرية؟\nأ) 354\nب) 365\nالإجابة غير واضحة\n";
$rows = App\QuestionImporter::parseText($manual);
echo "=== manual === rows=" . count($rows) . "\n";
foreach ($rows as $row) {
    echo "Q: {$row['question_text']} | answer=" . var_export($row['correct_answer'], true) . " review={$row['needs_review']} issues=" . implode(' | ', $row['issues']) . "\n";
}
