<?php
declare(strict_types=1);

namespace App\Services;

use App\Database;
use App\Logger;
use App\Repositories\QuestionRepository;

/**
 * محرك الاختبارات: بناء الاختبار، عرض الأسئلة، حفظ الإجابات، التصحيح، والتحليل.
 * كل الحسابات الحساسة (التصحيح والمؤقت) تتم على الخادم - لا يُعتمد على المتصفح.
 */
final class ExamEngine
{
    public const MODES = [
        'mock'     => 'اختبار تجريبي شامل',
        'category' => 'تدريب حسب المجال',
        'random'   => 'أسئلة عشوائية',
        'practice' => 'تدريب حر (إظهار الإجابة فوراً)',
        'daily'    => 'تحدي يومي',
    ];

    private static function db(): Database
    {
        return Database::instance();
    }

    // ------------------------------------------------------------------
    //  قوالب الاختبارات
    // ------------------------------------------------------------------

    /** @return array<int,array<string,mixed>> */
    public static function templates(?int $trackId = null, bool $onlyActive = true): array
    {
        $clauses = [];
        $params = [];
        if ($trackId !== null && $trackId > 0) {
            $clauses[] = 'e.track_id = :track_id';
            $params['track_id'] = $trackId;
        }
        if ($onlyActive) {
            $clauses[] = 'e.is_active = 1';
        }
        $where = $clauses === [] ? '1' : implode(' AND ', $clauses);
        return self::db()->all(
            "SELECT e.*, t.name_ar AS track_name, t.code AS track_code,
                    (SELECT COUNT(*) FROM `exam_attempts` a WHERE a.exam_template_id = e.id) AS attempts_count
               FROM `exam_templates` e
               JOIN `tracks` t ON t.id = e.track_id
              WHERE {$where}
              ORDER BY e.sort_order, e.id",
            $params
        );
    }

    /** @return array<string,mixed>|null */
    public static function template(int $id): ?array
    {
        return self::db()->one(
            'SELECT e.*, t.name_ar AS track_name FROM `exam_templates` e JOIN `tracks` t ON t.id = e.track_id WHERE e.id = :id',
            ['id' => $id]
        );
    }

    // ------------------------------------------------------------------
    //  بناء محاولة اختبار جديدة
    // ------------------------------------------------------------------

