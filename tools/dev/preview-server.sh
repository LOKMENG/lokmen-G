#!/usr/bin/env bash
#
# إعادة بناء «معاينة التصميم» الثابتة وتشغيلها على سيرفر محلي.
#
# المعاينة تُولّد صفحات HTML جاهزة من قوالب المنصة نفسها على قاعدة بيانات
# وهمية في الذاكرة (بلا MySQL وبلا تعديل أي بيانات)، وتُستخدم لمراجعة الشكل فقط.
#
# الاستخدام:
#   bash tools/dev/preview-server.sh              # يولّد الصفحات ويخدمها على :8080
#   bash tools/dev/preview-server.sh --build-only # يولّد الصفحات دون تشغيل السيرفر
#   PORT=9000 bash tools/dev/preview-server.sh    # منفذ آخر
#
# ملاحظة: إن وُجدت php في البيئة تُستخدم مباشرة؛ وإلا تُستخدم بيئة php-wasm
#         ( node + npm ) تلقائياً. لا علاقة لهذا السكربت بالإنتاج.
set -u

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
OUT="${PREVIEW_OUT:-/tmp/preview}"
PORT="${PORT:-8080}"
BUILD_ONLY=0
for arg in "$@"; do
    case "$arg" in
        --build-only) BUILD_ONLY=1 ;;
        --help|-h) sed -n '2,20p' "$0"; exit 0 ;;
    esac
done

pages=(
  "index.php:index.html"
  "auth/login.php:login.html"
  "auth/register.php:register.html"
  "questions/index.php:questions.html"
  "exams/index.php:exams.html"
  "exams/take.php:exam-take.html"
  "student/dashboard.php:dashboard.html"
  "student/statistics.php:statistics.html"
  "subscriptions/plans.php:plans.html"
  "admin/index.php:admin.html"
  "admin/questions.php:admin-questions.html"
  "admin/import.php:admin-import.html"
)

echo "== 1/4  تجهيز مولّد الصفحات =="
RUNNER=""
if command -v php >/dev/null 2>&1; then
    RUNNER="php"
    echo "    استخدام PHP المثبت: $(php -r 'echo PHP_VERSION;' 2>/dev/null)"
else
    WASM_DIR="${WASM_DIR:-/tmp/phpwasm}"
    mkdir -p "$WASM_DIR"
    cd "$WASM_DIR" || exit 1
    [ -d node_modules ] || npm init -y >/dev/null 2>&1
    echo "    تركيب php-wasm (مرة واحدة)…"
    npm install php-wasm php-wasm-mbstring php-wasm-iconv php-wasm-libxml php-wasm-dom \
                php-wasm-simplexml php-wasm-xml php-wasm-zlib php-wasm-openssl php-wasm-libzip \
                >/dev/null 2>&1
    cat > "$WASM_DIR/run.mjs" <<'RUNEOF'
