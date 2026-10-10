<?php
/** قائمة بلاغات الأسئلة: مراجعة، تصحيح، أو رفض */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

use App\Repositories\QuestionRepository;
use App\View;

$admin = auth()->requireAdmin();
$adminId = (int) $admin['id'];
$repo = new QuestionRepository();

if (is_post()) {
    $reportId = (int) post('report_id', 0);
    $action = (string) post('action', '');
    $report = db()->one('SELECT * FROM `question_reports` WHERE id = :id', ['id' => $reportId]);

    if ($report === null) {
        flash('danger', 'البلاغ غير موجود.');
        redirect('admin/reports-queue');
    }

    switch ($action) {
        case 'resolve':
            $questionId = (int) $report['question_id'];
            db()->update('question_reports', [
                'status'      => 'resolved',
                'resolved_by' => $adminId,
                'resolved_at' => date('Y-m-d H:i:s'),
            ], 'id = :id', ['id' => $reportId]);

            // إجراء اختياري على السؤال نفسه
            $questionAction = (string) post('question_action', 'none');
            if ($questionAction === 'needs_review') {
                $repo->setNeedsReview($questionId, true, 'بلاغ الطالب: ' . (string) $report['reason']);
            } elseif ($questionAction === 'fix') {
                $correct = (string) post('correct_answer', '');
                if (in_array($correct, ['a', 'b', 'c', 'd'], true)) {
                    $repo->update($questionId, ['correct_answer' => $correct, 'needs_review' => 0]);
                }
                if (trim((string) post('explanation', '')) !== '') {
                    $repo->update($questionId, ['explanation' => trim((string) post('explanation'))]);
                }
            } elseif ($questionAction === 'deactivate') {
                $repo->bulkSet([$questionId], ['active' => 0, 'updated_by' => $adminId]);
            }
            audit('admin.report_resolved', 'question_report', $reportId, ['question' => $questionId, 'action' => $questionAction]);
            flash('success', 'تم إغلاق البلاغ وتنفيذ الإجراء المطلوب (إن وُجد).');
            break;

        case 'reject':
            db()->update('question_reports', [
                'status'      => 'rejected',
                'resolved_by' => $adminId,
                'resolved_at' => date('Y-m-d H:i:s'),
            ], 'id = :id', ['id' => $reportId]);
            audit('admin.report_rejected', 'question_report', $reportId);
            flash('success', 'تم رفض البلاغ.');
            break;
    }
    redirect('admin/reports-queue');
}

$status = (string) query('status', 'open');
$reason = (string) query('reason', '');
$page = max(1, (int) query('page', 1));

$clauses = [];
$params = [];
if (in_array($status, ['open', 'resolved', 'rejected'], true)) {
    $clauses[] = 'r.status = :status';
    $params['status'] = $status;
}
if ($reason !== '') {
    $clauses[] = 'r.reason = :reason';
    $params['reason'] = $reason;
}

$paginated = db()->paginate(
    "SELECT r.*, q.question_text, q.option_a, q.option_b, q.option_c, q.option_d, q.correct_answer,
            q.explanation, q.active, q.needs_review, q.times_answered, q.times_correct,
            c.name_ar AS category_name, t.name_ar AS track_name,
            u.full_name AS reporter_name, u.email AS reporter_email,
            rb.full_name AS resolved_by_name
       FROM `question_reports` r
       JOIN `questions` q ON q.id = r.question_id
  LEFT JOIN `categories` c ON c.id = q.category_id
  LEFT JOIN `tracks` t ON t.id = q.track_id
  LEFT JOIN `users` u ON u.id = r.user_id
  LEFT JOIN `users` rb ON rb.id = r.resolved_by"
    . ($clauses === [] ? '' : ' WHERE ' . implode(' AND ', $clauses)) .
    " ORDER BY (r.status = 'open') DESC, r.id DESC",
    $params,
    15,
    $page
);

View::render('admin/reports-queue', [
    'title'      => 'بلاغات الأسئلة',
    'pageSub'    => 'راجع ملاحظات الطلاب على جودة الأسئلة',
    'activeMenu' => 'admin/reports-queue',
    'paginated'  => $paginated,
    'filters'    => ['status' => $status, 'reason' => $reason],
    'counters'   => [
        'open'     => (int) db()->value("SELECT COUNT(*) FROM `question_reports` WHERE status = 'open'", [], 0),
        'resolved' => (int) db()->value("SELECT COUNT(*) FROM `question_reports` WHERE status = 'resolved'", [], 0),
        'rejected' => (int) db()->value("SELECT COUNT(*) FROM `question_reports` WHERE status = 'rejected'", [], 0),
    ],
], 'layouts/app');
