<?php
declare(strict_types=1);

namespace App;

/**
 * مستورد بنك أسئلة قديم مكتوب بصيغة SQL (نسخة احتياطية أو تجميعات جاهزة).
 *
 * الملف القديم المتوقّع يحتوي عادةً على جملتين من هذا الشكل:
 *
 *   INSERT INTO tracks (name, slug, description, display_order) VALUES ('التربوي العام', 'general', '...', 1), ...;
 *   INSERT INTO questions (id, category, source, stem, option_a, option_b, option_c, option_d, correct_answer)
 *   VALUES (1, 'تجميعات ...', 'اديوميتر', 'نص السؤال', 'أ', 'ب', 'ج', 'د', NULL), ...;
 *
 * وهو لا يصلح للإدخال المباشر في هذه المنصة، لأن:
 *   1) بنية الجداول مختلفة (tracks/categories/sources/import_staging).
 *   2) أغلب الأسئلة في هذه الملفات بلا إجابة صحيحة (correct_answer = NULL).
 *   3) النص المستخرج من PDF كثيراً ما يكون مصاباً بتشويه حروف (انقلاب الهمزة، تفكّك
 *      الحروف، تكرار الهاء)، ولا يجوز تخمين الأصل ولا تخمين الإجابة.
 *
 * لذلك تعمل هذه الأداة على ثلاث مراحل آمنة:
 *   (أ) إصلاح آلي محافظ للأخطاء الطباعية المؤكدة، مع تسجيل كل إصلاح.
 *   (ب) وسم كل سؤال بوسوم مراجعة (بلا إجابة، تشويه نصي، مجال غير محدّد...).
 *   (ج) إنتاج ملفات: تصنيفات مصحّحة، مصادر، صفوف في منطقة المراجعة
 *       (import_staging) + قائمة مراجعة بشرية CSV + تقرير عربي.
 *
 * ولا تُدخل الأداة أي سؤال إلى بنك الأسئلة إلا بعد اعتماد بشري من لوحة التحكم
 * (وبيانات المراجعة لا تكون قابلة للاعتماد قبل ضبط الإجابة الصحيحة).
 */
final class LegacyBankImporter
{
    /** إصدار أداة الاستيراد — يُطبع في ملفات الإخراج */
    public const VERSION = '1.0.0';

    /**
     * وسوم المراجعة بوصف عربي (تُخزَّن في عمود import_staging.issues كـ JSON).
     * المفاتيح ثابتة، والوصف قابل للترجمة/التعديل لاحقاً.
     */
    public const ISSUES = [
        'missing_answer'  => 'الإجابة الصحيحة غير موجودة في الملف — لا يمكن تصحيحها آلياً ولا يجوز تخمينها',
        'missing_option'  => 'أحد الاختيارات فارغ',
        'short_stem'      => 'نص السؤال قصير جداً (أقل من 15 حرفاً)',
        'he_noise'        => 'تكرار حرف الهاء (أثر استخراج PDF) — يحتاج قراءة بشرية',
        'broken_spacing'  => 'تفكّك الحروف والمسافات داخل الكلمات (أثر استخراج نص) — يحتاج قراءة بشرية',
        'split_letter'    => 'حرف منفصل تم إلصاقه آلياً — راجع الكلمة للتأكد',
        'latin_mixed'     => 'أحرف لاتينية ملتصقة بنص عربي',
        'uncategorized'   => 'لم يُتعرَّف على المجال آلياً — يحتاج تصنيفاً يدوياً',
        'aptitude_scope'  => 'سؤال قدرات/لغة عامة (خارج نطاق الرخصة المهنية) — قرار المالك',
        'duplicate'       => 'مكرر مع صف آخر في الملف نفسه',
        'answer_conflict' => 'تعارض بين الإجابة الموسومة في الملف والشرح أو الخيارات — يحتاج قراراً بشرياً',
    ];

    /** الحروف التي لا تصح كلمةً مستقلة في العربية — إلصاقها بما بعدها إصلاح مؤكد */
    private const JOINABLE_LONE_LETTERS = 'ابتثجحخدذرزسشصضطظعغقمنهيىةءأإآٱ';

    /** تطويل ومحارف اتجاهية ومحارف صفرية العرض */
    private const INVISIBLE = ["\u{0640}", "\u{200B}", "\u{200C}", "\u{200D}", "\u{200E}", "\u{200F}", "\u{202A}", "\u{202B}", "\u{202C}", "\u{2066}", "\u{2067}", "\u{2068}", "\u{2069}", "\u{FEFF}"];

    // =====================================================================
    //  1) التصنيف الآلي المقترح (يُبنى على المجالات الموجودة في database.sql)
    // =====================================================================

