<?php
/**
 * アップロードされたファイルの受け取り。
 *
 * `move_uploaded_file()` は WordPress のチェック（アップロードエラーの判定、
 * MIME の照合、ファイル名の正規化、`upload_dir` を通したパス解決）をすべて迂回する。
 * wp.org のガイドラインでは `wp_handle_upload()` を使うことが求められている
 * （2026-09-02 のレビュー指摘）。ここはその薄いラッパー。
 *
 * 保存先はプラグイン独自のディレクトリだが、いずれも `wp_upload_dir()` の
 * 配下にある。`upload_dir` フィルタで一時的にそこへ向けてから戻す。
 *
 * @package KTPWP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! class_exists( 'KTPWP_Upload' ) ) {

	class KTPWP_Upload {

		/**
		 * アップロードファイルを指定ディレクトリへ受け取る。
		 *
		 * @param array  $file     $_FILES の1要素。
		 * @param string $dir      保存先の絶対パス（wp_upload_dir() 配下であること）。
		 * @param string $basename 保存するファイル名。空なら WordPress に任せる。
		 * @param array  $mimes    許可する MIME（'jpg|jpeg' => 'image/jpeg' 形式）。空なら既定。
		 * @return string|null 保存されたファイルの絶対パス。失敗時は null。
		 */
		public static function receive( $file, $dir, $basename = '', $mimes = array() ) {
			if ( ! is_array( $file ) || empty( $file['tmp_name'] ) || $dir === '' ) {
				return null;
			}

			// コアの読み込みファイルだが、直後に wp_handle_upload() を使うので
			// ガイドラインが認めている例外にあたる。
			if ( ! function_exists( 'wp_handle_upload' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}

			$relocate = static function ( $dirs ) use ( $dir ) {
				$dirs['path']   = $dir;
				$dirs['subdir'] = '';
				$upload         = wp_upload_dir();
				// URL も辻褄を合わせておく（呼び出し側は使わないことが多い）。
				if ( empty( $upload['error'] ) && strpos( $dir, $upload['basedir'] ) === 0 ) {
					$dirs['url'] = $upload['baseurl'] . substr( $dir, strlen( $upload['basedir'] ) );
				}
				return $dirs;
			};

			$overrides = array( 'test_form' => false );
			if ( $basename !== '' ) {
				$overrides['unique_filename_callback'] = static function () use ( $basename ) {
					return $basename;
				};
			}
			if ( ! empty( $mimes ) ) {
				$overrides['mimes'] = $mimes;
			}

			add_filter( 'upload_dir', $relocate );
			$result = wp_handle_upload( $file, $overrides );
			remove_filter( 'upload_dir', $relocate );

			if ( ! is_array( $result ) || ! empty( $result['error'] ) || empty( $result['file'] ) ) {
				return null;
			}

			return $result['file'];
		}
	}
}
