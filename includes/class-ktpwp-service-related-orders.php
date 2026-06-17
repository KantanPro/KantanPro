<?php
/**
 * サービスに紐づく契約・案件リスト（サービスタブ左ペイン用）
 *
 * @package KTPWP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'KTPWP_Service_Related_Orders' ) ) {

	/**
	 * 選択中サービスの契約・問い合わせ案件を一覧表示する。
	 */
	class KTPWP_Service_Related_Orders {

		/**
		 * サービスに紐づく契約一覧。
		 *
		 * @param int $service_id サービス ID。
		 * @return array<int, object>
		 */
		public static function get_contracts_for_service( $service_id ) {
			global $wpdb;

			$service_id = absint( $service_id );
			if ( $service_id <= 0 || ! class_exists( 'KTPWP_Contract_DB' ) ) {
				return array();
			}

			$db = KTPWP_Contract_DB::get_instance();
			if ( ! $db->tables_exist() ) {
				return array();
			}

			$contract_table = $db->get_contract_table_name();
			$client_table   = $wpdb->prefix . 'ktp_client';

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT c.*, cl.company_name AS client_company_name
					FROM {$contract_table} c
					LEFT JOIN {$client_table} cl ON cl.id = c.client_id
					WHERE c.service_id = %d
					ORDER BY c.id DESC",
					$service_id
				)
			);

			return is_array( $rows ) ? $rows : array();
		}

		/**
		 * サービスに紐づく問い合わせ・請求案件一覧。
		 *
		 * @param int $service_id サービス ID。
		 * @return array<int, object>
		 */
		public static function get_orders_for_service( $service_id ) {
			global $wpdb;

			$service_id = absint( $service_id );
			if ( $service_id <= 0 ) {
				return array();
			}

			$order_table = $wpdb->prefix . 'ktp_order';
			$client_table = $wpdb->prefix . 'ktp_client';
			$cols        = $wpdb->get_col( "SHOW COLUMNS FROM `{$order_table}`", 0 );
			$cols        = is_array( $cols ) ? $cols : array();
			$has_client  = in_array( 'client_id', $cols, true );
			$client_join = $has_client ? "LEFT JOIN {$client_table} cl ON cl.id = o.client_id" : '';
			$client_sel  = $has_client ? ', cl.company_name AS client_company_name' : '';
			$orders      = array();

			if ( class_exists( 'KTPWP_Contract_DB' ) ) {
				$db = KTPWP_Contract_DB::get_instance();
				if ( $db->tables_exist() && in_array( 'contract_id', $cols, true ) ) {
					$contract_table = $db->get_contract_table_name();
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$found = $wpdb->get_results(
						$wpdb->prepare(
							"SELECT o.*{$client_sel} FROM {$order_table} o
							{$client_join}
							INNER JOIN {$contract_table} c ON c.id = o.contract_id
							WHERE c.service_id = %d
							ORDER BY o.id DESC",
							$service_id
						)
					);

					if ( is_array( $found ) ) {
						foreach ( $found as $row ) {
							$orders[ (int) $row->id ] = $row;
						}
					}
				}
			}

			if ( in_array( 'external_source', $cols, true ) && in_array( 'external_order_id', $cols, true ) ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$found = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT o.*{$client_sel} FROM {$order_table} o
						{$client_join}
						WHERE o.external_source = %s AND o.external_order_id = %s
						ORDER BY o.id DESC",
						'public_product',
						(string) $service_id
					)
				);

				if ( is_array( $found ) ) {
					foreach ( $found as $row ) {
						$orders[ (int) $row->id ] = $row;
					}
				}
			}

			$memo_prefix = sprintf(
				/* translators: 1: service ID (must keep trailing space for exact match) */
				__( '商品ID: %1$d ', 'ktpwp' ),
				$service_id
			);
			$memo_like   = '%' . $wpdb->esc_like( $memo_prefix ) . '%';

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$found = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT o.*{$client_sel} FROM {$order_table} o
					{$client_join}
					WHERE o.memo LIKE %s
					ORDER BY o.id DESC",
					$memo_like
				)
			);

			if ( is_array( $found ) ) {
				foreach ( $found as $row ) {
					$orders[ (int) $row->id ] = $row;
				}
			}

			$rows = array_values( $orders );
			usort(
				$rows,
				static function ( $a, $b ) {
					return (int) $b->id <=> (int) $a->id;
				}
			);

			return $rows;
		}

		/**
		 * 左ペイン用の契約・案件リスト HTML。
		 *
		 * @param int    $service_id   サービス ID。
		 * @param string $service_name サービス名。
		 * @param string $base_page_url ベース URL。
		 * @return string
		 */
		public static function render_list_section( $service_id, $service_name, $base_page_url ) {
			$service_id = absint( $service_id );
			if ( $service_id <= 0 ) {
				return '';
			}

			$service_name = trim( (string) $service_name );
			if ( $service_name === '' ) {
				$service_name = sprintf(
					/* translators: %d: service ID */
					__( 'サービス ID %d', 'ktpwp' ),
					$service_id
				);
			}

			$contracts = self::get_contracts_for_service( $service_id );
			$orders    = self::get_orders_for_service( $service_id );

			$html  = '<div class="data_list_title ktp-service-related-list__title">';
			$html .= '■ ' . esc_html( $service_name ) . esc_html__( 'の契約・案件', 'ktpwp' );
			$html .= '</div>';

			$has_rows = false;
			$sections_html = '';

			if ( $contracts !== array() ) {
				$has_rows      = true;
				$sections_html .= '<div class="ktp-service-related-list__section">';
				$sections_html .= '<div class="ktp-service-related-list__group-title">' . esc_html__( '定期契約', 'ktpwp' ) . '</div>';
				$sections_html .= '<div class="ktp-service-related-list__rows">';
				foreach ( $contracts as $contract ) {
					$sections_html .= self::render_contract_row( $contract, $base_page_url );
				}
				$sections_html .= '</div></div>';
			}

			if ( $orders !== array() ) {
				$has_rows      = true;
				$sections_html .= '<div class="ktp-service-related-list__section">';
				$sections_html .= '<div class="ktp-service-related-list__group-title">' . esc_html__( '案件', 'ktpwp' ) . '</div>';
				$sections_html .= '<div class="ktp-service-related-list__rows">';
				foreach ( $orders as $order ) {
					$sections_html .= self::render_order_row( $order, $base_page_url );
				}
				$sections_html .= '</div></div>';
			}

			if ( $has_rows ) {
				$html .= '<div class="ktp-service-related-list__sections">' . $sections_html . '</div>';
			}

			if ( ! $has_rows ) {
				$html .= '<div class="ktp_data_list_item ktp-service-related-list__empty">'
					. '<span class="material-symbols-outlined" aria-hidden="true">info</span>'
					. esc_html__( 'このサービスに紐づく契約・案件はありません。', 'ktpwp' )
					. '</div>';
			}

			return $html;
		}

		/**
		 * 契約行 HTML。
		 *
		 * @param object $contract      契約行。
		 * @param string $base_page_url ベース URL。
		 * @return string
		 */
		private static function render_contract_row( $contract, $base_page_url ) {
			$contract_id   = isset( $contract->id ) ? (int) $contract->id : 0;
			$client_id     = isset( $contract->client_id ) ? (int) $contract->client_id : 0;
			$contract_name = isset( $contract->contract_name ) ? (string) $contract->contract_name : '';
			$client_name   = isset( $contract->client_company_name ) ? (string) $contract->client_company_name : '';
			$status        = isset( $contract->status ) ? sanitize_key( (string) $contract->status ) : '';
			$status_label  = self::get_contract_status_label( $status );

			$detail_url = add_query_arg(
				array(
					'tab_name' => 'client',
					'data_id'  => $client_id,
				),
				$base_page_url
			);

			$line  = 'ID: ' . $contract_id . ' - ' . esc_html( $contract_name );
			if ( $client_name !== '' ) {
				$line .= ' <span class="ktp-service-related-list__meta">' . esc_html( $client_name ) . '</span>';
			}
			$line .= ' <span class="ktp-service-related-list__badge ktp-service-related-list__badge--contract">' . esc_html__( '契約', 'ktpwp' ) . '</span>';
			$line .= ' <span class="ktp-service-related-list__status ktp-contract-status ktp-contract-status--' . esc_attr( $status ) . '">' . esc_html( $status_label ) . '</span>';

			return '<a href="' . esc_url( $detail_url ) . '">'
				. '<div class="ktp_data_list_item ktp-service-related-list__item">' . $line . '</div>'
				. '</a>';
		}

		/**
		 * 案件行 HTML。
		 *
		 * @param object $order         案件行。
		 * @param string $base_page_url ベース URL。
		 * @return string
		 */
		private static function render_order_row( $order, $base_page_url ) {
			$order_id      = isset( $order->id ) ? (int) $order->id : 0;
			$project_name  = isset( $order->project_name ) ? (string) $order->project_name : '';
			$progress      = isset( $order->progress ) ? (int) $order->progress : 0;
			$client_id     = isset( $order->client_id ) ? (int) $order->client_id : 0;
			$client_name   = isset( $order->client_company_name ) ? (string) $order->client_company_name : '';
			$progress_label = self::get_progress_label( $progress );
			$is_web         = class_exists( 'KTPWP_Public_Product_Order_Memo' )
				&& KTPWP_Public_Product_Order_Memo::is_web_application( $order->memo ?? '' );

			$detail_url = add_query_arg(
				array(
					'tab_name' => 'order',
					'order_id' => $order_id,
				),
				$base_page_url
			);

			$line  = 'ID: ' . $order_id . ' - ' . esc_html( $project_name );
			if ( $client_name !== '' ) {
				$line .= ' <span class="ktp-service-related-list__meta">' . esc_html( $client_name ) . '</span>';
			} elseif ( $client_id > 0 ) {
				$line .= ' <span class="ktp-service-related-list__meta">' . esc_html(
					sprintf(
						/* translators: %d: client ID */
						__( '顧客 #%d', 'ktpwp' ),
						$client_id
					)
				) . '</span>';
			}
			if ( $is_web ) {
				$line .= ' <span class="ktp-service-related-list__badge ktp-service-related-list__badge--web">' . esc_html__( 'Webお申込み', 'ktpwp' ) . '</span>';
			}
			$line .= ' <span class="ktp-service-related-list__status status-' . esc_attr( (string) $progress ) . '">' . esc_html( $progress_label ) . '</span>';

			return '<a href="' . esc_url( $detail_url ) . '">'
				. '<div class="ktp_data_list_item ktp-service-related-list__item">' . $line . '</div>'
				. '</a>';
		}

		/**
		 * 契約ステータスラベル。
		 *
		 * @param string $status ステータス。
		 * @return string
		 */
		private static function get_contract_status_label( $status ) {
			$labels = array(
				'active'    => __( '有効', 'ktpwp' ),
				'paused'    => __( '一時停止', 'ktpwp' ),
				'cancelled' => __( '解約', 'ktpwp' ),
			);

			return $labels[ $status ] ?? $status;
		}

		/**
		 * 案件進捗ラベル。
		 *
		 * @param int $progress 進捗。
		 * @return string
		 */
		private static function get_progress_label( $progress ) {
			$labels = array(
				1 => __( '受付中', 'ktpwp' ),
				2 => __( '見積中', 'ktpwp' ),
				3 => __( '受注', 'ktpwp' ),
				4 => __( '完了', 'ktpwp' ),
				5 => __( '請求済', 'ktpwp' ),
				6 => __( '入金済', 'ktpwp' ),
				7 => __( 'ボツ', 'ktpwp' ),
			);

			return $labels[ $progress ] ?? __( '不明', 'ktpwp' );
		}
	}
}
