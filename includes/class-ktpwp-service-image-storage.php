<?php
/**
 * サービス画像の保存先（プラグイン外 uploads / レガシー plugin 内）を管理する。
 *
 * @package KantanPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * KTPWP_Service_Image_Storage
 */
class KTPWP_Service_Image_Storage {

	const UPLOAD_SUBDIR = 'ktpwp-service-images';

	/**
	 * 「データを残す」設定かどうか
	 */
	public static function uses_persistent_storage() {
		if ( function_exists( 'ktpwp_get_uninstall_mode' ) ) {
			return ktpwp_get_uninstall_mode() === 'keep_data';
		}

		$options = get_option( 'ktp_uninstall_settings', array() );
		$mode    = isset( $options['uninstall_mode'] ) ? (string) $options['uninstall_mode'] : 'keep_data';

		return $mode !== 'full_delete';
	}

	/**
	 * プラグインルート（includes の親）
	 */
	public static function get_plugin_root() {
		return dirname( __DIR__ );
	}

	/**
	 * レガシー保存先（プラグイン内 images/upload/）
	 */
	public static function get_legacy_upload_dir() {
		return trailingslashit( self::get_plugin_root() ) . 'images/upload/';
	}

	/**
	 * レガシー公開 URL
	 */
	public static function get_legacy_upload_url() {
		return plugin_dir_url( self::get_plugin_root() . '/ktpwp.php' ) . 'images/upload/';
	}

	/**
	 * wp-content/uploads 配下の永続保存先（物理パス）
	 *
	 * @return string|false
	 */
	public static function get_persistent_upload_dir() {
		$upload = wp_upload_dir();
		if ( ! empty( $upload['error'] ) ) {
			return false;
		}

		$dir = trailingslashit( $upload['basedir'] ) . self::UPLOAD_SUBDIR;
		if ( ! wp_mkdir_p( $dir ) ) {
			return false;
		}

		return trailingslashit( $dir );
	}

	/**
	 * wp-content/uploads 配下の永続保存先（公開 URL）
	 *
	 * @return string|false
	 */
	public static function get_persistent_upload_url() {
		$upload = wp_upload_dir();
		if ( ! empty( $upload['error'] ) ) {
			return false;
		}

		return trailingslashit( $upload['baseurl'] ) . self::UPLOAD_SUBDIR . '/';
	}

	/**
	 * 新規アップロード先ディレクトリ
	 */
	public static function get_upload_dir() {
		if ( self::uses_persistent_storage() ) {
			$persistent = self::get_persistent_upload_dir();
			if ( $persistent !== false ) {
				return $persistent;
			}
		}

		$legacy = self::get_legacy_upload_dir();
		if ( ! is_dir( $legacy ) ) {
			wp_mkdir_p( $legacy );
		}

		return $legacy;
	}

	/**
	 * 新規アップロード先 URL
	 */
	public static function get_upload_url() {
		if ( self::uses_persistent_storage() ) {
			$url = self::get_persistent_upload_url();
			if ( $url !== false ) {
				return $url;
			}
		}

		return self::get_legacy_upload_url();
	}

	/**
	 * 画像検索対象ディレクトリ（永続 + レガシー）
	 *
	 * @return array<int, string>
	 */
	public static function get_search_dirs() {
		$dirs    = array();
		$primary = self::get_upload_dir();
		if ( $primary !== '' ) {
			$dirs[] = $primary;
		}

		if ( self::uses_persistent_storage() ) {
			$legacy = self::get_legacy_upload_dir();
			if ( is_dir( $legacy ) && ! in_array( $legacy, $dirs, true ) ) {
				$dirs[] = $legacy;
			}
		}

		return $dirs;
	}

	/**
	 * 物理パスから公開 URL を組み立てる
	 *
	 * @param string $file_path ファイルパス。
	 * @return string
	 */
	public static function file_path_to_public_url( $file_path ) {
		$file_path = wp_normalize_path( (string) $file_path );
		$filename  = basename( $file_path );

		if ( self::uses_persistent_storage() ) {
			$persistent_dir = self::get_persistent_upload_dir();
			if ( $persistent_dir !== false && strpos( $file_path, wp_normalize_path( $persistent_dir ) ) === 0 ) {
				$url = self::get_persistent_upload_url();
				return ( $url !== false ) ? $url . $filename : self::get_legacy_upload_url() . $filename;
			}
		}

		return self::get_legacy_upload_url() . $filename;
	}

	/**
	 * プラグイン内のレガシー画像を uploads へコピー（keep_data 時）
	 */
	public static function migrate_legacy_images() {
		if ( ! self::uses_persistent_storage() ) {
			return;
		}

		$target = self::get_persistent_upload_dir();
		$legacy = self::get_legacy_upload_dir();
		if ( $target === false || ! is_dir( $legacy ) ) {
			return;
		}

		$files = scandir( $legacy );
		if ( ! is_array( $files ) ) {
			return;
		}

		foreach ( $files as $file ) {
			if ( $file === '.' || $file === '..' ) {
				continue;
			}

			$source = $legacy . $file;
			if ( ! is_file( $source ) ) {
				continue;
			}

			$dest = $target . $file;
			if ( ! file_exists( $dest ) ) {
				@copy( $source, $dest );
			}
		}
	}

	/**
	 * 完全削除時に永続画像フォルダを削除
	 */
	public static function delete_persistent_storage_on_full_uninstall() {
		if ( ! function_exists( 'ktpwp_get_uninstall_mode' ) || ktpwp_get_uninstall_mode() !== 'full_delete' ) {
			return;
		}

		$upload = wp_upload_dir();
		if ( ! empty( $upload['error'] ) ) {
			return;
		}

		$dir = trailingslashit( $upload['basedir'] ) . self::UPLOAD_SUBDIR;
		if ( is_dir( $dir ) ) {
			self::recursive_rmdir( $dir );
		}
	}

	/**
	 * ディレクトリを再帰削除
	 *
	 * @param string $dir ディレクトリパス。
	 */
	private static function recursive_rmdir( $dir ) {
		if ( ! is_dir( $dir ) ) {
			return;
		}

		$objects = scandir( $dir );
		if ( ! is_array( $objects ) ) {
			return;
		}

		foreach ( $objects as $object ) {
			if ( $object === '.' || $object === '..' ) {
				continue;
			}

			$path = $dir . '/' . $object;
			if ( is_dir( $path ) ) {
				self::recursive_rmdir( $path );
			} else {
				@unlink( $path );
			}
		}

		@rmdir( $dir );
	}
}
