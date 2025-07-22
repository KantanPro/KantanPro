<?php
/**
 * Order UI management class for KTPWP plugin
 *
 * Handles UI display functionality for orders, including HTML table generation.
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

if ( ! class_exists( 'KTPWP_Order_UI' ) ) {

	/**
	 * Order UI management class
	 *
	 * @since 1.0.0
	 */
	class KTPWP_Order_UI {

		/**
		 * Singleton instance
		 *
		 * @since 1.0.0
		 * @var KTPWP_Order_UI
		 */
		private static $instance = null;

		/**
		 * Get singleton instance
		 *
		 * @since 1.0.0
		 * @return KTPWP_Order_UI
		 */
		public static function get_instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * Constructor
		 *
		 * @since 1.0.0
		 */
		private function __construct() {
			// Private constructor for singleton
		}

		/**
		 * Generate HTML table for invoice items
		 *
		 * @since 1.0.0
		 * @param int $order_id Order ID
		 * @return string HTML table content
		 */
		public function generate_invoice_items_table( $order_id ) {
			$order_items = KTPWP_Order_Items::get_instance();
			$items = $order_items->get_invoice_items( $order_id );
			
			// 顧客の税区分を取得
			$tax_category = '内税'; // デフォルト値
			if ( ! empty( $order_id ) ) {
				global $wpdb;
				$order_table = $wpdb->prefix . 'ktp_order';
				$client_table = $wpdb->prefix . 'ktp_client';
				
				$order_data = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT client_id FROM `{$order_table}` WHERE id = %d",
						$order_id
					)
				);
				
				if ( $order_data && ! empty( $order_data->client_id ) ) {
					$client_tax_category = $wpdb->get_var(
						$wpdb->prepare(
							"SELECT tax_category FROM `{$client_table}` WHERE id = %d",
							$order_data->client_id
						)
					);
					if ( $client_tax_category ) {
						$tax_category = $client_tax_category;
					}
				}
			}

			// sort_orderの昇順でソート
			usort(
                $items,
                function ( $a, $b ) {
                    $a_order = isset( $a['sort_order'] ) ? intval( $a['sort_order'] ) : 0;
                    $b_order = isset( $b['sort_order'] ) ? intval( $b['sort_order'] ) : 0;
                    // 昇順
                    return $a_order <=> $b_order;
                }
            );

			// If no items or empty array, create one empty row for display
			if ( empty( $items ) ) {
				$items = array(
					array(
						'id' => 0,
						'order_id' => $order_id,
						'product_name' => '',
						'price' => 0,
						'unit' => '式',
						'quantity' => 1,
						'amount' => 0,
						'tax_rate' => null,
						'remarks' => '',
						'sort_order' => 1,
					),
				);
			}

			// Calculate total amount
			$total_amount = 0;
			foreach ( $items as $item ) {
				$total_amount += isset( $item['amount'] ) ? floatval( $item['amount'] ) : 0;
			}

			$html = '<div class="invoice-items-container">';
			$html .= '<form method="post" action="" class="invoice-items-form">';
			$html .= '<input type="hidden" name="order_id" value="' . intval( $order_id ) . '" />';
			$html .= '<input type="hidden" name="save_invoice_items" value="1" />';
			$html .= '<input type="hidden" name="tax_category" value="' . esc_attr( $tax_category ) . '" />';
			$html .= wp_nonce_field( 'save_invoice_items_action', 'invoice_items_nonce', true, false );
			$html .= '<script>window.ktpClientTaxCategory = "' . esc_js( $tax_category ) . '";</script>';
			$html .= '<div class="invoice-items-scroll-wrapper">';
			$html .= '<table class="invoice-items-table" id="invoice-items-table-' . intval( $order_id ) . '">';
			$html .= '<thead>';
			$html .= '<tr>';
			$html .= '<th class="actions-column">' . esc_html__( '操作', 'ktpwp' ) . '</th>';
			$html .= '<th>' . esc_html__( 'サービス', 'ktpwp' ) . '</th>';
			$html .= '<th style="text-align:left;">' . esc_html__( '単価', 'ktpwp' ) . '</th>';
			$html .= '<th style="text-align:left;">' . esc_html__( '数量', 'ktpwp' ) . '</th>';
			$html .= '<th>' . esc_html__( '単位', 'ktpwp' ) . '</th>';
			$html .= '<th style="text-align:left;">' . esc_html__( '金額', 'ktpwp' ) . '</th>';
			$html .= '<th style="text-align:left;">' . esc_html__( '税率', 'ktpwp' ) . '</th>';
			$html .= '<th>' . esc_html__( '備考', 'ktpwp' ) . '</th>';
			$html .= '</tr>';
			$html .= '</thead>';
			$html .= '<tbody>';

			foreach ( $items as $index => $item ) {
				$row_id = isset( $item['id'] ) ? intval( $item['id'] ) : 0;
				$html .= '<tr class="invoice-item-row" data-row-id="' . $row_id . '">';
				// Actions column with drag handle and buttons
				$html .= '<td class="actions-column">';
				$html .= '<span class="drag-handle" title="' . esc_attr__( 'ドラッグして並び替え', 'ktpwp' ) . '">&#9776;</span>';
				$html .= '<button type="button" class="btn-add-row" title="' . esc_attr__( '行を追加', 'ktpwp' ) . '">+</button>';
				// --- ここを修正: 1行目でも常に削除ボタンを出力 ---
				$html .= '<button type="button" class="btn-delete-row" title="' . esc_attr__( '行を削除', 'ktpwp' ) . '">×</button>';
				$html .= '<button type="button" class="btn-move-row" title="' . esc_attr__( 'サービス選択', 'ktpwp' ) . '">></button>';
				$html .= '</td>';

				// Product name
				$html .= '<td>';
				$html .= '<input type="text" name="invoice_items[' . $index . '][product_name]" ';
				$html .= 'value="' . esc_attr( $item['product_name'] ) . '" ';
				$html .= 'class="invoice-item-input product-name" />';
				$html .= '<input type="hidden" name="invoice_items[' . $index . '][id]" value="' . $row_id . '" />';
				$html .= '</td>';

				// Price
				$html .= '<td style="text-align:left;">';
				$price_raw = floatval( $item['price'] );
				$price_display = rtrim( rtrim( number_format( $price_raw, 6, '.', '' ), '0' ), '.' );
				$html .= '<input type="number" name="invoice_items[' . $index . '][price]" ';
				$html .= 'value="' . esc_attr( $price_display ) . '" ';
				$html .= 'class="invoice-item-input price" step="1" min="0" style="text-align:left;" />';
				$html .= '</td>';

				// Quantity
				$html .= '<td style="text-align:left;">';
				$quantity_raw = floatval( $item['quantity'] );
				$quantity_display = rtrim( rtrim( number_format( $quantity_raw, 6, '.', '' ), '0' ), '.' );
				$html .= '<input type="number" name="invoice_items[' . $index . '][quantity]" ';
				$html .= 'value="' . esc_attr( $quantity_display ) . '" ';
				$html .= 'class="invoice-item-input quantity" step="1" min="0" style="text-align:left;" />';
				$html .= '</td>';

				// Unit
				$html .= '<td>';
				$html .= '<input type="text" name="invoice_items[' . $index . '][unit]" ';
				$html .= 'value="' . esc_attr( $item['unit'] ) . '" ';
				$html .= 'class="invoice-item-input unit" />';
				$html .= '</td>';

				// Amount
				$html .= '<td style="text-align:left;">';
				$html .= '<input type="number" name="invoice_items[' . $index . '][amount]" ';
				$html .= 'value="' . esc_attr( $item['amount'] ) . '" ';
				$html .= 'class="invoice-item-input amount" step="1" readonly style="text-align:left;" />';
				$html .= '</td>';

				// Tax Rate
				$tax_rate_raw = isset( $item['tax_rate'] ) ? $item['tax_rate'] : null;
				$tax_rate_display = '';
				if ( $tax_rate_raw !== null && $tax_rate_raw !== '' && is_numeric( $tax_rate_raw ) ) {
					$tax_rate_display = floatval( $tax_rate_raw );
				}
				$html .= '<td style="text-align:left;">';
				$html .= '<div style="display:inline-flex;align-items:center;margin-left:0;padding-left:0;">';
				$html .= '<input type="number" name="invoice_items[' . $index . '][tax_rate]" ';
				$html .= 'value="' . esc_attr( $tax_rate_display ) . '" ';
				$html .= 'class="invoice-item-input tax-rate" step="1" min="0" max="100" style="width:50px; text-align:right; display:inline-block; margin-left:0; padding-left:0;" />';
				$html .= '<span style="margin-left:2px; white-space:nowrap;">%</span>';
				$html .= '</div>';
				$html .= '</td>';

				// Remarks
				$html .= '<td>';
				$html .= '<input type="text" name="invoice_items[' . $index . '][remarks]" ';
				$html .= 'value="' . esc_attr( $item['remarks'] ) . '" ';
				$html .= 'class="invoice-item-input remarks" />';
				$html .= '</td>';

				$html .= '</tr>';
			}

			$html .= '</tbody>';
			$html .= '</table>';
			$html .= '</div>'; // invoice-items-scroll-wrapper

			// Calculate tax amount and total with tax
			$tax_amount = 0;
			
			if ( $tax_category === '外税' ) {
				// 外税表示の場合：各項目の税抜金額から税額を計算（切り上げ）
				foreach ( $items as $item ) {
					$item_amount = isset( $item['amount'] ) ? floatval( $item['amount'] ) : 0;
					$item_tax_rate_raw = isset( $item['tax_rate'] ) ? $item['tax_rate'] : null;
					
					// 税率がNULL、空文字、または数値でない場合は税額計算をスキップ
					if ( $item_tax_rate_raw !== null && $item_tax_rate_raw !== '' && is_numeric( $item_tax_rate_raw ) ) {
						$item_tax_rate = floatval( $item_tax_rate_raw );
						// 統一ルール：外税計算で切り上げ
						$tax_amount += ceil( $item_amount * ( $item_tax_rate / 100 ) );
					}
				}
			} else {
				// 内税表示の場合：税率別に集計して税額を計算
				$tax_rate_groups = array();
				foreach ( $items as $item ) {
					$item_amount = isset( $item['amount'] ) ? floatval( $item['amount'] ) : 0;
					$item_tax_rate_raw = isset( $item['tax_rate'] ) ? $item['tax_rate'] : null;
					
					// 税率がNULL、空文字、または数値でない場合は非課税として扱う
					if ( $item_tax_rate_raw !== null && $item_tax_rate_raw !== '' && is_numeric( $item_tax_rate_raw ) ) {
						$item_tax_rate = floatval( $item_tax_rate_raw );
						$tax_rate_key = number_format( $item_tax_rate, 1 );
						if ( ! isset( $tax_rate_groups[ $tax_rate_key ] ) ) {
							$tax_rate_groups[ $tax_rate_key ] = 0;
						}
						$tax_rate_groups[ $tax_rate_key ] += $item_amount;
					}
				}
				
				// 各税率グループごとに税額を計算（切り上げ）
				foreach ( $tax_rate_groups as $tax_rate => $group_amount ) {
					$rate = floatval( $tax_rate );
					$tax_amount += ceil( $group_amount * ( $rate / 100 ) / ( 1 + $rate / 100 ) );
				}
			}
			
			$total_with_tax = $total_amount + $tax_amount;
			$total_amount_ceiled = ceil( $total_amount );
			$tax_amount_ceiled = ceil( $tax_amount );
			$total_with_tax_ceiled = ceil( $total_with_tax );
			
			// 税区分に応じた合計表示
			if ( $tax_category === '外税' ) {
				// 外税表示の場合：3行表示
				$html .= '<div class="invoice-items-total" style="text-align:right;margin-top:8px;font-weight:bold;">';
				$html .= esc_html__( '合計金額', 'ktpwp' ) . ' : ' . esc_html( number_format( $total_amount_ceiled ) ) . esc_html__( '円', 'ktpwp' );
				$html .= '</div>';
				
				// Tax amount display
				$html .= '<div class="invoice-items-tax" style="text-align:right;margin-top:4px;color:#666;">';
				$html .= esc_html__( '消費税', 'ktpwp' ) . ' : ' . esc_html( number_format( $tax_amount_ceiled ) ) . esc_html__( '円', 'ktpwp' );
				$html .= '</div>';
				
				// Total with tax display
				$html .= '<div class="invoice-items-total-with-tax" style="text-align:right;margin-top:4px;font-weight:bold;color:#d32f2f;">';
				$html .= esc_html__( '税込合計', 'ktpwp' ) . ' : ' . esc_html( number_format( $total_with_tax_ceiled ) ) . esc_html__( '円', 'ktpwp' );
				$html .= '</div>';
			} else {
				// 内税表示の場合：1行表示
				$html .= '<div class="invoice-items-total" style="text-align:right;margin-top:8px;font-weight:bold;">';
				$html .= '金額合計：' . esc_html( number_format( $total_amount_ceiled ) ) . '円　（内税：' . esc_html( number_format( $tax_amount_ceiled ) ) . '円）';
				$html .= '</div>';
				
				// Tax amount display (非表示)
				$html .= '<div class="invoice-items-tax" style="text-align:right;margin-top:4px;color:#666;display:none;"></div>';
				
				// Total with tax display (非表示)
				$html .= '<div class="invoice-items-total-with-tax" style="text-align:right;margin-top:4px;font-weight:bold;color:#d32f2f;display:none;"></div>';
			}

			$html .= '</form>';
			$html .= '</div>';

			return $html;
		}

		/**
		 * Generate HTML table for cost items
		 *
		 * @since 1.0.0
		 * @param int $order_id Order ID
		 * @return string HTML table content
		 */
		public function generate_cost_items_table( $order_id ) {
			// supplier_idカラムがなければ自動追加
			if ( class_exists( 'KTPWP_Order_Items' ) ) {
				$order_items = KTPWP_Order_Items::get_instance();
				$order_items->add_supplier_id_column_if_missing();
			}

			$order_items = KTPWP_Order_Items::get_instance();
			$items = $order_items->get_cost_items( $order_id );

			// 顧客の税区分を取得
			$tax_category = $this->get_client_tax_category( $order_id );

			// デバッグログを追加
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'KTPWP Cost Items Debug - Order ID: ' . $order_id . ', Tax Category: ' . $tax_category . ', Type: ' . gettype($tax_category) . ', Length: ' . strlen($tax_category) );
			}

			// sort_orderの昇順でソート
			usort(
                $items,
                function ( $a, $b ) {
                    $a_order = isset( $a['sort_order'] ) ? intval( $a['sort_order'] ) : 0;
                    $b_order = isset( $b['sort_order'] ) ? intval( $b['sort_order'] ) : 0;
                    // 昇順
                    return $a_order <=> $b_order;
                }
            );

			// If no items or empty array, create one empty row for display
			if ( empty( $items ) ) {
				$items = array(
					array(
						'id' => 0,
						'order_id' => $order_id,
						'product_name' => '',
						'price' => 0,
						'unit' => '式',
						'quantity' => 1,
						'amount' => 0,
						'tax_rate' => 10.00,
						'remarks' => '',
						'sort_order' => 1,
					),
				);
			}

			// Calculate total amount and tax（ここから修正）
            require_once __DIR__ . '/class-supplier-data.php';
            $supplier_data = new KTPWP_Supplier_Data();
            $total_amount = 0;
            $total_tax_amount = 0;
            $has_outtax = false;
            foreach ( $items as $item ) {
                $item_amount = isset( $item['amount'] ) ? floatval( $item['amount'] ) : 0;
                $item_tax_rate_raw = isset( $item['tax_rate'] ) ? $item['tax_rate'] : null;
                $supplier_id = isset( $item['supplier_id'] ) ? intval( $item['supplier_id'] ) : 0;
                $item_tax_category = $supplier_data->get_tax_category_by_supplier_id( $supplier_id );
                if ( $item_tax_category === '外税' ) {
                    $has_outtax = true;
                }
                $total_amount += $item_amount;
                
                // 税率がNULL、空文字、または数値でない場合は税額計算をスキップ
                if ( $item_tax_rate_raw !== null && $item_tax_rate_raw !== '' && is_numeric( $item_tax_rate_raw ) ) {
                    $item_tax_rate = floatval( $item_tax_rate_raw );
                    if ( $item_tax_category === '外税' ) {
                        // 統一ルール：外税計算で切り上げ
                        $total_tax_amount += ceil( $item_amount * ( $item_tax_rate / 100 ) );
                    } else {
                        // 統一ルール：内税計算で切り上げ
                        $total_tax_amount += ceil( $item_amount * ( $item_tax_rate / 100 ) / ( 1 + $item_tax_rate / 100 ) );
                    }
                }
            }
            $total_with_tax = $total_amount + $total_tax_amount;
            $total_amount_ceiled = ceil( $total_amount );
            $total_tax_amount_ceiled = ceil( $total_tax_amount );
            $total_with_tax_ceiled = ceil( $total_with_tax );

			$html = '<div class="cost-items-container">';
			$html .= '<form method="post" action="" class="cost-items-form">';
			$html .= '<input type="hidden" name="order_id" value="' . intval( $order_id ) . '" />';
			$html .= '<input type="hidden" name="save_cost_items" value="1" />';
			$html .= wp_nonce_field( 'save_cost_items_action', 'cost_items_nonce', true, false );
			$html .= '<script>window.ktpClientTaxCategory = "' . esc_js( $tax_category ) . '";</script>';
			
			// デバッグ用のスクリプトを追加
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				$html .= '<script>console.log("[PHP] コスト項目税区分設定:", {';
				$html .= 'orderId: "' . intval( $order_id ) . '", ';
				$html .= 'taxCategory: "' . esc_js( $tax_category ) . '", ';
				$html .= 'taxCategoryType: "' . gettype($tax_category) . '", ';
				$html .= 'taxCategoryLength: ' . strlen($tax_category) . ', ';
				$html .= 'isOutTax: "' . esc_js( $tax_category ) . '" === "外税"';
				$html .= '});</script>';
			}
			$html .= '<div class="cost-items-scroll-wrapper">';
			$html .= '<table class="cost-items-table" id="cost-items-table-' . intval( $order_id ) . '">';
			$html .= '<thead>';
			$html .= '<tr>';
			$html .= '<th class="actions-column">' . esc_html__( '操作', 'ktpwp' ) . '</th>';
			$html .= '<th>' . esc_html__( 'サービス', 'ktpwp' ) . '</th>';
			$html .= '<th style="text-align:left;">' . esc_html__( '単価', 'ktpwp' ) . '</th>';
			$html .= '<th style="text-align:left;">' . esc_html__( '数量', 'ktpwp' ) . '</th>';
			$html .= '<th>' . esc_html__( '単位', 'ktpwp' ) . '</th>';
			$html .= '<th style="text-align:left;">' . esc_html__( '金額', 'ktpwp' ) . '</th>';
			$html .= '<th style="text-align:left;">' . esc_html__( '税率', 'ktpwp' ) . '</th>';
			$html .= '<th>' . esc_html__( '備考', 'ktpwp' ) . '</th>';
			$html .= '<th>' . esc_html__( '仕入', 'ktpwp' ) . '</th>';
			$html .= '</tr>';
			$html .= '</thead>';
			$html .= '<tbody>';

			foreach ( $items as $index => $item ) {
				$row_id = isset( $item['id'] ) ? intval( $item['id'] ) : 0;
				$supplier_id_for_row = isset( $item['supplier_id'] ) ? intval( $item['supplier_id'] ) : 0;
				$html .= '<tr class="cost-item-row" data-row-id="' . $row_id . '" data-supplier-id="' . $supplier_id_for_row . '">';
				// Actions column with drag handle and buttons
				$html .= '<td class="actions-column">';
				$html .= '<span class="drag-handle" title="' . esc_attr__( 'ドラッグして並び替え', 'ktpwp' ) . '">&#9776;</span>';
				$html .= '<button type="button" class="btn-add-row" title="' . esc_attr__( '行を追加', 'ktpwp' ) . '">+</button>';
				// --- ここを修正: 1行目でも常に削除ボタンを出力 ---
				$html .= '<button type="button" class="btn-delete-row" title="' . esc_attr__( '行を削除', 'ktpwp' ) . '">×</button>';
				$html .= '<button type="button" class="btn-move-row" title="' . esc_attr__( '行を移動', 'ktpwp' ) . '">></button>';
				$html .= '</td>';

				// Product name
				$html .= '<td>';
				$html .= '<input type="text" name="cost_items[' . $index . '][product_name]" ';
				$html .= 'value="' . esc_attr( $item['product_name'] ) . '" ';
				$html .= 'class="cost-item-input product-name" />';
				$html .= '<input type="hidden" name="cost_items[' . $index . '][id]" value="' . $row_id . '" />';
				$html .= '<input type="hidden" name="cost_items[' . $index . '][supplier_id]" value="' . esc_attr( $supplier_id_for_row ) . '" class="supplier-id" />';
				$html .= '</td>';

				// Price
				$html .= '<td style="text-align:left;">';
				$price_raw = floatval( $item['price'] );
				$price_display = rtrim( rtrim( number_format( $price_raw, 6, '.', '' ), '0' ), '.' );
				$html .= '<input type="number" name="cost_items[' . $index . '][price]" ';
				$html .= 'value="' . esc_attr( $price_display ) . '" ';
				$html .= 'class="cost-item-input price" step="1" min="0" style="text-align:left;" />';
				$html .= '</td>';

				// Quantity
				$html .= '<td style="text-align:left;">';
				$quantity_raw = floatval( $item['quantity'] );
				$quantity_display = rtrim( rtrim( number_format( $quantity_raw, 6, '.', '' ), '0' ), '.' );
				$html .= '<input type="number" name="cost_items[' . $index . '][quantity]" ';
				$html .= 'value="' . esc_attr( $quantity_display ) . '" ';
				$html .= 'class="cost-item-input quantity" step="1" min="0" style="text-align:left;" />';
				$html .= '</td>';

				// Unit
				$html .= '<td>';
				$html .= '<input type="text" name="cost_items[' . $index . '][unit]" ';
				$html .= 'value="' . esc_attr( $item['unit'] ) . '" ';
				$html .= 'class="cost-item-input unit" />';
				$html .= '</td>';

				// Amount
				$html .= '<td style="text-align:left;">';
				$html .= '<input type="number" name="cost_items[' . $index . '][amount]" ';
				$html .= 'value="' . esc_attr( $item['amount'] ) . '" ';
				$html .= 'class="cost-item-input amount" step="1" readonly style="text-align:left;" />';
				$html .= '</td>';

				// Tax Rate
				$tax_rate_raw = isset( $item['tax_rate'] ) ? $item['tax_rate'] : null;
				$tax_rate_display = '';
				if ( $tax_rate_raw !== null && $tax_rate_raw !== '' && is_numeric( $tax_rate_raw ) ) {
					$tax_rate_display = floatval( $tax_rate_raw );
				}
				$html .= '<td style="text-align:left;">';
				$html .= '<div style="display:inline-flex;align-items:center;margin-left:0;padding-left:0;">';
				$html .= '<input type="number" name="cost_items[' . $index . '][tax_rate]" ';
				$html .= 'value="' . esc_attr( $tax_rate_display ) . '" ';
				$html .= 'class="cost-item-input tax-rate" step="1" min="0" max="100" style="width:50px; text-align:right; display:inline-block; margin-left:0; padding-left:0;" />';
				$html .= '<span style="margin-left:2px; white-space:nowrap;">%</span>';
				$html .= '</div>';
				$html .= '</td>';

				// Remarks
				$remarks_value = isset( $item['remarks'] ) ? $item['remarks'] : '';
				// NULL値や空文字列の場合は空文字列として扱う
				if ( $remarks_value === null || $remarks_value === '0' ) {
					$remarks_value = '';
				}
				$html .= '<td>';
				$html .= '<input type="text" name="cost_items[' . $index . '][remarks]" ';
				$html .= 'value="' . esc_attr( $remarks_value ) . '" ';
				$html .= 'class="cost-item-input remarks" />';
				$html .= '</td>';

				// Purchase (仕入)
				$purchase_value = isset( $item['purchase'] ) ? $item['purchase'] : '';
				$ordered = isset( $item['ordered'] ) ? intval( $item['ordered'] ) : 0;
				if ( ! empty( $purchase_value ) ) {
					$html .= '<td>';
					$html .= '<span class="purchase-display purchase-link" data-purchase="' . esc_attr( $purchase_value ) . '" style="cursor: pointer; color: #007cba; text-decoration: underline;">' . esc_html( $purchase_value ) . 'に発注</span>';
					if ( $ordered === 1 ) {
						$html .= '<span class="purchase-checked" style="display:inline-block;margin-left:6px;vertical-align:middle;color:#dc3545;font-size:1.3em;font-weight:bold;">✓</span>';
					}
					$html .= '<input type="hidden" name="cost_items[' . $index . '][purchase]" ';
					$html .= 'value="' . esc_attr( $purchase_value ) . '" />';
					$html .= '</td>';
				} else {
					$html .= '<td>';
					$html .= '<span class="purchase-display">手入力</span>';
					$html .= '<input type="hidden" name="cost_items[' . $index . '][purchase]" ';
					$html .= 'value="" />';
					$html .= '</td>';
				}

				$html .= '</tr>';
			}

			$html .= '</tbody>';
			$html .= '</table>';
			$html .= '</div>'; // cost-items-scroll-wrapper

			// 税区分に応じた合計表示（ここも修正）
            if ( $has_outtax ) {
                // 外税行が1つでもあれば外税3行表示
                $html .= '<div class="cost-items-total" style="text-align:right;margin-top:8px;font-weight:bold;">';
                $html .= '金額合計 : ' . esc_html( number_format( $total_amount_ceiled ) ) . esc_html__( '円', 'ktpwp' );
                $html .= '</div>';
                $html .= '<div class="cost-items-tax" style="text-align:right;margin-top:4px;color:#666;">';
                $html .= esc_html__( '消費税', 'ktpwp' ) . ' : ' . esc_html( number_format( $total_tax_amount_ceiled ) ) . esc_html__( '円', 'ktpwp' );
                $html .= '</div>';
                $html .= '<div class="cost-items-total-with-tax" style="text-align:right;margin-top:4px;font-weight:bold;color:#d32f2f;">';
                $html .= esc_html__( '税込合計', 'ktpwp' ) . ' : ' . esc_html( number_format( $total_with_tax_ceiled ) ) . esc_html__( '円', 'ktpwp' );
                $html .= '</div>';
            } else {
                // 全て内税なら内税1行表示
                $html .= '<div class="cost-items-total" style="text-align:right;margin-top:8px;font-weight:bold;">';
                $html .= '金額合計：' . esc_html( number_format( $total_amount_ceiled ) ) . '円　（内税：' . esc_html( number_format( $total_tax_amount_ceiled ) ) . '円）';
                $html .= '</div>';
                $html .= '<div class="cost-items-tax" style="text-align:right;margin-top:4px;color:#666;display:none;"></div>';
                $html .= '<div class="cost-items-total-with-tax" style="text-align:right;margin-top:4px;font-weight:bold;color:#d32f2f;display:none;"></div>';
            }

			// Profit calculation similar to invoice items
			$invoice_items = $order_items->get_invoice_items( $order_id );
			$invoice_total = 0;
			$invoice_tax_total = 0;
			foreach ( $invoice_items as $invoice_item ) {
				$invoice_amount = isset( $invoice_item['amount'] ) ? floatval( $invoice_item['amount'] ) : 0;
				$invoice_tax_rate = isset( $invoice_item['tax_rate'] ) ? floatval( $invoice_item['tax_rate'] ) : 10.00;
				
				$invoice_total += $invoice_amount;
				
				// 請求項目の消費税計算
				if ( $tax_category === '外税' ) {
					$invoice_tax_total += $invoice_amount * ( $invoice_tax_rate / 100 );
				} else {
					$invoice_tax_total += $invoice_amount * ( $invoice_tax_rate / 100 ) / ( 1 + $invoice_tax_rate / 100 );
				}
			}

			// 税込合計での利益計算
			$invoice_total_with_tax = $invoice_total + $invoice_tax_total;
			$invoice_total_ceiled = ceil( $invoice_total );
			$invoice_tax_total_ceiled = ceil( $invoice_tax_total );
			$invoice_total_with_tax_ceiled = ceil( $invoice_total_with_tax );
			
			// 適格請求書ナンバーを考慮した利益計算
			$profit = $this->calculate_profit_with_qualified_invoice( $order_id, $items, $invoice_total_with_tax_ceiled );

			// Profit display (using tax-inclusive values)
			$profit_color = $profit >= 0 ? '#28a745' : '#dc3545';  // Green for profit, red for loss
			$html .= '<div class="profit-display" style="text-align:right;margin-top:8px;font-weight:bold;color:' . $profit_color . ';">';
			$html .= esc_html__( '利益', 'ktpwp' ) . ' : ' . esc_html( number_format( $profit ) ) . esc_html__( '円', 'ktpwp' );
			$html .= '</div>';

			$html .= '</form>';
			$html .= '</div>';

			return $html;
		}

		/**
		 * Get client tax category for an order
		 *
		 * @since 1.0.0
		 * @param int $order_id Order ID
		 * @return string Tax category ('外税' or '内税')
		 */
		private function get_client_tax_category( $order_id ) {
			global $wpdb;
			
			if ( ! $order_id || $order_id <= 0 ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'KTPWP Tax Category Debug - Invalid order ID: ' . $order_id );
				}
				return '内税'; // デフォルト値
			}

			// 受注書から顧客IDを取得
			$order_table = $wpdb->prefix . 'ktp_order';
			$order = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT client_id, customer_name, user_name FROM {$order_table} WHERE id = %d",
					$order_id
				)
			);

			if ( ! $order ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'KTPWP Tax Category Debug - Order not found for ID: ' . $order_id );
				}
				return '内税'; // デフォルト値
			}

			// 顧客テーブルから税区分を取得（client_idを優先、なければ会社名と担当者名で検索）
			$client_table = $wpdb->prefix . 'ktp_client';
			$client = null;

			// client_idがある場合はそれを使用
			if ( ! empty( $order->client_id ) ) {
				$client = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT tax_category FROM {$client_table} WHERE id = %d",
						$order->client_id
					)
				);
				
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'KTPWP Tax Category Debug - Using client_id: ' . $order->client_id . ', Tax Category: ' . ( $client ? $client->tax_category : 'not found' ) );
				}
			}

			// client_idで見つからない場合は会社名と担当者名で検索
			if ( ! $client && ! empty( $order->customer_name ) && ! empty( $order->user_name ) ) {
				$client = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT tax_category FROM {$client_table} WHERE company_name = %s AND name = %s",
						$order->customer_name,
						$order->user_name
					)
				);
				
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'KTPWP Tax Category Debug - Using company_name/name: ' . $order->customer_name . '/' . $order->user_name . ', Tax Category: ' . ( $client ? $client->tax_category : 'not found' ) );
				}
			}

			if ( $client && ! empty( $client->tax_category ) ) {
				return $client->tax_category;
			}

			return '内税'; // デフォルト値
		}

		/**
		 * Get supplier qualified invoice number
		 *
		 * @since 1.0.0
		 * @param int $supplier_id Supplier ID
		 * @return string Qualified invoice number or empty string
		 */
		private function get_supplier_qualified_invoice_number( $supplier_id ) {
			if ( ! $supplier_id || $supplier_id <= 0 ) {
				return '';
			}

			global $wpdb;
			$supplier_table = $wpdb->prefix . 'ktp_supplier';
			
			$qualified_invoice_number = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT qualified_invoice_number FROM `{$supplier_table}` WHERE id = %d",
					$supplier_id
				)
			);

			return $qualified_invoice_number ? trim( $qualified_invoice_number ) : '';
		}

		/**
		 * Calculate profit considering qualified invoice numbers
		 *
		 * @since 1.0.0
		 * @param int $order_id Order ID
		 * @param array $cost_items Cost items array
		 * @param float $invoice_total_with_tax Invoice total with tax
		 * @return float Calculated profit
		 */
		private function calculate_profit_with_qualified_invoice( $order_id, $cost_items, $invoice_total_with_tax ) {
			$total_cost = 0;
			$qualified_invoice_cost = 0;
			$non_qualified_invoice_cost = 0;
			
			foreach ( $cost_items as $item ) {
				$supplier_id = isset( $item['supplier_id'] ) ? intval( $item['supplier_id'] ) : 0;
				$amount = isset( $item['amount'] ) ? floatval( $item['amount'] ) : 0;
				$tax_rate = isset( $item['tax_rate'] ) ? floatval( $item['tax_rate'] ) : 10.00;
				
				// 協力会社の適格請求書ナンバーを確認
				$qualified_invoice_number = $this->get_supplier_qualified_invoice_number( $supplier_id );
				$has_qualified_invoice = ! empty( $qualified_invoice_number );
				
				if ( $has_qualified_invoice ) {
					// 適格請求書がある場合：税抜金額のみをコストとする（仕入税額控除可能）
					$tax_amount = $amount * ( $tax_rate / 100 ) / ( 1 + $tax_rate / 100 );
					$cost_amount = $amount - $tax_amount;
					$qualified_invoice_cost += $cost_amount;
					$total_cost += $cost_amount;
					
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( "KTPWP Profit Calculation - Supplier ID {$supplier_id} has qualified invoice: {$qualified_invoice_number}, Amount: {$amount}, Tax: {$tax_amount}, Cost: {$cost_amount}" );
					}
				} else {
					// 適格請求書がない場合：税込金額をコストとする（仕入税額控除不可）
					$non_qualified_invoice_cost += $amount;
					$total_cost += $amount;
					
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( "KTPWP Profit Calculation - Supplier ID {$supplier_id} has no qualified invoice, Amount: {$amount}, Cost: {$amount}" );
					}
				}
			}
			
			// 利益計算
			$profit = $invoice_total_with_tax - $total_cost;
			
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( "KTPWP Profit Calculation Summary - Order ID: {$order_id}, Invoice Total: {$invoice_total_with_tax}, Qualified Cost: {$qualified_invoice_cost}, Non-Qualified Cost: {$non_qualified_invoice_cost}, Total Cost: {$total_cost}, Profit: {$profit}" );
			}
			
			return $profit;
		}

		/**
		 * Generate email content for different order statuses
		 *
		 * @since 1.0.0
		 * @param object $order Order object
		 * @param string $my_company Company name
		 * @return array Email subject and body
		 */
		public function generate_email_content( $order, $my_company ) {
			if ( ! $order || ! is_object( $order ) ) {
				return array(
					'subject' => '',
					'body' => '',
				);
			}

			$order_items = KTPWP_Order_Items::get_instance();

			// Get invoice items list and amount from actual database
			$invoice_items_from_db = $order_items->get_invoice_items( $order->id );
			$amount = 0;
			$invoice_list = '';

			if ( ! empty( $invoice_items_from_db ) ) {
				// Actual invoice item data exists
				$invoice_list = "\n";
				$max_length = 0;
				$item_lines = array();

				foreach ( $invoice_items_from_db as $item ) {
					$product_name = isset( $item['product_name'] ) ? sanitize_text_field( $item['product_name'] ) : '';
					$item_amount = isset( $item['amount'] ) ? floatval( $item['amount'] ) : 0;
					$price = isset( $item['price'] ) ? floatval( $item['price'] ) : 0;
					$quantity = isset( $item['quantity'] ) ? floatval( $item['quantity'] ) : 0;
					$unit = isset( $item['unit'] ) ? sanitize_text_field( $item['unit'] ) : '';
					$remarks = isset( $item['remarks'] ) ? sanitize_text_field( $item['remarks'] ) : '';

					// 小数点以下の不要な0を削除
					$price_display = rtrim( rtrim( number_format( $price, 6, '.', '' ), '0' ), '.' );
					$quantity_display = rtrim( rtrim( number_format( $quantity, 6, '.', '' ), '0' ), '.' );

					$amount += $item_amount;

					if ( $product_name ) {
						$line = sprintf(
                            '%s  %s円 x %s%s = %s円',
                            $product_name,
                            $price_display,
                            $quantity_display,
                            $unit,
                            number_format( $item_amount )
						);
						if ( $remarks ) {
							$line .= ' (' . $remarks . ')';
						}
						$item_lines[] = $line;
						$max_length = max( $max_length, mb_strlen( $line ) );
					}
				}

				$invoice_list .= implode( "\n", $item_lines );
				$invoice_list .= "\n" . str_repeat( '-', $max_length );
				$invoice_list .= "\n合計：" . number_format( ceil( $amount ) ) . '円';
			} else {
				// No invoice item data, try JSON data (old format)
				$invoice_items_json = $order->invoice_items ? sanitize_textarea_field( $order->invoice_items ) : '';
				if ( $invoice_items_json ) {
					$items = @json_decode( $invoice_items_json, true );
					if ( is_array( $items ) ) {
						$invoice_list = "\n";
						foreach ( $items as $item ) {
							$amount += isset( $item['amount'] ) ? floatval( $item['amount'] ) : 0;
							$product_name = isset( $item['name'] ) ? sanitize_text_field( $item['name'] ) : '';
							$price = isset( $item['price'] ) ? floatval( $item['price'] ) : 0;
							$quantity = isset( $item['quantity'] ) ? floatval( $item['quantity'] ) : 1;
							$unit = isset( $item['unit'] ) ? sanitize_text_field( $item['unit'] ) : '';
							$remarks = isset( $item['remarks'] ) ? sanitize_text_field( $item['remarks'] ) : '';

							// 小数点以下の不要な0を削除
							$price_display = rtrim( rtrim( number_format( $price, 6, '.', '' ), '0' ), '.' );
							$quantity_display = rtrim( rtrim( number_format( $quantity, 6, '.', '' ), '0' ), '.' );

							if ( $product_name ) {
								$invoice_list .= sprintf(
                                    '%s  %s円 x %s%s = %s円',
                                    $product_name,
                                    $price_display,
                                    $quantity_display,
                                    $unit,
                                    number_format( $price * $quantity )
								);
								if ( $remarks ) {
									$invoice_list .= '　※ ' . $remarks;
								}
								$invoice_list .= "\n";
							}
						}
						$invoice_list .= '合計：' . number_format( ceil( $amount ) ) . '円';
					}
				}
			}

			// Company and admin name
			$my_name = '';

			// Generate subject and body by progress status
			$progress = absint( $order->progress );
			$project_name = $order->project_name ? sanitize_text_field( $order->project_name ) : '';
			$customer_name = sanitize_text_field( $order->customer_name );
			$user_name = sanitize_text_field( $order->user_name );

			// 会社名が0や空の場合のフォールバック処理
			if ( $customer_name === '0' || empty( $customer_name ) ) {
				global $wpdb;
				$client_table = $wpdb->prefix . 'ktp_client';
				
				// 顧客IDがある場合は顧客テーブルから会社名を取得
				if ( ! empty( $order->client_id ) ) {
					$client_company_name = $wpdb->get_var(
						$wpdb->prepare(
							"SELECT company_name FROM `{$client_table}` WHERE id = %d",
							$order->client_id
						)
					);
					if ( ! empty( $client_company_name ) ) {
						$customer_name = $client_company_name;
					} else {
						// 顧客テーブルからも取得できない場合は担当者名を使用
						$customer_name = $user_name;
					}
				} else {
					// 顧客IDがない場合は担当者名を使用
					$customer_name = $user_name;
				}
			}

			// 進捗に応じた帳票タイトルと件名を設定
			$document_titles = array(
				1 => '見積り書',
				2 => '注文受書',
				3 => '納品書',
				4 => '請求書',
				5 => '領収書',
				6 => '案件完了',
			);

			$document_messages = array(
				1 => 'につきましてお見積りいたします。',
				2 => 'につきましてご注文をお受けしました。',
				3 => 'につきまして完了しました。',
				4 => 'につきまして請求申し上げます。',
				5 => 'につきましてお支払いを確認しました。',
				6 => 'につきましては全て完了しています。',
			);

			$document_title = isset( $document_titles[ $progress ] ) ? $document_titles[ $progress ] : '受注書';
			$document_message = isset( $document_messages[ $progress ] ) ? $document_messages[ $progress ] : '';

			// 適格請求書番号を取得し、請求書の場合に追加
			if ( $progress === 4 && class_exists( 'KTP_Settings' ) ) {
				$qualified_invoice_number = KTP_Settings::get_qualified_invoice_number();
				if ( ! empty( $qualified_invoice_number ) ) {
					$document_title = $document_title . ' 適格請求書番号：' . $qualified_invoice_number;
				}
			}

			// 日付フォーマット
			$order_date = date( 'Y年m月d日', $order->time );

			// 件名と本文の統一フォーマット
			$subject = "{$document_title}：{$project_name}";
			$body = "{$customer_name}\n{$user_name} 様\n\nお世話になります。\n\n＜{$document_title}＞\nID: {$order->id} [{$order_date}]\n\n「{$project_name}」{$document_message}\n\n請求項目\n{$invoice_list}\n\n--\n{$my_company}";

			return array(
				'subject' => $subject,
				'body' => $body,
			);
		}
	} // End of KTPWP_Order_UI class

} // class_exists check
