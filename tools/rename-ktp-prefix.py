#!/usr/bin/env python3
"""
接頭辞 ktp を ktpwp にそろえる（wp.org 審査「接頭辞は 4 文字以上」への対応、2026-10）。

  ktp_xxx   → ktpwp_xxx   （関数・オプション・テーブル・AJAX・nonce・JS 変数・CSS クラス）
  ktpXxx    → ktpwpXxx    （JS の関数名・wp_localize_script のオブジェクト名）
  'ktp-xxx' → 'ktpwp-xxx' （wp_enqueue_* のハンドル名。CSS クラスの ktp- は対象外）

例外:
  JS グローバル ktp_ajax は既存の ktpwp_ajax と形が違う別物なので ktpwp_ajax_vars にする。

DB に残る名前（テーブル・オプション）が変わるため、無料版 → KantanProEX への移行は
EX 側の KTPWP_EX_Free_Data_Import が ktpwp_* を ktp_* に戻して引き継ぐ。
その対応表はこのスクリプトが tools/ktp-prefix-renamed.json に書き出す。

使い方:
  python3 tools/rename-ktp-prefix.py --dry-run   # 件数だけ
  python3 tools/rename-ktp-prefix.py             # 書き換え
"""
import json
import os
import re
import sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SKIP_DIRS = {'.git', 'vendor', 'node_modules', 'lib', 'languages', 'docs'}
EXTS = ('.php', '.js', '.css', '.py', '.sh')
SELF = os.path.abspath(__file__)

RE_AJAX_GLOBAL = re.compile(r'(?<![A-Za-z0-9_])ktp_ajax(?![A-Za-z0-9_])')
RE_SNAKE = re.compile(r'(?<![A-Za-z0-9_])ktp_')
# 直前が "_" の ktp_ は RE_SNAKE では拾えない。フック名・LIKE パターン・コメント中のテーブル名を個別に拾う
# （wp_ajax_ktp_xxx を取りこぼすと JS 側の action 名とずれて AJAX が全滅する）
RE_UNDERSCORE_CTX = re.compile(
    r'(?<=wp_ajax_)ktp_|(?<=wp_ajax_nopriv_)ktp_|(?<=_transient_)ktp_|(?<=_transient_timeout_)ktp_'
    r'|(?<=update_option_)ktp_|(?<=prefix_)ktp_|(?<![A-Za-z0-9])(?<=wp_)ktp_|(?<![A-Za-z0-9])(?<=top_)ktp_'
)
RE_CAMEL = re.compile(r'(?<![A-Za-z0-9_])ktp(?=[A-Z])')
RE_HANDLE_DEF = re.compile(r"wp_(?:enqueue|register)_(?:script|style)\(\s*['\"](ktp-[a-z0-9_-]+)['\"]")


def files():
    for root, dirs, names in os.walk(ROOT):
        dirs[:] = [d for d in dirs if d not in SKIP_DIRS]
        for n in names:
            p = os.path.join(root, n)
            if n.endswith(EXTS) and os.path.abspath(p) != SELF and n != 'ktp-prefix-renamed.json':
                yield p


def main():
    dry = '--dry-run' in sys.argv
    paths = list(files())
    sources = {p: open(p, encoding='utf-8', errors='surrogateescape').read() for p in paths}

    handles = set()
    for p, s in sources.items():
        if p.endswith('.php'):
            handles |= set(RE_HANDLE_DEF.findall(s))
    re_handle = None
    if handles:
        alt = '|'.join(sorted((re.escape(h) for h in handles), key=len, reverse=True))
        re_handle = re.compile(r"(['\"])(" + alt + r")(\1)")

    snake_names = set()
    total = {'ktp_ajax': 0, 'snake': 0, 'camel': 0, 'handle': 0}
    changed = 0
    for p, s in sources.items():
        snake_names |= set(re.findall(r'(?<![A-Za-z0-9_])ktp_[A-Za-z0-9_]+', s))
        n = s
        n, c = RE_AJAX_GLOBAL.subn('ktpwp_ajax_vars', n); total['ktp_ajax'] += c
        n, c = RE_SNAKE.subn('ktpwp_', n); total['snake'] += c
        n, c = RE_UNDERSCORE_CTX.subn('ktpwp_', n); total['underscore_ctx'] = total.get('underscore_ctx', 0) + c
        if p.endswith(('.php', '.js')):
            n, c = RE_CAMEL.subn('ktpwp', n); total['camel'] += c
        if re_handle and p.endswith('.php'):
            n, c = re_handle.subn(lambda m: m.group(1) + 'ktpwp-' + m.group(2)[4:] + m.group(3), n)
            total['handle'] += c
        if n != s:
            changed += 1
            if not dry:
                with open(p, 'w', encoding='utf-8', errors='surrogateescape') as f:
                    f.write(n)

    print(f"files changed: {changed}  {total}  handles: {len(handles)}")
    if not dry:
        out = os.path.join(ROOT, 'tools', 'ktp-prefix-renamed.json')
        with open(out, 'w', encoding='utf-8') as f:
            json.dump(sorted(snake_names), f, ensure_ascii=False, indent=1)
        print(f"wrote {out} ({len(snake_names)} names)")


if __name__ == '__main__':
    main()
