/**
 * Sales Ledger Print
 *
 * @package KTPWP
 * @since 1.0.0
 */

(function ($) {
    'use strict';

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
            $button.prop('disabled', true).html(ktpwpTranslate('🖨️ 生成中...'));

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
                            // 取得したHTMLをそのまま直接印刷
                            printSalesLedgerDirect(result.data.pdf_html, result.data.filename);
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
                    $button.prop('disabled', false).html(ktpwpTranslate('🖨️ 印刷'));
                }
            });
        });
    });

})(jQuery);