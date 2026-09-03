#!/usr/bin/env python3
"""ビルド成果物に「自前の関数を消したのに残っている呼び出し」が無いか調べる。

死んだ関数を消したときに呼び出し元が残ると、PHP 8 では即 Fatal error になる。
2026-09-03 に check_activation_key() でこれを踏んだ。add_action の文字列
コールバックだけを見ていて、直接呼び出しを見落としたのが原因。

判定に使う「既知の関数」:
  * PHP 本体の組み込み関数（php -r で列挙）
  * WordPress 本体の関数（コンテナの wp-includes / wp-admin から抽出）
  * プラグイン自身が定義している関数

検査するのは ktp / kantan で始まる関数だけ。これはノイズを避けるための割り切りで、
接頭辞の無い関数（check_activation_key など）は捕まらない。
**この検査だけで安心しないこと。** 最後は必ずテストサイトの全タブを開いて
Fatal error が出ないことを目で確かめる。

使い方:
  tools/check-undefined-functions.py <プラグインのディレクトリ>
"""
import os
import re
import subprocess
import sys

CONTAINER = 'KantanProFree_wordpress'

# 制御構文や言語構造は関数呼び出しに見えるが関数ではない
KEYWORDS = {
    'if', 'elseif', 'while', 'for', 'foreach', 'switch', 'catch', 'return', 'echo',
    'print', 'array', 'list', 'isset', 'unset', 'empty', 'exit', 'die', 'include',
    'include_once', 'require', 'require_once', 'function', 'fn', 'match', 'new',
    'clone', 'yield', 'throw', 'use', 'and', 'or', 'xor', 'declare', 'static',
    'class', 'int', 'float', 'string', 'bool', 'object', 'self', 'parent',
}


def php_builtins():
    out = subprocess.run(
        ['php', '-r', 'foreach(get_defined_functions()["internal"] as $f) echo $f,"\\n";'],
        capture_output=True, text=True)
    return set(out.stdout.split())


def wp_functions():
    """コンテナの WordPress 本体から関数名を拾う。取れなければ空集合。"""
    cmd = (r"grep -rhoE '^[[:space:]]*function[[:space:]]+[A-Za-z_][A-Za-z0-9_]*' "
           r"/var/www/html/wp-includes /var/www/html/wp-admin 2>/dev/null")
    out = subprocess.run(['docker', 'exec', CONTAINER, 'sh', '-c', cmd],
                         capture_output=True, text=True)
    return {ln.split()[-1] for ln in out.stdout.splitlines() if ln.strip()}


def main():
    root = sys.argv[1] if len(sys.argv) > 1 else '.'
    src = {}
    for dp, dns, fns in os.walk(root):
        if any(x in dp for x in ('/.git', '/node_modules', '/vendor', '/tools')):
            continue
        for fn in fns:
            if fn.endswith('.php'):
                p = os.path.join(dp, fn)
                src[p] = open(p, encoding='utf-8', errors='replace').read()

    blob = '\n'.join(src.values())
    defined = set(re.findall(r'function\s+&?\s*([A-Za-z_]\w*)\s*\(', blob))
    known = defined | php_builtins() | wp_functions() | KEYWORDS

    # 呼び出しに見えるもの。-> や :: や $ が直前にあるものはメソッドなので除く。
    # new Foo( や catch (Foo $e) はクラスなので、クラス名も既知に入れる。
    known |= set(re.findall(r'\b(?:class|interface|trait)\s+([A-Za-z_]\w*)', blob))
    known |= set(re.findall(r'\bnew\s+([A-Za-z_]\w*)', blob))
    call = re.compile(r'(?<![\w$>:])(?<!new )([A-Za-z_]\w*)\s*\(')
    bad = {}
    for p, s in src.items():
        # コメントと文字列リテラルは中を見ない。
        # ここを削らないと SQL の COUNT( や DECIMAL( を関数呼び出しとして拾う。
        s2 = re.sub(r'/\*.*?\*/', '', s, flags=re.S)
        s2 = re.sub(r'(?m)//.*$', '', s2)
        s2 = re.sub(r'(?m)#(?!\[).*$', '', s2)
        # 中身は消すが改行は残す。そうしないと報告する行番号がずれる。
        keep_nl = lambda m: '\n' * m.group(0).count('\n')
        s2 = re.sub(r'<<<[\'"]?(\w+)[\'"]?\n.*?\n\s*\1', keep_nl, s2, flags=re.S)
        s2 = re.sub(r'"(?:[^"\\\\]|\\\\.)*"', keep_nl, s2, flags=re.S)
        s2 = re.sub(r"'(?:[^'\\\\]|\\\\.)*'", keep_nl, s2, flags=re.S)
        for m in call.finditer(s2):
            name = m.group(1)
            if name in known or name.lower() in KEYWORDS:
                continue
            # このプラグイン自身の関数だけを見る。
            # 対象を広げると、PHP ファイルに埋め込まれた JS（alert / confirm）や
            # CSS（gradient）、翻訳文字列の英文まで拾ってノイズに埋もれる。
            # 消したい事故は「自前の関数を消したのに呼び出し元が残る」なので、
            # これで十分に捕まる。取りこぼしは実画面のスモークテストで拾う。
            if not re.match(r'(ktp|kantan)', name):
                continue
            ln = s2[:m.start()].count('\n') + 1
            bad.setdefault(name, []).append(f'{p.lstrip("./")}:{ln}')

    if not bad:
        print('未定義の関数呼び出しは見つかりませんでした。')
        return 0
    print(f'未定義かもしれない関数呼び出し: {len(bad)}件')
    for name, where in sorted(bad.items()):
        print(f'  {name}()  ← {where[0]}' + (f' 他{len(where)-1}件' if len(where) > 1 else ''))
    return 1


if __name__ == '__main__':
    sys.exit(main())
