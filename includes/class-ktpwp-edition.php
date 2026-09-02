<?php
/**
 * KantanPro エディション（無料版の機能制限・EX 移行案内）
 *
 * @package KantanPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * エディション別の制限を管理するクラス
 */
class KTPWP_Edition {

	/**
	 * 無料版で無効な機能キー（solo / EX 有料版では有効）
	 *
	 * @return string[]
	 */
	public static function get_free_disabled_features() {
		return array(
			'report',
			'backup',
			'order_auxiliary',
			'stripe_billing',
			'contract_invoice_auto_mail',
			'public_products',
			'contracts',
		);
	}

	/**
	 * エディション定義（無料版 KantanPro WP 用）
	 *
	 * @return array<string, array{slug: string, plugin_name: string, staff_limit: int, label: string}>
	 */
	public static function get_definitions() {
		return array(
			'free' => array(
				'slug'        => 'free',
				'plugin_name' => 'KantanPro(WP)',
				'staff_limit' => 0, // 0 = スタッフ追加不可（管理者のみ）。有料 pro の 0（無制限）とは別扱い。
				'label'       => __( '無料版', 'kantanpro' ),
			),
		);
	}

	/**
	 * 有効なエディション slug 一覧
	 *
	 * @return string[]
	 */
	public static function get_valid_slugs() {
		return array_keys( self::get_definitions() );
	}

	/**
	 * 現在有効なエディション slug
	 *
	 * @return string
	 */
	public static function get_active_edition() {
		$edition = defined( 'KTPWP_EDITION' ) ? (string) KTPWP_EDITION : 'free';

		if ( self::is_developer_override_enabled() ) {
			$override = get_option( 'ktp_developer_edition_override', '' );
			if ( is_string( $override ) && $override !== '' && isset( self::get_definitions()[ $override ] ) ) {
				$edition = $override;
			}
		}

		if ( ! isset( self::get_definitions()[ $edition ] ) ) {
			return 'free';
		}

		return $edition;
	}

	/**
	 * 開発環境でエディション上書きが有効か
	 *
	 * @return bool
	 */
	public static function is_developer_override_enabled() {
		if ( defined( 'KTPWP_DEVELOPMENT_MODE' ) && KTPWP_DEVELOPMENT_MODE ) {
			return true;
		}
		if ( defined( 'KANTANPRO_DEV_MODE' ) && KANTANPRO_DEV_MODE ) {
			return true;
		}

		$host = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
		if ( $host === '' ) {
			return false;
		}

		return in_array( $host, array( 'localhost', '127.0.0.1' ), true )
			|| strpos( $host, '.local' ) !== false
			|| strpos( $host, '.test' ) !== false
			|| strpos( $host, '.dev' ) !== false
			|| strpos( $host, 'localhost:' ) !== false
			|| strpos( $host, '127.0.0.1:' ) !== false
			|| ( defined( 'WP_ENV' ) && WP_ENV === 'development' );
	}

	/**
	 * エディション表示名
	 *
	 * @param string|null $edition 省略時は現在有効なエディション
	 * @return string
	 */
	public static function get_edition_label( $edition = null ) {
		$edition     = $edition ?? self::get_active_edition();
		$definitions = self::get_definitions();

		return isset( $definitions[ $edition ] ) ? $definitions[ $edition ]['label'] : $edition;
	}

	/**
	 * プラグイン表示名
	 *
	 * @return string
	 */
	public static function get_plugin_name() {
		if ( defined( 'KANTANPRO_PLUGIN_NAME' ) && KANTANPRO_PLUGIN_NAME !== '' ) {
			return (string) KANTANPRO_PLUGIN_NAME;
		}

		return 'KantanPro(WP)';
	}

	/**
	 * GitHub 更新チェック用リポジトリ（owner/repo）
	 *
	 * @return string
	 */
	public static function get_github_repo() {
		return 'KantanPro/KantanPro';
	}

	/**
	 * プラグインディレクトリの正規名
	 *
	 * @return string
	 */
	public static function get_canonical_plugin_dir() {
		return 'KantanPro(WP)';
	}

