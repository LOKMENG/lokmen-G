<?php
/** إضافة/تعديل سؤال واحد مع كشف التكرار */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

use App\Repositories\QuestionRepository;
use App\Str;
use App\Validator;
use App\View;

$admin = auth()->requireAdmin();
$adminId = (int) $admin['id'];
$repo = new QuestionRepository();

$questionId = (int) query('id', 0);
$question = $questionId > 0 ? $repo->find($questionId) : null;

if (is_post()) {
    $id = (int) post('id', 0);
    $validator = Validator::make($_POST, [
        'track_id'       => 'required|integer|exists:tracks,id',
        'category_id'    => 'required|integer|exists:categories,id',
        'question_text'  => 'required|min:10',
        'option_a'       => 'required|max:1000',
        'option_b'       => 'required|max:1000',
        'option_c'       => 'max:1000',
        'option_d'       => 'max:1000',
        'correct_answer' => 'required|in:a,b,c,d',
        'explanation'    => 'max:2000',
        'difficulty'     => 'required|in:easy,medium,hard',
        'source_id'      => 'integer',
        'source_page'    => 'integer|min:1|max:5000',
        'year'           => 'integer|min:1400|max:1500',
        'review_note'    => 'max:255',
    ]);
    if (!$validator->validate()) {
        set_errors($validator->errors());
        keep_old_input();
        redirect('admin/question-form', 302, $id > 0 ? ['id' => $id] : []);
    }

    $text = trim((string) post('question_text'));
    $hash = Str::contentFingerprint($text);
    $duplicate = $repo->duplicateExists($hash, $id > 0 ? $id : null);
    if ($duplicate !== null && (int) post('allow_duplicate', 0) !== 1) {
        set_errors(['question_text' => 'يوجد سؤال مطابق أو مشابه جداً (رقم ' . $duplicate['id'] . '). راجع السؤال أو أكّد الإضافة.']);
        keep_old_input();
        redirect('admin/question-form', 302, $id > 0 ? ['id' => $id, 'duplicate' => $duplicate['id']] : ['duplicate' => $duplicate['id']]);
    }

    $data = [
        'track_id'       => (int) post('track_id'),
        'category_id'    => (int) post('category_id'),
        'subcategory_id' => (int) post('subcategory_id', 0) ?: null,
        'source_id'      => (int) post('source_id', 0) ?: null,
        'question_text'  => $text,
        'question_type'  => (string) post('question_type', 'mcq') === 'true_false' ? 'true_false' : 'mcq',
        'option_a'       => trim((string) post('option_a')),
        'option_b'       => trim((string) post('option_b')),
        'option_c'       => trim((string) post('option_c', '')) ?: null,
        'option_d'       => trim((string) post('option_d', '')) ?: null,
        'correct_answer' => (string) post('correct_answer'),
        'explanation'    => trim((string) post('explanation', '')) ?: null,
        'difficulty'     => (string) post('difficulty', 'medium'),
        'source_note'    => trim((string) post('source_note', '')) ?: null,
        'source_page'    => (int) post('source_page', 0) ?: null,
        'year'           => (int) post('year', 0) ?: null,
        'needs_review'   => (int) post('needs_review', 0) === 1 ? 1 : 0,
        'review_note'    => trim((string) post('review_note', '')) ?: null,
        'active'         => (int) post('active', 0) === 1 ? 1 : 0,
        'content_hash'   => $hash,
        'updated_by'     => $adminId,
    ];
    if ($data['needs_review'] === 0) {
        $data['review_note'] = null;
    }

    if ($id > 0) {
        $repo->update($id, $data);
        audit('admin.question_updated', 'question', $id);
        flash('success', 'تم تحديث السؤال بنجاح.');
    } else {
        $data['created_by'] = $adminId;
        $id = $repo->create($data);
        audit('admin.question_created', 'question', $id);
        flash('success', 'تمت إضافة السؤال بنجاح.');
    }
    clear_old_input();
    redirect('admin/question-form', 302, ['id' => $id]);
}

View::render('admin/question-form', [
    'title'       => $questionId > 0 ? 'تعديل سؤال' : 'إضافة سؤال',
    'pageSub'     => 'اكتب السؤال واختر الإجابة الصحيحة بدقة',
    'activeMenu'  => 'admin/questions',
    'question'    => $question,
    'tracks'      => $repo->tracks(),
    'categories'  => $repo->categories(null, false, null),
    'sources'     => $repo->sources(),
    'errors'      => errors(),
    'duplicateId' => (int) query('duplicate', 0),
], 'layouts/app');
