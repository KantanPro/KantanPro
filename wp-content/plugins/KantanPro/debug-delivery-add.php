<?php
/**
 * 納期フィールド追加デバッグファイル
 */

// WordPress環境を読み込み
require_once('../../../wp-load.php');

echo "<h1>納期フィールド追加デバッグ</h1>";

global $wpdb;

$table_name = $wpdb->prefix . 'ktp_order';

echo "<h2>1. 基本情報確認</h2>";
echo "テーブル名: {$table_name}<br>";
echo "WordPressプレフィックス: " . $wpdb->prefix . "<br>";
echo "データベース名: " . DB_NAME . "<br>";

echo "<h2>2. テーブル存在確認</h2>";
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'");
if ($table_exists) {
    echo "✅ テーブル存在: {$table_name}<br>";
} else {
    echo "❌ テーブル不存在: {$table_name}<br>";
    
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

echo "<h2>3. 現在のカラム構造</h2>";
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
} else {
    echo "❌ カラム情報の取得に失敗<br>";
    echo "エラー: " . $wpdb->last_error . "<br>";
    exit;
}

echo "<h2>4. 納期フィールドの存在確認</h2>";
$column_names = array_column($columns, 'Field');
$desired_exists = in_array('desired_delivery_date', $column_names);
$expected_exists = in_array('expected_delivery_date', $column_names);

echo "希望納期フィールド (desired_delivery_date): " . ($desired_exists ? "✅ 存在" : "❌ 不存在") . "<br>";
echo "納品予定日フィールド (expected_delivery_date): " . ($expected_exists ? "✅ 存在" : "❌ 不存在") . "<br>";

echo "<h2>5. 手動でフィールドを追加</h2>";

if (!$desired_exists) {
    echo "<h3>希望納期フィールドを手動追加</h3>";
    echo "<form method='post' style='margin: 10px 0;'>";
    echo "<input type='hidden' name='add_desired' value='1'>";
    echo "<button type='submit' style='background: #007cba; color: white; padding: 10px; border: none; border-radius: 4px;'>希望納期フィールドを追加</button>";
    echo "</form>";
}

if (!$expected_exists) {
    echo "<h3>納品予定日フィールドを手動追加</h3>";
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

echo "<h2>6. 直接SQL実行</h2>";
echo "<p>以下のSQLをphpMyAdminで直接実行することもできます：</p>";

if (!$desired_exists) {
    echo "<pre style='background: #f5f5f5; padding: 10px; border: 1px solid #ccc;'>";
    echo "ALTER TABLE `{$table_name}` ADD COLUMN `desired_delivery_date` DATE NULL DEFAULT NULL COMMENT '希望納期';";
    echo "</pre>";
}

if (!$expected_exists) {
    echo "<pre style='background: #f5f5f5; padding: 10px; border: 1px solid #ccc;'>";
    echo "ALTER TABLE `{$table_name}` ADD COLUMN `expected_delivery_date` DATE NULL DEFAULT NULL COMMENT '納品予定日';";
    echo "</pre>";
}

echo "<p><a href='javascript:location.reload()' style='background: #28a745; color: white; padding: 10px; text-decoration: none; border-radius: 4px;'>ページを再読み込み</a></p>";
?> 