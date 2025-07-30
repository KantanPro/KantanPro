<?php
/**
 * Report class for KTPWP plugin
 *
 * Handles report generation, analytics display,
 * and security implementations.
 *
 * @package KTPWP
 * @subpackage Includes
 * @since 1.0.0
 * @author Kantan Pro
 * @copyright 2024 Kantan Pro
 * @license GPL-2.0+
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once plugin_dir_path( __FILE__ ) . 'class-ktpwp-ui-generator.php';
require_once plugin_dir_path( __FILE__ ) . 'class-ktpwp-graph-renderer.php';

if ( ! class_exists( 'KTPWP_Report_Class' ) ) {

	/**
	 * Report class for managing reports and analytics
	 *
	 * @since 1.0.0
	 */
	class KTPWP_Report_Class {

		/**
		 * Constructor
		 *
		 * @since 1.0.0
		 */
		public function __construct() {
			// Constructor initialization
		}

		/**
		 * Display report tab view
		 *
		 * @since 1.0.0
		 * @param string $tab_name Tab name
		 * @return string HTML content
		 */
		public function Report_Tab_View( $tab_name ) {
			if ( empty( $tab_name ) ) {
				error_log( 'KTPWP: Empty tab_name provided to Report_Tab_View method' );
				return '';
			}

			// 権限チェック
			if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'ktpwp_access' ) ) {
				return '<div class="error-message">' . esc_html__( 'このページにアクセスする権限がありません。', 'ktpwp' ) . '</div>';
			}

			// ライセンスマネージャーのインスタンスを取得
			$license_manager = KTPWP_License_Manager::get_instance();
			$is_license_valid = $license_manager->is_license_valid();

			$ui_generator = new KTPWP_Ui_Generator();
			$graph_renderer = new KTPWP_Graph_Renderer();

			$content = $ui_generator->generate_controller();

			if ( ! $is_license_valid ) {
				$content .= $graph_renderer->render_dummy_graph();
			} else {
				$content .= $this->render_comprehensive_reports();
			}

			return $content;
		}

		/**
		 * Render comprehensive reports with real data
		 *
		 * @since 1.0.0
		 * @return string HTML content
		 */
		private function render_comprehensive_reports() {
			global $wpdb;

			$content = '<div id="report_content" style="background:#fff;padding:32px 12px 32px 12px;max-width:1200px;margin:32px auto 0 auto;border-radius:10px;box-shadow:0 2px 8px #eee;">';
			
			// レポートタイプ選択
			$content .= $this->render_report_selector();
			
			// 現在選択されているレポートタイプを取得
			$report_type = isset( $_GET['report_type'] ) ? sanitize_text_field( $_GET['report_type'] ) : 'sales';
			
			switch ( $report_type ) {
				case 'sales':
					$content .= $this->render_sales_report();
					break;
				case 'progress':
					$content .= $this->render_progress_report();
					break;
				case 'client':
					$content .= $this->render_client_report();
					break;
				case 'service':
					$content .= $this->render_service_report();
					break;
				case 'supplier':
					$content .= $this->render_supplier_report();
					break;
				default:
					$content .= $this->render_sales_report();
					break;
			}

			$content .= '</div>';

			// Chart.js とカスタムスクリプトを読み込み
			$content .= '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>';
			
			// AJAX設定を追加
			$ajax_data = array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'ktpwp_ajax_nonce' ),
				'nonces'   => array(
					'general' => wp_create_nonce( 'ktpwp_ajax_nonce' )
				)
			);
			$content .= '<script>var ktp_ajax_object = ' . json_encode( $ajax_data ) . ';</script>';
			$content .= '<script src="' . esc_url( plugins_url( 'js/ktp-report-charts.js', dirname( __FILE__ ) ) ) . '?v=' . KANTANPRO_PLUGIN_VERSION . '"></script>';

			return $content;
		}

		/**
		 * Render report type selector
		 *
		 * @since 1.0.0
		 * @return string HTML content
		 */
		private function render_report_selector() {
			$current_report = isset( $_GET['report_type'] ) ? sanitize_text_field( $_GET['report_type'] ) : 'sales';
			
			$reports = array(
				'sales' => '売上レポート',
				'progress' => '進捗状況',
				'client' => '顧客別レポート',
				'service' => 'サービス別レポート',
				'supplier' => '協力会社レポート'
			);

			$content = '<div class="report-selector" style="margin-bottom:24px;padding:16px;background:#f8f9fa;border-radius:8px;">';
			$content .= '<h3 style="margin:0 0 16px 0;color:#333;">レポート種類</h3>';
			$content .= '<div style="display:flex;flex-wrap:wrap;gap:8px;">';

			foreach ( $reports as $key => $label ) {
				$active_class = ( $current_report === $key ) ? 'style="background:#1976d2;color:#fff;"' : 'style="background:#fff;color:#333;"';
				$url = add_query_arg( array( 'tab_name' => 'report', 'report_type' => $key ) );
				
				$content .= '<a href="' . esc_url( $url ) . '" class="report-btn" ' . $active_class . ' style="padding:8px 16px;border-radius:6px;text-decoration:none;border:1px solid #ddd;transition:all 0.3s;">';
				$content .= esc_html( $label );
				$content .= '</a>';
			}

			$content .= '</div></div>';

			return $content;
		}

		/**
		 * Render sales report
		 *
		 * @since 1.0.0
		 * @return string HTML content
		 */
		private function render_sales_report() {
			global $wpdb;

			$content = '<div class="sales-report">';
			$content .= '<h3 style="margin-bottom:24px;color:#333;">売上レポート</h3>';

			// 期間選択
			$content .= $this->render_period_selector();

			// 売上サマリー
			$content .= $this->render_sales_summary();

			// グラフエリア
			$content .= '<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-top:24px;">';
			$content .= '<div style="background:#f8f9fa;padding:20px;border-radius:8px;">';
			$content .= '<h4 style="margin:0 0 16px 0;">月別売上推移</h4>';
			$content .= '<canvas id="monthlySalesChart" width="400" height="300"></canvas>';
			$content .= '</div>';
			
			$content .= '<div style="background:#f8f9fa;padding:20px;border-radius:8px;">';
			$content .= '<h4 style="margin:0 0 16px 0;">進捗別売上</h4>';
			$content .= '<canvas id="progressSalesChart" width="400" height="300"></canvas>';
			$content .= '</div>';
			$content .= '</div>';

			$content .= '</div>';

			return $content;
		}

		/**
		 * Render progress report
		 *
		 * @since 1.0.0
		 * @return string HTML content
		 */
		private function render_progress_report() {
			$content = '<div class="progress-report">';
			$content .= '<h3 style="margin-bottom:24px;color:#333;">進捗状況レポート</h3>';

			// 進捗サマリー
			$content .= $this->render_progress_summary();

			// グラフエリア
			$content .= '<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-top:24px;">';
			$content .= '<div style="background:#f8f9fa;padding:20px;border-radius:8px;">';
			$content .= '<h4 style="margin:0 0 16px 0;">進捗状況分布</h4>';
			$content .= '<canvas id="progressDistributionChart" width="400" height="300"></canvas>';
			$content .= '</div>';
			
			$content .= '<div style="background:#f8f9fa;padding:20px;border-radius:8px;">';
			$content .= '<h4 style="margin:0 0 16px 0;">納期管理</h4>';
			$content .= '<canvas id="deadlineChart" width="400" height="300"></canvas>';
			$content .= '</div>';
			$content .= '</div>';

			$content .= '</div>';

			return $content;
		}

		/**
		 * Render client report
		 *
		 * @since 1.0.0
		 * @return string HTML content
		 */
		private function render_client_report() {
			$content = '<div class="client-report">';
			$content .= '<h3 style="margin-bottom:24px;color:#333;">顧客別レポート</h3>';

			// 顧客サマリー
			$content .= $this->render_client_summary();

			// グラフエリア
			$content .= '<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-top:24px;">';
			$content .= '<div style="background:#f8f9fa;padding:20px;border-radius:8px;">';
			$content .= '<h4 style="margin:0 0 16px 0;">顧客別売上</h4>';
			$content .= '<canvas id="clientSalesChart" width="400" height="300"></canvas>';
			$content .= '</div>';
			
			$content .= '<div style="background:#f8f9fa;padding:20px;border-radius:8px;">';
			$content .= '<h4 style="margin:0 0 16px 0;">顧客別案件数</h4>';
			$content .= '<canvas id="clientOrderChart" width="400" height="300"></canvas>';
			$content .= '</div>';
			$content .= '</div>';

			$content .= '</div>';

			return $content;
		}

		/**
		 * Render service report
		 *
		 * @since 1.0.0
		 * @return string HTML content
		 */
		private function render_service_report() {
			$content = '<div class="service-report">';
			$content .= '<h3 style="margin-bottom:24px;color:#333;">サービス別レポート</h3>';

			// サービスサマリー
			$content .= $this->render_service_summary();

			// グラフエリア
			$content .= '<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-top:24px;">';
			$content .= '<div style="background:#f8f9fa;padding:20px;border-radius:8px;">';
			$content .= '<h4 style="margin:0 0 16px 0;">サービス別売上</h4>';
			$content .= '<canvas id="serviceSalesChart" width="400" height="300"></canvas>';
			$content .= '</div>';
			
			$content .= '<div style="background:#f8f9fa;padding:20px;border-radius:8px;">';
			$content .= '<h4 style="margin:0 0 16px 0;">サービス利用率</h4>';
			$content .= '<canvas id="serviceUsageChart" width="400" height="300"></canvas>';
			$content .= '</div>';
			$content .= '</div>';

			$content .= '</div>';

			return $content;
		}

		/**
		 * Render supplier report
		 *
		 * @since 1.0.0
		 * @return string HTML content
		 */
		private function render_supplier_report() {
			$content = '<div class="supplier-report">';
			$content .= '<h3 style="margin-bottom:24px;color:#333;">協力会社レポート</h3>';

			// 協力会社サマリー
			$content .= $this->render_supplier_summary();

			// グラフエリア
			$content .= '<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-top:24px;">';
			$content .= '<div style="background:#f8f9fa;padding:20px;border-radius:8px;">';
			$content .= '<h4 style="margin:0 0 16px 0;">協力会社別貢献度</h4>';
			$content .= '<canvas id="supplierContributionChart" width="400" height="300"></canvas>';
			$content .= '</div>';
			
			$content .= '<div style="background:#f8f9fa;padding:20px;border-radius:8px;">';
			$content .= '<h4 style="margin:0 0 16px 0;">スキル別分布</h4>';
			$content .= '<canvas id="skillDistributionChart" width="400" height="300"></canvas>';
			$content .= '</div>';
			$content .= '</div>';

			$content .= '</div>';

			return $content;
		}

		/**
		 * Render period selector
		 *
		 * @since 1.0.0
		 * @return string HTML content
		 */
		private function render_period_selector() {
			$current_period = isset( $_GET['period'] ) ? sanitize_text_field( $_GET['period'] ) : 'all_time';
			
			$periods = array(
				'current_year' => '今年',
				'last_year' => '去年',
				'current_month' => '今月',
				'last_month' => '先月',
				'all_time' => '全期間'
			);

			$content = '<div style="margin-bottom:24px;padding:16px;background:#f8f9fa;border-radius:8px;">';
			$content .= '<h4 style="margin:0 0 12px 0;">期間選択</h4>';
			$content .= '<div style="display:flex;flex-wrap:wrap;gap:8px;">';

			foreach ( $periods as $key => $label ) {
				$active_class = ( $current_period === $key ) ? 'style="background:#1976d2;color:#fff;"' : 'style="background:#fff;color:#333;"';
				$url = add_query_arg( array( 'tab_name' => 'report', 'report_type' => $_GET['report_type'] ?? 'sales', 'period' => $key ) );
				
				$content .= '<a href="' . esc_url( $url ) . '" class="period-btn" ' . $active_class . ' style="padding:6px 12px;border-radius:4px;text-decoration:none;border:1px solid #ddd;font-size:14px;transition:all 0.3s;">';
				$content .= esc_html( $label );
				$content .= '</a>';
			}

			$content .= '</div></div>';

			return $content;
		}

		/**
		 * Render sales summary
		 *
		 * @since 1.0.0
		 * @return string HTML content
		 */
		private function render_sales_summary() {
			global $wpdb;

			$period = isset( $_GET['period'] ) ? sanitize_text_field( $_GET['period'] ) : 'all_time';
			
			// 期間に応じたWHERE句を生成
			$where_clause = $this->get_period_where_clause( $period );

			// 総売上
			$total_sales_query = "SELECT SUM(o.total_amount) as total FROM {$wpdb->prefix}ktp_order o WHERE 1=1 {$where_clause}";
			$total_sales = $wpdb->get_var( $total_sales_query ) ?: 0;

			// 案件数
			$order_count_query = "SELECT COUNT(*) as count FROM {$wpdb->prefix}ktp_order o WHERE 1=1 {$where_clause}";
			$order_count = $wpdb->get_var( $order_count_query ) ?: 0;

			// 平均単価
			$avg_amount = $order_count > 0 ? round( $total_sales / $order_count ) : 0;

			$content = '<div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:16px;margin-bottom:24px;">';
			
			$content .= '<div style="background:linear-gradient(135deg, #667eea 0%, #764ba2 100%);color:#fff;padding:20px;border-radius:8px;text-align:center;">';
			$content .= '<h4 style="margin:0 0 8px 0;font-size:14px;">総売上</h4>';
			$content .= '<div style="font-size:24px;font-weight:bold;">¥' . number_format( $total_sales ) . '</div>';
			$content .= '</div>';

			$content .= '<div style="background:linear-gradient(135deg, #f093fb 0%, #f5576c 100%);color:#fff;padding:20px;border-radius:8px;text-align:center;">';
			$content .= '<h4 style="margin:0 0 8px 0;font-size:14px;">案件数</h4>';
			$content .= '<div style="font-size:24px;font-weight:bold;">' . number_format( $order_count ) . '件</div>';
			$content .= '</div>';

			$content .= '<div style="background:linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);color:#fff;padding:20px;border-radius:8px;text-align:center;">';
			$content .= '<h4 style="margin:0 0 8px 0;font-size:14px;">平均単価</h4>';
			$content .= '<div style="font-size:24px;font-weight:bold;">¥' . number_format( $avg_amount ) . '</div>';
			$content .= '</div>';

			$content .= '</div>';

			return $content;
		}

		/**
		 * Render progress summary
		 *
		 * @since 1.0.0
		 * @return string HTML content
		 */
		private function render_progress_summary() {
			global $wpdb;

			$period = isset( $_GET['period'] ) ? sanitize_text_field( $_GET['period'] ) : 'all_time';
			$where_clause = $this->get_period_where_clause( $period );

			// 進捗別案件数
			$progress_query = "SELECT o.progress, COUNT(*) as count FROM {$wpdb->prefix}ktp_order o WHERE 1=1 {$where_clause} GROUP BY o.progress ORDER BY o.progress";
			$progress_results = $wpdb->get_results( $progress_query );

			$progress_labels = array(
				1 => '受付中',
				2 => '見積中',
				3 => '受注',
				4 => '完了',
				5 => '請求済',
				6 => '入金済',
				7 => 'ボツ'
			);

			$content = '<div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(150px, 1fr));gap:12px;margin-bottom:24px;">';

			foreach ( $progress_results as $result ) {
				$label = isset( $progress_labels[ $result->progress ] ) ? $progress_labels[ $result->progress ] : '不明';
				$color = $this->get_progress_color( $result->progress );
				
				$content .= '<div style="background:' . $color . ';color:#fff;padding:16px;border-radius:8px;text-align:center;">';
				$content .= '<h4 style="margin:0 0 4px 0;font-size:12px;">' . esc_html( $label ) . '</h4>';
				$content .= '<div style="font-size:20px;font-weight:bold;">' . number_format( $result->count ?? 0 ) . '件</div>';
				$content .= '</div>';
			}

			$content .= '</div>';

			return $content;
		}

		/**
		 * Render client summary
		 *
		 * @since 1.0.0
		 * @return string HTML content
		 */
		private function render_client_summary() {
			global $wpdb;

			$period = isset( $_GET['period'] ) ? sanitize_text_field( $_GET['period'] ) : 'current_year';
			$where_clause = $this->get_period_where_clause( $period );

			// 顧客別売上TOP5
			$client_query = "SELECT c.company_name, SUM(o.total_amount) as total_sales, COUNT(o.id) as order_count 
							FROM {$wpdb->prefix}ktp_order o 
							LEFT JOIN {$wpdb->prefix}ktp_client c ON o.client_id = c.id 
							WHERE 1=1 {$where_clause} 
							GROUP BY o.client_id 
							ORDER BY total_sales DESC 
							LIMIT 5";
			$client_results = $wpdb->get_results( $client_query );

			$content = '<div style="background:#f8f9fa;padding:20px;border-radius:8px;margin-bottom:24px;">';
			$content .= '<h4 style="margin:0 0 16px 0;">売上TOP5顧客</h4>';
			$content .= '<div style="display:grid;gap:12px;">';

			foreach ( $client_results as $index => $client ) {
				$rank = $index + 1;
				$content .= '<div style="display:flex;justify-content:space-between;align-items:center;padding:12px;background:#fff;border-radius:6px;">';
				$content .= '<div style="display:flex;align-items:center;gap:12px;">';
				$content .= '<span style="background:#1976d2;color:#fff;width:24px;height:24px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:bold;">' . $rank . '</span>';
				$content .= '<span style="font-weight:bold;">' . esc_html( $client->company_name ) . '</span>';
				$content .= '</div>';
				$content .= '<div style="text-align:right;">';
				$content .= '<div style="font-weight:bold;color:#1976d2;">¥' . number_format( $client->total_sales ?? 0 ) . '</div>';
				$content .= '<div style="font-size:12px;color:#666;">' . number_format( $client->order_count ?? 0 ) . '件</div>';
				$content .= '</div>';
				$content .= '</div>';
			}

			$content .= '</div></div>';

			return $content;
		}

		/**
		 * Render service summary
		 *
		 * @since 1.0.0
		 * @return string HTML content
		 */
		private function render_service_summary() {
			global $wpdb;

			$period = isset( $_GET['period'] ) ? sanitize_text_field( $_GET['period'] ) : 'current_year';
			$where_clause = $this->get_period_where_clause( $period );

			// サービス別売上TOP5（invoice_itemsテーブルが存在しない場合の代替クエリ）
			$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}ktp_invoice_items'");
			
			if ($table_exists) {
				// invoice_itemsテーブルが存在する場合
				$service_query = "SELECT s.service_name, SUM(oi.quantity * oi.unit_price) as total_sales, COUNT(DISTINCT o.id) as order_count 
								 FROM {$wpdb->prefix}ktp_order o 
								 LEFT JOIN {$wpdb->prefix}ktp_invoice_items oi ON o.id = oi.order_id 
								 LEFT JOIN {$wpdb->prefix}ktp_service s ON oi.service_id = s.id 
								 WHERE 1=1 {$where_clause} 
								 GROUP BY s.id 
								 ORDER BY total_sales DESC 
								 LIMIT 5";
			} else {
				// invoice_itemsテーブルが存在しない場合、orderテーブルのtotal_amountを使用
				$service_query = "SELECT 'サービス別売上' as service_name, SUM(o.total_amount) as total_sales, COUNT(o.id) as order_count 
								 FROM {$wpdb->prefix}ktp_order o 
								 WHERE 1=1 {$where_clause} 
								 ORDER BY total_sales DESC 
								 LIMIT 5";
			}
			$service_results = $wpdb->get_results( $service_query );

			$content = '<div style="background:#f8f9fa;padding:20px;border-radius:8px;margin-bottom:24px;">';
			$content .= '<h4 style="margin:0 0 16px 0;">売上TOP5サービス</h4>';
			$content .= '<div style="display:grid;gap:12px;">';

			foreach ( $service_results as $index => $service ) {
				$rank = $index + 1;
				$content .= '<div style="display:flex;justify-content:space-between;align-items:center;padding:12px;background:#fff;border-radius:6px;">';
				$content .= '<div style="display:flex;align-items:center;gap:12px;">';
				$content .= '<span style="background:#4caf50;color:#fff;width:24px;height:24px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:bold;">' . $rank . '</span>';
				$content .= '<span style="font-weight:bold;">' . esc_html( $service->service_name ) . '</span>';
				$content .= '</div>';
				$content .= '<div style="text-align:right;">';
				$content .= '<div style="font-weight:bold;color:#4caf50;">¥' . number_format( $service->total_sales ?? 0 ) . '</div>';
				$content .= '<div style="font-size:12px;color:#666;">' . number_format( $service->order_count ?? 0 ) . '件</div>';
				$content .= '</div>';
				$content .= '</div>';
			}

			$content .= '</div></div>';

			return $content;
		}

		/**
		 * Render supplier summary
		 *
		 * @since 1.0.0
		 * @return string HTML content
		 */
		private function render_supplier_summary() {
			global $wpdb;

			$period = isset( $_GET['period'] ) ? sanitize_text_field( $_GET['period'] ) : 'current_year';
			$where_clause = $this->get_period_where_clause( $period );

			// 協力会社別貢献度TOP5（invoice_itemsテーブルが存在しない場合の代替クエリ）
			$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}ktp_invoice_items'");
			
			if ($table_exists) {
				// invoice_itemsテーブルが存在する場合
				$supplier_query = "SELECT s.company_name, COUNT(DISTINCT o.id) as order_count, SUM(oi.quantity * oi.unit_price) as total_contribution 
								  FROM {$wpdb->prefix}ktp_order o 
								  LEFT JOIN {$wpdb->prefix}ktp_invoice_items oi ON o.id = oi.order_id 
								  LEFT JOIN {$wpdb->prefix}ktp_supplier s ON oi.supplier_id = s.id 
								  WHERE 1=1 {$where_clause} AND oi.supplier_id IS NOT NULL 
								  GROUP BY s.id 
								  ORDER BY total_contribution DESC 
								  LIMIT 5";
			} else {
				// invoice_itemsテーブルが存在しない場合、orderテーブルのtotal_amountを使用
				$supplier_query = "SELECT '協力会社貢献度' as company_name, COUNT(o.id) as order_count, SUM(o.total_amount) as total_contribution 
								  FROM {$wpdb->prefix}ktp_order o 
								  WHERE 1=1 {$where_clause} 
								  ORDER BY total_contribution DESC 
								  LIMIT 5";
			}
			$supplier_results = $wpdb->get_results( $supplier_query );

			$content = '<div style="background:#f8f9fa;padding:20px;border-radius:8px;margin-bottom:24px;">';
			$content .= '<h4 style="margin:0 0 16px 0;">貢献度TOP5協力会社</h4>';
			$content .= '<div style="display:grid;gap:12px;">';

			foreach ( $supplier_results as $index => $supplier ) {
				$rank = $index + 1;
				$content .= '<div style="display:flex;justify-content:space-between;align-items:center;padding:12px;background:#fff;border-radius:6px;">';
				$content .= '<div style="display:flex;align-items:center;gap:12px;">';
				$content .= '<span style="background:#ff9800;color:#fff;width:24px;height:24px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:bold;">' . $rank . '</span>';
				$content .= '<span style="font-weight:bold;">' . esc_html( $supplier->company_name ) . '</span>';
				$content .= '</div>';
				$content .= '<div style="text-align:right;">';
				$content .= '<div style="font-weight:bold;color:#ff9800;">¥' . number_format( $supplier->total_contribution ?? 0 ) . '</div>';
				$content .= '<div style="font-size:12px;color:#666;">' . number_format( $supplier->order_count ?? 0 ) . '件</div>';
				$content .= '</div>';
				$content .= '</div>';
			}

			$content .= '</div></div>';

			return $content;
		}

		/**
		 * Get period WHERE clause
		 *
		 * @since 1.0.0
		 * @param string $period Period type
		 * @return string WHERE clause
		 */
		private function get_period_where_clause( $period ) {
			$where_clause = '';

			switch ( $period ) {
				case 'current_year':
					$where_clause = " AND YEAR(o.created_at) = YEAR(CURDATE())";
					break;
				case 'last_year':
					$where_clause = " AND YEAR(o.created_at) = YEAR(CURDATE()) - 1";
					break;
				case 'current_month':
					$where_clause = " AND YEAR(o.created_at) = YEAR(CURDATE()) AND MONTH(o.created_at) = MONTH(CURDATE())";
					break;
				case 'last_month':
					$where_clause = " AND YEAR(o.created_at) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND MONTH(o.created_at) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))";
					break;
				case 'all_time':
				default:
					$where_clause = "";
					break;
			}

			return $where_clause;
		}

		/**
		 * Get progress color
		 *
		 * @since 1.0.0
		 * @param int $progress Progress number
		 * @return string Color code
		 */
		private function get_progress_color( $progress ) {
			$colors = array(
				1 => 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)', // 受付中
				2 => 'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)', // 見積中
				3 => 'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)', // 受注
				4 => 'linear-gradient(135deg, #43e97b 0%, #38f9d7 100%)', // 完了
				5 => 'linear-gradient(135deg, #fa709a 0%, #fee140 100%)', // 請求済
				6 => 'linear-gradient(135deg, #a8edea 0%, #fed6e3 100%)', // 入金済
				7 => 'linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%)', // ボツ
			);

			return isset( $colors[ $progress ] ) ? $colors[ $progress ] : 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)';
		}
	}
} // class_exists
