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
		return self::bulk_invoice_issuer_stack_html( $branding, $doc_settings, '', '', $legacy_company_info_html );
	}

	/**
	 * 一括請求：右上 issuer stack（KantanBiz bulk-invoice-issuer-stack 相当）
	 *
	 * @param array<string, mixed> $branding
	 * @param array<string, mixed> $doc_settings
	 * @param string               $qualified_invoice_number
	 * @param string               $bank_transfer_html
	 * @param string               $legacy_company_info_html
	 */
	public static function bulk_invoice_issuer_stack_html(
		array $branding,
		array $doc_settings,
		$qualified_invoice_number = '',
		$bank_transfer_html = '',
		$legacy_company_info_html = ''
	) {
		$sizes              = KTPWP_Pdf_Document_Settings::scaled_branding_sizes( $doc_settings );
		$seal_overlay_css   = KTPWP_Pdf_Document_Settings::bulk_issuer_seal_overlay_css_declaration( $sizes );
		$issuer_font_size   = KTPWP_Pdf_Document_Settings::scaled_font_size_px(
			(int) ( $doc_settings['body_font_size'] ?? 14 ),
			$doc_settings
		);
		$line_height        = (float) ( $doc_settings['issuer_line_height'] ?? KTPWP_Pdf_Document_Settings::DEFAULT_ISSUER_LINE_HEIGHT );
		$accent             = esc_attr( $doc_settings['accent_color'] ?? '#374151' );
		$title              = KTPWP_Pdf_Document_Settings::resolve_title(
			KTPWP_Pdf_Document_Kind::BULK_INVOICE,
			__( '請求書', 'ktpwp' )
		);

		$show_logo = ! empty( $doc_settings['show_logo'] ) && ! empty( $branding['logo_data_uri'] );
		$show_seal = ! empty( $doc_settings['show_seal'] ) && ! empty( $branding['seal_data_uri'] );
		$show_qualified = ! empty( $doc_settings['show_qualified_invoice_number'] )
			&& trim( (string) $qualified_invoice_number ) !== ''
			&& ! ( class_exists( 'KTPWP_Tax_Policy' ) && KTPWP_Tax_Policy::hide_tax_columns() );
		$show_bank = ! empty( $doc_settings['show_bank_transfer'] )
			&& trim( wp_strip_all_tags( (string) $bank_transfer_html ) ) !== '';

		$issuer_name      = trim( (string) ( $branding['name'] ?? '' ) );
		$has_company_text = $issuer_name !== '' || ! empty( $branding['address_html'] );
		$has_issuer_content = $show_logo || $show_seal || $has_company_text || $show_qualified || $show_bank || $legacy_company_info_html !== '';

		if ( ! $has_issuer_content ) {
			return '';
		}

		$html  = '<div class="ktp-bulk-invoice-issuer-stack" aria-hidden="false">';
		$html .= '<div class="ktp-bulk-invoice-issuer-inner ktp-bulk-invoice-company-info" style="line-height:' . esc_attr( (string) $line_height ) . ';font-size:' . (int) $issuer_font_size . 'px;color:#374151;">';
		$html .= '<div class="ktp-bulk-invoice-issuer-doc-title" style="color:' . $accent . ';">';
		$html .= self::bulk_invoice_doc_title_ornament_html();
		$html .= '<span class="ktp-bulk-invoice-issuer-doc-title-text">' . esc_html( $title ) . '</span>';
		$html .= self::bulk_invoice_doc_title_ornament_html();
		$html .= '</div>';

		if ( $show_logo ) {
			$html .= '<div class="ktp-bulk-invoice-issuer-logo-wrap">';
			$html .= '<img src="' . esc_attr( $branding['logo_data_uri'] ) . '" alt="" class="ktp-bulk-invoice-issuer-logo-img">';
			$html .= '</div>';
		}

		$html .= '<div class="ktp-bulk-invoice-issuer-text-block">';
		if ( $show_qualified ) {
			$html .= '<div class="ktp-bulk-invoice-issuer-registration">' . esc_html__( '登録番号：', 'ktpwp' ) . esc_html( $qualified_invoice_number ) . '</div>';
		}

		if ( $has_company_text || $show_bank || $show_seal || $legacy_company_info_html !== '' ) {
			$html .= '<div class="ktp-bulk-invoice-issuer-seal-scope">';
			if ( $issuer_name !== '' ) {
				$html .= '<div>' . esc_html( $issuer_name ) . '</div>';
				if ( ! empty( $branding['address_html'] ) ) {
					$html .= '<div>' . $branding['address_html'] . '</div>';
				}
			} elseif ( $legacy_company_info_html !== '' ) {
				$html .= $legacy_company_info_html;
			}
			if ( $show_bank ) {
				$bank_inner = class_exists( 'KTPWP_Settings' )
					? KTPWP_Settings::get_bank_transfer_bulk_issuer_html()
					: $bank_transfer_html;
				if ( $bank_inner !== '' ) {
					$html .= '<div class="ktp-bulk-invoice-issuer-bank">' . $bank_inner . '</div>';
				}
			}
			if ( $show_seal ) {
				$html .= '<img src="' . esc_attr( $branding['seal_data_uri'] ) . '" alt="" class="ktp-bulk-invoice-issuer-seal-overlay" style="' . esc_attr( $seal_overlay_css ) . '">';
			}
			$html .= '</div>';
		}

		$html .= '</div></div></div>';

		return $html;
	}

	/**
	 * @return string
	 */
	private static function bulk_invoice_doc_title_ornament_html() {
		return '<span class="ktp-bulk-invoice-issuer-doc-title-ornament" aria-hidden="true"><span></span><span></span><span></span></span>';
	}
}
