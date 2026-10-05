<?php
declare(strict_types=1);

namespace App;

/**
 * مستخرج نصوص PDF مكتوب بـ PHP نقي (بدون مكتبات خارجية) ليعمل على XAMPP.
 *
 * المنهج:
 *  - يقرأ تدفقات (streams) الملف ويفكّ ضغط FlateDecode بـ gzuncompress.
 *  - يبني خريطة ToUnicode من الـ CMaps المدمجة (ضرورية للعربية في ملفات Identity-H).
 *  - يستخرج النص من عمليات BT/ET و Tj/TJ/'" .
 *
 * القيود (مقصودة ومُعلنة للمستخدم):
 *  - لا يدعم OCR: الملفات الممسوحة ضوئياً (صور) لا يمكن استخراج نص منها — يجب استخراجها
 *    ببرنامج OCR خارجي ثم رفع الملف الناتج (TXT/CSV).
 *  - دقة الاستخراج تعتمد على الملف نفسه؛ لذلك كل صف يُمرّر إلى شاشة مراجعة بشرية قبل الإدخال.
 */
final class PdfExtractor
{
    private const MAX_BYTES = 40 * 1024 * 1024;

    /** هل يمكن الاستخراج (zlib متاح)؟ */
    public static function isAvailable(): bool
    {
        return function_exists('gzuncompress');
    }

    /**
     * استخراج النص من ملف PDF.
     * @return array{ok:bool,text:string,pages:int,streams:int,mapped:bool,warning:string,error:string}
     */
    public static function extractText(string $path): array
    {
        $result = ['ok' => false, 'text' => '', 'pages' => 0, 'streams' => 0, 'mapped' => false, 'warning' => '', 'error' => ''];

        if (!is_file($path) || !is_readable($path)) {
            $result['error'] = 'تعذّر قراءة الملف.';
            return $result;
        }
        $size = (int) filesize($path);
        if ($size <= 0) {
            $result['error'] = 'الملف فارغ.';
            return $result;
        }
        if ($size > self::MAX_BYTES) {
            $result['error'] = 'حجم الملف كبير جداً (الحد ' . (int) (self::MAX_BYTES / 1048576) . ' ميجابايت).';
            return $result;
        }
        if (!self::isAvailable()) {
            $result['error'] = 'إضافة zlib غير مفعّلة في PHP — فعّلها من php.ini لتفعيل استخراج PDF.';
            return $result;
        }

        $raw = (string) file_get_contents($path);
        if (!str_contains(substr($raw, 0, 1024), '%PDF') && !str_contains($raw, '%PDF-')) {
            $result['error'] = 'الملف ليس بصيغة PDF.';
            return $result;
        }

        $result['pages'] = preg_match_all('/\/Type\s*\/Page[^s]/', $raw) ?: 0;

        $streams = self::decodeStreams($raw);
        $result['streams'] = count($streams);

        if ($streams === []) {
            $result['error'] = 'لم يُعثر على أي تدفق نصي داخل الملف (قد يكون ملفاً ممسوحاً ضوئياً/صوراً).';
            $result['warning'] = 'الملفات الممسوحة ضوئياً تحتاج برنامج OCR خارجي — استخرج النص ثم ارفعه كملف TXT أو CSV.';
            return $result;
        }

        // 1) خريطة ToUnicode (تُبنى من التدفقات التي تحتوي CMap)
        [$map, $mappedWidth] = self::buildUnicodeMap($streams);
        $result['mapped'] = $map !== [];

        // 2) استخراج النص (نُفضّل عرض الكود المُعلن في الـ CMap)
        $chunks = [];
        foreach ($streams as $stream) {
            if (!self::looksLikeContent($stream['data'])) {
                continue;
            }
            $text = self::extractFromContent($stream['data'], $map, $mappedWidth);
            if (trim($text) !== '') {
                $chunks[] = $text;
            }
        }

        $result['text'] = self::normalize(implode("\n", $chunks));
        $result['ok'] = trim($result['text']) !== '';

        if (!$result['ok']) {
            $result['error'] = 'لم يُستخرج أي نص من الملف.';
            $result['warning'] = 'قد يكون الملف ممسوحاً ضوئياً أو يعتمد خطوطاً بلا خريطة يونيكود — استخدم OCR خارجي أو أدخل النص يدوياً.';
        } elseif (!$result['mapped'] && self::garbleRatio($result['text']) > 0.15) {
            $result['warning'] = 'لم يُعثر على خريطة يونيكود وقد ظهر جزء من النص مشوَّهاً — استخدم OCR خارجي أو الصق النص يدوياً لنتيجة موثوقة.';
        }

        return $result;
    }

