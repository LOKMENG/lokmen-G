<?php
/**
 * إدارة شهادات المتدربين: إضافة/تعديل/نشر/إخفاء/حذف.
 * قاعدة صارمة: لا يُنشر أي نص إلا عند تفعيل «موافقة صاحب الشهادة» + «النشر».
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

use App\Audit;
use App\Validator;
use App\View;

auth()->requireAdmin();

if (is_post()) {
    $action = (string) post('action', '');
    $id = (int) post('id', 0);

    switch ($action) {
        case 'save':
            $data = [
                'name'         => trim((string) post('name', '')),
                'role'         => trim((string) post('role', '')),
                'city'         => trim((string) post('city', '')),
                'body'         => trim((string) post('body', '')),
                'rating'       => max(1, min(5, (int) post('rating', 5))),
                'consent'      => post('consent') ? 1 : 0,
                'is_published' => post('is_published') ? 1 : 0,
                'sort_order'   => (int) post('sort_order', 0),
            ];
            $validator = Validator::make($data, [
                'name' => 'required|min:2|max:190',
                'body' => 'required|min:10|max:1000',
                'role' => 'max:190',
                'city' => 'max:100',
            ], [
                'name' => 'اسم صاحب الشهادة',
                'body' => 'نص الشهادة',
            ]);
            if ($validator->fails()) {
                set_errors($validator->errors());
                keep_old_input();
                flash('error', 'تحقق من الحقول المطلوبة.');
                redirect('admin/testimonials', $id > 0 ? ['edit' => $id] : []);
            }
            if ($data['is_published'] === 1 && $data['consent'] !== 1) {
                flash('warning', 'لم يتم النشر: يجب تأكيد موافقة صاحب الشهادة على النشر.');
                $data['is_published'] = 0;
            }
            if ($id > 0) {
                db()->update('testimonials', $data, 'id = :id', ['id' => $id]);
                audit('admin.testimonial_updated', 'testimonial', $id, ['published' => $data['is_published']]);
                flash('success', 'تم تحديث الشهادة.');
            } else {
                $id = db()->insert('testimonials', $data);
                audit('admin.testimonial_created', 'testimonial', $id, ['published' => $data['is_published']]);
                flash('success', 'تمت إضافة الشهادة.');
            }
            redirect('admin/testimonials', ['edit' => $id]);

        case 'publish':
        case 'unpublish':
            $row = db()->one('SELECT * FROM `testimonials` WHERE id = :id', ['id' => $id]);
            if ($row === null) {
                flash('error', 'الشهادة غير موجودة.');
                redirect('admin/testimonials');
            }
            if ($action === 'publish' && (int) $row['consent'] !== 1) {
                flash('warning', 'لا يمكن النشر قبل تأكيد موافقة صاحب الشهادة.');
                redirect('admin/testimonials');
            }
            db()->update('testimonials', ['is_published' => $action === 'publish' ? 1 : 0], 'id = :id', ['id' => $id]);
            audit('admin.testimonial_' . $action, 'testimonial', $id);
            flash('success', $action === 'publish' ? 'تم نشر الشهادة.' : 'تم إخفاء الشهادة.');
            redirect('admin/testimonials');

        case 'delete':
            db()->delete('testimonials', 'id = :id', ['id' => $id]);
            audit('admin.testimonial_deleted', 'testimonial', $id);
            flash('success', 'تم حذف الشهادة.');
            redirect('admin/testimonials');
    }
}

$editId = (int) query('edit', 0);
$editing = $editId > 0 ? db()->one('SELECT * FROM `testimonials` WHERE id = :id', ['id' => $editId]) : null;
$rows = db()->all('SELECT * FROM `testimonials` ORDER BY is_published DESC, sort_order ASC, id DESC LIMIT 200');
$stats = [
    'total'     => count($rows),
    'published' => count(array_filter($rows, static fn(array $row): bool => (int) $row['is_published'] === 1)),
    'waiting'   => count(array_filter($rows, static fn(array $row): bool => (int) $row['consent'] === 1 && (int) $row['is_published'] === 0)),
];

View::render('admin/testimonials', [
    'title'      => 'شهادات المتدربين',
    'pageSub'    => 'أضف آراء المتدربين الحقيقية وانشرها على الصفحة الرئيسية',
    'activeMenu' => 'admin/testimonials',
    'rows'       => $rows,
    'editing'    => $editing,
    'stats'      => $stats,
], 'layouts/app');
