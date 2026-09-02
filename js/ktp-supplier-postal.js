/**
 * 協力会社フォームの郵便番号 → 住所の自動入力。
 *
 * もともと class-ktpwp-tab-supplier.php の中に HEREDOC のインライン <script> として
 * 2箇所へ同じ内容が書かれていた。WordPress.org のガイドラインでインライン script と
 * HEREDOC の両方が禁じられているため、ファイルに切り出して enqueue する形にした。
 *
 * 通信先の zipcloud は readme.txt の "External services" に記載済み。
 * 送信するのは入力された郵便番号だけ。
 */
(function () {
	'use strict';

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

			var xhr = new XMLHttpRequest();
			xhr.open('GET', 'https://zipcloud.ibsnet.co.jp/api/search?zipcode=' + encodeURIComponent(zip));
			xhr.addEventListener('load', function () {
				var response;
				try {
					response = JSON.parse(xhr.responseText);
				} catch (err) {
					return;
				}
				if (!response || !response.results || !response.results[0]) {
					return;
				}
				var data = response.results[0];
				if (prefecture) {
					prefecture.value = data.address1;
				}
				if (city) {
					// 市区町村と町名を結合する
					city.value = data.address2 + data.address3;
				}
				if (address) {
					// 番地は利用者に入力してもらう
					address.value = '';
				}
			});
			xhr.send();
		});
	});
})();
