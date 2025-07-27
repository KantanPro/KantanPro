<?php
/**
 * Supplier class for KTPWP plugin
 *
 * Handles supplier data management including table creation,
 * data operations (CRUD), and security implementations.
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

if ( ! class_exists( 'KTPWP_Supplier_Class' ) ) {

	/**
	 * Supplier class for managing supplier data
	 *
	 * This class has been refactored to use a delegation pattern for better
	 * separation of concerns:
	 * - KTPWP_Supplier_Security: Handles security-related operations
	 * - KTPWP_Supplier_Data: Handles database operations
	 * - KTPWP_Supplier_Class: Main class coordinating UI and business logic
	 *
	 * @since 1.0.0
	 */
	class KTPWP_Supplier_Class {

		/**
		 * Supplier security instance
		 *
		 * @var KTPWP_Supplier_Security
		 * @since 1.0.0
		 */
		private $supplier_security;

		/**
		 * Supplier data instance
		 *
		 * @var KTPWP_Supplier_Data
		 * @since 1.0.0
		 */
		private $supplier_data;

		/**
		 * Constructor
		 *
		 * @since 1.0.0
		 * @param KTPWP_Supplier_Security $supplier_security Optional security instance
		 * @param KTPWP_Supplier_Data     $supplier_data Optional data instance
		 */
		public function __construct( $supplier_security = null, $supplier_data = null ) {
			$this->supplier_security = $supplier_security ?: new KTPWP_Supplier_Security();
			$this->supplier_data = $supplier_data ?: new KTPWP_Supplier_Data();
		}

		// -----------------------------
		// Table Operations
		// -----------------------------

		/**
		 * Set cookie for supplier data
		 *
		 * @since 1.0.0
		 * @param string $name The name parameter for cookie
		 * @return int The query ID
		 */
		public function set_cookie( $name ) {
			return $this->supplier_security->set_cookie( $name );
		}

		/**
		 * Create supplier table
		 *
		 * @since 1.0.0
		 * @param string $tab_name The table name suffix
		 * @return bool True on success, false on failure
		 */
		public function create_table( $tab_name ) {
			return $this->supplier_data->create_table( $tab_name );
		}

		// -----------------------------
		// テーブルの操作（更新・追加・削除・検索）
		// -----------------------------

		/**
		 * Update supplier table data
		 *
		 * @since 1.0.0
		 * @param string $tab_name Table name suffix
		 * @return void
		 */
		public function Update_Table( $tab_name ) {
			// Enhanced debug logging
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			}

			// Only proceed if POST data exists
			if ( ! empty( $_POST ) ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				}

				// Handle skills operations first
				$this->handle_skills_operations( $_POST );

				// Then handle regular supplier data updates
				$this->supplier_data->update_table( $tab_name, $_POST );
			} elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {

			}
		}

		/**
		 * Handle search and other operations for supplier data
		 *
		 * @since 1.0.0
		 * @param string $query_post The query type
		 * @param string $tab_name The table name suffix
		 * @param array  $post_data POST data array (optional)
		 * @return void
		 */
		public function handle_operations( $query_post, $tab_name, $post_data = null ) {
			global $wpdb;

			// セキュリティチェック
			if ( ! current_user_can( 'edit_posts' ) ) {
				wp_die( __( 'You do not have sufficient permissions to access this page.', 'ktpwp' ) );
			}

			// 入力値の検証
			if ( empty( $query_post ) || empty( $tab_name ) ) {
				error_log( 'KTPWP: Invalid parameters in handle_operations' );
				return;
			}

			$table_name = $wpdb->prefix . 'ktp_' . sanitize_text_field( $tab_name );

			// POST データが提供されていない場合は $_POST を使用
			if ( $post_data === null ) {
				$post_data = $_POST;
			}

			// 必要な変数を初期化
			$company_name = isset( $post_data['company_name'] ) ? sanitize_text_field( $post_data['company_name'] ) : '';
			$user_name = isset( $post_data['user_name'] ) ? sanitize_text_field( $post_data['user_name'] ) : '';
			$email = isset( $post_data['email'] ) ? sanitize_email( $post_data['email'] ) : '';
			$url = isset( $post_data['url'] ) ? esc_url_raw( $post_data['url'] ) : '';
			$representative_name = isset( $post_data['representative_name'] ) ? sanitize_text_field( $post_data['representative_name'] ) : '';
			$phone = isset( $post_data['phone'] ) ? sanitize_text_field( $post_data['phone'] ) : '';
			$postal_code = isset( $post_data['postal_code'] ) ? sanitize_text_field( $post_data['postal_code'] ) : '';
			$prefecture = isset( $post_data['prefecture'] ) ? sanitize_text_field( $post_data['prefecture'] ) : '';
			$city = isset( $post_data['city'] ) ? sanitize_text_field( $post_data['city'] ) : '';
			$address = isset( $post_data['address'] ) ? sanitize_text_field( $post_data['address'] ) : '';
			$building = isset( $post_data['building'] ) ? sanitize_text_field( $post_data['building'] ) : '';
			$closing_day = isset( $post_data['closing_day'] ) ? sanitize_text_field( $post_data['closing_day'] ) : '';
			$payment_month = isset( $post_data['payment_month'] ) ? sanitize_text_field( $post_data['payment_month'] ) : '';
			$payment_day = isset( $post_data['payment_day'] ) ? sanitize_text_field( $post_data['payment_day'] ) : '';
			$payment_method = isset( $post_data['payment_method'] ) ? sanitize_text_field( $post_data['payment_method'] ) : '';
			$tax_category = isset( $post_data['tax_category'] ) ? sanitize_text_field( $post_data['tax_category'] ) : '';
			$memo = isset( $post_data['memo'] ) ? sanitize_textarea_field( $post_data['memo'] ) : '';
			$category = isset( $post_data['category'] ) ? sanitize_text_field( $post_data['category'] ) : '';

			// search_field の値を構築
			$search_field_value = implode(
                ', ',
                array(
					current_time( 'mysql' ),
					$company_name,
					$user_name,
					$email,
					$url,
					$representative_name,
					$phone,
					$postal_code,
					$prefecture,
					$city,
					$address,
					$building,
					$closing_day,
					$payment_month,
					$payment_day,
					$payment_method,
					$tax_category,
					$memo,
					$category,
                )
            );

			// 検索
			if ( $query_post == 'search' ) {

				// SQLクエリを準備（search_fieldを検索対象にする）
				$search_query = $post_data['search_query'];
				$results = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table_name WHERE search_field LIKE %s", '%' . $wpdb->esc_like( $search_query ) . '%' ) );

				// 検索結果が1つある場合の処理
				if ( count( $results ) == 1 ) {
					// 検索結果のIDを取得
					$id = $results[0]->id;
					// 頻度の値を+1する
					$wpdb->query(
                        $wpdb->prepare(
                            "UPDATE $table_name SET frequency = frequency + 1 WHERE ID = %d",
                            $id
                        )
					);
					// 検索後に更新モードにする
					$action = 'update';
					$data_id = $id;
					// 現在のURLを取得
					global $wp;
					$current_page_id = get_queried_object_id();
					$base_page_url = get_permalink( $current_page_id );
					if ( ! $base_page_url ) {
							$base_page_url = home_url( add_query_arg( array(), $wp->request ) );
					}
					// 新しいパラメータを追加
					$redirect_url = add_query_arg(
                        array(
							'tab_name' => $tab_name,
							'data_id' => $data_id,
							'message' => 'found',
                        ),
                        $base_page_url
					);

					$cookie_name = 'ktp_' . $tab_name . '_id';
					setcookie( $cookie_name, $data_id, time() + ( 86400 * 30 ), '/' );

					echo '<script>
                document.addEventListener("DOMContentLoaded", function() {
                    showInfoNotification("' . esc_js( esc_html__( '検索結果を表示しています。', 'ktpwp' ) ) . '");
                    setTimeout(function() {
                        window.location.href = "' . esc_js( $redirect_url ) . '";
                    }, 1000);
                });
            </script>';
					exit;
				}
				// 検索結果が複数ある場合の処理
				elseif ( count( $results ) > 1 ) {
					// 顧客タブと同じく、base_page_urlを使い絶対パスでリンク生成
					global $wp;
					$current_page_id = get_queried_object_id();
					$base_page_url = add_query_arg( array( 'page_id' => $current_page_id ), home_url( $wp->request ) );
					$search_results_html = "<div class='data_contents'><div class='search_list_box'><div class='data_list_title'>■ 検索結果が複数あります！</div><ul>";
					foreach ( $results as $row ) {
						$id = esc_html( $row->id );
						$company_name = esc_html( $row->company_name );
						$category = esc_html( $row->category );
						$link_url = esc_url(
                            add_query_arg(
                                array(
									'tab_name' => $tab_name,
									'data_id' => $id,
									'query_post' => 'update',
                                ),
                                $base_page_url
                            )
                        );
						$search_results_html .= "<li style='text-align:left;'><a href='{$link_url}' style='text-align:left;'>ID：{$id} 会社名：{$company_name} カテゴリー：{$category}</a></li>";
					}
					$search_results_html .= '</ul></div></div>';
					$search_results_html_js = json_encode( $search_results_html );
					$close_redirect_url = esc_url(
                        add_query_arg(
                            array(
								'tab_name' => $tab_name,
								'query_post' => 'srcmode',
                            ),
                            $base_page_url
                        )
                    );
					echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                var searchResultsHtml = $search_results_html_js;
                var popup = document.createElement('div');
                popup.innerHTML = searchResultsHtml;
                popup.style.position = 'fixed';
                popup.style.top = '50%';
                popup.style.left = '50%';
                popup.style.transform = 'translate(-50%, -50%)';
                popup.style.backgroundColor = '#fff';
                popup.style.padding = '20px';
                popup.style.zIndex = '1000';
                popup.style.width = '80%';
                popup.style.maxWidth = '600px';
                popup.style.border = '1px solid #ccc';
                popup.style.borderRadius = '5px';
                popup.style.boxShadow = '0 4px 6px rgba(0,0,0,0.1)';
                document.body.appendChild(popup);
                // ポップアップを閉じるためのボタンを追加
                var closeButton = document.createElement('button');
                closeButton.textContent = '閉じる';
                closeButton.style.fontSize = '0.8em';
                closeButton.style.color = 'black';
                closeButton.style.display = 'block';
                closeButton.style.margin = '10px auto 0';
                closeButton.style.padding = '10px';
                closeButton.style.backgroundColor = '#cdcccc';
                closeButton.style.borderRadius = '5px';
                closeButton.style.borderColor = '#999';
                closeButton.onclick = function() {
                    document.body.removeChild(popup);
                    // 元の検索モードに戻るために特定のURLにリダイレクト（srcmodeに戻す）
                    location.href = '" . $close_redirect_url . "';
                };
                popup.appendChild(closeButton);
            });
            </script>";
				}
				// 検索結果が0件の場合の処理
				else {
					// サプライヤも顧客タブと同じくセッションメッセージ＋リダイレクト方式に統一
					ktpwp_safe_session_start();
					$_SESSION['ktp_search_message'] = '検索結果がありませんでした。';
					// 検索語とno_results=1を付与してsrcmodeにリダイレクト
					global $wp;
					$current_page_id = get_queried_object_id();
					$base_page_url = add_query_arg( array( 'page_id' => $current_page_id ), home_url( $wp->request ) );
					$search_query_encoded = urlencode( $post_data['search_query'] );
					$redirect_url_base = strtok( $base_page_url, '?' );
					$query_string = '?page_id=' . $current_page_id . '&tab_name=' . $tab_name . '&query_post=srcmode&search_query=' . $search_query_encoded . '&no_results=1';
					$redirect_url = $redirect_url_base . $query_string;
					header( 'Location: ' . $redirect_url );
					exit;
				}

				// ロックを解除する
				$wpdb->query( 'UNLOCK TABLES;' );
				// exit; を削除し、通常の画面描画を続行
			}

			// 追加
			elseif ( $query_post == 'insert' ) {

				// 新しいIDを取得（データが完全に0の場合は1から開始）
				$new_id_query = "SELECT COALESCE(MAX(id), 0) + 1 as new_id FROM {$table_name}";
				$new_id_result = $wpdb->get_row( $new_id_query );
				$new_id = $new_id_result && isset( $new_id_result->new_id ) ? intval( $new_id_result->new_id ) : 1;

				$insert_result = $wpdb->insert(
                    $table_name,
                    array(
						'id' => $new_id,
						'time' => current_time( 'mysql' ),
						'company_name' => $company_name,
						'name' => $user_name,
						'email' => $email,
						'url' => $url,
						'representative_name' => $representative_name,
						'phone' => $phone,
						'postal_code' => $postal_code,
						'prefecture' => $prefecture,
						'city' => $city,
						'address' => $address,
						'building' => $building,
						'closing_day' => $closing_day,
						'payment_month' => $payment_month,
						'payment_day' => $payment_day,
						'payment_method' => $payment_method,
						'tax_category' => $tax_category,
						'memo' => $memo,
						'category' => $category,
						'search_field' => $search_field_value,
                    ),
                    array(
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
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( 'Insert error: ' . $wpdb->last_error ); }
					echo '<script>
                document.addEventListener("DOMContentLoaded", function() {
                    showErrorNotification("追加に失敗しました。SQLエラー: ' . esc_js( $wpdb->last_error ) . '");
                });
                </script>';
					$wpdb->query( 'UNLOCK TABLES;' );
				} else {
					$wpdb->query( 'UNLOCK TABLES;' );
					$action = 'update';

					// 追加後のリダイレクト処理
					$cookie_name = 'ktp_' . $tab_name . '_id';
					setcookie( $cookie_name, $new_id, time() + ( 86400 * 30 ), '/' );

					global $wp;
					$current_page_id = get_queried_object_id();
					$base_page_url = get_permalink( $current_page_id );
					if ( ! $base_page_url ) {
						$base_page_url = home_url( add_query_arg( array(), $wp->request ) );
					}
					$redirect_url = add_query_arg(
                        array(
							'tab_name' => $tab_name,
							'data_id' => $new_id,
							'message' => 'added',
                        ),
                        $base_page_url
                    );

					echo '<script>
                    document.addEventListener("DOMContentLoaded", function() {
                        showSuccessNotification("' . esc_js( esc_html__( '新しい協力会社を追加しました。', 'ktpwp' ) ) . '");
                        setTimeout(function() {
                            window.location.href = "' . esc_js( $redirect_url ) . '";
                        }, 1000);
                    });
                </script>';
					exit;
				}
			}

			// 複製
			elseif ( $query_post == 'duplication' ) {
				// データのIDを取得
				$data_id = absint( $post_data['data_id'] );

				if ( $data_id <= 0 ) {
					error_log( 'KTPWP: Invalid data_id for duplication' );
					return;
				}

				// データを取得
				$data = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $table_name, $data_id ), ARRAY_A );

				if ( ! $data ) {
					error_log( 'KTPWP: Data not found for duplication, ID: ' . $data_id );
					return;
				}

				// 会社名の最後に#を追加
				$data['company_name'] .= '#';

				// IDを削除
				unset( $data['id'] );

				// 頻度を0に設定
				$data['frequency'] = 0;

				// search_fieldの値を更新
				$data['search_field'] = implode(
                    ', ',
                    array(
						$data['time'],
						$data['company_name'],
						$data['name'],
						$data['email'],
						$data['url'],
						$data['representative_name'],
						$data['phone'],
						$data['postal_code'],
						$data['prefecture'],
						$data['city'],
						$data['address'],
						$data['building'],
						$data['closing_day'],
						$data['payment_month'],
						$data['payment_day'],
						$data['payment_method'],
						$data['tax_category'],
						$data['memo'],
						$data['category'],
                    )
                );

				// データを挿入
				$insert_result = $wpdb->insert( $table_name, $data );
				if ( $insert_result === false ) {
					error_log( 'Duplication error: ' . $wpdb->last_error );
					echo '<script>
                document.addEventListener("DOMContentLoaded", function() {
                    showErrorNotification("複製に失敗しました。SQLエラー: ' . esc_js( $wpdb->last_error ) . '");
                });
                </script>';
				} else {
					$new_data_id = $wpdb->insert_id;
					$wpdb->query( 'UNLOCK TABLES;' );
					global $wp;
					$current_page_id = get_queried_object_id();
					$base_page_url = get_permalink( $current_page_id );
					if ( ! $base_page_url ) {
						$base_page_url = home_url( add_query_arg( array(), $wp->request ) );
					}
					$redirect_url = add_query_arg(
                        array(
							'tab_name' => $tab_name,
							'data_id' => $new_data_id,
							'message' => 'duplicated',
                        ),
                        $base_page_url
                    );
					$cookie_name = 'ktp_' . $tab_name . '_id';
					setcookie( $cookie_name, $new_data_id, time() + ( 86400 * 30 ), '/' );

					echo '<script>
                    document.addEventListener("DOMContentLoaded", function() {
                        showSuccessNotification("' . esc_js( esc_html__( '複製しました。', 'ktpwp' ) ) . '");
                        setTimeout(function() {
                            window.location.href = "' . esc_js( $redirect_url ) . '";
                        }, 1000);
                    });
                </script>';
				}
			}

			// どの処理にも当てはまらない場合はロック解除
			else {
				// ロックを解除する
				$wpdb->query( 'UNLOCK TABLES;' );
			}
		}

		// -----------------------------
		// テーブルの表示
		// -----------------------------

		function View_Table( $name ) {
			global $wpdb, $wp; // $wp をグローバルに追加

			// ベースURLの構築
			$current_url_path = home_url( $wp->request );
			$base_url_params = array();

			if ( get_queried_object_id() ) {
				$base_url_params['page_id'] = get_queried_object_id();
			}
			if ( isset( $_GET['page'] ) ) {
				$base_url_params['page'] = sanitize_text_field( $_GET['page'] );
			}
			$base_url_params['tab_name'] = $name;
			$base_page_url = add_query_arg( $base_url_params, $current_url_path );

			// フォームアクション用のベースURL (ページネーションパラメータ等は含めない)
			$form_action_base_url = $base_page_url;

			// --- DBエラー表示（セッションから） ---
			ktpwp_safe_session_start();
			if ( isset( $_SESSION['ktp_db_error_message'] ) ) {
				echo '<div class="ktp-db-error" style="background:#ffeaea;color:#b30000;padding:14px 20px;margin:18px 0 20px 0;border:2px solid #b30000;border-radius:7px;font-weight:bold;font-size:1.1em;">'
                . '<span style="font-size:1.2em;">⚠️ <b>DBエラー</b></span><br>'
                . $_SESSION['ktp_db_error_message']
                . '</div>';
				unset( $_SESSION['ktp_db_error_message'] );
			}
			// --- ここまで ---

			// URL パラメータからのメッセージ表示処理を追加
			if ( isset( $_GET['message'] ) ) {
				echo '<script>
            document.addEventListener("DOMContentLoaded", function() {
                const messageType = "' . esc_js( $_GET['message'] ) . '";
                switch (messageType) {
                    case "updated":
                        showSuccessNotification("' . esc_js( __( '更新しました。', 'ktpwp' ) ) . '");
                        break;
                    case "added":
                        showSuccessNotification("' . esc_js( __( '新しい協力会社を追加しました。', 'ktpwp' ) ) . '");
                        break;
                    case "deleted":
                        showSuccessNotification("' . esc_js( __( '削除しました。', 'ktpwp' ) ) . '");
                        break;
                    case "duplicated":
                        showSuccessNotification("' . esc_js( __( '複製しました。', 'ktpwp' ) ) . '");
                        break;
                    case "skill_added":
                        showSuccessNotification("' . esc_js( __( '商品・サービスを追加しました。', 'ktpwp' ) ) . '");
                        break;
                    case "skill_deleted":
                        showSuccessNotification("' . esc_js( __( '商品・サービスを削除しました。', 'ktpwp' ) ) . '");
                        break;
                    case "found":
                        showInfoNotification("' . esc_js( __( '検索結果を表示しています。', 'ktpwp' ) ) . '");
                        break;
                    case "not_found":
                        showWarningNotification("' . esc_js( __( '該当する協力会社が見つかりませんでした。', 'ktpwp' ) ) . '");
                        break;
                }
                // URLからmessageパラメータを削除
                if (window.history.replaceState) {
                    var currentUrl = new URL(window.location.href);
                    if (currentUrl.searchParams.has("message")) {
                        currentUrl.searchParams.delete("message");
                        window.history.replaceState({ path: currentUrl.href }, "", currentUrl.href);
                    }
                }
            });
            </script>';
			}

			// $search_results_listの使用前に初期化
			if ( ! isset( $search_results_list ) ) {
				$search_results_list = '';
			}

			// -----------------------------
			// リスト表示
			// -----------------------------

			// テーブル名
			$table_name = $wpdb->prefix . 'ktp_' . $name;

			// ソート順の取得（デフォルトはIDの降順）
			$sort_by = 'id';
			$sort_order = 'DESC';

			if ( isset( $_GET['sort_by'] ) ) {
				$sort_by = sanitize_text_field( $_GET['sort_by'] );
				// 安全なカラム名のみ許可（SQLインジェクション対策）
				$allowed_columns = array( 'id', 'company_name', 'frequency', 'time', 'category' );
				if ( ! in_array( $sort_by, $allowed_columns ) ) {
					$sort_by = 'id'; // 不正な値の場合はデフォルトに戻す
				}
			}

			if ( isset( $_GET['sort_order'] ) ) {
				$sort_order_param = strtoupper( sanitize_text_field( $_GET['sort_order'] ) );
				// ASCかDESCのみ許可
				$sort_order = ( $sort_order_param === 'ASC' ) ? 'ASC' : 'DESC';
			}

			// 現在のページのURLを生成（動的パーマリンク取得）
			$base_page_url = KTPWP_Main::get_current_page_base_url();

            // -----------------------------
			// ページネーションリンク
			// -----------------------------

			// 表示範囲
			// 一般設定から表示件数を取得（設定クラスが利用可能な場合）
			if ( class_exists( 'KTP_Settings' ) ) {
				$query_limit = KTP_Settings::get_work_list_range();
			} else {
				$query_limit = 20; // フォールバック値
			}

			// ソートプルダウンを追加
			$sort_dropdown = '';

			// 現在のURLからソート用プルダウンのアクションURLを生成
			$sort_url = add_query_arg( array( 'tab_name' => $name ), $base_page_url );

			// ソート用プルダウンのHTMLを構築
			$sort_dropdown = '<div class="sort-dropdown" style="float:right;margin-left:10px;">' .
            '<form method="get" action="' . esc_url( $sort_url ) . '" style="display:flex;align-items:center;">';

			// 現在のGETパラメータを維持するための隠しフィールド
			foreach ( $_GET as $key => $value ) {
				if ( $key !== 'sort_by' && $key !== 'sort_order' ) {
					$sort_dropdown .= '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '">';
				}
			}

			$sort_dropdown .=
            '<select id="sort-select" name="sort_by" style="margin-right:5px;">' .
            '<option value="id" ' . selected( $sort_by, 'id', false ) . '>' . esc_html__( 'ID', 'ktpwp' ) . '</option>' .
            '<option value="company_name" ' . selected( $sort_by, 'company_name', false ) . '>' . esc_html__( '会社名', 'ktpwp' ) . '</option>' .
            '<option value="frequency" ' . selected( $sort_by, 'frequency', false ) . '>' . esc_html__( '頻度', 'ktpwp' ) . '</option>' .
            '<option value="time" ' . selected( $sort_by, 'time', false ) . '>' . esc_html__( '登録日', 'ktpwp' ) . '</option>' .
            '<option value="category" ' . selected( $sort_by, 'category', false ) . '>' . esc_html__( 'カテゴリー', 'ktpwp' ) . '</option>' .
            '</select>' .
            '<select id="sort-order" name="sort_order">' .
            '<option value="ASC" ' . selected( $sort_order, 'ASC', false ) . '>' . esc_html__( '昇順', 'ktpwp' ) . '</option>' .
            '<option value="DESC" ' . selected( $sort_order, 'DESC', false ) . '>' . esc_html__( '降順', 'ktpwp' ) . '</option>' .
            '</select>' .
            '<button type="submit" style="margin-left:5px;padding:4px 8px;background:#f0f0f0;border:1px solid #ccc;border-radius:3px;cursor:pointer;" title="' . esc_attr__( '適用', 'ktpwp' ) . '">' .
            '<span class="material-symbols-outlined" style="font-size:18px;line-height:18px;vertical-align:middle;">check</span>' .
            '</button>' .
            '</form></div>';

			// リスト表示部分の開始 - ktp_data_contentsを開始
			$results_h = <<<END
        <div class="ktp_data_contents">
            <div class="ktp_data_list_box">
            <div class="data_list_title">■ 協力会社リスト {$sort_dropdown}</div>
        END;

			// スタート位置を決める
			$page_stage = $_GET['page_stage'] ?? '';
			$page_start = $_GET['page_start'] ?? 0;
			$flg = $_GET['flg'] ?? '';
			if ( $page_stage == '' ) {
				$page_start = 0;
			}
			
			// 負の値を防ぐ安全対策
			$page_start = max( 0, intval( $page_start ) );

			// 全データ数を取得
			$total_query = "SELECT COUNT(*) FROM {$table_name}";
			$total_rows = $wpdb->get_var( $total_query );
			$total_pages = ceil( $total_rows / $query_limit );

			// 現在のページ番号を計算
			$current_page = floor( $page_start / $query_limit ) + 1;

			// データを取得（選択されたソート順で）
			$sort_column = esc_sql( $sort_by ); // SQLインジェクション対策
			$sort_direction = $sort_order === 'ASC' ? 'ASC' : 'DESC'; // SQLインジェクション対策
			$query = $wpdb->prepare( "SELECT * FROM {$table_name} ORDER BY {$sort_column} {$sort_direction} LIMIT %d, %d", $page_start, $query_limit );
			$post_row = $wpdb->get_results( $query );
			if ( $post_row ) {
				foreach ( $post_row as $row ) {
					  $id = esc_html( $row->id );
					  $time = esc_html( $row->time );
					  $company_name = esc_html( $row->company_name );
					  $user_name = esc_html( $row->name );
					  $email = esc_html( $row->email );
					  $url = esc_html( $row->url );
					  $representative_name = esc_html( $row->representative_name );
					  $phone = esc_html( $row->phone );
					  $postal_code = esc_html( $row->postal_code );
					  $prefecture = esc_html( $row->prefecture );
					  $city = esc_html( $row->city );
					  $address = esc_html( $row->address );
					  $building = esc_html( $row->building );
					  $closing_day = esc_html( $row->closing_day );
					  $payment_month = esc_html( $row->payment_month );
					  $payment_day = esc_html( $row->payment_day );
					  $payment_method = esc_html( $row->payment_method );
					  $tax_category = esc_html( $row->tax_category );
					  $memo = esc_html( $row->memo );
					  $category = esc_html( $row->category );
					  $frequency = esc_html( $row->frequency );                // リスト項目
					  $cookie_name = 'ktp_' . $name . '_id';

					  $query_args = array(
						  'tab_name' => $name,
						  'data_id' => $id,
						  'page_start' => $page_start,
						  'page_stage' => $page_stage,
                    // 'flg' => $flg, // 必要に応じて維持
					  );
					  // 現在のソート順を維持
					  if ( isset( $_GET['sort_by'] ) ) {
						  $query_args['sort_by'] = $_GET['sort_by'];
					  }
					  if ( isset( $_GET['sort_order'] ) ) {
						  $query_args['sort_order'] = $_GET['sort_order'];
					  }

					  $item_link_url = esc_url( add_query_arg( $query_args, $base_page_url ) );
					  $results[] = <<<END
                <a href="{$item_link_url}" onclick="document.cookie = '{$cookie_name}=' + {$id};">
                    <div class="ktp_data_list_item">D: $id $company_name | 担当者: $user_name | $category | <span title="アクセス頻度（クリックされた回数）">頻度($frequency)</span></div>
                </a>
                END;

				}
				$query_max_num = $wpdb->num_rows;
			} else {
				$results[] = '<div class="ktp_data_list_item" style="padding: 15px 20px; background: linear-gradient(135deg, #e3f2fd 0%, #fce4ec 100%); border-radius: 8px; margin: 18px 0; color: #333; font-weight: 600; box-shadow: 0 3px 12px rgba(0,0,0,0.07); display: flex; align-items: center; font-size: 15px; gap: 10px;">'
				. '<span class="material-symbols-outlined" aria-label="データ作成">add_circle</span>'
				. '<span style="font-size: 1em; font-weight: 600;">[＋]ボタンを押してデーターを作成してください</span>'
				. '<span style="margin-left: 18px; font-size: 13px; color: #888;">データがまだ登録されていません</span>'
				. '</div>';
			}

			// 統一されたページネーションデザインを使用
			$results_f = $this->render_pagination( $current_page, $total_pages, $query_limit, $name, $flg, $base_page_url, $total_rows );

			// -----------------------------
			// 詳細表示(GET) - ID取得処理を先に実行
			// -----------------------------

			// 現在表示中の詳細
			$cookie_name = 'ktp_' . $name . '_id';
			$query_id = null;

			// アクションを取得（POST優先、なければGET、なければ'update'）
			$action = 'update';
			if ( isset( $_POST['query_post'] ) ) {
				$action = sanitize_text_field( $_POST['query_post'] );
			} elseif ( isset( $_GET['query_post'] ) ) {
				$action = sanitize_text_field( $_GET['query_post'] );
			}

			// 安全性確保: GETリクエストの場合は危険なアクションを実行しない
			if ( $_SERVER['REQUEST_METHOD'] === 'GET' && in_array( $action, array( 'delete', 'insert', 'search', 'duplicate', 'istmode', 'srcmode' ) ) ) {
				$action = 'update';
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				}
			}

			// 詳細表示ID取得処理（ページネーションメッセージ用）
			if ( $action !== 'istmode' ) {
				if ( isset( $_GET['data_id'] ) && $_GET['data_id'] !== '' ) {
					$query_id = filter_input( INPUT_GET, 'data_id', FILTER_SANITIZE_NUMBER_INT );
					// クッキーも即時更新（追加直後やURL遷移時に常に最新IDを保持）
					setcookie( $cookie_name, $query_id, time() + ( 86400 * 30 ), '/' );
				} else if ( isset( $_COOKIE[ $cookie_name ] ) && $_COOKIE[ $cookie_name ] !== '' ) {
					$cookie_id = filter_input( INPUT_COOKIE, $cookie_name, FILTER_SANITIZE_NUMBER_INT );
					// クッキーIDがDBに存在するかチェック
					$exists = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table_name} WHERE id = %d", $cookie_id ) );
					if ( $exists ) {
						$query_id = $cookie_id;
					} else {
						// 存在しなければ最大ID
						$max_id_row = $wpdb->get_row( "SELECT id FROM {$table_name} ORDER BY id DESC LIMIT 1" );
						$query_id = $max_id_row ? $max_id_row->id : '';
					}
				} else {
					// data_id未指定時は必ずID最大の協力会社を表示
					$max_id_row = $wpdb->get_row( "SELECT id FROM {$table_name} ORDER BY id DESC LIMIT 1" );
					$query_id = $max_id_row ? $max_id_row->id : '';
				}
			} else {
				// 追加モードの場合はIDを取得しない
				$query_id = '';
			}

			// データを取得し変数に格納（$query_idは既に取得済み）
			if ( $action !== 'istmode' && $query_id ) {
				$query = $wpdb->prepare( "SELECT * FROM {$table_name} WHERE id = %d", $query_id );
				$post_row = $wpdb->get_results( $query );
				if ( ! $post_row || count( $post_row ) === 0 ) {
					// データが0件でもフォーム・レイアウトを必ず出す
					$data_id = '';
					$time = '';
					$company_name = '';
					$user_name = '';
					$email = '';
					$url = '';
					$representative_name = '';
					$phone = '';
					$postal_code = '';
					$prefecture = '';
					$city = '';
					$address = '';
					$building = '';
					$closing_day = '';
					$payment_month = '';
					$payment_day = '';
					$payment_method = '';
					$tax_category = '';
					$memo = '';
					$category = '';
					$frequency = '';
					// $post_row を空配列にして以降のフォーム生成処理を通す
					$post_row = array();
					// リスト部分にだけ「データがありません」メッセージを出す（デザインは既に統一済み）
					$results[] = '<div class="ktp_data_list_item" style="padding: 15px 20px; background: linear-gradient(135deg, #ffeef1 0%, #ffeff2 100%); border-radius: 6px; margin: 15px 0; color: #333333; font-weight: 500; box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08); display: flex; align-items: center; font-size: 14px;">'
						. '<span style="margin-right: 10px; color: #ff6b8b; font-size: 18px;" class="material-symbols-outlined">search_off</span>'
						. esc_html__( 'データーがありません。', 'ktpwp' )
						. '</div>';
				}
				foreach ( $post_row as $row ) {
					$data_id = esc_html( $row->id );
					$time = esc_html( $row->time );
					$company_name = esc_html( $row->company_name );
					$user_name = esc_html( $row->name );
					$email = esc_html( $row->email );
					$url = esc_html( $row->url );
					$representative_name = esc_html( $row->representative_name );
					$phone = esc_html( $row->phone );
					$postal_code = esc_html( $row->postal_code );
					$prefecture = esc_html( $row->prefecture );
					$city = esc_html( $row->city );
					$address = esc_html( $row->address );
					$building = esc_html( $row->building );
					$closing_day = esc_html( $row->closing_day );
					$payment_month = esc_html( $row->payment_month );
					$payment_day = esc_html( $row->payment_day );
					$payment_method = esc_html( $row->payment_method );
					$tax_category = esc_html( $row->tax_category );
					$memo = esc_html( $row->memo );
					$qualified_invoice_number = esc_html( $row->qualified_invoice_number );
					$category = esc_html( $row->category );
					$frequency = esc_html( $row->frequency );
				}
			} else {
				// 追加モードの場合は全ての変数を空で初期化
				$data_id = '';
				$time = '';
				$company_name = '';
				$user_name = '';
				$email = '';
				$url = '';
				$representative_name = '';
				$phone = '';
				$postal_code = '';
				$prefecture = '';
				$city = '';
				$address = '';
				$building = '';
				$closing_day = '';
				$payment_month = '';
				$payment_day = '';
				$payment_method = '';
				$tax_category = '';
				$memo = '';
				$qualified_invoice_number = '';
				$category = '';
				$frequency = '';
			}

			// データ取得完了後、協力会社ID表示メッセージを生成
			if ( $query_id ) {
				$display_company_name = isset( $company_name ) && ! empty( $company_name ) ? $company_name : '未設定';

				// 職能データの件数を確認
				$skills_count = 0;
				if ( class_exists( 'KTPWP_Supplier_Skills' ) ) {
					$skills_manager = KTPWP_Supplier_Skills::get_instance();
					if ( $skills_manager ) {
						$skills_count = $skills_manager->get_supplier_skills_count( $query_id );
					}
				}

				// 職能ソート用プルダウンを生成
				$skills_sort_by = isset( $_GET['skills_sort_by'] ) ? sanitize_text_field( $_GET['skills_sort_by'] ) : 'frequency';
				$skills_sort_order = isset( $_GET['skills_sort_order'] ) ? sanitize_text_field( $_GET['skills_sort_order'] ) : 'DESC';

				// 現在のURLからソート用プルダウンのアクションURLを生成
				$skills_sort_url = add_query_arg(
                    array(
						'tab_name' => $name,
						'data_id' => $query_id,
						'query_post' => 'update',
                    ),
                    $base_page_url
                );

				// ソート用プルダウンのHTMLを構築
				$skills_sort_dropdown = '<div class="sort-dropdown" style="float:right;margin-left:10px;">' .
					'<form method="get" action="' . esc_url( $skills_sort_url ) . '" style="display:flex;align-items:center;">';

				// 現在のGETパラメータを維持するための隠しフィールド
				foreach ( $_GET as $key => $value ) {
					if ( ! in_array( $key, array( 'skills_sort_by', 'skills_sort_order' ) ) ) {
						$skills_sort_dropdown .= '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '">';
					}
				}

				$skills_sort_dropdown .=
					'<select id="skills-sort-select" name="skills_sort_by" style="margin-right:5px;">' .
					'<option value="id" ' . selected( $skills_sort_by, 'id', false ) . '>' . esc_html__( 'ID', 'ktpwp' ) . '</option>' .
					'<option value="product_name" ' . selected( $skills_sort_by, 'product_name', false ) . '>' . esc_html__( '商品名', 'ktpwp' ) . '</option>' .
					'<option value="frequency" ' . selected( $skills_sort_by, 'frequency', false ) . '>' . esc_html__( '頻度', 'ktpwp' ) . '</option>' .
					'</select>' .
					'<select id="skills-sort-order" name="skills_sort_order">' .
					'<option value="ASC" ' . selected( $skills_sort_order, 'ASC', false ) . '>' . esc_html__( '昇順', 'ktpwp' ) . '</option>' .
					'<option value="DESC" ' . selected( $skills_sort_order, 'DESC', false ) . '>' . esc_html__( '降順', 'ktpwp' ) . '</option>' .
					'</select>' .
					'<button type="submit" style="margin-left:5px;padding:4px 8px;background:#f0f0f0;border:1px solid #ccc;border-radius:3px;cursor:pointer;" title="' . esc_attr__( '適用', 'ktpwp' ) . '">' .
					'<span class="material-symbols-outlined" style="font-size:18px;line-height:18px;vertical-align:middle;">check</span>' .
					'</button>' .
					'</form></div>';

				// 職能データの件数に応じてタイトルを変更
				if ( $skills_count > 0 ) {
					// 職能データが1件以上ある場合：IDを表示
					$current_id_message = '<div class="data_skill_list_title" style="display: flex; align-items: center;">'
						. '<div style="display: flex; align-items: center; gap: 8px;">'
						. '■ ' . esc_html( $display_company_name ) . '（ID: ' . esc_html( $query_id ) . '）の商品'
						. '</div>'
						. $skills_sort_dropdown
						. '</div>';
				} else {
					// 職能データが0件の場合：IDを非表示
					$current_id_message = '<div class="data_skill_list_title" style="display: flex; align-items: center;">'
						. '<div style="display: flex; align-items: center; gap: 8px;">'
						. '■ ' . esc_html( $display_company_name ) . 'の商品'
						. '</div>'
						. $skills_sort_dropdown
						. '</div>';
				}
			} else {
				$current_id_message = '<div style="padding: 15px 20px; background: linear-gradient(135deg, #fff3cd 0%, #fff8e1 100%); border-radius: 6px; margin: 15px 0; color: #856404; font-weight: 600; text-align: center; box-shadow: 0 3px 12px rgba(0,0,0,0.07); display: flex; align-items: center; justify-content: center; font-size: 16px; gap: 10px;">'
					. '<span class="material-symbols-outlined" style="color: #ffc107;">info</span>'
					. ( $action === 'istmode' ? '新規追加モード' : 'まだサービスがありません。' )
					. '</div>';
			}

			// 変数の初期化を確実に行う（未定義変数エラーを防ぐ）
			$qualified_invoice_number = isset( $qualified_invoice_number ) ? $qualified_invoice_number : '';
			$category = isset( $category ) ? $category : '';
			$frequency = isset( $frequency ) ? $frequency : '';
			$memo = isset( $memo ) ? $memo : '';

			// data_listに協力会社ID表示メッセージを追加 - 協力会社リストBOXを継続（職能セクションを含むため）
			$data_list = $results_h . implode( $results ) . $results_f . $current_id_message;

			// 表示するフォーム要素を定義
			$fields = array(
				// 'ID' => ['type' => 'text', 'name' => 'data_id', 'readonly' => true],
				'会社名' => array(
					'type' => 'text',
					'name' => 'company_name',
					'required' => true,
					'placeholder' => '必須 法人名または屋号',
				),
				'名前' => array(
					'type' => 'text',
					'name' => 'user_name',
					'placeholder' => '担当者名',
				),
				'メール' => array(
					'type' => 'email',
					'name' => 'email',
				),
				'URL' => array(
					'type' => 'text',
					'name' => 'url',
					'placeholder' => 'https://....',
				),
				'代表者名' => array(
					'type' => 'text',
					'name' => 'representative_name',
					'placeholder' => '代表者名',
				),
				'電話番号' => array(
					'type' => 'text',
					'name' => 'phone',
					'pattern' => '\d*',
					'placeholder' => '半角数字 ハイフン不要',
				),
				'郵便番号' => array(
					'type' => 'text',
					'name' => 'postal_code',
					'pattern' => '[0-9]*',
					'placeholder' => '半角数字 ハイフン不要',
				),
				'都道府県' => array(
					'type' => 'text',
					'name' => 'prefecture',
				),
				'市区町村' => array(
					'type' => 'text',
					'name' => 'city',
				),
				'番地' => array(
					'type' => 'text',
					'name' => 'address',
				),
				'建物名' => array(
					'type' => 'text',
					'name' => 'building',
				),
				'締め日' => array(
					'type' => 'select',
					'name' => 'closing_day',
					'options' => array( '5日', '10日', '15日', '20日', '25日', '末日', 'なし' ),
					'default' => 'なし',
				),
				'支払月' => array(
					'type' => 'select',
					'name' => 'payment_month',
					'options' => array( '今月', '翌月', '翌々月', 'その他' ),
					'default' => 'その他',
				),
				'支払日' => array(
					'type' => 'select',
					'name' => 'payment_day',
					'options' => array( '即日', '5日', '10日', '15日', '20日', '25日', '末日' ),
					'default' => '即日',
				),
				'支払方法' => array(
					'type' => 'select',
					'name' => 'payment_method',
					'options' => array( '銀行振込', 'クレジット', '現金集金' ),
					'default' => '銀行振込',
				),
				'税区分' => array(
					'type' => 'select',
					'name' => 'tax_category',
					'options' => array( '外税', '内税' ),
					'default' => '内税',
				),
				'メモ' => array(
					'type' => 'textarea',
					'name' => 'memo',
				),
				'適格請求書番号' => array(
					'type' => 'text',
					'name' => 'qualified_invoice_number',
					'placeholder' => 'T1234567890123',
				),
				'カテゴリー' => array(
					'type' => 'text',
					'name' => 'category',
					'options' => '一般',
					'suggest' => true,
				),
			);

			// フォーム表示用のアクション（istmode:追加、srcmode:検索、update:更新）
			$form_action = $action;
			if ( $action === 'istmode' || $action === 'srcmode' ) {
				$data_id = ''; // 追加モードの場合はdata_idを空に
			}

			// 空のフォームを表示(追加モードの場合)
			if ( $action === 'istmode' ) {
				// 追加モードは data_id を空にする
				$data_id = '';
				// 詳細表示部分の開始
				$data_title = '<div class="data_detail_box">' .
                          '<div class="data_detail_title">■ ' . esc_html__( '協力会社追加中', 'ktpwp' ) . '</div>';
				// 郵便番号から住所を自動入力するためのJavaScriptコードを追加（日本郵政のAPIを利用）
				$data_forms = <<<END
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                var postalCode = document.querySelector('input[name="postal_code"]');
                var prefecture = document.querySelector('input[name="prefecture"]');
                var city = document.querySelector('input[name="city"]');
                var address = document.querySelector('input[name="address"]');
                postalCode.addEventListener('blur', function() {
                    var xhr = new XMLHttpRequest();
                    xhr.open('GET', 'https://zipcloud.ibsnet.co.jp/api/search?zipcode=' + postalCode.value);
                    xhr.addEventListener('load', function() {
                        var response = JSON.parse(xhr.responseText);
                        if (response.results) {
                            var data = response.results[0];
                            prefecture.value = data.address1;
                            city.value = data.address2 + data.address3; // 市区町村と町名を結合
                            address.value = ''; // 番地は空欄に
                        }
                    });
                    xhr.send();
                });
            });
            </script>
            END;
				// 空のフォームフィールドを生成
				$data_forms .= '<form method="post" action="' . esc_url( $form_action_base_url ) . '">';
				if ( function_exists( 'wp_nonce_field' ) ) {
					$data_forms .= wp_nonce_field( 'ktp_supplier_action', 'ktp_supplier_nonce', true, false ); }
				foreach ( $fields as $label => $field ) {
					$value = '';
					$pattern = isset( $field['pattern'] ) ? " pattern=\"{$field['pattern']}\"" : '';
					$required = isset( $field['required'] ) && $field['required'] ? ' required' : '';
					$fieldName = $field['name'];
					$placeholder = isset( $field['placeholder'] ) ? " placeholder=\"{$field['placeholder']}\"" : '';
					if ( $field['type'] === 'textarea' ) {
						$data_forms .= "<div class=\"form-group\"><label>{$label}：</label> <textarea name=\"{$fieldName}\"{$pattern}{$required}>{$value}</textarea></div>";
					} elseif ( $field['type'] === 'select' ) {
						$options = '';
						foreach ( $field['options'] as $option ) {
							$selected = $value === $option ? ' selected' : '';
							$options .= "<option value=\"{$option}\"{$selected}>{$option}</option>";
						}
						$default = isset( $field['default'] ) ? $field['default'] : '';
						$data_forms .= "<div class=\"form-group\"><label>{$label}：</label> <select name=\"{$fieldName}\"{$required}><option value=\"\">{$default}</option>{$options}</select></div>";
					} else {
						$data_forms .= "<div class=\"form-group\"><label>{$label}：</label> <input type=\"{$field['type']}\" name=\"{$fieldName}\" value=\"{$value}\"{$pattern}{$required}{$placeholder}></div>";
					}
				}
				$data_forms .= "<div class='button'>";
				// 追加実行ボタン
				$data_forms .= "<input type='hidden' name='query_post' value='insert'>";
				$data_forms .= "<input type='hidden' name='data_id' value=''>";
				$data_forms .= "<button type='submit' name='send_post' title='追加実行'><span class='material-symbols-outlined'>select_check_box</span></button>";
				// キャンセルボタン（JavaScriptでリダイレクト）
				global $wp;
				$current_page_id = get_queried_object_id();
				$base_page_url = get_permalink( $current_page_id );
				if ( ! $base_page_url ) {
					$base_page_url = home_url( add_query_arg( array(), $wp->request ) );
				}
				$cancel_url = add_query_arg( array( 'tab_name' => $name ), $base_page_url );
				$data_forms .= "<button type='button' onclick='window.location.href=\"" . esc_js( $cancel_url ) . "\"' title='キャンセル'><span class='material-symbols-outlined'>disabled_by_default</span></button>";
				$data_forms .= '<div class="add"></div>';
				$data_forms .= '</div>';
				$data_forms .= '</form>';
			}

			// 空のフォームを表示(検索モードの場合)
			elseif ( $action === 'srcmode' ) {
				// 表題
				$data_title = '<div class="data_detail_box search-mode">' .
                          '<div class="data_detail_title">■ ' . esc_html__( '協力会社の詳細（検索モード）', 'ktpwp' ) . '</div>';

				// 検索モード用のフォーム（顧客タブと同じ構造・装飾に）
				$data_forms = '<div class="search-mode-form ktpwp-search-form" style="background-color: #f8f9fa !important; border: 2px solid #0073aa !important; border-radius: 8px !important; padding: 20px !important; margin: 10px 0 !important; box-shadow: 0 2px 8px rgba(0, 115, 170, 0.1) !important;">';
				$data_forms .= '<form method="post" action="' . esc_url( $form_action_base_url ) . '">';
				$data_forms .= function_exists( 'wp_nonce_field' ) ? wp_nonce_field( 'ktp_supplier_action', 'ktp_supplier_nonce', true, false ) : '';
				// 検索クエリの値を取得（POSTが優先、次にGET）
				$search_query_value = '';
				if ( isset( $_POST['search_query'] ) ) {
					$search_query_value = esc_attr( $_POST['search_query'] );
				} elseif ( isset( $_GET['search_query'] ) ) {
					$search_query_value = esc_attr( urldecode( $_GET['search_query'] ) );
				}
				$data_forms .= '<div class="form-group" style="margin-bottom: 15px !important;">';
				$data_forms .= '<input type="text" name="search_query" placeholder="フリーワード検索" value="' . $search_query_value . '" style="width: 100% !important; padding: 12px !important; font-size: 16px !important; border: 2px solid #ddd !important; border-radius: 5px !important; box-sizing: border-box !important; transition: border-color 0.3s ease !important;">';
				$data_forms .= '</div>';

				// 検索結果がない場合のメッセージ表示
				if ( ( isset( $_POST['query_post'] ) && $_POST['query_post'] === 'search' && empty( $search_results_list ) ) ||
                ( isset( $_GET['no_results'] ) && $_GET['no_results'] === '1' ) ) {
					$no_results_id = 'no-results-' . uniqid();
					$data_forms .= '<div id="' . $no_results_id . '" class="no-results" style="
                    padding: 15px 20px !important;
                    background: linear-gradient(135deg, #ffeef1 0%, #ffeff2 100%) !important;
                    border-radius: 6px !important;
                    margin: 15px 0 !important;
                    color: #333333 !important;
                    font-weight: 500 !important;
                    box-shadow: 0 3px 10px rgba(0,0,0,0.08) !important;
                    display: flex !important;
                    align-items: center !important;
                    font-size: 14px !important;
                    opacity: 1;
                    transition: opacity 0.3s ease-in-out !important;
                ">
                <span style="
                    margin-right: 10px !important;
                    color: #ff6b8b !important;
                    font-size: 18px !important;
                " class="material-symbols-outlined">search_off</span>
                検索結果が見つかりませんでした。別のキーワードをお試しください。
                </div>
                <script>
                document.addEventListener("DOMContentLoaded", function() {
                    var noResultsEl = document.getElementById("' . $no_results_id . '");
                    if (noResultsEl) {
                        // 4秒後に非表示にする
                        setTimeout(function() {
                            noResultsEl.style.opacity = "0";
                            setTimeout(function() {
                                if (noResultsEl.parentNode) {
                                    noResultsEl.style.display = "none";
                                }
                            }, 300);
                        }, 4000);
                    }
                });
                </script>';
				}

				// ボタンを横並びにするためのラップクラスを追加
				$data_forms .= '<div class="button-group" style="display: flex !important; justify-content: space-between !important; margin-top: 20px !important;">';

				// 検索実行ボタン
				$data_forms .= '<input type="hidden" name="query_post" value="search">';
				$data_forms .= '<button type="submit" name="send_post" title="検索実行" style="background-color: #0073aa !important; color: white !important; border: none !important; padding: 10px 20px !important; cursor: pointer !important; border-radius: 5px !important; display: flex !important; align-items: center !important; gap: 5px !important; font-size: 14px !important; font-weight: 500 !important; transition: all 0.3s ease !important;">';
				$data_forms .= '<span class="material-symbols-outlined" style="font-size: 18px !important;">search</span>';
				$data_forms .= '検索実行';
				$data_forms .= '</button>';
				$data_forms .= '</form>';

				// 検索モードのキャンセルボタン（独立したフォーム）
				$data_forms .= '<form method="post" action="' . esc_url( $form_action_base_url ) . '" style="margin: 0 !important;">';
				$data_forms .= function_exists( 'wp_nonce_field' ) ? wp_nonce_field( 'ktp_supplier_action', 'ktp_supplier_nonce', true, false ) : '';
				$data_forms .= '<input type="hidden" name="query_post" value="update">';
				$data_forms .= '<button type="submit" name="send_post" title="キャンセル" style="background-color: #666 !important; color: white !important; border: none !important; padding: 10px 20px !important; cursor: pointer !important; border-radius: 5px !important; display: flex !important; align-items: center !important; gap: 5px !important; font-size: 14px !important; font-weight: 500 !important; transition: all 0.3s ease !important;">';
				$data_forms .= '<span class="material-symbols-outlined" style="font-size: 18px !important;">disabled_by_default</span>';
				$data_forms .= 'キャンセル';
				$data_forms .= '</button>';
				$data_forms .= '</form>';

				$data_forms .= '</div>'; // ボタンラップクラスの閉じタグ
				$data_forms .= '</div>'; // search-mode-formの閉じタグ
			}

			// 追加・検索 以外なら更新フォームを表示
			elseif ( $action !== 'srcmode' && $action !== 'istmode' ) {

				// 郵便番号から住所を自動入力するためのJavaScriptコードを追加（日本郵政のAPIを利用）
				$data_forms = <<<END
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                var postalCode = document.querySelector('input[name="postal_code"]');
                var prefecture = document.querySelector('input[name="prefecture"]');
                var city = document.querySelector('input[name="city"]');
                var address = document.querySelector('input[name="address"]');
                if (postalCode) {
                    postalCode.addEventListener('blur', function() {
                        var xhr = new XMLHttpRequest();
                        xhr.open('GET', 'https://zipcloud.ibsnet.co.jp/api/search?zipcode=' + postalCode.value);
                        xhr.addEventListener('load', function() {
                            var response = JSON.parse(xhr.responseText);
                            if (response.results) {
                                var data = response.results[0];
                                if (prefecture) prefecture.value = data.address1;
                                if (city) city.value = data.address2 + data.address3; // 市区町村と町名を結合
                                if (address) address.value = ''; // 番地は空欄に
                            }
                        });
                        xhr.send();
                    });
                }
            });
            </script>
            END;

				// ボタングループHTML生成
				$button_group_html = '<div class="button-group" style="display: flex; gap: 10px; margin-left: auto;">';

				// 削除ボタン
				$button_group_html .= '<form method="post" action="" style="margin: 0;">';
				$button_group_html .= wp_nonce_field( 'ktp_supplier_action', 'ktp_supplier_nonce', true, false );
				$button_group_html .= '<input type="hidden" name="data_id" value="' . esc_attr( $query_id ) . '">';
				$button_group_html .= '<input type="hidden" name="query_post" value="delete">';
				$button_group_html .= '<button type="submit" name="send_post" title="' . esc_attr__( '削除する', 'ktpwp' ) . '" onclick="return confirm(\'' . esc_js( __( '本当に削除しますか？', 'ktpwp' ) ) . '\')" class="button-style delete-submit-btn">';
				$button_group_html .= '<span class="material-symbols-outlined">delete</span>';
				$button_group_html .= '</button>';
				$button_group_html .= '</form>';

				// 追加モードボタン
				$add_action = 'istmode';
				$button_group_html .= '<form method="post" action="" style="margin: 0;">';
				$button_group_html .= wp_nonce_field( 'ktp_supplier_action', 'ktp_supplier_nonce', true, false );
				$button_group_html .= '<input type="hidden" name="data_id" value="">';
				$button_group_html .= '<input type="hidden" name="query_post" value="' . esc_attr( $add_action ) . '">';
				$button_group_html .= '<button type="submit" name="send_post" title="' . esc_attr__( '追加する', 'ktpwp' ) . '" class="button-style add-submit-btn">';
				$button_group_html .= '<span class="material-symbols-outlined">add</span>';
				$button_group_html .= '</button>';
				$button_group_html .= '</form>';

				// 検索モードボタン
				$search_action = 'srcmode';
				$button_group_html .= '<form method="post" action="" style="margin: 0;">';
				$button_group_html .= wp_nonce_field( 'ktp_supplier_action', 'ktp_supplier_nonce', true, false );
				$button_group_html .= '<input type="hidden" name="query_post" value="' . esc_attr( $search_action ) . '">';
				$button_group_html .= '<button type="submit" name="send_post" title="' . esc_attr__( '検索する', 'ktpwp' ) . '" class="button-style search-mode-btn">';
				$button_group_html .= '<span class="material-symbols-outlined">search</span>';
				$button_group_html .= '</button>';
				$button_group_html .= '</form>';

				$button_group_html .= '</div>'; // ボタングループ終了

				// 表題にボタングループを含める
				$data_title = '<div class="data_detail_box"><div class="data_detail_title" style="display: flex; align-items: center; justify-content: space-between;">
            <div>■ 協力会社の詳細（ ID: ' . esc_html( $query_id ) . ' ）</div>' . $button_group_html . '</div>';

				// メイン更新フォーム
				$data_forms .= '<form method="post" action="' . esc_url( $form_action_base_url ) . '">';
				if ( function_exists( 'wp_nonce_field' ) ) {
					$data_forms .= wp_nonce_field( 'ktp_supplier_action', 'ktp_supplier_nonce', true, false );
				}

				foreach ( $fields as $label => $field ) {
					$value = ( $action === 'istmode' ) ? '' : ( isset( ${$field['name']} ) ? ${$field['name']} : '' );
					$pattern = isset( $field['pattern'] ) ? " pattern=\"{$field['pattern']}\"" : '';
					$required = isset( $field['required'] ) && $field['required'] ? ' required' : '';
					$placeholder = isset( $field['placeholder'] ) ? " placeholder=\"{$field['placeholder']}\"" : '';

					if ( $field['type'] === 'textarea' ) {
						$data_forms .= "<div class=\"form-group\"><label>{$label}：</label> <textarea name=\"{$field['name']}\"{$pattern}{$required}>{$value}</textarea></div>";
					} elseif ( $field['type'] === 'select' ) {
						$options = '';
						foreach ( $field['options'] as $option ) {
							// 追加モードでは何も選択しない
							$selected = ( $action === 'istmode' ) ? '' : ( $value === $option ? ' selected' : '' );
							$options .= "<option value=\"{$option}\"{$selected}>{$option}</option>";
						}
						$data_forms .= "<div class=\"form-group\"><label>{$label}：</label> <select name=\"{$field['name']}\"{$required}>{$options}</select></div>";
					} else {
						$data_forms .= "<div class=\"form-group\"><label>{$label}：</label> <input type=\"{$field['type']}\" name=\"{$field['name']}\" value=\"{$value}\"{$pattern}{$required}{$placeholder}></div>";
					}
				}

				// hidden data_id は常に現在表示中のID（$query_id）
				$data_forms .= "<input type=\"hidden\" name=\"data_id\" value=\"{$query_id}\">";
				$data_forms .= '<input type="hidden" name="query_post" value="update">';

				// 検索リストを生成
				$data_forms .= $search_results_list;
				$data_forms .= "<div class='button'>";
				// 更新ボタンのみ残す
				$data_forms .= '<button type="submit" name="send_post" title="更新する"><span class="material-symbols-outlined">cached</span></button>';
				$data_forms .= '<div class="add"></div>';
				$data_forms .= '</div>';
				$data_forms .= '</form>';
			}

			$data_forms .= '</div>'; // フォームを囲む<div>タグの終了

			// スキル管理インターフェースを表示（協力会社が選択されている場合のみ）
			$skills_section = '';
			if ( $query_id && is_numeric( $query_id ) && $query_id > 0 ) {
				// スキル管理クラスをロード
				if ( ! class_exists( 'KTPWP_Supplier_Skills' ) ) {
					require_once __DIR__ . '/class-ktpwp-supplier-skills.php';
				}

				$skills_manager = KTPWP_Supplier_Skills::get_instance();
				if ( $skills_manager ) {
					// 協力会社の職能追加フォームと職能リストを協力会社リストBOX内に配置
					$skills_section = $skills_manager->render_skills_interface( $query_id );
				}
			}

			// 協力会社リストBOXの終了
			$skills_section .= '</div>'; // ktp_data_list_boxの終了

			// 詳細表示部分の終了
			$div_end = <<<END
            </div> <!-- data_detail_boxの終了 -->
        </div> <!-- ktp_data_contentsの終了 -->
        END;

			// -----------------------------
			// テンプレート印刷
			// -----------------------------

			// 協力会社情報のプレビュー用HTMLを生成
			$supplier_preview_html = $this->generateSupplierPreviewHTML(
                array(
					'company_name' => $company_name,
					'name' => $user_name,
					'email' => $email,
					'url' => $url,
					'representative_name' => $representative_name,
					'phone' => $phone,
					'postal_code' => $postal_code,
					'prefecture' => $prefecture,
					'city' => $city,
					'address' => $address,
					'building' => $building,
					'closing_day' => $closing_day,
					'payment_month' => $payment_month,
					'payment_day' => $payment_day,
					'payment_method' => $payment_method,
					'tax_category' => $tax_category,
					'qualified_invoice_number' => $qualified_invoice_number,
					'category' => $category,
					'frequency' => $frequency,
					'memo' => $memo,
                )
            );

			// PHP
			$supplier_preview_html = json_encode( $supplier_preview_html );  // JSON形式にエンコード

			// JavaScript
			$print = <<<END
        <script>
            // var isPreviewOpen = false; // プレビュー機能は廃止
            
            function printContent() {
                var printContent = $supplier_preview_html;
                var printWindow = window.open('', '_blank');
                printWindow.document.open();
                printWindow.document.write('<html><head><title>協力会社情報印刷</title></head><body>');
                printWindow.document.write(printContent);
                printWindow.document.write('<script>window.onafterprint = function(){ window.close(); }<\/script>');
                printWindow.document.write('</body></html>');
                printWindow.document.close();
                printWindow.print();

                // プレビュー機能は廃止
                // if (isPreviewOpen) {
                //     togglePreview();
                // }
            }

            // プレビュー機能（廃止）
            // function togglePreview() {
            //     var previewWindow = document.getElementById('previewWindow');
            //     var previewButton = document.getElementById('previewButton');
            //     if (isPreviewOpen) {
            //         previewWindow.style.display = 'none';
            //         previewButton.innerHTML = '<span class="material-symbols-outlined" aria-label="プレビュー">preview</span>';
            //         isPreviewOpen = false;
            //     } else {
            //         var printContent = $supplier_preview_html;
            //         previewWindow.innerHTML = printContent;
            //         previewWindow.style.display = 'block';
            //         previewButton.innerHTML = '<span class="material-symbols-outlined" aria-label="閉じる">close</span>';
            //         isPreviewOpen = true;
            //     }
            // }
        </script>
        <!-- コントローラー/プレビューアイコン（プレビューは廃止） -->
        <div class="controller">
                <button onclick="printContent()" title="印刷する" style="padding: 6px 10px; font-size: 12px;">
                    <span class="material-symbols-outlined" aria-label="印刷">print</span>
                </button>
        </div>
        END;

			// コンテンツを返す - 協力会社職能BOXを協力会社リストBOXの内部に配置
			$content = $print . $data_list . $skills_section . $data_title . $data_forms . $search_results_list . $div_end;
			return $content;
		}

		/**
		 * Handle skills operations (add, delete, etc.)
		 *
		 * @since 1.0.0
		 * @param array $post_data POST data array
		 * @return void
		 */
		private function handle_skills_operations( $post_data ) {
			// Check if this is a skills operation
			if ( ! isset( $post_data['skills_action'] ) ) {
				return;
			}

			// Security check - verify nonce
			if ( ! isset( $post_data['ktp_skills_nonce'] ) ||
             ! wp_verify_nonce( $post_data['ktp_skills_nonce'], 'ktp_skills_action' ) ) {
				// Display security error message and stop execution
				echo '<script>
            document.addEventListener("DOMContentLoaded", function() {
                showErrorNotification("セキュリティチェックに失敗しました。ページを更新して再度お試しください。");
            });
            </script>';
				return;
			}

			// Check user permissions
			if ( ! current_user_can( 'edit_posts' ) ) {
				echo '<script>
            document.addEventListener("DOMContentLoaded", function() {
                showErrorNotification("この操作を行う権限がありません。");
            });
            </script>';
				return;
			}

			$action = sanitize_key( $post_data['skills_action'] );

			switch ( $action ) {
				case 'add_skill':
					$this->handle_add_skill( $post_data );
					break;
				case 'delete_skill':
					$this->handle_delete_skill( $post_data );
					break;
			}
		}

		/**
		 * Handle adding a new skill
		 *
		 * @since 1.0.0
		 * @param array $post_data POST data array
		 * @return void
		 */
		private function handle_add_skill( $post_data ) {
			// Load skills manager
			if ( ! class_exists( 'KTPWP_Supplier_Skills' ) ) {
				require_once __DIR__ . '/class-ktpwp-supplier-skills.php';
			}

			$skills_manager = KTPWP_Supplier_Skills::get_instance();
			if ( ! $skills_manager ) {
				echo '<script>
            document.addEventListener("DOMContentLoaded", function() {
                showErrorNotification("スキル管理システムが利用できません。");
            });
            </script>';
				return;
			}

			// Sanitize input data
			$supplier_id = isset( $post_data['supplier_id'] ) ? absint( $post_data['supplier_id'] ) : 0;
			$product_name = isset( $post_data['product_name'] ) ? sanitize_text_field( $post_data['product_name'] ) : '';
			$unit_price = isset( $post_data['unit_price'] ) ? floatval( $post_data['unit_price'] ) : 0;
			$quantity = isset( $post_data['quantity'] ) ? absint( $post_data['quantity'] ) : 1;
			$unit = isset( $post_data['unit'] ) ? sanitize_text_field( $post_data['unit'] ) : '式';
			
			// Handle tax_rate - allow NULL values
			$tax_rate = isset( $post_data['tax_rate'] ) ? $post_data['tax_rate'] : '';
			$tax_rate = ( $tax_rate === '' || $tax_rate === null ) ? null : floatval( $tax_rate );

			// Validate required fields
			if ( empty( $supplier_id ) || empty( $product_name ) ) {
				echo '<script>
            document.addEventListener("DOMContentLoaded", function() {
                showErrorNotification("必要な情報が不足しています。");
            });
            </script>';
				return;
			}

			// Add the skill
			$result = $skills_manager->add_skill( $supplier_id, $product_name, $unit_price, $quantity, $unit, $tax_rate );

			if ( $result ) {
				// POSTデータ重複送信防止のためリダイレクト
				$redirect_url = remove_query_arg( array( 'message' ), $_SERVER['REQUEST_URI'] );
				$redirect_url = add_query_arg( 'message', 'skill_added', $redirect_url );
				wp_redirect( $redirect_url );
				exit;
			} else {
				echo '<script>
            document.addEventListener("DOMContentLoaded", function() {
                showErrorNotification("商品・サービスの追加に失敗しました。");
            });
            </script>';
			}
		}

		/**
		 * Handle deleting a skill
		 *
		 * @since 1.0.0
		 * @param array $post_data POST data array
		 * @return void
		 */
		private function handle_delete_skill( $post_data ) {
			// Load skills manager
			if ( ! class_exists( 'KTPWP_Supplier_Skills' ) ) {
				require_once __DIR__ . '/class-ktpwp-supplier-skills.php';
			}

			$skills_manager = KTPWP_Supplier_Skills::get_instance();
			if ( ! $skills_manager ) {
				echo '<script>
            document.addEventListener("DOMContentLoaded", function() {
                showErrorNotification("スキル管理システムが利用できません。");
            });
            </script>';
				return;
			}

			// Sanitize input data
			$skill_id = isset( $post_data['skill_id'] ) ? absint( $post_data['skill_id'] ) : 0;

			// Validate required fields
			if ( empty( $skill_id ) ) {
				echo '<script>
            document.addEventListener("DOMContentLoaded", function() {
                showErrorNotification("削除する商品・サービスが指定されていません。");
            });
            </script>';
				return;
			}

			// Delete the skill
			$result = $skills_manager->delete_skill( $skill_id );

			if ( $result ) {
				// POSTデータ重複送信防止のためリダイレクト
				$redirect_url = remove_query_arg( array( 'message' ), $_SERVER['REQUEST_URI'] );
				$redirect_url = add_query_arg( 'message', 'skill_deleted', $redirect_url );
				wp_redirect( $redirect_url );
				exit;
			} else {
				echo '<script>
            document.addEventListener("DOMContentLoaded", function() {
                showErrorNotification("商品・サービスの削除に失敗しました。");
            });
            </script>';
			}
		}

		/**
		 * 統一されたページネーションデザインをレンダリング
		 *
		 * @param int    $current_page 現在のページ
		 * @param int    $total_pages 総ページ数
		 * @param int    $query_limit 1ページあたりの件数
		 * @param string $name テーブル名
		 * @param string $flg フラグ
		 * @param string $base_page_url ベースURL
		 * @param int    $total_rows 総データ数
		 * @return string ページネーションHTML
		 */
		private function render_pagination( $current_page, $total_pages, $query_limit, $name, $flg, $base_page_url, $total_rows ) {
			// 0データの場合でもページネーションを表示（要件対応）
			// データが0件の場合はtotal_pagesが0になるため、最低1ページとして扱う
			if ( $total_pages == 0 ) {
				$total_pages = 1;
				$current_page = 1;
			}

			$pagination_html = '<div class="pagination" style="text-align: center; margin: 20px 0; padding: 20px 0;">';

			// 1行目：ページ情報表示
			$pagination_html .= '<div style="margin-bottom: 18px; color: #4b5563; font-size: 14px; font-weight: 500;">';
			$pagination_html .= esc_html( $current_page ) . ' / ' . esc_html( $total_pages ) . ' ページ（全 ' . esc_html( $total_rows ) . ' 件）';
			$pagination_html .= '</div>';

			// 2行目：ページネーションボタン
			$pagination_html .= '<div style="display: flex; align-items: center; gap: 4px; flex-wrap: wrap; justify-content: center; width: 100%;">';

			// ページネーションボタンのスタイル（正円ボタン）
			$button_style = 'display: inline-block; width: 36px; height: 36px; padding: 0; margin: 0 2px; text-decoration: none; border: 1px solid #ddd; border-radius: 50%; color: #333; background: #fff; transition: all 0.3s ease; box-shadow: 0 1px 3px rgba(0,0,0,0.1); line-height: 34px; text-align: center; vertical-align: middle; font-size: 14px;';
			$current_style = 'background: #1976d2; color: white; border-color: #1976d2; font-weight: bold; transform: translateY(-1px); box-shadow: 0 2px 5px rgba(0,0,0,0.2);';
			$hover_effect = 'onmouseover="this.style.backgroundColor=\'#f5f5f5\'; this.style.transform=\'translateY(-1px)\'; this.style.boxShadow=\'0 2px 5px rgba(0,0,0,0.15)\';" onmouseout="this.style.backgroundColor=\'#fff\'; this.style.transform=\'none\'; this.style.boxShadow=\'0 1px 3px rgba(0,0,0,0.1)\';"';

			// 前のページボタン
			if ( $current_page > 1 && $total_pages > 1 ) {
				$prev_args = array(
					'tab_name' => $name,
					'page_start' => ( $current_page - 2 ) * $query_limit,
					'page_stage' => 2,
					'flg' => $flg,
				);
				// 現在のソート順を維持
				if ( isset( $_GET['sort_by'] ) ) {
					$prev_args['sort_by'] = $_GET['sort_by'];
				}
				if ( isset( $_GET['sort_order'] ) ) {
					$prev_args['sort_order'] = $_GET['sort_order'];
				}

				$prev_url = esc_url( add_query_arg( $prev_args, $base_page_url ) );
				$pagination_html .= "<a href=\"{$prev_url}\" style=\"{$button_style}\" {$hover_effect}>‹</a>";
			}

			// ページ番号ボタン（省略表示対応）
			$start_page = max( 1, $current_page - 2 );
			$end_page = min( $total_pages, $current_page + 2 );

			// 最初のページを表示（データが0件でも1ページ目は表示）
			if ( $start_page > 1 && $total_pages > 1 ) {
				$first_args = array(
					'tab_name' => $name,
					'page_start' => 0,
					'page_stage' => 2,
					'flg' => $flg,
				);
				// 現在のソート順を維持
				if ( isset( $_GET['sort_by'] ) ) {
					$first_args['sort_by'] = $_GET['sort_by'];
				}
				if ( isset( $_GET['sort_order'] ) ) {
					$first_args['sort_order'] = $_GET['sort_order'];
				}

				$first_url = esc_url( add_query_arg( $first_args, $base_page_url ) );
				$pagination_html .= "<a href=\"{$first_url}\" style=\"{$button_style}\" {$hover_effect}>1</a>";

				if ( $start_page > 2 ) {
					$pagination_html .= "<span style=\"{$button_style} background: transparent; border: none; cursor: default;\">...</span>";
				}
			}

			// 中央のページ番号
			for ( $i = $start_page; $i <= $end_page; $i++ ) {
				$page_args = array(
					'tab_name' => $name,
					'page_start' => ( $i - 1 ) * $query_limit,
					'page_stage' => 2,
					'flg' => $flg,
				);
				// 現在のソート順を維持
				if ( isset( $_GET['sort_by'] ) ) {
					$page_args['sort_by'] = $_GET['sort_by'];
				}
				if ( isset( $_GET['sort_order'] ) ) {
					$page_args['sort_order'] = $_GET['sort_order'];
				}

				$page_url = esc_url( add_query_arg( $page_args, $base_page_url ) );

				if ( $i == $current_page ) {
					$pagination_html .= "<span style=\"{$button_style} {$current_style}\">{$i}</span>";
				} else {
					$pagination_html .= "<a href=\"{$page_url}\" style=\"{$button_style}\" {$hover_effect}>{$i}</a>";
				}
			}

			// 最後のページを表示
			if ( $end_page < $total_pages && $total_pages > 1 ) {
				if ( $end_page < $total_pages - 1 ) {
					$pagination_html .= "<span style=\"{$button_style} background: transparent; border: none; cursor: default;\">...</span>";
				}

				$last_args = array(
					'tab_name' => $name,
					'page_start' => ( $total_pages - 1 ) * $query_limit,
					'page_stage' => 2,
					'flg' => $flg,
				);
				// 現在のソート順を維持
				if ( isset( $_GET['sort_by'] ) ) {
					$last_args['sort_by'] = $_GET['sort_by'];
				}
				if ( isset( $_GET['sort_order'] ) ) {
					$last_args['sort_order'] = $_GET['sort_order'];
				}

				$last_url = esc_url( add_query_arg( $last_args, $base_page_url ) );
				$pagination_html .= "<a href=\"{$last_url}\" style=\"{$button_style}\" {$hover_effect}>{$total_pages}</a>";
			}

			// 次のページボタン
			if ( $current_page < $total_pages && $total_pages > 1 ) {
				$next_args = array(
					'tab_name' => $name,
					'page_start' => $current_page * $query_limit,
					'page_stage' => 2,
					'flg' => $flg,
				);
				// 現在のソート順を維持
				if ( isset( $_GET['sort_by'] ) ) {
					$next_args['sort_by'] = $_GET['sort_by'];
				}
				if ( isset( $_GET['sort_order'] ) ) {
					$next_args['sort_order'] = $_GET['sort_order'];
				}

				$next_url = esc_url( add_query_arg( $next_args, $base_page_url ) );
				$pagination_html .= "<a href=\"{$next_url}\" style=\"{$button_style}\" {$hover_effect}>›</a>";
			}

			$pagination_html .= '</div>';
			$pagination_html .= '</div>';

			return $pagination_html;
		}

		/**
		 * 協力会社情報のプレビュー用HTMLを生成するメソッド
		 *
		 * @param array $supplier_data 協力会社データ
		 * @return string 協力会社情報のプレビューHTML
		 */
		private function generateSupplierPreviewHTML( $supplier_data ) {
			$company_name = $supplier_data['company_name'] ?? '';
			$name = $supplier_data['name'] ?? '';
			$email = $supplier_data['email'] ?? '';
			$url = $supplier_data['url'] ?? '';
			$representative_name = $supplier_data['representative_name'] ?? '';
			$phone = $supplier_data['phone'] ?? '';
			$postal_code = $supplier_data['postal_code'] ?? '';
			$prefecture = $supplier_data['prefecture'] ?? '';
			$city = $supplier_data['city'] ?? '';
			$address = $supplier_data['address'] ?? '';
			$building = $supplier_data['building'] ?? '';
			$closing_day = $supplier_data['closing_day'] ?? '';
			$payment_month = $supplier_data['payment_month'] ?? '';
			$payment_day = $supplier_data['payment_day'] ?? '';
			$payment_method = $supplier_data['payment_method'] ?? '';
			$tax_category = $supplier_data['tax_category'] ?? '';
			$qualified_invoice_number = $supplier_data['qualified_invoice_number'] ?? '';
			$category = $supplier_data['category'] ?? '';
			$frequency = $supplier_data['frequency'] ?? '';
			$memo = $supplier_data['memo'] ?? '';

			// 住所の組み立て
			$full_address = '';
			if ( ! empty( $prefecture ) ) {
				$full_address .= $prefecture;
			}
			if ( ! empty( $city ) ) {
				$full_address .= $city;
			}
			if ( ! empty( $address ) ) {
				$full_address .= $address;
			}
			if ( ! empty( $building ) ) {
				$full_address .= $building;
			}

			return '
        <div style="font-family: Arial, sans-serif; padding: 20px; max-width: 900px; margin: 0 auto;">
            <div style="text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 20px;">
                <h1 style="color: #333; margin: 0; font-size: 24px;">協力会社情報</h1>
            </div>
            
            <table style="border-collapse: collapse; width: 100%; margin-bottom: 20px;">
                <tr>
                    <td style="border: 1px solid #ddd; padding: 12px; font-weight: bold; background-color: #f8f9fa; width: 25%;">会社名</td>
                    <td style="border: 1px solid #ddd; padding: 12px;">' . esc_html( $company_name ) . '</td>
                </tr>
                <tr>
                    <td style="border: 1px solid #ddd; padding: 12px; font-weight: bold; background-color: #f8f9fa;">担当者名</td>
                    <td style="border: 1px solid #ddd; padding: 12px;">' . esc_html( $name ) . '</td>
                </tr>
                <tr>
                    <td style="border: 1px solid #ddd; padding: 12px; font-weight: bold; background-color: #f8f9fa;">メールアドレス</td>
                    <td style="border: 1px solid #ddd; padding: 12px;">' . esc_html( $email ) . '</td>
                </tr>
                <tr>
                    <td style="border: 1px solid #ddd; padding: 12px; font-weight: bold; background-color: #f8f9fa;">URL</td>
                    <td style="border: 1px solid #ddd; padding: 12px;">' . esc_html( $url ) . '</td>
                </tr>
                <tr>
                    <td style="border: 1px solid #ddd; padding: 12px; font-weight: bold; background-color: #f8f9fa;">代表者名</td>
                    <td style="border: 1px solid #ddd; padding: 12px;">' . esc_html( $representative_name ) . '</td>
                </tr>
                <tr>
                    <td style="border: 1px solid #ddd; padding: 12px; font-weight: bold; background-color: #f8f9fa;">電話番号</td>
                    <td style="border: 1px solid #ddd; padding: 12px;">' . esc_html( $phone ) . '</td>
                </tr>
                <tr>
                    <td style="border: 1px solid #ddd; padding: 12px; font-weight: bold; background-color: #f8f9fa;">郵便番号</td>
                    <td style="border: 1px solid #ddd; padding: 12px;">' . esc_html( $postal_code ) . '</td>
                </tr>
                <tr>
                    <td style="border: 1px solid #ddd; padding: 12px; font-weight: bold; background-color: #f8f9fa;">住所</td>
                    <td style="border: 1px solid #ddd; padding: 12px;">' . esc_html( $full_address ) . '</td>
                </tr>
                <tr>
                    <td style="border: 1px solid #ddd; padding: 12px; font-weight: bold; background-color: #f8f9fa;">締め日</td>
                    <td style="border: 1px solid #ddd; padding: 12px;">' . esc_html( $closing_day ) . '</td>
                </tr>
                <tr>
                    <td style="border: 1px solid #ddd; padding: 12px; font-weight: bold; background-color: #f8f9fa;">支払月</td>
                    <td style="border: 1px solid #ddd; padding: 12px;">' . esc_html( $payment_month ) . '</td>
                </tr>
                <tr>
                    <td style="border: 1px solid #ddd; padding: 12px; font-weight: bold; background-color: #f8f9fa;">支払日</td>
                    <td style="border: 1px solid #ddd; padding: 12px;">' . esc_html( $payment_day ) . '</td>
                </tr>
                <tr>
                    <td style="border: 1px solid #ddd; padding: 12px; font-weight: bold; background-color: #f8f9fa;">支払方法</td>
                    <td style="border: 1px solid #ddd; padding: 12px;">' . esc_html( $payment_method ) . '</td>
                </tr>
                <tr>
                    <td style="border: 1px solid #ddd; padding: 12px; font-weight: bold; background-color: #f8f9fa;">税区分</td>
                    <td style="border: 1px solid #ddd; padding: 12px;">' . esc_html( $tax_category ) . '</td>
                </tr>
                <tr>
                    <td style="border: 1px solid #ddd; padding: 12px; font-weight: bold; background-color: #f8f9fa;">適格請求書番号</td>
                    <td style="border: 1px solid #ddd; padding: 12px;">' . esc_html( $qualified_invoice_number ) . '</td>
                </tr>
                <tr>
                    <td style="border: 1px solid #ddd; padding: 12px; font-weight: bold; background-color: #f8f9fa;">カテゴリー</td>
                    <td style="border: 1px solid #ddd; padding: 12px;">' . esc_html( $category ) . '</td>
                </tr>
                <tr>
                    <td style="border: 1px solid #ddd; padding: 12px; font-weight: bold; background-color: #f8f9fa;">頻度</td>
                    <td style="border: 1px solid #ddd; padding: 12px;">' . esc_html( $frequency ) . '</td>
                </tr>
                <tr>
                    <td style="border: 1px solid #ddd; padding: 12px; font-weight: bold; background-color: #f8f9fa;">メモ</td>
                    <td style="border: 1px solid #ddd; padding: 12px; white-space: pre-wrap;">' . esc_html( $memo ) . '</td>
                </tr>
            </table>
            
            <div style="text-align: center; margin-top: 30px; color: #666; font-size: 12px;">
                <p>印刷日時: ' . date( 'Y年m月d日 H:i' ) . '</p>
            </div>
        </div>';
		}
	}
} // class_exists
