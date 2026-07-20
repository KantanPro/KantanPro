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

			if ( self::looks_like_unexpanded_cf7_mail_tag( $value ) ) {
				return '';
			}

			return $value;
		}

		/**
		 * プレースホルダー・無効な会社名か。
		 *
		 * @param mixed $value 会社名候補。
		 * @return bool
		 */
		public static function is_placeholder_company_name( $value ) {
			$normalized = self::normalize_company_name( $value );
			if ( $normalized === '' ) {
				return true;
			}

			if ( preg_match( '/^未設定#\d+$/u', $normalized ) === 1 ) {
				return true;
			}

			$placeholders = array(
				'（会社名未入力）',
				__( '初めてのお客様', 'ktpwp' ),
			);

			return in_array( $normalized, $placeholders, true );
		}

		/**
		 * @param mixed $value 会社名候補。
		 * @return bool
		 */
		public static function is_meaningful_company_name( $value ) {
			return ! self::is_placeholder_company_name( $value );
		}

		/**
		 * 受注・通知に表示する顧客名（会社名）を決定する。
		 *
		 * 登録済みの有効な会社名を優先し、プレースホルダー（0・未設定# 等）のときはフォーム値へフォールバックする。
		 *
		 * @param int    $client_id    顧客 ID。
		 * @param string $form_company フォームの会社名。
		 * @param string $form_contact フォームの担当者名。
		 * @return string
		 */
		public static function resolve_order_customer_name( $client_id, $form_company, $form_contact ) {
			global $wpdb;

			$form_company = self::normalize_company_name( $form_company );
			$form_contact = sanitize_text_field( trim( (string) $form_contact ) );

			$registered_company = '';
			$client_id            = (int) $client_id;
			if ( $client_id > 0 ) {
				$table_name = $wpdb->prefix . 'ktp_client';
				$registered_company = self::normalize_company_name(
					$wpdb->get_var(
						$wpdb->prepare(
							"SELECT company_name FROM {$table_name} WHERE id = %d",
							$client_id
						)
					)
				);
			}

			if ( $registered_company !== '' && ! self::is_placeholder_company_name( $registered_company ) ) {
				return $registered_company;
			}

			if ( $form_company !== '' ) {
				return $form_company;
			}

			return $form_contact;
		}

		/**
		 * 顧客検索・保存用にメールアドレスを正規化する。
		 *
		 * @param mixed $email メールアドレス。
		 * @return string 空文字または小文字・trim 済みのメール。
		 */
		public static function normalize_email_for_lookup( $email ) {
			$email = sanitize_email( (string) $email );
			if ( $email === '' ) {
				return '';
			}

			return strtolower( trim( $email ) );
		}

		/**
		 * CF7 to Webhook 等でメールタグが展開されず "[your_company_name]" のまま届く値を除外する。
		 *
		 * @param string $value 入力値。
		 * @return bool
		 */
		private static function looks_like_unexpanded_cf7_mail_tag( $value ) {
			$value = self::normalize_for_cf7_mail_tag_probe( $value );
			if ( $value === '' ) {
				return false;
			}

			if ( preg_match( '/^\[\s*([^\[\]\r\n]+)\s*\]$/u', $value, $matches ) !== 1 ) {
				return false;
			}

			$inner = trim( $matches[1] );

			return preg_match( '/^[a-zA-Z][a-zA-Z0-9_*.-]*$/', $inner ) === 1;
		}

		/**
		 * @param string $value 入力値。
		 * @return string
		 */
		private static function normalize_for_cf7_mail_tag_probe( $value ) {
			if ( class_exists( 'Normalizer' ) ) {
				$normalized = Normalizer::normalize( $value, Normalizer::FORM_C );
				if ( is_string( $normalized ) ) {
					$value = $normalized;
				}
			}

			$value = preg_replace( '/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $value );
			$value = str_replace( array( '［', '］' ), array( '[', ']' ), (string) $value );

			return trim( (string) $value );
		}
	}
}
