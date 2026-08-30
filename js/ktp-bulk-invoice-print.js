// 同梱ライブラリ（js/lib/）の URL を解決する。
// PHP 側の wp_localize_script( ..., 'ktpwpVendor', ... ) で注入される。
function ktpVendorUrl(name) {
    var vendor = window.ktpwpVendor || {};
    return vendor[name] || '';
}

/**
 * 一括請求書: プレビュー操作・印刷・PDF保存（KantanBiz bulk-invoice-print.js 相当）
 * @package KTPWP
 */
(function (global) {
'use strict';

var KTPPrintIframe = global.KTPPrintIframe || {};
var downloadPdfBlob = KTPPrintIframe.downloadPdfBlob;
var dismissUnsafePrintPreviewWindow = KTPPrintIframe.dismissUnsafePrintPreviewWindow;
var isFirefoxBrowser = KTPPrintIframe.isFirefoxBrowser;
var isSafariBrowser = KTPPrintIframe.isSafariBrowser;
var isUnsafePrintHost = KTPPrintIframe.isUnsafePrintHost;
var prepareFirefoxPdfPrintWindow = KTPPrintIframe.prepareFirefoxPdfPrintWindow;
var preparePrintPreviewWindow = KTPPrintIframe.preparePrintPreviewWindow;
var prefersPopupPrint = KTPPrintIframe.prefersPopupPrint;
var printHtmlInHiddenIframe = KTPPrintIframe.printHtmlInHiddenIframe;
var printPdfBlob = KTPPrintIframe.printPdfBlob;
var restoreFocusTarget = KTPPrintIframe.restoreFocusTarget;

function ktpwpTranslateBulk(msg) {
    return typeof global.ktpwpTranslate === 'function' ? global.ktpwpTranslate(msg) : msg;
}


function loadKtpPdfLibraries() {
    return new Promise(function(resolve, reject) {
        if (typeof html2canvas !== 'undefined' && typeof jsPDF !== 'undefined') {
            resolve();
            return;
        }
        var pending = 0;
        function done() {
            pending--;
            if (pending <= 0) {
                if (typeof window.jspdf !== 'undefined' && typeof window.jsPDF === 'undefined') {
                    window.jsPDF = window.jspdf.jsPDF;
                }
                if (typeof html2canvas !== 'undefined' && typeof jsPDF !== 'undefined') {
                    resolve();
                } else {
                    reject(new Error('PDFライブラリの読み込みに失敗しました'));
                }
            }
        }
        if (typeof html2canvas === 'undefined') {
            pending++;
            var s1 = document.createElement('script');
            s1.src = ktpVendorUrl('html2canvas');
            s1.onload = done;
            s1.onerror = function() { reject(new Error('html2canvas読み込み失敗')); };
            document.head.appendChild(s1);
        }
        if (typeof jsPDF === 'undefined') {
            pending++;
            var s2 = document.createElement('script');
            s2.src = ktpVendorUrl('jspdf');
            s2.onload = function() {
                if (typeof window.jspdf !== 'undefined') {
                    window.jsPDF = window.jspdf.jsPDF;
                }
                done();
            };
            s2.onerror = function() { reject(new Error('jsPDF読み込み失敗')); };
            document.head.appendChild(s2);
        }
        if (pending === 0) {
            resolve();
        }
    });
}

function bindKtpBulkInvoiceOutputButtons() {
    var printBtn = document.getElementById('ktp-invoice-print-btn');
    var pdfBtn = document.getElementById('ktp-invoice-pdf-btn');
    if (!(printBtn instanceof HTMLButtonElement) && !(pdfBtn instanceof HTMLButtonElement)) {
        return;
    }
    if (pdfBtn instanceof HTMLButtonElement && !pdfBtn.dataset.ktpBulkBound) {
        pdfBtn.dataset.ktpBulkBound = '1';
        pdfBtn.addEventListener('click', function() {
            if (bulkInvoiceOutputInProgress) { return; }
            pdfBtn.disabled = true;
            startBulkInvoicePdfSave(pdfBtn).finally(function() { pdfBtn.disabled = false; });
        });
    }
    if (printBtn instanceof HTMLButtonElement && !printBtn.dataset.ktpBulkBound) {
        printBtn.dataset.ktpBulkBound = '1';
        printBtn.addEventListener('click', function() {
            if (bulkInvoiceOutputInProgress) { return; }
            if (typeof window.ktpConfirmInvoicePrintWithProgress === 'function' && !window.ktpConfirmInvoicePrintWithProgress()) {
                return;
            }
            printBtn.disabled = true;
            startBulkInvoicePrint(printBtn).finally(function() { printBtn.disabled = false; });
        });
    }
}

/**
 * 一括請求書: プレビュー操作・印刷・PDF保存
 *
 * - pdf   … html2canvas + jsPDF で直接ダウンロード（印刷ダイアログなし）
 * - print … Chrome / Safari: PDF 経由。Firefox: PDF と同一のページ画像を印刷
 */


/** PCプレビュー相当の A4 コンテンツ幅（96dpi）。スマホ PDF の下限幅 */
const BULK_INVOICE_A4_WIDTH_PX = 794;

/**
 * 印刷・PDF の出力幅。画面プレビューの content-area 幅に合わせ印影位置・折り返しを一致させる。
 * スマホではプレビューが狭いため A4 相当幅を下限にする。
 *
 * @param {HTMLElement | null} source
 */
function resolveBulkInvoiceOutputWidth(source) {
    if (!(source instanceof HTMLElement)) {
        return BULK_INVOICE_A4_WIDTH_PX;
    }

    const measured = Math.round(source.getBoundingClientRect().width);

    if (measured <= 0) {
        return BULK_INVOICE_A4_WIDTH_PX;
    }

    if (window.innerWidth < 768) {
        return Math.max(measured, BULK_INVOICE_A4_WIDTH_PX);
    }

    return measured;
}

/**
 * html2canvas は @media print を使わないため、html.dark / dark: の画面用スタイルを明示的に無効化する
 */
const BULK_INVOICE_PDF_CAPTURE_LIGHT_CSS = `
html.ktp-bulk-invoice-pdf-output-active .ktp-bulk-invoice-pdf-capture-mount,
html.ktp-bulk-invoice-pdf-output-active .ktp-bulk-invoice-pdf-capture-mount .ktp-bulk-invoice-sheet,
html.ktp-bulk-invoice-pdf-output-active .ktp-bulk-invoice-pdf-capture-mount .ktp-bulk-invoice-content-area,
html.ktp-bulk-invoice-pdf-output-active .ktp-bulk-invoice-pdf-capture-mount .ktp-bulk-invoice-pdf-root,
html.dark .ktp-bulk-invoice-pdf-capture-mount,
html.dark .ktp-bulk-invoice-pdf-capture-mount .ktp-bulk-invoice-sheet,
html.dark .ktp-bulk-invoice-pdf-capture-mount .ktp-bulk-invoice-content-area,
html.dark .ktp-bulk-invoice-pdf-capture-mount .ktp-bulk-invoice-pdf-root {
    color: #111827 !important;
    color-scheme: light !important;
    background: #fff !important;
    background-color: #fff !important;
}
html.ktp-bulk-invoice-pdf-output-active .ktp-bulk-invoice-pdf-capture-mount .ktp-bulk-invoice-month-group,
html.ktp-bulk-invoice-pdf-output-active .ktp-bulk-invoice-pdf-capture-mount .ktp-bulk-invoice-order-card,
html.dark .ktp-bulk-invoice-pdf-capture-mount .ktp-bulk-invoice-month-group,
html.dark .ktp-bulk-invoice-pdf-capture-mount .ktp-bulk-invoice-order-card {
    background: #fff !important;
    background-color: #fff !important;
    border-color: #e5e7eb !important;
}
html.dark .ktp-bulk-invoice-pdf-capture-mount .ktp-bulk-invoice-order-group-header,
html.dark .ktp-bulk-invoice-pdf-capture-mount .ktp-bulk-invoice-order-group-footer {
    background: #f9fafb !important;
    background-color: #f9fafb !important;
    border-color: #e5e7eb !important;
    color: #075985 !important;
}
html.dark .ktp-bulk-invoice-pdf-capture-mount .ktp-bulk-invoice-order-card-header,
html.dark .ktp-bulk-invoice-pdf-capture-mount .ktp-bulk-invoice-order-card-footer {
    background: #fff !important;
    background-color: #fff !important;
    border-color: #e5e7eb !important;
    color: #111827 !important;
}
html.dark .ktp-bulk-invoice-pdf-capture-mount .ktp-bulk-invoice-order-card-header > div {
    color: #4b5563 !important;
}
html.dark .ktp-bulk-invoice-pdf-capture-mount .ktp-bulk-invoice-row-odd,
html.dark .ktp-bulk-invoice-pdf-capture-mount .ktp-bulk-invoice-row-odd td {
    background: #eff6ff !important;
    background-color: #eff6ff !important;
    color: #111827 !important;
}
html.dark .ktp-bulk-invoice-pdf-capture-mount .ktp-bulk-invoice-row-even,
html.dark .ktp-bulk-invoice-pdf-capture-mount .ktp-bulk-invoice-row-even td {
    background: #fff !important;
    background-color: #fff !important;
    color: #111827 !important;
}
html.dark .ktp-bulk-invoice-pdf-capture-mount .ktp-bulk-invoice-table-head,
html.dark .ktp-bulk-invoice-pdf-capture-mount .ktp-bulk-invoice-table-head th {
    background: #ffeef1 !important;
    background-color: #ffeef1 !important;
    color: #4b5563 !important;
    border-color: #fecdd3 !important;
}
html.dark .ktp-bulk-invoice-pdf-capture-mount .ktp-bulk-invoice-summary-box,
html.dark .ktp-bulk-invoice-pdf-capture-mount .ktp-bulk-invoice-amount-box,
html.dark .ktp-bulk-invoice-pdf-capture-mount .ktp-bulk-invoice-issuer-bank,
html.dark .ktp-bulk-invoice-pdf-capture-mount .ktp-bulk-invoice-issuer-bank * {
    background: #fff !important;
    background-color: #fff !important;
    color: #111827 !important;
    border-color: #e5e7eb !important;
}
html.dark .ktp-bulk-invoice-pdf-capture-mount .ktp-bulk-invoice-document-lead,
html.dark .ktp-bulk-invoice-pdf-capture-mount .ktp-bulk-invoice-addressee,
html.dark .ktp-bulk-invoice-pdf-capture-mount .ktp-bulk-invoice-issuer-inner,
html.dark .ktp-bulk-invoice-pdf-capture-mount .ktp-bulk-invoice-company-info,
html.dark .ktp-bulk-invoice-pdf-capture-mount .ktp-bulk-invoice-payment-due,
html.dark .ktp-bulk-invoice-pdf-capture-mount .ktp-bulk-invoice-payment-due span,
html.dark .ktp-bulk-invoice-pdf-capture-mount .text-gray-900,
html.dark .ktp-bulk-invoice-pdf-capture-mount .text-gray-800,
html.dark .ktp-bulk-invoice-pdf-capture-mount .text-gray-700,
html.dark .ktp-bulk-invoice-pdf-capture-mount .text-gray-600,
html.dark .ktp-bulk-invoice-pdf-capture-mount .dark\\:text-gray-100,
html.dark .ktp-bulk-invoice-pdf-capture-mount .dark\\:text-gray-200,
html.dark .ktp-bulk-invoice-pdf-capture-mount .dark\\:text-gray-300,
html.dark .ktp-bulk-invoice-pdf-capture-mount .dark\\:text-gray-400 {
    color: #374151 !important;
}
html.dark .ktp-bulk-invoice-pdf-capture-mount .text-sky-700,
html.dark .ktp-bulk-invoice-pdf-capture-mount .text-sky-800,
html.dark .ktp-bulk-invoice-pdf-capture-mount .dark\\:text-sky-200,
html.dark .ktp-bulk-invoice-pdf-capture-mount .ktp-bulk-invoice-amount-value {
    color: #0369a1 !important;
}
html.dark .ktp-bulk-invoice-pdf-capture-mount .text-blue-800,
html.dark .ktp-bulk-invoice-pdf-capture-mount .dark\\:text-blue-300,
html.dark .ktp-bulk-invoice-pdf-capture-mount .ktp-bulk-invoice-section-label {
    color: #1e40af !important;
}
html.dark .ktp-bulk-invoice-pdf-capture-mount .ktp-bulk-invoice-envelope-label {
    color: #2563eb !important;
}
html.dark .ktp-bulk-invoice-pdf-capture-mount .ktp-bulk-invoice-items-table td,
html.dark .ktp-bulk-invoice-pdf-capture-mount .ktp-bulk-invoice-items-table th,
html.dark .ktp-bulk-invoice-pdf-capture-mount .ktp-bulk-invoice-items-table .ktp-bulk-invoice-item-cell,
.ktp-bulk-invoice-pdf-capture-mount .ktp-bulk-invoice-items-table td,
.ktp-bulk-invoice-pdf-capture-mount .ktp-bulk-invoice-items-table th,
.ktp-bulk-invoice-pdf-capture-mount .ktp-bulk-invoice-items-table .ktp-bulk-invoice-item-cell {
    color: #111827 !important;
}
html.dark .ktp-bulk-invoice-pdf-capture-mount .ktp-bulk-invoice-items-table th {
    color: #4b5563 !important;
}
html.dark .ktp-bulk-invoice-pdf-capture-mount .ktp-bulk-invoice-issuer-inner,
html.dark .ktp-bulk-invoice-pdf-capture-mount .ktp-bulk-invoice-company-info,
html.dark .ktp-bulk-invoice-pdf-capture-mount .ktp-bulk-invoice-issuer-text-block,
html.dark .ktp-bulk-invoice-pdf-capture-mount .ktp-bulk-invoice-issuer-text-block > div,
html.dark .ktp-bulk-invoice-pdf-capture-mount .ktp-bulk-invoice-issuer-registration,
html.ktp-bulk-invoice-pdf-output-active .ktp-bulk-invoice-pdf-capture-mount .ktp-bulk-invoice-issuer-inner,
html.ktp-bulk-invoice-pdf-output-active .ktp-bulk-invoice-pdf-capture-mount .ktp-bulk-invoice-company-info,
html.ktp-bulk-invoice-pdf-output-active .ktp-bulk-invoice-pdf-capture-mount .ktp-bulk-invoice-issuer-text-block,
html.ktp-bulk-invoice-pdf-output-active .ktp-bulk-invoice-pdf-capture-mount .ktp-bulk-invoice-issuer-text-block > div,
html.ktp-bulk-invoice-pdf-output-active .ktp-bulk-invoice-pdf-capture-mount .ktp-bulk-invoice-issuer-registration {
    color: #374151 !important;
    -webkit-text-fill-color: #374151 !important;
}
html.dark .ktp-bulk-invoice-pdf-capture-mount .ktp-bulk-invoice-issuer-seal-scope {
    isolation: isolate !important;
    background: #fff !important;
}
html.dark .ktp-bulk-invoice-pdf-capture-mount .bg-gray-50,
html.dark .ktp-bulk-invoice-pdf-capture-mount .bg-white {
    background-color: #fff !important;
}
html.dark .ktp-bulk-invoice-pdf-capture-mount .ktp-bulk-invoice-order-group-header.bg-gray-50 {
    background-color: #f9fafb !important;
}
.ktp-bulk-invoice-pdf-capture-mount .ktp-bulk-invoice-issuer-seal-overlay {
    mix-blend-mode: multiply !important;
    background: transparent !important;
}
`;

/**
 * スマホ viewport 向け @media が PDF キャプチャに効いて自社ブロックが縮むのを防ぐ
 */
const BULK_INVOICE_PDF_CAPTURE_LAYOUT_FIX_CSS = `
@media screen {
    .ktp-bulk-invoice-pdf-capture-mount.ktp-bulk-invoice-pdf-save-mode .ktp-bulk-invoice-content-area.ktp-bulk-invoice-envelope-page,
    .ktp-bulk-invoice-pdf-capture-mount .ktp-bulk-invoice-pdf-save-mode .ktp-bulk-invoice-content-area.ktp-bulk-invoice-envelope-page {
        display: grid !important;
        grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) !important;
        grid-template-areas:
            "addressee issuer"
            "body body"
            "footer footer" !important;
        column-gap: 1rem !important;
        align-items: start !important;
        padding: 8mm 10mm 10mm 10mm !important;
        --bulk-issuer-pad-right: 6mm !important;
    }
    .ktp-bulk-invoice-pdf-capture-mount.ktp-bulk-invoice-pdf-save-mode .ktp-bulk-invoice-address-block,
    .ktp-bulk-invoice-pdf-capture-mount .ktp-bulk-invoice-pdf-save-mode .ktp-bulk-invoice-address-block {
        position: static !important;
        grid-area: addressee !important;
        max-width: none !important;
        margin: 0 !important;
    }
    .ktp-bulk-invoice-pdf-capture-mount.ktp-bulk-invoice-pdf-save-mode .ktp-bulk-invoice-issuer-stack,
    .ktp-bulk-invoice-pdf-capture-mount .ktp-bulk-invoice-pdf-save-mode .ktp-bulk-invoice-issuer-stack {
        position: static !important;
        grid-area: issuer !important;
        justify-self: stretch !important;
        width: 100% !important;
        max-width: 100% !important;
        min-width: 0 !important;
    }
    .ktp-bulk-invoice-pdf-capture-mount.ktp-bulk-invoice-pdf-save-mode .ktp-bulk-invoice-issuer-inner,
    .ktp-bulk-invoice-pdf-capture-mount .ktp-bulk-invoice-pdf-save-mode .ktp-bulk-invoice-issuer-inner {
        width: 90% !important;
        max-width: 90% !important;
        margin-left: auto !important;
    }
    .ktp-bulk-invoice-pdf-capture-mount.ktp-bulk-invoice-pdf-save-mode .ktp-bulk-invoice-print-except-addressee,
    .ktp-bulk-invoice-pdf-capture-mount .ktp-bulk-invoice-pdf-save-mode .ktp-bulk-invoice-print-except-addressee {
        grid-area: body !important;
    }
    .ktp-bulk-invoice-pdf-capture-mount.ktp-bulk-invoice-pdf-save-mode .ktp-bulk-invoice-envelope-footer,
    .ktp-bulk-invoice-pdf-capture-mount .ktp-bulk-invoice-pdf-save-mode .ktp-bulk-invoice-envelope-footer {
        grid-area: footer !important;
        grid-column: 1 / -1 !important;
    }
}
`;

let bulkInvoiceOutputInProgress = false;

function resolveHtml2canvasScale() {
    return 2;
}

function formatYen(value) {
    try {
        return new Intl.NumberFormat('ja-JP').format(value);
    } catch {
        return String(value);
    }
}

function sanitizeFilenamePart(value) {
    return String(value || '')
        .trim()
        .replace(/[\\/:*?"<>|]/g, '')
        .replace(/\s+/g, ' ')
        .slice(0, 80);
}

function formatClosingYmdForFilename(ymd) {
    const raw = String(ymd || '').replace(/\D/g, '');

    if (raw.length === 8) {
        return `${raw.slice(0, 4)}-${raw.slice(4, 6)}-${raw.slice(6, 8)}`;
    }

    return raw || new Date().toISOString().slice(0, 10);
}

function buildBulkInvoicePrintFilename(triggerBtn) {
    if (typeof window.ktpBuildBulkInvoiceOutputHtml === 'function') {
        var built = window.ktpBuildBulkInvoiceOutputHtml('pdf');
        if (built && built.filename) {
            return built.filename;
        }
    }
    var clientName = sanitizeFilenamePart(
        triggerBtn instanceof HTMLButtonElement ? triggerBtn.dataset.clientName || '' : '',
    ) || '請求先';
    var closingYmd = triggerBtn instanceof HTMLButtonElement ? triggerBtn.dataset.closingYmd || '' : '';
    return clientName + '-' + formatClosingYmdForFilename(closingYmd) + '-請求書.pdf';
}

function sanitizeCloneForPrint(root) {
    root.querySelectorAll('.ktp-bulk-invoice-no-print, script, style').forEach((el) => {
        el.remove();
    });

    root.querySelectorAll('input, textarea, select').forEach((el) => {
        const span = document.createElement('span');
        const tag = el.tagName.toLowerCase();

        if (tag === 'select') {
            span.textContent = el.options?.[el.selectedIndex]?.text ?? '';
        } else {
            span.textContent = el.value ?? '';
        }

        span.className = el.className.replace(/\bktp-bulk-invoice-carryover-input\b/g, '').trim()
            || 'print-replaced-field';
        span.classList.add('tabular-nums');

        if (el.classList.contains('ktp-bulk-invoice-carryover-input')) {
            span.classList.add('ktp-bulk-invoice-carryover-input');
        }

        el.replaceWith(span);
    });
}

function syncLiveCarryoverIntoClone(clone) {
    const live = document.getElementById('carryover-amount-input');
    const cloned = clone.querySelector('#carryover-amount');

    if (live instanceof HTMLInputElement && cloned instanceof HTMLInputElement) {
        cloned.value = live.value;
    }
}

/**
 * @param {BulkInvoiceOutputMode} mode
 */
function collectBulkInvoiceOutputStyles(mode) {
    const iframeStyles = document.getElementById('ktp-bulk-invoice-iframe-styles');
    const chunks = [];

    if (iframeStyles instanceof HTMLStyleElement && iframeStyles.textContent.trim()) {
        chunks.push(iframeStyles.textContent.trim());
    }

    if (mode === 'pdf') {
        const mirrorStyles = document.getElementById('ktp-bulk-invoice-pdf-mirror-styles');
        if (mirrorStyles instanceof HTMLStyleElement && mirrorStyles.textContent.trim()) {
            chunks.push(mirrorStyles.textContent.trim());
        }
    } else {
        const mirrorStyles = document.getElementById('ktp-bulk-invoice-preview-mirror-styles');
        if (mirrorStyles instanceof HTMLStyleElement && mirrorStyles.textContent.trim()) {
            chunks.push(mirrorStyles.textContent.trim());
        }
    }

    if (chunks.length > 0) {
        return chunks.join('\n');
    }

    const fallback = document.getElementById('ktp-bulk-invoice-print-styles');

    return fallback instanceof HTMLStyleElement ? fallback.textContent.trim() : '';
}

function shouldShowTaxAmountColumn() {
    const checkbox = document.getElementById('show-tax-amount-column');

    return checkbox instanceof HTMLInputElement && checkbox.checked;
}

/**
 * @param {BulkInvoiceOutputMode} mode
 */
function buildBulkInvoicePrintHtml(mode) {
    if (typeof window.ktpBuildBulkInvoiceOutputHtml === 'function') {
        var built = window.ktpBuildBulkInvoiceOutputHtml(mode || 'pdf');
        return built && built.html ? built.html : null;
    }
    return null;
}

async function waitForElementImages(mountRoot) {
    if (!(mountRoot instanceof HTMLElement)) {
        return;
    }

    const images = Array.from(mountRoot.querySelectorAll('img'));
    const pending = images.filter((img) => !img.complete);

    if (pending.length === 0) {
        return;
    }

    await Promise.allSettled(
        pending.map(
            (img) =>
                new Promise((resolve) => {
                    img.addEventListener('load', resolve, { once: true });
                    img.addEventListener('error', resolve, { once: true });
                    window.setTimeout(resolve, 8000);
                }),
        ),
    );
}

/**
 * PDF キャプチャ時、グリッド1行目の高さを自社ブロック（issuer）基準で確保する。
 * 宛名列より issuer が高いときに本文が上へ被さり下端が切れるのを防ぐ。
 *
 * @param {HTMLElement} root
 */
function ensureBulkInvoiceHeaderGridRowHeight(root) {
    const contentArea = root.querySelector('.ktp-bulk-invoice-content-area.ktp-bulk-invoice-envelope-page');
    const issuer = root.querySelector('.ktp-bulk-invoice-issuer-stack');
    const addressee = root.querySelector('.ktp-bulk-invoice-address-block');

    if (!(contentArea instanceof HTMLElement) || !(issuer instanceof HTMLElement)) {
        return;
    }

    issuer.style.setProperty('position', 'static', 'important');
    issuer.style.setProperty('grid-area', 'issuer', 'important');
    contentArea.style.setProperty('overflow', 'visible', 'important');
    contentArea.style.setProperty('align-items', 'start', 'important');

    const contentTop = contentArea.getBoundingClientRect().top;
    const issuerBottom = issuer.getBoundingClientRect().bottom;
    const addresseeBottom = addressee instanceof HTMLElement
        ? addressee.getBoundingClientRect().bottom
        : contentTop;
    const headerRowHeight = Math.max(issuerBottom, addresseeBottom) - contentTop;

    if (headerRowHeight > 0) {
        const rowBufferPx = 6;
        contentArea.style.gridTemplateRows = `minmax(${Math.ceil(headerRowHeight) + rowBufferPx}px, auto) auto`;
    }
}

/**
 * PDF キャプチャ時、タイトル画像の表示サイズを画面プレビューと揃える
 *
 * @param {HTMLElement} target
 */
function syncIssuerDocTitleImageForPdfCapture(target) {
    target.querySelectorAll('.ktp-bulk-invoice-issuer-doc-title-img').forEach((img) => {
        if (!(img instanceof HTMLImageElement)) {
            return;
        }

        img.style.width = '100%';
        img.style.height = 'auto';
        img.style.maxWidth = '100%';
        img.style.objectFit = 'contain';
    });
}

/**
 * プレビュー DOM の計測値をキャプチャ対象へ同期（ロゴ余白・印影位置のずれを防ぐ）
 *
 * @param {HTMLElement|null} liveRoot
 * @param {HTMLElement|null} targetRoot
 */
function syncBulkInvoiceIssuerLayoutForPdfCapture(liveRoot, targetRoot) {
    if (!(liveRoot instanceof HTMLElement) || !(targetRoot instanceof HTMLElement)) {
        return;
    }

    var liveLogoWrap = liveRoot.querySelector('.ktp-bulk-invoice-issuer-logo-wrap');
    var targetLogoWrap = targetRoot.querySelector('.ktp-bulk-invoice-issuer-logo-wrap');
    if (liveLogoWrap instanceof HTMLElement && targetLogoWrap instanceof HTMLElement) {
        var logoWrapWidth = Math.round(liveLogoWrap.getBoundingClientRect().width);
        if (logoWrapWidth > 0) {
            targetLogoWrap.style.setProperty('width', logoWrapWidth + 'px', 'important');
            targetLogoWrap.style.setProperty('min-width', logoWrapWidth + 'px', 'important');
            targetLogoWrap.style.setProperty('overflow', 'visible', 'important');
        }
        var liveLogoImg = liveLogoWrap.querySelector('.ktp-bulk-invoice-issuer-logo-img');
        var targetLogoImg = targetLogoWrap.querySelector('.ktp-bulk-invoice-issuer-logo-img');
        if (liveLogoImg instanceof HTMLImageElement && targetLogoImg instanceof HTMLImageElement) {
            var logoRect = liveLogoImg.getBoundingClientRect();
            if (logoRect.width > 0) {
                targetLogoImg.style.setProperty('width', Math.round(logoRect.width) + 'px', 'important');
                targetLogoImg.style.setProperty('max-width', '100%', 'important');
                targetLogoImg.style.setProperty('height', 'auto', 'important');
                targetLogoImg.style.setProperty('display', 'block', 'important');
                targetLogoImg.style.setProperty('object-fit', 'contain', 'important');
                targetLogoImg.style.setProperty('object-position', 'left center', 'important');
            }
        }
    }

    var liveDocTitle = liveRoot.querySelector('.ktp-bulk-invoice-issuer-doc-title');
    var targetDocTitle = targetRoot.querySelector('.ktp-bulk-invoice-issuer-doc-title');
    if (liveDocTitle instanceof HTMLElement && targetDocTitle instanceof HTMLElement) {
        var titleWidth = Math.round(liveDocTitle.getBoundingClientRect().width);
        if (titleWidth > 0) {
            targetDocTitle.style.setProperty('display', 'flex', 'important');
            targetDocTitle.style.setProperty('width', titleWidth + 'px', 'important');
            targetDocTitle.style.setProperty('min-width', titleWidth + 'px', 'important');
            targetDocTitle.style.setProperty('align-items', 'center', 'important');
            targetDocTitle.style.setProperty('justify-content', 'space-between', 'important');
        }
    }

    var liveSealScope = liveRoot.querySelector('.ktp-bulk-invoice-issuer-seal-scope');
    var targetSealScope = targetRoot.querySelector('.ktp-bulk-invoice-issuer-seal-scope');
    if (liveSealScope instanceof HTMLElement && targetSealScope instanceof HTMLElement) {
        targetSealScope.style.setProperty('position', 'relative', 'important');
        targetSealScope.style.setProperty('overflow', 'visible', 'important');
        targetSealScope.style.setProperty('background', '#fff', 'important');
    }

    var liveSeal = liveRoot.querySelector('.ktp-bulk-invoice-issuer-seal-overlay');
    var targetSeal = targetRoot.querySelector('.ktp-bulk-invoice-issuer-seal-overlay');
    if (liveSeal instanceof HTMLImageElement && targetSeal instanceof HTMLImageElement && liveSealScope instanceof HTMLElement) {
        var liveCs = window.getComputedStyle(liveSeal);
        var scopeRect = liveSealScope.getBoundingClientRect();
        var sealRect = liveSeal.getBoundingClientRect();
        targetSeal.style.setProperty('position', 'absolute', 'important');
        targetSeal.style.setProperty('right', '-0.4em', 'important');
        targetSeal.style.setProperty('top', '-0.5em', 'important');
        targetSeal.style.setProperty('z-index', '3', 'important');
        targetSeal.style.setProperty('pointer-events', 'none', 'important');
        targetSeal.style.setProperty('background', 'transparent', 'important');
        targetSeal.style.setProperty('mix-blend-mode', 'multiply', 'important');
        targetSeal.style.setProperty('opacity', liveCs.opacity || '0.75', 'important');
        targetSeal.style.setProperty('object-fit', 'contain', 'important');
        if (liveCs.maxWidth && liveCs.maxWidth !== 'none') {
            targetSeal.style.setProperty('max-width', liveCs.maxWidth, 'important');
        }
        if (liveCs.maxHeight && liveCs.maxHeight !== 'none') {
            targetSeal.style.setProperty('max-height', liveCs.maxHeight, 'important');
        }
        if (sealRect.width > 0 && scopeRect.width > 0) {
            targetSeal.style.setProperty('width', Math.round(sealRect.width) + 'px', 'important');
            targetSeal.style.setProperty('height', Math.round(sealRect.height) + 'px', 'important');
        }
    }
}

/**
 * Safari の html2canvas で自社テキストが消えることがあるため、キャプチャ直前に色を固定する
 *
 * @param {HTMLElement} target
 */
function syncBulkInvoiceIssuerTextForPdfCapture(target) {
    if (!isSafariBrowser()) {
        return;
    }

    const issuerColor = '#374151';
    const selectors = [
        '.ktp-bulk-invoice-issuer-inner',
        '.ktp-bulk-invoice-issuer-text-block',
        '.ktp-bulk-invoice-issuer-text-block > div',
        '.ktp-bulk-invoice-issuer-registration',
        '.ktp-bulk-invoice-issuer-bank',
    ];

    selectors.forEach((selector) => {
        target.querySelectorAll(selector).forEach((el) => {
            if (!(el instanceof HTMLElement)) {
                return;
            }

            el.style.setProperty('color', issuerColor, 'important');
            el.style.setProperty('-webkit-text-fill-color', issuerColor, 'important');
        });
    });
}

const BULK_INVOICE_CAPTURE_ROOT_SELECTOR = '#ktp-bulk-invoice-pdf-capture-root';

/**
 * キャプチャ用 CSS から親ページの html/body へ漏れるセレクタだけ除去する
 * （全セレクタの prefix は壊れやすいため、漏洩箇所のみ最小修正）
 *
 * @param {string} cssText
 */
function sanitizeBulkInvoiceCaptureStyles(cssText) {
    const root = BULK_INVOICE_CAPTURE_ROOT_SELECTOR;

    return cssText
        .replace(/\bhtml\s*,\s*body\.ktp-bulk-invoice-print-document\b/g, `${root}.ktp-bulk-invoice-print-document`)
        .replace(/(^|[},]\s*)html\s*,\s*/g, '$1')
        .replace(
            /html\.(dark|ktp-bulk-invoice-pdf-output-active)\s+\.ktp-bulk-invoice-pdf-capture-mount\b/g,
            (match, cls) => `html.${cls} ${root}.ktp-bulk-invoice-pdf-capture-mount`,
        );
}

/**
 * PDF/印刷中のみスクロールバー変動を抑える（プレビュー要素のサイズは変更しない）
 *
 * @returns {() => void}
 */
function beginBulkInvoiceOutput() {
    const html = document.documentElement;
    const body = document.body;
    const lockedWidth = `${html.clientWidth}px`;
    const previous = {
        htmlOverflow: html.style.overflow,
        bodyOverflow: body.style.overflow,
        htmlWidth: html.style.width,
        htmlMaxWidth: html.style.maxWidth,
        scrollX: window.scrollX,
        scrollY: window.scrollY,
    };

    html.classList.add('ktp-bulk-invoice-pdf-output-active');
    html.style.overflow = 'hidden';
    body.style.overflow = 'hidden';
    html.style.width = lockedWidth;
    html.style.maxWidth = lockedWidth;
    window.scrollTo(previous.scrollX, previous.scrollY);

    return () => {
        html.classList.remove('ktp-bulk-invoice-pdf-output-active');
        html.style.overflow = previous.htmlOverflow;
        body.style.overflow = previous.bodyOverflow;
        html.style.width = previous.htmlWidth;
        html.style.maxWidth = previous.htmlMaxWidth;
        window.scrollTo(previous.scrollX, previous.scrollY);
    };
}

/**
 * PDF キャプチャ対象の実高さ（2ページ目以降が切れないよう子要素下端まで計測）
 *
 * @param {HTMLElement} target
 */
function measureBulkInvoicePdfCaptureHeight(target) {
    const measured = [
        target.scrollHeight,
        target.offsetHeight,
        Math.ceil(target.getBoundingClientRect().height),
    ];
    const rootTop = target.getBoundingClientRect().top;

    target.querySelectorAll(
        '.ktp-bulk-invoice-content-area, .ktp-bulk-invoice-order-groups, .ktp-bulk-invoice-envelope-footer, .ktp-bulk-invoice-summary-box',
    ).forEach((el) => {
        if (!(el instanceof HTMLElement)) {
            return;
        }

        measured.push(el.scrollHeight + el.offsetTop);
        measured.push(Math.ceil(el.getBoundingClientRect().bottom - rootTop));
    });

    return Math.max(400, ...measured) + 16;
}

/**
 * PDF キャプチャ用 HTML を画面外シェルへマウントする
 * - 1px クリップは Safari / スマホで高さ計測・html2canvas が1ページ分で止まる原因になる
 * - left:-12000px で本ページの横スクロールは広げない
 *
 * @param {number} previewWidth
 * @returns {{ shell: string, mount: string }}
 */
function buildBulkInvoicePdfCaptureShellLayout(previewWidth) {
    const useOffScreenShell = window.innerWidth < 768 || isSafariBrowser();

    if (useOffScreenShell) {
        return {
            shell: [
                'position:fixed',
                'left:-12000px',
                'top:0',
                `width:${previewWidth}px`,
                'height:auto',
                'overflow:visible',
                'pointer-events:none',
                'z-index:-1',
                'opacity:0',
            ].join(';'),
            mount: [
                'position:relative',
                'left:0',
                'top:0',
                `width:${previewWidth}px`,
                'max-width:none',
                'opacity:1',
                'overflow:visible',
                'background:#fff',
                'color-scheme:light',
            ].join(';'),
        };
    }

    const shellClipSize = '0';

    return {
        shell: [
            'position:fixed',
            'left:0',
            'top:0',
            `width:${shellClipSize}`,
            `height:${shellClipSize}`,
            'overflow:hidden',
            'pointer-events:none',
            'z-index:-1',
        ].join(';'),
        mount: [
            'position:absolute',
            'left:0',
            'top:0',
            `width:${previewWidth}px`,
            'max-width:none',
            'opacity:1',
            'overflow:visible',
            'background:#fff',
            'color-scheme:light',
        ].join(';'),
    };
}

/**
 * @param {string} html
 * @param {number} previewWidth
 * @returns {{ mount: HTMLElement, target: HTMLElement }}
 */
function mountBulkInvoicePdfHtml(html, previewWidth) {
    const parsed = new DOMParser().parseFromString(html, 'text/html');
    const shellLayout = buildBulkInvoicePdfCaptureShellLayout(previewWidth);

    const shell = document.createElement('div');
    shell.className = 'ktp-bulk-invoice-pdf-capture-shell';
    shell.setAttribute('aria-hidden', 'true');
    shell.style.cssText = shellLayout.shell;

    const mount = document.createElement('div');
    mount.id = 'ktp-bulk-invoice-pdf-capture-root';
    mount.setAttribute('aria-hidden', 'true');
    mount.className = `${parsed.body.className || 'ktp-bulk-invoice-print-document ktp-bulk-invoice-pdf-save-mode'} ktp-bulk-invoice-pdf-capture-mount`.trim();
    mount.style.cssText = shellLayout.mount;

    parsed.head.querySelectorAll('style').forEach((styleEl) => {
        const style = document.createElement('style');
        style.textContent = sanitizeBulkInvoiceCaptureStyles(styleEl.textContent.trim());
        mount.appendChild(style);
    });

    const lightStyle = document.createElement('style');
    lightStyle.textContent = sanitizeBulkInvoiceCaptureStyles(BULK_INVOICE_PDF_CAPTURE_LIGHT_CSS);
    mount.appendChild(lightStyle);

    const layoutFixStyle = document.createElement('style');
    layoutFixStyle.textContent = sanitizeBulkInvoiceCaptureStyles(BULK_INVOICE_PDF_CAPTURE_LAYOUT_FIX_CSS);
    mount.appendChild(layoutFixStyle);

    const root = parsed.body.querySelector('.ktp-bulk-invoice-pdf-root');
    if (!(root instanceof HTMLElement)) {
        throw new Error('PDF化対象が見つかりません');
    }

    mount.appendChild(document.importNode(root, true));
    shell.appendChild(mount);
    document.body.appendChild(shell);

    const target = mount.querySelector('.ktp-bulk-invoice-pdf-root');
    if (!(target instanceof HTMLElement)) {
        throw new Error('PDF化対象が見つかりません');
    }

    return { mount: shell, target };
}

const PDF_PAGE_BREAK_SELECTORS = [
    '.ktp-bulk-invoice-summary-box',
    '.ktp-bulk-invoice-order-group-header',
    '.ktp-bulk-invoice-order-group-footer',
    '.ktp-bulk-invoice-section-label',
    '.ktp-bulk-invoice-order-card',
    '.ktp-bulk-invoice-order-groups > .ktp-bulk-invoice-month-group > .space-y-4 > .rounded-md:not(.ktp-bulk-invoice-order-card)',
].join(', ');

/**
 * @param {HTMLElement} container
 * @returns {Array<{ top: number, bottom: number, height: number }>}
 */
function collectPdfBreakBlocks(container) {
    const containerRect = container.getBoundingClientRect();
    const scrollTop = container.scrollTop || 0;
    /** @type {Array<{ top: number, bottom: number, height: number }>} */
    const blocks = [];

    container.querySelectorAll(PDF_PAGE_BREAK_SELECTORS).forEach((el) => {
        if (!(el instanceof HTMLElement)) {
            return;
        }

        const rect = el.getBoundingClientRect();
        const top = rect.top - containerRect.top + scrollTop;
        const height = rect.height;

        if (height <= 0) {
            return;
        }

        blocks.push({ top, bottom: top + height, height });
    });

    return blocks.sort((a, b) => a.top - b.top);
}

/**
 * @param {number} totalCssHeight
 * @param {number} pageCssHeight
 * @param {Array<{ top: number, bottom: number, height: number }>} blocks
 * @returns {number[]}
 */
function computePdfSliceEnds(totalCssHeight, pageCssHeight, blocks) {
    /** @type {number[]} */
    const ends = [];
    let pageStart = 0;
    const minSlice = pageCssHeight * 0.12;

    while (pageStart < totalCssHeight - 1) {
        let pageEnd = Math.min(pageStart + pageCssHeight, totalCssHeight);

        if (pageEnd >= totalCssHeight - 1) {
            ends.push(totalCssHeight);
            break;
        }

        let adjusted = pageEnd;

        for (const block of blocks) {
            if (block.top < pageEnd && block.bottom > pageEnd && block.top > pageStart + minSlice) {
                adjusted = block.top;
                break;
            }
        }

        if (adjusted <= pageStart + minSlice) {
            const straddling = blocks.find((block) => block.top < pageEnd && block.bottom > pageEnd);

            if (straddling && straddling.height > pageCssHeight * 0.95) {
                adjusted = pageEnd;
            } else if (straddling && straddling.bottom - pageStart <= pageCssHeight * 1.05) {
                adjusted = Math.min(straddling.bottom, totalCssHeight);
            } else {
                adjusted = pageEnd;
            }
        }

        ends.push(adjusted);
        pageStart = adjusted;
    }

    if (ends.length === 0 || ends[ends.length - 1] < totalCssHeight) {
        ends.push(totalCssHeight);
    }

    return ends;
}

/**
 * @param {HTMLElement} target
 * @param {number} previewWidth
 * @param {{ filename?: string, output?: 'save' | 'blob' | 'rendered' }} [options]
 * @returns {Promise<Blob | void | { blob: Blob, pageImages: string[] }>}
 */
async function renderBulkInvoiceElementToPdf(target, previewWidth, options = {}) {
    const { filename = '請求書.pdf', output = 'save' } = options;
    await loadKtpPdfLibraries();
    var html2canvas = window.html2canvas;
    var jsPDF = window.jsPDF;
    if (typeof html2canvas !== 'function' || typeof jsPDF !== 'function') {
        throw new Error('PDFライブラリの読み込みに失敗しました');
    }

    const layoutCssHeight = measureBulkInvoicePdfCaptureHeight(target);
    const scale = resolveHtml2canvasScale();
    const safariCapture = isSafariBrowser();

    const canvas = await html2canvas(target, {
        scale,
        useCORS: true,
        allowTaint: true,
        backgroundColor: '#ffffff',
        logging: false,
        width: previewWidth,
        height: layoutCssHeight,
        windowWidth: previewWidth,
        windowHeight: layoutCssHeight,
        scrollX: 0,
        scrollY: 0,
        ...(safariCapture ? { foreignObjectRendering: false } : {}),
        onclone: (clonedDoc, clonedElement) => {
            const clonedMount = clonedDoc.querySelector('.ktp-bulk-invoice-pdf-capture-mount')
                ?? clonedDoc.querySelector('.ktp-bulk-invoice-pdf-root')?.parentElement;

            if (clonedMount instanceof HTMLElement) {
                clonedMount.classList.add('ktp-bulk-invoice-pdf-capture-mount');
                const style = clonedDoc.createElement('style');
                style.textContent = sanitizeBulkInvoiceCaptureStyles(BULK_INVOICE_PDF_CAPTURE_LIGHT_CSS);
                clonedMount.appendChild(style);

                const layoutStyle = clonedDoc.createElement('style');
                layoutStyle.textContent = sanitizeBulkInvoiceCaptureStyles(BULK_INVOICE_PDF_CAPTURE_LAYOUT_FIX_CSS);
                clonedMount.appendChild(layoutStyle);
            }

            const clonedTarget = clonedElement instanceof HTMLElement
                ? clonedElement
                : clonedMount instanceof HTMLElement
                    ? clonedMount.querySelector('.ktp-bulk-invoice-pdf-root')
                    : null;

            if (clonedTarget instanceof HTMLElement) {
                clonedTarget.style.minHeight = `${layoutCssHeight}px`;
                syncBulkInvoiceIssuerTextForPdfCapture(clonedTarget);
                syncIssuerDocTitleImageForPdfCapture(clonedTarget);
                var liveSource = document.querySelector('#invoiceList .ktp-bulk-invoice-content-area.ktp-bulk-invoice-envelope-page');
                var clonedContent = clonedTarget.querySelector('.ktp-bulk-invoice-content-area.ktp-bulk-invoice-envelope-page') || clonedTarget;
                syncBulkInvoiceIssuerLayoutForPdfCapture(liveSource instanceof HTMLElement ? liveSource : null, clonedContent instanceof HTMLElement ? clonedContent : null);
            }
        },
    });

    if (canvas.width <= 0 || canvas.height <= 0) {
        throw new Error(`PDFキャプチャのサイズが不正です (${canvas.width}x${canvas.height})`);
    }

    const capturedCssHeight = canvas.height / scale;

    if (layoutCssHeight > capturedCssHeight + 24) {
        console.warn('[一括請求書] PDFキャプチャ高さが不足している可能性があります', {
            layoutCssHeight,
            capturedCssHeight,
        });
    }

    const pdfFilename = filename.endsWith('.pdf') ? filename : `${filename}.pdf`;
    const pdf = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' });
    const pageWidth = pdf.internal.pageSize.getWidth();
    const pageHeight = pdf.internal.pageSize.getHeight();
    const margin = 10;
    const contentWidthMm = pageWidth - margin * 2;
    const contentHeightMm = pageHeight - margin * 2;
    const imgWidthMm = contentWidthMm;
    const imgHeightMm = (canvas.height / canvas.width) * imgWidthMm;
    const pageCssHeight = capturedCssHeight * (contentHeightMm / imgHeightMm);
    const blocks = collectPdfBreakBlocks(target);
    const sliceEnds = computePdfSliceEnds(capturedCssHeight, pageCssHeight, blocks);

    let sliceStart = 0;
    /** @type {string[]} */
    const pageImages = [];

    for (let pageIndex = 0; pageIndex < sliceEnds.length; pageIndex++) {
        const sliceEnd = sliceEnds[pageIndex];
        const sliceCssHeight = sliceEnd - sliceStart;

        if (sliceCssHeight <= 0) {
            continue;
        }

        const sliceCanvasTop = Math.round(sliceStart * scale);
        const sliceCanvasHeight = Math.max(1, Math.round(sliceCssHeight * scale));
        const pageCanvas = document.createElement('canvas');
        pageCanvas.width = canvas.width;
        pageCanvas.height = sliceCanvasHeight;
        const ctx = pageCanvas.getContext('2d');

        if (!ctx) {
            continue;
        }

        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, pageCanvas.width, pageCanvas.height);
        ctx.drawImage(
            canvas,
            0,
            sliceCanvasTop,
            canvas.width,
            sliceCanvasHeight,
            0,
            0,
            canvas.width,
            sliceCanvasHeight,
        );

        const sliceImg = pageCanvas.toDataURL('image/jpeg', 0.95);
        pageImages.push(sliceImg);
        const sliceHeightMm = (sliceCssHeight / capturedCssHeight) * imgHeightMm;

        if (pageIndex > 0) {
            pdf.addPage();
        }

        pdf.addImage(sliceImg, 'JPEG', margin, margin, imgWidthMm, sliceHeightMm);
        sliceStart = sliceEnd;
    }

    const pdfBlob = pdf.output('blob');

    if (output === 'rendered') {
        return { blob: pdfBlob, pageImages };
    }

    if (output === 'blob') {
        return pdfBlob;
    }

    if (typeof downloadPdfBlob !== 'function') {
        throw new Error('PDFダウンロード機能が読み込まれていません');
    }

    await downloadPdfBlob(pdfBlob, pdfFilename);
}

/**
 * PDF と同一内容のページ画像から印刷用 HTML を組み立てる
 *
 * @param {string[]} pageImages
 * @returns {string}
 */
function buildBulkInvoicePageImagesPrintHtml(pageImages) {
    const pages = pageImages
        .map((src) => `<div class="page"><img src="${src}" alt=""></div>`)
        .join('');

    return `<!DOCTYPE html><html lang="ja"><head><meta charset="utf-8"><title>請求書</title>
<style>
@page { size: A4 portrait; margin: 0; }
html, body { margin: 0; padding: 0; background: #fff; }
.page { width: 210mm; min-height: 297mm; page-break-after: always; background: #fff; }
.page:last-child { page-break-after: auto; }
.page img { width: 100%; height: auto; display: block; }
@media print {
  html, body { background: #fff !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  .page { page-break-after: always; }
  .page:last-child { page-break-after: auto; }
}
</style></head><body>${pages}</body></html>`;
}

/**
 * Firefox: blob PDF の print() が使えないため、同一レンダリングのページ画像を印刷する
 *
 * @param {string[]} pageImages
 * @param {Window | null} previewWindow
 * @param {HTMLButtonElement} triggerBtn
 * @param {string} filename
 * @param {() => void} onAfterPrint
 * @returns {boolean}
 */
function printBulkInvoicePageImagesInFirefox(
    pageImages,
    previewWindow,
    triggerBtn,
    filename,
    onAfterPrint,
) {
    if (pageImages.length === 0) {
        window.alert('印刷対象の請求書プレビューが見つかりません。');

        return false;
    }

    const html = buildBulkInvoicePageImagesPrintHtml(pageImages);
    const finish = () => {
        restoreFocusTarget(triggerBtn);
        onAfterPrint();
    };

    if (previewWindow instanceof Window && !previewWindow.closed) {
        try {
            previewWindow.document.open();
            previewWindow.document.write(html);
            previewWindow.document.close();

            let done = false;
            const settle = () => {
                if (done) {
                    return;
                }

                done = true;
                dismissUnsafePrintPreviewWindow(previewWindow);
                finish();
            };

            previewWindow.addEventListener('afterprint', settle, { once: true });
            window.setTimeout(() => {
                try {
                    previewWindow.focus();
                    previewWindow.print();
                } catch {
                    settle();
                    window.alert('印刷ダイアログを開けませんでした。');
                }
            }, 500);
            window.setTimeout(settle, 180000);

            return true;
        } catch {
            dismissUnsafePrintPreviewWindow(previewWindow);
        }
    }

    const started = printHtmlInHiddenIframe({
        html,
        filename,
        restoreFocusTo: triggerBtn,
        printDelayMs: 400,
        fallbackCleanupMs: 60000,
        onCleanup: finish,
    });

    if (!started) {
        window.alert('印刷処理が既に実行中です。しばらく待ってから再度お試しください。');

        return false;
    }

    return true;
}

/**
 * @param {string} html
 * @param {number} previewWidth
 * @returns {Promise<{ mount: HTMLElement, target: HTMLElement }>}
 */
async function prepareBulkInvoicePdfTarget(html, previewWidth) {
    const { mount, target } = mountBulkInvoicePdfHtml(html, previewWidth);

    try {
        await document.fonts?.ready;
    } catch {
        /* ignore */
    }

    await waitForElementImages(mount);
    await new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve)));
    const settleMs = window.innerWidth < 768 ? 300 : (isSafariBrowser() ? 400 : 150);
    await new Promise((resolve) => window.setTimeout(resolve, settleMs));

    syncIssuerDocTitleImageForPdfCapture(target);
    syncBulkInvoiceIssuerTextForPdfCapture(target);
    ensureBulkInvoiceHeaderGridRowHeight(target);

    var liveSource = document.querySelector('#invoiceList .ktp-bulk-invoice-content-area.ktp-bulk-invoice-envelope-page');
    var targetContent = target.querySelector('.ktp-bulk-invoice-content-area.ktp-bulk-invoice-envelope-page') || target;
    syncBulkInvoiceIssuerLayoutForPdfCapture(
        liveSource instanceof HTMLElement ? liveSource : null,
        targetContent instanceof HTMLElement ? targetContent : null,
    );

    return { mount, target };
}

