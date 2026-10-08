<?php
declare(strict_types=1);

namespace App;

use App\Repositories\QuestionRepository;

/**
 * استيراد بنك أسئلة من ملفات (PDF / TXT / CSV / JSON) عبر ثلاث مراحل:
 *   1) استخراج النص   2) تحليل الصفوف إلى جدول مراجعة (staging)   3) اعتماد بشري ثم الإدخال.
 *
 * قواعد ثابتة:
 *  - لا يُخمّن النظام الإجابة الصحيحة أبداً: إن لم تُحدَّد بوضوح تبقى NULL مع needs_review = 1.
 *  - كل صف يمر على جدول `import_staging` ويُعرض في شاشة مراجعة بشرية قبل الإدخال النهائي.
 */
final class QuestionImporter
{
    public const LETTERS = ['a', 'b', 'c', 'd'];

    // ------------------------------------------------------------------
    //  الدُفعات
    // ------------------------------------------------------------------

    public static function createBatch(string $fileName, string $fileType, ?string $filePath, int $userId, ?string $fileHash = null): int
    {
        return Database::instance()->insert('import_batches', [
            'file_name'    => mb_substr($fileName, 0, 255),
            'file_path'    => $filePath,
            'file_type'    => in_array($fileType, ['pdf', 'txt', 'csv', 'json', 'manual'], true) ? $fileType : 'txt',
            'file_hash'    => $fileHash,
            'status'       => 'pending',
            'imported_by'  => $userId > 0 ? $userId : null,
        ]);
    }

