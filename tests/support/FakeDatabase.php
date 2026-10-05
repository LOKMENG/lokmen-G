<?php
declare(strict_types=1);

namespace Tests\Support;

use RuntimeException;

/**
 * قاعدة بيانات وهمية في الذاكرة — للاختبارات والمعاينة المحلية فقط.
 *
 * تُفعَّل صراحةً عبر APP_TEST_FAKE_DB=1 أو تعريف الثابت APP_TEST_FAKE_DB
 * (انظر config/bootstrap.php)، والعمل الحقيقي يستخدم MySQL عبر PDO.
 * لا ترث من PDO لأن PDO لا يمكن محاكاته بأمان؛ بدلاً من ذلك تُحقن في
 * App\Database كـ«سائق» يطابق واجهة prepare/lastInsertId/transaction.
 *
 * المفردات المدعومة:
 *   SELECT * | COUNT(*) | أعمدة محددة، FROM جدول واحد، WHERE (= <> > >= < <= IS NULL
 *   IS NOT NULL IN AND OR)، ORDER BY، LIMIT/OFFSET، وتغليف COUNT(*) لاستعلام فرعي.
 *   INSERT ... VALUES / INSERT ... ON DUPLICATE KEY UPDATE (بقائمة مفاتيح فريدة معلنة).
 *   UPDATE ... SET (قيمة، معامل، عدّاد ±رقم، ±VALUES(col)، NOW()) WHERE.
 *   DELETE FROM ... WHERE.  وكذلك الدوال: LAST_INSERT_ID() و DATABASE().
 * كل ما هو خارج هذه المفردات يرمي استثناءً واضحاً بدلاً من إرجاع نتائج مضلّلة.
 */
final class FakeDatabase
{
    /** @var array<string,array<int,array<string,mixed>>> */
    private static array $store = [];
    /** @var array<string,array<int,array<int,string>>> مفاتيح فريدة معلنة لكل جدول */
    private static array $unique = [
        'settings'            => [['setting_key']],
        'users'               => [['email'], ['phone']],
        'subscriptions'       => [],
        'telegram_links'      => [['telegram_user_id']],
        'telegram_states'     => [['telegram_user_id']],
        'user_category_stats' => [['user_id', 'category_id']],
        'import_staging'      => [],
    ];
    private static int $sequence = 0;
    /**
     * القيم الافتراضية للأعمدة الشائعة (مطابقة لـ NOT NULL DEFAULT في database.sql).
     * ضرورية لأن اختبارات المنطق تعتمد على أن العدّادات تبدأ من صفر لا من NULL.
     * @var array<string,array<string,mixed>>
     */
    private static array $defaults = [
        'questions' => ['times_answered' => 0, 'times_correct' => 0, 'times_reported' => 0,
                        'active' => 1, 'needs_review' => 0, 'question_type' => 'mcq', 'difficulty' => 'medium'],
        'import_staging' => ['is_valid' => 1, 'is_duplicate' => 0, 'needs_review' => 0, 'status' => 'pending', 'attempts' => 0],
        'import_batches' => ['total_rows' => 0, 'imported_rows' => 0, 'duplicate_rows' => 0, 'invalid_rows' => 0,
                             'needs_review_rows' => 0, 'status' => 'pending'],
        'subscriptions' => ['extended_days' => 0, 'status' => 'pending'],
        'users' => ['role' => 'student', 'status' => 'active', 'failed_login_count' => 0],
        'testimonials' => ['rating' => 5, 'consent' => 0, 'is_published' => 0, 'sort_order' => 0],
        'user_category_stats' => ['total_answered' => 0, 'correct_answers' => 0, 'wrong_answers' => 0, 'accuracy' => 0],
    ];


    /** @var array<string|int,int|string> */
    private array $params = [];
    /** @var array<int,mixed> معاملات الموضع (?) */
    private array $positional = [];
    private int $rowCount = 0;
    private int $lastId = 0;
    private array $result = [];
    private bool $inTransaction = false;
    /** @var array<string,array<int,array<string,mixed>>> */
    private array $snapshot = [];

    public function __construct()
    {
        // عمداً بدون parent::__construct(): لا اتصال حقيقي، والطرق المستخدمة معاد تعريفها بالكامل.
    }

    /** ملاحظة: لا يمكن تسميتها connect() لأن PDO في PHP 8.4 يعرّف PDO::connect */
    public static function instance(): self
    {
        return new self();
    }

    public static function reset(): void
    {
        self::$store = [];
        self::$sequence = 0;
    }

    /** @param array<int,array<string,mixed>> $rows */
    public static function seed(string $table, array $rows): void
    {
        self::$store[$table] = [];
        foreach ($rows as $row) {
            self::$store[$table][] = $row;
            if (isset($row['id'])) {
                self::$sequence = max(self::$sequence, (int) $row['id']);
            }
        }
    }

    /** @return array<int,array<string,mixed>> */
    public static function table(string $table): array
    {
        return self::$store[$table] ?? [];
    }

    /** @param array<int,mixed> $options */
    public function prepare(string $query, array $options = []): FakeStatement
    {
        return new FakeStatement($this, $query);
    }

    /** للتشخيص فقط: آخر نتيجة نفّذها هذا "الاتصال" */
    public function lastResult(): array
    {
        return $this->result;
    }

    public function lastInsertId(?string $name = null): string|false
    {
        return (string) $this->lastId;
    }

    public function beginTransaction(): bool
    {
        $this->snapshot = self::$store;
        $this->inTransaction = true;
        return true;
    }

    public function commit(): bool
    {
        $this->snapshot = [];
        $this->inTransaction = false;
        return true;
    }

    public function rollBack(): bool
    {
        if ($this->inTransaction) {
            self::$store = $this->snapshot;
        }
        $this->snapshot = [];
        $this->inTransaction = false;
        return true;
    }

    public function inTransaction(): bool
    {
        return $this->inTransaction;
    }

    /* ==================== المحرك ==================== */

    /** @param array<string,mixed> $params */
    public function run(string $sql, array $params, FakeStatement $statement): void
    {
        // PDO يقبل مصفوفة مرقّمة للمعاملات الموضعية (?) ومصفوفة مفاتيح للمعاملات المسماة (:name)
        $this->positional = [];
        $this->params = [];
        foreach ($params as $key => $value) {
            if (is_int($key)) {
                $this->positional[] = $value;
            } else {
                $this->params[(string) $key] = $value;
            }
        }
        $this->rowCount = 0;
        $this->result = [];
        $sql = trim($sql);
        if ($sql === '') {
            return;
        }
        // استعلامات معلومات المخطط (tableExists)
        if (stripos($sql, 'information_schema.tables') !== false) {
            $table = (string) ($params[':t'] ?? $params['t'] ?? '');
            $this->rowCount = isset(self::$store[$table]) ? 1 : 0;
            $this->result = [['c' => $this->rowCount]];
            $statement->setResult($this->result, $this->rowCount);
            return;
        }

        // إزالة تعليقات SQL (-- و # و /* */) كما يفعل MySQL قبل التنفيذ
        $sql = $this->stripComments($sql);
        // تحويل المعاملات الموضعية (?) إلى معاملات مسماة بالترتيب نفسه في نص الاستعلام
        if ($this->positional !== []) {
            $sql = $this->bindPositional($sql, $this->positional);
        }
        $type = strtoupper((string) strtok($sql, " \t\n"));
        try {
            match ($type) {
                'SELECT' => $this->runSelect($sql, $statement),
                'INSERT' => $this->runInsert($sql),
                'UPDATE' => $this->runUpdate($sql),
                'DELETE' => $this->runDelete($sql),
                default  => throw new RuntimeException('FakeDatabase: نوع استعلام غير مدعوم → ' . substr($sql, 0, 60)),
            };
        } catch (RuntimeException $e) {
            // إضافة نص الاستعلام إلى الرسالة ليسهل تتبّع النقص في المحاكاة
            throw new RuntimeException(
                $e->getMessage() . ' | ' . basename($e->getFile()) . ':' . $e->getLine()
                . ' | SQL: ' . preg_replace('/\s+/', ' ', substr($sql, 0, 200)),
                0,
                $e
            );
        }
        // ضمان أن العبارة تحمل النتيجة دائماً (fetch/fetchColumn/rowCount)
        $statement->setResult($this->result, $this->rowCount);
    }

