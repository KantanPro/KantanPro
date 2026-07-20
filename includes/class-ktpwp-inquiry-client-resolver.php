<?php
/**
 * WEB問い合わせ系（公開商品・Contact Form 7・WooCommerce）の顧客検索・部署紐付けを統一。
 *
 * @package KTPWP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'KTPWP_Inquiry_Client_Resolver' ) ) {

	/**
	 * 問い合わせフォーム送信時の顧客解決。
	 */
	class KTPWP_Inquiry_Client_Resolver {

		/**
		 * 同一リクエスト内の CF7 二重取り込み防止。
		 *
		 * @var array<string, true>
		 */
		private static $processed_inquiry_keys = array();

		/**
		 * 同一 CF7 送信を複数フックから二重処理しない。
		 *
		 * @param string $email       正規化済みメール。
		 * @param int    $form_id     CF7 フォーム ID。
		 * @return bool スキップする場合 true。
		 */
		public static function should_skip_duplicate_inquiry( $email, $form_id = 0 ) {
			$email = self::normalize_email_for_lookup( $email );
			if ( $email === '' ) {
				return false;
			}

			$key = (int) $form_id . ':' . $email;
			if ( isset( self::$processed_inquiry_keys[ $key ] ) ) {
				return true;
			}

			self::$processed_inquiry_keys[ $key ] = true;

			return false;
		}

		/**
		 * メールアドレスで既存顧客を検索し、なければ新規作成する。
		 *
		 * 既存顧客が見つかった場合はマスタを更新せず、会社名が異なるときのみ部署を検索／作成する。
		 *
		 * @param array         $data {
		 *     @type string $email         メールアドレス。
		 *     @type string $company_name  フォームの会社名。
		 *     @type string $name          担当者名。
		 *     @type string $message       任意。新規顧客メモ用。
		 *     @type string $phone         任意。新規顧客メモ用。
		 *     @type string $service_name  任意。新規顧客メモ用。
		 *     @type string $memo          任意。新規顧客メモ（指定時は message/phone/service_name より優先）。
		 * }
		 * @param callable|null $create_new_client 新規顧客作成。`( array $data ): int|false`。省略時はデフォルト作成。
		 * @return array{client_id: int, department_id: int|null}|false
		 */
		public static function resolve( array $data, $create_new_client = null ) {
			global $wpdb;

			$table_name   = $wpdb->prefix . 'ktp_client';
			$email        = self::normalize_email_for_lookup( $data['email'] ?? '' );
			$company_name = self::normalize_company_name( $data['company_name'] ?? '' );
			$contact_name = isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : '';
			$department_id = null;

			if ( $email !== '' ) {
				$existing = self::find_client_by_email( $email );
				if ( $existing ) {
					$client_id = (int) $existing->id;

					if ( self::is_meaningful_company_name( $company_name ) && self::is_placeholder_company_name( $existing->company_name ?? '' ) ) {
						$wpdb->update(
							$table_name,
							array( 'company_name' => $company_name ),
							array( 'id' => $client_id ),
							array( '%s' ),
							array( '%d' )
						);
					}

					if ( self::should_use_inquiry_department( $existing, $company_name ) ) {
						$department_id = self::find_or_create_department_for_client( $client_id, $company_name, $contact_name, $email );
						$department_id = $department_id ? (int) $department_id : null;
					}

					return array(
						'client_id'     => $client_id,
						'department_id' => $department_id,
					);
				}
			}

			if ( is_callable( $create_new_client ) ) {
				$new_client_id = (int) call_user_func( $create_new_client, $data );
				if ( $new_client_id <= 0 ) {
					return false;
				}

				return array(
					'client_id'     => $new_client_id,
					'department_id' => null,
				);
			}

			return self::insert_default_client( $data );
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
			self::ensure_inquiry_field_class();

			return KTPWP_Inquiry_Field::resolve_order_customer_name( $client_id, $form_company, $form_contact );
		}

		/**
		 * デフォルトの新規顧客作成（公開商品・Contact Form 7 向け）。
		 *
		 * @param array $data resolve() と同じキー。
		 * @return array{client_id: int, department_id: int|null}|false
		 */
		private static function insert_default_client( array $data ) {
			global $wpdb;

			$table_name   = $wpdb->prefix . 'ktp_client';
			$email        = self::normalize_email_for_lookup( $data['email'] ?? '' );
			$company_name = self::normalize_company_name( $data['company_name'] ?? '' );

			$memo = '';
			if ( ! empty( $data['memo'] ) ) {
				$memo = sanitize_textarea_field( (string) $data['memo'] );
			} else {
				$memo_parts = array();
				if ( ! empty( $data['message'] ) ) {
					$memo_parts[] = __( 'ご要望:', 'ktpwp' ) . ' ' . sanitize_textarea_field( $data['message'] );
				}
				if ( ! empty( $data['phone'] ) ) {
					$memo_parts[] = __( '電話:', 'ktpwp' ) . ' ' . sanitize_text_field( $data['phone'] );
				}
				if ( ! empty( $data['service_name'] ) ) {
					$memo_parts[] = __( '初回お申込商品:', 'ktpwp' ) . ' ' . sanitize_text_field( $data['service_name'] );
				}
				$memo = implode( "\n", $memo_parts );
			}

			$client_data = array(
				'company_name'  => self::is_meaningful_company_name( $company_name ) ? $company_name : self::allocate_unset_company_name(),
				'name'          => isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : '',
				'email'         => $email,
				'memo'          => $memo,
				'time'          => current_time( 'mysql' ),
				'client_status' => __( '対象', 'ktpwp' ),
			);

			$result = $wpdb->insert(
				$table_name,
				$client_data,
				array( '%s', '%s', '%s', '%s', '%s', '%s' )
			);

			if ( $result === false ) {
				error_log( 'KTPWP Inquiry Client Resolver: Failed to insert client - ' . $wpdb->last_error );
				return false;
			}

			return array(
				'client_id'     => (int) $wpdb->insert_id,
				'department_id' => null,
			);
		}

		/**
		 * メールアドレスで既存顧客を検索する（KantanBiz 同様 LOWER(TRIM) 比較）。
		 *
		 * @param string $email 正規化済みメール。
		 * @return object|null
		 */
		private static function find_client_by_email( $email ) {
			global $wpdb;

			$email = self::normalize_email_for_lookup( $email );
			if ( $email === '' ) {
				return null;
			}

			$table_name = $wpdb->prefix . 'ktp_client';

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			return $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$table_name} WHERE LOWER(TRIM(email)) = %s ORDER BY id ASC LIMIT 1",
					$email
				)
			);
		}

		/**
		 * @param mixed $email メールアドレス。
		 * @return string
		 */
		private static function normalize_email_for_lookup( $email ) {
			self::ensure_inquiry_field_class();

			return KTPWP_Inquiry_Field::normalize_email_for_lookup( $email );
		}

		/**
		 * 会社名未入力の新規顧客用プレースホルダー（未設定#1, 未設定#2 …）。
		 *
		 * @return string
		 */
		private static function allocate_unset_company_name() {
			global $wpdb;

			$table  = $wpdb->prefix . 'ktp_client';
			$prefix = '未設定#';
			$max    = 0;

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$names = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT company_name FROM {$table} WHERE company_name LIKE %s",
					$wpdb->esc_like( $prefix ) . '%'
				)
			);

			if ( is_array( $names ) ) {
				foreach ( $names as $name ) {
					if ( preg_match( '/^未設定#(\d+)$/', (string) $name, $matches ) === 1 ) {
						$max = max( $max, (int) $matches[1] );
					}
				}
			}

			return $prefix . (string) ( $max + 1 );
		}

		/**
		 * フォーム会社名を部署として使うか（登録会社名と異なる場合のみ）。
		 *
		 * @param object $client       ktp_client 行。
		 * @param string $form_company フォームの会社名。
		 * @return bool
		 */
		private static function should_use_inquiry_department( $client, $form_company ) {
			$form_company = self::normalize_company_name( $form_company );
			if ( $form_company === '' ) {
				return false;
			}

			$registered_company = self::normalize_company_name( $client->company_name ?? '' );
			if ( $registered_company === '' ) {
				return false;
			}

			return ! self::normalized_equal( $form_company, $registered_company );
		}

		/**
		 * @param mixed $value 会社名候補。
		 * @return string
		 */
		private static function normalize_company_name( $value ) {
			self::ensure_inquiry_field_class();

			return KTPWP_Inquiry_Field::normalize_company_name( $value );
		}

		/**
		 * @param mixed $value 会社名候補。
		 * @return bool
		 */
		private static function is_meaningful_company_name( $value ) {
			self::ensure_inquiry_field_class();

			return KTPWP_Inquiry_Field::is_meaningful_company_name( $value );
		}

		/**
		 * @return void
		 */
		private static function ensure_inquiry_field_class() {
			if ( ! class_exists( 'KTPWP_Inquiry_Field' ) ) {
				require_once dirname( __FILE__ ) . '/class-ktpwp-inquiry-field.php';
			}
		}

		/**
		 * @param string $a 比較文字列 A。
		 * @param string $b 比較文字列 B。
		 * @return bool
		 */
		private static function normalized_equal( $a, $b ) {
			return mb_strtolower( trim( (string) $a ) ) === mb_strtolower( trim( (string) $b ) );
		}

		/**
		 * 同一メール・別名義の問い合わせ用に部署を登録し、受注の宛先部署として選択する。
		 *
		 * @param int    $client_id    顧客 ID。
		 * @param string $company_name フォームの会社名。
		 * @param string $contact_name 担当者名。
		 * @param string $email        メールアドレス。
		 * @return int|false 部署 ID。失敗時 false。
		 */
		private static function find_or_create_department_for_client( $client_id, $company_name, $contact_name, $email ) {
			$client_id    = (int) $client_id;
			$company_name = sanitize_text_field( (string) $company_name );
			$contact_name = sanitize_text_field( (string) $contact_name );
			$email        = sanitize_email( (string) $email );

			if ( $client_id <= 0 || $contact_name === '' || $email === '' ) {
				return false;
			}

			if ( trim( $company_name ) === '' ) {
				return false;
			}

			global $wpdb;
			$client_table = $wpdb->prefix . 'ktp_client';
			$registered_company = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT company_name FROM {$client_table} WHERE id = %d",
					$client_id
				)
			);
			if (
				is_string( $registered_company )
				&& trim( $registered_company ) !== ''
				&& self::normalized_equal( $company_name, $registered_company )
			) {
				return false;
			}

			if ( ! class_exists( 'KTPWP_Department_Manager' ) ) {
				require_once dirname( __FILE__ ) . '/class-ktpwp-department-manager.php';
			}

			if ( ! KTPWP_Department_Manager::table_exists() && function_exists( 'ktpwp_create_department_table' ) ) {
				ktpwp_create_department_table();
			}

			$department_name = KTPWP_Department_Manager::build_inquiry_department_name( $company_name, $contact_name );

			$departments = KTPWP_Department_Manager::get_departments_by_client( $client_id );
			foreach ( $departments as $department ) {
				if (
					self::normalized_equal( (string) ( $department->department_name ?? '' ), $department_name )
					&& self::normalized_equal( (string) ( $department->contact_person ?? '' ), $contact_name )
				) {
					return (int) $department->id;
				}
			}

			$department_id = KTPWP_Department_Manager::add_department(
				$client_id,
				$department_name,
				$contact_name,
				$email
			);

			if ( ! $department_id ) {
				error_log( 'KTPWP Inquiry Client Resolver: Failed to create department for client ' . $client_id );
				return false;
			}

			return (int) $department_id;
		}
	}
}
