/**
 * モバイル: マスタ一覧（表形式）の行タップで詳細欄を表示する。
 * 顧客・協力会社・サービス共通。
 *
 * .tab_content { overflow: auto } のため window ではなくタブパネル内をスクロールする。
 */
(function () {
    'use strict';

    var MQ = window.matchMedia('(max-width: 767px)');
    var STORAGE_KEY = 'ktp_scroll_to_detail';
    var DETAIL_ANCHOR_ID = 'ktp-detail';

    function t(msg) {
        return typeof window.ktpwpTranslate === 'function' ? window.ktpwpTranslate(msg) : msg;
    }

    function isMobile() {
        return MQ.matches;
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
            return document.getElementById(DETAIL_ANCHOR_ID)
                || document.querySelector('.data_detail_box .data_detail_title');
        }
        var title = contents.querySelector('.data_detail_box .data_detail_title');
        if (title && !title.id) {
            title.id = DETAIL_ANCHOR_ID;
        }
        return title;
    }

    function getScrollContainer(element) {
        if (!element) {
            return null;
        }
        var panel = element.closest('.tab_content');
        if (panel) {
            var style = window.getComputedStyle(panel);
            if (/(auto|scroll|overlay)/.test(style.overflowY)) {
                return panel;
            }
        }
        return null;
    }

    function scrollElementIntoView(element) {
        if (!element) {
            return;
        }

        var container = getScrollContainer(element);
        if (container) {
            var containerRect = container.getBoundingClientRect();
            var elementRect = element.getBoundingClientRect();
            var offset = elementRect.top - containerRect.top + container.scrollTop - 12;
            container.scrollTo({
                top: Math.max(0, offset),
                behavior: 'smooth'
            });
            return;
        }

        var top = element.getBoundingClientRect().top + (window.pageYOffset || document.documentElement.scrollTop || 0) - 12;
        window.scrollTo({
            top: Math.max(0, top),
            behavior: 'smooth'
        });
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
        var title = findDetailTitle(contents);
        if (!title || title.querySelector('.ktp-mobile-list-back')) {
            return;
        }

        var host = findTitleTextHost(title);
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'ktp-mobile-list-back';
        btn.setAttribute('aria-label', t('一覧に戻る'));
        btn.setAttribute('title', t('一覧に戻る'));
        btn.innerHTML = '<span class="material-symbols-outlined" aria-hidden="true">arrow_upward</span>';
        btn.addEventListener('click', function (event) {
            event.preventDefault();
            showListView(contents);
        });
        host.appendChild(btn);
    }

    function showDetailView(contents) {
        if (!contents) {
            return;
        }
        contents.classList.add('is-mobile-detail-view');
        ensureBackButton(contents);

        var title = findDetailTitle(contents);
        if (!title) {
            return;
        }

        var runScroll = function () {
            scrollElementIntoView(title);
        };

        runScroll();
        window.requestAnimationFrame(runScroll);
        window.setTimeout(runScroll, 80);
        window.setTimeout(runScroll, 240);
    }

    function showListView(contents) {
        if (!contents) {
            return;
        }
        contents.classList.remove('is-mobile-detail-view');
        var list = contents.querySelector('.ktp_data_list_box, .data_list_box');
        if (list) {
            scrollElementIntoView(list);
        }
    }

    function shouldOpenDetailView() {
        if (location.hash === '#' + DETAIL_ANCHOR_ID) {
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

    function initAfterNavigation() {
        if (!isMobile()) {
            return;
        }

        if (!shouldOpenDetailView()) {
            return;
        }

        clearDetailNavigationFlag();

        var contents = findActiveContents();
        if (contents && contents.querySelector('.data_detail_box, .ktp_data_detail_box')) {
            showDetailView(contents);
        }
    }

    function resetOnDesktop(event) {
        if (event.matches) {
            return;
        }
        document.querySelectorAll('.ktp_data_contents.is-mobile-detail-view, .data_contents.is-mobile-detail-view').forEach(function (contents) {
            contents.classList.remove('is-mobile-detail-view');
        });
    }

    if (typeof MQ.addEventListener === 'function') {
        MQ.addEventListener('change', resetOnDesktop);
    } else if (typeof MQ.addListener === 'function') {
        MQ.addListener(resetOnDesktop);
    }

    function boot() {
        initAfterNavigation();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
    window.addEventListener('load', boot);
})();
