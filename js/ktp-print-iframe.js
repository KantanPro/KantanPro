/**
 * 印刷処理（KantanBiz kp-print-iframe.js 相当）
 * @package KTPWP
 */
(function (global) {
'use strict';

/**
 * 印刷処理（Electron / Cursor 内蔵ブラウザでは iframe.print() がレンダラー落ちの原因になるため分岐）
 */

let printInProgress = false;
/** @type {number} */
let printStartedAt = 0;
const STALE_PRINT_LOCK_MS = 20000;
const BEFORE_PRINT_TIMEOUT_MS = 8000;
/** iframe 印刷中は親ページの @media print（ダーク→白）を無効化する */
const SUPPRESS_PARENT_PRINT_CLASS = 'kp-suppress-parent-print';
/** @type {HTMLIFrameElement | null} */
let sharedPrintFrame = null;
/** @type {number | null} */
let suppressReleaseFallbackTimer = null;

function suppressParentPrintStyles() {
    document.documentElement.classList.add(SUPPRESS_PARENT_PRINT_CLASS);
    if (suppressReleaseFallbackTimer !== null) {
        window.clearTimeout(suppressReleaseFallbackTimer);
        suppressReleaseFallbackTimer = null;
    }
}

function releaseParentPrintStyles() {
    document.documentElement.classList.remove(SUPPRESS_PARENT_PRINT_CLASS);
}

/** iframe 印刷中か */
function isParentPrintSuppressed() {
    return document.documentElement.classList.contains(SUPPRESS_PARENT_PRINT_CLASS);
}

/** 印刷ダイアログが閉じるまで親ページの抑止を維持（早すぎる解除で白フラッシュ） */
function scheduleReleaseParentPrintStyles() {
    if (suppressReleaseFallbackTimer !== null) {
        window.clearTimeout(suppressReleaseFallbackTimer);
        suppressReleaseFallbackTimer = null;
    }

    let done = false;
    const finish = () => {
        if (done) {
            return;
        }

        done = true;
        if (suppressReleaseFallbackTimer !== null) {
            window.clearTimeout(suppressReleaseFallbackTimer);
            suppressReleaseFallbackTimer = null;
        }
        window.removeEventListener('afterprint', finish);
        releaseParentPrintStyles();
    };

    window.addEventListener('afterprint', finish);
    suppressReleaseFallbackTimer = window.setTimeout(finish, 5000);
}

function captureFocusTarget() {
    const el = document.activeElement;

    return el instanceof HTMLElement && document.contains(el) ? el : null;
}

function restoreFocusTarget(el) {
    if (!(el instanceof HTMLElement) || !document.contains(el)) {
        return;
    }

    try {
        el.focus({ preventScroll: true });
    } catch {
        /* ignore */
    }
}

/**
 * ブラウザ印刷の固定フッタ用名称（帳票社名 → 未設定時はオフィス名）
 * 優先: 印刷ボタンの data-print-footer-name → meta → #kp-print-office-name
 *
 * @param {Element | null | undefined} triggerEl
 */
function getPrintOfficeName(triggerEl = null) {
    if (triggerEl instanceof HTMLElement) {
        const fromBtn = (triggerEl.dataset.printFooterName || '').trim();
        if (fromBtn !== '') {
            return fromBtn;
        }
    }

    if (triggerEl instanceof Element) {
        const carrier = triggerEl.closest('[data-print-footer-name]');
        if (carrier instanceof HTMLElement) {
            const fromCarrier = (carrier.dataset.printFooterName || '').trim();
            if (fromCarrier !== '') {
                return fromCarrier;
            }
        }
    }

    const meta = document.querySelector('meta[name="kp-print-footer-name"]');
    const fromMeta = (meta?.getAttribute('content') || '').trim();
    if (fromMeta !== '') {
        return fromMeta;
    }

    const officeEl = document.getElementById('kp-print-office-name');
    if (officeEl) {
        const name = (officeEl.textContent || '').trim();
        if (name !== '') {
            return name;
        }

        const fallback = (officeEl.dataset.fallback || '').trim();
        if (fallback !== '') {
            return fallback;
        }
    }

    const legacyEl = document.getElementById('work-list-print-company');
    const legacyName = (legacyEl?.textContent || '').trim();
    if (legacyName !== '') {
        return legacyName;
    }

    return document.documentElement.lang?.toLowerCase().startsWith('en')
        ? '(Company name not set)'
        : '（自社名未設定）';
}

function escapeHtmlForPrint(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

/** Safari では position:fixed のフッタが消えることがあるため末尾フロー用も併設 */
const PRINT_FOOTER_STYLE_BLOCK = `
.print-footer-fixed{position:fixed;bottom:0;left:0;right:0;height:10mm;display:flex;align-items:center;justify-content:center;border-top:1px solid #ddd;background:#fff;font-size:11px;line-height:1.3;z-index:9999;pointer-events:none;margin:0;padding:0;-webkit-print-color-adjust:exact;print-color-adjust:exact;}
.print-footer-end{display:none;margin:16px 0 0;padding:10px 0 0;border-top:1px solid #ddd;text-align:center;font-size:11px;line-height:1.3;break-inside:avoid;page-break-inside:avoid;}
@media print{
body{padding-bottom:12mm;}
.print-footer-fixed{display:flex !important;}
.print-footer-end{display:none !important;}
html.kp-safari-print .print-footer-fixed{display:none !important;}
html.kp-safari-print .print-footer-end{display:block !important;}
}`;

/**
 * @param {string} footerEscaped HTML エスケープ済みフッタ文字列
 */
function buildPrintFooterFixedMarkup(footerEscaped) {
    if (!footerEscaped) {
        return '';
    }

    return `<div class="print-footer-fixed">${footerEscaped}</div>`;
}

/**
 * @param {string} footerEscaped HTML エスケープ済みフッタ文字列
 */
function buildPrintFooterEndMarkup(footerEscaped) {
    if (!footerEscaped) {
        return '';
    }

    return `<div class="print-footer-end">${footerEscaped}</div>`;
}

/** @deprecated buildPrintFooterFixedMarkup / buildPrintFooterEndMarkup を使用 */
function buildPrintFooterMarkup(footerEscaped) {
    return buildPrintFooterFixedMarkup(footerEscaped) + buildPrintFooterEndMarkup(footerEscaped);
}

function markSafariPrintDocument(doc) {
    if (!doc?.documentElement || !isSafariBrowser() || isUnsafePrintHost()) {
        return;
    }

    doc.documentElement.classList.add('kp-safari-print');
}

/**
 * Cursor Simple Browser / Electron 等、iframe.print() で落ちやすい環境。
 */
function isUnsafePrintHost() {
    const ua = navigator.userAgent || '';

    if (typeof window.cursorBrowser !== 'undefined') {
        return true;
    }

    if (typeof window.acquireVsCodeApi === 'function') {
        return true;
    }

    if (ua.includes('Electron')) {
        return true;
    }

    if (ua.includes('Cursor')) {
        return true;
    }

    return false;
}

/** Safari（iOS/macOS）は 0px の非表示 iframe から print() できないことが多い */
function isSafariBrowser() {
    const ua = navigator.userAgent || '';

    return /Safari/i.test(ua) && !/Chrome|Chromium|CriOS|FxiOS|EdgiOS|Edg|OPR|OPiOS/i.test(ua);
}

/** Firefox は非表示 iframe 内の PDF blob から print() できないことが多い */
function isFirefoxBrowser() {
    return /Firefox\//i.test(navigator.userAgent || '');
}

/** iOS / iPadOS（Safari・Chrome 等） */
function isIosBrowser() {
    const ua = navigator.userAgent || '';

    if (/iPad|iPhone|iPod/i.test(ua)) {
        return true;
    }

    return navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1;
}

/**
 * PDF Blob を端末向けに保存する（iOS のみ共有シート優先。PC は a[download]）
 *
 * @param {Blob} blob
 * @param {string} filename
 */
async function downloadPdfBlob(blob, filename) {
    if (!(blob instanceof Blob)) {
        throw new Error('PDF blob missing');
    }

    const pdfFilename = filename.endsWith('.pdf') ? filename : `${filename}.pdf`;
    const url = URL.createObjectURL(blob);

    try {
        const file = typeof File !== 'undefined'
            ? new File([blob], pdfFilename, { type: 'application/pdf' })
            : null;

        // macOS 版 Chrome 等でも navigator.share があるが、PC では共有シートではなく直接 DL する
        if (
            isIosBrowser()
            && file
            && typeof navigator.share === 'function'
            && (!navigator.canShare || navigator.canShare({ files: [file] }))
        ) {
            try {
                await navigator.share({ files: [file] });
                URL.revokeObjectURL(url);

                return;
            } catch (error) {
                if (error instanceof Error && error.name === 'AbortError') {
                    URL.revokeObjectURL(url);

                    return;
                }
                // 共有失敗時は window.open せず a[download] へ（非同期後の open はポップアップブロックされる）
            }
        }

        const link = document.createElement('a');
        link.href = url;
        link.download = pdfFilename;
        link.rel = 'noopener';
        link.style.display = 'none';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        window.setTimeout(() => URL.revokeObjectURL(url), 1000);
    } catch (error) {
        URL.revokeObjectURL(url);
        throw error;
    }
}

/** 非表示 iframe ではなくポップアップ／新タブで印刷 HTML を開く（Cursor 等のみ。Safari は iframe 印刷） */
function prefersPopupPrint() {
    return isUnsafePrintHost();
}

/**
 * 非同期 fetch の前に同期的に呼ぶ（ポップアップブロック回避）。
 * Safari は事前の about:blank を開かない（閉じ残りの空タブを防ぐ。印刷は iframe 側で行う）。
 *
 * @returns {Window | { viaCursorBrowser: true } | null}
 */
function preparePrintPreviewWindow() {
    if (!prefersPopupPrint()) {
        return null;
    }

    if (isUnsafePrintHost()) {
        if (typeof window.cursorBrowser?.send === 'function') {
            return { viaCursorBrowser: true };
        }

        return null;
    }

    return null;
}

/**
 * Firefox 印刷用子ウィンドウの HTML（postMessage で PDF URL を受け取り自ら遷移・印刷）
 * @deprecated 一括請求書はページ画像印刷を使用。印刷用タブのプレースホルダのみ。
 *
 * @returns {string}
 */
function buildFirefoxPdfPrintShellHtml() {
    return '<!DOCTYPE html><html lang="ja"><head><meta charset="utf-8"><title>印刷</title></head><body>' +
        '<p style="font:14px/1.6 system-ui,sans-serif;padding:24px;color:#374151;margin:0">' +
        '請求書を準備しています…</p></body></html>';
}

/**
 * Firefox: PDF 生成は非同期のため、クリック直後に印刷用ウィンドウを開く（user activation 維持）。
 * noopener は付けない（親から close() するため）。
 *
 * @returns {Window | null}
 */
function prepareFirefoxPdfPrintWindow() {
    if (!isFirefoxBrowser() || isUnsafePrintHost()) {
        return null;
    }

    try {
        try {
            const stale = window.open('', 'kp-firefox-pdf-print');
            if (stale && !stale.closed) {
                stale.close();
            }
        } catch {
            /* ignore */
        }

        const win = window.open('about:blank', 'kp-firefox-pdf-print');
        if (!win) {
            return null;
        }

        win.document.open();
        win.document.write(buildFirefoxPdfPrintShellHtml());
        win.document.close();

        return win;
    } catch {
        return null;
    }
}

/** @deprecated prefersPopupPrint 向け。preparePrintPreviewWindow を使用 */
function prepareUnsafePrintPreviewWindow() {
    return preparePrintPreviewWindow();
}

/**
 * @param {Window} previewWindow
 * @param {string} docHtml
 * @param {string} [title]
 * @returns {boolean}
 */
function loadHtmlInPreviewWindow(previewWindow, docHtml, title = '') {
    if (!(previewWindow instanceof Window) || previewWindow.closed) {
        return false;
    }

    // Safari: 完全な HTML を document.write すると about:blank に書けず失敗しやすい → Blob URL を優先
    try {
        const blob = new Blob([docHtml], { type: 'text/html;charset=utf-8' });
        const blobUrl = URL.createObjectURL(blob);
        previewWindow.location.replace(blobUrl);
        window.setTimeout(() => URL.revokeObjectURL(blobUrl), 120000);

        return true;
    } catch {
        /* fall through */
    }

    try {
        previewWindow.document.open();
        previewWindow.document.write(docHtml);
        previewWindow.document.close();

        if (title) {
            try {
                previewWindow.document.title = title;
            } catch {
                /* ignore */
            }
        }

        return true;
    } catch {
        return false;
    }
}

/** @deprecated loadHtmlInPreviewWindow を使用 */
function writeHtmlToPreviewWindow(previewWindow, docHtml, title = '') {
    return loadHtmlInPreviewWindow(previewWindow, docHtml, title);
}

/**
 * @param {string} docHtml
 * @returns {boolean}
 */
function openPreviewViaCursorBrowser(docHtml) {
    if (typeof window.cursorBrowser?.send !== 'function') {
        return false;
    }

    try {
        const dataUrl = `data:text/html;charset=utf-8,${encodeURIComponent(docHtml)}`;

        if (dataUrl.length > 1_500_000) {
            return false;
        }

        window.cursorBrowser.send('open-url-new-tab', dataUrl);

        return true;
    } catch {
        return false;
    }
}

/**
 * @param {string} docHtml
 * @param {string} downloadName
 */
function downloadHtmlPreview(docHtml, downloadName) {
    const blob = new Blob([docHtml], { type: 'text/html;charset=utf-8' });
    const blobUrl = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = blobUrl;
    link.download = downloadName;
    link.rel = 'noopener';
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.setTimeout(() => URL.revokeObjectURL(blobUrl), 120000);
    window.alert('印刷プレビューを HTML ファイルで保存しました。開いて ⌘P / Ctrl+P で印刷してください。');
}

/**
 * @param {Window | { viaCursorBrowser: true } | null} previewWindow
 * @param {string} blobUrl
 * @param {string} downloadName
 * @param {string} [docHtml] HTML プレビュー用（Cursor 向けフォールバック）
 * @returns {boolean}
 */
function navigateUnsafePrintPreview(previewWindow, blobUrl, downloadName, docHtml = '') {
    if (docHtml && previewWindow instanceof Window && !previewWindow.closed) {
        if (loadHtmlInPreviewWindow(previewWindow, docHtml, downloadName.replace(/\.html$/i, ''))) {
            return true;
        }
    }

    if (docHtml && previewWindow && typeof previewWindow === 'object' && 'viaCursorBrowser' in previewWindow) {
        if (openPreviewViaCursorBrowser(docHtml)) {
            return true;
        }
    }

    if (previewWindow instanceof Window && !previewWindow.closed) {
        try {
            previewWindow.location.href = blobUrl;

            return true;
        } catch {
            try {
                previewWindow.close();
            } catch {
                /* ignore */
            }
        }
    }

    if (docHtml && openPreviewViaCursorBrowser(docHtml)) {
        return true;
    }

    if (typeof window.cursorBrowser?.send === 'function') {
        try {
            window.cursorBrowser.send('open-url-new-tab', blobUrl);

            return true;
        } catch {
            /* fall through */
        }
    }

    if (!isUnsafePrintHost()) {
        try {
            const popup = window.open(blobUrl, '_blank', 'noopener,noreferrer');
            if (popup) {
                return true;
            }
        } catch {
            /* fall through */
        }
    }

    if (docHtml) {
        if (isSafariBrowser() && !isUnsafePrintHost()) {
            dismissUnsafePrintPreviewWindow(previewWindow instanceof Window ? previewWindow : null);

            return false;
        }

        downloadHtmlPreview(docHtml, downloadName);

        return true;
    }

    const link = document.createElement('a');
    link.href = blobUrl;
    link.download = downloadName;
    link.rel = 'noopener';
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.alert('印刷プレビューをファイルで保存しました。開いて ⌘P / Ctrl+P で印刷してください。');

    return true;
}

/** @param {Window | null} previewWindow */
function dismissUnsafePrintPreviewWindow(previewWindow) {
    if (previewWindow instanceof Window && !previewWindow.closed) {
        try {
            previewWindow.close();
        } catch {
            /* ignore */
        }
    }
}

/**
 * Safari: 親から iframe.contentWindow.print() すると
 * 「このWebページがプリントを求めています」確認が出ることがある。
 * iframe 内スクリプトから window.print() して同一コンテキストで印刷する。
 *
 * @param {Window} frameWin
 * @param {Document} frameDoc
 * @param {number} printDelayMs
 * @param {() => void} onFail
 */
function invokePrintInIframe(frameWin, frameDoc, printDelayMs, onFail) {
    window.setTimeout(() => {
        try {
            if (!frameWin || !frameDoc) {
                onFail();

                return;
            }

            if (isSafariBrowser() && !isUnsafePrintHost()) {
                const script = frameDoc.createElement('script');
                script.textContent = 'try{window.print();}catch(e){}';
                const target = frameDoc.body || frameDoc.documentElement;
                if (!target) {
                    onFail();

                    return;
                }

                target.appendChild(script);
                script.remove();
            } else {
                frameWin.print();
            }
        } catch {
            onFail();
        }
    }, printDelayMs);
}

function getSharedPrintFrame() {
    if (sharedPrintFrame && document.body.contains(sharedPrintFrame)) {
        return sharedPrintFrame;
    }

    sharedPrintFrame = document.createElement('iframe');
    sharedPrintFrame.setAttribute('aria-hidden', 'true');
    sharedPrintFrame.setAttribute('tabindex', '-1');
    sharedPrintFrame.setAttribute('data-turbo', 'false');
    sharedPrintFrame.title = 'print';
    // width/height を 1px にする（0px だと Safari がレイアウト計算を省略して白紙印刷になる）
    sharedPrintFrame.style.cssText =
        'position:fixed;left:-9999px;top:-9999px;width:1px;height:1px;border:0;opacity:0;pointer-events:none;';
    document.body.appendChild(sharedPrintFrame);

    return sharedPrintFrame;
}

/** @type {HTMLIFrameElement | null} */
let firefoxPdfPrintFrame = null;

/**
 * Firefox の PDF ビューアは 1px iframe だと描画されないことがあるため A4 相当サイズで用意する
 */
function getFirefoxPdfPrintFrame() {
    if (firefoxPdfPrintFrame && document.body.contains(firefoxPdfPrintFrame)) {
        return firefoxPdfPrintFrame;
    }

    firefoxPdfPrintFrame = document.createElement('iframe');
    firefoxPdfPrintFrame.id = 'firefox-pdf-print-frame';
    firefoxPdfPrintFrame.setAttribute('aria-hidden', 'true');
    firefoxPdfPrintFrame.setAttribute('tabindex', '-1');
    firefoxPdfPrintFrame.setAttribute('data-turbo', 'false');
    firefoxPdfPrintFrame.title = 'print';
    firefoxPdfPrintFrame.style.cssText =
        'position:fixed;left:-10000px;top:0;width:794px;height:1123px;border:0;opacity:0;pointer-events:none;';
    document.body.appendChild(firefoxPdfPrintFrame);

    return firefoxPdfPrintFrame;
}

/**
 * 非表示 iframe に PDF blob URL を直読みして印刷する
 *
 * @param {HTMLIFrameElement} frame
 * @param {string} blobUrl
 * @param {() => void} onDone
 * @param {{ printDelayMs?: number, fallbackMs?: number, directPdfPrint?: boolean }} [options]
 */
function printPdfBlobViaIframeSrc(frame, blobUrl, onDone, options = {}) {
    const { printDelayMs = 600, fallbackMs = 3500, directPdfPrint = false } = options;
    let printStarted = false;
    /** @type {number | null} */
    let fallbackTimer = null;

    const cleanup = () => {
        if (fallbackTimer !== null) {
            window.clearTimeout(fallbackTimer);
            fallbackTimer = null;
        }

        onDone();
    };

    const tryPrint = () => {
        if (printStarted) {
            return;
        }

        try {
            const frameWin = frame.contentWindow;
            if (!frameWin) {
                return;
            }

            if (directPdfPrint) {
                printStarted = true;
                frameWin.onafterprint = cleanup;
                window.setTimeout(() => {
                    try {
                        frameWin.focus();
                        frameWin.print();
                    } catch {
                        cleanup();
                        window.alert('印刷ダイアログを開けませんでした。');
                    }
                }, printDelayMs);

                return;
            }

            printStarted = true;
            const frameDoc = frame.contentDocument || frameWin.document;
            if (!frameDoc) {
                printStarted = false;

                throw new Error('print frame missing');
            }

            frameWin.onafterprint = cleanup;
            invokePrintInIframe(frameWin, frameDoc, printDelayMs, () => {
                cleanup();
                window.alert('印刷ダイアログを開けませんでした。');
            });
        } catch {
            if (!printStarted) {
                return;
            }

            cleanup();
            window.alert('印刷ダイアログを開けませんでした。');
        }
    };

    frame.onload = () => {
        window.setTimeout(tryPrint, directPdfPrint ? 300 : 100);
    };

    fallbackTimer = window.setTimeout(() => {
        tryPrint();
    }, fallbackMs);

    frame.src = blobUrl;
}

/**
 * Firefox: 子ウィンドウへ postMessage で PDF URL を渡し、子側で遷移・印刷する
 *
 * @param {Window} previewWindow
 * @param {string} blobUrl
 * @param {() => void} onDone
 * @param {{ printDelayMs?: number, onFailed?: (() => void) | null }} [options]
 * @returns {boolean}
 */
function printPdfBlobInFirefoxWindow(previewWindow, blobUrl, onDone, options = {}) {
    const { printDelayMs = 3000, onFailed = null } = options;

    if (!(previewWindow instanceof Window) || previewWindow.closed) {
        return false;
    }

    let settled = false;
    let pdfSent = false;
    /** @type {number[]} */
    const timers = [];

    const clearTimers = () => {
        timers.forEach((id) => window.clearTimeout(id));
        timers.length = 0;
    };

    const closePreviewWindow = () => {
        try {
            if (!previewWindow.closed) {
                previewWindow.close();
            }
        } catch {
            /* ignore */
        }
    };

    const settle = () => {
        if (settled) {
            return;
        }

        settled = true;
        clearTimers();
        window.removeEventListener('message', onChildMessage);
        closePreviewWindow();
        onDone();
    };

    const fail = () => {
        if (settled) {
            return;
        }

        settled = true;
        clearTimers();
        window.removeEventListener('message', onChildMessage);
        closePreviewWindow();

        if (typeof onFailed === 'function') {
            onFailed();

            return;
        }

        onDone();
        window.alert('印刷ダイアログを開けませんでした。');
    };

    const sendPdfToChild = () => {
        if (pdfSent || settled || previewWindow.closed) {
            return false;
        }

        pdfSent = true;

        try {
            previewWindow.postMessage(
                {
                    channel: 'kp-firefox-pdf-print',
                    type: 'load-pdf',
                    url: blobUrl,
                    printDelayMs,
                },
                '*',
            );

            return true;
        } catch {
            fail();

            return false;
        }
    };

    const onChildMessage = (event) => {
        if (event.source !== previewWindow) {
            return;
        }

        const data = event.data;
        if (!data || data.channel !== 'kp-firefox-pdf-print') {
            return;
        }

        if (data.type === 'ready') {
            sendPdfToChild();

            return;
        }

        if (data.type === 'print-done') {
            settle();

            return;
        }

        if (data.type === 'print-failed') {
            fail();
        }
    };

    window.addEventListener('message', onChildMessage);
    timers.push(
        window.setTimeout(() => {
            if (!pdfSent) {
                sendPdfToChild();
            }
        }, 200),
    );
    timers.push(
        window.setTimeout(() => {
            if (!settled && !pdfSent) {
                fail();
                window.alert('印刷を開始できませんでした。ポップアップの設定を確認してください。');
            }
        }, 120000),
    );

    return true;
}

/**
 * @param {string} url
 * @returns {Promise<Document>}
 */
async function fetchHtmlDocument(url) {
    const response = await window.fetch(url, {
        credentials: 'same-origin',
        headers: {
            Accept: 'text/html',
            'X-Requested-With': 'XMLHttpRequest',
        },
    });

    if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
    }

    const text = await response.text();

    return new DOMParser().parseFromString(text, 'text/html');
}

function buildPrintPreviewHtml(html, filename) {
    const hint = `
<div id="kp-print-hint" style="position:fixed;top:0;left:0;right:0;z-index:99999;background:#fef3c7;color:#92400e;padding:10px 16px;font:14px/1.5 system-ui,sans-serif;border-bottom:1px solid #fcd34d;print-color-adjust:exact;-webkit-print-color-adjust:exact;">
  印刷プレビューです。<strong>⌘P / Ctrl+P</strong> で印刷または PDF 保存できます。このバナーは印刷時に非表示になります。
</div>
<style>@media print{#kp-print-hint{display:none!important;}}</style>`;

    if (/<body[\s>]/i.test(html)) {
        return html.replace(/<body([^>]*)>/i, `<body$1>${hint}`);
    }

    return `<!DOCTYPE html><html lang="ja"><head><meta charset="utf-8"><title>${filename || 'print'}</title></head><body>${hint}${html}</body></html>`;
}

/**
 * Electron / Cursor 向け: 新タブ（またはダウンロード）で印刷 HTML を開く。print() は呼ばない。
 *
 * @param {Window | { viaCursorBrowser: true } | null} [previewWindow]
 */
function openPrintPreviewTab(html, filename, restoreFocusTo, previewWindow = null) {
    const focusTarget = restoreFocusTo ?? captureFocusTarget();
    const docHtml = buildPrintPreviewHtml(html, filename);
    const downloadName = `${filename || 'print'}.html`;
    const blob = new Blob([docHtml], { type: 'text/html;charset=utf-8' });
    const blobUrl = URL.createObjectURL(blob);

    navigateUnsafePrintPreview(previewWindow, blobUrl, downloadName, docHtml);

    window.setTimeout(() => {
        URL.revokeObjectURL(blobUrl);
    }, 120000);

    restoreFocusTarget(focusTarget);
}

/**
 * Safari 向け: ポップアップ内側のスクリプトから window.print() を呼ぶ HTML を生成する。
 * 親ウィンドウから previewWindow.print() を呼ぶと Safari では親の印刷ダイアログが
 * 開いてしまう（またはブロックされる）ため、ポップアップ自身に auto-print させる。
 *
 * @param {string} html
 * @param {string} filename
 */
function buildSafariAutoPopupHtml(html, filename) {
    // </script> を文字列連結で回避（テンプレートリテラル内での早期終了対策）
    // load イベントより setTimeout の方が document.write 後も確実に発火する
    const autoScript =
        '<scr' + 'ipt>' +
        'function kpDoPrint(){' +
        '  window.onafterprint=function(){' +
        '    setTimeout(function(){try{window.close();}catch(e){}},200);' +
        '  };' +
        '  window.print();' +
        '}' +
        'if(document.readyState==="complete"){setTimeout(kpDoPrint,150);}' +
        'else{window.addEventListener("load",function(){setTimeout(kpDoPrint,150);},{once:true});}' +
        '</scr' + 'ipt>';

    const hint =
        '<div id="kp-print-hint" style="position:fixed;top:0;left:0;right:0;z-index:99999;' +
        'background:#fef3c7;color:#92400e;padding:10px 16px;font:14px/1.5 system-ui,sans-serif;' +
        'border-bottom:1px solid #fcd34d;print-color-adjust:exact;-webkit-print-color-adjust:exact;">' +
        '印刷ダイアログを開いています...</div>' +
        '<style>@media print{#kp-print-hint{display:none!important;}}</style>' +
        autoScript;

    if (/<body[\s>]/i.test(html)) {
        return html.replace(/<body([^>]*)>/i, `<body$1>${hint}`);
    }

    return `<!DOCTYPE html><html lang="ja"><head><meta charset="utf-8"><title>${filename || 'print'}</title></head><body>${hint}${html}</body></html>`;
}

/**
 * Safari 等: ポップアップに HTML を書き込む。
 * - Safari: popup 内スクリプトから自動 print()（cross-window print は親の印刷になるため使わない）
 * - Cursor (unsafe host): print() なしでプレビュー表示のみ
 *
 * @param {string} html 完全な HTML または body 断片
 * @param {string} filename
 * @param {HTMLElement|null} restoreFocusTo
 * @param {Window | { viaCursorBrowser: true } | null} previewWindow
 * @returns {boolean} プレビュー表示に成功したか（Safari で false のときは iframe 印刷へフォールバック）
 */
function printHtmlInPopupWindow(html, filename, restoreFocusTo, previewWindow) {
    const focusTarget = restoreFocusTo ?? captureFocusTarget();

    if (previewWindow instanceof Window && !previewWindow.closed) {
        const docHtml =
            isSafariBrowser() && !isUnsafePrintHost()
                ? buildSafariAutoPopupHtml(html, filename)
                : buildPrintPreviewHtml(html, filename);

        if (loadHtmlInPreviewWindow(previewWindow, docHtml, filename)) {
            restoreFocusTarget(focusTarget);

            return true;
        }
    }

    if (isSafariBrowser() && !isUnsafePrintHost()) {
        dismissUnsafePrintPreviewWindow(previewWindow instanceof Window ? previewWindow : null);

        return false;
    }

    openPrintPreviewTab(html, filename, focusTarget, previewWindow);

    return true;
}

/**
 * @param {object} options
 * @param {string} options.html
 * @param {string} [options.filename]
 * @param {number} [options.printDelayMs=100]
 * @param {HTMLElement|null} [options.restoreFocusTo]
 * @param {(iframe: HTMLIFrameElement) => void | Promise<void>} [options.beforePrint]
 * @param {() => void} [options.onCleanup]
 * @param {number} [options.fallbackCleanupMs=15000]
 * @param {Window | { viaCursorBrowser: true } | null} [options.previewWindow]
 * @returns {boolean}
 */
function printHtmlInHiddenIframe(options) {
    if (printInProgress) {
        if (Date.now() - printStartedAt > STALE_PRINT_LOCK_MS) {
            printInProgress = false;
        } else {
            return false;
        }
    }

    const {
        html,
        filename = '',
        printDelayMs = 100,
        restoreFocusTo = null,
        beforePrint = null,
        onCleanup = null,
        fallbackCleanupMs = 15000,
        previewWindow = null,
    } = options;

    const focusTarget = restoreFocusTo ?? captureFocusTarget();

    suppressParentPrintStyles();

    if (prefersPopupPrint()) {
        printInProgress = true;
        printStartedAt = Date.now();
        const popupHandled = printHtmlInPopupWindow(html, filename, focusTarget, previewWindow);

        if (popupHandled) {
            printInProgress = false;
            scheduleReleaseParentPrintStyles();
            onCleanup?.();

            return true;
        }

        if (isSafariBrowser() && !isUnsafePrintHost()) {
            printInProgress = false;
            // Safari: ポップアップ失敗時は非表示 iframe で印刷（HTML ダウンロードは使わない）
        } else {
            printInProgress = false;
            scheduleReleaseParentPrintStyles();
            onCleanup?.();

            return true;
        }
    }

    const iframe = getSharedPrintFrame();
    const originalDocumentTitle = document.title;
    const suggestedTitle = filename ? `${filename}.pdf` : '';

    printInProgress = true;
    printStartedAt = Date.now();

    let cleanupDone = false;
    const cleanup = () => {
        if (cleanupDone) {
            return;
        }

        cleanupDone = true;
        printInProgress = false;
        scheduleReleaseParentPrintStyles();

        window.setTimeout(() => {
            if (suggestedTitle) {
                try {
                    document.title = originalDocumentTitle;
                } catch {
                    /* ignore */
                }
            }

            restoreFocusTarget(focusTarget);
            onCleanup?.();
        }, 200);
    };

    let printed = false;
    let sequenceStarted = false;

    const triggerPrint = () => {
        if (printed) {
            return;
        }

        printed = true;

        try {
            const frameWin = iframe.contentWindow;
            if (!frameWin) {
                cleanup();

                return;
            }

            frameWin.onafterprint = cleanup;

            if (suggestedTitle) {
                try {
                    // iOS は親ページの title/URL が印刷ヘッダに出やすいため iframe 側のみ設定
                    if (!isIosBrowser() && !isSafariBrowser()) {
                        document.title = suggestedTitle;
                    }
                    if (frameWin.document) {
                        frameWin.document.title = suggestedTitle.replace(/\.pdf$/i, '');
                    }
                } catch {
                    /* ignore */
                }
            }

            invokePrintInIframe(frameWin, iframe.contentDocument || frameWin.document, printDelayMs, cleanup);
        } catch {
            cleanup();
        }
    };

    const runPrintSequence = async () => {
        if (sequenceStarted) {
            return;
        }

        sequenceStarted = true;

        try {
            if (beforePrint) {
                await Promise.race([
                    Promise.resolve(beforePrint(iframe)),
                    new Promise((_, reject) => {
                        window.setTimeout(() => reject(new Error('beforePrint timeout')), BEFORE_PRINT_TIMEOUT_MS);
                    }),
                ]);
            }

            triggerPrint();
        } catch (error) {
            console.error('[kp-print-iframe] beforePrint failed:', error);
            cleanup();
        }
    };

    try {
        const frameDoc = iframe.contentDocument || iframe.contentWindow?.document;
        if (!frameDoc) {
            cleanup();

            return false;
        }

        frameDoc.open();
        frameDoc.write(html);
        frameDoc.close();
        markSafariPrintDocument(frameDoc);

        void runPrintSequence();
    } catch (error) {
        console.error('[kp-print-iframe] print failed:', error);
        cleanup();

        return false;
    }

    window.setTimeout(cleanup, fallbackCleanupMs);

    return true;
}

/**
 * @param {string} url
 * @param {(doc: Document) => string | null | undefined} buildHtml
 * @param {object} printOptions
 */
async function fetchAndPrintHtml(url, buildHtml, printOptions) {
    const focusTarget = printOptions.restoreFocusTo ?? captureFocusTarget();
    const previewWindow = printOptions.previewWindow ?? null;

    suppressParentPrintStyles();

    try {
        const doc = await fetchHtmlDocument(url);
        const html = buildHtml(doc);

        if (!html) {
            throw new Error('print content missing');
        }

        const started = printHtmlInHiddenIframe({
            ...printOptions,
            html,
            restoreFocusTo: focusTarget,
            previewWindow,
        });

        if (!started) {
            releaseParentPrintStyles();
            dismissUnsafePrintPreviewWindow(previewWindow instanceof Window ? previewWindow : null);
            window.alert('印刷処理が既に実行中です。しばらく待ってから再度お試しください。');
            restoreFocusTarget(focusTarget);
        }
    } catch (error) {
        releaseParentPrintStyles();
        dismissUnsafePrintPreviewWindow(previewWindow instanceof Window ? previewWindow : null);
        console.error('[kp-print-iframe] fetch print failed:', error);
        window.alert('印刷データの取得に失敗しました。');
        restoreFocusTarget(focusTarget);
    }
}

/**
 * PDF Blob を印刷。Chrome / Firefox は非表示 iframe に PDF 直読み。Safari は iframe 内 script から print()。
 *
 * @param {Blob} blob
 * @param {HTMLElement|null} restoreFocusTo
 * @param {Window | { viaCursorBrowser: true } | null} [previewWindow]
 * @param {(() => void) | null} [onCleanup]
 */
function printPdfBlob(blob, restoreFocusTo, previewWindow = null, onCleanup = null) {
    const focusTarget = restoreFocusTo ?? captureFocusTarget();
    const blobUrl = URL.createObjectURL(blob);

    const finish = () => {
        URL.revokeObjectURL(blobUrl);
        restoreFocusTarget(focusTarget);
        onCleanup?.();
    };

    if (isUnsafePrintHost()) {
        navigateUnsafePrintPreview(previewWindow, blobUrl, 'order.pdf');
        window.setTimeout(() => URL.revokeObjectURL(blobUrl), 120000);
        restoreFocusTarget(focusTarget);
        onCleanup?.();

        return;
    }

    if (isFirefoxBrowser()) {
        const runParentIframeFallback = () => {
            printPdfBlobViaIframeSrc(getFirefoxPdfPrintFrame(), blobUrl, finish, {
                printDelayMs: 3000,
                fallbackMs: 10000,
                directPdfPrint: true,
            });
        };

        if (
            previewWindow instanceof Window
            && !previewWindow.closed
            && printPdfBlobInFirefoxWindow(previewWindow, blobUrl, finish, {
                printDelayMs: 3000,
                onFailed: runParentIframeFallback,
            })
        ) {
            return;
        }

        dismissUnsafePrintPreviewWindow(previewWindow instanceof Window ? previewWindow : null);
        runParentIframeFallback();

        return;
    }

    if (isSafariBrowser()) {
        let frame = document.getElementById('order-print-frame');
        if (!(frame instanceof HTMLIFrameElement)) {
            frame = getSharedPrintFrame();
            frame.id = 'order-print-frame';
        }

        printPdfBlobViaIframeSrc(frame, blobUrl, finish, {
            printDelayMs: 600,
            fallbackMs: 3000,
            directPdfPrint: false,
        });

        return;
    }

    let frame = document.getElementById('order-print-frame');
    if (!(frame instanceof HTMLIFrameElement)) {
        frame = getSharedPrintFrame();
        frame.id = 'order-print-frame';
    }

    printPdfBlobViaIframeSrc(frame, blobUrl, finish, {
        printDelayMs: 300,
        fallbackMs: 3500,
        directPdfPrint: true,
    });
}


global.KTPPrintIframe = {
  suppressParentPrintStyles,
  releaseParentPrintStyles,
  isParentPrintSuppressed,
  captureFocusTarget,
  restoreFocusTarget,
  getPrintOfficeName,
  escapeHtmlForPrint,
  buildPrintFooterFixedMarkup,
  buildPrintFooterEndMarkup,
  buildPrintFooterMarkup,
  isUnsafePrintHost,
  isSafariBrowser,
  isFirefoxBrowser,
  isIosBrowser,
  prefersPopupPrint,
  preparePrintPreviewWindow,
  prepareFirefoxPdfPrintWindow,
  prepareUnsafePrintPreviewWindow,
  dismissUnsafePrintPreviewWindow,
  openPrintPreviewTab,
  printHtmlInHiddenIframe,
  printPdfBlob,
  downloadPdfBlob,
  PRINT_FOOTER_STYLE_BLOCK
};

})(typeof window !== 'undefined' ? window : this);
