<?php
declare(strict_types=1);

namespace App\Services;

use App\Database;

/**
 * الإحصائيات والتحليلات: لوحة الإدارة + لوحة الطالب + نقاط القوة والضعف.
 */
final class StatisticsService
{
    private static function db(): Database
    {
        return Database::instance();
    }

    // ------------------------------------------------------------------
    //  لوحة الإدارة
    // ------------------------------------------------------------------

    /** @return array<string,int|float> */
    public static function overview(): array
    {
        $db = self::db();
        return [
            'users_total'        => (int) $db->value('SELECT COUNT(*) FROM `users`', [], 0),
            'users_students'     => (int) $db->value("SELECT COUNT(*) FROM `users` WHERE role = 'student'", [], 0),
            'users_new_month'    => (int) $db->value("SELECT COUNT(*) FROM `users` WHERE created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')", [], 0),
            'active_subscribers' => (int) $db->value("SELECT COUNT(DISTINCT user_id) FROM `subscriptions` WHERE status = 'active' AND (expires_at IS NULL OR expires_at > NOW())", [], 0),
            'pending_payments'   => (int) $db->value("SELECT COUNT(*) FROM `payments` WHERE status = 'pending'", [], 0),
            'revenue_total'      => (float) $db->value("SELECT COALESCE(SUM(amount), 0) FROM `payments` WHERE status = 'approved'", [], 0),
            'revenue_month'      => (float) $db->value("SELECT COALESCE(SUM(amount), 0) FROM `payments` WHERE status = 'approved' AND reviewed_at >= DATE_FORMAT(NOW(), '%Y-%m-01')", [], 0),
            'questions_total'    => (int) $db->value('SELECT COUNT(*) FROM `questions`', [], 0),
            'questions_review'   => (int) $db->value('SELECT COUNT(*) FROM `questions` WHERE needs_review = 1', [], 0),
            'templates_total'    => (int) $db->value('SELECT COUNT(*) FROM `exam_templates`', [], 0),
            'attempts_total'     => (int) $db->value("SELECT COUNT(*) FROM `exam_attempts` WHERE status IN ('completed','expired')", [], 0),
            'attempts_month'     => (int) $db->value("SELECT COUNT(*) FROM `exam_attempts` WHERE status IN ('completed','expired') AND started_at >= DATE_FORMAT(NOW(), '%Y-%m-01')", [], 0),
            'avg_score'          => round((float) $db->value("SELECT COALESCE(AVG(score), 0) FROM `exam_attempts` WHERE status IN ('completed','expired')", [], 0), 1),
            'pass_rate'          => round((float) $db->value("SELECT COALESCE(AVG(passed) * 100, 0) FROM `exam_attempts` WHERE status IN ('completed','expired')", [], 0), 1),
            'open_reports'       => (int) $db->value("SELECT COUNT(*) FROM `question_reports` WHERE status = 'open'", [], 0),
            'open_tickets'       => (int) $db->value("SELECT COUNT(*) FROM `support_tickets` WHERE status = 'open'", [], 0),
            'telegram_linked'    => (int) $db->value('SELECT COUNT(*) FROM `telegram_links` WHERE user_id IS NOT NULL', [], 0),
        ];
    }

