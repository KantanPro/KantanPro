<?php
/**
 * 適格請求書ナンバーを考慮した利益計算のテスト
 *
 * @package KTPWP
 * @since 1.0.0
 */

// 直接実行禁止
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 適格請求書ナンバーを考慮した利益計算のテストクラス
 */
class KTPWP_Qualified_Invoice_Profit_Test {

    /**
     * テストを実行
     */
    public static function run_tests() {
        echo "<h2>適格請求書ナンバーを考慮した利益計算テスト</h2>\n";
        
        // テストケース1: 適格請求書がある場合
        self::test_with_qualified_invoice();
        
        // テストケース2: 適格請求書がない場合
        self::test_without_qualified_invoice();
        
        // テストケース3: 混合ケース
        self::test_mixed_cases();
        
        echo "<h3>テスト完了</h3>\n";
    }

    /**
     * 適格請求書がある場合のテスト
     */
    private static function test_with_qualified_invoice() {
        echo "<h3>テストケース1: 適格請求書がある場合</h3>\n";
        
        // シミュレーションデータ
        $invoice_total = 110000; // 請求金額（税込）
        $cost_items = array(
            array(
                'supplier_id' => 1,
                'amount' => 55000, // 税込金額
                'tax_rate' => 10.0
            )
        );
        
        // 適格請求書がある場合の計算
        $tax_amount = 55000 * (10.0 / 100) / (1 + 10.0 / 100);
        $cost_amount = 55000 - $tax_amount;
        $expected_profit = $invoice_total - $cost_amount;
        
        echo "<p><strong>シナリオ:</strong> 適格請求書ナンバーがある協力会社からの仕入</p>\n";
        echo "<p><strong>請求金額（税込）:</strong> " . number_format($invoice_total) . "円</p>\n";
        echo "<p><strong>仕入金額（税込）:</strong> " . number_format(55000) . "円</p>\n";
        echo "<p><strong>消費税:</strong> " . number_format($tax_amount) . "円</p>\n";
        echo "<p><strong>仕入コスト（税抜）:</strong> " . number_format($cost_amount) . "円</p>\n";
        echo "<p><strong>期待利益:</strong> " . number_format($expected_profit) . "円</p>\n";
        echo "<p><strong>仕入税額控除:</strong> 可能（適格請求書あり）</p>\n";
        echo "<hr>\n";
    }

    /**
     * 適格請求書がない場合のテスト
     */
    private static function test_without_qualified_invoice() {
        echo "<h3>テストケース2: 適格請求書がない場合</h3>\n";
        
        // シミュレーションデータ
        $invoice_total = 110000; // 請求金額（税込）
        $cost_items = array(
            array(
                'supplier_id' => 2,
                'amount' => 55000, // 税込金額
                'tax_rate' => 10.0
            )
        );
        
        // 適格請求書がない場合の計算
        $cost_amount = 55000; // 税込金額をそのままコストとする
        $expected_profit = $invoice_total - $cost_amount;
        
        echo "<p><strong>シナリオ:</strong> 適格請求書ナンバーがない協力会社からの仕入</p>\n";
        echo "<p><strong>請求金額（税込）:</strong> " . number_format($invoice_total) . "円</p>\n";
        echo "<p><strong>仕入金額（税込）:</strong> " . number_format(55000) . "円</p>\n";
        echo "<p><strong>仕入コスト（税込）:</strong> " . number_format($cost_amount) . "円</p>\n";
        echo "<p><strong>期待利益:</strong> " . number_format($expected_profit) . "円</p>\n";
        echo "<p><strong>仕入税額控除:</strong> 不可（適格請求書なし）</p>\n";
        echo "<hr>\n";
    }

    /**
     * 混合ケースのテスト
     */
    private static function test_mixed_cases() {
        echo "<h3>テストケース3: 混合ケース</h3>\n";
        
        // シミュレーションデータ
        $invoice_total = 220000; // 請求金額（税込）
        $cost_items = array(
            array(
                'supplier_id' => 1, // 適格請求書あり
                'amount' => 55000, // 税込金額
                'tax_rate' => 10.0
            ),
            array(
                'supplier_id' => 2, // 適格請求書なし
                'amount' => 55000, // 税込金額
                'tax_rate' => 10.0
            )
        );
        
        // 適格請求書がある場合の計算
        $tax_amount1 = 55000 * (10.0 / 100) / (1 + 10.0 / 100);
        $cost_amount1 = 55000 - $tax_amount1;
        
        // 適格請求書がない場合の計算
        $cost_amount2 = 55000; // 税込金額をそのままコストとする
        
        $total_cost = $cost_amount1 + $cost_amount2;
        $expected_profit = $invoice_total - $total_cost;
        
        echo "<p><strong>シナリオ:</strong> 適格請求書あり・なしの混合</p>\n";
        echo "<p><strong>請求金額（税込）:</strong> " . number_format($invoice_total) . "円</p>\n";
        echo "<p><strong>仕入1（適格請求書あり）:</strong> " . number_format(55000) . "円 → コスト: " . number_format($cost_amount1) . "円</p>\n";
        echo "<p><strong>仕入2（適格請求書なし）:</strong> " . number_format(55000) . "円 → コスト: " . number_format($cost_amount2) . "円</p>\n";
        echo "<p><strong>総コスト:</strong> " . number_format($total_cost) . "円</p>\n";
        echo "<p><strong>期待利益:</strong> " . number_format($expected_profit) . "円</p>\n";
        echo "<hr>\n";
    }

    /**
     * 実際のデータベースを使用したテスト
     */
    public static function test_with_real_data() {
        echo "<h3>実際のデータベースを使用したテスト</h3>\n";
        
        global $wpdb;
        
        // 協力会社テーブルから適格請求書ナンバーがある協力会社を取得
        $supplier_table = $wpdb->prefix . 'ktp_supplier';
        $suppliers_with_qualified = $wpdb->get_results(
            "SELECT id, company_name, qualified_invoice_number FROM `{$supplier_table}` WHERE qualified_invoice_number != '' LIMIT 3"
        );
        
        $suppliers_without_qualified = $wpdb->get_results(
            "SELECT id, company_name, qualified_invoice_number FROM `{$supplier_table}` WHERE qualified_invoice_number = '' OR qualified_invoice_number IS NULL LIMIT 3"
        );
        
        echo "<h4>適格請求書ナンバーがある協力会社:</h4>\n";
        if ($suppliers_with_qualified) {
            echo "<ul>\n";
            foreach ($suppliers_with_qualified as $supplier) {
                echo "<li>ID: {$supplier->id}, 会社名: {$supplier->company_name}, 適格請求書番号: {$supplier->qualified_invoice_number}</li>\n";
            }
            echo "</ul>\n";
        } else {
            echo "<p>適格請求書ナンバーがある協力会社が見つかりませんでした。</p>\n";
        }
        
        echo "<h4>適格請求書ナンバーがない協力会社:</h4>\n";
        if ($suppliers_without_qualified) {
            echo "<ul>\n";
            foreach ($suppliers_without_qualified as $supplier) {
                echo "<li>ID: {$supplier->id}, 会社名: {$supplier->company_name}</li>\n";
            }
            echo "</ul>\n";
        } else {
            echo "<p>適格請求書ナンバーがない協力会社が見つかりませんでした。</p>\n";
        }
    }
}

// テスト実行
if (isset($_GET['run_qualified_invoice_test']) && current_user_can('manage_options')) {
    KTPWP_Qualified_Invoice_Profit_Test::run_tests();
    KTPWP_Qualified_Invoice_Profit_Test::test_with_real_data();
}
?> 