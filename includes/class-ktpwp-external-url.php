<?php
/**
 * External URL / email / phone helpers (normalize + action open links).
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
	 * URL / メール / 電話の正規化とフォーム横アクションリンク UI（KantanBiz 相当）。
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
		 * @param string $email Raw email address.
		 */
		public static function is_valid_email( string $email ): bool {
			return filter_var( trim( $email ), FILTER_VALIDATE_EMAIL ) !== false;
		}

		/**
		 * @param string $email Raw email address.
		 */
		public static function mailto_href( string $email ): string {
			$email = trim( $email );
			if ( ! self::is_valid_email( $email ) ) {
				return '';
			}

			return 'mailto:' . rawurlencode( $email );
		}

		/**
		 * @param string $phone Raw phone number.
		 */
		public static function tel_href( string $phone ): string {
			$raw = trim( $phone );
			if ( $raw === '' ) {
				return '';
			}

			$has_plus = isset( $raw[0] ) && $raw[0] === '+';
			$digits   = preg_replace( '/\D/u', '', $raw ) ?? '';
			if ( $digits === '' ) {
				return '';
			}

			return 'tel:' . ( $has_plus ? '+' : '' ) . $digits;
		}

		/**
		 * Heroicons globe outline (KantanBiz biz-url-field 相当).
		 */
		public static function globe_svg(): string {
			return '<svg xmlns="http://www.w3.org/2000/svg" class="ktp-url-open-link__icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">'
				. '<path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 013 12c0-1.605.42-3.113 1.157-4.418" />'
				. '</svg>';
		}

		/**
		 * Heroicons envelope outline.
		 */
		public static function email_svg(): string {
			return '<svg xmlns="http://www.w3.org/2000/svg" class="ktp-url-open-link__icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">'
				. '<path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />'
				. '</svg>';
		}

		/**
		 * Heroicons phone outline.
		 */
		public static function phone_svg(): string {
			return '<svg xmlns="http://www.w3.org/2000/svg" class="ktp-url-open-link__icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">'
				. '<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z" />'
				. '</svg>';
		}

		/**
		 * Action link placed beside an input.
		 *
		 * @param string $href        Resolved href (empty = disabled).
		 * @param string $input_id    Input element id (data-ktp-action-source).
		 * @param string $aria_label  Accessible label.
		 * @param string $svg         Icon markup.
		 * @param bool   $external    Open in new tab (URL only).
		 */
		private static function render_action_anchor( string $href, string $input_id, string $aria_label, string $svg, bool $external = false ): string {
			$disabled  = $href === '';
			$classes   = 'ktp-url-open-link' . ( $disabled ? ' ktp-url-open-link--disabled' : '' );
			$link_href = $disabled ? '#' : $href;
			$extra     = $disabled ? ' aria-disabled="true" tabindex="-1"' : '';
			$target    = ( ! $disabled && $external ) ? ' target="_blank" rel="noopener noreferrer"' : '';

			return sprintf(
				'<a href="%s" class="%s" data-ktp-action-source="%s" aria-label="%s" title="%s"%s%s>%s</a>',
				esc_url( $link_href ),
				esc_attr( $classes ),
				esc_attr( $input_id ),
				esc_attr( $aria_label ),
				esc_attr( $aria_label ),
				$target,
				$extra,
				$svg
			);
		}

		/**
		 * Globe link placed beside a URL input.
		 *
		 * @param string $raw         Current field value.
		 * @param string $input_id    Input element id.
		 * @param string $aria_label  Accessible label.
		 */
		public static function render_open_anchor( string $raw, string $input_id, string $aria_label = '' ): string {
			$aria_label = $aria_label !== '' ? $aria_label : __( 'URL', 'kantanpro' );

			return self::render_action_anchor( self::normalize( $raw ), $input_id, $aria_label, self::globe_svg(), true );
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
			return self::render_contact_form_group(
				'url',
				$label_text,
				$field_id,
				$field,
				$value,
				$pattern_attr,
				$required_attr,
				$placeholder_attr
			);
		}

		/**
		 * Form group: label + email input + mailto link.
		 *
		 * @param string $label_text Already translated label (without trailing colon).
		 */
		public static function render_email_form_group(
			string $label_text,
			string $field_id,
			array $field,
			string $value,
			string $pattern_attr,
			string $required_attr,
			string $placeholder_attr
		): string {
			return self::render_contact_form_group(
				'email',
				$label_text,
				$field_id,
				$field,
				$value,
				$pattern_attr,
				$required_attr,
				$placeholder_attr
			);
		}

		/**
		 * Form group: label + phone input + tel link.
		 *
		 * @param string $label_text Already translated label (without trailing colon).
		 */
		public static function render_phone_form_group(
			string $label_text,
			string $field_id,
			array $field,
			string $value,
			string $pattern_attr,
			string $required_attr,
			string $placeholder_attr
		): string {
			return self::render_contact_form_group(
				'phone',
				$label_text,
				$field_id,
				$field,
				$value,
				$pattern_attr,
				$required_attr,
				$placeholder_attr
			);
		}

		/**
		 * Render url / email / phone form group when supported; otherwise null.
		 */
		public static function maybe_render_form_group(
			string $field_name,
			string $label_text,
			string $field_id,
			array $field,
			string $value,
			string $pattern_attr,
			string $required_attr,
			string $placeholder_attr
		): ?string {
			switch ( $field_name ) {
				case 'url':
					return self::render_url_form_group( $label_text, $field_id, $field, $value, $pattern_attr, $required_attr, $placeholder_attr );
				case 'email':
					return self::render_email_form_group( $label_text, $field_id, $field, $value, $pattern_attr, $required_attr, $placeholder_attr );
				case 'phone':
					return self::render_phone_form_group( $label_text, $field_id, $field, $value, $pattern_attr, $required_attr, $placeholder_attr );
				default:
					return null;
			}
		}

		/**
		 * @param 'url'|'email'|'phone' $kind Field kind.
		 */
		private static function render_contact_form_group(
			string $kind,
			string $label_text,
			string $field_id,
			array $field,
			string $value,
			string $pattern_attr,
			string $required_attr,
			string $placeholder_attr
		): string {
			$input_type  = (string) ( $field['type'] ?? 'text' );
			$input_name  = (string) ( $field['name'] ?? $kind );
			$extra_attrs = '';

			if ( $kind === 'url' ) {
				$input_marker = ' data-ktp-url-input';
				$href         = self::normalize( $value );
				$svg          = self::globe_svg();
				$external     = true;
			} elseif ( $kind === 'email' ) {
				$input_marker = ' data-ktp-email-input';
				$href         = self::mailto_href( $value );
				$svg          = self::email_svg();
				$external     = false;
			} else {
				$input_marker = ' data-ktp-phone-input';
				$href         = self::tel_href( $value );
				$svg          = self::phone_svg();
				$external     = false;
				$extra_attrs  = ' inputmode="tel" autocomplete="tel"';
			}

			$input = sprintf(
				'<input id="%s" type="%s" name="%s" value="%s"%s%s%s%s>',
				esc_attr( $field_id ),
				esc_attr( $input_type ),
				esc_attr( $input_name ),
				esc_attr( $value ),
				$pattern_attr,
				$required_attr,
				$placeholder_attr,
				$input_marker . $extra_attrs
			);

			$link = self::render_action_anchor( $href, $field_id, $label_text, $svg, $external );

			$html = sprintf(
				'<div class="form-group form-group--%s"><label for="%s">%s：</label><span class="ktp-url-field-wrap">%s%s</span></div>',
				esc_attr( $kind ),
				esc_attr( $field_id ),
				esc_html( $label_text ),
				$input,
				$link
			);

			return $html . self::maybe_script();
		}

		/**
		 * Inline script: sync action links with input values (once per page).
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
	function normalizeEmail(raw) {
		raw = (raw || "").trim();
		if (!raw) { return ""; }
		if (/^[^\\s@]+@[^\\s@]+\\.[^\\s@]+$/.test(raw)) {
			return "mailto:" + encodeURIComponent(raw);
		}
		return "";
	}
	function normalizePhone(raw) {
		raw = (raw || "").trim();
		if (!raw) { return ""; }
		var hasPlus = raw.charAt(0) === "+";
		var digits = raw.replace(/\\D/g, "");
		if (!digits) { return ""; }
		return "tel:" + (hasPlus ? "+" : "") + digits;
	}
	function resolveHref(input) {
		if (!input) { return ""; }
		if (input.hasAttribute("data-ktp-url-input")) { return normalizeUrl(input.value); }
		if (input.hasAttribute("data-ktp-email-input")) { return normalizeEmail(input.value); }
		if (input.hasAttribute("data-ktp-phone-input")) { return normalizePhone(input.value); }
		return "";
	}
	function syncLink(link) {
		if (!link) { return; }
		var id = link.getAttribute("data-ktp-action-source") || link.getAttribute("data-ktp-url-source");
		var input = id ? document.getElementById(id) : null;
		var href = resolveHref(input);
		if (href) {
			link.href = href;
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
		if (!input || input.getAttribute("data-ktp-action-bound")) { return; }
		input.setAttribute("data-ktp-action-bound", "1");
		var id = input.id;
		if (!id) { return; }
		document.querySelectorAll("[data-ktp-action-source=\\"" + id + "\\"],[data-ktp-url-source=\\"" + id + "\\"]").forEach(function(link) {
			var handler = function() { syncLink(link); };
			input.addEventListener("input", handler);
			input.addEventListener("change", handler);
			syncLink(link);
		});
	}
	function init() {
		document.querySelectorAll("[data-ktp-url-input],[data-ktp-email-input],[data-ktp-phone-input]").forEach(bindInput);
	}
	if (document.readyState === "loading") {
		document.addEventListener("DOMContentLoaded", init);
	} else {
		init();
	}
	document.addEventListener("click", function(e) {
		var link = e.target.closest("[data-ktp-action-source].ktp-url-open-link--disabled,[data-ktp-url-source].ktp-url-open-link--disabled");
		if (link) { e.preventDefault(); }
	});
})();
</script>';
		}
	}
}