import fs from 'node:fs';
import path from 'node:path';
import { PhpNode } from 'php-wasm/PhpNode';
import mbstring from 'php-wasm-mbstring';
import iconv from 'php-wasm-iconv';
import libxml from 'php-wasm-libxml';
import dom from 'php-wasm-dom';
import simplexml from 'php-wasm-simplexml';
import xml from 'php-wasm-xml';
import zlib from 'php-wasm-zlib';
import openssl from 'php-wasm-openssl';
import libzip from 'php-wasm-libzip';
process.on('unhandledRejection', () => {});
const PROJECT_ROOT = process.env.PROJECT_ROOT;
const script = process.argv[2];
const args = process.argv.slice(3);
const php = new PhpNode({ version: '8.4', sharedLibs: [mbstring, iconv, libxml, dom, simplexml, xml, zlib, openssl, libzip] });
let out = '';
const onOut = (e) => { out += e.detail[0]; };
php.addEventListener('output', onOut);
const SKIP = new Set(['.git', 'node_modules', 'storage', 'dist', '.arena', 'vendor']);
const PATCH = ['config/bootstrap.php', 'includes/helpers.php', 'tests/run.php'];
function copyTree(localDir, wasmDir) {
  php.mkdir(wasmDir);
  for (const entry of fs.readdirSync(localDir, { withFileTypes: true })) {
    if (SKIP.has(entry.name)) continue;
    const localPath = path.join(localDir, entry.name);
    const rel = path.relative(PROJECT_ROOT, localPath).split(path.sep).join('/');
    if (entry.isDirectory()) { copyTree(localPath, wasmDir + '/' + entry.name); continue; }
    if (!entry.isFile()) continue;
    if (PATCH.includes(rel)) {
      php.writeFile(wasmDir + '/' + entry.name, fs.readFileSync(localPath, 'utf8')
        .split("['cli', 'phpdbg', 'wasm']").join("['cli', 'phpdbg', 'wasm', 'embed']")
        .split("PHP_SAPI !== 'cli'").join("PHP_SAPI !== 'cli' && PHP_SAPI !== 'embed'"));
      continue;
    }
    php.writeFile(wasmDir + '/' + entry.name, fs.readFileSync(localPath));
  }
}
copyTree(PROJECT_ROOT, '/proj');
for (const d of ['/proj/storage', '/proj/storage/logs', '/proj/storage/cache', '/proj/storage/tmp',
                 '/proj/uploads', '/proj/uploads/receipts', '/proj/uploads/questions', '/tmp', '/dev', '/etc']) {
  try { php.mkdir(d); } catch {}
}
try { php.writeFile('/dev/urandom', new Uint8Array(4096).map(() => Math.floor(Math.random() * 256))); } catch {}
try { php.writeFile('/dev/null', new Uint8Array(0)); } catch {}
if (!fs.existsSync(PROJECT_ROOT + '/.env')) {
  try { php.writeFile('/proj/.env', fs.readFileSync(PROJECT_ROOT + '/.env.example', 'utf8')); } catch {}
}
const wrapper = `<?php
declare(strict_types=1);
if (!defined('APP_NO_SESSION')) define('APP_NO_SESSION', true);
if (!defined('STDOUT')) define('STDOUT', fopen('php://stdout', 'w'));
if (!defined('STDERR')) define('STDERR', fopen('php://stderr', 'w'));
$GLOBALS['argv'] = array_merge(['${path.basename(script)}'], ${JSON.stringify(args)});
$_SERVER['APP_ARGV'] = implode(' ', array_slice($GLOBALS['argv'], 1));
$_SERVER['SCRIPT_NAME'] = '/${script}';
$_SERVER['SCRIPT_FILENAME'] = '/proj/${script}';
$_SERVER['REQUEST_URI'] = '/${script}';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['REQUEST_METHOD'] = 'GET';
chdir('/proj');
require '/proj/${script}';
`;
php.writeFile('/proj/__wasm_entry.php', wrapper);
(async () => {
  try { await php.run('<?php require "/proj/__wasm_entry.php";'); }
  catch (e) { process.stderr.write('RUN ERROR: ' + (e && e.stack ? e.stack : String(e)) + '\n'); }
  php.removeEventListener('output', onOut);
  process.stdout.write(out);
  if (process.env.RUN_OUT) fs.writeFileSync(process.env.RUN_OUT, out);
})();
RUNEOF
    RUNNER="wasm"
    echo "    بيئة php-wasm جاهزة"
fi

echo "== 2/4  توليد الصفحات =="
rm -rf "$OUT"
mkdir -p "$OUT"
cd "$ROOT" || exit 1
ok=0; failed=0
for pair in "${pages[@]}"; do
    route="${pair%%:*}"
    file="${pair##*:}"
    if [ "$RUNNER" = "php" ]; then
        php tools/dev/render-preview.php --route="$route" --out="$OUT" >/dev/null 2>/tmp/preview_err.txt
    else
        PROJECT_ROOT="$ROOT" RUN_OUT="$OUT/$file" node "$WASM_DIR/run.mjs" tools/dev/render-preview.php \
            --route="$route" --out=/tmp/preview --print >/dev/null 2>/tmp/preview_err.txt
    fi
    if [ -s "$OUT/$file" ] && ! grep -q "تعذّر توليد الصفحة" "$OUT/$file"; then
        ok=$((ok+1))
    else
        failed=$((failed+1))
        echo "    ✘ $route"
        head -3 /tmp/preview_err.txt
    fi
done
echo "    ناجح: $ok  |  فاشل: $failed"

echo "== 3/4  الأصول وشريط التنقل =="
cp -r "$ROOT/assets" "$OUT/" 2>/dev/null || true
python3 - "$OUT" <<'PYEOF'
import os, re, sys
out = sys.argv[1]
pages = [('index.html','الرئيسية'), ('login.html','دخول'), ('register.html','حساب جديد'),
         ('questions.html','بنك الأسئلة'), ('exams.html','الاختبارات'), ('exam-take.html','أداء اختبار'),
         ('dashboard.html','لوحة الطالب'), ('statistics.html','الإحصاءات'), ('plans.html','الباقات'),
         ('admin.html','لوحة الإدارة'), ('admin-questions.html','إدارة الأسئلة'), ('admin-import.html','استيراد الأسئلة')]
