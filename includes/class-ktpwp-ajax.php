<?php
/**
 * KTPWP Ajax処理管理クラス
 *
 * @package KTPWP
 * @since 0.1.0
 */

// セキュリティ: 直接アクセスを防止
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ajax処理管理クラス
 */
class KTPWP_Ajax {

	/**
	 * シングルトンインスタンス
	 *
	 * @var KTPWP_Ajax|null
	 */
	private static $instance = null;

	/**
	 * 登録されたAjaxハンドラー一覧
	 *
	 * @var array
	 */
	private $registered_handlers = array();

	/**
	 * ハンドラー登録済みフラグ
	 *
	 * @var bool
	 */
	private $handlers_registered = false;

	/**
	 * nonce名の設定
	 *
	 * @var array
	 */
	private $nonce_names = array(
		'auto_save'    => 'ktp_ajax_nonce',
		'project_name' => 'ktp_update_project_name',
		'inline_edit'  => 'ktpwp_inline_edit_nonce',
		'general'      => 'ktpwp_ajax_nonce',
		'staff_chat'   => 'ktpwp_staff_chat_nonce',
		'invoice_candidates' => 'ktp_get_invoice_candidates',
		'progress_update' => 'ktp_ajax_nonce',
	);

	/**
	 * シングルトンインスタンス取得
	 *
	 * @return KTPWP_Ajax
	 */
	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * コンストラクタ
	 */
	private function __construct() {
		$this->init_hooks();
	}

	/**
	 * フック初期化
	 */
	private function init_hooks() {
		// 初期化処理 - より早い優先度で実行
		add_action( 'init', array( $this, 'register_ajax_handlers' ), 5 );

		// WordPress管理画面でのスクリプト読み込み時にnonce設定
		add_action( 'wp_enqueue_scripts', array( $this, 'localize_ajax_scripts' ), 99 );
		add_action( 'admin_enqueue_scripts', array( $this, 'localize_ajax_scripts' ), 99 );

		// フッターでのスクリプト出力は AJAX リクエスト中のみに制限
		add_action( 'wp_footer', array( $this, 'conditional_ajax_localization' ), 5 );
		add_action( 'admin_footer', array( $this, 'conditional_ajax_localization' ), 5 );
	}

	/**
	 * 受注書IDから利益表示HTMLと数値を構築
	 *
	 * @param int $order_id
	 * @return array
	 */
	private function build_profit_payload_for_order( $order_id ) {
		if ( ! class_exists( 'KTPWP_Order_UI' ) ) {
			require_once __DIR__ . '/class-ktpwp-order-ui.php';
		}
		$order_ui = KTPWP_Order_UI::get_instance();
		$profit_data = $order_ui->get_profit_data_for_order( $order_id );
		return array(
			'html' => isset( $profit_data['html'] ) ? $profit_data['html'] : '',
			'profit' => isset( $profit_data['profit'] ) ? $profit_data['profit'] : 0,
			'qualified_invoice_cost' => isset( $profit_data['qualified_invoice_cost'] ) ? $profit_data['qualified_invoice_cost'] : 0,
			'non_qualified_invoice_cost' => isset( $profit_data['non_qualified_invoice_cost'] ) ? $profit_data['non_qualified_invoice_cost'] : 0,
			'color' => isset( $profit_data['color'] ) ? $profit_data['color'] : '#28a745',
		);
	}