    /**
     * تحديث إحصاءات الطالب بعد إجابة سؤال خارج سياق الاختبارات الرسمية
     * (التدريب السريع من الموقع أو عبر بوت تلجرام).
     *
     * يُحدّث: عدّادات السؤال (الإجابات/الصحيحة) وإحصاءات المجال.
     * لا يُنشئ محاولة اختبار، حتى لا تتلوث سجلّ المحاولات والإحصاءات الرسمية.
     */
    public static function recordAnswer(int $userId, int $questionId, bool $isCorrect): void
    {
        if ($userId <= 0 || $questionId <= 0) {
            return;
        }
        $db = self::db();
        $question = $db->one('SELECT id, track_id, category_id FROM `questions` WHERE id = :id', ['id' => $questionId]);
        if ($question === null) {
            return;
        }

        $db->transaction(function (Database $db) use ($userId, $questionId, $question, $isCorrect): void {
            $db->query(
                'UPDATE `questions`
                    SET times_answered = times_answered + 1, times_correct = times_correct + :c
                  WHERE id = :id',
                ['c' => $isCorrect ? 1 : 0, 'id' => $questionId]
            );

            $categoryId = (int) ($question['category_id'] ?? 0);
            if ($categoryId <= 0) {
                return; // بلا مجال: لا يمكن تحديث إحصاءات المجالات
            }

            $db->query(
                'INSERT INTO `user_category_stats`
                    (user_id, track_id, category_id, total_answered, correct_answers, wrong_answers, accuracy, last_attempt_at)
                 VALUES (:user_id, :track_id, :category_id, 1, :correct, :wrong, :accuracy, NOW())
                 ON DUPLICATE KEY UPDATE
                    -- تُحسب الدقة أولاً من القيم القديمة + القيم الواردة، لأن MySQL
                    -- تنفّذ التعيينات بالترتيب فتُصبح العدّادات محدَّثة في السطور التالية
                    accuracy        = ROUND(((correct_answers + VALUES(correct_answers))
                                             / NULLIF(total_answered + VALUES(total_answered), 0)) * 100, 2),
                    total_answered  = total_answered + VALUES(total_answered),
                    correct_answers = correct_answers + VALUES(correct_answers),
                    wrong_answers   = wrong_answers + VALUES(wrong_answers),
                    last_attempt_at = NOW()',
                [
                    'user_id'     => $userId,
                    'track_id'    => (int) $question['track_id'],
                    'category_id' => $categoryId,
                    'correct'     => $isCorrect ? 1 : 0,
                    'wrong'       => $isCorrect ? 0 : 1,
                    'accuracy'    => $isCorrect ? 100.0 : 0.0,
                ]
            );
        });
    }