    /**
     * @param array<string,mixed> $options
     *   mode, track_id, template_id, category_ids[], difficulty, count,
     *   duration_minutes, randomize_questions, randomize_options, show_explanation
     * @return array{ok:bool,message:string,attempt_id?:int,requested?:int,available?:int}
     */
    public static function build(array $options, int $userId): array
    {
        $repo = new QuestionRepository();
        $template = null;

        if (!empty($options['template_id'])) {
            $template = self::template((int) $options['template_id']);
            if ($template === null || (int) $template['is_active'] !== 1) {
                return ['ok' => false, 'message' => 'الاختبار المطلوب غير متاح.'];
            }
        }

        $mode = (string) ($template['mode'] ?? $options['mode'] ?? 'mock');
        $trackId = (int) ($template['track_id'] ?? $options['track_id'] ?? 0);
        $difficulty = (string) ($template['difficulty'] ?? $options['difficulty'] ?? 'any');
        $count = (int) ($options['count'] ?? $template['question_count'] ?? 20);
        $duration = (int) ($options['duration_minutes'] ?? $template['duration_minutes'] ?? 0);
        $randomizeOptions = (int) ($options['randomize_options'] ?? $template['randomize_options'] ?? 0) === 1;
        $showExplanation = (int) ($options['show_explanation'] ?? $template['show_explanation'] ?? 1) === 1;
        $passPercentage = (int) ($template['pass_percentage'] ?? \settings('exam_default_pass', 60));

        $categories = $options['category_ids'] ?? [];
        if ($categories === [] && !empty($template['category_ids'])) {
            $decoded = json_decode((string) $template['category_ids'], true);
            $categories = is_array($decoded) ? $decoded : [];
        }
        $categories = array_values(array_filter(array_map('intval', (array) $categories)));

        $track = self::db()->one('SELECT * FROM `tracks` WHERE id = :id AND is_active = 1', ['id' => $trackId]);
        if ($track === null) {
            return ['ok' => false, 'message' => 'يرجى اختيار المسار الصحيح.'];
        }

        $filters = ['track_id' => $trackId, 'difficulty' => $difficulty];
        if ($categories !== []) {
            $filters['category_ids'] = $categories;
        }

        $count = max(1, min(150, $count));
        $ids = $repo->randomIds($count, $filters);
        if ($ids === []) {
            return ['ok' => false, 'message' => 'لا توجد أسئلة كافية في هذا التصنيف حالياً. جرّب مجالاً آخر أو تواصل مع الإدارة.'];
        }

        $attemptId = self::db()->transaction(function (Database $db) use ($userId, $template, $trackId, $mode, $difficulty, $ids, $duration, $randomizeOptions, $showExplanation, $categories, $options): int {
            $title = (string) ($options['title'] ?? $template['title'] ?? self::MODES[$mode] ?? 'اختبار');
            $attemptId = $db->insert('exam_attempts', [
                'user_id'           => $userId,
                'exam_template_id'  => $template['id'] ?? null,
                'track_id'          => $trackId,
                'mode'              => $mode,
                'title'             => $title,
                'category_ids'      => $categories === [] ? null : json_encode($categories),
                'difficulty'        => $difficulty,
                'total_questions'   => count($ids),
                'duration_minutes'  => $duration,
                'randomize_options' => $randomizeOptions ? 1 : 0,
                'status'            => 'in_progress',
                'started_at'        => date('Y-m-d H:i:s'),
                'expires_at'        => $duration > 0 ? date('Y-m-d H:i:s', time() + $duration * 60) : null,
                'ip'                => \App\Security::ip(),
            ]);

            $order = 0;
            foreach ($ids as $questionId) {
                $order++;
                $db->insert('exam_attempt_questions', [
                    'attempt_id'     => $attemptId,
                    'question_id'    => $questionId,
                    'question_order' => $order,
                    'option_order'   => json_encode(self::shuffledLetters($questionId, $randomizeOptions)),
                ]);
            }
            return $attemptId;
        });

        \audit('exam.started', 'exam_attempt', $attemptId, ['mode' => $mode, 'questions' => count($ids), 'template' => $template['id'] ?? null]);

        return [
            'ok'        => true,
            'message'   => 'تم تجهيز الاختبار.',
            'attempt_id' => $attemptId,
            'requested' => $count,
            'available' => count($ids),
        ];
    }

    /** @return array<int,string> */
    private static function shuffledLetters(int $questionId, bool $shuffle): array
    {
        $letters = ['a', 'b', 'c', 'd'];
        if (!$shuffle) {
            return $letters;
        }
        $question = self::db()->one('SELECT option_a, option_b, option_c, option_d FROM `questions` WHERE id = :id', ['id' => $questionId]);
        $available = [];
        foreach ($letters as $letter) {
            if (!empty($question['option_' . $letter])) {
                $available[] = $letter;
            }
        }
        if (count($available) < 2) {
            return $letters;
        }
        shuffle($available);
        return $available;
    }

    // ------------------------------------------------------------------
    //  قراءة المحاولة والأسئلة
    // ------------------------------------------------------------------

    /** @return array<string,mixed>|null */
    public static function attempt(int $attemptId, ?int $userId = null): ?array
    {
        $sql = 'SELECT a.*, t.name_ar AS track_name, t.code AS track_code, e.pass_percentage, e.show_explanation, e.require_subscription
                  FROM `exam_attempts` a
                  JOIN `tracks` t ON t.id = a.track_id
             LEFT JOIN `exam_templates` e ON e.id = a.exam_template_id
                 WHERE a.id = :id';
        $params = ['id' => $attemptId];
        if ($userId !== null) {
            $sql .= ' AND a.user_id = :user_id';
            $params['user_id'] = $userId;
        }
        return self::db()->one($sql . ' LIMIT 1', $params);
    }

    public static function isExpired(array $attempt): bool
    {
        return $attempt['status'] === 'in_progress'
            && !empty($attempt['expires_at'])
            && strtotime((string) $attempt['expires_at']) <= time();
    }

    public static function remainingSeconds(array $attempt): int
    {
        if (empty($attempt['expires_at'])) {
            return 0;
        }
        return max(0, strtotime((string) $attempt['expires_at']) - time());
    }

