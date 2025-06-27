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
                    setTimeout(function() {
                        $input.css('border-color', '#ddd');
                    }, 2000);
                } else {
                    // エラー時の処理
                    $input.css('border-color', '#f44336');
                    alert('保存に失敗しました: ' + (response.data || 'エラーが発生しました'));
                    setTimeout(function() {
                        $input.css('border-color', '#ddd');
                    }, 3000);
                }
            },
            error: function(xhr, status, error) {
                // 通信エラー時の処理
                $input.css('border-color', '#f44336');
                alert('通信エラーが発生しました: ' + error);
                setTimeout(function() {
                    $input.css('border-color', '#ddd');
                }, 3000);
            },
            complete: function() {
                // 処理完了時の処理
                $input.prop('disabled', false);
                $input.css('opacity', '1');
            }
        });
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