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
    var forceLightAttr = 'data-ktp-force-light';

    var PRINT_BLACK = '#000000';
    var PRINT_GRID = '#dddddd';

    /**
     * 画像化の直前に、既存 Chart の文字色を黒・背景白に上書きして再描画する。
     * ライト/ダークどちらのサイトでも印刷用は白背景・黒文字にする。
     */
    function applyPrintStyleToCharts($area) {
        if (!$area || !$area.length || typeof window.Chart === 'undefined' || typeof window.Chart.getChart !== 'function') {
            return Promise.resolve(null);
        }

        var ChartGlobal = window.Chart;
        var prevColor = ChartGlobal.defaults && ChartGlobal.defaults.color;
        if (ChartGlobal.defaults) {
            ChartGlobal.defaults.color = PRINT_BLACK;
        }

        var canvases = $area[0].querySelectorAll('canvas');
        var i, chart, opt, scaleId;
        var chartSnapshots = [];

        for (i = 0; i < canvases.length; i++) {
            chart = window.Chart.getChart(canvases[i]);
            if (!chart || !chart.options) { continue; }

            opt = chart.options;
            var snapshot = {
                chart: chart,
                titleColor: null,
                legendLabelColor: null,
                scaleColors: {},
                backgroundColor: chart.options.backgroundColor
            };

            if (opt.plugins && opt.plugins.title) {
                snapshot.titleColor = opt.plugins.title.color;
            }
            if (opt.plugins && opt.plugins.legend && opt.plugins.legend.labels) {
                snapshot.legendLabelColor = opt.plugins.legend.labels.color;
            }
            if (opt.scales && typeof opt.scales === 'object') {
                for (scaleId in opt.scales) {
                    if (Object.prototype.hasOwnProperty.call(opt.scales, scaleId) && opt.scales[scaleId]) {
                        snapshot.scaleColors[scaleId] = {
                            ticks: opt.scales[scaleId].ticks ? opt.scales[scaleId].ticks.color : undefined,
                            grid: opt.scales[scaleId].grid ? opt.scales[scaleId].grid.color : undefined
                        };
                    }
                }
            }
            chartSnapshots.push(snapshot);

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
            return {
                prevColor: prevColor,
                chartSnapshots: chartSnapshots
            };
        });
    }

    function restoreChartStylesAfterPrint(printState) {
        if (!printState) { return; }

        var ChartGlobal = window.Chart;
        if (ChartGlobal && ChartGlobal.defaults && printState.prevColor !== undefined) {
            ChartGlobal.defaults.color = printState.prevColor;
        }

        (printState.chartSnapshots || []).forEach(function(snapshot) {
            if (!snapshot || !snapshot.chart || !snapshot.chart.options) { return; }
            var options = snapshot.chart.options;
            var sid;

            if (options.plugins && options.plugins.title) {
                options.plugins.title.color = snapshot.titleColor;
            }
            if (options.plugins && options.plugins.legend && options.plugins.legend.labels) {
                options.plugins.legend.labels.color = snapshot.legendLabelColor;
            }
            if (options.scales && typeof options.scales === 'object') {
                for (sid in snapshot.scaleColors) {
                    if (!Object.prototype.hasOwnProperty.call(snapshot.scaleColors, sid) || !options.scales[sid]) { continue; }
                    if (options.scales[sid].ticks) {
                        options.scales[sid].ticks.color = snapshot.scaleColors[sid].ticks;
                    }
                    if (options.scales[sid].grid) {
                        options.scales[sid].grid.color = snapshot.scaleColors[sid].grid;
                    }
                }
            }
            options.backgroundColor = snapshot.backgroundColor;
            try {
                snapshot.chart.update('none');
            } catch (e) {
                snapshot.chart.update();
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

    function getReportArea() {
        var $area = $('.ktp-report-print-area').first();
        if (!$area.length) { $area = $('#report_content').first(); }
        return $area;
    }

    /* ----------------------------------------------------------------
     * エントリーポイント（プリントボタン押下）
     * ---------------------------------------------------------------- */
    function showReportPrintPopup() {
        var $area = getReportArea();
        var printState = null;
        if (!$area.length) {
            alert('印刷する内容が見つかりません。');
            return;
        }

        var filename = 'レポート_' + (new Date().toISOString().slice(0, 10));

        // 印刷時: グラフをライトで再描画 → 画像化直前に全Chartの文字色を黒・背景白に上書き → 直接印刷
        prepareChartsForLightPrint($area).then(function() {
            return applyPrintStyleToCharts($area);
        }).then(function(state) {
            printState = state;
            return null;
        }).then(function() {
            var cloneHtml = buildWhiteCloneHtml($area);
            var html = createPrintableHTML(cloneHtml, filename);
            printDirect(html, filename);
        }).catch(function(err) {
            console.error('[KTP-REPORT-PRINT] build failed:', err);
            alert('印刷データの作成に失敗しました。');
        }).finally(function() {
            restoreChartStylesAfterPrint(printState);
            restoreChartsAfterPrint($area).catch(function() {});
        });
    }

    window.ktpReportPrintOpen = showReportPrintPopup;

})(jQuery);
