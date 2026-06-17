(function () {
	'use strict';

	if (typeof window.ktpwpPublicProducts === 'undefined') {
		return;
	}

	var config = window.ktpwpPublicProducts;
	var i18n = config.i18n || {};
	var openModalCount = 0;

	function qs(root, selector) {
		return (root || document).querySelector(selector);
	}

	function qsa(root, selector) {
		return Array.prototype.slice.call((root || document).querySelectorAll(selector));
	}

	function parseProduct(el) {
		var raw = el.getAttribute('data-product');
		if (!raw) {
			return null;
		}
		try {
			return JSON.parse(raw);
		} catch (e) {
			return null;
		}
	}

	function escapeHtml(text) {
		var div = document.createElement('div');
		div.textContent = text == null ? '' : String(text);
		return div.innerHTML;
	}

	function parseAjaxJsonResponse(response) {
		return response.text().then(function (text) {
			var trimmed = (text == null ? '' : String(text)).trim();

			if (trimmed === '-1' || trimmed === '0') {
				return {
					success: false,
					data: {
						message: i18n.sessionExpired || i18n.networkError || '送信に失敗しました',
					},
				};
			}

			if (trimmed === '') {
				throw new Error('empty_response');
			}

			try {
				return JSON.parse(trimmed);
			} catch (error) {
				var start = trimmed.indexOf('{');
				var end = trimmed.lastIndexOf('}');
				if (start !== -1 && end > start) {
					return JSON.parse(trimmed.slice(start, end + 1));
				}
				throw error;
			}
		});
	}

	function lockBodyScroll() {
		openModalCount += 1;
		if (openModalCount === 1) {
			document.body.classList.add('ktpwp-public-product-modal-open');
		}
	}

	function unlockBodyScroll() {
		openModalCount = Math.max(0, openModalCount - 1);
		if (openModalCount === 0) {
			document.body.classList.remove('ktpwp-public-product-modal-open');
		}
	}

	function getProductStatusLabel(product) {
		if (product && product.status_label) {
			return product.status_label;
		}
		if (product && product.is_sold_out) {
			return i18n.soldOutBadge || '完売御礼！';
		}
		if (product && product.is_pending) {
			return i18n.pendingBadge || '保留中';
		}
		return '';
	}

	function getProductStatusNotice(product) {
		if (product && product.is_sold_out) {
			return i18n.soldOutNotice || 'こちらの商品は完売しました。';
		}
		if (product && (product.is_pending || product.acceptance_open === false)) {
			return i18n.pendingNotice || '現在お問い合わせを受け付けておりません。';
		}
		return '';
	}

	function buildDetailHtml(product) {
		var parts = [];
		var statusLabel = getProductStatusLabel(product);
		var statusNotice = getProductStatusNotice(product);
		var isClosed = product.acceptance_open === false;
		parts.push('<div class="ktpwp-public-product-detail__hero">');
		if (product.image) {
			var imageWrapClass = 'ktpwp-public-product-detail__image-wrap';
			if (isClosed) {
				imageWrapClass += ' ktpwp-public-product-item__image-wrap--pending';
			}
			parts.push('<div class="' + imageWrapClass + '">');
			parts.push(
				'<img src="' +
					escapeHtml(product.image) +
					'" alt="' +
					escapeHtml(product.name) +
					'" class="ktpwp-public-product-detail__image" loading="lazy" decoding="async" />'
			);
			if (isClosed && statusLabel) {
				parts.push(
					'<span class="ktpwp-public-product-item__pending-overlay" aria-hidden="true">' +
						'<span class="ktpwp-public-product-item__pending-overlay-badge">' +
						escapeHtml(statusLabel) +
						'</span></span>'
				);
			}
			parts.push('</div>');
		}
		parts.push('<div class="ktpwp-public-product-detail__meta">');
		parts.push('<h3 class="ktpwp-public-product-detail__name">' + escapeHtml(product.name) + '</h3>');
		if (isClosed && statusNotice) {
			parts.push(
				'<p class="ktpwp-public-product-detail__pending-notice">' +
					escapeHtml(statusNotice) +
					'</p>'
			);
		}
		if (product.category) {
			parts.push(
				'<p class="ktpwp-public-product-detail__category">' +
					escapeHtml(i18n.category || 'カテゴリ') +
					': ' +
					escapeHtml(product.category) +
					'</p>'
			);
		}
		if (product.recurring_items && product.recurring_items.length) {
			parts.push('<div class="ktpwp-public-product-detail__recurring-items">');
			product.recurring_items.forEach(function (item) {
				var amountWithUnit = escapeHtml(item.amount_display || '');
				if (product.unit) {
					amountWithUnit += '/' + escapeHtml(product.unit);
				}
				parts.push(
					'<div class="ktpwp-public-product-detail__recurring-item">' +
						'<span class="ktpwp-public-product-detail__recurring-item-line">' +
						escapeHtml(item.item_name || '') +
						' ' +
						amountWithUnit +
						'</span>' +
						'</div>'
				);
			});
			parts.push('</div>');
		} else if (product.price_display) {
			parts.push(
				'<p class="ktpwp-public-product-detail__price">' +
					escapeHtml(i18n.price || '単価') +
					': ' +
					escapeHtml(product.price_display) +
					(product.unit ? ' / ' + escapeHtml(product.unit) : '') +
					(product.is_recurring || product.is_recurring_contract
						? ' <span class="ktpwp-public-product-detail__recurring-badge">' + escapeHtml(i18n.recurringBadge || '定期') + '</span>'
						: '') +
					'</p>'
			);
		}
		if (product.initial_fees && product.initial_fees.length) {
			parts.push('<div class="ktpwp-public-product-detail__initial-fees">');
			parts.push(
				'<span class="ktpwp-public-product-detail__initial-fees-label">' +
					escapeHtml(i18n.initialFees || '初回費用') +
					'</span>'
			);
			product.initial_fees.forEach(function (fee, index) {
				parts.push('<span class="ktpwp-public-product-detail__initial-fees-item">');
				parts.push(
					escapeHtml(fee.fee_name || '') +
						' ' +
						escapeHtml(fee.amount_display || '') +
						(fee.tax_rate ? ' (' + escapeHtml(i18n.tax || '税率') + ' ' + escapeHtml(fee.tax_rate) + '%)' : '')
				);
				if (index === product.initial_fees.length - 1) {
					parts.push(
						'<span class="ktpwp-public-product-detail__initial-fees-note">' +
							escapeHtml(i18n.initialFeesNote || '初回請求時のみ') +
							'</span>'
					);
				}
				parts.push('</span>');
			});
			parts.push('</div>');
		}
		if (product.tax_rate !== '' && product.tax_rate != null && !(product.recurring_items && product.recurring_items.length)) {
			parts.push(
				'<p class="ktpwp-public-product-detail__tax">' +
					escapeHtml(i18n.tax || '税率') +
					': ' +
					escapeHtml(product.tax_rate) +
					'%</p>'
			);
		}
		if (product.memo) {
			parts.push(
				'<div class="ktpwp-public-product-detail__memo">' +
					'<span class="ktpwp-public-product-detail__memo-label">' +
					escapeHtml(i18n.memo || 'メモ') +
					'</span>' +
					'<div class="ktpwp-public-product-detail__memo-body">' +
					escapeHtml(product.memo) +
					'</div></div>'
			);
		}
		if (product.public_html) {
			parts.push(
				'<div class="ktpwp-public-product-detail__public-html">' +
					product.public_html +
				'</div>'
			);
		}
		parts.push('</div></div>');
		return parts.join('');
	}

	var imageLightbox = null;
	var imageLightboxLastFocus = null;

	function getImageLightbox() {
		if (imageLightbox) {
			return imageLightbox;
		}

		imageLightbox = qs(document, '#ktpwp-public-product-image-lightbox');
		if (!imageLightbox) {
			return null;
		}

		if (imageLightbox.parentNode !== document.body) {
			document.body.appendChild(imageLightbox);
		}

		var backdrop = qs(imageLightbox, '.ktpwp-public-product-image-lightbox__backdrop');
		var closeBtn = qs(imageLightbox, '.ktpwp-public-product-image-lightbox__close');
		var imageEl = qs(imageLightbox, '.ktpwp-public-product-image-lightbox__image');
		var captionEl = qs(imageLightbox, '.ktpwp-public-product-image-lightbox__caption');

		function onEscapeKey(event) {
			if (event.key === 'Escape') {
				closeImageLightbox();
			}
		}

		function closeImageLightbox() {
			if (imageLightbox.hidden) {
				return;
			}

			imageLightbox.hidden = true;
			imageLightbox.classList.remove('is-open');
			unlockBodyScroll();

			if (imageEl) {
				imageEl.removeAttribute('src');
				imageEl.alt = '';
			}
			if (captionEl) {
				captionEl.textContent = '';
				captionEl.hidden = true;
			}

			document.removeEventListener('keydown', onEscapeKey);

			if (imageLightboxLastFocus && typeof imageLightboxLastFocus.focus === 'function') {
				imageLightboxLastFocus.focus();
			}
		}

		imageLightbox.open = function (img) {
			if (!img || !imageEl) {
				return;
			}

			imageLightboxLastFocus = document.activeElement;
			imageEl.src = img.currentSrc || img.src || '';
			imageEl.alt = img.alt || '';

			if (captionEl) {
				var caption = img.alt || '';
				if (caption) {
					captionEl.textContent = caption;
					captionEl.hidden = false;
				} else {
					captionEl.textContent = '';
					captionEl.hidden = true;
				}
			}

			imageLightbox.hidden = false;
			imageLightbox.classList.add('is-open');
			lockBodyScroll();
			document.addEventListener('keydown', onEscapeKey);

			window.requestAnimationFrame(function () {
				if (closeBtn) {
					closeBtn.focus();
				}
			});
		};

		imageLightbox.close = closeImageLightbox;

		if (backdrop) {
			backdrop.addEventListener('click', closeImageLightbox);
		}
		if (closeBtn) {
			closeBtn.addEventListener('click', closeImageLightbox);
		}

		return imageLightbox;
	}

	function initImageLightbox(wrapper) {
		var lightbox = getImageLightbox();
		if (!lightbox) {
			return;
		}

		qsa(wrapper, '.ktpwp-public-product-item__image-btn').forEach(function (btn) {
			btn.addEventListener('click', function (event) {
				event.preventDefault();
				event.stopPropagation();

				var img = qs(btn, '.ktpwp-public-product-item__image');
				if (!img) {
					return;
				}

				lightbox.open(img);
			});
		});
	}

	function initCategoryFilter(wrapper) {
		var input = qs(wrapper, '.ktpwp-public-products-filter__input');
		if (!input) {
			return;
		}

		var clearBtn = qs(wrapper, '.ktpwp-public-products-filter__clear');
		var emptyMsg = qs(wrapper, '.ktpwp-public-products-filter__empty');
		var datalist = input.list || null;
		var knownCategories = [];

		if (datalist) {
			qsa(datalist, 'option').forEach(function (option) {
				var value = (option.value || '').trim().toLowerCase();
				if (value) {
					knownCategories.push(value);
				}
			});
		}

		function itemMatchesCategory(item, term) {
			var cat = (item.getAttribute('data-category') || '').trim().toLowerCase();
			if (!term) {
				return true;
			}
			if (cat === term) {
				return true;
			}
			if (knownCategories.indexOf(term) !== -1) {
				return cat === term;
			}
			return cat.indexOf(term) !== -1;
		}

		function setItemVisibility(item, show) {
			item.classList.toggle('ktpwp-public-product-item--filtered-out', !show);
			item.hidden = !show;
			if (show) {
				item.removeAttribute('aria-hidden');
			} else {
				item.setAttribute('aria-hidden', 'true');
			}
		}

		function applyFilter() {
			var term = (input.value || '').trim().toLowerCase();
			var visible = 0;
			var items = qsa(wrapper, '.ktpwp-public-product-item');

			items.forEach(function (item) {
				var show = itemMatchesCategory(item, term);
				setItemVisibility(item, show);
				if (show) {
					visible += 1;
				}
			});

			if (emptyMsg) {
				emptyMsg.hidden = visible > 0;
			}
		}

		input.addEventListener('input', applyFilter);
		input.addEventListener('change', applyFilter);
		input.addEventListener('blur', applyFilter);

		if (clearBtn) {
			clearBtn.addEventListener('click', function () {
				input.value = '';
				applyFilter();
				input.focus();
			});
		}

		applyFilter();
	}

	function initWrapper(wrapper) {
		initCategoryFilter(wrapper);
		initImageLightbox(wrapper);
		var detail = qs(wrapper, '#ktpwp-public-product-detail');
		if (!detail) {
			return;
		}

		if (detail.parentNode !== document.body) {
			document.body.appendChild(detail);
		}

		var content = qs(detail, '.ktpwp-public-product-detail__content');
		var form = qs(detail, '.ktpwp-public-product-order-form');
		var closeBtn = qs(detail, '.ktpwp-public-product-detail__close');
		var formCloseBtn = qs(form, '.ktpwp-public-product-order-form__close');
		var backdrop = qs(detail, '.ktpwp-public-product-detail__backdrop');
		var dialog = qs(detail, '.ktpwp-public-product-detail__panel');
		var dialogScroll = qs(detail, '.ktpwp-public-product-detail__scroll');
		var messageBox = qs(form, '.ktpwp-public-product-order-form__message');
		var submitBtn = qs(form, '.ktpwp-public-product-order-form__submit');
		var serviceIdInput = qs(form, 'input[name="service_id"]');
		var lastActiveElement = null;

		function onEscapeKey(event) {
			if (event.key === 'Escape') {
				closeDetail();
			}
		}

		function setActiveItem(el) {
			qsa(wrapper, '.ktpwp-public-product-item.is-active').forEach(function (node) {
				node.classList.remove('is-active');
			});
			if (el) {
				el.classList.add('is-active');
			}
		}

		function hideMessage() {
			if (!messageBox) {
				return;
			}
			messageBox.hidden = true;
			messageBox.textContent = '';
			messageBox.className = 'ktpwp-public-product-order-form__message';
		}

		function showMessage(text, type) {
			if (!messageBox) {
				return;
			}
			messageBox.hidden = false;
			messageBox.textContent = text;
			messageBox.className =
				'ktpwp-public-product-order-form__message ktpwp-public-product-order-form__message--' + (type || 'info');
		}

		function syncQuantityField(product) {
		if (!form) {
			return;
		}

		var field = qs(form, '.ktpwp-public-product-order-form__field--quantity');
		if (!field) {
			return;
		}

		var fixed = product && product.quantity_fixed === true;
		field.hidden = fixed;

		var qty = qs(field, 'input[name="quantity"]');
		if (qty) {
			qty.value = '1';
		}
	}

	function openDetail(el, options) {
			options = options || {};
			var product = parseProduct(el);
			if (!product || !product.id) {
				return;
			}

			var acceptanceOpen = product.acceptance_open !== false && !product.is_pending;
			lastActiveElement = document.activeElement;

			hideMessage();
			if (form) {
				form.reset();
				if (serviceIdInput) {
					serviceIdInput.value = String(product.id);
				}
				var qty = qs(form, 'input[name="quantity"]');
				if (qty) {
					qty.value = '1';
				}
				syncQuantityField(product);
				form.hidden = !acceptanceOpen;
			}

			if (content) {
				content.innerHTML = buildDetailHtml(product);
			}

			detail.hidden = false;
			detail.classList.add('is-open');
			lockBodyScroll();
			setActiveItem(el);

			document.addEventListener('keydown', onEscapeKey);

			window.requestAnimationFrame(function () {
				if (options.focusForm && form && !form.hidden) {
					if (dialogScroll) {
						dialogScroll.scrollTop = Math.max(0, form.offsetTop - 16);
					}
					var firstField = qs(form, 'input[name="contact_name"]');
					if (firstField) {
						firstField.focus();
						return;
					}
				}

				if (closeBtn) {
					closeBtn.focus();
				}
			});
		}

		function closeDetail() {
			if (detail.hidden) {
				return;
			}

			detail.hidden = true;
			detail.classList.remove('is-open');
			unlockBodyScroll();
			setActiveItem(null);
			hideMessage();
			document.removeEventListener('keydown', onEscapeKey);

			if (lastActiveElement && typeof lastActiveElement.focus === 'function') {
				lastActiveElement.focus();
			}
		}

		qsa(wrapper, '.ktpwp-public-product-item__inquire-btn').forEach(function (btn) {
			btn.addEventListener('click', function (event) {
				event.preventDefault();
				event.stopPropagation();

				if (btn.disabled) {
					return;
				}

				var item = btn.closest('.ktpwp-public-product-item');
				if (!item) {
					return;
				}

				openDetail(item, { focusForm: true });
			});
		});

		if (closeBtn) {
			closeBtn.addEventListener('click', closeDetail);
		}

		if (formCloseBtn) {
			formCloseBtn.addEventListener('click', closeDetail);
		}

		if (backdrop) {
			backdrop.addEventListener('click', closeDetail);
		}

		if (dialog) {
			dialog.addEventListener('click', function (event) {
				event.stopPropagation();
			});
		}

		if (form) {
			form.addEventListener('submit', function (event) {
				event.preventDefault();
				hideMessage();

				if (!serviceIdInput || !serviceIdInput.value) {
					showMessage(i18n.networkError || 'エラー', 'error');
					return;
				}

				var formData = new FormData(form);
				formData.append('action', 'ktpwp_public_product_submit');
				formData.append('nonce', config.nonce);

				if (submitBtn) {
					submitBtn.disabled = true;
					submitBtn.textContent = i18n.submitting || '送信中…';
				}

				fetch(config.ajaxUrl, {
					method: 'POST',
					body: formData,
					credentials: 'same-origin',
				})
					.then(parseAjaxJsonResponse)
					.then(function (json) {
						if (json && json.success) {
							showMessage((json.data && json.data.message) || i18n.submit || '送信しました', 'success');
							form.hidden = true;
						} else {
							var errMsg =
								(json && json.data && json.data.message) ||
								i18n.networkError ||
								'送信に失敗しました';
							showMessage(errMsg, 'error');
						}
					})
					.catch(function () {
						showMessage(i18n.networkError || '通信エラー', 'error');
					})
					.finally(function () {
						if (submitBtn) {
							submitBtn.disabled = false;
							submitBtn.textContent = i18n.submit || '送信する';
						}
					});
			});
		}
	}

	function boot() {
		qsa(document, '.ktpwp-public-products').forEach(initWrapper);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
