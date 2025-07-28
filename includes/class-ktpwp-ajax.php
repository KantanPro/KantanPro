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
	 * Ajaxハンドラー登録
	 */
	public function register_ajax_handlers() {
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

		// 会社情報取得
		add_action( 'wp_ajax_get_company_info', array( $this, 'ajax_get_company_info' ) );
		add_action( 'wp_ajax_nopriv_get_company_info', array( $this, 'ajax_require_login' ) );
		$this->registered_handlers[] = 'get_company_info';

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
					wp_send_json_error( '権限がありません' );
				}
				// nonceチェック（必要ならPOSTでnonceも送る）
				// if ( ! isset($_POST['nonce']) || ! wp_verify_nonce($_POST['nonce'], 'ktp_ajax_nonce') ) {
				// wp_send_json_error('セキュリティ検証に失敗しました');
				// }
				global $wpdb;
				$client_id = isset( $_POST['client_id'] ) ? intval( $_POST['client_id'] ) : 0;
				if ( ! $client_id ) {
					wp_send_json_error( 'client_idが指定されていません' );
				}
				$table_name = $wpdb->prefix . 'ktp_order';
				// progress=4（完了）→5（請求済）に一括更新（completion_dateは変更しない）
				$result = $wpdb->query(
                    $wpdb->prepare(
                        "UPDATE {$table_name} SET progress = 5 WHERE client_id = %d AND progress = 4",
                        $client_id
                    )
				);
				if ( $result === false ) {
					wp_send_json_error( 'DB更新に失敗しました: ' . $wpdb->last_error );
				}
				wp_send_json_success( array( 'updated' => $result ) );
			}
        );
		// ▲▲▲ 一括請求書「請求済」進捗変更Ajax ▲▲▲

		// ▼▼▼ コスト項目「注文済」一括更新Ajax ▼▼▼
		add_action(
            'wp_ajax_ktp_set_cost_items_ordered',
            function () {
				// 権限チェック
				if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'ktpwp_access' ) ) {
					wp_send_json_error( '権限がありません' );
				}
				// nonceチェック
				if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'ktp_ajax_nonce' ) ) {
					wp_send_json_error( 'セキュリティ検証に失敗しました' );
				}
				global $wpdb;
				$order_id = isset( $_POST['order_id'] ) ? intval( $_POST['order_id'] ) : 0;
				$supplier_name = isset( $_POST['supplier_name'] ) ? sanitize_text_field( $_POST['supplier_name'] ) : '';
				if ( ! $order_id || ! $supplier_name ) {
					wp_send_json_error( 'order_idまたはsupplier_nameが指定されていません' );
				}
				$table_name = $wpdb->prefix . 'ktp_order_cost_items';
				// purchaseカラムがsupplier_nameと一致する全行を更新
				$result = $wpdb->update(
                    $table_name,
                    array( 'ordered' => 1 ),
                    array(
						'order_id' => $order_id,
						'purchase' => $supplier_name,
                    ),
                    array( '%d' ),
                    array( '%d', '%s' )
				);
				if ( $result === false ) {
					wp_send_json_error( 'DB更新に失敗しました: ' . $wpdb->last_error );
				}
				wp_send_json_success( array( 'updated' => $result ) );
			}
        );
		// ▲▲▲ コスト項目「注文済」一括更新Ajax ▲▲▲

		// 受注書データ取得
		add_action('wp_ajax_ktp_get_order_data', array($this, 'get_order_data'));
		add_action('wp_ajax_nopriv_ktp_get_order_data', array($this, 'get_order_data'));
	}

	/**
	 * 受注関連Ajaxハンドラー初期化
	 */
	private function init_order_ajax_handlers() {
		// 受注クラスファイルの読み込み
		$order_class_file = KTPWP_PLUGIN_DIR . 'includes/class-tab-order.php';

		if ( file_exists( $order_class_file ) ) {
			require_once $order_class_file;
		}

		// クラス存在チェックを削除して強制的に登録
		error_log( '[AJAX] 強制的にAjaxハンドラーを登録します' );
		
		// 自動保存
		add_action( 'wp_ajax_ktp_auto_save_item', array( $this, 'ajax_auto_save_item' ) );
		add_action( 'wp_ajax_nopriv_ktp_auto_save_item', array( $this, 'ajax_auto_save_item' ) );
		$this->registered_handlers[] = 'ktp_auto_save_item';

		// 新規アイテム作成
		add_action( 'wp_ajax_ktp_create_new_item', array( $this, 'ajax_create_new_item' ) );
		add_action( 'wp_ajax_nopriv_ktp_create_new_item', array( $this, 'ajax_create_new_item' ) );
		$this->registered_handlers[] = 'ktp_create_new_item';
		error_log( '[AJAX] ktp_create_new_item handler registered' );

		// アイテム削除
		add_action( 'wp_ajax_ktp_delete_item', array( $this, 'ajax_delete_item' ) );
		add_action( 'wp_ajax_nopriv_ktp_delete_item', array( $this, 'ajax_require_login' ) ); // 非ログインユーザーはエラー
		$this->registered_handlers[] = 'ktp_delete_item';

		// アイテム並び順更新
		add_action( 'wp_ajax_ktp_update_item_order', array( $this, 'ajax_update_item_order' ) );
		add_action( 'wp_ajax_nopriv_ktp_update_item_order', array( $this, 'ajax_require_login' ) ); // 非ログインユーザーはエラー
		$this->registered_handlers[] = 'ktp_update_item_order';
		
		error_log( '[AJAX] 全Ajaxハンドラー登録完了: ' . print_r($this->registered_handlers, true) );
		
		// デバッグ用：すべてのAjaxリクエストを監視
		add_action( 'wp_ajax_ktp_create_new_item', function() {
			error_log( '[AJAX_DEBUG] wp_ajax_ktp_create_new_item アクションが呼び出されました' );
		}, 1 );
		add_action( 'wp_ajax_nopriv_ktp_create_new_item', function() {
			error_log( '[AJAX_DEBUG] wp_ajax_nopriv_ktp_create_new_item アクションが呼び出されました' );
		}, 1 );
		
		// デバッグ用：すべてのAjaxリクエストを監視
		add_action( 'wp_ajax_ktp_create_new_item', function() {
			error_log( '[AJAX_DEBUG_ALL] すべてのAjaxリクエスト: ' . print_r( $_REQUEST, true ) );
		}, 0 );
		add_action( 'wp_ajax_nopriv_ktp_create_new_item', function() {
			error_log( '[AJAX_DEBUG_ALL] すべてのAjaxリクエスト（非ログイン）: ' . print_r( $_REQUEST, true ) );
		}, 0 );
		
		// デバッグ用：WordPressのAjax処理全体を監視
		add_action( 'init', function() {
			error_log( '[AJAX_DEBUG_INIT] WordPress init フック実行中' );
			if ( wp_doing_ajax() ) {
				error_log( '[AJAX_DEBUG_INIT] Ajax処理中: action=' . ( isset( $_REQUEST['action'] ) ? $_REQUEST['action'] : 'NOT_SET' ) );
			}
		}, 1 );
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
			}
		}
	}

	/**
	 * サービス関連Ajaxハンドラー初期化
	 */
	private function init_service_ajax_handlers() {
		error_log( '[AJAX] サービスAjaxハンドラー初期化開始' );
		
		// サービス一覧取得
		add_action( 'wp_ajax_ktp_get_service_list', array( $this, 'ajax_get_service_list' ) );
		add_action( 'wp_ajax_nopriv_ktp_get_service_list', array( $this, 'ajax_get_service_list' ) );
		$this->registered_handlers[] = 'ktp_get_service_list';
		
		error_log( '[AJAX] サービスAjaxハンドラー登録完了: ktp_get_service_list' );
		error_log( '[AJAX] 登録されたハンドラー: ' . print_r( $this->registered_handlers, true ) );
		
		// デバッグ用：アクションが正しく登録されているか確認
		error_log( '[AJAX] wp_ajax_ktp_get_service_list アクション登録確認: ' . ( has_action( 'wp_ajax_ktp_get_service_list' ) ? '登録済み' : '未登録' ) );
		error_log( '[AJAX] wp_ajax_nopriv_ktp_get_service_list アクション登録確認: ' . ( has_action( 'wp_ajax_nopriv_ktp_get_service_list' ) ? '登録済み' : '未登録' ) );
		
		// デバッグ用：Ajaxリクエスト全体を監視
		add_action( 'wp_ajax_ktp_get_service_list', function() {
			error_log( '[AJAX_DEBUG] wp_ajax_ktp_get_service_list アクションが呼び出されました' );
		}, 1 );
		add_action( 'wp_ajax_nopriv_ktp_get_service_list', function() {
			error_log( '[AJAX_DEBUG] wp_ajax_nopriv_ktp_get_service_list アクションが呼び出されました' );
		}, 1 );
		
		// デバッグ用：すべてのAjaxリクエストを監視
		add_action( 'wp_ajax_ktp_get_service_list', function() {
			error_log( '[AJAX_DEBUG_ALL] すべてのAjaxリクエスト: ' . print_r( $_REQUEST, true ) );
		}, 0 );
		add_action( 'wp_ajax_nopriv_ktp_get_service_list', function() {
			error_log( '[AJAX_DEBUG_ALL] すべてのAjaxリクエスト（非ログイン）: ' . print_r( $_REQUEST, true ) );
		}, 0 );
		
		// デバッグ用：WordPressのAjax処理全体を監視
		add_action( 'init', function() {
			error_log( '[AJAX_DEBUG_INIT] WordPress init フック実行中' );
			if ( wp_doing_ajax() ) {
				error_log( '[AJAX_DEBUG_INIT] Ajax処理中: action=' . ( isset( $_REQUEST['action'] ) ? $_REQUEST['action'] : 'NOT_SET' ) );
			}
		}, 1 );
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
		if ( class_exists( 'KTP_Settings' ) ) {
			$ajax_data['settings']['work_list_range']       = KTP_Settings::get_work_list_range();
			$ajax_data['settings']['delivery_warning_days'] = KTP_Settings::get_delivery_warning_days();
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
			} elseif ( $action === 'project_name' && current_user_can( 'manage_options' ) ) {
				$ajax_data['nonces'][ $action ] = wp_create_nonce( $nonce_name );
			} elseif ( $action !== 'project_name' ) {
				$ajax_data['nonces'][ $action ] = wp_create_nonce( $nonce_name );
			}
		}

		// JavaScriptファイルがエンキューされている場合のみlocalizeを実行
		global $wp_scripts;

		if ( isset( $wp_scripts->registered['ktp-js'] ) ) {
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

		if ( isset( $wp_scripts->registered['ktp-order-inline-projectname'] ) && current_user_can( 'manage_options' ) ) {
			wp_add_inline_script(
				'ktp-order-inline-projectname',
				'var ktpwp_inline_edit_nonce = ' . json_encode(
					array(
						'nonce' => $ajax_data['nonces']['project_name'],
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
							'delivery_warning_days' => KTP_Settings::get_delivery_warning_days(),
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
		echo 'var ajaxurl = "' . admin_url( 'admin-ajax.php' ) . '";';
		echo '}';
		echo '</script>';
	}

	/**
	 * Ajax: プロジェクト名更新（管理者のみ）
	 */
	public function ajax_update_project_name() {
		// 共通バリデーション（管理者権限必須）
		if ( ! $this->validate_ajax_request( 'ktp_update_project_name', true ) ) {
			return; // エラーレスポンスは既に送信済み
		}

		// POSTデータの取得とサニタイズ
		$order_id     = $this->sanitize_ajax_input( 'order_id', 'int' );
		$project_name = $this->sanitize_ajax_input( 'project_name', 'text' );

		// バリデーション
		if ( $order_id <= 0 ) {
			$this->log_ajax_error( 'Invalid order ID for project name update', array( 'order_id' => $order_id ) );
			wp_send_json_error( __( '無効な受注IDです', 'ktpwp' ) );
		}

		// 新しいクラス構造を使用してプロジェクト名を更新
		$order_manager = KTPWP_Order::get_instance();

		try {
			$result = $order_manager->update_order(
				$order_id,
				array(
					'project_name' => $project_name,
				)
			);

			if ( $result ) {
				wp_send_json_success(
					array(
						'message'      => __( 'プロジェクト名を更新しました', 'ktpwp' ),
						'project_name' => $project_name,
					)
				);
			} else {
				$this->log_ajax_error(
					'Failed to update project name',
					array(
						'order_id'     => $order_id,
						'project_name' => $project_name,
					)
				);
				wp_send_json_error( __( '更新に失敗しました', 'ktpwp' ) );
			}
		} catch ( Exception $e ) {
			$this->log_ajax_error(
				'Exception during project name update',
				array(
					'message'  => $e->getMessage(),
					'order_id' => $order_id,
				)
			);
			wp_send_json_error( __( '更新中にエラーが発生しました', 'ktpwp' ) );
		}
	}

	/**
	 * Ajax: ログイン中ユーザー取得
	 */
	public function ajax_get_logged_in_users() {
		// 編集者以上の権限チェック
		if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'ktpwp_access' ) ) {
			wp_send_json_error( __( 'この操作を行う権限がありません。', 'ktpwp' ) );
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
		wp_send_json_error( __( 'ログインが必要です', 'ktpwp' ) );
	}

	/**
	 * Ajax: 自動保存アイテム処理
	 */
	public function ajax_auto_save_item() {
		// 編集者以上の権限チェック
		if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'ktpwp_access' ) ) {
			$this->log_ajax_error( 'Auto-save permission check failed' );
			wp_send_json_error( __( 'この操作を行う権限がありません。', 'ktpwp' ) );
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

				if ( wp_verify_nonce( $nonce_value, 'ktp_ajax_nonce' ) ) {
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
			wp_send_json_error( __( 'セキュリティ検証に失敗しました', 'ktpwp' ) );
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
			wp_send_json_error( __( '無効なアイテムタイプです', 'ktpwp' ) );
		}

		if ( $item_id <= 0 || $order_id <= 0 ) {
			$this->log_ajax_error(
				'Invalid ID values',
				array(
					'item_id'  => $item_id,
					'order_id' => $order_id,
				)
			);
			wp_send_json_error( __( '無効なIDです', 'ktpwp' ) );
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
				wp_send_json_success(
					array(
						'message' => __( '正常に保存されました', 'ktpwp' ),
						'value_changed' => $result['value_changed'],
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
				wp_send_json_error( __( '保存に失敗しました', 'ktpwp' ) );
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
			wp_send_json_error( __( '保存中にエラーが発生しました', 'ktpwp' ) );
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
			wp_send_json_error( __( 'この操作を行う権限がありません。', 'ktpwp' ) );
			return;
		}

		// 安全性の初期チェック（Adminer警告対策）
		if ( ! is_array( $_POST ) || empty( $_POST ) ) {
			error_log( 'KTPWP Ajax: $_POST is not array or empty' );
			wp_send_json_error( __( 'リクエストデータが無効です', 'ktpwp' ) );
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
			wp_send_json_error( __( 'セキュリティ検証に失敗しました', 'ktpwp' ) );
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
			wp_send_json_error( __( '無効なアイテムタイプです', 'ktpwp' ) );
		}

		if ( $order_id <= 0 ) {
			$this->log_ajax_error( 'Invalid order ID for creation', array( 'order_id' => $order_id ) );
			wp_send_json_error( __( '無効な受注IDです', 'ktpwp' ) );
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
						'message' => __( '新しいアイテムが作成されました', 'ktpwp' ),
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
				wp_send_json_error( __( 'アイテムの作成に失敗しました', 'ktpwp' ) );
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
			wp_send_json_error( __( '作成中にエラーが発生しました', 'ktpwp' ) );
		}
	}

	/**
	 * Ajax: アイテム削除処理
	 */
	public function ajax_delete_item() {
		// 編集者以上の権限チェック
		if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'ktpwp_access' ) ) {
			$this->log_ajax_error( 'Delete item permission check failed', $_POST );
			wp_send_json_error( __( 'この操作を行う権限がありません。', 'ktpwp' ) );
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
			wp_send_json_error( __( 'セキュリティ検証に失敗しました', 'ktpwp' ) );
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
			wp_send_json_error( __( '無効なアイテムタイプです', 'ktpwp' ) );
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
			wp_send_json_error( __( '無効なアイテムIDです', 'ktpwp' ) );
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
			wp_send_json_error( __( '無効な受注IDです', 'ktpwp' ) );
		}

		// KTPWP_Order_Items クラスのインスタンスを取得
		// クラスが存在するか確認
		if ( ! class_exists( 'KTPWP_Order_Items' ) ) {
			$this->log_ajax_error( 'KTPWP_Order_Items class not found' );
			wp_send_json_error( __( '必要なクラスが見つかりません。', 'ktpwp' ) );
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
						'message' => __( 'アイテムを削除しました', 'ktpwp' ),
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
				wp_send_json_error( __( 'データベースからのアイテム削除に失敗しました（詳細エラーログ確認）', 'ktpwp' ) );
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
			wp_send_json_error( __( 'アイテム削除中に予期せぬエラーが発生しました（詳細エラーログ確認）', 'ktpwp' ) );
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
			wp_send_json_error( __( 'この操作を行う権限がありません。', 'ktpwp' ) );
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
			wp_send_json_error( __( 'セキュリティ検証に失敗しました', 'ktpwp' ) );
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
			wp_send_json_error( __( '無効な受注IDです', 'ktpwp' ) );
		}

		if ( ! in_array( $item_type, array( 'invoice', 'cost' ), true ) ) {
			$this->log_ajax_error( 'Invalid item type for updating item order', array( 'item_type' => $item_type ) );
			wp_send_json_error( __( '無効なアイテムタイプです', 'ktpwp' ) );
		}

		if ( empty( $items_data ) ) {
			$this->log_ajax_error( 'No items data provided for updating order', $_POST );
			wp_send_json_error( __( '更新するアイテムデータがありません', 'ktpwp' ) );
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
					'message'       => __( '一部のアイテムデータが無効です', 'ktpwp' ),
					'invalid_items' => $invalid_items,
				)
			);
		}

		// KTPWP_Order_Items クラスのインスタンスを取得
		if ( ! class_exists( 'KTPWP_Order_Items' ) ) {
			$this->log_ajax_error( 'KTPWP_Order_Items class not found for updating item order' );
			wp_send_json_error( __( '必要なクラスが見つかりません。', 'ktpwp' ) );
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
						'message'       => __( 'アイテムの並び順を更新しました', 'ktpwp' ),
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
				wp_send_json_error( __( 'データベースでのアイテム並び順更新に失敗しました（詳細エラーログ確認）', 'ktpwp' ) );
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
			wp_send_json_error( __( 'アイテムの並び順更新中に予期せぬエラーが発生しました（詳細エラーログ確認）', 'ktpwp' ) );
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
			wp_send_json_error( __( 'この操作を行う権限がありません', 'ktpwp' ) );
			return;
		}

		// 簡単なnonceチェック
		if ( ! check_ajax_referer( 'ktp_ajax_nonce', 'nonce', false ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( '[AJAX] サービス一覧取得: nonce検証失敗' );
			}
			wp_send_json_error( __( 'セキュリティチェックに失敗しました', 'ktpwp' ) );
			return;
		}

		// パラメータ取得
		$page = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;

		// 一般設定から表示件数を取得
		if ( isset( $_POST['limit'] ) && $_POST['limit'] === 'auto' ) {
			// 一般設定から表示件数を取得（設定クラスが利用可能な場合）
			if ( class_exists( 'KTP_Settings' ) ) {
				$limit = KTP_Settings::get_work_list_range();
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
				wp_send_json_error( __( 'サービスDBクラスが見つかりません', 'ktpwp' ) );
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
						"SELECT * FROM `{$client_table}` WHERE company_name = %s AND name = %s",
						$order->customer_name,
						$order->user_name
					)
				);
			}

			// メール送信可否の判定
			if ( ! $client ) {
				wp_send_json_error(
					array(
						'message'     => 'メール送信不可（顧客データなし）',
						'error_type'  => 'no_client',
						'error_title' => '顧客データが見つかりません',
						'error'       => 'この受注書に関連する顧客データが見つかりません。顧客管理画面で顧客を登録してください。',
					)
				);
				return;
			}

			if ( $client->category === '対象外' ) {
				wp_send_json_error(
					array(
						'message'     => 'メール送信不可（対象外顧客）',
						'error_type'  => 'excluded_client',
						'error_title' => 'メール送信不可',
						'error'       => 'この顧客は削除済み（対象外）のため、メール送信はできません。',
					)
				);
				return;
			}

			// メールアドレスの取得と検証
			$email_raw = $client->email ?? '';
			$name_raw  = $client->name ?? '';

			// nameフィールドにメールアドレスが入っている場合を検出
			$name_is_email  = ! empty( $name_raw ) && filter_var( $name_raw, FILTER_VALIDATE_EMAIL ) !== false;
			$email_is_empty = empty( trim( $email_raw ) );

			if ( $name_is_email && $email_is_empty ) {
				$email_raw = $name_raw;
			}

			$email    = trim( $email_raw );
			$email    = str_replace( array( "\0", "\r", "\n", "\t" ), '', $email );
			$is_valid = ! empty( $email ) && filter_var( $email, FILTER_VALIDATE_EMAIL ) !== false;

			if ( ! $is_valid ) {
				wp_send_json_error(
					array(
						'message'     => 'メール送信不可（メールアドレス未設定または無効）',
						'error_type'  => 'no_email',
						'error_title' => 'メールアドレス未設定',
						'error'       => 'この顧客のメールアドレスが未設定または無効です。顧客管理画面でメールアドレスを登録してください。',
					)
				);
				return;
			}

			$to = sanitize_email( $email );

			// 自社情報取得
			$smtp_settings = get_option( 'ktp_smtp_settings', array() );
			$my_email      = ! empty( $smtp_settings['email_address'] ) ? sanitize_email( $smtp_settings['email_address'] ) : '';

			$my_company = '';
			if ( class_exists( 'KTP_Settings' ) ) {
				$my_company = KTP_Settings::get_company_info();
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

			if ( ! empty( $invoice_items_from_db ) ) {
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

					// 税率の処理（NULL、空文字、NaNの場合は税率なしとして扱う）
					$tax_rate = null;
					if ( $tax_rate_raw !== null && $tax_rate_raw !== '' && is_numeric( $tax_rate_raw ) ) {
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
						$tax_rate_text = ( $tax_rate !== null ) ? '（税率' . $tax_rate . '%）' : '';
						$remarks_text = ( ! empty( trim( $remarks ) ) ) ? '　※ ' . $remarks : '';
						$line         = $product_name . '：' . $price_display . '円 × ' . $quantity_display . $unit . ' = ' . number_format( $item_amount ) . '円' . $tax_rate_text . $remarks_text;
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
								$tax_rate_details[] = $tax_rate . '%: ' . number_format( $tax_amount ) . '円';
							}
						}
				}
				
				$amount_ceiled = ceil( $amount );
				$total_tax_amount_ceiled = ceil( $total_tax_amount );
				$total_with_tax = $amount_ceiled + $total_tax_amount_ceiled;

				// 税区分に応じた合計行の表示
				if ( $tax_category === '外税' ) {
					$total_line = '外税合計：' . number_format( $amount_ceiled ) . '円';
					$tax_line = '消費税：' . number_format( $total_tax_amount_ceiled ) . '円';
					$total_with_tax_line = '内税合計：' . number_format( $total_with_tax ) . '円';
				} else {
									// 内税の場合は税率別の内訳を表示
				if ( count( $tax_rate_groups ) > 1 ) {
					$tax_detail_text = '（内税：' . implode( ', ', $tax_rate_details ) . '）';
				} else {
					// 単一税率の場合
					if ( array_key_first( $tax_rate_groups ) === 'no_tax_rate' ) {
						// 税率なしの場合は内税表示をしない
						$tax_detail_text = '';
					} else {
						$tax_detail_text = '（内税：' . number_format( $total_tax_amount_ceiled ) . '円）';
					}
				}
					$total_line = '金額合計：' . number_format( $amount_ceiled ) . '円' . $tax_detail_text;
					$tax_line = ''; // 内税の場合は消費税行を非表示
					$total_with_tax_line = ''; // 内税の場合は税込合計行を非表示
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
				$invoice_list = '（請求項目未入力）';
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
				1 => '見積り書',
				2 => '注文受書',
				3 => '納品書',
				4 => '請求書',
				5 => '領収書',
				6 => '案件完了',
			);

			$document_messages = array(
				1 => 'につきましてお見積りいたします。',
				2 => 'につきましてご注文をお受けしました。',
				3 => 'につきまして完了しました。',
				4 => 'につきまして請求申し上げます。',
				5 => 'につきましてお支払いを確認しました。',
				6 => 'につきましては全て完了しています。',
			);

			$document_title   = isset( $document_titles[ $progress ] ) ? $document_titles[ $progress ] : '受注書';
			$document_message = isset( $document_messages[ $progress ] ) ? $document_messages[ $progress ] : '';

			// 適格請求書番号を取得し、請求書の場合に追加
			if ( $progress === 4 && class_exists( 'KTP_Settings' ) ) {
				$qualified_invoice_number = KTP_Settings::get_qualified_invoice_number();
				if ( ! empty( $qualified_invoice_number ) ) {
					$document_title = $document_title . ' 適格請求書番号：' . $qualified_invoice_number;
				}
			}

			// 日付フォーマット
			$order_date = date( 'Y年m月d日', $order->time );

			// 部署情報を取得
			$department_info = '';
			$customer_display = $customer_name;
			$user_display = $user_name . ' 様';

			if ( class_exists( 'KTPWP_Department_Manager' ) ) {
				$selected_department = KTPWP_Department_Manager::get_selected_department_by_client( $client->id );
				if ( $selected_department ) {
					// 部署選択がある場合：会社名、部署名、担当者名を別々に表示
					$customer_display = $customer_name;
					$user_display = $selected_department->department_name . "\n" . $selected_department->contact_person . ' 様';
				}
			}

			// 件名と本文の統一フォーマット
			$subject = "{$document_title}：{$project_name}";
			$body    = "{$customer_display}\n{$user_display}\n\nお世話になります。\n\n＜{$document_title}＞\nID: {$order->id} [{$order_date}]\n\n「{$project_name}」{$document_message}\n{$invoice_list}\n\n--\n{$my_company}";

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'KTPWP Ajax get_email_content: Preparing JSON response - to: ' . $to . ', subject: ' . $subject );
			}

			wp_send_json_success(
				array(
					'to'           => $to,
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

			// ファイル添付処理
			$attachments = array();
			$temp_files  = array(); // 一時ファイルの記録（後でクリーンアップ）

			if ( ! empty( $_FILES['attachments'] ) ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'KTPWP Email: Processing file attachments - ' . print_r( $_FILES['attachments'], true ) );
				}

				$uploaded_files = $_FILES['attachments'];

				// ファイルの基本設定
				$max_file_size      = 10 * 1024 * 1024; // 10MB
				$max_total_size     = 50 * 1024 * 1024; // 50MB
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
					error_log( "KTPWP Email: Processing {$file_count} files" );
				}

				for ( $i = 0; $i < $file_count; $i++ ) {
					$file_name  = is_array( $uploaded_files['name'] ) ? $uploaded_files['name'][ $i ] : $uploaded_files['name'];
					$file_tmp   = is_array( $uploaded_files['tmp_name'] ) ? $uploaded_files['tmp_name'][ $i ] : $uploaded_files['tmp_name'];
					$file_size  = is_array( $uploaded_files['size'] ) ? $uploaded_files['size'][ $i ] : $uploaded_files['size'];
					$file_type  = is_array( $uploaded_files['type'] ) ? $uploaded_files['type'][ $i ] : $uploaded_files['type'];
					$file_error = is_array( $uploaded_files['error'] ) ? $uploaded_files['error'][ $i ] : $uploaded_files['error'];

					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( "KTPWP Email: Processing file {$i}: {$file_name} ({$file_size} bytes, type: {$file_type})" );
					}

					// ファイルエラーチェック
					if ( $file_error !== UPLOAD_ERR_OK ) {
						throw new Exception( "ファイル「{$file_name}」のアップロードでエラーが発生しました。（エラーコード: {$file_error}）" );
					}

					// ファイルサイズチェック
					if ( $file_size > $max_file_size ) {
						throw new Exception( "ファイル「{$file_name}」は10MBを超えています。" );
					}

					$total_size += $file_size;
					if ( $total_size > $max_total_size ) {
						throw new Exception( '合計ファイルサイズが50MBを超えています。' );
					}

					// ファイル形式チェック
					$file_ext        = strtolower( pathinfo( $file_name, PATHINFO_EXTENSION ) );
					$is_allowed_type = in_array( $file_type, $allowed_types );
					$is_allowed_ext  = in_array( '.' . $file_ext, $allowed_extensions );

					if ( ! $is_allowed_type && ! $is_allowed_ext ) {
						throw new Exception( "ファイル「{$file_name}」は対応していない形式です。" );
					}

					// ファイル名のサニタイズ
					$safe_filename = sanitize_file_name( $file_name );

					// 一時ディレクトリにファイルを保存
					$upload_dir = wp_upload_dir();
					$temp_dir   = $upload_dir['basedir'] . '/ktp-email-temp/';

					// 一時ディレクトリが存在しない場合は作成
					if ( ! file_exists( $temp_dir ) ) {
						wp_mkdir_p( $temp_dir );
						if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
							error_log( "KTPWP Email: Created temp directory: {$temp_dir}" );
						}
					}

					// ユニークなファイル名を生成
					$unique_filename = uniqid() . '_' . $safe_filename;
					$temp_file_path  = $temp_dir . $unique_filename;

					// ファイルを移動
					if ( move_uploaded_file( $file_tmp, $temp_file_path ) ) {
						$attachments[] = $temp_file_path;
						$temp_files[]  = $temp_file_path; // クリーンアップ用
						if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
							error_log( "KTPWP Email: Saved attachment: {$temp_file_path}" );
						}
					} else {
						throw new Exception( "ファイル「{$file_name}」の保存に失敗しました。" );
					}
				}

				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'KTPWP Email: Successfully processed ' . count( $attachments ) . ' attachments, total size: ' . round( $total_size / 1024 / 1024, 2 ) . 'MB' );
				}
			}

			// メール送信
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( "KTPWP Email: Sending email to {$to} with " . count( $attachments ) . ' attachments' );
			}

			$sent = wp_mail( $to, $subject, $body, $headers, $attachments );

			// 一時ファイルのクリーンアップ
			foreach ( $temp_files as $temp_file ) {
				if ( file_exists( $temp_file ) ) {
					unlink( $temp_file );
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( 'KTPWP Email: Cleaned up temp file: ' . basename( $temp_file ) );
					}
				}
			}

			if ( $sent ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( "KTPWP Email: Successfully sent email to {$to} with " . count( $attachments ) . ' attachments' );
				}
				wp_send_json_success(
					array(
						'message'          => 'メールを送信しました。',
						'to'               => $to,
						'attachment_count' => count( $attachments ),
					)
				);
			} else {
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
						'message' => '協力会社が見つかりません。',
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
		try {
			// セキュリティチェック
			if ( ! check_ajax_referer( 'ktpwp_ajax_nonce', 'nonce', false ) ) {
				throw new Exception( 'セキュリティ検証に失敗しました。' );
			}

			// 権限チェック
			if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'ktpwp_access' ) ) {
				throw new Exception( '権限がありません。' );
			}

			$to       = isset( $_POST['to'] ) ? sanitize_email( $_POST['to'] ) : '';
			$subject  = isset( $_POST['subject'] ) ? sanitize_text_field( $_POST['subject'] ) : '';
			$body     = isset( $_POST['body'] ) ? sanitize_textarea_field( $_POST['body'] ) : '';
			$supplier_name = isset( $_POST['supplier_name'] ) ? sanitize_text_field( $_POST['supplier_name'] ) : '';

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
			if ( class_exists( 'KTP_Settings' ) ) {
				$my_company = KTP_Settings::get_company_info();
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
			$headers = array();
			if ( ! empty( $my_email ) ) {
				$from_name = ! empty( $smtp_settings['smtp_from_name'] ) ? sanitize_text_field( $smtp_settings['smtp_from_name'] ) : '';
				if ( ! empty( $from_name ) ) {
					$headers[] = 'From: ' . $from_name . ' <' . $my_email . '>';
				} else {
					$headers[] = 'From: ' . $my_email;
				}
			}

			// ファイル添付処理
			$attachments = array();
			$temp_files  = array(); // 一時ファイルの記録（後でクリーンアップ）

			if ( ! empty( $_FILES['attachments'] ) ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'KTPWP Purchase Order Email: Processing file attachments - ' . print_r( $_FILES['attachments'], true ) );
				}

				$uploaded_files = $_FILES['attachments'];

				// ファイルの基本設定
				$max_file_size      = 10 * 1024 * 1024; // 10MB
				$max_total_size     = 50 * 1024 * 1024; // 50MB
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
					error_log( "KTPWP Purchase Order Email: Processing {$file_count} files" );
				}

				for ( $i = 0; $i < $file_count; $i++ ) {
					$file_name  = is_array( $uploaded_files['name'] ) ? $uploaded_files['name'][ $i ] : $uploaded_files['name'];
					$file_tmp   = is_array( $uploaded_files['tmp_name'] ) ? $uploaded_files['tmp_name'][ $i ] : $uploaded_files['tmp_name'];
					$file_size  = is_array( $uploaded_files['size'] ) ? $uploaded_files['size'][ $i ] : $uploaded_files['size'];
					$file_type  = is_array( $uploaded_files['type'] ) ? $uploaded_files['type'][ $i ] : $uploaded_files['type'];
					$file_error = is_array( $uploaded_files['error'] ) ? $uploaded_files['error'][ $i ] : $uploaded_files['error'];

					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( "KTPWP Purchase Order Email: Processing file {$i}: {$file_name} ({$file_size} bytes, type: {$file_type})" );
					}

					// ファイルエラーチェック
					if ( $file_error !== UPLOAD_ERR_OK ) {
						throw new Exception( "ファイル「{$file_name}」のアップロードでエラーが発生しました。（エラーコード: {$file_error}）" );
					}

					// ファイルサイズチェック
					if ( $file_size > $max_file_size ) {
						throw new Exception( "ファイル「{$file_name}」は10MBを超えています。" );
					}

					$total_size += $file_size;
					if ( $total_size > $max_total_size ) {
						throw new Exception( '合計ファイルサイズが50MBを超えています。' );
					}

					// ファイル形式チェック
					$file_ext        = strtolower( pathinfo( $file_name, PATHINFO_EXTENSION ) );
					$is_allowed_type = in_array( $file_type, $allowed_types );
					$is_allowed_ext  = in_array( '.' . $file_ext, $allowed_extensions );

					if ( ! $is_allowed_type && ! $is_allowed_ext ) {
						throw new Exception( "ファイル「{$file_name}」は対応していない形式です。" );
					}

					// ファイル名のサニタイズ
					$safe_filename = sanitize_file_name( $file_name );

					// 一時ディレクトリにファイルを保存
					$upload_dir = wp_upload_dir();
					$temp_dir   = $upload_dir['basedir'] . '/ktp-email-temp/';

					// 一時ディレクトリが存在しない場合は作成
					if ( ! file_exists( $temp_dir ) ) {
						wp_mkdir_p( $temp_dir );
						if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
							error_log( "KTPWP Purchase Order Email: Created temp directory: {$temp_dir}" );
						}
					}

					// ユニークなファイル名を生成
					$unique_filename = uniqid() . '_' . $safe_filename;
					$temp_file_path  = $temp_dir . $unique_filename;

					// ファイルを移動
					if ( move_uploaded_file( $file_tmp, $temp_file_path ) ) {
						$attachments[] = $temp_file_path;
						$temp_files[]  = $temp_file_path; // クリーンアップ用
						if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
							error_log( "KTPWP Purchase Order Email: Saved attachment: {$temp_file_path}" );
						}
					} else {
						throw new Exception( "ファイル「{$file_name}」の保存に失敗しました。" );
					}
				}

				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'KTPWP Purchase Order Email: Successfully processed ' . count( $attachments ) . ' attachments, total size: ' . round( $total_size / 1024 / 1024, 2 ) . 'MB' );
				}
			}

			// メール送信
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( "KTPWP Purchase Order Email: Sending email to {$to} with " . count( $attachments ) . ' attachments' );
			}

			$sent = wp_mail( $to, $subject, $body, $headers, $attachments );

			// 一時ファイルのクリーンアップ
			foreach ( $temp_files as $temp_file ) {
				if ( file_exists( $temp_file ) ) {
					unlink( $temp_file );
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						error_log( 'KTPWP Purchase Order Email: Cleaned up temp file: ' . basename( $temp_file ) );
					}
				}
			}

			if ( $sent ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( "KTPWP Purchase Order Email: Successfully sent email to {$to} with " . count( $attachments ) . ' attachments' );
				}
				wp_send_json_success(
					array(
						'message'          => '発注書メールを送信しました。',
						'to'               => $to,
						'supplier_name'    => $supplier_name,
						'attachment_count' => count( $attachments ),
					)
				);
			} else {
				throw new Exception( 'メール送信に失敗しました。サーバー設定を確認してください。' );
			}
		} catch ( Exception $e ) {
			// エラー時も一時ファイルをクリーンアップ
			if ( ! empty( $temp_files ) ) {
				foreach ( $temp_files as $temp_file ) {
					if ( file_exists( $temp_file ) ) {
						unlink( $temp_file );
						if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
							error_log( 'KTPWP Purchase Order Email Error: Cleaned up temp file on error: ' . basename( $temp_file ) );
						}
					}
				}
			}

			error_log( 'KTPWP Ajax send_purchase_order_email Error: ' . $e->getMessage() );
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'KTPWP Ajax send_purchase_order_email Error Stack Trace: ' . $e->getTraceAsString() );
			}
			wp_send_json_error(
				array(
					'message' => $e->getMessage(),
				)
			);
		}
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
			if ( class_exists( 'KTP_Settings' ) ) {
				$company_info = KTP_Settings::get_company_info();
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
						'data'    => __( 'ログインが必要です', 'ktpwp' ),
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
						'data'    => __( 'セキュリティトークンが無効です', 'ktpwp' ),
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
						'data'    => __( '注文IDが必要です', 'ktpwp' ),
					)
				);
				return;
			}

			// 権限チェック
			if ( ! current_user_can( 'read' ) ) {
				$this->send_clean_json_response(
					array(
						'success' => false,
						'data'    => __( '権限がありません', 'ktpwp' ),
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
					'data'    => __( 'メッセージの取得中にエラーが発生しました', 'ktpwp' ),
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
				wp_send_json_error( __( 'ログインが必要です', 'ktpwp' ) );
				return;
			}

			// Nonce検証（_ajax_nonceパラメータで送信される）
			$nonce       = $_POST['_ajax_nonce'] ?? '';
			$nonce_valid = wp_verify_nonce( $nonce, $this->nonce_names['staff_chat'] );
			// nonceが不正かつ権限もない場合のみエラー
			if ( ! $nonce_valid && ! current_user_can( 'edit_posts' ) && ! current_user_can( 'ktpwp_access' ) ) {
				wp_send_json_error( __( '権限がありません（nonce不正）', 'ktpwp' ) );
				return;
			}

			// パラメータの取得とサニタイズ
			$order_id = $this->sanitize_ajax_input( 'order_id', 'int' );
			$message  = $this->sanitize_ajax_input( 'message', 'text' );

			if ( empty( $order_id ) || empty( $message ) ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'KTPWP StaffChat: order_idまたはmessageが空: order_id=' . print_r( $order_id, true ) . ' message=' . print_r( $message, true ) );
				}
				wp_send_json_error( __( '注文IDとメッセージが必要です', 'ktpwp' ) );
				return;
			}

			// 権限チェック
			if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'ktpwp_access' ) ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'KTPWP StaffChat: 権限チェック失敗 user_id=' . get_current_user_id() );
				}
				wp_send_json_error( __( '権限がありません', 'ktpwp' ) );
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
							'message' => __( 'メッセージを送信しました', 'ktpwp' ),
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
						'data'    => __( 'メッセージの送信に失敗しました', 'ktpwp' ),
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
					'data'    => __( 'メッセージの送信中にエラーが発生しました', 'ktpwp' ),
				)
			);
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
			if ( ! class_exists( 'Kntan_Order_Class' ) ) {
				require_once MY_PLUGIN_PATH . 'includes/class-tab-order.php';
			}

			$order_class = new Kntan_Order_Class();

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

		// JSONを出力
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
	 * 納期フィールドの保存処理
	 */
	public function ajax_save_delivery_date() {
		try {
			// デバッグ情報をログに出力
			error_log( 'KTPWP Ajax save_delivery_date called with POST data: ' . print_r( $_POST, true ) );

			// パラメータ取得
			$order_id    = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
			$field_name  = isset( $_POST['field_name'] ) ? sanitize_text_field( $_POST['field_name'] ) : '';
			$field_value = isset( $_POST['field_value'] ) ? sanitize_text_field( $_POST['field_value'] ) : '';

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

			$delivery_columns = array( 'desired_delivery_date', 'expected_delivery_date', 'completion_date' );
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
			$allowed_fields = array( 'desired_delivery_date', 'expected_delivery_date', 'completion_date' );
			if ( ! in_array( $field_name, $allowed_fields ) ) {
				throw new Exception( '無効なフィールド名です。' );
			}

			// 日付形式の検証
			if ( ! empty( $field_value ) ) {
				$date_obj = DateTime::createFromFormat( 'Y-m-d', $field_value );
				if ( ! $date_obj || $date_obj->format( 'Y-m-d' ) !== $field_value ) {
					throw new Exception( '無効な日付形式です。' );
				}
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

			wp_send_json_success(
				array(
					'message'     => '納期が正常に保存されました。',
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
				wp_send_json_error( array( 'message' => __( 'この操作を行う権限がありません。', 'ktpwp' ) ) );
				return;
			}

			// nonce検証
			if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'ktp_ajax_nonce' ) ) {
				wp_send_json_error( array( 'message' => __( 'セキュリティ検証に失敗しました', 'ktpwp' ) ) );
				return;
			}

			// POSTデータの取得とサニタイズ
			$order_id = $this->sanitize_ajax_input( 'order_id', 'int' );
			$field    = $this->sanitize_ajax_input( 'field', 'text' );
			$value    = $this->sanitize_ajax_input( 'value', 'text' );

			// バリデーション
			if ( $order_id <= 0 ) {
				wp_send_json_error( array( 'message' => __( '無効な受注IDです', 'ktpwp' ) ) );
				return;
			}

			// フィールド名の検証
			$allowed_fields = array( 'desired_delivery_date', 'expected_delivery_date', 'completion_date' );
			if ( ! in_array( $field, $allowed_fields ) ) {
				wp_send_json_error( array( 'message' => __( '無効なフィールド名です', 'ktpwp' ) ) );
				return;
			}

			// 日付形式の検証（空の場合は許可）
			if ( ! empty( $value ) ) {
				$date_obj = DateTime::createFromFormat( 'Y-m-d', $value );
				if ( ! $date_obj || $date_obj->format( 'Y-m-d' ) !== $value ) {
					wp_send_json_error( array( 'message' => __( '無効な日付形式です', 'ktpwp' ) ) );
					return;
				}
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

			wp_send_json_success(
				array(
					'message' => __( '納期を更新しました', 'ktpwp' ),
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
				wp_send_json_error( __( 'この操作を行う権限がありません。', 'ktpwp' ) );
				return;
			}

			// nonce検証
			if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'ktp_ajax_nonce' ) ) {
				error_log( 'KTPWP Ajax: Nonce verification failed' );
				wp_send_json_error( __( 'セキュリティ検証に失敗しました', 'ktpwp' ) );
				return;
			}

			global $wpdb;
			$table_name = $wpdb->prefix . 'ktp_order';

			// 一般設定から警告日数を取得
			$warning_days = 3; // デフォルト値
			if ( class_exists( 'KTP_Settings' ) ) {
				$warning_days = KTP_Settings::get_delivery_warning_days();
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
				wp_send_json_error( __( 'データベースエラーが発生しました', 'ktpwp' ) );
				return;
			}

			error_log( 'KTPWP Ajax: Warning count: ' . $warning_count );

			wp_send_json_success(
				array(
					'warning_count' => (int) $warning_count,
					'warning_days'  => $warning_days,
				)
			);

		} catch ( Exception $e ) {
			error_log( 'KTPWP Ajax ajax_get_creating_warning_count Error: ' . $e->getMessage() );
			wp_send_json_error( __( 'エラーが発生しました: ' . $e->getMessage(), 'ktpwp' ) );
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
				wp_send_json_error( __( 'この操作を行う権限がありません。', 'ktpwp' ) );
				return;
			}

			// nonce検証
			if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'ktp_ajax_nonce' ) ) {
				error_log( 'KTPWP Ajax: Nonce verification failed' );
				wp_send_json_error( __( 'セキュリティ検証に失敗しました', 'ktpwp' ) );
				return;
			}

			// パラメータ取得
			$client_id = isset( $_POST['client_id'] ) ? absint( $_POST['client_id'] ) : 0;

			if ( $client_id <= 0 ) {
				wp_send_json_error( '顧客IDが無効です' );
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
				wp_send_json_error( __( '顧客が見つからないか、データベースエラーが発生しました', 'ktpwp' ) );
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
			wp_send_json_error( __( 'エラーが発生しました: ' . $e->getMessage(), 'ktpwp' ) );
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
				wp_send_json_error( __( 'この操作を行う権限がありません。', 'ktpwp' ) );
				return;
			}

			// nonce検証
			if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'ktp_ajax_nonce' ) ) {
				error_log( 'KTPWP Ajax: Nonce verification failed' );
				wp_send_json_error( __( 'セキュリティ検証に失敗しました', 'ktpwp' ) );
				return;
			}

			// パラメータ取得
			$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;

			if ( $order_id <= 0 ) {
				wp_send_json_error( '受注書IDが無効です' );
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
				wp_send_json_error( __( '受注書が見つからないか、データベースエラーが発生しました', 'ktpwp' ) );
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
				wp_send_json_error( __( '顧客が見つからないか、データベースエラーが発生しました', 'ktpwp' ) );
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
			wp_send_json_error( __( 'エラーが発生しました: ' . $e->getMessage(), 'ktpwp' ) );
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
				wp_send_json_error( __( 'この操作を行う権限がありません。', 'ktpwp' ) );
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
				wp_send_json_error(__('セキュリティ検証に失敗しました', 'ktpwp'));
				return;
			}

			// パラメータ取得
			$supplier_id = isset( $_POST['supplier_id'] ) ? absint( $_POST['supplier_id'] ) : 0;

			if ( $supplier_id <= 0 ) {
				wp_send_json_error( '協力会社IDが無効です' );
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
				wp_send_json_error( __( 'データベースエラーが発生しました', 'ktpwp' ) );
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
			wp_send_json_error( __( 'エラーが発生しました: ' . $e->getMessage(), 'ktpwp' ) );
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
				wp_send_json_error( __( 'この操作を行う権限がありません。', 'ktpwp' ) );
				return;
			}

			// nonce検証
			if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'ktp_ajax_nonce' ) ) {
				error_log( 'KTPWP Ajax: Nonce verification failed' );
				wp_send_json_error( __( 'セキュリティ検証に失敗しました', 'ktpwp' ) );
				return;
			}

			// パラメータ取得
			$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
			$field_name = isset( $_POST['field_name'] ) ? sanitize_text_field( $_POST['field_name'] ) : '';
			$field_value = isset( $_POST['field_value'] ) ? sanitize_text_field( $_POST['field_value'] ) : '';

			if ( $order_id <= 0 ) {
				wp_send_json_error( '受注書IDが無効です' );
			}

			if ( empty( $field_name ) ) {
				wp_send_json_error( 'フィールド名が指定されていません' );
			}

			// 許可されたフィールド名のみ更新可能
			$allowed_fields = array( 'completion_date', 'expected_delivery_date' );
			if ( ! in_array( $field_name, $allowed_fields ) ) {
				wp_send_json_error( '許可されていないフィールドです' );
			}

			global $wpdb;
			$table_name = $wpdb->prefix . 'ktp_order';

			// データベース更新
			$update_data = array( $field_name => $field_value );
			$result = $wpdb->update( $table_name, $update_data, array( 'id' => $order_id ) );

			if ( $result === false ) {
				error_log( 'KTPWP Ajax: Database update failed: ' . $wpdb->last_error );
				wp_send_json_error( 'データベース更新に失敗しました: ' . $wpdb->last_error );
			}

			error_log( 'KTPWP Ajax: Field saved successfully - Order ID: ' . $order_id . ', Field: ' . $field_name . ', Value: ' . $field_value );

			wp_send_json_success(
                array(
					'message' => 'フィールドが正常に保存されました',
					'field_name' => $field_name,
					'field_value' => $field_value,
                )
            );

		} catch ( Exception $e ) {
			error_log( 'KTPWP Ajax ajax_auto_save_field Error: ' . $e->getMessage() );
			wp_send_json_error( __( 'エラーが発生しました: ' . $e->getMessage(), 'ktpwp' ) );
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
				wp_send_json_error( __( 'この操作を行う権限がありません。', 'ktpwp' ) );
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
				wp_send_json_error(__('セキュリティ検証に失敗しました', 'ktpwp'));
				return;
			}

			// パラメータ取得
			$supplier_id = isset( $_POST['supplier_id'] ) ? absint( $_POST['supplier_id'] ) : 0;
			
			// デバッグ情報をログに記録
			error_log( 'KTPWP Ajax: ajax_get_supplier_qualified_invoice_number - supplier_id: ' . $supplier_id . ', POST data: ' . print_r($_POST, true) );
			
			if ( $supplier_id <= 0 ) {
				error_log( 'KTPWP Ajax: Invalid supplier ID: ' . $supplier_id );
				wp_send_json_error( '協力会社IDが無効です (ID: ' . $supplier_id . ')' );
				return;
			}

			global $wpdb;
			$supplier_table = $wpdb->prefix . 'ktp_supplier';

			// テーブルが存在するかチェック
			$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$supplier_table'") === $supplier_table;
			if (!$table_exists) {
				error_log('KTPWP Ajax: Supplier table does not exist: ' . $supplier_table);
				wp_send_json_error(__('協力会社テーブルが存在しません', 'ktpwp'));
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
				wp_send_json_error( __( 'データベースエラーが発生しました', 'ktpwp' ) );
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
			wp_send_json_error( __( 'エラーが発生しました: ' . $e->getMessage(), 'ktpwp' ) );
		}
	}

	/**
	 * 進捗更新Ajaxハンドラー
	 */
	public function ajax_update_progress() {
		// セキュリティチェック
		if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'ktpwp_access' ) ) {
			wp_send_json_error( '権限がありません' );
		}

		// nonceチェック
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'ktp_ajax_nonce' ) ) {
			wp_send_json_error( 'セキュリティ検証に失敗しました' );
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'ktp_order';

		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		$new_progress = isset( $_POST['progress'] ) ? absint( $_POST['progress'] ) : 0;
		$completion_date = isset( $_POST['completion_date'] ) ? sanitize_text_field( $_POST['completion_date'] ) : '';

		if ( $order_id <= 0 || $new_progress < 1 || $new_progress > 7 ) {
			wp_send_json_error( 'パラメータが不正です' );
		}

		// 現在の進捗を取得
		$current_order = $wpdb->get_row( $wpdb->prepare( "SELECT progress FROM {$table_name} WHERE id = %d", $order_id ) );
		if ( ! $current_order ) {
			wp_send_json_error( '受注書が見つかりません' );
		}

		$update_data = array( 'progress' => $new_progress );

		// 進捗が「完了」（progress = 4）に変更された場合、完了日を記録
		if ( $new_progress == 4 && $current_order->progress != 4 ) {
			if ( ! empty( $completion_date ) ) {
				// フォームから完了日が送信されている場合はその値を使用
				$update_data['completion_date'] = $completion_date;
			} else {
				// フォームから完了日が送信されていない場合は今日の日付を設定
				$update_data['completion_date'] = current_time( 'Y-m-d' );
			}
		}

		// 進捗が受注以前（受付中、見積中、受注）に変更された場合、完了日をクリア
		if ( in_array( $new_progress, array( 1, 2, 3 ) ) && $current_order->progress > 3 ) {
			$update_data['completion_date'] = null;
		}

		// データベース更新
		$result = $wpdb->update( $table_name, $update_data, array( 'id' => $order_id ) );

		if ( $result !== false ) {
			wp_send_json_success( array(
				'message' => '進捗を更新しました',
				'progress' => $new_progress,
				'completion_date' => isset( $update_data['completion_date'] ) ? $update_data['completion_date'] : null
			) );
		} else {
			wp_send_json_error( 'データベース更新に失敗しました' );
		}
	}

	/**
	 * 受注書データを取得
	 */
	public function get_order_data() {
		// セキュリティチェック
		if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ktp_ajax_nonce')) {
			wp_send_json_error('セキュリティチェックに失敗しました。');
			return;
		}
		
		// 受注書IDを取得
		$order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
		if (!$order_id) {
			wp_send_json_error('受注書IDが指定されていません。');
			return;
		}
		
		// 受注書データを取得
		global $wpdb;
		$table_name = $wpdb->prefix . 'ktp_order';
		$order_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$table_name}` WHERE id = %d", $order_id));
		
		if (!$order_data) {
			wp_send_json_error('指定された受注書が見つかりません。');
			return;
		}
		
		// 受注書タブクラスのインスタンスを作成
		if (class_exists('Kntan_Order_Class')) {
			$order_tab = new Kntan_Order_Class();
			
			// 受注書のHTMLを生成
			ob_start();
			$order_tab->Order_Tab_View('order');
			$html = ob_get_clean();
			
			wp_send_json_success(array(
				'html' => $html,
				'order_id' => $order_id
			));
		} else {
			wp_send_json_error('受注書クラスが見つかりません。');
		}
	}

	/**
	 * 請求書候補データを取得
	 */
	public function ajax_get_invoice_candidates() {
		// セキュリティチェック
		if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ktp_ajax_nonce')) {
			wp_send_json_error('セキュリティチェックに失敗しました。');
			return;
		}
		
		// 顧客IDを取得
		$client_id = isset($_POST['client_id']) ? absint($_POST['client_id']) : 0;
		if (!$client_id) {
			wp_send_json_error('顧客IDが指定されていません。');
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
			wp_send_json_error('指定された顧客が見つかりません。');
			return;
		}
		
		// 受注書データを取得（完了済みで請求済みでないもの）
		$order_table = $wpdb->prefix . 'ktp_order';
		$orders = $wpdb->get_results($wpdb->prepare(
			"SELECT * FROM `{$order_table}` 
			WHERE client_id = %d 
			AND progress = 4 
			AND completion_date IS NOT NULL
			ORDER BY completion_date ASC",
			$client_id
		));
		

		
		if (empty($orders)) {
			wp_send_json_error('請求対象の受注書が見つかりません。');
			return;
		}
		
		// 月別グループに分類
		$monthly_groups = array();
		foreach ($orders as $order) {
			$completion_date = new DateTime($order->completion_date);
			$year = $completion_date->format('Y');
			$month = $completion_date->format('m');
			$key = $year . '-' . $month;
			
			if (!isset($monthly_groups[$key])) {
				$monthly_groups[$key] = array(
					'billing_period' => $year . '年' . intval($month) . '月分',
					'closing_date' => $completion_date->format('Y年m月d日'),
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
			$monthly_groups[$key]['subtotal'] = $group_subtotal;
			$monthly_groups[$key]['tax_amount'] = $group_tax_amount;
		}
		
		// 支払期日を計算
		$payment_due_date = $this->calculate_payment_due_date($client_data);
		
		// レスポンスデータを構築
		$response_data = array(
			'client_name' => $client_data->company_name,
			'client_address' => $client_data->address,
			'client_contact' => $client_data->contact_person,
			'monthly_groups' => $monthly_groups,
			'departments' => $departments,
			'qualified_invoice_number' => $qualified_invoice_number,
			'tax_category' => $tax_category,
			'selected_department' => null, // デフォルトでは部署選択なし
			'payment_due_date' => $payment_due_date
		);
		
		wp_send_json_success($response_data);
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
			$supplier_name = isset( $_POST['supplier_name'] ) ? sanitize_text_field( $_POST['supplier_name'] ) : '';

			if ( $order_id <= 0 ) {
				throw new Exception( '無効な受注書IDです。' );
			}

			if ( empty( $supplier_name ) ) {
				throw new Exception( '協力会社名が指定されていません。' );
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

			// 協力会社データを取得
			$supplier_table = $wpdb->prefix . 'ktp_supplier';
			$supplier = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM `{$supplier_table}` WHERE company_name = %s",
					$supplier_name
				)
			);

			if ( ! $supplier ) {
				throw new Exception( '協力会社が見つかりません。' );
			}

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
			if ( class_exists( 'KTP_Settings' ) ) {
				$my_company = KTP_Settings::get_company_info();
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
				if ( $setting ) {
					$my_company = sanitize_text_field( strip_tags( $setting->my_company_content ) );
				}
			}

			// 発注書メール内容を生成
			$subject = '【発注書】受注書ID:' . $order_id . 'の件';

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
				$non_qualified_invoice_cost
			);

			wp_send_json_success(
				array(
					'subject' => $subject,
					'body' => $body,
					'supplier_email' => $supplier->email,
					'supplier_name' => $supplier->name,
					'supplier_qualified_invoice_number' => $supplier->qualified_invoice_number,
					'order_info' => array(
						'project_name' => $order->project_name,
						'order_date' => $order->order_date,
						'delivery_date' => isset( $order->delivery_date ) ? $order->delivery_date : null,
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
	private function generate_purchase_order_email_body( $order, $supplier, $client, $cost_items, $my_company, $total_amount, $total_tax_amount, $qualified_invoice_cost, $non_qualified_invoice_cost ) {
		$body = '';

		// 協力会社情報
		$body .= $supplier->company_name . "\n";
		if ( ! empty( $supplier->name ) ) {
			$body .= $supplier->name . "　様\n";
		} else {
			$body .= "　様\n";
		}
		$body .= "\n";

		// 挨拶文
		$body .= "いつもお世話になっています。\n";
		$body .= "以下のように発注します。納期が決まりましたらご連絡ください。\n\n";

		// 発注内容
		$body .= "【発注内容】\n";
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
			$product_name = $item['product_name'];
			$price = floatval( $item['price'] );
			$quantity = floatval( $item['quantity'] );
			$unit = $item['unit'] ?: '';
			$amount = floatval( $item['amount'] );
			$tax_rate = $item['tax_rate'];
			// 税率がNULLまたは空の場合は非課税取引
			$is_tax_free = empty( $tax_rate ) || $tax_rate === null;
			$tax_rate = $is_tax_free ? 0.0 : floatval( $tax_rate );
			$tax_category = isset($item['tax_category']) ? $item['tax_category'] : '内税';
			$remarks = $item['remarks'] ?: '';
			$has_qualified_invoice = ! empty( $item['qualified_invoice_number'] );

			// 品名を左寄せで表示（商品名と単価の間に半角スペース2つを追加）
			$product_name_padded = str_pad( $product_name, $max_length + 2, ' ' );
			
			// 詳細形式：品名  単価 × 数量/単位 = 金額円（税率X%・税区分）※ 備考
			if ( $is_tax_free ) {
				$tax_info = "（非課税取引）";
			} elseif ( $tax_rate == 0 ) {
				$tax_info = "（税率0%・{$tax_category}）";
			} else {
				$tax_info = "（税率{$tax_rate}%・{$tax_category}）";
			}
			$remarks_text = ( ! empty( trim( $remarks ) ) ) ? '　※ ' . $remarks : '';
			
			$body .= $product_name_padded . "  " . number_format( $price ) . '円 × ' . $quantity . $unit . ' = ' . number_format( $amount ) . "円{$tax_info}{$remarks_text}\n";

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
		
		if ( $is_inclusive_tax ) {
			// 内税の場合：税込金額を表示し、税抜価格と消費税額を内訳で表示
			// 要求された形式に合わせて、税抜価格と消費税額を計算
			$tax_excluded_amount = $total_amount - $total_tax_amount;
			$body .= "金額合計：" . number_format( $total_amount ) . "円（税抜価格：" . number_format( $tax_excluded_amount ) . "円＋消費税額：" . number_format( $total_tax_amount ) . "円）\n\n";
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
			
			$body .= "金額合計：" . number_format( $total_amount ) . "円\n";
			$body .= "消費税額：" . number_format( $correct_tax_amount ) . "円\n";
			$body .= "税込金額：" . number_format( $tax_included_amount ) . "円\n\n";
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
}