/**
 * プレビュー通りの HTML を PDF ファイルとして直接ダウンロードする（印刷ダイアログなし）
 *
 * @param {string} html
 * @param {string} filename
 * @param {HTMLElement | null} restoreFocusTo
 */
async function downloadBulkInvoiceAsPdf(html, filename, restoreFocusTo) {
    if (bulkInvoiceOutputInProgress) {
        window.alert('PDF生成中です。しばらくお待ちください。');

        return false;
    }

    bulkInvoiceOutputInProgress = true;
    const endBulkInvoiceOutput = beginBulkInvoiceOutput();

    const source = document.querySelector('.ktp-bulk-invoice-content-area.ktp-bulk-invoice-envelope-page');
    const previewWidth = resolveBulkInvoiceOutputWidth(
        source instanceof HTMLElement ? source : null,
    );

    let mount = null;

    try {
        const prepared = await prepareBulkInvoicePdfTarget(html, previewWidth);
        mount = prepared.mount;

        await renderBulkInvoiceElementToPdf(prepared.target, previewWidth, { filename, output: 'save' });

        restoreFocusTarget(restoreFocusTo);

        return true;
    } catch (error) {
        console.error('[一括請求書] PDF保存に失敗:', error);
        window.alert('PDFの保存に失敗しました。時間をおいて再度お試しください。');

        return false;
    } finally {
        bulkInvoiceOutputInProgress = false;
        endBulkInvoiceOutput();

        if (mount instanceof HTMLElement) {
            try {
                document.body.removeChild(mount);
            } catch {
                /* ignore */
            }
        }
    }
}

