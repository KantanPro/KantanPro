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
		// 初期化処理
		add_action( 'init', array( $this, 'register_ajax_handlers' ), 10 );

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

		// 顧客IDを受け取り、完了日が締日を超えている案件リストをJSONで返す
		add_action( 'wp_ajax_ktp_get_invoice_candidates', 'ktpwp_ajax_get_invoice_candidates' );
		add_action( 'wp_ajax_nopriv_ktp_get_invoice_candidates', array( $this, 'ajax_require_login' ) ); // 非ログインユーザーはエラー
		$this->registered_handlers[] = 'ktp_get_invoice_candidates';

		// 部署選択状態更新
		add_action( 'wp_ajax_ktp_update_department_selection', 'ktpwp_ajax_update_department_selection' );
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

		// ▼▼▼ 一括請求書「請求済」進捗変更Ajax ▼▼▼
		add_action('wp_ajax_ktp_set_invoice_completed', function() {
			// 権限チェック
			if ( ! current_user_can('edit_posts') && ! current_user_can('ktpwp_access') ) {
				wp_send_json_error('権限がありません');
			}
			// nonceチェック（必要ならPOSTでnonceも送る）
			// if ( ! isset($_POST['nonce']) || ! wp_verify_nonce($_POST['nonce'], 'ktp_ajax_nonce') ) {
			//     wp_send_json_error('セキュリティ検証に失敗しました');
			// }
			global $wpdb;
			$client_id = isset($_POST['client_id']) ? intval($_POST['client_id']) : 0;
			if (!$client_id) {
				wp_send_json_error('client_idが指定されていません');
			}
			$table_name = $wpdb->prefix . 'ktp_order';
			// progress=4（完了）→5（請求済）に一括更新（completion_dateは変更しない）
			$result = $wpdb->query($wpdb->prepare(
				"UPDATE {$table_name} SET progress = 5 WHERE client_id = %d AND progress = 4",
				$client_id
			));
			if ($result === false) {
				wp_send_json_error('DB更新に失敗しました: ' . $wpdb->last_error);
			}
			wp_send_json_success(['updated' => $result]);
		});
		// ▲▲▲ 一括請求書「請求済」進捗変更Ajax ▲▲▲

		// ▼▼▼ コスト項目「注文済」一括更新Ajax ▼▼▼
		add_action('wp_ajax_ktp_set_cost_items_ordered', function() {
			// 権限チェック
			if ( ! current_user_can('edit_posts') && ! current_user_can('ktpwp_access') ) {
				wp_send_json_error('権限がありません');
			}
			// nonceチェック
			if ( ! isset($_POST['nonce']) || ! wp_verify_nonce($_POST['nonce'], 'ktp_ajax_nonce') ) {
				wp_send_json_error('セキュリティ検証に失敗しました');
			}
			global $wpdb;
			$order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
			$supplier_name = isset($_POST['supplier_name']) ? sanitize_text_field($_POST['supplier_name']) : '';
			if (!$order_id || !$supplier_name) {
				wp_send_json_error('order_idまたはsupplier_nameが指定されていません');
			}
			$table_name = $wpdb->prefix . 'ktp_order_cost_items';
			// purchaseカラムがsupplier_nameと一致する全行を更新
			$result = $wpdb->update(
				$table_name,
				array('ordered' => 1),
				array('order_id' => $order_id, 'purchase' => $supplier_name),
				array('%d'),
				array('%d', '%s')
			);
			if ($result === false) {
				wp_send_json_error('DB更新に失敗しました: ' . $wpdb->last_error);
			}
			wp_send_json_success(['updated' => $result]);
		});
		// ▲▲▲ コスト項目「注文済」一括更新Ajax ▲▲▲
	}

	/**
	 * 受注関連Ajaxハンドラー初期化
	 */
	private function init_order_ajax_handlers() {
		// 受注クラスファイルの読み込み
		$order_class_file = KTPWP_PLUGIN_DIR . 'includes/class-tab-order.php';

		if ( file_exists( $order_class_file ) ) {
			require_once $order_class_file;

			if ( class_exists( 'Kntan_Order_Class' ) ) {
				// 直接このクラスのメソッドを使用して循環参照を回避
				// 自動保存
				add_action( 'wp_ajax_ktp_auto_save_item', array( $this, 'ajax_auto_save_item' ) );
				add_action( 'wp_ajax_nopriv_ktp_auto_save_item', array( $this, 'ajax_auto_save_item' ) );
				$this->registered_handlers[] = 'ktp_auto_save_item';

				// 新規アイテム作成
				add_action( 'wp_ajax_ktp_create_new_item', array( $this, 'ajax_create_new_item' ) );
				add_action( 'wp_ajax_nopriv_ktp_create_new_item', array( $this, 'ajax_create_new_item' ) );
				$this->registered_handlers[] = 'ktp_create_new_item';

				// アイテム削除
				add_action( 'wp_ajax_ktp_delete_item', array( $this, 'ajax_delete_item' ) );
				add_action( 'wp_ajax_nopriv_ktp_delete_item', array( $this, 'ajax_require_login' ) ); // 非ログインユーザーはエラー
				$this->registered_handlers[] = 'ktp_delete_item';

				// アイテム並び順更新
				add_action( 'wp_ajax_ktp_update_item_order', array( $this, 'ajax_update_item_order' ) );
				add_action( 'wp_ajax_nopriv_ktp_update_item_order', array( $this, 'ajax_require_login' ) ); // 非ログインユーザーはエラー
				$this->registered_handlers[] = 'ktp_update_item_order';
			}
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
			}
		}
	}

	/**
	 * サービス関連Ajaxハンドラー初期化
	 */
	private function init_service_ajax_handlers() {
		// サービス一覧取得
		add_action( 'wp_ajax_ktp_get_service_list', array( $this, 'ajax_get_service_list' ) );
		add_action( 'wp_ajax_nopriv_ktp_get_service_list', array( $this, 'ajax_get_service_list' ) );
		$this->registered_handlers[] = 'ktp_get_service_list';
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
						'settings' => array(
							'delivery_warning_days' => KTP_Settings::get_delivery_warning_days(),
						),
					)
				) . ';'
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
					error_log( "[AJAX_AUTO_SAVE] Nonce verified with field: {$field}" );
					break;
				}
			}
		}

		if ( ! $nonce_verified ) {
			error_log( '[AJAX_AUTO_SAVE] Security check failed - tried fields: ' . implode( ', ', $nonce_fields ) );
			error_log( '[AJAX_AUTO_SAVE] Available POST fields: ' . implode( ', ', array_keys( $_POST ) ) );
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

			if ( $result && is_array($result) && $result['success'] ) {
				wp_send_json_success(
					array(
						'message' => __( '正常に保存されました', 'ktpwp' ),
						'value_changed' => $result['value_changed']
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

			// 新しいアイテムを作成
			$new_item_id = $order_items->create_new_item( $item_type, $order_id );

			// 指定されたフィールド値を設定（アイテム作成後に更新）
			if ( ! empty( $field_name ) && ! empty( $field_value ) && $new_item_id ) {
				$update_result = $order_items->update_item_field( $item_type, $new_item_id, $field_name, $field_value );
				if ( ! $update_result || (is_array($update_result) && !$update_result['success']) ) {
					error_log( "KTPWP: Failed to update field {$field_name} for new item {$new_item_id}" );
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

		// 権限チェック
		if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'ktpwp_access' ) ) {
			error_log( '[AJAX] サービス一覧取得: 権限不足' );
			wp_send_json_error( __( 'この操作を行う権限がありません', 'ktpwp' ) );
			return;
		}

		// デバッグログ
		error_log( '[AJAX] サービス一覧取得開始: ' . print_r( $_POST, true ) );

		// 簡単なnonceチェック
		if ( ! check_ajax_referer( 'ktp_ajax_nonce', 'nonce', false ) ) {
			error_log( '[AJAX] サービス一覧取得: nonce検証失敗' );
			wp_send_json_error( __( 'セキュリティチェックに失敗しました', 'ktpwp' ) );
			return;
		}

		error_log( '[AJAX] サービス一覧取得: nonce検証成功' );

		// パラメータ取得
		$page = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;

		// 一般設定から表示件数を取得
		if ( isset( $_POST['limit'] ) && $_POST['limit'] === 'auto' ) {
			// 一般設定から表示件数を取得（設定クラスが利用可能な場合）
			if ( class_exists( 'KTP_Settings' ) ) {
				$limit = KTP_Settings::get_work_list_range();
				error_log( '[AJAX] 一般設定から表示件数を取得: ' . $limit );
			} else {
				$limit = 20; // フォールバック値
				error_log( '[AJAX] KTP_Settingsクラスなし - フォールバック値を使用: ' . $limit );
			}
		} else {
			$limit = isset( $_POST['limit'] ) ? absint( $_POST['limit'] ) : 20;
			error_log( '[AJAX] POSTパラメータから表示件数を取得: ' . $limit );
		}

		$search   = isset( $_POST['search'] ) ? sanitize_text_field( $_POST['search'] ) : '';
		$category = isset( $_POST['category'] ) ? sanitize_text_field( $_POST['category'] ) : '';

		if ( $limit > 500 ) {
			$limit = 500; // 一般設定の最大値に合わせる
		}

		$offset = ( $page - 1 ) * $limit;

		error_log( '[AJAX] パラメータ取得完了: page=' . $page . ', limit=' . $limit );

		try {
			error_log( '[AJAX] サービスDB処理開始' );

			// サービスDBクラスのインスタンスを取得
			if ( ! class_exists( 'KTPWP_Service_DB' ) ) {
				$service_db_file = KTPWP_PLUGIN_DIR . 'includes/class-ktpwp-service-db.php';
				error_log( '[AJAX] サービスDBファイル: ' . $service_db_file . ' (存在: ' . ( file_exists( $service_db_file ) ? 'Yes' : 'No' ) . ')' );
				require_once $service_db_file;
			}

			if ( ! class_exists( 'KTPWP_Service_DB' ) ) {
				error_log( '[AJAX] KTPWP_Service_DBクラスが見つかりません' );
				wp_send_json_error( __( 'サービスDBクラスが見つかりません', 'ktpwp' ) );
				return;
			}

			$service_db = KTPWP_Service_DB::get_instance();
			if ( ! $service_db ) {
				error_log( '[AJAX] サービスDBインスタンスの取得に失敗' );

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

				error_log( '[AJAX] ダミーデータ送信（DBエラー時）: ' . print_r( $response_data, true ) );
				wp_send_json_success( $response_data );
				return;
			}

			error_log( '[AJAX] 検索パラメータ: page=' . $page . ', limit=' . $limit . ', search=' . $search . ', category=' . $category );

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
			error_log( '[AJAX] サービス一覧取得呼び出し' );
			$services = $service_db->get_services( 'service', $search_args );
			error_log( '[AJAX] サービス一覧取得結果: ' . print_r( $services, true ) );

			$total_services = $service_db->get_services_count( 'service', $search_args );
			error_log( '[AJAX] サービス総数: ' . $total_services );

			if ( $services === null || empty( $services ) ) {
				error_log( '[AJAX] サービス一覧が空のためダミーデータに切り替え' );

				// テスト用のダミーデータを返す
				$dummy_services = array(
					array(
						'id'           => 1,
						'service_name' => 'サンプルサービス1',
						'price'        => 10000,
						'unit'         => '個',
						'category'     => 'カテゴリ1',
					),
					array(
						'id'           => 2,
						'service_name' => 'サンプルサービス2',
						'price'        => 20000,
						'unit'         => '時間',
						'category'     => 'カテゴリ2',
					),
					array(
						'id'           => 3,
						'service_name' => 'サンプルサービス3',
						'price'        => 5000,
						'unit'         => '回',
						'category'     => 'カテゴリ1',
					),
				);

				$services       = $dummy_services;
				$total_services = count( $dummy_services );
			}

			$total_pages = ceil( $total_services / $limit );

			// レスポンスデータ
			$response_data = array(
				'services'   => $services,
				'pagination' => array(
					'current_page'   => $page,
					'total_pages'    => $total_pages,
					'total_items'    => $total_services,
					'items_per_page' => $limit,
				),
			);

			error_log( '[AJAX] サービス一覧取得成功: ' . count( $services ) . '件' );
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

			if ( empty( $my_email ) && $setting ) {
				$my_email = sanitize_email( $setting->email_address );
			}

			// 請求項目リストを取得
			$order_class           = new Kntan_Order_Class();
			$invoice_items_from_db = $order_class->Get_Invoice_Items( $order->id );
			$amount                = 0;
			$invoice_list          = '';

			if ( ! empty( $invoice_items_from_db ) ) {
				$invoice_list = "\n";
				$max_length   = 0;
				$item_lines   = array();

				foreach ( $invoice_items_from_db as $item ) {
					$product_name = isset( $item['product_name'] ) ? sanitize_text_field( $item['product_name'] ) : '';
					$item_amount  = isset( $item['amount'] ) ? floatval( $item['amount'] ) : 0;
					$price        = isset( $item['price'] ) ? floatval( $item['price'] ) : 0;
					$quantity     = isset( $item['quantity'] ) ? floatval( $item['quantity'] ) : 0;
					$unit         = isset( $item['unit'] ) ? sanitize_text_field( $item['unit'] ) : '';
					$amount      += $item_amount;

					if ( ! empty( trim( $product_name ) ) ) {
						$line         = $product_name . '：' . number_format( $price ) . '円 × ' . $quantity . $unit . ' = ' . number_format( $item_amount ) . '円';
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

				$amount_ceiled = ceil( $amount );
				$total_line    = '合計：' . number_format( $amount_ceiled ) . '円';
				$total_length  = mb_strlen( $total_line, 'UTF-8' );

				$line_length = max( $max_length, $total_length );
				if ( $line_length < 30 ) {
					$line_length = 30;
				}
				if ( $line_length > 80 ) {
					$line_length = 80;
				}

				$invoice_list .= str_repeat( '-', $line_length ) . "\n";
				$invoice_list .= $total_line;
			} else {
				$invoice_list = '（請求項目未入力）';
			}

			// 進捗ごとに件名・本文を生成
			$progress      = absint( $order->progress );
			$project_name  = $order->project_name ? sanitize_text_field( $order->project_name ) : '';
			$customer_name = sanitize_text_field( $order->customer_name );
			$user_name     = sanitize_text_field( $order->user_name );

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

			// 日付フォーマット
			$order_date = date( 'Y年m月d日', $order->time );

			// 部署情報を取得
			$department_info = '';
			$customer_display = $customer_name;
			$user_display = $user_name . " 様";
			
			if (class_exists('KTPWP_Department_Manager')) {
				$selected_department = KTPWP_Department_Manager::get_selected_department_by_client($client->id);
				if ($selected_department) {
					// 部署選択がある場合：会社名、部署名、担当者名を別々に表示
					$customer_display = $customer_name;
					$user_display = $selected_department->department_name . "\n" . $selected_department->contact_person . " 様";
				}
			}

			// 件名と本文の統一フォーマット
			$subject = "{$document_title}：{$project_name}";
			$body    = "{$customer_display}\n{$user_display}\n\nお世話になります。\n\n＜{$document_title}＞ ID: {$order->id} [{$order_date}]\n「{$project_name}」{$document_message}\n\n請求項目\n{$invoice_list}\n\n--\n{$my_company}";

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
			wp_send_json_error(
				array(
					'message' => $e->getMessage(),
				)
			);
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
			$supplier = $wpdb->get_row( $wpdb->prepare(
				"SELECT email, name FROM {$table_name} WHERE company_name = %s",
				$supplier_name
			) );

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
				$setting       = $wpdb->get_row( $wpdb->prepare(
					"SELECT * FROM `{$setting_table}` WHERE id = %d",
					1
				) );
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
				$setting       = $wpdb->get_row( $wpdb->prepare(
					"SELECT * FROM `{$setting_table}` WHERE id = %d",
					1
				) );
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

			// 添付ファイルの処理
			$attachments = array();
			$temp_files  = array();

			if ( ! empty( $_FILES['attachments'] ) ) {
				$total_size = 0;
				$max_size   = 50 * 1024 * 1024; // 50MB

				foreach ( $_FILES['attachments']['tmp_name'] as $index => $file_tmp ) {
					if ( empty( $file_tmp ) || ! is_uploaded_file( $file_tmp ) ) {
						continue;
					}

					$file_name = sanitize_file_name( $_FILES['attachments']['name'][ $index ] );
					$file_size = $_FILES['attachments']['size'][ $index ];

					// ファイルサイズチェック
					if ( $file_size > 10 * 1024 * 1024 ) { // 10MB per file
						throw new Exception( "ファイル「{$file_name}」が10MBを超えています。" );
					}

					$total_size += $file_size;
					if ( $total_size > $max_size ) {
						throw new Exception( "添付ファイルの合計サイズが50MBを超えています。" );
					}

					// 一時ディレクトリに保存
					$upload_dir = wp_upload_dir();
					$temp_dir   = $upload_dir['basedir'] . '/ktp-email-temp/';

					if ( ! file_exists( $temp_dir ) ) {
						wp_mkdir_p( $temp_dir );
					}

					$unique_filename = uniqid() . '_' . $file_name;
					$temp_file_path  = $temp_dir . $unique_filename;

					if ( move_uploaded_file( $file_tmp, $temp_file_path ) ) {
						$attachments[] = $temp_file_path;
						$temp_files[]  = $temp_file_path;
					} else {
						throw new Exception( "ファイル「{$file_name}」の保存に失敗しました。" );
					}
				}
			}

			// メール送信
			$sent = wp_mail( $to, $subject, $body, $headers, $attachments );

			// 一時ファイルのクリーンアップ
			foreach ( $temp_files as $temp_file ) {
				if ( file_exists( $temp_file ) ) {
					unlink( $temp_file );
				}
			}

			if ( $sent ) {
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
			error_log( 'KTPWP Ajax send_purchase_order_email Error: ' . $e->getMessage() );
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
				$setting       = $wpdb->get_row( $wpdb->prepare(
					"SELECT * FROM `{$setting_table}` WHERE id = %d",
					1
				) );
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
			$supplier = $wpdb->get_row( $wpdb->prepare(
				"SELECT * FROM `{$supplier_table}` WHERE company_name = %s",
				$supplier_name
			) );

			if ( $supplier ) {
				$contact_info = array(
					'supplier_name' => $supplier->company_name,
					'contact_person' => $supplier->name,
					'email' => $supplier->email,
					'phone' => $supplier->phone,
					'address' => $supplier->address
				);
			} else {
				$contact_info = array(
					'supplier_name' => $supplier_name,
					'contact_person' => '',
					'email' => '',
					'phone' => '',
					'address' => ''
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
}

function ktpwp_ajax_get_invoice_candidates() {
	// デバッグ情報をログに出力
	error_log( 'KTPWP Invoice Debug - AJAX request received' );
	error_log( 'KTPWP Invoice Debug - POST data: ' . print_r( $_POST, true ) );
	error_log( 'KTPWP Invoice Debug - Nonce received: ' . ( isset( $_POST['_wpnonce'] ) ? $_POST['_wpnonce'] : 'not set' ) );

	// Nonce検証
	if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'ktp_get_invoice_candidates' ) ) {
		error_log( 'KTPWP Invoice Debug - Nonce verification failed' );
		wp_send_json_error( array( 'message' => 'Nonceが不正です' ) );
		return;
	}

	error_log( 'KTPWP Invoice Debug - Nonce verification passed' );

	// 権限チェック（管理者または編集権限）
	if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'ktpwp_access' ) ) {
		wp_send_json_error( array( 'message' => '権限がありません' ) );
	}

	global $wpdb;
	$client_id = isset( $_POST['client_id'] ) ? intval( $_POST['client_id'] ) : 0;
	if ( $client_id <= 0 ) {
		wp_send_json_error( array( 'message' => '顧客IDが不正です' ) );
	}

	// デバッグ情報をログに出力
	error_log( 'KTPWP Invoice Debug - Client ID: ' . $client_id );

	$client_table = $wpdb->prefix . 'ktp_client';
	$order_table  = $wpdb->prefix . 'ktp_order';

	// 顧客情報を取得
	$client_info = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT * FROM {$client_table} WHERE id = %d",
			$client_id
		)
	);

	if ( ! $client_info ) {
		error_log( 'KTPWP Invoice Debug - Client info not found for ID: ' . $client_id );
		wp_send_json_error( array( 'message' => '顧客情報が取得できません' ) );
	}

	error_log( 'KTPWP Invoice Debug - Full Client info: ' . json_encode( $client_info ) );

	// 担当者名を複数のフィールドから探す
	$contact_name = '';
	if ( ! empty( trim( $client_info->representative_name ) ) ) {
		$contact_name = $client_info->representative_name;
	} elseif ( ! empty( trim( $client_info->name ) ) ) {
		$contact_name = $client_info->name;
	} else {
		$contact_name = '未設定';
	}

	error_log( 'KTPWP Invoice Debug - Contact name found: ' . $contact_name );

	// 完全な住所を組み立て
	$full_address = '';
	if ( $client_info->postal_code ) {
		$full_address .= '〒' . $client_info->postal_code . ' ';
	}
	if ( $client_info->prefecture ) {
		$full_address .= $client_info->prefecture;
	}
	if ( $client_info->city ) {
		$full_address .= $client_info->city;
	}
	if ( $client_info->address ) {
		$full_address .= $client_info->address;
	}
	if ( $client_info->building ) {
		$full_address .= ' ' . $client_info->building;
	}

	// 住所が空の場合は「未設定」を表示
	if ( empty( trim( $full_address ) ) ) {
		$full_address = '未設定';
	}

	$closing_day = $client_info->closing_day;
	if ( ! $closing_day ) {
		error_log( 'KTPWP Invoice Debug - Closing day not found for client ID: ' . $client_id );
		wp_send_json_error( array( 'message' => '締日が取得できません' ) );
	}

	// 完了日が締日を超えている案件を取得
	$orders = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT id, project_name, completion_date FROM {$order_table} 
         WHERE client_id = %d AND progress = 4 AND completion_date IS NOT NULL 
         ORDER BY completion_date DESC",
			$client_id
		)
	);

	// デバッグ用：取得件数をログ出力
	error_log( 'KTPWP Invoice Candidates - Client ID: ' . $client_id . ', Total completed orders: ' . count( $orders ) );

	if ( empty( $orders ) ) {
		error_log( 'KTPWP Invoice Debug - No completed orders found for client ID: ' . $client_id );
		wp_send_json_success(
			array(
				'monthly_groups' => array(),
				'total_orders'   => 0,
				'total_groups'   => 0,
				'client_address' => $full_address,
				'client_name'    => $client_info->company_name,
				'client_contact' => $contact_name,
				'debug_info'     => array(
					'client_id'              => $client_id,
					'closing_day'            => $closing_day,
					'completed_orders_count' => 0,
					'message'                => '完了案件がありません',
				),
			)
		);
	}

	// 完了月の前月締日で比較し、月別にグループ化
	$monthly_groups  = array();
	$filtered_orders = array();

	foreach ( $orders as $order ) {
		$completion_date  = $order->completion_date;
		$completion_year  = date( 'Y', strtotime( $completion_date ) );
		$completion_month = date( 'm', strtotime( $completion_date ) );

		// 完了月の前月締日を計算
		if ( $client_info->closing_day === '末日' ) {
			$month_closing_date = date( 'Y-m-t', strtotime( $completion_year . '-' . $completion_month . '-01 -1 month' ) );
		} else {
			$closing_day_num    = intval( $client_info->closing_day );
			$month_closing_date = date( 'Y-m-d', strtotime( $completion_year . '-' . $completion_month . '-01 -1 month +' . ( $closing_day_num - 1 ) . ' days' ) );
		}

		// デバッグ用：各案件の比較結果をログ出力
		error_log( 'KTPWP Invoice Candidates - Order ID: ' . $order->id . ', Completion: ' . $completion_date . ', Month Closing: ' . $month_closing_date . ', Is Over: ' . ( $completion_date > $month_closing_date ? 'YES' : 'NO' ) );

		// 完了日が前月締日を超えているかチェック
		if ( $completion_date > $month_closing_date ) {
			$filtered_orders[] = $order;

			// 請求対象月を計算（完了月）
			$billing_year  = $completion_year;
			$billing_month = $completion_month;
			$billing_key   = $billing_year . '-' . $billing_month;

			// 月別グループに追加
			if ( ! isset( $monthly_groups[ $billing_key ] ) ) {
				// 請求対象月の締日を計算
				if ( $client_info->closing_day === '末日' ) {
					$billing_closing_date = date( 'Y-m-t', strtotime( $billing_year . '-' . $billing_month . '-01' ) );
				} else {
					$closing_day_num      = intval( $client_info->closing_day );
					$billing_closing_date = date( 'Y-m-d', strtotime( $billing_year . '-' . $billing_month . '-01 +' . ( $closing_day_num - 1 ) . ' days' ) );
				}

				// お支払い期日を計算
				$today = current_time('Y-m-d');
				$base_date = new DateTime($today);
				$payment_month = $client_info->payment_month;
				$payment_day = $client_info->payment_day;
				// 支払月加算
				if ($payment_month === '今月') {
					// 何もしない
				} elseif ($payment_month === '翌月') {
					$base_date->modify('+1 month');
				} elseif ($payment_month === '翌々月') {
					$base_date->modify('+2 month');
				} else {
					// その他→今月扱い
				}
				// 支払日セット
				if ($payment_day === '末日') {
					$due_date = $base_date->format('Y-m-t');
				} elseif ($payment_day === '即日') {
					$due_date = $today;
				} else {
					// 5日, 10日, ...
					$day_num = intval($payment_day);
					$due_date = $base_date->format('Y-m-') . str_pad($day_num, 2, '0', STR_PAD_LEFT);
					// 月の日数を超える場合は末日に補正
					$last_day = $base_date->format('t');
					if ($day_num > intval($last_day)) {
						$due_date = $base_date->format('Y-m-t');
					}
				}

				$monthly_groups[ $billing_key ] = array(
					'year'           => $billing_year,
					'month'          => $billing_month,
					'billing_period' => $billing_year . '年' . $billing_month . '月分',
					'closing_date'   => $billing_closing_date,
					'payment_due_date' => $due_date,
					'orders'         => array(),
				);
			}

			// 案件の請求項目を取得
			$order_items_table = $wpdb->prefix . 'ktp_order_invoice_items';
			$order_items       = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id, product_name, quantity, price, amount, unit, remarks 
                 FROM {$order_items_table} 
                 WHERE order_id = %d 
                 ORDER BY sort_order ASC, id ASC",
					$order->id
				)
			);

			$order_data = array(
				'id'              => $order->id,
				'project_name'    => $order->project_name,
				'completion_date' => $order->completion_date,
				'items'           => array(),
			);

			// 請求項目を追加
			foreach ( $order_items as $item ) {
				$order_data['items'][] = array(
					'id'          => $item->id,
					'item_name'   => $item->product_name,
					'quantity'    => $item->quantity,
					'unit_price'  => $item->price,
					'total_price' => $item->amount,
					'unit'        => $item->unit,
					'remarks'     => $item->remarks,
				);
			}

			$monthly_groups[ $billing_key ]['orders'][] = $order_data;
		}
	}

	// 月別グループを年月順にソート
	ksort( $monthly_groups );

	// ★最後の締め日を取得
	$last_closing_date = null;
	foreach ( $monthly_groups as $group ) {
		if ( empty( $last_closing_date ) || $group['closing_date'] > $last_closing_date ) {
			$last_closing_date = $group['closing_date'];
		}
	}

	// ★最後の締め日を起点にお支払い期日を再計算
	$payment_due_date = '';
	if ( $last_closing_date ) {
		$base_date = new DateTime( $last_closing_date );
		$payment_month = $client_info->payment_month;
		$payment_day = $client_info->payment_day;
		if ( $payment_month === '今月' ) {
			// 何もしない
		} elseif ( $payment_month === '翌月' ) {
			$base_date->modify( '+1 month' );
		} elseif ( $payment_month === '翌々月' ) {
			$base_date->modify( '+2 month' );
		}
		if ( $payment_day === '末日' ) {
			$payment_due_date = $base_date->format( 'Y-m-t' );
		} elseif ( $payment_day === '即日' ) {
			$payment_due_date = $last_closing_date;
		} else {
			$day_num = intval( $payment_day );
			$due = $base_date->format( 'Y-m-' ) . str_pad( $day_num, 2, '0', STR_PAD_LEFT );
			$last_day = $base_date->format( 't' );
			if ( $day_num > intval( $last_day ) ) {
				$due = $base_date->format( 'Y-m-t' );
			}
			$payment_due_date = $due;
		}
	}

	// monthly_groupsの各グループのpayment_due_dateを上書き
	foreach ( $monthly_groups as &$group ) {
		$group['payment_due_date'] = $payment_due_date;
	}
	unset( $group );

	// 会社情報を取得
	$company_info_html = '';
	if ( class_exists( 'KTP_Settings' ) ) {
		$company_info_html = KTP_Settings::get_company_info();
	}

	// 旧システムからも取得（後方互換性）
	if ( empty( $company_info_html ) ) {
		$setting_table = $wpdb->prefix . 'ktp_setting';
		$setting       = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM `{$setting_table}` WHERE id = %d",
				1
			)
		);
		if ( $setting && ! empty( $setting->my_company_content ) ) {
			$company_info_html = sanitize_text_field( strip_tags( $setting->my_company_content ) );
		}
	}

	// デフォルト値を設定
	if ( empty( $company_info_html ) ) {
		$company_info_html  = '<div style="font-size: 18px; font-weight: bold; margin-bottom: 10px;">株式会社サンプル</div>';
		$company_info_html .= '<div style="margin-bottom: 5px;">〒000-0000 東京都港区サンプル1-1-1</div>';
		$company_info_html .= '<div style="margin-bottom: 5px;">TEL: 03-0000-0000</div>';
		$company_info_html .= '<div>info@sample.com</div>';
	} else {
		// HTMLタグが含まれている場合はそのまま使用、プレーンテキストの場合は改行をHTMLに変換
		if ( strip_tags( $company_info_html ) === $company_info_html ) {
			// プレーンテキストの場合
			$company_info_html = nl2br( esc_html( $company_info_html ) );
		}
		// HTMLタグが含まれている場合はそのまま使用（エスケープしない）
	}

	// 選択された部署情報を取得
	$selected_department = null;
	if (class_exists('KTPWP_Department_Manager')) {
		$selected_department = KTPWP_Department_Manager::get_selected_department_by_client($client_id);
	}

	// 部署選択がある場合の宛先表示を修正
	$client_address_formatted = $full_address;
	$client_contact_formatted = $contact_name;
	
	if ($selected_department) {
		// 部署選択がある場合：会社名、部署名、担当者名を別々に表示
		$client_address_formatted = $client_info->company_name;
		$client_contact_formatted = $selected_department->department_name . "\n" . $selected_department->contact_person . " 様";
	} else {
		// 部署選択がない場合：現行のまま
		// 住所情報が設定されていない場合は「未設定」は表示しない
		if (empty(trim($full_address))) {
			$client_address_formatted = $client_info->company_name;
		}
	}
	
	$result = array(
		'monthly_groups' => array_values( $monthly_groups ),
		'total_orders'   => count( $filtered_orders ),
		'total_groups'   => count( $monthly_groups ),
		'client_address' => $client_address_formatted,
		'client_name'    => $client_info->company_name,
		'client_contact' => $client_contact_formatted,
		'selected_department' => $selected_department ? array(
			'department_name' => $selected_department->department_name,
			'contact_person' => $selected_department->contact_person,
			'email' => $selected_department->email
		) : null,
		'company_info'   => $company_info_html,  // 会社情報を追加
		'debug_info'     => array(
			'client_id'              => $client_id,
			'closing_day'            => $closing_day,
			'completed_orders_count' => count( $orders ),
			'filtered_orders_count'  => count( $filtered_orders ),
			'monthly_groups_count'   => count( $monthly_groups ),
		),
	);

	error_log( 'KTPWP Invoice Debug - Final result: ' . json_encode( $result ) );
	wp_send_json_success( $result );
}

