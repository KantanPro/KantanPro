<?php
/**
 * 受注書・一括請求書の明細表示（品名あり・単価0・数量0の説明行で 0 を出さない）
 *
 * KantanBiz OrderInvoiceDocumentDisplay 相当。
 *
 * @package KTPWP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class KTPWP_Order_Invoice_Document_Display {

	/**
	 * 品名あり・単価0・数量0の説明行か。
	 *
	 * @param string $product_name 品名.
	 * @param float  $price        単価.
	 * @param float  $quantity     数量.
	 */
	public static function hides_unit_price_and_quantity( $product_name, $price, $quantity ) {
		return trim( (string) $product_name ) !== ''
			&& (float) $price == 0.0
			&& (float) $quantity == 0.0;
	}

	/**
	 * 編集フォーム用：説明行なら空文字、それ以外は小数省略した数値文字列。
	 *
	 * @param string $product_name 品名.
	 * @param mixed  $price        単価.
	 * @param mixed  $quantity     数量.
	 * @param string $field        price|quantity|amount.
	 * @param mixed  $amount       金額（field=amount 時）.
	 * @return string
	 */
	public static function input_value( $product_name, $price, $quantity, $field, $amount = 0 ) {
		$price    = (float) $price;
		$quantity = (float) $quantity;
		$amount   = (float) $amount;
		$name     = trim( (string) $product_name );

		if ( 'price' === $field ) {
			if ( $name !== '' && $price == 0.0 ) {
				return '';
			}
			return self::format_decimal_trimmed( $price );
		}

		if ( 'quantity' === $field ) {
			if ( self::hides_unit_price_and_quantity( $name, $price, $quantity )
				|| ( $name !== '' && $quantity == 0.0 ) ) {
				return '';
			}
			return self::format_decimal_trimmed( $quantity );
		}

		if ( 'amount' === $field ) {
			if ( $name !== '' && $amount == 0.0 ) {
				return '';
			}
			if ( $amount == 0.0 ) {
				return '';
			}
			return (string) (int) round( $amount );
		}

		return '';
	}

	/**
	 * @param mixed $value 数値.
	 * @return string
	 */
	public static function format_decimal_trimmed( $value ) {
		if ( class_exists( 'KTPWP_Settings' ) && method_exists( 'KTPWP_Settings', 'format_decimal_trimmed' ) ) {
			return KTPWP_Settings::format_decimal_trimmed( $value );
		}

		return rtrim( rtrim( number_format( (float) $value, 6, '.', '' ), '0' ), '.' );
	}

	/**
	 * 印刷・PDF用単価セル.
	 *
	 * @param string $product_name 品名.
	 * @param mixed  $price        単価.
	 * @return string
	 */
	public static function unit_price_cell( $product_name, $price ) {
		if ( trim( (string) $product_name ) !== '' && (float) $price == 0.0 ) {
			return '';
		}

		return class_exists( 'KTPWP_Settings' )
			? KTPWP_Settings::format_money( $price )
			: number_format( (float) $price, 0 );
	}

	/**
	 * 印刷・PDF用数量セル（単位なし）.
	 *
	 * @param string $product_name 品名.
	 * @param mixed  $quantity     数量.
	 * @param float  $price        単価.
	 * @return string
	 */
	public static function quantity_cell( $product_name, $quantity, $price = 0.0 ) {
		$qty = (float) $quantity;

		if ( self::hides_unit_price_and_quantity( $product_name, (float) $price, $qty )
			|| ( trim( (string) $product_name ) !== '' && $qty == 0.0 ) ) {
			return '';
		}

		return self::format_decimal_trimmed( $quantity );
	}

	/**
	 * 印刷・PDF用単位セル.
	 *
	 * @param string $product_name 品名.
	 * @param mixed  $unit         単位.
	 * @param float  $price        単価.
	 * @param float  $quantity     数量.
	 * @return string
	 */
	public static function unit_cell( $product_name, $unit, $price = 0.0, $quantity = 0.0 ) {
		if ( self::hides_unit_price_and_quantity( $product_name, (float) $price, (float) $quantity ) ) {
			return '';
		}

		return trim( (string) ( $unit ?? '' ) );
	}

	/**
	 * 数量+単位（受注印刷の結合表示）.
	 *
	 * @param string $product_name 品名.
	 * @param mixed  $quantity     数量.
	 * @param mixed  $unit         単位.
	 * @param float  $price        単価.
	 * @return string
	 */
	public static function quantity_with_unit_cell( $product_name, $quantity, $unit, $price = 0.0 ) {
		if ( self::hides_unit_price_and_quantity( $product_name, (float) $price, (float) $quantity ) ) {
			return '';
		}

		$qty_display  = self::quantity_cell( $product_name, $quantity, $price );
		$unit_display = self::unit_cell( $product_name, $unit, $price, (float) $quantity );

		return $qty_display . $unit_display;
	}

	/**
	 * 一括請求の「数量/単位」セル.
	 *
	 * @param string $product_name 品名.
	 * @param mixed  $quantity     数量.
	 * @param mixed  $unit         単位.
	 * @param float  $price        単価.
	 * @param string $default_unit 単位が空のときの既定（通常行のみ）.
	 * @return string
	 */
	public static function quantity_unit_cell( $product_name, $quantity, $unit, $price = 0.0, $default_unit = '' ) {
		$qty = (float) $quantity;

		if ( self::hides_unit_price_and_quantity( $product_name, (float) $price, $qty ) ) {
			return '';
		}

		$qty_display = self::quantity_cell( $product_name, $quantity, $price );
		$unit_trim   = trim( (string) ( $unit ?? '' ) );
		if ( $unit_trim === '' && $default_unit !== '' ) {
			$unit_trim = $default_unit;
		}

		if ( $qty_display === '' && $unit_trim === '' ) {
			return '';
		}

		if ( $qty_display === '' ) {
			return $unit_trim;
		}

		if ( $unit_trim === '' ) {
			return $qty_display;
		}

		return $qty_display . '/' . $unit_trim;
	}

	/**
	 * 金額セル.
	 *
	 * @param string $product_name 品名.
	 * @param mixed  $amount       金額.
	 * @return string
	 */
	public static function amount_cell( $product_name, $amount ) {
		if ( trim( (string) $product_name ) !== '' && (float) $amount == 0.0 ) {
			return '';
		}

		if ( (float) $amount == 0.0 ) {
			return '';
		}

		return class_exists( 'KTPWP_Settings' )
			? KTPWP_Settings::format_money( $amount )
			: number_format( (float) $amount, 0 );
	}

	/**
	 * 税率セル（説明行は空）.
	 *
	 * @param string     $product_name 品名.
	 * @param float|null $tax_rate     税率.
	 * @param float      $price        単価.
	 * @param float      $quantity     数量.
	 * @return string
	 */
	public static function tax_rate_cell( $product_name, $tax_rate, $price = 0.0, $quantity = 0.0 ) {
		if ( self::hides_unit_price_and_quantity( $product_name, (float) $price, (float) $quantity ) ) {
			return '';
		}

		if ( $tax_rate === null || $tax_rate === '' || ! is_numeric( $tax_rate ) ) {
			return '';
		}

		return (string) $tax_rate . '%';
	}

	/**
	 * 備考セル（説明行は空）.
	 *
	 * @param string $product_name 品名.
	 * @param mixed  $remarks      備考.
	 * @param float  $price        単価.
	 * @param float  $quantity     数量.
	 * @param string $empty_display 通常行で備考空のときの表示.
	 * @return string
	 */
	public static function remarks_cell( $product_name, $remarks, $price = 0.0, $quantity = 0.0, $empty_display = '' ) {
		if ( self::hides_unit_price_and_quantity( $product_name, (float) $price, (float) $quantity ) ) {
			return '';
		}

		$value = trim( (string) ( $remarks ?? '' ) );

		return $value !== '' ? $value : $empty_display;
	}
}
