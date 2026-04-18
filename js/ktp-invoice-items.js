/**
 * 請求項目テーブルのJavaScript機能
 *
 * @package KTPWP
 * @since 1.0.0
 */

(function ($) {
    'use strict';

    // デバッグモードを有効化（本番では false に設定）
    window.ktpDebugMode = true; // 本番環境では false に設定

    // 進行中のAJAXリクエストを追跡するオブジェクト
    window.ktpInvoicePendingRequests = {};
    
    // 金額自動保存のデバウンス用タイマー
    window.ktpInvoiceAmountSaveTimers = {};

    // 統一されたAJAX設定の取得
    function getAjaxConfig() {
        const config = {
            url: '',
            nonce: ''
        };
        
        // URLの取得（優先順位順）
        if (typeof ktpwp_ajax !== 'undefined' && ktpwp_ajax.ajax_url) {
            config.url = ktpwp_ajax.ajax_url;
        } else if (typeof ktp_ajax_object !== 'undefined' && ktp_ajax_object.ajax_url) {
            config.url = ktp_ajax_object.ajax_url;
        } else if (typeof ajaxurl !== 'undefined') {
            config.url = ajaxurl;
        } else {
            config.url = '/wp-admin/admin-ajax.php';
        }
        
        // nonceの取得（優先順位順）
        if (typeof ktpwp_ajax !== 'undefined' && ktpwp_ajax.nonces && ktpwp_ajax.nonces.auto_save) {
            config.nonce = ktpwp_ajax.nonces.auto_save;
        } else if (typeof ktpwp_ajax !== 'undefined' && ktpwp_ajax.nonces && ktpwp_ajax.nonces.general) {
            config.nonce = ktpwp_ajax.nonces.general;
        } else if (typeof ktp_ajax_nonce !== 'undefined') {
            config.nonce = ktp_ajax_nonce;
        } else if (typeof ktp_ajax_object !== 'undefined' && ktp_ajax_object.nonce) {
            config.nonce = ktp_ajax_object.nonce;
        }
        
        return config;
    }

    // 利用可能な変数を確認（デバッグモード時のみ）
    if (window.ktpDebugMode) {
        const ajaxConfig = getAjaxConfig();
        console.log('[INVOICE] AJAX設定確認:');
        console.log('  - URL:', ajaxConfig.url);
        console.log('  - Nonce:', ajaxConfig.nonce ? '設定済み' : '未設定');
        console.log('  - ktpwp_ajax:', typeof ktpwp_ajax !== 'undefined' ? '利用可能' : '未定義');
        console.log('  - ktp_ajax_object:', typeof ktp_ajax_object !== 'undefined' ? '利用可能' : '未定義');
    }

    // デバウンス機能付きの金額保存関数
    function debouncedAmountSave(itemType, itemId, amount, orderId) {
        const timerKey = `${itemType}_${itemId}_amount`;
        
        // 既存のタイマーをクリア
        if (window.ktpInvoiceAmountSaveTimers[timerKey]) {
            clearTimeout(window.ktpInvoiceAmountSaveTimers[timerKey]);
        }
        
        // 新しいタイマーを設定（500ms後に実行）
        window.ktpInvoiceAmountSaveTimers[timerKey] = setTimeout(function() {
            if (window.ktpDebugMode) console.log('[INVOICE] debouncedAmountSave: 実行', {itemType, itemId, amount, orderId});
            window.ktpInvoiceAutoSaveItem(itemType, itemId, 'amount', amount, orderId);
        }, 500);
    }

    // グローバルスコープに関数を定義
    window.ktpInvoiceAutoSaveItem = function (itemType, itemId, fieldName, fieldValue, orderId) {
        if (window.ktpDebugMode) console.log('[INVOICE AUTO-SAVE] 呼び出し', { itemType, itemId, fieldName, fieldValue, orderId });
        
        // リクエストキーを作成
        const requestKey = `${itemType}_${itemId}_${fieldName}`;
        
        // 既存のリクエストがあればキャンセル
        if (window.ktpInvoicePendingRequests[requestKey]) {
            if (window.ktpDebugMode) console.log('[INVOICE AUTO-SAVE] 既存のリクエストをキャンセル:', requestKey);
            window.ktpInvoicePendingRequests[requestKey].abort();
        }
        
        // 統一されたAJAX設定を取得
        const ajaxConfig = getAjaxConfig();

        const ajaxData = {
            action: 'ktp_auto_save_item',
            item_type: itemType,
            item_id: itemId,
            field_name: fieldName,
            field_value: fieldValue,
            order_id: orderId,
            nonce: ajaxConfig.nonce,
            ktp_ajax_nonce: ajaxConfig.nonce  // 追加: PHPでチェックされるフィールド名
        };
        
        if (window.ktpDebugMode) console.log('[INVOICE AUTO-SAVE] Ajax data:', ajaxData);
        if (window.ktpDebugMode) console.log('[INVOICE AUTO-SAVE] Ajax URL:', ajaxConfig.url);
        
        // AJAXリクエストを実行し、進行中のリクエストとして記録
        const xhr = $.ajax({
            url: ajaxConfig.url,
            type: 'POST',
            data: ajaxData,
            timeout: 30000, // 30秒のタイムアウトに延長
            beforeSend: function(xhr) {
                if (window.ktpDebugMode) console.log('[INVOICE AUTO-SAVE] Sending request with data:', ajaxData);
            },
            success: function (response) {
                if (window.ktpDebugMode) console.log('[INVOICE AUTO-SAVE] Raw response:', response);
                try {
                    const result = typeof response === 'string' ? JSON.parse(response) : response;
                    if (window.ktpDebugMode) console.log('[INVOICE AUTO-SAVE] Parsed response:', result);
                    if (result.success) {
                        if (window.ktpDebugMode) console.log('[INVOICE AUTO-SAVE] 保存成功 - field:', fieldName, 'value:', fieldValue);
                        
                        // 成功通知を表示（条件付き）
                        // 実際に値が変更された場合のみ通知を表示
                        if (typeof window.showSuccessNotification === 'function' && 
                            result.data && result.data.value_changed === true) {
                            window.showSuccessNotification('請求項目が保存されました');
                        }
                    } else {
                        if (window.ktpDebugMode) console.warn('[INVOICE AUTO-SAVE] 保存失敗 - field:', fieldName, 'response:', result);
                        
                        // エラー通知を表示（タイムアウト以外のエラーのみ）
                        if (typeof window.showErrorNotification === 'function' && 
                            result.data && result.data !== 'timeout') {
                            window.showErrorNotification('請求項目の保存に失敗しました: ' + (result.data || '不明なエラー'));
                        }
                    }
                } catch (e) {
                    if (window.ktpDebugMode) console.error('[INVOICE AUTO-SAVE] レスポンスパースエラー:', e, 'response:', response);
                    
                    // エラー通知を表示
                    if (typeof window.showErrorNotification === 'function') {
                        window.showErrorNotification('請求項目の保存中にエラーが発生しました');
                    }
                }
            },
            error: function (xhr, status, error) {
                // タイムアウトエラーの場合は警告レベルでログ出力
                if (status === 'timeout') {
                    if (window.ktpDebugMode) console.warn('[INVOICE AUTO-SAVE] タイムアウト (30秒) - field:', fieldName, 'value:', fieldValue);
                } else {
                    if (window.ktpDebugMode) console.error('[INVOICE AUTO-SAVE] Ajax エラー:', {
                        field: fieldName,
                        value: fieldValue,
                        status: status,
                        error: error,
                        responseText: xhr.responseText,
                        statusCode: xhr.status,
                        readyState: xhr.readyState
                    });
                }
                
                // エラー通知を表示（タイムアウト以外のエラーのみ）
                if (typeof window.showErrorNotification === 'function' && status !== 'timeout') {
                    let errorMessage = '請求項目の保存に失敗しました';
                    if (xhr.status === 403) {
                        errorMessage += ' (権限エラー)';
                    } else if (xhr.status === 500) {
                        errorMessage += ' (サーバーエラー)';
                    }
                    window.showErrorNotification(errorMessage);
                }
            },
            complete: function() {
                // リクエスト完了時に進行中のリクエストから削除
                delete window.ktpInvoicePendingRequests[requestKey];
                if (window.ktpDebugMode) console.log('[INVOICE AUTO-SAVE] リクエスト完了:', requestKey);
            }
        });
        
        // 進行中のリクエストとして記録
        window.ktpInvoicePendingRequests[requestKey] = xhr;
    };
    // createNewItem関数にcallback引数を追加し、成功/失敗と新しいitem_idを返すように変更
    window.ktpInvoiceCreateNewItem = function (itemType, fieldName, fieldValue, orderId, $row, callback, skipAmountCalculation = false) {
        if (window.ktpDebugMode) console.log('[INVOICE] createNewItem呼び出し', { itemType, fieldName, fieldValue, orderId, $row });
        // 統一されたAJAX設定を取得
        const ajaxConfig = getAjaxConfig();

        const ajaxData = {
            action: 'ktp_create_new_item',
            item_type: itemType,
            field_name: fieldName,
            field_value: fieldValue,
            order_id: orderId,
            nonce: ajaxConfig.nonce,
            ktp_ajax_nonce: ajaxConfig.nonce  // 追加: PHPでチェックされるフィールド名
        };
        if (window.ktpDebugMode) console.log('[INVOICE] createNewItem送信', ajaxData);
        $.ajax({
            url: ajaxConfig.url,
            type: 'POST',
            data: ajaxData,
            success: function (response) {
                if (window.ktpDebugMode) console.log('[INVOICE] createNewItemレスポンス', response);
                try {
                    const result = typeof response === 'string' ? JSON.parse(response) : response;
                    // wp_send_json_success はレスポンスを { success: true, data: { ... } } の形でラップする
                    if (result.success && result.data && result.data.item_id) {
                        // 新しいIDをhidden inputに設定
                        $row.find('input[name*="[id]"]').val(result.data.item_id);
                        // data-newly-added属性を削除し、他のフィールドを有効化
                        if ($row.data('newly-added')) {
                            $row.removeAttr('data-newly-added');
                            $row.find('.invoice-item-input').not('.product-name').not('.amount').prop('disabled', false);
                            if (window.ktpDebugMode) console.log('[INVOICE] createNewItem: 他のフィールドを有効化', $row);
                            
                            // フィールド有効化後に金額計算を実行（スキップフラグがfalseの場合のみ）
                            if (!skipAmountCalculation) {
                                setTimeout(function() {
                                    calculateAmount($row);
                                    if (window.ktpDebugMode) console.log('[INVOICE] createNewItem: フィールド有効化後の金額計算実行');
                                    
                                    // 新規レコード作成後に金額も保存
                                    const currentAmountValue = $row.find('.invoice-item-amount').attr('data-amount') || $row.find('.invoice-item-amount').text().replace(/,/g, '');
                                    const currentAmount = parseFloat(currentAmountValue) || 0;
                                    if (currentAmount && currentAmount !== '0') {
                                        if (window.ktpDebugMode) console.log('[INVOICE] createNewItem: 新規レコード作成後の金額保存', {
                                            newItemId: result.data.item_id,
                                            amount: currentAmount
                                        });
                                        window.ktpInvoiceAutoSaveItem('invoice', result.data.item_id, 'amount', currentAmount, orderId);
                                    }
                                }, 100);
                            } else {
                                if (window.ktpDebugMode) console.log('[INVOICE] createNewItem: 金額計算をスキップ（サービス選択から追加）');
                            }
                        }
                        if (window.ktpDebugMode) console.log('[INVOICE] createNewItem新規IDセット', result.data.item_id);
                        if (callback) callback(true, result.data.item_id); // コールバック呼び出し
                    } else {
                        if (window.ktpDebugMode) console.warn('[INVOICE] createNewItem失敗（レスポンス構造確認）', result);
                        if (callback) callback(false, null); // コールバック呼び出し
                    }
                } catch (e) {
                    if (window.ktpDebugMode) console.error('[INVOICE] createNewItemレスポンスパースエラー', e, response);
                    if (callback) callback(false, null); // コールバック呼び出し
                }
            },
            error: function (xhr, status, error) {
                if (window.ktpDebugMode) console.error('[INVOICE] createNewItemエラー', {
                    status: status,
                    error: error,
                    responseText: xhr.responseText,
                    statusCode: xhr.status
                });
                if (callback) callback(false, null); // コールバック呼び出し
            }
        });
    };

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

    // 価格×数量の自動計算
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
            if (window.ktpDebugMode) console.log('[INVOICE] calculateAmount called:', {
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
        
        // span要素とhidden inputの両方を更新
        const $amountSpan = row.find('.invoice-item-amount');
        const $amountInput = row.find('input[name*="[amount]"]');
        
        if ($amountSpan.length > 0) {
            $amountSpan.text(finalAmount.toLocaleString());
            $amountSpan.attr('data-amount', finalAmount);
        }
        
        if ($amountInput.length > 0) {
            $amountInput.val(finalAmount);
        }

        // 金額を自動保存（デバウンス機能付き）
        const itemId = row.find('input[name*="[id]"]').val();
        const orderId = $('input[name="order_id"]').val() || $('#order_id').val();

        if (itemId && orderId) {
            if (itemId === '0') {
                // 新規行の場合：商品名が入力済みなら金額も保存
                const productName = row.find('.product-name').val().trim();
                if (productName !== '') {
                    if (window.ktpDebugMode) console.log('[INVOICE] calculateAmount: 新規行だが商品名入力済みのため金額保存実行', {
                        itemId, 
                        amount: finalAmount, 
                        productName: productName
                    });
                    // 新規行の場合は商品名入力時にレコードが作成されるので、
                    // その後に金額を保存するため少し遅延させる
                    setTimeout(function() {
                        const currentItemId = row.find('input[name*="[id]"]').val();
                        if (currentItemId && currentItemId !== '0') {
                            if (window.ktpDebugMode) console.log('[INVOICE] calculateAmount: 遅延実行で金額保存', {
                                currentItemId, 
                                amount: finalAmount
                            });
                            // デバウンス機能付きで金額保存
                            debouncedAmountSave('invoice', currentItemId, finalAmount, orderId);
                        }
                    }, 500);
                } else {
                    if (window.ktpDebugMode) console.log('[INVOICE] calculateAmount: 新規行で商品名未入力のため金額保存スキップ');
                }
            } else {
                // 既存行の場合：デバウンス機能付きで金額を自動保存
                if (window.ktpDebugMode) console.log('[INVOICE] calculateAmount: 既存行の金額自動保存実行', {
                    itemId, 
                    amount: finalAmount
                });
                debouncedAmountSave('invoice', itemId, finalAmount, orderId);
            }
        } else {
            if (window.ktpDebugMode) console.warn('[INVOICE] calculateAmount: 保存条件未満', {itemId, orderId});
        }

        // 請求項目合計と利益表示を更新
        updateTotalAndProfit();
    }

    // 請求項目合計と利益表示を更新
    function updateTotalAndProfit() {
        let invoiceTotal = 0;
        let costTotal = 0;
        let totalTaxAmount = 0;
        let costTotalTaxAmount = 0;

        // 税率別の集計用オブジェクト
        let taxRateGroups = {};
        let costTaxRateGroups = {};

        // 顧客の税区分を取得（デフォルトは内税）
        let taxCategory = '内税';
        
        // 受注書IDがある場合は顧客の税区分を取得
        const orderId = $('input[name="order_id"]').val() || $('#order_id').val();
        if (orderId) {
            // 既存の税区分情報があれば使用
            if (typeof window.ktpClientTaxCategory !== 'undefined') {
                taxCategory = window.ktpClientTaxCategory;
            }
        }

        // 請求項目の合計と消費税を計算（税率別に集計）
        $('#order_content .invoice-items-table tbody tr').each(function () {
            const $row = $(this);
            const amountValue = $row.find('.invoice-item-amount').attr('data-amount') || $row.find('.invoice-item-amount').text().replace(/,/g, '');
            const amount = parseFloat(amountValue) || 0;
            const taxRateInput = $row.find('.tax-rate').val();
            
            // 税率の処理（NULL、空文字、NaNの場合は税率なしとして扱う）
            let taxRate = null;
            if (taxRateInput !== null && taxRateInput !== '' && !isNaN(parseFloat(taxRateInput))) {
                taxRate = parseFloat(taxRateInput);
            }
            
            invoiceTotal += amount;
            
            // 税率別に集計（税率なしの場合は'no_tax_rate'として扱う）
            const taxRateKey = taxRate !== null ? taxRate.toString() : 'no_tax_rate';
            if (!taxRateGroups[taxRateKey]) {
                taxRateGroups[taxRateKey] = 0;
            }
            taxRateGroups[taxRateKey] += amount;

            // デバッグログ（各行の金額）
            if (window.ktpDebugMode) {
                console.log('[INVOICE] 請求項目行:', {
                    rowIndex: $row.index(),
                    amountValue: amountValue,
                    amount: amount,
                    taxRate: taxRate,
                    runningTotal: invoiceTotal
                });
            }
        });

        // 税制モード: 税廃止/一律税率の適用
        function normalizeRate(raw) {
            if (window.ktp_tax_policy) {
                if (window.ktp_tax_policy.mode === 'abolished') return 0;
                if (window.ktp_tax_policy.mode === 'unified') return parseFloat(window.ktp_tax_policy.unified_tax_rate || 0);
            }
            const n = parseFloat(raw);
            return isNaN(n) ? null : n;
        }

        // 税区分に応じて消費税を計算
        if (taxCategory === '外税') {
            // 外税表示の場合：各項目の税抜金額から税額を計算
            $('#order_content .invoice-items-table tbody tr').each(function () {
                const $row = $(this);
                const amountValue = $row.find('.invoice-item-amount').attr('data-amount') || $row.find('.invoice-item-amount').text().replace(/,/g, '');
                const amount = parseFloat(amountValue) || 0;
                const taxRateInput = $row.find('.tax-rate').val();
                
                // 税率の処理（NULL、空文字、NaNの場合は税率なしとして扱う）
                let taxRate = normalizeRate(taxRateInput);
                
                // 税率が設定されている場合のみ税額を計算
                if (taxRate !== null) {
                // 外税計算：税抜金額から税額を計算（切り上げ）
                const taxAmount = Math.ceil(amount * (taxRate / 100));
                totalTaxAmount += taxAmount;
                }
            });
        } else {
            // 内税表示の場合：税率別に税額を計算
            Object.keys(taxRateGroups).forEach(taxRateKey => {
                const groupAmount = taxRateGroups[taxRateKey];
                
                // 税率が設定されている場合のみ税額を計算
                if (taxRateKey !== 'no_tax_rate') {
                    let rate = parseFloat(taxRateKey);
                    if (window.ktp_tax_policy) {
                        if (window.ktp_tax_policy.mode === 'abolished') rate = 0;
                        if (window.ktp_tax_policy.mode === 'unified') rate = parseFloat(window.ktp_tax_policy.unified_tax_rate || 0);
                    }
                    // 内税計算：各税率グループごとに税額を計算（切り上げ）
                    const taxAmount = Math.ceil(groupAmount * (rate / 100) / (1 + rate / 100));
                    totalTaxAmount += taxAmount;
                }
            });
        }

        // コスト項目の合計と消費税を計算（税率別に集計）
        $('.cost-items-table tbody tr').each(function () {
            const $row = $(this);
            const amountValue = $row.find('.cost-item-amount').attr('data-amount') || $row.find('.cost-item-amount').text().replace(/,/g, '');
            const amount = parseFloat(amountValue) || 0;
            const taxRateInput = $row.find('.tax-rate').val();
            
            // 税率の処理（NULL、空文字、NaNの場合は税率なしとして扱う）
            let taxRate = null;
            if (taxRateInput !== null && taxRateInput !== '' && !isNaN(parseFloat(taxRateInput))) {
                taxRate = parseFloat(taxRateInput);
            }
            
            costTotal += amount;
            
            // 税率別に集計（税率なしの場合は'no_tax_rate'として扱う）
            const taxRateKey = taxRate !== null ? taxRate.toString() : 'no_tax_rate';
            if (!costTaxRateGroups[taxRateKey]) {
                costTaxRateGroups[taxRateKey] = 0;
            }
            costTaxRateGroups[taxRateKey] += amount;
            
            // コスト項目は常に内税計算（仕入先の税区分に関係なく）
            // 税率が設定されている場合のみ税額を計算
            if (taxRate !== null) {
            const taxAmount = Math.ceil(amount * (taxRate / 100) / (1 + taxRate / 100));
            costTotalTaxAmount += taxAmount;
            }
        });

        // 請求項目合計を切り上げ
        const invoiceTotalCeiled = Math.ceil(invoiceTotal);

        // コスト項目合計を切り上げ
        const costTotalCeiled = Math.ceil(costTotal);

        // 消費税合計を切り上げ
        const totalTaxAmountCeiled = Math.ceil(totalTaxAmount);

        // コスト項目消費税合計を切り上げ
        const costTotalTaxAmountCeiled = Math.ceil(costTotalTaxAmount);

        // 税込合計を計算
        const totalWithTax = invoiceTotalCeiled + totalTaxAmountCeiled;

        // コスト項目税込合計を計算
        const costTotalWithTax = costTotalCeiled + costTotalTaxAmountCeiled;

        // 利益計算（PHP側の計算結果を使用）
        const profit = totalWithTax - costTotalWithTax; // JavaScript側での利益計算を無効化

        // 請求項目の合計表示を更新（税区分に応じて）
        const invoiceTotalDisplay = $('.invoice-items-total');
        if (invoiceTotalDisplay.length > 0) {
            if (taxCategory === '外税') {
                // 外税表示の場合：3行表示
                invoiceTotalDisplay.html('合計金額 : ' + invoiceTotalCeiled.toLocaleString() + '円');
                
                // 消費税表示を更新（税率別の内訳を表示）
                const taxDisplay = $('.invoice-items-tax');
                if (taxDisplay.length > 0) {
                    let taxDetailHtml = '消費税 : ' + totalTaxAmountCeiled.toLocaleString() + '円';
                    
                                    // 税率別の内訳を追加
                const taxRateDetails = [];
                Object.keys(taxRateGroups).sort((a, b) => {
                    // 税率なしを最後に表示
                    if (a === 'no_tax_rate') return 1;
                    if (b === 'no_tax_rate') return -1;
                    return parseFloat(b) - parseFloat(a);
                }).forEach(taxRateKey => {
                    if (taxRateKey === 'no_tax_rate') {
                        // 税率なしの場合は表示しない
                        return;
                    } else {
                        const rate = parseFloat(taxRateKey);
                        const groupAmount = taxRateGroups[taxRateKey];
                        const taxAmount = Math.ceil(groupAmount * (rate / 100));
                        if (groupAmount > 0) {
                            taxRateDetails.push(`${rate}%: ${taxAmount.toLocaleString()}円`);
                        }
                    }
                });
                    
                    if (taxRateDetails.length > 1) {
                        taxDetailHtml += ' (' + taxRateDetails.join(', ') + ')';
                    }
                    
                    taxDisplay.html(taxDetailHtml);
                }

                // 税込合計表示を更新
                const totalWithTaxDisplay = $('.invoice-items-total-with-tax');
                if (totalWithTaxDisplay.length > 0) {
                    totalWithTaxDisplay.html('税込合計 : ' + totalWithTax.toLocaleString() + '円');
                }
            } else {
                // 内税表示の場合：税率別の内訳を表示
                let totalDisplayHtml = '金額合計：' + invoiceTotalCeiled.toLocaleString() + '円';
                
                // 税率別の内訳を追加
                const taxRateDetails = [];
                Object.keys(taxRateGroups).sort((a, b) => {
                    // 税率なしを最後に表示
                    if (a === 'no_tax_rate') return 1;
                    if (b === 'no_tax_rate') return -1;
                    return parseFloat(b) - parseFloat(a);
                }).forEach(taxRateKey => {
                    if (taxRateKey === 'no_tax_rate') {
                        // 税率なしの場合は表示しない
                        return;
                    } else {
                        const rate = parseFloat(taxRateKey);
                        const groupAmount = taxRateGroups[taxRateKey];
                        const taxAmount = Math.ceil(groupAmount * (rate / 100) / (1 + rate / 100));
                        if (groupAmount > 0) {
                            taxRateDetails.push(`${rate}%: ${taxAmount.toLocaleString()}円`);
                        }
                    }
                });
                
                if (taxRateDetails.length > 0) {
                    totalDisplayHtml += '　（内税：' + taxRateDetails.join(', ') + '）';
                }
                
                invoiceTotalDisplay.html(totalDisplayHtml);
                
                // 消費税表示を非表示
                const taxDisplay = $('.invoice-items-tax');
                if (taxDisplay.length > 0) {
                    taxDisplay.html('');
                }

                // 税込合計表示を非表示
                const totalWithTaxDisplay = $('.invoice-items-total-with-tax');
                if (totalWithTaxDisplay.length > 0) {
                    totalWithTaxDisplay.html('');
                }
            }
        }

        // コスト項目の金額合計表示を即時更新（受注書＞コスト項目の入力時に即時反映）
        const costTotalDisplay = $('.cost-items-total');
        if (costTotalDisplay.length > 0) {
            const hideCostTax = window.ktp_tax_policy && (window.ktp_tax_policy.mode === 'abolished' || window.ktp_tax_policy.hide_tax_columns);
            if (hideCostTax) {
                costTotalDisplay.html('金額合計：' + costTotalCeiled.toLocaleString() + '円');
            } else {
                costTotalDisplay.html('金額合計：' + costTotalCeiled.toLocaleString() + '円　（内税：' + costTotalTaxAmountCeiled.toLocaleString() + '円）');
            }
            costTotalDisplay.show();
            const costTaxDisplay = $('.cost-items-tax');
            const costTotalWithTaxDisplay = $('.cost-items-total-with-tax');
            if (costTaxDisplay.length) costTaxDisplay.hide();
            if (costTotalWithTaxDisplay.length) costTotalWithTaxDisplay.hide();
        }

        // 利益表示を更新（PHP側の計算結果を使用）
        // const profitDisplay = $('.profit-display');
        // if (profitDisplay.length > 0) {
        //     const profitColor = profit >= 0 ? '#28a745' : '#dc3545';
        //     profitDisplay.html('利益 : ' + profit.toLocaleString() + '円');
        //     profitDisplay.css('color', profitColor);
        // 
        //     // CSSクラスを更新
        //     profitDisplay.removeClass('positive negative');
        //     profitDisplay.addClass(profit >= 0 ? 'positive' : 'negative');
        // }

        // 利益表示（サーバー側の最新HTMLに置換）
        try {
            const orderId = $('input[name="order_id"]').val() || $('#order_id').val();
            if (orderId) {
                // 請求テーブル側での入力は高頻度なのでデバウンス
                if (!window.ktpInvoiceProfitRefreshTimer) {
                    window.ktpInvoiceProfitRefreshTimer = null;
                }
                clearTimeout(window.ktpInvoiceProfitRefreshTimer);
                window.ktpInvoiceProfitRefreshTimer = setTimeout(function() {
                    // 統一されたAJAX設定を取得
                    const ajaxConfig = getAjaxConfig();
                    $.ajax({
                        url: ajaxConfig.url,
                        type: 'POST',
                        data: {
                            action: 'ktp_get_profit_display',
                            order_id: orderId,
                            nonce: ajaxConfig.nonce,
                            ktp_ajax_nonce: ajaxConfig.nonce
                        },
                        success: function(res) {
                            try {
                                const result = typeof res === 'string' ? JSON.parse(res) : res;
                                if (result && result.success && result.data && result.data.html) {
                                    const $container = $('.profit-display').parent();
                                    if ($container && $container.length) {
                                        // 既存のprofit-displayを差し替え
                                        $('.profit-display').replaceWith(result.data.html);
                                    }
                                }
                            } catch (e) {
                                if (window.ktpDebugMode) console.warn('[INVOICE] 利益HTMLパース失敗', e, res);
                            }
                        },
                        error: function(xhr, status, error) {
                            if (window.ktpDebugMode) console.warn('[INVOICE] 利益HTML取得エラー', {status, error});
                        }
                    });
                }, 250);
            }
        } catch (e) {
            if (window.ktpDebugMode) console.warn('[INVOICE] 利益HTML更新処理エラー', e);
        }

        // デバッグログ（利益計算の詳細）
        if (window.ktpDebugMode) {
            console.log('[INVOICE] 利益計算詳細:', {
                invoiceTotal: invoiceTotal,
                invoiceTotalCeiled: invoiceTotalCeiled,
                costTotal: costTotal,
                costTotalCeiled: costTotalCeiled,
                totalTaxAmount: totalTaxAmount,
                totalTaxAmountCeiled: totalTaxAmountCeiled,
                costTotalTaxAmount: costTotalTaxAmount,
                costTotalTaxAmountCeiled: costTotalTaxAmountCeiled,
                totalWithTax: totalWithTax,
                costTotalWithTax: costTotalWithTax,
                profit: profit,
                taxCategory: taxCategory
            });
        }

        // デバッグログ（税率別の集計情報）
        if (window.ktpDebugMode) {
            console.log('[INVOICE] 税率別集計:', {
                taxCategory: taxCategory,
                invoiceTaxRateGroups: taxRateGroups,
                costTaxRateGroups: costTaxRateGroups,
                totalTaxAmount: totalTaxAmount,
                totalTaxAmountCeiled: totalTaxAmountCeiled
            });
        }
    }

    // コスト項目からの即時計算用にグローバルに露出（受注書＞コスト項目の金額合計・利益を即時更新）
    window.updateTotalAndProfit = updateTotalAndProfit;

    // 新しい行を追加（重複防止機能付き）
    function addNewRow(currentRow, callId) { // callId を追加
        if (window.ktpDebugMode) console.log(`[INVOICE][${callId}] addNewRow開始 (呼び出し元ID: ${callId})`);

        // 品名チェック (addNewRow関数側でも念のため)
        let rawProductName = currentRow.find('input.product-name').val();
        if (typeof rawProductName !== 'string') {
            rawProductName = currentRow.find('input[name$="[product_name]"]').val();
        }
        // const productName = (typeof rawProductName === 'string') ? rawProductName.trim() : '';
        // 修正: addNewRow内の品名チェックは、呼び出し元で既に行われているため、ここではログ出力のみに留めるか、
        // もし再度チェックするなら、その結果に基づいて早期リターンする。
        // 今回は呼び出し元を信頼し、ここではチェックを簡略化または削除の方向で検討したが、まずはログで状況把握
        const productNameValue = (typeof rawProductName === 'string') ? rawProductName.trim() : '';
        if (productNameValue === '') {
            // alert('品名を入力してください。(addNewRow)'); // クリックハンドラでアラートを出すので、ここでは不要
            if (window.ktpDebugMode) console.warn(`[INVOICE][${callId}] addNewRow: 品名が空の状態で呼び出されましたが、処理を続行します（本来はクリックハンドラでブロックされるべきです）。`);
            // return false; // ここで return false すると、クリックハンドラの品名チェックが機能していない場合に二重チェックになる
                          // ただし、現状問題が解決していないため、ここでも止めることを検討したが、まずはログで状況把握
        }

        if (window.ktpDebugMode) console.log(`[INVOICE][${callId}] addNewRow 本処理開始`);
        // フラグ管理はクリックハンドラに集約

        const newIndex = $('#order_content .invoice-items-table tbody tr').length;
        const newRowHtml = `
            <tr class="invoice-item-row" data-row-id="0" data-newly-added="true">
                <td class="actions-column">
                    <span class="drag-handle" title="ドラッグして並び替え">&#9776;</span><button type="button" class="btn-add-row" title="行を追加">+</button><button type="button" class="btn-delete-row" title="行を削除">×</button><button type="button" class="btn-move-row" title="サービス選択">></button>
                </td>
                <td>
                    <input type="text" name="invoice_items[${newIndex}][product_name]" class="invoice-item-input product-name" value="">
                    <input type="hidden" name="invoice_items[${newIndex}][id]" value="0">
                </td>
                <td style="text-align:left;">
                    <input type="number" name="invoice_items[${newIndex}][price]" class="invoice-item-input price" value="0" step="1" min="0" style="text-align:left;" disabled>
                </td>
                <td style="text-align:left;">
                    <input type="number" name="invoice_items[${newIndex}][quantity]" class="invoice-item-input quantity" value="1" step="1" min="0" style="text-align:left;" disabled>
                </td>
                <td>
                    <input type="text" name="invoice_items[${newIndex}][unit]" class="invoice-item-input unit" value="式" disabled>
                </td>
                <td style="text-align:left;">
                    <span class="invoice-item-amount" data-amount="0" style="display:inline-block;min-width:80px;text-align:left;">0</span>
                    <input type="hidden" name="invoice_items[${newIndex}][amount]" value="0">
                </td>
                <td style="text-align:left;">
                    <input type="number" name="invoice_items[${newIndex}][tax_rate]" class="invoice-item-input tax-rate" value="10" step="1" min="0" max="100" style="width: 50px; max-width: 60px; text-align: right !important;"> %
                </td>
                <td>
                    <input type="text" name="invoice_items[${newIndex}][remarks]" class="invoice-item-input remarks" value="" disabled>
                    <input type="hidden" name="invoice_items[${newIndex}][sort_order]" value="${newIndex + 1}">
                </td>
            </tr>
        `;

        let success = false;
        try {
            if (window.ktpDebugMode) console.log(`[INVOICE][${callId}] currentRow.after(newRowHtml) を実行する直前。`);
            currentRow.after(newRowHtml);
            const $newRow = currentRow.next();
            if ($newRow && $newRow.length > 0 && $newRow.hasClass('invoice-item-row')) {
                if (window.ktpDebugMode) console.log(`[INVOICE][${callId}] 新しい行がDOMに追加されました。`);
                
                // 新しい行で金額の自動計算を実行
                calculateAmount($newRow);
                
                $newRow.find('.product-name').focus();
                success = true;
            } else {
                if (window.ktpDebugMode) console.error(`[INVOICE][${callId}] 新しい行の追加に失敗したか、見つかりませんでした。`);
                success = false;
            }
        } catch (error) {
            if (window.ktpDebugMode) console.error(`[INVOICE][${callId}] addNewRow エラー:`, error);
            success = false;
        } finally {
            // window.ktpAddingInvoiceRow = false; // フラグ解除は呼び出し元の finally で
            if (window.ktpDebugMode) console.log(`[INVOICE][${callId}] addNewRow終了`);
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

            if (window.ktpDebugMode) console.log('[INVOICE] deleteRow呼び出し', { itemId, orderId, row: currentRow });

            // Ajaxでサーバーに削除を通知
            if (itemId && itemId !== '0' && orderId) {
                // 統一されたAJAX設定を取得
                const ajaxConfig = getAjaxConfig();

                const ajaxData = {
                    action: 'ktp_delete_item',
                    item_type: 'invoice',
                    item_id: itemId,
                    order_id: orderId,
                    nonce: ajaxConfig.nonce,
                    ktp_ajax_nonce: ajaxConfig.nonce  // 追加: PHPでチェックされるフィールド名
                };
                if (window.ktpDebugMode) console.log('[INVOICE] deleteRow送信', ajaxData);
                $.ajax({
                    url: ajaxConfig.url,
                    type: 'POST',
                    data: ajaxData,
                    success: function (response) {
                        if (window.ktpDebugMode) console.log('[INVOICE] deleteRowレスポンス', response);
                        try {
                            const result = typeof response === 'string' ? JSON.parse(response) : response;
                            if (result.success) {
                                if (window.ktpDebugMode) console.log('[INVOICE] deleteRowサーバー側削除成功');
                                currentRow.remove();
                                updateTotalAndProfit(); // 合計金額を更新
                            } else {
                                if (window.ktpDebugMode) console.warn('[INVOICE] deleteRowサーバー側削除失敗', result);
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
                            if (window.ktpDebugMode) console.error('[INVOICE] deleteRowレスポンスパースエラー', e, response);
                            alert('行削除の応答処理中にエラーが発生しました。\n詳細: ' + (typeof response === 'string' ? response : JSON.stringify(response)));
                        }
                    },
                    error: function (xhr, status, error) {
                        if (window.ktpDebugMode) console.error('[INVOICE] deleteRowエラー', { status, error, responseText: xhr.responseText, statusCode: xhr.status });
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
                if (window.ktpDebugMode) console.log('[INVOICE] deleteRow: サーバー未保存行のため即時削除');
                currentRow.remove();
                updateTotalAndProfit(); // 合計金額を更新
            } else {
                // itemIdがない、またはorderIdがない場合は、クライアント側でのみ削除（通常は発生しないはず）
                if (window.ktpDebugMode) console.warn('[INVOICE] deleteRow: itemIdまたはorderIdが不足しているため、クライアント側でのみ削除');
                currentRow.remove();
                updateTotalAndProfit(); // 合計金額を更新
            }
        }
    }

    // 行のインデックスを更新
    function updateRowIndexes(table) {
        const tbody = table.find('tbody');
        tbody.find('tr').each(function (index) {
            const row = $(this);
            row.find('input, textarea').each(function () {
                const input = $(this);
                const name = input.attr('name');
                if (name && name.match(/^invoice_items\[\d+\]/)) {
                    // 先頭の [数字] 部分だけを置換
                    const newName = name.replace(/^invoice_items\[\d+\]/, `invoice_items[${index}]`);
                    input.attr('name', newName);
                }
            });
        });
    }

    // 自動追加機能を無効化（[+]ボタンのみで行追加）
    function checkAutoAddRow(currentRow) {
        // 自動追加機能を無効化
        // [+]ボタンクリック時のみ行を追加する仕様に変更
        return;
    }

    // ページ読み込み完了時の初期化
    $(document).ready(function () {
        // 受注書テーブルが DOM に無ければ、以降の全 document 委譲・sortable 初期化・ループをスキップ
        // （サービス／協力会社／顧客等のタブでは不要。無駄な DOM 走査やイベント委譲を避ける）
        if ($('.invoice-items-table').length === 0) {
            return;
        }

        if (window.ktpDebugMode) console.log('[INVOICE] 📋 ページ初期化開始');

        // デバッグモードを有効化（金額計算・保存の詳細ログを表示）
        window.ktpDebugMode = true;
        if (window.ktpDebugMode) console.log('[INVOICE] デバッグモード有効化: 金額計算・保存の詳細ログを表示します');

        // 初期状態の確認
        const initialRowCount = $('#order_content .invoice-items-table tbody tr').length;
        if (window.ktpDebugMode) console.log('[INVOICE] 📊 初期行数:', initialRowCount);

        // 並び替え（sortable）有効化（受注書タブにテーブルがあるときのみ。空の tbody に対しては初期化しない）
        const $invoiceSortTbody = $('#order_content .invoice-items-table tbody');
        if ($invoiceSortTbody.length) {
        $invoiceSortTbody.sortable({
            handle: '.drag-handle',
            items: '> tr',
            axis: 'y',
            helper: 'clone',
            update: function (event, ui) {
                if (window.ktpDebugMode) console.log('[INVOICE] 行の並び替え完了');
                const items = [];
                const orderId = $('input[name="order_id"]').val() || $('#order_id').val();
                $(this).find('tr').each(function (index) {
                    const itemId = $(this).find('input[name*="[id]"]').val();
                    if (itemId && itemId !== '0') { // Ensure itemId is valid
                        items.push({ id: itemId, sort_order: index + 1 });
                    }
                });

                if (items.length > 0 && orderId) {
                    // 統一されたAJAX設定を取得
                    const ajaxConfig = getAjaxConfig();
                    
                    if (window.ktpDebugMode) console.log('[INVOICE] 使用するnonce:', ajaxConfig.nonce);

                    if (window.ktpDebugMode) console.log('[INVOICE] updateItemOrder送信', { order_id: orderId, items: items });
                    $.ajax({
                        url: ajaxConfig.url,
                        type: 'POST',
                        data: {
                            action: 'ktp_update_item_order',
                            order_id: orderId,
                            items: items,
                            item_type: 'invoice', // Assuming this is for invoice items
                            nonce: ajaxConfig.nonce,
                            ktp_ajax_nonce: ajaxConfig.nonce  // 追加: PHPでチェックされるフィールド名
                        },
                        success: function (response) {
                            if (window.ktpDebugMode) console.log('[INVOICE] updateItemOrderレスポンス', response);
                            try {
                                const result = typeof response === 'string' ? JSON.parse(response) : response;
                                if (result.success) {
                                    if (window.ktpDebugMode) console.log('[INVOICE] 並び順の保存に成功しました。');
                                    // Optionally, re-index rows if your display depends on it,
                                    // but it seems your PHP handles sort_order directly.
                                    // updateRowIndexes($(event.target).closest('table'));
                                } else {
                                    if (window.ktpDebugMode) console.warn('[INVOICE] 並び順の保存に失敗しました。', result);
                                    alert('並び順の保存に失敗しました。: ' + (result.data && result.data.message ? result.data.message : 'サーバーエラー'));
                                }
                            } catch (e) {
                                if (window.ktpDebugMode) console.error('[INVOICE] updateItemOrderレスポンスパースエラー', e, response);
                                alert('並び順保存の応答処理中にエラーが発生しました。');
                            }
                        },
                        error: function (xhr, status, error) {
                            if (window.ktpDebugMode) console.error('[INVOICE] updateItemOrderエラー', { status, error, responseText: xhr.responseText });
                            alert('並び順の保存中にサーバーエラーが発生しました。');
                        }
                    });
                } else {
                    if (window.ktpDebugMode) console.log('[INVOICE] 保存するアイテムがないか、orderIdがありません。');
                }
            },
            start: function (event, ui) {
                ui.item.addClass('dragging');
            },
            stop: function (event, ui) {
                ui.item.removeClass('dragging');
            }
        }).disableSelection();
        }

        // 価格・数量変更時の金額自動計算（blurイベントでのみ実行）
        $(document).on('blur', '#order_content .invoice-items-table .price, #order_content .invoice-items-table .quantity', function () {
            const $field = $(this);
            
            // disabled フィールドは処理をスキップ
            if ($field.prop('disabled')) {
                if (window.ktpDebugMode) {
                    if (window.ktpDebugMode) console.log('[INVOICE] Blur event skipped: field is disabled');
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
                if (window.ktpDebugMode) console.log('[INVOICE] Blur event triggered:', {
                    fieldType: fieldType,
                    originalValue: value,
                    formattedValue: formattedValue,
                    rowIndex: row.index()
                });
            }
            
            calculateAmount(row);
        });

        // スピンアップ・ダウンイベントの処理
        $(document).on('input', '#order_content .invoice-items-table .price, #order_content .invoice-items-table .quantity', function () {
            const $field = $(this);
            
            // disabled フィールドは処理をスキップ
            if ($field.prop('disabled')) {
                return;
            }
            
            const value = $field.val();
            const row = $field.closest('tr');
            const fieldType = $field.hasClass('price') ? 'price' : 'quantity';
            
            if (window.ktpDebugMode) {
                if (window.ktpDebugMode) console.log('[INVOICE] Input event triggered (spin):', {
                    fieldType: fieldType,
                    value: value,
                    rowIndex: row.index()
                });
            }
            
            // スピンイベントの場合は即座に金額計算を実行
            calculateAmount(row);
        });

        // スピンアップ・ダウンイベントの専用処理（changeイベント）
        $(document).on('change', '#order_content .invoice-items-table .price, #order_content .invoice-items-table .quantity', function () {
            const $field = $(this);
            
            // disabled フィールドは処理をスキップ
            if ($field.prop('disabled')) {
                return;
            }
            
            const value = $field.val();
            const row = $field.closest('tr');
            const fieldType = $field.hasClass('price') ? 'price' : 'quantity';
            
            if (window.ktpDebugMode) {
                if (window.ktpDebugMode) console.log('[INVOICE] Change event triggered (spin):', {
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

        // 税率変更時のリアルタイム再計算
        $(document).on('change', '#order_content .invoice-items-table .tax-rate', function () {
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
                console.log('[INVOICE] 税率変更イベント:', {
                    value: value,
                    rowIndex: row.index(),
                    itemId: itemId,
                    orderId: orderId
                });
            }
            
            // 税率を自動保存
            if (itemId && itemId !== '0' && orderId) {
                window.ktpInvoiceAutoSaveItem('invoice', itemId, 'tax_rate', value, orderId);
            }
            
            // 合計と利益を再計算
            updateTotalAndProfit();
        });

        // 税率入力時のリアルタイム再計算（inputイベント）
        $(document).on('input', '#order_content .invoice-items-table .tax-rate', function () {
            const $field = $(this);
            
            // disabled フィールドは処理をスキップ
            if ($field.prop('disabled')) {
                return;
            }
            
            const value = $field.val();
            const row = $field.closest('tr');
            
            if (window.ktpDebugMode) {
                console.log('[INVOICE] 税率入力イベント:', {
                    value: value,
                    rowIndex: row.index()
                });
            }
            
            // 入力中でも合計と利益を再計算（リアルタイム表示）
            updateTotalAndProfit();
        });

        // 自動追加機能を無効化（コメントアウト）
        // $(document).on('input', '#order_content .invoice-items-table .product-name, #order_content .invoice-items-table .price, #order_content .invoice-items-table .quantity', function() {
        //     const row = $(this).closest('tr');
        //     const tbody = row.closest('tbody');
        //     const isFirstRow = tbody.find('tr').first().is(row);
        //
        //     if (isFirstRow) {
        //         checkAutoAddRow(row);
        //     }
        // });

        // [+]ボタンで行追加
        // 既存のハンドラを解除してから登録
        $(document).off('click.ktpInvoiceAdd', '#order_content .invoice-items-table .btn-add-row');
        $('body').off('click.ktpInvoiceAdd', '#order_content .invoice-items-table .btn-add-row');
        $('#order_content .invoice-items-table').off('click.ktpInvoiceAdd', '.btn-add-row');

        // より強力な解除（名前空間なしも試す）
        $(document).off('click', '#order_content .invoice-items-table .btn-add-row');
        $('body').off('click', '#order_content .invoice-items-table .btn-add-row');
        $('#order_content .invoice-items-table').off('click', '.btn-add-row');


        $(document).on('click.ktpInvoiceAdd', '#order_content .invoice-items-table .btn-add-row', function (e) {
            const clickId = Date.now();
            if (window.ktpDebugMode) console.log(`[INVOICE][${clickId}] +ボタンクリックイベント発生 (ktpInvoiceAdd)`);

            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();

            const $button = $(this);
            const currentRow = $button.closest('tr');

            let rawProductNameCH = currentRow.find('input.product-name').val();
            if (typeof rawProductNameCH !== 'string') {
                rawProductNameCH = currentRow.find('input[name$="[product_name]"]').val();
            }
            const productNameValueCH = (typeof rawProductNameCH === 'string') ? rawProductNameCH.trim() : '';

            if (productNameValueCH === '') {
                alert('品名を入力してください。');
                if (window.ktpDebugMode) console.log(`[INVOICE][${clickId}] クリックハンドラ: 品名未入力。 addNewRow を呼び出さずに処理を中断します。これがこのハンドラの最後のログになるはずです。`);
                return false;
            }

            if (window.ktpDebugMode) console.log(`[INVOICE][${clickId}] クリックハンドラ: 品名入力済み。ktpAddingInvoiceRow の状態 (呼び出し前):`, window.ktpAddingInvoiceRow);

            if ($button.prop('disabled') || $button.hasClass('processing')) {
                if (window.ktpDebugMode) console.log(`[INVOICE][${clickId}] ボタンが無効または処理中のためスキップ`);
                return false;
            }

            if (window.ktpAddingInvoiceRow === true) {
                if (window.ktpDebugMode) console.log(`[INVOICE][${clickId}] 既に処理中のため中止 (ktpAddingInvoiceRow is true)`);
                return false;
            }

            $button.prop('disabled', true).addClass('processing');
            window.ktpAddingInvoiceRow = true;
            if (window.ktpDebugMode) console.log(`[INVOICE][${clickId}] +ボタン処理開始、ボタン無効化、ktpAddingInvoiceRow を true に設定`);

            let rowAddedSuccessfully = false;
            try {
                if (window.ktpDebugMode) console.log(`[INVOICE][${clickId}] addNewRow を呼び出します。`);
                rowAddedSuccessfully = addNewRow(currentRow, clickId); // clickId を渡す
                if (window.ktpDebugMode) console.log(`[INVOICE][${clickId}] addNewRow の呼び出し結果:`, rowAddedSuccessfully);

                if (!rowAddedSuccessfully) {
                    if (window.ktpDebugMode) console.warn(`[INVOICE][${clickId}] addNewRow が false を返しました。`);
                } else {
                    if (window.ktpDebugMode) console.log(`[INVOICE][${clickId}] addNewRow が true を返しました。`);
                }
            } catch (error) {
                if (window.ktpDebugMode) console.error(`[INVOICE][${clickId}] addNewRow 呼び出し中またはその前後でエラー:`, error);
                rowAddedSuccessfully = false;
            } finally {
                window.ktpAddingInvoiceRow = false;
                $button.prop('disabled', false).removeClass('processing');
                if (window.ktpDebugMode) console.log(`[INVOICE][${clickId}] ボタン再有効化、ktpAddingInvoiceRow を false に設定 (finally)`);
            }
            if (window.ktpDebugMode) console.log(`[INVOICE][${clickId}] クリックハンドラの末尾。`);
            return false;
        });

        // 行削除ボタン - イベント重複を防ぐ
        $(document).off('click.ktpInvoiceDelete', '#order_content .invoice-items-table .btn-delete-row') // 名前空間付きイベントに変更
            .on('click.ktpInvoiceDelete', '#order_content .invoice-items-table .btn-delete-row', function (e) {
                e.preventDefault();
                e.stopPropagation();
                const currentRow = $(this).closest('tr');
                if (window.ktpDebugMode) console.log('[INVOICE] 削除ボタンクリック', currentRow);
                deleteRow(currentRow);
            });

        // 行移動ボタン（サービス選択機能）- 請求項目テーブル専用
        $(document).on('click', '#order_content .invoice-items-table .btn-move-row', function (e) {
            e.preventDefault();
            e.stopPropagation();
            if (window.ktpDebugMode) console.log('[INVOICE-ITEMS] [>]ボタンクリック - サービス選択開始');
            if (window.ktpDebugMode) console.log('[INVOICE-ITEMS] クリックされた要素:', this);
            if (window.ktpDebugMode) console.log('[INVOICE-ITEMS] 要素のクラス:', $(this).attr('class'));
            
            // サービス選択ポップアップを表示
            const currentRow = $(this).closest('tr');
            if (window.ktpDebugMode) console.log('[INVOICE-ITEMS] currentRow:', currentRow);
            
            // ktpShowServiceSelector関数の存在確認
            if (window.ktpDebugMode) console.log('[INVOICE-ITEMS] ktpShowServiceSelector関数の存在確認:', typeof window.ktpShowServiceSelector);
            if (window.ktpDebugMode) console.log('[INVOICE-ITEMS] window.ktpShowServiceSelector:', window.ktpShowServiceSelector);
            
            if (typeof window.ktpShowServiceSelector === 'function') {
                if (window.ktpDebugMode) console.log('[INVOICE-ITEMS] ktpShowServiceSelector関数を呼び出し');
                try {
                    window.ktpShowServiceSelector(currentRow);
                    if (window.ktpDebugMode) console.log('[INVOICE-ITEMS] ktpShowServiceSelector関数呼び出し完了');
                } catch (error) {
                    if (window.ktpDebugMode) console.error('[INVOICE-ITEMS] ktpShowServiceSelector関数呼び出しエラー:', error);
                }
            } else {
                if (window.ktpDebugMode) console.error('[INVOICE-ITEMS] ktpShowServiceSelector関数が見つかりません');
                if (window.ktpDebugMode) console.error('[INVOICE-ITEMS] 利用可能なwindow関数:', Object.keys(window).filter(key => key.includes('ktp')));
                alert('サービス選択機能の読み込みに失敗しました。ページを再読み込みしてください。');
            }
        });

        // フォーカス時の入力欄スタイル調整
        $(document).on('focus', '#order_content .invoice-item-input', function () {
            $(this).addClass('focused');
            // 数値入力フィールドの場合、フォーカス時に全選択
            if ($(this).attr('type') === 'number') {
                $(this).select();
            }
        });

        $(document).on('blur', '#order_content .invoice-item-input', function () {
            $(this).removeClass('focused');
        });

        // 商品名フィールドのblurイベントで自動保存
        $(document).on('blur', '#order_content .invoice-item-input.product-name', function () {
            const $field = $(this);
            const productName = $field.val();
            const $row = $field.closest('tr');
            let itemId = $row.find('input[name*="[id]"]').val();
            const orderId = $('input[name="order_id"]').val() || $('#order_id').val();

            if (window.ktpDebugMode) {
                // Debug mode only if explicitly enabled
            }

            if (orderId) {
                // 変更点: itemId === '' も新規行扱いにする
                if (itemId === '0' || itemId === '' || $row.data('newly-added')) {
                    // 新規行の場合：新しいレコードを作成
                    // 変更点: productName が空でなく、実際に何か入力された場合のみ createNewItem を呼び出す
                    if (productName.trim() !== '') {
                        window.ktpInvoiceCreateNewItem('invoice', 'product_name', productName, orderId, $row, function(success, newItemId) {
                            if (success && newItemId) {
                                $row.find('input[name*="[id]"]').val(newItemId);
                                $row.data('pending-initial-creation', false); // フラグを解除
                                // 他のフィールドがまだ無効なら有効化 (createNewItemのコールバックで処理されるはずだが念のため)
                                if ($row.find('.price').prop('disabled')) {
                                    $row.find('.invoice-item-input').not('.product-name').not('.amount').prop('disabled', false);
                                    if (window.ktpDebugMode) console.log('[INVOICE] product-name blur: 他のフィールドを有効化（再確認）', $row);
                                }
                                // 単価フィールドにフォーカスを移動
                                if ($row.find('.price').prop('disabled') === false) {
                                    $row.find('.price').focus();
                                }
                                if (window.ktpDebugMode) console.log('[INVOICE] product-name blur: createNewItem成功後、ID:', newItemId, 'pending-initial-creation:', $row.data('pending-initial-creation'));
                            } else {
                                if (window.ktpDebugMode) console.warn('[INVOICE] product-name blur: createNewItem失敗');
                            }
                        });
                    } else if ($row.data('newly-added') || itemId === '' || itemId === '0') { // 条件を明確化
                        // 商品名が空のままフォーカスが外れた新規行の場合の処理
                        if (window.ktpDebugMode) {
                            if (window.ktpDebugMode) console.log('Invoice product name is empty on blur for new/template row. Item not created/saved.', {row: $row[0].outerHTML, itemId: itemId});
                        }
                    }
                } else {
                    // 既存行の場合：商品名を自動保存
                    window.ktpInvoiceAutoSaveItem('invoice', itemId, 'product_name', productName, orderId);
                }
            } else {
                if (window.ktpDebugMode) {
                    if (window.ktpDebugMode) console.warn('Order ID is missing. Cannot auto-save product name.');
                }
            }
        });
        // 単価フィールドのblurイベントで自動保存
        $(document).on('blur', '#order_content .invoice-item-input.price', function () {
            const $field = $(this);
            const price = $field.val();
            const $row = $field.closest('tr');
            const itemId = $row.find('input[name*="[id]"]').val();
            const orderId = $('input[name="order_id"]').val() || $('#order_id').val();
            // 金額を再計算
            calculateAmount($row);
            // item_idが0でなく、かつ空でない場合に保存
            if (orderId && itemId && itemId !== '0') {
                if (window.ktpDebugMode) console.log('[INVOICE] blur: price - 既存更新/新規作成後', { price, itemId, orderId });
                window.ktpInvoiceAutoSaveItem('invoice', itemId, 'price', price, orderId);
            } else if (itemId === '0') {
                if (window.ktpDebugMode) console.log('[INVOICE] blur: price - item_idが0のため保存スキップ。product_nameの入力/保存待ち。');
            }
        });
        // 数量フィールドのblurイベントで自動保存
        $(document).on('blur', '#order_content .invoice-item-input.quantity', function () {
            const $field = $(this);
            const quantity = $field.val();
            const $row = $field.closest('tr');
            const itemId = $row.find('input[name*="[id]"]').val();
            const orderId = $('input[name="order_id"]').val() || $('#order_id').val();
            // 金額を再計算
            calculateAmount($row);
            if (orderId && itemId && itemId !== '0') {
                if (window.ktpDebugMode) console.log('[INVOICE] blur: quantity - 既存更新/新規作成後', { quantity, itemId, orderId });
                window.ktpInvoiceAutoSaveItem('invoice', itemId, 'quantity', quantity, orderId);
            } else if (itemId === '0') {
                if (window.ktpDebugMode) console.log('[INVOICE] blur: quantity - item_idが0のため保存スキップ。product_nameの入力/保存待ち。');
            }
        });
        // 備考フィールドのblurイベントで自動保存
        $(document).on('blur', '#order_content .invoice-item-input.remarks', function () {
            const $field = $(this);
            const remarks = $field.val();
            const $row = $field.closest('tr');
            const itemId = $row.find('input[name*="[id]"]').val();
            const orderId = $('input[name="order_id"]').val() || $('#order_id').val();
            if (orderId && itemId && itemId !== '0') {
                if (window.ktpDebugMode) console.log('[INVOICE] blur: remarks - 既存更新/新規作成後', { remarks, itemId, orderId });
                window.ktpInvoiceAutoSaveItem('invoice', itemId, 'remarks', remarks, orderId);
            } else if (itemId === '0') {
                if (window.ktpDebugMode) console.log('[INVOICE] blur: remarks - item_idが0のため保存スキップ。product_nameの入力/保存待ち。');
            }
        });
        // ユニットフィールドのblurイベントで自動保存
        $(document).on('blur', '#order_content .invoice-item-input.unit', function () {
            const $field = $(this);
            const unit = $field.val();
            const $row = $field.closest('tr');
            const itemId = $row.find('input[name*="[id]"]').val();
            const orderId = $('input[name="order_id"]').val() || $('#order_id').val();
            if (orderId && itemId && itemId !== '0') {
                if (window.ktpDebugMode) console.log('[INVOICE] blur: unit - 既存更新/新規作成後', { unit, itemId, orderId });
                window.ktpInvoiceAutoSaveItem('invoice', itemId, 'unit', unit, orderId);
            } else if (itemId === '0') {
                if (window.ktpDebugMode) console.log('[INVOICE] blur: unit - item_idが0のため保存スキップ。product_nameの入力/保存待ち。');
            }
        });

        // 税率フィールドのblurイベントで自動保存
        $(document).on('blur', '#order_content .invoice-item-input.tax-rate', function () {
            const $field = $(this);
            const taxRate = $field.val();
            const $row = $field.closest('tr');
            const itemId = $row.find('input[name*="[id]"]').val();
            const orderId = $('input[name="order_id"]').val() || $('#order_id').val();
            
            if (window.ktpDebugMode) console.log('[INVOICE] 税率フィールドblurイベント発火:', {
                taxRate: taxRate,
                itemId: itemId,
                orderId: orderId,
                fieldElement: $field[0],
                rowElement: $row[0]
            });
            
            if (orderId && itemId && itemId !== '0') {
                if (window.ktpDebugMode) console.log('[INVOICE] blur: tax_rate - 既存更新/新規作成後', { taxRate, itemId, orderId });
                if (window.ktpDebugMode) console.log('[INVOICE] ktpInvoiceAutoSaveItem呼び出し開始');
                window.ktpInvoiceAutoSaveItem('invoice', itemId, 'tax_rate', taxRate, orderId);
                if (window.ktpDebugMode) console.log('[INVOICE] ktpInvoiceAutoSaveItem呼び出し完了');
            } else if (itemId === '0') {
                if (window.ktpDebugMode) console.log('[INVOICE] blur: tax_rate - item_idが0のため保存スキップ。product_nameの入力/保存待ち。');
            } else {
                if (window.ktpDebugMode) console.warn('[INVOICE] blur: tax_rate - 保存条件未満', {
                    orderId: orderId,
                    itemId: itemId,
                    hasOrderId: !!orderId,
                    hasItemId: !!itemId,
                    itemIdNotZero: itemId !== '0'
                });
            }
        });

        // 税率フィールドのinputイベントでリアルタイム計算
        $(document).on('input', '#order_content .invoice-item-input.tax-rate', function () {
            const $field = $(this);
            const $row = $field.closest('tr');
            
            if (window.ktpDebugMode) console.log('[INVOICE] 税率フィールドinputイベント発火:', {
                taxRate: $field.val(),
                rowIndex: $row.index()
            });
            
            // リアルタイムで合計金額、消費税、税込合計を再計算
            updateTotalAndProfit();
        });

        // 初期状態で既存の行に対して金額計算を実行
        $('#order_content .invoice-items-table tbody tr').each(function () {
            calculateAmount($(this));
        });

        // フォーム送信時にtr順でname属性indexを再構成
        $(document).on('submit', '#order_content .invoice-items-form', function(e) {
            const $form = $(this);
            const $table = $form.find('.invoice-items-table');
            if ($table.length > 0) {
                updateRowIndexes($table); // tr順でname属性indexを再構成
            }
            // ここでtr順とname属性indexが必ず一致する
        });
    });

    // デバッグ用関数をグローバルスコープに追加
    window.testInvoiceItemsDebug = function () {
        if (window.ktpDebugMode) console.log('=== インボイス項目デバッグ ===');

        const tbody = $('#order_content .invoice-items-table tbody');
        if (tbody.length === 0) {
            if (window.ktpDebugMode) console.log('インボイステーブルが見つかりません');
            return;
        }

        const rows = tbody.find('tr');
        if (window.ktpDebugMode) console.log('現在の行数:', rows.length);

        const indexes = [];
        rows.each(function (i) {
            const $row = $(this);
            const $nameInput = $row.find('input[name*="[product_name]"]');
            if ($nameInput.length > 0) {
                const name = $nameInput.attr('name');
                const match = name.match(/invoice_items\[(\d+)\]/);
                if (match) {
                    const index = parseInt(match[1], 10);
                    indexes.push(index);
                    if (window.ktpDebugMode) console.log(`行${i + 1}: インデックス=${index}, 商品名="${$nameInput.val()}"`);
                }
            }
        });

        if (window.ktpDebugMode) console.log('使用中のインデックス:', indexes.sort((a, b) => a - b));
        if (window.ktpDebugMode) console.log('最大インデックス:', Math.max(...indexes));
        if (window.ktpDebugMode) console.log('次のインデックス:', Math.max(...indexes) + 1);

        // フラグ状態をチェック
        if (window.ktpDebugMode) console.log('フラグ状態:', {
            ktpAddingRow: window.ktpAddingRow,
            tableProcessing: $('#order_content .invoice-items-table').hasClass('processing-add'),
            processingButtons: $('.btn-add-row.processing').length
        });
    };

    // 行カウンター機能
    window.countInvoiceRows = function () {
        const count = $('#order_content .invoice-items-table tbody tr').length;
        if (window.ktpDebugMode) console.log('[INVOICE] 現在の行数:', count);
        return count;
    };

    // 強化されたリアルタイム監視機能
    window.monitorInvoiceRows = function () {
        if (window.ktpDebugMode) console.log('[INVOICE MONITOR] 現在の状況監視開始');

        const observer = new MutationObserver(function (mutations) {
            mutations.forEach(function (mutation) {
                if (mutation.type === 'childList') {
                    const addedNodes = Array.from(mutation.addedNodes);
                    const addedRows = addedNodes.filter(node =>
                        node.nodeType === 1 &&
                        node.classList &&
                        node.classList.contains('invoice-item-row')
                    );

                    if (addedRows.length > 0) {
                        if (window.ktpDebugMode) console.warn('[INVOICE MONITOR] 行追加検出:', addedRows.length, '行');

                        // 即座に重複チェック
                        const duplicates = window.detectDuplicateRows();
                        if (duplicates.length > 0) {
                            if (window.ktpDebugMode) console.error('[INVOICE MONITOR] 重複行検出 - 緊急対応が必要');
                            // 重複行を削除
                            addedRows.forEach((row, index) => {
                                if (index > 0) { // 最初の行以外を削除
                                    if (window.ktpDebugMode) console.warn('[INVOICE MONITOR] 重複行削除:', row);
                                    row.remove();
                                }
                            });
                        }
                    }
                }
            });
        });

        const tableBody = $('#order_content .invoice-items-table tbody')[0];
        if (tableBody) {
            observer.observe(tableBody, {
                childList: true,
                subtree: true
            });
            if (window.ktpDebugMode) console.log('[INVOICE MONITOR] DOM監視開始');
        }

        return observer;
    };

    // 緊急時の重複行削除機能
    window.emergencyCleanDuplicateRows = function () {
        if (window.ktpDebugMode) console.log('[INVOICE EMERGENCY] 緊急重複行削除開始');

        const rows = $('#order_content .invoice-items-table tbody tr');
        const indexMap = {};
        const duplicateRows = [];

        rows.each(function () {
            const $row = $(this);
            const nameInput = $row.find('input[name*="[product_name]"]');
            if (nameInput.length > 0) {
                const name = nameInput.attr('name');
                const match = name.match(/invoice_items\[(\d+)\]/);
                if (match) {
                    const index = parseInt(match[1], 10);
                    if (indexMap[index]) {
                        duplicateRows.push($row);
                        if (window.ktpDebugMode) console.warn('[INVOICE EMERGENCY] 重複行発見:', index);
                    } else {
                        indexMap[index] = $row;
                    }
                }
            }
        });

        // 重複行を削除
        duplicateRows.forEach(function ($row) {
            if (window.ktpDebugMode) console.warn('[INVOICE EMERGENCY] 重複行削除実行');
            $row.remove();
        });

        if (window.ktpDebugMode) console.log('[INVOICE EMERGENCY] 完了 - 削除行数:', duplicateRows.length);
        return duplicateRows.length;
    };

    // フラグ状態の強制リセット機能
    window.forceResetInvoiceFlags = function () {
        if (window.ktpDebugMode) console.log('[INVOICE RESET] フラグ強制リセット開始');

        // 全てのフラグをリセット
        window.ktpAddingRow = false;
        $('.invoice-item-row').removeClass('adding-row');
        $('#order_content .invoice-items-table').removeClass('processing-add');
        $('.btn-add-row').removeClass('processing').prop('disabled', false);

        if (window.ktpDebugMode) console.log('[INVOICE RESET] 全フラグリセット完了');
    };

    // 重複行検出機能
    window.detectDuplicateRows = function () {
        const tbody = $('#order_content .invoice-items-table tbody');
        const rows = tbody.find('tr');
        const indexes = [];
        const duplicates = [];

        rows.each(function () {
            const $nameInput = $(this).find('input[name*="[product_name]"]');
            if ($nameInput.length > 0) {
                const name = $nameInput.attr('name');
                const match = name.match(/invoice_items\\[(\d+)\\]/);
                if (match) {
                    const index = parseInt(match[1], 10);
                    if (indexes.includes(index)) {
                        duplicates.push(index);
                    } else {
                        indexes.push(index);
                    }
                }
            }
        });

        if (duplicates.length > 0) {
            if (window.ktpDebugMode) console.warn('[INVOICE] 重複インデックス検出:', duplicates);
        } else {
            if (window.ktpDebugMode) console.log('[INVOICE] 重複なし - 全インデックス:', indexes.sort((a, b) => a - b));
        }

        return duplicates;
    };

})(jQuery);
