<?php
/**
 * データベース自動修正ファイル
 */

// WordPress環境を読み込み
require_once('../../../wp-load.php');

echo "<h1>データベース自動修正実行</h1>";

global $wpdb;

$table_name = $wpdb->prefix . 'ktp_order';

echo "<h2>1. 現在の状況確認</h2>";

// テーブルの存在確認
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'");
if (!$table_exists) {
    echo "❌ テーブル {$table_name} が存在しません<br>";
    exit;
}

echo "✅ テーブル {$table_name} が存在します<br>";

// 現在のカラム構造を取得
$columns = $wpdb->get_results("SHOW COLUMNS FROM `{$table_name}`");
$column_names = array_column($columns, 'Field');

echo "<h2>2. 自動修正実行</h2>";

// 1. created_atカラムの修正
echo "<h3>created_atカラムの修正中...</h3>";

if (in_array('created_at', $column_names)) {
    // created_atカラムが存在する場合、削除して再作成
    $drop_sql = "ALTER TABLE `{$table_name}` DROP COLUMN `created_at`";
    echo "実行SQL: {$drop_sql}<br>";
    $result = $wpdb->query($drop_sql);
    
    if ($result !== false) {
        echo "✅ created_atカラムの削除に成功<br>";
    } else {
        echo "❌ created_atカラムの削除に失敗: " . $wpdb->last_error . "<br>";
    }
}

// created_atカラムを再作成
$create_sql = "ALTER TABLE `{$table_name}` ADD COLUMN `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP";
echo "実行SQL: {$create_sql}<br>";
$result = $wpdb->query($create_sql);

if ($result !== false) {
    echo "✅ created_atカラムの再作成に成功<br>";
} else {
    echo "❌ created_atカラムの再作成に失敗: " . $wpdb->last_error . "<br>";
}

// 2. 納期フィールドの追加
echo "<h3>納期フィールドの追加中...</h3>";

// カラム構造を再取得
$columns = $wpdb->get_results("SHOW COLUMNS FROM `{$table_name}`");
$column_names = array_column($columns, 'Field');

// 希望納期フィールドの追加
if (!in_array('desired_delivery_date', $column_names)) {
    $desired_sql = "ALTER TABLE `{$table_name}` ADD COLUMN `desired_delivery_date` DATE NULL DEFAULT NULL COMMENT '希望納期'";
    echo "実行SQL: {$desired_sql}<br>";
    $result = $wpdb->query($desired_sql);
    
    if ($result !== false) {
        echo "✅ 希望納期フィールドの追加に成功<br>";
    } else {
        echo "❌ 希望納期フィールドの追加に失敗: " . $wpdb->last_error . "<br>";
    }
} else {
    echo "希望納期フィールドは既に存在します<br>";
}

// 納品予定日フィールドの追加
if (!in_array('expected_delivery_date', $column_names)) {
    $expected_sql = "ALTER TABLE `{$table_name}` ADD COLUMN `expected_delivery_date` DATE NULL DEFAULT NULL COMMENT '納品予定日'";
    echo "実行SQL: {$expected_sql}<br>";
    $result = $wpdb->query($expected_sql);
    
    if ($result !== false) {
        echo "✅ 納品予定日フィールドの追加に成功<br>";
    } else {
        echo "❌ 納品予定日フィールドの追加に失敗: " . $wpdb->last_error . "<br>";
    }
} else {
    echo "納品予定日フィールドは既に存在します<br>";
}

echo "<h2>3. 最終確認</h2>";

// 最終的なテーブル構造を確認
$final_columns = $wpdb->get_results("SHOW COLUMNS FROM `{$table_name}`");

echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr style='background-color: #f0f0f0;'>";
echo "<th>フィールド名</th><th>型</th><th>NULL</th><th>キー</th><th>デフォルト</th><th>Extra</th>";
echo "</tr>";

foreach ($final_columns as $column) {
    $is_target = in_array($column->Field, ['created_at', 'desired_delivery_date', 'expected_delivery_date']);
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

// 重要なフィールドの存在確認
$final_column_names = array_column($final_columns, 'Field');
$created_at_exists = in_array('created_at', $final_column_names);
$desired_exists = in_array('desired_delivery_date', $final_column_names);
$expected_exists = in_array('expected_delivery_date', $final_column_names);

echo "<h3>修正結果:</h3>";
echo "created_atカラム: " . ($created_at_exists ? "✅ 存在" : "❌ 不存在") . "<br>";
echo "希望納期フィールド: " . ($desired_exists ? "✅ 存在" : "❌ 不存在") . "<br>";
echo "納品予定日フィールド: " . ($expected_exists ? "✅ 存在" : "❌ 不存在") . "<br>";

if ($created_at_exists && $desired_exists && $expected_exists) {
    echo "<h2 style='color: green;'>🎉 データベース修正が完了しました！</h2>";
    echo "<p>受注書の納期機能が使用できるようになりました。</p>";
} else {
    echo "<h2 style='color: red;'>⚠️ 一部の修正に失敗しました</h2>";
    echo "<p>手動で確認してください。</p>";
}

echo "<p><a href='javascript:location.reload()' style='background: #28a745; color: white; padding: 10px; text-decoration: none; border-radius: 4px;'>ページを再読み込み</a></p>";
?> 