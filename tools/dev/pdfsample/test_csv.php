<?php
require_once '/home/user/lokmen-G/src/autoload.php';
$csv = "question,option_a,option_b,option_c,option_d,correct_answer,explanation\n"
     . "\"ما هي عاصمة المملكة العربية السعودية؟\",الرياض,جدة,الدمام,أبها,A,\"الرياض هي العاصمة\"\n"
     . "\"كم عدد مناطق المملكة؟\",13,12,14,15,1,\n"
     . "\"ما هو أكبر كوكب في المجموعة الشمسية؟\",الأرض,المشتري,المريخ,الزهرة,ب,\n";
$rows = App\QuestionImporter::parseCsv($csv);
echo "csv rows: " . count($rows) . "\n";
foreach ($rows as $row) {
    echo "Q: {$row['question_text']} | a={$row['option_a']} | answer=" . var_export($row['correct_answer'], true)
       . " valid={$row['is_valid']} review={$row['needs_review']} issues=" . implode(', ', $row['issues']) . "\n";
}
$json = json_encode([
    ['question' => 'ما هو ناتج ٧ × ٨؟', 'options' => ['56', '48', '64', '72'], 'correct' => 'أ', 'explanation' => '٥٦'],
    ['question' => 'أي مما يلي وحدة قياس الكتلة؟', 'options' => ['المتر', 'الكيلوجرام', 'الثانية', 'اللتر'], 'answer' => 'b'],
], JSON_UNESCAPED_UNICODE);
$rows = App\QuestionImporter::parseJson($json);
echo "json rows: " . count($rows) . "\n";
foreach ($rows as $row) {
    echo "Q: {$row['question_text']} | a={$row['option_a']} | answer=" . var_export($row['correct_answer'], true)
       . " valid={$row['is_valid']} review={$row['needs_review']}\n";
}
