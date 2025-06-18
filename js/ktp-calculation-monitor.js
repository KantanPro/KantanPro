/**
 * 金額計算監視・診断ツール
 * リアルタイムで金額計算の問題を検出・修正
 */

(function($) {
    'use strict';

    let calculationMonitor = null;
    let performanceStats = {
        totalCalculations: 0,
        failedCalculations: 0,
        lastCalculationTime: null,
        averageCalculationTime: 0
    };

    // 金額計算監視機能
    function startCalculationMonitor() {
        if (calculationMonitor) {
            console.log('[CALC MONITOR] 既に監視中です');
            return;
        }

        console.log('[CALC MONITOR] 金額計算監視開始');

        // MutationObserverで金額フィールドの変更を監視
        calculationMonitor = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                // 金額フィールドの値変更を検出
                if (mutation.type === 'attributes' && mutation.attributeName === 'value') {
                    const target = mutation.target;
                    if (target.classList.contains('amount')) {
                        validateAmountField($(target));
                    }
                }
            });
        });

        // 請求項目とコスト項目の金額フィールドを監視
        const amountFields = $('.invoice-items-table .amount, .cost-items-table .amount');
        amountFields.each(function() {
            calculationMonitor.observe(this, {
                attributes: true,
                attributeFilter: ['value']
            });
        });

        // input イベントも監視
        $(document).on('input.calcMonitor', '.amount', function() {
            validateAmountField($(this));
        });

        // 定期的な整合性チェック（5秒間隔）
        setInterval(performIntegrityCheck, 5000);
    }

    // 金額フィールドの検証
    function validateAmountField($field) {
        const value = $field.val();
        const numericValue = parseFloat(value);

        if (isNaN(numericValue) || value === '') {
            console.warn('[CALC MONITOR] 異常な金額値を検出:', value);
            fixAmountCalculation($field.closest('tr'));
        }
    }

    // 金額計算の修正
    function fixAmountCalculation($row) {
        const $price = $row.find('.price');
        const $quantity = $row.find('.quantity');
        const $amount = $row.find('.amount');

        const price = parseFloat($price.val()) || 0;
        const quantity = parseFloat($quantity.val()) || 0;
        const correctAmount = price * quantity;

        const currentAmount = parseFloat($amount.val()) || 0;

        if (Math.abs(correctAmount - currentAmount) > 0.01) {
            console.log('[CALC MONITOR] 金額計算を修正:', {
                price: price,
                quantity: quantity,
                currentAmount: currentAmount,
                correctAmount: correctAmount
            });

            $amount.val(correctAmount);
            
            // 合計表示も更新
            if (typeof updateTotalAndProfit === 'function') {
                updateTotalAndProfit();
            }
            if (typeof updateProfitDisplay === 'function') {
                updateProfitDisplay();
            }
        }
    }

    // 整合性チェック
    function performIntegrityCheck() {
        let issues = 0;

        $('.invoice-items-table tbody tr, .cost-items-table tbody tr').each(function() {
            const $row = $(this);
            const $price = $row.find('.price');
            const $quantity = $row.find('.quantity');
            const $amount = $row.find('.amount');

            if ($price.length && $quantity.length && $amount.length) {
                const price = parseFloat($price.val()) || 0;
                const quantity = parseFloat($quantity.val()) || 0;
                const expectedAmount = price * quantity;
                const currentAmount = parseFloat($amount.val()) || 0;

                if (Math.abs(expectedAmount - currentAmount) > 0.01) {
                    console.warn('[CALC MONITOR] 整合性エラー検出:', {
                        row: $row.index(),
                        price: price,
                        quantity: quantity,
                        expected: expectedAmount,
                        current: currentAmount
                    });
                    
                    fixAmountCalculation($row);
                    issues++;
                }
            }
        });

        if (issues > 0) {
            console.log(`[CALC MONITOR] ${issues}件の金額計算を修正しました`);
        }
    }

    // パフォーマンス測定
    function measureCalculationPerformance(calculationFunc, context) {
        const startTime = performance.now();
        
        try {
            calculationFunc.call(context);
            const endTime = performance.now();
            const duration = endTime - startTime;
            
            performanceStats.totalCalculations++;
            performanceStats.lastCalculationTime = duration;
            performanceStats.averageCalculationTime = 
                (performanceStats.averageCalculationTime * (performanceStats.totalCalculations - 1) + duration) / 
                performanceStats.totalCalculations;
                
            if (duration > 100) {
                console.warn('[CALC MONITOR] 計算時間が長すぎます:', duration + 'ms');
            }
        } catch (error) {
            performanceStats.failedCalculations++;
            console.error('[CALC MONITOR] 計算エラー:', error);
        }
    }

    // 統計情報表示
    function showCalculationStats() {
        console.log('[CALC MONITOR] パフォーマンス統計:', performanceStats);
        
        const totalRows = $('.invoice-items-table tbody tr, .cost-items-table tbody tr').length;
        const invalidAmounts = $('.amount').filter(function() {
            const value = $(this).val();
            return isNaN(parseFloat(value)) || value === '';
        }).length;

        console.log('[CALC MONITOR] 現在の状態:', {
            総行数: totalRows,
            無効な金額フィールド: invalidAmounts,
            計算成功率: ((performanceStats.totalCalculations - performanceStats.failedCalculations) / performanceStats.totalCalculations * 100).toFixed(2) + '%'
        });
    }

    // 監視停止
    function stopCalculationMonitor() {
        if (calculationMonitor) {
            calculationMonitor.disconnect();
            calculationMonitor = null;
            $(document).off('.calcMonitor');
            console.log('[CALC MONITOR] 監視停止');
        }
    }

    // 問題の自動修復
    function autoFixCalculationIssues() {
        console.log('[CALC MONITOR] 自動修復開始');
        
        let fixedCount = 0;

        // 1. 無効化されたフィールドの有効化
        $('.price, .quantity').each(function() {
            const $field = $(this);
            if ($field.prop('disabled')) {
                const $row = $field.closest('tr');
                const itemId = $row.find('input[name*="[id]"]').val();
                
                // 新規行（ID=0）以外は有効化
                if (itemId && itemId !== '0') {
                    $field.prop('disabled', false);
                    console.log('[CALC MONITOR] フィールドを有効化:', $field.attr('class'));
                    fixedCount++;
                }
            }
        });

        // 2. 空の金額フィールドの計算
        $('.amount').each(function() {
            const $amount = $(this);
            const value = $amount.val();
            
            if (value === '' || isNaN(parseFloat(value))) {
                const $row = $amount.closest('tr');
                fixAmountCalculation($row);
                fixedCount++;
            }
        });

        // 3. 合計表示の更新
        if (typeof updateTotalAndProfit === 'function') {
            updateTotalAndProfit();
        }
        if (typeof updateProfitDisplay === 'function') {
            updateProfitDisplay();
        }

        console.log(`[CALC MONITOR] 自動修復完了: ${fixedCount}件修正`);
        return fixedCount;
    }

    // グローバル関数として公開
    window.startCalculationMonitor = startCalculationMonitor;
    window.stopCalculationMonitor = stopCalculationMonitor;
    window.showCalculationStats = showCalculationStats;
    window.autoFixCalculationIssues = autoFixCalculationIssues;
    window.performIntegrityCheck = performIntegrityCheck;

    // ページ読み込み時に自動開始
    $(document).ready(function() {
        if ($('.invoice-items-table, .cost-items-table').length > 0) {
            setTimeout(startCalculationMonitor, 1000);
            console.log('[CALC MONITOR] 自動監視機能が有効になりました');
            console.log('使用可能なコマンド:');
            console.log('- startCalculationMonitor(): 監視開始');
            console.log('- stopCalculationMonitor(): 監視停止');
            console.log('- showCalculationStats(): 統計表示');
            console.log('- autoFixCalculationIssues(): 自動修復');
            console.log('- performIntegrityCheck(): 整合性チェック');
        }
    });

})(jQuery);
