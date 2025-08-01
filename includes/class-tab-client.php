<?php
/**
 * Client Tab Class for KTPWP Plugin
 *
 * Handles client management functionality including table creation,
 * data display, and client information management.
 *
 * @package KTPWP
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'Kntan_Client_Class' ) ) {
	class Kntan_Client_Class {

		public function __construct() {
		}

		/**
		 * ソートプルダウンを生成するメソッド
		 *
		 * @param string $name テーブル名サフィックス
		 * @param string $view_mode 表示モード
		 * @param string $base_page_url 基本URL
		 * @param string $sort_by ソートカラム
		 * @param string $sort_order ソート順
		 * @param string $order_sort_by 注文履歴ソートカラム
		 * @param string $order_sort_order 注文履歴ソート順
		 * @return string ソートプルダウンHTML
		 */
		private function generate_sort_dropdown( $name, $view_mode, $base_page_url, $sort_by, $sort_order, $order_sort_by, $order_sort_order ) {
			global $wpdb;
			$sort_dropdown = '';

			// 顧客リストのソートプルダウン
			if ( $view_mode !== 'order_history' ) {
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
                '<option value="client_status" ' . selected( $sort_by, 'client_status', false ) . '>' . esc_html__( '対象｜対象外', 'ktpwp' ) . '</option>' .
                '<option value="category" ' . selected( $sort_by, 'category', false ) . '>' . esc_html__( 'カテゴリー', 'ktpwp' ) . '</option>' .
                '</select>' .
                '<select id="sort-order" name="sort_order">' .
                '<option value="ASC" ' . selected( $sort_order, 'ASC', false ) . '>' . esc_html__( '昇順', 'ktpwp' ) . '</option>' .
                '<option value="DESC" ' . selected( $sort_order, 'DESC', false ) . '>' . esc_html__( '降順', 'ktpwp' ) . '</option>' .
                '</select>' .
                '<button type="submit" style="margin-left:5px;padding:4px 8px;background:#f0f0f0;border:1px solid #ccc;border-radius:3px;cursor:pointer;" title="' . esc_attr__( '適用', 'ktpwp' ) . '">' .
                (class_exists('KTPWP_SVG_Icons') ? KTPWP_SVG_Icons::get_icon('check', array('style' => 'font-size:18px;line-height:18px;vertical-align:middle;')) : '<span class="material-symbols-outlined" style="font-size:18px;line-height:18px;vertical-align:middle;">check</span>') .
                '</button>' .
                '</form></div>';
			}
			// 注文履歴のソートプルダウン
			else {
				// 現在表示中の顧客ID
				$cookie_name = 'ktp_' . $name . '_id';
				$client_id = null;

				if ( isset( $_GET['data_id'] ) ) {
					$client_id = filter_input( INPUT_GET, 'data_id', FILTER_SANITIZE_NUMBER_INT );
				} elseif ( isset( $_COOKIE[ $cookie_name ] ) ) {
					$client_id = filter_input( INPUT_COOKIE, $cookie_name, FILTER_SANITIZE_NUMBER_INT );
				}

				// 現在のURLからソート用プルダウンのアクションURLを生成
				$sort_url = add_query_arg(
                    array(
						'tab_name' => $name,
						'view_mode' => 'order_history',
						'data_id' => $client_id ?? '',
                    ),
                    $base_page_url
                );

				// ソート用プルダウンのHTMLを構築
				$sort_dropdown = '<div class="sort-dropdown" style="float:right;margin-left:10px;">' .
					'<form method="get" action="' . esc_url( $sort_url ) . '" style="display:flex;align-items:center;">';

				// 現在のGETパラメータを維持するための隠しフィールド
				foreach ( $_GET as $key => $value ) {
					if ( $key !== 'order_sort_by' && $key !== 'order_sort_order' ) {
						$sort_dropdown .= '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '">';
					}
				}

				$sort_dropdown .=
                '<select id="order-sort-select" name="order_sort_by" style="margin-right:5px;">' .
                '<option value="id" ' . selected( $order_sort_by, 'id', false ) . '>' . esc_html__( '注文ID', 'ktpwp' ) . '</option>' .
                '<option value="time" ' . selected( $order_sort_by, 'time', false ) . '>' . esc_html__( '日付', 'ktpwp' ) . '</option>' .
                '<option value="progress" ' . selected( $order_sort_by, 'progress', false ) . '>' . esc_html__( '進捗', 'ktpwp' ) . '</option>' .
                '<option value="project_name" ' . selected( $order_sort_by, 'project_name', false ) . '>' . esc_html__( '案件名', 'ktpwp' ) . '</option>' .
                '</select>' .
                '<select id="order-sort-order" name="order_sort_order">' .
                '<option value="ASC" ' . selected( $order_sort_order, 'ASC', false ) . '>' . esc_html__( '昇順', 'ktpwp' ) . '</option>' .
                '<option value="DESC" ' . selected( $order_sort_order, 'DESC', false ) . '>' . esc_html__( '降順', 'ktpwp' ) . '</option>' .
                '</select>' .
                '<button type="submit" style="margin-left:5px;padding:4px 8px;background:#f0f0f0;border:1px solid #ccc;border-radius:3px;cursor:pointer;" title="' . esc_attr__( '適用', 'ktpwp' ) . '">' .
                (class_exists('KTPWP_SVG_Icons') ? KTPWP_SVG_Icons::get_icon('check', array('style' => 'font-size:18px;line-height:18px;vertical-align:middle;')) : '<span class="material-symbols-outlined" style="font-size:18px;line-height:18px;vertical-align:middle;">check</span>') .
                '</button>' .
                '</form></div>';
			}

			return $sort_dropdown;
		}

		// -----------------------------
		// テーブル作成
		// -----------------------------


		/**
		 * Get cookie value or default
		 *
		 * @deprecated 1.1.0 Use KTPWP_Client_UI::get_instance()->set_cookie() instead
		 * @param string $name Cookie name suffix
		 * @return int Sanitized ID value
		 */
		public function set_cookie( $name ) {
			if ( ! class_exists( 'KTPWP_Client_UI' ) ) {
				require_once __DIR__ . '/class-ktpwp-client-ui.php';
			}
			return KTPWP_Client_UI::get_instance()->set_cookie( $name );
		}

		/**
		 * Create client table
		 *
		 * @param string $tab_name Table name suffix (sanitized)
		 * @return bool Success status
		 */
		public function create_table( $tab_name ) {
			if ( ! class_exists( 'KTPWP_Client_DB' ) ) {
				require_once __DIR__ . '/class-ktpwp-client-db.php';
			}
			return KTPWP_Client_DB::get_instance()->create_table( $tab_name );
		}

		// -----------------------------
		// テーブルの操作（更新・追加・削除・検索）
		// -----------------------------

		/**
		 * Update table and handle POST operations
		 *
		 * @deprecated 1.1.0 Use KTPWP_Client_DB::get_instance()->update_table() instead
		 * @param string $tab_name Table name suffix
		 * @return void
		 */
		function Update_Table( $tab_name ) {
			if ( ! class_exists( 'KTPWP_Client_DB' ) ) {
				require_once __DIR__ . '/class-ktpwp-client-db.php';
			}
			return KTPWP_Client_DB::get_instance()->update_table( $tab_name );
		}

		// 次に表示するIDを取得するヘルパーメソッド
		/**
		 * @deprecated 1.1.0 Use KTPWP_Client_DB::get_instance()->get_next_display_id() instead
		 */
		private function get_next_display_id( $table_name, $deleted_id ) {
			if ( ! class_exists( 'KTPWP_Client_DB' ) ) {
				require_once __DIR__ . '/class-ktpwp-client-db.php';
			}
			return KTPWP_Client_DB::get_instance()->get_next_display_id( $table_name, $deleted_id );
		}


		// -----------------------------
		// テーブルの表示
		// -----------------------------

		/**
		 * View client table
		 *
		 * @param string $name Table name suffix
		 * @return void
		 */
		function View_Table( $name ) {
			global $wpdb;

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// View_Table method started
				// error_log('KTPWP Client: View_Table method started');
			}

			// $search_results_listの使用前に初期化
			$search_results_list = '';

			// テーブル名
			$table_name = $wpdb->prefix . 'ktp_' . $name;

			// 表示モードの取得（デフォルトは顧客一覧）
			$view_mode = isset( $_GET['view_mode'] ) ? sanitize_text_field( $_GET['view_mode'] ) : 'customer_list';

			// ソート順の取得（デフォルトはIDの降順）
			$sort_by = 'id';
			$sort_order = 'DESC';

			// 注文履歴用のソート順（デフォルトは日付の降順）
			$order_sort_by = 'time';
			$order_sort_order = 'DESC';

			if ( isset( $_GET['sort_by'] ) ) {
				$sort_by = sanitize_text_field( $_GET['sort_by'] );
				// 安全なカラム名のみ許可（SQLインジェクション対策）
				$allowed_columns = array( 'id', 'company_name', 'frequency', 'time', 'client_status', 'category' );
				if ( ! in_array( $sort_by, $allowed_columns ) ) {
					$sort_by = 'id'; // 不正な値の場合はデフォルトに戻す
				}
			}

			if ( isset( $_GET['sort_order'] ) ) {
				$sort_order_param = strtoupper( sanitize_text_field( $_GET['sort_order'] ) );
				// ASCかDESCのみ許可
				$sort_order = ( $sort_order_param === 'ASC' ) ? 'ASC' : 'DESC';
			}

			// 注文履歴のソート順を取得
			if ( isset( $_GET['order_sort_by'] ) ) {
				$order_sort_by = sanitize_text_field( $_GET['order_sort_by'] );
				// 安全なカラム名のみ許可（SQLインジェクション対策）
				$allowed_order_columns = array( 'id', 'time', 'progress', 'project_name' );
				if ( ! in_array( $order_sort_by, $allowed_order_columns ) ) {
					$order_sort_by = 'time'; // 不正な値の場合はデフォルトに戻す
				}
			}

			if ( isset( $_GET['order_sort_order'] ) ) {
				$order_sort_order_param = strtoupper( sanitize_text_field( $_GET['order_sort_order'] ) );
				// ASCかDESCのみ許可
				$order_sort_order = ( $order_sort_order_param === 'ASC' ) ? 'ASC' : 'DESC';
			}

			// 現在のページのURLを生成（動的パーマリンク取得）
			$base_page_url = KTPWP_Main::get_current_page_base_url();

			// 表示タイトルの設定（国際化対応）
			$list_title = ( $view_mode === 'order_history' )
            ? esc_html__( '■ 注文履歴', 'ktpwp' )
            : esc_html__( '■ 顧客リスト', 'ktpwp' );

			// ソートプルダウンを生成
			if ( method_exists( $this, 'generate_sort_dropdown' ) ) {
				$sort_dropdown = $this->generate_sort_dropdown( $name, $view_mode, $base_page_url, $sort_by, $sort_order, $order_sort_by, $order_sort_order );
			} else {
				// UIクラスから適切なソートプルダウンを取得
				if ( ! class_exists( 'KTPWP_Client_UI' ) ) {
					require_once __DIR__ . '/class-ktpwp-client-ui.php';
				}
				$ui = KTPWP_Client_UI::get_instance();
				$sort_dropdown = $ui->render_list_header( $name, $view_mode, '', $base_page_url, $sort_by, $sort_order, $order_sort_by, $order_sort_order );
				// ヘッダー全体ではなくドロップダウンだけを取得するため、必要な部分だけを抽出
				if ( preg_match( '/<div class="sort-dropdown".*?<\/div><\/div>/s', $sort_dropdown, $matches ) ) {
					$sort_dropdown = $matches[0];
				} else {
					$sort_dropdown = '';
				}
			}

			// 段階的にUIクラスに処理を委譲
			if ( ! class_exists( 'KTPWP_Client_UI' ) ) {
				require_once __DIR__ . '/class-ktpwp-client-ui.php';
			}
			$client_ui = KTPWP_Client_UI::get_instance();
			$results_h = $client_ui->view_table( $name );

			// スタート位置を決める
			$page_stage = $_GET['page_stage'] ?? '';
			$page_start = $_GET['page_start'] ?? 0;
			$flg = $_GET['flg'] ?? '';
			if ( $page_stage == '' ) {
				$page_start = 0;
			}
			
			// 負の値を防ぐ安全対策
			$page_start = max( 0, intval( $page_start ) );

			// 表示件数を取得
			$query_limit = 20; // デフォルト値
			if ( class_exists( 'KTP_Settings' ) ) {
				$query_limit = KTP_Settings::get_work_list_range();
			}

			$query_range = $page_start . ',' . $query_limit;

			$query_order_by = 'frequency';

			// 注文履歴モードの場合
			if ( $view_mode === 'order_history' ) {
				// 現在表示中の顧客ID
				$cookie_name = 'ktp_' . $name . '_id';
				if ( isset( $_GET['data_id'] ) ) {
					$client_id = filter_input( INPUT_GET, 'data_id', FILTER_SANITIZE_NUMBER_INT );
				} elseif ( isset( $_COOKIE[ $cookie_name ] ) ) {
					$client_id = filter_input( INPUT_COOKIE, $cookie_name, FILTER_SANITIZE_NUMBER_INT );
				} else {
					// 最後のIDを取得して表示
					// $query = "SELECT id FROM {$table_name} ORDER BY id DESC LIMIT 1";
					// $last_id_row = $wpdb->get_row($query);
					$query_last_id = "SELECT id FROM {$table_name} ORDER BY id DESC LIMIT 1";
					$last_id_row = $wpdb->get_row( $query_last_id );
					$client_id = $last_id_row ? $last_id_row->id : 1;
				}

				// 受注書テーブル
				$order_table = $wpdb->prefix . 'ktp_order';

				// 全データ数を取得（この顧客IDに関連する受注書）
				$related_client_ids = array( $client_id );

				// IDのリストを文字列に変換（安全対策）
				$client_ids_str = implode( ',', array_map( 'intval', $related_client_ids ) );

				if ( empty( $client_ids_str ) ) {
					$client_ids_str = '0'; // 安全なフォールバック値
				}

				// IDが複数ある場合、IN句を使用 (元のコメントだが、現状は単一ID)
				// $total_query = "SELECT COUNT(*) FROM {$order_table} WHERE client_id IN ({$client_ids_str})";
				// $total_rows = $wpdb->get_var($total_query);
				// $client_id 変数を直接使用する方が明確
				$total_query_prepared = $wpdb->prepare(
					"SELECT COUNT(*) FROM {$order_table} WHERE client_id = %d",
					intval( $client_id )
				);
				$total_rows = $wpdb->get_var( $total_query_prepared );
				$total_pages = ceil( $total_rows / $query_limit );

				// 現在のページ番号を計算
				$current_page = floor( $page_start / $query_limit ) + 1;

				// この顧客の受注書を取得
				$related_client_ids = array( $client_id );

				// IDのリストを文字列に変換（safety check）
				$client_ids_str = implode( ',', array_map( 'intval', $related_client_ids ) );

				if ( empty( $client_ids_str ) ) {
					$client_ids_str = '0'; // 安全なフォールバック値
				}

				// IDが複数ある場合、IN句を使用（ソートオプションを適用）
				$order_sort_column = esc_sql( $order_sort_by ); // SQLインジェクション対策
				$order_sort_direction = $order_sort_order === 'ASC' ? 'ASC' : 'DESC'; // SQLインジェクション対策

				// 修正: $order_sort_column 内の % を %% にエスケープして prepare に渡す
				$order_sort_column_prepared = str_replace( '%', '%%', $order_sort_column );

				// 修正: $client_ids_str をプレースホルダーに置き換え
				$client_ids_array = explode( ',', $client_ids_str );
				$placeholders = implode( ',', array_fill( 0, count( $client_ids_array ), '%d' ) );
				$query = $wpdb->prepare(
					"SELECT * FROM {$order_table} WHERE client_id IN ($placeholders) ORDER BY {$order_sort_column_prepared} {$order_sort_direction} LIMIT %d, %d", // $order_sort_column_prepared を使用
					array_merge( $client_ids_array, array( intval( $page_start ), intval( $query_limit ) ) )
				);

				$order_rows = $wpdb->get_results( $query );

				// 顧客情報を取得して表示
				$client_query = $wpdb->prepare( "SELECT * FROM {$table_name} WHERE id = %d", $client_id );
				$client_data = $wpdb->get_row( $client_query );

				if ( $client_data ) {
					$client_name = esc_html( $client_data->company_name );
					$client_user_name = esc_html( $client_data->name );

					// 注文履歴のリストヘッダーを更新
					$results_h = <<<END
               <div class="ktp_data_contents">
                   <div class="ktp_data_list_box">
                   <div class="data_list_title">■ {$client_name} の注文履歴</div>
               END;

					if ( isset( $sort_dropdown ) ) {
						$results_h = str_replace( '</div>', "$sort_dropdown</div>", $results_h );
					}

					$results = array(); // 結果を格納する配列を初期化

					if ( $order_rows ) {
						// 進捗ラベル
						$progress_labels = array(
							1 => esc_html__( '受付中', 'ktpwp' ),
							2 => esc_html__( '見積中', 'ktpwp' ),
							3 => esc_html__( '受注', 'ktpwp' ),
							4 => esc_html__( '完了', 'ktpwp' ),
							5 => esc_html__( '請求済', 'ktpwp' ),
							6 => esc_html__( '入金済', 'ktpwp' ),
							7 => esc_html__( 'ボツ', 'ktpwp' ),
						);

						foreach ( $order_rows as $order ) {
							$order_id = esc_html( $order->id );
							$project_name = isset( $order->project_name ) ? esc_html( $order->project_name ) : '';
							$progress = intval( $order->progress );
							$progress_label = isset( $progress_labels[ $progress ] ) ? $progress_labels[ $progress ] : '不明';

							// 日時フォーマット変換
							$raw_time = $order->time;
							$formatted_time = '';
							if ( ! empty( $raw_time ) ) {
								if ( is_numeric( $raw_time ) && strlen( $raw_time ) >= 10 ) {
									$timestamp = (int) $raw_time;
									$dt = new DateTime( '@' . $timestamp );
									$dt->setTimezone( new DateTimeZone( 'Asia/Tokyo' ) );
								} else {
									$dt = date_create( $raw_time, new DateTimeZone( 'Asia/Tokyo' ) );
								}
								if ( $dt ) {
									$week = array( '日', '月', '火', '水', '木', '金', '土' );
									$w = $dt->format( 'w' );
									$formatted_time = $dt->format( 'Y/n/j' ) . '（' . $week[ $w ] . '）' . $dt->format( ' H:i' );
								}
							}

							// 完了日のフォーマット
							$completion_date = '';
							if ( ! empty( $order->completion_date ) ) {
								$completion_dt = date_create( $order->completion_date, new DateTimeZone( 'Asia/Tokyo' ) );
								if ( $completion_dt ) {
									$week = array( '日', '月', '火', '水', '木', '金', '土' );
									$w = $completion_dt->format( 'w' );
									$completion_date = $completion_dt->format( 'Y/n/j' ) . '（' . $week[ $w ] . '）';
								}
							}

							// 受注書の詳細へのリンク（シンプルなURL生成）
							$detail_url = add_query_arg(
                                array(
									'tab_name' => 'order',
									'order_id' => $order_id,
                                ),
                                $base_page_url
                            );

							// 1行化：ID・案件名・完了日・進捗を同じ行に表示（完了日は「完了日：」ラベル付き）
							$order_info = 'ID: ' . $order_id . ' - ' . $project_name;
							if ( ! empty( $completion_date ) ) {
								$order_info .= ' <span style="color: #666; margin-left: 12px;">完了日：' . $completion_date . '</span>';
							}
							$order_info .= ' <span style="float:right;" class="status-' . $progress . '">' . $progress_label . '</span>';

							$results[] = <<<END
                       <a href="{$detail_url}">
                           <div class="ktp_data_list_item">{$order_info}</div>
                       </a>
                       END;
						}
					} else {
						$results[] = '<div class="ktp_data_list_item" style="padding: 15px 20px; background: linear-gradient(135deg, #fff3cd 0%, #fff8e1 100%); border-radius: 6px; margin: 15px 0; color: #856404; font-weight: 600; text-align: center; box-shadow: 0 3px 12px rgba(0,0,0,0.07); display: flex; align-items: center; justify-content: center; font-size: 16px; gap: 10px;">'
							. '<span class="material-symbols-outlined" style="color: #ffc107;">info</span>'
							. 'まだ注文がありません。'
							. '</div>';
					}
				} else {
					$results[] = '<div class="ktp_data_list_item">' . esc_html__( '顧客データが見つかりません。', 'ktpwp' ) . '</div>';
				}
			} else {
				// 通常の顧客一覧表示（既存のコード）
				// 全データ数を取得
				// $total_query = "SELECT COUNT(*) FROM {$table_name}";
				// $total_rows = $wpdb->get_var($total_query);
				$total_query_prepared = "SELECT COUNT(*) FROM {$table_name}";
				$total_rows = $wpdb->get_var( $total_query_prepared );
				$total_pages = ceil( $total_rows / $query_limit );

				// 現在のページ番号を計算
				$current_page = floor( $page_start / $query_limit ) + 1;

				// データを取得（選択されたソート順で）
				$sort_column = esc_sql( $sort_by ); // SQLインジェクション対策
				$sort_column_prepared = str_replace( '%', '%%', $sort_column ); // % を %% にエスケープ
				$sort_direction = $sort_order === 'ASC' ? 'ASC' : 'DESC'; // SQLインジェクション対策
				$query = $wpdb->prepare( "SELECT * FROM {$table_name} ORDER BY {$sort_column_prepared} {$sort_direction} LIMIT %d, %d", intval( $page_start ), intval( $query_limit ) );
				$post_row = $wpdb->get_results( $query );

				$results = array(); // 結果を格納する配列を初期化

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
						$client_status = esc_html( $row->client_status );
						$frequency = esc_html( $row->frequency );
						$category = esc_html( $row->category ?? '' ); // カテゴリーフィールドを追加

						// リスト項目
						$cookie_name = 'ktp_' . $name . '_id';
						$link_url = esc_url(
                            add_query_arg(
                                array(
									'tab_name' => $name,
									'data_id' => $id,
									'page_start' => $page_start,
									'page_stage' => $page_stage,
                                ),
                                $base_page_url
                            )
                        );

						// 削除済み（対象外）の場合の視覚的スタイリング
						$list_style = '';
						$deleted_mark = '';
						if ( $client_status === '対象外' ) {
							$list_style = ' style="background-color: #ffe6e6; border-left: 3px solid #ff4444;"';
							$deleted_mark = '<span style="color: #ff4444; font-weight: bold; margin-right: 5px;">[削除済み]</span>';
						}

						// カテゴリーが空の場合は何も表示しない
						$display_category = ! empty( $category ) ? $category : '';

						$results[] = '<a href="' . $link_url . '" onclick="document.cookie = \'{$cookie_name}=\' + ' . $id . ';">'
						. '<div class="ktp_data_list_item"' . $list_style . '>' . $deleted_mark . 'D:' . $id . ' ' . $company_name . ' | ' . $user_name . ' | ' . $display_category . ' | 頻度(' . $frequency . ')</div>'
						. '</a>';
					}
				} else {
					// 新しい0データ案内メッセージ（統一デザイン・ガイダンス）
					$results[] = '<div class="ktp_data_list_item" style="padding: 15px 20px; background: linear-gradient(135deg, #e3f2fd 0%, #fce4ec 100%); border-radius: 8px; margin: 18px 0; color: #333; font-weight: 600; box-shadow: 0 3px 12px rgba(0,0,0,0.07); display: flex; align-items: center; font-size: 15px; gap: 10px;">'
					. '<span class="material-symbols-outlined" aria-label="データ作成">add_circle</span>'
					. '<span style="font-size: 1em; font-weight: 600;">[＋]ボタンを押してデーターを作成してください</span>'
					. '<span style="margin-left: 18px; font-size: 13px; color: #888;">データがまだ登録されていません</span>'
					. '</div>';
				}
			}

			// 統一されたページネーションデザインを使用
			$results_f = $this->render_pagination( $current_page, $total_pages, $query_limit, $name, $flg, $base_page_url, $total_rows, $view_mode, $client_id ?? null );

			// 顧客データが0件の場合の処理
			if ( $total_rows == 0 ) {
				// 協力会社タブと同じパターンで「info顧客データがありません」を表示
				$results_f .= '<div style="padding: 15px 20px; background: linear-gradient(135deg, #fff3cd 0%, #fff8e1 100%); border-radius: 6px; margin: 15px 0; color: #856404; font-weight: 600; text-align: center; box-shadow: 0 3px 12px rgba(0,0,0,0.07); display: flex; align-items: center; justify-content: center; font-size: 16px; gap: 10px;">'
					. '<span class="material-symbols-outlined" style="color: #ffc107;">info</span>'
					. '顧客データがありません。'
					. '</div>';
			} else {
				// 受注履歴セクションを追加（リストBOX内、ページネーションの後）
				// 詳細BOXに表示されている顧客のIDを取得して注文履歴タイトルを生成
				$current_customer_name = '';
				$current_customer_id = '';

				// 詳細BOXに表示されている顧客のIDを取得
				if ( isset( $_GET['data_id'] ) && $_GET['data_id'] !== '' ) {
					$current_customer_id = filter_input( INPUT_GET, 'data_id', FILTER_SANITIZE_NUMBER_INT );
				} elseif ( isset( $_COOKIE[ $cookie_name ] ) && $_COOKIE[ $cookie_name ] !== '' ) {
					$current_customer_id = filter_input( INPUT_COOKIE, $cookie_name, FILTER_SANITIZE_NUMBER_INT );
				} else {
					// data_id未指定時は最大IDを取得
					$max_id_row = $wpdb->get_row( "SELECT id FROM {$table_name} ORDER BY id DESC LIMIT 1" );
					$current_customer_id = $max_id_row ? $max_id_row->id : '';
				}

				// 顧客名を取得
				if ( $current_customer_id ) {
					$customer_query = $wpdb->prepare( "SELECT company_name FROM {$table_name} WHERE id = %d", $current_customer_id );
					$customer_data = $wpdb->get_row( $customer_query );
					if ( $customer_data ) {
						$current_customer_name = esc_html( $customer_data->company_name );
					}
				}

				// 注文履歴タイトル（ソートシステム）
				$order_sort_dropdown = $this->generate_sort_dropdown( $name, 'order_history', $base_page_url, $sort_by, $sort_order, $order_sort_by, $order_sort_order );
				$results_f .= '<div class="data_list_title">■ ' . esc_html( $current_customer_name ) . 'の注文履歴';
				if ( ! empty( $order_sort_dropdown ) ) {
					$results_f .= $order_sort_dropdown;
				}
				$results_f .= '</div>';

				// 注文履歴データを取得（注文履歴ボタンと同じロジック）
				$order_table = $wpdb->prefix . 'ktp_order';

				// この顧客の受注書を取得
				$related_client_ids = array( $current_customer_id );

				// IDのリストを文字列に変換（safety check）
				$client_ids_str = implode( ',', array_map( 'intval', $related_client_ids ) );

				if ( empty( $client_ids_str ) ) {
					$client_ids_str = '0'; // 安全なフォールバック値
				}

				// IDが複数ある場合、IN句を使用（ソートオプションを適用）
				$order_sort_column = esc_sql( $order_sort_by ); // SQLインジェクション対策
				$order_sort_direction = $order_sort_order === 'ASC' ? 'ASC' : 'DESC'; // SQLインジェクション対策

				// 修正: $order_sort_column 内の % を %% にエスケープして prepare に渡す
				$order_sort_column_prepared = str_replace( '%', '%%', $order_sort_column );

				// 修正: $client_ids_str をプレースホルダーに置き換え
				$client_ids_array = explode( ',', $client_ids_str );
				$placeholders = implode( ',', array_fill( 0, count( $client_ids_array ), '%d' ) );

				// 総件数取得（顧客のステータスに関係なく注文履歴を取得）
				$order_total_query = $wpdb->prepare(
					"SELECT COUNT(*) FROM {$order_table} WHERE client_id IN ($placeholders)",
					$client_ids_array
				);
				$order_total_rows = $wpdb->get_var( $order_total_query );
				$order_total_pages = ceil( $order_total_rows / $query_limit );

				// デバッグ情報を追加
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( "KTPWP Debug - Order History: client_id={$current_customer_id}, order_total_rows={$order_total_rows}, order_total_query={$order_total_query}" );
				}

				// 注文履歴のページを取得（order_pageパラメータを使用）
				$order_page = isset( $_GET['order_page'] ) ? intval( $_GET['order_page'] ) : 1;
				$order_page = max( 1, $order_page ); // 最小値は1
				$order_page = min( $order_page, $order_total_pages ); // 最大値は総ページ数
				$order_start = ( $order_page - 1 ) * $query_limit;
				$order_start = max( 0, $order_start ); // 負の値を防ぐ安全対策
				$order_current_page = $order_page;

				// 受注書データを取得（顧客のステータスに関係なく注文履歴を取得）
				$order_query = $wpdb->prepare(
					"SELECT * FROM {$order_table} WHERE client_id IN ($placeholders) ORDER BY {$order_sort_column_prepared} {$order_sort_direction} LIMIT %d, %d",
					array_merge( $client_ids_array, array( intval( $order_start ), intval( $query_limit ) ) )
				);
				$order_rows = $wpdb->get_results( $order_query );

				// デバッグ情報を追加
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'KTPWP Debug - Order History: order_rows_count=' . count( $order_rows ) . ", order_query={$order_query}" );
				}

				// 注文履歴リスト表示（注文履歴ボタンと同じ表示形式）
				if ( $order_rows ) {
					// 進捗ラベル
					$progress_labels = array(
						1 => esc_html__( '受付中', 'ktpwp' ),
						2 => esc_html__( '見積中', 'ktpwp' ),
						3 => esc_html__( '受注', 'ktpwp' ),
						4 => esc_html__( '完了', 'ktpwp' ),
						5 => esc_html__( '請求済', 'ktpwp' ),
						6 => esc_html__( '入金済', 'ktpwp' ),
						7 => esc_html__( 'ボツ', 'ktpwp' ),
					);

					foreach ( $order_rows as $order ) {
						$order_id = esc_html( $order->id );
						$project_name = isset( $order->project_name ) ? esc_html( $order->project_name ) : '';
						$progress = intval( $order->progress );
						$progress_label = isset( $progress_labels[ $progress ] ) ? $progress_labels[ $progress ] : '不明';

						// 日時フォーマット変換
						$raw_time = $order->time;
						$formatted_time = '';
						if ( ! empty( $raw_time ) ) {
							if ( is_numeric( $raw_time ) && strlen( $raw_time ) >= 10 ) {
								$timestamp = (int) $raw_time;
								$dt = new DateTime( '@' . $timestamp );
								$dt->setTimezone( new DateTimeZone( 'Asia/Tokyo' ) );
							} else {
								$dt = date_create( $raw_time, new DateTimeZone( 'Asia/Tokyo' ) );
							}
							if ( $dt ) {
								$week = array( '日', '月', '火', '水', '木', '金', '土' );
								$w = $dt->format( 'w' );
								$formatted_time = $dt->format( 'Y/n/j' ) . '（' . $week[ $w ] . '）' . $dt->format( ' H:i' );
							}
						}

						// 完了日のフォーマット
						$completion_date = '';
						if ( ! empty( $order->completion_date ) ) {
							$completion_dt = date_create( $order->completion_date, new DateTimeZone( 'Asia/Tokyo' ) );
							if ( $completion_dt ) {
								$week = array( '日', '月', '火', '水', '木', '金', '土' );
								$w = $completion_dt->format( 'w' );
								$completion_date = $completion_dt->format( 'Y/n/j' ) . '（' . $week[ $w ] . '）';
							}
						}

						// 受注書の詳細へのリンク（シンプルなURL生成）
						$detail_url = add_query_arg(
                            array(
								'tab_name' => 'order',
								'order_id' => $order_id,
                            ),
                            $base_page_url
                        );

						// 1行化：ID・案件名・完了日・進捗を同じ行に表示（完了日は「完了日：」ラベル付き）
						$order_info = 'ID: ' . $order_id . ' - ' . $project_name;
						if ( ! empty( $completion_date ) ) {
							$order_info .= ' <span style="color: #666; margin-left: 12px;">完了日：' . $completion_date . '</span>';
						}
						$order_info .= ' <span style="float:right;" class="status-' . $progress . '">' . $progress_label . '</span>';

						$results_f .= '<a href="' . $detail_url . '">'
							. '<div class="ktp_data_list_item">' . $order_info . '</div>'
							. '</a>';
					}
				} else {
					$results_f .= '<div class="ktp_data_list_item" style="padding: 15px 20px; background: linear-gradient(135deg, #fff3cd 0%, #fff8e1 100%); border-radius: 6px; margin: 15px 0; color: #856404; font-weight: 600; text-align: center; box-shadow: 0 3px 12px rgba(0,0,0,0.07); display: flex; align-items: center; justify-content: center; font-size: 16px; gap: 10px;">'
						. '<span class="material-symbols-outlined" style="color: #ffc107;">info</span>'
						. 'まだ注文がありません。'
						. '</div>';
				}

				// 注文履歴ページネーション
				// どんな場合でもページネーションを表示するため、条件チェックを削除
				// if ($order_total_pages > 1) {
					// 注文履歴のページネーションではview_modeを設定せず、通常の顧客リスト表示を維持
					$results_f .= $this->render_order_history_pagination( $order_current_page, $order_total_pages, $query_limit, $name, $flg, $base_page_url, $order_total_rows, $current_customer_id );
				// }
			}

			// リストBOXを閉じる
			$results_f .= '</div>';

			$data_list = $results_h . implode( $results ) . $results_f;

			// -----------------------------
			// 詳細表示(GET)
			// -----------------------------

			// アクションを取得（POSTパラメータを優先、次にGETパラメータ、デフォルトは'update'）
			$action = isset( $_POST['query_post'] ) ? $_POST['query_post'] : ( isset( $_GET['query_post'] ) ? $_GET['query_post'] : 'update' );

			// 安全性確保: GETリクエストの場合は危険なアクションを実行しない
			if ( $_SERVER['REQUEST_METHOD'] === 'GET' && in_array( $action, array( 'delete', 'insert', 'search', 'duplicate', 'istmode', 'srcmode' ) ) ) {
				$action = 'update';
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				}
			}

			// デバッグ: アクション値を確認
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			}

			// アクション値を保護するため、元の値を保存
			$original_action = $action;
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			}

			// 初期化：追加モードかどうかを最初に判定
			$cookie_name = 'ktp_' . $name . '_id';
			$query_id = null;

			// 変数の初期化
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
			$client_status = '';
			$order_customer_name = '';
			$order_user_name = '';

			// 追加モード以外の場合のみデータを取得
			if ( $action !== 'istmode' ) {
				if ( isset( $_GET['data_id'] ) && $_GET['data_id'] !== '' ) {
					$query_id = filter_input( INPUT_GET, 'data_id', FILTER_SANITIZE_NUMBER_INT );
				} elseif ( isset( $_COOKIE[ $cookie_name ] ) && $_COOKIE[ $cookie_name ] !== '' ) {
					$cookie_id = filter_input( INPUT_COOKIE, $cookie_name, FILTER_SANITIZE_NUMBER_INT );
					// クッキーIDがDBに存在するかチェック
					$exists = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table_name} WHERE id = %d", $cookie_id ) );
					if ( $exists ) {
						$query_id = $cookie_id;
					} else {
						// 存在しなければ最大ID
						// $max_id_row = $wpdb->get_row("SELECT id FROM {$table_name} ORDER BY id DESC LIMIT 1");
						$query_max_id_cookie_fallback = "SELECT id FROM {$table_name} ORDER BY id DESC LIMIT 1";
						$max_id_row = $wpdb->get_row( $query_max_id_cookie_fallback );
						$query_id = $max_id_row ? $max_id_row->id : '';
					}
				} else {
					// data_id未指定時は必ずID最大の顧客を表示
					// $max_id_row = $wpdb->get_row("SELECT id FROM {$table_name} ORDER BY id DESC LIMIT 1");
					$query_max_id_no_get_cookie = "SELECT id FROM {$table_name} ORDER BY id DESC LIMIT 1";
					$max_id_row = $wpdb->get_row( $query_max_id_no_get_cookie );
					$query_id = $max_id_row ? $max_id_row->id : '';
				}

				// データを取得し変数に格納
				$query = $wpdb->prepare( "SELECT * FROM {$table_name} WHERE id = %d", $query_id );
				$post_row = $wpdb->get_results( $query );
				if ( ! $post_row || count( $post_row ) === 0 ) {
					// 存在しないIDの場合は最大IDを取得して再表示
					// $max_id_row = $wpdb->get_row("SELECT id FROM {$table_name} ORDER BY id DESC LIMIT 1");
					$query_max_id_fetch_fail_fallback = "SELECT id FROM {$table_name} ORDER BY id DESC LIMIT 1";
					$max_id_row = $wpdb->get_row( $query_max_id_fetch_fail_fallback );
					if ( $max_id_row && isset( $max_id_row->id ) ) {
						$query_id = $max_id_row->id;
						$query = $wpdb->prepare( "SELECT * FROM {$table_name} WHERE id = %d", $query_id );
						$post_row = $wpdb->get_results( $query );
					}
					// それでもデータがなければ「データがありません」
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
						$client_status = '対象';
						$order_customer_name = '';
						$order_user_name = '';
						// $post_row を空配列にして以降のフォーム生成処理を通す
						$post_row = array();
						// リスト部分にだけ「0データ」ガイダンスを出す（統一デザイン）
						$results[] = '<div class="ktp_data_list_item" style="padding: 15px 20px; background: linear-gradient(135deg, #e3f2fd 0%, #fce4ec 100%); border-radius: 8px; margin: 18px 0; color: #333; font-weight: 600; box-shadow: 0 3px 12px rgba(0,0,0,0.07); display: flex; align-items: center; font-size: 15px; gap: 10px;">'
							. '<span class="material-symbols-outlined" aria-label="データ作成">add_circle</span>'
							. '<span style="font-size: 1em; font-weight: 600;">[＋]ボタンを押してデーターを作成してください</span>'
							. '<span style="margin-left: 18px; font-size: 13px; color: #888;">データがまだ登録されていません</span>'
							. '</div>';
					}
				}
				// 表示したIDをクッキーに保存
				setcookie( $cookie_name, $query_id, time() + ( 86400 * 30 ), '/' ); // 30日間有効
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
					$client_status = esc_html( $row->client_status );
					$frequency = esc_html( $row->frequency );
					$category = esc_html( $row->category ?? '' ); // カテゴリーフィールドを追加
					// 受注書作成用のデータを保持
					$order_customer_name = $company_name;
					$order_user_name = $user_name;
				}
			} else {
				// 追加モードの場合は全ての変数を空で初期化
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				}
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
				$closing_day = '末日';
				$payment_month = '翌月';
				$payment_day = '末日';
				$payment_method = '';
				$tax_category = '';
				$memo = '';
				$client_status = '対象'; // デフォルト値を設定
				$category = ''; // カテゴリーフィールドを追加
				$order_customer_name = '';
				$order_user_name = '';
			}

			// カテゴリーフィールド用の値を設定
			$category_value = isset( $category ) ? $category : '';

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
					'pattern' => '\\d*',
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
					'default' => '末日',
				),
				'支払月' => array(
					'type' => 'select',
					'name' => 'payment_month',
					'options' => array( '今月', '翌月', '翌々月', 'その他' ),
					'default' => '翌月',
				),
				'支払日' => array(
					'type' => 'select',
					'name' => 'payment_day',
					'options' => array( '即日', '5日', '10日', '15日', '20日', '25日', '末日' ),
					'default' => '末日',
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
					'options' => array( '内税', '外税' ),
					'default' => '内税',
				),
				'カテゴリー' => array(
					'type' => 'text',
					'name' => 'category',
				), // カテゴリーフィールド（valueは動的に設定）
				'対象｜対象外' => array(
					'type' => 'select',
					'name' => 'client_status',
					'options' => array( '対象', '対象外' ),
					'default' => '対象',
				),
				'メモ' => array(
					'type' => 'textarea',
					'name' => 'memo',
				),
			);

			$data_forms = ''; // フォームのHTMLコードを格納する変数を初期化
			$data_title = ''; // タイトルのHTMLコードを格納する変数を初期化
			$div_end = ''; // 終了タグを格納する変数を初期化

			// URL パラメータからのメッセージ表示処理を追加
			$session_message = '';
			if ( isset( $_GET['message'] ) ) {
				echo '<script>
            document.addEventListener("DOMContentLoaded", function() {
                const messageType = "' . esc_js( $_GET['message'] ) . '";
                switch (messageType) {
                    case "updated":
                        showSuccessNotification("' . esc_js( __( '更新しました。', 'ktpwp' ) ) . '");
                        break;
                    case "added":
                        showSuccessNotification("' . esc_js( __( '新しい顧客を追加しました。', 'ktpwp' ) ) . '");
                        break;
                    case "deleted":
                        showSuccessNotification("' . esc_js( __( '削除しました。', 'ktpwp' ) ) . '");
                        break;
                    case "found":
                        showInfoNotification("' . esc_js( __( '検索結果を表示しています。', 'ktpwp' ) ) . '");
                        break;
                    case "not_found":
                        showWarningNotification("' . esc_js( __( '該当する顧客が見つかりませんでした。', 'ktpwp' ) ) . '");
                        break;
                }
            });
            </script>';
			}

			// セッションメッセージの確認と表示
			if ( ! session_id() ) {
				ktpwp_safe_session_start();
			}
			if ( isset( $_SESSION['ktp_search_message'] ) ) {
				$message_id = 'ktp-message-' . uniqid();
				$session_message = '<div id="' . $message_id . '" class="ktp-message" style="
                padding: 15px 20px;
                background: linear-gradient(135deg, #e8f5e8 0%, #f0f8f0 100%);
                border-radius: 6px;
                margin: 10px 0;
                color: #333333;
                font-weight: 500;
                box-shadow: 0 3px 10px rgba(0,0,0,0.08);
                display: flex;
                align-items: center;
                font-size: 14px;
                max-width: 90%;
            ">
            <span style="margin-right: 10px; color: #ff6b8b; font-size: 18px;" class="material-symbols-outlined">info</span>'
                . esc_html( $_SESSION['ktp_search_message'] ) . '</div>';
				unset( $_SESSION['ktp_search_message'] ); // メッセージを表示後に削除
			}

			// controllerブロックを必ず先頭に追加
			$controller_html = '<div class="controller" style="display: flex; justify-content: space-between; align-items: center;">';

			// 左側：ボタン群（注文履歴と顧客一覧ボタンを削除）
			$controller_html .= '<div style="display: flex; gap: 5px;">';

			// 現在の顧客IDを取得（後で使用するため）
			$current_client_id = 0;
			$cookie_name = 'ktp_' . $name . '_id';
			if ( isset( $_GET['data_id'] ) ) {
				$current_client_id = filter_input( INPUT_GET, 'data_id', FILTER_SANITIZE_NUMBER_INT );
			} elseif ( isset( $_COOKIE[ $cookie_name ] ) ) {
				$current_client_id = filter_input( INPUT_COOKIE, $cookie_name, FILTER_SANITIZE_NUMBER_INT );
			} else {
				// 最後のIDを取得
				// $wpdb と $table_name がこのスコープで利用可能である必要がある
				if ( isset( $wpdb, $table_name ) ) {
							$query = "SELECT id FROM {$table_name} ORDER BY id DESC LIMIT 1";
							$last_id_row = $wpdb->get_row( $query );
							$current_client_id = $last_id_row ? $last_id_row->id : 0;
				}
			}
			$current_client_id = (int) $current_client_id;

			// 受注書作成用に現在の顧客IDから最新のデータを取得する
			$current_customer_name = '';
			$current_user_name = '';
			$current_client_status = '';
			if ( $current_client_id > 0 ) {
				$current_client_data_query = $wpdb->prepare( "SELECT company_name, name, client_status FROM {$table_name} WHERE id = %d", $current_client_id );
				$current_client_data = $wpdb->get_row( $current_client_data_query );
				if ( $current_client_data ) {
					$current_customer_name = esc_html( $current_client_data->company_name );
					$current_user_name = esc_html( $current_client_data->name );
					$current_client_status = esc_html( $current_client_data->client_status );
				}

				// デバッグ情報を追加
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( "KTPWP Debug - Order Button Control: client_id={$current_client_id}, client_status={$current_client_status}, query={$current_client_data_query}" );
				}
			}

			// 受注書作成ボタン（左端に配置）
			$controller_html .= '<form method="post" action="" id="create-order-form" style="display:inline-block;">';
			$controller_html .= wp_nonce_field( 'ktp_client_action', 'ktp_client_nonce', true, false );
			$controller_html .= '<input type="hidden" name="tab_name" value="order">';
			$controller_html .= '<input type="hidden" name="from_client" value="1">';
			$customer_name_to_use = ! empty( $current_customer_name ) ? $current_customer_name : $order_customer_name;
			$user_name_to_use = ! empty( $current_user_name ) ? $current_user_name : $order_user_name;
			$controller_html .= '<input type="hidden" name="customer_name" value="' . esc_attr( $customer_name_to_use ) . '">';
			$controller_html .= '<input type="hidden" name="user_name" value="' . esc_attr( $user_name_to_use ) . '">';
			$controller_html .= '<input type="hidden" id="client-id-input" name="client_id" value="' . esc_attr( $current_client_id ) . '">';
			$controller_html .= '<div data-client-status="' . esc_attr( $current_client_status ) . '" style="display:none;"></div>';
			$is_data_empty = empty( $post_row ) && empty( $data_id );
			$is_client_excluded = ( $current_client_status === '対象外' );
			$should_disable = $is_data_empty || $is_client_excluded;
			$disabled_attr = $should_disable ? 'disabled' : '';
			$button_title = $is_client_excluded ? '受注書作成（対象外顧客のため無効）' : '受注書作成';
			$controller_html .= '<button type="submit" id="createOrderButton" class="create-order-btn" ' . $disabled_attr . ' title="' . esc_attr( $button_title ) . '"><span class="material-symbols-outlined" aria-label="作成">create</span><span class="btn-label">受注書作成</span></button>';
			$controller_html .= '</form>';

			// 請求書発行ボタンを追加
			$controller_html .= '<button id="invoiceButton" title="請求書発行"><span class="material-symbols-outlined" aria-label="請求書">receipt_long</span><span class="btn-label">請求書発行</span></button>';

			// 請求書発行ポップアップ
			$controller_html .= '<div id="ktp-invoice-preview-popup" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:9999;">';
			$controller_html .= '<div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);background:white;padding:20px;border-radius:8px;width:90%;max-width:800px;max-height:80vh;display:flex;flex-direction:column;">';

			// ヘッダー部分（固定）
			$controller_html .= '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;border-bottom:1px solid #ddd;padding-bottom:10px;">';
			$controller_html .= '<h3 style="margin:0;color:#333;">請求書プレビュー</h3>';
			$controller_html .= '<button type="button" id="ktp-invoice-preview-close" style="background: none; color: #333; border: none; cursor: pointer; font-size: 28px; padding: 0; line-height: 1;">×</button>';
			$controller_html .= '</div>';

			// コンテンツ部分（スクロール可能）
			$controller_html .= '<div id="invoiceList" style="flex:1;overflow-y:scroll;padding-right:10px;padding:50px;">';
			$controller_html .= '<div style="text-align:center;color:#888;">読み込み中...</div>';
			$controller_html .= '</div>';

			$controller_html .= '</div>';
			$controller_html .= '</div>';

			// デザイン設定から奇数偶数カラーを取得
			$design_options = get_option( 'ktp_design_settings', array() );
			$odd_row_color = isset( $design_options['odd_row_color'] ) ? $design_options['odd_row_color'] : '#E7EEFD';
			$even_row_color = isset( $design_options['even_row_color'] ) ? $design_options['even_row_color'] : '#FFFFFF';

			$controller_html .= '</div>'; // 左側のボタン群終了

			// 右側：印刷ボタン（プレビューは廃止）
			$controller_html .= '<div style="display: flex; gap: 5px;">';
			$controller_html .= '<button onclick="printContent()" title="印刷する" style="padding: 8px 12px; font-size: 12px; background: #fff; border: 1px solid #ddd; border-radius: 4px; cursor: pointer; transition: all 0.2s ease;">'
            . '<span class="material-symbols-outlined" aria-label="印刷" style="font-size: 18px; color: #333;">print</span>'
            . '</button>';

			$controller_html .= '</div>'; // 右側のボタン群終了
			$controller_html .= '</div>'; // controller終了

			// 空のフォームを表示(追加モードの場合)
			if ( $action === 'istmode' ) {

					// デバッグ: 追加モード実行時のアクション値確認
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				}

				// 追加モードではデータベース挿入は行わず、フォーム表示のみ
				// $data_id = $wpdb->insert_id; // この行を削除

				// 詳細表示部分の開始
				$data_title = '<div class="data_detail_box"><div class="data_detail_title">' . esc_html__( '■ 顧客追加中', 'ktpwp' ) . '</div>';

				// 追加モード用のフォーム開始
				$data_forms .= '<form method="post" action="">';
				// nonceフィールド追加
				$data_forms .= wp_nonce_field( 'ktp_client_action', 'ktp_client_nonce', true, false );

				// デバッグ: フォーム生成開始をログ出力
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				}

				// 空のフォームフィールドを生成
				foreach ( $fields as $label => $field ) {
					// カテゴリーフィールドの特別処理
					if ( $field['name'] === 'category' ) {
						$value = ( $action === 'istmode' ) ? '' : ( isset( $category ) ? $category : '' );
					} else {
						// 追加モード（istmode）では常に空の値を設定
						$value = ( $action === 'istmode' ) ? '' : ( isset( ${$field['name']} ) ? ${$field['name']} : '' );
					}

					// デバッグ: istmode時のフィールド値をログ出力
					if ( $action === 'istmode' ) {
						if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						}
					}

					$pattern = isset( $field['pattern'] ) ? " pattern=\"{$field['pattern']}\"" : '';
					$required = isset( $field['required'] ) && $field['required'] ? ' required' : '';
					$fieldName = $field['name'];
					$placeholder = isset( $field['placeholder'] ) ? ' placeholder="' . esc_attr__( $field['placeholder'], 'ktpwp' ) . '"' : '';
					$label_i18n = esc_html__( $label, 'ktpwp' );
					if ( $field['type'] === 'textarea' ) {
						$fieldId = 'ktp-client-' . preg_replace( '/[^a-zA-Z0-9_-]/', '', $fieldName );
						$data_forms .= "<div class=\"form-group\"><label for=\"{$fieldId}\">{$label_i18n}：</label> <textarea id=\"{$fieldId}\" name=\"{$fieldName}\"{$pattern}{$required}>" . esc_textarea( $value ) . '</textarea></div>';
					} elseif ( $field['type'] === 'select' ) {
						$options = '';
						foreach ( $field['options'] as $option ) {
							// 追加モードではデフォルト値を選択、更新モードでは現在の値を選択
							if ( $action === 'istmode' ) {
								// 追加モードの場合、デフォルト値があれば選択
								$selected = ( isset( $field['default'] ) && $field['default'] === $option ) ? ' selected' : '';
							} else {
								// 更新モードの場合、現在の値を選択
								$selected = ( $value === $option ) ? ' selected' : '';
							}
							$options .= '<option value="' . esc_attr( $option ) . "\"{$selected}>" . esc_html__( $option, 'ktpwp' ) . '</option>';
						}
						$fieldId = 'ktp-client-' . preg_replace( '/[^a-zA-Z0-9_-]/', '', $fieldName );
						$data_forms .= "<div class=\"form-group\"><label for=\"{$fieldId}\">{$label_i18n}：</label> <select id=\"{$fieldId}\" name=\"{$fieldName}\"{$required}>{$options}</select></div>";
					} else {
						$fieldId = 'ktp-client-' . preg_replace( '/[^a-zA-Z0-9_-]/', '', $fieldName );
						$generated_html = "<div class=\"form-group\"><label for=\"{$fieldId}\">{$label_i18n}：</label> <input id=\"{$fieldId}\" type=\"{$field['type']}\" name=\"{$fieldName}\" value=\"" . esc_attr( $value ) . "\"{$pattern}{$required}{$placeholder}></div>";
						$data_forms .= $generated_html;

						// デバッグ: 生成されたHTMLをログ出力（istmodeの場合のみ）
						if ( $action === 'istmode' ) {
						}
					}
				}

				// ボタン群
				$data_forms .= "<div class='button'>";

				if ( $action === 'istmode' ) {
					// 追加実行ボタン（同じフォーム内）
					$data_forms .= '<input type="hidden" name="query_post" value="insert">'
						. '<input type="hidden" name="data_id" value="">'
						. '<button type="submit" name="send_post" title="' . esc_attr__( '追加実行', 'ktpwp' ) . '" class="insert-submit-btn">'
						. '<span class="material-symbols-outlined">select_check_box</span>'
						. esc_html__( '追加実行', 'ktpwp' ) . '</button>';

					// キャンセルボタン（独立したフォーム）
					$data_forms .= '</form>'; // 追加フォーム終了
					$data_forms .= '<form method="post" action="" style="display:inline-block;margin-left:10px;">';
					$data_forms .= wp_nonce_field( 'ktp_client_action', 'ktp_client_nonce', true, false );
					$data_forms .= '<input type="hidden" name="query_post" value="update">';
					$data_forms .= '<button type="submit" title="' . esc_attr__( 'キャンセル', 'ktpwp' ) . '" style="background-color: #666 !important; margin-left: 10px;">'
						. '<span class="material-symbols-outlined">disabled_by_default</span>'
						. esc_html__( 'キャンセル', 'ktpwp' ) . '</button>';
					$data_forms .= '</form>'; // キャンセルフォーム終了
				}
				$data_forms .= '</div>'; // button div終了

				// data_detail_box を閉じる
				$data_forms .= '</div>';
			}

			// 空のフォームを表示(検索モードの場合)
			elseif ( $action === 'srcmode' ) {

				// デバッグ: 検索モード実行時のアクション値確認

				$data_title = <<<END
            <div class="data_detail_box search-mode">
                <div class="data_detail_title">■ 顧客の詳細（検索モード）</div>
            END;

				// 検索モード用のフォーム
				$data_forms = '<div class="search-mode-form ktpwp-search-form" style="background-color: #f8f9fa !important; border: 2px solid #0073aa !important; border-radius: 8px !important; padding: 20px !important; margin: 10px 0 !important; box-shadow: 0 2px 8px rgba(0, 115, 170, 0.1) !important;">';
				$data_forms .= '<form method="post" action="">';
				$data_forms .= wp_nonce_field( 'ktp_client_action', 'ktp_client_nonce', true, false );

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
                    box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08) !important;
                    display: flex !important;
                                       align-items: center !important;
                    font-size: 14px !important;
                ">
                <span style="margin-right: 10px !important; color: #ff6b8b !important; font-size: 18px !important;" class="material-symbols-outlined">search_off</span>
                検索結果が見つかりませんでした。別のキーワードをお試しください。
                </div>';
				}

				// ボタンを横並びにするためのラップクラスを追加
				$data_forms .= '<div class="button-group" style="display: flex; gap: 10px; margin-top: 15px !important; justify-content: flex-end !important;">';

				// 検索実行ボタン
				$data_forms .= '<input type="hidden" name="query_post" value="search">';
				$data_forms .= '<button type="submit" name="send_post" title="検索実行" class="search-submit-btn" style="background-color: #0073aa !important; color: white !important; border: none !important; padding: 10px 20px !important; cursor: pointer !important; border-radius: 5px !important; display: flex !important; align-items: center !important; gap: 5px !important; font-size: 14px !important; font-weight: 500 !important; transition: all 0.3s ease !important;">';
				$data_forms .= '<span class="material-symbols-outlined" style="font-size: 18px !important;">search</span>';
				$data_forms .= '検索実行';
				$data_forms .= '</button>';

				$data_forms .= '</form>'; // 検索フォームの閉じタグ

				// 検索モードのキャンセルボタン（独立したフォーム）
				$data_forms .= '<form method="post" action="" style="margin: 0 !important;">';
				$data_forms .= wp_nonce_field( 'ktp_client_action', 'ktp_client_nonce', true, false );
				$data_forms .= '<input type="hidden" name="query_post" value="update">';
				$data_forms .= '<button type="submit" name="send_post" title="キャンセル" style="background-color: #666 !important; color: white !important; border: none !important; padding: 10px 20px !important; cursor: pointer !important; border-radius: 5px !important; display: flex !important; align-items: center !important; gap: 5px !important; font-size: 14px !important; font-weight: 500 !important; transition: all 0.3s ease !important;">';

				$data_forms .= '<span class="material-symbols-outlined" style="font-size: 18px !important;">disabled_by_default</span>';
				$data_forms .= 'キャンセル';
				$data_forms .= '</button>';
				$data_forms .= '</form>';

				$data_forms .= '</div>'; // ボタンラップクラスの閉じタグ
				$data_forms .= '</div>'; // search-mode-formの閉じタグ
				// data_detail_box を閉じる
				$data_forms .= '</div>';
			}

			// 追加・検索 以外なら更新フォームを表示
			elseif ( $action !== 'srcmode' && $action !== 'istmode' && $action !== 'search' ) { // searchも除外

				// Simple postal code auto-fill functionality
				$data_forms .= <<<END
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                var postalCode = document.querySelector('input[name="postal_code"]');
                var prefecture = document.querySelector('input[name="prefecture"]');
                var city = document.querySelector('input[name="city"]');

                if (postalCode) {
                    postalCode.addEventListener('blur', function() {
                        var zipcode = postalCode.value.replace(/[^0-9]/g, '');
                        if (zipcode.length === 7) {
                            var xhr = new XMLHttpRequest();
                            xhr.open('GET', 'https://zipcloud.ibsnet.co.jp/api/search?zipcode=' + zipcode);
                            xhr.onload = function() {
                                try {
                                    var response = JSON.parse(xhr.responseText);
                                    if (response.results && response.results.length > 0) {
                                        var data = response.results[0];
                                        if (prefecture) prefecture.value = data.address1;
                                        if (city) city.value = data.address2 + data.address3;
                                    }
                                } catch (error) {
                                    console.error('郵便番号検索エラー:', error);
                                }
                            };
                            xhr.send();
                        }
                    });
                }
            });
            </script>
            END;

				// cookieに保存されたIDを取得
				$cookie_name = 'ktp_' . $name . '_id';
				if ( isset( $_GET['data_id'] ) ) {
					$data_id = filter_input( INPUT_GET, 'data_id', FILTER_SANITIZE_NUMBER_INT );
				} elseif ( isset( $_COOKIE[ $cookie_name ] ) ) {
					$data_id = filter_input( INPUT_COOKIE, $cookie_name, FILTER_SANITIZE_NUMBER_INT );
				} else {
					$data_id = $last_id_row ? $last_id_row->id : null;
				}

				// ボタン群HTMLの準備
				$button_group_html = '<div class="button-group" style="display: flex; gap: 8px; margin-left: auto;">';

				// 削除ボタン
				$form_action_url = add_query_arg(array('tab_name' => $name), $base_page_url);
				$button_group_html .= '<form method="post" action="' . esc_url( $form_action_url ) . '" style="margin: 0;">';
				$button_group_html .= wp_nonce_field( 'ktp_client_action', 'ktp_client_nonce', true, false );
				$button_group_html .= '<input type="hidden" name="data_id" value="' . esc_attr( $data_id ) . '">';
				$button_group_html .= '<input type="hidden" name="query_post" value="delete">';
				$button_group_html .= '<input type="hidden" name="delete_type" value="soft">';
				$button_group_html .= '<button type="submit" name="send_post" title="' . esc_attr__( '削除（無効化）する', 'ktpwp' ) . '" onclick="return confirm(\"' . esc_js( __( 'この顧客を削除（無効化）しますか？\nデータは残りますが、表示ラベルが「対象外」に変更されます。', 'ktpwp' ) ) . '\")" class="button-style delete-submit-btn">';
				$button_group_html .= '<span class="material-symbols-outlined">delete</span>';
				$button_group_html .= '</button>';
				$button_group_html .= '</form>';

				// 追加モードボタン
				$add_action = 'istmode';
				$next_data_id = $data_id + 1;
				$form_action_url = add_query_arg(array('tab_name' => $name), $base_page_url);
				$button_group_html .= '<form method="post" action="' . esc_url( $form_action_url ) . '" style="margin: 0;">';
				$button_group_html .= wp_nonce_field( 'ktp_client_action', 'ktp_client_nonce', true, false );
				$button_group_html .= '<input type="hidden" name="data_id" value="">';
				$button_group_html .= '<input type="hidden" name="query_post" value="' . esc_attr( $add_action ) . '">';
				$button_group_html .= '<input type="hidden" name="data_id" value="' . esc_attr( $next_data_id ) . '">';
				$button_group_html .= '<button type="submit" name="send_post" title="' . esc_attr__( '追加する', 'ktpwp' ) . '" class="button-style add-submit-btn">';
				$button_group_html .= '<span class="material-symbols-outlined">add</span>';
				$button_group_html .= '</button>';
				$button_group_html .= '</form>';

				// 検索モードボタン
				$search_action = 'srcmode';
				$form_action_url = add_query_arg(array('tab_name' => $name), $base_page_url);
				$button_group_html .= '<form method="post" action="' . esc_url( $form_action_url ) . '" style="margin: 0;">';
				$button_group_html .= wp_nonce_field( 'ktp_client_action', 'ktp_client_nonce', true, false );
				$button_group_html .= '<input type="hidden" name="query_post" value="' . esc_attr( $search_action ) . '">';
				$button_group_html .= '<button type="submit" name="send_post" title="' . esc_attr__( '検索する', 'ktpwp' ) . '" class="button-style search-mode-btn">';
				$button_group_html .= '<span class="material-symbols-outlined">search</span>';
				$button_group_html .= '</button>';
				$button_group_html .= '</form>';

				$button_group_html .= '</div>'; // ボタングループ終了

				// 表題にボタングループを含める
				$id_display = (!empty($data_id) && $data_id !== '0' && $data_id !== 0) ? '（ ID: ' . esc_html( $data_id ) . ' ）' : '';
				$data_title = '<div class="data_detail_box"><div class="data_detail_title" style="display: flex; align-items: center; justify-content: space-between;">
            <div>■ 顧客の詳細' . $id_display . '</div>' . $button_group_html . '</div>';

				// メイン更新フォーム
				$form_action_url = add_query_arg(array('tab_name' => $name), $base_page_url);
				$data_forms .= '<form method="post" action="' . esc_url( $form_action_url ) . '">';
				$data_forms .= wp_nonce_field( 'ktp_client_action', 'ktp_client_nonce', true, false );

				// 基本情報フィールド（メールまで）
				$basic_fields = array( 'company_name', 'user_name', 'email' );
				foreach ( $basic_fields as $field_name ) {
					$field = $fields[ $field_name === 'company_name' ? '会社名' : ( $field_name === 'user_name' ? '名前' : 'メール' ) ];
					$value = $action === 'update' ? ( isset( ${$field_name} ) ? ${$field_name} : '' ) : '';

					$pattern = isset( $field['pattern'] ) ? ' pattern="' . esc_attr( $field['pattern'] ) . '"' : '';
					$required = isset( $field['required'] ) && $field['required'] ? ' required' : '';
					$placeholder = isset( $field['placeholder'] ) ? ' placeholder="' . esc_attr( $field['placeholder'] ) . '"' : '';

					$fieldId = 'ktp-client-' . preg_replace( '/[^a-zA-Z0-9_-]/', '', $field['name'] );
					$data_forms .= '<div class="form-group"><label for="' . $fieldId . '">' . esc_html( $field_name === 'company_name' ? '会社名' : ( $field_name === 'user_name' ? '名前' : 'メール' ) ) . '：</label> <input id="' . $fieldId . '" type="' . esc_attr( $field['type'] ) . '" name="' . esc_attr( $field['name'] ) . '" value="' . esc_attr( $value ) . '"' . $pattern . $required . $placeholder . '></div>';
				}

				// 部署設定セクション（シンプルデザイン）
				$data_forms .= '<div style="margin: 20px 0; padding: 15px; background: #f8f9fa; border-radius: 6px;">';
				$data_forms .= '<h4 style="margin: 0 0 15px 0; color: #333; font-size: 14px; font-weight: normal;">部署設定（複数の部署や担当者がある場合は設定してください）</h4>';

				// 部署追加フォーム（ラベルなし、シンプルデザイン）
				$data_forms .= '<div style="display: flex; gap: 10px; align-items: end; margin-bottom: 15px;">';
				$data_forms .= '<div style="flex: 1;"><input type="text" name="department_name" placeholder="部署名" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 12px;"></div>';
				$data_forms .= '<div style="flex: 1;"><input type="text" name="contact_person" placeholder="担当者名" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 12px;"></div>';
				$data_forms .= '<div style="flex: 1;"><input type="email" name="department_email" placeholder="メールアドレス" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 12px;"></div>';
				$data_forms .= '<div style="flex: 0 0 auto;"><button type="button" onclick="addDepartment()" style="padding: 8px 15px; background: #007cba; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 12px;">追加</button></div>';
				$data_forms .= '</div>';

				// 部署リスト表示
				$data_forms .= '<div id="department-list" style="margin-top: 15px;">';
				if ( $action === 'update' && ! empty( $data_id ) ) {
					// 既存の部署を取得して表示
					if ( class_exists( 'KTPWP_Department_Manager' ) ) {
						$departments = KTPWP_Department_Manager::get_departments_by_client( $data_id );
						if ( ! empty( $departments ) ) {
							$data_forms .= '<table style="width: 100%; border-collapse: collapse; font-size: 12px; border: 1px solid #ddd;">';
							$data_forms .= '<thead><tr style="background: #f5f5f5;"><th style="padding: 8px; border: 1px solid #ddd; text-align: center; width: 30px;">✔</th><th style="padding: 8px; border: 1px solid #ddd; text-align: left;">部署名</th><th style="padding: 8px; border: 1px solid #ddd; text-align: left;">担当者名</th><th style="padding: 8px; border: 1px solid #ddd; text-align: left;">メールアドレス</th><th style="padding: 8px; border: 1px solid #ddd; text-align: center; width: 80px;">操作</th></tr></thead>';
							$data_forms .= '<tbody>';
							// 現在選択されている部署IDを取得
							$selected_department_id = $wpdb->get_var(
                                $wpdb->prepare(
                                    "SELECT selected_department_id FROM `{$table_name}` WHERE id = %d",
                                    $data_id
                                )
                            );

							// selected_department_idがNULLまたは空の場合は選択なし状態
							if ( empty( $selected_department_id ) ) {
								$selected_department_id = null;
							}

							if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
								error_log( "KTPWP Client Tab: 顧客ID {$data_id} の選択された部署ID: " . ( $selected_department_id ?: 'NULL' ) );
								error_log( 'KTPWP Client Tab: 部署数: ' . count( $departments ) );
								error_log( 'KTPWP Client Tab: ページリロード時の状態確認完了' );
							}

							foreach ( $departments as $dept ) {
								// 部署IDと選択された部署IDを比較してチェック状態を決定
								// selected_department_idがNULLの場合は選択されていない
								$is_checked = ( ! empty( $selected_department_id ) && $dept->id == $selected_department_id );
								$checked = $is_checked ? ' checked' : '';

								if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
									error_log( "KTPWP Client Tab: 部署ID {$dept->id} のチェック状態: " . ( $is_checked ? 'true' : 'false' ) );
								}

								$data_forms .= '<tr>';
								$data_forms .= '<td style="padding: 8px; border: 1px solid #ddd; text-align: center;"><input type="checkbox" name="department_selection[]" value="' . $dept->id . '" id="dept_' . $dept->id . '"' . $checked . ' class="department-checkbox" data-department-id="' . $dept->id . '" onchange="updateDepartmentSelection(' . $dept->id . ', this.checked)"></td>';
								$data_forms .= '<td style="padding: 8px; border: 1px solid #ddd;">' . esc_html( $dept->department_name ) . '</td>';
								$data_forms .= '<td style="padding: 8px; border: 1px solid #ddd;">' . esc_html( $dept->contact_person ) . '</td>';
								$data_forms .= '<td style="padding: 8px; border: 1px solid #ddd;">' . esc_html( $dept->email ) . '</td>';
								$data_forms .= '<td style="padding: 8px; border: 1px solid #ddd; text-align: center;"><button type="button" onclick="deleteDepartment(' . $dept->id . ')" style="padding: 4px 8px; background: #dc3545; color: white; border: none; border-radius: 3px; cursor: pointer; font-size: 11px;">削除</button></td>';
								$data_forms .= '</tr>';
							}
							$data_forms .= '</tbody></table>';

							// 選択された部署の情報を表示（シンプルデザイン）
							$data_forms .= '<div id="selected-department-info" style="margin-top: 15px; padding: 10px; background: #f8f9fa; border-radius: 4px;">';
							$data_forms .= '<h5 style="margin: 0 0 10px 0; color: #333; font-size: 13px; font-weight: normal;">選択された部署</h5>';

							// 選択された部署の情報を表示
							$selected_departments = array();
							if ( ! empty( $selected_department_id ) ) {
								$selected_departments = array_filter(
                                    $departments,
                                    function ( $dept ) use ( $selected_department_id ) {
                                        return $dept->id == $selected_department_id;
                                    }
                                );
							}

							if ( ! empty( $selected_departments ) ) {
								foreach ( $selected_departments as $selected_dept ) {
									$data_forms .= '<div class="selected-dept-detail" style="margin-bottom: 10px; padding: 8px; background: white; border-radius: 3px; font-size: 12px; color: #666;">';
									$data_forms .= '<strong>部署名:</strong> ' . esc_html( $selected_dept->department_name ) . '<br>';
									$data_forms .= '<strong>担当者名:</strong> ' . esc_html( $selected_dept->contact_person ) . '<br>';
									$data_forms .= '<strong>メールアドレス:</strong> ' . esc_html( $selected_dept->email );
									$data_forms .= '</div>';
								}
							} else {
								$data_forms .= '<div class="selected-dept-detail" style="font-size: 12px; color: #666;">部署を選択してください</div>';
							}
							$data_forms .= '</div>';
						} else {
							$data_forms .= '<p style="color: #666; font-size: 12px; margin: 0;">部署が登録されていません。</p>';
						}
					}
				} else {
					$data_forms .= '<p style="color: #666; font-size: 12px; margin: 0;">顧客を保存後に部署を追加できます。</p>';
				}
				$data_forms .= '</div>';
				$data_forms .= '</div>';

				// 残りのフィールド
				$remaining_fields = array( 'url', 'representative_name', 'phone', 'postal_code', 'prefecture', 'city', 'address', 'building', 'closing_day', 'payment_month', 'payment_day', 'payment_method', 'tax_category', 'category', 'client_status', 'memo' );
				foreach ( $remaining_fields as $field_name ) {
					$field_key = '';
					switch ( $field_name ) {
						case 'url':
									$field_key = 'URL';
			                break;
						case 'representative_name':
									$field_key = '代表者名';
			                break;
						case 'phone':
									$field_key = '電話番号';
			                break;
						case 'postal_code':
									$field_key = '郵便番号';
			                break;
						case 'prefecture':
									$field_key = '都道府県';
			                break;
						case 'city':
									$field_key = '市区町村';
			                break;
						case 'address':
									$field_key = '番地';
			                break;
						case 'building':
									$field_key = '建物名';
			                break;
						case 'closing_day':
									$field_key = '締め日';
			                break;
						case 'payment_month':
									$field_key = '支払月';
			                break;
						case 'payment_day':
									$field_key = '支払日';
			                break;
						case 'payment_method':
									$field_key = '支払方法';
			                break;
						case 'tax_category':
									$field_key = '税区分';
			                break;
						case 'category':
									$field_key = 'カテゴリー';
			                break;
						case 'client_status':
									$field_key = '対象｜対象外';
			                break;
						case 'memo':
									$field_key = 'メモ';
			                break;
					}

					$field = $fields[ $field_key ];

					// カテゴリーフィールドの特別処理
					if ( $field['name'] === 'category' ) {
						$value = isset( $category ) ? $category : '';
					} else {
						$value = $action === 'update' ? ( isset( ${$field['name']} ) ? ${$field['name']} : '' ) : '';
					}

					$pattern = isset( $field['pattern'] ) ? ' pattern="' . esc_attr( $field['pattern'] ) . '"' : '';
					$required = isset( $field['required'] ) && $field['required'] ? ' required' : '';
					$placeholder = isset( $field['placeholder'] ) ? ' placeholder="' . esc_attr( $field['placeholder'] ) . '"' : '';

					if ( $field['type'] === 'textarea' ) {
						$fieldId = 'ktp-client-' . preg_replace( '/[^a-zA-Z0-9_-]/', '', $field['name'] );
						$data_forms .= '<div class="form-group"><label for="' . $fieldId . '">' . esc_html( $field_key ) . '：</label> <textarea id="' . $fieldId . '" name="' . esc_attr( $field['name'] ) . '"' . $pattern . $required . '>' . esc_textarea( $value ) . '</textarea></div>';
					} elseif ( $field['type'] === 'select' ) {
						$options = '';
						foreach ( $field['options'] as $option ) {
							$selected = $value === $option ? ' selected' : '';
							$options .= '<option value="' . esc_attr( $option ) . '"' . $selected . '>' . esc_html( $option ) . '</option>';
						}
						$fieldId = 'ktp-client-' . preg_replace( '/[^a-zA-Z0-9_-]/', '', $field['name'] );
						$data_forms .= '<div class="form-group"><label for="' . $fieldId . '">' . esc_html( $field_key ) . '：</label> <select id="' . $fieldId . '" name="' . esc_attr( $field['name'] ) . '"' . $required . '>' . $options . '</select></div>';
					} else {
						$fieldId = 'ktp-client-' . preg_replace( '/[^a-zA-Z0-9_-]/', '', $field['name'] );
						$data_forms .= '<div class="form-group"><label for="' . $fieldId . '">' . esc_html( $field_key ) . '：</label> <input id="' . $fieldId . '" type="' . esc_attr( $field['type'] ) . '" name="' . esc_attr( $field['name'] ) . '" value="' . esc_attr( $value ) . '"' . $pattern . $required . $placeholder . '></div>';
					}
				}
				$data_forms .= '<input type="hidden" name="query_post" value="update">';
				$data_forms .= '<input type="hidden" name="data_id" value="' . esc_attr( $data_id ) . '">';
				$data_forms .= '<div class="button">';
				$data_forms .= '<button type="submit" name="send_post" title="' . esc_attr__( '更新する', 'ktpwp' ) . '" class="update-submit-btn"><span class="material-symbols-outlined">cached</span></button>';
				$data_forms .= '</div>';
				$data_forms .= '</form>';

				// 部署管理用JavaScript
				$data_forms .= '<script>
        function addDepartment() {
            var departmentName = document.querySelector("input[name=\'department_name\']").value;
            var contactPerson = document.querySelector("input[name=\'contact_person\']").value;
            var email = document.querySelector("input[name=\'department_email\']").value;
            var clientId = ' . esc_js( $data_id ) . ';
            
            // 部署設定は空欄でも可
            if (!contactPerson || !email) {
                alert("担当者名とメールアドレスを入力してください。");
                return;
            }
            
            var formData = new FormData();
            formData.append("action", "ktp_add_department");
            formData.append("client_id", clientId);
            formData.append("department_name", departmentName);
            formData.append("contact_person", contactPerson);
            formData.append("email", email);
            formData.append("nonce", "' . wp_create_nonce( 'ktp_department_nonce' ) . '");
            
            fetch(ajaxurl, {
                method: "POST",
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert("部署の追加に失敗しました: " + data.data);
                }
            })
            .catch(error => {
                console.error("Error:", error);
                alert("部署の追加に失敗しました。");
            });
        }
        
        function deleteDepartment(departmentId) {
            if (!confirm("この部署を削除しますか？")) {
                return;
            }
            
            var formData = new FormData();
            formData.append("action", "ktp_delete_department");
            formData.append("department_id", departmentId);
            formData.append("nonce", "' . wp_create_nonce( 'ktp_department_nonce' ) . '");
            
            fetch(ajaxurl, {
                method: "POST",
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert("部署の削除に失敗しました: " + data.data);
                }
            })
            .catch(error => {
                console.error("Error:", error);
                alert("部署の削除に失敗しました。");
            });
        }
        
        function updateDepartmentSelection(departmentId, isSelected) {
            console.log("updateDepartmentSelection called - departmentId:", departmentId, "isSelected:", isSelected);
            console.log("ajaxurl available:", typeof ajaxurl !== "undefined" ? ajaxurl : "undefined");
            
            // 重複実行を防ぐため、処理中の場合はスキップ
            if (window.departmentUpdateInProgress) {
                console.log("Department update already in progress, skipping...");
                return;
            }
            
            window.departmentUpdateInProgress = true;
            
            // 他の部署のチェックを外す（単一選択のため）
            if (isSelected) {
                var allCheckboxes = document.querySelectorAll(\'input[name="department_selection[]"]\');
                allCheckboxes.forEach(function(checkbox) {
                    if (checkbox.dataset.departmentId != departmentId) {
                        checkbox.checked = false;
                    }
                });
            }
            // 選択解除時は他の部署のチェックはそのまま（自動選択しない）
            
            // AJAXで部署選択状態を更新
            var formData = new FormData();
            formData.append("action", "ktp_update_department_selection");
            formData.append("department_id", departmentId);
            formData.append("is_selected", isSelected);
            formData.append("nonce", "' . wp_create_nonce( 'ktp_department_nonce' ) . '");
            
            console.log("Sending AJAX request for department selection update");
            console.log("FormData contents:");
            console.log("action:", formData.get("action"));
            console.log("department_id:", formData.get("department_id"));
            console.log("is_selected:", formData.get("is_selected"));
            console.log("nonce:", formData.get("nonce"));
            
            fetch(ajaxurl, {
                method: "POST",
                body: formData
            })
            .then(response => {
                console.log("Response status:", response.status);
                console.log("Response ok:", response.ok);
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    console.log("Department selection update successful");
                    // 選択された部署情報を更新
                    updateSelectedDepartmentInfo();
                } else {
                    console.log("Department selection update failed:", data.data);
                    alert("部署選択状態の更新に失敗しました: " + data.data);
                    // チェックボックスの状態を元に戻す
                    var checkbox = document.querySelector(\'input[data-department-id="\' + departmentId + \'"]\');
                    if (checkbox) {
                        checkbox.checked = !isSelected;
                    }
                }
            })
            .catch(error => {
                console.error("Fetch error:", error);
                console.error("Error name:", error.name);
                console.error("Error message:", error.message);
                alert("部署選択状態の更新に失敗しました。");
                // チェックボックスの状態を元に戻す
                var checkbox = document.querySelector(\'input[data-department-id="\' + departmentId + \'"]\');
                if (checkbox) {
                    checkbox.checked = !isSelected;
                }
            })
            .finally(function() {
                // 処理完了後、フラグをリセット
                window.departmentUpdateInProgress = false;
            });
        }
        
        function updateSelectedDepartmentInfo() {
            // 選択された部署の情報を表示エリアに反映
            var selectedCheckboxes = document.querySelectorAll(\'input[name="department_selection[]"]:checked\');
            var infoDiv = document.getElementById("selected-department-info");
            
            console.log("updateSelectedDepartmentInfo called - selected checkboxes:", selectedCheckboxes.length);
            
            if (infoDiv) {
                // 既存の詳細表示要素を全て削除
                var existingDetails = infoDiv.querySelectorAll(".selected-dept-detail");
                existingDetails.forEach(function(detail) {
                    detail.remove();
                });
                
                if (selectedCheckboxes.length > 0) {
                    selectedCheckboxes.forEach(function(checkbox) {
                        var row = checkbox.closest("tr");
                        var departmentName = row.cells[1].textContent;
                        var contactPerson = row.cells[2].textContent;
                        var email = row.cells[3].textContent;
                        
                        var detailDiv = document.createElement("div");
                        detailDiv.className = "selected-dept-detail";
                        detailDiv.style.marginBottom = "10px";
                        detailDiv.style.padding = "8px";
                        detailDiv.style.background = "white";
                        detailDiv.style.borderRadius = "3px";
                        detailDiv.style.fontSize = "12px";
                        detailDiv.style.color = "#666";
                        
                        detailDiv.innerHTML = \'<strong>部署名:</strong> \' + departmentName + \'<br>\';
                        detailDiv.innerHTML += \'<strong>担当者名:</strong> \' + contactPerson + \'<br>\';
                        detailDiv.innerHTML += \'<strong>メールアドレス:</strong> \' + email;
                        
                        infoDiv.appendChild(detailDiv);
                    });
                    console.log("Updated with selected department info");
                } else {
                    // 選択された部署がない場合は「選択なし」を表示
                    var noSelectionDiv = document.createElement("div");
                    noSelectionDiv.className = "selected-dept-detail";
                    noSelectionDiv.style.fontSize = "12px";
                    noSelectionDiv.style.color = "#666";
                    noSelectionDiv.textContent = "部署を選択してください";
                    infoDiv.appendChild(noSelectionDiv);
                    console.log("Updated with no selection message");
                }
            } else {
                console.log("selected-department-info div not found");
            }
        }
        </script>';

				// ボタン群は既にタイトル内に配置済み

				// data_detail_box を閉じる
				$data_forms .= '</div>';
			}

			$data_forms .= '</div>'; // フォームを囲む<div>タグの終了

			// 詳細表示部分の終了タグを設定（全モード共通）
			// if (empty($div_end)) {
			// </div> <!-- data_contentsの終了 -->
			// END;
			// }

			// -----------------------------
			// テンプレート印刷
			// -----------------------------

			// Print_Classのパスを指定
			require_once __DIR__ . '/class-print.php';

			// 変数の初期化（未定義の場合に備えて）
			if ( ! isset( $company_name ) ) {
				$company_name = '';
			}
			if ( ! isset( $user_name ) ) {
				$user_name = '';
			}
			if ( ! isset( $email ) ) {
				$email = '';
			}
			if ( ! isset( $url ) ) {
				$url = '';
			}
			if ( ! isset( $representative_name ) ) {
				$representative_name = '';
			}
			if ( ! isset( $phone ) ) {
				$phone = '';
			}
			if ( ! isset( $postal_code ) ) {
				$postal_code = '';
			}
			if ( ! isset( $prefecture ) ) {
				$prefecture = '';
			}
			if ( ! isset( $city ) ) {
				$city = '';
			}
			if ( ! isset( $address ) ) {
				$address = '';
			}
			if ( ! isset( $building ) ) {
				$building = '';
			}
			if ( ! isset( $closing_day ) ) {
				$closing_day = '';
			}
			if ( ! isset( $payment_month ) ) {
				$payment_month = '';
			}
			if ( ! isset( $payment_day ) ) {
				$payment_day = '';
			}
			if ( ! isset( $payment_method ) ) {
				$payment_method = '';
			}
			if ( ! isset( $tax_category ) ) {
				$tax_category = '';
			}
			if ( ! isset( $category ) ) {
				$category = '';
			}
			if ( ! isset( $client_status ) ) {
				$client_status = '';
			}
			if ( ! isset( $memo ) ) {
				$memo = '';
			}

			// 顧客情報のプレビュー用HTMLを生成
			$customer_preview_html = $this->generateCustomerPreviewHTML(
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
					'category' => $category,
					'client_status' => $client_status,
					'memo' => $memo,
                )
            );

			// PHP
			$customer_preview_html = json_encode( $customer_preview_html );   // JSON形式にエンコード

			// Simplified JavaScript - matching Update_Table approach
			$print = <<<END
        <script>
            // var isPreviewOpen = false; // プレビュー機能は廃止

            function printContent() {
                var printContent = $customer_preview_html;
                var printHTML = '<!DOCTYPE html>';
                printHTML += '<html lang="ja">';
                printHTML += '<head>';
                printHTML += '<meta charset="UTF-8">';
                printHTML += '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
                printHTML += '<title>顧客情報印刷</title>';
                printHTML += '<style>';
                printHTML += '* { margin: 0; padding: 0; box-sizing: border-box; }';
                printHTML += 'body { font-family: "Noto Sans JP", "Hiragino Kaku Gothic ProN", "Yu Gothic", Meiryo, sans-serif; font-size: 12px; line-height: 1.4; color: #333; background: white; padding: 20px; }';
                printHTML += '@page { size: A4; margin: 15mm; }';
                printHTML += '@media print { body { margin: 0; padding: 0; background: white; } }';
                printHTML += '@media print { button, .no-print { display: none !important; } }';
                printHTML += '</style>';
                printHTML += '<script>';
                printHTML += 'window.addEventListener("load", function() {';
                printHTML += '  setTimeout(function() {';
                printHTML += '    window.print();';
                printHTML += '  }, 500);';
                printHTML += '});';
                printHTML += 'window.addEventListener("afterprint", function() {';
                printHTML += '  setTimeout(function() {';
                printHTML += '    window.close();';
                printHTML += '  }, 1000);';
                printHTML += '});';
                printHTML += '<\/script>';
                printHTML += '</head>';
                printHTML += '<body>';
                printHTML += printContent;
                printHTML += '</body>';
                printHTML += '</html>';
                
                var printWindow = window.open('', '_blank', 'width=800,height=600,scrollbars=yes,resizable=yes');
                
                if (!printWindow) {
                    alert('ポップアップがブロックされました。ブラウザの設定でポップアップを許可してください。');
                    return;
                }
                
                printWindow.document.open();
                printWindow.document.write(printHTML);
                printWindow.document.close();
                
                console.log('[顧客印刷] 印刷ウィンドウを作成しました。印刷ダイアログが自動表示されます。');

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
            //         previewButton.innerHTML = '<span class="material-symbols-outlined" aria-label="プレビュー" style="font-size: 18px; color: #333;">preview</span>';
            //         previewButton.style.background = '#fff';
            //         previewButton.style.borderColor = '#ddd';
            //         isPreviewOpen = false;
            //     } else {
            //         var printContent = $customer_preview_html;

            //         if (!previewWindow) {
            //             previewWindow = document.createElement('div');
            //             previewWindow.id = 'previewWindow';
            //             previewWindow.style.cssText = 'display:none;position:relative;z-index:100;background:#fff;padding:25px;border:2px solid #ddd;border-radius:8px;margin:15px 0;box-shadow:0 4px 12px rgba(0,0,0,0.1);';

            //             var controllerDiv = document.querySelector('.controller');
            //             if (controllerDiv) {
            //                 controllerDiv.parentNode.insertBefore(previewWindow, controllerDiv.nextSibling);
            //         } else {
            //                 document.querySelector('.box').appendChild(previewWindow);
            //             }
            //         }

            //         // プレビューウィンドウに閉じるボタンを追加
            //         var closeButton = '<div style="text-align: right; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #eee;"><button type="button" onclick="togglePreview()" style="background: none; border: none; font-size: 28px; cursor: pointer; color: #333; padding: 5px; line-height: 1; font-weight: bold;">×</button></div>';
            //         previewWindow.innerHTML = closeButton + printContent;
            //         previewWindow.style.display = 'block';
            //         previewButton.innerHTML = '<span class="material-symbols-outlined" aria-label="閉じる" style="font-size: 18px; color: #d32f2f;">close</span>';
            //         previewButton.style.background = '#ffebee';
            //         previewButton.style.borderColor = '#d32f2f';
            //         isPreviewOpen = true;
            //     }
            // }
        </script>
        END;

			// コンテンツを返す
			// controller, workflow（受注書作成ボタン）を$print直後に追加
			// controller_html, workflow_htmlが重複しないようにcontroller_htmlは1回のみ出力
			// プレビューウィンドウはJavaScriptで動的に作成されるため、HTMLに直接書く必要はなくなった

			// 必要な変数の初期化確認
			if ( ! isset( $search_results_list ) ) {
				$search_results_list = '';
			}
			if ( ! isset( $data_title ) ) {
				$data_title = '';
			}
			if ( ! isset( $data_forms ) ) {
				$data_forms = '';
			}
			if ( ! isset( $div_end ) ) {
				$div_end = '';
			}
			// 検索モードでも顧客リストを表示する
			$content = $print . $session_message . $controller_html . $data_list . $data_title . $data_forms . $search_results_list . $div_end;
			return $content;
		}

		/**
		 * ページネーションを生成するメソッド
		 *
		 * @param int      $current_page 現在のページ
		 * @param int      $total_pages 総ページ数
		 * @param int      $query_limit 1ページあたりの件数
		 * @param string   $name テーブル名
		 * @param string   $flg フラグ
		 * @param string   $base_page_url ベースURL
		 * @param int      $total_rows 総データ数
		 * @param string   $view_mode 表示モード
		 * @param int|null $client_id 顧客ID（注文履歴用）
		 * @return string ページネーションHTML
		 */
		private function render_pagination( $current_page, $total_pages, $query_limit, $name, $flg, $base_page_url, $total_rows, $view_mode = '', $client_id = null ) {
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
					'page_start' => 0, // 顧客リストは常に1ページ目を表示
					'page_stage' => 2,
					'flg' => $flg,
					'data_id' => $client_id,
					'order_page' => $current_page - 1,
				);

				// 注文履歴モードの場合
				if ( $view_mode === 'order_history' && $client_id ) {
					$prev_args['view_mode'] = 'order_history';
					$prev_args['data_id'] = $client_id;
					if ( isset( $_GET['order_sort_by'] ) ) {
						$prev_args['order_sort_by'] = $_GET['order_sort_by'];
					}
					if ( isset( $_GET['order_sort_order'] ) ) {
						$prev_args['order_sort_order'] = $_GET['order_sort_order'];
					}
				} else {
					// 通常の顧客リストモード
					if ( isset( $_GET['sort_by'] ) ) {
						$prev_args['sort_by'] = $_GET['sort_by'];
					}
					if ( isset( $_GET['sort_order'] ) ) {
						$prev_args['sort_order'] = $_GET['sort_order'];
					}
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

				// 注文履歴モードの場合
				if ( $view_mode === 'order_history' && $client_id ) {
					$first_args['view_mode'] = 'order_history';
					$first_args['data_id'] = $client_id;
					if ( isset( $_GET['order_sort_by'] ) ) {
						$first_args['order_sort_by'] = $_GET['order_sort_by'];
					}
					if ( isset( $_GET['order_sort_order'] ) ) {
						$first_args['order_sort_order'] = $_GET['order_sort_order'];
					}
				} else {
					// 通常の顧客リストモード
					if ( isset( $_GET['sort_by'] ) ) {
						$first_args['sort_by'] = $_GET['sort_by'];
					}
					if ( isset( $_GET['sort_order'] ) ) {
						$first_args['sort_order'] = $_GET['sort_order'];
					}
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

				// 注文履歴モードの場合
				if ( $view_mode === 'order_history' && $client_id ) {
					$page_args['view_mode'] = 'order_history';
					$page_args['data_id'] = $client_id;
					if ( isset( $_GET['order_sort_by'] ) ) {
						$page_args['order_sort_by'] = $_GET['order_sort_by'];
					}
					if ( isset( $_GET['order_sort_order'] ) ) {
						$page_args['order_sort_order'] = $_GET['order_sort_order'];
					}
				} else {
					// 通常の顧客リストモード
					if ( isset( $_GET['sort_by'] ) ) {
						$page_args['sort_by'] = $_GET['sort_by'];
					}
					if ( isset( $_GET['sort_order'] ) ) {
						$page_args['sort_order'] = $_GET['sort_order'];
					}
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

				// 注文履歴モードの場合
				if ( $view_mode === 'order_history' && $client_id ) {
					$last_args['view_mode'] = 'order_history';
					$last_args['data_id'] = $client_id;
					if ( isset( $_GET['order_sort_by'] ) ) {
						$last_args['order_sort_by'] = $_GET['order_sort_by'];
					}
					if ( isset( $_GET['order_sort_order'] ) ) {
						$last_args['order_sort_order'] = $_GET['order_sort_order'];
					}
				} else {
					// 通常の顧客リストモード
					if ( isset( $_GET['sort_by'] ) ) {
						$last_args['sort_by'] = $_GET['sort_by'];
					}
					if ( isset( $_GET['sort_order'] ) ) {
						$last_args['sort_order'] = $_GET['sort_order'];
					}
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

				// 注文履歴モードの場合
				if ( $view_mode === 'order_history' && $client_id ) {
					$next_args['view_mode'] = 'order_history';
					$next_args['data_id'] = $client_id;
					if ( isset( $_GET['order_sort_by'] ) ) {
						$next_args['order_sort_by'] = $_GET['order_sort_by'];
					}
					if ( isset( $_GET['order_sort_order'] ) ) {
						$next_args['order_sort_order'] = $_GET['order_sort_order'];
					}
				} else {
					// 通常の顧客リストモード
					if ( isset( $_GET['sort_by'] ) ) {
						$next_args['sort_by'] = $_GET['sort_by'];
					}
					if ( isset( $_GET['sort_order'] ) ) {
						$next_args['sort_order'] = $_GET['sort_order'];
					}
				}

				$next_url = esc_url( add_query_arg( $next_args, $base_page_url ) );
				$pagination_html .= "<a href=\"{$next_url}\" style=\"{$button_style}\" {$hover_effect}>›</a>";
			}

			$pagination_html .= '</div>';
			$pagination_html .= '</div>';

			return $pagination_html;
		}

		/**
		 * 注文履歴専用のページネーションデザインをレンダリング
		 *
		 * @param int    $current_page 現在のページ
		 * @param int    $total_pages 総ページ数
		 * @param int    $query_limit 1ページあたりの表示件数
		 * @param string $name テーブル名
		 * @param string $flg フラグ
		 * @param string $base_page_url ベースURL
		 * @param int    $total_rows 総データ数
		 * @param int    $client_id 顧客ID
		 * @return string ページネーションHTML
		 */
		private function render_order_history_pagination( $current_page, $total_pages, $query_limit, $name, $flg, $base_page_url, $total_rows, $client_id ) {
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
					'page_start' => 0, // 顧客リストは常に1ページ目を表示
					'page_stage' => 2,
					'flg' => $flg,
					'data_id' => $client_id,
					'order_page' => $current_page - 1,
				);

				// 現在のソート順を維持
				if ( isset( $_GET['order_sort_by'] ) ) {
					$prev_args['order_sort_by'] = $_GET['order_sort_by'];
				}
				if ( isset( $_GET['order_sort_order'] ) ) {
					$prev_args['order_sort_order'] = $_GET['order_sort_order'];
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
					'data_id' => $client_id,
					'order_page' => 1,
				);

				// 現在のソート順を維持
				if ( isset( $_GET['order_sort_by'] ) ) {
					$first_args['order_sort_by'] = $_GET['order_sort_by'];
				}
				if ( isset( $_GET['order_sort_order'] ) ) {
					$first_args['order_sort_order'] = $_GET['order_sort_order'];
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
					'page_start' => 0, // 顧客リストは常に1ページ目を表示
					'page_stage' => 2,
					'flg' => $flg,
					'data_id' => $client_id,
					'order_page' => $i,
				);

				// 現在のソート順を維持
				if ( isset( $_GET['order_sort_by'] ) ) {
					$page_args['order_sort_by'] = $_GET['order_sort_by'];
				}
				if ( isset( $_GET['order_sort_order'] ) ) {
					$page_args['order_sort_order'] = $_GET['order_sort_order'];
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
					'page_start' => 0, // 顧客リストは常に1ページ目を表示
					'page_stage' => 2,
					'flg' => $flg,
					'data_id' => $client_id,
					'order_page' => $total_pages,
				);

				// 現在のソート順を維持
				if ( isset( $_GET['order_sort_by'] ) ) {
					$last_args['order_sort_by'] = $_GET['order_sort_by'];
				}
				if ( isset( $_GET['order_sort_order'] ) ) {
					$last_args['order_sort_order'] = $_GET['order_sort_order'];
				}

				$last_url = esc_url( add_query_arg( $last_args, $base_page_url ) );
				$pagination_html .= "<a href=\"{$last_url}\" style=\"{$button_style}\" {$hover_effect}>{$total_pages}</a>";
			}

			// 次のページボタン
			if ( $current_page < $total_pages && $total_pages > 1 ) {
				$next_args = array(
					'tab_name' => $name,
					'page_start' => 0, // 顧客リストは常に1ページ目を表示
					'page_stage' => 2,
					'flg' => $flg,
					'data_id' => $client_id,
					'order_page' => $current_page + 1,
				);

				// 現在のソート順を維持
				if ( isset( $_GET['order_sort_by'] ) ) {
					$next_args['order_sort_by'] = $_GET['order_sort_by'];
				}
				if ( isset( $_GET['order_sort_order'] ) ) {
					$next_args['order_sort_order'] = $_GET['order_sort_order'];
				}

				$next_url = esc_url( add_query_arg( $next_args, $base_page_url ) );
				$pagination_html .= "<a href=\"{$next_url}\" style=\"{$button_style}\" {$hover_effect}>›</a>";
			}

			$pagination_html .= '</div>';
			$pagination_html .= '</div>';

			return $pagination_html;
		}

		/**
		 * 顧客情報のプレビュー用HTMLを生成するメソッド
		 *
		 * @param array $customer_data 顧客データ
		 * @return string 顧客情報のプレビューHTML
		 */
		private function generateCustomerPreviewHTML( $customer_data ) {
			$company_name = $customer_data['company_name'] ?? '';
			$name = $customer_data['name'] ?? '';
			$email = $customer_data['email'] ?? '';
			$url = $customer_data['url'] ?? '';
			$representative_name = $customer_data['representative_name'] ?? '';
			$phone = $customer_data['phone'] ?? '';
			$postal_code = $customer_data['postal_code'] ?? '';
			$prefecture = $customer_data['prefecture'] ?? '';
			$city = $customer_data['city'] ?? '';
			$address = $customer_data['address'] ?? '';
			$building = $customer_data['building'] ?? '';
			$closing_day = $customer_data['closing_day'] ?? '';
			$payment_month = $customer_data['payment_month'] ?? '';
			$payment_day = $customer_data['payment_day'] ?? '';
			$payment_method = $customer_data['payment_method'] ?? '';
			$tax_category = $customer_data['tax_category'] ?? '';
			$category = $customer_data['category'] ?? '';
			$client_status = $customer_data['client_status'] ?? '';
			$memo = $customer_data['memo'] ?? '';

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
                <h1 style="color: #333; margin: 0; font-size: 24px;">顧客情報</h1>
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
                    <td style="border: 1px solid #ddd; padding: 12px; font-weight: bold; background-color: #f8f9fa;">カテゴリー</td>
                    <td style="border: 1px solid #ddd; padding: 12px;">' . esc_html( $category ) . '</td>
                </tr>
                <tr>
                    <td style="border: 1px solid #ddd; padding: 12px; font-weight: bold; background-color: #f8f9fa;">対象｜対象外</td>
                    <td style="border: 1px solid #ddd; padding: 12px;">' . esc_html( $client_status ) . '</td>
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
	// class_exists
}
