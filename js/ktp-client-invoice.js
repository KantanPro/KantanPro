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

/** 二重枠を避けるため振込ブロックの外側 div を外す */
function ktpInvoiceUnwrapBankTransferBox(html) {
    var str = String(html || '').trim();
    var m = str.match(/^<div\s+class="ktp-invoice-bank-transfer"[^>]*>([\s\S]*)<\/div>\s*$/i);
    return m ? m[1].trim() : str;
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
                                var html = "<div class=\"ktp-invoice-address-block\" style=\"margin-bottom:20px;font-size:12px;\">";

                                // 部署選択がある場合の宛先表示を修正
                                var address = res.data.client_address || "";
                                var postalCode = "";
                                var addressWithoutPostal = address;
                                var companyName = res.data.client_name || "未設定";
                                // グローバル変数に顧客名を保存（印刷時のファイル名生成用）
                                window.invoiceClientName = companyName;
                                var contactDisplay = res.data.client_contact || "";



                                // 部署選択がある場合
                                if (res.data.selected_department) {
                                    // 住所情報を処理
                                    if (address && address.startsWith("〒")) {
                                        var postalMatch = address.match(/〒(\d{3}-?\d{4})/);
                                        if (postalMatch) {
                                            postalCode = "〒" + postalMatch[1];
                                            addressWithoutPostal = address.replace(/〒\d{3}-?\d{4}\s*/, "");
                                        }
                                    }



                                    // 住所情報が設定されていない場合は「未設定」は表示しない
                                    if (address && address.trim() !== "" && address !== "未設定") {
                                        if (postalCode) {
                                            html += "<div style=\"margin-bottom:5px;\">" + postalCode + "</div>";
                                        }
                                        if (addressWithoutPostal && addressWithoutPostal.trim() !== "") {
                                            html += "<div style=\"margin-bottom:5px;\">" + addressWithoutPostal + "</div>";
                                        }
                                    }
                                    // 会社名を表示
                                    html += "<div style=\"margin-bottom:5px;\">" + companyName + "</div>";
                                    // 部署名を表示
                                    html += "<div style=\"margin-bottom:5px;\">" + res.data.selected_department.department_name + "</div>";
                                    // 担当者名を表示
                                    html += "<div style=\"margin-bottom:5px;\">" + res.data.selected_department.contact_person + customerHonorific + "</div>";
                                } else {
                                    // 部署選択がない場合：そのまま表示
                                    if (address && address.startsWith("〒")) {
                                        var postalMatch = address.match(/〒(\d{3}-?\d{4})/);
                                        if (postalMatch) {
                                            postalCode = "〒" + postalMatch[1];
                                            addressWithoutPostal = address.replace(/〒\d{3}-?\d{4}\s*/, "");
                                        }
                                    }

                                    // 住所情報が設定されていない場合は「未設定」は表示しない
                                    if (address && address.trim() !== "" && address !== "未設定") {
                                        if (postalCode) {
                                            html += "<div style=\"margin-bottom:5px;\">" + postalCode + "</div>";
                                        }
                                        html += "<div style=\"margin-bottom:5px;\">" + addressWithoutPostal + "</div>";
                                    }
                                    html += "<div style=\"margin-bottom:5px;\">" + companyName + "</div>";

                                    if (contactDisplay && contactDisplay.trim() !== "" && contactDisplay !== "未設定") {
                                        contactDisplay += customerHonorific;
                                        html += "<div style=\"margin-bottom:5px;\">" + contactDisplay + "</div>";
                                    }
                                }
                                html += "</div>";

                                html += "<div class=\"ktp-invoice-title-box\" style=\"margin:100px 0 20px 0;padding:12px 16px;border:2px solid #d1d5db;border-radius:6px;background-color:#fff;text-align:center;\">";
                                html += "<div style=\"font-size:24px;line-height:1.25;font-weight:bold;color:#111827;\">" + t("請求書") + "</div>";
                                var showQualified = !(window.ktp_tax_policy && window.ktp_tax_policy.mode === 'abolished');
                                if (showQualified && res.data.qualified_invoice_number && res.data.qualified_invoice_number.trim() !== '') {
                                    html += "<div style=\"font-size:14px;color:#374151;margin-top:4px;\">" + t("適格請求書番号：") + res.data.qualified_invoice_number + "</div>";
                                }
                                html += "</div>";

                                html += "<p style=\"margin:0 0 16px;font-size:14px;line-height:1.5;color:#374151;\">";
                                html += t("平素より大変お世話になっております。下記の通りご請求申し上げます。");
                                html += "</p>";

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
                                html += "<div class=\"ktp-biz-summary-box\" style=\"margin-bottom:24px;padding:16px;border:1px solid #e5e7eb;border-radius:6px;background-color:#f9fafb;font-size:14px;color:#374151;\">";
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
                                window.invoiceTaxCategory = res.data.tax_category; // 税区分をグローバルに設定

                                var oddRowColor = window.ktp_design_settings.odd_row_color || "#E7EEFD";
                                var evenRowColor = window.ktp_design_settings.even_row_color || "#FFFFFF";
                                var hideTaxCols = !!(window.ktp_tax_policy && window.ktp_tax_policy.hide_tax_columns);

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

                                res.data.monthly_groups.forEach(function(group) {
                                    html += "<section style=\"border:1px solid #e5e7eb;border-radius:6px;overflow:hidden;margin-bottom:16px;\">";
                                    html += "<div style=\"padding:8px 16px;background-color:#f9fafb;border-bottom:1px solid #e5e7eb;font-size:14px;font-weight:600;color:#075985;\">";
                                    html += "【" + group.billing_period + "】" + t("締日：") + group.closing_date + " " + t("案件数：") + group.orders.length + t("件");
                                    html += "</div>";
                                    html += "<div style=\"padding:16px;display:flex;flex-direction:column;gap:16px;\">";

                                    var monthlyTotal = 0;
                                    var monthGroupItems = [];

                                    group.orders.forEach(function(order) {
                                        var orderSubtotal = 0;
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

                                        html += "<div style=\"border:1px solid #e5e7eb;border-radius:6px;overflow:hidden;\">";
                                        html += "<div style=\"padding:8px 12px;border-bottom:1px solid #e5e7eb;background-color:#fff;font-size:14px;font-weight:600;color:#1f2937;\">";
                                        html += "ID: " + order.id + " - " + ktpInvoiceEscapeHtml(order.project_name || "") + t("（完了日：") + formatInvoiceCompletionDate(order.completion_date) + "）" + invoicedBadge;
                                        html += "<div style=\"margin-top:4px;font-size:12px;font-weight:normal;color:#4b5563;\">" + deptLine + "</div>";
                                        html += "</div>";

                                        if (order.invoice_items && order.invoice_items.length > 0) {
                                            html += "<div style=\"overflow-x:auto;\">";
                                            html += "<table class=\"ktp-biz-items-table\" style=\"width:100%;border-collapse:collapse;font-size:14px;\">";
                                            html += "<thead><tr style=\"color:#4b5563;border-bottom:1px solid #f3f4f6;background-color:#f9fafb;\">";
                                            html += "<th style=\"text-align:left;padding:8px 12px;width:3.5rem;\">No.</th>";
                                            html += "<th style=\"text-align:left;padding:8px 12px;\">" + t("サービス") + "</th>";
                                            html += "<th style=\"text-align:right;padding:8px 12px;white-space:nowrap;\">" + t("単価") + "</th>";
                                            html += "<th style=\"text-align:right;padding:8px 12px;white-space:nowrap;\">" + t("数量/単位") + "</th>";
                                            html += "<th style=\"text-align:right;padding:8px 12px;\">" + t("金額") + "</th>";
                                            if (!hideTaxCols) {
                                                html += "<th style=\"text-align:right;padding:8px 12px;\">" + t("税額") + "</th>";
                                                html += "<th style=\"text-align:right;padding:8px 12px;\">" + t("税率") + "</th>";
                                            }
                                            html += "<th style=\"text-align:left;padding:8px 12px;white-space:nowrap;\">" + t("備考") + "</th>";
                                            html += "</tr></thead><tbody>";

                                            order.invoice_items.forEach(function(item, index) {
                                                var unitPrice = item.price ? ktpwpFormatMoney(item.price) : "—";
                                                var quantity = item.quantity ? formatDecimalDisplay(item.quantity) : "—";
                                                var amount = item.amount ? parseFloat(item.amount) : 0;
                                                var totalPrice = amount > 0 ? ktpwpFormatMoney(amount) : "—";
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
                                                var taxRateDisplay = "-";
                                                if (itemTaxRate !== null && !isNaN(itemTaxRate) && itemTaxRate >= 0) {
                                                    taxRateDisplay = itemTaxRate + "%";
                                                }
                                                var lineTaxAmountDisplay = "";
                                                if (!hideTaxCols) {
                                                    if (itemTaxRate !== null && !isNaN(itemTaxRate) && itemTaxRate >= 0 && amount > 0) {
                                                        if (itemTaxRate === 0) {
                                                            lineTaxAmountDisplay = "—";
                                                        } else if (res.data.tax_category === "外税") {
                                                            lineTaxAmountDisplay = ktpwpFormatMoney(Math.ceil(amount * (itemTaxRate / 100)));
                                                        } else {
                                                            lineTaxAmountDisplay = ktpwpFormatMoney(Math.ceil(amount * (itemTaxRate / 100) / (1 + itemTaxRate / 100)));
                                                        }
                                                    }
                                                }
                                                if (amount > 0) {
                                                    orderSubtotal += amount;
                                                }
                                                var rowBg = (index % 2 === 0) ? evenRowColor : oddRowColor;
                                                html += "<tr class=\"ktp-biz-inv-row\" style=\"border-bottom:1px solid #f3f4f6;background-color:" + rowBg + ";\">";
                                                html += "<td style=\"padding:8px 12px;color:#374151;\">" + (index + 1) + "</td>";
                                                html += "<td style=\"padding:8px 12px;color:#111827;\">" + ktpInvoiceEscapeHtml(item.product_name || "") + "</td>";
                                                html += "<td style=\"padding:8px 12px;text-align:right;color:#374151;white-space:nowrap;\">" + unitPrice + "</td>";
                                                html += "<td style=\"padding:8px 12px;text-align:right;color:#374151;white-space:nowrap;\">" + quantity + "/" + ktpInvoiceEscapeHtml(item.unit || t("式")) + "</td>";
                                                html += "<td style=\"padding:8px 12px;text-align:right;color:#111827;white-space:nowrap;\">" + totalPrice + "</td>";
                                                if (!hideTaxCols) {
                                                    html += "<td style=\"padding:8px 12px;text-align:right;color:#374151;white-space:nowrap;\">" + lineTaxAmountDisplay + "</td>";
                                                    html += "<td style=\"padding:8px 12px;text-align:right;color:#374151;white-space:nowrap;\">" + taxRateDisplay + "</td>";
                                                }
                                                var remarksDisplay = item.remarks ? ktpInvoiceEscapeHtml(item.remarks) : "—";
                                                html += "<td style=\"padding:8px 12px;color:#374151;\">" + remarksDisplay + "</td>";
                                                html += "</tr>";
                                            });

                                            html += "</tbody></table></div>";
                                            html += "<div style=\"padding:8px 12px;text-align:right;font-size:14px;font-weight:600;color:#111827;border-top:1px solid #f3f4f6;background-color:#fff;\">";
                                            html += t("案件合計：") + ktpwpFormatMoney(orderSubtotal) + ktpInvoiceTaxInlineSuffixFromItems(order.invoice_items || [], taxCategory, t);
                                            html += "</div>";
                                        } else {
                                            html += "<div style=\"padding:12px;color:#6b7280;font-size:14px;\">" + t("請求項目なし") + "</div>";
                                        }
                                        monthlyTotal += orderSubtotal;
                                        monthGroupItems = monthGroupItems.concat(order.invoice_items || []);
                                        html += "</div>";
                                    });

                                    html += "</div>";
                                    html += "<div style=\"padding:8px 16px;border-top:1px solid #e5e7eb;background-color:#f9fafb;text-align:right;font-size:16px;font-weight:700;color:#0369a1;\">";
                                    html += group.billing_period + t(" 月別合計：") + ktpwpFormatMoney(monthlyTotal) + ktpInvoiceTaxInlineSuffixFromItems(monthGroupItems, taxCategory, t);
                                    html += "</div>";
                                    html += "</section>";
                                });

                                html += "<div class=\"ktp-biz-company-bank-grid\" style=\"display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:16px;margin-top:24px;margin-bottom:24px;break-inside:avoid-page;page-break-inside:avoid;\">";
                                html += "<section style=\"border:1px solid #e5e7eb;border-radius:6px;padding:16px;break-inside:avoid-page;page-break-inside:avoid;\">";
                                if (res.data.company_info && String(res.data.company_info).trim() !== "") {
                                    html += "<div style=\"font-size:14px;color:#374151;line-height:1.5;\">" + ktpInvoiceUnwrapCompanyInfoBox(res.data.company_info) + "</div>";
                                }
                                html += "</section>";
                                html += "<section style=\"border:1px solid #e5e7eb;border-radius:6px;padding:16px;break-inside:avoid-page;page-break-inside:avoid;\">";
                                if (res.data.bank_transfer_html && String(res.data.bank_transfer_html).trim() !== "") {
                                    html += "<div style=\"font-size:14px;color:#374151;line-height:1.5;\">" + ktpInvoiceUnwrapBankTransferBox(res.data.bank_transfer_html) + "</div>";
                                }
                                html += "</section></div>";

                                // 印刷・PDF保存ボタンの上にチェックボックスを追加
                                html += '<div id="ktp-invoice-footer-actions" style="margin-top:20px;text-align:center;">';
                                html += '<label style="display:inline-flex;align-items:center;font-size:15px;font-weight:500;margin-bottom:12px;">';
                                html += '<input type="checkbox" id="set-invoice-completed" style="width:18px;height:18px;margin-right:8px;">';
                                html += t('対象受注書の進捗を「請求済」に変更する');
                                html += '</label><br />';
                                html += '<button type="button" onclick="printInvoiceContent(\'print\')" style="background-color:#0073aa;color:white;border:none;padding:10px 20px;border-radius:5px;cursor:pointer;font-size:14px;font-weight:500;margin-right:8px;">';
                                html += (typeof KTPSvgIcons !== 'undefined' ? KTPSvgIcons.getIcon('print', {'style': 'font-size:16px;vertical-align:middle;margin-right:5px;'}) : '<span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle;margin-right:5px;">print</span>');
                                html += t('印刷');
                                html += '</button>';
                                html += '<button type="button" onclick="printInvoiceContent(\'pdf\')" style="background-color:#2271b1;color:white;border:none;padding:10px 20px;border-radius:5px;cursor:pointer;font-size:14px;font-weight:500;">';
                                html += (typeof KTPSvgIcons !== 'undefined' ? KTPSvgIcons.getIcon('picture_as_pdf', {'style': 'font-size:16px;vertical-align:middle;margin-right:5px;'}) : '<span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle;margin-right:5px;">picture_as_pdf</span>');
                                html += t('PDF保存');
                                html += '</button>';
                                html += '</div>';

                                list.innerHTML = html;
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
 * @param {string} outputMode 'print' = 一括請求書の印刷のみ（@page10mm。宛名10+6/10+23。請求書タイトル枠上端67mm=10+57。宛名以外余白左20・右15・下15）、'pdf' = プレビューと同じA4（PDF保存用）
 */
function printInvoiceContent(outputMode) {
    var mode = (outputMode === 'pdf') ? 'pdf' : 'print';
    // 印刷のみ：宛名 absolute top=6 left=23（用紙 10+6 / 左33）。請求書タイトル枠は用紙上67mm（10+57）起点＝padding-top 57mm。宛名以外 padding 左10・右5・下5
    var invPrintPageMarginMm = (mode === 'print') ? 10 : 0;
    var invAddrTopMm = (mode === 'print') ? (16 - invPrintPageMarginMm) : 0;
    var invAddrLeftMm = (mode === 'print') ? (33 - invPrintPageMarginMm) : 0;
    var invInvoiceTitleTopFromPaperMm = 67;
    var invPrintBodyFlowPadTopMm = (mode === 'print') ? (invInvoiceTitleTopFromPaperMm - invPrintPageMarginMm) : 0;
    var invPrintPadLeftInnerMm = (mode === 'print') ? 10 : 0;
    var invPrintPadRightInnerMm = (mode === 'print') ? 5 : 0;
    var invPrintPadBottomInnerMm = (mode === 'print') ? 5 : 0;
    // チェックボックスの状態を確認
    var setInvoiceCompleted = document.getElementById('set-invoice-completed');
    var shouldSetCompleted = false;
    if (setInvoiceCompleted && setInvoiceCompleted.checked) {
        var confirmed = window.confirm(ktpwpTranslate('本当に対象受注書の進捗を「請求済」に変更しますか？\nこの操作は取り消せません。\nOKで印刷を続行、キャンセルで中止します。'));
        if (!confirmed) {
            return; // キャンセル時は何もしない
        }
        shouldSetCompleted = true;
    }
    try {
        console.log("[請求書印刷] 印刷開始");

        var invoiceList = document.getElementById('invoiceList');
        if (!invoiceList) {
            console.error("[請求書印刷] invoiceList要素が見つかりません");
            alert(ktpwpTranslate("印刷エラー：請求書データが見つかりません"));
            return;
        }

        var invoiceContent = invoiceList.innerHTML;
        if (!invoiceContent || invoiceContent.trim() === "") {
            console.error("[請求書印刷] 請求書の内容が空です");
            alert(ktpwpTranslate("印刷エラー：請求書の内容が空です"));
            return;
        }

        console.log("[請求書印刷] 請求書内容取得完了");

        // デザイン設定を取得
        var designSettings = window.ktp_design_settings || {};
        var oddRowColor = designSettings.odd_row_color || "#E7EEFD";
        var evenRowColor = designSettings.even_row_color || "#FFFFFF";
        
        console.log("[請求書印刷] デザイン設定:", {
            oddRowColor: oddRowColor,
            evenRowColor: evenRowColor
        });

        var carryoverAmount = window.carryoverAmount || 0;
        var carryoverInput = document.getElementById('carryover-amount');
        if (carryoverInput) {
            carryoverAmount = parseInt(carryoverInput.value) || 0;
            console.log("[請求書印刷] 繰越金額:", carryoverAmount);
        }

        // 繰越金額入力フィールドを非表示にし、印刷用のspanに置き換える
        var tempDiv = document.createElement('div');
        tempDiv.innerHTML = invoiceContent;
        var carryoverInputInContent = tempDiv.querySelector('#carryover-amount');
        if(carryoverInputInContent) {
            var carryoverSpan = document.createElement('span');
            carryoverSpan.style.fontWeight = 'bold';
            carryoverSpan.textContent = carryoverAmount.toLocaleString();
            carryoverInputInContent.parentNode.replaceChild(carryoverSpan, carryoverInputInContent);
        }

        // フッター（請求済チェック・印刷／PDFボタン）を印刷用HTMLから除去
        var footerActions = tempDiv.querySelector('#ktp-invoice-footer-actions');
        if (footerActions && footerActions.parentNode) {
            footerActions.parentNode.removeChild(footerActions);
        }

        // お支払い期日inputをテキストに置き換え
        var paymentDueDateInputInContent = tempDiv.querySelector('#payment-due-date-input');
        if (paymentDueDateInputInContent) {
            // 最新の値を取得（元のDOMから）
            var liveInput = document.getElementById('payment-due-date-input');
            var paymentDueDateValue = liveInput ? liveInput.value : paymentDueDateInputInContent.value;
            // 日付を「YYYY/MM/DD」形式に整形
            var formattedDate = paymentDueDateValue ? paymentDueDateValue.replace(/-/g, "/") : "";
            var paymentDueDateSpan = document.createElement('span');
            paymentDueDateSpan.style.fontWeight = 'bold';
            paymentDueDateSpan.textContent = formattedDate;
            paymentDueDateInputInContent.parentNode.replaceChild(paymentDueDateSpan, paymentDueDateInputInContent);
        }

        // 合計金額を更新（税区分に応じた計算）
        if (window.invoiceGrandTotal) {
            var totalAmountElement = tempDiv.querySelector('#total-amount');
            if(totalAmountElement) {
                var taxCategory = window.invoiceTaxCategory || '内税';
                var taxAmount = window.invoiceTaxAmount || 0;
                
                if (taxCategory === '外税') {
                    // 外税の場合：税抜き合計 + 消費税 + 繰越金額
                    var subtotal = window.invoiceGrandTotal - taxAmount; // 税抜き合計を計算
                    var totalWithTax = subtotal + taxAmount + carryoverAmount;
                    totalAmountElement.textContent = totalWithTax.toLocaleString();
                    console.log("[請求書印刷] 外税計算:", {
                        subtotal: subtotal,
                        taxAmount: taxAmount,
                        carryoverAmount: carryoverAmount,
                        totalWithTax: totalWithTax
                    });
                } else {
                    // 内税の場合：税込合計 + 繰越金額
                    var totalWithCarryover = window.invoiceGrandTotal + carryoverAmount;
                    totalAmountElement.textContent = totalWithCarryover.toLocaleString();
                    console.log("[請求書印刷] 内税計算:", {
                        grandTotal: window.invoiceGrandTotal,
                        carryoverAmount: carryoverAmount,
                        totalWithCarryover: totalWithCarryover
                    });
                }
            }
        }

        // 印刷用にデザイン設定を適用（旧 flex 明細）
        var rows = tempDiv.querySelectorAll('[style*="background"]');
        rows.forEach(function(row, index) {
            if (row.style.background && (row.style.background.includes('#E7EEFD') || row.style.background.includes('#FFFFFF'))) {
                var bgColor = (index % 2 === 0) ? evenRowColor : oddRowColor;
                row.style.background = bgColor;
                console.log("[請求書印刷] 行の色を更新:", index, bgColor);
            }
        });
        var invTableRows = tempDiv.querySelectorAll('tr.ktp-biz-inv-row');
        invTableRows.forEach(function(tr, idx) {
            var bgColor = (idx % 2 === 0) ? evenRowColor : oddRowColor;
            tr.style.backgroundColor = bgColor;
        });

        invoiceContent = tempDiv.innerHTML;

        // ファイル名生成
        var clientId = '';
        var clientName = '';
        
        // 顧客IDを取得
        var urlParams = new URLSearchParams(window.location.search);
        clientId = urlParams.get('data_id');
        if (!clientId) {
            var clientIdInput = document.getElementById('client-id-input');
            if (clientIdInput) {
                clientId = clientIdInput.value;
            }
        }
        
        // 顧客名を取得（優先順位順）
        // 方法0: グローバル変数から取得（最も確実）
        if (window.invoiceClientName && window.invoiceClientName !== '未設定') {
            clientName = window.invoiceClientName;
        }
        
        // 方法1: DOMから会社名を直接取得
        if (!clientName || clientName === '顧客' || clientName === '未設定') {
            var companyNameElem = document.querySelector('#invoiceList div[style*="font-size:16px;font-weight:bold;margin-bottom:4px;"]');
            if (companyNameElem) {
                clientName = companyNameElem.textContent.trim();
            }
        }
        
        // 方法2: 宛先情報から取得
        if (!clientName || clientName === '顧客' || clientName === '未設定') {
            var addressElems = document.querySelectorAll('#invoiceList div[style*="font-size:14px;margin-bottom:4px;"]');
            for (var i = 0; i < addressElems.length; i++) {
                var text = addressElems[i].textContent.trim();
                if (text && text.length > 0 && !text.includes('様') && !text.includes('〒') && !text.includes('電話') && text !== '未設定') {
                    clientName = text;
                    break;
                }
            }
        }
        
        // 方法3: 請求書タイトル周辺から取得
        if (!clientName || clientName === '顧客' || clientName === '未設定') {
            var titleElems = document.querySelectorAll('#invoiceList div');
            for (var i = 0; i < titleElems.length; i++) {
                var text = titleElems[i].textContent.trim();
                if (text && text.includes('様') && text.length < 50) {
                    clientName = text.replace(/\s*様?$/, '');
                    break;
                }
            }
        }
        
        // 方法4: 古い方法（後方互換性）
        if (!clientName || clientName === '顧客' || clientName === '未設定') {
            var clientNameElem = document.querySelector('#invoiceList div[style*="margin-bottom:5px;"]:nth-child(3)');
            if (clientNameElem) {
                clientName = clientNameElem.textContent.replace(/\s*様?$/, '');
            }
        }
        
        console.log("[請求書印刷] 顧客情報:", {
            clientId: clientId,
            clientName: clientName,
            todayStr: todayStr
        });
        
        // 顧客名取得のデバッグ情報
        console.log("[請求書印刷] 顧客名取得デバッグ:");
        console.log("- グローバル変数:", window.invoiceClientName);
        console.log("- 方法1要素:", document.querySelector('#invoiceList div[style*="font-size:16px;font-weight:bold;margin-bottom:4px;"]'));
        console.log("- 方法2要素数:", document.querySelectorAll('#invoiceList div[style*="font-size:14px;margin-bottom:4px;"]').length);
        console.log("- 方法3要素数:", document.querySelectorAll('#invoiceList div').length);
        console.log("- 最終的な顧客名:", clientName);
        
        // 今日の日付を取得（YYYY-MM-DD形式）
        var today = new Date();
        var year = today.getFullYear();
        var month = String(today.getMonth() + 1).padStart(2, '0');
        var day = String(today.getDate()).padStart(2, '0');
        var todayStr = year + '-' + month + '-' + day;

        function parseDateToTimestamp(dateStr) {
            if (!dateStr) { return null; }
            // "YYYY-MM-DD" / "YYYY/MM/DD" を想定して Date へ
            var normalized = String(dateStr).trim().replace(/\//g, '-');
            var ts = new Date(normalized).getTime();
            return isNaN(ts) ? null : ts;
        }

        function formatYmdForFilename(dateStr) {
            // "YYYY-MM-DD" -> "YYYYMMDD" のように数字だけ残す
            if (!dateStr) { return ''; }
            return String(dateStr).replace(/[^\d]/g, '');
        }

        // 最終締め日を抽出（DOM上の「締日：YYYY-MM-DD」から最大日を取る）
        var finalClosingDateYmd = '';
        try {
            var divs = tempDiv.querySelectorAll('div');
            var bestTs = null;
            var bestRaw = '';
            divs.forEach(function(el) {
                var text = (el && el.textContent) ? el.textContent : '';
                if (!text || text.indexOf('締日：') === -1) { return; }

                // 例: "【4】" + t("締日：") + "2026-03-31" + " " + t("案件数：") + "10"
                var m = text.match(/締日：\s*([0-9]{4}[-\/][0-9]{1,2}[-\/][0-9]{1,2})/);
                if (!m || !m[1]) { return; }

                var ts = parseDateToTimestamp(m[1]);
                if (ts === null) { return; }

                if (bestTs === null || ts > bestTs) {
                    bestTs = ts;
                    bestRaw = m[1];
                }
            });

            finalClosingDateYmd = bestRaw ? formatYmdForFilename(bestRaw) : '';
        } catch (e) {
            // 取得に失敗してもフォールバックで today を使う
            console.warn('[請求書印刷] 最終締め日抽出に失敗:', e);
        }
        
        function sanitizeFilename(value) {
            // 印刷をPDF保存した際のファイル名に禁止文字が含まれる場合、ブラウザがフォールバック名になることがあるためサニタイズする
            return String(value)
                .replace(/[\u0000-\u001F\/\\:\uFF1A*\?"<>\|]/g, '-')
                .replace(/\s+/g, ' ')
                .trim();
        }
        
        // ファイル名を生成: {請求先会社名}_{最終締め日}.pdf
        // （ブラウザの提案名がサイト名になってしまうのを防ぐため、必ず title/d.title に反映される値を作る）
        var closingDateForFilename = finalClosingDateYmd || formatYmdForFilename(todayStr);
        var filenameBase = sanitizeFilename((clientName || '請求先') + '_' + closingDateForFilename);
        var filename = filenameBase + '.pdf';
        
        // 印刷用のスタイルを適用したHTMLを生成（print=封筒窓向け / pdf=プレビュー同等A4）
        var printHTML = '<!DOCTYPE html>';
        printHTML += '<html lang="ja">';
        printHTML += '<head>';
        printHTML += '<meta charset="UTF-8">';
        printHTML += '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
        printHTML += '<title>' + filename + '</title>';
        printHTML += '<meta name="title" content="' + filename + '">';
        printHTML += '<meta name="filename" content="' + filename + '">';
        printHTML += '<style>';
        printHTML += '* { margin: 0; padding: 0; box-sizing: border-box; }';
        printHTML += 'body { font-family: "Noto Sans JP", "Hiragino Kaku Gothic ProN", "Yu Gothic", Meiryo, sans-serif; font-size: 12px; line-height: 1.4; color: #333; background: white; -webkit-print-color-adjust: exact; print-color-adjust: exact; }';
        printHTML += 'h1, h2, h3, h4, h5, h6 { font-weight: bold; }';
        printHTML += '* { -webkit-print-color-adjust: exact !important; color-adjust: exact !important; print-color-adjust: exact !important; }';
        printHTML += '@media print { button, .no-print { display: none !important; } }';
        if (mode === 'pdf') {
            printHTML += 'body { padding: 20px; background: white; }';
            printHTML += '.page-container { width: 210mm; max-width: 210mm; margin: 0 auto; background: white; padding: 50px; }';
            printHTML += '@page { size: A4; margin: 50px; }';
            printHTML += '@page :first { size: A4; margin: 50px; }';
            printHTML += '@media print { body { margin: 0; padding: 0; background: white; } .page-container { box-shadow: none; margin: 0; padding: 0; width: auto; max-width: none; } }';
        } else {
            // 印刷のみ：@page 10mm。padding 上=57mm（請求書タイトル枠 用紙上67mm=10+57）＋右5＋下5＋左10
            printHTML += 'html, body { padding: 0; margin: 0; background: white; }';
            printHTML += '@page { size: A4; margin: ' + invPrintPageMarginMm + 'mm; }';
            printHTML += '.page-container.ktp-inv-print-envelope { position: relative; width: 210mm; max-width: 210mm; margin: 0 auto; background: white; min-height: 297mm; padding: ' + invPrintBodyFlowPadTopMm + 'mm ' + invPrintPadRightInnerMm + 'mm ' + invPrintPadBottomInnerMm + 'mm ' + invPrintPadLeftInnerMm + 'mm; box-sizing: border-box; }';
            printHTML += '.page-container.ktp-inv-print-envelope .ktp-invoice-address-block { position: absolute; top: ' + invAddrTopMm + 'mm; left: ' + invAddrLeftMm + 'mm; z-index: 2; max-width: 88mm; margin: 0 !important; }';
            printHTML += '.page-container.ktp-inv-print-envelope .ktp-invoice-title-box { margin-top: 0 !important; }';
            printHTML += '@media print {';
            printHTML += '  html, body { margin: 0 !important; padding: 0 !important; }';
            printHTML += '  .page-container.ktp-inv-print-envelope { margin: 0 !important; width: 100% !important; max-width: none !important; padding: ' + invPrintBodyFlowPadTopMm + 'mm ' + invPrintPadRightInnerMm + 'mm ' + invPrintPadBottomInnerMm + 'mm ' + invPrintPadLeftInnerMm + 'mm !important; box-sizing: border-box !important; }';
            printHTML += '  .page-container.ktp-inv-print-envelope .ktp-invoice-address-block { position: absolute !important; top: ' + invAddrTopMm + 'mm !important; left: ' + invAddrLeftMm + 'mm !important; right: auto !important; z-index: 999 !important; max-width: 88mm !important; margin: 0 !important; }';
            printHTML += '  .page-container.ktp-inv-print-envelope .ktp-invoice-title-box { margin-top: 0 !important; }';
            printHTML += '  .ktp-biz-company-bank-grid { display: grid !important; grid-template-columns: minmax(0,1fr) minmax(0,1fr) !important; gap: 1rem !important; break-inside: avoid-page; page-break-inside: avoid; }';
            printHTML += '  .ktp-biz-company-bank-grid > section { break-inside: avoid-page; page-break-inside: avoid; }';
            printHTML += '  .ktp-biz-items-table { font-size: 0.8rem !important; }';
            printHTML += '}';
        }
        printHTML += '</style>';
        printHTML += '</head>';
        printHTML += '<body>';
        printHTML += (mode === 'pdf')
            ? '<div class="page-container">'
            : '<div class="page-container ktp-inv-print-envelope">';
        printHTML += invoiceContent;
        printHTML += '</div>';
        printHTML += '</body>';
        printHTML += '</html>';

        console.log("[請求書印刷] 印刷HTML生成完了");
        console.log("[請求書印刷] ファイル名:", filename);

        // 新規タブやabout:blankを開かず、隠しiframeで印刷（Chrome 139対応）
        var iframe = document.createElement('iframe');
        iframe.style.position = 'fixed';
        iframe.style.right = '0';
        iframe.style.bottom = '0';
        iframe.style.width = '0';
        iframe.style.height = '0';
        iframe.style.border = '0';
        iframe.style.visibility = 'hidden';
        document.body.appendChild(iframe);

        var originalDocumentTitle = document.title;
        var cleanupDone = false;
        function cleanup() {
            if (cleanupDone) return;
            cleanupDone = true;
            setTimeout(function() {
                try { document.body.removeChild(iframe); } catch (_) {}
                try { document.title = originalDocumentTitle; } catch (_) {}
            }, 300);
        }

        try {
            var frameDoc = iframe.contentDocument || iframe.contentWindow.document;
            frameDoc.open();
            frameDoc.write(printHTML);
            frameDoc.close();

            // title を onload に依存せず、書き込み直後に反映する（環境によって onload が不安定なため）
            try {
                var d = iframe.contentDocument || iframe.contentWindow.document;
                if (d) {
                    d.title = filename;
                    // 念のため <title> 要素も更新
                    if (d.head) {
                        var titleEl = d.head.querySelector('title');
                        if (titleEl) {
                            titleEl.textContent = filename;
                        } else {
                            var t = d.createElement('title');
                            t.textContent = filename;
                            d.head.appendChild(t);
                        }
                    }
                }
            } catch (_) {}

            // 封筒印刷：宛名 absolute（座標はスタイルシートと同一）。タイトル margin は CSS で 0
            if (mode === 'print') {
                try {
                    var dPrint = iframe.contentDocument || iframe.contentWindow.document;
                    if (dPrint) {
                        var addrEl = dPrint.querySelector('.ktp-invoice-address-block');
                        if (addrEl) {
                            addrEl.style.setProperty('position', 'absolute', 'important');
                            addrEl.style.setProperty('top', invAddrTopMm + 'mm', 'important');
                            addrEl.style.setProperty('left', invAddrLeftMm + 'mm', 'important');
                            addrEl.style.setProperty('right', 'auto', 'important');
                            addrEl.style.setProperty('z-index', '999', 'important');
                            addrEl.style.setProperty('max-width', '88mm', 'important');
                            addrEl.style.setProperty('margin', '0', 'important');
                        } else {
                            console.warn('[請求書印刷] .ktp-invoice-address-block が見つかりません（封筒用座標をスキップ）');
                        }
                    }
                } catch (injErr) {
                    console.warn('[請求書印刷] 印刷用インラインスタイル適用に失敗:', injErr);
                }
            }

            // print を発火
            try {
                var w = iframe.contentWindow || iframe;
                w.focus();
                w.onafterprint = function() {
                    cleanup();
                };
                setTimeout(function() {
                    // 一部ブラウザは iframe の title より親ドキュメントの title を参照して
                    // PDF保存時の提案ファイル名が決まることがあるため、直前に親 title も合わせる
                    try { document.title = filename; } catch (_) {}
                    try { w.print(); } catch (e) { cleanup(); }
                }, 50);
            } catch (e) {
                cleanup();
            }
        } catch (e) {
            console.error('[請求書印刷] iframe印刷に失敗:', e);
            cleanup();
        }

        // 印刷完了後の進捗変更Ajaxは、iframe印刷とは独立して実行
        if (shouldSetCompleted) {
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

    } catch (error) {
        console.error("[請求書印刷] エラーが発生しました:", error);
        alert(ktpwpTranslate("印刷エラーが発生しました: ") + error.message);
    }
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