    private function runSelect(string $sql, FakeStatement $statement): void
    {
        $sql = $this->stripAliases($sql);
        // تغليف COUNT(*) لاستعلام فرعي (Database::paginate)
        if (preg_match('/^SELECT\s+COUNT\(\*\)\s+FROM\s*\((.*)\)\s+AS\s+__c$/is', $sql, $matches) === 1) {
            $inner = $matches[1];
            $this->runSelect($inner, new FakeStatement($this, $inner));
            $this->rowCount = count($this->result);
            $this->result = [['c' => $this->rowCount]];
            $statement->setResult($this->result, $this->rowCount);
            return;
        }

        // FROM على المستوى الأعلى فقط (كي لا يُلتقط FROM داخل استعلام فرعي في قائمة SELECT)
        $fromPosition = $this->topLevelPosition($sql, 'FROM', 6);
        if ($fromPosition === null
            || preg_match('/^\s+`?([A-Za-z0-9_]+)`?(.*)$/s', substr($sql, $fromPosition + 4), $matches) !== 1) {
            throw new RuntimeException('FakeDatabase: صيغة SELECT غير مفهومة → ' . substr($sql, 0, 80));
        }
        $columns = trim(substr($sql, 6, $fromPosition - 6));
        $table = $this->clean($matches[1]);
        $tail = (string) $matches[2];

        // اسم مستعار الجدول الأساسي (للاستعلامات الفرعية المترابطة مثل t.id)
        $this->outerAlias = '';
        if (preg_match('/^\s+(?:AS\s+)?([A-Za-z_][A-Za-z0-9_]*)\b/i', $tail, $aliasMatch) === 1
            && !in_array(strtoupper($aliasMatch[1]), ['WHERE', 'JOIN', 'LEFT', 'RIGHT', 'INNER', 'OUTER', 'GROUP', 'ORDER', 'LIMIT', 'HAVING', 'ON', 'CROSS'], true)) {
            $this->outerAlias = $aliasMatch[1];
        }

        $rows = self::$store[$table] ?? [];
        $tail = $this->applyWhere($rows, $tail);

        // GROUP BY + دوال التجميع (COUNT/SUM/AVG/MIN/MAX) — تقريب كافٍ لفحص الصفحات
        $groupColumn = null;
        if (preg_match('/\sGROUP\s+BY\s+([`A-Za-z0-9_.]+)/i', $tail, $groupMatch) === 1) {
            $groupColumn = $this->clean($groupMatch[1]);
        }
        $hasAggregate = preg_match('/\b(COUNT|SUM|AVG|MIN|MAX)\s*\(/i', $columns) === 1;
        if ($groupColumn !== null || $hasAggregate || preg_match('/\sHAVING\s+/i', $tail) === 1) {
            $aliasMap = $this->selectExpressions($columns);
            $expressions = $this->splitList($columns);
            $groups = [];
            if ($groupColumn === null) {
                $groups['__all'] = $rows;
            } else {
                $groupExpression = $aliasMap[$groupColumn] ?? $groupColumn;
                foreach ($rows as $row) {
                    $groups[(string) $this->expression($groupExpression, $row)][] = $row;
                }
            }
            $grouped = [];
            foreach ($groups as $groupRows) {
                $grouped[] = $this->pickGrouped($groupRows, $expressions);
            }
            $rows = $grouped;
            if (preg_match('/\sHAVING\s+(.*?)(?:\s+ORDER\s+BY\s+|\s+LIMIT\s+|$)/is', $tail, $havingMatch) === 1) {
                $having = $havingMatch[1];
                $rows = array_values(array_filter($rows, fn(array $row): bool => $this->matches($row, $having)));
            }
            $rows = $this->orderBy($rows, $tail);
            $rows = $this->applyLimit($rows, $tail);
            $this->rowCount = count($rows);
            $this->result = array_values($rows);
            return;
        }

        $rows = $this->orderBy($rows, $tail);
        $rows = $this->applyLimit($rows, $tail);

        if (preg_match('/^(COUNT|MAX|MIN|SUM|AVG)\s*\(\s*([A-Za-z0-9_.*]+)\s*\)(?:\s+AS\s+[A-Za-z0-9_]+)?$/i', trim($columns), $aggregate) === 1) {
            $function = strtoupper($aggregate[1]);
            $column = $aggregate[2];
            $name = 'c';
            if (preg_match('/\s+AS\s+([A-Za-z0-9_]+)\s*$/i', trim($columns), $aliasMatch) === 1) {
                $name = $aliasMatch[1];
            }
            $values = [];
            foreach ($rows as $row) {
                if ($column === '*') {
                    $values[] = 1;
                    continue;
                }
                $value = $this->cell($row, $column);
                if ($value !== null) {
                    $values[] = $value;
                }
            }
            $aggregated = match ($function) {
                'COUNT' => count($values),
                'MAX'   => $values === [] ? null : max($values),
                'MIN'   => $values === [] ? null : min($values),
                'SUM'   => $values === [] ? null : array_sum(array_map('floatval', $values)),
                'AVG'   => $values === [] ? null : array_sum(array_map('floatval', $values)) / count($values),
            };
            $this->rowCount = count($rows);
            $this->result = [[$name => $aggregated]];
        } elseif (stripos($columns, 'COUNT(') !== false) {
            $this->rowCount = count($rows);
            $this->result = [['c' => $this->rowCount]];
        } elseif ($columns === '*') {
            $this->result = array_values($rows);
            $this->rowCount = count($this->result);
        } else {
            $this->result = array_map(fn(array $row): array => $this->pick($row, $columns), array_values($rows));
            $this->rowCount = count($this->result);
        }

        $statement->setResult($this->result, $this->rowCount);
    }

