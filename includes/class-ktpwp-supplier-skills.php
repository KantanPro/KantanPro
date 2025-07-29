<?php
/**
 * Supplier Skills Class for KTPWP plugin
 *
 * Handles supplier skills/capabilities data management including table creation,
 * data operations (CRUD), and linking with supplier data.
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

if ( ! class_exists( 'KTPWP_Supplier_Skills' ) ) {

	/**
	 * Supplier Skills class for managing supplier capabilities/services
	 *
	 * This class manages the skills/services/products that each supplier can provide.
	 * Each skill is linked to a specific supplier ID and automatically managed
	 * when suppliers are added or deleted.
	 *
	 * @since 1.0.0
	 */
	class KTPWP_Supplier_Skills {

		/**
		 * Instance of this class
		 *
		 * @var KTPWP_Supplier_Skills
		 */
		private static $instance = null;

		/**
		 * Get singleton instance
		 *
		 * @return KTPWP_Supplier_Skills
		 */
		public static function get_instance() {
			if ( self::$instance === null ) {
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
			// Hook into supplier deletion to clean up skills
			add_action( 'ktpwp_supplier_deleted', array( $this, 'delete_supplier_skills' ), 10, 1 );
		}

		/**
		 * Create supplier skills table
		 *
		 * @since 1.0.0
		 * @return bool True on success, false on failure
		 */
		public function create_table() {
			global $wpdb;

			$table_name = $wpdb->prefix . 'ktp_supplier_skills';
			$my_table_version = '3.4.0'; // バージョンを更新（税率NULL許可対応）
			$option_name = 'ktp_supplier_skills_table_version';

			// Check if table needs to be created or updated
			$installed_version = get_option( $option_name );

			if ( $installed_version !== $my_table_version ) {
				$charset_collate = $wpdb->get_charset_collate();

				$sql = "CREATE TABLE {$table_name} (
                id MEDIUMINT(9) NOT NULL AUTO_INCREMENT,
                supplier_id MEDIUMINT(9) NOT NULL,
                product_name VARCHAR(255) NOT NULL DEFAULT '' COMMENT '商品名',
                unit_price DECIMAL(20,10) NOT NULL DEFAULT 0 COMMENT '単価',
                quantity INT NOT NULL DEFAULT 1 COMMENT '数量',
                unit VARCHAR(50) NOT NULL DEFAULT '式' COMMENT '単位',
                tax_rate DECIMAL(5,2) NULL DEFAULT 10.00 COMMENT '税率',
                frequency INT NOT NULL DEFAULT 0 COMMENT '頻度',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
                PRIMARY KEY (id),
                KEY supplier_id (supplier_id),
                KEY product_name (product_name),
                KEY unit_price (unit_price),
                KEY frequency (frequency)
            ) {$charset_collate}";

				// Include upgrade functions
				require_once ABSPATH . 'wp-admin/includes/upgrade.php';

				if ( function_exists( 'dbDelta' ) ) {
					$result = dbDelta( $sql );

					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( 'KTPWP: Supplier Skills table creation result: ' . print_r( $result, true ) );
					}

					if ( ! empty( $result ) ) {
						update_option( $option_name, $my_table_version );
						return true;
					}

					error_log( 'KTPWP: Failed to create supplier skills table' );
					return false;
				}

				error_log( 'KTPWP: dbDelta function not available' );
				return false;
			} else {
				// Table exists, check for column structure updates
				$existing_columns = $wpdb->get_col( "SHOW COLUMNS FROM `{$table_name}`", 0 );

				// If version is less than 3.0.0, perform migration
				if ( version_compare( $installed_version, '3.0.0', '<' ) ) {
					// Remove deprecated columns if they exist
					$columns_to_remove = array( 'priority_order', 'is_active' );

					foreach ( $columns_to_remove as $column ) {
						if ( in_array( $column, $existing_columns, true ) ) {
							$drop_column_query = "ALTER TABLE `{$table_name}` DROP COLUMN `{$column}`";
							$result = $wpdb->query( $drop_column_query );

							if ( $result === false ) {
								error_log( "KTPWP: Failed to drop column {$column} from supplier skills table" );
							} elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
									error_log( "KTPWP: Successfully dropped column {$column} from supplier skills table" );
							}
						}
					}
				}

				// If version is less than 3.2.0, update unit_price precision
				if ( version_compare( $installed_version, '3.2.0', '<' ) ) {
					$alter_column_query = "ALTER TABLE `{$table_name}` MODIFY COLUMN `unit_price` DECIMAL(20,10) NOT NULL DEFAULT 0 COMMENT '単価'";
					$result = $wpdb->query( $alter_column_query );

					if ( $result === false ) {
						error_log( 'KTPWP: Failed to update unit_price column precision in supplier skills table' );
					} elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
							error_log( 'KTPWP: Successfully updated unit_price column precision in supplier skills table' );
					}
				}

				// If version is less than 3.3.0, add tax_rate column
				if ( version_compare( $installed_version, '3.3.0', '<' ) ) {
					$tax_rate_column = $wpdb->get_results( "SHOW COLUMNS FROM `{$table_name}` LIKE 'tax_rate'" );
					if ( empty( $tax_rate_column ) ) {
						$alter_column_query = "ALTER TABLE `{$table_name}` ADD COLUMN `tax_rate` DECIMAL(5,2) NOT NULL DEFAULT 10.00 COMMENT '税率' AFTER `unit`";
						$result = $wpdb->query( $alter_column_query );

						if ( $result === false ) {
							error_log( 'KTPWP: Failed to add tax_rate column to supplier skills table' );
						} elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
							error_log( 'KTPWP: Successfully added tax_rate column to supplier skills table' );
						}
					}
				}

				// If version is less than 3.4.0, allow NULL values for tax_rate column
				if ( version_compare( $installed_version, '3.4.0', '<' ) ) {
					$tax_rate_column = $wpdb->get_row( "SHOW COLUMNS FROM `{$table_name}` WHERE Field = 'tax_rate'" );
					if ( $tax_rate_column && $tax_rate_column->Null === 'NO' ) {
						$alter_column_query = "ALTER TABLE `{$table_name}` MODIFY COLUMN `tax_rate` DECIMAL(5,2) NULL DEFAULT 10.00 COMMENT '税率'";
						$result = $wpdb->query( $alter_column_query );

						if ( $result === false ) {
							error_log( 'KTPWP: Failed to modify tax_rate column to allow NULL values in supplier skills table' );
						} elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
							error_log( 'KTPWP: Successfully modified tax_rate column to allow NULL values in supplier skills table' );
						}
					}
				}

				// Update version after all migrations
				if ( version_compare( $installed_version, $my_table_version, '<' ) ) {
					update_option( $option_name, $my_table_version );
				}
			}

			return true;
		}

		/**
		 * Get skills for a specific supplier
		 *
		 * @since 1.0.0
		 * @param int $supplier_id Supplier ID
		 * @return array Array of skills data
		 */
		public function get_supplier_skills( $supplier_id ) {
			global $wpdb;

			if ( empty( $supplier_id ) || $supplier_id <= 0 ) {
				return array();
			}

			$table_name = $wpdb->prefix . 'ktp_supplier_skills';
			$supplier_id = absint( $supplier_id );

			$results = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$table_name} WHERE supplier_id = %d ORDER BY frequency DESC, product_name ASC",
                    $supplier_id
                ),
                ARRAY_A
			);

			return $results ? $results : array();
		}

		/**
		 * Get skills for a specific supplier with pagination
		 *
		 * @since 1.0.0
		 * @param int $supplier_id Supplier ID
		 * @param int $limit Number of items per page
		 * @param int $offset Starting position
		 * @return array Array of skills data
		 */
		public function get_supplier_skills_paginated( $supplier_id, $limit = 10, $offset = 0, $sort_by = 'frequency', $sort_order = 'DESC' ) {
			global $wpdb;

			if ( empty( $supplier_id ) || $supplier_id <= 0 ) {
				return array();
			}

			$table_name = $wpdb->prefix . 'ktp_supplier_skills';
			$supplier_id = absint( $supplier_id );
			$limit = absint( $limit );
			$offset = absint( $offset );

			// Sanitize sort parameters
			$allowed_sort_columns = array( 'id', 'product_name', 'frequency' );
			$sort_by = in_array( $sort_by, $allowed_sort_columns ) ? $sort_by : 'frequency';
			$sort_order = ( strtoupper( $sort_order ) === 'ASC' ) ? 'ASC' : 'DESC';

			$results = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$table_name} WHERE supplier_id = %d ORDER BY {$sort_by} {$sort_order}, product_name ASC LIMIT %d OFFSET %d",
                    $supplier_id,
                    $limit,
                    $offset
                ),
                ARRAY_A
			);

			return $results ? $results : array();
		}

		/**
		 * Get total count of skills for a specific supplier
		 *
		 * @since 1.0.0
		 * @param int $supplier_id Supplier ID
		 * @return int Total count of skills
		 */
		public function get_supplier_skills_count( $supplier_id ) {
			global $wpdb;

			if ( empty( $supplier_id ) || $supplier_id <= 0 ) {
				return 0;
			}

			$table_name = $wpdb->prefix . 'ktp_supplier_skills';
			$supplier_id = absint( $supplier_id );

			$count = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table_name} WHERE supplier_id = %d",
                    $supplier_id
                )
			);

			return $count ? intval( $count ) : 0;
		}

		/**
		 * Add a product for a supplier
		 *
		 * @since 1.0.0
		 * @param int    $supplier_id Supplier ID
		 * @param string $product_name Product name
		 * @param float  $unit_price Unit price
		 * @param int    $quantity Quantity (default: 1)
		 * @param string $unit Unit (default: '式')
		 * @param float  $tax_rate Tax rate (default: 10.00)
		 * @param int    $frequency Frequency (default: 0)
		 * @return int|false Product ID on success, false on failure
		 */
	public function add_skill( $supplier_id, $product_name, $unit_price = 0, $quantity = 1, $unit = '式', $tax_rate = 10.00, $frequency = 0 ) {
		global $wpdb;

		if ( empty( $supplier_id ) || $supplier_id <= 0 || empty( $product_name ) ) {
			return false;
		}

		$table_name = $wpdb->prefix . 'ktp_supplier_skills';
		$supplier_id = absint( $supplier_id );

		// Sanitize product data
		$sanitized_data = array(
			'supplier_id' => $supplier_id,
			'product_name' => sanitize_text_field( $product_name ),
			'unit_price' => floatval( $unit_price ),
			'quantity' => absint( $quantity ) ?: 1,
			'unit' => sanitize_text_field( $unit ) ?: '式',
			'tax_rate' => ( $tax_rate === null || $tax_rate === '' ) ? null : floatval( $tax_rate ),
			'frequency' => absint( $frequency ),
			'created_at' => current_time( 'mysql' ),
			'updated_at' => current_time( 'mysql' ),
		);

		// Prepare format array based on whether tax_rate is NULL
		$format_array = array( '%d', '%s', '%f', '%d', '%s', '%f', '%d', '%s', '%s' );
		if ( $sanitized_data['tax_rate'] === null ) {
			$format_array[5] = null; // tax_rate position
		}

		$result = $wpdb->insert(
            $table_name,
            $sanitized_data,
            $format_array
		);

		if ( $result === false ) {
			error_log( 'KTPWP: Failed to add supplier product: ' . $wpdb->last_error );
			return false;
		}

		// Clear cache for this supplier after successful addition
		if ( function_exists( 'ktpwp_cache_delete' ) ) {
			ktpwp_cache_delete( "supplier_skills_for_cost_{$supplier_id}" );
		}

		return $wpdb->insert_id;
	}		/**
		 * Update a product
		 *
		 * @since 1.0.0
		 * @param int    $skill_id Product ID
		 * @param string $product_name Product name
		 * @param float  $unit_price Unit price
		 * @param int    $quantity Quantity
		 * @param string $unit Unit
		 * @param float  $tax_rate Tax rate (default: 10.00)
		 * @param int    $frequency Frequency (default: 0)
		 * @return bool True on success, false on failure
		 */
	public function update_skill( $skill_id, $product_name, $unit_price = 0, $quantity = 1, $unit = '式', $tax_rate = 10.00, $frequency = 0 ) {
		global $wpdb;

		if ( empty( $skill_id ) || $skill_id <= 0 || empty( $product_name ) ) {
			return false;
		}

		$table_name = $wpdb->prefix . 'ktp_supplier_skills';
		$skill_id = absint( $skill_id );

		// Get supplier_id before update to clear cache
		$supplier_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT supplier_id FROM {$table_name} WHERE id = %d",
				$skill_id
			)
		);

		// Sanitize product data
		$sanitized_data = array(
			'product_name' => sanitize_text_field( $product_name ),
			'unit_price' => floatval( $unit_price ),
			'quantity' => absint( $quantity ) ?: 1,
			'unit' => sanitize_text_field( $unit ) ?: '式',
			'tax_rate' => ( $tax_rate === null || $tax_rate === '' ) ? null : floatval( $tax_rate ),
			'frequency' => absint( $frequency ),
			'updated_at' => current_time( 'mysql' ),
		);

		// Prepare format array based on whether tax_rate is NULL
		$format_array = array( '%s', '%f', '%d', '%s', '%f', '%d', '%s' );
		if ( $sanitized_data['tax_rate'] === null ) {
			$format_array[4] = null; // tax_rate position
		}

		$result = $wpdb->update(
            $table_name,
            $sanitized_data,
            array( 'id' => $skill_id ),
            $format_array,
            array( '%d' )
		);

		if ( $result === false ) {
			error_log( 'KTPWP: Failed to update supplier product: ' . $wpdb->last_error );
			return false;
		}

		// Clear cache for this supplier after successful update
		if ( $supplier_id && function_exists( 'ktpwp_cache_delete' ) ) {
			ktpwp_cache_delete( "supplier_skills_for_cost_{$supplier_id}" );
		}

		return true;
	}	/**
	 * Delete a product (physical delete)
	 *
	 * @since 1.0.0
	 * @param int $skill_id Product ID
	 * @return bool True on success, false on failure
	 */
	public function delete_skill( $skill_id ) {
		global $wpdb;

		if ( empty( $skill_id ) || $skill_id <= 0 ) {
			return false;
		}

		$table_name = $wpdb->prefix . 'ktp_supplier_skills';
		$skill_id = absint( $skill_id );

		// Get supplier_id before deletion to clear cache
		$supplier_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT supplier_id FROM {$table_name} WHERE id = %d",
				$skill_id
			)
		);

		$result = $wpdb->delete(
            $table_name,
            array( 'id' => $skill_id ),
            array( '%d' )
		);

		if ( $result === false ) {
			error_log( 'KTPWP: Failed to delete supplier skill: ' . $wpdb->last_error );
			return false;
		}

		// Clear cache for this supplier after successful deletion
		if ( $supplier_id && function_exists( 'ktpwp_cache_delete' ) ) {
			ktpwp_cache_delete( "supplier_skills_for_cost_{$supplier_id}" );
		}

		return true;
	}		/**
		 * Delete all products for a supplier (called when supplier is deleted)
		 *
		 * @since 1.0.0
		 * @param int $supplier_id Supplier ID
		 * @return bool True on success, false on failure
		 */
	public function delete_supplier_skills( $supplier_id ) {
		global $wpdb;

		if ( empty( $supplier_id ) || $supplier_id <= 0 ) {
			return false;
		}

		$table_name = $wpdb->prefix . 'ktp_supplier_skills';
		$supplier_id = absint( $supplier_id );

		$result = $wpdb->delete(
            $table_name,
            array( 'supplier_id' => $supplier_id ),
            array( '%d' )
		);

		if ( $result === false ) {
			// エラーログはサーバーサイドのみに記録（ヘッダーに表示されない）
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'KTPWP: Failed to delete supplier skills: ' . $wpdb->last_error );
			}
			return false;
		}

		// 成功時のログはサーバーサイドのみに記録（ヘッダーに表示されない）
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( "KTPWP: Deleted {$result} skills for supplier ID {$supplier_id}" );
		}

		// Clear cache for this supplier after successful deletion
		if ( function_exists( 'ktpwp_cache_delete' ) ) {
			ktpwp_cache_delete( "supplier_skills_for_cost_{$supplier_id}" );
		}

		return true;
	}		/**
		 * Get product by ID
		 *
		 * @since 1.0.0
		 * @param int $skill_id Product ID
		 * @return array|null Product data or null if not found
		 */
		public function get_skill( $skill_id ) {
			global $wpdb;

			if ( empty( $skill_id ) || $skill_id <= 0 ) {
				return null;
			}

			$table_name = $wpdb->prefix . 'ktp_supplier_skills';
			$skill_id = absint( $skill_id );

			$result = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$table_name} WHERE id = %d",
                    $skill_id
                ),
                ARRAY_A
			);

			return $result;
		}

		/**
		 * Generate HTML for skills management interface
		 *
		 * @since 1.0.0
		 * @param int $supplier_id Supplier ID
		 * @return string HTML content
		 */
		public function render_skills_interface( $supplier_id ) {
			if ( empty( $supplier_id ) || $supplier_id <= 0 ) {
				return '';
			}

			// ソート順の取得（デフォルトは頻度の降順）
			$sort_by = 'frequency';
			$sort_order = 'DESC';

			if ( isset( $_GET['skills_sort_by'] ) ) {
				$sort_by = sanitize_text_field( $_GET['skills_sort_by'] );
				// 安全なカラム名のみ許可（SQLインジェクション対策）
				$allowed_columns = array( 'id', 'product_name', 'frequency' );
				if ( ! in_array( $sort_by, $allowed_columns ) ) {
					$sort_by = 'frequency'; // 不正な値の場合はデフォルトに戻す
				}
			}

			if ( isset( $_GET['skills_sort_order'] ) ) {
				$sort_order_param = strtoupper( sanitize_text_field( $_GET['skills_sort_order'] ) );
				// ASCかDESCのみ許可
				$sort_order = ( $sort_order_param === 'ASC' ) ? 'ASC' : 'DESC';
			}

			// ページネーション設定
			// 一般設定から表示件数を取得（設定クラスが利用可能な場合）
			if ( class_exists( 'KTP_Settings' ) ) {
				$query_limit = KTP_Settings::get_work_list_range();
			} else {
				$query_limit = 10; // フォールバック値（職能リストは10件）
			}

			$current_page = isset( $_GET['skills_page'] ) ? absint( $_GET['skills_page'] ) : 1;
			$current_page = max( 1, $current_page );
			$offset = ( $current_page - 1 ) * $query_limit;

			// 職能データを取得（ページネーション付き、ソート順も含む）
			$skills = $this->get_supplier_skills_paginated( $supplier_id, $query_limit, $offset, $sort_by, $sort_order );
			$total_skills = $this->get_supplier_skills_count( $supplier_id );
			$total_pages = ceil( $total_skills / $query_limit );

			$supplier_id = absint( $supplier_id );

			$html = '';

			// Add new product form - 1行バー型
			$html .= '<div class="add-skill-form" style="margin-bottom: 20px;">';
			$html .= '<form method="post" style="background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 8px; padding: 12px; display: flex; align-items: center; gap: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">';
			$html .= wp_nonce_field( 'ktp_skills_action', 'ktp_skills_nonce', true, false );
			$html .= '<input type="hidden" name="skills_action" value="add_skill">';
			$html .= '<input type="hidden" name="supplier_id" value="' . $supplier_id . '">';

			// 商品名フィールド
			$html .= '<div style="flex: 2; min-width: 120px;">';
			$html .= '<input type="text" name="product_name" required placeholder="商品名" style="width: 100%; padding: 8px 10px; border: 1px solid #ced4da; border-radius: 4px; font-size: 14px; box-sizing: border-box;">';
			$html .= '</div>';

			// 単価フィールド
			$html .= '<div style="flex: 1; min-width: 80px;">';
			$html .= '<input type="number" name="unit_price" min="0" step="any" value="0" placeholder="単価" style="width: 100%; padding: 8px 10px; border: 1px solid #ced4da; border-radius: 4px; font-size: 14px; box-sizing: border-box;">';
			$html .= '</div>';

			// 数量フィールド
			$html .= '<div style="flex: 0.8; min-width: 60px;">';
			$html .= '<input type="number" name="quantity" min="1" value="1" placeholder="数量" style="width: 100%; padding: 8px 10px; border: 1px solid #ced4da; border-radius: 4px; font-size: 14px; box-sizing: border-box;">';
			$html .= '</div>';

			// 単位フィールド
			$html .= '<div style="flex: 0.8; min-width: 60px;">';
			$html .= '<input type="text" name="unit" value="式" placeholder="単位" style="width: 100%; padding: 8px 10px; border: 1px solid #ced4da; border-radius: 4px; font-size: 14px; box-sizing: border-box;">';
			$html .= '</div>';

			// 税率フィールド（NULL許可）
			$html .= '<div style="flex: 0.8; min-width: 70px;">';
			$html .= '<div style="display: flex; align-items: center; gap: 2px;">';
			$html .= '<input type="number" name="tax_rate" min="0" max="100" step="1" value="10" placeholder="税率" style="width: 100%; padding: 8px 10px; border: 1px solid #ced4da; border-radius: 4px; font-size: 14px; box-sizing: border-box;" title="空欄にすると税率なし（非課税）になります">';
			$html .= '<span style="font-size: 12px; color: #666; white-space: nowrap;">%</span>';
			$html .= '</div>';
			$html .= '</div>';

			// 追加ボタン
			$html .= '<div style="flex: 0 0 auto;">';
			$html .= '<button type="submit" style="background: #28a745; color: white; border: none; padding: 9px 16px; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: 500; display: flex; align-items: center; justify-content: center; white-space: nowrap; transition: background-color 0.2s ease;" onmouseover="this.style.backgroundColor=\'#218838\'" onmouseout="this.style.backgroundColor=\'#28a745\'">';
			$html .= '+';
			$html .= '</button>';
			$html .= '</div>';

			$html .= '</form>';
			$html .= '</div>';

			// 職能リストを追加フォームの後に表示（協力会社リストと同じスタイルに統一）
			if ( ! empty( $skills ) ) {
				$html .= '<div class="ktp_data_skill_list_box" style="margin-top: 15px;">';

				$skill_index = 0;
				foreach ( $skills as $skill ) {
					$skill_id = esc_attr( $skill['id'] );
					$product_name = esc_html( $skill['product_name'] );
					// Format unit price to remove unnecessary trailing zeros
					$unit_price_raw = floatval( $skill['unit_price'] );
					$unit_price = rtrim( rtrim( number_format( $unit_price_raw, 6, '.', '' ), '0' ), '.' );
					$quantity = absint( $skill['quantity'] );
					$unit = esc_html( $skill['unit'] );
					$tax_rate = isset( $skill['tax_rate'] ) ? $skill['tax_rate'] : null;
					$frequency = isset( $skill['frequency'] ) ? absint( $skill['frequency'] ) : 0;

					$html .= '<div class="ktp_data_list_item skill-item" style="display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; line-height: 1.2; min-height: 48px;">';

					// 商品情報を完全に1行で表示（IDを先頭に、頻度を末尾に追加）
					$html .= '<div style="flex: 1; min-width: 0; display: flex; align-items: center; gap: 8px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">';
					$html .= '<span style="color: #999; font-size: 12px; font-weight: normal; flex-shrink: 0;">ID: ' . $skill_id . '</span>';
					$html .= '<span style="font-weight: 600; color: #2c3e50; flex-shrink: 0;">' . $product_name . '</span>';
					$html .= '<span style="color: #666; font-size: 13px; font-weight: normal; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">';
					$html .= '単価: <strong>' . $unit_price . '円</strong> | ';
					$html .= '数量: <strong>' . $quantity . '</strong> | ';
					$html .= '単位: <strong>' . $unit . '</strong> | ';
					$html .= '税率: <strong>' . ( $tax_rate === null ? 'なし（非課税）' : round($tax_rate) . '%' ) . '</strong>';
					$html .= '</span>';
					$html .= '<span style="color: #666; font-size: 13px; font-weight: normal; flex-shrink: 0; margin-left: auto;" title="アクセス頻度（クリックされた回数）">頻度(' . $frequency . ')</span>';
					$html .= '</div>';

					// 削除ボタン
					$html .= '<div class="delete-skill-btn-container" style="flex-shrink: 0; margin-left: 12px;">';
					$html .= '<button type="button" class="delete-skill-btn" data-skill-id="' . $skill_id . '" ';
					$html .= 'style="background: #dc3545; color: white; border: none; padding: 6px 8px; border-radius: 3px; cursor: pointer; font-size: 12px; transition: background-color 0.2s ease;" ';
					$html .= 'title="削除" onmouseover="this.style.backgroundColor=\'#c82333\'" onmouseout="this.style.backgroundColor=\'#dc3545\'">';
					$html .= '<span class="material-symbols-outlined" style="font-size: 16px;">delete</span>';
					$html .= '</button>';
					$html .= '</div>';

					$html .= '</div>';
					$skill_index++;
				}
				$html .= '</div>';

				// ページネーションを職能リストの下に表示
				$html .= $this->render_skills_pagination( $current_page, $total_pages, $total_skills, $supplier_id );
			} else {
				$html .= '<div class="ktp_data_list_item" style="padding: 15px 20px; background: linear-gradient(135deg, #fff3cd 0%, #fff8e1 100%); border-radius: 6px; margin: 15px 0; color: #856404; font-weight: 600; text-align: center; box-shadow: 0 3px 12px rgba(0,0,0,0.07); display: flex; align-items: center; justify-content: center; font-size: 16px; gap: 10px;">';
				$html .= '<span class="material-symbols-outlined" style="color: #ffc107;">info</span>';
				$html .= 'まだサービスがありません。';
				$html .= '</div>';

				// 職能が0件の場合はパージネーションを非表示
				// $html .= $this->render_skills_pagination( $current_page, $total_pages, $total_skills, $supplier_id );
			}

			// Add JavaScript for delete functionality
			$nonce_field = wp_nonce_field( 'ktp_skills_action', 'ktp_skills_nonce', true, false );
			$html .= '<script>
        document.addEventListener("DOMContentLoaded", function() {
            document.querySelectorAll(".delete-skill-btn").forEach(function(btn) {
                btn.addEventListener("click", function() {
                    const skillId = this.getAttribute("data-skill-id");
                    if (confirm("この商品・サービスを削除しますか？")) {
                        const form = document.createElement("form");
                        form.method = "post";
                        form.innerHTML = `
                            ' . str_replace( array( "\r", "\n" ), '', $nonce_field ) . '
                            <input type="hidden" name="skills_action" value="delete_skill">
                            <input type="hidden" name="skill_id" value="${skillId}">
                        `;
                        document.body.appendChild(form);
                        form.submit();
                    }
                });
            });
        });
        </script>';

			return $html;
		}

		/**
		 * Render pagination for skills list
		 *
		 * @since 1.0.0
		 * @param int $current_page Current page
		 * @param int $total_pages Total pages
		 * @param int $total_skills Total skills count
		 * @param int $supplier_id Supplier ID
		 * @return string HTML content for pagination
		 */
		private function render_skills_pagination( $current_page, $total_pages, $total_skills, $supplier_id ) {
			// 職能が0件の場合はパージネーションを表示しない
			// データが0件の場合はtotal_pagesが0になるため、最低1ページとして扱う
			if ( $total_pages == 0 ) {
				$total_pages = 1;
				$current_page = 1;
			}

			// 現在のソートパラメータを取得
			$sort_by = isset( $_GET['skills_sort_by'] ) ? sanitize_text_field( $_GET['skills_sort_by'] ) : 'frequency';
			$sort_order = isset( $_GET['skills_sort_order'] ) ? sanitize_text_field( $_GET['skills_sort_order'] ) : 'DESC';

			// 他のタブと統一した正円ボタンデザインのページネーション
			$pagination_html = '<div class="pagination" style="text-align: center; margin: 20px 0; padding: 20px 0;">';

			// 1行目：ページ情報表示
			$page_start = ( $current_page - 1 ) * ( class_exists( 'KTP_Settings' ) ? KTP_Settings::get_work_list_range() : 10 ) + 1;
			$page_end = min( $total_skills, $current_page * ( class_exists( 'KTP_Settings' ) ? KTP_Settings::get_work_list_range() : 10 ) );

			$pagination_html .= '<div style="margin-bottom: 18px; color: #4b5563; font-size: 14px; font-weight: 500;">';
			$pagination_html .= esc_html( $current_page ) . ' / ' . esc_html( $total_pages ) . ' ページ（全 ' . esc_html( $total_skills ) . ' 件）';
			$pagination_html .= '</div>';

			// 2行目：ページネーションボタン
			$pagination_html .= '<div style="display: flex; align-items: center; gap: 4px; flex-wrap: wrap; justify-content: center; width: 100%;">';

			// 現在のURLを取得（動的パーマリンク取得）
			$tab_name = 'supplier'; // 動的に取得する場合は適切な変数や関数を使用
			$base_page_url = add_query_arg( array( 'tab_name' => $tab_name ), KTPWP_Main::get_current_page_base_url() );

			// ページネーションボタンのスタイル（正円ボタン）
			$button_style = 'display: inline-block; width: 36px; height: 36px; padding: 0; margin: 0 2px; text-decoration: none; border: 1px solid #ddd; border-radius: 50%; color: #333; background: #fff; transition: all 0.3s ease; box-shadow: 0 1px 3px rgba(0,0,0,0.1); line-height: 34px; text-align: center; vertical-align: middle; font-size: 14px;';
			$current_style = 'background: #1976d2; color: white; border-color: #1976d2; font-weight: bold; transform: translateY(-1px); box-shadow: 0 2px 5px rgba(0,0,0,0.2);';
			$hover_effect = 'onmouseover="this.style.backgroundColor=\'#f5f5f5\'; this.style.transform=\'translateY(-1px)\'; this.style.boxShadow=\'0 2px 5px rgba(0,0,0,0.15)\';" onmouseout="this.style.backgroundColor=\'#fff\'; this.style.transform=\'none\'; this.style.boxShadow=\'0 1px 3px rgba(0,0,0,0.1)\';"';

			// 前のページボタン
			if ( $current_page > 1 && $total_pages > 1 ) {
				$prev_page = $current_page - 1;
				$prev_url = add_query_arg(
                    array(
						'data_id' => $supplier_id,
						'query_post' => 'update',
						'skills_page' => $prev_page,
						'skills_sort_by' => $sort_by,
						'skills_sort_order' => $sort_order,
                    ),
                    $base_page_url
                );

				$pagination_html .= '<a href="' . esc_url( $prev_url ) . '" style="' . $button_style . '" ' . $hover_effect . '>‹</a>';
			}

			// ページ番号ボタン（省略表示対応）
			$start_page = max( 1, $current_page - 2 );
			$end_page = min( $total_pages, $current_page + 2 );

			// 最初のページを表示（データが0件でも1ページ目は表示）
			if ( $start_page > 1 && $total_pages > 1 ) {
				$first_url = add_query_arg(
                    array(
						'data_id' => $supplier_id,
						'query_post' => 'update',
						'skills_page' => 1,
						'skills_sort_by' => $sort_by,
						'skills_sort_order' => $sort_order,
                    ),
                    $base_page_url
                );

				$pagination_html .= '<a href="' . esc_url( $first_url ) . '" style="' . $button_style . '" ' . $hover_effect . '>1</a>';

				if ( $start_page > 2 ) {
					$pagination_html .= '<span style="' . $button_style . ' background: transparent; border: none; cursor: default;">...</span>';
				}
			}

			// 中央のページ番号
			for ( $i = $start_page; $i <= $end_page; $i++ ) {
				if ( $i == $current_page ) {
					$pagination_html .= '<span style="' . $button_style . ' ' . $current_style . '">' . $i . '</span>';
				} else {
					$page_url = add_query_arg(
                        array(
							'data_id' => $supplier_id,
							'query_post' => 'update',
							'skills_page' => $i,
							'skills_sort_by' => $sort_by,
							'skills_sort_order' => $sort_order,
                        ),
                        $base_page_url
                    );

					$pagination_html .= '<a href="' . esc_url( $page_url ) . '" style="' . $button_style . '" ' . $hover_effect . '>' . $i . '</a>';
				}
			}

			// 最後のページを表示
			if ( $end_page < $total_pages && $total_pages > 1 ) {
				if ( $end_page < $total_pages - 1 ) {
					$pagination_html .= '<span style="' . $button_style . ' background: transparent; border: none; cursor: default;">...</span>';
				}

				$last_url = add_query_arg(
                    array(
						'data_id' => $supplier_id,
						'query_post' => 'update',
						'skills_page' => $total_pages,
						'skills_sort_by' => $sort_by,
						'skills_sort_order' => $sort_order,
                    ),
                    $base_page_url
                );

				$pagination_html .= '<a href="' . esc_url( $last_url ) . '" style="' . $button_style . '" ' . $hover_effect . '>' . $total_pages . '</a>';
			}

			// 次のページボタン
			if ( $current_page < $total_pages && $total_pages > 1 ) {
				$next_page = $current_page + 1;
				$next_url = add_query_arg(
                    array(
						'data_id' => $supplier_id,
						'query_post' => 'update',
						'skills_page' => $next_page,
						'skills_sort_by' => $sort_by,
						'skills_sort_order' => $sort_order,
                    ),
                    $base_page_url
                );

				$pagination_html .= '<a href="' . esc_url( $next_url ) . '" style="' . $button_style . '" ' . $hover_effect . '>›</a>';
			}

			$pagination_html .= '</div>'; // ボタングループ終了
			$pagination_html .= '</div>'; // ページネーション終了

			return $pagination_html;
		}
	}

} // End class_exists check
