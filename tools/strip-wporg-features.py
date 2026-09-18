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
    # 売上台帳PDF（2026-09-18 の指摘の本体）。ktpwp.php の require_once は
    # 下の edit() で外す。ファイルだけ消すと全リクエストで fatal になる。
    'includes/ajax-sales-ledger-pdf.php',
    'js/ktp-sales-ledger-pdf.js',
    # public_products
    'includes/class-ktpwp-public-product-order.php',
    'includes/class-ktpwp-public-product-order-memo.php',
    'css/public-products.css',
    'js/public-products.js',
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


def strip_feature_markers(feature):
    """ソース中の `KTPWP-WPORG-STRIP <feature>` マーカーで囲まれたコードをステージ全体から除去する。

    大きな連続ブロックは
        // KTPWP-WPORG-STRIP <feature> BEGIN
        ...
        // KTPWP-WPORG-STRIP <feature> END
    （HTML テンプレート部分では `<!-- ... -->`）で囲み、その BEGIN/END の行ごと削除する。
    1行だけの散在参照は行末に `// KTPWP-WPORG-STRIP <feature>` を付け、その行だけ削除する。

    コメントの記法（`//` か `<!-- -->` か）は問わず、タグ文字列そのものを探すので
    HTML テンプレートの中でも PHP コードの中でも同じマーカーが使える。

    BEGIN と END の対応が崩れている場合はビルドを失敗させる
    （リファクタでマーカーが片方だけ消えて、削除されるべきコードが
    そのまま混入するのを防ぐのが目的）。
    戻り値は (削除したブロック数, 削除した単独行数)。
    """
    begin_tag = f'KTPWP-WPORG-STRIP {feature} BEGIN'
    end_tag = f'KTPWP-WPORG-STRIP {feature} END'
    line_tag = f'KTPWP-WPORG-STRIP {feature}'
    block_count = 0
    line_count = 0

    for dp, dns, fns in os.walk(stage):
        for fn in fns:
            if not fn.endswith(('.php', '.js', '.css')):
                continue
            fp = os.path.join(dp, fn)
            rel = os.path.relpath(fp, stage)
            lines = open(fp, encoding='utf-8').read().splitlines(keepends=True)
            out = []
            in_block = False
            block_start_line = None
            changed = False
            for i, line in enumerate(lines, 1):
                if begin_tag in line:
                    if in_block:
                        errors.append(f'{rel}:{i}: {feature} の BEGIN が入れ子になっています（{block_start_line}行目から未終了）')
                    in_block = True
                    block_start_line = i
                    block_count += 1
                    changed = True
                    continue
                if end_tag in line:
                    if not in_block:
                        errors.append(f'{rel}:{i}: {feature} の END に対応する BEGIN がありません')
                    in_block = False
                    changed = True
                    continue
                if in_block:
                    changed = True
                    continue
                if line_tag in line:
                    line_count += 1
                    changed = True
                    continue
                out.append(line)
            if in_block:
                errors.append(f'{rel}:{block_start_line}: {feature} の BEGIN に対応する END がありません')
            if changed:
                open(fp, 'w', encoding='utf-8').writelines(out)

    return block_count, line_count


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

# --- 1.5) public_products のマーカー付きコードを除去 -------------------------
#     shortcodes.php のショートコード本体・service-main/db/ui.php のフォーム欄と
#     一覧バッジ・settings.php のデザイン設定とドキュメント・order-items.php の
#     Web申込み用請求行生成・ktpwp.php のブートストラップ呼び出しが対象。
#     マイグレーション4本と ktp_service テーブルの4カラムはあえて残す
#     （contracts 機能が同じテーブルの列順に依存しており、消すと有効化中の
#     機能が壊れるため。カラムが残るだけでは「ロックされた機能」にはならない）。
pp_blocks, pp_lines = strip_feature_markers('public_products')
print(f'public_products マーカー除去: ブロック{pp_blocks}件 / 単独行{pp_lines}行')

# --- 1.6) report のマーカー付きコードを除去 -------------------------------
#     ・class-ktpwp-ajax.php のレポート用 AJAX 登録と集計メソッド
#     ・class-ktpwp-ui-generator.php のレポートツールバー
#     ・ktpwp.php の ktp-report CSS enqueue / view-tab.php の report_type
#     レポートタブ本体を消しただけでは、集計 API とその UI 部品が
#     「実装は残っているが隠されている」形で ZIP に残る（2026-09-18 の指摘）。
rp_blocks, rp_lines = strip_feature_markers('report')
print(f'report マーカー除去: ブロック{rp_blocks}件 / 単独行{rp_lines}行')

