<?php
declare(strict_types=1);

namespace App;

use PDO;
use PDOException;
use RuntimeException;

/**
 * طبقة قاعدة البيانات - PDO مع Prepared Statements في كل الاستعلامات.
 * كل الاستعلامات في المشروع تمر من هنا، ولا يتم دمج أي مدخل من المستخدم داخل نص الاستعلام.
 */
final class Database
{
    private static ?Database $instance = null;

    /**
     * منفذ حقن اتصال بديل (للاختبارات والمعاينة التجريبية فقط).
     * يعيد إما PDO حقيقياً أو كائناً يشبهه في الواجهة (prepare/lastInsertId/transaction).
     * @var null|callable():object
     */
    private static $connectionFactory = null;

    private ?PDO $pdo = null;

    /** الكائن المستخدم فعلياً كاتصال: PDO حقيقي أو برنامج اختبار */
    private ?object $driverInstance = null;
    private int $queries = 0;
    private float $time = 0.0;

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public static function setConnectionFactory(?callable $factory): void
    {
        self::$connectionFactory = $factory;
        if (self::$instance !== null) {
            self::$instance->pdo = null;
            self::$instance->driverInstance = null;
        }
    }

    /** إعادة تعيين الاتصال (يُستخدم في الاختبارات وفي install.php بعد تغيير الإعدادات) */
    public static function reset(): void
    {
        self::$instance = null;
    }

    /** الاتصال المستخدم فعلياً (PDO في الإنتاج، أو بديل في الاختبارات) */
    public function driver(): object
    {
        if ($this->driverInstance !== null) {
            return $this->driverInstance;
        }
        if (is_callable(self::$connectionFactory)) {
            return $this->driverInstance = (self::$connectionFactory)();
        }
        return $this->driverInstance = $this->connectPdo();
    }

    /** اتصال PDO حقيقي بالـ MySQL (يُستخدم مباشرة في الحالات التي تحتاج PDO) */
    public function pdo(): PDO
    {
        $driver = $this->driver();
        if (!$driver instanceof PDO) {
            throw new RuntimeException('لا يوجد اتصال PDO حقيقي في وضع الاختبار الحالي.');
        }
        return $driver;
    }

    private function connectPdo(): PDO
    {
        if ($this->pdo instanceof PDO) {
            return $this->pdo;
        }

        $cfg = config('database');
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $cfg['host'],
            (int) $cfg['port'],
            $cfg['name'],
            $cfg['charset']
        );

