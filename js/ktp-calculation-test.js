/**
 * 金額計算機能の包括的テストスクリプト
 * ブラウザコンソールで実行して自動計算機能の動作を検証
 */

// 包括的な計算テスト
function testCalculationComplete() {
    console.log('=== 金額計算機能 包括テスト開始 ===');
    
    // 1. 初期状態チェック
    testInitialState();
    
    // 2. 請求項目の計算テスト
    testInvoiceCalculation();
    
    // 3. コスト項目の計算テスト
    testCostCalculation();
    
    // 4. フィールド有効化テスト
    testFieldActivation();
    
    // 5. 合計・利益計算テスト
    testTotalCalculation();
    
    console.log('=== テスト完了 ===');
}

// 初期状態のチェック
function testInitialState() {
    console.log('--- 初期状態チェック ---');
    
    const invoiceRows = $('.invoice-items-table tbody tr').length;
    const costRows = $('.cost-items-table tbody tr').length;
    
    console.log(`請求項目行数: ${invoiceRows}`);
    console.log(`コスト項目行数: ${costRows}`);
    
    // デバッグモードの確認
    console.log(`デバッグモード: ${window.ktpDebugMode ? '有効' : '無効'}`);
    
    // 計算関数の存在確認
    console.log(`calculateAmount関数: ${typeof calculateAmount === 'function' ? '存在' : '未定義'}`);
}

// 請求項目の計算テスト
function testInvoiceCalculation() {
    console.log('--- 請求項目計算テスト ---');
    
    $('.invoice-items-table tbody tr').each(function(index) {
        const $row = $(this);
        const $price = $row.find('.price');
        const $quantity = $row.find('.quantity');
        const $amount = $row.find('.amount');
        
        if ($price.length === 0 || $quantity.length === 0 || $amount.length === 0) {
            console.log(`Row ${index}: 必要なフィールドが見つからない`);
            return;
        }
        
        const testPrice = 1000;
        const testQuantity = 2;
        const expectedAmount = testPrice * testQuantity;
        
        // テスト値を設定
        if (!$price.prop('disabled')) {
            $price.val(testPrice);
            $quantity.val(testQuantity);
            
            // 計算を実行
            if (typeof calculateAmount === 'function') {
                calculateAmount($row);
            }
            
            const actualAmount = parseFloat($amount.val()) || 0;
            const success = actualAmount === expectedAmount;
            
            console.log(`Row ${index}: 単価${testPrice} × 数量${testQuantity} = ${actualAmount} (期待値: ${expectedAmount}) ${success ? '✓' : '✗'}`);
        } else {
            console.log(`Row ${index}: 価格フィールドが無効化されている`);
        }
    });
}

// コスト項目の計算テスト
function testCostCalculation() {
    console.log('--- コスト項目計算テスト ---');
    
    $('.cost-items-table tbody tr').each(function(index) {
        const $row = $(this);
        const $price = $row.find('.price');
        const $quantity = $row.find('.quantity');
        const $amount = $row.find('.amount');
        
        if ($price.length === 0 || $quantity.length === 0 || $amount.length === 0) {
            console.log(`Row ${index}: 必要なフィールドが見つからない`);
            return;
        }
        
        const testPrice = 500;
        const testQuantity = 3;
        const expectedAmount = testPrice * testQuantity;
        
        // テスト値を設定
        if (!$price.prop('disabled')) {
            $price.val(testPrice);
            $quantity.val(testQuantity);
            
            // 計算を実行
            if (typeof calculateAmount === 'function') {
                calculateAmount($row);
            }
            
            const actualAmount = parseFloat($amount.val()) || 0;
            const success = actualAmount === expectedAmount;
            
            console.log(`Row ${index}: 単価${testPrice} × 数量${testQuantity} = ${actualAmount} (期待値: ${expectedAmount}) ${success ? '✓' : '✗'}`);
        } else {
            console.log(`Row ${index}: 価格フィールドが無効化されている`);
        }
    });
}

// フィールド有効化テスト
function testFieldActivation() {
    console.log('--- フィールド有効化テスト ---');
    
    // 請求項目のテスト
    $('.invoice-items-table tbody tr').each(function(index) {
        const $row = $(this);
        const $productName = $row.find('.product-name');
        const $price = $row.find('.price');
        const $quantity = $row.find('.quantity');
        
        if ($productName.length === 0) return;
        
        const productNameValue = $productName.val();
        const priceDisabled = $price.prop('disabled');
        const quantityDisabled = $quantity.prop('disabled');
        
        if (productNameValue && productNameValue.trim() !== '') {
            if (priceDisabled || quantityDisabled) {
                console.log(`請求項目 Row ${index}: 商品名入力済みなのにフィールドが無効 (価格: ${priceDisabled}, 数量: ${quantityDisabled})`);
            } else {
                console.log(`請求項目 Row ${index}: フィールド状態正常 ✓`);
            }
        } else {
            if (!priceDisabled || !quantityDisabled) {
                console.log(`請求項目 Row ${index}: 商品名未入力なのにフィールドが有効 (価格: ${priceDisabled}, 数量: ${quantityDisabled})`);
            } else {
                console.log(`請求項目 Row ${index}: 新規行状態正常 ✓`);
            }
        }
    });
    
    // コスト項目のテスト
    $('.cost-items-table tbody tr').each(function(index) {
        const $row = $(this);
        const $productName = $row.find('.product-name');
        const $price = $row.find('.price');
        const $quantity = $row.find('.quantity');
        
        if ($productName.length === 0) return;
        
        const productNameValue = $productName.val();
        const priceDisabled = $price.prop('disabled');
        const quantityDisabled = $quantity.prop('disabled');
        
        if (productNameValue && productNameValue.trim() !== '') {
            if (priceDisabled || quantityDisabled) {
                console.log(`コスト項目 Row ${index}: 商品名入力済みなのにフィールドが無効 (価格: ${priceDisabled}, 数量: ${quantityDisabled})`);
            } else {
                console.log(`コスト項目 Row ${index}: フィールド状態正常 ✓`);
            }
        } else {
            if (!priceDisabled || !quantityDisabled) {
                console.log(`コスト項目 Row ${index}: 商品名未入力なのにフィールドが有効 (価格: ${priceDisabled}, 数量: ${quantityDisabled})`);
            } else {
                console.log(`コスト項目 Row ${index}: 新規行状態正常 ✓`);
            }
        }
    });
}

