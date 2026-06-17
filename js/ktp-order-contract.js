(function () {
    'use strict';

    function t(key, fallback) {
        if (typeof ktpwpTranslate === 'function') {
            return ktpwpTranslate(key);
        }
        return fallback || key;
    }

    function getConfig() {
        return typeof ktpOrderContract !== 'undefined' ? ktpOrderContract : null;
    }

    function iconHtml(name) {
        var config = getConfig();
        if (config && config.icons && config.icons[name]) {
            return config.icons[name];
        }
        return '';
    }

    function formatAmountDisplay(value) {
        if (window.KTPNumberFormat && typeof window.KTPNumberFormat.decimal === 'function') {
            return window.KTPNumberFormat.decimal(value);
        }
        var num = Number(value || 0);
        if (!isFinite(num)) {
            return '0';
        }
        return String(num).replace(/(\.\d*?)0+$/, '$1').replace(/\.$/, '');
    }

    function qs(sel, root) {
        return (root || document).querySelector(sel);
    }

    function qsa(sel, root) {
        return Array.prototype.slice.call((root || document).querySelectorAll(sel));
    }

    function escapeAttr(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    function showMessage(text, isError) {
        var el = qs('#ktp-order-contract-message');
        if (!el) {
            return;
        }
        el.textContent = text || '';
        el.style.display = text ? 'block' : 'none';
        el.className = 'ktp-order-contract-message' + (isError ? ' ktp-order-contract-message--error' : ' ktp-order-contract-message--success');
    }

    function showSummary(draft) {
        var el = qs('#ktp-order-contract-summary');
        if (!el || !draft) {
            return;
        }

        var parts = [];
        parts.push('<div class="ktp-order-contract-summary__line"><strong>' + escapeAttr(t('サービス', 'サービス')) + ':</strong> ' + escapeAttr(draft.service_name) + '（' + escapeAttr(draft.billing_cycle_label) + '）</div>');

        if (draft.from_web_application) {
            parts.push('<div class="ktp-order-contract-summary__line ktp-order-contract-summary__note">' + escapeAttr(t('Web お申込み案件です。内容を確認して定期契約を登録してください。', 'Web お申込み案件です。内容を確認して定期契約を登録してください。')) + '</div>');
        }

        if (draft.recurring_items && draft.recurring_items.length) {
            var recurringText = draft.recurring_items.map(function (item) {
                return item.item_name + ' ' + formatAmountDisplay(item.amount) + t('円', '円');
            }).join('、');
            parts.push('<div class="ktp-order-contract-summary__line ktp-order-contract-summary__note">' + escapeAttr(t('定期請求項目', '定期請求項目') + ': ' + recurringText) + '</div>');
        }

        if (draft.initial_fees && draft.initial_fees.length) {
            var feeText = draft.initial_fees.map(function (fee) {
                return fee.fee_name + ' ' + formatAmountDisplay(fee.amount) + t('円', '円');
            }).join('、');
            parts.push('<div class="ktp-order-contract-summary__line ktp-order-contract-summary__note">' + escapeAttr(t('初回費用', '初回費用') + ': ' + feeText) + '</div>');
        }

        el.innerHTML = parts.join('');
        el.style.display = 'block';
    }

    function clearRecurringRows() {
        var tbody = qs('#ktp-oc-recurring-items-body');
        if (tbody) {
            tbody.innerHTML = '';
        }
    }

    function createRecurringRow(itemName, amount, taxRate) {
        var tbody = qs('#ktp-oc-recurring-items-body');
        if (!tbody) {
            return;
        }
        var tr = document.createElement('tr');
        tr.innerHTML =
            '<td><input type="text" class="ktp-oc-recurring-name" value="' + escapeAttr(itemName || '') + '" maxlength="255"></td>' +
            '<td><input type="number" class="ktp-oc-recurring-amount" min="0" step="0.01" value="' + escapeAttr(amount == null ? '' : amount) + '"></td>' +
            '<td><input type="number" class="ktp-oc-recurring-tax" min="0" max="100" step="1" value="' + escapeAttr(taxRate == null ? '' : taxRate) + '" placeholder="' + escapeAttr(t('非課税', '非課税')) + '"></td>' +
            '<td class="ktp-contract-recurring-items__actions"><button type="button" class="ktp-contract-action-btn ktp-contract-action-btn--danger ktp-contract-action-btn--icon ktp-oc-remove-recurring-row" title="' + escapeAttr(t('削除', '削除')) + '" aria-label="' + escapeAttr(t('削除', '削除')) + '">' + iconHtml('delete') + '</button></td>';
        tbody.appendChild(tr);
    }

    function ensureRecurringRows(count) {
        var tbody = qs('#ktp-oc-recurring-items-body');
        if (!tbody) {
            return;
        }
        while (tbody.children.length < count) {
            createRecurringRow('', '', '');
        }
    }

    function setRecurringItems(items) {
        clearRecurringRows();
        if (!items || !items.length) {
            ensureRecurringRows(3);
            return;
        }
        items.forEach(function (item) {
            createRecurringRow(item.item_name, item.amount, item.tax_rate);
        });
        ensureRecurringRows(Math.max(3, items.length));
        updateAmountFromRecurring();
    }

    function clearFeeRows() {
        var tbody = qs('#ktp-oc-initial-fees-body');
        if (tbody) {
            tbody.innerHTML = '';
        }
    }

    function createFeeRow(feeName, amount, taxRate) {
        var tbody = qs('#ktp-oc-initial-fees-body');
        if (!tbody) {
            return;
        }
        var tr = document.createElement('tr');
        tr.innerHTML =
            '<td><input type="text" class="ktp-oc-fee-name" value="' + escapeAttr(feeName || '') + '" maxlength="255"></td>' +
            '<td><input type="number" class="ktp-oc-fee-amount" min="0" step="0.01" value="' + escapeAttr(amount == null ? '' : amount) + '"></td>' +
            '<td><input type="number" class="ktp-oc-fee-tax" min="0" max="100" step="1" value="' + escapeAttr(taxRate == null ? '' : taxRate) + '" placeholder="' + escapeAttr(t('非課税', '非課税')) + '"></td>' +
            '<td class="ktp-contract-initial-fees__actions"><button type="button" class="ktp-contract-action-btn ktp-contract-action-btn--danger ktp-contract-action-btn--icon ktp-oc-remove-fee-row" title="' + escapeAttr(t('削除', '削除')) + '" aria-label="' + escapeAttr(t('削除', '削除')) + '">' + iconHtml('delete') + '</button></td>';
        tbody.appendChild(tr);
    }

    function setInitialFees(fees) {
        clearFeeRows();
        if (!fees || !fees.length) {
            return;
        }
        fees.forEach(function (fee) {
            createFeeRow(fee.fee_name, fee.amount, fee.tax_rate);
        });
    }

    function updateAmountFromRecurring() {
        var total = 0;
        qsa('#ktp-oc-recurring-items-body tr').forEach(function (row) {
            var name = qs('.ktp-oc-recurring-name', row);
            var amount = qs('.ktp-oc-recurring-amount', row);
            if (name && name.value.trim() !== '' && amount) {
                total += parseFloat(amount.value || '0') || 0;
            }
        });
        var amountEl = qs('#ktp-oc-amount');
        if (amountEl && total > 0) {
            amountEl.value = String(Math.round(total * 100) / 100);
        }
    }

    function collectRecurringItems() {
        var items = [];
        qsa('#ktp-oc-recurring-items-body tr').forEach(function (row) {
            var name = qs('.ktp-oc-recurring-name', row);
            var amount = qs('.ktp-oc-recurring-amount', row);
            var tax = qs('.ktp-oc-recurring-tax', row);
            if (!name || name.value.trim() === '') {
                return;
            }
            items.push({
                item_name: name.value.trim(),
                amount: amount ? amount.value : 0,
                tax_rate: tax && tax.value !== '' ? tax.value : null
            });
        });
        return items;
    }

    function collectInitialFees() {
        var fees = [];
        qsa('#ktp-oc-initial-fees-body tr').forEach(function (row) {
            var name = qs('.ktp-oc-fee-name', row);
            var amount = qs('.ktp-oc-fee-amount', row);
            var tax = qs('.ktp-oc-fee-tax', row);
            if (!name || name.value.trim() === '') {
                return;
            }
            fees.push({
                fee_name: name.value.trim(),
                amount: amount ? amount.value : 0,
                tax_rate: tax && tax.value !== '' ? tax.value : null
            });
        });
        return fees;
    }

    function applyDraft(draft) {
        var nameEl = qs('#ktp-oc-contract-name');
        if (nameEl) {
            nameEl.value = draft.contract_name || '';
        }
        var serviceEl = qs('#ktp-oc-service-id');
        if (serviceEl) {
            serviceEl.value = String(draft.service_id || '');
        }
        var amountEl = qs('#ktp-oc-amount');
        if (amountEl) {
            amountEl.value = draft.amount != null ? String(draft.amount) : '0';
        }
        var cycleEl = qs('#ktp-oc-billing-cycle');
        if (cycleEl && draft.billing_cycle) {
            cycleEl.value = draft.billing_cycle;
        }
        var dayEl = qs('#ktp-oc-billing-day');
        if (dayEl && draft.default_billing_day) {
            dayEl.value = String(draft.default_billing_day);
        }
        var memoEl = qs('#ktp-oc-memo');
        if (memoEl) {
            memoEl.value = draft.default_memo || '';
        }
        var linkEl = qs('#ktp-oc-link-order');
        if (linkEl) {
            linkEl.checked = !!draft.from_web_application;
        }
        var periodEl = qs('#ktp-oc-billing-period');
        if (periodEl && draft.billing_period) {
            periodEl.value = draft.billing_period;
        }
        var statusEl = qs('#ktp-oc-status');
        if (statusEl) {
            statusEl.value = draft.default_status || 'paused';
        }

        setRecurringItems(draft.recurring_items || []);
        setInitialFees(draft.initial_fees || []);
        showSummary(draft);
    }

    function openPanel() {
        var panel = qs('#ktp-order-contract-panel');
        if (!panel) {
            return;
        }
        panel.style.display = 'block';
        panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function closePanel() {
        var panel = qs('#ktp-order-contract-panel');
        if (panel) {
            panel.style.display = 'none';
        }
        showMessage('');
        var summary = qs('#ktp-order-contract-summary');
        if (summary) {
            summary.style.display = 'none';
            summary.innerHTML = '';
        }
    }

    function loadDraft(orderId) {
        var config = getConfig();
        if (!config || !config.ajax_url) {
            showMessage(t('設定の読み込みに失敗しました。', '設定の読み込みに失敗しました。'), true);
            return;
        }

        showMessage(t('読み込み中…', '読み込み中…'), false);

        var body = new FormData();
        body.append('action', 'ktp_get_order_contract_draft');
        body.append('nonce', config.nonce);
        body.append('order_id', String(orderId));

        fetch(config.ajax_url, {
            method: 'POST',
            credentials: 'same-origin',
            body: body
        })
            .then(function (res) { return res.json(); })
            .then(function (json) {
                if (!json || !json.success) {
                    throw new Error((json && json.data) || t('ドラフトの取得に失敗しました。', 'ドラフトの取得に失敗しました。'));
                }
                applyDraft(json.data);
                showMessage('');
            })
            .catch(function (err) {
                showMessage(err.message || t('ドラフトの取得に失敗しました。', 'ドラフトの取得に失敗しました。'), true);
            });
    }

    function submitConversion() {
        var config = getConfig();
        if (!config || !config.ajax_url) {
            showMessage(t('設定の読み込みに失敗しました。', '設定の読み込みに失敗しました。'), true);
            return;
        }

        var orderId = qs('#ktp-oc-order-id');
        var clientId = qs('#ktp-oc-client-id');
        var contractName = qs('#ktp-oc-contract-name');
        var serviceId = qs('#ktp-oc-service-id');

        if (!orderId || !clientId || !contractName || !serviceId) {
            showMessage(t('フォームが見つかりません。', 'フォームが見つかりません。'), true);
            return;
        }

        if (!contractName.value.trim()) {
            showMessage(t('契約名を入力してください。', '契約名を入力してください。'), true);
            return;
        }
        if (!serviceId.value) {
            showMessage(t('サービスを選択してください。', 'サービスを選択してください。'), true);
            return;
        }

        var submitBtn = qs('#ktp-oc-submit');
        if (submitBtn) {
            submitBtn.disabled = true;
        }

        showMessage(t('登録中…', '登録中…'), false);

        var body = new FormData();
        body.append('action', 'ktp_convert_order_to_contract');
        body.append('nonce', config.nonce);
        body.append('order_id', orderId.value);
        body.append('client_id', clientId.value);
        body.append('contract_name', contractName.value.trim());
        body.append('service_id', serviceId.value);
        body.append('amount', (qs('#ktp-oc-amount') || {}).value || '0');
        body.append('billing_cycle', (qs('#ktp-oc-billing-cycle') || {}).value || 'monthly');
        body.append('billing_day', (qs('#ktp-oc-billing-day') || {}).value || '1');
        body.append('payment_due_mode', (qs('#ktp-oc-payment-due-mode') || {}).value || 'client');
        body.append('start_date', (qs('#ktp-oc-start-date') || {}).value || '');
        body.append('end_date', (qs('#ktp-oc-end-date') || {}).value || '');
        body.append('status', (qs('#ktp-oc-status') || {}).value || 'active');
        body.append('memo', (qs('#ktp-oc-memo') || {}).value || '');

        var reminder = qs('#ktp-oc-send-reminder');
        if (reminder && reminder.checked) {
            body.append('send_reminder_mail', '1');
        }

        var linkOrder = qs('#ktp-oc-link-order');
        if (linkOrder && linkOrder.checked) {
            body.append('link_order_as_billing', '1');
            body.append('billing_period', (qs('#ktp-oc-billing-period') || {}).value || '');
        }

        body.append('initial_fees', JSON.stringify(collectInitialFees()));
        body.append('recurring_items', JSON.stringify(collectRecurringItems()));

        fetch(config.ajax_url, {
            method: 'POST',
            credentials: 'same-origin',
            body: body
        })
            .then(function (res) { return res.json(); })
            .then(function (json) {
                if (!json || !json.success) {
                    throw new Error((json && json.data) || t('登録に失敗しました。', '登録に失敗しました。'));
                }
                showMessage(json.data.message || t('定期契約を登録しました。', '定期契約を登録しました。'), false);
                window.setTimeout(function () {
                    window.location.reload();
                }, 800);
            })
            .catch(function (err) {
                showMessage(err.message || t('登録に失敗しました。', '登録に失敗しました。'), true);
                if (submitBtn) {
                    submitBtn.disabled = false;
                }
            });
    }

    function bindEvents() {
        document.addEventListener('click', function (event) {
            var target = event.target;

            var viewBtn = target.closest('.ktp-order-contract-btn--view');
            if (viewBtn) {
                var viewUrl = viewBtn.getAttribute('data-view-url');
                if (viewUrl) {
                    window.location.assign(viewUrl);
                }
                return;
            }

            if (target.closest('#ktp-order-contract-btn')) {
                openPanel();
                var panel = qs('#ktp-order-contract-panel');
                var orderId = panel ? panel.getAttribute('data-order-id') : '';
                if (orderId) {
                    loadDraft(orderId);
                }
                return;
            }

            if (target.closest('#ktp-order-contract-close') || target.closest('#ktp-oc-cancel')) {
                closePanel();
                return;
            }

            if (target.closest('#ktp-oc-submit')) {
                submitConversion();
                return;
            }

            if (target.closest('#ktp-oc-add-recurring-row')) {
                createRecurringRow('', '', '');
                return;
            }

            if (target.closest('.ktp-oc-remove-recurring-row')) {
                var recurringRow = target.closest('tr');
                if (recurringRow && recurringRow.parentNode) {
                    recurringRow.parentNode.removeChild(recurringRow);
                    updateAmountFromRecurring();
                }
                return;
            }

            if (target.closest('#ktp-oc-add-fee-row')) {
                createFeeRow('', '', '');
                return;
            }

            if (target.closest('.ktp-oc-remove-fee-row')) {
                var feeRow = target.closest('tr');
                if (feeRow && feeRow.parentNode) {
                    feeRow.parentNode.removeChild(feeRow);
                }
            }
        });

        document.addEventListener('change', function (event) {
            if (event.target && event.target.id === 'ktp-oc-fee-preset') {
                var value = event.target.value;
                if (!value) {
                    return;
                }
                var name = value === '__custom__' ? '' : value;
                createFeeRow(name, '', '');
                event.target.value = '';
            }

            if (event.target && event.target.id === 'ktp-oc-service-id') {
                var option = event.target.selectedOptions && event.target.selectedOptions[0];
                if (!option) {
                    return;
                }
                var cycle = option.getAttribute('data-cycle');
                var price = option.getAttribute('data-price');
                var recurringRaw = option.getAttribute('data-recurring-items');
                if (cycle && qs('#ktp-oc-billing-cycle')) {
                    qs('#ktp-oc-billing-cycle').value = cycle;
                }
                if (recurringRaw) {
                    try {
                        setRecurringItems(JSON.parse(recurringRaw));
                    } catch (e) {
                        ensureRecurringRows(3);
                    }
                } else if (price && qs('#ktp-oc-amount')) {
                    qs('#ktp-oc-amount').value = price;
                }
            }
        });

        document.addEventListener('input', function (event) {
            if (event.target && (event.target.classList.contains('ktp-oc-recurring-amount') || event.target.classList.contains('ktp-oc-recurring-name'))) {
                updateAmountFromRecurring();
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bindEvents);
    } else {
        bindEvents();
    }
})();
