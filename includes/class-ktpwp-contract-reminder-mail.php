<?php
/**
 * 定期契約の請求予定メール（請求日の N 日前）
 *
 * @package KTPWP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'KTPWP_Contract_Reminder_Mail' ) ) {

	/**
	 * 請求予定メールの送信・設定。
	 */
	class KTPWP_Contract_Reminder_Mail {

		/** @var self|null */
		private static $instance = null;

		/** @var KTPWP_Contract_DB|null */
		private $contract_db;

		/** @var KTPWP_Contract_Billing|null */
		private $billing;

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
			if ( class_exists( 'KTPWP_Contract_DB' ) ) {
				$this->contract_db = KTPWP_Contract_DB::get_instance();
			}
			if ( class_exists( 'KTPWP_Contract_Billing' ) ) {
				$this->billing = KTPWP_Contract_Billing::get_instance();
			}
		}

		/**
		 * Cron とフックを登録
		 *
		 * @return void
		 */
		public static function boot() {
			$instance = self::get_instance();
			add_action( 'ktpwp_contract_reminder_daily', array( $instance, 'process_due_reminders' ) );

			if ( ! wp_next_scheduled( 'ktpwp_contract_reminder_daily' ) ) {
				wp_schedule_event( time(), 'daily', 'ktpwp_contract_reminder_daily' );
			}
		}

		/**
		 * Cron を解除
		 *
		 * @return void
		 */
		public static function unschedule() {
			wp_clear_scheduled_hook( 'ktpwp_contract_reminder_daily' );
		}

		/**
		 * 自動送信が有効か
		 *
		 * @return bool
		 */
		public static function is_auto_send_enabled() {
			$options = get_option( 'ktp_general_settings', array() );

			return ! isset( $options['contract_reminder_enabled'] ) || ! empty( $options['contract_reminder_enabled'] );
		}

		/**
		 * 何日前に送るか
		 *
		 * @return int
		 */
		public static function get_days_before() {
			$options = get_option( 'ktp_general_settings', array() );
			$days    = isset( $options['contract_reminder_days_before'] ) ? absint( $options['contract_reminder_days_before'] ) : 3;

			return max( 1, min( 30, $days ) );
		}

		/**
		 * 件名テンプレート
		 *
		 * @return string
		 */
		public static function get_default_subject_template() {
			return '【ご請求予定のお知らせ】{client_name} {period_label}（請求予定: {billing_date}）';
		}

		/**
		 * 本文テンプレート
		 *
		 * @return string
		 */
		public static function get_default_body_template() {
			return "{client_name} 様\n\n"
				. "下記のとおり、請求予定のご案内です。\n\n"
				. "契約名: {contract_name}\n"
				. "請求予定日: {billing_date}\n"
				. "請求予定金額: {amount}\n"
				. "お支払い期日: {payment_due}\n\n"
				. "{initial_fee_note}\n"
				. "{bank_info}\n\n"
				. "{company_info}";
		}

		/**
		 * 件名テンプレート（保存値）
		 *
		 * @return string
		 */
		public static function get_subject_template() {
			$options = get_option( 'ktp_general_settings', array() );
			$value   = isset( $options['contract_reminder_subject'] ) ? (string) $options['contract_reminder_subject'] : '';

			return $value !== '' ? $value : self::get_default_subject_template();
		}

		/**
		 * 本文テンプレート（保存値）
		 *
		 * @return string
		 */
		public static function get_body_template() {
			$options = get_option( 'ktp_general_settings', array() );
			$value   = isset( $options['contract_reminder_body'] ) ? (string) $options['contract_reminder_body'] : '';

			return $value !== '' ? $value : self::get_default_body_template();
		}

		/**
		 * 設定画面: 自動送信
		 *
		 * @return void
		 */
		public static function render_enabled_field() {
			$options = get_option( 'ktp_general_settings', array() );
			$checked = ! isset( $options['contract_reminder_enabled'] ) || ! empty( $options['contract_reminder_enabled'] );
			echo '<label><input type="checkbox" name="ktp_general_settings[contract_reminder_enabled]" value="1" ' . checked( $checked, true, false ) . '> ';
			echo esc_html__( '請求予定メールを自動送信する', 'kantanpro' );
			echo '</label>';
		}

		/**
		 * 設定画面: 何日前
		 *
		 * @return void
		 */
		public static function render_days_before_field() {
			$options = get_option( 'ktp_general_settings', array() );
			$value   = isset( $options['contract_reminder_days_before'] ) ? absint( $options['contract_reminder_days_before'] ) : 3;
			echo '<input type="number" min="1" max="30" step="1" name="ktp_general_settings[contract_reminder_days_before]" value="' . esc_attr( (string) max( 1, $value ) ) . '" class="small-text"> ';
			echo esc_html__( '日前', 'kantanpro' );
		}

		/**
		 * 設定画面: 件名
		 *
		 * @return void
		 */
		public static function render_subject_field() {
			$value = self::get_subject_template();
			echo '<input type="text" name="ktp_general_settings[contract_reminder_subject]" value="' . esc_attr( $value ) . '" class="large-text">';
			echo '<p class="description">' . esc_html__( '利用可能: {client_name}, {contract_name}, {period_label}, {billing_date}, {amount}, {payment_due}', 'kantanpro' ) . '</p>';
		}

		/**
		 * 設定画面: 本文
		 *
		 * @return void
		 */
		public static function render_body_field() {
			$value = self::get_body_template();
			echo '<textarea name="ktp_general_settings[contract_reminder_body]" rows="12" class="large-text code">' . esc_textarea( $value ) . '</textarea>';
			echo '<p class="description">' . esc_html__( '利用可能: {client_name}, {contract_name}, {period_label}, {billing_date}, {amount}, {payment_due}, {initial_fee_note}, {bank_info}, {company_info}', 'kantanpro' ) . '</p>';
		}

		/**
		 * 設定セクション説明
		 *
		 * @return void
		 */
		public static function render_settings_section_info() {
			echo '<p>' . esc_html__( '定期契約の請求日の数日前に、顧客へ請求予定の案内メールを送信します。', 'kantanpro' ) . '</p>';
		}

		/**
		 * 日次 Cron: 送信対象を処理
		 *
		 * @return array{sent: int, skipped: int, errors: array<int, string>}
		 */
		public function process_due_reminders() {
			if ( ! self::is_auto_send_enabled() || ! $this->billing || ! $this->contract_db || ! $this->contract_db->tables_exist() ) {
				return array(
					'sent'     => 0,
					'skipped'  => 0,
					'errors'   => array(),
				);
			}

			$targets = $this->get_due_reminder_targets();
			$sent    = 0;
			$skipped = 0;
			$errors  = array();

			foreach ( $targets as $target ) {
				if ( ! empty( $target['reminder_sent'] ) ) {
					++$skipped;
					continue;
				}

				$result = $this->send_reminder_for_contract( (int) $target['contract_id'], (string) $target['period'] );
				if ( is_wp_error( $result ) ) {
					$errors[] = $target['contract_name'] . ': ' . $result->get_error_message();
					continue;
				}
				++$sent;
			}

			return array(
				'sent'     => $sent,
				'skipped'  => $skipped,
				'errors'   => $errors,
			);
		}

		/**
		 * 対象月の予告メール統計
		 *
		 * @param string|null $period YYYY-MM。
		 * @return array{total: int, sent: int, pending: int}
		 */
		public function get_reminder_stats( $period = null ) {
			$period = $period ? sanitize_text_field( $period ) : $this->billing->get_billing_period();
			$rows   = $this->billing->get_monthly_rows( $period );

			$total   = count( $rows );
			$sent    = 0;
			$pending = 0;

			foreach ( $rows as $row ) {
				if ( empty( $row['reminder_eligible'] ) ) {
					continue;
				}
				if ( ! empty( $row['reminder_sent'] ) ) {
					++$sent;
				} else {
					++$pending;
				}
			}

			return array(
				'total'   => $total,
				'sent'    => $sent,
				'pending' => $pending,
			);
		}

		/**
		 * 今日送信すべき契約一覧
		 *
		 * @return array<int, array<string, mixed>>
		 */
		public function get_due_reminder_targets() {
			if ( ! $this->billing || ! $this->contract_db || ! $this->contract_db->tables_exist() ) {
				return array();
			}

			global $wpdb;

			$days_before          = self::get_days_before();
			$target_billing_date  = wp_date( 'Y-m-d', strtotime( '+' . $days_before . ' days', current_time( 'timestamp' ) ) );
			$period               = wp_date( 'Y-m', strtotime( $target_billing_date ) );
			$contract_table       = $this->contract_db->get_contract_table_name();
			$client_table         = $wpdb->prefix . 'ktp_client';

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$contracts = $wpdb->get_results(
				"SELECT c.*, cl.company_name, cl.name AS client_user_name, cl.email, cl.representative_name,
					cl.payment_month, cl.payment_day, cl.closing_day
				FROM {$contract_table} c
				LEFT JOIN {$client_table} cl ON cl.id = c.client_id
				WHERE c.status = 'active' AND c.send_reminder_mail = 1
				ORDER BY c.id ASC"
			);

			if ( ! is_array( $contracts ) ) {
				return array();
			}

			$targets = array();
			foreach ( $contracts as $contract ) {
				if ( ! $this->billing->is_contract_due_in_period( $contract, $period ) ) {
					continue;
				}

				$billing_date = KTPWP_Contract_Billing::get_billing_date_for_period( (int) $contract->billing_day, $period );
				if ( $billing_date !== $target_billing_date ) {
					continue;
				}

				$log = $this->billing->get_billing_log( (int) $contract->id, $period );

				$targets[] = array(
					'contract_id'   => (int) $contract->id,
					'contract_name' => (string) $contract->contract_name,
					'period'        => $period,
					'billing_date'  => $billing_date,
					'reminder_sent' => $log && ! empty( $log->reminder_sent_at ),
				);
			}

			return $targets;
		}

		/**
		 * 1契約分の予告メール送信
		 *
		 * @param int    $contract_id 契約 ID。
		 * @param string $period      YYYY-MM。
		 * @param bool   $force       送信済みでも再送するか。
		 * @return true|\WP_Error
		 */
		public function send_reminder_for_contract( $contract_id, $period, $force = false ) {
			global $wpdb;

			$contract_id = absint( $contract_id );
			$period      = sanitize_text_field( $period );

			if ( $contract_id <= 0 || ! preg_match( '/^\d{4}-\d{2}$/', $period ) || ! $this->billing || ! $this->contract_db ) {
				return new WP_Error( 'invalid_args', __( '送信対象が不正です。', 'kantanpro' ) );
			}

			$contract = $this->contract_db->get_contract_by_id( $contract_id );
			if ( ! $contract || 'active' !== $contract->status ) {
				return new WP_Error( 'inactive', __( '有効な定期契約のみメールを送信できます。', 'kantanpro' ) );
			}

			if ( (int) $contract->send_reminder_mail !== 1 ) {
				return new WP_Error( 'disabled', __( 'この契約は請求予定メールが無効です。', 'kantanpro' ) );
			}

			if ( ! $this->billing->is_contract_due_in_period( $contract, $period ) ) {
				return new WP_Error( 'not_due', __( 'この月は請求対象ではありません。', 'kantanpro' ) );
			}

			$log = $this->billing->get_billing_log( $contract_id, $period );
			if ( ! $force && $log && ! empty( $log->reminder_sent_at ) ) {
				return new WP_Error( 'already_sent', __( '請求予定メールはすでに送信済みです。', 'kantanpro' ) );
			}

			$client = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}ktp_client WHERE id = %d",
					(int) $contract->client_id
				)
			);
			if ( ! $client ) {
				return new WP_Error( 'client_missing', __( '顧客が見つかりません。', 'kantanpro' ) );
			}

			$to = $this->resolve_client_email( $client );
			if ( $to === '' ) {
				return new WP_Error( 'no_email', __( '顧客のメールアドレスが設定されていません。', 'kantanpro' ) );
			}

			$mail = $this->build_mail_content( $contract, $client, $period );
			$headers = $this->build_mail_headers();

			$mail_outcome = KTPWP_Order_Auxiliary::run_wp_mail_with_result(
				static function () use ( $to, $mail, $headers ) {
					return wp_mail( $to, $mail['subject'], $mail['body'], $headers );
				}
			);

			if ( empty( $mail_outcome['success'] ) ) {
				$message = ! empty( $mail_outcome['error_message'] ) ? (string) $mail_outcome['error_message'] : __( 'メール送信に失敗しました。', 'kantanpro' );
				return new WP_Error( 'mail_failed', $message );
			}

			$this->mark_reminder_sent( $contract_id, $period );

			return true;
		}

		/**
		 * 未送信分を一括送信（手動）
		 *
		 * @param string|null $period YYYY-MM。
		 * @return array{sent: int, errors: array<int, string>}
		 */
		public function send_pending_reminders( $period = null ) {
			$period  = $period ? sanitize_text_field( $period ) : $this->billing->get_billing_period();
			$rows    = $this->billing->get_monthly_rows( $period );
			$sent    = 0;
			$errors  = array();

			foreach ( $rows as $row ) {
				if ( empty( $row['reminder_eligible'] ) || ! empty( $row['reminder_sent'] ) ) {
					continue;
				}

				$result = $this->send_reminder_for_contract( (int) $row['contract_id'], $period );
				if ( is_wp_error( $result ) ) {
					$errors[] = $row['contract_name'] . ': ' . $result->get_error_message();
					continue;
				}
				++$sent;
			}

			return array(
				'sent'   => $sent,
				'errors' => $errors,
			);
		}

		/**
		 * 請求ログに送信日時を記録
		 *
		 * @param int    $contract_id 契約 ID。
		 * @param string $period      YYYY-MM。
		 * @return void
		 */
		private function mark_reminder_sent( $contract_id, $period ) {
			global $wpdb;

			$log_table = $this->contract_db->get_billing_log_table_name();
			$log       = $this->billing->get_billing_log( $contract_id, $period );
			$now       = current_time( 'mysql' );

			if ( $log ) {
				$wpdb->update(
					$log_table,
					array( 'reminder_sent_at' => $now ),
					array( 'id' => (int) $log->id ),
					array( '%s' ),
					array( '%d' )
				);
				return;
			}

			$wpdb->insert(
				$log_table,
				array(
					'contract_id'      => $contract_id,
					'order_id'         => 0,
					'billing_period'   => $period,
					'reminder_sent_at' => $now,
				),
				array( '%d', '%d', '%s', '%s' )
			);
		}

		/**
		 * メール本文・件名を組み立て
		 *
		 * @param object $contract 契約行。
		 * @param object $client   顧客行。
		 * @param string $period   YYYY-MM。
		 * @return array{subject: string, body: string}
		 */
		private function build_mail_content( $contract, $client, $period ) {
			$parts        = explode( '-', $period );
			$period_label = (int) $parts[0] . '年' . (int) $parts[1] . '月分';
			$billing_date = KTPWP_Contract_Billing::get_billing_date_for_period( (int) $contract->billing_day, $period );
			$billing_lbl  = $this->format_japanese_date( $billing_date );
			$client_name  = trim( (string) $client->company_name . ' ' . (string) $client->name );
			$amount       = $this->format_contract_amount( $contract );
			$payment_due  = KTPWP_Contract_Billing::format_payment_due_label( $this->merge_client_into_contract( $contract, $client ), $period );
			$initial_note = $this->build_initial_fee_note( $contract );
			$bank_info    = class_exists( 'KTPWP_Settings' ) ? trim( (string) KTPWP_Settings::get_bank_transfer_plain_text() ) : '';
			$company_info = class_exists( 'KTPWP_Settings' ) ? trim( (string) KTPWP_Settings::get_company_info() ) : '';

			$replacements = array(
				'{client_name}'     => $client_name,
				'{contract_name}'   => (string) $contract->contract_name,
				'{period_label}'    => $period_label,
				'{billing_date}'    => $billing_lbl,
				'{amount}'          => $amount,
				'{payment_due}'     => $payment_due,
				'{initial_fee_note}' => $initial_note,
				'{bank_info}'       => $bank_info,
				'{company_info}'    => $company_info,
			);

			$subject = strtr( self::get_subject_template(), $replacements );
			$body    = strtr( self::get_body_template(), $replacements );
			$body    = preg_replace( "/\n{3,}/", "\n\n", $body );
			$body    = trim( (string) $body );

			return array(
				'subject' => sanitize_text_field( $subject ),
				'body'    => $body,
			);
		}

		/**
		 * 契約行に顧客の支払情報をマージ
		 *
		 * @param object $contract 契約行。
		 * @param object $client   顧客行。
		 * @return object
		 */
		private function merge_client_into_contract( $contract, $client ) {
			$merged                     = clone $contract;
			$merged->payment_month      = $client->payment_month ?? '';
			$merged->payment_day        = $client->payment_day ?? '';
			$merged->closing_day        = $client->closing_day ?? '';
			$merged->payment_due_mode   = $contract->payment_due_mode ?? 'contract';

			return $merged;
		}

		/**
		 * 初回請求の注記
		 *
		 * @param object $contract 契約行。
		 * @return string
		 */
		private function build_initial_fee_note( $contract ) {
			if ( (int) $contract->first_billed === 1 ) {
				return '';
			}

			$fees = $this->contract_db->get_initial_fees_by_contract_id( (int) $contract->id );
			if ( empty( $fees ) ) {
				return '';
			}

			return __( '※初回請求のため、保証金等の追加費用が含まれます。', 'kantanpro' );
		}

		/**
		 * 請求予定金額
		 *
		 * @param object $contract 契約行。
		 * @return string
		 */
		private function format_contract_amount( $contract ) {
			$total = (float) $contract->amount;
			if ( (int) $contract->first_billed === 0 ) {
				foreach ( $this->contract_db->get_initial_fees_by_contract_id( (int) $contract->id ) as $fee ) {
					$total += (float) $fee->amount;
				}
			}

			return class_exists( 'KTPWP_Settings' )
				? KTPWP_Settings::format_money( $total )
				: number_format( $total );
		}

		/**
		 * 日本語日付
		 *
		 * @param string $date Y-m-d。
		 * @return string
		 */
		private function format_japanese_date( $date ) {
			$timestamp = strtotime( $date );
			if ( false === $timestamp ) {
				return $date;
			}

			return wp_date( 'Y年n月j日', $timestamp );
		}

		/**
		 * 顧客メールアドレス
		 *
		 * @param object|null $client 顧客行。
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

			if ( (string) $email_raw === '' || trim( (string) $email_raw ) === '' ) {
				if ( $rep_raw !== '' && is_email( $rep_raw ) ) {
					$push( $rep_raw );
				} elseif ( $name_raw !== '' && is_email( $name_raw ) ) {
					$push( $name_raw );
				}
			} else {
				$push( $email_raw );
			}

			if ( ! empty( $client->id ) && class_exists( 'KTPWP_Department_Manager' ) ) {
				if ( function_exists( 'ktpwp_safe_create_department_table' ) ) {
					ktpwp_safe_create_department_table();
				}
				foreach ( KTPWP_Department_Manager::get_departments_by_client( (int) $client->id ) as $dept ) {
					if ( ! empty( $dept->email ) ) {
						$push( $dept->email );
					}
				}
			}

			return $candidates[0] ?? '';
		}

		/**
		 * メールヘッダー
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
