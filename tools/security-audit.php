<?php
/**
 * أداة فحص الحماية (Security Audit) — سطر الأوامر
 *
 *   php tools/security-audit.php
 *   php tools/security-audit.php --url=https://example.com   # + فحص الوصول عبر HTTP
 *   php tools/security-audit.php --json                      # تقرير آلي
 *   php tools/security-audit.php --fix-perms                 # إصلاح صلاحية .env
 *
 * رمز الخروج: 0 = لا مخاطر، 1 = يوجد بند «خطر».
 * النتائج نفسها متاحة داخل لوحة الإدارة: الإدارة ← فحص الحماية.
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

use App\SecurityAudit;

if (!is_cli()) {
    http_response_code(403);
    exit('هذا الملف يعمل من سطر الأوامر فقط.');
}

$asJson   = cli_has_flag('json');
$fixPerms = cli_has_flag('fix-perms');
$baseUrl  = rtrim((string) cli_option('url', ''), '/');

$report  = SecurityAudit::run($fixPerms, $baseUrl);
$checks  = $report['checks'];
$summary = $report['summary'];

if ($asJson) {
    echo json_encode([
        'generated_at' => date('c'),
        'summary'      => $summary,
        'checks'       => $checks,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), "\n";
    exit($summary['danger'] > 0 ? 1 : 0);
}

$icons = ['ok' => '✔', 'warn' => '⚠', 'danger' => '✘'];
echo "\n  ╔══════════════════════════════════════════════════════════╗\n";
echo "  ║           تقرير فحص الحماية — منصة لُقمان              ║\n";
echo "  ╚══════════════════════════════════════════════════════════╝\n";
echo '  التاريخ: ' . date('Y-m-d H:i:s') . '   |   PHP ' . PHP_VERSION . "\n\n";

foreach ($checks as $check) {
    echo '  ' . $icons[$check['level']] . ' [' . strtoupper($check['level']) . '] ' . $check['title'] . "\n";
    echo '      ' . $check['detail'] . "\n";
    if ($check['level'] !== 'ok' && $check['fix'] !== '') {
        echo '      ↳ الإجراء: ' . $check['fix'] . "\n";
    }
    echo "\n";
}

echo "  ───────────────────────────────────────────────────────────\n";
echo '  النتيجة: ' . $summary['ok'] . ' مطبَّق ✔   |   ' . $summary['warn'] . ' يحتاج انتباهاً ⚠   |   ' . $summary['danger'] . " خطر ✘\n";
echo "  ───────────────────────────────────────────────────────────\n";

if ($summary['danger'] > 0) {
    echo "  ⛔ توجد بنود «خطر» يجب معالجتها قبل التشغيل الفعلي.\n";
    exit(1);
}
if ($summary['warn'] > 0) {
    echo "  ⚠ البنود أعلاه تُحسَّن لكنها لا تمنع التشغيل.\n";
    exit(0);
}
echo "  ✔ النسخة مطابقة لسياسة الحماية المقترحة.\n";
