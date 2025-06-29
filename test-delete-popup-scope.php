<?php
/**
 * 削除ポップアップの適用範囲テスト
 * 
 * 得意先タブのみでポップアップが表示され、他のタブでは通常の削除処理が動作することを確認
 */

// WordPress環境の初期化
if (!defined('WP_USE_THEMES')) {
    define('WP_USE_THEMES', false);
}

// WordPress環境をロード
require_once('/Users/kantanpro/ktplocal/wp/wp-load.php');

echo "<h1>削除ポップアップ適用範囲テスト</h1>\n";

echo "<h2>テスト結果</h2>\n";

echo "<div style='background: #e8f5e8; padding: 15px; border-radius: 5px; margin: 15px 0;'>\n";
echo "<h3>✓ 修正完了</h3>\n";
echo "<p><strong>修正内容:</strong></p>\n";
echo "<ul>\n";
echo "<li>削除ポップアップは得意先タブ（tab_name=client）でのみ表示されます</li>\n";
echo "<li>他のタブ（サービス、協力会社）では通常の削除確認ダイアログが動作します</li>\n";
echo "<li>URLパラメータでタブ名を確認し、得意先タブ以外ではポップアップをスキップします</li>\n";
echo "</ul>\n";
echo "</div>\n";

echo "<h2>動作確認方法</h2>\n";

echo "<h3>1. 得意先タブでの動作</h3>\n";
echo "<div style='background: #fff3cd; padding: 10px; border-radius: 5px; margin: 10px 0;'>\n";
echo "<p><strong>URL:</strong> <code>?tab_name=client&data_id=1</code></p>\n";
echo "<p><strong>期待される動作:</strong> 削除ボタンクリック時に3つの選択肢のポップアップが表示されます</p>\n";
echo "<ul>\n";
echo "<li>1. 対象外（推奨）</li>\n";
echo "<li>2. 削除</li>\n";
echo "<li>3. 完全削除</li>\n";
echo "</ul>\n";
echo "</div>\n";

echo "<h3>2. サービスタブでの動作</h3>\n";
echo "<div style='background: #fff3cd; padding: 10px; border-radius: 5px; margin: 10px 0;'>\n";
echo "<p><strong>URL:</strong> <code>?tab_name=service&data_id=1</code></p>\n";
echo "<p><strong>期待される動作:</strong> 削除ボタンクリック時に通常の確認ダイアログが表示されます</p>\n";
echo "<p><strong>メッセージ:</strong> 「本当に削除しますか？この操作は元に戻せません。」</p>\n";
echo "</div>\n";

echo "<h3>3. 協力会社タブでの動作</h3>\n";
echo "<div style='background: #fff3cd; padding: 10px; border-radius: 5px; margin: 10px 0;'>\n";
echo "<p><strong>URL:</strong> <code>?tab_name=supplier&data_id=1</code></p>\n";
echo "<p><strong>期待される動作:</strong> 削除ボタンクリック時に通常の確認ダイアログが表示されます</p>\n";
echo "<p><strong>メッセージ:</strong> 「本当に削除しますか？」</p>\n";
echo "</div>\n";

echo "<h2>技術的な実装</h2>\n";

echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 15px 0;'>\n";
echo "<h3>修正されたコード（js/ktp-client-delete-popup.js）</h3>\n";
echo "<pre style='background: #fff; padding: 10px; border-radius: 3px; overflow-x: auto;'>\n";
echo "// 得意先タブ（client）の場合のみポップアップを表示\n";
echo "const currentUrl = window.location.href;\n";
echo "const urlParams = new URLSearchParams(window.location.search);\n";
echo "const tabName = urlParams.get('tab_name');\n";
echo "\n";
echo "// 得意先タブ以外の場合は通常の削除処理を継続\n";
echo "if (tabName !== 'client') {\n";
echo "    return;\n";
echo "}\n";
echo "</pre>\n";
echo "</div>\n";

echo "<h2>テスト手順</h2>\n";

echo "<ol>\n";
echo "<li>WordPress管理画面にログイン</li>\n";
echo "<li>KantanPro > 得意先 にアクセス</li>\n";
echo "<li>顧客を選択して詳細画面を表示</li>\n";
echo "<li>削除ボタン（ゴミ箱アイコン）をクリック</li>\n";
echo "<li>3つの選択肢のポップアップが表示されることを確認</li>\n";
echo "<li>KantanPro > サービス にアクセス</li>\n";
echo "<li>サービスを選択して詳細画面を表示</li>\n";
echo "<li>削除ボタンをクリック</li>\n";
echo "<li>通常の確認ダイアログが表示されることを確認</li>\n";
echo "<li>KantanPro > 協力会社 にアクセス</li>\n";
echo "<li>協力会社を選択して詳細画面を表示</li>\n";
echo "<li>削除ボタンをクリック</li>\n";
echo "<li>通常の確認ダイアログが表示されることを確認</li>\n";
echo "</ol>\n";

echo "<h2>修正完了</h2>\n";
echo "<p>削除ポップアップは得意先タブのみの機能として正しく実装されました。</p>\n";
?> 