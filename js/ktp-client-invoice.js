// KTP Client Invoice Script

function ktpInvoiceSuppressTaxForInlineSuffix() {
    return !!(window.ktp_tax_policy && (window.ktp_tax_policy.mode === 'abolished' || window.ktp_tax_policy.hide_columns));
}

function ktpInvoiceEffectiveItemTaxRate(item) {
    var itemTaxRateRaw = item && item.tax_rate;
    if (window.ktp_tax_policy) {
        if (window.ktp_tax_policy.mode === 'abolished') {
            return 0;
        }
        if (window.ktp_tax_policy.mode === 'unified') {
            return parseFloat(window.ktp_tax_policy.unified_tax_rate || 0) || 0;
        }
    }
    if (itemTaxRateRaw !== null && itemTaxRateRaw !== undefined && itemTaxRateRaw !== '' && !isNaN(parseFloat(itemTaxRateRaw))) {
        return parseFloat(itemTaxRateRaw);
    }
    return 0;
}

function ktpInvoiceFormatRateLabel(rate) {
    var s = Number(rate).toFixed(2);
    if (s.indexOf('.') >= 0) {
        s = s.replace(/0+$/, '').replace(/\.$/, '');
    }
    return s;
}

/** 一括請求：月別グループ・案件カードの印刷レイアウト（KantanBiz bulk-invoice-print-document 相当） */
function ktpBulkInvoiceOrderLayoutPrintCss(containerSelector) {
    var root = containerSelector ? containerSelector + ' ' : '';
    var css = '';
    css += root + '.ktp-bulk-invoice-order-groups{display:flex;flex-direction:column;gap:16px;margin-bottom:24px;break-before:avoid-page;page-break-before:avoid;}';
    css += root + '.ktp-bulk-invoice-order-group{border:1px solid #e5e7eb;border-radius:6px;overflow:hidden;background:#fff;break-inside:auto;page-break-inside:auto;}';
    css += root + '.ktp-bulk-invoice-order-group-header{padding:8px 16px;border-bottom:1px solid #e5e7eb;font-size:14px;font-weight:600;color:#075985;background:#f9fafb !important;break-after:avoid-page;page-break-after:avoid;}';
    css += root + '.ktp-bulk-invoice-order-group-body{padding:16px;display:flex;flex-direction:column;gap:16px;}';
    css += root + '.ktp-bulk-invoice-order-group-footer{padding:8px 16px;border-top:2px solid #cbd5e1;background:#f9fafb !important;text-align:right;font-size:16px;font-weight:700;color:#0369a1;}';
    css += root + '.ktp-bulk-invoice-order-card{border:1px solid #d1d5db;border-radius:6px;overflow:hidden;background:#fff;break-inside:avoid-page;page-break-inside:avoid;}';
    css += root + '.ktp-bulk-invoice-order-card-header{padding:8px 12px;border-bottom:1px solid #e5e7eb;font-size:14px;font-weight:600;color:#111827;background:#fff !important;}';
    css += root + '.ktp-bulk-invoice-order-card-header > div{margin-top:4px;font-size:12px;font-weight:400;color:#4b5563;}';
    css += root + '.ktp-bulk-invoice-order-card-footer{padding:8px 12px;border-top:1px solid #e5e7eb;text-align:right;font-size:14px;font-weight:600;color:#111827;background:#fff !important;break-before:avoid-page;page-break-before:avoid;}';
    css += root + '.ktp-bulk-invoice-section__title{color:#1e40af;font-size:14px;font-weight:700;margin:8px 0 8px;padding-bottom:4px;border-bottom:1px dashed #bfdbfe;}';
    css += root + '.ktp-bulk-invoice-order-groups > .ktp-bulk-invoice-order-group:not(:last-child){break-after:page;page-break-after:always;}';
    return css;
}

/** PDF/印刷キャプチャ用ミラー CSS（KantanBiz bulk-invoice-pdf-mirror-styles + iframe-styles 相当） */
function ktpBulkInvoicePdfMirrorStylesCss() {
    var css = '';
    css += '.ktp-bulk-invoice-pdf-save-mode .ktp-bulk-invoice-content-area{padding:8mm 10mm 10mm 10mm!important;--bulk-issuer-pad-right:6mm;}';
    css += '.ktp-bulk-invoice-pdf-save-mode .ktp-bulk-invoice-content-area.ktp-bulk-invoice-envelope-page{display:grid!important;grid-template-columns:minmax(0,1fr) minmax(0,1fr)!important;grid-template-rows:minmax(0,max-content) max-content!important;grid-template-areas:"addressee issuer" "body body"!important;column-gap:1rem!important;align-items:start!important;position:relative!important;width:100%!important;max-width:none!important;box-sizing:border-box!important;overflow:visible!important;}';
    css += '.ktp-bulk-invoice-pdf-save-mode .ktp-bulk-invoice-content-area.ktp-bulk-invoice-envelope-page--labeled{padding-top:calc(8mm + var(--bulk-envelope-label-offset,15mm))!important;}';
    css += '.ktp-bulk-invoice-pdf-save-mode .ktp-bulk-invoice-content-area.ktp-bulk-invoice-envelope-page--labeled .ktp-bulk-invoice-address-block{margin-top:calc(-1 * var(--bulk-envelope-label-offset,15mm));}';
    css += '.ktp-bulk-invoice-pdf-save-mode .ktp-bulk-invoice-address-block{position:static!important;top:auto!important;left:auto!important;right:auto!important;grid-area:addressee!important;max-width:none!important;margin:0!important;z-index:auto!important;}';
    css += '.ktp-bulk-invoice-pdf-save-mode .ktp-bulk-invoice-issuer-stack{position:static!important;top:auto!important;right:auto!important;grid-area:issuer!important;justify-self:stretch!important;align-self:start!important;width:100%!important;max-width:100%!important;min-height:max-content!important;padding-bottom:3mm!important;pointer-events:auto!important;overflow:visible!important;}';
    css += '.ktp-bulk-invoice-pdf-save-mode .ktp-bulk-invoice-issuer-inner{position:relative!important;display:block!important;width:90%!important;max-width:90%!important;margin-left:auto!important;box-sizing:border-box!important;overflow:visible!important;color:#374151;line-height:1.35;}';
    css += '.ktp-bulk-invoice-pdf-save-mode .ktp-bulk-invoice-print-except-addressee{grid-area:body!important;margin-top:1.25rem!important;min-width:0;width:100%!important;}';
    css += '.ktp-bulk-invoice-pdf-save-mode .ktp-bulk-invoice-pdf-root{position:relative;margin:0 auto;background:#fff;box-sizing:border-box;width:100%;max-width:100%;}';
    css += '.ktp-bulk-invoice-print-document .ktp-bulk-invoice-issuer-doc-title{display:flex!important;align-items:center!important;justify-content:space-between!important;gap:0.5em!important;width:0!important;min-width:100%!important;margin-bottom:0.55em!important;font-size:2em!important;color:var(--bulk-accent,#374151)!important;line-height:1!important;}';
    css += '.ktp-bulk-invoice-print-document .ktp-bulk-invoice-issuer-doc-title-ornament{display:flex!important;flex:1 1 0!important;flex-direction:column!important;align-items:stretch!important;justify-content:center!important;gap:0.2em!important;min-width:0!important;}';
    css += '.ktp-bulk-invoice-print-document .ktp-bulk-invoice-issuer-doc-title-ornament>span{display:block!important;width:100%!important;height:0.0625em!important;min-height:1px!important;background:currentColor!important;}';
    css += '.ktp-bulk-invoice-print-document .ktp-bulk-invoice-issuer-doc-title-text{flex:0 0 auto!important;font-size:inherit!important;letter-spacing:0.08em!important;white-space:nowrap!important;}';
    css += '.ktp-bulk-invoice-print-document .ktp-bulk-invoice-issuer-logo-wrap{width:0!important;min-width:100%!important;margin-top:2.5em!important;margin-bottom:0.45em!important;overflow:visible!important;}';
    css += '.ktp-bulk-invoice-print-document .ktp-bulk-invoice-issuer-logo-img{display:block!important;max-width:100%!important;height:auto!important;object-fit:contain!important;}';
    css += '.ktp-bulk-invoice-print-document .ktp-bulk-invoice-seal-resize-handle,.ktp-bulk-invoice-print-document .ktp-bulk-invoice-logo-resize-handle,.ktp-bulk-invoice-pdf-capture-mount .ktp-bulk-invoice-seal-resize-handle,.ktp-bulk-invoice-pdf-capture-mount .ktp-bulk-invoice-logo-resize-handle,.ktp-bulk-invoice-print-document .ktp-bulk-invoice-no-print,.ktp-bulk-invoice-pdf-capture-mount .ktp-bulk-invoice-no-print{display:none!important;}';
    css += '.ktp-bulk-invoice-print-document .ktp-bulk-invoice-issuer-text-block{display:block!important;width:100%!important;max-width:100%!important;position:relative!important;}';
    css += '.ktp-bulk-invoice-print-document .ktp-bulk-invoice-issuer-text-block>div{margin-bottom:0.2em;}';
    css += '.ktp-bulk-invoice-print-document .ktp-bulk-invoice-issuer-bank{margin-top:1.2em!important;padding:0!important;border:none!important;background:#fff!important;box-shadow:none!important;}';
    css += '.ktp-bulk-invoice-print-document .ktp-bulk-invoice-issuer-bank *{margin:0;padding:0;border:none!important;background:#fff!important;box-shadow:none!important;}';
    css += '.ktp-bulk-invoice-pdf-save-mode .ktp-bulk-invoice-issuer-seal-scope,.ktp-bulk-invoice-print-document .ktp-bulk-invoice-issuer-seal-scope{position:relative!important;overflow:visible!important;isolation:isolate!important;background:#fff!important;padding-bottom:0.35em!important;}';
    css += '.ktp-bulk-invoice-pdf-save-mode .ktp-bulk-invoice-issuer-seal-overlay,.ktp-bulk-invoice-print-document .ktp-bulk-invoice-issuer-seal-overlay{position:absolute!important;right:-0.4em!important;top:-0.5em!important;transform:none!important;z-index:3!important;pointer-events:none!important;background:transparent!important;mix-blend-mode:multiply!important;opacity:0.75!important;-webkit-print-color-adjust:exact!important;print-color-adjust:exact!important;object-fit:contain!important;}';
    css += '.ktp-bulk-invoice-print-document .ktp-bulk-invoice-envelope-label-slot{font-size:var(--bulk-issuer-text-font-size,14px);line-height:1;margin-bottom:0.35em;}';
    css += '.ktp-bulk-invoice-print-document .ktp-bulk-invoice-envelope-label{display:block;color:#2563eb;font-size:2em;line-height:1;letter-spacing:0.08em;text-align:left;}';
    css += '.ktp-bulk-invoice-print-document .ktp-bulk-invoice-addressee{font-size:12px;line-height:1.4;margin-bottom:20px;color:#111827;}';
    css += '.ktp-bulk-invoice-print-document .ktp-bulk-invoice-document-lead{width:50%;max-width:50%;text-align:left;margin:0 0 16px 0;color:#374151;}';
    css += '.ktp-bulk-invoice-print-document .ktp-bulk-invoice-summary-box{margin-bottom:24px;padding:16px;border:1px solid #e5e7eb;border-radius:6px;background-color:#f9fafb;font-size:14px;color:#374151;}';
    css += '.ktp-bulk-invoice-print-document .ktp-bulk-tax-amount-col{display:none!important;}';
    css += '.ktp-bulk-invoice-print-document .ktp-bulk-show-tax-amount .ktp-bulk-tax-amount-col{display:table-cell!important;}';
    css += '.ktp-bulk-invoice-print-document .ktp-biz-items-table thead.ktp-bulk-items-table-head tr,.ktp-bulk-invoice-print-document .ktp-biz-items-table thead.ktp-bulk-items-table-head th{background-color:#ffeef1!important;border-bottom:1px solid #fecdd3!important;}';
    css += '.ktp-bulk-invoice-pdf-capture-mount .ktp-bulk-invoice-issuer-seal-overlay{mix-blend-mode:multiply!important;background:transparent!important;}';
    return css;
}

