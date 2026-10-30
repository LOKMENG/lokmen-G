// مشغّل php-wasm: node run.mjs --root <مسار المشروع> <script.php> [args...]
// يشغّل سكربتات PHP في بيئات لا تحتوي ملف php تنفيذياً (مثل هذه المساحة الرملية).
// أداة تطوير فقط — مستثناة من حزم النشر (tools/dev).
import { PhpNode } from 'php-wasm/PhpNode.mjs';
import { readdirSync, statSync, readFileSync, writeFileSync, mkdirSync } from 'node:fs';
import { join } from 'node:path';

const argv = process.argv.slice(2);
let root = process.cwd();
const rest = [];
for (let i = 0; i < argv.length; i++) {
  if (argv[i] === '--root' && argv[i + 1]) { root = argv[++i]; continue; }
  rest.push(argv[i]);
}
if (!rest.length) {
  console.error('الاستخدام: node run.mjs [--root /path/to/project] <script.php> [args...]');
  process.exit(2);
}
const script = rest[0];
const args = rest.slice(1);

const php = new PhpNode({ root: '/host' });

// اجمع مخرجات PHP: المكتبة ترسلها كأحداث 'output' / 'error'
let stdoutText = '';
let stderrText = '';
php.addEventListener('output', e => { stdoutText += (e.detail ? e.detail.join('') : ''); });
php.addEventListener('error', e => { stderrText += (e.detail ? e.detail.join('') : ''); });

// انسخ المشروع إلى /proj (php-wasm يكتب فقط داخل مجلده)
const SKIP = new Set(['.git', 'node_modules', 'vendor']);
function copyTree(from, to) {
  php.mkdir(to);
  for (const name of readdirSync(from)) {
    if (SKIP.has(name)) continue;
    const src = join(from, name);
    const dst = to + '/' + name;
    if (statSync(src).isDirectory()) copyTree(src, dst);
    else php.writeFile(dst, readFileSync(src));
  }
}
copyTree(root, '/proj');

const out = await php.run(`<?php
  define('LOKMEN_WASM_RUNNER', true);
  chdir('/proj');
  if (!function_exists('mb_internal_encoding')) {
    function mb_internal_encoding($e = null) { return 'UTF-8'; }
  }
  if (!function_exists('mb_strlen')) {
    function mb_strlen($s, $e = null) { return preg_match_all('/./us', (string)$s); }
  }
  if (!function_exists('mb_substr')) {
    function mb_substr($s, $start, $length = null, $e = null) {
      $parts = preg_split('//u', (string)$s, -1, PREG_SPLIT_NO_EMPTY);
      return implode('', array_slice($parts, $start, $length === null ? null : (int)$length));
    }
  }
  if (!function_exists('mb_strtolower')) {
    function mb_strtolower($s, $e = null) { return strtolower((string)$s); }
  }
  if (!function_exists('mb_strtoupper')) {
    function mb_strtoupper($s, $e = null) { return strtoupper((string)$s); }
  }
  if (!function_exists('mb_strpos')) {
    function mb_strpos($h, $n, $o = 0, $e = null) { return strpos((string)$h, (string)$n, (int)$o); }
  }
  if (!function_exists('mb_str_split')) {
    function mb_str_split($s, $len = 1) {
      $s = (string)$s; $len = max(1, (int)$len); $out = [];
      $parts = preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY);
      for ($i = 0; $i < count($parts); $i += $len) { $out[] = implode('', array_slice($parts, $i, $len)); }
      return $out;
    }
  }
  if (!function_exists('mb_strimwidth')) {
    function mb_strimwidth($s, $start, $width, $trim = '', $e = null) {
      $parts = preg_split('//u', (string)$s, -1, PREG_SPLIT_NO_EMPTY);
      $slice = array_slice($parts, (int)$start, (int)$width);
      return implode('', $slice) . (count($parts) > ((int)$start + (int)$width) ? $trim : '');
    }
  }
  if (!function_exists('mb_convert_encoding')) {
    function mb_convert_encoding($s, $to, $from = null) { return $s; }
  }
  if (!function_exists('mb_detect_encoding')) {
    function mb_detect_encoding($s, $list = null, $strict = false) { return 'UTF-8'; }
  }
  if (!function_exists('getallheaders')) {
    function getallheaders() {
      $out = [];
      foreach ($_SERVER as $k => $v) {
        if (strpos($k, 'HTTP_') === 0) {
          $out[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($k, 5)))))] = $v;
        }
      }
      return $out;
    }
  }

  $_SERVER['argv'] = array_merge(['${script}'], ${JSON.stringify(args)});
  $_SERVER['argc'] = count($_SERVER['argv']);
  $argv = $_SERVER['argv'];
  $GLOBALS['argv'] = $_SERVER['argv'];
  $_SERVER['REQUEST_METHOD'] = 'CLI';
  $_SERVER['HTTP_HOST'] = 'localhost';
  $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
  $_SERVER['SCRIPT_NAME'] = '/${script}';
  $_SERVER['PHP_SELF'] = '/${script}';

  require '/proj/${script}';
`);

// انسخ ما تكتبه السكربتات داخل /proj/storage إلى نظيره الحقيقي
for (const sub of ['import', 'build', 'tmp']) {
  const src = '/proj/storage/' + sub;
  let listing = null;
  try { listing = await php.readdir(src); } catch (e) { /* غير موجود */ }
  if (listing && listing.length) {
    await copyTreeOut(src, join(root, 'storage', sub));
  }
}

for (const a of args) {
  const m = /^--out=(.+)$/.exec(a);
  if (!m) continue;
  const target = m[1].startsWith('/host') ? m[1].replace(/^\/host/, root) : m[1];
  try {
    const files = await php.readdir('/proj/storage/tmp');
    if (files && files.length) {
      mkdirSync(target, { recursive: true });
      for (const f of files) {
        await copyTreeOut('/proj/storage/tmp/' + f, join(target, f));
      }
    }
  } catch (e) { /* لا ملفات */ }
}

async function copyTreeOut(src, dst) {
  let listing = null;
  try {
    const l = await php.readdir(src);
    listing = Array.isArray(l) ? l.filter(n => n !== '.' && n !== '..') : null;
  } catch (e) { listing = null; }
  try {
    if (listing === null || listing.length === 0) {
      mkdirSync(join(dst, '..'), { recursive: true });
      const data = await php.readFile(src);
      writeFileSync(dst, Buffer.from(data));
      return;
    }
    mkdirSync(dst, { recursive: true });
    for (const name of listing) {
      await copyTreeOut(src + '/' + name, join(dst, name));
    }
  } catch (e) {
    try {
      mkdirSync(join(dst, '..'), { recursive: true });
      const data = await php.readFile(src);
      writeFileSync(dst, Buffer.from(data));
    } catch (e2) { /* تجاهل */ }
  }
}

if (stderrText.trim() !== '') process.stderr.write(stderrText.replace(/\/proj\//g, root + '/'));
process.stdout.write(stdoutText.replace(/\/proj\//g, root + '/'));
process.exit(Number(out) || 0);
