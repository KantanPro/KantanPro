/**
 * メール送信ポップアップ機能
 *
 * @package KTPWP
 * @since 1.0.0
 */

(function ($) {
    'use strict';

    console.log('[EMAIL-POPUP] スクリプトが読み込まれました');

    /**
     * ポップアップを閉じる（jQuery の $ は noConflict でグローバルに無いことがあるため、
     * インライン onclick で $ を使わない。成功画面の「閉じる」からも呼ぶ）
     */
    window.ktpCloseEmailPopup = function () {
        var wrap = document.getElementById('ktp-email-popup');
        if (wrap && wrap.parentNode) {
            wrap.parentNode.removeChild(wrap);
        }
        if (typeof jQuery !== 'undefined') {
            jQuery(document).off('keyup.email-popup');
        }
    };

    // 統一されたAJAX設定の取得
    function getAjaxConfig() {
        const config = {
            url: '',
            nonce: ''
        };
        
        // URLの取得（優先順位順）
        if (typeof ktpwp_ajax !== 'undefined' && ktpwp_ajax.ajax_url) {
            config.url = ktpwp_ajax.ajax_url;
        } else if (typeof ktp_ajax_object !== 'undefined' && ktp_ajax_object.ajax_url) {
            config.url = ktp_ajax_object.ajax_url;
        } else if (typeof ajaxurl !== 'undefined') {
            config.url = ajaxurl;
        } else {
            config.url = '/wp-admin/admin-ajax.php';
        }
        
        // nonceの取得（優先順位順）
        if (typeof ktpwp_ajax !== 'undefined' && ktpwp_ajax.nonces && ktpwp_ajax.nonces.email_send) {
            config.nonce = ktpwp_ajax.nonces.email_send;
        } else if (typeof ktpwp_ajax !== 'undefined' && ktpwp_ajax.nonces && ktpwp_ajax.nonces.general) {
            config.nonce = ktpwp_ajax.nonces.general;
        } else if (typeof ktpwp_ajax_nonce !== 'undefined') {
            config.nonce = ktpwp_ajax_nonce;
        } else if (typeof ktp_ajax_nonce !== 'undefined') {
            config.nonce = ktp_ajax_nonce;
        } else if (typeof ktp_ajax_object !== 'undefined' && ktp_ajax_object.nonce) {
            config.nonce = ktp_ajax_object.nonce;
        }
        
        return config;
    }

    // 依存関係チェック
    $(document).ready(function() {
        console.log('[EMAIL-POPUP] DOM準備完了');
        console.log('[EMAIL-POPUP] jQuery available:', typeof $ !== 'undefined');
        if (window.ktpDebugMode) {
            const ajaxConfig = getAjaxConfig();
            console.log('[EMAIL-POPUP] 統一されたAJAX設定:', ajaxConfig);
        }
    });

    // メール送信ポップアップの表示
    window.ktpShowEmailPopup = function (orderId) {
        if (!orderId) {
            alert(ktpwpTranslate('受注書IDが見つかりません。'));
            return;
        }

        // ポップアップHTML（サービス選択ポップアップと同じスタイル）
        const popupHtml = `
            <div id="ktp-email-popup" style="
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
                    padding: 20px;
                    width: 95%;
                    max-width: 800px;
                    max-height: 90%;
                    overflow-y: auto;
                    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
                ">
                    <div style="
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                        margin-bottom: 20px;
                        border-bottom: 1px solid #eee;
                        padding-bottom: 15px;
                    ">
                        <h3 style="margin: 0; color: #333;">${ktpwpTranslate('メール送信')}</h3>
                        <button type="button" id="ktp-email-popup-close" style="
                            background: none;
                            color: #333;
                            border: none;
                            cursor: pointer;
                            font-size: 28px;
                            padding: 0;
                            line-height: 1;
                        ">×</button>
                    </div>
                    <div id="ktp-email-popup-content" style="
                        display: flex;
                        flex-direction: column;
                        width: 100%;
                        box-sizing: border-box;
                    ">
                        <div style="text-align: center; padding: 40px;">
                            <div style="font-size: 16px; color: #666;">${ktpwpTranslate('メール内容を読み込み中...')}</div>
                        </div>
                    </div>
                </div>
            </div>
        `;

        // ポップアップを追加
        $('body').append(popupHtml);

        // 閉じるボタン（×）のイベント
        $(document).on('click', '#ktp-email-popup-close', function () {
            window.ktpCloseEmailPopup();
        });

        // Escapeキーで閉じる
        $(document).on('keyup.email-popup', function (e) {
            if (e.keyCode === 27) { // Escape key
                window.ktpCloseEmailPopup();
            }
        });

        // 背景クリックで閉じる
        $(document).on('click', '#ktp-email-popup', function (e) {
            if (e.target === this) {
                window.ktpCloseEmailPopup();
            }
        });

        // メール内容を取得
        loadEmailContent(orderId);
    };

    // メール内容の取得
    function loadEmailContent(orderId) {
        const ajaxConfig = getAjaxConfig();

        const ajaxData = {
            action: 'get_email_content',
            order_id: orderId,
            nonce: ajaxConfig.nonce
        };

        $.ajax({
            url: ajaxConfig.url,
            type: 'POST',
            data: ajaxData,
            dataType: 'json',
            success: function (response) {
                
                if (response.success && response.data) {
                    renderEmailForm(response.data, orderId);
                } else {
                    const errorMessage = response.data && response.data.message ? ktpwpTranslate(response.data.message) : ktpwpTranslate('メール内容の取得に失敗しました');
                    $('#ktp-email-popup-content').html(`
                        <div style="text-align: center; padding: 40px; color: #dc3545;">
                            <div style="font-size: 16px;">${errorMessage}</div>
                            <div style="font-size: 14px; margin-top: 4px;">${ktpwpTranslate('再度お試しください')}</div>
                        </div>
                    `);
                }
            },
            error: function (xhr, status, error) {
                console.error('[EMAIL POPUP] メール内容取得エラー', { 
                    status, 
                    error, 
                    responseText: xhr.responseText,
                    statusCode: xhr.status
                });
                console.error('[EMAIL POPUP] レスポンスヘッダー:', xhr.getAllResponseHeaders());
                console.error('[EMAIL POPUP] レスポンステキスト詳細:', xhr.responseText);
                
                let errorMessage = ktpwpTranslate('メール内容の読み込みに失敗しました');
                let errorDetails = '';
                
                // レスポンステキストからJSON部分を抽出して解析を試行
                try {
                    const responseText = xhr.responseText;
                    const jsonStart = responseText.indexOf('{"');
                    if (jsonStart !== -1) {
                        const jsonPart = responseText.substring(jsonStart);
                        const jsonData = JSON.parse(jsonPart);
                        if (jsonData.success && jsonData.data) {
                            // JSONデータが正常に取得できた場合は成功として処理
                            console.log('[EMAIL POPUP] JSON部分を正常に解析:', jsonData);
                            renderEmailForm(jsonData.data, orderId);
                            return;
                        }
                    }
                } catch (parseError) {
                    console.error('[EMAIL POPUP] JSON解析エラー:', parseError);
                }
                
                // エラーメッセージの詳細化
                if (xhr.status === 403) {
                    errorMessage = ktpwpTranslate('権限がありません。ログインを確認してください。');
                } else if (xhr.status === 404) {
                    errorMessage = ktpwpTranslate('受注書が見つかりませんでした。');
                } else if (xhr.status === 500) {
                    errorMessage = ktpwpTranslate('サーバーエラーが発生しました。');
                } else if (status === 'parsererror') {
                    errorMessage = ktpwpTranslate('レスポンスの解析に失敗しました。');
                    errorDetails = ktpwpTranslate('HTMLエラーメッセージが混在している可能性があります。');
                }
                
                $('#ktp-email-popup-content').html(`
                    <div style="text-align: center; padding: 40px; color: #dc3545;">
                        <div style="font-size: 16px;">${errorMessage}</div>
                        <div style="font-size: 14px; margin-top: 8px;">${ktpwpTranslate('ステータス')}: ${xhr.status} ${status}</div>
                        ${errorDetails ? `<div style="font-size: 14px; margin-top: 4px; color: #666;">${errorDetails}</div>` : ''}
                        <div style="font-size: 14px; margin-top: 4px;">${ktpwpTranslate('再度お試しください')}</div>
                    </div>
                `);
            }
        });
    }

    // メールフォームの表示
    function renderEmailForm(emailData, orderId) {
        console.log('[EMAIL POPUP] メールフォーム表示', emailData);

        let html = '';

        if (emailData.error) {
            // エラー表示
            html = `
                <div style="
                    background: ${emailData.error_type === 'no_email' ? '#fff3cd' : '#ffebee'};
                    border: 2px solid ${emailData.error_type === 'no_email' ? '#ffc107' : '#f44336'};
                    padding: 24px;
                    border-radius: 8px;
                    text-align: center;
                ">
                    <h4 style="margin-top: 0; color: ${emailData.error_type === 'no_email' ? '#856404' : '#d32f2f'};">
                        ${ktpwpTranslate(emailData.error_title)}
                    </h4>
                    <p style="color: ${emailData.error_type === 'no_email' ? '#856404' : '#d32f2f'}; margin-bottom: 0;">
                        ${ktpwpTranslate(emailData.error)}
                    </p>
                </div>
            `;
        } else {
            // メール送信フォーム
            html = `
                <form id="email-send-form" style="width: 100%;">
                    <div style="margin-bottom: 15px;">
                        <label style="display: block; font-weight: bold; margin-bottom: 5px;">${ktpwpTranslate('宛先')}：</label>
                        <input type="email" value="${emailData.to}" readonly style="
                            width: 100%;
                            padding: 8px;
                            border: 1px solid #ddd;
                            border-radius: 4px;
                            background: #f5f5f5;
                            box-sizing: border-box;
                        ">
                    </div>
                    <div style="margin-bottom: 15px;">
                        <label style="display: block; font-weight: bold; margin-bottom: 5px;">${ktpwpTranslate('CC（任意・カンマ区切り）')}：</label>
                        <p style="margin: 0 0 6px 0; font-size: 12px; color: #666; line-height: 1.4;">${ktpwpTranslate('顧客代表・各部署に登録されたメールのうち、宛先（To）以外を自動で入れます（KantanBizの「CC（任意）」と同様）。編集できます。')}</p>
                        <input type="text" id="email-cc" autocomplete="off" style="
                            width: 100%;
                            padding: 8px;
                            border: 1px solid #ddd;
                            border-radius: 4px;
                            box-sizing: border-box;
                        ">
                    </div>
                    <div style="margin-bottom: 15px;">
                        <label style="display: block; font-weight: bold; margin-bottom: 5px;">${ktpwpTranslate('件名')}：</label>
                        <input type="text" id="email-subject" value="${emailData.subject}" style="
                            width: 100%;
                            padding: 8px;
                            border: 1px solid #ddd;
                            border-radius: 4px;
                            box-sizing: border-box;
                        ">
                    </div>
                    <div style="margin-bottom: 20px;">
                        <label style="display: block; font-weight: bold; margin-bottom: 5px;">${ktpwpTranslate('本文')}：</label>
                        <textarea id="email-body" rows="12" style="
                            width: 100%;
                            padding: 8px;
                            border: 1px solid #ddd;
                            border-radius: 4px;
                            resize: vertical;
                            box-sizing: border-box;
                            font-family: monospace;
                        ">${emailData.body}</textarea>
                    </div>
                    <div style="margin-bottom: 20px;">
                        <label style="display: block; font-weight: bold; margin-bottom: 5px;">${ktpwpTranslate('ファイル添付')}：</label>
                        <div id="file-attachment-area" style="
                            border: 2px dashed #ddd;
                            border-radius: 8px;
                            padding: 20px;
                            text-align: center;
                            background: #fafafa;
                            margin-bottom: 10px;
                            transition: all 0.3s ease;
                        ">
                            <input type="file" id="email-attachments" multiple accept=".pdf,.jpg,.jpeg,.png,.gif,.doc,.docx,.xls,.xlsx,.zip,.rar,.7z" style="display: none;">
                            <div id="drop-zone" style="cursor: pointer;">
                                <div style="font-size: 18px; color: #666; margin-bottom: 8px;">
                                    📎 ${ktpwpTranslate('ファイルをドラッグ&ドロップまたはクリックして選択')}
                                </div>
                                <div style="font-size: 13px; color: #888; line-height: 1.4;">
                                    ${ktpwpTranslate('対応形式：PDF, 画像(JPG,PNG,GIF), Word, Excel, 圧縮ファイル等')}<br>
                                    <strong>${ktpwpTranslate('最大ファイルサイズ：10MB/ファイル, 合計50MB')}</strong>
                                </div>
                            </div>
                        </div>
                        <div id="selected-files" style="
                            max-height: 120px;
                            overflow-y: auto;
                            border: 1px solid #ddd;
                            border-radius: 4px;
                            padding: 8px;
                            background: white;
                            display: none;
                        "></div>
                    </div>
                    <div style="text-align: center;">
                        <button type="submit" id="email-send-button" style="
                            background: #2196f3;
                            color: white;
                            border: none;
                            padding: 12px 24px;
                            border-radius: 4px;
                            cursor: pointer;
                            font-size: 16px;
                            font-weight: bold;
                        ">
                            ${ktpwpTranslate('メール送信')}
                        </button>
                    </div>
                    <input type="hidden" name="order_id" value="${orderId}">
                    <input type="hidden" name="to" value="${emailData.to}">
                </form>
            `;
        }

        $('#ktp-email-popup-content').html(html);

        if (!emailData.error && $('#email-cc').length) {
            $('#email-cc').val(typeof emailData.cc === 'string' ? emailData.cc : '');
        }

        // フォーム送信イベント
        $('#email-send-form').on('submit', function(e) {
            e.preventDefault();
            sendEmail(orderId);
        });

        // ファイル添付機能のイベントハンドラー
        setupFileAttachment();
    }

    // ファイル添付機能のセットアップ
    function setupFileAttachment() {
        const fileInput = $('#email-attachments');
        const dropZone = $('#drop-zone');
        const selectedFilesDiv = $('#selected-files');
        let selectedFiles = [];

        // ドロップゾーンクリック
        dropZone.on('click', function() {
            fileInput.click();
        });

        // ファイル選択
        fileInput.on('change', function(e) {
            const files = Array.from(e.target.files);
            addFiles(files);
        });

        // ドラッグ&ドロップ
        dropZone.on('dragover', function(e) {
            e.preventDefault();
            $('#file-attachment-area').css({
                'background': '#e3f2fd',
                'border-color': '#2196f3',
                'transform': 'scale(1.02)'
            });
        });

        dropZone.on('dragleave', function(e) {
            e.preventDefault();
            $('#file-attachment-area').css({
                'background': '#fafafa',
                'border-color': '#ddd',
                'transform': 'scale(1.0)'
            });
        });

        dropZone.on('drop', function(e) {
            e.preventDefault();
            $('#file-attachment-area').css({
                'background': '#fafafa',
                'border-color': '#ddd',
                'transform': 'scale(1.0)'
            });
            const files = Array.from(e.originalEvent.dataTransfer.files);
            addFiles(files);
        });

        // ファイル追加
        function addFiles(files) {
            const maxFileSize = 10 * 1024 * 1024; // 10MB
            const maxTotalSize = 50 * 1024 * 1024; // 50MB
            const allowedTypes = [
                'application/pdf',
                'image/jpeg', 'image/jpg', 'image/png', 'image/gif',
                'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/zip', 'application/x-rar-compressed', 'application/x-zip-compressed'
            ];

            let totalSize = selectedFiles.reduce((sum, file) => sum + file.size, 0);
            let hasError = false;

            files.forEach(file => {
                // ファイルサイズチェック
                if (file.size > maxFileSize) {
                    alert(ktpwpTranslate('ファイル "%s" は10MBを超えています。\n最大ファイルサイズ：10MB').replace('%s', file.name));
                    hasError = true;
                    return;
                }

                // 合計サイズチェック
                if (totalSize + file.size > maxTotalSize) {
                    alert(ktpwpTranslate('合計ファイルサイズが50MBを超えます。\nファイルを減らしてください。'));
                    hasError = true;
                    return;
                }

                // ファイル形式チェック
                if (!allowedTypes.includes(file.type) && !isAllowedExtension(file.name)) {
                    alert(ktpwpTranslate('ファイル "%s" は対応していない形式です。\n対応形式：PDF, 画像, Word, Excel, 圧縮ファイル等').replace('%s', file.name));
                    hasError = true;
                    return;
                }

                // 重複チェック
                if (selectedFiles.find(f => f.name === file.name && f.size === file.size)) {
                    return; // スキップ
                }

                selectedFiles.push(file);
                totalSize += file.size;
            });

            if (!hasError) {
                updateFileList();
            }
        }

        // 拡張子による許可チェック
        function isAllowedExtension(filename) {
            const allowedExtensions = [
                '.pdf', '.jpg', '.jpeg', '.png', '.gif',
                '.doc', '.docx', '.xls', '.xlsx',
                '.zip', '.rar', '.7z'
            ];
            const ext = filename.toLowerCase().substring(filename.lastIndexOf('.'));
            return allowedExtensions.includes(ext);
        }

        // ファイルリスト更新
        function updateFileList() {
            if (selectedFiles.length === 0) {
                selectedFilesDiv.hide();
                return;
            }

            let html = '<div style="font-weight: bold; margin-bottom: 10px; color: #333; border-bottom: 1px solid #eee; padding-bottom: 5px;">' + ktpwpTranslate('選択されたファイル') + '：</div>';
            selectedFiles.forEach((file, index) => {
                const sizeText = formatFileSize(file.size);
                const fileIcon = getFileIcon(file.name);
                html += `
                    <div style="
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                        padding: 8px 10px;
                        margin-bottom: 6px;
                        background: #f8f9fa;
                        border-radius: 6px;
                        font-size: 13px;
                        border: 1px solid #e9ecef;
                    ">
                        <span style="color: #333; flex: 1; display: flex; align-items: center;">
                            <span style="margin-right: 8px; font-size: 16px;">${fileIcon}</span>
                            <span style="font-weight: 500;">${file.name}</span>
                            <span style="margin-left: 8px; color: #666; font-size: 12px;">(${sizeText})</span>
                        </span>
                        <button type="button" onclick="removeFile(${index})" style="
                            background: #dc3545;
                            color: white;
                            border: none;
                            padding: 4px 8px;
                            border-radius: 4px;
                            cursor: pointer;
                            font-size: 11px;
                            font-weight: 500;
                            margin-left: 10px;
                            transition: background 0.2s;
                        " onmouseover="this.style.background='#c82333'" onmouseout="this.style.background='#dc3545'">${ktpwpTranslate('削除')}</button>
                    </div>
                `;
            });

            const totalSize = selectedFiles.reduce((sum, file) => sum + file.size, 0);
            const totalSizePercent = Math.round((totalSize / (50 * 1024 * 1024)) * 100);
            const progressColor = totalSizePercent > 80 ? '#dc3545' : totalSizePercent > 50 ? '#ffc107' : '#28a745';
            
            html += `
                <div style="
                    font-size: 12px; 
                    color: #666; 
                    text-align: right; 
                    margin-top: 10px;
                    padding-top: 8px;
                    border-top: 1px solid #eee;
                ">
                    <div style="margin-bottom: 4px;">
                        ${ktpwpTranslate('合計')}：<strong style="color: ${progressColor};">${formatFileSize(totalSize)}</strong> / 50MB (${totalSizePercent}%)
                    </div>
                    <div style="
                        background: #e9ecef;
                        height: 4px;
                        border-radius: 2px;
                        overflow: hidden;
                    ">
                        <div style="
                            background: ${progressColor};
                            height: 100%;
                            width: ${totalSizePercent}%;
                            transition: width 0.3s ease;
                        "></div>
                    </div>
                </div>
            `;

            selectedFilesDiv.html(html).show();
        }

        // ファイルアイコン取得
        function getFileIcon(filename) {
            const ext = filename.toLowerCase().substring(filename.lastIndexOf('.'));
            const iconMap = {
                '.pdf': '📄',
                '.jpg': '🖼️', '.jpeg': '🖼️', '.png': '🖼️', '.gif': '🖼️',
                '.doc': '📝', '.docx': '📝',
                '.xls': '📊', '.xlsx': '📊',
                '.zip': '🗜️', '.rar': '🗜️', '.7z': '🗜️'
            };
            return iconMap[ext] || '📎';
        }

        // ファイル削除（グローバル関数として定義）
        window.removeFile = function(index) {
            selectedFiles.splice(index, 1);
            updateFileList();
        };

        // ファイルサイズフォーマット
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        // 選択されたファイルを取得する関数
        window.getSelectedFiles = function() {
            return selectedFiles;
        };
    }

    /** メールポップアップ用: 自動保存 API は ktp_ajax_nonce 優先（ktpwp 一般 nonce もサーバで受可） */
    function getAutoSaveNonceForEmail() {
        if (typeof ktp_ajax_nonce !== 'undefined' && ktp_ajax_nonce) {
            return ktp_ajax_nonce;
        }
        if (typeof ktpwp_ajax !== 'undefined' && ktpwp_ajax.nonces && ktpwp_ajax.nonces.auto_save) {
            return ktpwp_ajax.nonces.auto_save;
        }
        if (typeof ktp_ajax_object !== 'undefined' && ktp_ajax_object.nonces && ktp_ajax_object.nonces.auto_save) {
            return ktp_ajax_object.nonces.auto_save;
        }
        if (typeof ktp_ajax_object !== 'undefined' && ktp_ajax_object.nonce) {
            return ktp_ajax_object.nonce;
        }
        const cfg = getAjaxConfig();
        return cfg.nonce || '';
    }

    // メール送信前に最新の金額をデータベースに保存
    function saveLatestAmountsBeforeEmail(orderId) {
        try {
            const ajaxConfig = getAjaxConfig();
            const saveNonce = getAutoSaveNonceForEmail();

            // 請求項目の最新金額を保存（action は wp_ajax_ktp_auto_save_item に合わせる）
            $('.invoice-items-table tbody tr').each(function() {
                const $row = $(this);
                const itemId = $row.find('input[name*="[id]"]').val();
                const amountValue = $row.find('.invoice-item-amount').attr('data-amount') || $row.find('.invoice-item-amount').text().replace(/,/g, '');
                const amount = parseFloat(amountValue) || 0;

                if (itemId && itemId !== '0' && amount > 0) {
                    $.ajax({
                        url: ajaxConfig.url,
                        type: 'POST',
                        data: {
                            action: 'ktp_auto_save_item',
                            item_type: 'invoice',
                            item_id: itemId,
                            field_name: 'amount',
                            field_value: String(amount),
                            order_id: orderId,
                            nonce: saveNonce
                        },
                        async: false
                    });
                }
            });

            // コスト項目の最新金額を保存
            $('.cost-items-table tbody tr').each(function() {
                const $row = $(this);
                const itemId = $row.find('input[name*="[id]"]').val();
                const amountValue = $row.find('.cost-item-amount').attr('data-amount') || $row.find('.cost-item-amount').text().replace(/,/g, '');
                const amount = parseFloat(amountValue) || 0;

                if (itemId && itemId !== '0' && amount > 0) {
                    $.ajax({
                        url: ajaxConfig.url,
                        type: 'POST',
                        data: {
                            action: 'ktp_auto_save_item',
                            item_type: 'cost',
                            item_id: itemId,
                            field_name: 'amount',
                            field_value: String(amount),
                            order_id: orderId,
                            nonce: saveNonce
                        },
                        async: false
                    });
                }
            });

            console.log('[EMAIL POPUP] メール送信前の金額保存完了');
        } catch (error) {
            console.error('[EMAIL POPUP] メール送信前の金額保存エラー:', error);
        }
    }

    // メール送信
    function sendEmail(orderId) {
        const subject = $('#email-subject').val();
        const body = $('#email-body').val();
        const to = $('input[name="to"]').val();

        if (!subject.trim() || !body.trim()) {
            alert(ktpwpTranslate('件名と本文を入力してください。'));
            return;
        }

        // メール送信前に最新の金額をデータベースに保存
        saveLatestAmountsBeforeEmail(orderId);

        const selectedFiles = window.getSelectedFiles ? window.getSelectedFiles() : [];
        
        // FormDataを使用してファイルと一緒にデータを送信
        const formData = new FormData();
        formData.append('action', 'send_order_email');
        formData.append('order_id', orderId);
        formData.append('to', to);
        const ccVal = ($('#email-cc').val() || '').trim();
        if (ccVal) {
            formData.append('cc', ccVal);
        }
        formData.append('subject', subject);
        formData.append('body', body);
        
        const ajaxConfig = getAjaxConfig();
        if (ajaxConfig.nonce) {
            formData.append('nonce', ajaxConfig.nonce);
        }

        // ファイルを追加
        selectedFiles.forEach((file, index) => {
            formData.append(`attachments[${index}]`, file);
        });

        // 送信中表示を更新（ファイル数を表示）
        let loadingMessage = ktpwpTranslate('メール送信中...');
        if (selectedFiles.length > 0) {
            loadingMessage += `<br><small style="color: #666;">${ktpwpTranslate('%d件のファイルを添付中...').replace('%d', selectedFiles.length)}</small>`;
        }

        $('#ktp-email-popup-content').html(`
            <div style="text-align: center; padding: 40px;">
                <div style="font-size: 16px; color: #666;">${loadingMessage}</div>
            </div>
        `);

        console.log('[EMAIL POPUP] メール送信開始', { 
            orderId, 
            to, 
            subject, 
            attachmentCount: selectedFiles.length 
        });

        $.ajax({
            url: ajaxConfig.url,
            type: 'POST',
            data: formData,
            processData: false,  // FormDataを使用する場合は必須
            contentType: false,  // FormDataを使用する場合は必須
            success: function (response) {
                console.log('[EMAIL POPUP] メール送信レスポンス', response);
                
                if (response.success) {
                    const newProgress = response.data && response.data.progress != null ? response.data.progress : null;
                    const completionDate = response.data && response.data.completion_date ? response.data.completion_date : null;
                    const progressLabels = { 1: ktpwpTranslate('受付中'), 2: ktpwpTranslate('見積中'), 3: ktpwpTranslate('受注'), 4: ktpwpTranslate('完了'), 5: ktpwpTranslate('請求済'), 6: ktpwpTranslate('入金済'), 7: ktpwpTranslate('ボツ') };

                    let successMessage = `
                        <div style="font-size: 18px; font-weight: bold; margin-bottom: 10px;">
                            ✓ ${ktpwpTranslate('メール送信完了')}
                        </div>
                        <div style="font-size: 14px;">
                            ${ktpwpTranslate('宛先')}: ${to}
                        </div>
                    `;
                    if (newProgress != null && progressLabels[newProgress]) {
                        successMessage += `
                            <div style="font-size: 14px; margin-top: 8px; color: #28a745;">
                                ${ktpwpTranslate('進捗を「%s」に更新しました。').replace('%s', progressLabels[newProgress])}
                            </div>
                        `;
                        var $select = $('#order_progress_select');
                        if ($select.length && $select.find('option[value="' + newProgress + '"]').length) {
                            $select.val(String(newProgress));
                        }
                        // 進捗が「完了」(4)で完了日が返ってきた場合、完了日フィールドをリアルタイムで更新
                        if (newProgress === 4 && completionDate) {
                            var $completionInput = $('#completion_date');
                            if ($completionInput.length) {
                                $completionInput.val(completionDate);
                            }
                            var $hiddenCompletion = $('input[name="completion_date"]');
                            if ($hiddenCompletion.length) {
                                $hiddenCompletion.val(completionDate);
                            }
                        }
                    }
                    if (selectedFiles.length > 0) {
                        successMessage += `
                            <div style="font-size: 14px; margin-top: 8px; color: #666;">
                                ${ktpwpTranslate('添付ファイル')}: ${selectedFiles.length}${ktpwpTranslate('件')}
                            </div>
                        `;
                    }

                    $('#ktp-email-popup-content').html(`
                        <div style="text-align: center; padding: 40px; color: #28a745;">
                            ${successMessage}
                            <div style="margin-top: 20px;">
                                <button type="button" class="ktp-email-success-close" style="
                                    position: relative;
                                    z-index: 2;
                                    background: #28a745;
                                    color: white;
                                    border: none;
                                    padding: 8px 16px;
                                    border-radius: 4px;
                                    cursor: pointer;
                                    pointer-events: auto;
                                " onclick="if(typeof window.ktpCloseEmailPopup==='function'){window.ktpCloseEmailPopup();}return false;">
                                    ${ktpwpTranslate('閉じる')}
                                </button>
                            </div>
                        </div>
                    `);
                    (function () {
                        var okBtn = document.querySelector('#ktp-email-popup .ktp-email-success-close');
                        if (!okBtn) {
                            return;
                        }
                        okBtn.addEventListener(
                            'click',
                            function (ev) {
                                ev.preventDefault();
                                ev.stopPropagation();
                                window.ktpCloseEmailPopup();
                            },
                            true
                        );
                    })();
                } else {
                    const errorMessage = response.data && response.data.message ? ktpwpTranslate(response.data.message) : ktpwpTranslate('メール送信に失敗しました');
                    $('#ktp-email-popup-content').html(`
                        <div style="text-align: center; padding: 40px; color: #dc3545;">
                            <div style="font-size: 16px; font-weight: bold; margin-bottom: 10px;">
                                ✗ ${ktpwpTranslate('メール送信失敗')}
                            </div>
                            <div style="font-size: 14px;">${errorMessage}</div>
                            <div style="margin-top: 20px;">
                                <button type="button" onclick="ktpShowEmailPopup(${orderId})" style="
                                    background: #dc3545;
                                    color: white;
                                    border: none;
                                    padding: 8px 16px;
                                    border-radius: 4px;
                                    cursor: pointer;
                                ">
                                    ${ktpwpTranslate('再試行')}
                                </button>
                            </div>
                        </div>
                    `);
                }
            },
            error: function (xhr, status, error) {
                console.error('[EMAIL POPUP] メール送信エラー', { 
                    status, 
                    error, 
                    responseText: xhr.responseText,
                    statusCode: xhr.status
                });
                
                $('#ktp-email-popup-content').html(`
                    <div style="text-align: center; padding: 40px; color: #dc3545;">
                        <div style="font-size: 16px; font-weight: bold; margin-bottom: 10px;">
                            ✗ ${ktpwpTranslate('メール送信エラー')}
                        </div>
                        <div style="font-size: 14px;">${ktpwpTranslate('ステータス')}: ${xhr.status} ${status}</div>
                        <div style="margin-top: 20px;">
                            <button type="button" onclick="ktpShowEmailPopup(${orderId})" style="
                                background: #dc3545;
                                color: white;
                                border: none;
                                padding: 8px 16px;
                                border-radius: 4px;
                                cursor: pointer;
                            ">
                                再試行
                            </button>
                        </div>
                    </div>
                `);
            }
        });
    }

})(jQuery);