    // ------------------------------------------------------------------
    //  فك ضغط التدفقات
    // ------------------------------------------------------------------

    /**
     * استخراج تدفقات الملف اعتماداً على حدود الكائنات (obj) وطول التدفق المُعلن (/Length)
     * حتى لا تتأثر عملية القراءة ببيانات الخطوط الثنائية التي قد تحتوي كلمة stream.
     * @return array<int,array{data:string,dict:string}>
     */
    private static function decodeStreams(string $raw): array
    {
        $streams = [];
        $objects = [];
        if (preg_match_all('/(\d+)\s+(\d+)\s+obj\b/', $raw, $matches, PREG_OFFSET_CAPTURE) === false) {
            return [];
        }
        foreach ($matches[0] as $index => $match) {
            $objects[] = [
                'number' => (int) $matches[1][$index][0],
                'start'  => $match[1] + strlen($match[0]),
                'next'   => $matches[0][$index + 1][1] ?? strlen($raw),
            ];
        }

        foreach ($objects as $object) {
            $slice = substr($raw, $object['start'], $object['next'] - $object['start']);
            $streamPos = strpos($slice, 'stream');
            if ($streamPos === false) {
                continue;
            }
            $dict = substr($slice, 0, $streamPos);
            if (str_contains($dict, '/Subtype/Image') || str_contains($dict, '/Subtype /Image')) {
                continue;
            }
            foreach (['/DCTDecode', '/JPXDecode', '/CCITTFaxDecode', '/Type /EmbeddedFile', '/FontFile',
                      '/Length1', '/Subtype/Type1C', '/Subtype /Type1C', '/CIDFontType', '/Type /Font', '/CMapType'] as $skipMarker) {
                if (str_contains($dict, $skipMarker)) {
                    continue 2;
                }
            }

            $dataStart = $object['start'] + $streamPos + 6;
            if (substr($raw, $dataStart, 2) === "\r\n") {
                $dataStart += 2;
            } elseif (in_array(substr($raw, $dataStart, 1), ["\n", "\r"], true)) {
                $dataStart += 1;
            }

            // تحديد الطول: من /Length مباشرة أو بالرجوع إلى كائن الطول، وإلا البحث عن endstream
            $length = null;
            if (preg_match('/\/Length\s+(\d+)(?!\s+\d+\s+R)/', $dict, $lengthMatch)) {
                $length = (int) $lengthMatch[1];
            } elseif (preg_match('/\/Length\s+(\d+)\s+\d+\s+R/', $dict, $indirect)) {
                $lengthObject = (int) $indirect[1];
                if (preg_match('/(?:^|[^0-9])' . $lengthObject . '\s+\d+\s+obj\s*(\d+)/s', $raw, $resolved)) {
                    $length = (int) $resolved[1];
                }
            }

            if ($length !== null && $length > 0 && $length <= 10 * 1024 * 1024) {
                $data = substr($raw, $dataStart, $length);
            } else {
                $end = strpos($raw, 'endstream', $dataStart);
                if ($end === false) {
                    continue;
                }
                $data = substr($raw, $dataStart, $end - $dataStart);
            }
            if (trim($data) === '') {
                continue;
            }

            $decoded = null;
            if (str_contains($dict, '/FlateDecode') || str_contains($dict, '/Fl')) {
                $decoded = self::inflate($data);
            } elseif (str_contains($dict, '/ASCII85Decode')) {
                $decoded = self::ascii85Decode($data);
                if ($decoded !== null && (str_contains($dict, '/FlateDecode') || str_contains($dict, '/Fl'))) {
                    $decoded = self::inflate($decoded);
                }
            } else {
                $decoded = $data;
            }

            if (is_string($decoded) && $decoded !== '') {
                $streams[] = ['data' => $decoded, 'dict' => $dict];
            }
        }
        return $streams;
    }

    private static function inflate(string $data): ?string
    {
        $data = ltrim($data, "\r\n");
        foreach (['gzuncompress', 'gzinflate', 'gzdecode'] as $function) {
            if (!function_exists($function)) {
                continue;
            }
            $out = @$function($data);
            if (is_string($out) && $out !== '') {
                return $out;
            }
        }
        // بعض الملفات تُخزّن الضغط بصيغة خام بإزاحة
        $out = @gzinflate(substr($data, 2));
        return is_string($out) && $out !== '' ? $out : null;
    }

