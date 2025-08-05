<?php
/**
 * Order items management class for KTPWP plugin
 *
 * Handles invoice items and cost items management.
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

if ( ! class_exists( 'KTPWP_Order_Items' ) ) {

	/**
	 * Order items management class
	 *
	 * @since 1.0.0
	 */
	class KTPWP_Order_Items {

		/**
		 * Singleton instance
		 *
		 * @since 1.0.0
		 * @var KTPWP_Order_Items
		 */
		private static $instance = null;

		/**
		 * Get singleton instance
		 *
		 * @since 1.0.0
		 * @return KTPWP_Order_Items
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
		 * Get the schema for both invoice and cost items tables.
		 *
		 * @return string The SQL for creating the tables.
		 */
		public function get_schema() {
			global $wpdb;
			$charset_collate = $wpdb->get_charset_collate();

			// Schema for invoice_items table
			$table_name_invoice = $wpdb->prefix . 'ktp_order_invoice_items';
			$sql_invoice = "CREATE TABLE {$table_name_invoice} (
				id MEDIUMINT(9) NOT NULL AUTO_INCREMENT,
				order_id MEDIUMINT(9) NOT NULL,
				product_name VARCHAR(255) NOT NULL DEFAULT '',
				price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
				unit VARCHAR(50) NOT NULL DEFAULT '',
				quantity DECIMAL(10,2) NOT NULL DEFAULT 0.00,
				amount INT(11) NOT NULL DEFAULT 0,
				tax_rate DECIMAL(5,2) NULL DEFAULT NULL,
				remarks TEXT,
				sort_order INT NOT NULL DEFAULT 0,
				created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
				updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				KEY order_id (order_id),
				KEY sort_order (sort_order)
			) {$charset_collate};";

			// Schema for cost_items table
			$table_name_cost = $wpdb->prefix . 'ktp_order_cost_items';
			$sql_cost = "CREATE TABLE {$table_name_cost} (
				id MEDIUMINT(9) NOT NULL AUTO_INCREMENT,
				order_id MEDIUMINT(9) NOT NULL,
				product_name VARCHAR(255) NOT NULL DEFAULT '',
				price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
				unit VARCHAR(50) NOT NULL DEFAULT '',
				quantity DECIMAL(10,2) NOT NULL DEFAULT 0.00,
				amount INT(11) NOT NULL DEFAULT 0,
				tax_rate DECIMAL(5,2) NOT NULL DEFAULT 10.00,
				remarks TEXT,
				purchase VARCHAR(255),
				qualified_invoice_number VARCHAR(255),
				ordered TINYINT(1) NOT NULL DEFAULT 0,
				sort_order INT NOT NULL DEFAULT 0,
				created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
				updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				KEY order_id (order_id),
				KEY sort_order (sort_order)
			) {$charset_collate};";

			return $sql_invoice . "\n" . $sql_cost;
		}

		/**
		 * Create or update the invoice items table.
		 */
		public function create_invoice_items_table() {
			if ( ! function_exists( 'dbDelta' ) ) {
				require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			}
			$schema = $this->get_schema();
			dbDelta( $schema );
		}

		/**
		 * Create or update the cost items table.
		 */
		public function create_cost_items_table() {
			if ( ! function_exists( 'dbDelta' ) ) {
				require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			}
			$schema = $this->get_schema();
			dbDelta( $schema );
		}

		/**
		 * Get invoice items for an order
		 *
		 * @since 1.0.0
		 * @param int $order_id Order ID
		 * @return array Invoice items
		 */
		public function get_invoice_items( $order_id ) {
			if ( ! $order_id || $order_id <= 0 ) {
				return array();
			}

			global $wpdb;
			$table_name = $wpdb->prefix . 'ktp_order_invoice_items';

			// テーブルの存在確認
			$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" );
			if ( ! $table_exists ) {
				error_log( "KTPWP: Invoice items table {$table_name} does not exist" );
				return array();
			}

			// sort_orderの昇順でソートして取得
			$items = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM `{$table_name}` WHERE order_id = %d ORDER BY COALESCE(sort_order, id) ASC, id ASC",
                    $order_id
                ),
                ARRAY_A
            );

			return $items ? $items : array();
		}

		/**
		 * Get cost items for an order
		 *
		 * @since 1.0.0
		 * @param int $order_id Order ID
		 * @return array Cost items
		 */
		public function get_cost_items( $order_id ) {
			if ( ! $order_id || $order_id <= 0 ) {
				return array();
			}

			global $wpdb;
			$table_name = $wpdb->prefix . 'ktp_order_cost_items';

			// sort_orderが設定されている場合はそれを使用し、設定されていない場合はid順でソート
			// これにより、ドラッグ&ドロップで並び替えた順序が維持され、新規追加項目は最後に表示される
			$items = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM `{$table_name}` WHERE order_id = %d ORDER BY COALESCE(sort_order, id) ASC, id ASC",
                    $order_id
                ),
                ARRAY_A
            );

			return $items ? $items : array();
		}

		/**
		 * Save invoice items
		 *
		 * @since 1.0.0
		 * @param int   $order_id Order ID
		 * @param array $items Invoice items data
		 * @return bool True on success, false on failure
		 */
		public function save_invoice_items( $order_id, $items ) {
			if ( ! $order_id || $order_id <= 0 || ! is_array( $items ) ) {
				return false;
			}

			global $wpdb;
			$table_name = $wpdb->prefix . 'ktp_order_invoice_items';

			// Start transaction
			$wpdb->query( 'START TRANSACTION' );

			try {
				$sort_order = 1;
				$submitted_ids = array();
				// Keep track of existing items that were submitted, even if their product_name became empty
				$existing_submitted_ids = array();

				foreach ( $items as $item ) {
					// Sanitize input data
					$item_id = isset( $item['id'] ) ? intval( $item['id'] ) : 0;
					$product_name = isset( $item['product_name'] ) ? sanitize_text_field( $item['product_name'] ) : '';
					$price = isset( $item['price'] ) ? floatval( $item['price'] ) : 0;
					$quantity = isset( $item['quantity'] ) ? floatval( $item['quantity'] ) : 0;
					$unit = isset( $item['unit'] ) ? sanitize_text_field( $item['unit'] ) : '';
					$amount = isset( $item['amount'] ) ? floatval( $item['amount'] ) : $price * $quantity; // Recalculate if not provided
					$remarks = isset( $item['remarks'] ) ? sanitize_text_field( $item['remarks'] ) : '';
					$is_provisional = isset( $item['is_provisional'] ) ? rest_sanitize_boolean( $item['is_provisional'] ) : 0;
					// 税率の処理（空文字、nullの場合はNULLとして扱う、0の場合は0として扱う）
					$tax_rate_raw = isset( $item['tax_rate'] ) ? $item['tax_rate'] : null;
					$tax_rate = null;
					if ( $tax_rate_raw !== null && $tax_rate_raw !== '' && is_numeric( $tax_rate_raw ) ) {
						$tax_rate = floatval( $tax_rate_raw );
					}

					if ( $item_id > 0 ) {
						// This is an existing item. Add its ID to existing_submitted_ids
						// so it won't be deleted by the cleanup logic later,
						// even if product_name is now empty (it will be updated with an empty name).
						$existing_submitted_ids[] = $item_id;
					}

					// 商品名が空で、かつ新規行(id=0)の場合は、まだ保存対象としないのでスキップ
					if ( empty( $product_name ) && $item_id === 0 ) {
						continue;
					}
					// 商品名が空でも既存行(id>0)の場合は、product_nameを空で更新するために処理を続ける

					$data = array(
						'order_id' => $order_id,
						'product_name' => $product_name,
						'price' => $price,
						'quantity' => $quantity,
						'unit' => $unit,
						'amount' => $amount,
						'tax_rate' => $tax_rate,
						'remarks' => $remarks,
						'is_provisional' => $is_provisional,
						'sort_order' => $sort_order,
						'updated_at' => current_time( 'mysql' ),
					);

					$format = array(
						'%d', // order_id
						'%s', // product_name
						'%f', // price
						'%f', // quantity
						'%s', // unit
						'%f', // amount
						( $tax_rate !== null ? '%f' : null ), // tax_rate
						'%s', // remarks
						'%d', // is_provisional
						'%d', // sort_order
						'%s',  // updated_at
					);

					$used_id = 0;
					if ( $item_id > 0 ) {
						// Update existing item
						$result = $wpdb->update(
                            $table_name,
                            $data,
                            array(
								'id' => $item_id,
								'order_id' => $order_id,
                            ),
                            $format,
                            array( '%d', '%d' )
						);
						$used_id = $item_id;
					} else {
						// Insert new item (only if product_name is not empty, or if it's an existing item being cleared)
						$data['created_at'] = current_time( 'mysql' );
						$format[] = '%s'; // created_at
						$result = $wpdb->insert( $table_name, $data, $format );
						if ( $result === false ) {
							error_log( 'KTPWP Error: Item INSERT failed in save_invoice_items: ' . $wpdb->last_error . ' Data: ' . print_r( $data, true ) );
						}
						$used_id = $wpdb->insert_id;
					}

					if ( $result === false ) {
						throw new Exception( 'Database operation failed: ' . $wpdb->last_error );
					}

					if ( $used_id > 0 ) {
						$submitted_ids[] = $used_id;
					}
					$sort_order++;
				}

				// Merge $submitted_ids (actually processed) and $existing_submitted_ids (all submitted existing items)
				// to ensure no existing submitted item gets deleted.
				$final_ids_to_keep = array_unique( array_merge( $submitted_ids, $existing_submitted_ids ) );

				// Remove any items that weren't in the submitted data for this order_id
				if ( ! empty( $final_ids_to_keep ) ) {
					$ids_placeholder = implode( ',', array_fill( 0, count( $final_ids_to_keep ), '%d' ) );
					$delete_query = $wpdb->prepare(
                        "DELETE FROM `{$table_name}` WHERE order_id = %d AND id NOT IN ({$ids_placeholder})",
                        array_merge( array( $order_id ), $final_ids_to_keep )
					);
					$wpdb->query( $delete_query );
				} else {
					// Delete all items for this order_id ONLY IF the initial $items array was empty.
					// This prevents deleting all items if $items contained only new rows with empty product_names
					// which were then skipped.
					if ( empty( $items ) ) {
						$wpdb->delete( $table_name, array( 'order_id' => $order_id ), array( '%d' ) );
					}
				}

				// Commit transaction
				$wpdb->query( 'COMMIT' );
				return true;

			} catch ( Exception $e ) {
				// Rollback transaction
				$wpdb->query( 'ROLLBACK' );
				error_log( 'KTPWP: Failed to save invoice items: ' . $e->getMessage() );
				return false;
			}
		}

		/**
		 * Save cost items
		 *
		 * @since 1.0.0
		 * @param int   $order_id Order ID
		 * @param array $items Cost items data
		 * @param bool  $force_save 商品名が空でも保存する場合true（協力会社職能からの追加・更新用）
		 * @return bool True on success, false on failure
		 */
		public function save_cost_items( $order_id, $items, $force_save = false ) {
			if ( ! $order_id || $order_id <= 0 || ! is_array( $items ) ) {
				return false;
			}

			global $wpdb;
			$table_name = $wpdb->prefix . 'ktp_order_cost_items';

			// Ajax送信データのキー補正（unit_price→price など）
			foreach ( $items as &$item ) {
				if ( isset( $item['unit_price'] ) && ! isset( $item['price'] ) ) {
					$item['price'] = $item['unit_price'];
				}
				if ( isset( $item['frequency'] ) && ! isset( $item['remarks'] ) ) {
					$item['remarks'] = 'frequency:' . $item['frequency'];
				}
			}
			unset( $item );

			// Start transaction
			$wpdb->query( 'START TRANSACTION' );

			try {
				$sort_order = 1;
				$submitted_ids = array();
				$existing_submitted_ids = array();

				foreach ( $items as $item ) {
					// Sanitize input data
					$item_id = isset( $item['id'] ) ? intval( $item['id'] ) : 0;
					$product_name = isset( $item['product_name'] ) ? sanitize_text_field( $item['product_name'] ) : '';
					$price = isset( $item['price'] ) ? floatval( $item['price'] ) : 0;
					$quantity = isset( $item['quantity'] ) ? floatval( $item['quantity'] ) : 0;
					$unit = isset( $item['unit'] ) ? sanitize_text_field( $item['unit'] ) : '';
					$amount = isset( $item['amount'] ) ? floatval( $item['amount'] ) : $price * $quantity;
					// 税率の処理（空文字、nullの場合はNULLとして扱う、0の場合は0として扱う）
					$tax_rate_raw = isset( $item['tax_rate'] ) ? $item['tax_rate'] : null;
					$tax_rate = null;
					if ( $tax_rate_raw !== null && $tax_rate_raw !== '' && is_numeric( $tax_rate_raw ) ) {
						$tax_rate = floatval( $tax_rate_raw );
					}
					$remarks = isset( $item['remarks'] ) ? sanitize_textarea_field( $item['remarks'] ) : '';
					// 備考欄が「0」の場合は空文字列として扱う
					if ( $remarks === '0' ) {
						$remarks = '';
					}
					$purchase = isset( $item['purchase'] ) ? sanitize_text_field( $item['purchase'] ) : '';
					$supplier_id = isset( $item['supplier_id'] ) ? intval( $item['supplier_id'] ) : null;

					// 商品名が空ならスキップ（force_save時は保存）
					if ( empty( $product_name ) && ! $force_save ) {
						continue;
					}

					$data = array(
						'order_id' => $order_id,
						'product_name' => $product_name,
						'price' => $price,
						'unit' => $unit,
						'quantity' => $quantity,
						'amount' => $amount,
						'tax_rate' => $tax_rate, // 税率対応
						'remarks' => $remarks,
						'purchase' => $purchase,
						'updated_at' => current_time( 'mysql' ),
					);
					$format = array( '%d', '%s', '%f', '%s', '%f', '%f', ( $tax_rate !== null ? '%f' : null ), '%s', '%s', '%s' );

					// supplier_idカラムが存在する場合のみ追加
					$columns = $wpdb->get_col( "SHOW COLUMNS FROM `{$table_name}` LIKE 'supplier_id'", 0 );
					if ( ! empty( $columns ) ) {
						$data['supplier_id'] = $supplier_id;
						$format[] = '%d';
					}

					$used_id = 0;
					if ( $item_id > 0 ) {
						// Update existing item - sort_orderを設定
						$data['sort_order'] = $sort_order;
						$format[] = '%d';
						$result = $wpdb->update(
                            $table_name,
                            $data,
                            array(
								'id' => $item_id,
								'order_id' => $order_id,
                            ),
                            $format,
                            array( '%d', '%d' )
						);
						$used_id = $item_id;
					} else {
						// Insert new item - 新規作成時は現在の最大sort_order値+1を設定
						// まず現在の最大sort_order値を取得
						$max_sort_order = $wpdb->get_var(
                            $wpdb->prepare(
                                "SELECT COALESCE(MAX(sort_order), 0) FROM `{$table_name}` WHERE order_id = %d",
                                $order_id
                            )
                        );
						$new_sort_order = intval( $max_sort_order ) + 1;

						$data['sort_order'] = $new_sort_order;
						$data['created_at'] = current_time( 'mysql' );
						$format[] = '%d'; // sort_order
						$format[] = '%s'; // created_at
						$result = $wpdb->insert( $table_name, $data, $format );
						if ( $result === false ) {
							error_log( 'KTPWP Error: Cost item INSERT failed: ' . $wpdb->last_error );
						}
						$used_id = $wpdb->insert_id;
					}

					if ( $result === false ) {
						throw new Exception( 'Database operation failed: ' . $wpdb->last_error );
					}

					if ( $used_id > 0 ) {
						$submitted_ids[] = $used_id;
					}

					$sort_order++;
				}

				$final_ids_to_keep = array_unique( array_merge( $submitted_ids, $existing_submitted_ids ) );

				if ( ! empty( $final_ids_to_keep ) ) {
					$ids_placeholder = implode( ',', array_fill( 0, count( $final_ids_to_keep ), '%d' ) );
					$delete_query = $wpdb->prepare(
                        "DELETE FROM `{$table_name}` WHERE order_id = %d AND id NOT IN ({$ids_placeholder})",
                        array_merge( array( $order_id ), $final_ids_to_keep )
					);
					$wpdb->query( $delete_query );
				} elseif ( empty( $items ) ) {
						$wpdb->delete( $table_name, array( 'order_id' => $order_id ), array( '%d' ) );
				}

				$wpdb->query( 'COMMIT' );
				return true;

			} catch ( Exception $e ) {
				$wpdb->query( 'ROLLBACK' );
				error_log( 'KTPWP: Failed to save cost items: ' . $e->getMessage() );
				return false;
			}
		}

		/**
		 * コスト項目テーブルに supplier_id カラムがなければ自動追加
		 *
		 * @since 1.1.3
		 * @return bool true: 追加済み/既存, false: 追加失敗
		 */
		public function add_supplier_id_column_if_missing() {
			global $wpdb;
			$table_name = $wpdb->prefix . 'ktp_order_cost_items';
			$column = $wpdb->get_results(
                "SHOW COLUMNS FROM `{$table_name}` LIKE 'supplier_id'"
			);
			if ( empty( $column ) ) {
				$result = $wpdb->query( "ALTER TABLE `{$table_name}` ADD COLUMN supplier_id INT(11) DEFAULT NULL AFTER order_id" );
				if ( $result === false ) {
					error_log( 'KTPWP: supplier_idカラムの自動追加に失敗: ' . $wpdb->last_error );
					return false;
				} else {
					error_log( 'KTPWP: supplier_idカラムを自動追加しました' );
				}
			}
			return true;
		}

		/**
		 * Create initial invoice item for new order
		 *
		 * @since 1.0.0
		 * @param int $order_id Order ID
		 * @return bool True on success, false on failure
		 */
		public function create_initial_invoice_item( $order_id ) {
			if ( ! $order_id || $order_id <= 0 ) {
				return false;
			}

			global $wpdb;
			$table_name = $wpdb->prefix . 'ktp_order_invoice_items';

			$data = array(
				'order_id' => $order_id,
				'product_name' => '',
				'price' => 0,
				'unit' => '式',
				'quantity' => 1,
				'amount' => 0,
				'tax_rate' => null, // デフォルトは税率なし
				'remarks' => '',
				'sort_order' => 1,
				'created_at' => current_time( 'mysql' ),
				'updated_at' => current_time( 'mysql' ),
			);

			$result = $wpdb->insert(
                $table_name,
                $data,
                array( '%d', '%s', '%f', '%s', '%f', '%f', null, '%s', '%d', '%s', '%s' )
			);

			if ( $result === false ) {
				error_log( 'KTPWP: Failed to create initial invoice item: ' . $wpdb->last_error );
				return false;
			}

			return true;
		}

		/**
		 * Create initial cost item for new order
		 *
		 * @since 1.0.0
		 * @param int $order_id Order ID
		 * @return bool True on success, false on failure
		 */
		public function create_initial_cost_item( $order_id ) {
			if ( ! $order_id || $order_id <= 0 ) {
				return false;
			}

			global $wpdb;
			$table_name = $wpdb->prefix . 'ktp_order_cost_items';

			$data = array(
				'order_id' => $order_id,
				'product_name' => '',
				'price' => 0,
				'unit' => '式',
				'quantity' => 1,
				'amount' => 0,
				'tax_rate' => null, // デフォルトは税率なし
				'remarks' => '',
				'sort_order' => 1,
				'created_at' => current_time( 'mysql' ),
				'updated_at' => current_time( 'mysql' ),
			);

			$result = $wpdb->insert(
                $table_name,
                $data,
                array( '%d', '%s', '%f', '%s', '%f', '%f', null, '%s', '%d', '%s', '%s' )
			);

			if ( $result === false ) {
				error_log( 'KTPWP: Failed to create initial cost item: ' . $wpdb->last_error );
				return false;
			}

			return true;
		}

		/**
		 * Delete all invoice items for an order
		 *
		 * @since 1.0.0
		 * @param int $order_id Order ID
		 * @return bool True on success, false on failure
		 */
		public function delete_invoice_items( $order_id ) {
			if ( ! $order_id || $order_id <= 0 ) {
				return false;
			}

			global $wpdb;
			$table_name = $wpdb->prefix . 'ktp_order_invoice_items';

			$result = $wpdb->delete(
                $table_name,
                array( 'order_id' => $order_id ),
                array( '%d' )
			);

			if ( $result === false ) {
				error_log( 'KTPWP: Failed to delete invoice items: ' . $wpdb->last_error );
				return false;
			}

			return true;
		}

		/**
		 * Delete all cost items for an order
		 *
		 * @since 1.0.0
		 * @param int $order_id Order ID
		 * @return bool True on success, false on failure
		 */
		public function delete_cost_items( $order_id ) {
			if ( ! $order_id || $order_id <= 0 ) {
				return false;
			}

			global $wpdb;
			$table_name = $wpdb->prefix . 'ktp_order_cost_items';

			$result = $wpdb->delete(
                $table_name,
                array( 'order_id' => $order_id ),
                array( '%d' )
			);

			if ( $result === false ) {
				error_log( 'KTPWP: Failed to delete cost items: ' . $wpdb->last_error );
				return false;
			}

			return true;
		}

		/**
		 * Update single item field (for Ajax auto-save)
		 *
		 * @since 1.0.0
		 * @param string $item_type Item type ('invoice' or 'cost')
		 * @param int    $item_id Item ID
		 * @param string $field_name Field name
		 * @param mixed  $field_value Field value
		 * @return bool True on success, false on failure
		 */
		public function update_item_field( $item_type, $item_id, $field_name, $field_value ) {
			if ( ! in_array( $item_type, array( 'invoice', 'cost' ) ) || ! $item_id || $item_id <= 0 ) {
				return false;
			}

			global $wpdb;
			$table_name = $wpdb->prefix . 'ktp_order_' . $item_type . '_items';

			// テーブルの存在確認
			$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" );
			if ( ! $table_exists ) {
				error_log( "KTPWP: Table {$table_name} does not exist" );
				return false;
			}

			// 現在の値を取得して変更があったかチェック
			$current_value = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT {$field_name} FROM `{$table_name}` WHERE id = %d",
                    $item_id
                )
            );

			// 値の比較（型を考慮）
			$value_changed = false;
			switch ( $field_name ) {
				case 'product_name':
				case 'unit':
				case 'remarks':
				case 'purchase':
					$value_changed = (string) $current_value !== (string) $field_value;
					break;
				case 'price':
				case 'quantity':
				case 'amount':
					$value_changed = abs( (float) $current_value - (float) $field_value ) > 0.001; // 小数点の誤差を考慮
					break;
				case 'tax_rate':
					// 税率の比較（NULL値と空文字を適切に処理、0は0として扱う）
					$current_tax_rate = ( $current_value === null || $current_value === '' ) ? null : (float) $current_value;
					$new_tax_rate = ( $field_value === null || $field_value === '' ) ? null : (float) $field_value;
					$value_changed = $current_tax_rate !== $new_tax_rate;
					break;
				case 'sort_order':
				case 'supplier_id':
					$value_changed = (int) $current_value !== (int) $field_value;
					break;
				default:
					$value_changed = $current_value !== $field_value;
			}

			// 値が変更されていない場合は早期リターン
			if ( ! $value_changed ) {
				return array(
					'success' => true,
					'value_changed' => false,
				);
			}

			// Determine field update data based on field name
			$update_data = array();
			$format = array();

			switch ( $field_name ) {
				case 'product_name':
					$update_data['product_name'] = sanitize_text_field( $field_value );
					$format[] = '%s';
					break;
				case 'price':
					$update_data['price'] = floatval( $field_value );
					$format[] = '%f';
					break;
				case 'quantity':
					$update_data['quantity'] = floatval( $field_value );
					$format[] = '%f';
					break;
				case 'unit':
					$update_data['unit'] = sanitize_text_field( $field_value );
					$format[] = '%s';
					break;
				case 'amount':
					$update_data['amount'] = floatval( $field_value );
					$format[] = '%f';
					break;
				case 'tax_rate':
					// 税率の処理（空文字、nullの場合はNULLとして扱う、0の場合は0として扱う）
					if ( $field_value === null || $field_value === '' ) {
						$update_data['tax_rate'] = null;
						// NULL値の場合はフォーマットを指定しない（MySQLが自動的にNULLとして扱う）
					} else {
						$update_data['tax_rate'] = floatval( $field_value );
						$format[] = '%f';
					}
					break;
				case 'remarks':
					$remarks_value = sanitize_textarea_field( $field_value );
					// 備考欄が「0」の場合は空文字列として扱う
					if ( $remarks_value === '0' ) {
						$remarks_value = '';
					}
					$update_data['remarks'] = $remarks_value;
					$format[] = '%s';
					break;
				case 'purchase':
					// purchaseフィールドはcost itemsのみで使用
					if ( $item_type === 'cost' ) {
						$update_data['purchase'] = sanitize_text_field( $field_value );
						$format[] = '%s';
					} else {
						// invoice itemsではpurchaseフィールドを無視
						error_log( 'KTPWP: Attempted to update purchase field for invoice item - ignoring' );
						return array(
							'success' => true,
							'value_changed' => false,
						);
					}
					break;
				case 'sort_order':
					$update_data['sort_order'] = intval( $field_value );
					$format[] = '%d';
					break;
				case 'supplier_id':
					// supplier_idカラムはcost itemsのみで使用
					if ( $item_type === 'cost' ) {
						$columns = $wpdb->get_col( "SHOW COLUMNS FROM `{$table_name}` LIKE 'supplier_id'", 0 );
						if ( ! empty( $columns ) ) {
							$update_data['supplier_id'] = intval( $field_value ) ?: null;
							$format[] = '%d';
						}
					} else {
						// invoice itemsではsupplier_idフィールドを無視
						error_log( 'KTPWP: Attempted to update supplier_id field for invoice item - ignoring' );
						return array(
							'success' => true,
							'value_changed' => false,
						);
					}
					break;
				default:
					return array(
						'success' => false,
						'value_changed' => false,
					);
			}

			// Always update the updated_at timestamp
			$update_data['updated_at'] = current_time( 'mysql' );
			$format[] = '%s';

			$result = $wpdb->update(
                $table_name,
                $update_data,
                array( 'id' => $item_id ),
                $format,
                array( '%d' )
			);

			if ( $result === false ) {
				error_log( 'KTPWP: Failed to update item field: ' . $wpdb->last_error );
				return array(
					'success' => false,
					'value_changed' => false,
				);
			}

			return array(
				'success' => true,
				'value_changed' => true,
			);
		}

		/**
		 * Get item field value
		 *
		 * @since 1.0.0
		 * @param string $item_type Item type ('invoice' or 'cost')
		 * @param int    $item_id Item ID
		 * @param string $field_name Field name
		 * @return mixed Field value or null if not found
		 */
		public function get_item_field_value( $item_type, $item_id, $field_name ) {
			if ( ! in_array( $item_type, array( 'invoice', 'cost' ) ) || ! $item_id || $item_id <= 0 ) {
				return null;
			}

			global $wpdb;
			$table_name = $wpdb->prefix . 'ktp_order_' . $item_type . '_items';

			// テーブルの存在確認
			$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" );
			if ( ! $table_exists ) {
				error_log( "KTPWP: Table {$table_name} does not exist" );
				return null;
			}

			// フィールドの存在確認
			$columns = $wpdb->get_col( "SHOW COLUMNS FROM `{$table_name}`", 0 );
			if ( ! in_array( $field_name, $columns ) ) {
				error_log( "KTPWP: Field {$field_name} does not exist in table {$table_name}" );
				return null;
			}

			$value = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT {$field_name} FROM `{$table_name}` WHERE id = %d",
                    $item_id
                )
            );

			return $value;
		}

		/**
		 * Create new item (for Ajax)
		 *
		 * @since 1.0.0
		 * @param string $item_type Item type ('invoice' or 'cost')
		 * @param int    $order_id Order ID
		 * @param string $initial_field_name Initial field name (e.g., 'product_name')
		 * @param string $initial_field_value Initial field value
		 * @param string $request_id Request ID for duplicate prevention
		 * @return int|false Item ID on success, false on failure
		 */
		public function create_new_item( $item_type, $order_id, $initial_field_name = null, $initial_field_value = null, $request_id = null ) {
			if ( ! in_array( $item_type, array( 'invoice', 'cost' ) ) || ! $order_id || $order_id <= 0 ) {
				return false;
			}

			// 重複リクエスト防止（リクエストIDが提供された場合）
			if ( $request_id ) {
				$transient_key = 'ktp_create_item_' . md5( $request_id );
				if ( get_transient( $transient_key ) ) {
					error_log( "[KTPWP] Duplicate create_new_item request blocked: {$request_id}" );
					return false;
				}
				// 30秒間のロックを設定
				set_transient( $transient_key, time(), 30 );
			}

			global $wpdb;
			$table_name = $wpdb->prefix . 'ktp_order_' . $item_type . '_items';
			
			error_log( "[KTPWP] create_new_item called: item_type={$item_type}, order_id={$order_id}, initial_field_name={$initial_field_name}, initial_field_value={$initial_field_value}, request_id={$request_id}" );

			// 新規作成時は現在の最大sort_order値+1を設定
			$max_sort_order = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COALESCE(MAX(sort_order), 0) FROM `{$table_name}` WHERE order_id = %d",
                    $order_id
                )
            );
			$new_sort_order = intval( $max_sort_order ) + 1;

			// 基本データ
			$data = array(
				'order_id' => $order_id,
				'product_name' => '',
				'price' => 0,
				'quantity' => 1,
				'unit' => '式',
				'amount' => 0,
				'tax_rate' => null, // デフォルトは税率なし
				'remarks' => '',
				'sort_order' => $new_sort_order,
				'created_at' => current_time( 'mysql' ),
				'updated_at' => current_time( 'mysql' ),
			);

			// 初期値が指定されている場合は設定
			if ( $initial_field_name && $initial_field_value !== null ) {
				switch ( $initial_field_name ) {
					case 'product_name':
						$data['product_name'] = sanitize_text_field( $initial_field_value );
						break;
					case 'price':
						$data['price'] = floatval( $initial_field_value );
						break;
					case 'unit':
						$data['unit'] = sanitize_text_field( $initial_field_value );
						break;
					case 'quantity':
						$data['quantity'] = floatval( $initial_field_value );
						break;
					case 'tax_rate':
						// 税率の処理（空文字、null、0の場合はNULLとして扱う）
						if ( $initial_field_value === null || $initial_field_value === '' || $initial_field_value === '0' ) {
							$data['tax_rate'] = null;
						} else {
							$data['tax_rate'] = floatval( $initial_field_value );
						}
						break;
					case 'remarks':
						$data['remarks'] = sanitize_textarea_field( $initial_field_value );
						break;
				}
			}

			// cost itemsの場合のみ追加フィールドを設定
			if ( $item_type === 'cost' ) {
				$data['purchase'] = '';
				$data['ordered'] = 0;
				
				// supplier_idカラムが存在する場合のみ追加
				$columns = $wpdb->get_col( "SHOW COLUMNS FROM `{$table_name}` LIKE 'supplier_id'", 0 );
				if ( ! empty( $columns ) ) {
					$data['supplier_id'] = null;
				}
			}
			
			// 税率カラムが存在するかチェックし、存在しない場合は削除
			$columns = $wpdb->get_col( "SHOW COLUMNS FROM `{$table_name}`", 0 );
			if ( ! in_array( 'tax_rate', $columns ) ) {
				unset( $data['tax_rate'] );
			}

			// フォーマット配列を動的に生成
			$format = array();
			foreach ( $data as $value ) {
				if ( $value === null ) {
					$format[] = null; // NULL値の場合はフォーマットもnull
				} else {
					$format[] = '%s'; // デフォルトは文字列
				}
			}
			
			// 特定のフィールドのフォーマットを調整
			foreach ( $data as $key => $value ) {
				$index = array_search( $key, array_keys( $data ) );
				if ( $index !== false ) {
					switch ( $key ) {
						case 'order_id':
						case 'sort_order':
						case 'ordered':
							$format[$index] = '%d';
							break;
						case 'price':
						case 'quantity':
						case 'amount':
						case 'tax_rate':
							// 税率がNULLの場合はフォーマットもNULLのままにする
							if ( $value !== null ) {
								$format[$index] = '%f';
							}
							break;
						case 'supplier_id':
							$format[$index] = '%d';
							break;
						default:
							$format[$index] = '%s';
							break;
					}
				}
			}
			
			error_log( "[KTPWP] create_new_item INSERT debug: table={$table_name}, data_count=" . count($data) . ", format_count=" . count($format) . ", data=" . print_r($data, true) . ", format=" . print_r($format, true) );
			
			$result = $wpdb->insert(
                $table_name,
                $data,
                $format
			);

			if ( $result === false ) {
				error_log( 'KTPWP: Failed to create new item: ' . $wpdb->last_error );
				error_log( 'KTPWP: Last SQL Query: ' . $wpdb->last_query );
				// 失敗時はトランジェントを削除
				if ( $request_id ) {
					$transient_key = 'ktp_create_item_' . md5( $request_id );
					delete_transient( $transient_key );
				}
				return false;
			}

			$new_item_id = $wpdb->insert_id;
			
			// 成功時のログ
			error_log( "[KTPWP] create_new_item success: new_item_id={$new_item_id}, item_type={$item_type}, order_id={$order_id}" );
			
			// 成功時はトランジェントを即座に削除（処理完了のため）
			if ( $request_id ) {
				$transient_key = 'ktp_create_item_' . md5( $request_id );
				delete_transient( $transient_key );
			}
			
			return $new_item_id;
		}

		/**
		 * Delete a single item (for Ajax)
		 *
		 * @since 1.0.0 // このメソッドのバージョン。必要に応じて更新してください。
		 * @param string $item_type Item type (\'invoice\' or \'cost\')
		 * @param int    $item_id Item ID to delete
		 * @param int    $order_id Order ID (for verification, optional but recommended)
		 * @return bool True on success, false on failure
		 */
		public function delete_item( $item_type, $item_id, $order_id ) {
			error_log( "[KTPWP_Order_Items] delete_item called with: item_type={$item_type}, item_id={$item_id}, order_id={$order_id}" );

			if ( ! in_array( $item_type, array( 'invoice', 'cost' ) ) ) {
				error_log( "[KTPWP_Order_Items] delete_item: Invalid item_type: {$item_type}" );
				return false;
			}
			if ( ! $item_id || $item_id <= 0 ) {
				error_log( "[KTPWP_Order_Items] delete_item: Invalid item_id: {$item_id}" );
				return false;
			}
			if ( ! $order_id || $order_id <= 0 ) {
				error_log( "[KTPWP_Order_Items] delete_item: Invalid order_id: {$order_id}" );
				return false;
			}

			global $wpdb;
			$table_name = $wpdb->prefix . 'ktp_order_' . $item_type . '_items';

			// アイテムが存在し、指定されたorder_idに属するかどうかを確認 (オプションだが推奨)
			$item_exists = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM `{$table_name}` WHERE id = %d AND order_id = %d",
                    $item_id,
                    $order_id
                )
            );

			if ( ! $item_exists ) {
				error_log( "[KTPWP_Order_Items] delete_item: Item not found or does not belong to order. item_id={$item_id}, order_id={$order_id}, table_name={$table_name}" );
				return false; // アイテムが存在しないか、order_idが一致しない
			}

			$result = $wpdb->delete(
                $table_name,
                array(
					'id' => $item_id,
					'order_id' => $order_id,
                ), // order_id も条件に加えることでより安全に
                array( '%d', '%d' )
			);

			if ( $result === false ) {
				error_log( "[KTPWP_Order_Items] delete_item: Failed to delete item from {$table_name}. item_id={$item_id}, order_id={$order_id}. DB Error: " . $wpdb->last_error );
				return false;
			}

			if ( $result === 0 ) {
				// 削除対象の行が見つからなかった場合 (既に削除されているか、条件に一致しなかった)
				// $item_exists チェックがあるので、ここに来る場合は稀だが、念のためログに残す
				error_log( "[KTPWP_Order_Items] delete_item: No rows deleted from {$table_name}. item_id={$item_id}, order_id={$order_id}. Item might have been already deleted or conditions not met." );
				// このケースを成功とみなすか失敗とみなすかは要件によるが、ここではfalseを返す
				return false;
			}

			error_log( "[KTPWP_Order_Items] delete_item: Successfully deleted item_id={$item_id} from {$table_name} for order_id={$order_id}. Rows affected: {$result}" );
			return true;
		}

		/**
		 * Update the sort order of items (for Ajax drag-and-drop)
		 *
		 * @since 1.0.0
		 * @param string $item_type Item type ('invoice' or 'cost')
		 * @param int    $order_id Order ID
		 * @param array  $items An array of items, where each item is an associative array like ['id' => 'item_id', 'sort_order' => 'new_sort_order']
		 * @return bool True on success, false on failure
		 */
		public function update_items_order( $item_type, $order_id, $items ) {
			error_log( "[KTPWP_Order_Items] update_items_order called with: item_type={$item_type}, order_id={$order_id}, items_count=" . count( $items ) );
			error_log( '[KTPWP_Order_Items] Items data: ' . print_r( $items, true ) );

			if ( ! in_array( $item_type, array( 'invoice', 'cost' ) ) ) {
				error_log( "[KTPWP_Order_Items] update_items_order: Invalid item_type: {$item_type}" );
				return false;
			}
			if ( ! $order_id || $order_id <= 0 ) {
				error_log( "[KTPWP_Order_Items] update_items_order: Invalid order_id: {$order_id}" );
				return false;
			}
			if ( ! is_array( $items ) || empty( $items ) ) {
				error_log( '[KTPWP_Order_Items] update_items_order: Items array is empty or not an array.' );
				return false;
			}

			global $wpdb;
			$table_name = $wpdb->prefix . 'ktp_order_' . $item_type . '_items';

			// テーブルが存在するかチェック
			$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) === $table_name;
			if ( ! $table_exists ) {
				error_log( "[KTPWP_Order_Items] update_items_order: Table {$table_name} does not exist" );
				return false;
			}

			// トランザクション開始
			$wpdb->query( 'START TRANSACTION' );
			$all_successful = true;
			$updated_count = 0;
			$error_details = array();

			foreach ( $items as $index => $item ) {
				if ( ! isset( $item['id'] ) || ! isset( $item['sort_order'] ) ) {
					$error_details[] = "Item at index {$index} missing id or sort_order: " . print_r( $item, true );
					error_log( '[KTPWP_Order_Items] update_items_order: Skipping item due to missing id or sort_order. Item data: ' . print_r( $item, true ) );
					continue; // Skip if data is incomplete
				}

				$item_id = intval( $item['id'] );
				$sort_order = intval( $item['sort_order'] );

				if ( $item_id <= 0 ) {
					$error_details[] = "Invalid item_id {$item_id} at index {$index}";
					error_log( "[KTPWP_Order_Items] update_items_order: Invalid item_id {$item_id} for order_id {$order_id}." );
					$all_successful = false;
					break;
				}

				if ( $sort_order <= 0 ) {
					$error_details[] = "Invalid sort_order {$sort_order} for item_id {$item_id}";
					error_log( "[KTPWP_Order_Items] update_items_order: Invalid sort_order {$sort_order} for item_id {$item_id}" );
					$all_successful = false;
					break;
				}

				error_log( "[KTPWP_Order_Items] Updating item_id: {$item_id} to sort_order: {$sort_order} in table: {$table_name} for order_id: {$order_id}" );

				// アイテムが存在し、指定されたorder_idに属するかどうかを確認
				$item_exists = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COUNT(*) FROM `{$table_name}` WHERE id = %d AND order_id = %d",
                        $item_id,
                        $order_id
                    )
                );

				if ( ! $item_exists ) {
					$error_details[] = "Item {$item_id} not found or does not belong to order {$order_id}";
					error_log( "[KTPWP_Order_Items] update_items_order: Item {$item_id} not found or does not belong to order {$order_id}" );
					$all_successful = false;
					break;
				}

				$result = $wpdb->update(
                    $table_name,
                    array(
						'sort_order' => $sort_order,
						'updated_at' => current_time( 'mysql' ),
                    ),
                    array(
						'id' => $item_id,
						'order_id' => $order_id,
                    ), // Ensure item belongs to the order
                    array( '%d', '%s' ), // Format for data
                    array( '%d', '%d' )  // Format for where
				);

				if ( $result === false ) {
					$error_details[] = "Database error updating item {$item_id}: " . $wpdb->last_error;
					error_log( "[KTPWP_Order_Items] update_items_order: Failed to update sort_order for item_id={$item_id}, order_id={$order_id}. DB Error: " . $wpdb->last_error );
					$all_successful = false;
					break; // Exit loop on first error
				}

				if ( $result === 0 ) {
					error_log( "[KTPWP_Order_Items] update_items_order: No rows affected for item_id={$item_id}, order_id={$order_id}. Item might not exist or sort_order was already correct." );
				} else {
					$updated_count++;
					error_log( "[KTPWP_Order_Items] update_items_order: Successfully updated item_id={$item_id} to sort_order={$sort_order}. Rows affected: {$result}" );
				}
			}

			if ( $all_successful ) {
				$wpdb->query( 'COMMIT' );
				error_log( "[KTPWP_Order_Items] update_items_order: Successfully updated sort order for {$updated_count} items for order_id={$order_id}." );
				return true;
			} else {
				$wpdb->query( 'ROLLBACK' );
				error_log( "[KTPWP_Order_Items] update_items_order: Transaction rolled back due to errors for order_id={$order_id}. Error details: " . implode( '; ', $error_details ) );
				return false;
			}
		}
	} // End of KTPWP_Order_Items class

} // class_exists check
