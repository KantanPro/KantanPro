/**
 * KantanPro更新通知吹き出し JavaScript
 * 
 * @package KantanPro
 * @since 1.0.4
 */

(function($) {
    'use strict';
    
    // グローバル変数
    var updateBalloon = {
        isVisible: false,
        currentUpdateData: null
    };
    
    // スクリプト読み込み確認
    console.log('KantanPro: 更新通知スクリプト開始');
    console.log('KantanPro: jQuery利用可能:', typeof $ !== 'undefined' ? 'はい' : 'いいえ');
    
    $(document).ready(function() {
        // デバッグ情報を出力
        console.log('KantanPro: 更新通知スクリプト読み込み完了');
        console.log('KantanPro: ktpwp_update_ajax:', typeof ktpwp_update_ajax !== 'undefined' ? ktpwp_update_ajax : '未定義');
        console.log('KantanPro: ktpwp_update_data:', typeof ktpwp_update_data !== 'undefined' ? ktpwp_update_data : '未定義');
        
        // ktpwp_update_ajax変数の詳細確認
        if (typeof ktpwp_update_ajax !== 'undefined') {
            console.log('KantanPro: ktpwp_update_ajax.nonce:', ktpwp_update_ajax.nonce);
            console.log('KantanPro: ktpwp_update_ajax.ajax_url:', ktpwp_update_ajax.ajax_url);
            console.log('KantanPro: ktpwp_update_ajax.notifications_enabled:', ktpwp_update_ajax.notifications_enabled);
        } else {
            console.error('KantanPro: ktpwp_update_ajax変数が定義されていません！');
            // 変数が存在しない場合は更新リンクを無効化
            $('#ktpwp-header-update-check').off('click').css('pointer-events', 'none').attr('title', '更新機能が利用できません');
            return;
        }
        
        // nonceが存在しない場合の処理
        if (!ktpwp_update_ajax.nonce) {
            console.error('KantanPro: nonceが設定されていません！');
            $('#ktpwp-header-update-check').off('click').css('pointer-events', 'none').attr('title', 'セキュリティ設定エラー');
            return;
        }
        
        // ヘッダー更新リンクのクリックイベント
        console.log('KantanPro: クリックイベント登録開始');
        $(document).on('click', '#ktpwp-header-update-check', function(e) {
            e.preventDefault();
            console.log('KantanPro: ヘッダー更新リンクがクリックされました');
            checkForUpdates();
        });
        console.log('KantanPro: クリックイベント登録完了');
        
        // 更新リンク要素の存在確認
        var $updateLink = $('#ktpwp-header-update-check');
        console.log('KantanPro: 更新リンク要素:', $updateLink.length > 0 ? '存在' : '不存在');
        if ($updateLink.length > 0) {
            console.log('KantanPro: 更新リンクHTML:', $updateLink.prop('outerHTML'));
        }
        
        // 既存の更新がある場合は吹き出しを表示
        checkExistingUpdate();
    });
    
    /**
     * 更新チェックを実行
     */
    function checkForUpdates() {
        var $link = $('#ktpwp-header-update-check');
        var originalHtml = $link.html();
        
        // 更新通知が無効化されている場合の処理
        if (typeof ktpwp_update_ajax !== 'undefined' && ktpwp_update_ajax.notifications_enabled === false) {
            showUpdateBalloon('更新通知が無効化されています。管理画面の設定で有効にしてください。', false);
            return;
        }
        
        // ローディング状態に変更
        $link.html(typeof KTPSvgIcons !== 'undefined' ? KTPSvgIcons.getIcon('refresh', {'style': 'font-size: 20px; vertical-align: middle; animation: spin 1s linear infinite;'}) : '<span class="material-symbols-outlined" style="font-size: 20px; vertical-align: middle; animation: spin 1s linear infinite;">refresh</span>');
        $link.css('pointer-events', 'none');
        
        console.log('KantanPro: 更新チェック開始');
        console.log('KantanPro: 送信するnonce:', ktpwp_update_ajax.nonce);
        console.log('KantanPro: 送信するデータ:', {
            action: 'ktpwp_check_header_update',
            nonce: ktpwp_update_ajax.nonce
        });
        
        // nonceの最終確認
        if (!ktpwp_update_ajax.nonce) {
            console.error('KantanPro: AJAX送信前にnonceが存在しません！');
            showUpdateBalloon('セキュリティ設定エラーが発生しました。', false);
            return;
        }
        
        // AJAX リクエストを送信
        $.ajax({
            url: ktpwp_update_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'ktpwp_check_header_update',
                nonce: ktpwp_update_ajax.nonce
            },
            timeout: 30000, // 30秒タイムアウト
            success: function(response) {
                console.log('KantanPro: AJAX レスポンス受信', response);
                console.log('KantanPro: レスポンス詳細', {
                    success: response.success,
                    data: response.data,
                    message: response.data ? response.data.message : 'なし'
                });
                
                if (response.success) {
                    if (response.data.has_update) {
                        // 更新がある場合は吹き出しを表示
                        showUpdateBalloon(response.data.message, true, response.data.update_data);
                    } else {
                        // 更新がない場合
                        var message = response.data.message;
                        if (response.data.notifications_disabled) {
                            message = '更新通知が無効化されています。管理画面の設定で有効にしてください。';
                        }
                        showUpdateBalloon(message, false);
                    }
                } else {
                    var errorMessage = '更新チェック中にエラーが発生しました。';
                    if (response.data && response.data.message) {
                        errorMessage = response.data.message;
                    }
                    if (response.data && response.data.error_type) {
                        console.error('KantanPro: エラータイプ:', response.data.error_type);
                    }
                    showUpdateBalloon(errorMessage, false);
                }
            },
            error: function(xhr, status, error) {
                console.error('KantanPro: AJAX エラー', {
                    status: status,
                    error: error,
                    xhr: xhr,
                    responseText: xhr.responseText,
                    statusText: xhr.statusText
                });
                
                var errorMessage = 'ネットワークエラーが発生しました。';
                
                if (status === 'timeout') {
                    errorMessage = '更新チェックがタイムアウトしました。しばらく時間をおいて再試行してください。';
                } else if (status === 'error') {
                    if (xhr.status === 403) {
                        errorMessage = 'アクセスが拒否されました。権限を確認してください。';
                    } else if (xhr.status === 500) {
                        errorMessage = 'サーバーエラーが発生しました。';
                    } else if (xhr.status !== 0) {
                        errorMessage = 'HTTPエラー ' + xhr.status + ' が発生しました。';
                    }
                }
                
                showUpdateBalloon(errorMessage, false);
            },
            complete: function() {
                // リンクを元に戻す
                setTimeout(function() {
                    $link.html(originalHtml);
                    $link.css('pointer-events', 'auto');
                }, 1000);
            }
        });
    }
    
    /**
     * 既存の更新があるかチェック
     */
    function checkExistingUpdate() {
        // 更新情報を取得（サーバーサイドで設定された変数を使用）
        if (typeof ktpwp_update_data !== 'undefined' && ktpwp_update_data.has_update) {
            showUpdateBalloon(ktpwp_update_data.message, true, ktpwp_update_data.update_data);
        }
    }
    
    /**
     * 更新通知吹き出しを表示
     */
    function showUpdateBalloon(message, hasUpdate, updateData) {
        console.log('KantanPro: 吹き出し表示', {
            message: message,
            hasUpdate: hasUpdate,
            updateData: updateData,
            isVisible: updateBalloon.isVisible
        });
        
        if (updateBalloon.isVisible) {
            console.log('KantanPro: 吹き出しが既に表示中です');
            return;
        }
        
        updateBalloon.isVisible = true;
        updateBalloon.currentUpdateData = updateData;
        
        // オーバーレイを作成
        var $overlay = $('<div class="ktpwp-update-balloon-overlay"></div>');
        
        // 吹き出しを作成
        var $balloon = createBalloonHTML(message, hasUpdate, updateData);
        
        // DOMに追加
        $('body').append($overlay).append($balloon);
        
        // アニメーションで表示
        setTimeout(function() {
            $overlay.addClass('show');
            $balloon.addClass('show slide-in');
            console.log('KantanPro: 吹き出し表示完了');
        }, 10);
        
        // イベントハンドラーを設定
        setupBalloonEvents($balloon, $overlay);
    }
    
    /**
     * 吹き出しHTMLを作成
     */
    function createBalloonHTML(message, hasUpdate, updateData) {
        var title = hasUpdate ? '更新が利用可能です' : '更新チェック完了';
        var icon = hasUpdate ? 'update' : 'check_circle';
        var contentIcon = hasUpdate ? '?' : '✓';
        var versionInfo = '';
        
        // エラーメッセージの場合はアイコンを変更
        if (message.includes('エラー') || message.includes('タイムアウト') || message.includes('拒否')) {
            icon = 'warning';
            title = 'エラーが発生しました';
            contentIcon = '!';
        }
        
        if (hasUpdate && updateData && updateData.new_version) {
            versionInfo = '<div class="ktpwp-update-balloon-version">新しいバージョン: <strong>' + updateData.new_version + '</strong></div>';
        } else if (hasUpdate && updateData && updateData.version) {
            versionInfo = '<div class="ktpwp-update-balloon-version">新しいバージョン: <strong>' + updateData.version + '</strong></div>';
        }
        
        var actions = '';
        if (hasUpdate) {
            actions = '<div class="ktpwp-update-balloon-actions">' +
                     '<button class="ktpwp-update-balloon-btn ktpwp-update-balloon-btn-secondary" id="ktpwp-dismiss-update">後で</button>' +
                     '<a href="' + (typeof ktpwp_update_ajax !== 'undefined' ? ktpwp_update_ajax.admin_url : '/wp-admin/') + 'plugins.php" class="ktpwp-update-balloon-btn ktpwp-update-balloon-btn-primary">管理画面で更新</a>' +
                     '</div>';
        } else {
            actions = '<div class="ktpwp-update-balloon-actions">' +
                     '<button class="ktpwp-update-balloon-btn ktpwp-update-balloon-btn-primary" id="ktpwp-close-balloon">OK</button>' +
                     '</div>';
        }
        
        return $('<div class="ktpwp-update-balloon">' +
                '<div class="ktpwp-update-balloon-header">' +
                '<h3 class="ktpwp-update-balloon-title">' +
                '<span class="dashicons dashicons-' + icon + '"></span>' +
                title +
                '</h3>' +
                '<button class="ktpwp-update-balloon-close" id="ktpwp-close-balloon-x">&times;</button>' +
                '</div>' +
                '<div class="ktpwp-update-balloon-content">' +
                '<div class="ktpwp-update-balloon-icon">' + contentIcon + '</div>' +
                '<div class="ktpwp-update-balloon-message">' + message + '</div>' +
                '</div>' +
                versionInfo +
                actions +
                '</div>');
    }
    
    /**
     * 吹き出しのイベントハンドラーを設定
     */
    function setupBalloonEvents($balloon, $overlay) {
        // 閉じるボタン
        $balloon.on('click', '#ktpwp-close-balloon, #ktpwp-close-balloon-x', function(e) {
            e.preventDefault();
            hideUpdateBalloon($balloon, $overlay);
        });
        
        // 後でボタン（更新がある場合）
        $balloon.on('click', '#ktpwp-dismiss-update', function(e) {
            e.preventDefault();
            dismissUpdateNotice();
            hideUpdateBalloon($balloon, $overlay);
        });
        
        // オーバーレイクリックで閉じる
        $overlay.on('click', function(e) {
            if (e.target === this) {
                hideUpdateBalloon($balloon, $overlay);
            }
        });
        
        // ESCキーで閉じる
        $(document).on('keydown.ktpwp-balloon', function(e) {
            if (e.keyCode === 27) { // ESC
                hideUpdateBalloon($balloon, $overlay);
            }
        });
    }
    
    /**
     * 更新通知吹き出しを非表示
     */
    function hideUpdateBalloon($balloon, $overlay) {
        $balloon.removeClass('show').addClass('slide-out');
        $overlay.removeClass('show');
        
        setTimeout(function() {
            $balloon.remove();
            $overlay.remove();
            updateBalloon.isVisible = false;
            $(document).off('keydown.ktpwp-balloon');
        }, 300);
    }
    
    /**
     * 更新通知を無視
     */
    function dismissUpdateNotice() {
        if (typeof ktpwp_update_ajax !== 'undefined') {
            console.log('KantanPro: 更新通知を無視します');
            $.post(ktpwp_update_ajax.ajax_url, {
                action: 'ktpwp_dismiss_header_update_notice',
                nonce: ktpwp_update_ajax.dismiss_nonce
            }).done(function(response) {
                console.log('KantanPro: 無視処理完了', response);
            }).fail(function(xhr, status, error) {
                console.error('KantanPro: 無視処理エラー', {xhr: xhr, status: status, error: error});
            });
        } else {
            console.error('KantanPro: ktpwp_update_ajax変数が定義されていません');
        }
    }
    
    /**
     * CSSスピンアニメーション
     */
    $('<style>')
        .prop('type', 'text/css')
        .html('@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }')
        .appendTo('head');
        
})(jQuery); 