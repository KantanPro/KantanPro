(function () {
	'use strict';

	if (window.__ktpOrderDeleteConfirmBound) {
		return;
	}

	function getOrderDeleteConfirmMessage() {
		if (typeof window.ktpOrderDeleteConfirm !== 'undefined' && window.ktpOrderDeleteConfirm.message) {
			return window.ktpOrderDeleteConfirm.message;
		}
		return '本当にこの受注書を削除しますか？\n\n請求明細・原価明細・スタッフチャット・添付ファイル・メール送信履歴も削除されます。\nこの操作は元に戻せません。';
	}

	function handleDeleteClick(event) {
		var button = event.target && event.target.closest ? event.target.closest('.ktp-order-delete-trigger') : null;
		if (!button || button.disabled) {
			return;
		}

		var form = button.closest('form.ktp-order-delete-form');
		if (!form) {
			return;
		}

		event.preventDefault();
		event.stopPropagation();

		if (!window.confirm(getOrderDeleteConfirmMessage())) {
			return;
		}

		var confirmedInput = form.querySelector('input[name="delete_confirmed"]');
		if (confirmedInput) {
			confirmedInput.value = '1';
		}

		HTMLFormElement.prototype.submit.call(form);
	}

	document.addEventListener('click', handleDeleteClick, true);
	window.__ktpOrderDeleteConfirmBound = true;
})();
