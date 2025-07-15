<?php
/**
 * コスト項目備考欄修正マイグレーション
 * 
 * このスクリプトは、コスト項目テーブルの備考欄に「0」が保存されているデータを
 * 空文字列に修正するためのマイグレーションです。
 */

// WordPress環境を読み込み
require_once('/var/www/html/wp-load.php');

// 権限チェック
if (!current_user_can('manage_options')) {
    die('権限がありません');
}

global $wpdb;

echo "<h2>コスト項目備考欄修正マイグレーション</h2>";

// コスト項目テーブルの確認
$table_name = $wpdb->prefix . 'ktp_order_cost_items';
echo "<p>テーブル名: {$table_name}</p>";

$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'");
if (!$table_exists) {
    echo "<p>❌ テーブルが存在しません</p>";
    exit;
}

echo "<p>✅ テーブルが存在します</p>";

// 修正前のデータ確認
echo "<h3>修正前のデータ確認</h3>";
$before_count = $wpdb->get_var(
    "SELECT COUNT(*) FROM `{$table_name}` WHERE remarks = '0'"
);
echo "<p>備考欄が「0」のレコード数: {$before_count}</p>";

if ($before_count > 0) {
    // 修正対象のデータを表示
    $before_items = $wpdb->get_results(
        "SELECT id, order_id, product_name, remarks FROM `{$table_name}` WHERE remarks = '0' LIMIT 10",
        ARRAY_A
    );
    
    echo "<h4>修正対象データ（最大10件）:</h4>";
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>受注書ID</th><th>商品名</th><th>備考</th></tr>";
    
    foreach ($before_items as $item) {
        echo "<tr>";
        echo "<td>{$item['id']}</td>";
        echo "<td>{$item['order_id']}</td>";
        echo "<td>" . htmlspecialchars($item['product_name']) . "</td>";
        echo "<td>" . htmlspecialchars($item['remarks']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // 修正実行
    echo "<h3>修正実行</h3>";
    
    $result = $wpdb->update(
        $table_name,
        array('remarks' => ''),
        array('remarks' => '0'),
        array('%s'),
        array('%s')
    );
    
    if ($result !== false) {
        echo "<p>✅ 修正が完了しました。修正されたレコード数: {$result}</p>";
        
        // 修正後の確認
        echo "<h3>修正後の確認</h3>";
        $after_count = $wpdb->get_var(
            "SELECT COUNT(*) FROM `{$table_name}` WHERE remarks = '0'"
        );
        echo "<p>備考欄が「0」のレコード数: {$after_count}</p>";
        
        if ($after_count == 0) {
            echo "<p>✅ すべての「0」が正常に空文字列に修正されました。</p>";
        } else {
            echo "<p>⚠️ まだ「0」が残っているレコードがあります。</p>";
        }
        
        // 修正後のデータを表示
        $after_items = $wpdb->get_results(
            "SELECT id, order_id, product_name, remarks FROM `{$table_name}` WHERE remarks = '' LIMIT 10",
            ARRAY_A
        );
        
        echo "<h4>修正後のデータ（最大10件）:</h4>";
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>受注書ID</th><th>商品名</th><th>備考</th></tr>";
        
        foreach ($after_items as $item) {
            echo "<tr>";
            echo "<td>{$item['id']}</td>";
            echo "<td>{$item['order_id']}</td>";
            echo "<td>" . htmlspecialchars($item['product_name']) . "</td>";
            echo "<td>" . htmlspecialchars($item['remarks']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
    } else {
        echo "<p>❌ 修正に失敗しました。エラー: " . $wpdb->last_error . "</p>";
    }
    
} else {
    echo "<p>✅ 修正対象のデータはありません。</p>";
}

// 全体的な統計
echo "<h3>全体統計</h3>";
$total_count = $wpdb->get_var("SELECT COUNT(*) FROM `{$table_name}`");
$empty_remarks_count = $wpdb->get_var("SELECT COUNT(*) FROM `{$table_name}` WHERE remarks = '' OR remarks IS NULL");
$zero_remarks_count = $wpdb->get_var("SELECT COUNT(*) FROM `{$table_name}` WHERE remarks = '0'");
$other_remarks_count = $wpdb->get_var("SELECT COUNT(*) FROM `{$table_name}` WHERE remarks != '' AND remarks != '0' AND remarks IS NOT NULL");

echo "<table border='1' style='border-collapse: collapse;'>";
echo "<tr><th>項目</th><th>件数</th></tr>";
echo "<tr><td>総レコード数</td><td>{$total_count}</td></tr>";
echo "<tr><td>備考欄が空のレコード数</td><td>{$empty_remarks_count}</td></tr>";
echo "<tr><td>備考欄が「0」のレコード数</td><td>{$zero_remarks_count}</td></tr>";
echo "<tr><td>備考欄に値があるレコード数</td><td>{$other_remarks_count}</td></tr>";
echo "</table>";

echo "<hr>";
echo "<p><a href='debug_cost_items_remarks.php'>デバッグページに戻る</a></p>";
?> 