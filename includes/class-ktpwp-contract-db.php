<?php
/**
 * 定期契約 DB 管理
 *
 * @package KTPWP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'KTPWP_Contract_DB' ) ) {

	/**
	 * 定期契約テーブルの作成・参照。
	 */
	class KTPWP_Contract_DB {

		/** @var self|null */
		private static $instance = null;

		/**
		 * @return self
		 */
		public static function get_instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		private function __construct() {}

		/**
		 * 契約テーブル名
		 *
		 * @return string
		 */
		public function get_contract_table_name() {
			global $wpdb;

			return $wpdb->prefix . 'ktp_contract';
		}

		/**
		 * 初回費用テーブル名
		 *
		 * @return string
		 */
		public function get_initial_fee_table_name() {
			global $wpdb;

			return $wpdb->prefix . 'ktp_contract_initial_fee';
		}

		/**
		 * 請求ログテーブル名
		 *
		 * @return string
		 */
		public function get_billing_log_table_name() {
			global $wpdb;

			return $wpdb->prefix . 'ktp_contract_billing_log';
		}

		/**
		 * テーブルが存在するか
		 *
		 * @return bool
		 */
		public function tables_exist() {
			global $wpdb;

			$table = $this->get_contract_table_name();

			return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
		}

		/**
		 * 顧客に紐づく契約一覧
		 *
		 * @param int $client_id 顧客 ID。
		 * @return array<int, object>
		 */
		public function get_contracts_by_client_id( $client_id ) {
			global $wpdb;

			$client_id = absint( $client_id );
			if ( $client_id <= 0 || ! $this->tables_exist() ) {
				return array();
			}

			$table = $this->get_contract_table_name();

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE client_id = %d ORDER BY id DESC",
					$client_id
				)
			);

			return is_array( $rows ) ? $rows : array();
		}

		/**
		 * 初回請求の追加費用一覧
		 *
		 * @param int $contract_id 契約 ID。
		 * @return array<int, object>
		 */
		public function get_initial_fees_by_contract_id( $contract_id ) {
			global $wpdb;

			$contract_id = absint( $contract_id );
			if ( $contract_id <= 0 || ! $this->tables_exist() ) {
				return array();
			}

			$table = $this->get_initial_fee_table_name();

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE contract_id = %d ORDER BY sort_order ASC, id ASC",
					$contract_id
				)
			);

			return is_array( $rows ) ? $rows : array();
		}

		/**
		 * 初回費用のプリセット名目
		 *
		 * @return array<int, string>
		 */
		public static function get_initial_fee_presets() {
			return array(
				__( '保証金', 'kantanpro' ),
				__( '敷金', 'kantanpro' ),
				__( '礼金', 'kantanpro' ),
				__( '初期設定費用', 'kantanpro' ),
				__( '入会金', 'kantanpro' ),
			);
		}

		/**
		 * 契約 ID で1件取得
		 *
		 * @param int $contract_id 契約 ID。
		 * @return object|null
		 */
		public function get_contract_by_id( $contract_id ) {
			global $wpdb;

			$contract_id = absint( $contract_id );
			if ( $contract_id <= 0 || ! $this->tables_exist() ) {
				return null;
			}

			$table = $this->get_contract_table_name();

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE id = %d",
					$contract_id
				)
			);

			return $row ? $row : null;
		}

		/**
		 * 定期請求に使えるサービス一覧
		 *
		 * @return array<int, object>
		 */
		public function get_recurring_services() {
			global $wpdb;

			$table = $wpdb->prefix . 'ktp_service';
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
				return array();
			}

			$columns = $wpdb->get_col( "SHOW COLUMNS FROM `{$table}`" );
			if ( ! is_array( $columns ) || ! in_array( 'contract_billing_cycle', $columns, true ) ) {
				return array();
			}

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->get_results(
				"SELECT id, service_name, price, tax_rate, unit, contract_billing_cycle
				FROM {$table}
				WHERE contract_billing_cycle IS NOT NULL
				AND contract_billing_cycle <> 'none'
				ORDER BY service_name ASC"
			);

			return is_array( $rows ) ? $rows : array();
		}

		/**
		 * 契約を保存（新規・更新）
		 *
		 * @param array<string, mixed>       $data         契約データ。
		 * @param array<int, array<string, mixed>> $initial_fees 初回費用行。
		 * @param array<int, array<string, mixed>> $recurring_items 定期請求明細行。
		 * @return int|\WP_Error 契約 ID またはエラー。
		 */
		public function save_contract( $data, $initial_fees = array(), $recurring_items = array() ) {
			global $wpdb;

			if ( ! $this->tables_exist() ) {
				return new WP_Error( 'no_tables', __( '定期契約テーブルが存在しません。', 'kantanpro' ) );
			}

			$client_id = absint( $data['client_id'] ?? 0 );
			$service_id = absint( $data['service_id'] ?? 0 );
			$contract_name = sanitize_text_field( $data['contract_name'] ?? '' );

			if ( $client_id <= 0 || $service_id <= 0 || $contract_name === '' ) {
				return new WP_Error( 'invalid_input', __( '契約名とサービスを入力してください。', 'kantanpro' ) );
			}

			if ( ! $this->client_exists( $client_id ) ) {
				return new WP_Error( 'invalid_client', __( '顧客が見つかりません。', 'kantanpro' ) );
			}

			$contract_id = absint( $data['id'] ?? 0 );
			$existing    = null;
			$previous_service_id = 0;

			if ( $contract_id > 0 ) {
				$existing = $this->get_contract_by_id( $contract_id );
				if ( ! $existing || (int) $existing->client_id !== $client_id ) {
					return new WP_Error( 'not_found', __( '契約が見つかりません。', 'kantanpro' ) );
				}

				$previous_service_id = (int) $existing->service_id;

				if ( (int) $existing->first_billed === 1 ) {
					$service_id = (int) $existing->service_id;
				}
			}

			if ( ! $this->is_valid_recurring_service( $service_id ) ) {
				return new WP_Error( 'invalid_service', __( '定期請求に利用できるサービスを選択してください。', 'kantanpro' ) );
			}

			$billing_cycle = class_exists( 'KTPWP_Contract_Billing_Cycle' )
				? KTPWP_Contract_Billing_Cycle::sanitize( $data['billing_cycle'] ?? '' )
				: 'monthly';

			if ( $existing && (int) $existing->first_billed === 1 ) {
				$billing_cycle = KTPWP_Contract_Billing_Cycle::sanitize( $existing->billing_cycle );
			}

			if ( ! class_exists( 'KTPWP_Contract_Billing_Cycle' ) || ! KTPWP_Contract_Billing_Cycle::is_recurring( $billing_cycle ) ) {
				return new WP_Error( 'invalid_cycle', __( '請求サイクルが不正です。', 'kantanpro' ) );
			}

			$billing_day = absint( $data['billing_day'] ?? 1 );
			if ( $billing_day < 1 || ( $billing_day > 28 && 99 !== $billing_day ) ) {
				$billing_day = 1;
			}

			$status = sanitize_key( $data['status'] ?? 'active' );
			if ( ! in_array( $status, array( 'active', 'paused', 'cancelled' ), true ) ) {
				$status = 'active';
			}

			$start_date = $this->sanitize_date( $data['start_date'] ?? '' );
			$end_date   = $this->sanitize_date( $data['end_date'] ?? '' );
			$amount     = isset( $data['amount'] ) ? floatval( $data['amount'] ) : 0.0;
			$normalized_recurring = class_exists( 'KTPWP_Contract_Recurring_Items' )
				? KTPWP_Contract_Recurring_Items::normalize_rows( $recurring_items )
				: array();

			if ( ! $existing || (int) $existing->first_billed === 0 ) {
				if ( empty( $normalized_recurring ) && class_exists( 'KTPWP_Contract_Recurring_Items' ) ) {
					$service_items = KTPWP_Contract_Recurring_Items::get_by_service_id( $service_id );
					foreach ( $service_items as $service_item ) {
						$normalized_recurring[] = array(
							'item_name' => (string) $service_item->item_name,
							'amount'    => (float) $service_item->amount,
							'tax_rate'  => isset( $service_item->tax_rate ) && $service_item->tax_rate !== null
								? (float) $service_item->tax_rate
								: null,
						);
					}
				}

				if ( ! empty( $normalized_recurring ) ) {
					$amount = class_exists( 'KTPWP_Contract_Recurring_Items' )
						? KTPWP_Contract_Recurring_Items::total_amount( $normalized_recurring )
						: $amount;
				}
			} elseif ( $existing ) {
				$amount = (float) $existing->amount;
			}

			$memo       = sanitize_textarea_field( $data['memo'] ?? '' );
			$send_reminder = ! empty( $data['send_reminder_mail'] ) ? 1 : 0;
			$payment_due_mode = self::sanitize_payment_due_mode( $data['payment_due_mode'] ?? 'contract' );

			$table = $this->get_contract_table_name();

			$row_data = array(
				'client_id'          => $client_id,
				'service_id'         => $service_id,
				'contract_name'      => $contract_name,
				'amount'             => $amount,
				'billing_cycle'      => $billing_cycle,
				'billing_day'        => $billing_day,
				'start_date'         => $start_date,
				'end_date'           => $end_date,
				'status'             => $status,
				'send_reminder_mail' => $send_reminder,
				'memo'               => $memo,
			);

			$row_format = array( '%d', '%d', '%s', '%f', '%s', '%d', '%s', '%s', '%s', '%d', '%s' );

			if ( $this->table_has_column( 'payment_due_mode' ) ) {
				$row_data['payment_due_mode'] = $payment_due_mode;
				$row_format[]                 = '%s';
			}

			if ( $contract_id > 0 ) {
				$result = $wpdb->update(
					$table,
					$row_data,
					array( 'id' => $contract_id ),
					$row_format,
					array( '%d' )
				);

				if ( false === $result ) {
					return new WP_Error( 'update_failed', __( '契約の更新に失敗しました。', 'kantanpro' ) );
				}
			} else {
				$row_data['first_billed'] = 0;
				$row_format[]             = '%d';

				$result = $wpdb->insert( $table, $row_data, $row_format );
				if ( false === $result ) {
					return new WP_Error( 'insert_failed', __( '契約の追加に失敗しました。', 'kantanpro' ) );
				}
				$contract_id = (int) $wpdb->insert_id;
			}

			$existing = $this->get_contract_by_id( $contract_id );
			if ( $existing && (int) $existing->first_billed === 0 ) {
				$this->replace_initial_fees( $contract_id, $initial_fees );
				if ( class_exists( 'KTPWP_Contract_Recurring_Items' ) ) {
					KTPWP_Contract_Recurring_Items::replace_for_contract( $contract_id, $normalized_recurring );
				}
			}

			if ( class_exists( 'KTPWP_Contract_Service_Public_Availability' ) ) {
				if ( 'active' === $status ) {
					KTPWP_Contract_Service_Public_Availability::close_stale_public_inquiries_for_service( $service_id );
				}
				KTPWP_Contract_Service_Public_Availability::sync_for_service( $service_id );
				if ( $previous_service_id > 0 && $previous_service_id !== $service_id ) {
					KTPWP_Contract_Service_Public_Availability::sync_for_service( $previous_service_id );
				}
			}

			if ( $existing && isset( $existing->status ) && $existing->status !== $status && class_exists( 'KTPWP_Stripe_Billing' ) ) {
				KTPWP_Stripe_Billing::get_instance()->on_contract_status_changed( $contract_id, (string) $existing->status, $status );
			}

			return $contract_id;
		}

		/**
		 * 顧客が存在するか
		 *
		 * @param int $client_id 顧客 ID。
		 * @return bool
		 */
		private function client_exists( $client_id ) {
			global $wpdb;

			$client_id = absint( $client_id );
			if ( $client_id <= 0 ) {
				return false;
			}

			$table = $wpdb->prefix . 'ktp_client';
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
				return false;
			}

			$found = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$table} WHERE id = %d",
					$client_id
				)
			);

			return (int) $found === $client_id;
		}

		/**
		 * 定期請求に利用できるサービスか
		 *
		 * @param int $service_id サービス ID。
		 * @return bool
		 */
		private function is_valid_recurring_service( $service_id ) {
			global $wpdb;

			$service_id = absint( $service_id );
			if ( $service_id <= 0 ) {
				return false;
			}

			$table = $wpdb->prefix . 'ktp_service';
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
				return false;
			}

			$columns = $wpdb->get_col( "SHOW COLUMNS FROM `{$table}`" );
			if ( ! is_array( $columns ) || ! in_array( 'contract_billing_cycle', $columns, true ) ) {
				return false;
			}

			$cycle = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT contract_billing_cycle FROM {$table} WHERE id = %d",
					$service_id
				)
			);

			return class_exists( 'KTPWP_Contract_Billing_Cycle' )
				&& KTPWP_Contract_Billing_Cycle::is_recurring( (string) $cycle );
		}

		/**
		 * 契約を削除
		 *
		 * @param int $contract_id 契約 ID。
		 * @param int $client_id   顧客 ID（所有確認）。
		 * @return bool|\WP_Error
		 */
		public function delete_contract( $contract_id, $client_id ) {
			global $wpdb;

			$contract_id = absint( $contract_id );
			$client_id   = absint( $client_id );

			$contract = $this->get_contract_by_id( $contract_id );
			if ( ! $contract || (int) $contract->client_id !== $client_id ) {
				return new WP_Error( 'not_found', __( '契約が見つかりません。', 'kantanpro' ) );
			}

			$service_id = (int) $contract->service_id;

			$fee_table = $this->get_initial_fee_table_name();
			$wpdb->delete( $fee_table, array( 'contract_id' => $contract_id ), array( '%d' ) );

			if ( class_exists( 'KTPWP_Contract_Recurring_Items' ) && KTPWP_Contract_Recurring_Items::tables_exist() ) {
				$wpdb->delete(
					KTPWP_Contract_Recurring_Items::contract_table_name(),
					array( 'contract_id' => $contract_id ),
					array( '%d' )
				);
			}

			$log_table = $this->get_billing_log_table_name();
			$wpdb->delete( $log_table, array( 'contract_id' => $contract_id ), array( '%d' ) );

			$table  = $this->get_contract_table_name();
			$result = $wpdb->delete( $table, array( 'id' => $contract_id ), array( '%d' ) );

			if ( false === $result ) {
				return new WP_Error( 'delete_failed', __( '契約の削除に失敗しました。', 'kantanpro' ) );
			}

			if ( $service_id > 0 && class_exists( 'KTPWP_Contract_Service_Public_Availability' ) ) {
				KTPWP_Contract_Service_Public_Availability::sync_for_service( $service_id );
			}

			return true;
		}

		/**
		 * Ajax 用：契約＋初回費用を配列で返す
		 *
		 * @param int $contract_id 契約 ID。
		 * @return array<string, mixed>|null
		 */
		public function get_contract_payload( $contract_id ) {
			$contract = $this->get_contract_by_id( $contract_id );
			if ( ! $contract ) {
				return null;
			}

			$fees = $this->get_initial_fees_by_contract_id( $contract_id );
			$fee_rows = array();

			foreach ( $fees as $fee ) {
				$fee_rows[] = array(
					'fee_name' => $fee->fee_name,
					'amount'   => (float) $fee->amount,
					'tax_rate' => $fee->tax_rate !== null ? (float) $fee->tax_rate : '',
				);
			}

			return array(
				'id'                 => (int) $contract->id,
				'client_id'          => (int) $contract->client_id,
				'service_id'         => (int) $contract->service_id,
				'contract_name'      => $contract->contract_name,
				'amount'             => (float) $contract->amount,
				'billing_cycle'      => $contract->billing_cycle,
				'billing_day'        => (int) $contract->billing_day,
				'payment_due_mode'   => self::sanitize_payment_due_mode( $contract->payment_due_mode ?? 'contract' ),
				'start_date'         => $contract->start_date,
				'end_date'           => $contract->end_date,
				'status'             => $contract->status,
				'send_reminder_mail' => (int) $contract->send_reminder_mail,
				'memo'               => $contract->memo,
				'first_billed'       => (int) $contract->first_billed,
				'initial_fees'       => $fee_rows,
				'recurring_items'    => class_exists( 'KTPWP_Contract_Recurring_Items' )
					? KTPWP_Contract_Recurring_Items::rows_to_payload(
						KTPWP_Contract_Recurring_Items::get_by_contract_id( $contract_id )
					)
					: array(),
			);
		}

		/**
		 * 初回費用を全置換
		 *
		 * @param int                              $contract_id  契約 ID。
		 * @param array<int, array<string, mixed>> $initial_fees 初回費用行。
		 * @return void
		 */
		private function replace_initial_fees( $contract_id, $initial_fees ) {
			global $wpdb;

			$contract_id = absint( $contract_id );
			$table       = $this->get_initial_fee_table_name();

			$wpdb->delete( $table, array( 'contract_id' => $contract_id ), array( '%d' ) );

			if ( ! is_array( $initial_fees ) ) {
				return;
			}

			$sort = 0;
			foreach ( $initial_fees as $fee ) {
				$fee_name = sanitize_text_field( $fee['fee_name'] ?? '' );
				$amount   = floatval( $fee['amount'] ?? 0 );

				if ( $fee_name === '' || $amount <= 0 ) {
					continue;
				}

				$tax_rate = isset( $fee['tax_rate'] ) && $fee['tax_rate'] !== '' ? floatval( $fee['tax_rate'] ) : null;

				$wpdb->insert(
					$table,
					array(
						'contract_id' => $contract_id,
						'fee_name'    => $fee_name,
						'amount'      => $amount,
						'tax_rate'    => $tax_rate,
						'sort_order'  => $sort,
					),
					array( '%d', '%s', '%f', '%f', '%d' )
				);
				++$sort;
			}
		}

		/**
		 * 入金期日の参照先選択肢
		 *
		 * @return array<string, string>
		 */
		public static function get_payment_due_mode_options() {
			return array(
				'contract' => __( '契約の請求日', 'kantanpro' ),
				'client'   => __( '顧客の締め支払日', 'kantanpro' ),
			);
		}

		/**
		 * 入金期日の参照先を正規化
		 *
		 * @param mixed $value 入力値。
		 * @return string contract|client
		 */
		public static function sanitize_payment_due_mode( $value ) {
			$value = is_string( $value ) ? sanitize_key( $value ) : 'contract';

			return array_key_exists( $value, self::get_payment_due_mode_options() ) ? $value : 'contract';
		}

		/**
		 * 契約テーブルのカラム有無
		 *
		 * @param string $column カラム名。
		 * @return bool
		 */
		private function table_has_column( $column ) {
			global $wpdb;

			static $cache = array();
			$table        = $this->get_contract_table_name();
			$column       = sanitize_key( (string) $column );

			if ( isset( $cache[ $column ] ) ) {
				return $cache[ $column ];
			}

			$columns = $wpdb->get_col( "SHOW COLUMNS FROM `{$table}`" );
			$cache[ $column ] = is_array( $columns ) && in_array( $column, $columns, true );

			return $cache[ $column ];
		}

		/**
		 * 日付文字列を Y-m-d に正規化
		 *
		 * @param string $value 日付。
		 * @return string|null
		 */
		private function sanitize_date( $value ) {
			$value = sanitize_text_field( (string) $value );
			if ( $value === '' ) {
				return null;
			}

			$timestamp = strtotime( $value );
			if ( false === $timestamp ) {
				return null;
			}

			return wp_date( 'Y-m-d', $timestamp );
		}
	}
}
