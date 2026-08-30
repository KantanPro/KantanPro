<?php
/**
 * 定期契約の請求メール自動送信（初回を除く）
 *
 * @package KTPWP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'KTPWP_Contract_Invoice_Mail' ) ) {

	/**
	 * 月次都度 Invoice + KantanPro メール自動送信。
	 */
	class KTPWP_Contract_Invoice_Mail {

		/** @var self|null */
		private static $instance = null;

		/** @var KTPWP_Contract_Billing|null */
		private $billing;

		/** @var KTPWP_Contract_DB|null */
		private $contract_db;

		/**
		 * @return self
		 */
		public static function get_instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		private function __construct() {
			if ( class_exists( 'KTPWP_Contract_Billing' ) ) {
				$this->billing = KTPWP_Contract_Billing::get_instance();
			}
			if ( class_exists( 'KTPWP_Contract_DB' ) ) {
				$this->contract_db = KTPWP_Contract_DB::get_instance();
			}
		}

		/**
		 * Cron 登録。
		 *
		 * @return void
		 */
		public static function boot() {
			$instance = self::get_instance();
			add_action( 'ktpwp_contract_invoice_mail_daily', array( $instance, 'process_due_invoices' ) );

			if ( ! wp_next_scheduled( 'ktpwp_contract_invoice_mail_daily' ) ) {
				wp_schedule_event( time(), 'daily', 'ktpwp_contract_invoice_mail_daily' );
			}
		}

		/**
		 * Cron 解除。
		 *
		 * @return void
		 */
		public static function unschedule() {
			wp_clear_scheduled_hook( 'ktpwp_contract_invoice_mail_daily' );
		}

		/**
		 * 自動送信が有効か。
		 *
		 * @return bool
		 */
		public static function is_auto_send_enabled() {
			$options = get_option( 'ktp_general_settings', array() );

			return ! isset( $options['contract_invoice_auto_enabled'] ) || ! empty( $options['contract_invoice_auto_enabled'] );
		}

		/**
		 * 日次処理。
		 *
		 * @return array{sent: int, skipped: int, errors: array<int, string>}
		 */
		public function process_due_invoices() {
			if ( ! self::is_auto_send_enabled() || ! $this->billing || ! $this->contract_db || ! $this->contract_db->tables_exist() ) {
				return array(
					'sent'    => 0,
					'skipped' => 0,
					'errors'  => array(),
				);
			}

			$period = $this->billing->get_billing_period();

			if ( method_exists( $this->billing, 'generate_all_pending' ) ) {
				$this->billing->generate_all_pending( $period );
			}

			$sent   = 0;
			$skipped = 0;
			$errors = array();

			foreach ( $this->billing->get_monthly_rows( $period ) as $row ) {
				if ( empty( $row['order_id'] ) || (int) $row['order_id'] <= 0 ) {
					++$skipped;
					continue;
				}

				if ( ! $this->should_auto_send_for_contract( (int) $row['contract_id'], (string) $period ) ) {
					++$skipped;
					continue;
				}

				$result = $this->send_invoice_for_order( (int) $row['order_id'], (int) $row['contract_id'], $period );
				if ( is_wp_error( $result ) ) {
					$errors[] = ( $row['contract_name'] ?? '' ) . ': ' . $result->get_error_message();
					continue;
				}

				++$sent;
			}

			return array(
				'sent'    => $sent,
				'skipped' => $skipped,
				'errors'  => $errors,
			);
		}

		/**
		 * 自動送信対象か（初回請求は手動）。
		 *
		 * @param int    $contract_id 契約 ID.
		 * @param string $period        YYYY-MM.
		 * @return bool
		 */
		public function should_auto_send_for_contract( $contract_id, $period ) {
			global $wpdb;

			$contract_id = absint( $contract_id );
			$period      = sanitize_text_field( $period );

			if ( $contract_id <= 0 || ! preg_match( '/^\d{4}-\d{2}$/', $period ) ) {
				return false;
			}

			if ( class_exists( 'KTPWP_Stripe_Subscription' )
				&& KTPWP_Stripe_Subscription::get_instance()->contract_uses_subscription( $contract_id ) ) {
				return false;
			}

			if ( ! $this->contract_has_sent_invoice_before( $contract_id ) ) {
				return false;
			}

			$log = $this->billing->get_billing_log( $contract_id, $period );
			if ( $log && ! empty( $log->invoice_sent_at ) ) {
				return false;
			}

			return true;
		}

		/**
		 * 契約で過去に請求メールを送ったことがあるか。
		 *
		 * @param int $contract_id 契約 ID.
		 * @return bool
		 */
		public function contract_has_sent_invoice_before( $contract_id ) {
			global $wpdb;

			$contract_id = absint( $contract_id );
			if ( $contract_id <= 0 || ! $this->contract_db ) {
				return false;
			}

			$log_table = $this->contract_db->get_billing_log_table_name();
			$count     = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$log_table} WHERE contract_id = %d AND invoice_sent_at IS NOT NULL",
					$contract_id
				)
			);

			return $count > 0;
		}

		/**
		 * 請求メール送信。
		 *
		 * @param int    $order_id    受注 ID.
		 * @param int    $contract_id 契約 ID.
		 * @param string $period      YYYY-MM.
		 * @return true|WP_Error
		 */
		public function send_invoice_for_order( $order_id, $contract_id, $period ) {
			global $wpdb;

			$order_id    = absint( $order_id );
			$contract_id = absint( $contract_id );
			$period      = sanitize_text_field( $period );

			if ( $order_id <= 0 ) {
				return new WP_Error( 'invalid_order', __( '案件が見つかりません。', 'kantanpro' ) );
			}

			$order_table = $wpdb->prefix . 'ktp_order';
			$order       = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$order_table} WHERE id = %d",
					$order_id
				)
			);

			if ( ! $order || (int) $order->progress !== 4 ) {
				return new WP_Error( 'invalid_progress', __( '請求書ステータスの案件のみ送信できます。', 'kantanpro' ) );
			}

			$client = null;
			if ( ! empty( $order->client_id ) ) {
				$client = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT * FROM {$wpdb->prefix}ktp_client WHERE id = %d",
						(int) $order->client_id
					)
				);
			}

			$to = $this->resolve_client_email( $client );
			if ( $to === '' ) {
				return new WP_Error( 'no_email', __( '顧客のメールアドレスが見つかりません。', 'kantanpro' ) );
			}

			$my_company = class_exists( 'KTPWP_Settings' ) ? KTPWP_Settings::get_company_info() : get_bloginfo( 'name' );
			$content    = array(
				'subject' => '',
				'body'    => '',
			);

			if ( class_exists( 'KTPWP_Order_UI' ) ) {
				$ui      = new KTPWP_Order_UI();
				$content = $ui->generate_email_content( $order, $my_company );
			}

			if ( empty( $content['subject'] ) || empty( $content['body'] ) ) {
				return new WP_Error( 'empty_content', __( 'メール本文の生成に失敗しました。', 'kantanpro' ) );
			}

			$body = (string) $content['body'];

			if ( class_exists( 'KTPWP_Stripe_Billing' ) ) {
				$stripe = KTPWP_Stripe_Billing::get_instance();
				$body   = $stripe->finalize_and_append_to_body( $order, $body );
				if ( is_wp_error( $body ) ) {
					return $body;
				}
			}

			$headers = $this->build_mail_headers();
			$sent    = wp_mail( $to, (string) $content['subject'], $body, $headers );

			if ( ! $sent ) {
				return new WP_Error( 'mail_failed', __( 'メール送信に失敗しました。', 'kantanpro' ) );
			}

			$this->mark_invoice_sent( $contract_id, $period, $order_id );
			$this->advance_progress_after_send( $order_id );

			if ( class_exists( 'KTPWP_Order_Auxiliary' ) ) {
				KTPWP_Order_Auxiliary::record_customer_mail(
					$order_id,
					$to,
					(string) $content['subject'],
					$body,
					0
				);
			}

			return true;
		}

		/**
		 * 手動送信後にも請求ログを更新（ajax から呼ぶ）。
		 *
		 * @param int         $order_id    受注 ID.
		 * @param string|null $period      YYYY-MM.
		 * @return void
		 */
		public function mark_sent_for_order( $order_id, $period = null ) {
			global $wpdb;

			$order_id = absint( $order_id );
			if ( $order_id <= 0 || ! $this->contract_db ) {
				return;
			}

			$order_table = $wpdb->prefix . 'ktp_order';
			$order       = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT contract_id, billing_period FROM {$order_table} WHERE id = %d",
					$order_id
				)
			);

			if ( ! $order || empty( $order->contract_id ) ) {
				return;
			}

			$contract_id = (int) $order->contract_id;
			$period      = $period ? sanitize_text_field( $period ) : (string) ( $order->billing_period ?? '' );
			if ( ! preg_match( '/^\d{4}-\d{2}$/', $period ) && $this->billing ) {
				$period = $this->billing->get_billing_period();
			}

			$this->mark_invoice_sent( $contract_id, $period, $order_id );
		}

		/**
		 * invoice_sent_at を記録。
		 *
		 * @param int    $contract_id 契約 ID.
		 * @param string $period        YYYY-MM.
		 * @param int    $order_id      受注 ID.
		 * @return void
		 */
		private function mark_invoice_sent( $contract_id, $period, $order_id ) {
			global $wpdb;

			if ( ! $this->contract_db ) {
				return;
			}

			$log_table = $this->contract_db->get_billing_log_table_name();
			$now       = current_time( 'mysql' );
			$log       = $this->billing ? $this->billing->get_billing_log( $contract_id, $period ) : null;

			if ( $log ) {
				$wpdb->update(
					$log_table,
					array(
						'invoice_sent_at' => $now,
						'order_id'        => absint( $order_id ),
					),
					array( 'id' => (int) $log->id ),
					array( '%s', '%d' ),
					array( '%d' )
				);
				return;
			}

			$wpdb->insert(
				$log_table,
				array(
					'contract_id'     => absint( $contract_id ),
					'order_id'        => absint( $order_id ),
					'billing_period'  => sanitize_text_field( $period ),
					'invoice_sent_at' => $now,
				),
				array( '%d', '%d', '%s', '%s' )
			);
		}

		/**
		 * メール送信後の進捗更新（既存 ajax と同様）。
		 *
		 * @param int $order_id 受注 ID.
		 * @return void
		 */
		private function advance_progress_after_send( $order_id ) {
			global $wpdb;

			$order_table = $wpdb->prefix . 'ktp_order';
			$order       = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT progress FROM {$order_table} WHERE id = %d",
					$order_id
				)
			);

			if ( ! $order ) {
				return;
			}

			$current = (int) $order->progress;
			if ( $current >= 1 && $current < 6 ) {
				$wpdb->update(
					$order_table,
					array( 'progress' => $current + 1 ),
					array( 'id' => $order_id ),
					array( '%d' ),
					array( '%d' )
				);
			}
		}

		/**
		 * 顧客メールアドレス。
		 *
		 * @param object|null $client 顧客行.
		 * @return string
		 */
		private function resolve_client_email( $client ) {
			if ( ! $client ) {
				return '';
			}

			$candidates = array();
			$push       = static function ( $raw ) use ( &$candidates ) {
				$email = sanitize_email( trim( (string) $raw ) );
				if ( $email !== '' && is_email( $email ) ) {
					$candidates[] = $email;
				}
			};

			$email_raw = $client->email ?? '';
			$rep_raw   = trim( (string) ( $client->representative_name ?? '' ) );
			$name_raw  = trim( (string) ( $client->name ?? '' ) );

			if ( trim( (string) $email_raw ) === '' ) {
				if ( $rep_raw !== '' && is_email( $rep_raw ) ) {
					$push( $rep_raw );
				} elseif ( $name_raw !== '' && is_email( $name_raw ) ) {
					$push( $name_raw );
				}
			} else {
				$push( $email_raw );
			}

			return $candidates[0] ?? '';
		}

		/**
		 * メールヘッダー。
		 *
		 * @return string[]
		 */
		private function build_mail_headers() {
			$smtp_settings = get_option( 'ktp_smtp_settings', array() );
			$from_email    = ! empty( $smtp_settings['email_address'] )
				? sanitize_email( $smtp_settings['email_address'] )
				: get_option( 'admin_email' );
			$from_name     = ! empty( $smtp_settings['smtp_from_name'] )
				? sanitize_text_field( $smtp_settings['smtp_from_name'] )
				: get_bloginfo( 'name' );

			return array(
				'Content-Type: text/plain; charset=UTF-8',
				'From: ' . $from_name . ' <' . $from_email . '>',
			);
		}
	}
}