    private static function ascii85Decode(string $data): ?string
    {
        $data = preg_replace('/\s+/', '', $data) ?? '';
        $data = str_replace(['<~', '~>'], '', $data);
        $out = '';
        $length = strlen($data);
        for ($i = 0; $i < $length; $i += 5) {
            $chunk = substr($data, $i, 5);
            $padding = 5 - strlen($chunk);
            if ($padding > 0) {
                $chunk .= str_repeat('u', $padding);
            }
            $value = 0;
            for ($j = 0; $j < 5; $j++) {
                $code = ord($chunk[$j]) - 33;
                if ($code < 0 || $code > 84) {
                    return $out !== '' ? $out : null;
                }
                $value = $value * 85 + $code;
            }
            $block = pack('N', $value & 0xFFFFFFFF);
            $out .= $padding > 0 ? substr($block, 0, 5 - $padding) : $block;
        }
        return $out;
    }

    // ------------------------------------------------------------------
    //  خريطة ToUnicode
    // ------------------------------------------------------------------

    /**
     * بناء خريطة ToUnicode من تدفقات CMap.
     * @param array<int,array{data:string,dict:string}> $streams
     * @return array{0:array<int,string>,1:int} [الخريطة, عرض الكود بالبايتات]
     */
    private static function buildUnicodeMap(array $streams): array
    {
        $map = [];
        $width = 0;
        foreach ($streams as $streamRow) {
            $stream = $streamRow['data'];
            if (!str_contains($stream, 'beginbfchar') && !str_contains($stream, 'beginbfrange')) {
                continue;
            }
            // عرض الكود من قسم begincodespacerange تحديداً (<0000> <FFFF> يعني رمزين بايت)
            if (preg_match('/begincodespacerange(.*?)endcodespacerange/s', $stream, $spaceBlock)) {
                if (preg_match_all('/<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>/', $spaceBlock[1], $codespaces)) {
                    foreach ($codespaces[1] as $codespace) {
                        $width = max($width, (int) ceil(strlen($codespace) / 2));
                    }
                }
            }
            // beginbfchar: <src> <dst>
            if (preg_match_all('/beginbfchar(.*?)endbfchar/s', $stream, $blocks)) {
                foreach ($blocks[1] as $block) {
                    if (preg_match_all('/<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>/', $block, $pairs, PREG_SET_ORDER)) {
                        foreach ($pairs as $pair) {
                            $src = hexdec($pair[1]);
                            $map[$src] = self::utf16HexToUtf8($pair[2]);
                        }
                    }
                }
            }
            // beginbfrange: <start> <end> <dstStart> أو <start> <end> [<d1> <d2> ...]
            if (preg_match_all('/beginbfrange(.*?)endbfrange/s', $stream, $blocks)) {
                foreach ($blocks[1] as $block) {
                    if (preg_match_all('/<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>/', $block, $ranges, PREG_SET_ORDER)) {
                        foreach ($ranges as $range) {
                            $start = hexdec($range[1]);
                            $end = hexdec($range[2]);
                            $dstStart = hexdec($range[3]);
                            // التعامل مع صفحات البدايات (ترميز بسيط بعرض ثابت)
                            if ($end - $start > 65535) {
                                continue;
                            }
                            for ($code = $start; $code <= $end; $code++) {
                                $map[$code] = self::codePointToUtf8($dstStart + ($code - $start));
                            }
                        }
                    }
                    if (preg_match_all('/<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>\s*\[(.*?)\]/s', $block, $lists, PREG_SET_ORDER)) {
                        foreach ($lists as $list) {
                            $start = hexdec($list[1]);
                            if (preg_match_all('/<([0-9A-Fa-f]+)>/', $list[3], $items)) {
                                foreach ($items[1] as $index => $item) {
                                    $map[$start + $index] = self::utf16HexToUtf8($item);
                                }
                            }
                        }
                    }
                }
            }
        }
        if ($width < 1 || $width > 2) {
            $width = $map === [] ? 0 : (max(array_keys($map)) > 255 ? 2 : 1);
        }
        return [$map, $width];
    }

