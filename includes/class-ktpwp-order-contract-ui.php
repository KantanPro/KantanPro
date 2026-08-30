<?php
/**
 * 案件詳細から定期契約への変換 UI
 *
 * @package KTPWP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'KTPWP_Order_Contract_UI' ) ) {

	/**
	 * 受注書画面に「定期契約を作成」ボタンと変換フォームを描画する。
	 */
	class KTPWP_Order_Contract_UI {

		/** @var self|null */
		private static $instance = null;

		/** @var KTPWP_Order_Contract_Conversion */
		private $conversion;

		/** @var KTPWP_Contract_DB */
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
			$this->conversion  = KTPWP_Order_Contract_Conversion::get_instance();
			$this->contract_db = KTPWP_Contract_DB::get_instance();
		}

		/**
		 * 案件が定期契約に紐付いているか（KantanBiz Order::isRecurringContractOrder 相当）。
		 *
		 * @param object $order_data 受注書。
		 * @return bool
		 */
		public function is_recurring_contract_order( $order_data ) {
			return is_object( $order_data )
				&& isset( $order_data->contract_id )
				&& (int) $order_data->contract_id > 0;
		}

		/**
		 * 変換ボタン・パネルを表示できるか。
		 *
		 * @param object $order_data 受注書。
		 * @return bool
		 */
		public function can_show( $order_data ) {
			if ( ! is_object( $order_data ) || empty( $order_data->id ) ) {
				return false;
			}

			if ( ! $this->contract_db->tables_exist() ) {
				return false;
			}

			if ( $this->is_recurring_contract_order( $order_data ) ) {
				return false;
			}

			if ( empty( $order_data->client_id ) ) {
				return false;
			}

			return $this->conversion->can_convert( (int) $order_data->id );
		}

		/**
		 * 紐付け済み案件の「定期契約を見る」URL。
		 *
		 * @param object $order_data 受注書。
		 * @return string
		 */
		private function get_view_contract_url( $order_data ) {
			$base_url = class_exists( 'KTPWP_Main' )
				? KTPWP_Main::get_current_page_base_url()
				: home_url( '/' );

			$args = array(
				'tab_name' => 'client',
				'data_id'  => absint( $order_data->client_id ),
			);

			if ( isset( $order_data->contract_id ) && (int) $order_data->contract_id > 0 ) {
				$args['contract_id'] = (int) $order_data->contract_id;
			}

			return add_query_arg( $args, $base_url );
		}

		/**
		 * タイトル行のアクションボタン HTML。
		 *
		 * @param object $order_data 受注書。
		 * @return string
		 */
		public function render_action_button( $order_data ) {
			if ( ! is_object( $order_data ) || empty( $order_data->id ) || ! $this->contract_db->tables_exist() ) {
				return '';
			}

			if ( $this->can_show( $order_data ) ) {
				$html  = '<button type="button" id="ktp-order-contract-btn" class="ktp-order-contract-btn"';
				$html .= ' data-order-id="' . esc_attr( (string) $order_data->id ) . '"';
				$html .= ' title="' . esc_attr__( '定期契約を作成', 'kantanpro' ) . '"';
				$html .= ' aria-label="' . esc_attr__( '定期契約を作成', 'kantanpro' ) . '">';
				$html .= '<span class="material-symbols-outlined" aria-hidden="true">contract</span>';
				$html .= esc_html__( '定期契約を作成', 'kantanpro' );
				$html .= '</button>';

				return $html;
			}

			if ( $this->is_recurring_contract_order( $order_data ) && ! empty( $order_data->client_id ) ) {
				$url = $this->get_view_contract_url( $order_data );

				$html  = '<button type="button" class="ktp-order-contract-btn ktp-order-contract-btn--view"';
				$html .= ' data-view-url="' . esc_attr( esc_url( $url ) ) . '"';
				$html .= ' title="' . esc_attr__( '定期契約を見る', 'kantanpro' ) . '"';
				$html .= ' aria-label="' . esc_attr__( '定期契約を見る', 'kantanpro' ) . '">';
				$html .= '<span class="material-symbols-outlined" aria-hidden="true">contract</span>';
				$html .= esc_html__( '定期契約を見る', 'kantanpro' );
				$html .= '</button>';

				return $html;
			}

			return '';
		}

		/**
		 * 変換パネル HTML（初期は非表示）。
		 *
		 * @param object $order_data 受注書。
		 * @return string
		 */
		public function render_panel( $order_data ) {
			if ( ! $this->can_show( $order_data ) ) {
				return '';
			}

			$order_id           = (int) $order_data->id;
			$client_id          = (int) $order_data->client_id;
			$recurring_services = $this->contract_db->get_recurring_services();
			$fee_presets        = KTPWP_Contract_DB::get_initial_fee_presets();
			$status_labels      = $this->get_status_labels();
			$billing_day_opts   = $this->get_billing_day_options();
			$billing_period     = class_exists( 'KTPWP_Contract_Billing' )
				? KTPWP_Contract_Billing::get_instance()->get_billing_period()
				: wp_date( 'Y-m' );
			$default_billing_day = min( 28, max( 1, (int) wp_date( 'j' ) ) );

			$html  = '<div id="ktp-order-contract-panel" class="ktp-order-contract-panel" style="display:none;"';
			$html .= ' data-order-id="' . esc_attr( (string) $order_id ) . '"';
			$html .= ' data-client-id="' . esc_attr( (string) $client_id ) . '">';
			$html .= '<div class="ktp-order-contract-panel__inner ktp-contract-section">';
			$html .= '<div class="ktp-order-contract-panel__header">';
			$html .= '<h4 class="ktp-contract-section__title">' . esc_html__( '案件から定期契約を作成', 'kantanpro' ) . '</h4>';
			$html .= '<button type="button" id="ktp-order-contract-close" class="ktp-contract-action-btn ktp-contract-action-btn--secondary ktp-contract-action-btn--sm" title="' . esc_attr__( '閉じる', 'kantanpro' ) . '">';
			$html .= $this->render_icon( 'close' );
			$html .= '</button>';
			$html .= '</div>';

			$html .= '<div id="ktp-order-contract-summary" class="ktp-order-contract-summary" style="display:none;"></div>';
			$html .= '<div id="ktp-order-contract-message" class="ktp-order-contract-message" style="display:none;"></div>';

			$html .= '<input type="hidden" id="ktp-oc-order-id" value="' . esc_attr( (string) $order_id ) . '">';
			$html .= '<input type="hidden" id="ktp-oc-client-id" value="' . esc_attr( (string) $client_id ) . '">';

			$html .= '<div class="ktp-contract-form__grid">';
			$html .= '<div class="ktp-contract-form__field">';
			$html .= '<label for="ktp-oc-contract-name">' . esc_html__( '契約名', 'kantanpro' ) . ' <span class="required">*</span></label>';
			$html .= '<input type="text" id="ktp-oc-contract-name" maxlength="255">';
			$html .= '</div>';

			$html .= '<div class="ktp-contract-form__field">';
			$html .= '<label for="ktp-oc-service-id">' . esc_html__( 'サービス', 'kantanpro' ) . ' <span class="required">*</span></label>';
			$html .= '<select id="ktp-oc-service-id">';
			$html .= '<option value="">' . esc_html__( '選択してください', 'kantanpro' ) . '</option>';
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
			$html .= '<label for="ktp-oc-amount">' . esc_html__( '請求金額', 'kantanpro' ) . '</label>';
			$html .= '<input type="number" id="ktp-oc-amount" min="0" step="0.01" value="0">';
			$html .= '</div>';

			$html .= '<div class="ktp-contract-form__field">';
			$html .= '<label for="ktp-oc-billing-cycle">' . esc_html__( '請求サイクル', 'kantanpro' ) . '</label>';
			$html .= '<select id="ktp-oc-billing-cycle">';
			if ( class_exists( 'KTPWP_Contract_Billing_Cycle' ) ) {
				foreach ( KTPWP_Contract_Billing_Cycle::get_recurring_options() as $value => $label ) {
					$html .= '<option value="' . esc_attr( $value ) . '">' . esc_html( $label ) . '</option>';
				}
			}
			$html .= '</select>';
			$html .= '</div>';

			$html .= '<div class="ktp-contract-form__field">';
			$html .= '<label for="ktp-oc-billing-day">' . esc_html__( '請求日', 'kantanpro' ) . '</label>';
			$html .= '<select id="ktp-oc-billing-day">';
			foreach ( $billing_day_opts as $day_value => $day_label ) {
				$selected = ( (int) $day_value === $default_billing_day ) ? ' selected' : '';
				$html    .= '<option value="' . esc_attr( (string) $day_value ) . '"' . $selected . '>' . esc_html( $day_label ) . '</option>';
			}
			$html .= '</select>';
			$html .= '</div>';

			$html .= '<div class="ktp-contract-form__field">';
			$html .= '<label for="ktp-oc-payment-due-mode">' . esc_html__( '入金期日', 'kantanpro' ) . '</label>';
			$html .= '<select id="ktp-oc-payment-due-mode">';
			foreach ( KTPWP_Contract_DB::get_payment_due_mode_options() as $value => $label ) {
				$selected = ( 'client' === $value ) ? ' selected' : '';
				$html    .= '<option value="' . esc_attr( $value ) . '"' . $selected . '>' . esc_html( $label ) . '</option>';
			}
			$html .= '</select>';
			$html .= '</div>';

			$html .= '<div class="ktp-contract-form__field">';
			$html .= '<label for="ktp-oc-start-date">' . esc_html__( '契約開始日', 'kantanpro' ) . '</label>';
			$html .= '<input type="date" id="ktp-oc-start-date" value="' . esc_attr( wp_date( 'Y-m-d' ) ) . '">';
			$html .= '</div>';

			$html .= '<div class="ktp-contract-form__field">';
			$html .= '<label for="ktp-oc-end-date">' . esc_html__( '契約終了日', 'kantanpro' ) . '</label>';
			$html .= '<input type="date" id="ktp-oc-end-date">';
			$html .= '</div>';

			$html .= '<div class="ktp-contract-form__field">';
			$html .= '<label for="ktp-oc-status">' . esc_html__( '状態', 'kantanpro' ) . '</label>';
			$html .= '<select id="ktp-oc-status">';
			foreach ( $status_labels as $value => $label ) {
				$selected = ( 'paused' === $value ) ? ' selected' : '';
				$html    .= '<option value="' . esc_attr( $value ) . '"' . $selected . '>' . esc_html( $label ) . '</option>';
			}
			$html .= '</select>';
			$html .= '</div>';

			$html .= '<div class="ktp-contract-form__field ktp-contract-form__field--checkbox">';
			$html .= '<label><input type="checkbox" id="ktp-oc-send-reminder" value="1" checked> ' . esc_html__( '請求予定メールを送る（請求日の3日前）', 'kantanpro' ) . '</label>';
			$html .= '</div>';
			$html .= '</div>';

			$html .= '<div class="ktp-contract-form__field">';
			$html .= '<label for="ktp-oc-memo">' . esc_html__( 'メモ', 'kantanpro' ) . '</label>';
			$html .= '<textarea id="ktp-oc-memo" rows="2"></textarea>';
			$html .= '</div>';

			$html .= $this->render_recurring_items_block();
			$html .= $this->render_initial_fees_block( $fee_presets );

			$html .= '<div class="ktp-order-contract-link-block">';
			$html .= '<h5 class="ktp-order-contract-link-block__title">' . esc_html__( '案件との紐付け', 'kantanpro' ) . '</h5>';
			$html .= '<div class="ktp-contract-form__field ktp-contract-form__field--checkbox ktp-order-contract-link-block__checkbox">';
			$html .= '<label for="ktp-oc-link-order">';
			$html .= '<input type="checkbox" id="ktp-oc-link-order" value="1" checked>';
			$html .= esc_html__( 'この案件を定期請求案件として紐付ける', 'kantanpro' );
			$html .= '</label>';
			$html .= '<p class="ktp-order-contract-link-block__help">' . esc_html__( 'チェックを入れると、この案件が定期請求リストに表示されます。同じ月の請求を二重に作るのを防ぎます。受付中〜受注の案件は「完了」に更新されます。', 'kantanpro' ) . '</p>';
			$html .= '</div>';
			$html .= '<div class="ktp-contract-form__field">';
			$html .= '<label for="ktp-oc-billing-period">' . esc_html__( '紐付ける請求月', 'kantanpro' ) . '</label>';
			$html .= '<input type="month" id="ktp-oc-billing-period" value="' . esc_attr( $billing_period ) . '">';
			$html .= '</div>';
			$html .= '</div>';

			$html .= '<div class="ktp-contract-form__actions">';
			$html .= '<button type="button" id="ktp-oc-submit" class="ktp-contract-action-btn ktp-contract-action-btn--primary update-submit-btn">';
			$html .= $this->render_icon( 'cached' );
			$html .= '<span class="btn-label">' . esc_html__( '定期契約を登録', 'kantanpro' ) . '</span>';
			$html .= '</button>';
			$html .= ' ';
			$html .= '<button type="button" id="ktp-oc-cancel" class="ktp-contract-action-btn ktp-contract-action-btn--secondary">';
			$html .= $this->render_icon( 'disabled_by_default' );
			$html .= '<span class="btn-label">' . esc_html__( 'キャンセル', 'kantanpro' ) . '</span>';
			$html .= '</button>';
			$html .= '</div>';

			$html .= '</div></div>';

			return $html;
		}

		/**
		 * @return array<string, string>
		 */
		private function get_status_labels() {
			return array(
				'active'    => __( '有効', 'kantanpro' ),
				'paused'    => __( '一時停止', 'kantanpro' ),
				'cancelled' => __( '解約', 'kantanpro' ),
			);
		}

		/**
		 * @return array<int, string>
		 */
		private function get_billing_day_options() {
			$options = array();
			for ( $day = 1; $day <= 28; $day++ ) {
				/* translators: %d: day of month */
				$options[ $day ] = sprintf( __( '毎月%d日', 'kantanpro' ), $day );
			}
			$options[99] = __( '末日', 'kantanpro' );

			return $options;
		}

		/**
		 * @return string
		 */
		private function render_recurring_items_block() {
			$html  = '<div class="ktp-contract-recurring-items" id="ktp-oc-recurring-items">';
			$html .= '<h5 class="ktp-contract-recurring-items__title">' . esc_html__( '定期請求項目', 'kantanpro' ) . '</h5>';
			$html .= '<table class="ktp-contract-recurring-items__table">';
			$html .= '<thead><tr>';
			$html .= '<th>' . esc_html__( '項目名', 'kantanpro' ) . '</th>';
			$html .= '<th>' . esc_html__( '金額', 'kantanpro' ) . '</th>';
			$html .= '<th>' . esc_html__( '税率(%)', 'kantanpro' ) . '</th>';
			$html .= '<th></th>';
			$html .= '</tr></thead>';
			$html .= '<tbody id="ktp-oc-recurring-items-body"></tbody>';
			$html .= '</table>';
			$html .= '<button type="button" id="ktp-oc-add-recurring-row" class="ktp-contract-action-btn ktp-contract-action-btn--primary ktp-contract-action-btn--sm">';
			$html .= $this->render_icon( 'add' );
			$html .= '<span class="btn-label">' . esc_html__( '行を追加', 'kantanpro' ) . '</span>';
			$html .= '</button>';
			$html .= '</div>';

			return $html;
		}

		/**
		 * @param array<int, string> $fee_presets プリセット。
		 * @return string
		 */
		private function render_initial_fees_block( $fee_presets ) {
			$html  = '<div class="ktp-contract-initial-fees" id="ktp-oc-initial-fees">';
			$html .= '<h5 class="ktp-contract-initial-fees__title">' . esc_html__( '初回請求の追加費用', 'kantanpro' ) . '</h5>';
			$html .= '<div class="ktp-contract-initial-fees__presets">';
			$html .= '<label for="ktp-oc-fee-preset">' . esc_html__( '名目プリセット', 'kantanpro' ) . '</label> ';
			$html .= '<select id="ktp-oc-fee-preset">';
			$html .= '<option value="">' . esc_html__( '選択して行を追加', 'kantanpro' ) . '</option>';
			foreach ( $fee_presets as $preset ) {
				$html .= '<option value="' . esc_attr( $preset ) . '">' . esc_html( $preset ) . '</option>';
			}
			$html .= '<option value="__custom__">' . esc_html__( 'その他（自由入力）', 'kantanpro' ) . '</option>';
			$html .= '</select>';
			$html .= '</div>';
			$html .= '<table class="ktp-contract-initial-fees__table">';
			$html .= '<thead><tr>';
			$html .= '<th>' . esc_html__( '名目', 'kantanpro' ) . '</th>';
			$html .= '<th>' . esc_html__( '金額', 'kantanpro' ) . '</th>';
			$html .= '<th>' . esc_html__( '税率(%)', 'kantanpro' ) . '</th>';
			$html .= '<th></th>';
			$html .= '</tr></thead>';
			$html .= '<tbody id="ktp-oc-initial-fees-body"></tbody>';
			$html .= '</table>';
			$html .= '<button type="button" id="ktp-oc-add-fee-row" class="ktp-contract-action-btn ktp-contract-action-btn--primary ktp-contract-action-btn--sm">';
			$html .= $this->render_icon( 'add' );
			$html .= '<span class="btn-label">' . esc_html__( '行を追加', 'kantanpro' ) . '</span>';
			$html .= '</button>';
			$html .= '</div>';

			return $html;
		}

		/**
		 * SVG アイコン HTML を返す。
		 *
		 * @param string $icon_name アイコン名。
		 * @return string
		 */
		private function render_icon( $icon_name ) {
			if ( class_exists( 'KTPWP_SVG_Icons' ) ) {
				return KTPWP_SVG_Icons::get_icon(
					$icon_name,
					array(
						'class' => 'ktp-svg-icon',
						'style' => 'font-size:18px;line-height:1;',
					)
				);
			}

			return '<span class="material-symbols-outlined" aria-hidden="true">' . esc_html( $icon_name ) . '</span>';
		}
	}
}
