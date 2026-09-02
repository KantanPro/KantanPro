<?php
/**
 * Plugin Name: KantanPro ビルド切替（開発専用）
 * Description: 管理バーから wp.org 版と GitHub 版をワンクリックで切り替える。テスト環境専用。
 * Version: 1.0.0
 *
 * **これはプラグイン本体には絶対に含めない。** mu-plugins として
 * テスト用コンテナにだけ置く。プラグインが自分自身を有効化／無効化できる仕組みは
 * 配布物に入れてはいけない（wp.org のガイドライン以前に、単純に危険）。
 *
 * 設置:
 *   docker cp tools/ktpwp-build-switcher.php \
 *     KantanProFree_wordpress:/var/www/html/wp-content/mu-plugins/
 *
 * できること・できないこと:
 *   ○ 有効化するプラグインの入れ替え（これが切り替えの実体）
 *   × wp.org 版のビルドし直し
 *     build-wporg.sh は python3 と rsync を使うが、コンテナには入っていない。
 *     そのため「ソースを編集したのに wp.org 版が古いまま」を検出して警告だけ出す。
 *
 * @package KTPWP_Dev
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'KTPWP_Build_Switcher' ) ) {

	class KTPWP_Build_Switcher {

		/** GitHub 版（開発ソースをバインドマウントしたもの） */
		const GITHUB = 'KantanPro/ktpwp.php';

		/** wp.org 版（build-wporg.sh の成果物） */
		const WPORG = 'kantanpro/ktpwp.php';

		const ACTION = 'ktpwp_switch_build';

		public static function boot() {
			add_action( 'admin_bar_menu', array( __CLASS__, 'add_menu' ), 100 );
			add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'handle' ) );
		}

		/** いま有効なのはどちらか。'wporg' | 'github' | '' */
		private static function current() {
			if ( ! function_exists( 'is_plugin_active' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			if ( is_plugin_active( self::WPORG ) ) {
				return 'wporg';
			}
			if ( is_plugin_active( self::GITHUB ) ) {
				return 'github';
			}
			return '';
		}

		/**
		 * wp.org 版のビルドがソースより古くないか。
		 *
		 * ソース側で一番新しい更新時刻と、ビルド成果物の ktpwp.php を比べる。
		 * 古ければ build-wporg.sh のかけ直しが要る。
		 *
		 * @return int 何秒古いか。0 なら古くない（または判定不能）。
		 */
		private static function staleness() {
			$built = WP_PLUGIN_DIR . '/kantanpro/ktpwp.php';
			$src   = WP_PLUGIN_DIR . '/KantanPro';
			if ( ! file_exists( $built ) || ! is_dir( $src ) ) {
				return 0;
			}

			$built_at = (int) filemtime( $built );
			$newest   = 0;
			$it       = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $src, FilesystemIterator::SKIP_DOTS ) );
			foreach ( $it as $file ) {
				$path = $file->getPathname();
				// ビルドに含まれないものは無視する
				if ( strpos( $path, '/.git' ) !== false || strpos( $path, '/tools/' ) !== false ) {
					continue;
				}
				$ext = strtolower( $file->getExtension() );
				if ( ! in_array( $ext, array( 'php', 'js', 'css', 'txt' ), true ) ) {
					continue;
				}
				$m = (int) $file->getMTime();
				if ( $m > $newest ) {
					$newest = $m;
				}
			}

			return $newest > $built_at ? ( $newest - $built_at ) : 0;
		}

		public static function add_menu( $bar ) {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}

			$current = self::current();
			$target  = ( 'wporg' === $current ) ? 'github' : 'wporg';

			$label = array(
				'wporg'  => 'WordPress.org 版',
				'github' => 'GitHub 版',
				''       => '（未有効）',
			);
			$color = ( 'wporg' === $current ) ? '#7bdcb5' : '#ffb900';

			$bar->add_node( array(
				'id'    => 'ktpwp-build',
				'title' => '<span style="color:' . esc_attr( $color ) . ';">●</span> '
					. esc_html( $label[ $current ] ),
				'href'  => self::switch_url( $target ),
				'meta'  => array( 'title' => 'クリックで ' . $label[ $target ] . ' に切り替えます' ),
			) );

			$bar->add_node( array(
				'parent' => 'ktpwp-build',
				'id'     => 'ktpwp-build-switch',
				'title'  => $label[ $target ] . ' に切り替える',
				'href'   => self::switch_url( $target ),
			) );

			$stale = self::staleness();
			if ( $stale > 0 ) {
				$bar->add_node( array(
					'parent' => 'ktpwp-build',
					'id'     => 'ktpwp-build-stale',
					'title'  => '⚠ wp.org 版のビルドが古い（'
						. esc_html( human_time_diff( time() - $stale, time() ) ) . '前のソースを反映していません）',
					'href'   => false,
					'meta'   => array( 'title' => 'ホストで ./tools/switch-test-build.sh wporg を実行してください' ),
				) );
			}
		}

		private static function switch_url( $target ) {
			return wp_nonce_url(
				add_query_arg(
					array(
						'action' => self::ACTION,
						'target' => $target,
						'back'   => rawurlencode( self::current_url() ),
					),
					admin_url( 'admin-post.php' )
				),
				self::ACTION
			);
		}

		private static function current_url() {
			$host = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
			$uri  = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
			return ( is_ssl() ? 'https://' : 'http://' ) . $host . $uri;
		}

		public static function handle() {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				wp_die( '権限がありません' );
			}
			check_admin_referer( self::ACTION );

			$target = isset( $_GET['target'] ) ? sanitize_key( wp_unslash( $_GET['target'] ) ) : '';
			if ( ! in_array( $target, array( 'wporg', 'github' ), true ) ) {
				wp_die( '切り替え先が不正です' );
			}

			require_once ABSPATH . 'wp-admin/includes/plugin.php';

			$on  = ( 'wporg' === $target ) ? self::WPORG : self::GITHUB;
			$off = ( 'wporg' === $target ) ? self::GITHUB : self::WPORG;

			if ( ! file_exists( WP_PLUGIN_DIR . '/' . dirname( $on ) ) ) {
				wp_die( '切り替え先のプラグインが見つかりません: ' . esc_html( dirname( $on ) ) );
			}

			// **activate_plugin() / deactivate_plugins() は使えない。**
			// activate_plugin() は $silent に関わらず plugin_sandbox_scrape() で
			// 対象ファイルを include して検証する。ところが切り替え元のコードは
			// すでにこのリクエストで読み込まれているため、同じ関数が二重定義になり
			// 「Cannot redeclare ktpwp_maybe_migrate_service_images()」で落ちる
			// （2026-09-03 に2回踏んだ）。
			//
			// どちらも同じプラグインの別ビルドで、テーブルは作成済み。
			// 有効化フックも検証も不要なので、active_plugins を直接書き換える。
			// これならどちらのファイルもこのリクエストでは読み込まれない。
			$active = (array) get_option( 'active_plugins', array() );
			$active = array_values( array_diff( $active, array( $on, $off ) ) );
			$active[] = $on;
			update_option( 'active_plugins', $active );

			$back = isset( $_GET['back'] ) ? rawurldecode( wp_unslash( $_GET['back'] ) ) : home_url( '/' );
			wp_safe_redirect( $back );
			exit;
		}
	}

	KTPWP_Build_Switcher::boot();
}
