<?php
/**
 * 定期契約の請求・案件自動生成
 *
 * @package KTPWP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'KTPWP_Contract_Billing' ) ) {

	/**
	 * 定期請求の生成と一覧取得。
	 */
	class KTPWP_Contract_Billing {

		/** 初回費用行の備考（一括請求・メールで識別） */
		const INITIAL_FEE_REMARKS = '初回のみ';

		/** @var self|null */
		private static $instance = null;

		/** @var KTPWP_Contract_DB */
		private $contract_db;

		/**
		 * @return self
		 */
		public static function get_instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		private function __construct() {
			$this->contract_db = KTPWP_Contract_DB::get_instance();
		}

		/**
		 * 請求対象月（YYYY-MM）
		 *
		 * @param string|null $date Y-m-d 形式。
		 * @return string
		 */
		public function get_billing_period( $date = null ) {
			if ( $date ) {
				$timestamp = strtotime( $date );
				if ( false === $timestamp ) {
					return wp_date( 'Y-m', current_time( 'timestamp' ) );
				}

				return wp_date( 'Y-m', $timestamp );
			}

			return wp_date( 'Y-m', current_time( 'timestamp' ) );
		}

		/**
		 * 対象月の定期請求ダッシュボード行
		 *
		 * @param string|null $period YYYY-MM。
		 * @return array<int, array<string, mixed>>
		 */
		public function get_monthly_rows( $period = null ) {
			$period = $period ? sanitize_text_field( $period ) : $this->get_billing_period();
			$rows   = array();

			if ( ! $this->contract_db->tables_exist() ) {
				return $rows;
			}

			global $wpdb;
			$contract_table = $this->contract_db->get_contract_table_name();
			$client_table   = $wpdb->prefix . 'ktp_client';

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$contracts = $wpdb->get_results(
				"SELECT c.*, cl.company_name, cl.name AS client_user_name,
					cl.payment_month, cl.payment_day, cl.closing_day
				FROM {$contract_table} c
				LEFT JOIN {$client_table} cl ON cl.id = c.client_id
				WHERE c.status = 'active'
				ORDER BY c.id ASC"
			);

			if ( ! is_array( $contracts ) ) {
				return $rows;
			}

			foreach ( $contracts as $contract ) {
				if ( ! $this->is_contract_due_in_period( $contract, $period ) ) {
					continue;
				}

				$log   = $this->get_billing_log( (int) $contract->id, $period );
				$order = null;
				if ( $log && (int) $log->order_id > 0 ) {
					$order = $wpdb->get_row(
						$wpdb->prepare(
							"SELECT id, progress FROM {$wpdb->prefix}ktp_order WHERE id = %d",
							(int) $log->order_id
						)
					);
				}
				if ( ! $order ) {
					$existing_order = $this->find_order_for_contract_period( (int) $contract->id, $period );
					if ( $existing_order ) {
						$order = $existing_order;
						$this->ensure_billing_log( (int) $contract->id, $period, (int) $existing_order->id );
					}
				}

				$status = 'pending';
				if ( $order ) {
					$progress = (int) $order->progress;
					if ( 7 === $progress ) {
						$status = 'rejected';
					} elseif ( $progress >= 6 ) {
						$status = 'paid';
					} elseif ( $progress >= 5 ) {
						$status = 'invoiced';
					} else {
						$status = 'generated';
					}
				}

				$billing_day   = (int) $contract->billing_day;
				$billing_date  = self::get_billing_date_for_period( $billing_day, $period );
				$payment_mode  = class_exists( 'KTPWP_Contract_DB' )
					? KTPWP_Contract_DB::sanitize_payment_due_mode( $contract->payment_due_mode ?? 'contract' )
					: 'contract';

				$rows[] = array(
					'contract_id'          => (int) $contract->id,
					'client_id'            => (int) $contract->client_id,
					'client_name'          => trim( (string) $contract->company_name . ' ' . (string) $contract->client_user_name ),
					'contract_name'        => (string) $contract->contract_name,
					'amount'               => (float) $contract->amount,
					'billing_cycle'        => (string) $contract->billing_cycle,
					'billing_day'          => $billing_day,
					'billing_day_label'    => self::format_billing_day_label( $billing_day ),
					'billing_date'         => $billing_date,
					'billing_date_label'   => self::format_short_date( $billing_date ),
					'payment_due_mode'     => $payment_mode,
					'payment_timing_label' => self::format_payment_due_label( $contract, $period ),
					'period'               => $period,
					'status'               => $status,
					'order_id'             => $order ? (int) $order->id : 0,
					'log_id'               => $log ? (int) $log->id : 0,
					'reminder_eligible'    => (int) $contract->send_reminder_mail === 1,
					'reminder_sent'        => $log && ! empty( $log->reminder_sent_at ),
				);
			}

			return $rows;
		}

		/**
		 * 契約が指定月に請求対象か
		 *
		 * @param object $contract 契約行。
		 * @param string $period   YYYY-MM。
		 * @return bool
		 */
		public function is_contract_due_in_period( $contract, $period ) {
			if ( ! is_object( $contract ) || empty( $contract->billing_cycle ) ) {
				return false;
			}

			if ( isset( $contract->status ) && 'active' !== $contract->status ) {
				return false;
			}

			$interval = class_exists( 'KTPWP_Contract_Billing_Cycle' )
				? KTPWP_Contract_Billing_Cycle::get_interval_months( $contract->billing_cycle )
				: 0;

			if ( $interval <= 0 ) {
				return false;
			}

			$period_start = $period . '-01';
			$period_end   = gmdate( 'Y-m-t', strtotime( $period_start ) );

			if ( ! empty( $contract->start_date ) && $contract->start_date > $period_end ) {
				return false;
			}

			if ( ! empty( $contract->end_date ) && $contract->end_date < $period_start ) {
				return false;
			}

			$anchor = ! empty( $contract->start_date ) ? $contract->start_date : substr( (string) $contract->created_at, 0, 10 );
			if ( ! $anchor ) {
				$anchor = $period_start;
			}

			$anchor_year  = (int) gmdate( 'Y', strtotime( $anchor ) );
			$anchor_month = (int) gmdate( 'n', strtotime( $anchor ) );
			$period_year  = (int) substr( $period, 0, 4 );
			$period_month = (int) substr( $period, 5, 2 );

			$months_diff = ( $period_year - $anchor_year ) * 12 + ( $period_month - $anchor_month );
			if ( $months_diff < 0 ) {
				return false;
			}

			return ( $months_diff % $interval ) === 0;
		}

		/**
		 * 請求ログ取得
		 *
		 * @param int    $contract_id 契約 ID。
		 * @param string $period        YYYY-MM。
		 * @return object|null
		 */
		public function get_billing_log( $contract_id, $period ) {
			global $wpdb;

			$contract_id = absint( $contract_id );
			$period      = sanitize_text_field( $period );
			if ( $contract_id <= 0 || ! preg_match( '/^\d{4}-\d{2}$/', $period ) ) {
				return null;
			}

			$table = $this->contract_db->get_billing_log_table_name();

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			return $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE contract_id = %d AND billing_period = %s",
					$contract_id,
					$period
				)
			);
		}

		/**
		 * 定期契約から受注書を生成
		 *
		 * @param int    $contract_id 契約 ID。
		 * @param string $period      YYYY-MM。
		 * @return int|\WP_Error
		 */
		public function generate_order_for_contract( $contract_id, $period = null ) {
			global $wpdb;

			$contract_id = absint( $contract_id );
			$period      = $period ? sanitize_text_field( $period ) : $this->get_billing_period();

			if ( $contract_id <= 0 || ! preg_match( '/^\d{4}-\d{2}$/', $period ) ) {
				return new WP_Error( 'invalid_args', __( '請求対象が不正です。', 'ktpwp' ) );
			}

			$contract = $this->contract_db->get_contract_by_id( $contract_id );
			if ( ! $contract ) {
				return new WP_Error( 'not_found', __( '定期契約が見つかりません。', 'ktpwp' ) );
			}

			if ( 'active' !== $contract->status ) {
				return new WP_Error( 'inactive', __( '有効な定期契約のみ案件を紐付けできます。', 'ktpwp' ) );
			}

			if ( ! $this->is_contract_due_in_period( $contract, $period ) ) {
				return new WP_Error( 'not_due', __( 'この月は請求対象ではありません。', 'ktpwp' ) );
			}

			$existing_log = $this->get_billing_log( $contract_id, $period );
			if ( $existing_log && (int) $existing_log->order_id > 0 ) {
				return new WP_Error( 'already_generated', __( '今月分の案件はすでに紐付けされています。', 'ktpwp' ) );
			}

			$existing_order = $this->find_order_for_contract_period( $contract_id, $period );
			if ( $existing_order ) {
				$this->ensure_billing_log( $contract_id, $period, (int) $existing_order->id );
				return new WP_Error( 'already_generated', __( '今月分の案件はすでに紐付けされています。', 'ktpwp' ) );
			}

			$client = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}ktp_client WHERE id = %d",
					(int) $contract->client_id
				)
			);
			if ( ! $client ) {
				return new WP_Error( 'client_missing', __( '顧客が見つかりません。', 'ktpwp' ) );
			}

			if ( isset( $client->client_status ) && '対象外' === $client->client_status ) {
				return new WP_Error( 'client_excluded', __( '対象外の顧客には案件を紐付けできません。', 'ktpwp' ) );
			}

			$service = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}ktp_service WHERE id = %d",
					(int) $contract->service_id
				)
			);

			$order_table = $wpdb->prefix . 'ktp_order';
			$timestamp   = current_time( 'timestamp' );
			$today       = wp_date( 'Y-m-d', $timestamp );
			$order_prefix = wp_date( 'Y-md', $timestamp ) . '-';
			$today_count = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM `{$order_table}` WHERE order_number LIKE %s",
					$wpdb->esc_like( $order_prefix ) . '%'
				)
			);
			$order_number = $order_prefix . str_pad( (string) ( $today_count + 1 ), 3, '0', STR_PAD_LEFT );

			$parts        = explode( '-', $period );
			$period_label = (int) $parts[0] . '年' . (int) $parts[1] . '月分';
			$project_name = trim( (string) $contract->contract_name ) . '（' . $period_label . '）';

			$insert_data = array(
				'order_number'  => $order_number,
				'time'          => $timestamp,
				'client_id'     => (int) $contract->client_id,
				'customer_name' => sanitize_text_field( (string) $client->company_name ),
				'user_name'     => sanitize_text_field( (string) $client->name ),
				'project_name'  => sanitize_text_field( $project_name ),
				'progress'      => 4,
				'invoice_items' => '',
				'cost_items'    => '',
				'memo'          => sanitize_textarea_field(
					sprintf(
						/* translators: %d: contract id */
						__( '定期契約ID: %d', 'ktpwp' ),
						$contract_id
					)
				),
				'search_field'  => sanitize_text_field(
					implode(
						', ',
						array(
							$client->company_name,
							$client->name,
							$project_name,
							$contract->contract_name,
						)
					)
				),
			);
			$insert_formats = array( '%s', '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s' );

			if ( $this->order_table_has_column( 'contract_id' ) ) {
				$insert_data['contract_id'] = $contract_id;
				$insert_formats[]           = '%d';
			}
			if ( $this->order_table_has_column( 'billing_period' ) ) {
				$insert_data['billing_period'] = $period;
				$insert_formats[]              = '%s';
			}
			if ( $this->order_table_has_column( 'completion_date' ) ) {
				$insert_data['completion_date'] = $today;
				$insert_formats[]               = '%s';
			}

			$wpdb->query( 'START TRANSACTION' );

			$inserted = $wpdb->insert( $order_table, $insert_data, $insert_formats );
			if ( false === $inserted ) {
				$wpdb->query( 'ROLLBACK' );
				return new WP_Error( 'insert_failed', __( '案件の紐付けに失敗しました。', 'ktpwp' ) );
			}

			$order_id = (int) $wpdb->insert_id;
			$this->populate_invoice_items( $order_id, $contract, $service, (int) $contract->first_billed === 0 );

			if ( class_exists( 'KTPWP_Order_Items' ) ) {
				KTPWP_Order_Items::get_instance()->create_initial_cost_item( $order_id );
			}

			if ( (int) $contract->first_billed === 0 ) {
				$updated = $wpdb->update(
					$this->contract_db->get_contract_table_name(),
					array( 'first_billed' => 1 ),
					array( 'id' => $contract_id ),
					array( '%d' ),
					array( '%d' )
				);
				if ( false === $updated ) {
					$wpdb->query( 'ROLLBACK' );
					return new WP_Error( 'update_failed', __( '初回請求フラグの更新に失敗しました。', 'ktpwp' ) );
				}
			}

			$log_table = $this->contract_db->get_billing_log_table_name();
			if ( $existing_log && (int) $existing_log->order_id <= 0 ) {
				$log_saved = $wpdb->update(
					$log_table,
					array( 'order_id' => $order_id ),
					array( 'id' => (int) $existing_log->id ),
					array( '%d' ),
					array( '%d' )
				);
			} else {
				$log_saved = $wpdb->insert(
					$log_table,
					array(
						'contract_id'    => $contract_id,
						'order_id'       => $order_id,
						'billing_period' => $period,
					),
					array( '%d', '%d', '%s' )
				);
			}
			if ( false === $log_saved ) {
				$wpdb->query( 'ROLLBACK' );
				return new WP_Error( 'log_failed', __( '請求ログの保存に失敗しました。', 'ktpwp' ) );
			}

			$wpdb->query( 'COMMIT' );

			return $order_id;
		}

		/**
		 * 契約・請求月に紐づく受注書を検索
		 *
		 * @param int    $contract_id 契約 ID。
		 * @param string $period      YYYY-MM。
		 * @return object|null
		 */
		private function find_order_for_contract_period( $contract_id, $period ) {
			global $wpdb;

			$contract_id = absint( $contract_id );
			$period      = sanitize_text_field( $period );

			if ( $contract_id <= 0 || ! preg_match( '/^\d{4}-\d{2}$/', $period ) ) {
				return null;
			}

			if ( ! $this->order_table_has_column( 'contract_id' ) || ! $this->order_table_has_column( 'billing_period' ) ) {
				return null;
			}

			$order_table = $wpdb->prefix . 'ktp_order';

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			return $wpdb->get_row(
				$wpdb->prepare(
					"SELECT id, progress FROM {$order_table} WHERE contract_id = %d AND billing_period = %s ORDER BY id DESC LIMIT 1",
					$contract_id,
					$period
				)
			);
		}

		/**
		 * 請求ログが欠けている場合に補完
		 *
		 * @param int    $contract_id 契約 ID。
		 * @param string $period      YYYY-MM。
		 * @param int    $order_id    受注書 ID。
		 * @return void
		 */
		private function ensure_billing_log( $contract_id, $period, $order_id ) {
			global $wpdb;

			$contract_id = absint( $contract_id );
			$order_id    = absint( $order_id );
			$period      = sanitize_text_field( $period );

			if ( $contract_id <= 0 || $order_id <= 0 || ! preg_match( '/^\d{4}-\d{2}$/', $period ) ) {
				return;
			}

			$log = $this->get_billing_log( $contract_id, $period );
			if ( $log ) {
				if ( (int) $log->order_id <= 0 ) {
					$wpdb->update(
						$this->contract_db->get_billing_log_table_name(),
						array( 'order_id' => $order_id ),
						array( 'id' => (int) $log->id ),
						array( '%d' ),
						array( '%d' )
					);
				}
				return;
			}

			$wpdb->insert(
				$this->contract_db->get_billing_log_table_name(),
				array(
					'contract_id'    => $contract_id,
					'order_id'       => $order_id,
					'billing_period' => $period,
				),
				array( '%d', '%d', '%s' )
			);
		}

		/**
		 * 対象月の未生成分を一括生成
		 *
		 * @param string|null $period YYYY-MM。
		 * @return array{created: int, errors: array<int, string>}
		 */
		public function generate_all_pending( $period = null ) {
			$period  = $period ? sanitize_text_field( $period ) : $this->get_billing_period();
			$created = 0;
			$errors  = array();

			foreach ( $this->get_monthly_rows( $period ) as $row ) {
				if ( 'pending' !== $row['status'] ) {
					continue;
				}

				if ( class_exists( 'KTPWP_Stripe_Subscription' )
					&& KTPWP_Stripe_Subscription::get_instance()->contract_uses_subscription( (int) $row['contract_id'] ) ) {
					continue;
				}

				$result = $this->generate_order_for_contract( (int) $row['contract_id'], $period );
				if ( is_wp_error( $result ) ) {
					$errors[] = $row['contract_name'] . ': ' . $result->get_error_message();
					continue;
				}
				++$created;
			}

			return array(
				'created' => $created,
				'errors'  => $errors,
			);
		}

		/**
		 * 請求項目を設定
		 *
		 * @param int     $order_id         受注書 ID。
		 * @param object  $contract         契約。
		 * @param object|null $service      サービス。
		 * @param bool    $include_initial  初回費用を含めるか。
		 * @return void
		 */
		private function populate_invoice_items( $order_id, $contract, $service, $include_initial ) {
			global $wpdb;

			$order_id = absint( $order_id );
			if ( $order_id <= 0 || ! class_exists( 'KTPWP_Order_Items' ) ) {
				return;
			}

			$invoice_table = $wpdb->prefix . 'ktp_order_invoice_items';
			$wpdb->delete( $invoice_table, array( 'order_id' => $order_id ), array( '%d' ) );

			$unit     = ( $service && ! empty( $service->unit ) ) ? (string) $service->unit : __( '式', 'ktpwp' );
			$default_tax_rate = null;
			if ( $service && isset( $service->tax_rate ) && $service->tax_rate !== null && $service->tax_rate !== '' ) {
				$default_tax_rate = (float) $service->tax_rate;
			}

			$recurring_items = class_exists( 'KTPWP_Contract_Recurring_Items' )
				? KTPWP_Contract_Recurring_Items::get_by_contract_id( (int) $contract->id )
				: array();
			$sort = 1;

			if ( ! empty( $recurring_items ) ) {
				foreach ( $recurring_items as $item ) {
					$item_tax = ( $item->tax_rate !== null && $item->tax_rate !== '' )
						? (float) $item->tax_rate
						: $default_tax_rate;
					$this->insert_invoice_line(
						$order_id,
						(string) $item->item_name,
						(float) $item->amount,
						1,
						$unit,
						$item_tax,
						$sort
					);
					++$sort;
				}
			} else {
				$price = (float) $contract->amount;
				$product_name = (string) $contract->contract_name;
				if ( $service && ! empty( $service->service_name ) ) {
					$product_name = (string) $service->service_name;
				}

				$this->insert_invoice_line( $order_id, $product_name, $price, 1, $unit, $default_tax_rate, $sort );
				++$sort;
			}

			if ( $include_initial ) {
				$fees = $this->contract_db->get_initial_fees_by_contract_id( (int) $contract->id );
				foreach ( $fees as $fee ) {
					$fee_tax = ( $fee->tax_rate !== null && $fee->tax_rate !== '' ) ? (float) $fee->tax_rate : null;
					$this->insert_invoice_line(
						$order_id,
						(string) $fee->fee_name,
						(float) $fee->amount,
						1,
						__( '式', 'ktpwp' ),
						$fee_tax,
						$sort,
						self::INITIAL_FEE_REMARKS,
					);
					++$sort;
				}
			}
		}

		/**
		 * 請求行を1件追加
		 *
		 * @param int         $order_id     受注書 ID。
		 * @param string      $product_name 品名。
		 * @param float       $price        単価。
		 * @param float       $quantity     数量。
		 * @param string      $unit         単位。
		 * @param float|null  $tax_rate     税率。
		 * @param int         $sort_order   並び順。
		 * @param string      $remarks      備考。
		 * @return void
		 */
		private function insert_invoice_line( $order_id, $product_name, $price, $quantity, $unit, $tax_rate, $sort_order, $remarks = '' ) {
			global $wpdb;

			$table = $wpdb->prefix . 'ktp_order_invoice_items';
			$data  = array(
				'order_id'     => absint( $order_id ),
				'product_name' => sanitize_text_field( $product_name ),
				'price'        => (float) $price,
				'unit'         => sanitize_text_field( $unit ),
				'quantity'     => (float) $quantity,
				'amount'       => (int) round( (float) $price * (float) $quantity ),
				'remarks'      => sanitize_text_field( $remarks ),
				'sort_order'   => (int) $sort_order,
				'created_at'   => current_time( 'mysql' ),
				'updated_at'   => current_time( 'mysql' ),
			);
			$format = array( '%d', '%s', '%f', '%s', '%f', '%d', '%s', '%d', '%s', '%s' );

			if ( null !== $tax_rate ) {
				$data['tax_rate'] = (float) $tax_rate;
				$format[]         = '%f';
			}

			$wpdb->insert( $table, $data, $format );
		}

		/**
		 * 受注書テーブルのカラム有無
		 *
		 * @param string $column カラム名。
		 * @return bool
		 */
		private function order_table_has_column( $column ) {
			global $wpdb;

			static $cache = array();
			$table        = $wpdb->prefix . 'ktp_order';

			if ( isset( $cache[ $column ] ) ) {
				return $cache[ $column ];
			}

			$columns = $wpdb->get_col( "SHOW COLUMNS FROM `{$table}`" );
			$cache[ $column ] = is_array( $columns ) && in_array( $column, $columns, true );

			return $cache[ $column ];
		}

		/**
		 * 請求日ラベル（毎月1日 / 毎月末）
		 *
		 * @param int $billing_day 請求日（99=月末）。
		 * @return string
		 */
		public static function format_billing_day_label( $billing_day ) {
			$billing_day = (int) $billing_day;
			if ( 99 === $billing_day ) {
				return __( '毎月末', 'ktpwp' );
			}
			if ( $billing_day <= 0 ) {
				return __( '未設定', 'ktpwp' );
			}

			/* translators: %d: day of month */
			return sprintf( __( '毎月%d日', 'ktpwp' ), $billing_day );
		}

		/**
		 * 対象月の請求日（Y-m-d）
		 *
		 * @param int    $billing_day 請求日（99=月末）。
		 * @param string $period      YYYY-MM。
		 * @return string
		 */
		public static function get_billing_date_for_period( $billing_day, $period ) {
			$billing_day = (int) $billing_day;
			$year        = (int) substr( $period, 0, 4 );
			$month       = (int) substr( $period, 5, 2 );

			if ( $year < 1 || $month < 1 || $month > 12 ) {
				return '';
			}

			if ( 99 === $billing_day ) {
				return gmdate( 'Y-m-t', strtotime( sprintf( '%04d-%02d-01', $year, $month ) ) );
			}

			$last_day = (int) gmdate( 't', strtotime( sprintf( '%04d-%02d-01', $year, $month ) ) );
			$day      = min( max( 1, $billing_day ), $last_day );

			return sprintf( '%04d-%02d-%02d', $year, $month, $day );
		}

		/**
		 * 短い日付表示（n/j）
		 *
		 * @param string $date Y-m-d。
		 * @return string
		 */
		public static function format_short_date( $date ) {
			$date = sanitize_text_field( (string) $date );
			if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
				return '';
			}

			$timestamp = strtotime( $date );
			if ( false === $timestamp ) {
				return '';
			}

			return wp_date( 'n/j', $timestamp );
		}

		/**
		 * 入金期日ラベル（契約設定に応じて表示）
		 *
		 * @param object $contract 契約＋顧客 JOIN 行。
		 * @param string $period   YYYY-MM。
		 * @return string
		 */
		public static function format_payment_due_label( $contract, $period ) {
			if ( ! is_object( $contract ) ) {
				return '—';
			}

			$mode = class_exists( 'KTPWP_Contract_DB' )
				? KTPWP_Contract_DB::sanitize_payment_due_mode( $contract->payment_due_mode ?? 'contract' )
				: 'contract';

			if ( 'client' === $mode ) {
				return self::format_client_payment_timing( $contract );
			}

			$billing_day      = (int) ( $contract->billing_day ?? 1 );
			$billing_date     = self::get_billing_date_for_period( $billing_day, $period );
			$billing_date_lbl = self::format_short_date( $billing_date );

			if ( 99 === $billing_day ) {
				if ( $billing_date_lbl !== '' ) {
					return sprintf(
						/* translators: %s: short date like 6/30 */
						__( '月末（%s）', 'ktpwp' ),
						$billing_date_lbl
					);
				}

				return __( '月末', 'ktpwp' );
			}

			if ( $billing_date_lbl !== '' ) {
				return sprintf(
					/* translators: 1: day of month, 2: short date */
					__( '%1$d日（%2$s）', 'ktpwp' ),
					$billing_day,
					$billing_date_lbl
				);
			}

			return self::format_billing_day_label( $billing_day );
		}

		/**
		 * 顧客マスタの支払期日ラベル
		 *
		 * @param object|null $client_or_contract 顧客または契約行。
		 * @return string
		 */
		public static function format_client_payment_timing( $client_or_contract ) {
			if ( ! is_object( $client_or_contract ) ) {
				return '—';
			}

			$payment_month = isset( $client_or_contract->payment_month ) ? trim( (string) $client_or_contract->payment_month ) : '';
			$payment_day   = isset( $client_or_contract->payment_day ) ? trim( (string) $client_or_contract->payment_day ) : '';
			$closing_day   = isset( $client_or_contract->closing_day ) ? trim( (string) $client_or_contract->closing_day ) : '';

			if ( $payment_month === '' && $payment_day === '' && ( $closing_day === '' || $closing_day === 'なし' ) ) {
				return __( '未設定', 'ktpwp' );
			}

			$parts = array();
			if ( $closing_day !== '' && $closing_day !== 'なし' ) {
				$parts[] = sprintf(
					/* translators: %s: closing day label */
					__( '締め:%s', 'ktpwp' ),
					$closing_day
				);
			}
			if ( $payment_month !== '' || $payment_day !== '' ) {
				$parts[] = trim( $payment_month . $payment_day );
			}

			return implode( ' / ', $parts );
		}
	}
}
