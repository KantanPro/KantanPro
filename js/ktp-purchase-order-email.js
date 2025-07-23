/**
 * 発注メールポップアップ機能
 * 
 * @package KTPWP
 * @since 1.0.0
 */

(function($) {
    'use strict';

    // 発注メールポップアップを表示
    window.ktpShowPurchaseOrderEmailPopup = function(orderId, supplierName) {
        console.log('[PURCHASE-ORDER-EMAIL] ポップアップ表示開始', { orderId, supplierName });

        if (!orderId || !supplierName) {
            alert('受注書IDまたは協力会社名が指定されていません。');
            return;
        }

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
        loadPurchaseOrderEmailContent(orderId, supplierName);
    };

    // 発注メール内容を読み込み
    function loadPurchaseOrderEmailContent(orderId, supplierName) {
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
                nonce: nonce,
                ktpwp_ajax_nonce: nonce  // 追加: サーバー側で期待されるフィールド名
            },
            success: function(response) {
                console.log('[PURCHASE-ORDER-EMAIL] レスポンス受信:', response);
                try {
                    const result = typeof response === 'string' ? JSON.parse(response) : response;
                    if (result.success && result.data) {
                        displayPurchaseOrderEmailForm(result.data);
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

    // 発注メールフォームを表示
    function displayPurchaseOrderEmailForm(data) {
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
                    <textarea id="email-body" name="body" rows="25" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; font-family: monospace; font-size: 12px;" required>${data.body}</textarea>
                </div>

                <div style="margin-bottom: 15px;">
                    <label for="email-attachments" style="display: block; margin-bottom: 5px; font-weight: bold;">添付ファイル:</label>
                    <input type="file" id="email-attachments" name="attachments[]" multiple style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box;">
                    <small style="color: #666;">複数ファイル選択可能（各ファイル10MB以下、合計50MB以下）</small>
                </div>

                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" id="ktp-purchase-order-email-cancel" style="
                        padding: 10px 20px;
                        background: #6c757d;
                        color: white;
                        border: none;
                        border-radius: 4px;
                        cursor: pointer;
                    ">キャンセル</button>
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

        // イベントハンドラーを設定
        $('#ktp-purchase-order-email-cancel').on('click', function() {
            $('#ktp-purchase-order-email-popup').remove();
        });

        $('#ktp-purchase-order-email-form').on('submit', function(e) {
            e.preventDefault();
            sendPurchaseOrderEmail(orderId, supplierName);
        });
    }

    // 発注メールを送信
    function sendPurchaseOrderEmail(orderId, supplierName) {
        const formData = new FormData();
        formData.append('action', 'send_purchase_order_email');
        formData.append('order_id', orderId);
        formData.append('supplier_name', supplierName);
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

        // 添付ファイルを追加
        const fileInput = $('#email-attachments')[0];
        if (fileInput.files.length > 0) {
            for (let i = 0; i < fileInput.files.length; i++) {
                formData.append('attachments[]', fileInput.files[i]);
            }
        }

        // 送信ボタンを無効化
        $('#ktp-purchase-order-email-send').prop('disabled', true).text('送信中...');

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
                    } else {
                        showError('メール送信に失敗しました: ' + (result.data ? result.data.message : '不明なエラー'));
                    }
                } catch (e) {
                    console.error('[PURCHASE-ORDER-EMAIL] 送信レスポンスパースエラー:', e, response);
                    showError('メール送信の処理中にエラーが発生しました');
                }
            },
            error: function(xhr, status, error) {
                console.error('[PURCHASE-ORDER-EMAIL] 送信エラー:', {xhr, status, error});
                showError('メール送信中にエラーが発生しました');
            },
            complete: function() {
                // 送信ボタンを再有効化
                $('#ktp-purchase-order-email-send').prop('disabled', false).text('メール送信');
            }
        });
    }

    // 成功メッセージを表示
    function showSuccess(message) {
        alert('✓ ' + message);
    }

    // エラーメッセージを表示
    function showError(message) {
        alert('✗ ' + message);
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