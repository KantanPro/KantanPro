/**
 * 請求項目テーブルのJavaScript機能
 *
 * @package KTPWP
 * @since 1.0.0
 */

(function ($) {
    'use strict';

    // グローバルスコープに関数を定義
    window.ktpInvoiceAutoSaveItem = function (itemType, itemId, fieldName, fieldValue, orderId) {
        console.log('[INVOICE] autoSaveItem呼び出し', { itemType, itemId, fieldName, fieldValue, orderId });
        // Ajax URLの確認と代替設定
        let ajaxUrl = ajaxurl;
        if (!ajaxUrl) {
            ajaxUrl = '/wp-admin/admin-ajax.php';
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
            item_type: itemType,
            item_id: itemId,
            field_name: fieldName,
            field_value: fieldValue,
            order_id: orderId,
            nonce: nonce,
            ktp_ajax_nonce: nonce  // 追加: PHPでチェックされるフィールド名
        };


        console.log('[INVOICE] autoSaveItem送信', ajaxData);
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: ajaxData,
            success: function (response) {
                console.log('[INVOICE] autoSaveItemレスポンス', response);
                try {
                    const result = typeof response === 'string' ? JSON.parse(response) : response;
                    if (result.success) {
                        console.log('[INVOICE] autoSaveItem保存成功');
                    } else {
                        console.warn('[INVOICE] autoSaveItem保存失敗', result);
                    }
                } catch (e) {
                    console.error('[INVOICE] autoSaveItemレスポンスパースエラー', e, response);
                }
            },
            error: function (xhr, status, error) {
                console.error('[INVOICE] autoSaveItemエラー', {
                    status: status,
                    error: error,
                    responseText: xhr.responseText,
                    statusCode: xhr.status
                });
            }
        });
    };
    // createNewItem関数にcallback引数を追加し、成功/失敗と新しいitem_idを返すように変更
    window.ktpInvoiceCreateNewItem = function (itemType, fieldName, fieldValue, orderId, $row, callback) {
        console.log('[INVOICE] createNewItem呼び出し', { itemType, fieldName, fieldValue, orderId, $row });
        // Ajax URLの確認と代替設定
        let ajaxUrl = ajaxurl;
        if (!ajaxUrl) {
            ajaxUrl = '/wp-admin/admin-ajax.php';
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
            item_type: itemType,
            field_name: fieldName,
            field_value: fieldValue,
            order_id: orderId,
            nonce: nonce,
            ktp_ajax_nonce: nonce  // 追加: PHPでチェックされるフィールド名
        };
        console.log('[INVOICE] createNewItem送信', ajaxData);
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: ajaxData,
            success: function (response) {
                console.log('[INVOICE] createNewItemレスポンス', response);
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
                            console.log('[INVOICE] createNewItem: 他のフィールドを有効化', $row);
                            
                            // フィールド有効化後に金額計算を実行
                            setTimeout(function() {
                                calculateAmount($row);
                                console.log('[INVOICE] createNewItem: フィールド有効化後の金額計算実行');
                            }, 100);
                        }
                        console.log('[INVOICE] createNewItem新規IDセット', result.data.item_id);
                        if (callback) callback(true, result.data.item_id); // コールバック呼び出し
                    } else {
                        console.warn('[INVOICE] createNewItem失敗（レスポンス構造確認）', result);
                        if (callback) callback(false, null); // コールバック呼び出し
                    }
                } catch (e) {
                    console.error('[INVOICE] createNewItemレスポンスパースエラー', e, response);
                    if (callback) callback(false, null); // コールバック呼び出し
                }
            },
            error: function (xhr, status, error) {
                console.error('[INVOICE] createNewItemエラー', {
                    status: status,
                    error: error,
                    responseText: xhr.responseText,
                    statusCode: xhr.status
                });
                if (callback) callback(false, null); // コールバック呼び出し
            }
        });
    };

    // 価格×数量の自動計算
    function calculateAmount(row) {
        const priceValue = row.find('.price').val();
        const quantityValue = row.find('.quantity').val();
        
        // より厳密な数値変換
        const price = (priceValue === '' || priceValue === null || isNaN(priceValue)) ? 0 : parseFloat(priceValue);
        const quantity = (quantityValue === '' || quantityValue === null || isNaN(quantityValue)) ? 0 : parseFloat(quantityValue);
        const amount = price * quantity;
        
        // NaNチェック
        const finalAmount = isNaN(amount) ? 0 : amount;
        
        // デバッグログ
        if (window.ktpDebugMode) {
            console.log('[INVOICE] calculateAmount called:', {
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

        if (itemId && orderId) {
            if (itemId === '0') {
                // 新規行の場合は何もしない（商品名入力時に新規作成される）
                if (window.ktpDebugMode) {
                    console.log('[INVOICE] calculateAmount: 新規行のため金額保存スキップ');
                }
            } else {
                // 既存行の場合：金額を自動保存
                if (window.ktpDebugMode) {
                    console.log('[INVOICE] calculateAmount: 金額自動保存実行', {itemId, amount: finalAmount});
                }
                window.ktpInvoiceAutoSaveItem('invoice', itemId, 'amount', finalAmount, orderId);
            }
        } else {
            if (window.ktpDebugMode) {
                console.log('[INVOICE] calculateAmount: 保存条件未満', {itemId, orderId});
            }
        }

        // 請求項目合計と利益表示を更新
        updateTotalAndProfit();
    }

    // 請求項目合計と利益表示を更新
    function updateTotalAndProfit() {
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

        // 請求項目の合計表示を更新（切り上げ後の値を表示）
        const invoiceTotalDisplay = $('.invoice-items-total');
        if (invoiceTotalDisplay.length > 0) {
            invoiceTotalDisplay.html('合計金額 : ' + invoiceTotalCeiled.toLocaleString() + '円');
        }

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
    }

    // 新しい行を追加（重複防止機能付き）
    function addNewRow(currentRow, callId) { // callId を追加
        console.log(`[INVOICE][${callId}] addNewRow開始 (呼び出し元ID: ${callId})`);

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
            console.warn(`[INVOICE][${callId}] addNewRow: 品名が空の状態で呼び出されましたが、処理を続行します（本来はクリックハンドラでブロックされるべきです）。`);
            // return false; // ここで return false すると、クリックハンドラの品名チェックが機能していない場合に二重チェックになる
                          // ただし、現状問題が解決していないため、ここでも止めることを検討したが、まずはログで状況把握
        }

        console.log(`[INVOICE][${callId}] addNewRow 本処理開始`);
        // フラグ管理はクリックハンドラに集約

        const newIndex = $('.invoice-items-table tbody tr').length;
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
                    <input type="number" name="invoice_items[${newIndex}][amount]" class="invoice-item-input amount" value="" step="1" readonly style="text-align:left;">
                </td>
                <td>
                    <input type="text" name="invoice_items[${newIndex}][remarks]" class="invoice-item-input remarks" value="" disabled>
                    <input type="hidden" name="invoice_items[${newIndex}][sort_order]" value="${newIndex + 1}">
                </td>
            </tr>
        `;

        let success = false;
        try {
            console.log(`[INVOICE][${callId}] currentRow.after(newRowHtml) を実行する直前。`);
            currentRow.after(newRowHtml);
            const $newRow = currentRow.next();
            if ($newRow && $newRow.length > 0 && $newRow.hasClass('invoice-item-row')) {
                console.log(`[INVOICE][${callId}] 新しい行がDOMに追加されました。`);
                
                // 新しい行で金額の自動計算を実行
                calculateAmount($newRow);
                
                $newRow.find('.product-name').focus();
                success = true;
            } else {
                console.error(`[INVOICE][${callId}] 新しい行の追加に失敗したか、見つかりませんでした。`);
                success = false;
            }
        } catch (error) {
            console.error(`[INVOICE][${callId}] addNewRow エラー:`, error);
            success = false;
        } finally {
            // window.ktpAddingInvoiceRow = false; // フラグ解除は呼び出し元の finally で
            console.log(`[INVOICE][${callId}] addNewRow終了`);
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

            console.log('[INVOICE] deleteRow呼び出し', { itemId, orderId, row: currentRow });

            // Ajaxでサーバーに削除を通知
            if (itemId && itemId !== '0' && orderId) {
                let ajaxUrl = ajaxurl;
                if (!ajaxUrl && typeof ktp_ajax_object !== 'undefined') {
                    ajaxUrl = ktp_ajax_object.ajax_url;
                } else if (!ajaxUrl) {
                    ajaxUrl = '/wp-admin/admin-ajax.php';
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
                    console.warn('[INVOICE] deleteRow: nonceが取得できませんでした');
                }

                const ajaxData = {
                    action: 'ktp_delete_item',
                    item_type: 'invoice',
                    item_id: itemId,
                    order_id: orderId,
                    nonce: nonce,
                    ktp_ajax_nonce: nonce  // 追加: PHPでチェックされるフィールド名
                };
                console.log('[INVOICE] deleteRow送信', ajaxData);
                $.ajax({
                    url: ajaxUrl,
                    type: 'POST',
                    data: ajaxData,
                    success: function (response) {
                        console.log('[INVOICE] deleteRowレスポンス', response);
                        try {
                            const result = typeof response === 'string' ? JSON.parse(response) : response;
                            if (result.success) {
                                console.log('[INVOICE] deleteRowサーバー側削除成功');
                                currentRow.remove();
                                updateTotalAndProfit(); // 合計金額を更新
                            } else {
                                console.warn('[INVOICE] deleteRowサーバー側削除失敗', result);
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
                            console.error('[INVOICE] deleteRowレスポンスパースエラー', e, response);
                            alert('行削除の応答処理中にエラーが発生しました。\n詳細: ' + (typeof response === 'string' ? response : JSON.stringify(response)));
                        }
                    },
                    error: function (xhr, status, error) {
                        console.error('[INVOICE] deleteRowエラー', { status, error, responseText: xhr.responseText, statusCode: xhr.status });
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
                console.log('[INVOICE] deleteRow: サーバー未保存行のため即時削除');
                currentRow.remove();
                updateTotalAndProfit(); // 合計金額を更新
            } else {
                // itemIdがない、またはorderIdがない場合は、クライアント側でのみ削除（通常は発生しないはず）
                console.warn('[INVOICE] deleteRow: itemIdまたはorderIdが不足しているため、クライアント側でのみ削除');
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
        console.log('[INVOICE] 📋 ページ初期化開始');

        // 初期状態の確認
        const initialRowCount = $('.invoice-items-table tbody tr').length;
        console.log('[INVOICE] 📊 初期行数:', initialRowCount);

        // 並び替え（sortable）有効化
        $('.invoice-items-table tbody').sortable({
            handle: '.drag-handle',
            items: '> tr',
            axis: 'y',
            helper: 'clone',
            update: function (event, ui) {
                console.log('[INVOICE] 行の並び替え完了');
                const items = [];
                const orderId = $('input[name="order_id"]').val() || $('#order_id').val();
                $(this).find('tr').each(function (index) {
                    const itemId = $(this).find('input[name*="[id]"]').val();
                    if (itemId && itemId !== '0') { // Ensure itemId is valid
                        items.push({ id: itemId, sort_order: index + 1 });
                    }
                });

                if (items.length > 0 && orderId) {
                    let ajaxUrl = ajaxurl;
                    if (!ajaxUrl && typeof ktp_ajax_object !== 'undefined') {
                        ajaxUrl = ktp_ajax_object.ajax_url;
                    } else if (!ajaxUrl) {
                        ajaxUrl = '/wp-admin/admin-ajax.php';
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
                    
                    console.log('[INVOICE] 使用するnonce:', nonce);

                    console.log('[INVOICE] updateItemOrder送信', { order_id: orderId, items: items });
                    $.ajax({
                        url: ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'ktp_update_item_order',
                            order_id: orderId,
                            items: items,
                            item_type: 'invoice', // Assuming this is for invoice items
                            nonce: nonce,
                            ktp_ajax_nonce: nonce  // 追加: PHPでチェックされるフィールド名
                        },
                        success: function (response) {
                            console.log('[INVOICE] updateItemOrderレスポンス', response);
                            try {
                                const result = typeof response === 'string' ? JSON.parse(response) : response;
                                if (result.success) {
                                    console.log('[INVOICE] 並び順の保存に成功しました。');
                                    // Optionally, re-index rows if your display depends on it,
                                    // but it seems your PHP handles sort_order directly.
                                    // updateRowIndexes($(event.target).closest('table'));
                                } else {
                                    console.warn('[INVOICE] 並び順の保存に失敗しました。', result);
                                    alert('並び順の保存に失敗しました。: ' + (result.data && result.data.message ? result.data.message : 'サーバーエラー'));
                                }
                            } catch (e) {
                                console.error('[INVOICE] updateItemOrderレスポンスパースエラー', e, response);
                                alert('並び順保存の応答処理中にエラーが発生しました。');
                            }
                        },
                        error: function (xhr, status, error) {
                            console.error('[INVOICE] updateItemOrderエラー', { status, error, responseText: xhr.responseText });
                            alert('並び順の保存中にサーバーエラーが発生しました。');
                        }
                    });
                } else {
                    console.log('[INVOICE] 保存するアイテムがないか、orderIdがありません。');
                }
            },
            start: function (event, ui) {
                ui.item.addClass('dragging');
            },
            stop: function (event, ui) {
                ui.item.removeClass('dragging');
            }
        }).disableSelection();

        // 価格・数量変更時の金額自動計算
        $(document).on('input', '.invoice-items-table .price, .invoice-items-table .quantity', function () {
            const $field = $(this);
            
            // disabled フィールドは処理をスキップ
            if ($field.prop('disabled')) {
                if (window.ktpDebugMode) {
                    console.log('[INVOICE] Input event skipped: field is disabled');
                }
                return;
            }
            
            const row = $field.closest('tr');
            const fieldType = $field.hasClass('price') ? 'price' : 'quantity';
            const value = $field.val();
            
            if (window.ktpDebugMode) {
                console.log('[INVOICE] Input event triggered:', {
                    fieldType: fieldType,
                    value: value,
                    rowIndex: row.index()
                });
            }
            
            calculateAmount(row);
        });

        // 自動追加機能を無効化（コメントアウト）
        // $(document).on('input', '.invoice-items-table .product-name, .invoice-items-table .price, .invoice-items-table .quantity', function() {
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
        $(document).off('click.ktpInvoiceAdd', '.invoice-items-table .btn-add-row');
        $('body').off('click.ktpInvoiceAdd', '.invoice-items-table .btn-add-row');
        $('.invoice-items-table').off('click.ktpInvoiceAdd', '.btn-add-row');

        // より強力な解除（名前空間なしも試す）
        $(document).off('click', '.invoice-items-table .btn-add-row');
        $('body').off('click', '.invoice-items-table .btn-add-row');
        $('.invoice-items-table').off('click', '.btn-add-row');


        $(document).on('click.ktpInvoiceAdd', '.invoice-items-table .btn-add-row', function (e) {
            const clickId = Date.now();
            console.log(`[INVOICE][${clickId}] +ボタンクリックイベント発生 (ktpInvoiceAdd)`);

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
                console.log(`[INVOICE][${clickId}] クリックハンドラ: 品名未入力。 addNewRow を呼び出さずに処理を中断します。これがこのハンドラの最後のログになるはずです。`);
                return false;
            }

            console.log(`[INVOICE][${clickId}] クリックハンドラ: 品名入力済み。ktpAddingInvoiceRow の状態 (呼び出し前):`, window.ktpAddingInvoiceRow);

            if ($button.prop('disabled') || $button.hasClass('processing')) {
                console.log(`[INVOICE][${clickId}] ボタンが無効または処理中のためスキップ`);
                return false;
            }

            if (window.ktpAddingInvoiceRow === true) {
                console.log(`[INVOICE][${clickId}] 既に処理中のため中止 (ktpAddingInvoiceRow is true)`);
                return false;
            }

            $button.prop('disabled', true).addClass('processing');
            window.ktpAddingInvoiceRow = true;
            console.log(`[INVOICE][${clickId}] +ボタン処理開始、ボタン無効化、ktpAddingInvoiceRow を true に設定`);

            let rowAddedSuccessfully = false;
            try {
                console.log(`[INVOICE][${clickId}] addNewRow を呼び出します。`);
                rowAddedSuccessfully = addNewRow(currentRow, clickId); // clickId を渡す
                console.log(`[INVOICE][${clickId}] addNewRow の呼び出し結果:`, rowAddedSuccessfully);

                if (!rowAddedSuccessfully) {
                    console.warn(`[INVOICE][${clickId}] addNewRow が false を返しました。`);
                } else {
                    console.log(`[INVOICE][${clickId}] addNewRow が true を返しました。`);
                }
            } catch (error) {
                console.error(`[INVOICE][${clickId}] addNewRow 呼び出し中またはその前後でエラー:`, error);
                rowAddedSuccessfully = false;
            } finally {
                window.ktpAddingInvoiceRow = false;
                $button.prop('disabled', false).removeClass('processing');
                console.log(`[INVOICE][${clickId}] ボタン再有効化、ktpAddingInvoiceRow を false に設定 (finally)`);
            }
            console.log(`[INVOICE][${clickId}] クリックハンドラの末尾。`);
            return false;
        });

        // 行削除ボタン - イベント重複を防ぐ
        $(document).off('click.ktpInvoiceDelete', '.invoice-items-table .btn-delete-row') // 名前空間付きイベントに変更
            .on('click.ktpInvoiceDelete', '.invoice-items-table .btn-delete-row', function (e) {
                e.preventDefault();
                e.stopPropagation();
                const currentRow = $(this).closest('tr');
                console.log('[INVOICE] 削除ボタンクリック', currentRow);
                deleteRow(currentRow);
            });

        // 行移動ボタン（サービス選択機能）- 請求項目テーブル専用
        $(document).on('click', '.invoice-items-table .btn-move-row', function (e) {
            e.preventDefault();
            e.stopPropagation();
            console.log('[INVOICE-ITEMS] [>]ボタンクリック - サービス選択開始');
            
            // サービス選択ポップアップを表示
            const currentRow = $(this).closest('tr');
            
            // ktpShowServiceSelector関数の存在確認
            if (typeof window.ktpShowServiceSelector === 'function') {
                console.log('[INVOICE-ITEMS] ktpShowServiceSelector関数を呼び出し');
                window.ktpShowServiceSelector(currentRow);
            } else {
                console.error('[INVOICE-ITEMS] ktpShowServiceSelector関数が見つかりません');
                alert('サービス選択機能の読み込みに失敗しました。ページを再読み込みしてください。');
            }
        });

        // フォーカス時の入力欄スタイル調整
        $(document).on('focus', '.invoice-item-input', function () {
            $(this).addClass('focused');
            // 数値入力フィールドの場合、フォーカス時に全選択
            if ($(this).attr('type') === 'number') {
                $(this).select();
            }
        });

        $(document).on('blur', '.invoice-item-input', function () {
            $(this).removeClass('focused');
        });

        // 商品名フィールドのblurイベントで自動保存
        $(document).on('blur', '.invoice-item-input.product-name', function () {
            const $field = $(this);
            const productName = $field.val();
            const $row = $field.closest('tr');
            let itemId = $row.find('input[name*="[id]"]').val();
            const orderId = $('input[name="order_id"]').val() || $('#order_id').val();

            if (window.ktpDebugMode) {
                console.log('Invoice product name auto-save debug:', {
                    productName: productName,
                    itemId: itemId,
                    orderId: orderId,
                    hasNonce: typeof ktp_ajax_nonce !== 'undefined',
                    hasAjaxurl: typeof ajaxurl !== 'undefined'
                });
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
                                    console.log('[INVOICE] product-name blur: 他のフィールドを有効化（再確認）', $row);
                                }
                                // price フィールドにフォーカスを移動
                                if ($row.find('.price').prop('disabled') === false) {
                                    $row.find('.price').focus();
                                }
                                console.log('[INVOICE] product-name blur: createNewItem成功後、ID:', newItemId, 'pending-initial-creation:', $row.data('pending-initial-creation'));
                            } else {
                                console.warn('[INVOICE] product-name blur: createNewItem失敗');
                            }
                        });
                    } else if ($row.data('newly-added') || itemId === '' || itemId === '0') { // 条件を明確化
                        // 商品名が空のままフォーカスが外れた新規行の場合の処理
                        if (window.ktpDebugMode) {
                            console.log('Invoice product name is empty on blur for new/template row. Item not created/saved.', {row: $row[0].outerHTML, itemId: itemId});
                        }
                    }
                } else {
                    // 既存行の場合：商品名を自動保存
                    window.ktpInvoiceAutoSaveItem('invoice', itemId, 'product_name', productName, orderId);
                }
            } else {
                if (window.ktpDebugMode) {
                    console.warn('Order ID is missing. Cannot auto-save product name.');
                }
            }
        });
        // 単価フィールドのblurイベントで自動保存
        $(document).on('blur', '.invoice-item-input.price', function () {
            const $field = $(this);
            const price = $field.val();
            const $row = $field.closest('tr');
            const itemId = $row.find('input[name*="[id]"]').val();
            const orderId = $('input[name="order_id"]').val() || $('#order_id').val();
            // 金額を再計算
            calculateAmount($row);
            // item_idが0でなく、かつ空でない場合に保存
            if (orderId && itemId && itemId !== '0') {
                console.log('[INVOICE] blur: price - 既存更新/新規作成後', { price, itemId, orderId });
                window.ktpInvoiceAutoSaveItem('invoice', itemId, 'price', price, orderId);
            } else if (itemId === '0') {
                console.log('[INVOICE] blur: price - item_idが0のため保存スキップ。product_nameの入力/保存待ち。');
            }
        });
        // 数量フィールドのblurイベントで自動保存
        $(document).on('blur', '.invoice-item-input.quantity', function () {
            const $field = $(this);
            const quantity = $field.val();
            const $row = $field.closest('tr');
            const itemId = $row.find('input[name*="[id]"]').val();
            const orderId = $('input[name="order_id"]').val() || $('#order_id').val();
            // 金額を再計算
            calculateAmount($row);
            if (orderId && itemId && itemId !== '0') {
                console.log('[INVOICE] blur: quantity - 既存更新/新規作成後', { quantity, itemId, orderId });
                window.ktpInvoiceAutoSaveItem('invoice', itemId, 'quantity', quantity, orderId);
            } else if (itemId === '0') {
                console.log('[INVOICE] blur: quantity - item_idが0のため保存スキップ。product_nameの入力/保存待ち。');
            }
        });
        // 備考フィールドのblurイベントで自動保存
        $(document).on('blur', '.invoice-item-input.remarks', function () {
            const $field = $(this);
            const remarks = $field.val();
            const $row = $field.closest('tr');
            const itemId = $row.find('input[name*="[id]"]').val();
            const orderId = $('input[name="order_id"]').val() || $('#order_id').val();
            if (orderId && itemId && itemId !== '0') {
                console.log('[INVOICE] blur: remarks - 既存更新/新規作成後', { remarks, itemId, orderId });
                window.ktpInvoiceAutoSaveItem('invoice', itemId, 'remarks', remarks, orderId);
            } else if (itemId === '0') {
                console.log('[INVOICE] blur: remarks - item_idが0のため保存スキップ。product_nameの入力/保存待ち。');
            }
        });
        // ユニットフィールドのblurイベントで自動保存
        $(document).on('blur', '.invoice-item-input.unit', function () {
            const $field = $(this);
            const unit = $field.val();
            const $row = $field.closest('tr');
            const itemId = $row.find('input[name*="[id]"]').val();
            const orderId = $('input[name="order_id"]').val() || $('#order_id').val();
            if (orderId && itemId && itemId !== '0') {
                console.log('[INVOICE] blur: unit - 既存更新/新規作成後', { unit, itemId, orderId });
                window.ktpInvoiceAutoSaveItem('invoice', itemId, 'unit', unit, orderId);
            } else if (itemId === '0') {
                console.log('[INVOICE] blur: unit - item_idが0のため保存スキップ。product_nameの入力/保存待ち。');
            }
        });

        // 初期状態で既存の行に対して金額計算を実行
        $('.invoice-items-table tbody tr').each(function () {
            calculateAmount($(this));
        });
    });

    // デバッグ用関数をグローバルスコープに追加
    window.testInvoiceItemsDebug = function () {
        console.log('=== インボイス項目デバッグ ===');

        const tbody = $('.invoice-items-table tbody');
        if (tbody.length === 0) {
            console.log('インボイステーブルが見つかりません');
            return;
        }

        const rows = tbody.find('tr');
        console.log('現在の行数:', rows.length);

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
                    console.log(`行${i + 1}: インデックス=${index}, 商品名="${$nameInput.val()}"`);
                }
            }
        });

        console.log('使用中のインデックス:', indexes.sort((a, b) => a - b));
        console.log('最大インデックス:', Math.max(...indexes));
        console.log('次のインデックス:', Math.max(...indexes) + 1);

        // フラグ状態をチェック
        console.log('フラグ状態:', {
            ktpAddingRow: window.ktpAddingRow,
            tableProcessing: $('.invoice-items-table').hasClass('processing-add'),
            processingButtons: $('.btn-add-row.processing').length
        });
    };

    // 行カウンター機能
    window.countInvoiceRows = function () {
        const count = $('.invoice-items-table tbody tr').length;
        console.log('[INVOICE] 現在の行数:', count);
        return count;
    };

    // 強化されたリアルタイム監視機能
    window.monitorInvoiceRows = function () {
        console.log('[INVOICE MONITOR] 現在の状況監視開始');

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
                        console.warn('[INVOICE MONITOR] 行追加検出:', addedRows.length, '行');

                        // 即座に重複チェック
                        const duplicates = window.detectDuplicateRows();
                        if (duplicates.length > 0) {
                            console.error('[INVOICE MONITOR] 重複行検出 - 緊急対応が必要');
                            // 重複行を削除
                            addedRows.forEach((row, index) => {
                                if (index > 0) { // 最初の行以外を削除
                                    console.warn('[INVOICE MONITOR] 重複行削除:', row);
                                    row.remove();
                                }
                            });
                        }
                    }
                }
            });
        });

        const tableBody = $('.invoice-items-table tbody')[0];
        if (tableBody) {
            observer.observe(tableBody, {
                childList: true,
                subtree: true
            });
            console.log('[INVOICE MONITOR] DOM監視開始');
        }

        return observer;
    };

    // 緊急時の重複行削除機能
    window.emergencyCleanDuplicateRows = function () {
        console.log('[INVOICE EMERGENCY] 緊急重複行削除開始');

        const rows = $('.invoice-items-table tbody tr');
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
                        console.warn('[INVOICE EMERGENCY] 重複行発見:', index);
                    } else {
                        indexMap[index] = $row;
                    }
                }
            }
        });

        // 重複行を削除
        duplicateRows.forEach(function ($row) {
            console.warn('[INVOICE EMERGENCY] 重複行削除実行');
            $row.remove();
        });

        console.log('[INVOICE EMERGENCY] 完了 - 削除行数:', duplicateRows.length);
        return duplicateRows.length;
    };

    // フラグ状態の強制リセット機能
    window.forceResetInvoiceFlags = function () {
        console.log('[INVOICE RESET] フラグ強制リセット開始');

        // 全てのフラグをリセット
        window.ktpAddingRow = false;
        $('.invoice-item-row').removeClass('adding-row');
        $('.invoice-items-table').removeClass('processing-add');
        $('.btn-add-row').removeClass('processing').prop('disabled', false);

        console.log('[INVOICE RESET] 全フラグリセット完了');
    };

    // 重複行検出機能
    window.detectDuplicateRows = function () {
        const tbody = $('.invoice-items-table tbody');
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
            console.warn('[INVOICE] 重複インデックス検出:', duplicates);
        } else {
            console.log('[INVOICE] 重複なし - 全インデックス:', indexes.sort((a, b) => a - b));
        }

        return duplicates;
    };

})(jQuery);
