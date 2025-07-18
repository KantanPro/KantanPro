/**
 * KantanPro Image Optimizer JavaScript
 * 
 * 管理画面での画像最適化機能
 */

(function($) {
    'use strict';

    // WebP変換関数をグローバルスコープに追加
    window.ktpwpConvertToWebP = function(attachmentId, nonce) {
        var $button = $('button[onclick*="' + attachmentId + '"]');
        var $status = $('#ktpwp-webp-status-' + attachmentId);
        
        // ボタンを無効化
        $button.prop('disabled', true).text('変換中...');
        $status.html('');
        
        // AJAX リクエスト
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'ktpwp_convert_to_webp',
                attachment_id: attachmentId,
                nonce: nonce
            },
            success: function(response) {
                if (response.success) {
                    $status.html('<span style="color: green;">✓ ' + response.data + '</span>');
                    $button.hide();
                } else {
                    $status.html('<span style="color: red;">✗ ' + response.data + '</span>');
                }
            },
            error: function(xhr, status, error) {
                $status.html('<span style="color: red;">✗ エラーが発生しました: ' + error + '</span>');
            },
            complete: function() {
                // ボタンを再有効化
                $button.prop('disabled', false).text('WebPに変換');
            }
        });
    };

    // 管理画面の準備完了後に実行
    $(document).ready(function() {
        
        // 一括WebP変換ボタンを追加
        if ($('.media-frame').length || $('.upload-php').length) {
            addBulkWebPConversion();
        }
        
        // 画像最適化設定セクションを追加
        if ($('#ktp-settings-form').length) {
            addImageOptimizationSettings();
        }
    });

    /**
     * 一括WebP変換機能を追加
     */
    function addBulkWebPConversion() {
        var bulkButton = '<button type="button" id="ktpwp-bulk-webp-convert" class="button button-secondary" style="margin-left: 10px;">選択した画像をWebPに変換</button>';
        
        // メディアライブラリの一括操作エリアに追加
        $('.bulkactions').first().append(bulkButton);
        
        $('#ktpwp-bulk-webp-convert').on('click', function() {
            var selectedIds = [];
            
            // 選択された画像IDを取得
            $('input[name="media[]"]:checked').each(function() {
                selectedIds.push($(this).val());
            });
            
            if (selectedIds.length === 0) {
                alert('変換する画像を選択してください。');
                return;
            }
            
            if (confirm(selectedIds.length + '個の画像をWebPに変換しますか？')) {
                bulkConvertToWebP(selectedIds);
            }
        });
    }

    /**
     * 一括WebP変換を実行
     */
    function bulkConvertToWebP(attachmentIds) {
        var $button = $('#ktpwp-bulk-webp-convert');
        var totalCount = attachmentIds.length;
        var processedCount = 0;
        var successCount = 0;
        
        // プログレスバーを表示
        var progressHtml = '<div id="ktpwp-conversion-progress" style="margin-top: 10px;">' +
                          '<div style="background: #f1f1f1; border-radius: 3px; overflow: hidden;">' +
                          '<div id="ktpwp-progress-bar" style="background: #0073aa; height: 20px; width: 0%; transition: width 0.3s;"></div>' +
                          '</div>' +
                          '<div id="ktpwp-progress-text">0 / ' + totalCount + ' 完了</div>' +
                          '</div>';
        
        $button.after(progressHtml);
        $button.prop('disabled', true).text('変換中...');
        
        // 各画像を順次処理
        processNextImage();
        
        function processNextImage() {
            if (processedCount >= totalCount) {
                // 完了
                $('#ktpwp-progress-text').text('完了: ' + successCount + ' / ' + totalCount + ' 成功');
                $button.prop('disabled', false).text('選択した画像をWebPに変換');
                
                setTimeout(function() {
                    $('#ktpwp-conversion-progress').fadeOut();
                }, 3000);
                
                return;
            }
            
            var attachmentId = attachmentIds[processedCount];
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'ktpwp_convert_to_webp',
                    attachment_id: attachmentId,
                    nonce: ktpwp_image_optimizer.nonce || ''
                },
                success: function(response) {
                    if (response.success) {
                        successCount++;
                    }
                },
                complete: function() {
                    processedCount++;
                    
                    // プログレスバーを更新
                    var progressPercent = (processedCount / totalCount) * 100;
                    $('#ktpwp-progress-bar').css('width', progressPercent + '%');
                    $('#ktpwp-progress-text').text(processedCount + ' / ' + totalCount + ' 完了');
                    
                    // 次の画像を処理
                    setTimeout(processNextImage, 100); // 100ms間隔で処理
                }
            });
        }
    }

    /**
     * 画像最適化設定セクションを追加
     */
    function addImageOptimizationSettings() {
        var imageSection = `
            <div class="ktpwp-image-optimization" style="margin-top: 20px; padding: 15px; border: 1px solid #ddd; background: #f9f9f9;">
                <h3>🖼️ 画像最適化設定</h3>
                <p>WebP形式への自動変換により、画像ファイルサイズを削減します。</p>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">WebP品質</th>
                        <td>
                            <input type="range" id="ktpwp-webp-quality" name="ktpwp_webp_quality" min="50" max="100" value="85" />
                            <span id="ktpwp-quality-value">85</span>%
                            <p class="description">WebP画像の品質を設定します（50-100）。値が高いほど品質が良くなりますが、ファイルサイズも大きくなります。</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">自動変換</th>
                        <td>
                            <label>
                                <input type="checkbox" id="ktpwp-auto-convert" name="ktpwp_auto_convert" checked />
                                画像アップロード時に自動的にWebPに変換する
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">既存画像の変換</th>
                        <td>
                            <button type="button" id="ktpwp-convert-all-images" class="button button-secondary">
                                すべての既存画像をWebPに変換
                            </button>
                            <p class="description">※ この処理には時間がかかる場合があります。</p>
                        </td>
                    </tr>
                </table>
                
                <div id="ktpwp-image-optimization-status" style="margin-top: 10px;"></div>
            </div>
        `;
        
        // 設定フォームの最後に追加
        $('#ktp-settings-form').append(imageSection);
        
        // 品質スライダーのイベント
        $('#ktpwp-webp-quality').on('input', function() {
            $('#ktpwp-quality-value').text($(this).val());
        });
        
        // 全画像変換ボタンのイベント
        $('#ktpwp-convert-all-images').on('click', function() {
            if (confirm('すべての既存画像をWebPに変換しますか？この処理には時間がかかる場合があります。')) {
                convertAllImages();
            }
        });
    }

    /**
     * すべての画像を変換
     */
    function convertAllImages() {
        var $button = $('#ktpwp-convert-all-images');
        var $status = $('#ktpwp-image-optimization-status');
        
        $button.prop('disabled', true).text('変換中...');
        $status.html('<div style="color: #0073aa;">変換を開始しています...</div>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'ktpwp_convert_all_images',
                nonce: ktpwp_image_optimizer.nonce || ''
            },
            success: function(response) {
                if (response.success) {
                    $status.html('<div style="color: green;">✓ ' + response.data + '</div>');
                } else {
                    $status.html('<div style="color: red;">✗ ' + response.data + '</div>');
                }
            },
            error: function(xhr, status, error) {
                $status.html('<div style="color: red;">✗ エラーが発生しました: ' + error + '</div>');
            },
            complete: function() {
                $button.prop('disabled', false).text('すべての既存画像をWebPに変換');
            }
        });
    }

})(jQuery);