/**
 * 請求項目配列から「 (内税|消費税 : 10%: xxx, 8%: yyy)」形式（KantanBiz InvoiceTaxInlineBreakdown と同式）
 */
function ktpInvoiceTaxInlineSuffixFromItems(items, taxCategory, translateFn) {
    var tfn = typeof translateFn === 'function' ? translateFn : function (x) { return x; };
    if (ktpInvoiceSuppressTaxForInlineSuffix() || !items || !items.length) {
        return '';
    }
    var sums = {};
    items.forEach(function (item) {
        var rate = ktpInvoiceEffectiveItemTaxRate(item);
        if (rate <= 0) {
            return;
        }
        var amount = parseFloat(item.amount) || 0;
        if (amount <= 0) {
            amount = (parseFloat(item.price) || 0) * (parseFloat(item.quantity) || 0);
        }
        if (amount <= 0) {
            return;
        }
        var key = Number(rate).toFixed(2);
        sums[key] = (sums[key] || 0) + amount;
    });
    var keys = Object.keys(sums).sort(function (a, b) { return parseFloat(b) - parseFloat(a); });
    var parts = [];
    keys.forEach(function (key) {
        var rate = parseFloat(key);
        var groupAmount = sums[key];
        var tax = taxCategory === '外税'
            ? Math.ceil(groupAmount * (rate / 100))
            : Math.ceil((groupAmount * (rate / 100)) / (1 + rate / 100));
        if (tax > 0) {
            parts.push(ktpInvoiceFormatRateLabel(rate) + '%: ' + (typeof ktpwpFormatMoney === 'function' ? ktpwpFormatMoney(tax) : String(tax)));
        }
    });
    if (!parts.length) {
        return '';
    }
    var label = taxCategory === '外税' ? tfn('消費税') : tfn('内税');
    return ' (' + label + ' : ' + parts.join(', ') + ')';
}

