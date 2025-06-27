<?php
/**
 * テーブル構造デバッグファイル
 */

// WordPress環境を読み込み
require_once('../../../wp-load.php');

echo "<h1>テーブル構造デバッグ</h1>";

global $wpdb;

echo "<h2>1. WordPress設定確認</h2>";
echo "テーブルプレフィックス: " . $wpdb->prefix . "<br>";
echo "データベース名: " . DB_NAME . "<br>";

echo "<h2>2. 受注書テーブル確認</h2>";
$table_name = $wpdb->prefix . 'ktp_order';
echo "対象テーブル名: {$table_name}<br>";

// テーブルの存在確認
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'");
if ($table_exists) {
    echo "✅ テーブル存在<br>";
} else {
    echo "❌ テーブル不存在<br>";
    
    // 全テーブル一覧を表示
    echo "<h3>データベース内の全テーブル:</h3>";
    $all_tables = $wpdb->get_results("SHOW TABLES");
    echo "<ul>";
    foreach ($all_tables as $table) {
        $table_array = get_object_vars($table);
        $table_name_actual = array_values($table_array)[0];
        $is_target = ($table_name_actual === $table_name);
        $marker = $is_target ? '🎯' : '📋';
        echo "<li>{$marker} {$table_name_actual}</li>";
    }
    echo "</ul>";
    
    // 類似テーブルを検索
    echo "<h3>類似テーブルの検索:</h3>";
    $similar_tables = $wpdb->get_results("SHOW TABLES LIKE '%ktp%'");
    if ($similar_tables) {
        echo "<ul>";
        foreach ($similar_tables as $table) {
            $table_array = get_object_vars($table);
            $table_name_actual = array_values($table_array)[0];
            echo "<li>📋 {$table_name_actual}</li>";
        }
        echo "</ul>";
    }
    exit;
}

echo "<h2>3. カラム構造詳細</h2>";
$columns = $wpdb->get_results("SHOW COLUMNS FROM `{$table_name}`");

if ($columns) {
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
}

echo "<h2>4. 納期フィールドの存在確認</h2>";
$column_names = array_column($columns, 'Field');
$desired_exists = in_array('desired_delivery_date', $column_names);
$expected_exists = in_array('expected_delivery_date', $column_names);

echo "希望納期フィールド (desired_delivery_date): " . ($desired_exists ? "✅ 存在" : "❌ 不存在") . "<br>";
echo "納品予定日フィールド (expected_delivery_date): " . ($expected_exists ? "✅ 存在" : "❌ 不存在") . "<br>";

echo "<h2>5. 手動でフィールドを追加</h2>";

if (!$desired_exists) {
    echo "<form method='post' style='margin: 10px 0;'>";
    echo "<input type='hidden' name='add_desired' value='1'>";
    echo "<button type='submit' style='background: #007cba; color: white; padding: 10px; border: none; border-radius: 4px;'>希望納期フィールドを追加</button>";
    echo "</form>";
}

if (!$expected_exists) {
    echo "<form method='post' style='margin: 10px 0;'>";
    echo "<input type='hidden' name='add_expected' value='1'>";
    echo "<button type='submit' style='background: #007cba; color: white; padding: 10px; border: none; border-radius: 4px;'>納品予定日フィールドを追加</button>";
    echo "</form>";
}

// フィールド追加処理
if (isset($_POST['add_desired'])) {
    echo "<h3>希望納期フィールド追加中...</h3>";
    $sql = "ALTER TABLE `{$table_name}` ADD COLUMN `desired_delivery_date` DATE NULL DEFAULT NULL COMMENT '希望納期'";
    echo "実行SQL: {$sql}<br>";
    
    $result = $wpdb->query($sql);
    
    if ($result !== false) {
        echo "✅ 希望納期フィールドの追加に成功しました<br>";
    } else {
        echo "❌ 希望納期フィールドの追加に失敗しました<br>";
        echo "エラー: " . $wpdb->last_error . "<br>";
    }
}

if (isset($_POST['add_expected'])) {
    echo "<h3>納品予定日フィールド追加中...</h3>";
    $sql = "ALTER TABLE `{$table_name}` ADD COLUMN `expected_delivery_date` DATE NULL DEFAULT NULL COMMENT '納品予定日'";
    echo "実行SQL: {$sql}<br>";
    
    $result = $wpdb->query($sql);
    
    if ($result !== false) {
        echo "✅ 納品予定日フィールドの追加に成功しました<br>";
    } else {
        echo "❌ 納品予定日フィールドの追加に失敗しました<br>";
        echo "エラー: " . $wpdb->last_error . "<br>";
    }
}

echo "<h2>6. テストデータ確認</h2>";
$test_order = $wpdb->get_row("SELECT * FROM `{$table_name}` ORDER BY id DESC LIMIT 1");
if ($test_order) {
    echo "最新の受注書ID: {$test_order->id}<br>";
    echo "案件名: " . esc_html($test_order->project_name) . "<br>";
    
    if ($desired_exists) {
        echo "希望納期: " . esc_html($test_order->desired_delivery_date ?? '未設定') . "<br>";
    }
    
    if ($expected_exists) {
        echo "納品予定日: " . esc_html($test_order->expected_delivery_date ?? '未設定') . "<br>";
    }
} else {
    echo "受注書データがありません<br>";
}

echo "<h2>7. 手動テスト更新</h2>";
if ($test_order && ($desired_exists || $expected_exists)) {
    echo "<form method='post' style='margin: 10px 0;'>";
    echo "<input type='hidden' name='test_update' value='1'>";
    echo "<input type='hidden' name='order_id' value='{$test_order->id}'>";
    
    if ($desired_exists) {
        echo "希望納期: <input type='date' name='desired_date' value='" . date('Y-m-d') . "'><br>";
    }
    
    if ($expected_exists) {
        echo "納品予定日: <input type='date' name='expected_date' value='" . date('Y-m-d', strtotime('+1 week')) . "'><br>";
    }
    
    echo "<button type='submit' style='background: #28a745; color: white; padding: 10px; border: none; border-radius: 4px;'>テスト更新</button>";
    echo "</form>";
}

// テスト更新処理
if (isset($_POST['test_update']) && isset($_POST['order_id'])) {
    $order_id = absint($_POST['order_id']);
    $desired_date = isset($_POST['desired_date']) ? sanitize_text_field($_POST['desired_date']) : '';
    $expected_date = isset($_POST['expected_date']) ? sanitize_text_field($_POST['expected_date']) : '';
    
    echo "<h3>テスト更新結果:</h3>";
    
    $update_data = array();
    if (!empty($desired_date) && $desired_exists) {
        $update_data['desired_delivery_date'] = $desired_date;
    }
    if (!empty($expected_date) && $expected_exists) {
        $update_data['expected_delivery_date'] = $expected_date;
    }
    
    if (!empty($update_data)) {
        $result = $wpdb->update(
            $table_name,
            $update_data,
            array('id' => $order_id),
            array_fill(0, count($update_data), '%s'),
            array('%d')
        );
        
        if ($result !== false) {
            echo "✅ テスト更新に成功しました<br>";
            echo "更新されたフィールド: " . implode(', ', array_keys($update_data)) . "<br>";
        } else {
            echo "❌ テスト更新に失敗しました: " . $wpdb->last_error . "<br>";
        }
    }
}

echo "<p><a href='javascript:location.reload()' style='background: #28a745; color: white; padding: 10px; text-decoration: none; border-radius: 4px;'>ページを再読み込み</a></p>";
?> 