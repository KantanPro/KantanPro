#!/usr/bin/env python3
"""wp.org 提出版から「無料版でロックしていた機能」のコードを取り除く。

background:
  WordPress.org ガイドライン5（Trialware）は「ロックされた機能がコードに存在すること」
  自体を禁じている。KTPWP_Edition::get_free_disabled_features() で7機能を無効化して
  アップグレード誘導を出していたのが 2026-09-02 のレビューで指摘された。

方針（2026-09-03 に実測して決定）:
  削除する … report / public_products / stripe_billing
      いずれもオートロードで、外部からの参照はすべて class_exists() ガード内。
      ファイルを消しても fatal にならないことを確認済み。
  開放する … contracts / backup / order_auxiliary
      - contracts は21ファイル・外部156行で受注/サービス/設定の中核と密結合（ユーザー判断）
      - backup は独立ファイルが無く class-ktpwp-settings.php に埋まっている
      - order_auxiliary は5箇所の require_once のうち4箇所が機能ゲートの外にあり、
        消すと受注メールの添付と受注削除で **fatal error になる**（実体は中核機能）
      これらはロック判定を外して全開放する。ロックが無ければガイドライン5は満たす。

いずれの場合も、アップグレード誘導UI（ガイドライン11）は完全に消す。

使い方: strip-wporg-features.py <ステージのパス>
"""
import os, re, sys

REMOVE_FILES = [
    # report
    'includes/class-ktpwp-tab-report.php',
    'includes/class-ktpwp-graph-renderer.php',
    'js/ktp-report-charts.js',
    'js/ktp-report-print.js',
    'js/lib/chart.umd.min.js',
    'css/ktp-report.css',
    # public_products
    'includes/class-ktpwp-public-product-order.php',
    'includes/class-ktpwp-public-product-order-memo.php',
    # stripe_billing / contract_invoice_auto_mail
    'includes/class-ktpwp-stripe-billing.php',
    'includes/class-ktpwp-stripe-subscription.php',
    'includes/class-ktpwp-contract-invoice-mail.php',
]

# オートローダのマップから消すクラス名
REMOVE_CLASSES = [
    'KTPWP_Report_Class', 'KTPWP_Graph_Renderer',
    'KTPWP_Public_Product_Order', 'KTPWP_Public_Product_Order_Memo',
    'KTPWP_Stripe_Billing', 'KTPWP_Stripe_Subscription',
    'KTPWP_Contract_Invoice_Mail',
]

stage = sys.argv[1] if len(sys.argv) > 1 else sys.exit('ステージのパスを渡してください')
errors = []


def path(rel):
    return os.path.join(stage, rel)


def edit(rel, old, new, required=True, count=0):
    """テキスト置換。required なのに見つからなければエラーとして積む。"""
    p = path(rel)
    if not os.path.exists(p):
        if required:
            errors.append(f'{rel}: ファイルがありません')
        return
    s = open(p, encoding='utf-8').read()
    if old not in s:
        if required:
            errors.append(f'{rel}: 対象が見つかりません → {old[:70]!r}')
        return
    s = s.replace(old, new) if count == 0 else s.replace(old, new, count)
    open(p, 'w', encoding='utf-8').write(s)


def drop_lines(rel, pattern):
    """正規表現に一致する行を消す。消した行数を返す。"""
    p = path(rel)
    if not os.path.exists(p):
        return 0
    lines = open(p, encoding='utf-8').read().splitlines(keepends=True)
    keep = [l for l in lines if not re.search(pattern, l)]
    open(p, 'w', encoding='utf-8').writelines(keep)
    return len(lines) - len(keep)


# --- 1) ファイルを消す -------------------------------------------------------
removed = 0
for rel in REMOVE_FILES:
    p = path(rel)
    if os.path.exists(p):
        os.remove(p)
        removed += 1
    else:
        errors.append(f'{rel}: 消そうとしたファイルが見つかりません')
print(f'ファイル削除: {removed}件')

# --- 2) オートローダ登録を消す ----------------------------------------------
autoload_pat = r"'(" + '|'.join(REMOVE_CLASSES) + r")'\s*=>"
n = drop_lines('ktpwp.php', autoload_pat) + drop_lines('includes/class-ktpwp-loader.php', autoload_pat)
print(f'オートローダ登録の削除: {n}行')

# --- 3) レポートタブのUI除去は不要 ------------------------------------------
#     2026-09-03 にソース側で6番目のタブを「情報」(KTPWP_Tab_Info) に置き換えたため、
#     ここでタブを消す処理は要らなくなった。残す CSS の除去だけ行う。
drop_lines('ktpwp.php', r"wp_enqueue_style\( 'ktp-report'")

