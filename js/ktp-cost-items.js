/**
 * コスト項目テーブルのJavaScript機能
 *
 * @package KTPWP
 * @since 1.0.0
 */

(function ($) {
    'use strict';

    // デバッグモードを有効化
    window.ktpDebugMode = true;

    // 重複追加防止フラグ (コスト項目専用)
    window.ktpAddingCostRow = false;

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

    // 利益表示を更新
    function updateProfitDisplay() {
        let invoiceTotal = 0;
        let costTotal = 0;

        // 請求項目の合計を計算
        $('.invoice-items-table .amount').each(function () {
            invoiceTotal += parseFloat($(this).val()) || 0;
        });

        // コスト項目の合計を計算
        $('.cost-items-table .amount').each(function () {
            costTotal += parseFloat($(this).val()) || 0;
        });

        // 請求項目合計を切り上げ
        const invoiceTotalCeiled = Math.ceil(invoiceTotal);

        // コスト項目合計を切り上げ
        const costTotalCeiled = Math.ceil(costTotal);

        // 利益計算（切り上げ後の値を使用）
        const profit = invoiceTotalCeiled - costTotalCeiled;

        // 利益表示を更新
        const profitDisplay = $('.profit-display');
        if (profitDisplay.length > 0) {
            const profitColor = profit >= 0 ? '#28a745' : '#dc3545';
            profitDisplay.html('利益 : ' + profit.toLocaleString() + '円');
            profitDisplay.css('color', profitColor);

            // CSSクラスを更新
            profitDisplay.removeClass('positive negative');
            profitDisplay.addClass(profit >= 0 ? 'positive' : 'negative');
        }

        // コスト項目の合計表示も更新（切り上げ後の値を表示）
        const costTotalDisplay = $('.cost-items-total');
        if (costTotalDisplay.length > 0) {
            costTotalDisplay.html('コスト項目合計 : ' + costTotalCeiled.toLocaleString() + '円');
        }
    }

    // updateProfitDisplay関数をグローバルに露出
    window.updateProfitDisplay = updateProfitDisplay;

    // 新しい行を追加（重複防止機能付き）
    function addNewRow(currentRow, callId) { // callId を受け取る
        console.log(`[COST][${callId}] addNewRow開始 (呼び出し元ID: ${callId})`);

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
            console.warn(`[COST][${callId}] addNewRow: 品名が空の状態で呼び出されましたが、処理を続行します（本来はクリックハンドラでブロックされるべきです）。`);
            // return false; // ここで return false すると、クリックハンドラの品名チェックが機能していない場合に二重チェックになる
                          // ただし、現状問題が解決していないため、ここでも止めることを検討したが、まずはログで状況把握
        }
        // End of added check

        console.log(`[COST][${callId}] addNewRow 本処理開始`);
        // フラグ管理はクリックハンドラに集約

        const newIndex = $('.cost-items-table tbody tr').length;
        const newRowHtml = `
            <tr class="cost-item-row" data-row-id="0" data-newly-added="true">
                <td class="actions-column">
                    <span class="drag-handle" title="ドラッグして並び替え">&#9776;</span><button type="button" class="btn-add-row" title="行を追加">+</button><button type="button" class="btn-delete-row" title="行を削除">×</button><button type="button" class="btn-move-row" title="行を移動">></button>
                </td>
                <td>
                    <input type="text" name="cost_items[${newIndex}][product_name]" class="cost-item-input product-name" value="">
                    <input type="hidden" name="cost_items[${newIndex}][id]" value="0">
                </td>
                <td style="text-align:left;">
                    <input type="number" name="cost_items[${newIndex}][price]" class="cost-item-input price" value="0" step="0.01" min="0" style="text-align:left;" disabled>
                </td>
                <td style="text-align:left;">
                    <input type="number" name="cost_items[${newIndex}][quantity]" class="cost-item-input quantity" value="1" step="0.01" min="0" style="text-align:left;" disabled>
                </td>
                <td>
                    <input type="text" name="cost_items[${newIndex}][unit]" class="cost-item-input unit" value="式" disabled>
                </td>
                <td style="text-align:left;">
                    <input type="number" name="cost_items[${newIndex}][amount]" class="cost-item-input amount" value="" step="0.01" readonly style="text-align:left;">
                </td>
                <td>
                    <input type="text" name="cost_items[${newIndex}][remarks]" class="cost-item-input remarks" value="" disabled>
                    <input type="hidden" name="cost_items[${newIndex}][sort_order]" value="${newIndex + 1}">
                </td>
                <td>
                    <span class="purchase-display">(^^)</span>
                    <input type="hidden" name="cost_items[${newIndex}][purchase]" value="">
                </td>
            </tr>
        `;

        let success = false;
        try {
            console.log(`[COST][${callId}] currentRow.after(newRowHtml) を実行する直前。currentRow:`, currentRow[0].outerHTML);
            currentRow.after(newRowHtml);
            const $newRow = currentRow.next();
            if ($newRow && $newRow.length > 0 && $newRow.hasClass('cost-item-row')) {
                console.log(`[COST][${callId}] 新しい行がDOMに追加されました。`);
                
                // 新しい行で金額の自動計算を実行
                calculateAmount($newRow);
                
                $newRow.find('.product-name').focus();
                success = true;
            } else {
                console.error(`[COST][${callId}] 新しい行の追加に失敗したか、見つかりませんでした。$newRow:`, $newRow);
                success = false;
            }

        } catch (error) {
            console.error(`[COST][${callId}] addNewRow エラー:`, error);
            success = false;
        } finally {
            // フラグ解除はクリックハンドラで行う
            console.log(`[COST][${callId}] addNewRow終了`);
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
            console.log('[COST] deleteRow呼び出し', { itemId, orderId, row: currentRow });

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
                    console.warn('[COST] deleteRow: nonceが取得できませんでした');
                }

                const ajaxData = {
                    action: 'ktp_delete_item',
                    item_type: 'cost',
                    item_id: itemId,
                    order_id: orderId,
                    nonce: nonce,
                    ktp_ajax_nonce: nonce  // 追加: PHPでチェックされるフィールド名
                };
                console.log('[COST] deleteRow送信', ajaxData);
                $.ajax({
                    url: ajaxUrl,
                    type: 'POST',
                    data: ajaxData,
                    success: function (response) {
                        console.log('[COST] deleteRowレスポンス', response);
                        try {
                            const result = typeof response === 'string' ? JSON.parse(response) : response;
                            if (result.success) {
                                console.log('[COST] deleteRowサーバー側削除成功');
                                currentRow.remove();
                                updateProfitDisplay(); // 合計金額と利益を更新
                            } else {
                                console.warn('[COST] deleteRowサーバー側削除失敗', result);
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
                            console.error('[COST] deleteRowレスポンスパースエラー', e, response);
                            alert('行削除の応答処理中にエラーが発生しました。\n詳細: ' + (typeof response === 'string' ? response : JSON.stringify(response)));
                        }
                    },
                    error: function (xhr, status, error) {
                        console.error('[COST] deleteRowエラー', { status, error, responseText: xhr.responseText, statusCode: xhr.status });
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
                console.log('[COST] deleteRow: サーバー未保存行のため即時削除');
                currentRow.remove();
                updateProfitDisplay(); // 合計金額と利益を更新
            } else {
                console.warn('[COST] deleteRow: itemIdまたはorderIdが不足しているため、クライアント側でのみ削除');
                currentRow.remove();
                updateProfitDisplay(); // 合計金額と利益を更新
            }
        }
    }

    // 行のインデックスを更新 (Sortable用)
    function updateRowIndexes(table) {
        console.log('[COST] updateRowIndexes開始');
        const tbody = table.find('tbody');
        const rowCount = tbody.find('tr').length;
        console.log('[COST] 更新対象行数:', rowCount);
        
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
                    if (name.includes('[id]') || name.includes('[sort_order]') || name.includes('[product_name]')) {
                        console.log('[COST] フィールド名更新:', { 
                            oldName: oldName, 
                            newName: newName, 
                            value: input.val() 
                        });
                    }
                }
            });
            
            console.log('[COST] 行' + (index + 1) + 'の更新完了:', { 
                rowIndex: index, 
                updatedFields: updatedCount 
            });
        });
        
        console.log('[COST] updateRowIndexes完了');
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
            console.warn('ajaxurl not defined, using fallback');
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

        console.log('Cost items - Sending Ajax request:', ajaxData);
        console.log('Ajax URL:', ajaxUrl);
        console.log('Field being saved:', fieldName, 'Value:', fieldValue);

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: ajaxData,
            success: function (response) {
                console.log('Cost items - Ajax response received:', response);
                try {
                    const result = typeof response === 'string' ? JSON.parse(response) : response;
                    if (result.success) {
                        console.log('Cost auto-saved successfully');
                        // 成功時の視覚的フィードバック（オプション）
                        // showSaveIndicator('saved');
                    } else {
                        console.error('Cost auto-save failed:', result.message);
                    }
                } catch (e) {
                    console.error('Cost auto-save response parse error:', e, 'Raw response:', response);
                }
            },
            error: function (xhr, status, error) {
                console.error('Cost auto-save Ajax error:', {
                    status: status,
                    error: error,
                    responseText: xhr.responseText,
                    statusCode: xhr.status
                });
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

        if (window.ktpDebugMode) {
            console.log('Creating new cost item:', ajaxData);
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
                            console.log('[COST] createNewItem: 他のフィールドを有効化', $row);

                            // フィールド有効化後に金額計算を実行
                            setTimeout(function() {
                                calculateAmount($row);
                                console.log('[COST] createNewItem: フィールド有効化後の金額計算実行');
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
    // ポップアップ内の「更新」ボタン
    $(document).off('click', '.popup-dialog .ktp-cost-update-btn').on('click', '.popup-dialog .ktp-cost-update-btn', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        console.log('[COST] 更新ボタンクリック');
        
        const $btn = $(this);
        const $popup = $btn.closest('.popup-dialog');
        const serviceData = $btn.data('service') || $btn.closest('.supplier-service-item').data('service');
        const targetRowId = $popup.data('target-row');
        const $targetRow = $('#' + targetRowId);
        
        if (!serviceData || $targetRow.length === 0) {
            console.error('[COST] 更新対象の行またはサービスデータが見つかりません', {
                serviceData: serviceData,
                targetRowId: targetRowId,
                targetRowExists: $targetRow.length > 0
            });
            alert('更新対象の行またはサービスデータが見つかりません。');
            return;
        }
        
        console.log('[COST] 更新処理開始', {
            serviceData: serviceData,
            targetRowId: targetRowId
        });
        
        // UI反映
        $targetRow.find('.product-name').val(serviceData.product_name);
        displaySupplierServicePrice($targetRow, serviceData);
        $targetRow.find('input').prop('disabled', false);
        
        // 協力会社名を「仕入」フィールドに表示
        if (window.ktpCurrentSupplierName) {
            $targetRow.find('.purchase-display').text(window.ktpCurrentSupplierName);
            $targetRow.find('input[name*="[purchase]"]').val(window.ktpCurrentSupplierName);
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
            
            // 各フィールドを順次保存
            autoSaveItem('cost', itemId, 'product_name', serviceData.product_name, orderId);
            autoSaveItem('cost', itemId, 'price', serviceData.unit_price, orderId);
            autoSaveItem('cost', itemId, 'quantity', serviceData.quantity, orderId);
            autoSaveItem('cost', itemId, 'unit', serviceData.unit, orderId);
            
            // 協力会社名を「仕入」フィールドに保存
            if (window.ktpCurrentSupplierName) {
                autoSaveItem('cost', itemId, 'purchase', window.ktpCurrentSupplierName, orderId);
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
        
        // ポップアップ自動クローズ
        $popup.remove();
        
        console.log('[COST] 更新処理完了');
    });

    // ポップアップ内の「追加」ボタン
    $(document).off('click', '.popup-dialog .ktp-cost-add-btn').on('click', '.popup-dialog .ktp-cost-add-btn', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        console.log('[COST] 追加ボタンクリック');
        
        const $btn = $(this);
        const $popup = $btn.closest('.popup-dialog');
        const serviceData = $btn.data('service') || $btn.closest('.supplier-service-item').data('service');
        
        if (!serviceData) {
            console.error('[COST] 追加するサービスデータが見つかりません');
            alert('追加するサービスデータが見つかりません。');
            return;
        }
        
        console.log('[COST] 追加処理開始', {
            serviceData: serviceData
        });
        
        // 一番下に新規行を追加
        const $lastRow = $('.cost-items-table tbody tr').last();
        const callId = Date.now();
        const rowAdded = addNewRow($lastRow, callId);
        
        if (!rowAdded) {
            console.error('[COST] 新規行の追加に失敗しました');
            alert('新規行の追加に失敗しました。');
            return;
        }
        
        const $newRow = $lastRow.next();
        
        console.log('[COST] 新規行追加完了', {
            newRowIndex: $newRow.index()
        });
        
        // UI反映
        $newRow.find('.product-name').val(serviceData.product_name);
        $newRow.find('input').prop('disabled', false);
        displaySupplierServicePrice($newRow, serviceData);
        
        // 協力会社名を「仕入」フィールドに表示
        if (window.ktpCurrentSupplierName) {
            $newRow.find('.purchase-display').text(window.ktpCurrentSupplierName);
            $newRow.find('input[name*="[purchase]"]').val(window.ktpCurrentSupplierName);
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
            
            createNewItem('cost', 'product_name', serviceData.product_name, orderId, $newRow, function(success, newItemId) {
                if (success && newItemId) {
                    console.log('[COST] 新規アイテム作成成功', {
                        newItemId: newItemId
                    });
                    
                    // 各フィールドを順次保存
                    autoSaveItem('cost', newItemId, 'price', serviceData.unit_price, orderId);
                    autoSaveItem('cost', newItemId, 'quantity', serviceData.quantity, orderId);
                    autoSaveItem('cost', newItemId, 'unit', serviceData.unit, orderId);
                    
                    // 協力会社名を「仕入」フィールドに保存
                    if (window.ktpCurrentSupplierName) {
                        autoSaveItem('cost', newItemId, 'purchase', window.ktpCurrentSupplierName, orderId);
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
            });
        } else {
            console.warn('[COST] DB新規作成スキップ - orderId未設定');
        }
        
        // ポップアップは閉じない（ユーザーが手動で閉じるまで待つ）
        console.log('[COST] 追加処理完了 - ポップアップは開いたまま');
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
        // inputイベントでの即座の計算は削除（小数点入力時のカーソル移動問題を解決）
        // $(document).on('input', '.cost-items-table .price, .cost-items-table .quantity', function () {
        //     const $field = $(this);
        //     
        //     // disabled フィールドは処理をスキップ
        //     if ($field.prop('disabled')) {
        //         if (window.ktpDebugMode) {
        //             console.log('[COST] Input event skipped: field is disabled');
        //         }
        //         return;
        //     }
        //     
        //     const row = $field.closest('tr');
        //     const fieldType = $field.hasClass('price') ? 'price' : 'quantity';
        //     const value = $field.val();
        //     
        //     if (window.ktpDebugMode) {
        //         console.log('[COST] Input event triggered:', {
        //             fieldType: fieldType,
        //             value: value,
        //             rowIndex: row.index()
        //         });
        //     }
        //     
        //     calculateAmount(row);

        //     // 金額の自動保存は calculateAmount 内で行われる
        // });

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

        // 単価フィールドのblurイベントで自動保存
        $(document).on('blur', '.cost-item-input.price', function () {
            const $field = $(this);
            // フィールドが無効なら何もしない (新規行で商品名入力前の状態)
            if ($field.prop('disabled')) return;

            const price = $field.val();
            const $row = $field.closest('tr');
            const itemId = $row.find('input[name*="[id]"]').val();
            const orderId = $('input[name="order_id"]').val() || $('#order_id').val();

            // 金額を再計算 (calculateAmountはinputイベントで呼ばれるが、blurでも念のため)
            // calculateAmount($row); // これがamountの保存もトリガーする可能性

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

        // 数量フィールドのblurイベントで自動保存
        $(document).on('blur', '.cost-item-input.quantity', function () {
            const $field = $(this);
            if ($field.prop('disabled')) return;

            const quantity = $field.val();
            const $row = $field.closest('tr');
            const itemId = $row.find('input[name*="[id]"]').val();
            const orderId = $('input[name="order_id"]').val() || $('#order_id').val();

            // 金額を再計算 (calculateAmountはinputイベントで呼ばれるが、blurでも念のため)
            // calculateAmount($row);

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

            const remarks = $field.val();
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
})(jQuery);
