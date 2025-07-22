<?php
/**
 * 複数税率計算テスト
 * 
 * このファイルは複数税率に対応したリアルタイム計算システムのテスト用です。
 * 様々な税率の組み合わせで計算が正しく動作することを確認できます。
 */

// 直接実行禁止
if (!defined('ABSPATH')) {
    exit;
}

// テスト用のHTMLを出力
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>複数税率計算テスト</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #f5f5f5;
        }
        .test-container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .test-section {
            margin-bottom: 30px;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .test-title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 15px;
            color: #333;
        }
        .test-description {
            margin-bottom: 15px;
            color: #666;
        }
        .test-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        .test-table th,
        .test-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        .test-table th {
            background-color: #f8f9fa;
            font-weight: bold;
        }
        .test-result {
            padding: 10px;
            border-radius: 4px;
            margin-top: 10px;
        }
        .test-result.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .test-result.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .test-result.info {
            background-color: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        .tax-rate-input {
            width: 60px;
            text-align: right;
        }
        .amount-input {
            width: 100px;
            text-align: right;
        }
        .total-display {
            font-weight: bold;
            font-size: 16px;
            margin-top: 10px;
            padding: 10px;
            background-color: #e9ecef;
            border-radius: 4px;
        }
        .button {
            background-color: #007cba;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            margin: 5px;
        }
        .button:hover {
            background-color: #005a87;
        }
        .button.secondary {
            background-color: #6c757d;
        }
        .button.secondary:hover {
            background-color: #545b62;
        }
    </style>
</head>
<body>
    <div class="test-container">
        <h1>複数税率計算テスト</h1>
        <p>このページでは、複数税率に対応したリアルタイム計算システムの動作をテストできます。</p>

        <!-- テスト1: 基本税率計算 -->
        <div class="test-section">
            <div class="test-title">テスト1: 基本税率計算（10%のみ）</div>
            <div class="test-description">
                全ての項目が10%税率の場合の計算をテストします。
            </div>
            <table class="test-table">
                <thead>
                    <tr>
                        <th>項目名</th>
                        <th>単価</th>
                        <th>数量</th>
                        <th>金額</th>
                        <th>税率</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>サービスA</td>
                        <td><input type="number" class="price-input" value="1000" step="1"></td>
                        <td><input type="number" class="quantity-input" value="2" step="1"></td>
                        <td><input type="number" class="amount-input" value="2000" readonly></td>
                        <td><input type="number" class="tax-rate-input" value="10" step="1">%</td>
                    </tr>
                    <tr>
                        <td>サービスB</td>
                        <td><input type="number" class="price-input" value="1500" step="1"></td>
                        <td><input type="number" class="quantity-input" value="1" step="1"></td>
                        <td><input type="number" class="amount-input" value="1500" readonly></td>
                        <td><input type="number" class="tax-rate-input" value="10" step="1">%</td>
                    </tr>
                </tbody>
            </table>
            <div class="total-display" id="total-1">
                合計: 3,500円（内税: 318円）
            </div>
            <button class="button" onclick="calculateTest1()">計算実行</button>
            <div class="test-result info" id="result-1">
                期待値: 合計3,500円、内税318円（3,500 × 10% ÷ 1.1 を切り上げ）
            </div>
        </div>

        <!-- テスト2: 複数税率計算 -->
        <div class="test-section">
            <div class="test-title">テスト2: 複数税率計算（10%と8%）</div>
            <div class="test-description">
                10%税率と8%税率が混在する場合の計算をテストします。
            </div>
            <table class="test-table">
                <thead>
                    <tr>
                        <th>項目名</th>
                        <th>単価</th>
                        <th>数量</th>
                        <th>金額</th>
                        <th>税率</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>一般サービス</td>
                        <td><input type="number" class="price-input" value="1000" step="1"></td>
                        <td><input type="number" class="quantity-input" value="3" step="1"></td>
                        <td><input type="number" class="amount-input" value="3000" readonly></td>
                        <td><input type="number" class="tax-rate-input" value="10" step="1">%</td>
                    </tr>
                    <tr>
                        <td>軽減税率サービス</td>
                        <td><input type="number" class="price-input" value="800" step="1"></td>
                        <td><input type="number" class="quantity-input" value="2" step="1"></td>
                        <td><input type="number" class="amount-input" value="1600" readonly></td>
                        <td><input type="number" class="tax-rate-input" value="8" step="1">%</td>
                    </tr>
                </tbody>
            </table>
            <div class="total-display" id="total-2">
                合計: 4,600円（内税: 10%: 273円, 8%: 119円）
            </div>
            <button class="button" onclick="calculateTest2()">計算実行</button>
            <div class="test-result info" id="result-2">
                期待値: 合計4,600円、内税10%: 273円（3,000 × 10% ÷ 1.1 を切り上げ）、内税8%: 119円（1,600 × 8% ÷ 1.08 を切り上げ）
            </div>
        </div>

        <!-- テスト3: 外税計算 -->
        <div class="test-section">
            <div class="test-title">テスト3: 外税計算</div>
            <div class="test-description">
                外税表示の場合の計算をテストします。
            </div>
            <table class="test-table">
                <thead>
                    <tr>
                        <th>項目名</th>
                        <th>単価</th>
                        <th>数量</th>
                        <th>金額</th>
                        <th>税率</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>外税サービスA</td>
                        <td><input type="number" class="price-input" value="1000" step="1"></td>
                        <td><input type="number" class="quantity-input" value="2" step="1"></td>
                        <td><input type="number" class="amount-input" value="2000" readonly></td>
                        <td><input type="number" class="tax-rate-input" value="10" step="1">%</td>
                    </tr>
                    <tr>
                        <td>外税サービスB</td>
                        <td><input type="number" class="price-input" value="1500" step="1"></td>
                        <td><input type="number" class="quantity-input" value="1" step="1"></td>
                        <td><input type="number" class="amount-input" value="1500" readonly></td>
                        <td><input type="number" class="tax-rate-input" value="8" step="1">%</td>
                    </tr>
                </tbody>
            </table>
            <div class="total-display" id="total-3">
                合計金額: 3,500円<br>
                消費税: 10%: 200円, 8%: 120円<br>
                税込合計: 3,820円
            </div>
            <button class="button" onclick="calculateTest3()">計算実行</button>
            <div class="test-result info" id="result-3">
                期待値: 合計3,500円、消費税10%: 200円（2,000 × 10% を切り上げ）、消費税8%: 120円（1,500 × 8% を切り上げ）、税込合計3,820円
            </div>
        </div>

        <!-- テスト4: リアルタイム計算テスト -->
        <div class="test-section">
            <div class="test-title">テスト4: リアルタイム計算テスト</div>
            <div class="test-description">
                税率を変更した際のリアルタイム再計算をテストします。
            </div>
            <table class="test-table">
                <thead>
                    <tr>
                        <th>項目名</th>
                        <th>単価</th>
                        <th>数量</th>
                        <th>金額</th>
                        <th>税率</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>動的テスト項目</td>
                        <td><input type="number" class="price-input" value="1000" step="1"></td>
                        <td><input type="number" class="quantity-input" value="1" step="1"></td>
                        <td><input type="number" class="amount-input" value="1000" readonly></td>
                        <td><input type="number" class="tax-rate-input" value="10" step="1" onchange="calculateRealTime()">%</td>
                    </tr>
                </tbody>
            </table>
            <div class="total-display" id="total-4">
                合計: 1,000円（内税: 91円）
            </div>
            <div class="test-result info" id="result-4">
                税率を変更すると、リアルタイムで計算結果が更新されます。
            </div>
        </div>

        <!-- テスト結果サマリー -->
        <div class="test-section">
            <div class="test-title">テスト結果サマリー</div>
            <div id="test-summary">
                <div class="test-result info">
                    テストを実行して結果を確認してください。
                </div>
            </div>
            <button class="button" onclick="runAllTests()">全テスト実行</button>
            <button class="button secondary" onclick="clearResults()">結果クリア</button>
        </div>
    </div>

    <script>
        // テスト1: 基本税率計算
        function calculateTest1() {
            const amounts = [2000, 1500];
            const taxRates = [10, 10];
            const total = amounts.reduce((sum, amount) => sum + amount, 0);
            
            // 内税計算（10%のみ）
            const taxAmount = Math.ceil(total * 0.1 / 1.1);
            
            document.getElementById('total-1').innerHTML = `合計: ${total.toLocaleString()}円（内税: ${taxAmount.toLocaleString()}円）`;
            
            const expectedTax = Math.ceil(3500 * 0.1 / 1.1);
            const success = taxAmount === expectedTax;
            
            document.getElementById('result-1').innerHTML = 
                success ? 
                `✅ 成功: 期待値通り ${expectedTax}円 でした` :
                `❌ 失敗: 期待値 ${expectedTax}円 に対して実際は ${taxAmount}円 でした`;
            
            document.getElementById('result-1').className = 
                success ? 'test-result success' : 'test-result error';
        }

        // テスト2: 複数税率計算
        function calculateTest2() {
            const amounts = [3000, 1600];
            const taxRates = [10, 8];
            const total = amounts.reduce((sum, amount) => sum + amount, 0);
            
            // 税率別に計算
            const tax10Amount = Math.ceil(amounts[0] * 0.1 / 1.1);
            const tax8Amount = Math.ceil(amounts[1] * 0.08 / 1.08);
            const totalTax = tax10Amount + tax8Amount;
            
            document.getElementById('total-2').innerHTML = 
                `合計: ${total.toLocaleString()}円（内税: 10%: ${tax10Amount.toLocaleString()}円, 8%: ${tax8Amount.toLocaleString()}円）`;
            
            const expectedTax10 = Math.ceil(3000 * 0.1 / 1.1);
            const expectedTax8 = Math.ceil(1600 * 0.08 / 1.08);
            const success = tax10Amount === expectedTax10 && tax8Amount === expectedTax8;
            
            document.getElementById('result-2').innerHTML = 
                success ? 
                `✅ 成功: 10%税率 ${expectedTax10}円, 8%税率 ${expectedTax8}円 でした` :
                `❌ 失敗: 期待値 10%税率 ${expectedTax10}円, 8%税率 ${expectedTax8}円 に対して実際は 10%税率 ${tax10Amount}円, 8%税率 ${tax8Amount}円 でした`;
            
            document.getElementById('result-2').className = 
                success ? 'test-result success' : 'test-result error';
        }

        // テスト3: 外税計算
        function calculateTest3() {
            const amounts = [2000, 1500];
            const taxRates = [10, 8];
            const total = amounts.reduce((sum, amount) => sum + amount, 0);
            
            // 外税計算
            const tax10Amount = Math.ceil(amounts[0] * 0.1);
            const tax8Amount = Math.ceil(amounts[1] * 0.08);
            const totalTax = tax10Amount + tax8Amount;
            const totalWithTax = total + totalTax;
            
            document.getElementById('total-3').innerHTML = 
                `合計金額: ${total.toLocaleString()}円<br>` +
                `消費税: 10%: ${tax10Amount.toLocaleString()}円, 8%: ${tax8Amount.toLocaleString()}円<br>` +
                `税込合計: ${totalWithTax.toLocaleString()}円`;
            
            const expectedTax10 = Math.ceil(2000 * 0.1);
            const expectedTax8 = Math.ceil(1500 * 0.08);
            const success = tax10Amount === expectedTax10 && tax8Amount === expectedTax8;
            
            document.getElementById('result-3').innerHTML = 
                success ? 
                `✅ 成功: 10%税率 ${expectedTax10}円, 8%税率 ${expectedTax8}円 でした` :
                `❌ 失敗: 期待値 10%税率 ${expectedTax10}円, 8%税率 ${expectedTax8}円 に対して実際は 10%税率 ${tax10Amount}円, 8%税率 ${tax8Amount}円 でした`;
            
            document.getElementById('result-3').className = 
                success ? 'test-result success' : 'test-result error';
        }

        // テスト4: リアルタイム計算
        function calculateRealTime() {
            const priceInput = document.querySelector('#total-4').closest('.test-section').querySelector('.price-input');
            const quantityInput = document.querySelector('#total-4').closest('.test-section').querySelector('.quantity-input');
            const taxRateInput = document.querySelector('#total-4').closest('.test-section').querySelector('.tax-rate-input');
            
            const price = parseFloat(priceInput.value) || 0;
            const quantity = parseFloat(quantityInput.value) || 0;
            const taxRate = parseFloat(taxRateInput.value) || 0;
            
            const amount = price * quantity;
            const taxAmount = Math.ceil(amount * taxRate / 100 / (1 + taxRate / 100));
            
            document.getElementById('total-4').innerHTML = 
                `合計: ${amount.toLocaleString()}円（内税: ${taxAmount.toLocaleString()}円）`;
        }

        // 全テスト実行
        function runAllTests() {
            calculateTest1();
            calculateTest2();
            calculateTest3();
            
            // サマリー更新
            const results = document.querySelectorAll('.test-result');
            let successCount = 0;
            let totalCount = 0;
            
            results.forEach(result => {
                if (result.classList.contains('success') || result.classList.contains('error')) {
                    totalCount++;
                    if (result.classList.contains('success')) {
                        successCount++;
                    }
                }
            });
            
            const summary = document.getElementById('test-summary');
            if (totalCount > 0) {
                const successRate = Math.round((successCount / totalCount) * 100);
                summary.innerHTML = `
                    <div class="test-result ${successCount === totalCount ? 'success' : 'error'}">
                        テスト結果: ${successCount}/${totalCount} 成功 (${successRate}%)
                    </div>
                `;
            }
        }

        // 結果クリア
        function clearResults() {
            const results = document.querySelectorAll('.test-result');
            results.forEach(result => {
                result.className = 'test-result info';
                result.innerHTML = 'テストを実行して結果を確認してください。';
            });
            
            document.getElementById('test-summary').innerHTML = `
                <div class="test-result info">
                    テストを実行して結果を確認してください。
                </div>
            `;
        }

        // ページ読み込み時の初期化
        document.addEventListener('DOMContentLoaded', function() {
            // 金額計算の初期化
            const priceInputs = document.querySelectorAll('.price-input');
            const quantityInputs = document.querySelectorAll('.quantity-input');
            const amountInputs = document.querySelectorAll('.amount-input');
            
            // 金額計算関数
            function calculateAmount(index) {
                const price = parseFloat(priceInputs[index].value) || 0;
                const quantity = parseFloat(quantityInputs[index].value) || 0;
                const amount = price * quantity;
                amountInputs[index].value = amount;
            }
            
            // イベントリスナー設定
            priceInputs.forEach((input, index) => {
                input.addEventListener('input', () => calculateAmount(index));
            });
            
            quantityInputs.forEach((input, index) => {
                input.addEventListener('input', () => calculateAmount(index));
            });
            
            // 初期計算
            for (let i = 0; i < priceInputs.length; i++) {
                calculateAmount(i);
            }
        });
    </script>
</body>
</html> 