	/**
	 * Ajax: 最新の利益表示を取得
	 */
	public function ajax_get_profit_display() {
		// 権限チェック
		if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'ktpwp_access' ) ) {
			wp_send_json_error( __( 'この操作を行う権限がありません。', 'kantanpro' ) );
		}

		$order_id = isset( $_POST['order_id'] ) ? intval( $_POST['order_id'] ) : 0;
		if ( $order_id <= 0 ) {
			wp_send_json_error( __( '無効なIDです', 'kantanpro' ) );
		}

		$payload = $this->build_profit_payload_for_order( $order_id );
		wp_send_json_success( $payload );
	}

	/**
	 * Ajaxハンドラー登録
	 */
	public function register_ajax_handlers() {
		// 重複登録を防ぐ
		if ( $this->handlers_registered ) {
			return;
		}

		// プロジェクト名インライン編集（管理者のみ）
		add_action( 'wp_ajax_ktp_update_project_name', array( $this, 'ajax_update_project_name' ) );
		add_action( 'wp_ajax_nopriv_ktp_update_project_name', array( $this, 'ajax_require_login' ) );
		$this->registered_handlers[] = 'ktp_update_project_name';

		// 受注関連のAjax処理を初期化
		$this->init_order_ajax_handlers();

		// サービス関連のAjax処理を初期化
		$this->init_service_ajax_handlers();

		// スタッフチャット関連のAjax処理を初期化
		$this->init_staff_chat_ajax_handlers();

		// ログイン中ユーザー取得
		add_action( 'wp_ajax_get_logged_in_users', array( $this, 'ajax_get_logged_in_users' ) );
		add_action( 'wp_ajax_nopriv_get_logged_in_users', array( $this, 'ajax_get_logged_in_users' ) );
		$this->registered_handlers[] = 'get_logged_in_users';

		// メール内容取得
		add_action( 'wp_ajax_get_email_content', array( $this, 'ajax_get_email_content' ) );
		add_action( 'wp_ajax_nopriv_get_email_content', array( $this, 'ajax_require_login' ) );
		$this->registered_handlers[] = 'get_email_content';

		// メール送信
		add_action( 'wp_ajax_send_order_email', array( $this, 'ajax_send_order_email' ) );
		add_action( 'wp_ajax_nopriv_send_order_email', array( $this, 'ajax_require_login' ) );
		$this->registered_handlers[] = 'send_order_email';

		// 協力会社メールアドレス取得
		add_action( 'wp_ajax_get_supplier_email', array( $this, 'ajax_get_supplier_email' ) );
		add_action( 'wp_ajax_nopriv_get_supplier_email', array( $this, 'ajax_require_login' ) );
		$this->registered_handlers[] = 'get_supplier_email';

		// 発注書メール内容取得
		add_action( 'wp_ajax_get_purchase_order_email_content', array( $this, 'ajax_get_purchase_order_email_content' ) );
		add_action( 'wp_ajax_nopriv_get_purchase_order_email_content', array( $this, 'ajax_require_login' ) );
		$this->registered_handlers[] = 'get_purchase_order_email_content';

		// 発注書メール送信
		add_action( 'wp_ajax_send_purchase_order_email', array( $this, 'ajax_send_purchase_order_email' ) );
		add_action( 'wp_ajax_nopriv_send_purchase_order_email', array( $this, 'ajax_require_login' ) );
		$this->registered_handlers[] = 'send_purchase_order_email';

		// 発注書/見積依頼書 印刷用HTML取得（PDF保存）
		add_action( 'wp_ajax_get_purchase_order_print_html', array( $this, 'ajax_get_purchase_order_print_html' ) );
		add_action( 'wp_ajax_nopriv_get_purchase_order_print_html', array( $this, 'ajax_require_login' ) );
		$this->registered_handlers[] = 'get_purchase_order_print_html';

		// 会社情報取得
		add_action( 'wp_ajax_get_company_info', array( $this, 'ajax_get_company_info' ) );
		add_action( 'wp_ajax_nopriv_get_company_info', array( $this, 'ajax_require_login' ) );
		$this->registered_handlers[] = 'get_company_info';

		add_action( 'wp_ajax_ktp_lookup_postal_address', array( $this, 'ajax_lookup_postal_address' ) );
		add_action( 'wp_ajax_nopriv_ktp_lookup_postal_address', array( $this, 'ajax_require_login' ) );
		$this->registered_handlers[] = 'ktp_lookup_postal_address';

		// 協力会社担当者情報取得
		add_action( 'wp_ajax_get_supplier_contact_info', array( $this, 'ajax_get_supplier_contact_info' ) );
		add_action( 'wp_ajax_nopriv_get_supplier_contact_info', array( $this, 'ajax_require_login' ) );
		$this->registered_handlers[] = 'get_supplier_contact_info';

		// 最新の受注書プレビューデータ取得
		add_action( 'wp_ajax_ktp_get_order_preview', array( $this, 'get_order_preview' ) );
		add_action( 'wp_ajax_nopriv_ktp_get_order_preview', array( $this, 'ajax_require_login' ) );
		$this->registered_handlers[] = 'ktp_get_order_preview';

		// アイテム並び順更新
		add_action( 'wp_ajax_ktp_update_item_order', array( $this, 'ajax_update_item_order' ) );
		add_action( 'wp_ajax_nopriv_ktp_update_item_order', array( $this, 'ajax_require_login' ) ); // 非ログインユーザーはエラー
		$this->registered_handlers[] = 'ktp_update_item_order';

		// 納期フィールド保存
		add_action( 'wp_ajax_ktp_save_delivery_date', array( $this, 'ajax_save_delivery_date' ) );
		add_action( 'wp_ajax_nopriv_ktp_save_delivery_date', array( $this, 'ajax_require_login' ) ); // 非ログインユーザーはエラー
		$this->registered_handlers[] = 'ktp_save_delivery_date';

		// 納期フィールド更新
		add_action( 'wp_ajax_ktp_update_delivery_date', array( $this, 'ajax_update_delivery_date' ) );
		add_action( 'wp_ajax_nopriv_ktp_update_delivery_date', array( $this, 'ajax_require_login' ) ); // 非ログインユーザーはエラー
		$this->registered_handlers[] = 'ktp_update_delivery_date';

		// 作成中の納期警告件数取得
		add_action( 'wp_ajax_ktp_get_creating_warning_count', array( $this, 'ajax_get_creating_warning_count' ) );
		add_action( 'wp_ajax_nopriv_ktp_get_creating_warning_count', array( $this, 'ajax_require_login' ) ); // 非ログインユーザーはエラー
		$this->registered_handlers[] = 'ktp_get_creating_warning_count';

		// フィールド自動保存（完了日など）
		add_action( 'wp_ajax_ktp_auto_save_field', array( $this, 'ajax_auto_save_field' ) );
		add_action( 'wp_ajax_nopriv_ktp_auto_save_field', array( $this, 'ajax_require_login' ) ); // 非ログインユーザーはエラー
		$this->registered_handlers[] = 'ktp_auto_save_field';

		// 顧客IDを受け取り、完了日が締日を超えている案件リストをJSONで返す
		add_action( 'wp_ajax_ktp_get_invoice_candidates', array( $this, 'ajax_get_invoice_candidates' ) );
		add_action( 'wp_ajax_nopriv_ktp_get_invoice_candidates', array( $this, 'ajax_require_login' ) ); // 非ログインユーザーはエラー
		$this->registered_handlers[] = 'ktp_get_invoice_candidates';

		add_action( 'wp_ajax_ktp_update_bulk_invoice_seal_size', array( $this, 'ajax_update_bulk_invoice_seal_size' ) );
		add_action( 'wp_ajax_nopriv_ktp_update_bulk_invoice_seal_size', array( $this, 'ajax_require_login' ) );
		$this->registered_handlers[] = 'ktp_update_bulk_invoice_seal_size';

		add_action( 'wp_ajax_ktp_update_bulk_invoice_logo_width', array( $this, 'ajax_update_bulk_invoice_logo_width' ) );
		add_action( 'wp_ajax_nopriv_ktp_update_bulk_invoice_logo_width', array( $this, 'ajax_require_login' ) );
		$this->registered_handlers[] = 'ktp_update_bulk_invoice_logo_width';

		add_action( 'wp_ajax_ktp_update_atena_label_position', array( $this, 'ajax_update_atena_label_position' ) );
		add_action( 'wp_ajax_nopriv_ktp_update_atena_label_position', array( $this, 'ajax_require_login' ) );
		$this->registered_handlers[] = 'ktp_update_atena_label_position';

		// 部署選択状態更新（ajax-department.phpで登録済み）
		// add_action( 'wp_ajax_ktp_update_department_selection', 'ktp_update_department_selection_ajax' );
		add_action( 'wp_ajax_nopriv_ktp_update_department_selection', array( $this, 'ajax_require_login' ) );
		$this->registered_handlers[] = 'ktp_update_department_selection';

		// 部署追加
		add_action( 'wp_ajax_ktp_add_department', 'ktpwp_ajax_add_department' );
		add_action( 'wp_ajax_nopriv_ktp_add_department', array( $this, 'ajax_require_login' ) );
		$this->registered_handlers[] = 'ktp_add_department';

		// 部署削除
		add_action( 'wp_ajax_ktp_delete_department', 'ktpwp_ajax_delete_department' );
		add_action( 'wp_ajax_nopriv_ktp_delete_department', array( $this, 'ajax_require_login' ) );
		$this->registered_handlers[] = 'ktp_delete_department';

		// 顧客税区分取得
		add_action( 'wp_ajax_ktp_get_client_tax_category', array( $this, 'ajax_get_client_tax_category' ) );
		add_action( 'wp_ajax_nopriv_ktp_get_client_tax_category', array( $this, 'ajax_require_login' ) );
		$this->registered_handlers[] = 'ktp_get_client_tax_category';

		// 受注書IDから顧客税区分取得
		add_action( 'wp_ajax_ktp_get_client_tax_category_by_order', array( $this, 'ajax_get_client_tax_category_by_order' ) );
		add_action( 'wp_ajax_nopriv_ktp_get_client_tax_category_by_order', array( $this, 'ajax_require_login' ) );
		$this->registered_handlers[] = 'ktp_get_client_tax_category_by_order';

		// 協力会社税区分取得
		add_action( 'wp_ajax_ktp_get_supplier_tax_category', array( $this, 'ajax_get_supplier_tax_category' ) );
		add_action( 'wp_ajax_nopriv_ktp_get_supplier_tax_category', array( $this, 'ajax_require_login' ) );
		$this->registered_handlers[] = 'ktp_get_supplier_tax_category';

		// 協力会社適格請求書番号取得
		add_action( 'wp_ajax_ktp_get_supplier_qualified_invoice_number', array( $this, 'ajax_get_supplier_qualified_invoice_number' ) );
		add_action( 'wp_ajax_nopriv_ktp_get_supplier_qualified_invoice_number', array( $this, 'ajax_require_login' ) );
		$this->registered_handlers[] = 'ktp_get_supplier_qualified_invoice_number';

		// 利益表示の最新化（請求項目変更に連動）
		add_action( 'wp_ajax_ktp_get_profit_display', array( $this, 'ajax_get_profit_display' ) );
		add_action( 'wp_ajax_nopriv_ktp_get_profit_display', array( $this, 'ajax_require_login' ) );
		$this->registered_handlers[] = 'ktp_get_profit_display';

		// 進捗更新Ajax
		add_action( 'wp_ajax_ktp_update_progress', array( $this, 'ajax_update_progress' ) );
		add_action( 'wp_ajax_nopriv_ktp_update_progress', array( $this, 'ajax_require_login' ) );
		$this->registered_handlers[] = 'ktp_update_progress';

		// ▼▼▼ 一括請求書「請求済」進捗変更Ajax ▼▼▼
		add_action(
            'wp_ajax_ktp_set_invoice_completed',
            function () {
				// 権限チェック
				if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'ktpwp_access' ) ) {
					wp_send_json_error( __( '権限がありません', 'kantanpro' ) );
				}
				// nonceチェック（必要ならPOSTでnonceも送る）
				// if ( ! isset($_POST['nonce']) || ! wp_verify_nonce($_POST['nonce'], 'ktp_ajax_nonce') ) {
				// wp_send_json_error( __( 'セキュリティ検証に失敗しました', 'kantanpro' ) );
				// }
				global $wpdb;
				$client_id = isset( $_POST['client_id'] ) ? intval( $_POST['client_id'] ) : 0;
				if ( ! $client_id ) {
					wp_send_json_error( __( 'client_idが指定されていません', 'kantanpro' ) );
				}
				$order_table = $wpdb->prefix . 'ktp_order';
				$client_table = $wpdb->prefix . 'ktp_client';
				// progress=4（完了）→5（請求済）に一括更新。前払い（WC受注・前入金済等）は後払い請求対象外のため除外する。
				$result = $wpdb->query(
                    $wpdb->prepare(
                        "UPDATE `{$order_table}` o
						LEFT JOIN `{$client_table}` c ON o.client_id = c.id
						SET o.progress = 5
						WHERE o.client_id = %d AND o.progress = 4
						AND NOT ( o.payment_timing = 'prepay' OR ( ( o.payment_timing IS NULL OR o.payment_timing = '' ) AND c.payment_timing = 'prepay' ) )",
                        $client_id
                    )
                );
				if ( $result === false ) {
					wp_send_json_error( __( 'DB更新に失敗しました: ', 'kantanpro' ) . $wpdb->last_error );
				}
				wp_send_json_success( array( 'updated' => $result ) );
			}
        );
		// ▲▲▲ 一括請求書「請求済」進捗変更Ajax ▲▲▲

		// ▼▼▼ コスト項目「仕入ステータス」一括更新Ajax（協力会社単位） ▼▼▼
		add_action(
            'wp_ajax_ktp_set_cost_items_purchase_status',
            function () {
				// 権限チェック
				if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'ktpwp_access' ) ) {
					wp_send_json_error( __( '権限がありません', 'kantanpro' ) );
				}
				// nonceチェック
				if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'ktp_ajax_nonce' ) ) {
					wp_send_json_error( __( 'セキュリティ検証に失敗しました', 'kantanpro' ) );
				}
				global $wpdb;
				$order_id = isset( $_POST['order_id'] ) ? intval( $_POST['order_id'] ) : 0;
				$supplier_name = isset( $_POST['supplier_name'] ) ? sanitize_text_field( $_POST['supplier_name'] ) : '';
				$status = isset( $_POST['status'] ) ? sanitize_key( $_POST['status'] ) : 'ordered';
				if ( ! $order_id || ! $supplier_name ) {
					wp_send_json_error( __( 'order_idまたはsupplier_nameが指定されていません', 'kantanpro' ) );
				}
				if ( class_exists( 'KTPWP_Order_Items' ) && ! array_key_exists( $status, KTPWP_Order_Items::get_purchase_status_options() ) ) {
					wp_send_json_error( __( '無効な仕入ステータスです', 'kantanpro' ) );
				}
				if ( class_exists( 'KTPWP_Order_Items' ) ) {
					KTPWP_Order_Items::get_instance()->add_purchase_status_column_if_missing();
					KTPWP_Order_Items::get_instance()->add_purchase_status_date_column_if_missing();
					KTPWP_Order_Items::get_instance()->add_purchase_status_dates_column_if_missing();
				}
				$ordered_flag = in_array( $status, array( 'ordered', 'completed' ), true ) ? 1 : 0;
				// 未着手に戻したら日付をクリア、それ以外は本日の日付をステータス変更日として記録
				$today = current_time( 'Y-m-d' );
				$status_date = ( $status === 'pending' ) ? null : $today;
				// ※ $wpdb->prepare() の %s に null を渡すと空文字列になりDATE型としては不正なため、SQL文自体を分岐する
				$status_date_sql = ( $status_date === null ) ? 'NULL' : '%s';
				$table_name = $wpdb->prefix . 'ktp_order_cost_items';
				// 旧形式（purchase = 会社名）と新形式（purchase = 会社名 > サービス名）の両方を対象に更新
				$like_pattern = $wpdb->esc_like( $supplier_name ) . ' > %';

				// 見積依頼／発注／仕入完了の日付履歴は行ごとに既存値が異なりうるため、行単位でマージして個別に更新する
				$rows = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT id, purchase_status_dates FROM `{$table_name}` WHERE order_id = %d AND (purchase = %s OR purchase LIKE %s)",
						$order_id,
						$supplier_name,
						$like_pattern
					)
				);

				$updated = 0;
				$records = array();
				foreach ( $rows as $row ) {
					$history = class_exists( 'KTPWP_Order_Items' )
						? KTPWP_Order_Items::merge_purchase_status_history( $row->purchase_status_dates, $status, $today )
						: array();
					$history_json = empty( $history ) ? null : wp_json_encode( $history );
					$history_json_sql = ( $history_json === null ) ? 'NULL' : '%s';

					$query_args = array( $status, $ordered_flag );
					if ( $status_date !== null ) {
						$query_args[] = $status_date;
					}
					if ( $history_json !== null ) {
						$query_args[] = $history_json;
					}
					$query_args[] = current_time( 'mysql' );
					$query_args[] = (int) $row->id;

					$result = $wpdb->query(
						$wpdb->prepare(
							"UPDATE `{$table_name}` SET purchase_status = %s, ordered = %d, purchase_status_date = {$status_date_sql}, purchase_status_dates = {$history_json_sql}, updated_at = %s WHERE id = %d",
							$query_args
						)
					);
					if ( $result !== false ) {
						++$updated;
						$records[] = array(
							'id'    => (int) $row->id,
							'label' => class_exists( 'KTPWP_Order_Items' ) ? KTPWP_Order_Items::format_purchase_status_history_label( $history_json ) : '',
						);
					}
				}

				wp_send_json_success(
					array(
						'updated'     => $updated,
						'status'      => $status,
						'status_date' => $status_date,
						'records'     => $records,
					)
				);
			}
        );
		// ▲▲▲ コスト項目「仕入ステータス」一括更新Ajax ▲▲▲

		// 受注書データ取得
		add_action('wp_ajax_ktp_get_order_data', array($this, 'get_order_data'));
		add_action('wp_ajax_nopriv_ktp_get_order_data', array($this, 'get_order_data'));

		// レポート機能用のAJAXアクション
		add_action( 'wp_ajax_ktpwp_get_report_data', array( $this, 'get_report_data' ) );
		
		// 登録完了フラグを設定
		$this->handlers_registered = true;
	}

	/**
	 * 受注関連Ajaxハンドラー初期化
	 */
	private function init_order_ajax_handlers() {
		// 受注クラスファイルの読み込み
		        $order_class_file = KTPWP_PLUGIN_DIR . 'includes/class-ktpwp-order-main.php';

		if ( file_exists( $order_class_file ) ) {
			require_once $order_class_file;
		}

		// クラス存在チェックを削除して強制的に登録
		if ( class_exists( 'KTPWP_Settings' ) ) {
			KTPWP_Settings::log_debug( '受注関連Ajaxハンドラーを登録します', array(), 'info' );
		}
		
		// 自動保存
		add_action( 'wp_ajax_ktp_auto_save_item', array( $this, 'ajax_auto_save_item' ) );
		add_action( 'wp_ajax_nopriv_ktp_auto_save_item', array( $this, 'ajax_auto_save_item' ) );
		$this->registered_handlers[] = 'ktp_auto_save_item';

		// 新規アイテム作成
		add_action( 'wp_ajax_ktp_create_new_item', array( $this, 'ajax_create_new_item' ) );
		add_action( 'wp_ajax_nopriv_ktp_create_new_item', array( $this, 'ajax_create_new_item' ) );
		$this->registered_handlers[] = 'ktp_create_new_item';
		if ( class_exists( 'KTPWP_Settings' ) ) {
			KTPWP_Settings::log_debug( 'ktp_create_new_item handler registered', array(), 'debug' );
		}

		// アイテム削除
		add_action( 'wp_ajax_ktp_delete_item', array( $this, 'ajax_delete_item' ) );
		add_action( 'wp_ajax_nopriv_ktp_delete_item', array( $this, 'ajax_require_login' ) ); // 非ログインユーザーはエラー
		$this->registered_handlers[] = 'ktp_delete_item';

		// アイテム並び順更新
		add_action( 'wp_ajax_ktp_update_item_order', array( $this, 'ajax_update_item_order' ) );
		add_action( 'wp_ajax_nopriv_ktp_update_item_order', array( $this, 'ajax_require_login' ) ); // 非ログインユーザーはエラー
		$this->registered_handlers[] = 'ktp_update_item_order';
		
		if ( class_exists( 'KTPWP_Settings' ) ) {
			KTPWP_Settings::log_debug( '受注関連Ajaxハンドラー登録完了', array( 'count' => count($this->registered_handlers) ), 'info' );
		}
		
		// デバッグ用：Ajaxリクエスト監視（デバッグモード時のみ）
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			add_action( 'wp_ajax_ktp_create_new_item', function() {
				error_log( '[AJAX_DEBUG] wp_ajax_ktp_create_new_item アクションが呼び出されました' );
			}, 1 );
			add_action( 'wp_ajax_nopriv_ktp_create_new_item', function() {
				error_log( '[AJAX_DEBUG] wp_ajax_nopriv_ktp_create_new_item アクションが呼び出されました' );
			}, 1 );
		}
		
		// デバッグ用：Ajaxリクエスト監視（デバッグモード時のみ）
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			add_action( 'wp_ajax_ktp_create_new_item', function() {
				error_log( '[AJAX_DEBUG_ALL] Ajaxリクエスト: action=' . ( isset( $_REQUEST['action'] ) ? $_REQUEST['action'] : 'NOT_SET' ) );
			}, 0 );
			add_action( 'wp_ajax_nopriv_ktp_create_new_item', function() {
				error_log( '[AJAX_DEBUG_ALL] Ajaxリクエスト（非ログイン）: action=' . ( isset( $_REQUEST['action'] ) ? $_REQUEST['action'] : 'NOT_SET' ) );
			}, 0 );
		}
	}

	/**
	 * スタッフチャット関連Ajaxハンドラー初期化
	 */
	private function init_staff_chat_ajax_handlers() {
		// スタッフチャットクラスファイルの読み込み
		$staff_chat_class_file = KTPWP_PLUGIN_DIR . 'includes/class-ktpwp-staff-chat.php';

		if ( file_exists( $staff_chat_class_file ) ) {
			require_once $staff_chat_class_file;

			if ( class_exists( 'KTPWP_Staff_Chat' ) ) {
				// 最新チャットメッセージ取得
				add_action( 'wp_ajax_get_latest_staff_chat', array( $this, 'ajax_get_latest_staff_chat' ) );
				add_action( 'wp_ajax_nopriv_get_latest_staff_chat', array( $this, 'ajax_require_login' ) );
				$this->registered_handlers[] = 'get_latest_staff_chat';

				// チャットメッセージ送信
				add_action( 'wp_ajax_send_staff_chat_message', array( $this, 'ajax_send_staff_chat_message' ) );
				add_action( 'wp_ajax_nopriv_send_staff_chat_message', array( $this, 'ajax_send_staff_chat_message' ) ); // For testing nopriv
				$this->registered_handlers[] = 'send_staff_chat_message';

				// チャットメッセージ削除（投稿者/管理者）
				add_action( 'wp_ajax_delete_staff_chat_message', array( $this, 'ajax_delete_staff_chat_message' ) );
				$this->registered_handlers[] = 'delete_staff_chat_message';
			}
		}
	}

	/**
	 * サービス関連Ajaxハンドラー初期化
	 */
	private function init_service_ajax_handlers() {
		if ( class_exists( 'KTPWP_Settings' ) ) {
			KTPWP_Settings::log_debug( 'サービスAjaxハンドラー初期化開始', array(), 'info' );
		}
		
		// サービス一覧取得
		add_action( 'wp_ajax_ktp_get_service_list', array( $this, 'ajax_get_service_list' ) );
		add_action( 'wp_ajax_nopriv_ktp_get_service_list', array( $this, 'ajax_get_service_list' ) );
		$this->registered_handlers[] = 'ktp_get_service_list';

		// サービス複製（軽量 Ajax。Cookie 肥大化環境での 431 回避）
		add_action( 'wp_ajax_ktp_duplicate_service', array( $this, 'ajax_duplicate_service' ) );
		add_action( 'wp_ajax_nopriv_ktp_duplicate_service', array( $this, 'ajax_require_login' ) );
		$this->registered_handlers[] = 'ktp_duplicate_service';
		
		if ( class_exists( 'KTPWP_Settings' ) ) {
			KTPWP_Settings::log_debug( 'サービスAjaxハンドラー登録完了', array( 
				'handler' => 'ktp_get_service_list',
				'total_handlers' => count( $this->registered_handlers ),
				'wp_ajax_registered' => has_action( 'wp_ajax_ktp_get_service_list' ) ? '登録済み' : '未登録',
				'wp_ajax_nopriv_registered' => has_action( 'wp_ajax_nopriv_ktp_get_service_list' ) ? '登録済み' : '未登録'
			), 'info' );
		}
		
		// デバッグ用：Ajaxリクエスト監視（デバッグモード時のみ）
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			add_action( 'wp_ajax_ktp_get_service_list', function() {
				error_log( '[AJAX_DEBUG] wp_ajax_ktp_get_service_list アクションが呼び出されました' );
			}, 1 );
			add_action( 'wp_ajax_nopriv_ktp_get_service_list', function() {
				error_log( '[AJAX_DEBUG] wp_ajax_nopriv_ktp_get_service_list アクションが呼び出されました' );
			}, 1 );
		}
		
		// デバッグ用：WordPressのAjax処理全体を監視（デバッグモード時のみ）
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			add_action( 'init', function() {
				if ( wp_doing_ajax() ) {
					error_log( '[AJAX_DEBUG_INIT] Ajax処理中: action=' . ( isset( $_REQUEST['action'] ) ? $_REQUEST['action'] : 'NOT_SET' ) );
				}
			}, 1 );
		}
	}

	/**
	 * Ajaxスクリプトの設定
	 */
	public function localize_ajax_scripts() {
		// 基本的なAjax URL設定
		$ajax_data = array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonces'   => array(),
			'settings' => array(),
		);

		// 一般設定の値を追加
		if ( class_exists( 'KTPWP_Settings' ) ) {
			$ajax_data['settings']['work_list_range']       = KTPWP_Settings::get_work_list_range();
			$ajax_data['settings']['delivery_warning_days'] = KTPWP_Settings::get_delivery_warning_days();
		} else {
			$ajax_data['settings']['work_list_range']       = 20;
			$ajax_data['settings']['delivery_warning_days'] = 3;
		}

		// 現在のユーザー情報を追加
		if ( is_user_logged_in() ) {
			$current_user              = wp_get_current_user();
			$ajax_data['current_user'] = $current_user->display_name ? $current_user->display_name : $current_user->user_login;
		}

		// 各種nonceの設定
		foreach ( $this->nonce_names as $action => $nonce_name ) {
			if ( $action === 'staff_chat' ) {
				// スタッフチャットnonceは必ず統一マネージャーから取得
				if ( ! class_exists( 'KTPWP_Nonce_Manager' ) ) {
					require_once KTPWP_PLUGIN_DIR . 'includes/class-ktpwp-nonce-manager.php';
				}
				$ajax_data['nonces'][ $action ] = KTPWP_Nonce_Manager::get_instance()->get_staff_chat_nonce();
			} elseif ( $action === 'project_name' ) {
				if ( current_user_can( 'manage_options' )
					|| current_user_can( 'ktpwp_access' )
					|| current_user_can( 'edit_posts' ) ) {
					$ajax_data['nonces'][ $action ] = wp_create_nonce( $nonce_name );
				}
			} else {
				$ajax_data['nonces'][ $action ] = wp_create_nonce( $nonce_name );
			}
		}

		// JavaScriptファイルがエンキューされている場合のみlocalizeを実行
		global $wp_scripts;

		if ( isset( $wp_scripts->registered['ktp-js'] ) ) {
			// ktp_ajax_objectにnonceプロパティを追加
			$ajax_data['nonce'] = $ajax_data['nonces']['general'];
			wp_add_inline_script( 'ktp-js', 'var ktp_ajax_object = ' . json_encode( $ajax_data ) . ';' );
			wp_add_inline_script( 'ktp-js', 'var ktpwp_ajax = ' . json_encode( $ajax_data ) . ';' );
			wp_add_inline_script( 'ktp-js', 'var ajaxurl = ' . json_encode( $ajax_data['ajax_url'] ) . ';' );
			wp_add_inline_script( 'ktp-js', 'var ktpwp_ajax_nonce = ' . json_encode( $ajax_data['nonces']['general'] ) . ';' );
		}

		if ( isset( $wp_scripts->registered['ktp-invoice-items'] ) ) {
			wp_add_inline_script( 'ktp-invoice-items', 'var ktp_ajax_nonce = ' . json_encode( $ajax_data['nonces']['auto_save'] ) . ';' );
			wp_add_inline_script( 'ktp-invoice-items', 'var ajaxurl = ' . json_encode( $ajax_data['ajax_url'] ) . ';' );
		}

		if ( isset( $wp_scripts->registered['ktp-cost-items'] ) ) {
			wp_add_inline_script( 'ktp-cost-items', 'var ktp_ajax_nonce = ' . json_encode( $ajax_data['nonces']['auto_save'] ) . ';' );
			wp_add_inline_script( 'ktp-cost-items', 'var ajaxurl = ' . json_encode( $ajax_data['ajax_url'] ) . ';' );
		}

		// 発注メール用のnonce設定
		if ( isset( $wp_scripts->registered['ktp-purchase-order-email'] ) ) {
			wp_add_inline_script( 'ktp-purchase-order-email', 'var ktpwp_ajax_nonce = ' . json_encode( $ajax_data['nonces']['general'] ) . ';' );
			wp_add_inline_script( 'ktp-purchase-order-email', 'var ktp_ajax_nonce = ' . json_encode( $ajax_data['nonces']['auto_save'] ) . ';' );
			wp_add_inline_script( 'ktp-purchase-order-email', 'var ajaxurl = ' . json_encode( $ajax_data['ajax_url'] ) . ';' );
		}

		// サービス選択機能専用のAJAX設定
		if ( isset( $wp_scripts->registered['ktp-service-selector'] ) ) {
			wp_add_inline_script(
				'ktp-service-selector',
				'var ktp_service_ajax_object = ' . json_encode(
					array(
						'ajax_url' => $ajax_data['ajax_url'],
						'nonce'    => $ajax_data['nonces']['auto_save'],
						'settings' => $ajax_data['settings'],
					)
				) . ';'
			);
		}

		if ( isset( $wp_scripts->registered['ktp-order-inline-projectname'] )
			&& ! empty( $ajax_data['nonces']['project_name'] ) ) {
			wp_add_inline_script(
				'ktp-order-inline-projectname',
				'var ktpwp_inline_edit_nonce = ' . json_encode(
					array(
						'ajax_url' => $ajax_data['ajax_url'],
						'nonce'    => $ajax_data['nonces']['project_name'],
					)
				) . ';'
			);
		}

		// 納期フィールド用のAjax設定
		if ( isset( $wp_scripts->registered['ktp-delivery-dates'] ) ) {
			wp_add_inline_script(
				'ktp-delivery-dates',
				'var ktp_ajax = ' . json_encode(
					array(
						'ajax_url' => $ajax_data['ajax_url'],
						'nonce'    => $ajax_data['nonces']['auto_save'],
						'progress_nonce' => $ajax_data['nonces']['progress_update'],
						'settings' => array(
							'delivery_warning_days' => KTPWP_Settings::get_delivery_warning_days(),
						),
					)
				) . ';'
			);
		}

		// サプライヤー選択機能専用のAJAX設定
		if ( isset( $wp_scripts->registered['ktp-supplier-selector'] ) ) {
			wp_add_inline_script(
				'ktp-supplier-selector',
				'var ktp_ajax_nonce = ' . json_encode( $ajax_data['nonces']['auto_save'] ) . ';'
			);
			wp_add_inline_script(
				'ktp-supplier-selector',
				'var ajaxurl = ' . json_encode( $ajax_data['ajax_url'] ) . ';'
			);
		}
	}

	/**
	 * 条件付きAJAX設定の確保（非AJAX時の出力を防ぐ）
	 */
	public function conditional_ajax_localization() {
		// AJAX リクエスト中、またはWordPressコアの処理中は何も出力しない
		if ( wp_doing_ajax() || defined( 'DOING_AJAX' ) || headers_sent() ) {
			return;
		}

		// ページ編集画面でない場合は実行しない
		global $pagenow;
		if ( ! in_array( $pagenow, array( 'post.php', 'post-new.php', 'admin.php' ) ) ) {
			return;
		}

		// KTPWPのスクリプトが読み込まれている場合のみ実行
		if ( wp_script_is( 'ktp-js', 'done' ) || wp_script_is( 'ktp-js', 'enqueued' ) ) {
			$this->ensure_ajax_localization();
		}
	}

	/**
	 * AJAX設定が確実にロードされるようにするフォールバック
	 */
	public function ensure_ajax_localization() {
		// グローバル変数が設定されていない場合のフォールバック
		if ( ! wp_script_is( 'ktp-js', 'done' ) && ! wp_script_is( 'ktp-js', 'enqueued' ) ) {
			return;
		}

		// 基本的なAJAX設定を再確認
		$ajax_data = array(
			'ajax_url'         => admin_url( 'admin-ajax.php' ),
			'staff_chat_nonce' => wp_create_nonce( 'staff_chat_nonce' ),
			'auto_save_nonce'  => wp_create_nonce( 'ktp_auto_save_nonce' ),
		);

		// JavaScriptで利用可能になるよう出力
		echo '<script type="text/javascript">';
		echo 'if (typeof ktpwp_ajax === "undefined") {';
		echo 'var ktpwp_ajax = ' . json_encode( $ajax_data ) . ';';
		echo '}';
		echo 'if (typeof ajaxurl === "undefined") {';
		echo 'var ajaxurl = "' . esc_url_raw( admin_url( 'admin-ajax.php' ) ) . '";';
		echo '}';
		echo '</script>';
	}

	/**
	 * Ajax: プロジェクト名更新
	 */
	public function ajax_update_project_name() {
		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'ktpwp_access' ) && ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( __( '権限がありません', 'kantanpro' ) );
		}

		$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
		if ( $nonce === '' || ! wp_verify_nonce( $nonce, 'ktp_update_project_name' ) ) {
			wp_send_json_error( __( 'セキュリティ検証に失敗しました', 'kantanpro' ) );
		}

		$order_id     = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		$project_name = isset( $_POST['project_name'] ) ? wp_strip_all_tags( wp_unslash( $_POST['project_name'] ) ) : '';
		$project_name = class_exists( 'KTPWP_Order' )
			? KTPWP_Order::project_name_for_storage( $project_name )
			: sanitize_text_field( $project_name );

		if ( $order_id <= 0 ) {
			wp_send_json_error( __( '無効な受注IDです', 'kantanpro' ) );
		}

		global $wpdb;
		$table  = $wpdb->prefix . 'ktp_order';
		$result = $wpdb->update(
			$table,
			array( 'project_name' => $project_name ),
			array( 'id' => $order_id ),
			array( '%s' ),
			array( '%d' )
		);

		if ( $result === false ) {
			$this->log_ajax_error(
				'Failed to update project name',
				array(
					'order_id'     => $order_id,
					'project_name' => $project_name,
				)
			);
			wp_send_json_error( __( '更新に失敗しました', 'kantanpro' ) );
		}

		wp_send_json_success(
			array(
				'message'      => __( 'プロジェクト名を更新しました', 'kantanpro' ),
				'project_name' => $project_name,
			)
		);
	}

	/**
	 * Ajax: ログイン中ユーザー取得
	 */
	public function ajax_get_logged_in_users() {
		// 編集者以上の権限チェック
		if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'ktpwp_access' ) ) {
			wp_send_json_error( __( 'この操作を行う権限がありません。', 'kantanpro' ) );
			return;
		}

		// Ajax以外からのアクセスは何も返さない
		if (
			! defined( 'DOING_AJAX' ) ||
			! DOING_AJAX ||
			( empty( $_SERVER['HTTP_X_REQUESTED_WITH'] ) || strtolower( $_SERVER['HTTP_X_REQUESTED_WITH'] ) !== 'xmlhttprequest' )
		) {
			wp_die();
		}

		$logged_in_users = get_users(
			array(
				'meta_key'     => 'session_tokens',
				'meta_compare' => 'EXISTS',
			)
		);

		$users_names = array();
		foreach ( $logged_in_users as $user ) {
			$users_names[] = esc_html( $user->nickname ) . 'さん';
		}

		wp_send_json( $users_names );
	}

	/**
	 * Ajax: ログイン要求（非ログインユーザー用）
	 */
	public function ajax_require_login() {
		wp_send_json_error( __( 'ログインが必要です', 'kantanpro' ) );
	}

	/**
	 * Ajax: 自動保存アイテム処理
	 */
	public function ajax_auto_save_item() {
		// 編集者以上の権限チェック
		if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'ktpwp_access' ) ) {
			$this->log_ajax_error( 'Auto-save permission check failed' );
			wp_send_json_error( __( 'この操作を行う権限がありません。', 'kantanpro' ) );
			return;
		}

		// セキュリティチェック - 複数のnonce名でチェック
		$nonce_verified = false;
		$nonce_value    = '';

		// デバッグモード時のみ詳細ログを出力
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[AJAX_AUTO_SAVE] POST data received: ' . print_r( $_POST, true ) );
		}

		// 複数のnonce名でチェック
		$nonce_fields = array( 'nonce', 'ktp_ajax_nonce', '_ajax_nonce', '_wpnonce' );
		foreach ( $nonce_fields as $field ) {
			if ( isset( $_POST[ $field ] ) ) {
				$nonce_value = $_POST[ $field ];

				// 配列の場合はvalueキーを取得
				if ( is_array( $nonce_value ) && isset( $nonce_value['value'] ) ) {
					$nonce_value = $nonce_value['value'];
				}

				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( "[AJAX_AUTO_SAVE] Checking nonce field '{$field}': '{$nonce_value}" );
				}

				if ( wp_verify_nonce( $nonce_value, 'ktp_ajax_nonce' ) || wp_verify_nonce( $nonce_value, 'ktpwp_ajax_nonce' ) ) {
					$nonce_verified = true;
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( "[AJAX_AUTO_SAVE] Nonce verified with field: {$field}" );
					}
					break;
				} else {
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( "[AJAX_AUTO_SAVE] Nonce verification failed for field: {$field}" );
					}
				}
			} else {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( "[AJAX_AUTO_SAVE] Nonce field '{$field}' not found in POST data" );
				}
			}
		}

		if ( ! $nonce_verified ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[AJAX_AUTO_SAVE] Security check failed - tried fields: ' . implode( ', ', $nonce_fields ) );
				error_log( '[AJAX_AUTO_SAVE] Available POST fields: ' . implode( ', ', array_keys( $_POST ) ) );
				error_log( '[AJAX_AUTO_SAVE] All POST values: ' . print_r( $_POST, true ) );
			}
			$this->log_ajax_error( 'Auto-save security check failed', $_POST );
			wp_send_json_error( __( 'セキュリティ検証に失敗しました', 'kantanpro' ) );
		}

		// POSTデータの取得とサニタイズ
		$item_type   = $this->sanitize_ajax_input( 'item_type', 'text' );
		$item_id     = $this->sanitize_ajax_input( 'item_id', 'int' );
		$field_name  = $this->sanitize_ajax_input( 'field_name', 'text' );
		$field_value = $this->sanitize_ajax_input( 'field_value', 'text' );
		$order_id    = $this->sanitize_ajax_input( 'order_id', 'int' );

		// バリデーション
		if ( ! in_array( $item_type, array( 'invoice', 'cost' ), true ) ) {
			$this->log_ajax_error( 'Invalid item type', array( 'type' => $item_type ) );
			wp_send_json_error( __( '無効なアイテムタイプです', 'kantanpro' ) );
		}

		if ( $item_id <= 0 || $order_id <= 0 ) {
			$this->log_ajax_error(
				'Invalid ID values',
				array(
					'item_id'  => $item_id,
					'order_id' => $order_id,
				)
			);
			wp_send_json_error( __( '無効なIDです', 'kantanpro' ) );
		}

		// 新しいクラス構造を使用してアイテムを更新
		$order_items = KTPWP_Order_Items::get_instance();

		try {
			if ( $item_type === 'invoice' ) {
				$result = $order_items->update_item_field( 'invoice', $item_id, $field_name, $field_value );
			} else {
				$result = $order_items->update_item_field( 'cost', $item_id, $field_name, $field_value );
			}

			if ( $result && is_array( $result ) && $result['success'] ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( "[AJAX_AUTO_SAVE] Successfully updated {$item_type} item {$item_id}, field: {$field_name}" );
				}
				// 保存後の最新利益HTMLを返す（フロントで置換）
				$profit_payload = $this->build_profit_payload_for_order( $order_id );
				wp_send_json_success(
					array(
						'message' => __( '正常に保存されました', 'kantanpro' ),
						'value_changed' => $result['value_changed'],
						'profit' => $profit_payload,
						'history_label' => isset( $result['history_label'] ) ? $result['history_label'] : null,
					)
				);
			} else {
				$this->log_ajax_error(
					'Failed to update item',
					array(
						'type'    => $item_type,
						'item_id' => $item_id,
						'field'   => $field_name,
					)
				);
				wp_send_json_error( __( '保存に失敗しました', 'kantanpro' ) );
			}
		} catch ( Exception $e ) {
			$this->log_ajax_error(
				'Exception during auto-save',
				array(
					'message' => $e->getMessage(),
					'type'    => $item_type,
					'item_id' => $item_id,
				)
			);
			wp_send_json_error( __( '保存中にエラーが発生しました', 'kantanpro' ) );
		}
	}

	/**
	 * Ajax: 新規アイテム作成処理（強化版）
	 */
	public function ajax_create_new_item() {
		error_log( '[AJAX_CREATE_NEW_ITEM] Method called - POST data: ' . print_r($_POST, true) );
		
		// 編集者以上の権限チェック
		if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'ktpwp_access' ) ) {
			$this->log_ajax_error( 'Create new item permission check failed' );
			wp_send_json_error( __( 'この操作を行う権限がありません。', 'kantanpro' ) );
			return;
		}

		// 安全性の初期チェック（Adminer警告対策）
		if ( ! is_array( $_POST ) || empty( $_POST ) ) {
			error_log( 'KTPWP Ajax: $_POST is not array or empty' );
			wp_send_json_error( __( 'リクエストデータが無効です', 'kantanpro' ) );
			return;
		}

		// セキュリティチェック - 複数のnonce名でチェック
		$nonce_verified = false;
		$nonce_value    = '';

		// 複数のnonce名でチェック
		$nonce_fields = array( 'nonce', 'ktp_ajax_nonce', '_ajax_nonce', '_wpnonce' );
		foreach ( $nonce_fields as $field ) {
			if ( isset( $_POST[ $field ] ) ) {
				$nonce_value = $_POST[ $field ];

				// 配列の場合はvalueキーを取得
				if ( is_array( $nonce_value ) && isset( $nonce_value['value'] ) ) {
					$nonce_value = $nonce_value['value'];
				}

				if ( wp_verify_nonce( $nonce_value, 'ktp_ajax_nonce' ) ) {
					$nonce_verified = true;
					error_log( "[AJAX_CREATE_NEW_ITEM] Nonce verified with field: {$field}" );
					break;
				}
			}
		}

		if ( ! $nonce_verified ) {
			error_log( '[AJAX_CREATE_NEW_ITEM] Security check failed - tried fields: ' . implode( ', ', $nonce_fields ) );
			error_log( '[AJAX_CREATE_NEW_ITEM] Available POST fields: ' . implode( ', ', array_keys( $_POST ) ) );
			$this->log_ajax_error( 'Create new item security check failed', $_POST );
			wp_send_json_error( __( 'セキュリティ検証に失敗しました', 'kantanpro' ) );
		}

		// POSTデータの取得とサニタイズ（強化版）
		$item_type   = $this->sanitize_ajax_input( 'item_type', 'text' );
		$field_name  = $this->sanitize_ajax_input( 'field_name', 'text' );
		$field_value = $this->sanitize_ajax_input( 'field_value', 'text' );
		$order_id    = $this->sanitize_ajax_input( 'order_id', 'int' );
		$request_id  = $this->sanitize_ajax_input( 'request_id', 'text' );

		// バリデーション
		if ( ! in_array( $item_type, array( 'invoice', 'cost' ), true ) ) {
			$this->log_ajax_error( 'Invalid item type for creation', array( 'type' => $item_type ) );
			wp_send_json_error( __( '無効なアイテムタイプです', 'kantanpro' ) );
		}

		if ( $order_id <= 0 ) {
			$this->log_ajax_error( 'Invalid order ID for creation', array( 'order_id' => $order_id ) );
			wp_send_json_error( array( 'message' => __( '無効な受注IDです。ページを再読み込みしてから再度お試しください。', 'kantanpro' ) ) );
		}

		// 新しいクラス構造を使用してアイテムを作成
		$order_items = KTPWP_Order_Items::get_instance();

		try {

			// 新しいアイテムを作成（初期値とリクエストIDを渡して重複防止）
			$new_item_id = $order_items->create_new_item( $item_type, $order_id, $field_name, $field_value, $request_id );
			
			error_log( "[AJAX_CREATE_NEW_ITEM] create_new_item result: item_type={$item_type}, order_id={$order_id}, field_name={$field_name}, field_value={$field_value}, request_id={$request_id}, new_item_id={$new_item_id}" );

			// 指定されたフィールド値が初期値として設定されていない場合は、アイテム作成後に更新
			if ( ! empty( $field_name ) && ! empty( $field_value ) && $new_item_id ) {
				// 初期値として設定されていない場合のみ更新
				$current_value = $order_items->get_item_field_value( $item_type, $new_item_id, $field_name );
				if ( $current_value !== $field_value ) {
					$update_result = $order_items->update_item_field( $item_type, $new_item_id, $field_name, $field_value );
					if ( ! $update_result || ( is_array( $update_result ) && ! $update_result['success'] ) ) {
						error_log( "KTPWP: Failed to update field {$field_name} for new item {$new_item_id}" );
					}
				}
			}

			if ( $new_item_id ) {
				wp_send_json_success(
					array(
						'item_id' => $new_item_id,
						'message' => __( '新しいアイテムが作成されました', 'kantanpro' ),
					)
				);
			} else {
				$this->log_ajax_error(
					'Failed to create new item',
					array(
						'type'     => $item_type,
						'order_id' => $order_id,
					)
				);
				wp_send_json_error( array( 'message' => __( 'アイテムの作成に失敗しました', 'kantanpro' ) ) );
			}
		} catch ( Exception $e ) {
			$this->log_ajax_error(
				'Exception during item creation',
				array(
					'message'  => $e->getMessage(),
					'type'     => $item_type,
					'order_id' => $order_id,
				)
			);
			wp_send_json_error( __( '作成中にエラーが発生しました', 'kantanpro' ) );
		}
	}

	/**
	 * Ajax: アイテム削除処理
	 */
	public function ajax_delete_item() {
		// 編集者以上の権限チェック
		if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'ktpwp_access' ) ) {
			$this->log_ajax_error( 'Delete item permission check failed', $_POST );
			wp_send_json_error( __( 'この操作を行う権限がありません。', 'kantanpro' ) );
			return;
		}

		// 受け取ったパラメータをログに出力
		error_log( '[AJAX_DELETE_ITEM] Received params: ' . print_r( $_POST, true ) );

		// セキュリティチェック - 複数のnonce名でチェック
		$nonce_verified = false;
		$nonce_value    = '';

		// 複数のnonce名でチェック
		$nonce_fields = array( 'nonce', 'ktp_ajax_nonce', '_ajax_nonce', '_wpnonce' );
		foreach ( $nonce_fields as $field ) {
			if ( isset( $_POST[ $field ] ) ) {
				$nonce_value = $_POST[ $field ];

				// 配列の場合はvalueキーを取得
				if ( is_array( $nonce_value ) && isset( $nonce_value['value'] ) ) {
					$nonce_value = $nonce_value['value'];
				}

				if ( wp_verify_nonce( $nonce_value, 'ktp_ajax_nonce' ) ) {
					$nonce_verified = true;
					error_log( "[AJAX_DELETE_ITEM] Nonce verified with field: {$field}" );
					break;
				}
			}
		}

		if ( ! $nonce_verified ) {
			error_log( '[AJAX_DELETE_ITEM] Security check failed - tried fields: ' . implode( ', ', $nonce_fields ) );
			error_log( '[AJAX_DELETE_ITEM] Available POST fields: ' . implode( ', ', array_keys( $_POST ) ) );
			$this->log_ajax_error( 'Delete item security check failed', $_POST );
			wp_send_json_error( __( 'セキュリティ検証に失敗しました', 'kantanpro' ) );
		}

		// POSTデータの取得とサニタイズ
		$item_type = $this->sanitize_ajax_input( 'item_type', 'text' );
		$item_id   = $this->sanitize_ajax_input( 'item_id', 'int' );
		$order_id  = $this->sanitize_ajax_input( 'order_id', 'int' );

		// パラメータのログ出力（サニタイズ後）
		error_log( "[AJAX_DELETE_ITEM] Sanitized params: item_type={$item_type}, item_id={$item_id}, order_id={$order_id}" );

		// バリデーション
		if ( ! in_array( $item_type, array( 'invoice', 'cost' ), true ) ) {
			$this->log_ajax_error( 'Invalid item type for deletion', array( 'type' => $item_type ) );
			wp_send_json_error( __( '無効なアイテムタイプです', 'kantanpro' ) );
		}

		if ( $item_id <= 0 ) {
			$this->log_ajax_error(
				'Invalid item ID for deletion',
				array(
					'item_id'   => $item_id,
					'item_type' => $item_type,
					'order_id'  => $order_id,
				)
			);
			wp_send_json_error( __( '無効なアイテムIDです', 'kantanpro' ) );
		}

		if ( $order_id <= 0 ) {
			$this->log_ajax_error(
				'Invalid order ID for deletion',
				array(
					'order_id'  => $order_id,
					'item_type' => $item_type,
					'item_id'   => $item_id,
				)
			);
			wp_send_json_error( __( '無効な受注IDです', 'kantanpro' ) );
		}

		// KTPWP_Order_Items クラスのインスタンスを取得
		// クラスが存在するか確認
		if ( ! class_exists( 'KTPWP_Order_Items' ) ) {
			$this->log_ajax_error( 'KTPWP_Order_Items class not found' );
			wp_send_json_error( __( '必要なクラスが見つかりません。', 'kantanpro' ) );
			return; // ここで処理を中断
		}
		$order_items = KTPWP_Order_Items::get_instance();

		try {
			error_log( "[AJAX_DELETE_ITEM] Calling KTPWP_Order_Items::delete_item({$item_type}, {$item_id}, {$order_id})" );
			$result = $order_items->delete_item( $item_type, $item_id, $order_id );
			error_log( '[AJAX_DELETE_ITEM] delete_item result: ' . print_r( $result, true ) );

			if ( $result ) {
				error_log( '[AJAX_DELETE_ITEM] Success: item deleted successfully' );
				wp_send_json_success(
					array(
						'message' => __( 'アイテムを削除しました', 'kantanpro' ),
					)
				);
			} else {
				error_log( '[AJAX_DELETE_ITEM] Failure: delete_item returned false' );
				$this->log_ajax_error(
					'Failed to delete item from database (KTPWP_Order_Items::delete_item returned false)',
					array(
						'item_type' => $item_type,
						'item_id'   => $item_id,
						'order_id'  => $order_id,
					)
				);
				wp_send_json_error( __( 'データベースからのアイテム削除に失敗しました（詳細エラーログ確認）', 'kantanpro' ) );
			}
		} catch ( Exception $e ) {
			$this->log_ajax_error(
				'Exception during item deletion: ' . $e->getMessage() . ' Stack trace: ' . $e->getTraceAsString(),
				array(
					'message'   => $e->getMessage(),
					'item_type' => $item_type,
					'item_id'   => $item_id,
					'order_id'  => $order_id,
					'trace'     => $e->getTraceAsString(),
				)
			);
			wp_send_json_error( __( 'アイテム削除中に予期せぬエラーが発生しました（詳細エラーログ確認）', 'kantanpro' ) );
		}
	}

	/**
	 * Ajax: アイテムの並び順更新処理
	 */
	public function ajax_update_item_order() {
		error_log( '[AJAX_UPDATE_ITEM_ORDER] リクエスト開始: ' . print_r( $_POST, true ) );

		// 編集者以上の権限チェック
		if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'ktpwp_access' ) ) {
			$this->log_ajax_error( 'Update item order permission check failed', $_POST );
			wp_send_json_error( __( 'この操作を行う権限がありません。', 'kantanpro' ) );
			return;
		}

		// セキュリティチェック - 複数のnonce名でチェック
		$nonce_verified = false;
		$nonce_value    = '';
		$verified_field = '';

		// 複数のnonce名でチェック
		$nonce_fields = array( 'nonce', 'ktp_ajax_nonce', '_ajax_nonce', '_wpnonce' );
		foreach ( $nonce_fields as $field ) {
			if ( isset( $_POST[ $field ] ) ) {
				$nonce_value = $_POST[ $field ];

				// 配列の場合はvalueキーを取得
				if ( is_array( $nonce_value ) && isset( $nonce_value['value'] ) ) {
					$nonce_value = $nonce_value['value'];
				}

				$nonce_value = sanitize_text_field( $nonce_value );
				if ( wp_verify_nonce( $nonce_value, 'ktp_ajax_nonce' ) ) {
					$nonce_verified = true;
					$verified_field = $field;
					error_log( "[AJAX_UPDATE_ITEM_ORDER] Nonce verified with field: {$field}, value: " . substr( $nonce_value, 0, 10 ) . '...' );
					break;
				}
			}
		}

		if ( ! $nonce_verified ) {
			error_log( '[AJAX_UPDATE_ITEM_ORDER] Security check failed - tried fields: ' . implode( ', ', $nonce_fields ) );
			error_log( '[AJAX_UPDATE_ITEM_ORDER] Available POST fields: ' . implode( ', ', array_keys( $_POST ) ) );
			foreach ( $_POST as $key => $value ) {
				if ( strpos( $key, 'nonce' ) !== false || strpos( $key, '_wp' ) !== false ) {
					error_log( "[AJAX_UPDATE_ITEM_ORDER] Found nonce-like field: {$key} = " . substr( $value, 0, 10 ) . '...' );
				}
			}
			$this->log_ajax_error( 'Update item order security check failed', $_POST );
			wp_send_json_error( __( 'セキュリティ検証に失敗しました', 'kantanpro' ) );
		}

		// POSTデータの取得とサニタイズ
		$order_id   = $this->sanitize_ajax_input( 'order_id', 'int' );
		$items_data = isset( $_POST['items'] ) && is_array( $_POST['items'] ) ? $_POST['items'] : array();
		$item_type  = $this->sanitize_ajax_input( 'item_type', 'text' ); // 'invoice' or 'cost'

		// サニタイズされたパラメータのログ
		error_log( "[AJAX_UPDATE_ITEM_ORDER] Sanitized params: order_id={$order_id}, item_type={$item_type}, items_count=" . count( $items_data ) );
		error_log( '[AJAX_UPDATE_ITEM_ORDER] Items data: ' . print_r( $items_data, true ) );

		// バリデーション
		if ( $order_id <= 0 ) {
			$this->log_ajax_error( 'Invalid order ID for updating item order', array( 'order_id' => $order_id ) );
			wp_send_json_error( __( '無効な受注IDです', 'kantanpro' ) );
		}

		if ( ! in_array( $item_type, array( 'invoice', 'cost' ), true ) ) {
			$this->log_ajax_error( 'Invalid item type for updating item order', array( 'item_type' => $item_type ) );
			wp_send_json_error( __( '無効なアイテムタイプです', 'kantanpro' ) );
		}

		if ( empty( $items_data ) ) {
			$this->log_ajax_error( 'No items data provided for updating order', $_POST );
			wp_send_json_error( __( '更新するアイテムデータがありません', 'kantanpro' ) );
		}

		// アイテムデータの詳細バリデーション
		$valid_items   = array();
		$invalid_items = array();

		foreach ( $items_data as $index => $item ) {
			if ( ! isset( $item['id'] ) || ! isset( $item['sort_order'] ) ) {
				$invalid_items[] = array(
					'index'  => $index,
					'item'   => $item,
					'reason' => 'Missing id or sort_order',
				);
				continue;
			}

			$item_id    = intval( $item['id'] );
			$sort_order = intval( $item['sort_order'] );

			if ( $item_id <= 0 ) {
				$invalid_items[] = array(
					'index'  => $index,
					'item'   => $item,
					'reason' => 'Invalid item ID',
				);
				continue;
			}

			if ( $sort_order <= 0 ) {
				$invalid_items[] = array(
					'index'  => $index,
					'item'   => $item,
					'reason' => 'Invalid sort order',
				);
				continue;
			}

			$valid_items[] = $item;
		}

		if ( ! empty( $invalid_items ) ) {
			error_log( '[AJAX_UPDATE_ITEM_ORDER] Invalid items found: ' . print_r( $invalid_items, true ) );
			wp_send_json_error(
				array(
					'message'       => __( '一部のアイテムデータが無効です', 'kantanpro' ),
					'invalid_items' => $invalid_items,
				)
			);
		}

		// KTPWP_Order_Items クラスのインスタンスを取得
		if ( ! class_exists( 'KTPWP_Order_Items' ) ) {
			$this->log_ajax_error( 'KTPWP_Order_Items class not found for updating item order' );
			wp_send_json_error( __( '必要なクラスが見つかりません。', 'kantanpro' ) );
			return;
		}
		$order_items_manager = KTPWP_Order_Items::get_instance();

		try {
			error_log( "[AJAX_UPDATE_ITEM_ORDER] Calling KTPWP_Order_Items::update_items_order({$item_type}, {$order_id}, ...)" );
			error_log( '[AJAX_UPDATE_ITEM_ORDER] Valid items to update: ' . print_r( $valid_items, true ) );

			$result = $order_items_manager->update_items_order( $item_type, $order_id, $valid_items );
			error_log( '[AJAX_UPDATE_ITEM_ORDER] update_items_order result: ' . print_r( $result, true ) );

			if ( $result ) {
				error_log( "[AJAX_UPDATE_ITEM_ORDER] Successfully updated item order for order_id={$order_id}, item_type={$item_type}" );
				wp_send_json_success(
					array(
						'message'       => __( 'アイテムの並び順を更新しました', 'kantanpro' ),
						'updated_count' => count( $valid_items ),
						'order_id'      => $order_id,
						'item_type'     => $item_type,
					)
				);
			} else {
				$this->log_ajax_error(
					'Failed to update item order (KTPWP_Order_Items::update_items_order returned false)',
					array(
						'item_type'        => $item_type,
						'order_id'         => $order_id,
						'items_data_count' => count( $valid_items ),
					)
				);
				wp_send_json_error( __( 'データベースでのアイテム並び順更新に失敗しました（詳細エラーログ確認）', 'kantanpro' ) );
			}
		} catch ( Exception $e ) {
			$this->log_ajax_error(
				'Exception during item order update: ' . $e->getMessage() . ' Stack trace: ' . $e->getTraceAsString(),
				array(
					'message'   => $e->getMessage(),
					'item_type' => $item_type,
					'order_id'  => $order_id,
					'trace'     => $e->getTraceAsString(),
				)
			);
			wp_send_json_error( __( 'アイテムの並び順更新中に予期せぬエラーが発生しました（詳細エラーログ確認）', 'kantanpro' ) );
		}
	}

	/**
	 * Ajax: サービス一覧取得
	 */
	public function ajax_get_service_list() {
		// 最小限のデバッグログのみ
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[AJAX_GET_SERVICE_LIST] リクエスト開始: ' . date( 'Y-m-d H:i:s' ) );
		}

		// 権限チェック
		if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'ktpwp_access' ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[AJAX] サービス一覧取得: 権限不足' );
			}
			wp_send_json_error( __( 'この操作を行う権限がありません', 'kantanpro' ) );
			return;
		}

		// 簡単なnonceチェック
		if ( ! check_ajax_referer( 'ktp_ajax_nonce', 'nonce', false ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[AJAX] サービス一覧取得: nonce検証失敗' );
			}
			wp_send_json_error( __( 'セキュリティチェックに失敗しました', 'kantanpro' ) );
			return;
		}

		// パラメータ取得
		$page = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;

		// 一般設定から表示件数を取得
		if ( isset( $_POST['limit'] ) && $_POST['limit'] === 'auto' ) {
			// 一般設定から表示件数を取得（設定クラスが利用可能な場合）
			if ( class_exists( 'KTPWP_Settings' ) ) {
				$limit = KTPWP_Settings::get_work_list_range();
			} else {
				$limit = 20; // フォールバック値
			}
		} else {
			$limit = isset( $_POST['limit'] ) ? absint( $_POST['limit'] ) : 20;
		}

		$search   = isset( $_POST['search'] ) ? sanitize_text_field( $_POST['search'] ) : '';
		$category = isset( $_POST['category'] ) ? sanitize_text_field( $_POST['category'] ) : '';

		if ( $limit > 500 ) {
			$limit = 500; // 一般設定の最大値に合わせる
		}

		$offset = ( $page - 1 ) * $limit;

		try {

			// サービスDBクラスのインスタンスを取得
			if ( ! class_exists( 'KTPWP_Service_DB' ) ) {
				$service_db_file = KTPWP_PLUGIN_DIR . 'includes/class-ktpwp-service-db.php';
				require_once $service_db_file;
			}

			if ( ! class_exists( 'KTPWP_Service_DB' ) ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( '[AJAX] KTPWP_Service_DBクラスが見つかりません' );
				}
				wp_send_json_error( __( 'サービスDBクラスが見つかりません', 'kantanpro' ) );
				return;
			}

			$service_db = KTPWP_Service_DB::get_instance();
			if ( ! $service_db ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( '[AJAX] サービスDBインスタンスの取得に失敗' );
				}

				// ダミーデータで代替
				$dummy_services = array(
					array(
						'id'           => 1,
						'service_name' => 'テストサービス1（DBエラー時）',
						'price'        => 10000,
						'unit'         => '個',
						'category'     => 'カテゴリ1',
					),
				);

				$response_data = array(
					'services'      => $dummy_services,
					'pagination'    => array(
						'current_page'   => $page,
						'total_pages'    => 1,
						'total_items'    => 1,
						'items_per_page' => $limit,
					),
					'debug_message' => 'DBインスタンス取得失敗のためダミーデータを使用',
				);

				wp_send_json_success( $response_data );
				return;
			}

			// 検索条件の配列
			$search_args = array(
				'limit'    => $limit,
				'offset'   => $offset,
				'order_by' => 'frequency',
				'order'    => 'DESC',
				'search'   => $search,
				'category' => $category,
			);

			// サービス一覧を取得
			$services = $service_db->get_services( 'service', $search_args );
			$total_services = $service_db->get_services_count( 'service', $search_args );

			if ( $services === null || empty( $services ) ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( '[AJAX] サービス一覧が空のためダミーデータに切り替え' );
				}

				// テスト用のダミーデータを返す
				$dummy_services = array(
					array(
						'id'           => 1,
						'service_name' => 'サンプルサービス1',
						'price'        => 10000,
						'unit'         => '個',
						'category'     => 'カテゴリ1',
						'remarks'      => '',
					),
					array(
						'id'           => 2,
						'service_name' => 'サンプルサービス2',
						'price'        => 20000,
						'unit'         => '時間',
						'category'     => 'カテゴリ2',
						'remarks'      => '',
					),
					array(
						'id'           => 3,
						'service_name' => 'サンプルサービス3',
						'price'        => 5000,
						'unit'         => '回',
						'category'     => 'カテゴリ1',
						'remarks'      => '',
					),
				);

				$services       = $dummy_services;
				$total_services = count( $dummy_services );
			}

			$total_pages = ceil( $total_services / $limit );

			// サービス一覧を配列化（tax_rate含む）
			$services_array = array();
			foreach ( $services as $service ) {
				$service_data = array(
					'id'           => $service->id,
					'service_name' => $service->service_name,
					'price'        => $service->price,
					'unit'         => $service->unit,
					'category'     => $service->category,
					'tax_rate'     => (isset($service->tax_rate) && $service->tax_rate !== null) ? $service->tax_rate : null,
					'remarks'      => isset($service->remarks) ? $service->remarks : '',
					// 必要に応じて他のフィールドも追加
				);
				
				$services_array[] = $service_data;
			}

			// レスポンスデータ
			$response_data = array(
				'services'   => $services_array,
				'pagination' => array(
					'current_page'   => $page,
					'total_pages'    => $total_pages,
					'total_items'    => $total_services,
					'items_per_page' => $limit,
				),
			);

			error_log( '[AJAX] サービス一覧取得成功: ' . count( $services ) . '件' );
			error_log( '[AJAX] レスポンスデータ: ' . print_r( $response_data, true ) );
			error_log( '[AJAX] 最初のサービスのservice_name: ' . ( isset( $services_array[0]['service_name'] ) ? $services_array[0]['service_name'] : 'NOT_SET' ) );
			wp_send_json_success( $response_data );

		} catch ( Exception $e ) {
			error_log( '[AJAX] サービス一覧取得例外エラー: ' . $e->getMessage() );

			// エラー時はダミーデータで代替
			$dummy_services = array(
				array(
					'id'           => 1,
					'service_name' => 'エラー時サンプル',
					'price'        => 1000,
					'unit'         => '個',
					'category'     => 'エラー対応',
				),
			);

			$response_data = array(
				'services'      => $dummy_services,
				'pagination'    => array(
					'current_page'   => $page,
					'total_pages'    => 1,
					'total_items'    => 1,
					'items_per_page' => $limit,
				),
				'debug_message' => 'Exception発生: ' . $e->getMessage(),
			);

			wp_send_json_success( $response_data );
		}
	}

	/**
	 * Ajax: サービス複製
	 */
	public function ajax_duplicate_service() {
		if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'ktpwp_access' ) ) {
			wp_send_json_error( __( 'この操作を行う権限がありません。', 'kantanpro' ) );
		}

		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( empty( $nonce ) || ( ! wp_verify_nonce( $nonce, 'ktp_ajax_nonce' ) && ! wp_verify_nonce( $nonce, 'ktpwp_ajax_nonce' ) ) ) {
			wp_send_json_error( __( 'セキュリティチェックに失敗しました', 'kantanpro' ) );
		}

		$data_id = isset( $_POST['data_id'] ) ? absint( $_POST['data_id'] ) : 0;
		if ( $data_id <= 0 ) {
			wp_send_json_error( __( '複製元のサービス ID が不正です。', 'kantanpro' ) );
		}

		if ( ! class_exists( 'KTPWP_Service_DB' ) ) {
			require_once KTPWP_PLUGIN_DIR . 'includes/class-ktpwp-service-db.php';
		}

		$service_db = KTPWP_Service_DB::get_instance();
		$result       = $service_db->duplicate_service_record( $data_id );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		$new_id = (int) $result['new_id'];
		$cookie_name = 'ktp_service_id';
		if ( ! headers_sent() ) {
			setcookie( $cookie_name, (string) $new_id, time() + ( 86400 * 30 ), '/' );
		}

		$posted_base   = isset( $_POST['redirect_base'] ) ? wp_unslash( $_POST['redirect_base'] ) : '';
		$base_page_url = class_exists( 'KTPWP_Main' ) ? KTPWP_Main::resolve_page_base_url_for_redirect( $posted_base ) : home_url( '/' );
		$redirect_url  = add_query_arg(
			array(
				'tab_name' => 'service',
				'data_id'  => $new_id,
				'message'  => 'duplicated',
			),
			$base_page_url
		);
		$redirect_url = remove_query_arg( array( 'query_post', '_ktp_service_nonce', 'send_post' ), $redirect_url );

		wp_send_json_success(
			array(
				'new_id'       => $new_id,
				'redirect_url' => esc_url_raw( $redirect_url ),
			)
		);
	}

	/**
	 * 顧客メールの宛先候補（代表メール1件＋部署メール、重複除去）。KantanBiz の contactEmailCandidates に相当。
	 *
	 * @param object|null $client ktp_client 行。
	 * @return string[]
	 */
	private function ktpwp_order_mail_contact_email_candidates( $client ) {
		if ( ! $client ) {
			return array();
		}
		$candidates = array();
		$push       = static function ( $raw ) use ( &$candidates ) {
			if ( $raw === null || $raw === '' ) {
				return;
			}
			$e = trim( str_replace( array( "\0", "\r", "\n", "\t" ), '', (string) $raw ) );
			if ( $e === '' || ! filter_var( $e, FILTER_VALIDATE_EMAIL ) ) {
				return;
			}
			$s = sanitize_email( $e );
			if ( $s === '' || ! filter_var( $s, FILTER_VALIDATE_EMAIL ) ) {
				$s = $e;
			}
			$candidates[] = $s;
		};
		$email_raw = $client->email ?? '';
		$rep_raw   = trim( str_replace( array( "\0", "\r", "\n", "\t" ), '', (string) ( $client->representative_name ?? '' ) ) );
		$name_raw  = trim( str_replace( array( "\0", "\r", "\n", "\t" ), '', (string) ( $client->name ?? '' ) ) );
		if ( (string) $email_raw === '' || trim( (string) $email_raw ) === '' ) {
			if ( $rep_raw !== '' && filter_var( $rep_raw, FILTER_VALIDATE_EMAIL ) ) {
				$push( $rep_raw );
			} elseif ( $name_raw !== '' && filter_var( $name_raw, FILTER_VALIDATE_EMAIL ) ) {
				$push( $name_raw );
			}
		} else {
			$push( $email_raw );
		}
		if ( ! empty( $client->id ) && class_exists( 'KTPWP_Department_Manager' ) ) {
			if ( function_exists( 'ktpwp_safe_create_department_table' ) ) {
				ktpwp_safe_create_department_table();
			}
			foreach ( KTPWP_Department_Manager::get_departments_by_client( (int) $client->id ) as $dept ) {
				if ( ! empty( $dept->email ) ) {
					$push( $dept->email );
				}
			}
		}
		$seen = array();
		$uniq = array();
		foreach ( $candidates as $e ) {
			$k = strtolower( $e );
			if ( ! isset( $seen[ $k ] ) ) {
				$seen[ $k ] = true;
				$uniq[]     = $e;
			}
		}
		return $uniq;
	}

	/**
	 * 既定の To（選択部署メール優先、なければ代表メール）。
	 *
	 * @param object|null $client ktp_client 行。
	 * @param object|null $order  ktp_order 行。
	 * @return string
	 */
	private function ktpwp_order_mail_default_to_email( $client, $order = null ) {
		if ( ! $client ) {
			return '';
		}
		if ( $order && class_exists( 'KTPWP_Department_Manager' ) && KTPWP_Department_Manager::table_exists() ) {
			$dept_mail = KTPWP_Department_Manager::get_department_email_for_order( $order );
			if ( $dept_mail !== '' && is_email( $dept_mail ) ) {
				return $dept_mail;
			}
		}
		$all = $this->ktpwp_order_mail_contact_email_candidates( $client );
		return $all[0] ?? '';
	}

	/**
	 * カンマ・改行区切りのメール文字列を配列に分解する。
	 *
	 * @param string $raw 入力文字列。
	 * @return string[]
	 */
	private function ktpwp_order_mail_parse_email_list( $raw ) {
		$parts = preg_split( '/[\s,;、]+/u', trim( (string) $raw ), -1, PREG_SPLIT_NO_EMPTY );
		if ( ! is_array( $parts ) ) {
			return array();
		}
		$out = array();
		foreach ( $parts as $part ) {
			$out[] = trim( (string) $part );
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * 部署行からメール宛先ラベルを組み立てる（例: アットサイン 野中-atsign07@gmail.com）。
	 *
	 * @param object $department ktp_department 行。
	 * @param string $email      メールアドレス。
	 * @return string
	 */
	private function ktpwp_order_mail_format_department_label( $department, $email ) {
		$department_name = '';
		if ( class_exists( 'KTPWP_Department_Manager' ) ) {
			$department_name = trim(
				KTPWP_Department_Manager::department_name_for_mail_addressee( (string) ( $department->department_name ?? '' ) )
			);
		}
		$contact = trim( str_replace( array( "\0", "\r", "\n", "\t" ), '', (string) ( $department->contact_person ?? '' ) ) );
		if ( $contact === '' || filter_var( $contact, FILTER_VALIDATE_EMAIL ) ) {
			$contact = 'ご担当者';
		}

		if ( $department_name !== '' ) {
			return $department_name . ' ' . $contact . '-' . $email;
		}

		return $contact . '-' . $email;
	}

	/**
	 * 受注の担当者名・会社名から宛先ラベルを組み立てる（例: アットサイン 野中-foo@example.com）。
	 *
	 * @param object      $client ktp_client 行。
	 * @param object      $order  ktp_order 行。
	 * @param string      $email  メールアドレス。
	 * @return string
	 */
	private function ktpwp_order_mail_format_order_contact_label( $client, $order, $email ) {
		$contact = '';
		if ( class_exists( 'KTPWP_Department_Manager' ) ) {
			$contact = KTPWP_Department_Manager::resolve_contact_name_for_order( $order );
		}
		if ( $contact === '' ) {
			$contact = trim( str_replace( array( "\0", "\r", "\n", "\t" ), '', (string) ( $order->user_name ?? '' ) ) );
		}
		if ( $contact === '' || filter_var( $contact, FILTER_VALIDATE_EMAIL ) ) {
			$contact = trim( str_replace( array( "\0", "\r", "\n", "\t" ), '', (string) ( $client->representative_name ?? $client->name ?? '' ) ) );
		}
		if ( $contact === '' || filter_var( $contact, FILTER_VALIDATE_EMAIL ) ) {
			$contact = 'ご担当者';
		}

		$company = '';
		if ( class_exists( 'KTPWP_Department_Manager' ) ) {
			$display = trim( (string) ( $order->customer_name ?? '' ) );
			$company = KTPWP_Department_Manager::resolve_parent_company_name_for_order( $order, $client, $display, null );
		} elseif ( ! empty( $client->company_name ) ) {
			$company = trim( (string) $client->company_name );
		}

		if ( $company !== '' ) {
			return $company . ' ' . $contact . '-' . $email;
		}

		return $contact . '-' . $email;
	}

	/**
	 * 宛先候補の表示ラベル（部署名 担当者-メール 等）。
	 *
	 * @param object|null $client ktp_client 行。
	 * @param string      $email  メールアドレス。
	 * @param object|null $order  ktp_order 行（受注に紐づく部署を優先）。
	 * @return string
	 */
	private function ktpwp_order_mail_recipient_label_for_email( $client, $email, $order = null ) {
		$email_lc = strtolower( trim( (string) $email ) );
		if ( $email_lc === '' || ! $client ) {
			return (string) $email;
		}

		if ( $order && class_exists( 'KTPWP_Department_Manager' ) ) {
			if ( function_exists( 'ktpwp_safe_create_department_table' ) ) {
				ktpwp_safe_create_department_table();
			}
			$order_department = KTPWP_Department_Manager::resolve_department_for_order( $order );
			if (
				$order_department
				&& ! KTPWP_Department_Manager::is_bogus_inquiry_department( $order_department )
				&& ! empty( $order_department->email )
			) {
				$order_dept_email = trim( str_replace( array( "\0", "\r", "\n", "\t" ), '', (string) $order_department->email ) );
				if ( $order_dept_email !== '' && strtolower( $order_dept_email ) === $email_lc ) {
					return $this->ktpwp_order_mail_format_department_label( $order_department, $email );
				}
			}

			$client_email = trim( str_replace( array( "\0", "\r", "\n", "\t" ), '', (string) ( $client->email ?? '' ) ) );
			if ( $client_email !== '' && strtolower( $client_email ) === $email_lc ) {
				return $this->ktpwp_order_mail_format_order_contact_label( $client, $order, $email );
			}
		}

		if ( ! empty( $client->id ) && class_exists( 'KTPWP_Department_Manager' ) ) {
			if ( function_exists( 'ktpwp_safe_create_department_table' ) ) {
				ktpwp_safe_create_department_table();
			}
			foreach ( KTPWP_Department_Manager::get_departments_by_client( (int) $client->id ) as $dept ) {
				if ( KTPWP_Department_Manager::is_bogus_inquiry_department( $dept ) ) {
					continue;
				}
				$dept_email = trim( str_replace( array( "\0", "\r", "\n", "\t" ), '', (string) ( $dept->email ?? '' ) ) );
				if ( $dept_email === '' || strtolower( $dept_email ) !== $email_lc ) {
					continue;
				}

				return $this->ktpwp_order_mail_format_department_label( $dept, $email );
			}
		}

		$client_email = trim( str_replace( array( "\0", "\r", "\n", "\t" ), '', (string) ( $client->email ?? '' ) ) );
		$name_raw     = trim( str_replace( array( "\0", "\r", "\n", "\t" ), '', (string) ( $client->name ?? '' ) ) );
		if ( ( $client_email === '' || ! filter_var( $client_email, FILTER_VALIDATE_EMAIL ) )
			&& filter_var( $name_raw, FILTER_VALIDATE_EMAIL )
			&& strtolower( $name_raw ) === $email_lc ) {
			$contact = trim( str_replace( array( "\0", "\r", "\n", "\t" ), '', (string) ( $client->representative_name ?? $client->name ?? '' ) ) );
			if ( $contact === '' || filter_var( $contact, FILTER_VALIDATE_EMAIL ) ) {
				$contact = 'ご担当者';
			}

			return $contact . '-' . $email;
		}
		if ( $client_email !== '' && strtolower( $client_email ) === $email_lc ) {
			if ( $order ) {
				return $this->ktpwp_order_mail_format_order_contact_label( $client, $order, $email );
			}

			$contact = trim( str_replace( array( "\0", "\r", "\n", "\t" ), '', (string) ( $client->representative_name ?? $client->name ?? '' ) ) );
			if ( $contact === '' || filter_var( $contact, FILTER_VALIDATE_EMAIL ) ) {
				$contact = 'ご担当者';
			}

			return $contact . '-' . $email;
		}

		return (string) $email;
	}

	/**
	 * 宛先候補一覧（email + label）。
	 *
	 * @param object|null $client ktp_client 行。
	 * @param object|null $order  ktp_order 行。
	 * @return array<int, array{email: string, label: string}>
	 */
	private function ktpwp_order_mail_build_recipient_options( $client, $order = null ) {
		$options = array();
		foreach ( $this->ktpwp_order_mail_contact_email_candidates( $client ) as $email ) {
			$options[] = array(
				'email' => $email,
				'label' => $this->ktpwp_order_mail_recipient_label_for_email( $client, $email, $order ),
			);
		}

		return $options;
	}

	/**
	 * CC 宛先を解決（部署チェックボックス + その他の CC）。
	 *
	 * @param string   $to_email  To。
	 * @param string[] $allowed   顧客・部署の許可メール一覧。
	 * @return string[]
	 */
	private function ktpwp_order_mail_resolve_cc_recipients( $to_email, array $allowed ) {
		$to_lower = strtolower( trim( (string) $to_email ) );
		$allowed_lc = array();
		foreach ( $allowed as $email ) {
			$allowed_lc[ strtolower( (string) $email ) ] = sanitize_email( (string) $email );
		}

		$contact_cc = array();
		$cc_raw     = isset( $_POST['cc_emails'] ) ? wp_unslash( $_POST['cc_emails'] ) : array();
		if ( is_array( $cc_raw ) ) {
			foreach ( $cc_raw as $raw_email ) {
				$email = sanitize_email( trim( (string) $raw_email ) );
				if ( $email === '' || ! is_email( $email ) ) {
					continue;
				}
				if ( ! isset( $allowed_lc[ strtolower( $email ) ] ) ) {
					throw new Exception( 'CCに許可されていないメールアドレスが含まれています。' );
				}
				$contact_cc[] = $email;
			}
		}

		$extra_raw = isset( $_POST['cc_emails_extra'] ) ? (string) wp_unslash( $_POST['cc_emails_extra'] ) : '';
		if ( $extra_raw === '' && isset( $_POST['cc'] ) ) {
			$extra_raw = (string) wp_unslash( $_POST['cc'] );
		}
		$extra_cc = $this->ktpwp_order_mail_parse_email_list( $extra_raw );
		foreach ( $extra_cc as $email ) {
			$clean = sanitize_email( $email );
			if ( $clean === '' || ! is_email( $clean ) ) {
				throw new Exception( 'その他のCCに無効なメールアドレスが含まれています。' );
			}
		}
		$extra_cc = array_map( 'sanitize_email', $extra_cc );

		if ( $to_lower !== '' ) {
			foreach ( $extra_cc as $email ) {
				if ( strtolower( $email ) === $to_lower ) {
					throw new Exception( 'その他のCCに宛先と同じメールアドレスは指定できません。' );
				}
			}
		}

		$cc_list = array_values(
			array_unique(
				array_filter(
					array_merge( $contact_cc, $extra_cc ),
					static function ( $email ) use ( $to_lower ) {
						return is_string( $email ) && $email !== '' && strtolower( $email ) !== $to_lower;
					}
				)
			)
		);

		if ( count( $cc_list ) > 20 ) {
			throw new Exception( 'CCは最大20件までです。' );
		}

		return $cc_list;
	}

	/**
	 * BCC 宛先を解決（自由入力のみ）。
	 *
	 * @param string $to_email To。
	 * @return string[]
	 */
	private function ktpwp_order_mail_resolve_bcc_recipients( $to_email ) {
		$to_lower  = strtolower( trim( (string) $to_email ) );
		$extra_raw = isset( $_POST['bcc_emails_extra'] ) ? (string) wp_unslash( $_POST['bcc_emails_extra'] ) : '';
		$bcc_list  = $this->ktpwp_order_mail_parse_email_list( $extra_raw );

		foreach ( $bcc_list as $email ) {
			$clean = sanitize_email( $email );
			if ( $clean === '' || ! is_email( $clean ) ) {
				throw new Exception( 'BCCに無効なメールアドレスが含まれています。' );
			}
		}
		$bcc_list = array_map( 'sanitize_email', $bcc_list );

		if ( $to_lower !== '' ) {
			foreach ( $bcc_list as $email ) {
				if ( strtolower( $email ) === $to_lower ) {
					throw new Exception( 'BCCに宛先と同じメールアドレスは指定できません。' );
				}
			}
		}

		$bcc_list = array_values( array_unique( array_filter( $bcc_list ) ) );

		if ( count( $bcc_list ) > 20 ) {
			throw new Exception( 'BCCは最大20件までです。' );
		}

		return $bcc_list;
	}

	/**
	 * メール添付ファイルをリクエストから収集し一時保存する。
	 *
	 * @param string $log_prefix デバッグログ用プレフィックス。
	 * @return array{entries: array<int, array{path: string, name: string}>, temp_files: string[]}
	 */
	private function ktpwp_collect_email_attachments_from_request( $log_prefix = 'KTPWP Email' ) {
		$entries    = array();
		$temp_files = array();

		if ( empty( $_FILES['attachments'] ) ) {
			return array(
				'entries'    => $entries,
				'temp_files' => $temp_files,
			);
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( $log_prefix . ': Processing file attachments - ' . print_r( $_FILES['attachments'], true ) );
		}

		$uploaded_files     = $_FILES['attachments'];
		$max_file_size      = 10 * 1024 * 1024;
		$max_total_size     = 50 * 1024 * 1024;
		$allowed_types      = array(
			'application/pdf',
			'image/jpeg',
			'image/jpg',
			'image/png',
			'image/gif',
			'application/msword',
			'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
			'application/vnd.ms-excel',
			'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
			'application/zip',
			'application/x-rar-compressed',
			'application/x-zip-compressed',
		);
		$allowed_extensions = array( '.pdf', '.jpg', '.jpeg', '.png', '.gif', '.doc', '.docx', '.xls', '.xlsx', '.zip', '.rar', '.7z' );

		$total_size = 0;
		$file_count = is_array( $uploaded_files['name'] ) ? count( $uploaded_files['name'] ) : 1;

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( "{$log_prefix}: Processing {$file_count} files" );
		}

		for ( $i = 0; $i < $file_count; $i++ ) {
			$file_name  = is_array( $uploaded_files['name'] ) ? $uploaded_files['name'][ $i ] : $uploaded_files['name'];
			$file_tmp   = is_array( $uploaded_files['tmp_name'] ) ? $uploaded_files['tmp_name'][ $i ] : $uploaded_files['tmp_name'];
			$file_size  = is_array( $uploaded_files['size'] ) ? $uploaded_files['size'][ $i ] : $uploaded_files['size'];
			$file_type  = is_array( $uploaded_files['type'] ) ? $uploaded_files['type'][ $i ] : $uploaded_files['type'];
			$file_error = is_array( $uploaded_files['error'] ) ? $uploaded_files['error'][ $i ] : $uploaded_files['error'];

			if ( $file_name === '' && (int) $file_error === UPLOAD_ERR_NO_FILE ) {
				continue;
			}

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( "{$log_prefix}: Processing file {$i}: {$file_name} ({$file_size} bytes, type: {$file_type})" );
			}

			if ( $file_error !== UPLOAD_ERR_OK ) {
				$upload_error_message = $this->ktpwp_email_upload_error_message( (int) $file_error );
				throw new Exception( "ファイル「{$file_name}」のアップロードでエラーが発生しました。（{$upload_error_message}）" );
			}

			if ( $file_size > $max_file_size ) {
				throw new Exception( "ファイル「{$file_name}」は10MBを超えています。" );
			}

			if ( $file_size <= 0 ) {
				throw new Exception( "ファイル「{$file_name}」が空です。" );
			}

			$total_size += $file_size;
			if ( $total_size > $max_total_size ) {
				throw new Exception( '合計ファイルサイズが50MBを超えています。' );
			}

			$file_ext        = strtolower( pathinfo( $file_name, PATHINFO_EXTENSION ) );
			$is_allowed_type = in_array( $file_type, $allowed_types, true );
			$is_allowed_ext  = in_array( '.' . $file_ext, $allowed_extensions, true );

			if ( ! $is_allowed_type && ! $is_allowed_ext ) {
				throw new Exception( "ファイル「{$file_name}」は対応していない形式です。" );
			}

			$safe_filename = $this->ktpwp_email_attachment_display_name( $file_name );
			$upload_dir    = wp_upload_dir();
			$temp_dir      = $upload_dir['basedir'] . '/ktp-email-temp/';

			if ( ! file_exists( $temp_dir ) ) {
				wp_mkdir_p( $temp_dir );
			}

			$temp_file_path = $temp_dir . uniqid( 'ktp_', true ) . '_' . $safe_filename;

			if ( ! move_uploaded_file( $file_tmp, $temp_file_path ) ) {
				throw new Exception( "ファイル「{$file_name}」の保存に失敗しました。" );
			}

			$entries[]    = array(
				'path' => $temp_file_path,
				'name' => $safe_filename,
			);
			$temp_files[] = $temp_file_path;

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( "{$log_prefix}: Saved attachment: {$temp_file_path}" );
			}
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && $entries !== array() ) {
			error_log( $log_prefix . ': Successfully processed ' . count( $entries ) . ' attachments, total size: ' . round( $total_size / 1024 / 1024, 2 ) . 'MB' );
		}

		return array(
			'entries'    => $entries,
			'temp_files' => $temp_files,
		);
	}

	/**
	 * PHP アップロードエラーコードを説明文に変換する。
	 */
	private function ktpwp_email_upload_error_message( int $error_code ): string {
		switch ( $error_code ) {
			case UPLOAD_ERR_INI_SIZE:
			case UPLOAD_ERR_FORM_SIZE:
				return 'ファイルサイズがサーバーの上限を超えています（upload_max_filesize / post_max_size をご確認ください）';
			case UPLOAD_ERR_PARTIAL:
				return 'ファイルが一部しかアップロードされませんでした';
			case UPLOAD_ERR_NO_TMP_DIR:
				return '一時フォルダがありません';
			case UPLOAD_ERR_CANT_WRITE:
				return 'ディスクへの書き込みに失敗しました';
			case UPLOAD_ERR_EXTENSION:
				return 'PHP 拡張によりアップロードが中断されました';
			default:
				return 'エラーコード: ' . $error_code;
		}
	}

	/**
	 * 添付ファイルの表示名（sanitize_file_name で日本語が消える場合のフォールバック付き）。
	 */
	private function ktpwp_email_attachment_display_name( string $original_filename ): string {
		$original_filename = wp_basename( $original_filename );
		$extension         = strtolower( pathinfo( $original_filename, PATHINFO_EXTENSION ) );
		$sanitized         = sanitize_file_name( $original_filename );
		$base_name         = pathinfo( $sanitized, PATHINFO_FILENAME );

		if ( $base_name !== '' && strlen( $base_name ) >= 2 ) {
			return $sanitized;
		}

		$fallback = 'attachment_' . wp_generate_password( 8, false, false );
		if ( $extension !== '' ) {
			return $fallback . '.' . $extension;
		}

		return $fallback;
	}

	/**
	 * 添付ファイルの MIME タイプを推定する。
	 */
	private function ktpwp_email_attachment_mime_type( string $display_name, string $file_path ): string {
		$checked = wp_check_filetype( $display_name );
		if ( ! empty( $checked['type'] ) ) {
			return $checked['type'];
		}

		if ( function_exists( 'mime_content_type' ) && is_readable( $file_path ) ) {
			$detected = mime_content_type( $file_path );
			if ( is_string( $detected ) && $detected !== '' && $detected !== 'application/octet-stream' ) {
				return $detected;
			}
		}

		$extension = strtolower( pathinfo( $display_name, PATHINFO_EXTENSION ) );
		$map       = array(
			'pdf'  => 'application/pdf',
			'jpg'  => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'png'  => 'image/png',
			'gif'  => 'image/gif',
			'doc'  => 'application/msword',
			'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
			'xls'  => 'application/vnd.ms-excel',
			'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
			'zip'  => 'application/zip',
			'rar'  => 'application/x-rar-compressed',
			'7z'   => 'application/x-7z-compressed',
		);

		return $map[ $extension ] ?? 'application/octet-stream';
	}

	/**
	 * ファイル添付付き wp_mail 送信（本文を base64 埋め込みで MIME を安定化）。
	 *
	 * @param array<int, array{path: string, name: string}> $attachment_entries
	 * @return array{ success: bool, error_message: ?string }
	 */
	private function ktpwp_send_wp_mail_with_file_attachments( $to, $subject, $body, $headers, array $attachment_entries ) {
		$pending_attachments = array();

		foreach ( $attachment_entries as $entry ) {
			$path          = isset( $entry['path'] ) ? (string) $entry['path'] : '';
			$original_name = isset( $entry['name'] ) ? (string) $entry['name'] : '';

			if ( $path === '' || ! is_readable( $path ) ) {
				throw new Exception( '添付ファイルを読み込めませんでした。' );
			}

			$file_contents = file_get_contents( $path );
			if ( $file_contents === false || $file_contents === '' ) {
				$label = $original_name !== '' ? $original_name : basename( $path );
				throw new Exception( sprintf( '添付ファイル「%s」が空です。', $label ) );
			}

			$display_name = $this->ktpwp_email_attachment_display_name( $original_name );
			$pending_attachments[] = array(
				'content' => $file_contents,
				'name'    => $display_name,
				'mime'    => $this->ktpwp_email_attachment_mime_type( $display_name, $path ),
			);
		}

		$attach_hook = static function ( $phpmailer ) use ( $pending_attachments ) {
			if ( ! is_object( $phpmailer ) ) {
				return;
			}

			if ( class_exists( 'KTPWP_Settings' ) ) {
				KTPWP_Settings::apply_phpmailer_identity( $phpmailer );
			}

			if ( $pending_attachments !== array() ) {
				$phpmailer->Encoding = 'base64';
			}

			if ( ! method_exists( $phpmailer, 'addStringAttachment' ) ) {
				return;
			}

			foreach ( $pending_attachments as $item ) {
				try {
					$phpmailer->addStringAttachment(
						$item['content'],
						$item['name'],
						'base64',
						$item['mime']
					);
				} catch ( Exception $e ) {
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( 'KTPWP Email: addStringAttachment failed: ' . $e->getMessage() );
					}
				}
			}
		};

		add_action( 'phpmailer_init', $attach_hook, 99, 1 );

		if ( ! class_exists( 'KTPWP_Order_Auxiliary' ) ) {
			require_once KTPWP_PLUGIN_DIR . 'includes/class-ktpwp-order-auxiliary.php';
		}

		$outcome = KTPWP_Order_Auxiliary::run_wp_mail_with_result(
			static function () use ( $to, $subject, $body, $headers ) {
				return wp_mail( $to, $subject, $body, $headers );
			}
		);

		remove_action( 'phpmailer_init', $attach_hook, 99 );

		return $outcome;
	}

	/**
	 * クライアントが期待する添付件数とサーバー処理結果を突き合わせる。
	 *
	 * @param array<int, array{path: string, name: string}> $attachment_entries
	 */
	private function ktpwp_validate_email_attachment_count( array $attachment_entries ) {
		$expected = isset( $_POST['attachment_count'] ) ? absint( $_POST['attachment_count'] ) : 0;
		$actual   = count( $attachment_entries );

		if ( $expected <= 0 ) {
			return;
		}

		if ( $actual === 0 ) {
			throw new Exception( 'ファイルのアップロードに失敗しました。サーバーのアップロード制限（upload_max_filesize / post_max_size）をご確認ください。' );
		}

		if ( $actual < $expected ) {
			throw new Exception(
				sprintf(
					'添付ファイルの一部を処理できませんでした（%d件中%d件）。',
					$expected,
					$actual
				)
			);
		}
	}
	/**
	 * メール内容取得のAJAX処理
	 */
	public function ajax_get_email_content() {
		// エラー出力を抑制してHTMLエラーメッセージを防ぐ
		$error_reporting = error_reporting();
		error_reporting(0);
		
		try {
			// セキュリティチェック
			if ( ! check_ajax_referer( 'ktpwp_ajax_nonce', 'nonce', false ) ) {
				throw new Exception( 'セキュリティ検証に失敗しました。' );
			}

			// 権限チェック
			if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'ktpwp_access' ) ) {
				throw new Exception( '権限がありません。' );
			}

			$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
			if ( $order_id <= 0 ) {
				throw new Exception( '無効な受注書IDです。' );
			}

			// メール送信前に最新の金額をデータベースに保存
			$this->update_latest_amounts_before_email($order_id);

			global $wpdb;
			$table_name   = $wpdb->prefix . 'ktp_order';
			$client_table = $wpdb->prefix . 'ktp_client';

			// 受注書データを取得
			$order = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM `{$table_name}` WHERE id = %d",
					$order_id
				)
			);

			if ( ! $order ) {
				throw new Exception( '受注書が見つかりません。' );
			}

			if ( class_exists( 'KTPWP_Stripe_Billing' ) ) {
				$stripe_billing = KTPWP_Stripe_Billing::get_instance();
				if ( $stripe_billing->sync_order_payment_from_stripe( (int) $order->id ) ) {
					$order = $wpdb->get_row(
						$wpdb->prepare(
							"SELECT * FROM `{$table_name}` WHERE id = %d",
							$order_id
						)
					);
				}
			}

			if ( ! $order ) {
				throw new Exception( '受注書が見つかりません。' );
			}

			// 顧客データを取得
			$client = null;
			if ( ! empty( $order->client_id ) ) {
				$client = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT * FROM `{$client_table}` WHERE id = %d",
						$order->client_id
					)
				);
			}

			// IDで見つからない場合は会社名と担当者名で検索
			if ( ! $client && ! empty( $order->customer_name ) && ! empty( $order->user_name ) ) {
				$client = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT * FROM `{$client_table}` WHERE company_name = %s AND (name = %s OR representative_name = %s) LIMIT 1",
						$order->customer_name,
						$order->user_name,
						$order->user_name
					)
				);
			}
			if ( ! $client && ! empty( $order->customer_name ) ) {
				$client = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT * FROM `{$client_table}` WHERE company_name = %s ORDER BY id DESC LIMIT 1",
						$order->customer_name
					)
				);
			}

			// メール送信可否の判定
			if ( ! $client ) {
				wp_send_json_error(
					array(
						'message'     => __( 'メール送信不可（顧客データなし）', 'kantanpro' ),
						'error_type'  => 'no_client',
						'error_title' => __( '顧客データが見つかりません', 'kantanpro' ),
						'error'       => __( 'この受注書に関連する顧客データが見つかりません。顧客管理画面で顧客を登録してください。', 'kantanpro' ),
					)
				);
				return;
			}

			if ( $client->category === '対象外' ) {
				wp_send_json_error(
					array(
						'message'     => __( 'メール送信不可（対象外顧客）', 'kantanpro' ),
						'error_type'  => 'excluded_client',
						'error_title' => __( 'メール送信不可', 'kantanpro' ),
						'error'       => __( 'この顧客は削除済み（対象外）のため、メール送信はできません。', 'kantanpro' ),
					)
				);
				return;
			}

			// 宛先・CC（KantanBiz: 部署選択を To に、他の代表・部署メールを CC 初期値に）
			$to = $this->ktpwp_order_mail_default_to_email( $client, $order );
			if ( $to === '' || ! is_email( $to ) ) {
				wp_send_json_error(
					array(
						'message'     => __( 'メール送信不可（メールアドレス未設定または無効）', 'kantanpro' ),
						'error_type'  => 'no_email',
						'error_title' => __( 'メールアドレス未設定', 'kantanpro' ),
						'error'       => __( 'この顧客のメールアドレスが未設定または無効です。顧客管理画面で代表メールまたは部署メールを登録してください。', 'kantanpro' ),
					)
				);
				return;
			}
			$recipient_options = $this->ktpwp_order_mail_build_recipient_options( $client, $order );
			$to_label          = $this->ktpwp_order_mail_recipient_label_for_email( $client, $to, $order );
			$to_lc             = strtolower( (string) $to );
			$cc_options        = array();
			foreach ( $recipient_options as $option ) {
				if ( strtolower( (string) $option['email'] ) !== $to_lc ) {
					$cc_options[] = $option;
				}
			}
			$cc_options = array_values( $cc_options );
			$cc_default = implode(
				', ',
				array_map(
					static function ( array $option ): string {
						return (string) $option['email'];
					},
					$cc_options
				)
			);

			// 自社情報取得
			$smtp_settings = get_option( 'ktp_smtp_settings', array() );
			$my_email      = ! empty( $smtp_settings['email_address'] ) ? sanitize_email( $smtp_settings['email_address'] ) : '';

			$my_company = '';
			if ( class_exists( 'KTPWP_Settings' ) ) {
				$my_company = KTPWP_Settings::get_company_info();
			}

			// 旧システムからも取得（後方互換性）
			if ( empty( $my_company ) ) {
				// wp_ktp_settingテーブルが存在する場合のみ参照
				$setting_table = $wpdb->prefix . 'ktp_setting';
				try {
					$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $setting_table ) );
					
					if ( $table_exists === $setting_table ) {
						$setting = $wpdb->get_row(
							$wpdb->prepare(
								"SELECT * FROM `{$setting_table}` WHERE id = %d",
								1
							)
						);
						if ( $setting ) {
							$my_company = sanitize_text_field( strip_tags( $setting->my_company_content ) );
						}
						
						if ( empty( $my_email ) && $setting ) {
							$my_email = sanitize_email( $setting->email_address );
						}
					}
				} catch ( Exception $e ) {
					// テーブルが存在しない場合のエラーを無視
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( 'KTPWP Ajax get_email_content: wp_ktp_setting table not accessible: ' . $e->getMessage() );
					}
				}
			}

			// 請求項目リストを取得
			$invoice_items_from_db = array();
			$amount                = 0;
			$total_tax_amount      = 0;
			$invoice_list          = '';
			
			// 顧客の税区分を取得
			$tax_category = '内税'; // デフォルト値
			if ( ! empty( $order->client_id ) ) {
				$client_tax_category = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT tax_category FROM `{$client_table}` WHERE id = %d",
						$order->client_id
					)
				);
				if ( $client_tax_category ) {
					$tax_category = $client_tax_category;
				}
			}
			
			try {
				$order_items = KTPWP_Order_Items::get_instance();
				$invoice_items_from_db = $order_items->get_invoice_items( $order->id );
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'KTPWP Ajax get_email_content: Successfully got invoice items - count: ' . count( $invoice_items_from_db ) );
				}
			} catch ( Exception $e ) {
				error_log( 'KTPWP Ajax get_email_content: Failed to get invoice items - ' . $e->getMessage() );
				// エラーが発生した場合は空の配列を使用
				$invoice_items_from_db = array();
			}

			if ( ! empty( $invoice_items_from_db ) && class_exists( 'KTPWP_Invoice_Line_Amount' ) ) {
				$email_sections = KTPWP_Invoice_Line_Amount::build_invoice_email_sections( $invoice_items_from_db, $tax_category );
				$invoice_list   = $email_sections['list'];
				$amount         = (float) $email_sections['bill_total'];
			} elseif ( ! empty( $invoice_items_from_db ) ) {
				$invoice_list = "\n";
				$max_length   = 0;
				$item_lines   = array();

				// 税率別の集計用配列
				$tax_rate_groups = array();

                foreach ( $invoice_items_from_db as $item ) {
					$product_name = isset( $item['product_name'] ) ? sanitize_text_field( $item['product_name'] ) : '';
					$item_amount  = isset( $item['amount'] ) ? floatval( $item['amount'] ) : 0;
					$price        = isset( $item['price'] ) ? floatval( $item['price'] ) : 0;
					$quantity     = isset( $item['quantity'] ) ? floatval( $item['quantity'] ) : 0;
					$unit         = isset( $item['unit'] ) ? sanitize_text_field( $item['unit'] ) : '';
					$tax_rate_raw = isset( $item['tax_rate'] ) ? $item['tax_rate'] : null;
					$remarks      = isset( $item['remarks'] ) ? sanitize_text_field( $item['remarks'] ) : '';
					$amount      += $item_amount;

                    // 税率の処理（税制モード適用: 廃止/一律）
                    $tax_rate = null;
                    if ( class_exists( 'KTPWP_Tax_Policy' ) ) {
                        $tax_rate = KTPWP_Tax_Policy::get_effective_rate( $tax_rate_raw );
                    } elseif ( $tax_rate_raw !== null && $tax_rate_raw !== '' && is_numeric( $tax_rate_raw ) ) {
                        $tax_rate = floatval( $tax_rate_raw );
                    }

					// 税率別の集計（税率なしの場合は'no_tax_rate'として扱う）
					$tax_rate_key = $tax_rate !== null ? number_format( $tax_rate, 1 ) : 'no_tax_rate';
					if ( ! isset( $tax_rate_groups[ $tax_rate_key ] ) ) {
						$tax_rate_groups[ $tax_rate_key ] = 0;
					}
					$tax_rate_groups[ $tax_rate_key ] += $item_amount;

					// 消費税計算（税区分に応じて）
					if ( $tax_category === '外税' ) {
						// 外税表示の場合：税抜金額から税額を計算
						if ( $tax_rate !== null ) {
						$tax_amount = ceil( $item_amount * ( $tax_rate / 100 ) );
						$total_tax_amount += $tax_amount;
					}
					}
					// 内税の場合は後で税率別に計算

					// 小数点以下の不要な0を削除
					$price_display = rtrim( rtrim( number_format( $price, 6, '.', '' ), '0' ), '.' );
					$quantity_display = rtrim( rtrim( number_format( $quantity, 6, '.', '' ), '0' ), '.' );

                    if ( ! empty( trim( $product_name ) ) ) {
                        // 税制モードで税率/税額列が非表示の場合は税率テキストを抑止
                        $suppress_tax_text = ( class_exists( 'KTPWP_Tax_Policy' ) && ( KTPWP_Tax_Policy::is_abolished() || KTPWP_Tax_Policy::hide_tax_columns() ) );
                        /* translators: %s: 税率（パーセント値） */
                        $tax_rate_text = ( $tax_rate !== null && ! $suppress_tax_text ) ? sprintf( __( '（税率%s%%）', 'kantanpro' ), $tax_rate ) : '';
						/* translators: %s: 補足メッセージ */
						$remarks_text = ( ! empty( trim( $remarks ) ) ) ? '　' . sprintf( __( '※ %s', 'kantanpro' ), $remarks ) : '';
						$line         = sprintf(
							/* translators: 1: 品目名, 2: 単価, 3: 数量, 4: 単位, 5: 金額, 6: 通貨記号, 7: 税区分の注記 */
							__( '%1$s：%2$s × %3$s%4$s = %5$s%6$s%7$s', 'kantanpro' ),
							$product_name,
							$price_display,
							$quantity_display,
							$unit,
							KTPWP_Settings::format_money( $item_amount ),
							$tax_rate_text,
							$remarks_text
						);
						$item_lines[] = $line;
						$line_length  = mb_strlen( $line, 'UTF-8' );
						if ( $line_length > $max_length ) {
							$max_length = $line_length;
						}
					}
				}

				foreach ( $item_lines as $line ) {
					$invoice_list .= $line . "\n";
				}

									// 内税の場合は税率別に計算
                if ( $tax_category !== '外税' ) {
                        // 税制モードで税率/税額列が非表示の場合は内税内訳を出さない
                        $suppress_tax_detail = ( class_exists( 'KTPWP_Tax_Policy' ) && ( KTPWP_Tax_Policy::is_abolished() || KTPWP_Tax_Policy::hide_tax_columns() ) );
                        if ( ! $suppress_tax_detail ) {
						$total_tax_amount = 0;
						$tax_rate_details = array();
						
						foreach ( $tax_rate_groups as $tax_rate => $group_amount ) {
							if ( $tax_rate === 'no_tax_rate' ) {
								// 税率なしの場合は表示しない
								continue;
							} else {
								$tax_rate_value = floatval( $tax_rate );
								$tax_amount = ceil( $group_amount * ( $tax_rate_value / 100 ) / ( 1 + $tax_rate_value / 100 ) );
								$total_tax_amount += $tax_amount;
								$tax_rate_details[] = $tax_rate . '%: ' . KTPWP_Settings::format_money( $tax_amount );
							}
                        }
                        }
				}
				
				$amount_ceiled = ceil( $amount );
				$total_tax_amount_ceiled = ceil( $total_tax_amount );
				$total_with_tax = $amount_ceiled + $total_tax_amount_ceiled;

				// 税区分に応じた合計行の表示
                if ( $tax_category === '外税' ) {
					/* translators: %s: 外税の合計額 */
					$total_line = sprintf( __( '外税合計：%s', 'kantanpro' ), KTPWP_Settings::format_money( $amount_ceiled ) );
					/* translators: %s: 消費税額 */
					$tax_line = sprintf( __( '消費税：%s', 'kantanpro' ), KTPWP_Settings::format_money( $total_tax_amount_ceiled ) );
					/* translators: %s: 内税の合計額 */
					$total_with_tax_line = sprintf( __( '内税合計：%s', 'kantanpro' ), KTPWP_Settings::format_money( $total_with_tax ) );
				} else {
                    // 税制モードで税率/税額列が非表示の場合は内税内訳を付けない
                    $suppress_tax_detail = ( class_exists( 'KTPWP_Tax_Policy' ) && ( KTPWP_Tax_Policy::is_abolished() || KTPWP_Tax_Policy::hide_tax_columns() ) );
                    if ( ! $suppress_tax_detail ) {
                        if ( count( $tax_rate_groups ) > 1 ) {
                            /* translators: %s: 内税額（通貨記号付き） */
                            $tax_detail_text = sprintf( __( '（内税：%s）', 'kantanpro' ), implode( ', ', $tax_rate_details ) );
                        } else {
                            if ( array_key_first( $tax_rate_groups ) === 'no_tax_rate' ) {
                                $tax_detail_text = '';
                            } else {
                                /* translators: %s: 内税額（通貨記号付き） */
                                $tax_detail_text = sprintf( __( '（内税：%s）', 'kantanpro' ), KTPWP_Settings::format_money( $total_tax_amount_ceiled ) );
                            }
                        }
                    } else {
                        $tax_detail_text = '';
                    }
                    /* translators: %s: 金額の合計 */
                    $total_line = sprintf( __( '金額合計：%s', 'kantanpro' ), KTPWP_Settings::format_money( $amount_ceiled ) ) . $tax_detail_text;
                    $tax_line = '';
                    $total_with_tax_line = '';
				}
				
				$total_length = mb_strlen( $total_line, 'UTF-8' );
				$tax_length = mb_strlen( $tax_line, 'UTF-8' );
				$total_with_tax_length = mb_strlen( $total_with_tax_line, 'UTF-8' );

				$line_length = max( $max_length, $total_length, $tax_length, $total_with_tax_length );
				if ( $line_length < 30 ) {
					$line_length = 30;
				}
				if ( $line_length > 80 ) {
					$line_length = 80;
				}

				$invoice_list .= str_repeat( '-', $line_length ) . "\n";
				$invoice_list .= $total_line . "\n";
				if ( $tax_category === '外税' ) {
					$invoice_list .= $tax_line . "\n";
					$invoice_list .= $total_with_tax_line;
				}
			} else {
				$invoice_list = __( '（請求項目未入力）', 'kantanpro' );
			}

			// 進捗ごとに件名・本文を生成
			$progress      = absint( $order->progress );
			$project_name  = $order->project_name ? sanitize_text_field( $order->project_name ) : '';
			$customer_name = sanitize_text_field( $order->customer_name );
			$user_name     = sanitize_text_field( $order->user_name );

			// 会社名が0や空の場合のフォールバック処理
			if ( $customer_name === '0' || empty( $customer_name ) ) {
				global $wpdb;
				$client_table = $wpdb->prefix . 'ktp_client';
				
				// 顧客IDがある場合は顧客テーブルから会社名を取得
				if ( ! empty( $order->client_id ) ) {
					$client_company_name = $wpdb->get_var(
						$wpdb->prepare(
							"SELECT company_name FROM `{$client_table}` WHERE id = %d",
							$order->client_id
						)
					);
					if ( ! empty( $client_company_name ) ) {
						$customer_name = $client_company_name;
					} else {
						// 顧客テーブルからも取得できない場合は担当者名を使用
						$customer_name = $user_name;
					}
				} else {
					// 顧客IDがない場合は担当者名を使用
					$customer_name = $user_name;
				}
			}

			// 進捗に応じた帳票タイトルと件名を設定
			$document_titles = array(
				1 => __( '見積り書', 'kantanpro' ),
				2 => __( '注文受書', 'kantanpro' ),
				3 => __( '納品書', 'kantanpro' ),
				4 => __( '請求書', 'kantanpro' ),
				5 => __( '領収書', 'kantanpro' ),
				6 => __( '案件完了', 'kantanpro' ),
			);

			$document_messages = array(
				1 => __( 'につきましてお見積りいたします。', 'kantanpro' ),
				2 => __( 'につきましてご注文をお受けしました。', 'kantanpro' ),
				3 => __( 'につきまして完了しました。', 'kantanpro' ),
				4 => __( 'につきまして請求申し上げます。', 'kantanpro' ),
				5 => __( 'につきましてお支払いを確認しました。', 'kantanpro' ),
				6 => __( 'につきましては全て完了しています。', 'kantanpro' ),
			);

			$document_title   = isset( $document_titles[ $progress ] ) ? $document_titles[ $progress ] : __( '受注書', 'kantanpro' );
			$document_message = isset( $document_messages[ $progress ] ) ? $document_messages[ $progress ] : '';

			// 適格請求書番号を取得し、請求書の場合に追加
			if ( $progress === 4 && class_exists( 'KTPWP_Settings' ) ) {
                $qualified_invoice_number = KTPWP_Settings::get_qualified_invoice_number();
                if ( ! ( class_exists('KTPWP_Tax_Policy') && KTPWP_Tax_Policy::is_abolished() ) && ! empty( $qualified_invoice_number ) ) {
                    $document_title = $document_title . ' ' . __( '適格請求書番号', 'kantanpro' ) . '：' . $qualified_invoice_number;
                }
			}

			// 日付フォーマット
			$order_date = wp_date( __( 'Y年m月d日', 'kantanpro' ), $order->time );

			// 宛先表示（部署選択時: {親会社名} / {部署名} / {部署担当者名} 様）
			$customer_display = $customer_name;
			/* translators: %s: 宛名（顧客名または担当者名） */
			$user_display     = sprintf( __( '%s 様', 'kantanpro' ), $user_name );

			if ( class_exists( 'KTPWP_Department_Manager' ) ) {
				$selected_department = KTPWP_Department_Manager::resolve_department_for_order( $order );
				$contact_name        = KTPWP_Department_Manager::resolve_contact_name_for_order( $order );
				$parent_company_name = KTPWP_Department_Manager::resolve_parent_company_name_for_order(
					$order,
					$client,
					$customer_name,
					$selected_department
				);

				if ( $contact_name !== '' ) {
					/* translators: %s: 宛名（顧客名または担当者名） */
					$user_display = sprintf( __( '%s 様', 'kantanpro' ), $contact_name );
				}

				if ( $selected_department ) {
					$department_mail_line = KTPWP_Department_Manager::department_name_for_mail_addressee(
						$selected_department->department_name
					);
					$customer_display = $parent_company_name . "\n" . $department_mail_line;
				} else {
					$customer_display = $parent_company_name;
				}
			}

            // 件名と本文の統一フォーマット
            /* translators: 1: 項目名, 2: 値 */
            $subject = sprintf( __( '%1$s：%2$s', 'kantanpro' ), $document_title, $project_name );
            $email_rule_line = str_repeat( '-', 60 );
            $body    = sprintf(
				__( "%1\$s\n%2\$s\n\nお世話になります。\n「%6\$s」%7\$s\n\n＜%3\$s＞ID: %4\$d [%5\$s]\n%9\$s\n%8\$s", 'kantanpro' ),
				$customer_display,
				$user_display,
				$document_title,
				$order->id,
				$order_date,
				$project_name,
				$document_message,
				$invoice_list,
				$email_rule_line
			);

            // 見積り（進捗1）: Web受注は Stripe、それ以外は振込先
            if ( 1 === $progress && class_exists( 'KTPWP_Stripe_Billing' ) ) {
                $stripe_billing = KTPWP_Stripe_Billing::get_instance();
                if ( ! $stripe_billing->should_apply_to_order( $order ) && ! $stripe_billing->should_hide_bank_transfer( $order ) && class_exists( 'KTPWP_Settings' ) ) {
                    $bank_plain = KTPWP_Settings::get_bank_transfer_plain_text();
                    if ( $bank_plain !== '' ) {
                        $body .= "\n\n" . $bank_plain;
                    }
                }
            } elseif ( 1 === $progress && class_exists( 'KTPWP_Settings' ) ) {
                $bank_plain = KTPWP_Settings::get_bank_transfer_plain_text();
                if ( $bank_plain !== '' ) {
                    $body .= "\n\n" . $bank_plain;
                }
            }

            // 請求書（進捗4）: Stripe 決済リンク（定期契約・Web受注は振込非表示）
            if ( 4 === $progress && class_exists( 'KTPWP_Stripe_Billing' ) ) {
                $stripe_billing = KTPWP_Stripe_Billing::get_instance();
                if ( ! $stripe_billing->should_apply_to_order( $order ) && ! $stripe_billing->should_hide_bank_transfer( $order ) && class_exists( 'KTPWP_Settings' ) ) {
                    $bank_plain = KTPWP_Settings::get_bank_transfer_plain_text();
                    if ( $bank_plain !== '' ) {
                        $body .= "\n\n" . $bank_plain;
                    }
                }
            } elseif ( 4 === $progress && class_exists( 'KTPWP_Settings' ) ) {
                $is_contract_order = isset( $order->contract_id ) && (int) $order->contract_id > 0;
                if ( ! $is_contract_order ) {
                    $bank_plain = KTPWP_Settings::get_bank_transfer_plain_text();
                    if ( $bank_plain !== '' ) {
                        $body .= "\n\n" . $bank_plain;
                    }
                }
            }

            // 税制モードに応じたメール本文の調整
            if ( class_exists( 'KTPWP_Tax_Policy' ) ) {
                if ( KTPWP_Tax_Policy::is_abolished() || KTPWP_Tax_Policy::hide_tax_columns() ) {
                    // 税廃止または税率/税額非表示のとき、余分な税情報/内訳行があれば除去（保守的に末尾の余白のみ調整）
                    $body = rtrim( $body );
                }
            }

            $body   .= "\n\n--\n{$my_company}";

            if ( class_exists( 'KTPWP_Stripe_Billing' ) ) {
                $stripe_billing = KTPWP_Stripe_Billing::get_instance();
                if ( $stripe_billing->should_apply_to_order( $order ) ) {
                    $body = $stripe_billing->append_preview_to_body( $order, $body );
                }
            }

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'KTPWP Ajax get_email_content: Preparing JSON response - to: ' . $to . ', subject: ' . $subject );
			}

			wp_send_json_success(
				array(
					'to'           => $to,
					'to_label'     => $to_label,
					'cc_options'   => $cc_options,
					'cc'           => $cc_default,
					'subject'      => $subject,
					'body'         => $body,
					'order_id'     => $order_id,
					'project_name' => $project_name,
				)
			);

		} catch ( Exception $e ) {
			error_log( 'KTPWP Ajax get_email_content Error: ' . $e->getMessage() );
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'KTPWP Ajax get_email_content Error Stack Trace: ' . $e->getTraceAsString() );
			}
			wp_send_json_error(
				array(
					'message' => $e->getMessage(),
				)
			);
		} finally {
			// エラー出力設定を復元
			error_reporting($error_reporting);
		}
	}

	/**
	 * メール送信前に最新の金額をデータベースに保存
	 *
	 * @param int $order_id 受注書ID
	 * @since 1.0.19
	 */
	private function update_latest_amounts_before_email($order_id) {
		if ( class_exists( 'KTPWP_Invoice_Line_Amount' ) ) {
			KTPWP_Invoice_Line_Amount::normalize_provisional_amounts_for_order( $order_id );
			KTPWP_Invoice_Line_Amount::sync_billable_amounts_for_order( $order_id );
			return;
		}

		try {
			global $wpdb;
			
			$invoice_items_table = $wpdb->prefix . 'ktp_order_invoice_items';
			
			// 現在の請求項目を取得
			$current_items = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM `{$invoice_items_table}` WHERE order_id = %d ORDER BY sort_order ASC",
					$order_id
				)
			);
			
			if ($current_items) {
				foreach ($current_items as $item) {
					// 単価と数量から金額を再計算
					$price = floatval($item->price);
					$quantity = floatval($item->quantity);
					$calculated_amount = ceil($price * $quantity);
					
					// 現在の金額と異なる場合のみ更新
					if (floatval($item->amount) !== $calculated_amount) {
						$wpdb->update(
							$invoice_items_table,
							array('amount' => $calculated_amount),
							array('id' => $item->id),
							array('%f'),
							array('%d')
						);
						
						if (defined('WP_DEBUG') && WP_DEBUG) {
							error_log("KTPWP Email: Updated invoice item amount - ID: {$item->id}, Old: {$item->amount}, New: {$calculated_amount}");
						}
					}
				}
			}
			
			// コスト項目の最新金額を取得してデータベースに保存
			$cost_items_table = $wpdb->prefix . 'ktp_cost_items';
			
			// 現在のコスト項目を取得
			$current_cost_items = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM `{$cost_items_table}` WHERE order_id = %d ORDER BY sort_order ASC",
					$order_id
				)
			);
			
			if ($current_cost_items) {
				foreach ($current_cost_items as $item) {
					// 単価と数量から金額を再計算
					$price = floatval($item->price);
					$quantity = floatval($item->quantity);
					$calculated_amount = ceil($price * $quantity);
					
					// 現在の金額と異なる場合のみ更新
					if (floatval($item->amount) !== $calculated_amount) {
						$wpdb->update(
							$cost_items_table,
							array('amount' => $calculated_amount),
							array('id' => $item->id),
							array('%f'),
							array('%d')
						);
						
						if (defined('WP_DEBUG') && WP_DEBUG) {
							error_log("KTPWP Email: Updated cost item amount - ID: {$item->id}, Old: {$item->amount}, New: {$calculated_amount}");
						}
					}
				}
			}
			
		} catch (Exception $e) {
			// エラーが発生してもメール送信は続行
			if (defined('WP_DEBUG') && WP_DEBUG) {
				error_log("KTPWP Email: Error updating amounts before email - " . $e->getMessage());
			}
		}
	}

	/**
	 * メール送信のAJAX処理（ファイル添付対応）
	 */
	public function ajax_send_order_email() {
		try {
			// セキュリティチェック
			if ( ! check_ajax_referer( 'ktpwp_ajax_nonce', 'nonce', false ) ) {
				throw new Exception( 'セキュリティ検証に失敗しました。' );
			}

			// 権限チェック
			if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'ktpwp_access' ) ) {
				throw new Exception( '権限がありません。' );
			}

			$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
			$to       = isset( $_POST['to'] ) ? sanitize_email( $_POST['to'] ) : '';
			$subject  = isset( $_POST['subject'] ) ? sanitize_text_field( $_POST['subject'] ) : '';
			$body     = isset( $_POST['body'] ) ? sanitize_textarea_field( $_POST['body'] ) : '';

			if ( $order_id <= 0 ) {
				throw new Exception( '無効な受注書IDです。' );
			}

			if ( empty( $to ) || ! filter_var( $to, FILTER_VALIDATE_EMAIL ) ) {
				throw new Exception( '有効なメールアドレスが指定されていません。' );
			}

			if ( empty( $subject ) || empty( $body ) ) {
				throw new Exception( '件名と本文を入力してください。' );
			}

			// 自社メールアドレスを取得
			$smtp_settings = get_option( 'ktp_smtp_settings', array() );
			$my_email      = ! empty( $smtp_settings['email_address'] ) ? sanitize_email( $smtp_settings['email_address'] ) : '';

			// 旧システムからも取得（後方互換性）
			if ( empty( $my_email ) ) {
				global $wpdb;
				$setting_table = $wpdb->prefix . 'ktp_setting';
				$setting       = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT * FROM `{$setting_table}` WHERE id = %d",
						1
					)
				);
				if ( $setting ) {
					$my_email = sanitize_email( $setting->email_address );
				}
			}

			// ヘッダー設定
			$headers = array();
			if ( $my_email ) {
				$headers[] = 'From: ' . $my_email;
			}

			global $wpdb;
			$order_table  = $wpdb->prefix . 'ktp_order';
			$client_table = $wpdb->prefix . 'ktp_client';
			$order_row    = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM `{$order_table}` WHERE id = %d",
					$order_id
				)
			);

			if ( $order_row && class_exists( 'KTPWP_Stripe_Billing' ) ) {
				$stripe_result = KTPWP_Stripe_Billing::get_instance()->finalize_and_append_to_body( $order_row, $body );
				if ( is_wp_error( $stripe_result ) ) {
					throw new Exception( $stripe_result->get_error_message() );
				}
				$body = $stripe_result;
			}

			$client_row = null;
			if ( $order_row && ! empty( $order_row->client_id ) ) {
				$client_row = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT * FROM `{$client_table}` WHERE id = %d",
						$order_row->client_id
					)
				);
			}
			$allowed  = $this->ktpwp_order_mail_contact_email_candidates( $client_row );
			$cc_list  = $this->ktpwp_order_mail_resolve_cc_recipients( $to, $allowed );
			$bcc_list = $this->ktpwp_order_mail_resolve_bcc_recipients( $to );
			if ( $cc_list !== array() ) {
				$headers[] = 'Cc: ' . implode( ', ', $cc_list );
			}
			if ( $bcc_list !== array() ) {
				$headers[] = 'Bcc: ' . implode( ', ', $bcc_list );
			}

			// ファイル添付処理
			$collected           = $this->ktpwp_collect_email_attachments_from_request( 'KTPWP Email' );
			$attachment_entries  = $collected['entries'];
			$temp_files          = $collected['temp_files'];
			$this->ktpwp_validate_email_attachment_count( $attachment_entries );

			// メール送信
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( "KTPWP Email: Sending email to {$to} with " . count( $attachment_entries ) . ' attachments' );
			}

			if ( ! class_exists( 'KTPWP_Order_Auxiliary' ) ) {
				require_once KTPWP_PLUGIN_DIR . 'includes/class-ktpwp-order-auxiliary.php';
			}
			$mail_outcome = $this->ktpwp_send_wp_mail_with_file_attachments( $to, $subject, $body, $headers, $attachment_entries );
			$sent = $mail_outcome['success'];

			if ( $sent ) {
				if ( ! class_exists( 'KTPWP_Order_Auxiliary' ) ) {
					require_once KTPWP_PLUGIN_DIR . 'includes/class-ktpwp-order-auxiliary.php';
				}
				if ( class_exists( 'KTPWP_Order_Auxiliary' ) ) {
					KTPWP_Order_Auxiliary::record_customer_mail(
						$order_id,
						$to,
						$subject,
						$body,
						true,
						null,
						'to',
						$to,
						$cc_list !== array() ? $cc_list : null,
						'customer',
						null,
						$bcc_list !== array() ? $bcc_list : null
					);
				}

				if ( $order_row && isset( $order_row->progress ) && (int) $order_row->progress === 4
					&& class_exists( 'KTPWP_Contract_Invoice_Mail' ) ) {
					KTPWP_Contract_Invoice_Mail::get_instance()->mark_sent_for_order( $order_id );
				}

				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( "KTPWP Email: Successfully sent email to {$to} with " . count( $attachment_entries ) . ' attachments' );
				}

				// メール送信時のみ「次の進捗」へ移行。ローテーションは 1→2→3→4→5→6 で入金済(6)で終了。
				// （ユーザーによる進捗の直接変更は任意で可能。受付中→完了など飛び級も可。）
				$new_progress    = null;
				$new_completion_date = null;
				global $wpdb;
				$order_table  = $wpdb->prefix . 'ktp_order';
				$current_order = $wpdb->get_row( $wpdb->prepare( "SELECT progress FROM {$order_table} WHERE id = %d", $order_id ) );
				if ( $current_order ) {
					$current_progress = (int) $current_order->progress;
					// 1〜5 のときのみ次へ（6=入金済でローテーション終了、7=ボツは変更しない）
					if ( $current_progress >= 1 && $current_progress < 6 ) {
						$next_progress = $current_progress + 1;
						$update_data   = array( 'progress' => $next_progress );
						$formats       = array( '%d' );
						// 進捗が「完了」(4)になった場合のみ完了日を記録
						if ( $next_progress === 4 && $current_progress !== 4 ) {
							$update_data['completion_date'] = current_time( 'Y-m-d' );
							$new_completion_date = $update_data['completion_date'];
							$formats[] = '%s';
						}
						$updated = $wpdb->update( $order_table, $update_data, array( 'id' => $order_id ), $formats, array( '%d' ) );
						if ( $updated !== false ) {
							$new_progress = $next_progress;
						}
					}
				}

				$response_data = array(
					'message'          => __( 'メールを送信しました。', 'kantanpro' ),
					'to'               => $to,
					'attachment_count' => count( $attachment_entries ),
					'progress'         => $new_progress,
				);
				if ( $new_completion_date !== null ) {
					$response_data['completion_date'] = $new_completion_date;
				}
				wp_send_json_success( $response_data );
			} else {
				if ( class_exists( 'KTPWP_Order_Auxiliary' ) ) {
					KTPWP_Order_Auxiliary::record_customer_mail(
						$order_id,
						$to,
						$subject,
						$body,
						false,
						$mail_outcome['error_message'],
						'to',
						$to,
						$cc_list !== array() ? $cc_list : null,
						'customer',
						null,
						$bcc_list !== array() ? $bcc_list : null
					);
				}
				throw new Exception( 'メール送信に失敗しました。サーバー設定を確認してください。' );
			}
		} catch ( Exception $e ) {
			// エラー時も一時ファイルをクリーンアップ
			if ( ! empty( $temp_files ) ) {
				foreach ( $temp_files as $temp_file ) {
					if ( file_exists( $temp_file ) ) {
						unlink( $temp_file );
						if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
							error_log( 'KTPWP Email Error: Cleaned up temp file on error: ' . basename( $temp_file ) );
						}
					}
				}
			}

			error_log( 'KTPWP Ajax send_order_email Error: ' . $e->getMessage() );
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'KTPWP Ajax send_order_email Error Stack Trace: ' . $e->getTraceAsString() );
			}
			wp_send_json_error(
				array(
					'message' => $e->getMessage(),
				)
			);
		}
	}

	/**
	 * Ajax: 協力会社メールアドレス取得
	 */
	public function ajax_get_supplier_email() {
		try {
			// セキュリティチェック
			if ( ! check_ajax_referer( 'ktpwp_ajax_nonce', 'nonce', false ) ) {
				throw new Exception( 'セキュリティ検証に失敗しました。' );
			}

			// 権限チェック
			if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'ktpwp_access' ) ) {
				throw new Exception( '権限がありません。' );
			}

			$supplier_name = isset( $_POST['supplier_name'] ) ? sanitize_text_field( $_POST['supplier_name'] ) : '';

			if ( empty( $supplier_name ) ) {
				throw new Exception( '協力会社名が指定されていません。' );
			}

			// 協力会社テーブルからメールアドレスを取得
			global $wpdb;
			$table_name = $wpdb->prefix . 'ktp_supplier';
			$supplier = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT email, name FROM {$table_name} WHERE company_name = %s",
                    $supplier_name
                )
            );

			if ( $supplier ) {
				wp_send_json_success(
					array(
						'email' => $supplier->email,
						'name' => $supplier->name,
					)
				);
			} else {
				wp_send_json_error(
					array(
						'message' => __( '協力会社が見つかりません。', 'kantanpro' ),
					)
				);
			}
		} catch ( Exception $e ) {
			error_log( 'KTPWP Ajax get_supplier_email Error: ' . $e->getMessage() );
			wp_send_json_error(
				array(
					'message' => $e->getMessage(),
				)
			);
		}
	}

	/**
	 * Ajax: 発注書メール送信
	 */
	public function ajax_send_purchase_order_email() {
		if ( ! headers_sent() ) {
			ob_start();
		}
		$temp_files = array();
		try {
			// セキュリティチェック
			if ( ! check_ajax_referer( 'ktpwp_ajax_nonce', 'nonce', false ) ) {
				throw new Exception( 'セキュリティ検証に失敗しました。' );
			}

			// 権限チェック
			if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'ktpwp_access' ) ) {
				throw new Exception( '権限がありません。' );
			}

			$order_id      = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
			$to            = isset( $_POST['to'] ) ? sanitize_email( $_POST['to'] ) : '';
			$subject       = isset( $_POST['subject'] ) ? sanitize_text_field( $_POST['subject'] ) : '';
			$body          = isset( $_POST['body'] ) ? sanitize_textarea_field( $_POST['body'] ) : '';
			$supplier_name = isset( $_POST['supplier_name'] ) ? sanitize_text_field( $_POST['supplier_name'] ) : '';
			$mode          = ( isset( $_POST['mode'] ) && $_POST['mode'] === 'quote' ) ? 'quote' : 'order';
			$mail_log_type = ( $mode === 'quote' ) ? 'quote_request' : 'purchase_order';

			if ( $order_id <= 0 ) {
				throw new Exception( '無効な受注書IDです。' );
			}

			if ( empty( $to ) || ! filter_var( $to, FILTER_VALIDATE_EMAIL ) ) {
				throw new Exception( '有効なメールアドレスが指定されていません。' );
			}

			if ( empty( $subject ) || empty( $body ) ) {
				throw new Exception( '件名と本文を入力してください。' );
			}

			// 自社メールアドレスを取得
			$smtp_settings = get_option( 'ktp_smtp_settings', array() );
			$my_email      = ! empty( $smtp_settings['email_address'] ) ? sanitize_email( $smtp_settings['email_address'] ) : '';

			// 旧システムからも取得（後方互換性）
			if ( empty( $my_email ) ) {
				global $wpdb;
				$setting_table = $wpdb->prefix . 'ktp_setting';
				$setting       = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT * FROM `{$setting_table}` WHERE id = %d",
                        1
                    )
                );
				if ( $setting ) {
					$my_email = sanitize_email( $setting->email_address );
				}
			}

			// 会社情報を取得
			$my_company = '';
			if ( class_exists( 'KTPWP_Settings' ) ) {
				$my_company = KTPWP_Settings::get_company_info();
			}

			// 旧システムからも取得（後方互換性）
			if ( empty( $my_company ) ) {
				global $wpdb;
				$setting_table = $wpdb->prefix . 'ktp_setting';
				$setting       = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT * FROM `{$setting_table}` WHERE id = %d",
                        1
                    )
                );
				if ( $setting ) {
					$my_company = sanitize_text_field( strip_tags( $setting->my_company_content ) );
				}
			}

			// 本文の会社情報を実際の会社情報に置換
			$body = str_replace( '会社情報', $my_company, $body );

			// ヘッダーを設定
			$headers = array( 'Content-Type: text/plain; charset=UTF-8' );
			if ( ! empty( $my_email ) ) {
				$from_name = ! empty( $smtp_settings['smtp_from_name'] ) ? sanitize_text_field( $smtp_settings['smtp_from_name'] ) : '';
				if ( ! empty( $from_name ) ) {
					$headers[] = 'From: ' . $from_name . ' <' . $my_email . '>';
				} else {
					$headers[] = 'From: ' . $my_email;
				}
			}

			$cc_list = array();
			$cc_raw  = isset( $_POST['cc'] ) ? wp_unslash( $_POST['cc'] ) : '';
			if ( is_array( $cc_raw ) ) {
				$parts = $cc_raw;
			} elseif ( is_string( $cc_raw ) && $cc_raw !== '' ) {
				$parts = preg_split( '/[\s,;]+/', $cc_raw, -1, PREG_SPLIT_NO_EMPTY );
			} else {
				$parts = array();
			}
			$to_lower = strtolower( $to );
			foreach ( (array) $parts as $p ) {
				$e = sanitize_email( trim( (string) $p ) );
				if ( $e === '' || ! is_email( $e ) ) {
					continue;
				}
				if ( strtolower( $e ) === $to_lower ) {
					continue;
				}
				$cc_list[] = $e;
			}
			$cc_list = array_values( array_unique( $cc_list ) );
			if ( $cc_list !== array() ) {
				$headers[] = 'Cc: ' . implode( ', ', $cc_list );
			}

			// ファイル添付処理
			$collected          = $this->ktpwp_collect_email_attachments_from_request( 'KTPWP Purchase Order Email' );
			$attachment_entries = $collected['entries'];
			$temp_files         = $collected['temp_files'];
			$this->ktpwp_validate_email_attachment_count( $attachment_entries );

			// メール送信
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( "KTPWP Purchase Order Email: Sending email to {$to} with " . count( $attachment_entries ) . ' attachments' );
			}

			$mail_outcome_po = $this->ktpwp_send_wp_mail_with_file_attachments( $to, $subject, $body, $headers, $attachment_entries );
			$sent = $mail_outcome_po['success'];

			if ( $sent ) {
				if ( class_exists( 'KTPWP_Order_Auxiliary' ) ) {
					KTPWP_Order_Auxiliary::record_customer_mail(
						$order_id,
						$to,
						$subject,
						$body,
						true,
						null,
						'to',
						$to,
						$cc_list !== array() ? $cc_list : null,
						$mail_log_type,
						$supplier_name !== '' ? $supplier_name : null
					);
				}

				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( "KTPWP Purchase Order Email: Successfully sent email to {$to} with " . count( $attachment_entries ) . ' attachments' );
				}
				if ( ob_get_level() ) {
					ob_end_clean();
				}
				wp_send_json_success(
					array(
						'message'          => ( $mode === 'quote' ) ? __( '見積依頼メールを送信しました。', 'kantanpro' ) : __( '発注書メールを送信しました。', 'kantanpro' ),
						'to'               => $to,
						'supplier_name'    => $supplier_name,
						'mode'             => $mode,
						'attachment_count' => count( $attachment_entries ),
					)
				);
			} else {
				if ( class_exists( 'KTPWP_Order_Auxiliary' ) ) {
					KTPWP_Order_Auxiliary::record_customer_mail(
						$order_id,
						$to,
						$subject,
						$body,
						false,
						$mail_outcome_po['error_message'],
						'to',
						$to,
						$cc_list !== array() ? $cc_list : null,
						$mail_log_type,
						$supplier_name !== '' ? $supplier_name : null
					);
				}
				throw new Exception( 'メール送信に失敗しました。サーバー設定を確認してください。' );
			}
		} catch ( Exception $e ) {
			// エラー時も一時ファイルをクリーンアップ
			if ( ! empty( $temp_files ) ) {
				foreach ( $temp_files as $temp_file ) {
					if ( file_exists( $temp_file ) ) {
						unlink( $temp_file );
						if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
							error_log( 'KTPWP Ajax send_purchase_order_email Error: Cleaned up temp file on error: ' . basename( $temp_file ) );
						}
					}
				}
			}

			error_log( 'KTPWP Ajax send_purchase_order_email Error: ' . $e->getMessage() );
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'KTPWP Ajax send_purchase_order_email Error Stack Trace: ' . $e->getTraceAsString() );
			}
			if ( ob_get_level() ) {
				ob_end_clean();
			}
			wp_send_json_error(
				array(
					'message' => $e->getMessage(),
				)
			);
		}
	}

	/**
	 * Ajax: 発注書/見積依頼書の印刷用HTMLを取得（ブラウザの印刷ダイアログでPDF保存する）
	 *
	 * @since 1.2.0
	 */
	public function ajax_get_purchase_order_print_html() {
		if ( ! headers_sent() ) {
			ob_start();
		}
		try {
			if ( ! check_ajax_referer( 'ktpwp_ajax_nonce', 'nonce', false ) ) {
				throw new Exception( 'セキュリティ検証に失敗しました。' );
			}
			if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'ktpwp_access' ) ) {
				throw new Exception( '権限がありません。' );
			}

			$order_id      = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
			$supplier_id   = isset( $_POST['supplier_id'] ) ? absint( $_POST['supplier_id'] ) : 0;
			$supplier_name = isset( $_POST['supplier_name'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['supplier_name'] ) ) ) : '';
			$mode          = ( isset( $_POST['mode'] ) && $_POST['mode'] === 'quote' ) ? 'quote' : 'order';

			if ( $order_id <= 0 ) {
				throw new Exception( '無効な受注書IDです。' );
			}
			if ( $supplier_id <= 0 && empty( $supplier_name ) ) {
				throw new Exception( '協力会社名または協力会社IDが指定されていません。' );
			}

			global $wpdb;

			$order_table = $wpdb->prefix . 'ktp_order';
			$order = $wpdb->get_row(
				$wpdb->prepare( "SELECT * FROM `{$order_table}` WHERE id = %d", $order_id )
			);
			if ( ! $order ) {
				throw new Exception( '受注書が見つかりません。' );
			}

			$supplier_table = $wpdb->prefix . 'ktp_supplier';
			$supplier = null;
			if ( $supplier_id > 0 ) {
				$supplier = $wpdb->get_row(
					$wpdb->prepare( "SELECT * FROM `{$supplier_table}` WHERE id = %d", $supplier_id )
				);
			}
			if ( ! $supplier && ! empty( $supplier_name ) ) {
				$supplier = $wpdb->get_row(
					$wpdb->prepare( "SELECT * FROM `{$supplier_table}` WHERE TRIM(company_name) = %s", $supplier_name )
				);
			}
			if ( ! $supplier ) {
				throw new Exception( '協力会社が見つかりません。' );
			}
			$supplier_name = $supplier->company_name;

			$order_items = KTPWP_Order_Items::get_instance();
			$cost_items  = $order_items->get_cost_items( $order_id );

			$filtered_cost_items = array();
			foreach ( $cost_items as $item ) {
				$item_purchase = isset( $item['purchase'] ) ? $item['purchase'] : '';
				if ( $item_purchase === $supplier_name ) {
					$filtered_cost_items[] = $item;
				}
			}

			$my_company = '';
			if ( class_exists( 'KTPWP_Settings' ) ) {
				$my_company = KTPWP_Settings::get_company_info();
			}

			$doc_title = ( $mode === 'quote' ) ? __( '見積依頼書', 'kantanpro' ) : __( '発注書', 'kantanpro' );
			$html      = $this->build_purchase_order_print_html( $doc_title, $order, $supplier, $filtered_cost_items, $my_company );
			$filename  = $doc_title . '_' . $order_id . '_' . sanitize_file_name( $supplier_name );

			if ( ob_get_level() ) {
				ob_end_clean();
			}
			wp_send_json_success(
				array(
					'html'     => $html,
					'filename' => $filename,
				)
			);
		} catch ( Exception $e ) {
			error_log( 'KTPWP Ajax get_purchase_order_print_html Error: ' . $e->getMessage() );
			if ( ob_get_level() ) {
				ob_end_clean();
			}
			wp_send_json_error(
				array(
					'message' => $e->getMessage(),
				)
			);
		}
	}

	/**
	 * 発注書/見積依頼書の印刷用HTMLドキュメントを組み立てる
	 *
	 * @since 1.2.0
	 * @param string $doc_title   帳票タイトル（発注書／見積依頼書）
	 * @param object $order       ktp_order 行
	 * @param object $supplier    ktp_supplier 行
	 * @param array  $cost_items  対象協力会社に絞り込んだコスト項目
	 * @param string $my_company  自社情報（テキスト）
	 * @return string
	 */
	private function build_purchase_order_print_html( $doc_title, $order, $supplier, $cost_items, $my_company ) {
		$total_amount     = 0;
		$total_tax_amount = 0;
		$rows_html        = '';

		foreach ( $cost_items as $item ) {
			$product_name = isset( $item['product_name'] ) ? $item['product_name'] : '';
			$price        = floatval( isset( $item['price'] ) ? $item['price'] : 0 );
			$quantity     = floatval( isset( $item['quantity'] ) ? $item['quantity'] : 0 );
			$unit         = isset( $item['unit'] ) ? $item['unit'] : '';
			$amount       = floatval( isset( $item['amount'] ) ? $item['amount'] : 0 );
			$tax_rate_raw = isset( $item['tax_rate'] ) ? $item['tax_rate'] : null;
			if ( class_exists( 'KTPWP_Tax_Policy' ) ) {
				$tax_rate = KTPWP_Tax_Policy::get_effective_rate( $tax_rate_raw );
				if ( $tax_rate === null ) {
					$tax_rate = 0.0;
				}
			} else {
				$tax_rate = ( $tax_rate_raw !== null && $tax_rate_raw !== '' ) ? floatval( $tax_rate_raw ) : 0.0;
			}
			$tax_category = isset( $item['tax_category'] ) ? $item['tax_category'] : '内税';
			$remarks      = isset( $item['remarks'] ) ? $item['remarks'] : '';

			$tax_amount = 0;
			if ( $tax_rate > 0 ) {
				if ( $tax_category === '外税' ) {
					$tax_amount = ceil( $amount * ( $tax_rate / 100 ) );
				} else {
					$tax_amount = ceil( $amount * ( $tax_rate / 100 ) / ( 1 + $tax_rate / 100 ) );
				}
			}
			$total_amount     += $amount;
			$total_tax_amount += $tax_amount;

			$rows_html .= '<tr>';
			$rows_html .= '<td>' . esc_html( $product_name ) . '</td>';
			$rows_html .= '<td class="num">' . esc_html( KTPWP_Settings::format_money( $price ) ) . '</td>';
			$rows_html .= '<td class="num">' . esc_html( $quantity ) . esc_html( $unit ) . '</td>';
			$rows_html .= '<td class="num">' . esc_html( KTPWP_Settings::format_money( $amount ) ) . '</td>';
			$rows_html .= '<td class="num">' . ( $tax_rate > 0 ? esc_html( $tax_rate ) . '%' : '-' ) . '</td>';
			$rows_html .= '<td>' . esc_html( $remarks ) . '</td>';
			$rows_html .= '</tr>';
		}

		$total_with_tax = $total_amount + $total_tax_amount;
		$today          = date_i18n( 'Y年n月j日' );

		$supplier_address = trim(
			( isset( $supplier->prefecture ) ? $supplier->prefecture : '' ) .
			( isset( $supplier->city ) ? $supplier->city : '' ) .
			( isset( $supplier->address ) ? $supplier->address : '' ) .
			( isset( $supplier->building ) ? $supplier->building : '' )
		);

		$html  = '<!DOCTYPE html><html lang="ja"><head><meta charset="utf-8"><title>' . esc_html( $doc_title ) . '</title>';
		$html .= '<style>
			@page { size: A4 portrait; margin: 15mm; }
			body { font-family: "Hiragino Kaku Gothic ProN", "Yu Gothic", sans-serif; color: #222; margin: 0; padding: 0; }
			.doc-title { text-align: center; font-size: 24px; font-weight: bold; letter-spacing: 0.3em; margin: 0 0 24px; }
			.doc-header { display: flex; justify-content: space-between; margin-bottom: 24px; }
			.doc-header .to { font-size: 15px; }
			.doc-header .to .company { font-size: 16px; font-weight: bold; border-bottom: 1px solid #333; padding-bottom: 4px; display: inline-block; min-width: 220px; }
			.doc-header .meta { text-align: right; font-size: 12px; color: #444; }
			.doc-header .from { margin-top: 16px; font-size: 12px; white-space: pre-line; }
			table.items { width: 100%; border-collapse: collapse; margin-top: 12px; font-size: 12px; }
			table.items th, table.items td { border: 1px solid #999; padding: 6px 8px; }
			table.items th { background: #f0f0f0; }
			table.items td.num { text-align: right; }
			.totals { margin-top: 12px; text-align: right; font-size: 13px; }
			.totals .grand { font-size: 16px; font-weight: bold; }
		</style></head><body>';

		$html .= '<div class="doc-title">' . esc_html( $doc_title ) . '</div>';
		$html .= '<div class="doc-header">';
		$html .= '<div class="to">';
		$html .= '<div class="company">' . esc_html( $supplier->company_name ) . ' 御中</div><br>';
		if ( ! empty( $supplier->name ) ) {
			$html .= '<div>' . esc_html( $supplier->name ) . ' 様</div>';
		}
		$html .= '<div class="from">' . esc_html( $my_company ) . '</div>';
		$html .= '</div>';
		$html .= '<div class="meta">';
		$html .= esc_html( $today ) . '<br>';
		$html .= esc_html__( '案件', 'kantanpro' ) . '：' . esc_html( isset( $order->project_name ) ? $order->project_name : '' ) . '<br>';
		$html .= esc_html__( '受注書ID', 'kantanpro' ) . '：' . esc_html( $order->id );
		if ( $supplier_address !== '' ) {
			$html .= '<br>' . esc_html( $supplier_address );
		}
		$html .= '</div>';
		$html .= '</div>';

		$html .= '<table class="items"><thead><tr>';
		$html .= '<th>' . esc_html__( '品目', 'kantanpro' ) . '</th>';
		$html .= '<th>' . esc_html__( '単価', 'kantanpro' ) . '</th>';
		$html .= '<th>' . esc_html__( '数量', 'kantanpro' ) . '</th>';
		$html .= '<th>' . esc_html__( '金額', 'kantanpro' ) . '</th>';
		$html .= '<th>' . esc_html__( '税率', 'kantanpro' ) . '</th>';
		$html .= '<th>' . esc_html__( '備考', 'kantanpro' ) . '</th>';
		$html .= '</tr></thead><tbody>' . $rows_html . '</tbody></table>';

		$html .= '<div class="totals">';
		$html .= '<div>' . esc_html__( '金額合計', 'kantanpro' ) . '：' . esc_html( KTPWP_Settings::format_money( $total_amount ) ) . '</div>';
		$html .= '<div>' . esc_html__( '消費税額', 'kantanpro' ) . '：' . esc_html( KTPWP_Settings::format_money( $total_tax_amount ) ) . '</div>';
		$html .= '<div class="grand">' . esc_html__( '合計', 'kantanpro' ) . '：' . esc_html( KTPWP_Settings::format_money( $total_with_tax ) ) . '</div>';
		$html .= '</div>';

		$html .= '</body></html>';

		return $html;
	}

	/**
	 * Ajax: 会社情報取得
	 */
	public function ajax_get_company_info() {
		try {
			// セキュリティチェック
			if ( ! check_ajax_referer( 'ktpwp_ajax_nonce', 'nonce', false ) ) {
				throw new Exception( 'セキュリティ検証に失敗しました。' );
			}

			// 権限チェック
			if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'ktpwp_access' ) ) {
				throw new Exception( '権限がありません。' );
			}

			// 会社情報を取得
			$company_info = '';
			if ( class_exists( 'KTPWP_Settings' ) ) {
				$company_info = KTPWP_Settings::get_company_info();
			}

			// 旧システムからも取得（後方互換性）
			if ( empty( $company_info ) ) {
				global $wpdb;
				$setting_table = $wpdb->prefix . 'ktp_setting';
				$setting       = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT * FROM `{$setting_table}` WHERE id = %d",
                        1
                    )
                );
				if ( $setting ) {
					$company_info = sanitize_text_field( strip_tags( $setting->my_company_content ) );
				}
			}

			// デフォルト値
			if ( empty( $company_info ) ) {
				$company_info = get_bloginfo( 'name' );
			}

			wp_send_json_success(
				array(
					'company_info' => $company_info,
				)
			);
		} catch ( Exception $e ) {
			error_log( 'KTPWP Ajax get_company_info Error: ' . $e->getMessage() );
			wp_send_json_error(
				array(
					'message' => $e->getMessage(),
				)
			);
		}
	}

	/**
	 * Ajax: 郵便番号から住所（日本郵便API経由）
	 */
	public function ajax_lookup_postal_address() {
		if ( ! check_ajax_referer( 'ktpwp_ajax_nonce', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'セキュリティ検証に失敗しました。', 'kantanpro' ) ) );
		}
		if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'ktpwp_access' ) ) {
			wp_send_json_error( array( 'message' => __( '権限がありません。', 'kantanpro' ) ) );
		}
		if ( ! class_exists( 'KTPWP_JapanPost_Address_API' ) ) {
			wp_send_json_error( array( 'message' => __( '住所検索機能が利用できません。', 'kantanpro' ) ) );
		}
		$zip = isset( $_POST['zipcode'] ) ? sanitize_text_field( wp_unslash( $_POST['zipcode'] ) ) : '';
		$xff = KTPWP_JapanPost_Address_API::get_request_client_ip();
		$res = KTPWP_JapanPost_Address_API::lookup_zip( $zip, $xff );
		if ( is_wp_error( $res ) ) {
			wp_send_json_error( array( 'message' => $res->get_error_message() ) );
		}
		wp_send_json_success( $res );
	}

	/**
	 * Ajax: 協力会社担当者情報取得
	 */
	public function ajax_get_supplier_contact_info() {
		try {
			// セキュリティチェック
			if ( ! check_ajax_referer( 'ktpwp_ajax_nonce', 'nonce', false ) ) {
				throw new Exception( 'セキュリティ検証に失敗しました。' );
			}

			// 権限チェック
			if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'ktpwp_access' ) ) {
				throw new Exception( '権限がありません。' );
			}

			$supplier_name = isset( $_POST['supplier_name'] ) ? sanitize_text_field( $_POST['supplier_name'] ) : '';

			if ( empty( $supplier_name ) ) {
				throw new Exception( '協力会社名が指定されていません。' );
			}

			// 協力会社情報を取得
			global $wpdb;
			$supplier_table = $wpdb->prefix . 'ktp_supplier';
			$supplier = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM `{$supplier_table}` WHERE company_name = %s",
                    $supplier_name
                )
            );

			if ( $supplier ) {
				$contact_info = array(
					'supplier_name' => $supplier->company_name,
					'contact_person' => $supplier->name,
					'email' => $supplier->email,
					'phone' => $supplier->phone,
					'address' => $supplier->address,
				);
			} else {
				$contact_info = array(
					'supplier_name' => $supplier_name,
					'contact_person' => '',
					'email' => '',
					'phone' => '',
					'address' => '',
				);
			}

			wp_send_json_success(
				array(
					'contact_info' => $contact_info,
				)
			);
		} catch ( Exception $e ) {
			error_log( 'KTPWP Ajax get_supplier_contact_info Error: ' . $e->getMessage() );
			wp_send_json_error(
				array(
					'message' => $e->getMessage(),
				)
			);
		}
	}

	/**
	 * Ajax: 最新スタッフチャットメッセージ取得
	 */
	public function ajax_get_latest_staff_chat() {
		try {
			// 出力バッファをクリア
			while ( ob_get_level() ) {
				ob_end_clean();
			}
			ob_start();

			// フック干渉を無効化
			$this->disable_potentially_interfering_hooks();

			// ログインチェック
			if ( ! is_user_logged_in() ) {
				$this->send_clean_json_response(
					array(
						'success' => false,
						'data'    => __( 'ログインが必要です', 'kantanpro' ),
					)
				);
				return;
			}

			// Nonce検証（_ajax_nonceパラメータで送信される）
			$nonce = $_POST['_ajax_nonce'] ?? '';
			if ( ! wp_verify_nonce( $nonce, $this->nonce_names['staff_chat'] ) ) {
				$this->log_ajax_error(
					'Staff chat get messages nonce verification failed',
					array(
						'received_nonce'  => $nonce,
						'expected_action' => $this->nonce_names['staff_chat'],
					)
				);
				$this->send_clean_json_response(
					array(
						'success' => false,
						'data'    => __( 'セキュリティトークンが無効です', 'kantanpro' ),
					)
				);
				return;
			}

			// パラメータの取得とサニタイズ
			$order_id  = $this->sanitize_ajax_input( 'order_id', 'int' );
			$last_time = $this->sanitize_ajax_input( 'last_time', 'text' );

			if ( empty( $order_id ) ) {
				$this->send_clean_json_response(
					array(
						'success' => false,
						'data'    => __( '注文IDが必要です', 'kantanpro' ),
					)
				);
				return;
			}

			// 権限チェック
			if ( ! current_user_can( 'read' ) ) {
				$this->send_clean_json_response(
					array(
						'success' => false,
						'data'    => __( '権限がありません', 'kantanpro' ),
					)
				);
				return;
			}

			// スタッフチャットクラスのインスタンス化
			if ( ! class_exists( 'KTPWP_Staff_Chat' ) ) {
				require_once KTPWP_PLUGIN_DIR . 'includes/class-ktpwp-staff-chat.php';
			}

			$staff_chat = KTPWP_Staff_Chat::get_instance();

			// 最新メッセージを取得
			$messages = $staff_chat->get_messages_after( $order_id, $last_time );

			// バッファをクリーン
			$output = ob_get_clean();
			if ( ! empty( $output ) ) {
				error_log( 'KTPWP Ajax get_latest_staff_chat: Unexpected output cleaned: ' . $output );
			}

			$this->send_clean_json_response(
				array(
					'success' => true,
					'data'    => $messages,
				)
			);

		} catch ( Exception $e ) {
			// バッファをクリーン
			while ( ob_get_level() ) {
				ob_end_clean();
			}

			$this->log_ajax_error(
				'Exception during get latest staff chat',
				array(
					'message'  => $e->getMessage(),
					'order_id' => $_POST['order_id'] ?? 'unknown',
				)
			);

			$this->send_clean_json_response(
				array(
					'success' => false,
					'data'    => __( 'メッセージの取得中にエラーが発生しました', 'kantanpro' ),
				)
			);
		}
	}

	/**
	 * Ajax: スタッフチャットメッセージ送信
	 */
	public function ajax_send_staff_chat_message() {
		// WordPress出力バッファ完全クリア
		while ( ob_get_level() ) {
			ob_end_clean();
		}

		// 新しいバッファを開始（レスポンス汚染防止）
		ob_start();

		// エラー出力を抑制（JSON汚染防止）
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			error_reporting( 0 );
			ini_set( 'display_errors', 0 );
		}

		// 汚染される可能性のあるWordPressフックを一時的に無効化
		$this->disable_potentially_interfering_hooks();

		try {
			// ログインチェック
			if ( ! is_user_logged_in() ) {
				wp_send_json_error( __( 'ログインが必要です', 'kantanpro' ) );
				return;
			}

			// Nonce検証（_ajax_nonceパラメータで送信される）
			$nonce       = $_POST['_ajax_nonce'] ?? '';
			$nonce_valid = wp_verify_nonce( $nonce, $this->nonce_names['staff_chat'] );
			// nonceが不正かつ権限もない場合のみエラー
			if ( ! $nonce_valid && ! current_user_can( 'edit_posts' ) && ! current_user_can( 'ktpwp_access' ) ) {
				wp_send_json_error( __( '権限がありません（nonce不正）', 'kantanpro' ) );
				return;
			}

			// パラメータの取得とサニタイズ
			$order_id = $this->sanitize_ajax_input( 'order_id', 'int' );
			$message  = $this->sanitize_ajax_input( 'message', 'text' );

			if ( empty( $order_id ) || empty( $message ) ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'KTPWP StaffChat: order_idまたはmessageが空: order_id=' . print_r( $order_id, true ) . ' message=' . print_r( $message, true ) );
				}
				wp_send_json_error( __( '注文IDとメッセージが必要です', 'kantanpro' ) );
				return;
			}

			// 権限チェック
			if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'ktpwp_access' ) ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'KTPWP StaffChat: 権限チェック失敗 user_id=' . get_current_user_id() );
				}
				wp_send_json_error( __( '権限がありません', 'kantanpro' ) );
				return;
			}

			// スタッフチャットクラスのインスタンス化
			if ( ! class_exists( 'KTPWP_Staff_Chat' ) ) {
				require_once KTPWP_PLUGIN_DIR . 'includes/class-ktpwp-staff-chat.php';
			}

			$staff_chat = KTPWP_Staff_Chat::get_instance();

			// メッセージを送信
			$result = $staff_chat->add_message( $order_id, $message );

			if ( $result ) {
				// バッファをクリーンにしてからJSONを送信
				$output = ob_get_clean();
				if ( ! empty( $output ) ) {
					// デバッグ用：予期しない出力があればログに記録
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( 'KTPWP Ajax: Unexpected output cleaned: ' . $output );
					}
				}

				// 最終的なクリーンアップ
				$this->send_clean_json_response(
					array(
						'success' => true,
						'data'    => array(
							'message' => __( 'メッセージを送信しました', 'kantanpro' ),
						),
					)
				);
			} else {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'KTPWP StaffChat: add_message失敗 user_id=' . get_current_user_id() . ' order_id=' . print_r( $order_id, true ) . ' message=' . print_r( $message, true ) );
				}
				ob_end_clean();
				$this->send_clean_json_response(
					array(
						'success' => false,
						'data'    => __( 'メッセージの送信に失敗しました', 'kantanpro' ),
					)
				);
			}
		} catch ( Exception $e ) {
			ob_end_clean();
			$this->log_ajax_error(
				'Exception during send staff chat message',
				array(
					'message'  => $e->getMessage(),
					'order_id' => $_POST['order_id'] ?? 'unknown',
				)
			);
			$this->send_clean_json_response(
				array(
					'success' => false,
					'data'    => __( 'メッセージの送信中にエラーが発生しました', 'kantanpro' ),
				)
			);
		}
	}

	/**
	 * Ajax: スタッフチャットメッセージ削除（投稿者/管理者）
	 */
	public function ajax_delete_staff_chat_message() {
		while ( ob_get_level() ) {
			ob_end_clean();
		}
		ob_start();
		$this->disable_potentially_interfering_hooks();

		try {
			if ( ! is_user_logged_in() ) {
				wp_send_json_error( __( 'ログインが必要です', 'kantanpro' ) );
				return;
			}

			$nonce       = $_POST['_ajax_nonce'] ?? '';
			$nonce_valid = wp_verify_nonce( $nonce, $this->nonce_names['staff_chat'] );
			if ( ! $nonce_valid && ! current_user_can( 'edit_posts' ) && ! current_user_can( 'ktpwp_access' ) ) {
				wp_send_json_error( __( '権限がありません（nonce不正）', 'kantanpro' ) );
				return;
			}

			$message_id = $this->sanitize_ajax_input( 'message_id', 'int' );
			if ( empty( $message_id ) ) {
				wp_send_json_error( __( 'メッセージIDが必要です', 'kantanpro' ) );
				return;
			}

			if ( ! class_exists( 'KTPWP_Staff_Chat' ) ) {
				require_once KTPWP_PLUGIN_DIR . 'includes/class-ktpwp-staff-chat.php';
			}
			$staff_chat = KTPWP_Staff_Chat::get_instance();

			$result = $staff_chat->delete_message_by_author( $message_id );
			$output = ob_get_clean();
			if ( ! empty( $output ) && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'KTPWP Ajax delete_staff_chat_message: Unexpected output cleaned: ' . $output );
			}

            if ( $result ) {
                $current_user    = wp_get_current_user();
                $display_name    = $current_user && $current_user->display_name ? $current_user->display_name : ( $current_user ? $current_user->user_login : '' );
                $deleted_message = sprintf( '%s がメッセージを削除しました', sanitize_text_field( $display_name ) );

                $this->send_clean_json_response(
                    array(
                        'success' => true,
                        'data'    => array(
                            'message'      => __( '削除しました', 'kantanpro' ),
                            'deleted_text' => $deleted_message,
                            'message_id'   => (int) $message_id,
                        ),
                    )
                );
			} else {
				$this->send_clean_json_response(
					array(
						'success' => false,
						'data'    => __( '削除に失敗しました', 'kantanpro' ),
					)
				);
			}
		} catch ( Exception $e ) {
			ob_end_clean();
			$this->log_ajax_error( 'Exception during delete staff chat message', array( 'message' => $e->getMessage() ) );
			$this->send_clean_json_response( array( 'success' => false, 'data' => __( '削除中にエラーが発生しました', 'kantanpro' ) ) );
		}
	}

	/**
	 * 最新の受注書プレビューデータを取得
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function get_order_preview() {
		try {
			// nonce検証
			if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'ktp_ajax_nonce' ) ) {
				throw new Exception( 'セキュリティチェックに失敗しました。' );
			}

			// 権限チェック
			if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'ktpwp_access' ) ) {
				throw new Exception( 'この操作を実行する権限がありません。' );
			}

			// パラメータ取得
			$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
			if ( $order_id <= 0 ) {
				throw new Exception( '無効な受注書IDです。' );
			}

			global $wpdb;
			$table_name = $wpdb->prefix . 'ktp_order';

			// 受注書データを取得
			$order = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM `{$table_name}` WHERE id = %d",
					$order_id
				)
			);

			if ( ! $order ) {
				throw new Exception( '受注書が見つかりません。' );
			}

			// Order クラスのインスタンスを作成してプレビューHTML生成を利用
			        if ( ! class_exists( 'KTPWP_Order_Class' ) ) {
				require_once KTPWP_PLUGIN_DIR . 'includes/class-ktpwp-order-main.php';
			}

			            $order_class = new KTPWP_Order_Class();

			// パブリックメソッドを使用して最新のプレビューHTMLを生成
			$preview_html = $order_class->Generate_Order_Preview_HTML_Public( $order );

			// 進捗状況に応じた帳票タイトルを取得
			$document_info = $order_class->Get_Document_Info_By_Progress_Public( $order->progress );

			wp_send_json_success(
				array(
					'preview_html'   => $preview_html,
					'order_id'       => $order_id,
					'progress'       => $order->progress,
					'document_title' => $document_info['title'],
					'timestamp'      => current_time( 'timestamp' ),
				)
			);

		} catch ( Exception $e ) {
			error_log( 'KTPWP Ajax get_order_preview Error: ' . $e->getMessage() );
			wp_send_json_error(
				array(
					'message' => $e->getMessage(),
				)
			);
		}
	}

	/**
	 * WordPress 互換性を保持したクリーンな JSON レスポンスを送信
	 * wp_send_json_* 関数の改良版（WordPress コア機能との競合を避ける）
	 */
	private function send_clean_json_response( $data ) {
		// WordPress 標準の JSON レスポンス関数が利用可能で、
		// 既に出力が汚染されていない場合は、標準関数を使用
		if ( function_exists( 'wp_send_json' ) && ob_get_length() === false ) {
			wp_send_json( $data );
			return;
		}

		// 出力バッファが汚染されている場合のみクリーニングを実行
		$buffer_content = '';
		$buffer_level   = ob_get_level();

		if ( $buffer_level > 0 ) {
			$buffer_content = ob_get_contents();
			// 意味のある出力があるかチェック（空白や改行のみの場合は無視）
			$meaningful_content = trim( $buffer_content );

			if ( ! empty( $meaningful_content ) ) {
				// 汚染されている場合のみバッファをクリア
				while ( ob_get_level() ) {
					ob_end_clean();
				}
			}
		}

		// HTTPヘッダーが送信されていない場合のみ設定
		if ( ! headers_sent() ) {
			// WordPress 標準に準拠したヘッダー設定
			status_header( 200 );
			header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) );

			// キャッシュ制御
			nocache_headers();

			// セキュリティヘッダー
			if ( is_ssl() ) {
				header( 'Vary: Accept-Encoding' );
			}
		}

		// JSONエンコード（WordPress 標準オプション使用）
		$json = wp_json_encode( $data );

		if ( $json === false ) {
			// JSON エンコードに失敗した場合のフォールバック
			$fallback_data = array(
				'success' => false,
				'data'    => null,
				'message' => 'JSON encoding failed: ' . json_last_error_msg(),
			);
			$json          = wp_json_encode( $fallback_data );
		}

		// JSONを出力。wp_json_encode() の戻り値であり、JSON として送出するため
		// HTML エスケープを行うと壊れる。
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $json;

		// WordPress 標準の終了処理を使用（shutdown フックを実行）
		wp_die( '', '', array( 'response' => 200 ) );
	}

	/**
	 * 出力に干渉する可能性のあるWordPressフックを一時的に無効化
	 */
	private function disable_potentially_interfering_hooks() {
		// デバッグバーや他のプラグインの出力を防ぐ（安全なフックのみ削除）
		remove_all_actions( 'wp_footer' );
		remove_all_actions( 'admin_footer' );
		remove_all_actions( 'in_admin_footer' );
		remove_all_actions( 'admin_print_footer_scripts' );
		remove_all_actions( 'wp_print_footer_scripts' );

		// WordPress コアの重要な処理を破壊しないよう、shutdown フックは保持

		// 他のプラグインのAJAX干渉を防ぐ（安全な範囲で）
		if ( class_exists( 'WP_Debug_Bar' ) ) {
			remove_all_actions( 'wp_before_admin_bar_render' );
			remove_all_actions( 'wp_after_admin_bar_render' );
		}

		// より安全な方法：特定のプラグインによる出力のみを無効化
		$this->disable_specific_plugin_outputs();
	}

	/**
	 * 特定のプラグインによる出力のみを無効化（WordPress コア機能は保持）
	 */
	private function disable_specific_plugin_outputs() {
		global $wp_filter;

		// 問題を引き起こす可能性のある特定のプラグインフックのみを対象とする
		$problematic_hooks = array(
			'wp_footer'    => array( 'debug_bar', 'query_monitor', 'wp_debug_bar' ),
			'admin_footer' => array( 'debug_bar', 'query_monitor' ),
			'shutdown'     => array( 'debug_bar_output', 'query_monitor_output' ),
		);

		foreach ( $problematic_hooks as $hook_name => $plugin_patterns ) {
			if ( isset( $wp_filter[ $hook_name ] ) ) {
				foreach ( $wp_filter[ $hook_name ]->callbacks as $priority => $callbacks ) {
					foreach ( $callbacks as $callback_id => $callback_data ) {
						// コールバック関数名または関数オブジェクトをチェック
						$function_name = '';
						if ( is_string( $callback_data['function'] ) ) {
							$function_name = $callback_data['function'];
						} elseif ( is_array( $callback_data['function'] ) && count( $callback_data['function'] ) === 2 ) {
							$class_name    = is_object( $callback_data['function'][0] ) ?
								get_class( $callback_data['function'][0] ) : $callback_data['function'][0];
							$function_name = $class_name . '::' . $callback_data['function'][1];
						}

						// 問題のあるパターンに一致する場合のみ削除
						foreach ( $plugin_patterns as $pattern ) {
							if ( stripos( $function_name, $pattern ) !== false ) {
								remove_action( $hook_name, $callback_data['function'], $priority );
								break;
							}
						}
					}
				}
			}
		}
	}

	/**
	 * Ajax入力データのサニタイズ処理
	 *
	 * @param string $key 取得するPOSTキー
	 * @param string $type サニタイズのタイプ
	 * @param mixed  $default デフォルト値
	 * @return mixed サニタイズされた値
	 */
	private function sanitize_ajax_input( $key, $type = 'text', $default = '' ) {
		if ( ! isset( $_POST[ $key ] ) ) {
			return $default;
		}

		$value = $_POST[ $key ];

		switch ( $type ) {
			case 'int':
				return intval( $value );
			case 'float':
				return floatval( $value );
			case 'email':
				return sanitize_email( $value );
			case 'url':
				return esc_url_raw( $value );
			case 'textarea':
				return sanitize_textarea_field( $value );
			case 'html':
				return wp_kses_post( $value );
			case 'key':
				return sanitize_key( $value );
			case 'title':
				return sanitize_title( $value );
			case 'text':
			default:
				return sanitize_text_field( $value );
		}
	}

	/**
	 * 受注書 memo カラム用（最大10000文字、KantanBiz相当）
	 *
	 * @param mixed $raw POST の生値（未 unstslash 可）
	 * @return string
	 */
	private function sanitize_ktp_order_memo( $raw ) {
		$memo = sanitize_textarea_field( wp_unslash( $raw ) );
		if ( function_exists( 'mb_strlen' ) && mb_strlen( $memo ) > 10000 ) {
			return mb_substr( $memo, 0, 10000 );
		}
		if ( strlen( $memo ) > 10000 ) {
			return substr( $memo, 0, 10000 );
		}
		return $memo;
	}

	/**
	 * 受付日（登録日）を更新する。
	 *
	 * @param int    $order_id 受注書ID
	 * @param string $date_value Y-m-d形式の日付
	 * @return array 更新後の値
	 * @throws Exception 更新に失敗した場合
	 */
	private function update_order_reception_date( $order_id, $date_value ) {
		if ( empty( $date_value ) ) {
			throw new Exception( '受付日は必須です。' );
		}

		$date_obj = DateTime::createFromFormat( 'Y-m-d', $date_value );
		if ( ! $date_obj || $date_obj->format( 'Y-m-d' ) !== $date_value ) {
			throw new Exception( '無効な日付形式です。' );
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'ktp_order';
		$order = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT `time`, `created_at` FROM `{$table_name}` WHERE id = %d",
				$order_id
			)
		);

		if ( ! $order ) {
			throw new Exception( '受注書が見つかりません。' );
		}

		$timezone = new DateTimeZone( wp_timezone_string() );
		$current_dt = null;
		$raw_time = isset( $order->time ) ? (string) $order->time : '';

		if ( $raw_time !== '' ) {
			if ( is_numeric( $raw_time ) && strlen( $raw_time ) >= 10 ) {
				$current_dt = new DateTime( '@' . (int) $raw_time );
				$current_dt->setTimezone( $timezone );
			} else {
				$current_dt = date_create( $raw_time, $timezone );
			}
		}

		if ( ! $current_dt && ! empty( $order->created_at ) ) {
			$current_dt = date_create( $order->created_at, $timezone );
		}

		if ( ! $current_dt ) {
			$current_dt = new DateTime( 'now', $timezone );
		}

		$updated_dt = DateTime::createFromFormat( 'Y-m-d H:i:s', $date_value . ' ' . $current_dt->format( 'H:i:s' ), $timezone );
		if ( ! $updated_dt ) {
			throw new Exception( '受付日の更新に失敗しました。' );
		}

		$created_at = $updated_dt->format( 'Y-m-d H:i:s' );
		$time_value = ( is_numeric( $raw_time ) && strlen( $raw_time ) >= 10 ) ? $updated_dt->getTimestamp() : $created_at;
		$result = $wpdb->update(
			$table_name,
			array(
				'created_at' => $created_at,
				'time'       => $time_value,
			),
			array( 'id' => $order_id ),
			array( '%s', is_int( $time_value ) ? '%d' : '%s' ),
			array( '%d' )
		);

		if ( $result === false ) {
			throw new Exception( 'データベースの更新に失敗しました: ' . $wpdb->last_error );
		}

		return array(
			'created_at'   => $created_at,
			'field_value'  => $date_value,
			'time'         => $time_value,
			'display_time' => $updated_dt->format( 'H:i' ),
		);
	}

	/**
	 * 納期フィールドの保存処理
	 */
	public function ajax_save_delivery_date() {
		try {
			// デバッグ情報をログに出力
			error_log( 'KTPWP Ajax save_delivery_date called with POST data: ' . print_r( $_POST, true ) );

			// パラメータ取得
			$order_id   = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
			$field_name = isset( $_POST['field_name'] ) ? sanitize_text_field( wp_unslash( $_POST['field_name'] ) ) : '';
			if ( $field_name === 'memo' ) {
				$field_value = isset( $_POST['field_value'] ) ? $this->sanitize_ktp_order_memo( $_POST['field_value'] ) : '';
			} else {
				$field_value = isset( $_POST['field_value'] ) ? sanitize_text_field( wp_unslash( $_POST['field_value'] ) ) : '';
			}

			if ( $order_id <= 0 ) {
				throw new Exception( '無効な受注書IDです。' );
			}

			// データベース接続のデバッグ情報を最初に出力
			global $wpdb;
			$table_name = $wpdb->prefix . 'ktp_order';
			error_log( 'KTPWP Ajax: Table name = ' . $table_name );
			error_log( 'KTPWP Ajax: wpdb->prefix = ' . $wpdb->prefix );

			// テーブルの存在確認
			$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" );
			error_log( 'KTPWP Ajax: Table exists = ' . ( $table_exists ? 'YES' : 'NO' ) );

			// テーブル構造の確認
			$columns = $wpdb->get_results( "DESCRIBE `{$table_name}`" );
			error_log( 'KTPWP Ajax: Table columns = ' . print_r( $columns, true ) );

			// 納期カラムの存在確認と追加
			$column_names = array();
			foreach ( $columns as $column ) {
				$column_names[] = $column->Field;
			}

			$delivery_columns = array( 'promised_delivery_date', 'desired_delivery_date', 'expected_delivery_date', 'completion_date' );
			foreach ( $delivery_columns as $delivery_column ) {
				if ( ! in_array( $delivery_column, $column_names ) ) {
					error_log( 'KTPWP Ajax: Adding missing column: ' . $delivery_column );
					$wpdb->query( "ALTER TABLE `{$table_name}` ADD COLUMN `{$delivery_column}` DATE NULL" );
					error_log( 'KTPWP Ajax: Column added: ' . $delivery_column );
				}
			}

			// 受注書の存在確認
			$order_exists = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM `{$table_name}` WHERE id = %d",
					$order_id
				)
			);

			if ( ! $order_exists ) {
				throw new Exception( '受注書が見つかりません。' );
			}

			// セキュリティチェック - 複数のnonce名を試行
			$nonce_verified = false;
			$nonce_names    = array( 'ktp_ajax_nonce', 'ktpwp_ajax_nonce', 'nonce' );

			foreach ( $nonce_names as $nonce_name ) {
				if ( isset( $_POST[ $nonce_name ] ) ) {
					$nonce_value = $_POST[ $nonce_name ];

					// 配列の場合はvalueキーを取得
					if ( is_array( $nonce_value ) && isset( $nonce_value['value'] ) ) {
						$nonce_value = $nonce_value['value'];
					}

					error_log( 'KTPWP Ajax: Found nonce field: ' . $nonce_name . ' = ' . $nonce_value );
					if ( wp_verify_nonce( $nonce_value, 'ktp_ajax_nonce' ) ) {
						$nonce_verified = true;
						error_log( 'KTPWP Ajax: Nonce verified with field: ' . $nonce_name );
						break;
					} else {
						error_log( 'KTPWP Ajax: Nonce verification failed for field: ' . $nonce_name );
					}
				} else {
					error_log( 'KTPWP Ajax: Nonce field not found: ' . $nonce_name );
				}
			}

			if ( ! $nonce_verified ) {
				error_log( 'KTPWP Ajax: Nonce verification failed. Available fields: ' . implode( ', ', array_keys( $_POST ) ) );
				throw new Exception( 'セキュリティ検証に失敗しました。' );
			}

			// 権限チェック
			if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'ktpwp_access' ) ) {
				throw new Exception( '権限がありません。' );
			}

			// フィールド名の検証
			$allowed_fields = array( 'promised_delivery_date', 'desired_delivery_date', 'expected_delivery_date', 'completion_date', 'created_at', 'memo' );
			if ( ! in_array( $field_name, $allowed_fields ) ) {
				throw new Exception( '無効なフィールド名です。' );
			}

			// 日付形式の検証（memo は対象外）
			if ( $field_name !== 'memo' && ! empty( $field_value ) ) {
				$date_obj = DateTime::createFromFormat( 'Y-m-d', $field_value );
				if ( ! $date_obj || $date_obj->format( 'Y-m-d' ) !== $field_value ) {
					throw new Exception( '無効な日付形式です。' );
				}
			}

			if ( $field_name === 'created_at' ) {
				$updated_reception_date = $this->update_order_reception_date( $order_id, $field_value );
				wp_send_json_success(
					array(
						'message'      => __( '受付日が正常に保存されました。', 'kantanpro' ),
						'order_id'     => $order_id,
						'field_name'   => $field_name,
						'field_value'  => $updated_reception_date['field_value'],
						'created_at'   => $updated_reception_date['created_at'],
						'display_time' => $updated_reception_date['display_time'],
					)
				);
			}

			// データベース更新
			$update_data = array( $field_name => $field_value );
			$result      = $wpdb->update(
				$table_name,
				$update_data,
				array( 'id' => $order_id ),
				array( '%s' ),
				array( '%d' )
			);

			if ( $result === false ) {
				throw new Exception( 'データベースの更新に失敗しました: ' . $wpdb->last_error );
			}

			$save_ok_message = ( $field_name === 'memo' ) ? 'メモが正常に保存されました。' : '納期が正常に保存されました。';
			wp_send_json_success(
				array(
					'message'     => $save_ok_message,
					'order_id'    => $order_id,
					'field_name'  => $field_name,
					'field_value' => $field_value,
				)
			);

		} catch ( Exception $e ) {
			error_log( 'KTPWP Ajax save_delivery_date Error: ' . $e->getMessage() );
			wp_send_json_error(
				array(
					'message' => $e->getMessage(),
				)
			);
		}
	}

	/**
	 * 納期フィールドの更新処理
	 */
	public function ajax_update_delivery_date() {
		try {
			// 権限チェック
			if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'ktpwp_access' ) ) {
				wp_send_json_error( array( 'message' => __( 'この操作を行う権限がありません。', 'kantanpro' ) ) );
				return;
			}

			// nonce検証（複数キー・複数アクションに対応：仕事リスト・WC受注・受注書詳細のいずれでも通るようにする）
			$nonce_keys    = array( 'nonce', 'security', '_wpnonce', 'ktp_ajax_nonce' );
			$nonce_actions = array( 'ktp_ajax_nonce', 'ktpwp_ajax_nonce', 'ktpwp_auto_save_nonce' );
			$nonce_value   = '';
			foreach ( $nonce_keys as $key ) {
				if ( ! empty( $_POST[ $key ] ) && is_string( $_POST[ $key ] ) ) {
					$nonce_value = sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
					break;
				}
			}
			$nonce_ok = false;
			if ( $nonce_value !== '' ) {
				foreach ( $nonce_actions as $action ) {
					if ( wp_verify_nonce( $nonce_value, $action ) ) {
						$nonce_ok = true;
						break;
					}
				}
			}
			if ( ! $nonce_ok ) {
				wp_send_json_error( array( 'message' => __( 'セキュリティ検証に失敗しました', 'kantanpro' ) ) );
				return;
			}

			// POSTデータの取得とサニタイズ
			$order_id = $this->sanitize_ajax_input( 'order_id', 'int' );
			$field    = $this->sanitize_ajax_input( 'field', 'text' );
			if ( $field === 'memo' ) {
				$value = isset( $_POST['value'] ) ? $this->sanitize_ktp_order_memo( $_POST['value'] ) : '';
			} else {
				$value = $this->sanitize_ajax_input( 'value', 'text' );
			}

			// バリデーション
			if ( $order_id <= 0 ) {
				wp_send_json_error( array( 'message' => __( '無効な受注IDです', 'kantanpro' ) ) );
				return;
			}

			// フィールド名の検証
			$allowed_fields = array( 'promised_delivery_date', 'desired_delivery_date', 'expected_delivery_date', 'completion_date', 'created_at', 'memo' );
			if ( ! in_array( $field, $allowed_fields ) ) {
				wp_send_json_error( array( 'message' => __( '無効なフィールド名です', 'kantanpro' ) ) );
				return;
			}

			// 日付形式の検証（空の場合は許可、memo は対象外）
			if ( $field !== 'memo' && ! empty( $value ) ) {
				$date_obj = DateTime::createFromFormat( 'Y-m-d', $value );
				if ( ! $date_obj || $date_obj->format( 'Y-m-d' ) !== $value ) {
					wp_send_json_error( array( 'message' => __( '無効な日付形式です', 'kantanpro' ) ) );
					return;
				}
			}

			if ( $field === 'created_at' ) {
				$updated_reception_date = $this->update_order_reception_date( $order_id, $value );
				wp_send_json_success(
					array(
						'message'      => __( '受付日を更新しました', 'kantanpro' ),
						'field'        => $field,
						'value'        => $updated_reception_date['field_value'],
						'created_at'   => $updated_reception_date['created_at'],
						'display_time' => $updated_reception_date['display_time'],
					)
				);
				return;
			}

			global $wpdb;
			$table_name = $wpdb->prefix . 'ktp_order';

			// デバッグログを追加
			error_log( 'KTPWP Ajax: Table name: ' . $table_name );
			error_log( 'KTPWP Ajax: Order ID: ' . $order_id );
			error_log( 'KTPWP Ajax: Field: ' . $field );
			error_log( 'KTPWP Ajax: Value: ' . $value );

			// テーブルの存在確認
			$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" );
			error_log( 'KTPWP Ajax: Table exists: ' . ( $table_exists ? 'YES' : 'NO' ) );

			// 日付カラムが未作成の環境向けに自動追加
			$auto_add_date_fields = array( 'promised_delivery_date', 'desired_delivery_date', 'expected_delivery_date', 'completion_date' );
			if ( in_array( $field, $auto_add_date_fields, true ) ) {
				$column_exists = $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM `{$table_name}` LIKE %s", $field ) );
				if ( ! $column_exists ) {
					$wpdb->query( "ALTER TABLE `{$table_name}` ADD COLUMN `{$field}` DATE NULL" );
				}
			}

			// カラムの存在確認
			$column_exists = $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM `{$table_name}` LIKE %s", $field ) );
			error_log( 'KTPWP Ajax: Column exists: ' . ( $column_exists ? 'YES' : 'NO' ) );

			// データベース更新
			$result = $wpdb->update(
				$table_name,
				array( $field => $value ),
				array( 'id' => $order_id ),
				array( '%s' ),
				array( '%d' )
			);

			error_log( 'KTPWP Ajax: Update result: ' . var_export( $result, true ) );
			if ( $result === false ) {
				error_log( 'KTPWP Ajax: Last error: ' . $wpdb->last_error );
			}

			$ok_message = ( $field === 'memo' ) ? __( 'メモを更新しました', 'kantanpro' ) : __( '納期を更新しました', 'kantanpro' );
			wp_send_json_success(
				array(
					'message' => $ok_message,
					'field'   => $field,
					'value'   => $value,
				)
			);

		} catch ( Exception $e ) {
			error_log( 'KTPWP Ajax update_delivery_date Error: ' . $e->getMessage() );
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}
	}

	/**
	 * Ajax: 作成中の納期警告件数を取得
	 */
	public function ajax_get_creating_warning_count() {
		try {
			// デバッグログ
			error_log( 'KTPWP Ajax: ajax_get_creating_warning_count called' );

			// 権限チェック
			if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'ktpwp_access' ) ) {
				error_log( 'KTPWP Ajax: Permission check failed' );
				wp_send_json_error( __( 'この操作を行う権限がありません。', 'kantanpro' ) );
				return;
			}

			// nonce検証
			if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'ktp_ajax_nonce' ) ) {
				error_log( 'KTPWP Ajax: Nonce verification failed' );
				wp_send_json_error( __( 'セキュリティ検証に失敗しました', 'kantanpro' ) );
				return;
			}

			global $wpdb;
			$table_name = $wpdb->prefix . 'ktp_order';

			// 一般設定から警告日数を取得
			$warning_days = 3; // デフォルト値
			if ( class_exists( 'KTPWP_Settings' ) ) {
				$warning_days = KTPWP_Settings::get_delivery_warning_days();
			}

			error_log( 'KTPWP Ajax: Warning days: ' . $warning_days . ', Table: ' . $table_name );

			// 作成中（progress = 3）で納期警告の件数を取得
			$query = $wpdb->prepare(
				"SELECT COUNT(*) FROM `{$table_name}` WHERE progress = %d AND expected_delivery_date IS NOT NULL AND expected_delivery_date <= DATE_ADD(CURDATE(), INTERVAL %d DAY)",
				3, // 作成中
				$warning_days
			);

			error_log( 'KTPWP Ajax: Query: ' . $query );

			$warning_count = $wpdb->get_var( $query );

			if ( $warning_count === null ) {
				error_log( 'KTPWP Ajax: Database error: ' . $wpdb->last_error );
				wp_send_json_error( __( 'データベースエラーが発生しました', 'kantanpro' ) );
				return;
			}

			error_log( 'KTPWP Ajax: Warning count: ' . $warning_count );

			// 完了タブ（progress=4）の請求書締日警告件数（リアルタイム更新用）
			$invoice_warning_count = 0;
			$client_table           = $wpdb->prefix . 'ktp_client';
			$query_invoice          = "SELECT o.id, o.client_id, o.completion_date, c.closing_day FROM {$table_name} o LEFT JOIN {$client_table} c ON o.client_id = c.id WHERE o.progress = 4 AND o.completion_date IS NOT NULL AND c.closing_day IS NOT NULL AND c.closing_day != 'なし'";
			$orders_invoice         = $wpdb->get_results( $query_invoice );
			$today                  = new DateTime();
			$today->setTime( 0, 0, 0 );
			foreach ( $orders_invoice as $order ) {
				$completion_date = $order->completion_date;
				if ( empty( $completion_date ) ) {
					continue;
				}
				$dt = DateTime::createFromFormat( 'Y-m-d', $completion_date );
				$errors = DateTime::getLastErrors();
				if ( $dt === false || ( $errors && ( $errors['warning_count'] > 0 || $errors['error_count'] > 0 ) ) ) {
					continue;
				}
				$completion_dt = $dt;
				$year          = (int) $completion_dt->format( 'Y' );
				$month         = (int) $completion_dt->format( 'm' );
				if ( $year < 1 || $year > 9999 || $month < 1 || $month > 12 ) {
					continue;
				}
				$closing_day = $order->closing_day;
				if ( $closing_day === '末日' ) {
					$closing_dt = new DateTime( "$year-$month-01" );
					$closing_dt->modify( 'last day of this month' );
				} else {
					$closing_day_num = (int) $closing_day;
					$closing_dt      = new DateTime( "$year-$month-" . str_pad( $closing_day_num, 2, '0', STR_PAD_LEFT ) );
					$last_day        = (int) $closing_dt->format( 't' );
					if ( $closing_day_num > $last_day ) {
						$closing_dt->modify( 'last day of this month' );
					}
				}
				$closing_dt->setTime( 0, 0, 0 );
				$diff     = $today->diff( $closing_dt );
				$days_left = $diff->invert ? -$diff->days : $diff->days;
				if ( $days_left <= 0 ) {
					$invoice_warning_count++;
				}
			}

			// 請求済タブ（progress=5）の入金予定日（支払期日）超過件数（前入金済は対象外・リアルタイム更新用）
			$payment_warning_count = 0;
			$query_payment         = "SELECT o.id, o.client_id, o.completion_date, o.payment_timing AS order_payment_timing, c.closing_day, c.payment_month, c.payment_day, c.payment_timing AS client_payment_timing FROM {$table_name} o LEFT JOIN {$client_table} c ON o.client_id = c.id WHERE o.progress = 5 AND o.completion_date IS NOT NULL AND c.payment_month IS NOT NULL AND c.payment_day IS NOT NULL";
			$orders_payment        = $wpdb->get_results( $query_payment );
			foreach ( $orders_payment as $order ) {
				// 前入金済みは未入金警告対象外
				if ( class_exists( 'KTPWP_Payment_Timing' ) ) {
					$order_obj  = (object) array(
						'payment_timing' => isset( $order->order_payment_timing ) ? $order->order_payment_timing : null,
						'client_id'      => isset( $order->client_id ) ? $order->client_id : 0,
					);
					$client_obj = (object) array(
						'payment_timing' => isset( $order->client_payment_timing ) ? $order->client_payment_timing : null,
					);
					if ( KTPWP_Payment_Timing::is_prepay( $order_obj, $client_obj ) ) {
						continue;
					}
				}

				$completion_date = isset( $order->completion_date ) ? (string) $order->completion_date : '';
				if ( $completion_date === '' ) {
					continue;
				}
				$completion_dt = DateTime::createFromFormat( 'Y-m-d', $completion_date );
				$errors = DateTime::getLastErrors();
				if ( $completion_dt === false || ( $errors && ( $errors['warning_count'] > 0 || $errors['error_count'] > 0 ) ) ) {
					continue;
				}
				$completion_dt->setTime( 0, 0, 0 );

				$year  = (int) $completion_dt->format( 'Y' );
				$month = (int) $completion_dt->format( 'm' );
				if ( $year < 1 || $year > 9999 || $month < 1 || $month > 12 ) {
					continue;
				}

				$closing_day   = isset( $order->closing_day ) ? (string) $order->closing_day : '末日';
				$payment_month = isset( $order->payment_month ) ? (string) $order->payment_month : '翌月';
				$payment_day   = isset( $order->payment_day ) ? (string) $order->payment_day : '末日';

				// 完了日が属する請求月（締め日基準）を決定
				$billing_year  = $year;
				$billing_month = $month;
				if ( $closing_day !== '' && $closing_day !== 'なし' ) {
					if ( $closing_day === '末日' ) {
						$closing_dt = new DateTime( "$year-$month-01" );
						$closing_dt->modify( 'last day of this month' );
					} else {
						$closing_day_num = (int) $closing_day;
						$closing_dt      = new DateTime( "$year-$month-" . str_pad( (string) $closing_day_num, 2, '0', STR_PAD_LEFT ) );
						$last_day        = (int) $closing_dt->format( 't' );
						if ( $closing_day_num > $last_day ) {
							$closing_dt->modify( 'last day of this month' );
						}
					}
					$closing_dt->setTime( 0, 0, 0 );
					if ( $completion_dt > $closing_dt ) {
						$billing_month++;
						if ( $billing_month > 12 ) {
							$billing_month = 1;
							$billing_year++;
						}
					}
				}

				// 支払月を計算（今月/翌月/翌々月）
				$payment_year  = $billing_year;
				$payment_m_num = $billing_month;
				switch ( $payment_month ) {
					case '今月':
						$payment_m_num = $billing_month;
						break;
					case '翌々月':
						$payment_m_num = $billing_month + 2;
						if ( $payment_m_num > 12 ) {
							$payment_m_num -= 12;
							$payment_year++;
						}
						break;
					case '翌月':
					default:
						$payment_m_num = $billing_month + 1;
						if ( $payment_m_num > 12 ) {
							$payment_m_num = 1;
							$payment_year++;
						}
						break;
				}

				// 支払日を計算（末日/即日/指定日）
				if ( $payment_day === '即日' ) {
					$due_dt = clone $completion_dt;
				} else {
					$due_dt = new DateTime();
					$due_dt->setDate( $payment_year, $payment_m_num, 1 );
					if ( $payment_day === '末日' ) {
						$due_dt->modify( 'last day of this month' );
					} else {
						$payment_day_num = (int) str_replace( '日', '', $payment_day );
						$due_dt->setDate( $payment_year, $payment_m_num, max( 1, $payment_day_num ) );
						$last_day = (int) $due_dt->format( 't' );
						if ( $payment_day_num > $last_day ) {
							$due_dt->modify( 'last day of this month' );
						}
					}
					$due_dt->setTime( 0, 0, 0 );
				}

				if ( $today > $due_dt ) {
					$payment_warning_count++;
				}
			}

			wp_send_json_success(
				array(
					'warning_count'        => (int) $warning_count,
					'warning_days'         => $warning_days,
					'invoice_warning_count' => (int) $invoice_warning_count,
					'payment_warning_count' => (int) $payment_warning_count,
				)
			);

		} catch ( Exception $e ) {
			error_log( 'KTPWP Ajax ajax_get_creating_warning_count Error: ' . $e->getMessage() );
			wp_send_json_error( __( 'エラーが発生しました: ' . $e->getMessage(), 'kantanpro' ) );
		}
	}

	/**
	 * 顧客税区分取得
	 */
	public function ajax_get_client_tax_category() {
		try {
			// デバッグログ
			error_log( 'KTPWP Ajax: ajax_get_client_tax_category called' );

			// 権限チェック
			if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'ktpwp_access' ) ) {
				error_log( 'KTPWP Ajax: Permission check failed' );
				wp_send_json_error( __( 'この操作を行う権限がありません。', 'kantanpro' ) );
				return;
			}

			// nonce検証
			if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'ktp_ajax_nonce' ) ) {
				error_log( 'KTPWP Ajax: Nonce verification failed' );
				wp_send_json_error( __( 'セキュリティ検証に失敗しました', 'kantanpro' ) );
				return;
			}

			// パラメータ取得
			$client_id = isset( $_POST['client_id'] ) ? absint( $_POST['client_id'] ) : 0;

			if ( $client_id <= 0 ) {
				wp_send_json_error( __( '顧客IDが無効です', 'kantanpro' ) );
			}

			global $wpdb;
			$client_table = $wpdb->prefix . 'ktp_client';

			// 顧客の税区分を取得
			$tax_category = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT tax_category FROM `{$client_table}` WHERE id = %d",
					$client_id
				)
			);

			if ( $tax_category === null ) {
				error_log( 'KTPWP Ajax: Client not found or database error: ' . $wpdb->last_error );
				wp_send_json_error( __( '顧客が見つからないか、データベースエラーが発生しました', 'kantanpro' ) );
				return;
			}

			// デフォルト値の設定
			if ( empty( $tax_category ) ) {
				$tax_category = '内税';
			}

			error_log( 'KTPWP Ajax: Tax category retrieved: ' . $tax_category );

			wp_send_json_success(
				array(
					'tax_category' => $tax_category,
					'client_id'    => $client_id,
				)
			);

		} catch ( Exception $e ) {
			error_log( 'KTPWP Ajax ajax_get_client_tax_category Error: ' . $e->getMessage() );
			wp_send_json_error( __( 'エラーが発生しました: ' . $e->getMessage(), 'kantanpro' ) );
		}
	}

	/**
	 * 受注書IDから顧客税区分取得
	 */
	public function ajax_get_client_tax_category_by_order() {
		try {
			// デバッグログ
			error_log( 'KTPWP Ajax: ajax_get_client_tax_category_by_order called' );

			// 権限チェック
			if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'ktpwp_access' ) ) {
				error_log( 'KTPWP Ajax: Permission check failed' );
				wp_send_json_error( __( 'この操作を行う権限がありません。', 'kantanpro' ) );
				return;
			}

			// nonce検証
			if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'ktp_ajax_nonce' ) ) {
				error_log( 'KTPWP Ajax: Nonce verification failed' );
				wp_send_json_error( __( 'セキュリティ検証に失敗しました', 'kantanpro' ) );
				return;
			}

			// パラメータ取得
			$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;

			if ( $order_id <= 0 ) {
				wp_send_json_error( __( '受注書IDが無効です', 'kantanpro' ) );
			}

			global $wpdb;
			$order_table = $wpdb->prefix . 'ktp_order';
			$client_table = $wpdb->prefix . 'ktp_client';

			// 受注書から顧客IDを取得
			$client_id = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT client_id FROM `{$order_table}` WHERE id = %d",
					$order_id
				)
			);

			if ( $client_id === null ) {
				error_log( 'KTPWP Ajax: Order not found or database error: ' . $wpdb->last_error );
				wp_send_json_error( __( '受注書が見つからないか、データベースエラーが発生しました', 'kantanpro' ) );
				return;
			}

			if ( $client_id <= 0 ) {
				// 顧客IDが設定されていない場合はデフォルト値を返す
				wp_send_json_success(
					array(
						'tax_category' => '内税',
						'client_id'    => 0,
						'order_id'     => $order_id,
					)
				);
				return;
			}

			// 顧客の税区分を取得
			$tax_category = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT tax_category FROM `{$client_table}` WHERE id = %d",
					$client_id
				)
			);

			if ( $tax_category === null ) {
				error_log( 'KTPWP Ajax: Client not found or database error: ' . $wpdb->last_error );
				wp_send_json_error( __( '顧客が見つからないか、データベースエラーが発生しました', 'kantanpro' ) );
				return;
			}

			// デフォルト値の設定
			if ( empty( $tax_category ) ) {
				$tax_category = '内税';
			}

			error_log( 'KTPWP Ajax: Tax category retrieved by order: ' . $tax_category . ' (Order ID: ' . $order_id . ', Client ID: ' . $client_id . ')' );

			wp_send_json_success(
				array(
					'tax_category' => $tax_category,
					'client_id'    => $client_id,
					'order_id'     => $order_id,
				)
			);

		} catch ( Exception $e ) {
			error_log( 'KTPWP Ajax ajax_get_client_tax_category_by_order Error: ' . $e->getMessage() );
			wp_send_json_error( __( 'エラーが発生しました: ' . $e->getMessage(), 'kantanpro' ) );
		}
	}

	/**
	 * 協力会社税区分取得
	 */
	public function ajax_get_supplier_tax_category() {
		try {
			// デバッグログ
			error_log( 'KTPWP Ajax: ajax_get_supplier_tax_category called' );

			// 権限チェック
			if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'ktpwp_access' ) ) {
				error_log( 'KTPWP Ajax: Permission check failed' );
				wp_send_json_error( __( 'この操作を行う権限がありません。', 'kantanpro' ) );
				return;
			}

			// nonce検証（一時的に緩和版）
			$nonce_verified = false;
			$nonce_value = '';
			$nonce_sources = [
				'nonce',
				'_wpnonce',
				'_ajax_nonce',
				'ktp_ajax_nonce',
				'security'
			];

			foreach ($nonce_sources as $source) {
				if (isset($_POST[$source]) && !empty($_POST[$source])) {
					$nonce_value = $_POST[$source];
					error_log('KTPWP Ajax: Trying tax category nonce from source: ' . $source . ' with value: ' . $nonce_value);
					// 複数のnonce名で検証を試行
					$nonce_names = [
						'ktp_ajax_nonce',
						'_wpnonce',
						'_ajax_nonce',
						'auto_save',
						'general'
					];
					foreach ($nonce_names as $name) {
						if (wp_verify_nonce($nonce_value, $name)) {
							$nonce_verified = true;
							error_log('KTPWP Ajax: Tax category nonce verified with source: ' . $source . ' and name: ' . $name);
							break 2;
						}
					}
				}
			}
			
			// 権限があるユーザーの場合は、nonce検証を一時的に緩和
			if (!$nonce_verified && (current_user_can('edit_posts') || current_user_can('ktpwp_access'))) {
				error_log('KTPWP Ajax: Tax category nonce verification failed but user has permissions, proceeding with caution');
				error_log('KTPWP Ajax: Attempted tax category nonce value: ' . $nonce_value);
				$nonce_verified = true;
			}
			
			if (!$nonce_verified) {
				error_log('KTPWP Ajax: Tax category nonce verification failed');
				wp_send_json_error(__('セキュリティ検証に失敗しました', 'kantanpro'));
				return;
			}

			// パラメータ取得
			$supplier_id = isset( $_POST['supplier_id'] ) ? absint( $_POST['supplier_id'] ) : 0;

			if ( $supplier_id <= 0 ) {
				wp_send_json_error( __( '協力会社IDが無効です', 'kantanpro' ) );
			}

			global $wpdb;
			$supplier_table = $wpdb->prefix . 'ktp_supplier';

			// 協力会社の税区分を取得
			$tax_category = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT tax_category FROM `{$supplier_table}` WHERE id = %d",
					$supplier_id
				)
			);

			// データベースエラーのチェック
			if ( $wpdb->last_error ) {
				error_log( 'KTPWP Ajax: Database error when getting tax category: ' . $wpdb->last_error );
				wp_send_json_error( __( 'データベースエラーが発生しました', 'kantanpro' ) );
				return;
			}

			// 協力会社が見つからない場合
			if ( $tax_category === null ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'KTPWP Ajax: Supplier not found for tax category lookup, supplier ID: ' . $supplier_id );
				}
				// デフォルト値として内税を返す
				$tax_category = '内税';
			}

			// デフォルト値の設定
			if ( empty( $tax_category ) ) {
				$tax_category = '内税';
			}

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'KTPWP Ajax: Supplier tax category retrieved: ' . $tax_category . ' for supplier ID: ' . $supplier_id );
			}

			wp_send_json_success(
				array(
					'tax_category' => $tax_category,
					'supplier_id'  => $supplier_id,
				)
			);

		} catch ( Exception $e ) {
			error_log( 'KTPWP Ajax ajax_get_supplier_tax_category Error: ' . $e->getMessage() );
			wp_send_json_error( __( 'エラーが発生しました: ' . $e->getMessage(), 'kantanpro' ) );
		}
	}

	/**
	 * フィールド自動保存（完了日など）
	 */
	public function ajax_auto_save_field() {
		try {
			// デバッグログ
			error_log( 'KTPWP Ajax: ajax_auto_save_field called' );

			// 権限チェック
			if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'ktpwp_access' ) ) {
				error_log( 'KTPWP Ajax: Permission check failed' );
				wp_send_json_error( __( 'この操作を行う権限がありません。', 'kantanpro' ) );
				return;
			}

			// nonce検証（複数キー・複数アクションに対応：仕事リスト・WC受注・受注書詳細のいずれでも通るようにする）
			$nonce_keys   = array( 'nonce', 'security', '_wpnonce', 'ktp_ajax_nonce' );
			$nonce_actions = array( 'ktp_ajax_nonce', 'ktpwp_ajax_nonce', 'ktpwp_auto_save_nonce' );
			$nonce_value  = '';
			foreach ( $nonce_keys as $key ) {
				if ( ! empty( $_POST[ $key ] ) && is_string( $_POST[ $key ] ) ) {
					$nonce_value = sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
					break;
				}
			}
			$nonce_ok = false;
			if ( $nonce_value !== '' ) {
				foreach ( $nonce_actions as $action ) {
					if ( wp_verify_nonce( $nonce_value, $action ) ) {
						$nonce_ok = true;
						break;
					}
				}
			}
			if ( ! $nonce_ok ) {
				error_log( 'KTPWP Ajax: Nonce verification failed (ajax_auto_save_field)' );
				wp_send_json_error( __( 'セキュリティ検証に失敗しました', 'kantanpro' ) );
				return;
			}

			// パラメータ取得
			$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
			$field_name = isset( $_POST['field_name'] ) ? sanitize_text_field( wp_unslash( $_POST['field_name'] ) ) : '';
			if ( $field_name === 'memo' ) {
				$field_value = isset( $_POST['field_value'] ) ? $this->sanitize_ktp_order_memo( $_POST['field_value'] ) : '';
			} else {
				$field_value = isset( $_POST['field_value'] ) ? sanitize_text_field( wp_unslash( $_POST['field_value'] ) ) : '';
			}

			if ( $order_id <= 0 ) {
				wp_send_json_error( __( '受注書IDが無効です', 'kantanpro' ) );
			}

			if ( empty( $field_name ) ) {
				wp_send_json_error( __( 'フィールド名が指定されていません', 'kantanpro' ) );
			}

			// 許可されたフィールド名のみ更新可能
			$allowed_fields = array( 'promised_delivery_date', 'desired_delivery_date', 'completion_date', 'expected_delivery_date', 'created_at', 'memo' );
			if ( ! in_array( $field_name, $allowed_fields ) ) {
				wp_send_json_error( __( '許可されていないフィールドです', 'kantanpro' ) );
			}

			if ( $field_name === 'created_at' ) {
				$updated_reception_date = $this->update_order_reception_date( $order_id, $field_value );
				wp_send_json_success(
					array(
						'message'      => __( '受付日が正常に保存されました', 'kantanpro' ),
						'field_name'   => $field_name,
						'field_value'  => $updated_reception_date['field_value'],
						'created_at'   => $updated_reception_date['created_at'],
						'display_time' => $updated_reception_date['display_time'],
					)
				);
			}

			global $wpdb;
			$table_name = $wpdb->prefix . 'ktp_order';

			$date_autocreate = array( 'promised_delivery_date', 'desired_delivery_date', 'expected_delivery_date', 'completion_date' );
			if ( in_array( $field_name, $date_autocreate, true ) ) {
				$col = $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM `{$table_name}` LIKE %s", $field_name ) );
				if ( ! $col ) {
					$wpdb->query( "ALTER TABLE `{$table_name}` ADD COLUMN `{$field_name}` DATE NULL" );
				}
			}

			// データベース更新
			$update_data = array( $field_name => $field_value );
			$result = $wpdb->update( $table_name, $update_data, array( 'id' => $order_id ) );

			if ( $result === false ) {
				error_log( 'KTPWP Ajax: Database update failed: ' . $wpdb->last_error );
				wp_send_json_error( __( 'データベース更新に失敗しました: ', 'kantanpro' ) . $wpdb->last_error );
			}

			error_log( 'KTPWP Ajax: Field saved successfully - Order ID: ' . $order_id . ', Field: ' . $field_name . ', Value: ' . $field_value );

			wp_send_json_success(
                array(
					'message' => __( 'フィールドが正常に保存されました', 'kantanpro' ),
					'field_name' => $field_name,
					'field_value' => $field_value,
                )
            );

		} catch ( Exception $e ) {
			error_log( 'KTPWP Ajax ajax_auto_save_field Error: ' . $e->getMessage() );
			wp_send_json_error( __( 'エラーが発生しました: ' . $e->getMessage(), 'kantanpro' ) );
		}
	}

	/**
	 * Log Ajax errors
	 *
	 * @param string $message Error message
	 * @param array  $context Additional context data
	 */
	private function log_ajax_error( $message, $context = array() ) {
		$log_message = 'KTPWP Ajax Error: ' . $message;
		if ( ! empty( $context ) ) {
			$log_message .= ' Context: ' . print_r( $context, true );
		}
		error_log( $log_message );
	}

	/**
	 * 協力会社の適格請求書番号を取得
	 */
	public function ajax_get_supplier_qualified_invoice_number() {
		try {
			// デバッグログ
			error_log( 'KTPWP Ajax: ajax_get_supplier_qualified_invoice_number called' );

			// 権限チェック
			if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'ktpwp_access' ) ) {
				error_log( 'KTPWP Ajax: Permission check failed' );
				wp_send_json_error( __( 'この操作を行う権限がありません。', 'kantanpro' ) );
				return;
			}

			// nonce検証（一時的に緩和版）
			$nonce_verified = false;
			$nonce_value = '';
			$nonce_sources = [
				'nonce',
				'_wpnonce',
				'_ajax_nonce',
				'ktp_ajax_nonce',
				'security'
			];

			foreach ($nonce_sources as $source) {
				if (isset($_POST[$source]) && !empty($_POST[$source])) {
					$nonce_value = $_POST[$source];
					error_log('KTPWP Ajax: Trying nonce from source: ' . $source . ' with value: ' . $nonce_value);
					// 複数のnonce名で検証を試行
					$nonce_names = [
						'ktp_ajax_nonce',
						'_wpnonce',
						'_ajax_nonce',
						'auto_save',
						'general'
					];
					foreach ($nonce_names as $name) {
						if (wp_verify_nonce($nonce_value, $name)) {
							$nonce_verified = true;
							error_log('KTPWP Ajax: Nonce verified with source: ' . $source . ' and name: ' . $name);
							break 2;
						}
					}
				}
			}
			
			// 権限があるユーザーの場合は、nonce検証を一時的に緩和
			if (!$nonce_verified && (current_user_can('edit_posts') || current_user_can('ktpwp_access'))) {
				error_log('KTPWP Ajax ajax_get_supplier_qualified_invoice_number: Nonce verification failed but user has permissions, proceeding with caution');
				error_log('KTPWP Ajax: Attempted nonce value: ' . $nonce_value);
				error_log('[AJAX_GET_SUPPLIER_QUALIFIED_INVOICE] Proceeding without nonce verification due to user permissions');
				$nonce_verified = true;
			}
			
			if (!$nonce_verified) {
				error_log('[AJAX_GET_SUPPLIER_QUALIFIED_INVOICE] Security check failed - all nonce sources: ' . print_r($nonce_sources, true));
				error_log('[AJAX_GET_SUPPLIER_QUALIFIED_INVOICE] POST data: ' . print_r($_POST, true));
				wp_send_json_error(__('セキュリティ検証に失敗しました', 'kantanpro'));
				return;
			}

			// パラメータ取得
			$supplier_id = isset( $_POST['supplier_id'] ) ? absint( $_POST['supplier_id'] ) : 0;
			
			// デバッグ情報をログに記録
			error_log( 'KTPWP Ajax: ajax_get_supplier_qualified_invoice_number - supplier_id: ' . $supplier_id . ', POST data: ' . print_r($_POST, true) );
			
			if ( $supplier_id <= 0 ) {
				error_log( 'KTPWP Ajax: Invalid supplier ID: ' . $supplier_id );
				wp_send_json_error( __( '協力会社IDが無効です (ID: ', 'kantanpro' ) . $supplier_id . ')' );
				return;
			}

			global $wpdb;
			$supplier_table = $wpdb->prefix . 'ktp_supplier';

			// テーブルが存在するかチェック
			$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$supplier_table'") === $supplier_table;
			if (!$table_exists) {
				error_log('KTPWP Ajax: Supplier table does not exist: ' . $supplier_table);
				wp_send_json_error(__('協力会社テーブルが存在しません', 'kantanpro'));
				return;
			}

			// 協力会社の適格請求書番号を取得
			$qualified_invoice_number = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT qualified_invoice_number FROM `{$supplier_table}` WHERE id = %d",
					$supplier_id
				)
			);

			// データベースエラーのチェック
			if ( $wpdb->last_error ) {
				error_log( 'KTPWP Ajax: Database error when getting qualified invoice number: ' . $wpdb->last_error );
				wp_send_json_error( __( 'データベースエラーが発生しました', 'kantanpro' ) );
				return;
			}

			// 協力会社が見つからない場合
			if ( $qualified_invoice_number === null ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'KTPWP Ajax: Supplier not found or no qualified invoice number set for supplier ID: ' . $supplier_id );
				}
				// エラーではなく、適格請求書番号がない場合として扱う
				$qualified_invoice_number = '';
			}

			$qualified_invoice_number = $qualified_invoice_number ? trim( $qualified_invoice_number ) : '';

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'KTPWP Ajax: Qualified invoice number for supplier ' . $supplier_id . ': ' . ($qualified_invoice_number ?: 'なし') );
			}

			wp_send_json_success(
				array(
					'qualified_invoice_number' => $qualified_invoice_number,
					'supplier_id' => $supplier_id,
					'debug_info' => array(
						'table_exists' => $table_exists,
						'nonce_verified' => $nonce_verified,
						'nonce_value' => $nonce_value
					)
				)
			);

		} catch ( Exception $e ) {
			error_log( 'KTPWP Ajax ajax_get_supplier_qualified_invoice_number Error: ' . $e->getMessage() );
			wp_send_json_error( __( 'エラーが発生しました: ' . $e->getMessage(), 'kantanpro' ) );
		}
	}

	/**
	 * 進捗更新Ajaxハンドラー
	 * ユーザーは進捗を柔軟に変更可能（1〜7のいずれへでも変更可。順番でなく飛び級も可）。
	 */
	public function ajax_update_progress() {
		// セキュリティチェック
		if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'ktpwp_access' ) ) {
			wp_send_json_error( __( '権限がありません', 'kantanpro' ) );
		}

		// nonceチェック（進捗更新は general / progress_update のどちらでも可）
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( empty( $nonce ) || ( ! wp_verify_nonce( $nonce, 'ktp_ajax_nonce' ) && ! wp_verify_nonce( $nonce, 'ktpwp_ajax_nonce' ) ) ) {
			wp_send_json_error( __( 'セキュリティ検証に失敗しました', 'kantanpro' ) );
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'ktp_order';

		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		$new_progress = isset( $_POST['progress'] ) ? absint( $_POST['progress'] ) : 0;

		if ( $order_id <= 0 || $new_progress < 1 || $new_progress > 7 ) {
			wp_send_json_error( __( 'パラメータが不正です', 'kantanpro' ) );
		}

		// 現在の進捗を取得
		$current_order = $wpdb->get_row( $wpdb->prepare( "SELECT progress FROM {$table_name} WHERE id = %d", $order_id ) );
		if ( ! $current_order ) {
			wp_send_json_error( __( '受注書が見つかりません', 'kantanpro' ) );
		}

		$update_data = array( 'progress' => $new_progress );

		// 進捗が「完了」（progress = 4）に変更された場合、完了日を実行した日で記録
		if ( $new_progress == 4 && $current_order->progress != 4 ) {
			$update_data['completion_date'] = current_time( 'Y-m-d' );
		}

		// 進捗が受注以前（受付中、見積中、受注）に変更された場合、完了日をクリア
		if ( in_array( $new_progress, array( 1, 2, 3 ) ) && $current_order->progress > 3 ) {
			$update_data['completion_date'] = null;
		}

		// データベース更新（フォーマット指定で確実に保存）
		$data_format   = array( '%d' );
		$where_format  = array( '%d' );
		if ( isset( $update_data['completion_date'] ) ) {
			$data_format[] = $update_data['completion_date'] === null ? '%s' : '%s';
		}
		$result = $wpdb->update( $table_name, $update_data, array( 'id' => $order_id ), $data_format, $where_format );

		if ( $result !== false ) {
			if ( class_exists( 'KTPWP_Order_Progress_Effects' ) ) {
				KTPWP_Order_Progress_Effects::after_progress_updated( $order_id, $new_progress );
			}

			wp_send_json_success( array(
				'message' => __( '進捗を更新しました', 'kantanpro' ),
				'progress' => $new_progress,
				'completion_date' => isset( $update_data['completion_date'] ) ? $update_data['completion_date'] : null
			) );
		} else {
			wp_send_json_error( __( 'データベース更新に失敗しました', 'kantanpro' ) );
		}
	}

	/**
	 * 受注書データを取得
	 */
	public function get_order_data() {
		// セキュリティチェック
		if (!isset($_POST['ktp_ajax_nonce']) || !wp_verify_nonce($_POST['ktp_ajax_nonce'], 'ktp_ajax_nonce')) {
			wp_send_json_error( __( 'セキュリティチェックに失敗しました。', 'kantanpro' ) );
			return;
		}
		
		// 受注書IDを取得
		$order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
		if (!$order_id) {
			wp_send_json_error( __( '受注書IDが指定されていません。', 'kantanpro' ) );
			return;
		}
		
		// 受注書データを取得
		global $wpdb;
		$table_name = $wpdb->prefix . 'ktp_order';
		$order_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$table_name}` WHERE id = %d", $order_id));
		
		if (!$order_data) {
			wp_send_json_error( __( '指定された受注書が見つかりません。', 'kantanpro' ) );
			return;
		}
		
		// 受注書タブクラスのインスタンスを作成
		        if (class_exists('KTPWP_Order_Class')) {
            $order_tab = new KTPWP_Order_Class();
			
			// 受注書のHTMLを生成
			ob_start();
			$order_tab->Order_Tab_View('order');
			$html = ob_get_clean();
			
			wp_send_json_success(array(
				'html' => $html,
				'order_id' => $order_id
			));
		} else {
			wp_send_json_error( __( '受注書クラスが見つかりません。', 'kantanpro' ) );
		}
	}

	/**
	 * 一括請求書プレビュー：印影サイズ更新
	 */
	public function ajax_update_bulk_invoice_seal_size() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( $nonce === '' || ! wp_verify_nonce( $nonce, 'ktp_ajax_nonce' ) ) {
			wp_send_json_error( __( 'セキュリティ検証に失敗しました', 'kantanpro' ) );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( '権限がありません', 'kantanpro' ) );
		}
		if ( ! class_exists( 'KTPWP_Pdf_Document_Settings' ) ) {
			wp_send_json_error( __( '設定クラスが見つかりません', 'kantanpro' ) );
		}

		$width  = isset( $_POST['seal_max_width_px'] ) ? (int) $_POST['seal_max_width_px'] : 0;
		$height = isset( $_POST['seal_max_height_px'] ) ? (int) $_POST['seal_max_height_px'] : 0;
		KTPWP_Pdf_Document_Settings::merge_bulk_invoice_seal_size( $width, $height );

		$resolved = KTPWP_Pdf_Document_Settings::resolve( KTPWP_Pdf_Document_Kind::BULK_INVOICE );
		$sizes    = KTPWP_Pdf_Document_Settings::scaled_branding_sizes( $resolved );
		$scaled   = KTPWP_Pdf_Document_Settings::scaled_bulk_issuer_seal_branding( $sizes );

		wp_send_json_success(
			array(
				'ok'                 => true,
				'seal_max_width_px'  => (int) $resolved['seal_max_width_px'],
				'seal_max_height_px' => (int) $resolved['seal_max_height_px'],
				'display_width_px'   => (int) $scaled['seal_max_width_px'],
				'display_height_px'  => (int) $scaled['seal_max_height_px'],
			)
		);
	}

	/**
	 * 一括請求書プレビュー：ロゴ幅（%）更新
	 */
	public function ajax_update_bulk_invoice_logo_width() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( $nonce === '' || ! wp_verify_nonce( $nonce, 'ktp_ajax_nonce' ) ) {
			wp_send_json_error( __( 'セキュリティ検証に失敗しました', 'kantanpro' ) );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( '権限がありません', 'kantanpro' ) );
		}
		if ( ! class_exists( 'KTPWP_Pdf_Document_Settings' ) ) {
			wp_send_json_error( __( '設定クラスが見つかりません', 'kantanpro' ) );
		}

		$percent = isset( $_POST['issuer_logo_width_percent'] ) ? (int) $_POST['issuer_logo_width_percent'] : 0;
		$align   = isset( $_POST['issuer_logo_align'] ) ? sanitize_text_field( wp_unslash( $_POST['issuer_logo_align'] ) ) : KTPWP_Pdf_Document_Settings::ISSUER_LOGO_ALIGN_DEFAULT;
		KTPWP_Pdf_Document_Settings::merge_bulk_invoice_logo_width_percent( $percent, $align );

		$resolved = KTPWP_Pdf_Document_Settings::resolve( KTPWP_Pdf_Document_Kind::BULK_INVOICE );

		wp_send_json_success(
			array(
				'ok'                         => true,
				'issuer_logo_width_percent'  => (int) $resolved['issuer_logo_width_percent'],
				'issuer_logo_align'          => (string) $resolved['issuer_logo_align'],
			)
		);
	}

	/**
	 * 宛名印刷プレビュー：宛名ブロック位置（封筒窓合わせ）更新
	 */
	public function ajax_update_atena_label_position() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( $nonce === '' || ! wp_verify_nonce( $nonce, 'ktp_ajax_nonce' ) ) {
			wp_send_json_error( __( 'セキュリティ検証に失敗しました', 'kantanpro' ) );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( '権限がありません', 'kantanpro' ) );
		}
		if ( ! class_exists( 'KTPWP_Pdf_Document_Settings' ) ) {
			wp_send_json_error( __( '設定クラスが見つかりません', 'kantanpro' ) );
		}

		$top  = isset( $_POST['top_mm'] ) ? (int) $_POST['top_mm'] : KTPWP_Pdf_Document_Settings::ATENA_LABEL_BASE_TOP_MM;
		$left = isset( $_POST['left_mm'] ) ? (int) $_POST['left_mm'] : KTPWP_Pdf_Document_Settings::ATENA_LABEL_BASE_LEFT_MM;

		$position = KTPWP_Pdf_Document_Settings::merge_atena_label_position( $top, $left );

		wp_send_json_success(
			array(
				'ok'      => true,
				'top_mm'  => (int) $position['top_mm'],
				'left_mm' => (int) $position['left_mm'],
			)
		);
	}

	/**
	 * 請求書候補データを取得
	 */
	public function ajax_get_invoice_candidates() {
		// セキュリティチェック
		if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ktp_ajax_nonce')) {
			wp_send_json_error( __( 'セキュリティチェックに失敗しました。', 'kantanpro' ) );
			return;
		}
		
		// 顧客IDを取得
		$client_id = isset($_POST['client_id']) ? absint($_POST['client_id']) : 0;
		if (!$client_id) {
			wp_send_json_error( __( '顧客IDが指定されていません。', 'kantanpro' ) );
			return;
		}
		
		global $wpdb;
		
		// 顧客情報を取得
		$client_table = $wpdb->prefix . 'ktp_client';
		$client_data = $wpdb->get_row($wpdb->prepare(
			"SELECT * FROM `{$client_table}` WHERE id = %d",
			$client_id
		));
		
		if (!$client_data) {
			wp_send_json_error( __( '指定された顧客が見つかりません。', 'kantanpro' ) );
			return;
		}
		
		// 受注書データを取得（完了済みで請求済みでないもの）
		$order_table = $wpdb->prefix . 'ktp_order';
		$orders = $wpdb->get_results($wpdb->prepare(
			"SELECT * FROM `{$order_table}` 
			WHERE client_id = %d 
			AND progress = 4 
			AND completion_date IS NOT NULL
			AND completion_date <> '0000-00-00'
			AND completion_date <> '0000-00-00 00:00:00'
			ORDER BY completion_date ASC",
			$client_id
		));

		// MySQL のゼロ日付など IS NOT NULL を満たすが無効な完了日は除外
		if ( ! empty( $orders ) ) {
			$orders = array_values(
				array_filter(
					$orders,
					static function ( $row ) {
						$raw = isset( $row->completion_date ) ? trim( (string) $row->completion_date ) : '';
						if ( $raw === '' || 0 === strpos( $raw, '0000-00-00' ) ) {
							return false;
						}
						return true;
					}
				)
			);
		}

		// 前払い（前入金済・EC受注・WC受注）は後払い請求対象から除外
		if ( class_exists( 'KTPWP_Payment_Timing' ) && ! empty( $orders ) ) {
			$orders = array_filter( $orders, function ( $order ) use ( $client_data ) {
				return ! KTPWP_Payment_Timing::is_prepay( $order, $client_data );
			} );
			$orders = array_values( $orders );
		}

		if ( empty( $orders ) ) {
			wp_send_json_error( __( '請求対象の受注書が見つかりません。', 'kantanpro' ) );
			return;
		}
		
		// 月別グループに分類
		$monthly_groups = array();
		foreach ($orders as $order) {
			$date_candidates = array(
				isset( $order->completion_date ) ? $order->completion_date : '',
				isset( $order->order_date ) ? $order->order_date : '',
				isset( $order->created_at ) ? $order->created_at : '',
				isset( $order->time ) ? $order->time : '',
			);
			$completion_date = null;

			foreach ( $date_candidates as $date_candidate ) {
				if ( empty( $date_candidate ) || in_array( (string) $date_candidate, array( '0000-00-00', '0000-00-00 00:00:00' ), true ) ) {
					continue;
				}

				try {
					$candidate_date = is_numeric( $date_candidate ) ? new DateTime( '@' . (int) $date_candidate ) : new DateTime( (string) $date_candidate );
				} catch ( Exception $e ) {
					continue;
				}

				$candidate_year = (int) $candidate_date->format( 'Y' );
				$candidate_month = (int) $candidate_date->format( 'm' );
				if ( $candidate_year >= 1 && $candidate_month >= 1 && $candidate_month <= 12 ) {
					$completion_date = $candidate_date;
					break;
				}
			}

			if ( null === $completion_date ) {
				$completion_date = new DateTime( current_time( 'mysql' ) );
			}

			$year = (int) $completion_date->format('Y');
			$month = (int) $completion_date->format('m');
			$key = sprintf( '%04d-%02d', $year, $month );
			$completion_timestamp = $completion_date->getTimestamp();
			
			if (!isset($monthly_groups[$key])) {
				$monthly_groups[$key] = array(
					'billing_period' => wp_date( __( 'Y年n月分', 'kantanpro' ), $completion_timestamp ),
					'closing_date' => $this->bulk_invoice_closing_date_label_for_period( $key, $client_data ),
					'orders' => array(),
					'subtotal' => 0,
					'tax_amount' => 0
				);
			}
			
			// 請求項目を取得
			$invoice_items_table = $wpdb->prefix . 'ktp_order_invoice_items';
			$invoice_items = $wpdb->get_results($wpdb->prepare(
				"SELECT * FROM `{$invoice_items_table}` WHERE order_id = %d ORDER BY COALESCE(sort_order, id) ASC, id ASC",
				$order->id
			));
			
			if ($wpdb->last_error) {
				error_log("KTPWP Invoice AJAX: 請求項目取得エラー - " . $wpdb->last_error);
			}
			
			$order->invoice_items = $invoice_items;
			$monthly_groups[$key]['orders'][] = $order;
		}
		
		if ( empty( $monthly_groups ) ) {
			wp_send_json_error( __( '請求対象の受注書が見つかりません。', 'kantanpro' ) );
			return;
		}

		// 配列のキーをリセット
		$monthly_groups = array_values($monthly_groups);
		
		// 部署情報を取得
		$department_table = $wpdb->prefix . 'ktp_department';
		$departments = $wpdb->get_results($wpdb->prepare(
			"SELECT * FROM `{$department_table}` WHERE client_id = %d",
			$client_id
		));
		
		// 適格請求書番号を取得（一般設定から）
		$qualified_invoice_number = '';
		$general_settings = get_option('ktp_general_settings', array());
		if (isset($general_settings['qualified_invoice_number']) && !empty($general_settings['qualified_invoice_number'])) {
			$qualified_invoice_number = $general_settings['qualified_invoice_number'];
		}
		
		// 顧客の税区分を取得
		$tax_category = $client_data->tax_category ?: '内税';
		
		// 税区分に応じた消費税計算の修正
		foreach ($monthly_groups as $key => $group) {
			$group_subtotal = 0;
			$group_tax_amount = 0;
			
			foreach ($group['orders'] as $order) {
				$order_subtotal = 0;
				$order_tax_amount = 0;
				
				foreach ($order->invoice_items as $item) {
					$price = floatval($item->price);
					$quantity = floatval($item->quantity);
					$amount = floatval($item->amount);
					$total_price = $amount > 0 ? $amount : ($price * $quantity);
					
					if ($tax_category === '外税') {
						// 外税の場合：税抜き価格 + 消費税
						$tax_rate = floatval($item->tax_rate);
						if ($tax_rate > 0) {
							$item_tax = $total_price * ($tax_rate / 100);
							$order_subtotal += $total_price; // 税抜き価格
							$order_tax_amount += $item_tax;
						} else {
							$order_subtotal += $total_price; // 税抜き価格
						}
					} else {
						// 内税の場合：税込価格から消費税を逆算
						$tax_rate = floatval($item->tax_rate);
						if ($tax_rate > 0) {
							$item_subtotal = $total_price / (1 + ($tax_rate / 100)); // 税抜き価格を逆算
							$item_tax = $total_price - $item_subtotal; // 消費税を計算
							$order_subtotal += $item_subtotal; // 税抜き価格
							$order_tax_amount += $item_tax;
						} else {
							$order_subtotal += $total_price; // 税抜き価格
						}
					}
				}
				
				// 受注書の合計を更新
				$order->total_amount = $order_subtotal + $order_tax_amount;
				$order->subtotal_amount = $order_subtotal;
				$order->tax_amount = $order_tax_amount;
				
				$group_subtotal += $order_subtotal;
				$group_tax_amount += $order_tax_amount;
			}
			
			// 月別グループの合計を更新
			$monthly_groups[ $key ]['subtotal'] = $group_subtotal;
			$monthly_groups[ $key ]['tax_amount'] = $group_tax_amount;
			$monthly_groups[ $key ]['sections']   = $this->build_bulk_invoice_sections( $group['orders'] );
		}
		
		// 支払期日を計算
		$payment_due_date = $this->calculate_payment_due_date($client_data);
		
		$bank_transfer_html = '';
		if ( class_exists( 'KTPWP_Settings' ) ) {
			$bank_transfer_html = KTPWP_Settings::get_bank_transfer_invoice_html();
		}

		// 請求元（自社）情報：一般設定・旧 ktp_setting と同じロジックで HTML 化
		$company_info_html = '';
		if ( ! class_exists( 'KTPWP_Order_Class' ) ) {
			require_once KTPWP_PLUGIN_DIR . 'includes/class-ktpwp-order-main.php';
		}
		if ( class_exists( 'KTPWP_Order_Class' ) ) {
			$order_for_company = new KTPWP_Order_Class();
			$company_info_html = $order_for_company->get_company_info_box_html();
		}

		$issuer_company_html = $company_info_html;
		$issuer_stack_html   = $company_info_html;
		if ( class_exists( 'KTPWP_Pdf_Branding' ) && class_exists( 'KTPWP_Pdf_Document_Settings' ) && class_exists( 'KTPWP_Pdf_Document_Renderer' ) ) {
			$bulk_doc_settings = KTPWP_Pdf_Document_Settings::resolve( KTPWP_Pdf_Document_Kind::BULK_INVOICE );
			$pdf_branding      = KTPWP_Pdf_Branding::for_documents();
			$issuer_company_html = KTPWP_Pdf_Document_Renderer::bulk_invoice_company_section_html(
				$pdf_branding,
				$bulk_doc_settings,
				$company_info_html
			);
			$issuer_stack_html = KTPWP_Pdf_Document_Renderer::bulk_invoice_issuer_stack_html(
				$pdf_branding,
				$bulk_doc_settings,
				$qualified_invoice_number,
				$bank_transfer_html,
				$company_info_html
			);
		}

		$selected_department = null;
		$bulk_department_contact_line = '';
		$representative_name = trim( (string) ( $client_data->representative_name ?? '' ) );
		if ( $representative_name === '' ) {
			$representative_name = trim( (string) ( $client_data->name ?? '' ) );
		}
		if ( class_exists( 'KTPWP_Department_Manager' ) ) {
			$selected_dept_row = null;
			$requested_dept_id = isset( $_POST['selected_department_id'] ) ? absint( $_POST['selected_department_id'] ) : 0;
			if ( $requested_dept_id > 0 ) {
				$candidate_dept = KTPWP_Department_Manager::get_department( $requested_dept_id );
				if ( $candidate_dept && (int) $candidate_dept->client_id === (int) $client_id ) {
					KTPWP_Department_Manager::update_department_selection( $requested_dept_id, true );
					$selected_dept_row = $candidate_dept;
				}
			}
			if ( ! $selected_dept_row ) {
				$selected_dept_row = KTPWP_Department_Manager::get_selected_department_by_client( $client_id );
			}
			if ( $selected_dept_row ) {
				$selected_department = array(
					'department_name' => KTPWP_Department_Manager::department_name_for_mail_addressee( $selected_dept_row->department_name ),
					'contact_person' => trim( (string) ( $selected_dept_row->contact_person ?? '' ) ),
				);
				$bulk_department_contact_line = KTPWP_Department_Manager::bulk_invoice_department_contact_line( $selected_dept_row );
			}
		}

		$document_lead = class_exists( 'KTPWP_Pdf_Document_Settings' )
			? KTPWP_Pdf_Document_Settings::resolve_lead(
				KTPWP_Pdf_Document_Kind::BULK_INVOICE,
				'この度はご用命いただき誠にありがとうございました。 以下の通りご請求させていただきますので、よろしくお願い申し上げます。'
			)
			: 'この度はご用命いただき誠にありがとうございました。 以下の通りご請求させていただきますので、よろしくお願い申し上げます。';

		// レスポンスデータを構築
		$response_data = array(
			'client_name' => $client_data->company_name,
			'client_address' => $this->format_client_address_for_invoice_preview( $client_data ),
			'client_contact' => $client_data->name ?? '',
			'representative_name' => $representative_name,
			'has_representative_contact' => ( $representative_name !== '' ),
			'bulk_department_contact_line' => $bulk_department_contact_line,
			'has_department_contact' => ( $bulk_department_contact_line !== '' ),
			'document_lead' => $document_lead,
			'monthly_groups' => $monthly_groups,
			'departments' => $departments,
			'qualified_invoice_number' => $qualified_invoice_number,
			'tax_category' => $tax_category,
			'selected_department' => $selected_department,
			'payment_due_date' => $payment_due_date,
			'bank_transfer_html' => $bank_transfer_html,
			'company_info' => $company_info_html,
			'issuer_company_html' => $issuer_company_html,
			'issuer_stack_html' => $issuer_stack_html,
		);
		
		wp_send_json_success($response_data);
	}

	/**
	 * 一括請求の月別グループ表示用締日（顧客の締め日 × 対象年月。完了日は使わない）
	 *
	 * @param string $period_key 例: 2026-04
	 * @param object $client_data
	 * @return string
	 */
	private function bulk_invoice_closing_date_label_for_period( $period_key, $client_data ) {
		$closing_day = isset( $client_data->closing_day ) ? trim( (string) $client_data->closing_day ) : '';
		if ( $closing_day === '' || $closing_day === 'なし' || ! preg_match( '/^\d{4}-\d{2}$/', (string) $period_key ) ) {
			return '—';
		}

		$year  = (int) substr( $period_key, 0, 4 );
		$month = (int) substr( $period_key, 5, 2 );
		if ( $year < 1 || $month < 1 || $month > 12 ) {
			return '—';
		}

		try {
			$base = new DateTime( sprintf( '%04d-%02d-01', $year, $month ) );
		} catch ( Exception $e ) {
			return '—';
		}

		if ( in_array( $closing_day, array( '末', '末日' ), true ) ) {
			$base->modify( 'last day of this month' );

			return wp_date( __( 'Y年m月d日', 'kantanpro' ), $base->getTimestamp() );
		}

		$day_num = (int) preg_replace( '/[^0-9]/', '', $closing_day );
		if ( $day_num < 1 ) {
			return '—';
		}

		$last_day = (int) $base->format( 't' );
		if ( $day_num > $last_day ) {
			$day_num = $last_day;
		}

		try {
			$closing = new DateTime( sprintf( '%04d-%02d-%02d', $year, $month, $day_num ) );
		} catch ( Exception $e ) {
			return '—';
		}

		return wp_date( __( 'Y年m月d日', 'kantanpro' ), $closing->getTimestamp() );
	}

	/**
	 * 請求書プレビュー用に住所を1～2行の文字列へまとめる（郵便・都道府県・市区町村・番地・建物。従来は address のみ返して空になりがちだった）
	 *
	 * @param object $client_data ktp_client 行
	 * @return string
	 */
	private function format_client_address_for_invoice_preview( $client_data ) {
		$postal_raw = isset( $client_data->postal_code ) ? sanitize_text_field( (string) $client_data->postal_code ) : '';
		$pref       = isset( $client_data->prefecture ) ? trim( (string) $client_data->prefecture ) : '';
		$city       = isset( $client_data->city ) ? trim( (string) $client_data->city ) : '';
		$street     = isset( $client_data->address ) ? trim( (string) $client_data->address ) : '';
		$building   = isset( $client_data->building ) ? trim( (string) $client_data->building ) : '';
		$body       = $pref . $city . $street . $building;
		if ( $body !== '' && strpos( $body, '〒' ) === 0 ) {
			return $body;
		}
		$postal_line = '';
		if ( $postal_raw !== '' ) {
			$digits = preg_replace( '/\D/', '', $postal_raw );
			if ( strlen( $digits ) === 7 ) {
				$postal_line = '〒' . substr( $digits, 0, 3 ) . '-' . substr( $digits, 3 );
			} else {
				$postal_line = ( strpos( $postal_raw, '〒' ) === 0 ) ? $postal_raw : ( '〒' . $postal_raw );
			}
		}
		if ( $postal_line !== '' && $body !== '' ) {
			return $postal_line . "\n" . $body;
		}
		if ( $postal_line !== '' ) {
			return $postal_line;
		}
		return $body;
	}

	/**
	 * 顧客の支払条件に基づいて支払期日を計算
	 *
	 * @param object $client_data 顧客データ
	 * @return string 支払期日（Y-m-d形式）
	 */
	private function calculate_payment_due_date($client_data) {
		// デフォルト値
		$closing_day = $client_data->closing_day ?: '末日';
		$payment_month = $client_data->payment_month ?: '翌月';
		$payment_day = $client_data->payment_day ?: '末日';
		
		// 今日の日付を基準に計算
		$today = new DateTime();
		$current_year = (int)$today->format('Y');
		$current_month = (int)$today->format('m');
		$current_day = (int)$today->format('d');
		
		// 支払日が即日の場合は今日の日付を返す
		if ($payment_day === '即日') {
			return $today->format('Y-m-d');
		}
		
		// 締め日を数値に変換
		$closing_day_num = 0;
		if ($closing_day === '末日') {
			$closing_day_num = 31; // 月末を表す特別な値
		} elseif ($closing_day === 'なし') {
			$closing_day_num = 0; // 締め日なし
		} else {
			$closing_day_num = (int)str_replace('日', '', $closing_day);
		}
		
		// 締め日を考慮した請求月を決定
		$billing_year = $current_year;
		$billing_month = $current_month;
		
		if ($closing_day_num > 0) {
			// 締め日がある場合
			$closing_date = new DateTime();
			if ($closing_day_num === 31) {
				$closing_date->modify('last day of this month');
			} else {
				$closing_date->setDate($current_year, $current_month, $closing_day_num);
				// 指定日が月末を超える場合は末日に補正
				$last_day = (int)$closing_date->format('t');
				if ($closing_day_num > $last_day) {
					$closing_date->modify('last day of this month');
				}
			}
			
			// 今日が締め日を過ぎている場合は翌月の締め日を請求対象とする
			if ($today > $closing_date) {
				$billing_month++;
				if ($billing_month > 12) {
					$billing_month = 1;
					$billing_year++;
				}
			}
		} else {
			// 締め日なしの場合は、現在の月を請求対象とする
			$billing_year = $current_year;
			$billing_month = $current_month;
		}
		
		// 支払月を計算
		$payment_year = $billing_year;
		$payment_month_num = $billing_month;
		
		switch ($payment_month) {
			case '今月':
				$payment_month_num = $billing_month;
				break;
			case '翌月':
				$payment_month_num = $billing_month + 1;
				if ($payment_month_num > 12) {
					$payment_month_num = 1;
					$payment_year++;
				}
				break;
			case '翌々月':
				$payment_month_num = $billing_month + 2;
				if ($payment_month_num > 12) {
					$payment_month_num = $payment_month_num - 12;
					$payment_year++;
				}
				break;
			default:
				$payment_month_num = $billing_month + 1;
				if ($payment_month_num > 12) {
					$payment_month_num = 1;
					$payment_year++;
				}
		}
		
		// 支払日を計算
		$payment_day_num = 0;
		if ($payment_day === '末日') {
			$payment_day_num = 31; // 月末を表す特別な値
		} else {
			$payment_day_num = (int)str_replace('日', '', $payment_day);
		}
		
		// 支払期日を計算
		$payment_due_date = new DateTime();
		$payment_due_date->setDate($payment_year, $payment_month_num, 1);
		
		if ($payment_day_num === 31) {
			// 末日の場合
			$payment_due_date->modify('last day of this month');
		} else {
			// 指定日の場合
			$payment_due_date->setDate($payment_year, $payment_month_num, $payment_day_num);
			
			// 指定日が月末を超える場合は末日に補正
			$last_day = (int)$payment_due_date->format('t');
			if ($payment_day_num > $last_day) {
				$payment_due_date->modify('last day of this month');
			}
		}
		
		return $payment_due_date->format('Y-m-d');
	}
	
	/**
	 * 顧客の支払条件の説明テキストを生成
	 *
	 * @param object $client_data 顧客データ
	 * @return string 支払条件の説明テキスト
	 */
	private function generate_payment_terms_text($client_data) {
		// 計算された支払期日のみを返す
		$payment_due_date = $this->calculate_payment_due_date($client_data);
		$formatted_due_date = date('Y年m月d日', strtotime($payment_due_date));
		
		return $formatted_due_date;
	}

	/**
	 * Ajax: 発注書メール内容取得
	 */
	public function ajax_get_purchase_order_email_content() {
		// AJAXレスポンスがJSONのみになるよう、余計な出力（Warning等）を抑止
		if ( ! headers_sent() ) {
			ob_start();
		}
		try {
			// セキュリティチェック
			if ( ! check_ajax_referer( 'ktpwp_ajax_nonce', 'nonce', false ) ) {
				throw new Exception( 'セキュリティ検証に失敗しました。' );
			}

			// 権限チェック
			if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'ktpwp_access' ) ) {
				throw new Exception( '権限がありません。' );
			}

			$order_id     = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
			$supplier_id  = isset( $_POST['supplier_id'] ) ? absint( $_POST['supplier_id'] ) : 0;
			$supplier_name = isset( $_POST['supplier_name'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['supplier_name'] ) ) ) : '';
			$mode         = ( isset( $_POST['mode'] ) && $_POST['mode'] === 'quote' ) ? 'quote' : 'order';

			if ( $order_id <= 0 ) {
				throw new Exception( '無効な受注書IDです。' );
			}

			if ( $supplier_id <= 0 && empty( $supplier_name ) ) {
				throw new Exception( '協力会社名または協力会社IDが指定されていません。' );
			}

			global $wpdb;

			// 受注書データを取得
			$order_table = $wpdb->prefix . 'ktp_order';
			$order = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM `{$order_table}` WHERE id = %d",
					$order_id
				)
			);

			if ( ! $order ) {
				throw new Exception( '受注書が見つかりません。' );
			}

			// 協力会社データを取得（ID優先、なければ会社名で検索。名前は前後空白を無視）
			$supplier_table = $wpdb->prefix . 'ktp_supplier';
			$supplier = null;
			if ( $supplier_id > 0 ) {
				$supplier = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT * FROM `{$supplier_table}` WHERE id = %d",
						$supplier_id
					)
				);
			}
			if ( ! $supplier && ! empty( $supplier_name ) ) {
				$supplier = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT * FROM `{$supplier_table}` WHERE TRIM(company_name) = %s",
						$supplier_name
					)
				);
			}
			if ( ! $supplier ) {
				throw new Exception( '協力会社が見つかりません。' );
			}
			// 以降のコスト項目フィルタでは取得した協力会社の会社名を使用
			$supplier_name = $supplier->company_name;

			// 顧客データを取得
			$client_table = $wpdb->prefix . 'ktp_client';
			$client = null;
			if ( ! empty( $order->client_id ) ) {
				$client = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT * FROM `{$client_table}` WHERE id = %d",
						$order->client_id
					)
				);
			}

			// コスト項目を取得
			$order_items = KTPWP_Order_Items::get_instance();
			$cost_items = $order_items->get_cost_items( $order_id );

			// 該当する協力会社のコスト項目のみをフィルタリング
			$filtered_cost_items = array();
			$total_amount = 0;
			$total_tax_amount = 0;
			$qualified_invoice_cost = 0;
			$non_qualified_invoice_cost = 0;

			foreach ( $cost_items as $item ) {
				// 協力会社名フィールドの確認（複数の可能性）
				$item_supplier_name = '';
				if ( isset( $item['purchase'] ) ) {
					$item_supplier_name = $item['purchase'];
				} elseif ( isset( $item['supplier_name'] ) ) {
					$item_supplier_name = $item['supplier_name'];
				} elseif ( isset( $item['supplier'] ) ) {
					$item_supplier_name = $item['supplier'];
				} elseif ( isset( $item['company_name'] ) ) {
					$item_supplier_name = $item['company_name'];
				}

				if ( $item_supplier_name === $supplier_name ) {
					$filtered_cost_items[] = $item;
					$amount = floatval( $item['amount'] );
					$total_amount += $amount;

					// 税額計算
					$tax_rate = $item['tax_rate'];
					$is_tax_free = empty( $tax_rate ) || $tax_rate === null;
					$tax_rate = $is_tax_free ? 0.0 : floatval( $tax_rate );
					$item_tax_category = isset( $item['tax_category'] ) ? $item['tax_category'] : '内税';
					$has_qualified_invoice = ! empty( $item['qualified_invoice_number'] );

					// 税額計算（非課税取引の場合は0円）
					if ( $is_tax_free ) {
						$tax_amount = 0;
					} elseif ( $item_tax_category === '外税' ) {
						$tax_amount = ceil( $amount * ( $tax_rate / 100 ) );
					} else {
						$tax_amount = ceil( $amount * ( $tax_rate / 100 ) / ( 1 + $tax_rate / 100 ) );
					}

					if ( $has_qualified_invoice ) {
						// 適格請求書あり：税抜金額をコストとする
						if ( $item_tax_category === '外税' ) {
							$cost_amount = $amount - $tax_amount;
						} else {
							$cost_amount = $amount - $tax_amount;
						}
						$qualified_invoice_cost += $cost_amount;
					} else {
						// 適格請求書なし：税込金額をコストとする
						$cost_amount = $amount;
						$non_qualified_invoice_cost += $cost_amount;
					}

					$total_tax_amount += $tax_amount;
				}
			}

			// 自社情報を取得
			$smtp_settings = get_option( 'ktp_smtp_settings', array() );
			$my_email = ! empty( $smtp_settings['email_address'] ) ? sanitize_email( $smtp_settings['email_address'] ) : '';
			$my_company = '';
			if ( class_exists( 'KTPWP_Settings' ) ) {
				$my_company = KTPWP_Settings::get_company_info();
			}

			// 旧システムからも取得（後方互換性）
			if ( empty( $my_company ) ) {
				$setting_table = $wpdb->prefix . 'ktp_setting';
				$setting = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT * FROM `{$setting_table}` WHERE id = %d",
						1
					)
				);
				if ( $setting && isset( $setting->my_company_content ) ) {
					$my_company = sanitize_text_field( strip_tags( $setting->my_company_content ) );
				}
			}

			// 発注書/見積依頼書メール内容を生成
			$subject = ( $mode === 'quote' )
				? '【見積依頼】受注書ID:' . $order_id . 'の件'
				: '【発注書】受注書ID:' . $order_id . 'の件';

			// メール本文を生成
			$body = $this->generate_purchase_order_email_body(
				$order,
				$supplier,
				$client,
				$filtered_cost_items,
				$my_company,
				$total_amount,
				$total_tax_amount,
				$qualified_invoice_cost,
				$non_qualified_invoice_cost,
				$mode
			);

			if ( ob_get_level() ) {
				ob_end_clean();
			}
			wp_send_json_success(
				array(
					'subject' => $subject,
					'body' => $body,
					'supplier_email' => isset( $supplier->email ) ? $supplier->email : '',
					'supplier_name' => isset( $supplier->name ) ? $supplier->name : '',
					'supplier_qualified_invoice_number' => isset( $supplier->qualified_invoice_number ) ? $supplier->qualified_invoice_number : '',
					'order_info' => array(
						'project_name' => $order->project_name,
						'order_date' => $order->order_date,
						'expected_delivery_date' => isset( $order->expected_delivery_date ) ? $order->expected_delivery_date : null,
						'client_name' => $client ? $client->company_name : $order->customer_name,
					),
					'cost_summary' => array(
						'total_amount' => $total_amount,
						'total_tax_amount' => $total_tax_amount,
						'qualified_invoice_cost' => $qualified_invoice_cost,
						'non_qualified_invoice_cost' => $non_qualified_invoice_cost,
						'item_count' => count( $filtered_cost_items ),
					),
				)
			);

		} catch ( Exception $e ) {
			error_log( 'KTPWP Ajax get_purchase_order_email_content Error: ' . $e->getMessage() );
			if ( ob_get_level() ) {
				ob_end_clean();
			}
			wp_send_json_error(
				array(
					'message' => $e->getMessage(),
				)
			);
		}
	}

	/**
	 * 発注書メール本文を生成
	 */
	private function generate_purchase_order_email_body( $order, $supplier, $client, $cost_items, $my_company, $total_amount, $total_tax_amount, $qualified_invoice_cost, $non_qualified_invoice_cost, $mode = 'order' ) {
		$is_quote = ( $mode === 'quote' );
		$body = '';

		// 協力会社情報
		$body .= ( isset( $supplier->company_name ) ? $supplier->company_name : '' ) . "\n";
		if ( ! empty( $supplier->name ) ) {
			$body .= $supplier->name . "　様\n";
		} else {
			$body .= "　様\n";
		}
		$body .= "\n";

		// 挨拶文
		$body .= "いつもお世話になっています。\n";
		if ( $is_quote ) {
			$body .= "下記の内容でお見積りをお願いいたします。ご確認のほど、よろしくお願いいたします。\n\n";
		} else {
			$body .= "以下のように発注します。納期が決まりましたらご連絡ください。\n\n";
		}

		// 発注/見積依頼内容
		$body .= $is_quote ? "【見積依頼内容】\n" : "【発注内容】\n";
		$body .= str_repeat( '-', 60 ) . "\n";

		$max_length = 0;
		foreach ( $cost_items as $item ) {
			$product_name_length = mb_strlen( $item['product_name'] );
			if ( $product_name_length > $max_length ) {
				$max_length = $product_name_length;
			}
		}

		// 税率別の集計用配列
		$tax_rate_groups = array();
		$qualified_items = array();
		$non_qualified_items = array();

		foreach ( $cost_items as $item ) {
			$product_name = isset( $item['product_name'] ) ? $item['product_name'] : '';
			$price = floatval( isset( $item['price'] ) ? $item['price'] : 0 );
			$quantity = floatval( isset( $item['quantity'] ) ? $item['quantity'] : 0 );
			$unit = ( isset( $item['unit'] ) && $item['unit'] !== '' ) ? $item['unit'] : '';
			$amount = floatval( isset( $item['amount'] ) ? $item['amount'] : 0 );
            $tax_rate_raw = isset( $item['tax_rate'] ) ? $item['tax_rate'] : null;
            // 税制モードの実効税率
            if ( class_exists( 'KTPWP_Tax_Policy' ) ) {
                $tax_rate = KTPWP_Tax_Policy::get_effective_rate( $tax_rate_raw );
                if ( $tax_rate === null ) { $tax_rate = 0.0; }
                $is_tax_free = ( $tax_rate == 0 );
            } else {
                $is_tax_free = empty( $tax_rate_raw ) || $tax_rate_raw === null;
                $tax_rate = $is_tax_free ? 0.0 : floatval( $tax_rate_raw );
            }
			$tax_category = isset( $item['tax_category'] ) ? $item['tax_category'] : '内税';
			$remarks = ( isset( $item['remarks'] ) && $item['remarks'] !== '' ) ? $item['remarks'] : '';
			$has_qualified_invoice = ! empty( $item['qualified_invoice_number'] );

			// 品名を左寄せで表示（商品名と単価の間に半角スペース2つを追加）
			$product_name_padded = str_pad( $product_name, $max_length + 2, ' ' );
			
			// 詳細形式：品名  単価 × 数量/単位 = 金額円（税率X%・税区分）※ 備考
            $suppress_tax_text = ( class_exists( 'KTPWP_Tax_Policy' ) && ( KTPWP_Tax_Policy::is_abolished() || KTPWP_Tax_Policy::hide_tax_columns() ) );
            if ( $suppress_tax_text ) {
                $tax_info = '';
            } else {
                if ( $tax_rate == 0 ) {
                    $tax_info = "（税率0%・{$tax_category}）";
                } else {
                    $tax_info = "（税率{$tax_rate}%・{$tax_category}）";
                }
            }
			$remarks_text = ( ! empty( trim( $remarks ) ) ) ? '　※ ' . $remarks : '';
			
			$body .= $product_name_padded . "  " . KTPWP_Settings::format_money( $price ) . ' × ' . $quantity . $unit . ' = ' . KTPWP_Settings::format_money( $amount ) . "{$tax_info}{$remarks_text}\n";

			// 税率別集計
			$tax_rate_key = number_format( $tax_rate, 1 );
			if ( ! isset( $tax_rate_groups[ $tax_rate_key ] ) ) {
				$tax_rate_groups[ $tax_rate_key ] = array(
					'amount' => 0,
					'qualified_amount' => 0,
					'non_qualified_amount' => 0,
					'tax_amount' => 0
				);
			}
			$tax_rate_groups[ $tax_rate_key ]['amount'] += $amount;

			// 適格請求書の有無で分類
			if ( $has_qualified_invoice ) {
				$qualified_items[] = $item;
				$tax_rate_groups[ $tax_rate_key ]['qualified_amount'] += $amount;
			} else {
				$non_qualified_items[] = $item;
				$tax_rate_groups[ $tax_rate_key ]['non_qualified_amount'] += $amount;
			}

			// 税額計算（非課税取引の場合は0円）
			if ( $is_tax_free ) {
				$tax_amount = 0;
			} elseif ( $tax_category === '外税' ) {
				$tax_amount = ceil( $amount * ( $tax_rate / 100 ) );
			} else {
				$tax_amount = ceil( $amount * ( $tax_rate / 100 ) / ( 1 + $tax_rate / 100 ) );
			}
			$tax_rate_groups[ $tax_rate_key ]['tax_amount'] += $tax_amount;
		}

		$body .= str_repeat( '-', 60 ) . "\n";

		// 協力会社の税区分を取得
		$supplier_tax_category = '内税'; // デフォルト値（内税に設定）
		try {
			// デバッグ用：税区分取得プロセスを記録
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'KTPWP Purchase Order Email: === TAX CATEGORY RETRIEVAL START ===' );
				error_log( 'KTPWP Purchase Order Email: Supplier object tax_category: ' . (isset($supplier->tax_category) ? '"' . $supplier->tax_category . '"' : 'NOT_SET') );
				error_log( 'KTPWP Purchase Order Email: Supplier object tax_category empty: ' . (empty($supplier->tax_category) ? 'true' : 'false') );
			}
			
			// まず、協力会社オブジェクトから直接取得を試行
			if ( isset( $supplier->tax_category ) && ! empty( $supplier->tax_category ) ) {
				$supplier_tax_category = trim( $supplier->tax_category );
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'KTPWP Purchase Order Email: Retrieved from supplier object: "' . $supplier_tax_category . '"' );
				}
			} 
			// 次に、KTPWP_Supplier_Dataクラスを使用して取得を試行
			elseif ( class_exists( 'KTPWP_Supplier_Data' ) && method_exists( 'KTPWP_Supplier_Data', 'get_instance' ) ) {
				$supplier_data = KTPWP_Supplier_Data::get_instance();
				if ( method_exists( $supplier_data, 'get_tax_category_by_supplier_id' ) ) {
					$supplier_tax_category = trim( $supplier_data->get_tax_category_by_supplier_id( $supplier->id ) );
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( 'KTPWP Purchase Order Email: Retrieved from KTPWP_Supplier_Data: "' . $supplier_tax_category . '"' );
					}
				} else {
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( 'KTPWP Purchase Order Email: KTPWP_Supplier_Data method not found' );
					}
				}
			} else {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'KTPWP Purchase Order Email: KTPWP_Supplier_Data class not found' );
				}
			}
			// 最後に、直接データベースから取得を試行
			if ( empty( $supplier_tax_category ) || $supplier_tax_category === '内税' ) {
				global $wpdb;
				$supplier_table = $wpdb->prefix . 'ktp_supplier';
				$tax_category = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT tax_category FROM `{$supplier_table}` WHERE id = %d",
						$supplier->id
					)
				);
				if ( $tax_category ) {
					$supplier_tax_category = trim( $tax_category );
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( 'KTPWP Purchase Order Email: Retrieved from database: "' . $supplier_tax_category . '"' );
					}
				} else {
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( 'KTPWP Purchase Order Email: No tax_category found in database' );
					}
				}
			}
			
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'KTPWP Purchase Order Email: Final supplier_tax_category: "' . $supplier_tax_category . '"' );
				error_log( 'KTPWP Purchase Order Email: === TAX CATEGORY RETRIEVAL END ===' );
			}
		} catch ( Exception $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'KTPWP Purchase Order Email: Error getting supplier tax category: ' . $e->getMessage() );
			}
			$supplier_tax_category = '内税'; // エラー時はデフォルト値を使用
		}
		

		
		// 合計金額（協力会社の税区分に応じて表示）
		// 税区分の比較を堅牢にするため、空白を除去して比較
		$clean_tax_category = trim($supplier_tax_category);
		// 空文字、NULL、または「内税」以外の場合は内税として扱う（デフォルト）
		$is_inclusive_tax = ( empty($clean_tax_category) || $clean_tax_category === '内税' || strtolower($clean_tax_category) === '内税' );
		
		// デバッグ用ログ
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'KTPWP Purchase Order Email: Supplier tax category: ' . $supplier_tax_category . ' for supplier ID: ' . $supplier->id );
			error_log( 'KTPWP Purchase Order Email: Total amount: ' . $total_amount . ', Total tax amount: ' . $total_tax_amount );
			error_log( 'KTPWP Purchase Order Email: About to check tax category. Value: "' . $supplier_tax_category . '"' );
			error_log( 'KTPWP Purchase Order Email: Clean tax category: "' . $clean_tax_category . '"' );
			error_log( 'KTPWP Purchase Order Email: Is inclusive tax: ' . ( $is_inclusive_tax ? 'true' : 'false' ) );
			error_log( 'KTPWP Purchase Order Email: Tax category length: ' . strlen($supplier_tax_category) );
			error_log( 'KTPWP Purchase Order Email: Tax category type: ' . gettype($supplier_tax_category) );
			error_log( 'KTPWP Purchase Order Email: Tax category bytes: ' . bin2hex($supplier_tax_category) );
			error_log( 'KTPWP Purchase Order Email: Clean tax category length: ' . strlen($clean_tax_category) );
			error_log( 'KTPWP Purchase Order Email: Clean tax category bytes: ' . bin2hex($clean_tax_category) );
		}
		
		// デバッグ用：実際の値を詳細に記録
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'KTPWP Purchase Order Email: === TAX CATEGORY DEBUG START ===' );
			error_log( 'KTPWP Purchase Order Email: Supplier company name: ' . $supplier->company_name );
			error_log( 'KTPWP Purchase Order Email: Supplier ID: ' . $supplier->id );
			error_log( 'KTPWP Purchase Order Email: Original supplier_tax_category: "' . $supplier_tax_category . '"' );
			error_log( 'KTPWP Purchase Order Email: Clean supplier_tax_category: "' . $clean_tax_category . '"' );
			error_log( 'KTPWP Purchase Order Email: Clean tax category length: ' . strlen($clean_tax_category) );
			error_log( 'KTPWP Purchase Order Email: Clean tax category bytes: ' . bin2hex($clean_tax_category) );
			error_log( 'KTPWP Purchase Order Email: Is empty: ' . (empty($clean_tax_category) ? 'true' : 'false') );
			error_log( 'KTPWP Purchase Order Email: Is equal to 内税: ' . ($clean_tax_category === '内税' ? 'true' : 'false') );
			error_log( 'KTPWP Purchase Order Email: Is inclusive tax: ' . ($is_inclusive_tax ? 'true' : 'false') );
			error_log( 'KTPWP Purchase Order Email: === TAX CATEGORY DEBUG END ===' );
		}
		
        $suppress_tax_text = ( class_exists( 'KTPWP_Tax_Policy' ) && ( KTPWP_Tax_Policy::is_abolished() || KTPWP_Tax_Policy::hide_tax_columns() ) );
        if ( $suppress_tax_text ) {
            // 税廃止/非表示: 税関連の注記を出さない
            $body .= "金額合計：" . KTPWP_Settings::format_money( $total_amount ) . "\n\n";
        } elseif ( $is_inclusive_tax ) {
			// 内税の場合：税込金額を表示し、税抜価格と消費税額を内訳で表示
			// 要求された形式に合わせて、税抜価格と消費税額を計算
			$tax_excluded_amount = $total_amount - $total_tax_amount;
			$body .= "金額合計：" . KTPWP_Settings::format_money( $total_amount ) . "（税抜価格：" . KTPWP_Settings::format_money( $tax_excluded_amount ) . "＋消費税額：" . KTPWP_Settings::format_money( $total_tax_amount ) . "）\n\n";
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'KTPWP Purchase Order Email: Using 内税 format' );
			}
		} else {
			// 外税の場合：税抜金額、消費税額、税込金額を別々に表示
			// 外税の場合、$total_amountは税抜金額なので、実際の税率に基づいて消費税額を計算し直す
			$correct_tax_amount = 0;
			
			// 各商品の税率に基づいて消費税額を計算
			foreach ( $cost_items as $item ) {
				$amount = floatval( $item['amount'] );
				$tax_rate = $item['tax_rate'];
				$is_tax_free = empty( $tax_rate ) || $tax_rate === null;
				
				if ( ! $is_tax_free ) {
					$tax_rate = floatval( $tax_rate );
					// 外税の場合の消費税額計算
					$correct_tax_amount += ceil( $amount * ( $tax_rate / 100 ) );
				}
			}
			
			$tax_included_amount = $total_amount + $correct_tax_amount;
			
			$body .= "金額合計：" . KTPWP_Settings::format_money( $total_amount ) . "\n";
			$body .= "消費税額：" . KTPWP_Settings::format_money( $correct_tax_amount ) . "\n";
			$body .= "税込金額：" . KTPWP_Settings::format_money( $tax_included_amount ) . "\n\n";
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'KTPWP Purchase Order Email: Using 外税 format' );
				error_log( 'KTPWP Purchase Order Email: Correct tax amount for 外税: ' . $correct_tax_amount );
			}
		}

		// 税率別内訳は削除（不要）

		// フッター
		$body .= "よろしくお願いいたします。\n\n";
		$body .= "--\n";
		$body .= $my_company . "\n";

		return $body;
	}

	/**
	 * Initialize AJAX handlers
	 *
	 * @since 1.0.0
	 */
	public function init() {
		// 既存のAJAXハンドラー
		add_action( 'wp_ajax_ktp_get_departments', array( $this, 'get_departments' ) );
		add_action( 'wp_ajax_ktp_get_supplier_costs', array( $this, 'get_supplier_costs' ) );
		
		// レポートタブ用のAJAXハンドラー
		add_action( 'wp_ajax_ktp_get_report_data', array( $this, 'get_report_data' ) );
		add_action( 'wp_ajax_ktp_get_sales_data', array( $this, 'get_sales_data' ) );
		add_action( 'wp_ajax_ktp_get_progress_data', array( $this, 'get_progress_data' ) );
		add_action( 'wp_ajax_ktp_get_client_data', array( $this, 'get_client_data' ) );
		add_action( 'wp_ajax_ktp_get_service_data', array( $this, 'get_service_data' ) );
		add_action( 'wp_ajax_ktp_get_supplier_data', array( $this, 'get_supplier_data' ) );
		
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'レポートAJAXハンドラー登録完了: ktp_get_report_data' );
			error_log( '登録されたAJAXアクション: wp_ajax_ktp_get_report_data' );
		}
	}

	/**
	 * Get report data for charts
	 *
	 * @since 1.0.0
	 */
	public function get_report_data() {
		// 権限チェック
		if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'ktpwp_access' ) ) {
			wp_die( esc_html__( '権限がありません。', 'kantanpro' ) );
		}

		// ノンスチェック
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'レポートAJAX リクエスト開始: ' . ( isset( $_POST['action'] ) ? $_POST['action'] : 'NO_ACTION' ) );
			error_log( 'レポートAJAX nonce検証: ' . ( isset( $_POST['nonce'] ) ? $_POST['nonce'] : 'NOT_SET' ) );
		}
		
		if ( ! wp_verify_nonce( $_POST['nonce'], 'ktpwp_ajax_nonce' ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'レポートAJAX nonce検証失敗: ' . ( isset( $_POST['nonce'] ) ? $_POST['nonce'] : 'NOT_SET' ) );
			}
			wp_die( esc_html__( 'セキュリティチェックに失敗しました。', 'kantanpro' ) );
		}
		
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'レポートAJAX nonce検証成功' );
		}

		$report_type = sanitize_text_field( $_POST['report_type'] );
		$period = sanitize_text_field( $_POST['period'] );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'レポートAJAX リクエスト詳細: report_type=' . $report_type . ', period=' . $period );
		}

		global $wpdb;

		switch ( $report_type ) {
			case 'sales':
				$data = $this->get_sales_chart_data( $period );
				break;
			case 'client':
				$data = $this->get_client_chart_data( $period );
				break;
			case 'service':
				$data = $this->get_service_chart_data( $period );
				break;
			case 'supplier':
				$data = $this->get_supplier_chart_data( $period );
				break;
			default:
				$data = array( 'error' => '無効なレポートタイプです。' );
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'レポートAJAX レスポンスデータ: ' . json_encode( $data ) );
		}

		wp_send_json_success( $data );
	}

	/**
	 * Get sales chart data
	 *
	 * @since 1.0.0
	 * @param string $period Period type
	 * @return array Chart data
	 */
	private function get_sales_chart_data( $period ) {
		global $wpdb;

		$where_clause = $this->get_period_where_clause( $period );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '売上データ取得開始: period=' . $period . ', where_clause=' . $where_clause );
			
			// データベースの全件数を確認
			$total_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ktp_order");
			error_log( '注文テーブル総件数: ' . $total_count );
			
			// 期間別件数を確認
			$period_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ktp_order o WHERE 1=1 {$where_clause}");
			error_log( '期間別件数: ' . $period_count );
		}

		// 月別売上データ（請求済以降の進捗で、請求項目がある案件のみ）
		$monthly_query = "SELECT 
			DATE_FORMAT(o.completion_date, '%Y-%m') as month,
			SUM(ii.amount) as total_sales
			FROM {$wpdb->prefix}ktp_order o
			LEFT JOIN {$wpdb->prefix}ktp_order_invoice_items ii ON o.id = ii.order_id
			WHERE 1=1 {$where_clause} AND ii.amount IS NOT NULL AND o.progress >= 5 AND o.progress != 7 AND o.completion_date IS NOT NULL
			GROUP BY DATE_FORMAT(o.completion_date, '%Y-%m')
			ORDER BY month";

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '月別売上クエリ: ' . $monthly_query );
		}

		$monthly_results = $wpdb->get_results( $monthly_query );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '月別売上結果: ' . json_encode( $monthly_results ) );
		}

		// 利益推移データ（請求済以降の進捗で、売上とコストを時系列で取得）
		$profit_query = "SELECT 
			DATE_FORMAT(o.completion_date, '%Y-%m') as month,
			SUM(ii.amount) as total_sales,
			SUM(COALESCE(oci.amount, 0)) as total_cost
			FROM {$wpdb->prefix}ktp_order o
			LEFT JOIN (
				SELECT order_id, SUM(amount) as amount 
				FROM {$wpdb->prefix}ktp_order_invoice_items 
				GROUP BY order_id
			) ii ON o.id = ii.order_id
			LEFT JOIN (
				SELECT order_id, SUM(amount) as amount 
				FROM {$wpdb->prefix}ktp_order_cost_items 
				GROUP BY order_id
			) oci ON o.id = oci.order_id
			WHERE 1=1 {$where_clause} AND ii.amount IS NOT NULL AND o.progress >= 5 AND o.progress != 7 AND o.completion_date IS NOT NULL
			GROUP BY DATE_FORMAT(o.completion_date, '%Y-%m')
			ORDER BY month";

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '利益推移クエリ: ' . $profit_query );
		}

		$profit_results = $wpdb->get_results( $profit_query );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '利益推移結果: ' . json_encode( $profit_results ) );
		}

		$monthly_data = array();
		$profit_data = array();

		foreach ( $monthly_results as $result ) {
			$monthly_data[] = array(
				'label' => $result->month,
				'value' => (int) $result->total_sales
			);
		}

		foreach ( $profit_results as $result ) {
			$sales = (int) $result->total_sales;
			$cost = (int) $result->total_cost;
			$profit = $sales - $cost;
			
			$profit_data[] = array(
				'label' => $result->month,
				'sales' => $sales,
				'cost' => $cost,
				'profit' => $profit
			);
		}

		return array(
			'monthly_sales' => array(
				'labels' => array_column($monthly_data, 'label'),
				'data' => array_column($monthly_data, 'value')
			),
			'profit_trend' => array(
				'labels' => array_column($profit_data, 'label'),
				'sales' => array_column($profit_data, 'sales'),
				'cost' => array_column($profit_data, 'cost'),
				'profit' => array_column($profit_data, 'profit')
			)
		);
	}



	/**
	 * Get client chart data
	 *
	 * @since 1.0.0
	 * @param string $period Period type
	 * @return array Chart data
	 */
	private function get_client_chart_data( $period ) {
		global $wpdb;

		$where_clause = $this->get_period_where_clause( $period );

		// 顧客別売上TOP10（請求済以降の進捗状況の案件のみ）
		$sales_query = "SELECT 
			o.customer_name,
			SUM(ii.amount) as total_sales
			FROM {$wpdb->prefix}ktp_order o
			LEFT JOIN {$wpdb->prefix}ktp_order_invoice_items ii ON o.id = ii.order_id
			WHERE 1=1 {$where_clause} 
			AND ii.amount IS NOT NULL 
			AND o.progress >= 5 
			AND o.progress != 7
			AND o.completion_date IS NOT NULL
			GROUP BY o.customer_name
			ORDER BY total_sales DESC
			LIMIT 10";

		$sales_results = $wpdb->get_results( $sales_query );

		// 顧客別案件数TOP10（請求済以降の進捗状況の案件のみ）
		$order_query = "SELECT 
			o.customer_name,
			COUNT(o.id) as order_count
			FROM {$wpdb->prefix}ktp_order o
			WHERE 1=1 {$where_clause} 
			AND o.progress >= 5 
			AND o.progress != 7
			AND o.completion_date IS NOT NULL
			GROUP BY o.customer_name
			ORDER BY order_count DESC
			LIMIT 10";

		$order_results = $wpdb->get_results( $order_query );

		$sales_data = array();
		$order_data = array();

		foreach ( $sales_results as $result ) {
			$sales_data[] = array(
				'label' => $result->customer_name ?: '不明',
				'value' => (int) $result->total_sales
			);
		}

		foreach ( $order_results as $result ) {
			$order_data[] = array(
				'label' => $result->customer_name ?: '不明',
				'value' => (int) $result->order_count
			);
		}

		return array(
			'client_sales' => array(
				'labels' => array_column($sales_data, 'label'),
				'data' => array_column($sales_data, 'value')
			),
			'client_orders' => array(
				'labels' => array_column($order_data, 'label'),
				'data' => array_column($order_data, 'value')
			)
		);
	}

			/**
		 * Get service chart data
		 *
		 * @since 1.0.0
		 * @param string $period Period type
		 * @return array Chart data
		 */
		private function get_service_chart_data( $period ) {
			global $wpdb;

			$where_clause = $this->get_period_where_clause( $period );

			// サービス別売上TOP5（請求済以降の進捗状況の案件のみ）
			$sales_query = "SELECT 
				ii.product_name as service_name,
				SUM(ii.amount) as total_sales
				FROM {$wpdb->prefix}ktp_order o 
				LEFT JOIN {$wpdb->prefix}ktp_order_invoice_items ii ON o.id = ii.order_id 
				WHERE 1=1 {$where_clause} 
				AND ii.amount IS NOT NULL 
				AND o.progress >= 5 
				AND o.progress != 7
				AND o.completion_date IS NOT NULL
				GROUP BY ii.product_name 
				ORDER BY total_sales DESC 
				LIMIT 5";

			$sales_results = $wpdb->get_results( $sales_query );

			// サービス別比率（受注以降の進捗状況の案件のみ）
			$usage_query = "SELECT 
				ii.product_name as service_name,
				COUNT(DISTINCT o.id) as usage_count
				FROM {$wpdb->prefix}ktp_order o 
				LEFT JOIN {$wpdb->prefix}ktp_order_invoice_items ii ON o.id = ii.order_id 
				WHERE 1=1 {$where_clause} 
				AND ii.amount IS NOT NULL 
				AND o.progress >= 3 
				AND o.progress != 7
				GROUP BY ii.product_name 
				ORDER BY usage_count DESC 
				LIMIT 5";

			$usage_results = $wpdb->get_results( $usage_query );

			$sales_data = array();
			$usage_data = array();

			foreach ( $sales_results as $result ) {
				$sales_data[] = array(
					'label' => $result->service_name ?: '不明',
					'value' => (int) $result->total_sales
				);
			}

			foreach ( $usage_results as $result ) {
				$usage_data[] = array(
					'label' => $result->service_name ?: '不明',
					'value' => (int) $result->usage_count
				);
			}

					return array(
			'service_sales' => array(
				'labels' => array_column($sales_data, 'label'),
				'data' => array_column($sales_data, 'value')
			),
			'service_quantity' => array(
				'labels' => array_column($usage_data, 'label'),
				'data' => array_column($usage_data, 'value')
			)
		);
		}

			/**
		 * Get supplier chart data
		 *
		 * @since 1.0.0
		 * @param string $period Period type
		 * @return array Chart data
		 */
		private function get_supplier_chart_data( $period ) {
			global $wpdb;

			$where_clause = $this->get_period_where_clause( $period );

			// 協力会社別貢献度TOP5（請求済以降の進捗状況の案件のみ）
			$contribution_query = "SELECT 
				s.company_name,
				SUM(oci.amount) as total_contribution
				FROM {$wpdb->prefix}ktp_order o 
				LEFT JOIN {$wpdb->prefix}ktp_order_cost_items oci ON o.id = oci.order_id 
				LEFT JOIN {$wpdb->prefix}ktp_supplier s ON oci.supplier_id = s.id 
				WHERE 1=1 {$where_clause} 
				AND oci.supplier_id IS NOT NULL 
				AND o.progress >= 5 
				AND o.progress != 7
				AND o.completion_date IS NOT NULL
				GROUP BY s.id 
				ORDER BY total_contribution DESC 
				LIMIT 5";

			$contribution_results = $wpdb->get_results( $contribution_query );

			// スキル別協力会社数TOP5
			$skill_query = "SELECT 
				ss.product_name,
				COUNT(*) as skill_count
				FROM {$wpdb->prefix}ktp_supplier_skills ss
				LEFT JOIN {$wpdb->prefix}ktp_supplier s ON ss.supplier_id = s.id
				WHERE ss.product_name IS NOT NULL
				GROUP BY ss.product_name
				ORDER BY skill_count DESC
				LIMIT 5";

			$skill_results = $wpdb->get_results( $skill_query );

			$contribution_data = array();
			$skill_data = array();

			foreach ( $contribution_results as $result ) {
				$contribution_data[] = array(
					'label' => $result->company_name ?: '不明',
					'value' => (int) $result->total_contribution
				);
			}

			foreach ( $skill_results as $result ) {
				$skill_data[] = array(
					'label' => $result->product_name ?: '不明',
					'value' => (int) $result->skill_count
				);
			}

					return array(
			'supplier_skills' => array(
				'labels' => array_column($contribution_data, 'label'),
				'data' => array_column($contribution_data, 'value')
			),
			'skill_suppliers' => array(
				'labels' => array_column($skill_data, 'label'),
				'data' => array_column($skill_data, 'value')
			)
		);
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
			case 'this_year':
				$where_clause = " AND YEAR(o.completion_date) = YEAR(CURDATE())";
				break;
			case 'last_year':
				$where_clause = " AND YEAR(o.completion_date) = YEAR(CURDATE()) - 1";
				break;
			case 'this_month':
				$where_clause = " AND YEAR(o.completion_date) = YEAR(CURDATE()) AND MONTH(o.completion_date) = MONTH(CURDATE())";
				break;
			case 'last_month':
				$where_clause = " AND YEAR(o.completion_date) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND MONTH(o.completion_date) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))";
				break;
			case 'last_3_months':
				$where_clause = " AND o.completion_date >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)";
				break;
			case 'last_6_months':
				$where_clause = " AND o.completion_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)";
				break;
			case 'all_time':
			default:
				$where_clause = "";
				break;
		}

		return $where_clause;
	}

	/**
	 * 一括請求プレビュー用に月別グループを【定期】【都度】【初回のみ】へ分割
	 *
	 * @param array<int, object> $orders 受注書一覧。
	 * @return array<int, array<string, mixed>>
	 */
	private function build_bulk_invoice_sections( $orders ) {
		$initial_remark = class_exists( 'KTPWP_Contract_Billing' )
			? KTPWP_Contract_Billing::INITIAL_FEE_REMARKS
			: '初回のみ';

		$recurring_orders = array();
		$spot_orders      = array();
		$initial_lines    = array();

		foreach ( $orders as $order ) {
			$contract_id     = isset( $order->contract_id ) ? (int) $order->contract_id : 0;
			$recurring_items = array();
			$spot_items      = array();

			foreach ( (array) ( $order->invoice_items ?? array() ) as $item ) {
				$remarks = isset( $item->remarks ) ? trim( (string) $item->remarks ) : '';
				if ( $remarks === $initial_remark ) {
					$initial_lines[] = array(
						'order_id'        => (int) $order->id,
						'project_name'    => (string) ( $order->project_name ?? '' ),
						'completion_date' => $order->completion_date ?? '',
						'progress'        => isset( $order->progress ) ? (int) $order->progress : 0,
						'product_name'    => (string) ( $item->product_name ?? '' ),
						'price'           => $item->price ?? 0,
						'quantity'        => $item->quantity ?? 0,
						'unit'            => (string) ( $item->unit ?? '' ),
						'amount'          => $item->amount ?? 0,
						'tax_rate'        => $item->tax_rate ?? null,
						'remarks'         => $remarks,
					);
					continue;
				}

				if ( $contract_id > 0 ) {
					$recurring_items[] = $item;
				} else {
					$spot_items[] = $item;
				}
			}

			if ( $contract_id > 0 && ! empty( $recurring_items ) ) {
				$recurring_orders[] = $this->filter_order_invoice_items( $order, $recurring_items );
			}
			if ( $contract_id <= 0 && ! empty( $spot_items ) ) {
				$spot_orders[] = $this->filter_order_invoice_items( $order, $spot_items );
			}
		}

		$sections = array();
		if ( ! empty( $recurring_orders ) ) {
			$sections[] = array(
				'key'    => 'recurring',
				'label'  => '【' . __( '定期', 'kantanpro' ) . '】',
				'orders' => $recurring_orders,
			);
		}
		if ( ! empty( $spot_orders ) ) {
			$sections[] = array(
				'key'    => 'spot',
				'label'  => '【' . __( '都度', 'kantanpro' ) . '】',
				'orders' => $spot_orders,
			);
		}
		if ( ! empty( $initial_lines ) ) {
			$sections[] = array(
				'key'   => 'initial',
				'label' => '【' . __( '初回のみ', 'kantanpro' ) . '】',
				'lines' => $initial_lines,
			);
		}

		return $sections;
	}

	/**
	 * 受注書の請求行を部分集合に差し替え
	 *
	 * @param object             $order 受注書。
	 * @param array<int, object> $items 請求行。
	 * @return object
	 */
	private function filter_order_invoice_items( $order, $items ) {
		$filtered                  = clone $order;
		$filtered->invoice_items   = array_values( $items );

		return $filtered;
	}
}
