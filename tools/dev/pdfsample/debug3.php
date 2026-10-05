<?php
require_once '/home/user/lokmen-G/src/autoload.php';
$raw = file_get_contents('/home/user/lokmen-G/tools/dev/pdfsample/sample_ar.pdf');
// object boundaries
preg_match_all('/(\d+)\s+(\d+)\s+obj\b/', $raw, $m, PREG_OFFSET_CAPTURE);
$objs = [];
foreach ($m[0] as $i => $match) {
    $objs[] = ['num' => (int)$m[1][$i][0], 'start' => $match[1] + strlen($match[0]), 'next' => $m[0][$i+1][1] ?? strlen($raw)];
}
foreach ($objs as $o) {
    $slice = substr($raw, $o['start'], min(4000, $o['next'] - $o['start']));
    if (str_contains($slice, 'beginbfchar') || str_contains($slice, 'begincodespacerange')) {
        echo "=== CMap object {$o['num']} ===\n" . substr($slice, 0, 700) . "\n\n";
    }
    if (str_contains($slice, 'BT') && (str_contains($slice, 'Tj') || str_contains($slice, 'TJ'))) {
        echo "=== content object {$o['num']} (first 400) ===\n" . substr($slice, 0, 400) . "\n\n";
    }
}
// show font dict
foreach ($objs as $o) {
    $slice = substr($raw, $o['start'], min(1500, $o['next'] - $o['start']));
    if (str_contains($slice, '/Type0')) {
        echo "=== font dict object {$o['num']} ===\n" . substr($slice, 0, 700) . "\n";
        break;
    }
}
