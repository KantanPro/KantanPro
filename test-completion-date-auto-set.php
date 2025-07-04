<?php
/**
 * 完了日自動設定機能テストファイル
 * 
 * このファイルは、進捗ステータス変更時の完了日自動設定機能をテストするためのものです。
 * ブラウザで直接アクセスしてテスト結果を確認してください。
 */

// WordPress環境を読み込み
require_once('../../../wp-load.php');

echo "<h1>完了日自動設定機能テスト</h1>";

// 権限チェック
if (!current_user_can('edit_posts') && !current_user_can('ktpwp_access')) {
    echo "<p style='color: red;'>❌ 権限がありません。管理者または編集権限を持つユーザーでログインしてください。</p>";
    exit;
}

global $wpdb;
$table_name = $wpdb->prefix . 'ktp_order';

echo "<h2>1. 現在のデータベース状況確認</h2>";

// テーブルの存在確認
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'");
if (!$table_exists) {
    echo "<p style='color: red;'>❌ テーブル {$table_name} が存在しません</p>";
    exit;
}

echo "<p style='color: green;'>✅ テーブル {$table_name} が存在します</p>";

// completion_dateカラムの存在確認
$columns = $wpdb->get_results("SHOW COLUMNS FROM `{$table_name}`");
$column_names = array_column($columns, 'Field');

if (!in_array('completion_date', $column_names)) {
    echo "<p style='color: red;'>❌ completion_dateカラムが存在しません</p>";
    exit;
}

echo "<p style='color: green;'>✅ completion_dateカラムが存在します</p>";

// テスト用データの確認
$test_orders = $wpdb->get_results("SELECT id, customer_name, progress, completion_date FROM {$table_name} ORDER BY id DESC LIMIT 5");

echo "<h2>2. 現在の受注書データ（最新5件）</h2>";
echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr style='background-color: #f0f0f0;'>";
echo "<th>ID</th><th>顧客名</th><th>進捗</th><th>完了日</th>";
echo "</tr>";

foreach ($test_orders as $order) {
    $progress_labels = [
        1 => '受付中',
        2 => '見積中', 
        3 => '受注',
        4 => '完了',
        5 => '請求済',
        6 => '入金済',
        7 => 'ボツ'
    ];
    
    $progress_label = isset($progress_labels[$order->progress]) ? $progress_labels[$order->progress] : '不明';
    $completion_date = $order->completion_date ? $order->completion_date : '未設定';
    
    echo "<tr>";
    echo "<td>{$order->id}</td>";
    echo "<td>{$order->customer_name}</td>";
    echo "<td>{$progress_label} ({$order->progress})</td>";
    echo "<td>{$completion_date}</td>";
    echo "</tr>";
}
echo "</table>";

echo "<h2>3. 機能テスト</h2>";

echo "<h3>3.1 進捗ステータス変更テスト</h3>";
echo "<p>以下のテストを実行してください：</p>";
echo "<ol>";
echo "<li>仕事リストタブに移動</li>";
echo "<li>任意の受注書の進捗を「完了」に変更</li>";
echo "<li>完了日が自動的に設定されることを確認</li>";
echo "<li>進捗を「受付中」「見積中」「受注」のいずれかに変更</li>";
echo "<li>完了日が自動的にクリアされることを確認</li>";
echo "</ol>";

echo "<h3>3.2 JavaScript機能テスト</h3>";
echo "<p>ブラウザの開発者ツール（F12）を開いて、コンソールタブで以下のログを確認してください：</p>";
echo "<ul>";
echo "<li>進捗変更時: <code>[DELIVERY-DATES] 進捗プルダウンが変更されました</code></li>";
echo "<li>完了日自動設定時: <code>[DELIVERY-DATES] 進捗が完了に変更されたため、完了日を自動設定します</code></li>";
echo "<li>完了日クリア時: <code>[DELIVERY-DATES] 進捗が受注以前に変更されたため、完了日をクリアします</code></li>";
echo "<li>完了日保存時: <code>[DELIVERY-DATES] 完了日が正常に保存されました</code></li>";
echo "</ul>";

echo "<h3>3.3 対象進捗ステータス</h3>";
echo "<ul>";
echo "<li><strong>受付中</strong> (progress = 1) - 完了日をクリア</li>";
echo "<li><strong>見積中</strong> (progress = 2) - 完了日をクリア</li>";
echo "<li><strong>受注</strong> (progress = 3) - 完了日をクリア</li>";
echo "<li><strong>完了</strong> (progress = 4) - 完了日を自動設定</li>";
echo "</ul>";

echo "<h2>4. 実装内容</h2>";
echo "<h3>4.1 バックエンド（PHP）</h3>";
echo "<ul>";
echo "<li><strong>includes/class-tab-list.php</strong>: 進捗変更時の完了日自動設定・クリア処理</li>";
echo "<li><strong>includes/class-tab-order.php</strong>: 受注書詳細での進捗変更処理</li>";
echo "<li><strong>includes/class-ktpwp-ajax.php</strong>: 完了日フィールドの自動保存Ajaxハンドラー</li>";
echo "</ul>";

echo "<h3>4.2 フロントエンド（JavaScript）</h3>";
echo "<ul>";
echo "<li><strong>js/ktp-delivery-dates.js</strong>: 進捗変更時の完了日自動設定・クリア処理</li>";
echo "<li>完了日フィールドの変更監視と自動保存</li>";
echo "</ul>";

echo "<h2>5. テスト結果</h2>";
echo "<p>上記のテストを実行後、以下の点を確認してください：</p>";
echo "<ul>";
echo "<li>✅ 進捗を「完了」に変更した際に完了日が自動設定される</li>";
echo "<li>✅ 進捗を「受付中」「見積中」「受注」に変更した際に完了日がクリアされる</li>";
echo "<li>✅ 完了日フィールドを手動で変更した際にデータベースに保存される</li>";
echo "<li>✅ コンソールログに適切なメッセージが表示される</li>";
echo "</ul>";

echo "<p><strong>実装完了！</strong> 仕事リストで進捗ステータス変更時の完了日自動設定機能をテストしてください。</p>";
?> 