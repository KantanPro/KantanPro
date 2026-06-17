(function () {
	'use strict';

	var cfg = window.ktpContractBilling || {};
	var panel = document.getElementById('ktp-contract-billing-panel');
	if (!panel || !cfg.ajax_url) {
		return;
	}

	function showMessage(text, isError) {
		var el = document.getElementById('ktp-contract-billing-message');
		if (!el) {
			return;
		}
		el.textContent = text;
		el.className = 'ktp-contract-billing-panel__message' + (isError ? ' is-error' : ' is-success');
		el.style.display = 'block';
	}

	function buildOrderUrl(orderId) {
		try {
			var url = new URL(window.location.href);
			url.searchParams.set('tab_name', 'order');
			url.searchParams.set('order_id', String(orderId));
			url.searchParams.delete('recurring_billing');
			url.searchParams.delete('billing_period');
			return url.toString();
		} catch (e) {
			return '';
		}
	}

	function post(action, data) {
		var body = new URLSearchParams();
		body.append('action', action);
		body.append('nonce', cfg.nonce || '');
		Object.keys(data).forEach(function (key) {
			body.append(key, data[key]);
		});

		return fetch(cfg.ajax_url, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
			},
			body: body.toString(),
		}).then(function (res) {
			return res.text().then(function (text) {
				if (!text) {
					throw new Error('empty');
				}
				try {
					return JSON.parse(text);
				} catch (e) {
					if (text === '0' || text === '-1') {
						throw new Error('ajax_failed');
					}
					throw e;
				}
			});
		});
	}

	function reloadSoon() {
		window.setTimeout(function () {
			window.location.reload();
		}, 600);
	}

	panel.addEventListener('click', function (event) {
		var generateOne = event.target.closest('.ktp-contract-billing-generate-one');
		if (generateOne) {
			event.preventDefault();
			generateOne.disabled = true;
			post('ktp_generate_contract_order', {
				contract_id: generateOne.getAttribute('data-contract-id') || '',
				period: generateOne.getAttribute('data-period') || panel.getAttribute('data-period') || '',
			})
				.then(function (json) {
					if (!json || typeof json !== 'object' || !json.success) {
						var errMsg = '案件の紐付けに失敗しました。';
						if (json && typeof json.data === 'string' && json.data !== '') {
							errMsg = json.data;
						}
						showMessage(errMsg, true);
						generateOne.disabled = false;
						return;
					}
					showMessage(json.data.message || '案件を紐付けしました。', false);
					var orderId = json.data && json.data.order_id ? parseInt(json.data.order_id, 10) : 0;
					var orderUrl = orderId > 0 ? buildOrderUrl(orderId) : (json.data.order_url || '');
					if (orderUrl) {
						window.location.href = orderUrl;
						return;
					}
					reloadSoon();
				})
				.catch(function () {
					showMessage('通信エラーが発生しました。ページを再読み込みして再度お試しください。', true);
					generateOne.disabled = false;
				});
			return;
		}

		var generateAll = event.target.closest('#ktp-contract-billing-generate-all');
		if (generateAll) {
			event.preventDefault();
			if (!window.confirm('未紐付けの定期契約を一括で案件紐付けします。よろしいですか？')) {
				return;
			}
			generateAll.disabled = true;
			post('ktp_generate_all_contract_orders', {
				period: generateAll.getAttribute('data-period') || panel.getAttribute('data-period') || '',
			})
				.then(function (json) {
					if (!json || typeof json !== 'object' || !json.success) {
						var errMsg = '一括紐付けに失敗しました。';
						if (json && typeof json.data === 'string' && json.data !== '') {
							errMsg = json.data;
						}
						showMessage(errMsg, true);
						generateAll.disabled = false;
						return;
					}
					showMessage(json.data.message || '一括紐付けが完了しました。', false);
					reloadSoon();
				})
				.catch(function () {
					showMessage('通信エラーが発生しました。', true);
					generateAll.disabled = false;
				});
			return;
		}

		var sendReminderOne = event.target.closest('.ktp-contract-billing-send-reminder-one');
		if (sendReminderOne) {
			event.preventDefault();
			sendReminderOne.disabled = true;
			post('ktp_send_contract_reminder', {
				contract_id: sendReminderOne.getAttribute('data-contract-id') || '',
				period: sendReminderOne.getAttribute('data-period') || panel.getAttribute('data-period') || '',
			})
				.then(function (json) {
					if (!json || typeof json !== 'object' || !json.success) {
						var errMsg = '予告メールの送信に失敗しました。';
						if (json && typeof json.data === 'string' && json.data !== '') {
							errMsg = json.data;
						}
						showMessage(errMsg, true);
						sendReminderOne.disabled = false;
						return;
					}
					showMessage(json.data.message || '請求予定メールを送信しました。', false);
					reloadSoon();
				})
				.catch(function () {
					showMessage('通信エラーが発生しました。', true);
					sendReminderOne.disabled = false;
				});
			return;
		}

		var sendRemindersAll = event.target.closest('#ktp-contract-billing-send-reminders');
		if (sendRemindersAll) {
			event.preventDefault();
			if (!window.confirm('未送信の請求予定メールを一括送信します。よろしいですか？')) {
				return;
			}
			sendRemindersAll.disabled = true;
			post('ktp_send_pending_contract_reminders', {
				period: sendRemindersAll.getAttribute('data-period') || panel.getAttribute('data-period') || '',
			})
				.then(function (json) {
					if (!json || typeof json !== 'object' || !json.success) {
						var errMsg = '一括送信に失敗しました。';
						if (json && typeof json.data === 'string' && json.data !== '') {
							errMsg = json.data;
						}
						showMessage(errMsg, true);
						sendRemindersAll.disabled = false;
						return;
					}
					showMessage(json.data.message || '一括送信が完了しました。', false);
					reloadSoon();
				})
				.catch(function () {
					showMessage('通信エラーが発生しました。', true);
					sendRemindersAll.disabled = false;
				});
		}
	});
})();
