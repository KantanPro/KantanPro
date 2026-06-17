<?php
/**
 * External URL helpers (normalize + globe open link).
 *
 * @package KTPWP
 * @since 1.0.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'KTPWP_External_Url' ) ) {

	/**
	 * URL normalization and form field open-link UI (KantanBiz detail-cards 相当).
	 */
	class KTPWP_External_Url {

		/** @var bool */
		private static $script_printed = false;

		/**
		 * Normalize a user-entered URL for opening in a browser.
		 */
		public static function normalize( string $raw ): string {
			$raw = trim( $raw );
			if ( $raw === '' ) {
				return '';
			}

			$url = $raw;
			if ( ! preg_match( '/^https?:\/\//i', $url ) ) {
				$url = 'https://' . $url;
			}

			return filter_var( $url, FILTER_VALIDATE_URL ) !== false ? $url : '';
		}

		/**
		 * Heroicons globe (same path as KantanBiz clients/partials/detail-cards).
		 */
		public static function globe_svg(): string {
			return '<svg xmlns="http://www.w3.org/2000/svg" class="ktp-url-open-link__icon" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">'
				. '<path fill-rule="evenodd" d="M10 1a9 9 0 100 18 9 9 0 000-18zm4.5 9a7.5 7.5 0 01-.14 1.425h-2.151a14.391 14.391 0 000-2.85h2.15A7.5 7.5 0 0114.5 10zM10 2.5c.914 0 1.885 1.17 2.32 3.575H7.68C8.115 3.67 9.086 2.5 10 2.5zM7.454 8.575a12.893 12.893 0 000 2.85H5.303a7.5 7.5 0 010-2.85h2.15zm.226 4.35h4.64C11.885 16.33 10.914 17.5 10 17.5s-1.885-1.17-2.32-3.575zM12.546 11.425H7.454a12.893 12.893 0 010-2.85h5.092a12.893 12.893 0 010 2.85zM4.5 10c0-.49.047-.97.14-1.425h2.151a14.391 14.391 0 000 2.85H4.64A7.5 7.5 0 014.5 10zm8.82 6.348a10.214 10.214 0 001.355-3.423h1.535a7.538 7.538 0 01-2.89 3.423zM16.21 7.075h-1.535a10.214 10.214 0 00-1.355-3.423 7.538 7.538 0 012.89 3.423zM6.68 3.652A10.214 10.214 0 005.325 7.075H3.79A7.538 7.538 0 016.68 3.652zM3.79 12.925h1.535a10.214 10.214 0 001.355 3.423 7.538 7.538 0 01-2.89-3.423z" clip-rule="evenodd" />'
				. '</svg>';
		}

		/**
		 * Globe link placed beside a URL input.
		 *
		 * @param string $raw         Current field value.
		 * @param string $input_id    Input element id (data-ktp-url-source).
		 * @param string $aria_label  Accessible label.
		 */
		public static function render_open_anchor( string $raw, string $input_id, string $aria_label = '' ): string {
			$aria_label = $aria_label !== '' ? $aria_label : __( 'URL', 'ktpwp' );
			$url        = self::normalize( $raw );
			$disabled   = $url === '';
			$classes    = 'ktp-url-open-link' . ( $disabled ? ' ktp-url-open-link--disabled' : '' );
			$href       = $disabled ? '#' : $url;

			$extra = $disabled ? ' aria-disabled="true" tabindex="-1"' : '';

			return sprintf(
				'<a href="%s" target="_blank" rel="noopener noreferrer" class="%s" data-ktp-url-source="%s" aria-label="%s" title="%s"%s>%s</a>',
				esc_url( $href ),
				esc_attr( $classes ),
				esc_attr( $input_id ),
				esc_attr( $aria_label ),
				esc_attr( $aria_label ),
				$extra,
				self::globe_svg()
			);
		}

		/**
		 * Form group: label + URL input + globe link.
		 *
		 * @param string $label_text Already translated label (without trailing colon).
		 */
		public static function render_url_form_group(
			string $label_text,
			string $field_id,
			array $field,
			string $value,
			string $pattern_attr,
			string $required_attr,
			string $placeholder_attr
		): string {
			$input = sprintf(
				'<input id="%s" type="%s" name="%s" value="%s"%s%s%s>',
				esc_attr( $field_id ),
				esc_attr( (string) ( $field['type'] ?? 'text' ) ),
				esc_attr( (string) ( $field['name'] ?? 'url' ) ),
				esc_attr( $value ),
				$pattern_attr,
				$required_attr,
				$placeholder_attr
			);

			$link = self::render_open_anchor( $value, $field_id, $label_text );

			$html = sprintf(
				'<div class="form-group form-group--url"><label for="%s">%s：</label><span class="ktp-url-field-wrap">%s%s</span></div>',
				esc_attr( $field_id ),
				esc_html( $label_text ),
				$input,
				$link
			);

			return $html . self::maybe_script();
		}

		/**
		 * Inline script: sync globe link with input value (once per page).
		 */
		public static function maybe_script(): string {
			if ( self::$script_printed ) {
				return '';
			}
			self::$script_printed = true;

			return '<script>
(function() {
	function normalizeUrl(raw) {
		raw = (raw || "").trim();
		if (!raw) { return ""; }
		var url = raw;
		if (!/^https?:\\/\\//i.test(url)) { url = "https://" + url; }
		try {
			var u = new URL(url);
			if (u.protocol === "http:" || u.protocol === "https:") { return u.href; }
		} catch (e) {}
		return "";
	}
	function syncLink(link) {
		if (!link) { return; }
		var id = link.getAttribute("data-ktp-url-source");
		var input = id ? document.getElementById(id) : null;
		var url = input ? normalizeUrl(input.value) : "";
		if (url) {
			link.href = url;
			link.classList.remove("ktp-url-open-link--disabled");
			link.removeAttribute("aria-disabled");
			link.removeAttribute("tabindex");
		} else {
			link.href = "#";
			link.classList.add("ktp-url-open-link--disabled");
			link.setAttribute("aria-disabled", "true");
			link.setAttribute("tabindex", "-1");
		}
	}
	function bindInput(input) {
		if (!input || input.getAttribute("data-ktp-url-bound")) { return; }
		input.setAttribute("data-ktp-url-bound", "1");
		var id = input.id;
		if (!id) { return; }
		document.querySelectorAll("[data-ktp-url-source=\\"" + id + "\\"]").forEach(function(link) {
			var handler = function() { syncLink(link); };
			input.addEventListener("input", handler);
			input.addEventListener("change", handler);
			syncLink(link);
		});
	}
	function init() {
		document.querySelectorAll("input[name=\\"url\\"]").forEach(bindInput);
	}
	if (document.readyState === "loading") {
		document.addEventListener("DOMContentLoaded", init);
	} else {
		init();
	}
	document.addEventListener("click", function(e) {
		var link = e.target.closest("[data-ktp-url-source].ktp-url-open-link--disabled");
		if (link) { e.preventDefault(); }
	});
})();
</script>';
		}
	}
}
