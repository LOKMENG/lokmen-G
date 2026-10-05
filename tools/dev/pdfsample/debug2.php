<?php
$raw = file_get_contents('/home/user/lokmen-G/tools/dev/pdfsample/sample_ar.pdf');
// اطبع كل التدفقات مع فلاترها
$off = 0; $n = 0;
while (($pos = strpos($raw, 'stream', $off)) !== false) {
    $dictStart = max(0, $pos - 400);
    $dict = substr($raw, $dictStart, $pos - $dictStart);
    $end = strpos($raw, 'endstream', $pos);
    if ($end === false) break;
    $data = substr($raw, $pos + 6, $end - $pos - 6);
    $n++;
    $filter = 'none';
    if (preg_match('/\/Filter\s*(\/[A-Za-z0-9]+)/', $dict, $fm)) $filter = $fm[1];
    $isCmap = str_contains($dict, 'CMap') || str_contains($dict, '/Type0') || str_contains($dict, 'ToUnicode');
    $decLen = -1;
    $dec = @gzuncompress(ltrim($data, "\r\n"));
    if (!is_string($dec)) { $dec = @gzinflate(ltrim($data, "\r\n")); }
    if (is_string($dec)) $decLen = strlen($dec);
    echo "#$n pos=$pos len=" . strlen($data) . " filter=$filter dec=$decLen\n";
    if ($is_string = (is_string($dec) && (str_contains($dec, 'beginbfchar') || str_contains($dec, 'begincodespacerange')))) {
        echo "   >>> CMAP DETECTED\n" . substr($dec, 0, 400) . "\n";
    }
    $off = $end + 9;
    if ($n > 25) break;
}
