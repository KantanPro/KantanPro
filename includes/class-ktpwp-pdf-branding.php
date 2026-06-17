<?php
/**
 * 帳票用ロゴ・印影と発行者情報
 *
 * @package KTPWP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class KTPWP_Pdf_Branding {

	public const OPTION_LOGO_PATH = 'ktp_pdf_logo_path';

	public const OPTION_SEAL_PATH = 'ktp_pdf_seal_path';

	public const OPTION_ISSUER_NAME = 'ktp_pdf_issuer_name';

	public const OPTION_ISSUER_ADDRESS = 'ktp_pdf_issuer_address';

	/** @var list<string> */
	private static $allowed_mimes = array(
		'image/png',
		'image/jpeg',
		'image/gif',
		'image/webp',
	);

	/**
	 * @return array{name:string,address:string|null,address_html:string|null,note:string|null,logo_data_uri:string|null,seal_data_uri:string|null}
	 */
	public static function for_documents() {
		$name = self::issuer_display_name();
		$address = self::issuer_address_plain();
		$address_html = ( $address !== null && $address !== '' ) ? nl2br( esc_html( $address ), false ) : null;
		$note = '';
		if ( class_exists( 'KTPWP_Settings' ) ) {
			$note = trim( (string) KTPWP_Settings::get_qualified_invoice_number() );
		}

		return array(
			'name' => $name,
			'address' => $address !== '' ? $address : null,
			'address_html' => $address_html,
			'note' => $note !== '' ? $note : null,
			'logo_data_uri' => self::path_to_data_uri( get_option( self::OPTION_LOGO_PATH, '' ) ),
			'seal_data_uri' => self::path_to_data_uri( get_option( self::OPTION_SEAL_PATH, '' ) ),
		);
	}

	public static function issuer_display_name() {
		$custom = trim( (string) get_option( self::OPTION_ISSUER_NAME, '' ) );
		if ( $custom !== '' ) {
			return $custom;
		}

		return wp_strip_all_tags( get_bloginfo( 'name' ) );
	}

	public static function issuer_address_plain() {
		$custom = trim( (string) get_option( self::OPTION_ISSUER_ADDRESS, '' ) );
		if ( $custom !== '' ) {
			return $custom;
		}
		if ( class_exists( 'KTPWP_Settings' ) ) {
			return wp_strip_all_tags( KTPWP_Settings::get_company_info() );
		}

		return '';
	}

	/**
	 * @return string|null 相対パス（uploads/ktp-pdf-branding/ 以下）
	 */
	public static function storage_dir() {
		$upload = wp_upload_dir();
		if ( ! empty( $upload['error'] ) ) {
			return null;
		}
		$dir = trailingslashit( $upload['basedir'] ) . 'ktp-pdf-branding';
		if ( ! file_exists( $dir ) ) {
			wp_mkdir_p( $dir );
		}

		return $dir;
	}

	/**
	 * @param string $field logo|seal
	 * @return string|null
	 */
	public static function handle_upload( $field ) {
		if ( empty( $_FILES[ $field ]['tmp_name'] ) || ! is_uploaded_file( $_FILES[ $field ]['tmp_name'] ) ) {
			return null;
		}

		$file = $_FILES[ $field ];
		if ( (int) $file['size'] > 1024 * 1024 ) {
			return null;
		}

		$finfo = finfo_open( FILEINFO_MIME_TYPE );
		$mime  = $finfo ? finfo_file( $finfo, $file['tmp_name'] ) : false;
		if ( $finfo ) {
			finfo_close( $finfo );
		}
		if ( ! is_string( $mime ) || ! in_array( $mime, self::$allowed_mimes, true ) ) {
			return null;
		}

		$size = @getimagesize( $file['tmp_name'] );
		if ( $size && ( $size[0] > 2048 || $size[1] > 2048 ) ) {
			return null;
		}

		$dir = self::storage_dir();
		if ( $dir === null ) {
			return null;
		}

		$ext_map = array(
			'image/jpeg' => 'jpg',
			'image/png' => 'png',
			'image/gif' => 'gif',
			'image/webp' => 'webp',
		);
		$ext     = $ext_map[ $mime ] ?? 'bin';
		$basename = $field . '-' . wp_generate_password( 8, false ) . '.' . $ext;
		$dest     = trailingslashit( $dir ) . $basename;

		if ( ! move_uploaded_file( $file['tmp_name'], $dest ) ) {
			return null;
		}

		return 'ktp-pdf-branding/' . $basename;
	}

	public static function remove_file( $option_key ) {
		$rel = (string) get_option( $option_key, '' );
		if ( $rel === '' ) {
			return;
		}
		$upload = wp_upload_dir();
		$path   = trailingslashit( $upload['basedir'] ) . ltrim( $rel, '/' );
		if ( is_file( $path ) ) {
			wp_delete_file( $path );
		}
		delete_option( $option_key );
	}

	/**
	 * アップロード時に全種別の表示フラグをオン
	 */
	public static function enable_visibility_on_upload( $field ) {
		$key = ( $field === 'seal' ) ? 'show_seal' : 'show_logo';
		$all = get_option( KTPWP_Pdf_Document_Settings::OPTION_DOCUMENT_SETTINGS, array() );
		if ( ! is_array( $all ) ) {
			$all = array();
		}
		foreach ( KTPWP_Pdf_Document_Kind::all() as $kind ) {
			if ( ! isset( $all[ $kind ] ) || ! is_array( $all[ $kind ] ) ) {
				$all[ $kind ] = array();
			}
			$all[ $kind ][ $key ] = true;
		}
		update_option( KTPWP_Pdf_Document_Settings::OPTION_DOCUMENT_SETTINGS, $all );
	}

	private static function path_to_data_uri( $relative_path ) {
		$relative_path = trim( (string) $relative_path );
		if ( $relative_path === '' ) {
			return null;
		}
		$upload = wp_upload_dir();
		$path   = trailingslashit( $upload['basedir'] ) . ltrim( $relative_path, '/' );
		if ( ! is_readable( $path ) ) {
			return null;
		}
		$binary = file_get_contents( $path );
		if ( $binary === false ) {
			return null;
		}
		$finfo = finfo_open( FILEINFO_MIME_TYPE );
		$mime  = $finfo ? finfo_buffer( $finfo, $binary ) : false;
		if ( $finfo ) {
			finfo_close( $finfo );
		}
		if ( ! is_string( $mime ) || ! in_array( $mime, self::$allowed_mimes, true ) ) {
			return null;
		}

		return 'data:' . $mime . ';base64,' . base64_encode( $binary );
	}

	/**
	 * JS 向けエクスポート
	 *
	 * @return array<string, mixed>
	 */
	public static function export_for_js() {
		$settings = array();
		foreach ( KTPWP_Pdf_Document_Kind::all() as $kind ) {
			$settings[ $kind ] = KTPWP_Pdf_Document_Settings::resolve( $kind );
		}
		$branding = self::for_documents();

		return array(
			'document_settings' => $settings,
			'branding' => array(
				'name' => $branding['name'],
				'address_html' => $branding['address_html'],
				'note' => $branding['note'],
				'logo_data_uri' => $branding['logo_data_uri'],
				'seal_data_uri' => $branding['seal_data_uri'],
			),
			'bank_transfer_html' => class_exists( 'KTPWP_Settings' ) ? KTPWP_Settings::get_bank_transfer_invoice_html() : '',
		);
	}
}
