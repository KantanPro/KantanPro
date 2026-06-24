/**
 * 仕事リスト 印刷・PDF保存
 * KantanBiz work-list-print.js と同様、ボタンの data 属性から印刷メタを組み立てる。
 *
 * @package KTPWP
 * @since 1.0.0
 */

(function ($) {
    'use strict';

    function sanitizeFilename(value) {
        return String(value)
            .replace(/[\u0000-\u001F\/\\:\uFF1A*\?"<>\|]/g, '-')
            .replace(/\s+/g, ' ')
            .trim();
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function getPrintOfficeName(btn) {
        var footer = '';
        if (btn && btn.dataset && btn.dataset.printFooterName) {
            footer = String(btn.dataset.printFooterName).trim();
        }
        if (!footer) {
            try {
                footer = ($('#ktp_list_print_area').find('#ktp_list_my_company_name').text() || '').trim();
            } catch (e) {}
        }
        if (!footer) {
            footer = '（自社名未設定）';
        }
        return footer;
    }

    function buildListPrintMeta(btn, area) {
        var printDate = new Date();
        var yyyy = printDate.getFullYear();
        var mm = String(printDate.getMonth() + 1).padStart(2, '0');
        var dd = String(printDate.getDate()).padStart(2, '0');
        var ymd = yyyy + mm + dd;
        var printDateYmdDisplay = yyyy + '-' + mm + '-' + dd;

        var progressParam = 1;
        var statusLabelMap = {};
        var defaultStatusLabel = typeof ktpwpTranslate === 'function'
            ? ktpwpTranslate('進捗タブ')
            : '進捗タブ';

        try {
            var sp = new URLSearchParams(window.location.search);
            var p = sp.get('progress');
            progressParam = p ? parseInt(p, 10) : 1;
        } catch (e) {}

        try {
            statusLabelMap = JSON.parse((btn && btn.dataset ? btn.dataset.statusLabelMap : '') || '{}');
        } catch (e) {
            statusLabelMap = {};
        }

        if (btn && btn.dataset && btn.dataset.defaultStatusLabel) {
            defaultStatusLabel = btn.dataset.defaultStatusLabel.trim() || defaultStatusLabel;
        }

        if (btn && btn.dataset && btn.dataset.selectedProgress) {
            var selected = parseInt(btn.dataset.selectedProgress, 10);
            if (!isNaN(selected)) {
                progressParam = selected;
            }
        }

        var progressLabels = {
            1: '受付中',
            2: '見積中',
            3: '受注',
            4: '完了',
            5: '請求済',
            6: '入金済'
        };

        var statusLabel = (btn && btn.dataset && btn.dataset.printTitle)
            ? btn.dataset.printTitle.trim()
            : '';
        if (!statusLabel && btn && btn.dataset && btn.dataset.selectedStatusLabel) {
            statusLabel = btn.dataset.selectedStatusLabel.trim();
        }
        if (!statusLabel) {
            statusLabel = statusLabelMap[String(progressParam)] || progressLabels[progressParam] || defaultStatusLabel;
        }

        var filename = sanitizeFilename(statusLabel) + '_' + ymd;
        var headerFormat = (btn && btn.dataset && btn.dataset.printHeaderFormat)
            ? btn.dataset.printHeaderFormat.trim()
            : ':statusの作業リスト';
        var listTitle = (btn && btn.dataset && btn.dataset.printListTitle)
            ? btn.dataset.printListTitle.trim()
            : '作業リスト';
        var headerTitle = statusLabel
            ? headerFormat.replace(':status', statusLabel)
            : listTitle;
        var headerText = escapeHtml(headerTitle + ' ' + printDateYmdDisplay);
        var footerText = escapeHtml(getPrintOfficeName(btn));

        return {
            progressParam: progressParam,
            filename: filename,
            headerText: headerText,
            footerText: footerText,
            areaHtmlFallback: area ? area.innerHTML : ''
        };
    }

    /**
     * 仕事リストを直接印刷ダイアログで開く（プレビューUIは表示しない）
     */
    function showListPrintPopup(triggerBtn) {
        var $area = $('#ktp_list_print_area');
        if (!$area.length) {
            alert(ktpwpTranslate('印刷する内容が見つかりません。'));
            return;
        }

        var btn = triggerBtn instanceof HTMLElement
            ? triggerBtn
            : document.getElementById('js-work-list-print-btn');
        var meta = buildListPrintMeta(btn, $area.get(0));

        (function loadFullListForPrint() {
            var iframe = document.createElement('iframe');
            iframe.style.cssText = 'position:fixed;right:-9999px;bottom:-9999px;width:0;height:0;border:0;visibility:hidden;';
            document.body.appendChild(iframe);

            var url = null;
            try {
                url = new URL(window.location.href);
            } catch (e) {
                printListDirect($area.html(), meta.filename, meta.headerText, meta.footerText);
                try { document.body.removeChild(iframe); } catch (_) {}
                return;
            }

            url.searchParams.set('print_all', '1');
            url.searchParams.set('progress', String(meta.progressParam));
            url.searchParams.set('page_start', '0');
            url.searchParams.delete('page_stage');
            url.searchParams.delete('list_search');

            iframe.onload = function() {
                try {
                    var doc = iframe.contentDocument || iframe.contentWindow.document;
                    var listBox = doc.querySelector('#ktp_list_print_area .ktp_work_list_box');
                    if (listBox) {
                        printListDirect(listBox.outerHTML, meta.filename, meta.headerText, meta.footerText);
                    } else {
                        var areaHtml = doc.querySelector('#ktp_list_print_area');
                        printListDirect(areaHtml ? areaHtml.innerHTML : meta.areaHtmlFallback, meta.filename, meta.headerText, meta.footerText);
                    }
                } catch (e) {
                    console.error('[KTP-LIST-PRINT] 全件取得失敗:', e);
                    alert(ktpwpTranslate('印刷データの取得に失敗しました。'));
                } finally {
                    try { document.body.removeChild(iframe); } catch (_) {}
                }
            };

            iframe.src = url.toString();
        })();
    }

    /**
     * 印刷ダイアログを開く（PDF保存もブラウザの印刷から「PDFに保存」で可能）
     */
    function printListDirect(content, filename, headerText, footerText) {
        var printHTML = createListPrintableHTML(content, filename, headerText, footerText);

        var iframe = document.createElement('iframe');
        iframe.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;visibility:hidden;';
        document.body.appendChild(iframe);

        var originalDocumentTitle = document.title;
        var cleanupDone = false;
        function cleanup() {
            if (cleanupDone) return;
            cleanupDone = true;
            setTimeout(function () {
                try {
                    document.body.removeChild(iframe);
                } catch (e) {}
                try { document.title = originalDocumentTitle; } catch (e) {}
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
                try { document.title = filename + '.pdf'; } catch (e) {}
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
                        if (d.head) {
                            var titleEl = d.head.querySelector('title');
                            if (titleEl) {
                                titleEl.textContent = d.title;
                            } else {
                                var t = d.createElement('title');
                                t.textContent = d.title;
                                d.head.appendChild(t);
                            }
                        }
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
    function createListPrintableHTML(content, filename, headerText, footerText) {
        return '<!DOCTYPE html><html lang="ja"><head><meta charset="UTF-8">'
            + '<meta name="viewport" content="width=device-width,initial-scale=1.0">'
            + '<title>' + (filename || '仕事リスト') + '</title>'
            + '<style>'
            + '*{margin:0;padding:0;box-sizing:border-box;}'
            + 'body{font-family:"Noto Sans JP","Hiragino Kaku Gothic ProN","Yu Gothic",Meiryo,sans-serif;font-size:12px;line-height:1.5;color:#333;background:#fff;padding:20px;-webkit-print-color-adjust:exact;print-color-adjust:exact;}'
            + '.print-header{position:relative;display:flex;align-items:center;justify-content:center;'
            + 'min-height:12mm;margin:0 0 4mm 0;padding:1mm 0;border-bottom:1px solid #ddd;background:#fff;'
            + 'font-size:24px;font-weight:700;z-index:1;page-break-after:avoid;}'
            + '.print-footer{position:fixed;bottom:0;left:0;right:0;height:10mm;display:flex;align-items:center;justify-content:center;border-top:1px solid #ddd;background:#fff;font-size:11px;z-index:9999;pointer-events:none;margin:0;padding:0;}'
            + '.page-container{max-width:210mm;margin:0 auto;background:#fff;padding:20px;}'
            + '.workflow,.progress-filter{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px;}'
            + '.ktp_work_list_box ul{list-style:none;padding-left:0;}'
            + '.ktp_work_list_box li{border:none;padding:10px 0;margin:0 0 12px 0;border-radius:0;'
            + 'break-inside:avoid !important;break-inside:avoid-page !important;page-break-inside:avoid !important;-webkit-column-break-inside:avoid !important;}'
            + '.ktp_work_list_box li.ktp_work_list_item{position:relative;padding-left:70px;display:block;width:100%;overflow:visible;}'
            + '.ktp_work_list_box li.ktp_work_list_item::before{content:"☐";position:absolute;left:0;top:2px;font-size:44px;line-height:1;color:#333;}'
            + '.ktp_work_list_box li.ktp_work_list_item{border-bottom:1px solid #ddd;}'
            + '.ktp_work_list_item_text,.delivery-dates-container{'
            + 'break-inside:avoid;page-break-inside:avoid;-webkit-column-break-inside:avoid;}'
            + '.delivery-dates-container{display:flex;flex-wrap:wrap;align-items:center;gap:8px;margin-top:6px;}'
            + '.delivery-input-wrapper,.completion-input-wrapper{'
            + 'break-inside:avoid;page-break-inside:avoid;-webkit-column-break-inside:avoid;}'
            + '.ktp_work_list_box a{color:#1976d2;}'
            + '.delivery-warning-mark-row,.invoice-warning-mark-row,.payment-warning-mark-row{'
            + 'display:inline-flex;align-items:center;justify-content:center;'
            + 'min-width:16px;height:16px;margin-left:6px;padding:0 4px;'
            + 'border-radius:999px;background:#d32f2f;color:#fff;font-size:11px;font-weight:700;line-height:1;}'
            + '.ktp-progress-warning-badge{'
            + 'display:inline-flex;align-items:center;justify-content:center;'
            + 'min-width:18px;height:18px;margin-left:6px;padding:0 6px;'
            + 'border-radius:999px;background:#d32f2f;color:#fff;font-size:11px;font-weight:700;line-height:1;}'
            + 'form{display:none;}'
            + 'select{display:none;}'
            + '.delivery-date-input,.completion-date-input{'
            + 'display:inline-block !important;border:none !important;background:transparent !important;'
            + '-webkit-appearance:none;-moz-appearance:none;appearance:none;'
            + 'padding:0;margin:0;min-width:0;font:inherit;color:inherit;}'
            + '.delivery-date-input::-webkit-calendar-picker-indicator,.completion-date-input::-webkit-calendar-picker-indicator{opacity:0;pointer-events:none;width:0;height:0;}'
            + '.ktp-list-search-results{margin-bottom:16px;padding:14px;background:#f9f9f9;border:1px solid #eee;border-radius:6px;}'
            + '@page{size:A4;margin:14mm 10mm 10mm 10mm;}'
            + '@media print{'
            + 'body{margin:0;padding:0;}.page-container{box-shadow:none;padding:0;max-width:none;}'
            + '.print-header{position:static !important;}'
            + '.print-header,.print-footer{display:flex !important;}'
            + '.delivery-date-input,.completion-date-input{display:inline-block !important;border:none !important;background:transparent !important;}'
            + '.delivery-date-input::-webkit-calendar-picker-indicator,.completion-date-input::-webkit-calendar-picker-indicator{opacity:0 !important;visibility:hidden !important;}'
            + '}'
            + '</style></head><body>'
            + '<div class="print-header">' + (headerText || '') + '</div>'
            + '<div class="print-footer">' + (footerText || '') + '</div>'
            + '<div class="page-container">'
            + content
            + '</div></body></html>';
    }

    $(document).on('click', '#js-work-list-print-btn', function (event) {
        var btn = event.currentTarget;
        if (!btn || btn.disabled || btn.dataset.ktpPrintBusy === '1') {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        btn.dataset.ktpPrintBusy = '1';

        try {
            showListPrintPopup(btn);
        } finally {
            window.setTimeout(function () {
                delete btn.dataset.ktpPrintBusy;
            }, 1500);
        }
    });

    window.ktpListPrintOpen = function (triggerBtn) {
        showListPrintPopup(triggerBtn || document.getElementById('js-work-list-print-btn'));
    };

})(jQuery);
