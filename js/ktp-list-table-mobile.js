/**
 * モバイル: マスタ一覧（表形式）の行タップ後、一覧は残したまま詳細欄へスクロールする。
 * 顧客・協力会社・サービス共通。
 */
(function () {
    'use strict';

    var MQ = window.matchMedia('(max-width: 767px)');
    var STORAGE_KEY = 'ktp_scroll_to_detail';
    var DETAIL_ANCHOR_PREFIX = 'ktp-detail';
    var SCROLL_DURATION_MS = 1200;
    var smoothScrollFrameId = 0;

    try {
        if (MQ.matches && sessionStorage.getItem(STORAGE_KEY) === '1' && 'scrollRestoration' in history) {
            history.scrollRestoration = 'manual';
        }
    } catch (err) {
        // ignore
    }

    function t(msg) {
        return typeof window.ktpwpTranslate === 'function' ? window.ktpwpTranslate(msg) : msg;
    }

    function isMobile() {
        return MQ.matches;
    }

    function getPanelKeyFromElement(element) {
        if (!element) {
            return '';
        }
        var panel = element.closest('#client_content, #supplier_content, #service_content');
        if (!panel || !panel.id) {
            return '';
        }
        return panel.id.replace(/_content$/, '');
    }

    function getDetailAnchorId(contents) {
        var panelKey = getPanelKeyFromElement(contents);
        return panelKey ? DETAIL_ANCHOR_PREFIX + '-' + panelKey : DETAIL_ANCHOR_PREFIX;
    }

    function findActiveContents() {
        var panels = document.querySelectorAll('#client_content, #supplier_content, #service_content');
        var i;
        for (i = 0; i < panels.length; i++) {
            if (panels[i].classList.contains('tab_content--active') || panels[i].getAttribute('aria-hidden') === 'false') {
                var contents = panels[i].querySelector('.ktp_data_contents, .data_contents');
                if (contents) {
                    return contents;
                }
            }
        }
        return document.querySelector('.ktp_plugin_container .ktp_data_contents, .ktp_plugin_container .data_contents');
    }

    function findDetailTitle(contents) {
        if (!contents) {
            return document.querySelector('.data_detail_box .data_detail_title');
        }

        return contents.querySelector('.data_detail_box .data_detail_title');
    }

    function assignDetailAnchorId(title, contents) {
        if (!title) {
            return '';
        }

        var anchorId = getDetailAnchorId(contents);
        title.id = anchorId;
        return anchorId;
    }

    function findListTarget(contents) {
        if (!contents) {
            return null;
        }

        var listBox = contents.querySelector('.ktp_data_list_box, .data_list_box');
        if (!listBox) {
            return null;
        }

        return listBox.querySelector('.data_list_title') || listBox;
    }

    function getScrollableContainer(element) {
        var el = element;

        while (el && el !== document.documentElement) {
            var style = window.getComputedStyle(el);
            if (/(auto|scroll|overlay)/.test(style.overflowY) && el.scrollHeight > el.clientHeight + 1) {
                return el;
            }
            el = el.parentElement;
        }

        return null;
    }

    function resolveScrollContext(firstElement, secondElement) {
        var container = getScrollableContainer(firstElement) || getScrollableContainer(secondElement);

        return {
            container: container,
            scrollable: container || window
        };
    }

    function getScrollTop(context) {
        if (context.scrollable === window) {
            return window.pageYOffset || document.documentElement.scrollTop || 0;
        }

        return context.scrollable.scrollTop;
    }

    function setScrollTop(context, top) {
        if (context.scrollable === window) {
            window.scrollTo(0, top);
            return;
        }

        context.scrollable.scrollTop = top;
    }

    function easeInOutCubic(progress) {
        return progress < 0.5
            ? 4 * progress * progress * progress
            : 1 - Math.pow(-2 * progress + 2, 3) / 2;
    }

    function getScrollTargetTop(element, container) {
        if (container) {
            var containerRect = container.getBoundingClientRect();
            var elementRect = element.getBoundingClientRect();
            return Math.max(0, elementRect.top - containerRect.top + container.scrollTop - 12);
        }

        var elementRect = element.getBoundingClientRect();
        return Math.max(0, elementRect.top + (window.pageYOffset || document.documentElement.scrollTop || 0) - 12);
    }

    function animateScrollTop(context, targetTop, duration, forcedStartTop) {
        if (smoothScrollFrameId) {
            window.cancelAnimationFrame(smoothScrollFrameId);
            smoothScrollFrameId = 0;
        }

        var startTop = typeof forcedStartTop === 'number' ? forcedStartTop : getScrollTop(context);
        var distance = targetTop - startTop;

        if (Math.abs(distance) < 1) {
            setScrollTop(context, targetTop);
            return;
        }

        var startTime = null;
        var step = function (timestamp) {
            if (!startTime) {
                startTime = timestamp;
            }

            var progress = Math.min((timestamp - startTime) / duration, 1);
            setScrollTop(context, startTop + distance * easeInOutCubic(progress));

            if (progress < 1) {
                smoothScrollFrameId = window.requestAnimationFrame(step);
            } else {
                smoothScrollFrameId = 0;
            }
        };

        smoothScrollFrameId = window.requestAnimationFrame(step);
    }

    function scrollListToDetail(list, title, duration) {
        var context = resolveScrollContext(list, title);
        var listTop = getScrollTargetTop(list, context.container);

        setScrollTop(context, listTop);

        var runAnimation = function () {
            var startTop = getScrollTop(context);
            var toTop = getScrollTargetTop(title, context.container);
            animateScrollTop(context, toTop, duration, startTop);
        };

        window.requestAnimationFrame(function () {
            window.requestAnimationFrame(function () {
                var startTop = getScrollTop(context);
                var toTop = getScrollTargetTop(title, context.container);

                if (Math.abs(toTop - startTop) < 8) {
                    window.setTimeout(runAnimation, 120);
                    return;
                }

                runAnimation();
            });
        });
    }

    function scrollElementIntoView(element, behavior, duration) {
        if (!element) {
            return;
        }

        var scrollBehavior = behavior || 'smooth';
        var context = resolveScrollContext(element, element);
        var targetTop = getScrollTargetTop(element, context.container);

        if (scrollBehavior === 'smooth') {
            animateScrollTop(context, targetTop, duration || SCROLL_DURATION_MS);
            return;
        }

        setScrollTop(context, targetTop);
    }

    function getBackButtonIconHtml() {
        var html = '';
        if (typeof window.KTPSvgIcons !== 'undefined' && typeof window.KTPSvgIcons.getIcon === 'function') {
            html = window.KTPSvgIcons.getIcon('arrow_upward', { 'aria-hidden': 'true' });
        }
        if (!html) {
            html = '<span class="ktp-svg-icon" aria-hidden="true"><svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path d="M4 12l1.41 1.41L11 7.83V20h2V7.83l5.58 5.59L20 12l-8-8-8 8z"/></svg></span>';
        }
        return html;
    }

    function findTitleTextHost(titleBar) {
        var i;
        for (i = 0; i < titleBar.children.length; i++) {
            var el = titleBar.children[i];
            if (el.classList && el.classList.contains('button-group')) {
                continue;
            }
            if (el.tagName === 'DIV' && !el.classList.contains('ktp-mobile-list-back')) {
                return el;
            }
        }
        return titleBar;
    }

    function ensureBackButton(contents) {
        if (!isMobile()) {
            return;
        }

        var title = findDetailTitle(contents);
        if (!title || title.querySelector('.ktp-mobile-list-back')) {
            return;
        }

        var host = findTitleTextHost(title);
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'ktp-mobile-list-back';
        btn.setAttribute('aria-label', t('一覧へ移動'));
        btn.setAttribute('title', t('一覧へ移動'));
        btn.innerHTML = getBackButtonIconHtml();
        host.appendChild(btn);
    }

    function suppressHashJump() {
        if (!hashRequestsDetailScroll()) {
            return;
        }

        history.replaceState(null, '', location.pathname + location.search);
    }

    function scrollToDetail(contents) {
        if (!contents) {
            return;
        }

        suppressHashJump();

        var title = findDetailTitle(contents);
        if (!title) {
            return;
        }

        title.removeAttribute('id');
        ensureBackButton(contents);

        var list = findListTarget(contents);

        if (list) {
            var context = resolveScrollContext(list, title);
            setScrollTop(context, getScrollTargetTop(list, context.container));
        }

        var runListToDetailScroll = function () {
            assignDetailAnchorId(title, contents);

            if (list) {
                scrollListToDetail(list, title, SCROLL_DURATION_MS);
                return;
            }

            scrollElementIntoView(title, 'smooth', SCROLL_DURATION_MS);
        };

        if (document.readyState === 'complete') {
            runListToDetailScroll();
        } else {
            window.addEventListener('load', runListToDetailScroll, { once: true });
        }
    }

    function scrollToList(contents) {
        if (!contents) {
            contents = findActiveContents();
        }
        var list = findListTarget(contents);
        if (list) {
            scrollElementIntoView(list);
        }
    }

    function hashRequestsDetailScroll() {
        var hash = (location.hash || '').replace(/^#/, '');
        if (!hash) {
            return false;
        }
        return hash === DETAIL_ANCHOR_PREFIX || hash.indexOf(DETAIL_ANCHOR_PREFIX + '-') === 0;
    }

    function shouldOpenDetailView() {
        if (hashRequestsDetailScroll()) {
            return true;
        }
        try {
            return sessionStorage.getItem(STORAGE_KEY) === '1';
        } catch (err) {
            return false;
        }
    }

    function clearDetailNavigationFlag() {
        try {
            sessionStorage.removeItem(STORAGE_KEY);
        } catch (err) {
            // ignore
        }
    }

    var detailScrollInitiated = false;

    function initAfterNavigation() {
        if (!isMobile() || detailScrollInitiated) {
            return;
        }

        if (!shouldOpenDetailView()) {
            return;
        }

        detailScrollInitiated = true;
        clearDetailNavigationFlag();

        if ('scrollRestoration' in history) {
            history.scrollRestoration = 'manual';
        }

        var contents = findActiveContents();
        if (contents && contents.querySelector('.data_detail_box, .ktp_data_detail_box')) {
            scrollToDetail(contents);
        }
    }

    function removeBackButtons() {
        document.querySelectorAll('.ktp-mobile-list-back').forEach(function (btn) {
            btn.remove();
        });
    }

    function resetOnViewportChange(event) {
        if (event.matches) {
            return;
        }
        removeBackButtons();
    }

    var backButtonHandlerBound = false;

    function bindBackButtonHandler() {
        if (backButtonHandlerBound) {
            return;
        }
        backButtonHandlerBound = true;

        document.addEventListener('click', function (event) {
            var btn = event.target.closest('.ktp-mobile-list-back');
            if (!btn || !isMobile()) {
                return;
            }
            event.preventDefault();
            event.stopPropagation();
            scrollToList(btn.closest('.ktp_data_contents, .data_contents'));
        });
    }

    if (typeof MQ.addEventListener === 'function') {
        MQ.addEventListener('change', resetOnViewportChange);
    } else if (typeof MQ.addListener === 'function') {
        MQ.addListener(resetOnViewportChange);
    }

    function boot() {
        bindBackButtonHandler();
        initAfterNavigation();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
    window.addEventListener('load', boot);
})();
