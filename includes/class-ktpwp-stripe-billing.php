<?php
/**
 * Stripe 請求（初回 Invoice / 定期 Subscription）連携
 *
 * @package KTPWP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'KTPWP_Stripe_Billing' ) ) {

	/**
	 * Stripe Invoice 作成・Webhook・メール本文挿入。
	 */
	class KTPWP_Stripe_Billing {

		/** @var self|null */
		private static $instance = null;

		/**
		 * @return self
		 */
		public static function get_instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * フック登録。
		 *
		 * @return void
		 */
		public static function boot() {
			$instance = self::get_instance();
			add_action( 'rest_api_init', array( $instance, 'register_rest_routes' ) );
			add_action( 'ktpwp_stripe_void_stale_drafts', array( $instance, 'void_stale_draft_invoices' ) );
			add_action( 'update_option_ktp_general_settings', array( $instance, 'maybe_sync_business_profile_on_settings_save' ), 10, 2 );

			if ( ! wp_next_scheduled( 'ktpwp_stripe_void_stale_drafts' ) ) {
				wp_schedule_event( time(), 'daily', 'ktpwp_stripe_void_stale_drafts' );
			}
		}

		/**
		 * Cron 解除。
		 *
		 * @return void
		 */
		public static function unschedule() {
			wp_clear_scheduled_hook( 'ktpwp_stripe_void_stale_drafts' );
		}

		/**
		 * Stripe 連携が有効か。
		 *
		 * @return bool
		 */
		public static function is_enabled() {
			if ( self::get_secret_key() === '' ) {
				return false;
			}

			$options = get_option( 'ktp_general_settings', array() );
			if ( ! empty( $options['stripe_enabled'] ) ) {
				return true;
			}

			// 有効化チェック漏れでも Secret Key が設定されていれば動作させる。
			return ! empty( $options['stripe_secret_key_test'] ) || ! empty( $options['stripe_secret_key_live'] );
		}

		/**
		 * テストモードか。
		 *
		 * @return bool
		 */
		public static function is_test_mode() {
			$options = get_option( 'ktp_general_settings', array() );

			return ! empty( $options['stripe_test_mode'] );
		}

		/**
		 * Secret Key。
		 *
		 * @return string
		 */
		public static function get_secret_key() {
			$options = get_option( 'ktp_general_settings', array() );
			$key     = self::is_test_mode()
				? ( $options['stripe_secret_key_test'] ?? '' )
				: ( $options['stripe_secret_key_live'] ?? '' );

			return trim( (string) $key );
		}

		/**
		 * Webhook Secret。
		 *
		 * @return string
		 */
		public static function get_webhook_secret() {
			$options = get_option( 'ktp_general_settings', array() );
			$key     = self::is_test_mode()
				? ( $options['stripe_webhook_secret_test'] ?? '' )
				: ( $options['stripe_webhook_secret_live'] ?? '' );

			return trim( (string) $key );
		}

		/**
		 * 支払期日（日数）。
		 *
		 * @return int
		 */
		public static function get_days_until_due() {
			$options = get_option( 'ktp_general_settings', array() );
			$days    = isset( $options['stripe_days_until_due'] ) ? absint( $options['stripe_days_until_due'] ) : 30;

			return max( 1, min( 90, $days ) );
		}

		/**
		 * Webhook URL。
		 *
		 * @return string
		 */
		public static function get_webhook_url() {
			return rest_url( 'ktpwp/v1/stripe-webhook' );
		}

		/**
		 * Hosted Invoice の「請求元」表示名。
		 *
		 * @return string
		 */
		public static function get_invoice_issuer_name() {
			$options = get_option( 'ktp_general_settings', array() );
			$custom  = trim( (string) ( $options['stripe_invoice_issuer_name'] ?? '' ) );
			if ( $custom !== '' ) {
				return $custom;
			}

			return defined( 'KANTANPRO_PLUGIN_NAME' ) ? KANTANPRO_PLUGIN_NAME : 'KantanProEX';
		}

		/**
		 * Stripe アカウントの business_profile.name を同期（請求書の請求元表示）。
		 *
		 * @param \Stripe\StripeClient|null $stripe Stripe client.
		 * @return void
		 */
		public function sync_account_business_profile( $stripe = null ) {
			if ( ! self::is_enabled() || ! class_exists( '\Stripe\StripeClient' ) ) {
				return;
			}

			$name = self::get_invoice_issuer_name();
			if ( $name === '' ) {
				return;
			}

			try {
				if ( ! $stripe ) {
					$stripe = new \Stripe\StripeClient( self::get_secret_key() );
				}

				$account = $stripe->accounts->retrieve();
				$current = trim( (string) ( $account->business_profile->name ?? '' ) );
				if ( $current === $name ) {
					return;
				}

				$stripe->accounts->update(
					$account->id,
					array(
						'business_profile' => array(
							'name' => $name,
						),
					)
				);
			} catch ( Exception $e ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'KTPWP Stripe sync business_profile: ' . $e->getMessage() );
				}
			}
		}

		/**
		 * 一般設定保存後に請求元名を Stripe へ反映。
		 *
		 * @param mixed $old_value Old option value.
		 * @param mixed $new_value New option value.
		 * @return void
		 */
		public function maybe_sync_business_profile_on_settings_save( $old_value, $new_value ) {
			$this->sync_account_business_profile();
		}

		/**
		 * REST ルート登録。
		 *
		 * @return void
		 */
		public function register_rest_routes() {
			register_rest_route(
				'ktpwp/v1',
				'/stripe-webhook',
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'rest_webhook_handler' ),
					'permission_callback' => '__return_true',
				)
			);
		}

		/**
		 * Webhook 受信。
		 *
		 * @param WP_REST_Request $request Request.
		 * @return WP_REST_Response|WP_Error
		 */
		public function rest_webhook_handler( $request ) {
			if ( ! self::is_enabled() ) {
				return new WP_Error( 'stripe_disabled', 'Stripe disabled', array( 'status' => 400 ) );
			}

			$payload   = $request->get_body();
			$sig       = isset( $_SERVER['HTTP_STRIPE_SIGNATURE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_STRIPE_SIGNATURE'] ) ) : '';
			$secret    = self::get_webhook_secret();

			if ( $secret === '' ) {
				return new WP_Error( 'no_webhook_secret', 'Webhook secret not configured', array( 'status' => 400 ) );
			}

			if ( ! class_exists( '\Stripe\Webhook' ) ) {
				return new WP_Error( 'stripe_sdk_missing', 'Stripe SDK missing', array( 'status' => 500 ) );
			}

			try {
				$event = \Stripe\Webhook::constructEvent( $payload, $sig, $secret );
			} catch ( Exception $e ) {
				return new WP_Error( 'invalid_payload', $e->getMessage(), array( 'status' => 400 ) );
			}

			$this->handle_webhook_event( $event );

			return new WP_REST_Response( array( 'received' => true ), 200 );
		}

		/**
		 * Webhook イベント処理。
		 *
		 * @param object $event Stripe Event.
		 * @return void
		 */
		public function handle_webhook_event( $event ) {
			$type = isset( $event->type ) ? (string) $event->type : '';

			if ( $type === 'invoice.paid' ) {
				$invoice = $event->data->object ?? null;
				if ( $invoice && ! empty( $invoice->id ) ) {
					$paid_at = isset( $invoice->status_transitions->paid_at )
						? (int) $invoice->status_transitions->paid_at
						: time();

					if ( ! empty( $invoice->subscription ) && class_exists( 'KTPWP_Stripe_Subscription' ) ) {
						// 初回 Subscription 入金は mark_order_paid 側で契約自動作成する。
					}

					$this->mark_order_paid_by_invoice_id( (string) $invoice->id, $paid_at );

					if ( ! empty( $invoice->subscription ) && class_exists( 'KTPWP_Stripe_Subscription' ) ) {
						KTPWP_Stripe_Subscription::get_instance()->handle_subscription_invoice_paid( $invoice );
					}
				}
				return;
			}

			if ( $type === 'invoice.payment_failed' ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'KTPWP Stripe: invoice.payment_failed received' );
				}
				return;
			}

			if ( $type === 'customer.subscription.deleted' && class_exists( 'KTPWP_Stripe_Subscription' ) ) {
				$subscription = $event->data->object ?? null;
				if ( $subscription && ! empty( $subscription->metadata->ktp_contract_id ) ) {
					global $wpdb;
					$contract_id = absint( $subscription->metadata->ktp_contract_id );
					if ( $contract_id > 0 ) {
						$table = $wpdb->prefix . 'ktp_contract';
						$cols  = $wpdb->get_col( "SHOW COLUMNS FROM `{$table}`" );
						if ( is_array( $cols ) && in_array( 'stripe_subscription_id', $cols, true ) ) {
							$wpdb->update(
								$table,
								array( 'stripe_subscription_id' => null ),
								array( 'id' => $contract_id ),
								array( '%s' ),
								array( '%d' )
							);
						}
					}
				}
				return;
			}

			if ( $type === 'checkout.session.completed' && class_exists( 'KTPWP_Stripe_Subscription' ) ) {
				$session = $event->data->object ?? null;
				if ( $session && isset( $session->mode ) && $session->mode === 'setup' ) {
					KTPWP_Stripe_Subscription::get_instance()->handle_setup_checkout_completed( $session );
				}
			}
		}

		/**
		 * 受注書に Stripe 請求を適用するか。
		 *
		 * @param object|null $order 受注行。
		 * @return bool
		 */
		public function should_apply_to_order( $order ) {
			if ( ! self::is_enabled() || ! $order ) {
				return false;
			}

			if ( class_exists( 'KTPWP_Payment_Timing' ) && KTPWP_Payment_Timing::is_prepay( $order, null ) ) {
				return false;
			}

			$progress = isset( $order->progress ) ? (int) $order->progress : 0;

			if ( $this->is_public_web_order( $order ) && 1 === $progress ) {
				return true;
			}

			if ( 4 === $progress && $this->is_contract_order( $order ) && class_exists( 'KTPWP_Stripe_Subscription' ) ) {
				$contract_id = (int) ( $order->contract_id ?? 0 );
				if ( $contract_id > 0 && KTPWP_Stripe_Subscription::get_instance()->contract_uses_subscription( $contract_id ) ) {
					return false;
				}
			}

			return 4 === $progress;
		}

		/**
		 * 公開商品フォーム（Webお申込み）由来か。
		 *
		 * @param object|null $order 受注行。
		 * @return bool
		 */
		public function is_public_web_order( $order ) {
			return class_exists( 'KTPWP_Payment_Timing' ) && KTPWP_Payment_Timing::is_public_web_order( $order );
		}

		/**
		 * 定期契約由来の受注か。
		 *
		 * @param object|null $order 受注行。
		 * @return bool
		 */
		public function is_contract_order( $order ) {
			return $order && isset( $order->contract_id ) && (int) $order->contract_id > 0;
		}

		/**
		 * メール本文に振込口座を載せないか（Stripe のみ）。
		 *
		 * @param object|null $order 受注行。
		 * @return bool
		 */
		public function should_hide_bank_transfer( $order ) {
			return $this->is_contract_order( $order ) || $this->is_public_web_order( $order );
		}

		/**
		 * プレビュー用: draft Invoice を用意し本文を拡張。
		 *
		 * @param object $order 受注行。
		 * @param string $body  本文。
		 * @return string
		 */
		public function append_preview_to_body( $order, $body ) {
			if ( ! $this->should_apply_to_order( $order ) ) {
				return $body;
			}

			$progress         = isset( $order->progress ) ? (int) $order->progress : 0;
			$finalize_preview = $this->is_public_web_order( $order ) && 1 === $progress;

			$result = $this->prepare_invoice_for_order( (int) $order->id, $finalize_preview );
			if ( is_wp_error( $result ) ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'KTPWP Stripe preview: ' . $result->get_error_message() );
				}
				$body .= "\n\n" . sprintf(
					/* translators: %s: error message */
					__( '【オンライン決済】決済リンクの準備に失敗しました: %s', 'ktpwp' ),
					$result->get_error_message()
				);
				return $body;
			}

			$url = isset( $result['url'] ) ? (string) $result['url'] : '';
			if ( $url !== '' ) {
				return $this->inject_payment_block( $body, $url, $order );
			}

			$body .= "\n\n" . __( '【オンライン決済】送信確定時に決済リンクが本文へ挿入されます。', 'ktpwp' );

			if ( ! $this->should_hide_bank_transfer( $order ) && class_exists( 'KTPWP_Settings' ) ) {
				$bank_plain = KTPWP_Settings::get_bank_transfer_plain_text();
				if ( $bank_plain !== '' ) {
					$body .= "\n\n" . $bank_plain;
				}
			}

			return $body;
		}

		/**
		 * 送信時: finalize して URL を本文へ挿入。
		 *
		 * @param object $order 受注行。
		 * @param string $body  本文。
		 * @return string|WP_Error
		 */
		public function finalize_and_append_to_body( $order, $body ) {
			if ( ! $this->should_apply_to_order( $order ) ) {
				return $body;
			}

			$body = $this->strip_existing_payment_block( $body );

			$result = $this->prepare_invoice_for_order( (int) $order->id, true );
			if ( is_wp_error( $result ) ) {
				return $result;
			}

			$url = isset( $result['url'] ) ? (string) $result['url'] : '';
			if ( $url === '' ) {
				return new WP_Error( 'stripe_no_url', __( 'Stripe 決済リンクの取得に失敗しました。', 'ktpwp' ) );
			}

			return $this->inject_payment_block( $body, $url, $order );
		}

		private function normalize_email_body( $body ) {
			return str_replace( array( "\r\n", "\r" ), "\n", (string) $body );
		}

		/**
		 * 既存の決済ブロックを除去。
		 *
		 * @param string $body 本文。
		 * @return string
		 */
		private function strip_existing_payment_block( $body ) {
			$body = $this->normalize_email_body( $body );

			$legacy_block_pattern = '━━━━━━━━━━━━━━━━\n【オンライン決済】[\s\S]*?━━━━━━━━━━━━━━━━';

			$body = preg_replace(
				'/\n\n' . $legacy_block_pattern . '(?=\n\n--\n|\n--\n)/u',
				'',
				$body
			);
			$body = preg_replace(
				'/\n+' . $legacy_block_pattern . '\n*/u',
				'',
				$body
			);
			$body = preg_replace(
				'/\n\n【オンライン決済】以下のリンクよりお支払いください。\nhttps?:\/\/\S+/u',
				'',
				$body
			);
			$body = preg_replace(
				'/\n\n【オンライン決済】(?:送信確定時に決済リンクが本文へ挿入されます。|決済リンクの準備に失敗しました:[^\n]*)/u',
				'',
				$body
			);
			$body = preg_replace(
				'/\n\n【オンライン決済】\n(?:送信確定時に決済リンクが本文へ挿入されます。|決済リンクの準備に失敗しました:[^\n]*)/u',
				'',
				$body
			);

			return rtrim( $body );
		}

		/**
		 * メール送信履歴用: Stripe 決済リンクを除去（入金後の領収書 URL 等を履歴に残さない）。
		 *
		 * @param string $body メール本文。
		 * @return string
		 */
		public static function sanitize_body_for_mail_log( $body ) {
			return self::get_instance()->strip_body_for_mail_log( $body );
		}

		/**
		 * @param string $body メール本文。
		 * @return string
		 */
		private function strip_body_for_mail_log( $body ) {
			$body         = $this->normalize_email_body( $body );
			$history_note = __( '【オンライン決済】決済リンクは送信履歴には含めません。', 'ktpwp' );
			$note         = "\n\n" . $history_note;

			// 保存時・表示時の二重呼び出しでも注記が重複しないよう、判定前に履歴用注記を除外する。
			$body_for_detection = str_replace( $note, '', $body );
			$had_pay            = (bool) preg_match( '/【オンライン決済】|invoice\.stripe\.com|pay\.stripe\.com/i', $body_for_detection );
			$had_history_note   = str_contains( $body, $history_note );

			$body = $this->strip_existing_payment_block( $body );
			$body = str_replace( $note, '', $body );
			$body = preg_replace( '/\n?https?:\/\/(?:invoice|pay)\.stripe\.com\/[^\s\n]*/iu', '', $body );
			$body = preg_replace( '/\n{3,}/', "\n\n", $body );
			$body = rtrim( $body );

			if ( ! $had_pay && ! $had_history_note ) {
				return $body;
			}
			$sig   = "\n\n--\n";
			$pos   = strrpos( $body, $sig );
			if ( false === $pos ) {
				$sig = "\n--\n";
				$pos = strrpos( $body, $sig );
			}
			if ( false !== $pos ) {
				return substr( $body, 0, $pos ) . $note . substr( $body, $pos );
			}

			return $body . $note;
		}

		/**
		 * 決済ブロック本文。
		 *
		 * @param string $url Stripe URL.
		 * @return string
		 */
		private function build_payment_block( $url ) {
			$url = trim( (string) $url );

			return "\n\n" . __( '【オンライン決済】以下のリンクよりお支払いください。', 'ktpwp' ) . "\n" . $url;
		}

		/**
		 * 決済ブロックを本文へ挿入（署名の直前）。
		 *
		 * @param string $body  本文。
		 * @param string $url   Stripe URL.
		 * @param object $order 受注行。
		 * @return string
		 */
		private function inject_payment_block( $body, $url, $order ) {
			$body  = $this->strip_existing_payment_block( $body );
			$block = $this->build_payment_block( $url );

			if ( ! $this->should_hide_bank_transfer( $order ) && class_exists( 'KTPWP_Settings' ) ) {
				$bank_plain = KTPWP_Settings::get_bank_transfer_plain_text();
				if ( $bank_plain !== '' ) {
					$block .= "\n\n" . $bank_plain;
				}
			}

			$signature = "\n\n--\n";
			$pos       = strrpos( $body, $signature );
			if ( false === $pos ) {
				$signature = "\n--\n";
				$pos       = strrpos( $body, $signature );
			}
			if ( false !== $pos ) {
				return substr( $body, 0, $pos ) . $block . substr( $body, $pos );
			}

			return rtrim( $body ) . $block;
		}

		/**
		 * Invoice 作成または再利用。
		 *
		 * @param int  $order_id  受注 ID.
		 * @param bool $finalize  finalize するか。
		 * @return array{invoice_id: string, url: string}|WP_Error
		 */
		public function prepare_invoice_for_order( $order_id, $finalize = false ) {
			global $wpdb;

			$order_id = absint( $order_id );
			if ( $order_id <= 0 || ! self::is_enabled() ) {
				return new WP_Error( 'invalid', __( 'Stripe 請求の対象外です。', 'ktpwp' ) );
			}

			if ( ! class_exists( '\Stripe\StripeClient' ) ) {
				return new WP_Error( 'stripe_sdk_missing', __( 'Stripe SDK が読み込まれていません。', 'ktpwp' ) );
			}

			$order_table = $wpdb->prefix . 'ktp_order';
			$order       = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$order_table} WHERE id = %d",
					$order_id
				)
			);

			if ( ! $order || ! $this->should_apply_to_order( $order ) ) {
				return new WP_Error( 'invalid_order', __( 'Stripe 請求の対象外です。', 'ktpwp' ) );
			}

			if ( class_exists( 'KTPWP_Stripe_Subscription' )
				&& KTPWP_Stripe_Subscription::get_instance()->order_qualifies_for_immediate_subscription( $order ) ) {
				return KTPWP_Stripe_Subscription::get_instance()->prepare_subscription_invoice_for_order( $order_id, $finalize );
			}

			if ( ! empty( $order->stripe_paid_at ) ) {
				return new WP_Error( 'already_paid', __( 'この請求は入金済みです。', 'ktpwp' ) );
			}

			$client_id = (int) ( $order->client_id ?? 0 );
			if ( $client_id <= 0 ) {
				return new WP_Error( 'no_client', __( '顧客が見つかりません。', 'ktpwp' ) );
			}

			$customer_id = $this->get_or_create_customer( $client_id );
			if ( is_wp_error( $customer_id ) ) {
				return $customer_id;
			}

			$stripe = new \Stripe\StripeClient( self::get_secret_key() );
			$this->sync_account_business_profile( $stripe );

			$line_items = $this->get_order_line_items( $order_id );
			if ( empty( $line_items ) ) {
				return new WP_Error( 'no_items', __( '請求明細がありません。', 'ktpwp' ) );
			}

			$expected_total = $this->compute_expected_total_from_lines( $line_items );

			$reuse = $this->try_reuse_existing_invoice( $stripe, $order_id, $order, $expected_total, $finalize );
			if ( is_wp_error( $reuse ) ) {
				return $reuse;
			}
			if ( is_array( $reuse ) ) {
				return $reuse;
			}

			$metadata = array(
				'ktp_order_id' => (string) $order_id,
			);
			if ( ! empty( $order->contract_id ) ) {
				$metadata['ktp_contract_id'] = (string) (int) $order->contract_id;
			}
			if ( ! empty( $order->billing_period ) ) {
				$metadata['ktp_billing_period'] = (string) $order->billing_period;
			}

			try {
				$invoice = $stripe->invoices->create(
					array(
						'customer'          => $customer_id,
						'collection_method' => 'send_invoice',
						'days_until_due'    => self::get_days_until_due(),
						'auto_advance'      => false,
						'metadata'          => $metadata,
						'payment_settings'  => array(
							'payment_method_types' => array( 'card' ),
						),
					)
				);

				foreach ( $line_items as $line ) {
					$stripe->invoiceItems->create(
						array(
							'customer'    => $customer_id,
							'invoice'     => $invoice->id,
							'amount'      => (int) $line['amount'],
							'currency'    => 'jpy',
							'description' => $line['description'],
						)
					);
				}

				if ( $finalize ) {
					$invoice = $stripe->invoices->finalizeInvoice( $invoice->id );
				}

				$url = (string) ( $invoice->hosted_invoice_url ?? '' );
				$this->save_order_stripe_fields( $order_id, $invoice->id, $url );

				return array(
					'invoice_id' => (string) $invoice->id,
					'url'        => $url,
				);
			} catch ( Exception $e ) {
				return new WP_Error( 'stripe_error', $e->getMessage() );
			}
		}

		/**
		 * 請求明細を Stripe 行に変換。
		 *
		 * @param int $order_id 受注 ID.
		 * @return array<int, array{amount: int, description: string}>
		 */
		private function get_order_line_items( $order_id ) {
			$lines = array();

			if ( ! class_exists( 'KTPWP_Order_Items' ) ) {
				return $lines;
			}

			$items = KTPWP_Order_Items::get_instance()->get_invoice_items( $order_id );
			foreach ( $items as $item ) {
				if ( ! is_array( $item ) || ! class_exists( 'KTPWP_Invoice_Line_Amount' ) ) {
					continue;
				}

				if ( ! KTPWP_Invoice_Line_Amount::is_billable_now( $item ) ) {
					continue;
				}

				$name   = trim( (string) ( $item['product_name'] ?? '' ) );
				$amount = KTPWP_Invoice_Line_Amount::resolve_billable_amount( $item );
				$remarks = isset( $item['remarks'] ) ? trim( (string) $item['remarks'] ) : '';

				$desc = $name;
				if ( $remarks !== '' ) {
					$desc .= ' (' . $remarks . ')';
				}

				$lines[] = array(
					'amount'      => $amount,
					'description' => $desc,
				);
			}

			return $lines;
		}

		/**
		 * 請求行から Stripe Invoice 合計（円）を算出。
		 *
		 * @param array<int, array{amount: int, description: string}> $line_items 請求行。
		 * @return int
		 */
		public function compute_expected_total_from_lines( array $line_items ) {
			$total = 0;
			foreach ( $line_items as $line ) {
				$total += (int) ( $line['amount'] ?? 0 );
			}

			return $total;
		}

		/**
		 * 受注の今回請求合計（円）。
		 *
		 * @param int $order_id 受注 ID.
		 * @return int
		 */
		public function compute_expected_invoice_total_cents( $order_id ) {
			return $this->compute_expected_total_from_lines( $this->get_order_line_items( $order_id ) );
		}

		/**
		 * Stripe Invoice が受注の今回請求と一致するか。
		 *
		 * @param object $invoice        Stripe Invoice.
		 * @param int    $order_id       受注 ID.
		 * @param int    $expected_total 期待合計（円）。
		 * @return bool
		 */
		public function invoice_matches_order( $invoice, $order_id, $expected_total ) {
			$invoice_total = isset( $invoice->total ) ? (int) $invoice->total : 0;
			if ( $invoice_total !== (int) $expected_total ) {
				return false;
			}

			if ( isset( $invoice->metadata->ktp_order_id ) ) {
				$meta_order = (int) $invoice->metadata->ktp_order_id;
				if ( $meta_order > 0 && $meta_order !== (int) $order_id ) {
					return false;
				}
			}

			return true;
		}

		/**
		 * 既存 Invoice を再利用できる場合は URL を返す。不一致時は void して null。
		 *
		 * @param \Stripe\StripeClient $stripe         Stripe client.
		 * @param int                  $order_id       受注 ID.
		 * @param object               $order          受注行。
		 * @param int                  $expected_total 期待合計（円）。
		 * @param bool                 $finalize       finalize するか。
		 * @return array{invoice_id: string, url: string}|WP_Error|null
		 */
		private function try_reuse_existing_invoice( $stripe, $order_id, $order, $expected_total, $finalize ) {
			if ( empty( $order->stripe_invoice_id ) ) {
				return null;
			}

			try {
				$existing = $stripe->invoices->retrieve( (string) $order->stripe_invoice_id );
			} catch ( Exception $e ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'KTPWP Stripe retrieve existing: ' . $e->getMessage() );
				}

				return null;
			}

			if ( isset( $existing->status ) && $existing->status === 'paid' ) {
				return new WP_Error( 'already_paid', __( 'この請求は入金済みです。', 'ktpwp' ) );
			}

			if ( ! in_array( (string) $existing->status, array( 'draft', 'open' ), true ) ) {
				return null;
			}

			if ( ! $this->invoice_matches_order( $existing, $order_id, $expected_total ) ) {
				try {
					$stripe->invoices->voidInvoice( $existing->id );
				} catch ( Exception $e ) {
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( 'KTPWP Stripe void stale invoice: ' . $e->getMessage() );
					}
				}

				return null;
			}

			if ( $finalize && in_array( (string) $existing->status, array( 'draft', 'open' ), true ) ) {
				if ( $existing->status === 'draft' ) {
					$existing = $stripe->invoices->finalizeInvoice( $existing->id );
				}
				$this->save_order_stripe_fields( $order_id, $existing->id, $existing->hosted_invoice_url ?? '' );

				return array(
					'invoice_id' => (string) $existing->id,
					'url'        => (string) ( $existing->hosted_invoice_url ?? '' ),
				);
			}

			if ( ! $finalize && $existing->status === 'open' && ! empty( $existing->hosted_invoice_url ) ) {
				return array(
					'invoice_id' => (string) $existing->id,
					'url'        => (string) $existing->hosted_invoice_url,
				);
			}

			try {
				$stripe->invoices->voidInvoice( $existing->id );
			} catch ( Exception $e ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'KTPWP Stripe void draft invoice: ' . $e->getMessage() );
				}
			}

			return null;
		}

		/**
		 * Stripe Customer を取得または作成。
		 *
		 * @param int $client_id 顧客 ID.
		 * @return string|WP_Error
		 */
		public function get_or_create_customer( $client_id ) {
			global $wpdb;

			$client_id = absint( $client_id );
			if ( $client_id <= 0 ) {
				return new WP_Error( 'invalid_client', __( '顧客が見つかりません。', 'ktpwp' ) );
			}

			$table  = $wpdb->prefix . 'ktp_client';
			$client = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE id = %d",
					$client_id
				)
			);

			if ( ! $client ) {
				return new WP_Error( 'invalid_client', __( '顧客が見つかりません。', 'ktpwp' ) );
			}

			if ( ! empty( $client->stripe_customer_id ) ) {
				return (string) $client->stripe_customer_id;
			}

			if ( ! class_exists( '\Stripe\StripeClient' ) ) {
				return new WP_Error( 'stripe_sdk_missing', __( 'Stripe SDK が読み込まれていません。', 'ktpwp' ) );
			}

			$email = $this->resolve_client_email( $client );
			$name  = trim( (string) ( $client->company_name ?? '' ) . ' ' . (string) ( $client->name ?? '' ) );

			try {
				$stripe   = new \Stripe\StripeClient( self::get_secret_key() );
				$customer = $stripe->customers->create(
					array_filter(
						array(
							'email'    => $email !== '' ? $email : null,
							'name'     => $name !== '' ? $name : null,
							'metadata' => array(
								'ktp_client_id' => (string) $client_id,
							),
						)
					)
				);

				if ( $this->client_table_has_column( 'stripe_customer_id' ) ) {
					$wpdb->update(
						$table,
						array( 'stripe_customer_id' => $customer->id ),
						array( 'id' => $client_id ),
						array( '%s' ),
						array( '%d' )
					);
				}

				return (string) $customer->id;
			} catch ( Exception $e ) {
				return new WP_Error( 'stripe_error', $e->getMessage() );
			}
		}

		/**
		 * 入金反映。
		 *
		 * @param string   $invoice_id Stripe Invoice ID.
		 * @param int|null $paid_at    Unix timestamp.
		 * @return void
		 */
		public function mark_order_paid_by_invoice_id( $invoice_id, $paid_at = null ) {
			global $wpdb;

			$invoice_id = sanitize_text_field( $invoice_id );
			if ( $invoice_id === '' || ! $this->order_table_has_column( 'stripe_invoice_id' ) ) {
				return;
			}

			$order_table = $wpdb->prefix . 'ktp_order';
			$order       = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$order_table} WHERE stripe_invoice_id = %s LIMIT 1",
					$invoice_id
				)
			);

			if ( ! $order || ! empty( $order->stripe_paid_at ) ) {
				return;
			}

			if ( class_exists( 'KTPWP_Stripe_Subscription' ) ) {
				KTPWP_Stripe_Subscription::get_instance()->after_order_paid( $order, $invoice_id );
			}

			$paid_at_mysql = gmdate( 'Y-m-d H:i:s', $paid_at ? (int) $paid_at : time() );
			$update        = array( 'stripe_paid_at' => $paid_at_mysql );
			$formats       = array( '%s' );

			$new_progress = $this->resolve_progress_after_stripe_payment( $order );
			if ( null !== $new_progress ) {
				$update['progress'] = $new_progress;
				$formats[]          = '%d';
			}

			$wpdb->update(
				$order_table,
				$update,
				array( 'id' => (int) $order->id ),
				$formats,
				array( '%d' )
			);

			if ( class_exists( 'KTPWP_Stripe_Subscription' ) && ! empty( $order->client_id ) ) {
				$customer_id = $this->get_or_create_customer( (int) $order->client_id );
				if ( ! is_wp_error( $customer_id ) ) {
					KTPWP_Stripe_Subscription::get_instance()->sync_default_payment_method_from_invoice(
						$invoice_id,
						(string) $customer_id
					);
				}
			}
		}

		/**
		 * Stripe API から入金状態を同期（Webhook 未到達時のフォールバック）。
		 *
		 * @param int $order_id 受注 ID.
		 * @return bool 反映した場合 true。
		 */
		public function sync_order_payment_from_stripe( $order_id ) {
			if ( ! self::is_enabled() || ! class_exists( '\Stripe\StripeClient' ) ) {
				return false;
			}

			global $wpdb;

			$order_id = (int) $order_id;
			if ( $order_id <= 0 || ! $this->order_table_has_column( 'stripe_invoice_id' ) ) {
				return false;
			}

			$order_table = $wpdb->prefix . 'ktp_order';
			$order       = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$order_table} WHERE id = %d",
					$order_id
				)
			);

			if ( ! $order || empty( $order->stripe_invoice_id ) ) {
				return false;
			}

			if ( ! empty( $order->stripe_paid_at ) ) {
				return $this->repair_paid_order_progress( $order );
			}

			try {
				$stripe  = new \Stripe\StripeClient( self::get_secret_key() );
				$invoice = $stripe->invoices->retrieve( (string) $order->stripe_invoice_id );

				if ( ! isset( $invoice->status ) || (string) $invoice->status !== 'paid' ) {
					return false;
				}

				$paid_at = isset( $invoice->status_transitions->paid_at )
					? (int) $invoice->status_transitions->paid_at
					: time();
				$this->mark_order_paid_by_invoice_id( (string) $invoice->id, $paid_at );

				return true;
			} catch ( Exception $e ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'KTPWP Stripe sync: ' . $e->getMessage() );
				}
				return false;
			}
		}

		/**
		 * Stripe 入金後の進捗（1=受付中, 2=見積中, 3=受注 …）。
		 *
		 * @param object $order 受注行。
		 * @return int|null 更新先 progress。変更不要なら null。
		 */
		private function resolve_progress_after_stripe_payment( $order ) {
			$progress = isset( $order->progress ) ? (int) $order->progress : 0;

			if ( $this->is_public_web_order( $order ) && $progress < 3 ) {
				return 3;
			}

			if ( $progress >= 4 && $progress < 6 ) {
				return 6;
			}

			return null;
		}

		/**
		 * 入金済みだが進捗だけ未更新の受注を補正する。
		 *
		 * @param object $order 受注行。
		 * @return bool 補正した場合 true。
		 */
		private function repair_paid_order_progress( $order ) {
			global $wpdb;

			$new_progress = $this->resolve_progress_after_stripe_payment( $order );
			if ( null === $new_progress ) {
				return false;
			}

			$order_table = $wpdb->prefix . 'ktp_order';
			$updated     = $wpdb->update(
				$order_table,
				array( 'progress' => $new_progress ),
				array( 'id' => (int) $order->id ),
				array( '%d' ),
				array( '%d' )
			);

			return false !== $updated && $updated > 0;
		}

		/**
		 * 契約ステータス変更時。
		 *
		 * @param int    $contract_id 契約 ID.
		 * @param string $old_status  旧ステータス.
		 * @param string $new_status  新ステータス.
		 * @return void
		 */
		public function on_contract_status_changed( $contract_id, $old_status, $new_status ) {
			if ( ! self::is_enabled() || $old_status === $new_status ) {
				return;
			}

			if ( in_array( $new_status, array( 'paused', 'cancelled' ), true ) ) {
				$this->void_open_invoices_for_contract( $contract_id );
				if ( class_exists( 'KTPWP_Stripe_Subscription' ) ) {
					KTPWP_Stripe_Subscription::get_instance()->cancel_subscription_for_contract( $contract_id );
				}
			}
		}

		/**
		 * 契約に紐づく未払い Invoice を void。
		 *
		 * @param int $contract_id 契約 ID.
		 * @return void
		 */
		public function void_open_invoices_for_contract( $contract_id ) {
			global $wpdb;

			if ( ! self::is_enabled() || ! $this->order_table_has_column( 'stripe_invoice_id' ) ) {
				return;
			}

			$contract_id = absint( $contract_id );
			if ( $contract_id <= 0 || ! $this->order_table_has_column( 'contract_id' ) ) {
				return;
			}

			$order_table = $wpdb->prefix . 'ktp_order';
			$orders      = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id, stripe_invoice_id, stripe_paid_at FROM {$order_table}
					WHERE contract_id = %d AND stripe_invoice_id IS NOT NULL AND stripe_invoice_id <> '' AND stripe_paid_at IS NULL",
					$contract_id
				)
			);

			foreach ( $orders as $order ) {
				$this->void_invoice_if_open( (string) $order->stripe_invoice_id );
			}
		}

		/**
		 * 受注の Stripe Invoice を void。
		 *
		 * @param int $order_id 受注 ID.
		 * @return void
		 */
		public function void_invoice_for_order( $order_id ) {
			global $wpdb;

			$order_id = absint( $order_id );
			if ( $order_id <= 0 || ! $this->order_table_has_column( 'stripe_invoice_id' ) ) {
				return;
			}

			$order_table = $wpdb->prefix . 'ktp_order';
			$invoice_id  = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT stripe_invoice_id FROM {$order_table} WHERE id = %d",
					$order_id
				)
			);

			if ( $invoice_id ) {
				$this->void_invoice_if_open( (string) $invoice_id );
			}
		}

		/**
		 * open/draft Invoice を void。
		 *
		 * @param string $invoice_id Stripe Invoice ID.
		 * @return void
		 */
		private function void_invoice_if_open( $invoice_id ) {
			if ( ! self::is_enabled() || $invoice_id === '' || ! class_exists( '\Stripe\StripeClient' ) ) {
				return;
			}

			try {
				$stripe  = new \Stripe\StripeClient( self::get_secret_key() );
				$invoice = $stripe->invoices->retrieve( $invoice_id );
				if ( in_array( $invoice->status, array( 'draft', 'open' ), true ) ) {
					$stripe->invoices->voidInvoice( $invoice_id );
				}
			} catch ( Exception $e ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'KTPWP Stripe void: ' . $e->getMessage() );
				}
			}
		}

		/**
		 * 7日以上前の draft Invoice を void。
		 *
		 * @return void
		 */
		public function void_stale_draft_invoices() {
			global $wpdb;

			if ( ! self::is_enabled() || ! $this->order_table_has_column( 'stripe_invoice_id' ) ) {
				return;
			}

			$order_table = $wpdb->prefix . 'ktp_order';
			$cutoff_ts = time() - 7 * DAY_IN_SECONDS;

			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id, stripe_invoice_id FROM {$order_table}
					WHERE stripe_invoice_id IS NOT NULL AND stripe_invoice_id <> ''
					AND stripe_paid_at IS NULL AND time < %d",
					$cutoff_ts
				)
			);

			foreach ( $rows as $row ) {
				$this->void_invoice_if_open( (string) $row->stripe_invoice_id );
			}
		}

		/**
		 * 受注の Stripe カラムを保存。
		 *
		 * @param int    $order_id   受注 ID.
		 * @param string $invoice_id Invoice ID.
		 * @param string $url        Hosted URL.
		 * @return void
		 */
		/**
		 * 受注の Stripe Invoice 情報を保存（Subscription 連携からも利用）。
		 *
		 * @param int    $order_id   受注 ID.
		 * @param string $invoice_id Invoice ID.
		 * @param string $url        Hosted URL.
		 * @return void
		 */
		public function save_order_stripe_invoice( $order_id, $invoice_id, $url = '' ) {
			$this->save_order_stripe_fields( $order_id, $invoice_id, $url );
		}

		/**
		 * @param int    $order_id   受注 ID.
		 * @param string $invoice_id Invoice ID.
		 * @param string $url        Hosted URL.
		 * @return void
		 */
		private function save_order_stripe_fields( $order_id, $invoice_id, $url ) {
			global $wpdb;

			if ( ! $this->order_table_has_column( 'stripe_invoice_id' ) ) {
				return;
			}

			$data = array(
				'stripe_invoice_id' => sanitize_text_field( $invoice_id ),
			);
			$fmt  = array( '%s' );

			if ( $this->order_table_has_column( 'stripe_invoice_url' ) ) {
				$data['stripe_invoice_url'] = esc_url_raw( $url );
				$fmt[]                        = '%s';
			}

			$wpdb->update(
				$wpdb->prefix . 'ktp_order',
				$data,
				array( 'id' => absint( $order_id ) ),
				$fmt,
				array( '%d' )
			);
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

			$push( $client->email ?? '' );
			$push( $client->representative_name ?? '' );
			$push( $client->name ?? '' );

			return $candidates[0] ?? '';
		}

		/**
		 * @param string $column Column name.
		 * @return bool
		 */
		private function order_table_has_column( $column ) {
			global $wpdb;
			$table = $wpdb->prefix . 'ktp_order';
			$cols  = $wpdb->get_col( "SHOW COLUMNS FROM `{$table}`", 0 );

			return in_array( $column, $cols, true );
		}

		/**
		 * @param string $column Column name.
		 * @return bool
		 */
		private function client_table_has_column( $column ) {
			global $wpdb;
			$table = $wpdb->prefix . 'ktp_client';
			$cols  = $wpdb->get_col( "SHOW COLUMNS FROM `{$table}`", 0 );

			return in_array( $column, $cols, true );
		}

		/**
		 * 設定画面: セクション説明。
		 *
		 * @return void
		 */
		public static function render_settings_section_info() {
			if ( function_exists( 'ktpwp_is_feature_enabled' ) && ! ktpwp_is_feature_enabled( 'stripe_billing' ) ) {
				if ( class_exists( 'KTPWP_Edition' ) ) {
					echo wp_kses_post( KTPWP_Edition::get_upgrade_message_html( __( 'Stripe 請求連携', 'ktpwp' ) ) );
				}
				return;
			}
			echo '<p>' . esc_html__( '初回は Stripe Invoice（今回請求分）、初回費用なしの定額案件は Subscription を即時開始します。', 'ktpwp' ) . '</p>';
			echo '<p class="description">' . esc_html__( 'Webhook URL:', 'ktpwp' ) . ' <code>' . esc_html( self::get_webhook_url() ) . '</code></p>';
		}

		/**
		 * 設定画面: 有効化。
		 *
		 * @return void
		 */
		public static function render_enabled_field() {
			if ( function_exists( 'ktpwp_is_feature_enabled' ) && ! ktpwp_is_feature_enabled( 'stripe_billing' ) ) {
				echo '<span class="description">' . esc_html__( 'フリー版では利用できません。', 'ktpwp' ) . '</span>';
				return;
			}
			$options = get_option( 'ktp_general_settings', array() );
			$checked = ! empty( $options['stripe_enabled'] );
			echo '<label><input type="checkbox" name="ktp_general_settings[stripe_enabled]" value="1" ' . checked( $checked, true, false ) . '> ';
			echo esc_html__( 'Stripe 請求連携を有効にする', 'ktpwp' );
			echo '</label>';
		}

		/**
		 * 設定画面: テストモード。
		 *
		 * @return void
		 */
		public static function render_test_mode_field() {
			$options = get_option( 'ktp_general_settings', array() );
			$checked = ! empty( $options['stripe_test_mode'] );
			echo '<label><input type="checkbox" name="ktp_general_settings[stripe_test_mode]" value="1" ' . checked( $checked, true, false ) . '> ';
			echo esc_html__( 'テストモード（テスト用 API キーを使用）', 'ktpwp' );
			echo '</label>';
		}

		/**
		 * 設定画面: Secret Key（テスト）。
		 *
		 * @return void
		 */
		public static function render_secret_key_test_field() {
			$options = get_option( 'ktp_general_settings', array() );
			$value   = isset( $options['stripe_secret_key_test'] ) ? (string) $options['stripe_secret_key_test'] : '';
			echo '<input type="password" name="ktp_general_settings[stripe_secret_key_test]" value="' . esc_attr( $value ) . '" class="regular-text" autocomplete="off">';
		}

		/**
		 * 設定画面: Secret Key（本番）。
		 *
		 * @return void
		 */
		public static function render_secret_key_live_field() {
			$options = get_option( 'ktp_general_settings', array() );
			$value   = isset( $options['stripe_secret_key_live'] ) ? (string) $options['stripe_secret_key_live'] : '';
			echo '<input type="password" name="ktp_general_settings[stripe_secret_key_live]" value="' . esc_attr( $value ) . '" class="regular-text" autocomplete="off">';
		}

		/**
		 * 設定画面: Webhook Secret（テスト）。
		 *
		 * @return void
		 */
		public static function render_webhook_secret_test_field() {
			$options = get_option( 'ktp_general_settings', array() );
			$value   = isset( $options['stripe_webhook_secret_test'] ) ? (string) $options['stripe_webhook_secret_test'] : '';
			echo '<input type="password" name="ktp_general_settings[stripe_webhook_secret_test]" value="' . esc_attr( $value ) . '" class="regular-text" autocomplete="off">';
		}

		/**
		 * 設定画面: Webhook Secret（本番）。
		 *
		 * @return void
		 */
		public static function render_webhook_secret_live_field() {
			$options = get_option( 'ktp_general_settings', array() );
			$value   = isset( $options['stripe_webhook_secret_live'] ) ? (string) $options['stripe_webhook_secret_live'] : '';
			echo '<input type="password" name="ktp_general_settings[stripe_webhook_secret_live]" value="' . esc_attr( $value ) . '" class="regular-text" autocomplete="off">';
		}

		/**
		 * 設定画面: 支払期日。
		 *
		 * @return void
		 */
		public static function render_days_until_due_field() {
			$options = get_option( 'ktp_general_settings', array() );
			$value   = isset( $options['stripe_days_until_due'] ) ? absint( $options['stripe_days_until_due'] ) : 30;
			echo '<input type="number" min="1" max="90" step="1" name="ktp_general_settings[stripe_days_until_due]" value="' . esc_attr( (string) max( 1, $value ) ) . '" class="small-text"> ';
			echo esc_html__( '日', 'ktpwp' );
		}

		/**
		 * 設定画面: 請求元名（Hosted Invoice）。
		 *
		 * @return void
		 */
		public static function render_invoice_issuer_name_field() {
			$value = self::get_invoice_issuer_name();
			echo '<input type="text" name="ktp_general_settings[stripe_invoice_issuer_name]" value="' . esc_attr( $value ) . '" class="regular-text">';
			echo '<p class="description">' . esc_html__( 'Stripe 請求ページの「請求元」に表示されます。保存時と請求書作成時に Stripe アカウントへ同期されます。', 'ktpwp' ) . '</p>';
		}

		/**
		 * 設定画面: 定期請求メール自動送信。
		 *
		 * @return void
		 */
		public static function render_contract_invoice_auto_field() {
			if ( function_exists( 'ktpwp_is_feature_enabled' ) && ! ktpwp_is_feature_enabled( 'contract_invoice_auto_mail' ) ) {
				if ( class_exists( 'KTPWP_Edition' ) ) {
					echo wp_kses_post( KTPWP_Edition::get_upgrade_message_html( __( '定期請求メール自動送信', 'ktpwp' ) ) );
				}
				return;
			}
			$options = get_option( 'ktp_general_settings', array() );
			$checked = ! isset( $options['contract_invoice_auto_enabled'] ) || ! empty( $options['contract_invoice_auto_enabled'] );
			echo '<label><input type="checkbox" name="ktp_general_settings[contract_invoice_auto_enabled]" value="1" ' . checked( $checked, true, false ) . '> ';
			echo esc_html__( '定期契約の請求メールを自動送信する（初回請求を除く・Subscription 契約は対象外）', 'ktpwp' );
			echo '</label>';
		}
	}
}
