<?php
/**
 * AJAX適格請求書番号取得テスト（修正版）
 * 
 * このスクリプトは直接PHPでAJAXハンドラーをテストします
 */

// WordPress環境を読み込む
require_once __DIR__ . '/../../../wp-config.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';

// ログイン状態を模擬
wp_set_current_user(1); // 管理者ユーザーとしてログイン

// KTPWPプラグインを読み込む
require_once __DIR__ . '/ktpwp.php';

// AJAX処理クラスの初期化
$ajax_handler = KTPWP_Ajax::getInstance();

// テスト用のデータ
$test_supplier_id = 1;

echo "<h1>AJAX適格請求書番号取得テスト</h1>\n";

// データベースのテーブル確認
global $wpdb;
$supplier_table = $wpdb->prefix . 'ktp_supplier';
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$supplier_table'") === $supplier_table;

echo "<h2>データベース情報</h2>\n";
echo "テーブル存在: " . ($table_exists ? "はい" : "いいえ") . "\n";

if ($table_exists) {
    // 協力会社テーブルの構造を確認
    $columns = $wpdb->get_results("DESCRIBE `$supplier_table`");
    echo "<h3>テーブル構造</h3>\n";
    echo "<table border='1'>\n";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>\n";
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td>" . $column->Field . "</td>";
        echo "<td>" . $column->Type . "</td>";
        echo "<td>" . $column->Null . "</td>";
        echo "<td>" . $column->Key . "</td>";
        echo "<td>" . $column->Default . "</td>";
        echo "<td>" . $column->Extra . "</td>";
        echo "</tr>\n";
    }
    echo "</table>\n";
    
    // 協力会社データの確認
    $suppliers = $wpdb->get_results("SELECT id, name, qualified_invoice_number FROM `$supplier_table` ORDER BY id LIMIT 10");
    echo "<h3>協力会社データ（最初の10件）</h3>\n";
    echo "<table border='1'>\n";
    echo "<tr><th>ID</th><th>Name</th><th>Qualified Invoice Number</th></tr>\n";
    foreach ($suppliers as $supplier) {
        echo "<tr>";
        echo "<td>" . $supplier->id . "</td>";
        echo "<td>" . $supplier->name . "</td>";
        echo "<td>" . ($supplier->qualified_invoice_number ?: '（空）') . "</td>";
        echo "</tr>\n";
    }
    echo "</table>\n";
    
    // 実際のAJAXハンドラーテスト
    echo "<h2>AJAX ハンドラー テスト</h2>\n";
    
    // POSTデータを模擬
    $_POST = [
        'supplier_id' => $test_supplier_id,
        'nonce' => wp_create_nonce('ktp_ajax_nonce'),
        'action' => 'ktp_get_supplier_qualified_invoice_number'
    ];
    
    echo "<h3>送信データ</h3>\n";
    echo "<pre>" . print_r($_POST, true) . "</pre>\n";
    
    // 出力バッファリングでAJAXレスポンスをキャプチャ
    ob_start();
    
    try {
        // 直接AJAXハンドラーを呼び出し
        $ajax_handler->ajax_get_supplier_qualified_invoice_number();
    } catch (Exception $e) {
        echo "エラー発生: " . $e->getMessage() . "\n";
    }
    
    $output = ob_get_clean();
    
    echo "<h3>AJAX レスポンス</h3>\n";
    echo "<pre>" . htmlspecialchars($output) . "</pre>\n";
    
    // JSONレスポンスの解析
    $json_response = json_decode($output, true);
    if ($json_response) {
        echo "<h3>解析されたレスポンス</h3>\n";
        echo "<pre>" . print_r($json_response, true) . "</pre>\n";
    }
    
    // 税区分取得もテスト
    echo "<h2>税区分取得テスト</h2>\n";
    
    $_POST = [
        'supplier_id' => $test_supplier_id,
        'nonce' => wp_create_nonce('ktp_ajax_nonce'),
        'action' => 'ktp_get_supplier_tax_category'
    ];
    
    ob_start();
    
    try {
        $ajax_handler->ajax_get_supplier_tax_category();
    } catch (Exception $e) {
        echo "エラー発生: " . $e->getMessage() . "\n";
    }
    
    $tax_output = ob_get_clean();
    
    echo "<h3>税区分AJAX レスポンス</h3>\n";
    echo "<pre>" . htmlspecialchars($tax_output) . "</pre>\n";
    
    $tax_json_response = json_decode($tax_output, true);
    if ($tax_json_response) {
        echo "<h3>解析された税区分レスポンス</h3>\n";
        echo "<pre>" . print_r($tax_json_response, true) . "</pre>\n";
    }
    
} else {
    echo "<p>協力会社テーブルが存在しません。</p>\n";
}

// 権限情報
echo "<h2>権限情報</h2>\n";
echo "現在のユーザーID: " . get_current_user_id() . "\n";
echo "edit_posts権限: " . (current_user_can('edit_posts') ? "はい" : "いいえ") . "\n";
echo "ktpwp_access権限: " . (current_user_can('ktpwp_access') ? "はい" : "いいえ") . "\n";
echo "管理者権限: " . (current_user_can('manage_options') ? "はい" : "いいえ") . "\n";

echo "<h2>テスト完了</h2>\n";
?>
