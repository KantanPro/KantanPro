<?php
/**
 * デバッグ用：プレビューでの単価問題調査
 * 
 * Usage: wp eval-file debug-preview-price.php --order_id=12
 */

if (!defined('WP_CLI') || !WP_CLI) {
    die('This script can only be run via WP-CLI');
}

// パラメータ取得
$order_id = WP_CLI\Utils\get_flag_value($assoc_args, 'order_id', null);

if (!$order_id) {
    WP_CLI::error('--order_id パラメータが必要です');
}

WP_CLI::log("=== プレビュー単価問題デバッグ (受注書ID: {$order_id}) ===");

global $wpdb;
$invoice_table = $wpdb->prefix . 'ktp_invoice_item';

// 1. 生のDBデータを確認
WP_CLI::log("1. 生のDBデータ:");
$raw_items = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM `{$invoice_table}` WHERE order_id = %d ORDER BY id",
    $order_id
), ARRAY_A);

foreach ($raw_items as $item) {
    WP_CLI::log(sprintf(
        "  ID:%s | %s | price:%s | quantity:%s | amount:%s",
        $item['id'],
        $item['product_name'],
        $item['price'],
        $item['quantity'],
        $item['amount']
    ));
}

// 2. Get_Invoice_Itemsメソッドの結果を確認
WP_CLI::log("\n2. Get_Invoice_Itemsメソッドの結果:");
if (class_exists('KTPWP_Order_Items')) {
    $order_items = new KTPWP_Order_Items();
    $invoice_items = $order_items->get_invoice_items($order_id);
    
    foreach ($invoice_items as $item) {
        WP_CLI::log(sprintf(
            "  ID:%s | %s | price:%s | quantity:%s | amount:%s",
            isset($item['id']) ? $item['id'] : 'N/A',
            isset($item['product_name']) ? $item['product_name'] : 'N/A',
            isset($item['price']) ? $item['price'] : 'N/A',
            isset($item['quantity']) ? $item['quantity'] : 'N/A',
            isset($item['amount']) ? $item['amount'] : 'N/A'
        ));
    }
} else {
    WP_CLI::error('KTPWP_Order_Items クラスが見つかりません');
}

// 3. プレビューHTML生成をテスト
WP_CLI::log("\n3. プレビューHTML生成テスト:");
if (class_exists('Tab_Order')) {
    $tab_order = new Tab_Order();
    
    // privateメソッドをテストするためにリフレクションを使用
    $reflection = new ReflectionClass($tab_order);
    $method = $reflection->getMethod('Generate_Invoice_Items_For_Preview');
    $method->setAccessible(true);
    
    $preview_html = $method->invoke($tab_order, $order_id);
    
    // HTMLから単価部分を抽出して表示
    if (preg_match_all('/¥([0-9,]+)<\/td>/', $preview_html, $matches)) {
        WP_CLI::log("  プレビューHTMLから抽出された単価:");
        foreach ($matches[1] as $price) {
            WP_CLI::log("    ¥{$price}");
        }
    }
} else {
    WP_CLI::error('Tab_Order クラスが見つかりません');
}

// 4. 最近の変更がある項目を特定
WP_CLI::log("\n4. 最近の変更がある項目:");
$recent_items = $wpdb->get_results($wpdb->prepare(
    "SELECT *, UNIX_TIMESTAMP(updated_at) as updated_timestamp FROM `{$invoice_table}` WHERE order_id = %d ORDER BY updated_at DESC LIMIT 5",
    $order_id
), ARRAY_A);

foreach ($recent_items as $item) {
    $updated_time = date('Y-m-d H:i:s', $item['updated_timestamp']);
    WP_CLI::log(sprintf(
        "  更新日時:%s | ID:%s | %s | price:%s",
        $updated_time,
        $item['id'],
        $item['product_name'],
        $item['price']
    ));
}

WP_CLI::success('デバッグ完了');