# 売上台帳PDFハンドラの require_once。ガードが無いので、ファイルを消すと
# 全リクエストで fatal になる。edit()（required）なのでソース側の表記が変わったら
# 黙って通らずビルドが落ちる。
edit('ktpwp.php', """// --- 売上台帳PDF生成AJAXハンドラを読み込む ---
require_once __DIR__ . '/includes/ajax-sales-ledger-pdf.php';
""", '')

# report を無料版で使えないタブとして数えていた配列から 'report' を外す。
edit('includes/class-ktpwp-assets.php',
     "array( 'service', 'supplier', 'client', 'report', 'list' )",
     "array( 'service', 'supplier', 'client', 'list' )")

# --- 2) オートローダ登録を消す ----------------------------------------------
autoload_pat = r"'(" + '|'.join(REMOVE_CLASSES) + r")'\s*=>"
n = drop_lines('ktpwp.php', autoload_pat) + drop_lines('includes/class-ktpwp-loader.php', autoload_pat)
print(f'オートローダ登録の削除: {n}行')

# --- 2.6) SOURCES.txt から、同梱しなくなったライブラリの記載を消す ----------
#     chart.umd.min.js は REMOVE_FILES で消えるので、説明だけ残ると
#     「無いファイルの出典」が配布物に載ってしまう。
edit('js/lib/SOURCES.txt', """
chart.umd.min.js
  Chart.js 4.4.9 (MIT License)
  https://github.com/chartjs/Chart.js
  取得元: https://cdn.jsdelivr.net/npm/chart.js@4.4.9/dist/chart.umd.min.js
""", '\n')

# --- 3) レポートタブのUI除去は不要 ------------------------------------------
#     2026-09-03 にソース側で6番目のタブを「情報」(KTPWP_Tab_Info) に置き換えたため、
#     ここでタブを消す処理は要らなくなった。残す CSS の除去だけ行う。
# ktp-report の enqueue は KTPWP-WPORG-STRIP report マーカーで消える（1.6）。

