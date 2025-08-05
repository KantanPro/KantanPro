<?php
/**
 * Client DB management class for KTPWP plugin
 *
 * Handles client table creation, update, delete, and search.
 *
 * @package KTPWP
 * @subpackage Includes
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'KTPWP_Client_DB' ) ) {
	class KTPWP_Client_DB {
		public static function get_instance() {
			static $instance = null;
			if ( $instance === null ) {
				$instance = new self();
			}
			return $instance;
		}

		/**
		 * Create client table
		 *
		 * @param string $tab_name Table name suffix (sanitized)
		 * @return bool Success status
		 */
		public function create_table( $tab_name ) {
			global $wpdb;
			$tab_name = sanitize_key( $tab_name );
			if ( empty( $tab_name ) ) {
				return false;
			}
			$my_table_version = '1.0.2';
			$table_name = $wpdb->prefix . 'ktp_' . $tab_name;
			$charset_collate = $wpdb->get_charset_collate();
			$columns_def = array(
				'id MEDIUMINT(9) NOT NULL AUTO_INCREMENT',
				"time BIGINT(11) DEFAULT '0' NOT NULL",
				'name TINYTEXT',
				'url VARCHAR(55)',
				"company_name VARCHAR(100) NOT NULL DEFAULT '" . __( '初めてのお客様', 'ktpwp' ) . "'",
				'representative_name TINYTEXT',
				'email VARCHAR(100)',
				'phone VARCHAR(20)',
				'postal_code VARCHAR(10)',
				'prefecture TINYTEXT',
				'city TINYTEXT',
				'address TEXT',
				'building TINYTEXT',
				'closing_day TINYTEXT',
				'payment_month TINYTEXT',
				'payment_day TINYTEXT',
				'payment_method TINYTEXT',
				"tax_category VARCHAR(100) NOT NULL DEFAULT '" . __( '内税', 'ktpwp' ) . "'",
				'memo TEXT',
				'search_field TEXT',
				'frequency INT NOT NULL DEFAULT 0',
				"client_status VARCHAR(100) NOT NULL DEFAULT '" . __( '対象', 'ktpwp' ) . "'",
				'category VARCHAR(255) NULL',
				'UNIQUE KEY id (id)',
			);
			$existing_table = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );
			if ( $existing_table !== $table_name ) {
				$sql = "CREATE TABLE {$table_name} (" . implode( ', ', $columns_def ) . ") {$charset_collate};";
				require_once ABSPATH . 'wp-admin/includes/upgrade.php';
				$result = dbDelta( $sql );
				if ( ! empty( $result ) ) {
					add_option( 'ktp_' . $tab_name . '_table_version', $my_table_version );
					return true;
				}
				return false;
			} else {
				$existing_columns = $wpdb->get_col( "SHOW COLUMNS FROM `{$table_name}`", 0 );
				$def_column_names = array();
				foreach ( $columns_def as $def ) {
					if ( preg_match( '/^([a-zA-Z0-9_]+)/', $def, $m ) ) {
						$def_column_names[] = $m[1];
					}
				}
				foreach ( $def_column_names as $i => $col_name ) {
					if ( ! in_array( $col_name, $existing_columns ) ) {
						if ( $col_name === 'UNIQUE' || $col_name === 'category' ) {
							continue;
						}
						$def = $columns_def[ $i ];
						$result = $wpdb->query( "ALTER TABLE `{$table_name}` ADD COLUMN {$def}" );
						if ( $result === false ) {
							error_log( "KTPWP: Failed to add column {$col_name} to table {$table_name}" );
						}
					}
				}
				update_option( 'ktp_' . $tab_name . '_table_version', $my_table_version );
			}
			$indexes = $wpdb->get_results( "SHOW INDEX FROM `{$table_name}`" );
			$has_unique_id = false;
			foreach ( $indexes as $idx ) {
				if ( $idx->Key_name === 'id' && $idx->Non_unique == 0 ) {
					$has_unique_id = true;
					break;
				}
			}
			if ( ! $has_unique_id ) {
				$result = $wpdb->query( "ALTER TABLE `{$table_name}` ADD UNIQUE (id)" );
				if ( $result === false ) {
					error_log( "KTPWP: Failed to add unique key to table {$table_name}" );
				}
			}
			return true;
		}

		/**
		 * Update table and handle POST operations
		 *
		 * @param string $tab_name Table name suffix
		 * @return void
		 */
		public function update_table( $tab_name ) {
			global $wpdb;
			$table_name = $wpdb->prefix . 'ktp_' . $tab_name;

			// 'category' カラムが存在するか確認し、なければ追加する (マイグレーション)
			$column_exists = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM `{$table_name}` LIKE %s", 'category' ) );
			if ( empty( $column_exists ) ) {
				$wpdb->query( "ALTER TABLE {$table_name} ADD COLUMN category VARCHAR(255) NULL" );
			}

			// 空のカテゴリーフィールドを「対象」に更新（一度だけ実行）
			$migration_option = 'ktp_client_category_migration_done';
			if ( ! get_option( $migration_option ) ) {
				$update_result = $wpdb->query(
                    $wpdb->prepare(
                        "UPDATE {$table_name} SET client_status = %s WHERE client_status = '' OR client_status IS NULL",
                        '対象'
                    )
				);

				if ( $update_result !== false ) {
					update_option( $migration_option, true );
				}
			}

			// POST処理の場合のみ実行
			if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
				// デバッグログを追加
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'KTPWP Client Debug: update_table - POST request detected' );
					error_log( 'KTPWP Client Debug: update_table - REQUEST_METHOD = ' . $_SERVER['REQUEST_METHOD'] );
					error_log( 'KTPWP Client Debug: update_table - POST data keys = ' . implode( ', ', array_keys( $_POST ) ) );
				}

				// nonce検証
				if ( ! isset( $_POST['ktp_client_nonce'] ) || ! wp_verify_nonce( $_POST['ktp_client_nonce'], 'ktp_client_action' ) ) {
					wp_die( __( '不正なリクエストです。', 'ktpwp' ) );
				}

				// POST データの取得とサニタイズ
				$query_post = isset( $_POST['query_post'] ) ? sanitize_text_field( $_POST['query_post'] ) : '';
				$data_id = isset( $_POST['data_id'] ) ? intval( $_POST['data_id'] ) : 0;

				// フィールドデータの取得
				$fields_data = $this->sanitize_post_data( $_POST );

				// 検索フィールドの更新
				$search_field_value = implode(
                    ' ',
                    array(
						$fields_data['company_name'],
						$fields_data['user_name'],
						$fields_data['email'],
						$fields_data['representative_name'],
						$fields_data['phone'],
						$fields_data['prefecture'],
						$fields_data['city'],
						$fields_data['address'],
						$fields_data['client_status'],
						$fields_data['category'],
					)
                );

				// 操作に応じた処理
				switch ( $query_post ) {
					case 'delete':
						return $this->handle_delete( $table_name, $tab_name, $data_id );
					case 'insert':
						// デバッグログを追加
						if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
							error_log( 'KTPWP Client Debug: update_table - insert case called' );
							error_log( 'KTPWP Client Debug: update_table - query_post = ' . $query_post );
							error_log( 'KTPWP Client Debug: update_table - data_id = ' . $data_id );
						}
						return $this->handle_insert( $table_name, $tab_name, $fields_data, $search_field_value );
					case 'update':
						return $this->handle_update( $table_name, $tab_name, $data_id, $fields_data, $search_field_value );
					case 'search':
						return $this->handle_search( $table_name, $tab_name, $_POST );
				}
			}
		}

		/**
		 * Sanitize POST data
		 *
		 * @param array $post_data POST data
		 * @return array Sanitized data
		 */
		private function sanitize_post_data( $post_data ) {
			// client_statusの処理を改善
			$client_status = '';
			if ( isset( $post_data['client_status'] ) ) {
				$client_status = sanitize_text_field( $post_data['client_status'] );
			}
			// 空の場合はデフォルト値「対象」を設定
			if ( empty( $client_status ) ) {
				$client_status = '対象';
			}

			// デバッグログを追加
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'KTPWP Client Debug: client_status from POST = ' . ( isset( $post_data['client_status'] ) ? $post_data['client_status'] : 'NOT_SET' ) );
				error_log( 'KTPWP Client Debug: client_status after sanitize = ' . $client_status );
			}

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
				'client_status' => $client_status,
				'category' => isset( $post_data['category'] ) ? sanitize_text_field( $post_data['category'] ) : '',
				'selected_department_id' => isset( $post_data['selected_department_id'] ) ? intval( $post_data['selected_department_id'] ) : null,
			);
		}

		/**
		 * Handle delete operation (soft delete)
		 *
		 * @param string $table_name Table name
		 * @param string $tab_name Tab name
		 * @param int    $data_id Data ID
		 * @return void
		 */
		private function handle_delete( $table_name, $tab_name, $data_id ) {
			global $wpdb;

			$delete_type = isset( $_POST['delete_type'] ) ? $_POST['delete_type'] : 'soft';

			if ( $data_id > 0 ) {
				if ( $delete_type === 'soft' ) {
					// ソフトデリート（対象外）
					// 部署データは復活可能にするため削除しない
					$result = $wpdb->update(
                        $table_name,
                        array( 'client_status' => '対象外' ),
                        array( 'id' => $data_id ),
                        array( '%s' ),
                        array( '%d' )
					);
				} elseif ( $delete_type === 'delete' ) {
					// 通常削除：顧客データと部署データを物理削除（受注書は残す）
					// 部署データを先に削除
					$this->delete_client_departments( $data_id );
					
					$result = $wpdb->delete(
                        $table_name,
                        array( 'id' => $data_id ),
                        array( '%d' )
					);
				} elseif ( $delete_type === 'complete' ) {
					// 顧客データと関連受注書を完全削除
					try {
						// トランザクション開始
						$wpdb->query( 'START TRANSACTION' );

						// 1. 受注書テーブルからclient_id一致の受注書IDを取得
						$order_table = $wpdb->prefix . 'ktp_order';
						$order_ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$order_table} WHERE client_id = %d", $data_id ) );

						if ( ! empty( $order_ids ) ) {
							// 2. 受注書関連データを全削除
							foreach ( $order_ids as $order_id ) {
								// KTPWP_Order_Itemsクラスを使用して関連データを削除
								if ( class_exists( 'KTPWP_Order_Items' ) ) {
									$order_items = KTPWP_Order_Items::get_instance();
									$order_items->delete_invoice_items( $order_id );
									$order_items->delete_cost_items( $order_id );
								}

								// スタッフチャットメッセージを削除
								$wpdb->delete( $wpdb->prefix . 'ktp_order_staff_chat', array( 'order_id' => $order_id ), array( '%d' ) );

								// 受注書自体を削除
								$wpdb->delete( $order_table, array( 'id' => $order_id ), array( '%d' ) );
							}
						}

						// 3. 部署データを削除
						$this->delete_client_departments( $data_id );

						// 4. 顧客データ物理削除
						$result = $wpdb->delete(
                            $table_name,
                            array( 'id' => $data_id ),
                            array( '%d' )
						);

						if ( $result === false ) {
							error_log( 'KTPWP Client Delete Error: Failed to delete client data. Error: ' . $wpdb->last_error );
							throw new Exception( '顧客データの削除に失敗しました: ' . $wpdb->last_error );
						}

						// トランザクションコミット
						$wpdb->query( 'COMMIT' );

					} catch ( Exception $e ) {
						// トランザクションロールバック
						$wpdb->query( 'ROLLBACK' );
						error_log( 'KTPWP Client Delete Error: ' . $e->getMessage() );

						// エラーが発生した場合は、ソフトデリートにフォールバック
						// 部署データは復活可能にするため削除しない
						$result = $wpdb->update(
                            $table_name,
                            array( 'client_status' => '対象外' ),
                            array( 'id' => $data_id ),
                            array( '%s' ),
                            array( '%d' )
						);

						if ( $result === false ) {
							error_log( 'KTPWP Client Delete Error: Fallback to soft delete also failed. Error: ' . $wpdb->last_error );
							return; // 処理を中断
						}
					}
				} else {
					// 不明なタイプはソフトデリート
					// 部署データは復活可能にするため削除しない
					$result = $wpdb->update(
                        $table_name,
                        array( 'client_status' => '対象外' ),
                        array( 'id' => $data_id ),
                        array( '%s' ),
                        array( '%d' )
					);
				}

				if ( $result !== false ) {
					$next_id = $this->get_next_display_id( $table_name, $data_id );
					$cookie_name = 'ktp_' . $tab_name . '_id';
					setcookie( $cookie_name, $next_id, time() + ( 86400 * 30 ), '/' );
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
				} else {
					// 削除処理が失敗した場合のエラーハンドリング
					error_log( 'KTPWP Client Delete Error: Delete operation failed for client ID: ' . $data_id );
					// エラーメッセージを表示するためのリダイレクト
					$redirect_url = add_query_arg(
                        array(
							'tab_name' => $tab_name,
							'data_id' => $data_id,
							'message' => 'delete_error',
                        ),
                        wp_get_referer()
                    );
					wp_redirect( $redirect_url );
					exit;
				}
			}
		}

		/**
		 * 顧客の部署データを削除
		 *
		 * @param int $client_id 顧客ID
		 * @return bool 削除成功時はtrue
		 */
		private function delete_client_departments( $client_id ) {
			global $wpdb;

			if ( empty( $client_id ) || $client_id <= 0 ) {
				return false;
			}

			// 部署管理クラスが利用可能かチェック
			if ( ! class_exists( 'KTPWP_Department_Manager' ) ) {
				// クラスが利用できない場合は直接削除
				$department_table = $wpdb->prefix . 'ktp_department';
				$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $department_table ) );
				
				if ( $table_exists ) {
					$result = $wpdb->delete(
						$department_table,
						array( 'client_id' => $client_id ),
						array( '%d' )
					);
					
					if ( $result !== false ) {
						if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
							error_log( "KTPWP Client Delete: 顧客ID {$client_id} の部署データ {$result} 件を削除しました（直接削除）" );
						}
						return true;
					} else {
						if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
							error_log( "KTPWP Client Delete: 顧客ID {$client_id} の部署データ削除に失敗しました: " . $wpdb->last_error );
						}
						return false;
					}
				}
				return true; // テーブルが存在しない場合は成功とみなす
			}

			// 部署管理クラスを使用して削除
			$departments = KTPWP_Department_Manager::get_departments_by_client( $client_id );
			$deleted_count = 0;
			
			if ( ! empty( $departments ) ) {
				foreach ( $departments as $department ) {
					if ( KTPWP_Department_Manager::delete_department( $department->id ) ) {
						$deleted_count++;
					}
				}
			}

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( "KTPWP Client Delete: 顧客ID {$client_id} の部署データ {$deleted_count} 件を削除しました" );
			}

			return true;
		}

		/**
		 * Handle insert operation
		 *
		 * @param string $table_name Table name
		 * @param string $tab_name Tab name
		 * @param array  $fields_data Field data
		 * @param string $search_field_value Search field value
		 * @return void
		 */
		private function handle_insert( $table_name, $tab_name, $fields_data, $search_field_value ) {
			global $wpdb;

			// デバッグログを追加
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'KTPWP Client Debug: handle_insert called' );
				error_log( 'KTPWP Client Debug: table_name = ' . $table_name );
				error_log( 'KTPWP Client Debug: company_name = ' . $fields_data['company_name'] );
				error_log( 'KTPWP Client Debug: user_name = ' . $fields_data['user_name'] );
			}

			// データが完全に0の場合、AUTO_INCREMENTカウンターを1にリセット
			$row_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" );
			if ( $row_count == 0 ) {
				$wpdb->query( "ALTER TABLE {$table_name} AUTO_INCREMENT = 1" );
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'KTPWP Client Debug: AUTO_INCREMENT reset to 1' );
				}
			}

			// IDを手動設定せず、AUTO_INCREMENTに任せる
			$result = $wpdb->insert(
				$table_name,
				array(
					'time' => current_time( 'mysql' ),
					'company_name' => $fields_data['company_name'],
					'name' => $fields_data['user_name'],
					'email' => $fields_data['email'],
					'url' => $fields_data['url'],
					'representative_name' => $fields_data['representative_name'],
					'phone' => $fields_data['phone'],
					'postal_code' => $fields_data['postal_code'],
					'prefecture' => $fields_data['prefecture'],
					'city' => $fields_data['city'],
					'address' => $fields_data['address'],
					'building' => $fields_data['building'],
					'closing_day' => $fields_data['closing_day'],
					'payment_month' => $fields_data['payment_month'],
					'payment_day' => $fields_data['payment_day'],
					'payment_method' => $fields_data['payment_method'],
					'tax_category' => $fields_data['tax_category'],
					'memo' => $fields_data['memo'],
					'client_status' => $fields_data['client_status'],
					'category' => $fields_data['category'],
					'selected_department_id' => $fields_data['selected_department_id'],
					'search_field' => $search_field_value,
				),
				array(
					'%s', // time
					'%s', // company_name
					'%s', // name
					'%s', // email
					'%s', // url
					'%s', // representative_name
					'%s', // phone
					'%s', // postal_code
					'%s', // prefecture
					'%s', // city
					'%s', // address
					'%s', // building
					'%s', // closing_day
					'%s', // payment_month
					'%s', // payment_day
					'%s', // payment_method
					'%s', // tax_category
					'%s', // memo
					'%s', // client_status
					'%s', // category
					'%d', // selected_department_id
					'%s', // search_field
				)
			);

			// デバッグログを追加
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'KTPWP Client Debug: insert result = ' . ( $result !== false ? 'success' : 'failed' ) );
				if ( $result !== false ) {
					$new_id = $wpdb->insert_id;
					error_log( 'KTPWP Client Debug: new_id = ' . $new_id );
				} else {
					error_log( 'KTPWP Client Debug: wpdb error = ' . $wpdb->last_error );
				}
			}

			if ( $result !== false ) {
				$new_id = $wpdb->insert_id;
				$cookie_name = 'ktp_' . $tab_name . '_id';
				setcookie( $cookie_name, $new_id, time() + ( 86400 * 30 ), '/' );

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
		}

		/**
		 * Handle update operation
		 *
		 * @param string $table_name Table name
		 * @param string $tab_name Tab name
		 * @param int    $data_id Data ID
		 * @param array  $fields_data Field data
		 * @param string $search_field_value Search field value
		 * @return void
		 */
		private function handle_update( $table_name, $tab_name, $data_id, $fields_data, $search_field_value ) {
			global $wpdb;

			// デバッグログを追加
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'KTPWP Client Debug: handle_update - client_status = ' . $fields_data['client_status'] );
				error_log( 'KTPWP Client Debug: handle_update - data_id = ' . $data_id );
			}

			if ( $data_id > 0 ) {
				$result = $wpdb->update(
                    $table_name,
                    array(
						'company_name' => $fields_data['company_name'],
						'name' => $fields_data['user_name'],
						'email' => $fields_data['email'],
						'url' => $fields_data['url'],
						'representative_name' => $fields_data['representative_name'],
						'phone' => $fields_data['phone'],
						'postal_code' => $fields_data['postal_code'],
						'prefecture' => $fields_data['prefecture'],
						'city' => $fields_data['city'],
						'address' => $fields_data['address'],
						'building' => $fields_data['building'],
						'closing_day' => $fields_data['closing_day'],
						'payment_month' => $fields_data['payment_month'],
						'payment_day' => $fields_data['payment_day'],
						'payment_method' => $fields_data['payment_method'],
						'tax_category' => $fields_data['tax_category'],
						'memo' => $fields_data['memo'],
						'client_status' => $fields_data['client_status'],
						'category' => $fields_data['category'],
						'selected_department_id' => $fields_data['selected_department_id'],
						'search_field' => $search_field_value,
                    ),
                    array( 'id' => $data_id ),
                    array(
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
						'%d',
                    ),
                    array( '%d' )
				);

				// デバッグログを追加
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'KTPWP Client Debug: update result = ' . ( $result !== false ? 'success' : 'failed' ) );
					if ( $result === false ) {
						error_log( 'KTPWP Client Debug: wpdb error = ' . $wpdb->last_error );
					}
				}

				if ( $result !== false ) {
					$wpdb->query(
                        $wpdb->prepare(
                            "UPDATE $table_name SET frequency = frequency + 1 WHERE id = %d",
                            $data_id
                        )
                    );

					if ( isset( $_GET['sort_by'] ) || isset( $_GET['sort_order'] ) ) {
						wp_redirect( remove_query_arg( 'message', wp_get_referer() ) );
					} else {
						$redirect_url = add_query_arg(
                            array(
								'tab_name' => $tab_name,
								'data_id' => $data_id,
								'message' => 'updated',
                            ),
                            wp_get_referer()
                        );
						wp_redirect( $redirect_url );
					}
					exit;
				}
			}
		}

		/**
		 * Handle search operation
		 *
		 * @param string $table_name Table name
		 * @param string $tab_name Tab name
		 * @param array  $post_data POST data
		 * @return void
		 */
		private function handle_search( $table_name, $tab_name, $post_data ) {
			global $wpdb;

			$search_query = isset( $post_data['search_query'] ) ? sanitize_text_field( $post_data['search_query'] ) : '';

			if ( ! empty( $search_query ) ) {
				$results = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT * FROM $table_name WHERE search_field LIKE %s",
                        '%' . $wpdb->esc_like( $search_query ) . '%'
                    )
                );

				if ( count( $results ) === 1 ) {
					$found_id = $results[0]->id;

					$wpdb->query(
                        $wpdb->prepare(
                            "UPDATE $table_name SET frequency = frequency + 1 WHERE id = %d",
                            $found_id
                        )
                    );

					$cookie_name = 'ktp_' . $tab_name . '_id';
					setcookie( $cookie_name, $found_id, time() + ( 86400 * 30 ), '/' );

					$redirect_url = add_query_arg(
                        array(
							'tab_name' => $tab_name,
							'data_id' => $found_id,
							'message' => 'found',
                        ),
                        wp_get_referer()
                    );

					wp_redirect( $redirect_url );
					exit;
				} elseif ( count( $results ) > 1 ) {
					$redirect_url = add_query_arg(
                        array(
							'tab_name' => $tab_name,
							'search_query' => $search_query,
							'multiple_results' => '1',
                        ),
                        wp_get_referer()
                    );

					wp_redirect( $redirect_url );
					exit;
				} else {
					$redirect_url = add_query_arg(
                        array(
							'tab_name' => $tab_name,
							'search_query' => $search_query,
							'message' => 'not_found',
                        ),
                        wp_get_referer()
                    );

					wp_redirect( $redirect_url );
					exit;
				}
			}
		}

		/**
		 * Get next display ID for pagination
		 *
		 * @param string $table_name Table name
		 * @param int    $deleted_id Deleted ID
		 * @return int Next ID
		 */
		public function get_next_display_id( $table_name, $deleted_id ) {
			global $wpdb;

			$next_id = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM $table_name WHERE id > %d ORDER BY id ASC LIMIT 1",
                    $deleted_id
                )
            );

			if ( $next_id ) {
				return $next_id;
			}

			$prev_id = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM $table_name WHERE id < %d ORDER BY id DESC LIMIT 1",
                    $deleted_id
                )
            );

			return $prev_id ? $prev_id : 1;
		}

		public function get_client_by_id( $client_id ) {
			global $wpdb;
			$table_name = $wpdb->prefix . 'ktp_client';
			return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $client_id ) );
		}

		public function get_all_clients() {
			global $wpdb;
			$table_name = $wpdb->prefix . 'ktp_clients';
			return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table_name ORDER BY client_name ASC", array() ) );
		}

		public function get_total_clients() {
			global $wpdb;
			$table_name = $wpdb->prefix . 'ktp_clients';
			return $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table_name", array() ) );
		}
	}
}
