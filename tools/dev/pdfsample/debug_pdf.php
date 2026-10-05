<?php
$raw = file_get_contents('/home/user/lokmen-G/tools/dev/pdfsample/sample_ar.pdf');
echo "size=" . strlen($raw) . "\n";
echo "fonts: " . substr_count($raw, '/Type0') . " type0, " . substr_count($raw, '/TrueType') . " truetype, " . substr_count($raw, '/ToUnicode') . " tounicode\n";
if (preg_match_all('/\/ToUnicode\s+(\d+)\s+(\d+)\s+R/', $raw, $m)) {
    echo "ToUnicode refs: " . implode(',', array_unique($m[1])) . "\n";
}
// dump any stream containing codespace
$off = 0; $n = 0;
while (($pos = strpos($raw, 'stream', $off)) !== false) {
    $end = strpos($raw, 'endstream', $pos);
    if ($end === false) break;
    $data = substr($raw, $pos + 6, $end - $pos - 6);
    $dec = @gzuncompress(ltrim($data, "\r\n"));
    if (is_string($dec) && (str_contains($dec, 'beginbfchar') || str_contains($dec, 'begincodespace') || str_contains($dec, 'beginbfrange'))) {
        $n++;
        echo "--- cmap-ish stream #$n ---\n" . substr($dec, 0, 600) . "\n";
        if ($n >= 2) break;
    }
    $off = $end + 9;
}
