/**
 * 受注書プレビューポップアップ機能
 *
 * @package KTPWP
 * @since 1.0.0
 */

(function ($) {
    'use strict';

    console.log('[ORDER-PREVIEW] スクリプトが読み込まれました - Version: 2024-06-18');
    console.log('[ORDER-PREVIEW] window.ktpShowOrderPreview定義を開始');

    // HTMLエンティティをデコードする関数
    function decodeHtmlEntities(text) {
        const textArea = document.createElement('textarea');
        textArea.innerHTML = text;
        return textArea.value;
    }

    // 依存関係チェック
    $(document).ready(function() {
        console.log('[ORDER-PREVIEW] DOM準備完了');
        console.log('[ORDER-PREVIEW] jQuery available:', typeof $ !== 'undefined');
        console.log('[ORDER-PREVIEW] ktpShowOrderPreview function available:', typeof window.ktpShowOrderPreview !== 'undefined');
        
        // ボタンの存在確認
        const orderPreviewButton = document.getElementById('orderPreviewButton');
        console.log('[ORDER-PREVIEW] orderPreviewButton found:', !!orderPreviewButton);
        if (orderPreviewButton) {
            console.log('[ORDER-PREVIEW] orderPreviewButton onclick:', orderPreviewButton.getAttribute('onclick'));
            console.log('[ORDER-PREVIEW] orderPreviewButton data-order-id:', orderPreviewButton.getAttribute('data-order-id'));
            console.log('[ORDER-PREVIEW] orderPreviewButton data-preview-content length:', orderPreviewButton.getAttribute('data-preview-content') ? orderPreviewButton.getAttribute('data-preview-content').length : 'なし');
        }
        
        // ボタンクリックイベントを設定 - 最新データをAjaxで取得
        $(document).on('click', '#orderPreviewButton', function(e) {
            e.preventDefault();
            console.log('[ORDER-PREVIEW] ボタンがクリックされました！');
            
            const orderId = $(this).data('order-id');
            
            console.log('[ORDER-PREVIEW] データ取得 - OrderID:', orderId);
            
            if (!orderId) {
                console.error('[ORDER-PREVIEW] 受注書IDが見つかりません');
                alert('受注書IDが見つかりません。');
                return;
            }

            // ローディング表示
            $(this).prop('disabled', true).html('<span class="material-symbols-outlined">hourglass_empty</span>');
            
            // Ajaxで最新のプレビューデータを取得
            $.ajax({
                url: typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php',
                type: 'POST',
                data: {
                    action: 'ktp_get_order_preview',
                    order_id: orderId,
                    nonce: typeof ktp_ajax_nonce !== 'undefined' ? ktp_ajax_nonce : 
                          typeof ktp_ajax_object !== 'undefined' ? ktp_ajax_object.nonce : ''
                },
                success: function(response) {
                    console.log('[ORDER-PREVIEW] Ajax成功:', response);
                    
                    try {
                        const result = typeof response === 'string' ? JSON.parse(response) : response;
                        
                        if (result.success && result.data && result.data.preview_html) {
                            console.log('[ORDER-PREVIEW] 最新プレビュー取得成功');
                            // プレビューデータに進捗情報とタイトル情報を含める
                            window.ktpShowOrderPreview(orderId, result.data.preview_html, {
                                progress: result.data.progress,
                                document_title: result.data.document_title
                            });
                        } else {
                            console.error('[ORDER-PREVIEW] プレビューデータの取得に失敗:', result);
                            alert('プレビューデータの取得に失敗しました: ' + (result.data || 'エラー詳細不明'));
                        }
                    } catch (parseError) {
                        console.error('[ORDER-PREVIEW] レスポンス解析エラー:', parseError);
                        alert('プレビューデータの解析に失敗しました。');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('[ORDER-PREVIEW] Ajax エラー:', { status, error, responseText: xhr.responseText });
                    alert('プレビューデータの取得中にエラーが発生しました: ' + error);
                },
                complete: function() {
                    // ボタンを元に戻す
                    $('#orderPreviewButton').prop('disabled', false).html('<span class="material-symbols-outlined" aria-label="プレビュー">preview</span>');
                }
            });
        });
    });

    // 受注書プレビューポップアップの表示
    window.ktpShowOrderPreview = function (orderId, previewContent, orderInfo) {
        console.log('[ORDER PREVIEW] ===== ktpShowOrderPreview 関数が呼び出されました =====');
        console.log('[ORDER PREVIEW] 引数 orderId:', orderId);
        console.log('[ORDER PREVIEW] 引数 previewContent:', previewContent ? 'データあり (' + previewContent.length + ' 文字)' : 'データなし');
        console.log('[ORDER PREVIEW] 引数 orderInfo:', orderInfo);
        
        // グローバル変数として保存（PDF保存時に使用）
        window.currentOrderInfo = orderInfo || {};
        console.log('[ORDER PREVIEW] 関数が正常に呼び出されました');

        if (!orderId) {
            console.error('[ORDER PREVIEW] エラー: orderIdが見つかりません');
            alert('受注書IDが見つかりません。');
            return;
        }

        if (!previewContent) {
            console.error('[ORDER PREVIEW] エラー: previewContentが見つかりません');
            alert('プレビュー内容が見つかりません。');
            return;
        }

        // HTMLコンテンツはすでにデコード済みなので、そのまま使用

        // ポップアップHTML
        const popupHtml = `
            <div id="ktp-order-preview-popup" style="
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
                    max-width: 800px;
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
                        <h3 style="margin: 0; color: #333;">受注書プレビュー</h3>
                        <button type="button" id="ktp-order-preview-close" style="
                            background: none;
                            color: #333;
                            border: none;
                            cursor: pointer;
                            font-size: 28px;
                            padding: 0;
                            line-height: 1;
                        ">×</button>
                    </div>
                    <div id="ktp-order-preview-content" style="
                        margin-bottom: 20px;
                        padding: 20px;
                        border: 1px solid #ddd;
                        border-radius: 4px;
                        background: #fff;
                        min-height: 300px;
                        font-family: 'Noto Sans JP', 'Hiragino Kaku Gothic ProN', Meiryo, sans-serif;
                        line-height: 1.6;
                        color: #333;
                    ">
                        ${previewContent}
                    </div>
                    <div style="
                        display: flex;
                        justify-content: center;
                        gap: 10px;
                        border-top: 1px solid #eee;
                        padding-top: 15px;
                    ">
                        <button type="button" id="ktp-order-preview-print" style="
                            background: #2196f3;
                            color: white;
                            border: none;
                            padding: 10px 20px;
                            border-radius: 4px;
                            cursor: pointer;
                            font-size: 16px;
                            display: flex;
                            align-items: center;
                            gap: 8px;
                        ">
                            <span class="material-symbols-outlined">print</span>
                            印刷
                        </button>
                        <button type="button" id="ktp-order-preview-save-pdf" style="
                            background: #ff9800;
                            color: white;
                            border: none;
                            padding: 10px 20px;
                            border-radius: 4px;
                            cursor: pointer;
                            font-size: 16px;
                            display: flex;
                            align-items: center;
                            gap: 8px;
                        ">
                            <span class="material-symbols-outlined">picture_as_pdf</span>
                            PDF保存
                        </button>
                    </div>
                </div>
            </div>
        `;

        // ポップアップを追加
        $('body').append(popupHtml);

        // ポップアップを閉じる関数
        function closeOrderPreview() {
            $('#ktp-order-preview-popup').remove();
            $(document).off('keyup.order-preview');
        }

        // 閉じるボタンのイベント
        $(document).on('click', '#ktp-order-preview-close', function() {
            closeOrderPreview();
        });

        // Escapeキーで閉じる
        $(document).on('keyup.order-preview', function(e) {
            if (e.keyCode === 27) { // Escape key
                closeOrderPreview();
            }
        });

        // 背景クリックで閉じる
        $(document).on('click', '#ktp-order-preview-popup', function(e) {
            if (e.target === this) {
                closeOrderPreview();
            }
        });

        // 印刷ボタンのイベント
        $(document).on('click', '#ktp-order-preview-print', function() {
            printOrderPreview();
        });

        // PDF保存ボタンのイベント
        $(document).on('click', '#ktp-order-preview-save-pdf', function() {
            saveOrderPreviewAsPDF(orderId);
        });
    };

    // デバッグ用: Ajaxハンドラーのテスト関数
    window.ktpTestOrderPreview = function(orderId) {
        console.log('[ORDER PREVIEW TEST] テスト開始 - OrderID:', orderId);
        
        $.ajax({
            url: typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php',
            type: 'POST',
            data: {
                action: 'ktp_get_order_preview',
                order_id: orderId,
                nonce: typeof ktp_ajax_nonce !== 'undefined' ? ktp_ajax_nonce : 
                      typeof ktp_ajax_object !== 'undefined' ? ktp_ajax_object.nonce : ''
            },
            success: function(response) {
                console.log('[ORDER PREVIEW TEST] 成功:', response);
            },
            error: function(xhr, status, error) {
                console.error('[ORDER PREVIEW TEST] エラー:', { status, error, responseText: xhr.responseText });
            }
        });
    };

    // 印刷機能
    function printOrderPreview() {
        console.log('[ORDER PREVIEW] 印刷開始');
        
        const printContent = $('#ktp-order-preview-content').html();
        
        // 印刷用ウィンドウを作成
        const printWindow = window.open('', '_blank');
        
        if (!printWindow) {
            showPopupBlockedMessage();
            return;
        }
        
        printWindow.document.open();
        printWindow.document.write(`
            <html>
            <head>
                <title>受注書プレビュー - 印刷</title>
                <meta charset="UTF-8">
                <style>
                    body {
                        font-family: "Noto Sans JP", "Hiragino Kaku Gothic ProN", Meiryo, sans-serif;
                        margin: 0;
                        padding: 0;
                        color: #333;
                        line-height: 1.4;
                    }
                    @media print {
                        body { 
                            margin: 0; 
                            padding: 0;
                        }
                        .no-print { display: none; }
                        .order-preview-document {
                            max-width: none !important;
                            margin: 0 !important;
                            padding: 10mm !important;
                            min-height: auto !important;
                        }
                        /* ページ区切り処理 */
                        div[style*="page-break-before: always"] {
                            page-break-before: always;
                        }
                    }
                    @page {
                        size: A4;
                        margin: 10mm;
                    }
                </style>
            </head>
            <body>
                ${printContent}
                <script>
                    var isDialogClosed = false;
                    var startTime = Date.now();
                    
                    window.onload = function() {
                        setTimeout(function() {
                            window.print();
                        }, 500);
                    };
                    
                    // 印刷ダイアログ終了の検知
                    window.onafterprint = function() {
                        isDialogClosed = true;
                        setTimeout(function() {
                            window.close();
                        }, 100);
                    };
                    
                    // フォーカス変更での検知（代替手段）
                    window.onfocus = function() {
                        if (Date.now() - startTime > 1000 && !isDialogClosed) {
                            isDialogClosed = true;
                            setTimeout(function() {
                                window.close();
                            }, 500);
                        }
                    };
                    
                    // 強制クローズ（10秒後）
                    setTimeout(function() {
                        if (!window.closed) {
                            window.close();
                        }
                    }, 10000);
                </script>
            </body>
            </html>
        `);
        printWindow.document.close();
        
        // ウィンドウが閉じられない場合の追加対策
        setTimeout(function() {
            if (printWindow && !printWindow.closed) {
                try {
                    printWindow.close();
                } catch (e) {
                    console.log('[ORDER PREVIEW] ウィンドウクローズエラー:', e);
                }
            }
        }, 15000);
    }

    // PDF保存機能 - 直接PDFダウンロード
    function saveOrderPreviewAsPDF(orderId) {
        console.log('[ORDER PREVIEW] PDF保存開始', { orderId });
        
        const saveContent = $('#ktp-order-preview-content').html();
        
        // ファイル名を要求された形式で生成
        const filename = generateFilename(orderId);
        
        // Canvas APIを使用してPDF生成
        generatePDFFromHTML(saveContent, filename, orderId);
    }

    // HTMLからPDFを生成してダウンロード
    function generatePDFFromHTML(content, filename, orderId) {
        // 新しいウィンドウでPDF生成処理
        const printWindow = window.open('', '_blank');
        
        if (!printWindow) {
            showPopupBlockedMessage();
            return;
        }
        
        // PDF生成用HTMLを作成
        const printContent = createPrintableHTML(content, orderId);
        
        printWindow.document.open();
        printWindow.document.write(printContent);
        printWindow.document.close();
        
        // ウィンドウが読み込まれた後にPDF生成を実行
        printWindow.onload = function() {
            setTimeout(function() {
                try {
                    // ブラウザの印刷機能を使ってPDFを生成
                    printWindow.print();
                    
                    // 印刷完了後の処理（自動クローズはHTMLのJavaScriptで処理）
                    printWindow.onafterprint = function() {
                        setTimeout(function() {
                            if (!printWindow.closed) {
                                printWindow.close();
                            }
                            showSaveMessage('PDFを保存しました。');
                        }, 500);
                    };
                    
                } catch (error) {
                    console.error('[ORDER PREVIEW] PDF生成エラー:', error);
                    printWindow.close();
                    showSaveMessage('PDF保存でエラーが発生しました。');
                }
            }, 1000);
        };
        
        // 追加の自動クローズ機能（フェイルセーフ）
        let autoCloseTimer = setTimeout(function() {
            if (printWindow && !printWindow.closed) {
                try {
                    printWindow.close();
                    showSaveMessage('PDFを保存しました。');
                } catch (e) {
                    console.log('[ORDER PREVIEW] 自動クローズエラー:', e);
                }
            }
        }, 15000);
        
        // ウィンドウが手動で閉じられた場合のタイマークリア
        const checkClosed = setInterval(function() {
            if (printWindow.closed) {
                clearTimeout(autoCloseTimer);
                clearInterval(checkClosed);
                showSaveMessage('PDFを保存しました。');
            }
        }, 1000);
        
        console.log('[ORDER PREVIEW] PDF生成プロセス開始', { filename });
    }

    // ファイル名生成関数
    function generateFilename(orderId) {
        // 現在の日付を取得（YYYYMMDD形式）
        const currentDate = new Date();
        const year = currentDate.getFullYear();
        const month = String(currentDate.getMonth() + 1).padStart(2, '0');
        const day = String(currentDate.getDate()).padStart(2, '0');
        const dateString = `${year}${month}${day}`;
        
        // 帳票タイトルを取得（デフォルトは受注書）
        const documentTitle = (window.currentOrderInfo && window.currentOrderInfo.document_title) 
            ? window.currentOrderInfo.document_title 
            : '受注書';
        
        // ファイル名生成: {タイトル}_ID{id}_{発行日}.pdf
        // macOSでコロン（：）が問題になるため除去
        const filename = `${documentTitle}_ID${orderId}_${dateString}`;
        
        console.log('[ORDER PREVIEW] 生成されたファイル名:', filename);
        return filename;
    }

    // 印刷可能なHTMLを生成（PDF最適化）
    function createPrintableHTML(content, orderId) {
        return `<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>受注書 - ID: ${orderId}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: "Noto Sans JP", "Hiragino Kaku Gothic ProN", "Yu Gothic", Meiryo, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #333;
            background: #f5f5f5;
            padding: 20px;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .page-container {
            width: 210mm;
            max-width: 210mm;
            margin: 0 auto;
            background: white;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            padding: 15mm;
            min-height: 297mm;
        }
        .order-preview-document {
            max-width: none !important;
            margin: 0 !important;
            padding: 0 !important;
            min-height: auto !important;
            background: white;
        }
        /* ページ区切り処理 */
        div[style*="page-break-before: always"] {
            page-break-before: always;
        }
        @page {
            size: A4;
            margin: 15mm;
        }
        @media print {
            body { 
                margin: 0; 
                padding: 0;
                background: white;
            }
            .page-container {
                box-shadow: none;
                margin: 0;
                padding: 0;
                width: auto;
                max-width: none;
                min-height: auto;
            }
            .no-print, .pdf-instructions { 
                display: none !important; 
            }
        }
        /* フォント最適化 */
        h1, h2, h3, h4, h5, h6 {
            font-weight: bold;
        }
        /* 色の保持 */
        * {
            -webkit-print-color-adjust: exact !important;
            color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        .pdf-instructions {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            margin: 20px auto;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            max-width: 600px;
        }
        .pdf-instructions h3 {
            margin-bottom: 15px;
            font-size: 18px;
        }
        .pdf-instructions p {
            margin: 8px 0;
            font-size: 14px;
        }
        .pdf-instructions .highlight {
            background: rgba(255,255,255,0.2);
            padding: 8px 12px;
            border-radius: 6px;
            margin: 10px 0;
            font-weight: bold;
        }
    </style>
    <script>
        // ページ読み込み完了後に自動でPDF保存を促す
        window.addEventListener('load', function() {
            document.title = '${generateFilename(orderId)}.pdf';
            
            // ブラウザ判定
            const isChrome = navigator.userAgent.includes('Chrome');
            const isEdge = navigator.userAgent.includes('Edge');
            const isSafari = navigator.userAgent.includes('Safari') && !isChrome;
            const isFirefox = navigator.userAgent.includes('Firefox');
            
            // PDF保存を促すメッセージを更新
            const instructions = document.querySelector('.pdf-instructions');
            if (instructions) {
                if (isChrome || isEdge) {
                    instructions.innerHTML = '<h3>📄 PDF保存中...</h3><p>印刷ダイアログで「PDFとして保存」を選択してください</p><div class="highlight">送信先: PDFに保存</div>';
                } else if (isSafari) {
                    instructions.innerHTML = '<h3>📄 PDF保存中...</h3><p>印刷ダイアログで「PDFとして保存」を選択してください</p><div class="highlight">PDF ボタンをクリック</div>';
                } else {
                    instructions.innerHTML = '<h3>📄 PDF保存中...</h3><p>印刷ダイアログで「PDFとして保存」を選択してください</p>';
                }
            }
        });
        
        // 印刷完了を検知するためのイベントリスナー
        window.addEventListener('afterprint', function() {
            // 印刷完了後、少し待ってからウィンドウを閉じる
            setTimeout(function() {
                window.close();
            }, 1000);
        });
        
        // フォーカス変更での検知（代替手段）
        let printDialogClosed = false;
        window.addEventListener('focus', function() {
            if (!printDialogClosed) {
                printDialogClosed = true;
                setTimeout(function() {
                    window.close();
                }, 1500);
            }
        });
    </script>
</head>
<body>
    <div class="pdf-instructions no-print">
        <h3>📄 PDF保存の準備ができました</h3>
        <p>印刷ダイアログで「PDFとして保存」を選択してください</p>
        <div class="highlight">
            自動的に印刷ダイアログが表示されます
        </div>
    </div>
    <div class="page-container">
        ${content}
    </div>
</body>
</html>`;
    }

    // 保存メッセージ表示関数
    function showSaveMessage(message) {
        // 既存のメッセージがあれば削除
        $('#ktp-save-message').remove();
        
        // メッセージ要素を作成
        const messageHtml = `
            <div id="ktp-save-message" style="
                position: fixed;
                top: 20px;
                right: 20px;
                background: #4caf50;
                color: white;
                padding: 15px 20px;
                border-radius: 4px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.2);
                z-index: 10001;
                max-width: 300px;
                word-wrap: break-word;
            ">
                ${message}
            </div>
        `;
        
        $('body').append(messageHtml);
        
        // 5秒後に自動で消去
        setTimeout(function() {
            $('#ktp-save-message').fadeOut(300, function() {
                $(this).remove();
            });
        }, 5000);
    }

    // ポップアップブロック通知関数
    function showPopupBlockedMessage() {
        // 既存のメッセージがあれば削除
        $('#ktp-popup-blocked-message').remove();
        
        // メッセージ要素を作成
        const messageHtml = `
            <div id="ktp-popup-blocked-message" style="
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                background: #ff9800;
                color: white;
                padding: 20px 25px;
                border-radius: 8px;
                box-shadow: 0 4px 20px rgba(0,0,0,0.3);
                z-index: 10002;
                max-width: 400px;
                text-align: center;
                word-wrap: break-word;
            ">
                <div style="font-size: 18px; font-weight: bold; margin-bottom: 10px;">
                    ⚠️ ポップアップがブロックされました
                </div>
                <div style="margin-bottom: 15px;">
                    ブラウザの設定でポップアップを許可してから再度お試しください。
                </div>
                <button onclick="$('#ktp-popup-blocked-message').remove();" style="
                    background: white;
                    color: #ff9800;
                    border: none;
                    padding: 8px 16px;
                    border-radius: 4px;
                    cursor: pointer;
                    font-weight: bold;
                ">
                    閉じる
                </button>
            </div>
        `;
        
        $('body').append(messageHtml);
        
        // 10秒後に自動で消去
        setTimeout(function() {
            $('#ktp-popup-blocked-message').fadeOut(300, function() {
                $(this).remove();
            });
        }, 10000);
    }

})(jQuery);
