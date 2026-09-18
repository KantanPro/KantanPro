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

		// KTPWP-WPORG-STRIP report BEGIN
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
					'label' => __( '売上レポート', 'kantanpro' ),
					'icon' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/></svg>'
				),
				'client' => array(
					'label' => __( '顧客別レポート', 'kantanpro' ),
					'icon' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M16 4c0-1.11.89-2 2-2s2 .89 2 2-.89 2-2 2-2-.89-2-2zm4 18v-6h2.5l-2.54-7.63A1.5 1.5 0 0 0 18.54 7H17c-.8 0-1.54.37-2.01.99l-2.98 3.67a.5.5 0 0 0 .39.84H14v8h6zm-7.5-10.5c.83 0 1.5-.67 1.5-1.5s-.67-1.5-1.5-1.5S11 9.17 11 10s.67 1.5 1.5 1.5zm1.5 1h-3c-1.1 0-2 .9-2 2v7h2v-5h2v5h2v-7c0-1.1-.9-2-2-2z"/></svg>'
				),
				'service' => array(
					'label' => __( 'サービス別レポート', 'kantanpro' ),
					'icon' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>'
				),
				'supplier' => array(
					'label' => __( '協力会社レポート', 'kantanpro' ),
					'icon' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>'
				),
				'tax_return' => array(
					'label' => __( '確定申告用', 'kantanpro' ),
					'icon' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 2 2h12c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>'
				)
			);

			$search_toolbar = '';
			if ( class_exists( 'KTPWP_Tab_Search_UI' ) ) {
				$search_toolbar = KTPWP_Tab_Search_UI::get_instance()->render_toolbar_form(
					'report',
					array( 'report_type' => $current_report )
				);
			}

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
			$print_button = self::render_tab_print_button(
				array(
					'id'      => 'js-report-print-btn',
					'label'   => __( 'レポート印刷', 'kantanpro' ),
					'title'   => __( '印刷（ブラウザの印刷／PDFに保存）', 'kantanpro' ),
					'onclick' => "typeof ktpReportPrintOpen === 'function' && ktpReportPrintOpen();",
				)
			);

			return '<div class="controller ktp-report-controller">
				<div class="ktp-report-controller__search">
					' . $search_toolbar . '
				</div>
				<div class="ktp-report-controller__bar">
				<div class="ktp-report-controller__main">
					' . $report_buttons . '
				</div>
				<div class="ktp-report-controller__actions">
					' . $print_button . '
				</div>
				</div>
			</div>';
		}

		/**
		 * 無料版レポートタブ用タイトルバー
		 *
		 * @return string
		 */
		public function generate_free_edition_report_title_bar() {
			return '<div class="controller ktp-report-controller ktp-report-controller--free-notice">'
				. '<div class="ktp-report-controller__main">'
				. '<span class="ktp-report-free-edition-title">' . esc_html__( 'レポート機能は無料版では利用できません', 'kantanpro' ) . '</span>'
				. '</div>'
				. '<div class="ktp-report-controller__actions"></div>'
				. '</div>';
		}
		// KTPWP-WPORG-STRIP report END

		/**
		 * Generate workflow section
		 *
		 * @since 1.0.0
		 * @return string HTML content for the workflow section
		 */
		public function generate_workflow() {
			return '<div class="workflow"></div>';
		}

		/**
		 * 検索が複数件ヒットしたときの結果ポップアップ（顧客・サービス・協力会社で共通）。
		 *
		 * 結果の HTML は非表示の div に入れて本文へ返し、ポップアップを開く JavaScript は
		 * スクリプトキューに載せる。本文に生の <script> を出すと、ショートコード出力の
		 * 許可リスト（KTPWP_Kses）で落ちてしまうため。
		 *
		 * @param string $multi_results_id   非表示コンテナの id。
		 * @param string $close_redirect_url 「閉じる」を押したときの遷移先。
		 * @param string $results_html       検索結果の HTML。
		 * @return string 非表示コンテナの HTML。
		 */
		public static function render_multi_results_popup( $multi_results_id, $close_redirect_url, $results_html ) {
			// $close_redirect_url は esc_url() 済みで & が &#038; になっている。esc_js() も & を &amp; にするので、
			// JS の location.href に使うと URL が壊れる（?page_id=5#038;tab_name=…）。実体参照を戻し、
			// JS リテラルは wp_json_encode() で作る。
			// 呼び出し元で複数回エスケープされていることがあるので、変化しなくなるまで戻す（最大3回）。
			$close_redirect_url = (string) $close_redirect_url;
			for ( $i = 0; $i < 3; $i++ ) {
				$decoded = html_entity_decode( $close_redirect_url, ENT_QUOTES, 'UTF-8' );
				if ( $decoded === $close_redirect_url ) {
					break;
				}
				$close_redirect_url = $decoded;
			}

			$js = '(function() {
	var run = function() {
		var el = document.getElementById(' . wp_json_encode( (string) $multi_results_id ) . ');
		if (!el) return;
		var searchResultsHtml = el.innerHTML;
		var popup = document.createElement("div");
		popup.innerHTML = searchResultsHtml;
		popup.style.cssText = "position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);background:#fff;padding:20px;z-index:10001;width:80%;max-width:600px;border:1px solid #ccc;border-radius:5px;box-shadow:0 4px 6px rgba(0,0,0,0.1)";
		document.body.appendChild(popup);
		var closeBtn = document.createElement("button");
		closeBtn.textContent = ' . wp_json_encode( __( '閉じる', 'kantanpro' ) ) . ';
		closeBtn.style.cssText = "font-size:0.8em;color:#000;display:block;margin:10px auto 0;padding:10px;background:#cdcccc;border-radius:5px;border-color:#999;cursor:pointer";
		closeBtn.onclick = function() { document.body.removeChild(popup); location.href = ' . wp_json_encode( $close_redirect_url ) . '; };
		popup.appendChild(closeBtn);
	};
	if (document.readyState === "loading") {
		document.addEventListener("DOMContentLoaded", run);
	} else {
		run();
	}
})();';

			ktpwp_add_inline_script( $js );

			return '<div id="' . esc_attr( $multi_results_id ) . '" style="display:none;">' . $results_html . '</div>';
		}

		/**
		 * KantanBiz 相当のタブ印刷ボタン HTML を生成する。
		 *
		 * @since 1.0.0
		 * @param array<string, mixed> $args ボタン設定。
		 * @return string
		 */
		public static function render_tab_print_button( $args = array() ) {
			$args = wp_parse_args(
				$args,
				array(
					'id'       => '',
					'label'    => __( '印刷', 'kantanpro' ),
					'title'    => __( '印刷（ブラウザの印刷／PDFに保存）', 'kantanpro' ),
					'onclick'  => '',
					'class'    => '',
					'attrs'    => array(),
					'disabled' => false,
				)
			);

			$classes = trim( 'ktp-tab-print-btn js-tab-print-btn ' . (string) $args['class'] );
			$attrs   = '';

			foreach ( (array) $args['attrs'] as $key => $value ) {
				if ( ! is_string( $key ) || $key === '' ) {
					continue;
				}
				if ( $key === 'data-status-label-map' && is_array( $value ) ) {
					$value = wp_json_encode( $value, JSON_UNESCAPED_UNICODE );
				}
				$attrs .= ' ' . esc_attr( $key ) . '="' . esc_attr( (string) $value ) . '"';
			}

			$id_attr      = $args['id'] !== '' ? ' id="' . esc_attr( (string) $args['id'] ) . '"' : '';
			$onclick_attr = $args['onclick'] !== '' ? ' onclick="' . esc_attr( (string) $args['onclick'] ) . '"' : '';
			$disabled     = ! empty( $args['disabled'] ) ? ' disabled' : '';

			return '<button type="button"'
				. $id_attr
				. ' class="' . esc_attr( $classes ) . '"'
				. ' title="' . esc_attr( (string) $args['title'] ) . '"'
				. $onclick_attr
				. $disabled
				. $attrs
				. '>'
				. esc_html( (string) $args['label'] )
				. '</button>';
		}
	}

}
