#!/usr/bin/env php
<?php
/**
 * 適格請求書番号 AJAX 問題のテスト用スクリプト
 */

// WordPress のbootstrap
require_once __DIR__ . '/../../../wp-config.php';
require_once __DIR__ . '/../../../wp-load.php';

// テスト用データ
$test_supplier_id = 1;

echo "=== 適格請求書番号 AJAX 問題のテスト ===\n";

// 1. データベース接続確認
global $wpdb;
$supplier_table = $wpdb->prefix . 'ktp_supplier';
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$supplier_table'") === $supplier_table;
echo "1. データベーステーブル存在確認: " . ($table_exists ? "OK" : "NG") . "\n";

if ($table_exists) {
    // 2. 協力会社データの確認
    $suppliers = $wpdb->get_results("SELECT id, name, qualified_invoice_number FROM $supplier_table LIMIT 5");
    echo "2. 協力会社データ数: " . count($suppliers) . "\n";
    
    foreach ($suppliers as $supplier) {
        $qualified_invoice_number = $supplier->qualified_invoice_number ? trim($supplier->qualified_invoice_number) : '';
        echo "   ID: {$supplier->id}, 名前: {$supplier->name}, 適格請求書番号: '{$qualified_invoice_number}'\n";
    }
    
    // 3. 特定の協力会社の適格請求書番号取得テスト
    if (!empty($suppliers)) {
        $test_supplier = $suppliers[0];
        $qualified_invoice_number = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT qualified_invoice_number FROM `{$supplier_table}` WHERE id = %d",
                $test_supplier->id
            )
        );
        
        echo "3. 協力会社 ID {$test_supplier->id} の適格請求書番号取得テスト: ";
        echo $qualified_invoice_number !== null ? "OK ('{$qualified_invoice_number}')" : "NG";
        echo "\n";
    }
}

// 4. AJAX ハンドラの存在確認
echo "4. AJAX ハンドラ確認:\n";
$ajax_class = new KTPWP_Ajax();
$reflection = new ReflectionClass($ajax_class);
$methods = $reflection->getMethods();
$has_qualified_invoice_handler = false;
foreach ($methods as $method) {
    if ($method->getName() === 'ajax_get_supplier_qualified_invoice_number') {
        $has_qualified_invoice_handler = true;
        break;
    }
}
echo "   ajax_get_supplier_qualified_invoice_number メソッド: " . ($has_qualified_invoice_handler ? "OK" : "NG") . "\n";

// 5. WordPress nonce 確認
$nonce = wp_create_nonce('ktp_ajax_nonce');
echo "5. WordPress nonce 生成テスト: " . (!empty($nonce) ? "OK" : "NG") . " (nonce: $nonce)\n";

// 6. 現在のユーザー権限確認
$current_user = wp_get_current_user();
echo "6. 現在のユーザー: " . ($current_user->ID ? $current_user->user_login : 'ゲスト') . "\n";
echo "   edit_posts 権限: " . (current_user_can('edit_posts') ? "OK" : "NG") . "\n";
echo "   ktpwp_access 権限: " . (current_user_can('ktpwp_access') ? "OK" : "NG") . "\n";

echo "\n=== テスト完了 ===\n";
