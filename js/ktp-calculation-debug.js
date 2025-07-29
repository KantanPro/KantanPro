/**
 * 金額計算デバッグ用ヘルパー関数
 * ブラウザコンソールで実行して金額計算の状態を確認
 */

// 請求項目の金額計算をテスト
function debugInvoiceCalculation() {
    console.log('=== 請求項目金額計算デバッグ ===');
    
    $('.invoice-items-table tbody tr').each(function(index) {
        const $row = $(this);
        const $price = $row.find('.price');
        const $quantity = $row.find('.quantity');
        const $amount = $row.find('.amount');
        const $productName = $row.find('.product-name');
        const itemId = $row.find('input[name*="[id]"]').val();
        
        const price = parseFloat($price.val()) || 0;
        const quantity = parseFloat($quantity.val()) || 0;
        const calculatedAmount = price * quantity;
        const currentAmount = parseFloat($amount.val()) || 0;
        
        console.log(`Row ${index}:`, {
            productName: $productName.val(),
            price: price,
            quantity: quantity,
            calculatedAmount: calculatedAmount,
            currentAmount: currentAmount,
            itemId: itemId,
            priceDisabled: $price.prop('disabled'),
            quantityDisabled: $quantity.prop('disabled'),
            amountDisabled: $amount.prop('disabled'),
            priceReadonly: $price.prop('readonly'),
            quantityReadonly: $quantity.prop('readonly'),
            amountReadonly: $amount.prop('readonly'),
            calculationMatch: calculatedAmount === currentAmount
        });
    });
}

// コスト項目の金額計算をテスト
function debugCostCalculation() {
    console.log('=== コスト項目金額計算デバッグ ===');
    
    $('.cost-items-table tbody tr').each(function(index) {
        const $row = $(this);
        const $price = $row.find('.price');
        const $quantity = $row.find('.quantity');
        const $amount = $row.find('.amount');
        const $productName = $row.find('.product-name');
        const itemId = $row.find('input[name*="[id]"]').val();
        
        const price = parseFloat($price.val()) || 0;
        const quantity = parseFloat($quantity.val()) || 0;
        const calculatedAmount = price * quantity;
        const currentAmount = parseFloat($amount.val()) || 0;
        
        console.log(`Row ${index}:`, {
            productName: $productName.val(),
            price: price,
            quantity: quantity,
            calculatedAmount: calculatedAmount,
            currentAmount: currentAmount,
            itemId: itemId,
            priceDisabled: $price.prop('disabled'),
            quantityDisabled: $quantity.prop('disabled'),
            amountDisabled: $amount.prop('disabled'),
            priceReadonly: $price.prop('readonly'),
            quantityReadonly: $quantity.prop('readonly'),
            amountReadonly: $amount.prop('readonly'),
            calculationMatch: calculatedAmount === currentAmount
        });
    });
}

// 全ての金額計算を強制実行
function forceRecalculateAll() {
    console.log('=== 全金額計算強制実行 ===');
    
    console.log('請求項目の再計算:');
    $('.invoice-items-table tbody tr').each(function() {
        const $row = $(this);
        if (typeof calculateAmount === 'function') {
            calculateAmount($row);
        }
    });
    
    console.log('コスト項目の再計算:');
    $('.cost-items-table tbody tr').each(function() {
        const $row = $(this);
        if (typeof calculateAmount === 'function') {
            calculateAmount($row);
        }
    });
}

// ブラウザコンソールで使用できるようにwindowオブジェクトに追加
window.debugInvoiceCalculation = debugInvoiceCalculation;
window.debugCostCalculation = debugCostCalculation;
window.forceRecalculateAll = forceRecalculateAll;

console.log('金額計算デバッグ機能がロードされました:');
console.log('- debugInvoiceCalculation(): 請求項目の金額計算状態を確認');
console.log('- debugCostCalculation(): コスト項目の金額計算状態を確認');  
console.log('- forceRecalculateAll(): 全ての金額計算を強制実行');