/**
 * 部署選択状態を更新するAjaxハンドラー
 */
function ktpwp_ajax_update_department_selection() {
	// デバッグログ開始
	if (defined('WP_DEBUG') && WP_DEBUG) {
		error_log("KTPWP AJAX: ktpwp_ajax_update_department_selection called");
		error_log("KTPWP AJAX: POST data: " . json_encode($_POST));
	}
	
	// 権限チェック
	if (!current_user_can('edit_posts') && !current_user_can('ktpwp_access')) {
		if (defined('WP_DEBUG') && WP_DEBUG) {
			error_log("KTPWP AJAX: Permission denied");
		}
		wp_send_json_error(array('message' => '権限がありません'));
	}

	// nonce検証
	if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ktp_department_nonce')) {
		if (defined('WP_DEBUG') && WP_DEBUG) {
			error_log("KTPWP AJAX: Nonce verification failed");
		}
		wp_send_json_error(array('message' => 'セキュリティチェックに失敗しました'));
	}

	$department_id = isset($_POST['department_id']) ? intval($_POST['department_id']) : 0;
	$is_selected = isset($_POST['is_selected']) ? ($_POST['is_selected'] === 'true' || $_POST['is_selected'] === true) : false;

	if (defined('WP_DEBUG') && WP_DEBUG) {
		error_log("KTPWP AJAX: department_id: {$department_id}, is_selected: " . ($is_selected ? 'true' : 'false'));
	}

	if (empty($department_id)) {
		if (defined('WP_DEBUG') && WP_DEBUG) {
			error_log("KTPWP AJAX: department_id is empty");
		}
		wp_send_json_error(array('message' => '部署IDが指定されていません'));
	}

	if (class_exists('KTPWP_Department_Manager')) {
		if (defined('WP_DEBUG') && WP_DEBUG) {
			error_log("KTPWP AJAX: Calling KTPWP_Department_Manager::update_department_selection");
		}
		
		$result = KTPWP_Department_Manager::update_department_selection($department_id, $is_selected);
		
		if ($result) {
			if (defined('WP_DEBUG') && WP_DEBUG) {
				error_log("KTPWP AJAX: update_department_selection successful");
			}
			wp_send_json_success(array('message' => '部署選択状態を更新しました'));
		} else {
			if (defined('WP_DEBUG') && WP_DEBUG) {
				error_log("KTPWP AJAX: update_department_selection failed");
			}
			wp_send_json_error(array('message' => '部署選択状態の更新に失敗しました'));
		}
	} else {
		if (defined('WP_DEBUG') && WP_DEBUG) {
			error_log("KTPWP AJAX: KTPWP_Department_Manager class not found");
		}
		wp_send_json_error(array('message' => '部署管理クラスが見つかりません'));
	}
}

