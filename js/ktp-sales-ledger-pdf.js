/**
 * Sales Ledger Print
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
                    console.error('[SALES-LEDGER-PDF] html2canvas読み込み失敗');
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
                    console.error('[SALES-LEDGER-PDF] jsPDF読み込み失敗');
                    reject('jsPDF読み込み失敗');
                };
                document.head.appendChild(jsPDFScript);
            }

            if (html2canvasLoaded && jsPDFLoaded) {
                resolve();
            }
        });
    }

    // 売上台帳PDFポップアップの表示
    function showSalesLedgerPDFPopup(pdfHtml, filename, year, totalRecords, totalAmount) {
        // ポップアップHTML
        const popupHtml = `
            <div id="sales-ledger-pdf-popup" style="
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
                    max-width: 900px;
                    max-height: 85%;
                    overflow-y: auto;
                    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
                ">
                    <div style="
                        display: flex;
                        justify-content: flex-end;
                        align-items: center;
                        margin-bottom: 15px;
                        border-bottom: 1px solid #eee;
                        padding-bottom: 10px;
                    ">
                        <button type="button" id="sales-ledger-pdf-close" style="
                            background: none;
                            color: #333;
                            border: none;
                            cursor: pointer;
                            font-size: 28px;
                            padding: 0;
                            line-height: 1;
                        ">×</button>
                    </div>
                    <div id="sales-ledger-pdf-content" style="
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
                        ${pdfHtml}
                    </div>
                    <div style="
                        display: flex;
                        justify-content: center;
                        gap: 10px;
                        border-top: 1px solid #eee;
                        padding-top: 15px;
                    ">
                        <button type="button" id="sales-ledger-pdf-print" style="
                            background: #1976d2;
                            color: white;
                            border: none;
                            padding: 12px 24px;
                            border-radius: 4px;
                            cursor: pointer;
                            font-size: 16px;
                            display: flex;
                            align-items: center;
                            gap: 8px;
                        ">
                            🖨️ 印刷
                        </button>
                    </div>
                </div>
            </div>
        `;

        // ポップアップを追加
        $('body').append(popupHtml);

        // ポップアップを閉じる関数
        function closeSalesLedgerPDFPopup() {
            $('#sales-ledger-pdf-popup').remove();
            $(document).off('keyup.sales-ledger-pdf');
        }

        // 閉じるボタンのイベント
        $(document).on('click', '#sales-ledger-pdf-close', function() {
            closeSalesLedgerPDFPopup();
        });

        // Escapeキーで閉じる
        $(document).on('keyup.sales-ledger-pdf', function(e) {
            if (e.keyCode === 27) { // Escape key
                closeSalesLedgerPDFPopup();
            }
        });

        // 背景クリックで閉じる
        $(document).on('click', '#sales-ledger-pdf-popup', function(e) {
            if (e.target === this) {
                closeSalesLedgerPDFPopup();
            }
        });

        // 印刷ボタンのイベント
        $(document).on('click', '#sales-ledger-pdf-print', function() {
            printSalesLedger(filename);
        });
    }

    // 印刷機能
    function printSalesLedger(filename) {
        const content = $('#sales-ledger-pdf-content').html();
        printSalesLedgerDirect(content, filename);
    }

    // 直接印刷する方法
    function printSalesLedgerDirect(content, filename) {
        const printHTML = createSalesLedgerPrintableHTML(content, filename);

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
                    if (d && d.title !== undefined) {
                        d.title = filename + '.pdf';
                    }
                } catch (_) {}
                triggerPrint();
            };
            frameDoc.open();
            frameDoc.write(printHTML);
            frameDoc.close();
        } catch (e) {
            console.error('[SALES-LEDGER-PDF] iframe印刷処理に失敗:', e);
            cleanup();
        }

        // タイムアウトクリーンアップ
        setTimeout(cleanup, 10000);
    }

    // 印刷可能なHTMLを生成
    function createSalesLedgerPrintableHTML(content, filename) {
        return `<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>${filename}</title>
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
            padding: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #333;
            padding: 6px;
            text-align: left;
        }
        th {
            background: #f5f5f5;
            font-weight: bold;
            text-align: center;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        h1, h2, h3 {
            font-weight: bold;
            margin-bottom: 10px;
        }
        h1 {
            font-size: 24px;
            text-align: center;
        }
        h2 {
            font-size: 18px;
        }
        .summary {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .monthly-summary {
            margin-bottom: 20px;
        }
        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #ddd;
            text-align: center;
            font-size: 11px;
            color: #666;
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
            }
            .no-print { 
                display: none !important; 
            }
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

    // メッセージ表示関数
    function showMessage(message, type = 'success') {
        const bgColor = type === 'success' ? '#4caf50' : '#f44336';
        
        // 既存のメッセージがあれば削除
        $('#sales-ledger-message').remove();
        
        // メッセージ要素を作成
        const messageHtml = `
            <div id="sales-ledger-message" style="
                position: fixed;
                top: 20px;
                right: 20px;
                background: ${bgColor};
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
            $('#sales-ledger-message').fadeOut(300, function() {
                $(this).remove();
            });
        }, 5000);
    }

    // ドキュメント準備完了時の処理
    $(document).ready(function() {
        // 売上台帳印刷ボタンのクリックイベント
        $(document).on('click', '#sales-ledger-pdf-btn', function(e) {
            e.preventDefault();
            
            const year = $(this).data('year');
            const $button = $(this);
            
            if (!year) {
                showMessage('年度が指定されていません。', 'error');
                return;
            }

            // ボタンを無効化してローディング表示
            $button.prop('disabled', true).html('🖨️ 生成中...');

            // Ajaxで売上台帳PDFデータを取得
            $.ajax({
                url: typeof ktp_ajax_object !== 'undefined' ? ktp_ajax_object.ajax_url : 
                     typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php',
                type: 'POST',
                data: {
                    action: 'ktp_generate_sales_ledger_pdf',
                    year: year,
                    nonce: typeof ktp_ajax_object !== 'undefined' ? ktp_ajax_object.nonce : 
                           typeof ktp_ajax_nonce !== 'undefined' ? ktp_ajax_nonce : ''
                },
                success: function(response) {
                    try {
                        const result = typeof response === 'string' ? JSON.parse(response) : response;
                        
                        if (result.success && result.data) {
                            // PDFポップアップを表示
                            showSalesLedgerPDFPopup(
                                result.data.pdf_html,
                                result.data.filename,
                                result.data.year,
                                result.data.total_records,
                                result.data.total_amount
                            );
                        } else {
                            console.error('[SALES-LEDGER-PDF] PDFデータの取得に失敗:', result);
                            showMessage('PDFデータの取得に失敗しました: ' + (result.data || 'エラー詳細不明'), 'error');
                        }
                    } catch (parseError) {
                        console.error('[SALES-LEDGER-PDF] レスポンス解析エラー:', parseError);
                        showMessage('PDFデータの解析に失敗しました。', 'error');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('[SALES-LEDGER-PDF] Ajax エラー:', { status, error, responseText: xhr.responseText });
                    showMessage('PDFデータの取得中にエラーが発生しました: ' + error, 'error');
                },
                complete: function() {
                    // ボタンを元に戻す
                    $button.prop('disabled', false).html('🖨️ 印刷');
                }
            });
        });
    });

})(jQuery);