    /** @return array{labels:array<int,string>,values:array<int,float>} */
    public static function registrationsByDay(int $days = 14): array
    {
        return self::seriesByDay(
            "SELECT DATE(created_at) AS day, COUNT(*) AS value FROM `users`
              WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL :days DAY) GROUP BY day ORDER BY day",
            $days
        );
    }

    /** @return array{labels:array<int,string>,values:array<int,float>} */
    public static function attemptsByDay(int $days = 14): array
    {
        return self::seriesByDay(
            "SELECT DATE(started_at) AS day, COUNT(*) AS value FROM `exam_attempts`
              WHERE started_at >= DATE_SUB(CURDATE(), INTERVAL :days DAY) GROUP BY day ORDER BY day",
            $days
        );
    }

    /** @return array{labels:array<int,string>,values:array<int,float>} */
    public static function revenueByDay(int $days = 14): array
    {
        return self::seriesByDay(
            "SELECT DATE(created_at) AS day, COALESCE(SUM(amount), 0) AS value FROM `payments`
              WHERE status = 'approved' AND created_at >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
              GROUP BY day ORDER BY day",
            $days
        );
    }

    /** @return array{labels:array<int,string>,values:array<int,float>} */
    private static function seriesByDay(string $sql, int $days): array
    {
        $rows = self::db()->all($sql, ['days' => max(1, min(365, $days))]);
        $map = [];
        foreach ($rows as $row) {
            $map[(string) $row['day']] = (float) $row['value'];
        }
        $labels = [];
        $values = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $day = date('Y-m-d', strtotime("-{$i} day"));
            $labels[] = date('m/d', strtotime($day));
            $values[] = $map[$day] ?? 0.0;
        }
        return ['labels' => $labels, 'values' => $values];
    }

    /** أداء كل مجال على مستوى المنصة (من إحصاءات الطلاب المُجمّعة) */
    /** @return array<int,array<string,mixed>> */
    public static function categoryPerformance(?int $trackId = null): array
    {
        $where = $trackId !== null && $trackId > 0 ? 'WHERE u.track_id = :track' : '';
        $params = $trackId !== null && $trackId > 0 ? ['track' => $trackId] : [];
        return self::db()->all(
            "SELECT c.id, c.name_ar, t.name_ar AS track_name,
                    SUM(u.total_answered) AS total_answered,
                    SUM(u.correct_answers) AS correct_answers,
                    ROUND((SUM(u.correct_answers) / NULLIF(SUM(u.total_answered), 0)) * 100, 1) AS accuracy
               FROM `user_category_stats` u
               JOIN `categories` c ON c.id = u.category_id
               JOIN `tracks` t ON t.id = u.track_id
               {$where}
              GROUP BY c.id, c.name_ar, t.name_ar
             HAVING total_answered > 0
              ORDER BY accuracy DESC",
            $params
        );
    }

    /** @return array<int,array<string,mixed>> */
    public static function hardestQuestions(int $limit = 8): array
    {
        return self::db()->all(
            "SELECT q.id, q.question_text, q.times_answered, q.times_correct,
                    ROUND(((q.times_answered - q.times_correct) / NULLIF(q.times_answered, 0)) * 100, 1) AS wrong_rate,
                    c.name_ar AS category_name
               FROM `questions` q
          LEFT JOIN `categories` c ON c.id = q.category_id
              WHERE q.times_answered >= 3
              ORDER BY wrong_rate DESC
              LIMIT " . max(1, min(50, $limit))
        );
    }

    /** @return array<int,array<string,mixed>> */
    public static function topStudents(int $limit = 8): array
    {
        return self::db()->all(
            "SELECT u.id, u.full_name, u.email,
                    COUNT(a.id) AS attempts,
                    ROUND(AVG(a.score), 1) AS avg_score,
                    SUM(a.passed) AS passed_count
               FROM `users` u
               JOIN `exam_attempts` a ON a.user_id = u.id AND a.status IN ('completed','expired')
              GROUP BY u.id, u.full_name, u.email
             HAVING attempts >= 1
              ORDER BY avg_score DESC, attempts DESC
              LIMIT " . max(1, min(50, $limit))
        );
    }

    /** @return array<int,array<string,mixed>> أكثر المجالات ضعفاً بين الطلاب */
    public static function weakestCategories(int $limit = 6): array
    {
        return self::db()->all(
            "SELECT c.name_ar, t.name_ar AS track_name,
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

    // ------------------------------------------------------------------
    //  لوحة الطالب
    // ------------------------------------------------------------------

    /** @return array<string,mixed> كل ما تحتاجه لوحة الطالب */
    public static function userDashboard(int $userId): array
    {
        $summary = ExamEngine::userSummary($userId);
        $breakdown = self::categoryBreakdown($userId);
        [$strengths, $weaknesses] = self::splitByLevel($breakdown);
        $recent = ExamEngine::recentAttempts($userId, 5);

        return [
            'summary'         => $summary,
            'subscription'    => SubscriptionService::current($userId),
            'breakdown'       => $breakdown,
            'strengths'       => $strengths,
            'weaknesses'      => $weaknesses,
            'recent'          => $recent,
            'recommendations' => self::recommendations($weaknesses, $strengths),
            'trend'           => self::scoreTrend($userId, 10),
            'notifications'   => Notifier::unreadCount($userId),
        ];
    }

    /** @return array<int,array{category_id:int,name:string,track_id:int,track_name:string,total:int,correct:int,accuracy:float,level:string}> */
    public static function categoryBreakdown(int $userId, ?int $trackId = null): array
    {
        $clauses = ['u.user_id = :user'];
        $params = ['user' => $userId];
        if ($trackId !== null && $trackId > 0) {
            $clauses[] = 'u.track_id = :track';
            $params['track'] = $trackId;
        }
        $rows = self::db()->all(
            'SELECT u.category_id, u.track_id, c.name_ar, t.name_ar AS track_name,
                    u.total_answered, u.correct_answers, u.accuracy, u.last_attempt_at
               FROM `user_category_stats` u
               JOIN `categories` c ON c.id = u.category_id
               JOIN `tracks` t ON t.id = u.track_id
              WHERE ' . implode(' AND ', $clauses) . '
              ORDER BY u.accuracy DESC, u.total_answered DESC',
            $params
        );
        $strength = (int) \settings('strength_threshold', 80);
        $weakness = (int) \settings('weakness_threshold', 60);
        $out = [];
        foreach ($rows as $row) {
            $accuracy = (float) $row['accuracy'];
            $out[] = [
                'category_id' => (int) $row['category_id'],
                'name'        => (string) $row['name_ar'],
                'track_id'    => (int) $row['track_id'],
                'track_name'  => (string) $row['track_name'],
                'total'       => (int) $row['total_answered'],
                'correct'     => (int) $row['correct_answers'],
                'accuracy'    => round($accuracy, 1),
                'last_attempt_at' => $row['last_attempt_at'],
                'level'       => $accuracy >= $strength ? 'strength' : ($accuracy < $weakness ? 'weakness' : 'average'),
            ];
        }
        return $out;
    }

    /**
     * @param array<int,array<string,mixed>> $breakdown
     * @return array{0:array<int,array<string,mixed>>,1:array<int,array<string,mixed>>}
     */
    public static function splitByLevel(array $breakdown): array
    {
        $strengths = array_values(array_filter($breakdown, static fn(array $row): bool => $row['level'] === 'strength' && $row['total'] >= 3));
        $weaknesses = array_values(array_filter($breakdown, static fn(array $row): bool => $row['level'] === 'weakness' && $row['total'] >= 3));
        return [$strengths, $weaknesses];
    }

    /** @return array{labels:array<int,string>,values:array<int,float>} */
    public static function scoreTrend(int $userId, int $limit = 10): array
    {
        $rows = self::db()->all(
            "SELECT score, DATE(finished_at) AS day FROM `exam_attempts`
              WHERE user_id = :id AND status IN ('completed','expired')
              ORDER BY id DESC LIMIT " . max(1, min(50, $limit)),
            ['id' => $userId]
        );
        $rows = array_reverse($rows);
        $labels = [];
        $values = [];
        foreach ($rows as $index => $row) {
            $labels[] = 'اختبار ' . ($index + 1);
            $values[] = round((float) $row['score'], 1);
        }
        return ['labels' => $labels, 'values' => $values];
    }

    /**
     * توصيات نصية مبنية على تحليل الأداء.
     * @param array<int,array<string,mixed>> $weaknesses
     * @param array<int,array<string,mixed>> $strengths
     * @return array<int,string>
     */
    public static function recommendations(array $weaknesses, array $strengths): array
    {
        $out = [];
        if ($weaknesses !== []) {
            $names = array_slice(array_column($weaknesses, 'name'), 0, 3);
            $out[] = 'ننصحك بمراجعة قسم ' . implode(' و ', $names) . ' قبل إعادة الاختبار، والتركيز على شرح الإجابات الخاطئة.';
            $out[] = 'ابدأ بتدريب مركّز على ' . $names[0] . ' (20 سؤالاً) ثم أعد الاختبار التجريبي لقياس التحسن.';
        }
        if ($strengths !== []) {
            $out[] = 'أداؤك ممتاز في ' . implode(' و ', array_slice(array_column($strengths, 'name'), 0, 3)) . ' — حافظ على هذا المستوى بتدريبات سريعة دورية.';
        }
        if ($weaknesses === [] && $strengths === []) {
            $out[] = 'قم بأداء اختبار تجريبي كامل ليتمكن النظام من تحليل مستواك وتحديد نقاط القوة والضعف.';
        }
        $out[] = 'خصّص جلسة يومية 30 دقيقة من الأسئلة العشوائية لرفع نسبة الاستعداد قبل الاختبار الرسمي.';
        return $out;
    }

    /** نسبة استعداد تقديرية (0-100) بناءً على الأداء العام والتغطية */
    public static function readinessScore(int $userId): array
    {
        $summary = ExamEngine::userSummary($userId);
        $breakdown = self::categoryBreakdown($userId);
        $coverage = 0.0;
        if ($breakdown !== []) {
            $coverage = min(100, (array_sum(array_column($breakdown, 'total')) / 200) * 100); // 200 سؤال = تغطية جيدة
        }
        $accuracy = (float) $summary['accuracy'];
        $avg = (float) $summary['avg_score'];
        $score = round(($accuracy * 0.45) + ($avg * 0.4) + ($coverage * 0.15), 1);
        return [
            'score'    => min(100, max(0, $score)),
            'accuracy' => $accuracy,
            'average'  => $avg,
            'coverage' => round($coverage, 1),
            'attempts' => (int) $summary['attempts'],
        ];
    }
}
