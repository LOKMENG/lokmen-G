<?php
require_once '/home/user/lokmen-G/src/autoload.php';
$cls = new ReflectionClass(App\PdfExtractor::class);
$decodeStreams = $cls->getMethod('decodeStreams'); $decodeStreams->setAccessible(true);
$buildMap = $cls->getMethod('buildUnicodeMap'); $buildMap->setAccessible(true);
$extract = $cls->getMethod('extractFromContent'); $extract->setAccessible(true);
$raw = file_get_contents('/home/user/lokmen-G/tools/dev/pdfsample/sample_ar.pdf');
$streams = $decodeStreams->invoke(null, $raw);
echo "streams=" . count($streams) . "\n";
[$map, $width] = $buildMap->invoke(null, $streams);
echo "map=" . count($map) . " width=" . $width . "\n";
foreach ([0x055a, 0x000c] as $c) { echo sprintf("map[%04X]=%s\n", $c, $map[$c] ?? '(none)'); }
foreach ($streams as $i => $s) {
    if (str_contains($s['data'], 'TJ')) {
        echo "--- content stream #$i ---\n" . substr($s['data'], 0, 160) . "\n";
        echo "extract: [" . $extract->invoke(null, $s['data'], $map, $width) . "]\n";
        break;
    }
}