    /**
     * خريطة: رمز المجال في المنصة => كلمات مفتاحية مميّزة.
     * ملاحظة: كلمات مثل «طالب/معلم/درس» لا تُستخدم وحدها لأنها في كل سؤال تقريباً.
     * @return array<string,array{name:string,keywords:array<int,string>}>
     */
    public static function categoryMap(): array
    {
        return [
            'curriculum-methods' => ['name' => 'المناهج وطرق التدريس', 'keywords' => [
                'استراتيجية', 'استراتيجيات', 'طريقة التدريس', 'طرق التدريس', 'تخطيط الدرس', 'خطة الدرس',
                'تخطيط', 'منهج', 'مناهج', 'وحدة دراسية', 'أهداف الدرس', 'أهداف تعليمية', 'هدف سلوكي',
                'بلوم', 'تصنيف بلوم', 'تحليل المحتوى', 'تنظيم المحتوى', 'أنشطة تعليمية', 'نشاط تعليمي',
                'وسائل تعليمية', 'تمهيد', 'تنفيذ الدرس', 'خرائط المفاهيم', 'خريطة مفاهيم', 'التعلم التعاوني',
                'العصف الذهني', 'حل المشكلات', 'الاستقصاء', 'الاكتشاف', 'التعلم النشط', 'المشاريع',
                'التدريس المباشر', 'دورة التعلم', 'المنظم المتقدم', 'التعليم المبرمج', 'الفصل المقلوب',
            ]],
            'assessment' => ['name' => 'القياس والتقويم', 'keywords' => [
                'تقويم', 'قياس', 'اختبار', 'معامل الصعوبة', 'معامل التمييز', 'الثبات', 'الصدق',
                'الموضوعية', 'سلم تقدير', 'سلالم التقدير', 'ملف الإنجاز', 'ملفات الإنجاز', 'قوائم الرصد',
                'قائمة الشطب', 'السجل القصصي', 'تغذية راجعة', 'تصحيح', 'الدرجات', 'محكي المرجع',
                'معياري المرجع', 'رتبة مئينية', 'الوسيط', 'المتوسط الحسابي', 'المنوال', 'الانحراف المعياري',
                'نسبة مئوية', 'جدول المواصفات', 'أسئلة مقالية', 'الصواب والخطأ', 'الاختيار من متعدد',
                'التقويم التشخيصي', 'التقويم التكويني', 'التقويم الختامي', 'تقويم الأقران', 'التقويم البديل',
            ]],
            'educational-psychology' => ['name' => 'علم النفس التربوي', 'keywords' => [
                'بياجيه', 'فيجوتسكي', 'برونر', 'ثورندايك', 'سكنر', 'باندورا', 'أريكسون', 'ماسلو',
                'جشطالت', 'الجشطالت', 'نظرية التعلم', 'نظريات التعلم', 'الدافعية', 'دافعية', 'التعزيز',
                'العقاب', 'الاشتراط', 'الذاكرة', 'النمو المعرفي', 'مراحل النمو', 'الحاجات', 'حاجات',
                'تعديل السلوك', 'التعلم ذو المعنى', 'أوزبل', 'النمذجة', 'الفروق الفردية',
                'التعلم بالملاحظة', 'التقليد', 'الملاحظة والتقليد',
            ]],
            'school-leadership' => ['name' => 'الإدارة المدرسية والقيادة التربوية', 'keywords' => [
                'مدير المدرسة', 'قائد المدرسة', 'قائدة المدرسة', 'إدارة المدرسة', 'وكيل المدرسة',
                'مجلس المدرسة', 'لجنة', 'رؤية المدرسة', 'القرار الإداري', 'الشراكة المجتمعية',
                'أولياء الأمور', 'مجتمع المدرسة', 'القيادة', 'الاجتماع المدرسي',
            ]],
            'professional-ethics' => ['name' => 'أخلاقيات المهنة', 'keywords' => [
                'أخلاقيات', 'ميثاق', 'سياسة التعليم', 'المواطنة', 'الهوية الوطنية', 'القيم', 'انتماء',
                'حقوق وواجبات', 'الخدمة المدنية', 'اللائحة', 'رخصة مهنية', 'وسطية', 'التطرف', 'الولاء',
                'الانتماء', 'القدوة', 'المسؤولية المهنية',
            ]],
            'instructional-technology' => ['name' => 'تكنولوجيا التعليم', 'keywords' => [
                'التقنية', 'تقنيات التعليم', 'الحاسوب', 'الإنترنت', 'منصة', 'الإلكتروني', 'إلكتروني',
                'البرمجيات', 'الوسائط', 'الرقمية', 'المواطنة الرقمية', 'معايير تكنولوجيا', 'الحوسبة السحابية',
                'الواقع المعزز', 'المدونة', 'الفيديو التعليمي', 'الفصول الافتراضية', 'التعليم عن بعد',
                'السبورة التفاعلية', 'برنامج', 'تطبيق',
            ]],
            'individual-differences' => ['name' => 'الفروق الفردية وصعوبات التعلم', 'keywords' => [
                'صعوبات التعلم', 'صعوبات تعلم', 'الموهوبين', 'الموهوبون', 'موهوب', 'فرط الحركة',
                'التوحد', 'الإعاقة', 'إعاقة', 'ديسلكسيا', 'الفروق الفردية', 'بطء التعلم', 'الذكاءات المتعددة',
                'نسبة الذكاء', 'القدرات العقلية', 'التأخر الدراسي', 'تأخر دراسي', 'الاحتياجات الخاصة',
                'الحتياجات الخاصة', 'الدمج', 'التربية الخاصة',
            ]],
            'classroom-management' => ['name' => 'الإدارة الصفية', 'keywords' => [
                'إدارة الصف', 'الإدارة الصفية', 'ضبط الصف', 'الانضباط', 'قواعد صفية', 'السلوك غير المرغوب',
                'التنمر', 'المشكلات السلوكية', 'المناخ الصفي', 'البيئة الصفية', 'تنويع المثيرات',
                'إثارة الانتباه', 'السلوك العدواني', 'التعامل مع الطلاب',
            ]],
            'educational-research' => ['name' => 'البحث التربوي وتطوير الأداء المهني', 'keywords' => [
                'البحث الإجرائي', 'بحث إجرائي', 'البحث الوصفي', 'البحث التجريبي', 'عينة', 'العينة',
                'الاستبانة', 'التأمل الذاتي', 'التأمل في التدريس', 'التنمية المهنية', 'التطوير المهني',
                'النمو المهني', 'مجتمعات التعلم', 'التنمية المستدامة', 'الزيارة الإشرافية', 'المشرف التربوي',
                'ملف إنجاز المعلم', 'التقويم الذاتي للمعلم', 'الرتب المهنية', 'المعايير المهنية',
            ]],
        ];
    }

    // =====================================================================
    //  2) تحليل ملف SQL قديم
    // =====================================================================

    /**
     * تحليل أي ملف SQL إلى جمل INSERT فقط، مجمّعة باسم الجدول.
     * (مفيد للملفات ذات المخططات المختلفة: question/choix/explication ...)
     * @return array<string,array<int,array<string,mixed>>>
     */
    public static function parseSqlFile(string $sql): array
    {
        $sql = str_replace(["\r\n", "\r"], "\n", $sql);
        $tables = [];
        foreach (self::splitStatements($sql) as $statement) {
            if (!preg_match('/INSERT\s+INTO\s+`?([a-zA-Z0-9_]+)`?\s*\(([^)]*)\)\s*VALUES\s*(.+)$/is', $statement, $match)) {
                continue;
            }
            $table = strtolower($match[1]);
            $columns = array_map(
                static fn(string $column): string => trim($column, " \t\n`"),
                explode(',', $match[2])
            );
            foreach (self::parseTuples($match[3]) as $values) {
                $row = [];
                foreach ($columns as $index => $column) {
                    $row[$column] = $values[$index] ?? null;
                }
                $tables[$table][] = $row;
            }
        }
        return $tables;
    }

    /**
     * تحليل نص ملف SQL قديم وإرجاع الصفوف الخام.
     * @return array{tracks:array<int,array<string,string|null>>,questions:array<int,array<string,mixed>>,statements:int}
     */
    public static function parseLegacySql(string $sql): array
    {
        $sql = str_replace(["\r\n", "\r"], "\n", $sql);
        $tracks = [];
        $questions = [];

        foreach (self::splitStatements($sql) as $statement) {
            if (!preg_match('/INSERT\s+INTO\s+`?([a-zA-Z0-9_]+)`?\s*\(([^)]*)\)\s*VALUES\s*(.+)$/is', $statement, $match)) {
                continue;
            }
            $table = strtolower($match[1]);
            $columns = array_map(
                static fn(string $column): string => trim($column, " \t\n`"),
                explode(',', $match[2])
            );
            $tuples = self::parseTuples($match[3]);
            foreach ($tuples as $values) {
                $row = [];
                foreach ($columns as $index => $column) {
                    $row[$column] = $values[$index] ?? null;
                }
                if ($table === 'tracks') {
                    $tracks[] = $row;
                } elseif ($table === 'questions') {
                    $questions[] = $row;
                }
            }
        }

        return ['tracks' => $tracks, 'questions' => $questions, 'statements' => count(self::splitStatements($sql))];
    }