# ktpwp.php 末尾の「クラスが無ければ include」ブロック。
# class_exists ガードはあるが、ファイルが無いと include_once が
# PHP Warning を吐いて全ページの先頭に出る（2026-09-03 に実機で発見）。
# **class_exists ガードはファイルの存在を保証しない。** 参照検査だけでは拾えない。
edit('ktpwp.php', """if ( ! class_exists( 'KTPWP_Report_Class' ) ) {
    include_once MY_PLUGIN_PATH . 'includes/class-ktpwp-tab-report.php';
}
""", '')

# --- 5) ロック判定とアップグレード誘導UIを無効化 -----------------------------
#     ここが本丸。ロック対象を空にすれば「ロックされた機能」は存在しなくなる。
edit('includes/class-ktpwp-edition.php',
     """		return array(
			'report',
			'backup',
			'order_auxiliary',
			'stripe_billing',
			'contract_invoice_auto_mail',
			'public_products',
			'contracts',
		);""",
     """		// WordPress.org 配布版では「有料だからロックする」機能は無い（ガイドライン5）。
		// ここに残るのは **コードごと同梱していない機能** だけ。
		// 同梱していない以上 UI も出してはいけないので、無効として扱う。
		// （空配列にすると、実体の無い機能の列やボタンが描画されてしまう。
		//   2026-09-03 にサービスタブの「公開」列が出て気づいた）
		return array(
			'report',
			'public_products',
			'stripe_billing',
			'contract_invoice_auto_mail',
		);""")

print('ロック解除: 完了')
# --- 5b) アップグレード誘導UIを空にする（ガイドライン11） --------------------
#     呼び出し元は8ファイルに散っているが、いずれも「ロックされているとき」の分岐で、
#     ロック対象が空になった今は到達しない。呼び出し元を1つずつ消すより、
#     メッセージ生成側を空にして誘導の文言とリンクを消すほうが壊しにくい。
import io as _io

def replace_method_body(rel, signature, body):
    """メソッド1つの本体を差し替える。次の `\n\t}` までを本体とみなす。"""
    p = path(rel)
    src = open(p, encoding='utf-8').read()
    i = src.find(signature)
    if i < 0:
        errors.append(f'{rel}: {signature} が見つかりません')
        return
    # 終端の閉じ括弧はシグネチャと同じインデント。クラスが if の中にあると
    # タブが1段深くなるので、決め打ちにせずシグネチャから導く。
    indent = signature[:len(signature) - len(signature.lstrip(' \t'))]
    closer = '\n' + indent + '}'
    j = src.find(closer, i)
    if j < 0:
        errors.append(f'{rel}: {signature} の終端が見つかりません')
        return
    src = src[:i] + signature + body + src[j + len(closer):]
    open(p, 'w', encoding='utf-8').write(src)

NOOP = """
		// WordPress.org 配布版ではアップグレード誘導を出さない（ガイドライン11）。
		// ロック対象が無いのでこの分岐には到達しないが、
		// 誘導の文言とリンクをパッケージに含めないために空を返す。
		unset( $feature_name );
		return '';
	}"""

replace_method_body('includes/class-ktpwp-edition.php',
                    '\tpublic static function get_upgrade_message_html( $feature_name ) {', NOOP)
replace_method_body('includes/class-ktpwp-edition.php',
                    '\tpublic static function get_admin_moved_to_ex_message_html( $feature_name ) {', NOOP)

edit('includes/class-ktpwp-edition.php',
     "\t\treturn 'https://www.kantanpro.com/product/kantanpro-ex';",
     "\t\t// WordPress.org 配布版では販売ページへ誘導しない。\n\t\treturn '';")

edit('includes/class-ktpwp-edition.php',
     "\t\t\t\t\t: __( 'この機能は有料版で利用できます。', 'kantanpro' )",
     "\t\t\t\t\t: ''")

# report を消したので、無料版向けレポート見出し（ロック文言入り）も不要。
replace_method_body('includes/class-ktpwp-ui-generator.php',
                    '\t\tpublic function generate_free_edition_report_title_bar() {',
                    "\n\t\t\t// レポート機能は同梱していないため、この見出しは使わない。\n\t\t\treturn '';\n\t\t}")

# 翻訳辞書に残る誘導文言も消す
drop_lines('includes/class-ktpwp-i18n.php', r"無料版では利用できません")

print('アップグレード誘導UIの無効化: 完了')