function ktpInvoiceEscapeHtml(s) {
    return String(s == null ? '' : s)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

/** KantanBiz 相当の自社ブロック用に旧 company-info-box の外枠を外す */
function ktpInvoiceUnwrapCompanyInfoBox(html) {
    var str = String(html || '').trim();
    var m = str.match(/^<div\s+class="company-info-box"[^>]*>([\s\S]*)<\/div>\s*$/i);
    return m ? m[1].trim() : str;
}

/** KantanBiz 一括請求：帳票フォントのスケール（compact 時 75%） */
function ktpBulkInvoiceScaledIssuerTextFontSizePx(bulkDoc) {
    bulkDoc = bulkDoc || {};
    var base = parseInt(bulkDoc.body_font_size, 10) || 14;
    base = Math.max(8, Math.min(16, base));
    if (bulkDoc.layout === 'compact') {
        return Math.max(8, Math.round(base * 0.75));
    }
    return base;
}

/** KantanBiz 一括請求：挨拶文・issuer 内文の共通フォントサイズ（px） */
function ktpBulkInvoiceLeadFontSizePx(bulkDoc) {
    return ktpBulkInvoiceScaledIssuerTextFontSizePx(bulkDoc);
}

/** 一括請求：「請求書在中」ラベル（issuer タイトルと同じ 2em 相当の px） */
function ktpBulkInvoiceEnvelopeLabelFontSizePx(bulkDoc) {
    return ktpBulkInvoiceScaledIssuerTextFontSizePx(bulkDoc) * 2;
}

/** KantanBiz 一括請求：issuer stack 用 CSS 変数文字列 */
function ktpBulkInvoiceEnvelopeStyleVars(bulkDoc) {
    bulkDoc = bulkDoc || {};
    var marginTop = parseInt(bulkDoc.margin_top_mm, 10);
    var marginLeft = parseInt(bulkDoc.margin_left_mm, 10);
    var marginRight = parseInt(bulkDoc.margin_right_mm, 10);
    var marginBottom = parseInt(bulkDoc.margin_bottom_mm, 10);
    var envTop = parseInt(bulkDoc.envelope_top_mm, 10);
    var envLeft = parseInt(bulkDoc.envelope_left_mm, 10);
    if (isNaN(marginTop)) { marginTop = 57; }
    if (isNaN(marginLeft)) { marginLeft = 10; }
    if (isNaN(marginRight)) { marginRight = 5; }
    if (isNaN(marginBottom)) { marginBottom = 5; }
    if (isNaN(envTop)) { envTop = 6; }
    if (isNaN(envLeft)) { envLeft = 23; }
    var issuerTextFontSize = ktpBulkInvoiceScaledIssuerTextFontSizePx(bulkDoc);
    var envelopeLabelFontSize = issuerTextFontSize * 2;
    var envelopeLabelOffsetMm = Math.max(8, Math.round(10 * envelopeLabelFontSize / 28));
    var accent = bulkDoc.accent_color || '#374151';
    return '--bulk-pad-top:' + marginTop + 'mm;--bulk-pad-right:' + marginRight + 'mm;--bulk-pad-bottom:' + marginBottom + 'mm;--bulk-pad-left:' + marginLeft + 'mm;--bulk-env-top:' + envTop + 'mm;--bulk-env-left:' + envLeft + 'mm;--bulk-accent:' + accent + ';--bulk-issuer-text-font-size:' + issuerTextFontSize + 'px;--bulk-envelope-label-offset:' + envelopeLabelOffsetMm + 'mm;--bulk-envelope-label-font-size:' + envelopeLabelFontSize + 'px;';
}

/** 一括請求・宛名の代表者行（representative_name 優先、なければ client_contact / name） */
function ktpResolveBulkAddresseeRepresentativeName(res) {
    var rep = (res.representative_name || '').trim();
    if (rep !== '') {
        return rep;
    }
    return (res.client_contact || '').trim();
}

/** 顧客フォームでチェック中の部署ID */
function ktpReadSelectedDepartmentIdFromDom() {
    var checked = document.querySelector('input.department-checkbox:checked');
    if (!checked) {
        return '';
    }
    return String(checked.dataset.departmentId || checked.value || '').trim();
}

/** 部署名＋担当者名を一括請求宛名行に整形 */
function ktpBuildBulkDepartmentContactLine(deptName, contactPerson) {
    var dept = (deptName || '').trim();
    var contact = (contactPerson || '').trim();
    if (dept === '' && contact === '') {
        return '';
    }
    if (dept === '') {
        return contact;
    }
    if (contact === '') {
        return dept;
    }
    return dept + ' ' + contact;
}

/** DOM の部署チェックから Ajax データを補完（DB 未同期時のフォールバック） */
function ktpEnrichBulkInvoiceClientData(data) {
    if (!data || typeof data !== 'object') {
        return data;
    }
    if ((data.bulk_department_contact_line || '').trim() !== '') {
        return data;
    }
    var checked = document.querySelector('input.department-checkbox:checked');
    if (!checked) {
        return data;
    }
    var row = checked.closest('tr');
    if (!row || !row.cells || row.cells.length < 3) {
        return data;
    }
    var line = ktpBuildBulkDepartmentContactLine(row.cells[1].textContent, row.cells[2].textContent);
    if (line === '') {
        return data;
    }
    var enriched = Object.assign({}, data);
    enriched.bulk_department_contact_line = line;
    enriched.has_department_contact = true;
    if (!enriched.selected_department) {
        enriched.selected_department = {
            department_name: (row.cells[1].textContent || '').trim(),
            contact_person: (row.cells[2].textContent || '').trim()
        };
    }
    return enriched;
}

/** 一括請求の宛名ブロック（請求書在中ラベル付き・担当表示切替対応） */
function ktpBuildBulkInvoiceAddresseeHtml(res, customerHonorific, t) {
    var html = '<div class="ktp-bulk-invoice-addressee ktp-bulk-invoice-address-block">';
    html += '<div class="ktp-bulk-invoice-envelope-label-slot">';
    html += '<div class="ktp-bulk-invoice-envelope-label">' + t('請求書在中') + '</div>';
    html += '</div>';

    var address = res.client_address || '';
    var postalCode = '';
    var addressWithoutPostal = address;
    var companyName = res.client_name || t('未設定');
    var unsetLabel = t('未設定');

    if (address && address.indexOf('〒') === 0) {
        var postalMatch = address.match(/〒(\d{3}-?\d{4})/);
        if (postalMatch) {
            postalCode = '〒' + postalMatch[1];
            addressWithoutPostal = address.replace(/〒\d{3}-?\d{4}\s*/, '');
        }
    }
    if (address && address.trim() !== '' && address !== unsetLabel) {
        if (postalCode) {
            html += '<div>' + ktpInvoiceEscapeHtml(postalCode) + '</div>';
        }
        if (addressWithoutPostal && addressWithoutPostal.trim() !== '') {
            html += '<div>' + ktpInvoiceEscapeHtml(addressWithoutPostal) + '</div>';
        }
    }

    html += '<div>' + ktpInvoiceEscapeHtml(companyName) + '<span class="ktp-bulk-invoice-company-honorific"> ' + t('様') + '</span></div>';

    var representativeName = ktpResolveBulkAddresseeRepresentativeName(res);
    if (representativeName !== '') {
        html += '<div class="ktp-bulk-invoice-representative-row ktp-bulk-invoice-addressee-contact-row" style="display:none;">'
            + ktpInvoiceEscapeHtml(representativeName) + customerHonorific + '</div>';
    }

    var deptContactLine = (res.bulk_department_contact_line || '').trim();
    if (deptContactLine !== '') {
        html += '<div class="ktp-bulk-invoice-department-contact-row ktp-bulk-invoice-addressee-contact-row" style="display:none;">'
            + ktpInvoiceEscapeHtml(deptContactLine) + customerHonorific + '</div>';
    }

    html += '</div>';
    return html;
}

/** 宛名ブロック: 会社名のみ / 代表者 / 部署・ご担当 の表示切替 */
function ktpApplyAddresseeContactModeToRoot(root, inputName, forcedMode) {
    inputName = inputName || 'addressee-contact-mode';
    if (!root || !root.querySelector) {
        return;
    }
    var mode = forcedMode || 'company';
    if (!forcedMode) {
        var scope = root.closest('.ktp-bulk-invoice-content-area') || root.closest('#invoiceList') || root;
        var checked = scope.querySelector('input[name="' + inputName + '"]:checked')
            || document.querySelector('input[name="' + inputName + '"]:checked');
        mode = (checked && checked.value) ? checked.value : 'company';
    }
    var showContact = mode === 'representative' || mode === 'department';

    root.querySelectorAll('.ktp-bulk-invoice-company-honorific').forEach(function(el) {
        el.style.display = showContact ? 'none' : '';
    });
    root.querySelectorAll('.ktp-bulk-invoice-representative-row').forEach(function(el) {
        el.style.display = mode === 'representative' ? 'block' : 'none';
    });
    root.querySelectorAll('.ktp-bulk-invoice-department-contact-row').forEach(function(el) {
        el.style.display = mode === 'department' ? 'block' : 'none';
    });
}

function ktpBindAddresseeContactMode(inputName) {
    inputName = inputName || 'addressee-contact-mode';
    var radios = document.querySelectorAll('input[name="' + inputName + '"]');
    if (!radios.length) {
        return function() {};
    }
    var addresseeRoot = document.querySelector('#invoiceList .ktp-bulk-invoice-addressee');

    function update() {
        if (addresseeRoot) {
            ktpApplyAddresseeContactModeToRoot(addresseeRoot, inputName);
        }
    }

    radios.forEach(function(radio) {
        radio.addEventListener('change', update);
    });
    update();
    return update;
}

function ktpBuildAddresseeContactModeFieldset(res, t, inputName) {
    inputName = inputName || 'addressee-contact-mode';
    var representativeName = ktpResolveBulkAddresseeRepresentativeName(res);
    var hasRepresentative = !!(
        res.has_representative_contact
        || representativeName !== ''
    );
    var hasDepartment = !!(
        res.has_department_contact
        || ((res.bulk_department_contact_line || '').trim() !== '')
    );
    if (!hasRepresentative && !hasDepartment) {
        return '';
    }
    var html = '<fieldset class="ktp-bulk-invoice-addressee-mode" style="margin-bottom:8px;text-align:center;font-size:14px;color:#1f2937;">';
    html += '<legend style="margin-bottom:4px;font-size:12px;color:#6b7280;">' + t('宛名のご担当') + '</legend>';
    html += '<div style="display:flex;flex-wrap:wrap;align-items:center;justify-content:center;gap:12px 24px;">';
    html += '<label style="display:inline-flex;align-items:center;gap:6px;"><input type="radio" name="' + ktpInvoiceEscapeHtml(inputName) + '" value="company" checked> ' + t('会社名のみ') + '</label>';
    if (hasRepresentative) {
        html += '<label style="display:inline-flex;align-items:center;gap:6px;"><input type="radio" name="' + ktpInvoiceEscapeHtml(inputName) + '" value="representative"> ' + t('代表者名を表示') + '</label>';
    }
    if (hasDepartment) {
        html += '<label style="display:inline-flex;align-items:center;gap:6px;"><input type="radio" name="' + ktpInvoiceEscapeHtml(inputName) + '" value="department"> ' + t('部署・ご担当を表示') + '</label>';
    }
    html += '</div></fieldset>';
    return html;
}

/**
 * 一括請求の自社情報（右上 issuer stack）— AJAX 未対応時のフォールバック
 */
function ktpBuildBulkIssuerStackHtml(bulkDoc, branding, qualifiedNumber, bankTransferHtml, legacyHtml, t) {
    t = typeof t === 'function' ? t : function (x) { return x; };
    if (!branding || !bulkDoc) {
        return legacyHtml || '';
    }
    var showLogo = !!bulkDoc.show_logo && branding.logo_data_uri;
    var showSeal = !!bulkDoc.show_seal && branding.seal_data_uri;
    var showQualified = !!bulkDoc.show_qualified_invoice_number && qualifiedNumber && String(qualifiedNumber).trim() !== '' && !(window.ktp_tax_policy && window.ktp_tax_policy.mode === 'abolished');
    var bulkBankHtml = (typeof ktpClientInvoice !== 'undefined' && ktpClientInvoice.bank_transfer_bulk_issuer_html)
        ? ktpClientInvoice.bank_transfer_bulk_issuer_html
        : ktpInvoicePlainBankTransferHtml(bankTransferHtml);
    var showBank = !!bulkDoc.show_bank_transfer && bulkBankHtml && String(bulkBankHtml).trim() !== '';
    var lh = bulkDoc.issuer_line_height || 1.35;
    var fontSize = ktpBulkInvoiceLeadFontSizePx(bulkDoc);
    var accent = bulkDoc.accent_color || '#374151';
    var title = bulkDoc.title || t('請求書');
    var name = (branding.name || '').trim();
    var hasCompany = name !== '' || branding.address_html;
    var canResize = typeof ktpClientInvoice !== 'undefined'
        && ((ktpClientInvoice.bulkSealResize && ktpClientInvoice.bulkSealResize.enabled)
            || (ktpClientInvoice.bulkLogoResize && ktpClientInvoice.bulkLogoResize.enabled));
    if (!showLogo && !showSeal && !hasCompany && !showQualified && !showBank && !legacyHtml) {
        return '';
    }

    var logoPercent = parseInt(bulkDoc.issuer_logo_width_percent, 10);
    if (isNaN(logoPercent) || logoPercent < 15) {
        logoPercent = 100;
    }
    if (logoPercent > 100) {
        logoPercent = 100;
    }
    var logoAlign = bulkDoc.issuer_logo_align === 'right' ? 'right' : 'left';
    var logoCss = 'display:block;width:' + logoPercent + '%;max-width:100%;height:auto;object-fit:contain;'
        + (logoAlign === 'right' ? 'object-position:right center;margin-left:auto;' : 'object-position:left center;margin-left:0;')
        + 'margin-right:0;';

    var sealSettingW = parseInt(bulkDoc.seal_max_width_px, 10) || 48;
    var sealSettingH = parseInt(bulkDoc.seal_max_height_px, 10) || 48;
    var sealOverlayScale = (typeof ktpClientInvoice !== 'undefined' && ktpClientInvoice.bulkSealResize && ktpClientInvoice.bulkSealResize.overlayScale)
        ? Number(ktpClientInvoice.bulkSealResize.overlayScale)
        : (6 * 0.7 * 0.9);
    var sealOverlayMax = (typeof ktpClientInvoice !== 'undefined' && ktpClientInvoice.bulkSealResize && ktpClientInvoice.bulkSealResize.overlayMax)
        ? Number(ktpClientInvoice.bulkSealResize.overlayMax)
        : 454;
    var sealDisplayW = Math.max(16, Math.min(sealOverlayMax, Math.round(sealSettingW * sealOverlayScale)));
    var sealDisplayH = Math.max(16, Math.min(sealOverlayMax, Math.round(sealSettingH * sealOverlayScale)));
    var sealCss = 'display:block;width:' + sealDisplayW + 'px;height:' + sealDisplayH + 'px;object-fit:contain;object-position:center center;';

    var html = '<div class="ktp-bulk-invoice-issuer-stack" aria-hidden="false">';
    html += '<div class="ktp-bulk-invoice-issuer-inner ktp-bulk-invoice-company-info" style="font-size:' + fontSize + 'px;color:#374151;line-height:' + lh + ';">';
    html += '<div class="ktp-bulk-invoice-issuer-doc-title" style="color:' + ktpInvoiceEscapeHtml(accent) + ';">';
    html += '<span class="ktp-bulk-invoice-issuer-doc-title-ornament" aria-hidden="true"><span></span><span></span><span></span></span>';
    html += '<span class="ktp-bulk-invoice-issuer-doc-title-text">' + ktpInvoiceEscapeHtml(title) + '</span>';
    html += '<span class="ktp-bulk-invoice-issuer-doc-title-ornament" aria-hidden="true"><span></span><span></span><span></span></span>';
    html += '</div>';
    if (showLogo) {
        var logoClass = 'ktp-bulk-invoice-issuer-logo-img' + (canResize ? ' is-resizable' : '');
        var logoAttrs = canResize
            ? ' data-ktp-bulk-logo-resize="1" data-width-percent="' + logoPercent + '" data-logo-align="' + logoAlign + '"'
            : '';
        html += '<div class="ktp-bulk-invoice-issuer-logo-wrap"><img src="' + branding.logo_data_uri + '" alt="" class="' + logoClass + '" style="' + logoCss + '" draggable="false"' + logoAttrs + '></div>';
    }
    html += '<div class="ktp-bulk-invoice-issuer-text-block">';
    if (showQualified) {
        html += '<div class="ktp-bulk-invoice-issuer-registration">' + t('登録番号：') + ktpInvoiceEscapeHtml(qualifiedNumber) + '</div>';
    }
    if (hasCompany || showBank || showSeal || legacyHtml) {
        html += '<div class="ktp-bulk-invoice-issuer-seal-scope">';
        if (name) {
            html += '<div style="font-weight:bold;">' + ktpInvoiceEscapeHtml(name) + '</div>';
            if (branding.address_html) {
                html += '<div>' + branding.address_html + '</div>';
            }
        } else if (legacyHtml) {
            html += legacyHtml;
        }
        if (showBank) {
            html += '<div class="ktp-bulk-invoice-issuer-bank">' + bulkBankHtml + '</div>';
        }
        if (showSeal) {
            var sealClass = 'ktp-bulk-invoice-issuer-seal-overlay' + (canResize ? ' is-resizable' : '');
            var sealAttrs = canResize
                ? ' data-ktp-bulk-seal-resize="1" data-setting-width="' + sealSettingW + '" data-setting-height="' + sealSettingH + '" data-display-width="' + sealDisplayW + '" data-display-height="' + sealDisplayH + '"'
                : '';
            html += '<img src="' + branding.seal_data_uri + '" alt="" class="' + sealClass + '" style="' + sealCss + '" draggable="false"' + sealAttrs + '>';
        }
        html += '</div>';
    }
    html += '</div></div></div>';
    return html;
}

/** @deprecated 後方互換 */
function ktpBuildBulkIssuerCompanyHtml(bulkDoc, branding, legacyHtml) {
    return ktpBuildBulkIssuerStackHtml(bulkDoc, branding, '', '', legacyHtml, function (x) { return x; });
}

/** 二重枠を避けるため振込ブロックの外側 div を外す */
function ktpInvoiceUnwrapBankTransferBox(html) {
    var str = String(html || '').trim();
    var m = str.match(/^<div\s+class="ktp-invoice-bank-transfer"[^>]*>([\s\S]*)<\/div>\s*$/i);
    return m ? m[1].trim() : str;
}

/** 一括請求 issuer stack 用の振込先 HTML（Ajax 未対応時は localized データを優先） */
function ktpInvoicePlainBankTransferHtml(html) {
    if (typeof ktpClientInvoice !== 'undefined' && ktpClientInvoice.bank_transfer_bulk_issuer_html) {
        return ktpClientInvoice.bank_transfer_bulk_issuer_html;
    }
    return '';
}

jQuery(document).ready(function($) {
    //
    // 請求書発行機能
    //
    (function() {
        if (typeof ktpClientInvoice === 'undefined') {
            console.error("[請求書発行] Localized script object 'ktpClientInvoice' not found.");
            return;
        }

        // デザイン設定をグローバル変数として設定
        window.ktp_design_settings = ktpClientInvoice.design_settings;
        
        var ajaxurl = ktpClientInvoice.ajax_url;
        var t = function(text) { return typeof ktpwpTranslate === 'function' ? ktpwpTranslate(text) : text; };
        /** 請求プレビュー用: MySQL ゼロ日付などは「未設定」表示 */
        var formatInvoiceCompletionDate = function(d) {
            var s = d != null ? String(d).trim() : '';
            if (!s || s.indexOf('0000-00-00') === 0) {
                return t('未設定');
            }
            return s.replace(/\s00:00:00$/, '');
        };
        var currentLocale = (window.ktpwpI18n && window.ktpwpI18n.locale) || document.documentElement.lang || '';
        var customerHonorific = /^ja/i.test(currentLocale) ? ' 様' : '';
        var ccyForCarryover = (window.ktpwpI18n && window.ktpwpI18n.currency) || {};
        var carryoverSym = (ccyForCarryover.symbol != null && String(ccyForCarryover.symbol).trim() !== '') ? String(ccyForCarryover.symbol).trim() : '';
        var carryoverCurrencyLabel = carryoverSym !== '' ? carryoverSym : (ccyForCarryover.code === 'JPY' ? '円' : (ccyForCarryover.code || 'JPY'));
        
        // フォールバック: ktpClientInvoiceが利用できない場合の代替手段
        if (!ajaxurl) {
            if (typeof ktp_ajax_object !== 'undefined' && ktp_ajax_object.ajax_url) {
                ajaxurl = ktp_ajax_object.ajax_url;
            } else if (typeof ktpwp_ajax !== 'undefined' && ktpwp_ajax.ajax_url) {
                ajaxurl = ktpwp_ajax.ajax_url;
            } else if (typeof window.ajaxurl !== 'undefined') {
                ajaxurl = window.ajaxurl;
            } else {
                ajaxurl = '/wp-admin/admin-ajax.php';
            }
        }

        var invoiceButton = document.getElementById("invoiceButton");
        var popup = document.getElementById("ktp-invoice-preview-popup");
        var list = document.getElementById("invoiceList");

        if (invoiceButton && popup && list) {
            function ensureInvoicePopupOnBody() {
                if (popup.parentNode !== document.body) {
                    document.body.appendChild(popup);
                }
            }

            ensureInvoicePopupOnBody();

            // ポップアップを閉じる関数
            function closeInvoicePopup() {
                popup.style.display = "none";
            }

            // ポップアップ外クリックで閉じる機能
            popup.addEventListener("click", function(e) {
                if (e.target === popup) {
                    closeInvoicePopup();
                }
            });

            // Escapeキーで閉じる機能
            document.addEventListener("keydown", function(e) {
                if (e.key === "Escape" && popup.style.display === "block") {
                    closeInvoicePopup();
                }
            });

            // 閉じるボタンのイベントハンドラー
            document.addEventListener("click", function(e) {
                if (e.target && e.target.id === "ktp-invoice-preview-close") {
                    closeInvoicePopup();
                }
            });

            invoiceButton.addEventListener("click", function() {
                ensureInvoicePopupOnBody();
                popup.style.display = "block";
                
                var xhr = new XMLHttpRequest();
                xhr.open("POST", ajaxurl, true);
                xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
                xhr.onerror = function() {
                    console.error("[請求書発行] Ajax通信エラー");
                    list.innerHTML = "<div style=\"color:#c00;\">" + t("通信エラーが発生しました。ページを再読み込みして再度お試しください。") + "</div>";
                };
                xhr.onload = function() {
                    console.log("[請求書発行] Ajaxレスポンス受信:", xhr.status, xhr.responseText);
                    if (xhr.status === 200) {
                        try {
                            var res = JSON.parse(xhr.responseText);
                            console.log("[請求書発行] レスポンス解析結果:", res);
                            if (res.success && res.data && res.data.monthly_groups && res.data.monthly_groups.length > 0) {
                                res.data = ktpEnrichBulkInvoiceClientData(res.data);
                                var bulkDocSettings = (typeof ktpClientInvoice !== 'undefined' && ktpClientInvoice.document_settings && ktpClientInvoice.document_settings.bulk_invoice)
                                    ? ktpClientInvoice.document_settings.bulk_invoice
                                    : {};
                                window.invoiceClientName = res.data.client_name || t('未設定');

                                var html = '<div class="ktp-bulk-invoice-content-area ktp-bulk-invoice-envelope-page ktp-bulk-invoice-envelope-page--labeled" style="' + ktpBulkInvoiceEnvelopeStyleVars(bulkDocSettings) + '">';
                                html += ktpBuildBulkInvoiceAddresseeHtml(res.data, customerHonorific, t);

                                var issuerStackHtml = (res.data.issuer_stack_html && String(res.data.issuer_stack_html).trim() !== '')
                                    ? res.data.issuer_stack_html
                                    : ktpBuildBulkIssuerStackHtml(
                                        bulkDocSettings,
                                        (typeof ktpClientInvoice !== 'undefined' ? ktpClientInvoice.branding : null),
                                        res.data.qualified_invoice_number || '',
                                        res.data.bank_transfer_html || '',
                                        res.data.company_info ? ktpInvoiceUnwrapCompanyInfoBox(res.data.company_info) : '',
                                        t
                                    );
                                if (issuerStackHtml) {
                                    html += issuerStackHtml;
                                }

                                html += '<div class="ktp-bulk-invoice-print-except-addressee">';

                                var documentLead = (res.data.document_lead && String(res.data.document_lead).trim() !== '')
                                    ? res.data.document_lead
                                    : ((bulkDocSettings.lead && String(bulkDocSettings.lead).trim() !== '')
                                        ? bulkDocSettings.lead
                                        : t('この度はご用命いただき誠にありがとうございました。 以下の通りご請求させていただきますので、よろしくお願い申し上げます。'));
                                var leadFontSize = ktpBulkInvoiceLeadFontSizePx(bulkDocSettings);
                                html += '<p class="ktp-bulk-invoice-document-lead" style="font-size:' + leadFontSize + 'px;">' + ktpInvoiceEscapeHtml(documentLead) + '</p>';

                                // 消費税対応の全体合計計算
                                var grandTotal = 0;
                                var grandSubtotal = 0;
                                var grandTaxAmount = 0;
                                
                                res.data.monthly_groups.forEach(function(group) {
                                    grandSubtotal += (group.subtotal || 0);
                                    grandTaxAmount += (group.tax_amount || 0);
                                    grandTotal += (group.subtotal || 0) + (group.tax_amount || 0);
                                });

                                // 税区分に応じた表示（税廃止時は税情報を抑止）
                                var taxCategory = res.data.tax_category || '内税';
                                var suppressTax = !!(window.ktp_tax_policy && (window.ktp_tax_policy.mode === 'abolished' || window.ktp_tax_policy.hide_columns));
                                console.log("[請求書発行] 税区分:", taxCategory);

                                var allPreviewItems = [];
                                res.data.monthly_groups.forEach(function (g) {
                                    (g.orders || []).forEach(function (ord) {
                                        (ord.invoice_items || []).forEach(function (it) {
                                            allPreviewItems.push(it);
                                        });
                                    });
                                });
                                var grandTaxSuffix = suppressTax ? '' : ktpInvoiceTaxInlineSuffixFromItems(allPreviewItems, taxCategory, t);

                                var paymentDueDate = res.data.payment_due_date || '';
                                html += '<div class="ktp-bulk-invoice-summary-box ktp-biz-summary-box">';
                                html += "<div style=\"display:flex;flex-wrap:wrap;align-items:center;column-gap:24px;row-gap:8px;\">";
                                html += "<div style=\"display:inline-flex;align-items:center;gap:8px;\">";
                                html += "<span>" + t("繰越金額：") + "</span>";
                                html += "<input type=\"number\" id=\"carryover-amount\" name=\"carryover_amount\" value=\"0\" min=\"0\" step=\"1\" style=\"width:100px;padding:4px 8px;border:1px solid #d1d5db;border-radius:6px;font-size:14px;text-align:right;background:#fff;\" onchange=\"updateInvoiceTotal()\">";
                                html += "<span>" + carryoverCurrencyLabel + "</span>";
                                html += "</div>";
                                html += "<div class=\"ktp-biz-invoice-amount-box\" style=\"margin-left:16px;border:1px solid #bae6fd;background-color:#e0f2fe;border-radius:6px;padding:6px 12px;font-weight:800;display:inline-flex;align-items:baseline;flex-wrap:wrap;gap:4px;\">";
                                html += t("請求金額：") + "<span id=\"total-amount\" style=\"font-size:20px;font-weight:800;color:#0369a1;\">" + ktpwpFormatMoney(grandTotal) + "</span>";
                                html += "<span class=\"ktp-invoice-tax-inline-suffix\" style=\"font-size:14px;font-weight:700;color:#1e293b;\">" + grandTaxSuffix + "</span>";
                                html += "</div>";
                                html += "<div class=\"ktp-biz-payment-due\" style=\"flex-basis:100%;\">";
                                html += t("お支払い期日：") + "<input type=\"date\" id=\"payment-due-date-input\" value=\"" + paymentDueDate + "\" style=\"margin-left:8px;font-size:14px;padding:4px 8px;border:1px solid #d1d5db;border-radius:6px;max-width:100%;\">";
                                html += "</div>";
                                html += "</div></div>";

                                window.invoiceGrandTotal = grandTotal;
                                window.invoiceTaxAmount = grandTaxAmount; // 消費税をグローバルに設定
                                window.invoiceTaxCategory = res.data.tax_category;

                                var oddRowColor = window.ktp_design_settings.odd_row_color || "#E7EEFD";
                                var evenRowColor = window.ktp_design_settings.even_row_color || "#FFFFFF";
                                var hideTaxCols = !!(window.ktp_tax_policy && window.ktp_tax_policy.hide_tax_columns);
                                var showTaxRateCol = !hideTaxCols && bulkDocSettings.show_tax_column !== false;
                                var defaultShowTaxAmount = !!bulkDocSettings.show_tax_amount_column;

                                function formatDecimalDisplay(value) {
                                    if (value === '' || value === null || value === undefined) {
                                        return '';
                                    }
                                    var num = parseFloat(value);
                                    if (isNaN(num)) {
                                        return value;
                                    }
                                    return num.toFixed(6).replace(/\.?0+$/, '');
                                }

                                function renderInvoiceItemsTable(items, res, taxCategory, tableOptions) {
                                    var html = '';
                                    var subtotal = 0;
                                    if (!items || !items.length) {
                                        return { html: html, subtotal: subtotal, items: [] };
                                    }

                                    html += "<div style=\"overflow-x:auto;\">";
                                    html += "<table class=\"ktp-biz-items-table\" style=\"width:100%;border-collapse:collapse;font-size:14px;\">";
                                    html += "<thead class=\"ktp-bulk-items-table-head\"><tr style=\"color:#4b5563;border-bottom:1px solid #fecdd3;background-color:#ffeef1;\">";
                                    html += "<th style=\"text-align:left;padding:8px 12px;width:3.5rem;\">No.</th>";
                                    html += "<th style=\"text-align:left;padding:8px 12px;\">" + t("サービス") + "</th>";
                                    html += "<th style=\"text-align:right;padding:8px 12px;white-space:nowrap;\">" + t("単価") + "</th>";
                                    html += "<th style=\"text-align:right;padding:8px 12px;white-space:nowrap;\">" + t("数量/単位") + "</th>";
                                    html += "<th style=\"text-align:right;padding:8px 12px;\">" + t("金額") + "</th>";
                                    if (!tableOptions.hideTaxCols) {
                                        html += "<th class=\"ktp-bulk-tax-amount-col\" style=\"text-align:right;padding:8px 12px;\">" + t("税額") + "</th>";
                                    }
                                    if (tableOptions.showTaxRateCol) {
                                        html += "<th style=\"text-align:right;padding:8px 12px;\">" + t("税率") + "</th>";
                                    }
                                    html += "<th style=\"text-align:left;padding:8px 12px;white-space:nowrap;\">" + t("備考") + "</th>";
                                    html += "</tr></thead><tbody>";

                                    items.forEach(function(item, index) {
                                        var productName = item.product_name || "";
                                        var priceNum = parseFloat(item.price) || 0;
                                        var qtyNum = parseFloat(item.quantity) || 0;
                                        var amount = item.amount ? parseFloat(item.amount) : 0;
                                        var isSectionTitle = productName.trim() !== "" && priceNum === 0 && qtyNum === 0;
                                        var unitPrice = "";
                                        if (!(productName.trim() !== "" && priceNum === 0)) {
                                            unitPrice = priceNum ? ktpwpFormatMoney(priceNum) : "";
                                        }
                                        var quantityUnit = "";
                                        if (isSectionTitle) {
                                            quantityUnit = "";
                                        } else {
                                            var quantity = qtyNum ? formatDecimalDisplay(qtyNum) : "";
                                            var unitPart = item.unit || t("式");
                                            if (quantity === "" && (!item.unit || String(item.unit).trim() === "")) {
                                                quantityUnit = "";
                                            } else if (quantity === "") {
                                                quantityUnit = ktpInvoiceEscapeHtml(unitPart);
                                            } else if (!item.unit || String(item.unit).trim() === "") {
                                                quantityUnit = quantity;
                                            } else {
                                                quantityUnit = quantity + "/" + ktpInvoiceEscapeHtml(item.unit);
                                            }
                                        }
                                        var totalPrice = (!isSectionTitle && amount > 0) ? ktpwpFormatMoney(amount) : "";
                                        var itemTaxRateRaw = item.tax_rate;
                                        var itemTaxRate = null;
                                        if (window.ktp_tax_policy) {
                                            if (window.ktp_tax_policy.mode === 'abolished') {
                                                itemTaxRate = 0;
                                            } else if (window.ktp_tax_policy.mode === 'unified') {
                                                itemTaxRate = parseFloat(window.ktp_tax_policy.unified_tax_rate || 0);
                                            } else if (itemTaxRateRaw !== null && itemTaxRateRaw !== '' && !isNaN(parseFloat(itemTaxRateRaw))) {
                                                itemTaxRate = parseFloat(itemTaxRateRaw);
                                            }
                                        } else if (itemTaxRateRaw !== null && itemTaxRateRaw !== '' && !isNaN(parseFloat(itemTaxRateRaw))) {
                                            itemTaxRate = parseFloat(itemTaxRateRaw);
                                        }
                                        var taxRateDisplay = "";
                                        if (!isSectionTitle && itemTaxRate !== null && !isNaN(itemTaxRate) && itemTaxRate >= 0) {
                                            taxRateDisplay = itemTaxRate + "%";
                                        }
                                        var lineTaxAmountDisplay = "";
                                        if (!isSectionTitle && !tableOptions.hideTaxCols && itemTaxRate !== null && !isNaN(itemTaxRate) && itemTaxRate >= 0 && amount > 0) {
                                            if (itemTaxRate === 0) {
                                                lineTaxAmountDisplay = "—";
                                            } else if (res.data.tax_category === "外税") {
                                                lineTaxAmountDisplay = ktpwpFormatMoney(Math.ceil(amount * (itemTaxRate / 100)));
                                            } else {
                                                lineTaxAmountDisplay = ktpwpFormatMoney(Math.ceil(amount * (itemTaxRate / 100) / (1 + itemTaxRate / 100)));
                                            }
                                        }
                                        if (amount > 0) {
                                            subtotal += amount;
                                        }
                                        var rowBg = (index % 2 === 0) ? tableOptions.evenRowColor : tableOptions.oddRowColor;
                                        html += "<tr class=\"ktp-biz-inv-row\" style=\"border-bottom:1px solid #f3f4f6;background-color:" + rowBg + ";\">";
                                        html += "<td style=\"padding:8px 12px;color:#374151;\">" + (index + 1) + "</td>";
                                        html += "<td style=\"padding:8px 12px;color:#111827;\">" + ktpInvoiceEscapeHtml(productName) + "</td>";
                                        html += "<td style=\"padding:8px 12px;text-align:right;color:#374151;white-space:nowrap;\">" + unitPrice + "</td>";
                                        html += "<td style=\"padding:8px 12px;text-align:right;color:#374151;white-space:nowrap;\">" + quantityUnit + "</td>";
                                        html += "<td style=\"padding:8px 12px;text-align:right;color:#111827;white-space:nowrap;\">" + totalPrice + "</td>";
                                        if (!tableOptions.hideTaxCols) {
                                            html += "<td class=\"ktp-bulk-tax-amount-col\" style=\"padding:8px 12px;text-align:right;color:#374151;white-space:nowrap;\">" + lineTaxAmountDisplay + "</td>";
                                        }
                                        if (tableOptions.showTaxRateCol) {
                                            html += "<td style=\"padding:8px 12px;text-align:right;color:#374151;white-space:nowrap;\">" + taxRateDisplay + "</td>";
                                        }
                                        var remarksDisplay = "";
                                        if (!isSectionTitle) {
                                            remarksDisplay = item.remarks ? ktpInvoiceEscapeHtml(item.remarks) : "—";
                                        }
                                        html += "<td style=\"padding:8px 12px;color:#374151;\">" + remarksDisplay + "</td>";
                                        html += "</tr>";
                                    });

                                    html += "</tbody></table></div>";
                                    return { html: html, subtotal: subtotal, items: items };
                                }

                                function renderOrderBlock(order, res, taxCategory, tableOptions, customerHonorific) {
                                    var blockHtml = "";
                                    var deptLine = "";
                                    if (res.data.selected_department) {
                                        deptLine = t("部署：") + ktpInvoiceEscapeHtml(res.data.selected_department.department_name || "") + " ／ " + t("ご担当者名：") + ktpInvoiceEscapeHtml(res.data.selected_department.contact_person || "") + customerHonorific;
                                    } else {
                                        var cn = (res.data.client_contact || "").trim();
                                        deptLine = t("部署：") + t("代表窓口") + " ／ " + t("ご担当者名：") + (cn ? cn + customerHonorific : "—");
                                    }
                                    var invoicedBadge = parseInt(order.progress, 10) === 5
                                        ? "<span style=\"margin-left:8px;display:inline-flex;align-items:center;border-radius:9999px;background-color:#fef3c7;padding:2px 8px;font-size:11px;font-weight:700;color:#92400e;\">" + t("請求済（入金予定日超過）") + "</span>"
                                        : "";
                                    var recurringBadge = parseInt(order.contract_id, 10) > 0
                                        ? "<span class=\"ktp-recurring-badge\" style=\"margin-left:8px;\">" + t("定期") + "</span>"
                                        : "";

                                    blockHtml += '<div class="ktp-bulk-invoice-order-card">';
                                    blockHtml += '<div class="ktp-bulk-invoice-order-card-header">';
                                    blockHtml += 'ID: ' + order.id + ' - ' + ktpInvoiceEscapeHtml(order.project_name || '') + t('（完了日：') + formatInvoiceCompletionDate(order.completion_date) + '）' + recurringBadge + invoicedBadge;
                                    blockHtml += '<div style="margin-top:4px;font-size:12px;font-weight:normal;color:#4b5563;">' + deptLine + '</div>';
                                    blockHtml += '</div>';

                                    var tableResult = renderInvoiceItemsTable(order.invoice_items || [], res, taxCategory, tableOptions);
                                    if (tableResult.html) {
                                        blockHtml += tableResult.html;
                                        blockHtml += '<div class="ktp-bulk-invoice-order-card-footer">';
                                        blockHtml += t('案件合計：') + ktpwpFormatMoney(tableResult.subtotal) + ktpInvoiceTaxInlineSuffixFromItems(tableResult.items, taxCategory, t);
                                        blockHtml += '</div>';
                                    } else {
                                        blockHtml += "<div style=\"padding:12px;color:#6b7280;font-size:14px;\">" + t("請求項目なし") + "</div>";
                                    }
                                    blockHtml += "</div>";

                                    return {
                                        html: blockHtml,
                                        subtotal: tableResult.subtotal,
                                        items: tableResult.items
                                    };
                                }

                                var tableOptions = {
                                    hideTaxCols: hideTaxCols,
                                    showTaxRateCol: showTaxRateCol,
                                    oddRowColor: oddRowColor,
                                    evenRowColor: evenRowColor
                                };

                                html += '<div class="ktp-bulk-invoice-order-groups">';
                                res.data.monthly_groups.forEach(function(group) {
                                    html += '<section class="ktp-bulk-invoice-order-group">';
                                    html += '<div class="ktp-bulk-invoice-order-group-header">';
                                    if (group.is_carryover) {
                                        html += '<span style="display:inline-flex;align-items:center;border-radius:9999px;background-color:#fef3c7;padding:2px 8px;font-size:11px;font-weight:700;color:#92400e;margin-right:8px;">' + t('請求残') + '</span>';
                                    }
                                    html += '【' + group.billing_period + '】' + t('締日：') + group.closing_date + ' ' + t('案件数：') + group.orders.length + t('件');
                                    html += '</div>';
                                    html += '<div class="ktp-bulk-invoice-order-group-body">';

                                    var monthlyTotal = 0;
                                    var monthGroupItems = [];
                                    var sections = group.sections && group.sections.length
                                        ? group.sections
                                        : [{ key: 'spot', label: '', orders: group.orders || [] }];

                                    sections.forEach(function(section) {
                                        if (section.label) {
                                            html += "<div class=\"ktp-bulk-invoice-section__title\">" + ktpInvoiceEscapeHtml(section.label) + "</div>";
                                        }

                                        if (section.key === 'initial' && section.lines && section.lines.length) {
                                            var initialItems = section.lines.map(function(line) {
                                                return {
                                                    product_name: line.product_name,
                                                    price: line.price,
                                                    quantity: line.quantity,
                                                    unit: line.unit,
                                                    amount: line.amount,
                                                    tax_rate: line.tax_rate,
                                                    remarks: line.remarks
                                                };
                                            });
                                            html += "<div style=\"border:1px solid #e5e7eb;border-radius:6px;overflow:hidden;\">";
                                            html += "<div style=\"padding:8px 12px;border-bottom:1px solid #e5e7eb;background-color:#fff;font-size:13px;color:#4b5563;\">";
                                            html += t("初回請求に含まれる追加費用（保証金・初期設定費など）");
                                            html += "</div>";
                                            var initialTable = renderInvoiceItemsTable(initialItems, res, taxCategory, tableOptions);
                                            html += initialTable.html;
                                            html += "<div style=\"padding:8px 12px;text-align:right;font-size:14px;font-weight:600;color:#111827;border-top:1px solid #f3f4f6;background-color:#fff;\">";
                                            html += t("初回のみ合計：") + ktpwpFormatMoney(initialTable.subtotal) + ktpInvoiceTaxInlineSuffixFromItems(initialTable.items, taxCategory, t);
                                            html += "</div></div>";
                                            monthlyTotal += initialTable.subtotal;
                                            monthGroupItems = monthGroupItems.concat(initialTable.items);
                                            return;
                                        }

                                        (section.orders || []).forEach(function(order) {
                                            var orderBlock = renderOrderBlock(order, res, taxCategory, tableOptions, customerHonorific);
                                            html += orderBlock.html;
                                            monthlyTotal += orderBlock.subtotal;
                                            monthGroupItems = monthGroupItems.concat(orderBlock.items);
                                        });
                                    });

                                    html += '</div>';
                                    html += '<div class="ktp-bulk-invoice-order-group-footer">';
                                    html += group.billing_period + t(' 月別合計：') + ktpwpFormatMoney(monthlyTotal) + ktpInvoiceTaxInlineSuffixFromItems(monthGroupItems, taxCategory, t);
                                    html += '</div>';
                                    html += '</section>';
                                });
                                html += '</div>';

                                html += '</div>';

                                html += '<div class="ktp-bulk-invoice-envelope-footer">';
                                html += '<div class="ktp-bulk-invoice-note-box">';
                                html += '<p class="ktp-bulk-invoice-envelope-margin-note">' + t('※ 本画面の印刷位置は、プリンタの印刷余白が上下左右いずれも10mmのときに長形３号窓明封筒の窓と揃うようレイアウトしています。') + '</p>';
                                html += ktpBuildAddresseeContactModeFieldset(res.data, t);
                                html += '<div id="ktp-invoice-footer-actions" style="margin-top:12px;text-align:center;">';
                                if (!hideTaxCols) {
                                    html += '<label style="display:block;font-size:15px;font-weight:500;margin-bottom:12px;">';
                                    html += '<input type="checkbox" id="show-tax-amount-column" style="width:18px;height:18px;margin-right:8px;vertical-align:middle;"' + (defaultShowTaxAmount ? ' checked' : '') + '>';
                                    html += t('請求行の税額を表示・印刷する');
                                    html += '</label>';
                                }
                                html += '<label style="display:inline-flex;align-items:center;font-size:15px;font-weight:500;margin-bottom:12px;">';
                                html += '<input type="checkbox" id="set-invoice-completed" style="width:18px;height:18px;margin-right:8px;">';
                                html += t('対象受注書の進捗を「請求済」に変更する');
                                html += '</label><br />';
                                html += '<div class="ktp-invoice-output-actions" style="display:flex;flex-wrap:wrap;justify-content:center;gap:8px;margin-top:4px;">';
                                html += '<button type="button" id="ktp-invoice-print-btn" class="ktp-invoice-output-btn ktp-invoice-output-btn--print" data-client-name="' + ktpInvoiceEscapeHtml(res.data.client_name || window.invoiceClientName || '') + '">';
                                html += (typeof KTPSvgIcons !== 'undefined' ? KTPSvgIcons.getIcon('print', {'style': 'font-size:16px;vertical-align:middle;margin-right:5px;'}) : '<span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle;margin-right:5px;">print</span>');
                                html += t('印刷');
                                html += '</button>';
                                html += '<button type="button" id="ktp-invoice-pdf-btn" class="ktp-invoice-output-btn ktp-invoice-output-btn--pdf" data-client-name="' + ktpInvoiceEscapeHtml(res.data.client_name || window.invoiceClientName || '') + '">';
                                html += (typeof KTPSvgIcons !== 'undefined' ? KTPSvgIcons.getIcon('picture_as_pdf', {'style': 'font-size:16px;vertical-align:middle;margin-right:5px;'}) : '<span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle;margin-right:5px;">picture_as_pdf</span>');
                                html += t('PDF保存');
                                html += '</button>';
                                html += '</div>';
                                html += '</div>';
                                html += '</div>';
                                html += '</div>';
                                html += '</div>';

                                list.innerHTML = html;
                                ktpBindAddresseeContactMode('addressee-contact-mode');
                                list.classList.toggle('ktp-bulk-show-tax-amount', defaultShowTaxAmount);
                                var showTaxAmountCheckbox = document.getElementById('show-tax-amount-column');
                                function updateKtpBulkTaxAmountVisibility() {
                                    list.classList.toggle('ktp-bulk-show-tax-amount', !!(showTaxAmountCheckbox && showTaxAmountCheckbox.checked));
                                }
                                if (showTaxAmountCheckbox) {
                                    showTaxAmountCheckbox.addEventListener('change', updateKtpBulkTaxAmountVisibility);
                                    updateKtpBulkTaxAmountVisibility();
                                }
                                if (typeof bindKtpBulkInvoiceOutputButtons === 'function') {
                                    bindKtpBulkInvoiceOutputButtons();
                                }
                                try {
                                    document.dispatchEvent(new CustomEvent('ktp-bulk-invoice-preview-ready', { detail: { root: list } }));
                                } catch (previewReadyErr) {
                                    console.warn('[請求書発行] preview-ready イベントの発火に失敗しました', previewReadyErr);
                                }
                            } else {
                                list.innerHTML = "<div style=\"color:#888;\">" + t("該当する案件はありません。") + "</div>";
                            }
                        } catch (e) {
                            console.error("[請求書発行] JSON解析エラー:", e);
                            console.error("[請求書発行] レスポンス内容:", xhr.responseText);
                            list.innerHTML = "<div style=\"color:#c00;\">" + t("データ取得エラー: ") + e.message + "<br>" + t("レスポンス: ") + xhr.responseText.substring(0, 200) + "</div>";
                        }
                    } else {
                        console.error("[請求書発行] HTTPエラー:", xhr.status, xhr.statusText);
                        console.error("[請求書発行] レスポンス内容:", xhr.responseText);
                        list.innerHTML = "<div style=\"color:#c00;\">" + t("通信エラー (HTTP ") + xhr.status + "): " + xhr.statusText + "<br>" + t("レスポンス: ") + xhr.responseText.substring(0, 200) + "</div>";
                    }
                };
                var clientId = "";
                var urlParams = new URLSearchParams(window.location.search);
                clientId = urlParams.get("data_id");
                console.log("[請求書発行] URLパラメータから顧客ID:", clientId);

                if (!clientId) {
                    var clientIdInput = document.getElementById("client-id-input");
                    if (clientIdInput) {
                        clientId = clientIdInput.value;
                        console.log("[請求書発行] フォームから顧客ID:", clientId);
                    }
                }

                if (!clientId) {
                    var hiddenClientId = document.querySelector("input[name=\"data_id\"]");
                    if (hiddenClientId) {
                        clientId = hiddenClientId.value;
                        console.log("[請求書発行] 隠しフィールドから顧客ID:", clientId);
                    }
                }

                if (!clientId) {
                    console.error("[請求書発行] 顧客IDが見つかりません");
                    list.innerHTML = "<div style=\"color:#c00;\">" + t("顧客IDが見つかりません。") + "</div>";
                    return;
                }

                console.log("[請求書発行] 最終的な顧客ID:", clientId);
                var nonce = ktpClientInvoice.nonce;
                
                // フォールバック: nonceが利用できない場合の代替手段
                if (!nonce) {
                    console.warn("[請求書発行] ktpClientInvoice.nonce が利用できません。代替手段を試行します。");
                    if (typeof ktp_ajax_object !== 'undefined' && ktp_ajax_object.nonce) {
                        nonce = ktp_ajax_object.nonce;
                        console.log("[請求書発行] ktp_ajax_object から nonce を取得");
                    } else if (typeof ktp_ajax_nonce !== 'undefined') {
                        nonce = ktp_ajax_nonce;
                        console.log("[請求書発行] ktp_ajax_nonce から nonce を取得");
                    } else if (typeof window.ktpwp_ajax_nonce !== 'undefined') {
                        nonce = window.ktpwp_ajax_nonce;
                        console.log("[請求書発行] window.ktpwp_ajax_nonce から nonce を取得");
                    } else {
                        console.error("[請求書発行] nonce が見つかりません。AJAXリクエストを中止します。");
                        list.innerHTML = "<div style=\"color:#c00;\">" + t("セキュリティエラー: nonceが見つかりません。") + "</div>";
                        return;
                    }
                }
                
                var params = "action=ktp_get_invoice_candidates&client_id=" + encodeURIComponent(clientId) + "&nonce=" + encodeURIComponent(nonce);
                var selectedDeptId = ktpReadSelectedDepartmentIdFromDom();
                if (selectedDeptId) {
                    params += "&selected_department_id=" + encodeURIComponent(selectedDeptId);
                }
                console.log("[請求書発行] 送信パラメータ:", params);
                xhr.send(params);
            });
        } else {
            console.error("[請求書発行] 必要な要素が見つかりません:", {
                invoiceButton: !!invoiceButton,
                popup: !!popup,
                list: !!list
            });
        }
    })();
});

/**
 * 印刷前の「請求済」確認（印刷ボタンのみ）
 * @returns {boolean}
 */
function ktpConfirmInvoicePrintWithProgress() {
    var setInvoiceCompleted = document.getElementById('set-invoice-completed');
    if (!setInvoiceCompleted || !setInvoiceCompleted.checked) {
        return true;
    }
    return window.confirm(ktpwpTranslate('本当に対象受注書の進捗を「請求済」に変更しますか？\nこの操作は取り消せません。\nOKで印刷を続行、キャンセルで中止します。'));
}

/** 印刷完了後に進捗を「請求済」へ更新 */
function ktpRunInvoiceCompletedAfterPrint() {
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '/wp-admin/admin-ajax.php');
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    var clientId = '';
    var urlParams = new URLSearchParams(window.location.search);
    clientId = urlParams.get('data_id');
    if (!clientId) {
        var clientIdInput = document.getElementById('client-id-input');
        if (clientIdInput) {
            clientId = clientIdInput.value;
        }
    }
    var params = 'action=ktp_set_invoice_completed&client_id=' + encodeURIComponent(clientId);
    xhr.onload = function() {
        if (xhr.status === 200) {
            try {
                var response = JSON.parse(xhr.responseText);
                if (response.success) {
                    updateOrderHistoryProgress(clientId, 4, 5);
                }
            } catch (e) {}
        }
    };
    xhr.send(params);
}

