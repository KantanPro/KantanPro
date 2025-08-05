<?php
/**
 * Supplier Data Class for KTPWP plugin
 *
 * Handles database operations for supplier data.
 *
 * @package KTPWP
 * @subpackage Includes
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'KTPWP_Supplier_Data' ) ) {

	/**
	 * Class KTPWP_Supplier_Data
	 *
	 * Handles database operations for supplier data.
	 *
	 * @since 1.0.0
	 */
	class KTPWP_Supplier_Data {

        private static $instance = null;

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
		public function __construct() {
			// Load supplier skills class if not already loaded
			if ( ! class_exists( 'KTPWP_Supplier_Skills' ) ) {
				require_once __DIR__ . '/class-ktpwp-supplier-skills.php';
			}
		}

		/**
		 * Get the supplier table schema.
		 *
		 * @return string The SQL for creating the supplier table.
		 */
		public function get_schema() {
			global $wpdb;
			$table_name = $wpdb->prefix . 'ktp_supplier';
			$charset_collate = $wpdb->get_charset_collate();
			$default_company = __( 'Regular Supplier', 'ktpwp' );
			$default_tax = __( 'Tax Included', 'ktpwp' );
			$default_category = __( 'General', 'ktpwp' );

			$sql = "CREATE TABLE {$table_name} (
                id MEDIUMINT(9) NOT NULL AUTO_INCREMENT,
                time BIGINT(11) DEFAULT '0' NOT NULL,
                name TINYTEXT NOT NULL,
                url VARCHAR(55) NOT NULL,
                company_name VARCHAR(100) NOT NULL DEFAULT '{$default_company}',
                email VARCHAR(100) NOT NULL,
                representative_name VARCHAR(100) NOT NULL DEFAULT '',
                phone VARCHAR(20) NOT NULL,
                postal_code VARCHAR(10) NOT NULL,
                prefecture TINYTEXT NOT NULL,
                city TINYTEXT NOT NULL,
                address TEXT NOT NULL,
                building TINYTEXT NOT NULL,
                closing_day TINYTEXT NOT NULL,
                payment_month TINYTEXT NOT NULL,
                payment_day TINYTEXT NOT NULL,
                payment_method TINYTEXT NOT NULL,
                tax_category VARCHAR(100) NOT NULL DEFAULT '{$default_tax}',
                memo TEXT NOT NULL,
                qualified_invoice_number VARCHAR(100) NOT NULL DEFAULT '',
                search_field TEXT NOT NULL,
                frequency INT NOT NULL DEFAULT 0,
                category VARCHAR(100) NOT NULL DEFAULT '{$default_category}',
                PRIMARY KEY  (id)
            ) {$charset_collate};";
			
			// スキルテーブルもここで作成を試みる
			if ( class_exists( 'KTPWP_Supplier_Skills' ) ) {
				$skills_manager = KTPWP_Supplier_Skills::get_instance();
				if(method_exists($skills_manager, 'get_schema')){
					$sql .= "\n" . $skills_manager->get_schema();
				}
			}

			return $sql;
		}

		/**
		 * Create or update the supplier table.
		 * This method is kept for backward compatibility and direct calls,
		 * but the main activation hook now uses get_schema().
		 */
		public function create_table() {
			if ( ! function_exists( 'dbDelta' ) ) {
				require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			}
			$schema = $this->get_schema();
			dbDelta( $schema );
		}

		/**
		 * Update supplier table data
		 *
		 * @since 1.0.0
		 * @param string $tab_name Table name suffix
		 * @param array  $post_data POST data for the operation
		 * @return void
		 */
		public function update_table( $tab_name, $post_data ) {
			if ( empty( $tab_name ) ) {
				// エラーログはサーバーサイドのみに記録（ヘッダーに表示されない）
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'KTPWP: Empty tab_name provided to update_table method' );
				}
				return;
			}

			global $wpdb;
			$table_name = $wpdb->prefix . 'ktp_' . sanitize_key( $tab_name );

			// Security: CSRF protection - verify nonce on POST requests
			if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
				if ( ! isset( $post_data['ktp_supplier_nonce'] ) ||
                 ! wp_verify_nonce( $post_data['ktp_supplier_nonce'], 'ktp_supplier_action' ) ) {
					// エラーログはサーバーサイドのみに記録（ヘッダーに表示されない）
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( 'KTPWP: Nonce verification failed' );
					}
					wp_die( __( 'Security check failed. Please refresh the page and try again.', 'ktpwp' ) );
				}
			}

			// Sanitize and validate input data
			$data_id = isset( $post_data['data_id'] ) ? absint( $post_data['data_id'] ) : 0;
			$query_post = isset( $post_data['query_post'] ) ? sanitize_key( $post_data['query_post'] ) : '';

			// Log operation without sensitive data (サーバーサイドのみ)
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'KTPWP: update_table called for tab: ' . $tab_name . ', action: ' . $query_post );
			}

			// Handle different operations (update, delete, insert, etc.)
			switch ( $query_post ) {
				case 'delete':
					// Handle delete operation
					if ( $data_id > 0 ) {
						// Fire action hook before deletion for cleanup
						do_action( 'ktpwp_supplier_before_delete', $data_id );

						$delete_result = $wpdb->delete( $table_name, array( 'id' => $data_id ), array( '%d' ) );

						if ( $delete_result === false ) {
							// エラーログはサーバーサイドのみに記録
							if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
								error_log( 'KTPWP: Supplier deletion failed. SQL Error: ' . $wpdb->last_error );
							}
							
							// ユーザーには適切なエラーメッセージのみ表示
							echo '<script>
                        document.addEventListener("DOMContentLoaded", function() {
                            showErrorNotification("' . esc_js( __( '削除に失敗しました。', 'ktpwp' ) ) . '");
                        });
                        </script>';
						} else {
							// Fire action hook after successful deletion
							do_action( 'ktpwp_supplier_deleted', $data_id );

							$cookie_name = 'ktp_' . $tab_name . '_id';
							setcookie( $cookie_name, '', time() - 3600, '/' );

							// Prepare redirect URL
							$base_page_url = KTPWP_Main::get_current_page_base_url();
							$redirect_url = add_query_arg(
                                array(
									'tab_name' => $tab_name,
									'message' => 'deleted',
                                ),
                                $base_page_url
                            );

							// 成功メッセージのみ表示（デバッグログは含めない）
							echo '<script>
                            document.addEventListener("DOMContentLoaded", function() {
                                showSuccessNotification("' . esc_js( esc_html__( '協力会社を削除しました。', 'ktpwp' ) ) . '");
                                setTimeout(function() {
                                    window.location.href = "' . esc_js( $redirect_url ) . '";
                                }, 1000);
                            });
                        </script>';
							return;
						}
					}
					break;

				case 'update':
					// Handle update operation
					if ( $data_id > 0 ) {
						// Sanitize all POST data for update operation
						$sanitized_data = $this->sanitize_supplier_data( $post_data );

						// Build search_field value
						$search_field_value = implode(
                            ', ',
                            array(
								current_time( 'timestamp' ),
								$sanitized_data['company_name'],
								$sanitized_data['user_name'],
								$sanitized_data['email'],
								$sanitized_data['url'],
								$sanitized_data['representative_name'],
								$sanitized_data['phone'],
								$sanitized_data['postal_code'],
								$sanitized_data['prefecture'],
								$sanitized_data['city'],
								$sanitized_data['address'],
								$sanitized_data['building'],
								$sanitized_data['closing_day'],
								$sanitized_data['payment_month'],
								$sanitized_data['payment_day'],
								$sanitized_data['payment_method'],
								$sanitized_data['tax_category'],
								$sanitized_data['memo'],
								$sanitized_data['qualified_invoice_number'],
								$sanitized_data['category'],
                            )
                        );

						// Perform database update
						$update_result = $wpdb->update(
							$table_name,
							array(
								'time' => current_time( 'timestamp' ),
								'company_name' => $sanitized_data['company_name'],
								'name' => $sanitized_data['user_name'],
								'email' => $sanitized_data['email'],
								'url' => $sanitized_data['url'],
								'representative_name' => $sanitized_data['representative_name'],
								'phone' => $sanitized_data['phone'],
								'postal_code' => $sanitized_data['postal_code'],
								'prefecture' => $sanitized_data['prefecture'],
								'city' => $sanitized_data['city'],
								'address' => $sanitized_data['address'],
								'building' => $sanitized_data['building'],
								'closing_day' => $sanitized_data['closing_day'],
								'payment_month' => $sanitized_data['payment_month'],
								'payment_day' => $sanitized_data['payment_day'],
								'payment_method' => $sanitized_data['payment_method'],
								'tax_category' => $sanitized_data['tax_category'],
								'memo' => $sanitized_data['memo'],
								'qualified_invoice_number' => $sanitized_data['qualified_invoice_number'],
								'category' => $sanitized_data['category'],
								'search_field' => $search_field_value,
                            ),
                            array( 'id' => $data_id ),
                            array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ),
                            array( '%d' )
						);

						if ( $update_result === false ) {
							echo '<script>
                        document.addEventListener("DOMContentLoaded", function() {
                            showErrorNotification("' . esc_js( __( '更新に失敗しました。SQLエラー: ', 'ktpwp' ) ) . esc_js( $wpdb->last_error ) . '");
                        });
                        </script>';
						} else {
							$cookie_name = 'ktp_' . $tab_name . '_id';
							setcookie( $cookie_name, $data_id, time() + ( 86400 * 30 ), '/' );

							// Prepare redirect URL
							global $wp;
							$current_page_id = get_queried_object_id();
							$base_page_url = KTPWP_Main::get_current_page_base_url();
							$redirect_url = add_query_arg(
                                array(
									'tab_name' => $tab_name,
									'data_id' => $data_id,
									'message' => 'updated',
                                ),
                                $base_page_url
                            );

							echo '<script>
                            document.addEventListener("DOMContentLoaded", function() {
                                showSuccessNotification("' . esc_js( esc_html__( '協力会社情報を更新しました。', 'ktpwp' ) ) . '");
                                setTimeout(function() {
                                    window.location.href = "' . esc_js( $redirect_url ) . '";
                                }, 1000);
                            });
                        </script>';
							return;
						}
					}
					break;

				case 'insert':
					// Handle insert operation

					// Check if table exists before attempting insert
					$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name;
					if ( ! $table_exists ) {
						error_log( 'KTPWP ERROR: Table does not exist: ' . $table_name );
						wp_die( __( 'Database table does not exist. Please contact the administrator.', 'ktpwp' ) );
					}

					// Sanitize all POST data for insert operation
					$sanitized_data = $this->sanitize_supplier_data( $post_data );

					// 新しいIDを取得（データが完全に0の場合は1から開始）
					$new_id_query = "SELECT COALESCE(MAX(id), 0) + 1 as new_id FROM {$table_name}";
					$new_id_result = $wpdb->get_row( $new_id_query );
					$new_id = $new_id_result && isset( $new_id_result->new_id ) ? intval( $new_id_result->new_id ) : 1;

					// Build search_field value
					$search_field_value = implode(
                        ', ',
                        array(
							current_time( 'timestamp' ),
							$sanitized_data['company_name'],
							$sanitized_data['user_name'],
							$sanitized_data['email'],
							$sanitized_data['url'],
							$sanitized_data['representative_name'],
							$sanitized_data['phone'],
							$sanitized_data['postal_code'],
							$sanitized_data['prefecture'],
							$sanitized_data['city'],
							$sanitized_data['address'],
							$sanitized_data['building'],
							$sanitized_data['closing_day'],
							$sanitized_data['payment_month'],
							$sanitized_data['payment_day'],
							$sanitized_data['payment_method'],
							$sanitized_data['tax_category'],
							$sanitized_data['memo'],
							$sanitized_data['qualified_invoice_number'],
							$sanitized_data['category'],
                        )
                    );

					// Perform database insert
					$insert_result = $wpdb->insert(
						$table_name,
						array(
							'id' => $new_id,
							'time' => current_time( 'timestamp' ),
							'company_name' => $sanitized_data['company_name'],
							'name' => $sanitized_data['user_name'],
							'email' => $sanitized_data['email'],
							'url' => $sanitized_data['url'],
							'representative_name' => $sanitized_data['representative_name'],
							'phone' => $sanitized_data['phone'],
							'postal_code' => $sanitized_data['postal_code'],
							'prefecture' => $sanitized_data['prefecture'],
							'city' => $sanitized_data['city'],
							'address' => $sanitized_data['address'],
							'building' => $sanitized_data['building'],
							'closing_day' => $sanitized_data['closing_day'],
							'payment_month' => $sanitized_data['payment_month'],
							'payment_day' => $sanitized_data['payment_day'],
							'payment_method' => $sanitized_data['payment_method'],
							'tax_category' => $sanitized_data['tax_category'],
							'memo' => $sanitized_data['memo'],
							'qualified_invoice_number' => $sanitized_data['qualified_invoice_number'],
							'category' => $sanitized_data['category'],
							'search_field' => $search_field_value,
                        ),
                        array(
							'%d',
							'%d',
							'%s',
							'%s',
							'%s',
							'%s',
							'%s',
							'%s',
							'%s',
							'%s',
							'%s',
							'%s',
							'%s',
							'%s',
							'%s',
							'%s',
							'%s',
							'%s',
							'%s',
							'%s',
							'%s',
							'%s',
                        )
					);

					if ( $insert_result === false ) {
						echo '<script>
                    document.addEventListener("DOMContentLoaded", function() {
                        showErrorNotification("' . esc_js( __( '追加に失敗しました。SQLエラー: ', 'ktpwp' ) ) . esc_js( $wpdb->last_error ) . '");
                    });
                    </script>';
					} else {
						// 成功時のリダイレクト処理
						$redirect_url = add_query_arg(
                            array(
								'tab_name' => $tab_name,
								'data_id' => $new_id,
								'message' => 'added',
                            ),
                            wp_get_referer()
                        );

						wp_redirect( $redirect_url );
						exit;
					}
					break;

				default:
					error_log( 'KTPWP: Invalid query_post action: ' . $query_post );
					break;
			}
		}

		/**
		 * Sanitize supplier data for database operations
		 *
		 * @since 1.0.0
		 * @param array $post_data Raw POST data
		 * @return array Sanitized data
		 */
		private function sanitize_supplier_data( $post_data ) {
			return array(
				'company_name' => isset( $post_data['company_name'] ) ? sanitize_text_field( $post_data['company_name'] ) : '',
				'user_name' => isset( $post_data['user_name'] ) ? sanitize_text_field( $post_data['user_name'] ) : '',
				'email' => isset( $post_data['email'] ) ? sanitize_email( $post_data['email'] ) : '',
				'url' => isset( $post_data['url'] ) ? esc_url_raw( $post_data['url'] ) : '',
				'representative_name' => isset( $post_data['representative_name'] ) ? sanitize_text_field( $post_data['representative_name'] ) : '',
				'phone' => isset( $post_data['phone'] ) ? sanitize_text_field( $post_data['phone'] ) : '',
				'postal_code' => isset( $post_data['postal_code'] ) ? sanitize_text_field( $post_data['postal_code'] ) : '',
				'prefecture' => isset( $post_data['prefecture'] ) ? sanitize_text_field( $post_data['prefecture'] ) : '',
				'city' => isset( $post_data['city'] ) ? sanitize_text_field( $post_data['city'] ) : '',
				'address' => isset( $post_data['address'] ) ? sanitize_text_field( $post_data['address'] ) : '',
				'building' => isset( $post_data['building'] ) ? sanitize_text_field( $post_data['building'] ) : '',
				'closing_day' => isset( $post_data['closing_day'] ) ? sanitize_text_field( $post_data['closing_day'] ) : '',
				'payment_month' => isset( $post_data['payment_month'] ) ? sanitize_text_field( $post_data['payment_month'] ) : '',
				'payment_day' => isset( $post_data['payment_day'] ) ? sanitize_text_field( $post_data['payment_day'] ) : '',
				'payment_method' => isset( $post_data['payment_method'] ) ? sanitize_text_field( $post_data['payment_method'] ) : '',
				'tax_category' => isset( $post_data['tax_category'] ) ? sanitize_text_field( $post_data['tax_category'] ) : '',
				'memo' => isset( $post_data['memo'] ) ? sanitize_textarea_field( $post_data['memo'] ) : '',
				'qualified_invoice_number' => isset( $post_data['qualified_invoice_number'] ) ? sanitize_text_field( $post_data['qualified_invoice_number'] ) : '',
				'category' => isset( $post_data['category'] ) ? sanitize_text_field( $post_data['category'] ) : '',
			);
		}

		/**
		 * 指定したsupplier_idの税区分（tax_category）を取得
		 *
		 * @since 1.0.0
		 * @param int $supplier_id 協力会社ID
		 * @return string 税区分（'外税' または '内税'）
		 */
		public function get_tax_category_by_supplier_id( $supplier_id ) {
			global $wpdb;
			if ( empty( $supplier_id ) || $supplier_id <= 0 ) {
				return '内税'; // デフォルト
			}
			$table_name = $wpdb->prefix . 'ktp_supplier';
			$tax_category = $wpdb->get_var( $wpdb->prepare( "SELECT tax_category FROM {$table_name} WHERE id = %d", $supplier_id ) );
			if ( $tax_category === '外税' ) {
				return '外税';
			}
			return '内税';
		}

		/**
		 * 指定したsupplier_idの適格請求書番号を取得
		 *
		 * @since 1.0.0
		 * @param int $supplier_id 協力会社ID
		 * @return string 適格請求書番号（空文字の場合は適格請求書なし）
		 */
		public function get_qualified_invoice_number_by_supplier_id( $supplier_id ) {
			global $wpdb;
			if ( empty( $supplier_id ) || $supplier_id <= 0 ) {
				return ''; // デフォルト
			}
			$table_name = $wpdb->prefix . 'ktp_supplier';
			$qualified_invoice_number = $wpdb->get_var( $wpdb->prepare( "SELECT qualified_invoice_number FROM {$table_name} WHERE id = %d", $supplier_id ) );
			return $qualified_invoice_number ? $qualified_invoice_number : '';
		}
	}
}
