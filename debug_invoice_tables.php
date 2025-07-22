<?php
/**
 * 請求書関連テーブルの存在確認
 */

// WordPress環境を読み込み
require_once('../../../wp-config.php');

echo "=== 請求書関連テーブル確認 ===\n";

global $wpdb;

// 確認するテーブル一覧
$tables_to_check = array(
    'ktp_client',
    'ktp_order', 
    'ktp_invoice_items',
    'ktp_department',
    'ktp_supplier'
);

foreach ($tables_to_check as $table_name) {
    $full_table_name = $wpdb->prefix . $table_name;
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$full_table_name}'") === $full_table_name;
    
    echo "テーブル {$full_table_name}: " . ($table_exists ? "存在" : "不存在") . "\n";
    
    if ($table_exists) {
        // テーブルの構造を確認
        $columns = $wpdb->get_results("DESCRIBE {$full_table_name}");
        echo "  カラム数: " . count($columns) . "\n";
        
        // レコード数を確認
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$full_table_name}");
        echo "  レコード数: " . $count . "\n";
        
        // 最初の数行を表示
        if ($count > 0) {
            $sample_data = $wpdb->get_results("SELECT * FROM {$full_table_name} LIMIT 3");
            echo "  サンプルデータ:\n";
            foreach ($sample_data as $row) {
                echo "    " . json_encode($row, JSON_UNESCAPED_UNICODE) . "\n";
            }
        }
    }
    echo "\n";
}

// テスト用の顧客IDでデータを確認
echo "=== テストデータ確認 ===\n";
$test_client_id = 1;

// 顧客データ確認
$client_data = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}ktp_client WHERE id = %d",
    $test_client_id
));

if ($client_data) {
    echo "顧客ID {$test_client_id} のデータ:\n";
    echo json_encode($client_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n\n";
} else {
    echo "顧客ID {$test_client_id} のデータが見つかりません\n\n";
}

// 受注書データ確認
$orders = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}ktp_order WHERE client_id = %d",
    $test_client_id
));

echo "顧客ID {$test_client_id} の受注書数: " . count($orders) . "\n";
if (!empty($orders)) {
    echo "受注書データ:\n";
    foreach ($orders as $order) {
        echo "  ID: {$order->id}, 進捗: {$order->progress}, 完了日: {$order->completion_date}\n";
    }
}

echo "\n=== 確認完了 ===\n"; 