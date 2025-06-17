/**
 * KTPWP Plugin Reference Modal Script
 * プラグインリファレンスモーダル用JavaScript
 */

(function ($) {
    'use strict';

    $(document).ready(function () {
        // リファレンスリンクのクリックイベント
        $(document).on('click', '.ktpwp-reference-link', function (e) {
            e.preventDefault();
            openReferenceModal();
        });

        // モーダルを開く関数
        function openReferenceModal() {
            // モーダルが存在しない場合は作成（フォールバック）
            if ($('#ktpwp-reference-modal').length === 0) {
                createModalHTML();
            }

            // モーダルを表示
            $('#ktpwp-reference-modal').fadeIn(300);
            $('body').addClass('ktpwp-modal-open');

            // 初期セクションを読み込み
            loadReferenceSection('overview');
        }

        // モーダルHTMLを作成
        function createModalHTML() {
            var modalHTML = `
                <div id="ktpwp-reference-modal" class="ktpwp-modal" style="display: none;">
                    <div class="ktpwp-modal-overlay">
                        <div class="ktpwp-modal-content">
                            <div class="ktpwp-modal-header">
                                <h3>${ktpwp_reference.strings.modal_title}</h3>
                                <button type="button" class="ktpwp-modal-close" aria-label="${ktpwp_reference.strings.close}">&times;</button>
                            </div>
                            <div class="ktpwp-modal-body">
                                <div class="ktpwp-reference-sidebar">
                                    <ul class="ktpwp-reference-nav">
                                        <li><a href="#" data-section="overview" class="active">${ktpwp_reference.strings.nav_overview}</a></li>
                                        <li><a href="#" data-section="tabs">${ktpwp_reference.strings.nav_tabs}</a></li>
                                        <li><a href="#" data-section="shortcodes">${ktpwp_reference.strings.nav_shortcodes}</a></li>
                                        <li><a href="#" data-section="settings">${ktpwp_reference.strings.nav_settings}</a></li>
                                        <li><a href="#" data-section="security">${ktpwp_reference.strings.nav_security}</a></li>
                                        <li><a href="#" data-section="troubleshooting">${ktpwp_reference.strings.nav_troubleshooting}</a></li>
                                    </ul>
                                </div>
                                <div class="ktpwp-reference-content">
                                    <div id="ktpwp-reference-loading" style="display: none;">
                                        <p>${ktpwp_reference.strings.loading}</p>
                                    </div>
                                    <div id="ktpwp-reference-text"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            $('body').append(modalHTML);
        }

        // モーダルを閉じる
        $(document).on('click', '.ktpwp-modal-close, .ktpwp-modal-overlay', function (e) {
            if (e.target === this) {
                closeReferenceModal();
            }
        });

        // ESCキーでモーダルを閉じる
        $(document).on('keydown', function (e) {
            if (e.keyCode === 27 && $('#ktpwp-reference-modal').is(':visible')) {
                closeReferenceModal();
            }
        });

        // モーダルを閉じる関数
        function closeReferenceModal() {
            $('#ktpwp-reference-modal').fadeOut(300);
            $('body').removeClass('ktpwp-modal-open');
        }

        // ナビゲーションクリックイベント
        $(document).on('click', '.ktpwp-reference-nav a', function (e) {
            e.preventDefault();
            var section = $(this).data('section');

            // アクティブ状態を切り替え
            $('.ktpwp-reference-nav a').removeClass('active');
            $(this).addClass('active');

            // セクションを読み込み
            loadReferenceSection(section);
        });

        // キャッシュクリアボタンクリックイベント
        $(document).on('click', '.ktpwp-clear-cache-btn', function (e) {
            e.preventDefault();
            clearReferenceCache();
        });

        // リファレンスセクションを読み込む
        function loadReferenceSection(section) {
            var $loading = $('#ktpwp-reference-loading');
            var $content = $('#ktpwp-reference-text');

            // ローディング表示
            $loading.show();
            $content.hide();

            // Ajax でセクションデータを取得（キャッシュ無効化）
            $.ajax({
                url: ktpwp_reference.ajax_url,
                type: 'POST',
                cache: false,
                data: {
                    action: 'ktpwp_get_reference',
                    nonce: ktpwp_reference.nonce,
                    section: section,
                    _timestamp: Date.now() // キャッシュバスター
                },
                success: function (response) {
                    $loading.hide();
                    if (response.success) {
                        $content.html(response.data.content).show();

                        // リファレンス更新情報を表示
                        updateReferenceInfo(response.data);
                    } else {
                        $content.html('<p class="error">' + response.data + '</p>').show();
                    }
                },
                error: function () {
                    $loading.hide();
                    $content.html('<p class="error">' + ktpwp_reference.strings.error_loading + '</p>').show();
                }
            });
        }

        // リファレンス更新情報を表示する
        function updateReferenceInfo(data) {
            if (data.last_updated && data.version) {
                var lastUpdated = new Date(data.last_updated * 1000);
                var infoHTML = `
                    <div class="ktpwp-reference-info">
                        <small>
                            <strong>バージョン: ${data.version}</strong> |
                            最終更新: ${lastUpdated.toLocaleDateString('ja-JP')} ${lastUpdated.toLocaleTimeString('ja-JP')}
                        </small>
                    </div>
                `;

                // 既存の情報があれば削除
                $('.ktpwp-reference-info').remove();

                // 新しい情報をヘッダーに追加
                $('.ktpwp-modal-header').append(infoHTML);
                
                // コンソールログでデバッグ情報を出力
                console.log('KTPWP Reference Info Updated:', {
                    version: data.version,
                    last_updated: data.last_updated,
                    formatted_date: lastUpdated.toLocaleString('ja-JP')
                });
            }
        }

        // リファレンス手動更新機能
        function refreshReference() {
            // キャッシュをクリアして再読み込み
            $.ajax({
                url: ktpwp_reference.ajax_url,
                type: 'POST',
                data: {
                    action: 'ktpwp_clear_reference_cache',
                    nonce: ktpwp_reference.nonce
                },
                success: function (response) {
                    if (response.success) {
                        // 現在のセクションを再読み込み
                        var currentSection = $('.ktpwp-reference-nav a.active').data('section') || 'overview';
                        loadReferenceSection(currentSection);
                    }
                }
            });
        }

        // リファレンスキャッシュをクリアする
        function clearReferenceCache() {
            var $btn = $('.ktpwp-clear-cache-btn');
            var originalText = $btn.text();

            $btn.text('クリア中...').prop('disabled', true);

            $.ajax({
                url: ktpwp_reference.ajax_url,
                type: 'POST',
                cache: false,
                data: {
                    action: 'ktpwp_clear_reference_cache',
                    nonce: ktpwp_reference.nonce,
                    _timestamp: Date.now()
                },
                success: function (response) {
                    $btn.text(originalText).prop('disabled', false);

                    if (response.success) {
                        // 現在のアクティブセクションを再読み込み
                        var activeSection = $('.ktpwp-reference-nav a.active').data('section') || 'overview';
                        loadReferenceSection(activeSection);

                        // 成功メッセージを表示
                        showCacheMessage(response.data.message || 'キャッシュをクリアしました', 'success');
                    } else {
                        var errorMsg = response.data && response.data.message ? response.data.message : 'キャッシュクリアに失敗しました';
                        showCacheMessage(errorMsg, 'error');
                    }
                },
                error: function (xhr, status, error) {
                    $btn.text(originalText).prop('disabled', false);
                    showCacheMessage('キャッシュクリアでエラーが発生しました: ' + error, 'error');
                }
            });
        }

        // キャッシュメッセージを表示
        function showCacheMessage(message, type) {
            var messageClass = type === 'success' ? 'success' : 'error';
            var $message = $('<div class="ktpwp-cache-message ' + messageClass + '">' + message + '</div>');

            $('.ktpwp-modal-header').append($message);

            setTimeout(function () {
                $message.fadeOut(function () {
                    $message.remove();
                });
            }, 3000);
        }
    });

})(jQuery);
