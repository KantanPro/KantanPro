<?php
/**
 * Order management class for KTPWP plugin
 *
 * Handles order data operations (CRUD) and business logic.
 *
 * @package KTPWP
 * @subpackage Includes
 * @since 1.0.0
 * @author Kantan Pro
 * @copyright 2024 Kantan Pro
 * @license GPL-2.0+
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'KTPWP_Order' ) ) {

	/**
	 * Order management class
	 *
	 * @since 1.0.0
	 */
	class KTPWP_Order {

		/**
		 * Singleton instance
		 *
		 * @since 1.0.0
		 * @var KTPWP_Order
		 */
		private static $instance = null;

		/**
		 * Get singleton instance
		 *
		 * @since 1.0.0
		 * @return KTPWP_Order
		 */
		public static function get_instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * Constructor
		 *
		 * @since 1.0.0
		 */
		private function __construct() {
			$this->init();
		}

		/**
		 * Initialize the class
		 *
		 * @since 1.0.0
		 */
		private function init() {
			// フックの登録など初期化処理
		}

		/**
		 * Get the order table schema.
		 *
		 * @return string The SQL for creating the order table.
		 */
		public function get_schema() {
			global $wpdb;
			$table_name = $wpdb->prefix . 'ktp_order';
			$charset_collate = $wpdb->get_charset_collate();

			$sql = "CREATE TABLE {$table_name} (
				id int(11) NOT NULL AUTO_INCREMENT,
				order_number varchar(50) NOT NULL COMMENT '受注番号',
				client_id int(11) NOT NULL COMMENT 'クライアントID',
				project_name varchar(255) NOT NULL COMMENT 'プロジェクト名',
				order_date date NOT NULL COMMENT '受注日',
				desired_delivery_date date NULL DEFAULT NULL COMMENT '希望納期',
				expected_delivery_date date NULL DEFAULT NULL COMMENT '納品予定日',
				total_amount decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '合計金額',
				status varchar(20) NOT NULL DEFAULT 'pending' COMMENT 'ステータス',
				created_at timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
				updated_at timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日時',
				PRIMARY KEY  (id),
				UNIQUE KEY order_number (order_number),
				KEY client_id (client_id),
				KEY order_date (order_date)
			) {$charset_collate};";

			return $sql;
		}

		/**
		 * Create or update the order table.
		 *
		 * @since 1.0.0
		 * @return void
		 */
		public function create_order_table() {
			if ( ! function_exists( 'dbDelta' ) ) {
				require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			}
			$schema = $this->get_schema();
			dbDelta( $schema );
		}


		/**
		 * Get order by ID
		 *
		 * @since 1.0.0
		 * @param int $order_id Order ID
		 * @return object|null Order data or null if not found
		 */
		public function get_order( $order_id ) {
			if ( ! $order_id || $order_id <= 0 ) {
				return null;
			}

			global $wpdb;
			$table_name = $wpdb->prefix . 'ktp_order';

			return $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM `{$table_name}` WHERE id = %d",
                    $order_id
                )
            );
		}

		/**
		 * Get orders with filters and pagination
		 *
		 * @since 1.0.0
		 * @param array $args Query arguments
		 * @return array Orders data
		 */
		public function get_orders( $args = array() ) {
			global $wpdb;
			$table_name = $wpdb->prefix . 'ktp_order';

			$defaults = array(
				'progress'    => null,
				'client_id'   => null,
				'limit'       => 20,
				'offset'      => 0,
				'order_by'    => 'time',
				'order'       => 'DESC',
				'search'      => '',
			);

			$args = wp_parse_args( $args, $defaults );

			$where_clauses = array();
			$where_values = array();

			// Progress filter
			if ( ! is_null( $args['progress'] ) ) {
				$where_clauses[] = 'progress = %d';
				$where_values[] = $args['progress'];
			}

			// Client ID filter
			if ( ! is_null( $args['client_id'] ) ) {
				$where_clauses[] = 'client_id = %d';
				$where_values[] = $args['client_id'];
			}

			// Search filter
			if ( ! empty( $args['search'] ) ) {
				$where_clauses[] = '(customer_name LIKE %s OR user_name LIKE %s OR project_name LIKE %s OR search_field LIKE %s)';
				$search_term = '%' . $wpdb->esc_like( $args['search'] ) . '%';
				$where_values[] = $search_term;
				$where_values[] = $search_term;
				$where_values[] = $search_term;
				$where_values[] = $search_term;
			}

			$where_sql = '';
			if ( ! empty( $where_clauses ) ) {
				$where_sql = 'WHERE ' . implode( ' AND ', $where_clauses );
			}

			// Sanitize order by and order
			$allowed_order_by = array( 'id', 'time', 'customer_name', 'project_name', 'progress' );
			if ( ! in_array( $args['order_by'], $allowed_order_by ) ) {
				$args['order_by'] = 'time';
			}

			$args['order'] = strtoupper( $args['order'] );
			if ( ! in_array( $args['order'], array( 'ASC', 'DESC' ) ) ) {
				$args['order'] = 'DESC';
			}

			$sql = "SELECT * FROM `{$table_name}` {$where_sql} ORDER BY {$args['order_by']} {$args['order']} LIMIT %d OFFSET %d";

			// Add limit and offset to values
			$where_values[] = $args['limit'];
			$where_values[] = $args['offset'];

			if ( ! empty( $where_values ) ) {
				return $wpdb->get_results( $wpdb->prepare( $sql, $where_values ) );
			} else {
				return $wpdb->get_results( $sql );
			}
		}

		/**
		 * Get orders count with filters
		 *
		 * @since 1.0.0
		 * @param array $args Query arguments
		 * @return int Orders count
		 */
		public function get_orders_count( $args = array() ) {
			global $wpdb;
			$table_name = $wpdb->prefix . 'ktp_order';

			$defaults = array(
				'progress'    => null,
				'client_id'   => null,
				'search'      => '',
			);

			$args = wp_parse_args( $args, $defaults );

			$where_clauses = array();
			$where_values = array();

			// Progress filter
			if ( ! is_null( $args['progress'] ) ) {
				$where_clauses[] = 'progress = %d';
				$where_values[] = $args['progress'];
			}

			// Client ID filter
			if ( ! is_null( $args['client_id'] ) ) {
				$where_clauses[] = 'client_id = %d';
				$where_values[] = $args['client_id'];
			}

			// Search filter
			if ( ! empty( $args['search'] ) ) {
				$where_clauses[] = '(customer_name LIKE %s OR user_name LIKE %s OR project_name LIKE %s OR search_field LIKE %s)';
				$search_term = '%' . $wpdb->esc_like( $args['search'] ) . '%';
				$where_values[] = $search_term;
				$where_values[] = $search_term;
				$where_values[] = $search_term;
				$where_values[] = $search_term;
			}

			$where_sql = '';
			if ( ! empty( $where_clauses ) ) {
				$where_sql = 'WHERE ' . implode( ' AND ', $where_clauses );
			}

			$sql = "SELECT COUNT(*) FROM `{$table_name}` {$where_sql}";

			if ( ! empty( $where_values ) ) {
				return (int) $wpdb->get_var( $wpdb->prepare( $sql, $where_values ) );
			} else {
				return (int) $wpdb->get_var( $sql );
			}
		}

		/**
		 * Create new order
		 *
		 * @since 1.0.0
		 * @param array $data Order data
		 * @return int|false Order ID on success, false on failure
		 */
		public function create_order( $data ) {
			global $wpdb;
			$table_name = $wpdb->prefix . 'ktp_order';

			$defaults = array(
				'time'           => time(),
				'client_id'      => null,
				'customer_name'  => '',
				'user_name'      => '',
				'project_name'   => '',
				'progress'       => 1,
				'invoice_items'  => '',
				'cost_items'     => '',
				'memo'           => '',
				'search_field'   => '',
			);

			$data = wp_parse_args( $data, $defaults );

			// Sanitize data
			$data['customer_name'] = sanitize_text_field( $data['customer_name'] );
			$data['user_name'] = sanitize_text_field( $data['user_name'] );
			$data['project_name'] = sanitize_text_field( $data['project_name'] );
			$data['memo'] = sanitize_textarea_field( $data['memo'] );
			$data['search_field'] = sanitize_textarea_field( $data['search_field'] );

			// 受注書番号を自動生成（order_numberが設定されていない場合）
			if ( empty( $data['order_number'] ) ) {
				$timestamp = isset( $data['time'] ) ? $data['time'] : time();
				$today = date( 'Y-md', $timestamp );
				$order_number_prefix = $today . '-';
				
				// 今日の受注書数を取得して連番を生成
				$today_count = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COUNT(*) FROM `{$table_name}` WHERE order_number LIKE %s",
                        $order_number_prefix . '%'
                    )
                );
				$data['order_number'] = $order_number_prefix . str_pad( intval( $today_count ) + 1, 3, '0', STR_PAD_LEFT );
			}

			// IDはAUTO_INCREMENTで自動生成されるため明示的に設定しない

			$result = $wpdb->insert(
                $table_name,
                $data,
                array(
					'%s', // order_number
					'%d', // time
					'%d', // client_id
					'%s', // customer_name
					'%s', // user_name
					'%s', // project_name
					'%d', // progress
					'%s', // invoice_items
					'%s', // cost_items
					'%s', // memo
					'%s', // search_field
                )
			);

			if ( $result === false ) {
				error_log( 'KTPWP: Failed to create order: ' . $wpdb->last_error );
				return false;
			}

			return $wpdb->insert_id;
		}

		/**
		 * Update order
		 *
		 * @since 1.0.0
		 * @param int   $order_id Order ID
		 * @param array $data Update data
		 * @return bool True on success, false on failure
		 */
		public function update_order( $order_id, $data ) {
			if ( ! $order_id || $order_id <= 0 ) {
				return false;
			}

			global $wpdb;
			$table_name = $wpdb->prefix . 'ktp_order';

			// Remove non-updatable fields
			unset( $data['id'] );

			if ( empty( $data ) ) {
				return false;
			}

			// Sanitize updatable fields
			if ( isset( $data['customer_name'] ) ) {
				$data['customer_name'] = sanitize_text_field( $data['customer_name'] );
			}
			if ( isset( $data['user_name'] ) ) {
				$data['user_name'] = sanitize_text_field( $data['user_name'] );
			}
			if ( isset( $data['project_name'] ) ) {
				$data['project_name'] = sanitize_text_field( $data['project_name'] );
			}
			if ( isset( $data['memo'] ) ) {
				$data['memo'] = sanitize_textarea_field( $data['memo'] );
			}
			if ( isset( $data['search_field'] ) ) {
				$data['search_field'] = sanitize_textarea_field( $data['search_field'] );
			}

			$result = $wpdb->update(
                $table_name,
                $data,
                array( 'id' => $order_id ),
                null, // format determined automatically
                array( '%d' )
			);

			if ( $result === false ) {
				error_log( 'KTPWP: Failed to update order ' . $order_id . ': ' . $wpdb->last_error );
				return false;
			}

			return true;
		}

		/**
		 * Delete order
		 *
		 * @since 1.0.0
		 * @param int $order_id Order ID
		 * @return bool True on success, false on failure
		 */
		public function delete_order( $order_id ) {
			if ( ! $order_id || $order_id <= 0 ) {
				return false;
			}

			global $wpdb;
			$table_name = $wpdb->prefix . 'ktp_order';

			// First delete related data
			$this->delete_order_related_data( $order_id );

			$result = $wpdb->delete(
                $table_name,
                array( 'id' => $order_id ),
                array( '%d' )
			);

			if ( $result === false ) {
				error_log( 'KTPWP: Failed to delete order ' . $order_id . ': ' . $wpdb->last_error );
				return false;
			}

			return true;
		}

		/**
		 * Delete order related data (invoice items, cost items, staff chat)
		 *
		 * @since 1.0.0
		 * @param int $order_id Order ID
		 */
		private function delete_order_related_data( $order_id ) {
			global $wpdb;

			// Delete invoice items
			$invoice_table = $wpdb->prefix . 'ktp_order_invoice_items';
			$wpdb->delete( $invoice_table, array( 'order_id' => $order_id ), array( '%d' ) );

			// Delete cost items
			$cost_table = $wpdb->prefix . 'ktp_order_cost_items';
			$wpdb->delete( $cost_table, array( 'order_id' => $order_id ), array( '%d' ) );

			// Delete staff chat messages
			$chat_table = $wpdb->prefix . 'ktp_order_staff_chat';
			$wpdb->delete( $chat_table, array( 'order_id' => $order_id ), array( '%d' ) );
		}

		/**
		 * Get progress labels
		 *
		 * @since 1.0.0
		 * @return array Progress labels
		 */
		public function get_progress_labels() {
			return array(
				1 => esc_html__( '受付中', 'ktpwp' ),
				2 => esc_html__( '見積中', 'ktpwp' ),
				3 => esc_html__( '受注', 'ktpwp' ),
				4 => esc_html__( '完了', 'ktpwp' ),
				5 => esc_html__( '請求済', 'ktpwp' ),
				6 => esc_html__( '入金済', 'ktpwp' ),
			);
		}

		/**
		 * Get progress counts
		 *
		 * @since 1.0.0
		 * @return array Progress counts
		 */
		public function get_progress_counts() {
			global $wpdb;
			$table_name = $wpdb->prefix . 'ktp_order';

			$results = $wpdb->get_results(
                "SELECT progress, COUNT(*) as count FROM `{$table_name}` GROUP BY progress"
			);

			$counts = array();
			$progress_labels = $this->get_progress_labels();

			// Initialize all progress counts to 0
			foreach ( $progress_labels as $num => $label ) {
				$counts[ $num ] = 0;
			}

			// Set actual counts
			foreach ( $results as $result ) {
				$counts[ $result->progress ] = (int) $result->count;
			}

			return $counts;
		}

		/**
		 * Update order progress
		 *
		 * @since 1.0.0
		 * @param int $order_id Order ID
		 * @param int $progress Progress value
		 * @return bool True on success, false on failure
		 */
		public function update_progress( $order_id, $progress ) {
			if ( ! $order_id || $order_id <= 0 ) {
				return false;
			}

			$progress = absint( $progress );
			$progress_labels = $this->get_progress_labels();

			if ( ! isset( $progress_labels[ $progress ] ) ) {
				return false;
			}

			return $this->update_order( $order_id, array( 'progress' => $progress ) );
		}

		/**
		 * Update project name
		 *
		 * @since 1.0.0
		 * @param int    $order_id Order ID
		 * @param string $project_name Project name
		 * @return bool True on success, false on failure
		 */
		public function update_project_name( $order_id, $project_name ) {
			if ( ! $order_id || $order_id <= 0 ) {
				return false;
			}

			$project_name = sanitize_text_field( $project_name );

			return $this->update_order( $order_id, array( 'project_name' => $project_name ) );
		}

		/**
		 * Get the next order ID after deletion
		 *
		 * @since 1.0.0
		 * @param int $deleted_order_id Deleted order ID
		 * @return int Next order ID or 0 if no orders found
		 */
		public function get_next_order_id( $deleted_order_id ) {
			global $wpdb;
			$table_name = $wpdb->prefix . 'ktp_order';

			// Try to get the next order with higher ID
			$next_order = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM `{$table_name}` WHERE id > %d ORDER BY id ASC LIMIT 1",
                    $deleted_order_id
                )
            );

			if ( $next_order ) {
				return (int) $next_order;
			}

			// If no higher ID, get the previous order
			$prev_order = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM `{$table_name}` WHERE id < %d ORDER BY id DESC LIMIT 1",
                    $deleted_order_id
                )
            );

			if ( $prev_order ) {
				return (int) $prev_order;
			}

			// If no orders exist, get the latest order
			$latest_order = $wpdb->get_var(
                "SELECT id FROM `{$table_name}` ORDER BY time DESC LIMIT 1"
			);

			return $latest_order ? (int) $latest_order : 0;
		}
	} // End of KTPWP_Order class

} // class_exists check
