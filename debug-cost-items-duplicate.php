<?php
/**
 * コスト項目重複レコード調査スクリプト
 */

// WordPressの設定を読み込み
require_once('../../../wp-config.php');

global $wpdb;

echo "<h2>コスト項目重複調査</h2>\n";

// 最新の受注一覧を確認
$latest_orders = $wpdb->get_results("
    SELECT id, customer_name, project_name, created_at 
    FROM {$wpdb->prefix}ktp_order 
    ORDER BY id DESC 
    LIMIT 5
");

echo "<h3>最新の受注一覧</h3>\n";
foreach ($latest_orders as $order) {
    echo "ID: {$order->id}, 顧客: {$order->customer_name}, プロジェクト: {$order->project_name}<br>\n";
}

// 最新の受注のコスト項目を確認
$latest_order_id = $latest_orders[0]->id ?? null;

if ($latest_order_id) {
    echo "<h3>受注ID {$latest_order_id} のコスト項目詳細</h3>\n";
    
    $cost_items = $wpdb->get_results($wpdb->prepare("
        SELECT id, product_name, price, quantity, amount, purchase, created_at
        FROM {$wpdb->prefix}ktp_order_cost_items 
        WHERE order_id = %d 
        ORDER BY id
    ", $latest_order_id));
    
    echo "総数: " . count($cost_items) . " 件<br>\n";
    
    foreach ($cost_items as $item) {
        echo "ID: {$item->id}, 商品名: '" . esc_html($item->product_name) . "', 金額: {$item->amount}, 仕入先: '" . esc_html($item->purchase) . "', 作成日: {$item->created_at}<br>\n";
    }
}

echo "<p><small>実行時刻: " . date('Y-m-d H:i:s') . "</small></p>\n";
?> 