    /** تقسيم الملف إلى جمل SQL منتهية بفاصلة منقوطة خارج النصوص */
    public static function splitStatements(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $inString = false;
        $length = strlen($sql);
        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            if ($inString) {
                $buffer .= $char;
                if ($char === '\\' && $i + 1 < $length) {
                    $buffer .= $sql[++$i];
                    continue;
                }
                if ($char === "'") {
                    if ($i + 1 < $length && $sql[$i + 1] === "'") {
                        $buffer .= $sql[++$i];
                        continue;
                    }
                    $inString = false;
                }
                continue;
            }
            if ($char === "'" ) {
                $inString = true;
                $buffer .= $char;
                continue;
            }
            if ($char === ';') {
                $trimmed = trim($buffer);
                if ($trimmed !== '') {
                    $statements[] = $trimmed;
                }
                $buffer = '';
                continue;
            }
            $buffer .= $char;
        }
        $trimmed = trim($buffer);
        if ($trimmed !== '') {
            $statements[] = $trimmed;
        }
        return $statements;
    }

    /**
     * تحليل قائمة القيم "(...) , (...)" وإرجاع صفوف القيم.
     * @return array<int,array<int,mixed>>
     */
    public static function parseTuples(string $values): array
    {
        $rows = [];
        $current = [];
        $buffer = '';
        $inString = false;
        $depth = 0;
        $length = strlen($values);

        $flushValue = static function () use (&$buffer, &$current): void {
            $current[] = self::castValue(trim($buffer));
            $buffer = '';
        };

        for ($i = 0; $i < $length; $i++) {
            $char = $values[$i];
            if ($inString) {
                if ($char === '\\' && $i + 1 < $length) {
                    $buffer .= self::unescape($values[$i + 1]);
                    $i++;
                    continue;
                }
                if ($char === "'") {
                    if ($i + 1 < $length && $values[$i + 1] === "'") {
                        $buffer .= "'";
                        $i++;
                        continue;
                    }
                    $inString = false;
                    continue;
                }
                $buffer .= $char;
                continue;
            }
            if ($char === "'") {
                $inString = true;
                continue;
            }
            if ($char === '(') {
                $depth++;
                if ($depth === 1) {
                    $buffer = '';
                    $current = [];
                    continue;
                }
            }
            if ($char === ')') {
                $depth--;
                if ($depth === 0) {
                    $flushValue();
                    $rows[] = $current;
                    $current = [];
                    $buffer = '';
                    continue;
                }
            }
            if ($char === ',' && $depth === 1) {
                $flushValue();
                continue;
            }
            if ($depth >= 1) {
                $buffer .= $char;
            }
        }
        return $rows;
    }

    private static function unescape(string $char): string
    {
        return match ($char) {
            'n' => "\n",
            'r' => "\r",
            't' => "\t",
            '0' => '',
            default => $char,
        };
    }

    private static function castValue(string $value): mixed
    {
        if ($value === '' || strcasecmp($value, 'NULL') === 0) {
            return null;
        }
        if (is_numeric($value) && !str_starts_with($value, '0') && strlen($value) < 12) {
            return str_contains($value, '.') ? (float) $value : (int) $value;
        }
        return $value;
    }

    // =====================================================================
    //  3) إصلاح النص العربي (محافظ: كل إصلاح مسجَّل، والمشكوك يُوسَم لا يُخمَّن)
    // =====================================================================

    /**
     * @return array{text:string,fixes:array<string,int>}
     */
    public static function repair(string $text): array
    {
        $fixes = [];
        $original = $text;

        $text = str_replace(self::INVISIBLE, '', $text);

        // (أ) انقلاب «الأ/الإ/الآ» إلى «األ/اإل/اآل» — أشهر تشويه في استخراج PDF عربي
        $text = (string) preg_replace_callback(
            '/\x{0627}([\x{0623}\x{0625}\x{0622}\x{0671}])\x{0644}/u',
            static function (array $m) use (&$fixes): string {
                $fixes['hamza_order'] = ($fixes['hamza_order'] ?? 0) + 1;
                return "\u{0627}\u{0644}" . $m[1];
            },
            $text
        );

        // (ب) انقلاب «الا» إلى «اال»
        $text = (string) preg_replace_callback(
            '/\x{0627}\x{0627}\x{0644}/u',
            static function () use (&$fixes): string {
                $fixes['alef_lam_order'] = ($fixes['alef_lam_order'] ?? 0) + 1;
                return "\u{0627}\u{0644}\u{0627}";
            },
            $text
        );

        // (ج) إلصاق «ء» المفصولة بآخر الكلمة: «االستقصا ء» -> «الاستقصاء»
        $text = (string) preg_replace_callback(
            '/(?<=\p{Arabic})\s+\x{0621}/u',
            static function () use (&$fixes): string {
                $fixes['joined_hamza'] = ($fixes['joined_hamza'] ?? 0) + 1;
                return "\u{0621}";
            },
            $text
        );

        // (د) إلصاق اللواحق المنفصلة بحرف واحد بآخر الكلمة: «األسئل ة» -> «الأسئلة»
        $text = (string) preg_replace_callback(
            '/(?<=\p{Arabic})\s+([\x{0629}\x{0649}\x{0648}\x{064A}])(?=\s|$)/u',
            static function (array $m) use (&$fixes): string {
                $fixes['joined_suffix'] = ($fixes['joined_suffix'] ?? 0) + 1;
                return $m[1];
            },
            $text
        );

        // (هـ) حرفان منفصلان متجاوران يشكّلان كلمة واحدة: «م ن» -> «من» (الأكثر شيوعاً في استخراج PDF)
        $letters = self::JOINABLE_LONE_LETTERS;
        $pairPattern = '/(?<![\p{Arabic}])([' . $letters . ']) ([\x{0621}' . $letters . '])(?![\p{Arabic}])/u';
        for ($pass = 0; $pass < 3; $pass++) {
            $before = $text;
            $text = (string) preg_replace_callback(
                $pairPattern,
                static function (array $m) use (&$fixes): string {
                    $fixes['joined_letter'] = ($fixes['joined_letter'] ?? 0) + 1;
                    return $m[1] . $m[2];
                },
                $text
            );
            if ($text === $before) {
                break;
            }
        }

        // (و) حرف مفصول عن بداية كلمة تالية: «ه ذا» -> «هذا» ، «ا لمنهج» -> «المنهج»
        $text = (string) preg_replace_callback(
            '/(?<![\p{Arabic}])([' . $letters . ']) ([\p{Arabic}]{2,})/u',
            static function (array $m) use (&$fixes): string {
                $fixes['joined_letter'] = ($fixes['joined_letter'] ?? 0) + 1;
                return $m[1] . $m[2];
            },
            $text
        );

        // (و) مسافة قبل علامات الترقيم العربية
        $text = (string) preg_replace_callback(
            '/\s+([\x{060C}\x{061B}\x{061F}:])/u',
            static function (array $m) use (&$fixes): string {
                $fixes['space_before_punct'] = ($fixes['space_before_punct'] ?? 0) + 1;
                return $m[1];
            },
            $text
        );

        // (ز) مسافة ناقصة بعد علامة الترقيم
        $text = (string) preg_replace_callback(
            '/([\x{060C}\x{061B}\x{061F}])(?=[\p{Arabic}\p{N}])/u',
            static function (array $m) use (&$fixes): string {
                $fixes['space_after_punct'] = ($fixes['space_after_punct'] ?? 0) + 1;
                return $m[1] . ' ';
            },
            $text
        );

        // (ح) توحيد المسافات ومحارف البدائل
        $text = str_replace(["\u{00A0}", "\u{2000}", "\u{2001}", "\u{2002}", "\u{2003}"], ' ', $text);
        $text = (string) preg_replace('/[ \t]+/u', ' ', $text);
        $text = (string) preg_replace('/\s*\n\s*/u', ' ', $text);
        $text = trim($text);

        if ($text !== $original) {
            $fixes['changed'] = 1;
        }
        return ['text' => $text, 'fixes' => $fixes];
    }

    /**
     * وسوم المراجعة النصية لسؤال بعد الإصلاح.
     * @return array<int,string>
     */
    public static function textIssues(string $text): array
    {
        $issues = [];
        if ($text === '') {
            return ['broken_spacing'];
        }
        // تكرار الهاء — بصمة هذا النوع من الاستخراج، ولا يصح «هه» في العربية عادة
        if (preg_match('/\x{0647}\x{0647}/u', $text)) {
            $issues[] = 'he_noise';
        }
        // حرف منفصل لم يُلصق (بعد الإصلاح) أي «س ع د» أو «ال طالب»
        if (preg_match('/(?<![\p{Arabic}])[' . self::JOINABLE_LONE_LETTERS . '](?![\p{Arabic}])/u', $text)) {
            $issues[] = 'broken_spacing';
        }
        // «ال» معرفة منفصلة عن اسمها
        if (preg_match('/\x{0627}\x{0644}\s+(?=[\p{Arabic}])/u', $text)) {
            $issues[] = 'broken_spacing';
        }
        // تكرار حرف ثلاث مرات (أثر استخراج)
        if (preg_match('/([\p{Arabic}])\1\1/u', $text)) {
            $issues[] = 'broken_spacing';
        }
        // لاتيني ملتصق بعربي
        if (preg_match('/\p{Arabic}\p{Latin}|\p{Latin}\p{Arabic}/u', $text)) {
            $issues[] = 'latin_mixed';
        }
        return array_values(array_unique($issues));
    }

    // =====================================================================
    //  4) التصنيف الآلي
    // =====================================================================

    /**
     * @param array<string,array{name:string,keywords:array<int,string>}>|null $map
     * @return array{code:?string,name:?string,score:int,scores:array<string,int>}
     */
    public static function classify(string $text, ?array $map = null, int $minScore = 2): array
    {
        $map ??= self::categoryMap();
        $normalized = Str::normalizeArabic($text);
        $scores = [];
        foreach ($map as $code => $definition) {
            $score = 0;
            foreach ($definition['keywords'] as $keyword) {
                $needle = Str::normalizeArabic($keyword);
                if ($needle !== '' && str_contains($normalized, $needle)) {
                    $score++;
                }
            }
            $scores[$code] = $score;
        }
        arsort($scores);
        $best = array_key_first($scores) ?? null;
        $bestScore = $best !== null ? (int) $scores[$best] : 0;
        $result = ['code' => null, 'name' => null, 'score' => $bestScore, 'scores' => $scores];
        if ($best !== null && $bestScore >= $minScore) {
            $result['code'] = (string) $best;
            $result['name'] = $map[$best]['name'];
        }
        return $result;
    }

    // =====================================================================
    //  5) المعالجة الكاملة
    // =====================================================================

    /**
     * @param array<int,array<string,mixed>> $rows صفوف خام من parseLegacySql()
     * @param array<string,mixed> $options
     *        - category_map    : خريطة تصنيف بديلة
     *        - min_score       : حد أدنى لنقاط التصنيف (افتراضي 2)
     *        - dedupe          : true/false (افتراضي true)
     *        - similarity      : نسبة التشابه لاعتبار السؤال مكرراً (افتراضي 0.92)
     * @return array{rows:array<int,array<string,mixed>>,summary:array<string,mixed>}
     */
    public static function process(array $rows, array $options = []): array
    {
        $map = $options['category_map'] ?? self::categoryMap();
        $minScore = (int) ($options['min_score'] ?? 2);
        $dedupe = (bool) ($options['dedupe'] ?? true);
        $similarity = (float) ($options['similarity'] ?? 0.92);

        $processed = [];
        $seenHash = [];
        $seenNormalized = [];
        $summary = [
            'total' => 0, 'valid' => 0, 'invalid' => 0, 'needs_review' => 0,
            'missing_answer' => 0, 'duplicates_exact' => 0, 'duplicates_near' => 0,
            'flagged_text' => 0, 'uncategorized' => 0, 'aptitude' => 0,
            'by_source' => [], 'by_category' => [], 'repairs' => [],
            'answer_key_present' => 0,
        ];

        $rowNumber = 0;
        foreach ($rows as $row) {
            $rawStem = (string) ($row['stem'] ?? '');
            if (trim($rawStem) === '' && trim((string) ($row['question_text'] ?? '')) === '') {
                continue;
            }
            $rowNumber++;
            $summary['total']++;

            $stem = self::repair($rawStem);
            $optionsText = [];
            foreach (['a', 'b', 'c', 'd'] as $letter) {
                $rawOption = (string) ($row['option_' . $letter] ?? '');
                $repaired = self::repair($rawOption);
                $optionsText[$letter] = $repaired['text'];
                foreach ($repaired['fixes'] as $key => $count) {
                    $summary['repairs'][$key] = ($summary['repairs'][$key] ?? 0) + $count;
                }
            }
            foreach ($stem['fixes'] as $key => $count) {
                $summary['repairs'][$key] = ($summary['repairs'][$key] ?? 0) + $count;
            }

            $sourceName = trim((string) ($row['source'] ?? '')) ?: 'غير محدد';
            $legacyCategory = trim((string) ($row['category'] ?? '')) ?: 'غير مصنّف';

            $questionType = self::isTrueFalse($optionsText) ? 'true_false' : 'mcq';
            if ($questionType === 'true_false') {
                $summary['true_false'] = ($summary['true_false'] ?? 0) + 1;
            }

            $answer = self::normalizeAnswer($row['correct_answer'] ?? null)
                ?? self::resolveAnswerByText($row['correct_answer'] ?? null, $optionsText);
            $issues = [];
            if ($answer === null) {
                $issues[] = 'missing_answer';
                $summary['missing_answer']++;
            } else {
                $summary['answer_key_present']++;
            }
            // في سؤال mcq يلزم اختياران على الأقل؛ وفي «صح/خطأ» اكتمل الاختياران
            if ($questionType === 'mcq') {
                foreach (['a', 'b', 'c'] as $letter) {
                    if ($optionsText[$letter] === '') {
                        $issues[] = 'missing_option';
                        break;
                    }
                }
            }

            $combined = trim($stem['text'] . ' ' . implode(' ', $optionsText));
            $issues = array_merge($issues, self::textIssues($combined));
            if (in_array('he_noise', $issues, true) || in_array('broken_spacing', $issues, true)) {
                $summary['flagged_text']++;
            }
            if (mb_strlen($stem['text']) < 15) {
                $issues[] = 'short_stem';
            }
            $classification = self::classify($stem['text'] . ' ' . implode(' ', $optionsText), $map, $minScore);
            if ($classification['code'] === null) {
                $issues[] = 'uncategorized';
                $summary['uncategorized']++;
            }
            $categoryLabel = $classification['name'] ?? 'غير مصنّف';
            $summary['by_category'][$categoryLabel] = ($summary['by_category'][$categoryLabel] ?? 0) + 1;
            // وسم «قدرات عامة» يكون بالمحتوى فقط: سؤال رياضيات/لغة لم يُصنَّف على أي مجال تربوي
            if ($classification['code'] === null && self::looksAptitude($stem['text'])) {
                $issues[] = 'aptitude_scope';
                $summary['aptitude']++;
            }

            $hash = Str::contentFingerprint($stem['text']);
            $normalizedStem = self::normalizedStem($stem['text']);
            $duplicateOf = null;
            $isDuplicate = false;
            if ($dedupe) {
                if (isset($seenHash[$hash])) {
                    $isDuplicate = true;
                    $duplicateOf = $seenHash[$hash];
                    $summary['duplicates_exact']++;
                } else {
                    foreach ($seenNormalized as $candidate) {
                        if (abs(strlen($candidate['text']) - strlen($normalizedStem)) > 8) {
                            continue;
                        }
                        similar_text($candidate['text'], $normalizedStem, $percent);
                        if ($percent >= $similarity * 100) {
                            $isDuplicate = true;
                            $duplicateOf = $candidate['row'];
                            $summary['duplicates_near']++;
                            break;
                        }
                    }
                }
                if (!$isDuplicate) {
                    $seenHash[$hash] = $rowNumber;
                    $seenNormalized[] = ['row' => $rowNumber, 'text' => $normalizedStem];
                } else {
                    $issues[] = 'duplicate';
                }
            }

            $issues = array_values(array_unique($issues));
            $isValid = !in_array('missing_option', $issues, true)
                && !in_array('short_stem', $issues, true)
                && mb_strlen($stem['text']) >= 10;

            $needsReview = $isDuplicate
                || $answer === null
                || in_array('he_noise', $issues, true)
                || in_array('broken_spacing', $issues, true)
                || in_array('split_letter', $issues, true)
                || in_array('uncategorized', $issues, true);

            if ($isValid) {
                $summary['valid']++;
            } else {
                $summary['invalid']++;
            }
            if ($needsReview) {
                $summary['needs_review']++;
            }
            $summary['by_source'][$sourceName] = ($summary['by_source'][$sourceName] ?? 0) + 1;

            $processed[] = [
                'legacy_id'       => isset($row['id']) ? (int) $row['id'] : null,
                'row_number'      => $rowNumber,
                'raw_stem'        => $rawStem,
                'question_text'   => $stem['text'],
                'options'         => $optionsText,
                'correct_answer'  => $answer,
                'explanation'     => null,
                'source_name'     => $sourceName,
                'legacy_category' => $legacyCategory,
                'category_code'   => $classification['code'],
                'category_name'   => $classification['name'],
                'category_score'  => $classification['score'],
                'difficulty'      => null,
                'question_type'   => $questionType,
                'content_hash'    => $hash,
                'issues'          => $issues,
                'needs_review'    => $needsReview ? 1 : 0,
                'is_valid'        => $isValid ? 1 : 0,
                'is_duplicate'    => $isDuplicate ? 1 : 0,
                'duplicate_of'    => $duplicateOf,
            ];
        }

        $summary['unique'] = $summary['total'] - $summary['duplicates_exact'] - $summary['duplicates_near'];
        arsort($summary['by_source']);
        arsort($summary['by_category']);
        return ['rows' => $processed, 'summary' => $summary];
    }

    /** هل يبدو السؤال من نوع «القدرات العامة» (رياضيات/لغة) بدل التربية؟ */
    public static function looksAptitude(string $text): bool
    {
        $markers = [
            'احسب', 'أوجد قيمة', 'أوجد ناتج', 'كم تبلغ', 'كم عدد', 'مساحة', 'محيط', 'حجم المكعب',
            'الأعداد الأولية', 'القاسم المشترك', 'المضاعف المشترك', 'التناسب الطردي', 'التناسب العكسي',
            'همزة الوصل', 'همزة القطع', 'الضاد والظاء', 'إعراب', 'مرادف كلمة', 'مضاد كلمة',
            'الكلمة الشاذة', 'الخطأ الإملائي', 'اكتب الكلمة', 'التشبيه', 'الاستعارة', 'الكناية',
            'زمن القطار', 'سرعة السيارة', 'سلسلة الأعداد', 'النمط التالي',
        ];
        foreach ($markers as $marker) {
            if (str_contains($text, $marker)) {
                return true;
            }
        }
        return false;
    }

    /**
     * كشف أسئلة الصواب/الخطأ: اختياران فقط وهما من أزواج «صح/خطأ» المعروفة.
     * @param array{a:string,b:string,c:string,d:string} $options
     */
    public static function isTrueFalse(array $options): bool
    {
        if ($options['a'] === '' || $options['b'] === '' || $options['c'] !== '' || $options['d'] !== '') {
            return false;
        }
        $normalize = static fn(string $value): string => Str::normalizeArabic(mb_strtolower(trim($value), 'UTF-8'));
        $a = $normalize($options['a']);
        $b = $normalize($options['b']);
        $pairs = [
            ['صح', 'خطا'], ['صواب', 'خطا'], ['صحيح', 'خطا'], ['صح', 'غير صحيح'],
            ['نعم', 'لا'], ['true', 'false'], ['صح', 'خاطي'], ['صحيح', 'غير صحيح'],
        ];
        foreach ($pairs as [$first, $second]) {
            $first = $normalize($first);
            $second = $normalize($second);
            if (($a === $first || str_starts_with($a, $first)) && ($b === $second || str_contains($b, $second))) {
                return true;
            }
            if (($b === $first || str_starts_with($b, $first)) && ($a === $second || str_contains($a, $second))) {
                return true;
            }
        }
        return false;
    }

    /** تطبيع للمقارنة بين الأسئلة (دالة المنصة نفسها) */
    public static function normalizedStem(string $text): string
    {
        $normalized = Str::normalizeArabic($text);
        $normalized = mb_strtolower($normalized, 'UTF-8');
        $normalized = (string) preg_replace('/[^\p{L}\p{N} ]+/u', '', $normalized);
        return trim((string) preg_replace('/\s+/u', ' ', $normalized));
    }

    /**
     * تحويل قيمة الإجابة في الملف القديم إلى حرف a/b/c/d.
     * يقبل: a, B, 'ج', 'د', 'الإجابة: ج', '3' ... ويرجع null إن لم تتضح (لا تخمين).
     */
    public static function normalizeAnswer(mixed $answer): ?string
    {
        if ($answer === null) {
            return null;
        }
        $raw = trim((string) $answer);
        if ($raw === '') {
            return null;
        }
        $map = [
            'أ' => 'a', 'ا' => 'a', 'آ' => 'a', 'إ' => 'a', '١' => 'a', '1' => 'a',
            'ب' => 'b', '٢' => 'b', '2' => 'b',
            'ج' => 'c', '٣' => 'c', '3' => 'c',
            'د' => 'd', '٤' => 'd', '4' => 'd',
        ];
        $single = mb_strtolower($raw, 'UTF-8');
        if (isset($map[$raw])) {
            return $map[$raw];
        }
        if (isset($map[$single]) || array_key_exists($single, ['a' => 1, 'b' => 1, 'c' => 1, 'd' => 1])) {
            return $single;
        }
        // «الإجابة: ج» / «(ج)» / «الخيار ج» — نأخذ أول حرف اختيار يظهر
        if (preg_match('/[\p{Arabic}a-d]/u', $raw, $matches) === 1) {
            $first = $matches[0];
            $candidate = mb_strtolower($first, 'UTF-8');
            if (isset($map[$first])) {
                return $map[$first];
            }
            if (in_array($candidate, ['a', 'b', 'c', 'd'], true)) {
                return $candidate;
            }
        }
        return null;
    }

    /**
     * مطابقة الإجابة إن جاءت «نصاً» لا «حرفاً» (مثل: صواب / خطأ / العصف الذهني).
     * المطابقة تامة بعد التطبيع، وإلا تُرجع null (لا تخمين).
     * @param array{a:string,b:string,c:string,d:string} $options
     */
    public static function resolveAnswerByText(mixed $answer, array $options): ?string
    {
        if ($answer === null) {
            return null;
        }
        $raw = trim((string) $answer);
        if ($raw === '') {
            return null;
        }
        $target = Str::normalizeArabic(mb_strtolower($raw, 'UTF-8'));
        $target = trim((string) preg_replace('/[^\p{L}\p{N} ]+/u', '', $target));
        foreach (['a', 'b', 'c', 'd'] as $letter) {
            $option = $options[$letter] ?? '';
            if ($option === '') {
                continue;
            }
            $candidate = Str::normalizeArabic(mb_strtolower($option, 'UTF-8'));
            $candidate = trim((string) preg_replace('/[^\p{L}\p{N} ]+/u', '', $candidate));
            if ($candidate !== '' && ($candidate === $target || str_starts_with($target, $candidate))) {
                return $letter;
            }
        }
        return null;
    }

    /** وصف عربي لوسم */
    public static function issueLabel(string $key): string
    {
        return self::ISSUES[$key] ?? $key;
    }

    /** عدد الصفوف المستوفية لشروط الإدخال الفوري (صالحة + غير مكررة + لها إجابة + بلا وسوم مراجعة) */
    public static function countClean(array $processed): int
    {
        $count = 0;
        foreach ($processed as $row) {
            if ((int) $row['is_valid'] === 1
                && (int) $row['is_duplicate'] === 0
                && (int) $row['needs_review'] === 0
                && $row['correct_answer'] !== null) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * إعادة حساب الملخص من الصفوف المعالجة (مفيد عند تقسيم الإخراج إلى عدة ملفات).
     * @param array<int,array<string,mixed>> $processed
     * @param array<string,mixed> $extra قيم تضاف كما هي (مثل إحصاءات الإصلاحات)
     */
    public static function summarize(array $processed, array $extra = []): array
    {
        $summary = [
            'total' => 0, 'valid' => 0, 'invalid' => 0, 'needs_review' => 0,
            'missing_answer' => 0, 'duplicates_exact' => 0, 'duplicates_near' => 0,
            'flagged_text' => 0, 'uncategorized' => 0, 'aptitude' => 0, 'true_false' => 0,
            'by_source' => [], 'by_category' => [], 'repairs' => [], 'answer_key_present' => 0,
        ];
        foreach ($processed as $row) {
            $summary['total']++;
            $summary['valid'] += (int) $row['is_valid'];
            $summary['invalid'] += (int) $row['is_valid'] === 1 ? 0 : 1;
            $summary['needs_review'] += (int) $row['needs_review'];
            if ($row['correct_answer'] === null) {
                $summary['missing_answer']++;
            } else {
                $summary['answer_key_present']++;
            }
            if ((int) $row['is_duplicate'] === 1) {
                $summary['duplicates_exact']++;
            }
            if (in_array('he_noise', $row['issues'], true) || in_array('broken_spacing', $row['issues'], true)) {
                $summary['flagged_text']++;
            }
            if (in_array('uncategorized', $row['issues'], true)) {
                $summary['uncategorized']++;
            }
            if (in_array('aptitude_scope', $row['issues'], true)) {
                $summary['aptitude']++;
            }
            if (($row['question_type'] ?? 'mcq') === 'true_false') {
                $summary['true_false']++;
            }
            $source = (string) $row['source_name'];
            $summary['by_source'][$source] = ($summary['by_source'][$source] ?? 0) + 1;
            $category = (string) ($row['category_name'] ?? 'غير مصنّف');
            $summary['by_category'][$category] = ($summary['by_category'][$category] ?? 0) + 1;
        }
        $summary['unique'] = $summary['total'] - $summary['duplicates_exact'] - $summary['duplicates_near'];
        arsort($summary['by_source']);
        arsort($summary['by_category']);
        return array_merge($summary, $extra);
    }

    /**
     * تصفية الصفوف الجاهزة للإدخال الفوري.
     * @param array<int,array<string,mixed>> $processed
     * @return array<int,array<string,mixed>>
     */
    public static function cleanRows(array $processed): array
    {
        return array_values(array_filter($processed, static fn(array $row): bool =>
            (int) $row['is_valid'] === 1
            && (int) $row['is_duplicate'] === 0
            && (int) $row['needs_review'] === 0
            && $row['correct_answer'] !== null));
    }

    // =====================================================================
    //  6) مُخرَجات: SQL للمراجعة، CSV، تقرير
    // =====================================================================

    /** تهيئة نص ليكون حرفياً في SQL */
    public static function sqlString(?string $value): string
    {
        if ($value === null) {
            return 'NULL';
        }
        return "'" . str_replace(['\\', "'"], ['\\\\', "''"], $value) . "'";
    }

    /**
     * ملف SQL: مصادر + دُفعة استيراد + صفوف منطقة المراجعة.
     * لا يُدخل أي سؤال إلى جدول questions — الإدخال يتم بالاعتماد من لوحة التحكم.
     */
    public static function stagingSql(array $processed, array $summary, string $batchName, string $sourceFile, bool $withTransaction = true, int $chunk = 100): string
    {
        $lines = [];
        $lines[] = '-- =====================================================================';
        $lines[] = '--  منطقة مراجعة الأسئلة المستوردة (import_staging) — لا تُدخل الأسئلة نفسها';
        $lines[] = '--  الملف المصدر: ' . $sourceFile;
        $lines[] = '--  أداة الاستيراد: LegacyBankImporter v' . self::VERSION . ' — ' . date('Y-m-d H:i');
        $lines[] = '--  الإجمالي: ' . $summary['total'] . ' | صالح: ' . $summary['valid'] . ' | يحتاج مراجعة: ' . $summary['needs_review'];
        $lines[] = '--  الطريقة: نفّذ الملف في phpMyAdmin ثم افتح لوحة التحكم → استيراد الأسئلة → راجع الصفوف.';
        $lines[] = '-- =====================================================================';
        $lines[] = 'SET NAMES utf8mb4;';
        $lines[] = '';
        if ($withTransaction) {
            $lines[] = 'START TRANSACTION;';
            $lines[] = '';
        }

        // 1) المصادر
        $lines[] = '-- (1) المصادر: تُنشأ إن لم تكن موجودة';
        foreach (array_keys($summary['by_source']) as $sourceName) {
            $lines[] = 'INSERT INTO `sources` (`name`, `type`, `license_note`, `notes`, `is_active`)'
                . ' SELECT ' . self::sqlString($sourceName) . ", 'pdf', "
                . self::sqlString('ملف قديم قدّمه مالك المنصة — لم يُراجَع بعد. حقوق الاستخدام مسؤولية المالك.')
                . ', ' . self::sqlString('استوردته أداة الاستيراد من ملف SQL قديم.') . ', 1'
                . ' WHERE NOT EXISTS (SELECT 1 FROM `sources` WHERE `name` = ' . self::sqlString($sourceName) . ');';
        }
        $lines[] = '';

        // 2) الدُفعة
        $lines[] = '-- (2) دُفعة استيراد في منطقة المراجعة';
        $lines[] = 'INSERT INTO `import_batches` (`file_name`, `file_type`, `total_rows`, `imported_rows`,'
            . ' `duplicate_rows`, `invalid_rows`, `needs_review_rows`, `status`, `report`, `imported_by`) VALUES ('
            . self::sqlString($batchName) . ", 'json', "
            . (int) $summary['total'] . ', 0, '
            . (int) ($summary['duplicates_exact'] + $summary['duplicates_near']) . ', '
            . (int) $summary['invalid'] . ', '
            . (int) $summary['needs_review'] . ", 'previewed', "
            . self::sqlString((string) json_encode([
                'tool'      => 'LegacyBankImporter v' . self::VERSION,
                'source'    => $sourceFile,
                'missing_answer' => (int) $summary['missing_answer'],
                'uncategorized'  => (int) $summary['uncategorized'],
                'by_category'    => $summary['by_category'],
                'by_source'      => $summary['by_source'],
            ], JSON_UNESCAPED_UNICODE))
            . ', (SELECT MIN(`id`) FROM `users` WHERE `role` = \'admin\'));';
        $lines[] = 'SET @legacy_batch_id := LAST_INSERT_ID();';
        $lines[] = '';

        // 3) الصفوف — على دفعات صغيرة لتجاوز حد حجم الطلب (max_allowed_packet) في phpMyAdmin
        $lines[] = '-- (3) صفوف المراجعة (' . count($processed) . ' صفاً، كل جملة بإدخال ' . max(1, (int) $chunk) . ' صفاً)';
        $chunks = array_chunk($processed, max(1, (int) $chunk));
        foreach ($chunks as $chunkIndex => $chunkRows) {
            $lines[] = 'INSERT INTO `import_staging` (`batch_id`, `row_number`, `raw_text`, `question_text`,'
                . ' `option_a`, `option_b`, `option_c`, `option_d`, `correct_answer`, `explanation`,'
                . ' `category_guess`, `difficulty`, `source_page`, `content_hash`, `is_valid`, `is_duplicate`,'
                . ' `needs_review`, `issues`, `status`) VALUES';
            $values = [];
            foreach ($chunkRows as $row) {
                $issuesJson = $row['issues'] === []
                    ? null
                    : (string) json_encode(array_map([self::class, 'issueLabel'], $row['issues']), JSON_UNESCAPED_UNICODE);
                $values[] = '(@legacy_batch_id, ' . (int) $row['row_number'] . ', '
                    . self::sqlString($row['raw_stem']) . ', '
                    . self::sqlString($row['question_text']) . ', '
                    . self::sqlString($row['options']['a']) . ', '
                    . self::sqlString($row['options']['b']) . ', '
                    . self::sqlString($row['options']['c'] !== '' ? $row['options']['c'] : null) . ', '
                    . self::sqlString($row['options']['d'] !== '' ? $row['options']['d'] : null) . ', '
                    . self::sqlString($row['correct_answer']) . ', NULL, '
                    . self::sqlString($row['category_name']) . ', '
                    . self::sqlString($row['difficulty']) . ', NULL, '
                    . self::sqlString($row['content_hash']) . ', '
                    . (int) $row['is_valid'] . ', ' . (int) $row['is_duplicate'] . ', ' . (int) $row['needs_review'] . ', '
                    . ($issuesJson === null ? 'NULL' : self::sqlString($issuesJson)) . ", 'pending')";
            }
            $lines[] = implode(",\n", $values) . ';';
            if ($chunkIndex < count($chunks) - 1) {
                $lines[] = '';
            }
        }
        $lines[] = '';
        if ($withTransaction) {
            $lines[] = 'COMMIT;';
            $lines[] = '';
        }
        $lines[] = '-- بعد التنفيذ: لوحة التحكم → استيراد الأسئلة → الدُفعة «' . $batchName . '» → اختر المسار والمجال ثم اعتمد الصفوف الصحيحة.';
        return implode("\n", $lines) . "\n";
    }

    /** تعديل جمل التصنيفات المطلوبة (بدل جملة tracks القديمة الخاطئة) */
    public static function tracksSql(): string
    {
        $lines = [];
        $lines[] = '-- =====================================================================';
        $lines[] = '--  تصحيح جملة tracks القديمة';
        $lines[] = '--  الجملة الأصلية في ملفك لا تعمل على هذه المنصة: أسماء الأعمدة مختلفة';
        $lines[] = '--  (name/slug/description/display_order غير موجودة)، والمنصة تستخدم:';
        $lines[] = '--  tracks(code, name_ar, name_en, description, icon, color, sort_order, is_active)';
        $lines[] = '--  كما أن المسارين المطلوبين موجودان أصلاً في database.sql كمسارين رقم 1 و 2.';
        $lines[] = '--  لذلك هذه الجملة تجعلهما مضمونين بلا تكرار ولا تعارض.';
        $lines[] = '-- =====================================================================';
        $lines[] = 'SET NAMES utf8mb4;';
        $lines[] = '';
        $lines[] = "INSERT INTO `tracks` (`code`, `name_ar`, `name_en`, `description`, `icon`, `color`, `sort_order`, `is_active`) VALUES";
        $lines[] = "('educational', 'الاختبار التربوي العام', 'General Educational Test', 'الاختبار التربوي العام للمعلمين: المناهج، القياس، علم النفس التربوي، الإدارة الصفية.', 'bi-mortarboard', '#006C35', 2, 1)";
        $lines[] = 'ON DUPLICATE KEY UPDATE `name_ar` = VALUES(`name_ar`), `description` = VALUES(`description`), `is_active` = 1;';
        $lines[] = '';
        $lines[] = "INSERT INTO `tracks` (`code`, `name_ar`, `name_en`, `description`, `icon`, `color`, `sort_order`, `is_active`) VALUES";
        $lines[] = "('specialist', 'الاختبار التخصصي', 'Specialist Test', 'الاختبار التخصصي للرخصة المهنية - تخصص الحاسب الآلي وتقنية المعلومات.', 'bi-cpu', '#0d6efd', 1, 1)";
        $lines[] = 'ON DUPLICATE KEY UPDATE `name_ar` = VALUES(`name_ar`), `description` = VALUES(`description`), `is_active` = 1;';
        $lines[] = '';
        $lines[] = '-- ملاحظة: رمز المسار (code) هو المفتاح الفريد، لذلك لا يلزم ذكر المعرّف يدوياً.';
        $lines[] = '-- المجالات التربوية الجاهزة للتوزيع في المنصة: 22 المناهج وطرق التدريس، 23 القياس والتقويم،';
        $lines[] = '-- 24 علم النفس التربوي، 25 الإدارة المدرسية، 26 أخلاقيات المهنة، 27 تكنولوجيا التعليم،';
        $lines[] = '-- 28 الفروق الفردية وصعوبات التعلم، 29 الإدارة الصفية، 30 البحث التربوي وتطوير الأداء المهني.';
        return implode("\n", $lines) . "\n";
    }

    /** قائمة المراجعة البشرية (CSV بترميز UTF-8 مع BOM ليفتح في Excel بشكل سليم) */
    public static function reviewCsv(array $processed): string
    {
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, "\xEF\xBB\xBF");
        $escapeArgument = "\\";
        fputcsv($handle, ['#', 'المعرّف القديم', 'المصدر', 'المجال المقترح', 'التصنيف في الملف الأصلي',
            'الإجابة', 'الوسوم', 'نص السؤال (بعد الإصلاح)', 'النص الأصلي', 'أ', 'ب', 'ج', 'د', 'تكرار لصف'],
            ',', '"', $escapeArgument);
        foreach ($processed as $row) {
            fputcsv($handle, [
                $row['row_number'],
                $row['legacy_id'],
                (string) $row['source_name'],
                $row['category_name'] ?? '— غير مصنّف —',
                $row['legacy_category'],
                $row['correct_answer'] ?? '',
                implode(' | ', array_map([self::class, 'issueLabel'], $row['issues'])),
                $row['question_text'],
                $row['raw_stem'],
                $row['options']['a'], $row['options']['b'], $row['options']['c'], $row['options']['d'],
                $row['duplicate_of'] ?? '',
            ], ',', '"', $escapeArgument);
        }
        rewind($handle);
        return (string) stream_get_contents($handle);
    }

    /** تقرير عربي مختصر */
    public static function reportMarkdown(array $summary, array $processed, array $meta = []): string
    {
        $lines = [];
        $lines[] = '# تقرير استيراد بنك أسئلة قديم';
        $lines[] = '';
        $lines[] = '- **الملف المصدر:** `' . ($meta['file'] ?? '—') . '`';
        $lines[] = '- **الهدف المقترح:** ' . ($meta['batch'] ?? '—');
        $lines[] = '- **أداة الاستيراد:** LegacyBankImporter v' . self::VERSION . ' — ' . date('Y-m-d H:i');
        $lines[] = '';
        $lines[] = '## 1) الأرقام';
        $lines[] = '';
        $lines[] = '| البند | العدد |';
        $lines[] = '|---|---|';
        $lines[] = '| إجمالي الأسئلة في الملف | ' . number_format((int) $summary['total']) . ' |';
        $lines[] = '| صالحة داخلياً (قابلة للإدخال بعد الاعتماد) | ' . number_format((int) $summary['valid']) . ' |';
        $lines[] = '| غير صالحة (نص قصير أو اختيار ناقص) | ' . number_format((int) $summary['invalid']) . ' |';
        $lines[] = '| تحتاج مراجعة بشرية | ' . number_format((int) $summary['needs_review']) . ' |';
        $lines[] = '| **بلا إجابة صحيحة في الملف** | **' . number_format((int) $summary['missing_answer']) . '** |';
        $lines[] = '| فيها إجابة صحيحة | ' . number_format((int) $summary['answer_key_present']) . ' |';
        $lines[] = '| مكررة (تطابق تام) | ' . number_format((int) $summary['duplicates_exact']) . ' |';
        $lines[] = '| مكررة (تشابه عالٍ) | ' . number_format((int) $summary['duplicates_near']) . ' |';
        $lines[] = '| نص مصاب بتشويه (تكرار هاء/تفكّك) | ' . number_format((int) $summary['flagged_text']) . ' |';
        $lines[] = '| بلا مجال محدّد آلياً | ' . number_format((int) $summary['uncategorized']) . ' |';
        $lines[] = '| أسئلة قدرات/لغة عامة | ' . number_format((int) $summary['aptitude']) . ' |';
        $lines[] = '| أسئلة «صح/خطأ» | ' . number_format((int) ($summary['true_false'] ?? 0)) . ' |';
        $clean = self::countClean($processed);
        $lines[] = '| **جاهزة للإدخال الفوري (إجابة + نص سليم)** | **' . number_format($clean) . '** |';
        $lines[] = '';
        $lines[] = '> **قاعدة صارمة:** أي صف وسِمه التقرير بـ «الإجابة الصحيحة غير موجودة» لا يمكن إدخاله إلى بنك الأسئلة';
        $lines[] = '> قبل أن يضبط المراجع الإجابة (درجة a/b/c/d). المنصة ترفض تصحيح سؤال بلا إجابة، وتخمين الإجابة ممنوع.';
        $lines[] = '';

        if ($summary['by_source'] !== []) {
            $lines[] = '## 2) التوزيع حسب المصدر في الملف';
            $lines[] = '';
            $lines[] = '| المصدر | عدد الأسئلة |';
            $lines[] = '|---|---|';
            foreach ($summary['by_source'] as $name => $count) {
                $lines[] = '| ' . $name . ' | ' . number_format((int) $count) . ' |';
            }
            $lines[] = '';
        }

        if ($summary['by_category'] !== []) {
            $lines[] = '## 3) التصنيف الآلي المقترح (راجعه)';
            $lines[] = '';
            $lines[] = '| المجال في المنصة | عدد الأسئلة |';
            $lines[] = '|---|---|';
            foreach ($summary['by_category'] as $name => $count) {
                $lines[] = '| ' . $name . ' | ' . number_format((int) $count) . ' |';
            }
            $lines[] = '';
        }

        if (($summary['repairs'] ?? []) !== []) {
            $lines[] = '## 4) الإصلاحات الآلية المنفّذة على النص';
            $lines[] = '';
            $labels = [
                'hamza_order'      => 'تصحيح ترتيب «الأ/الإ/الآ» (انقلاب الهمزة)',
                'alef_lam_order'   => 'تصحيح ترتيب «الا» المقلوب إلى «اال»',
                'joined_letter'    => 'إلصاق حرف منفصل بما بعده (ن/ه/م...)',
                'joined_hamza'     => 'إلصاق «ء» المفصولة',
                'joined_suffix'    => 'إلصاق لاحقة منفصلة بحرف واحد (ة/ى/و/ي)',
                'space_before_punct' => 'حذف مسافة قبل علامة ترقيم',
                'space_after_punct'  => 'إضافة مسافة بعد علامة ترقيم',
            ];
            $lines[] = '| الإصلاح | عدد الحالات |';
            $lines[] = '|---|---|';
            foreach ($summary['repairs'] as $key => $count) {
                if ($key === 'changed') {
                    continue;
                }
                $lines[] = '| ' . ($labels[$key] ?? $key) . ' | ' . number_format((int) $count) . ' |';
            }
            $lines[] = '';
            $lines[] = 'هذه الإصلاحات مؤكدة لغوياً (ترتيب محارف/مسافات) ولا تغيّر معنى الكلمات.';
            $lines[] = 'أما الكلمات التي فُقدت بعض حروفها (مثل «سههتخدم») فلا يجوز تخمينها، ولذلك وُسمت للمراجعة البشرية.';
            $lines[] = '';
        }

        $worst = array_values(array_filter($processed, static fn(array $row): bool => $row['needs_review'] === 1));
        usort($worst, static fn(array $a, array $b): int => count($b['issues']) <=> count($a['issues']));
        if ($worst !== []) {
            $lines[] = '## 5) عيّنة من أكثر الصفوف احتياجاً للمراجعة';
            $lines[] = '';
            foreach (array_slice($worst, 0, 10) as $row) {
                $lines[] = '- **صف ' . $row['row_number'] . '** (' . count($row['issues']) . ' وسوم): '
                    . mb_substr($row['question_text'], 0, 120) . '…';
                $lines[] = '  - ' . implode('، ', array_map([self::class, 'issueLabel'], $row['issues']));
            }
            $lines[] = '';
        }

        $lines[] = '## 6) الخطوات التالية';
        $lines[] = '';
        $lines[] = '1. نفّذ `00_tracks_categories.sql` (تصحيح جملة tracks القديمة + ضمان المسارين).';
        $lines[] = '2. نفّذ `20_staging.sql` في phpMyAdmin (ينشئ المصادر + دُفعة المراجعة + الصفوف).';
        $lines[] = '3. افتح لوحة التحكم → **استيراد الأسئلة** → افتح الدُفعة → راجع الصفوف.';
        $lines[] = '4. اضبط الإجابة الصحيحة لكل صف تقبله (الملف الذي لا يحمل إجابات لا يمكن اعتماده قبل ذلك).';
        $lines[] = '5. اعتمد الصفوف الصحيحة ثم اضغط «إدخال المعتمد» مع اختيار المسار والمجال.';
        $lines[] = '';
        $lines[] = '> تُفتح الصفوف في منطقة المراجعة فقط، ولا يدخل أي سؤال إلى بنك الأسئلة قبل اعتمادك.';
        return implode("\n", $lines) . "\n";
    }

    /**
     * ملف SQL لإدخال أسئلة الأداة مباشرة في بنك الأسئلة (يُستخدم بعد الاعتماد البشري فقط).
     * يرفض الصفوف بلا إجابة صحيحة إلا إذا سُمح صراحةً.
     */
    public static function promoteSql(array $processed, int $trackId, array $options = []): string
    {
        // only_clean = true (الافتراضي): لا يُدخل إلا صفاً كاملاً (صالح، غير مكرر، له إجابة، بلا وسوم مراجعة)
        $onlyClean = (bool) ($options['only_clean'] ?? true);
        $allowMissing = (bool) ($options['allow_missing_answer'] ?? false);
        $sourceIds = $options['source_ids'] ?? [];
        // تعبير SQL جاهز للمصدر (مثل: SELECT id FROM sources WHERE name = ... LIMIT 1)
        $sourceIdSql = isset($options['source_id_sql']) ? (string) $options['source_id_sql'] : null;
        $lines = [];
        $lines[] = '-- إدخال الأسئلة المستوفية للشروط إلى جدول questions مباشرة';
        $lines[] = '-- المعايير: صف صالح + غير مكرر + له إجابة صحيحة' . ($onlyClean ? ' + بلا وسوم مراجعة نصية' : ' (يسمح بوسوم المراجعة)');
        $lines[] = 'SET NAMES utf8mb4;';
        $lines[] = '';
        $count = 0;
        $skipped = 0;
        foreach ($processed as $row) {
            if ((int) $row['is_valid'] !== 1 || (int) $row['is_duplicate'] === 1) {
                $skipped++;
                continue;
            }
            if ($row['correct_answer'] === null && !$allowMissing) {
                $skipped++;
                continue;
            }
            if ($onlyClean && (int) $row['needs_review'] === 1) {
                $skipped++;
                continue;
            }
            $sourceId = $sourceIds[$row['source_name']] ?? null;
            $sourceExpression = $sourceIdSql !== null
                ? $sourceIdSql
                : ($sourceId !== null ? (string) $sourceId : 'NULL');
            $lines[] = 'INSERT INTO `questions` (`track_id`, `category_id`, `source_id`, `question_text`,'
                . ' `question_type`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_answer`,'
                . ' `explanation`, `difficulty`, `year`, `source_note`, `needs_review`, `review_note`,'
                . ' `content_hash`, `active`) VALUES ('
                . $trackId . ', (SELECT `id` FROM `categories` WHERE `code` = ' . self::sqlString((string) $row['category_code']) . ' LIMIT 1), '
                . $sourceExpression . ', '
                . self::sqlString($row['question_text']) . ", '" . ($row['question_type'] ?? 'mcq') . "', "
                . self::sqlString($row['options']['a']) . ', ' . self::sqlString($row['options']['b']) . ', '
                . self::sqlString($row['options']['c'] !== '' ? $row['options']['c'] : null) . ', '
                . self::sqlString($row['options']['d'] !== '' ? $row['options']['d'] : null) . ', '
                . self::sqlString($row['correct_answer']) . ', '
                . self::sqlString($row['explanation'] ?? null) . ", '"
                . (in_array((string) ($row['difficulty'] ?? ''), ['easy', 'medium', 'hard'], true) ? (string) $row['difficulty'] : 'medium') . "', "
                . (isset($row['year']) && $row['year'] !== null ? (string) (int) $row['year'] : 'NULL') . ', '
                . self::sqlString($row['source_note'] ?? null) . ', '
                . ((int) $row['needs_review'] === 1 ? '1' : '0') . ', '
                . self::sqlString((int) $row['needs_review'] === 1
                    ? (string) ($row['review_note'] ?? ('مستورد من ملف قديم: ' . implode('، ', array_map([self::class, 'issueLabel'], $row['issues']))))
                    : null)
                . ', ' . self::sqlString($row['content_hash']) . ', 1);';
            $count++;
        }
        $lines[] = '';
        $lines[] = '-- أُدرج: ' . $count . ' سؤالاً | متخطى: ' . $skipped . ' صفاً (غير صالح أو مكرر أو بلا إجابة).';
        return implode("\n", $lines) . "\n";
    }
}
