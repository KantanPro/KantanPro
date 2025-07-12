/**
 * KantanPro 寄付通知 JavaScript
 * 
 * @package KantanPro
 * @since 1.0.0
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        var $notice = $('#ktpwp-donation-notice');
        
        if ($notice.length === 0) {
            return;
        }

        // 通知を表示
        function showNotice() {
            $notice.fadeIn(500);
        }

        // 通知を非表示
        function hideNotice() {
            $notice.fadeOut(300);
        }

        // 通知を拒否
        function dismissNotice() {
            $.ajax({
                url: ktpwp_donation_notice.ajax_url,
                type: 'POST',
                data: {
                    action: 'ktpwp_dismiss_donation_notice',
                    nonce: ktpwp_donation_notice.nonce
                },
                success: function(response) {
                    if (response.success) {
                        hideNotice();
                    }
                },
                error: function() {
                    console.error('Failed to dismiss donation notice');
                }
            });
        }

        // 閉じるボタンのクリックイベント
        $notice.on('click', '.ktpwp-notice-dismiss-btn', function(e) {
            e.preventDefault();
            dismissNotice();
        });

        // 寄付ボタンのクリックイベント（新しいタブで開く）
        $notice.on('click', '.ktpwp-notice-donate-btn', function(e) {
            // リンクの動作はそのまま（target="_blank"で新しいタブで開く）
            // 必要に応じて追加の処理をここに記述
        });

        // ページ読み込み後、少し遅延してから通知を表示
        setTimeout(function() {
            showNotice();
        }, 2000);

        // キーボードショートカット（ESCキーで閉じる）
        $(document).on('keydown', function(e) {
            if (e.keyCode === 27 && $notice.is(':visible')) { // ESC key
                dismissNotice();
            }
        });

        // 通知外のクリックで閉じる（オプション）
        $(document).on('click', function(e) {
            if ($notice.is(':visible') && !$(e.target).closest('.ktpwp-donation-notice').length) {
                // 通知外をクリックした場合の処理（必要に応じて有効化）
                // dismissNotice();
            }
        });
    });

})(jQuery); 