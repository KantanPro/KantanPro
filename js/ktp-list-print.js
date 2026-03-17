/**
 * 仕事リスト 印刷・PDF保存
 * 現在表示されている内容をポップアップで表示し、PDF保存・印刷ボタンを提供する。
 *
 * @package KTPWP
 * @since 1.0.0
 */

(function ($) {
    'use strict';

    /**
     * 仕事リスト印刷用ポップアップを表示（レポート・売上台帳と同様のPDF・印刷ボタン付き）
     */
    function showListPrintPopup() {
        var $area = $('#ktp_list_print_area');
        if (!$area.length) {
            alert('印刷する内容が見つかりません。');
            return;
        }
        var contentHtml = $area.html();
        if (!contentHtml || contentHtml.trim() === '') {
            alert('印刷する内容がありません。');
            return;
        }

        var filename = '仕事リスト_' + (new Date().toISOString().slice(0, 10));

        var popupHtml = ''
            + '<div id="ktp-list-print-popup" style="'
            + 'position:fixed;top:0;left:0;width:100%;height:100%;'
            + 'background:rgba(0,0,0,0.5);z-index:10000;'
            + 'display:flex;justify-content:center;align-items:center;">'
            + '<div style="'
            + 'background:white;border-radius:8px;padding:15px;'
            + 'width:95%;max-width:900px;max-height:85%;overflow-y:auto;'
            + 'box-shadow:0 4px 20px rgba(0,0,0,0.3);">'
            + '<div style="'
            + 'display:flex;justify-content:flex-end;align-items:center;'
            + 'margin-bottom:15px;border-bottom:1px solid #eee;padding-bottom:10px;">'
            + '<button type="button" id="ktp-list-print-close" style="'
            + 'background:none;color:#333;border:none;cursor:pointer;'
            + 'font-size:28px;padding:0;line-height:1;">×</button>'
            + '</div>'
            + '<div id="ktp-list-print-content" style="'
            + 'margin-bottom:20px;padding:20px;border:1px solid #ddd;border-radius:4px;'
            + 'background:#fff;min-height:300px;'
            + 'font-family:\'Noto Sans JP\',\'Hiragino Kaku Gothic ProN\',Meiryo,sans-serif;'
            + 'line-height:1.6;color:#333;">'
            + contentHtml
            + '</div>'
            + '<div style="'
            + 'display:flex;justify-content:center;gap:10px;'
            + 'border-top:1px solid #eee;padding-top:15px;">'
            + '<button type="button" id="ktp-list-print-save" style="'
            + 'background:#e53935;color:white;border:none;'
            + 'padding:12px 24px;border-radius:4px;cursor:pointer;font-size:16px;'
            + 'display:flex;align-items:center;gap:8px;">📄 PDF保存</button>'
            + '<button type="button" id="ktp-list-print-do" style="'
            + 'background:#1976d2;color:white;border:none;'
            + 'padding:12px 24px;border-radius:4px;cursor:pointer;font-size:16px;'
            + 'display:flex;align-items:center;gap:8px;">🖨️ 印刷</button>'
            + '</div>'
            + '</div>'
            + '</div>';

        $('body').append(popupHtml);

        function closeListPrintPopup() {
            $('#ktp-list-print-popup').remove();
            $(document).off('keyup.ktp-list-print');
        }

        $(document).on('click', '#ktp-list-print-close', function () {
            closeListPrintPopup();
        });

        $(document).on('keyup.ktp-list-print', function (e) {
            if (e.keyCode === 27) {
                closeListPrintPopup();
            }
        });

        $(document).on('click', '#ktp-list-print-popup', function (e) {
            if (e.target === this) {
                closeListPrintPopup();
            }
        });

        $(document).on('click', '#ktp-list-print-save', function () {
            var html = $('#ktp-list-print-content').html();
            printListDirect(html, filename);
        });

        $(document).on('click', '#ktp-list-print-do', function () {
            var html = $('#ktp-list-print-content').html();
            printListDirect(html, filename);
        });
    }

    /**
     * 印刷ダイアログを開く（PDF保存もブラウザの印刷から「PDFに保存」で可能）
     */
    function printListDirect(content, filename) {
        var printHTML = createListPrintableHTML(content, filename);

        var iframe = document.createElement('iframe');
        iframe.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;visibility:hidden;';
        document.body.appendChild(iframe);

        var cleanupDone = false;
        function cleanup() {
            if (cleanupDone) return;
            cleanupDone = true;
            setTimeout(function () {
                try {
                    document.body.removeChild(iframe);
                } catch (e) {}
            }, 300);
        }

        var printed = false;
        function triggerPrint() {
            if (printed) return;
            printed = true;
            try {
                var frameWin = iframe.contentWindow || iframe;
                frameWin.focus();
                frameWin.onafterprint = cleanup;
                setTimeout(function () {
                    try {
                        frameWin.print();
                    } catch (e) {
                        cleanup();
                    }
                }, 50);
            } catch (e) {
                cleanup();
            }
        }

        try {
            var frameDoc = iframe.contentDocument || iframe.contentWindow.document;
            iframe.onload = function () {
                try {
                    var d = iframe.contentDocument || iframe.contentWindow.document;
                    if (d && d.title !== undefined) {
                        d.title = filename + '.pdf';
                    }
                } catch (e) {}
                triggerPrint();
            };
            frameDoc.open();
            frameDoc.write(printHTML);
            frameDoc.close();
        } catch (e) {
            console.error('[KTP-LIST-PRINT] iframe印刷処理に失敗:', e);
            cleanup();
        }
        setTimeout(cleanup, 10000);
    }

    /**
     * 印刷用HTMLを生成（スタイル付き）
     */
    function createListPrintableHTML(content, filename) {
        return '<!DOCTYPE html><html lang="ja"><head><meta charset="UTF-8">'
            + '<meta name="viewport" content="width=device-width,initial-scale=1.0">'
            + '<title>' + (filename || '仕事リスト') + '</title>'
            + '<style>'
            + '*{margin:0;padding:0;box-sizing:border-box;}'
            + 'body{font-family:"Noto Sans JP","Hiragino Kaku Gothic ProN","Yu Gothic",Meiryo,sans-serif;font-size:12px;line-height:1.5;color:#333;background:#fff;padding:20px;-webkit-print-color-adjust:exact;print-color-adjust:exact;}'
            + '.page-container{max-width:210mm;margin:0 auto;background:#fff;padding:20px;}'
            + '.workflow,.progress-filter{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px;}'
            + '.ktp_work_list_box ul{list-style:none;padding-left:0;}'
            + '.ktp_work_list_box li{border:1px solid #eee;padding:10px 12px;margin-bottom:8px;border-radius:4px;}'
            + '.ktp_work_list_box a{color:#1976d2;}'
            + '.delivery-dates-container{display:flex;flex-wrap:wrap;align-items:center;gap:8px;margin-top:6px;}'
            + 'form{display:none;}'
            + 'select,.delivery-date-input,.completion-date-input{display:none;}'
            + '.ktp-list-search-results{margin-bottom:16px;padding:14px;background:#f9f9f9;border:1px solid #eee;border-radius:6px;}'
            + '@page{size:A4;margin:15mm;}'
            + '@media print{body{margin:0;padding:0;}.page-container{box-shadow:none;padding:0;}}'
            + '</style></head><body><div class="page-container">'
            + content
            + '</div></body></html>';
    }

    window.ktpListPrintOpen = showListPrintPopup;

})(jQuery);
