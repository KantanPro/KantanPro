/**
 * KantanPro 数値表示ユーティリティ
 *
 * ルール: ユーザーが小数を入力していない限り、小数点以下は表示しない。
 */
(function (window) {
	'use strict';

	function trimTrailingZeros(str) {
		return String(str).replace(/(\.\d*?)0+$/, '$1').replace(/\.$/, '');
	}

	/**
	 * 税率・数量・金額などの表示用（末尾 0 省略）。
	 *
	 * @param {number|string|null|undefined} value
	 * @param {number} [maxDecimals=6]
	 * @returns {string}
	 */
	function formatDecimalDisplay(value, maxDecimals) {
		if (value === null || value === undefined || value === '') {
			return '';
		}

		var num = Number(value);
		if (!isFinite(num)) {
			return String(value);
		}

		var decimals = typeof maxDecimals === 'number' ? maxDecimals : 6;
		return trimTrailingZeros(num.toFixed(decimals));
	}

	window.KTPNumberFormat = {
		decimal: formatDecimalDisplay,
		trimTrailingZeros: trimTrailingZeros,
	};

	// 既存スクリプトとの互換
	window.formatDecimalDisplay = formatDecimalDisplay;
})(window);
