<?php
/**
 * Stripe Subscription（定期契約2回目以降の自動引き落とし）
 *
 * @package KTPWP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'KTPWP_Stripe_Subscription' ) ) {

	/**
	 * 初回は都度 Invoice、初回費用なしの定額案件は Subscription 即時開始。
	 */
	class KTPWP_Stripe_Subscription {

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
		 * 定期契約が Subscription 課金か。
		 *
		 * @param int|object|null $contract 契約 ID または行。
		 * @return bool
		 */
		public function contract_uses_subscription( $contract ) {
			if ( is_numeric( $contract ) ) {
				$contract = $this->get_contract( (int) $contract );
			}

			if ( ! $contract || ! is_object( $contract ) ) {
				return false;
			}

			return ! empty( $contract->stripe_subscription_id );
		}

		/**
		 * 初回費用なしの定額 WEB 受注か（見積時点で Subscription 即時開始対象）。
		 *
		 * @param object|null $order 受注行。
		 * @return bool
		 */
		public function order_qualifies_for_immediate_subscription( $order ) {
			if ( ! $order || ! class_exists( 'KTPWP_Stripe_Billing' ) ) {
				return false;
			}

			$stripe_billing = KTPWP_Stripe_Billing::get_instance();
			if ( ! $stripe_billing->is_public_web_order( $order ) ) {
				return false;
			}

			$progress = isset( $order->progress ) ? (int) $order->progress : 0;
			if ( 1 !== $progress ) {
				return false;
			}

			if ( isset( $order->contract_id ) && (int) $order->contract_id > 0 ) {
				return false;
			}

			if ( ! class_exists( 'KTPWP_Order_Contract_Draft_Resolver' ) ) {
				return false;
			}

			$draft = KTPWP_Order_Contract_Draft_Resolver::get_instance()->resolve( (int) $order->id );
			if ( ! $draft || ! empty( $draft['initial_fees'] ) ) {
				return false;
			}

			if ( empty( $draft['recurring_items'] ) && (float) ( $draft['amount'] ?? 0 ) <= 0 ) {
				return false;
			}

			return class_exists( 'KTPWP_Contract_Billing_Cycle' )
				&& KTPWP_Contract_Billing_Cycle::is_recurring( $draft['billing_cycle'] ?? '' );
		}

		/**
		 * 見積メール用: Subscription 初回 Invoice を用意する。
		 *
		 * @param int  $order_id  受注 ID.
		 * @param bool $finalize  finalize するか。
		 * @return array{invoice_id: string, url: string, subscription_id?: string}|WP_Error
		 */
		public function prepare_subscription_invoice_for_order( $order_id, $finalize = false ) {
			global $wpdb;

			$order_id = absint( $order_id );
			if ( $order_id <= 0 || ! class_exists( '\Stripe\StripeClient' ) || ! class_exists( 'KTPWP_Stripe_Billing' ) ) {
				return new WP_Error( 'invalid', __( 'Stripe 請求の対象外です。', 'ktpwp' ) );
			}

			$order_table = $wpdb->prefix . 'ktp_order';
			$order       = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$order_table} WHERE id = %d",
					$order_id
				)
			);

			if ( ! $order || ! $this->order_qualifies_for_immediate_subscription( $order ) ) {
				return new WP_Error( 'invalid_order', __( 'Stripe サブスクリプションの対象外です。', 'ktpwp' ) );
			}

			if ( ! empty( $order->stripe_paid_at ) ) {
				return new WP_Error( 'already_paid', __( 'この請求は入金済みです。', 'ktpwp' ) );
			}

			$stripe_billing = KTPWP_Stripe_Billing::get_instance();
			$customer_id    = $stripe_billing->get_or_create_customer( (int) $order->client_id );
			if ( is_wp_error( $customer_id ) ) {
				return $customer_id;
			}

			$stripe = new \Stripe\StripeClient( KTPWP_Stripe_Billing::get_secret_key() );
			$stripe_billing->sync_account_business_profile( $stripe );
			$expected_total = $stripe_billing->compute_expected_invoice_total_cents( $order_id );

			if ( ! empty( $order->stripe_subscription_id ) ) {
				try {
					$subscription = $stripe->subscriptions->retrieve(
						(string) $order->stripe_subscription_id,
						array( 'expand' => array( 'latest_invoice' ) )
					);
					$invoice        = $subscription->latest_invoice ?? null;
					if ( $invoice && isset( $invoice->status ) && $invoice->status === 'paid' ) {
						return new WP_Error( 'already_paid', __( 'この請求は入金済みです。', 'ktpwp' ) );
					}
					if ( $invoice && in_array( (string) $invoice->status, array( 'draft', 'open' ), true ) ) {
						if ( $expected_total > 0 && $stripe_billing->invoice_matches_order( $invoice, $order_id, $expected_total ) ) {
							if ( $finalize && $invoice->status === 'draft' ) {
								$invoice = $stripe->invoices->finalizeInvoice( $invoice->id );
							}
							$url = (string) ( $invoice->hosted_invoice_url ?? '' );
							$stripe_billing->save_order_stripe_invoice( $order_id, (string) $invoice->id, $url );

							return array(
								'invoice_id'      => (string) $invoice->id,
								'url'             => $url,
								'subscription_id' => (string) $subscription->id,
							);
						}

						$this->void_subscription_for_recreate(
							$stripe,
							$order_id,
							(string) $subscription->id,
							$invoice && ! empty( $invoice->id ) ? (string) $invoice->id : ''
						);
					}
				} catch ( Exception $e ) {
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( 'KTPWP Stripe retrieve subscription: ' . $e->getMessage() );
					}
				}
			}

			$draft = KTPWP_Order_Contract_Draft_Resolver::get_instance()->resolve( $order_id );
			if ( ! $draft ) {
				return new WP_Error( 'no_draft', __( '定期契約ドラフトが見つかりません。', 'ktpwp' ) );
			}

			$items = $this->build_subscription_items_from_draft( $draft, $stripe, $order_id );
			if ( empty( $items ) ) {
				return new WP_Error( 'no_items', __( '定期請求の明細がありません。', 'ktpwp' ) );
			}

			$billing_day = $this->resolve_immediate_billing_day();

			try {
				$subscription = $stripe->subscriptions->create(
					array(
						'customer'             => (string) $customer_id,
						'items'                => $items,
						'collection_method'    => 'send_invoice',
						'days_until_due'       => KTPWP_Stripe_Billing::get_days_until_due(),
						'metadata'             => array(
							'ktp_order_id'               => (string) $order_id,
							'ktp_immediate_subscription' => '1',
						),
						'payment_settings'     => array(
							'save_default_payment_method' => 'on_subscription',
						),
						'billing_cycle_anchor_config' => array(
							'day_of_month' => $billing_day,
						),
						'expand'               => array( 'latest_invoice' ),
					)
				);

				$invoice = $subscription->latest_invoice ?? null;
				if ( ! $invoice || empty( $invoice->id ) ) {
					return new WP_Error( 'stripe_no_invoice', __( 'Stripe 初回請求書の取得に失敗しました。', 'ktpwp' ) );
				}

				if ( $finalize && isset( $invoice->status ) && $invoice->status === 'draft' ) {
					$invoice = $stripe->invoices->finalizeInvoice( $invoice->id );
				}

				$url = (string) ( $invoice->hosted_invoice_url ?? '' );
				$this->save_order_subscription_id( $order_id, (string) $subscription->id );
				$stripe_billing->save_order_stripe_invoice( $order_id, (string) $invoice->id, $url );

				return array(
					'invoice_id'      => (string) $invoice->id,
					'url'             => $url,
					'subscription_id' => (string) $subscription->id,
				);
			} catch ( Exception $e ) {
				return new WP_Error( 'stripe_error', $e->getMessage() );
			}
		}

		/**
		 * 初回 Subscription 入金後に定期契約を自動作成する。
		 *
		 * @param object $order           受注行。
		 * @param string $invoice_id      Stripe Invoice ID.
		 * @return void
		 */
		public function after_order_paid( $order, $invoice_id ) {
			if ( ! $order || ! class_exists( '\Stripe\StripeClient' ) || $invoice_id === '' ) {
				return;
			}

			try {
				$stripe  = new \Stripe\StripeClient( KTPWP_Stripe_Billing::get_secret_key() );
				$invoice = $stripe->invoices->retrieve(
					$invoice_id,
					array(
						'expand' => array( 'subscription' ),
					)
				);

				if ( empty( $invoice->subscription ) ) {
					return;
				}

				$subscription = is_object( $invoice->subscription )
					? $invoice->subscription
					: $stripe->subscriptions->retrieve( (string) $invoice->subscription );

				if ( empty( $subscription->metadata->ktp_immediate_subscription ) ) {
					return;
				}

				$this->provision_contract_on_first_payment( (int) $order->id, (string) $subscription->id );
			} catch ( Exception $e ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'KTPWP Stripe after_order_paid: ' . $e->getMessage() );
				}
			}
		}

		/**
		 * 入金後に定期契約を自動作成し Subscription を紐付ける。
		 *
		 * @param int    $order_id         受注 ID.
		 * @param string $subscription_id  Subscription ID.
		 * @return int|WP_Error 契約 ID。
		 */
		public function provision_contract_on_first_payment( $order_id, $subscription_id ) {
			$order_id = absint( $order_id );
			if ( $order_id <= 0 || $subscription_id === '' || ! class_exists( 'KTPWP_Order_Contract_Conversion' ) ) {
				return new WP_Error( 'invalid', __( '自動契約の対象外です。', 'ktpwp' ) );
			}

			global $wpdb;

			$order = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}ktp_order WHERE id = %d",
					$order_id
				)
			);

			if ( ! $order ) {
				return new WP_Error( 'invalid_order', __( '自動契約の対象外です。', 'ktpwp' ) );
			}

			if ( (int) ( $order->contract_id ?? 0 ) > 0 ) {
				$this->attach_subscription_to_contract( (int) $order->contract_id, $subscription_id, $order_id );
				return (int) $order->contract_id;
			}

			if ( ! class_exists( 'KTPWP_Stripe_Billing' ) || ! KTPWP_Stripe_Billing::get_instance()->is_public_web_order( $order ) ) {
				return new WP_Error( 'invalid_order', __( '自動契約の対象外です。', 'ktpwp' ) );
			}

			$draft = KTPWP_Order_Contract_Draft_Resolver::get_instance()->resolve( $order_id );
			if ( ! $draft || ! empty( $draft['initial_fees'] ) ) {
				return new WP_Error( 'invalid_order', __( '自動契約の対象外です。', 'ktpwp' ) );
			}

			$billing_day = $this->resolve_immediate_billing_day();
			$contract_data = array(
				'client_id'          => (int) ( $draft['client_id'] ?? $order->client_id ?? 0 ),
				'service_id'         => (int) ( $draft['service_id'] ?? 0 ),
				'contract_name'      => (string) ( $draft['contract_name'] ?? '' ),
				'amount'             => (float) ( $draft['amount'] ?? 0 ),
				'billing_cycle'      => (string) ( $draft['billing_cycle'] ?? 'monthly' ),
				'billing_day'        => $billing_day,
				'payment_due_mode'   => 'contract',
				'start_date'         => wp_date( 'Y-m-d' ),
				'end_date'           => '',
				'status'             => 'active',
				'send_reminder_mail' => 1,
				'memo'               => sprintf(
					/* translators: %d: order id */
					__( '受注 #%d から自動作成', 'ktpwp' ),
					$order_id
				),
			);

			$conversion = KTPWP_Order_Contract_Conversion::get_instance();
			$result     = $conversion->convert(
				$order_id,
				$contract_data,
				array(),
				$draft['recurring_items'] ?? array(),
				true,
				class_exists( 'KTPWP_Contract_Billing' )
					? KTPWP_Contract_Billing::get_instance()->get_billing_period()
					: wp_date( 'Y-m' ),
				array(
					'skip_subscription_start' => true,
					'subscription_id'         => $subscription_id,
				)
			);

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			return (int) $result;
		}

		/**
		 * 既存 Subscription を契約・受注に紐付ける。
		 *
		 * @param int    $contract_id     契約 ID.
		 * @param string $subscription_id Subscription ID.
		 * @param int    $order_id        受注 ID.
		 * @return void
		 */
		public function attach_subscription_to_contract( $contract_id, $subscription_id, $order_id = 0 ) {
			$contract_id     = absint( $contract_id );
			$subscription_id = sanitize_text_field( $subscription_id );
			$order_id        = absint( $order_id );

			if ( $contract_id <= 0 || $subscription_id === '' || ! class_exists( '\Stripe\StripeClient' ) ) {
				return;
			}

			$this->save_subscription_id( $contract_id, $subscription_id );

			if ( $order_id > 0 ) {
				$this->save_order_subscription_id( $order_id, $subscription_id );
			}

			try {
				$stripe = new \Stripe\StripeClient( KTPWP_Stripe_Billing::get_secret_key() );
				$stripe->subscriptions->update(
					$subscription_id,
					array(
						'metadata' => array(
							'ktp_contract_id'              => (string) $contract_id,
							'ktp_immediate_subscription'   => '1',
							'ktp_order_id'                 => $order_id > 0 ? (string) $order_id : null,
						),
					)
				);
			} catch ( Exception $e ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'KTPWP Stripe attach subscription: ' . $e->getMessage() );
				}
			}
		}

		/**
		 * 契約変換後などに Subscription を開始する。
		 *
		 * @param int $contract_id     契約 ID.
		 * @param int $source_order_id 初回入金済み受注 ID（任意）.
		 * @return true|WP_Error
		 */
		public function maybe_start_for_contract( $contract_id, $source_order_id = 0 ) {
			if ( ! class_exists( 'KTPWP_Stripe_Billing' ) || ! KTPWP_Stripe_Billing::is_enabled() ) {
				return new WP_Error( 'stripe_disabled', __( 'Stripe 連携が無効です。', 'ktpwp' ) );
			}

			if ( ! class_exists( '\Stripe\StripeClient' ) ) {
				return new WP_Error( 'stripe_sdk_missing', __( 'Stripe SDK が読み込まれていません。', 'ktpwp' ) );
			}

			$contract_id = absint( $contract_id );
			$contract    = $this->get_contract( $contract_id );

			if ( ! $contract ) {
				return new WP_Error( 'not_found', __( '定期契約が見つかりません。', 'ktpwp' ) );
			}

			if ( isset( $contract->status ) && 'active' !== $contract->status ) {
				return new WP_Error( 'inactive', __( '有効な定期契約のみサブスクリプションを開始できます。', 'ktpwp' ) );
			}

			if ( ! empty( $contract->stripe_subscription_id ) ) {
				return true;
			}

			if ( $this->contract_has_no_initial_fees( $contract_id ) && $source_order_id > 0 ) {
				global $wpdb;
				$linked_sub = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT stripe_subscription_id FROM {$wpdb->prefix}ktp_order WHERE id = %d",
						$source_order_id
					)
				);
				if ( ! empty( $linked_sub ) ) {
					$this->attach_subscription_to_contract( $contract_id, (string) $linked_sub, $source_order_id );
					return true;
				}
			}

			if ( ! class_exists( 'KTPWP_Contract_Billing_Cycle' )
				|| ! KTPWP_Contract_Billing_Cycle::is_recurring( $contract->billing_cycle ?? '' ) ) {
				return new WP_Error( 'not_recurring', __( '都度請求の契約はサブスクリプション対象外です。', 'ktpwp' ) );
			}

			$client_id = (int) ( $contract->client_id ?? 0 );
			if ( $client_id <= 0 ) {
				return new WP_Error( 'no_client', __( '顧客が見つかりません。', 'ktpwp' ) );
			}

			$stripe_billing = KTPWP_Stripe_Billing::get_instance();
			$customer_id      = $stripe_billing->get_or_create_customer( $client_id );
			if ( is_wp_error( $customer_id ) ) {
				return $customer_id;
			}

			$source_order_id = absint( $source_order_id );
			if ( $source_order_id > 0 ) {
				global $wpdb;
				$order = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT stripe_invoice_id FROM {$wpdb->prefix}ktp_order WHERE id = %d",
						$source_order_id
					)
				);
				if ( $order && ! empty( $order->stripe_invoice_id ) ) {
					$this->sync_default_payment_method_from_invoice( (string) $order->stripe_invoice_id, (string) $customer_id );
				}
			}

			if ( ! $this->customer_has_default_payment_method( (string) $customer_id ) ) {
				$setup_url = $this->create_setup_checkout_for_contract( $contract_id, (string) $customer_id );
				if ( $setup_url !== '' ) {
					return new WP_Error(
						'no_payment_method',
						sprintf(
							/* translators: %s: Stripe Setup URL */
							__( 'カード登録が必要です。以下のリンクからカードを登録してください: %s', 'ktpwp' ),
							$setup_url
						)
					);
				}

				return new WP_Error(
					'no_payment_method',
					__( '初回決済のカード情報が Stripe に保存されていません。初回 Invoice をカードで支払った後、再度お試しください。', 'ktpwp' )
				);
			}

			$trial_end = $this->compute_subscription_trial_end( $contract );
			if ( ! $trial_end || $trial_end <= time() ) {
				return new WP_Error( 'invalid_trial', __( '次回請求日の算出に失敗しました。', 'ktpwp' ) );
			}

			try {
				$stripe = new \Stripe\StripeClient( KTPWP_Stripe_Billing::get_secret_key() );
				$items  = $this->build_subscription_items( $contract, $stripe );
				if ( empty( $items ) ) {
					return new WP_Error( 'no_items', __( '定期請求の明細がありません。', 'ktpwp' ) );
				}

				$params = array(
					'customer'             => (string) $customer_id,
					'items'                => $items,
					'trial_end'            => (int) $trial_end,
					'metadata'             => array(
						'ktp_contract_id' => (string) $contract_id,
					),
					'payment_settings'     => array(
						'save_default_payment_method' => 'on_subscription',
					),
					'proration_behavior'   => 'none',
				);

				$subscription = $stripe->subscriptions->create( $params );
				$this->save_subscription_id( $contract_id, (string) $subscription->id );

				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log(
						sprintf(
							'KTPWP Stripe Subscription started: contract=%d sub=%s trial_end=%s',
							$contract_id,
							$subscription->id,
							gmdate( 'Y-m-d H:i:s', (int) $trial_end )
						)
					);
				}

				return true;
			} catch ( Exception $e ) {
				return new WP_Error( 'stripe_error', $e->getMessage() );
			}
		}

		/**
		 * Subscription 請求の invoice.paid を KantanPro 受注へ反映。
		 *
		 * @param object $invoice Stripe Invoice.
		 * @return void
		 */
		public function handle_subscription_invoice_paid( $invoice ) {
			if ( ! $invoice || empty( $invoice->id ) ) {
				return;
			}

			if ( ! empty( $invoice->subscription ) && class_exists( '\Stripe\StripeClient' ) ) {
				try {
					$stripe       = new \Stripe\StripeClient( KTPWP_Stripe_Billing::get_secret_key() );
					$subscription = is_object( $invoice->subscription )
						? $invoice->subscription
						: $stripe->subscriptions->retrieve( (string) $invoice->subscription );
					if ( ! empty( $subscription->metadata->ktp_immediate_subscription )
						&& empty( $subscription->metadata->ktp_contract_id ) ) {
						return;
					}
				} catch ( Exception $e ) {
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( 'KTPWP Stripe subscription invoice skip: ' . $e->getMessage() );
					}
				}
			}

			$contract_id = $this->resolve_contract_id_from_invoice( $invoice );
			if ( $contract_id <= 0 ) {
				return;
			}

			if ( ! class_exists( 'KTPWP_Contract_Billing' ) || ! class_exists( 'KTPWP_Stripe_Billing' ) ) {
				return;
			}

			global $wpdb;

			$billing = KTPWP_Contract_Billing::get_instance();
			$period  = $this->resolve_billing_period_from_invoice( $invoice );

			if ( ! preg_match( '/^\d{4}-\d{2}$/', $period ) ) {
				return;
			}

			$order_id = 0;
			$log      = $billing->get_billing_log( $contract_id, $period );
			if ( $log && (int) $log->order_id > 0 ) {
				$order_id = (int) $log->order_id;
			}

			if ( $order_id <= 0 && ! empty( $invoice->subscription ) ) {
				$first_order_id = $this->resolve_first_order_id_from_subscription_invoice( $invoice );
				if ( $first_order_id > 0 ) {
					$order_id = $first_order_id;
					$billing->get_billing_log( $contract_id, $period );
					if ( ! $log ) {
						global $wpdb;
						$wpdb->insert(
							$wpdb->prefix . 'ktp_contract_billing_log',
							array(
								'contract_id'    => $contract_id,
								'order_id'       => $order_id,
								'billing_period' => $period,
							),
							array( '%d', '%d', '%s' )
						);
					}
				}
			}

			if ( $order_id <= 0 ) {
				$result = $billing->generate_order_for_contract( $contract_id, $period );
				if ( is_wp_error( $result ) ) {
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( 'KTPWP Stripe Subscription order gen: ' . $result->get_error_message() );
					}
					return;
				}
				$order_id = (int) $result;
			}

			if ( $order_id <= 0 ) {
				return;
			}

			$stripe_billing = KTPWP_Stripe_Billing::get_instance();
			$stripe_billing->save_order_stripe_invoice(
				$order_id,
				(string) $invoice->id,
				(string) ( $invoice->hosted_invoice_url ?? '' )
			);

			$paid_at = isset( $invoice->status_transitions->paid_at )
				? (int) $invoice->status_transitions->paid_at
				: time();
			$stripe_billing->mark_order_paid_by_invoice_id( (string) $invoice->id, $paid_at );
		}

		/**
		 * カード未登録時に Setup Checkout URL を発行する。
		 *
		 * @param int    $contract_id 契約 ID.
		 * @param string $customer_id Stripe Customer ID.
		 * @return string Checkout URL。失敗時は空文字。
		 */
		public function create_setup_checkout_for_contract( $contract_id, $customer_id ) {
			if ( ! class_exists( '\Stripe\StripeClient' ) || $contract_id <= 0 || $customer_id === '' ) {
				return '';
			}

			try {
				$stripe  = new \Stripe\StripeClient( KTPWP_Stripe_Billing::get_secret_key() );
				$session = $stripe->checkout->sessions->create(
					array(
						'mode'       => 'setup',
						'customer'   => $customer_id,
						'currency'   => 'jpy',
						'metadata'   => array(
							'ktp_contract_id' => (string) absint( $contract_id ),
							'ktp_setup_for'   => 'subscription',
						),
						'success_url' => add_query_arg(
							array(
								'ktp_stripe_setup' => 'success',
								'contract_id'      => absint( $contract_id ),
							),
							home_url( '/' )
						),
						'cancel_url'  => add_query_arg(
							array(
								'ktp_stripe_setup' => 'cancel',
								'contract_id'      => absint( $contract_id ),
							),
							home_url( '/' )
						),
					)
				);

				$url = isset( $session->url ) ? (string) $session->url : '';
				if ( $url !== '' ) {
					update_option( 'ktp_stripe_setup_url_' . absint( $contract_id ), $url, false );
				}

				return $url;
			} catch ( Exception $e ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'KTPWP Stripe setup checkout: ' . $e->getMessage() );
				}

				return '';
			}
		}

		/**
		 * Setup Checkout 完了後に Subscription を開始する。
		 *
		 * @param object $session Stripe Checkout Session.
		 * @return void
		 */
		public function handle_setup_checkout_completed( $session ) {
			if ( ! $session || empty( $session->metadata->ktp_contract_id ) ) {
				return;
			}

			$contract_id = absint( $session->metadata->ktp_contract_id );
			if ( $contract_id <= 0 ) {
				return;
			}

			$customer_id = isset( $session->customer ) ? (string) $session->customer : '';
			if ( $customer_id !== '' && ! empty( $session->setup_intent ) && class_exists( '\Stripe\StripeClient' ) ) {
				try {
					$stripe = new \Stripe\StripeClient( KTPWP_Stripe_Billing::get_secret_key() );
					$setup  = is_object( $session->setup_intent )
						? $session->setup_intent
						: $stripe->setupIntents->retrieve( (string) $session->setup_intent );

					if ( ! empty( $setup->payment_method ) ) {
						$this->attach_payment_method_to_customer( $stripe, (string) $setup->payment_method, $customer_id );
						$stripe->customers->update(
							$customer_id,
							array(
								'invoice_settings' => array(
									'default_payment_method' => (string) $setup->payment_method,
								),
							)
						);
					}
				} catch ( Exception $e ) {
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( 'KTPWP Stripe setup complete PM: ' . $e->getMessage() );
					}
				}
			}

			$result = $this->maybe_start_for_contract( $contract_id, 0 );
			if ( is_wp_error( $result ) && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'KTPWP Stripe Subscription after setup: ' . $result->get_error_message() );
			}
		}

		/**
		 * 契約停止・解約時に Subscription をキャンセル。
		 *
		 * @param int $contract_id 契約 ID.
		 * @return void
		 */
		public function cancel_subscription_for_contract( $contract_id ) {
			if ( ! class_exists( 'KTPWP_Stripe_Billing' ) || ! KTPWP_Stripe_Billing::is_enabled() ) {
				return;
			}

			$contract = $this->get_contract( absint( $contract_id ) );
			if ( ! $contract || empty( $contract->stripe_subscription_id ) || ! class_exists( '\Stripe\StripeClient' ) ) {
				return;
			}

			try {
				$stripe = new \Stripe\StripeClient( KTPWP_Stripe_Billing::get_secret_key() );
				$sub    = $stripe->subscriptions->retrieve( (string) $contract->stripe_subscription_id );
				if ( in_array( (string) ( $sub->status ?? '' ), array( 'active', 'trialing', 'past_due' ), true ) ) {
					$stripe->subscriptions->cancel( (string) $contract->stripe_subscription_id );
				}
			} catch ( Exception $e ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'KTPWP Stripe Subscription cancel: ' . $e->getMessage() );
				}
			}
		}

		/**
		 * 入金済み Invoice から Customer のデフォルトカードを設定。
		 *
		 * @param string $invoice_id  Stripe Invoice ID.
		 * @param string $customer_id Stripe Customer ID.
		 * @return bool
		 */
		public function sync_default_payment_method_from_invoice( $invoice_id, $customer_id ) {
			if ( ! class_exists( '\Stripe\StripeClient' ) || $invoice_id === '' || $customer_id === '' ) {
				return false;
			}

			try {
				$stripe  = new \Stripe\StripeClient( KTPWP_Stripe_Billing::get_secret_key() );
				$invoice = $stripe->invoices->retrieve(
					$invoice_id,
					array(
						'expand' => array( 'payments', 'payment_intent', 'charge' ),
					)
				);

				$payment_method = $this->resolve_payment_method_from_invoice( $stripe, $invoice, $customer_id );
				if ( $payment_method === '' ) {
					return false;
				}

				$this->attach_payment_method_to_customer( $stripe, $payment_method, $customer_id );

				$stripe->customers->update(
					$customer_id,
					array(
						'invoice_settings' => array(
							'default_payment_method' => $payment_method,
						),
					)
				);

				return true;
			} catch ( Exception $e ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'KTPWP Stripe sync PM: ' . $e->getMessage() );
				}

				return false;
			}
		}

		/**
		 * @param \Stripe\StripeClient $stripe       Stripe client.
		 * @param object               $invoice      Stripe Invoice.
		 * @param string               $customer_id  Stripe Customer ID.
		 * @return string PaymentMethod ID.
		 */
		private function resolve_payment_method_from_invoice( $stripe, $invoice, $customer_id ) {
			$payment_method = '';

			if ( ! empty( $invoice->payment_intent ) && is_object( $invoice->payment_intent ) && ! empty( $invoice->payment_intent->payment_method ) ) {
				return (string) $invoice->payment_intent->payment_method;
			}

			if ( ! empty( $invoice->charge ) && is_object( $invoice->charge ) && ! empty( $invoice->charge->payment_method ) ) {
				return (string) $invoice->charge->payment_method;
			}

			if ( ! empty( $invoice->payments ) && ! empty( $invoice->payments->data ) && is_array( $invoice->payments->data ) ) {
				foreach ( $invoice->payments->data as $payment_row ) {
					$pi_id = '';
					if ( ! empty( $payment_row->payment ) && ! empty( $payment_row->payment->payment_intent ) ) {
						$pi_id = (string) $payment_row->payment->payment_intent;
					}
					if ( $pi_id === '' ) {
						continue;
					}

					$pi = is_object( $payment_row->payment->payment_intent )
						? $payment_row->payment->payment_intent
						: $stripe->paymentIntents->retrieve( $pi_id );

					if ( ! empty( $pi->payment_method ) ) {
						return (string) $pi->payment_method;
					}
				}
			}

			if ( $customer_id !== '' ) {
				$charges = $stripe->charges->all(
					array(
						'customer' => $customer_id,
						'limit'    => 5,
					)
				);
				foreach ( $charges->data as $charge ) {
					if ( ! empty( $charge->payment_method ) && (string) ( $charge->status ?? '' ) === 'succeeded' ) {
						return (string) $charge->payment_method;
					}
				}
			}

			return $payment_method;
		}

		/**
		 * @param \Stripe\StripeClient $stripe          Stripe client.
		 * @param string               $payment_method  PaymentMethod ID.
		 * @param string               $customer_id     Customer ID.
		 * @return void
		 */
		private function attach_payment_method_to_customer( $stripe, $payment_method, $customer_id ) {
			if ( $payment_method === '' || $customer_id === '' ) {
				return;
			}

			try {
				$pm = $stripe->paymentMethods->retrieve( $payment_method );
				if ( empty( $pm->customer ) || (string) $pm->customer !== (string) $customer_id ) {
					$stripe->paymentMethods->attach(
						$payment_method,
						array(
							'customer' => $customer_id,
						)
					);
				}
			} catch ( Exception $e ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'KTPWP Stripe attach PM: ' . $e->getMessage() );
				}
			}
		}

		/**
		 * @param object $invoice Stripe Invoice.
		 * @return int
		 */
		private function resolve_contract_id_from_invoice( $invoice ) {
			if ( ! empty( $invoice->metadata->ktp_contract_id ) ) {
				return absint( $invoice->metadata->ktp_contract_id );
			}

			if ( empty( $invoice->subscription ) || ! class_exists( '\Stripe\StripeClient' ) ) {
				return 0;
			}

			try {
				$stripe       = new \Stripe\StripeClient( KTPWP_Stripe_Billing::get_secret_key() );
				$subscription = $stripe->subscriptions->retrieve( (string) $invoice->subscription );
				if ( ! empty( $subscription->metadata->ktp_contract_id ) ) {
					return absint( $subscription->metadata->ktp_contract_id );
				}
			} catch ( Exception $e ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'KTPWP Stripe resolve contract: ' . $e->getMessage() );
				}
			}

			return 0;
		}

		/**
		 * @param object $invoice Stripe Invoice.
		 * @return string YYYY-MM
		 */
		private function resolve_billing_period_from_invoice( $invoice ) {
			$ts = isset( $invoice->period_start ) ? (int) $invoice->period_start : 0;
			if ( $ts <= 0 ) {
				$ts = isset( $invoice->created ) ? (int) $invoice->created : time();
			}

			return wp_date( 'Y-m', $ts );
		}

		/**
		 * @param object               $contract 契約行。
		 * @param \Stripe\StripeClient $stripe   Stripe client.
		 * @return array<int, array<string, mixed>>
		 */
		private function build_subscription_items( $contract, $stripe ) {
			$items     = array();
			$recurring = class_exists( 'KTPWP_Contract_Recurring_Items' )
				? KTPWP_Contract_Recurring_Items::get_by_contract_id( (int) $contract->id )
				: array();
			$interval  = $this->stripe_recurring_interval( (string) ( $contract->billing_cycle ?? '' ) );

			if ( ! empty( $recurring ) ) {
				foreach ( $recurring as $item ) {
					$amount = (int) round( (float) ( $item->amount ?? 0 ) );
					$name   = trim( (string) ( $item->item_name ?? '' ) );
					if ( $amount <= 0 || $name === '' ) {
						continue;
					}

					$product_id = $this->get_or_create_stripe_product( $stripe, $name, $contract_id );
					if ( $product_id === '' ) {
						continue;
					}

					$items[] = array(
						'price_data' => array(
							'currency'    => 'jpy',
							'unit_amount' => $amount,
							'product'     => $product_id,
							'recurring'   => $interval,
						),
					);
				}
			} else {
				$amount = (int) round( (float) ( $contract->amount ?? 0 ) );
				$name   = trim( (string) ( $contract->contract_name ?? __( '定期契約', 'ktpwp' ) ) );
				if ( $amount > 0 && $name !== '' ) {
					$product_id = $this->get_or_create_stripe_product( $stripe, $name, $contract_id );
					if ( $product_id !== '' ) {
						$items[] = array(
							'price_data' => array(
								'currency'    => 'jpy',
								'unit_amount' => $amount,
								'product'     => $product_id,
								'recurring'   => $interval,
							),
						);
					}
				}
			}

			return $items;
		}

		/**
		 * @param \Stripe\StripeClient $stripe      Stripe client.
		 * @param string               $name        商品名。
		 * @param int                  $contract_id 契約 ID.
		 * @return string Product ID.
		 */
		private function get_or_create_stripe_product( $stripe, $name, $contract_id, $order_id = 0 ) {
			try {
				$metadata = array();
				if ( $contract_id > 0 ) {
					$metadata['ktp_contract_id'] = (string) absint( $contract_id );
				}
				if ( $order_id > 0 ) {
					$metadata['ktp_order_id'] = (string) absint( $order_id );
				}

				$product = $stripe->products->create(
					array(
						'name'     => $name,
						'metadata' => $metadata,
					)
				);

				return (string) ( $product->id ?? '' );
			} catch ( Exception $e ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'KTPWP Stripe product create: ' . $e->getMessage() );
				}

				return '';
			}
		}

		/**
		 * @param string $billing_cycle 請求サイクル。
		 * @return array{interval: string, interval_count: int}
		 */
		private function stripe_recurring_interval( $billing_cycle ) {
			$months = class_exists( 'KTPWP_Contract_Billing_Cycle' )
				? KTPWP_Contract_Billing_Cycle::get_interval_months( $billing_cycle )
				: 1;

			if ( $months >= 12 ) {
				return array(
					'interval'       => 'year',
					'interval_count' => 1,
				);
			}

			return array(
				'interval'       => 'month',
				'interval_count' => max( 1, $months ),
			);
		}

		/**
		 * 初回請求済みの場合、次回請求日まで trial を設定する。
		 *
		 * @param object $contract 契約行。
		 * @return int|null Unix timestamp.
		 */
		private function compute_subscription_trial_end( $contract ) {
			if ( ! class_exists( 'KTPWP_Contract_Billing' ) ) {
				return null;
			}

			$billing      = KTPWP_Contract_Billing::get_instance();
			$period       = $billing->get_billing_period();
			$interval     = class_exists( 'KTPWP_Contract_Billing_Cycle' )
				? KTPWP_Contract_Billing_Cycle::get_interval_months( (string) ( $contract->billing_cycle ?? '' ) )
				: 1;
			$billing_day  = (int) ( $contract->billing_day ?? 1 );
			$contract_id  = (int) ( $contract->id ?? 0 );

			if ( (int) ( $contract->first_billed ?? 0 ) === 1 ) {
				$log = $billing->get_billing_log( $contract_id, $period );
				if ( $log && (int) $log->order_id > 0 ) {
					$period = wp_date( 'Y-m', strtotime( $period . '-01 +' . max( 1, $interval ) . ' months' ) );
				}
			}

			$guard = 0;
			while ( ! $billing->is_contract_due_in_period( $contract, $period ) && $guard < 24 ) {
				$period = wp_date( 'Y-m', strtotime( $period . '-01 +1 month' ) );
				++$guard;
			}

			$billing_date = KTPWP_Contract_Billing::get_billing_date_for_period( $billing_day, $period );
			$ts           = strtotime( $billing_date . ' 09:00:00' );

			while ( $ts <= time() && $guard < 48 ) {
				$period       = wp_date( 'Y-m', strtotime( $period . '-01 +' . max( 1, $interval ) . ' months' ) );
				$billing_date = KTPWP_Contract_Billing::get_billing_date_for_period( $billing_day, $period );
				$ts           = strtotime( $billing_date . ' 09:00:00' );
				++$guard;
			}

			return $ts > time() ? $ts : null;
		}

		/**
		 * @param string $customer_id Stripe Customer ID.
		 * @return bool
		 */
		private function customer_has_default_payment_method( $customer_id ) {
			if ( ! class_exists( '\Stripe\StripeClient' ) || $customer_id === '' ) {
				return false;
			}

			try {
				$stripe   = new \Stripe\StripeClient( KTPWP_Stripe_Billing::get_secret_key() );
				$customer = $stripe->customers->retrieve(
					$customer_id,
					array(
						'expand' => array( 'invoice_settings.default_payment_method' ),
					)
				);

				if ( ! empty( $customer->invoice_settings->default_payment_method ) ) {
					return true;
				}

				$methods = $stripe->paymentMethods->all(
					array(
						'customer' => $customer_id,
						'type'     => 'card',
						'limit'    => 1,
					)
				);

				return ! empty( $methods->data );
			} catch ( Exception $e ) {
				return false;
			}
		}

		/**
		 * @param int    $contract_id     契約 ID.
		 * @param string $subscription_id Subscription ID.
		 * @return void
		 */
		private function save_subscription_id( $contract_id, $subscription_id ) {
			global $wpdb;

			if ( ! $this->contract_table_has_column( 'stripe_subscription_id' ) ) {
				return;
			}

			$wpdb->update(
				$wpdb->prefix . 'ktp_contract',
				array( 'stripe_subscription_id' => sanitize_text_field( $subscription_id ) ),
				array( 'id' => absint( $contract_id ) ),
				array( '%s' ),
				array( '%d' )
			);
		}

		/**
		 * @param int $contract_id 契約 ID.
		 * @return bool
		 */
		private function contract_has_no_initial_fees( $contract_id ) {
			if ( $contract_id <= 0 || ! class_exists( 'KTPWP_Contract_DB' ) ) {
				return false;
			}

			$fees = KTPWP_Contract_DB::get_instance()->get_initial_fees_by_contract_id( $contract_id );

			return empty( $fees );
		}

		/**
		 * @return int 1-28
		 */
		private function resolve_immediate_billing_day() {
			$day = (int) wp_date( 'j' );

			return min( 28, max( 1, $day ) );
		}

		/**
		 * @param array<string, mixed>         $draft    ドラフト。
		 * @param \Stripe\StripeClient         $stripe   Stripe client.
		 * @param int                          $order_id 受注 ID.
		 * @return array<int, array<string, mixed>>
		 */
		private function build_subscription_items_from_draft( $draft, $stripe, $order_id ) {
			$items    = array();
			$interval = $this->stripe_recurring_interval( (string) ( $draft['billing_cycle'] ?? 'monthly' ) );
			$rows     = $draft['recurring_items'] ?? array();

			if ( empty( $rows ) && (float) ( $draft['amount'] ?? 0 ) > 0 ) {
				$rows = array(
					array(
						'item_name' => (string) ( $draft['contract_name'] ?? __( '定期契約', 'ktpwp' ) ),
						'amount'    => (float) $draft['amount'],
					),
				);
			}

			foreach ( $rows as $row ) {
				$amount = (int) round( (float) ( $row['amount'] ?? 0 ) );
				$name   = trim( (string) ( $row['item_name'] ?? '' ) );
				if ( $amount <= 0 || $name === '' ) {
					continue;
				}

				$product_id = $this->get_or_create_stripe_product( $stripe, $name, 0, $order_id );
				if ( $product_id === '' ) {
					continue;
				}

				$items[] = array(
					'price_data' => array(
						'currency'    => 'jpy',
						'unit_amount' => $amount,
						'product'     => $product_id,
						'recurring'   => $interval,
					),
				);
			}

			return $items;
		}

		/**
		 * @param object $invoice Stripe Invoice.
		 * @return int
		 */
		private function resolve_first_order_id_from_subscription_invoice( $invoice ) {
			if ( empty( $invoice->subscription ) || ! class_exists( '\Stripe\StripeClient' ) ) {
				return 0;
			}

			try {
				$stripe       = new \Stripe\StripeClient( KTPWP_Stripe_Billing::get_secret_key() );
				$subscription = is_object( $invoice->subscription )
					? $invoice->subscription
					: $stripe->subscriptions->retrieve( (string) $invoice->subscription );

				return ! empty( $subscription->metadata->ktp_order_id )
					? absint( $subscription->metadata->ktp_order_id )
					: 0;
			} catch ( Exception $e ) {
				return 0;
			}
		}

		/**
		 * 金額不一致時: Invoice を void し Subscription を解約して再作成可能にする。
		 *
		 * @param \Stripe\StripeClient $stripe          Stripe client.
		 * @param int                  $order_id        受注 ID.
		 * @param string               $subscription_id Subscription ID.
		 * @param string               $invoice_id      Invoice ID.
		 * @return void
		 */
		private function void_subscription_for_recreate( $stripe, $order_id, $subscription_id, $invoice_id ) {
			if ( $invoice_id !== '' ) {
				try {
					$invoice = $stripe->invoices->retrieve( $invoice_id );
					if ( in_array( (string) $invoice->status, array( 'draft', 'open' ), true ) ) {
						$stripe->invoices->voidInvoice( $invoice_id );
					}
				} catch ( Exception $e ) {
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( 'KTPWP Stripe void subscription invoice: ' . $e->getMessage() );
					}
				}
			}

			if ( $subscription_id !== '' ) {
				try {
					$sub = $stripe->subscriptions->retrieve( $subscription_id );
					if ( isset( $sub->status ) && $sub->status !== 'canceled' ) {
						$stripe->subscriptions->cancel( $subscription_id );
					}
				} catch ( Exception $e ) {
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( 'KTPWP Stripe cancel subscription for recreate: ' . $e->getMessage() );
					}
				}
			}

			$this->save_order_subscription_id( $order_id, '' );
		}

		/**
		 * @param int    $order_id         受注 ID.
		 * @param string $subscription_id  Subscription ID.
		 * @return void
		 */
		private function save_order_subscription_id( $order_id, $subscription_id ) {
			global $wpdb;

			if ( ! $this->order_table_has_column( 'stripe_subscription_id' ) ) {
				return;
			}

			$wpdb->update(
				$wpdb->prefix . 'ktp_order',
				array( 'stripe_subscription_id' => sanitize_text_field( $subscription_id ) ),
				array( 'id' => absint( $order_id ) ),
				array( '%s' ),
				array( '%d' )
			);
		}

		/**
		 * @param string $column Column name.
		 * @return bool
		 */
		private function order_table_has_column( $column ) {
			global $wpdb;

			static $cache = array();
			$table        = $wpdb->prefix . 'ktp_order';

			if ( isset( $cache[ $column ] ) ) {
				return $cache[ $column ];
			}

			$columns          = $wpdb->get_col( "SHOW COLUMNS FROM `{$table}`" );
			$cache[ $column ] = is_array( $columns ) && in_array( $column, $columns, true );

			return $cache[ $column ];
		}

		/**
		 * 契約が Stripe Subscription 対象か。
		 *
		 * @param int|object|null $contract 契約 ID または行。
		 * @return bool
		 */
		public function contract_applies_to_stripe_subscription( $contract ) {
			if ( is_numeric( $contract ) ) {
				$contract = $this->get_contract( (int) $contract );
			}

			if ( ! $contract || ! is_object( $contract ) ) {
				return false;
			}

			if ( ! class_exists( 'KTPWP_Stripe_Billing' ) || ! KTPWP_Stripe_Billing::is_enabled() ) {
				return false;
			}

			return class_exists( 'KTPWP_Contract_Billing_Cycle' )
				&& KTPWP_Contract_Billing_Cycle::is_recurring( $contract->billing_cycle ?? '' );
		}

		/**
		 * 契約画面用: Subscription ステータスを Stripe API から取得。
		 *
		 * @param int $contract_id 契約 ID.
		 * @return array<string, mixed>|null
		 */
		public function get_subscription_status_for_contract( $contract_id ) {
			$contract_id = absint( $contract_id );
			$contract    = $this->get_contract( $contract_id );

			if ( ! $contract ) {
				return null;
			}

			if ( ! $this->contract_applies_to_stripe_subscription( $contract ) ) {
				return array(
					'applicable' => false,
				);
			}

			$result = array(
				'applicable'          => true,
				'subscription_id'     => (string) ( $contract->stripe_subscription_id ?? '' ),
				'status'              => '',
				'status_label'        => '',
				'next_billing_date'   => '',
				'has_payment_method'  => false,
				'needs_setup_link'    => false,
				'setup_url'           => '',
			);

			$cached_setup_url = get_option( 'ktp_stripe_setup_url_' . $contract_id, '' );
			if ( is_string( $cached_setup_url ) && $cached_setup_url !== '' ) {
				$result['setup_url'] = esc_url_raw( $cached_setup_url );
			}

			if ( class_exists( 'KTPWP_Stripe_Billing' ) ) {
				$customer_id = KTPWP_Stripe_Billing::get_instance()->get_or_create_customer( (int) $contract->client_id );
				if ( ! is_wp_error( $customer_id ) ) {
					$result['has_payment_method'] = $this->customer_has_default_payment_method( (string) $customer_id );
				}
			}

			if ( ! empty( $contract->stripe_subscription_id ) && class_exists( '\Stripe\StripeClient' ) ) {
				try {
					$stripe = new \Stripe\StripeClient( KTPWP_Stripe_Billing::get_secret_key() );
					$sub    = $stripe->subscriptions->retrieve( (string) $contract->stripe_subscription_id );
					$result['status']       = (string) ( $sub->status ?? '' );
					$result['status_label'] = $this->format_subscription_status_label( $result['status'] );

					$next_ts = 0;
					if ( 'trialing' === $result['status'] && ! empty( $sub->trial_end ) ) {
						$next_ts = (int) $sub->trial_end;
					} elseif ( ! empty( $sub->current_period_end ) ) {
						$next_ts = (int) $sub->current_period_end;
					}

					if ( $next_ts > 0 ) {
						$result['next_billing_date'] = wp_date( 'Y-m-d', $next_ts );
					}
				} catch ( Exception $e ) {
					$result['status']       = 'unknown';
					$result['status_label'] = __( '取得失敗', 'ktpwp' );
				}
			} else {
				if ( $result['has_payment_method'] ) {
					$result['status']       = 'not_started';
					$result['status_label'] = __( '未開始', 'ktpwp' );
				} else {
					$result['status']       = 'needs_card';
					$result['status_label'] = __( '要カード登録', 'ktpwp' );
				}
			}

			$result['needs_setup_link'] = $this->contract_needs_setup_link( $contract, $result );

			return $result;
		}

		/**
		 * カード登録 Setup リンクを表示・発行すべきか。
		 *
		 * @param int|object|null      $contract        契約 ID または行。
		 * @param array<string, mixed> $status_context  get_subscription_status_for_contract の結果。
		 * @return bool
		 */
		public function contract_needs_setup_link( $contract, $status_context = null ) {
			if ( is_numeric( $contract ) ) {
				$contract = $this->get_contract( (int) $contract );
			}

			if ( ! $contract || ! $this->contract_applies_to_stripe_subscription( $contract ) ) {
				return false;
			}

			if ( isset( $contract->status ) && 'active' !== $contract->status ) {
				return false;
			}

			if ( ! is_array( $status_context ) ) {
				$status_context = array(
					'applicable'         => true,
					'has_payment_method' => false,
					'status'             => '',
				);

				if ( class_exists( 'KTPWP_Stripe_Billing' ) ) {
					$customer_id = KTPWP_Stripe_Billing::get_instance()->get_or_create_customer( (int) $contract->client_id );
					if ( ! is_wp_error( $customer_id ) ) {
						$status_context['has_payment_method'] = $this->customer_has_default_payment_method( (string) $customer_id );
					}
				}

				if ( ! empty( $contract->stripe_subscription_id ) && class_exists( '\Stripe\StripeClient' ) ) {
					try {
						$stripe                   = new \Stripe\StripeClient( KTPWP_Stripe_Billing::get_secret_key() );
						$sub                      = $stripe->subscriptions->retrieve( (string) $contract->stripe_subscription_id );
						$status_context['status'] = (string) ( $sub->status ?? '' );
					} catch ( Exception $e ) {
						$status_context['status'] = 'unknown';
					}
				} elseif ( $status_context['has_payment_method'] ) {
					$status_context['status'] = 'not_started';
				} else {
					$status_context['status'] = 'needs_card';
				}
			}

			if ( ! is_array( $status_context ) || empty( $status_context['applicable'] ) ) {
				return false;
			}

			if ( ! empty( $status_context['has_payment_method'] ) ) {
				return false;
			}

			if ( $this->subscription_is_started( $contract, $status_context ) ) {
				return false;
			}

			return true;
		}

		/**
		 * 契約向け Setup Checkout URL を発行する。
		 *
		 * @param int $contract_id 契約 ID.
		 * @return array{url: string}|WP_Error
		 */
		public function issue_setup_checkout_for_contract( $contract_id ) {
			$contract_id = absint( $contract_id );
			$contract    = $this->get_contract( $contract_id );

			if ( ! $contract ) {
				return new WP_Error( 'not_found', __( '定期契約が見つかりません。', 'ktpwp' ) );
			}

			if ( ! $this->contract_needs_setup_link( $contract ) ) {
				return new WP_Error( 'not_needed', __( 'カード登録リンクは不要です。', 'ktpwp' ) );
			}

			if ( ! class_exists( 'KTPWP_Stripe_Billing' ) ) {
				return new WP_Error( 'stripe_disabled', __( 'Stripe 連携が無効です。', 'ktpwp' ) );
			}

			$customer_id = KTPWP_Stripe_Billing::get_instance()->get_or_create_customer( (int) $contract->client_id );
			if ( is_wp_error( $customer_id ) ) {
				return $customer_id;
			}

			$url = $this->create_setup_checkout_for_contract( $contract_id, (string) $customer_id );
			if ( $url === '' ) {
				return new WP_Error( 'stripe_error', __( 'カード登録リンクの発行に失敗しました。', 'ktpwp' ) );
			}

			return array(
				'url' => $url,
			);
		}

		/**
		 * Subscription ステータスの日本語ラベル。
		 *
		 * @param string $status Stripe status.
		 * @return string
		 */
		private function format_subscription_status_label( $status ) {
			$labels = array(
				'active'             => __( '稼働中', 'ktpwp' ),
				'trialing'           => __( 'トライアル', 'ktpwp' ),
				'past_due'           => __( '支払い遅延', 'ktpwp' ),
				'unpaid'             => __( '未払い', 'ktpwp' ),
				'canceled'           => __( '解約済み', 'ktpwp' ),
				'incomplete'         => __( '未完了', 'ktpwp' ),
				'incomplete_expired' => __( '期限切れ', 'ktpwp' ),
				'paused'             => __( '一時停止', 'ktpwp' ),
				'not_started'        => __( '未開始', 'ktpwp' ),
				'needs_card'         => __( '要カード登録', 'ktpwp' ),
				'unknown'            => __( '取得失敗', 'ktpwp' ),
			);

			return isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
		}

		/**
		 * @param object               $contract       契約行。
		 * @param array<string, mixed> $status_context ステータス情報。
		 * @return bool
		 */
		private function subscription_is_started( $contract, $status_context ) {
			if ( empty( $contract->stripe_subscription_id ) ) {
				return false;
			}

			$started_statuses = array( 'active', 'trialing', 'past_due', 'unpaid', 'incomplete' );
			$status           = isset( $status_context['status'] ) ? (string) $status_context['status'] : '';

			return in_array( $status, $started_statuses, true );
		}

		/**
		 * @param int $contract_id 契約 ID.
		 * @return object|null
		 */
		private function get_contract( $contract_id ) {
			if ( $contract_id <= 0 || ! class_exists( 'KTPWP_Contract_DB' ) ) {
				return null;
			}

			return KTPWP_Contract_DB::get_instance()->get_contract_by_id( $contract_id );
		}

		/**
		 * @param string $column Column name.
		 * @return bool
		 */
		private function contract_table_has_column( $column ) {
			global $wpdb;

			static $cache = array();
			$table        = $wpdb->prefix . 'ktp_contract';

			if ( isset( $cache[ $column ] ) ) {
				return $cache[ $column ];
			}

			$columns          = $wpdb->get_col( "SHOW COLUMNS FROM `{$table}`" );
			$cache[ $column ] = is_array( $columns ) && in_array( $column, $columns, true );

			return $cache[ $column ];
		}
	}
}
