<?php
/**
 * 外部経路からの受注作成時に管理者へメール通知する。
 *
 * @package KTPWP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'KTPWP_Order_Admin_Notification' ) ) {

	/**
	 * 受注作成の管理者通知。
	 */
	class KTPWP_Order_Admin_Notification {

		const SOURCE_WOOCOMMERCE   = 'woocommerce';
		const SOURCE_CONTACT_FORM7 = 'contact_form_7';
		const SOURCE_PUBLIC_PRODUCT = 'public_product';

		/**
		 * @var self|null
		 */
		private static $instance = null;

		/**
		 * 同一リクエスト内の重複通知防止。
		 *
		 * @var array<int, true>
		 */
		private static $notified_order_ids = array();

		/**
		 * @return self
		 */
		public static function get_instance() {
			if ( self::$instance === null ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * 管理者へ新規受注通知を送信する。
		 *
		 * @param int    $order_id 受注 ID。
		 * @param string $source   受注元（SOURCE_* 定数）。
		 * @param array  $context  追加情報（任意）。
		 * @return bool 1件以上送信できた場合 true。
		 */
		public function notify_new_order( $order_id, $source, array $context = array() ) {
			$order_id = (int) $order_id;
			$source   = sanitize_key( (string) $source );

			if ( $order_id <= 0 || ! $this->is_notifiable_source( $source ) ) {
				return false;
			}

			if ( isset( self::$notified_order_ids[ $order_id ] ) ) {
				return false;
			}
			self::$notified_order_ids[ $order_id ] = true;

			$order = $this->get_order( $order_id );
			if ( ! $order ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'KTPWP Order Admin Notification: Order not found (id=' . $order_id . ')' );
				}
				return false;
			}

			$recipients = $this->get_admin_recipient_emails();
			if ( empty( $recipients ) ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'KTPWP Order Admin Notification: No administrator email recipients found.' );
				}
				return false;
			}

			$mail = $this->build_mail_content( $order, $source, $context );
			$headers = $this->build_mail_headers();

			$sent_any = false;
			foreach ( $recipients as $to ) {
				$outcome = KTPWP_Order_Auxiliary::run_wp_mail_with_result(
					static function () use ( $to, $mail, $headers ) {
						return wp_mail( $to, $mail['subject'], $mail['body'], $headers );
					}
				);

				if ( ! empty( $outcome['success'] ) ) {
					$sent_any = true;
				} elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					$err = isset( $outcome['error_message'] ) ? $outcome['error_message'] : 'unknown';
					error_log( 'KTPWP Order Admin Notification: Failed to send to ' . $to . ' - ' . $err );
				}
			}

			return $sent_any;
		}

		/**
		 * @param string $source 受注元。
		 * @return bool
		 */
		private function is_notifiable_source( $source ) {
			return in_array(
				$source,
				array(
					self::SOURCE_WOOCOMMERCE,
					self::SOURCE_CONTACT_FORM7,
					self::SOURCE_PUBLIC_PRODUCT,
				),
				true
			);
		}

		/**
		 * @param int $order_id 受注 ID。
		 * @return object|null
		 */
		private function get_order( $order_id ) {
			if ( class_exists( 'KTPWP_Order' ) ) {
				return KTPWP_Order::get_instance()->get_order( $order_id );
			}

			global $wpdb;
			$table = $wpdb->prefix . 'ktp_order';

			return $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM `{$table}` WHERE id = %d",
					$order_id
				)
			);
		}

		/**
		 * 管理者ロールのメールアドレス一覧を取得する。
		 *
		 * @return string[]
		 */
		private function get_admin_recipient_emails() {
			$emails = array();

			$users = get_users(
				array(
					'role'   => 'administrator',
					'fields' => array( 'user_email' ),
				)
			);

			foreach ( $users as $user ) {
				$email = isset( $user->user_email ) ? sanitize_email( $user->user_email ) : '';
				if ( $email !== '' && is_email( $email ) ) {
					$emails[ strtolower( $email ) ] = $email;
				}
			}

			if ( empty( $emails ) ) {
				$fallback = sanitize_email( get_option( 'admin_email' ) );
				if ( $fallback !== '' && is_email( $fallback ) ) {
					$emails[ strtolower( $fallback ) ] = $fallback;
				}
			}

			return array_values( $emails );
		}

		/**
		 * @param object $order   受注行。
		 * @param string $source  受注元。
		 * @param array  $context 追加情報。
		 * @return array{subject:string,body:string}
		 */
		private function build_mail_content( $order, $source, array $context ) {
			$source_label = $this->get_source_label( $source );
			$site_name    = get_bloginfo( 'name' );
			$order_number = isset( $order->order_number ) ? (string) $order->order_number : '';
			$project_name = isset( $order->project_name ) ? (string) $order->project_name : '';
			$customer_name = isset( $order->customer_name ) ? $this->normalize_display_company_name( $order->customer_name ) : '';
			$user_name     = isset( $order->user_name ) ? (string) $order->user_name : '';
			$memo          = isset( $order->memo ) ? (string) $order->memo : '';
			$progress      = isset( $order->progress ) ? (int) $order->progress : 0;
			$progress_label = $this->get_progress_label( $progress );

			$client_email = '';
			if ( ! empty( $context['client_email'] ) ) {
				$client_email = sanitize_email( (string) $context['client_email'] );
			} elseif ( ! empty( $order->client_id ) ) {
				$client_email = $this->get_client_email( (int) $order->client_id );
			}

			$subject = sprintf(
				/* translators: 1: site name, 2: order source label */
				__( '[%1$s] 新規受注のお知らせ（%2$s）', 'ktpwp' ),
				$site_name,
				$source_label
			);

			$body  = __( '新しい受注が登録されました。', 'ktpwp' ) . "\n\n";
			$body .= __( '受注元:', 'ktpwp' ) . ' ' . $source_label . "\n";

			if ( $order_number !== '' ) {
				$body .= __( '受注番号:', 'ktpwp' ) . ' ' . $order_number . "\n";
			}

			$body .= __( '案件名:', 'ktpwp' ) . ' ' . $project_name . "\n";
			$body .= __( '進捗:', 'ktpwp' ) . ' ' . $progress_label . "\n";

			if ( $customer_name !== '' ) {
				$body .= __( '顧客名:', 'ktpwp' ) . ' ' . $customer_name . "\n";
			}
			if ( $user_name !== '' ) {
				$body .= __( '担当者名:', 'ktpwp' ) . ' ' . $user_name . "\n";
			}
			if ( $client_email !== '' ) {
				$body .= __( 'メールアドレス:', 'ktpwp' ) . ' ' . $client_email . "\n";
			}

			if ( ! empty( $context['wc_order_number'] ) ) {
				$body .= __( 'WooCommerce 注文番号:', 'ktpwp' ) . ' ' . sanitize_text_field( (string) $context['wc_order_number'] ) . "\n";
			}
			if ( ! empty( $context['service_name'] ) ) {
				$body .= __( '商品名:', 'ktpwp' ) . ' ' . sanitize_text_field( (string) $context['service_name'] ) . "\n";
			}
			if ( $memo !== '' ) {
				$body .= "\n" . __( 'メモ:', 'ktpwp' ) . "\n" . $memo . "\n";
			}

			$order_url = $this->get_order_document_url( (int) $order->id );
			if ( $order_url !== '' ) {
				$body .= "\n" . __( '受注書へのリンク:', 'ktpwp' ) . "\n" . $order_url . "\n";
				$order_ref_parts = array();
				if ( $order_number !== '' ) {
					$order_ref_parts[] = sprintf( __( '受注番号 %s', 'ktpwp' ), $order_number );
				}
				$order_ref_parts[] = sprintf( __( 'ID %d', 'ktpwp' ), (int) $order->id );
				$body .= '（' . implode( ' / ', $order_ref_parts ) . '）' . "\n";
			}

			$body .= "\n" . __( '※ このメールは自動送信されています。', 'ktpwp' ) . "\n";

			return array(
				'subject' => $subject,
				'body'    => $body,
			);
		}

		/**
		 * 通知メール表示用の会社名を正規化する。
		 *
		 * @param mixed $value 受注の顧客名。
		 * @return string
		 */
		private function normalize_display_company_name( $value ) {
			if ( ! class_exists( 'KTPWP_Inquiry_Field' ) ) {
				require_once dirname( __FILE__ ) . '/class-ktpwp-inquiry-field.php';
			}

			return KTPWP_Inquiry_Field::normalize_company_name( $value );
		}

		/**
		 * @return string[]
		 */
		private function build_mail_headers() {
			$smtp_settings = get_option( 'ktp_smtp_settings', array() );
			$from_email    = ! empty( $smtp_settings['email_address'] ) ? sanitize_email( $smtp_settings['email_address'] ) : sanitize_email( get_option( 'admin_email' ) );
			$from_name     = ! empty( $smtp_settings['smtp_from_name'] ) ? sanitize_text_field( $smtp_settings['smtp_from_name'] ) : get_bloginfo( 'name' );

			$headers = array();
			if ( $from_email !== '' && is_email( $from_email ) ) {
				if ( $from_name !== '' ) {
					$headers[] = 'From: ' . $from_name . ' <' . $from_email . '>';
				} else {
					$headers[] = 'From: ' . $from_email;
				}
			}

			return $headers;
		}

		/**
		 * @param string $source 受注元。
		 * @return string
		 */
		private function get_source_label( $source ) {
			switch ( $source ) {
				case self::SOURCE_WOOCOMMERCE:
					return __( 'WooCommerce', 'ktpwp' );
				case self::SOURCE_CONTACT_FORM7:
					return __( 'お問い合わせフォーム（Contact Form 7）', 'ktpwp' );
				case self::SOURCE_PUBLIC_PRODUCT:
					return __( '公開商品のお問い合わせ', 'ktpwp' );
				default:
					return __( '外部連携', 'ktpwp' );
			}
		}

		/**
		 * @param int $progress 進捗番号。
		 * @return string
		 */
		private function get_progress_label( $progress ) {
			if ( class_exists( 'KTPWP_Order' ) ) {
				$labels = KTPWP_Order::get_instance()->get_progress_labels();
				if ( isset( $labels[ $progress ] ) ) {
					return (string) $labels[ $progress ];
				}
			}

			return (string) $progress;
		}

		/**
		 * @param int $client_id 顧客 ID。
		 * @return string
		 */
		private function get_client_email( $client_id ) {
			if ( $client_id <= 0 ) {
				return '';
			}

			global $wpdb;
			$table = $wpdb->prefix . 'ktp_client';
			$email = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT email FROM `{$table}` WHERE id = %d",
					$client_id
				)
			);

			$email = is_string( $email ) ? sanitize_email( $email ) : '';
			return ( $email !== '' && is_email( $email ) ) ? $email : '';
		}

		/**
		 * 該当案件の受注書詳細画面への URL を組み立てる。
		 *
		 * @param int $order_id 受注 ID。
		 * @return string
		 */
		private function get_order_document_url( $order_id ) {
			if ( ! class_exists( 'KTPWP_Settings' ) ) {
				return '';
			}

			$base_url = KTPWP_Settings::get_ktpwp_business_page_url();
			if ( $base_url === '' ) {
				return '';
			}

			$args = array(
				'tab_name' => 'order',
				'order_id' => (int) $order_id,
			);

			$page_id = KTPWP_Settings::get_ktpwp_business_page_id();
			if ( $page_id > 0 ) {
				$args['page_id'] = $page_id;
			}

			return add_query_arg( $args, $base_url );
		}
	}
}
