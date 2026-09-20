#!/usr/bin/env python3
"""$_SERVER['REQUEST_METHOD'] の直読みを ktpwp_request_method() に置き換える。

WordPress.org のレビューは $_SERVER も入力として扱うよう求めている。
比較のためだけに使っている箇所でも、未サニタイズだと Plugin Check の
ValidatedSanitizedInput.InputNotSanitized / MissingUnslash が出る。

ヘルパーは ktpwp.php に定義済み（サニタイズ + 大文字化して返す）。
"""
import re, sys, glob, os

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
PATTERNS = [
    # $_SERVER['REQUEST_METHOD'] === 'POST' / !== 'POST' など
    (re.compile(r"\$_SERVER\[\s*'REQUEST_METHOD'\s*\]\s*(===|!==|==|!=)\s*'(GET|POST|PUT|DELETE|HEAD|PATCH|OPTIONS)'"),
     lambda m: f"ktpwp_request_method() {m.group(1)} '{m.group(2)}'"),
    # 'POST' !== strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? '' ) )
    (re.compile(r"'(GET|POST)'\s*(===|!==)\s*strtoupper\(\s*\(string\)\s*\(\s*\$_SERVER\[\s*'REQUEST_METHOD'\s*\]\s*\?\?\s*''\s*\)\s*\)"),
     lambda m: f"'{m.group(1)}' {m.group(2)} ktpwp_request_method()"),
    # isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : 'NOT_SET'
    (re.compile(r"isset\(\s*\$_SERVER\[\s*'REQUEST_METHOD'\s*\]\s*\)\s*\?\s*\$_SERVER\[\s*'REQUEST_METHOD'\s*\]\s*:\s*'NOT_SET'"),
     lambda m: "ktpwp_request_method( 'NOT_SET' )"),
]

total = 0
for path in glob.glob(os.path.join(ROOT, '**', '*.php'), recursive=True):
    rel = os.path.relpath(path, ROOT)
    if rel.startswith('tools/'):
        continue
    src = open(path, encoding='utf-8').read()
    new = src
    n = 0
    for rx, rep in PATTERNS:
        new, k = rx.subn(rep, new)
        n += k
    if n:
        open(path, 'w', encoding='utf-8').write(new)
        print(f"  {rel}: {n} 件")
        total += n
print(f"置換合計: {total} 件")
