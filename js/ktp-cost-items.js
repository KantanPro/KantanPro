/**
 * コスト項目テーブルのJavaScript機能
 *
 * @package KTPWP
 * @since 1.0.0
 */

(function ($) {
    'use strict';

    // デバッグモードを有効化（本番では false に設定）
    window.ktpDebugMode = false; // 本番環境では false に設定

    // 利用可能な変数を確認（デバッグモード時のみ）
    if (window.ktpDebugMode) {
        console.log('[COST] Available variables check:');
        console.log('  - ajaxurl:', typeof ajaxurl !== 'undefined' ? ajaxurl : 'undefined');
        console.log('  - ktp_ajax:', typeof ktp_ajax !== 'undefined' ? ktp_ajax : 'undefined');
        console.log('  - ktpwp_ajax:', typeof ktpwp_ajax !== 'undefined' ? ktpwp_ajax : 'undefined');
        console.log('  - ktp_ajax_nonce:', typeof ktp_ajax_nonce !== 'undefined' ? ktp_ajax_nonce : 'undefined');
        console.log('  - ktp_ajax_object:', typeof ktp_ajax_object !== 'undefined' ? ktp_ajax_object : 'undefined');
    }

    // 重複追加防止フラグ (コスト項目専用)
    window.ktpAddingCostRow = false;
    
    // 初期化完了フラグ
    window.ktpCostItemsInitialized = window.ktpCostItemsInitialized || false;

    // 小数点以下の不要な0を削除する関数
    function formatDecimalDisplay(value) {
        if (value === '' || value === null || value === undefined) {
            return '';
        }
        const num = parseFloat(value);
        if (isNaN(num)) {
            return value;
        }
        // 小数点以下6桁まで表示し、末尾の0とピリオドを削除
        return num.toFixed(6).replace(/\.?0+$/, '');
    }

    // 単価×数量の自動計算
    function calculateAmount(row) {
        const priceValue = row.find('.price').val();
        const quantityValue = row.find('.quantity').val();
        
        // より厳密な数値変換
        const price = (priceValue === '' || priceValue === null || isNaN(priceValue)) ? 0 : parseFloat(priceValue);
        const quantity = (quantityValue === '' || quantityValue === null || isNaN(quantityValue)) ? 0 : parseFloat(quantityValue);
        const amount = Math.ceil(price * quantity);
        
        // NaNチェック
        const finalAmount = isNaN(amount) ? 0 : amount;
        
        // デバッグログ
        if (window.ktpDebugMode) {
            console.log('[COST] calculateAmount called:', {
                priceValue: priceValue,
                quantityValue: quantityValue,
                price: price,
                quantity: quantity,
                amount: amount,
                finalAmount: finalAmount,
                rowIndex: row.index(),
                priceElement: row.find('.price').length,
                quantityElement: row.find('.quantity').length,
                amountElement: row.find('.amount').length
            });
        }
        
        row.find('.amount').val(finalAmount);

        // 金額を自動保存
        const itemId = row.find('input[name*="[id]"]').val();
        const orderId = $('input[name="order_id"]').val() || $('#order_id').val();

        if (itemId && orderId && itemId !== '0') {
            // 既存行の場合：金額を自動保存
            if (window.ktpDebugMode) {
                console.log('[COST] calculateAmount: 金額自動保存実行', {itemId, amount: finalAmount});
            }
            autoSaveItem('cost', itemId, 'amount', finalAmount, orderId);
        } else {
            if (window.ktpDebugMode) {
                console.log('[COST] calculateAmount: 保存条件未満', {itemId, orderId});
            }
        }

        // 利益計算を更新
        updateProfitDisplay();
    }

    // calculateAmount関数をグローバルに露出
    window.calculateAmount = calculateAmount;

    // 協力会社の税区分を取得する関数
    function getSupplierTaxCategory(supplierId, callback) {
        if (!supplierId || supplierId <= 0) {
            if (window.ktpDebugMode) {
                console.log('[COST] Invalid supplier ID for tax category:', supplierId);
            }
            callback('内税'); // デフォルト
            return;
        }

        // 統一されたnonce取得方法
        let nonce = '';
        if (typeof ktp_ajax_nonce !== 'undefined') {
            nonce = ktp_ajax_nonce;
        } else if (typeof ktp_ajax_object !== 'undefined' && ktp_ajax_object.nonce) {
            nonce = ktp_ajax_object.nonce;
        } else if (typeof ktpwp_ajax !== 'undefined' && ktpwp_ajax.nonces && ktpwp_ajax.nonces.auto_save) {
            nonce = ktpwp_ajax.nonces.auto_save;
        } else if (typeof ktpwp_ajax !== 'undefined' && ktpwp_ajax.nonces && ktpwp_ajax.nonces.general) {
            nonce = ktpwp_ajax.nonces.general;
        } else if (typeof window.ktpwp_ajax !== 'undefined' && window.ktpwp_ajax.nonces && window.ktpwp_ajax.nonces.auto_save) {
            nonce = window.ktpwp_ajax.nonces.auto_save;
        } else if (typeof window.ktpwp_ajax !== 'undefined' && window.ktpwp_ajax.nonces && window.ktpwp_ajax.nonces.general) {
            nonce = window.ktpwp_ajax.nonces.general;
        }

        if (window.ktpDebugMode) {
            console.log('[COST] getSupplierTaxCategory - nonce:', nonce, 'supplierId:', supplierId);
        }

        // 統一されたajax_url取得
        let ajaxUrl = '';
        if (typeof ajaxurl !== 'undefined') {
            ajaxUrl = ajaxurl;
        } else if (typeof ktp_ajax_object !== 'undefined' && ktp_ajax_object.ajax_url) {
            ajaxUrl = ktp_ajax_object.ajax_url;
        } else if (typeof ktpwp_ajax !== 'undefined' && ktpwp_ajax.ajax_url) {
            ajaxUrl = ktpwp_ajax.ajax_url;
        } else if (typeof window.ktpwp_ajax !== 'undefined' && window.ktpwp_ajax.ajax_url) {
            ajaxUrl = window.ktpwp_ajax.ajax_url;
        } else {
            ajaxUrl = '/wp-admin/admin-ajax.php';
        }

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'ktp_get_supplier_tax_category',
                supplier_id: supplierId,
                nonce: nonce
            },
            success: function(response) {
                if (window.ktpDebugMode) {
                    console.log('[COST] getSupplierTaxCategory response:', response);
                }
                try {
                    const result = typeof response === 'string' ? JSON.parse(response) : response;
                    if (result.success && result.data && result.data.tax_category) {
                        callback(result.data.tax_category);
                    } else {
                        if (window.ktpDebugMode) {
                            console.warn('[COST] getSupplierTaxCategory failed:', result);
                        }
                        callback('内税'); // デフォルト
                    }
                } catch (e) {
                    if (window.ktpDebugMode) {
                        console.error('[COST] getSupplierTaxCategory parse error:', e, response);
                    }
                    callback('内税'); // デフォルト
                }
            },
            error: function(xhr, status, error) {
                if (window.ktpDebugMode) {
                    console.warn('[COST] getSupplierTaxCategory error (using default):', {xhr, status, error});
                }
                callback('内税'); // デフォルト
            }
        });
    }

    // 協力会社の適格請求書番号を取得する関数
    function getSupplierQualifiedInvoiceNumber(supplierId, callback) {
        if (!supplierId || supplierId <= 0) {
            if (window.ktpDebugMode) {
                console.log('[COST] Invalid supplier ID for qualified invoice number:', supplierId);
            }
            callback(''); // デフォルト（適格請求書なし）
            return;
        }

        // 統一されたnonce取得方法
        let nonce = '';
        if (typeof ktp_ajax_nonce !== 'undefined') {
            nonce = ktp_ajax_nonce;
        } else if (typeof ktp_ajax_object !== 'undefined' && ktp_ajax_object.nonce) {
            nonce = ktp_ajax_object.nonce;
        } else if (typeof ktpwp_ajax !== 'undefined' && ktpwp_ajax.nonces && ktpwp_ajax.nonces.auto_save) {
            nonce = ktpwp_ajax.nonces.auto_save;
        } else if (typeof ktpwp_ajax !== 'undefined' && ktpwp_ajax.nonces && ktpwp_ajax.nonces.general) {
            nonce = ktpwp_ajax.nonces.general;
        } else if (typeof window.ktpwp_ajax !== 'undefined' && window.ktpwp_ajax.nonces && window.ktpwp_ajax.nonces.auto_save) {
            nonce = window.ktpwp_ajax.nonces.auto_save;
        } else if (typeof window.ktpwp_ajax !== 'undefined' && window.ktpwp_ajax.nonces && window.ktpwp_ajax.nonces.general) {
            nonce = window.ktpwp_ajax.nonces.general;
        }

        if (window.ktpDebugMode) {
            console.log('[COST] getSupplierQualifiedInvoiceNumber - nonce:', nonce, 'supplierId:', supplierId);
        }

        // 統一されたajax_url取得
        let ajaxUrl = '';
        if (typeof ajaxurl !== 'undefined') {
            ajaxUrl = ajaxurl;
        } else if (typeof ktp_ajax_object !== 'undefined' && ktp_ajax_object.ajax_url) {
            ajaxUrl = ktp_ajax_object.ajax_url;
        } else if (typeof ktpwp_ajax !== 'undefined' && ktpwp_ajax.ajax_url) {
            ajaxUrl = ktpwp_ajax.ajax_url;
        } else if (typeof window.ktpwp_ajax !== 'undefined' && window.ktpwp_ajax.ajax_url) {
            ajaxUrl = window.ktpwp_ajax.ajax_url;
        } else {
            ajaxUrl = '/wp-admin/admin-ajax.php';
        }

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'ktp_get_supplier_qualified_invoice_number',
                supplier_id: supplierId,
                nonce: nonce
            },
            success: function(response) {
                if (window.ktpDebugMode) {
                    console.log('[COST] getSupplierQualifiedInvoiceNumber response:', response);
                }
                try {
                    const result = typeof response === 'string' ? JSON.parse(response) : response;
                    if (result.success && result.data && result.data.qualified_invoice_number !== undefined) {
                        callback(result.data.qualified_invoice_number);
                    } else {
                        if (window.ktpDebugMode) {
                            console.warn('[COST] getSupplierQualifiedInvoiceNumber failed (using default):', result);
                        }
                        callback(''); // デフォルト（適格請求書なし）
                    }
                } catch (e) {
                    if (window.ktpDebugMode) {
                        console.error('[COST] getSupplierQualifiedInvoiceNumber parse error (using default):', e, response);
                    }
                    callback(''); // デフォルト（適格請求書なし）
                }
            },
            error: function(xhr, status, error) {
                if (window.ktpDebugMode) {
                    console.warn('[COST] getSupplierQualifiedInvoiceNumber error (using default):', {xhr, status, error});
                }
                callback(''); // デフォルト（適格請求書なし）
            }
        });
    }

    // 利益表示を更新（修正版）
    function updateProfitDisplay() {
        let invoiceTotal = 0;
        let costTotal = 0;
        let costTotalTaxAmount = 0;
        let hasOuttax = false;
        let processedRows = 0;
        let totalRows = $('.cost-items-table tbody tr').length;
        let costItems = [];

        // 税率別の集計用オブジェクト
        let costTaxRateGroups = {};

        // 請求項目の合計を計算
        $('.invoice-items-table .amount').each(function () {
            invoiceTotal += parseFloat($(this).val()) || 0;
        });

        // コスト項目のデータを収集（税率別に集計）
        $('.cost-items-table tbody tr').each(function () {
            const $row = $(this);
            const amount = parseFloat($row.find('.amount').val()) || 0;
            const taxRateInput = $row.find('.tax-rate').val();
            
            // 税率の処理（NULL、空文字、NaNの場合は税率なしとして扱う）
            let taxRate = null;
            if (taxRateInput !== null && taxRateInput !== '' && !isNaN(parseFloat(taxRateInput))) {
                taxRate = parseFloat(taxRateInput);
            }
            
            // 協力会社IDの取得を改善
            let supplierId = 0;
            
            // 1. 隠しフィールドから取得（最優先）
            const hiddenSupplierId = $row.find('input[name*="[supplier_id]"]').val();
            if (hiddenSupplierId && !isNaN(parseInt(hiddenSupplierId, 10))) {
                supplierId = parseInt(hiddenSupplierId, 10);
            }
            
            // 2. data属性から取得
            if (!supplierId) {
                const dataSupplierId = $row.attr('data-supplier-id');
                if (dataSupplierId && !isNaN(parseInt(dataSupplierId, 10))) {
                    supplierId = parseInt(dataSupplierId, 10);
                }
            }
            
            // 3. クラス付き要素から取得
            if (!supplierId) {
                const classSupplierId = $row.find('.supplier-id').val();
                if (classSupplierId && !isNaN(parseInt(classSupplierId, 10))) {
                    supplierId = parseInt(classSupplierId, 10);
                }
            }
            
            // 4. select要素から取得
            if (!supplierId) {
                const selectSupplierId = $row.find('select[name*="[supplier_id]"]').val();
                if (selectSupplierId && !isNaN(parseInt(selectSupplierId, 10))) {
                    supplierId = parseInt(selectSupplierId, 10);
                }
            }
            
            // 税率別に集計（税率なしの場合は'no_tax_rate'として扱う）
            const taxRateKey = taxRate !== null ? taxRate.toString() : 'no_tax_rate';
            if (!costTaxRateGroups[taxRateKey]) {
                costTaxRateGroups[taxRateKey] = 0;
            }
            costTaxRateGroups[taxRateKey] += amount;
            
            // デバッグ情報（デバッグモード時のみ）
            if (window.ktpDebugMode) {
                console.log('[COST] Row data collected:', {
                    supplierId: supplierId,
                    amount: amount,
                    taxRate: taxRate,
                    rowIndex: $row.index()
                });
            }
            
            costTotal += amount;
            costItems.push({
                supplierId: supplierId,
                amount: amount,
                taxRate: taxRate
            });
            
            // 各行ごとに協力会社の税区分を取得して計算
            getSupplierTaxCategory(supplierId, function(taxCategory) {
                // 税率が設定されている場合のみ税額を計算
                if (taxRate !== null) {
                    if (taxCategory === '外税') {
                        hasOuttax = true;
                        // 外税計算：税抜金額から税額を計算（切り上げ）
                        costTotalTaxAmount += Math.ceil(amount * (taxRate / 100));
                    } else {
                        // 内税計算：各税率グループごとに税額を計算（切り上げ）
                        costTotalTaxAmount += Math.ceil(amount * (taxRate / 100) / (1 + taxRate / 100));
                    }
                }
                
                processedRows++;
                
                // 全ての行の処理が完了したら表示を更新
                if (processedRows === totalRows) {
                    updateCostDisplay(invoiceTotal, costTotal, costTotalTaxAmount, hasOuttax);
                    
                    // 顧客の税区分を取得して利益計算
                    getClientTaxCategory(function(clientTaxCategory) {
                        // 適格請求書ナンバーを考慮した利益計算
                        const invoiceTotalCeiled = Math.ceil(invoiceTotal);
                        calculateProfitWithQualifiedInvoice(invoiceTotalCeiled, costItems, clientTaxCategory, function(profit, qualifiedCost, nonQualifiedCost, totalCost) {
                            updateProfitDisplayWithQualifiedInvoice(profit, qualifiedCost, nonQualifiedCost, totalCost);
                        });
                    });
                }
            });
        });

        // 行がない場合は即座に表示を更新
        if (totalRows === 0) {
            updateCostDisplay(invoiceTotal, costTotal, costTotalTaxAmount, hasOuttax);
            getClientTaxCategory(function(clientTaxCategory) {
                updateProfitDisplayWithQualifiedInvoice(invoiceTotal, 0, 0, 0);
            });
        }

        // デバッグログ（税率別の集計情報）
        if (window.ktpDebugMode) {
            console.log('[COST] 税率別集計:', {
                costTaxRateGroups: costTaxRateGroups,
                costTotalTaxAmount: costTotalTaxAmount,
                hasOuttax: hasOuttax
            });
        }
    }

    // 適格請求書ナンバーを考慮した利益表示を更新
    function updateProfitDisplayWithQualifiedInvoice(profit, qualifiedCost, nonQualifiedCost, totalCost) {
        const profitDisplay = $('.profit-display');
        if (profitDisplay.length > 0) {
            const profitColor = profit >= 0 ? '#28a745' : '#dc3545';
            // 利益を整数で切り捨て
            const profitInteger = Math.floor(profit);
            let profitText = '利益 : ' + profitInteger.toLocaleString() + '円';
            
            // デバッグモードの場合は詳細情報も表示
            if (window.ktpDebugMode) {
                console.log('[PROFIT] Display values:', {
                    profit: profit,
                    qualifiedCost: qualifiedCost,
                    nonQualifiedCost: nonQualifiedCost,
                    totalCost: totalCost
                });
                profitText += ' (適格請求書コスト: ' + Math.ceil(qualifiedCost).toLocaleString() + '円, 非適格請求書コスト: ' + Math.ceil(nonQualifiedCost).toLocaleString() + '円)';
            } else {
                // 通常モードでも詳細情報を表示（一時的な修正）
                profitText += ' (適格請求書コスト: ' + Math.ceil(qualifiedCost).toLocaleString() + '円, 非適格請求書コスト: ' + Math.ceil(nonQualifiedCost).toLocaleString() + '円)';
            }
            
            profitDisplay.html(profitText);
            profitDisplay.css('color', profitColor);
            profitDisplay.removeClass('positive negative');
            profitDisplay.addClass(profit >= 0 ? 'positive' : 'negative');
        }
    }

    // updateProfitDisplay関数をグローバルに露出
    window.updateProfitDisplay = updateProfitDisplay;

    // 税区分変更時の表示更新関数
    function updateTaxCategoryDisplay(newTaxCategory) {
        if (window.ktpDebugMode) {
            console.log('[COST] 税区分変更検知:', {
                newTaxCategory: newTaxCategory,
                currentTaxCategory: window.ktpClientTaxCategory
            });
        }

        // グローバル変数を更新
        window.ktpClientTaxCategory = newTaxCategory;

        // コスト項目の表示を更新
        updateProfitDisplay();

        // 請求項目の表示も更新（もし存在する場合）
        if (typeof window.updateTotalAndProfit === 'function') {
            window.updateTotalAndProfit();
        }
    }

    // 税区分変更のイベントハンドラーを設定
    $(document).ready(function() {
        // 顧客選択時の税区分更新
        $(document).on('change', 'select[name="customer_id"], select[name="client_id"]', function() {
            const customerId = $(this).val();
            if (customerId) {
                // 統一されたnonce取得方法
                let nonce = '';
                if (typeof ktp_ajax_nonce !== 'undefined') {
                    nonce = ktp_ajax_nonce;
                } else if (typeof ktp_ajax_object !== 'undefined' && ktp_ajax_object.nonce) {
                    nonce = ktp_ajax_object.nonce;
                } else if (typeof ktpwp_ajax !== 'undefined' && ktpwp_ajax.nonces && ktpwp_ajax.nonces.auto_save) {
                    nonce = ktpwp_ajax.nonces.auto_save;
                } else if (typeof window.ktpwp_ajax !== 'undefined' && window.ktpwp_ajax.nonces && window.ktpwp_ajax.nonces.auto_save) {
                    nonce = window.ktpwp_ajax.nonces.auto_save;
                }

                // AJAXで顧客の税区分を取得
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'ktp_get_client_tax_category',
                        client_id: customerId,
                        nonce: nonce
                    },
                    success: function(response) {
                        if (response.success && response.data && response.data.tax_category) {
                            updateTaxCategoryDisplay(response.data.tax_category);
                        }
                    },
                    error: function(xhr, status, error) {
                        if (window.ktpDebugMode) {
                            console.error('[COST] 顧客税区分取得エラー:', error);
                        }
                    }
                });
            }
        });

        // 税区分フィールドの直接変更時の更新
        $(document).on('change', 'select[name="tax_category"]', function() {
            const newTaxCategory = $(this).val();
            if (newTaxCategory) {
                updateTaxCategoryDisplay(newTaxCategory);
            }
        });
    });

    // 新しい行を追加（重複防止機能付き）
    function addNewRow(currentRow, callId) { // callId を受け取る
        if (window.ktpDebugMode) {
            console.log(`[COST][${callId}] addNewRow開始 (呼び出し元ID: ${callId})`);
        }

        // 品名チェック (addNewRow関数側でも念のため)
        let rawProductName = currentRow.find('input.product-name').val();
        if (typeof rawProductName !== 'string') {
            rawProductName = currentRow.find('input[name$="[product_name]"]').val();
        }
        // const productName = (typeof rawProductName === 'string') ? rawProductName.trim() : '';
        // 修正: addNewRow内の品名チェックは、呼び出し元で既に行われているため、ここではログ出力のみに留めるか、
        // もし再度チェックするなら、その結果に基づいて早期リターンする。
        // 今回は呼び出し元を信頼し、ここではチェックを簡略化または削除の方向で検討したが、
        // 念のため残し、警告ログを出す。
        const productNameValue = (typeof rawProductName === 'string') ? rawProductName.trim() : '';
        if (productNameValue === '') {
            // alert('品名を入力してください。(addNewRow)'); // クリックハンドラでアラートを出すので、ここでは不要
            if (window.ktpDebugMode) {
                console.warn(`[COST][${callId}] addNewRow: 品名が空の状態で呼び出されましたが、処理を続行します（本来はクリックハンドラでブロックされるべきです）。`);
            }
            // return false; // ここで return false すると、クリックハンドラの品名チェックが機能していない場合に二重チェックになる
                          // ただし、現状問題が解決していないため、ここでも止めることを検討したが、まずはログで状況把握
        }
        // End of added check

        if (window.ktpDebugMode) {
            console.log(`[COST][${callId}] addNewRow 本処理開始`);
        }
        // フラグ管理はクリックハンドラに集約

        const newIndex = $('.cost-items-table tbody tr').length;
        const newRowHtml = `
            <tr class="cost-item-row" data-row-id="0" data-newly-added="true" data-supplier-id="0">
                <td class="actions-column">
                    <span class="drag-handle" title="ドラッグして並び替え">&#9776;</span><button type="button" class="btn-add-row" title="行を追加">+</button><button type="button" class="btn-delete-row" title="行を削除">×</button><button type="button" class="btn-move-row" title="行を移動">></button>
                </td>
                <td>
                    <input type="text" name="cost_items[${newIndex}][product_name]" class="cost-item-input product-name" value="">
                    <input type="hidden" name="cost_items[${newIndex}][id]" value="0">
                    <input type="hidden" name="cost_items[${newIndex}][supplier_id]" value="0" class="supplier-id">
                </td>
                <td style="text-align:left;">
                    <input type="number" name="cost_items[${newIndex}][price]" class="cost-item-input price" value="0" step="1" min="0" style="text-align:left;">
                </td>
                <td style="text-align:left;">
                    <input type="number" name="cost_items[${newIndex}][quantity]" class="cost-item-input quantity" value="1" step="1" min="0" style="text-align:left;">
                </td>
                <td>
                    <input type="text" name="cost_items[${newIndex}][unit]" class="cost-item-input unit" value="式">
                </td>
                <td style="text-align:left;">
                    <input type="number" name="cost_items[${newIndex}][amount]" class="cost-item-input amount" value="" step="0.01" readonly style="text-align:left;">
                </td>
                <td style="text-align:left;">
                    <div style="display:inline-flex;align-items:center;margin-left:0;padding-left:0;">
                        <input type="number" name="cost_items[${newIndex}][tax_rate]" class="cost-item-input tax-rate" value="10" step="1" min="0" max="100" style="width:50px; text-align:right; display:inline-block; margin-left:0; padding-left:0;">
                        <span style="margin-left:2px; white-space:nowrap;">%</span>
                    </div>
                </td>
                <td>
                    <input type="text" name="cost_items[${newIndex}][remarks]" class="cost-item-input remarks" value="">
                    <input type="hidden" name="cost_items[${newIndex}][sort_order]" value="${newIndex + 1}">
                </td>
                <td>
                    <span class="purchase-display">手入力</span>
                    <input type="hidden" name="cost_items[${newIndex}][purchase]" value="">
                </td>
            </tr>
        `;

        let success = false;
        try {
            if (window.ktpDebugMode) {
                console.log(`[COST][${callId}] currentRow.after(newRowHtml) を実行する直前。currentRow:`, currentRow[0].outerHTML);
            }
            currentRow.after(newRowHtml);
            const $newRow = currentRow.next();
            if ($newRow && $newRow.length > 0 && $newRow.hasClass('cost-item-row')) {
                if (window.ktpDebugMode) {
                    console.log(`[COST][${callId}] 新しい行がDOMに追加されました。`);
                }
                
                // 新しい行で金額の自動計算を実行
                calculateAmount($newRow);
                
                $newRow.find('.product-name').focus();
                success = true;
            } else {
                if (window.ktpDebugMode) {
                    console.error(`[COST][${callId}] 新しい行の追加に失敗したか、見つかりませんでした。$newRow:`, $newRow);
                }
                success = false;
            }

        } catch (error) {
            if (window.ktpDebugMode) {
                console.error(`[COST][${callId}] addNewRow エラー:`, error);
            }
            success = false;
        } finally {
            // フラグ解除はクリックハンドラで行う
            if (window.ktpDebugMode) {
                console.log(`[COST][${callId}] addNewRow終了`);
            }
        }
        return success;
    }

    // 行を削除
    function deleteRow(currentRow) {
        const table = currentRow.closest('table');
        const tbody = table.find('tbody');

        // 最後の1行は削除しない
        if (tbody.find('tr').length <= 1) {
            alert('最低1行は必要です。');
            return;
        }

        if (confirm('この行を削除しますか？')) {
            const itemId = currentRow.find('input[name*="[id]"]').val();
            const orderId = $('input[name="order_id"]').val() || $('#order_id').val();
            if (window.ktpDebugMode) {
                console.log('[COST] deleteRow呼び出し', { itemId, orderId, row: currentRow });
            }

            if (itemId && itemId !== '0' && orderId) {
                let ajaxUrl = ajaxurl;
                if (!ajaxUrl && typeof ktp_ajax_object !== 'undefined') {
                    ajaxUrl = ktp_ajax_object.ajax_url;
                } else if (!ajaxUrl) {
                    ajaxUrl = '/wp-admin/admin-ajax.php'; // Fallback
                }
                // nonce の取得を修正（統一された方法）
                let nonce = '';
                if (typeof ktp_ajax_nonce !== 'undefined') {
                    nonce = ktp_ajax_nonce;
                } else if (typeof ktp_ajax_object !== 'undefined' && ktp_ajax_object.nonce) {
                    nonce = ktp_ajax_object.nonce;
                } else if (typeof ktpwp_ajax !== 'undefined' && ktpwp_ajax.nonces && ktpwp_ajax.nonces.auto_save) {
                    nonce = ktpwp_ajax.nonces.auto_save;
                } else if (typeof window.ktpwp_ajax !== 'undefined' && window.ktpwp_ajax.nonces && window.ktpwp_ajax.nonces.auto_save) {
                    nonce = window.ktpwp_ajax.nonces.auto_save;
                } else {
                    if (window.ktpDebugMode) {
                        console.warn('[COST] deleteRow: nonceが取得できませんでした');
                    }
                }

                const ajaxData = {
                    action: 'ktp_delete_item',
                    item_type: 'cost',
                    item_id: itemId,
                    order_id: orderId,
                    nonce: nonce,
                    ktp_ajax_nonce: nonce  // 追加: PHPでチェックされるフィールド名
                };
                if (window.ktpDebugMode) {
                    console.log('[COST] deleteRow送信', ajaxData);
                }
                $.ajax({
                    url: ajaxUrl,
                    type: 'POST',
                    data: ajaxData,
                    success: function (response) {
                        if (window.ktpDebugMode) {
                            console.log('[COST] deleteRowレスポンス', response);
                        }
                        try {
                            const result = typeof response === 'string' ? JSON.parse(response) : response;
                            if (result.success) {
                                if (window.ktpDebugMode) {
                                    console.log('[COST] deleteRowサーバー側削除成功');
                                }
                                currentRow.remove();
                                updateProfitDisplay(); // 合計金額と利益を更新
                            } else {
                                if (window.ktpDebugMode) {
                                    console.warn('[COST] deleteRowサーバー側削除失敗', result);
                                }
                                let errorMessage = '行の削除に失敗しました。';
                                if (result.data) {
                                    if (typeof result.data === 'string') {
                                        errorMessage += '\nエラー: ' + result.data;
                                    } else if (result.data.message) {
                                        errorMessage += '\nエラー: ' + result.data.message;
                                    }
                                } else if (result.message) {
                                    errorMessage += '\nエラー: ' + result.message;
                                }
                                alert(errorMessage);
                            }
                        } catch (e) {
                            if (window.ktpDebugMode) {
                                console.error('[COST] deleteRowレスポンスパースエラー', e, response);
                            }
                            alert('行削除の応答処理中にエラーが発生しました。\n詳細: ' + (typeof response === 'string' ? response : JSON.stringify(response)));
                        }
                    },
                    error: function (xhr, status, error) {
                        if (window.ktpDebugMode) {
                            console.error('[COST] deleteRowエラー', { status, error, responseText: xhr.responseText, statusCode: xhr.status });
                        }
                        let errorDetail = 'サーバーエラーが発生しました。';
                        if (xhr.responseText) {
                            try {
                                const errorResponse = JSON.parse(xhr.responseText);
                                if (errorResponse.data) {
                                    errorDetail += '\nエラー詳細: ' + errorResponse.data;
                                }
                            } catch (e) {
                                errorDetail += '\nレスポンス: ' + xhr.responseText.substring(0, 200);
                            }
                        }
                        errorDetail += '\nステータス: ' + xhr.status + ' ' + error;
                        alert('行の削除中にサーバーエラーが発生しました。\n' + errorDetail);
                    }
                });
            } else if (itemId === '0') {
                // サーバーに保存されていない行は、確認後すぐに削除
                if (window.ktpDebugMode) {
                    console.log('[COST] deleteRow: サーバー未保存行のため即時削除');
                }
                currentRow.remove();
                updateProfitDisplay(); // 合計金額と利益を更新
            } else {
                if (window.ktpDebugMode) {
                    console.warn('[COST] deleteRow: itemIdまたはorderIdが不足しているため、クライアント側でのみ削除');
                }
                currentRow.remove();
                updateProfitDisplay(); // 合計金額と利益を更新
            }
        }
    }

    // 行のインデックスを更新 (Sortable用)
    function updateRowIndexes(table) {
        if (window.ktpDebugMode) {
            console.log('[COST] updateRowIndexes開始');
        }
        const tbody = table.find('tbody');
        const rowCount = tbody.find('tr').length;
        if (window.ktpDebugMode) {
            console.log('[COST] 更新対象行数:', rowCount);
        }
        
        tbody.find('tr').each(function (index) {
            const row = $(this);
            let updatedCount = 0;
            
            row.find('input, textarea').each(function () {
                const input = $(this);
                const name = input.attr('name');
                if (name && name.match(/^cost_items\[\d+\]/)) {
                    // 先頭の [数字] 部分だけを置換
                    const oldName = name;
                    const newName = name.replace(/^cost_items\[\d+\]/, `cost_items[${index}]`);
                    input.attr('name', newName);
                    updatedCount++;
                    
                    // デバッグ: 重要なフィールドの更新をログ
                    if (window.ktpDebugMode && (name.includes('[id]') || name.includes('[sort_order]') || name.includes('[product_name]'))) {
                        console.log('[COST] フィールド名更新:', { 
                            oldName: oldName, 
                            newName: newName, 
                            value: input.val() 
                        });
                    }
                }
            });
            
            if (window.ktpDebugMode) {
                console.log('[COST] 行' + (index + 1) + 'の更新完了:', { 
                    rowIndex: index, 
                    updatedFields: updatedCount 
                });
            }
        });
        
        if (window.ktpDebugMode) {
            console.log('[COST] updateRowIndexes完了');
        }
    }

    // 自動追加機能を無効化（[+]ボタンのみで行追加）
    function checkAutoAddRow(currentRow) {
        // 自動追加機能を無効化
        // [+]ボタンクリック時のみ行を追加する仕様に変更
        return;
    }

    // 自動保存機能
    function autoSaveItem(itemType, itemId, fieldName, fieldValue, orderId) {
        // Ajax URLの確認と代替設定
        let ajaxUrl = ajaxurl;
        if (!ajaxUrl) {
            ajaxUrl = '/wp-admin/admin-ajax.php';
            if (window.ktpDebugMode) {
                console.warn('ajaxurl not defined, using fallback');
            }
        }

        // 統一されたnonce取得方法
        let nonce = '';
        if (typeof ktp_ajax_nonce !== 'undefined') {
            nonce = ktp_ajax_nonce;
        } else if (typeof ktp_ajax_object !== 'undefined' && ktp_ajax_object.nonce) {
            nonce = ktp_ajax_object.nonce;
        } else if (typeof ktpwp_ajax !== 'undefined' && ktpwp_ajax.nonces && ktpwp_ajax.nonces.auto_save) {
            nonce = ktpwp_ajax.nonces.auto_save;
        } else if (typeof window.ktpwp_ajax !== 'undefined' && window.ktpwp_ajax.nonces && window.ktpwp_ajax.nonces.auto_save) {
            nonce = window.ktpwp_ajax.nonces.auto_save;
        }

        const ajaxData = {
            action: 'ktp_auto_save_item',
            item_type: itemType, // 'cost' であることを期待
            item_id: itemId,
            field_name: fieldName,
            field_value: fieldValue,
            order_id: orderId,
            nonce: nonce,
            ktp_ajax_nonce: nonce  // 追加: PHPでチェックされるフィールド名
        };

        if (window.ktpDebugMode) {
            console.log('Cost items - Sending Ajax request:', ajaxData);
            console.log('Ajax URL:', ajaxUrl);
            console.log('Field being saved:', fieldName, 'Value:', fieldValue);
        }

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: ajaxData,
            success: function (response) {
                if (window.ktpDebugMode) {
                    console.log('Cost items - Ajax response received:', response);
                }
                try {
                    const result = typeof response === 'string' ? JSON.parse(response) : response;
                    if (result.success) {
                        if (window.ktpDebugMode) {
                            console.log('Cost auto-saved successfully');
                        }
                        
                        // 成功通知を表示（条件付き）
                        // 実際に値が変更された場合のみ通知を表示
                        if (typeof window.showSuccessNotification === 'function' && 
                            result.data && result.data.value_changed === true) {
                            window.showSuccessNotification('原価項目が保存されました');
                        }
                        
                        // 成功時の視覚的フィードバック（オプション）
                        // showSaveIndicator('saved');
                    } else {
                        if (window.ktpDebugMode) {
                            console.error('Cost auto-save failed:', result.message);
                        }
                        
                        // エラー通知を表示
                        if (typeof window.showErrorNotification === 'function') {
                            window.showErrorNotification('原価項目の保存に失敗しました: ' + (result.data || '不明なエラー'));
                        }
                    }
                } catch (e) {
                    if (window.ktpDebugMode) {
                        console.error('Cost auto-save response parse error:', e, 'Raw response:', response);
                    }
                    
                    // エラー通知を表示
                    if (typeof window.showErrorNotification === 'function') {
                        window.showErrorNotification('原価項目の保存中にエラーが発生しました');
                    }
                }
            },
            error: function (xhr, status, error) {
                if (window.ktpDebugMode) {
                    console.error('Cost auto-save Ajax error:', {
                        status: status,
                        error: error,
                        responseText: xhr.responseText,
                        statusCode: xhr.status
                    });
                }
            }
        });
    }

    // autoSaveItem関数をグローバルに露出
    window.autoSaveItem = autoSaveItem;

    // 新規レコード作成機能 (コールバック対応)
    function createNewItem(itemType, fieldName, fieldValue, orderId, $row, callback) {
        // Ajax URLの確認と代替設定
        let ajaxUrl = ajaxurl;
        if (!ajaxUrl) {
            ajaxUrl = '/wp-admin/admin-ajax.php';
            if (window.ktpDebugMode) {
                console.warn('ajaxurl not defined, using fallback');
            }
        }

        // 統一されたnonce取得方法
        let nonce = '';
        if (typeof ktp_ajax_nonce !== 'undefined') {
            nonce = ktp_ajax_nonce;
        } else if (typeof ktp_ajax_object !== 'undefined' && ktp_ajax_object.nonce) {
            nonce = ktp_ajax_object.nonce;
        } else if (typeof ktpwp_ajax !== 'undefined' && ktpwp_ajax.nonces && ktpwp_ajax.nonces.auto_save) {
            nonce = ktpwp_ajax.nonces.auto_save;
        } else if (typeof window.ktpwp_ajax !== 'undefined' && window.ktpwp_ajax.nonces && window.ktpwp_ajax.nonces.auto_save) {
            nonce = window.ktpwp_ajax.nonces.auto_save;
        }

        const ajaxData = {
            action: 'ktp_create_new_item',
            item_type: itemType, // 'cost' であることを期待
            field_name: fieldName,
            field_value: fieldValue,
            order_id: orderId,
            nonce: nonce,
            ktp_ajax_nonce: nonce  // 追加: PHPでチェックされるフィールド名
        };

        // 重複実行チェック用のユニークID
        const requestId = 'createNewItem_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        ajaxData.request_id = requestId;
        
        if (window.ktpDebugMode) {
            console.log('[COST] createNewItem AJAX送信', {
                requestId: requestId,
                ajaxData: ajaxData,
                timestamp: new Date().toISOString()
            });
        }

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: ajaxData,
            success: function (response) {
                if (window.ktpDebugMode) {
                    console.log('New cost item creation response:', response);
                }
                try {
                    const result = typeof response === 'string' ? JSON.parse(response) : response;
                    // wp_send_json_success はレスポンスを { success: true, data: { ... } } の形でラップする
                    if (result.success && result.data && result.data.item_id) {
                        const newItemId = result.data.item_id;
                        // 新しいIDをhidden inputに設定
                        $row.find('input[name*="[id]"]').val(newItemId);
                        $row.attr('data-row-id', newItemId); // data-row-idも更新

                        // data-newly-added属性を削除し、他のフィールドを有効化
                        if ($row.data('newly-added')) {
                            $row.removeAttr('data-newly-added');
                            $row.find('.cost-item-input').not('.product-name').not('.amount').prop('disabled', false);
                            if (window.ktpDebugMode) {
                                console.log('[COST] createNewItem: 他のフィールドを有効化', $row);
                            }

                            // フィールド有効化後に金額計算を実行
                            setTimeout(function() {
                                calculateAmount($row);
                                if (window.ktpDebugMode) {
                                    console.log('[COST] createNewItem: フィールド有効化後の金額計算実行');
                                }
                            }, 100);

                            // product_name からの最初の保存後、price フィールドにフォーカスを移す
                            const $priceField = $row.find('.cost-item-input.price');
                            if ($priceField.length > 0 && !$priceField.prop('disabled')) {
                                $priceField.focus();
                            }
                        }
                        if (window.ktpDebugMode) {
                            console.log('New cost item created with ID:', newItemId);
                        }
                        if (callback) callback(true, newItemId);
                    } else {
                        if (window.ktpDebugMode) {
                            console.error('New cost item creation failed:', result.message || (result.data ? result.data.message : 'Unknown error'));
                        }
                        if (callback) callback(false, null);
                    }
                } catch (e) {
                    if (window.ktpDebugMode) {
                        console.error('New cost item creation response parse error:', e, 'Raw response:', response);
                    }
                    if (callback) callback(false, null);
                }
            },
            error: function (xhr, status, error) {
                if (window.ktpDebugMode) {
                    console.error('New cost item creation Ajax error:', {
                        status: status,
                        error: error,
                        responseText: xhr.responseText,
                        statusCode: xhr.status
                    });
                }
                if (callback) callback(false, null);
            }
        });
    }

    // サービス項目の単価を正確に表示（末尾の不要な0とピリオドを削除）
    function displaySupplierServicePrice(row, serviceData) {
        if (serviceData && typeof serviceData.unit_price !== 'undefined') {
            // 末尾のピリオドのみの場合は削除
            let displayPrice = serviceData.unit_price;
            if (typeof displayPrice === 'string' && displayPrice.match(/^[0-9]+\.$/)) {
                displayPrice = displayPrice.slice(0, -1);
            }
            
            // 単価を表示
            row.find('.price').val(displayPrice);
            
            // 数量と単位も設定
            if (serviceData.quantity) {
                row.find('.quantity').val(serviceData.quantity);
            }
            if (serviceData.unit) {
                row.find('.unit').val(serviceData.unit);
            }
            // 金額を再計算
            calculateAmount(row);
        }
    }

    // サービス選択時の処理を更新
    $(document).on('click', '.supplier-service-item', function() {
        const serviceData = $(this).data('service');
        const targetRow = $('#' + $(this).closest('.popup-dialog').data('target-row'));
        
        if (serviceData) {
            targetRow.find('.product-name').val(serviceData.product_name);
            displaySupplierServicePrice(targetRow, serviceData);
            
            // 入力フィールドを有効化
            targetRow.find('input').prop('disabled', false);
            
            // ポップアップを閉じる
            $(this).closest('.popup-dialog').remove();
        }
    });

    // --- コスト項目用: 協力会社サービス選択ポップアップ内「追加」「更新」ボタン処理 ---
    // ポップアップ内の「更新」ボタン（協力会社選択と従来ポップアップの両方に対応）
    $(document).off('click', '.ktp-cost-update-btn').on('click', '.ktp-cost-update-btn', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        // 協力会社選択専用ボタンは除外（新しいハンドラーで処理される）
        if ($(this).hasClass('ktp-supplier-update-btn')) {
            console.log('[COST] 協力会社選択専用ボタンのため処理をスキップ');
            return;
        }
        
        // 重複実行防止フラグをチェック
        const $btn = $(this);
        if ($btn.data('processing')) {
            console.log('[COST] 更新処理中のため、重複実行をブロック');
            return;
        }
        
        // 処理中フラグを設定
        $btn.data('processing', true);
        $btn.prop('disabled', true);
        
        // ボタンのテキストを変更して処理中であることを明示
        const originalUpdateText = $btn.text();
        $btn.data('original-text', originalUpdateText);
        $btn.text('処理中...');
        
        console.log('[COST] 更新ボタンクリック');
        
        const $popup = $btn.closest('.popup-dialog, #ktp-supplier-selector-modal');
        
        // 協力会社選択ポップアップからのデータを取得（JSON形式のdata-service属性から）
        let serviceData = $btn.data('service');
        if (typeof serviceData === 'string') {
            try {
                serviceData = JSON.parse(serviceData);
            } catch (e) {
                console.error('[COST] サービスデータのJSONパースエラー:', e);
            }
        }
        
        // 従来のポップアップ形式への対応も維持
        if (!serviceData) {
            serviceData = $btn.closest('.supplier-service-item').data('service');
        }
        
        // 協力会社選択の場合は window.ktpCurrentRow を使用
        let $targetRow;
        const targetRowId = $popup.data('target-row');
        if (targetRowId) {
            $targetRow = $('#' + targetRowId);
        } else if (window.ktpCurrentRow) {
            $targetRow = window.ktpCurrentRow;
        }
        
        if (!serviceData || !$targetRow || $targetRow.length === 0) {
            console.error('[COST] 更新対象の行またはサービスデータが見つかりません', {
                serviceData: serviceData,
                targetRowId: targetRowId,
                hasCurrentRow: !!window.ktpCurrentRow,
                targetRowExists: $targetRow && $targetRow.length > 0
            });
            alert('更新対象の行またはサービスデータが見つかりません。');
            // エラー時にフラグを解除
            $btn.data('processing', false);
            $btn.prop('disabled', false);
            
            // ボタンテキストを元に戻す
            const originalUpdateText1 = $btn.data('original-text');
            if (originalUpdateText1) {
                $btn.text(originalUpdateText1);
            }
            return;
        }
        
        console.log('[COST] 更新処理開始', {
            serviceData: serviceData,
            targetRowId: targetRowId
        });
        
        // UI反映（協力会社選択と従来ポップアップの両方に対応）
        const productName = serviceData.product_name || serviceData.name || '';
        const unitPrice = serviceData.unit_price || serviceData.price || 0;
        const quantity = serviceData.quantity || 1;
        const unit = serviceData.unit || '';
        const taxRate = serviceData.tax_rate !== null && serviceData.tax_rate !== undefined && serviceData.tax_rate !== '' ? serviceData.tax_rate : '';
        
        $targetRow.find('.product-name').val(productName);
        $targetRow.find('.price').val(unitPrice);
        $targetRow.find('.quantity').val(quantity);
        $targetRow.find('.unit').val(unit);
        $targetRow.find('.tax-rate').val(taxRate);
        $targetRow.find('input').prop('disabled', false);
        
        // 金額を再計算
        calculateAmount($targetRow);
        
        // 協力会社名を「仕入」フィールドに表示
        if (window.ktpCurrentSupplierName) {
            const purchaseDisplayText = window.ktpCurrentSupplierName && productName ? 
                `${window.ktpCurrentSupplierName} > ${productName}` : 
                window.ktpCurrentSupplierName;
            
            // リンク付きの仕入フィールドを更新
            const $purchaseDisplay = $targetRow.find('.purchase-display');
            if (purchaseDisplayText.indexOf(' > ') !== -1) {
                $purchaseDisplay.removeClass('purchase-link').addClass('purchase-link')
                    .attr('data-purchase', purchaseDisplayText)
                    .text(purchaseDisplayText);
            } else {
                $purchaseDisplay.removeClass('purchase-link')
                    .removeAttr('data-purchase')
                    .text(purchaseDisplayText);
            }
            $targetRow.find('input[name*="[purchase]"]').val(purchaseDisplayText);
        }
        
        // DB即時反映
        const itemId = $targetRow.find('input[name*="[id]"]').val();
        const orderId = $('input[name="order_id"]').val() || $('#order_id').val();
        
        if (itemId && itemId !== '0' && orderId) {
            console.log('[COST] DB更新処理開始', {
                itemId: itemId,
                orderId: orderId,
                productName: serviceData.product_name,
                unitPrice: serviceData.unit_price,
                quantity: serviceData.quantity,
                unit: serviceData.unit,
                supplierId: window.ktpCurrentSupplierId
            });
            
            // 各フィールドを順次保存（協力会社選択と従来ポップアップの両方に対応）
            autoSaveItem('cost', itemId, 'product_name', productName, orderId);
            autoSaveItem('cost', itemId, 'price', unitPrice, orderId);
            autoSaveItem('cost', itemId, 'quantity', quantity, orderId);
            autoSaveItem('cost', itemId, 'unit', unit, orderId);
            autoSaveItem('cost', itemId, 'tax_rate', taxRate, orderId);
            
            // 協力会社名を「仕入」フィールドに保存
            if (window.ktpCurrentSupplierName) {
                const purchaseDisplayText = window.ktpCurrentSupplierName && productName ? 
                    `${window.ktpCurrentSupplierName} > ${productName}` : 
                    window.ktpCurrentSupplierName;
                autoSaveItem('cost', itemId, 'purchase', purchaseDisplayText, orderId);
            }
            
            // supplier_idも保存（設定されている場合）
            if (window.ktpCurrentSupplierId) {
                autoSaveItem('cost', itemId, 'supplier_id', window.ktpCurrentSupplierId, orderId);
            }
            
            // 金額も再計算・保存
            calculateAmount($targetRow);
            
            console.log('[COST] DB更新処理完了');
        } else {
            console.warn('[COST] DB更新スキップ - 条件未満', {
                itemId: itemId,
                orderId: orderId
            });
        }
        
        // ポップアップ自動クローズ（協力会社選択と従来ポップアップの両方に対応）
        if ($popup.length > 0) {
            $popup.remove();
        }
        // 協力会社選択ポップアップの場合
        if ($('#ktp-supplier-selector-modal').length > 0) {
            $('#ktp-supplier-selector-modal').remove();
        }
        
        // 処理完了後、フラグを解除してボタンを再有効化
        $btn.data('processing', false);
        $btn.prop('disabled', false);
        
        // ボタンテキストを元に戻す
        const originalUpdateText2 = $btn.data('original-text');
        if (originalUpdateText2) {
            $btn.text(originalUpdateText2);
        }
        
        console.log('[COST] 更新処理完了');
    });

    // ポップアップ内の「追加」ボタン（協力会社選択と従来ポップアップの両方に対応）
    $(document).off('click', '.ktp-cost-add-btn').on('click', '.ktp-cost-add-btn', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        // 協力会社選択専用ボタンは除外（新しいハンドラーで処理される）
        if ($(this).hasClass('ktp-supplier-add-btn')) {
            console.log('[COST] 協力会社選択専用ボタンのため処理をスキップ');
            return;
        }
        
        // 重複実行防止フラグをチェック
        const $btn = $(this);
        console.log('[COST] 追加ボタンクリック開始', {
            btnElement: $btn[0],
            processing: $btn.data('processing'),
            timestamp: new Date().toISOString(),
            version: '2025-01-10-v2.0'
        });
        
        if ($btn.data('processing')) {
            console.log('[COST] 追加処理中のため、重複実行をブロック');
            return;
        }
        
        // 処理中フラグを設定
        $btn.data('processing', true);
        $btn.prop('disabled', true);
        
        // ボタンのテキストを変更して処理中であることを明示
        const originalAddText = $btn.text();
        $btn.data('original-text', originalAddText);
        $btn.text('処理中...');
        
        console.log('[COST] 追加ボタンクリック');
        
        const $popup = $btn.closest('.popup-dialog, #ktp-supplier-selector-modal');
        
        // 協力会社選択ポップアップからのデータを取得（JSON形式のdata-service属性から）
        let serviceData = $btn.data('service');
        if (typeof serviceData === 'string') {
            try {
                serviceData = JSON.parse(serviceData);
            } catch (e) {
                console.error('[COST] サービスデータのJSONパースエラー:', e);
            }
        }
        
        // 従来のポップアップ形式への対応も維持
        if (!serviceData) {
            serviceData = $btn.closest('.supplier-service-item').data('service');
        }
        
        if (!serviceData) {
            console.error('[COST] 追加するサービスデータが見つかりません');
            alert('追加するサービスデータが見つかりません。');
            // エラー時にフラグを解除
            $btn.data('processing', false);
            $btn.prop('disabled', false);
            
            // ボタンテキストを元に戻す
            const originalAddText2 = $btn.data('original-text');
            if (originalAddText2) {
                $btn.text(originalAddText2);
            }
            return;
        }
        
        console.log('[COST] 追加処理開始', {
            serviceData: serviceData,
            timestamp: new Date().toISOString(),
            buttonElement: $btn[0]
        });
        
        // 一番下に新規行を追加
        const $lastRow = $('.cost-items-table tbody tr').last();
        const callId = Date.now();
        const rowAdded = addNewRow($lastRow, callId);
        
        if (!rowAdded) {
            console.error('[COST] 新規行の追加に失敗しました');
            alert('新規行の追加に失敗しました。');
            // 新規行追加失敗時にフラグを解除
            $btn.data('processing', false);
            $btn.prop('disabled', false);
            
            // ボタンテキストを元に戻す
            const originalAddText3 = $btn.data('original-text');
            if (originalAddText3) {
                $btn.text(originalAddText3);
            }
            return;
        }
        
        const $newRow = $lastRow.next();
        
        console.log('[COST] 新規行追加完了', {
            newRowIndex: $newRow.index()
        });
        
        // UI反映（協力会社選択と従来ポップアップの両方に対応）
        const productName = serviceData.product_name || serviceData.name || '';
        const unitPrice = serviceData.unit_price || serviceData.price || 0;
        const quantity = serviceData.quantity || 1;
        const unit = serviceData.unit || '';
        const taxRate = serviceData.tax_rate !== null && serviceData.tax_rate !== undefined && serviceData.tax_rate !== '' ? serviceData.tax_rate : '';
        
        $newRow.find('.product-name').val(productName);
        $newRow.find('.price').val(unitPrice);
        $newRow.find('.quantity').val(quantity);
        $newRow.find('.unit').val(unit);
        $newRow.find('.tax-rate').val(taxRate);
        $newRow.find('input').prop('disabled', false);
        
        // 金額を再計算
        calculateAmount($newRow);
        
        // 協力会社名を「仕入」フィールドに表示
        if (window.ktpCurrentSupplierName) {
            const purchaseDisplayText = window.ktpCurrentSupplierName && productName ? 
                `${window.ktpCurrentSupplierName} > ${productName}` : 
                window.ktpCurrentSupplierName;
            
            // リンク付きの仕入フィールドを更新
            const $purchaseDisplay = $newRow.find('.purchase-display');
            if (purchaseDisplayText.indexOf(' > ') !== -1) {
                $purchaseDisplay.removeClass('purchase-link').addClass('purchase-link')
                    .attr('data-purchase', purchaseDisplayText)
                    .text(purchaseDisplayText);
            } else {
                $purchaseDisplay.removeClass('purchase-link')
                    .removeAttr('data-purchase')
                    .text(purchaseDisplayText);
            }
            $newRow.find('input[name*="[purchase]"]').val(purchaseDisplayText);
        }
        
        // DB新規作成
        const orderId = $('input[name="order_id"]').val() || $('#order_id').val();
        
        if (orderId) {
            console.log('[COST] DB新規作成開始', {
                orderId: orderId,
                productName: serviceData.product_name,
                unitPrice: serviceData.unit_price,
                quantity: serviceData.quantity,
                unit: serviceData.unit
            });
            
            console.log('[COST] createNewItem呼び出し直前', {
                orderId: orderId,
                productName: productName,
                timestamp: new Date().toISOString(),
                stackTrace: new Error().stack
            });
            
            createNewItem('cost', 'product_name', productName, orderId, $newRow, function(success, newItemId) {
                if (success && newItemId) {
                    console.log('[COST] 新規アイテム作成成功', {
                        newItemId: newItemId,
                        timestamp: new Date().toISOString()
                    });
                    
                    // 各フィールドを順次保存（協力会社選択と従来ポップアップの両方に対応）
                    autoSaveItem('cost', newItemId, 'price', unitPrice, orderId);
                    autoSaveItem('cost', newItemId, 'quantity', quantity, orderId);
                    autoSaveItem('cost', newItemId, 'unit', unit, orderId);
                    
                    // 税率も保存（協力会社選択と従来ポップアップの両方に対応）
                    // 税率の処理：null、空文字の場合はnullとして送信、0の場合は0として送信
                    const taxRateForDB = (taxRate === null || taxRate === '') ? null : Math.round(taxRate);
                    autoSaveItem('cost', newItemId, 'tax_rate', taxRateForDB, orderId);
                    
                    // 協力会社名を「仕入」フィールドに保存
                    if (window.ktpCurrentSupplierName) {
                        const purchaseDisplayText = window.ktpCurrentSupplierName && productName ? 
                            `${window.ktpCurrentSupplierName} > ${productName}` : 
                            window.ktpCurrentSupplierName;
                        autoSaveItem('cost', newItemId, 'purchase', purchaseDisplayText, orderId);
                    }
                    
                    // supplier_idも保存（設定されている場合）
                    if (window.ktpCurrentSupplierId) {
                        autoSaveItem('cost', newItemId, 'supplier_id', window.ktpCurrentSupplierId, orderId);
                    }
                    
                    // 金額も再計算・保存
                    calculateAmount($newRow);
                    
                    console.log('[COST] DB新規作成完了');
                } else {
                    console.error('[COST] 新規コスト項目のDB作成に失敗しました');
                    alert('新規コスト項目のDB作成に失敗しました。');
                }
                
                // 処理完了後、フラグを解除してボタンを再有効化
                $btn.data('processing', false);
                $btn.prop('disabled', false);
                
                // ボタンテキストを元に戻す
                const originalAddText1 = $btn.data('original-text');
                if (originalAddText1) {
                    $btn.text(originalAddText1);
                }
            });
        } else {
            console.warn('[COST] DB新規作成スキップ - orderId未設定');
            // orderId未設定の場合もフラグを解除
            $btn.data('processing', false);
            $btn.prop('disabled', false);
            
            // ボタンテキストを元に戻す
            const originalAddText4 = $btn.data('original-text');
            if (originalAddText4) {
                $btn.text(originalAddText4);
            }
        }
        
        // ポップアップを強制的に閉じる（重複実行防止のため）
        setTimeout(function() {
            // 協力会社選択ポップアップを閉じる
            if ($popup.length > 0) {
                $popup.remove();
            }
            if ($('#ktp-supplier-selector-modal').length > 0) {
                $('#ktp-supplier-selector-modal').remove();
            }
            
            console.log('[COST] 追加処理完了 - ポップアップ強制クローズ');
        }, 1000);
        
        console.log('[COST] 追加処理完了');
    });

    // --- ポップアップ内の「追加」「更新」ボタンに自動でクラス付与（コスト項目用） ---
    // ポップアップ表示時にボタンへクラスを自動付与
    $(document).on('DOMNodeInserted', '.popup-dialog', function(e) {
        const $popup = $(this);
        
        // 少し遅延を入れてDOMの構築を待つ
        setTimeout(function() {
            // 「更新」ボタン
            $popup.find('button, input[type="button"], a.button').each(function() {
                const $btn = $(this);
                const btnText = $btn.text().trim();
                
                // 既にクラスが付いていなければ付与
                if (btnText === '更新' && !$btn.hasClass('ktp-cost-update-btn')) {
                    $btn.addClass('ktp-cost-update-btn');
                    console.log('[COST] 更新ボタンにクラス付与:', $btn);
                }
                if (btnText === '追加' && !$btn.hasClass('ktp-cost-add-btn')) {
                    $btn.addClass('ktp-cost-add-btn');
                    console.log('[COST] 追加ボタンにクラス付与:', $btn);
                }
            });
        }, 100);
    });

    // ページ読み込み完了時の初期化
    $(document).ready(function () {
        console.log('[COST] 📋 ページ初期化開始');
        
        // 並び替え（sortable）有効化
        $('.cost-items-table tbody').sortable({
            handle: '.drag-handle',
            items: '> tr',
            axis: 'y',
            helper: 'clone',
            update: function (event, ui) {
                console.log('[COST] ドラッグ&ドロップ並び替え完了');
                const table = $(this).closest('table');
                
                // name属性のインデックスを更新
                updateRowIndexes(table);
                
                // サーバーに並び順を保存
                const items = [];
                const orderId = $('input[name="order_id"]').val() || $('#order_id').val();
                let hasInvalid = false;
                let invalidItems = [];
                
                $(this).find('tr').each(function (index) {
                    const itemId = $(this).find('input[name*="[id]"]').val();
                    const productName = $(this).find('input[name*="[product_name]"]').val();
                    
                    if (!itemId || isNaN(itemId) || itemId === '0') {
                        hasInvalid = true;
                        invalidItems.push({
                            index: index,
                            itemId: itemId,
                            productName: productName,
                            reason: '無効なID'
                        });
                        console.warn('[COST] 並び替え: 無効なitemId検出', { 
                            index: index, 
                            itemId: itemId, 
                            productName: productName 
                        });
                    } else {
                        items.push({ 
                            id: parseInt(itemId, 10), 
                            sort_order: index + 1 
                        });
                        console.log('[COST] 有効なアイテム追加:', { 
                            id: itemId, 
                            sort_order: index + 1, 
                            productName: productName 
                        });
                    }
                });
                
                if (hasInvalid) {
                    console.error('[COST] 並び替えエラー: 無効なアイテムが検出されました', invalidItems);
                    alert('一部のコスト項目IDが不正です。\n\n無効なアイテム:\n' + 
                          invalidItems.map(item => 
                            `行${item.index + 1}: "${item.productName}" (ID: ${item.itemId}) - ${item.reason}`
                          ).join('\n') + 
                          '\n\n再度ページをリロードしてやり直してください。');
                    return;
                }

                if (items.length > 0 && orderId) {
                    let ajaxUrl = ajaxurl;
                    if (!ajaxUrl && typeof ktp_ajax_object !== 'undefined') {
                        ajaxUrl = ktp_ajax_object.ajax_url;
                    } else if (!ajaxUrl) {
                        ajaxUrl = '/wp-admin/admin-ajax.php'; // Fallback
                    }
                    
                    // 統一されたnonce取得方法
                    let nonce = '';
                    if (typeof ktp_ajax_nonce !== 'undefined') {
                        nonce = ktp_ajax_nonce;
                    } else if (typeof ktp_ajax_object !== 'undefined' && ktp_ajax_object.nonce) {
                        nonce = ktp_ajax_object.nonce;
                    } else if (typeof ktpwp_ajax !== 'undefined' && ktpwp_ajax.nonces && ktpwp_ajax.nonces.auto_save) {
                        nonce = ktpwp_ajax.nonces.auto_save;
                    } else if (typeof window.ktpwp_ajax !== 'undefined' && window.ktpwp_ajax.nonces && window.ktpwp_ajax.nonces.auto_save) {
                        nonce = window.ktpwp_ajax.nonces.auto_save;
                    }
                    
                    console.log('[COST] 並び替え保存開始:', { 
                        order_id: orderId, 
                        items_count: items.length, 
                        nonce_length: nonce ? nonce.length : 0 
                    });

                    $.ajax({
                        url: ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'ktp_update_item_order',
                            order_id: orderId,
                            items: items,
                            item_type: 'cost',
                            nonce: nonce,
                            ktp_ajax_nonce: nonce
                        },
                        success: function (response) {
                            console.log('[COST] updateItemOrderレスポンス', response);
                            try {
                                const result = typeof response === 'string' ? JSON.parse(response) : response;
                                if (result.success) {
                                    console.log('[COST] 並び順の保存に成功しました。');
                                    // 成功時の視覚的フィードバック
                                    $('.cost-items-table tbody').addClass('sort-success');
                                    setTimeout(function() {
                                        $('.cost-items-table tbody').removeClass('sort-success');
                                    }, 1000);
                                } else {
                                    console.warn('[COST] 並び順の保存に失敗しました。', result);
                                    const errorMessage = result.data && result.data.message ? 
                                        result.data.message : 'サーバーエラー';
                                    alert('並び順の保存に失敗しました。\n\nエラー: ' + errorMessage);
                                }
                            } catch (e) {
                                console.error('[COST] updateItemOrderレスポンスパースエラー', e, response);
                                alert('並び順保存の応答処理中にエラーが発生しました。\n\n詳細: ' + e.message);
                            }
                        },
                        error: function (xhr, status, error) {
                            console.error('[COST] updateItemOrderエラー', { 
                                status: status, 
                                error: error, 
                                responseText: xhr.responseText,
                                statusCode: xhr.status
                            });
                            let msg = '並び順の保存中にサーバーエラーが発生しました。\n\n';
                            msg += 'ステータス: ' + status + '\n';
                            msg += 'エラー: ' + error + '\n';
                            if (xhr.status) {
                                msg += 'HTTPステータス: ' + xhr.status + '\n';
                            }
                            if (xhr && xhr.responseText) {
                                msg += 'レスポンス: ' + xhr.responseText.substring(0, 500);
                                if (xhr.responseText.length > 500) {
                                    msg += '...';
                                }
                            }
                            alert(msg);
                        }
                    });
                } else {
                    console.log('[COST] 保存するアイテムがないか、orderIdがありません。', {
                        items_count: items.length,
                        orderId: orderId
                    });
                }
            },
            start: function (event, ui) {
                console.log('[COST] ドラッグ開始');
                ui.item.addClass('dragging');
                // ドラッグ中の視覚的フィードバック
                ui.item.css('opacity', '0.8');
            },
            stop: function (event, ui) {
                console.log('[COST] ドラッグ終了');
                ui.item.removeClass('dragging');
                ui.item.css('opacity', '1');
            }
        }).disableSelection();

        // 単価・数量変更時の金額自動計算（blurイベントでのみ実行）
        $(document).on('blur', '.cost-items-table .price, .cost-items-table .quantity', function () {
            const $field = $(this);
            
            // disabled フィールドは処理をスキップ
            if ($field.prop('disabled')) {
                if (window.ktpDebugMode) {
                    console.log('[COST] Blur event skipped: field is disabled');
                }
                return;
            }
            
            const value = $field.val();
            const row = $field.closest('tr');
            const fieldType = $field.hasClass('price') ? 'price' : 'quantity';
            
            if (window.ktpDebugMode) {
                console.log('[COST] Blur event triggered:', {
                    fieldType: fieldType,
                    value: value,
                    rowIndex: row.index()
                });
            }
            
            // 小数点以下の不要な0を削除して表示
            const formattedValue = formatDecimalDisplay(value);
            if (formattedValue !== value) {
                $field.val(formattedValue);
            }
            
            calculateAmount(row);
        });

        // 税率変更時のリアルタイム再計算
        $(document).on('change', '.cost-items-table .tax-rate', function () {
            const $field = $(this);
            
            // disabled フィールドは処理をスキップ
            if ($field.prop('disabled')) {
                return;
            }
            
            const value = $field.val();
            const row = $field.closest('tr');
            const itemId = row.find('input[name*="[id]"]').val();
            const orderId = $('input[name="order_id"]').val() || $('#order_id').val();
            
            if (window.ktpDebugMode) {
                console.log('[COST] 税率変更イベント:', {
                    value: value,
                    rowIndex: row.index(),
                    itemId: itemId,
                    orderId: orderId
                });
            }
            
            // 税率を自動保存
            if (itemId && itemId !== '0' && orderId) {
                autoSaveItem('cost', itemId, 'tax_rate', value, orderId);
            }
            
            // 利益計算を更新
            updateProfitDisplay();
        });

        // 税率入力時のリアルタイム再計算（inputイベント）
        $(document).on('input', '.cost-items-table .tax-rate', function () {
            const $field = $(this);

            // disabled フィールドは処理をスキップ
            if ($field.prop('disabled')) {
                return;
            }
            
            const value = $field.val();
            const row = $field.closest('tr');
            
            if (window.ktpDebugMode) {
                console.log('[COST] 税率入力イベント:', {
                    value: value,
                    rowIndex: row.index()
                });
            }
            
            // 入力中でも利益計算を更新（リアルタイム表示）
            updateProfitDisplay();
        });

        // 自動追加機能を無効化（コメントアウト）
        // $(document).on('input change', '.cost-items-table .service-name, .cost-items-table .price, .cost-items-table .quantity', function() {
        //     const row = $(this).closest('tr');
        //     const tbody = row.closest('tbody');
        //     const isFirstRow = tbody.find('tr').first().is(row);
        //
        //     // 手動で行を追加した直後は自動追加をスキップ
        //     if (row.hasClass('manual-add')) {
        //         return;
        //     }
        //
        //     // 1行目で実際に値が変更された場合のみ自動追加をチェック
        //     if (isFirstRow) {
        //         // 少し遅延を入れて、連続入力による重複を防ぐ
        //         clearTimeout(row.data('autoAddTimeout'));
        //         const timeoutId = setTimeout(function() {
        //             checkAutoAddRow(row);
        //         }, 300); // 300ms後にチェック
        //         row.data('autoAddTimeout', timeoutId);
        //     }
        // });

        // [+]ボタンで行追加（手動追加のみ）- イベント重複を防ぐ
        // より強力に既存のクリックハンドラを全て解除し、その後で名前空間付きのハンドラを1つだけバインドする
        $(document).off('click', '.cost-items-table .btn-add-row'); // 名前空間なしで全て解除
        $('body').off('click', '.cost-items-table .btn-add-row');   // bodyからの委譲も同様に解除
        $('.cost-items-table').off('click', '.btn-add-row');        // テーブル要素からの委譲も同様に解除

        // その後、私たちの意図する名前空間付きのハンドラを登録
        $(document).on('click.ktpCostAdd', '.cost-items-table .btn-add-row', function (e) {
            const clickId = Date.now(); // Define clickId at the beginning of the handler
            console.log(`[COST][${clickId}] +ボタンクリックイベント発生 (ktpCostAdd - 強力解除後)`); 

            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation(); // 同じ要素の他のハンドラを止める

            const $button = $(this);
            const currentRow = $button.closest('tr');

            // 品名取得（クラス優先、なければname属性）- クリックハンドラ側での先行チェック
            let rawProductNameCH = currentRow.find('input.product-name').val();
            if (typeof rawProductNameCH !== 'string') {
                rawProductNameCH = currentRow.find('input[name$="[product_name]"]').val();
            }
            const productNameValueCH = (typeof rawProductNameCH === 'string') ? rawProductNameCH.trim() : '';
            if (productNameValueCH === '') {
                alert('品名を入力してください。'); // クリックハンドラからのアラート
                console.log(`[COST][${clickId}] クリックハンドラ: 品名未入力。 addNewRow を呼び出さずに処理を中断します。これがこのハンドラの最後のログになるはずです。`);
                return false; // addNewRowを呼び出す前に中断
            }

            console.log(`[COST][${clickId}] クリックハンドラ: 品名入力済み。ktpAddingCostRow の状態 (呼び出し前):`, window.ktpAddingCostRow);

            // ボタン自体の状態で重複クリックをある程度防ぐ
            if ($button.prop('disabled') || $button.hasClass('processing')) {
                console.log(`[COST][${clickId}] ボタンが無効または処理中のためスキップ（クリックハンドラ冒頭）`);
                return false;
            }

            // グローバルな処理中フラグのチェック
            if (window.ktpAddingCostRow === true) {
                console.log(`[COST][${clickId}] クリックハンドラ: 既に処理中のため中止 (ktpAddingCostRow is true)`);
                return false;
            }

            // 即座にボタンを無効化し、フラグを設定
            $button.prop('disabled', true).addClass('processing');
            window.ktpAddingCostRow = true;
            console.log(`[COST][${clickId}] +ボタンクリック処理開始、ボタン無効化、ktpAddingCostRow を true に設定`);

            let rowAddedSuccessfully = false;
            try {
                // addNewRowを呼び出すのは、クリックハンドラ側の品名チェックを通過した後
                console.log(`[COST][${clickId}] addNewRow を呼び出します。`);
                rowAddedSuccessfully = addNewRow(currentRow, clickId); 
                console.log(`[COST][${clickId}] addNewRow の呼び出し結果:`, rowAddedSuccessfully);

                if (rowAddedSuccessfully === false) {
                    // このログは、addNewRowが品名チェックなどでfalseを返した場合に出るはず
                    console.warn(`[COST][${clickId}] addNewRow が false を返しました。これは、addNewRow内部の品名チェックで中断されたか、または他の理由で失敗したことを意味します。この場合、行は追加されていないはずです。もし行が追加されている場合、他の要因が考えられます。`);
                } else {
                    console.log(`[COST][${clickId}] addNewRow が true を返しました。行が正常に追加されました。`);
                }
            } catch (error) {
                console.error(`[COST][${clickId}] addNewRow の呼び出し中またはその前後でエラーが発生:`, error);
                rowAddedSuccessfully = false; // エラー時もfalse扱い
            } finally {
                window.ktpAddingCostRow = false; // フラグを解除
                $button.prop('disabled', false).removeClass('processing');
                console.log(`[COST][${clickId}] ボタン再有効化完了、ktpAddingCostRow を false に設定 (finally)`);
            }
            console.log(`[COST][${clickId}] クリックハンドラの末尾。`);
            return false; // イベントのさらなる伝播を防ぐ
        });

        // 行削除ボタン - イベント重複を防ぐ
        $(document).off('click', '.cost-items-table .btn-delete-row').on('click', '.cost-items-table .btn-delete-row', function (e) {
            e.preventDefault();
            e.stopPropagation();
            const currentRow = $(this).closest('tr');
            deleteRow(currentRow);
        });

        // 行移動ボタン（協力会社選択機能）- コスト項目テーブル専用
        $(document).off('click', '.cost-items-table .btn-move-row');
        $(document).on('click', '.cost-items-table .btn-move-row', function (e) {
            e.preventDefault();
            e.stopPropagation();
            console.log('[COST-ITEMS] [>]ボタンクリック - 協力会社選択開始');
            console.log('[COST-ITEMS] ktpShowSupplierSelector関数の存在確認:', typeof window.ktpShowSupplierSelector);
            console.log('[COST-ITEMS] window.ktpShowSupplierSelector:', window.ktpShowSupplierSelector);
            const currentRow = $(this).closest('tr');
            console.log('[COST-ITEMS] currentRow:', currentRow);
            if (typeof window.ktpShowSupplierSelector === 'function') {
                console.log('[COST-ITEMS] ktpShowSupplierSelector関数を呼び出し');
                try {
                    window.ktpShowSupplierSelector(currentRow); // 必ずjQueryオブジェクトで渡す
                    console.log('[COST-ITEMS] ktpShowSupplierSelector関数呼び出し完了');
                } catch (error) {
                    console.error('[COST-ITEMS] ktpShowSupplierSelector関数呼び出しエラー:', error);
                }
            } else {
                console.error('[COST-ITEMS] ktpShowSupplierSelector関数が見つかりません');
                alert('協力会社選択機能の読み込みに失敗しました。ページを再読み込みしてください。');
            }
        });

        // フォーカス時の入力欄スタイル調整
        $(document).on('focus', '.cost-item-input', function () {
            $(this).addClass('focused');
        });

        $(document).on('blur', '.cost-item-input', function () {
            $(this).removeClass('focused');
        });

        // 数値フィールドフォーカス時に全選択
        $(document).on('focus', '.cost-items-table input[type="number"]', function () {
            $(this).select();
        });

        // 商品名フィールドのblurイベントで自動保存
        $(document).on('blur', '.cost-item-input.product-name', function () {
            if (window.ktpAddingCostRow === true) {
                if (window.ktpDebugMode) {
                    console.log('[COST] Product name blur event skipped due to ktpAddingCostRow flag being true.');
                }
                return; // Exit early
            }

            const $field = $(this);
            const productName = $field.val();
            const $row = $field.closest('tr');
            let itemId = $row.find('input[name*="[id]"]').val();
            const orderId = $('input[name="order_id"]').val() || $('#order_id').val();

            if (window.ktpDebugMode) {
                console.log('Cost product name auto-save debug:', {
                    productName: productName,
                    itemId: itemId,
                    orderId: orderId,
                    hasNonce: typeof ktp_ajax_nonce !== 'undefined',
                    hasAjaxurl: typeof ajaxurl !== 'undefined'
                });
            }

            // 新規行（ID=0 または data-newly-added=true）と既存行の両方を処理
            if (orderId) {
                // 変更点: itemId === '' も新規行扱いにする
                if (itemId === '0' || itemId === '' || $row.data('newly-added')) {
                    // 新規行の場合：新しいレコードを作成
                    // 変更点: productName が空でなく、実際に何か入力された場合のみ createNewItem を呼び出す
                    if (productName.trim() !== '') {
                        createNewItem('cost', 'product_name', productName, orderId, $row, function(success, newItemId) {
                            if (success && newItemId) {
                                itemId = newItemId; // itemIdを更新
                                // 他のフィールドが有効化されるので、必要ならここで何かする
                                // 例えば、単価や数量にデフォルト値があれば、それらをautoSaveItemで保存するなど
                                // 現状はcreateNewItemのコールバック内でフィールド有効化まで
                            } else {
                                // 作成失敗時の処理
                                console.warn('[COST] 商品名blur時、新規アイテム作成失敗');
                            }
                        });
                    } else if ($row.data('newly-added') || itemId === '' || itemId === '0') { // 条件を明確化
                        // 商品名が空のままフォーカスが外れた新規行の場合の処理（例：何もしない、またはユーザーに通知）
                        if (window.ktpDebugMode) {
                            console.log('Cost product name is empty on blur for new/template row. Item not created/saved.', {row: $row[0].outerHTML, itemId: itemId});
                        }
                    }
                } else {
                    // 既存行の場合：商品名を自動保存 (itemId が '0'でも ''でもなく、newly-addedでもない場合)
                    autoSaveItem('cost', itemId, 'product_name', productName, orderId);
                }
            } else {
                if (window.ktpDebugMode) {
                    console.warn('Order ID is missing. Cannot auto-save product name.');
                }
            }
        });

        // 単価フィールドのinputイベントでリアルタイム計算
        $(document).on('input', '.cost-item-input.price', function () {
            const $field = $(this);
            // フィールドが無効なら何もしない (新規行で商品名入力前の状態)
            if ($field.prop('disabled')) return;

            const $row = $field.closest('tr');
            
            // リアルタイムで金額計算を実行
            calculateAmount($row);
        });

        // 単価フィールドのblurイベントで自動保存
        $(document).on('blur', '.cost-item-input.price', function () {
            const $field = $(this);
            // フィールドが無効なら何もしない (新規行で商品名入力前の状態)
            if ($field.prop('disabled')) return;

            const price = $field.val();
            const $row = $field.closest('tr');
            const itemId = $row.find('input[name*="[id]"]').val();
            const orderId = $('input[name="order_id"]').val() || $('#order_id').val();

            if (window.ktpDebugMode) {
                console.log('Cost price auto-save debug:', {
                    price: price,
                    itemId: itemId,
                    orderId: orderId,
                    hasNonce: typeof ktp_ajax_nonce !== 'undefined',
                    hasAjaxurl: typeof ajaxurl !== 'undefined'
                });
            }

            // 新規行（ID=0）は商品名入力時に作成されるので、ここでは既存行のみ対象
            if (orderId && itemId && itemId !== '0') {
                autoSaveItem('cost', itemId, 'price', price, orderId);
            } else {
                if (window.ktpDebugMode) {
                    console.log('Cost price auto-save skipped - item not yet created or missing data');
                }
            }
        });

        // 数量フィールドのinputイベントでリアルタイム計算
        $(document).on('input', '.cost-item-input.quantity', function () {
            const $field = $(this);
            if ($field.prop('disabled')) return;

            const $row = $field.closest('tr');
            
            // リアルタイムで金額計算を実行
            calculateAmount($row);
        });

        // 数量フィールドのblurイベントで自動保存
        $(document).on('blur', '.cost-item-input.quantity', function () {
            const $field = $(this);
            if ($field.prop('disabled')) return;

            const quantity = $field.val();
            const $row = $field.closest('tr');
            const itemId = $row.find('input[name*="[id]"]').val();
            const orderId = $('input[name="order_id"]').val() || $('#order_id').val();

            if (window.ktpDebugMode) {
                console.log('Cost quantity auto-save debug:', {
                    quantity: quantity,
                    itemId: itemId,
                    orderId: orderId,
                    hasNonce: typeof ktp_ajax_nonce !== 'undefined',
                    hasAjaxurl: typeof ajaxurl !== 'undefined'
                });
            }

            // 新規行（ID=0）は商品名入力時に作成されるので、ここでは既存行のみ対象
            if (orderId && itemId && itemId !== '0') {
                autoSaveItem('cost', itemId, 'quantity', quantity, orderId);
            } else {
                if (window.ktpDebugMode) {
                    console.log('Cost quantity auto-save skipped - item not yet created or missing data');
                }
            }
        });

        // 単位フィールドのblurイベントで自動保存
        $(document).on('blur', '.cost-item-input.unit', function () {
            const $field = $(this);
            if ($field.prop('disabled')) return;

            const unit = $field.val();
            const $row = $field.closest('tr');
            const itemId = $row.find('input[name*="[id]"]').val();
            const orderId = $('input[name="order_id"]').val() || $('#order_id').val();

            if (window.ktpDebugMode) {
                console.log('Cost unit auto-save debug:', {
                    unit: unit,
                    itemId: itemId,
                    orderId: orderId,
                    hasNonce: typeof ktp_ajax_nonce !== 'undefined',
                    hasAjaxurl: typeof ajaxurl !== 'undefined'
                });
            }

            if (orderId && itemId && itemId !== '0') {
                autoSaveItem('cost', itemId, 'unit', unit, orderId);
            } else {
                if (window.ktpDebugMode) {
                    console.log('Cost unit auto-save skipped - item not yet created or missing data');
                }
            }
        });

        // 備考フィールドのblurイベントで自動保存
        $(document).on('blur', '.cost-item-input.remarks', function () {
            const $field = $(this);
            if ($field.prop('disabled')) return;

            let remarks = $field.val();
            // 備考欄が「0」の場合は空文字列として扱う
            if (remarks === '0') {
                remarks = '';
                $field.val(''); // フィールドの値も空に更新
            }
            
            const $row = $field.closest('tr');
            const itemId = $row.find('input[name*="[id]"]').val();
            const orderId = $('input[name="order_id"]').val() || $('#order_id').val();

            if (window.ktpDebugMode) {
                console.log('Cost remarks auto-save debug:', {
                    remarks: remarks,
                    itemId: itemId,
                    orderId: orderId,
                    hasNonce: typeof ktp_ajax_nonce !== 'undefined',
                    hasAjaxurl: typeof ajaxurl !== 'undefined'
                });
            }

            if (orderId && itemId && itemId !== '0') {
                autoSaveItem('cost', itemId, 'remarks', remarks, orderId);
            } else {
                if (window.ktpDebugMode) {
                    console.log('Cost remarks auto-save skipped - item not yet created or missing data');
                }
            }
        });

        // 仕入フィールドのblurイベントで自動保存
        $(document).on('blur', '.cost-item-input.purchase', function () {
            const $field = $(this);
            if ($field.prop('disabled')) return;

            const purchase = $field.val();
            const $row = $field.closest('tr');
            const itemId = $row.find('input[name*="[id]"]').val();
            const orderId = $('input[name="order_id"]').val() || $('#order_id').val();

            if (window.ktpDebugMode) {
                console.log('Cost purchase auto-save debug:', {
                    purchase: purchase,
                    itemId: itemId,
                    orderId: orderId,
                    hasNonce: typeof ktp_ajax_nonce !== 'undefined',
                    hasAjaxurl: typeof ajaxurl !== 'undefined'
                });
            }

            if (orderId && itemId && itemId !== '0') {
                autoSaveItem('cost', itemId, 'purchase', purchase, orderId);
            } else {
                if (window.ktpDebugMode) {
                    console.log('Cost purchase auto-save skipped - item not yet created or missing data');
                }
            }
        });

        // 初期状態で既存の行に対して金額計算を実行
        $('.cost-items-table tbody tr').each(function () {
            calculateAmount($(this));
        });

        // 初期ロード時に合計金額と利益を計算・表示
        updateProfitDisplay();

        console.log('[COST] 📋 ページ初期化完了');
    });

    // createNewItem関数をグローバルに露出
    window.createNewItem = createNewItem;

    // フォーム送信時にtr順でname属性indexを再構成
    $(document).on('submit', '.cost-items-form', function(e) {
        const $form = $(this);
        const $table = $form.find('.cost-items-table');
        if ($table.length > 0) {
            updateRowIndexes($table); // tr順でname属性indexを再構成
        }
        // ここでtr順とname属性indexが必ず一致する
    });

    // 仕入リンクのクリックイベントハンドラ
    $(document).on('click', '.purchase-link', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        let supplierName = $(this).data('purchase');
        if (!supplierName) {
            return;
        }
        
        // 新形式（テスト１会社 > サービス名）から協力会社名のみを抽出
        if (supplierName.includes(' > ')) {
            supplierName = supplierName.split(' > ')[0];
        }
        
        // 必要な変数を明示的に取得
        const orderId = $('input[name="order_id"]').val() || $('#order_id').val();
        const projectName = $('input[name="project_name"]').val() || $('#project_name').val() || '案件名未設定';
        
        // 同一協力会社の他の仕入情報を収集
        const supplierItems = [];
        $('.cost-items-table tbody tr').each(function() {
            const $row = $(this);
            const purchaseText = $row.find('.purchase-display').text().trim();
            
            // 新形式（テスト１会社 > サービス名に発注）と旧形式（テスト１会社に発注）の両方に対応
            const isNewFormat = purchaseText && purchaseText.startsWith(supplierName + ' >') && purchaseText.endsWith('に発注');
            const isOldFormat = purchaseText && purchaseText === (supplierName + 'に発注');
            
            if (isNewFormat || isOldFormat) {
                const productName = $row.find('.product-name').val();
                const price = parseFloat($row.find('.price').val()) || 0;
                const quantity = parseFloat($row.find('.quantity').val()) || 0;
                const unit = $row.find('.unit').val() || '';
                const amount = parseFloat($row.find('.amount').val()) || 0;
                const taxRateRaw = $row.find('.tax-rate').val();
                const taxRate = (taxRateRaw !== null && taxRateRaw !== undefined && taxRateRaw !== '') ? parseFloat(taxRateRaw) : null;
                
                if (productName && price > 0) {
                    supplierItems.push({
                        productName: productName,
                        price: price,
                        quantity: quantity,
                        unit: unit,
                        amount: amount,
                        taxRate: taxRate
                    });
                }
            }
        });
        
        // 発注項目が見つからない場合の処理
        if (supplierItems.length === 0) {
            alert(`${supplierName}への発注項目が見つかりません。\n\n対象の行で「${supplierName}に発注」または「${supplierName} > サービス名に発注」と表示されている必要があります。`);
            return;
        }
        
        // 新しい発注メールポップアップ機能を使用
        if (typeof window.ktpShowPurchaseOrderEmailPopup === 'function') {
            window.ktpShowPurchaseOrderEmailPopup(orderId, supplierName);
        } else {
            console.error('[PURCHASE-EMAIL] ktpShowPurchaseOrderEmailPopup関数が見つかりません');
            alert('発注メール機能の読み込みに失敗しました。ページを再読み込みしてください。');
        }
        // ここでこのクリックハンドラの処理は終了
                    return;
    });

    // 価格・数量変更時の金額自動計算（blurイベントでのみ実行）
    $(document).on('blur', '.cost-items-table .price, .cost-items-table .quantity', function () {
        const $field = $(this);
        
        // disabled フィールドは処理をスキップ
        if ($field.prop('disabled')) {
            if (window.ktpDebugMode) {
                console.log('[COST] Blur event skipped: field is disabled');
            }
            return;
        }
        
        const value = $field.val();
        
        // 小数点以下の不要な0を削除して表示
        const formattedValue = formatDecimalDisplay(value);
        if (formattedValue !== value) {
            $field.val(formattedValue);
        }
        
        const row = $field.closest('tr');
        const fieldType = $field.hasClass('price') ? 'price' : 'quantity';
        
        if (window.ktpDebugMode) {
            console.log('[COST] Blur event triggered:', {
                fieldType: fieldType,
                originalValue: value,
                formattedValue: formattedValue,
                rowIndex: row.index()
            });
        }
        
        calculateAmount(row);
    });

    // スピンアップ・ダウンイベントの処理
    $(document).on('input', '.cost-items-table .price, .cost-items-table .quantity', function () {
        const $field = $(this);
        
        // disabled フィールドは処理をスキップ
        if ($field.prop('disabled')) {
            return;
        }
        
        const value = $field.val();
        const row = $field.closest('tr');
        const fieldType = $field.hasClass('price') ? 'price' : 'quantity';
        
        if (window.ktpDebugMode) {
            console.log('[COST] Input event triggered (spin):', {
                fieldType: fieldType,
                value: value,
                rowIndex: row.index()
            });
        }
        
        // スピンイベントの場合は即座に金額計算を実行
        calculateAmount(row);
    });

    // スピンアップ・ダウンイベントの専用処理（changeイベント）
    $(document).on('change', '.cost-items-table .price, .cost-items-table .quantity', function () {
        const $field = $(this);
        
        // disabled フィールドは処理をスキップ
        if ($field.prop('disabled')) {
            return;
        }
        
        const value = $field.val();
        const row = $field.closest('tr');
        const fieldType = $field.hasClass('price') ? 'price' : 'quantity';
        
        if (window.ktpDebugMode) {
            console.log('[COST] Change event triggered (spin):', {
                fieldType: fieldType,
                value: value,
                rowIndex: row.index()
            });
        }
        
        // スピンイベントの場合は即座に金額計算を実行
        calculateAmount(row);
        
        // 小数点以下の表示を即座に適用
        const formattedValue = formatDecimalDisplay(value);
        if (formattedValue !== value) {
            $field.val(formattedValue);
        }
    });

    // 税率変更時の合計表示更新
    $(document).on('blur', '.cost-items-table .tax-rate', function () {
        const $field = $(this);
        
        // disabled フィールドは処理をスキップ
        if ($field.prop('disabled')) {
            return;
        }
        
        const value = $field.val();
        const row = $field.closest('tr');
        
        if (window.ktpDebugMode) {
            console.log('[COST] Tax rate blur event triggered:', {
                value: value,
                rowIndex: row.index()
            });
        }
        
        // 税率変更時に合計表示を更新
        updateProfitDisplay();
    });

    // 税率変更時の即座更新（inputイベント）
    $(document).on('input', '.cost-items-table .tax-rate', function () {
        const $field = $(this);
        
        // disabled フィールドは処理をスキップ
        if ($field.prop('disabled')) {
            return;
        }
        
        const value = $field.val();
        const row = $field.closest('tr');
        
        if (window.ktpDebugMode) {
            console.log('[COST] Tax rate input event triggered:', {
                value: value,
                rowIndex: row.index()
            });
        }
        
        // 税率変更時に合計表示を更新
        updateProfitDisplay();
    });

    // ページ読み込み時の初期化
    $(document).ready(function() {
        // 既存のデバッグボタンを削除
        $('#ktp-debug-toggle, #ktp-cost-debug-button').remove();
        
        // デバッグモードの初期化
        if (typeof window.ktpDebugMode === 'undefined') {
            window.ktpDebugMode = false;
        }
        
        if (window.ktpDebugMode) {
            console.log('[COST] Debug mode is enabled on page load');
        }
    });

    // コスト項目の表示を更新する関数
    function updateCostDisplay(invoiceTotal, costTotal, costTotalTaxAmount, hasOuttax) {
        const invoiceTotalCeiled = Math.ceil(invoiceTotal);
        const costTotalCeiled = Math.ceil(costTotal);
        const costTotalTaxAmountCeiled = Math.ceil(costTotalTaxAmount);
        const costTotalWithTax = costTotalCeiled + costTotalTaxAmountCeiled;

        // コスト項目の合計表示を更新
        const costTotalDisplay = $('.cost-items-total');
        if (costTotalDisplay.length > 0) {
            console.log('[COST] 税区分判定:', {
                hasOuttax: hasOuttax,
                costTotalCeiled: costTotalCeiled,
                costTotalTaxAmountCeiled: costTotalTaxAmountCeiled
            });
            
            if (hasOuttax) {
                // 外税行が1つでもあれば外税3行表示
                costTotalDisplay.html('金額合計 : ' + costTotalCeiled.toLocaleString() + '円');
                costTotalDisplay.show();
                
                const costTaxDisplay = $('.cost-items-tax');
                if (costTaxDisplay.length > 0) {
                    costTaxDisplay.html('消費税 : ' + costTotalTaxAmountCeiled.toLocaleString() + '円');
                    costTaxDisplay.show();
                }

                const costTotalWithTaxDisplay = $('.cost-items-total-with-tax');
                if (costTotalWithTaxDisplay.length > 0) {
                    costTotalWithTaxDisplay.html('税込合計 : ' + costTotalWithTax.toLocaleString() + '円');
                    costTotalWithTaxDisplay.show();
                }
            } else {
                // 全て内税なら内税1行表示
                costTotalDisplay.html('金額合計：' + costTotalCeiled.toLocaleString() + '円　（内税：' + costTotalTaxAmountCeiled.toLocaleString() + '円）');
                costTotalDisplay.show();
                
                $('.cost-items-tax, .cost-items-total-with-tax').hide();
            }
        }
    }

    // 顧客の税区分を取得する関数
    function getClientTaxCategory(callback) {
        // まずグローバル変数から取得を試行
        if (window.ktpClientTaxCategory) {
            callback(window.ktpClientTaxCategory);
            return;
        }

        // 受注書IDから顧客IDを取得して税区分を取得
        const orderId = $('input[name="order_id"]').val() || $('#order_id').val();
        if (!orderId) {
            callback('内税'); // デフォルト
            return;
        }

        // 統一されたnonce取得方法
        let nonce = '';
        if (typeof ktp_ajax_nonce !== 'undefined') {
            nonce = ktp_ajax_nonce;
        } else if (typeof ktp_ajax_object !== 'undefined' && ktp_ajax_object.nonce) {
            nonce = ktp_ajax_object.nonce;
        } else if (typeof ktpwp_ajax !== 'undefined' && ktpwp_ajax.nonces && ktpwp_ajax.nonces.auto_save) {
            nonce = ktpwp_ajax.nonces.auto_save;
        } else if (typeof window.ktpwp_ajax !== 'undefined' && window.ktpwp_ajax.nonces && window.ktpwp_ajax.nonces.auto_save) {
            nonce = window.ktpwp_ajax.nonces.auto_save;
        }

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'ktp_get_client_tax_category_by_order',
                order_id: orderId,
                nonce: nonce
            },
            success: function(response) {
                if (response.success && response.data && response.data.tax_category) {
                    callback(response.data.tax_category);
                } else {
                    console.log('[COST] 顧客税区分取得失敗:', response);
                    callback('内税'); // デフォルト
                }
            },
            error: function(xhr, status, error) {
                console.log('[COST] 顧客税区分取得エラー:', error);
                callback('内税'); // デフォルト
            }
        });
    }

    // 適格請求書ナンバーを考慮した利益計算（顧客税区分対応版）
    function calculateProfitWithQualifiedInvoice(invoiceTotal, costItems, clientTaxCategory, callback) {
        let totalCost = 0;
        let qualifiedInvoiceCost = 0;
        let nonQualifiedInvoiceCost = 0;
        let processedItems = 0;
        const totalItems = costItems.length;

        if (totalItems === 0) {
            // 請求金額をそのまま使用（内税・外税の区分は請求側で処理済み）
            let adjustedInvoiceTotal = invoiceTotal;
            
            callback(adjustedInvoiceTotal, 0, 0, 0);
            return;
        }

        costItems.forEach(function(item) {
            const supplierId = item.supplierId || 0;
            const amount = parseFloat(item.amount) || 0;
            const taxRate = parseFloat(item.taxRate) || 10.0;

            // 供給業者IDが無効な場合は適格請求書がないものとして処理
            if (!supplierId || supplierId <= 0) {
                // 適格請求書がない場合：税込金額をコストとする（仕入税額控除不可）
                nonQualifiedInvoiceCost += amount;
                totalCost += amount;

                if (window.ktpDebugMode) {
                    console.log('[COST] Profit Calculation - Invalid supplier ID ' + supplierId + ', treating as no qualified invoice, Amount: ' + amount + ', Cost: ' + amount);
                }

                processedItems++;

                if (processedItems === totalItems) {
                    // 請求金額をそのまま使用（内税・外税の区分は請求側で処理済み）
                    let adjustedInvoiceTotal = invoiceTotal;
                    
                    const profit = adjustedInvoiceTotal - totalCost;

                    if (window.ktpDebugMode) {
                        console.log('[COST] Profit Calculation Summary - Client Tax Category: ' + clientTaxCategory + ', Original Invoice Total: ' + invoiceTotal + ', Adjusted Invoice Total: ' + adjustedInvoiceTotal + ', Qualified Cost: ' + qualifiedInvoiceCost + ', Non-Qualified Cost: ' + nonQualifiedInvoiceCost + ', Total Cost: ' + totalCost + ', Profit: ' + profit);
                    }

                    callback(profit, qualifiedInvoiceCost, nonQualifiedInvoiceCost, totalCost);
                }
                return;
            }

            // 協力会社の税区分を取得して適切な税額計算を行う
            getSupplierTaxCategory(supplierId, function(taxCategory) {
                getSupplierQualifiedInvoiceNumber(supplierId, function(qualifiedInvoiceNumber) {
                    const hasQualifiedInvoice = qualifiedInvoiceNumber && qualifiedInvoiceNumber.trim() !== '';

                    if (hasQualifiedInvoice) {
                        // 適格請求書がある場合：税抜金額のみをコストとする（仕入税額控除可能）
                        let taxAmount = 0;
                        let costAmount = 0;
                        
                        if (taxCategory === '外税') {
                            // 外税の場合：税込金額から税額を計算
                            taxAmount = amount * (taxRate / 100);
                            costAmount = amount - taxAmount;
                        } else {
                            // 内税の場合：税込金額から税額を計算
                            taxAmount = amount * (taxRate / 100) / (1 + taxRate / 100);
                            costAmount = amount - taxAmount;
                        }
                        
                        qualifiedInvoiceCost += costAmount;
                        totalCost += costAmount;

                        if (window.ktpDebugMode) {
                            console.log('[COST] Profit Calculation - Supplier ID ' + supplierId + ' has qualified invoice: ' + qualifiedInvoiceNumber + ', Tax Category: ' + taxCategory + ', Amount: ' + amount + ', Tax: ' + taxAmount + ', Cost: ' + costAmount);
                        }
                    } else {
                        // 適格請求書がない場合：税込金額をコストとする（仕入税額控除不可）
                        nonQualifiedInvoiceCost += amount;
                        totalCost += amount;

                        if (window.ktpDebugMode) {
                            console.log('[COST] Profit Calculation - Supplier ID ' + supplierId + ' has no qualified invoice, Tax Category: ' + taxCategory + ', Amount: ' + amount + ', Cost: ' + amount);
                        }
                    }

                    processedItems++;

                    if (processedItems === totalItems) {
                        // 請求金額をそのまま使用（内税・外税の区分は請求側で処理済み）
                        let adjustedInvoiceTotal = invoiceTotal;
                        
                        const profit = adjustedInvoiceTotal - totalCost;

                        if (window.ktpDebugMode) {
                            console.log('[COST] Profit Calculation Summary - Client Tax Category: ' + clientTaxCategory + ', Original Invoice Total: ' + invoiceTotal + ', Adjusted Invoice Total: ' + adjustedInvoiceTotal + ', Qualified Cost: ' + qualifiedInvoiceCost + ', Non-Qualified Cost: ' + nonQualifiedInvoiceCost + ', Total Cost: ' + totalCost + ', Profit: ' + profit);
                        }

                        callback(profit, qualifiedInvoiceCost, nonQualifiedInvoiceCost, totalCost);
                    }
                });
            });
        });
    }

    // ページ読み込み時の初期化
    $(document).ready(function() {
        // デバッグモードの初期化
        if (typeof window.ktpDebugMode === 'undefined') {
            window.ktpDebugMode = false;
        }
        
        if (window.ktpDebugMode) {
            console.log('[COST] Debug mode is enabled on page load');
        }
    });


})(jQuery);