        try {
            $this->pdo = new PDO($dsn, (string) $cfg['user'], (string) $cfg['pass'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_STRINGIFY_FETCHES  => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$cfg['charset']} COLLATE {$cfg['charset']}_unicode_ci, time_zone = '+03:00'",
            ]);
        } catch (PDOException $e) {
            Logger::error('تعذّر الاتصال بقاعدة البيانات: ' . $e->getMessage());
            throw new RuntimeException(
                'تعذّر الاتصال بقاعدة البيانات. تأكد من إعدادات قاعدة البيانات في ملف .env',
                0,
                $e
            );
        }

        return $this->pdo;
    }

    // ------------------------------------------------------------------
    //  استعلامات
    // ------------------------------------------------------------------

    /**
     * تنفيذ استعلام جاهز.
     * القيمة المعادة PDOStatement في الإنتاج، وبديل مطابق الواجهة في الاختبارات.
     * @param array<string,mixed> $params
     */
    public function query(string $sql, array $params = []): mixed
    {
        $start = microtime(true);
        try {
            $stmt = $this->driver()->prepare($sql);
            $stmt->execute($this->normalize($params));
        } catch (PDOException $e) {
            Logger::error('خطأ في استعلام قاعدة البيانات', [
                'sql'     => $sql,
                'params'  => $params,
                'message' => $e->getMessage(),
            ]);
            throw new RuntimeException(
                config('app.debug') ? 'خطأ SQL: ' . $e->getMessage() . ' | ' . $sql : 'حدث خطأ أثناء تنفيذ العملية على قاعدة البيانات',
                0,
                $e
            );
        }
        $this->queries++;
        $this->time += microtime(true) - $start;
        return $stmt;
    }

    /** @param array<string,mixed> $params */
    public function one(string $sql, array $params = []): ?array
    {
        $row = $this->query($sql, $params)->fetch();
        return is_array($row) ? $row : null;
    }

    /** @param array<string,mixed> $params @return array<int,array<string,mixed>> */
    public function all(string $sql, array $params = []): array
    {
        $rows = $this->query($sql, $params)->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    /** @param array<string,mixed> $params */
    public function value(string $sql, array $params = [], mixed $default = null): mixed
    {
        $value = $this->query($sql, $params)->fetchColumn();
        return ($value === false) ? $default : $value;
    }

    /** @param array<string,mixed> $params @return array<int,mixed> */
    public function column(string $sql, array $params = []): array
    {
        $rows = $this->query($sql, $params)->fetchAll(PDO::FETCH_COLUMN);
        return is_array($rows) ? $rows : [];
    }

    public function scalar(string $sql, array $params = []): int|float|string|null
    {
        $row = $this->one($sql, $params);
        if ($row === null) {
            return null;
        }
        $value = reset($row);
        return is_scalar($value) ? $value : null;
    }

    /** @param array<string,mixed> $data */
    public function insert(string $table, array $data): int
    {
        $columns = array_keys($data);
        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            $this->safeIdentifier($table),
            implode(', ', array_map(fn($c) => '`' . $this->safeIdentifier($c) . '`', $columns)),
            implode(', ', array_map(fn($c) => ':' . $c, $columns))
        );
        $this->query($sql, $data);
        return (int) $this->driver()->lastInsertId();
    }

    /**
     * @param array<string,mixed> $data
     * @param array<string,mixed> $params
     */
    public function update(string $table, array $data, string $where, array $params = []): int
    {
        $sets = [];
        foreach (array_keys($data) as $column) {
            $sets[] = '`' . $this->safeIdentifier($column) . '` = :' . $column;
        }
        $sql = sprintf(
            'UPDATE `%s` SET %s WHERE %s',
            $this->safeIdentifier($table),
            implode(', ', $sets),
            $where
        );
        return $this->query($sql, array_merge($data, $params))->rowCount();
    }

    /** @param array<string,mixed> $params */
    public function delete(string $table, string $where, array $params = []): int
    {
        $sql = sprintf('DELETE FROM `%s` WHERE %s', $this->safeIdentifier($table), $where);
        return $this->query($sql, $params)->rowCount();
    }

    /** @param array<string,mixed> $params */
    public function count(string $table, string $where = '1', array $params = []): int
    {
        return (int) $this->value(sprintf('SELECT COUNT(*) FROM `%s` WHERE %s', $this->safeIdentifier($table), $where), $params, 0);
    }

    public function tableExists(string $table): bool
    {
        try {
            $found = $this->value(
                'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :t',
                ['t' => $table]
            );
            return (int) $found > 0;
        } catch (RuntimeException) {
            return false;
        }
    }

    /**
     * تنفيذ مجموعة عمليات داخل معاملة واحدة (Transaction).
     * @template T
     * @param callable(self):T $callback
     * @return T
     */
    public function transaction(callable $callback): mixed
    {
        $driver = $this->driver();
        $driver->beginTransaction();
        try {
            $result = $callback($this);
            $driver->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($driver->inTransaction()) {
                $driver->rollBack();
            }
            throw $e;
        }
    }

    /**
     * ترقيم الصفحات: يعيد ['rows' => ..., 'total' => ...]
     * @param array<string,mixed> $params
     * @return array{rows:array<int,array<string,mixed>>,total:int,pages:int,page:int,per_page:int}
     */
    public function paginate(string $sql, array $params = [], int $perPage = 20, int $page = 1): array
    {
        $perPage = max(1, min(200, $perPage));
        $page = max(1, $page);

        $countSql = 'SELECT COUNT(*) FROM (' . $sql . ') AS __c';
        $total = (int) $this->value($countSql, $params, 0);

        $offset = ($page - 1) * $perPage;
        $rows = $this->all($sql . ' LIMIT ' . $perPage . ' OFFSET ' . $offset, $params);

        return [
            'rows'     => $rows,
            'total'    => $total,
            'pages'    => (int) max(1, (int) ceil($total / $perPage)),
            'page'     => $page,
            'per_page' => $perPage,
        ];
    }

    public function queryCount(): int
    {
        return $this->queries;
    }

    public function queryTime(): float
    {
        return round($this->time, 4);
    }

    /** @param array<string,mixed> $params @return array<string,mixed> */
    private function normalize(array $params): array
    {
        $normalized = [];
        foreach ($params as $key => $value) {
            $name = is_int($key) ? $key : (str_starts_with((string) $key, ':') ? $key : ':' . $key);
            $normalized[$name] = match (true) {
                is_bool($value) => $value ? 1 : 0,
                is_array($value) => json_encode($value, JSON_UNESCAPED_UNICODE),
                default => $value,
            };
        }
        return $normalized;
    }

    private function safeIdentifier(string $name): string
    {
        if (preg_match('/^[A-Za-z0-9_]+$/', $name) !== 1) {
            throw new RuntimeException('اسم جدول/عمود غير صالح: ' . $name);
        }
        return $name;
    }
}
