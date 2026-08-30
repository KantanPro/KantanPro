<?php
/**
 * 今月の定期請求 UI
 *
 * @package KTPWP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'KTPWP_Contract_Billing_UI' ) ) {

	/**
	 * 仕事リスト内の定期請求ダッシュボード描画。
	 */
	class KTPWP_Contract_Billing_UI {

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

		private function __construct() {}

		/**
		 * 今月の定期請求パネル
		 *
		 * @param string $tab_name タブ名。
		 * @param string|null $period YYYY-MM。
		 * @return string
		 */
		public function render_monthly_panel( $tab_name, $period = null ) {
			if ( ! class_exists( 'KTPWP_Contract_Billing' ) || ! class_exists( 'KTPWP_Contract_DB' ) ) {
				return '';
			}

			$billing = KTPWP_Contract_Billing::get_instance();
			if ( ! KTPWP_Contract_DB::get_instance()->tables_exist() ) {
				return '<div class="ktp-contract-billing-panel"><p class="ktp-contract-billing-panel__hint">' . esc_html__( '定期契約テーブルが見つかりません。プラグインを更新してください。', 'kantanpro' ) . '</p></div>';
			}

			$period = $period ? sanitize_text_field( $period ) : $billing->get_billing_period();
			$rows   = $billing->get_monthly_rows( $period );

			$parts        = explode( '-', $period );
			$period_title = (int) $parts[0] . '年' . (int) $parts[1] . '月分';

			$pending_count = 0;
			foreach ( $rows as $row ) {
				if ( 'pending' === $row['status'] ) {
					++$pending_count;
				}
			}

			$reminder_stats = class_exists( 'KTPWP_Contract_Reminder_Mail' )
				? KTPWP_Contract_Reminder_Mail::get_instance()->get_reminder_stats( $period )
				: array(
					'sent'    => 0,
					'pending' => 0,
				);

			$html  = '<div class="ktp-contract-billing-panel" id="ktp-contract-billing-panel" data-period="' . esc_attr( $period ) . '">';
			$html .= '<div class="ktp-contract-billing-panel__header">';
			$html .= '<div class="ktp-contract-billing-panel__title-wrap">';
			$html .= '<h3 class="ktp-contract-billing-panel__title">' . esc_html( $period_title ) . ' ' . esc_html__( '定期請求', 'kantanpro' ) . '</h3>';
			$html .= '<div class="ktp-contract-billing-panel__meta">';
			$html .= '<div class="ktp-contract-billing-panel__meta-line ktp-contract-billing-panel__summary">';
			$html .= esc_html(
				sprintf(
					/* translators: 1: total contracts, 2: pending count */
					__( '対象 %1$d件（未紐付け %2$d件）', 'kantanpro' ),
					count( $rows ),
					$pending_count
				)
			);
			$html .= '</div>';
			$html .= '<div class="ktp-contract-billing-panel__meta-line ktp-contract-billing-panel__summary">';
			$html .= esc_html(
				sprintf(
					/* translators: 1: sent count, 2: eligible count */
					__( '予告メール: 送信済 %1$d / 対象 %2$d', 'kantanpro' ),
					(int) $reminder_stats['sent'],
					(int) $reminder_stats['sent'] + (int) $reminder_stats['pending']
				)
			);
			$html .= '</div>';
			$html .= '<div class="ktp-contract-billing-panel__meta-line ktp-contract-billing-panel__hint">' . esc_html__( 'サイクルは請求タイミング、支払期日は契約の「入金期日」設定に従って表示します。', 'kantanpro' ) . '</div>';
			if ( empty( $rows ) ) {
				$html .= '<div class="ktp-contract-billing-panel__meta-line ktp-contract-billing-panel__empty">' . esc_html__( '今月請求対象の定期契約はありません。', 'kantanpro' ) . '</div>';
			}
			$html .= '</div>';
			$html .= '</div>';
			$html .= '<div class="ktp-contract-billing-panel__actions">';
			if ( (int) $reminder_stats['pending'] > 0 ) {
				$html .= '<button type="button" class="ktp-contract-action-btn ktp-contract-action-btn--secondary" id="ktp-contract-billing-send-reminders" data-period="' . esc_attr( $period ) . '">';
				$html .= esc_html__( '未送信の予告メールを送信', 'kantanpro' );
				$html .= '</button>';
			}
			if ( $pending_count > 0 ) {
				$html .= '<button type="button" class="ktp-contract-action-btn ktp-contract-action-btn--primary" id="ktp-contract-billing-generate-all" data-period="' . esc_attr( $period ) . '">';
				$html .= esc_html__( '未紐付けを一括で紐付け', 'kantanpro' );
				$html .= '</button>';
			}
			$html .= '</div>';
			$html .= '</div>';

			if ( ! empty( $rows ) ) {
				$html .= '<div class="ktp-contract-billing-table-wrap">';
				$html .= '<table class="ktp-contract-billing-table">';
				$html .= '<thead><tr>';
				$html .= '<th>' . esc_html__( '顧客', 'kantanpro' ) . '</th>';
				$html .= '<th>' . esc_html__( '契約名', 'kantanpro' ) . '</th>';
				$html .= '<th>' . esc_html__( 'サイクル', 'kantanpro' ) . '</th>';
				$html .= '<th>' . esc_html__( '支払期日', 'kantanpro' ) . '</th>';
				$html .= '<th>' . esc_html__( '金額', 'kantanpro' ) . '</th>';
				$html .= '<th>' . esc_html__( '状態', 'kantanpro' ) . '</th>';
				$html .= '<th>' . esc_html__( '予告', 'kantanpro' ) . '</th>';
				$html .= '<th></th>';
				$html .= '</tr></thead><tbody>';

				foreach ( $rows as $row ) {
					$html .= $this->render_monthly_row( $row, $tab_name, $period );
				}

				$html .= '</tbody></table>';
				$html .= '</div>';
			}

			$html .= '<div class="ktp-contract-billing-panel__message" id="ktp-contract-billing-message" style="display:none;"></div>';
			$html .= '</div>';

			return $html;
		}

		/**
		 * 進捗リスト / 定期請求リストの切り替え
		 *
		 * @param string $tab_name              タブ名。
		 * @param bool   $recurring_billing_view 定期請求ビュー表示中か。
		 * @return string
		 */
		public function render_list_view_switcher( $tab_name, $recurring_billing_view ) {
			if ( ! class_exists( 'KTPWP_Contract_DB' ) || ! KTPWP_Contract_DB::get_instance()->tables_exist() ) {
				return '';
			}

			$progress_url = $this->get_list_back_url( $tab_name );
			$billing_url  = add_query_arg(
				array(
					'tab_name'          => $tab_name,
					'recurring_billing' => '1',
				),
				remove_query_arg( array( 'page_start', 'page_stage', 'flg', 'list_type', 'contract_id' ) )
			);

			$html  = '<div class="ktp-list-view-switcher">';
			$html .= '<a href="' . esc_url( $progress_url ) . '" class="ktp-list-view-switcher__btn' . ( ! $recurring_billing_view ? ' is-active' : '' ) . '">';
			$html .= esc_html__( '進捗リスト', 'kantanpro' );
			$html .= '</a>';
			$html .= '<a href="' . esc_url( $billing_url ) . '" class="ktp-list-view-switcher__btn' . ( $recurring_billing_view ? ' is-active' : '' ) . '">';
			$html .= esc_html__( '定期請求リスト', 'kantanpro' );
			$html .= '</a>';
			$html .= '</div>';

			return $html;
		}

		/**
		 * 進捗リスト URL
		 *
		 * @param string $tab_name タブ名。
		 * @return string
		 */
		private function get_list_back_url( $tab_name ) {
			return add_query_arg(
				array(
					'tab_name' => $tab_name,
					'progress' => isset( $_GET['progress'] ) ? absint( $_GET['progress'] ) : 4,
				),
				remove_query_arg( array( 'recurring_billing', 'billing_period', 'contract_id' ) )
			);
		}

		/**
		 * 定期のみフィルタリンク
		 *
		 * @param string $tab_name タブ名。
		 * @param bool   $active   定期フィルタが有効か。
		 * @return string
		 */
		public function render_list_type_filter( $tab_name, $active ) {
			global $wpdb;

			$order_table = $wpdb->prefix . 'ktp_order';
			$columns     = $wpdb->get_col( "SHOW COLUMNS FROM `{$order_table}`" );
			if ( ! is_array( $columns ) || ! in_array( 'contract_id', $columns, true ) ) {
				return '';
			}

			$base_args = array(
				'tab_name' => $tab_name,
				'progress' => isset( $_GET['progress'] ) ? absint( $_GET['progress'] ) : 1,
			);
			if ( isset( $_GET['list_search'] ) && (string) $_GET['list_search'] !== '' ) {
				$base_args['list_search'] = sanitize_text_field( wp_unslash( $_GET['list_search'] ) );
			}

			$all_url = add_query_arg( $base_args, remove_query_arg( 'list_type' ) );
			$rec_url = add_query_arg( array_merge( $base_args, array( 'list_type' => 'recurring' ) ) );

			$html  = '<div class="ktp-list-type-filter">';
			$html .= '<span class="ktp-list-type-filter__label">' . esc_html__( '表示:', 'kantanpro' ) . '</span>';
			$html .= '<a href="' . esc_url( $all_url ) . '" class="ktp-list-type-filter__btn' . ( ! $active ? ' is-active' : '' ) . '">' . esc_html__( 'すべて', 'kantanpro' ) . '</a>';
			$html .= '<a href="' . esc_url( $rec_url ) . '" class="ktp-list-type-filter__btn' . ( $active ? ' is-active' : '' ) . '">' . esc_html__( '定期のみ', 'kantanpro' ) . '</a>';
			$html .= '</div>';

			return $html;
		}

		/**
		 * 1行分の HTML
		 *
		 * @param array<string, mixed> $row 行データ。
		 * @param string               $tab_name タブ名。
		 * @param string               $period YYYY-MM。
		 * @return string
		 */
		private function render_monthly_row( $row, $tab_name, $period ) {
			$cycle_label = class_exists( 'KTPWP_Contract_Billing_Cycle' )
				? KTPWP_Contract_Billing_Cycle::get_label( $row['billing_cycle'] )
				: (string) $row['billing_cycle'];

			$status_label = $this->get_status_label( $row['status'] );
			$status_class = 'ktp-contract-billing-status--' . sanitize_html_class( $row['status'] );

			$billing_date_label = isset( $row['billing_date_label'] ) ? (string) $row['billing_date_label'] : '';
			$payment_timing     = isset( $row['payment_timing_label'] ) ? (string) $row['payment_timing_label'] : '—';
			$cycle_timing_line  = $this->format_cycle_timing_line(
				$cycle_label,
				isset( $row['billing_day'] ) ? (int) $row['billing_day'] : 0,
				$billing_date_label
			);

			$html  = '<tr data-contract-id="' . esc_attr( (string) $row['contract_id'] ) . '">';
			$html .= '<td>' . esc_html( (string) $row['client_name'] ) . '</td>';
			$html .= '<td>' . esc_html( (string) $row['contract_name'] ) . '</td>';
			$html .= '<td class="ktp-contract-billing-table__cycle">' . esc_html( $cycle_timing_line ) . '</td>';
			$html .= '<td>' . esc_html( $payment_timing ) . '</td>';
			$html .= '<td>' . esc_html( class_exists( 'KTPWP_Settings' ) ? KTPWP_Settings::format_money( (float) $row['amount'] ) : number_format( (float) $row['amount'] ) ) . '</td>';
			$html .= '<td><span class="ktp-contract-billing-status ' . esc_attr( $status_class ) . '">' . esc_html( $status_label ) . '</span></td>';
			$html .= '<td>' . $this->render_reminder_status_cell( $row, $period ) . '</td>';
			$html .= '<td class="ktp-contract-billing-table__actions">';

			$contract_url = $this->get_contract_open_url( (int) $row['client_id'], (int) $row['contract_id'] );
			$html        .= '<a href="' . esc_url( $contract_url ) . '" class="ktp-contract-action-btn ktp-contract-action-btn--outline">' . esc_html__( '契約を開く', 'kantanpro' ) . '</a>';

			if ( 'pending' === $row['status'] ) {
				$html .= '<button type="button" class="ktp-contract-action-btn ktp-contract-action-btn--primary ktp-contract-billing-generate-one" data-contract-id="' . esc_attr( (string) $row['contract_id'] ) . '" data-period="' . esc_attr( $period ) . '">';
				$html .= esc_html__( '案件を紐付け', 'kantanpro' );
				$html .= '</button>';
			} elseif ( (int) $row['order_id'] > 0 ) {
				$order_url = add_query_arg(
					array(
						'tab_name' => 'order',
						'order_id' => (int) $row['order_id'],
					)
				);
				$html .= '<a href="' . esc_url( $order_url ) . '" class="ktp-contract-action-btn ktp-contract-action-btn--outline">' . esc_html__( '案件を開く', 'kantanpro' ) . '</a>';
			}

			$html .= '</td></tr>';

			return $html;
		}

		/**
		 * サイクル＋請求日を1行表示
		 *
		 * @param string $cycle_label        サイクルラベル。
		 * @param int    $billing_day        請求日（99=月末）。
		 * @param string $billing_date_label 今月の日付（n/j）。
		 * @return string
		 */
		private function format_cycle_timing_line( $cycle_label, $billing_day, $billing_date_label ) {
			$billing_day = (int) $billing_day;
			if ( 99 === $billing_day ) {
				$day_part = __( '月末', 'kantanpro' );
			} elseif ( $billing_day > 0 ) {
				$day_part = $billing_day . '日';
			} else {
				$day_part = __( '未設定', 'kantanpro' );
			}

			$line = trim( $cycle_label . '・' . $day_part );
			if ( $billing_date_label !== '' ) {
				$line .= '（' . $billing_date_label . '）';
			}

			return $line;
		}

		/**
		 * 顧客タブで契約編集を開く URL
		 *
		 * @param int $client_id   顧客 ID。
		 * @param int $contract_id 契約 ID。
		 * @return string
		 */
		private function get_contract_open_url( $client_id, $contract_id ) {
			$base = class_exists( 'KTPWP_Main' ) ? KTPWP_Main::get_current_page_base_url() : home_url( '/' );

			return add_query_arg(
				array(
					'tab_name'    => 'client',
					'data_id'     => absint( $client_id ),
					'contract_id' => absint( $contract_id ),
				),
				$base
			);
		}

		/**
		 * 予告メール列
		 *
		 * @param array<string, mixed> $row    行データ。
		 * @param string               $period YYYY-MM。
		 * @return string
		 */
		private function render_reminder_status_cell( $row, $period ) {
			if ( empty( $row['reminder_eligible'] ) ) {
				return '<span class="ktp-contract-reminder-status ktp-contract-reminder-status--off">' . esc_html__( '停止', 'kantanpro' ) . '</span>';
			}

			if ( ! empty( $row['reminder_sent'] ) ) {
				return '<span class="ktp-contract-reminder-status ktp-contract-reminder-status--sent">' . esc_html__( '予告済', 'kantanpro' ) . '</span>';
			}

			$html  = '<span class="ktp-contract-reminder-status ktp-contract-reminder-status--pending">' . esc_html__( '未送信', 'kantanpro' ) . '</span>';
			$html .= ' <button type="button" class="ktp-contract-action-btn ktp-contract-action-btn--outline ktp-contract-billing-send-reminder-one" data-contract-id="' . esc_attr( (string) $row['contract_id'] ) . '" data-period="' . esc_attr( $period ) . '">';
			$html .= esc_html__( '送信', 'kantanpro' );
			$html .= '</button>';

			return $html;
		}

		/**
		 * 状態ラベル
		 *
		 * @param string $status 内部状態。
		 * @return string
		 */
		private function get_status_label( $status ) {
			$labels = array(
				'pending'   => __( '未紐付け', 'kantanpro' ),
				'generated' => __( '紐付け済', 'kantanpro' ),
				'invoiced'  => __( '請求済', 'kantanpro' ),
				'paid'      => __( '入金済', 'kantanpro' ),
				'rejected'  => __( 'ボツ', 'kantanpro' ),
			);

			return $labels[ $status ] ?? $status;
		}
	}
}
