<?php
/**
 * WEB問い合わせフォームのフィールド正規化。
 *
 * @package KTPWP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'KTPWP_Inquiry_Field' ) ) {

	/**
	 * 問い合わせフォームの会社名などを正規化する。
	 */
	class KTPWP_Inquiry_Field {

		/**
		 * 会社名として扱わない値（空・"0" 等）を空文字にそろえる。
		 *
		 * @param mixed $value フォームまたは DB の値。
		 * @return string
		 */
		public static function normalize_company_name( $value ) {
			if ( is_array( $value ) ) {
				$filtered = array_filter(
					$value,
					static function ( $item ) {
						return $item !== '' && $item !== null;
					}
				);
				$value = $filtered ? (string) reset( $filtered ) : '';
			}

			$value = trim( sanitize_text_field( (string) $value ) );
			if ( $value === '' || $value === '0' ) {
				return '';
			}

			return $value;
		}

		/**
		 * @param mixed $value 会社名候補。
		 * @return bool
		 */
		public static function is_meaningful_company_name( $value ) {
			return self::normalize_company_name( $value ) !== '';
		}

		/**
		 * 受注に保存する会社名（顧客マスタの登録会社名を優先）。
		 *
		 * @param int    $client_id    顧客 ID。
		 * @param string $form_company フォームの会社名。
		 * @param string $form_contact フォームの担当者名。
		 * @return string
		 */
		public static function resolve_order_customer_name( $client_id, $form_company, $form_contact ) {
			global $wpdb;

			$client_id = (int) $client_id;
			if ( $client_id > 0 ) {
				$table_name = $wpdb->prefix . 'ktp_client';
				$registered_company = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT company_name FROM {$table_name} WHERE id = %d",
						$client_id
					)
				);
				$normalized_registered = self::normalize_company_name( $registered_company );
				if ( $normalized_registered !== '' ) {
					return $normalized_registered;
				}
			}

			$form_company = self::normalize_company_name( $form_company );
			if ( $form_company !== '' ) {
				return $form_company;
			}

			return sanitize_text_field( trim( (string) $form_contact ) );
		}
	}
}
