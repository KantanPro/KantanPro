/**
 * 協力会社フォームの郵便番号 → 住所の自動入力。
 *
 * 住所検索は WordPress 側の AJAX（ktp_lookup_postal_address）経由で、
 * 管理者が明示的に有効化した日本郵便の公式API（サーバー側で呼び出し）だけを使う。
 * ブラウザから外部サービスへ直接通信しない。設定が無効なときはこのスクリプト自体が
 * 読み込まれない（PHP 側で enqueue を抑止）。
 */
(function () {
	'use strict';

	var cfg = window.ktpSupplierPostal || {};
	if (!cfg.ajaxUrl || !cfg.nonce) {
		return;
	}

	document.addEventListener('DOMContentLoaded', function () {
		var postalCode = document.querySelector('input[name="postal_code"]');
		if (!postalCode) {
			return;
		}

		var prefecture = document.querySelector('input[name="prefecture"]');
		var city = document.querySelector('input[name="city"]');
		var address = document.querySelector('input[name="address"]');

		postalCode.addEventListener('blur', function () {
			var zip = String(postalCode.value || '').replace(/[^0-9]/g, '');
			if (zip.length !== 7) {
				return;
			}

			var fd = new FormData();
			fd.append('action', 'ktp_lookup_postal_address');
			fd.append('nonce', cfg.nonce);
			fd.append('zipcode', zip);

			fetch(cfg.ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
				.then(function (r) { return r.json(); })
				.then(function (res) {
					if (!res || !res.success || !res.data) {
						return;
					}
					if (prefecture) {
						prefecture.value = res.data.prefecture || '';
					}
					if (city) {
						city.value = (res.data.city != null ? String(res.data.city) : '') || '';
					}
					if (address) {
						// 番地は利用者に入力してもらう
						address.value = (res.data.address != null ? String(res.data.address) : '') || '';
					}
				})
				.catch(function () { /* 失敗時は手動入力 */ });
		});
	});
})();
