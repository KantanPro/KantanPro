#!/usr/bin/env python3
"""$wpdb->get_*( $var ) / query( $var ) の $var が直前で prepare 済みかを分類する（2026-10、SQL 指摘の棚卸し用）。"""
import os, re, sys
ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
CALL = re.compile(r'\$wpdb->(get_results|get_var|get_row|get_col|query)\(\s*(\$\w+)\s*[,)]')
skip = {'vendor', '.git', 'tools', 'node_modules'}
out = {'prepared': [], 'raw': [], 'unknown': []}
for root, dirs, files in os.walk(ROOT):
    dirs[:] = [d for d in dirs if d not in skip]
    for f in files:
        if not f.endswith('.php'):
            continue
        p = os.path.join(root, f)
        lines = open(p, encoding='utf-8', errors='ignore').read().split('\n')
        for i, l in enumerate(lines):
            m = CALL.search(l)
            if not m or 'phpcs:ignore' in l:
                continue
            var = m.group(2)
            kind = 'unknown'
            for j in range(i - 1, max(-1, i - 80), -1):
                a = re.search(re.escape(var) + r'\s*=\s*(.*)', lines[j])
                if a and not re.search(re.escape(var) + r'\s*==', lines[j]):
                    rhs = a.group(1)
                    kind = 'prepared' if rhs.lstrip().startswith('$wpdb->prepare(') else 'raw'
                    break
            out[kind].append((os.path.relpath(p, ROOT), i + 1, var, l.strip()[:110]))
for k, v in out.items():
    print('##', k, len(v))
    if k != 'prepared' or '-v' in sys.argv:
        for x in v:
            print('  ', *x)
