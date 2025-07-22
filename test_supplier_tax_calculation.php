<?php
/**
 * 協力会社の税区分による税額計算テスト
 * 
 * @package KTPWP
 */

// 直接実行禁止
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// テスト用の関数
function test_supplier_tax_calculation() {
    echo '<h2>協力会社の税区分による税額計算テスト</h2>';
    
    // 必要なクラスを読み込み
    if ( ! class_exists( 'KTPWP_Supplier_Data' ) ) {
        require_once __DIR__ . '/includes/class-supplier-data.php';
    }
    
    $supplier_data = new KTPWP_Supplier_Data();
    
    // テストケース1: 内税の協力会社
    echo '<h3>テストケース1: 内税の協力会社</h3>';
    $supplier_id_1 = 1; // 実際の協力会社IDに変更してください
    $tax_category_1 = $supplier_data->get_tax_category_by_supplier_id( $supplier_id_1 );
    echo "協力会社ID: {$supplier_id_1}<br>";
    echo "税区分: {$tax_category_1}<br>";
    
    $amount_1 = 10000;
    $tax_rate_1 = 10;
    
    if ( $tax_category_1 === '外税' ) {
        $tax_amount_1 = ceil( $amount_1 * ( $tax_rate_1 / 100 ) );
        echo "外税計算: 税抜金額 {$amount_1}円 × {$tax_rate_1}% = {$tax_amount_1}円<br>";
    } else {
        $tax_amount_1 = ceil( $amount_1 * ( $tax_rate_1 / 100 ) / ( 1 + $tax_rate_1 / 100 ) );
        echo "内税計算: 税込金額 {$amount_1}円 × {$tax_rate_1}% ÷ (1 + {$tax_rate_1}%) = {$tax_amount_1}円<br>";
    }
    
    // テストケース2: 外税の協力会社
    echo '<h3>テストケース2: 外税の協力会社</h3>';
    $supplier_id_2 = 2; // 実際の協力会社IDに変更してください
    $tax_category_2 = $supplier_data->get_tax_category_by_supplier_id( $supplier_id_2 );
    echo "協力会社ID: {$supplier_id_2}<br>";
    echo "税区分: {$tax_category_2}<br>";
    
    $amount_2 = 10000;
    $tax_rate_2 = 10;
    
    if ( $tax_category_2 === '外税' ) {
        $tax_amount_2 = ceil( $amount_2 * ( $tax_rate_2 / 100 ) );
        echo "外税計算: 税抜金額 {$amount_2}円 × {$tax_rate_2}% = {$tax_amount_2}円<br>";
    } else {
        $tax_amount_2 = ceil( $amount_2 * ( $tax_rate_2 / 100 ) / ( 1 + $tax_rate_2 / 100 ) );
        echo "内税計算: 税込金額 {$amount_2}円 × {$tax_rate_2}% ÷ (1 + {$tax_rate_2}%) = {$tax_amount_2}円<br>";
    }
    
    // テストケース3: 無効な協力会社ID
    echo '<h3>テストケース3: 無効な協力会社ID</h3>';
    $supplier_id_3 = 99999;
    $tax_category_3 = $supplier_data->get_tax_category_by_supplier_id( $supplier_id_3 );
    echo "協力会社ID: {$supplier_id_3}<br>";
    echo "税区分: {$tax_category_3}<br>";
    
    // データベース内の協力会社一覧を表示
    echo '<h3>データベース内の協力会社一覧</h3>';
    global $wpdb;
    $supplier_table = $wpdb->prefix . 'ktp_supplier';
    $suppliers = $wpdb->get_results( "SELECT id, company_name, tax_category FROM {$supplier_table} ORDER BY id ASC LIMIT 10" );
    
    if ( $suppliers ) {
        echo '<table border="1" style="border-collapse: collapse; margin: 10px 0;">';
        echo '<tr><th>ID</th><th>会社名</th><th>税区分</th></tr>';
        foreach ( $suppliers as $supplier ) {
            echo '<tr>';
            echo '<td>' . esc_html( $supplier->id ) . '</td>';
            echo '<td>' . esc_html( $supplier->company_name ) . '</td>';
            echo '<td>' . esc_html( $supplier->tax_category ) . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    } else {
        echo '協力会社データが見つかりません。';
    }
}

// テスト実行
if ( current_user_can( 'manage_options' ) ) {
    test_supplier_tax_calculation();
} else {
    echo '権限がありません。';
}
?> 