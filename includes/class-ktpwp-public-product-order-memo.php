<?php
/**
 * 公開商品 Web 申込案件のメモ欄解析
 *
 * @package KTPWP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'KTPWP_Public_Product_Order_Memo' ) ) {

	/**
	 * 受注書メモから Web 申込・商品 ID を判別する。
	 */
	class KTPWP_Public_Product_Order_Memo {

		/** @var string Web 申込サフィックス */
		public const WEB_SUFFIX = '（Webお申込み）';

		/**
		 * Web 申込案件かどうか。
		 *
		 * @param string|null $memo メモ。
		 * @return bool
		 */
		public static function is_web_application( $memo ) {
			return is_string( $memo ) && $memo !== '' && str_contains( $memo, self::WEB_SUFFIX );
		}

		/**
		 * メモから商品（サービス）ID を抽出する。
		 *
		 * @param string|null $memo メモ。
		 * @return int|null
		 */
		public static function parse_service_id( $memo ) {
			if ( ! is_string( $memo ) || $memo === '' ) {
				return null;
			}

			if ( preg_match( '/商品ID:\s*(\d+)/u', $memo, $matches ) !== 1 ) {
				return null;
			}

			$service_id = (int) $matches[1];

			return $service_id > 0 ? $service_id : null;
		}
	}
}