// 合計・利益計算テスト
function testTotalCalculation() {
    console.log('--- 合計・利益計算テスト ---');
    
    let invoiceTotal = 0;
    let costTotal = 0;
    
    // 請求項目合計計算
    $('.invoice-items-table .amount').each(function() {
        const amount = parseFloat($(this).val()) || 0;
        invoiceTotal += amount;
    });
    
    // コスト項目合計計算
    $('.cost-items-table .amount').each(function() {
        const amount = parseFloat($(this).val()) || 0;
        costTotal += amount;
    });
    
    const invoiceTotalCeiled = Math.ceil(invoiceTotal);
    const costTotalCeiled = Math.ceil(costTotal);
    const profit = invoiceTotalCeiled - costTotalCeiled;
    
    console.log(`請求項目合計: ${invoiceTotal} → ${invoiceTotalCeiled} (切り上げ)`);
    console.log(`コスト項目合計: ${costTotal} → ${costTotalCeiled} (切り上げ)`);
    console.log(`利益: ${profit} (${profit >= 0 ? '黒字' : '赤字'})`);
    
    // 表示されている値と比較
    const $invoiceTotalDisplay = $('.invoice-items-total');
    const $costTotalDisplay = $('.cost-items-total');
    const $profitDisplay = $('.profit-display');
    
    if ($invoiceTotalDisplay.length > 0) {
        console.log(`請求項目合計表示: ${$invoiceTotalDisplay.text()}`);
    }
    
    if ($costTotalDisplay.length > 0) {
        console.log(`コスト項目合計表示: ${$costTotalDisplay.text()}`);
    }
    
    if ($profitDisplay.length > 0) {
        console.log(`利益表示: ${$profitDisplay.text()}`);
    }
}

// 新規行追加テスト
function testNewRowAddition() {
    console.log('--- 新規行追加テスト ---');
    
    // 請求項目の新規行追加テスト
    const $invoiceAddBtn = $('.invoice-items-table .btn-add-row').first();
    if ($invoiceAddBtn.length > 0) {
        const beforeRowCount = $('.invoice-items-table tbody tr').length;
        
        // 現在の行に商品名を入力
        const $currentRow = $invoiceAddBtn.closest('tr');
        const $productName = $currentRow.find('.product-name');
        if ($productName.length > 0 && $productName.val().trim() === '') {
            $productName.val('テスト商品');
            console.log('請求項目: テスト用商品名を入力');
        }
        
        console.log(`請求項目 行追加前: ${beforeRowCount}行`);
        // $invoiceAddBtn.click(); // 実際のクリックはテスト環境でのみ
        console.log('請求項目: [+]ボタンクリック可能');
    }
    
    // コスト項目の新規行追加テスト
    const $costAddBtn = $('.cost-items-table .btn-add-row').first();
    if ($costAddBtn.length > 0) {
        const beforeRowCount = $('.cost-items-table tbody tr').length;
        
        // 現在の行に商品名を入力
        const $currentRow = $costAddBtn.closest('tr');
        const $productName = $currentRow.find('.product-name');
        if ($productName.length > 0 && $productName.val().trim() === '') {
            $productName.val('テストコスト');
            console.log('コスト項目: テスト用商品名を入力');
        }
        
        console.log(`コスト項目 行追加前: ${beforeRowCount}行`);
        // $costAddBtn.click(); // 実際のクリックはテスト環境でのみ
        console.log('コスト項目: [+]ボタンクリック可能');
    }
}

// ブラウザコンソールで使用できるようにwindowオブジェクトに追加
window.testCalculationComplete = testCalculationComplete;
window.testInitialState = testInitialState;
window.testInvoiceCalculation = testInvoiceCalculation;
window.testCostCalculation = testCostCalculation;
window.testFieldActivation = testFieldActivation;
window.testTotalCalculation = testTotalCalculation;
window.testNewRowAddition = testNewRowAddition;

console.log('金額計算テストスクリプトがロードされました:');
console.log('- testCalculationComplete(): 包括的なテストを実行');
console.log('- testInitialState(): 初期状態をチェック');
console.log('- testInvoiceCalculation(): 請求項目の計算をテスト');
console.log('- testCostCalculation(): コスト項目の計算をテスト');
console.log('- testFieldActivation(): フィールド有効化をテスト');
console.log('- testTotalCalculation(): 合計・利益計算をテスト');
console.log('- testNewRowAddition(): 新規行追加をテスト');