    public static function batch(int $batchId): ?array
    {
        return Database::instance()->one(
            'SELECT b.*, u.full_name AS importer_name FROM `import_batches` b
        LEFT JOIN `users` u ON u.id = b.imported_by WHERE b.id = :id',
            ['id' => $batchId]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public static function batches(int $limit = 30): array
    {
        return Database::instance()->all(
            "SELECT b.*, u.full_name AS importer_name,
                    (SELECT COUNT(*) FROM `import_staging` s WHERE s.batch_id = b.id AND s.status = 'pending') AS pending_count,
                    (SELECT COUNT(*) FROM `import_staging` s WHERE s.batch_id = b.id AND s.status = 'approved') AS approved_count,
                    (SELECT COUNT(*) FROM `import_staging` s WHERE s.batch_id = b.id AND s.status = 'imported') AS imported_count
               FROM `import_batches` b
          LEFT JOIN `users` u ON u.id = b.imported_by
              ORDER BY b.id DESC LIMIT " . max(1, min(200, $limit))
        );
    }

    public static function deleteBatch(int $batchId): bool
    {
        return Database::instance()->delete('import_batches', 'id = :id', ['id' => $batchId]) > 0;
    }

    public static function updateBatchCounters(int $batchId, array $counts = []): void
    {
        $db = Database::instance();
        $data = [
            'total_rows'         => (int) $db->value('SELECT COUNT(*) FROM `import_staging` WHERE batch_id = :id', ['id' => $batchId], 0),
            'imported_rows'      => (int) $db->value("SELECT COUNT(*) FROM `import_staging` WHERE batch_id = :id AND status = 'imported'", ['id' => $batchId], 0),
            'duplicate_rows'     => (int) $db->value('SELECT COUNT(*) FROM `import_staging` WHERE batch_id = :id AND is_duplicate = 1', ['id' => $batchId], 0),
            'invalid_rows'       => (int) $db->value('SELECT COUNT(*) FROM `import_staging` WHERE batch_id = :id AND is_valid = 0', ['id' => $batchId], 0),
            'needs_review_rows'  => (int) $db->value('SELECT COUNT(*) FROM `import_staging` WHERE batch_id = :id AND needs_review = 1', ['id' => $batchId], 0),
        ];
        foreach ($counts as $key => $value) {
            $data[$key] = $value;
        }
        $db->update('import_batches', $data, 'id = :id', ['id' => $batchId]);
    }

    // ------------------------------------------------------------------
    //  التحليل (Parsing)
    // ------------------------------------------------------------------

    /**
     * تحليل نص خام إلى صفوف أسئلة مبدئية.
     * @return array<int,array<string,mixed>>
     */
    public static function parseText(string $text): array
    {
        $text = PdfExtractor::normalize($text);
        if (trim($text) === '') {
            return [];
        }
        $lines = preg_split('/\n/u', $text) ?: [];
        $rows = [];
        $current = null;
        $page = 1;
        $optionIndex = 0;
        $lastOptionLetter = null;

        $flush = static function () use (&$rows, &$current): void {
            if ($current !== null) {
                $rows[] = self::finalizeRow($current);
                $current = null;
            }
        };

        foreach ($lines as $line) {
            $line = trim(preg_replace('/\s+/u', ' ', $line) ?? '');
            if ($line === '') {
                continue;
            }
            if (str_contains($line, "\f")) {
                $page++;
            }
            // تجاهل أرقام الصفحات والأسطر التي لا تحمل حروفاً
            if (preg_match('/^[\d\s\-\/\.\|]+$/u', $line)) {
                continue;
            }
            if (preg_match('/^(?:صفحة|Page)\s*\d+/iu', $line)) {
                $page++;
                continue;
            }
            // حقوق النشر / الترويسات المتكررة
            if (preg_match('/(جميع الحقوق محفوظة|all rights reserved|حقوق النشر)/iu', $line)) {
                continue;
            }

            // سطر إجابة
            if (preg_match('/^(?:الإجابة|الاجابة|الجواب|الإجابة الصحيحة|الاجابة الصحيحة|Answer|Correct(?:\s*Answer)?|Ans)\s*[:\-：]?\s*(.+)$/u', $line, $match)) {
                if ($current !== null) {
                    $current['answer_raw'] = trim($match[1]);
                }
                continue;
            }

            // سطر شرح
            if (preg_match('/^(?:الشرح|التوضيح|التفسير|Explanation)\s*[:\-：]?\s*(.+)$/u', $line, $match)) {
                if ($current !== null) {
                    $current['explanation'] = trim(($current['explanation'] ?? '') . ' ' . trim($match[1]));
                }
                continue;
            }

            // سطر اختيار
            if (preg_match('/^(?:\(?([أ-يa-dA-D])\)?\s*[\)\.\-:،]\s*|\s*[\-•·]\s+|\s*([1-9])\s*[\)\.\-:]\s+)(.+)$/u', $line, $match)) {
                if ($current !== null) {
                    $token = trim((string) ($match[1] ?? ''));
                    $number = trim((string) ($match[2] ?? ''));
                    $body = trim((string) $match[3]);
                    $letter = $token !== '' ? self::normalizeLetter($token) : null;
                    if ($letter === null) {
                        $letter = self::LETTERS[$optionIndex] ?? null;
                    }
                    if ($body !== '' && $letter !== null && !isset($current['options'][$letter])) {
                        $current['options'][$letter] = $body;
                        $lastOptionLetter = $letter;
                        $optionIndex = array_search($letter, self::LETTERS, true) + 1;
                        continue;
                    }
                }
            }

            // بداية سؤال جديد
            if (preg_match('/^(?:س(?:\s*ؤال)?\s*\.?\s*\(?\s*\d+\s*\)?\s*[:\-\.]?|السؤال\s*\(?\s*\d+\s*\)?\s*[:\-\.]?|Q(?:uestion)?\s*\.?\s*\d+\s*[:\-\.)]?|\d{1,3}\s*[\)\.\-:]\s*)/u', $line, $match)) {
                $flush();
                $body = trim(mb_substr($line, mb_strlen($match[0])));
                $current = [
                    'raw_text'       => $line,
                    'question_text'  => $body,
                    'options'        => [],
                    'answer_raw'     => null,
                    'explanation'    => '',
                    'page'           => $page,
                ];
                $optionIndex = 0;
                continue;
            }

            if ($current === null) {
                // نص تمهيدي أو سؤال بدون ترقيم: نعتبره سؤالاً جديداً
                $current = [
                    'raw_text'      => $line,
                    'question_text' => $line,
                    'options'       => [],
                    'answer_raw'    => null,
                    'explanation'   => '',
                    'page'          => $page,
                ];
                $optionIndex = 0;
                continue;
            }

            // سطر تابع: إما استكمال نص السؤال أو استكمال آخر اختيار
            if ($lastOptionLetter !== null && isset($current['options'][$lastOptionLetter])) {
                $current['options'][$lastOptionLetter] .= ' ' . $line;
            } elseif ($current['options'] === []) {
                $current['question_text'] = trim($current['question_text'] . ' ' . $line);
            } else {
                $current['explanation'] = trim(($current['explanation'] ?? '') . ' ' . $line);
            }
        }
        $flush();

        return $rows;
    }

    private static function finalizeRow(array $row): array
    {
        $options = $row['options'] ?? [];
        $issues = [];
        $questionText = trim((string) $row['question_text']);
        $questionText = Str::cleanOcrArtifacts($questionText);

        $optionA = $options['a'] ?? null;
        $optionB = $options['b'] ?? null;
        $optionC = $options['c'] ?? null;
        $optionD = $options['d'] ?? null;

        $textTooShort = mb_strlen($questionText) < 10;
        if ($textTooShort) {
            $issues[] = 'نص السؤال قصير جداً (أقل من 10 أحرف) — لن يُدخل حتى تصحّحه';
        }
        if ($optionA === null || $optionB === null) {
            $issues[] = 'اختياران على الأقل غير متوفرين';
        }
        if (isset($options['c']) && !isset($options['b'])) {
            $issues[] = 'ترقيم الاختيارات غير متسلسل';
        }

        $answer = null;
        $answerRaw = trim((string) ($row['answer_raw'] ?? ''));
        if ($answerRaw !== '') {
            $answer = self::resolveAnswer($answerRaw, $options);
            if ($answer === null) {
                $issues[] = 'تعذّر مطابقة نص الإجابة بالاختيارات: ' . Str::limit($answerRaw, 40);
            }
        } else {
            $issues[] = 'لم يُذكر سطر الإجابة في المصدر';
        }

        $needsReview = $answer === null;
        if ($answer === null && $answerRaw !== '' && preg_match('/(غير واضح|غير موجود|لم يتحدد|\?)/u', $answerRaw)) {
            $issues[] = 'المصدر نفسه يشير إلى أن الإجابة غير واضحة';
        }

        // الصلاحية: نص كافٍ + اختياران على الأقل (الإجابة غير الواضحة لا تُلغي الصلاحية لكنها تُوسم للمراجعة)
        $valid = !$textTooShort && $optionA !== null && $optionB !== null;

        return [
            'raw_text'       => (string) ($row['raw_text'] ?? $questionText),
            'question_text'  => $questionText,
            'option_a'       => $optionA,
            'option_b'       => $optionB,
            'option_c'       => $optionC,
            'option_d'       => $optionD,
            'correct_answer' => $answer,
            'explanation'    => trim(Str::cleanOcrArtifacts((string) ($row['explanation'] ?? ''))) ?: null,
            'source_page'    => (int) ($row['page'] ?? 0) ?: null,
            'category_guess' => isset($row['category_guess']) && $row['category_guess'] !== '' ? mb_substr((string) $row['category_guess'], 0, 190) : null,
            'difficulty'     => isset($row['difficulty']) && in_array((string) $row['difficulty'], ['easy', 'medium', 'hard'], true) ? (string) $row['difficulty'] : null,
            'is_valid'       => $valid ? 1 : 0,
            'needs_review'   => $needsReview ? 1 : 0,
            'issues'         => $issues,
        ];
    }

    /** محاولة تحديد الإجابة من نص حر: حرف (أ/ب/ج/د أو A-D أو 1-4) أو نص اختيار كامل */
    public static function resolveAnswer(string $raw, array $options): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        // حرف مباشر: أ/ا/ب/ج/د/a-d
        if (preg_match('/^\(?([أ-يa-dA-D])\)?\s*[\)\.\-]?$/u', $raw, $match)) {
            $letter = self::normalizeLetter($match[1]);
            if ($letter !== null && isset($options[$letter])) {
                return $letter;
            }
        }
        // رقم: 1-4 → a-d
        if (preg_match('/^\(?([1-9])\)?$/', $raw, $match)) {
            $letter = self::LETTERS[(int) $match[1] - 1] ?? null;
            if ($letter !== null && isset($options[$letter])) {
                return $letter;
            }
        }
        // مطابقة نصية مع أحد الاختيارات
        $clean = static fn(string $text): string => Str::normalizeArabic(preg_replace('/[^\p{Arabic}\p{L}\p{N}\s]/u', ' ', $text) ?? $text);
        $target = $clean($raw);
        foreach ($options as $letter => $optionText) {
            $option = $clean((string) $optionText);
            if ($option !== '' && ($target === $option || mb_strpos($target, $option) !== false)) {
                return (string) $letter;
            }
        }
        // صيغة "(أ) النص" أو "أ - النص"
        if (preg_match('/^\(?([أ-يa-dA-D])\)?\s*[\-\.:]?\s*(.+)$/u', $raw, $match)) {
            $letter = self::normalizeLetter($match[1]);
            if ($letter !== null && isset($options[$letter])) {
                return $letter;
            }
        }
        return null;
    }

    public static function normalizeLetter(string $letter): ?string
    {
        $map = [
            'أ' => 'a', 'ا' => 'a', 'a' => 'a', 'A' => 'a', '١' => 'a',
            'ب' => 'b', 'b' => 'b', 'B' => 'b', '٢' => 'b',
            'ج' => 'c', 'c' => 'c', 'C' => 'c', '٣' => 'c',
            'د' => 'd', 'd' => 'd', 'D' => 'd', '٤' => 'd',
        ];
        return $map[$letter] ?? null;
    }

    /**
     * تحليل ملف CSV: يدعم الأعمدة الإنجليزية والعربية، ورؤوساً اختيارية.
     * @return array<int,array<string,mixed>>
     */
    public static function parseCsv(string $text): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($text)) ?: [];
        $escape = chr(92); // محرف الهروب لفك حقول CSV المقتبسة
        $rows = [];
        $map = null;
        $aliases = [
            'question_text'  => ['question', 'question_text', 'q', 'text', 'السؤال', 'نص السؤال', 'سؤال'],
            'option_a'       => ['option_a', 'a', 'choice1', 'opt1', 'الاختيار الأول', 'أ', 'اختيار1'],
            'option_b'       => ['option_b', 'b', 'choice2', 'opt2', 'الاختيار الثاني', 'ب', 'اختيار2'],
            'option_c'       => ['option_c', 'c', 'choice3', 'opt3', 'الاختيار الثالث', 'ج', 'اختيار3'],
            'option_d'       => ['option_d', 'd', 'choice4', 'opt4', 'الاختيار الرابع', 'د', 'اختيار4'],
            'correct_answer' => ['correct_answer', 'answer', 'correct', 'key', 'الإجابة', 'الاجابة', 'الإجابة الصحيحة'],
            'explanation'    => ['explanation', 'explain', 'note', 'الشرح', 'التفسير'],
            'category_guess' => ['category', 'المجال', 'التصنيف'],
            'difficulty'     => ['difficulty', 'level', 'الصعوبة'],
            'source_page'    => ['page', 'source_page', 'الصفحة'],
        ];

        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $cells = str_getcsv($line, ',', '"', $escape);
            if ($cells === [null] || $cells === []) {
                continue;
            }
            if ($map === null) {
                $headerCells = array_map(static fn($cell) => mb_strtolower(trim((string) $cell)), $cells);
                $found = [];
                foreach ($aliases as $field => $names) {
                    foreach ($headerCells as $index => $header) {
                        if (in_array($header, $names, true)) {
                            $found[$field] = $index;
                        }
                    }
                }
                if (isset($found['question_text'])) {
                    $map = $found;
                    continue; // هذا سطر رؤوس - نتخطاه
                }
                // بلا رؤوس: نفترض الترتيب سؤال، أ، ب، ج، د، إجابة
                $map = ['question_text' => 0, 'option_a' => 1, 'option_b' => 2, 'option_c' => 3, 'option_d' => 4, 'correct_answer' => 5];
            }
            $get = static fn(string $field): ?string => isset($map[$field], $cells[$map[$field]]) && trim((string) $cells[$map[$field]]) !== ''
                ? trim((string) $cells[$map[$field]])
                : null;

