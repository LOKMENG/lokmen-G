<?php
/** إدارة قوالب الاختبارات + سجل المحاولات */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

use App\Repositories\QuestionRepository;
use App\View;

$admin = auth()->requireAdmin();
$adminId = (int) $admin['id'];

if (is_post()) {
    $action = (string) post('action', '');
    $ok = true;
    $message = '';

    switch ($action) {
        case 'save_template':
            $id = (int) post('id', 0);
            $title = trim((string) post('title'));
            $trackId = (int) post('track_id', 0);
            if ($title === '' || $trackId <= 0) {
                $ok = false;
                $message = 'عنوان القالب والمسار مطلوبان.';
                break;
            }
            $categoryIds = array_values(array_filter(array_map('intval', (array) post('category_ids', []))));
            $data = [
                'title'               => $title,
                'description'         => trim((string) post('description', '')) ?: null,
                'track_id'            => $trackId,
                'mode'                => (string) post('mode', 'mock'),
                'category_ids'        => $categoryIds === [] ? null : json_encode($categoryIds, JSON_UNESCAPED_UNICODE),
                'difficulty'          => (string) post('difficulty', 'any'),
                'question_count'      => max(1, (int) post('question_count', 50)),
                'duration_minutes'    => max(0, (int) post('duration_minutes', 60)),
                'pass_percentage'     => max(1, min(100, (int) post('pass_percentage', 60))),
                'randomize_questions' => (int) post('randomize_questions', 0) === 1 ? 1 : 0,
                'randomize_options'   => (int) post('randomize_options', 0) === 1 ? 1 : 0,
                'show_explanation'    => (int) post('show_explanation', 0) === 1 ? 1 : 0,
                'require_subscription'=> (int) post('require_subscription', 0) === 1 ? 1 : 0,
                'max_attempts'        => max(0, (int) post('max_attempts', 0)),
                'is_active'           => (int) post('is_active', 0) === 1 ? 1 : 0,
                'sort_order'          => (int) post('sort_order', 0),
            ];
            if ($id > 0) {
                db()->update('exam_templates', $data, 'id = :id', ['id' => $id]);
                audit('admin.template_updated', 'exam_template', $id);
            } else {
                $data['created_by'] = $adminId;
                $id = db()->insert('exam_templates', $data);
                audit('admin.template_created', 'exam_template', $id);
            }
            $message = 'تم حفظ قالب الاختبار.';
            break;

        case 'delete_template':
            $id = (int) post('id', 0);
            $attempts = (int) db()->value('SELECT COUNT(*) FROM `exam_attempts` WHERE exam_template_id = :id', ['id' => $id], 0);
            if ($attempts > 0) {
                db()->update('exam_templates', ['is_active' => 0], 'id = :id', ['id' => $id]);
                $ok = true;
                $message = 'القالب مرتبط بـ ' . $attempts . ' محاولة — تم تعطيله بدلاً من حذفه للحفاظ على السجل.';
            } else {
                db()->delete('exam_templates', 'id = :id', ['id' => $id]);
                audit('admin.template_deleted', 'exam_template', $id);
                $message = 'تم حذف القالب.';
            }
            break;

        case 'delete_attempt':
            $id = (int) post('id', 0);
            $attempt = db()->one('SELECT * FROM `exam_attempts` WHERE id = :id', ['id' => $id]);
            if ($attempt === null) {
                $ok = false;
                $message = 'المحاولة غير موجودة.';
            } else {
                db()->delete('exam_attempts', 'id = :id', ['id' => $id]);
                audit('admin.attempt_deleted', 'exam_attempt', $id, ['user_id' => (int) $attempt['user_id']]);
                $message = 'تم حذف المحاولة.';
            }
            break;

        case 'expire_stale':
            $count = \App\Services\ExamEngine::expireOverdue();
            $message = 'تم إنهاء ' . $count . ' محاولة منتهية الصلاحية.';
            break;

        default:
            $ok = false;
            $message = 'إجراء غير معروف.';
    }

    flash($ok ? 'success' : 'danger', $message);
    redirect('admin/exams', 302, ['tab' => (string) post('tab', 'templates')]);
}

$tab = (string) query('tab', 'templates');
if (!in_array($tab, ['templates', 'attempts'], true)) {
    $tab = 'templates';
}
$repo = new QuestionRepository();
$editId = (int) query('edit', 0);

$templateRows = db()->all(
    "SELECT et.*, t.name_ar AS track_name,
            (SELECT COUNT(*) FROM `exam_attempts` a WHERE a.exam_template_id = et.id) AS attempts_count
       FROM `exam_templates` et
       JOIN `tracks` t ON t.id = et.track_id
      ORDER BY et.sort_order, et.id"
);

$attemptFilters = [
    'q'      => (string) query('q', ''),
    'status' => (string) query('status', ''),
    'mode'   => (string) query('mode', ''),
];
$page = max(1, (int) query('page', 1));
$clauses = [];
$params = [];
if ($attemptFilters['q'] !== '') {
    $clauses[] = '(u.full_name LIKE :q OR u.email LIKE :q OR a.title LIKE :q)';
    $params['q'] = '%' . $attemptFilters['q'] . '%';
}
if (in_array($attemptFilters['status'], ['in_progress', 'completed', 'expired', 'abandoned'], true)) {
    $clauses[] = 'a.status = :status';
    $params['status'] = $attemptFilters['status'];
}
if (in_array($attemptFilters['mode'], ['mock', 'category', 'random', 'practice', 'daily'], true)) {
    $clauses[] = 'a.mode = :mode';
    $params['mode'] = $attemptFilters['mode'];
}

$attempts = db()->paginate(
    "SELECT a.*, u.full_name, u.email, t.name_ar AS track_name
       FROM `exam_attempts` a
       JOIN `users` u ON u.id = a.user_id
       JOIN `tracks` t ON t.id = a.track_id"
    . ($clauses === [] ? '' : ' WHERE ' . implode(' AND ', $clauses))
    . ' ORDER BY a.id DESC',
    $params,
    20,
    $page
);

View::render('admin/exams', [
    'title'      => 'الاختبارات',
    'pageSub'    => 'قوالب الاختبارات وسجل محاولات الطلاب',
    'activeMenu' => 'admin/exams',
    'tab'        => $tab,
    'templates'  => $templateRows,
    'edit'       => $editId > 0 ? db()->one('SELECT * FROM `exam_templates` WHERE id = :id', ['id' => $editId]) : null,
    'tracks'     => $repo->tracks(),
    'categories' => $repo->categories(null, false, null),
    'attempts'   => $attempts,
    'filters'    => $attemptFilters,
    'summary'    => [
        'templates' => count($templateRows),
        'attempts'  => (int) db()->value('SELECT COUNT(*) FROM `exam_attempts`', [], 0),
        'completed' => (int) db()->value("SELECT COUNT(*) FROM `exam_attempts` WHERE status IN ('completed','expired')", [], 0),
        'avg_score' => round((float) db()->value("SELECT COALESCE(AVG(score), 0) FROM `exam_attempts` WHERE status IN ('completed','expired')", [], 0), 1),
    ],
], 'layouts/app');
