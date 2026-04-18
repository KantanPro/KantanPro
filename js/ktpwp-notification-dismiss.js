/**
 * KantanPro 通知非表示機能
 * 
 * @package KTPWP
 * @since 1.0.0
 */

jQuery(document).ready(function($) {
    'use strict';
    
    /**
     * 通知を非表示にする
     */
    window.dismissInvoiceItemsFixNotification = function() {
        if (!confirm('この通知を非表示にしますか？')) {
            return false;
        }
        
        $.ajax({
            url: ktpwp_notification_dismiss.ajaxurl,
            type: 'POST',
            data: {
                action: 'ktpwp_dismiss_invoice_items_fix_notification',
                nonce: ktpwp_notification_dismiss.nonce
            },
            success: function(response) {
                if (response.success) {
                    // 通知を非表示にする
                    $('#ktpwp-invoice-items-fix-notice').fadeOut(300, function() {
                        $(this).remove();
                    });
                    
                    // 成功メッセージを表示
                    if (response.data) {
                        alert(response.data);
                    }
                } else {
                    alert('エラーが発生しました: ' + (response.data || '不明なエラー'));
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                alert('通信エラーが発生しました。ページを再読み込みしてください。');
            }
        });
        
        return false;
    };
    
    // 通知の非表示ボタンにイベントリスナーを追加
    $(document).on('click', '.dismiss-notification-btn', function(e) {
        e.preventDefault();
        dismissInvoiceItemsFixNotification();
    });
}); 