#!/usr/bin/env python3
"""
$_GET の直接読み取りを KTPWP_Request 経由に置き換える（wp.org 審査の nonce / サニタイズ指摘への対応、2026-10）。

一覧の並べ替え・ページ送り・検索語・表示中レコード ID など、読み取り専用の画面移動用引数を
1 か所（includes/class-ktpwp-request.php）で読み、必ずサニタイズしてから返す。
$_GET へ代入している行は書き換えない。

  python3 tools/convert-get-access.py --dry-run
  python3 tools/convert-get-access.py
"""
import os
import re
import sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SKIP_DIRS = {'.git', 'vendor', 'node_modules', 'tools', 'languages'}
SKIP_FILES = {'class-ktpwp-request.php'}

K = r"\$_GET\[\s*(['\"])([A-Za-z0-9_\-]+)\1\s*\]"


def q(name):
    return "KTPWP_Request::query( '%s' )" % name


RULES = [
    (re.compile(r"sanitize_text_field\(\s*wp_unslash\(\s*" + K + r"\s*\)\s*\)"), lambda m: q(m.group(2))),
    (re.compile(r"sanitize_text_field\(\s*" + K + r"\s*\)"), lambda m: q(m.group(2))),
    (re.compile(r"sanitize_key\(\s*wp_unslash\(\s*" + K + r"\s*\)\s*\)"), lambda m: "sanitize_key( %s )" % q(m.group(2))),
    (re.compile(r"absint\(\s*wp_unslash\(\s*" + K + r"\s*\)\s*\)"), lambda m: "absint( %s )" % q(m.group(2))),
    (re.compile(r"filter_input\(\s*INPUT_GET\s*,\s*(['\"])([A-Za-z0-9_\-]+)\1\s*,\s*FILTER_SANITIZE_NUMBER_INT\s*\)"),
     lambda m: "KTPWP_Request::query_number( '%s' )" % m.group(2)),
    (re.compile(r"wp_unslash\(\s*" + K + r"\s*\)"), lambda m: "KTPWP_Request::query_unslashed( '%s' )" % m.group(2)),
    (re.compile(r"isset\(\s*" + K + r"\s*\)"), lambda m: "KTPWP_Request::has_query( '%s' )" % m.group(2)),
    (re.compile(r"empty\(\s*" + K + r"\s*\)"), lambda m: "empty( %s )" % q(m.group(2))),
]
RE_ASSIGN = re.compile(K + r"\s*=(?!=)")
RE_LEFT = re.compile(K)


def convert_line(line):
    if RE_ASSIGN.search(line):
        return line, 0
    n = 0
    for rx, fn in RULES:
        line, c = rx.subn(fn, line)
        n += c
    line, c = RE_LEFT.subn(lambda m: "KTPWP_Request::query_unslashed( '%s' )" % m.group(2), line)
    return line, n + c


def main():
    dry = '--dry-run' in sys.argv
    total = 0
    for root, dirs, names in os.walk(ROOT):
        dirs[:] = [d for d in dirs if d not in SKIP_DIRS]
        for name in names:
            if not name.endswith('.php') or name in SKIP_FILES:
                continue
            path = os.path.join(root, name)
            src = open(path, encoding='utf-8', errors='surrogateescape').read()
            out_lines = []
            changed = 0
            for line in src.split('\n'):
                new, c = convert_line(line)
                changed += c
                out_lines.append(new)
            if changed:
                total += changed
                print(f"{changed:4d}  {os.path.relpath(path, ROOT)}")
                if not dry:
                    with open(path, 'w', encoding='utf-8', errors='surrogateescape') as f:
                        f.write('\n'.join(out_lines))
    print('total', total)


if __name__ == '__main__':
    main()
