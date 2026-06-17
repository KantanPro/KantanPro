<?php
/**
 * 案件から定期契約への変換
 *
 * @package KTPWP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'KTPWP_Order_Contract_Conversion' ) ) {

	/**
	 * 受注書を定期契約に変換し、必要に応じて請求案件として紐付ける。
	 */
	class KTPWP_Order_Contract_Conversion {

		/** @var self|null */
		private static $instance = null;

		/** @var KTPWP_Order_Contract_Draft_Resolver */
		private $draft_resolver;

		/** @var KTPWP_Contract_DB */
		private $contract_db;

		/** @var KTPWP_Contract_Billing */
		private $billing;

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
			$this->draft_resolver = KTPWP_Order_Contract_Draft_Resolver::get_instance();
			$this->contract_db    = KTPWP_Contract_DB::get_instance();
			$this->billing        = KTPWP_Contract_Billing::get_instance();
		}

		/**
		 * 変換可能か。
		 *
		 * @param int $order_id 受注書 ID。
		 * @return bool
		 */
		public function can_convert( $order_id ) {
			return null !== $this->draft_resolver->resolve( $order_id );
		}

		/**
		 * ドラフト取得。
		 *
		 * @param int $order_id 受注書 ID。
		 * @return array<string, mixed>|null
		 */
		public function get_draft( $order_id ) {
			return $this->draft_resolver->resolve( $order_id );
		}

		/**
		 * 案件から定期契約を作成する。
		 *
		 * @param int                              $order_id         受注書 ID。
		 * @param array<string, mixed>             $contract_data    契約データ。
		 * @param array<int, array<string, mixed>> $initial_fees     初回費用。
		 * @param array<int, array<string, mixed>> $recurring_items  定期明細。
		 * @param bool                             $link_order       案件を請求案件として紐付けるか。
		 * @param string|null                      $billing_period   請求月 YYYY-MM。
		 * @param array<string, mixed>             $options          追加オプション。
		 * @return int|\WP_Error 契約 ID またはエラー。
		 */
		public function convert( $order_id, $contract_data, $initial_fees = array(), $recurring_items = array(), $link_order = true, $billing_period = null, $options = array() ) {
			global $wpdb;

			$order_id = absint( $order_id );
			$draft    = $this->draft_resolver->resolve( $order_id );

			if ( ! $draft ) {
				return new WP_Error( 'cannot_convert', __( 'この案件から定期契約を作成できません。', 'ktpwp' ) );
			}

			if ( ! $this->contract_db->tables_exist() ) {
				return new WP_Error( 'no_tables', __( '定期契約機能が利用できません。', 'ktpwp' ) );
			}

			$order_table = $wpdb->prefix . 'ktp_order';
			$wpdb->query( 'START TRANSACTION' );

			try {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$order = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT * FROM {$order_table} WHERE id = %d FOR UPDATE",
						$order_id
					)
				);

				if ( ! $order ) {
					throw new Exception( __( '案件が見つかりません。', 'ktpwp' ) );
				}

				if ( isset( $order->contract_id ) && (int) $order->contract_id > 0 ) {
					throw new Exception( __( 'この案件はすでに定期契約に紐付いています。', 'ktpwp' ) );
				}

				$client_id = (int) ( $contract_data['client_id'] ?? $order->client_id ?? 0 );
				if ( $client_id <= 0 ) {
					throw new Exception( __( '顧客が見つかりません。', 'ktpwp' ) );
				}

				$save_data = array_merge(
					$contract_data,
					array(
						'id'        => 0,
						'client_id' => $client_id,
					)
				);

				$contract_id = $this->contract_db->save_contract( $save_data, $initial_fees, $recurring_items );
				if ( is_wp_error( $contract_id ) ) {
					throw new Exception( $contract_id->get_error_message() );
				}

				if ( $link_order ) {
					$period = is_string( $billing_period ) && preg_match( '/^\d{4}-\d{2}$/', $billing_period )
						? $billing_period
						: $this->billing->get_billing_period();

					$this->link_order_as_billing( $order, (int) $contract_id, $period );
				}

				$wpdb->query( 'COMMIT' );

				if ( class_exists( 'KTPWP_Contract_Service_Public_Availability' ) ) {
					$service_id = isset( $save_data['service_id'] ) ? (int) $save_data['service_id'] : 0;
					if ( $service_id > 0 ) {
						KTPWP_Contract_Service_Public_Availability::close_stale_public_inquiries_for_service( $service_id );
					}
				}

				if ( class_exists( 'KTPWP_Stripe_Subscription' ) ) {
					$subscription_options = is_array( $options ) ? $options : array();
					if ( ! empty( $subscription_options['skip_subscription_start'] ) && ! empty( $subscription_options['subscription_id'] ) ) {
						KTPWP_Stripe_Subscription::get_instance()->attach_subscription_to_contract(
							(int) $contract_id,
							(string) $subscription_options['subscription_id'],
							$order_id
						);
					} elseif ( empty( $subscription_options['skip_subscription_start'] ) ) {
						$result = KTPWP_Stripe_Subscription::get_instance()->maybe_start_for_contract( (int) $contract_id, $order_id );
						if ( is_wp_error( $result ) && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
							error_log( 'KTPWP Stripe Subscription start: ' . $result->get_error_message() );
						}
					}
				}

				return (int) $contract_id;
			} catch ( Exception $e ) {
				$wpdb->query( 'ROLLBACK' );

				return new WP_Error( 'convert_failed', $e->getMessage() );
			}
		}

		/**
		 * 案件を定期請求案件として紐付ける。
		 *
		 * @param object $order       受注書。
		 * @param int    $contract_id 契約 ID。
		 * @param string $period      YYYY-MM。
		 * @return void
		 */
		private function link_order_as_billing( $order, $contract_id, $period ) {
			global $wpdb;

			$contract = $this->contract_db->get_contract_by_id( $contract_id );
			if ( ! $contract ) {
				throw new Exception( __( '契約が見つかりません。', 'ktpwp' ) );
			}

			if ( ! $this->billing->is_contract_due_in_period( $contract, $period ) ) {
				throw new Exception( __( '選択した月はこの契約の請求対象ではありません。', 'ktpwp' ) );
			}

			$existing_log = $this->billing->get_billing_log( $contract_id, $period );
			if ( $existing_log && (int) $existing_log->order_id > 0 && (int) $existing_log->order_id !== (int) $order->id ) {
				throw new Exception( __( '選択した月にはすでに別の定期請求案件があります。', 'ktpwp' ) );
			}

			$order_table = $wpdb->prefix . 'ktp_order';
			$columns     = $wpdb->get_col( "SHOW COLUMNS FROM `{$order_table}`" );

			if ( is_array( $columns ) && in_array( 'contract_id', $columns, true ) && in_array( 'billing_period', $columns, true ) ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$duplicate = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT id FROM {$order_table}
						WHERE contract_id = %d AND billing_period = %s AND id <> %d
						LIMIT 1",
						$contract_id,
						$period,
						(int) $order->id
					)
				);

				if ( $duplicate ) {
					throw new Exception( __( '選択した月にはすでに別の定期請求案件があります。', 'ktpwp' ) );
				}
			}

			$updates  = array();
			$formats  = array();

			if ( is_array( $columns ) && in_array( 'contract_id', $columns, true ) ) {
				$updates['contract_id'] = $contract_id;
				$formats[]              = '%d';
			}
			if ( is_array( $columns ) && in_array( 'billing_period', $columns, true ) ) {
				$updates['billing_period'] = $period;
				$formats[]                 = '%s';
			}

			$progress = isset( $order->progress ) ? (int) $order->progress : 0;
			if ( in_array( $progress, array( 1, 2, 3 ), true ) ) {
				$updates['progress'] = 4;
				$formats[]           = '%d';

				if ( is_array( $columns ) && in_array( 'completion_date', $columns, true ) ) {
					$updates['completion_date'] = wp_date( 'Y-m-d' );
					$formats[]                  = '%s';
				}
			}

			if ( ! empty( $updates ) ) {
				$result = $wpdb->update(
					$order_table,
					$updates,
					array( 'id' => (int) $order->id ),
					$formats,
					array( '%d' )
				);

				if ( false === $result ) {
					throw new Exception( __( '案件の紐付けに失敗しました。', 'ktpwp' ) );
				}
			}

			$log_table = $this->contract_db->get_billing_log_table_name();
			if ( $existing_log ) {
				$wpdb->update(
					$log_table,
					array( 'order_id' => (int) $order->id ),
					array( 'id' => (int) $existing_log->id ),
					array( '%d' ),
					array( '%d' )
				);
			} else {
				$wpdb->insert(
					$log_table,
					array(
						'contract_id'    => $contract_id,
						'order_id'       => (int) $order->id,
						'billing_period' => $period,
					),
					array( '%d', '%d', '%s' )
				);
			}

			$this->sync_first_billed_flag( (int) $order->id, $contract_id );
		}

		/**
		 * 初回請求済みフラグを同期する。
		 *
		 * @param int $order_id    受注書 ID。
		 * @param int $contract_id 契約 ID。
		 * @return void
		 */
		private function sync_first_billed_flag( $order_id, $contract_id ) {
			global $wpdb;

			$initial_fees = $this->contract_db->get_initial_fees_by_contract_id( $contract_id );
			$contract_table = $this->contract_db->get_contract_table_name();

			if ( empty( $initial_fees ) ) {
				$wpdb->update(
					$contract_table,
					array( 'first_billed' => 1 ),
					array( 'id' => $contract_id ),
					array( '%d' ),
					array( '%d' )
				);

				return;
			}

			$invoice_items = array();
			if ( class_exists( 'KTPWP_Order_Items' ) ) {
				$invoice_items = KTPWP_Order_Items::get_instance()->get_invoice_items( $order_id );
			}

			$initial_remarks = class_exists( 'KTPWP_Contract_Billing' )
				? KTPWP_Contract_Billing::INITIAL_FEE_REMARKS
				: '初回のみ';

			$has_initial_fee_line = false;
			foreach ( $invoice_items as $item ) {
				if ( trim( (string) ( $item['remarks'] ?? '' ) ) === $initial_remarks ) {
					$has_initial_fee_line = true;
					break;
				}
			}

			if ( ! $has_initial_fee_line ) {
				foreach ( $initial_fees as $fee ) {
					$fee_name = trim( (string) ( $fee->fee_name ?? '' ) );
					if ( $fee_name === '' ) {
						continue;
					}

					foreach ( $invoice_items as $item ) {
						$product_name = trim( (string) ( $item['product_name'] ?? '' ) );
						if ( $product_name === $fee_name
							|| str_contains( $product_name, $fee_name )
							|| str_contains( $fee_name, $product_name )
						) {
							$has_initial_fee_line = true;
							break 2;
						}
					}
				}
			}

			if ( $has_initial_fee_line ) {
				$wpdb->update(
					$contract_table,
					array( 'first_billed' => 1 ),
					array( 'id' => $contract_id ),
					array( '%d' ),
					array( '%d' )
				);
			}
		}
	}
}
