<?php
/**
 * 帳票種別ごとのレイアウト・表示項目（wp_options JSON と既定値のマージ）
 *
 * @package KTPWP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class KTPWP_Pdf_Document_Settings {

	public const LAYOUT_DEFAULT = 'default';

	public const LAYOUT_COMPACT = 'compact';

	/** @var list<string> */
	public const LAYOUTS = array(
		self::LAYOUT_DEFAULT,
		self::LAYOUT_COMPACT,
	);

	public const LOGO_POSITION_HEADER = 'header';

	public const LOGO_POSITION_FOOTER = 'footer';

	/** @var list<string> */
	public const LOGO_POSITIONS = array(
		self::LOGO_POSITION_HEADER,
		self::LOGO_POSITION_FOOTER,
	);

	private const DEFAULT_ACCENT = '#666666';

	public const LOGO_MAX_HEIGHT_MIN = 12;

	public const LOGO_MAX_HEIGHT_MAX = 80;

	public const LOGO_MAX_WIDTH_MIN = 40;

	public const LOGO_MAX_WIDTH_MAX = 320;

	public const SEAL_MAX_SIZE_MIN = 16;

	public const SEAL_MAX_SIZE_MAX = 120;

	public const COMPACT_BRANDING_SCALE = 0.75;

	public const ISSUER_LINE_HEIGHT_MIN = 1.0;

	public const ISSUER_LINE_HEIGHT_MAX = 2.5;

	public const DEFAULT_ISSUER_LINE_HEIGHT = 1.45;

	public const INVOICE_ROW_PADDING_Y_MIN = 2;

	public const INVOICE_ROW_PADDING_Y_MAX = 20;

	public const DEFAULT_INVOICE_ROW_PADDING_Y = 6;

	public const OPTION_DOCUMENT_SETTINGS = 'ktp_pdf_document_settings';

	public const OPTION_PDF_LAYOUT = 'ktp_pdf_layout';

	/** @var array<string, array<string, mixed>> */
	private static $kind_defaults = array(
		KTPWP_Pdf_Document_Kind::ESTIMATE => array(
			'title' => null,
			'lead' => null,
			'show_bank_transfer' => true,
			'show_tax_column' => null,
			'show_remarks_column' => true,
			'show_memo' => true,
			'show_output_at' => false,
			'show_qualified_invoice_number' => false,
			'show_logo' => true,
			'show_seal' => false,
			'logo_position' => self::LOGO_POSITION_FOOTER,
			'logo_max_height_px' => 32,
			'logo_max_width_px' => 140,
			'seal_max_height_px' => 48,
			'seal_max_width_px' => 48,
			'logo_keep_aspect_ratio' => true,
			'seal_keep_aspect_ratio' => true,
			'accent_color' => self::DEFAULT_ACCENT,
			'body_font_size' => 11,
			'title_font_size' => 24,
			'issuer_line_height' => self::DEFAULT_ISSUER_LINE_HEIGHT,
			'invoice_row_padding_y_px' => self::DEFAULT_INVOICE_ROW_PADDING_Y,
			'margin_top_mm' => 0,
			'margin_right_mm' => 0,
			'margin_bottom_mm' => 0,
			'margin_left_mm' => 0,
			'envelope_top_mm' => 0,
			'envelope_left_mm' => 0,
		),
		KTPWP_Pdf_Document_Kind::ORDER => array(
			'title' => null,
			'lead' => null,
			'show_bank_transfer' => false,
			'show_tax_column' => null,
			'show_remarks_column' => true,
			'show_memo' => true,
			'show_output_at' => true,
			'show_qualified_invoice_number' => false,
			'show_logo' => true,
			'show_seal' => false,
			'logo_position' => self::LOGO_POSITION_FOOTER,
			'logo_max_height_px' => 32,
			'logo_max_width_px' => 140,
			'seal_max_height_px' => 48,
			'seal_max_width_px' => 48,
			'logo_keep_aspect_ratio' => true,
			'seal_keep_aspect_ratio' => true,
			'accent_color' => self::DEFAULT_ACCENT,
			'body_font_size' => 11,
			'title_font_size' => 24,
			'issuer_line_height' => self::DEFAULT_ISSUER_LINE_HEIGHT,
			'invoice_row_padding_y_px' => self::DEFAULT_INVOICE_ROW_PADDING_Y,
			'margin_top_mm' => 0,
			'margin_right_mm' => 0,
			'margin_bottom_mm' => 0,
			'margin_left_mm' => 0,
			'envelope_top_mm' => 0,
			'envelope_left_mm' => 0,
		),
		KTPWP_Pdf_Document_Kind::BULK_INVOICE => array(
			'title' => '請求書',
			'lead' => '平素より大変お世話になっております。下記の通りご請求申し上げます。',
			'show_bank_transfer' => true,
			'show_tax_column' => null,
			'show_tax_amount_column' => false,
			'show_remarks_column' => true,
			'show_memo' => false,
			'show_output_at' => false,
			'show_qualified_invoice_number' => true,
			'show_logo' => true,
			'show_seal' => false,
			'logo_position' => self::LOGO_POSITION_FOOTER,
			'logo_max_height_px' => 32,
			'logo_max_width_px' => 180,
			'seal_max_height_px' => 48,
			'seal_max_width_px' => 48,
			'logo_keep_aspect_ratio' => true,
			'seal_keep_aspect_ratio' => true,
			'accent_color' => self::DEFAULT_ACCENT,
			'body_font_size' => 14,
			'title_font_size' => 24,
			'issuer_line_height' => self::DEFAULT_ISSUER_LINE_HEIGHT,
			'invoice_row_padding_y_px' => self::DEFAULT_INVOICE_ROW_PADDING_Y,
			'margin_top_mm' => 57,
			'margin_right_mm' => 5,
			'margin_bottom_mm' => 5,
			'margin_left_mm' => 10,
			'envelope_top_mm' => 6,
			'envelope_left_mm' => 23,
		),
	);

	/**
	 * @return array<string, mixed>
	 */
	public static function resolve( $kind ) {
		if ( ! in_array( $kind, KTPWP_Pdf_Document_Kind::all(), true ) ) {
			$kind = KTPWP_Pdf_Document_Kind::ORDER;
		}

		$stored    = self::stored_slice( $kind );
		$defaults  = self::$kind_defaults[ $kind ];
		$layout    = self::resolve_layout( $stored );

		if ( array_key_exists( 'show_tax_column', $stored ) ) {
			$show_tax_column = filter_var( $stored['show_tax_column'], FILTER_VALIDATE_BOOLEAN );
		} elseif ( ( $defaults['show_tax_column'] ?? null ) !== null ) {
			$show_tax_column = (bool) $defaults['show_tax_column'];
		} else {
			$show_tax_column = ! ( class_exists( 'KTPWP_Tax_Policy' ) && KTPWP_Tax_Policy::hide_tax_columns() );
		}

		$resolved = array(
			'layout' => $layout,
			'title' => self::nullable_trimmed_string( $stored['title'] ?? $defaults['title'] ?? null ),
			'lead' => self::nullable_trimmed_string( $stored['lead'] ?? $defaults['lead'] ?? null ),
			'show_bank_transfer' => self::bool_value( $stored, 'show_bank_transfer', (bool) $defaults['show_bank_transfer'] ),
			'show_tax_column' => $show_tax_column,
			'show_remarks_column' => self::bool_value( $stored, 'show_remarks_column', (bool) $defaults['show_remarks_column'] ),
			'show_memo' => self::bool_value( $stored, 'show_memo', (bool) $defaults['show_memo'] ),
			'show_output_at' => self::bool_value( $stored, 'show_output_at', (bool) $defaults['show_output_at'] ),
			'show_qualified_invoice_number' => self::bool_value( $stored, 'show_qualified_invoice_number', (bool) $defaults['show_qualified_invoice_number'] ),
			'show_logo' => self::bool_value( $stored, 'show_logo', (bool) $defaults['show_logo'] ),
			'show_seal' => self::bool_value( $stored, 'show_seal', (bool) $defaults['show_seal'] ),
			'logo_position' => self::resolve_logo_position( $stored, (string) $defaults['logo_position'] ),
			'logo_max_height_px' => self::clamp_int( $stored['logo_max_height_px'] ?? $defaults['logo_max_height_px'], self::LOGO_MAX_HEIGHT_MIN, self::LOGO_MAX_HEIGHT_MAX ),
			'logo_max_width_px' => self::clamp_int( $stored['logo_max_width_px'] ?? $defaults['logo_max_width_px'], self::LOGO_MAX_WIDTH_MIN, self::LOGO_MAX_WIDTH_MAX ),
			'seal_max_height_px' => self::clamp_int( $stored['seal_max_height_px'] ?? $defaults['seal_max_height_px'], self::SEAL_MAX_SIZE_MIN, self::SEAL_MAX_SIZE_MAX ),
			'seal_max_width_px' => self::clamp_int( $stored['seal_max_width_px'] ?? $defaults['seal_max_width_px'], self::SEAL_MAX_SIZE_MIN, self::SEAL_MAX_SIZE_MAX ),
			'logo_keep_aspect_ratio' => self::bool_value( $stored, 'logo_keep_aspect_ratio', (bool) $defaults['logo_keep_aspect_ratio'] ),
			'seal_keep_aspect_ratio' => self::bool_value( $stored, 'seal_keep_aspect_ratio', (bool) $defaults['seal_keep_aspect_ratio'] ),
			'accent_color' => self::normalize_color( isset( $stored['accent_color'] ) ? (string) $stored['accent_color'] : (string) $defaults['accent_color'] ),
			'body_font_size' => self::clamp_int( $stored['body_font_size'] ?? $defaults['body_font_size'], 8, 16 ),
			'title_font_size' => self::clamp_int( $stored['title_font_size'] ?? $defaults['title_font_size'], 14, 32 ),
			'issuer_line_height' => self::clamp_line_height( $stored['issuer_line_height'] ?? $defaults['issuer_line_height'], (float) $defaults['issuer_line_height'] ),
			'invoice_row_padding_y_px' => self::clamp_int( $stored['invoice_row_padding_y_px'] ?? $defaults['invoice_row_padding_y_px'], self::INVOICE_ROW_PADDING_Y_MIN, self::INVOICE_ROW_PADDING_Y_MAX ),
			'margin_top_mm' => self::clamp_int( $stored['margin_top_mm'] ?? $defaults['margin_top_mm'], 0, 80 ),
			'margin_right_mm' => self::clamp_int( $stored['margin_right_mm'] ?? $defaults['margin_right_mm'], 0, 40 ),
			'margin_bottom_mm' => self::clamp_int( $stored['margin_bottom_mm'] ?? $defaults['margin_bottom_mm'], 0, 40 ),
			'margin_left_mm' => self::clamp_int( $stored['margin_left_mm'] ?? $defaults['margin_left_mm'], 0, 40 ),
			'envelope_top_mm' => self::clamp_int( $stored['envelope_top_mm'] ?? $defaults['envelope_top_mm'], 0, 40 ),
			'envelope_left_mm' => self::clamp_int( $stored['envelope_left_mm'] ?? $defaults['envelope_left_mm'], 0, 40 ),
		);

		if ( $kind === KTPWP_Pdf_Document_Kind::BULK_INVOICE ) {
			$resolved['show_tax_amount_column'] = self::bool_value(
				$stored,
				'show_tax_amount_column',
				(bool) ( $defaults['show_tax_amount_column'] ?? false )
			);
		}

		return $resolved;
	}

	public static function resolve_title( $kind, $fallback ) {
		$title = self::resolve( $kind )['title'];

		return ( $title !== null && $title !== '' ) ? $title : $fallback;
	}

	public static function resolve_lead( $kind, $fallback ) {
		$lead = self::resolve( $kind )['lead'];

		return ( $lead !== null && $lead !== '' ) ? $lead : $fallback;
	}

	/**
	 * @param array<string, mixed> $resolved
	 */
	public static function scaled_invoice_row_padding_y( array $resolved ) {
		$base = self::clamp_int(
			$resolved['invoice_row_padding_y_px'] ?? self::DEFAULT_INVOICE_ROW_PADDING_Y,
			self::INVOICE_ROW_PADDING_Y_MIN,
			self::INVOICE_ROW_PADDING_Y_MAX
		);
		if ( ( $resolved['layout'] ?? '' ) === self::LAYOUT_COMPACT ) {
			return max( self::INVOICE_ROW_PADDING_Y_MIN, (int) round( $base * self::COMPACT_BRANDING_SCALE ) );
		}

		return $base;
	}

	/**
	 * @param array<string, mixed> $resolved
	 * @return array<string, mixed>
	 */
	public static function scaled_branding_sizes( array $resolved ) {
		$compact = ( $resolved['layout'] ?? '' ) === self::LAYOUT_COMPACT;
		$scale   = $compact ? self::COMPACT_BRANDING_SCALE : 1.0;

		return array(
			'logo_max_height_px' => self::scale_branding_px( (int) $resolved['logo_max_height_px'], $scale, self::LOGO_MAX_HEIGHT_MIN, self::LOGO_MAX_HEIGHT_MAX ),
			'logo_max_width_px' => self::scale_branding_px( (int) $resolved['logo_max_width_px'], $scale, self::LOGO_MAX_WIDTH_MIN, self::LOGO_MAX_WIDTH_MAX ),
			'seal_max_height_px' => self::scale_branding_px( (int) $resolved['seal_max_height_px'], $scale, self::SEAL_MAX_SIZE_MIN, self::SEAL_MAX_SIZE_MAX ),
			'seal_max_width_px' => self::scale_branding_px( (int) $resolved['seal_max_width_px'], $scale, self::SEAL_MAX_SIZE_MIN, self::SEAL_MAX_SIZE_MAX ),
			'logo_keep_aspect_ratio' => (bool) ( $resolved['logo_keep_aspect_ratio'] ?? true ),
			'seal_keep_aspect_ratio' => (bool) ( $resolved['seal_keep_aspect_ratio'] ?? true ),
		);
	}

	/**
	 * @param array<string, mixed> $branding
	 */
	public static function logo_css_declaration( array $branding, $bulk_height_mode = false ) {
		return implode( ';', self::logo_css_rules( $branding, $bulk_height_mode ) ) . ';';
	}

	/**
	 * @param array<string, mixed> $branding
	 */
	public static function seal_css_declaration( array $branding ) {
		return implode( ';', self::seal_css_rules( $branding ) ) . ';';
	}

	/**
	 * @param array<string, mixed> $branding
	 * @return list<string>
	 */
	public static function logo_css_rules( array $branding, $bulk_height_mode = false ) {
		$height = (int) $branding['logo_max_height_px'];
		$width  = (int) $branding['logo_max_width_px'];

		if ( ! empty( $branding['logo_keep_aspect_ratio'] ) ) {
			if ( $bulk_height_mode ) {
				return array(
					'display:block',
					"height:{$height}px",
					'width:auto',
					"max-width:{$width}px",
					'object-fit:contain',
				);
			}

			return array(
				'display:block',
				"max-height:{$height}px",
				"max-width:{$width}px",
				'width:auto',
				'height:auto',
				'object-fit:contain',
			);
		}

		return array(
			'display:block',
			"width:{$width}px",
			"height:{$height}px",
			'object-fit:fill',
		);
	}

	/**
	 * @param array<string, mixed> $branding
	 * @return list<string>
	 */
	public static function seal_css_rules( array $branding ) {
		$height = (int) $branding['seal_max_height_px'];
		$width  = (int) $branding['seal_max_width_px'];

		if ( ! empty( $branding['seal_keep_aspect_ratio'] ) ) {
			return array(
				'display:block',
				"max-height:{$height}px",
				"max-width:{$width}px",
				'width:auto',
				'height:auto',
				'object-fit:contain',
				'flex-shrink:0',
			);
		}

		return array(
			'display:block',
			"width:{$width}px",
			"height:{$height}px",
			'object-fit:fill',
			'flex-shrink:0',
		);
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	public static function raw_for_form() {
		$out = array();
		foreach ( KTPWP_Pdf_Document_Kind::all() as $kind ) {
			$out[ $kind ] = self::resolve( $kind );
		}

		return $out;
	}

	/** @var list<string> */
	private static $boolean_field_keys = array(
		'show_bank_transfer',
		'show_tax_column',
		'show_tax_amount_column',
		'show_remarks_column',
		'show_memo',
		'show_output_at',
		'show_qualified_invoice_number',
		'show_logo',
		'show_seal',
		'logo_keep_aspect_ratio',
		'seal_keep_aspect_ratio',
	);

	/**
	 * @param array<string, mixed>|null $input
	 * @return array<string, mixed>|null
	 */
	public static function coerce_incoming_form_values( $input ) {
		if ( $input === null ) {
			return null;
		}

		foreach ( KTPWP_Pdf_Document_Kind::all() as $kind ) {
			if ( ! is_array( $input[ $kind ] ?? null ) ) {
				continue;
			}
			foreach ( self::$boolean_field_keys as $key ) {
				if ( ! array_key_exists( $key, $input[ $kind ] ) ) {
					continue;
				}
				$input[ $kind ][ $key ] = self::coerce_to_bool( $input[ $kind ][ $key ] );
			}
		}

		return $input;
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array<string, array<string, mixed>>
	 */
	public static function normalize_from_request( array $input ) {
		$input = self::coerce_incoming_form_values( $input ) ?? array();

		$normalized = array();
		foreach ( KTPWP_Pdf_Document_Kind::all() as $kind ) {
			$slice                  = is_array( $input[ $kind ] ?? null ) ? $input[ $kind ] : array();
			$normalized[ $kind ]    = self::normalize_slice( $kind, $slice );
		}

		return $normalized;
	}

	public static function effective_pdf_layout() {
		$layout = (string) get_option( self::OPTION_PDF_LAYOUT, self::LAYOUT_DEFAULT );

		return in_array( $layout, self::LAYOUTS, true ) ? $layout : self::LAYOUT_DEFAULT;
	}

	/**
	 * @param array<string, mixed> $slice
	 * @return array<string, mixed>
	 */
	private static function normalize_slice( $kind, array $slice ) {
		$defaults = self::$kind_defaults[ $kind ];
		$layout   = isset( $slice['layout'] ) ? (string) $slice['layout'] : null;
		if ( $layout !== null && ! in_array( $layout, self::LAYOUTS, true ) ) {
			$layout = null;
		}

		$normalized = array(
			'layout' => $layout,
			'title' => self::nullable_trimmed_string( $slice['title'] ?? null ),
			'lead' => self::nullable_trimmed_string( $slice['lead'] ?? null ),
			'show_bank_transfer' => self::request_bool( $slice, 'show_bank_transfer', (bool) $defaults['show_bank_transfer'] ),
			'show_tax_column' => self::request_bool(
				$slice,
				'show_tax_column',
				( $defaults['show_tax_column'] ?? null ) !== null ? (bool) $defaults['show_tax_column'] : true
			),
			'show_remarks_column' => self::request_bool( $slice, 'show_remarks_column', (bool) $defaults['show_remarks_column'] ),
			'show_memo' => self::request_bool( $slice, 'show_memo', (bool) $defaults['show_memo'] ),
			'show_output_at' => self::request_bool( $slice, 'show_output_at', (bool) $defaults['show_output_at'] ),
			'show_qualified_invoice_number' => self::request_bool( $slice, 'show_qualified_invoice_number', (bool) $defaults['show_qualified_invoice_number'] ),
			'show_logo' => self::request_bool( $slice, 'show_logo', (bool) $defaults['show_logo'] ),
			'show_seal' => self::request_bool( $slice, 'show_seal', (bool) $defaults['show_seal'] ),
			'logo_position' => self::resolve_logo_position( $slice, (string) $defaults['logo_position'] ),
			'logo_max_height_px' => self::clamp_int( $slice['logo_max_height_px'] ?? $defaults['logo_max_height_px'], self::LOGO_MAX_HEIGHT_MIN, self::LOGO_MAX_HEIGHT_MAX ),
			'logo_max_width_px' => self::clamp_int( $slice['logo_max_width_px'] ?? $defaults['logo_max_width_px'], self::LOGO_MAX_WIDTH_MIN, self::LOGO_MAX_WIDTH_MAX ),
			'seal_max_height_px' => self::clamp_int( $slice['seal_max_height_px'] ?? $defaults['seal_max_height_px'], self::SEAL_MAX_SIZE_MIN, self::SEAL_MAX_SIZE_MAX ),
			'seal_max_width_px' => self::clamp_int( $slice['seal_max_width_px'] ?? $defaults['seal_max_width_px'], self::SEAL_MAX_SIZE_MIN, self::SEAL_MAX_SIZE_MAX ),
			'logo_keep_aspect_ratio' => self::request_bool( $slice, 'logo_keep_aspect_ratio', (bool) $defaults['logo_keep_aspect_ratio'] ),
			'seal_keep_aspect_ratio' => self::request_bool( $slice, 'seal_keep_aspect_ratio', (bool) $defaults['seal_keep_aspect_ratio'] ),
			'accent_color' => self::normalize_color( (string) ( $slice['accent_color'] ?? $defaults['accent_color'] ) ),
			'body_font_size' => self::clamp_int( $slice['body_font_size'] ?? $defaults['body_font_size'], 8, 16 ),
			'title_font_size' => self::clamp_int( $slice['title_font_size'] ?? $defaults['title_font_size'], 14, 32 ),
			'issuer_line_height' => self::clamp_line_height( $slice['issuer_line_height'] ?? $defaults['issuer_line_height'], (float) $defaults['issuer_line_height'] ),
			'invoice_row_padding_y_px' => self::clamp_int( $slice['invoice_row_padding_y_px'] ?? $defaults['invoice_row_padding_y_px'], self::INVOICE_ROW_PADDING_Y_MIN, self::INVOICE_ROW_PADDING_Y_MAX ),
			'margin_top_mm' => self::clamp_int( $slice['margin_top_mm'] ?? $defaults['margin_top_mm'], 0, 80 ),
			'margin_right_mm' => self::clamp_int( $slice['margin_right_mm'] ?? $defaults['margin_right_mm'], 0, 40 ),
			'margin_bottom_mm' => self::clamp_int( $slice['margin_bottom_mm'] ?? $defaults['margin_bottom_mm'], 0, 40 ),
			'margin_left_mm' => self::clamp_int( $slice['margin_left_mm'] ?? $defaults['margin_left_mm'], 0, 40 ),
			'envelope_top_mm' => self::clamp_int( $slice['envelope_top_mm'] ?? $defaults['envelope_top_mm'], 0, 40 ),
			'envelope_left_mm' => self::clamp_int( $slice['envelope_left_mm'] ?? $defaults['envelope_left_mm'], 0, 40 ),
		);

		if ( array_key_exists( 'show_tax_amount_column', $defaults ) ) {
			$normalized['show_tax_amount_column'] = self::request_bool(
				$slice,
				'show_tax_amount_column',
				(bool) $defaults['show_tax_amount_column']
			);
		}

		return $normalized;
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function stored_slice( $kind ) {
		$all = get_option( self::OPTION_DOCUMENT_SETTINGS, array() );
		if ( ! is_array( $all ) ) {
			return array();
		}
		$slice = $all[ $kind ] ?? null;

		return is_array( $slice ) ? $slice : array();
	}

	/**
	 * @param array<string, mixed> $stored
	 */
	private static function resolve_layout( array $stored ) {
		$layout = isset( $stored['layout'] ) ? (string) $stored['layout'] : '';
		if ( in_array( $layout, self::LAYOUTS, true ) ) {
			return $layout;
		}

		return self::effective_pdf_layout();
	}

	/**
	 * @param array<string, mixed> $stored
	 */
	private static function bool_value( array $stored, $key, $default ) {
		if ( ! array_key_exists( $key, $stored ) || $stored[ $key ] === null ) {
			return $default;
		}

		return self::coerce_to_bool( $stored[ $key ] );
	}

	/**
	 * @param array<string, mixed> $slice
	 */
	private static function request_bool( array $slice, $key, $default ) {
		if ( ! array_key_exists( $key, $slice ) ) {
			return $default;
		}

		return self::coerce_to_bool( $slice[ $key ] );
	}

	private static function coerce_to_bool( $value ) {
		if ( is_array( $value ) ) {
			foreach ( $value as $item ) {
				if ( filter_var( $item, FILTER_VALIDATE_BOOLEAN ) ) {
					return true;
				}
			}

			return false;
		}

		return filter_var( $value, FILTER_VALIDATE_BOOLEAN );
	}

	/**
	 * @param array<string, mixed> $stored
	 */
	private static function resolve_logo_position( array $stored, $default ) {
		$pos = isset( $stored['logo_position'] ) ? (string) $stored['logo_position'] : $default;

		return in_array( $pos, self::LOGO_POSITIONS, true ) ? $pos : self::LOGO_POSITION_FOOTER;
	}

	private static function nullable_trimmed_string( $value ) {
		if ( $value === null ) {
			return null;
		}
		$s = trim( (string) $value );

		return $s !== '' ? $s : null;
	}

	private static function normalize_color( $color ) {
		$color = trim( $color );
		if ( preg_match( '/^#[0-9A-Fa-f]{6}$/', $color ) === 1 ) {
			return strtolower( $color );
		}

		return self::DEFAULT_ACCENT;
	}

	private static function clamp_int( $value, $min, $max ) {
		$n = is_numeric( $value ) ? (int) $value : $min;

		return max( $min, min( $max, $n ) );
	}

	private static function clamp_line_height( $value, $default ) {
		$n = is_numeric( $value ) ? (float) $value : $default;

		return round( max( self::ISSUER_LINE_HEIGHT_MIN, min( self::ISSUER_LINE_HEIGHT_MAX, $n ) ), 2 );
	}

	private static function scale_branding_px( $base, $scale, $min, $max ) {
		return self::clamp_int( (int) round( $base * $scale ), $min, $max );
	}
}