# --- 5c) 開発元への外部通信を止める -----------------------------------------
#     kantanpro.com への「規約同意の通知」と「中央バナーの取得」は、
#     wp.org 版では機能としても不要で、readme への記載義務も生む。
#     フラグで止めるだけだとコードが残って指摘されるので中身ごと消す。
replace_method_body('includes/class-ktpwp-terms-of-service.php',
                    '    private function notify_via_klm_api( $user_id ) {',
                    '''
        // WordPress.org 配布版では開発元へ規約同意を通知しない。
        // 外部サービスへの送信をなくすことで readme への記載も不要になる。
        unset( $user_id );
        return false;
    }''')

replace_method_body('includes/class-ktpwp-terms-of-service.php',
                    '    private function get_klm_terms_api_url() {',
                    '''
        // WordPress.org 配布版では外部の規約APIを使わない。
        return '';
    }''')

replace_method_body('includes/class-ktpwp-shortcodes.php',
                    '    private function should_fetch_official_central_banner_feed( $options ) {',
                    '''
        // WordPress.org 配布版では開発元配信のバナーを一切取得しない
        // （ガイドライン11: サイトへの広告の注入）。
        unset( $options );
        return false;
    }''')

drop_lines('includes/class-ktpwp-shortcodes.php', r"wp-json/kantanpro/v1/central-banner")

# 情報タブの案内は、wp.org 版では「売り込み」ではなく「別製品の紹介」に差し替える。
#
# レビュー本文の許容範囲:
#   "Your plugin may point out which features are available through a
#    separated plugin, but that's it."
# つまり別プラグインの存在を示すのは可。不可なのは
#   ・内蔵機能をロックして誘導する（ガイドライン5）
#   ・管理画面を宣伝で占有する（ガイドライン11）
# なので、無料版が制限されているという書き方をせず、
# 「別製品がある」という事実だけを1箇所に静かに置く。
replace_method_body('includes/class-ktpwp-tab-info.php',
                    '\t\tprivate static function upgrade_notice() {',
                    '''
			$html  = '<div class="ktp-info-section ktp-info-related">';
			$html .= '<h4>' . esc_html__( '関連製品', 'kantanpro' ) . '</h4>';
			$html .= '<p>' . esc_html__( '同じ開発元から、KantanProEX（WP）という別のプラグインも提供しています。売上レポート、自社商品の公開と申し込み受付、Stripe による決済、複数人での利用に対応しています。', 'kantanpro' ) . '</p>';
			$html .= '<p><a href="' . esc_url( 'https://www.kantanpro.com/product/kantanpro-ex' )
				. '" target="_blank" rel="noopener noreferrer">'
				. esc_html__( 'KantanProEX（WP）について', 'kantanpro' )
				. '</a></p>';
			$html .= '</div>';

			return $html;
		}''')

# --- 5d) スタッフ管理を外す（ガイドライン5） ---------------------------------
#     無料版は staff_limit=0 でスタッフを追加できないのに、管理UIは同梱していて
#     「チームで使うなら KantanProEX を」と誘導していた。レポートと同じ
#     「内蔵機能をロックして有料版へ誘導」の形なので、機能ごと外す。
#     （ユーザー判断 2026-09-03: 開放ではなく削除）
#     参照は class-ktpwp-settings.php と class-ktpwp-edition.php の2ファイルだけ。

# 管理メニューから外す
edit('includes/class-ktpwp-settings.php', """        // サブメニュー - スタッフ管理
        add_submenu_page(
            'ktp-settings', // 親メニューのスラッグ
            __( 'スタッフ管理', 'kantanpro' ), // ページタイトル
            __( 'スタッフ管理', 'kantanpro' ), // メニュータイトル
            'manage_options', // 権限
            'ktp-staff', // メニューのスラッグ
            array( $this, 'create_staff_page' ) // 表示を処理する関数
        );

""", '')

# 管理ページ本体を空に
replace_method_body('includes/class-ktpwp-settings.php',
                    '    public function create_staff_page() {',
                    '''
        // スタッフ管理は WordPress.org 配布版には含めない。
        return;
    }''')

# ヘッダーのリンクを外す
drop_lines('ktpwp.php', r"admin\.php\?page=ktp-staff")

# 上限まわりを「制限なし」に倒す（誘導文言を残さない）
replace_method_body('includes/class-ktpwp-edition.php',
                    '\tpublic static function get_staff_limit_reached_message() {',
                    '''
		// スタッフ管理を同梱していないため、この文言は使わない。
		return '';
	}''')

