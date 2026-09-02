#!/usr/bin/env python3
"""印刷用の大きな HEREDOC を ob_start() へ機械的に置き換える。

なぜファイルに切り出さないのか:
  印刷まわりは端末（PC / iPad / iPhone）ごとに挙動が違い、CLAUDE.md でも
  「ユーザー確認済みで凍結」とされている。実機テストは本番サイトに入れるまで
  できないので、**出力される JavaScript を1バイトも変えない**方法を採る。
  ob_start() + ob_get_clean() は wp.org のレビュー本文が長いブロックの代替として
  明示的に挙げている方法でもある。

やること:
  $var = <<<TAG
  ...本文...
  TAG;
  →
  ob_start();
  ?>
  ...本文（{$x} は <?php echo $x; ?> に置換）...
  <?php
  $var = ob_get_clean();

使い方: heredoc-to-obstart.py <ファイル> <変数名> [--dry-run]
  変数名は代入先（例: print）。同じファイルに複数あるときは先頭の1件を処理する。
"""
import re
import sys


def convert(path, varname, dry=False):
    src = open(path, encoding='utf-8').read()
    start = src.find(f'${varname} = <<<')
    if start < 0:
        print(f'{path}: ${varname} の HEREDOC が見つかりません', file=sys.stderr)
        return 1

    tag_m = re.match(r'\$\w+ = <<<(\w+)\n', src[start:])
    if not tag_m:
        print(f'{path}: HEREDOC の開始を解釈できません', file=sys.stderr)
        return 1
    tag = tag_m.group(1)

    end_m = re.compile(r'^\s*' + tag + r';\s*$', re.M).search(src, start)
    if not end_m:
        print(f'{path}: 終端 {tag}; が見つかりません', file=sys.stderr)
        return 1

    block = src[start:end_m.end()]
    body = block.split('\n', 1)[1]
    body = re.sub(r'\n\s*' + tag + r';\s*$', '', body)

    # 埋め込み変数を PHP の echo に変える。
    # {$x} と $x の両方の書き方があるので、長いほうから処理する。
    used = sorted(set(re.findall(r'\{\$([a-zA-Z_]\w*)\}|\$([a-zA-Z_]\w*)', body)),
                  key=lambda t: -len(t[0] or t[1]))
    names = []
    for a, b in used:
        n = a or b
        if n not in names:
            names.append(n)
    # **1回のパスで置換すること。** 名前ごとに順に replace すると、先に挿入した
    # `<?php echo $x; ?>` の中の `$x` を次の名前の処理が再び拾って
    # `<?php echo <?php echo $x; ?>; ?>` になる（2026-09-03 に実際に壊した）。
    if names:
        alt = '|'.join(sorted(names, key=len, reverse=True))
        one_pass = re.compile(r'\{\$(' + alt + r')\}|(?<![\w{])\$(' + alt + r')(?![\w}])')
        body = one_pass.sub(lambda m: '<?php echo $' + (m.group(1) or m.group(2)) + '; ?>', body)

    indent = re.match(r'[ \t]*', src[src.rfind('\n', 0, start) + 1:start]).group(0)
    new = (f'{indent}// HEREDOC は使わない（wp.org ガイドライン）。出力される JS は変更していない。\n'
           f'{indent}ob_start();\n'
           f'{indent}?>\n'
           + body + '\n'
           + f'{indent}<?php\n'
           + f'{indent}${varname} = ob_get_clean();')

    out = src[:start] + new.lstrip() + src[end_m.end():]
    if dry:
        print(f'{path}: ${varname} ({body.count(chr(10))+1}行) 埋め込み変数={names}')
        return 0
    open(path, 'w', encoding='utf-8').write(out)
    print(f'{path}: ${varname} を ob_start() へ変換（{body.count(chr(10))+1}行、変数={names}）')
    return 0


if __name__ == '__main__':
    args = [a for a in sys.argv[1:] if not a.startswith('--')]
    if len(args) < 2:
        print(__doc__)
        sys.exit(2)
    sys.exit(convert(args[0], args[1], '--dry-run' in sys.argv))
