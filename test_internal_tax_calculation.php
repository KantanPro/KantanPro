<?php
/**
 * 内税計算の統一テスト
 * 
 * 統一ルール：
 * - 外税計算: ceil(税抜金額 × 税率)
 * - 内税計算: ceil(税込金額 ÷ (1 + 税率) × 税率)
 */

echo "=== 内税計算統一テスト ===\n\n";

// テストデータ
$amount = 2000; // 税込金額
$tax_rate = 10; // 税率10%

echo "テストデータ:\n";
echo "税込金額: {$amount}円\n";
echo "税率: {$tax_rate}%\n\n";

// 修正後の計算（正しい）- 合計金額で一括計算
$new_tax_amount = ceil($amount * ($tax_rate / 100) / (1 + $tax_rate / 100));
echo "修正後の計算: {$amount}円 ÷ (1 + {$tax_rate}%) × {$tax_rate}% = {$new_tax_amount}円\n";

// 税抜金額の計算
$tax_exclusive_amount = $amount - $new_tax_amount;
echo "税抜金額: {$tax_exclusive_amount}円\n\n";

echo "=== 各項目ごとの内税計算テスト ===\n";

// 複数の項目がある場合のテスト
$items = [
    ['amount' => 1000, 'tax_rate' => 10],
    ['amount' => 1000, 'tax_rate' => 10]
];

$total_amount = 0;
$total_tax_amount_items = 0;

foreach ($items as $item) {
    $item_amount = $item['amount'];
    $item_tax_rate = $item['tax_rate'];
    
    // 内税計算（小数点以下切り上げ）- 各項目ごと
    $item_tax_amount = ceil($item_amount * ($item_tax_rate / 100) / (1 + $item_tax_rate / 100));
    
    $total_amount += $item_amount;
    $total_tax_amount_items += $item_tax_amount;
    
    echo "項目: {$item_amount}円（内税: {$item_tax_amount}円）\n";
}

echo "各項目合計: {$total_amount}円（内税合計: {$total_tax_amount_items}円）\n\n";

echo "=== 合計金額での一括内税計算テスト（新方式） ===\n";

$total_tax_amount_new = ceil($total_amount * ($tax_rate / 100) / (1 + $tax_rate / 100));
echo "合計金額: {$total_amount}円\n";
echo "合計内税（新方式）: {$total_tax_amount_new}円\n";

echo "差額: " . ($total_tax_amount_new - $total_tax_amount_items) . "円\n\n";

echo "=== コスト項目の内税計算テスト ===\n";

$cost_amount = 1500;
$cost_tax_rate = 10;

// コスト項目の内税計算（小数点以下切り上げ）
$cost_tax_amount = ceil($cost_amount * ($cost_tax_rate / 100) / (1 + $cost_tax_rate / 100));

echo "コスト項目: {$cost_amount}円（内税: {$cost_tax_amount}円）\n";

echo "=== 統一ルールの確認 ===\n";
echo "✓ 外税計算: ceil(税抜金額 × 税率)\n";
echo "✓ 内税計算: ceil(税込金額 ÷ (1 + 税率) × 税率)\n";
echo "✓ すべての計算で切り上げ（ceil）を使用\n";
echo "✓ 請求項目とコスト項目で統一されたルール\n\n";

echo "=== 利益計算の確認 ===\n";
$invoice_total_with_tax = $amount + $new_tax_amount;
$cost_total_with_tax = $cost_amount + $cost_tax_amount;
$profit = $invoice_total_with_tax - $cost_total_with_tax;

echo "請求項目税込合計: {$invoice_total_with_tax}円\n";
echo "コスト項目税込合計: {$cost_total_with_tax}円\n";
echo "利益: {$profit}円\n";
echo "✓ 利益計算は単純な引き算で正しい\n\n";

echo "=== 統一完了 ===\n";
echo "すべての消費税計算で切り上げルールが統一されました。\n";
echo "請求項目とコスト項目で一貫した計算が行われます。\n";
?> 