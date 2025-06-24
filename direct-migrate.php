<?php
/**
 * 直接データベース操作でテーブル構造マイグレーション
 */

// WordPress環境をロード
require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-config.php');

echo "=== wp_ktp_supplier_skills テーブル構造マイグレーション ===\n";

try {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'ktp_supplier_skills';
    
    echo "1. 現在のテーブル構造を確認中...\n";
    
    // テーブルの存在確認
    $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name));
    
    if (!$table_exists) {
        echo "テーブルが存在しません。\n";
        exit(1);
    }
    
    // 現在のカラム構造を取得
    $columns = $wpdb->get_results("DESCRIBE $table_name");
    
    echo "現在のカラム:\n";
    foreach ($columns as $column) {
        echo "  - " . $column->Field . " (" . $column->Type . ")\n";
    }
    
    // unit_priceカラムの型を変更
    echo "\n2. unit_priceカラムの型を変更中...\n";
    
    $alter_query = "ALTER TABLE `$table_name` 
                   MODIFY COLUMN `unit_price` DECIMAL(20,10) NOT NULL DEFAULT 0 COMMENT '単価'";
    
    $result = $wpdb->query($alter_query);
    
    if ($result !== false) {
        echo "✓ unit_priceカラムの型をDECIMAL(20,10)に変更しました。\n";
        
        // 変更後の構造を確認
        $updated_columns = $wpdb->get_results("DESCRIBE $table_name");
        foreach ($updated_columns as $column) {
            if ($column->Field === 'unit_price') {
                echo "新しい型: " . $column->Type . "\n";
                break;
            }
        }
    } else {
        echo "✗ カラムの型変更に失敗しました: " . $wpdb->last_error . "\n";
        exit(1);
    }
    
    echo "\n完了しました。\n";
    
} catch (Exception $e) {
    echo "エラーが発生しました: " . $e->getMessage() . "\n";
    exit(1);
}

?>
