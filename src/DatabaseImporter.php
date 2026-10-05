<?php
declare(strict_types=1);

namespace App;

use PDO;
use Throwable;

/**
 * استيراد ملف SQL (database.sql) من داخل PHP بدون الحاجة إلى سطر الأوامر أو phpMyAdmin.
 *
 * يُستخدم في:
 *   - معالج التثبيت install.php على الاستضافات المشتركة حيث لا يوجد SSH.
 *   - tools/install-cli.php على السيرفرات الخاصة.
 *
 * ملاحظات مهمة:
 *   - التقسيم إلى جمل يحترم النصوص المقتبسة والتعليقات، حتى لو احتوى نص عربي على فاصلة منقوطة.
 *   - الجمل الخاصة بإنشاء/اختيار قاعدة البيانات تُستثنى تلقائياً، لأن الاستيراد يتم داخل
 *     قاعدة البيانات المختارة أصلاً (وهو ما يمنع الكتابة في قاعدة بيانات أخرى بالخطأ).
 */
final class DatabaseImporter
{
    /** @return array<int,string> جمل SQL القابلة للتنفيذ (بدون تعليقات ولا جمل فارغة) */
    public static function split(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $length = strlen($sql);
        $state = 'normal';
        $quoteChar = '';

        for ($index = 0; $index < $length; $index++) {
            $char = $sql[$index];
            $next = $index + 1 < $length ? $sql[$index + 1] : '';

            switch ($state) {
                case 'normal':
                    // تعليق سطري: -- أو #
                    if (($char === '-' && $next === '-') || $char === '#') {
                        $state = 'line_comment';
                        if ($char === '-') {
                            $index++;
                        }
                        break;
                    }
                    // تعليق كتلة
                    if ($char === '/' && $next === '*') {
                        $state = 'block_comment';
                        $index++;
                        break;
                    }
                    if ($char === "'" || $char === '"' || $char === '`') {
                        $state = 'quoted_' . $char;
                        $quoteChar = $char;
                        $buffer .= $char;
                        break;
                    }
                    if ($char === ';') {
                        $statement = trim($buffer);
                        if ($statement !== '') {
                            $statements[] = $statement;
                        }
                        $buffer = '';
                        break;
                    }
                    $buffer .= $char;
                    break;

                case 'line_comment':
                    if ($char === "\n") {
                        $state = 'normal';
                        $buffer .= "\n";
                    }
                    break;

                case 'block_comment':
                    if ($char === '*' && $next === '/') {
                        $state = 'normal';
                        $index++;
                    }
                    break;

                default: // داخل نص مقتبس
                    if ($char === '\\') { // هروب بـ \ داخل النصوص
                        $buffer .= $char . $next;
                        $index++;
                        break;
                    }
                    if ($char === $quoteChar) {
                        // '' أو `` داخل نفس النوع = حرف مقتبس وليس نهاية النص
                        if ($next === $quoteChar) {
                            $buffer .= $char . $next;
                            $index++;
                            break;
                        }
                        $state = 'normal';
                        $buffer .= $char;
                        break;
                    }
                    $buffer .= $char;
                    break;
            }
        }

        $statement = trim($buffer);
        if ($statement !== '') {
            $statements[] = $statement;
        }
        return $statements;
    }

    /**
     * استثناء جمل CREATE DATABASE / USE من الملف حتى يبقى الاستيراد داخل القاعدة المختارة.
     * @param array<int,string> $statements
     * @return array{statements:array<int,string>,skipped:int}
     */
    public static function filterSchemaStatements(array $statements): array
    {
        $kept = [];
        $skipped = 0;
        foreach ($statements as $statement) {
            if (preg_match('/^(CREATE\s+DATABASE|CREATE\s+SCHEMA|USE\s+|ALTER\s+DATABASE)/i', $statement) === 1) {
                $skipped++;
                continue;
            }
            $kept[] = $statement;
        }
        return ['statements' => $kept, 'skipped' => $skipped];
    }

    /**
     * تنفيذ ملف SQL كامل.
     *
     * @param callable(int,string):void|null $onProgress تُستدعى بعد كل جملة ناجحة (الرقم، بداية الجملة)
     * @return array{ok:bool,executed:int,skipped:int,error:string,failed_statement:string}
     */
    public static function run(PDO $pdo, string $sql, ?callable $onProgress = null): array
    {
        $split = self::filterSchemaStatements(self::split($sql));
        $executed = 0;
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        foreach ($split['statements'] as $statement) {
            $preview = Str::limit(preg_replace('/\s+/', ' ', $statement) ?? $statement, 90);
            try {
                $pdo->exec($statement);
                $executed++;
                if ($onProgress !== null) {
                    $onProgress($executed, $preview);
                }
            } catch (Throwable $error) {
                return [
                    'ok'                => false,
                    'executed'          => $executed,
                    'skipped'           => $split['skipped'],
                    'error'             => $error->getMessage(),
                    'failed_statement'  => $preview,
                ];
            }
        }

        return [
            'ok'               => true,
            'executed'         => $executed,
            'skipped'          => $split['skipped'],
            'error'            => '',
            'failed_statement' => '',
        ];
    }

    /**
     * التحقق من اكتمال المخطط بمقارنة الجداول المتوقعة بما هو موجود فعلاً.
     * @param array<int,string> $tables
     * @return array{ok:bool,present:array<int,string>,missing:array<int,string>}
     */
    public static function verifySchema(PDO $pdo, string $database, array $tables): array
    {
        $statement = $pdo->prepare(
            'SELECT table_name FROM information_schema.tables WHERE table_schema = :db AND table_type = :type'
        );
        $statement->execute(['db' => $database, 'type' => 'BASE TABLE']);
        $present = array_map(static fn(array $row): string => (string) $row['table_name'] ?: (string) reset($row), $statement->fetchAll(PDO::FETCH_ASSOC));

        $missing = array_values(array_diff($tables, $present));
        return ['ok' => $missing === [], 'present' => $present, 'missing' => $missing];
    }

    /**
     * الجداول التي يجب أن ينشئها database.sql (تُستخدم للتحقق بعد الاستيراد).
     * @return array<int,string>
     */
    public static function expectedTables(): array
    {
        return [
            'settings', 'tracks', 'categories', 'users', 'password_resets', 'remember_tokens',
            'login_attempts', 'audit_logs', 'sources', 'questions', 'question_reports',
            'import_batches', 'import_staging', 'exam_templates', 'exam_attempts',
            'exam_attempt_questions', 'subscription_plans', 'subscriptions', 'payments',
            'telegram_links', 'telegram_link_codes', 'telegram_states', 'telegram_messages',
            'support_tickets', 'notifications', 'user_category_stats', 'testimonials',
        ];
    }
}