    /**
     * أسئلة المحاولة مع ترتيب الاختيارات.
     * @return array<int,array<string,mixed>>
     */
    public static function questions(int $attemptId, bool $forReview = false): array
    {
        $rows = self::db()->all(
            "SELECT aq.id AS attempt_question_id, aq.question_id, aq.question_order, aq.option_order,
                    aq.selected_answer, aq.is_correct, aq.is_flagged, aq.time_spent_seconds,
                    q.question_text, q.option_a, q.option_b, q.option_c, q.option_d,
                    q.correct_answer, q.explanation, q.difficulty, q.question_type,
                    c.name_ar AS category_name, c.id AS category_id
               FROM `exam_attempt_questions` aq
               JOIN `questions` q ON q.id = aq.question_id
          LEFT JOIN `categories` c ON c.id = q.category_id
              WHERE aq.attempt_id = :id
              ORDER BY aq.question_order ASC",
            ['id' => $attemptId]
        );

        $out = [];
        foreach ($rows as $row) {
            $order = json_decode((string) $row['option_order'], true);
            if (!is_array($order) || $order === []) {
                $order = ['a', 'b', 'c', 'd'];
            }
            $options = [];
            foreach ($order as $letter) {
                $letter = (string) $letter;
                $text = $row['option_' . $letter] ?? null;
                if ($text !== null && $text !== '') {
                    $options[$letter] = (string) $text;
                }
            }
            $question = [
                'attempt_question_id' => (int) $row['attempt_question_id'],
                'id'                  => (int) $row['question_id'],
                'number'              => (int) $row['question_order'],
                'text'                => (string) $row['question_text'],
                'options'             => $options,
                'selected'            => $row['selected_answer'],
                'is_correct'          => $row['is_correct'] === null ? null : (int) $row['is_correct'] === 1,
                'is_flagged'          => (int) $row['is_flagged'] === 1,
                'difficulty'          => (string) $row['difficulty'],
                'category'            => (string) ($row['category_name'] ?? 'غير مصنّف'),
                'category_id'         => (int) ($row['category_id'] ?? 0),
                'time_spent_seconds'  => (int) $row['time_spent_seconds'],
            ];
            if ($forReview) {
                $question['correct_answer'] = $row['correct_answer'];
                $question['explanation'] = (string) ($row['explanation'] ?? '');
            }
            $out[] = $question;
        }
        return $out;
    }

    /** @return array{answered:int,unanswered:int,flagged:int,total:int,percentage:float} */
    public static function progress(int $attemptId): array
    {
        $row = self::db()->one(
            'SELECT COUNT(*) AS total,
                    SUM(CASE WHEN selected_answer IS NOT NULL THEN 1 ELSE 0 END) AS answered,
                    SUM(CASE WHEN is_flagged = 1 THEN 1 ELSE 0 END) AS flagged
               FROM `exam_attempt_questions` WHERE attempt_id = :id',
            ['id' => $attemptId]
        );
        $total = (int) ($row['total'] ?? 0);
        $answered = (int) ($row['answered'] ?? 0);
        return [
            'total'      => $total,
            'answered'   => $answered,
            'unanswered' => max(0, $total - $answered),
            'flagged'    => (int) ($row['flagged'] ?? 0),
            'percentage' => $total > 0 ? round(($answered / $total) * 100, 1) : 0.0,
        ];
    }

    // ------------------------------------------------------------------
    //  حفظ الإجابات
    // ------------------------------------------------------------------