	/**
	 * スタッフ上限（無料版の 0 = 追加不可。有料 pro の 0 = 無制限）
	 *
	 * @return int
	 */
	public static function get_staff_limit() {
		if ( defined( 'KTPWP_STAFF_LIMIT' ) ) {
			return max( 0, (int) KTPWP_STAFF_LIMIT );
		}

		$edition     = self::get_active_edition();
		$definitions = self::get_definitions();

		if ( ! isset( $definitions[ $edition ] ) ) {
			return 0;
		}

		return max( 0, (int) $definitions[ $edition ]['staff_limit'] );
	}

	/**
	 * KantanProEX 有料 EX 系エディションか
	 *
	 * @return bool
	 */
	public static function is_ex_edition() {
		return false;
	}

	/**
	 * 有料エディションか
	 *
	 * @return bool
	 */
	public static function is_paid_edition() {
		return false;
	}

	/**
	 * 販売ページ URL
	 *
	 * @return string
	 */
	public static function get_store_url() {
		return 'https://www.kantanpro.com/product/kantanpro-ex';
	}

	/**
	 * エディション別機能フラグ
	 *
	 * @param string $feature 機能キー
	 * @return bool
	 */
	public static function is_feature_enabled( $feature ) {
		$feature = sanitize_key( (string) $feature );
		if ( $feature === '' ) {
			return true;
		}

		return ! in_array( $feature, self::get_free_disabled_features(), true );
	}

	/**
	 * バナー（ktp-banner）を隠すか
	 *
	 * @return bool
	 */
	public static function should_hide_banner() {
		return self::is_paid_edition();
	}

	/**
	 * 機能無効時のメッセージ（販売リンク付き）
	 *
	 * @param string $feature_name 機能名
	 * @return string
	 */
	public static function get_upgrade_message_html( $feature_name ) {
		$feature_name = sanitize_text_field( (string) $feature_name );
		$store_url    = esc_url( self::get_store_url() );

		return '<div class="ktpwp-notice ktp-report-free-ex-notice" style="background:#fff7e6;border:1px solid #ffd591;border-radius:6px;color:#8a6d3b;padding:12px 16px;margin:12px 0;">'
			. '<div class="ktp-ex-migrate-one-line">'
			. '<span>' . esc_html(
				sprintf(
					/* translators: %s: feature name */
					__( '%s は無料版では利用できません。', 'kantanpro' ),
					$feature_name
				)
			) . ' ' . esc_html__( 'KantanProEX（有料版）へ移行してご利用ください。', 'kantanpro' ) . '</span> '
			. '<a class="button button-primary" href="' . $store_url . '" target="_blank" rel="noopener noreferrer">'
			. esc_html__( 'KantanProEX 商品ページ', 'kantanpro' )
			. '</a></div></div>';
	}

	/**
	 * 無料版向け EX 移行メッセージ（管理画面）
	 *
	 * @param string $feature_name 機能名
	 * @return string
	 */
	public static function get_admin_moved_to_ex_message_html( $feature_name ) {
		$feature_name = sanitize_text_field( (string) $feature_name );
		$store_url    = esc_url( self::get_store_url() );

		return '<div class="wrap"><h1>' . esc_html( $feature_name ) . '</h1>'
			. '<div class="notice notice-warning"><p>'
			. esc_html(
				sprintf(
					/* translators: %s: feature name */
					__( '%s は無料版では利用できません。', 'kantanpro' ),
					$feature_name
				)
			)
			. '<br />' . esc_html__( 'KantanProEX（有料版）へ移行してご利用ください。', 'kantanpro' )
			. '</p><p><a class="button button-primary" href="' . $store_url . '" target="_blank" rel="noopener noreferrer">'
			. esc_html__( 'KantanProEX 商品ページ', 'kantanpro' )
			. '</a></p></div></div>';
	}

