<?php
require_once '/home/user/lokmen-G/src/autoload.php';
$base = '/home/user/lokmen-G/tools/dev/pdfsample/';
foreach (['sample_en.pdf' => 'EN', 'sample_ar.pdf' => 'AR'] as $name => $label) {
    $r = App\PdfExtractor::extractText($base . $name);
    echo "=== $label (" . $name . ") ===\n";
    echo "ok=" . var_export($r['ok'], true) . " streams=" . $r['streams'] . " mapped=" . var_export($r['mapped'], true) . " pages=" . $r['pages'] . "\n";
    if ($r['error'] !== '') echo "ERROR: {$r['error']}\n";
    if ($r['warning'] !== '') echo "WARNING: {$r['warning']}\n";
    echo "---- text ----\n" . substr($r['text'], 0, 700) . "\n\n";
}
