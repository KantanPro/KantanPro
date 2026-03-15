<?php
/**
 * WooCommerce 連携: WooCommerce の注文を KantanPro に自動追加
 *
 * @package KantanPro
 * @since 1.2.10
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class KTPWP_WooCommerce_Integration
 */
class KTPWP_WooCommerce_Integration {

	/**
	 * シングルトンインスタンス
	 *
	 * @var KTPWP_WooCommerce_Integration|null
	 */
	private static $instance = null;

	/**
	 * シングルトンインスタンスを取得
	 *
	 * @return KTPWP_WooCommerce_Integration
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * コンストラクタ
	 */
	private function __construct() {
		$this->init_hooks();
	}

	/**
	 * フックを登録
	 */
	private function init_hooks(): void {
		// 新規注文作成時に KantanPro に受注を追加（WC 5.6+ では woocommerce_store_api_checkout_order_processed も利用可）
		add_action( 'woocommerce_new_order', array( $this, 'sync_order_to_ktp' ), 20, 2 );
		// 注文ステータスが「処理中」になったときも同期（新規注文フックが飛ばない環境向け）
		add_action( 'woocommerce_order_status_processing', array( $this, 'sync_order_to_ktp_on_status' ), 20, 2 );
		add_action( 'woocommerce_order_status_completed', array( $this, 'sync_order_to_ktp_on_status' ), 20, 2 );
	}

	/**
	 * 注文ステータス変更時に同期（既に連携済みなら何もしない）
	 *
	 * @param int      $order_id WC 注文ID
	 * @param WC_Order $order    注文オブジェクト（WC 3.0+）
	 */
	public function sync_order_to_ktp_on_status( int $order_id, $order = null ): void {
		if ( ! $order instanceof WC_Order ) {
			$order = wc_get_order( $order_id );
		}
		if ( ! $order ) {
			return;
		}
		$this->sync_order_to_ktp( $order_id, $order );
	}

