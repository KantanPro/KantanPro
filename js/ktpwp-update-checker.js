/**
 * KantanPro更新チェッカー JavaScript
 * 
 * @package KantanPro
 * @since 1.0.4
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        // 更新チェックリンクのクリックイベント
        $('#ktpwp-manual-check').on('click', function(e) {
            e.preventDefault();
            
            var $link = $(this);
            var originalText = $link.text();
            
            // リンクを無効化し、テキストを変更
            $link.text(ktpwp_update_checker.checking_text)
                 .addClass('disabled')
                 .css('pointer-events', 'none');
            
            // AJAX リクエストを送信
            $.ajax({
                url: ktpwp_update_checker.ajax_url,
                type: 'POST',
                data: {
                    action: 'ktpwp_check_github_update',
                    nonce: ktpwp_update_checker.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // 成功時の処理
                        showUpdateResult(response.data.message, 'success');
                        
                        // 更新が利用可能な場合はページをリロード
                        if (response.data.reload) {
                            setTimeout(function() {
                                window.location.reload();
                            }, 2000);
                        }
                    } else {
                        showUpdateResult(ktpwp_update_checker.error_text, 'error');
                    }
                },
                error: function() {
                    showUpdateResult(ktpwp_update_checker.error_text, 'error');
                },
                complete: function() {
                    // リンクを元に戻す
                    setTimeout(function() {
                        $link.text(originalText)
                             .removeClass('disabled')
                             .css('pointer-events', 'auto');
                    }, 2000);
                }
            });
        });
        
        // キャッシュクリアリンクのクリックイベント
        $('#ktpwp-cache-clear').on('click', function(e) {
            e.preventDefault();
            
            var $link = $(this);
            var originalText = $link.text();
            
            // リンクを無効化し、テキストを変更
            $link.text('キャッシュクリア中...')
                 .addClass('disabled')
                 .css('pointer-events', 'none');
            
            // AJAX リクエストを送信
            $.ajax({
                url: ktpwp_update_checker.ajax_url,
                type: 'POST',
                data: {
                    action: 'ktpwp_clear_plugin_cache',
                    nonce: ktpwp_update_checker.nonce
                },
                success: function(response) {
                    if (response.success) {
                        showUpdateResult(response.data.message, 'success');
                    } else {
                        showUpdateResult(response.data.message || 'キャッシュクリアに失敗しました', 'error');
                    }
                },
                error: function() {
                    showUpdateResult('キャッシュクリア中にエラーが発生しました', 'error');
                },
                complete: function() {
                    // リンクを元に戻す
                    setTimeout(function() {
                        $link.text(originalText)
                             .removeClass('disabled')
                             .css('pointer-events', 'auto');
                    }, 2000);
                }
            });
        });
        
        /**
         * 更新結果を表示
         */
        function showUpdateResult(message, type) {
            // 既存の通知を削除
            $('.ktpwp-update-result').remove();
            
            var noticeClass = 'notice-' + (type === 'success' ? 'success' : 'error');
            
            var $notice = $('<div class="notice ' + noticeClass + ' is-dismissible ktpwp-update-result">')
                .html('<p>' + message + '</p>')
                .hide()
                .insertAfter('.wp-header-end');
            
            // 通知を表示
            $notice.fadeIn();
            
            // 5秒後に自動で非表示
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
            
            // 削除ボタンの処理
            $notice.find('.notice-dismiss').on('click', function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            });
        }
    });
    
})(jQuery); 