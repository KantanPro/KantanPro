<?php
/**
 * 税率NULL値修正のテストファイル
 * 
 * このファイルは、税率をNULLに設定した際にUI上で10%に書き換えられないことを確認するためのテストです。
 */

// WordPress環境の読み込み
require_once('../../../wp-load.php');

// 直接実行禁止
if (!defined('ABSPATH')) {
    exit;
}

echo "<h1>税率NULL値修正テスト</h1>\n";

// テスト用の受注書ID（実際の環境に合わせて変更してください）
$test_order_id = 1; // 実際の受注書IDに変更

if (!$test_order_id) {
    echo "<p style='color: red;'>テスト用の受注書IDを設定してください。</p>\n";
    exit;
}

// 1. データベースの現在の状態を確認
echo "<h2>1. データベースの現在の状態</h2>\n";
global $wpdb;
$table_name = $wpdb->prefix . 'ktp_order_invoice_items';

$current_items = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT id, product_name, tax_rate FROM `{$table_name}` WHERE order_id = %d ORDER BY sort_order ASC, id ASC",
        $test_order_id
    ),
    ARRAY_A
);

echo "<table border='1' style='border-collapse: collapse;'>\n";
echo "<tr><th>ID</th><th>商品名</th><th>税率（DB）</th><th>税率タイプ</th></tr>\n";
foreach ($current_items as $item) {
    $tax_rate_type = is_null($item['tax_rate']) ? 'NULL' : gettype($item['tax_rate']);
    echo "<tr>";
    echo "<td>" . esc_html($item['id']) . "</td>";
    echo "<td>" . esc_html($item['product_name']) . "</td>";
    echo "<td>" . esc_html($item['tax_rate']) . "</td>";
    echo "<td>" . esc_html($tax_rate_type) . "</td>";
    echo "</tr>\n";
}
echo "</table>\n";

// 2. UI生成テスト
echo "<h2>2. UI生成テスト</h2>\n";
if (class_exists('KTPWP_Order_UI')) {
    $order_ui = KTPWP_Order_UI::get_instance();
    $invoice_html = $order_ui->generate_invoice_items_table($test_order_id);
    
    // HTMLから税率の値を抽出
    preg_match_all('/name="invoice_items\[\d+\]\[tax_rate\]" value="([^"]*)"/', $invoice_html, $matches);
    
    echo "<h3>生成されたHTMLの税率値:</h3>\n";
    echo "<ul>\n";
    foreach ($matches[1] as $index => $tax_rate_value) {
        echo "<li>行 " . ($index + 1) . ": '" . esc_html($tax_rate_value) . "'</li>\n";
    }
    echo "</ul>\n";
    
    // 税率が空文字列になっているかチェック
    $has_empty_tax_rate = in_array('', $matches[1]);
    if ($has_empty_tax_rate) {
        echo "<p style='color: green;'>✓ 税率が空文字列として正しく表示されています。</p>\n";
    } else {
        echo "<p style='color: red;'>✗ 税率が空文字列として表示されていません。</p>\n";
    }
    
    // 税率が10.00になっていないかチェック
    $has_default_tax_rate = in_array('10', $matches[1]) || in_array('10.00', $matches[1]);
    if (!$has_default_tax_rate) {
        echo "<p style='color: green;'>✓ 税率がデフォルト値（10%）に書き換えられていません。</p>\n";
    } else {
        echo "<p style='color: red;'>✗ 税率がデフォルト値（10%）に書き換えられています。</p>\n";
    }
} else {
    echo "<p style='color: red;'>KTPWP_Order_UIクラスが見つかりません。</p>\n";
}

// 3. 税率をNULLに設定するテスト
echo "<h2>3. 税率NULL設定テスト</h2>\n";
if (class_exists('KTPWP_Order_Items')) {
    $order_items = KTPWP_Order_Items::get_instance();
    
    // 最初のアイテムの税率をNULLに設定
    if (!empty($current_items)) {
        $first_item_id = $current_items[0]['id'];
        $result = $order_items->update_item_field('invoice', $first_item_id, 'tax_rate', '');
        
        if ($result['success']) {
            echo "<p style='color: green;'>✓ 税率をNULLに設定しました（ID: {$first_item_id}）</p>\n";
            
            // 設定後の状態を確認
            $updated_item = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT id, product_name, tax_rate FROM `{$table_name}` WHERE id = %d",
                    $first_item_id
                ),
                ARRAY_A
            );
            
            if ($updated_item) {
                $tax_rate_type = is_null($updated_item['tax_rate']) ? 'NULL' : gettype($updated_item['tax_rate']);
                echo "<p>更新後の税率: " . esc_html($updated_item['tax_rate']) . " (タイプ: " . esc_html($tax_rate_type) . ")</p>\n";
                
                if (is_null($updated_item['tax_rate'])) {
                    echo "<p style='color: green;'>✓ データベースにNULL値が正しく保存されています。</p>\n";
                } else {
                    echo "<p style='color: red;'>✗ データベースにNULL値が保存されていません。</p>\n";
                }
            }
        } else {
            echo "<p style='color: red;'>✗ 税率の設定に失敗しました。</p>\n";
        }
    } else {
        echo "<p style='color: orange;'>テスト用のアイテムがありません。</p>\n";
    }
} else {
    echo "<p style='color: red;'>KTPWP_Order_Itemsクラスが見つかりません。</p>\n";
}

echo "<h2>4. テスト結果サマリー</h2>\n";
echo "<p>このテストで以下を確認しました：</p>\n";
echo "<ul>\n";
echo "<li>データベースの税率値の状態</li>\n";
echo "<li>UI生成時の税率表示</li>\n";
echo "<li>税率NULL設定の動作</li>\n";
echo "</ul>\n";

echo "<p><strong>注意:</strong> 実際の環境でテストする際は、テスト用の受注書IDを設定してください。</p>\n";
?> 