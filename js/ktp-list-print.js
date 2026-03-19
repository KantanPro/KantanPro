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
        // 初期表示は仮（全件取得後に差し替え）
        var contentHtml = '<div style="padding:40px 20px;color:#666;text-align:center;">全件の印刷データを準備中です...</div>';

        function sanitizeFilename(value) {
            // Print to PDF の提案名に禁止文字が含まれるとフォールバック名になることがあるためサニタイズする
            return String(value)
                .replace(/[\u0000-\u001F\/\\:\uFF1A*\?"<>\|]/g, '-')
                .replace(/\s+/g, ' ')
                .trim();
        }

        // 選択中の進捗(progress)から進捗名を決定（URLパラメータを優先）
        var progressParam = 1;
        try {
            var sp = new URLSearchParams(window.location.search);
            var p = sp.get('progress');
            progressParam = p ? parseInt(p, 10) : 1;
        } catch (e) {}

        var progressLabels = {
            1: '受付中',
            2: '見積中',
            3: '受注',
            4: '完了',
            5: '請求済',
            6: '入金済'
        };

        var progressName = progressLabels[progressParam] || '進捗';

        // 印刷日（YYYYMMDD）
        var printDate = new Date();
        var yyyy = printDate.getFullYear();
        var mm = String(printDate.getMonth() + 1).padStart(2, '0');
        var dd = String(printDate.getDate()).padStart(2, '0');
        var ymd = yyyy + mm + dd;
        var printDateYmdDisplay = yyyy + '-' + mm + '-' + dd;

        // filename は「拡張子なし」。d.title / document.title 側で .pdf を付与する
        var filename = sanitizeFilename(progressName) + '_' + ymd;
        function escapeHtml(value) {
            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        // ヘッダー表示用：ファイル名用のサニタイズはせず、表示文字「：」を維持する
        var headerText = escapeHtml(progressName + '：' + printDateYmdDisplay);

        // 自社名（サーバで埋め込んだ隠し要素から取得）
        var footerText = '';
        try {
            footerText = ($area.find('#ktp_list_my_company_name').text() || '').trim();
        } catch (e) {}
        if (!footerText) {
            footerText = '（自社名未設定）';
        }

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
            + '<button type="button" id="ktp-list-print-do" style="'
            + 'background:#1976d2;color:white;border:none;'
            + 'padding:12px 24px;border-radius:4px;cursor:pointer;font-size:16px;'
            + 'display:flex;align-items:center;gap:8px;">🖨️ 印刷</button>'
            + '</div>'
            + '</div>'
            + '</div>';

        $('body').append(popupHtml);

        var listReady = false;

        function closeListPrintPopup() {
            $('#ktp-list-print-popup').remove();
            $(document).off('keyup.ktp-list-print');
            $(document).off('click.ktp-list-print', '#ktp-list-print-close');
            $(document).off('click.ktp-list-print', '#ktp-list-print-popup');
            $(document).off('click.ktp-list-print', '#ktp-list-print-do');
        }

        // 既存ハンドラが積み上がると、1回のクリックで print が複数回発火するため先に解除する
        $(document).off('click.ktp-list-print', '#ktp-list-print-close');
        $(document).off('click.ktp-list-print', '#ktp-list-print-popup');
        $(document).off('click.ktp-list-print', '#ktp-list-print-do');

        $(document).on('click.ktp-list-print', '#ktp-list-print-close', function () {
            closeListPrintPopup();
        });

        $(document).on('keyup.ktp-list-print', function (e) {
            if (e.keyCode === 27) {
                closeListPrintPopup();
            }
        });

        $(document).on('click.ktp-list-print', '#ktp-list-print-popup', function (e) {
            if (e.target === this) {
                closeListPrintPopup();
            }
        });

        $(document).on('click.ktp-list-print', '#ktp-list-print-do', function () {
            if (!listReady) {
                alert('印刷データ（全件）を準備中です。少しお待ちください。');
                return;
            }
            var html = $('#ktp-list-print-content').html();
            printListDirect(html, filename, headerText, footerText);
        });

        // ページネーション無視：進捗指定で print_all=1 の一覧HTMLを取りに行く
        (function loadFullListForPrint() {
            var iframe = document.createElement('iframe');
            iframe.style.cssText = 'position:fixed;right:-9999px;bottom:-9999px;width:0;height:0;border:0;visibility:hidden;';
            document.body.appendChild(iframe);

            var url = null;
            try {
                url = new URL(window.location.href);
            } catch (e) {
                // URL APIが使えない環境はフォールバック（この場合は現状HTMLのまま）
                $('#ktp-list-print-content').html($area.html());
                listReady = true;
                try { document.body.removeChild(iframe); } catch (_) {}
                return;
            }

            url.searchParams.set('print_all', '1');
            url.searchParams.set('progress', String(progressParam));
            url.searchParams.set('page_start', '0');
            url.searchParams.delete('page_stage');

            iframe.onload = function() {
                try {
                    var doc = iframe.contentDocument || iframe.contentWindow.document;
                    var listBox = doc.querySelector('#ktp_list_print_area .ktp_work_list_box');
                    if (listBox) {
                        $('#ktp-list-print-content').html(listBox.outerHTML);
                    } else {
                        var areaHtml = doc.querySelector('#ktp_list_print_area');
                        $('#ktp-list-print-content').html(areaHtml ? areaHtml.innerHTML : $('#ktp-list-print-content').html());
                    }
                    listReady = true;
                } catch (e) {
                    console.error('[KTP-LIST-PRINT] 全件取得失敗:', e);
                    $('#ktp-list-print-content').html('<div style="padding:40px 20px;color:#d32f2f;text-align:center;">全件の取得に失敗しました。</div>');
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
                // 環境によっては親 document.title が提案名に使われるため、直前に合わせる
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
                        // <title> 要素も更新（提案名が title 要素を読む場合に備える）
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
    // header/footer は fixed で常に各ページに表示する
    function createListPrintableHTML(content, filename, headerText, footerText) {
        return '<!DOCTYPE html><html lang="ja"><head><meta charset="UTF-8">'
            + '<meta name="viewport" content="width=device-width,initial-scale=1.0">'
            + '<title>' + (filename || '仕事リスト') + '</title>'
            + '<style>'
            + '*{margin:0;padding:0;box-sizing:border-box;}'
            + 'body{font-family:"Noto Sans JP","Hiragino Kaku Gothic ProN","Yu Gothic",Meiryo,sans-serif;font-size:12px;line-height:1.5;color:#333;background:#fff;padding:20px;-webkit-print-color-adjust:exact;print-color-adjust:exact;}'
            + '.print-header{position:fixed;top:40px;left:0;right:0;height:44px;display:flex;align-items:center;justify-content:center;border-bottom:1px solid #ddd;background:#fff;font-size:12px;font-weight:700;z-index:9999;}'
            + '.print-footer{position:fixed;bottom:40px;left:0;right:0;height:40px;display:flex;align-items:center;justify-content:center;border-top:1px solid #ddd;background:#fff;font-size:11px;z-index:9999;}'
            + '.page-container{max-width:210mm;margin:0 auto;background:#fff;padding:20px;}'
            + '.workflow,.progress-filter{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px;}'
            + '.ktp_work_list_box ul{list-style:none;padding-left:0;}'
            + '.ktp_work_list_box li{border:none;padding:10px 0;margin:0 0 12px 0;border-radius:0;}'
            + '.ktp_work_list_box li.ktp_work_list_item{position:relative;padding-left:70px;}'
            + '.ktp_work_list_box li.ktp_work_list_item::before{content:"☐";position:absolute;left:0;top:2px;font-size:44px;line-height:1;color:#333;}'
            + '.ktp_work_list_box li.ktp_work_list_item{border-bottom:1px solid #ddd;}'
            + '.ktp_work_list_box a{color:#1976d2;}'
            + '.delivery-dates-container{display:flex;flex-wrap:wrap;align-items:center;gap:8px;margin-top:6px;}'
            + 'form{display:none;}'
            + 'select{display:none;}'
            + '.delivery-date-input,.completion-date-input{'
            + 'display:inline-block !important;border:none !important;background:transparent !important;'
            + '-webkit-appearance:none;-moz-appearance:none;appearance:none;'
            + 'padding:0;margin:0;min-width:0;font:inherit;color:inherit;}'
            + '.delivery-date-input::-webkit-calendar-picker-indicator,.completion-date-input::-webkit-calendar-picker-indicator{opacity:0;pointer-events:none;width:0;height:0;}'
            + '.ktp-list-search-results{margin-bottom:16px;padding:14px;background:#f9f9f9;border:1px solid #eee;border-radius:6px;}'
            + '@page{size:A4;margin:0;}'
            + '@media print{'
            + 'body{margin:0;padding:115px 40px 340px 40px;}.page-container{box-shadow:none;padding:0;}'
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

    window.ktpListPrintOpen = showListPrintPopup;

})(jQuery);
