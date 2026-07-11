/**
 * 一括請求書プレビュー：ロゴをクリックして下辺ハンドルで拡大縮小
 * ・右下（se）→ 左上起点・左寄せ
 * ・左下（sw）→ 右上起点・右寄せ
 * ・上辺ハンドルは出さない
 * ・縦横比固定・上限は右上ブロック幅
 */
(function () {
    const HANDLE_CORNERS = ['sw', 'se'];
    const PREVIEW_READY_EVENT = 'ktp-bulk-invoice-preview-ready';

    /** @type {null | {
     *   img: HTMLImageElement,
     *   wrap: HTMLElement,
     *   config: object,
     *   corner: 'sw' | 'se',
     *   origin: 'top-left' | 'top-right',
     *   align: 'left' | 'right',
     *   startWidth: number,
     *   startHeight: number,
     *   startLeft: number,
     *   startRight: number,
     *   startTop: number,
     *   maxWidth: number,
     * }} */
    let activeDrag = null;
    let saveTimer = 0;
    let globalsBound = false;
    /** @type {HTMLImageElement | null} */
    let activeImg = null;
    /** @type {HTMLElement | null} */
    let activeWrap = null;

    function clamp(value, min, max) {
        return Math.max(min, Math.min(max, value));
    }

    function readConfig() {
        if (typeof ktpClientInvoice === 'undefined' || !ktpClientInvoice.bulkLogoResize) {
            return null;
        }
        return ktpClientInvoice.bulkLogoResize;
    }

    function findPageRoot() {
        return document.getElementById('invoiceList')
            || document.querySelector('.ktp-bulk-invoice-preview')
            || document.getElementById('ktp-invoice-preview-popup');
    }

    function wrapMaxWidth(wrap) {
        const width = wrap.getBoundingClientRect().width;
        return width > 0 ? width : wrap.clientWidth;
    }

    /**
     * @param {'left' | 'right'} align
     */
    function applyLogoAlign(img, align) {
        const isRight = align === 'right';
        img.style.setProperty('margin-left', isRight ? 'auto' : '0');
        img.style.setProperty('margin-right', '0');
        img.style.setProperty('object-position', isRight ? 'right center' : 'left center');
        img.dataset.logoAlign = isRight ? 'right' : 'left';
    }

    /**
     * @param {'left' | 'right'} align
     */
    function applyLogoWidthPx(img, widthPx, align) {
        const w = Math.round(widthPx);
        img.style.setProperty('width', `${w}px`);
        img.style.setProperty('max-width', '100%');
        img.style.setProperty('height', 'auto');
        img.dataset.displayWidth = String(w);
        applyLogoAlign(img, align);
    }

    /**
     * @param {'left' | 'right'} align
     */
    function applyLogoWidthPercent(img, percent, align) {
        const p = Math.round(percent);
        img.style.setProperty('width', `${p}%`);
        img.style.setProperty('max-width', '100%');
        img.style.setProperty('height', 'auto');
        img.dataset.widthPercent = String(p);
        applyLogoAlign(img, align);
    }

    function clearHandles(wrap) {
        if (!wrap) {
            return;
        }
        wrap.querySelectorAll('.ktp-bulk-invoice-logo-resize-handle').forEach((el) => el.remove());
    }

    function positionHandles(img, wrap) {
        const imgRect = img.getBoundingClientRect();
        const wrapRect = wrap.getBoundingClientRect();
        const left = imgRect.left - wrapRect.left + wrap.scrollLeft;
        const top = imgRect.top - wrapRect.top + wrap.scrollTop;
        const width = imgRect.width;
        const height = imgRect.height;

        const points = {
            sw: { x: left, y: top + height },
            se: { x: left + width, y: top + height },
        };

        HANDLE_CORNERS.forEach((corner) => {
            let handle = wrap.querySelector(`.ktp-bulk-invoice-logo-resize-handle[data-corner="${corner}"]`);
            if (!(handle instanceof HTMLElement)) {
                handle = document.createElement('button');
                handle.type = 'button';
                handle.className = 'ktp-bulk-invoice-logo-resize-handle ktp-bulk-invoice-no-print';
                handle.dataset.corner = corner;
                handle.setAttribute('aria-label', 'ロゴサイズを変更');
                handle.tabIndex = -1;
                wrap.appendChild(handle);
            }

            handle.style.left = `${points[corner].x}px`;
            handle.style.top = `${points[corner].y}px`;
        });
    }

    function deselect(img, wrap) {
        if (img) {
            img.classList.remove('is-selected');
        }
        clearHandles(wrap);
    }

    function select(img, wrap) {
        img.classList.add('is-selected');
        positionHandles(img, wrap);
    }

    function sizeFromTopLeftOrigin(startWidth, startHeight, startLeft, startTop, clientX, clientY) {
        const aspect = startWidth > 0 && startHeight > 0 ? startWidth / startHeight : 1;
        const dx = clientX - startLeft;
        const dy = clientY - startTop;
        let width = Math.max(dx, dy * aspect);
        let height = width / aspect;

        if (!Number.isFinite(width) || width <= 0) {
            width = startWidth;
            height = startHeight;
        }

        return { width, height };
    }

    function sizeFromTopRightOrigin(startWidth, startHeight, startRight, startTop, clientX, clientY) {
        const aspect = startWidth > 0 && startHeight > 0 ? startWidth / startHeight : 1;
        const dx = startRight - clientX;
        const dy = clientY - startTop;
        let width = Math.max(dx, dy * aspect);
        let height = width / aspect;

        if (!Number.isFinite(width) || width <= 0) {
            width = startWidth;
            height = startHeight;
        }

        return { width, height };
    }

    /**
     * @param {'left' | 'right'} align
     */
    async function saveLogoWidth(config, percent, align) {
        const body = new URLSearchParams();
        body.set('action', config.action);
        body.set('nonce', config.nonce);
        body.set('issuer_logo_width_percent', String(percent));
        body.set('issuer_logo_align', align);

        const response = await fetch(config.ajaxUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                Accept: 'application/json',
            },
            credentials: 'same-origin',
            body: body.toString(),
        });

        if (!response.ok) {
            throw new Error(`logo width save failed: ${response.status}`);
        }

        const json = await response.json();
        if (!json || !json.success || !json.data) {
            throw new Error('logo width save failed: invalid response');
        }

        return json.data;
    }

    /**
     * @param {'left' | 'right'} align
     */
    function scheduleSave(img, wrap, config, displayW, maxWidth, align) {
        window.clearTimeout(saveTimer);
        saveTimer = window.setTimeout(async () => {
            const cap = maxWidth > 0 ? maxWidth : wrapMaxWidth(wrap);
            const rawPercent = cap > 0 ? Math.round((displayW / cap) * 100) : config.percentMax;
            const percent = clamp(rawPercent, config.percentMin, config.percentMax);
            const nextAlign = align === 'right' ? 'right' : 'left';

            applyLogoWidthPercent(img, percent, nextAlign);
            if (img.classList.contains('is-selected')) {
                positionHandles(img, wrap);
            }

            if (!config.ajaxUrl || !config.nonce || !config.action) {
                return;
            }

            try {
                const result = await saveLogoWidth(config, percent, nextAlign);
                if (result && result.issuer_logo_width_percent) {
                    applyLogoWidthPercent(
                        img,
                        result.issuer_logo_width_percent,
                        result.issuer_logo_align === 'right' ? 'right' : 'left'
                    );
                    if (img.classList.contains('is-selected')) {
                        positionHandles(img, wrap);
                    }
                }
            } catch (error) {
                console.warn('[一括請求書] ロゴサイズの保存に失敗しました', error);
            }
        }, 250);
    }

    function bindGlobals() {
        if (globalsBound) {
            return;
        }

        globalsBound = true;

        window.addEventListener('pointermove', (event) => {
            if (!activeDrag) {
                return;
            }

            const {
                img,
                wrap,
                config,
                origin,
                align,
                startWidth,
                startHeight,
                startLeft,
                startRight,
                startTop,
                maxWidth,
            } = activeDrag;
            const next = origin === 'top-right'
                ? sizeFromTopRightOrigin(
                    startWidth,
                    startHeight,
                    startRight,
                    startTop,
                    event.clientX,
                    event.clientY
                )
                : sizeFromTopLeftOrigin(
                    startWidth,
                    startHeight,
                    startLeft,
                    startTop,
                    event.clientX,
                    event.clientY
                );
            const minDisplay = Math.max(
                Number(config.minDisplayPx) || 40,
                maxWidth * ((Number(config.percentMin) || 15) / 100)
            );
            const width = clamp(next.width, minDisplay, maxWidth);

            applyLogoWidthPx(img, width, align);
            positionHandles(img, wrap);
        });

        const endDrag = () => {
            if (!activeDrag) {
                return;
            }

            const { img, wrap, config, maxWidth, align } = activeDrag;
            activeDrag = null;
            const width = Number(img.dataset.displayWidth) || img.getBoundingClientRect().width;
            scheduleSave(img, wrap, config, width, maxWidth, align);
        };

        window.addEventListener('pointerup', endDrag);
        window.addEventListener('pointercancel', endDrag);

        document.addEventListener('click', (event) => {
            if (!activeImg || !activeWrap || !activeImg.classList.contains('is-selected')) {
                return;
            }
            const target = event.target;
            if (!(target instanceof Element)) {
                return;
            }
            if (target === activeImg || target.closest('.ktp-bulk-invoice-logo-resize-handle')) {
                return;
            }
            deselect(activeImg, activeWrap);
        });

        window.addEventListener('resize', () => {
            if (activeImg && activeWrap && activeImg.classList.contains('is-selected') && document.contains(activeImg)) {
                positionHandles(activeImg, activeWrap);
            }
        });
    }

    function init() {
        const config = readConfig();
        if (!config || !config.enabled) {
            return;
        }

        const pageRoot = findPageRoot();
        if (!(pageRoot instanceof HTMLElement)) {
            return;
        }

        const img = pageRoot.querySelector('.ktp-bulk-invoice-issuer-logo-img[data-ktp-bulk-logo-resize="1"]');
        if (!(img instanceof HTMLImageElement)) {
            activeImg = null;
            activeWrap = null;
            return;
        }

        if (img.dataset.ktpBulkLogoResizeBound === '1') {
            activeImg = img;
            activeWrap = img.closest('.ktp-bulk-invoice-issuer-logo-wrap');
            return;
        }

        const wrap = img.closest('.ktp-bulk-invoice-issuer-logo-wrap');
        if (!(wrap instanceof HTMLElement)) {
            return;
        }

        img.dataset.ktpBulkLogoResizeBound = '1';
        activeImg = img;
        activeWrap = wrap;
        bindGlobals();

        img.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            select(img, wrap);
        });

        wrap.addEventListener('pointerdown', (event) => {
            const handle = event.target instanceof Element
                ? event.target.closest('.ktp-bulk-invoice-logo-resize-handle')
                : null;

            if (!(handle instanceof HTMLElement) || !img.classList.contains('is-selected')) {
                return;
            }

            const corner = handle.dataset.corner;
            if (corner !== 'sw' && corner !== 'se') {
                return;
            }

            event.preventDefault();
            event.stopPropagation();

            const rect = img.getBoundingClientRect();
            const origin = corner === 'sw' ? 'top-right' : 'top-left';
            const align = corner === 'sw' ? 'right' : 'left';

            activeDrag = {
                img,
                wrap,
                config,
                corner,
                origin,
                align,
                startWidth: rect.width,
                startHeight: rect.height,
                startLeft: rect.left,
                startRight: rect.right,
                startTop: rect.top,
                maxWidth: wrapMaxWidth(wrap),
            };

            handle.setPointerCapture?.(event.pointerId);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }

    document.addEventListener(PREVIEW_READY_EVENT, init);
})();
