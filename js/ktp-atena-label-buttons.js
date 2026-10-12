/**
 * 宛名印刷ボタン（顧客・協力会社タブ）
 *
 * 以前は各タブの PHP が本文にインライン <script> で出していた関数を、そのまま移したもの
 * （wp.org ガイドライン：JS は wp_enqueue_script で読み込む）。中身は変えていない。
 * ボタンの onclick から呼ぶのでグローバル関数のままにしてある。
 * 実際の印刷は js/ktp-atena-print.js の window.KtpAtenaPrint が行う。
 */
/* global ktpwpTranslate */

function printClientAddressLabel() {
    function t(msg) { return (typeof ktpwpTranslate === 'function') ? ktpwpTranslate(msg) : msg; }
    function esc(s) {
        if (s == null || s === '') { return ''; }
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }
    function field(name) {
        var box = document.querySelector('.data_detail_box');
        var el = box ? box.querySelector('[name="' + name + '"]') : null;
        if (!el) { el = document.querySelector('[name="' + name + '"]'); }
        if (!el || el.value === undefined || el.value === null) { return ''; }
        return String(el.value).trim();
    }
    function formatPostal(pc) {
        var d = String(pc).replace(/\D/g, '');
        if (d.length === 7) { return '\u3012' + d.slice(0, 3) + '-' + d.slice(3); }
        if (d.length > 0) { return '\u3012' + d; }
        return '';
    }
    var postal = formatPostal(field('postal_code'));
    var pref = field('prefecture');
    var city = field('city');
    var street = field('address');
    var building = field('building');
    var company = field('company_name');
    var person = field('user_name');
    var line2 = (pref + city).trim();
    var line3 = (street + building).trim();
    var honor = (/^ja/i.test(document.documentElement.lang || '') || (window.ktpwpI18n && /^ja/i.test(String(window.ktpwpI18n.locale || '')))) ? ' \u69d8' : '';
    if (!postal && !line2 && !line3 && !company && !person) {
        alert(t('宛先情報がありません。顧客詳細を表示して住所などを入力してください。'));
        return;
    }
    var inner = '';
    if (postal) { inner += '<div>' + esc(postal) + '</div>'; }
    if (line2) { inner += '<div>' + esc(line2) + '</div>'; }
    if (line3) { inner += '<div>' + esc(line3) + '</div>'; }
    if (company) { inner += '<div style="font-weight:bold;margin-top:0.35em;">' + esc(company) + '</div>'; }
    if (person) { inner += '<div style="margin-top:0.25em;">' + esc(person) + esc(honor) + '</div>'; }
    if (!window.KtpAtenaPrint || typeof window.KtpAtenaPrint.openPreview !== 'function') {
        alert(t('宛名印刷の準備ができていません。ページを再読み込みしてください。'));
        return;
    }
    var recordId = field('client_id') || field('data_id');
    if (!recordId) {
        var params = new URLSearchParams(window.location.search);
        recordId = params.get('data_id') || '0';
    }
    window.KtpAtenaPrint.openPreview({
        entityType: 'client',
        recordId: recordId,
        labelInnerHtml: inner,
        title: t('宛名印刷'),
        maxMemoLines: 18
    });
}

function printSupplierAddressLabel() {
    function t(msg) { return (typeof ktpwpTranslate === 'function') ? ktpwpTranslate(msg) : msg; }
    function esc(s) {
        if (s == null || s === '') { return ''; }
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }
    function field(name) {
        var box = document.querySelector('.data_detail_box');
        var el = box ? box.querySelector('[name="' + name + '"]') : null;
        if (!el) { el = document.querySelector('[name="' + name + '"]'); }
        if (!el || el.value === undefined || el.value === null) { return ''; }
        return String(el.value).trim();
    }
    function formatPostal(pc) {
        var d = String(pc).replace(/\D/g, '');
        if (d.length === 7) { return '\u3012' + d.slice(0, 3) + '-' + d.slice(3); }
        if (d.length > 0) { return '\u3012' + d; }
        return '';
    }
    var postal = formatPostal(field('postal_code'));
    var pref = field('prefecture');
    var city = field('city');
    var street = field('address');
    var building = field('building');
    var company = field('company_name');
    var person = field('user_name');
    if (!person) { person = field('representative_name'); }
    var line2 = (pref + city).trim();
    var line3 = (street + building).trim();
    var honor = (/^ja/i.test(document.documentElement.lang || '') || (window.ktpwpI18n && /^ja/i.test(String(window.ktpwpI18n.locale || '')))) ? ' \u69d8' : '';
    if (!postal && !line2 && !line3 && !company && !person) {
        alert(t('宛先情報がありません。協力会社詳細を表示して住所などを入力してください。'));
        return;
    }
    var inner = '';
    if (postal) { inner += '<div>' + esc(postal) + '</div>'; }
    if (line2) { inner += '<div>' + esc(line2) + '</div>'; }
    if (line3) { inner += '<div>' + esc(line3) + '</div>'; }
    if (company) { inner += '<div style="font-weight:bold;margin-top:0.35em;">' + esc(company) + '</div>'; }
    if (person) { inner += '<div style="margin-top:0.25em;">' + esc(person) + esc(honor) + '</div>'; }
    if (!window.KtpAtenaPrint || typeof window.KtpAtenaPrint.openPreview !== 'function') {
        alert(t('宛名印刷の準備ができていません。ページを再読み込みしてください。'));
        return;
    }
    var recordId = field('data_id');
    if (!recordId) {
        var params = new URLSearchParams(window.location.search);
        recordId = params.get('data_id') || '0';
    }
    window.KtpAtenaPrint.openPreview({
        entityType: 'supplier',
        recordId: recordId,
        labelInnerHtml: inner,
        title: t('宛名印刷'),
        maxMemoLines: 18
    });
}
