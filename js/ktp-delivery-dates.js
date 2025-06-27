/**
 * 納期フィールドのAjax保存機能
 * 
 * @package KTPWP
 * @since 1.0.0
 */

jQuery(document).ready(function($) {
    'use strict';

    // 納期フィールドの変更を監視
    $(document).on('change', '.delivery-date-input', function() {
        var $input = $(this);
        var orderId = $input.data('order-id');
        var field = $input.data('field');
        var value = $input.val();
        
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
                if (response.success) {
                    // 成功時の処理
                    $input.css('border-color', '#4caf50');
                    $input.css('background-color', '#f1f8e9');
                    
                    // 警告マークを更新
                    updateWarningMark($input, value);
                    
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
                var delivery = new Date(deliveryDate);
                var diffTime = delivery.getTime() - today.getTime();
                var diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                
                // 警告日数を取得（デフォルト3日）
                var warningDays = 3;
                if (typeof ktp_ajax !== 'undefined' && ktp_ajax.settings && ktp_ajax.settings.delivery_warning_days) {
                    warningDays = parseInt(ktp_ajax.settings.delivery_warning_days);
                }
                
                // 警告マークを表示するかどうか判定
                if (diffDays <= warningDays && diffDays >= 0) {
                    var warningMark = $('<span class="delivery-warning-mark-row" title="納期が迫っています">!</span>');
                    $wrapper.append(warningMark);
                }
            }
        }
    }

    // ページ読み込み時に既存の警告マークを更新
    function updateAllWarningMarks() {
        $('.delivery-date-input').each(function() {
            var $input = $(this);
            var value = $input.val();
            if (value) {
                updateWarningMark($input, value);
            }
        });
    }

    // ページ読み込み完了後に警告マークを更新
    setTimeout(updateAllWarningMarks, 100);

    // 進捗プルダウンの変更を監視
    $(document).on('change', '.progress-select', function() {
        var $select = $(this);
        var $listItem = $select.closest('.ktp_work_list_item');
        var $deliveryInput = $listItem.find('.delivery-date-input');
        
        // 納期フィールドが存在する場合、警告マークを更新
        if ($deliveryInput.length > 0) {
            var deliveryDate = $deliveryInput.val();
            updateWarningMark($deliveryInput, deliveryDate);
        }
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