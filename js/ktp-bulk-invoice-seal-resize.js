/**
 * 一括請求書プレビュー：印影をクリックして四隅ハンドルで拡大縮小（起点は常に右上・縦横比固定）
 */
(function () {
    const HANDLE_CORNERS = ['nw', 'ne', 'sw', 'se'];
    const PREVIEW_READY_EVENT = 'ktp-bulk-invoice-preview-ready';

    /** @type {null | {
     *   img: HTMLImageElement,
     *   scope: HTMLElement,
     *   config: object,
     *   startWidth: number,
     *   startHeight: number,
     *   startRight: number,
     *   startTop: number,
     * }} */
    let activeDrag = null;
    let saveTimer = 0;
    let globalsBound = false;
    /** @type {HTMLImageElement | null} */
    let activeImg = null;
    /** @type {HTMLElement | null} */
    let activeScope = null;
    /** @type {object | null} */
    let activeConfig = null;

    function clamp(value, min, max) {
        return Math.max(min, Math.min(max, value));
    }

    function readConfig() {
        if (typeof ktpClientInvoice === 'undefined' || !ktpClientInvoice.bulkSealResize) {
            return null;
        }
        return ktpClientInvoice.bulkSealResize;
    }

    function findPageRoot() {
        return document.getElementById('invoiceList')
            || document.querySelector('.ktp-bulk-invoice-preview')
            || document.getElementById('ktp-invoice-preview-popup');
    }

    function settingFromDisplay(displayPx, config) {
        const scale = Number(config.overlayScale) || 1;
        if (scale <= 0) {
            return config.settingMin;
        }

        return clamp(
            Math.round(displayPx / scale),
            config.settingMin,
            config.settingMax
        );
    }

    function displayFromSetting(settingPx, config) {
        const scale = Number(config.overlayScale) || 1;
        return clamp(
            Math.round(settingPx * scale),
            config.settingMin,
            config.overlayMax
        );
    }

    function applySealDisplaySize(img, widthPx, heightPx) {
        const w = Math.round(widthPx);
        const h = Math.round(heightPx);
        img.style.setProperty('width', `${w}px`);
        img.style.setProperty('height', `${h}px`);
        img.style.setProperty('max-width', `${w}px`);
        img.style.setProperty('max-height', `${h}px`);
        img.dataset.displayWidth = String(w);
        img.dataset.displayHeight = String(h);
    }

    function clearHandles(scope) {
        if (!scope) {
            return;
        }
        scope.querySelectorAll('.ktp-bulk-invoice-seal-resize-handle').forEach((el) => el.remove());
    }

    function positionHandles(img, scope) {
        const imgRect = img.getBoundingClientRect();
        const scopeRect = scope.getBoundingClientRect();
        const left = imgRect.left - scopeRect.left + scope.scrollLeft;
        const top = imgRect.top - scopeRect.top + scope.scrollTop;
        const width = imgRect.width;
        const height = imgRect.height;

        const points = {
            nw: { x: left, y: top },
            ne: { x: left + width, y: top },
            sw: { x: left, y: top + height },
            se: { x: left + width, y: top + height },
        };

        HANDLE_CORNERS.forEach((corner) => {
            let handle = scope.querySelector(`.ktp-bulk-invoice-seal-resize-handle[data-corner="${corner}"]`);
            if (!(handle instanceof HTMLElement)) {
                handle = document.createElement('button');
                handle.type = 'button';
                handle.className = 'ktp-bulk-invoice-seal-resize-handle ktp-bulk-invoice-no-print';
                handle.dataset.corner = corner;
                handle.setAttribute('aria-label', '印影サイズを変更');
                handle.tabIndex = -1;
                scope.appendChild(handle);
            }

            handle.style.left = `${points[corner].x}px`;
            handle.style.top = `${points[corner].y}px`;
        });
    }

    function deselect(img, scope) {
        if (img) {
            img.classList.remove('is-selected');
        }
        clearHandles(scope);
    }

    function select(img, scope) {
        img.classList.add('is-selected');
        positionHandles(img, scope);
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

    async function saveSealSize(config, widthSetting, heightSetting) {
        const body = new URLSearchParams();
        body.set('action', config.action);
        body.set('nonce', config.nonce);
        body.set('seal_max_width_px', String(widthSetting));
        body.set('seal_max_height_px', String(heightSetting));

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
            throw new Error(`seal size save failed: ${response.status}`);
        }

        const json = await response.json();
        if (!json || !json.success || !json.data) {
            throw new Error('seal size save failed: invalid response');
        }

        return json.data;
    }

    function scheduleSave(img, scope, config, displayW, displayH) {
        window.clearTimeout(saveTimer);
        saveTimer = window.setTimeout(async () => {
            const settingSize = Math.max(
                settingFromDisplay(displayW, config),
                settingFromDisplay(displayH, config)
            );
            const syncedDisplay = displayFromSetting(settingSize, config);

            applySealDisplaySize(img, syncedDisplay, syncedDisplay);
            img.dataset.settingWidth = String(settingSize);
            img.dataset.settingHeight = String(settingSize);
            if (img.classList.contains('is-selected')) {
                positionHandles(img, scope);
            }

            try {
                const result = await saveSealSize(config, settingSize, settingSize);
                if (result && result.display_width_px && result.display_height_px) {
                    applySealDisplaySize(img, result.display_width_px, result.display_height_px);
                    if (result.seal_max_width_px) {
                        img.dataset.settingWidth = String(result.seal_max_width_px);
                    }
                    if (result.seal_max_height_px) {
                        img.dataset.settingHeight = String(result.seal_max_height_px);
                    }
                    if (img.classList.contains('is-selected')) {
                        positionHandles(img, scope);
                    }
                }
            } catch (error) {
                console.warn('[一括請求書] 印影サイズの保存に失敗しました', error);
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

            const { img, scope, config, startWidth, startHeight, startRight, startTop } = activeDrag;
            const next = sizeFromTopRightOrigin(
                startWidth,
                startHeight,
                startRight,
                startTop,
                event.clientX,
                event.clientY
            );
            const minDisplay = displayFromSetting(config.settingMin, config);
            const maxDisplay = Math.min(
                config.overlayMax,
                displayFromSetting(config.settingMax, config)
            );
            const width = clamp(next.width, minDisplay, maxDisplay);
            const height = clamp(next.height, minDisplay, maxDisplay);

            applySealDisplaySize(img, width, height);
            positionHandles(img, scope);
        });

        const endDrag = () => {
            if (!activeDrag) {
                return;
            }

            const { img, scope, config } = activeDrag;
            activeDrag = null;
            const width = Number(img.dataset.displayWidth) || img.getBoundingClientRect().width;
            const height = Number(img.dataset.displayHeight) || img.getBoundingClientRect().height;
            scheduleSave(img, scope, config, width, height);
        };

        window.addEventListener('pointerup', endDrag);
        window.addEventListener('pointercancel', endDrag);

        document.addEventListener('click', (event) => {
            if (!activeImg || !activeScope || !activeImg.classList.contains('is-selected')) {
                return;
            }
            const target = event.target;
            if (!(target instanceof Element)) {
                return;
            }
            if (target === activeImg || target.closest('.ktp-bulk-invoice-seal-resize-handle')) {
                return;
            }
            deselect(activeImg, activeScope);
        });

        window.addEventListener('resize', () => {
            if (activeImg && activeScope && activeImg.classList.contains('is-selected') && document.contains(activeImg)) {
                positionHandles(activeImg, activeScope);
            }
        });
    }

    function init() {
        const config = readConfig();
        if (!config || !config.enabled || !config.ajaxUrl || !config.nonce || !config.action) {
            return;
        }

        const pageRoot = findPageRoot();
        if (!(pageRoot instanceof HTMLElement)) {
            return;
        }

        const img = pageRoot.querySelector('.ktp-bulk-invoice-issuer-seal-overlay[data-ktp-bulk-seal-resize="1"]');
        if (!(img instanceof HTMLImageElement)) {
            activeImg = null;
            activeScope = null;
            activeConfig = null;
            return;
        }

        if (img.dataset.ktpBulkSealResizeBound === '1') {
            activeImg = img;
            activeScope = img.closest('.ktp-bulk-invoice-issuer-seal-scope');
            activeConfig = config;
            return;
        }

        const scope = img.closest('.ktp-bulk-invoice-issuer-seal-scope');
        if (!(scope instanceof HTMLElement)) {
            return;
        }

        img.dataset.ktpBulkSealResizeBound = '1';
        activeImg = img;
        activeScope = scope;
        activeConfig = config;
        bindGlobals();

        img.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            select(img, scope);
        });

        scope.addEventListener('pointerdown', (event) => {
            const handle = event.target instanceof Element
                ? event.target.closest('.ktp-bulk-invoice-seal-resize-handle')
                : null;

            if (!(handle instanceof HTMLElement) || !img.classList.contains('is-selected')) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();

            const rect = img.getBoundingClientRect();
            activeDrag = {
                img,
                scope,
                config,
                startWidth: rect.width,
                startHeight: rect.height,
                startRight: rect.right,
                startTop: rect.top,
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
