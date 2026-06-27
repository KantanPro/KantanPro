<?php
/**
 * 定期契約 UI
 *
 * @package KTPWP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'KTPWP_Contract_UI' ) ) {

	/**
	 * 顧客タブ内の定期契約セクション描画。
	 */
	class KTPWP_Contract_UI {

		/** @var self|null */
		private static $instance = null;

		/** @var KTPWP_Contract_DB */
		private $db;

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
			$this->db = KTPWP_Contract_DB::get_instance();
		}

		/**
		 * 顧客詳細に定期契約セクションを出力する。
		 *
		 * @param int $client_id 顧客 ID。
		 * @return string
		 */
		public function render_client_section( $client_id ) {
			if ( function_exists( 'ktpwp_contracts_feature_enabled' ) && ! ktpwp_contracts_feature_enabled() ) {
				return '';
			}

			$client_id = absint( $client_id );
			if ( $client_id <= 0 || ! $this->db->tables_exist() ) {
				return '';
			}

			$contracts         = $this->db->get_contracts_by_client_id( $client_id );
			$recurring_services = $this->db->get_recurring_services();
			$fee_presets       = KTPWP_Contract_DB::get_initial_fee_presets();
			$status_labels     = $this->get_status_labels();
			$billing_day_opts  = $this->get_billing_day_options();
			$stripe_enabled    = class_exists( 'KTPWP_Stripe_Billing' ) && KTPWP_Stripe_Billing::is_enabled();

			$html  = '<div class="ktp-contract-section" id="ktp-contract-section" data-client-id="' . esc_attr( (string) $client_id ) . '">';
			$html .= '<h4 class="ktp-contract-section__title">' . esc_html__( '■ 定期契約', 'ktpwp' ) . '</h4>';
			if ( function_exists( 'ktpwp_is_feature_enabled' ) && ! ktpwp_is_feature_enabled( 'stripe_billing' ) && class_exists( 'KTPWP_Edition' ) ) {
				$html .= KTPWP_Edition::get_upgrade_message_html( __( 'Stripe 請求連携', 'ktpwp' ) );
			}

			if ( empty( $recurring_services ) ) {
				$html .= '<p class="ktp-contract-section__hint">' . esc_html__( '定期請求に使えるサービスがありません。サービスタブで「契約（請求サイクル）」を都度以外に設定してください。', 'ktpwp' ) . '</p>';
			} else {
				$html .= '<div class="ktp-contract-section__toolbar">';
				$html .= $this->render_action_button(
					array(
						'id'    => 'ktp-contract-add-btn',
						'class' => 'ktp-contract-action-btn--primary',
						'icon'  => 'add',
						'label' => __( '新規定期契約', 'ktpwp' ),
					)
				);
				$html .= '</div>';
			}

			$html .= '<div id="ktp-contract-list-wrap">';
			$html .= $this->render_contract_list_table( $contracts, $recurring_services, $status_labels, $stripe_enabled );
			$html .= '</div>';

			$html .= '<div id="ktp-contract-form-wrap" class="ktp-contract-form-wrap" style="display:none;">';
			$html .= $this->render_contract_form( $client_id, $recurring_services, $fee_presets, $status_labels, $billing_day_opts, $stripe_enabled );
			$html .= '</div>';

			$html .= '</div>';

			return $html;
		}

		/**
		 * 契約一覧テーブル
		 *
		 * @param array<int, object>        $contracts          契約一覧。
		 * @param array<int, object>        $recurring_services 定期サービス。
		 * @param array<string, string>     $status_labels      状態ラベル。
		 * @param bool                      $stripe_enabled     Stripe 有効か。
		 * @return string
		 */
		private function render_contract_list_table( $contracts, $recurring_services, $status_labels, $stripe_enabled = false ) {
			$service_map = array();
			foreach ( $recurring_services as $service ) {
				$service_map[ (int) $service->id ] = $service;
			}

			if ( empty( $contracts ) ) {
				return '<p class="ktp-contract-section__empty">' . esc_html__( '定期契約はまだ登録されていません。', 'ktpwp' ) . '</p>';
			}

			$html  = '<table class="ktp-contract-list-table">';
			$html .= '<thead><tr>';
			$html .= '<th>' . esc_html__( '契約名', 'ktpwp' ) . '</th>';
			$html .= '<th>' . esc_html__( 'サービス', 'ktpwp' ) . '</th>';
			$html .= '<th>' . esc_html__( '金額', 'ktpwp' ) . '</th>';
			$html .= '<th>' . esc_html__( 'サイクル', 'ktpwp' ) . '</th>';
			$html .= '<th>' . esc_html__( '請求日', 'ktpwp' ) . '</th>';
			$html .= '<th>' . esc_html__( '状態', 'ktpwp' ) . '</th>';
			if ( $stripe_enabled ) {
				$html .= '<th>' . esc_html__( 'Stripe', 'ktpwp' ) . '</th>';
			}
			$html .= '<th>' . esc_html__( '操作', 'ktpwp' ) . '</th>';
			$html .= '</tr></thead><tbody>';

			foreach ( $contracts as $contract ) {
				$service      = isset( $service_map[ (int) $contract->service_id ] ) ? $service_map[ (int) $contract->service_id ] : null;
				$service_name = $service ? $service->service_name : '—';
				$cycle_label  = class_exists( 'KTPWP_Contract_Billing_Cycle' )
					? KTPWP_Contract_Billing_Cycle::get_label( $contract->billing_cycle )
					: $contract->billing_cycle;
				$status       = isset( $status_labels[ $contract->status ] ) ? $status_labels[ $contract->status ] : $contract->status;
				$amount       = class_exists( 'KTPWP_Settings' )
					? KTPWP_Settings::format_money( (float) $contract->amount )
					: number_format( (float) $contract->amount );

				$html .= '<tr data-contract-id="' . esc_attr( (string) $contract->id ) . '">';
				$html .= '<td>' . esc_html( $contract->contract_name ) . '</td>';
				$html .= '<td>' . esc_html( $service_name ) . '</td>';
				$html .= '<td class="ktp-contract-list-table__amount">' . esc_html( $amount ) . '</td>';
				$html .= '<td>' . esc_html( $cycle_label ) . '</td>';
				$html .= '<td>' . esc_html( $this->format_billing_day_label( (int) $contract->billing_day ) ) . '</td>';
				$html .= '<td><span class="ktp-contract-status ktp-contract-status--' . esc_attr( $contract->status ) . '">' . esc_html( $status ) . '</span></td>';
				if ( $stripe_enabled ) {
					$html .= '<td class="ktp-contract-list-table__stripe">';
					$html .= $this->render_list_stripe_cell( $contract );
					$html .= '</td>';
				}
				$html .= '<td class="ktp-contract-list-table__actions">';
				$html .= $this->render_action_button(
					array(
						'class'       => 'ktp-contract-action-btn--icon ktp-contract-edit-btn',
						'icon'        => 'edit',
						'label'       => __( '編集', 'ktpwp' ),
						'show_label'  => false,
						'extra_attrs' => ' data-contract-id="' . esc_attr( (string) $contract->id ) . '"',
					)
				);
				$html .= ' ';
				$html .= $this->render_action_button(
					array(
						'class'       => 'ktp-contract-action-btn--danger ktp-contract-action-btn--icon ktp-contract-delete-btn',
						'icon'        => 'delete',
						'label'       => __( '削除', 'ktpwp' ),
						'show_label'  => false,
						'extra_attrs' => ' data-contract-id="' . esc_attr( (string) $contract->id ) . '"',
					)
				);
				$html .= '</td>';
				$html .= '</tr>';
			}

			$html .= '</tbody></table>';

			return $html;
		}

		/**
		 * 契約編集フォーム
		 *
		 * @param int                   $client_id          顧客 ID。
		 * @param array<int, object>    $recurring_services 定期サービス。
		 * @param array<int, string>    $fee_presets        初回費用プリセット。
		 * @param array<string, string> $status_labels      状態ラベル。
		 * @param array<int, string>    $billing_day_opts   請求日選択肢。
		 * @param bool                  $stripe_enabled     Stripe 有効か。
		 * @return string
		 */
		private function render_contract_form( $client_id, $recurring_services, $fee_presets, $status_labels, $billing_day_opts, $stripe_enabled = false ) {
			$html  = '<h5 class="ktp-contract-form__heading" id="ktp-contract-form-heading">' . esc_html__( '定期契約を追加', 'ktpwp' ) . '</h5>';
			$html .= '<input type="hidden" id="ktp-contract-id" value="0">';
			$html .= '<input type="hidden" id="ktp-contract-client-id" value="' . esc_attr( (string) $client_id ) . '">';

			$html .= '<div class="ktp-contract-form__grid">';
			$html .= '<div class="ktp-contract-form__field">';
			$html .= '<label for="ktp-contract-name">' . esc_html__( '契約名', 'ktpwp' ) . ' <span class="required">*</span></label>';
			$html .= '<input type="text" id="ktp-contract-name" maxlength="255" placeholder="' . esc_attr__( '例: ○○ビル 101号室 家賃', 'ktpwp' ) . '">';
			$html .= '</div>';

			$html .= '<div class="ktp-contract-form__field">';
			$html .= '<label for="ktp-contract-service-id">' . esc_html__( 'サービス', 'ktpwp' ) . ' <span class="required">*</span></label>';
			$html .= '<select id="ktp-contract-service-id">';
			$html .= '<option value="">' . esc_html__( '選択してください', 'ktpwp' ) . '</option>';
			foreach ( $recurring_services as $service ) {
				$cycle = class_exists( 'KTPWP_Contract_Billing_Cycle' )
					? KTPWP_Contract_Billing_Cycle::sanitize( $service->contract_billing_cycle ?? 'none' )
					: 'monthly';
				$service_recurring = class_exists( 'KTPWP_Contract_Recurring_Items' )
					? KTPWP_Contract_Recurring_Items::rows_to_payload(
						KTPWP_Contract_Recurring_Items::get_by_service_id( (int) $service->id )
					)
					: array();
				$html .= '<option value="' . esc_attr( (string) $service->id ) . '"'
					. ' data-price="' . esc_attr( (string) $service->price ) . '"'
					. ' data-cycle="' . esc_attr( $cycle ) . '"'
					. ' data-tax-rate="' . esc_attr( $service->tax_rate !== null ? (string) $service->tax_rate : '' ) . '"'
					. ' data-recurring-items="' . esc_attr( wp_json_encode( $service_recurring ) ) . '">'
					. esc_html( $service->service_name )
					. '</option>';
			}
			$html .= '</select>';
			$html .= '</div>';

			$html .= '<div class="ktp-contract-form__field">';
			$html .= '<label for="ktp-contract-amount">' . esc_html__( '請求金額', 'ktpwp' ) . '</label>';
			$html .= '<input type="number" id="ktp-contract-amount" min="0" step="0.01" value="0">';
			$html .= '<p class="description">' . esc_html__( '定期請求項目がある場合は、項目の合計が契約金額になります。', 'ktpwp' ) . '</p>';
			$html .= '</div>';

			$html .= '<div class="ktp-contract-form__field">';
			$html .= '<label for="ktp-contract-billing-cycle">' . esc_html__( '請求サイクル', 'ktpwp' ) . '</label>';
			$html .= '<select id="ktp-contract-billing-cycle">';
			if ( class_exists( 'KTPWP_Contract_Billing_Cycle' ) ) {
				foreach ( KTPWP_Contract_Billing_Cycle::get_recurring_options() as $value => $label ) {
					$html .= '<option value="' . esc_attr( $value ) . '">' . esc_html( $label ) . '</option>';
				}
			}
			$html .= '</select>';
			$html .= '</div>';

			$html .= '<div class="ktp-contract-form__field">';
			$html .= '<label for="ktp-contract-billing-day">' . esc_html__( '請求日', 'ktpwp' ) . '</label>';
			$html .= '<select id="ktp-contract-billing-day">';
			foreach ( $billing_day_opts as $day_value => $day_label ) {
				$html .= '<option value="' . esc_attr( (string) $day_value ) . '">' . esc_html( $day_label ) . '</option>';
			}
			$html .= '</select>';
			$html .= '</div>';

			$html .= '<div class="ktp-contract-form__field">';
			$html .= '<label for="ktp-contract-payment-due-mode">' . esc_html__( '入金期日', 'ktpwp' ) . '</label>';
			$html .= '<select id="ktp-contract-payment-due-mode">';
			foreach ( KTPWP_Contract_DB::get_payment_due_mode_options() as $value => $label ) {
				$selected = ( 'contract' === $value ) ? ' selected' : '';
				$html    .= '<option value="' . esc_attr( $value ) . '"' . $selected . '>' . esc_html( $label ) . '</option>';
			}
			$html .= '</select>';
			$html .= '<p class="description">' . esc_html__( '家賃などは「契約の請求日」。都度請求と同じ締め支払ルールで回収する場合は「顧客の締め支払日」を選びます。', 'ktpwp' ) . '</p>';
			$html .= '</div>';

			$html .= '<div class="ktp-contract-form__field">';
			$html .= '<label for="ktp-contract-start-date">' . esc_html__( '契約開始日', 'ktpwp' ) . '</label>';
			$html .= '<input type="date" id="ktp-contract-start-date" value="' . esc_attr( wp_date( 'Y-m-d' ) ) . '">';
			$html .= '</div>';

			$html .= '<div class="ktp-contract-form__field">';
			$html .= '<label for="ktp-contract-end-date">' . esc_html__( '契約終了日', 'ktpwp' ) . '</label>';
			$html .= '<input type="date" id="ktp-contract-end-date">';
			$html .= '<p class="description">' . esc_html__( '空欄の場合は自動更新', 'ktpwp' ) . '</p>';
			$html .= '</div>';

			$html .= '<div class="ktp-contract-form__field">';
			$html .= '<label for="ktp-contract-status">' . esc_html__( '状態', 'ktpwp' ) . '</label>';
			$html .= '<select id="ktp-contract-status">';
			foreach ( $status_labels as $value => $label ) {
				$html .= '<option value="' . esc_attr( $value ) . '">' . esc_html( $label ) . '</option>';
			}
			$html .= '</select>';
			$html .= '</div>';

			$html .= '<div class="ktp-contract-form__field ktp-contract-form__field--checkbox">';
			$html .= '<label><input type="checkbox" id="ktp-contract-send-reminder" value="1" checked> ' . esc_html__( '請求予定メールを送る（請求日の3日前）', 'ktpwp' ) . '</label>';
			$html .= '</div>';
			$html .= '</div>';

			$html .= '<div class="ktp-contract-form__field">';
			$html .= '<label for="ktp-contract-memo">' . esc_html__( 'メモ', 'ktpwp' ) . '</label>';
			$html .= '<textarea id="ktp-contract-memo" rows="2"></textarea>';
			$html .= '</div>';

			$html .= $this->render_recurring_items_block();

			$html .= $this->render_initial_fees_block( $fee_presets );

			if ( $stripe_enabled ) {
				$html .= $this->render_stripe_subscription_block();
			}

			$html .= '<div class="button ktp-contract-form__actions">';
			$html .= $this->render_action_button(
				array(
					'id'    => 'ktp-contract-save-btn',
					'class' => 'ktp-contract-action-btn--primary update-submit-btn',
					'icon'  => 'cached',
					'label' => __( '保存', 'ktpwp' ),
				)
			);
			$html .= ' ';
			$html .= $this->render_action_button(
				array(
					'id'    => 'ktp-contract-cancel-btn',
					'class' => 'ktp-contract-action-btn--secondary',
					'icon'  => 'disabled_by_default',
					'label' => __( 'キャンセル', 'ktpwp' ),
				)
			);
			$html .= '</div>';

			return $html;
		}

		/**
		 * 定期請求項目ブロック
		 *
		 * @return string
		 */
		private function render_recurring_items_block() {
			$html  = '<div class="ktp-contract-recurring-items" id="ktp-contract-recurring-items">';
			$html .= '<h5 class="ktp-contract-recurring-items__title">' . esc_html__( '定期請求項目', 'ktpwp' ) . '</h5>';
			$html .= '<p class="description">' . esc_html__( '家賃＋共益費など、毎回請求する明細を登録します。空欄の行は無視されます。', 'ktpwp' ) . '</p>';
			$html .= '<table class="ktp-contract-recurring-items__table" id="ktp-contract-recurring-items-table">';
			$html .= '<thead><tr>';
			$html .= '<th>' . esc_html__( '項目名', 'ktpwp' ) . '</th>';
			$html .= '<th>' . esc_html__( '金額', 'ktpwp' ) . '</th>';
			$html .= '<th>' . esc_html__( '税率(%)', 'ktpwp' ) . '</th>';
			$html .= '<th></th>';
			$html .= '</tr></thead>';
			$html .= '<tbody id="ktp-contract-recurring-items-body"></tbody>';
			$html .= '</table>';
			$html .= $this->render_action_button(
				array(
					'id'    => 'ktp-contract-add-recurring-row',
					'class' => 'ktp-contract-action-btn--primary ktp-contract-action-btn--sm',
					'icon'  => 'add',
					'label' => __( '行を追加', 'ktpwp' ),
				)
			);
			$html .= '<p class="ktp-contract-recurring-items__locked" id="ktp-contract-recurring-items-locked" style="display:none;">' . esc_html__( '初回請求済みのため、定期請求項目は変更できません。', 'ktpwp' ) . '</p>';
			$html .= '</div>';

			return $html;
		}

		/**
		 * 初回請求の追加費用ブロック
		 *
		 * @param array<int, string> $fee_presets プリセット名目。
		 * @return string
		 */
		private function render_initial_fees_block( $fee_presets ) {
			$html  = '<div class="ktp-contract-initial-fees" id="ktp-contract-initial-fees">';
			$html .= '<h5 class="ktp-contract-initial-fees__title">' . esc_html__( '初回請求の追加費用', 'ktpwp' ) . '</h5>';
			$html .= '<p class="description">' . esc_html__( '初回請求書にのみ含めます（保証金・初期設定費用など）。2回目以降は自動で除外されます。', 'ktpwp' ) . '</p>';

			$html .= '<div class="ktp-contract-initial-fees__presets">';
			$html .= '<label for="ktp-contract-fee-preset">' . esc_html__( '名目プリセット', 'ktpwp' ) . '</label> ';
			$html .= '<select id="ktp-contract-fee-preset">';
			$html .= '<option value="">' . esc_html__( '選択して行を追加', 'ktpwp' ) . '</option>';
			foreach ( $fee_presets as $preset ) {
				$html .= '<option value="' . esc_attr( $preset ) . '">' . esc_html( $preset ) . '</option>';
			}
			$html .= '<option value="__custom__">' . esc_html__( 'その他（自由入力）', 'ktpwp' ) . '</option>';
			$html .= '</select>';
			$html .= '</div>';

			$html .= '<table class="ktp-contract-initial-fees__table" id="ktp-contract-initial-fees-table">';
			$html .= '<thead><tr>';
			$html .= '<th>' . esc_html__( '名目', 'ktpwp' ) . '</th>';
			$html .= '<th>' . esc_html__( '金額', 'ktpwp' ) . '</th>';
			$html .= '<th>' . esc_html__( '税率(%)', 'ktpwp' ) . '</th>';
			$html .= '<th></th>';
			$html .= '</tr></thead>';
			$html .= '<tbody id="ktp-contract-initial-fees-body"></tbody>';
			$html .= '</table>';

			$html .= $this->render_action_button(
				array(
					'id'    => 'ktp-contract-add-fee-row',
					'class' => 'ktp-contract-action-btn--primary ktp-contract-action-btn--sm',
					'icon'  => 'add',
					'label' => __( '行を追加', 'ktpwp' ),
				)
			);
			$html .= '<p class="ktp-contract-initial-fees__locked" id="ktp-contract-initial-fees-locked" style="display:none;">' . esc_html__( '初回請求済みのため、追加費用は変更できません。', 'ktpwp' ) . '</p>';
			$html .= '</div>';

			return $html;
		}

		/**
		 * 請求日ラベル
		 *
		 * @param int $billing_day 請求日（99=末日）。
		 * @return string
		 */
		private function format_billing_day_label( $billing_day ) {
			if ( 99 === $billing_day ) {
				return __( '末日', 'ktpwp' );
			}

			/* translators: %d: day of month */
			return sprintf( __( '毎月%d日', 'ktpwp' ), $billing_day );
		}

		/**
		 * 状態ラベル
		 *
		 * @return array<string, string>
		 */
		private function get_status_labels() {
			return array(
				'active'    => __( '有効', 'ktpwp' ),
				'paused'    => __( '一時停止', 'ktpwp' ),
				'cancelled' => __( '解約', 'ktpwp' ),
			);
		}

		/**
		 * 請求日選択肢
		 *
		 * @return array<int, string>
		 */
		private function get_billing_day_options() {
			$options = array();
			for ( $day = 1; $day <= 28; $day++ ) {
				/* translators: %d: day of month */
				$options[ $day ] = sprintf( __( '毎月%d日', 'ktpwp' ), $day );
			}
			$options[99] = __( '末日', 'ktpwp' );

			return $options;
		}

		/**
		 * 一覧の Stripe 列
		 *
		 * @param object $contract 契約行。
		 * @return string
		 */
		private function render_list_stripe_cell( $contract ) {
			if ( ! class_exists( 'KTPWP_Stripe_Subscription' ) ) {
				return '—';
			}

			$stripe = KTPWP_Stripe_Subscription::get_instance();
			if ( ! $stripe->contract_applies_to_stripe_subscription( $contract ) ) {
				return '<span class="ktp-contract-stripe-cell ktp-contract-stripe-cell--na">—</span>';
			}

			$status = $stripe->get_subscription_status_for_contract( (int) $contract->id );
			if ( ! is_array( $status ) || empty( $status['applicable'] ) ) {
				return '<span class="ktp-contract-stripe-cell ktp-contract-stripe-cell--na">—</span>';
			}

			$label = isset( $status['status_label'] ) ? (string) $status['status_label'] : '';
			$code  = isset( $status['status'] ) ? (string) $status['status'] : '';

			$html  = '<span class="ktp-contract-stripe-status ktp-contract-stripe-status--' . esc_attr( $code !== '' ? $code : 'unknown' ) . '">';
			$html .= esc_html( $label !== '' ? $label : '—' );
			$html .= '</span>';

			if ( ! empty( $status['next_billing_date'] ) ) {
				$html .= '<br><span class="ktp-contract-stripe-cell__next">';
				/* translators: %s: date */
				$html .= esc_html( sprintf( __( '次回: %s', 'ktpwp' ), $status['next_billing_date'] ) );
				$html .= '</span>';
			}

			return $html;
		}

		/**
		 * 編集フォーム内 Stripe Subscription ブロック
		 *
		 * @return string
		 */
		private function render_stripe_subscription_block() {
			$html  = '<div class="ktp-contract-stripe-block" id="ktp-contract-stripe-block" style="display:none;">';
			$html .= '<h5 class="ktp-contract-stripe-block__title">' . esc_html__( 'Stripe サブスクリプション', 'ktpwp' ) . '</h5>';
			$html .= '<div class="ktp-contract-stripe-block__body" id="ktp-contract-stripe-status">';
			$html .= '<p class="ktp-contract-stripe-block__loading">' . esc_html__( '読み込み中…', 'ktpwp' ) . '</p>';
			$html .= '</div>';
			$html .= '<div class="ktp-contract-stripe-block__setup" id="ktp-contract-stripe-setup" style="display:none;">';
			$html .= $this->render_action_button(
				array(
					'id'    => 'ktp-contract-setup-link-btn',
					'class' => 'ktp-contract-action-btn--primary ktp-contract-action-btn--sm',
					'icon'  => 'link',
					'label' => __( 'カード登録リンクを発行', 'ktpwp' ),
				)
			);
			$html .= '<div class="ktp-contract-stripe-block__url-wrap" id="ktp-contract-setup-url-wrap" style="display:none;">';
			$html .= '<label for="ktp-contract-setup-url">' . esc_html__( 'カード登録 URL', 'ktpwp' ) . '</label>';
			$html .= '<div class="ktp-contract-stripe-block__url-row">';
			$html .= '<input type="text" id="ktp-contract-setup-url" readonly>';
			$html .= $this->render_action_button(
				array(
					'id'    => 'ktp-contract-setup-url-copy-btn',
					'class' => 'ktp-contract-action-btn--secondary ktp-contract-action-btn--sm',
					'icon'  => 'content_copy',
					'label' => __( 'コピー', 'ktpwp' ),
				)
			);
			$html .= '</div>';
			$html .= '<p class="description">' . esc_html__( '顧客に送付してカード登録後、自動でサブスクリプションが開始されます。', 'ktpwp' ) . '</p>';
			$html .= '</div>';
			$html .= '</div>';
			$html .= '</div>';

			return $html;
		}

		/**
		 * 定期契約セクション用のアクションボタン HTML
		 *
		 * @param array<string, mixed> $args ボタン設定。
		 * @return string
		 */
		private function render_action_button( $args ) {
			$type        = isset( $args['type'] ) ? (string) $args['type'] : 'button';
			$id          = isset( $args['id'] ) ? (string) $args['id'] : '';
			$class       = isset( $args['class'] ) ? (string) $args['class'] : '';
			$icon        = isset( $args['icon'] ) ? (string) $args['icon'] : '';
			$label       = isset( $args['label'] ) ? (string) $args['label'] : '';
			$title       = isset( $args['title'] ) ? (string) $args['title'] : $label;
			$show_label  = array_key_exists( 'show_label', $args ) ? (bool) $args['show_label'] : true;
			$extra_attrs = isset( $args['extra_attrs'] ) ? (string) $args['extra_attrs'] : '';

			$classes = trim( 'ktp-contract-action-btn ' . $class );

			$html = '<button type="' . esc_attr( $type ) . '" class="' . esc_attr( $classes ) . '"';
			if ( $id !== '' ) {
				$html .= ' id="' . esc_attr( $id ) . '"';
			}
			if ( $title !== '' ) {
				$html .= ' title="' . esc_attr( $title ) . '"';
			}
			$html .= $extra_attrs . '>';

			if ( $icon !== '' ) {
				if ( class_exists( 'KTPWP_SVG_Icons' ) ) {
					$html .= KTPWP_SVG_Icons::get_icon(
						$icon,
						array(
							'class' => 'ktp-svg-icon',
							'style' => 'font-size:18px;line-height:1;',
						)
					);
				} else {
					$html .= '<span class="material-symbols-outlined" aria-hidden="true">' . esc_html( $icon ) . '</span>';
				}
			}
			if ( $show_label && $label !== '' ) {
				$html .= '<span class="btn-label">' . esc_html( $label ) . '</span>';
			}
			$html .= '</button>';

			return $html;
		}
	}
}
