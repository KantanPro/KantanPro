<?php
/**
 * サービスタブのデータベース構造確認
 *
 * @package KTPWP
 * @since 1.0.0
 */

// WordPress環境を読み込み
require_once('../../../wp-load.php');

echo "<h1>サービスタブのデータベース構造確認</h1>";

global $wpdb;

// サービスタブのテーブル名を確認
$service_table_name = $wpdb->prefix . 'ktp_service';

echo "<h2>1. サービスタブのテーブル構造確認</h2>";

// テーブルの存在確認
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$service_table_name}'");
if (!$table_exists) {
    echo "❌ テーブル {$service_table_name} が存在しません<br>";
    echo "<p>サービスタブがまだ作成されていない可能性があります。</p>";
    exit;
}

echo "✅ テーブル {$service_table_name} が存在します<br>";

// カラム構造を取得
$columns = $wpdb->get_results("SHOW COLUMNS FROM `{$service_table_name}`");

echo "<h3>現在のカラム一覧:</h3>";
echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr style='background-color: #f0f0f0;'>";
echo "<th>フィールド名</th><th>型</th><th>NULL</th><th>キー</th><th>デフォルト</th><th>Extra</th>";
echo "</tr>";

$price_column = null;
foreach ($columns as $column) {
    $is_price = ($column->Field === 'price');
    $bg_color = $is_price ? '#ffffcc' : '';
    
    echo "<tr style='background-color: {$bg_color};'>";
    echo "<td>{$column->Field}</td>";
    echo "<td>{$column->Type}</td>";
    echo "<td>{$column->Null}</td>";
    echo "<td>{$column->Key}</td>";
    echo "<td>" . ($column->Default ?? 'NULL') . "</td>";
    echo "<td>{$column->Extra}</td>";
    echo "</tr>";
    
    if ($is_price) {
        $price_column = $column;
    }
}

echo "</table>";

echo "<h2>2. priceカラムの詳細確認</h2>";

if ($price_column) {
    echo "<h3>priceカラムの現在の設定:</h3>";
    echo "フィールド名: {$price_column->Field}<br>";
    echo "型: {$price_column->Type}<br>";
    echo "NULL: {$price_column->Null}<br>";
    echo "デフォルト: " . ($price_column->Default ?? 'NULL') . "<br>";
    echo "Extra: {$price_column->Extra}<br>";
    
    // DECIMAL(10,0)の場合は修正が必要
    if (strpos($price_column->Type, 'DECIMAL(10,0)') !== false) {
        echo "<h3 style='color: red;'>⚠️ 問題: priceカラムがDECIMAL(10,0)になっています</h3>";
        echo "<p>小数点以下が保持されません。DECIMAL(10,2)に変更する必要があります。</p>";
        
        echo "<h3>修正SQL:</h3>";
        echo "<pre style='background: #f5f5f5; padding: 10px; border: 1px solid #ccc;'>";
        echo "ALTER TABLE `{$service_table_name}` MODIFY COLUMN `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00;";
        echo "</pre>";
        
        echo "<h3>修正実行:</h3>";
        echo "<form method='post'>";
        echo "<input type='hidden' name='action' value='fix_price_column'>";
        echo "<input type='submit' value='priceカラムを修正する' style='background: #0073aa; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer;'>";
        echo "</form>";
        
    } elseif (strpos($price_column->Type, 'DECIMAL(10,2)') !== false) {
        echo "<h3 style='color: green;'>✅ 正常: priceカラムがDECIMAL(10,2)になっています</h3>";
        echo "<p>小数点以下が正しく保持されます。</p>";
    } else {
        echo "<h3 style='color: orange;'>⚠️ 注意: priceカラムの型が予期しない形式です</h3>";
        echo "<p>現在の型: {$price_column->Type}</p>";
    }
} else {
    echo "❌ priceカラムが見つかりません<br>";
}

echo "<h2>3. テストデータの確認</h2>";

// 最新の5件のデータを取得
$test_data = $wpdb->get_results("SELECT id, service_name, price FROM `{$service_table_name}` ORDER BY id DESC LIMIT 5");

if ($test_data) {
    echo "<h3>最新の5件のデータ:</h3>";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background-color: #f0f0f0;'>";
    echo "<th>ID</th><th>サービス名</th><th>価格</th>";
    echo "</tr>";
    
    foreach ($test_data as $row) {
        echo "<tr>";
        echo "<td>{$row->id}</td>";
        echo "<td>{$row->service_name}</td>";
        echo "<td>{$row->price}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>テストデータがありません。</p>";
}

// 修正処理
if (isset($_POST['action']) && $_POST['action'] === 'fix_price_column') {
    echo "<h2>4. 修正処理実行</h2>";
    
    $result = $wpdb->query("ALTER TABLE `{$service_table_name}` MODIFY COLUMN `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00");
    
    if ($result !== false) {
        echo "<h3 style='color: green;'>✅ priceカラムの修正が完了しました</h3>";
        
        // 修正後の確認
        $updated_columns = $wpdb->get_results("SHOW COLUMNS FROM `{$service_table_name}`");
        foreach ($updated_columns as $column) {
            if ($column->Field === 'price') {
                echo "<p>修正後のpriceカラム: {$column->Type}</p>";
                break;
            }
        }
    } else {
        echo "<h3 style='color: red;'>❌ priceカラムの修正に失敗しました</h3>";
        echo "<p>エラー: " . $wpdb->last_error . "</p>";
    }
}

echo "<h2>5. 推奨事項</h2>";
echo "<ul>";
echo "<li>priceカラムがDECIMAL(10,2)になっていることを確認してください</li>";
echo "<li>小数点以下の値が正しく保存・表示されることをテストしてください</li>";
echo "<li>問題がある場合は、上記の修正SQLを実行してください</li>";
echo "</ul>";

echo "<p><a href='check-service-database.php'>ページを再読み込み</a></p>";
?> 