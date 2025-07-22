<?php
/**
 * 請求項目テーブル作成マイグレーション実行スクリプト
 */

// WordPress環境を読み込み
require_once('../../../wp-config.php');

echo "=== 請求項目テーブル作成マイグレーション ===\n";

// マイグレーションファイルを読み込み
require_once('includes/migrations/20250722_create_invoice_items_table.php');

// マイグレーション実行
$result = ktpwp_create_invoice_items_table();

if ($result) {
    echo "✓ 請求項目テーブルの作成が完了しました\n";
    
    // テーブルが正しく作成されたか確認
    global $wpdb;
    $table_name = $wpdb->prefix . 'ktp_invoice_items';
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
    
    if ($table_exists) {
        echo "✓ テーブル {$table_name} が正常に作成されました\n";
        
        // テーブル構造を確認
        $columns = $wpdb->get_results("DESCRIBE {$table_name}");
        echo "✓ カラム数: " . count($columns) . "\n";
        
        // サンプルデータを挿入（テスト用）
        $test_data = array(
            'order_id' => 1,
            'item_name' => 'テスト請求項目',
            'unit_price' => 10000.00,
            'quantity' => 1.00,
            'total_price' => 10000.00,
            'tax_rate' => 10.00,
            'remarks' => 'テスト用データ',
            'sort_order' => 1
        );
        
        $insert_result = $wpdb->insert($table_name, $test_data);
        if ($insert_result !== false) {
            echo "✓ テストデータの挿入が完了しました\n";
        } else {
            echo "✗ テストデータの挿入に失敗しました: " . $wpdb->last_error . "\n";
        }
    } else {
        echo "✗ テーブル {$table_name} の作成に失敗しました\n";
    }
} else {
    echo "✗ 請求項目テーブルの作成に失敗しました\n";
}

echo "\n=== マイグレーション完了 ===\n"; 