            $options = [
                'a' => $get('option_a'),
                'b' => $get('option_b'),
                'c' => $get('option_c'),
                'd' => $get('option_d'),
            ];
            $options = array_filter($options, static fn($value) => $value !== null);
            $answerRaw = $get('correct_answer');
            $answer = $answerRaw !== null ? self::resolveAnswer($answerRaw, $options) : null;

            $rows[] = self::finalizeRow([
                'raw_text'      => implode(' | ', array_map(static fn($cell) => (string) $cell, $cells)),
                'question_text' => (string) $get('question_text'),
                'options'       => $options,
                'answer_raw'    => $answerRaw,
                'explanation'   => (string) ($get('explanation') ?? ''),
                'page'          => (int) ($get('source_page') ?? 0),
                'category_guess' => $get('category_guess'),
                'difficulty'    => $get('difficulty'),
            ]);
        }
        return $rows;
    }

    /**
     * تحليل ملف JSON: مصفوفة من كائنات الأسئلة.
     * @return array<int,array<string,mixed>>
     */
    public static function parseJson(string $text): array
    {
        $data = json_decode($text, true);
        if (!is_array($data)) {
            return [];
        }
        if (isset($data['questions']) && is_array($data['questions'])) {
            $data = $data['questions'];
        }
        $rows = [];
        foreach ($data as $item) {
            if (!is_array($item)) {
                continue;
            }
            $questionText = (string) ($item['question'] ?? $item['question_text'] ?? $item['text'] ?? '');
            $options = [];
            if (isset($item['options']) && is_array($item['options'])) {
                $keys = array_values(array_keys($item['options']));
                foreach (self::LETTERS as $index => $letter) {
                    $key = $keys[$index] ?? null;
                    if ($key !== null && trim((string) $item['options'][$key]) !== '') {
                        $options[$letter] = trim((string) $item['options'][$key]);
                    }
                }
            } else {
                foreach (self::LETTERS as $letter) {
                    $value = $item['option_' . $letter] ?? null;
                    if ($value !== null && trim((string) $value) !== '') {
                        $options[$letter] = trim((string) $value);
                    }
                }
            }
            $answerRaw = $item['correct_answer'] ?? $item['answer'] ?? $item['correct'] ?? null;
            $answer = $answerRaw !== null ? self::resolveAnswer((string) $answerRaw, $options) : null;

            $rows[] = self::finalizeRow([
                'raw_text'       => json_encode($item, JSON_UNESCAPED_UNICODE) ?: $questionText,
                'question_text'  => $questionText,
                'options'        => $options,
                'answer_raw'     => $answerRaw !== null ? (string) $answerRaw : null,
                'explanation'    => (string) ($item['explanation'] ?? ''),
                'page'           => (int) ($item['source_page'] ?? $item['page'] ?? 0),
                'category_guess' => $item['category'] ?? null,
                'difficulty'     => $item['difficulty'] ?? null,
            ]);
        }
        return $rows;
    }

    // ------------------------------------------------------------------
    //  الإيداع في جدول المراجعة
    // ------------------------------------------------------------------

    /**
     * إيداع صفوف في جدول المراجعة.
     * @param array<int,array<string,mixed>> $rows
     * @return array{staged:int,invalid:int,duplicates:int,needs_review:int}
     */
    public static function stage(int $batchId, array $rows): array
    {
        $db = Database::instance();
        $repo = new QuestionRepository();
        $stats = ['staged' => 0, 'invalid' => 0, 'duplicates' => 0, 'needs_review' => 0];
        $rowNumber = (int) $db->value('SELECT COALESCE(MAX(row_number), 0) FROM `import_staging` WHERE batch_id = :id', ['id' => $batchId], 0);

        foreach ($rows as $row) {
            $rowNumber++;
            $hash = Str::contentFingerprint((string) ($row['question_text'] ?? ''));
            $duplicate = $repo->duplicateExists($hash);
            $issues = (array) ($row['issues'] ?? []);
            if ($duplicate !== null) {
                $issues[] = 'مكرر مع سؤال رقم ' . $duplicate['id'];
            }

            $db->insert('import_staging', [
                'batch_id'       => $batchId,
                'row_number'     => $rowNumber,
                'raw_text'       => $row['raw_text'] ?? null,
                'question_text'  => $row['question_text'] ?? null,
                'option_a'       => $row['option_a'] ?? null,
                'option_b'       => $row['option_b'] ?? null,
                'option_c'       => $row['option_c'] ?? null,
                'option_d'       => $row['option_d'] ?? null,
                'correct_answer' => $row['correct_answer'] ?? null,
                'explanation'    => $row['explanation'] ?? null,
                'difficulty'     => $row['difficulty'] ?? null,
                'category_guess' => $row['category_guess'] ?? null,
                'source_page'    => $row['source_page'] ?? null,
                'content_hash'   => $hash,
                'is_valid'       => (int) ($row['is_valid'] ?? 1),
                'is_duplicate'   => $duplicate !== null ? 1 : 0,
                'needs_review'   => (int) ($row['needs_review'] ?? 0),
                'issues'         => $issues === [] ? null : json_encode($issues, JSON_UNESCAPED_UNICODE),
                'status'         => 'pending',
            ]);

            $stats['staged']++;
            if ((int) ($row['is_valid'] ?? 1) === 0) {
                $stats['invalid']++;
            }
            if ($duplicate !== null) {
                $stats['duplicates']++;
            }
            if ((int) ($row['needs_review'] ?? 0) === 1) {
                $stats['needs_review']++;
            }
        }

        self::updateBatchCounters($batchId, ['status' => 'previewed']);
        return $stats;
    }

    /** @return array{ok:bool,message:string,stats:array<string,int>} */
    public static function ingestText(int $batchId, string $text, string $fileType = 'txt', ?int $sourceId = null): array
    {
        $text = PdfExtractor::normalize($text);
        if (trim($text) === '') {
            return ['ok' => false, 'message' => 'لم يتم العثور على نص قابل للتحليل.', 'stats' => []];
        }
        $rows = match ($fileType) {
            'csv'   => self::parseCsv($text),
            'json'  => self::parseJson($text),
            default => self::parseText($text),
        };
        if ($rows === [] && $fileType !== 'txt') {
            // احتياط: جرّب التحليل النصي العام
            $rows = self::parseText($text);
        }
        if ($rows === []) {
            return ['ok' => false, 'message' => 'لم يتم التعرف على أي سؤال في النص. تأكد من ترقيم الأسئلة (س1، Q1، 1- ...).', 'stats' => []];
        }
        if ($sourceId !== null && $sourceId > 0) {
            foreach ($rows as &$row) {
                $row['source_id'] = $sourceId;
            }
            unset($row);
        }
        $stats = self::stage($batchId, $rows);
        return ['ok' => true, 'message' => 'تم تحليل ' . $stats['staged'] . ' صفاً وإضافتها إلى جدول المراجعة.', 'stats' => $stats];
    }

    // ------------------------------------------------------------------
    //  صفوف المراجعة
    // ------------------------------------------------------------------

    /** @return array<string,mixed> */
    public static function rows(int $batchId, array $filters = [], int $page = 1, int $perPage = 25): array
    {
        $clauses = ['s.batch_id = :batch'];
        $params = ['batch' => $batchId];
        $status = (string) ($filters['status'] ?? '');
        if (in_array($status, ['pending', 'approved', 'rejected', 'imported'], true)) {
            $clauses[] = 's.status = :status';
            $params['status'] = $status;
        }
        if (!empty($filters['only_invalid'])) {
            $clauses[] = 's.is_valid = 0';
        }
        if (!empty($filters['only_duplicates'])) {
            $clauses[] = 's.is_duplicate = 1';
        }
        if (!empty($filters['only_review'])) {
            $clauses[] = 's.needs_review = 1';
        }

        return Database::instance()->paginate(
            'SELECT s.* FROM `import_staging` s WHERE ' . implode(' AND ', $clauses) . ' ORDER BY s.row_number ASC',
            $params,
            $perPage,
            $page
        );
    }

    /** تعديل صف مراجعة (تحديث الحقول المشتقة تلقائياً) */
    public static function updateRow(int $rowId, array $data): bool
    {
        $db = Database::instance();
        $row = $db->one('SELECT * FROM `import_staging` WHERE id = :id', ['id' => $rowId]);
        if ($row === null) {
            return false;
        }

        $payload = [];
        foreach (['question_text', 'option_a', 'option_b', 'option_c', 'option_d', 'explanation', 'category_guess'] as $field) {
            if (array_key_exists($field, $data)) {
                $value = is_string($data[$field]) ? trim($data[$field]) : $data[$field];
                $payload[$field] = ($value === '' || $value === null) ? null : $value;
            }
        }
        if (array_key_exists('correct_answer', $data)) {
            $answer = strtolower((string) $data['correct_answer']);
            $payload['correct_answer'] = in_array($answer, self::LETTERS, true) ? $answer : null;
        }
        if (array_key_exists('source_page', $data)) {
            $payload['source_page'] = (int) $data['source_page'] ?: null;
        }
        if (array_key_exists('difficulty', $data)) {
            $payload['difficulty'] = in_array((string) $data['difficulty'], ['easy', 'medium', 'hard'], true) ? (string) $data['difficulty'] : null;
        }
        if (array_key_exists('needs_review', $data)) {
            $payload['needs_review'] = (int) $data['needs_review'] === 1 ? 1 : 0;
        }

        $merged = array_merge($row, $payload);
        $issues = self::evaluateIssues([
            'question_text'  => (string) ($merged['question_text'] ?? ''),
            'option_a'       => $merged['option_a'] ?? null,
            'option_b'       => $merged['option_b'] ?? null,
            'option_c'       => $merged['option_c'] ?? null,
            'option_d'       => $merged['option_d'] ?? null,
        ], $merged['correct_answer'] ?? null, isset($merged['status']) ? (string) $merged['status'] : 'pending');

        $hash = Str::contentFingerprint((string) ($merged['question_text'] ?? ''));
        $duplicate = (new QuestionRepository())->duplicateExists($hash);

        $payload['content_hash'] = $hash;
        $payload['is_duplicate'] = $duplicate !== null ? 1 : 0;
        $payload['is_valid'] = ($issues['valid'] && ($merged['option_a'] ?? null) !== null && ($merged['option_b'] ?? null) !== null) ? 1 : 0;
        $payload['needs_review'] = ($merged['correct_answer'] ?? null) === null && empty($payload['needs_review']) ? 1 : (int) ($merged['needs_review'] ?? $payload['needs_review'] ?? 0);
        $payload['issues'] = $issues['issues'] === [] ? null : json_encode($issues['issues'], JSON_UNESCAPED_UNICODE);

        return $db->update('import_staging', $payload, 'id = :id', ['id' => $rowId]) >= 0;
    }

    /**
     * @param array<string,mixed> $fields
     * @return array{valid:bool,issues:array<int,string>}
     */
    public static function evaluateIssues(array $fields, ?string $answer, string $status = 'pending'): array
    {
        $issues = [];
        $questionText = trim((string) ($fields['question_text'] ?? ''));
        $textTooShort = mb_strlen($questionText) < 10;
        if ($textTooShort) {
            $issues[] = 'نص السؤال قصير جداً (أقل من 10 أحرف) — لن يُدخل حتى تصحّحه';
        }
        if (($fields['option_a'] ?? null) === null || ($fields['option_b'] ?? null) === null) {
            $issues[] = 'اختياران على الأقل مطلوبان';
        }
        if ($answer === null) {
            $issues[] = 'الإجابة الصحيحة غير محددة';
        }
        if (($fields['option_c'] ?? null) !== null && ($fields['option_d'] ?? null) === null) {
            $issues[] = 'يوجد اختيار (ج) بدون (د) — قد يكون ناقصاً';
        }
        $valid = !$textTooShort
            && ($fields['option_a'] ?? null) !== null
            && ($fields['option_b'] ?? null) !== null;
        return ['valid' => $valid, 'issues' => $issues];
    }

    /** اعتماد أو رفض صفوف المراجعة */
    public static function setStatus(array $ids, string $status): int
    {
        $ids = array_values(array_filter(array_map('intval', $ids), static fn(int $id) => $id > 0));
        if ($ids === [] || !in_array($status, ['pending', 'approved', 'rejected'], true)) {
            return 0;
        }
        $db = Database::instance();
        $params = [];
        $placeholders = [];
        foreach ($ids as $index => $id) {
            $placeholders[] = ':id' . $index;
            $params['id' . $index] = $id;
        }
        return $db->query(
            'UPDATE `import_staging` SET status = :status WHERE id IN (' . implode(',', $placeholders) . ')',
            $params + ['status' => $status]
        )->rowCount();
    }

    // ------------------------------------------------------------------
    //  الإدخال النهائي (بعد الاعتماد البشري)
    // ------------------------------------------------------------------

    /**
     * إدخال الصفوف المعتمدة إلى جدول الأسئلة.
     * @param array<string,mixed> $options ['track_id','category_id','difficulty','source_id','include_duplicates','include_invalid']
     * @return array{ok:bool,message:string,imported:int,skipped:int,warnings:array<int,string>}
     */
    public static function importApproved(int $batchId, array $options, int $adminId): array
    {
        $db = Database::instance();
        $repo = new QuestionRepository();
        $trackId = (int) ($options['track_id'] ?? 0);
        $categoryId = (int) ($options['category_id'] ?? 0);
        $sourceId = (int) ($options['source_id'] ?? 0);
        // ملاحظة: القراءة تتم مرة واحدة ثم يُتحقق منها، لتجنب قراءة مفتاح غير موجود
        $difficultyOption = (string) ($options['difficulty'] ?? 'medium');
        $difficulty = in_array($difficultyOption, ['easy', 'medium', 'hard'], true) ? $difficultyOption : 'medium';

        if ($trackId <= 0) {
            return ['ok' => false, 'message' => 'يجب اختيار المسار قبل الإدخال.', 'imported' => 0, 'skipped' => 0, 'warnings' => []];
        }

        $rows = $db->all(
            "SELECT * FROM `import_staging` WHERE batch_id = :batch AND status = 'approved' ORDER BY row_number ASC",
            ['batch' => $batchId]
        );
        if ($rows === []) {
            return ['ok' => false, 'message' => 'لا توجد صفوف معتمدة للإدخال. اعتمد الصفوف الصحيحة أولاً.', 'imported' => 0, 'skipped' => 0, 'warnings' => []];
        }

        $imported = 0;
        $skipped = 0;
        $warnings = [];

        // خيار «استخدام التصنيف المقترح لكل صف»: يحوّل category_guess (اسم المجال) إلى معرّف مجال
        $useRowCategory = (bool) ($options['use_row_category'] ?? false);
        $categoryByName = [];
        if ($useRowCategory) {
            foreach ($db->all('SELECT `id`, `name_ar` FROM `categories` WHERE track_id = :t', ['t' => $trackId]) as $category) {
                $categoryByName[Str::normalizeArabic((string) $category['name_ar'])] = (int) $category['id'];
            }
        }

        foreach ($rows as $row) {
            $questionText = trim((string) ($row['question_text'] ?? ''));
            $hash = (string) ($row['content_hash'] ?: Str::contentFingerprint($questionText));

            if ($repo->duplicateExists($hash) !== null) {
                $skipped++;
                $warnings[] = 'الصف ' . (int) $row['row_number'] . ': مُستبعد (سؤال مكرر موجود مسبقاً).';
                $db->update('import_staging', ['is_duplicate' => 1], 'id = :id', ['id' => (int) $row['id']]);
                continue;
            }
            if ($questionText === '' || ($row['option_a'] ?? null) === null || ($row['option_b'] ?? null) === null) {
                $skipped++;
                $warnings[] = 'الصف ' . (int) $row['row_number'] . ': مُستبعد (بيانات ناقصة).';
                continue;
            }

            $needsReview = $row['correct_answer'] === null || (int) $row['needs_review'] === 1;

            // المجال: تصنيف الصف إن كان متاحاً ومطابقاً لمجال في المسار، وإلا مجال الدُفعة
            $rowCategoryId = $categoryId;
            if ($useRowCategory && trim((string) ($row['category_guess'] ?? '')) !== '') {
                $key = Str::normalizeArabic((string) $row['category_guess']);
                if (isset($categoryByName[$key])) {
                    $rowCategoryId = $categoryByName[$key];
                }
            }

            // سبب المراجعة: من وسوم الصف إن وُجدت، وإلا سبب الإجابة الناقصة
            $reviewNote = null;
            if ($needsReview) {
                $rowIssues = json_decode((string) ($row['issues'] ?? ''), true);
                $noteText = is_array($rowIssues) && $rowIssues !== []
                    ? implode(' | ', array_map('strval', $rowIssues))
                    : 'مستورد من ملف: الإجابة غير مؤكدة';
                $reviewNote = mb_substr($noteText, 0, 250, 'UTF-8');
            }

            try {
                $questionId = $repo->create([
                    'track_id'       => $trackId,
                    'category_id'    => $rowCategoryId > 0 ? $rowCategoryId : null,
                    'source_id'      => $sourceId > 0 ? $sourceId : null,
                    'question_text'  => $questionText,
                    // سؤال باختيارين فقط (صح/خطأ) يُسجَّل بنوعه الصحيح
                    'question_type'  => (($row['option_c'] ?? null) === null || (string) $row['option_c'] === '')
                                        && (($row['option_d'] ?? null) === null || (string) $row['option_d'] === '')
                                        ? 'true_false' : 'mcq',
                    'option_a'       => (string) $row['option_a'],
                    'option_b'       => (string) $row['option_b'],
                    'option_c'       => $row['option_c'] !== null ? (string) $row['option_c'] : null,
                    'option_d'       => $row['option_d'] !== null ? (string) $row['option_d'] : null,
                    'correct_answer' => $row['correct_answer'],
                    'explanation'    => $row['explanation'],
                    'difficulty'     => $row['difficulty'] ?: $difficulty,
                    'source_page'    => $row['source_page'],
                    'needs_review'   => $needsReview ? 1 : 0,
                    'review_note'    => $reviewNote,
                    'active'         => 1,
                    'content_hash'   => $hash,
                    'created_by'     => $adminId,
                    'updated_by'     => $adminId,
                ]);
            } catch (\Throwable $exception) {
                $skipped++;
                $warnings[] = 'الصف ' . (int) $row['row_number'] . ': فشل الإدخال (' . $exception->getMessage() . ').';
                continue;
            }

            $db->update('import_staging', [
                'status'      => 'imported',
                'question_id' => $questionId,
            ], 'id = :id', ['id' => (int) $row['id']]);
            $imported++;
        }

        self::updateBatchCounters($batchId, [
            'status' => $imported > 0 ? 'imported' : 'previewed',
            'report' => json_encode(['imported' => $imported, 'skipped' => $skipped, 'at' => date('c')], JSON_UNESCAPED_UNICODE),
        ]);

        return [
            'ok'       => $imported > 0,
            'message'  => $imported > 0
                ? 'تم إدخال ' . $imported . ' سؤالاً بنجاح' . ($skipped > 0 ? ' وتخطي ' . $skipped . ' صفاً.' : '.')
                : 'لم يتم إدخال أي سؤال. راجع التحذيرات.',
            'imported' => $imported,
            'skipped'  => $skipped,
            'warnings' => array_slice($warnings, 0, 50),
        ];
    }

    /** إحصاءات صفوف دُفعة */
    public static function batchStats(int $batchId): array
    {
        $db = Database::instance();
        return [
            'total'      => (int) $db->value('SELECT COUNT(*) FROM `import_staging` WHERE batch_id = :id', ['id' => $batchId], 0),
            'pending'    => (int) $db->value("SELECT COUNT(*) FROM `import_staging` WHERE batch_id = :id AND status = 'pending'", ['id' => $batchId], 0),
            'approved'   => (int) $db->value("SELECT COUNT(*) FROM `import_staging` WHERE batch_id = :id AND status = 'approved'", ['id' => $batchId], 0),
            'rejected'   => (int) $db->value("SELECT COUNT(*) FROM `import_staging` WHERE batch_id = :id AND status = 'rejected'", ['id' => $batchId], 0),
            'imported'   => (int) $db->value("SELECT COUNT(*) FROM `import_staging` WHERE batch_id = :id AND status = 'imported'", ['id' => $batchId], 0),
            'invalid'    => (int) $db->value('SELECT COUNT(*) FROM `import_staging` WHERE batch_id = :id AND is_valid = 0', ['id' => $batchId], 0),
            'duplicates' => (int) $db->value('SELECT COUNT(*) FROM `import_staging` WHERE batch_id = :id AND is_duplicate = 1', ['id' => $batchId], 0),
            'needs_review' => (int) $db->value('SELECT COUNT(*) FROM `import_staging` WHERE batch_id = :id AND needs_review = 1', ['id' => $batchId], 0),
        ];
    }
}
