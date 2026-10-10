#!/usr/bin/env python3
"""أداة تطوير: التحقق من صحة صياغة database.sql (MySQL/MariaDB) عبر sqlglot + فحوص إضافية.
   Dev tool: validate the SQL schema syntax using sqlglot (MySQL dialect).
   Usage: python3 tools/dev/validate_sql.py [path/to/database.sql]
   Requires: pip install sqlglot
"""
import re
import sys
import pathlib

try:
    import sqlglot
    from sqlglot.errors import ParseError
except ImportError:
    print("sqlglot غير مثبت: pip install sqlglot")
    sys.exit(2)

path = pathlib.Path(sys.argv[1] if len(sys.argv) > 1 else "database.sql")
raw = path.read_text(encoding="utf-8")

# تقسيم الجمل مع تجاهل الفواصل داخل النصوص والتعليقات
def split_statements(sql: str):
    stmts, buf, in_str, quote, i, n = [], [], None, "", 0, len(sql)
    while i < n:
        ch = sql[i]
        if in_str:
            buf.append(ch)
            if ch == "\\":
                if i + 1 < n:
                    buf.append(sql[i + 1]); i += 1
            elif ch == quote:
                if i + 1 < n and sql[i + 1] == quote:
                    buf.append(sql[i + 1]); i += 1
                else:
                    in_str = None
        elif ch in ("'", '"', "`"):
            in_str, quote = "s", ch
            buf.append(ch)
        elif ch == "-" and i + 1 < n and sql[i + 1] == "-":
            j = sql.find("\n", i)
            i = j if j != -1 else n
            continue
        elif ch == ";":
            stmts.append("".join(buf).strip()); buf = []
        else:
            buf.append(ch)
        i += 1
    if "".join(buf).strip():
        stmts.append("".join(buf).strip())
    return [s for s in stmts if s]

statements = split_statements(raw)
errors, warnings, ok = [], [], 0
for stmt in statements:
    head = stmt.split(None, 2)[:2]
    keyword = " ".join(head).upper()
    if keyword.startswith(("SET ", "USE ")) or keyword == "SET":
        continue  # جمل إعداد لا تغطيها sqlglot بالكامل
    try:
        sqlglot.parse_one(stmt, dialect="mysql")
        ok += 1
    except ParseError as exc:
        errors.append((stmt.split("\n")[0][:90], str(exc).split("\n")[0]))
    except Exception as exc:  # pragma: no cover
        warnings.append((stmt.split("\n")[0][:90], str(exc)[:120]))

print(f"الملف: {path}  ({len(raw.splitlines())} سطراً)")
print(f"عدد الجمل: {len(statements)}   صحيحة نحوياً: {ok}   أخطاء: {len(errors)}   ملاحظات: {len(warnings)}")

create_tables = re.findall(r"CREATE TABLE `(\w+)`", raw)
create_views = re.findall(r"CREATE VIEW `(\w+)`", raw)
print(f"الجداول ({len(create_tables)}): {', '.join(create_tables)}")
print(f"العروض ({len(create_views)}): {', '.join(create_views)}")

issues = []
if "utf8mb4" not in raw:
    issues.append("لا يوجد utf8mb4")
if raw.count("ENGINE=InnoDB") < len(create_tables):
    issues.append("بعض الجداول ليست InnoDB")
for tbl in create_tables:
    body = raw.split(f"CREATE TABLE `{tbl}`", 1)[1].split(";", 1)[0]
    if "PRIMARY KEY" not in body:
        issues.append(f"الجدول {tbl} بدون مفتاح أساسي")
    for fk in re.finditer(r"FOREIGN KEY \(`(\w+)`\) REFERENCES `(\w+)`", body):
        col, ref = fk.group(1), fk.group(2)
        if ref not in create_tables:
            issues.append(f"FK في {tbl} يشير إلى جدول غير موجود: {ref}")
        if col not in body:
            issues.append(f"عمود FK مفقود: {tbl}.{col}")

# التحقق من ترتيب الإنشاء مقارنة بمراجع المفاتيح
order = {t: i for i, t in enumerate(create_tables)}
for tbl in create_tables:
    body = raw.split(f"CREATE TABLE `{tbl}`", 1)[1].split(";", 1)[0]
    for fk in re.finditer(r"FOREIGN KEY \(`\w+`\) REFERENCES `(\w+)`", body):
        ref = fk.group(1)
        if ref in order and order[ref] > order[tbl]:
            warnings.append((tbl, f"يشير إلى {ref} المُنشأ بعده (مقبول عند تعطيل فحص المفاتيح)"))

for e in errors:
    print("  ✗ خطأ:", e[0], "→", e[1])
for w in warnings[:10]:
    print("  ! ملاحظة:", w[0], "→", w[1])
for i in issues:
    print("  ! تحذير:", i)
print("النتيجة:", "ناجح ✔" if not errors and not issues else "يحتاج مراجعة ✘")
sys.exit(1 if (errors or issues) else 0)
