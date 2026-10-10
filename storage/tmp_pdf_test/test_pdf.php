<?php
require_once '/home/user/lokmen-G/src/autoload.php';
$files = ['/home/user/lokmen-G/storage/tmp_pdf_test/sample_en.pdf' => 'EN', '/home/user/lokmen-G/storage/tmp_pdf_test/sample_ar.pdf' => 'AR'];
foreach ($files as $file => $label) {
    $r = App\PdfExtractor::extractText($file);
    echo "=== $label ===\n";
    echo "ok=" . var_export($r['ok'], true) . " streams=" . $r['streams'] . " mapped=" . var_export($r['mapped'], true) . " pages=" . $r['pages'] . "\n";
    if ($r['error'] !== '') echo "ERROR: {$r['error']}\n";
    if ($r['warning'] !== '') echo "WARNING: {$r['warning']}\n";
    echo "---- text ----\n" . substr($r['text'], 0, 800) . "\n\n";
}
