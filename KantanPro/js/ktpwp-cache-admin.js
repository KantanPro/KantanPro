/**
 * KantanPro Cache Management JavaScript
 * 
 * 管理画面でのキャッシュ管理機能
 */

(function($) {
    'use strict';

    // 管理画面の準備完了後に実行
    $(document).ready(function() {
        
        // キャッシュクリアボタンを設定ページに追加
        if ($('#ktp-settings-form').length) {
            addCacheManagementSection();
        }
        
        // キャッシュクリアボタンのクリックイベント
        $(document).on('click', '#ktpwp-clear-cache-btn', function(e) {
            e.preventDefault();
            clearCache();
        });
    });

    /**
     * キャッシュ管理セクションを追加
     */
    function addCacheManagementSection() {
        var cacheSection = `
            <div class="ktpwp-cache-management" style="margin-top: 20px; padding: 15px; border: 1px solid #ddd; background: #f9f9f9;">
                <h3>🚀 キャッシュ管理</h3>
                <p>パフォーマンス向上のため、データベースクエリ結果をキャッシュしています。</p>
                <p>データに問題がある場合は、キャッシュをクリアしてください。</p>
                <button type="button" id="ktpwp-clear-cache-btn" class="button button-secondary">
                    キャッシュをクリア
                </button>
                <div id="ktpwp-cache-status" style="margin-top: 10px;"></div>
            </div>
        `;
        
        // 設定フォームの最後に追加
        $('#ktp-settings-form').append(cacheSection);
    }

    /**
     * キャッシュをクリア
     */
    function clearCache() {
        var $button = $('#ktpwp-clear-cache-btn');
        var $status = $('#ktpwp-cache-status');
        
        // ボタンを無効化
        $button.prop('disabled', true).text('処理中...');
        $status.html('');
        
        // AJAX リクエスト
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'ktpwp_clear_cache',
                nonce: ktpwp_cache_admin.nonce || ''
            },
            success: function(response) {
                if (response.success) {
                    $status.html('<span style="color: green;">✓ ' + response.data + '</span>');
                } else {
                    $status.html('<span style="color: red;">✗ ' + response.data + '</span>');
                }
            },
            error: function(xhr, status, error) {
                $status.html('<span style="color: red;">✗ エラーが発生しました: ' + error + '</span>');
            },
            complete: function() {
                // ボタンを再有効化
                $button.prop('disabled', false).text('キャッシュをクリア');
            }
        });
    }

})(jQuery);
