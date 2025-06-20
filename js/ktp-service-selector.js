/**
 * サービス選択ポップアップ機能
 *
 * @package KTPWP
 * @since 1.0.0
 */

(function ($) {
    'use strict';

    // デバッグ用のコンソールログを追加
    console.log('[SERVICE-SELECTOR] スクリプトが読み込まれました');

    // 依存関係チェック
    $(document).ready(function() {
        console.log('[SERVICE-SELECTOR] DOM準備完了');
        console.log('[SERVICE-SELECTOR] jQuery available:', typeof $ !== 'undefined');
        console.log('[SERVICE-SELECTOR] ktp_service_ajax_object available:', typeof ktp_service_ajax_object !== 'undefined');
        if (typeof ktp_service_ajax_object !== 'undefined') {
            console.log('[SERVICE-SELECTOR] Ajax URL:', ktp_service_ajax_object.ajax_url);
            console.log('[SERVICE-SELECTOR] Nonce:', ktp_service_ajax_object.nonce);
        }
    });

    // サービス選択ポップアップの表示
    window.ktpShowServiceSelector = function (targetRow, mode = 'add') {
        console.log('[SERVICE SELECTOR] ポップアップ表示開始', { targetRow, mode });

        const orderId = $('input[name="order_id"]').val() || $('#order_id').val();
        if (!orderId) {
            alert('受注書IDが見つかりません。');
            return;
        }

        // ポップアップHTML
        const popupHtml = `
            <div id="ktp-service-selector-popup" style="
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.5);
                z-index: 10000;
                display: flex;
                justify-content: center;
                align-items: center;
            ">
                <div style="
                    background: white;
                    border-radius: 8px;
                    padding: 15px;
                    width: 95%;
                    max-width: 1000px;
                    max-height: 85%;
                    overflow-y: auto;
                    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
                ">
                    <div style="
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                        margin-bottom: 15px;
                        border-bottom: 1px solid #eee;
                        padding-bottom: 10px;
                    ">
                        <h3 style="margin: 0; color: #333;">サービス選択</h3>
                        <button type="button" id="ktp-service-selector-close" style="
                            background: none;
                            color: #333;
                            border: none;
                            cursor: pointer;
                            font-size: 28px;
                            padding: 0;
                            line-height: 1;
                        ">×</button>
                    </div>
                    <div id="ktp-service-selector-content" style="
                        display: flex;
                        flex-direction: column;
                        width: 100%;
                        box-sizing: border-box;
                    ">
                        <div style="text-align: center; padding: 40px;">
                            <div style="font-size: 16px; color: #666;">サービス一覧を読み込み中...</div>
                        </div>
                    </div>
                </div>
            </div>
        `;

        // ポップアップを追加
        $('body').append(popupHtml);

        // ポップアップを閉じる関数（共通化）
        function closeServiceSelector() {
            $('#ktp-service-selector-popup').remove();
            $(document).off('keyup.service-selector');
            $(document).off('click.ktp-pagination mouseenter.ktp-pagination mouseleave.ktp-pagination');
            $(document).off('mouseenter.ktp-service-item mouseleave.ktp-service-item');
        }

        // 閉じるボタンのイベント
        $('#ktp-service-selector-close').on('click.service-selector', function (e) {
            e.preventDefault();
            e.stopPropagation();
            console.log('[SERVICE SELECTOR] 閉じるボタンクリック');
            closeServiceSelector();
        });

        // 背景クリックで閉じる
        $('#ktp-service-selector-popup').on('click.service-selector', function (e) {
            if (e.target === this) {
                console.log('[SERVICE SELECTOR] 背景クリックで閉じる');
                closeServiceSelector();
            }
        });

        // ESCキーで閉じる
        $(document).on('keyup.service-selector', function (e) {
            if (e.key === 'Escape') {
                console.log('[SERVICE SELECTOR] ESCキーで閉じる');
                closeServiceSelector();
            }
        });

        // サービスリストを読み込み
        loadServiceList(targetRow, mode);
    };

    // サービスリストの読み込み
    function loadServiceList(targetRow, mode, page = 1) {
        console.log('[SERVICE SELECTOR] サービスリスト読み込み開始', { page });

        // ローディング表示
        $('#ktp-service-selector-content').html(`
            <div style="text-align: center; padding: 40px;">
                <div style="font-size: 16px; color: #666;">サービス一覧を読み込み中...</div>
            </div>
        `);

        // Ajax設定の取得
        let ajaxUrl = '/wp-admin/admin-ajax.php';
        let nonce = '';

        if (typeof ktp_service_ajax_object !== 'undefined') {
            ajaxUrl = ktp_service_ajax_object.ajax_url;
            nonce = ktp_service_ajax_object.nonce;
        } else if (typeof ktp_ajax_object !== 'undefined') {
            ajaxUrl = ktp_ajax_object.ajax_url;
            nonce = ktp_ajax_object.nonce;
        } else if (typeof ajaxurl !== 'undefined') {
            ajaxUrl = ajaxurl;
        }

        // nonceの取得優先順位
        if (!nonce) {
            if (typeof ktp_ajax_nonce !== 'undefined') {
                nonce = ktp_ajax_nonce;
            } else if (typeof window.ktpwp_ajax !== 'undefined' && window.ktpwp_ajax.nonces) {
                nonce = window.ktpwp_ajax.nonces.auto_save || window.ktpwp_ajax.nonces.general || '';
            }
        }

        console.log('[SERVICE SELECTOR] Ajax設定', { ajaxUrl, nonce: nonce ? '設定済み' : '未設定' });

        const ajaxData = {
            action: 'ktp_get_service_list',
            nonce: nonce,
            page: page,
            limit: 'auto' // サーバーサイドで一般設定から取得
        };

        console.log('[SERVICE SELECTOR] Ajax送信データ', ajaxData);
        console.log('[SERVICE SELECTOR] Ajax URL:', ajaxUrl);
        console.log('[SERVICE SELECTOR] 使用可能なグローバル変数:', {
            ktp_service_ajax_object: typeof ktp_service_ajax_object,
            ktp_ajax_object: typeof ktp_ajax_object,
            ajaxurl: typeof ajaxurl,
            ktp_ajax_nonce: typeof ktp_ajax_nonce
        });

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: ajaxData,
            success: function (response) {
                console.log('[SERVICE SELECTOR] サービスリスト取得成功', response);
                try {
                    renderServiceList(response, targetRow, mode, page);
                } catch (renderError) {
                    console.error('[SERVICE SELECTOR] レンダリングエラー', renderError);
                    $('#ktp-service-selector-content').html(`
                        <div style="text-align: center; padding: 40px; color: #dc3545;">
                            <div style="font-size: 16px;">サービスリストの表示に失敗しました</div>
                            <div style="font-size: 14px; margin-top: 8px;">データの処理中にエラーが発生しました</div>
                        </div>
                    `);
                }
            },
            error: function (xhr, status, error) {
                console.error('[SERVICE SELECTOR] サービスリスト取得エラー', { 
                    status, 
                    error, 
                    responseText: xhr.responseText,
                    statusCode: xhr.status,
                    ajaxData: ajaxData,
                    ajaxUrl: ajaxUrl
                });
                
                let errorMessage = 'サービスリストの読み込みに失敗しました';
                if (xhr.status === 403) {
                    errorMessage = '権限がありません。ログインを確認してください。';
                } else if (xhr.status === 404) {
                    errorMessage = 'サービスが見つかりませんでした。';
                } else if (xhr.status === 500) {
                    errorMessage = 'サーバーエラーが発生しました。';
                }
                
                $('#ktp-service-selector-content').html(`
                    <div style="text-align: center; padding: 40px; color: #dc3545;">
                        <div style="font-size: 16px;">${errorMessage}</div>
                        <div style="font-size: 14px; margin-top: 8px;">ステータス: ${xhr.status} ${status}</div>
                        <div style="font-size: 14px; margin-top: 4px;">再度お試しください</div>
                    </div>
                `);
            }
        });
    }

    // サービスリストの表示
    function renderServiceList(response, targetRow, mode, currentPage = 1) {
        try {
            const result = typeof response === 'string' ? JSON.parse(response) : response;
            
            if (!result.success || !result.data) {
                throw new Error('無効なレスポンス形式');
            }

            const services = result.data.services || [];
            const pagination = result.data.pagination || {};

            let html = '';

            // メインコンテナ（縦並びレイアウト）
            html = `<div style="display: flex; flex-direction: column; width: 100%;">`;

            if (services.length === 0) {
                html += `
                    <div class="ktp_data_list_box" style="
                        border: 1px solid #e5e7eb; 
                        border-radius: 4px; 
                        overflow: hidden; 
                        margin-bottom: 0;
                        width: 100%;
                        box-sizing: border-box;
                    ">
                        <div style="text-align: center; padding: 50px 40px; color: #6b7280; background: #f9fafb;">
                            <div style="font-size: 18px; font-weight: 500; margin-bottom: 8px;">登録されているサービスがありません</div>
                            <div style="font-size: 14px;">サービスタブから先にサービスを登録してください</div>
                        </div>
                    </div>
                `;
                
                // 空の状態でもページネーション領域を確保（統一されたレイアウト）
                html += `
                    <div style="
                        width: 100%;
                        border-top: 2px solid #e5e7eb; 
                        padding: 20px;
                        background: #f8f9fa;
                        border-radius: 0 0 4px 4px;
                        text-align: center;
                        color: #9ca3af;
                        font-size: 14px;
                        box-sizing: border-box;
                    ">
                        サービスを登録すると、ここにページネーションが表示されます
                    </div>
                `;
            } else {
                // サービス一覧をカード型リストで表示
                html += `
                    <div class="ktp_data_list_box" style="
                        border: 1px solid #e5e7eb; 
                        border-radius: 4px; 
                        overflow: hidden; 
                        margin-bottom: 0;
                        width: 100%;
                        box-sizing: border-box;
                    ">
                `;

                services.forEach(function (service, index) {
                    const serviceId = service.id;
                    const serviceName = service.service_name || '';
                    const price = parseInt(service.price) || 0;
                    const unit = service.unit || '式';
                    const category = service.category || '';
                    const frequency = service.frequency || 0;

                    // 偶数・奇数で背景色を変更
                    const backgroundColor = index % 2 === 0 ? '#f9fafb' : '#ffffff';
                    
                    // 画面サイズに応じてレイアウトを調整
                    const isSmallScreen = window.innerWidth < 600;

                    html += `
                        <div class="ktp_data_list_item" style="
                            line-height: 1.5;
                            border-bottom: 1px solid #e5e7eb;
                            margin: 0;
                            padding: 16px;
                            background-color: ${backgroundColor};
                            transition: background-color 0.2s ease, transform 0.1s ease;
                            position: relative;
                            font-size: 14px;
                            display: flex;
                            width: 100%;
                            box-sizing: border-box;
                            ${isSmallScreen ? 'flex-direction: column;' : 'justify-content: space-between;'}
                            ${isSmallScreen ? 'align-items: stretch;' : 'align-items: center;'}
                            gap: 15px;
                            flex-wrap: nowrap;
                        ">
                            <div style="
                                flex: 1; 
                                min-width: 0;
                                width: 100%;
                                ${isSmallScreen ? 'margin-bottom: 10px;' : ''}
                            ">
                                <div style="display: flex; align-items: center; gap: ${isSmallScreen ? '8px' : '15px'}; flex-wrap: wrap; line-height: 1.4;">
                                    <strong style="font-size: ${isSmallScreen ? '14px' : '15px'}; color: #1f2937; word-break: break-word; flex-shrink: 0;">
                                        ID: ${serviceId} - ${escapeHtml(serviceName)}
                                    </strong>
                                    <span style="color: #6b7280; font-size: ${isSmallScreen ? '12px' : '13px'}; flex-shrink: 0;"><strong>価格:</strong> ${price.toLocaleString()}円</span>
                                    <span style="color: #6b7280; font-size: ${isSmallScreen ? '12px' : '13px'}; flex-shrink: 0;"><strong>単位:</strong> ${escapeHtml(unit)}</span>
                                    <span style="color: #6b7280; font-size: ${isSmallScreen ? '12px' : '13px'}; flex-shrink: 0;"><strong>カテゴリー:</strong> ${escapeHtml(category)}</span>
                                    <span style="color: #6b7280; font-size: ${isSmallScreen ? '12px' : '13px'}; flex-shrink: 0;" title="アクセス頻度（クリックされた回数）"><strong>頻度:</strong> ${frequency}</span>
                                </div>
                            </div>
                            <div style="
                                display: flex; 
                                gap: 8px; 
                                flex-shrink: 0;
                                ${isSmallScreen ? 'width: 100%; justify-content: center;' : 'min-width: 160px; justify-content: flex-end;'}
                            ">
                                <button type="button" 
                                        class="ktp-service-selector-add" 
                                        data-service-id="${serviceId}"
                                        data-service-name="${escapeHtml(serviceName)}"
                                        data-price="${price}"
                                        data-unit="${escapeHtml(unit)}"
                                        data-mode="add"
                                        style="
                                            background: #28a745; 
                                            color: white; 
                                            border: none; 
                                            padding: 8px ${isSmallScreen ? '20px' : '16px'}; 
                                            border-radius: 4px; 
                                            cursor: pointer; 
                                            font-size: 12px;
                                            font-weight: 500;
                                            transition: background-color 0.2s ease;
                                            white-space: nowrap;
                                            ${isSmallScreen ? 'flex: 1;' : ''}
                                        "
                                        onmouseover="this.style.backgroundColor='#218838'"
                                        onmouseout="this.style.backgroundColor='#28a745'">
                                    追加
                                </button>
                                <button type="button" 
                                        class="ktp-service-selector-update" 
                                        data-service-id="${serviceId}"
                                        data-service-name="${escapeHtml(serviceName)}"
                                        data-price="${price}"
                                        data-unit="${escapeHtml(unit)}"
                                        data-mode="update"
                                        style="
                                            background: #007bff; 
                                            color: white; 
                                            border: none; 
                                            padding: 8px ${isSmallScreen ? '20px' : '16px'}; 
                                            border-radius: 4px; 
                                            cursor: pointer; 
                                            font-size: 12px;
                                            font-weight: 500;
                                            transition: background-color 0.2s ease;
                                            white-space: nowrap;
                                            ${isSmallScreen ? 'flex: 1;' : ''}
                                        "
                                        onmouseover="this.style.backgroundColor='#0056b3'"
                                        onmouseout="this.style.backgroundColor='#007bff'">
                                    更新
                                </button>
                            </div>
                        </div>
                    `;
                });

                html += `
                    </div>
                `;
            }

            // ページネーション（必ずリストの下に配置）
            if (pagination.total_pages > 1) {
                html += `<div style="width: 100%; margin-top: 0;">`;
                html += renderPagination(pagination, targetRow, mode);
                html += `</div>`;
            }

            // メインコンテナを閉じる
            html += `</div>`;

            $('#ktp-service-selector-content').html(html);

            // ボタンイベントの設定
            $('.ktp-service-selector-add').on('click', function () {
                const serviceData = {
                    id: $(this).data('service-id'),
                    name: $(this).data('service-name'),
                    price: $(this).data('price'),
                    unit: $(this).data('unit')
                };
                addServiceToInvoice(serviceData, targetRow);
            });

            $('.ktp-service-selector-update').on('click', function () {
                const serviceData = {
                    id: $(this).data('service-id'),
                    name: $(this).data('service-name'),
                    price: $(this).data('price'),
                    unit: $(this).data('unit')
                };
                updateServiceInInvoice(serviceData, targetRow);
            });

            // ページネーションボタンのイベント設定（イベント委譲を使用）
            $(document).off('click.ktp-pagination').on('click.ktp-pagination', '.ktp-pagination-btn', function (e) {
                e.preventDefault();
                const page = parseInt($(this).data('page'));
                if (page && !$(this).hasClass('disabled') && !$(this).hasClass('current')) {
                    console.log('[SERVICE SELECTOR] ページネーション: ページ', page, 'に移動');
                    loadServiceList(targetRow, mode, page);
                }
            });

            // ページネーションボタンのホバーエフェクト（イベント委譲を使用）
            $(document).off('mouseenter.ktp-pagination mouseleave.ktp-pagination')
                .on('mouseenter.ktp-pagination', '.ktp-pagination-btn:not(.disabled):not(.current)', function() {
                    // ホバー時
                    if (!$(this).hasClass('current')) {
                        $(this).css({
                            'background': '#005a87',
                            'transform': 'translateY(-1px)',
                            'box-shadow': '0 2px 4px rgba(0,0,0,0.2)'
                        });
                    }
                })
                .on('mouseleave.ktp-pagination', '.ktp-pagination-btn:not(.disabled):not(.current)', function() {
                    // ホバー解除時
                    if (!$(this).hasClass('current')) {
                        const isNavButton = $(this).text().includes('前') || $(this).text().includes('次');
                        $(this).css({
                            'background': isNavButton ? '#007cba' : 'white',
                            'transform': 'translateY(0)',
                            'box-shadow': 'none'
                        });
                    }
                });

            // カード型リストアイテムのホバーエフェクト
            $(document).off('mouseenter.ktp-service-item mouseleave.ktp-service-item')
                .on('mouseenter.ktp-service-item', '.ktp_data_list_item', function() {
                    // ホバー時のエフェクト
                    $(this).css({
                        'background-color': '#e3f2fd',
                        'transform': 'translateY(-2px)',
                        'box-shadow': '0 4px 12px rgba(0,0,0,0.15)',
                        'border-radius': '4px'
                    });
                })
                .on('mouseleave.ktp-service-item', '.ktp_data_list_item', function() {
                    // ホバー解除時
                    const index = $(this).index();
                    const originalBg = index % 2 === 0 ? '#f9fafb' : '#ffffff';
                    $(this).css({
                        'background-color': originalBg,
                        'transform': 'translateY(0)',
                        'box-shadow': 'none',
                        'border-radius': '0'
                    });
                });

        } catch (e) {
            console.error('[SERVICE SELECTOR] レスポンス解析エラー', e);
            $('#ktp-service-selector-content').html(`
                <div style="text-align: center; padding: 40px; color: #dc3545;">
                    <div style="font-size: 16px;">データの解析に失敗しました</div>
                    <div style="font-size: 14px; margin-top: 8px;">再度お試しください</div>
                </div>
            `);
        }
    }

    // ページネーションHTMLの生成（正円ボタンデザイン統一）
    function renderPagination(pagination, targetRow, mode) {
        const currentPage = pagination.current_page || 1;
        const totalPages = pagination.total_pages || 1;
        const totalItems = pagination.total_items || 0;

        let paginationHtml = `
            <div style="
                width: 100%;
                clear: both;
                display: block;
                text-align: center; 
                margin: 0; 
                border-top: 2px solid #e5e7eb; 
                padding: 25px 20px 15px 20px;
                background: #f8f9fa;
                border-radius: 0 0 4px 4px;
                box-sizing: border-box;
            ">
                <div style="margin-bottom: 18px; color: #4b5563; font-size: 14px; font-weight: 500;">
                    ${currentPage} / ${totalPages} ページ（全 ${totalItems} 件）
                </div>
                <div style="
                    display: flex; 
                    align-items: center; 
                    gap: 4px; 
                    flex-wrap: wrap; 
                    justify-content: center;
                    width: 100%;
                ">
        `;

        // 共通ボタンスタイル（正円ボタン）
        const buttonStyle = `
            width: 36px;
            height: 36px;
            padding: 0;
            border: 1px solid #ddd;
            border-radius: 50%;
            background: #fff;
            color: #333;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            line-height: 34px;
            text-align: center;
            display: inline-block;
            margin: 0 2px;
        `;

        const disabledStyle = `
            background: #f8f9fa;
            color: #999;
            border-color: #ddd;
            cursor: not-allowed;
        `;

        const currentPageStyle = `
            background: #1976d2;
            color: white;
            border-color: #1976d2;
            font-weight: bold;
            transform: translateY(-1px);
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        `;

        // 前へボタン
        const prevDisabled = currentPage <= 1;
        paginationHtml += `
            <button type="button" 
                    class="ktp-pagination-btn ${prevDisabled ? 'disabled' : ''}" 
                    data-page="${currentPage - 1}"
                    style="${buttonStyle} ${prevDisabled ? disabledStyle : ''}"
                    ${prevDisabled ? 'disabled' : ''}
                    onmouseover="${!prevDisabled ? 'this.style.backgroundColor=\'#f5f5f5\'; this.style.transform=\'translateY(-1px)\'; this.style.boxShadow=\'0 2px 5px rgba(0,0,0,0.15)\';' : ''}"
                    onmouseout="${!prevDisabled ? 'this.style.backgroundColor=\'#fff\'; this.style.transform=\'none\'; this.style.boxShadow=\'0 1px 3px rgba(0,0,0,0.1)\';' : ''}">
                ‹
            </button>
        `;

        // ページ番号ボタン
        const startPage = Math.max(1, currentPage - 2);
        const endPage = Math.min(totalPages, currentPage + 2);

        // 最初のページ
        if (startPage > 1) {
            paginationHtml += `
                <button type="button" 
                        class="ktp-pagination-btn" 
                        data-page="1"
                        style="${buttonStyle}"
                        onmouseover="this.style.backgroundColor='#f5f5f5'; this.style.transform='translateY(-1px)'; this.style.boxShadow='0 2px 5px rgba(0,0,0,0.15)';"
                        onmouseout="this.style.backgroundColor='#fff'; this.style.transform='none'; this.style.boxShadow='0 1px 3px rgba(0,0,0,0.1)';">
                    1
                </button>
            `;
            if (startPage > 2) {
                paginationHtml += `<span style="${buttonStyle} background: transparent; border: none; cursor: default;">...</span>`;
            }
        }

        // 現在のページ周辺
        for (let i = startPage; i <= endPage; i++) {
            const isCurrentPage = i === currentPage;
            paginationHtml += `
                <button type="button" 
                        class="ktp-pagination-btn ${isCurrentPage ? 'current' : ''}" 
                        data-page="${i}"
                        style="${buttonStyle} ${isCurrentPage ? currentPageStyle : ''}"
                        ${isCurrentPage ? 'disabled' : ''}
                        ${!isCurrentPage ? 'onmouseover="this.style.backgroundColor=\'#f5f5f5\'; this.style.transform=\'translateY(-1px)\'; this.style.boxShadow=\'0 2px 5px rgba(0,0,0,0.15)\';" onmouseout="this.style.backgroundColor=\'#fff\'; this.style.transform=\'none\'; this.style.boxShadow=\'0 1px 3px rgba(0,0,0,0.1)\';"' : ''}>
                    ${i}
                </button>
            `;
        }

        // 最後のページ
        if (endPage < totalPages) {
            if (endPage < totalPages - 1) {
                paginationHtml += `<span style="${buttonStyle} background: transparent; border: none; cursor: default;">...</span>`;
            }
            paginationHtml += `
                <button type="button" 
                        class="ktp-pagination-btn" 
                        data-page="${totalPages}"
                        style="${buttonStyle}"
                        onmouseover="this.style.backgroundColor='#f5f5f5'; this.style.transform='translateY(-1px)'; this.style.boxShadow='0 2px 5px rgba(0,0,0,0.15)';"
                        onmouseout="this.style.backgroundColor='#fff'; this.style.transform='none'; this.style.boxShadow='0 1px 3px rgba(0,0,0,0.1)';">
                    ${totalPages}
                </button>
            `;
        }

        // 次へボタン
        const nextDisabled = currentPage >= totalPages;
        paginationHtml += `
            <button type="button" 
                    class="ktp-pagination-btn ${nextDisabled ? 'disabled' : ''}" 
                    data-page="${currentPage + 1}"
                    style="${buttonStyle} ${nextDisabled ? disabledStyle : ''}"
                    ${nextDisabled ? 'disabled' : ''}
                    onmouseover="${!nextDisabled ? 'this.style.backgroundColor=\'#f5f5f5\'; this.style.transform=\'translateY(-1px)\'; this.style.boxShadow=\'0 2px 5px rgba(0,0,0,0.15)\';' : ''}"
                    onmouseout="${!nextDisabled ? 'this.style.backgroundColor=\'#fff\'; this.style.transform=\'none\'; this.style.boxShadow=\'0 1px 3px rgba(0,0,0,0.1)\';' : ''}">
                ›
            </button>
                </div>
            </div>
        `;

        return paginationHtml;
    }

    // サービスを新規行に追加
    function addServiceToInvoice(serviceData, targetRow) {
        console.log('[SERVICE SELECTOR] サービス追加', { serviceData, targetRow });

        // 新しい行を作成
        const tbody = targetRow.closest('tbody');
        const newIndex = tbody.find('tr').length;
        
        const newRowHtml = `
            <tr class="invoice-item-row" data-row-id="0" data-newly-added="true">
                <td class="actions-column">
                    <span class="drag-handle" title="ドラッグして並び替え">&#9776;</span><button type="button" class="btn-add-row" title="行を追加">+</button><button type="button" class="btn-delete-row" title="行を削除">×</button><button type="button" class="btn-move-row" title="サービス選択">></button>
                </td>
                <td>
                    <input type="text" name="invoice_items[${newIndex}][product_name]" class="invoice-item-input product-name" value="${serviceData.name}">
                    <input type="hidden" name="invoice_items[${newIndex}][id]" value="0">
                </td>
                <td style="text-align:left;">
                    <input type="number" name="invoice_items[${newIndex}][price]" class="invoice-item-input price" value="${serviceData.price}" step="1" min="0" style="text-align:left;">
                </td>
                <td style="text-align:left;">
                    <input type="number" name="invoice_items[${newIndex}][quantity]" class="invoice-item-input quantity" value="1" step="1" min="0" style="text-align:left;">
                </td>
                <td>
                    <input type="text" name="invoice_items[${newIndex}][unit]" class="invoice-item-input unit" value="${serviceData.unit}">
                </td>
                <td style="text-align:left;">
                    <input type="number" name="invoice_items[${newIndex}][amount]" class="invoice-item-input amount" value="${serviceData.price}" step="1" readonly style="text-align:left;">
                </td>
                <td>
                    <input type="text" name="invoice_items[${newIndex}][remarks]" class="invoice-item-input remarks" value="">
                    <input type="hidden" name="invoice_items[${newIndex}][sort_order]" value="${newIndex + 1}">
                </td>
            </tr>
        `;

        // 新しい行を追加
        tbody.append(newRowHtml);
        const $newRow = tbody.find('tr').last();

        // 金額を計算
        if (typeof calculateAmount === 'function') {
            calculateAmount($newRow);
        }

        // 合計と利益を更新
        if (typeof updateTotalAndProfit === 'function') {
            updateTotalAndProfit();
        }

        // データベースに保存
        const orderId = $('input[name="order_id"]').val() || $('#order_id').val();
        if (orderId && typeof window.ktpInvoiceCreateNewItem === 'function') {
            window.ktpInvoiceCreateNewItem('invoice', 'product_name', serviceData.name, orderId, $newRow, function(success, newItemId) {
                if (success && newItemId) {
                    $newRow.find('input[name*="[id]"]').val(newItemId);
                    $newRow.removeAttr('data-newly-added');
                    console.log('[SERVICE SELECTOR] 新規サービス保存成功', newItemId);
                }
            });
        }

        // ポップアップを閉じる（イベントハンドラーもクリーンアップ）
        $(document).off('click.ktp-pagination mouseenter.ktp-pagination mouseleave.ktp-pagination');
        $(document).off('mouseenter.ktp-service-item mouseleave.ktp-service-item');
        $('#ktp-service-selector-popup').remove();

        // 成功メッセージ
        showMessage('サービスを新規行に追加しました', 'success');
    }

    // サービスで既存行を更新
    function updateServiceInInvoice(serviceData, targetRow) {
        console.log('[SERVICE SELECTOR] サービス更新', { serviceData, targetRow });

        // 対象行のフィールドを更新
        targetRow.find('.product-name').val(serviceData.name);
        targetRow.find('.price').val(serviceData.price);
        targetRow.find('.unit').val(serviceData.unit);
        
        // 数量はデフォルトで1に設定
        targetRow.find('.quantity').val(1);

        // 金額を再計算
        if (typeof calculateAmount === 'function') {
            calculateAmount(targetRow);
        }

        // 合計と利益を更新
        if (typeof updateTotalAndProfit === 'function') {
            updateTotalAndProfit();
        }

        // データベースに保存
        const itemId = targetRow.find('input[name*="[id]"]').val();
        const orderId = $('input[name="order_id"]').val() || $('#order_id').val();
        
        if (orderId && itemId && itemId !== '0' && typeof window.ktpInvoiceAutoSaveItem === 'function') {
            // 各フィールドを順次保存
            window.ktpInvoiceAutoSaveItem('invoice', itemId, 'product_name', serviceData.name, orderId);
            window.ktpInvoiceAutoSaveItem('invoice', itemId, 'price', serviceData.price, orderId);
            window.ktpInvoiceAutoSaveItem('invoice', itemId, 'unit', serviceData.unit, orderId);
            window.ktpInvoiceAutoSaveItem('invoice', itemId, 'quantity', 1, orderId);
        }

        // ポップアップを閉じる（イベントハンドラーもクリーンアップ）
        $(document).off('click.ktp-pagination mouseenter.ktp-pagination mouseleave.ktp-pagination');
        $(document).off('mouseenter.ktp-service-item mouseleave.ktp-service-item');
        $('#ktp-service-selector-popup').remove();

        // 成功メッセージ
        showMessage('サービス情報で行を更新しました', 'success');
    }

    // メッセージ表示
    function showMessage(message, type = 'info') {
        const className = type === 'success' ? 'notice-success' : type === 'error' ? 'notice-error' : 'notice-info';
        const messageHtml = `
            <div class="notice ${className} is-dismissible" style="
                position: fixed;
                top: 32px;
                right: 20px;
                z-index: 10001;
                background: white;
                border-left: 4px solid ${type === 'success' ? '#46b450' : type === 'error' ? '#dc3232' : '#0073aa'};
                box-shadow: 0 2px 5px rgba(0,0,0,0.2);
                padding: 12px 16px;
                margin: 0;
                max-width: 300px;
            ">
                <p style="margin: 0; font-size: 14px;">${message}</p>
            </div>
        `;

        $('body').append(messageHtml);

        // 3秒後に自動で削除
        setTimeout(function () {
            $('.notice.is-dismissible').fadeOut(300, function () {
                $(this).remove();
            });
        }, 3000);
    }

    // HTMLエスケープ関数
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function (m) { return map[m]; });
    }

})(jQuery);
