<?php
/** استيراد بنك الأسئلة: رفع ملف (PDF/TXT/CSV/JSON) أو لصق نص → تحليل → مراجعة بشرية → إدخال */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

use App\PdfExtractor;
use App\QuestionImporter;
use App\Repositories\QuestionRepository;
use App\Security;
use App\Str;
use App\View;

$admin = auth()->requireAdmin();
$adminId = (int) $admin['id'];
$repo = new QuestionRepository();

$batchId = (int) query('batch', 0);

if (is_post()) {
    $action = (string) post('action', '');

    // ------------------------------------------------ إنشاء دفعة جديدة
    if ($action === 'create') {
        $sourceId = (int) post('source_id', 0) ?: null;
        $useText = trim((string) post('pasted_text', ''));
        $fileType = 'txt';
        $fileName = 'نص ملصق';
        $filePath = null;
        $text = '';

        $hasUpload = isset($_FILES['file']) && (int) ($_FILES['file']['error'] ?? 4) !== UPLOAD_ERR_NO_FILE;

        if ($hasUpload) {
            $upload = Security::upload($_FILES['file'], ['pdf', 'txt', 'csv', 'json'], 'imports', 25);
            if (!$upload['ok']) {
                flash('danger', $upload['error']);
                redirect('admin/import');
            }
            $filePath = $upload['path'];
            $fileName = (string) $_FILES['file']['name'];
            $fileType = strtolower((string) pathinfo($fileName, PATHINFO_EXTENSION));
            $absolute = BASE_PATH . '/' . ltrim($filePath, '/');
            if (!is_file($absolute)) {
                flash('danger', 'تعذّر الوصول إلى الملف المرفوع.');
                redirect('admin/import');
            }

            if ($fileType === 'pdf') {
                $extraction = PdfExtractor::extractText($absolute);
                if (!$extraction['ok']) {
                    flash('danger', ($extraction['error'] !== '' ? $extraction['error'] . ' ' : '') . ($extraction['warning'] ?? ''));
                    $newBatchId = QuestionImporter::createBatch($fileName, 'pdf', $filePath, $adminId, hash_file('sha256', $absolute) ?: null);
                    flash('info', 'تم إنشاء دفعة رقم ' . $newBatchId . ' للملف، لكن لم يُستخرج نص منها. يمكنك لصق النص يدوياً في نفس الدفعة من الشاشة التالية.');
                    redirect('admin/import', 302, ['batch' => $newBatchId]);
                }
                $text = $extraction['text'];
                if (($extraction['warning'] ?? '') !== '') {
                    flash('warning', $extraction['warning']);
                }
            } else {
                $text = (string) file_get_contents($absolute);
            }

            $batchId = QuestionImporter::createBatch($fileName, $fileType, $filePath, $adminId, hash_file('sha256', $absolute) ?: null);
        } elseif ($useText !== '') {
            if (mb_strlen($useText) < 30) {
                flash('danger', 'النص الملصق قصير جداً — الصق نص الأسئلة كاملاً.');
                redirect('admin/import');
            }
            $text = $useText;
            $fileType = 'txt';
            $batchId = QuestionImporter::createBatch('نص ملصق — ' . date('Y-m-d H:i'), 'manual', null, $adminId);
        } else {
            flash('danger', 'ارفع ملفاً أو الصق نص الأسئلة.');
            redirect('admin/import');
        }

        $result = QuestionImporter::ingestText($batchId, $text, $fileType, $sourceId);
        question_importer_audit($adminId, 'admin.import_created', $batchId, ['file' => $fileName, 'type' => $fileType, 'ok' => $result['ok']]);
        if (!$result['ok']) {
            flash('danger', $result['message']);
            redirect('admin/import', 302, ['batch' => $batchId]);
        }
        flash('success', $result['message'] . ' راجع الصفوف بعناية قبل الإدخال.');
        redirect('admin/import', 302, ['batch' => $batchId]);
    }

    // ------------------------------------------------ إجراءات على الصفوف
    if (in_array($action, ['approve', 'reject', 'reset', 'approve_valid'], true)) {
        $targetBatch = (int) post('batch_id', 0);
        $ids = array_values(array_filter(array_map('intval', (array) post('ids', [])), static fn(int $id) => $id > 0));

        if ($action === 'approve_valid') {
            $rows = db()->all(
                "SELECT id FROM `import_staging` WHERE batch_id = :b AND status = 'pending' AND is_valid = 1 AND is_duplicate = 0 AND correct_answer IS NOT NULL",
                ['b' => $targetBatch]
            );
            $ids = array_map(static fn(array $row) => (int) $row['id'], $rows);
            if ($ids === []) {
                flash('warning', 'لا توجد صفوف صالحة جاهزة للاعتماد الجماعي.');
                redirect('admin/import', 302, ['batch' => $targetBatch]);
            }
        }

        if ($ids === []) {
            flash('warning', 'لم يتم تحديد أي صف.');
            redirect('admin/import', 302, ['batch' => $targetBatch]);
        }

        $status = match ($action) {
            'approve', 'approve_valid' => 'approved',
            'reject'                   => 'rejected',
            default                    => 'pending',
        };
        $count = QuestionImporter::setStatus($ids, $status);
        audit('admin.import_rows_' . $status, 'import_batch', $targetBatch, ['count' => $count]);
        flash('success', 'تم تحديث حالة ' . $count . ' صفاً.');
        redirect('admin/import', 302, ['batch' => $targetBatch]);
    }

    // ------------------------------------------------ تعديل صف واحد
    if ($action === 'save_row') {
        $rowId = (int) post('row_id', 0);
        $targetBatch = (int) post('batch_id', 0);
        QuestionImporter::updateRow($rowId, [
            'question_text'  => (string) post('question_text'),
            'option_a'       => (string) post('option_a'),
            'option_b'       => (string) post('option_b'),
            'option_c'       => (string) post('option_c'),
            'option_d'       => (string) post('option_d'),
            'correct_answer' => (string) post('correct_answer'),
            'explanation'    => (string) post('explanation'),
            'difficulty'     => (string) post('difficulty'),
            'source_page'    => (int) post('source_page', 0),
        ]);
        audit('admin.import_row_updated', 'import_batch', $targetBatch, ['row' => $rowId]);
        flash('success', 'تم حفظ تعديلات الصف.');
        redirect('admin/import', 302, ['batch' => $targetBatch]);
    }

    // ------------------------------------------------ الإدخال النهائي
    if ($action === 'import') {
        $targetBatch = (int) post('batch_id', 0);
        $result = QuestionImporter::importApproved($targetBatch, [
            'track_id'    => (int) post('track_id', 0),
            'category_id' => (int) post('category_id', 0),
            'source_id'   => (int) post('source_id', 0),
            'difficulty'  => (string) post('difficulty', 'medium'),
            'use_row_category' => (int) post('use_row_category', 0) === 1,
        ], $adminId);

        if ($result['imported'] > 0) {
            audit('admin.import_approved', 'import_batch', $targetBatch, ['imported' => $result['imported'], 'skipped' => $result['skipped']]);
        }
        flash($result['ok'] ? 'success' : 'danger', $result['message']);
        if ($result['warnings'] !== []) {
            flash('warning', implode(' ', array_slice($result['warnings'], 0, 5)));
        }
        redirect('admin/import', 302, ['batch' => $targetBatch]);
    }

    // ------------------------------------------------ حذف دفعة
    if ($action === 'delete_batch') {
        $targetBatch = (int) post('batch_id', 0);
        QuestionImporter::deleteBatch($targetBatch);
        audit('admin.import_batch_deleted', 'import_batch', $targetBatch);
        flash('success', 'تم حذف الدفعة وكل صفوفها.');
        redirect('admin/import');
    }
}

