<?php
/**
 * Service database management class for KTPWP plugin
 *
 * Handles service data management including table creation,
 * data operations (CRUD), and security implementations.
 *
 * @package KTPWP
 * @subpackage Includes
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'KTPWP_Service_DB' ) ) {
	class KTPWP_Service_DB {
		/**
		 * Instance of this class
		 *
		 * @var KTPWP_Service_DB
		 */
		private static $instance = null;

		/**
		 * Get singleton instance
		 *
		 * @return KTPWP_Service_DB
		 */
		public static function get_instance() {
			if ( self::$instance === null ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * Private constructor to prevent creating a new instance directly
		 */
		private function __construct() {
			// シングルトン
		}

		/**
		 * Get the service table schema.
		 *
		 * @return string The SQL for creating the service table.
		 */
		public function get_schema() {
			global $wpdb;
			$table_name = $wpdb->prefix . 'ktp_service';
			$charset_collate = $wpdb->get_charset_collate();

			// Column definitions with internationalization
			$sql = "CREATE TABLE {$table_name} (
				id MEDIUMINT(9) NOT NULL AUTO_INCREMENT,
				time DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
				service_name TINYTEXT,
				price DECIMAL(10,2) DEFAULT 0.00 NOT NULL,
				tax_rate DECIMAL(5,2) NULL DEFAULT NULL,
				unit VARCHAR(50) NOT NULL DEFAULT '',
				image_url VARCHAR(255),
				memo TEXT,
				search_field TEXT,
				frequency INT NOT NULL DEFAULT 0,
				category VARCHAR(100) NOT NULL DEFAULT '" . esc_sql( __( 'General', 'ktpwp' ) ) . "',
				PRIMARY KEY  (id)
			) {$charset_collate};";

			return $sql;
		}

		/**
		 * Create or update the service table.
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
		 * Update service table with POST data
		 *
		 * @param string $tab_name The table name suffix
		 * @return void
		 */
		public function update_table( $tab_name ) {
			if ( empty( $tab_name ) ) {
				return;
			}

			global $wpdb;
			$table_name = $wpdb->prefix . 'ktp_' . sanitize_key( $tab_name );

			// Only process POST requests
			if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
				return;
			}

			// Verify nonce for security
			if ( ! isset( $_POST['_ktp_service_nonce'] ) || ! wp_verify_nonce( $_POST['_ktp_service_nonce'], 'ktp_service_action' ) ) {
				wp_die( esc_html__( 'Security check failed.', 'ktpwp' ) );
			}

			// Sanitize and validate POST data
			$data_id = isset( $_POST['data_id'] ) ? absint( $_POST['data_id'] ) : 0;
			$query_post = isset( $_POST['query_post'] ) ? sanitize_text_field( $_POST['query_post'] ) : '';

			// Empty query_post should not be processed
			if ( empty( $query_post ) ) {
				return;
			}

			// Sanitize form fields
			$service_name = isset( $_POST['service_name'] ) ? sanitize_text_field( $_POST['service_name'] ) : '';
			$price = isset( $_POST['price'] ) ? floatval( $_POST['price'] ) : 0;
			$tax_rate = isset( $_POST['tax_rate'] ) && $_POST['tax_rate'] !== '' ? floatval( $_POST['tax_rate'] ) : null;
			$unit = isset( $_POST['unit'] ) ? sanitize_text_field( $_POST['unit'] ) : '';
			$memo = isset( $_POST['memo'] ) ? sanitize_textarea_field( $_POST['memo'] ) : '';
			$category = isset( $_POST['category'] ) ? sanitize_text_field( $_POST['category'] ) : '';

			// Create search field value
			$search_field_value = implode(
                ', ',
                array(
					current_time( 'mysql' ),
					$service_name,
					$price,
					$tax_rate,
					$unit,
					$memo,
					$category,
                )
            );

			// Get last ID if data_id is 0
			if ( $data_id === 0 ) {
				$last_id = $wpdb->get_var(
                    $wpdb->prepare( "SELECT id FROM {$table_name} ORDER BY id DESC LIMIT %d", 1 )
				);
				$data_id = $last_id ? $last_id : 1;
			}

			// Handle different operations based on query_post
			switch ( $query_post ) {
				case 'update':
					if ( $data_id > 0 ) {
						$data = array(
							'service_name' => $service_name,
							'price' => $price,
							'tax_rate' => $tax_rate,
							'unit' => $unit,
							'memo' => $memo,
							'category' => $category,
							'search_field' => $search_field_value,
						);

						$wpdb->update(
                            $table_name,
                            $data,
                            array( 'id' => $data_id ),
                            array( '%s', '%f', '%f', '%s', '%s', '%s', '%s' ),
                            array( '%d' )
						);
					}
					break;

				case 'new':
					return $this->handle_new_service( $tab_name );

				case 'istmode':
					// 追加モードの場合は何もしない（表示ロジックで処理される）
					return;

				case 'delete':
					return $this->handle_delete_service( $tab_name, $data_id );

				case 'duplicate':
					return $this->handle_duplicate_service( $tab_name, $data_id );

				case 'search':
				case 'search_execute':
				case 'search_cancel':
					return $this->handle_search_operations( $tab_name, $query_post );

				case 'upload_image':
					return $this->handle_upload_image( $tab_name, $data_id );

				case 'delete_image':
					return $this->handle_delete_image( $tab_name, $data_id );

				default:
					break;
			}
		}

		/**
		 * Handle creating a new service
		 *
		 * @param string $tab_name Table name suffix
		 * @return void
		 */
		private function handle_new_service( $tab_name ) {
			global $wpdb;
			$table_name = $wpdb->prefix . 'ktp_' . sanitize_key( $tab_name );

			// nonceを検証
			if ( ! isset( $_POST['_ktp_service_nonce'] ) || ! wp_verify_nonce( $_POST['_ktp_service_nonce'], 'ktp_service_action' ) ) {
				wp_die( esc_html__( 'Nonce verification failed.', 'ktpwp' ) );
			}

			// 新しいIDを取得
			$new_id_query = "SELECT COALESCE(MAX(id), 0) + 1 as new_id FROM {$table_name}";
			$new_id_result = $wpdb->get_row( $new_id_query );
			$new_id = $new_id_result && isset( $new_id_result->new_id ) ? intval( $new_id_result->new_id ) : 1;

			// フォームからのデータを取得
			$service_name = isset( $_POST['service_name'] ) ? sanitize_text_field( $_POST['service_name'] ) : esc_html__( '新しいサービス', 'ktpwp' );
			$price = isset( $_POST['price'] ) ? floatval( $_POST['price'] ) : 0;
			$tax_rate = isset( $_POST['tax_rate'] ) && $_POST['tax_rate'] !== '' ? floatval( $_POST['tax_rate'] ) : null;
			$unit = isset( $_POST['unit'] ) ? sanitize_text_field( $_POST['unit'] ) : '';
			$memo = isset( $_POST['memo'] ) ? sanitize_textarea_field( $_POST['memo'] ) : '';
			$category = isset( $_POST['category'] ) ? sanitize_text_field( $_POST['category'] ) : '';

			// 検索フィールド値を作成
			$search_field_value = implode(
                ', ',
                array(
					current_time( 'mysql' ),
					$service_name,
					$price,
					$tax_rate,
					$unit,
					$memo,
					$category,
                )
            );

			// 新規データを挿入
			$insert_result = $wpdb->insert(
                $table_name,
                array(
					'id' => $new_id,
					'time' => current_time( 'mysql' ),
					'service_name' => $service_name,
					'price' => $price,
					'tax_rate' => $tax_rate,
					'unit' => $unit,
					'memo' => $memo,
					'category' => $category,
					'image_url' => '',
					'frequency' => 0,
					'search_field' => $search_field_value,
                ),
                array( '%d', '%s', '%s', '%f', '%f', '%s', '%s', '%s', '%s', '%d', '%s' )
			);

			if ( $insert_result === false ) {
				echo "<script>alert('" . esc_js( esc_html__( '新規追加に失敗しました。', 'ktpwp' ) ) . "');</script>";
			} else {

				// 元のページ（ショートコードが配置された固定ページ）にリダイレクト
				$current_page_url = wp_get_referer();
				if ( ! $current_page_url ) {
					// refererが取得できない場合は、動的パーマリンク取得を使用
					$current_page_url = KTPWP_Main::get_current_page_base_url();
				}

				$redirect_url = add_query_arg(
                    array(
						'tab_name' => $tab_name,
						'data_id' => $new_id,
						'message' => 'added',
                    ),
                    $current_page_url
                );

				// PHPリダイレクトを使用（JavaScriptではなく）
				wp_redirect( $redirect_url );
				exit;
			}
		}

		/**
		 * Handle deleting a service
		 *
		 * @param string $tab_name Table name suffix
		 * @param int    $data_id Data ID
		 * @return void
		 */
		private function handle_delete_service( $tab_name, $data_id ) {
			global $wpdb;
			$table_name = $wpdb->prefix . 'ktp_' . sanitize_key( $tab_name );

			// nonceを検証
			if ( ! isset( $_POST['_ktp_service_nonce'] ) || ! wp_verify_nonce( $_POST['_ktp_service_nonce'], 'ktp_service_action' ) ) {
				wp_die( esc_html__( 'Nonce verification failed.', 'ktpwp' ) );
			}

			if ( $data_id > 0 ) {
				$delete_result = $wpdb->delete(
                    $table_name,
                    array( 'id' => $data_id ),
                    array( '%d' )
				);

				if ( $delete_result === false ) {
					echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    showErrorNotification('" . esc_js( esc_html__( '削除に失敗しました。', 'ktpwp' ) ) . "');
                });
                </script>";
				} else {
					// 削除後は最新のレコード（ID降順のトップ）にリダイレクト
					$next_record = $wpdb->get_row( "SELECT id FROM {$table_name} ORDER BY id DESC LIMIT 1" );
					$next_id = $next_record ? $next_record->id : 0;

					// 統一されたリダイレクト処理（wp_redirect使用）
					$redirect_url = add_query_arg(
                        array(
							'tab_name' => $tab_name,
							'data_id' => $next_id,
							'message' => 'deleted',
                        ),
                        wp_get_referer()
                    );

					wp_redirect( $redirect_url );
					exit;
				}
			}

			$wpdb->query( 'UNLOCK TABLES;' );
		}

		/**
		 * Handle duplicating a service
		 *
		 * @param string $tab_name Table name suffix
		 * @param int    $data_id Data ID
		 * @return void
		 */
		private function handle_duplicate_service( $tab_name, $data_id ) {
			global $wpdb;
			$table_name = $wpdb->prefix . 'ktp_' . sanitize_key( $tab_name );

			// POSTリクエストのみ許可
			if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
				wp_die( esc_html__( 'Invalid request method.', 'ktpwp' ) );
			}

			// nonceを検証
			if ( ! isset( $_POST['_ktp_service_nonce'] ) || ! wp_verify_nonce( $_POST['_ktp_service_nonce'], 'ktp_service_action' ) ) {
				wp_die( esc_html__( 'Nonce verification failed.', 'ktpwp' ) );
			}

			if ( $data_id > 0 ) {
				// 元のデータを取得
				$original_data = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_name} WHERE id = %d", $data_id ) );

				if ( $original_data ) {
					// 新しいIDを取得（データが完全に0の場合は1から開始）
					$new_id_query = "SELECT COALESCE(MAX(id), 0) + 1 as new_id FROM {$table_name}";
					$new_id_result = $wpdb->get_row( $new_id_query );
					$new_id = $new_id_result && isset( $new_id_result->new_id ) ? intval( $new_id_result->new_id ) : 1;

					// データを複製して挿入
					$insert_result = $wpdb->insert(
                        $table_name,
                        array(
							'id' => $new_id,
							'time' => current_time( 'mysql' ),
							'service_name' => $original_data->service_name . esc_html__( ' (複製)', 'ktpwp' ),
							'price' => $original_data->price,
							'tax_rate' => $original_data->tax_rate,
							'unit' => $original_data->unit,
							'memo' => $original_data->memo,
							'category' => $original_data->category,
							'image_url' => $original_data->image_url,
							'frequency' => $original_data->frequency,
							'search_field' => $original_data->service_name . esc_html__( ' (複製)', 'ktpwp' ) . ', ' . $original_data->price . ', ' . ( $original_data->tax_rate ?? '' ) . ', ' . $original_data->unit . ', ' . $original_data->category,
                        ),
                        array( '%d', '%s', '%s', '%f', '%f', '%s', '%s', '%s', '%s', '%s', '%s' )
					);

					if ( $insert_result === false ) {
						// エラー処理は最小限に
					} else {
						// 成功時は複製されたレコードにリダイレクト
						$base_page_url = KTPWP_Main::get_current_page_base_url();
						$redirect_url = add_query_arg(
                            array(
								'tab_name' => $tab_name, // tab_nameを維持
								'data_id' => $new_id,    // 新しいIDに遷移
								'message' => 'duplicated', // 複製成功のメッセージパラメータ
                            ),
                            $base_page_url
                        );
						// 不要なPOST関連パラメータを削除
						$redirect_url = remove_query_arg( array( 'query_post', '_ktp_service_nonce', 'send_post' ), $redirect_url );

						wp_redirect( esc_url_raw( $redirect_url ) );
						exit;
					}
				}
			}

			$wpdb->query( 'UNLOCK TABLES;' );
		}

		/**
		 * Handle search operations
		 *
		 * @param string $tab_name Table name suffix
		 * @param string $query_post Query post type
		 * @return void
		 */
		private function handle_search_operations( $tab_name, $query_post ) {
			global $wpdb;
			$table_name = $wpdb->prefix . 'ktp_' . sanitize_key( $tab_name );

			// nonceを検証
			if ( ! isset( $_POST['_ktp_service_nonce'] ) || ! wp_verify_nonce( $_POST['_ktp_service_nonce'], 'ktp_service_action' ) ) {
				wp_die( esc_html__( 'Nonce verification failed.', 'ktpwp' ) );
			}

			if ( ! session_id() ) {
				ktpwp_safe_session_start();
			}

			if ( $query_post === 'search' ) {
				// 検索モードフラグをセット
				$_SESSION['ktp_service_search_mode'] = true;
				$_SESSION['ktp_service_search_message'] = esc_html__( '検索モードです。条件を入力して検索してください。', 'ktpwp' );
				$wpdb->query( 'UNLOCK TABLES;' );
				return;
			} elseif ( $query_post === 'search_cancel' ) {
				// 検索モードを解除
				unset( $_SESSION['ktp_service_search_mode'] );
				unset( $_SESSION['ktp_service_search_message'] );

				$base_page_url = KTPWP_Main::get_current_page_base_url();
				$url = add_query_arg(
                    array(
						'tab_name' => $tab_name,
						'message' => 'search_cancelled',
                    ),
                    $base_page_url
                );

				echo '<script>
                window.location.href = "' . esc_js( $url ) . '";
            </script>';

				$wpdb->query( 'UNLOCK TABLES;' );
			} elseif ( $query_post === 'search_execute' ) {
				$search_service_name = isset( $_POST['search_service_name'] ) ? sanitize_text_field( $_POST['search_service_name'] ) : '';
				$search_category = isset( $_POST['search_category'] ) ? sanitize_text_field( $_POST['search_category'] ) : '';

				// 検索条件の構築
				$where_conditions = array();
				$where_values = array();

				if ( ! empty( $search_service_name ) ) {
					$where_conditions[] = 'service_name LIKE %s';
					$where_values[] = '%' . $wpdb->esc_like( $search_service_name ) . '%';
				}

				if ( ! empty( $search_category ) ) {
					$where_conditions[] = 'category LIKE %s';
					$where_values[] = '%' . $wpdb->esc_like( $search_category ) . '%';
				}

				if ( empty( $where_conditions ) ) {
					// 検索条件が空の場合
					$_SESSION['ktp_service_search_message'] = esc_html__( '検索条件を入力してください。', 'ktpwp' );
				} else {
					// 検索実行
					$where_clause = ' WHERE ' . implode( ' AND ', $where_conditions );
					$search_query = "SELECT * FROM {$table_name}" . $where_clause . ' ORDER BY id DESC';

					if ( ! empty( $where_values ) ) {
						$search_results = $wpdb->get_results( $wpdb->prepare( $search_query, $where_values ) );
					} else {
						$search_results = $wpdb->get_results( $search_query );
					}

					if ( $search_results ) {
						// 検索結果がある場合、最初の結果にリダイレクト
						$first_result = $search_results[0];

						// 検索モードを解除
						unset( $_SESSION['ktp_service_search_mode'] );
						unset( $_SESSION['ktp_service_search_message'] );

						global $wp;
						$current_page_id = get_queried_object_id();
						$base_page_url = get_permalink( $current_page_id );
						if ( ! $base_page_url ) {
							 $base_page_url = home_url( add_query_arg( array(), $wp->request ) );
						}
						$url = add_query_arg(
                            array(
								'tab_name' => $tab_name,
								'data_id' => $first_result->id,
								'message' => 'search_found',
                            ),
                            $base_page_url
                        );

						echo '<script>
                        alert("' . esc_js( sprintf( esc_html__( '検索結果: %d件見つかりました。最初の結果を表示します。', 'ktpwp' ), count( $search_results ) ) ) . '");
                        window.location.href = "' . esc_js( $url ) . '";
                    </script>';
					} else {
						// 検索結果が無い場合
						$_SESSION['ktp_service_search_message'] = esc_html__( '該当するサービスが見つかりませんでした。条件を変更して再検索してください。', 'ktpwp' );
						echo '<script>alert("' . esc_js( esc_html__( '該当するサービスが見つかりませんでした。', 'ktpwp' ) ) . '");</script>';
					}
				}

				$wpdb->query( 'UNLOCK TABLES;' );
			}
		}

		/**
		 * Handle uploading an image
		 *
		 * @param string $tab_name Table name suffix
		 * @param int    $data_id Data ID
		 * @return void
		 */
		private function handle_upload_image( $tab_name, $data_id ) {
			global $wpdb;
			$table_name = $wpdb->prefix . 'ktp_' . sanitize_key( $tab_name );

			// 先にImage_Processorクラスが存在するか確認
			if ( ! class_exists( 'Image_Processor' ) ) {
				require_once __DIR__ . '/class-image_processor.php';
			}

			// 画像URLを取得
			$image_processor = new Image_Processor();
			$default_image_url = plugin_dir_url( __DIR__ ) . 'images/default/no-image-icon.jpg';

			// デフォルト画像のパスが正しいか確認
			$default_image_path = __DIR__ . '/../images/default/no-image-icon.jpg';
			if ( ! file_exists( $default_image_path ) ) {
				// デフォルト画像が存在しない場合のエラーは記録しない（プロダクション環境）
			}

			$image_url = $image_processor->handle_image( $tab_name, $data_id, $default_image_url );

			$update_result = $wpdb->update(
                $table_name,
                array(
					'image_url' => $image_url,
                ),
                array(
					'id' => $data_id,
                ),
                array(
					'%s',
                ),
                array(
					'%d',
                )
			);

			$wpdb->query( 'UNLOCK TABLES;' ); // Unlock before redirect

			// 元のページ（ショートコードが配置された固定ページ）にリダイレクト
			$current_page_url = wp_get_referer();
			if ( ! $current_page_url ) {
				// refererが取得できない場合は、動的パーマリンク取得を使用
				$current_page_url = KTPWP_Main::get_current_page_base_url();
			}

			$redirect_url = add_query_arg(
                array(
					'tab_name' => $tab_name,
					'data_id' => $data_id,
					'message' => 'image_uploaded',
                ),
                $current_page_url
            );

			wp_redirect( $redirect_url );
			exit;
		}

		/**
		 * Handle deleting an image
		 *
		 * @param string $tab_name Table name suffix
		 * @param int    $data_id Data ID
		 * @return void
		 */
		private function handle_delete_image( $tab_name, $data_id ) {
			global $wpdb;
			$table_name = $wpdb->prefix . 'ktp_' . sanitize_key( $tab_name );

			// デフォルト画像のURLを設定
			$default_image_url = plugin_dir_url( __DIR__ ) . 'images/default/no-image-icon.jpg';

			// 既存の画像ファイルを削除する処理を追加
			$upload_dir = __DIR__ . '/../images/upload/';
			$file_path = $upload_dir . $data_id . '.jpeg';

			// ファイルが存在する場合は削除する
			if ( file_exists( $file_path ) ) {
				unlink( $file_path );
			}

			$wpdb->update(
                $table_name,
                array(
					'image_url' => $default_image_url,
                ),
                array(
					'id' => $data_id,
                ),
                array(
					'%s',
                ),
                array(
					'%d',
                )
			);
			$wpdb->query( 'UNLOCK TABLES;' ); // Unlock before redirect

			// 元のページ（ショートコードが配置された固定ページ）にリダイレクト
			$current_page_url = wp_get_referer();
			if ( ! $current_page_url ) {
				// refererが取得できない場合は、動的パーマリンク取得を使用
				$current_page_url = KTPWP_Main::get_current_page_base_url();
			}

			$redirect_url = add_query_arg(
                array(
					'tab_name' => $tab_name,
					'data_id' => $data_id,
					'message' => 'image_deleted',
                ),
                $current_page_url
            );

			wp_redirect( $redirect_url );
			exit;
		}

		/**
		 * Get the next available ID to display
		 *
		 * @param string $table_name Full table name
		 * @param int    $deleted_id ID that was just deleted
		 * @return int Next available ID
		 */
		public function get_next_display_id( $table_name, $deleted_id ) {
			global $wpdb;

			// Delete succeeded, find next ID to display
			$next_id_query = $wpdb->prepare(
                "SELECT id FROM {$table_name} WHERE id < %d ORDER BY id DESC LIMIT 1",
                $deleted_id
			);
			$next_id = $wpdb->get_var( $next_id_query );

			// If no previous ID, try to get next ID
			if ( null === $next_id ) {
				$next_id_query = $wpdb->prepare(
                    "SELECT id FROM {$table_name} WHERE id > %d ORDER BY id ASC LIMIT 1",
                    $deleted_id
				);
				$next_id = $wpdb->get_var( $next_id_query );

				// If no next ID either, get highest available ID
				if ( null === $next_id ) {
					$next_id_query = "SELECT id FROM {$table_name} ORDER BY id DESC LIMIT 1";
					$next_id = $wpdb->get_var( $next_id_query );
				}
			}

			// Return found ID or 0 if no records left
			return $next_id ? (int) $next_id : 0;
		}

		/**
		 * Get services with filters and pagination
		 *
		 * @param string $tab_name Table name suffix
		 * @param array  $args Query arguments
		 * @return array Services data
		 */
		public function get_services( $tab_name, $args = array() ) {
			if ( empty( $tab_name ) ) {
				return array();
			}

			global $wpdb;
			$table_name = $wpdb->prefix . 'ktp_' . sanitize_key( $tab_name );

			$defaults = array(
				'limit'       => 20,
				'offset'      => 0,
				'order_by'    => 'id',
				'order'       => 'DESC',
				'search'      => '',
				'category'    => '',
			);

			$args = wp_parse_args( $args, $defaults );

			$where_clauses = array();
			$where_values = array();

			// カテゴリーフィルター
			if ( ! empty( $args['category'] ) ) {
				$where_clauses[] = 'category = %s';
				$where_values[] = $args['category'];
			}

			// 検索フィルター
			if ( ! empty( $args['search'] ) ) {
				$where_clauses[] = '(service_name LIKE %s OR price LIKE %s OR unit LIKE %s OR category LIKE %s OR search_field LIKE %s)';
				$search_term = '%' . $wpdb->esc_like( $args['search'] ) . '%';
				$where_values[] = $search_term;
				$where_values[] = $search_term;
				$where_values[] = $search_term;
				$where_values[] = $search_term;
				$where_values[] = $search_term;
			}

			// WHERE句の構築
			$where_sql = '';
			if ( ! empty( $where_clauses ) ) {
				$where_sql = 'WHERE ' . implode( ' AND ', $where_clauses );
			}

			// ORDER BY句の検証とサニタイズ
			$allowed_order_by = array( 'id', 'service_name', 'price', 'unit', 'frequency', 'time', 'category', 'tax_rate' );
			if ( ! in_array( $args['order_by'], $allowed_order_by ) ) {
				$args['order_by'] = 'id';
			}

			// ORDER句のサニタイズ
			$args['order'] = strtoupper( $args['order'] );
			if ( ! in_array( $args['order'], array( 'ASC', 'DESC' ) ) ) {
				$args['order'] = 'DESC';
			}

			// クエリの構築
			$sql = "SELECT * FROM `{$table_name}` {$where_sql} ORDER BY {$args['order_by']} {$args['order']} LIMIT %d OFFSET %d";

			// LIMIT, OFFSETパラメータを追加
			$where_values[] = $args['limit'];
			$where_values[] = $args['offset'];

			// プリペアードステートメントの実行
			if ( ! empty( $where_values ) ) {
				$results = $wpdb->get_results( $wpdb->prepare( $sql, $where_values ) );
			} else {
				$results = $wpdb->get_results( $sql );
			}

			return $results ? $results : array();
		}

		/**
		 * Get services count with filters
		 *
		 * @param string $tab_name Table name suffix
		 * @param array  $args Query arguments
		 * @return int Services count
		 */
		public function get_services_count( $tab_name, $args = array() ) {
			if ( empty( $tab_name ) ) {
				return 0;
			}

			global $wpdb;
			$table_name = $wpdb->prefix . 'ktp_' . sanitize_key( $tab_name );

			$defaults = array(
				'search'      => '',
				'category'    => '',
			);

			$args = wp_parse_args( $args, $defaults );

			$where_clauses = array();
			$where_values = array();

			// カテゴリーフィルター
			if ( ! empty( $args['category'] ) ) {
				$where_clauses[] = 'category = %s';
				$where_values[] = $args['category'];
			}

			// 検索フィルター
			if ( ! empty( $args['search'] ) ) {
				$where_clauses[] = '(service_name LIKE %s OR price LIKE %s OR unit LIKE %s OR category LIKE %s OR search_field LIKE %s)';
				$search_term = '%' . $wpdb->esc_like( $args['search'] ) . '%';
				$where_values[] = $search_term;
				$where_values[] = $search_term;
				$where_values[] = $search_term;
				$where_values[] = $search_term;
				$where_values[] = $search_term;
			}

			// WHERE句の構築
			$where_sql = '';
			if ( ! empty( $where_clauses ) ) {
				$where_sql = 'WHERE ' . implode( ' AND ', $where_clauses );
			}

			// カウントクエリの構築
			$sql = "SELECT COUNT(*) FROM `{$table_name}` {$where_sql}";

			// プリペアードステートメントの実行
			if ( ! empty( $where_values ) ) {
				$count = $wpdb->get_var( $wpdb->prepare( $sql, $where_values ) );
			} else {
				$count = $wpdb->get_var( $sql );
			}

			return $count ? (int) $count : 0;
		}
	}
}
