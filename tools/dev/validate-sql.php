<?php
/**
 * فحص سريع لملف database.sql بدون أي مكتبات خارجية:
 *   - تقسيم الملف إلى جمل والتأكد من أن كل جملة تبدأ بكلمة SQL معروفة
 *   - إحصاء الجداول والعروض والصفوف المزروعة
 *   - التأكد من وجود جداول المنصة المتوقعة (App\DatabaseImporter::expectedTables)
 *
 * التشغيل:  php tools/dev/validate-sql.php
 */
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/bootstrap.php';

$file = BASE_PATH . '/database.sql';
if (!is_file($file)) {
    fwrite(STDERR, "الملف غير موجود: {$file}\n");
    exit(1);
}
$sql = (string) file_get_contents($file);
$statements = App\DatabaseImporter::split($sql);
$filtered = App\DatabaseImporter::filterSchemaStatements($statements);

$known = ['CREATE', 'DROP', 'INSERT', 'UPDATE', 'DELETE', 'ALTER', 'SET', 'SELECT', 'GRANT', 'REPLACE', 'CREATE VIEW'];
$unknown = [];
$tables = [];
$views = [];
$inserts = 0;
foreach ($filtered['statements'] as $statement) {
    $keyword = strtoupper((string) strtok($statement, " \t\n("));
    $ok = false;
    foreach ($known as $candidate) {
        if (str_starts_with(strtoupper($statement), $candidate)) {
            $ok = true;
            break;
        }
    }
    if (!$ok) {
        $unknown[] = App\Str::limit(preg_replace('/\s+/', ' ', $statement) ?? $statement, 70);
    }
    if (preg_match('/^CREATE TABLE `([^`]+)`/i', $statement, $match) === 1) {
        $tables[] = $match[1];
    }
    if (preg_match('/^CREATE (?:OR REPLACE )?VIEW `([^`]+)`/i', $statement, $match) === 1) {
        $views[] = $match[1];
    }
    if (preg_match('/^INSERT/i', $statement) === 1) {
        $inserts++;
    }
}

$missing = array_values(array_diff(App\DatabaseImporter::expectedTables(), $tables));

echo "\n  فحص database.sql\n";
echo "  ─────────────────────────────────────────────\n";
echo '  الجمل: ' . count($statements) . ' (قابلة للتنفيذ: ' . count($filtered['statements']) . ' — مستثناة: ' . $filtered['skipped'] . ")\n";
echo '  الجداول: ' . count($tables) . ' — العروض: ' . count($views) . ' — جمل INSERT: ' . $inserts . "\n";
echo '  جمل غير معروفة: ' . count($unknown) . "\n";
foreach ($unknown as $line) {
    echo '    - ' . $line . "\n";
}
echo '  جداول متوقعة مفقودة: ' . ($missing === [] ? 'لا شيء' : implode('، ', $missing)) . "\n";
$ok = $unknown === [] && $missing === [];
echo "\n  النتيجة: " . ($ok ? 'ناجح ✔' : 'فاشل ✘') . "\n\n";
exit($ok ? 0 : 1);
