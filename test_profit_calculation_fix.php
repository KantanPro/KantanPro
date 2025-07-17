<?php
/**
 * 利益計算修正のテストファイル
 * 
 * 修正内容：
 * - 適格請求書がある場合：税抜金額のみをコストとする
 * - 適格請求書がない場合：税込金額をコストとする
 */

// テスト用データ
$test_data = array(
    'invoice_total' => 27180,
    'cost_items' => array(
        array(
            'supplier_id' => 1,
            'amount' => 1000,
            'tax_rate' => 10.0,
            'supplier_name' => 'テスト１会社',
            'qualified_invoice_number' => 'T1234567890123'
        ),
        array(
            'supplier_id' => 2,
            'amount' => 2500,
            'tax_rate' => 8.0,
            'supplier_name' => 'テスト２会社',
            'qualified_invoice_number' => 'T9876543210987'
        ),
        array(
            'supplier_id' => 3,
            'amount' => 500,
            'tax_rate' => 8.0,
            'supplier_name' => 'テスト３会社',
            'qualified_invoice_number' => '' // 適格請求書なし
        )
    )
);

echo "=== 利益計算修正テスト ===\n\n";

echo "請求金額: " . number_format($test_data['invoice_total']) . "円\n\n";

echo "コスト項目:\n";
$total_qualified_cost = 0;
$total_non_qualified_cost = 0;
$total_cost = 0;

foreach ($test_data['cost_items'] as $item) {
    $amount = $item['amount'];
    $tax_rate = $item['tax_rate'];
    $has_qualified_invoice = !empty($item['qualified_invoice_number']);
    
    echo "- " . $item['supplier_name'] . ": " . number_format($amount) . "円 (税率: " . $tax_rate . "%)\n";
    echo "  適格請求書: " . ($has_qualified_invoice ? "あり (" . $item['qualified_invoice_number'] . ")" : "なし") . "\n";
    
    if ($has_qualified_invoice) {
        // 適格請求書がある場合：税抜金額のみをコストとする
        $tax_amount = $amount * ($tax_rate / 100) / (1 + $tax_rate / 100);
        $cost_amount = $amount - $tax_amount;
        $total_qualified_cost += $cost_amount;
        $total_cost += $cost_amount;
        
        echo "  税抜コスト: " . number_format($cost_amount, 2) . "円 (税額: " . number_format($tax_amount, 2) . "円)\n";
    } else {
        // 適格請求書がない場合：税込金額をコストとする
        $total_non_qualified_cost += $amount;
        $total_cost += $amount;
        
        echo "  税込コスト: " . number_format($amount) . "円 (税額控除不可)\n";
    }
    echo "\n";
}

echo "=== 計算結果 ===\n";
echo "適格請求書コスト: " . number_format($total_qualified_cost, 2) . "円\n";
echo "非適格請求書コスト: " . number_format($total_non_qualified_cost) . "円\n";
echo "総コスト: " . number_format($total_cost, 2) . "円\n";

// 内税調整を行わない場合の利益計算（修正版）
$adjusted_invoice_total = $test_data['invoice_total']; // 調整しない
$profit = $adjusted_invoice_total - $total_cost;

echo "請求金額: " . number_format($adjusted_invoice_total) . "円 (調整なし)\n";
echo "利益: " . number_format($profit, 2) . "円\n\n";

echo "=== 期待値との比較 ===\n";
echo "期待される適格請求書コスト: 3,224円\n";
echo "実際の適格請求書コスト: " . number_format(ceil($total_qualified_cost)) . "円\n";
echo "期待される非適格請求書コスト: 500円\n";
echo "実際の非適格請求書コスト: " . number_format($total_non_qualified_cost) . "円\n";
echo "期待される利益: 23,180円\n";
echo "実際の利益: " . number_format(floor($profit)) . "円\n";

// 検証
$qualified_match = abs(ceil($total_qualified_cost) - 3224) <= 1;
$non_qualified_match = $total_non_qualified_cost == 500;
$profit_match = abs(floor($profit) - 23180) <= 10; // 23,180円を期待値にする

echo "\n=== 検証結果 ===\n";
echo "適格請求書コスト: " . ($qualified_match ? "✓ 一致" : "✗ 不一致") . "\n";
echo "非適格請求書コスト: " . ($non_qualified_match ? "✓ 一致" : "✗ 不一致") . "\n";
echo "利益計算: " . ($profit_match ? "✓ 一致" : "✗ 不一致") . "\n";

echo "\n=== JavaScript修正内容の確認 ===\n";
echo "修正前:\n";
echo "- 適格請求書がある場合：qualifiedInvoiceCost += amount;\n";
echo "- 適格請求書がない場合：nonQualifiedInvoiceCost += amount;\n";
echo "\n修正後:\n";
echo "- 適格請求書がある場合：税抜金額のみをコストとする\n";
echo "- 適格請求書がない場合：税込金額をコストとする\n";
?>