    private function runInsert(string $sql): void
    {
        $onDuplicate = null;
        if (stripos($sql, 'ON DUPLICATE KEY UPDATE') !== false) {
            [$sql, $onDuplicate] = preg_split('/ON DUPLICATE KEY UPDATE/i', $sql, 2) + [1 => null];
            $onDuplicate = $onDuplicate === null ? null : trim((string) $onDuplicate);
        }
        if (!preg_match('/^INSERT\s+INTO\s+([`A-Za-z0-9_]+)\s*\((.*?)\)\s*VALUES\s*\((.*)\)$/is', trim((string) $sql), $matches)) {
            throw new RuntimeException('FakeDatabase: صيغة INSERT غير مدعومة → ' . substr($sql, 0, 80));
        }
        $table = $this->clean($matches[1]);
        $columns = array_map([$this, 'clean'], $this->splitList($matches[2]));
        $values = $this->splitList($matches[3]);
        if (count($columns) !== count($values)) {
            throw new RuntimeException("FakeDatabase: عدد الأعمدة لا يطابق القيم في الجدول {$table}");
        }
        $row = [];
        foreach ($columns as $index => $column) {
            $row[$column] = $this->value($values[$index]);
        }

        self::$store[$table] ??= [];
        // تعبئة القيم الافتراضية للأعمدة غير المذكورة في INSERT
        foreach (self::$defaults[$table] ?? [] as $column => $default) {
            if (!array_key_exists($column, $row)) {
                $row[$column] = $default;
            }
        }
        $existingIndex = $this->findDuplicate($table, $row);
        if ($existingIndex !== null && $onDuplicate !== null) {
            $existingRow = self::$store[$table][$existingIndex];
            $incomingValues = $row; // نسخة ثابتة من قيم INSERT لاستخدامها في VALUES(col)
            foreach ($this->splitList($onDuplicate) as $assignment) {
                if (!str_contains($assignment, '=')) {
                    continue;
                }
                [$column, $expression] = array_map('trim', explode('=', $assignment, 2));
                $column = $this->clean($column);
                // القيمة الجديدة تُحسب على الصف الموجود، وتدعم VALUES(col) وأعمدة أخرى بجانب أرقام ثابتة
                $row[$column] = $this->expression($expression, $existingRow, $incomingValues);
            }
            self::$store[$table][$existingIndex] = array_merge($existingRow, $row);
            $this->rowCount = 2; // سلوك MySQL في UPDATE على صف موجود
            $this->lastId = (int) (self::$store[$table][$existingIndex]['id'] ?? 0);
            return;
        }
        if ($existingIndex !== null) {
            $this->rowCount = 0;
            return;
        }
        if (!array_key_exists('id', $row)) {
            $row['id'] = ++self::$sequence;
        } else {
            self::$sequence = max(self::$sequence, (int) $row['id']);
        }
        self::$store[$table][] = $row;
        $this->lastId = (int) $row['id'];
        $this->rowCount = 1;
    }

    private function runUpdate(string $sql): void
    {
        if (!preg_match('/^UPDATE\s+([`A-Za-z0-9_]+)\s+SET\s+(.*?)\s+WHERE\s+(.*)$/is', trim($sql), $matches)) {
            throw new RuntimeException('FakeDatabase: صيغة UPDATE غير مدعومة → ' . substr($sql, 0, 80));
        }
        $table = $this->clean($matches[1]);
        $assignments = $this->splitList($matches[2]);
        $where = $matches[3];
        $rows = self::$store[$table] ?? [];
        $matched = 0;
        foreach ($rows as $index => $row) {
            if (!$this->matches($row, $where)) {
                continue;
            }
            foreach ($assignments as $assignment) {
                [$column, $expression] = array_map('trim', explode('=', $assignment, 2));
                $column = $this->clean($column);
                $rows[$index][$column] = $this->expression($expression, $row);
            }
            $matched++;
        }
        self::$store[$table] = $rows;
        $this->rowCount = $matched;
    }

    private function runDelete(string $sql): void
    {
        if (!preg_match('/^DELETE\s+FROM\s+([`A-Za-z0-9_]+)(?:\s+WHERE\s+(.*))?$/is', trim($sql), $matches)) {
            throw new RuntimeException('FakeDatabase: صيغة DELETE غير مدعومة → ' . substr($sql, 0, 80));
        }
        $table = $this->clean($matches[1]);
        $where = trim((string) ($matches[2] ?? ''));
        $rows = self::$store[$table] ?? [];
        if ($where === '') {
            $this->rowCount = count($rows);
            self::$store[$table] = [];
            return;
        }
        $kept = [];
        foreach ($rows as $row) {
            if ($this->matches($row, $where)) {
                $this->rowCount++;
                continue;
            }
            $kept[] = $row;
        }
        self::$store[$table] = $kept;
    }

    /* ==================== أدوات SQL ==================== */

    /** @param array<int,array<string,mixed>> $rows */
    private function applyWhere(array &$rows, string $tail): string
    {
        $wherePosition = $this->topLevelPosition($tail, 'WHERE');
        if ($wherePosition === null) {
            return $tail;
        }
        $where = substr($tail, $wherePosition + 5);
        $boundary = null;
        foreach (['GROUP BY', 'HAVING', 'ORDER BY', 'LIMIT'] as $keyword) {
            $position = $this->topLevelPosition($where, $keyword);
            if ($position !== null && ($boundary === null || $position < $boundary)) {
                $boundary = $position;
            }
        }
        if ($boundary !== null) {
            $where = substr($where, 0, $boundary);
        }
        $where = trim($where);
        if ($where !== '') {
            $rows = array_values(array_filter($rows, fn(array $row): bool => $this->matches($row, $where)));
        }
        return $tail;
    }

    /** @param array<int,array<string,mixed>> $rows @return array<int,array<string,mixed>> */
    private function orderBy(array $rows, string $tail): array
    {
        if (preg_match('/\sORDER\s+BY\s+(.*?)(?:\s+LIMIT\s+|$)/is', $tail, $matches) !== 1) {
            return $rows;
        }
        $parts = array_reverse(array_map('trim', explode(',', $matches[1])));
        foreach ($parts as $part) {
            $direction = 'ASC';
            if (preg_match('/\s+(ASC|DESC)$/i', $part, $dir) === 1) {
                $direction = strtoupper($dir[1]);
                $part = trim(preg_replace('/\s+(ASC|DESC)$/i', '', $part) ?? $part);
            }
            $part = $this->clean((string) preg_replace('/\s+AS\s+.*$/i', '', $part));
            usort($rows, static function (array $a, array $b) use ($part, $direction): int {
                $left = $a[$part] ?? null;
                $right = $b[$part] ?? null;
                $compare = is_numeric($left) && is_numeric($right) ? ((float) $left <=> (float) $right) : strcmp((string) $left, (string) $right);
                return $direction === 'DESC' ? -$compare : $compare;
            });
        }
        return $rows;
    }

    /** @param array<int,array<string,mixed>> $rows @return array<int,array<string,mixed>> */
    private function applyLimit(array $rows, string $tail): array
    {
        if (preg_match('/\sLIMIT\s+(\d+)(?:\s*,\s*(\d+)|\s+OFFSET\s+(\d+))?/i', $tail, $matches) !== 1) {
            return $rows;
        }
        $first = (int) $matches[1];
        if (isset($matches[2]) && $matches[2] !== '') {
            return array_slice($rows, $first, (int) $matches[2]);
        }
        $offset = (int) ($matches[3] ?? 0);
        return array_slice($rows, $offset, $first);
    }

    /** @param array<string,mixed> $row */
    private function matches(array $row, string $where): bool
    {
        $where = trim($where);
        if ($where === '' || $where === '1') {
            return true;
        }
        // إزالة الأقواس الخارجية إن كانت تُغلّف الشرط كاملاً، حتى لا تمنع تقسيم AND/OR
        while (str_starts_with($where, '(') && str_ends_with($where, ')') && $this->balanced(substr($where, 1, -1))) {
            $where = trim(substr($where, 1, -1));
        }
        // أولوية صحيحة: OR خارجي، ثم AND داخلي
        foreach ($this->splitTop($where, 'OR') as $orPart) {
            $andOk = true;
            foreach ($this->splitTop($orPart, 'AND') as $condition) {
                if (!$this->condition($row, $condition)) {
                    $andOk = false;
                    break;
                }
            }
            if ($andOk) {
                return true;
            }
        }
        return false;
    }

