<?php
/**
 * Service UI management class for KTPWP plugin
 *
 * Handles service list and form display.
 *
 * @package KTPWP
 * @subpackage Includes
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'KTPWP_Service_UI' ) ) {
	class KTPWP_Service_UI {
		/**
		 * Instance of this class
		 *
		 * @var KTPWP_Service_UI
		 */
		private static $instance = null;

		/**
		 * Get singleton instance
		 *
		 * @return KTPWP_Service_UI
		 */
		public static function get_instance() {
			if ( self::$instance === null ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * コンストラクタ
		 */
		private function __construct() {
			// シングルトン
		}

		/**
		 * View table content with forms and service list
		 *
		 * @param string $name Table name suffix
		 * @return string HTML content
		 */
		public function view_table( $name ) {
			global $wpdb;

			// 表示結果を格納する変数
			$output = '';

			// -----------------------------
			// リスト表示
			// -----------------------------

			// テーブル名
			$table_name = $wpdb->prefix . 'ktp_' . $name;

			// ソート順の取得（デフォルトはIDの降順 - 新しい順）
			$sort_by = 'id';
			$sort_order = 'DESC';

			if ( isset( $_GET['sort_by'] ) ) {
				$sort_by = sanitize_text_field( $_GET['sort_by'] );
				// 安全なカラム名のみ許可（SQLインジェクション対策）
				$allowed_columns = array( 'id', 'service_name', 'price', 'unit', 'frequency', 'time', 'category', 'tax_rate' );
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

			// 表示範囲
			// 一般設定から表示件数を取得（設定クラスが利用可能な場合）
			if ( class_exists( 'KTP_Settings' ) ) {
				$query_limit = KTP_Settings::get_work_list_range();
			} else {
				$query_limit = 20; // フォールバック値
			}

			// 現在のページ番号を取得
			$paged = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
			$offset = ( $paged - 1 ) * $query_limit;

			// 検索パラメータを取得
			$search = '';
			$category = '';

			// POSTデータから検索条件を取得
			if ( isset( $_POST['query_post'] ) && $_POST['query_post'] === 'search_execute' ) {
				// 検索フォームからの入力を取得
				if ( isset( $_POST['search_service_name'] ) ) {
					$search = sanitize_text_field( $_POST['search_service_name'] );
				}
				if ( isset( $_POST['search_category'] ) ) {
					$category = sanitize_text_field( $_POST['search_category'] );
				}
			} elseif ( isset( $_GET['search'] ) ) {
				// GETパラメータからの検索条件
				$search = sanitize_text_field( $_GET['search'] );
			} elseif ( isset( $_GET['category'] ) ) {
				// GETパラメータからのカテゴリー条件
				$category = sanitize_text_field( $_GET['category'] );
			}

			// 検索条件の配列
			$search_args = array(
				'limit' => $query_limit,
				'offset' => $offset,
				'order_by' => $sort_by,
				'order' => $sort_order,
				'search' => $search,
				'category' => $category,
			);

			// データベースからサービス情報を取得
			$service_db = KTPWP_Service_DB::get_instance();

			// try-catchブロックでデータベースエラーを捕捉
			try {
				$services = $service_db->get_services( $name, $search_args );
				$total_services = $service_db->get_services_count( $name, $search_args );

				if ( $services === null ) {
					$services = array();
				}

				$total_pages = ceil( $total_services / $query_limit );
			} catch ( Exception $e ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'KTPWP: Error retrieving service data: ' . $e->getMessage() );
				}
				error_log( 'KTPWP: Exception Trace: ' . $e->getTraceAsString() );
				$services = array();
				$total_services = 0;
				$total_pages = 1;
			}

			// 検索ステータスと通知を表示
			$message_type = '';
			$is_searching = false;

			if ( isset( $_GET['message'] ) ) {
				$message_type = sanitize_key( $_GET['message'] );
			}

			if ( ! empty( $search ) || ! empty( $category ) ) {
				$is_searching = true;
				$message_type = 'search_found';
			}

			if ( isset( $_POST['query_post'] ) && $_POST['query_post'] === 'search_cancel' ) {
				$is_searching = false;
				$message_type = 'search_cancelled';
			}

			// 通知表示
			$notification = $this->render_notification( $message_type );

			// リストヘッダーを生成
			$html = $this->render_list_header( $name, $base_page_url, $sort_by, $sort_order );

			// 通知があれば表示
			if ( ! empty( $notification ) ) {
				$html .= $notification;
			}

			// 検索フォームを表示
			$html .= $this->render_search_form( $name );

			// サービス一覧テーブルのヘッダー
			$html .= '<div class="ktp_service_list_container">';
			$html .= '<table class="ktp_service_table wp-list-table widefat fixed striped">';
			$html .= '<thead>
            <tr>
                <th class="manage-column column-id">ID</th>
                <th class="manage-column column-image">' . esc_html__( '画像', 'ktpwp' ) . '</th>
                <th class="manage-column column-service-name">' . esc_html__( 'サービス名', 'ktpwp' ) . '</th>
                <th class="manage-column column-price">' . esc_html__( '価格', 'ktpwp' ) . '</th>
                <th class="manage-column column-tax-rate">' . esc_html__( '税率', 'ktpwp' ) . '</th>
                <th class="manage-column column-unit">' . esc_html__( '単位', 'ktpwp' ) . '</th>
                <th class="manage-column column-category">' . esc_html__( 'カテゴリー', 'ktpwp' ) . '</th>
                <th class="manage-column column-actions">' . esc_html__( '操作', 'ktpwp' ) . '</th>
            </tr>
        </thead>';

			$html .= '<tbody>';

			// サービスデータが存在する場合は一覧を表示
			if ( $services && count( $services ) > 0 ) {
				foreach ( $services as $service ) {
					$service_id = $service->id;
					$edit_url = add_query_arg(
                        array(
							'tab_name' => $name,
							'data_id' => $service_id,
                        ),
                        $base_page_url
                    );

					$html .= '<tr id="service-' . $service_id . '">';
					$html .= '<td class="column-id">' . $service_id . '</td>';

					// 画像列
					$html .= '<td class="column-image">';
					if ( ! empty( $service->image_url ) ) {
						$html .= '<img src="' . esc_url( $service->image_url ) . '" alt="' . esc_attr( $service->service_name ) . '" width="60" />';
					} else {
						$html .= '<span class="no-image">-</span>';
					}
					$html .= '</td>';

					// サービス名列
					$html .= '<td class="column-service-name">';
					$html .= '<a href="' . esc_url( $edit_url ) . '">' . esc_html( $service->service_name ) . '</a>';
					$html .= '</td>';

					// 価格列
					$html .= '<td class="column-price">' . $this->format_price_display( $service->price ) . '</td>';

					// 税率列
					$tax_rate = isset($service->tax_rate) ? floatval($service->tax_rate) : 10.00;
					$html .= '<td class="column-tax-rate">' . esc_html( intval($tax_rate) ) . '%</td>';

					// 単位列
					$html .= '<td class="column-unit">' . esc_html( $service->unit ) . '</td>';

					// カテゴリー列
					$html .= '<td class="column-category">' . esc_html( $service->category ) . '</td>';

					// 操作列
					$html .= '<td class="column-actions">';

					// 編集リンク
					$html .= '<a href="' . esc_url( $edit_url ) . '" class="button button-small" title="' . esc_attr__( '編集', 'ktpwp' ) . '">
                    <span class="dashicons dashicons-edit"></span>
                </a> ';

					// 複製フォーム
					$html .= '<form method="post" action="' . esc_url( $base_page_url ) . '" style="display:inline;">';
					$html .= '<input type="hidden" name="_ktp_service_nonce" value="' . wp_create_nonce( 'ktp_service_action' ) . '">';
					$html .= '<input type="hidden" name="tab_name" value="' . esc_attr( $name ) . '">';
					$html .= '<input type="hidden" name="data_id" value="' . esc_attr( $service_id ) . '">';
					$html .= '<input type="hidden" name="query_post" value="duplicate">';
					$html .= '<button type="submit" class="button button-small" title="' . esc_attr__( '複製', 'ktpwp' ) . '">
                    <span class="dashicons dashicons-admin-page"></span>
                </button>';
					$html .= '</form> ';

					// 削除フォーム
					$html .= '<form method="post" action="' . esc_url( $base_page_url ) . '" style="display:inline;" onsubmit="return confirm(\'' . esc_js( __( 'このサービスを削除してもよろしいですか？', 'ktpwp' ) ) . '\');">';
					$html .= '<input type="hidden" name="_ktp_service_nonce" value="' . wp_create_nonce( 'ktp_service_action' ) . '">';
					$html .= '<input type="hidden" name="tab_name" value="' . esc_attr( $name ) . '">';
					$html .= '<input type="hidden" name="data_id" value="' . esc_attr( $service_id ) . '">';
					$html .= '<input type="hidden" name="query_post" value="delete">';
					$html .= '<button type="submit" class="button button-small" title="' . esc_attr__( '削除', 'ktpwp' ) . '">
                    <span class="dashicons dashicons-trash"></span>
                </button>';
					$html .= '</form>';

					$html .= '</td>';
					$html .= '</tr>';
				}
			} else {
				// データがない場合のメッセージ
				$html .= '<tr><td colspan="7" style="text-align: center;">' . esc_html__( 'サービスが見つかりません。', 'ktpwp' ) . '</td></tr>';
			}

			$html .= '</tbody>';
			$html .= '</table>';

			// ページネーション
			$html .= '<div class="tablenav bottom">';
			$html .= '<div class="tablenav-pages">';
			$html .= '<span class="displaying-num">' . sprintf( _n( '%s項目', '%s項目', $total_services, 'ktpwp' ), number_format_i18n( $total_services ) ) . '</span>';

			// ページネーションリンクの生成
			$page_links = array();

			// ページネーションベースURL
			$pagination_args = array(
				'tab_name' => $name,
				'sort_by' => $sort_by,
				'sort_order' => $sort_order,
			);

			// 検索パラメータを維持
			if ( ! empty( $search ) ) {
				$pagination_args['search'] = $search;
			}
			if ( ! empty( $category ) ) {
				$pagination_args['category'] = $category;
			}

			$pagination_url = add_query_arg( $pagination_args, $base_page_url );

			// 前のページへのリンク
			if ( $paged > 1 ) {
				$page_links[] = '<a class="prev-page" href="' . esc_url( add_query_arg( 'paged', ( $paged - 1 ), $pagination_url ) ) . '">
                <span aria-hidden="true">‹</span>
            </a>';
			} else {
				$page_links[] = '<span class="tablenav-pages-navspan button disabled">‹</span>';
			}

			// ページ番号リンク
			$start_number = max( 1, $paged - 2 );
			$end_number = min( $total_pages, $paged + 2 );

			for ( $i = $start_number; $i <= $end_number; $i++ ) {
				if ( $i == $paged ) {
					$page_links[] = '<span class="tablenav-pages-navspan button current">' . $i . '</span>';
				} else {
					$page_links[] = '<a class="page-numbers" href="' . esc_url( add_query_arg( 'paged', $i, $pagination_url ) ) . '">' . $i . '</a>';
				}
			}

			// 次のページへのリンク
			if ( $paged < $total_pages ) {
				$page_links[] = '<a class="next-page" href="' . esc_url( add_query_arg( 'paged', ( $paged + 1 ), $pagination_url ) ) . '">
                <span aria-hidden="true">›</span>
            </a>';
			} else {
				$page_links[] = '<span class="tablenav-pages-navspan button disabled">›</span>';
			}

			$html .= join( '', $page_links );
			$html .= '</div>';
			$html .= '</div>';

			$html .= '</div>'; // .ktp_service_list_container

			// 新規サービス追加フォーム
			if ( current_user_can( 'manage_options' ) ) {
				$html .= '<div class="ktp_new_service_form">';
				$html .= '<h3>' . esc_html__( '新規サービスを追加', 'ktpwp' ) . '</h3>';
				$html .= '<form method="post" action="' . esc_url( $base_page_url ) . '" class="ktp-service-form">';
				$html .= '<input type="hidden" name="_ktp_service_nonce" value="' . wp_create_nonce( 'ktp_service_action' ) . '">';
				$html .= '<input type="hidden" name="tab_name" value="' . esc_attr( $name ) . '">';
				$html .= '<input type="hidden" name="query_post" value="new">';

				$html .= '<div class="form-group">';
				$html .= '<label for="service_name">' . esc_html__( 'サービス名', 'ktpwp' ) . ' <span class="required">*</span></label>';
				$html .= '<input type="text" id="service_name" name="service_name" required maxlength="100">';
				$html .= '</div>';

				$html .= '<div class="form-row">';
				$html .= '<div class="form-group half">';
				$html .= '<label for="price">' . esc_html__( '価格', 'ktpwp' ) . '</label>';
				$html .= '<input type="number" id="price" name="price" min="0" step="0.01" value="0">';
				$html .= '</div>';

				$html .= '<div class="form-group half">';
				$html .= '<label for="unit">' . esc_html__( '単位', 'ktpwp' ) . '</label>';
				$html .= '<input type="text" id="unit" name="unit" value="式" maxlength="50">';
				$html .= '</div>';
				$html .= '</div>';

				$html .= '<div class="form-group">';
				$html .= '<label for="category">' . esc_html__( 'カテゴリー', 'ktpwp' ) . '</label>';
				$html .= '<input type="text" id="category" name="category" value="' . esc_attr__( 'General', 'ktpwp' ) . '" maxlength="100">';
				$html .= '</div>';

				$html .= '<div class="form-group">';
				$html .= '<label for="memo">' . esc_html__( 'メモ', 'ktpwp' ) . '</label>';
				$html .= '<textarea id="memo" name="memo" rows="3"></textarea>';
				$html .= '</div>';

				// 画像アップロード機能（新規サービス追加時）
				$html .= '<div class="form-group">';
				$html .= '<label for="service_image">' . esc_html__( 'サービス画像', 'ktpwp' ) . '</label>';
				$html .= '<input type="file" id="service_image" name="service_image" accept="image/*">';
				$html .= '<small style="display: block; margin-top: 5px; color: #666;">' . esc_html__( 'JPEGまたはPNG形式の画像をアップロードできます。', 'ktpwp' ) . '</small>';
				$html .= '</div>';

				$html .= '<div class="form-submit">';
				$html .= '<button type="submit" class="button button-primary">' . esc_html__( '追加する', 'ktpwp' ) . '</button>';
				$html .= '</div>';

				$html .= '</form>';
				$html .= '</div>';
			}

			// リストフッター
			$html .= '</div>'; // .ktp_data_list_box
			$html .= '</div>'; // .ktp_data_contents

			return $html;
		}

		/**
		 * 価格表示を適切にフォーマットする
		 *
		 * @param float $price 価格
		 * @return string フォーマットされた価格
		 */
		private function format_price_display( $price ) {
			// 小数点以下が0の場合は整数として表示
			if ( $price == (int) $price ) {
				return number_format( $price );
			}
			// 小数点以下がある場合は小数点以下を表示
			return number_format( $price, 2 );
		}

		/**
		 * リスト表示のヘッダー部分を生成する
		 *
		 * @param string $name テーブル名のサフィックス
		 * @param string $base_page_url 基本URL
		 * @param string $sort_by ソートカラム
		 * @param string $sort_order ソート順
		 * @return string HTML部分
		 */
		private function render_list_header( $name, $base_page_url, $sort_by, $sort_order ) {
			// ソート用プルダウンアクションURLからは 'message' を除去
			$sort_action_url = remove_query_arg( 'message', $base_page_url );

			// ソートプルダウンのHTMLを構築
			$sort_dropdown = '<div class="sort-dropdown" style="float:right;margin-left:10px;">' .
            '<form method="get" action="' . esc_url( $sort_action_url ) . '" style="display:flex;align-items:center;">';

			// 現在のGETパラメータを維持するための隠しフィールド (不要なパラメータは除外)
			foreach ( $_GET as $key => $value ) {
				if ( ! in_array( $key, array( 'message', 'sort_by', 'sort_order', '_ktp_service_nonce', 'query_post', 'send_post' ) ) ) {
					$sort_dropdown .= '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '">';
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
			$html = <<<END
        <div class="ktp_data_contents">
            <div class="ktp_data_list_box">
                <div class="data_list_title">■ サービスリスト {$sort_dropdown}</div>
END;

			return $html;
		}

		/**
		 * サービス検索フォームを生成
		 *
		 * @param string $name テーブル名サフィックス
		 * @return string 検索フォームHTML
		 */
		public function render_search_form( $name ) {
			global $wp;
			$current_page_id = get_queried_object_id();
			$base_page_url = add_query_arg( array( 'page_id' => $current_page_id ), home_url( $wp->request ) );

			$nonce = wp_create_nonce( 'ktp_service_action' );

			// 検索フォームのHTML
			$html = '<div class="search-form-container" style="padding: 15px; background: #f9f9f9; border-radius: 5px; margin-bottom: 20px;">';
			$html .= '<form method="post" action="' . esc_url( $base_page_url ) . '">';
			$html .= '<input type="hidden" name="_ktp_service_nonce" value="' . $nonce . '">';
			$html .= '<input type="hidden" name="query_post" value="search_execute">';
			$html .= '<input type="hidden" name="tab_name" value="' . esc_attr( $name ) . '">';

			$html .= '<div style="display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 15px;">';

			// サービス名検索
			$html .= '<div style="flex: 1; min-width: 250px;">';
			$html .= '<label for="search_service_name" style="display: block; margin-bottom: 5px; font-weight: bold;">' . esc_html__( 'サービス名', 'ktpwp' ) . '</label>';
			$html .= '<input type="text" id="search_service_name" name="search_service_name" style="width: 100%; padding: 8px;" placeholder="' . esc_attr__( 'サービス名を入力...', 'ktpwp' ) . '">';
			$html .= '</div>';

			// カテゴリー検索
			$html .= '<div style="flex: 1; min-width: 250px;">';
			$html .= '<label for="search_category" style="display: block; margin-bottom: 5px; font-weight: bold;">' . esc_html__( 'カテゴリー', 'ktpwp' ) . '</label>';
			$html .= '<input type="text" id="search_category" name="search_category" style="width: 100%; padding: 8px;" placeholder="' . esc_attr__( 'カテゴリーを入力...', 'ktpwp' ) . '">';
			$html .= '</div>';

			$html .= '</div>';

			// 検索ボタン
			$html .= '<div style="display: flex; justify-content: space-between;">';
			$html .= '<button type="submit" class="button button-primary" style="padding: 8px 15px;">' . esc_html__( '検索', 'ktpwp' ) . '</button>';
			$html .= '</div>';

			$html .= '</form>';

			// キャンセルフォーム - 検索フォームとは別に実装
			$html .= '<div style="margin-top: 10px; text-align: right;">';
			$html .= '<form method="post" action="' . esc_url( $base_page_url ) . '">';
			$html .= '<input type="hidden" name="_ktp_service_nonce" value="' . $nonce . '">';
			$html .= '<input type="hidden" name="query_post" value="search_cancel">';
			$html .= '<input type="hidden" name="tab_name" value="' . esc_attr( $name ) . '">';
			$html .= '<button type="submit" class="button" style="padding: 8px 15px;">' . esc_html__( 'キャンセル', 'ktpwp' ) . '</button>';
			$html .= '</form>';
			$html .= '</div>';
			$html .= '</div>';

			return $html;
		}

		/**
		 * メッセージ通知を表示
		 *
		 * @param string $message_type メッセージタイプ
		 * @param bool   $is_sorting_action ソート操作かどうか
		 * @return string 通知HTML
		 */
		public function render_notification( $message_type, $is_sorting_action = false ) {
			$message_text = '';
			$notice_class = '';

			if ( $message_type === 'updated' && ! $is_sorting_action ) {
				$message_text = esc_html__( '更新しました。', 'ktpwp' );
				$notice_class = 'notice-success is-dismissible';
			} elseif ( $message_type === 'added' ) {
				$message_text = esc_html__( '新しいサービスを追加しました。', 'ktpwp' );
				$notice_class = 'notice-success is-dismissible';
			} elseif ( $message_type === 'deleted' ) {
				$message_text = esc_html__( '削除しました。', 'ktpwp' );
				$notice_class = 'notice-success is-dismissible';
			} elseif ( $message_type === 'duplicated' ) {
				$message_text = esc_html__( '複製しました。', 'ktpwp' );
				$notice_class = 'notice-success is-dismissible';
			} elseif ( $message_type === 'search_cancelled' ) {
				$message_text = esc_html__( '検索をキャンセルしました。', 'ktpwp' );
				$notice_class = 'notice-info is-dismissible';
			} elseif ( $message_type === 'search_found' ) {
				$message_text = esc_html__( '検索結果を表示しています。', 'ktpwp' );
				$notice_class = 'notice-info is-dismissible';
			}

			if ( ! empty( $message_text ) && ! empty( $notice_class ) ) {
				return '<div class="notice ' . esc_attr( $notice_class ) . '"><p>' . $message_text . '</p></div>';
			}

			return '';
		}

		/**
		 * Set cookie for UI session management
		 *
		 * @param string $name Cookie name suffix
		 * @return int The query ID
		 */
		public function set_cookie( $name ) {
			if ( empty( $name ) ) {
				return 1;
			}

			$cookie_name = 'ktp_' . sanitize_key( $name ) . '_id';
			$query_id = 1;

			if ( isset( $_COOKIE[ $cookie_name ] ) ) {
				$query_id = absint( $_COOKIE[ $cookie_name ] );
			} elseif ( isset( $_GET['data_id'] ) ) {
				$query_id = absint( $_GET['data_id'] );
			}

			// Validate ID is positive
			return ( $query_id > 0 ) ? $query_id : 1;
		}
	}
}
