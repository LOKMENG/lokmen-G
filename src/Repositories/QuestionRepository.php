<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Database;
use App\Str;

/**
 * بنك الأسئلة: بحث متقدم، إضافة/تعديل، منع التكرار، وإحصاءات.
 */
final class QuestionRepository
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::instance();
    }

    /**
     * بحث متقدم مع ترقيم صفحات.
     * المفاتيح المدعومة: q, track_id, category_id, subcategory_id, source_id, difficulty,
     *                    active, needs_review, correct_answer, id, has_explanation, reported, has_answers
     * @param array<string,mixed> $filters
     * @return array{rows:array<int,array<string,mixed>>,total:int,pages:int,page:int,per_page:int}
     */
    public function search(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        [$where, $params] = $this->buildWhere($filters);
        $sql = "SELECT q.*, t.name_ar AS track_name, c.name_ar AS category_name, sc.name_ar AS subcategory_name,
                       s.name AS source_name
                  FROM `questions` q
                  JOIN `tracks` t ON t.id = q.track_id
             LEFT JOIN `categories` c ON c.id = q.category_id
             LEFT JOIN `categories` sc ON sc.id = q.subcategory_id
             LEFT JOIN `sources` s ON s.id = q.source_id
                 WHERE {$where}
              ORDER BY q.id DESC";
        return $this->db->paginate($sql, $params, $perPage, $page);
    }

    /** @param array<string,mixed> $filters @return array<int,array<string,mixed>> */
    public function exportAll(array $filters = [], int $limit = 5000): array
    {
        [$where, $params] = $this->buildWhere($filters);
        return $this->db->all(
            "SELECT q.*, t.name_ar AS track_name, c.name_ar AS category_name
               FROM `questions` q
               JOIN `tracks` t ON t.id = q.track_id
          LEFT JOIN `categories` c ON c.id = q.category_id
              WHERE {$where} ORDER BY q.id ASC LIMIT " . max(1, $limit),
            $params
        );
    }

    /**
     * @param array<string,mixed> $filters
     * @return array{0:string,1:array<string,mixed>}
     */
    public function buildWhere(array $filters): array
    {
        $clauses = [];
        $params = [];

        if (!empty($filters['id'])) {
            $clauses[] = 'q.id = :id';
            $params['id'] = (int) $filters['id'];
        }
        if (!empty($filters['q'])) {
            $term = '%' . trim((string) $filters['q']) . '%';
            $clauses[] = '(q.question_text LIKE :term OR q.option_a LIKE :term OR q.option_b LIKE :term
                           OR q.option_c LIKE :term OR q.option_d LIKE :term OR q.explanation LIKE :term)';
            $params['term'] = $term;
        }
        if (!empty($filters['track_id'])) {
            $clauses[] = 'q.track_id = :track_id';
            $params['track_id'] = (int) $filters['track_id'];
        }
        if (!empty($filters['category_id'])) {
            $ids = $this->categoryWithChildren((int) $filters['category_id']);
            $clauses[] = 'q.category_id IN (' . $this->placeholders('cat', count($ids), $params, $ids) . ')';
        }
        if (!empty($filters['category_ids']) && is_array($filters['category_ids'])) {
            $ids = [];
            foreach ($filters['category_ids'] as $categoryId) {
                $ids = array_merge($ids, $this->categoryWithChildren((int) $categoryId));
            }
            $ids = array_values(array_unique($ids));
            if ($ids !== []) {
                $clauses[] = 'q.category_id IN (' . $this->placeholders('cats', count($ids), $params, $ids) . ')';
            }
        }
        if (!empty($filters['subcategory_id'])) {
            $clauses[] = 'q.subcategory_id = :subcategory_id';
            $params['subcategory_id'] = (int) $filters['subcategory_id'];
        }
        if (!empty($filters['source_id'])) {
            $clauses[] = 'q.source_id = :source_id';
            $params['source_id'] = (int) $filters['source_id'];
        }
        if (!empty($filters['difficulty']) && $filters['difficulty'] !== 'any') {
            $clauses[] = 'q.difficulty = :difficulty';
            $params['difficulty'] = (string) $filters['difficulty'];
        }
        if (isset($filters['active']) && $filters['active'] !== '' && $filters['active'] !== null) {
            $clauses[] = 'q.active = :active';
            $params['active'] = (int) $filters['active'] === 1 ? 1 : 0;
        }
        if (isset($filters['needs_review']) && $filters['needs_review'] !== '' && $filters['needs_review'] !== null) {
            $clauses[] = 'q.needs_review = :needs_review';
            $params['needs_review'] = (int) $filters['needs_review'] === 1 ? 1 : 0;
        }
        if (!empty($filters['correct_answer'])) {
            $clauses[] = 'q.correct_answer = :correct_answer';
            $params['correct_answer'] = (string) $filters['correct_answer'];
        }
        if (!empty($filters['reported'])) {
            $clauses[] = 'EXISTS (SELECT 1 FROM `question_reports` qr WHERE qr.question_id = q.id AND qr.status = \'open\')';
        }
        if (!empty($filters['has_explanation'])) {
            $clauses[] = "q.explanation IS NOT NULL AND q.explanation <> ''";
        }
        // الأسئلة الجاهزة للاستخدام في الاختبارات
        if (!empty($filters['exam_ready'])) {
            $clauses[] = "q.active = 1 AND q.correct_answer IS NOT NULL AND q.needs_review = 0
                          AND q.option_a IS NOT NULL AND q.option_b IS NOT NULL";
        }
        // الاستبعاد بالمعرّفات (يستخدمها محرك الاختبار)
        if (!empty($filters['exclude_ids']) && is_array($filters['exclude_ids'])) {
            $ids = array_map('intval', $filters['exclude_ids']);
            $clauses[] = 'q.id NOT IN (' . $this->placeholders('ex', count($ids), $params, $ids) . ')';
        }
        if (!empty($filters['ids']) && is_array($filters['ids'])) {
            $ids = array_map('intval', $filters['ids']);
            $clauses[] = 'q.id IN (' . $this->placeholders('inc', count($ids), $params, $ids) . ')';
        }

        return [$clauses === [] ? '1' : implode(' AND ', $clauses), $params];
    }

    /** @return array<int,array<string,mixed>> */
    public function random(int $limit, array $filters = []): array
    {
        $filters['exam_ready'] = true;
        [$where, $params] = $this->buildWhere($filters);
        $limit = max(1, min(200, $limit));
        return $this->db->all(
            "SELECT q.* FROM `questions` q WHERE {$where} ORDER BY RAND() LIMIT {$limit}",
            $params
        );
    }

    /** @return array<int,int> */
    public function randomIds(int $limit, array $filters = []): array
    {
        $filters['exam_ready'] = true;
        [$where, $params] = $this->buildWhere($filters);
        $limit = max(1, min(200, $limit));
        $ids = $this->db->column("SELECT q.id FROM `questions` q WHERE {$where} ORDER BY RAND() LIMIT {$limit}", $params);
        return array_map('intval', $ids);
    }

    /** @return array<int,array<string,mixed>> */
    public function byIds(array $ids, bool $preserveOrder = true): array
    {
        if ($ids === []) {
            return [];
        }
        $params = [];
        $ids = array_map('intval', $ids);
        $in = $this->placeholders('q', count($ids), $params, $ids);
        $rows = $this->db->all(
            "SELECT q.*, t.name_ar AS track_name, c.name_ar AS category_name
               FROM `questions` q
               JOIN `tracks` t ON t.id = q.track_id
          LEFT JOIN `categories` c ON c.id = q.category_id
              WHERE q.id IN ({$in})",
            $params
        );
        if (!$preserveOrder) {
            return $rows;
        }
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[(int) $row['id']] = $row;
        }
        $ordered = [];
        foreach ($ids as $id) {
            if (isset($indexed[$id])) {
                $ordered[] = $indexed[$id];
            }
        }
        return $ordered;
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->one(
            "SELECT q.*, t.name_ar AS track_name, c.name_ar AS category_name, sc.name_ar AS subcategory_name,
                    s.name AS source_name
               FROM `questions` q
               JOIN `tracks` t ON t.id = q.track_id
          LEFT JOIN `categories` c ON c.id = q.category_id
          LEFT JOIN `categories` sc ON sc.id = q.subcategory_id
          LEFT JOIN `sources` s ON s.id = q.source_id
              WHERE q.id = :id",
            ['id' => $id]
        );
    }

    /** @param array<string,mixed> $data */
    public function create(array $data): int
    {
        $data['content_hash'] ??= Str::contentFingerprint((string) $data['question_text']);
        $data['created_by'] ??= \user_id();
        return $this->db->insert('questions', $data);
    }

    /** @param array<string,mixed> $data */
    public function update(int $id, array $data): bool
    {
        if (isset($data['question_text'])) {
            $data['content_hash'] = Str::contentFingerprint((string) $data['question_text']);
        }
        $data['updated_by'] = \user_id();
        return $this->db->update('questions', $data, 'id = :id', ['id' => $id]) >= 0;
    }

    public function delete(int $id): bool
    {
        return $this->db->delete('questions', 'id = :id', ['id' => $id]) > 0;
    }

    public function toggleActive(int $id): bool
    {
        $this->db->query('UPDATE `questions` SET active = 1 - active WHERE id = :id', ['id' => $id]);
        $value = $this->db->value('SELECT active FROM `questions` WHERE id = :id', ['id' => $id], 0);
        return (int) $value === 1;
    }

    /** @param array<int,int> $ids */
    public function bulkSet(array $ids, array $fields): int
    {
        if ($ids === [] || $fields === []) {
            return 0;
        }
        $params = [];
        $in = $this->placeholders('b', count($ids), $params, array_map('intval', $ids));
        $sets = [];
        foreach ($fields as $column => $value) {
            $sets[] = '`' . preg_replace('/[^a-z_]/', '', (string) $column) . '` = :' . $column;
            $params[$column] = $value;
        }
        return $this->db->query('UPDATE `questions` SET ' . implode(', ', $sets) . " WHERE id IN ({$in})", $params)->rowCount();
    }

    /** @param array<int,int> $ids */
    public function bulkDelete(array $ids): int
    {
        if ($ids === []) {
            return 0;
        }
        $params = [];
        $in = $this->placeholders('d', count($ids), $params, array_map('intval', $ids));
        return $this->db->query("DELETE FROM `questions` WHERE id IN ({$in})", $params)->rowCount();
    }

    public function duplicateExists(string $hash, ?int $ignoreId = null): ?array
    {
        $sql = 'SELECT id, question_text FROM `questions` WHERE content_hash = :h';
        $params = ['h' => $hash];
        if ($ignoreId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $ignoreId;
        }
        return $this->db->one($sql . ' LIMIT 1', $params);
    }

    public function setNeedsReview(int $id, bool $flag, string $note = ''): void
    {
        $this->db->update('questions', [
            'needs_review' => $flag ? 1 : 0,
            'review_note'  => $note !== '' ? $note : null,
        ], 'id = :id', ['id' => $id]);
    }

    public function recordAnswer(int $questionId, bool $correct): void
    {
        $this->db->query(
            'UPDATE `questions`
                SET times_answered = times_answered + 1,
                    times_correct = times_correct + :correct
              WHERE id = :id',
            ['correct' => $correct ? 1 : 0, 'id' => $questionId]
        );
    }

    // ------------------------------------------------------------------
    //  التصنيفات والمصادر
    // ------------------------------------------------------------------

    /** @return array<int,array<string,mixed>> */
    public function tracks(bool $onlyActive = false): array
    {
        $sql = 'SELECT t.*, (SELECT COUNT(*) FROM `questions` q WHERE q.track_id = t.id) AS questions_count
                  FROM `tracks` t';
        if ($onlyActive) {
            $sql .= ' WHERE t.is_active = 1';
        }
        return $this->db->all($sql . ' ORDER BY t.sort_order, t.id');
    }

    /** @return array<int,array<string,mixed>> */
    public function categories(?int $trackId = null, bool $onlyActive = false, ?int $parentId = null): array
    {
        $clauses = [];
        $params = [];
        if ($trackId !== null) {
            $clauses[] = 'c.track_id = :track_id';
            $params['track_id'] = $trackId;
        }
        if ($onlyActive) {
            $clauses[] = 'c.is_active = 1';
        }
        if ($parentId !== null) {
            $clauses[] = 'c.parent_id = :parent_id';
            $params['parent_id'] = $parentId;
        }
        $where = $clauses === [] ? '1' : implode(' AND ', $clauses);
        return $this->db->all(
            "SELECT c.*, t.name_ar AS track_name,
                    (SELECT COUNT(*) FROM `questions` q WHERE q.category_id = c.id AND q.active = 1) AS questions_count,
                    (SELECT COUNT(*) FROM `questions` q WHERE q.subcategory_id = c.id AND q.active = 1) AS sub_questions_count
               FROM `categories` c
               JOIN `tracks` t ON t.id = c.track_id
              WHERE {$where}
              ORDER BY c.track_id, c.parent_id IS NULL DESC, c.sort_order, c.id",
            $params
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function categoryTree(?int $trackId = null, bool $onlyActive = true): array
    {
        $all = $this->categories($trackId, $onlyActive);
        $tree = [];
        foreach ($all as $category) {
            if ($category['parent_id'] === null) {
                $children = array_values(array_filter($all, static fn(array $c): bool => (int) $c['parent_id'] === (int) $category['id']));
                $category['children'] = $children;
                $category['total_questions'] = (int) $category['questions_count'] + array_sum(array_column($children, 'questions_count'));
                $tree[] = $category;
            }
        }
        return $tree;
    }

    /** @return array<int,int> التصنيف ومعرّفات أبنائه */
    public function categoryWithChildren(int $categoryId): array
    {
        $ids = [$categoryId];
        $children = $this->db->column('SELECT id FROM `categories` WHERE parent_id = :id', ['id' => $categoryId]);
        foreach ($children as $childId) {
            $ids[] = (int) $childId;
        }
        return $ids;
    }

    /** @return array<int,array<string,mixed>> */
    public function sources(): array
    {
        return $this->db->all(
            'SELECT s.*, (SELECT COUNT(*) FROM `questions` q WHERE q.source_id = s.id) AS questions_count
               FROM `sources` s ORDER BY s.name'
        );
    }

    // ------------------------------------------------------------------
    //  إحصاءات
    // ------------------------------------------------------------------

    /** @return array<string,int|float> */
    public function statistics(): array
    {
        return [
            'total'        => (int) $this->db->value('SELECT COUNT(*) FROM `questions`', [], 0),
            'active'       => (int) $this->db->value('SELECT COUNT(*) FROM `questions` WHERE active = 1', [], 0),
            'needs_review' => (int) $this->db->value('SELECT COUNT(*) FROM `questions` WHERE needs_review = 1', [], 0),
            'without_answer' => (int) $this->db->value('SELECT COUNT(*) FROM `questions` WHERE correct_answer IS NULL', [], 0),
            'easy'         => (int) $this->db->value("SELECT COUNT(*) FROM `questions` WHERE difficulty = 'easy'", [], 0),
            'medium'       => (int) $this->db->value("SELECT COUNT(*) FROM `questions` WHERE difficulty = 'medium'", [], 0),
            'hard'         => (int) $this->db->value("SELECT COUNT(*) FROM `questions` WHERE difficulty = 'hard'", [], 0),
            'open_reports' => (int) $this->db->value("SELECT COUNT(*) FROM `question_reports` WHERE status = 'open'", [], 0),
        ];
    }

    /** أكثر الأسئلة خطأً عند الطلاب */
    /** @return array<int,array<string,mixed>> */
    public function mostWrong(int $limit = 10): array
    {
        return $this->db->all(
            "SELECT q.id, q.question_text, q.difficulty, q.times_answered, q.times_correct,
                    c.name_ar AS category_name,
                    ROUND(((q.times_answered - q.times_correct) / NULLIF(q.times_answered, 0)) * 100, 1) AS wrong_rate
               FROM `questions` q
          LEFT JOIN `categories` c ON c.id = q.category_id
              WHERE q.times_answered >= 3
              ORDER BY wrong_rate DESC, q.times_answered DESC
              LIMIT " . max(1, min(50, $limit))
        );
    }

    /** أكثر المجالات ضعفاً على مستوى المنصة */
    /** @return array<int,array<string,mixed>> */
    public function weakestCategories(int $limit = 8): array
    {
        return $this->db->all(
            "SELECT c.id, c.name_ar, t.name_ar AS track_name,
                    SUM(u.total_answered) AS total_answered,
                    ROUND((SUM(u.correct_answers) / NULLIF(SUM(u.total_answered), 0)) * 100, 1) AS accuracy
               FROM `user_category_stats` u
               JOIN `categories` c ON c.id = u.category_id
               JOIN `tracks` t ON t.id = u.track_id
              GROUP BY c.id, c.name_ar, t.name_ar
             HAVING total_answered >= 5
              ORDER BY accuracy ASC
              LIMIT " . max(1, min(30, $limit))
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function reports(bool $openOnly = true, int $limit = 50): array
    {
        $where = $openOnly ? "WHERE qr.status = 'open'" : '';
        return $this->db->all(
            "SELECT qr.*, q.question_text, u.full_name
               FROM `question_reports` qr
               JOIN `questions` q ON q.id = qr.question_id
          LEFT JOIN `users` u ON u.id = qr.user_id
               {$where}
              ORDER BY qr.id DESC LIMIT " . max(1, min(200, $limit))
        );
    }

    public function report(int $questionId, ?int $userId, string $reason, string $note = ''): int
    {
        return $this->db->insert('question_reports', [
            'question_id' => $questionId,
            'user_id'     => $userId,
            'reason'      => $reason,
            'note'        => $note !== '' ? $note : null,
        ]);
    }

    // ------------------------------------------------------------------
    //  أدوات داخلية
    // ------------------------------------------------------------------

    /** @param array<string,mixed> $params */
    private function placeholders(string $prefix, int $count, array &$params, array $values): string
    {
        $names = [];
        foreach (array_values($values) as $index => $value) {
            $name = $prefix . $index;
            $names[] = ':' . $name;
            $params[$name] = is_int($value) ? $value : (string) $value;
        }
        return implode(', ', $names);
    }
}