/**
 * 部署を追加するAjaxハンドラー
 */
function ktpwp_ajax_add_department() {
	// 権限チェック
	if (!current_user_can('edit_posts') && !current_user_can('ktpwp_access')) {
		wp_send_json_error(array('message' => '権限がありません'));
	}

	// nonce検証
	if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ktp_department_nonce')) {
		wp_send_json_error(array('message' => 'セキュリティチェックに失敗しました'));
	}

	$client_id = isset($_POST['client_id']) ? intval($_POST['client_id']) : 0;
	$department_name = isset($_POST['department_name']) ? sanitize_text_field($_POST['department_name']) : '';
	$contact_person = isset($_POST['contact_person']) ? sanitize_text_field($_POST['contact_person']) : '';
	$email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';

	if (empty($client_id) || empty($contact_person) || empty($email)) {
		wp_send_json_error(array('message' => '担当者名とメールアドレスを入力してください'));
	}

	if (class_exists('KTPWP_Department_Manager')) {
		$result = KTPWP_Department_Manager::add_department($client_id, $department_name, $contact_person, $email);
		
		if ($result) {
			wp_send_json_success(array('message' => '部署を追加しました'));
		} else {
			wp_send_json_error(array('message' => '部署の追加に失敗しました'));
		}
	} else {
		wp_send_json_error(array('message' => '部署管理クラスが見つかりません'));
	}
}

/**
 * 部署を削除するAjaxハンドラー
 */
function ktpwp_ajax_delete_department() {
	// 権限チェック
	if (!current_user_can('edit_posts') && !current_user_can('ktpwp_access')) {
		wp_send_json_error(array('message' => '権限がありません'));
	}

	// nonce検証
	if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ktp_department_nonce')) {
		wp_send_json_error(array('message' => 'セキュリティチェックに失敗しました'));
	}

	$department_id = isset($_POST['department_id']) ? intval($_POST['department_id']) : 0;

	if (empty($department_id)) {
		wp_send_json_error(array('message' => '部署IDが指定されていません'));
	}

	if (class_exists('KTPWP_Department_Manager')) {
		$result = KTPWP_Department_Manager::delete_department($department_id);
		
		if ($result) {
			wp_send_json_success(array('message' => '部署を削除しました'));
		} else {
			wp_send_json_error(array('message' => '部署の削除に失敗しました'));
		}
	} else {
		wp_send_json_error(array('message' => '部署管理クラスが見つかりません'));
	}
}
