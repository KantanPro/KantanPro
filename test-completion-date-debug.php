<?php
/**
 * 完了日データベース状態確認用テストファイル
 */

// WordPressを読み込み
require_once('../../../wp-load.php');

// データベース接続
global $wpdb;
$table_name = $wpdb->prefix . 'ktp_order';

echo "<h2>完了日データベース状態確認</h2>";

// テーブル構造を確認
echo "<h3>1. テーブル構造確認</h3>";
$columns = $wpdb->get_results("SHOW COLUMNS FROM {$table_name}");
echo "<table border='1' style='border-collapse: collapse;'>";
echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
foreach ($columns as $column) {
    if ($column->Field === 'completion_date') {
        echo "<tr style='background: #ffeb3b;'>";
    } else {
        echo "<tr>";
    }
    echo "<td>{$column->Field}</td>";
    echo "<td>{$column->Type}</td>";
    echo "<td>{$column->Null}</td>";
    echo "<td>{$column->Key}</td>";
    echo "<td>{$column->Default}</td>";
    echo "<td>{$column->Extra}</td>";
    echo "</tr>";
}
echo "</table>";

// 受注書ID 12のデータを確認
echo "<h3>2. 受注書ID 12のデータ確認</h3>";
$order = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_name} WHERE id = %d", 12));

if ($order) {
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>フィールド</th><th>値</th><th>データ型</th></tr>";
    
    foreach ($order as $field => $value) {
        if ($field === 'completion_date') {
            echo "<tr style='background: #ffeb3b;'>";
        } else {
            echo "<tr>";
        }
        echo "<td>{$field}</td>";
        echo "<td>" . var_export($value, true) . "</td>";
        echo "<td>" . gettype($value) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // 完了日の詳細確認
    echo "<h3>3. 完了日の詳細確認</h3>";
    echo "<p><strong>Raw completion_date:</strong> " . var_export($order->completion_date, true) . "</p>";
    echo "<p><strong>isEmpty check:</strong> " . (empty($order->completion_date) ? 'true' : 'false') . "</p>";
    echo "<p><strong>=== '0000-00-00' check:</strong> " . ($order->completion_date === '0000-00-00' ? 'true' : 'false') . "</p>";
    echo "<p><strong>=== NULL check:</strong> " . ($order->completion_date === null ? 'true' : 'false') . "</p>";
    echo "<p><strong>strlen:</strong> " . strlen($order->completion_date) . "</p>";
    
    // 実際のPHPコードでの表示確認
    echo "<h3>4. PHPコードでの表示確認</h3>";
    $completion_date = isset($order->completion_date) ? $order->completion_date : '';
    echo "<p><strong>isset() result:</strong> " . (isset($order->completion_date) ? 'true' : 'false') . "</p>";
    echo "<p><strong>Final \$completion_date value:</strong> " . var_export($completion_date, true) . "</p>";
    
} else {
    echo "<p>受注書ID 12が見つかりません。</p>";
}

// 最近更新されたデータを確認
echo "<h3>5. 最近更新されたデータ確認</h3>";
$recent_orders = $wpdb->get_results("SELECT id, progress, completion_date, time FROM {$table_name} ORDER BY time DESC LIMIT 5");

if ($recent_orders) {
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>進捗</th><th>完了日</th><th>更新時刻</th></tr>";
    foreach ($recent_orders as $order) {
        echo "<tr>";
        echo "<td>{$order->id}</td>";
        echo "<td>{$order->progress}</td>";
        echo "<td>" . var_export($order->completion_date, true) . "</td>";
        echo "<td>" . date('Y-m-d H:i:s', $order->time) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// SQLクエリでの直接確認
echo "<h3>6. SQLクエリでの直接確認</h3>";
$sql = "SELECT id, progress, completion_date, UNIX_TIMESTAMP(completion_date) as completion_timestamp FROM {$table_name} WHERE id = 12";
echo "<p><strong>実行SQL:</strong> {$sql}</p>";
$result = $wpdb->get_row($sql);
if ($result) {
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>フィールド</th><th>値</th></tr>";
    foreach ($result as $field => $value) {
        echo "<tr>";
        echo "<td>{$field}</td>";
        echo "<td>" . var_export($value, true) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}
?> 