# 受注書の「メール送信履歴・案件ファイル」のロック表示。
# order_auxiliary は wp.org 版では開放しているので実行されないが、
# aria-label="有料版機能" の鍵アイコンがコードに残っているとガイドライン5の
# 「ロックされた機能」に見える。メソッドごと空にする。
replace_method_body('includes/class-ktpwp-order-main.php',
                    '\t\tprivate function render_free_edition_order_auxiliary_notice_blocks( $order_id ) {',
                    '''
			// wp.org 版ではこの機能を開放しているため、ロック表示は出さない。
			unset( $order_id );
			return '';
		}''')

print('受注書のロック表示の除去: 完了')

print('スタッフ管理の除去: 完了')

print('開発元への外部通信の除去: 完了')



# --- 6) 検証 -----------------------------------------------------------------
leftovers = []
for rel in REMOVE_FILES:
    if os.path.exists(path(rel)):
        leftovers.append(rel)
if leftovers:
    errors.append('消えていないファイル: ' + ', '.join(leftovers))

# 消したファイルを include/require している箇所が残っていないか。
# class_exists ガードがあっても、ファイルが無ければ Warning が出る。
for rel in REMOVE_FILES:
    base = os.path.basename(rel)
    for dp, dns, fns in os.walk(stage):
        for fn in fns:
            if not fn.endswith('.php'):
                continue
            fp = os.path.join(dp, fn)
            for i, line in enumerate(open(fp, encoding='utf-8', errors='replace'), 1):
                if base in line and re.search(r'\b(include|include_once|require|require_once|load_required_class)\b', line):
                    errors.append(f'{os.path.relpath(fp, stage)}:{i} が削除済みの {base} を読み込もうとしています')

# 削除したクラスへの参照が「ガードの外」に残っていないか。
# ガードは同じ行とは限らず、数行前の if ( class_exists( ... ) ) のこともあるので
# 直前 8 行を見る。ここを行単位だけで見ると大量に誤検知する。
GUARD_WINDOW = 15

# 変数経由のガードは追えないので、目視確認した箇所だけ理由付きで除外する。
# ここに足すときは必ず「なぜ安全か」を書くこと。安易に足すと fatal を見逃す。
REVIEWED_SAFE = {
    # 3295行の $stripe_feature_enabled が class_exists( 'KTPWP_Stripe_Billing' ) を
    # 含むようになったので、この if ブロック内は Stripe 非同梱でも実行されない。
    # （2026-09-03 に目視確認）
    ('includes/class-ktpwp-settings.php', 'KTPWP_Stripe_Billing'): '$stripe_feature_enabled でガード済み',
}

for cls in REMOVE_CLASSES:
    hit = []
    for dp, dns, fns in os.walk(stage):
        for fn in fns:
            if not fn.endswith('.php'):
                continue
            fp = os.path.join(dp, fn)
            lines = open(fp, encoding='utf-8', errors='replace').read().splitlines()
            word = re.compile(r'\b' + cls + r'\b')
            for i, line in enumerate(lines):
                if not word.search(line):
                    continue
                # コメント・PHPDoc は対象外
                stripped = line.strip()
                if stripped.startswith(('*', '//', '#')):
                    continue
                window = '\n'.join(lines[max(0, i - GUARD_WINDOW):i + 1])
                if re.search(r"class_exists\(\s*'" + cls + r"'", window) or 'instanceof' in line:
                    continue
                rel = os.path.relpath(fp, stage)
                if (rel, cls) in REVIEWED_SAFE:
                    continue
                hit.append(f'{rel}:{i + 1}')
    if hit:
        errors.append(f'{cls} のガード外参照が残っています: ' + ', '.join(hit[:5]))

# 触ったあとに壊れていないかを必ず見る。ここが無いと parse error のまま
# 「検証OK」と表示してしまう（2026-09-03 に実際に起きた）。
import subprocess
bad = []
for dp, dns, fns in os.walk(stage):
    for fn in fns:
        if not fn.endswith('.php'):
            continue
        fp = os.path.join(dp, fn)
        r = subprocess.run(['php', '-l', fp], capture_output=True, text=True)
        if r.returncode != 0:
            bad.append(f'{os.path.relpath(fp, stage)}: ' + r.stdout.strip().splitlines()[0])
if bad:
    errors.extend(bad)

if errors:
    print('\n[ERROR] 以下を解決してください:', file=sys.stderr)
    for e in errors:
        print('  - ' + e, file=sys.stderr)
    sys.exit(1)

print('検証OK')
