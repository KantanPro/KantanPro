<?php
/**
 * UI Generator class for KTPWP plugin
 *
 * Handles the generation of UI components like controller and workflow sections.
 *
 * @package KTPWP
 * @subpackage Includes
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'KTPWP_Ui_Generator' ) ) {

	class KTPWP_Ui_Generator {

		/**
		 * Generate controller section
		 *
		 * @since 1.0.0
		 * @return string HTML content for the controller section
		 */
		public function generate_controller() {
			// レポート種類ボタンを生成
			$current_report = isset( $_GET['report_type'] ) ? sanitize_text_field( $_GET['report_type'] ) : 'sales';
			
			$reports = array(
				'sales' => array(
					'label' => '売上レポート',
					'icon' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/></svg>'
				),
				'client' => array(
					'label' => '顧客別レポート',
					'icon' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M16 4c0-1.11.89-2 2-2s2 .89 2 2-.89 2-2 2-2-.89-2-2zm4 18v-6h2.5l-2.54-7.63A1.5 1.5 0 0 0 18.54 7H17c-.8 0-1.54.37-2.01.99l-2.98 3.67a.5.5 0 0 0 .39.84H14v8h6zm-7.5-10.5c.83 0 1.5-.67 1.5-1.5s-.67-1.5-1.5-1.5S11 9.17 11 10s.67 1.5 1.5 1.5zm1.5 1h-3c-1.1 0-2 .9-2 2v7h2v-5h2v5h2v-7c0-1.1-.9-2-2-2z"/></svg>'
				),
				'service' => array(
					'label' => 'サービス別レポート',
					'icon' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>'
				),
				'supplier' => array(
					'label' => '協力会社レポート',
					'icon' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>'
				),
				'tax_return' => array(
					'label' => '確定申告用',
					'icon' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 2 2h12c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>'
				)
			);

			$report_buttons = '';
			foreach ( $reports as $key => $report_data ) {
				$active_style = ( $current_report === $key ) ? 
					'background:#1976d2 !important;color:#fff !important;border-color:#1565c0 !important;' : 
					'background:#fff !important;color:#333 !important;border-color:#ddd !important;';
				
				$url = add_query_arg( array( 'tab_name' => 'report', 'report_type' => $key ) );
				
				$report_buttons .= '<a href="' . esc_url( $url ) . '" style="' . $active_style . 
					'padding:6px 10px !important;' .
					'font-size:12px !important;' .
					'border:1px solid !important;' .
					'border-radius:3px !important;' .
					'text-decoration:none !important;' .
					'display:inline-flex !important;' .
					'align-items:center !important;' .
					'gap:4px !important;' .
					'transition:all 0.2s ease !important;' .
					'margin-right:4px !important;' .
					'cursor:pointer !important;"' .
					' onmouseover="this.style.transform=\'translateY(-1px)\';this.style.boxShadow=\'0 2px 5px rgba(0,0,0,0.15)\';"' .
					' onmouseout="this.style.transform=\'translateY(0)\';this.style.boxShadow=\'none\';">';
				$report_buttons .= '<span class="report-btn-icon" style="display:inline-flex;align-items:center;">' . $report_data['icon'] . '</span>';
				$report_buttons .= '<span class="report-btn-text">' . esc_html( $report_data['label'] ) . '</span>';
				$report_buttons .= '</a>';
			}

			// プリントボタン（現在表示されている内容を印刷ダイアログで表示）
			$print_button = '<button type="button" onclick="typeof ktpReportPrintOpen === \'function\' && ktpReportPrintOpen();" title="印刷する" style="padding: 6px 10px; font-size: 12px;">
				<span class="material-symbols-outlined" aria-label="印刷">print</span>
			</button>';

			return '<div class="controller ktp-report-controller">
				<div class="ktp-report-controller__main">
					' . $report_buttons . '
				</div>
				<div class="ktp-report-controller__actions">
					' . $print_button . '
				</div>
			</div>';
		}

		/**
		 * Generate workflow section
		 *
		 * @since 1.0.0
		 * @return string HTML content for the workflow section
		 */
		public function generate_workflow() {
			return '<div class="workflow"></div>';
		}
	}

}
