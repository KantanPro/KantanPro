/**
 * メール送信ポップアップ機能
 *
 * @package KTPWP
 * @since 1.0.0
 */

(function ($) {
    'use strict';

    console.log('[EMAIL-POPUP] スクリプトが読み込まれました');

    // 依存関係チェック
    $(document).ready(function() {
        console.log('[EMAIL-POPUP] DOM準備完了');
        console.log('[EMAIL-POPUP] jQuery available:', typeof $ !== 'undefined');
        console.log('[EMAIL-POPUP] ktp_ajax_object available:', typeof ktp_ajax_object !== 'undefined');
        if (typeof ktp_ajax_object !== 'undefined') {
            console.log('[EMAIL-POPUP] Ajax URL:', ktp_ajax_object.ajax_url);
            console.log('[EMAIL-POPUP] Nonce:', ktp_ajax_object.nonce);
        }
    });

    // メール送信ポップアップの表示
    window.ktpShowEmailPopup = function (orderId) {
        console.log('[EMAIL POPUP] ポップアップ表示開始', { orderId });

        if (!orderId) {
            alert('受注書IDが見つかりません。');
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
                    padding: 15px;
                    width: 95%;
                    max-width: 600px;
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
                        <h3 style="margin: 0; color: #333;">メール送信</h3>
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
                            <div style="font-size: 16px; color: #666;">メール内容を読み込み中...</div>
                        </div>
                    </div>
                </div>
            </div>
        `;

        // ポップアップを追加
        $('body').append(popupHtml);

        // ポップアップを閉じる関数
        function closeEmailPopup() {
            $('#ktp-email-popup').remove();
            $(document).off('keyup.email-popup');
        }

        // 閉じるボタンのイベント
        $(document).on('click', '#ktp-email-popup-close', function() {
            closeEmailPopup();
        });

        // Escapeキーで閉じる
        $(document).on('keyup.email-popup', function(e) {
            if (e.keyCode === 27) { // Escape key
                closeEmailPopup();
            }
        });

        // 背景クリックで閉じる
        $(document).on('click', '#ktp-email-popup', function(e) {
            if (e.target === this) {
                closeEmailPopup();
            }
        });

        // メール内容を取得
        loadEmailContent(orderId);
    };

    // メール内容の取得
    function loadEmailContent(orderId) {
        const ajaxUrl = typeof ktp_ajax_object !== 'undefined' ? ktp_ajax_object.ajax_url : '/wp-admin/admin-ajax.php';
        const nonce = typeof ktp_ajax_object !== 'undefined' ? ktp_ajax_object.nonce : '';

        const ajaxData = {
            action: 'get_email_content',
            order_id: orderId,
            nonce: nonce
        };

        console.log('[EMAIL POPUP] メール内容取得開始', ajaxData);

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: ajaxData,
            dataType: 'json',
            success: function (response) {
                console.log('[EMAIL POPUP] メール内容取得成功', response);
                
                if (response.success && response.data) {
                    renderEmailForm(response.data, orderId);
                } else {
                    const errorMessage = response.data && response.data.message ? response.data.message : 'メール内容の取得に失敗しました';
                    $('#ktp-email-popup-content').html(`
                        <div style="text-align: center; padding: 40px; color: #dc3545;">
                            <div style="font-size: 16px;">${errorMessage}</div>
                            <div style="font-size: 14px; margin-top: 4px;">再度お試しください</div>
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
                
                let errorMessage = 'メール内容の読み込みに失敗しました';
                if (xhr.status === 403) {
                    errorMessage = '権限がありません。ログインを確認してください。';
                } else if (xhr.status === 404) {
                    errorMessage = '受注書が見つかりませんでした。';
                } else if (xhr.status === 500) {
                    errorMessage = 'サーバーエラーが発生しました。';
                }
                
                $('#ktp-email-popup-content').html(`
                    <div style="text-align: center; padding: 40px; color: #dc3545;">
                        <div style="font-size: 16px;">${errorMessage}</div>
                        <div style="font-size: 14px; margin-top: 8px;">ステータス: ${xhr.status} ${status}</div>
                        <div style="font-size: 14px; margin-top: 4px;">再度お試しください</div>
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
                        ${emailData.error_title}
                    </h4>
                    <p style="color: ${emailData.error_type === 'no_email' ? '#856404' : '#d32f2f'}; margin-bottom: 0;">
                        ${emailData.error}
                    </p>
                </div>
            `;
        } else {
            // メール送信フォーム
            html = `
                <form id="email-send-form" style="width: 100%;">
                    <div style="margin-bottom: 15px;">
                        <label style="display: block; font-weight: bold; margin-bottom: 5px;">宛先：</label>
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
                        <label style="display: block; font-weight: bold; margin-bottom: 5px;">件名：</label>
                        <input type="text" id="email-subject" value="${emailData.subject}" style="
                            width: 100%;
                            padding: 8px;
                            border: 1px solid #ddd;
                            border-radius: 4px;
                            box-sizing: border-box;
                        ">
                    </div>
                    <div style="margin-bottom: 20px;">
                        <label style="display: block; font-weight: bold; margin-bottom: 5px;">本文：</label>
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
                    <div style="text-align: center;">
                        <button type="submit" style="
                            background: #2196f3;
                            color: white;
                            border: none;
                            padding: 12px 24px;
                            border-radius: 4px;
                            cursor: pointer;
                            font-size: 16px;
                            font-weight: bold;
                        ">
                            メール送信
                        </button>
                    </div>
                    <input type="hidden" name="order_id" value="${orderId}">
                    <input type="hidden" name="to" value="${emailData.to}">
                </form>
            `;
        }

        $('#ktp-email-popup-content').html(html);

        // フォーム送信イベント
        $('#email-send-form').on('submit', function(e) {
            e.preventDefault();
            sendEmail(orderId);
        });
    }

    // メール送信
    function sendEmail(orderId) {
        const subject = $('#email-subject').val();
        const body = $('#email-body').val();
        const to = $('input[name="to"]').val();

        if (!subject.trim() || !body.trim()) {
            alert('件名と本文を入力してください。');
            return;
        }

        const ajaxUrl = typeof ktp_ajax_object !== 'undefined' ? ktp_ajax_object.ajax_url : '/wp-admin/admin-ajax.php';
        const nonce = typeof ktp_ajax_object !== 'undefined' ? ktp_ajax_object.nonce : '';

        const ajaxData = {
            action: 'send_order_email',
            order_id: orderId,
            to: to,
            subject: subject,
            body: body,
            nonce: nonce
        };

        // 送信中表示
        $('#ktp-email-popup-content').html(`
            <div style="text-align: center; padding: 40px;">
                <div style="font-size: 16px; color: #666;">メール送信中...</div>
            </div>
        `);

        console.log('[EMAIL POPUP] メール送信開始', ajaxData);

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: ajaxData,
            dataType: 'json',
            success: function (response) {
                console.log('[EMAIL POPUP] メール送信レスポンス', response);
                
                if (response.success) {
                    $('#ktp-email-popup-content').html(`
                        <div style="text-align: center; padding: 40px; color: #28a745;">
                            <div style="font-size: 18px; font-weight: bold; margin-bottom: 10px;">
                                ✓ メール送信完了
                            </div>
                            <div style="font-size: 14px;">
                                宛先: ${to}
                            </div>
                            <div style="margin-top: 20px;">
                                <button type="button" onclick="$('#ktp-email-popup').remove()" style="
                                    background: #28a745;
                                    color: white;
                                    border: none;
                                    padding: 8px 16px;
                                    border-radius: 4px;
                                    cursor: pointer;
                                ">
                                    閉じる
                                </button>
                            </div>
                        </div>
                    `);
                } else {
                    const errorMessage = response.data && response.data.message ? response.data.message : 'メール送信に失敗しました';
                    $('#ktp-email-popup-content').html(`
                        <div style="text-align: center; padding: 40px; color: #dc3545;">
                            <div style="font-size: 16px; font-weight: bold; margin-bottom: 10px;">
                                ✗ メール送信失敗
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
                                    再試行
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
                            ✗ メール送信エラー
                        </div>
                        <div style="font-size: 14px;">ステータス: ${xhr.status} ${status}</div>
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