# ktpwp.php 末尾の「クラスが無ければ include」ブロック。
# class_exists ガードはあるが、ファイルが無いと include_once が
# PHP Warning を吐いて全ページの先頭に出る（2026-09-03 に実機で発見）。
# **class_exists ガードはファイルの存在を保証しない。** 参照検査だけでは拾えない。
edit('ktpwp.php', """if ( ! class_exists( 'KTPWP_Report_Class' ) ) {
    include_once KANTANPRO_PLUGIN_DIR . 'includes/class-ktpwp-tab-report.php';
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
     """		// WordPress.org 配布版にはロックされた機能は無い（ガイドライン5）。
		// 「隠しているが実装はある」機能を配列に残すこと自体がトライアルウェアの形になる。
		// 2026-09-18 のレビュー指摘を受けて、無効機能のキーが読み手ごと残る状態をやめた。
		// 同梱していない機能はコードを物理削除し、それを読む側も
		// ビルド時に取り除いている。よってここは常に空で正しい。
		return array();""")

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

# 無料版向けレポート見出し（generate_free_edition_report_title_bar）は
# KTPWP-WPORG-STRIP report マーカーでメソッドごと消える（1.6）。

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

# 更新完了ガイド（admin_footer）は更新チェッカーが立てるトランジェント頼みで、
# wp.org 版では到達しない。生の <script> を出すので中身ごと空にする。
# （admin_footer はスクリプト出力後なので wp_add_inline_script では代替できない）
replace_method_body('ktpwp.php', 'function ktpwp_footer_update_complete_guide() {',
                    '''
    // WordPress.org 配布版では自前の更新機能を持たないため、この案内は出さない。
    return;
}''')

print('更新完了ガイドの除去: 完了')

print('受注書のロック表示の除去: 完了')

print('スタッフ管理の除去: 完了')

print('開発元への外部通信の除去: 完了')

# --- 5e) 無効機能キーの読み手を取り除く（ガイドライン5） --------------------
#     get_free_disabled_features() を空にしたので、以下は到達しない。
#     クラスは REMOVE_FILES で消えているため、ゲートごと消して
#     「無料版では使えない機能の入口」がコードに残らないようにする。
replace_method_body('ktpwp.php', 'function ktpwp_init_stripe_billing() {',
                    '''
    // WordPress.org 配布版は Stripe 請求連携を同梱していない。
    return;
}''')
replace_method_body('ktpwp.php', 'function ktpwp_init_contract_invoice_mail() {',
                    '''
    // WordPress.org 配布版は請求メール自動送信を同梱していない。
    return;
}''')
sb_blocks, sb_lines = strip_feature_markers('stripe_billing')
print(f'stripe_billing マーカー除去: ブロック{sb_blocks}件 / 単独行{sb_lines}行')

# --- 5f) スタッフ上限の残りを外す ---------------------------------------------
#     スタッフ管理ページは 5d で空にしたが、エディション定義のスタッフ上限と
#     開発者向け画面（ローカル/.test ホストで表示される）が残っていた。
#     wp.org 版では上限を設けない（0 = 無制限）。
edit('ktpwp.php',
     "// 無料版: 0 = スタッフ追加不可（管理者のみ）。有料 pro の 0（無制限）とは別扱い。",
     "// 0 = 無制限。")
edit('includes/class-ktpwp-edition.php',
     "\t * スタッフ上限（無料版の 0 = 追加不可。有料 pro の 0 = 無制限）",
     "\t * スタッフ上限（0 = 無制限）")
edit('includes/class-ktpwp-edition.php',
     "\t * 無料版で無効な機能キー（solo / EX 有料版では有効）",
     "\t * 無効な機能キー（この配布版では常に空）")
edit('includes/class-ktpwp-edition.php',
     "'staff_limit' => 0, // 0 = スタッフ追加不可（管理者のみ）。有料 pro の 0（無制限）とは別扱い。",
     "'staff_limit' => 0, // 0 = 無制限。")
replace_method_body('includes/class-ktpwp-edition.php',
                    '\tpublic static function can_add_staff() {',
                    '''
\t\treturn true;
\t}''')
replace_method_body('includes/class-ktpwp-edition.php',
                    '\tpublic static function format_staff_limit_display() {',
                    '''
\t\treturn __( '無制限', 'kantanpro' );
\t}''')
replace_method_body('includes/class-ktpwp-settings.php',
                    '    private function render_developer_edition_settings() {',
                    '''
        // エディションとスタッフ上限の設定画面は WordPress.org 配布版には含めない。
        return;
    }''')
drop_lines('includes/class-ktpwp-settings.php', r"'(スタッフ上限|登録スタッフ数)'")

# --- 5g) レポート／ライセンス誘導の翻訳文言と説明文 ---------------------------
#     翻訳辞書は class-ktpwp-i18n.php に残るので、消した機能の文言も
#     ここで落とさないと「ライセンスが必要」等が grep に引っかかる。
drop_lines('includes/class-ktpwp-i18n.php',
           r"^\s*'(?:[^']*売上台帳[^']*|[^']*売上レポート[^']*|顧客別レポート|サービス別レポート|協力会社レポート"
           r"|レポート|[^']*ライセンス[^']*|詳細な分析とレポート[^']*)'\s*=>")
edit('includes/class-ktpwp-order-main.php',
     "売上レポートの期間判定に使われる登録日です", "受注書の登録日です")



# --- 6) 検証 -----------------------------------------------------------------
leftovers = []
for rel in REMOVE_FILES:
    if os.path.exists(path(rel)):
        leftovers.append(rel)
if leftovers:
    errors.append('消えていないファイル: ' + ', '.join(leftovers))

# 消したファイルを include/require、または enqueue/URL 参照している箇所が
# 残っていないか。class_exists ガードがあっても、ファイルが無ければ
# PHP Warning が出る（include/require）か、404 になる（CSS/JS の enqueue）。
LOAD_REF_PATTERN = re.compile(
    r'\b(include|include_once|require|require_once|load_required_class'
    r'|wp_enqueue_style|wp_enqueue_script|wp_register_style|wp_register_script)\b'
)
for rel in REMOVE_FILES:
    base = os.path.basename(rel)
    for dp, dns, fns in os.walk(stage):
        for fn in fns:
            if not fn.endswith(('.php', '.js')):
                continue
            fp = os.path.join(dp, fn)
            for i, line in enumerate(open(fp, encoding='utf-8', errors='replace'), 1):
                if base in line and (LOAD_REF_PATTERN.search(line) or "plugin_dir_url" in line or "plugins_url" in line):
                    errors.append(f'{os.path.relpath(fp, stage)}:{i} が削除済みの {base} を読み込もうとしています')

# --- 6.5) public_products の痕跡が残っていないか -----------------------------
#     マーカーで囲んだつもりでも、対応漏れやマーカー範囲外の書き忘れがあれば
#     ここで捕まえる。次のレビューで同じ指摘を二度と受けないためのゲート。
#
# 許可リストに載っている箇所だけは、意図的に残す（理由を必ず書く）。
# ここに安易に追加すると「隠しているだけ」に逆戻りするので、
# 追加する前に「なぜ機能の実装ではないのか」を説明できることを確認する。
PUBLIC_PRODUCTS_ALLOWED = {
    # class-ktpwp-service-db.php: UI（フォーム欄・一覧バッジ・ショートコード）と
    # 「無効なら値を強制的にクリアする」clamp_public_product_fields_for_edition() は
    # マーカーで全て除去済み。残るのは ktp_service.get_schema() の列定義、
    # $_POST から読んだ値をそのままDBへ保存するだけの配列組み立て、
    # カラム有無を確認するヘルパー（service_table_has_public_*_column）、
    # 値の型を揃えるだけのサニタイザ（sanitize_public_*／is_public_*／
    # format_public_html_for_display）。UIが無い以上これらの値は
    # 事実上常に既定値のままになるが、テーブル形状を無料版と同じに保つために
    # 保存ロジック自体は残す。「機能を隠す」コードではなく
    # 「列を持つテーブルへの一般的な読み書き」なので許可する。
    ('includes/class-ktpwp-service-db.php', 'is_public'),
    ('includes/class-ktpwp-service-db.php', 'public_quantity_fixed'),
    ('includes/class-ktpwp-service-db.php', 'public_instant_purchase'),
    ('includes/class-ktpwp-service-db.php', 'public_html'),
    # class-ktpwp-service-main.php: 一覧のソート許可リストと $_GET 読み取り時の
    # 初期化に、UI除去後は使われない既定値代入としてカラム名が残る。
    # UIの列・バッジ・入力欄は除去済みで、値が変わっても表示に影響しない。
    ('includes/class-ktpwp-service-main.php', 'is_public'),
    ('includes/class-ktpwp-service-main.php', 'public_quantity_fixed'),
    ('includes/class-ktpwp-service-main.php', 'public_instant_purchase'),
    ('includes/class-ktpwp-service-main.php', 'public_html'),
    # 4本のマイグレーション。列を追加する ALTER 文の中で、直前に追加した
    # 列名を `AFTER \`...\`` として参照し合っている（実行順の連鎖）ため、
    # 自分の列名だけでなく前段の列名も同じ行に同居する。
    ('includes/migrations/20260611_add_is_public_to_service.php', 'is_public'),
    ('includes/migrations/20260618_add_public_quantity_fixed_to_service.php', 'public_quantity_fixed'),
    ('includes/migrations/20260619_add_public_html_to_service.php', 'public_html'),
    ('includes/migrations/20260619_add_public_html_to_service.php', 'public_quantity_fixed'),
    ('includes/migrations/20260620_add_public_instant_purchase_to_service.php', 'public_instant_purchase'),
    ('includes/migrations/20260620_add_public_instant_purchase_to_service.php', 'public_quantity_fixed'),
    # contracts の billing_cycle 列を is_public の直後に追加するための
    # ALTER 文。同じ理由でカラム名を残す必要がある。
    ('includes/migrations/20260613_add_contract_billing_cycle_to_service.php', 'is_public'),
    # class-ktpwp-contract-service-public-availability.php: 在庫・契約可否の
    # 判定で is_public カラムの値を読むだけで、フォーム/UI/保存ロジックは無い。
    # contracts は無料版で開放している機能なので、この参照はそのまま必要。
    ('includes/class-ktpwp-contract-service-public-availability.php', 'is_public'),
    # 受注の external_source に保存済みの列挙値。DBに実データがある可能性があり、
    # 契約枠の計算や受注ラベル表示がこの文字列に依存している。
    # 保存済みデータの識別子であって「機能の実装」ではないため残す。
    ('includes/class-ktpwp-order-admin-notification.php', 'public_product'),
    ('includes/class-ktpwp-contract-service-public-availability.php', 'public_product'),
    ('includes/class-ktpwp-service-related-orders.php', 'public_product'),
    ('includes/class-ktpwp-staff-chat.php', 'public_product'),
    ('includes/class-ktpwp-payment-timing.php', 'public_product'),
    # KTPWP_Public_Product_Order_Memo への参照は class_exists() ガード済みで、
    # 該当クラスファイルは REMOVE_FILES で物理削除される。ガードにより
    # 常に false 側の分岐しか実行されない（Phase 1 と同じ確認済みの方式）。
    ('includes/class-ktpwp-order-contract-draft-resolver.php', 'KTPWP_Public_Product'),
    ('includes/class-ktpwp-service-related-orders.php', 'KTPWP_Public_Product'),
    ('includes/class-ktpwp-payment-timing.php', 'KTPWP_Public_Product'),
}

# 「単語境界」付きで見るパターン。素の部分文字列だと
# is_public_checkbox / is_public_products_enabled / render_public_html_field
# のような **本来検出すべき複合識別子** まで許可リストが誤って隠してしまうため、
# is_public 系はカラム名そのもの（前後が識別子文字ではない）にだけ絞る。
PUBLIC_PRODUCTS_WORD_PATTERNS = [
    'is_public', 'public_quantity_fixed', 'public_instant_purchase', 'public_html',
]
# 部分文字列のままでよいもの（このパターンが出る時点で複合識別子ごと怪しいので、
# 逆に単語境界を付けると検出漏れが増える）。
PUBLIC_PRODUCTS_SUBSTR_PATTERNS = [
    'public_products', 'public-products', 'ktpwp_public_product',
    'public_product_card_bg_color', 'KTPWP_Public_Product', 'public_product',
]

WORD_PATTERN_RE = {p: re.compile(r'(?<![A-Za-z0-9_])' + re.escape(p) + r'(?![A-Za-z0-9_])') for p in PUBLIC_PRODUCTS_WORD_PATTERNS}

for dp, dns, fns in os.walk(stage):
    for fn in fns:
        if not fn.endswith(('.php', '.js', '.css', '.txt')):
            continue
        fp = os.path.join(dp, fn)
        rel = os.path.relpath(fp, stage)
        for i, line in enumerate(open(fp, encoding='utf-8', errors='replace'), 1):
            hits = []
            for pat in PUBLIC_PRODUCTS_SUBSTR_PATTERNS:
                if pat in line:
                    hits.append(pat)
            for pat in PUBLIC_PRODUCTS_WORD_PATTERNS:
                if WORD_PATTERN_RE[pat].search(line):
                    hits.append(pat)
            for pat in hits:
                if (rel, pat) in PUBLIC_PRODUCTS_ALLOWED:
                    continue
                errors.append(f'{rel}:{i}: public_products の痕跡が残っています（{pat!r}） → {line.strip()[:100]!r}')

# --- 6.6) トライアルウェアの痕跡が残っていないか ----------------------------
#     2026-09-18 のレビューで、レポートタブを消したのに売上台帳PDFの実装
#     (includes/ajax-sales-ledger-pdf.php) が ZIP に残り、しかもこのスクリプトの
#     検証は REMOVE_FILES と public_products しか見ていなかったので「検証OK」のまま
#     提出してしまった。**消したはずの機能の名前で ZIP を grep する**のがこのゲート。
#
# 許可リストに足すときは、「なぜ機能の実装ではないのか」を必ず書くこと。
TRIALWARE_SUBSTR_PATTERNS = [
    # 売上台帳PDF / レポート API / レポート UI
    'sales_ledger', 'sales-ledger', '売上台帳',
    'ktp_get_report_data', 'ktpwp_get_report_data',
    'ktp_get_sales_data', 'ktp_get_progress_data', 'ktp_get_client_data',
    'ktp_get_service_data', 'ktp_get_supplier_data',
    'get_sales_chart_data', 'get_client_chart_data',
    'get_service_chart_data', 'get_supplier_chart_data',
    'generate_free_edition_report_title_bar',
    'KTPWP_Report_Class', 'KTPWP_Graph_Renderer', 'report_type',
    'ktp-report', 'chart.umd',
    # ライセンス購入への誘導文言
    'ライセンスを購入', 'ライセンスが必要', '詳細な分析とレポート',
    'Purchase a license', 'license is required',
]
# 単語境界で見るもの（case-sensitive）。素の部分文字列だと reported / error_report 等に誤爆する。
TRIALWARE_WORD_PATTERNS = ['report']
TRIALWARE_WORD_RE = {p: re.compile(r'(?<![A-Za-z0-9_])' + re.escape(p) + r'(?![A-Za-z0-9_])') for p in TRIALWARE_WORD_PATTERNS}

TRIALWARE_ALLOWED = {
    # AI 一括取り込みの結果レポート（transient に取り込み件数を保存する変数名）。
    # 売上レポートとは無関係で、取り込み画面の結果表示にだけ使われる。
    ('includes/class-ktpwp-fm-import.php', 'report'): '取り込み結果の変数名',
    # readme の「別製品の紹介」節と changelog。ガイドライン5は
    # "may point out which features are available through a separated plugin" と
    # 別製品の存在を示すことを認めている。ロックして誘導する形ではない。
    ('readme.txt', 'report'): '別製品の紹介と changelog',
    ('readme.txt', 'sales-ledger'): 'changelog（1.3.41 で削除したことの記載）',
    # 削除済みのレポート UI 用セレクタ。参照する HTML が無いので何も描画されない。
    # 1 枚の styles.css に他タブのスタイルと入り混じっていて安全に切り出せないため残す。
    ('css/styles.css', 'ktp-report'): '死んだセレクタ（HTML 側は削除済み）',
}

for dp, dns, fns in os.walk(stage):
    for fn in fns:
        if not fn.endswith(('.php', '.js', '.css', '.txt')):
            continue
        fp = os.path.join(dp, fn)
        rel = os.path.relpath(fp, stage)
        for i, line in enumerate(open(fp, encoding='utf-8', errors='replace'), 1):
            hits = [pat for pat in TRIALWARE_SUBSTR_PATTERNS if pat in line]
            # CSS は見ない: 消したレポート UI 用のセレクタ（ktp-report-*）が styles.css に
            # 他タブの規則と混在しており、単語境界が - で切れて誤爆する。HTML 側が無いので何も描画されない。
            if not fn.endswith('.css'):
                hits += [pat for pat in TRIALWARE_WORD_PATTERNS if TRIALWARE_WORD_RE[pat].search(line)]
            for pat in hits:
                if (rel, pat) in TRIALWARE_ALLOWED:
                    continue
                errors.append(f'{rel}:{i}: ロック機能／レポートの痕跡が残っています（{pat!r}） → {line.strip()[:100]!r}')

# 無効機能リストが「空」であること。検索ではなく表明にしてあるのは、ソース側の
# 書き方が変わって edit() の対象が見つからなくなっても、黙って通らないため。
ed = open(path('includes/class-ktpwp-edition.php'), encoding='utf-8').read()
m = re.search(r'function get_free_disabled_features\(\)\s*\{(.*?)\n\t\}', ed, re.S)
if not m or not re.search(r'return array\(\s*\);', m.group(1)):
    errors.append('get_free_disabled_features() が空配列を返していません（ガイドライン5）')
if re.search(r"ktpwp_is_feature_enabled\(\s*'(report|stripe_billing|contract_invoice_auto_mail|public_products)'", '\n'.join(
        open(os.path.join(dp, fn), encoding='utf-8', errors='replace').read()
        for dp, dns, fns in os.walk(stage) for fn in fns if fn.endswith('.php'))):
    errors.append("同梱していない機能のゲート（ktpwp_is_feature_enabled('report' 等)）が残っています")

# マーカー自体の消し忘れ（BEGIN/ENDの対応が壊れて片方だけ残った場合の保険）。
for dp, dns, fns in os.walk(stage):
    for fn in fns:
        if not fn.endswith(('.php', '.js', '.css')):
            continue
        fp = os.path.join(dp, fn)
        for i, line in enumerate(open(fp, encoding='utf-8', errors='replace'), 1):
            if 'KTPWP-WPORG-STRIP' in line:
                errors.append(f'{os.path.relpath(fp, stage)}:{i}: 除去し忘れたマーカーが残っています')

# ショートコードとして登録されていないことの確認。
for dp, dns, fns in os.walk(stage):
    for fn in fns:
        if not fn.endswith('.php'):
            continue
        fp = os.path.join(dp, fn)
        for i, line in enumerate(open(fp, encoding='utf-8', errors='replace'), 1):
            if re.search(r"(add_shortcode|shortcode_exists)\(\s*'ktpwp_public_products'", line):
                errors.append(f'{os.path.relpath(fp, stage)}:{i}: ktpwp_public_products がショートコードとして残っています')

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
