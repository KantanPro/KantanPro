#!/usr/bin/env python3
"""
PHP メソッドが nowdoc（<<<'JS' / <<<'CSS'）で返していた JS/CSS を静的ファイルへ切り出す（2026-10、wp.org 審査対応）。

wp_add_inline_script()/wp_add_inline_style() にメソッドの戻り値を渡すと「エスケープなしの出力」と判定され、
HEREDOC/nowdoc 自体もガイドライン違反として指摘されている。

nowdoc の中に <?php ... ?> を書いていた箇所は PHP として評価されず、そのまま文字列として出力されていた
（利用規約ダイアログの AJAX URL / nonce、フッターの利用規約リンク）。切り出し時にデータ変数の参照へ置き換える。

  python3 tools/extract-nowdoc-assets.py
"""
import os
import re

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))

JOBS = [
    ('includes/class-ktpwp-assets.php', 'get_console_silencer_js', 'js/ktp-console-silencer.js', {}),
    ('includes/class-ktpwp-svg-icons.php', 'get_styles', 'css/ktp-svg-icons.css', {}),
    ('includes/class-ktpwp-terms-of-service.php', 'get_terms_footer_link_js', 'js/ktp-terms-footer-link.js', {
        "'<?php echo esc_url( $terms_url ); ?>'": "( window.ktpwpTermsFooterLink && window.ktpwpTermsFooterLink.url ) || ''",
        "'<?php echo esc_js( __( '利用規約', 'kantanpro' ) ); ?>'": "( window.ktpwpTermsFooterLink && window.ktpwpTermsFooterLink.label ) || ''",
    }),
    ('includes/class-ktpwp-terms-of-service.php', 'get_terms_dialog_css', 'css/ktp-terms-dialog.css', {}),
    ('includes/class-ktpwp-terms-of-service.php', 'get_terms_dialog_js', 'js/ktp-terms-dialog.js', {
        "'<?php echo admin_url( 'admin-ajax.php' ); ?>'": "( window.ktpwpTermsDialog && window.ktpwpTermsDialog.ajaxUrl ) || ''",
        "'<?php echo wp_create_nonce( 'ktpwp_terms_agreement' ); ?>'": "( window.ktpwpTermsDialog && window.ktpwpTermsDialog.nonce ) || ''",
        "'<?php echo esc_js( __( 'エラーが発生しました。', 'kantanpro' ) ); ?>'": "( window.ktpwpTermsDialog && window.ktpwpTermsDialog.errorMessage ) || ''",
    }),
    ('includes/class-ktpwp-settings.php', 'get_donation_notice_preview_js', 'js/ktp-donation-notice-preview.js', {}),
    ('includes/class-ktpwp-settings.php', 'get_donation_notice_preview_css', 'css/ktp-donation-notice-preview.css', {}),
    ('includes/class-ktpwp-settings.php', 'get_page_content_widths_js', 'js/ktp-page-content-widths.js', {}),
]


def dedent(text):
    lines = text.split('\n')
    indents = [len(l) - len(l.lstrip(' ')) for l in lines if l.strip()]
    cut = min(indents) if indents else 0
    return '\n'.join(l[cut:] if len(l) >= cut else l.lstrip(' ') for l in lines).rstrip() + '\n'


def remove_method(src, name):
    """docblock ごとメソッドを取り除き、本文を返す。"""
    i = src.index('function %s(' % name)
    line_start = src.rfind('\n', 0, i) + 1
    db = src.rfind('/**', 0, i)
    start = line_start
    if db != -1 and src[src.index('*/', db) + 2:line_start].strip() == '':
        start = src.rfind('\n', 0, db) + 1
    a = src.index('<<<', i)
    tag = re.match(r"<<<'?(\w+)'?", src[a:]).group(1)
    b = src.index('\n', a) + 1
    e = re.compile(r'^\s*' + tag + r';', re.M).search(src, b)
    body = src[b:e.start()]
    close = src.index('}', e.end())
    end = src.index('\n', close) + 1
    while src[end:end + 1] == '\n':
        end += 1
    return src[:start] + src[end:], body


def main():
    for rel, method, out, repl in JOBS:
        path = os.path.join(ROOT, rel)
        src = open(path, encoding='utf-8').read()
        src, body = remove_method(src, method)
        body = dedent(body)
        for a, b in repl.items():
            assert a in body, (method, a)
            body = body.replace(a, b)
        assert '<?php' not in body, method
        header = ('/* %s から切り出し（以前は PHP の nowdoc で返していた） */\n' % os.path.basename(rel))
        with open(os.path.join(ROOT, out), 'w', encoding='utf-8') as f:
            f.write(header + body)
        with open(path, 'w', encoding='utf-8') as f:
            f.write(src)
        print(f'{method:36s} -> {out}')


if __name__ == '__main__':
    main()
