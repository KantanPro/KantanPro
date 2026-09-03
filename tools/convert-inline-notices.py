#!/usr/bin/env python3
"""通知用のインライン <script> を ktpwp_add_inline_notice() に置き換える。

対象は次の形（インデントや改行位置は多少揺れる）:

    echo '<script>
    document.addEventListener("DOMContentLoaded", function() {
        showErrorNotification("メッセージ");
    });
    </script>';

これを

    ktpwp_add_inline_notice( 'error', 'メッセージ' );

にする。リダイレクト付き（setTimeout + location.href）も拾う。

WordPress.org は生の <script> の出力を認めていないため（2026-09-02 のレビュー指摘）、
出力を1箇所に集約する。メッセージは wp_json_encode() で JS リテラル化されるので、
従来の esc_js より壊れにくい。

使い方:
  convert-inline-notices.py --dry-run
  convert-inline-notices.py
"""
import os
import re
import sys

FN = {
    'showErrorNotification': 'error',
    'showSuccessNotification': 'success',
    'showInfoNotification': 'info',
}

# echo '<script> … </script>'; を丸ごと拾う
BLOCK = re.compile(
    r"(?P<indent>[ \t]*)echo\s*'<script>\s*\n"
    r"(?P<body>.*?)"
    r"</script>';",
    re.S,
)

CALL = re.compile(
    r"(?P<fn>show(?:Error|Success|Info)Notification)\(\s*(?P<arg>.*?)\s*\);",
    re.S,
)

REDIRECT = re.compile(
    r"window\.location\.href\s*=\s*(?P<url>.*?);",
    re.S,
)

DELAY = re.compile(r"\}\s*,\s*(?P<ms>\d+)\s*\)")


def strip_esc_js(expr):
    """esc_js( X ) を X に戻す。

    置き換え先の ktpwp_add_inline_notice() は wp_json_encode() で JS リテラル化するため、
    esc_js を残すと二重エスケープになる（\\' が \\\\' になる）。
    """
    out = expr
    while True:
        i = out.find('esc_js(')
        if i < 0:
            return out
        j = i + len('esc_js(')
        depth = 1
        while j < len(out) and depth:
            if out[j] == '(':
                depth += 1
            elif out[j] == ')':
                depth -= 1
            j += 1
        out = out[:i] + out[i + len('esc_js('):j - 1].strip() + out[j:]


def php_arg(js_literal):
    """JS の引数表現を PHP の式に直す。

    引数は「PHP が組み立てた JS の二重引用符文字列」なので、
    **両端の " を ' に替えるだけで PHP の連結式として成立する**。
      "文字列"                          → '文字列'
      "text: ' . $x . '"                → 'text: ' . $x . ''
    末尾に余る . '' は無害なので落とさない（無理に削ると壊れる）。
    """
    t = js_literal.strip()
    if len(t) >= 2 and t[0] == '"' and t[-1] == '"':
        t = "'" + t[1:-1] + "'"
    return strip_esc_js(t).strip()


def convert(text):
    out = []
    pos = 0
    count = 0
    for m in BLOCK.finditer(text):
        body = m.group('body')
        call = CALL.search(body)
        if not call or call.group('fn') not in FN:
            continue  # 通知以外はここでは触らない

        kind = FN[call.group('fn')]
        arg = php_arg(call.group('arg'))

        red = REDIRECT.search(body)
        parts = [f"'{kind}'", arg]
        if red:
            parts.append(php_arg(red.group('url')))
            d = DELAY.search(body)
            parts.append(d.group('ms') if d else '1000')

        repl = m.group('indent') + 'ktpwp_add_inline_notice( ' + ', '.join(parts) + ' );'
        out.append(text[pos:m.start()])
        out.append(repl)
        pos = m.end()
        count += 1
    out.append(text[pos:])
    return ''.join(out), count


def main():
    dry = '--dry-run' in sys.argv
    total = {}
    for dp, dns, fns in os.walk('.'):
        if any(x in dp for x in ('/vendor', '/node_modules', '/tools', '/.git')):
            continue
        for fn in fns:
            if not fn.endswith('.php'):
                continue
            p = os.path.join(dp, fn)
            src = open(p, encoding='utf-8', errors='replace').read()
            new, n = convert(src)
            if n:
                total[p] = n
                if not dry:
                    open(p, 'w', encoding='utf-8').write(new)
    for p, n in sorted(total.items(), key=lambda x: -x[1]):
        print(f'{p:<52} {n}件')
    print(('[dry-run] ' if dry else '') + f'計 {sum(total.values())}件 / {len(total)}ファイル')
    return 0


if __name__ == '__main__':
    sys.exit(main())
