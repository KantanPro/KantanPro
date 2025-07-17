<?php
/**
 * AJAX ハンドラー登録状況テスト
 */
require_once('../../../wp-config.php');

// AJAXアクションが登録されているか確認
global $wp_filter;

echo "=== AJAX ハンドラー登録状況 ===\n";

// 適格請求書番号取得
$action = 'wp_ajax_ktp_get_supplier_qualified_invoice_number';
if (isset($wp_filter[$action])) {
    echo "✓ $action が登録されています\n";
    foreach ($wp_filter[$action]->callbacks as $priority => $callbacks) {
        foreach ($callbacks as $callback) {
            if (is_array($callback['function'])) {
                echo "  - Priority $priority: " . get_class($callback['function'][0]) . "::" . $callback['function'][1] . "\n";
            } else {
                echo "  - Priority $priority: " . $callback['function'] . "\n";
            }
        }
    }
} else {
    echo "✗ $action が登録されていません\n";
}

// 税区分取得
$action = 'wp_ajax_ktp_get_supplier_tax_category';
if (isset($wp_filter[$action])) {
    echo "✓ $action が登録されています\n";
    foreach ($wp_filter[$action]->callbacks as $priority => $callbacks) {
        foreach ($callbacks as $callback) {
            if (is_array($callback['function'])) {
                echo "  - Priority $priority: " . get_class($callback['function'][0]) . "::" . $callback['function'][1] . "\n";
            } else {
                echo "  - Priority $priority: " . $callback['function'] . "\n";
            }
        }
    }
} else {
    echo "✗ $action が登録されていません\n";
}

// nonceテスト
echo "\n=== Nonce テスト ===\n";
$nonce = wp_create_nonce('ktp_ajax_nonce');
echo "Generated nonce: $nonce\n";

$verify_result = wp_verify_nonce($nonce, 'ktp_ajax_nonce');
echo "Nonce verification result: " . ($verify_result ? 'PASS' : 'FAIL') . "\n";

// 現在のユーザー情報
echo "\n=== ユーザー情報 ===\n";
$current_user = wp_get_current_user();
echo "User ID: " . $current_user->ID . "\n";
echo "User login: " . $current_user->user_login . "\n";
echo "Has edit_posts: " . (current_user_can('edit_posts') ? 'YES' : 'NO') . "\n";
echo "Has ktpwp_access: " . (current_user_can('ktpwp_access') ? 'YES' : 'NO') . "\n";

// KTPWP_Ajax クラスが初期化されているか確認
echo "\n=== KTPWP_Ajax クラス状況 ===\n";
if (class_exists('KTPWP_Ajax')) {
    echo "✓ KTPWP_Ajax クラスが存在します\n";
    try {
        $ajax_instance = KTPWP_Ajax::get_instance();
        echo "✓ KTPWP_Ajax インスタンスが取得できます\n";
    } catch (Exception $e) {
        echo "✗ KTPWP_Ajax インスタンス取得エラー: " . $e->getMessage() . "\n";
    }
} else {
    echo "✗ KTPWP_Ajax クラスが存在しません\n";
}

// データベース接続確認
echo "\n=== データベース接続確認 ===\n";
global $wpdb;
$supplier_table = $wpdb->prefix . 'ktp_supplier';
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$supplier_table'") === $supplier_table;
echo "Supplier table exists: " . ($table_exists ? 'YES' : 'NO') . "\n";

if ($table_exists) {
    $supplier_count = $wpdb->get_var("SELECT COUNT(*) FROM $supplier_table");
    echo "Supplier count: $supplier_count\n";
    
    // 最初の協力会社の情報を取得
    $first_supplier = $wpdb->get_row("SELECT id, name, qualified_invoice_number, tax_category FROM $supplier_table ORDER BY id LIMIT 1");
    if ($first_supplier) {
        echo "First supplier: ID={$first_supplier->id}, Name={$first_supplier->name}, QIN={$first_supplier->qualified_invoice_number}, Tax={$first_supplier->tax_category}\n";
    }
}
