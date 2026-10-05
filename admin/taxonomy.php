<?php
/** إدارة التصنيفات: المسارات والمجالات والمصادر */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

use App\Repositories\QuestionRepository;
use App\Str;
use App\View;

$admin = auth()->requireAdmin();
$adminId = (int) $admin['id'];
$repo = new QuestionRepository();

if (is_post()) {
    $action = (string) post('action', '');
    $ok = true;
    $message = '';

    switch ($action) {
        case 'save_track':
            $id = (int) post('id', 0);
            $nameAr = trim((string) post('name_ar'));
            if ($nameAr === '') {
                $ok = false;
                $message = 'اسم المسار مطلوب.';
                break;
            }
            $data = [
                'code'        => trim((string) post('code', '')) ?: Str::slug($nameAr),
                'name_ar'     => $nameAr,
                'name_en'     => trim((string) post('name_en', '')) ?: null,
                'description' => trim((string) post('description', '')) ?: null,
                'icon'        => trim((string) post('icon', '')) ?: null,
                'color'       => trim((string) post('color', '')) ?: null,
                'sort_order'  => (int) post('sort_order', 0),
                'is_active'   => (int) post('is_active', 0) === 1 ? 1 : 0,
            ];
            if ($id > 0) {
                db()->update('tracks', $data, 'id = :id', ['id' => $id]);
                audit('admin.track_updated', 'track', $id);
            } else {
                $id = db()->insert('tracks', $data);
                audit('admin.track_created', 'track', $id);
            }
            $message = 'تم حفظ المسار.';
            break;

        case 'delete_track':
            $id = (int) post('id', 0);
            $questions = (int) db()->value('SELECT COUNT(*) FROM `questions` WHERE track_id = :id', ['id' => $id], 0);
            if ($questions > 0) {
                $ok = false;
                $message = 'لا يمكن حذف المسار لاحتوائه على ' . $questions . ' سؤالاً. عطّله بدلاً من ذلك.';
            } else {
                db()->delete('tracks', 'id = :id', ['id' => $id]);
                audit('admin.track_deleted', 'track', $id);
                $message = 'تم حذف المسار.';
            }
            break;

        case 'save_category':
            $id = (int) post('id', 0);
            $nameAr = trim((string) post('name_ar'));
            $trackId = (int) post('track_id', 0);
            if ($nameAr === '' || $trackId <= 0) {
                $ok = false;
                $message = 'اسم المجال والمسار مطلوبان.';
                break;
            }
            $parentId = (int) post('parent_id', 0) ?: null;
            if ($id > 0 && $parentId === $id) {
                $parentId = null;
            }
            $data = [
                'track_id'    => $trackId,
                'parent_id'   => $parentId,
                'code'        => trim((string) post('code', '')) ?: Str::slug($nameAr),
                'name_ar'     => $nameAr,
                'name_en'     => trim((string) post('name_en', '')) ?: null,
                'description' => trim((string) post('description', '')) ?: null,
                'icon'        => trim((string) post('icon', '')) ?: null,
                'color'       => trim((string) post('color', '')) ?: null,
                'sort_order'  => (int) post('sort_order', 0),
                'is_active'   => (int) post('is_active', 0) === 1 ? 1 : 0,
            ];
            if ($id > 0) {
                db()->update('categories', $data, 'id = :id', ['id' => $id]);
                audit('admin.category_updated', 'category', $id);
            } else {
                $id = db()->insert('categories', $data);
                audit('admin.category_created', 'category', $id);
            }
            $message = 'تم حفظ المجال.';
            break;

        case 'delete_category':
            $id = (int) post('id', 0);
            $used = (int) db()->value('SELECT COUNT(*) FROM `questions` WHERE category_id = :id OR subcategory_id = :id', ['id' => $id], 0);
            if ($used > 0) {
                $ok = false;
                $message = 'لا يمكن حذف المجال لارتباطه بـ ' . $used . ' سؤالاً. عطّله بدلاً من ذلك.';
            } else {
                db()->delete('categories', 'id = :id', ['id' => $id]);
                audit('admin.category_deleted', 'category', $id);
                $message = 'تم حذف المجال.';
            }
            break;

        case 'save_source':
            $id = (int) post('id', 0);
            $name = trim((string) post('name'));
            if ($name === '') {
                $ok = false;
                $message = 'اسم المصدر مطلوب.';
                break;
            }
            $data = [
                'name'         => $name,
                'type'         => (string) post('type', 'pdf'),
                'author'       => trim((string) post('author', '')) ?: null,
                'year'         => (int) post('year', 0) ?: null,
                'url'          => trim((string) post('url', '')) ?: null,
                'license_note' => trim((string) post('license_note', '')) ?: null,
                'notes'        => trim((string) post('notes', '')) ?: null,
                'is_active'    => (int) post('is_active', 0) === 1 ? 1 : 0,
            ];
            if ($id > 0) {
                db()->update('sources', $data, 'id = :id', ['id' => $id]);
                audit('admin.source_updated', 'source', $id);
            } else {
                $data['created_by'] = $adminId;
                $id = db()->insert('sources', $data);
                audit('admin.source_created', 'source', $id);
            }
            $message = 'تم حفظ المصدر.';
            break;

        case 'delete_source':
            $id = (int) post('id', 0);
            db()->update('questions', ['source_id' => null], 'source_id = :id', ['id' => $id]);
            db()->delete('sources', 'id = :id', ['id' => $id]);
            audit('admin.source_deleted', 'source', $id);
            $message = 'تم حذف المصدر وإلغاء ربطه بالأسئلة.';
            break;

        default:
            $ok = false;
            $message = 'إجراء غير معروف.';
    }

    flash($ok ? 'success' : 'danger', $message);
    redirect('admin/taxonomy', 302, ['tab' => (string) post('tab', 'tracks')]);
}

$tab = (string) query('tab', 'tracks');
if (!in_array($tab, ['tracks', 'categories', 'sources'], true)) {
    $tab = 'tracks';
}
$editTrackId = (int) query('edit_track', 0);
$editCategoryId = (int) query('edit_category', 0);
$editSourceId = (int) query('edit_source', 0);

View::render('admin/taxonomy', [
    'title'      => 'التصنيفات والمصادر',
    'pageSub'    => 'إدارة المسارات والمجالات ومصادر الأسئلة',
    'activeMenu' => 'admin/taxonomy',
    'tab'        => $tab,
    'tracks'     => $repo->tracks(),
    'categories' => $repo->categories(null, false, null),
    'sources'    => $repo->sources(),
    'editTrack'    => $editTrackId > 0 ? db()->one('SELECT * FROM `tracks` WHERE id = :id', ['id' => $editTrackId]) : null,
    'editCategory' => $editCategoryId > 0 ? db()->one('SELECT * FROM `categories` WHERE id = :id', ['id' => $editCategoryId]) : null,
    'editSource'   => $editSourceId > 0 ? db()->one('SELECT * FROM `sources` WHERE id = :id', ['id' => $editSourceId]) : null,
], 'layouts/app');
