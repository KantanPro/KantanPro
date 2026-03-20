/**
 * レポート 印刷
 * レポートのグラフを含む内容を白背景・黒文字で印刷ダイアログに表示する。
 * ダークモード環境でも印刷は常に白背景で出力される。
 *
 * @package KTPWP
 * @since 1.0.0
 */

(function ($) {
    'use strict';

    var latestPrintHtml = '';
    var forceLightAttr = 'data-ktp-force-light';

    var PRINT_BLACK = '#000000';
    var PRINT_GRID = '#dddddd';

    /**
     * 画像化の直前に、既存 Chart の文字色を黒・背景白に上書きして再描画する。
     * ライト/ダークどちらのサイトでも印刷用は白背景・黒文字にする。
     */
    function applyPrintStyleToCharts($area) {
        if (!$area || !$area.length || typeof window.Chart === 'undefined' || typeof window.Chart.getChart !== 'function') {
            return Promise.resolve();
        }

        var ChartGlobal = window.Chart;
        var prevColor = ChartGlobal.defaults && ChartGlobal.defaults.color;
        if (ChartGlobal.defaults) {
            ChartGlobal.defaults.color = PRINT_BLACK;
        }

        var canvases = $area[0].querySelectorAll('canvas');
        var i, chart, opt, scaleId;

        for (i = 0; i < canvases.length; i++) {
            chart = window.Chart.getChart(canvases[i]);
            if (!chart || !chart.options) { continue; }

            opt = chart.options;

            if (opt.plugins) {
                if (opt.plugins.title) {
                    opt.plugins.title.color = PRINT_BLACK;
                }
                if (opt.plugins.legend && opt.plugins.legend.labels) {
                    opt.plugins.legend.labels.color = PRINT_BLACK;
                }
            }

            if (opt.scales && typeof opt.scales === 'object') {
                for (scaleId in opt.scales) {
                    if (Object.prototype.hasOwnProperty.call(opt.scales, scaleId) && opt.scales[scaleId]) {
                        if (opt.scales[scaleId].ticks) {
                            opt.scales[scaleId].ticks.color = PRINT_BLACK;
                        }
                        if (opt.scales[scaleId].grid) {
                            opt.scales[scaleId].grid.color = PRINT_GRID;
                        }
                    }
                }
            }

            if (opt.layout && opt.layout.padding) {
                // そのまま
            }
            chart.options.backgroundColor = '#ffffff';
            try {
                chart.update('none');
            } catch (e) {
                chart.update();
            }
        }

        return wait(180).then(function() {
            if (ChartGlobal.defaults && prevColor !== undefined) {
                ChartGlobal.defaults.color = prevColor;
            }
        });
    }

    /* ----------------------------------------------------------------
     * グラフ canvas → PNG dataURL（白背景を合成して取得）
     * ---------------------------------------------------------------- */
    function canvasToDataUrl(canvas) {
        if (!canvas || !canvas.width || !canvas.height) {
            return '';
        }
        try {
            // 印刷用は高解像度化して文字の可読性を上げる
            var scale = 2;
            var tmp = document.createElement('canvas');
            tmp.width  = canvas.width * scale;
            tmp.height = canvas.height * scale;
            var ctx = tmp.getContext('2d');
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, tmp.width, tmp.height);
            ctx.imageSmoothingEnabled = true;
            ctx.drawImage(canvas, 0, 0, tmp.width, tmp.height);
            return tmp.toDataURL('image/png');
        } catch (e) {
            console.warn('[KTP-REPORT-PRINT] canvas export failed:', e);
            return '';
        }
    }

    /* ----------------------------------------------------------------
     * レポートエリアのクローンを作り、全要素を白背景・黒文字に統一する
     * ---------------------------------------------------------------- */
    function buildWhiteCloneHtml($area) {
        var sourceEl = $area[0];

        // canvas の画像をあらかじめ取得（クローン後は toDataURL が使えないため先に取る）
        var sourceCanvases = sourceEl.querySelectorAll('canvas');
        var chartImages = [];
        var i;
        for (i = 0; i < sourceCanvases.length; i++) {
            chartImages.push(canvasToDataUrl(sourceCanvases[i]));
        }

        // DOM クローン
        var clone = sourceEl.cloneNode(true);

        // canvas を img に差し替え
        var cloneCanvases = clone.querySelectorAll('canvas');
        for (i = 0; i < cloneCanvases.length; i++) {
            var imgEl = document.createElement('img');
            imgEl.src   = chartImages[i] || '';
            imgEl.alt   = 'Chart';
            imgEl.style.cssText = 'display:block;width:100%;max-width:100%;height:auto;margin:8px auto;border:1px solid #ddd;';
            if (chartImages[i]) {
                cloneCanvases[i].parentNode.replaceChild(imgEl, cloneCanvases[i]);
            } else {
                cloneCanvases[i].parentNode.removeChild(cloneCanvases[i]);
            }
        }

        // フォーム・非表示要素を削除
        clone.querySelectorAll('form, button, input, select, textarea, .no-print, script, style').forEach(function (el) {
            el.parentNode && el.parentNode.removeChild(el);
        });

        // 全要素に白背景・黒文字をインラインで強制上書き
        forceWhiteOnElement(clone);
        clone.querySelectorAll('*').forEach(function (el) {
            forceWhiteOnElement(el);
        });

        // 印刷時のレイアウト調整：グラフは横2列・少し小さめに
        clone.querySelectorAll('.ktp-report-charts-grid').forEach(function (grid) {
            grid.style.setProperty('display', 'grid', 'important');
            grid.style.setProperty('grid-template-columns', '1fr 1fr', 'important');
            grid.style.setProperty('gap', '12px', 'important');
            grid.style.setProperty('margin-top', '12px', 'important');
            grid.style.setProperty('margin-bottom', '12px', 'important');
        });
        clone.querySelectorAll('.ktp-report-chart-item').forEach(function (item) {
            item.style.setProperty('width', '100%', 'important');
            item.style.setProperty('max-width', '100%', 'important');
            item.style.setProperty('margin', '0', 'important');
            item.style.setProperty('padding', '12px', 'important');
            item.style.setProperty('height', 'auto', 'important');
            item.style.setProperty('min-height', '260px', 'important');
            item.style.setProperty('overflow', 'hidden', 'important');
        });

        return clone.innerHTML;
    }

    function forceWhiteOnElement(el) {
        if (!el || !el.style) { return; }
        el.style.setProperty('background',        '#ffffff',  'important');
        el.style.setProperty('background-color',  '#ffffff',  'important');
        el.style.setProperty('background-image',  'none',     'important');
        el.style.setProperty('color',             '#333333',  'important');
        el.style.setProperty('border-color',      '#dddddd',  'important');
        el.style.setProperty('box-shadow',        'none',     'important');
        el.style.setProperty('text-shadow',       'none',     'important');
    }

    function wait(ms) {
        return new Promise(function(resolve) {
            window.setTimeout(resolve, ms);
        });
    }

    function destroyChartsInArea($area) {
        if (!$area || !$area.length || typeof window.Chart === 'undefined' || typeof window.Chart.getChart !== 'function') {
            return;
        }

        $area.find('canvas').each(function() {
            var chart = window.Chart.getChart(this);
            if (chart) {
                chart.destroy();
            }
        });
    }

    function waitForChartsReady($area) {
        return new Promise(function(resolve) {
            if (!$area || !$area.length || typeof window.Chart === 'undefined' || typeof window.Chart.getChart !== 'function') {
                resolve();
                return;
            }

            var tries = 0;
            var maxTries = 35;

            function check() {
                var allReady = true;
                $area.find('canvas').each(function() {
                    if (!window.Chart.getChart(this)) {
                        allReady = false;
                    }
                });

                tries += 1;
                if (allReady || tries >= maxTries) {
                    resolve();
                    return;
                }
                window.setTimeout(check, 120);
            }

            check();
        });
    }

    function refreshChartsForTheme($area) {
        if (
            !$area ||
            !$area.length ||
            typeof window.KTPReportCharts === 'undefined' ||
            typeof window.KTPReportCharts.initializeCharts !== 'function'
        ) {
            return Promise.resolve();
        }

        destroyChartsInArea($area);
        window.KTPReportCharts.initializeCharts();

        return waitForChartsReady($area).then(function() {
            return wait(250);
        });
    }

    function prepareChartsForLightPrint($area) {
        document.body.setAttribute(forceLightAttr, '1');
        return refreshChartsForTheme($area);
    }

    function restoreChartsAfterPrint($area) {
        document.body.removeAttribute(forceLightAttr);
        return refreshChartsForTheme($area);
    }

    /* ----------------------------------------------------------------
     * 印刷用フルHTML
     * ---------------------------------------------------------------- */
    function createPrintableHTML(innerHtml, filename) {
        return '<!DOCTYPE html>'
            + '<html lang="ja"><head>'
            + '<meta charset="UTF-8">'
            + '<title>' + (filename || 'レポート') + '</title>'
            + '<style>'
            + '*{margin:0;padding:0;box-sizing:border-box;}'
            + 'html,body{background:#ffffff !important;color:#333333 !important;}'
            + 'body{font-family:"Noto Sans JP","Hiragino Kaku Gothic ProN","Yu Gothic",Meiryo,sans-serif;'
            + 'font-size:12px;line-height:1.5;padding:24px 28px;'
            + '-webkit-print-color-adjust:exact;print-color-adjust:exact;}'
            + '.page-container{max-width:210mm;margin:0 auto;background:#ffffff;padding:18px 20px 24px 20px;}'
            + 'table{width:100%;border-collapse:collapse;margin-bottom:12px;}'
            + 'th,td{border:1px solid #dddddd;padding:6px;color:#333333;background:#ffffff;}'
            + 'img{display:block;max-width:100%;height:auto;page-break-inside:avoid;break-inside:avoid;}'
            + 'h1,h2,h3,h4{font-weight:bold;margin-bottom:8px;color:#333333;}'
            + '@page{size:A4;margin:15mm;}'
            + '@media print{'
            + 'html,body{background:#ffffff !important;color:#333333 !important;}'
            + '.page-container{box-shadow:none;padding:0;}'
            + '}'
            + '</style>'
            + '</head>'
            + '<body><div class="page-container">'
            + innerHtml
            + '</div></body></html>';
    }

    /* ----------------------------------------------------------------
     * iframe で印刷ダイアログを開く
     * ---------------------------------------------------------------- */
    function printDirect(html, filename) {
        var iframe = document.createElement('iframe');
        iframe.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;visibility:hidden;';
        document.body.appendChild(iframe);

        var cleanupDone = false;
        function cleanup() {
            if (cleanupDone) { return; }
            cleanupDone = true;
            window.setTimeout(function () {
                try { document.body.removeChild(iframe); } catch (e) {}
            }, 300);
        }

        var printed = false;
        function triggerPrint() {
            if (printed) { return; }
            printed = true;
            try {
                var fw = iframe.contentWindow || iframe;
                fw.focus();
                fw.onafterprint = cleanup;
                window.setTimeout(function () {
                    try { fw.print(); } catch (e) { cleanup(); }
                }, 80);
            } catch (e) { cleanup(); }
        }

        try {
            var fd = iframe.contentDocument || iframe.contentWindow.document;
            iframe.onload = function () {
                try {
                    var d = iframe.contentDocument || iframe.contentWindow.document;
                    if (d) { d.title = filename + '.pdf'; }
                } catch (e) {}
                triggerPrint();
            };
            fd.open();
            fd.write(html);
            fd.close();
        } catch (e) {
            console.error('[KTP-REPORT-PRINT] iframe print failed:', e);
            cleanup();
        }
        window.setTimeout(cleanup, 12000);
    }

    /* ----------------------------------------------------------------
     * ポップアップUI
     * ---------------------------------------------------------------- */
    function getReportArea() {
        var $area = $('.ktp-report-print-area').first();
        if (!$area.length) { $area = $('#report_content').first(); }
        return $area;
    }

    /**
     * ポップアップを閉じる。
     * @param {boolean} [skipReload=false] true のときはリロードしない（既存ポップアップのクリーンアップ用）
     */
    function closePopup(skipReload) {
        $('#ktp-report-print-popup').remove();
        $(document).off('.ktp-report-print');
        if (!skipReload) {
            // 印刷用に黒くしていたグラフを元のテーマに戻してからリロード（戻さないとリロードまで黒いまま）
            var $area = getReportArea();
            var p = $area.length ? restoreChartsAfterPrint($area) : Promise.resolve();
            p.then(function() {
                window.location.reload();
            }, function() {
                window.location.reload();
            });
        }
    }

    function buildPopupHtml(innerHtml) {
        return ''
            + '<div id="ktp-report-print-popup" style="'
            + 'position:fixed;top:0;left:0;width:100%;height:100%;'
            + 'background:rgba(0,0,0,0.5);z-index:10000;'
            + 'display:flex;justify-content:center;align-items:center;">'
            + '<div style="background:#fff;border-radius:8px;padding:15px;'
            + 'width:95%;max-width:1000px;max-height:85%;overflow-y:auto;'
            + 'box-shadow:0 4px 20px rgba(0,0,0,0.3);">'
            + '<div style="display:flex;justify-content:flex-end;align-items:center;'
            + 'margin-bottom:15px;border-bottom:1px solid #eee;padding-bottom:10px;">'
            + '<button type="button" id="ktp-report-print-close" style="'
            + 'background:none;color:#333;border:none;cursor:pointer;font-size:28px;padding:0;line-height:1;">×</button>'
            + '</div>'
            + '<div id="ktp-report-print-content" style="'
            + 'margin-bottom:20px;padding:18px;border:1px solid #ddd;border-radius:4px;'
            + 'background:#fff;min-height:300px;'
            + 'font-family:\'Noto Sans JP\',\'Hiragino Kaku Gothic ProN\',Meiryo,sans-serif;'
            + 'line-height:1.6;color:#333;overflow:auto;">'
            + innerHtml
            + '</div>'
            + '<div style="display:flex;justify-content:center;gap:10px;'
            + 'border-top:1px solid #eee;padding-top:15px;">'
            + '<button type="button" id="ktp-report-print-do" style="'
            + 'background:#1976d2;color:#fff;border:none;padding:12px 24px;'
            + 'border-radius:4px;cursor:pointer;font-size:16px;'
            + 'display:flex;align-items:center;gap:8px;">🖨️ 印刷</button>'
            + '</div>'
            + '</div>'
            + '</div>';
    }

    function bindEvents(filename) {
        $(document).off('.ktp-report-print');

        $(document).on('click.ktp-report-print', '#ktp-report-print-close', function () { closePopup(); });

        $(document).on('keyup.ktp-report-print', function (e) {
            if (e.keyCode === 27) { closePopup(); }
        });

        $(document).on('click.ktp-report-print', '#ktp-report-print-popup', function (e) {
            if (e.target === this) { closePopup(); }
        });

        $(document).on('click.ktp-report-print', '#ktp-report-print-do', function () {
            if (!latestPrintHtml) { alert('プレビューが準備中です。しばらく待ってから押してください。'); return; }
            printDirect(latestPrintHtml, filename);
        });
    }

    /* ----------------------------------------------------------------
     * エントリーポイント（プリントボタン押下）
     * ---------------------------------------------------------------- */
    function showReportPrintPopup() {
        var $area = getReportArea();
        if (!$area.length) {
            alert('印刷する内容が見つかりません。');
            return;
        }

        var filename = 'レポート_' + (new Date().toISOString().slice(0, 10));
        latestPrintHtml = '';

        closePopup(true);
        $('body').append(buildPopupHtml('<div style="padding:40px 20px;color:#666;text-align:center;">印刷データを準備中です...</div>'));
        bindEvents(filename);

        // 印刷時: グラフをライトで再描画 → 画像化直前に全Chartの文字色を黒・背景白に上書き → クローン作成
        prepareChartsForLightPrint($area).then(function() {
            return applyPrintStyleToCharts($area);
        }).then(function() {
            var cloneHtml = buildWhiteCloneHtml($area);
            latestPrintHtml = createPrintableHTML(cloneHtml, filename);

            // ポップアップのプレビューエリアも白背景で表示
            $('#ktp-report-print-content').html(
                '<div style="background:#fff;padding:16px;border:1px solid #eee;border-radius:4px;'
                + 'color:#333;font-size:12px;line-height:1.5;">' + cloneHtml + '</div>'
            );
        }).catch(function(err) {
            console.error('[KTP-REPORT-PRINT] build failed:', err);
            latestPrintHtml = '';
            $('#ktp-report-print-content').html(
                '<div style="padding:40px 20px;color:#d32f2f;text-align:center;">印刷データの作成に失敗しました。</div>'
            );
        });
        // ポップアップ表示中は restoreChartsAfterPrint を呼ばない（グラフ破棄・再初期化で
        // イベントが発火し、勝手に閉じる原因になるため）。ユーザーが閉じたときにリロードする。
    }

    window.ktpReportPrintOpen = showReportPrintPopup;

})(jQuery);
