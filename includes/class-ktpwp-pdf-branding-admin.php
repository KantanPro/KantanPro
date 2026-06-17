<?php
/**
 * 帳票表示設定（管理画面）
 *
 * @package KTPWP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class KTPWP_Pdf_Branding_Admin {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ), 20 );
		add_action( 'admin_init', array( __CLASS__, 'handle_save' ) );
	}

	public static function register_menu() {
		add_submenu_page(
			'ktp-settings',
			__( '帳票表示設定', 'ktpwp' ),
			__( '帳票表示設定', 'ktpwp' ),
			'manage_options',
			'ktp-pdf-branding',
			array( __CLASS__, 'render_page' )
		);
	}

	public static function handle_save() {
		if ( ! isset( $_POST['ktp_pdf_branding_save'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		check_admin_referer( 'ktp_pdf_branding_save', 'ktp_pdf_branding_nonce' );

		update_option( KTPWP_Pdf_Branding::OPTION_ISSUER_NAME, sanitize_text_field( wp_unslash( $_POST['pdf_issuer_name'] ?? '' ) ) );
		update_option( KTPWP_Pdf_Branding::OPTION_ISSUER_ADDRESS, sanitize_textarea_field( wp_unslash( $_POST['pdf_issuer_address'] ?? '' ) ) );

		if ( ! empty( $_POST['remove_logo'] ) ) {
			KTPWP_Pdf_Branding::remove_file( KTPWP_Pdf_Branding::OPTION_LOGO_PATH );
		} elseif ( ! empty( $_FILES['pdf_logo']['tmp_name'] ) ) {
			$path = KTPWP_Pdf_Branding::handle_upload( 'pdf_logo' );
			if ( $path ) {
				KTPWP_Pdf_Branding::remove_file( KTPWP_Pdf_Branding::OPTION_LOGO_PATH );
				update_option( KTPWP_Pdf_Branding::OPTION_LOGO_PATH, $path );
				KTPWP_Pdf_Branding::enable_visibility_on_upload( 'logo' );
			}
		}

		if ( ! empty( $_POST['remove_seal'] ) ) {
			KTPWP_Pdf_Branding::remove_file( KTPWP_Pdf_Branding::OPTION_SEAL_PATH );
		} elseif ( ! empty( $_FILES['pdf_seal']['tmp_name'] ) ) {
			$path = KTPWP_Pdf_Branding::handle_upload( 'pdf_seal' );
			if ( $path ) {
				KTPWP_Pdf_Branding::remove_file( KTPWP_Pdf_Branding::OPTION_SEAL_PATH );
				update_option( KTPWP_Pdf_Branding::OPTION_SEAL_PATH, $path );
				KTPWP_Pdf_Branding::enable_visibility_on_upload( 'seal' );
			}
		}

		$raw = isset( $_POST['pdf_document_settings'] ) && is_array( $_POST['pdf_document_settings'] )
			? wp_unslash( $_POST['pdf_document_settings'] )
			: array();
		$normalized = KTPWP_Pdf_Document_Settings::normalize_from_request( $raw );
		update_option( KTPWP_Pdf_Document_Settings::OPTION_DOCUMENT_SETTINGS, $normalized );

		if ( isset( $normalized[ KTPWP_Pdf_Document_Kind::ORDER ]['layout'] ) && $normalized[ KTPWP_Pdf_Document_Kind::ORDER ]['layout'] ) {
			update_option( KTPWP_Pdf_Document_Settings::OPTION_PDF_LAYOUT, $normalized[ KTPWP_Pdf_Document_Kind::ORDER ]['layout'] );
		}

		wp_safe_redirect( add_query_arg( 'updated', '1', admin_url( 'admin.php?page=ktp-pdf-branding' ) ) );
		exit;
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$settings = KTPWP_Pdf_Document_Settings::raw_for_form();
		$branding = KTPWP_Pdf_Branding::for_documents();
		$logo_url = self::preview_url( get_option( KTPWP_Pdf_Branding::OPTION_LOGO_PATH, '' ) );
		$seal_url = self::preview_url( get_option( KTPWP_Pdf_Branding::OPTION_SEAL_PATH, '' ) );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( '帳票表示設定', 'ktpwp' ); ?></h1>
			<?php if ( isset( $_GET['updated'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( '保存しました。', 'ktpwp' ); ?></p></div>
			<?php endif; ?>
			<p><?php esc_html_e( '見積書・案件帳票・一括請求書ごとにレイアウト・表示項目・ロゴ・印影を設定できます。', 'ktpwp' ); ?></p>
			<form method="post" enctype="multipart/form-data">
				<?php wp_nonce_field( 'ktp_pdf_branding_save', 'ktp_pdf_branding_nonce' ); ?>
				<input type="hidden" name="ktp_pdf_branding_save" value="1" />
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="pdf_issuer_name"><?php esc_html_e( '帳票に出す社名', 'ktpwp' ); ?></label></th>
						<td><input name="pdf_issuer_name" id="pdf_issuer_name" type="text" class="regular-text" value="<?php echo esc_attr( get_option( KTPWP_Pdf_Branding::OPTION_ISSUER_NAME, '' ) ); ?>" />
						<p class="description"><?php esc_html_e( '空欄のときはサイト名を使います。', 'ktpwp' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><label for="pdf_issuer_address"><?php esc_html_e( '住所・連絡先', 'ktpwp' ); ?></label></th>
						<td><textarea name="pdf_issuer_address" id="pdf_issuer_address" rows="4" class="large-text"><?php echo esc_textarea( get_option( KTPWP_Pdf_Branding::OPTION_ISSUER_ADDRESS, '' ) ); ?></textarea>
						<p class="description"><?php esc_html_e( '空欄のときは一般設定の会社情報を使います。', 'ktpwp' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'ロゴ', 'ktpwp' ); ?></th>
						<td>
							<?php if ( $logo_url ) : ?><p><img src="<?php echo esc_url( $logo_url ); ?>" alt="" style="max-height:48px;max-width:140px;"></p><?php endif; ?>
							<input type="file" name="pdf_logo" accept="image/jpeg,image/png,image/gif,image/webp" />
							<label><input type="checkbox" name="remove_logo" value="1" /> <?php esc_html_e( '削除', 'ktpwp' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( '印影', 'ktpwp' ); ?></th>
						<td>
							<?php if ( $seal_url ) : ?><p><img src="<?php echo esc_url( $seal_url ); ?>" alt="" style="max-height:48px;max-width:48px;"></p><?php endif; ?>
							<input type="file" name="pdf_seal" accept="image/jpeg,image/png,image/gif,image/webp" />
							<label><input type="checkbox" name="remove_seal" value="1" /> <?php esc_html_e( '削除', 'ktpwp' ); ?></label>
						</td>
					</tr>
				</table>
				<?php
				foreach ( array(
					KTPWP_Pdf_Document_Kind::ESTIMATE => __( '見積書', 'ktpwp' ),
					KTPWP_Pdf_Document_Kind::ORDER => __( '案件帳票', 'ktpwp' ),
					KTPWP_Pdf_Document_Kind::BULK_INVOICE => __( '一括請求書', 'ktpwp' ),
				) as $kind => $label ) {
					self::render_kind_fields( $kind, $label, $settings[ $kind ] ?? array() );
				}
				?>
				<?php submit_button( __( '保存', 'ktpwp' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * @param string               $kind
	 * @param string               $label
	 * @param array<string, mixed> $values
	 */
	private static function render_kind_fields( $kind, $label, array $values ) {
		$p = 'pdf_document_settings[' . esc_attr( $kind ) . ']';
		$v = function ( $key ) use ( $values ) {
			return isset( $values[ $key ] ) ? $values[ $key ] : '';
		};
		?>
		<h2 class="title"><?php echo esc_html( $label ); ?></h2>
		<table class="form-table" role="presentation">
			<tr><th><?php esc_html_e( 'レイアウト', 'ktpwp' ); ?></th><td>
				<select name="<?php echo esc_attr( $p ); ?>[layout]">
					<option value="<?php echo esc_attr( KTPWP_Pdf_Document_Settings::LAYOUT_DEFAULT ); ?>" <?php selected( $v( 'layout' ), KTPWP_Pdf_Document_Settings::LAYOUT_DEFAULT ); ?>><?php esc_html_e( '標準', 'ktpwp' ); ?></option>
					<option value="<?php echo esc_attr( KTPWP_Pdf_Document_Settings::LAYOUT_COMPACT ); ?>" <?php selected( $v( 'layout' ), KTPWP_Pdf_Document_Settings::LAYOUT_COMPACT ); ?>><?php esc_html_e( 'コンパクト', 'ktpwp' ); ?></option>
				</select>
			</td></tr>
			<tr><th><?php esc_html_e( 'タイトル（任意）', 'ktpwp' ); ?></th><td><input type="text" name="<?php echo esc_attr( $p ); ?>[title]" value="<?php echo esc_attr( (string) $v( 'title' ) ); ?>" class="regular-text" /></td></tr>
			<tr><th><?php esc_html_e( '挨拶文（任意）', 'ktpwp' ); ?></th><td><textarea name="<?php echo esc_attr( $p ); ?>[lead]" rows="2" class="large-text"><?php echo esc_textarea( (string) $v( 'lead' ) ); ?></textarea></td></tr>
			<tr><th><?php esc_html_e( 'アクセント色', 'ktpwp' ); ?></th><td><input type="text" name="<?php echo esc_attr( $p ); ?>[accent_color]" value="<?php echo esc_attr( (string) $v( 'accent_color' ) ); ?>" pattern="#[0-9A-Fa-f]{6}" /></td></tr>
			<tr><th><?php esc_html_e( '本文サイズ(px)', 'ktpwp' ); ?></th><td><input type="number" min="8" max="16" name="<?php echo esc_attr( $p ); ?>[body_font_size]" value="<?php echo esc_attr( (string) $v( 'body_font_size' ) ); ?>" /></td></tr>
			<tr><th><?php esc_html_e( 'タイトルサイズ(px)', 'ktpwp' ); ?></th><td><input type="number" min="14" max="32" name="<?php echo esc_attr( $p ); ?>[title_font_size]" value="<?php echo esc_attr( (string) $v( 'title_font_size' ) ); ?>" /></td></tr>
			<tr><th><?php esc_html_e( '自社情報の行間', 'ktpwp' ); ?></th><td><input type="number" min="<?php echo (int) KTPWP_Pdf_Document_Settings::ISSUER_LINE_HEIGHT_MIN; ?>" max="<?php echo (int) KTPWP_Pdf_Document_Settings::ISSUER_LINE_HEIGHT_MAX; ?>" step="0.05" name="<?php echo esc_attr( $p ); ?>[issuer_line_height]" value="<?php echo esc_attr( (string) $v( 'issuer_line_height' ) ); ?>" /></td></tr>
			<tr><th><?php esc_html_e( '請求明細行の高さ(px)', 'ktpwp' ); ?></th><td><input type="number" min="<?php echo (int) KTPWP_Pdf_Document_Settings::INVOICE_ROW_PADDING_Y_MIN; ?>" max="<?php echo (int) KTPWP_Pdf_Document_Settings::INVOICE_ROW_PADDING_Y_MAX; ?>" name="<?php echo esc_attr( $p ); ?>[invoice_row_padding_y_px]" value="<?php echo esc_attr( (string) $v( 'invoice_row_padding_y_px' ) ); ?>" /></td></tr>
			<tr><th><?php esc_html_e( 'ロゴ位置', 'ktpwp' ); ?></th><td>
				<select name="<?php echo esc_attr( $p ); ?>[logo_position]">
					<option value="header" <?php selected( $v( 'logo_position' ), 'header' ); ?>><?php esc_html_e( '上部', 'ktpwp' ); ?></option>
					<option value="footer" <?php selected( $v( 'logo_position' ), 'footer' ); ?>><?php esc_html_e( '下部', 'ktpwp' ); ?></option>
				</select>
			</td></tr>
			<tr><th><?php esc_html_e( 'ロゴ高さ/幅(px)', 'ktpwp' ); ?></th><td>
				<input type="number" name="<?php echo esc_attr( $p ); ?>[logo_max_height_px]" value="<?php echo esc_attr( (string) $v( 'logo_max_height_px' ) ); ?>" min="12" max="80" /> ×
				<input type="number" name="<?php echo esc_attr( $p ); ?>[logo_max_width_px]" value="<?php echo esc_attr( (string) $v( 'logo_max_width_px' ) ); ?>" min="40" max="320" />
			</td></tr>
			<tr><th><?php esc_html_e( '印影サイズ(px)', 'ktpwp' ); ?></th><td>
				<input type="number" name="<?php echo esc_attr( $p ); ?>[seal_max_height_px]" value="<?php echo esc_attr( (string) $v( 'seal_max_height_px' ) ); ?>" min="16" max="120" /> ×
				<input type="number" name="<?php echo esc_attr( $p ); ?>[seal_max_width_px]" value="<?php echo esc_attr( (string) $v( 'seal_max_width_px' ) ); ?>" min="16" max="120" />
			</td></tr>
			<?php self::checkbox_row( $p, 'show_logo', __( 'ロゴ表示', 'ktpwp' ), $v( 'show_logo' ) ); ?>
			<?php self::checkbox_row( $p, 'show_seal', __( '印影表示', 'ktpwp' ), $v( 'show_seal' ) ); ?>
			<?php self::checkbox_row( $p, 'show_tax_column', __( '税率列', 'ktpwp' ), $v( 'show_tax_column' ) ); ?>
			<?php if ( $kind === KTPWP_Pdf_Document_Kind::BULK_INVOICE ) : ?>
			<?php self::checkbox_row( $p, 'show_tax_amount_column', __( '税額列（初期表示）', 'ktpwp' ), $v( 'show_tax_amount_column' ) ); ?>
			<?php endif; ?>
			<?php self::checkbox_row( $p, 'show_remarks_column', __( '備考列', 'ktpwp' ), $v( 'show_remarks_column' ) ); ?>
			<?php self::checkbox_row( $p, 'show_memo', __( 'メモ', 'ktpwp' ), $v( 'show_memo' ) ); ?>
			<?php self::checkbox_row( $p, 'show_output_at', __( '出力日時', 'ktpwp' ), $v( 'show_output_at' ) ); ?>
			<?php self::checkbox_row( $p, 'show_bank_transfer', __( '振込先', 'ktpwp' ), $v( 'show_bank_transfer' ) ); ?>
			<?php self::checkbox_row( $p, 'show_qualified_invoice_number', __( '適格請求書番号', 'ktpwp' ), $v( 'show_qualified_invoice_number' ) ); ?>
			<?php self::checkbox_row( $p, 'logo_keep_aspect_ratio', __( 'ロゴ縦横比固定', 'ktpwp' ), $v( 'logo_keep_aspect_ratio' ) ); ?>
			<?php self::checkbox_row( $p, 'seal_keep_aspect_ratio', __( '印影縦横比固定', 'ktpwp' ), $v( 'seal_keep_aspect_ratio' ) ); ?>
			<?php if ( $kind === KTPWP_Pdf_Document_Kind::BULK_INVOICE ) : ?>
			<tr><th><?php esc_html_e( '余白(mm)', 'ktpwp' ); ?></th><td>
				上 <input type="number" name="<?php echo esc_attr( $p ); ?>[margin_top_mm]" value="<?php echo esc_attr( (string) $v( 'margin_top_mm' ) ); ?>" min="0" max="80" style="width:4em" />
				右 <input type="number" name="<?php echo esc_attr( $p ); ?>[margin_right_mm]" value="<?php echo esc_attr( (string) $v( 'margin_right_mm' ) ); ?>" min="0" max="40" style="width:4em" />
				下 <input type="number" name="<?php echo esc_attr( $p ); ?>[margin_bottom_mm]" value="<?php echo esc_attr( (string) $v( 'margin_bottom_mm' ) ); ?>" min="0" max="40" style="width:4em" />
				左 <input type="number" name="<?php echo esc_attr( $p ); ?>[margin_left_mm]" value="<?php echo esc_attr( (string) $v( 'margin_left_mm' ) ); ?>" min="0" max="40" style="width:4em" />
			</td></tr>
			<tr><th><?php esc_html_e( '封筒窓(mm)', 'ktpwp' ); ?></th><td>
				上 <input type="number" name="<?php echo esc_attr( $p ); ?>[envelope_top_mm]" value="<?php echo esc_attr( (string) $v( 'envelope_top_mm' ) ); ?>" min="0" max="40" style="width:4em" />
				左 <input type="number" name="<?php echo esc_attr( $p ); ?>[envelope_left_mm]" value="<?php echo esc_attr( (string) $v( 'envelope_left_mm' ) ); ?>" min="0" max="40" style="width:4em" />
			</td></tr>
			<?php endif; ?>
		</table>
		<?php
	}

	private static function checkbox_row( $prefix, $key, $label, $checked ) {
		$name = $prefix . '[' . $key . ']';
		?>
		<tr><th><?php echo esc_html( $label ); ?></th><td>
			<input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="0" />
			<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>" value="1" <?php checked( filter_var( $checked, FILTER_VALIDATE_BOOLEAN ) ); ?> /> <?php echo esc_html( $label ); ?></label>
		</td></tr>
		<?php
	}

	private static function preview_url( $relative ) {
		$relative = trim( (string) $relative );
		if ( $relative === '' ) {
			return '';
		}
		$upload = wp_upload_dir();

		return trailingslashit( $upload['baseurl'] ) . ltrim( $relative, '/' );
	}
}
