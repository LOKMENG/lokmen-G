<?php
/** الإبلاغ عن خطأ في سؤال (يصل للإدارة في قائمة البلاغات) */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

use App\Repositories\QuestionRepository;
use App\Validator;
use App\View;

$user = auth()->requireLogin();
$questionId = (int) (query('question', 0) ?: post('question_id', 0));

if (is_post()) {
    $validator = Validator::make($_POST, [
        'question_id' => 'required|integer|exists:questions,id',
        'reason'      => 'required|in:wrong_answer,typo,unclear,duplicate,other,copyright',
        'note'        => 'max:1000',
    ]);
    if (!$validator->validate()) {
        set_errors($validator->errors());
        redirect('questions/report', 302, ['question' => $questionId]);
    }
    $repo = new QuestionRepository();
    $repo->report((int) post('question_id'), (int) $user['id'], (string) post('reason'), (string) post('note', ''));
    audit('question.reported', 'question', (int) post('question_id'), ['reason' => (string) post('reason')]);
    flash('success', 'شكراً لك! تم إرسال البلاغ للإدارة وسيتم مراجعة السؤال.');
    redirect('exams/history');
}

$question = null;
if ($questionId > 0) {
    $question = (new QuestionRepository())->find($questionId);
}
if ($question === null) {
    flash('warning', 'السؤال المطلوب غير موجود.');
    redirect('exams/history');
}

View::render('questions/report', [
    'title'      => 'الإبلاغ عن سؤال',
    'pageSub'    => 'رأيك يساعدنا في تحسين جودة بنك الأسئلة',
    'activeMenu' => 'questions/index',
    'question'   => $question,
    'errors'     => errors(),
], 'layouts/app');
