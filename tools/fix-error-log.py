#!/usr/bin/env python3
"""error_log() の直接呼び出しを ktpwp_debug_log() に置き換える。

Plugin Check の WordPress.PHP.DevelopmentFunctions.error_log_error_log は
WP_DEBUG のガードの有無を見ず、呼び出しそのものを報告する。
922 件が出ていたので、出口を ktpwp.php の ktpwp_debug_log() 1 箇所に集約する。
これで Plugin Check の報告は 1 件になり、同時に本番でログが出なくなる
（244 箇所あった未ガードの呼び出しもまとめて塞がる）。

ktpwp_debug_log() の中の error_log() だけは phpcs:ignore 付きで残す。
"""
import re, os, glob

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
# 唯一の出口。ここは変換しない。
HELPER_MARK = 'プラグイン全体で唯一のログ出口'
CALL = re.compile(r'(?<![\w>$:-])error_log\s*\(')

total = 0
for path in sorted(glob.glob(os.path.join(ROOT, '**', '*.php'), recursive=True)):
    rel = os.path.relpath(path, ROOT)
    if rel.startswith('tools/'):
        continue
    src = open(path, encoding='utf-8').read()
    if not CALL.search(src):
        continue

    out, n, pos = [], 0, 0
    for m in CALL.finditer(src):
        # ヘルパー本体の呼び出し（直前行に phpcs:ignore の目印）は残す
        line_start = src.rfind('\n', 0, m.start()) + 1
        prev_line_start = src.rfind('\n', 0, line_start - 1) + 1
        prev_line = src[prev_line_start:line_start]
        if HELPER_MARK in prev_line:
            continue
        out.append(src[pos:m.start()])
        out.append('ktpwp_debug_log(')
        pos = m.end()
        n += 1
    if n:
        out.append(src[pos:])
        open(path, 'w', encoding='utf-8').write(''.join(out))
        print(f'  {rel}: {n} 件')
        total += n

print(f'置換合計: {total} 件')