    /** @return array{ok:bool,message:string,expired?:bool,progress?:array<string,mixed>} */
    public static function saveAnswer(int $attemptId, int $userId, int $questionId, ?string $answer, ?bool $flagged = null, int $timeSpent = 0): array
    {
        $attempt = self::attempt($attemptId, $userId);
        if ($attempt === null) {
            return ['ok' => false, 'message' => 'المحاولة غير موجودة.'];
        }
        if ($attempt['status'] !== 'in_progress') {
            return ['ok' => false, 'message' => 'انتهت هذه المحاولة بالفعل.'];
        }
        if (self::isExpired($attempt)) {
            self::submit($attemptId, $userId, true);
            return ['ok' => false, 'message' => 'انتهى وقت الاختبار وتم إرساله تلقائياً.', 'expired' => true];
        }

        if ($answer !== null && !in_array($answer, ['a', 'b', 'c', 'd'], true)) {
            return ['ok' => false, 'message' => 'إجابة غير صالحة.'];
        }

        $row = self::db()->one(
            'SELECT aq.id, q.correct_answer
               FROM `exam_attempt_questions` aq
               JOIN `questions` q ON q.id = aq.question_id
              WHERE aq.attempt_id = :attempt AND aq.question_id = :question LIMIT 1',
            ['attempt' => $attemptId, 'question' => $questionId]
        );
        if ($row === null) {
            return ['ok' => false, 'message' => 'السؤال لا ينتمي إلى هذه المحاولة.'];
        }

        $update = [
            'selected_answer' => $answer,
            'is_correct'      => $answer === null ? null : (int) ($answer === (string) $row['correct_answer']),
            'answered_at'     => $answer === null ? null : date('Y-m-d H:i:s'),
        ];
        if ($flagged !== null) {
            $update['is_flagged'] = $flagged ? 1 : 0;
        }
        if ($timeSpent > 0) {
            $update['time_spent_seconds'] = min(3600, $timeSpent);
        }
        self::db()->update('exam_attempt_questions', $update, 'id = :id', ['id' => (int) $row['id']]);

        return ['ok' => true, 'message' => 'تم حفظ الإجابة.', 'progress' => self::progress($attemptId)];
    }

    public static function toggleFlag(int $attemptId, int $userId, int $questionId): array
    {
        $attempt = self::attempt($attemptId, $userId);
        if ($attempt === null || $attempt['status'] !== 'in_progress') {
            return ['ok' => false, 'message' => 'المحاولة غير قابلة للتعديل.'];
        }
        self::db()->query(
            'UPDATE `exam_attempt_questions` SET is_flagged = 1 - is_flagged WHERE attempt_id = :a AND question_id = :q',
            ['a' => $attemptId, 'q' => $questionId]
        );
        $flagged = (int) self::db()->value(
            'SELECT is_flagged FROM `exam_attempt_questions` WHERE attempt_id = :a AND question_id = :q',
            ['a' => $attemptId, 'q' => $questionId],
            0
        ) === 1;
        return ['ok' => true, 'flagged' => $flagged];
    }

    // ------------------------------------------------------------------
    //  التصحيح وإنهاء الاختبار
    // ------------------------------------------------------------------

