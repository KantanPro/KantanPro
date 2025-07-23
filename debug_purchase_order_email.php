<?php
/**
 * 発注メールデバッグスクリプト
 * 
 * 使用方法:
 * 1. このファイルをプラグインディレクトリに配置
 * 2. ブラウザで /wp-content/plugins/KantanPro/debug_purchase_order_email.php?order_id=1&supplier_name=テスト業者 にアクセス
 */

// WordPressを読み込み
require_once('../../../wp-load.php');

// セキュリティチェック
if (!current_user_can('edit_posts')) {
    die('権限がありません');
}

$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
$supplier_name = isset($_GET['supplier_name']) ? sanitize_text_field($_GET['supplier_name']) : '';

if ($order_id <= 0 || empty($supplier_name)) {
    die('パラメータが不正です。order_id と supplier_name を指定してください。');
}

echo "<h1>発注メールデバッグ</h1>";
echo "<p>Order ID: {$order_id}</p>";
echo "<p>Supplier Name: {$supplier_name}</p>";

global $wpdb;

// 1. 受注書データを取得
$order_table = $wpdb->prefix . 'ktp_order';
$order = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$order_table}` WHERE id = %d", $order_id));

echo "<h2>1. 受注書データ</h2>";
if ($order) {
    echo "<pre>" . print_r($order, true) . "</pre>";
} else {
    echo "<p style='color: red;'>受注書が見つかりません</p>";
}

// 2. 協力会社データを取得
$supplier_table = $wpdb->prefix . 'ktp_supplier';
$supplier = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$supplier_table}` WHERE company_name = %s", $supplier_name));

echo "<h2>2. 協力会社データ</h2>";
if ($supplier) {
    echo "<pre>" . print_r($supplier, true) . "</pre>";
} else {
    echo "<p style='color: red;'>協力会社が見つかりません</p>";
}

// 3. コスト項目を取得（方法1: KTPWP_Order_Itemsクラス）
echo "<h2>3. コスト項目（方法1: KTPWP_Order_Itemsクラス）</h2>";
try {
    $order_items = KTPWP_Order_Items::get_instance();
    $cost_items = $order_items->get_cost_items($order_id);
    echo "<p>取得件数: " . count($cost_items) . "</p>";
    if (!empty($cost_items)) {
        echo "<pre>" . print_r($cost_items, true) . "</pre>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>エラー: " . $e->getMessage() . "</p>";
}

// 4. コスト項目を取得（方法2: 直接データベース）
echo "<h2>4. コスト項目（方法2: 直接データベース）</h2>";
$cost_table = $wpdb->prefix . 'ktp_cost';
$cost_items_direct = $wpdb->get_results(
    $wpdb->prepare("SELECT * FROM `{$cost_table}` WHERE order_id = %d ORDER BY sort_order ASC", $order_id),
    ARRAY_A
);
echo "<p>取得件数: " . count($cost_items_direct) . "</p>";
if (!empty($cost_items_direct)) {
    echo "<pre>" . print_r($cost_items_direct, true) . "</pre>";
}

// 5. フィルタリング結果
echo "<h2>5. フィルタリング結果</h2>";
$filtered_items = array();
foreach ($cost_items_direct as $item) {
    $item_supplier_name = '';
    if (isset($item['supplier_name'])) {
        $item_supplier_name = $item['supplier_name'];
    } elseif (isset($item['supplier'])) {
        $item_supplier_name = $item['supplier'];
    } elseif (isset($item['company_name'])) {
        $item_supplier_name = $item['company_name'];
    }
    
    echo "<p>Item Supplier: '{$item_supplier_name}' vs Target: '{$supplier_name}' - " . 
         ($item_supplier_name === $supplier_name ? 'MATCH' : 'NO MATCH') . "</p>";
    
    if ($item_supplier_name === $supplier_name) {
        $filtered_items[] = $item;
    }
}

echo "<p>フィルタリング後の件数: " . count($filtered_items) . "</p>";
if (!empty($filtered_items)) {
    echo "<pre>" . print_r($filtered_items, true) . "</pre>";
}

// 6. データベーステーブル構造確認
echo "<h2>6. データベーステーブル構造</h2>";
$cost_table_structure = $wpdb->get_results("DESCRIBE `{$cost_table}`");
echo "<pre>" . print_r($cost_table_structure, true) . "</pre>";

echo "<h2>7. 全コスト項目（デバッグ用）</h2>";
$all_cost_items = $wpdb->get_results("SELECT * FROM `{$cost_table}` WHERE order_id = {$order_id}");
echo "<pre>" . print_r($all_cost_items, true) . "</pre>";
?> 