/**
 * 納期フィールドのAjax保存機能
 * 
 * @package KTPWP
 * @since 1.0.0
 */

jQuery(document).ready(function($) {
    'use strict';

    console.log('[DELIVERY-DATES] 納期警告機能が読み込まれました');
    
    // Ajax設定の確認
    if (typeof ktp_ajax !== 'undefined') {
        console.log('[DELIVERY-DATES] Ajax設定確認:', {
            ajax_url: ktp_ajax.ajax_url,
            nonce: ktp_ajax.nonce ? '設定済み' : '未設定',
            settings: ktp_ajax.settings || '未設定'
        });
    } else {
        console.log('[DELIVERY-DATES] 警告: ktp_ajaxオブジェクトが見つかりません');
    }

    // 納期フィールドの変更を監視
    $(document).on('change', '.delivery-date-input', function() {
        console.log('[DELIVERY-DATES] 納期フィールドが変更されました');
        var $input = $(this);
        var orderId = $input.data('order-id');
        var field = $input.data('field');
        var value = $input.val();
        
        console.log('[DELIVERY-DATES] 変更内容:', { 
            orderId: orderId, 
            field: field, 
            value: value,
            inputId: $input.attr('id'),
            inputName: $input.attr('name')
        });
        
        // フィールド名の検証
        if (!field) {
            console.error('[DELIVERY-DATES] エラー: data-field属性が設定されていません');
            alert('フィールド名が設定されていません。ページを再読み込みしてください。');
            return;
        }
        
        // 保存中の表示
        $input.prop('disabled', true);
        $input.css('opacity', '0.6');
        
        // Ajaxでデータを保存
        $.ajax({
            url: ktp_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'ktp_update_delivery_date',
                order_id: orderId,
                field: field,
                value: value,
                nonce: ktp_ajax.nonce
            },
            success: function(response) {
                console.log('[DELIVERY-DATES] Ajax応答:', response);
                if (response.success) {
                    // 成功時の処理
                    $input.css('border-color', '#4caf50');
                    $input.css('background-color', '#f1f8e9');
                    
                    // 警告マークを更新
                    updateWarningMark($input, value);
                    
                    // 進捗ボタンの警告マークも更新（納期変更時）
                    updateProgressButtonWarning();
                    
                    // 3秒後に元のスタイルに戻す
                    setTimeout(function() {
                        $input.css('border-color', '');
                        $input.css('background-color', '');
                    }, 3000);
                } else {
                    // エラー時の処理
                    $input.css('border-color', '#f44336');
                    $input.css('background-color', '#ffebee');
                    alert('保存に失敗しました: ' + (response.data || 'エラーが発生しました'));
                }
            },
            error: function() {
                // 通信エラー時の処理
                $input.css('border-color', '#f44336');
                $input.css('background-color', '#ffebee');
                alert('通信エラーが発生しました');
            },
            complete: function() {
                // 処理完了時の処理
                $input.prop('disabled', false);
                $input.css('opacity', '1');
            }
        });
    });

    /**
     * 警告マークを更新する関数
     * 
     * @param {jQuery} $input 納期入力フィールド
     * @param {string} deliveryDate 納品予定日
     */
    function updateWarningMark($input, deliveryDate) {
        var $wrapper = $input.closest('.delivery-input-wrapper');
        var $existingWarning = $wrapper.find('.delivery-warning-mark-row');
        
        // 既存の警告マークを削除
        $existingWarning.remove();
        
        // 納期が設定されている場合のみ警告判定を行う
        if (deliveryDate && deliveryDate.trim() !== '') {
            // 進捗が「作成中」かどうかを確認
            var $progressSelect = $input.closest('.ktp_work_list_item').find('.progress-select');
            var currentProgress = parseInt($progressSelect.val());
            
            if (currentProgress === 3) { // 作成中
                // 納期警告の判定
                var today = new Date();
                today.setHours(0, 0, 0, 0); // 時間を00:00:00に設定
                var delivery = new Date(deliveryDate);
                delivery.setHours(0, 0, 0, 0); // 時間を00:00:00に設定
                
                var diffTime = delivery.getTime() - today.getTime();
                var diffDays = Math.floor(diffTime / (1000 * 60 * 60 * 24));
                
                // 警告日数を取得（デフォルト3日）
                var warningDays = 3;
                if (typeof ktp_ajax !== 'undefined' && ktp_ajax.settings && ktp_ajax.settings.delivery_warning_days) {
                    warningDays = parseInt(ktp_ajax.settings.delivery_warning_days);
                }
                
                // デバッグ情報をコンソールに出力
                console.log('[DELIVERY-DATES] 警告計算:', {
                    today: today.toISOString().split('T')[0],
                    delivery: delivery.toISOString().split('T')[0],
                    diffDays: diffDays,
                    warningDays: warningDays,
                    shouldWarn: diffDays <= warningDays && diffDays >= 0
                });
                
                // 警告日数以内で納期が迫っている場合、警告マークを表示
                if (diffDays <= warningDays && diffDays >= 0) {
                    $wrapper.append('<span class="delivery-warning-mark-row" title="納期が迫っています">!</span>');
                    console.log('[DELIVERY-DATES] 警告マークを表示しました');
                } else {
                    console.log('[DELIVERY-DATES] 警告マークは表示しません（条件不適合）');
                }
            }
        }
    }

    /**
     * 進捗ボタンの警告マークを更新する関数
     */
    function updateProgressButtonWarning() {
        console.log('[DELIVERY-DATES] 進捗ボタン警告マーク更新開始');
        
        // Ajaxで作成中の納期警告件数を取得
        $.ajax({
            url: ktp_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'ktp_get_creating_warning_count',
                nonce: ktp_ajax.nonce
            },
            success: function(response) {
                console.log('[DELIVERY-DATES] 作成中警告件数取得応答:', response);
                
                if (response.success) {
                    var warningCount = response.data.warning_count;
                    var warningDays = response.data.warning_days;
                    
                    console.log('[DELIVERY-DATES] 作成中の納期警告件数:', warningCount, '件（警告日数:', warningDays, '日）');
                    
                    // 進捗ボタンの警告マークをチェック
                    var $progressButton = $('.progress-btn').filter(function() {
                        return $(this).text().indexOf('作成中') !== -1;
                    });
                    
                    console.log('[DELIVERY-DATES] 作成中ボタン数:', $progressButton.length);
                    
                    var $existingButtonWarning = $progressButton.find('.delivery-warning-mark');
                    
                    if (warningCount > 0) {
                        // 警告マークがある場合、ボタンにも警告マークを表示
                        if ($existingButtonWarning.length === 0) {
                            $progressButton.append('<span class="delivery-warning-mark" title="納期が迫っている案件があります（' + warningCount + '件）">!</span>');
                            console.log('[DELIVERY-DATES] 進捗ボタンに警告マークを追加しました（' + warningCount + '件）');
                        } else {
                            // 既存の警告マークの件数を更新
                            $existingButtonWarning.attr('title', '納期が迫っている案件があります（' + warningCount + '件）');
                            console.log('[DELIVERY-DATES] 進捗ボタンの警告マークを更新しました（' + warningCount + '件）');
                        }
                    } else {
                        // 警告マークがない場合、ボタンの警告マークを削除
                        $existingButtonWarning.remove();
                        console.log('[DELIVERY-DATES] 進捗ボタンの警告マークを削除しました');
                    }
                } else {
                    console.log('[DELIVERY-DATES] 警告件数取得に失敗:', response.data);
                    // エラー時は既存の警告マークを削除
                    var $progressButton = $('.progress-btn').filter(function() {
                        return $(this).text().indexOf('作成中') !== -1;
                    });
                    $progressButton.find('.delivery-warning-mark').remove();
                }
            },
            error: function(xhr, status, error) {
                console.log('[DELIVERY-DATES] 警告件数取得で通信エラーが発生しました:', {
                    status: status,
                    error: error,
                    responseText: xhr.responseText
                });
                // エラー時は既存の警告マークを削除
                var $progressButton = $('.progress-btn').filter(function() {
                    return $(this).text().indexOf('作成中') !== -1;
                });
                $progressButton.find('.delivery-warning-mark').remove();
            }
        });
    }

    // ページ読み込み時に既存の警告マークを更新
    function updateAllWarningMarks() {
        console.log('[DELIVERY-DATES] 全警告マーク更新開始');
        
        var inputCount = $('.delivery-date-input').length;
        console.log('[DELIVERY-DATES] 納期入力フィールド数:', inputCount);
        
        $('.delivery-date-input').each(function(index) {
            var $input = $(this);
            var value = $input.val();
            console.log('[DELIVERY-DATES] フィールド', index + 1, ':', value);
            
            if (value) {
                updateWarningMark($input, value);
            }
        });
        
        // 進捗ボタンの警告マークも更新（常に作成中の警告件数を取得）
        updateProgressButtonWarning();
        
        console.log('[DELIVERY-DATES] 全警告マーク更新完了');
    }

    // ページ読み込み完了後に警告マークを更新
    setTimeout(function() {
        console.log('[DELIVERY-DATES] ページ読み込み後の警告マーク更新を開始');
        updateAllWarningMarks();
    }, 100);

    // 進捗プルダウンの変更を監視
    $(document).on('change', '.progress-select', function() {
        console.log('[DELIVERY-DATES] 進捗プルダウンが変更されました');
        
        var $select = $(this);
        var $listItem = $select.closest('.ktp_work_list_item');
        var $deliveryInput = $listItem.find('.delivery-date-input');
        
        console.log('[DELIVERY-DATES] 進捗変更:', {
            newProgress: $select.val(),
            hasDeliveryInput: $deliveryInput.length > 0
        });
        
        // 納期フィールドが存在する場合、警告マークを更新
        if ($deliveryInput.length > 0) {
            var deliveryDate = $deliveryInput.val();
            console.log('[DELIVERY-DATES] 納期フィールドあり、更新:', deliveryDate);
            updateWarningMark($deliveryInput, deliveryDate);
        }
        
        // 進捗変更時は常に進捗ボタンの警告マークを更新
        console.log('[DELIVERY-DATES] 進捗変更後のボタン警告マーク更新');
        updateProgressButtonWarning();
    });

    // 納期フィールドのフォーカス時の処理
    $(document).on('focus', '.delivery-date-input', function() {
        $(this).css('border-color', '#1976d2');
    });

    // 納期フィールドのフォーカスアウト時の処理
    $(document).on('blur', '.delivery-date-input', function() {
        $(this).css('border-color', '#ddd');
    });
}); 