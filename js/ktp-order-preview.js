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
                alert(ktpwpTranslate('受注書IDが見つかりません。'));
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
                            // プレビュー表示は行わず、取得した最新内容をそのまま印刷ダイアログへ渡す
                            window.currentOrderInfo = {
                                progress: result.data.progress,
                                document_title: result.data.document_title
                            };
                            saveOrderPreviewAsPDF(orderId, result.data.preview_html);
                        } else {
                            console.error('[ORDER-PREVIEW] プレビューデータの取得に失敗:', result);
                            alert(ktpwpTranslate('プレビューデータの取得に失敗しました: ') + (result.data || 'エラー詳細不明'));
                        }
                    } catch (parseError) {
                        console.error('[ORDER-PREVIEW] レスポンス解析エラー:', parseError);
                        alert(ktpwpTranslate('プレビューデータの解析に失敗しました。'));
                    }
                },
                error: function(xhr, status, error) {
                    console.error('[ORDER-PREVIEW] Ajax エラー:', { status, error, responseText: xhr.responseText });
                    alert(ktpwpTranslate('プレビューデータの取得中にエラーが発生しました: ') + error);
                },
                complete: function() {
                    // ボタンを元に戻す
                    $('#orderPreviewButton').prop('disabled', false).html(typeof KTPSvgIcons !== 'undefined' ? KTPSvgIcons.getIcon('print', {'aria-label': '印刷する'}) : '<span class="material-symbols-outlined" aria-label="印刷する">print</span>');
                }
            });
        });
    });

    // 後方互換: 既存呼び出しがあっても直接印刷する
    window.ktpShowOrderPreview = function (orderId, previewContent, orderInfo) {
        window.currentOrderInfo = orderInfo || {};

        if (!orderId) {
            console.error('[ORDER PREVIEW] エラー: orderIdが見つかりません');
            alert(ktpwpTranslate('受注書IDが見つかりません。'));
            return;
        }

        if (!previewContent) {
            console.error('[ORDER PREVIEW] エラー: previewContentが見つかりません');
            alert(ktpwpTranslate('プレビュー内容が見つかりません。'));
            return;
        }

        saveOrderPreviewAsPDF(orderId, previewContent);
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
    function saveOrderPreviewAsPDF(orderId, previewContent) {
        const saveContent = previewContent || $('#ktp-order-preview-content').html();
        
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

    // 現在のページで直接印刷する方法（隠しiframeで印刷し、タブを増やさない）
    function printOrderPreviewDirect(content, filename, orderId) {
        const printHTML = createPrintableHTML(content, orderId);
        const safeBaseTitle = sanitizeFilename(filename || '受注書');
        const safeTitle = safeBaseTitle + '.pdf';

        const iframe = document.createElement('iframe');
        iframe.style.position = 'fixed';
        iframe.style.right = '0';
        iframe.style.bottom = '0';
        iframe.style.width = '0';
        iframe.style.height = '0';
        iframe.style.border = '0';
        iframe.style.visibility = 'hidden';
        document.body.appendChild(iframe);

        let cleanupDone = false;
        const cleanup = function() {
            if (cleanupDone) return;
            cleanupDone = true;
            setTimeout(function() {
                try { document.body.removeChild(iframe); } catch(_) {}
            }, 300);
        };

        let printed = false;
        const triggerPrint = function() {
            if (printed) return;
            printed = true;
            try {
                const frameWin = iframe.contentWindow || iframe;
                frameWin.focus();
                frameWin.onafterprint = cleanup;
                setTimeout(function() {
                    try { frameWin.print(); } catch(e) { cleanup(); }
                }, 50);
            } catch (e) {
                cleanup();
            }
        };

        try {
            const frameDoc = (iframe.contentDocument || iframe.contentWindow.document);
            iframe.onload = function() {
                try {
                    const d = iframe.contentDocument || iframe.contentWindow.document;
                    // タイトルを設定
                    if (d) {
                        if (d.title !== undefined) {
                            d.title = safeTitle;
                        } else if (d.head) {
                            const t = d.createElement('title');
                            t.textContent = safeTitle;
                            d.head.appendChild(t);
                        }
                    }
                } catch (_) {}
                triggerPrint();
            };
            frameDoc.open();
            frameDoc.write(printHTML);
            frameDoc.close();
        } catch (e) {
            console.error('[ORDER PREVIEW] iframe印刷処理に失敗:', e);
            cleanup();
        }

        // 念のためのタイムアウトクリーンアップ
        setTimeout(cleanup, 10000);
    }

    // ファイル名生成関数
    function sanitizeFilename(value) {
        // 印刷をPDF保存した際のファイル名には禁止文字が含まれうるためサニタイズする
        // 例: macOS/Windowsでコロン（: / ：）等が問題になり、ブラウザがフォールバック名を使うことがある
        return String(value)
            .replace(/[\u0000-\u001F\/\\:\uFF1A*\?"<>\|]/g, '-')
            .replace(/\s+/g, ' ')
            .trim();
    }

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
        const rawFilename = `${documentTitle}_ID${orderId}_${dateString}`;
        return sanitizeFilename(rawFilename);
    }

    // 印刷可能なHTMLを生成（PDF最適化）
    function createPrintableHTML(content, orderId) {
        return `<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>受注書 ID ${orderId}</title>
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
