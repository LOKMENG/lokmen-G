<?php
$raw = file_get_contents('/home/user/lokmen-G/tools/dev/pdfsample/sample_ar.pdf');
// decompress the ToUnicode CMap (object 6)
preg_match_all('/(\d+)\s+(\d+)\s+obj\b/', $raw, $m, PREG_OFFSET_CAPTURE);
$objs = [];
foreach ($m[0] as $i => $match) {
    $objs[] = ['num' => (int)$m[1][$i][0], 'start' => $match[1] + strlen($match[0]), 'next' => $m[0][$i+1][1] ?? strlen($raw)];
}
$cmap = '';
foreach ($objs as $o) {
    if ($o['num'] !== 6) continue;
    $slice = substr($raw, $o['start'], $o['next'] - $o['start']);
    $sp = strpos($slice, 'stream');
    $data = ltrim(substr($slice, $sp + 6), "\r\n");
    $end = strpos($data, 'endstream');
    $cmap = substr($data, 0, $end);
}
echo "cmap len=" . strlen($cmap) . "\n";
$map = [];
if (preg_match_all('/beginbfrange(.*?)endbfrange/s', $cmap, $blocks)) {
    foreach ($blocks[1] as $block) {
        if (preg_match_all('/<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>/', $block, $ranges, PREG_SET_ORDER)) {
            foreach ($ranges as $range) {
                $start = hexdec($range[1]); $end = hexdec($range[2]); $dst = hexdec($range[3]);
                if ($end - $start > 65535) continue;
                for ($code = $start; $code <= $end; $code++) { $map[$code] = $dst + ($code - $start); }
            }
        }
    }
}
echo "map size=" . count($map) . "\n";
foreach ([0x055a, 0x000c, 0x0003, 0x0555, 0x056d, 0x0551, 0x0571, 0x056e] as $code) {
    $u = $map[$code] ?? null;
    printf("CID %04X -> U+%04X %s\n", $code, $u ?? 0, $u !== null ? mb_convert_encoding(pack('n', $u), 'UTF-8', 'UTF-16BE') : '(unmapped)');
}
