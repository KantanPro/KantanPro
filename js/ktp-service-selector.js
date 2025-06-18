/**
 * サービス選択ポップアップ機能
 *
 * @package KTPWP
 * @since 1.0.0
 */

(function ($) {
    'use strict';

    // デバッグ用のコンソールログを追加
    console.log('[SERVICE-SELECTOR] スクリプトが読み込まれました');

    // 依存関係チェック
    $(document).ready(function() {
        console.log('[SERVICE-SELECTOR] DOM準備完了');
        console.log('[SERVICE-SELECTOR] jQuery available:', typeof $ !== 'undefined');
        console.log('[SERVICE-SELECTOR] ktp_service_ajax_object available:', typeof ktp_service_ajax_object !== 'undefined');
        if (typeof ktp_service_ajax_object !== 'undefined') {
            console.log('[SERVICE-SELECTOR] Ajax URL:', ktp_service_ajax_object.ajax_url);
            console.log('[SERVICE-SELECTOR] Nonce:', ktp_service_ajax_object.nonce);
        }
    });

    // サービス選択ポップアップの表示
    window.ktpShowServiceSelector = function (targetRow, mode = 'add') {
        console.log('[SERVICE SELECTOR] ポップアップ表示開始', { targetRow, mode });

        const orderId = $('input[name="order_id"]').val() || $('#order_id').val();
        if (!orderId) {
            alert('受注書IDが見つかりません。');
            return;
        }

        // ポップアップHTML
        const popupHtml = `
            <div id="ktp-service-selector-popup" style="
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.5);
                z-index: 10000;
                display: flex;
                justify-content: center;
                align-items: center;
            ">
                <div style="
                    background: white;
                    border-radius: 8px;
                    padding: 20px;
                    width: 90%;
                    max-width: 800px;
                    max-height: 80%;
                    overflow-y: auto;
                    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
                ">
                    <div style="
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                        margin-bottom: 20px;
                        border-bottom: 1px solid #eee;
                        padding-bottom: 15px;
                    ">
                        <h3 style="margin: 0; color: #333;">サービス選択</h3>
                        <button type="button" id="ktp-service-selector-close" style="
                            background: #dc3545;
                            color: white;
                            border: none;
                            border-radius: 4px;
                            padding: 8px 16px;
                            cursor: pointer;
                            font-size: 14px;
                        ">閉じる</button>
                    </div>
                    <div id="ktp-service-selector-content">
                        <div style="text-align: center; padding: 40px;">
                            <div style="font-size: 16px; color: #666;">サービス一覧を読み込み中...</div>
                        </div>
                    </div>
                </div>
            </div>
        `;

        // ポップアップを追加
        $('body').append(popupHtml);

        // 閉じるボタンのイベント
        $('#ktp-service-selector-close').on('click.service-selector', function (e) {
            e.preventDefault();
            e.stopPropagation();
            console.log('[SERVICE SELECTOR] 閉じるボタンクリック');
            $('#ktp-service-selector-popup').remove();
            $(document).off('keyup.service-selector');
        });

        // 背景クリックで閉じる
        $('#ktp-service-selector-popup').on('click.service-selector', function (e) {
            if (e.target === this) {
                console.log('[SERVICE SELECTOR] 背景クリックで閉じる');
                $(this).remove();
                $(document).off('keyup.service-selector');
            }
        });

        // ESCキーで閉じる
        $(document).on('keyup.service-selector', function (e) {
            if (e.key === 'Escape') {
                console.log('[SERVICE SELECTOR] ESCキーで閉じる');
                $('#ktp-service-selector-popup').remove();
                $(document).off('keyup.service-selector');
            }
        });

        // サービスリストを読み込み
        loadServiceList(targetRow, mode);
    };

    // サービスリストの読み込み
    function loadServiceList(targetRow, mode) {
        console.log('[SERVICE SELECTOR] サービスリスト読み込み開始');

        // Ajax設定の取得
        let ajaxUrl = '/wp-admin/admin-ajax.php';
        let nonce = '';

        if (typeof ktp_service_ajax_object !== 'undefined') {
            ajaxUrl = ktp_service_ajax_object.ajax_url;
            nonce = ktp_service_ajax_object.nonce;
        } else if (typeof ktp_ajax_object !== 'undefined') {
            ajaxUrl = ktp_ajax_object.ajax_url;
            nonce = ktp_ajax_object.nonce;
        } else if (typeof ajaxurl !== 'undefined') {
            ajaxUrl = ajaxurl;
        }

        // nonceの取得優先順位
        if (!nonce) {
            if (typeof ktp_ajax_nonce !== 'undefined') {
                nonce = ktp_ajax_nonce;
            } else if (typeof window.ktpwp_ajax !== 'undefined' && window.ktpwp_ajax.nonces) {
                nonce = window.ktpwp_ajax.nonces.auto_save || window.ktpwp_ajax.nonces.general || '';
            }
        }

        console.log('[SERVICE SELECTOR] Ajax設定', { ajaxUrl, nonce: nonce ? '設定済み' : '未設定' });

        const ajaxData = {
            action: 'ktp_get_service_list',
            nonce: nonce,
            page: 1,
            limit: 20
        };

        console.log('[SERVICE SELECTOR] Ajax送信データ', ajaxData);
        console.log('[SERVICE SELECTOR] Ajax URL:', ajaxUrl);
        console.log('[SERVICE SELECTOR] 使用可能なグローバル変数:', {
            ktp_service_ajax_object: typeof ktp_service_ajax_object,
            ktp_ajax_object: typeof ktp_ajax_object,
            ajaxurl: typeof ajaxurl,
            ktp_ajax_nonce: typeof ktp_ajax_nonce
        });

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: ajaxData,
            success: function (response) {
                console.log('[SERVICE SELECTOR] サービスリスト取得成功', response);
                try {
                    renderServiceList(response, targetRow, mode);
                } catch (renderError) {
                    console.error('[SERVICE SELECTOR] レンダリングエラー', renderError);
                    $('#ktp-service-selector-content').html(`
                        <div style="text-align: center; padding: 40px; color: #dc3545;">
                            <div style="font-size: 16px;">サービスリストの表示に失敗しました</div>
                            <div style="font-size: 14px; margin-top: 8px;">データの処理中にエラーが発生しました</div>
                        </div>
                    `);
                }
            },
            error: function (xhr, status, error) {
                console.error('[SERVICE SELECTOR] サービスリスト取得エラー', { 
                    status, 
                    error, 
                    responseText: xhr.responseText,
                    statusCode: xhr.status,
                    ajaxData: ajaxData,
                    ajaxUrl: ajaxUrl
                });
                
                let errorMessage = 'サービスリストの読み込みに失敗しました';
                if (xhr.status === 403) {
                    errorMessage = '権限がありません。ログインを確認してください。';
                } else if (xhr.status === 404) {
                    errorMessage = 'サービスが見つかりませんでした。';
                } else if (xhr.status === 500) {
                    errorMessage = 'サーバーエラーが発生しました。';
                }
                
                $('#ktp-service-selector-content').html(`
                    <div style="text-align: center; padding: 40px; color: #dc3545;">
                        <div style="font-size: 16px;">${errorMessage}</div>
                        <div style="font-size: 14px; margin-top: 8px;">ステータス: ${xhr.status} ${status}</div>
                        <div style="font-size: 14px; margin-top: 4px;">再度お試しください</div>
                    </div>
                `);
            }
        });
    }

    // サービスリストの表示
    function renderServiceList(response, targetRow, mode) {
        try {
            const result = typeof response === 'string' ? JSON.parse(response) : response;
            
            if (!result.success || !result.data) {
                throw new Error('無効なレスポンス形式');
            }

            const services = result.data.services || [];
            const pagination = result.data.pagination || {};

            let html = '';

            if (services.length === 0) {
                html = `
                    <div style="text-align: center; padding: 40px; color: #666;">
                        <div style="font-size: 16px;">登録されているサービスがありません</div>
                        <div style="font-size: 14px; margin-top: 8px;">サービスタブから先にサービスを登録してください</div>
                    </div>
                `;
            } else {
                // サービス一覧テーブル
                html += `
                    <div style="margin-bottom: 20px;">
                        <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
                            <thead>
                                <tr style="background: #f8f9fa;">
                                    <th style="border: 1px solid #ddd; padding: 12px; text-align: left;">ID</th>
                                    <th style="border: 1px solid #ddd; padding: 12px; text-align: left;">サービス名</th>
                                    <th style="border: 1px solid #ddd; padding: 12px; text-align: right;">単価</th>
                                    <th style="border: 1px solid #ddd; padding: 12px; text-align: center;">単位</th>
                                    <th style="border: 1px solid #ddd; padding: 12px; text-align: center;">カテゴリー</th>
                                    <th style="border: 1px solid #ddd; padding: 12px; text-align: center;">操作</th>
                                </tr>
                            </thead>
                            <tbody>
                `;

                services.forEach(function (service) {
                    const serviceId = service.id;
                    const serviceName = service.service_name || '';
                    const price = parseInt(service.price) || 0;
                    const unit = service.unit || '式';
                    const category = service.category || '';

                    html += `
                        <tr style="border-bottom: 1px solid #eee;">
                            <td style="border: 1px solid #ddd; padding: 10px;">${serviceId}</td>
                            <td style="border: 1px solid #ddd; padding: 10px;">
                                <strong>${escapeHtml(serviceName)}</strong>
                            </td>
                            <td style="border: 1px solid #ddd; padding: 10px; text-align: right;">
                                ${price.toLocaleString()}円
                            </td>
                            <td style="border: 1px solid #ddd; padding: 10px; text-align: center;">
                                ${escapeHtml(unit)}
                            </td>
                            <td style="border: 1px solid #ddd; padding: 10px; text-align: center;">
                                ${escapeHtml(category)}
                            </td>
                            <td style="border: 1px solid #ddd; padding: 10px; text-align: center;">
                                <button type="button" 
                                        class="ktp-service-selector-add" 
                                        data-service-id="${serviceId}"
                                        data-service-name="${escapeHtml(serviceName)}"
                                        data-price="${price}"
                                        data-unit="${escapeHtml(unit)}"
                                        data-mode="add"
                                        style="background: #28a745; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; margin-right: 4px; font-size: 12px;">
                                    追加
                                </button>
                                <button type="button" 
                                        class="ktp-service-selector-update" 
                                        data-service-id="${serviceId}"
                                        data-service-name="${escapeHtml(serviceName)}"
                                        data-price="${price}"
                                        data-unit="${escapeHtml(unit)}"
                                        data-mode="update"
                                        style="background: #007bff; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 12px;">
                                    更新
                                </button>
                            </td>
                        </tr>
                    `;
                });

                html += `
                            </tbody>
                        </table>
                    </div>
                `;

                // ページネーション（将来の拡張用）
                if (pagination.total_pages > 1) {
                    html += `
                        <div style="text-align: center; margin-top: 20px;">
                            <div style="color: #666; font-size: 14px;">
                                ${pagination.current_page} / ${pagination.total_pages} ページ
                                (全 ${pagination.total_items} 件)
                            </div>
                        </div>
                    `;
                }
            }

            $('#ktp-service-selector-content').html(html);

            // ボタンイベントの設定
            $('.ktp-service-selector-add').on('click', function () {
                const serviceData = {
                    id: $(this).data('service-id'),
                    name: $(this).data('service-name'),
                    price: $(this).data('price'),
                    unit: $(this).data('unit')
                };
                addServiceToInvoice(serviceData, targetRow);
            });

            $('.ktp-service-selector-update').on('click', function () {
                const serviceData = {
                    id: $(this).data('service-id'),
                    name: $(this).data('service-name'),
                    price: $(this).data('price'),
                    unit: $(this).data('unit')
                };
                updateServiceInInvoice(serviceData, targetRow);
            });

        } catch (e) {
            console.error('[SERVICE SELECTOR] レスポンス解析エラー', e);
            $('#ktp-service-selector-content').html(`
                <div style="text-align: center; padding: 40px; color: #dc3545;">
                    <div style="font-size: 16px;">データの解析に失敗しました</div>
                    <div style="font-size: 14px; margin-top: 8px;">再度お試しください</div>
                </div>
            `);
        }
    }

    // サービスを新規行に追加
    function addServiceToInvoice(serviceData, targetRow) {
        console.log('[SERVICE SELECTOR] サービス追加', { serviceData, targetRow });

        // 新しい行を作成
        const tbody = targetRow.closest('tbody');
        const newIndex = tbody.find('tr').length;
        
        const newRowHtml = `
            <tr class="invoice-item-row" data-row-id="0" data-newly-added="true">
                <td class="actions-column">
                    <span class="drag-handle" title="ドラッグして並び替え">&#9776;</span><button type="button" class="btn-add-row" title="行を追加">+</button><button type="button" class="btn-delete-row" title="行を削除">×</button><button type="button" class="btn-move-row" title="サービス選択">></button>
                </td>
                <td>
                    <input type="text" name="invoice_items[${newIndex}][product_name]" class="invoice-item-input product-name" value="${serviceData.name}">
                    <input type="hidden" name="invoice_items[${newIndex}][id]" value="0">
                </td>
                <td style="text-align:left;">
                    <input type="number" name="invoice_items[${newIndex}][price]" class="invoice-item-input price" value="${serviceData.price}" step="1" min="0" style="text-align:left;">
                </td>
                <td style="text-align:left;">
                    <input type="number" name="invoice_items[${newIndex}][quantity]" class="invoice-item-input quantity" value="1" step="1" min="0" style="text-align:left;">
                </td>
                <td>
                    <input type="text" name="invoice_items[${newIndex}][unit]" class="invoice-item-input unit" value="${serviceData.unit}">
                </td>
                <td style="text-align:left;">
                    <input type="number" name="invoice_items[${newIndex}][amount]" class="invoice-item-input amount" value="${serviceData.price}" step="1" readonly style="text-align:left;">
                </td>
                <td>
                    <input type="text" name="invoice_items[${newIndex}][remarks]" class="invoice-item-input remarks" value="">
                    <input type="hidden" name="invoice_items[${newIndex}][sort_order]" value="${newIndex + 1}">
                </td>
            </tr>
        `;

        // 新しい行を追加
        tbody.append(newRowHtml);
        const $newRow = tbody.find('tr').last();

        // 金額を計算
        if (typeof calculateAmount === 'function') {
            calculateAmount($newRow);
        }

        // 合計と利益を更新
        if (typeof updateTotalAndProfit === 'function') {
            updateTotalAndProfit();
        }

        // データベースに保存
        const orderId = $('input[name="order_id"]').val() || $('#order_id').val();
        if (orderId && typeof window.ktpInvoiceCreateNewItem === 'function') {
            window.ktpInvoiceCreateNewItem('invoice', 'product_name', serviceData.name, orderId, $newRow, function(success, newItemId) {
                if (success && newItemId) {
                    $newRow.find('input[name*="[id]"]').val(newItemId);
                    $newRow.removeAttr('data-newly-added');
                    console.log('[SERVICE SELECTOR] 新規サービス保存成功', newItemId);
                }
            });
        }

        // ポップアップを閉じる
        $('#ktp-service-selector-popup').remove();

        // 成功メッセージ
        showMessage('サービスを新規行に追加しました', 'success');
    }

    // サービスで既存行を更新
    function updateServiceInInvoice(serviceData, targetRow) {
        console.log('[SERVICE SELECTOR] サービス更新', { serviceData, targetRow });

        // 対象行のフィールドを更新
        targetRow.find('.product-name').val(serviceData.name);
        targetRow.find('.price').val(serviceData.price);
        targetRow.find('.unit').val(serviceData.unit);
        
        // 数量はデフォルトで1に設定
        targetRow.find('.quantity').val(1);

        // 金額を再計算
        if (typeof calculateAmount === 'function') {
            calculateAmount(targetRow);
        }

        // 合計と利益を更新
        if (typeof updateTotalAndProfit === 'function') {
            updateTotalAndProfit();
        }

        // データベースに保存
        const itemId = targetRow.find('input[name*="[id]"]').val();
        const orderId = $('input[name="order_id"]').val() || $('#order_id').val();
        
        if (orderId && itemId && itemId !== '0' && typeof window.ktpInvoiceAutoSaveItem === 'function') {
            // 各フィールドを順次保存
            window.ktpInvoiceAutoSaveItem('invoice', itemId, 'product_name', serviceData.name, orderId);
            window.ktpInvoiceAutoSaveItem('invoice', itemId, 'price', serviceData.price, orderId);
            window.ktpInvoiceAutoSaveItem('invoice', itemId, 'unit', serviceData.unit, orderId);
            window.ktpInvoiceAutoSaveItem('invoice', itemId, 'quantity', 1, orderId);
        }

        // ポップアップを閉じる
        $('#ktp-service-selector-popup').remove();

        // 成功メッセージ
        showMessage('サービス情報で行を更新しました', 'success');
    }

    // メッセージ表示
    function showMessage(message, type = 'info') {
        const className = type === 'success' ? 'notice-success' : type === 'error' ? 'notice-error' : 'notice-info';
        const messageHtml = `
            <div class="notice ${className} is-dismissible" style="
                position: fixed;
                top: 32px;
                right: 20px;
                z-index: 10001;
                background: white;
                border-left: 4px solid ${type === 'success' ? '#46b450' : type === 'error' ? '#dc3232' : '#0073aa'};
                box-shadow: 0 2px 5px rgba(0,0,0,0.2);
                padding: 12px 16px;
                margin: 0;
                max-width: 300px;
            ">
                <p style="margin: 0; font-size: 14px;">${message}</p>
            </div>
        `;

        $('body').append(messageHtml);

        // 3秒後に自動で削除
        setTimeout(function () {
            $('.notice.is-dismissible').fadeOut(300, function () {
                $(this).remove();
            });
        }, 3000);
    }

    // HTMLエスケープ関数
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function (m) { return map[m]; });
    }

})(jQuery);
