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
			if ( ktpwp_request_method() !== 'POST' ) {
				return;
			}

			// 職能フォーム（skills_action あり）は ktpwp_skills_nonce、協力会社フォームは ktpwp_supplier_nonce を送る。
			$is_skills_form = ! empty( $_POST['skills_action'] );
			$nonce_field    = $is_skills_form ? 'ktpwp_skills_nonce' : 'ktpwp_supplier_nonce';
			$nonce_action   = $is_skills_form ? 'ktpwp_skills_action' : 'ktpwp_supplier_action';
			if ( ! isset( $_POST[ $nonce_field ] )
				|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ $nonce_field ] ) ), $nonce_action ) ) {
				wp_die( esc_html__( '不正なリクエストです。', 'kantanpro' ) );
			}

			$post_data = map_deep( wp_unslash( $_POST ), 'sanitize_textarea_field' ); // 項目ごとの仕上げは下の sanitize_form_value。
			array_walk_recursive( $post_data, array( 'KTPWP_Request', 'sanitize_form_value' ) );

			if ( $is_skills_form ) {
				// template_redirect で既に処理済みの場合は二重実行しない
				if ( empty( $GLOBALS['ktpwp_supplier_skills_early_done'] ) ) {
					$this->handle_skills_operations( $post_data, false );
				}
				return;
			}

			$this->supplier_data->update_table( $tab_name, $post_data );
		}

		// -----------------------------
		// テーブルの表示
		// -----------------------------

		function View_Table( $name ) {
			// フォームから来た POST（表示モード・検索語）は、このタブの nonce を確認できたときだけ使う。
			// データの変更はここではなく、nonce と権限を確認する保存処理側で行う。
			$ktpwp_form_ok = isset( $_POST['ktpwp_supplier_nonce'] )
				&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ktpwp_supplier_nonce'] ) ), 'ktpwp_supplier_action' );

			// アクセス権限チェック（多層防御。主なゲートは ktpwp.php の kantanAllTab() 側）。
			if ( function_exists( 'ktpwp_current_user_can_access' ) && ! ktpwp_current_user_can_access() ) {
				return '';
			}

			global $wpdb, $wp; // $wp をグローバルに追加

			// ベースURLの構築
			$current_url_path = home_url( $wp->request );
			$base_url_params = array();

			if ( get_queried_object_id() ) {
				$base_url_params['page_id'] = get_queried_object_id();
			}
			if ( KTPWP_Request::has_query( 'page' ) ) {
				$base_url_params['page'] = KTPWP_Request::query( 'page' );
			}
			$base_url_params['tab_name'] = $name;
			$base_page_url = add_query_arg( $base_url_params, $current_url_path );

			// フォームアクション用のベースURL (ページネーションパラメータ等は含めない)
			$form_action_base_url = $base_page_url;


			// URL パラメータからのメッセージ表示処理を追加
			if ( KTPWP_Request::has_query( 'message' ) ) {
				ktpwp_add_inline_notice( 'success', '' . __( '更新しました。', 'kantanpro' ) . '' );
			}

			// $search_results_listの使用前に初期化
			if ( ! isset( $search_results_list ) ) {
				$search_results_list = '';
			}

			// -----------------------------
			// リスト表示
			// -----------------------------

			// テーブル名
			$table_name = $wpdb->prefix . 'ktpwp_' . $name;

			// ソート順の取得（デフォルトはIDの降順）
			$sort_by = 'id';
			$sort_order = class_exists( 'KTPWP_List_Table' ) ? KTPWP_List_Table::default_sort_order() : 'DESC';

			if ( KTPWP_Request::has_query( 'sort_by' ) ) {
				$sort_by = KTPWP_Request::query( 'sort_by' );
				// 安全なカラム名のみ許可（SQLインジェクション対策）
				$allowed_columns = array( 'id', 'company_name', 'name', 'phone', 'frequency', 'time', 'category' );
				if ( ! in_array( $sort_by, $allowed_columns ) ) {
					$sort_by = 'id'; // 不正な値の場合はデフォルトに戻す
				}
			}

			if ( KTPWP_Request::has_query( 'sort_order' ) ) {
				$sort_order = class_exists( 'KTPWP_List_Table' )
					? KTPWP_List_Table::sanitize_sort_order( KTPWP_Request::query( 'sort_order' ) )
					: 'DESC';
			}

			// リスト表示前に頻度を加算（一覧に反映させる）
			if ( KTPWP_Request::has_query( 'data_id' ) && KTPWP_Request::query_unslashed( 'data_id' ) !== '' && ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX ) && ( ! function_exists( 'ktpwp_should_skip_frequency_on_view' ) || ! ktpwp_should_skip_frequency_on_view() ) ) {
				$view_record_id = KTPWP_Request::query_number( 'data_id' );
				if ( $view_record_id > 0 && function_exists( 'ktpwp_increment_record_frequency_on_view' ) ) {
					ktpwp_increment_record_frequency_on_view( 'supplier', (int) $view_record_id );
				}
			}

			// 現在のページのURLを生成（動的パーマリンク取得）
			$base_page_url = KTPWP_Main::get_current_page_base_url();
			$print_all       = KTPWP_Request::has_query( 'print_all' ) && (string) KTPWP_Request::query_unslashed( 'print_all' ) !== '' && (string) KTPWP_Request::query_unslashed( 'print_all' ) !== '0';

			// 検索結果が複数ある場合：リダイレクト後のGETでダイアログにリストを表示（顧客タブと同様）
			// HTMLは hidden div に置きスクリプトで読み取る方式で、JSON埋め込みによる構文エラーを防ぐ
			if ( KTPWP_Request::has_query( 'multiple_results' ) && KTPWP_Request::query_unslashed( 'multiple_results' ) === '1' && ! empty( KTPWP_Request::query( 'search_query' ) ) ) {
				$search_query_multiple = KTPWP_Request::query( 'search_query' );
				$like_pattern = '%' . $wpdb->esc_like( $search_query_multiple ) . '%';
				$multi_results = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT * FROM {$table_name} WHERE (COALESCE(search_field,'') LIKE %s OR company_name LIKE %s OR name LIKE %s) ORDER BY id DESC",
						$like_pattern,
						$like_pattern,
						$like_pattern
					)
				);
				if ( ! empty( $multi_results ) ) {
					$multi_results_id = 'ktp-supplier-multi-results-' . wp_rand( 10000, 99999 );
					$search_results_html = "<div class='data_contents'><div class='search_list_box'><div class='data_list_title'>■ " . esc_html__( '検索結果が複数あります！', 'kantanpro' ) . "</div><ul>";
					foreach ( $multi_results as $row ) {
						$id = esc_html( (string) $row->id );
						$company_name = esc_html( isset( $row->company_name ) ? $row->company_name : '' );
						$disp_name = esc_html( isset( $row->name ) ? $row->name : '' );
						$category = esc_html( isset( $row->category ) ? $row->category : '' );
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
						$search_results_html .= "<li style='text-align:left;'><a href='" . $link_url . "' style='text-align:left;'>ID：" . $id . " 会社名：" . $company_name . " 名前：" . $disp_name . ( $category !== '' ? " カテゴリー：" . $category : '' ) . "</a></li>";
					}
					$search_results_html .= '</ul></div></div>';
					$close_redirect_base = home_url( esc_url_raw( wp_unslash( isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '/' ) ) );
					$close_redirect_base = remove_query_arg( array( 'multiple_results', 'search_query', 'message' ), $close_redirect_base );
					$close_redirect_url = esc_url(
						add_query_arg(
							array(
								'tab_name' => $name,
								'query_post' => 'srcmode',
							),
							$close_redirect_base
						)
					);
					$search_results_list = KTPWP_Ui_Generator::render_multi_results_popup( $multi_results_id, $close_redirect_url, $search_results_html );
				}
			}

            // -----------------------------
			// ページネーションリンク
			// -----------------------------

			// 表示範囲
			// 一般設定から表示件数を取得（設定クラスが利用可能な場合）
			if ( class_exists( 'KTPWP_Settings' ) ) {
				$query_limit = KTPWP_Settings::get_work_list_range();
			} else {
				$query_limit = 20; // フォールバック値
			}

			// リスト表示部分の開始 - ktpwp_data_contentsを開始
			// HEREDOC は使わない（wp.org ガイドライン）
			$results_h = '<div class="ktpwp_data_contents">'
				. '<div class="ktpwp_data_list_box">'
				. '<div id="ktp-suppliers-print-list-area">'
				. '<div id="ktp-suppliers-print-list-only">'
				. '<div class="data_list_title">■ ' . esc_html__( '協力会社リスト', 'kantanpro' ) . '</div>';

			// スタート位置を決める
			$page_stage = KTPWP_Request::has_query( 'page_stage' ) ? KTPWP_Request::query( 'page_stage' ) : '';
			$page_start = KTPWP_Request::has_query( 'page_start' ) ? absint( KTPWP_Request::query( 'page_start' ) ) : 0;
			$flg = KTPWP_Request::has_query( 'flg' ) ? KTPWP_Request::query( 'flg' ) : '';
			if ( $page_stage == '' ) {
				$page_start = 0;
			}
			
			// 負の値を防ぐ安全対策
			$page_start = max( 0, intval( $page_start ) );

			$list_search_where = '';
			$list_search_args  = array();
			if ( class_exists( 'KTPWP_Tab_Search_UI' ) ) {
				$list_search_keyword = KTPWP_Tab_Search_UI::get_instance()->get_keyword();
				if ( $list_search_keyword !== '' ) {
					list( $list_search_where, $list_search_args ) = KTPWP_Tab_Search_UI::get_instance()->master_list_search_clause( $table_name, $list_search_keyword, 'supplier' );
				}
			}

			// 全データ数を取得
			// $list_search_where はプレースホルダー（%i / %s）だけで組み立てた条件。値は $list_search_args で渡す
			$total_rows = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM %i WHERE 1=1{$list_search_where}", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- 上記のとおりプレースホルダーのみ。
					array_merge( array( $table_name ), $list_search_args )
				)
			);
			$total_pages = ceil( $total_rows / $query_limit );

			// 現在のページ番号を計算
			$current_page = floor( $page_start / $query_limit ) + 1;

			if ( ! class_exists( 'KTPWP_List_Table' ) ) {
				require_once __DIR__ . '/class-ktpwp-list-table.php';
			}

			$list_header = '';
			$list_footer = '';
			$results     = array();

			// データを取得（選択されたソート順で）
			$sort_column          = esc_sql( $sort_by );
			$sort_column_prepared = str_replace( '%', '%%', $sort_column );
			$sort_direction       = $sort_order === 'ASC' ? 'ASC' : 'DESC';
			if ( $print_all ) {
				$page_start   = 0;
				$current_page = 1;
				$total_pages  = 1;
				$query        = $wpdb->prepare(
					"SELECT * FROM {$table_name} WHERE 1=1{$list_search_where} ORDER BY {$sort_column_prepared} {$sort_direction}",
					$list_search_args
				);
			} else {
				$query = $wpdb->prepare(
					"SELECT * FROM {$table_name} WHERE 1=1{$list_search_where} ORDER BY {$sort_column_prepared} {$sort_direction} LIMIT %d, %d",
					array_merge( $list_search_args, array( $page_start, $query_limit ) )
				);
			}
			$post_row = $wpdb->get_results( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $query は $wpdb->prepare() 済み。
			if ( $post_row ) {
				$list_header = KTPWP_List_Table::open(
					array(
						array(
							'class'    => 'col-id',
							'label'    => __( 'ID', 'kantanpro' ),
							'sort_key' => 'id',
						),
						array(
							'class'    => 'col-company',
							'label'    => __( '会社名', 'kantanpro' ),
							'sort_key' => 'company_name',
						),
						array(
							'class'    => 'col-contact',
							'label'    => __( '担当者', 'kantanpro' ),
							'sort_key' => 'name',
						),
						array(
							'class'    => 'col-phone',
							'label'    => __( '電話番号', 'kantanpro' ),
							'sort_key' => 'phone',
						),
						array(
							'class'    => 'col-frequency no-print',
							'label'    => __( '頻度', 'kantanpro' ),
							'sort_key' => 'frequency',
						),
					),
					array(
						'base_url'      => $base_page_url,
						'sort_by'       => $sort_by,
						'sort_order'    => $sort_order,
						'preserve_args' => KTPWP_List_Table::preserved_query_args(
							array( 'query_post', 'send_post' )
						),
					),
					'ktp-list-table--party'
				);

				foreach ( $post_row as $row ) {
					  $id = esc_html( $row->id );
					  $time = esc_html( $row->time );
					  $company_name = esc_html( $row->company_name );
					  $user_name = esc_html( $row->name );
					  $email = esc_html( $row->email );
					  $url = isset( $row->url ) ? esc_html( $row->url ) : '';
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
					  $cookie_name = 'ktpwp_' . $name . '_id';

					  $query_args = array(
						  'tab_name' => $name,
						  'data_id' => $id,
						  'page_start' => $page_start,
						  'page_stage' => $page_stage,
                    // 'flg' => $flg, // 必要に応じて維持
					  );
					  // 現在のソート順を維持
					  if ( KTPWP_Request::has_query( 'sort_by' ) ) {
						  $query_args['sort_by'] = rawurlencode( KTPWP_Request::query( 'sort_by' ) );
					  }
					  if ( KTPWP_Request::has_query( 'sort_order' ) ) {
						  $query_args['sort_order'] = rawurlencode( KTPWP_Request::query( 'sort_order' ) );
					  }

					  $item_link_url = esc_url( add_query_arg( $query_args, $base_page_url ) );
					  $frequency_title = esc_attr__( 'アクセス頻度（クリックされた回数）', 'kantanpro' );
					  $display_phone    = $phone !== '' ? $phone : '—';
					  $row_attrs       = KTPWP_List_Table::row_nav_attrs( $item_link_url, $cookie_name, (int) $row->id );
					  $results[]       = '<tr' . $row_attrs . '>'
						. '<td class="col-id">' . $id . '</td>'
						. '<td class="col-company">' . $company_name . '</td>'
						. '<td class="col-contact">' . $user_name . '</td>'
						. '<td class="col-phone">' . esc_html( $display_phone ) . '</td>'
						. '<td class="col-frequency no-print" title="' . $frequency_title . '">' . $frequency . '</td>'
						. '</tr>';

				}
				$list_footer   = KTPWP_List_Table::close();
				$query_max_num = $wpdb->num_rows;
			} else {
				$results[] = '<div class="ktpwp_data_list_item" style="padding: 15px 20px; background: linear-gradient(135deg, #e3f2fd 0%, #fce4ec 100%); border-radius: 8px; margin: 18px 0; color: #333; font-weight: 600; box-shadow: 0 3px 12px rgba(0,0,0,0.07); display: flex; align-items: center; font-size: 15px; gap: 10px;">'
				. '<span class="material-symbols-outlined" aria-label="データ作成">add_circle</span>'
				. '<span style="font-size: 1em; font-weight: 600;">[＋]ボタンを押してデーターを作成してください</span>'
				. '<span style="margin-left: 18px; font-size: 13px; color: #888;">データがまだ登録されていません</span>'
				. '</div>';
			}

			// 統一されたページネーションデザインを使用
			$results_f = '</div></div>';
			if ( ! $print_all ) {
				$results_f .= $this->render_pagination( $current_page, $total_pages, $query_limit, $name, $flg, $base_page_url, $total_rows );
			}

			// -----------------------------
			// 詳細表示(GET) - ID取得処理を先に実行
			// -----------------------------

			// 現在表示中の詳細
			$cookie_name = 'ktpwp_' . $name . '_id';
			$query_id = null;

			// アクションを取得（POST優先、なければGET、なければ'update'）
			$action = 'update';
			if ( $ktpwp_form_ok && isset( $_POST['query_post'] ) ) {
				$action = sanitize_text_field( wp_unslash( $_POST['query_post'] ) );
			} elseif ( KTPWP_Request::has_query( 'query_post' ) ) {
				$action = KTPWP_Request::query( 'query_post' );
			}

			// 安全性確保: GETリクエストの場合は危険なアクションを実行しない（srcmode/istmode は表示用のため許可）
			if ( ktpwp_request_method() === 'GET' && in_array( $action, array( 'delete', 'insert', 'search', 'duplicate' ) ) ) {
				$action = 'update';
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				}
			}

			// 詳細表示ID取得処理（ページネーションメッセージ用）
			if ( $action !== 'istmode' ) {
				if ( KTPWP_Request::has_query( 'data_id' ) && KTPWP_Request::query_unslashed( 'data_id' ) !== '' ) {
					$query_id = KTPWP_Request::query_number( 'data_id' );
					// クッキーも即時更新（追加直後やURL遷移時に常に最新IDを保持）。ショートコード表示後はヘッダー済みの場合あり
					if ( ! headers_sent() ) {
						setcookie( $cookie_name, (string) $query_id, time() + ( 86400 * 30 ), '/' );
					}
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
				$post_row = $wpdb->get_results( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $query は $wpdb->prepare() 済み。
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
					$results[] = '<div class="ktpwp_data_list_item" style="padding: 15px 20px; background: linear-gradient(135deg, #ffeef1 0%, #ffeff2 100%); border-radius: 6px; margin: 15px 0; color: #333333; font-weight: 500; box-shadow: 0 3px 10px rgba(0, 0, 0, 0.08); display: flex; align-items: center; font-size: 14px;">'
						. '<span style="margin-right: 10px; color: #ff6b8b; font-size: 18px;" class="material-symbols-outlined">search_off</span>'
						. esc_html__( 'データーがありません。', 'kantanpro' )
						. '</div>';
				}
				foreach ( $post_row as $row ) {
					$data_id = esc_html( $row->id );
					$time = esc_html( $row->time );
					$company_name = esc_html( $row->company_name );
					$user_name = esc_html( $row->name );
					$email = esc_html( $row->email );
					$url = isset( $row->url ) ? esc_html( $row->url ) : '';
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
				$skills_sort_by = KTPWP_Request::has_query( 'skills_sort_by' ) ? KTPWP_Request::query( 'skills_sort_by' ) : 'frequency';
				$skills_sort_order = KTPWP_Request::has_query( 'skills_sort_order' )
					? ( class_exists( 'KTPWP_List_Table' )
						? KTPWP_List_Table::sanitize_sort_order( KTPWP_Request::query( 'skills_sort_order' ) )
						: 'DESC' )
					: ( class_exists( 'KTPWP_List_Table' ) ? KTPWP_List_Table::default_sort_order() : 'DESC' );

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
				foreach ( KTPWP_Request::query_all() as $key => $value ) {
					if ( ! in_array( $key, array( 'skills_sort_by', 'skills_sort_order' ) ) ) {
						$skills_sort_dropdown .= '<input type="hidden" name="' . esc_attr( sanitize_text_field( wp_unslash( (string) $key ) ) ) . '" value="' . esc_attr( is_array( $value ) ? '' : sanitize_text_field( wp_unslash( (string) $value ) ) ) . '">';
					}
				}

				$skills_sort_dropdown .=
					'<select id="' . esc_attr( 'ktp-' . $name . '-skills-sort-select' ) . '" name="skills_sort_by" style="margin-right:5px;">' .
					'<option value="id" ' . selected( $skills_sort_by, 'id', false ) . '>' . esc_html__( 'ID', 'kantanpro' ) . '</option>' .
					'<option value="product_name" ' . selected( $skills_sort_by, 'product_name', false ) . '>' . esc_html__( '商品名', 'kantanpro' ) . '</option>' .
					'<option value="frequency" ' . selected( $skills_sort_by, 'frequency', false ) . '>' . esc_html__( '頻度', 'kantanpro' ) . '</option>' .
					'</select>' .
					'<select id="' . esc_attr( 'ktp-' . $name . '-skills-sort-order' ) . '" name="skills_sort_order">' .
					'<option value="DESC" ' . selected( $skills_sort_order, 'DESC', false ) . '>' . esc_html__( '降順', 'kantanpro' ) . '</option>' .
					'<option value="ASC" ' . selected( $skills_sort_order, 'ASC', false ) . '>' . esc_html__( '昇順', 'kantanpro' ) . '</option>' .
					'</select>' .
					'<button type="submit" style="margin-left:5px;padding:4px 8px;background:#f0f0f0;border:1px solid #ccc;border-radius:3px;cursor:pointer;" title="' . esc_attr__( '適用', 'kantanpro' ) . '">' .
					'<span class="material-symbols-outlined" style="font-size:18px;line-height:18px;vertical-align:middle;">check</span>' .
					'</button>' .
					'</form></div>';

				// 職能データの件数に応じてタイトルを変更
				if ( $skills_count > 0 ) {
					// 職能データが1件以上ある場合：IDを表示
					$current_id_message = '<div class="data_skill_list_title" style="display: flex; align-items: center;">'
						. '<div style="display: flex; align-items: center; gap: 8px;">'
						/* translators: 1: 協力会社名, 2: ID */
						. esc_html( sprintf( __( '■ %1$s（ID: %2$s）の商品', 'kantanpro' ), $display_company_name, $query_id ) )
						. '</div>'
						. $skills_sort_dropdown
						. '</div>';
				} else {
					// 職能データが0件の場合：IDを非表示
					$current_id_message = '<div class="data_skill_list_title" style="display: flex; align-items: center;">'
						. '<div style="display: flex; align-items: center; gap: 8px;">'
						/* translators: %s: 協力会社名 */
						. esc_html( sprintf( __( '■ %sの商品', 'kantanpro' ), $display_company_name ) )
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
			$data_list = $results_h . $list_header . implode( $results ) . $list_footer . $results_f . $current_id_message;

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
				'電話番号' => array(
					'type' => 'text',
					'name' => 'phone',
					'pattern' => '\d*',
					'placeholder' => '半角数字 ハイフン不要',
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

            // 税制モード: 消費税なし/税列非表示 の場合、税区分と適格請求書番号をUIから隠す
            if ( class_exists( 'KTPWP_Tax_Policy' ) && ( KTPWP_Tax_Policy::is_abolished() || KTPWP_Tax_Policy::hide_tax_columns() ) ) {
				$fields = array_filter(
					$fields,
					function ( $field ) {
                        if ( ! isset( $field['name'] ) ) {
                            return true;
                        }
                        return ! in_array( $field['name'], array( 'tax_category', 'qualified_invoice_number' ), true );
					}
				);
			}

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
                          '<div class="data_detail_title">■ ' . esc_html__( '協力会社追加中', 'kantanpro' ) . '</div>';
				// 郵便番号から住所を自動入力するためのJavaScriptコードを追加（日本郵政のAPIを利用）
				// 郵便番号→住所の自動入力は js/ktp-supplier-postal.js に切り出した。
				// （インライン <script> と HEREDOC はどちらも wp.org のガイドライン違反）
				// 住所検索は管理者が明示的に有効化した日本郵便の公式API（サーバー側で呼び出し）
				// 経由でのみ行う。既定では外部サービスへ通信しないため、未設定時はスクリプトを
				// 読み込まない。
				if ( class_exists( 'KTPWP_JapanPost_Address_API' ) && KTPWP_JapanPost_Address_API::is_enabled() ) {
					wp_enqueue_script(
						'ktpwp-supplier-postal',
						plugins_url( 'js/ktp-supplier-postal.js', KTPWP_PLUGIN_FILE ),
						array(),
						KANTANPRO_PLUGIN_VERSION,
						true
					);
					wp_localize_script(
						'ktpwp-supplier-postal',
						'ktpwpSupplierPostal',
						array(
							'ajaxUrl' => admin_url( 'admin-ajax.php' ),
							'nonce'   => wp_create_nonce( 'ktpwp_ajax_nonce' ),
						)
					);
				}
				$data_forms = '';
				// 空のフォームフィールドを生成
				$data_forms .= '<form method="post" action="' . esc_url( $form_action_base_url ) . '">';
				if ( function_exists( 'wp_nonce_field' ) ) {
					$data_forms .= wp_nonce_field( 'ktpwp_supplier_action', 'ktpwp_supplier_nonce', true, false ); }
				// ブラウザ拡張（Grammarly等）や翻訳による textarea フリーズ回避属性
				$textarea_guard_attrs = ' spellcheck="false" autocorrect="off" autocapitalize="off" autocomplete="off" translate="no" data-gramm="false" data-gramm_editor="false" data-enable-grammarly="false"';
				foreach ( $fields as $label => $field ) {
					$value = '';
					$pattern = isset( $field['pattern'] ) ? " pattern=\"{$field['pattern']}\"" : '';
					$required = isset( $field['required'] ) && $field['required'] ? ' required' : '';
					$fieldName = $field['name'];
					$placeholder = isset( $field['placeholder'] ) ? " placeholder=\"{$field['placeholder']}\"" : '';
					if ( $field['type'] === 'textarea' ) {
						$data_forms .= "<div class=\"form-group\"><label>{$label}：</label> <textarea name=\"{$fieldName}\"{$pattern}{$required}{$textarea_guard_attrs}>{$value}</textarea></div>";
					} elseif ( $field['type'] === 'select' ) {
						$options = '';
						foreach ( $field['options'] as $option ) {
							$selected = $value === $option ? ' selected' : '';
							$options .= "<option value=\"{$option}\"{$selected}>{$option}</option>";
						}
						$default = isset( $field['default'] ) ? $field['default'] : '';
						$data_forms .= "<div class=\"form-group\"><label>{$label}：</label> <select name=\"{$fieldName}\"{$required}><option value=\"\">{$default}</option>{$options}</select></div>";
					} else {
						$field_id = 'ktp-supplier-' . preg_replace( '/[^a-zA-Z0-9_-]/', '', $fieldName );
						$contact_html = class_exists( 'KTPWP_External_Url' )
							? KTPWP_External_Url::maybe_render_form_group(
								$fieldName,
								$label,
								$field_id,
								$field,
								(string) $value,
								$pattern,
								$required,
								$placeholder
							)
							: null;
						if ( $contact_html !== null ) {
							$data_forms .= $contact_html;
						} else {
							$data_forms .= "<div class=\"form-group\"><label for=\"{$field_id}\">{$label}：</label> <input id=\"{$field_id}\" type=\"{$field['type']}\" name=\"{$fieldName}\" value=\"{$value}\"{$pattern}{$required}{$placeholder}></div>";
						}
					}
				}
				$data_forms .= "<div class='button'>";
				// 追加実行ボタン（顧客タブと同じスタイル）
				$data_forms .= "<input type='hidden' name='query_post' value='insert'>";
				$data_forms .= "<input type='hidden' name='data_id' value=''>";
				$data_forms .= '<button type="submit" name="send_post" title="' . esc_attr__( '追加実行', 'kantanpro' ) . '" class="insert-submit-btn">'
					. '<span class="material-symbols-outlined">select_check_box</span>'
					. esc_html__( '追加実行', 'kantanpro' ) . '</button>';
				$data_forms .= '</form>';

				// キャンセルボタン（独立したフォーム・顧客タブと同じスタイル）
				$data_forms .= '<form method="post" action="' . esc_url( $form_action_base_url ) . '" style="display:inline-block;margin-left:10px;">';
				if ( function_exists( 'wp_nonce_field' ) ) {
					$data_forms .= wp_nonce_field( 'ktpwp_supplier_action', 'ktpwp_supplier_nonce', true, false );
				}
				$data_forms .= '<input type="hidden" name="query_post" value="update">';
				$data_forms .= '<button type="submit" title="' . esc_attr__( 'キャンセル', 'kantanpro' ) . '" style="background-color: #666 !important; margin-left: 10px;">'
					. '<span class="material-symbols-outlined">disabled_by_default</span>'
					. esc_html__( 'キャンセル', 'kantanpro' ) . '</button>';
				$data_forms .= '</form>';
				$data_forms .= '</div>';
			}

			// 空のフォームを表示(検索モードの場合)
			elseif ( $action === 'srcmode' ) {
				// 表題
				$data_title = '<div class="data_detail_box search-mode">' .
                          '<div class="data_detail_title">■ ' . esc_html__( '協力会社の詳細（検索モード）', 'kantanpro' ) . '</div>';

				// 検索モード用のフォーム（顧客タブと同じ構造・装飾に）
				$data_forms = '<div class="search-mode-form ktpwp-search-form" style="background-color: #f8f9fa !important; border: 2px solid #0073aa !important; border-radius: 8px !important; padding: 20px !important; margin: 10px 0 !important; box-shadow: 0 2px 8px rgba(0, 115, 170, 0.1) !important;">';
				$data_forms .= '<div class="notice notice-info ktp-search-mode-notice" style="margin: 10px 0; padding: 10px; background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; border-radius: 4px; display: flex; align-items: center;">';
				$data_forms .= '<span style="margin-right: 10px; color: #17a2b8; font-size: 18px;" class="material-symbols-outlined" aria-hidden="true">search</span>';
				$data_forms .= esc_html__( '検索モードです。条件を入力して検索してください。', 'kantanpro' );
				$data_forms .= '</div>';
				$data_forms .= '<form method="post" action="' . esc_url( $form_action_base_url ) . '">';
				$data_forms .= function_exists( 'wp_nonce_field' ) ? wp_nonce_field( 'ktpwp_supplier_action', 'ktpwp_supplier_nonce', true, false ) : '';
				$data_forms .= '<input type="hidden" name="tab_name" value="' . esc_attr( $name ) . '">';
				// 検索クエリの値を取得（POSTが優先、次にGET）
				$search_query_value = '';
				if ( $ktpwp_form_ok && isset( $_POST['search_query'] ) ) {
					$search_query_value = esc_attr( sanitize_text_field( wp_unslash( $_POST['search_query'] ) ) );
				} elseif ( KTPWP_Request::has_query( 'search_query' ) ) {
					$search_query_value = esc_attr( sanitize_text_field( urldecode( KTPWP_Request::query_unslashed( 'search_query' ) ) ) );
				}
				$data_forms .= '<div class="form-group" style="margin-bottom: 15px !important;">';
				$data_forms .= '<input type="text" name="search_query" placeholder="' . esc_attr__( 'フリーワード検索', 'kantanpro' ) . '" value="' . $search_query_value . '" style="width: 100% !important; padding: 12px !important; font-size: 16px !important; border: 2px solid #ddd !important; border-radius: 5px !important; box-sizing: border-box !important; transition: border-color 0.3s ease !important;">';
				$data_forms .= '</div>';

				// ボタンを横並びにするためのラップクラスを追加
				$data_forms .= '<div class="button-group" style="display: flex !important; justify-content: flex-end !important; gap: 10px !important; margin-top: 15px !important;">';

				// 検索実行ボタン
				$data_forms .= '<input type="hidden" name="query_post" value="search">';
				$data_forms .= '<button type="submit" name="send_post" title="' . esc_attr__( '検索実行', 'kantanpro' ) . '" style="background-color: #0073aa !important; color: white !important; border: none !important; padding: 10px 20px !important; cursor: pointer !important; border-radius: 5px !important; display: flex !important; align-items: center !important; gap: 5px !important; font-size: 14px !important; font-weight: 500 !important; transition: all 0.3s ease !important;">';
				$data_forms .= '<span class="material-symbols-outlined" style="font-size: 18px !important;">search</span>';
				$data_forms .= esc_html__( '検索実行', 'kantanpro' );
				$data_forms .= '</button>';
				$data_forms .= '</form>';

				// 検索モードのキャンセルボタン（独立したフォーム）
				$data_forms .= '<form method="post" action="' . esc_url( $form_action_base_url ) . '" style="margin: 0 !important;">';
				$data_forms .= function_exists( 'wp_nonce_field' ) ? wp_nonce_field( 'ktpwp_supplier_action', 'ktpwp_supplier_nonce', true, false ) : '';
				$data_forms .= '<input type="hidden" name="query_post" value="update">';
				$data_forms .= '<button type="submit" name="send_post" title="' . esc_attr__( 'キャンセル', 'kantanpro' ) . '" style="background-color: #666 !important; color: white !important; border: none !important; padding: 10px 20px !important; cursor: pointer !important; border-radius: 5px !important; display: flex !important; align-items: center !important; gap: 5px !important; font-size: 14px !important; font-weight: 500 !important; transition: all 0.3s ease !important;">';
				$data_forms .= '<span class="material-symbols-outlined" style="font-size: 18px !important;">disabled_by_default</span>';
				$data_forms .= esc_html__( 'キャンセル', 'kantanpro' );
				$data_forms .= '</button>';
				$data_forms .= '</form>';

				$data_forms .= '</div>'; // ボタンラップクラスの閉じタグ
				// 該当なしメッセージは検索実行・キャンセルボタンの直下に表示（顧客・サービスと同様）
				if ( ( $ktpwp_form_ok && isset( $_POST['query_post'] ) && $_POST['query_post'] === 'search' && empty( $search_results_list ) ) ||
					( KTPWP_Request::has_query( 'no_results' ) && KTPWP_Request::query_unslashed( 'no_results' ) === '1' ) ) {
					$no_results_id = 'no-results-' . uniqid();
					$data_forms .= '<div id="' . esc_attr( $no_results_id ) . '" class="no-results ktp-supplier-no-results" style="
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
                ' . esc_html__( '検索結果が見つかりませんでした。別のキーワードをお試しください。', 'kantanpro' ) . '
                </div>';
				}
				$data_forms .= '</div>'; // search-mode-formの閉じタグ
			}

			// 追加・検索 以外なら更新フォームを表示
			elseif ( $action !== 'srcmode' && $action !== 'istmode' ) {

				// 郵便番号から住所を自動入力するためのJavaScriptコードを追加（日本郵政のAPIを利用）
				// 郵便番号→住所の自動入力は js/ktp-supplier-postal.js に切り出した。
				// （インライン <script> と HEREDOC はどちらも wp.org のガイドライン違反）
				// 住所検索は管理者が明示的に有効化した日本郵便の公式API（サーバー側で呼び出し）
				// 経由でのみ行う。既定では外部サービスへ通信しないため、未設定時はスクリプトを
				// 読み込まない。
				if ( class_exists( 'KTPWP_JapanPost_Address_API' ) && KTPWP_JapanPost_Address_API::is_enabled() ) {
					wp_enqueue_script(
						'ktpwp-supplier-postal',
						plugins_url( 'js/ktp-supplier-postal.js', KTPWP_PLUGIN_FILE ),
						array(),
						KANTANPRO_PLUGIN_VERSION,
						true
					);
					wp_localize_script(
						'ktpwp-supplier-postal',
						'ktpwpSupplierPostal',
						array(
							'ajaxUrl' => admin_url( 'admin-ajax.php' ),
							'nonce'   => wp_create_nonce( 'ktpwp_ajax_nonce' ),
						)
					);
				}
				$data_forms = '';

				// ボタングループHTML生成
				$button_group_html = '<div class="button-group" style="display: flex; gap: 10px; margin-left: auto;">';

				// 削除ボタン
				$form_action_url = add_query_arg(array('tab_name' => $name), $form_action_base_url);
				$button_group_html .= '<form method="post" action="' . esc_url( $form_action_url ) . '" style="margin: 0;">';
				$button_group_html .= wp_nonce_field( 'ktpwp_supplier_action', 'ktpwp_supplier_nonce', true, false );
				$button_group_html .= '<input type="hidden" name="data_id" value="' . esc_attr( $query_id ) . '">';
				$button_group_html .= '<input type="hidden" name="query_post" value="delete">';
				$button_group_html .= '<button type="submit" name="send_post" title="' . esc_attr__( '削除する', 'kantanpro' ) . '" onclick="return confirm(\'' . esc_js( __( '本当に削除しますか？', 'kantanpro' ) ) . '\')" class="button-style delete-submit-btn">';
				$button_group_html .= '<span class="material-symbols-outlined">delete</span>';
				$button_group_html .= '</button>';
				$button_group_html .= '</form>';

				// 追加モードボタン
				$add_action = 'istmode';
				$form_action_url = add_query_arg(array('tab_name' => $name), $form_action_base_url);
				$button_group_html .= '<form method="post" action="' . esc_url( $form_action_url ) . '" style="margin: 0;">';
				$button_group_html .= wp_nonce_field( 'ktpwp_supplier_action', 'ktpwp_supplier_nonce', true, false );
				$button_group_html .= '<input type="hidden" name="data_id" value="">';
				$button_group_html .= '<input type="hidden" name="query_post" value="' . esc_attr( $add_action ) . '">';
				$button_group_html .= '<button type="submit" name="send_post" title="' . esc_attr__( '追加する', 'kantanpro' ) . '" class="button-style add-submit-btn">';
				$button_group_html .= '<span class="material-symbols-outlined">add</span>';
				$button_group_html .= '</button>';
				$button_group_html .= '</form>';

				// 検索モードボタン
				$search_action = 'srcmode';
				$form_action_url = add_query_arg(array('tab_name' => $name), $form_action_base_url);
				$button_group_html .= '<form method="post" action="' . esc_url( $form_action_url ) . '" style="margin: 0;">';
				$button_group_html .= wp_nonce_field( 'ktpwp_supplier_action', 'ktpwp_supplier_nonce', true, false );
				$button_group_html .= '<input type="hidden" name="query_post" value="' . esc_attr( $search_action ) . '">';
				$button_group_html .= '<button type="submit" name="send_post" title="' . esc_attr__( '検索する', 'kantanpro' ) . '" class="button-style search-mode-btn">';
				$button_group_html .= '<span class="material-symbols-outlined">search</span>';
				$button_group_html .= '</button>';
				$button_group_html .= '</form>';

				$button_group_html .= '</div>'; // ボタングループ終了

				// 表題にボタングループを含める
				// デバッグ用：query_idの値を確認
				if (defined('WP_DEBUG') && WP_DEBUG) {
					ktpwp_debug_log('KTPWP Supplier Tab: query_id = ' . var_export($query_id, true));
					ktpwp_debug_log('KTPWP Supplier Tab: query_id type = ' . gettype($query_id));
					ktpwp_debug_log('KTPWP Supplier Tab: id_display condition = ' . (!empty($query_id) && $query_id !== '0' && $query_id !== 0 ? 'true' : 'false'));
				}
				/* translators: %s: ID */
				$id_display = ( empty( $query_id ) || $query_id === '0' || $query_id === 0 ) ? '' : sprintf( __( '（ ID: %s ）', 'kantanpro' ), esc_html( $query_id ) );
				$data_title = '<div class="data_detail_box"><div class="data_detail_title" style="display: flex; align-items: center; justify-content: space-between;">
            <div>' . esc_html__( '■ 協力会社の詳細', 'kantanpro' ) . $id_display . '</div>' . $button_group_html . '</div>';

				// メイン更新フォーム
				$data_forms .= '<form method="post" action="' . esc_url( $form_action_base_url ) . '">';
				if ( function_exists( 'wp_nonce_field' ) ) {
					$data_forms .= wp_nonce_field( 'ktpwp_supplier_action', 'ktpwp_supplier_nonce', true, false );
				}

				// ブラウザ拡張（Grammarly等）や翻訳による textarea フリーズ回避属性
				$textarea_guard_attrs = ' spellcheck="false" autocorrect="off" autocapitalize="off" autocomplete="off" translate="no" data-gramm="false" data-gramm_editor="false" data-enable-grammarly="false"';
				foreach ( $fields as $label => $field ) {
					$value = ( $action === 'istmode' ) ? '' : ( isset( ${$field['name']} ) ? ${$field['name']} : '' );
					$pattern = isset( $field['pattern'] ) ? " pattern=\"{$field['pattern']}\"" : '';
					$required = isset( $field['required'] ) && $field['required'] ? ' required' : '';
					$placeholder = isset( $field['placeholder'] ) ? " placeholder=\"{$field['placeholder']}\"" : '';

					if ( $field['type'] === 'textarea' ) {
						$data_forms .= "<div class=\"form-group\"><label>{$label}：</label> <textarea name=\"{$field['name']}\"{$pattern}{$required}{$textarea_guard_attrs}>{$value}</textarea></div>";
					} elseif ( $field['type'] === 'select' ) {
						$options = '';
						foreach ( $field['options'] as $option ) {
							// 追加モードでは何も選択しない
							$selected = ( $action === 'istmode' ) ? '' : ( $value === $option ? ' selected' : '' );
							$options .= "<option value=\"{$option}\"{$selected}>{$option}</option>";
						}
						$data_forms .= "<div class=\"form-group\"><label>{$label}：</label> <select name=\"{$field['name']}\"{$required}>{$options}</select></div>";
					} else {
						$field_id = 'ktp-supplier-' . preg_replace( '/[^a-zA-Z0-9_-]/', '', $field['name'] );
						$contact_html = class_exists( 'KTPWP_External_Url' )
							? KTPWP_External_Url::maybe_render_form_group(
								$field['name'],
								$label,
								$field_id,
								$field,
								(string) $value,
								$pattern,
								$required,
								$placeholder
							)
							: null;
						if ( $contact_html !== null ) {
							$data_forms .= $contact_html;
						} else {
							$data_forms .= "<div class=\"form-group\"><label for=\"{$field_id}\">{$label}：</label> <input id=\"{$field_id}\" type=\"{$field['type']}\" name=\"{$field['name']}\" value=\"{$value}\"{$pattern}{$required}{$placeholder}></div>";
						}
					}
				}

				// hidden data_id は常に現在表示中のID（$query_id）
				$data_forms .= "<input type=\"hidden\" name=\"data_id\" value=\"{$query_id}\">";
				$data_forms .= '<input type="hidden" name="query_post" value="update">';

				// 検索結果複数時のダイアログ（顧客・サービスと同様にフォーム直前に1回だけ出力）
				if ( ! empty( $search_results_list ) ) {
					$data_forms .= $search_results_list;
				}
				$data_forms .= "<div class='button'>";
				// 更新ボタンのみ残す
				$data_forms .= '<button type="submit" name="send_post" title="' . esc_attr__( '更新する', 'kantanpro' ) . '"><span class="material-symbols-outlined">cached</span></button>';
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
			$skills_section .= '</div>'; // ktpwp_data_list_boxの終了

			// 詳細表示部分の終了
			// HEREDOC は使わない（wp.org ガイドライン）
			$div_end = '</div><!-- data_detail_boxの終了 -->'
				. '</div><!-- ktpwp_data_contentsの終了 -->';

			// -----------------------------
			// テンプレート印刷
			// -----------------------------

			$my_company_for_print = '';
			if ( class_exists( 'KTPWP_Settings' ) ) {
				$my_company_for_print = KTPWP_Settings::get_company_info();
			}
			if ( empty( $my_company_for_print ) ) {
				$my_company_for_print = get_bloginfo( 'name' );
			}
			$my_company_for_print = wp_strip_all_tags( (string) $my_company_for_print );
			$my_company_for_print = preg_replace( '/\S+@\S+\.\S+/', '', $my_company_for_print );
			$my_company_for_print = preg_replace( '/\s+/', ' ', trim( $my_company_for_print ) );

			$suppliers_print_args = array(
				'tab_name'   => $name,
				'print_all'  => 1,
				'page_start' => 0,
				'page_stage' => 2,
			);
			if ( KTPWP_Request::has_query( 'sort_by' ) && (string) KTPWP_Request::query_unslashed( 'sort_by' ) !== '' ) {
				$suppliers_print_args['sort_by'] = KTPWP_Request::query( 'sort_by' );
			}
			if ( KTPWP_Request::has_query( 'sort_order' ) && (string) KTPWP_Request::query_unslashed( 'sort_order' ) !== '' ) {
				$suppliers_print_args['sort_order'] = KTPWP_Request::query( 'sort_order' );
			}
			$suppliers_list_print_url = esc_url( add_query_arg( $suppliers_print_args, $base_page_url ) );

			$tab_print_button = class_exists( 'KTPWP_Ui_Generator' )
				? KTPWP_Ui_Generator::render_tab_print_button(
					array(
						'id'    => 'js-suppliers-list-print-btn',
						'label' => __( '協力会社リスト印刷', 'kantanpro' ),
						'title' => __( '印刷（ブラウザの印刷／PDFに保存）', 'kantanpro' ),
						'attrs' => array(
							'data-tab-list-print'         => '1',
							'data-print-target'           => '#ktp-suppliers-print-list-only',
							'data-print-fetch-url'        => $suppliers_list_print_url,
							'data-print-extract-selector' => '#ktp-suppliers-print-list-only',
							'data-print-filename-base'    => __( '協力会社リスト', 'kantanpro' ),
							'data-print-title'            => __( '協力会社リスト', 'kantanpro' ),
							'data-print-footer-name'      => $my_company_for_print,
						),
					)
				)
				: '';
			$supplier_address_label_title = __( '宛名印刷', 'kantanpro' );
			$supplier_address_label_aria  = esc_attr__( '宛名', 'kantanpro' );
			$supplier_address_label_text    = __( '宛名印刷', 'kantanpro' );
			$supplier_address_label_icon    = class_exists( 'KTPWP_SVG_Icons' )
				? KTPWP_SVG_Icons::get_icon( 'contact_mail', array( 'aria-label' => __( '宛名', 'kantanpro' ) ) )
				: '<span class="material-symbols-outlined" aria-label="' . esc_attr__( '宛名', 'kantanpro' ) . '">contact_mail</span>';

			$search_keep_params  = array();
			$search_toolbar_html = '';
			$search_panel_html   = '';
			if ( KTPWP_Request::has_query( 'data_id' ) && KTPWP_Request::query_unslashed( 'data_id' ) !== '' ) {
				$search_keep_params['data_id'] = KTPWP_Request::query( 'data_id' );
			}
			if ( class_exists( 'KTPWP_Tab_Search_UI' ) ) {
				$search_ui           = KTPWP_Tab_Search_UI::get_instance();
				$search_toolbar_html = $search_ui->render_toolbar_form( 'supplier', $search_keep_params );
				$search_panel_html   = $search_ui->maybe_render_cross_search_panel( 'supplier' );
			}

			// コントローラー（顧客タブと同様：検索全幅1行、ボタン1行＋印刷右寄せ）。
			// 返した HTML はショートコード出力の許可リスト（KTPWP_Kses::business_screen）で絞られる。
			$print  = '<div class="controller ktp-supplier-controller">';
			$print .= '<div class="ktp-supplier-controller__search">' . $search_toolbar_html . '</div>';
			$print .= '<div class="ktp-supplier-controller__bar">';
			$print .= '<div class="ktp-supplier-controller-actions">';
			$print .= '<button type="button" id="supplierAddressLabelPrintButton" class="ktp-client-address-label-btn" onclick="printSupplierAddressLabel(); return false;" title="' . esc_attr( $supplier_address_label_title ) . '">'
				. KTPWP_SVG_Icons::kses_icon( $supplier_address_label_icon )
				. '<span class="btn-label">' . esc_html( $supplier_address_label_text ) . '</span></button>';
			$print .= '</div>';
			$print .= '<div class="ktp-supplier-controller__tools">' . $tab_print_button . '</div>';
			$print .= '</div>';
			$print .= '</div>';
			// コンテンツを返す（検索複数時ダイアログは $data_forms 内で既に出力済み）
			$content = $print . $search_panel_html . $data_list . $skills_section . $data_title . $data_forms . $div_end;
			return $content;
		}

		/**
		 * 職能追加・削除成功後のリダイレクト。
		 * REQUEST_URI のみではスキーム・ホストがなく環境によって wp_redirect が失敗し白画面になるため、フルURLを組み立てる。
		 *
		 * @param string $message_key GET の message 値（例: skill_added）。
		 * @param int    $supplier_id Referer 欠如時に tab_name / data_id を付与するための協力会社ID。
		 * @return void
		 */
		private function redirect_after_supplier_skill_change( $message_key, $supplier_id = 0 ) {
			$redirect_base = wp_get_referer();

			// Referer が無い環境（ブラウザ・CDN・プライバシー設定）では $_SERVER から絶対URLを組み立てる
			if ( ! $redirect_base && isset( $_SERVER['HTTP_HOST'], $_SERVER['REQUEST_URI'] ) ) {
				$https = is_ssl();
				if ( isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) && 'https' === strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) ) ) ) {
					$https = true;
				}
				$scheme = $https ? 'https' : 'http';
				$host   = sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) );
				$uri    = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );
				if ( $host !== '' ) {
					$redirect_base = $scheme . '://' . $host . $uri;
				}
			}

			if ( ! $redirect_base ) {
				if ( class_exists( 'KTPWP_Main' ) ) {
					$redirect_base = KTPWP_Main::get_current_page_base_url();
				} else {
					$redirect_base = home_url( '/' );
				}
			}

			// 職能フォームPOSTで tab_name が欠落するとショートコードが別タブ扱いになるため、常に協力会社＋data_id を明示する
			if ( $supplier_id > 0 ) {
				$redirect_base = add_query_arg(
					array(
						'tab_name' => 'supplier',
						'data_id'  => absint( $supplier_id ),
					),
					remove_query_arg( array( 'tab_name', 'data_id', 'message' ), $redirect_base )
				);
			} else {
				$redirect_base = remove_query_arg( array( 'message' ), $redirect_base );
			}

			$redirect_url = add_query_arg( 'message', sanitize_key( $message_key ), $redirect_base );
			nocache_headers();
			wp_safe_redirect( $redirect_url );
			exit;
		}

		/**
		 * Handle skills operations (add, delete, etc.)
		 *
		 * @since 1.0.0
		 * @param array $post_data POST data array.
		 * @param bool  $early_context true のときテーマ出力前（template_redirect）。リダイレクトのみで通知し echo しない。
		 */
		private function handle_skills_operations( $post_data, $early_context = false ) {
			// Check if this is a skills operation
			if ( ! isset( $post_data['skills_action'] ) ) {
				return;
			}

			// Security check - verify nonce
			// wp_verify_nonce() は差し替え可能な関数なので、生の入力を渡さずサニタイズしてから比較する。
			// （$post_data はスラッシュ付きで渡る場合と外された状態で渡る場合があるため、
			//   英数字の nonce に対しては無害な wp_unslash() を通して正規化している。）
			$skills_nonce = isset( $post_data['ktpwp_skills_nonce'] )
				? sanitize_text_field( wp_unslash( (string) $post_data['ktpwp_skills_nonce'] ) )
				: '';
			if ( ! wp_verify_nonce( $skills_nonce, 'ktpwp_skills_action' ) ) {
				if ( $early_context ) {
					$this->redirect_skills_error_notification( 'skill_err_nonce' );
				}
				ktpwp_add_inline_notice( 'error', 'セキュリティチェックに失敗しました。ページを更新して再度お試しください。' );
				return;
			}

			// Check user permissions
			if ( ! current_user_can( 'edit_posts' ) ) {
				if ( $early_context ) {
					$this->redirect_skills_error_notification( 'skill_err_cap' );
				}
				ktpwp_add_inline_notice( 'error', 'この操作を行う権限がありません。' );
				return;
			}

			$action = sanitize_key( $post_data['skills_action'] );

			switch ( $action ) {
				case 'add_skill':
					$this->handle_add_skill( $post_data, $early_context );
					break;
				case 'delete_skill':
					$this->handle_delete_skill( $post_data, $early_context );
					break;
			}
		}

		/**
		 * フロントの template_redirect で呼ぶ（テーマ出力前にリダイレクト可能にするため public）。
		 *
		 * @param array $post_data $_POST 相当。
		 */
		public function handle_skills_operations_front_before_template( $post_data ) {
			$this->handle_skills_operations( $post_data, true );
		}

		/**
		 * 職能操作エラー時（早期リダイレクト用）。exit する。
		 *
		 * @param string $message_key GET message パラメータ。
		 */
		private function redirect_skills_error_notification( $message_key ) {
			$ref = wp_get_referer();
			if ( ! $ref && isset( $_SERVER['HTTP_HOST'], $_SERVER['REQUEST_URI'] ) ) {
				$https = is_ssl();
				if ( isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) && 'https' === strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) ) ) ) {
					$https = true;
				}
				$scheme = $https ? 'https' : 'http';
				$host   = sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) );
				$uri    = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );
				if ( $host !== '' ) {
					$ref = $scheme . '://' . $host . $uri;
				}
			}
			if ( ! $ref && class_exists( 'KTPWP_Main' ) ) {
				$ref = KTPWP_Main::get_current_page_base_url();
			}
			if ( ! $ref ) {
				$ref = home_url( '/' );
			}
			$url = add_query_arg( 'message', sanitize_key( $message_key ), remove_query_arg( 'message', $ref ) );
			nocache_headers();
			wp_safe_redirect( $url );
			exit;
		}

		/**
		 * Handle adding a new skill
		 *
		 * @since 1.0.0
		 * @param array $post_data POST data array
		 * @return void
		 */
		private function handle_add_skill( $post_data, $early_context = false ) {
			// Load skills manager
			if ( ! class_exists( 'KTPWP_Supplier_Skills' ) ) {
				require_once __DIR__ . '/class-ktpwp-supplier-skills.php';
			}

			$skills_manager = KTPWP_Supplier_Skills::get_instance();
			if ( ! $skills_manager ) {
				if ( $early_context ) {
					$this->redirect_skills_error_notification( 'skill_err_system' );
				}
				ktpwp_add_inline_notice( 'error', 'スキル管理システムが利用できません。' );
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
				if ( $early_context ) {
					$this->redirect_skills_error_notification( 'skill_err_input' );
				}
				ktpwp_add_inline_notice( 'error', '必要な情報が不足しています。' );
				return;
			}

			// Add the skill
			$result = $skills_manager->add_skill( $supplier_id, $product_name, $unit_price, $quantity, $unit, $tax_rate );

			if ( $result ) {
				$this->redirect_after_supplier_skill_change( 'skill_added', $supplier_id );
			} else {
				if ( $early_context ) {
					$this->redirect_skills_error_notification( 'skill_err_add' );
				}
				ktpwp_add_inline_notice( 'error', '商品・サービスの追加に失敗しました。' );
			}
		}

		/**
		 * Handle deleting a skill
		 *
		 * @since 1.0.0
		 * @param array $post_data POST data array
		 * @return void
		 */
		private function handle_delete_skill( $post_data, $early_context = false ) {
			// Load skills manager
			if ( ! class_exists( 'KTPWP_Supplier_Skills' ) ) {
				require_once __DIR__ . '/class-ktpwp-supplier-skills.php';
			}

			$skills_manager = KTPWP_Supplier_Skills::get_instance();
			if ( ! $skills_manager ) {
				if ( $early_context ) {
					$this->redirect_skills_error_notification( 'skill_err_system' );
				}
				ktpwp_add_inline_notice( 'error', 'スキル管理システムが利用できません。' );
				return;
			}

			// Sanitize input data
			$skill_id = isset( $post_data['skill_id'] ) ? absint( $post_data['skill_id'] ) : 0;

			// Validate required fields
			if ( empty( $skill_id ) ) {
				if ( $early_context ) {
					$this->redirect_skills_error_notification( 'skill_err_input' );
				}
				ktpwp_add_inline_notice( 'error', '削除する商品・サービスが指定されていません。' );
				return;
			}

			// 削除後は get_skill が取れないため、リダイレクト用に supplier_id を事前取得
			$supplier_row   = $skills_manager->get_skill( $skill_id );
			$supplier_id_ok = ( $supplier_row && isset( $supplier_row['supplier_id'] ) ) ? absint( $supplier_row['supplier_id'] ) : 0;

			// Delete the skill
			$result = $skills_manager->delete_skill( $skill_id );

			if ( $result ) {
				$this->redirect_after_supplier_skill_change( 'skill_deleted', $supplier_id_ok );
			} else {
				if ( $early_context ) {
					$this->redirect_skills_error_notification( 'skill_err_delete' );
				}
				ktpwp_add_inline_notice( 'error', '商品・サービスの削除に失敗しました。' );
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
			/* translators: 1: 現在のページ番号, 2: 総ページ数, 3: 総件数 */
			$pagination_html .= esc_html( sprintf( __( '%1$d / %2$d ページ（全 %3$d 件）', 'kantanpro' ), $current_page, $total_pages, $total_rows ) );
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
				if ( KTPWP_Request::has_query( 'sort_by' ) ) {
					$prev_args['sort_by'] = rawurlencode( KTPWP_Request::query( 'sort_by' ) );
				}
				if ( KTPWP_Request::has_query( 'sort_order' ) ) {
					$prev_args['sort_order'] = rawurlencode( KTPWP_Request::query( 'sort_order' ) );
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
				if ( KTPWP_Request::has_query( 'sort_by' ) ) {
					$first_args['sort_by'] = rawurlencode( KTPWP_Request::query( 'sort_by' ) );
				}
				if ( KTPWP_Request::has_query( 'sort_order' ) ) {
					$first_args['sort_order'] = rawurlencode( KTPWP_Request::query( 'sort_order' ) );
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
				if ( KTPWP_Request::has_query( 'sort_by' ) ) {
					$page_args['sort_by'] = rawurlencode( KTPWP_Request::query( 'sort_by' ) );
				}
				if ( KTPWP_Request::has_query( 'sort_order' ) ) {
					$page_args['sort_order'] = rawurlencode( KTPWP_Request::query( 'sort_order' ) );
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
				if ( KTPWP_Request::has_query( 'sort_by' ) ) {
					$last_args['sort_by'] = rawurlencode( KTPWP_Request::query( 'sort_by' ) );
				}
				if ( KTPWP_Request::has_query( 'sort_order' ) ) {
					$last_args['sort_order'] = rawurlencode( KTPWP_Request::query( 'sort_order' ) );
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
				if ( KTPWP_Request::has_query( 'sort_by' ) ) {
					$next_args['sort_by'] = rawurlencode( KTPWP_Request::query( 'sort_by' ) );
				}
				if ( KTPWP_Request::has_query( 'sort_order' ) ) {
					$next_args['sort_order'] = rawurlencode( KTPWP_Request::query( 'sort_order' ) );
				}

				$next_url = esc_url( add_query_arg( $next_args, $base_page_url ) );
				$pagination_html .= "<a href=\"{$next_url}\" style=\"{$button_style}\" {$hover_effect}>›</a>";
			}

			$pagination_html .= '</div>';
			$pagination_html .= '</div>';

			return $pagination_html;
		}

	}
} // class_exists
