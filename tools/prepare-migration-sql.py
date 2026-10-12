#!/usr/bin/env python3
"""
マイグレーションの SQL に埋め込んだテーブル名・列名・インデックス名を %i プレースホルダーに置き換え、
$wpdb->prepare() を通す（WordPress 6.2 以降。wp.org 審査「Unsafe SQL calls」への対応、2026-10）。

対象:
  $sql = "ALTER TABLE `{$table}` ADD COLUMN `x` ...";   → $sql = $wpdb->prepare( "ALTER TABLE %i ADD COLUMN `x` ...", $table );
  $wpdb->query( "ALTER TABLE `{$table}` DROP COLUMN `{$col}`" ) → $wpdb->query( $wpdb->prepare( "ALTER TABLE %i DROP COLUMN %i", $table, $col ) )

識別子以外の変数（列定義など）が残る文字列は書き換えずに一覧に出す（手で直す）。
CREATE TABLE（dbDelta に渡すもの）は対象外。

  python3 tools/prepare-migration-sql.py [--dry-run]
"""
import glob
import os
import re
import sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
IDENT = re.compile(r"`\{?\$(\w+)\}?`")
LEFTOVER = re.compile(r"\{?\$\w+")
SQL_HEAD = re.compile(r"^\s*(ALTER|UPDATE|DELETE|INSERT|SELECT|DROP)\b", re.I)


def convert_sql(body):
    """SQL 文字列（PHP のダブルクオート内）を prepare 用に変換。変換できなければ None。"""
    if not SQL_HEAD.match(body):
        return None
    args = []

    def rep(m):
        args.append('$' + m.group(1))
        return '\x00'

    tmp = IDENT.sub(rep, body)
    if not args or LEFTOVER.search(tmp):
        return None
    tmp = tmp.replace('%', '%%').replace('\x00', '%i')
    return tmp, args


def main():
    dry = '--dry-run' in sys.argv
    manual = []
    total = 0
    for path in sorted(glob.glob(os.path.join(ROOT, 'includes/migrations/*.php'))):
        src = open(path, encoding='utf-8').read()
        n = 0

        # 1) $var = "SQL";
        def assign(m):
            nonlocal n
            res = convert_sql(m.group(3))
            if res is None:
                if SQL_HEAD.match(m.group(3)) and '$' in m.group(3):
                    manual.append((os.path.basename(path), m.group(3).strip().split('\n')[0][:90]))
                return m.group(0)
            sql, args = res
            n += 1
            return '%s%s = $wpdb->prepare( "%s", %s );' % (m.group(1), m.group(2), sql, ', '.join(args))

        src = re.sub(r'(^[ \t]*)(\$\w+)\s*=\s*"((?:[^"\\]|\\.)*)";', assign, src, flags=re.M | re.S)

        # 2) $wpdb->query( "SQL" )
        def inline(m):
            nonlocal n
            res = convert_sql(m.group(1))
            if res is None:
                if '$' in m.group(1):
                    manual.append((os.path.basename(path), m.group(1).strip()[:90]))
                return m.group(0)
            sql, args = res
            n += 1
            return '$wpdb->query( $wpdb->prepare( "%s", %s ) )' % (sql, ', '.join(args))

        src = re.sub(r'\$wpdb->query\(\s*"((?:[^"\\]|\\.)*)"\s*\)', inline, src, flags=re.S)

        if n:
            total += n
            print(f'{n:3d}  {os.path.basename(path)}')
            if not dry:
                open(path, 'w', encoding='utf-8').write(src)
    print('converted', total)
    for f, s in manual:
        print('MANUAL', f, s)


if __name__ == '__main__':
    main()
