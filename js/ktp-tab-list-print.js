/**
 * マスタ一覧タブ共通: リスト印刷（print_all=1 で全件取得して iframe 印刷）
 *
 * @package KTPWP
 * @since 1.0.0
 */

(function ($) {
    'use strict';

    var bound = false;

    function ymd() {
        var d = new Date();
        var y = d.getFullYear();
        var m = String(d.getMonth() + 1).padStart(2, '0');
        var day = String(d.getDate()).padStart(2, '0');
        return { compact: y + m + day, display: y + '-' + m + '-' + day };
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
        if (btn && btn.dataset && btn.dataset.printFooterName) {
            var footer = String(btn.dataset.printFooterName).trim();
            if (footer) {
                return footer;
            }
        }
        return '（自社名未設定）';
    }

    function sanitizeForPrint(root) {
        root.querySelectorAll('script, style, .no-print, [data-no-print], .pagination').forEach(function (el) {
            el.remove();
        });
        root.querySelectorAll('button, form').forEach(function (el) {
            el.remove();
        });
        root.querySelectorAll('.material-symbols-outlined, .fa, .fas, .far, .fal, .fab, svg').forEach(function (el) {
            el.remove();
        });
        root.querySelectorAll('[onclick],[onkeydown],[onsubmit]').forEach(function (el) {
            el.removeAttribute('onclick');
            el.removeAttribute('onkeydown');
            el.removeAttribute('onsubmit');
        });
        root.querySelectorAll('input, textarea, select').forEach(function (el) {
            var span = document.createElement('span');
            var value = el.tagName.toLowerCase() === 'select'
                ? ((el.options && el.options[el.selectedIndex]) ? el.options[el.selectedIndex].text : '')
                : (el.value || '');
            span.textContent = value;
            span.className = 'print-replaced-field';
            el.replaceWith(span);
        });
        root.querySelectorAll('a').forEach(function (el) {
            el.removeAttribute('href');
            el.style.color = '#111827';
            el.style.textDecoration = 'none';
        });
    }

    var PER_IMAGE_INLINE_MS = 12000;
    var IMAGE_INLINE_CONCURRENCY = 8;

    function inlineOneImageForPrint(img, loadedBySrc) {
        var src = img.getAttribute('src');
        if (!src || src.indexOf('data:') === 0) {
            return Promise.resolve();
        }

        var sources = [img];
        var cached = loadedBySrc && loadedBySrc.get(src);
        if (cached && cached !== img) {
            sources.push(cached);
        }

        for (var i = 0; i < sources.length; i++) {
            var source = sources[i];
            if (!source.complete || source.naturalWidth <= 0) {
                continue;
            }
            try {
                var canvas = document.createElement('canvas');
                canvas.width = source.naturalWidth;
                canvas.height = source.naturalHeight;
                var ctx = canvas.getContext('2d');
                if (ctx) {
                    ctx.drawImage(source, 0, 0);
                    img.setAttribute('src', canvas.toDataURL('image/png'));
                }
                return Promise.resolve();
            } catch (e) {
                /* cross-origin 等は fetch へフォールバック */
            }
        }

        return Promise.race([
            window.fetch(src, { credentials: 'same-origin' })
                .then(function (response) {
                    if (!response.ok) {
                        return;
                    }
                    return response.blob();
                })
                .then(function (blob) {
                    if (!blob) {
                        return;
                    }
                    return new Promise(function (resolve, reject) {
                        var reader = new FileReader();
                        reader.onloadend = function () {
                            resolve(reader.result);
                        };
                        reader.onerror = reject;
                        reader.readAsDataURL(blob);
                    });
                })
                .then(function (dataUrl) {
                    if (typeof dataUrl === 'string' && dataUrl !== '') {
                        img.setAttribute('src', dataUrl);
                    }
                }),
            new Promise(function (_, reject) {
                window.setTimeout(function () {
                    reject(new Error('image inline timeout'));
                }, PER_IMAGE_INLINE_MS);
            }),
        ]).catch(function () {
            /* 失敗時は元 URL のまま */
        });
    }

    function buildLoadedImageLookup() {
        var loadedBySrc = new Map();
        document.querySelectorAll('img[src]').forEach(function (node) {
            if (!(node instanceof HTMLImageElement) || !node.complete || node.naturalWidth <= 0) {
                return;
            }
            var nodeSrc = node.getAttribute('src');
            if (nodeSrc) {
                loadedBySrc.set(nodeSrc, node);
            }
        });
        return loadedBySrc;
    }

    function inlineImagesForPrint(root) {
        var images = Array.from(root.querySelectorAll('img'));
        if (images.length === 0) {
            return Promise.resolve();
        }

        var loadedBySrc = buildLoadedImageLookup();
        images.forEach(function (img) {
            img.removeAttribute('loading');
            img.removeAttribute('decoding');
        });

        var chain = Promise.resolve();
        for (var i = 0; i < images.length; i += IMAGE_INLINE_CONCURRENCY) {
            (function (batch) {
                chain = chain.then(function () {
                    return Promise.allSettled(batch.map(function (img) {
                        return inlineOneImageForPrint(img, loadedBySrc);
                    }));
                });
            })(images.slice(i, i + IMAGE_INLINE_CONCURRENCY));
        }
        return chain;
    }

    function printableHtml(content, headerText, footerText) {
        return '<!doctype html><html lang="ja"><head><meta charset="utf-8"><title>'
            + escapeHtml(headerText) + '</title><style>'
            + 'html,body{margin:0;padding:0;background:#fff;color:#111827;}'
            + 'body{font-family:"Noto Sans JP","Hiragino Kaku Gothic ProN","Yu Gothic",Meiryo,sans-serif;font-size:12px;line-height:1.5;padding:16px 14px 24px;-webkit-print-color-adjust:exact;print-color-adjust:exact;}'
            + '.print-header{font-size:14px;font-weight:700;text-align:center;margin:0 0 10px;}'
            + '.print-footer{position:fixed;bottom:0;left:0;right:0;height:10mm;display:flex;align-items:center;justify-content:center;border-top:1px solid #ddd;background:#fff;font-size:11px;z-index:9999;pointer-events:none;}'
            + '.print-content table{width:100%;border-collapse:collapse;}'
            + '.print-content th,.print-content td{border:1px solid #d1d5db;padding:6px 8px;vertical-align:top;color:#111827;background:#fff;}'
            + '.print-content .data_list_title{font-size:14px;font-weight:700;margin:0 0 8px;}'
            + '.print-content a{color:#111827!important;text-decoration:none!important;}'
            + '.print-content img{max-width:100%;height:auto;}'
            + '.print-content img.ktp-service-list-thumb{width:12mm;height:12mm;max-width:12mm;max-height:12mm;object-fit:cover;border:1px solid #d1d5db;}'
            + '.print-content .truncate{white-space:normal!important;overflow:visible!important;text-overflow:clip!important;max-width:none!important;}'
            + '.print-replaced-field{display:inline-block;min-height:1em;}'
            + '@page{size:A4 portrait;margin:10mm 10mm 18mm;}'
            + '</style></head><body>'
            + '<div class="print-header">' + escapeHtml(headerText) + '</div>'
            + '<div class="print-footer">' + escapeHtml(footerText) + '</div>'
            + '<div class="print-content">' + content + '</div>'
            + '</body></html>';
    }

    function printHtml(html, filename, restoreFocusTo) {
        var iframe = document.createElement('iframe');
        iframe.style.cssText = 'position:fixed;left:-9999px;top:-9999px;width:1px;height:1px;border:0;opacity:0;pointer-events:none;';
        document.body.appendChild(iframe);

        var originalDocumentTitle = document.title;
        var cleanupDone = false;

        function cleanup() {
            if (cleanupDone) {
                return;
            }
            cleanupDone = true;
            setTimeout(function () {
                try {
                    document.body.removeChild(iframe);
                } catch (e) {}
                try {
                    document.title = originalDocumentTitle;
                } catch (e2) {}
                if (restoreFocusTo && typeof restoreFocusTo.focus === 'function') {
                    restoreFocusTo.focus();
                }
            }, 300);
        }

        var printed = false;
        function triggerPrint() {
            if (printed) {
                return;
            }
            printed = true;
            try {
                var frameWin = iframe.contentWindow || iframe;
                frameWin.focus();
                frameWin.onafterprint = cleanup;
                try {
                    document.title = filename + '.pdf';
                } catch (e) {}
                setTimeout(function () {
                    try {
                        frameWin.print();
                    } catch (err) {
                        cleanup();
                    }
                }, 100);
            } catch (e) {
                cleanup();
            }
        }

        try {
            var frameDoc = iframe.contentDocument || iframe.contentWindow.document;
            iframe.onload = function () {
                try {
                    var d = iframe.contentDocument || iframe.contentWindow.document;
                    if (d) {
                        d.title = filename + '.pdf';
                    }
                } catch (e) {}
                triggerPrint();
            };
            frameDoc.open();
            frameDoc.write(html);
            frameDoc.close();
        } catch (e) {
            cleanup();
            return false;
        }

        setTimeout(cleanup, 15000);
        return true;
    }

    function resolvePrintSource(btn, target) {
        var fetchUrl = (btn.dataset.printFetchUrl || '').trim();
        if (fetchUrl === '') {
            return Promise.resolve(target ? target.cloneNode(true) : null);
        }

        return window.fetch(fetchUrl, {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                return response.text();
            })
            .then(function (html) {
                var parsed = new DOMParser().parseFromString(html, 'text/html');
                var selector = (btn.dataset.printExtractSelector || btn.dataset.printTarget || '').trim();
                if (selector !== '') {
                    var extracted = parsed.querySelector(selector);
                    if (extracted) {
                        return extracted.cloneNode(true);
                    }
                }
                if (target) {
                    return target.cloneNode(true);
                }
                return null;
            })
            .catch(function (error) {
                console.error('[KTP-TAB-LIST-PRINT] 印刷データ取得に失敗:', error);
                return target ? target.cloneNode(true) : null;
            });
    }

    function handlePrint(btn) {
        var targetSelector = btn.dataset.printTarget;
        var target = targetSelector ? document.querySelector(targetSelector) : null;
        var fetchUrl = (btn.dataset.printFetchUrl || '').trim();

        if (!target && fetchUrl === '') {
            window.alert(typeof ktpwpTranslate === 'function'
                ? ktpwpTranslate('印刷する内容が見つかりません。')
                : '印刷する内容が見つかりません。');
            return Promise.resolve();
        }

        return resolvePrintSource(btn, target).then(function (clone) {
            if (!clone) {
                window.alert(typeof ktpwpTranslate === 'function'
                    ? ktpwpTranslate('印刷する内容が見つかりません。')
                    : '印刷する内容が見つかりません。');
                return;
            }
            return inlineImagesForPrint(clone).then(function () {
                sanitizeForPrint(clone);
                var date = ymd();
                var base = (btn.dataset.printFilenameBase || '一覧').trim();
                var title = (btn.dataset.printTitle || base).trim();
                var filename = base + '_' + date.compact;
                var header = title + '（' + date.display + '）';
                var footer = getPrintOfficeName(btn);
                printHtml(printableHtml(clone.innerHTML, header, footer), filename, btn);
            });
        });
    }

    function bindPrintHandler() {
        if (bound) {
            return;
        }
        bound = true;

        $(document).on('click', '[data-tab-list-print]', function (event) {
            var btn = event.currentTarget;
            if (!btn || btn.disabled || btn.dataset.ktpPrintBusy === '1') {
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            btn.dataset.ktpPrintBusy = '1';

            handlePrint(btn).finally(function () {
                window.setTimeout(function () {
                    delete btn.dataset.ktpPrintBusy;
                }, 1500);
            });
        });
    }

    $(bindPrintHandler);
})(jQuery);
