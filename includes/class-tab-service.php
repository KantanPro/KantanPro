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

require_once 'class-image_processor.php';
require_once 'class-ktpwp-service-ui.php';
require_once 'class-ktpwp-service-db.php';

if ( ! class_exists( 'Kntan_Service_Class' ) ) {

	/**
	 * Service class for managing service data
	 *
	 * @since 1.0.0
	 */
	class Kntan_Service_Class {

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

				// istmode（追加モード）の場合は update_table を呼ばない
				$query_post = isset( $_POST['query_post'] ) ? sanitize_text_field( $_POST['query_post'] ) : '';
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'KTPWP Service: Extracted query_post: "' . $query_post . '"' );
				}

				if ( $query_post !== 'istmode' ) {
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( 'KTPWP Service: Calling update_table with query_post: "' . $query_post . '"' );
					}
					$this->update_table( $name );
				} elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( 'KTPWP Service: Skipping update_table for istmode' );
				}
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
			if ( isset( $_SESSION['ktp_service_search_mode'] ) && $_SESSION['ktp_service_search_mode'] ) {
				$search_mode = true;
				$search_message = isset( $_SESSION['ktp_service_search_message'] ) ? $_SESSION['ktp_service_search_message'] : '';
			}

			// JS通知は他タブと統一のため廃止（noticeのみ）
			$message = '';

			// 検索メッセージの表示
			if ( $search_mode && $search_message ) {
				$message .= '<div class="notice notice-info" style="margin: 10px 0; padding: 10px; background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; border-radius: 4px;">'
                . '<span style="margin-right: 10px; color: #17a2b8; font-size: 18px;" class="material-symbols-outlined">search</span>'
                . esc_html( $search_message ) . '</div>';
			}

			// -----------------------------
			// リスト表示
			// -----------------------------

			// テーブル名
			$table_name = $wpdb->prefix . 'ktp_' . $name;        // -----------------------------
			// ページネーションリンク
			// -----------------------------
			// ソート順の取得（デフォルトはIDの降順 - 新しい順）
			$sort_by = 'id';
			$sort_order = 'DESC';

			if ( isset( $_GET['sort_by'] ) ) {
				$sort_by = sanitize_text_field( $_GET['sort_by'] );
				// 安全なカラム名のみ許可（SQLインジェクション対策）
				$allowed_columns = array( 'id', 'service_name', 'price', 'unit', 'frequency', 'time', 'category' );
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

			// 表示範囲（1ページあたりの表示件数）
			// 一般設定から表示件数を取得（設定クラスが利用可能な場合）
			if ( class_exists( 'KTP_Settings' ) ) {
				$query_limit = KTP_Settings::get_work_list_range();
			} else {
				$query_limit = 20; // フォールバック値
			}
			if ( ! is_numeric( $query_limit ) || $query_limit <= 0 ) {
				$query_limit = 20; // 不正な値の場合はデフォルト値に
			}

			// ソートプルダウンを追加
			// ソートフォームのアクションURLからは 'message' を除去
			$sort_action_url = remove_query_arg( 'message', $base_page_url );

			$sort_dropdown = '<div class="sort-dropdown" style="float:right;margin-left:10px;">' .
            '<form method="get" action="' . esc_url( $sort_action_url ) . '" style="display:flex;align-items:center;">';

			// 現在のGETパラメータを維持するための隠しフィールド (messageとソート自体に関連するキーは除く)
			foreach ( $_GET as $key => $value ) {
				if ( ! in_array( $key, array( 'message', 'sort_by', 'sort_order', '_ktp_service_nonce', 'query_post', 'send_post' ) ) ) {
					$sort_dropdown .= '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( stripslashes( $value ) ) . '">';
				}
			}

			$sort_dropdown .=
            '<select id="sort-select" name="sort_by" style="margin-right:5px;">' .
            '<option value="id" ' . selected( $sort_by, 'id', false ) . '>' . esc_html__( 'ID', 'ktpwp' ) . '</option>' .
            '<option value="service_name" ' . selected( $sort_by, 'service_name', false ) . '>' . esc_html__( 'サービス名', 'ktpwp' ) . '</option>' .
            '<option value="price" ' . selected( $sort_by, 'price', false ) . '>' . esc_html__( '価格', 'ktpwp' ) . '</option>' .
            '<option value="unit" ' . selected( $sort_by, 'unit', false ) . '>' . esc_html__( '単位', 'ktpwp' ) . '</option>' .
            '<option value="category" ' . selected( $sort_by, 'category', false ) . '>' . esc_html__( 'カテゴリー', 'ktpwp' ) . '</option>' .
            '<option value="frequency" ' . selected( $sort_by, 'frequency', false ) . '>' . esc_html__( '頻度', 'ktpwp' ) . '</option>' .
            '<option value="time" ' . selected( $sort_by, 'time', false ) . '>' . esc_html__( '登録日', 'ktpwp' ) . '</option>' .
            '</select>' .
            '<select id="sort-order" name="sort_order">' .
            '<option value="ASC" ' . selected( $sort_order, 'ASC', false ) . '>' . esc_html__( '昇順', 'ktpwp' ) . '</option>' .
            '<option value="DESC" ' . selected( $sort_order, 'DESC', false ) . '>' . esc_html__( '降順', 'ktpwp' ) . '</option>' .
            '</select>' .
            '<button type="submit" style="margin-left:5px;padding:4px 8px;background:#f0f0f0;border:1px solid #ccc;border-radius:3px;cursor:pointer;" title="' . esc_attr__( '適用', 'ktpwp' ) . '">' .
            '<span class="material-symbols-outlined" style="font-size:18px;line-height:18px;vertical-align:middle;">check</span>' .
            '</button>' .
            '</form></div>';

			// リスト表示部分の開始
			$results_h = <<<END
            <div class="ktp_data_list_box">
            <div class="data_list_title">■ サービスリスト {$sort_dropdown}</div>
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

			// 全データ数を取得
			$total_query = "SELECT COUNT(*) FROM {$table_name}";
			$total_rows = $wpdb->get_var( $total_query );

			// ゼロ除算防止のための安全対策
			if ( $query_limit <= 0 ) {
				if ( class_exists( 'KTP_Settings' ) ) {
					$query_limit = KTP_Settings::get_work_list_range();
				} else {
					$query_limit = 20; // フォールバック値
				}
			}

			$total_pages = ceil( $total_rows / $query_limit );

			// 現在のページ番号を計算
			$current_page = floor( $page_start / $query_limit ) + 1;

			// データを取得（ソート順を適用）
			$query = $wpdb->prepare( "SELECT * FROM {$table_name} ORDER BY {$sort_by} {$sort_order} LIMIT %d, %d", $page_start, $query_limit );
			$post_row = $wpdb->get_results( $query );
			$results = array(); // ← 追加：未定義エラー防止
			if ( $post_row ) {
				foreach ( $post_row as $row ) {
					$id = esc_html( $row->id );
					$service_name = esc_html( $row->service_name );
					$price = isset( $row->price ) ? floatval( $row->price ) : 0;
					$tax_rate = isset( $row->tax_rate ) && $row->tax_rate !== null ? floatval( $row->tax_rate ) : null;
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
					$tax_display = $tax_rate !== null ? intval( $tax_rate ) . '%' : '非課税';
					$formatted_price = number_format( $price, 0, '.', ',' );
					$results[] = '<a href="' . esc_url( add_query_arg( $item_link_args, $base_page_url ) ) . '">' .
                    '<div class="ktp_data_list_item">' . esc_html__( 'ID', 'ktpwp' ) . ': ' . $id . ' ' . $service_name . ' | ' . $formatted_price . '円' . ( $unit ? '/' . $unit : '' ) . ' | 税率' . $tax_display . ' | ' . $category . ' | ' . esc_html__( '頻度', 'ktpwp' ) . '(' . $frequency . ')</div>' .
					'</a><!-- DEBUG: price=' . $price . ' formatted=' . $formatted_price . ' -->';
				}
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

			$data_list = $results_h . implode( $results ) . $results_f . '</div>'; // ktp_data_list_box を閉じる

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

			// デバッグ: タブクリック時の動作をログに記録
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
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
			$query_id = 0;

			// 追加モード以外の場合のみデータを取得
			if ( $action !== 'istmode' ) {
				// 現在表示中の詳細
				$cookie_name = 'ktp_' . $name . '_id';

				// デバッグログ：初期状態の確認

				if ( isset( $_GET['data_id'] ) && $_GET['data_id'] !== '' ) {
					$query_id = filter_input( INPUT_GET, 'data_id', FILTER_SANITIZE_NUMBER_INT );
					// GETパラメータで取得したIDをクッキーに保存
					setcookie( $cookie_name, $query_id, time() + ( 86400 * 30 ), '/' );
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
						setcookie( $cookie_name, $query_id, time() + ( 86400 * 30 ), '/' );
					}
				} else {
					// data_id未指定時は必ずID最新のサービスを表示（降順トップ）
					$last_id_row = $wpdb->get_row( "SELECT id FROM {$table_name} ORDER BY id DESC LIMIT 1" );
					$query_id = $last_id_row ? $last_id_row->id : 1;
					// 最新IDをクッキーに保存
					setcookie( $cookie_name, $query_id, time() + ( 86400 * 30 ), '/' );
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
					'placeholder' => esc_attr__( '価格（円）', 'ktpwp' ),
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

			// アクションを取得（POSTパラメータを優先、次にGETパラメータ、デフォルトは'update'）
			$action = 'update';
			if ( isset( $_POST['query_post'] ) ) {
				$action = sanitize_text_field( $_POST['query_post'] );
			} elseif ( isset( $_GET['query_post'] ) ) {
				$action = sanitize_text_field( $_GET['query_post'] );
			}

			$data_forms = ''; // フォームのHTMLコードを格納する変数を初期化

			// 検索モードの場合は検索フォームを表示
			if ( $search_mode ) {
				// 検索フォームの表示
				$data_title = '<div class="data_detail_box search-mode">' .
                          '<div class="data_detail_title">■ ' . esc_html__( 'サービス検索', 'ktpwp' ) . '</div>';

				// 検索フォーム
				$data_forms .= '<form method="post" action="" class="search-form">';
				if ( function_exists( 'wp_nonce_field' ) ) {
					$data_forms .= wp_nonce_field( 'ktp_service_action', '_ktp_service_nonce', true, false );
				}

				// 検索フィールド
				$data_forms .= '<div class="form-group">';
				$data_forms .= '<label>' . esc_html__( 'サービス名で検索', 'ktpwp' ) . '：</label>';
				$data_forms .= '<input type="text" name="search_service_name" placeholder="' . esc_attr__( 'サービス名を入力', 'ktpwp' ) . '" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">';
				$data_forms .= '</div>';

				$data_forms .= '<div class="form-group">';
				$data_forms .= '<label>' . esc_html__( 'カテゴリーで検索', 'ktpwp' ) . '：</label>';
				$data_forms .= '<input type="text" name="search_category" placeholder="' . esc_attr__( 'カテゴリーを入力', 'ktpwp' ) . '" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">';
				$data_forms .= '</div>';

				// 検索ボタン群
				$data_forms .= '<div class="search-button-group" style="margin-top: 20px; display: flex; gap: 10px;">';

				// 検索実行ボタン
				$data_forms .= '<input type="hidden" name="query_post" value="search_execute">';
				$data_forms .= '<button type="submit" name="send_post" title="' . esc_attr__( '検索実行', 'ktpwp' ) . '" style="background-color: #0073aa; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; display: flex; align-items: center; gap: 5px;">';
				$data_forms .= '<span class="material-symbols-outlined" style="font-size: 18px;">search</span>';
				$data_forms .= esc_html__( '検索実行', 'ktpwp' );
				$data_forms .= '</button>';
				$data_forms .= '</form>';

				// キャンセルボタン（別フォーム）
				$data_forms .= '<form method="post" action="" style="margin: 0;">';
				if ( function_exists( 'wp_nonce_field' ) ) {
					$data_forms .= wp_nonce_field( 'ktp_service_action', '_ktp_service_nonce', true, false );
				}
				$data_forms .= '<input type="hidden" name="query_post" value="search_cancel">';
				$data_forms .= '<button type="submit" name="send_post" title="' . esc_attr__( '検索キャンセル', 'ktpwp' ) . '" style="background-color: #666; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; display: flex; align-items: center; gap: 5px;">';
				$data_forms .= '<span class="material-symbols-outlined" style="font-size: 18px;">close</span>';
				$data_forms .= esc_html__( 'キャンセル', 'ktpwp' );
				$data_forms .= '</button>';
				$data_forms .= '</form>';

				$data_forms .= '</div>'; // search-button-group の終了
				// Removed: $data_forms .= '</div>'; // data_detail_box の終了 (This was incorrectly closing the detail box here)

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
				foreach ( $fields as $label => $field ) {
					$value = ''; // 追加モードでは常に空
					$pattern = isset( $field['pattern'] ) ? ' pattern="' . esc_attr( $field['pattern'] ) . '"' : '';
					$required = isset( $field['required'] ) && $field['required'] ? ' required' : '';
					$fieldName = esc_attr( $field['name'] );
					$placeholder = isset( $field['placeholder'] ) ? ' placeholder="' . esc_attr__( $field['placeholder'], 'ktpwp' ) . '"' : '';
					$label_i18n = esc_html__( $label, 'ktpwp' );

					if ( $field['type'] === 'textarea' ) {
						$data_forms .= "<div class=\"form-group\"><label>{$label_i18n}：</label> <textarea name=\"{$fieldName}\"{$pattern}{$required}>" . esc_textarea( $value ) . '</textarea></div>';
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

				$data_forms .= "<div class='button'>";
				// 追加実行ボタン
				$data_forms .= "<input type='hidden' name='query_post' value='new'>";
				$data_forms .= "<input type='hidden' name='data_id' value=''>";
				$data_forms .= "<input type='hidden' name='action_type' value='create_new'>";
				$data_forms .= "<button type='submit' name='send_post' value='create' title='追加実行'><span class='material-symbols-outlined'>select_check_box</span></button>";
				$data_forms .= '</form>';

				// キャンセルボタン（独立したフォーム）
				$data_forms .= "<form method='post' action='' style='display:inline-block;margin-left:10px;'>";
				if ( function_exists( 'wp_nonce_field' ) ) {
					$data_forms .= wp_nonce_field( 'ktp_service_action', '_ktp_service_nonce', true, false );
				}
				$data_forms .= "<input type='hidden' name='query_post' value='update'>";
				$data_forms .= "<input type='hidden' name='action_type' value='cancel'>";
				$data_forms .= "<button type='submit' name='send_post' value='cancel' title='キャンセル'><span class='material-symbols-outlined'>disabled_by_default</span></button>";
				$data_forms .= '</form>';
				$data_forms .= '<div class="add"></div>';
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

				// 複製ボタン
				if ( $data_id ) {
					$form_action_url = add_query_arg(array('tab_name' => $name), $base_page_url);
					$button_group_html .= '<form method="post" action="' . esc_url( $form_action_url ) . '" style="margin: 0;">';
					if ( function_exists( 'wp_nonce_field' ) ) {
						$button_group_html .= wp_nonce_field( 'ktp_service_action', '_ktp_service_nonce', true, false );
					}
					$button_group_html .= '<input type="hidden" name="query_post" value="duplicate">';
					$button_group_html .= '<input type="hidden" name="data_id" value="' . esc_attr( $data_id ) . '">';
					$button_group_html .= '<button type="submit" name="send_post" title="' . esc_attr__( '複製する', 'ktpwp' ) . '" class="button-style duplicate-submit-btn">';
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
				$image_url = '';
				foreach ( $post_row as $row ) {
					$image_url = esc_html( $row->image_url );
				}

				// 画像URLが空または無効な場合、デフォルト画像を使用
				if ( empty( $image_url ) ) {
					$image_url = plugin_dir_url( __DIR__ ) . 'images/default/no-image-icon.jpg';
				}

				// アップロード画像が存在するか確認
				$upload_dir = __DIR__ . '/../images/upload/';
				$upload_file = $upload_dir . $data_id . '.jpeg';
				if ( file_exists( $upload_file ) ) {
					$plugin_url = plugin_dir_url( __DIR__ );
					$image_url = $plugin_url . 'images/upload/' . $data_id . '.jpeg';
				}

				// 画像とアップロードフォームのHTML
				$image_section_html = '<div style="margin-top: 10px;">'; // 画像セクション開始
				$image_section_html .= '<div class="image"><img src="' . $image_url . '" alt="' . esc_attr__( 'サービス画像', 'ktpwp' ) . '" class="product-image" onerror="this.src=\'' . plugin_dir_url( __DIR__ ) . 'images/default/no-image-icon.jpg\'" style="width: 100%; height: auto; max-width: 100%;"></div>';
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
				$image_section_html .= '<button type="submit" name="send_post" class="upload-btn" title="画像をアップロード">';
				$image_section_html .= '<span class="material-symbols-outlined">upload</span>';
				$image_section_html .= '</button>';
				$image_section_html .= '</div>';
				$image_section_html .= '</form>';
				$image_section_html .= '<script>function checkImageUpload(form) { if (!form.image.value) { alert("画像が選択されていません。アップロードする画像を選択してください。"); return false; } return true; }</script>';

				// サービス画像削除ボタン
				$nonce_field_delete = function_exists( 'wp_nonce_field' ) ? wp_nonce_field( 'ktp_service_action', '_ktp_service_nonce', true, false ) : '';
				$form_action_url = add_query_arg(array('tab_name' => $name), $base_page_url);
				$image_section_html .= '<form method="post" action="' . esc_url( $form_action_url ) . '">';
				$image_section_html .= $nonce_field_delete;
				$image_section_html .= '<input type="hidden" name="data_id" value="' . esc_attr( $data_id ) . '">';
				$image_section_html .= '<input type="hidden" name="query_post" value="delete_image">';
				$image_section_html .= '<button type="submit" name="send_post" title="削除する" onclick="return confirm(\'本当に削除しますか？\')">';
				$image_section_html .= '<span class="material-symbols-outlined">delete</span>';
				$image_section_html .= '</button>';
				$image_section_html .= '</form>';
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
        <div>■ サービスの詳細' . $id_display . '</div>' . $button_group_html . '</div>' . $image_section_html;

				// 更新フォームの開始
				$form_action_url = add_query_arg(array('tab_name' => $name), $base_page_url);
				$data_forms .= "<form name='service_form' method='post' action='" . esc_url( $form_action_url ) . "'>";
				if ( function_exists( 'wp_nonce_field' ) ) {
					$data_forms .= wp_nonce_field( 'ktp_service_action', '_ktp_service_nonce', true, false ); }
				foreach ( $fields as $label => $field ) {
					$value = $action === 'update' ? ${$field['name']} : '';
					$pattern = isset( $field['pattern'] ) ? ' pattern="' . esc_attr( $field['pattern'] ) . '"' : '';
					$required = isset( $field['required'] ) && $field['required'] ? ' required' : '';
					$fieldName = esc_attr( $field['name'] );
					$placeholder = isset( $field['placeholder'] ) ? ' placeholder="' . esc_attr__( $field['placeholder'], 'ktpwp' ) . '"' : '';
					$label_i18n = esc_html__( $label, 'ktpwp' );
					if ( $field['type'] === 'textarea' ) {
						$data_forms .= "<div class=\"form-group\"><label>{$label_i18n}：</label> <textarea name=\"{$fieldName}\"{$pattern}{$required}>" . esc_textarea( $value ) . '</textarea></div>';
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
			$div_end = '</div> <!-- data_detail_boxの終了 -->';

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

			// PHP
			$service_preview_html = json_encode( $service_preview_html );  // JSON形式にエンコード

			// JavaScript
			$print = <<<END
        <script>
            // var isPreviewOpen = false; // プレビュー機能は廃止
            
            function printContent() {
                var printContent = $service_preview_html;
                var printWindow = window.open('', '_blank');
                printWindow.document.open();
                printWindow.document.write('<html><head><title>サービス情報印刷</title></head><body>');
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
        <div class="controller">
                <button onclick="printContent()" title="印刷する" style="padding: 6px 10px; font-size: 12px;">
                    <span class="material-symbols-outlined" aria-label="印刷">print</span>
                </button>
        </div>
        END;

			// コンテンツを返す
			$content = $message . $print . '<div class="data_contents">' . $data_list . $data_title . $data_forms . $div_end . '</div> <!-- data_contentsの終了 -->';
			return $content;
		}

		/**
		 * 価格表示を適切にフォーマットする
		 *
		 * @param float $price 価格
		 * @return string フォーマットされた価格
		 */
		private function format_price_display( $price ) {
			// 小数点以下がある場合は2桁まで表示（末尾の0は消す）
			if ( is_numeric($price) ) {
				$formatted = rtrim(rtrim(number_format($price, 2, '.', ''), '0'), '.');
				return $formatted;
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
				$price_display = $this->format_price_display( $price ) . '円';
				if ( ! empty( $unit ) ) {
					$price_display .= '/' . $unit;
				}
			}

			// 税率の表示形式
			$tax_display = '';
			if ( $tax_rate !== null && $tax_rate > 0 ) {
				$tax_display = round( $tax_rate ) . '%';
			} elseif ( $tax_rate === null ) {
				$tax_display = '非課税';
			}

			// 画像の表示部分
			$image_html = '';
			if ( ! empty( $image_url ) ) {
				$image_html = '
            <div style="text-align: center; margin-bottom: 20px;">
                <img src="' . esc_url( $image_url ) . '" alt="' . esc_attr( $service_name ) . '" style="max-width: 100%; max-height: 300px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
            </div>';
			}

			return '
        <div style="font-family: Arial, sans-serif; padding: 20px; max-width: 800px; margin: 0 auto;">
            <div style="text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 20px;">
                <h1 style="color: #333; margin: 0; font-size: 24px;">サービス情報</h1>
            </div>
            
            ' . $image_html . '
            
            <table style="border-collapse: collapse; width: 100%; margin-bottom: 20px;">
                <tr>
                    <td style="border: 1px solid #ddd; padding: 12px; font-weight: bold; background-color: #f8f9fa; width: 25%;">サービス名</td>
                    <td style="border: 1px solid #ddd; padding: 12px;">' . esc_html( $service_name ) . '</td>
                </tr>
                <tr>
                    <td style="border: 1px solid #ddd; padding: 12px; font-weight: bold; background-color: #f8f9fa;">価格</td>
                    <td style="border: 1px solid #ddd; padding: 12px;">' . esc_html( $price_display ) . '</td>
                </tr>
                <tr>
                    <td style="border: 1px solid #ddd; padding: 12px; font-weight: bold; background-color: #f8f9fa;">税率</td>
                    <td style="border: 1px solid #ddd; padding: 12px;">' . esc_html( $tax_display ) . '</td>
                </tr>
                <tr>
                    <td style="border: 1px solid #ddd; padding: 12px; font-weight: bold; background-color: #f8f9fa;">カテゴリー</td>
                    <td style="border: 1px solid #ddd; padding: 12px;">' . esc_html( $category ) . '</td>
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
	} // End class Kntan_Service_Class

} // End if class_exists