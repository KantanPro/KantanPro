<?php
/**
 * Service class for KTPWP plugin
 *
 * Handles service data management including table creation,
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

        require_once 'class-ktpwp-image-processor.php';
require_once 'class-ktpwp-service-ui.php';
require_once 'class-ktpwp-service-db.php';

if ( ! class_exists( 'KTPWP_Service_Class' ) ) {

	/**
	 * Service class for managing service data
	 *
	 * @since 1.0.0
	 */
	class KTPWP_Service_Class {

		/**
		 * UI helper instance
		 *
		 * @var KTPWP_Service_UI
		 */
		private $ui_helper;

		/**
		 * DB helper instance
		 *
		 * @var KTPWP_Service_DB
		 */
		private $db_helper;

		/**
		 * Constructor
		 *
		 * @since 1.0.0
		 * @param string $tab_name The tab name
		 */
		public function __construct( $tab_name = '' ) {
			// Initialize helper classes using singleton pattern
			$this->ui_helper = KTPWP_Service_UI::get_instance();
			$this->db_helper = KTPWP_Service_DB::get_instance();
		}

		// -----------------------------
		// Table Operations
		// -----------------------------

		/**
		 * Set cookie for UI session management (delegated to UI helper)
		 *
		 * @since 1.0.0
		 * @param string $name The name parameter for cookie
		 * @return int The query ID
		 */
		public function set_cookie( $name ) {
			return $this->ui_helper->set_cookie( $name );
		}

		/**
		 * Create service table (delegated to DB helper)
		 *
		 * @since 1.0.0
		 * @param string $tab_name The table name suffix
		 * @return bool True on success, false on failure
		 */
		public function create_table( $tab_name ) {
			return $this->db_helper->create_table( $tab_name );
		}

		// -----------------------------
		// Table Operations (CRUD)
		// -----------------------------

		/**
		 * Update table with POST data (delegated to DB helper)
		 *
		 * @since 1.0.0
		 * @param string $tab_name The table name suffix
		 * @return void
		 */
		public function update_table( $tab_name ) {
			return $this->db_helper->update_table( $tab_name );
		}


		// -----------------------------
		// テーブルの表示
		// -----------------------------

		function View_Table( $name ) {

			global $wpdb;

			// Ensure table exists
			$table_name = $wpdb->prefix . 'ktp_' . sanitize_key( $name );
			$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );

			if ( ! $table_exists ) {
				// Create table if it doesn't exist
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'KTPWP Service: Table does not exist, creating: ' . $table_name );
				}
				$this->create_table( $name );
			}

			// Handle POST requests by calling update_table
			if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
				// Debug logging
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'KTPWP Service: POST request detected in View_Table' );
					error_log( 'KTPWP Service: Full POST data: ' . print_r( $_POST, true ) );
					error_log( 'KTPWP Service: Full GET data: ' . print_r( $_GET, true ) );
					error_log( 'KTPWP Service: Request URI: ' . $_SERVER['REQUEST_URI'] );
				}

				$query_post = isset( $_POST['query_post'] ) ? sanitize_text_field( $_POST['query_post'] ) : '';
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'KTPWP Service: Extracted query_post: "' . $query_post . '"' );
				}

				// 追加・検索モードは GET へリダイレクト（タブ状態復元で上書きされないよう PRG）
				if ( in_array( $query_post, array( 'istmode', 'srcmode' ), true ) ) {
					$redirect_url = add_query_arg(
						array(
							'tab_name'   => $name,
							'query_post' => $query_post,
						),
						KTPWP_Main::get_current_page_base_url()
					);
					wp_safe_redirect( $redirect_url );
					exit;
				}

				$this->update_table( $name );
			}

			// GETパラメータからのメッセージをフローティングアラート（JS通知）で表示（他タブと統一・安全な出力）
			if ( isset( $_GET['message'] ) ) {
				?>
            <script>
            document.addEventListener("DOMContentLoaded", function() {
                var messageType = "<?php echo esc_js( $_GET['message'] ); ?>";
                switch (messageType) {
                    case "updated":
                        if (typeof showSuccessNotification === 'function') showSuccessNotification("<?php echo esc_js( __( '更新しました。', 'ktpwp' ) ); ?>");
                        break;
                    case "added":
                        if (typeof showSuccessNotification === 'function') showSuccessNotification("<?php echo esc_js( __( '新しいサービスを追加しました。', 'ktpwp' ) ); ?>");
                        break;
                    case "deleted":
                        if (typeof showSuccessNotification === 'function') showSuccessNotification("<?php echo esc_js( __( '削除しました。', 'ktpwp' ) ); ?>");
                        break;
                    case "duplicated":
                        if (typeof showSuccessNotification === 'function') showSuccessNotification("<?php echo esc_js( __( '複製しました。', 'ktpwp' ) ); ?>");
                        break;
                    case "search_cancelled":
                        if (typeof showInfoNotification === 'function') showInfoNotification("<?php echo esc_js( __( '検索をキャンセルしました。', 'ktpwp' ) ); ?>");
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
            </script>
				<?php
			}

			// セッション変数をチェックしてメッセージを表示 (これは前の修正の名残なので、GETパラメータ方式に統一した場合は削除またはコメントアウトを検討)
			// if (isset($_SESSION['ktp_service_message']) && isset($_SESSION['ktp_service_message_type'])) {
			// $message_text = $_SESSION['ktp_service_message'];
			// $message_type = $_SESSION['ktp_service_message_type'];
			// unset($_SESSION['ktp_service_message']); // メッセージを表示したらセッション変数を削除
			// unset($_SESSION['ktp_service_message_type']);
			//
			// $notice_class = 'notice-success'; // デフォルトは成功メッセージ
			// if ($message_type === 'error') {
			// $notice_class = 'notice-error';
			// } elseif ($message_type === 'updated') {
			// $notice_class = 'notice-success is-dismissible'; // 更新成功のクラス
			// }
			//
			// echo '<div class="notice ' . esc_attr($notice_class) . '"><p>' . esc_html($message_text) . '</p></div>';
			// }

			// 検索モードの確認
			$search_mode = false;
			$search_message = '';
			if ( ! session_id() ) {
				ktpwp_safe_session_start();
			}
			// タブクリックで遷移した場合（GET で query_post が無い）は検索モードを解除
			if ( $_SERVER['REQUEST_METHOD'] === 'GET' && ! isset( $_GET['query_post'] ) ) {
				unset( $_SESSION['ktp_service_search_mode'] );
				unset( $_SESSION['ktp_service_search_message'] );
			}
			if ( isset( $_SESSION['ktp_service_search_mode'] ) && $_SESSION['ktp_service_search_mode'] ) {
				$search_mode = true;
				$search_message = isset( $_SESSION['ktp_service_search_message'] ) ? $_SESSION['ktp_service_search_message'] : '';
			}
			// 本番等でセッションがリダイレクト後に引き継がれない場合の対策: GET の query_post=srcmode で検索フォームを表示
			if ( ! $search_mode && $_SERVER['REQUEST_METHOD'] === 'GET' && isset( $_GET['query_post'] ) && $_GET['query_post'] === 'srcmode' ) {
				$search_mode = true;
				if ( isset( $_GET['no_results'] ) && $_GET['no_results'] === '1' ) {
					$search_message = esc_html__( '該当するサービスが見つかりませんでした。条件を変更して再検索してください。', 'ktpwp' );
				} elseif ( $search_message === '' ) {
					$search_message = esc_html__( '検索モードです。条件を入力して検索してください。', 'ktpwp' );
				}
			}

			// JS通知は他タブと統一のため廃止（noticeのみ）
			$message = '';

			// -----------------------------
			// リスト表示
			// -----------------------------

			// テーブル名
			$table_name = $wpdb->prefix . 'ktp_' . $name;        // -----------------------------
			// ページネーションリンク
			// -----------------------------
			// ソート順の取得（デフォルトはIDの降順 - 新しい順）
			$sort_by = 'id';
			$sort_order = class_exists( 'KTPWP_List_Table' ) ? KTPWP_List_Table::default_sort_order() : 'DESC';

			if ( isset( $_GET['sort_by'] ) ) {
				$sort_by = sanitize_text_field( $_GET['sort_by'] );
				// 安全なカラム名のみ許可（SQLインジェクション対策）
				$allowed_columns = array( 'id', 'service_name', 'price', 'unit', 'frequency', 'time', 'category', 'tax_rate', 'is_public', 'contract_billing_cycle' );
				if ( ! in_array( $sort_by, $allowed_columns ) ) {
					$sort_by = 'id'; // 不正な値の場合はデフォルトに戻す
				}
			}

			if ( isset( $_GET['sort_order'] ) ) {
				$sort_order = class_exists( 'KTPWP_List_Table' )
					? KTPWP_List_Table::sanitize_sort_order( sanitize_text_field( $_GET['sort_order'] ) )
					: 'DESC';
			}

			// リスト表示前に頻度を加算（一覧に反映させる）
			if ( isset( $_GET['data_id'] ) && $_GET['data_id'] !== '' && ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX ) && ( ! function_exists( 'ktpwp_should_skip_frequency_on_view' ) || ! ktpwp_should_skip_frequency_on_view() ) ) {
				$view_record_id = filter_input( INPUT_GET, 'data_id', FILTER_SANITIZE_NUMBER_INT );
				if ( $view_record_id > 0 && function_exists( 'ktpwp_increment_record_frequency_on_view' ) ) {
					ktpwp_increment_record_frequency_on_view( 'service', (int) $view_record_id );
				}
			}

			// 現在のページのURLを生成（動的パーマリンク取得）
			$base_page_url = KTPWP_Main::get_current_page_base_url();

			// 検索結果が複数ある場合：リダイレクト後のGETでダイアログにリストを表示（協力会社タブと同じ方式）
			// HTMLは hidden div に置きスクリプトで読み取る方式で、JSON埋め込みによる構文エラーを防ぐ
			$service_search_results_script = '';
			if ( isset( $_GET['multiple_results'] ) && $_GET['multiple_results'] === '1' ) {
				$search_service_name = isset( $_GET['search_service_name'] ) ? sanitize_text_field( wp_unslash( $_GET['search_service_name'] ) ) : '';
				$search_category = isset( $_GET['search_category'] ) ? sanitize_text_field( wp_unslash( $_GET['search_category'] ) ) : '';
				if ( $search_service_name !== '' || $search_category !== '' ) {
					$where_conditions = array();
					$where_values = array();
					if ( $search_service_name !== '' ) {
						$where_conditions[] = '(COALESCE(service_name,\'\') LIKE %s OR COALESCE(search_field,\'\') LIKE %s)';
						$name_like = '%' . $wpdb->esc_like( $search_service_name ) . '%';
						$where_values[] = $name_like;
						$where_values[] = $name_like;
					}
					if ( $search_category !== '' ) {
						$where_conditions[] = '(COALESCE(category,\'\') LIKE %s OR COALESCE(search_field,\'\') LIKE %s)';
						$cat_like = '%' . $wpdb->esc_like( $search_category ) . '%';
						$where_values[] = $cat_like;
						$where_values[] = $cat_like;
					}
					if ( ! empty( $where_conditions ) ) {
						$where_clause = ' WHERE ' . implode( ' AND ', $where_conditions );
						$multi_query = "SELECT * FROM {$table_name}" . $where_clause . ' ORDER BY id DESC';
						$multi_results = $wpdb->get_results( $wpdb->prepare( $multi_query, $where_values ) );
						if ( ! empty( $multi_results ) ) {
							$multi_results_id = 'ktp-service-multi-results-' . wp_rand( 10000, 99999 );
							$search_results_html = "<div class='data_contents'><div class='search_list_box'><div class='data_list_title'>■ " . esc_html__( '検索結果が複数あります！', 'ktpwp' ) . "</div><ul>";
							foreach ( $multi_results as $row ) {
								$id = esc_html( (string) $row->id );
								$service_name = esc_html( isset( $row->service_name ) ? $row->service_name : '' );
								$category = esc_html( isset( $row->category ) ? $row->category : '' );
								$price = isset( $row->price ) ? esc_html( (string) $row->price ) : '';
								$link_url = esc_url(
									add_query_arg(
										array(
											'tab_name' => $name,
											'data_id' => (int) $row->id,
											'query_post' => 'update',
										),
										$base_page_url
									)
								);
								$search_results_html .= "<li style='text-align:left;'><a href='" . $link_url . "' style='text-align:left;'>ID：" . $id . " サービス名：" . $service_name . ( $category !== '' ? " カテゴリー：" . $category : '' ) . ( $price !== '' ? " 価格：" . $price : '' ) . "</a></li>";
							}
							$search_results_html .= '</ul></div></div>';
							// 閉じる＝検索モードへ。現在のリクエストURLからダイアログ用パラメータを除き検索モード用のみ付与
							$close_redirect_base = home_url( wp_unslash( isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '/' ) );
							$close_redirect_base = remove_query_arg( array( 'multiple_results', 'search_service_name', 'search_category', 'message' ), $close_redirect_base );
							$close_redirect_url = esc_url(
								add_query_arg(
									array(
										'tab_name' => $name,
										'query_post' => 'srcmode',
									),
									$close_redirect_base
								)
							);
							$service_search_results_script = '<div id="' . esc_attr( $multi_results_id ) . '" style="display:none;">' . $search_results_html . '</div>' . "\n" . '<script>
(function() {
	var run = function() {
		var el = document.getElementById("' . esc_js( $multi_results_id ) . '");
		if (!el) return;
		var searchResultsHtml = el.innerHTML;
		var popup = document.createElement("div");
		popup.innerHTML = searchResultsHtml;
		popup.style.cssText = "position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);background:#fff;padding:20px;z-index:10001;width:80%;max-width:600px;border:1px solid #ccc;border-radius:5px;box-shadow:0 4px 6px rgba(0,0,0,0.1)";
		document.body.appendChild(popup);
		var closeBtn = document.createElement("button");
		closeBtn.textContent = "' . esc_js( __( '閉じる', 'ktpwp' ) ) . '";
		closeBtn.style.cssText = "font-size:0.8em;color:#000;display:block;margin:10px auto 0;padding:10px;background:#cdcccc;border-radius:5px;border-color:#999;cursor:pointer";
		closeBtn.onclick = function() { document.body.removeChild(popup); location.href = "' . esc_js( $close_redirect_url ) . '"; };
		popup.appendChild(closeBtn);
	};
	if (document.readyState === "loading") {
		document.addEventListener("DOMContentLoaded", run);
	} else {
		run();
	}
})();
</script>';
						}
					}
				}
			}

			// 表示範囲（1ページあたりの表示件数）
			// 一般設定から表示件数を取得（設定クラスが利用可能な場合）
			if ( class_exists( 'KTPWP_Settings' ) ) {
				$query_limit = KTPWP_Settings::get_work_list_range();
			} else {
				$query_limit = 20; // フォールバック値
			}
			if ( ! is_numeric( $query_limit ) || $query_limit <= 0 ) {
				$query_limit = 20; // 不正な値の場合はデフォルト値に
			}

			// リスト表示部分の開始
			// 顧客・協力会社タブと同じラッパー（.data_contents は display:flex のため二段レイアウトと相性が悪い）
			$list_title = esc_html__( '■ サービスリスト', 'ktpwp' );
			$results_h = <<<END
            <div class="ktp_data_contents">
            <div class="ktp_data_list_box">
            <div class="data_list_title">{$list_title}</div>
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
			
			$query_range = $page_start . ',' . $query_limit;

			$list_search_where = '';
			$list_search_args  = array();
			if ( class_exists( 'KTPWP_Tab_Search_UI' ) ) {
				$list_search_keyword = KTPWP_Tab_Search_UI::get_instance()->get_keyword();
				if ( $list_search_keyword !== '' ) {
					list( $list_search_where, $list_search_args ) = KTPWP_Tab_Search_UI::get_instance()->master_list_search_clause( $table_name, $list_search_keyword, 'service' );
				}
			}

			// 全データ数を取得
			$total_query = "SELECT COUNT(*) FROM {$table_name} WHERE 1=1{$list_search_where}";
			if ( $list_search_args !== array() ) {
				$total_rows = $wpdb->get_var( $wpdb->prepare( $total_query, $list_search_args ) );
			} else {
				$total_rows = $wpdb->get_var( $total_query );
			}

			// ゼロ除算防止のための安全対策
			if ( $query_limit <= 0 ) {
				if ( class_exists( 'KTPWP_Settings' ) ) {
					$query_limit = KTPWP_Settings::get_work_list_range();
				} else {
					$query_limit = 20; // フォールバック値
				}
			}

			$total_pages = ceil( $total_rows / $query_limit );

			// 現在のページ番号を計算
			$current_page = floor( $page_start / $query_limit ) + 1;

			// データを取得（ソート順を適用）
			$query = $wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE 1=1{$list_search_where} ORDER BY {$sort_by} {$sort_order} LIMIT %d, %d",
				array_merge( $list_search_args, array( $page_start, $query_limit ) )
			);
			$post_row = $wpdb->get_results( $query );
			$results = array(); // ← 追加：未定義エラー防止
			$list_header = '';
			$list_footer = '';
			$hide_tax = true;
			if ( $post_row ) {
				$list_header = $this->render_service_list_table_open( $hide_tax, $base_page_url, $sort_by, $sort_order );
				foreach ( $post_row as $row ) {
					$id = esc_html( $row->id );
					$service_name_raw = isset( $row->service_name ) ? (string) $row->service_name : '';
					$service_name = esc_html( $service_name_raw );
					$price = isset( $row->price ) ? floatval( $row->price ) : 0;
					$unit = isset( $row->unit ) ? esc_html( $row->unit ) : '';
					$category = esc_html( $row->category );
					$frequency = esc_html( $row->frequency );
					  // リスト項目
					$cookie_name = 'ktp_' . $name . '_id';
					// $base_page_url を add_query_arg の第2引数として使用
					$item_link_args = array(
						'tab_name' => $name,
						'data_id' => $id,
						'page_start' => $page_start,
						'page_stage' => $page_stage,
					);
					// 他のソートやフィルタ関連のGETパラメータを維持しつつ、'message'は含めない
					foreach ( $_GET as $getKey => $getValue ) {
						if ( ! in_array( $getKey, array( 'tab_name', 'data_id', 'page_start', 'page_stage', 'message', '_ktp_service_nonce', 'query_post', 'send_post' ) ) ) {
							$item_link_args[ $getKey ] = $getValue;
						}
					}
                    $formatted_price = number_format( $price, 0, '.', ',' );
					$is_public = isset( $row->is_public ) ? (int) $row->is_public : 0;
					$row_stock = isset( $row->stock ) ? max( 0, absint( $row->stock ) ) : 1;
					$contract_cycle_value = class_exists( 'KTPWP_Contract_Billing_Cycle' ) && isset( $row->contract_billing_cycle )
						? KTPWP_Contract_Billing_Cycle::sanitize( $row->contract_billing_cycle )
						: ( class_exists( 'KTPWP_Contract_Billing_Cycle' ) ? KTPWP_Contract_Billing_Cycle::NONE : 'none' );
					$contract_cycle_cell = class_exists( 'KTPWP_Contract_Billing_Cycle' )
						? '<td class="col-contract">' . KTPWP_Contract_Billing_Cycle::render_badge( $contract_cycle_value ) . '</td>'
						: '';
					$thumb_url   = $this->db_helper->resolve_image_url(
						(int) $row->id,
						isset( $row->image_url ) ? (string) $row->image_url : ''
					);
					$default_thumb_url = $this->db_helper->get_default_image_url();
					$row_url = esc_url( add_query_arg( $item_link_args, $base_page_url ) );
					$price_unit_cell = '<td class="col-price-unit">' . $this->render_service_price_unit_display( $price, $unit ) . '</td>';
					$results[] = '<tr class="ktp-service-list-data-row" data-href="' . $row_url . '" onclick="window.location.href=this.dataset.href">' .
					'<td class="col-id">' . $id . '</td>' .
					'<td class="col-image"><span class="ktp-service-list-thumb-wrap"><img src="' . esc_url( $thumb_url ) . '" alt="' . esc_attr( $service_name_raw ) . '" class="ktp-service-list-thumb" loading="lazy" decoding="async" onerror="this.src=\'' . esc_url( $default_thumb_url ) . '\'" /></span></td>' .
					'<td class="col-name">' . $service_name . '</td>' .
					'<td class="col-public">' . $this->render_service_public_badge( $is_public, $row_stock, (int) $row->id, $contract_cycle_value ) . '</td>' .
					$contract_cycle_cell .
					$price_unit_cell .
					'<td class="col-category">' . $category . '</td>' .
					'<td class="col-frequency">' . $frequency . '</td>' .
					'</tr><!-- DEBUG: price=' . $price . ' formatted=' . $formatted_price . ' -->';
				}
				$list_footer = class_exists( 'KTPWP_List_Table' ) ? KTPWP_List_Table::close() : '</tbody></table></div>';
				$query_max_num = $wpdb->num_rows;
			} else {
				// 新しい0データ案内メッセージ（統一デザイン・ガイダンス）
				$results[] = '<div class="ktp_data_list_item" style="padding: 15px 20px; background: linear-gradient(135deg, #e3f2fd 0%, #fce4ec 100%); border-radius: 8px; margin: 18px 0; color: #333; font-weight: 600; box-shadow: 0 3px 12px rgba(0,0,0,0.07); display: flex; align-items: center; font-size: 15px; gap: 10px;">'
                . '<span class="material-symbols-outlined" aria-label="データ作成">add_circle</span>'
                . '<span style="font-size: 1em; font-weight: 600;">[＋]ボタンを押してデーターを作成してください</span>'
                . '<span style="margin-left: 18px; font-size: 13px; color: #888;">データがまだ登録されていません</span>'
                . '</div>';
			}

			// 統一されたページネーションデザインを使用
			$results_f = $this->render_pagination( $current_page, $total_pages, $query_limit, $name, $flg, $base_page_url, $total_rows );

			$selected_service_id   = $this->resolve_selected_service_id( $name, $table_name );
			$related_list_html     = '';
			if ( $selected_service_id > 0 && class_exists( 'KTPWP_Service_Related_Orders' ) ) {
				$selected_service_name = (string) $wpdb->get_var(
					$wpdb->prepare(
						"SELECT service_name FROM {$table_name} WHERE id = %d",
						$selected_service_id
					)
				);
				$related_list_html = KTPWP_Service_Related_Orders::render_list_section(
					$selected_service_id,
					$selected_service_name,
					$base_page_url
				);
			}

			$data_list = $results_h . $list_header . implode( $results ) . $list_footer . $results_f . $related_list_html . '</div>'; // ktp_data_list_box を閉じる

			// -----------------------------
			// 詳細表示(GET)
			// -----------------------------

			// アクションを取得（POSTパラメータを優先、次にGETパラメータ、デフォルトは'update'）
			$action = isset( $_POST['query_post'] ) ? sanitize_text_field( $_POST['query_post'] ) : ( isset( $_GET['query_post'] ) ? sanitize_text_field( $_GET['query_post'] ) : 'update' );

			// 安全性確保: GETリクエストの場合は危険なアクションを実行しない
			if ( $_SERVER['REQUEST_METHOD'] === 'GET' && in_array( $action, array( 'duplicate', 'delete', 'insert', 'search', 'search_execute', 'upload_image' ) ) ) {
				$action = 'update';
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				}
			}

			// 初期化
			$data_id = '';
			$time = '';
			$service_name = '';
			$price = 0;
			$tax_rate = 10.00; // デフォルト税率
			$unit = '';
			$memo = '';
			$category = '';
			$image_url = '';
			$is_public = 0;
			$contract_billing_cycle = class_exists( 'KTPWP_Contract_Billing_Cycle' ) ? KTPWP_Contract_Billing_Cycle::NONE : 'none';
			$stock = 1;
			$public_quantity_fixed = 0;
			$public_html = '';
			$query_id = 0;

			// 追加モード以外の場合のみデータを取得
			if ( $action !== 'istmode' ) {
				// 現在表示中の詳細
				$cookie_name = 'ktp_' . $name . '_id';

				// デバッグログ：初期状態の確認

				if ( isset( $_GET['data_id'] ) && $_GET['data_id'] !== '' ) {
					$query_id = filter_input( INPUT_GET, 'data_id', FILTER_SANITIZE_NUMBER_INT );
					// GETパラメータで取得したIDをクッキーに保存（ショートコード表示後はヘッダー済みのため送れない場合あり）
					if ( ! headers_sent() ) {
						setcookie( $cookie_name, (string) $query_id, time() + ( 86400 * 30 ), '/' );
					}
				} elseif ( isset( $_COOKIE[ $cookie_name ] ) && $_COOKIE[ $cookie_name ] !== '' ) {
					$cookie_id = filter_input( INPUT_COOKIE, $cookie_name, FILTER_SANITIZE_NUMBER_INT );
					// クッキーIDがDBに存在するかチェック
					$exists = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table_name} WHERE id = %d", $cookie_id ) );
					if ( $exists ) {
						$query_id = $cookie_id;
					} else {
						// 存在しなければ最新ID（降順トップ）
						$last_id_row = $wpdb->get_row( "SELECT id FROM {$table_name} ORDER BY id DESC LIMIT 1" );
						$query_id = $last_id_row ? $last_id_row->id : 1;
						// 最新IDをクッキーに保存
						if ( ! headers_sent() ) {
							setcookie( $cookie_name, (string) $query_id, time() + ( 86400 * 30 ), '/' );
						}
					}
				} else {
					// data_id未指定時は必ずID最新のサービスを表示（降順トップ）
					$last_id_row = $wpdb->get_row( "SELECT id FROM {$table_name} ORDER BY id DESC LIMIT 1" );
					$query_id = $last_id_row ? $last_id_row->id : 1;
					// 最新IDをクッキーに保存
					if ( ! headers_sent() ) {
						setcookie( $cookie_name, (string) $query_id, time() + ( 86400 * 30 ), '/' );
					}
				}

				// データを取得し変数に格納
				$query = $wpdb->prepare( "SELECT * FROM {$table_name} WHERE id = %d", $query_id );
				$post_row = $wpdb->get_results( $query );
				if ( ! $post_row || count( $post_row ) === 0 ) {
					// 存在しないIDの場合は最新IDを取得して再表示
					$last_id_row = $wpdb->get_row( "SELECT id FROM {$table_name} ORDER BY id DESC LIMIT 1" );
					if ( $last_id_row && isset( $last_id_row->id ) ) {
						$query_id = $last_id_row->id;
						$query = $wpdb->prepare( "SELECT * FROM {$table_name} WHERE id = %d", $query_id );
						$post_row = $wpdb->get_results( $query );
					}
					// それでもデータがなければ「データがありません」は後で処理
				}
				foreach ( $post_row as $row ) {
					$data_id = esc_html( $row->id );
					$time = esc_html( $row->time );
					$service_name = esc_html( $row->service_name );
					$price = isset( $row->price ) ? floatval( $row->price ) : 0;
					$tax_rate = isset( $row->tax_rate ) && $row->tax_rate !== null ? floatval( $row->tax_rate ) : '';
					$unit = isset( $row->unit ) ? esc_html( $row->unit ) : '';
					$memo = esc_html( $row->memo );
					$category = esc_html( $row->category );
					$image_url = esc_html( $row->image_url );
					$is_public = isset( $row->is_public ) ? (int) $row->is_public : 0;
					$contract_billing_cycle = class_exists( 'KTPWP_Contract_Billing_Cycle' ) && isset( $row->contract_billing_cycle )
						? KTPWP_Contract_Billing_Cycle::sanitize( $row->contract_billing_cycle )
						: ( class_exists( 'KTPWP_Contract_Billing_Cycle' ) ? KTPWP_Contract_Billing_Cycle::NONE : 'none' );
					$stock = isset( $row->stock ) ? max( 0, absint( $row->stock ) ) : 1;
					$public_quantity_fixed = class_exists( 'KTPWP_Service_DB' )
						? KTPWP_Service_DB::sanitize_public_quantity_fixed( $row->public_quantity_fixed ?? null )
						: 0;
					$public_html = class_exists( 'KTPWP_Service_DB' )
						? (string) ( $row->public_html ?? '' )
						: '';
				}
			}
			  			// 表示するフォーム要素を定義
			$fields = array(
				// 'ID' => ['type' => 'text', 'name' => 'data_id', 'readonly' => true],
				esc_html__( 'サービス名', 'ktpwp' ) => array(
					'type' => 'text',
					'name' => 'service_name',
					'required' => true,
					'placeholder' => esc_attr__( '必須 サービス名', 'ktpwp' ),
				),
				esc_html__( '価格', 'ktpwp' ) => array(
					'type' => 'number',
					'name' => 'price',
					'placeholder' => esc_attr__( '価格', 'ktpwp' ),
					'step' => '0.01',
					'min' => '0',
				),
				esc_html__( '単位', 'ktpwp' ) => array(
					'type' => 'text',
					'name' => 'unit',
					'placeholder' => esc_attr__( '月、件、時間など', 'ktpwp' ),
				),
				esc_html__( '税率', 'ktpwp' ) => array(
					'type' => 'number',
					'name' => 'tax_rate',
					'placeholder' => esc_attr__( '税率（%）空白で非課税', 'ktpwp' ),
					'step' => '1',
					'min' => '0',
					'max' => '100',
				),
				// '画像URL' => ['type' => 'text', 'name' => 'image_url'], // サービス画像のURLフィールドはコメントアウト
				esc_html__( 'メモ', 'ktpwp' ) => array(
					'type' => 'textarea',
					'name' => 'memo',
				),
				esc_html__( 'カテゴリー', 'ktpwp' ) => array(
					'type' => 'text',
					'name' => 'category',
					'options' => esc_html__( '一般', 'ktpwp' ),
					'suggest' => true,
				),
			);

			// 税制モード: 税廃止 または 税率/税額の列を非表示 の場合、税率フィールドをUIから隠す
			if ( class_exists( 'KTPWP_Tax_Policy' ) && ( KTPWP_Tax_Policy::is_abolished() || KTPWP_Tax_Policy::hide_tax_columns() ) ) {
				$fields = array_filter(
					$fields,
					function ( $field ) {
						return ! ( isset( $field['name'] ) && $field['name'] === 'tax_rate' );
					}
				);
			}

			// アクションを取得（POSTパラメータを優先、次にGETパラメータ、デフォルトは'update'）
			$action = 'update';
			if ( isset( $_POST['query_post'] ) ) {
				$action = sanitize_text_field( $_POST['query_post'] );
			} elseif ( isset( $_GET['query_post'] ) ) {
				$action = sanitize_text_field( $_GET['query_post'] );
			}

			$data_forms = ''; // フォームのHTMLコードを格納する変数を初期化

			// 検索モードの場合は検索フォームを表示（顧客・協力会社と同じデザイン）
			if ( $search_mode ) {
				$data_title = '<div class="data_detail_box search-mode">' .
					'<div class="data_detail_title">■ ' . esc_html__( 'サービスの詳細（検索モード）', 'ktpwp' ) . '</div>';

				// 検索モード用のフォーム（顧客・協力会社と同じ構造・装飾）
				$data_forms = '<div class="search-mode-form ktpwp-search-form" style="background-color: #f8f9fa !important; border: 2px solid #0073aa !important; border-radius: 8px !important; padding: 20px !important; margin: 10px 0 !important; box-shadow: 0 2px 8px rgba(0, 115, 170, 0.1) !important;">';
				$data_forms .= '<div class="notice notice-info ktp-search-mode-notice" style="margin: 10px 0; padding: 10px; background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; border-radius: 4px; display: flex; align-items: center;">';
				$data_forms .= '<span style="margin-right: 10px; color: #17a2b8; font-size: 18px;" class="material-symbols-outlined" aria-hidden="true">search</span>';
				$data_forms .= esc_html__( '検索モードです。条件を入力して検索してください。', 'ktpwp' );
				$data_forms .= '</div>';
				$data_forms .= '<form method="post" action="">';
				if ( function_exists( 'wp_nonce_field' ) ) {
					$data_forms .= wp_nonce_field( 'ktp_service_action', '_ktp_service_nonce', true, false );
				}
				$data_forms .= '<input type="hidden" name="tab_name" value="' . esc_attr( $name ) . '">';

				// 検索条件の値を取得（POSTが優先、次にGET）
				$search_service_name_value = isset( $_POST['search_service_name'] ) ? esc_attr( wp_unslash( $_POST['search_service_name'] ) ) : ( isset( $_GET['search_service_name'] ) ? esc_attr( urldecode( wp_unslash( $_GET['search_service_name'] ) ) ) : '' );
				$search_category_value = isset( $_POST['search_category'] ) ? esc_attr( wp_unslash( $_POST['search_category'] ) ) : ( isset( $_GET['search_category'] ) ? esc_attr( urldecode( wp_unslash( $_GET['search_category'] ) ) ) : '' );

				$data_forms .= '<div class="form-group" style="margin-bottom: 15px !important;">';
				$data_forms .= '<input type="text" name="search_service_name" placeholder="' . esc_attr__( 'サービス名を入力', 'ktpwp' ) . '" value="' . $search_service_name_value . '" style="width: 100% !important; padding: 12px !important; font-size: 16px !important; border: 2px solid #ddd !important; border-radius: 5px !important; box-sizing: border-box !important; transition: border-color 0.3s ease !important;">';
				$data_forms .= '</div>';

				$data_forms .= '<div class="form-group" style="margin-bottom: 15px !important;">';
				$data_forms .= '<input type="text" name="search_category" placeholder="' . esc_attr__( 'カテゴリーを入力', 'ktpwp' ) . '" value="' . $search_category_value . '" style="width: 100% !important; padding: 12px !important; font-size: 16px !important; border: 2px solid #ddd !important; border-radius: 5px !important; box-sizing: border-box !important; transition: border-color 0.3s ease !important;">';
				$data_forms .= '</div>';

				// ボタンを横並び（顧客・協力会社と同じ）
				$data_forms .= '<div class="button-group" style="display: flex; gap: 10px; margin-top: 15px !important; justify-content: flex-end !important;">';

				$data_forms .= '<input type="hidden" name="query_post" value="search_execute">';
				$data_forms .= '<button type="submit" name="send_post" title="' . esc_attr__( '検索実行', 'ktpwp' ) . '" class="search-submit-btn" style="background-color: #0073aa !important; color: white !important; border: none !important; padding: 10px 20px !important; cursor: pointer !important; border-radius: 5px !important; display: flex !important; align-items: center !important; gap: 5px !important; font-size: 14px !important; font-weight: 500 !important; transition: all 0.3s ease !important;">';
				$data_forms .= '<span class="material-symbols-outlined" style="font-size: 18px !important;">search</span>';
				$data_forms .= esc_html__( '検索実行', 'ktpwp' );
				$data_forms .= '</button>';
				$data_forms .= '</form>';

				// キャンセルボタン（独立したフォーム・顧客・協力会社と同じスタイル）
				$data_forms .= '<form method="post" action="" style="margin: 0 !important;">';
				if ( function_exists( 'wp_nonce_field' ) ) {
					$data_forms .= wp_nonce_field( 'ktp_service_action', '_ktp_service_nonce', true, false );
				}
				$data_forms .= '<input type="hidden" name="tab_name" value="' . esc_attr( $name ) . '">';
				$data_forms .= '<input type="hidden" name="query_post" value="search_cancel">';
				$data_forms .= '<button type="submit" name="send_post" title="' . esc_attr__( 'キャンセル', 'ktpwp' ) . '" style="background-color: #666 !important; color: white !important; border: none !important; padding: 10px 20px !important; cursor: pointer !important; border-radius: 5px !important; display: flex !important; align-items: center !important; gap: 5px !important; font-size: 14px !important; font-weight: 500 !important; transition: all 0.3s ease !important;">';
				$data_forms .= '<span class="material-symbols-outlined" style="font-size: 18px !important;">disabled_by_default</span>';
				$data_forms .= esc_html__( 'キャンセル', 'ktpwp' );
				$data_forms .= '</button>';
				$data_forms .= '</form>';

				$data_forms .= '</div>'; // button-group の閉じタグ
				// 該当なしメッセージは検索実行・キャンセルボタンの直下に表示
				$no_results_message = esc_html__( '該当するサービスが見つかりませんでした。条件を変更して再検索してください。', 'ktpwp' );
				if ( $search_message && $search_message === $no_results_message ) {
					$no_results_id = 'no-results-' . uniqid();
					$data_forms .= '<div id="' . esc_attr( $no_results_id ) . '" class="no-results ktp-service-no-results" style="
                    margin-top: 16px !important;
                    padding: 15px 20px !important;
                    background: linear-gradient(135deg, #ffeef1 0%, #ffeff2 100%) !important;
                    border-radius: 6px !important;
                    margin-right: 0 !important;
                    margin-bottom: 15px !important;
                    margin-left: 0 !important;
                    color: #333333 !important;
                    font-weight: 500 !important;
                    box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08) !important;
                    display: flex !important;
                    align-items: center !important;
                    font-size: 14px !important;
                ">
                <span style="margin-right: 10px !important; color: #ff6b8b !important; font-size: 18px !important;" class="material-symbols-outlined">search_off</span>
                ' . esc_html( $search_message ) . '
                </div>';
				}
				$data_forms .= '</div>'; // search-mode-form の閉じタグ
				$data_forms .= '</div>'; // data_detail_box の閉じタグ
			}
			// 空のフォームを表示(追加モードの場合)
			elseif ( $action === 'istmode' ) {
				// 追加モードは data_id を空にする
				$data_id = '';
				// 詳細表示部分の開始
				$data_title = '<div class="data_detail_box">' .
                          '<div class="data_detail_title">■ ' . esc_html__( 'サービス追加中', 'ktpwp' ) . '</div>';

				// 追加フォーム
				$data_forms .= "<form name='service_form' method='post' action=''>";
				if ( function_exists( 'wp_nonce_field' ) ) {
					$data_forms .= wp_nonce_field( 'ktp_service_action', '_ktp_service_nonce', true, false ); }

				// フィールド生成
				// ブラウザ拡張・翻訳対策
				$textarea_guard_attrs = ' spellcheck="false" autocorrect="off" autocapitalize="off" autocomplete="off" translate="no" data-gramm="false" data-gramm_editor="false" data-enable-grammarly="false"';
				foreach ( $fields as $label => $field ) {
					$value = ''; // 追加モードでは常に空
					$pattern = isset( $field['pattern'] ) ? ' pattern="' . esc_attr( $field['pattern'] ) . '"' : '';
					$required = isset( $field['required'] ) && $field['required'] ? ' required' : '';
					$fieldName = esc_attr( $field['name'] );
					$placeholder = isset( $field['placeholder'] ) ? ' placeholder="' . esc_attr__( $field['placeholder'], 'ktpwp' ) . '"' : '';
					$label_i18n = esc_html__( $label, 'ktpwp' );

					if ( $field['type'] === 'textarea' ) {
						$data_forms .= "<div class=\"form-group\"><label>{$label_i18n}：</label> <textarea name=\"{$fieldName}\"{$pattern}{$required}{$textarea_guard_attrs}>" . esc_textarea( $value ) . '</textarea></div>';
					} elseif ( $field['type'] === 'select' ) {
						$options = '';
						foreach ( (array) $field['options'] as $option ) {
							$options .= '<option value="' . esc_attr( $option ) . '">' . esc_html__( $option, 'ktpwp' ) . '</option>';
						}
						$default = isset( $field['default'] ) ? esc_html__( $field['default'], 'ktpwp' ) : '';
						$data_forms .= "<div class=\"form-group\"><label>{$label_i18n}：</label> <select name=\"{$fieldName}\"{$required}><option value=\"\">{$default}</option>{$options}</select></div>";
					} else {
						$data_forms .= "<div class=\"form-group\"><label>{$label_i18n}：</label> <input type=\"{$field['type']}\" name=\"{$fieldName}\" value=\"" . esc_attr( $value ) . "\"{$pattern}{$required}{$placeholder}></div>";
					}
				}

				$data_forms .= $this->render_is_public_checkbox_field( 0 );
				$data_forms .= $this->render_public_quantity_mode_field( 0 );
				$data_forms .= $this->render_public_html_field( '' );
				$default_cycle = class_exists( 'KTPWP_Contract_Billing_Cycle' ) ? KTPWP_Contract_Billing_Cycle::NONE : 'none';
				$data_forms .= $this->render_contract_billing_cycle_field( $default_cycle );
				$data_forms .= $this->render_service_recurring_fields_block_open( $default_cycle );
				$data_forms .= $this->render_stock_field( 1 );
				$data_forms .= $this->render_service_recurring_items_field( 0, $default_cycle );
				$data_forms .= $this->render_service_initial_fees_field( 0 );
				$data_forms .= $this->render_service_recurring_fields_block_close();
				$data_forms .= $this->render_service_contract_fields_scripts();

				$data_forms .= "<div class='button'>";
				// 追加実行ボタン（顧客タブと同じスタイル）
				$data_forms .= "<input type='hidden' name='query_post' value='new'>";
				$data_forms .= "<input type='hidden' name='data_id' value=''>";
				$data_forms .= "<input type='hidden' name='action_type' value='create_new'>";
				$data_forms .= '<button type="submit" name="send_post" value="create" title="' . esc_attr__( '追加実行', 'ktpwp' ) . '" class="insert-submit-btn">'
					. '<span class="material-symbols-outlined">select_check_box</span>'
					. esc_html__( '追加実行', 'ktpwp' ) . '</button>';
				$data_forms .= '</form>';

				// キャンセルボタン（独立したフォーム・顧客タブと同じスタイル）
				$data_forms .= "<form method='post' action='' style='display:inline-block;margin-left:10px;'>";
				if ( function_exists( 'wp_nonce_field' ) ) {
					$data_forms .= wp_nonce_field( 'ktp_service_action', '_ktp_service_nonce', true, false );
				}
				$data_forms .= "<input type='hidden' name='query_post' value='update'>";
				$data_forms .= "<input type='hidden' name='action_type' value='cancel'>";
				$data_forms .= '<button type="submit" title="' . esc_attr__( 'キャンセル', 'ktpwp' ) . '" style="background-color: #666 !important; margin-left: 10px;">'
					. '<span class="material-symbols-outlined">disabled_by_default</span>'
					. esc_html__( 'キャンセル', 'ktpwp' ) . '</button>';
				$data_forms .= '</form>';
				$data_forms .= '</div>';
			} else {
				// 通常モード：既存の詳細フォーム表示

				// データー量を取得（追加モード以外の場合）
				if ( $action !== 'istmode' ) {
					$query = $wpdb->prepare( "SELECT * FROM {$table_name} WHERE id = %d", $query_id );
					$data_num = $wpdb->get_results( $query );
					$data_num = count( $data_num ); // 現在のデータ数を取得し$data_numに格納
				} else {
					$data_num = 0; // 新規追加の場合はデータ数を0に設定
				}

				// 更新フォームを表示
				// cookieに保存されたIDを取得
				$cookie_name = 'ktp_' . $name . '_id';
				if ( isset( $_GET['data_id'] ) ) {
					$data_id = filter_input( INPUT_GET, 'data_id', FILTER_SANITIZE_NUMBER_INT );
				} elseif ( isset( $_COOKIE[ $cookie_name ] ) ) {
					$data_id = filter_input( INPUT_COOKIE, $cookie_name, FILTER_SANITIZE_NUMBER_INT );
				} else {
					$data_id = $last_id_row ? $last_id_row->id : null;
				}

				// データが存在するかチェックし、存在しない場合は空に設定
				if ( $data_id ) {
					$exists = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table_name} WHERE id = %d", $data_id ) );
					if ( ! $exists ) {
						$data_id = '';
					}
				}

				// ボタン群HTMLの準備
				$button_group_html = '<div class="button-group" style="display: flex; gap: 8px; margin-left: auto;">';

				// 削除ボタン
				if ( $data_id ) {
					$button_group_html .= '<form method="post" action="" style="margin: 0;" onsubmit="return confirm(\'' . esc_js( __( '本当に削除しますか？この操作は元に戻せません。', 'ktpwp' ) ) . '\');">';
					if ( function_exists( 'wp_nonce_field' ) ) {
						$button_group_html .= wp_nonce_field( 'ktp_service_action', '_ktp_service_nonce', true, false );
					}
					$button_group_html .= '<input type="hidden" name="data_id" value="' . esc_attr( $data_id ) . '">';
					$button_group_html .= '<input type="hidden" name="query_post" value="delete">';
					$button_group_html .= '<button type="submit" name="send_post" title="' . esc_attr__( '削除する', 'ktpwp' ) . '" class="button-style delete-submit-btn">';
					$button_group_html .= '<span class="material-symbols-outlined">delete</span>';
					$button_group_html .= '</button>';
					$button_group_html .= '</form>';
				}

				// 追加モードボタン
				$add_action = 'istmode';
				$form_action_url = add_query_arg(array('tab_name' => $name), $base_page_url);
				$button_group_html .= '<form method="post" action="' . esc_url( $form_action_url ) . '" style="margin: 0;">';
				if ( function_exists( 'wp_nonce_field' ) ) {
					$button_group_html .= wp_nonce_field( 'ktp_service_action', '_ktp_service_nonce', true, false );
				}
				$button_group_html .= '<input type="hidden" name="data_id" value="">';
				$button_group_html .= '<input type="hidden" name="query_post" value="' . esc_attr( $add_action ) . '">';
				$button_group_html .= '<button type="submit" name="send_post" title="' . esc_attr__( '追加する', 'ktpwp' ) . '" class="button-style add-submit-btn">';
				$button_group_html .= '<span class="material-symbols-outlined">add</span>';
				$button_group_html .= '</button>';
				$button_group_html .= '</form>';

				// 複製ボタン（Ajax で軽量送信。Cookie 肥大化時の 431 回避）
				if ( $data_id ) {
					$button_group_html .= '<form style="margin: 0;" onsubmit="return false;">';
					$button_group_html .= '<button type="button" class="button-style duplicate-submit-btn ktp-service-duplicate-btn" data-service-id="' . esc_attr( (string) $data_id ) . '" data-success-message="' . esc_attr__( '複製しました。', 'ktpwp' ) . '" data-error-message="' . esc_attr__( '複製に失敗しました。', 'ktpwp' ) . '" title="' . esc_attr__( '複製する', 'ktpwp' ) . '">';
					$button_group_html .= '<span class="material-symbols-outlined">content_copy</span>';
					$button_group_html .= '</button>';
					$button_group_html .= '</form>';
				}

				// 検索モードボタン
				$search_action = 'srcmode';
				$form_action_url = add_query_arg(array('tab_name' => $name), $base_page_url);
				$button_group_html .= '<form method="post" action="' . esc_url( $form_action_url ) . '" style="margin: 0;">';
				if ( function_exists( 'wp_nonce_field' ) ) {
					$button_group_html .= wp_nonce_field( 'ktp_service_action', '_ktp_service_nonce', true, false );
				}
				$button_group_html .= '<input type="hidden" name="query_post" value="' . esc_attr( $search_action ) . '">';
				$button_group_html .= '<button type="submit" name="send_post" title="' . esc_attr__( '検索する', 'ktpwp' ) . '" class="button-style search-mode-btn">';
				$button_group_html .= '<span class="material-symbols-outlined">search</span>';
				$button_group_html .= '</button>';
				$button_group_html .= '</form>';

				$button_group_html .= '</div>'; // ボタングループ終了

				// データを取得
				global $wpdb;
				$table_name = $wpdb->prefix . 'ktp_' . $name;

				// データを取得
				$query = "SELECT * FROM {$table_name} WHERE id = %d";
				$post_row = $wpdb->get_results( $wpdb->prepare( $query, $data_id ) );
				$db_image_url = '';
				foreach ( $post_row as $row ) {
					$db_image_url = isset( $row->image_url ) ? (string) $row->image_url : '';
				}

				$image_url   = $this->db_helper->resolve_image_url( (int) $data_id, $db_image_url );
				$default_url = $this->db_helper->get_default_image_url();
				$has_custom_image = ( $image_url !== $default_url );

				// 画像とアップロードフォームのHTML
				$image_section_html = '<div style="margin-top: 10px;">'; // 画像セクション開始
				$image_section_html .= '<div class="image">';
				$image_section_html .= '<div class="image-frame">';
				$image_section_html .= '<img src="' . esc_url( $image_url ) . '" alt="' . esc_attr__( 'サービス画像', 'ktpwp' ) . '" class="product-image" onerror="this.src=\'' . esc_url( $default_url ) . '\'">';

				if ( $has_custom_image ) {
					$nonce_field_delete = function_exists( 'wp_nonce_field' ) ? wp_nonce_field( 'ktp_service_action', '_ktp_service_nonce', true, false ) : '';
					$form_action_url    = add_query_arg( array( 'tab_name' => $name ), $base_page_url );
					$image_section_html .= '<form method="post" action="' . esc_url( $form_action_url ) . '" class="image-delete-form">';
					$image_section_html .= $nonce_field_delete;
					$image_section_html .= '<input type="hidden" name="data_id" value="' . esc_attr( $data_id ) . '">';
					$image_section_html .= '<input type="hidden" name="query_post" value="delete_image">';
					$image_section_html .= '<button type="submit" name="send_post" class="image-delete-btn" title="' . esc_attr__( '画像を削除', 'ktpwp' ) . '" aria-label="' . esc_attr__( '画像を削除', 'ktpwp' ) . '" onclick="return confirm(\'' . esc_js( __( '本当に削除しますか？', 'ktpwp' ) ) . '\')">&times;</button>';
					$image_section_html .= '</form>';
				}

				$image_section_html .= '</div>'; // image-frame
				$image_section_html .= '</div>'; // image
				$image_section_html .= '<div class="image_upload_form">';

				// サービス画像アップロードフォーム
				$nonce_field_upload = function_exists( 'wp_nonce_field' ) ? wp_nonce_field( 'ktp_service_action', '_ktp_service_nonce', true, false ) : '';
				$form_action_url = add_query_arg(array('tab_name' => $name), $base_page_url);
				$image_section_html .= '<form action="' . esc_url( $form_action_url ) . '" method="post" enctype="multipart/form-data" onsubmit="return checkImageUpload(this);">';
				$image_section_html .= $nonce_field_upload;
				$image_section_html .= '<div class="file-upload-container">';
				$image_section_html .= '<input type="file" name="image" class="file-input">';
				$image_section_html .= '<input type="hidden" name="data_id" value="' . esc_attr( $data_id ) . '">';
				$image_section_html .= '<input type="hidden" name="query_post" value="upload_image">';
				$image_section_html .= '<button type="submit" name="send_post" class="upload-btn" title="' . esc_attr__( '画像をアップロード', 'ktpwp' ) . '">';
				$image_section_html .= '<span class="material-symbols-outlined">upload</span>';
				$image_section_html .= '</button>';
				$image_section_html .= '</div>';
				$image_section_html .= '</form>';
				$image_section_html .= '<script>function checkImageUpload(form) { if (!form.image.value) { alert("画像が選択されていません。アップロードする画像を選択してください。"); return false; } return true; }</script>';
				$image_section_html .= '</div>'; // image_upload_form終了
				$image_section_html .= '</div>'; // 画像セクション終了

				// 表題にボタングループと画像セクションを含める
				// デバッグ用：data_idの値を確認
				if (defined('WP_DEBUG') && WP_DEBUG) {
					error_log('KTPWP Service Tab: data_id = ' . var_export($data_id, true));
					error_log('KTPWP Service Tab: data_id type = ' . gettype($data_id));
					error_log('KTPWP Service Tab: id_display condition = ' . (!empty($data_id) && $data_id !== '0' && $data_id !== 0 ? 'true' : 'false'));
				}
				$id_display = (empty($data_id) || $data_id === '0' || $data_id === 0) ? '' : '（ ID： ' . $data_id . ' ）';
				// デバッグ用：実際の表示内容を確認
				if (defined('WP_DEBUG') && WP_DEBUG) {
					error_log('KTPWP Service Tab: Final id_display = ' . $id_display);
				}
				$data_title = '<div class="data_detail_box"><div class="data_detail_title" style="display: flex; align-items: center; justify-content: space-between;">
        <div>' . esc_html__( '■ サービスの詳細', 'ktpwp' ) . $id_display . '</div>' . $button_group_html . '</div>' . $image_section_html;

				// 更新フォームの開始
				$form_action_url = add_query_arg(array('tab_name' => $name), $base_page_url);
				$data_forms .= "<form name='service_form' method='post' action='" . esc_url( $form_action_url ) . "'>";
				if ( function_exists( 'wp_nonce_field' ) ) {
					$data_forms .= wp_nonce_field( 'ktp_service_action', '_ktp_service_nonce', true, false ); }
				// ブラウザ拡張（Grammarly 等）やブラウザ翻訳が textarea に介入してフリーズする事象の回避属性
				$textarea_guard_attrs = ' spellcheck="false" autocorrect="off" autocapitalize="off" autocomplete="off" translate="no" data-gramm="false" data-gramm_editor="false" data-enable-grammarly="false"';
				foreach ( $fields as $label => $field ) {
					$value = $action === 'update' ? ${$field['name']} : '';
					$pattern = isset( $field['pattern'] ) ? ' pattern="' . esc_attr( $field['pattern'] ) . '"' : '';
					$required = isset( $field['required'] ) && $field['required'] ? ' required' : '';
					$fieldName = esc_attr( $field['name'] );
					$placeholder = isset( $field['placeholder'] ) ? ' placeholder="' . esc_attr__( $field['placeholder'], 'ktpwp' ) . '"' : '';
					$label_i18n = esc_html__( $label, 'ktpwp' );
					if ( $field['type'] === 'textarea' ) {
						$data_forms .= "<div class=\"form-group\"><label>{$label_i18n}：</label> <textarea name=\"{$fieldName}\"{$pattern}{$required}{$textarea_guard_attrs}>" . esc_textarea( $value ) . '</textarea></div>';
					} elseif ( $field['type'] === 'select' ) {
						$options = '';
						foreach ( (array) $field['options'] as $option ) {
							$selected = $value === $option ? ' selected' : '';
							$options .= '<option value="' . esc_attr( $option ) . "\"{$selected}>" . esc_html__( $option, 'ktpwp' ) . '</option>';
						}
						$default = isset( $field['default'] ) ? esc_html__( $field['default'], 'ktpwp' ) : '';
						$data_forms .= "<div class=\"form-group\"><label>{$label_i18n}：</label> <select name=\"{$fieldName}\"{$required}><option value=\"\">{$default}</option>{$options}</select></div>";
					} else {
						$step = isset( $field['step'] ) ? ' step="' . esc_attr( $field['step'] ) . '"' : '';
						$min = isset( $field['min'] ) ? ' min="' . esc_attr( $field['min'] ) . '"' : '';
						$data_forms .= "<div class=\"form-group\"><label>{$label_i18n}：</label> <input type=\"{$field['type']}\" name=\"{$fieldName}\" value=\"" . esc_attr( $value ) . "\"{$pattern}{$required}{$placeholder}{$step}{$min}></div>";
					}
				}
				$data_forms .= $this->render_is_public_checkbox_field( (int) $is_public );
				$data_forms .= $this->render_public_quantity_mode_field( (int) $public_quantity_fixed );
				$data_forms .= $this->render_public_html_field( (string) $public_html );
				$cycle_value = class_exists( 'KTPWP_Contract_Billing_Cycle' )
					? KTPWP_Contract_Billing_Cycle::sanitize( $contract_billing_cycle )
					: 'none';
				$data_forms .= $this->render_contract_billing_cycle_field( $cycle_value );
				$data_forms .= $this->render_service_recurring_fields_block_open( $cycle_value );
				$data_forms .= $this->render_stock_field( (int) $stock );
				$data_forms .= $this->render_service_recurring_items_field( (int) $data_id, $cycle_value );
				$data_forms .= $this->render_service_initial_fees_field( (int) $data_id );
				$data_forms .= $this->render_service_recurring_fields_block_close();
				$data_forms .= $this->render_service_contract_fields_scripts();
				$data_forms .= '<input type="hidden" name="query_post" value="update">';
				$data_forms .= "<input type=\"hidden\" name=\"data_id\" value=\"{$data_id}\">";
				$data_forms .= "<div class='button'>";
				$data_forms .= '<button type="submit" name="send_post" title="' . esc_attr__( '更新する', 'ktpwp' ) . '" class="update-submit-btn"><span class="material-symbols-outlined">cached</span></button>';
				$data_forms .= '</div>';
				$data_forms .= '</form>';

			} // 通常モード分岐の終了

            $data_forms .= '<div class="add">';
            // 表題は上部で既に定義済み、重複フォーム削除完了

			$data_forms .= '</div>'; // フォームを囲む<div>タグの終了

			// 詳細表示部分の終了
			$div_end = '</div> <!-- data_detail_boxの終了 -->' . "\n        </div> <!-- ktp_data_contentsの終了 -->";

			// -----------------------------
			// テンプレート印刷
			// -----------------------------

			// サービスタブのプレビュー機能を修正
			// 変数の初期化（未定義の場合に備えて）
			if ( ! isset( $service_name ) ) {
				$service_name = '';
			}
			if ( ! isset( $price ) ) {
				$price = 0;
			}
			if ( ! isset( $tax_rate ) ) {
				$tax_rate = 10.00;
			}
			if ( ! isset( $unit ) ) {
				$unit = '';
			}
			if ( ! isset( $memo ) ) {
				$memo = '';
			}
			if ( ! isset( $category ) ) {
				$category = '';
			}
			if ( ! isset( $image_url ) ) {
				$image_url = '';
			}

			// サービス情報のプレビュー用HTMLを生成
			$service_preview_html = $this->generateServicePreviewHTML(
                array(
					'service_name' => $service_name,
					'price' => $price,
					'tax_rate' => $tax_rate,
					'unit' => $unit,
					'memo' => $memo,
					'category' => $category,
					'image_url' => $image_url,
                )
            );

			// インライン <script> 内に埋め込むため、</script> 等でタグが閉じないようエスケープ（顧客・協力会社と同様）
			$service_preview_html = wp_json_encode(
				$service_preview_html,
				JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
			);
			if ( false === $service_preview_html ) {
				$service_preview_html = '""';
			}
			$service_name_json = wp_json_encode( (string) $service_name, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE );
			$print_button_title = esc_attr__( '印刷する', 'ktpwp' );
			$print_button_label = esc_attr__( '印刷', 'ktpwp' );

			$search_keep_params  = array();
			$search_toolbar_html = '';
			$search_panel_html   = '';
			if ( isset( $_GET['data_id'] ) && $_GET['data_id'] !== '' ) {
				$search_keep_params['data_id'] = sanitize_text_field( wp_unslash( $_GET['data_id'] ) );
			}
			if ( class_exists( 'KTPWP_Tab_Search_UI' ) ) {
				$search_ui           = KTPWP_Tab_Search_UI::get_instance();
				$search_toolbar_html = $search_ui->render_toolbar_form( 'service', $search_keep_params );
				$search_panel_html   = $search_ui->maybe_render_cross_search_panel( 'service' );
			}

			// JavaScript
			$print = <<<END
        <script>
            // var isPreviewOpen = false; // プレビュー機能は廃止
            
            function printContent() {
                var printContent = $service_preview_html;
                // ファイル名/タイトル生成（Print to PDF の提案名に使用される）
                var serviceName = {$service_name_json};
                var printDate = new Date();
                var yyyy = printDate.getFullYear();
                var mm = String(printDate.getMonth() + 1).padStart(2, '0');
                var dd = String(printDate.getDate()).padStart(2, '0');
                var ymd = yyyy + mm + dd;
                function sanitizeFilename(value) {
                    return String(value)
                        .replace(/[\u0000-\u001F\/\\:\uFF1A*\?"<>\|]/g, '-')
                        .replace(/\s+/g, ' ')
                        .trim();
                }
                var filenameBase = sanitizeFilename(serviceName || 'サービス') + '_' + ymd;
                var filename = filenameBase + '.pdf';

                var printWindow = window.open('', '_blank');
                printWindow.document.open();
                printWindow.document.write('<html><head><title>' + filename + '</title></head><body>');
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
            //         var printContent = $service_preview_html;
            //         previewWindow.innerHTML = printContent;
            //         previewWindow.style.display = 'block';
            //         previewButton.innerHTML = '<span class="material-symbols-outlined" aria-label="閉じる">close</span>';
            //         isPreviewOpen = true;
            //     }
            // }
        </script>
        <!-- コントローラー/プレビューアイコン（プレビューは廃止） -->
        <div class="controller" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
                {$search_toolbar_html}
                <div style="display:flex;gap:5px;margin-left:auto;">
                <button onclick="printContent()" title="{$print_button_title}" style="padding: 6px 10px; font-size: 12px;">
                    <span class="material-symbols-outlined" aria-label="{$print_button_label}">print</span>
                </button>
                </div>
        </div>
        END;

			// コンテンツを返す（複数検索結果ダイアログ用スクリプトを含む）
			$content = $message . $print . $search_panel_html . $data_list . $data_title . $data_forms . $service_search_results_script . $div_end;
			return $content;
		}

		/**
		 * 詳細表示と同じルールで選択中のサービス ID を解決する。
		 *
		 * @param string $name       タブ名。
		 * @param string $table_name サービステーブル名。
		 * @return int
		 */
		private function resolve_selected_service_id( $name, $table_name ) {
			global $wpdb;

			$cookie_name = 'ktp_' . $name . '_id';

			if ( isset( $_GET['data_id'] ) && $_GET['data_id'] !== '' ) {
				return max( 0, (int) filter_input( INPUT_GET, 'data_id', FILTER_SANITIZE_NUMBER_INT ) );
			}

			if ( isset( $_COOKIE[ $cookie_name ] ) && $_COOKIE[ $cookie_name ] !== '' ) {
				$cookie_id = (int) filter_input( INPUT_COOKIE, $cookie_name, FILTER_SANITIZE_NUMBER_INT );
				if ( $cookie_id > 0 ) {
					$exists = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table_name} WHERE id = %d", $cookie_id ) );
					if ( $exists ) {
						return $cookie_id;
					}
				}
			}

			$last_id_row = $wpdb->get_row( "SELECT id FROM {$table_name} ORDER BY id DESC LIMIT 1" );

			return $last_id_row && isset( $last_id_row->id ) ? (int) $last_id_row->id : 0;
		}

		/**
		 * 価格表示を適切にフォーマットする
		 *
		 * @param float $price 価格
		 * @return string フォーマットされた価格
		 */
		private function format_price_display( $price ) {
			if ( class_exists( 'KTPWP_Settings' ) && is_numeric( $price ) ) {
				return KTPWP_Settings::format_decimal_trimmed( $price );
			}

			return $price;
		}

		/**
		 * サービス情報のプレビュー用HTMLを生成するメソッド
		 *
		 * @param array $service_data サービスデータ
		 * @return string サービス情報のプレビューHTML
		 */
		private function generateServicePreviewHTML( $service_data ) {
			$service_name = $service_data['service_name'] ?? '';
			$price = $service_data['price'] ?? 0;
			$tax_rate = $service_data['tax_rate'] ?? null;
			$unit = $service_data['unit'] ?? '';
			$memo = $service_data['memo'] ?? '';
			$category = $service_data['category'] ?? '';
			$image_url = $service_data['image_url'] ?? '';

			// 価格の表示形式
			$price_display = '';
			if ( $price > 0 ) {
				$price_display = KTPWP_Settings::format_money( $price );
				if ( ! empty( $unit ) ) {
					$price_display .= '/' . $unit;
				}
			}

			// 税率の表示形式
			$tax_display = '';
			if ( $tax_rate !== null && $tax_rate > 0 ) {
				$tax_display = round( $tax_rate ) . '%';
			} elseif ( $tax_rate === null ) {
				$tax_display = __( '非課税', 'ktpwp' );
			}

			// 画像の表示部分
			$image_html = '';
			if ( ! empty( $image_url ) ) {
				$image_html = '
            <div class="image" style="margin-bottom: 20px;">
                <img src="' . esc_url( $image_url ) . '" alt="' . esc_attr( $service_name ) . '" class="product-image" style="border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
            </div>';
			}

			return '
        <div style="font-family: Arial, sans-serif; padding: 20px; max-width: 800px; margin: 0 auto;">
            <div style="text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 20px;">
                <h1 style="color: #333; margin: 0; font-size: 24px;">' . esc_html__( 'サービス情報', 'ktpwp' ) . '</h1>
            </div>
            
            ' . $image_html . '
            
            <table style="border-collapse: collapse; width: 100%; margin-bottom: 20px;">
                <tr>
                    <td style="border: 1px solid #ddd; padding: 12px; font-weight: bold; background-color: #f8f9fa; width: 25%;">' . esc_html__( 'サービス名', 'ktpwp' ) . '</td>
                    <td style="border: 1px solid #ddd; padding: 12px;">' . esc_html( $service_name ) . '</td>
                </tr>
                <tr>
                    <td style="border: 1px solid #ddd; padding: 12px; font-weight: bold; background-color: #f8f9fa;">' . esc_html__( '価格', 'ktpwp' ) . '</td>
                    <td style="border: 1px solid #ddd; padding: 12px;">' . esc_html( $price_display ) . '</td>
                </tr>
                <tr>
                    <td style="border: 1px solid #ddd; padding: 12px; font-weight: bold; background-color: #f8f9fa;">' . esc_html__( '税率', 'ktpwp' ) . '</td>
                    <td style="border: 1px solid #ddd; padding: 12px;">' . esc_html( $tax_display ) . '</td>
                </tr>
                <tr>
                    <td style="border: 1px solid #ddd; padding: 12px; font-weight: bold; background-color: #f8f9fa;">' . esc_html__( 'カテゴリー', 'ktpwp' ) . '</td>
                    <td style="border: 1px solid #ddd; padding: 12px;">' . esc_html( $category ) . '</td>
                </tr>
                <tr>
                    <td style="border: 1px solid #ddd; padding: 12px; font-weight: bold; background-color: #f8f9fa;">' . esc_html__( 'メモ', 'ktpwp' ) . '</td>
                    <td style="border: 1px solid #ddd; padding: 12px; white-space: pre-wrap;">' . esc_html( $memo ) . '</td>
                </tr>
            </table>
            
            <div style="text-align: center; margin-top: 30px; color: #666; font-size: 12px;">
                <p>' . esc_html__( '印刷日時:', 'ktpwp' ) . ' ' . esc_html( wp_date( __( 'Y年m月d日 H:i', 'ktpwp' ) ) ) . '</p>
            </div>
        </div>';
		}

		/**
		 * 統一されたページネーションデザインをレンダリング（2行レイアウト）
		 *
		 * @param int    $current_page 現在のページ
		 * @param int    $total_pages 総ページ数
		 * @param int    $query_limit 1ページあたりの表示件数
		 * @param string $name タブ名
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
			$pagination_html .= esc_html( sprintf( __( '%1$d / %2$d ページ（全 %3$d 件）', 'ktpwp' ), $current_page, $total_pages, $total_rows ) );
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
		 * サービスリスト表（ヘッダー付き）の開始タグを返す。
		 *
		 * @param bool $hide_tax 税率列を非表示にするか。
		 * @return string
		 */
		private function render_service_list_table_open( $hide_tax, $base_page_url, $sort_by, $sort_order ) {
			if ( ! class_exists( 'KTPWP_List_Table' ) ) {
				require_once __DIR__ . '/class-ktpwp-list-table.php';
			}

			$sort_context = array(
				'base_url'      => $base_page_url,
				'sort_by'       => $sort_by,
				'sort_order'    => $sort_order,
				'preserve_args' => KTPWP_List_Table::preserved_query_args(
					array( '_ktp_service_nonce', 'query_post', 'send_post' )
				),
			);

			$columns = array(
				array(
					'class'    => 'col-id',
					'label'    => __( 'ID', 'ktpwp' ),
					'sort_key' => 'id',
				),
				array(
					'class' => 'col-image',
					'label' => __( '画像', 'ktpwp' ),
				),
				array(
					'class'    => 'col-name',
					'label'    => __( 'サービス名', 'ktpwp' ),
					'sort_key' => 'service_name',
				),
				array(
					'class'    => 'col-public',
					'label'    => __( '公開', 'ktpwp' ),
					'sort_key' => 'is_public',
				),
				array(
					'class'    => 'col-contract',
					'label'    => __( '契約', 'ktpwp' ),
					'sort_key' => 'contract_billing_cycle',
				),
				array(
					'class'    => 'col-price-unit',
					'label'    => __( '価格/単位', 'ktpwp' ),
					'sort_key' => 'price',
				),
			);

			if ( ! $hide_tax ) {
				$columns[] = array(
					'class'    => 'col-tax',
					'label'    => __( '税率', 'ktpwp' ),
					'sort_key' => 'tax_rate',
				);
			}

			$columns[] = array(
				'class'    => 'col-category',
				'label'    => __( 'カテゴリー', 'ktpwp' ),
				'sort_key' => 'category',
			);
			$columns[] = array(
				'class'    => 'col-frequency',
				'label'    => __( '頻度', 'ktpwp' ),
				'sort_key' => 'frequency',
			);

			return KTPWP_List_Table::open( $columns, $sort_context, 'ktp-list-table--service' );
		}

		/**
		 * サイト公開チェックボックスの HTML を返す。
		 *
		 * @param int $is_public 公開フラグ（0 or 1）。
		 * @return string
		 */
		private function render_is_public_checkbox_field( $is_public ) {
			$checked = (int) $is_public === 1 ? ' checked' : '';

			return '<div class="form-group ktpwp-service-public-field">'
				. '<label>'
				. '<input type="checkbox" name="is_public" value="1"' . $checked . '>'
				. '<span class="ktpwp-service-public-field__text">' . esc_html__( 'サイトに公開', 'ktpwp' ) . '</span>'
				. '</label>'
				. '</div>';
		}

		/**
		 * 公開フォームの数量設定フィールドの HTML を返す。
		 *
		 * @param int $public_quantity_fixed 0=変更可能, 1=1固定。
		 * @return string
		 */
		private function render_public_quantity_mode_field( $public_quantity_fixed ) {
			$fixed = (int) $public_quantity_fixed === 1;
			$html  = $this->render_service_contract_fields_styles();
			$html .= '<div class="ktpwp-service-field-block ktpwp-service-field-block--public-quantity">';
			$html .= '<div class="ktpwp-service-field-block__label">';
			$html .= '<span class="ktpwp-service-field-block__label-text ktpwp-service-field-block__section-title">' . esc_html__( '公開フォームの数量', 'ktpwp' ) . '</span>';
			$html .= '<span class="ktpwp-service-field-block__hint">' . esc_html__( 'サイト公開時のお問い合わせフォームで、数量を入力させるか1に固定するかを選びます。', 'ktpwp' ) . '</span>';
			$html .= '</div>';
			$html .= '<div class="ktpwp-service-field-block__control ktpwp-service-field-block__control--radio">';
			$html .= '<label class="ktpwp-service-radio-option">';
			$html .= '<input type="radio" name="public_quantity_fixed" value="0"' . checked( ! $fixed, true, false ) . '>';
			$html .= '<span>' . esc_html__( '変更可能（数量欄を表示）', 'ktpwp' ) . '</span>';
			$html .= '</label>';
			$html .= '<label class="ktpwp-service-radio-option">';
			$html .= '<input type="radio" name="public_quantity_fixed" value="1"' . checked( $fixed, true, false ) . '>';
			$html .= '<span>' . esc_html__( '1に固定（数量欄を非表示）', 'ktpwp' ) . '</span>';
			$html .= '</label>';
			$html .= '</div></div>';

			return $html;
		}

		/**
		 * 公開用HTMLフィールドの HTML を返す。
		 *
		 * @param string $public_html 公開用HTML。
		 * @return string
		 */
		private function render_public_html_field( $public_html ) {
			$html  = $this->render_service_contract_fields_styles();
			$html .= '<div class="ktpwp-service-field-block ktpwp-service-field-block--public-html">';
			$html .= '<div class="ktpwp-service-field-block__label">';
			$html .= '<span class="ktpwp-service-field-block__label-text ktpwp-service-field-block__section-title">' . esc_html__( '公開用HTML', 'ktpwp' ) . '</span>';
			$html .= '<span class="ktpwp-service-field-block__hint">' . esc_html__( '公開商品カード・詳細に表示するHTMLです。公式LINE友だち追加ボタンなど、リンクや画像タグを記述できます。メモ欄とは別管理です。', 'ktpwp' ) . '</span>';
			$html .= '</div>';
			$html .= '<div class="ktpwp-service-field-block__control">';
			$html .= '<textarea id="public_html" name="public_html" rows="6" class="large-text code" placeholder="' . esc_attr__( '例: LINE友だち追加ボタンのHTML', 'ktpwp' ) . '">' . esc_textarea( $public_html ) . '</textarea>';
			$html .= '</div></div>';

			return $html;
		}

		/**
		 * 契約（請求サイクル）セレクトの HTML を返す。
		 *
		 * @param string $selected 選択中のサイクル値。
		 * @return string
		 */
		private function render_contract_billing_cycle_field( $selected ) {
			if ( ! class_exists( 'KTPWP_Contract_Billing_Cycle' ) ) {
				return '';
			}

			$this->ensure_service_contract_fields_script_enqueued();
			$selected = KTPWP_Contract_Billing_Cycle::sanitize( $selected );
			$html     = $this->render_service_contract_fields_styles();
			$html    .= '<div class="ktpwp-service-field-block ktpwp-service-field-block--cycle">';
			$html    .= '<div class="ktpwp-service-field-block__label">';
			$html    .= '<span class="ktpwp-service-field-block__label-text ktpwp-service-field-block__section-title">' . esc_html__( '契約（請求サイクル）', 'ktpwp' ) . '</span>';
			$html    .= '<span class="ktpwp-service-field-block__hint">' . esc_html__( '定期契約で請求する場合にサイクルを選びます。都度請求のサービスは「都度請求」のままにしてください。', 'ktpwp' ) . '</span>';
			$html    .= '</div>';
			$html    .= '<div class="ktpwp-service-field-block__control">';
			$html    .= '<select id="contract_billing_cycle" name="contract_billing_cycle">';
			foreach ( KTPWP_Contract_Billing_Cycle::get_options() as $value => $label ) {
				$is_selected = $selected === $value ? ' selected' : '';
				$html       .= '<option value="' . esc_attr( $value ) . '"' . $is_selected . '>' . esc_html( $label ) . '</option>';
			}
			$html .= '</select>';
			$html .= '</div></div>';

			return $html;
		}

		/**
		 * 定期請求項目以降（初回費用含む）のブロック開始タグ。
		 *
		 * @param string $contract_billing_cycle 請求サイクル。
		 * @return string
		 */
		private function render_service_recurring_fields_block_open( $contract_billing_cycle ) {
			$show  = $this->should_show_service_recurring_fields( $contract_billing_cycle );
			$class = 'ktpwp-service-recurring-fields';
			if ( ! $show ) {
				$class .= ' ktpwp-service-recurring-fields--hidden';
			}

			return '<div id="ktpwp-service-recurring-fields" class="' . esc_attr( $class ) . '" aria-hidden="' . ( $show ? 'false' : 'true' ) . '">';
		}

		/**
		 * 定期請求項目以降のブロック終了タグ。
		 *
		 * @return string
		 */
		private function render_service_recurring_fields_block_close() {
			return '</div>';
		}

		/**
		 * 定期契約用フィールドの表示判定（都度請求時は非表示）。
		 *
		 * @param string $contract_billing_cycle 請求サイクル。
		 * @return bool
		 */
		private function should_show_service_recurring_fields( $contract_billing_cycle ) {
			return ! class_exists( 'KTPWP_Contract_Billing_Cycle' )
				|| KTPWP_Contract_Billing_Cycle::is_recurring( $contract_billing_cycle );
		}

		/**
		 * 在庫数フィールドの HTML を返す。
		 *
		 * @param int $stock 在庫数。
		 * @return string
		 */
		private function render_stock_field( $stock ) {
			$stock = max( 0, absint( $stock ) );
			$html  = $this->render_service_contract_fields_styles();
			$html .= '<div id="ktpwp-service-stock" class="ktpwp-service-field-block ktpwp-service-field-block--stock">';
			$html .= '<div class="ktpwp-service-field-block__label">';
			$html .= '<span class="ktpwp-service-field-block__label-text ktpwp-service-field-block__section-title">' . esc_html__( '在庫数', 'ktpwp' ) . '</span>';
			$html .= '<span class="ktpwp-service-field-block__hint">' . esc_html__( '販売所の受付上限です。0 で完売。契約件数と問い合わせ案件の合計が在庫数に達すると、すべての顧客からの問い合わせを停止（保留中）します。', 'ktpwp' ) . '</span>';
			$html .= '</div>';
			if ( $stock === 0 ) {
				$html .= '<p class="ktpwp-service-stock-sold-out-notice">' . esc_html__( '公開ページでは「完売御礼！」と表示され、問い合わせは受け付けません。', 'ktpwp' ) . '</p>';
			}
			$html .= '<div class="ktpwp-service-field-block__control">';
			$html .= '<input type="number" id="stock" name="stock" min="0" step="1" value="' . esc_attr( (string) $stock ) . '">';
			$html .= '</div></div>';

			return $html;
		}

		/**
		 * サービス詳細の定期請求項目入力欄
		 *
		 * @param int    $service_id             サービス ID。
		 * @param string $contract_billing_cycle 請求サイクル。
		 * @return string
		 */
		private function render_service_recurring_items_field( $service_id, $contract_billing_cycle = 'none' ) {
			if ( ! class_exists( 'KTPWP_Contract_Recurring_Items' ) ) {
				return '';
			}

			$items = $service_id > 0
				? KTPWP_Contract_Recurring_Items::get_by_service_id( $service_id )
				: array();
			$row_count = max( 3, count( $items ) );

			$html  = $this->render_service_contract_fields_styles();
			$html .= '<div class="ktpwp-service-field-block ktpwp-service-field-block--recurring" id="ktpwp-service-recurring-items">';
			$html .= '<div class="ktpwp-service-field-block__label">';
			$html .= '<span class="ktpwp-service-field-block__label-text ktpwp-service-field-block__section-title">' . esc_html__( '定期請求項目', 'ktpwp' ) . '</span>';
			$html .= '<span class="ktpwp-service-field-block__hint">' . esc_html__(
				'定期契約作成時のデフォルト明細です（例: 家賃・共益費）。WEB初回オン＝WEB受注の今回請求に含める。オフ＝今回は0円の参考行（月額案内のみ）。初回だけ別請求は下の「初回費用」を使ってください。',
				'ktpwp'
			) . '</span>';
			$html .= '</div>';
			$html .= '<div class="ktpwp-service-field-block__control ktpwp-service-field-block__control--full">';
			$html .= '<table class="ktpwp-service-detail-items__table" id="ktpwp-service-recurring-items-table"><thead><tr>';
			$html .= $this->render_service_detail_items_table_headers( true );
			$html .= '</tr></thead><tbody>';

			for ( $i = 0; $i < $row_count; $i++ ) {
				$item = isset( $items[ $i ] ) ? $items[ $i ] : null;
				$bill_on_first = $item ? ! empty( $item->bill_on_first_invoice ) : true;
				$html .= '<tr>';
				$html .= '<td><input type="text" name="recurring_items[' . $i . '][item_name]" value="' . esc_attr( $item ? (string) $item->item_name : '' ) . '" maxlength="255"></td>';
				$html .= '<td><input type="number" name="recurring_items[' . $i . '][amount]" value="' . esc_attr( $item ? KTPWP_Settings::format_number_field_value( $item->amount ) : '' ) . '" min="0" step="0.01"></td>';
				$html .= '<td><input type="number" name="recurring_items[' . $i . '][tax_rate]" value="' . esc_attr( $item && $item->tax_rate !== null ? KTPWP_Settings::format_number_field_value( $item->tax_rate ) : '' ) . '" min="0" max="100" step="1" placeholder="' . esc_attr__( '非課税', 'ktpwp' ) . '"></td>';
				$html .= '<td class="ktpwp-service-recurring-first-invoice">';
				$html .= '<input type="hidden" name="recurring_items[' . $i . '][bill_on_first_invoice]" value="0">';
				$html .= '<label><input type="checkbox" name="recurring_items[' . $i . '][bill_on_first_invoice]" value="1" ' . checked( $bill_on_first, true, false ) . '> ' . esc_html__( '初回請求', 'ktpwp' ) . '</label>';
				$html .= '</td>';
				$html .= '</tr>';
			}

			$html .= '</tbody></table></div></div>';

			return $html;
		}

		/**
		 * サービス詳細の初回費用（既定）入力欄
		 *
		 * @param int $service_id サービス ID。
		 * @return string
		 */
		private function render_service_initial_fees_field( $service_id ) {
			if ( ! class_exists( 'KTPWP_Service_Initial_Fees' ) ) {
				return '';
			}

			$fees = $service_id > 0
				? KTPWP_Service_Initial_Fees::get_by_service_id( $service_id )
				: array();
			$row_count = max( 3, count( $fees ) );

			$presets = class_exists( 'KTPWP_Contract_DB' )
				? KTPWP_Contract_DB::get_initial_fee_presets()
				: array();
			$preset_text = ! empty( $presets )
				? implode( ' / ', array_map( 'esc_html', $presets ) )
				: '';

			$html  = $this->render_service_contract_fields_styles();
			$html .= '<div class="ktpwp-service-field-block ktpwp-service-field-block--initial-fees" id="ktpwp-service-initial-fees">';
			$html .= '<div class="ktpwp-service-field-block__label">';
			$html .= '<span class="ktpwp-service-field-block__label-text">' . esc_html__( '初回費用（既定）', 'ktpwp' ) . '</span>';
			$html .= '<span class="ktpwp-service-field-block__hint">' . esc_html__( '敷金・礼金など、初回請求時のみ自動で入る費用です（毎月請求の共益費は上の「定期請求項目」へ）。', 'ktpwp' ) . '</span>';
			$html .= '</div>';
			$html .= '<div class="ktpwp-service-field-block__control ktpwp-service-field-block__control--full">';
			$html .= '<table class="ktpwp-service-detail-items__table" id="ktpwp-service-initial-fees-table"><thead><tr>';
			$html .= $this->render_service_detail_items_table_headers();
			$html .= '</tr></thead><tbody>';

			for ( $i = 0; $i < $row_count; $i++ ) {
				$fee = isset( $fees[ $i ] ) ? $fees[ $i ] : null;
				$html .= '<tr>';
				$html .= '<td><input type="text" name="initial_fees[' . $i . '][fee_name]" value="' . esc_attr( $fee ? (string) $fee->fee_name : '' ) . '" maxlength="255"></td>';
				$html .= '<td><input type="number" name="initial_fees[' . $i . '][amount]" value="' . esc_attr( $fee ? KTPWP_Settings::format_number_field_value( $fee->amount ) : '' ) . '" min="0" step="0.01"></td>';
				$html .= '<td><input type="number" name="initial_fees[' . $i . '][tax_rate]" value="' . esc_attr( $fee && $fee->tax_rate !== null ? KTPWP_Settings::format_number_field_value( $fee->tax_rate ) : '' ) . '" min="0" max="100" step="1" placeholder="' . esc_attr__( '非課税', 'ktpwp' ) . '"></td>';
				$html .= '</tr>';
			}

			$html .= '</tbody></table>';
			if ( $preset_text !== '' ) {
				$html .= '<p class="ktpwp-service-initial-fees__presets">' . esc_html__( 'プリセット例:', 'ktpwp' ) . ' ' . $preset_text . '</p>';
			}
			$html .= '</div></div>';

			return $html;
		}

		/**
		 * 定期請求・初回費用テーブルの共通ヘッダ
		 *
		 * @return string
		 */
		private function render_service_detail_items_table_headers( $include_first_invoice = false ) {
			$html  = '<th>' . esc_html__( '項目名', 'ktpwp' ) . '</th>';
			$html .= '<th>' . esc_html__( '金額', 'ktpwp' ) . '</th>';
			$html .= '<th>' . esc_html__( '税率(%)', 'ktpwp' ) . '</th>';
			if ( $include_first_invoice ) {
				$html .= '<th>' . esc_html__( 'WEB初回', 'ktpwp' ) . '</th>';
			}

			return $html;
		}

		/**
		 * 請求サイクル切替 JS を読み込む（インライン script は WP ショートコード出力で実行されないため外部ファイル）。
		 *
		 * @return void
		 */
		private function ensure_service_contract_fields_script_enqueued() {
			if ( ! function_exists( 'wp_enqueue_script' ) ) {
				return;
			}
			if ( wp_script_is( 'ktp-service-contract-fields', 'enqueued' ) ) {
				return;
			}

			if ( ! wp_script_is( 'ktp-service-contract-fields', 'registered' ) ) {
				$plugin_file = dirname( __DIR__ ) . '/ktpwp.php';
				$script_path = dirname( __DIR__ ) . '/js/ktp-service-contract-fields.js';
				$version     = defined( 'KTPWP_PLUGIN_VERSION' ) ? KTPWP_PLUGIN_VERSION : '1.0';
				if ( file_exists( $script_path ) ) {
					$version .= '.' . filemtime( $script_path );
				}
				wp_register_script(
					'ktp-service-contract-fields',
					plugins_url( 'js/ktp-service-contract-fields.js', $plugin_file ),
					array(),
					$version,
					true
				);
				wp_localize_script(
					'ktp-service-contract-fields',
					'ktpServiceContractFields',
					array(
						'none_value' => class_exists( 'KTPWP_Contract_Billing_Cycle' )
							? KTPWP_Contract_Billing_Cycle::NONE
							: 'none',
					)
				);
			}

			wp_enqueue_script( 'ktp-service-contract-fields' );
		}

		/**
		 * 請求サイクルに応じて定期請求・初回費用ブロックの表示を切り替える JS
		 *
		 * @return string
		 */
		private function render_service_contract_fields_scripts() {
			$this->ensure_service_contract_fields_script_enqueued();

			return '';
		}

		/**
		 * 契約・定期請求項目ブロック用 CSS（キャッシュに依存しないよう1回だけ出力）
		 *
		 * @return string
		 */
		private function render_service_contract_fields_styles() {
			static $rendered = false;
			if ( $rendered ) {
				return '';
			}
			$rendered = true;

			return '<style id="ktpwp-service-contract-fields-css">'
				. '.ktpwp-service-field-block{display:grid;grid-template-columns:25% minmax(0,1fr);column-gap:12px;row-gap:0;margin-bottom:16px;align-items:start;}'
				. '.ktpwp-service-field-block__label{text-align:right;padding-top:8px;}'
				. '.ktpwp-service-field-block__label-text{display:block;font-size:14px;color:#444;line-height:1.4;font-weight:normal;}'
				. '.ktpwp-service-field-block__section-title{margin-top:12px;font-weight:700;color:#333;}'
				. '.ktpwp-service-field-block__hint{display:block;margin-top:3px;font-size:12px;line-height:1.45;color:#777;font-weight:normal;}'
				. '.ktpwp-service-field-block__control{min-width:0;}'
				. '.ktpwp-service-field-block__control select,.ktpwp-service-field-block__control input{width:100%;box-sizing:border-box;padding:8px 10px;border:1px solid #ddd;border-radius:4px;font-size:14px;background:#fff;}'
				. '.ktpwp-service-field-block--recurring,.ktpwp-service-field-block--initial-fees{display:block;}'
				. '.ktpwp-service-recurring-fields--hidden{display:none !important;}'
				. '.ktpwp-service-field-block--cycle .ktpwp-service-field-block__section-title{font-weight:600;}'
				. '.ktpwp-service-field-block--recurring .ktpwp-service-field-block__label,.ktpwp-service-field-block--initial-fees .ktpwp-service-field-block__label{display:grid;grid-template-columns:25% minmax(0,1fr);column-gap:12px;margin-bottom:8px;padding-top:0;}'
				. '.ktpwp-service-field-block--recurring .ktpwp-service-field-block__label-text,.ktpwp-service-field-block--recurring .ktpwp-service-field-block__hint,.ktpwp-service-field-block--initial-fees .ktpwp-service-field-block__label-text,.ktpwp-service-field-block--initial-fees .ktpwp-service-field-block__hint{grid-column:1;text-align:right;}'
				. '.ktpwp-service-field-block__control--full{width:100%;}'
				. '.ktpwp-service-detail-items__table{width:100%;border-collapse:collapse;table-layout:fixed;font-size:13px;}'
				. '.ktpwp-service-detail-items__table th,.ktpwp-service-detail-items__table td{border:1px solid #ddd;padding:8px;vertical-align:middle;font-size:13px;line-height:1.4;}'
				. '.ktpwp-service-detail-items__table th{background:#ffeef1;color:#4b5563;font-weight:600;text-align:left;border-bottom:1px solid #fecdd3;}'
				. '.ktpwp-service-detail-items__table td input{width:100%;box-sizing:border-box;padding:8px 10px;border:1px solid #ddd;border-radius:4px;font-size:14px;background:#fff;}'
				. '.ktpwp-service-initial-fees__presets{margin:8px 0 0;font-size:12px;line-height:1.45;color:#777;}'
				. '@media screen and (max-width:767px){.ktpwp-service-field-block,.ktpwp-service-field-block--recurring .ktpwp-service-field-block__label,.ktpwp-service-field-block--initial-fees .ktpwp-service-field-block__label{grid-template-columns:1fr;}.ktpwp-service-field-block__label,.ktpwp-service-field-block--recurring .ktpwp-service-field-block__label-text,.ktpwp-service-field-block--recurring .ktpwp-service-field-block__hint,.ktpwp-service-field-block--initial-fees .ktpwp-service-field-block__label-text,.ktpwp-service-field-block--initial-fees .ktpwp-service-field-block__hint{text-align:left;}}'
				. '</style>';
		}

		/**
		 * サービスリスト用の価格/単位表示 HTML を返す。
		 *
		 * @param float  $price 価格。
		 * @param string $unit  単位（エスケープ済み想定）。
		 * @return string
		 */
		private function render_service_price_unit_display( $price, $unit ) {
			$html = esc_html( KTPWP_Settings::format_money( $price ) );

			if ( $unit !== '' ) {
				$html .= '<span class="ktp-service-price-unit-sep">/</span>' . $unit;
			}

			return $html;
		}

		/**
		 * サービスリスト用の公開状態バッジ HTML を返す。
		 *
		 * @param int      $is_public              公開フラグ（0 or 1）。
		 * @param int|null $stock                  在庫数。
		 * @param int      $service_id             サービス ID。
		 * @param string   $contract_billing_cycle 請求サイクル。
		 * @return string
		 */
		private function render_service_public_badge( $is_public, $stock = null, $service_id = 0, $contract_billing_cycle = 'none' ) {
			$service_id = absint( $service_id );
			$is_recurring = class_exists( 'KTPWP_Contract_Billing_Cycle' )
				&& KTPWP_Contract_Billing_Cycle::is_recurring( $contract_billing_cycle );

			if ( (int) $is_public === 1 && $service_id > 0 && $is_recurring && class_exists( 'KTPWP_Contract_Service_Public_Availability' ) ) {
				$service = (object) array(
					'stock'                  => $stock ?? 1,
					'contract_billing_cycle' => $contract_billing_cycle,
				);
				$availability = KTPWP_Contract_Service_Public_Availability::get_public_availability(
					$service_id,
					$service,
					true
				);
				$state = (string) ( $availability['availability_state'] ?? 'open' );

				if ( $state === 'sold_out' ) {
					return '<span class="ktp-service-public-badge ktp-service-public-badge--sold-out" title="' . esc_attr__( '公開ページで完売表示', 'ktpwp' ) . '">'
						. esc_html__( '完売御礼！', 'ktpwp' )
						. '</span>';
				}

				if ( $state === 'pending' ) {
					return '<span class="ktp-service-public-badge ktp-service-public-badge--pending" title="' . esc_attr__( '公開ページで保留中表示', 'ktpwp' ) . '">'
						. esc_html__( '保留中', 'ktpwp' )
						. '</span>';
				}
			}

			if ( $stock !== null && class_exists( 'KTPWP_Contract_Service_Public_Availability' )
				&& KTPWP_Contract_Service_Public_Availability::is_sold_out( (int) $stock )
				&& (int) $is_public === 1 ) {
				return '<span class="ktp-service-public-badge ktp-service-public-badge--sold-out" title="' . esc_attr__( '公開ページで完売表示', 'ktpwp' ) . '">'
					. esc_html__( '完売御礼！', 'ktpwp' )
					. '</span>';
			}

			if ( (int) $is_public === 1 ) {
				return '<span class="ktp-service-public-badge ktp-service-public-badge--public" title="' . esc_attr__( 'サイトに公開中', 'ktpwp' ) . '">'
					. esc_html__( '公開', 'ktpwp' )
					. '</span>';
			}

			return '<span class="ktp-service-public-badge ktp-service-public-badge--private" title="' . esc_attr__( 'サイト非公開', 'ktpwp' ) . '">'
				. esc_html__( '非公開', 'ktpwp' )
				. '</span>';
		}
	} // End class Kntan_Service_Class

} // End if class_exists