    private static function utf16HexToUtf8(string $hex): string
    {
        $hex = strlen($hex) % 2 === 1 ? '0' . $hex : $hex;
        $binary = @hex2bin($hex);
        if ($binary === false || $binary === '') {
            return '';
        }
        return self::utf16beToUtf8($binary);
    }

    /** تحويل UTF-16BE إلى UTF-8 يدوياً (لا يعتمد على mbstring ليعمل في كل بيئات XAMPP) */
    private static function utf16beToUtf8(string $binary): string
    {
        $out = '';
        $length = strlen($binary);
        for ($i = 0; $i + 1 < $length; $i += 2) {
            $unit = (ord($binary[$i]) << 8) | ord($binary[$i + 1]);
            // زوج بديل (Surrogate pair)
            if ($unit >= 0xD800 && $unit <= 0xDBFF && $i + 3 < $length) {
                $low = (ord($binary[$i + 2]) << 8) | ord($binary[$i + 3]);
                if ($low >= 0xDC00 && $low <= 0xDFFF) {
                    $out .= self::codePointToUtf8(0x10000 + (($unit - 0xD800) << 10) + ($low - 0xDC00));
                    $i += 2;
                    continue;
                }
            }
            if ($unit === 0xFEFF || $unit === 0x0000) {
                continue; // BOM أو محارف تحكم
            }
            $out .= self::codePointToUtf8($unit);
        }
        return $out;
    }

    /** ترميز نقطة يونيكود إلى UTF-8 يدوياً */
    private static function codePointToUtf8(int $codePoint): string
    {
        if ($codePoint < 0x80) {
            return chr($codePoint);
        }
        if ($codePoint < 0x800) {
            return chr(0xC0 | ($codePoint >> 6)) . chr(0x80 | ($codePoint & 0x3F));
        }
        if ($codePoint < 0x10000) {
            return chr(0xE0 | ($codePoint >> 12))
                . chr(0x80 | (($codePoint >> 6) & 0x3F))
                . chr(0x80 | ($codePoint & 0x3F));
        }
        return chr(0xF0 | ($codePoint >> 18))
            . chr(0x80 | (($codePoint >> 12) & 0x3F))
            . chr(0x80 | (($codePoint >> 6) & 0x3F))
            . chr(0x80 | ($codePoint & 0x3F));
    }

    // ------------------------------------------------------------------
    //  استخراج النص من محتوى الصفحة
    // ------------------------------------------------------------------

    private static function looksLikeContent(string $stream): bool
    {
        // تدفق محتوى الصفحة: يحتوي عمليات نصية حقيقية وليس بيانات خط أو خريطة
        if (str_contains($stream, 'beginbfchar') || str_contains($stream, 'begincodespacerange') || str_contains($stream, 'glyf')) {
            return false;
        }
        return str_contains($stream, 'BT')
            && str_contains($stream, 'ET')
            && (str_contains($stream, 'Tf') || str_contains($stream, 'Tm'))
            && (str_contains($stream, 'Tj') || str_contains($stream, 'TJ'));
    }