strip = ('<div class="pl-preview-nav" style="position:fixed;inset-block-start:0;inset-inline-start:0;'
         'inset-inline-end:0;z-index:99999;background:#232D3C;color:#fff;font:600 12px/1.6 system-ui,sans-serif;'
         'padding:8px 12px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;overflow-x:auto;'
         'box-shadow:0 2px 12px rgba(35,45,60,.25)">'
         '<span style="background:#2A7A62;padding:3px 10px;border-radius:999px;white-space:nowrap">معاينة التصميم</span>'
         + ''.join('<a href="%s" style="color:#E8EDF7;text-decoration:none;white-space:nowrap;opacity:.85;'
                   'padding:3px 8px;border-radius:8px;background:rgba(255,255,255,.08)">%s</a>' % (f, l)
                   for f, l in pages)
         + '<span style="margin-inline-start:auto;opacity:.6;white-space:nowrap">عرض ثابت — بلا قاعدة بيانات</span></div>'
         '<div style="height:46px"></div>')
count = 0
for f, _ in pages:
    path = os.path.join(out, f)
    if not os.path.exists(path):
        continue
    s = open(path, encoding='utf-8').read()
    if 'pl-preview-nav' in s:
        continue
    s2 = re.sub(r'<body([^>]*)>', lambda m: '<body' + m.group(1) + '>' + strip, s, count=1)
    if s2 != s:
        open(path, 'w', encoding='utf-8').write(s2)
        count += 1
print('    شريط التنقل في %d صفحة' % count)
PYEOF

echo "== 3ب/4  تهيئة مسار التطبيق (APP_URL) داخل المعاينة =="
python3 - "$ROOT" "$OUT" <<'PYEOF'
import os, re, sys
root, out = sys.argv[1], sys.argv[2]
prefix = ''
for name in ('.env', '.env.example'):
    path = os.path.join(root, name)
    if os.path.exists(path):
        m = re.search(r'^\s*APP_URL\s*=\s*(\S+)', open(path, encoding='utf-8').read(), re.M)
        if m:
            prefix = re.sub(r'^[a-z]+://[^/]*', '', m.group(1)).strip('/')
            break
if not prefix:
    print('    APP_URL بلا مسار فرعي — لا حاجة لتهيئة إضافية')
    raise SystemExit(0)
base = os.path.join(out, prefix)
os.makedirs(base, exist_ok=True)
# الأصول: /prefix/assets/* → assets/*
link = os.path.join(base, 'assets')
if os.path.islink(link) or os.path.exists(link):
    os.remove(link)
os.symlink(os.path.join(out, 'assets'), link)
# مسارات التطبيق: /prefix/admin/questions/ → admin-questions.html
routes = {
 'index.php': 'index.html', 'auth/login.php': 'login.html', 'auth/register.php': 'register.html',
 'questions/index.php': 'questions.html', 'exams/index.php': 'exams.html', 'exams/take.php': 'exam-take.html',
 'student/dashboard.php': 'dashboard.html', 'student/statistics.php': 'statistics.html',
 'subscriptions/plans.php': 'plans.html', 'admin/index.php': 'admin.html',
 'admin/questions.php': 'admin-questions.html', 'admin/import.php': 'admin-import.html',
}
for route, page in routes.items():
    name = route[:-4] if route.endswith('.php') else route
    d = os.path.dirname(name) if os.path.basename(name) == 'index' else name
    full = os.path.join(base, d)
    os.makedirs(full, exist_ok=True)
    target = os.path.join(full, 'index.html')
    if os.path.islink(target) or os.path.exists(target):
        os.remove(target)
    os.symlink(os.path.join(out, page), target)
print('    المسار /%s جاهز: أصول + %d مسار' % (prefix, len(routes)))
PYEOF

if [ "$BUILD_ONLY" = "1" ]; then
    echo "== تم التوليد فقط في: $OUT =="
    exit 0
fi

echo "== 4/4  تشغيل السيرفر على المنفذ $PORT =="
cd "$OUT" || exit 1
exec python3 -m http.server "$PORT" --bind 0.0.0.0 --directory "$OUT"