	/**
	 * WooCommerce 注文を KantanPro に同期
	 *
	 * @param int      $order_id WC 注文ID
	 * @param WC_Order $order    注文オブジェクト（省略時は get_order で取得）
	 */
	public function sync_order_to_ktp( int $order_id, $order = null ): void {
		if ( ! $order instanceof WC_Order ) {
			$order = wc_get_order( $order_id );
		}
		if ( ! $order ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'KTPWP WooCommerce: Invalid order id ' . $order_id );
			}
			return;
		}

		// 既に連携済みかチェック
		if ( $this->get_ktp_order_id_by_wc_order_id( $order_id ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'KTPWP WooCommerce: Order ' . $order_id . ' already synced to KantanPro, skip.' );
			}
			return;
		}

		$order_manager = null;
		if ( class_exists( 'KTPWP_Order' ) ) {
			$order_manager = KTPWP_Order::get_instance();
		}
		if ( ! $order_manager || ! method_exists( $order_manager, 'create_order' ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'KTPWP WooCommerce: KTPWP_Order not available.' );
			}
			return;
		}

		$customer_name = $this->get_customer_display_name( $order );
		$order_number   = $order->get_order_number();
		$project_name   = 'WooCommerce #' . $order_number;
		$created        = $order->get_date_created();
		$time           = $created ? $created->getTimestamp() : time();
		$memo           = sprintf(
			/* translators: 1: WooCommerce order number, 2: order ID */
			__( 'WooCommerce 注文 #%1$s (ID: %2$d)', 'ktpwp' ),
			$order_number,
			$order_id
		);

		$search_parts = array_filter( array( $customer_name, $project_name, $memo ) );
		$search_field = implode( ', ', $search_parts );

		$data = array(
			'time'           => $time,
			'client_id'      => null,
			'customer_name'  => $customer_name,
			'user_name'      => '',
			'project_name'   => $project_name,
			'progress'       => 1,
			'invoice_items'  => '',
			'cost_items'     => '',
			'memo'           => $memo,
			'search_field'   => $search_field,
		);

		$ktp_order_id = $order_manager->create_order( $data );
		if ( ! $ktp_order_id ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'KTPWP WooCommerce: Failed to create KantanPro order for WC order ' . $order_id );
			}
			return;
		}

		// 外部連携情報を保存
		$order_manager->update_order(
			$ktp_order_id,
			array(
				'external_source'   => 'woocommerce',
				'external_order_id' => (string) $order_id,
			)
		);

		// 請求項目: WC のラインアイテムを追加（なければ初期1件）
		$this->sync_invoice_items( $ktp_order_id, $order );

		// 初期コスト項目
		if ( class_exists( 'KTPWP_Order_Items' ) ) {
			$order_items = KTPWP_Order_Items::get_instance();
			if ( method_exists( $order_items, 'create_initial_cost_item' ) ) {
				$order_items->create_initial_cost_item( $ktp_order_id );
			}
		}

		// 初期スタッフチャット
		if ( class_exists( 'KTPWP_Staff_Chat' ) ) {
			$staff_chat = KTPWP_Staff_Chat::get_instance();
			if ( method_exists( $staff_chat, 'create_initial_chat' ) ) {
				$staff_chat->create_initial_chat( $ktp_order_id, null );
			}
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'KTPWP WooCommerce: Synced WC order ' . $order_id . ' to KantanPro order ' . $ktp_order_id );
		}
	}

	/**
	 * 注文から表示用顧客名を取得
	 *
	 * @param WC_Order $order
	 * @return string
	 */
	private function get_customer_display_name( WC_Order $order ): string {
		$company = $order->get_billing_company();
		if ( $company ) {
			return $company;
		}
		$first = $order->get_billing_first_name();
		$last  = $order->get_billing_last_name();
		$name  = trim( $first . ' ' . $last );
		return $name !== '' ? $name : __( 'ゲスト', 'ktpwp' );
	}

	/**
	 * external_order_id から KantanPro の order id を取得
	 *
	 * @param int $wc_order_id WooCommerce 注文ID
	 * @return int|null KTP order id or null
	 */
	private function get_ktp_order_id_by_wc_order_id( int $wc_order_id ): ?int {
		global $wpdb;
		$table = $wpdb->prefix . 'ktp_order';
		$col   = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM `{$table}` WHERE external_source = 'woocommerce' AND external_order_id = %s LIMIT 1",
				(string) $wc_order_id
			)
		);
		return $col !== null ? (int) $col : null;
	}

	/**
	 * WooCommerce のラインアイテムを KantanPro の請求項目として同期
	 *
	 * @param int      $ktp_order_id KantanPro 受注ID
	 * @param WC_Order $order        WooCommerce 注文
	 */
	private function sync_invoice_items( int $ktp_order_id, WC_Order $order ): void {
		global $wpdb;
		$table = $wpdb->prefix . 'ktp_order_invoice_items';

		$items = $order->get_items();
		if ( empty( $items ) ) {
			if ( class_exists( 'KTPWP_Order_Items' ) ) {
				$order_items = KTPWP_Order_Items::get_instance();
				if ( method_exists( $order_items, 'create_initial_invoice_item' ) ) {
					$order_items->create_initial_invoice_item( $ktp_order_id );
				}
			}
			return;
		}

		$sort_order = 1;
		$now        = current_time( 'mysql' );

		foreach ( $items as $item ) {
			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}
			$name     = $item->get_name();
			$qty      = (float) $item->get_quantity();
			$total    = (float) $item->get_total();
			$subtotal = (float) $item->get_subtotal();
			$price    = $qty > 0 ? $subtotal / $qty : 0;
			$amount   = $total;
			$tax      = (float) $item->get_total_tax();
			// 税率は簡易（金額ベースで逆算、0 の場合は null）
			$tax_rate = ( $total > 0 && $tax > 0 ) ? round( ( $tax / ( $total - $tax ) ) * 100, 1 ) : null;

			$row = array(
				'order_id'     => $ktp_order_id,
				'product_name' => $name,
				'price'        => $price,
				'unit'         => __( '個', 'ktpwp' ),
				'quantity'     => $qty,
				'amount'       => $amount,
				'remarks'      => '',
				'sort_order'   => $sort_order,
				'created_at'   => $now,
				'updated_at'   => $now,
			);
			$fmt = array( '%d', '%s', '%f', '%s', '%f', '%f', '%s', '%d', '%s', '%s' );
			if ( $tax_rate !== null ) {
				$row = array_merge(
					array_slice( $row, 0, 6, true ),
					array( 'tax_rate' => $tax_rate ),
					array_slice( $row, 6, null, true )
				);
				$fmt = array_merge( array_slice( $fmt, 0, 6 ), array( '%f' ), array_slice( $fmt, 6, null ) );
			}
			$wpdb->insert( $table, $row, $fmt );
			$sort_order++;
		}
	}
}