    /**
     * تقسيم شرط على AND/OR في المستوى الأعلى فقط (يتجاهل داخل الأقواس والاقتباسات).
     * @return array<int,string>
     */
    private function splitTop(string $sql, string $operator): array
    {
        $chunks = [];
        $buffer = '';
        $depth = 0;
        $quote = null;
        $betweenPending = false;
        $length = strlen($sql);
        $keyLength = strlen($operator);
        for ($index = 0; $index < $length; $index++) {
            $char = $sql[$index];
            if ($quote !== null) {
                $buffer .= $char;
                if ($char === $quote) {
                    $quote = null;
                }
                continue;
            }
            if ($char === "'" || $char === '"') {
                $quote = $char;
                $buffer .= $char;
                continue;
            }
            if ($char === '(') {
                $depth++;
                $buffer .= $char;
                continue;
            }
            if ($char === ')') {
                $depth--;
                $buffer .= $char;
                continue;
            }
            // كلمة BETWEEN تشير إلى أن أول AND بعدها ينتمي إليها لا إلى تقسيم الشروط
            if ($depth === 0 && $operator === 'AND' && $betweenPending && strcasecmp(substr($sql, $index, 3), 'AND') === 0
                && ($this->isBoundary($sql[$index - 1] ?? ' ')) && ($this->isBoundary($sql[$index + 3] ?? ' '))) {
                $betweenPending = false;
                $buffer .= substr($sql, $index, 3);
                $index += 2;
                continue;
            }
            if ($depth === 0 && strcasecmp(substr($sql, $index, 7), 'BETWEEN') === 0
                && ($this->isBoundary($sql[$index - 1] ?? ' ')) && ($this->isBoundary($sql[$index + 7] ?? ' '))) {
                $betweenPending = true;
                $buffer .= substr($sql, $index, 7);
                $index += 6;
                continue;
            }
            if ($depth === 0 && strcasecmp(substr($sql, $index, $keyLength), $operator) === 0
                && ($this->isBoundary($sql[$index - 1] ?? ' ')) && ($this->isBoundary($sql[$index + $keyLength] ?? ' '))) {
                $chunks[] = trim($buffer);
                $buffer = '';
                $index += $keyLength - 1;
                continue;
            }
            $buffer .= $char;
        }
        $chunks[] = trim($buffer);
        return array_values(array_filter($chunks, static fn(string $chunk): bool => $chunk !== ''));
    }

    /** موضع كلمة مفتاحية على المستوى الأعلى (خارج الأقواس والاقتباسات) */
    private function topLevelPosition(string $sql, string $keyword, int $from = 0): ?int
    {
        $depth = 0;
        $quote = null;
        $length = strlen($sql);
        $keyLength = strlen($keyword);
        for ($index = $from; $index < $length; $index++) {
            $char = $sql[$index];
            if ($quote !== null) {
                if ($char === $quote) {
                    $quote = null;
                }
                continue;
            }
            if ($char === "'" || $char === '"') {
                $quote = $char;
                continue;
            }
            if ($char === '(') {
                $depth++;
                continue;
            }
            if ($char === ')') {
                $depth--;
                if ($depth < 0) {
                    return null;
                }
                continue;
            }
            if ($depth === 0 && strcasecmp(substr($sql, $index, $keyLength), $keyword) === 0
                && $this->isBoundary($sql[$index - 1] ?? ' ') && $this->isBoundary($sql[$index + $keyLength] ?? ' ')) {
                return $index;
            }
        }
        return null;
    }

    private function isBoundary(string $char): bool
    {
        return $char === '' || preg_match('/[\s()]/', $char) === 1;
    }

    /** @param array<string,mixed> $row */
    private function condition(array $row, string $condition): bool
    {
        $condition = trim(trim($condition), " \t\n");
        while (str_starts_with($condition, '(') && str_ends_with($condition, ')') && $this->balanced(substr($condition, 1, -1))) {
            $condition = trim(substr($condition, 1, -1));
        }
        // شرط مركّب بين قوسين مثل (a IS NULL OR a > NOW()): يُقيَّم عبر المسار الكامل
        if (count($this->splitTop($condition, 'OR')) > 1 || count($this->splitTop($condition, 'AND')) > 1) {
            return $this->matches($row, $condition);
        }
        if (preg_match('/^([`A-Za-z0-9_.]+)\s+IS\s+NOT\s+NULL$/i', $condition, $matches) === 1) {
            return $this->cell($row, $matches[1]) !== null;
        }
        if (preg_match('/^([`A-Za-z0-9_.]+)\s+IS\s+NULL$/i', $condition, $matches) === 1) {
            return $this->cell($row, $matches[1]) === null;
        }
        if (preg_match('/^([`A-Za-z0-9_.]+)\s+BETWEEN\s+(.+)\s+AND\s+(.+)$/is', $condition, $matches) === 1) {
            $actual = $this->cell($row, $matches[1]);
            if ($actual === null) {
                return false;
            }
            $low = $this->expression($matches[2], $row);
            $high = $this->expression($matches[3], $row);
            if (is_numeric($actual) && is_numeric($low) && is_numeric($high)) {
                return (float) $actual >= (float) $low && (float) $actual <= (float) $high;
            }
            return strcmp((string) $actual, (string) $low) >= 0 && strcmp((string) $actual, (string) $high) <= 0;
        }
        if (preg_match('/^([`A-Za-z0-9_.]+)\s+IN\s*\((.*)\)$/is', $condition, $matches) === 1) {
            $values = array_map(fn(string $item): mixed => $this->expression($item, $row), $this->splitList($matches[2]));
            return in_array($this->cell($row, $matches[1]), $values, false);
        }
        if (preg_match('/^([`A-Za-z0-9_.]+)\s+(LIKE)\s+(.+)$/is', $condition, $matches) === 1) {
            $needle = (string) $this->value($matches[3]);
            $haystack = (string) $this->cell($row, $matches[1]);
            $pattern = '/^' . str_replace(['%', '_'], ['.*', '.'], preg_quote($needle, '/')) . '$/su';
            return preg_match($pattern, $haystack) === 1;
        }
        if (preg_match('/^([`A-Za-z0-9_.]+)\s*(=|<>|!=|>=|<=|>|<)\s*(.+)$/is', $condition, $matches) !== 1) {
            throw new RuntimeException('FakeDatabase: شرط WHERE غير مدعوم → ' . $condition);
        }
        $operator = $matches[2];
        $expected = $this->expression($matches[3], $row);
        $actual = $this->cell($row, $matches[1]);
        if ($operator === '=' || $operator === '<>') {
            $equal = is_numeric($actual) && is_numeric($expected)
                ? (float) $actual === (float) $expected
                : (string) $actual === (string) $expected;
            return $operator === '=' ? $equal : !$equal;
        }
        if ($operator === '!=') {
            return (string) $actual !== (string) $expected;
        }
        $left = is_numeric($actual) ? (float) $actual : (string) $actual;
        $right = is_numeric($expected) ? (float) $expected : (string) $expected;
        return match ($operator) {
            '>'  => $left > $right,
            '>=' => $left >= $right,
            '<'  => $left < $right,
            '<=' => $left <= $right,
        };
    }

