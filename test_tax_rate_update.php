<?php
/**
 * 税率更新テストファイル
 * 協力会社選択ポップアップの「更新」時の税率処理をテスト
 */

// 直接実行禁止
if (!defined('ABSPATH')) {
    exit;
}

// テスト用の税率データ
$test_tax_rates = array(
    'null' => null,
    'empty_string' => '',
    'zero' => 0,
    'zero_string' => '0',
    'ten' => 10,
    'ten_string' => '10',
    'ten_point_five' => 10.5
);

echo "<h2>税率更新テスト</h2>\n";
echo "<p>協力会社選択ポップアップの「更新」時の税率処理をテストします。</p>\n";

// KTPWP_Order_Itemsクラスを取得
$order_items = KTPWP_Order_Items::get_instance();

// テスト用の受注書ID（実際の環境に合わせて調整）
$test_order_id = 1; // 実際の受注書IDに変更してください

echo "<h3>1. 新規アイテム作成テスト</h3>\n";

foreach ($test_tax_rates as $test_name => $tax_rate) {
    echo "<h4>テスト: {$test_name} (値: " . var_export($tax_rate, true) . ")</h4>\n";
    
    // 新規アイテムを作成
    $new_item_id = $order_items->create_new_item('cost', $test_order_id, 'product_name', "テスト商品_{$test_name}", null);
    
    if ($new_item_id) {
        echo "<p>✓ 新規アイテム作成成功 (ID: {$new_item_id})</p>\n";
        
        // 税率を更新
        $update_result = $order_items->update_item_field('cost', $new_item_id, 'tax_rate', $tax_rate);
        
        if ($update_result && $update_result['success']) {
            echo "<p>✓ 税率更新成功</p>\n";
            
            // 更新された値を取得して確認
            $updated_tax_rate = $order_items->get_item_field_value('cost', $new_item_id, 'tax_rate');
            echo "<p>更新後の税率: " . var_export($updated_tax_rate, true) . "</p>\n";
            
            // 期待値との比較
            $expected_tax_rate = null;
            if ($tax_rate !== null && $tax_rate !== '' && $tax_rate !== '0' && is_numeric($tax_rate)) {
                $expected_tax_rate = floatval($tax_rate);
            }
            
            if ($updated_tax_rate === $expected_tax_rate) {
                echo "<p style='color: green;'>✓ 期待値と一致</p>\n";
            } else {
                echo "<p style='color: red;'>✗ 期待値と不一致</p>\n";
                echo "<p>期待値: " . var_export($expected_tax_rate, true) . "</p>\n";
            }
        } else {
            echo "<p style='color: red;'>✗ 税率更新失敗</p>\n";
        }
        
        // テスト用アイテムを削除
        $order_items->delete_item('cost', $new_item_id, $test_order_id);
        echo "<p>テスト用アイテムを削除しました</p>\n";
    } else {
        echo "<p style='color: red;'>✗ 新規アイテム作成失敗</p>\n";
    }
    
    echo "<hr>\n";
}

echo "<h3>2. 既存アイテム更新テスト</h3>\n";

// テスト用の既存アイテムを作成
$existing_item_id = $order_items->create_new_item('cost', $test_order_id, 'product_name', '既存テスト商品', null);

if ($existing_item_id) {
    echo "<p>既存アイテム作成成功 (ID: {$existing_item_id})</p>\n";
    
    foreach ($test_tax_rates as $test_name => $tax_rate) {
        echo "<h4>テスト: {$test_name} (値: " . var_export($tax_rate, true) . ")</h4>\n";
        
        // 税率を更新
        $update_result = $order_items->update_item_field('cost', $existing_item_id, 'tax_rate', $tax_rate);
        
        if ($update_result && $update_result['success']) {
            echo "<p>✓ 税率更新成功</p>\n";
            
            // 更新された値を取得して確認
            $updated_tax_rate = $order_items->get_item_field_value('cost', $existing_item_id, 'tax_rate');
            echo "<p>更新後の税率: " . var_export($updated_tax_rate, true) . "</p>\n";
            
            // 期待値との比較
            $expected_tax_rate = null;
            if ($tax_rate !== null && $tax_rate !== '' && $tax_rate !== '0' && is_numeric($tax_rate)) {
                $expected_tax_rate = floatval($tax_rate);
            }
            
            if ($updated_tax_rate === $expected_tax_rate) {
                echo "<p style='color: green;'>✓ 期待値と一致</p>\n";
            } else {
                echo "<p style='color: red;'>✗ 期待値と不一致</p>\n";
                echo "<p>期待値: " . var_export($expected_tax_rate, true) . "</p>\n";
            }
        } else {
            echo "<p style='color: red;'>✗ 税率更新失敗</p>\n";
        }
        
        echo "<hr>\n";
    }
    
    // テスト用アイテムを削除
    $order_items->delete_item('cost', $existing_item_id, $test_order_id);
    echo "<p>テスト用アイテムを削除しました</p>\n";
} else {
    echo "<p style='color: red;'>✗ 既存アイテム作成失敗</p>\n";
}

echo "<h3>テスト完了</h3>\n";
echo "<p>税率更新のテストが完了しました。</p>\n";
?> 