/**
 * PDF保存と同じレンダリング結果を印刷ダイアログで出力する（全ブラウザ）
 *
 * @param {HTMLButtonElement} triggerBtn
 */
async function startBulkInvoicePrint(triggerBtn) {
    if (bulkInvoiceOutputInProgress) {
        window.alert('印刷処理が既に実行中です。しばらく待ってから再度お試しください。');

        return false;
    }

    const html = buildBulkInvoicePrintHtml('pdf');
    if (!html) {
        window.alert('印刷対象の請求書プレビューが見つかりません。');

        return false;
    }

    bulkInvoiceOutputInProgress = true;
    const endBulkInvoiceOutput = beginBulkInvoiceOutput();

    const source = document.querySelector('.ktp-bulk-invoice-content-area.ktp-bulk-invoice-envelope-page');
    const previewWidth = resolveBulkInvoiceOutputWidth(
        source instanceof HTMLElement ? source : null,
    );
    const filename = buildBulkInvoicePrintFilename(triggerBtn);
    let previewWindow = null;
    if (isFirefoxBrowser() && !isUnsafePrintHost()) {
        previewWindow = prepareFirefoxPdfPrintWindow();
    } else if (prefersPopupPrint()) {
        previewWindow = preparePrintPreviewWindow();
    }
    var setInvoiceCompleted = document.getElementById('set-invoice-completed');
        var markInvoicedAfter = !!(setInvoiceCompleted && setInvoiceCompleted.checked);
        var submitted = false;

    let mount = null;

    try {
        const prepared = await prepareBulkInvoicePdfTarget(html, previewWidth);
        mount = prepared.mount;

        const useFirefoxPagePrint = isFirefoxBrowser() && !isUnsafePrintHost();
        const rendered = await renderBulkInvoiceElementToPdf(prepared.target, previewWidth, {
            filename,
            output: useFirefoxPagePrint ? 'rendered' : 'blob',
        });

        const afterPrint = () => {
            if (!markInvoicedAfter || submitted) { return; }
            submitted = true;
            if (typeof window.ktpRunInvoiceCompletedAfterPrint === 'function') {
                window.ktpRunInvoiceCompletedAfterPrint();
            }
        };

        if (useFirefoxPagePrint) {
            if (
                !rendered
                || typeof rendered !== 'object'
                || !('pageImages' in rendered)
                || !Array.isArray(rendered.pageImages)
            ) {
                throw new Error('印刷用データの生成に失敗しました');
            }

            printBulkInvoicePageImagesInFirefox(
                rendered.pageImages,
                previewWindow,
                triggerBtn,
                filename,
                afterPrint,
            );

            return true;
        }

        if (!(rendered instanceof Blob)) {
            throw new Error('印刷用PDFの生成に失敗しました');
        }

        printPdfBlob(rendered, triggerBtn, previewWindow, afterPrint);

        return true;
    } catch (error) {
        dismissUnsafePrintPreviewWindow(previewWindow instanceof Window ? previewWindow : null);
        console.error('[一括請求書] 印刷に失敗:', error);
        window.alert('印刷を開始できませんでした。時間をおいて再度お試しください。');

        return false;
    } finally {
        bulkInvoiceOutputInProgress = false;
        endBulkInvoiceOutput();

        if (mount instanceof HTMLElement) {
            try {
                document.body.removeChild(mount);
            } catch {
                /* ignore */
            }
        }
    }
}

/**
 * @param {HTMLButtonElement} triggerBtn
 */
async function startBulkInvoicePdfSave(triggerBtn) {
    const html = buildBulkInvoicePrintHtml('pdf');
    if (!html) {
        window.alert('PDF保存対象の請求書プレビューが見つかりません。');

        return false;
    }

    const filename = buildBulkInvoicePrintFilename(triggerBtn);

    return downloadBulkInvoiceAsPdf(html, filename, triggerBtn);
}

global.KTPBulkInvoicePrint = {
    startPrint: startBulkInvoicePrint,
    startPdfSave: startBulkInvoicePdfSave,
    bindButtons: bindKtpBulkInvoiceOutputButtons
};
global.bindKtpBulkInvoiceOutputButtons = bindKtpBulkInvoiceOutputButtons;

})(typeof window !== 'undefined' ? window : this);