    /** قراءة قيمة عمود من الصف مع تجاهل حالة الأحرف ومع تجاهل اسم الجدول/الاسم المستعار */
    private function cell(array $row, string $column): mixed
    {
        $column = $this->clean($column);
        if (str_contains($column, '.')) {
            // مفتاح مؤهَّل بالجدول (t.id) — يُستخدم في الاستعلامات الفرعية المترابطة
            if (array_key_exists($column, $row)) {
                return $row[$column];
            }
            $column = substr($column, (int) strrpos($column, '.') + 1);
        }
        if (array_key_exists($column, $row)) {
            return $row[$column];
        }
        foreach ($row as $key => $value) {
            if (strcasecmp((string) $key, $column) === 0) {
                return $value;
            }
        }
        return null;
    }

    private function balanced(string $expression): bool
    {
        $depth = 0;
        foreach (str_split($expression) as $char) {
            if ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;
                if ($depth < 0) {
                    return false;
                }
            }
        }
        return $depth === 0;
    }

    /**
     * إزالة تعليقات SQL خارج النصوص المقتبسة (MySQL يتجاهلها، ويجب أن تفعل مثلها).
     */
    private function stripComments(string $sql): string
    {
        $result = '';
        $length = strlen($sql);
        $quote = null;
        for ($index = 0; $index < $length; $index++) {
            $char = $sql[$index];
            $next = $index + 1 < $length ? $sql[$index + 1] : '';
            if ($quote !== null) {
                $result .= $char;
                if ($char === '\\') {
                    $result .= $next;
                    $index++;
                    continue;
                }
                if ($char === $quote) {
                    $quote = null;
                }
                continue;
            }
            if ($char === "'" || $char === '"' || $char === '`') {
                $quote = $char;
                $result .= $char;
                continue;
            }
            if (($char === '-' && $next === '-') || $char === '#') {
                while ($index < $length && $sql[$index] !== "\n") {
                    $index++;
                }
                $result .= "\n";
                continue;
            }
            if ($char === '/' && $next === '*') {
                $index += 2;
                while ($index < $length && !($sql[$index] === '*' && ($sql[$index + 1] ?? '') === '/')) {
                    $index++;
                }
                $index++;
                $result .= ' ';
                continue;
            }
            $result .= $char;
        }
        return $result;
    }

    /**
     * استبدال علامات ? بمعاملات مسماة بالترتيب النصي، مع تجاهل ما داخل النصوص المقتبسة.
     * @param array<int,mixed> $values
     */
    private function bindPositional(string $sql, array $values): string
    {
        $result = '';
        $length = strlen($sql);
        $quote = null;
        $index = 0;
        for ($position = 0; $position < $length; $position++) {
            $char = $sql[$position];
            if ($quote !== null) {
                $result .= $char;
                if ($char === '\\') {
                    $result .= $sql[++$position] ?? '';
                    continue;
                }
                if ($char === $quote) {
                    $quote = null;
                }
                continue;
            }
            if ($char === "'" || $char === '"' || $char === '`') {
                $quote = $char;
                $result .= $char;
                continue;
            }
            if ($char === '?') {
                $name = '__p' . $index;
                $result .= ':' . $name;
                $this->params[$name] = $values[$index] ?? null;
                $index++;
                continue;
            }
            $result .= $char;
        }
        return $result;
    }

    /**
     * تقييم تعبير SQL بسيط على صف: أعمدة، VALUES(col)، أرقام، +-*÷، أقواس،
     * والدوال ROUND() و NULLIF() و IFNULL() و COALESCE() و ABS().
     * القسمة على صفر والقيم غير المعروفة تُعيد null (سلوك MySQL).
     */
    /** @param array<string,mixed> $incoming القيم الواردة في INSERT (تُستخدم في VALUES(col)) */
    private function expression(string $expression, array $row, array $incoming = []): mixed
    {
        $expression = trim(str_replace('`', '', $expression));
        if ($expression === '') {
            return null;
        }
        if (preg_match('/^NOW\(\)$/i', $expression) === 1) {
            return date('Y-m-d H:i:s');
        }
        if (preg_match('/^CURDATE\(\)$/i', $expression) === 1) {
            return date('Y-m-d');
        }
        // DATE_ADD/DATE_SUB مع INTERVAL (تستخدمها إحصاءات السلاسل الزمنية)
        if (preg_match('/^(DATE_ADD|DATE_SUB)\s*\(\s*(.+?)\s*,\s*INTERVAL\s+(.+?)\s+(SECOND|MINUTE|HOUR|DAY|WEEK|MONTH|YEAR)\s*\)$/is', $expression, $matches) === 1) {
            $base = $this->expression($matches[2], $row, $incoming);
            if ($base === null) {
                return null;
            }
            $amount = $this->expression($matches[3], $row, $incoming);
            $unit = strtolower($matches[4]);
            $sign = strtoupper($matches[1]) === 'DATE_ADD' ? '+' : '-';
            $timestamp = strtotime((string) $base . ' ' . $sign . (int) $amount . ' ' . $unit);
            return $timestamp === false ? null : date('Y-m-d H:i:s', $timestamp);
        }
        // DATE_FORMAT(NOW(), '%Y-%m-01') — تُستخدم لبداية الشهر الحالي
        if (preg_match('/^DATE_FORMAT\s*\(\s*(.+?)\s*,\s*\'([^\']*)\'\s*\)$/is', $expression, $matches) === 1) {
            $value = $this->expression($matches[1], $row, $incoming);
            if ($value === null) {
                return null;
            }
            $timestamp = is_numeric($value) ? (int) $value : (int) strtotime((string) $value);
            return date(str_replace(['%%', '%Y', '%m', '%d'], ['%', 'Y', 'm', 'd'], $matches[2]), $timestamp);
        }
        if (preg_match('/^DATE\s*\(\s*(.+?)\s*\)$/is', $expression, $matches) === 1) {
            $value = $this->expression($matches[1], $row, $incoming);
            return $value === null ? null : substr((string) $value, 0, 10);
        }
        // CASE WHEN ... THEN ... [ELSE ...] END
        if (preg_match('/^CASE\s+(.*?)\s+END$/is', $expression, $matches) === 1) {
            $body = $matches[1];
            $else = null;
            if (preg_match('/\s+ELSE\s+(.+)$/is', $body, $elseMatch) === 1) {
                $else = trim($elseMatch[1]);
                $body = (string) preg_replace('/\s+ELSE\s+.+$/is', '', $body);
            }
            if (preg_match_all('/WHEN\s+(.+?)\s+THEN\s+(.+?)(?=\s+WHEN\s+|$)/is', $body, $whens, PREG_SET_ORDER) > 0) {
                foreach ($whens as $when) {
                    if ($this->matches($row, trim($when[1]))) {
                        return $this->expression(trim($when[2]), $row, $incoming);
                    }
                }
            }
            return $else === null ? null : $this->expression($else, $row, $incoming);
        }
        // تعبير من رمز واحد: معامل (:name) أو نص مقتبس أو رقم أو عمود
        if (preg_match('/^:[A-Za-z_][A-Za-z0-9_]*$/', $expression) === 1) {
            return $this->value($expression, $row);
        }
        if (preg_match("/^'(?:[^']|'')*'$/s", $expression) === 1 || preg_match('/^\d+(\.\d+)?$/', $expression) === 1) {
            return $this->value($expression, $row);
        }
        // حفظ الحالة لأن التقييم قد يكون متداخلاً (دالة داخل استعلام فرعي داخل تعبير)
        $previousRow = $this->exprRow;
        $previousValues = $this->exprValues;
        $previousTokens = $this->exprTokens;
        $previousIndex = $this->exprIndex;
        $this->exprRow = $row;
        $this->exprValues = $incoming;
        $this->exprTokens = $this->tokenize($expression);
        $this->exprIndex = 0;
        try {
            $value = $this->parseExpression();
            if ($this->exprIndex < count($this->exprTokens)) {
                throw new RuntimeException('تعبير غير متوقع');
            }
            $this->exprRow = $previousRow;
            $this->exprValues = $previousValues;
            $this->exprTokens = $previousTokens;
            $this->exprIndex = $previousIndex;
            return $value;
        } catch (Throwable $e) {
            $this->exprRow = $previousRow;
            $this->exprValues = $previousValues;
            $this->exprTokens = $previousTokens;
            $this->exprIndex = $previousIndex;
            // تعبير غير مدعوم: تُعاد القيمة كما هي (بدلاً من إرجاع نتيجة مضلّلة)
            return $this->value($expression, $row);
        }
    }

    /** @var array<string,mixed> */
    private array $exprRow = [];
    /** @var array<string,mixed> */
    private array $exprValues = [];
    /** @var array<int,string> */
    private array $exprTokens = [];
    private int $exprIndex = 0;
    /** اسم مستعار الجدول الأساسي في الاستعلام الحالي */
    private string $outerAlias = '';

    /** @return array<int,string> */
    private function tokenize(string $expression): array
    {
        preg_match_all(
            "/VALUES\s*\(\s*[A-Za-z0-9_]+\s*\)|:[A-Za-z_][A-Za-z0-9_]*|'(?:[^']|'')*'|\"(?:[^\"]|\"\")*\"|[A-Za-z_][A-Za-z0-9_.]*|\d+\.\d+|\d+|[()+\-*\/]|,/i",
            $expression,
            $matches
        );
        return array_values(array_filter($matches[0], static fn(string $token): bool => trim($token) !== ''));
    }

    private function parseExpression(): mixed
    {
        $value = $this->parseTerm();
        while ($this->exprIndex < count($this->exprTokens)) {
            $token = $this->exprTokens[$this->exprIndex];
            if ($token !== '+' && $token !== '-') {
                break;
            }
            $this->exprIndex++;
            $right = $this->parseTerm();
            if ($value === null || $right === null) {
                $value = null;
                continue;
            }
            $value = $token === '+' ? $value + $right : $value - $right;
        }
        return $value;
    }

    private function parseTerm(): mixed
    {
        $value = $this->parseFactor();
        while ($this->exprIndex < count($this->exprTokens)) {
            $token = $this->exprTokens[$this->exprIndex];
            if ($token !== '*' && $token !== '/') {
                break;
            }
            $this->exprIndex++;
            $right = $this->parseFactor();
            if ($value === null || $right === null) {
                $value = null;
                continue;
            }
            if ($token === '/') {
                $value = (float) $right === 0.0 ? null : $value / $right;
            } else {
                $value = $value * $right;
            }
        }
        return $value;
    }

    private function parseFactor(): mixed
    {
        $token = $this->exprTokens[$this->exprIndex] ?? null;
        if ($token === null) {
            return null;
        }
        // أرقام سالبة
        if ($token === '-') {
            $this->exprIndex++;
            $value = $this->parseFactor();
            return $value === null ? null : -$value;
        }
        if ($token === '(') {
            $this->exprIndex++;
            $value = $this->parseExpression();
            if (($this->exprTokens[$this->exprIndex] ?? null) === ')') {
                $this->exprIndex++;
            }
            return $value;
        }
        $this->exprIndex++;
        if (is_numeric($token)) {
            return str_contains($token, '.') ? (float) $token : (int) $token;
        }
        if (str_starts_with($token, ':')) { // معامل مُسمّى
            return $this->value($token, $this->exprRow);
        }
        if (preg_match("/^'(.*)'$/s", $token, $stringMatch) === 1) {
            return str_replace("''", "'", $stringMatch[1]);
        }
        if (preg_match('/^"(.*)"$/s', $token, $stringMatch) === 1) {
            return str_replace('""', '"', $stringMatch[1]);
        }
        if (preg_match('/^VALUES\s*\(\s*([A-Za-z0-9_]+)\s*\)$/i', $token, $matches) === 1) {
            return $this->exprValues[$matches[1]] ?? null;
        }
        if (str_contains($token, '.')) { // عمود مؤهَّل بالجدول: t.id
            return $this->cell($this->exprRow, $token);
        }
        // دالة
        if (($this->exprTokens[$this->exprIndex] ?? null) === '(') {
            $this->exprIndex++;
            $arguments = [];
            while ($this->exprIndex < count($this->exprTokens)) {
                if (($this->exprTokens[$this->exprIndex] ?? null) === ')') {
                    $this->exprIndex++;
                    break;
                }
                if (($this->exprTokens[$this->exprIndex] ?? null) === ',') {
                    $this->exprIndex++;
                    continue;
                }
                $arguments[] = $this->parseExpression();
            }
            return $this->callFunction(strtoupper($token), $arguments);
        }
        return $this->cell($this->exprRow, $token);
    }

    /** @param array<int,mixed> $arguments */
    private function callFunction(string $name, array $arguments): mixed
    {
        return match ($name) {
            'NOW', 'CURRENT_TIMESTAMP' => date('Y-m-d H:i:s'),
            'CURDATE', 'CURRENT_DATE'  => date('Y-m-d'),
            'DATE'    => isset($arguments[0]) && $arguments[0] !== null
                ? substr((string) $arguments[0], 0, 10)
                : null,
            'DATEDIFF' => isset($arguments[0], $arguments[1])
                ? (int) floor(((int) strtotime((string) $arguments[0]) - (int) strtotime((string) $arguments[1])) / 86400)
                : null,
            'DATE_FORMAT' => isset($arguments[0]) && $arguments[0] !== null
                ? date(str_replace(['%%', '%Y', '%m', '%d'], ['%', 'Y', 'm', 'd'], (string) ($arguments[1] ?? 'Y-m-d')), (int) strtotime((string) $arguments[0]))
                : null,
            'ROUND'   => isset($arguments[0]) && $arguments[0] !== null
                ? round((float) $arguments[0], (int) ($arguments[1] ?? 0))
                : null,
            'NULLIF'  => ($arguments[0] ?? null) == ($arguments[1] ?? null) ? null : ($arguments[0] ?? null),
            'IFNULL'  => ($arguments[0] ?? null) ?? ($arguments[1] ?? null),
            'COALESCE'=> (static function (array $values): mixed {
                foreach ($values as $value) {
                    if ($value !== null) {
                        return $value;
                    }
                }
                return null;
            })($arguments),
            'ABS'     => isset($arguments[0]) && $arguments[0] !== null ? abs((float) $arguments[0]) : null,
            'CEIL', 'CEILING' => isset($arguments[0]) && $arguments[0] !== null ? (int) ceil((float) $arguments[0]) : null,
            'FLOOR'   => isset($arguments[0]) && $arguments[0] !== null ? (int) floor((float) $arguments[0]) : null,
            default   => throw new RuntimeException('دالة غير مدعومة في FakeDatabase: ' . $name),
        };
    }

    private function value(string $token, array $row = []): mixed
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }
        if ($token === 'NULL' || strcasecmp($token, 'null') === 0) {
            return null;
        }
        if (strcasecmp($token, 'NOW()') === 0 || strcasecmp($token, 'CURRENT_TIMESTAMP') === 0) {
            return date('Y-m-d H:i:s');
        }
        if (preg_match("/^'((?:[^']|'')*)'$/s", $token, $matches) === 1) {
            return str_replace("''", "'", $matches[1]);
        }
        if (preg_match('/^"((?:[^"]|"")*)"$/s', $token, $matches) === 1) {
            return str_replace('""', '"', $matches[1]);
        }
        if (preg_match('/^:?([A-Za-z_][A-Za-z0-9_]*)$/', $token, $matches) === 1) {
            $name = $matches[1];
            foreach ([$name, ':' . $name] as $candidate) {
                if (array_key_exists($candidate, $this->params)) {
                    return $this->params[$candidate];
                }
            }
            // مقارنة غير حساسة لحالة الأحرف (المعاملات في MySQL حساسة، وهذا تسامح مقصود للاختبارات)
            foreach ($this->params as $key => $value) {
                if (strcasecmp(ltrim((string) $key, ':'), $name) === 0) {
                    return $value;
                }
            }
            return $this->cell($row, $name);
        }
        if (is_numeric($token)) {
            return str_contains($token, '.') ? (float) $token : (int) $token;
        }
        return $token;
    }

    /** @return array<int,string> */
    private function splitList(string $list): array
    {
        $items = [];
        $buffer = '';
        $depth = 0;
        $quote = null;
        $length = strlen($list);
        for ($index = 0; $index < $length; $index++) {
            $char = $list[$index];
            if ($quote !== null) {
                $buffer .= $char;
                if ($char === $quote) {
                    $quote = null;
                }
                continue;
            }
            if ($char === "'" || $char === '"') {
                $quote = $char;
                $buffer .= $char;
                continue;
            }
            if ($char === '(') {
                $depth++;
            }
            if ($char === ')') {
                $depth--;
            }
            if ($char === ',' && $depth === 0) {
                $items[] = trim($buffer);
                $buffer = '';
                continue;
            }
            $buffer .= $char;
        }
        if (trim($buffer) !== '') {
            $items[] = trim($buffer);
        }
        return $items;
    }

    private function stripAliases(string $sql): string
    {
        return (string) preg_replace('/\s+AS\s+`?__c`?$/i', ' AS __c', $sql);
    }

    private function clean(string $identifier): string
    {
        return trim(str_replace('`', '', $identifier));
    }

    /** @param array<string,mixed> $row */
    private function findDuplicate(string $table, array $row): ?int
    {
        foreach (self::$unique[$table] ?? [] as $columns) {
            $allPresent = true;
            foreach ($columns as $column) {
                if (!array_key_exists($column, $row) || $row[$column] === null) {
                    $allPresent = false;
                    break;
                }
            }
            if (!$allPresent) {
                continue;
            }
            foreach (self::$store[$table] as $index => $existing) {
                $same = true;
                foreach ($columns as $column) {
                    if ((string) ($existing[$column] ?? null) !== (string) $row[$column]) {
                        $same = false;
                        break;
                    }
                }
                if ($same) {
                    return $index;
                }
            }
        }
        return null;
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    /** خريطة اسم مستعار → تعبير في قائمة SELECT */
    private function selectExpressions(string $columns): array
    {
        $map = [];
        foreach ($this->splitList($columns) as $item) {
            if (preg_match('/^(.*)\s+AS\s+([A-Za-z0-9_]+)$/is', $item, $matches) === 1) {
                $map[$matches[2]] = trim($matches[1]);
            }
        }
        return $map;
    }

    /**
     * تقييم قائمة SELECT على مجموعة صفوف (نتيجة GROUP BY).
     * @param array<int,array<string,mixed>> $group
     * @param array<int,string> $expressions
     * @return array<string,mixed>
     */
    private function pickGrouped(array $group, array $expressions): array
    {
        $first = $group[0] ?? [];
        $picked = [];
        foreach ($expressions as $item) {
            if ($item === '*' || preg_match('/^[`A-Za-z0-9_]+\.\*$/', $item) === 1) {
                $picked = array_merge($picked, $first);
                continue;
            }
            $alias = null;
            $expression = $item;
            if (preg_match('/^(.*)\s+AS\s+([A-Za-z0-9_]+)$/is', $item, $matches) === 1) {
                $expression = trim($matches[1]);
                $alias = $matches[2];
            }
            $name = $alias ?? $this->clean($expression);
            if (str_contains($name, '.')) {
                $name = substr($name, (int) strrpos($name, '.') + 1);
            }
            if ($this->looksLikeSubquery($expression)) {
                $picked[$alias ?? 'subquery'] = $this->subqueryValue($expression, $first);
                continue;
            }
            // سلوك MySQL/PHP السابق: دالة تجميع بلا اسم مستعار تُسمّى c
            if ($alias === null && preg_match('/^(COUNT|SUM|AVG|MIN|MAX)\s*\(/i', trim($expression)) === 1) {
                $name = 'c';
            }
            $picked[$name] = $this->groupValue($expression, $group);
        }
        return $picked;
    }

    /** @param array<int,array<string,mixed>> $group */
    private function groupValue(string $expression, array $group): mixed
    {
        $first = $group[0] ?? [];
        // استعلام فرعي مترابط في قائمة SELECT
        if ($this->looksLikeSubquery($expression)) {
            return $this->subqueryValue($expression, $first);
        }
        // تعبير يضم دوال تجميع: تُستبدل كل دالة بقيمتها ثم يُقيَّم الباقي
        // مثال: COALESCE(AVG(passed) * 100, 0) — ودوال مجمّعة مفردة أيضاً
        $substituted = (string) preg_replace_callback(
            '/\b(COUNT|SUM|AVG|MIN|MAX)\s*\((?:[^()]|\([^()]*\))*\)/i',
            function (array $match) use ($group): string {
                $value = $this->aggregateValue($match[0], $group);
                return $value === null ? 'NULL' : (string) $value;
            },
            $expression
        );
        if ($substituted !== $expression) {
            return $this->expression($substituted, $first);
        }
        return $this->expression($expression, $first);
    }

    /** هل النص استعلام فرعي كامل بين قوسين؟ */
    private function looksLikeSubquery(string $expression): bool
    {
        return preg_match('/^\(\s*SELECT\s/i', trim($expression)) === 1;
    }

    /** تنفيذ استعلام فرعي مترابط (SELECT ... من جدول واحد + WHERE اختياري) */
    private function subqueryValue(string $subquery, array $outerRow): mixed
    {
        $subquery = trim($subquery);
        $body = trim(substr($subquery, 1, -1));
        $fromPosition = $this->topLevelPosition($body, 'FROM', 6);
        if ($fromPosition === null
            || preg_match('/^\s+`?([A-Za-z0-9_]+)`?(.*)$/s', substr($body, $fromPosition + 4), $inner) !== 1) {
            return null;
        }
        $select = trim(substr($body, 6, $fromPosition - 6));
        $table = $this->clean($inner[1]);
        $innerTail = $inner[2];
        $wherePosition = $this->topLevelPosition($innerTail, 'WHERE');
        $where = $wherePosition === null ? '' : trim(substr($innerTail, $wherePosition + 5));
        // قصّ الشرط عند ORDER BY / LIMIT / HAVING كما في الاستعلامات الفرعية الشائعة
        if ($where !== '') {
            $boundary = null;
            foreach (['GROUP BY', 'HAVING', 'ORDER BY', 'LIMIT'] as $keyword) {
                $position = $this->topLevelPosition($where, $keyword);
                if ($position !== null && ($boundary === null || $position < $boundary)) {
                    $boundary = $position;
                }
            }
            if ($boundary !== null) {
                $where = trim(substr($where, 0, $boundary));
            }
        }
        $select = (string) preg_replace('/\s+AS\s+[A-Za-z0-9_]+\s*$/i', '', $select);

        // دمج صف الجدول الخارجي مع صفوف الجدول الداخلي (مفاتيح مؤهَّلة بالجدولين)
        $merged = [];
        foreach (self::$store[$table] ?? [] as $row) {
            $candidate = $row;
            foreach ($outerRow as $key => $value) {
                if (!array_key_exists($key, $candidate)) {
                    $candidate[$key] = $value;
                }
                if ($this->outerAlias !== '') {
                    $candidate[$this->outerAlias . '.' . $key] = $value;
                }
            }
            $merged[] = $candidate;
        }
        $filtered = $where === '' ? $merged : array_values(array_filter($merged, fn(array $row): bool => $this->matches($row, $where)));
        if (preg_match('/\b(COUNT|SUM|AVG|MIN|MAX)\s*\(/i', $select) === 1) {
            return $this->aggregateValue($select, $filtered);
        }
        if (preg_match('/\sORDER\s+BY\s+(.*?)(?:\s+LIMIT\s+(\d+))?$/is', $innerTail, $orderMatch) === 1
            && strcasecmp(trim($orderMatch[1]), '') !== 0
            && $this->topLevelPosition($innerTail, 'ORDER BY') !== null) {
            $ordered = $this->orderBy($filtered, ' ORDER BY ' . $orderMatch[1]);
            $filtered = $ordered;
        }
        if (preg_match('/\sLIMIT\s+(\d+)/i', $innerTail, $limitMatch) === 1) {
            $filtered = array_slice($filtered, 0, (int) $limitMatch[1]);
        }
        return $filtered === [] ? null : $this->expression($select, $filtered[0]);
    }

    /** @param array<int,array<string,mixed>> $group */
    private function aggregateValue(string $expression, array $group): mixed
    {
        if (preg_match('/^([A-Z]+)\s*\(\s*(DISTINCT\s+)?(.*?)\s*\)$/is', trim($expression), $matches) !== 1) {
            // تعبير مركّب غير مدعوم: يُقيَّم على أول صف (تقريب) بلا إسقاط الصفحة
            try {
                return $this->expression($expression, $group[0] ?? []);
            } catch (Throwable) {
                return null;
            }
        }
        $function = strtoupper($matches[1]);
        $argument = trim($matches[3]);
        $values = [];
        if ($argument === '*' || $argument === '') {
            $values = array_fill(0, count($group), 1);
        } else {
            foreach ($group as $row) {
                $value = $this->expression($argument, $row);
                if ($value !== null) {
                    $values[] = $value;
                }
            }
        }
        if ($function === 'COUNT') {
            return count($argument === '*' ? $group : $values);
        }
        if ($values === []) {
            return null;
        }
        return match ($function) {
            'SUM' => array_sum(array_map('floatval', $values)),
            'AVG' => array_sum(array_map('floatval', $values)) / count($values),
            'MIN' => min($values),
            'MAX' => max($values),
            default => null,
        };
    }

    private function pick(array $row, string $columns): array
    {
        $picked = [];
        foreach ($this->splitList($columns) as $column) {
            // SELECT * أو SELECT q.* → الصف كامل
            if ($column === '*' || preg_match('/^[`A-Za-z0-9_]+\.\*$/', $column) === 1) {
                $picked = array_merge($picked, $row);
                continue;
            }
            $alias = null;
            $expression = $column;
            if (preg_match('/^(.*)\s+AS\s+([A-Za-z0-9_]+)$/is', $column, $matches) === 1) {
                $expression = trim($matches[1]);
                $alias = $matches[2];
            }
            $name = $this->clean($expression);
            if (str_contains($name, '.')) {
                $name = substr($name, (int) strrpos($name, '.') + 1);
            }
            if ($this->looksLikeSubquery($expression)) {
                $picked[$alias ?? 'subquery'] = $this->subqueryValue($expression, $row);
                continue;
            }
            // تعبير محسوب (دالة أو عملية حسابية) وليس عموداً مجرداً
            if (preg_match('/^[`A-Za-z0-9_.]+$/', $expression) !== 1) {
                $picked[$alias ?? $name] = $this->expression($expression, $row);
                continue;
            }
            $picked[$alias ?? $name] = $this->cell($row, $name);
        }
        return $picked;
    }
}

