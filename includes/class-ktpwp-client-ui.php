<?php
/**
 * Client UI management class for KTPWP plugin
 *
 * Handles client list and form display.
 *
 * @package KTPWP
 * @subpackage Includes
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'KTPWP_Client_UI' ) ) {
	class KTPWP_Client_UI {
		public static function get_instance() {
			static $instance = null;
			if ( $instance === null ) {
				$instance = new self();
			}
			return $instance;
		}

		// View_Tableやフォーム生成などのUIロジックを今後分割して実装

		/**
		 * View table content with forms and client list
		 *
		 * @param string $name Table name suffix
		 * @return string HTML content
		 */
		public function view_table( $name ) {
			global $wpdb;

			// 表示結果を格納する変数
			$output = '';

			// $search_results_listの使用前に初期化
			$search_results_list = '';

			// -----------------------------
			// リスト表示
			// -----------------------------

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

			// 表示タイトルの設定（国際化対応）
			$list_title = ( $view_mode === 'order_history' )
            ? esc_html__( '■ 注文履歴', 'ktpwp' )
            : esc_html__( '■ 顧客リスト', 'ktpwp' );

			// ここでは顧客一覧のリスト表示部分を実装します
			// 顧客詳細フォーム部分は後で移行します

			// 今後の拡張予定:
			// - render_client_list()
			// - render_client_form()
			// - render_pagination()
			// のようなメソッドに分割する

			$html = $this->render_list_header( $name, $view_mode, $list_title, $base_page_url, $sort_by, $sort_order, $order_sort_by, $order_sort_order );

			// ここに顧客リスト・ページネーションのHTML生成処理を追加する予定

			// 現在は元のクラス(Kntan_Client_Class)のView_Tableメソッドに委譲して動作させる
			return $html;
		}

		/**
		 * リスト表示のヘッダー部分を生成する
		 *
		 * @param string $name テーブル名のサフィックス
		 * @param string $view_mode 表示モード
		 * @param string $list_title タイトル
		 * @param string $base_page_url 基本URL
		 * @param string $sort_by ソートカラム
		 * @param string $sort_order ソート順
		 * @param string $order_sort_by 注文履歴ソートカラム
		 * @param string $order_sort_order 注文履歴ソート順
		 * @return string HTML部分
		 */
		private function render_list_header( $name, $view_mode, $list_title, $base_page_url, $sort_by, $sort_order, $order_sort_by, $order_sort_order ) {
			// ソートプルダウンを追加
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
                '<span class="material-symbols-outlined" style="font-size:18px;line-height:18px;vertical-align:middle;">check</span>' .
                '</button>' .
                '</form></div>';
			}
			// 注文履歴のソートプルダウン
			else {
				global $wpdb;
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
                '<span class="material-symbols-outlined" style="font-size:18px;line-height:18px;vertical-align:middle;">check</span>' .
                '</button>' .
                '</form></div>';
			}

			// リスト表示部分の開始
			$html = <<<END
        <div class="ktp_data_contents">
            <div class="ktp_data_list_box">
            <div class="data_list_title">$list_title $sort_dropdown</div>
        END;

			return $html;
		}

		/**
		 * Set cookie value or get default
		 *
		 * @param string $name Cookie name suffix
		 * @return int Sanitized ID value
		 */
		public function set_cookie( $name ) {
			$cookie_name = 'ktp_' . sanitize_key( $name ) . '_id';
			$query_id = 1; // Default value

			if ( isset( $_COOKIE[ $cookie_name ] ) ) {
				$query_id = filter_input( INPUT_COOKIE, $cookie_name, FILTER_SANITIZE_NUMBER_INT );
			} elseif ( isset( $_GET['data_id'] ) ) {
				$query_id = filter_input( INPUT_GET, 'data_id', FILTER_SANITIZE_NUMBER_INT );
			}

			// Ensure we have a valid positive integer
			return max( 1, intval( $query_id ) );
		}
	}
}
