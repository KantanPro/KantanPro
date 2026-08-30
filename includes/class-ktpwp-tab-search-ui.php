<?php
/**
 * 全タブ共通フリーワード検索 UI（KantanBiz list_search 相当）
 *
 * @package KTPWP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'KTPWP_Tab_Search_UI' ) ) {

	class KTPWP_Tab_Search_UI {

		/** @var self|null */
		private static $instance = null;

		public static function get_instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * GET list_search（後方互換: search_query / search_service_name）
		 */
		public function get_keyword() {
			if ( isset( $_GET['list_search'] ) ) {
				return trim( sanitize_text_field( wp_unslash( $_GET['list_search'] ) ) );
			}
			if ( isset( $_GET['search_query'] ) ) {
				return trim( sanitize_text_field( wp_unslash( $_GET['search_query'] ) ) );
			}
			if ( isset( $_GET['search_service_name'] ) ) {
				return trim( sanitize_text_field( wp_unslash( $_GET['search_service_name'] ) ) );
			}

			return '';
		}

		/**
		 * ツールバー検索フォーム HTML
		 *
		 * @param string               $tab_name    タブ名
		 * @param array<string,string> $keep_params 維持する GET パラメータ
		 */
		public function render_toolbar_form( $tab_name, $keep_params = array() ) {
			$keyword             = $this->get_keyword();
			$search_placeholder  = ( $keyword !== '' ) ? $keyword : __( 'フリーワード', 'kantanpro' );
			$html                = '<div class="ktp-list-search-wrap" style="display:flex;align-items:center;gap:6px;">';
			$html               .= '<form method="get" action="" class="ktp-list-search-form" style="display:flex;align-items:center;gap:6px;">';
			$html               .= '<input type="hidden" name="tab_name" value="' . esc_attr( (string) $tab_name ) . '">';

			foreach ( $keep_params as $key => $value ) {
				if ( $value === null || $value === '' ) {
					continue;
				}
				$html .= '<input type="hidden" name="' . esc_attr( (string) $key ) . '" value="' . esc_attr( (string) $value ) . '">';
			}

			$html .= '<input type="search" name="list_search" value="" placeholder="' . esc_attr( $search_placeholder ) . '" aria-label="' . esc_attr__( 'フリーワード', 'kantanpro' ) . '" class="ktp-list-search-input" style="min-width:160px;padding:6px 8px;border:1px solid #ddd;border-radius:4px;">';
			$html .= '<button type="submit" class="ktp-list-search-btn" title="' . esc_attr__( '検索', 'kantanpro' ) . '" style="padding:6px 10px;border:1px solid #ddd;border-radius:4px;background:#f5f5f5;cursor:pointer;">🔍</button>';
			$html .= '</form></div>';

			return $html;
		}

		/**
		 * 横断検索結果パネル
		 *
		 * @param string $keyword   検索語
		 * @param string $close_url 閉じるリンク
		 */
		public function render_cross_search_results( $keyword, $close_url = '' ) {
			global $wpdb;

			$keyword = trim( (string) $keyword );
			if ( $keyword === '' ) {
				return '';
			}

			$like    = '%' . $wpdb->esc_like( $keyword ) . '%';
			$results = array();

			// 受注書
			$order_table = $wpdb->prefix . 'ktp_order';
			$order_args  = array( $like, $like, $like );
			$order_where = ' ( customer_name LIKE %s OR user_name LIKE %s OR project_name LIKE %s ';
			$order_cols  = $wpdb->get_col( "SHOW COLUMNS FROM `{$order_table}`" );
			if ( is_array( $order_cols ) && in_array( 'memo', $order_cols, true ) ) {
				$order_where .= ' OR memo LIKE %s ';
				$order_args[] = $like;
			}
			if ( is_array( $order_cols ) && in_array( 'search_field', $order_cols, true ) ) {
				$order_where .= ' OR search_field LIKE %s ';
				$order_args[] = $like;
			}
			$order_where .= ') ';
			$order_args[] = 50;
			$order_sql    = "SELECT id, client_id, customer_name, user_name, project_name FROM `{$order_table}` WHERE {$order_where} ORDER BY time DESC LIMIT %d";
			$orders       = $wpdb->get_results( $wpdb->prepare( $order_sql, $order_args ) );
			if ( $orders ) {
				foreach ( $orders as $row ) {
					$client_label = class_exists( 'KTPWP_Department_Manager' )
						? KTPWP_Department_Manager::format_work_list_client_label_for_order( $row )
						: trim( (string) ( $row->customer_name ?: '' ) . ' ' . (string) ( $row->user_name ?: '' ) );
					$label        = 'ID: ' . (int) $row->id;
					if ( $client_label !== '' ) {
						$label .= ' ' . $client_label;
					}
					if ( $row->project_name ) {
						$label .= ' - ' . $row->project_name;
					}
					$results[] = array(
						'page_label' => __( '受注書', 'kantanpro' ),
						'label'      => $label,
						'url'        => add_query_arg( array( 'tab_name' => 'order', 'order_id' => (int) $row->id ) ),
					);
				}
			}

			// 顧客
			$client_table = $wpdb->prefix . 'ktp_client';
			$client_cols  = $wpdb->get_col( "SHOW COLUMNS FROM `{$client_table}`" );
			$client_where = ' ( company_name LIKE %s OR name LIKE %s ';
			$client_args  = array( $like, $like );
			if ( is_array( $client_cols ) && in_array( 'memo', $client_cols, true ) ) {
				$client_where .= ' OR memo LIKE %s ';
				$client_args[] = $like;
			}
			if ( is_array( $client_cols ) && in_array( 'search_field', $client_cols, true ) ) {
				$client_where .= ' OR search_field LIKE %s ';
				$client_args[] = $like;
			}
			$client_where .= ') ';
			$client_args[] = 50;
			$clients       = $wpdb->get_results( $wpdb->prepare( "SELECT id, company_name, name FROM `{$client_table}` WHERE {$client_where} ORDER BY id DESC LIMIT %d", $client_args ) );
			if ( $clients ) {
				foreach ( $clients as $row ) {
					$results[] = array(
						'page_label' => __( '顧客', 'kantanpro' ),
						'label'      => ( $row->company_name ?: '' ) . ' (' . ( $row->name ?: '' ) . ')',
						'url'        => add_query_arg( array( 'tab_name' => 'client', 'data_id' => (int) $row->id ) ),
					);
				}
			}

			// サービス
			$service_table = $wpdb->prefix . 'ktp_service';
			$service_cols  = $wpdb->get_col( "SHOW COLUMNS FROM `{$service_table}`" );
			$service_where = ' ( service_name LIKE %s ';
			$service_args  = array( $like );
			if ( is_array( $service_cols ) ) {
				foreach ( array( 'memo', 'category', 'search_field', 'unit' ) as $col ) {
					if ( in_array( $col, $service_cols, true ) ) {
						$service_where .= " OR {$col} LIKE %s ";
						$service_args[] = $like;
					}
				}
			}
			$service_where .= ') ';
			$service_args[] = 50;
			$services       = $wpdb->get_results( $wpdb->prepare( "SELECT id, service_name FROM `{$service_table}` WHERE {$service_where} ORDER BY id DESC LIMIT %d", $service_args ) );
			if ( $services ) {
				foreach ( $services as $row ) {
					$results[] = array(
						'page_label' => __( 'サービス', 'kantanpro' ),
						'label'      => (string) ( $row->service_name ?: '' ),
						'url'        => add_query_arg( array( 'tab_name' => 'service', 'data_id' => (int) $row->id ) ),
					);
				}
			}

			// 協力会社
			$supplier_table = $wpdb->prefix . 'ktp_supplier';
			$supplier_cols  = $wpdb->get_col( "SHOW COLUMNS FROM `{$supplier_table}`" );
			$supplier_where = ' ( company_name LIKE %s OR name LIKE %s ';
			$supplier_args  = array( $like, $like );
			if ( is_array( $supplier_cols ) ) {
				foreach ( array( 'memo', 'search_field', 'category' ) as $col ) {
					if ( in_array( $col, $supplier_cols, true ) ) {
						$supplier_where .= " OR {$col} LIKE %s ";
						$supplier_args[] = $like;
					}
				}
			}
			$supplier_where .= ') ';
			$supplier_args[] = 50;
			$suppliers       = $wpdb->get_results( $wpdb->prepare( "SELECT id, company_name, name FROM `{$supplier_table}` WHERE {$supplier_where} ORDER BY id DESC LIMIT %d", $supplier_args ) );
			if ( $suppliers ) {
				foreach ( $suppliers as $row ) {
					$results[] = array(
						'page_label' => __( '協力会社', 'kantanpro' ),
						'label'      => ( $row->company_name ?: '' ) . ' (' . ( $row->name ?: '' ) . ')',
						'url'        => add_query_arg( array( 'tab_name' => 'supplier', 'data_id' => (int) $row->id ) ),
					);
				}
			}

			$close_btn = '';
			if ( $close_url !== '' ) {
				$close_btn = '<a href="' . esc_url( $close_url ) . '" class="ktp-list-search-results-close" title="' . esc_attr__( '閉じる', 'kantanpro' ) . '" style="position:absolute;top:8px;right:8px;width:28px;height:28px;display:flex;align-items:center;justify-content:center;color:#666;text-decoration:none;font-size:18px;line-height:1;border-radius:4px;">×</a>';
			}

			if ( empty( $results ) ) {
				$html  = '<div class="ktp-list-search-results" style="position:relative;margin-bottom:16px;padding:14px 18px;padding-right:44px;background:#f9f9f9;border:1px solid #eee;border-radius:6px;">';
				$html .= $close_btn;
				$html .= '<p style="margin:0;color:#666;font-size:14px;">' . esc_html__( '検索に一致するデータはありません。', 'kantanpro' ) . '</p></div>';

				return $html;
			}

			$html  = '<div class="ktp-list-search-results" style="position:relative;margin-bottom:16px;padding:14px 18px;padding-right:44px;background:#f0f7ff;border:1px solid #bbdefb;border-radius:6px;">';
			$html .= $close_btn;
			$html .= '<p style="margin:0 0 10px 0;font-weight:bold;font-size:14px;color:#1565c0;">' . esc_html__( '検索結果', 'kantanpro' ) . '</p>';
			$html .= '<ul style="margin:0;padding-left:20px;list-style:disc;">';
			foreach ( $results as $r ) {
				$html .= '<li style="margin-bottom:6px;"><span>' . esc_html( $r['label'] ) . '</span> ';
				$html .= '<a href="' . esc_url( $r['url'] ) . '" style="color:#1976d2;font-weight:600;">' . esc_html( $r['page_label'] ) . '</a></li>';
			}
			$html .= '</ul></div>';

			return $html;
		}

		/**
		 * マスタ一覧用 WHERE 句（AND (...)）と prepare 引数
		 *
		 * @param string $table_name テーブル名
		 * @param string $keyword    検索語
		 * @param string $entity     client|supplier|service
		 * @return array{0:string,1:array<int,mixed>}
		 */
		public function master_list_search_clause( $table_name, $keyword, $entity ) {
			global $wpdb;

			$keyword = trim( (string) $keyword );
			if ( $keyword === '' ) {
				return array( '', array() );
			}

			$like = '%' . $wpdb->esc_like( $keyword ) . '%';
			$cols = $wpdb->get_col( "SHOW COLUMNS FROM `{$table_name}`" );
			if ( ! is_array( $cols ) ) {
				return array( '', array() );
			}

			$parts = array();
			$args  = array();

			if ( $entity === 'service' ) {
				if ( in_array( 'service_name', $cols, true ) ) {
					$parts[] = 'service_name LIKE %s';
					$args[]  = $like;
				}
				foreach ( array( 'memo', 'category', 'search_field', 'unit' ) as $col ) {
					if ( in_array( $col, $cols, true ) ) {
						$parts[] = "`{$col}` LIKE %s";
						$args[]  = $like;
					}
				}
			} else {
				if ( in_array( 'company_name', $cols, true ) ) {
					$parts[] = 'company_name LIKE %s';
					$args[]  = $like;
				}
				if ( in_array( 'name', $cols, true ) ) {
					$parts[] = 'name LIKE %s';
					$args[]  = $like;
				}
				foreach ( array( 'memo', 'search_field', 'email', 'category', 'representative_name', 'phone' ) as $col ) {
					if ( in_array( $col, $cols, true ) ) {
						$parts[] = "`{$col}` LIKE %s";
						$args[]  = $like;
					}
				}
			}

			if ( $parts === array() ) {
				return array( '', array() );
			}

			return array( ' AND (' . implode( ' OR ', $parts ) . ')', $args );
		}

		/**
		 * list_search を除いた現在 URL
		 *
		 * @param array<int,string> $extra_remove 追加で除去するクエリキー
		 */
		public function close_url_without_search( $extra_remove = array() ) {
			$remove = array_merge(
				array( 'list_search', 'search_query', 'search_service_name', 'search_category', 'no_results', 'multiple_results' ),
				$extra_remove
			);
			$base   = remove_query_arg( $remove );

			return $base;
		}

		/**
		 * 横断検索パネル（現在の GET から list_search を読む）
		 *
		 * @param string              $tab_name タブ名（close URL 用）
		 * @param array<int,string>   $extra_remove
		 */
		public function maybe_render_cross_search_panel( $tab_name, $extra_remove = array() ) {
			$keyword = $this->get_keyword();
			if ( $keyword === '' ) {
				return '';
			}

			$close = $this->close_url_without_search( $extra_remove );
			if ( $tab_name !== '' ) {
				$close = add_query_arg( 'tab_name', $tab_name, $close );
			}

			return $this->render_cross_search_results( $keyword, $close );
		}
	}
}
