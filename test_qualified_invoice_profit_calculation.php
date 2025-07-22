<?php
/**
 * 適格請求書を考慮した利益計算テスト
 * 
 * @package KTPWP
 */

// 直接実行禁止
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// テスト用の関数
function test_qualified_invoice_profit_calculation() {
    echo '<h2>適格請求書を考慮した利益計算テスト</h2>';
    
    // 必要なクラスを読み込み
    if ( ! class_exists( 'KTPWP_Supplier_Data' ) ) {
        require_once __DIR__ . '/includes/class-supplier-data.php';
    }
    
    $supplier_data = new KTPWP_Supplier_Data();
    
    // テストケース1: 適格請求書ありの協力会社
    echo '<h3>テストケース1: 適格請求書ありの協力会社</h3>';
    $supplier_id_1 = 1; // 実際の協力会社IDに変更してください
    $qualified_invoice_number_1 = $supplier_data->get_qualified_invoice_number_by_supplier_id( $supplier_id_1 );
    $tax_category_1 = $supplier_data->get_tax_category_by_supplier_id( $supplier_id_1 );
    
    echo "協力会社ID: {$supplier_id_1}<br>";
    echo "適格請求書番号: " . ($qualified_invoice_number_1 ? $qualified_invoice_number_1 : 'なし') . "<br>";
    echo "税区分: {$tax_category_1}<br>";
    
    $amount_1 = 11000; // 税込金額
    $tax_rate_1 = 10;
    
    if ( $qualified_invoice_number_1 ) {
        // 適格請求書あり：税抜金額をコストとする
        if ( $tax_category_1 === '外税' ) {
            $tax_amount_1 = $amount_1 * ( $tax_rate_1 / 100 );
            $cost_amount_1 = $amount_1 - $tax_amount_1;
        } else {
            $tax_amount_1 = $amount_1 * ( $tax_rate_1 / 100 ) / ( 1 + $tax_rate_1 / 100 );
            $cost_amount_1 = $amount_1 - $tax_amount_1;
        }
        echo "税込金額: " . number_format( $amount_1 ) . "円<br>";
        echo "税額: " . number_format( $tax_amount_1 ) . "円<br>";
        echo "コスト（税抜）: " . number_format( $cost_amount_1 ) . "円<br>";
    } else {
        // 適格請求書なし：税込金額をコストとする
        $cost_amount_1 = $amount_1;
        echo "税込金額: " . number_format( $amount_1 ) . "円<br>";
        echo "コスト（税込）: " . number_format( $cost_amount_1 ) . "円<br>";
    }
    
    // テストケース2: 適格請求書なしの協力会社
    echo '<h3>テストケース2: 適格請求書なしの協力会社</h3>';
    $supplier_id_2 = 2; // 実際の協力会社IDに変更してください
    $qualified_invoice_number_2 = $supplier_data->get_qualified_invoice_number_by_supplier_id( $supplier_id_2 );
    $tax_category_2 = $supplier_data->get_tax_category_by_supplier_id( $supplier_id_2 );
    
    echo "協力会社ID: {$supplier_id_2}<br>";
    echo "適格請求書番号: " . ($qualified_invoice_number_2 ? $qualified_invoice_number_2 : 'なし') . "<br>";
    echo "税区分: {$tax_category_2}<br>";
    
    $amount_2 = 11000; // 税込金額
    $tax_rate_2 = 10;
    
    if ( $qualified_invoice_number_2 ) {
        // 適格請求書あり：税抜金額をコストとする
        if ( $tax_category_2 === '外税' ) {
            $tax_amount_2 = $amount_2 * ( $tax_rate_2 / 100 );
            $cost_amount_2 = $amount_2 - $tax_amount_2;
        } else {
            $tax_amount_2 = $amount_2 * ( $tax_rate_2 / 100 ) / ( 1 + $tax_rate_2 / 100 );
            $cost_amount_2 = $amount_2 - $tax_amount_2;
        }
        echo "税込金額: " . number_format( $amount_2 ) . "円<br>";
        echo "税額: " . number_format( $tax_amount_2 ) . "円<br>";
        echo "コスト（税抜）: " . number_format( $cost_amount_2 ) . "円<br>";
    } else {
        // 適格請求書なし：税込金額をコストとする
        $cost_amount_2 = $amount_2;
        echo "税込金額: " . number_format( $amount_2 ) . "円<br>";
        echo "コスト（税込）: " . number_format( $cost_amount_2 ) . "円<br>";
    }
    
    // 利益計算の例
    echo '<h3>利益計算例</h3>';
    $invoice_total = 50000; // 請求金額（税込）
    $total_cost = $cost_amount_1 + $cost_amount_2;
    $profit = $invoice_total - $total_cost;
    
    echo "請求金額（税込）: " . number_format( $invoice_total ) . "円<br>";
    echo "総コスト: " . number_format( $total_cost ) . "円<br>";
    echo "利益: " . number_format( $profit ) . "円 (" . ($profit >= 0 ? '黒字' : '赤字') . ")<br>";
    
    // 適格請求書の有無による違い
    echo '<h3>適格請求書の有無による違い</h3>';
    echo "適格請求書ありの場合：税抜金額をコストとする（仕入税額控除可能）<br>";
    echo "適格請求書なしの場合：税込金額をコストとする（仕入税額控除不可）<br>";
    echo "この違いにより、適格請求書がある協力会社からの仕入れが多いほど利益が増加します。<br>";
}

// テスト実行
if ( isset( $_GET['test_qualified_invoice'] ) ) {
    test_qualified_invoice_profit_calculation();
}
?> 