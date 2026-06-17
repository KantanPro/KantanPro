(function () {
    'use strict';

    function t(key, fallback) {
        if (typeof ktpwpTranslate === 'function') {
            return ktpwpTranslate(key);
        }
        return fallback || key;
    }

    function getConfig() {
        return typeof ktpClientContract !== 'undefined' ? ktpClientContract : null;
    }

    function qs(sel, root) {
        return (root || document).querySelector(sel);
    }

    function qsa(sel, root) {
        return Array.prototype.slice.call((root || document).querySelectorAll(sel));
    }

    function todayLocalDateString() {
        var d = new Date();
        var month = String(d.getMonth() + 1);
        var day = String(d.getDate());
        if (month.length < 2) {
            month = '0' + month;
        }
        if (day.length < 2) {
            day = '0' + day;
        }
        return d.getFullYear() + '-' + month + '-' + day;
    }

    function showForm(isEdit) {
        var wrap = qs('#ktp-contract-form-wrap');
        var heading = qs('#ktp-contract-form-heading');
        if (!wrap) {
            return;
        }
        wrap.style.display = 'block';
        if (heading) {
            heading.textContent = isEdit
                ? t('定期契約を編集', '定期契約を編集')
                : t('定期契約を追加', '定期契約を追加');
        }
        wrap.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function hideForm() {
        var wrap = qs('#ktp-contract-form-wrap');
        if (wrap) {
            wrap.style.display = 'none';
        }
        resetForm();
    }

    function resetForm() {
        var idEl = qs('#ktp-contract-id');
        if (idEl) {
            idEl.value = '0';
        }
        var fields = ['#ktp-contract-name', '#ktp-contract-memo', '#ktp-contract-end-date'];
        fields.forEach(function (sel) {
            var el = qs(sel);
            if (el) {
                el.value = '';
            }
        });
        var amountEl = qs('#ktp-contract-amount');
        if (amountEl) {
            amountEl.value = '0';
        }
        var serviceEl = qs('#ktp-contract-service-id');
        if (serviceEl) {
            serviceEl.value = '';
        }
        var statusEl = qs('#ktp-contract-status');
        if (statusEl) {
            statusEl.value = 'active';
        }
        var startEl = qs('#ktp-contract-start-date');
        if (startEl) {
            startEl.value = todayLocalDateString();
        }
        var reminderEl = qs('#ktp-contract-send-reminder');
        if (reminderEl) {
            reminderEl.checked = true;
        }
        var paymentDueEl = qs('#ktp-contract-payment-due-mode');
        if (paymentDueEl) {
            paymentDueEl.value = 'contract';
        }
        clearInitialFeeRows();
        clearRecurringItemRows();
        ensureRecurringItemRows(3);
        setInitialFeesEditable(true);
        setRecurringItemsEditable(true);
        setContractCoreFieldsEditable(true);
        updateStripeBlock(null);
    }

    function createRecurringRow(itemName, amount, taxRate) {
        var tbody = qs('#ktp-contract-recurring-items-body');
        if (!tbody) {
            return;
        }
        var tr = document.createElement('tr');
        tr.innerHTML =
            '<td><input type="text" class="ktp-contract-recurring-name" value="' + escapeAttr(itemName || '') + '" maxlength="255"></td>' +
            '<td><input type="number" class="ktp-contract-recurring-amount" min="0" step="0.01" value="' + escapeAttr(amount || '') + '"></td>' +
            '<td><input type="number" class="ktp-contract-recurring-tax" min="0" max="100" step="1" value="' + escapeAttr(taxRate !== undefined && taxRate !== null ? taxRate : '') + '" placeholder="' + escapeAttr(t('非課税', '非課税')) + '"></td>' +
            '<td class="ktp-contract-recurring-items__actions"><button type="button" class="ktp-contract-action-btn ktp-contract-action-btn--danger ktp-contract-action-btn--icon ktp-contract-remove-recurring-row" title="' + escapeAttr(t('削除', '削除')) + '">' + getDeleteIconHtml() + '</button></td>';
        tbody.appendChild(tr);
    }

    function clearRecurringItemRows() {
        var tbody = qs('#ktp-contract-recurring-items-body');
        if (tbody) {
            tbody.innerHTML = '';
        }
    }

    function ensureRecurringItemRows(count) {
        var tbody = qs('#ktp-contract-recurring-items-body');
        if (!tbody) {
            return;
        }
        while (tbody.children.length < count) {
            createRecurringRow('', '', '');
        }
    }

    function setRecurringItemsEditable(editable) {
        var block = qs('#ktp-contract-recurring-items');
        var locked = qs('#ktp-contract-recurring-items-locked');
        var addBtn = qs('#ktp-contract-add-recurring-row');
        if (block) {
            block.classList.toggle('ktp-contract-recurring-items--locked', !editable);
        }
        if (locked) {
            locked.style.display = editable ? 'none' : 'block';
        }
        if (addBtn) {
            addBtn.disabled = !editable;
        }
        qsa('.ktp-contract-recurring-name, .ktp-contract-recurring-amount, .ktp-contract-recurring-tax').forEach(function (el) {
            el.readOnly = !editable;
            el.disabled = !editable;
        });
        qsa('.ktp-contract-remove-recurring-row').forEach(function (el) {
            el.disabled = !editable;
            el.style.display = editable ? '' : 'none';
        });
    }

    function collectRecurringItems() {
        var rows = qsa('#ktp-contract-recurring-items-body tr');
        return rows.map(function (row) {
            return {
                item_name: (qs('.ktp-contract-recurring-name', row) || {}).value || '',
                amount: (qs('.ktp-contract-recurring-amount', row) || {}).value || '',
                tax_rate: (qs('.ktp-contract-recurring-tax', row) || {}).value || ''
            };
        }).filter(function (item) {
            return item.item_name.trim() !== '' && parseFloat(item.amount) > 0;
        });
    }

    function updateAmountFromRecurringItems() {
        var amountEl = qs('#ktp-contract-amount');
        if (!amountEl) {
            return;
        }
        var total = collectRecurringItems().reduce(function (sum, item) {
            return sum + (parseFloat(item.amount) || 0);
        }, 0);
        if (total > 0) {
            amountEl.value = String(Math.round(total * 100) / 100);
        }
    }

    function applyRecurringItemsFromServiceOption() {
        var serviceEl = qs('#ktp-contract-service-id');
        if (!serviceEl || !serviceEl.selectedOptions.length) {
            return;
        }
        var raw = serviceEl.selectedOptions[0].getAttribute('data-recurring-items') || '[]';
        var items = [];
        try {
            items = JSON.parse(raw);
        } catch (e) {
            items = [];
        }
        clearRecurringItemRows();
        if (items.length) {
            items.forEach(function (item) {
                createRecurringRow(item.item_name, item.amount, item.tax_rate);
            });
            updateAmountFromRecurringItems();
        } else {
            ensureRecurringItemRows(3);
        }
    }

    function setContractCoreFieldsEditable(editable) {
        ['#ktp-contract-service-id', '#ktp-contract-billing-cycle'].forEach(function (sel) {
            var el = qs(sel);
            if (el) {
                el.disabled = !editable;
            }
        });
    }

    function applyServiceDefaults() {
        var serviceEl = qs('#ktp-contract-service-id');
        if (!serviceEl || !serviceEl.selectedOptions.length) {
            return;
        }
        var opt = serviceEl.selectedOptions[0];
        var price = opt.getAttribute('data-price');
        var cycle = opt.getAttribute('data-cycle');
        var amountEl = qs('#ktp-contract-amount');
        var cycleEl = qs('#ktp-contract-billing-cycle');
        if (amountEl && price !== null && amountEl.value === '0') {
            amountEl.value = price;
        }
        if (cycleEl && cycle) {
            cycleEl.value = cycle;
        }
        applyRecurringItemsFromServiceOption();
    }

    function getDeleteIconHtml() {
        if (window.KTPSvgIcons && typeof window.KTPSvgIcons.getIcon === 'function') {
            return window.KTPSvgIcons.getIcon('delete', {
                class: 'ktp-svg-icon',
                style: 'font-size:18px;line-height:1;'
            });
        }
        return '<span class="material-symbols-outlined" aria-hidden="true">delete</span>';
    }

    function createFeeRow(feeName, amount, taxRate) {
        var tbody = qs('#ktp-contract-initial-fees-body');
        if (!tbody) {
            return;
        }
        var tr = document.createElement('tr');
        tr.innerHTML =
            '<td><input type="text" class="ktp-contract-fee-name" value="' + escapeAttr(feeName || '') + '" maxlength="255"></td>' +
            '<td><input type="number" class="ktp-contract-fee-amount" min="0" step="0.01" value="' + escapeAttr(amount || '') + '"></td>' +
            '<td><input type="number" class="ktp-contract-fee-tax" min="0" max="100" step="1" value="' + escapeAttr(taxRate !== undefined && taxRate !== null ? taxRate : '') + '" placeholder="' + escapeAttr(t('非課税', '非課税')) + '"></td>' +
            '<td class="ktp-contract-initial-fees__actions"><button type="button" class="ktp-contract-action-btn ktp-contract-action-btn--danger ktp-contract-action-btn--icon ktp-contract-remove-fee-row" title="' + escapeAttr(t('削除', '削除')) + '">' + getDeleteIconHtml() + '</button></td>';
        tbody.appendChild(tr);
    }

    function escapeAttr(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;');
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    function clearInitialFeeRows() {
        var tbody = qs('#ktp-contract-initial-fees-body');
        if (tbody) {
            tbody.innerHTML = '';
        }
    }

    function setInitialFeesEditable(editable) {
        var block = qs('#ktp-contract-initial-fees');
        var locked = qs('#ktp-contract-initial-fees-locked');
        var addBtn = qs('#ktp-contract-add-fee-row');
        var preset = qs('#ktp-contract-fee-preset');
        if (block) {
            block.classList.toggle('ktp-contract-initial-fees--locked', !editable);
        }
        if (locked) {
            locked.style.display = editable ? 'none' : 'block';
        }
        if (addBtn) {
            addBtn.disabled = !editable;
        }
        if (preset) {
            preset.disabled = !editable;
        }
        qsa('.ktp-contract-fee-name, .ktp-contract-fee-amount, .ktp-contract-fee-tax').forEach(function (el) {
            el.readOnly = !editable;
            el.disabled = !editable;
        });
        qsa('.ktp-contract-remove-fee-row').forEach(function (el) {
            el.disabled = !editable;
            el.style.display = editable ? '' : 'none';
        });
    }

    function collectInitialFees() {
        var rows = qsa('#ktp-contract-initial-fees-body tr');
        return rows.map(function (row) {
            return {
                fee_name: (qs('.ktp-contract-fee-name', row) || {}).value || '',
                amount: (qs('.ktp-contract-fee-amount', row) || {}).value || '',
                tax_rate: (qs('.ktp-contract-fee-tax', row) || {}).value || ''
            };
        }).filter(function (fee) {
            return fee.fee_name.trim() !== '' && parseFloat(fee.amount) > 0;
        });
    }

    function collectFormData(config) {
        return {
            action: 'ktp_save_contract',
            nonce: config.nonce,
            contract_id: (qs('#ktp-contract-id') || {}).value || '0',
            client_id: (qs('#ktp-contract-client-id') || {}).value || config.client_id,
            contract_name: (qs('#ktp-contract-name') || {}).value || '',
            service_id: (qs('#ktp-contract-service-id') || {}).value || '',
            amount: (qs('#ktp-contract-amount') || {}).value || '0',
            billing_cycle: (qs('#ktp-contract-billing-cycle') || {}).value || 'monthly',
            billing_day: (qs('#ktp-contract-billing-day') || {}).value || '1',
            payment_due_mode: (qs('#ktp-contract-payment-due-mode') || {}).value || 'contract',
            start_date: (qs('#ktp-contract-start-date') || {}).value || '',
            end_date: (qs('#ktp-contract-end-date') || {}).value || '',
            status: (qs('#ktp-contract-status') || {}).value || 'active',
            send_reminder_mail: (qs('#ktp-contract-send-reminder') || {}).checked ? '1' : '0',
            memo: (qs('#ktp-contract-memo') || {}).value || '',
            initial_fees: JSON.stringify(collectInitialFees()),
            recurring_items: JSON.stringify(collectRecurringItems())
        };
    }

    function postFormData(config, data) {
        var body = new FormData();
        Object.keys(data).forEach(function (key) {
            body.append(key, data[key]);
        });
        return fetch(config.ajax_url, {
            method: 'POST',
            body: body,
            credentials: 'same-origin'
        }).then(function (response) {
            return response.json();
        });
    }

    function fillForm(contract) {
        var map = {
            '#ktp-contract-id': contract.id,
            '#ktp-contract-name': contract.contract_name,
            '#ktp-contract-service-id': contract.service_id,
            '#ktp-contract-amount': contract.amount,
            '#ktp-contract-billing-cycle': contract.billing_cycle,
            '#ktp-contract-billing-day': contract.billing_day,
            '#ktp-contract-payment-due-mode': contract.payment_due_mode || 'contract',
            '#ktp-contract-start-date': contract.start_date || '',
            '#ktp-contract-end-date': contract.end_date || '',
            '#ktp-contract-status': contract.status,
            '#ktp-contract-memo': contract.memo || ''
        };
        Object.keys(map).forEach(function (sel) {
            var el = qs(sel);
            if (el) {
                el.value = map[sel];
            }
        });
        var reminderEl = qs('#ktp-contract-send-reminder');
        if (reminderEl) {
            reminderEl.checked = parseInt(contract.send_reminder_mail, 10) === 1;
        }
        clearInitialFeeRows();
        (contract.initial_fees || []).forEach(function (fee) {
            createFeeRow(fee.fee_name, fee.amount, fee.tax_rate);
        });
        clearRecurringItemRows();
        (contract.recurring_items || []).forEach(function (item) {
            createRecurringRow(item.item_name, item.amount, item.tax_rate);
        });
        if (!(contract.recurring_items || []).length) {
            ensureRecurringItemRows(3);
        }
        var firstBilled = parseInt(contract.first_billed, 10) === 1;
        setInitialFeesEditable(!firstBilled);
        setRecurringItemsEditable(!firstBilled);
        setContractCoreFieldsEditable(!firstBilled);
        updateStripeBlock(contract.stripe_subscription || null);
    }

    function updateStripeBlock(stripeData) {
        var block = qs('#ktp-contract-stripe-block');
        var statusEl = qs('#ktp-contract-stripe-status');
        var setupWrap = qs('#ktp-contract-stripe-setup');
        var urlWrap = qs('#ktp-contract-setup-url-wrap');
        var urlInput = qs('#ktp-contract-setup-url');
        var setupBtn = qs('#ktp-contract-setup-link-btn');
        var config = getConfig();

        if (!block || !config || !config.stripe_enabled) {
            return;
        }

        if (!stripeData || stripeData.applicable === false) {
            block.style.display = 'none';
            return;
        }

        block.style.display = 'block';

        if (statusEl) {
            var html = '<dl>';
            html += '<dt>' + escapeHtml(t('ステータス', 'ステータス')) + '</dt>';
            html += '<dd><span class="ktp-contract-stripe-status ktp-contract-stripe-status--' + escapeAttr(stripeData.status || 'unknown') + '">' + escapeHtml(stripeData.status_label || '—') + '</span></dd>';

            if (stripeData.subscription_id) {
                html += '<dt>' + escapeHtml(t('Subscription ID', 'Subscription ID')) + '</dt>';
                html += '<dd>' + escapeHtml(stripeData.subscription_id) + '</dd>';
            }

            if (stripeData.next_billing_date) {
                html += '<dt>' + escapeHtml(t('次回請求日', '次回請求日')) + '</dt>';
                html += '<dd>' + escapeHtml(stripeData.next_billing_date) + '</dd>';
            }

            html += '</dl>';
            statusEl.innerHTML = html;
        }

        var showSetup = !!stripeData.needs_setup_link;
        if (setupWrap) {
            setupWrap.style.display = showSetup ? 'block' : 'none';
        }
        if (setupBtn) {
            setupBtn.disabled = false;
        }

        if (urlWrap && urlInput) {
            if (stripeData.setup_url) {
                urlWrap.style.display = 'block';
                urlInput.value = stripeData.setup_url;
            } else {
                urlWrap.style.display = 'none';
                urlInput.value = '';
            }
        }
    }

    function createSetupCheckoutLink(config) {
        var contractId = (qs('#ktp-contract-id') || {}).value || '0';
        if (parseInt(contractId, 10) <= 0) {
            alert(t('契約を保存してからカード登録リンクを発行してください。', '契約を保存してからカード登録リンクを発行してください。'));
            return;
        }

        var setupBtn = qs('#ktp-contract-setup-link-btn');
        if (setupBtn) {
            setupBtn.disabled = true;
        }

        postFormData(config, {
            action: 'ktp_create_contract_setup_checkout',
            nonce: config.nonce,
            contract_id: contractId,
            client_id: config.client_id
        }).then(function (result) {
            if (setupBtn) {
                setupBtn.disabled = false;
            }
            if (!result.success) {
                alert(result.data || t('リンクの発行に失敗しました。', 'リンクの発行に失敗しました。'));
                return;
            }
            var urlWrap = qs('#ktp-contract-setup-url-wrap');
            var urlInput = qs('#ktp-contract-setup-url');
            if (urlInput && result.data && result.data.url) {
                urlInput.value = result.data.url;
            }
            if (urlWrap) {
                urlWrap.style.display = 'block';
            }
            if (typeof showSuccessNotification === 'function') {
                showSuccessNotification(result.data.message || t('カード登録リンクを発行しました。', 'カード登録リンクを発行しました。'));
            }
        }).catch(function () {
            if (setupBtn) {
                setupBtn.disabled = false;
            }
            alert(t('リンクの発行に失敗しました。', 'リンクの発行に失敗しました。'));
        });
    }

    function copySetupUrl() {
        var urlInput = qs('#ktp-contract-setup-url');
        if (!urlInput || !urlInput.value) {
            return;
        }

        if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
            navigator.clipboard.writeText(urlInput.value).then(function () {
                if (typeof showSuccessNotification === 'function') {
                    showSuccessNotification(t('URLをコピーしました。', 'URLをコピーしました。'));
                }
            }).catch(function () {
                urlInput.select();
                document.execCommand('copy');
            });
            return;
        }

        urlInput.select();
        document.execCommand('copy');
    }

    function saveContract(config) {
        var data = collectFormData(config);
        if (!data.contract_name.trim()) {
            alert(t('契約名を入力してください。', '契約名を入力してください。'));
            return;
        }
        if (!data.service_id) {
            alert(t('サービスを選択してください。', 'サービスを選択してください。'));
            return;
        }
        postFormData(config, data).then(function (result) {
            if (result.success) {
                if (typeof showSuccessNotification === 'function') {
                    showSuccessNotification(result.data.message);
                }
                window.location.reload();
            } else {
                alert(result.data || t('保存に失敗しました。', '保存に失敗しました。'));
            }
        }).catch(function () {
            alert(t('保存に失敗しました。', '保存に失敗しました。'));
        });
    }

    function loadContract(config, contractId) {
        postFormData(config, {
            action: 'ktp_get_contract',
            nonce: config.nonce,
            contract_id: contractId,
            client_id: config.client_id
        }).then(function (result) {
            if (!result.success) {
                alert(result.data || t('契約の取得に失敗しました。', '契約の取得に失敗しました。'));
                return;
            }
            fillForm(result.data);
            showForm(true);
        }).catch(function () {
            alert(t('契約の取得に失敗しました。', '契約の取得に失敗しました。'));
        });
    }

    function deleteContract(config, contractId) {
        if (!confirm(t('この定期契約を削除しますか？', 'この定期契約を削除しますか？'))) {
            return;
        }
        postFormData(config, {
            action: 'ktp_delete_contract',
            nonce: config.nonce,
            contract_id: contractId,
            client_id: config.client_id
        }).then(function (result) {
            if (result.success) {
                if (typeof showSuccessNotification === 'function') {
                    showSuccessNotification(result.data.message);
                }
                window.location.reload();
            } else {
                alert(result.data || t('削除に失敗しました。', '削除に失敗しました。'));
            }
        }).catch(function () {
            alert(t('削除に失敗しました。', '削除に失敗しました。'));
        });
    }

    function bindEvents(config) {
        var addBtn = qs('#ktp-contract-add-btn');
        if (addBtn) {
            addBtn.addEventListener('click', function () {
                resetForm();
                showForm(false);
            });
        }

        var cancelBtn = qs('#ktp-contract-cancel-btn');
        if (cancelBtn) {
            cancelBtn.addEventListener('click', hideForm);
        }

        var saveBtn = qs('#ktp-contract-save-btn');
        if (saveBtn) {
            saveBtn.addEventListener('click', function () {
                saveContract(config);
            });
        }

        var serviceEl = qs('#ktp-contract-service-id');
        if (serviceEl) {
            serviceEl.addEventListener('change', applyServiceDefaults);
        }

        var addFeeBtn = qs('#ktp-contract-add-fee-row');
        if (addFeeBtn) {
            addFeeBtn.addEventListener('click', function () {
                createFeeRow('', '', '');
            });
        }

        var addRecurringBtn = qs('#ktp-contract-add-recurring-row');
        if (addRecurringBtn) {
            addRecurringBtn.addEventListener('click', function () {
                createRecurringRow('', '', '');
            });
        }

        var setupLinkBtn = qs('#ktp-contract-setup-link-btn');
        if (setupLinkBtn) {
            setupLinkBtn.addEventListener('click', function () {
                createSetupCheckoutLink(config);
            });
        }

        var setupCopyBtn = qs('#ktp-contract-setup-url-copy-btn');
        if (setupCopyBtn) {
            setupCopyBtn.addEventListener('click', copySetupUrl);
        }

        var recurringBody = qs('#ktp-contract-recurring-items-body');
        if (recurringBody) {
            recurringBody.addEventListener('input', updateAmountFromRecurringItems);
            recurringBody.addEventListener('click', function (event) {
                var btn = event.target.closest('.ktp-contract-remove-recurring-row');
                if (!btn) {
                    return;
                }
                var row = btn.closest('tr');
                if (row) {
                    row.remove();
                    updateAmountFromRecurringItems();
                }
            });
        }

        var presetEl = qs('#ktp-contract-fee-preset');
        if (presetEl) {
            presetEl.addEventListener('change', function () {
                var val = presetEl.value;
                if (!val) {
                    return;
                }
                if (val === '__custom__') {
                    createFeeRow('', '', '');
                } else {
                    createFeeRow(val, '', '');
                }
                presetEl.value = '';
            });
        }

        document.addEventListener('click', function (event) {
            var target = event.target;
            var removeBtn = target.closest ? target.closest('.ktp-contract-remove-fee-row') : null;
            if (removeBtn) {
                var row = removeBtn.closest('tr');
                if (row) {
                    row.remove();
                }
                return;
            }
            var editBtn = target.closest ? target.closest('.ktp-contract-edit-btn') : null;
            if (editBtn) {
                loadContract(config, editBtn.getAttribute('data-contract-id'));
                return;
            }
            var deleteBtn = target.closest ? target.closest('.ktp-contract-delete-btn') : null;
            if (deleteBtn) {
                deleteContract(config, deleteBtn.getAttribute('data-contract-id'));
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var section = qs('#ktp-contract-section');
        var config = getConfig();
        if (!section || !config) {
            return;
        }
        bindEvents(config);

        var params = new URLSearchParams(window.location.search);
        var openContractId = params.get('contract_id');
        if (openContractId && parseInt(openContractId, 10) > 0) {
            loadContract(config, openContractId);
        }
    });
})();
