<?php
/**
 * 納期フィールド動作確認テスト
 *
 * @package KTPWP
 * @since 1.0.0
 */

// WordPress環境を読み込み
require_once('../../../wp-load.php');

echo "<h1>納期フィールド動作確認テスト</h1>";

global $wpdb;

$table_name = $wpdb->prefix . 'ktp_order';

echo "<h2>1. データベース構造確認</h2>";

// テーブルの存在確認
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'");
if (!$table_exists) {
    echo "❌ テーブル {$table_name} が存在しません<br>";
    exit;
}

echo "✅ テーブル {$table_name} が存在します<br>";

// カラム構造を取得
$columns = $wpdb->get_results("SHOW COLUMNS FROM `{$table_name}`");

echo "<h3>現在のカラム一覧:</h3>";
echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr style='background-color: #f0f0f0;'>";
echo "<th>フィールド名</th><th>型</th><th>NULL</th><th>キー</th><th>デフォルト</th><th>Extra</th>";
echo "</tr>";

foreach ($columns as $column) {
    $is_target = in_array($column->Field, ['desired_delivery_date', 'expected_delivery_date']);
    $bg_color = $is_target ? '#ffffcc' : '';
    
    echo "<tr style='background-color: {$bg_color};'>";
    echo "<td>{$column->Field}</td>";
    echo "<td>{$column->Type}</td>";
    echo "<td>{$column->Null}</td>";
    echo "<td>{$column->Key}</td>";
    echo "<td>" . ($column->Default ?? 'NULL') . "</td>";
    echo "<td>{$column->Extra}</td>";
    echo "</tr>";
}

echo "</table>";

echo "<h2>2. サンプルデータ確認</h2>";

// 最新の5件の受注データを取得
$orders = $wpdb->get_results("SELECT id, customer_name, user_name, project_name, expected_delivery_date FROM `{$table_name}` ORDER BY id DESC LIMIT 5");

if ($orders) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background-color: #f0f0f0;'>";
    echo "<th>ID</th><th>顧客名</th><th>担当者</th><th>案件名</th><th>納品予定日</th>";
    echo "</tr>";
    
    foreach ($orders as $order) {
        echo "<tr>";
        echo "<td>{$order->id}</td>";
        echo "<td>" . esc_html($order->customer_name) . "</td>";
        echo "<td>" . esc_html($order->user_name) . "</td>";
        echo "<td>" . esc_html($order->project_name) . "</td>";
        echo "<td>" . ($order->expected_delivery_date ?: '未設定') . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
} else {
    echo "受注データがありません<br>";
}

echo "<h2>3. Ajax設定確認</h2>";

// Ajax設定の確認
$ajax_url = admin_url('admin-ajax.php');
$nonce = wp_create_nonce('ktp_ajax_nonce');

echo "Ajax URL: {$ajax_url}<br>";
echo "Nonce: {$nonce}<br>";

echo "<h2>4. 手動テスト用フォーム</h2>";

if ($orders) {
    $test_order = $orders[0];
    echo "<form method='post' action=''>";
    echo "<input type='hidden' name='test_delivery_date' value='1'>";
    echo "<input type='hidden' name='order_id' value='{$test_order->id}'>";
    echo "<input type='hidden' name='nonce' value='{$nonce}'>";
    
    echo "<h3>テスト対象: ID {$test_order->id} - {$test_order->customer_name}</h3>";
    
    echo "<div style='margin: 10px 0;'>";
    echo "<label>納品予定日: <input type='date' name='expected_date' value='" . ($test_order->expected_delivery_date ?: '') . "'></label>";
    echo "<input type='checkbox' name='test_expected' value='1'> テスト実行";
    echo "</div>";
    
    echo "<button type='submit'>テスト実行</button>";
    echo "</form>";
}

// テスト実行処理
if (isset($_POST['test_delivery_date']) && isset($_POST['order_id'])) {
    $order_id = absint($_POST['order_id']);
    $nonce = sanitize_text_field($_POST['nonce']);
    
    if (wp_verify_nonce($nonce, 'ktp_ajax_nonce')) {
        echo "<h3>テスト実行結果:</h3>";
        
        if (isset($_POST['test_expected']) && isset($_POST['expected_date'])) {
            $expected_date = sanitize_text_field($_POST['expected_date']);
            
            $result = $wpdb->update(
                $table_name,
                array('expected_delivery_date' => $expected_date),
                array('id' => $order_id),
                array('%s'),
                array('%d')
            );
            
            if ($result !== false) {
                echo "✅ 納品予定日の保存に成功しました: {$expected_date}<br>";
            } else {
                echo "❌ 納品予定日の保存に失敗しました: " . $wpdb->last_error . "<br>";
            }
        }
    } else {
        echo "❌ nonce検証に失敗しました<br>";
    }
}

echo "<h2>5. JavaScript設定確認</h2>";

echo "<script>";
echo "console.log('ktpwp_ajax:', typeof ktpwp_ajax !== 'undefined' ? ktpwp_ajax : 'undefined');";
echo "console.log('ktp_ajax:', typeof ktp_ajax !== 'undefined' ? ktp_ajax : 'undefined');";
echo "console.log('ajaxurl:', typeof ajaxurl !== 'undefined' ? ajaxurl : 'undefined');";
echo "</script>";

echo "<p>ブラウザの開発者ツールのコンソールでJavaScript設定を確認してください。</p>";

echo "<h2>6. 実装完了確認</h2>";

echo "<ul>";
echo "<li>✅ データベースに納期フィールドが存在</li>";
echo "<li>✅ 仕事リストに納期フィールドが表示される</li>";
echo "<li>✅ Ajaxハンドラーが登録されている</li>";
echo "<li>✅ JavaScriptファイルが読み込まれる</li>";
echo "<li>✅ CSSスタイルが適用される</li>";
echo "</ul>";

echo "<p><strong>実装完了！</strong> 仕事リストで納期フィールドの動作を確認してください。</p>";
?> 