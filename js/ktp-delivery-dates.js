// 納期フィールド自動保存機能
jQuery(document).ready(function($) {
    // 保存処理を関数化
    function saveDeliveryDate($input) {
        var fieldName = $input.attr('name');
        var fieldValue = $input.val();
        var orderId = $input.data('order-id');
        
        if (typeof orderId === 'undefined' || orderId === '') {
            console.error('Order ID not found for delivery date field');
            return;
        }
        
        // デバッグ情報を出力
        console.log('納期保存デバッグ:', {
            fieldName: fieldName,
            fieldValue: fieldValue,
            orderId: orderId,
            ajaxurl: (typeof ajaxurl !== 'undefined' ? ajaxurl : 'undefined'),
            ktp_ajax_object: (typeof ktp_ajax_object !== 'undefined' ? ktp_ajax_object : 'undefined'),
            ktp_ajax_nonce: (typeof ktp_ajax_nonce !== 'undefined' ? ktp_ajax_nonce : 'undefined'),
            nonce_available: (typeof ktp_ajax_nonce !== 'undefined' && ktp_ajax_nonce !== '')
        });
        
        // Ajax保存処理
        $.ajax({
            url: (typeof ajaxurl !== 'undefined' ? ajaxurl : (typeof ktp_ajax_object !== 'undefined' ? ktp_ajax_object.ajax_url : '')),
            type: 'POST',
            data: {
                action: 'ktp_save_delivery_date',
                order_id: orderId,
                field_name: fieldName,
                field_value: fieldValue,
                ktp_ajax_nonce: (typeof ktp_ajax_nonce !== 'undefined' ? (typeof ktp_ajax_nonce === 'object' && ktp_ajax_nonce.value ? ktp_ajax_nonce.value : ktp_ajax_nonce) : '')
            },
            success: function(res) {
                if (res && res.success) {
                    $input.addClass('autosaved');
                    setTimeout(function(){ $input.removeClass('autosaved'); }, 800);
                    
                    // 成功メッセージを表示
                    showNotification('納期を更新しました', 'success');
                } else {
                    $input.addClass('autosave-error');
                    setTimeout(function(){ $input.removeClass('autosave-error'); }, 1200);
                    
                    // エラーメッセージの詳細処理
                    var errorMessage = '保存に失敗しました';
                    if (res && res.data) {
                        if (typeof res.data === 'string') {
                            errorMessage = res.data;
                        } else if (res.data.message) {
                            errorMessage = res.data.message;
                        } else if (typeof res.data === 'object') {
                            errorMessage = JSON.stringify(res.data);
                        }
                    }
                    showNotification('保存エラー: ' + errorMessage, 'error');
                    
                    // デバッグ情報をコンソールに出力
                    console.error('納期保存エラー:', res);
                }
            },
            error: function(xhr, status, error) {
                $input.addClass('autosave-error');
                setTimeout(function(){ $input.removeClass('autosave-error'); }, 1200);
                
                // 詳細なエラー情報を取得
                var errorMessage = '保存中にエラーが発生しました';
                try {
                    if (xhr.responseText) {
                        var response = JSON.parse(xhr.responseText);
                        if (response.data && response.data.message) {
                            errorMessage = response.data.message;
                        }
                    }
                } catch (e) {
                    errorMessage = '通信エラー: ' + status + ' - ' + error;
                }
                
                showNotification(errorMessage, 'error');
                
                // デバッグ情報をコンソールに出力
                console.error('納期保存通信エラー:', {
                    status: status,
                    error: error,
                    responseText: xhr.responseText,
                    statusCode: xhr.status
                });
            }
        });
    }
    
    // 通知メッセージ表示関数
    function showNotification(message, type) {
        // 既存の通知を削除
        $('.ktp-notification').remove();
        
        var bgColor = type === 'success' ? '#4caf50' : '#f44336';
        var notification = $('<div class="ktp-notification" style="position: fixed; top: 20px; right: 20px; background: ' + bgColor + '; color: white; padding: 12px 20px; border-radius: 4px; z-index: 9999; font-size: 14px; box-shadow: 0 2px 10px rgba(0,0,0,0.2);">' + message + '</div>');
        
        $('body').append(notification);
        
        // 3秒後に自動削除
        setTimeout(function() {
            notification.fadeOut(300, function() {
                $(this).remove();
            });
        }, 3000);
    }
    
    // 納期フィールドの変更イベント
    $(document).on('change', '.delivery-date-input', function() {
        saveDeliveryDate($(this));
    });
    
    // 納期フィールドのblurイベント（フォーカスが外れた時）
    $(document).on('blur', '.delivery-date-input', function() {
        saveDeliveryDate($(this));
    });
    
    // Enterキー押下時の処理
    $(document).on('keydown', '.delivery-date-input', function(e) {
        if (e.key === 'Enter' || e.keyCode === 13) {
            e.preventDefault();
            saveDeliveryDate($(this));
            $(this).blur();
        }
    });
    
    // 納期フィールドのスタイル調整
    $(document).on('focus', '.delivery-date-input', function() {
        $(this).addClass('focused');
    });
    
    $(document).on('blur', '.delivery-date-input', function() {
        $(this).removeClass('focused');
    });
    
    // 保存状態の視覚的フィードバック用CSS
    $('<style>')
        .prop('type', 'text/css')
        .html(`
            .delivery-date-input.autosaved {
                border-color: #4caf50 !important;
                background-color: #f1f8e9 !important;
                transition: all 0.3s ease;
            }
            .delivery-date-input.autosave-error {
                border-color: #f44336 !important;
                background-color: #ffebee !important;
                transition: all 0.3s ease;
            }
            .delivery-date-input.focused {
                border-color: #2196f3 !important;
                box-shadow: 0 0 0 2px rgba(33, 150, 243, 0.2) !important;
            }
        `)
        .appendTo('head');
}); 