/**
 * プレビュー相当の印刷用 HTML を生成（KantanBiz buildBulkInvoicePrintHtml 相当）
 * @param {string} mode 'pdf' | 'print'（いずれもプレビュー同等レイアウト）
 * @returns {{ html: string, filename: string } | null}
 */
function ktpBuildBulkInvoiceOutputHtml(mode) {
    try {
        var sourceEl = document.querySelector('#invoiceList .ktp-bulk-invoice-content-area.ktp-bulk-invoice-envelope-page');
        if (!sourceEl) {
            return null;
        }

        var designSettings = window.ktp_design_settings || {};
        var oddRowColor = designSettings.odd_row_color || '#E7EEFD';
        var evenRowColor = designSettings.even_row_color || '#FFFFFF';

        var carryoverAmount = window.carryoverAmount || 0;
        var carryoverInput = document.getElementById('carryover-amount');
        if (carryoverInput) {
            carryoverAmount = parseInt(carryoverInput.value, 10) || 0;
        }

        var clone = sourceEl.cloneNode(true);

        var carryoverInputInContent = clone.querySelector('#carryover-amount');
        if (carryoverInputInContent) {
            var carryoverSpan = document.createElement('span');
            carryoverSpan.style.fontWeight = 'bold';
            carryoverSpan.textContent = carryoverAmount.toLocaleString();
            carryoverInputInContent.parentNode.replaceChild(carryoverSpan, carryoverInputInContent);
        }

        var showTaxAmountForPrint = false;
        var showTaxAmountCheckboxLive = document.getElementById('show-tax-amount-column');
        if (showTaxAmountCheckboxLive) {
            showTaxAmountForPrint = !!showTaxAmountCheckboxLive.checked;
        }

        var addresseeContactMode = 'company';
        var addresseeModeRadio = document.querySelector('input[name="addressee-contact-mode"]:checked');
        if (addresseeModeRadio && addresseeModeRadio.value) {
            addresseeContactMode = addresseeModeRadio.value;
        }

        clone.querySelectorAll('.ktp-bulk-invoice-no-print, script, style').forEach(function(el) {
            el.remove();
        });

        var footerActions = clone.querySelector('#ktp-invoice-footer-actions');
        if (footerActions) {
            var footerWrap = footerActions.closest('.ktp-bulk-invoice-envelope-footer');
            if (footerWrap && footerWrap.parentNode) {
                footerWrap.parentNode.removeChild(footerWrap);
            } else if (footerActions.parentNode) {
                footerActions.parentNode.removeChild(footerActions);
            }
        }

        var addresseeEl = clone.querySelector('.ktp-bulk-invoice-addressee');
        if (addresseeEl) {
            ktpApplyAddresseeContactModeToRoot(addresseeEl, 'addressee-contact-mode', addresseeContactMode);
        }

        var paymentDueDateInputInContent = clone.querySelector('#payment-due-date-input');
        if (paymentDueDateInputInContent) {
            var liveInput = document.getElementById('payment-due-date-input');
            var paymentDueDateValue = liveInput ? liveInput.value : paymentDueDateInputInContent.value;
            var formattedDate = paymentDueDateValue ? paymentDueDateValue.replace(/-/g, '/') : '';
            var paymentDueDateSpan = document.createElement('span');
            paymentDueDateSpan.style.fontWeight = 'bold';
            paymentDueDateSpan.textContent = formattedDate;
            paymentDueDateInputInContent.parentNode.replaceChild(paymentDueDateSpan, paymentDueDateInputInContent);
        }

        if (window.invoiceGrandTotal) {
            var totalAmountElement = clone.querySelector('#total-amount');
            if (totalAmountElement) {
                var taxCategory = window.invoiceTaxCategory || '内税';
                var taxAmount = window.invoiceTaxAmount || 0;
                if (taxCategory === '外税') {
                    var subtotal = window.invoiceGrandTotal - taxAmount;
                    totalAmountElement.textContent = (subtotal + taxAmount + carryoverAmount).toLocaleString();
                } else {
                    totalAmountElement.textContent = (window.invoiceGrandTotal + carryoverAmount).toLocaleString();
                }
            }
        }

        clone.querySelectorAll('[style*="background"]').forEach(function(row, index) {
            if (row.style.background && (row.style.background.includes('#E7EEFD') || row.style.background.includes('#FFFFFF'))) {
                row.style.background = (index % 2 === 0) ? evenRowColor : oddRowColor;
            }
        });
        clone.querySelectorAll('tr.ktp-biz-inv-row').forEach(function(tr, idx) {
            tr.style.backgroundColor = (idx % 2 === 0) ? evenRowColor : oddRowColor;
        });

        var invoiceContent = clone.outerHTML;

        var clientName = window.invoiceClientName || '請求先';
        if (!clientName || clientName === '顧客' || clientName === '未設定') {
            var companyNameElem = sourceEl.querySelector('.ktp-bulk-invoice-addressee div[style*="font-weight:bold"]');
            if (companyNameElem) {
                clientName = companyNameElem.textContent.replace(/\s*様?$/, '').trim();
            }
        }

        var today = new Date();
        var todayStr = today.getFullYear() + '-' + String(today.getMonth() + 1).padStart(2, '0') + '-' + String(today.getDate()).padStart(2, '0');

        function parseDateToTimestamp(dateStr) {
            if (!dateStr) { return null; }
            var ts = new Date(String(dateStr).trim().replace(/\//g, '-')).getTime();
            return isNaN(ts) ? null : ts;
        }

        var finalClosingDateYmd = '';
        var bestTs = null;
        clone.querySelectorAll('div').forEach(function(el) {
            var text = el && el.textContent ? el.textContent : '';
            if (!text || text.indexOf('締日：') === -1) { return; }
            var m = text.match(/締日：\s*([0-9]{4}[-\/][0-9]{1,2}[-\/][0-9]{1,2})/);
            if (!m || !m[1]) { return; }
            var ts = parseDateToTimestamp(m[1]);
            if (ts === null) { return; }
            if (bestTs === null || ts > bestTs) {
                bestTs = ts;
                finalClosingDateYmd = m[1].replace(/[^\d]/g, '');
            }
        });

        function sanitizeFilename(value) {
            return String(value)
                .replace(/[\u0000-\u001F\/\\:\uFF1A*\?"<>\|]/g, '-')
                .replace(/\s+/g, ' ')
                .trim();
        }

        var closingDateForFilename = finalClosingDateYmd || todayStr.replace(/[^\d]/g, '');
        var filename = sanitizeFilename((clientName || '請求先') + '_' + closingDateForFilename) + '.pdf';

        var previewWidth = 794;
        var measured = Math.round(sourceEl.getBoundingClientRect().width);
        if (measured > 0) {
            previewWidth = window.innerWidth < 768 ? Math.max(measured, 794) : measured;
        }

        var rootClass = 'ktp-bulk-invoice-pdf-root' + (showTaxAmountForPrint ? ' ktp-bulk-show-tax-amount' : '');
        var rootWidthAttr = ' style="width:' + previewWidth + 'px;max-width:100%;box-sizing:border-box;"';

        var printHTML = '<!DOCTYPE html><html lang="ja" style="color-scheme:light"><head><meta charset="UTF-8">';
        printHTML += '<meta name="viewport" content="width=' + previewWidth + '">';
        printHTML += '<title>' + filename + '</title>';
        printHTML += '<style id="ktp-bulk-invoice-pdf-mirror-styles">';
        printHTML += 'html,body.ktp-bulk-invoice-print-document{margin:0;padding:0;background:#fff!important;color:#111827;color-scheme:light;font-family:"Noto Sans JP","Hiragino Kaku Gothic ProN","Yu Gothic",Meiryo,sans-serif;font-size:14px;line-height:1.5;-webkit-print-color-adjust:exact;print-color-adjust:exact;}';
        printHTML += ktpBulkInvoicePdfMirrorStylesCss();
        printHTML += ktpBulkInvoiceOrderLayoutPrintCss('.ktp-bulk-invoice-pdf-root');
        printHTML += '</style></head>';
        printHTML += '<body class="ktp-bulk-invoice-print-document ktp-bulk-invoice-pdf-save-mode">';
        printHTML += '<div class="' + rootClass + '"' + rootWidthAttr + '>' + invoiceContent + '</div>';
        printHTML += '</body></html>';

        return { html: printHTML, filename: filename, previewWidth: previewWidth };
    } catch (error) {
        console.error('[請求書] 印刷HTML生成エラー:', error);
        return null;
    }
}

/**
 * @param {string} outputMode 'print' | 'pdf'
 */
function printInvoiceContent(outputMode) {
    var mode = (outputMode === 'pdf') ? 'pdf' : 'print';
    if (mode === 'print' && !ktpConfirmInvoicePrintWithProgress()) {
        return;
    }
    if (typeof window.KTPBulkInvoicePrint !== 'undefined') {
        if (mode === 'pdf') {
            window.KTPBulkInvoicePrint.startPdfSave(document.getElementById('ktp-invoice-pdf-btn'));
        } else {
            window.KTPBulkInvoicePrint.startPrint(document.getElementById('ktp-invoice-print-btn'));
        }
        return;
    }
    alert(ktpwpTranslate('印刷機能の読み込みに失敗しました。ページを再読み込みしてください。'));
}



function updateInvoiceTotal() {
    var carryoverAmount = parseInt(document.getElementById("carryover-amount").value) || 0;
    var grandTotal = window.invoiceGrandTotal || 0;
    var taxAmount = window.invoiceTaxAmount || 0;
    var taxCategory = window.invoiceTaxCategory || '内税';
    
    var totalAmountElement = document.getElementById("total-amount");
    if (totalAmountElement) {
        if (taxCategory === '外税') {
            // 外税の場合：税抜き合計 + 消費税 + 繰越金額
            var subtotal = grandTotal - taxAmount; // 税抜き合計を計算
            var totalWithTax = subtotal + taxAmount + carryoverAmount;
            totalAmountElement.textContent = totalWithTax.toLocaleString();
        } else {
            // 内税の場合：税込合計 + 繰越金額
            var totalWithCarryover = grandTotal + carryoverAmount;
            totalAmountElement.textContent = totalWithCarryover.toLocaleString();
        }
    }
    window.carryoverAmount = carryoverAmount;
}

// 入力変更時に値を即時反映（例：印刷時や他の参照用にwindow.paymentDueDateを更新）
setTimeout(function() {
    var paymentDueDateInput = document.getElementById('payment-due-date-input');
    if (paymentDueDateInput) {
        window.paymentDueDate = paymentDueDateInput.value;
        paymentDueDateInput.addEventListener('change', function() {
            window.paymentDueDate = paymentDueDateInput.value;
        });
    }
}, 100); 

/**
 * 注文履歴リストの進捗表示を即座に更新
 * @param {string} clientId - 顧客ID
 * @param {number} oldProgress - 変更前の進捗
 * @param {number} newProgress - 変更後の進捗
 */
function updateOrderHistoryProgress(clientId, oldProgress, newProgress) {
    console.log('[UI更新] 注文履歴リストの進捗更新開始:', {
        clientId: clientId,
        oldProgress: oldProgress,
        newProgress: newProgress
    });
    
    // 進捗ラベルの定義
    var progressLabels = {
        1: '受付中',
        2: '見積中',
        3: '受注',
        4: '完了',
        5: '請求済',
        6: '入金済',
        7: 'ボツ'
    };
    
    // 注文履歴リストの各項目を確認・更新
    var orderListItems = document.querySelectorAll('.ktp_data_list_item');
    var updatedCount = 0;
    
    orderListItems.forEach(function(item) {
        // 進捗表示要素を探す
        var progressElement = item.querySelector('.status-' + oldProgress);
        if (progressElement) {
            console.log('[UI更新] 進捗要素発見:', progressElement.textContent);
            
            // 進捗表示を更新
            progressElement.textContent = progressLabels[newProgress] || '不明';
            progressElement.className = progressElement.className.replace('status-' + oldProgress, 'status-' + newProgress);
            
            updatedCount++;
            console.log('[UI更新] 進捗更新完了:', progressElement.textContent);
        }
    });
    
    // 代替方法: spanタグで進捗が表示されている場合
    if (updatedCount === 0) {
        var progressSpans = document.querySelectorAll('span[class*="status-"]');
        progressSpans.forEach(function(span) {
            if (span.className.includes('status-' + oldProgress)) {
                span.textContent = progressLabels[newProgress] || '不明';
                span.className = span.className.replace('status-' + oldProgress, 'status-' + newProgress);
                updatedCount++;
                console.log('[UI更新] span進捗更新完了:', span.textContent);
            }
        });
    }
    
    // より広範囲な検索: 「完了」テキストを含む要素を探す
    if (updatedCount === 0 && oldProgress === 4) {
        var allSpans = document.querySelectorAll('span');
        allSpans.forEach(function(span) {
            if (span.textContent.trim() === progressLabels[oldProgress]) {
                // 親要素がリストアイテムかどうか確認
                var listItem = span.closest('.ktp_data_list_item');
                if (listItem) {
                    span.textContent = progressLabels[newProgress] || '不明';
                    // クラスが存在する場合は更新
                    if (span.className.includes('status-')) {
                        span.className = span.className.replace('status-' + oldProgress, 'status-' + newProgress);
                    }
                    updatedCount++;
                    console.log('[UI更新] テキスト検索による進捗更新完了:', span.textContent);
                }
            }
        });
    }
    
    console.log('[UI更新] 注文履歴リスト更新完了:', {
        updatedCount: updatedCount,
        totalItems: orderListItems.length
    });
    
    // 更新できなかった場合の警告
    if (updatedCount === 0) {
        console.warn('[UI更新] 注文履歴リストの進捗要素が見つかりませんでした。ページリロードが必要かもしれません。');
        
        // 代替案: 注文履歴リストの部分的な再読み込みを試行
        refreshOrderHistoryList(clientId);
    }
    
    return updatedCount;
}

/**
 * 注文履歴リストの部分的な再読み込み
 * @param {string} clientId - 顧客ID
 */
function refreshOrderHistoryList(clientId) {
    console.log('[UI更新] 注文履歴リストの再読み込み開始:', clientId);
    
    if (!clientId) {
        console.warn('[UI更新] 顧客IDが不明のため、再読み込みをスキップします');
        return;
    }
    
    // 現在のページURLを取得
    var currentUrl = window.location.href;
    
    // 5秒後にページを再読み込み（ユーザーに時間を与える）
    setTimeout(function() {
        console.log('[UI更新] ページを再読み込みします');
        window.location.reload();
    }, 5000);
    
    // ユーザーに通知
    if (typeof window.showInfoNotification === 'function') {
        window.showInfoNotification('注文履歴を最新の状態に更新するため、5秒後にページを再読み込みします');
    } else {
        console.log('[UI更新] 注文履歴を最新の状態に更新するため、5秒後にページを再読み込みします');
    }
} 