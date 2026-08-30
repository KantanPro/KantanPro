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
						"SELECT * FROM `{$order_table}` WHERE id = %d",
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
				if ( class_exists( 'KTPWP_Invoice_Line_Amount' ) ) {
					$total_amount += KTPWP_Invoice_Line_Amount::resolve_line_amount( $item );
				} else {
					$total_amount += isset( $item['amount'] ) ? floatval( $item['amount'] ) : 0;
				}
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
			$html .= '<th class="actions-column">' . esc_html__( '操作', 'kantanpro' ) . '</th>';
			$html .= '<th>' . esc_html__( 'サービス', 'kantanpro' ) . '</th>';
			$html .= '<th style="text-align:left;">' . esc_html__( '単価', 'kantanpro' ) . '</th>';
			$html .= '<th style="text-align:left;">' . esc_html__( '数量', 'kantanpro' ) . '</th>';
			$html .= '<th>' . esc_html__( '単位', 'kantanpro' ) . '</th>';
            $html .= '<th style="text-align:left;">' . esc_html__( '金額', 'kantanpro' ) . '</th>';
            if ( class_exists( 'KTPWP_Tax_Policy' ) && KTPWP_Tax_Policy::hide_tax_columns() ) {
                // 税率/税額列を非表示
            } else {
                $html .= '<th style="text-align:left;">' . esc_html__( '税率', 'kantanpro' ) . '</th>';
            }
			$html .= '<th>' . esc_html__( '備考', 'kantanpro' ) . '</th>';
			$html .= '</tr>';
			$html .= '</thead>';
			$html .= '<tbody>';

			$invoice_stripe_index = 0;
			foreach ( $items as $index => $item ) {
				$row_id = isset( $item['id'] ) ? intval( $item['id'] ) : 0;
				$is_provisional = ! empty( $item['is_provisional'] );
				$row_lock_attr  = $is_provisional ? ' readonly tabindex="-1"' : '';
				$btn_disabled   = $is_provisional ? ' disabled' : '';
				$line_amount = class_exists( 'KTPWP_Invoice_Line_Amount' )
					? KTPWP_Invoice_Line_Amount::resolve_line_amount( $item )
					: ( isset( $item['amount'] ) ? floatval( $item['amount'] ) : 0 );
				// nth-of-typeだと非表示/参考行の数え方でズレるため、表示順に明示的な偶奇クラスを振る（参考行は対象外）
				$invoice_stripe_class = '';
				if ( ! $is_provisional ) {
					// $invoice_stripe_index は0始まりのため、0番目が人間の数える「1行目＝奇数行」になる
					$invoice_stripe_class = ( $invoice_stripe_index % 2 === 0 ) ? 'invoice-item-row--odd' : 'invoice-item-row--even';
					++$invoice_stripe_index;
				}
				$html .= '<tr class="invoice-item-row' . ( $is_provisional ? ' invoice-item-row--provisional' : ' ' . $invoice_stripe_class ) . '" data-row-id="' . $row_id . '" data-is-provisional="' . ( $is_provisional ? '1' : '0' ) . '">';
				// Actions column with drag handle and buttons
				$html .= '<td class="actions-column">';
				$html .= '<span class="drag-handle' . ( $is_provisional ? ' is-disabled' : '' ) . '" title="' . esc_attr__( 'ドラッグして並び替え', 'kantanpro' ) . '">&#9776;</span>';
				$html .= '<button type="button" class="btn-add-row"' . $btn_disabled . ' title="' . esc_attr__( '行を追加', 'kantanpro' ) . '">+</button>';
				// --- ここを修正: 1行目でも常に削除ボタンを出力 ---
				$html .= '<button type="button" class="btn-delete-row"' . $btn_disabled . ' title="' . esc_attr__( '行を削除', 'kantanpro' ) . '">×</button>';
				$html .= '<button type="button" class="btn-move-row"' . $btn_disabled . ' title="' . esc_attr__( 'サービス選択', 'kantanpro' ) . '">></button>';
				$html .= '</td>';

				// Product name
				$html .= '<td>';
				$html .= '<input type="text" name="invoice_items[' . $index . '][product_name]" ';
				$html .= 'value="' . esc_attr( $item['product_name'] ) . '" ';
				$html .= 'class="invoice-item-input product-name"' . $row_lock_attr . ' />';
				$html .= '<input type="hidden" name="invoice_items[' . $index . '][id]" value="' . $row_id . '" />';
				$html .= '<input type="hidden" name="invoice_items[' . $index . '][is_provisional]" class="invoice-item-is-provisional" value="' . esc_attr( $is_provisional ? '1' : '0' ) . '" />';
				$html .= '</td>';

				// Price
				$html .= '<td style="text-align:left;">';
				$price_raw = floatval( $item['price'] );
				$quantity_raw_for_display = floatval( $item['quantity'] );
				$price_display = class_exists( 'KTPWP_Order_Invoice_Document_Display' )
					? KTPWP_Order_Invoice_Document_Display::input_value( $item['product_name'], $price_raw, $quantity_raw_for_display, 'price' )
					: rtrim( rtrim( number_format( $price_raw, 6, '.', '' ), '0' ), '.' );
				$html .= '<input type="number" name="invoice_items[' . $index . '][price]" ';
				$html .= 'value="' . esc_attr( $price_display ) . '" ';
				$html .= 'class="invoice-item-input price" step="1" min="0" style="text-align:left;"' . $row_lock_attr . ' />';
				$html .= '</td>';

				// Quantity
				$html .= '<td style="text-align:left;">';
				$quantity_raw = floatval( $item['quantity'] );
				$quantity_display = class_exists( 'KTPWP_Order_Invoice_Document_Display' )
					? KTPWP_Order_Invoice_Document_Display::input_value( $item['product_name'], $price_raw, $quantity_raw, 'quantity' )
					: rtrim( rtrim( number_format( $quantity_raw, 6, '.', '' ), '0' ), '.' );
				$html .= '<input type="number" name="invoice_items[' . $index . '][quantity]" ';
				$html .= 'value="' . esc_attr( $quantity_display ) . '" ';
				$html .= 'class="invoice-item-input quantity" step="1" min="0" style="text-align:left;"' . $row_lock_attr . ' />';
				$html .= '</td>';



				// Unit
				$html .= '<td>';
				$html .= '<input type="text" name="invoice_items[' . $index . '][unit]" ';
				$html .= 'value="' . esc_attr( $item['unit'] ) . '" ';
				$html .= 'class="invoice-item-input unit"' . $row_lock_attr . ' />';
				$html .= '</td>';

				// Amount
				$html .= '<td style="text-align:left;">';
				$amount_input_display = class_exists( 'KTPWP_Order_Invoice_Document_Display' )
					? KTPWP_Order_Invoice_Document_Display::input_value( $item['product_name'], $price_raw, $quantity_raw, 'amount', $line_amount )
					: (string) (int) round( $line_amount );
				$amount_span_text = $amount_input_display === ''
					? ''
					: number_format( (int) round( $line_amount ) );
				$html .= '<span class="invoice-item-amount" data-amount="' . esc_attr( (string) (int) round( $line_amount ) ) . '" style="display:inline-block;min-width:80px;text-align:left;">' . esc_html( $amount_span_text ) . '</span>';
				$html .= '<input type="hidden" name="invoice_items[' . $index . '][amount]" value="' . esc_attr( (string) (int) round( $line_amount ) ) . '" />';
				$html .= '</td>';



                // Tax Rate（モードに応じて非表示/ロック/一律適用）
                if ( ! ( class_exists( 'KTPWP_Tax_Policy' ) && KTPWP_Tax_Policy::hide_tax_columns() ) ) {
                    $tax_rate_raw = isset( $item['tax_rate'] ) ? $item['tax_rate'] : null;
                    $effective_rate = class_exists( 'KTPWP_Tax_Policy' ) ? KTPWP_Tax_Policy::get_effective_rate( $tax_rate_raw ) : null;
                    $tax_rate_display = ( $effective_rate !== null ) ? $effective_rate : '';
                    $tax_readonly = ( class_exists( 'KTPWP_Tax_Policy' ) && KTPWP_Tax_Policy::lock_line_tax_rate() ) ? 'readonly' : '';
                    if ( $is_provisional ) {
                        $tax_readonly = 'readonly tabindex="-1"';
                    }
                    $html .= '<td style="text-align:left;">';
                    $html .= '<div style="display:inline-flex;align-items:center;margin-left:0;padding-left:0;">';
                    $html .= '<input type="number" name="invoice_items[' . $index . '][tax_rate]" ';
                    $html .= 'value="' . esc_attr( $tax_rate_display ) . '" ';
                    $html .= 'class="invoice-item-input tax-rate" step="1" min="0" max="100" style="width:50px; text-align:right; display:inline-block; margin-left:0; padding-left:0;" ' . $tax_readonly . ' />';
                    $html .= '<span style="margin-left:2px; white-space:nowrap;">%</span>';
                    $html .= '</div>';
                    $html .= '</td>';
                }

				// Remarks
				$html .= '<td>';
				$html .= '<input type="text" name="invoice_items[' . $index . '][remarks]" ';
				$html .= 'value="' . esc_attr( $item['remarks'] ) . '" ';
				$html .= 'class="invoice-item-input remarks"' . $row_lock_attr . ' />';
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
				// 内税表示の場合：合計金額から内税を計算
				$tax_amount = ceil( $total_amount * 0.10 / 1.10 );
			}
			
			$total_with_tax = $total_amount + $tax_amount;
			$total_amount_ceiled = ceil( $total_amount );
			$tax_amount_ceiled = ceil( $tax_amount );
			$total_with_tax_ceiled = ceil( $total_with_tax );

			$has_provisional_rows = false;
			foreach ( $items as $item ) {
				if ( ! empty( $item['is_provisional'] ) ) {
					$has_provisional_rows = true;
					break;
				}
			}
			$total_label = $has_provisional_rows
				? __( '今回請求合計', 'kantanpro' )
				: __( '金額合計', 'kantanpro' );
			
			// 税区分に応じた合計表示
			if ( $tax_category === '外税' ) {
				// 外税表示の場合：3行表示
				$html .= '<div class="invoice-items-total" style="text-align:right;margin-top:8px;font-weight:bold;">';
				$html .= esc_html__( '合計金額', 'kantanpro' ) . ' : ' . esc_html( KTPWP_Settings::format_money( $total_amount_ceiled ) );
				$html .= '</div>';
				
				// Tax amount display
				$html .= '<div class="invoice-items-tax" style="text-align:right;margin-top:4px;color:#666;">';
				$html .= esc_html__( '消費税', 'kantanpro' ) . ' : ' . esc_html( KTPWP_Settings::format_money( $tax_amount_ceiled ) );
				$html .= '</div>';
				
				// Total with tax display
				$html .= '<div class="invoice-items-total-with-tax" style="text-align:right;margin-top:4px;font-weight:bold;color:#d32f2f;">';
				$html .= esc_html__( '税込合計', 'kantanpro' ) . ' : ' . esc_html( KTPWP_Settings::format_money( $total_with_tax_ceiled ) );
				$html .= '</div>';
			} else {
				// 内税表示の場合：合計と内税を1行表示
				$total_display = esc_html( $total_label ) . ' : ' . esc_html( KTPWP_Settings::format_money( $total_amount_ceiled ) );
				if ( $tax_amount_ceiled > 0 && ! ( class_exists( 'KTPWP_Tax_Policy' ) && ( KTPWP_Tax_Policy::is_abolished() || KTPWP_Tax_Policy::hide_tax_columns() ) ) ) {
					$total_display .= ' (' . esc_html__( '内税', 'kantanpro' ) . ' : ' . esc_html( KTPWP_Settings::format_money( $tax_amount_ceiled ) ) . ')';
				}
				$html .= '<div class="invoice-items-total" style="text-align:right;margin-top:8px;font-weight:bold;">';
				$html .= $total_display;
				$html .= '</div>';
				$html .= '<div class="invoice-items-tax" style="text-align:right;margin-top:4px;color:#666;"></div>';
				
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
			// supplier_id / purchase_status / purchase_status_date / purchase_status_datesカラムがなければ自動追加
			if ( class_exists( 'KTPWP_Order_Items' ) ) {
				$order_items = KTPWP_Order_Items::get_instance();
				$order_items->add_supplier_id_column_if_missing();
				$order_items->add_purchase_status_column_if_missing();
				$order_items->add_purchase_status_date_column_if_missing();
				$order_items->add_purchase_status_dates_column_if_missing();
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
            require_once __DIR__ . '/class-ktpwp-supplier-data.php';
            $supplier_data = new KTPWP_Supplier_Data();
            $total_amount = 0;
            $total_tax_amount = 0;
            $has_outtax = false;
            foreach ( $items as $item ) {
                $item_amount = isset( $item['amount'] ) ? floatval( $item['amount'] ) : 0;
                $item_tax_rate_raw = isset( $item['tax_rate'] ) ? $item['tax_rate'] : null;
                $supplier_id = isset( $item['supplier_id'] ) ? intval( $item['supplier_id'] ) : 0;
                $purchase = isset( $item['purchase'] ) ? $item['purchase'] : '';
                
                // ダミーデータの場合は内税として扱う
                if ( $purchase === 'ダミーデータ' || $purchase === 'Dummy Data' ) {
                    $item_tax_category = '内税';
                } else {
                    $item_tax_category = $supplier_data->get_tax_category_by_supplier_id( $supplier_id );
                }
                
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
			$html .= '<th class="actions-column">' . esc_html__( '操作', 'kantanpro' ) . '</th>';
			$html .= '<th>' . esc_html__( 'サービス', 'kantanpro' ) . '</th>';
			$html .= '<th style="text-align:left;">' . esc_html__( '単価', 'kantanpro' ) . '</th>';
			$html .= '<th style="text-align:left;">' . esc_html__( '数量', 'kantanpro' ) . '</th>';
			$html .= '<th>' . esc_html__( '単位', 'kantanpro' ) . '</th>';
			$html .= '<th style="text-align:left;">' . esc_html__( '金額', 'kantanpro' ) . '</th>';
			$html .= '<th style="text-align:left;">' . esc_html__( '税率', 'kantanpro' ) . '</th>';
			$html .= '<th>' . esc_html__( '備考', 'kantanpro' ) . '</th>';
			$html .= '<th>' . esc_html__( '仕入', 'kantanpro' ) . '</th>';
			$html .= '</tr>';
			$html .= '</thead>';
			$html .= '<tbody>';

			foreach ( $items as $index => $item ) {
				$row_id = isset( $item['id'] ) ? intval( $item['id'] ) : 0;
				$supplier_id_for_row = isset( $item['supplier_id'] ) ? intval( $item['supplier_id'] ) : 0;
				// 仕入記録行を挟んでも交互配色が崩れないよう、コスト項目1件単位で偶奇クラスを付与する
				// $index は0始まりのため、0番目が人間の数える「1行目＝奇数行」になる
				$stripe_class = ( $index % 2 === 0 ) ? 'cost-item-row--odd' : 'cost-item-row--even';
				$html .= '<tr class="cost-item-row ' . $stripe_class . '" data-row-id="' . $row_id . '" data-supplier-id="' . $supplier_id_for_row . '">';
				// Actions column with drag handle and buttons
				$html .= '<td class="actions-column">';
				$html .= '<span class="drag-handle" title="' . esc_attr__( 'ドラッグして並び替え', 'kantanpro' ) . '">&#9776;</span>';
				$html .= '<button type="button" class="btn-add-row" title="' . esc_attr__( '行を追加', 'kantanpro' ) . '">+</button>';
				// --- ここを修正: 1行目でも常に削除ボタンを出力 ---
				$html .= '<button type="button" class="btn-delete-row" title="' . esc_attr__( '行を削除', 'kantanpro' ) . '">×</button>';
				$html .= '<button type="button" class="btn-move-row" title="' . esc_attr__( '行を移動', 'kantanpro' ) . '">></button>';
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
				$html .= '<span class="cost-item-amount" data-amount="' . esc_attr( $item['amount'] ) . '" style="display:inline-block;min-width:80px;text-align:left;">' . esc_html( number_format( $item['amount'] ) ) . '</span>';
				$html .= '<input type="hidden" name="cost_items[' . $index . '][amount]" value="' . esc_attr( $item['amount'] ) . '" />';
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

				// Purchase (仕入) : プルダウンのみ。協力会社名や仕入記録（見積依頼／発注／仕入完了の各日付）は下の結合行に表示する
				$purchase_value = isset( $item['purchase'] ) ? $item['purchase'] : '';
				$purchase_status = isset( $item['purchase_status'] ) && $item['purchase_status'] !== '' ? $item['purchase_status'] : 'pending';
				$purchase_status_dates_json = isset( $item['purchase_status_dates'] ) ? $item['purchase_status_dates'] : '';
				$status_history_label = KTPWP_Order_Items::format_purchase_status_history_label( $purchase_status_dates_json );
				$html .= '<td class="purchase-cell">';
				$html .= $this->render_purchase_status_select( $purchase_status, $supplier_id_for_row, $purchase_value, $row_id );
				$html .= '<input type="hidden" name="cost_items[' . $index . '][purchase]" ';
				$html .= 'value="' . esc_attr( $purchase_value ) . '" />';
				$html .= '</td>';

				$html .= '</tr>';

				// 仕入記録行（協力会社名＋見積依頼／発注／仕入完了の各日付）。今後長くなっていくため全カラムを結合した専用行に表示する。
				// 協力会社が未設定の場合は「協力会社を選ぶ」リンクを表示する。
				$html .= '<tr class="cost-item-purchase-record-row ' . $stripe_class . '" data-row-id="' . $row_id . '">';
				$html .= '<td colspan="9" class="purchase-record-cell">';
				if ( ! empty( $purchase_value ) ) {
					$html .= '<span class="purchase-display" data-purchase="' . esc_attr( $purchase_value ) . '">' . esc_html( $purchase_value );
					if ( $status_history_label !== '' ) {
						$html .= '<span class="purchase-status-date"> ' . esc_html( $status_history_label ) . '</span>';
					}
					$html .= '</span>';
				} else {
					$html .= '<button type="button" class="purchase-choose-supplier-link">' . esc_html__( '協力会社を選ぶ', 'kantanpro' ) . '</button>';
				}
				$html .= '</td>';
				$html .= '</tr>';
			}

			$html .= '</tbody>';
			$html .= '</table>';
			$html .= '</div>'; // cost-items-scroll-wrapper

            // 税制モードに応じたコスト合計の上書き
            if ( class_exists( 'KTPWP_Tax_Policy' ) ) {
                if ( KTPWP_Tax_Policy::is_abolished() ) {
                    $total_tax_amount = 0;
                    $total_with_tax = $total_amount;
                    $total_amount_ceiled = ceil( $total_amount );
                    $total_tax_amount_ceiled = 0;
                    $total_with_tax_ceiled = $total_amount_ceiled;
                    $has_outtax = false;
                } elseif ( KTPWP_Tax_Policy::is_unified() ) {
                    $unified_rate = KTPWP_Tax_Policy::get_unified_tax_rate();
                    $total_tax_amount = 0;
                    if ( $unified_rate > 0 ) {
                        if ( $has_outtax ) {
                            $total_tax_amount = ceil( $total_amount * ( $unified_rate / 100 ) );
                            $total_with_tax = $total_amount + $total_tax_amount;
                        } else {
                            $total_tax_amount = ceil( $total_amount * ( $unified_rate / 100 ) / ( 1 + $unified_rate / 100 ) );
                            $total_with_tax = $total_amount;
                        }
                    } else {
                        $total_with_tax = $total_amount;
                    }
                    $total_amount_ceiled = ceil( $total_amount );
                    $total_tax_amount_ceiled = ceil( $total_tax_amount );
                    $total_with_tax_ceiled = ceil( $total_with_tax );
                }
            }

            // 税区分に応じた合計表示（ここも修正）
            if ( $has_outtax ) {
                // 外税行が1つでもあれば外税3行表示
                $html .= '<div class="cost-items-total" style="text-align:right;margin-top:8px;font-weight:bold;">';
                $html .= esc_html__( '金額合計', 'kantanpro' ) . ' : ' . esc_html( KTPWP_Settings::format_money( $total_amount_ceiled ) );
                $html .= '</div>';
                $html .= '<div class="cost-items-tax" style="text-align:right;margin-top:4px;color:#666;">';
                $html .= esc_html__( '消費税', 'kantanpro' ) . ' : ' . esc_html( KTPWP_Settings::format_money( $total_tax_amount_ceiled ) );
                $html .= '</div>';
                $html .= '<div class="cost-items-total-with-tax" style="text-align:right;margin-top:4px;font-weight:bold;color:#d32f2f;">';
                $html .= esc_html__( '税込合計', 'kantanpro' ) . ' : ' . esc_html( KTPWP_Settings::format_money( $total_with_tax_ceiled ) );
                $html .= '</div>';
            } else {
                // 全て内税なら内税1行表示
                $html .= '<div class="cost-items-total" style="text-align:right;margin-top:8px;font-weight:bold;">';
                if ( class_exists( 'KTPWP_Tax_Policy' ) && ( KTPWP_Tax_Policy::is_abolished() || KTPWP_Tax_Policy::hide_tax_columns() ) ) {
                    $html .= esc_html__( '金額合計', 'kantanpro' ) . ' : ' . esc_html( KTPWP_Settings::format_money( $total_amount_ceiled ) );
                } else {
                    $html .= esc_html__( '金額合計', 'kantanpro' ) . ' : ' . esc_html( KTPWP_Settings::format_money( $total_amount_ceiled ) ) . ' (' . esc_html__( '内税', 'kantanpro' ) . ' : ' . esc_html( KTPWP_Settings::format_money( $total_tax_amount_ceiled ) ) . ')';
                }
                $html .= '</div>';
                $html .= '<div class="cost-items-tax" style="text-align:right;margin-top:4px;color:#666;' . ( ( class_exists( 'KTPWP_Tax_Policy' ) && ( KTPWP_Tax_Policy::is_abolished() || KTPWP_Tax_Policy::hide_tax_columns() ) ) ? 'display:none;' : '' ) . '"></div>';
                $html .= '<div class="cost-items-total-with-tax" style="text-align:right;margin-top:4px;font-weight:bold;color:#d32f2f;' . ( ( class_exists( 'KTPWP_Tax_Policy' ) && ( KTPWP_Tax_Policy::is_abolished() || KTPWP_Tax_Policy::hide_tax_columns() ) ) ? 'display:none;' : '' ) . '"></div>';
            }

			// Profit calculation similar to invoice items（請求は税込売上ベースで統一）
			$invoice_items = $order_items->get_invoice_items( $order_id );
			$invoice_total_with_tax_ceiled = ceil( $this->get_invoice_tax_inclusive_total_for_profit( $order_id, $invoice_items ) );
			
			// 適格請求書ナンバーを考慮した利益計算
			$profit_data = $this->calculate_profit_with_qualified_invoice( $order_id, $items, $invoice_total_with_tax_ceiled );
			$profit = $profit_data['profit'];
			$qualified_invoice_cost = $profit_data['qualified_invoice_cost'];
			$non_qualified_invoice_cost = $profit_data['non_qualified_invoice_cost'];



            // Profit display (using tax-inclusive values)
            $profit_color = $profit >= 0 ? '#28a745' : '#dc3545';  // Green for profit, red for loss
            $html .= '<div class="profit-display" style="text-align:right;margin-top:8px;font-weight:bold;color:' . $profit_color . ';">';
            $html .= esc_html__( '利益', 'kantanpro' ) . ' : ' . esc_html( KTPWP_Settings::format_money( $profit ) );
            // 税廃止モード/税列非表示の場合は内訳を表示しない
            $hide_breakdown = ( class_exists( 'KTPWP_Tax_Policy' ) && ( KTPWP_Tax_Policy::is_abolished() || KTPWP_Tax_Policy::hide_tax_columns() ) );
            if ( ! $hide_breakdown ) {
                $html .= ' (' . esc_html__( '適格請求書コスト', 'kantanpro' ) . ': ' . esc_html( KTPWP_Settings::format_money( ceil( $qualified_invoice_cost ) ) ) . ', ' . esc_html__( '非適格請求書コスト', 'kantanpro' ) . ': ' . esc_html( KTPWP_Settings::format_money( ceil( $non_qualified_invoice_cost ) ) ) . ')';
            }
            $html .= '</div>';

			$html .= '</form>';
			$html .= '</div>';

			return $html;
		}

		/**
		 * コスト項目「仕入」列の発注ステータス選択プルダウンHTMLを生成
		 *
		 * 協力会社が未設定（手入力）の行にも表示する。その場合はメール送信対象がないため
		 * 「見積依頼」「発注」を選ぶと協力会社選択を促すダイアログを表示し、
		 * 「未着手」「仕入完了」は行単位でそのまま保存する（JS側で分岐）。
		 *
		 * @since 1.2.0
		 * @param string $status         現在の purchase_status（pending/quote_requested/ordered/completed）
		 * @param int    $supplier_id    協力会社ID（未設定なら0）
		 * @param string $purchase_value 「会社名 > 品目名」または会社名のみの文字列（未設定なら空文字）
		 * @param int    $item_id        コスト項目ID（未保存の新規行なら0）
		 * @return string
		 */
		private function render_purchase_status_select( $status, $supplier_id, $purchase_value, $item_id = 0 ) {
			if ( ! class_exists( 'KTPWP_Order_Items' ) ) {
				return '';
			}
			$supplier_name = $purchase_value;
			if ( strpos( $purchase_value, ' > ' ) !== false ) {
				$parts = explode( ' > ', $purchase_value, 2 );
				$supplier_name = $parts[0];
			}

			$options = KTPWP_Order_Items::get_purchase_status_options();
			if ( ! array_key_exists( $status, $options ) ) {
				$status = 'pending';
			}

			$html = '<select class="cost-item-purchase-status" data-item-id="' . esc_attr( intval( $item_id ) ) . '" data-supplier-id="' . esc_attr( intval( $supplier_id ) ) . '" data-supplier-name="' . esc_attr( $supplier_name ) . '">';
			foreach ( $options as $value => $label ) {
				$html .= '<option value="' . esc_attr( $value ) . '"' . selected( $status, $value, false ) . '>' . esc_html( $label ) . '</option>';
			}
			$html .= '</select>';

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
		 * 明細行の金額。amount が 0・未同期のときは単価×数量（画面・JS と整合）。
		 *
		 * @param array $item invoice_items / cost_items の1行。
		 * @return float
		 */
		private function resolve_order_line_amount_from_item( array $item ) {
			if ( class_exists( 'KTPWP_Invoice_Line_Amount' ) ) {
				return KTPWP_Invoice_Line_Amount::resolve_line_amount( $item );
			}

			$price = isset( $item['price'] ) ? floatval( $item['price'] ) : 0.0;
			$qty   = isset( $item['quantity'] ) ? floatval( $item['quantity'] ) : 0.0;
			$from_pq = $price * $qty;
			if ( ! isset( $item['amount'] ) || $item['amount'] === '' || ! is_numeric( $item['amount'] ) ) {
				return $from_pq;
			}
			$from_amount = floatval( $item['amount'] );
			if ( $from_amount < 0 ) {
				return $from_amount;
			}
			if ( $from_amount > 0 ) {
				return $from_amount;
			}
			if ( $from_pq > 0 ) {
				return $from_pq;
			}
			return 0.0;
		}

		/**
		 * 利益計算用の請求ベース（顧客が外税のときは税抜合計＋外税相当、それ以外は行金額の合計＝税込扱い）。
		 *
		 * @param int   $order_id      Order ID.
		 * @param array $invoice_items Invoice rows.
		 * @return float
		 */
		private function get_invoice_tax_inclusive_total_for_profit( $order_id, array $invoice_items ) {
			if ( empty( $invoice_items ) ) {
				return 0.0;
			}
			$tax_category = $this->get_client_tax_category( $order_id );
			$sum_lines = 0.0;
			foreach ( $invoice_items as $row ) {
				$sum_lines += $this->resolve_order_line_amount_from_item( $row );
			}
			if ( $tax_category !== '外税' ) {
				return $sum_lines;
			}
			$tax_sum = 0.0;
			foreach ( $invoice_items as $row ) {
				$line = $this->resolve_order_line_amount_from_item( $row );
				$raw_rate = isset( $row['tax_rate'] ) ? $row['tax_rate'] : null;
				$rate = class_exists( 'KTPWP_Tax_Policy' ) ? KTPWP_Tax_Policy::get_effective_rate( $raw_rate ) : null;
				if ( $rate !== null && $rate > 0 ) {
					$tax_sum += ceil( $line * ( $rate / 100 ) );
				}
			}
			return $sum_lines + $tax_sum;
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
				$amount = $this->resolve_order_line_amount_from_item( $item );
				$tax_rate_raw = isset( $item['tax_rate'] ) ? $item['tax_rate'] : null;
				$tax_rate = ( $tax_rate_raw !== null && $tax_rate_raw !== '' && is_numeric( $tax_rate_raw ) ) ? floatval( $tax_rate_raw ) : 10.00;
				$purchase = isset( $item['purchase'] ) ? $item['purchase'] : '';
				$qualified_invoice_number = isset( $item['qualified_invoice_number'] ) ? $item['qualified_invoice_number'] : '';
				
				// ダミーデータ仕入れ先の場合は、適格請求書の有無に関係なく税込金額をそのままコストとする
				if ( strpos( $purchase, 'ダミーデータ' ) !== false || strpos( $purchase, 'Dummy Data' ) !== false ) {
					$has_qualified_invoice = false; // ダミーデータは常に税込金額をコストとする
				} else {
					// 通常の協力会社の場合は、協力会社の適格請求書ナンバーを確認
					$qualified_invoice_number = $this->get_supplier_qualified_invoice_number( $supplier_id );
					$has_qualified_invoice = ! empty( $qualified_invoice_number );
				}
				
				if ( $has_qualified_invoice ) {
					// 適格請求書がある場合：税抜金額のみをコストとする（仕入税額控除可能）
					$tax_amount = $amount * ( $tax_rate / 100 ) / ( 1 + $tax_rate / 100 );
					$cost_amount = $amount - $tax_amount;
					$qualified_invoice_cost += $cost_amount;
					$total_cost += $cost_amount;
					

				} else {
					// 適格請求書がない場合：税込金額をコストとする（仕入税額控除不可）
					$non_qualified_invoice_cost += $amount;
					$total_cost += $amount;
					

				}
			}
			
			// 利益計算
			$profit = $invoice_total_with_tax - $total_cost;
			

			
			// 利益と内訳情報を配列で返す
			return array(
				'profit' => $profit,
				'qualified_invoice_cost' => $qualified_invoice_cost,
				'non_qualified_invoice_cost' => $non_qualified_invoice_cost,
				'total_cost' => $total_cost
			);
		}

		/**
		 * Get profit data and HTML for a given order_id using existing server-side logic
		 *
		 * Returns both raw values and the rendered HTML snippet used in the UI
		 * so that frontend can update `.profit-display` without page reload.
		 *
		 * @since 1.0.0
		 * @param int $order_id Order ID
		 * @return array{
		 *   profit: float,
		 *   qualified_invoice_cost: float,
		 *   non_qualified_invoice_cost: float,
		 *   color: string,
		 *   html: string
		 * }
		 */
		public function get_profit_data_for_order( $order_id ) {
			if ( ! class_exists( 'KTPWP_Order_Items' ) ) {
				return array(
					'profit' => 0,
					'qualified_invoice_cost' => 0,
					'non_qualified_invoice_cost' => 0,
					'color' => '#28a745',
					'html' => '<div class="profit-display" style="text-align:right;margin-top:8px;font-weight:bold;color:#28a745;">' . esc_html__( '利益', 'kantanpro' ) . ' : ' . esc_html( KTPWP_Settings::format_money( 0 ) ) . '</div>'
				);
			}

			$order_items = KTPWP_Order_Items::get_instance();
			$cost_items  = $order_items->get_cost_items( $order_id );
			$invoice_items = $order_items->get_invoice_items( $order_id );

			$invoice_total_with_tax_ceiled = ceil( $this->get_invoice_tax_inclusive_total_for_profit( $order_id, $invoice_items ) );

			$profit_data = $this->calculate_profit_with_qualified_invoice( $order_id, $cost_items, $invoice_total_with_tax_ceiled );
			$profit = isset( $profit_data['profit'] ) ? floatval( $profit_data['profit'] ) : 0;
			$qualified_invoice_cost = isset( $profit_data['qualified_invoice_cost'] ) ? floatval( $profit_data['qualified_invoice_cost'] ) : 0;
			$non_qualified_invoice_cost = isset( $profit_data['non_qualified_invoice_cost'] ) ? floatval( $profit_data['non_qualified_invoice_cost'] ) : 0;

            $profit_color = $profit >= 0 ? '#28a745' : '#dc3545';
            $html  = '<div class="profit-display" style="text-align:right;margin-top:8px;font-weight:bold;color:' . $profit_color . ';">';
            $html .= esc_html__( '利益', 'kantanpro' ) . ' : ' . esc_html( KTPWP_Settings::format_money( $profit ) );
            $hide_breakdown = ( class_exists( 'KTPWP_Tax_Policy' ) && ( KTPWP_Tax_Policy::is_abolished() || KTPWP_Tax_Policy::hide_tax_columns() ) );
            if ( ! $hide_breakdown ) {
                $html .= ' (' . esc_html__( '適格請求書コスト', 'kantanpro' ) . ': ' . esc_html( KTPWP_Settings::format_money( ceil( $qualified_invoice_cost ) ) ) . ', ' . esc_html__( '非適格請求書コスト', 'kantanpro' ) . ': ' . esc_html( KTPWP_Settings::format_money( ceil( $non_qualified_invoice_cost ) ) ) . ')';
            }
            $html .= '</div>';

			return array(
				'profit' => $profit,
				'qualified_invoice_cost' => $qualified_invoice_cost,
				'non_qualified_invoice_cost' => $non_qualified_invoice_cost,
				'color' => $profit_color,
				'html' => $html,
			);
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
                            '%s  %s x %s%s = %s',
                            $product_name,
                            KTPWP_Settings::format_money( $price ),
                            $quantity_display,
                            $unit,
                            KTPWP_Settings::format_money( $item_amount )
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
				$invoice_list .= "\n合計：" . KTPWP_Settings::format_money( ceil( $amount ) );
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
                                    '%s  %s x %s%s = %s',
                                    $product_name,
                                    KTPWP_Settings::format_money( $price ),
                                    $quantity_display,
                                    $unit,
                                    KTPWP_Settings::format_money( $price * $quantity )
								);
								if ( $remarks ) {
									$invoice_list .= '　※ ' . $remarks;
								}
								$invoice_list .= "\n";
							}
						}
						$invoice_list .= '合計：' . KTPWP_Settings::format_money( ceil( $amount ) );
					}
				}
			}

			// Company and admin name
			$my_name = '';

			// Generate subject and body by progress status
			$progress = absint( $order->progress );
			$project_name = class_exists( 'KTPWP_Order' )
				? KTPWP_Order::project_name_for_content( $order->project_name )
				: ( $order->project_name ? sanitize_text_field( $order->project_name ) : '案件' );
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
			if ( $progress === 4 && class_exists( 'KTPWP_Settings' ) ) {
                $qualified_invoice_number = KTPWP_Settings::get_qualified_invoice_number();
                if ( ! ( class_exists('KTPWP_Tax_Policy') && KTPWP_Tax_Policy::is_abolished() ) && ! empty( $qualified_invoice_number ) ) {
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