    /** @param array<int,string> $map */
    private static function extractFromContent(string $content, array $map, int $codeWidth = 0): string
    {
        $out = '';
        $length = strlen($content);
        $i = 0;
        $twoByte = $codeWidth >= 2; // عرض الكود حسب CMap الخاص بالخط

        while ($i < $length) {
            $char = $content[$i];

            // سلاسل سداسية عشرية <...>
            if ($char === '<' && ($content[$i + 1] ?? '') !== '<') {
                $end = strpos($content, '>', $i);
                if ($end === false) {
                    break;
                }
                $hex = preg_replace('/[^0-9A-Fa-f]/', '', substr($content, $i + 1, $end - $i - 1)) ?? '';
                if ($hex !== '') {
                    $out .= self::decodeHexString($hex, $map, $twoByte);
                }
                $i = $end + 1;
                continue;
            }

            // قواميس <</ToUnicode ...>> تُتجاوز
            if ($char === '<' && ($content[$i + 1] ?? '') === '<') {
                $depth = 0;
                $j = $i;
                while ($j < $length) {
                    if (substr($content, $j, 2) === '<<') {
                        $depth++;
                        $j += 2;
                        continue;
                    }
                    if (substr($content, $j, 2) === '>>') {
                        $depth--;
                        $j += 2;
                        if ($depth === 0) {
                            break;
                        }
                        continue;
                    }
                    $j++;
                }
                $i = max($j, $i + 2);
                continue;
            }

            // سلاسل نصية (...) مع دعم الهروب
            if ($char === '(') {
                $depth = 1;
                $j = $i + 1;
                $buffer = '';
                while ($j < $length && $depth > 0) {
                    $current = $content[$j];
                    if ($current === '\\') {
                        $next = $content[$j + 1] ?? '';
                        if (preg_match('/^[0-7]{1,3}/', substr($content, $j + 1), $octal)) {
                            $buffer .= chr((int) octdec($octal[0]));
                            $j += 1 + strlen($octal[0]);
                            continue;
                        }
                        $buffer .= match ($next) {
                            'n' => "\n", 'r' => "\r", 't' => "\t", 'b' => "\x08", 'f' => "\x0C",
                            '(', ')', '\\' => $next,
                            default => $next,
                        };
                        $j += 2;
                        continue;
                    }
                    if ($current === '(') {
                        $depth++;
                    } elseif ($current === ')') {
                        $depth--;
                        if ($depth === 0) {
                            $j++;
                            break;
                        }
                    }
                    $buffer .= $current;
                    $j++;
                }
                $out .= self::decodeLiteral($buffer, $map, $twoByte);
                $i = $j;
                continue;
            }

            // اكتشاف حجم الخط: /F1 12 Tf — الخطوط ذات Tf ≫ (لا شيء)
            if ($char === 'T' && ($content[$i + 1] ?? '') === 'f') {
                $i += 2;
                continue;
            }

            // عمليات نهاية السطر
            if (
                ($char === 'T' && in_array(($content[$i + 1] ?? ''), ['d', 'D', 'L'], true))
                || ($char === 'T' && ($content[$i + 1] ?? '') === '*')
                || ($char === 'E' && ($content[$i + 1] ?? '') === 'T')
                || $char === "'"
                || $char === '"'
            ) {
                $out = rtrim($out) . "\n";
                $i += $char === 'T' ? 2 : 1;
                continue;
            }

            $i++;
        }

        return $out;
    }

    /** @param array<int,string> $map */
    private static function decodeHexString(string $hex, array $map, bool &$twoByte): string
    {
        $out = '';
        if ($map !== [] && $twoByte) {
            for ($k = 0; $k + 4 <= strlen($hex); $k += 4) {
                $code = hexdec(substr($hex, $k, 4));
                $out .= $map[$code] ?? '';
            }
            return $out;
        }
        // بايت واحد لكل رمز
        $binary = @hex2bin(strlen($hex) % 2 === 1 ? $hex . '0' : $hex);
        if ($binary === false) {
            return '';
        }
        $length = strlen($binary);
        for ($k = 0; $k < $length; $k++) {
            $code = ord($binary[$k]);
            $out .= $map !== [] ? ($map[$code] ?? '') : self::codePointToUtf8($code);
        }
        return $out;
    }

    /** @param array<int,string> $map */
    private static function decodeLiteral(string $buffer, array $map, bool &$twoByte): string
    {
        if ($map === []) {
            return $buffer;
        }
        $out = '';
        if ($twoByte && strlen($buffer) >= 2) {
            for ($k = 0; $k + 1 < strlen($buffer); $k += 2) {
                $code = (ord($buffer[$k]) << 8) | ord($buffer[$k + 1]);
                $out .= $map[$code] ?? '';
            }
            return $out;
        }
        for ($k = 0; $k < strlen($buffer); $k++) {
            $out .= $map[ord($buffer[$k])] ?? '';
        }
        return $out;
    }

    // ------------------------------------------------------------------

    /** نسبة المحارف المشوَّهة (رموز تحكم/لاتينية ممتدة/منطقة الاستخدام الخاص) للتحذير من سوء الترميز */
    public static function garbleRatio(string $text): float
    {
        if ($text === '') {
            return 0.0;
        }
        $total = preg_match_all('/./su', $text) ?: 0;
        if ($total === 0) {
            return 0.0;
        }
        $garbled = preg_match_all('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F\x{0080}-\x{024F}\x{E000}-\x{F8FF}\x{FFFD}]/u', $text) ?: 0;
        return $garbled / $total;
    }

    /** تنظيف النص الناتج: مسافات زائدة، أسطر فارغة مكررة */
    public static function normalize(string $text): string
    {
        $text = str_replace(["\r\n", "\r", "\u{00A0}"], ["\n", "\n", ' '], $text);
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\n{3,}/u', "\n\n", $text) ?? $text;
        return trim($text);
    }
}