/** تسجيل العملية في سجل التدقيق */
function question_importer_audit(int $adminId, string $action, int $batchId, array $meta = []): void
{
    audit($action, 'import_batch', $batchId, $meta);
}

$sourceOptions = $repo->sources();
$tracks = $repo->tracks();

if ($batchId > 0) {
    $batch = QuestionImporter::batch($batchId);
    if ($batch === null) {
        flash('danger', 'الدفعة المطلوبة غير موجودة.');
        redirect('admin/import');
    }
    $status = (string) query('status', 'pending');
    $page = max(1, (int) query('page', 1));
    View::render('admin/import-review', [
        'title'      => 'مراجعة دفعة الاستيراد #' . $batchId,
        'pageSub'    => 'راجع كل صف وعدّله ثم اعتمد ما تثبت صحته — لا يُدخل النظام أي سؤال دون اعتماد بشري',
        'activeMenu' => 'admin/import',
        'batch'      => $batch,
        'stats'      => QuestionImporter::batchStats($batchId),
        'rows'       => QuestionImporter::rows($batchId, ['status' => $status, 'only_review' => (int) query('only_review', 0), 'only_duplicates' => (int) query('only_duplicates', 0), 'only_invalid' => (int) query('only_invalid', 0)], $page, 20),
        'status'     => $status,
        'onlyReview' => (int) query('only_review', 0),
        'onlyDuplicates' => (int) query('only_duplicates', 0),
        'onlyInvalid'    => (int) query('only_invalid', 0),
        'tracks'     => $tracks,
        'categories' => $repo->categories(null, false, null),
        'sources'    => $sourceOptions,
    ], 'layouts/app');
    exit;
}

View::render('admin/import', [
    'title'      => 'استيراد بنك الأسئلة',
    'pageSub'    => 'استخرج الأسئلة من ملفاتك ثم راجعها بشرياً قبل الإدخال — لا إدخال تلقائي أعمى',
    'activeMenu' => 'admin/import',
    'batches'    => QuestionImporter::batches(30),
    'sources'    => $sourceOptions,
    'tracks'     => $tracks,
    'pdfReady'   => PdfExtractor::isAvailable(),
    'stats'      => $repo->statistics(),
], 'layouts/app');
