<?php
/**
 * 定期契約の請求サイクル定義
 *
 * @package KTPWP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'KTPWP_Contract_Billing_Cycle' ) ) {

	/**
	 * サービス・契約で共有する請求サイクル定義。
	 */
	class KTPWP_Contract_Billing_Cycle {

		/** @var string 都度請求（定期契約対象外） */
		public const NONE = 'none';

		/** @var string 毎月 */
		public const MONTHLY = 'monthly';

		/** @var string 2ヶ月に1回 */
		public const BIMONTHLY = 'bimonthly';

		/** @var string 3ヶ月に1回 */
		public const QUARTERLY = 'quarterly';

		/** @var string 6ヶ月に1回 */
		public const SEMIANNUAL = 'semiannual';

		/** @var string 毎年 */
		public const ANNUAL = 'annual';

		/**
		 * 選択肢（value => ラベル）
		 *
		 * @return array<string, string>
		 */
		public static function get_options() {
			return array(
				self::NONE       => __( '都度請求', 'ktpwp' ),
				self::MONTHLY    => __( '毎月', 'ktpwp' ),
				self::BIMONTHLY  => __( '2ヶ月に1回', 'ktpwp' ),
				self::QUARTERLY  => __( '3ヶ月に1回', 'ktpwp' ),
				self::SEMIANNUAL => __( '6ヶ月に1回', 'ktpwp' ),
				self::ANNUAL     => __( '毎年', 'ktpwp' ),
			);
		}

		/**
		 * 定期契約に使えるサイクルのみ（都度を除く）
		 *
		 * @return array<string, string>
		 */
		public static function get_recurring_options() {
			$options = self::get_options();
			unset( $options[ self::NONE ] );

			return $options;
		}

		/**
		 * 値を正規化する。
		 *
		 * @param mixed $value 入力値。
		 * @return string
		 */
		public static function sanitize( $value ) {
			$value   = is_string( $value ) ? sanitize_key( $value ) : self::NONE;
			$allowed = array_keys( self::get_options() );

			return in_array( $value, $allowed, true ) ? $value : self::NONE;
		}

		/**
		 * 表示ラベルを返す。
		 *
		 * @param string $value サイクル値。
		 * @return string
		 */
		public static function get_label( $value ) {
			$value   = self::sanitize( $value );
			$options = self::get_options();

			return $options[ $value ] ?? $options[ self::NONE ];
		}

		/**
		 * 定期契約対象かどうか。
		 *
		 * @param string $value サイクル値。
		 * @return bool
		 */
		public static function is_recurring( $value ) {
			return self::sanitize( $value ) !== self::NONE;
		}

		/**
		 * 請求サイクルに対応する月数（都度は 0）。
		 *
		 * @param string $value サイクル値。
		 * @return int
		 */
		public static function get_interval_months( $value ) {
			switch ( self::sanitize( $value ) ) {
				case self::MONTHLY:
					return 1;
				case self::BIMONTHLY:
					return 2;
				case self::QUARTERLY:
					return 3;
				case self::SEMIANNUAL:
					return 6;
				case self::ANNUAL:
					return 12;
				default:
					return 0;
			}
		}

		/**
		 * リスト用バッジ HTML。
		 *
		 * @param string $value サイクル値。
		 * @return string
		 */
		public static function render_badge( $value ) {
			$value = self::sanitize( $value );
			$label = self::get_label( $value );

			if ( ! self::is_recurring( $value ) ) {
				return '<span class="ktp-contract-cycle-badge ktp-contract-cycle-badge--none" title="' . esc_attr( $label ) . '">'
					. esc_html( $label )
					. '</span>';
			}

			return '<span class="ktp-contract-cycle-badge ktp-contract-cycle-badge--recurring" title="' . esc_attr( $label ) . '">'
				. esc_html( $label )
				. '</span>';
		}
	}
}
