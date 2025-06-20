<?php
/**
 * プレビュー問題デバッグ用：実際のテーブル構造を確認
 */

// WordPressを読み込み
define('WP_USE_THEMES', false);
require_once('/Users/kantanpro/ktplocal/wp/wp-load.php');

echo "=== KantanPro DBテーブル調査 ===\n";

global $wpdb;

// 1. 請求項目関連テーブルを探す
echo "1. 請求項目関連テーブル:\n";
$tables = $wpdb->get_results("SHOW TABLES LIKE '%invoice%'", ARRAY_N);
foreach ($tables as $table) {
    echo "  - " . $table[0] . "\n";
}

// 2. 受注書関連テーブルを探す
echo "\n2. 受注書関連テーブル:\n";
$tables = $wpdb->get_results("SHOW TABLES LIKE '%order%'", ARRAY_N);
foreach ($tables as $table) {
    echo "  - " . $table[0] . "\n";
}

// 3. KTプレフィックス付きテーブルを探す
echo "\n3. KTプレフィックス付きテーブル:\n";
$tables = $wpdb->get_results("SHOW TABLES LIKE 'wp_ktp%'", ARRAY_N);
foreach ($tables as $table) {
    echo "  - " . $table[0] . "\n";
}

// 4. 具体的なテーブル名でデータ確認
$possible_tables = [
    'wp_ktp_invoice_item',
    'wp_ktp_order_invoice_items',
    'wp_ktp_invoice'
];

foreach ($possible_tables as $table_name) {
    echo "\n4. テーブル '$table_name' の確認:\n";
    
    // テーブルの存在確認
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
    
    if ($table_exists) {
        echo "  存在: YES\n";
        
        // レコード数
        $count = $wpdb->get_var("SELECT COUNT(*) FROM `$table_name`");
        echo "  レコード数: $count\n";
        
        // 構造確認
        $columns = $wpdb->get_results("DESCRIBE `$table_name`", ARRAY_A);
        echo "  カラム:\n";
        foreach ($columns as $col) {
            echo "    - {$col['Field']} ({$col['Type']})\n";
        }
        
        // サンプルデータ（最新5件）
        if ($count > 0) {
            echo "  サンプルデータ（最新5件）:\n";
            $samples = $wpdb->get_results("SELECT * FROM `$table_name` ORDER BY id DESC LIMIT 5", ARRAY_A);
            foreach ($samples as $sample) {
                echo "    ID:{$sample['id']} | order_id:{$sample['order_id']} | ";
                if (isset($sample['product_name'])) {
                    echo "product:{$sample['product_name']} | ";
                }
                if (isset($sample['price'])) {
                    echo "price:{$sample['price']} | ";
                }
                if (isset($sample['quantity'])) {
                    echo "quantity:{$sample['quantity']} | ";
                }
                if (isset($sample['amount'])) {
                    echo "amount:{$sample['amount']}";
                }
                echo "\n";
            }
        }
    } else {
        echo "  存在: NO\n";
    }
}

// 5. 実際のGet_Invoice_Itemsメソッドをテスト
echo "\n5. Get_Invoice_Itemsメソッドのテスト:\n";

// 受注書データを確認
$orders = $wpdb->get_results("SELECT id, project_name FROM wp_ktp_order ORDER BY id DESC LIMIT 3", ARRAY_A);
echo "  受注書:\n";
foreach ($orders as $order) {
    echo "    ID:{$order['id']} | {$order['project_name']}\n";
}

// 最新の受注書でGet_Invoice_Itemsをテスト
if (!empty($orders)) {
    $test_order_id = $orders[0]['id'];
    echo "\n  受注書ID {$test_order_id} でGet_Invoice_Itemsをテスト:\n";
    
    if (class_exists('Tab_Order')) {
        $tab_order = new Tab_Order();
        $items = $tab_order->Get_Invoice_Items($test_order_id);
        
        echo "    結果: " . (is_array($items) ? count($items) . "件" : "エラー") . "\n";
        if (is_array($items) && !empty($items)) {
            foreach ($items as $item) {
                echo "      ID:" . (isset($item['id']) ? $item['id'] : 'N/A');
                echo " | product:" . (isset($item['product_name']) ? $item['product_name'] : 'N/A');
                echo " | price:" . (isset($item['price']) ? $item['price'] : 'N/A');
                echo " | quantity:" . (isset($item['quantity']) ? $item['quantity'] : 'N/A');
                echo " | amount:" . (isset($item['amount']) ? $item['amount'] : 'N/A') . "\n";
            }
        } else {
            echo "      データなし または エラー\n";
        }
    } else {
        echo "    Tab_Orderクラスが見つかりません\n";
    }
}

echo "\n=== 調査完了 ===\n";
