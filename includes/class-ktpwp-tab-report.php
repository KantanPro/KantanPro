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

			// デバッグログを追加
			error_log( 'KTPWP Report: License check result = ' . ( $is_license_valid ? 'true' : 'false' ) );
			
			// 現在のライセンス状態を詳細にログ出力
			$license_key = get_option( 'ktp_license_key' );
			$license_status = get_option( 'ktp_license_status' );
			error_log( 'KTPWP Report: Current license key = ' . ( empty( $license_key ) ? 'empty' : 'set' ) . ', status = ' . $license_status );

			$ui_generator = new KTPWP_Ui_Generator();
			$graph_renderer = new KTPWP_Graph_Renderer();

			$content = $ui_generator->generate_controller();

			if ( ! $is_license_valid ) {
				error_log( 'KTPWP Report: Rendering license required message (license invalid)' );
				// グラフ背景＋メッセージ重ね表示
				$content .= $this->render_license_required_with_graph();
			} else {
				error_log( 'KTPWP Report: Rendering comprehensive reports (license valid)' );
				$content .= $this->render_comprehensive_reports();
			}

			return $content;
		}

		/**
		 * ライセンス無効時にグラフを背景に重ねてメッセージを表示
		 *
		 * @return string
		 */
		private function render_license_required_with_graph() {
			$graph_renderer = new KTPWP_Graph_Renderer();
			$dummy_graph_url = esc_url( plugins_url( '../images/default/dummy_graph.png', __FILE__ ) );
			return '<div style="position:relative;max-width:800px;margin:30px auto;">
				<img src="' . $dummy_graph_url . '" alt="' . esc_attr__( 'Report Graph', 'ktpwp' ) . '" style="width:100%;border-radius:8px;box-shadow:0 4px 16px rgba(0,0,0,0.1);filter:blur(3px);opacity:0.7;">
				<div style="position:absolute;top:0;left:0;width:100%;height:100%;background:rgba(255,255,255,0.3);border-radius:8px;display:flex;flex-direction:column;justify-content:flex-start;align-items:center;text-align:center;padding:20px;">
					<h3 style="margin:50px 0 15px;color:#d32f2f;font-size:24px;text-shadow:0 1px 2px rgba(255,255,255,0.8);">レポート機能の利用にはライセンスが必要です</h3>
					<p style="margin-bottom:20px;font-size:16px;line-height:1.6;color:#555;text-shadow:0 1px 2px rgba(255,255,255,0.8);">詳細な分析とレポート機能を利用するには、ライセンスキーを購入して設定してください。</p>
					<div style="margin-bottom:20px;">
						<a href="https://www.kantanpro.com/klm" target="_blank" class="button button-primary" style="padding:12px 24px;font-size:16px;text-decoration:none;background:#0073aa;color:#fff;border-radius:5px;display:inline-block;">ライセンスを購入</a>
					</div>
					<p style="font-size:18px;font-weight:bold;color:#0073aa;line-height:1.5;">ライセンス購入後は<a href="' . esc_url( admin_url( 'admin.php?page=ktp-license' ) ) . '" style="color:#0073aa;text-decoration:underline;">ライセンス設定</a>でライセンスキーを入力してください</p>
				</div>
			</div>';
		}

		/**
		 * Render comprehensive reports with real data
		 *
		 * @since 1.0.0
		 * @return string HTML content
		 */
		private function render_comprehensive_reports() {
			global $wpdb;

			$content = '<div id="ktp_report_inner_content" class="ktp-report-print-area" style="background:#fcfcfc;padding:16px 12px 32px 12px;max-width:1200px;margin:0 auto;border-radius:10px;box-shadow:0 2px 8px #eee;">';
			
			// 現在選択されているレポートタイプを取得
			$report_type = isset( $_GET['report_type'] ) ? sanitize_text_field( $_GET['report_type'] ) : 'sales';
			
			switch ( $report_type ) {
				case 'sales':
					$content .= $this->render_sales_report();
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
				case 'tax_return':
					$content .= $this->render_tax_return_report();
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
			$report_charts_ver = KANTANPRO_PLUGIN_VERSION;
			$report_print_ver  = KANTANPRO_PLUGIN_VERSION;
			$report_charts_path = plugin_dir_path( dirname( __FILE__ ) ) . 'js/ktp-report-charts.js';
			$report_print_path  = plugin_dir_path( dirname( __FILE__ ) ) . 'js/ktp-report-print.js';

			if ( file_exists( $report_charts_path ) ) {
				$report_charts_ver .= '.' . filemtime( $report_charts_path );
			}
			if ( file_exists( $report_print_path ) ) {
				$report_print_ver .= '.' . filemtime( $report_print_path );
			}

			$content .= '<script src="' . esc_url( plugins_url( 'js/ktp-report-charts.js', dirname( __FILE__ ) ) ) . '?v=' . esc_attr( $report_charts_ver ) . '"></script>';
			$content .= '<script src="' . esc_url( plugins_url( 'js/ktp-report-print.js', dirname( __FILE__ ) ) ) . '?v=' . esc_attr( $report_print_ver ) . '"></script>';

			// 確定申告タブの場合は売上台帳PDF用スクリプトも読み込み
			$report_type = isset( $_GET['report_type'] ) ? sanitize_text_field( $_GET['report_type'] ) : 'sales';
			if ( $report_type === 'tax_return' ) {
				$sales_ledger_print_ver  = KANTANPRO_PLUGIN_VERSION;
				$sales_ledger_print_path = plugin_dir_path( dirname( __FILE__ ) ) . 'js/ktp-sales-ledger-pdf.js';
				if ( file_exists( $sales_ledger_print_path ) ) {
					$sales_ledger_print_ver .= '.' . filemtime( $sales_ledger_print_path );
				}
				$content .= '<script src="' . esc_url( plugins_url( 'js/ktp-sales-ledger-pdf.js', dirname( __FILE__ ) ) ) . '?v=' . esc_attr( $sales_ledger_print_ver ) . '"></script>';
			}

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
			$content .= '<h3 style="margin-top:0;margin-bottom:24px;color:#333;">売上レポート</h3>';

			// 売上計算条件の説明
			$content .= '<div style="background:#e3f2fd;border-left:4px solid #2196f3;padding:16px;margin-bottom:24px;border-radius:4px;">';
			$content .= '<div style="font-weight:bold;color:#1976d2;margin-bottom:8px;">📊 売上計算について</div>';
			$content .= '<div style="color:#333;font-size:14px;line-height:1.5;">';
			$content .= '売上は「請求済」以降の進捗状況の案件のみを対象としています。<br>';
			$content .= '※ 請求項目があっても進捗が「完了」以前の場合は売上に含まれません。<br>';
			$content .= '※ 「ボツ」案件は売上計算から除外されています。';
			$content .= '</div>';
			$content .= '</div>';

			// 期間選択
			$content .= $this->render_period_selector();

			// 売上サマリー
			$content .= $this->render_sales_summary();

			// グラフエリア（モバイルでは縦並び）
			$content .= '<div class="ktp-report-charts-grid">';
			$content .= '<div class="ktp-report-chart-item" style="background:#f8f9fa;padding:20px;border-radius:8px;">';
			$content .= '<h4 style="margin:0 0 16px 0;">月別売上推移</h4>';
			$content .= '<canvas id="monthlySalesChart" width="400" height="300"></canvas>';
			$content .= '</div>';
			
			$content .= '<div class="ktp-report-chart-item" style="background:#f8f9fa;padding:20px;border-radius:8px;">';
			$content .= '<h4 style="margin:0 0 16px 0;">月別利益コスト比較</h4>';
			$content .= '<canvas id="profitTrendChart" width="400" height="300"></canvas>';
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
			$content .= '<h3 style="margin-top:0;margin-bottom:8px;color:#333;">顧客別レポート</h3>';
			
			// 期間の説明を追加
			$period = isset( $_GET['period'] ) ? sanitize_text_field( $_GET['period'] ) : 'all_time';
			$period_description = $this->get_period_description( $period );
			$content .= '<p style="margin:0 0 24px 0;color:#666;font-size:14px;">売上は「請求済」以降の進捗状況の案件のみを対象としています。「ボツ」案件は売上計算から除外されています。対象期間：' . esc_html( $period_description ) . '</p>';

			// 顧客サマリー
			$content .= $this->render_client_summary();

			// グラフエリア（モバイルでは縦並び）
			$content .= '<div class="ktp-report-charts-grid">';
			$content .= '<div class="ktp-report-chart-item" style="background:#f8f9fa;padding:20px;border-radius:8px;">';
			$content .= '<h4 style="margin:0 0 16px 0;">顧客別売上</h4>';
			$content .= '<canvas id="clientSalesChart" width="400" height="300"></canvas>';
			$content .= '</div>';
			
			$content .= '<div class="ktp-report-chart-item" style="background:#f8f9fa;padding:20px;border-radius:8px;">';
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
			$content .= '<h3 style="margin-top:0;margin-bottom:8px;color:#333;">サービス別レポート</h3>';
			
			// 期間の説明を追加
			$period = isset( $_GET['period'] ) ? sanitize_text_field( $_GET['period'] ) : 'all_time';
			$period_description = $this->get_period_description( $period );
			$content .= '<p style="margin:0 0 24px 0;color:#666;font-size:14px;">売上は「請求済」以降の進捗状況の案件のみを対象としています。サービス別比率は「受注」以降の進捗状況の案件を対象としています。「ボツ」案件は計算から除外されています。対象期間：' . esc_html( $period_description ) . '</p>';

			// サービスサマリー
			$content .= $this->render_service_summary();

			// グラフエリア（モバイルでは縦並び）
			$content .= '<div class="ktp-report-charts-grid">';
			$content .= '<div class="ktp-report-chart-item" style="background:#f8f9fa;padding:20px;border-radius:8px;">';
			$content .= '<h4 style="margin:0 0 16px 0;">サービス別売上</h4>';
			$content .= '<canvas id="serviceSalesChart" width="400" height="300"></canvas>';
			$content .= '</div>';
			
			$content .= '<div class="ktp-report-chart-item" style="background:#f8f9fa;padding:20px;border-radius:8px;">';
			$content .= '<h4 style="margin:0 0 16px 0;">サービス別比率（受注ベース）</h4>';
			$content .= '<canvas id="serviceQuantityChart" width="400" height="300"></canvas>';
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
			$content .= '<h3 style="margin-top:0;margin-bottom:8px;color:#333;">協力会社レポート</h3>';
			
			// 期間の説明を追加
			$period = isset( $_GET['period'] ) ? sanitize_text_field( $_GET['period'] ) : 'all_time';
			$period_description = $this->get_period_description( $period );
			$content .= '<p style="margin:0 0 24px 0;color:#666;font-size:14px;">貢献度は「請求済」以降の進捗状況の案件のみを対象としています。「ボツ」案件は計算から除外されています。対象期間：' . esc_html( $period_description ) . '</p>';

			// 協力会社サマリー
			$content .= $this->render_supplier_summary();

			// グラフエリア（モバイルでは縦並び）
			$content .= '<div class="ktp-report-charts-grid">';
			$content .= '<div class="ktp-report-chart-item" style="background:#f8f9fa;padding:20px;border-radius:8px;">';
			$content .= '<h4 style="margin:0 0 16px 0;">協力会社別貢献度</h4>';
			$content .= '<canvas id="supplierSkillsChart" width="400" height="300"></canvas>';
			$content .= '</div>';
			
			$content .= '<div class="ktp-report-chart-item" style="background:#f8f9fa;padding:20px;border-radius:8px;">';
			$content .= '<h4 style="margin:0 0 16px 0;">スキル別協力会社数</h4>';
			$content .= '<canvas id="skillSuppliersChart" width="400" height="300"></canvas>';
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
				'all_time' => '全期間',
				'this_year' => '今年',
				'last_year' => '去年',
				'this_month' => '今月',
				'last_month' => '先月',
				'last_3_months' => '過去3ヶ月',
				'last_6_months' => '過去6ヶ月'
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

			// 総売上（請求済以降の進捗で、請求項目がある案件のみ）
			$total_sales_query = "SELECT SUM(ii.amount) as total 
								 FROM {$wpdb->prefix}ktp_order o 
								 LEFT JOIN {$wpdb->prefix}ktp_order_invoice_items ii ON o.id = ii.order_id 
								 WHERE 1=1 {$where_clause} AND ii.amount IS NOT NULL AND o.progress >= 5 AND o.progress != 7";
			$total_sales = $wpdb->get_var( $total_sales_query ) ?: 0;

			// 案件数（請求済以降の進捗のみ）
			$order_count_query = "SELECT COUNT(*) as count FROM {$wpdb->prefix}ktp_order o WHERE 1=1 {$where_clause} AND o.progress >= 5 AND o.progress != 7";
			$order_count = $wpdb->get_var( $order_count_query ) ?: 0;

			// 平均単価
			$avg_amount = $order_count > 0 ? round( $total_sales / $order_count ) : 0;

			$content = '<div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:16px;margin-bottom:24px;">';
			
			$content .= '<div style="background:linear-gradient(135deg, #667eea 0%, #764ba2 100%);color:#fff;padding:20px;border-radius:8px;text-align:center;">';
			$content .= '<div style="margin:0 0 8px 0;font-size:16px;font-weight:bold;color:#fff;">総売上</div>';
			$content .= '<div style="font-size:24px;font-weight:bold;">¥' . number_format( $total_sales ) . '</div>';
			$content .= '</div>';

			$content .= '<div style="background:linear-gradient(135deg, #f093fb 0%, #f5576c 100%);color:#fff;padding:20px;border-radius:8px;text-align:center;">';
			$content .= '<div style="margin:0 0 8px 0;font-size:16px;font-weight:bold;color:#fff;">案件数</div>';
			$content .= '<div style="font-size:24px;font-weight:bold;">' . number_format( $order_count ) . '件</div>';
			$content .= '</div>';

			$content .= '<div style="background:linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);color:#fff;padding:20px;border-radius:8px;text-align:center;">';
			$content .= '<div style="margin:0 0 8px 0;font-size:16px;font-weight:bold;color:#fff;">平均単価</div>';
			$content .= '<div style="font-size:24px;font-weight:bold;">¥' . number_format( $avg_amount ) . '</div>';
			$content .= '</div>';

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

			$period = isset( $_GET['period'] ) ? sanitize_text_field( $_GET['period'] ) : 'all_time';
			$where_clause = $this->get_period_where_clause( $period );

			// 顧客別売上TOP5（請求済以降の進捗状況の案件のみ）
			$client_query = "SELECT COALESCE(c.company_name, '（顧客未設定）') AS company_name, SUM(ii.amount) AS total_sales, COUNT(DISTINCT o.id) AS order_count 
				FROM {$wpdb->prefix}ktp_order o 
				LEFT JOIN {$wpdb->prefix}ktp_client c ON o.client_id = c.id 
				LEFT JOIN {$wpdb->prefix}ktp_order_invoice_items ii ON o.id = ii.order_id 
				WHERE 1=1 {$where_clause} 
				AND ii.amount IS NOT NULL 
				AND o.progress >= 5 
				AND o.progress != 7 
				GROUP BY o.client_id 
				ORDER BY total_sales DESC 
				LIMIT 5";
			$client_results = $wpdb->get_results( $client_query );

			$content = '<div style="background:#f8f9fa;padding:20px;border-radius:8px;margin-bottom:24px;">';
			$content .= '<h4 style="margin:0 0 16px 0;">売上TOP5顧客</h4>';
			$content .= '<div style="display:grid;gap:12px;">';

			if ( empty( $client_results ) ) {
				$content .= '<p style="margin:0;padding:12px;background:#fff;border-radius:6px;color:#666;">' . esc_html__( '該当期間に売上データがありません。期間を変更するか、請求済以降の案件・請求項目を登録してください。', 'ktpwp' ) . '</p>';
			} else {
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

			$period = isset( $_GET['period'] ) ? sanitize_text_field( $_GET['period'] ) : 'all_time';
			$where_clause = $this->get_period_where_clause( $period );

			// サービス別売上TOP5（請求済以降の進捗状況の案件のみ）
			$service_query = "SELECT COALESCE(ii.product_name, '（未設定）') AS service_name, SUM(ii.amount) AS total_sales, COUNT(DISTINCT o.id) AS order_count 
				FROM {$wpdb->prefix}ktp_order o 
				LEFT JOIN {$wpdb->prefix}ktp_order_invoice_items ii ON o.id = ii.order_id 
				WHERE 1=1 {$where_clause} 
				AND ii.amount IS NOT NULL 
				AND o.progress >= 5 
				AND o.progress != 7 
				GROUP BY ii.product_name 
				ORDER BY total_sales DESC 
				LIMIT 5";
			$service_results = $wpdb->get_results( $service_query );

			$content = '<div style="background:#f8f9fa;padding:20px;border-radius:8px;margin-bottom:24px;">';
			$content .= '<h4 style="margin:0 0 16px 0;">売上TOP5サービス</h4>';
			$content .= '<div style="display:grid;gap:12px;">';

			if ( empty( $service_results ) ) {
				$content .= '<p style="margin:0;padding:12px;background:#fff;border-radius:6px;color:#666;">' . esc_html__( '該当期間に売上データがありません。期間を変更するか、請求済以降の案件・請求項目を登録してください。', 'ktpwp' ) . '</p>';
			} else {
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

			$period = isset( $_GET['period'] ) ? sanitize_text_field( $_GET['period'] ) : 'all_time';
			$where_clause = $this->get_period_where_clause( $period );

			// 協力会社別貢献度TOP5（請求済以降の進捗状況の案件のみ）
			$supplier_query = "SELECT COALESCE(s.company_name, '（協力会社未設定）') AS company_name, COUNT(DISTINCT o.id) AS order_count, SUM(oci.amount) AS total_contribution 
				FROM {$wpdb->prefix}ktp_order o 
				LEFT JOIN {$wpdb->prefix}ktp_order_cost_items oci ON o.id = oci.order_id 
				LEFT JOIN {$wpdb->prefix}ktp_supplier s ON oci.supplier_id = s.id 
				WHERE 1=1 {$where_clause} 
				AND oci.supplier_id IS NOT NULL 
				AND o.progress >= 5 
				AND o.progress != 7 
				GROUP BY s.id 
				ORDER BY total_contribution DESC 
				LIMIT 5";
			$supplier_results = $wpdb->get_results( $supplier_query );

			$content = '<div style="background:#f8f9fa;padding:20px;border-radius:8px;margin-bottom:24px;">';
			$content .= '<h4 style="margin:0 0 16px 0;">貢献度TOP5協力会社</h4>';
			$content .= '<div style="display:grid;gap:12px;">';

			if ( empty( $supplier_results ) ) {
				$content .= '<p style="margin:0;padding:12px;background:#fff;border-radius:6px;color:#666;">' . esc_html__( '該当期間に貢献度データがありません。期間を変更するか、請求済以降の案件に協力会社・原価項目を登録してください。', 'ktpwp' ) . '</p>';
			} else {
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
			}

			$content .= '</div></div>';

			return $content;
		}

			/**
	 * Get period description
	 *
	 * @since 1.0.0
	 * @param string $period Period type
	 * @return string Period description
	 */
	private function get_period_description( $period ) {
		$periods = array(
			'all_time' => '全期間',
			'this_year' => '今年',
			'last_year' => '去年',
			'this_month' => '今月',
			'last_month' => '先月',
			'last_3_months' => '過去3ヶ月',
			'last_6_months' => '過去6ヶ月',
			'current_year' => '今年',
			'current_month' => '今月'
		);

		return isset( $periods[ $period ] ) ? $periods[ $period ] : '全期間';
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
			case 'this_year':
				$where_clause = " AND YEAR(o.created_at) = YEAR(CURDATE())";
				break;
			case 'last_year':
				$where_clause = " AND YEAR(o.created_at) = YEAR(CURDATE()) - 1";
				break;
			case 'current_month':
			case 'this_month':
				$where_clause = " AND YEAR(o.created_at) = YEAR(CURDATE()) AND MONTH(o.created_at) = MONTH(CURDATE())";
				break;
			case 'last_month':
				$where_clause = " AND YEAR(o.created_at) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND MONTH(o.created_at) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))";
				break;
			case 'last_3_months':
				$where_clause = " AND o.created_at >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)";
				break;
			case 'last_6_months':
				$where_clause = " AND o.created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)";
				break;
			case 'all_time':
			default:
				$where_clause = "";
				break;
		}

		return $where_clause;
	}

	/**
	 * Render tax return report
	 *
	 * @since 1.0.0
	 * @return string HTML content
	 */
	private function render_tax_return_report() {
		$content = '<div class="tax-return-report">';
		$content .= '<h3 style="margin-top:0;margin-bottom:24px;color:#333;">確定申告用</h3>';

		// 年度選択
		$content .= $this->render_tax_year_selector();

		// 売上台帳セクション
		$content .= $this->render_sales_ledger_section();

		// その他の確定申告関連機能（将来拡張用）
		$content .= $this->render_tax_return_features();

		$content .= '</div>';

		return $content;
	}

	/**
	 * Render tax year selector
	 *
	 * @since 1.0.0
	 * @return string HTML content
	 */
	private function render_tax_year_selector() {
		$current_year = isset( $_GET['tax_year'] ) ? intval( $_GET['tax_year'] ) : date('Y');
		$start_year = 2020; // 開始年
		$end_year = date('Y') + 1; // 来年まで

		$content = '<div style="margin-bottom:24px;padding:16px;background:#f8f9fa;border-radius:8px;">';
		$content .= '<h4 style="margin:0 0 12px 0;">対象年度選択</h4>';
		$content .= '<div style="display:flex;flex-wrap:wrap;gap:8px;">';

		for ( $year = $end_year; $year >= $start_year; $year-- ) {
			$active_class = ( $current_year === $year ) ? 'style="background:#1976d2;color:#fff;"' : 'style="background:#fff;color:#333;"';
			$url = add_query_arg( array( 'tab_name' => 'report', 'report_type' => 'tax_return', 'tax_year' => $year ) );
			
			$content .= '<a href="' . esc_url( $url ) . '" class="year-btn" ' . $active_class . ' style="padding:6px 12px;border-radius:4px;text-decoration:none;border:1px solid #ddd;font-size:14px;transition:all 0.3s;">';
			$content .= esc_html( $year . '年' );
			$content .= '</a>';
		}

		$content .= '</div></div>';

		return $content;
	}

	/**
	 * Render sales ledger section
	 *
	 * @since 1.0.0
	 * @return string HTML content
	 */
	private function render_sales_ledger_section() {
		global $wpdb;

		$tax_year = isset( $_GET['tax_year'] ) ? intval( $_GET['tax_year'] ) : date('Y');
		
		// 売上台帳データを取得
		$sales_data = $this->get_sales_ledger_data( $tax_year );

		$content = '<div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:20px;margin-bottom:24px;">';
		$content .= '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">';
		$content .= '<h4 style="margin:0;color:#333;">売上台帳（' . esc_html( $tax_year ) . '年）</h4>';
		
		// 印刷ボタン
		$content .= '<button type="button" id="sales-ledger-pdf-btn" data-year="' . esc_attr( $tax_year ) . '" style="
			background:#1976d2;
			color:#fff;
			border:none;
			padding:10px 20px;
			border-radius:4px;
			cursor:pointer;
			font-size:14px;
			display:flex;
			align-items:center;
			gap:8px;
		">';
		$content .= '<span style="font-size:16px;">🖨️</span>';
		$content .= '印刷';
		$content .= '</button>';
		
		$content .= '</div>';

		// 売上サマリー
		$total_sales = array_sum( array_column( $sales_data, 'total_amount' ) );
		$total_orders = count( $sales_data );

		$content .= '<div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:16px;margin-bottom:20px;">';
		
		$content .= '<div style="background:linear-gradient(135deg, #43a047 0%, #66bb6a 100%);color:#fff;padding:16px;border-radius:6px;text-align:center;">';
		$content .= '<div style="font-size:14px;margin-bottom:4px;">年間売上合計</div>';
		$content .= '<div style="font-size:20px;font-weight:bold;">¥' . number_format( $total_sales ) . '</div>';
		$content .= '</div>';

		$content .= '<div style="background:linear-gradient(135deg, #1976d2 0%, #42a5f5 100%);color:#fff;padding:16px;border-radius:6px;text-align:center;">';
		$content .= '<div style="font-size:14px;margin-bottom:4px;">売上件数</div>';
		$content .= '<div style="font-size:20px;font-weight:bold;">' . number_format( $total_orders ) . '件</div>';
		$content .= '</div>';

		$content .= '</div>';

		// 売上台帳テーブル（プレビュー版）
		$content .= '<div style="margin-top:20px;">';
		$content .= '<div style="background:#f5f5f5;padding:12px;border-radius:4px;margin-bottom:12px;">';
		$content .= '<strong>📋 売上台帳プレビュー</strong>（最新10件）';
		$content .= '</div>';

		if ( ! empty( $sales_data ) ) {
			$content .= '<div style="overflow-x:auto;">';
			$content .= '<table style="width:100%;border-collapse:collapse;font-size:16px;line-height:1.6;">';
			$content .= '<thead>';
			$content .= '<tr style="background:#f8f9fa;">';
			$content .= '<th style="border:1px solid #ddd;padding:14px 12px;text-align:left;font-weight:bold;font-size:16px;">日付</th>';
			$content .= '<th style="border:1px solid #ddd;padding:14px 12px;text-align:left;font-weight:bold;font-size:16px;">顧客名</th>';
			$content .= '<th style="border:1px solid #ddd;padding:14px 12px;text-align:left;font-weight:bold;font-size:16px;">案件名</th>';
			$content .= '<th style="border:1px solid #ddd;padding:14px 12px;text-align:right;font-weight:bold;font-size:16px;">売上金額</th>';
			$content .= '<th style="border:1px solid #ddd;padding:14px 12px;text-align:center;font-weight:bold;font-size:16px;">進捗</th>';
			$content .= '</tr>';
			$content .= '</thead>';
			$content .= '<tbody>';

			// 最新10件のみ表示
			$preview_data = array_slice( $sales_data, 0, 10 );
			
			foreach ( $preview_data as $row ) {
				$content .= '<tr style="border-bottom:1px solid #f0f0f0;">';
				$content .= '<td style="border:1px solid #ddd;padding:12px;font-size:15px;">' . esc_html( $row['date'] ) . '</td>';
				$content .= '<td style="border:1px solid #ddd;padding:12px;font-size:15px;">' . esc_html( $row['client_name'] ) . '</td>';
				$content .= '<td style="border:1px solid #ddd;padding:12px;font-size:15px;">' . esc_html( $row['order_title'] ) . '</td>';
				$content .= '<td style="border:1px solid #ddd;padding:12px;text-align:right;font-weight:bold;color:#1976d2;font-size:16px;">¥' . number_format( $row['total_amount'] ) . '</td>';
				$content .= '<td style="border:1px solid #ddd;padding:12px;text-align:center;font-size:15px;">' . esc_html( $this->get_progress_label( $row['progress'] ) ) . '</td>';
				$content .= '</tr>';
			}

			$content .= '</tbody>';
			$content .= '</table>';
			$content .= '</div>';

			if ( count( $sales_data ) > 10 ) {
				$content .= '<div style="text-align:center;margin-top:12px;color:#666;font-size:14px;">';
				$content .= '※ 全' . count( $sales_data ) . '件中、最新10件を表示。全件は印刷プレビューでご確認ください。';
				$content .= '</div>';
			}
		} else {
			$content .= '<div style="text-align:center;padding:40px;color:#666;">';
			$content .= '対象年度の売上データがありません。';
			$content .= '</div>';
		}

		$content .= '</div>';
		$content .= '</div>';

		return $content;
	}

	/**
	 * Render tax return features
	 *
	 * @since 1.0.0
	 * @return string HTML content
	 */
	private function render_tax_return_features() {
		$content = '<div style="background:#e3f2fd;border-left:4px solid #2196f3;padding:16px;border-radius:4px;">';
		$content .= '<h4 style="margin:0 0 12px 0;color:#1976d2;">📊 確定申告サポート機能</h4>';
		$content .= '<div style="color:#333;line-height:1.6;">';
		$content .= '<ul style="margin:0;padding-left:20px;">';
		$content .= '<li><strong>売上台帳印刷</strong>：年度別の売上データを帳簿形式で印刷</li>';
		$content .= '<li><strong>税務署提出対応</strong>：確定申告に必要な売上情報を整理</li>';
		$content .= '<li><strong>月別集計</strong>：月ごとの売上推移を確認可能</li>';
		$content .= '<li><strong>顧客別売上</strong>：主要取引先の売上内訳を把握</li>';
		$content .= '</ul>';
		$content .= '</div>';
		$content .= '</div>';

		return $content;
	}

	/**
	 * Get sales ledger data for tax return
	 *
	 * @since 1.0.0
	 * @param int $year Target year
	 * @return array Sales data
	 */
	private function get_sales_ledger_data( $year ) {
		global $wpdb;

		// 売上台帳用のデータを取得（請求済以降の進捗状況の案件のみ）
		$query = "SELECT 
			o.id,
			o.project_name as order_title,
			o.created_at as date,
			o.progress,
			o.customer_name as client_name,
			COALESCE(SUM(ii.amount), 0) as total_amount
		FROM {$wpdb->prefix}ktp_order o
		LEFT JOIN {$wpdb->prefix}ktp_order_invoice_items ii ON o.id = ii.order_id
		WHERE YEAR(o.created_at) = %d
		AND o.progress >= 5
		AND o.progress != 7
		AND ii.amount IS NOT NULL
		GROUP BY o.id
		ORDER BY o.created_at DESC";

		$results = $wpdb->get_results( $wpdb->prepare( $query, $year ), ARRAY_A );

		// データを整形
		$sales_data = array();
		foreach ( $results as $row ) {
			$sales_data[] = array(
				'id' => $row['id'],
				'date' => date( 'Y-m-d', strtotime( $row['date'] ) ),
				'client_name' => $row['client_name'] ?: '未設定',
				'order_title' => $row['order_title'] ?: '無題',
				'total_amount' => floatval( $row['total_amount'] ),
				'progress' => intval( $row['progress'] )
			);
		}

		return $sales_data;
	}

	/**
	 * Get progress label
	 *
	 * @since 1.0.0
	 * @param int $progress Progress status
	 * @return string Progress label
	 */
	private function get_progress_label( $progress ) {
		$labels = array(
			1 => '受注',
			2 => '進行中',
			3 => '完了',
			4 => '完了',
			5 => '請求済',
			6 => '支払済',
			7 => 'ボツ',
			8 => '見積中'
		);

		return isset( $labels[ $progress ] ) ? $labels[ $progress ] : '不明';
	}


	}
} // class_exists