/**
 * عبارة PDO وهمية تُعيد النتائج المحسوبة من المحرك.
 */
final class FakeStatement
{
    /** @var array<int,array<string,mixed>> */
    private array $rows = [];
    private int $count = 0;
    private int $cursor = 0;

    public function __construct(private FakeDatabase $engine, private string $sql)
    {
    }

    /** اسم السائق (توافق مع واجهة PDOStatement) */
    public function setFetchMode(int $mode, mixed ...$args): bool
    {
        return true;
    }

    /** @param array<string,mixed>|null $params */
    public function execute(?array $params = null): bool
    {
        $this->cursor = 0;
        $this->engine->run($this->sql, $params ?? [], $this);
        return true;
    }

    /** @param array<int,array<string,mixed>> $rows */
    public function setResult(array $rows, int $count): void
    {
        $this->rows = $rows;
        $this->count = $count;
    }

    public function fetch(int $mode = 2, int $cursorOrientation = 0, int $cursorOffset = 0): mixed
    {
        if (!isset($this->rows[$this->cursor])) {
            return false;
        }
        $row = $this->rows[$this->cursor];
        $this->cursor++;
        if ($mode === 7 || $mode === 3) { // FETCH_COLUMN | FETCH_NUM
            return array_values($row)[0] ?? false;
        }
        return $row;
    }

    /** @return array<int,mixed> */
    public function fetchAll(int $mode = 2, mixed ...$args): array
    {
        $rows = array_slice($this->rows, $this->cursor);
        $this->cursor = count($this->rows);
        if ($mode === 7) { // FETCH_COLUMN
            return array_map(static fn(array $row): mixed => array_values($row)[0] ?? null, $rows);
        }
        return $rows;
    }

    public function fetchColumn(int $column = 0): mixed
    {
        $row = $this->fetch();
        if ($row === false || !is_array($row)) {
            return false;
        }
        $values = array_values($row);
        return $values[$column] ?? false;
    }

    public function rowCount(): int
    {
        return $this->count;
    }

    public function closeCursor(): bool
    {
        $this->cursor = 0;
        return true;
    }
}
