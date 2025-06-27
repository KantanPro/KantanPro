<?php
/**
 * 納期フィールドのみ追加ファイル
 */

// WordPress環境を読み込み
require_once('../../../wp-load.php');

echo "<h1>納期フィールドのみ追加</h1>";

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

echo "<h2>2. 納期フィールドの追加</h2>";

$success_count = 0;

// 希望納期フィールドの追加
if (!in_array('desired_delivery_date', $column_names)) {
    echo "<h3>希望納期フィールドを追加中...</h3>";
    $desired_sql = "ALTER TABLE `{$table_name}` ADD COLUMN `desired_delivery_date` DATE NULL DEFAULT NULL COMMENT '希望納期'";
    echo "実行SQL: {$desired_sql}<br>";
    $result = $wpdb->query($desired_sql);
    
    if ($result !== false) {
        echo "✅ 希望納期フィールドの追加に成功<br>";
        $success_count++;
    } else {
        echo "❌ 希望納期フィールドの追加に失敗: " . $wpdb->last_error . "<br>";
    }
} else {
    echo "✅ 希望納期フィールドは既に存在します<br>";
    $success_count++;
}

// 納品予定日フィールドの追加
if (!in_array('expected_delivery_date', $column_names)) {
    echo "<h3>納品予定日フィールドを追加中...</h3>";
    $expected_sql = "ALTER TABLE `{$table_name}` ADD COLUMN `expected_delivery_date` DATE NULL DEFAULT NULL COMMENT '納品予定日'";
    echo "実行SQL: {$expected_sql}<br>";
    $result = $wpdb->query($expected_sql);
    
    if ($result !== false) {
        echo "✅ 納品予定日フィールドの追加に成功<br>";
        $success_count++;
    } else {
        echo "❌ 納品予定日フィールドの追加に失敗: " . $wpdb->last_error . "<br>";
    }
} else {
    echo "✅ 納品予定日フィールドは既に存在します<br>";
    $success_count++;
}

echo "<h2>3. 結果確認</h2>";

if ($success_count == 2) {
    echo "<h2 style='color: green;'>🎉 納期フィールドの追加が完了しました！</h2>";
    echo "<p>受注書の納期機能が使用できるようになりました。</p>";
    echo "<p><strong>次のステップ:</strong></p>";
    echo "<ol>";
    echo "<li>WordPress管理画面で受注書を開く</li>";
    echo "<li>案件名の右側に納期入力フィールドが表示されることを確認</li>";
    echo "<li>日付を選択して保存をテスト</li>";
    echo "</ol>";
} else {
    echo "<h2 style='color: orange;'>⚠️ 一部のフィールドの追加に失敗しました</h2>";
    echo "<p>手動で確認してください。</p>";
}

echo "<h3>現在のテーブル構造:</h3>";

// 最終的なテーブル構造を確認
$final_columns = $wpdb->get_results("SHOW COLUMNS FROM `{$table_name}`");

echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr style='background-color: #f0f0f0;'>";
echo "<th>フィールド名</th><th>型</th><th>NULL</th><th>キー</th><th>デフォルト</th><th>Extra</th>";
echo "</tr>";

foreach ($final_columns as $column) {
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

echo "<p><a href='javascript:location.reload()' style='background: #28a745; color: white; padding: 10px; text-decoration: none; border-radius: 4px;'>ページを再読み込み</a></p>";
?> 