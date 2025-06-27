<?php
/**
 * created_atカラム修正ファイル
 */

// WordPress環境を読み込み
require_once('../../../wp-load.php');

echo "<h1>created_atカラム修正</h1>";

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

// created_atカラムの確認
$created_at_column = $wpdb->get_row("SHOW COLUMNS FROM `{$table_name}` LIKE 'created_at'");
if ($created_at_column) {
    echo "<h3>created_atカラムの現在の設定:</h3>";
    echo "フィールド名: {$created_at_column->Field}<br>";
    echo "型: {$created_at_column->Type}<br>";
    echo "NULL: {$created_at_column->Null}<br>";
    echo "デフォルト: " . ($created_at_column->Default ?? 'NULL') . "<br>";
    echo "Extra: {$created_at_column->Extra}<br>";
} else {
    echo "created_atカラムは存在しません<br>";
}

echo "<h2>2. 修正方法の選択</h2>";

echo "<h3>方法1: created_atカラムを削除して再作成</h3>";
echo "<form method='post' style='margin: 10px 0;'>";
echo "<input type='hidden' name='fix_method' value='recreate'>";
echo "<button type='submit' style='background: #dc3545; color: white; padding: 10px; border: none; border-radius: 4px;'>created_atカラムを削除して再作成</button>";
echo "</form>";

echo "<h3>方法2: created_atカラムをNULL許可に変更</h3>";
echo "<form method='post' style='margin: 10px 0;'>";
echo "<input type='hidden' name='fix_method' value='nullify'>";
echo "<button type='submit' style='background: #ffc107; color: black; padding: 10px; border: none; border-radius: 4px;'>created_atカラムをNULL許可に変更</button>";
echo "</form>";

echo "<h3>方法3: 納期フィールドのみ追加（created_atは無視）</h3>";
echo "<form method='post' style='margin: 10px 0;'>";
echo "<input type='hidden' name='fix_method' value='delivery_only'>";
echo "<button type='submit' style='background: #28a745; color: white; padding: 10px; border: none; border-radius: 4px;'>納期フィールドのみ追加</button>";
echo "</form>";

// 修正処理
if (isset($_POST['fix_method'])) {
    $method = $_POST['fix_method'];
    
    echo "<h2>3. 修正実行結果</h2>";
    
    switch ($method) {
        case 'recreate':
            echo "<h3>created_atカラムを削除して再作成中...</h3>";
            
            // 1. created_atカラムを削除
            $drop_sql = "ALTER TABLE `{$table_name}` DROP COLUMN `created_at`";
            echo "実行SQL: {$drop_sql}<br>";
            $result = $wpdb->query($drop_sql);
            
            if ($result !== false) {
                echo "✅ created_atカラムの削除に成功<br>";
                
                // 2. created_atカラムを再作成
                $create_sql = "ALTER TABLE `{$table_name}` ADD COLUMN `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP";
                echo "実行SQL: {$create_sql}<br>";
                $result = $wpdb->query($create_sql);
                
                if ($result !== false) {
                    echo "✅ created_atカラムの再作成に成功<br>";
                } else {
                    echo "❌ created_atカラムの再作成に失敗: " . $wpdb->last_error . "<br>";
                }
            } else {
                echo "❌ created_atカラムの削除に失敗: " . $wpdb->last_error . "<br>";
            }
            break;
            
        case 'nullify':
            echo "<h3>created_atカラムをNULL許可に変更中...</h3>";
            
            $modify_sql = "ALTER TABLE `{$table_name}` MODIFY COLUMN `created_at` DATETIME NULL DEFAULT NULL";
            echo "実行SQL: {$modify_sql}<br>";
            $result = $wpdb->query($modify_sql);
            
            if ($result !== false) {
                echo "✅ created_atカラムの修正に成功<br>";
            } else {
                echo "❌ created_atカラムの修正に失敗: " . $wpdb->last_error . "<br>";
            }
            break;
            
        case 'delivery_only':
            echo "<h3>納期フィールドのみ追加中...</h3>";
            
            // 納期フィールドの存在確認
            $columns = $wpdb->get_results("SHOW COLUMNS FROM `{$table_name}`");
            $column_names = array_column($columns, 'Field');
            
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
            break;
    }
}

echo "<h2>4. 現在のテーブル構造確認</h2>";
$columns = $wpdb->get_results("SHOW COLUMNS FROM `{$table_name}`");

echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr style='background-color: #f0f0f0;'>";
echo "<th>フィールド名</th><th>型</th><th>NULL</th><th>キー</th><th>デフォルト</th><th>Extra</th>";
echo "</tr>";

foreach ($columns as $column) {
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

echo "<p><a href='javascript:location.reload()' style='background: #28a745; color: white; padding: 10px; text-decoration: none; border-radius: 4px;'>ページを再読み込み</a></p>";
?> 