    /** @return array{ok:bool,message:string,attempt_id?:int,score?:float} */
    public static function submit(int $attemptId, int $userId, bool $auto = false): array
    {
        $attempt = self::attempt($attemptId, $userId);
        if ($attempt === null) {
            return ['ok' => false, 'message' => 'المحاولة غير موجودة.'];
        }
        if ($attempt['status'] !== 'in_progress') {
            return ['ok' => true, 'message' => 'تم إرسال المحاولة مسبقاً.', 'attempt_id' => $attemptId, 'score' => (float) $attempt['score']];
        }

        $rows = self::db()->all(
            'SELECT aq.id, aq.question_id, aq.selected_answer, q.correct_answer, q.category_id
               FROM `exam_attempt_questions` aq
               JOIN `questions` q ON q.id = aq.question_id
              WHERE aq.attempt_id = :id',
            ['id' => $attemptId]
        );

        $correct = 0;
        $wrong = 0;
        $unanswered = 0;
        $categoryTotals = [];
        $questionStats = [];

        foreach ($rows as $row) {
            $selected = $row['selected_answer'];
            $isCorrect = $selected !== null && $selected === (string) $row['correct_answer'];
            if ($selected === null) {
                $unanswered++;
            } elseif ($isCorrect) {
                $correct++;
            } else {
                $wrong++;
            }
            $categoryId = (int) ($row['category_id'] ?? 0);
            if ($categoryId > 0) {
                $categoryTotals[$categoryId] ??= ['total' => 0, 'correct' => 0];
                $categoryTotals[$categoryId]['total']++;
                $categoryTotals[$categoryId]['correct'] += $isCorrect ? 1 : 0;
            }
            if ($selected !== null) {
                $questionStats[(int) $row['question_id']] = $isCorrect;
            }
        }

        $total = count($rows);
        $score = $total > 0 ? round(($correct / $total) * 100, 2) : 0.0;
        $passPercentage = (int) ($attempt['pass_percentage'] ?? \settings('exam_default_pass', 60));
        $timeSpent = min(
            time() - strtotime((string) $attempt['started_at']),
            !empty($attempt['duration_minutes']) ? (int) $attempt['duration_minutes'] * 60 + 5 : 86400
        );

        self::db()->transaction(function (Database $db) use ($attemptId, $attempt, $correct, $wrong, $unanswered, $score, $passPercentage, $timeSpent, $auto, $categoryTotals, $questionStats): void {
            $db->update('exam_attempts', [
                'status'            => self::isExpired($attempt) ? 'expired' : 'completed',
                'finished_at'       => date('Y-m-d H:i:s'),
                'time_spent_seconds' => max(0, $timeSpent),
                'correct_count'     => $correct,
                'wrong_count'       => $wrong,
                'unanswered_count'  => $unanswered,
                'score'             => $score,
                'passed'            => $score >= $passPercentage ? 1 : 0,
            ], 'id = :id', ['id' => $attemptId]);

            // تحديث إحصاءات الأسئلة
            foreach ($questionStats as $questionId => $isCorrect) {
                $db->query(
                    'UPDATE `questions` SET times_answered = times_answered + 1, times_correct = times_correct + :c WHERE id = :id',
                    ['c' => $isCorrect ? 1 : 0, 'id' => (int) $questionId]
                );
            }

            // تحديث إحصاءات الطالب حسب المجال (نقاط القوة/الضعف)
            foreach ($categoryTotals as $categoryId => $totals) {
                $db->query(
                    'INSERT INTO `user_category_stats` (user_id, track_id, category_id, total_answered, correct_answers, wrong_answers, accuracy, last_attempt_at)
                     VALUES (:user_id, :track_id, :category_id, :total, :correct, :wrong, :accuracy, NOW())
                     ON DUPLICATE KEY UPDATE
                        -- الدقة أولاً حتى لا تُحتسب القيم الواردة مرتين بعد تحديث العدّادات
                        accuracy = ROUND(((correct_answers + VALUES(correct_answers)) / NULLIF(total_answered + VALUES(total_answered), 0)) * 100, 2),
                        total_answered = total_answered + VALUES(total_answered),
                        correct_answers = correct_answers + VALUES(correct_answers),
                        wrong_answers = wrong_answers + VALUES(wrong_answers),
                        last_attempt_at = NOW()',
                    [
                        'user_id'     => (int) $attempt['user_id'],
                        'track_id'    => (int) $attempt['track_id'],
                        'category_id' => (int) $categoryId,
                        'total'       => (int) $totals['total'],
                        'correct'     => (int) $totals['correct'],
                        'wrong'       => (int) $totals['total'] - (int) $totals['correct'],
                        'accuracy'    => $totals['total'] > 0 ? round(($totals['correct'] / $totals['total']) * 100, 2) : 0,
                    ]
                );
            }
        });

        \audit('exam.submitted', 'exam_attempt', $attemptId, [
            'score' => $score,
            'correct' => $correct,
            'wrong' => $wrong,
            'unanswered' => $unanswered,
            'auto' => $auto,
        ]);

        if (!$auto) {
            self::checkMilestone((int) $attempt['user_id'], $score);
        }

        return ['ok' => true, 'message' => $auto ? 'تم إرسال الاختبار تلقائياً.' : 'تم إرسال الاختبار.', 'attempt_id' => $attemptId, 'score' => $score];
    }

    private static function checkMilestone(int $userId, float $score): void
    {
        if ($score < 90) {
            return;
        }
        try {
            $best = (float) self::db()->value(
                "SELECT MAX(score) FROM `exam_attempts` WHERE user_id = :id AND status IN ('completed','expired') AND id <> (SELECT MAX(id) FROM `exam_attempts` WHERE user_id = :id2)",
                ['id' => $userId, 'id2' => $userId],
                0
            );
            if ($score > $best) {
                Notifier::toUser($userId, 'نتيجة مميزة 🎉', 'حصلت على ' . $score . '% في آخر اختبار. استمر بنفس المستوى!', \url('student/statistics'), 'exam');
            }
        } catch (\Throwable $e) {
            Logger::warning('تعذّر إرسال إشعار الإنجاز: ' . $e->getMessage());
        }
    }

    /** @return array<string,mixed> */
    public static function result(int $attemptId, int $userId): array
    {
        $attempt = self::attempt($attemptId, $userId);
        if ($attempt === null) {
            return [];
        }
        $passPercentage = (int) ($attempt['pass_percentage'] ?? \settings('exam_default_pass', 60));
        $attempt['score'] = (float) $attempt['score'];
        $attempt['pass_percentage'] = $passPercentage;
        $attempt['passed'] = $attempt['score'] >= $passPercentage;
        $attempt['time_spent_human'] = \duration_ar((int) $attempt['time_spent_seconds']);
        return $attempt;
    }

