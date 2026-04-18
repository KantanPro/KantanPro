/**
 * 発注メールポップアップ機能
 * 
 * @package KTPWP
 * @since 1.0.0
 */

(function($) {
    'use strict';

    // 発注メールポップアップを表示（supplierIdは省略可。あると協力会社の検索が安定する）
    window.ktpShowPurchaseOrderEmailPopup = function(orderId, supplierName, supplierId) {
        if (!orderId || !supplierName) {
            alert('受注書IDまたは協力会社名が指定されていません。');
            return;
        }
        supplierId = supplierId || 0;

        // ポップアップHTML
        const popupHtml = `
            <div id="ktp-purchase-order-email-popup" style="
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
                        <h3 style="margin: 0; color: #333;">発注メール送信</h3>
                        <button type="button" id="ktp-purchase-order-email-popup-close" style="
                            background: none;
                            color: #333;
                            border: none;
                            cursor: pointer;
                            font-size: 28px;
                            padding: 0;
                            line-height: 1;
                        ">×</button>
                    </div>
                    <div id="ktp-purchase-order-email-popup-content" style="
                        display: flex;
                        flex-direction: column;
                        width: 100%;
                        box-sizing: border-box;
                    ">
                        <div style="text-align: center; padding: 40px;">
                            <div style="font-size: 16px; color: #666;">発注メール内容を読み込み中...</div>
                        </div>
                    </div>
                </div>
            </div>
        `;

        // 既存のポップアップを削除
        $('#ktp-purchase-order-email-popup').remove();

        // 新しいポップアップを追加
        $('body').append(popupHtml);

        // 閉じるボタンのイベント
        $('#ktp-purchase-order-email-popup-close').on('click', function() {
            $('#ktp-purchase-order-email-popup').remove();
        });

        // 背景クリックで閉じる
        $('#ktp-purchase-order-email-popup').on('click', function(e) {
            if (e.target === this) {
                $(this).remove();
            }
        });

        // 発注メール内容を取得
        loadPurchaseOrderEmailContent(orderId, supplierName, supplierId);
    };

    // 発注メール内容を読み込み
    function loadPurchaseOrderEmailContent(orderId, supplierName, supplierId) {
        supplierId = supplierId || 0;
        // Ajax URLの確認と代替設定
        let ajaxUrl = '';
        if (typeof ajaxurl !== 'undefined') {
            ajaxUrl = ajaxurl;
        } else if (typeof ktp_ajax_object !== 'undefined' && ktp_ajax_object.ajax_url) {
            ajaxUrl = ktp_ajax_object.ajax_url;
        } else if (typeof ktpwp_ajax !== 'undefined' && ktpwp_ajax.ajax_url) {
            ajaxUrl = ktpwp_ajax.ajax_url;
        } else {
            ajaxUrl = '/wp-admin/admin-ajax.php';
        }

        // 統一されたnonce取得方法
        let nonce = '';
        if (typeof ktpwp_ajax_nonce !== 'undefined') {
            nonce = ktpwp_ajax_nonce;
        } else if (typeof ktp_ajax_nonce !== 'undefined') {
            nonce = ktp_ajax_nonce;
        } else if (typeof ktp_ajax_object !== 'undefined' && ktp_ajax_object.nonce) {
            nonce = ktp_ajax_object.nonce;
        } else if (typeof ktpwp_ajax !== 'undefined' && ktpwp_ajax.nonces && ktpwp_ajax.nonces.general) {
            nonce = ktpwp_ajax.nonces.general;
        } else if (typeof ktpwp_ajax !== 'undefined' && ktpwp_ajax.nonces && ktpwp_ajax.nonces.auto_save) {
            nonce = ktpwp_ajax.nonces.auto_save;
        }

        // nonceが取得できない場合のデバッグ情報
        if (!nonce) {
            console.error('[PURCHASE-ORDER-EMAIL] nonceが取得できません:', {
                ktpwp_ajax_nonce: typeof ktpwp_ajax_nonce !== 'undefined' ? 'defined' : 'undefined',
                ktp_ajax_nonce: typeof ktp_ajax_nonce !== 'undefined' ? 'defined' : 'undefined',
                ktp_ajax_object: typeof ktp_ajax_object !== 'undefined' ? 'defined' : 'undefined',
                ktpwp_ajax: typeof ktpwp_ajax !== 'undefined' ? 'defined' : 'undefined'
            });
        }

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'get_purchase_order_email_content',
                order_id: orderId,
                supplier_name: supplierName,
                supplier_id: supplierId,
                nonce: nonce,
                ktpwp_ajax_nonce: nonce  // 追加: サーバー側で期待されるフィールド名
            },
            success: function(response) {
                try {
                    const result = typeof response === 'string' ? JSON.parse(response) : response;
                    if (result.success && result.data) {
                        displayPurchaseOrderEmailForm(result.data, orderId, supplierName, supplierId);
                    } else {
                        showError('発注メール内容の取得に失敗しました: ' + (result.data ? result.data.message : '不明なエラー'));
                    }
                } catch (e) {
                    console.error('[PURCHASE-ORDER-EMAIL] レスポンスパースエラー:', e, response);
                    showError('発注メール内容の処理中にエラーが発生しました');
                }
            },
            error: function(xhr, status, error) {
                console.error('[PURCHASE-ORDER-EMAIL] Ajax エラー:', {xhr, status, error});
                showError('発注メール内容の取得中にエラーが発生しました');
            }
        });
    }

    // 発注メールフォームを表示（orderId, supplierName, supplierId は送信時に必要）
    function displayPurchaseOrderEmailForm(data, orderId, supplierName, supplierId) {
        orderId = orderId || 0;
        supplierName = supplierName || '';
        supplierId = supplierId || 0;
        const content = `
            <form id="ktp-purchase-order-email-form">
                <div style="margin-bottom: 15px;">
                    <label for="email-to" style="display: block; margin-bottom: 5px; font-weight: bold;">送信先メールアドレス:</label>
                    <input type="email" id="email-to" name="to" value="${data.supplier_email}" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box;" required>
                </div>

                <div style="margin-bottom: 15px;">
                    <label for="email-subject" style="display: block; margin-bottom: 5px; font-weight: bold;">件名:</label>
                    <input type="text" id="email-subject" name="subject" value="${data.subject}" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box;" required>
                </div>

                <div style="margin-bottom: 15px;">
                    <label for="email-body" style="display: block; margin-bottom: 5px; font-weight: bold;">本文:</label>
                    <textarea id="email-body" name="body" rows="20" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; font-family: monospace; font-size: 12px;" required>${data.body}</textarea>
                </div>

                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-weight: bold; margin-bottom: 5px;">ファイル添付：</label>
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
                                📎 ファイルをドラッグ&ドロップまたはクリックして選択
                            </div>
                            <div style="font-size: 13px; color: #888; line-height: 1.4;">
                                対応形式：PDF, 画像(JPG,PNG,GIF), Word, Excel, 圧縮ファイル等<br>
                                <strong>最大ファイルサイズ：10MB/ファイル, 合計50MB</strong>
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

                <div style="display: flex; justify-content: center;">
                    <button type="submit" id="ktp-purchase-order-email-send" style="
                        padding: 10px 20px;
                        background: #007cba;
                        color: white;
                        border: none;
                        border-radius: 4px;
                        cursor: pointer;
                    ">メール送信</button>
                </div>
            </form>
        `;

        $('#ktp-purchase-order-email-popup-content').html(content);

        // イベントハンドラーを設定（orderId, supplierName, supplierId を渡して送信）
        $('#ktp-purchase-order-email-form').on('submit', function(e) {
            e.preventDefault();
            sendPurchaseOrderEmail(orderId, supplierName, supplierId);
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
                    alert(`ファイル "${file.name}" は10MBを超えています。\n最大ファイルサイズ：10MB`);
                    hasError = true;
                    return;
                }

                // 合計サイズチェック
                if (totalSize + file.size > maxTotalSize) {
                    alert(`合計ファイルサイズが50MBを超えます。\nファイルを減らしてください。`);
                    hasError = true;
                    return;
                }

                // ファイル形式チェック
                const fileExt = file.name.toLowerCase().substring(file.name.lastIndexOf('.'));
                const isAllowedType = allowedTypes.includes(file.type);
                const isAllowedExt = isAllowedExtension(file.name);

                if (!isAllowedType && !isAllowedExt) {
                    alert(`ファイル "${file.name}" は対応していない形式です。\n対応形式：PDF, 画像, Word, Excel, 圧縮ファイル等`);
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

            const totalSize = selectedFiles.reduce((sum, file) => sum + file.size, 0);
            const totalSizePercent = Math.min((totalSize / (50 * 1024 * 1024)) * 100, 100);
            const progressColor = totalSizePercent > 80 ? '#ff6b6b' : totalSizePercent > 60 ? '#ffa726' : '#4caf50';

            let html = '';
            selectedFiles.forEach((file, index) => {
                const fileIcon = getFileIcon(file.name);
                const fileSize = formatFileSize(file.size);
                html += `
                    <div style="
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                        padding: 8px;
                        border-bottom: 1px solid #eee;
                        background: #f9f9f9;
                        margin-bottom: 4px;
                        border-radius: 4px;
                    ">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span style="font-size: 16px;">${fileIcon}</span>
                            <div>
                                <div style="font-size: 12px; font-weight: bold; color: #333;">${file.name}</div>
                                <div style="font-size: 11px; color: #666;">${fileSize}</div>
                            </div>
                        </div>
                        <button type="button" onclick="removeFile(${index})" style="
                            background: #ff6b6b;
                            color: white;
                            border: none;
                            border-radius: 50%;
                            width: 24px;
                            height: 24px;
                            cursor: pointer;
                            font-size: 12px;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                        ">×</button>
                    </div>
                `;
            });

            html += `
                <div style="margin-top: 8px; padding: 8px; background: #f0f0f0; border-radius: 4px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                        <span style="font-size: 11px; color: #666;">合計サイズ: ${formatFileSize(totalSize)}</span>
                        <span style="font-size: 11px; color: #666;">${totalSizePercent.toFixed(1)}%</span>
                    </div>
                    <div style="
                        width: 100%;
                        height: 4px;
                        background: #ddd;
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

    // 発注メールを送信
    function sendPurchaseOrderEmail(orderId, supplierName, supplierId) {
        supplierId = supplierId || 0;
        const formData = new FormData();
        formData.append('action', 'send_purchase_order_email');
        formData.append('order_id', orderId || '');
        formData.append('supplier_name', supplierName || '');
        formData.append('supplier_id', supplierId);
        formData.append('to', $('#email-to').val());
        formData.append('subject', $('#email-subject').val());
        formData.append('body', $('#email-body').val());

        // nonceを追加
        let nonce = '';
        if (typeof ktpwp_ajax_nonce !== 'undefined') {
            nonce = ktpwp_ajax_nonce;
        } else if (typeof ktp_ajax_nonce !== 'undefined') {
            nonce = ktp_ajax_nonce;
        } else if (typeof ktp_ajax_object !== 'undefined' && ktp_ajax_object.nonce) {
            nonce = ktp_ajax_object.nonce;
        } else if (typeof ktpwp_ajax !== 'undefined' && ktpwp_ajax.nonces && ktpwp_ajax.nonces.general) {
            nonce = ktpwp_ajax.nonces.general;
        } else if (typeof ktpwp_ajax !== 'undefined' && ktpwp_ajax.nonces && ktpwp_ajax.nonces.auto_save) {
            nonce = ktpwp_ajax.nonces.auto_save;
        }
        formData.append('nonce', nonce);
        formData.append('ktpwp_ajax_nonce', nonce);  // 追加: サーバー側で期待されるフィールド名

        // 選択されたファイルを追加
        const selectedFiles = window.getSelectedFiles ? window.getSelectedFiles() : [];
        selectedFiles.forEach((file, index) => {
            formData.append(`attachments[${index}]`, file);
        });

        // 送信ボタンを無効化
        $('#ktp-purchase-order-email-send').prop('disabled', true).text('送信中...');

        // 送信中表示を更新（ファイル数を表示）
        let loadingMessage = '発注メール送信中...';
        if (selectedFiles.length > 0) {
            loadingMessage += `<br><small style="color: #666;">${selectedFiles.length}件のファイルを添付中...</small>`;
        }

        $('#ktp-purchase-order-email-popup-content').html(`
            <div style="text-align: center; padding: 40px;">
                <div style="font-size: 16px; color: #666;">${loadingMessage}</div>
            </div>
        `);

        // Ajax URLの確認と代替設定
        let ajaxUrl = '';
        if (typeof ajaxurl !== 'undefined') {
            ajaxUrl = ajaxurl;
        } else if (typeof ktp_ajax_object !== 'undefined' && ktp_ajax_object.ajax_url) {
            ajaxUrl = ktp_ajax_object.ajax_url;
        } else if (typeof ktpwp_ajax !== 'undefined' && ktpwp_ajax.ajax_url) {
            ajaxUrl = ktpwp_ajax.ajax_url;
        } else {
            ajaxUrl = '/wp-admin/admin-ajax.php';
        }

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                console.log('[PURCHASE-ORDER-EMAIL] 送信レスポンス:', response);
                try {
                    const result = typeof response === 'string' ? JSON.parse(response) : response;
                    if (result.success) {
                        showSuccess('発注メールを送信しました。');
                        $('#ktp-purchase-order-email-popup').remove();
                        // 該当協力会社のコスト項目を「発注済み」に更新
                        setCostItemsOrderedAndUpdateDisplay(orderId, supplierName);
                    } else {
                        const errMsg = result.data && result.data.message ? result.data.message : '不明なエラー';
                        showErrorInPopup('メール送信に失敗しました: ' + errMsg);
                    }
                } catch (e) {
                    console.error('[PURCHASE-ORDER-EMAIL] 送信レスポンスパースエラー:', e, response);
                    showErrorInPopup('メール送信の処理中にエラーが発生しました（レスポンスが不正です）');
                }
            },
            error: function(xhr, status, error) {
                console.error('[PURCHASE-ORDER-EMAIL] 送信エラー:', {xhr, status, error});
                let errMsg = 'メール送信中にエラーが発生しました。';
                if (status === 'parsererror') {
                    errMsg = 'サーバーからの応答が不正です。PHPのエラーや警告が出ている可能性があります。';
                } else if (xhr && xhr.responseText && xhr.responseText.length < 500) {
                    errMsg += ' ' + (xhr.responseText.trim().substring(0, 200) || '');
                }
                showErrorInPopup(errMsg);
            },
            complete: function() {
                var $btn = $('#ktp-purchase-order-email-send');
                if ($btn.length) {
                    $btn.prop('disabled', false).text('メール送信');
                }
            }
        });
    }

    // 発注メール送信後、該当協力会社のコスト項目を発注済みにし、表示を「に発注済み」に更新
    function setCostItemsOrderedAndUpdateDisplay(orderId, supplierName) {
        if (!orderId || !supplierName) return;
        const nonce = (typeof ktp_ajax_nonce !== 'undefined') ? ktp_ajax_nonce : (typeof ktpwp_ajax_nonce !== 'undefined' ? ktpwp_ajax_nonce : '');
        if (!nonce) {
            console.warn('[PURCHASE-ORDER-EMAIL] nonceが取得できず発注済み更新をスキップします');
            updatePurchaseDisplayOnly(supplierName);
            return;
        }
        const ajaxUrl = (typeof ajaxurl !== 'undefined') ? ajaxurl : ((typeof ktpwp_ajax !== 'undefined' && ktpwp_ajax.ajax_url) ? ktpwp_ajax.ajax_url : '/wp-admin/admin-ajax.php');
        $.post(ajaxUrl, {
            action: 'ktp_set_cost_items_ordered',
            nonce: nonce,
            order_id: orderId,
            supplier_name: supplierName
        }).done(function(res) {
            if (res && res.success) {
                updatePurchaseDisplayOnly(supplierName);
            }
        }).fail(function() {
            updatePurchaseDisplayOnly(supplierName);
        });
    }

    // 画面上の該当協力会社の仕入表示を「に発注」→「に発注済み」に更新
    function updatePurchaseDisplayOnly(supplierName) {
        $('.cost-items-table tbody tr').each(function() {
            const $span = $(this).find('.purchase-display.purchase-link');
            if (!$span.length) return;
            const text = $span.text().trim();
            const isOld = (text === supplierName + 'に発注');
            const isNew = text.startsWith(supplierName + ' >') && text.endsWith('に発注');
            if (isOld || isNew) {
                $span.text(text.replace(/に発注$/, 'に発注済み')).attr('data-ordered', '1');
                if ($(this).find('.purchase-checked').length === 0) {
                    $span.after('<span class="purchase-checked" style="display:inline-block;margin-left:6px;vertical-align:middle;color:#dc3545;font-size:1.3em;font-weight:bold;">✓</span>');
                }
            }
        });
    }

    // 成功メッセージを表示
    function showSuccess(message) {
        alert('✓ ' + message);
    }

    // エラーメッセージを表示（アラート）
    function showError(message) {
        alert('✗ ' + message);
    }

    // ポップアップ内にエラー表示と閉じるボタンを表示（送信失敗時）
    function showErrorInPopup(message) {
        $('#ktp-purchase-order-email-popup-content').html(
            '<div style="padding: 24px; text-align: center;">' +
            '<p style="color: #c62828; font-weight: bold; margin-bottom: 16px;">✗ ' + (message || 'エラーが発生しました').replace(/</g, '&lt;') + '</p>' +
            '<p style="color: #666; font-size: 13px; margin-bottom: 20px;">ローカル環境ではSMTP未設定で送信できない場合があります。<br>本番でSMTP設定後、再度お試しください。</p>' +
            '<button type="button" id="ktp-purchase-order-email-error-close" style="padding: 10px 24px; background: #007cba; color: white; border: none; border-radius: 4px; cursor: pointer;">閉じる</button>' +
            '</div>'
        );
        $('#ktp-purchase-order-email-error-close').on('click', function() {
            $('#ktp-purchase-order-email-popup').remove();
        });
    }

    // 日付をフォーマット
    function formatDate(dateString) {
        if (!dateString) return '';
        const date = new Date(dateString);
        return date.getFullYear() + '年' + (date.getMonth() + 1) + '月' + date.getDate() + '日';
    }

    // 数値をフォーマット
    function numberFormat(number) {
        return new Intl.NumberFormat('ja-JP').format(number);
    }

})(jQuery); 