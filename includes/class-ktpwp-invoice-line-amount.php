<?php
/**
 * 請求明細行の金額解決（画面・メール・Stripe 共通）
 *
 * @package KTPWP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'KTPWP_Invoice_Line_Amount' ) ) {

	/**
	 * 請求明細の effective amount とメール分割を一元化する。
	 */
	class KTPWP_Invoice_Line_Amount {

		/**
		 * 初回費用の備考文字列。
		 *
		 * @return string
		 */
		public static function initial_fee_remarks() {
			return class_exists( 'KTPWP_Contract_Billing' )
				? KTPWP_Contract_Billing::INITIAL_FEE_REMARKS
				: '初回のみ';
		}

		/**
		 * 初回費用行か。
		 *
		 * @param array<string, mixed> $item 請求行。
		 * @return bool
		 */
		public static function is_initial_fee_item( array $item ) {
			return trim( (string) ( $item['remarks'] ?? '' ) ) === self::initial_fee_remarks();
		}

		/**
		 * 定期参考行（今回請求しない）か。
		 *
		 * @param array<string, mixed> $item 請求行。
		 * @return bool
		 */
		public static function is_provisional_item( array $item ) {
			return ! empty( $item['is_provisional'] );
		}

		/**
		 * 今回 Stripe / 見積合計に含める行か。
		 *
		 * @param array<string, mixed> $item 請求行。
		 * @return bool
		 */
		public static function is_billable_now( array $item ) {
			if ( self::is_provisional_item( $item ) ) {
				return false;
			}

			$name = trim( (string) ( $item['product_name'] ?? '' ) );
			if ( $name === '' ) {
				return false;
			}

			return self::resolve_billable_amount( $item ) > 0;
		}

		/**
		 * JS の自動再計算をスキップする行か。
		 *
		 * @param array<string, mixed> $item 請求行。
		 * @return bool
		 */
		public static function should_skip_auto_recalculate( array $item ) {
			return self::is_provisional_item( $item );
		}

		/**
		 * 単価×数量（切り上げ）。
		 *
		 * @param array<string, mixed> $item 請求行。
		 * @return float
		 */
		public static function amount_from_price_quantity( array $item ) {
			$price = isset( $item['price'] ) ? (float) $item['price'] : 0.0;
			$qty   = isset( $item['quantity'] ) ? (float) $item['quantity'] : 0.0;

			if ( $price <= 0 || $qty <= 0 ) {
				return 0.0;
			}

			return (float) ceil( $price * $qty );
		}

		/**
		 * 画面表示・合計用の行金額。
		 *
		 * @param array<string, mixed> $item 請求行。
		 * @return float
		 */
		public static function resolve_line_amount( array $item ) {
			if ( self::is_provisional_item( $item ) ) {
				return 0.0;
			}

			if ( ! isset( $item['amount'] ) || $item['amount'] === '' || ! is_numeric( $item['amount'] ) ) {
				return self::amount_from_price_quantity( $item );
			}

			$from_amount = (float) $item['amount'];
			if ( $from_amount < 0 ) {
				return $from_amount;
			}
			if ( $from_amount > 0 ) {
				return $from_amount;
			}

			return self::amount_from_price_quantity( $item );
		}

		/**
		 * Stripe・今回請求合計用（整数円）。
		 *
		 * @param array<string, mixed> $item 請求行。
		 * @return int
		 */
		public static function resolve_billable_amount( array $item ) {
			if ( self::is_provisional_item( $item ) ) {
				return 0;
			}

			return (int) ceil( self::resolve_line_amount( $item ) );
		}

		/**
		 * 請求行を「今回」と「定期参考」に分割する。
		 *
		 * @param array<int, array<string, mixed>> $items 請求行一覧。
		 * @return array{bill_now: array<int, array<string, mixed>>, reference: array<int, array<string, mixed>>}
		 */
		public static function split_items_for_display( array $items ) {
			$bill_now  = array();
			$reference = array();

			foreach ( $items as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}

				$name = trim( (string) ( $item['product_name'] ?? '' ) );
				if ( $name === '' ) {
					continue;
				}

				if ( self::is_provisional_item( $item ) ) {
					$reference[] = $item;
				} else {
					$bill_now[] = $item;
				}
			}

			return array(
				'bill_now'  => $bill_now,
				'reference' => $reference,
			);
		}

		/**
		 * 非参考行の amount を単価×数量で DB 同期する。
		 *
		 * @param int $order_id 受注 ID.
		 * @return void
		 */
		public static function sync_billable_amounts_for_order( $order_id ) {
			global $wpdb;

			$order_id = absint( $order_id );
			if ( $order_id <= 0 || ! class_exists( 'KTPWP_Order_Items' ) ) {
				return;
			}

			$table = $wpdb->prefix . 'ktp_order_invoice_items';
			$items = KTPWP_Order_Items::get_instance()->get_invoice_items( $order_id );
			if ( empty( $items ) ) {
				return;
			}

			foreach ( $items as $item ) {
				if ( ! is_array( $item ) || empty( $item['id'] ) ) {
					continue;
				}

				if ( self::is_provisional_item( $item ) ) {
					continue;
				}

				$calculated = self::resolve_billable_amount( $item );
				$current    = isset( $item['amount'] ) ? (int) round( (float) $item['amount'] ) : 0;
				if ( $current === $calculated ) {
					continue;
				}

				$wpdb->update(
					$table,
					array( 'amount' => $calculated ),
					array( 'id' => (int) $item['id'] ),
					array( '%d' ),
					array( '%d' )
				);
			}
		}

		/**
		 * 参考行を 0 円・単価保持で DB 同期する。
		 *
		 * @param int $order_id 受注 ID.
		 * @return void
		 */
		public static function normalize_provisional_amounts_for_order( $order_id ) {
			global $wpdb;

			$order_id = absint( $order_id );
			if ( $order_id <= 0 || ! class_exists( 'KTPWP_Order_Items' ) ) {
				return;
			}

			$table = $wpdb->prefix . 'ktp_order_invoice_items';
			$items = KTPWP_Order_Items::get_instance()->get_invoice_items( $order_id );
			if ( empty( $items ) ) {
				return;
			}

			foreach ( $items as $item ) {
				if ( ! is_array( $item ) || empty( $item['id'] ) || ! self::is_provisional_item( $item ) ) {
					continue;
				}

				$current = isset( $item['amount'] ) ? (int) round( (float) $item['amount'] ) : 0;
				if ( $current === 0 ) {
					continue;
				}

				$wpdb->update(
					$table,
					array( 'amount' => 0 ),
					array( 'id' => (int) $item['id'] ),
					array( '%d' ),
					array( '%d' )
				);
			}
		}

		/**
		 * 見積メール用の請求項目ブロックを組み立てる。
		 *
		 * @param array<int, array<string, mixed>> $items        請求行。
		 * @param string                            $tax_category 内税|外税。
		 * @return array{list: string, bill_total: float, has_reference: bool}
		 */
		public static function build_invoice_email_sections( array $items, $tax_category ) {
			$split = self::split_items_for_display( $items );

			if ( empty( $split['bill_now'] ) && empty( $split['reference'] ) ) {
				return array(
					'list'           => __( '（請求項目未入力）', 'kantanpro' ),
					'bill_total'     => 0.0,
					'has_reference'  => false,
				);
			}

			$output = '';

			if ( ! empty( $split['bill_now'] ) ) {
				$bill_block = self::build_item_block( $split['bill_now'], $tax_category, false );
				if ( count( $split['reference'] ) > 0 ) {
					$output .= '＜' . __( '今回のお支払い', 'kantanpro' ) . '＞' . "\n";
				}
				$output .= $bill_block['list'];
			}

			if ( ! empty( $split['reference'] ) ) {
				if ( $output !== '' ) {
					$output .= "\n";
				}
				$output .= '＜' . __( '契約開始後の月額（参考）', 'kantanpro' ) . '＞' . "\n";
				$output .= self::build_reference_block( $split['reference'] );
			}

			$bill_total = 0.0;
			foreach ( $split['bill_now'] as $item ) {
				$bill_total += self::resolve_line_amount( $item );
			}

			return array(
				'list'          => $output,
				'bill_total'    => $bill_total,
				'has_reference' => ! empty( $split['reference'] ),
			);
		}

		/**
		 * @param array<int, array<string, mixed>> $items        請求行。
		 * @param string                            $tax_category 税区分。
		 * @param bool                              $reference_only 参考表示。
		 * @return array{list: string, subtotal: float, tax_amount: float}
		 */
		private static function build_item_block( array $items, $tax_category, $reference_only ) {
			$item_lines      = array();
			$tax_rate_groups = array();
			$subtotal        = 0.0;
			$total_tax       = 0.0;

			foreach ( $items as $item ) {
				$line_data = self::format_item_line( $item, $tax_category, $reference_only );
				if ( $line_data === null ) {
					continue;
				}

				$item_lines[] = $line_data['line'];
				if ( ! $reference_only ) {
					$subtotal += $line_data['amount'];
					$key = $line_data['tax_rate_key'];
					if ( ! isset( $tax_rate_groups[ $key ] ) ) {
						$tax_rate_groups[ $key ] = 0.0;
					}
					$tax_rate_groups[ $key ] += $line_data['amount'];
					$total_tax += $line_data['tax_amount'];
				}
			}

			$list = '';
			if ( ! empty( $item_lines ) ) {
				$list = implode( "\n", $item_lines ) . "\n";
			}

			if ( ! $reference_only && $subtotal > 0 ) {
				$list .= self::build_total_lines( $subtotal, $total_tax, $tax_rate_groups, $tax_category );
			}

			return array(
				'list'       => $list,
				'subtotal'   => $subtotal,
				'tax_amount' => $total_tax,
			);
		}

		/**
		 * @param array<int, array<string, mixed>> $items 参考行。
		 * @return string
		 */
		private static function build_reference_block( array $items ) {
			$lines = array();
			$total = 0.0;

			foreach ( $items as $item ) {
				$name  = trim( (string) ( $item['product_name'] ?? '' ) );
				$price = isset( $item['price'] ) ? (float) $item['price'] : 0.0;
				$unit  = trim( (string) ( $item['unit'] ?? '' ) );
				if ( $name === '' || $price <= 0 ) {
					continue;
				}

				$total += $price;
				$unit_suffix = $unit !== '' ? '/' . $unit : '';
				$lines[]     = sprintf(
					'%1$s：%2$s%3$s',
					$name,
					class_exists( 'KTPWP_Settings' ) ? KTPWP_Settings::format_money( $price ) : number_format( $price ),
					$unit_suffix
				);
			}

			if ( empty( $lines ) ) {
				return '';
			}

			$block  = implode( "\n", $lines ) . "\n";
			$block .= str_repeat( '-', 60 ) . "\n";
			$block .= sprintf(
				/* translators: %s: 月額の合計 */
				__( '月額合計：%s', 'kantanpro' ),
				class_exists( 'KTPWP_Settings' ) ? KTPWP_Settings::format_money( $total ) : number_format( $total )
			);

			return $block;
		}

		/**
		 * @param array<string, mixed> $item         請求行。
		 * @param string               $tax_category 税区分。
		 * @param bool                 $reference_only 参考行。
		 * @return array{line: string, amount: float, tax_rate_key: string, tax_amount: float}|null
		 */
		private static function format_item_line( array $item, $tax_category, $reference_only ) {
			$product_name = trim( (string) ( $item['product_name'] ?? '' ) );
			if ( $product_name === '' ) {
				return null;
			}

			$item_amount  = $reference_only ? 0.0 : self::resolve_line_amount( $item );
			$price        = isset( $item['price'] ) ? (float) $item['price'] : 0.0;
			$quantity     = isset( $item['quantity'] ) ? (float) $item['quantity'] : 0.0;
			$unit         = trim( (string) ( $item['unit'] ?? '' ) );
			$tax_rate_raw = isset( $item['tax_rate'] ) ? $item['tax_rate'] : null;
			$remarks      = trim( (string) ( $item['remarks'] ?? '' ) );

			$tax_rate = null;
			if ( class_exists( 'KTPWP_Tax_Policy' ) ) {
				$tax_rate = KTPWP_Tax_Policy::get_effective_rate( $tax_rate_raw );
			} elseif ( $tax_rate_raw !== null && $tax_rate_raw !== '' && is_numeric( $tax_rate_raw ) ) {
				$tax_rate = (float) $tax_rate_raw;
			}

			$tax_amount = 0.0;
			if ( $tax_category === '外税' && $tax_rate !== null ) {
				$tax_amount = (float) ceil( $item_amount * ( $tax_rate / 100 ) );
			}

			$tax_rate_key = $tax_rate !== null ? number_format( $tax_rate, 1 ) : 'no_tax_rate';

			// 単価・数量が未入力（0）でサービス名だけ入力された行は、計算式を出さずサービス名のみ表示する（KantanBiz準拠）。
			if ( $product_name !== '' && $price == 0.0 && $quantity == 0.0 ) {
				return array(
					'line'         => $product_name,
					'amount'       => $item_amount,
					'tax_rate_key' => $tax_rate_key,
					'tax_amount'   => $tax_amount,
				);
			}

			$price_display = rtrim( rtrim( number_format( $price, 6, '.', '' ), '0' ), '.' );
			$quantity_display = rtrim( rtrim( number_format( $quantity, 6, '.', '' ), '0' ), '.' );
			$suppress_tax_text = ( class_exists( 'KTPWP_Tax_Policy' ) && ( KTPWP_Tax_Policy::is_abolished() || KTPWP_Tax_Policy::hide_tax_columns() ) );
			/* translators: %s: 税率（パーセント値） */
			$tax_rate_text = ( $tax_rate !== null && ! $suppress_tax_text ) ? sprintf( __( '（税率%s%%）', 'kantanpro' ), $tax_rate ) : '';
			/* translators: %s: 補足メッセージ */
			$remarks_text  = ( $remarks !== '' ) ? '　' . sprintf( __( '※ %s', 'kantanpro' ), $remarks ) : '';

			$line = sprintf(
				/* translators: 1: 品目名, 2: 単価, 3: 数量, 4: 単位, 5: 金額, 6: 通貨記号, 7: 税区分の注記 */
				__( '%1$s：%2$s × %3$s%4$s = %5$s%6$s%7$s', 'kantanpro' ),
				$product_name,
				$price_display,
				$quantity_display,
				$unit,
				class_exists( 'KTPWP_Settings' ) ? KTPWP_Settings::format_money( $item_amount ) : number_format( $item_amount ),
				$tax_rate_text,
				$remarks_text
			);

			return array(
				'line'         => $line,
				'amount'       => $item_amount,
				'tax_rate_key' => $tax_rate_key,
				'tax_amount'   => $tax_amount,
			);
		}

		/**
		 * @param float                $subtotal        小計。
		 * @param float                $total_tax       外税合計。
		 * @param array<string, float> $tax_rate_groups 税率別。
		 * @param string               $tax_category    税区分。
		 * @return string
		 */
		private static function build_total_lines( $subtotal, $total_tax, array $tax_rate_groups, $tax_category ) {
			$amount_ceiled = (float) ceil( $subtotal );
			$tax_ceiled    = (float) ceil( $total_tax );

			if ( $tax_category === '外税' ) {
				$total_with_tax = $amount_ceiled + $tax_ceiled;
				$lines          = str_repeat( '-', 60 ) . "\n";
				/* translators: %s: 外税の合計額 */
				$lines         .= sprintf( __( '外税合計：%s', 'kantanpro' ), KTPWP_Settings::format_money( $amount_ceiled ) ) . "\n";
				/* translators: %s: 消費税額 */
				$lines         .= sprintf( __( '消費税：%s', 'kantanpro' ), KTPWP_Settings::format_money( $tax_ceiled ) ) . "\n";
				/* translators: %s: 内税の合計額 */
				$lines         .= sprintf( __( '内税合計：%s', 'kantanpro' ), KTPWP_Settings::format_money( $total_with_tax ) );

				return $lines;
			}

			$suppress_tax_detail = ( class_exists( 'KTPWP_Tax_Policy' ) && ( KTPWP_Tax_Policy::is_abolished() || KTPWP_Tax_Policy::hide_tax_columns() ) );
			$tax_detail_text     = '';
			if ( ! $suppress_tax_detail ) {
				$total_tax_amount = 0.0;
				$tax_rate_details = array();
				foreach ( $tax_rate_groups as $tax_rate => $group_amount ) {
					if ( $tax_rate === 'no_tax_rate' ) {
						continue;
					}
					$tax_rate_value    = (float) $tax_rate;
					$tax_amount        = (float) ceil( $group_amount * ( $tax_rate_value / 100 ) / ( 1 + $tax_rate_value / 100 ) );
					$total_tax_amount += $tax_amount;
					$tax_rate_details[] = $tax_rate . '%: ' . KTPWP_Settings::format_money( $tax_amount );
				}
				if ( count( $tax_rate_groups ) > 1 ) {
					/* translators: %s: 内税額（通貨記号付き） */
					$tax_detail_text = sprintf( __( '（内税：%s）', 'kantanpro' ), implode( ', ', $tax_rate_details ) );
				} elseif ( $total_tax_amount > 0 ) {
					/* translators: %s: 内税額（通貨記号付き） */
					$tax_detail_text = sprintf( __( '（内税：%s）', 'kantanpro' ), KTPWP_Settings::format_money( ceil( $total_tax_amount ) ) );
				}
			}

			return str_repeat( '-', 60 ) . "\n"
				/* translators: %s: 金額の合計 */
				. sprintf( __( '金額合計：%s', 'kantanpro' ), KTPWP_Settings::format_money( $amount_ceiled ) )
				. $tax_detail_text;
		}
	}
}