    /**
     * تحليل أداء المحاولة حسب المجال.
     * @return array<int,array{category_id:int,name:string,total:int,correct:int,accuracy:float,level:string}>
     */
    public static function analysis(int $attemptId): array
    {
        $rows = self::db()->all(
            "SELECT COALESCE(c.id, 0) AS category_id, COALESCE(c.name_ar, 'غير مصنّف') AS name,
                    COUNT(*) AS total,
                    SUM(CASE WHEN aq.is_correct = 1 THEN 1 ELSE 0 END) AS correct
               FROM `exam_attempt_questions` aq
               JOIN `questions` q ON q.id = aq.question_id
          LEFT JOIN `categories` c ON c.id = q.category_id
              WHERE aq.attempt_id = :id
              GROUP BY category_id, name
              ORDER BY total DESC",
            ['id' => $attemptId]
        );
        $strength = (int) \settings('strength_threshold', 80);
        $weakness = (int) \settings('weakness_threshold', 60);
        $out = [];
        foreach ($rows as $row) {
            $total = (int) $row['total'];
            $correct = (int) $row['correct'];
            $accuracy = $total > 0 ? round(($correct / $total) * 100, 1) : 0.0;
            $out[] = [
                'category_id' => (int) $row['category_id'],
                'name'        => (string) $row['name'],
                'total'       => $total,
                'correct'     => $correct,
                'accuracy'    => $accuracy,
                'level'       => $accuracy >= $strength ? 'strength' : ($accuracy < $weakness ? 'weakness' : 'average'),
            ];
        }
        return $out;
    }

    // ------------------------------------------------------------------
    //  مهام دورية
    // ------------------------------------------------------------------

    /** إنهاء الاختبارات التي انتهى وقتها (Cron) */
    public static function expireOverdue(int $limit = 100): int
    {
        $rows = self::db()->all(
            "SELECT id, user_id FROM `exam_attempts`
              WHERE status = 'in_progress' AND expires_at IS NOT NULL AND expires_at < NOW()
              ORDER BY id ASC LIMIT " . max(1, min(500, $limit))
        );
        $count = 0;
        foreach ($rows as $row) {
            $result = self::submit((int) $row['id'], (int) $row['user_id'], true);
            if ($result['ok']) {
                $count++;
            }
        }
        return $count;
    }

    /** @return array<int,array<string,mixed>> آخر محاولات الطالب */
    public static function recentAttempts(int $userId, int $limit = 5): array
    {
        return self::db()->all(
            "SELECT a.*, t.name_ar AS track_name
               FROM `exam_attempts` a
               JOIN `tracks` t ON t.id = a.track_id
              WHERE a.user_id = :id
              ORDER BY a.id DESC LIMIT " . max(1, min(50, $limit)),
            ['id' => $userId]
        );
    }

    /** @return array<string,float|int> ملخص أداء الطالب */
    public static function userSummary(int $userId): array
    {
        $row = self::db()->one(
            "SELECT COUNT(*) AS attempts,
                    COALESCE(AVG(score), 0) AS avg_score,
                    COALESCE(MAX(score), 0) AS best_score,
                    COALESCE(SUM(correct_count), 0) AS correct,
                    COALESCE(SUM(wrong_count), 0) AS wrong,
                    COALESCE(SUM(unanswered_count), 0) AS unanswered
               FROM `exam_attempts`
              WHERE user_id = :id AND status IN ('completed','expired')",
            ['id' => $userId]
        ) ?? [];
        $answered = (int) ($row['correct'] ?? 0) + (int) ($row['wrong'] ?? 0);
        return [
            'attempts'   => (int) ($row['attempts'] ?? 0),
            'avg_score'  => round((float) ($row['avg_score'] ?? 0), 1),
            'best_score' => round((float) ($row['best_score'] ?? 0), 1),
            'correct'    => (int) ($row['correct'] ?? 0),
            'wrong'      => (int) ($row['wrong'] ?? 0),
            'unanswered' => (int) ($row['unanswered'] ?? 0),
            'accuracy'   => $answered > 0 ? round(((int) $row['correct'] / $answered) * 100, 1) : 0.0,
        ];
    }
}
