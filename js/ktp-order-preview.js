/**
 * 受注書プレビューポップアップ機能
 *
 * @package KTPWP
 * @since 1.0.0
 */

(function ($) {
    'use strict';

    // PDF生成ライブラリの動的ロード
    function loadPDFLibraries() {
        return new Promise((resolve, reject) => {
            if (typeof html2canvas !== 'undefined' && typeof jsPDF !== 'undefined') {
                resolve();
                return;
            }

            let html2canvasLoaded = typeof html2canvas !== 'undefined';
            let jsPDFLoaded = typeof jsPDF !== 'undefined';

            // html2canvasの読み込み
            if (!html2canvasLoaded) {
                const html2canvasScript = document.createElement('script');
                html2canvasScript.src = 'https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js';
                html2canvasScript.onload = function() {
                    html2canvasLoaded = true;
                    if (jsPDFLoaded) resolve();
                };
                html2canvasScript.onerror = function() {
                    console.error('[ORDER-PREVIEW] html2canvas読み込み失敗');
                    reject('html2canvas読み込み失敗');
                };
                document.head.appendChild(html2canvasScript);
            }

            // jsPDFの読み込み
            if (!jsPDFLoaded) {
                const jsPDFScript = document.createElement('script');
                jsPDFScript.src = 'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js';
                jsPDFScript.onload = function() {
                    // jsPDFをグローバルに設定
                    if (typeof window.jspdf !== 'undefined') {
                        window.jsPDF = window.jspdf.jsPDF;
                    }
                    jsPDFLoaded = true;
                    if (html2canvasLoaded) resolve();
                };
                jsPDFScript.onerror = function() {
                    console.error('[ORDER-PREVIEW] jsPDF読み込み失敗');
                    reject('jsPDF読み込み失敗');
                };
                document.head.appendChild(jsPDFScript);
            }

            if (html2canvasLoaded && jsPDFLoaded) {
                resolve();
            }
        });
    }

    // HTMLエンティティをデコードする関数
    function decodeHtmlEntities(text) {
        const textArea = document.createElement('textarea');
        textArea.innerHTML = text;
        return textArea.value;
    }

    // 依存関係チェック
    $(document).ready(function() {
        // ボタンクリックイベントを設定 - 最新データをAjaxで取得
        $(document).on('click', '#orderPreviewButton', function(e) {
            e.preventDefault();
            
            const orderId = $(this).data('order-id');
            
            if (!orderId) {
                console.error('[ORDER-PREVIEW] 受注書IDが見つかりません');
                alert('受注書IDが見つかりません。');
                return;
            }

            // ローディング表示
            $(this).prop('disabled', true).html(typeof KTPSvgIcons !== 'undefined' ? KTPSvgIcons.getIcon('hourglass_empty') : '<span class="material-symbols-outlined">hourglass_empty</span>');
            
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
                    try {
                        const result = typeof response === 'string' ? JSON.parse(response) : response;
                        
                        if (result.success && result.data && result.data.preview_html) {
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
                    $('#orderPreviewButton').prop('disabled', false).html(typeof KTPSvgIcons !== 'undefined' ? KTPSvgIcons.getIcon('preview', {'aria-label': 'プレビュー'}) : '<span class="material-symbols-outlined" aria-label="プレビュー">preview</span>');
                }
            });
        });
    });

    // 受注書プレビューポップアップの表示
    window.ktpShowOrderPreview = function (orderId, previewContent, orderInfo) {
        // グローバル変数として保存（PDF保存時に使用）
        window.currentOrderInfo = orderInfo || {};

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
                            ${typeof KTPSvgIcons !== 'undefined' ? KTPSvgIcons.getIcon('print') : '<span class="material-symbols-outlined">print</span>'}
                            印刷 PDF保存
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

        // 印刷 PDF保存ボタンのイベント
        $(document).on('click', '#ktp-order-preview-save-pdf', function() {
            saveOrderPreviewAsPDF(orderId);
        });
    };

    // デバッグ用: Ajaxハンドラーのテスト関数
    window.ktpTestOrderPreview = function(orderId) {
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
    
    // PDF保存機能 - 印刷ダイアログ経由でPDF保存
    function saveOrderPreviewAsPDF(orderId) {
        const saveContent = $('#ktp-order-preview-content').html();
        
        // ファイル名を要求された形式で生成
        const filename = generateFilename(orderId);
        
        // 現在のページで直接印刷する方法
        printOrderPreviewDirect(saveContent, filename, orderId);
    }

    // 直接ダウンロード方式でPDF生成（フォールバック用）
    function generatePDFDirectDownload(content, filename, orderId) {
        // 一時的な印刷用要素を作成
        const printElement = document.createElement('div');
        printElement.innerHTML = content;
        printElement.style.position = 'fixed';
        printElement.style.left = '-9999px';
        printElement.style.top = '0';
        printElement.style.width = '210mm';
        printElement.style.backgroundColor = 'white';
        printElement.style.fontFamily = '"Noto Sans JP", "Hiragino Kaku Gothic ProN", Meiryo, sans-serif';
        printElement.style.fontSize = '12px';
        printElement.style.lineHeight = '1.4';
        printElement.style.color = '#333';
        
        document.body.appendChild(printElement);
        
        // html2canvasとjsPDFを使用してPDF生成
        if (typeof html2canvas !== 'undefined' && typeof jsPDF !== 'undefined') {
            html2canvas(printElement, {
                scale: 2,
                useCORS: true,
                allowTaint: true,
                backgroundColor: '#ffffff',
                width: printElement.scrollWidth,
                height: printElement.scrollHeight
            }).then(function(canvas) {
                const imgData = canvas.toDataURL('image/png');
                const pdf = new jsPDF('p', 'mm', 'a4');
                
                const imgWidth = 210; // A4幅
                const pageHeight = 295; // A4高さ
                const imgHeight = (canvas.height * imgWidth) / canvas.width;
                let heightLeft = imgHeight;
                let position = 0;
                
                // 最初のページ
                pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
                heightLeft -= pageHeight;
                
                // 複数ページの場合の処理
                while (heightLeft >= 0) {
                    position = heightLeft - imgHeight;
                    pdf.addPage();
                    pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
                    heightLeft -= pageHeight;
                }
                
                // PDFを保存
                pdf.save(filename + '.pdf');
                
                // 一時要素を削除
                document.body.removeChild(printElement);
                
            }).catch(function(error) {
                console.error('[ORDER PREVIEW] Canvas生成エラー:', error);
                document.body.removeChild(printElement);
                
                // フォールバック: 直接印刷方式
                printOrderPreviewDirect(content, filename, orderId);
            });
        } else {
            document.body.removeChild(printElement);
            
            // フォールバック: 直接印刷方式
            printOrderPreviewDirect(content, filename, orderId);
        }
    }

    // 現在のページで直接印刷する方法
    function printOrderPreviewDirect(content, filename, orderId) {
        // 現在のページの状態を保存
        const originalBody = document.body.innerHTML;
        const originalTitle = document.title;
        
        // 印刷用のHTMLを作成
        const printHTML = createPrintableHTML(content, orderId);
        
        // ページの内容を印刷用に変更
        document.body.innerHTML = printHTML;
        document.title = filename + '.pdf';
        
        // 印刷ダイアログを表示
        window.print();
        
        // 印刷完了後、元の内容に戻す
        setTimeout(function() {
            document.body.innerHTML = originalBody;
            document.title = originalTitle;
            
            // 受注書プレビューポップアップを閉じる
            const popup = document.getElementById('ktp-order-preview-popup');
            if (popup) {
                popup.remove();
            }
        }, 1000);
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
            background: white;
            padding: 20px;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .page-container {
            width: 210mm;
            max-width: 210mm;
            margin: 0 auto;
            background: white;
            padding: 50px;
        }
        /* ページ区切り処理 */
        div[style*="page-break-before: always"] {
            page-break-before: always;
        }
        @page {
            size: A4;
            margin: 50px;
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
    </style>
</head>
<body>
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
