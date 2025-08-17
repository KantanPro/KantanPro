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
				'sales' => '売上レポート',
				'client' => '顧客別レポート',
				'service' => 'サービス別レポート',
				'supplier' => '協力会社レポート',
				'tax_return' => '確定申告'
			);

			$report_buttons = '';
			foreach ( $reports as $key => $label ) {
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
				$report_buttons .= esc_html( $label );
				$report_buttons .= '</a>';
			}

			// プリントボタンを追加（協力会社タブと同じスタイル）
			$print_button = '<button onclick="printContent()" title="印刷する" style="padding: 6px 10px; font-size: 12px;">
				<span class="material-symbols-outlined" aria-label="印刷">print</span>
			</button>';

			return '<div class="controller" style="display:flex;align-items:center;justify-content:space-between;gap:4px;margin-bottom:24px;">
				<div style="display:flex;align-items:center;gap:4px;">
					' . $report_buttons . '
				</div>
				<div>
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
