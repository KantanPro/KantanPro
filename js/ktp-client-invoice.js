// KTP Client Invoice Script

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
                    list.innerHTML = "<div style=\"color:#c00;\">通信エラーが発生しました。ページを再読み込みして再度お試しください。</div>";
                };
                xhr.onload = function() {
                    console.log("[請求書発行] Ajaxレスポンス受信:", xhr.status, xhr.responseText);
                    if (xhr.status === 200) {
                        try {
                            var res = JSON.parse(xhr.responseText);
                            console.log("[請求書発行] レスポンス解析結果:", res);
                            if (res.success && res.data && res.data.monthly_groups && res.data.monthly_groups.length > 0) {
                                var html = "<div style=\"margin-bottom:20px;font-size:12px;\">";

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
                                    html += "<div style=\"margin-bottom:5px;\">" + res.data.selected_department.contact_person + " 様</div>";
                                } else {
                                    // 部署選択がない場合：現行のまま
                                    if (address.startsWith("〒")) {
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
                                        contactDisplay += " 様";
                                        html += "<div style=\"margin-bottom:5px;\">" + contactDisplay + "</div>";
                                    }
                                }
                                html += "</div>";

                                html += "<div style=\"margin:100px 0 20px 0;padding:15px;border:2px solid #333;border-radius:8px;background-color:#f9f9f9;text-align:center;\">";
                                html += "<div style=\"font-size:18px;font-weight:bold;color:#333;\">請求書</div>";
                                // 適格請求書番号を表示（設定されている場合のみ）
                                if (res.data.qualified_invoice_number && res.data.qualified_invoice_number.trim() !== '') {
                                    html += "<div style=\"font-size:14px;color:#333;margin-top:5px;\">適格請求書番号：" + res.data.qualified_invoice_number + "</div>";
                                }
                                html += "</div>";

                                html += "<div style=\"margin:20px 0;padding:10px;font-size:14px;line-height:1.6;color:#333;\">";
                                html += "平素より大変お世話になっております。下記の通りご請求申し上げます。";
                                html += "</div>";

                                // 消費税対応の全体合計計算
                                var grandTotal = 0;
                                var grandSubtotal = 0;
                                var grandTaxAmount = 0;
                                
                                res.data.monthly_groups.forEach(function(group) {
                                    grandSubtotal += (group.subtotal || 0);
                                    grandTaxAmount += (group.tax_amount || 0);
                                    grandTotal += (group.subtotal || 0) + (group.tax_amount || 0);
                                });

                                // 税区分に応じた表示
                                var taxCategory = res.data.tax_category || '内税';
                                console.log("[請求書発行] 税区分:", taxCategory);
                                
                                if (taxCategory === '外税') {
                                    // 外税の場合：合計金額（税抜）→消費税→税込合計
                                    html += "<div style=\"font-weight:bold;font-size:14px;color:#333;display:flex;align-items:center;margin:10px 0 0 0;\">";
                                    html += "<span>合計金額：" + grandSubtotal.toLocaleString() + "円</span>";
                                    if (grandTaxAmount > 0) {
                                        html += "<span style=\"margin-left:15px;\">消費税：" + grandTaxAmount.toLocaleString() + "円</span>";
                                        html += "<span style=\"margin-left:15px;\">税込合計：" + grandTotal.toLocaleString() + "円</span>";
                                    }
                                    html += "<span style=\"margin-left:15px;\">繰越金額：</span>";
                                    html += "<input type=\"number\" id=\"carryover-amount\" name=\"carryover_amount\" value=\"0\" min=\"0\" step=\"1\" style=\"width:100px;padding:3px 6px;border:1px solid #ccc;border-radius:4px;font-size:14px;text-align:right;margin-left:5px;\" onchange=\"updateInvoiceTotal()\">";
                                    html += "<span style=\"font-size:14px;\">円</span>";
                                    html += "</div>";
                                } else {
                                    // 内税の場合：合計金額（税込）に内消費税を表示
                                    html += "<div style=\"font-weight:bold;font-size:14px;color:#333;display:flex;align-items:center;margin:10px 0 0 0;\">";
                                    html += "<span>合計金額：" + grandTotal.toLocaleString() + "円";
                                    if (grandTaxAmount > 0) {
                                        html += "（内消費税：" + Math.round(grandTaxAmount).toLocaleString() + "円）";
                                    }
                                    html += "</span>";
                                    html += "<span style=\"margin-left:15px;\">繰越金額：</span>";
                                    html += "<input type=\"number\" id=\"carryover-amount\" name=\"carryover_amount\" value=\"0\" min=\"0\" step=\"1\" style=\"width:100px;padding:3px 6px;border:1px solid #ccc;border-radius:4px;font-size:14px;text-align:right;margin-left:5px;\" onchange=\"updateInvoiceTotal()\">";
                                    html += "<span style=\"font-size:14px;\">円</span>";
                                    html += "</div>";
                                }
                                
                                // 請求金額・お支払い期日を1行で横並び
                                var paymentDueDate = res.data.payment_due_date || '';
                                
                                html += "<div style=\"font-weight:bold;font-size:20px;color:#0073aa;display:flex;align-items:center;margin:10px 0 0 0;\">";
                                html += "<span>請求金額：<span id=\"total-amount\">" + grandTotal.toLocaleString() + "</span>円</span>";
                                html += "<span style=\"margin-left:2em;font-size:16px;\">お支払い期日：<input type=\"date\" id=\"payment-due-date-input\" value=\"" + paymentDueDate + "\" style=\"font-size:16px;padding:4px 8px;border:1px solid #ccc;border-radius:4px;width:180px;max-width:100%;\"></span>";
                                html += "</div>";

                                window.invoiceGrandTotal = grandTotal;
                                window.invoiceTaxAmount = grandTaxAmount; // 消費税をグローバルに設定
                                window.invoiceTaxCategory = res.data.tax_category; // 税区分をグローバルに設定

                                res.data.monthly_groups.forEach(function(group) {
                                    html += "<div style=\"margin:20px 0 10px 0;padding:8px 12px;background-color:#f0f8ff;border-left:4px solid #0073aa;border-radius:4px;\">";
                                    html += "<div style=\"font-weight:bold;color:#0073aa;font-size:14px;\">";
                                    html += "【" + group.billing_period + "】締日：" + group.closing_date + "　案件数：" + group.orders.length + "件";
                                    html += "</div>";
                                    html += "</div>";

                                    var monthlyTotal = 0;

                                    group.orders.forEach(function(order) {
                                        var orderSubtotal = 0;
                                        html += "<div style=\"padding:10px;border-bottom:1px solid #eee;\">";
                                        html += "<div style=\"font-weight:bold;margin-bottom:8px;color:#333;font-size:12px;\">";
                                        html += "ID: " + order.id + " - " + order.project_name + "（完了日：" + order.completion_date + "）";
                                        html += "</div>";

                                        if (order.invoice_items && order.invoice_items.length > 0) {
                                            html += "<div style=\"margin-top:10px;width:100%;\">";
                                            html += "<div style=\"display: flex; background: #f0f0f0; padding: 8px; font-weight: bold; border-bottom: 1px solid #ccc; align-items: center; font-size: 12px;\">";
                                            html += "<div style=\"width: 30px; text-align: center;\">No.</div>";
                                            html += "<div style=\"flex: 1; text-align: left; margin-left: 8px;\">サービス</div>";
                                            html += "<div style=\"width: 80px; text-align: right;\">単価</div>";
                                            html += "<div style=\"width: 60px; text-align: right;\">数量/単位</div>";
                                            html += "<div style=\"width: 80px; text-align: right;\">金額</div>";
                                            html += "<div style=\"width: 60px; text-align: center;\">税率</div>";
                                            html += "<div style=\"width: 100px; text-align: left; margin-left: 8px;\">備考</div>";
                                            html += "</div>";

                                            var oddRowColor = window.ktp_design_settings.odd_row_color || "#E7EEFD";
                                            var evenRowColor = window.ktp_design_settings.even_row_color || "#FFFFFF";

                                            order.invoice_items.forEach(function(item, index) {
                                                // 小数点以下の不要な0を削除する関数
                                                function formatDecimalDisplay(value) {
                                                    if (value === '' || value === null || value === undefined) {
                                                        return '';
                                                    }
                                                    const num = parseFloat(value);
                                                    if (isNaN(num)) {
                                                        return value;
                                                    }
                                                    // 小数点以下6桁まで表示し、末尾の0とピリオドを削除
                                                    return num.toFixed(6).replace(/\.?0+$/, '');
                                                }
                                                
                                                // 金額を3桁区切りでフォーマットする関数
                                                function formatCurrency(value) {
                                                    if (value === '' || value === null || value === undefined) {
                                                        return '';
                                                    }
                                                    const num = parseFloat(value);
                                                    if (isNaN(num)) {
                                                        return value;
                                                    }
                                                    return num.toLocaleString();
                                                }
                                                
                                                var unitPrice = item.price ? formatCurrency(item.price) + "円" : "-";
                                                var quantity = item.quantity ? formatDecimalDisplay(item.quantity) : "-";
                                                var amount = item.amount ? parseFloat(item.amount) : 0;
                                                var totalPrice = amount > 0 ? amount.toLocaleString() + "円" : "-";
                                                
                                                // 税率表示（全ての税率を表示）
                                                var taxRateDisplay = "-";
                                                var itemTaxRate = parseFloat(item.tax_rate);
                                                if (item.tax_rate && !isNaN(itemTaxRate) && itemTaxRate > 0) {
                                                    // 全ての税率を表示
                                                    taxRateDisplay = itemTaxRate + "%";
                                                }
                                                
                                                // デバッグ用ログ（開発時のみ）
                                                if (typeof console !== 'undefined' && console.log && typeof ktpwpDebugMode !== 'undefined' && ktpwpDebugMode) {
                                                    console.log("税率デバッグ - 商品:", item.product_name, "税率:", item.tax_rate, "数値変換:", itemTaxRate, "表示:", taxRateDisplay);
                                                }

                                                if (amount > 0) {
                                                    orderSubtotal += amount;
                                                }
                                                var bgColor = (index % 2 === 0) ? evenRowColor : oddRowColor;
                                                html += "<div style=\"display: flex; padding: 6px 8px; height: 24px; background: " + bgColor + "; align-items: center; font-size: 12px;\">";
                                                html += "<div style=\"width: 30px; text-align: center;\">" + (index + 1) + "</div>";
                                                html += "<div style=\"flex: 1; text-align: left; margin-left: 8px;\">" + item.product_name + "</div>";
                                                html += "<div style=\"width: 80px; text-align: right;\">" + unitPrice + "</div>";
                                                html += "<div style=\"width: 60px; text-align: right;\">" + quantity + "/" + (item.unit || "式") + "</div>";
                                                html += "<div style=\"width: 80px; text-align: right;\">" + totalPrice + "</div>";
                                                html += "<div style=\"width: 60px; text-align: center;\">" + taxRateDisplay + "</div>";
                                                html += "<div style=\"width: 100px; text-align: left; margin-left: 8px;\"></div>";
                                                html += "</div>";
                                            });

                                            html += "</div>";
                                            html += "<div style=\"margin-top:10px;text-align:right;font-weight:bold;font-size:13px;color:#333;\">";
                                            html += "案件合計：" + orderSubtotal.toLocaleString() + "円";
                                            html += "</div>";
                                        } else {
                                            html += "<div style=\"color:#999;font-size:12px;\">請求項目なし</div>";
                                        }
                                        monthlyTotal += orderSubtotal;
                                        html += "</div>";
                                    });

                                    html += "<div style=\"margin:15px 0;padding:12px;background-color:#f8f9fa;border:2px solid #0073aa;border-radius:6px;text-align:right;\">";
                                    html += "<div style=\"font-weight:bold;font-size:15px;color:#0073aa;\">";
                                    html += group.billing_period + " 月別合計：" + monthlyTotal.toLocaleString() + "円";
                                    html += "</div>";
                                    html += "</div>";
                                });

                                if (res.data.company_info) {
                                    html += "<div style=\"margin-top:30px;padding:20px;border:1px solid #ddd;background:#fafafa;text-align:right;border-radius:6px;\">";
                                    html += res.data.company_info;
                                    html += "</div>";
                                }

                                // 印刷・PDF保存ボタンの上にチェックボックスを追加
                                html += '<div style="margin-top:20px;text-align:center;">';
                                html += '<label style="display:inline-flex;align-items:center;font-size:15px;font-weight:500;margin-bottom:12px;">';
                                html += '<input type="checkbox" id="set-invoice-completed" style="width:18px;height:18px;margin-right:8px;">';
                                html += '対象受注書の進捗を「請求済」に変更する';
                                html += '</label><br />';
                                html += '<button onclick="printInvoiceContent()" style="background-color:#0073aa;color:white;border:none;padding:10px 20px;border-radius:5px;cursor:pointer;font-size:14px;font-weight:500;">';
                                html += (typeof KTPSvgIcons !== 'undefined' ? KTPSvgIcons.getIcon('print', {'style': 'font-size:16px;vertical-align:middle;margin-right:5px;'}) : '<span class="material-symbols-outlined" style="font-size:16px;vertical-align:middle;margin-right:5px;">print</span>');
                                html += '印刷 PDF保存';
                                html += '</button>';
                                html += '</div>';

                                list.innerHTML = html;
                            } else {
                                list.innerHTML = "<div style=\"color:#888;\">該当する案件はありません。</div>";
                            }
                        } catch (e) {
                            console.error("[請求書発行] JSON解析エラー:", e);
                            console.error("[請求書発行] レスポンス内容:", xhr.responseText);
                            list.innerHTML = "<div style=\"color:#c00;\">データ取得エラー: " + e.message + "<br>レスポンス: " + xhr.responseText.substring(0, 200) + "</div>";
                        }
                    } else {
                        console.error("[請求書発行] HTTPエラー:", xhr.status, xhr.statusText);
                        console.error("[請求書発行] レスポンス内容:", xhr.responseText);
                        list.innerHTML = "<div style=\"color:#c00;\">通信エラー (HTTP " + xhr.status + "): " + xhr.statusText + "<br>レスポンス: " + xhr.responseText.substring(0, 200) + "</div>";
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
                    list.innerHTML = "<div style=\"color:#c00;\">顧客IDが見つかりません。</div>";
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
                        list.innerHTML = "<div style=\"color:#c00;\">セキュリティエラー: nonceが見つかりません。</div>";
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

function printInvoiceContent() {
    // チェックボックスの状態を確認
    var setInvoiceCompleted = document.getElementById('set-invoice-completed');
    var shouldSetCompleted = false;
    if (setInvoiceCompleted && setInvoiceCompleted.checked) {
        var confirmed = window.confirm('本当に対象受注書の進捗を「請求済」に変更しますか？\nこの操作は取り消せません。\nOKで印刷を続行、キャンセルで中止します。');
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
            alert("印刷エラー：請求書データが見つかりません");
            return;
        }

        var invoiceContent = invoiceList.innerHTML;
        if (!invoiceContent || invoiceContent.trim() === "") {
            console.error("[請求書印刷] 請求書の内容が空です");
            alert("印刷エラー：請求書の内容が空です");
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

        // チェックボックスとラベルを印刷用HTMLから除去
        var invoiceCompletedInput = tempDiv.querySelector('#set-invoice-completed');
        if (invoiceCompletedInput) {
            // 親labelごと削除
            var label = invoiceCompletedInput.closest('label');
            if (label && label.parentNode) {
                // labelの直後の<br>も削除
                var next = label.nextSibling;
                if (next && next.nodeName === 'BR') {
                    next.parentNode.removeChild(next);
                }
                label.parentNode.removeChild(label);
            } else {
                invoiceCompletedInput.parentNode.removeChild(invoiceCompletedInput);
            }
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

        // 印刷用にデザイン設定を適用
        var rows = tempDiv.querySelectorAll('[style*="background"]');
        rows.forEach(function(row, index) {
            if (row.style.background && (row.style.background.includes('#E7EEFD') || row.style.background.includes('#FFFFFF'))) {
                var bgColor = (index % 2 === 0) ? evenRowColor : oddRowColor;
                row.style.background = bgColor;
                console.log("[請求書印刷] 行の色を更新:", index, bgColor);
            }
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
        
        // ファイル名を生成: 請求書_{顧客名}_ID-{顧客ID}_{今日の日付}.pdf
        var filename = '請求書_' + (clientName || '顧客') + '_ID-' + (clientId || '0') + '_' + todayStr + '.pdf';
        
        // 印刷用のスタイルを適用したHTMLを生成
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
        printHTML += 'body { font-family: "Noto Sans JP", "Hiragino Kaku Gothic ProN", "Yu Gothic", Meiryo, sans-serif; font-size: 12px; line-height: 1.4; color: #333; background: white; padding: 20px; -webkit-print-color-adjust: exact; print-color-adjust: exact; }';
        printHTML += '.page-container { width: 210mm; max-width: 210mm; margin: 0 auto; background: white; padding: 50px; }';
        printHTML += '@page { size: A4; margin: 50px; }';
        printHTML += '@page :first { size: A4; margin: 50px; }';
        printHTML += '@media print { body { margin: 0; padding: 0; background: white; } .page-container { box-shadow: none; margin: 0; padding: 0; width: auto; max-width: none; } }';
        printHTML += '@media print { button, .no-print { display: none !important; } }';
        printHTML += 'h1, h2, h3, h4, h5, h6 { font-weight: bold; }';
        printHTML += '* { -webkit-print-color-adjust: exact !important; color-adjust: exact !important; print-color-adjust: exact !important; }';
        printHTML += '</style>';
        printHTML += '</head>';
        printHTML += '<body>';
        printHTML += '<div class="page-container">';
        printHTML += invoiceContent;
        printHTML += '</div>';
        printHTML += '</body>';
        printHTML += '</html>';

        console.log("[請求書印刷] 印刷HTML生成完了");
        console.log("[請求書印刷] ファイル名:", filename);

        // プリントアイコンの方法を参考に、現在のページの内容を印刷用HTMLに置き換えて印刷
        var originalBody = document.body.innerHTML;
        var originalTitle = document.title;
        
        // ページの内容を印刷用に変更
        document.body.innerHTML = printHTML;
        document.title = filename;
        
        console.log("[請求書印刷] 印刷用HTMLに置き換え完了");
        console.log("[請求書印刷] ファイル名:", filename);
        
        // 印刷完了イベントを設定
        window.addEventListener('afterprint', function() {
            console.log('[印刷完了] afterprintイベントが発火しました');
            setTimeout(function() {
                var popup = document.getElementById('ktp-invoice-preview-popup');
                if (popup) {
                    popup.style.display = 'none';
                    console.log('[印刷完了] afterprintイベントでポップアップを閉じました');
                }
            }, 1000);
        });
        
        // 印刷ダイアログを表示
        window.print();
        
        // 印刷完了後、元の内容に戻す（プリントアイコンと同じ方法）
        setTimeout(function() {
            document.body.innerHTML = originalBody;
            document.title = originalTitle;
            
            console.log("[請求書印刷] 印刷完了。元のページに戻しました。");
            
            // 請求書プレビューポップアップを再表示（必要に応じて）
            if (window.refreshInvoicePreview) {
                window.refreshInvoicePreview();
            }
            
            // 印刷完了後にポップアップを閉じる（進捗変更の有無に関係なく）
            setTimeout(function() {
                var popup = document.getElementById('ktp-invoice-preview-popup');
                if (popup) {
                    popup.style.display = 'none';
                    console.log('[印刷完了] 請求書ポップアップを閉じました');
                } else {
                    console.warn('[印刷完了] 請求書ポップアップ要素が見つかりません');
                }
                
                // イベントリスナーを再設定
                setTimeout(function() {
                    var invoiceButton = document.getElementById("invoiceButton");
                    var popup = document.getElementById("ktp-invoice-preview-popup");
                    var list = document.getElementById("invoiceList");
                    
                    if (invoiceButton && popup && list) {
                        console.log('[印刷完了] イベントリスナーを再設定しました');
                    }
                }, 100);
            }, 500);
            
            // 印刷完了後に進捗変更Ajax
            if (shouldSetCompleted) {
                // ここでAjaxリクエストを送信
                var xhr = new XMLHttpRequest();
                xhr.open('POST', '/wp-admin/admin-ajax.php');
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                // 必要に応じて対象IDやnonceをセット
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
                        console.log('[進捗変更] 請求済みへの変更成功:', xhr.responseText);
                        
                        // ★ 注文履歴リストのUIを即座に更新
                        try {
                            var response = JSON.parse(xhr.responseText);
                            if (response.success) {
                                console.log('[進捗変更] UI更新開始 - 更新件数:', response.data.updated);
                                updateOrderHistoryProgress(clientId, 4, 5); // 完了→請求済
                                
                                // 成功メッセージを表示
                                if (typeof window.showSuccessNotification === 'function') {
                                    window.showSuccessNotification('対象受注書の進捗を「請求済」に変更しました');
                                } else {
                                    console.log('[進捗変更] 成功: ' + response.data.updated + '件の受注書を「請求済」に変更しました');
                                }
                                
                                // ★ ポップアップを自動的に閉じる
                                setTimeout(function() {
                                    var popup = document.getElementById('ktp-invoice-preview-popup');
                                    if (popup) {
                                        popup.style.display = 'none';
                                        console.log('[UI更新] 請求書ポップアップを閉じました');
                                    }
                                }, 2000);
                            }
                        } catch (e) {
                            console.error('[進捗変更] レスポンス解析エラー:', e);
                        }
                    } else {
                        console.error('[進捗変更] リクエスト失敗:', xhr.status);
                    }
                };
                xhr.onerror = function() {
                    console.error('[進捗変更] ネットワークエラー');
                };
                xhr.send(params);
            }
        }, 1000);

    } catch (error) {
        console.error("[請求書印刷] エラーが発生しました:", error);
        alert("印刷エラーが発生しました: " + error.message);
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