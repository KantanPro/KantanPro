(function ($) {
	'use strict';

	$(function () {
		var $btn = $('#ktp-fm-ai-suggest');
		var $status = $('#ktp-fm-ai-status');
		// 以前は <script type="application/json"> のデータ島から読んでいたが、
		// 生の script 出力をやめたため PHP 側が window に直接渡すようになった。
		var payload = window.ktpwpFmImportBootstrap;
		if (!$btn.length || !payload || typeof ktpwpFmImport === 'undefined') {
			return;
		}

		$btn.on('click', function () {
			$status.text(ktpwpFmImport.i18n.working);
			$btn.prop('disabled', true);

			$.post(
				ktpwpFmImport.ajaxUrl,
				{
					action: 'ktpwp_fm_import_ai_mapping',
					nonce: ktpwpFmImport.nonce,
					entity: payload.entity || 'client',
					headers: JSON.stringify(payload.headers || []),
					samples: JSON.stringify(payload.samples || [])
				}
			)
				.done(function (res) {
					if (!res || !res.success || !res.data || !res.data.map) {
						$status.text(ktpwpFmImport.i18n.error);
						return;
					}
					var map = res.data.map;
					Object.keys(map).forEach(function (field) {
						var idx = map[field];
						var $sel = $('select[name="ktpwp_fm_map[' + field + ']"]');
						if ($sel.length) {
							$sel.val(String(idx));
						}
					});
					$status.text(ktpwpFmImport.i18n.done);
				})
				.fail(function (xhr) {
					var msg = ktpwpFmImport.i18n.error;
					if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
						msg = xhr.responseJSON.data.message;
					}
					$status.text(msg);
				})
				.always(function () {
					$btn.prop('disabled', false);
				});
		});
	});
})(jQuery);
