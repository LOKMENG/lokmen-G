<?php
/** بنك الأسئلة: بحث وتصفية وإجراءات جماعية */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

use App\Repositories\QuestionRepository;
use App\View;

$admin = auth()->requireAdmin();
$adminId = (int) $admin['id'];
$repo = new QuestionRepository();

if (is_post()) {
    $action = (string) post('action', '');
    $ids = array_values(array_filter(array_map('intval', (array) post('ids', [])), static fn(int $id) => $id > 0));

    if ($ids === []) {
        flash('warning', 'لم يتم تحديد أي أسئلة.');
        redirect('admin/questions');
    }

    switch ($action) {
        case 'activate':
            $count = $repo->bulkSet($ids, ['active' => 1, 'updated_by' => $adminId]);
            flash('success', 'تم تفعيل ' . $count . ' سؤالاً.');
            break;
        case 'deactivate':
            $count = $repo->bulkSet($ids, ['active' => 0, 'updated_by' => $adminId]);
            flash('success', 'تم تعطيل ' . $count . ' سؤالاً.');
            break;
        case 'reviewed':
            $count = $repo->bulkSet($ids, ['needs_review' => 0, 'review_note' => null, 'updated_by' => $adminId]);
            flash('success', 'تم تعليم ' . $count . ' سؤالاً كمراجَع.');
            break;
        case 'needs_review':
            $count = $repo->bulkSet($ids, ['needs_review' => 1, 'updated_by' => $adminId]);
            flash('success', 'تم تعليم ' . $count . ' سؤالاً كمحتاج للمراجعة.');
            break;
        case 'delete':
            $count = $repo->bulkDelete($ids);
            audit('admin.questions_deleted', 'question', null, ['ids' => $ids, 'count' => $count]);
            flash('success', 'تم حذف ' . $count . ' سؤالاً.');
            break;
        default:
            flash('warning', 'إجراء غير معروف.');
    }
    redirect('admin/questions');
}

$filters = [
    'q'            => (string) query('q', ''),
    'track_id'     => (int) query('track', 0),
    'category_id'  => (int) query('category', 0),
    'difficulty'   => (string) query('difficulty', ''),
    'active'       => (string) query('active', ''),
    'needs_review' => (string) query('needs_review', ''),
    'reported'     => (int) query('reported', 0),
];
$page = max(1, (int) query('page', 1));

$paginated = $repo->search($filters, $page, 25);

View::render('admin/questions', [
    'title'      => 'بنك الأسئلة',
    'pageSub'    => 'إدارة وتصفية ومراجعة الأسئلة',
    'activeMenu' => 'admin/questions',
    'paginated'  => $paginated,
    'filters'    => $filters + ['page' => $page],
    'tracks'     => $repo->tracks(),
    'tree'       => $repo->categoryTree($filters['track_id'] > 0 ? $filters['track_id'] : null, false),
    'stats'      => $repo->statistics(),
    'sources'    => $repo->sources(),
], 'layouts/app');