	/**
	 * ktpwp_access 権限を持つスタッフ数（管理者は含めない）
	 *
	 * @return int
	 */
	public static function count_staff_users() {
		$users = get_users(
			array(
				'role__not_in' => array( 'administrator' ),
				'fields'       => 'ID',
			)
		);

		$count = 0;
		foreach ( $users as $user_id ) {
			if ( user_can( (int) $user_id, 'ktpwp_access' ) ) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * スタッフを追加できるか
	 *
	 * @return bool
	 */
	public static function can_add_staff() {
		$limit   = self::get_staff_limit();
		$edition = self::get_active_edition();

		if ( $edition === 'free' && $limit === 0 ) {
			return false;
		}

		if ( $limit <= 0 ) {
			return true;
		}

		return self::count_staff_users() < $limit;
	}

	/**
	 * スタッフ上限の表示用文字列
	 *
	 * @return string
	 */
	public static function format_staff_limit_display() {
		$limit   = self::get_staff_limit();
		$edition = self::get_active_edition();

		if ( $edition === 'free' && $limit === 0 ) {
			return __( '追加不可（管理者のみ）', 'kantanpro' );
		}

		return $limit > 0 ? (string) $limit : __( '無制限', 'kantanpro' );
	}

	/**
	 * スタッフ上限到達時のメッセージ
	 *
	 * @return string
	 */
	public static function get_staff_limit_reached_message() {
		$limit         = self::get_staff_limit();
		$edition_label = self::get_edition_label();

		if ( self::get_active_edition() === 'free' && $limit === 0 ) {
			return __( '無料版ではスタッフを追加できません。管理者のみご利用いただけます。チームで使う場合は KantanProEX（有料版）をご検討ください。', 'kantanpro' );
		}

		return sprintf(
			/* translators: 1: edition label, 2: staff limit number */
			__( '%1$sではスタッフは最大%2$d人まで登録できます。上限に達しているため、これ以上スタッフを追加できません。', 'kantanpro' ),
			$edition_label,
			$limit
		);
	}
}

if ( ! function_exists( 'ktpwp_is_ex_edition' ) ) {
	/**
	 * KantanPro 有料 EX 系エディションか
	 *
	 * @return bool
	 */
	function ktpwp_is_ex_edition() {
		return class_exists( 'KTPWP_Edition' ) && KTPWP_Edition::is_ex_edition();
	}
}

if ( ! function_exists( 'ktpwp_should_hide_ktp_banner' ) ) {
	/**
	 * KTP バナーを隠すか
	 *
	 * @return bool
	 */
	function ktpwp_should_hide_ktp_banner() {
		return class_exists( 'KTPWP_Edition' ) && KTPWP_Edition::should_hide_banner();
	}
}

if ( ! function_exists( 'ktpwp_is_feature_enabled' ) ) {
	/**
	 * エディションごとの機能フラグ
	 *
	 * @param string $feature 機能キー
	 * @return bool
	 */
	function ktpwp_is_feature_enabled( $feature ) {
		if ( ! class_exists( 'KTPWP_Edition' ) ) {
			return true;
		}

		return KTPWP_Edition::is_feature_enabled( $feature );
	}
}

if ( ! function_exists( 'ktpwp_contracts_feature_enabled' ) ) {
	/**
	 * 定期契約機能が利用可能か
	 *
	 * @return bool
	 */
	function ktpwp_contracts_feature_enabled() {
		return ktpwp_is_feature_enabled( 'contracts' );
	}
}

if ( ! function_exists( 'ktpwp_require_contracts_feature_or_ajax_error' ) ) {
	/**
	 * 定期契約 AJAX: 無料版では JSON エラーで終了
	 *
	 * @return void
	 */
	function ktpwp_require_contracts_feature_or_ajax_error() {
		if ( ! ktpwp_contracts_feature_enabled() ) {
			wp_send_json_error(
				class_exists( 'KTPWP_Edition' )
					? wp_strip_all_tags( KTPWP_Edition::get_upgrade_message_html( __( '定期契約', 'kantanpro' ) ) )
					: __( 'この機能は有料版で利用できます。', 'kantanpro' )
			);
		}
	}
}
