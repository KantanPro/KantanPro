<?php
/**
 * Payment timing helper: prepay detection and label (前入金済 / EC受注)
 *
 * @package KTPWP
 * @subpackage Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class KTPWP_Payment_Timing
 */
class KTPWP_Payment_Timing {

	/**
	 * 受注の実効支払タイミングを判定する（顧客の設定をフォールバック）
	 *
	 * @param object $order 受注オブジェクト（payment_timing, client_id を持つ）
	 * @param object|null $client 顧客オブジェクト（payment_timing を持つ）。null の場合は client_id から取得を試みる
	 * @return string 'prepay' | 'postpay'
	 */
	public static function get_effective_payment_timing( $order, $client = null ) {
		$order_timing = isset( $order->payment_timing ) ? trim( (string) $order->payment_timing ) : '';
		if ( $order_timing === 'prepay' || $order_timing === 'postpay' ) {
			return $order_timing;
		}

		if ( $client === null && ! empty( $order->client_id ) ) {
			global $wpdb;
			$client = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT payment_timing FROM `{$wpdb->prefix}ktp_client` WHERE id = %d",
					(int) $order->client_id
				)
			);
		}

		$client_timing = ( $client && isset( $client->payment_timing ) ) ? trim( (string) $client->payment_timing ) : '';
		if ( $client_timing === 'prepay' || $client_timing === 'prepay_wc' ) {
			return 'prepay';
		}
		return 'postpay';
	}

	/**
	 * 前払いかどうか
	 *
	 * @param object $order 受注オブジェクト
	 * @param object|null $client 顧客オブジェクト（省略可）
	 * @return bool
	 */
	public static function is_prepay( $order, $client = null ) {
		return self::get_effective_payment_timing( $order, $client ) === 'prepay';
	}

	/**
	 * 表示用ラベルを返す（前払いでない場合は空文字）
	 * 前払い + external_source=woocommerce → 'WC受注'、その他外部連携 → 'EC受注'、前払いのみ → '前入金済'
	 *
	 * @param object $order 受注オブジェクト（payment_timing, external_source, client_id を持つ）
	 * @param object|null $client 顧客オブジェクト（省略可）
	 * @return string '' | '前入金済' | 'EC受注' | 'WC受注'
	 */
	public static function get_prepay_label( $order, $client = null ) {
		if ( ! self::is_prepay( $order, $client ) ) {
			return '';
		}
		$external = isset( $order->external_source ) ? trim( (string) $order->external_source ) : '';
		if ( $external === 'woocommerce' ) {
			return __( 'WC受注', 'ktpwp' );
		}
		if ( $external !== '' ) {
			return __( 'EC受注', 'ktpwp' );
		}
		return __( '前入金済', 'ktpwp' );
	}

	/**
	 * 受注IDから表示用ラベルを取得する（一覧などで利用）
	 *
	 * @param int $order_id 受注ID
	 * @return string '' | '前入金済' | 'EC受注'
	 */
	public static function get_prepay_label_for_order_id( $order_id ) {
		$order_id = (int) $order_id;
		if ( $order_id <= 0 ) {
			return '';
		}
		global $wpdb;
		$order_table = $wpdb->prefix . 'ktp_order';
		$client_table = $wpdb->prefix . 'ktp_client';
		$order = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT o.id, o.payment_timing, o.external_source, o.client_id FROM `{$order_table}` o WHERE o.id = %d",
				$order_id
			)
		);
		if ( ! $order ) {
			return '';
		}
		$client = null;
		if ( ! empty( $order->client_id ) ) {
			$client = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT payment_timing FROM `{$client_table}` WHERE id = %d",
					(int) $order->client_id
				)
			);
		}
		return self::get_prepay_label( $order, $client );
	}
}
