<?php
/**
 * 案件から定期契約ドラフトを推定する
 *
 * @package KTPWP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'KTPWP_Order_Contract_Draft_Resolver' ) ) {

	/**
	 * 受注書の内容から定期契約作成用ドラフトを組み立てる。
	 */
	class KTPWP_Order_Contract_Draft_Resolver {

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
		 * ドラフトを返す。変換不可なら null。
		 *
		 * @param int $order_id 受注書 ID。
		 * @return array<string, mixed>|null
		 */
		public function resolve( $order_id ) {
			$order = $this->get_order( $order_id );
			if ( ! $order || $this->is_recurring_contract_order( $order ) ) {
				return null;
			}

			$service = $this->resolve_service( $order );
			if ( ! $service || ! $this->is_recurring_service( $service ) ) {
				return null;
			}

			$invoice_items   = $this->get_invoice_items( $order_id );
			$recurring_items = $this->resolve_recurring_items( $order, $service, $invoice_items );
			$amount          = ! empty( $recurring_items )
				? KTPWP_Contract_Recurring_Items::total_amount( $recurring_items )
				: $this->resolve_amount( $order, $service, $invoice_items );
			$initial_fees = $this->resolve_initial_fees( $order, $service, $invoice_items );

			$contract_name = trim( (string) ( $order->project_name ?? '' ) );
			if ( $contract_name === '' ) {
				$contract_name = trim( (string) ( $service->service_name ?? '' ) );
			}

			$billing_cycle = class_exists( 'KTPWP_Contract_Billing_Cycle' )
				? KTPWP_Contract_Billing_Cycle::sanitize( $service->contract_billing_cycle ?? 'none' )
				: 'monthly';

			return array(
				'service'              => $service,
				'service_id'           => (int) $service->id,
				'service_name'         => (string) $service->service_name,
				'contract_name'        => $contract_name,
				'amount'               => $amount,
				'billing_cycle'        => $billing_cycle,
				'billing_cycle_label'  => class_exists( 'KTPWP_Contract_Billing_Cycle' )
					? KTPWP_Contract_Billing_Cycle::get_label( $billing_cycle )
					: $billing_cycle,
				'from_web_application' => KTPWP_Public_Product_Order_Memo::is_web_application( $order->memo ?? '' ),
				'initial_fees'         => $initial_fees,
				'recurring_items'      => $recurring_items,
				'client_id'            => (int) ( $order->client_id ?? 0 ),
				'order_id'             => (int) $order->id,
			);
		}

		/**
		 * @param object $order 受注書。
		 * @return bool
		 */
		private function is_recurring_contract_order( $order ) {
			return isset( $order->contract_id ) && (int) $order->contract_id > 0;
		}

		/**
		 * @param int $order_id 受注書 ID。
		 * @return object|null
		 */
		private function get_order( $order_id ) {
			global $wpdb;

			$order_id = absint( $order_id );
			if ( $order_id <= 0 ) {
				return null;
			}

			$table = $wpdb->prefix . 'ktp_order';

			return $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE id = %d",
					$order_id
				)
			);
		}

		/**
		 * @param object $order 受注書。
		 * @return object|null
		 */
		private function resolve_service( $order ) {
			$service_id = KTPWP_Public_Product_Order_Memo::parse_service_id( $order->memo ?? '' );
			if ( $service_id !== null ) {
				$service = $this->get_service_by_id( $service_id );
				if ( $service ) {
					return $service;
				}
			}

			$invoice_items = $this->get_invoice_items( (int) $order->id );
			foreach ( $invoice_items as $item ) {
				$product_name = trim( (string) ( $item['product_name'] ?? '' ) );
				if ( $product_name === '' ) {
					continue;
				}

				$service = $this->get_recurring_service_by_name( $product_name );
				if ( $service ) {
					return $service;
				}
			}

			return null;
		}

		/**
		 * @param int $service_id サービス ID。
		 * @return object|null
		 */
		private function get_service_by_id( $service_id ) {
			global $wpdb;

			$service_id = absint( $service_id );
			if ( $service_id <= 0 ) {
				return null;
			}

			$table = $wpdb->prefix . 'ktp_service';

			return $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE id = %d",
					$service_id
				)
			);
		}

		/**
		 * @param string $name サービス名。
		 * @return object|null
		 */
		private function get_recurring_service_by_name( $name ) {
			global $wpdb;

			$name = trim( $name );
			if ( $name === '' ) {
				return null;
			}

			$table = $wpdb->prefix . 'ktp_service';
			$columns = $wpdb->get_col( "SHOW COLUMNS FROM `{$table}`" );
			if ( ! is_array( $columns ) || ! in_array( 'contract_billing_cycle', $columns, true ) ) {
				return null;
			}

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			return $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$table}
					WHERE service_name = %s
					AND contract_billing_cycle IS NOT NULL
					AND contract_billing_cycle <> 'none'
					ORDER BY id ASC
					LIMIT 1",
					$name
				)
			);
		}

		/**
		 * @param object $service サービス。
		 * @return bool
		 */
		private function is_recurring_service( $service ) {
			if ( ! class_exists( 'KTPWP_Contract_Billing_Cycle' ) ) {
				return false;
			}

			return KTPWP_Contract_Billing_Cycle::is_recurring( $service->contract_billing_cycle ?? 'none' );
		}

		/**
		 * @param int $order_id 受注書 ID。
		 * @return array<int, array<string, mixed>>
		 */
		private function get_invoice_items( $order_id ) {
			if ( ! class_exists( 'KTPWP_Order_Items' ) ) {
				return array();
			}

			$items = KTPWP_Order_Items::get_instance()->get_invoice_items( $order_id );

			return is_array( $items ) ? $items : array();
		}

		/**
		 * @param object                             $order         受注書。
		 * @param object                             $service       サービス。
		 * @param array<int, array<string, mixed>>   $invoice_items 請求明細。
		 * @return array<int, array{item_name: string, amount: float, tax_rate: ?float}>
		 */
		private function resolve_recurring_items( $order, $service, $invoice_items ) {
			$from_invoice = $this->resolve_recurring_items_from_invoice( $service, $invoice_items );
			if ( ! empty( $from_invoice ) ) {
				return $from_invoice;
			}

			return $this->default_recurring_items_for_service( (int) $service->id );
		}

		/**
		 * @param object                           $service       サービス。
		 * @param array<int, array<string, mixed>> $invoice_items 請求明細。
		 * @return array<int, array{item_name: string, amount: float, tax_rate: ?float}>
		 */
		private function resolve_recurring_items_from_invoice( $service, $invoice_items ) {
			$recurring_names = $this->recurring_item_name_keys( (int) $service->id );
			$service_name    = trim( (string) ( $service->service_name ?? '' ) );
			$items           = array();
			$seen            = array();

			foreach ( $invoice_items as $item ) {
				if ( $this->is_initial_fee_invoice_item( $item ) ) {
					continue;
				}

				$product_name = trim( (string) ( $item['product_name'] ?? '' ) );
				if ( $product_name === '' ) {
					continue;
				}

				$key = mb_strtolower( $product_name );
				if ( isset( $seen[ $key ] ) ) {
					continue;
				}

				if ( ! empty( $recurring_names ) && ! isset( $recurring_names[ $key ] ) ) {
					continue;
				}

				if ( empty( $recurring_names ) && $product_name !== $service_name ) {
					continue;
				}

				$seen[ $key ] = true;
				$items[]      = array(
					'item_name' => $product_name,
					'amount'    => max( 0, (float) ( $item['amount'] ?? 0 ) ),
					'tax_rate'  => isset( $item['tax_rate'] ) && $item['tax_rate'] !== '' && $item['tax_rate'] !== null
						? (float) $item['tax_rate']
						: null,
				);
			}

			return $items;
		}

		/**
		 * @param int $service_id サービス ID。
		 * @return array<int, array{item_name: string, amount: float, tax_rate: ?float}>
		 */
		public function default_recurring_items_for_service( $service_id, $exclude_contract_id = null ) {
			$service_id = absint( $service_id );
			if ( $service_id <= 0 || ! class_exists( 'KTPWP_Contract_Recurring_Items' ) ) {
				return array();
			}

			$service_items = KTPWP_Contract_Recurring_Items::get_by_service_id( $service_id );
			if ( ! empty( $service_items ) ) {
				return $this->map_recurring_models_to_rows( $service_items );
			}

			global $wpdb;

			$contract_table = $wpdb->prefix . 'ktp_contract';
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $contract_table ) ) !== $contract_table ) {
				return array();
			}

			$sql = "SELECT id FROM {$contract_table} WHERE service_id = %d";
			$params = array( $service_id );

			if ( $exclude_contract_id !== null && (int) $exclude_contract_id > 0 ) {
				$sql     .= ' AND id <> %d';
				$params[] = (int) $exclude_contract_id;
			}

			$sql .= ' ORDER BY id DESC LIMIT 1';

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$contract_id = (int) $wpdb->get_var( $wpdb->prepare( $sql, $params ) );
			if ( $contract_id <= 0 ) {
				return array();
			}

			$contract_items = KTPWP_Contract_Recurring_Items::get_by_contract_id( $contract_id );
			if ( empty( $contract_items ) ) {
				return array();
			}

			return $this->map_recurring_models_to_rows( $contract_items );
		}

		/**
		 * @param object                             $order         受注書。
		 * @param object                             $service       サービス。
		 * @param array<int, array<string, mixed>>   $invoice_items 請求明細。
		 * @return array<int, array{fee_name: string, amount: float, tax_rate: ?float}>
		 */
		private function resolve_initial_fees( $order, $service, $invoice_items ) {
			$from_invoice = $this->resolve_initial_fees_from_invoice( $service, $invoice_items );
			if ( ! empty( $from_invoice ) ) {
				return $from_invoice;
			}

			return $this->default_initial_fees_for_service( (int) $service->id );
		}

		/**
		 * @param object                           $service       サービス。
		 * @param array<int, array<string, mixed>> $invoice_items 請求明細。
		 * @return array<int, array{fee_name: string, amount: float, tax_rate: ?float}>
		 */
		private function resolve_initial_fees_from_invoice( $service, $invoice_items ) {
			$service_name    = trim( (string) ( $service->service_name ?? '' ) );
			$recurring_names = $this->recurring_item_name_keys( (int) $service->id );
			$fees            = array();
			$seen            = array();

			foreach ( $invoice_items as $item ) {
				if ( $this->is_initial_fee_invoice_item( $item ) ) {
					$product_name = trim( (string) ( $item['product_name'] ?? '' ) );
					if ( $product_name === '' ) {
						continue;
					}
					$key = mb_strtolower( $product_name );
					if ( isset( $seen[ $key ] ) ) {
						continue;
					}
					$seen[ $key ] = true;
					$fees[]       = array(
						'fee_name' => $product_name,
						'amount'     => max( 0, (float) ( $item['amount'] ?? 0 ) ),
						'tax_rate'   => isset( $item['tax_rate'] ) && $item['tax_rate'] !== '' && $item['tax_rate'] !== null
							? (float) $item['tax_rate']
							: null,
					);
					continue;
				}

				$product_name = trim( (string) ( $item['product_name'] ?? '' ) );
				if ( $product_name === '' || $product_name === $service_name ) {
					continue;
				}

				if ( isset( $recurring_names[ mb_strtolower( $product_name ) ] ) ) {
					continue;
				}

				$key = mb_strtolower( $product_name );
				if ( isset( $seen[ $key ] ) ) {
					continue;
				}
				$seen[ $key ] = true;

				$fees[] = array(
					'fee_name' => $product_name,
					'amount'   => max( 0, (float) ( $item['amount'] ?? 0 ) ),
					'tax_rate' => isset( $item['tax_rate'] ) && $item['tax_rate'] !== '' && $item['tax_rate'] !== null
						? (float) $item['tax_rate']
						: null,
				);
			}

			return $fees;
		}

		/**
		 * @param int      $service_id          サービス ID。
		 * @param int|null $exclude_contract_id 除外する契約 ID。
		 * @return array<int, array{fee_name: string, amount: float, tax_rate: ?float}>
		 */
		public function default_initial_fees_for_service( $service_id, $exclude_contract_id = null ) {
			$service_id = absint( $service_id );
			if ( $service_id <= 0 ) {
				return array();
			}

			if ( class_exists( 'KTPWP_Service_Initial_Fees' ) ) {
				$service_fees = KTPWP_Service_Initial_Fees::get_by_service_id( $service_id );
				if ( ! empty( $service_fees ) ) {
					return $this->map_fee_models_to_rows( $service_fees );
				}
			}

			if ( ! class_exists( 'KTPWP_Contract_DB' ) ) {
				return array();
			}

			global $wpdb;

			$contract_table = $wpdb->prefix . 'ktp_contract';
			if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $contract_table ) ) !== $contract_table ) {
				return array();
			}

			$sql    = "SELECT id FROM {$contract_table} WHERE service_id = %d";
			$params = array( $service_id );

			if ( $exclude_contract_id !== null && (int) $exclude_contract_id > 0 ) {
				$sql     .= ' AND id <> %d';
				$params[] = (int) $exclude_contract_id;
			}

			$sql .= ' ORDER BY id DESC LIMIT 1';

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$contract_id = (int) $wpdb->get_var( $wpdb->prepare( $sql, $params ) );
			if ( $contract_id <= 0 ) {
				return array();
			}

			$db   = KTPWP_Contract_DB::get_instance();
			$fees = $db->get_initial_fees_by_contract_id( $contract_id );
			if ( empty( $fees ) ) {
				return array();
			}

			return $this->map_fee_models_to_rows( $fees );
		}

		/**
		 * @param object                             $order         受注書。
		 * @param object                             $service       サービス。
		 * @param array<int, array<string, mixed>>   $invoice_items 請求明細。
		 * @return float
		 */
		private function resolve_amount( $order, $service, $invoice_items ) {
			$service_name = trim( (string) ( $service->service_name ?? '' ) );

			foreach ( $invoice_items as $item ) {
				if ( trim( (string) ( $item['product_name'] ?? '' ) ) === $service_name ) {
					return max( 0, (float) ( $item['amount'] ?? 0 ) );
				}
			}

			if ( count( $invoice_items ) === 1 ) {
				return max( 0, (float) ( $invoice_items[0]['amount'] ?? 0 ) );
			}

			return max( 0, (float) ( $service->price ?? 0 ) );
		}

		/**
		 * @param int $service_id サービス ID。
		 * @return array<string, true>
		 */
		private function recurring_item_name_keys( $service_id ) {
			$keys = array();
			if ( ! class_exists( 'KTPWP_Contract_Recurring_Items' ) ) {
				return $keys;
			}

			foreach ( KTPWP_Contract_Recurring_Items::get_by_service_id( $service_id ) as $item ) {
				$name = trim( (string) ( $item->item_name ?? '' ) );
				if ( $name !== '' ) {
					$keys[ mb_strtolower( $name ) ] = true;
				}
			}

			return $keys;
		}

		/**
		 * @param array<string, mixed> $item 請求明細行。
		 * @return bool
		 */
		private function is_initial_fee_invoice_item( $item ) {
			$remarks = class_exists( 'KTPWP_Contract_Billing' )
				? KTPWP_Contract_Billing::INITIAL_FEE_REMARKS
				: '初回のみ';

			return trim( (string) ( $item['remarks'] ?? '' ) ) === $remarks;
		}

		/**
		 * @param array<int, object> $fees 初回費用モデル。
		 * @return array<int, array{fee_name: string, amount: float, tax_rate: ?float}>
		 */
		private function map_fee_models_to_rows( $fees ) {
			$rows = array();
			foreach ( $fees as $fee ) {
				$rows[] = array(
					'fee_name' => (string) ( $fee->fee_name ?? '' ),
					'amount'   => (float) ( $fee->amount ?? 0 ),
					'tax_rate' => isset( $fee->tax_rate ) && $fee->tax_rate !== null
						? (float) $fee->tax_rate
						: null,
				);
			}

			return $rows;
		}

		/**
		 * @param array<int, object> $items 定期明細モデル。
		 * @return array<int, array{item_name: string, amount: float, tax_rate: ?float}>
		 */
		private function map_recurring_models_to_rows( $items ) {
			$rows = array();
			foreach ( $items as $item ) {
				$rows[] = array(
					'item_name'             => (string) ( $item->item_name ?? '' ),
					'amount'                => (float) ( $item->amount ?? 0 ),
					'tax_rate'              => isset( $item->tax_rate ) && $item->tax_rate !== null
						? (float) $item->tax_rate
						: null,
					'bill_on_first_invoice' => ! empty( $item->bill_on_first_invoice ),
				);
			}

			return $rows;
		}
	}
}
