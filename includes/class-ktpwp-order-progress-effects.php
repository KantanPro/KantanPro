<?php
/**
 * 案件進捗変更に伴う副作用（ボツ時の契約解消など）
 *
 * @package KTPWP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'KTPWP_Order_Progress_Effects' ) ) {

	/**
	 * 進捗更新後の連動処理。
	 */
	class KTPWP_Order_Progress_Effects {

		/**
		 * 進捗更新後に呼ぶ。
		 *
		 * @param int $order_id      案件 ID。
		 * @param int $new_progress  新しい進捗。
		 * @return void
		 */
		public static function after_progress_updated( $order_id, $new_progress ) {
			if ( (int) $new_progress !== 7 ) {
				return;
			}

			self::handle_rejected_order( $order_id );
		}

		/**
		 * ボツ（progress=7）になった案件の後処理。
		 *
		 * @param int $order_id 案件 ID。
		 * @return void
		 */
		private static function handle_rejected_order( $order_id ) {
			global $wpdb;

			$order_id = absint( $order_id );
			if ( $order_id <= 0 ) {
				return;
			}

			$order_table = $wpdb->prefix . 'ktp_order';
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$order = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT id, contract_id FROM {$order_table} WHERE id = %d",
					$order_id
				)
			);

			if ( ! $order ) {
				return;
			}

			$contract_ids = array();

			if ( isset( $order->contract_id ) && (int) $order->contract_id > 0 ) {
				$contract_ids[ (int) $order->contract_id ] = true;
			}

			if ( class_exists( 'KTPWP_Contract_DB' ) ) {
				$db = KTPWP_Contract_DB::get_instance();
				if ( $db->tables_exist() ) {
					$log_table = $db->get_billing_log_table_name();
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$log_contract_ids = $wpdb->get_col(
						$wpdb->prepare(
							"SELECT DISTINCT contract_id FROM {$log_table} WHERE order_id = %d AND contract_id > 0",
							$order_id
						)
					);

					if ( is_array( $log_contract_ids ) ) {
						foreach ( $log_contract_ids as $contract_id ) {
							$contract_id = absint( $contract_id );
							if ( $contract_id > 0 ) {
								$contract_ids[ $contract_id ] = true;
							}
						}
					}
				}
			}

			foreach ( array_keys( $contract_ids ) as $contract_id ) {
				self::cancel_contract_for_rejected_order( (int) $contract_id );
			}
		}

		/**
		 * ボツ案件に紐づく契約を解約する。
		 *
		 * @param int $contract_id 契約 ID。
		 * @return void
		 */
		private static function cancel_contract_for_rejected_order( $contract_id ) {
			global $wpdb;

			$contract_id = absint( $contract_id );
			if ( $contract_id <= 0 || ! class_exists( 'KTPWP_Contract_DB' ) ) {
				return;
			}

			$db = KTPWP_Contract_DB::get_instance();
			if ( ! $db->tables_exist() ) {
				return;
			}

			$contract = $db->get_contract_by_id( $contract_id );
			if ( ! $contract ) {
				return;
			}

			$status = isset( $contract->status ) ? sanitize_key( (string) $contract->status ) : '';
			if ( ! in_array( $status, array( 'active', 'paused' ), true ) ) {
				return;
			}

			$table = $db->get_contract_table_name();
			$wpdb->update(
				$table,
				array( 'status' => 'cancelled' ),
				array( 'id' => $contract_id ),
				array( '%s' ),
				array( '%d' )
			);

			$service_id = isset( $contract->service_id ) ? (int) $contract->service_id : 0;
			if ( $service_id > 0 && class_exists( 'KTPWP_Contract_Service_Public_Availability' ) ) {
				KTPWP_Contract_Service_Public_Availability::close_stale_public_inquiries_for_service( $service_id );
				KTPWP_Contract_Service_Public_Availability::sync_for_service( $service_id );
			}
		}
	}
}
