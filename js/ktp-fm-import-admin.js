(function ($) {
	'use strict';

	$(function () {
		var $btn = $('#ktp-fm-ai-suggest');
		var $status = $('#ktp-fm-ai-status');
		var $bootstrap = $('#ktp-fm-import-bootstrap');
		if (!$btn.length || !$bootstrap.length || typeof ktpFmImport === 'undefined') {
			return;
		}

		var payload;
		try {
			payload = JSON.parse($bootstrap.text());
		} catch (e) {
			return;
		}

		$btn.on('click', function () {
			$status.text(ktpFmImport.i18n.working);
			$btn.prop('disabled', true);

			$.post(
				ktpFmImport.ajaxUrl,
				{
					action: 'ktp_fm_import_ai_mapping',
					nonce: ktpFmImport.nonce,
					entity: payload.entity || 'client',
					headers: JSON.stringify(payload.headers || []),
					samples: JSON.stringify(payload.samples || [])
				}
			)
				.done(function (res) {
					if (!res || !res.success || !res.data || !res.data.map) {
						$status.text(ktpFmImport.i18n.error);
						return;
					}
					var map = res.data.map;
					Object.keys(map).forEach(function (field) {
						var idx = map[field];
						var $sel = $('select[name="ktp_fm_map[' + field + ']"]');
						if ($sel.length) {
							$sel.val(String(idx));
						}
					});
					$status.text(ktpFmImport.i18n.done);
				})
				.fail(function (xhr) {
					var msg = ktpFmImport.i18n.error;
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
