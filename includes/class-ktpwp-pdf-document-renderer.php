<?php
/**
 * 帳票 HTML 用 CSS・ブランディング行
 *
 * @package KTPWP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class KTPWP_Pdf_Document_Renderer {

	/**
	 * @param array<string, mixed> $doc_settings
	 */
	public static function document_styles_css( array $doc_settings ) {
		$accent   = esc_attr( $doc_settings['accent_color'] ?? '#666666' );
		$body     = (int) ( $doc_settings['body_font_size'] ?? 11 );
		$title    = (int) ( $doc_settings['title_font_size'] ?? 24 );
		$issuer_lh = (float) ( $doc_settings['issuer_line_height'] ?? KTPWP_Pdf_Document_Settings::DEFAULT_ISSUER_LINE_HEIGHT );
		$row_pad  = KTPWP_Pdf_Document_Settings::scaled_invoice_row_padding_y( $doc_settings );
		$branding = KTPWP_Pdf_Document_Settings::scaled_branding_sizes( $doc_settings );
		$logo_css = esc_attr( KTPWP_Pdf_Document_Settings::logo_css_declaration( $branding ) );
		$seal_css = esc_attr( KTPWP_Pdf_Document_Settings::seal_css_declaration( $branding ) );
		$compact  = ( $doc_settings['layout'] ?? '' ) === KTPWP_Pdf_Document_Settings::LAYOUT_COMPACT;
		$title_compact = max( 14, (int) round( $title * 0.75 ) );

		$css  = 'body.ktp-pdf-doc { font-size: ' . $body . 'px; }';
		$css .= '.ktp-pdf-doc .ktp-doc-title-box { border-color: ' . $accent . '; font-size: ' . $title . 'px; }';
		$css .= '.ktp-pdf-doc .ktp-invoice-items-header { border-color: ' . $accent . '; }';
		$css .= '.ktp-pdf-doc .ktp-issuer-info { line-height: ' . $issuer_lh . '; }';
		$css .= '.ktp-pdf-doc .ktp-invoice-item-row, .ktp-pdf-doc .ktp-invoice-items-header { padding-top: ' . (int) $row_pad . 'px; padding-bottom: ' . (int) $row_pad . 'px; }';
		$css .= '.ktp-pdf-doc .ktp-branding-logo { ' . $logo_css . ' }';
		$css .= '.ktp-pdf-doc .ktp-branding-seal { ' . $seal_css . ' }';
		if ( $compact ) {
			$css .= 'body.ktp-pdf-doc.ktp-pdf-compact { font-size: ' . max( 8, (int) round( $body * 0.82 ) ) . 'px; }';
			$css .= 'body.ktp-pdf-doc.ktp-pdf-compact .ktp-doc-title-box { font-size: ' . $title_compact . 'px; }';
		}

		return $css;
	}

	/**
	 * @param array<string, mixed> $branding
	 * @param array<string, mixed> $doc_settings
	 * @param string               $placement header|footer
	 */
	public static function branding_row_html( array $branding, array $doc_settings, $placement ) {
		$want_header = ( $doc_settings['logo_position'] ?? '' ) === KTPWP_Pdf_Document_Settings::LOGO_POSITION_HEADER;
		if ( $placement === 'header' && ! $want_header ) {
			return '';
		}
		if ( $placement === 'footer' && $want_header ) {
			return '';
		}

		$show_logo = ! empty( $doc_settings['show_logo'] ) && ! empty( $branding['logo_data_uri'] );
		$show_seal = ! empty( $doc_settings['show_seal'] ) && ! empty( $branding['seal_data_uri'] );
		if ( ! $show_logo && ! $show_seal ) {
			return '';
		}

		$justify = ( $placement === 'header' ) ? 'center' : 'flex-start';
		$html    = '<div class="ktp-branding-row" style="display:flex;flex-wrap:wrap;align-items:center;gap:6px;justify-content:' . esc_attr( $justify ) . ';margin:4px 0;">';
		if ( $show_logo ) {
			$html .= '<img class="ktp-branding-logo" src="' . esc_attr( $branding['logo_data_uri'] ) . '" alt="">';
		}
		if ( $show_seal ) {
			$html .= '<img class="ktp-branding-seal" src="' . esc_attr( $branding['seal_data_uri'] ) . '" alt="">';
		}
		$html .= '</div>';

		return $html;
	}

	/**
	 * @param array<string, mixed> $branding
	 * @param array<string, mixed> $doc_settings
	 */
	public static function issuer_block_html( array $branding, array $doc_settings ) {
		$lh = (float) ( $doc_settings['issuer_line_height'] ?? KTPWP_Pdf_Document_Settings::DEFAULT_ISSUER_LINE_HEIGHT );
		$html = '<div class="ktp-issuer-info" style="line-height:' . esc_attr( (string) $lh ) . ';">';
		$html .= '<div style="font-weight:bold;">' . esc_html( $branding['name'] ) . '</div>';
		if ( ! empty( $branding['address_html'] ) ) {
			$html .= '<div>' . $branding['address_html'] . '</div>';
		}
		$html .= '</div>';

		return $html;
	}

	/**
	 * 一括請求プレビュー／印刷用の自社情報ブロック（ロゴ・印影＋発行者名・住所）
	 *
	 * @param array<string, mixed> $branding
	 * @param array<string, mixed> $doc_settings
	 * @param string               $legacy_company_info_html 従来の company-info-box HTML（フォールバック）
	 */
	public static function bulk_invoice_company_section_html( array $branding, array $doc_settings, $legacy_company_info_html = '' ) {
		$sizes       = KTPWP_Pdf_Document_Settings::scaled_branding_sizes( $doc_settings );
		$logo_style  = KTPWP_Pdf_Document_Settings::logo_css_declaration( $sizes, true );
		$seal_style  = KTPWP_Pdf_Document_Settings::seal_css_declaration( $sizes );
		$line_height = (float) ( $doc_settings['issuer_line_height'] ?? KTPWP_Pdf_Document_Settings::DEFAULT_ISSUER_LINE_HEIGHT );

		$show_logo = ! empty( $doc_settings['show_logo'] ) && ! empty( $branding['logo_data_uri'] );
		$show_seal = ! empty( $doc_settings['show_seal'] ) && ! empty( $branding['seal_data_uri'] );

		$html = '<div class="ktp-bulk-company-info" style="font-size:14px;color:#374151;line-height:' . esc_attr( (string) $line_height ) . ';">';

		if ( $show_logo || $show_seal ) {
			$html .= '<div class="ktp-bulk-branding-row" style="display:flex;flex-wrap:wrap;align-items:center;gap:8px;margin-bottom:8px;">';
			if ( $show_logo ) {
				$html .= '<img src="' . esc_attr( $branding['logo_data_uri'] ) . '" alt="" style="' . esc_attr( $logo_style ) . '">';
			}
			if ( $show_seal ) {
				$html .= '<img src="' . esc_attr( $branding['seal_data_uri'] ) . '" alt="" style="' . esc_attr( $seal_style ) . '">';
			}
			$html .= '</div>';
		}

		$issuer_name = trim( (string) ( $branding['name'] ?? '' ) );
		if ( $issuer_name !== '' ) {
			$html .= '<div style="font-weight:bold;">' . esc_html( $issuer_name ) . '</div>';
			if ( ! empty( $branding['address_html'] ) ) {
				$html .= '<div>' . $branding['address_html'] . '</div>';
			}
		} elseif ( $legacy_company_info_html !== '' ) {
			$html .= $legacy_company_info_html;
		}

		$html .= '</div>';

		return $html;
	}
}
