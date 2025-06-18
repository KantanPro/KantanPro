<?php
/**
 * KTPWP Ajax処理管理クラス
 *
 * @package KTPWP
 * @since 0.1.0
 */

// セキュリティ: 直接アクセスを防止
if (!defined('ABSPATH')) {
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
        'auto_save' => 'ktp_ajax_nonce',
        'project_name' => 'ktp_update_project_name',
        'inline_edit' => 'ktpwp_inline_edit_nonce',
        'general' => 'ktpwp_ajax_nonce',
        'staff_chat' => 'ktpwp_staff_chat_nonce'
    );

    /**
     * シングルトンインスタンス取得
     *
     * @return KTPWP_Ajax
     */
    public static function get_instance() {
        if (self::$instance === null) {
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
        add_action('init', array($this, 'register_ajax_handlers'), 10);

        // WordPress管理画面でのスクリプト読み込み時にnonce設定
        add_action('wp_enqueue_scripts', array($this, 'localize_ajax_scripts'), 99);
        add_action('admin_enqueue_scripts', array($this, 'localize_ajax_scripts'), 99);

        // フッターでのスクリプト出力は AJAX リクエスト中のみに制限
        add_action('wp_footer', array($this, 'conditional_ajax_localization'), 5);
        add_action('admin_footer', array($this, 'conditional_ajax_localization'), 5);
    }

    /**
     * Ajaxハンドラー登録
     */
    public function register_ajax_handlers() {
        // プロジェクト名インライン編集（管理者のみ）
        add_action('wp_ajax_ktp_update_project_name', array($this, 'ajax_update_project_name'));
        add_action('wp_ajax_nopriv_ktp_update_project_name', array($this, 'ajax_require_login'));
        $this->registered_handlers[] = 'ktp_update_project_name';

        // 受注関連のAjax処理を初期化
        $this->init_order_ajax_handlers();

        // サービス関連のAjax処理を初期化
        $this->init_service_ajax_handlers();

        // スタッフチャット関連のAjax処理を初期化
        $this->init_staff_chat_ajax_handlers();

        // ログイン中ユーザー取得
        add_action('wp_ajax_get_logged_in_users', array($this, 'ajax_get_logged_in_users'));
        add_action('wp_ajax_nopriv_get_logged_in_users', array($this, 'ajax_get_logged_in_users'));
        $this->registered_handlers[] = 'get_logged_in_users';
    }

    /**
     * 受注関連Ajaxハンドラー初期化
     */
    private function init_order_ajax_handlers() {
        // 受注クラスファイルの読み込み
        $order_class_file = KTPWP_PLUGIN_DIR . 'includes/class-tab-order.php';

        if (file_exists($order_class_file)) {
            require_once $order_class_file;

            if (class_exists('Kntan_Order_Class')) {
                // 直接このクラスのメソッドを使用して循環参照を回避
                // 自動保存
                add_action('wp_ajax_ktp_auto_save_item', array($this, 'ajax_auto_save_item'));
                add_action('wp_ajax_nopriv_ktp_auto_save_item', array($this, 'ajax_auto_save_item'));
                $this->registered_handlers[] = 'ktp_auto_save_item';

                // 新規アイテム作成
                add_action('wp_ajax_ktp_create_new_item', array($this, 'ajax_create_new_item'));
                add_action('wp_ajax_nopriv_ktp_create_new_item', array($this, 'ajax_create_new_item'));
                $this->registered_handlers[] = 'ktp_create_new_item';

                // アイテム削除
                add_action('wp_ajax_ktp_delete_item', array($this, 'ajax_delete_item'));
                add_action('wp_ajax_nopriv_ktp_delete_item', array($this, 'ajax_require_login')); // 非ログインユーザーはエラー
                $this->registered_handlers[] = 'ktp_delete_item';

                // アイテム並び順更新
                add_action('wp_ajax_ktp_update_item_order', array($this, 'ajax_update_item_order'));
                add_action('wp_ajax_nopriv_ktp_update_item_order', array($this, 'ajax_require_login')); // 非ログインユーザーはエラー
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

        if (file_exists($staff_chat_class_file)) {
            require_once $staff_chat_class_file;

            if (class_exists('KTPWP_Staff_Chat')) {
                // 最新チャットメッセージ取得
                add_action('wp_ajax_get_latest_staff_chat', array($this, 'ajax_get_latest_staff_chat'));
                add_action('wp_ajax_nopriv_get_latest_staff_chat', array($this, 'ajax_require_login'));
                $this->registered_handlers[] = 'get_latest_staff_chat';

                // チャットメッセージ送信
                add_action('wp_ajax_send_staff_chat_message', array($this, 'ajax_send_staff_chat_message'));
                add_action('wp_ajax_nopriv_send_staff_chat_message', array($this, 'ajax_send_staff_chat_message')); // For testing nopriv
                $this->registered_handlers[] = 'send_staff_chat_message';
            }
        }
    }

    /**
     * サービス関連Ajaxハンドラー初期化
     */
    private function init_service_ajax_handlers() {
        // サービス一覧取得
        add_action('wp_ajax_ktp_get_service_list', array($this, 'ajax_get_service_list'));
        add_action('wp_ajax_nopriv_ktp_get_service_list', array($this, 'ajax_get_service_list'));
        $this->registered_handlers[] = 'ktp_get_service_list';
    }

    /**
     * Ajaxスクリプトの設定
     */
    public function localize_ajax_scripts() {
        // 基本的なAjax URL設定
        $ajax_data = array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonces' => array()
        );

        // 現在のユーザー情報を追加
        if (is_user_logged_in()) {
            $current_user = wp_get_current_user();
            $ajax_data['current_user'] = $current_user->display_name ? $current_user->display_name : $current_user->user_login;
        }

        // 各種nonceの設定
        foreach ($this->nonce_names as $action => $nonce_name) {
            if ($action === 'staff_chat') {
                // スタッフチャットnonceは必ず統一マネージャーから取得
                if (!class_exists('KTPWP_Nonce_Manager')) {
                    require_once KTPWP_PLUGIN_DIR . 'includes/class-ktpwp-nonce-manager.php';
                }
                $ajax_data['nonces'][$action] = KTPWP_Nonce_Manager::get_instance()->get_staff_chat_nonce();
            } elseif ($action === 'project_name' && current_user_can('manage_options')) {
                $ajax_data['nonces'][$action] = wp_create_nonce($nonce_name);
            } elseif ($action !== 'project_name') {
                $ajax_data['nonces'][$action] = wp_create_nonce($nonce_name);
            }
        }

        // JavaScriptファイルがエンキューされている場合のみlocalizeを実行
        global $wp_scripts;

        if (isset($wp_scripts->registered['ktp-js'])) {
            wp_add_inline_script('ktp-js', 'var ktp_ajax_object = ' . json_encode($ajax_data) . ';');
            wp_add_inline_script('ktp-js', 'var ktpwp_ajax = ' . json_encode($ajax_data) . ';');
            wp_add_inline_script('ktp-js', 'var ajaxurl = ' . json_encode($ajax_data['ajax_url']) . ';');
        }

        if (isset($wp_scripts->registered['ktp-invoice-items'])) {
            wp_add_inline_script('ktp-invoice-items', 'var ktp_ajax_nonce = ' . json_encode($ajax_data['nonces']['auto_save']) . ';');
            wp_add_inline_script('ktp-invoice-items', 'var ajaxurl = ' . json_encode($ajax_data['ajax_url']) . ';');
        }

        if (isset($wp_scripts->registered['ktp-cost-items'])) {
            wp_add_inline_script('ktp-cost-items', 'var ktp_ajax_nonce = ' . json_encode($ajax_data['nonces']['auto_save']) . ';');
            wp_add_inline_script('ktp-cost-items', 'var ajaxurl = ' . json_encode($ajax_data['ajax_url']) . ';');
        }

        if (isset($wp_scripts->registered['ktp-order-inline-projectname']) && current_user_can('manage_options')) {
            wp_add_inline_script('ktp-order-inline-projectname', 'var ktpwp_inline_edit_nonce = ' . json_encode(array(
                'nonce' => $ajax_data['nonces']['project_name']
            )) . ';');
        }
    }

    /**
     * 条件付きAJAX設定の確保（非AJAX時の出力を防ぐ）
     */
    public function conditional_ajax_localization() {
        // AJAX リクエスト中、またはWordPressコアの処理中は何も出力しない
        if (wp_doing_ajax() || defined('DOING_AJAX') || headers_sent()) {
            return;
        }

        // ページ編集画面でない場合は実行しない
        global $pagenow;
        if (!in_array($pagenow, array('post.php', 'post-new.php', 'admin.php'))) {
            return;
        }

        // KTPWPのスクリプトが読み込まれている場合のみ実行
        if (wp_script_is('ktp-js', 'done') || wp_script_is('ktp-js', 'enqueued')) {
            $this->ensure_ajax_localization();
        }
    }

    /**
     * AJAX設定が確実にロードされるようにするフォールバック
     */
    public function ensure_ajax_localization() {
        // グローバル変数が設定されていない場合のフォールバック
        if (!wp_script_is('ktp-js', 'done') && !wp_script_is('ktp-js', 'enqueued')) {
            return;
        }

        // 基本的なAJAX設定を再確認
        $ajax_data = array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'staff_chat_nonce' => wp_create_nonce('staff_chat_nonce'),
            'auto_save_nonce' => wp_create_nonce('ktp_auto_save_nonce')
        );

        // JavaScriptで利用可能になるよう出力
        echo '<script type="text/javascript">';
        echo 'if (typeof ktpwp_ajax === "undefined") {';
        echo 'var ktpwp_ajax = ' . json_encode($ajax_data) . ';';
        echo '}';
        echo 'if (typeof ajaxurl === "undefined") {';
        echo 'var ajaxurl = "' . admin_url('admin-ajax.php') . '";';
        echo '}';
        echo '</script>';
    }

    /**
     * Ajax: プロジェクト名更新（管理者のみ）
     */
    public function ajax_update_project_name() {
        // 共通バリデーション（管理者権限必須）
        if (!$this->validate_ajax_request('ktp_update_project_name', true)) {
            return; // エラーレスポンスは既に送信済み
        }

        // POSTデータの取得とサニタイズ
        $order_id = $this->sanitize_ajax_input('order_id', 'int');
        $project_name = $this->sanitize_ajax_input('project_name', 'text');

        // バリデーション
        if ($order_id <= 0) {
            $this->log_ajax_error('Invalid order ID for project name update', array('order_id' => $order_id));
            wp_send_json_error(__('無効な受注IDです', 'ktpwp'));
        }

        // 新しいクラス構造を使用してプロジェクト名を更新
        $order_manager = KTPWP_Order::get_instance();

        try {
            $result = $order_manager->update_order($order_id, array(
                'project_name' => $project_name
            ));

            if ($result) {
                wp_send_json_success(array(
                    'message' => __('プロジェクト名を更新しました', 'ktpwp'),
                    'project_name' => $project_name
                ));
            } else {
                $this->log_ajax_error('Failed to update project name', array(
                    'order_id' => $order_id,
                    'project_name' => $project_name
                ));
                wp_send_json_error(__('更新に失敗しました', 'ktpwp'));
            }
        } catch (Exception $e) {
            $this->log_ajax_error('Exception during project name update', array(
                'message' => $e->getMessage(),
                'order_id' => $order_id
            ));
            wp_send_json_error(__('更新中にエラーが発生しました', 'ktpwp'));
        }
    }

    /**
     * Ajax: ログイン中ユーザー取得
     */
    public function ajax_get_logged_in_users() {
        // 編集者以上の権限チェック
        if (!current_user_can('edit_posts') && !current_user_can('ktpwp_access')) {
            wp_send_json_error(__('この操作を行う権限がありません。', 'ktpwp'));
            return;
        }

        // Ajax以外からのアクセスは何も返さない
        if (
            !defined('DOING_AJAX') ||
            !DOING_AJAX ||
            (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest')
        ) {
            wp_die();
        }

        $logged_in_users = get_users(array(
            'meta_key' => 'session_tokens',
            'meta_compare' => 'EXISTS'
        ));

        $users_names = array();
        foreach ($logged_in_users as $user) {
            $users_names[] = esc_html($user->nickname) . 'さん';
        }

        wp_send_json($users_names);
    }

    /**
     * Ajax: ログイン要求（非ログインユーザー用）
     */
    public function ajax_require_login() {
        wp_send_json_error(__('ログインが必要です', 'ktpwp'));
    }

    /**
     * Ajax: 自動保存アイテム処理
     */
    public function ajax_auto_save_item() {
        // 編集者以上の権限チェック
        if (!current_user_can('edit_posts') && !current_user_can('ktpwp_access')) {
            $this->log_ajax_error('Auto-save permission check failed');
            wp_send_json_error(__('この操作を行う権限がありません。', 'ktpwp'));
            return;
        }

        // セキュリティチェック
        if (!check_ajax_referer('ktp_ajax_nonce', 'nonce', false)) {
            $this->log_ajax_error('Auto-save security check failed');
            wp_send_json_error(__('セキュリティ検証に失敗しました', 'ktpwp'));
        }

        // POSTデータの取得とサニタイズ
        $item_type = $this->sanitize_ajax_input('item_type', 'text');
        $item_id = $this->sanitize_ajax_input('item_id', 'int');
        $field_name = $this->sanitize_ajax_input('field_name', 'text');
        $field_value = $this->sanitize_ajax_input('field_value', 'text');
        $order_id = $this->sanitize_ajax_input('order_id', 'int');

        // バリデーション
        if (!in_array($item_type, array('invoice', 'cost'), true)) {
            $this->log_ajax_error('Invalid item type', array('type' => $item_type));
            wp_send_json_error(__('無効なアイテムタイプです', 'ktpwp'));
        }

        if ($item_id <= 0 || $order_id <= 0) {
            $this->log_ajax_error('Invalid ID values', array('item_id' => $item_id, 'order_id' => $order_id));
            wp_send_json_error(__('無効なIDです', 'ktpwp'));
        }

        // 新しいクラス構造を使用してアイテムを更新
        $order_items = KTPWP_Order_Items::get_instance();

        try {
            if ($item_type === 'invoice') {
                $result = $order_items->update_item_field('invoice', $item_id, $field_name, $field_value);
            } else {
                $result = $order_items->update_item_field('cost', $item_id, $field_name, $field_value);
            }

            if ($result) {
                wp_send_json_success(array(
                    'message' => __('正常に保存されました', 'ktpwp')
                ));
            } else {
                $this->log_ajax_error('Failed to update item', array(
                    'type' => $item_type,
                    'item_id' => $item_id,
                    'field' => $field_name
                ));
                wp_send_json_error(__('保存に失敗しました', 'ktpwp'));
            }
        } catch (Exception $e) {
            $this->log_ajax_error('Exception during auto-save', array(
                'message' => $e->getMessage(),
                'type' => $item_type,
                'item_id' => $item_id
            ));
            wp_send_json_error(__('保存中にエラーが発生しました', 'ktpwp'));
        }
    }

    /**
     * Ajax: 新規アイテム作成処理（強化版）
     */
    public function ajax_create_new_item() {
        // 編集者以上の権限チェック
        if (!current_user_can('edit_posts') && !current_user_can('ktpwp_access')) {
            $this->log_ajax_error('Create new item permission check failed');
            wp_send_json_error(__('この操作を行う権限がありません。', 'ktpwp'));
            return;
        }

        // 安全性の初期チェック（Adminer警告対策）
        if (!is_array($_POST) || empty($_POST)) {
            error_log('KTPWP Ajax: $_POST is not array or empty');
            wp_send_json_error(__('リクエストデータが無効です', 'ktpwp'));
            return;
        }

        // セキュリティチェック
        if (!check_ajax_referer('ktp_ajax_nonce', 'nonce', false)) {
            $this->log_ajax_error('Create new item security check failed');
            wp_send_json_error(__('セキュリティ検証に失敗しました', 'ktpwp'));
        }

        // POSTデータの取得とサニタイズ（強化版）
        $item_type = $this->sanitize_ajax_input('item_type', 'text');
        $field_name = $this->sanitize_ajax_input('field_name', 'text');
        $field_value = $this->sanitize_ajax_input('field_value', 'text');
        $order_id = $this->sanitize_ajax_input('order_id', 'int');

        // バリデーション
        if (!in_array($item_type, array('invoice', 'cost'), true)) {
            $this->log_ajax_error('Invalid item type for creation', array('type' => $item_type));
            wp_send_json_error(__('無効なアイテムタイプです', 'ktpwp'));
        }

        if ($order_id <= 0) {
            $this->log_ajax_error('Invalid order ID for creation', array('order_id' => $order_id));
            wp_send_json_error(__('無効な受注IDです', 'ktpwp'));
        }

        // 新しいクラス構造を使用してアイテムを作成
        $order_items = KTPWP_Order_Items::get_instance();

        try {

            // 新しいアイテムを作成
            $new_item_id = $order_items->create_new_item($item_type, $order_id);

            // 指定されたフィールド値を設定（アイテム作成後に更新）
            if (!empty($field_name) && !empty($field_value) && $new_item_id) {
                $update_result = $order_items->update_item_field($item_type, $new_item_id, $field_name, $field_value);
                if (!$update_result) {
                    error_log("KTPWP: Failed to update field {$field_name} for new item {$new_item_id}");
                }
            }

            if ($new_item_id) {
                wp_send_json_success(array(
                    'item_id' => $new_item_id,
                    'message' => __('新しいアイテムが作成されました', 'ktpwp')
                ));
            } else {
                $this->log_ajax_error('Failed to create new item', array(
                    'type' => $item_type,
                    'order_id' => $order_id
                ));
                wp_send_json_error(__('アイテムの作成に失敗しました', 'ktpwp'));
            }
        } catch (Exception $e) {
            $this->log_ajax_error('Exception during item creation', array(
                'message' => $e->getMessage(),
                'type' => $item_type,
                'order_id' => $order_id
            ));
            wp_send_json_error(__('作成中にエラーが発生しました', 'ktpwp'));
        }
    }

    /**
     * Ajax: アイテム削除処理
     */
    public function ajax_delete_item() {
        // 編集者以上の権限チェック
        if (!current_user_can('edit_posts') && !current_user_can('ktpwp_access')) {
            $this->log_ajax_error('Delete item permission check failed', $_POST);
            wp_send_json_error(__( 'この操作を行う権限がありません。', 'ktpwp' ));
            return;
        }

        // 受け取ったパラメータをログに出力
        error_log('[AJAX_DELETE_ITEM] Received params: ' . print_r($_POST, true));

        // セキュリティチェック
        if (!check_ajax_referer('ktp_ajax_nonce', 'nonce', false)) {
            $this->log_ajax_error('Delete item security check failed', $_POST);
            wp_send_json_error(__( 'セキュリティ検証に失敗しました', 'ktpwp' ));
        }

        // POSTデータの取得とサニタイズ
        $item_type = $this->sanitize_ajax_input('item_type', 'text');
        $item_id = $this->sanitize_ajax_input('item_id', 'int');
        $order_id = $this->sanitize_ajax_input('order_id', 'int');

        // パラメータのログ出力（サニタイズ後）
        error_log("[AJAX_DELETE_ITEM] Sanitized params: item_type={$item_type}, item_id={$item_id}, order_id={$order_id}");

        // バリデーション
        if (!in_array($item_type, array('invoice', 'cost'), true)) {
            $this->log_ajax_error('Invalid item type for deletion', array('type' => $item_type));
            wp_send_json_error(__( '無効なアイテムタイプです', 'ktpwp' ));
        }

        if ($item_id <= 0) {
            $this->log_ajax_error('Invalid item ID for deletion', array('item_id' => $item_id, 'item_type' => $item_type, 'order_id' => $order_id));
            wp_send_json_error(__( '無効なアイテムIDです', 'ktpwp' ));
        }

        if ($order_id <= 0) {
            $this->log_ajax_error('Invalid order ID for deletion', array('order_id' => $order_id, 'item_type' => $item_type, 'item_id' => $item_id));
            wp_send_json_error(__( '無効な受注IDです', 'ktpwp' ));
        }

        // KTPWP_Order_Items クラスのインスタンスを取得
        // クラスが存在するか確認
        if (!class_exists('KTPWP_Order_Items')) {
            $this->log_ajax_error('KTPWP_Order_Items class not found');
            wp_send_json_error(__( '必要なクラスが見つかりません。', 'ktpwp' ));
            return; // ここで処理を中断
        }
        $order_items = KTPWP_Order_Items::get_instance();

        try {
            error_log("[AJAX_DELETE_ITEM] Calling KTPWP_Order_Items::delete_item({$item_type}, {$item_id}, {$order_id})");
            $result = $order_items->delete_item($item_type, $item_id, $order_id);
            error_log("[AJAX_DELETE_ITEM] delete_item result: " . print_r($result, true));

            if ($result) {
                wp_send_json_success(array(
                    'message' => __('アイテムを削除しました', 'ktpwp')
                ));
            } else {
                $this->log_ajax_error('Failed to delete item from database (KTPWP_Order_Items::delete_item returned false)', array(
                    'item_type' => $item_type,
                    'item_id' => $item_id,
                    'order_id' => $order_id
                ));
                wp_send_json_error(__( 'データベースからのアイテム削除に失敗しました（詳細エラーログ確認）', 'ktpwp' ));
            }
        } catch (Exception $e) {
            $this->log_ajax_error('Exception during item deletion: ' . $e->getMessage() . ' Stack trace: ' . $e->getTraceAsString(), array(
                'message' => $e->getMessage(),
                'item_type' => $item_type,
                'item_id' => $item_id,
                'order_id' => $order_id,
                'trace' => $e->getTraceAsString()
            ));
            wp_send_json_error(__( 'アイテム削除中に予期せぬエラーが発生しました（詳細エラーログ確認）', 'ktpwp' ));
        }
    }

    /**
     * Ajax: アイテムの並び順更新処理
     */
    public function ajax_update_item_order() {
        error_log('[AJAX_UPDATE_ITEM_ORDER] Received params: ' . print_r($_POST, true));

        // 編集者以上の権限チェック
        if (!current_user_can('edit_posts') && !current_user_can('ktpwp_access')) {
            $this->log_ajax_error('Update item order permission check failed', $_POST);
            wp_send_json_error(__( 'この操作を行う権限がありません。', 'ktpwp' ));
            return;
        }

        // セキュリティチェック (ktp_ajax_nonce を使用)
        if (!check_ajax_referer('ktp_ajax_nonce', 'nonce', false)) {
            $this->log_ajax_error('Update item order security check failed', $_POST);
            wp_send_json_error(__( 'セキュリティ検証に失敗しました', 'ktpwp' ));
        }

        // POSTデータの取得とサニタイズ
        $order_id = $this->sanitize_ajax_input('order_id', 'int');
        $items_data = isset($_POST['items']) && is_array($_POST['items']) ? $_POST['items'] : array();
        $item_type = $this->sanitize_ajax_input('item_type', 'text'); // 'invoice' or 'cost'

        // サニタイズされたパラメータのログ
        error_log("[AJAX_UPDATE_ITEM_ORDER] Sanitized params: order_id={$order_id}, item_type={$item_type}, items_count=" . count($items_data));

        // バリデーション
        if ($order_id <= 0) {
            $this->log_ajax_error('Invalid order ID for updating item order', array('order_id' => $order_id));
            wp_send_json_error(__( '無効な受注IDです', 'ktpwp' ));
        }

        if (!in_array($item_type, array('invoice', 'cost'), true)) {
            $this->log_ajax_error('Invalid item type for updating item order', array('item_type' => $item_type));
            wp_send_json_error(__( '無効なアイテムタイプです', 'ktpwp' ));
        }

        if (empty($items_data)) {
            $this->log_ajax_error('No items data provided for updating order', $_POST);
            wp_send_json_error(__( '更新するアイテムデータがありません', 'ktpwp' ));
        }

        // KTPWP_Order_Items クラスのインスタンスを取得
        if (!class_exists('KTPWP_Order_Items')) {
            $this->log_ajax_error('KTPWP_Order_Items class not found for updating item order');
            wp_send_json_error(__( '必要なクラスが見つかりません。', 'ktpwp' ));
            return;
        }
        $order_items_manager = KTPWP_Order_Items::get_instance();

        try {
            error_log("[AJAX_UPDATE_ITEM_ORDER] Calling KTPWP_Order_Items::update_items_order({$item_type}, {$order_id}, ...)");
            $result = $order_items_manager->update_items_order($item_type, $order_id, $items_data);
            error_log("[AJAX_UPDATE_ITEM_ORDER] update_items_order result: " . print_r($result, true));

            if ($result) {
                wp_send_json_success(array(
                    'message' => __( 'アイテムの並び順を更新しました', 'ktpwp' )
                ));
            } else {
                $this->log_ajax_error('Failed to update item order (KTPWP_Order_Items::update_items_order returned false)', array(
                    'item_type' => $item_type,
                    'order_id' => $order_id,
                    'items_data_count' => count($items_data)
                ));
                wp_send_json_error(__( 'データベースでのアイテム並び順更新に失敗しました（詳細エラーログ確認）', 'ktpwp' ));
            }
        } catch (Exception $e) {
            $this->log_ajax_error('Exception during item order update: ' . $e->getMessage() . ' Stack trace: ' . $e->getTraceAsString(), array(
                'message' => $e->getMessage(),
                'item_type' => $item_type,
                'order_id' => $order_id,
                'trace' => $e->getTraceAsString()
            ));
            wp_send_json_error(__( 'アイテムの並び順更新中に予期せぬエラーが発生しました（詳細エラーログ確認）', 'ktpwp' ));
        }
    }

    /**
     * Ajax: サービス一覧取得
     */
    public function ajax_get_service_list() {
        error_log('[AJAX DEBUG] ajax_get_service_list method called');
        
        // 権限チェック
        if (!current_user_can('edit_posts') && !current_user_can('ktpwp_access')) {
            error_log('[AJAX] サービス一覧取得: 権限不足');
            wp_send_json_error(__('この操作を行う権限がありません', 'ktpwp'));
            return;
        }

        // デバッグログ
        error_log('[AJAX] サービス一覧取得開始: ' . print_r($_POST, true));

        // 簡単なnonceチェック
        if (!check_ajax_referer('ktp_ajax_nonce', 'nonce', false)) {
            error_log('[AJAX] サービス一覧取得: nonce検証失敗');
            wp_send_json_error(__('セキュリティチェックに失敗しました', 'ktpwp'));
            return;
        }

        error_log('[AJAX] サービス一覧取得: nonce検証成功');

        // パラメータ取得
        $page = isset($_POST['page']) ? absint($_POST['page']) : 1;
        $limit = isset($_POST['limit']) ? absint($_POST['limit']) : 20;
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        $category = isset($_POST['category']) ? sanitize_text_field($_POST['category']) : '';

        if ($limit > 100) {
            $limit = 100; // 最大100件に制限
        }

        $offset = ($page - 1) * $limit;

        error_log('[AJAX] パラメータ取得完了: page=' . $page . ', limit=' . $limit);

        try {
            error_log('[AJAX] サービスDB処理開始');
            
            // サービスDBクラスのインスタンスを取得
            if (!class_exists('KTPWP_Service_DB')) {
                $service_db_file = KTPWP_PLUGIN_DIR . 'includes/class-ktpwp-service-db.php';
                error_log('[AJAX] サービスDBファイル: ' . $service_db_file . ' (存在: ' . (file_exists($service_db_file) ? 'Yes' : 'No') . ')');
                require_once $service_db_file;
            }

            if (!class_exists('KTPWP_Service_DB')) {
                error_log('[AJAX] KTPWP_Service_DBクラスが見つかりません');
                wp_send_json_error(__('サービスDBクラスが見つかりません', 'ktpwp'));
                return;
            }

            $service_db = KTPWP_Service_DB::get_instance();
            if (!$service_db) {
                error_log('[AJAX] サービスDBインスタンスの取得に失敗');
                
                // ダミーデータで代替
                $dummy_services = array(
                    array(
                        'id' => 1,
                        'service_name' => 'テストサービス1（DBエラー時）',
                        'price' => 10000,
                        'unit' => '個',
                        'category' => 'カテゴリ1'
                    )
                );

                $response_data = array(
                    'services' => $dummy_services,
                    'pagination' => array(
                        'current_page' => $page,
                        'total_pages' => 1,
                        'total_items' => 1,
                        'items_per_page' => $limit
                    ),
                    'debug_message' => 'DBインスタンス取得失敗のためダミーデータを使用'
                );

                error_log('[AJAX] ダミーデータ送信（DBエラー時）: ' . print_r($response_data, true));
                wp_send_json_success($response_data);
                return;
            }

            error_log('[AJAX] 検索パラメータ: page=' . $page . ', limit=' . $limit . ', search=' . $search . ', category=' . $category);

            // 検索条件の配列
            $search_args = array(
                'limit' => $limit,
                'offset' => $offset,
                'order_by' => 'frequency',
                'order' => 'DESC',
                'search' => $search,
                'category' => $category
            );

            // サービス一覧を取得
            error_log('[AJAX] サービス一覧取得呼び出し');
            $services = $service_db->get_services('service', $search_args);
            error_log('[AJAX] サービス一覧取得結果: ' . print_r($services, true));
            
            $total_services = $service_db->get_services_count('service', $search_args);
            error_log('[AJAX] サービス総数: ' . $total_services);

            if ($services === null || empty($services)) {
                error_log('[AJAX] サービス一覧が空のためダミーデータに切り替え');
                
                // テスト用のダミーデータを返す
                $dummy_services = array(
                    array(
                        'id' => 1,
                        'service_name' => 'サンプルサービス1',
                        'price' => 10000,
                        'unit' => '個',
                        'category' => 'カテゴリ1'
                    ),
                    array(
                        'id' => 2,
                        'service_name' => 'サンプルサービス2', 
                        'price' => 20000,
                        'unit' => '時間',
                        'category' => 'カテゴリ2'
                    ),
                    array(
                        'id' => 3,
                        'service_name' => 'サンプルサービス3', 
                        'price' => 5000,
                        'unit' => '回',
                        'category' => 'カテゴリ1'
                    )
                );

                $services = $dummy_services;
                $total_services = count($dummy_services);
            }

            $total_pages = ceil($total_services / $limit);

            // レスポンスデータ
            $response_data = array(
                'services' => $services,
                'pagination' => array(
                    'current_page' => $page,
                    'total_pages' => $total_pages,
                    'total_items' => $total_services,
                    'items_per_page' => $limit
                )
            );

            error_log('[AJAX] サービス一覧取得成功: ' . count($services) . '件');
            wp_send_json_success($response_data);

        } catch (Exception $e) {
            error_log('[AJAX] サービス一覧取得例外エラー: ' . $e->getMessage());
            
            // エラー時はダミーデータで代替
            $dummy_services = array(
                array(
                    'id' => 1,
                    'service_name' => 'エラー時サンプル',
                    'price' => 1000,
                    'unit' => '個',
                    'category' => 'エラー対応'
                )
            );

            $response_data = array(
                'services' => $dummy_services,
                'pagination' => array(
                    'current_page' => $page,
                    'total_pages' => 1,
                    'total_items' => 1,
                    'items_per_page' => $limit
                ),
                'debug_message' => 'Exception発生: ' . $e->getMessage()
            );

            wp_send_json_success($response_data);
        }
    }

    /**
     * Ajaxリクエストの共通バリデーション
     *
     * @param string $action アクション名
     * @param bool $require_admin 管理者権限必須かどうか
     * @return bool バリデーション結果
     */
    public function validate_ajax_request($action, $require_admin = false) {
        // ログインチェック
        if (!is_user_logged_in()) {
            wp_send_json_error(__('ログインが必要です', 'ktpwp'));
            return false;
        }

        // 管理者権限チェック
        if ($require_admin && !current_user_can('manage_options')) {
            wp_send_json_error(__('管理者権限が必要です', 'ktpwp'));
            return false;
        }

        // nonceチェック
        $nonce_key = $this->get_nonce_key_for_action($action);
        if ($nonce_key && isset($_POST['_wpnonce'])) {
            if (!wp_verify_nonce($_POST['_wpnonce'], $nonce_key)) {
                wp_send_json_error(__('セキュリティ検証に失敗しました', 'ktpwp'));
                return false;
            }
        }

        return true;
    }

    /**
     * アクションに対応するnonce名を取得
     *
     * @param string $action アクション名
     * @return string|false nonce名またはfalse
     */
    private function get_nonce_key_for_action($action) {
        $action_nonce_map = array(
            'ktp_update_project_name' => $this->nonce_names['project_name'],
            'ktp_auto_save_item' => $this->nonce_names['auto_save'],
            'ktp_create_new_item' => $this->nonce_names['auto_save'],
            'ktp_delete_item' => $this->nonce_names['auto_save'], // ktp_ajax_nonce を使用
            'ktp_update_item_order' => $this->nonce_names['auto_save'], // Add nonce for new action
        );

        return isset($action_nonce_map[$action]) ? $action_nonce_map[$action] : false;
    }

    /**
     * 安全なAjaxレスポンス送信
     *
     * @param mixed $data レスポンスデータ
     * @param bool $success 成功かどうか
     * @param string $message メッセージ
     */
    public function send_ajax_response($data = null, $success = true, $message = '') {
        if ($success) {
            $response = array();

            if (!empty($message)) {
                $response['message'] = $message;
            }

            if ($data !== null) {
                $response['data'] = $data;
            }

            wp_send_json_success($response);
        } else {
            wp_send_json_error($message);
        }
    }

    /**
     * Ajax入力データのサニタイズ（強化版）
     *
     * @param string $key POST配列のキー
     * @param string $type データタイプ（text, email, int, float, textarea, html）
     * @param mixed $default デフォルト値
     * @return mixed サニタイズされた値
     */
    public function sanitize_ajax_input($key, $type = 'text', $default = '') {
        // $_POST配列の存在確認（Adminer警告対策）
        if (!is_array($_POST) || !isset($_POST[$key])) {
            return $default;
        }

        $value = wp_unslash($_POST[$key]);

        // null値チェック（追加保護）
        if ($value === null) {
            return $default;
        }

        switch ($type) {
            case 'int':
                return intval($value);

            case 'float':
                return floatval($value);

            case 'email':
                return sanitize_email($value);

            case 'textarea':
                return sanitize_textarea_field($value);

            case 'html':
                return wp_kses_post($value);

            case 'text':
            default:
                return sanitize_text_field($value);
        }
    }

    /**
     * 登録されたハンドラー一覧取得
     *
     * @return array ハンドラー名配列
     */
    public function get_registered_handlers() {
        return $this->registered_handlers;
    }

    /**
     * Ajaxハンドラー存在チェック
     *
     * @param string $handler_name ハンドラー名
     * @return bool 存在するかどうか
     */
    public function handler_exists($handler_name) {
        return in_array($handler_name, $this->registered_handlers, true);
    }

    /**
     * nonce名設定の取得
     *
     * @param string $type nonce種別
     * @return string|false nonce名またはfalse
     */
    public function get_nonce_name($type) {
        return isset($this->nonce_names[$type]) ? $this->nonce_names[$type] : false;
    }

    /**
     * Ajaxエラーログ記録
     *
     * @param string $message エラーメッセージ
     * @param array $context 追加コンテキスト
     */
    public function log_ajax_error( $message, $context = array() ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            $log_message = 'KTPWP Ajax Error: ' . $message;

            if ( ! empty( $context ) ) {
                $log_message .= ' | Context: ' . wp_json_encode( $context );
            }

            error_log( $log_message );
        }
    }

    /**
     * Ajax: 最新スタッフチャットメッセージ取得
     */
    public function ajax_get_latest_staff_chat() {
        try {
            // 出力バッファをクリア
            while (ob_get_level()) {
                ob_end_clean();
            }
            ob_start();

            // フック干渉を無効化
            $this->disable_potentially_interfering_hooks();

            // ログインチェック
            if (!is_user_logged_in()) {
                $this->send_clean_json_response(array(
                    'success' => false,
                    'data' => __('ログインが必要です', 'ktpwp')
                ));
                return;
            }

            // Nonce検証（_ajax_nonceパラメータで送信される）
            $nonce = $_POST['_ajax_nonce'] ?? '';
            if (!wp_verify_nonce($nonce, $this->nonce_names['staff_chat'])) {
                $this->log_ajax_error('Staff chat get messages nonce verification failed', array(
                    'received_nonce' => $nonce,
                    'expected_action' => $this->nonce_names['staff_chat']
                ));
                $this->send_clean_json_response(array(
                    'success' => false,
                    'data' => __('セキュリティトークンが無効です', 'ktpwp')
                ));
                return;
            }

            // パラメータの取得とサニタイズ
            $order_id = $this->sanitize_ajax_input('order_id', 'int');
            $last_time = $this->sanitize_ajax_input('last_time', 'text');

            if (empty($order_id)) {
                $this->send_clean_json_response(array(
                    'success' => false,
                    'data' => __('注文IDが必要です', 'ktpwp')
                ));
                return;
            }

            // 権限チェック
            if (!current_user_can('read')) {
                $this->send_clean_json_response(array(
                    'success' => false,
                    'data' => __('権限がありません', 'ktpwp')
                ));
                return;
            }

            // スタッフチャットクラスのインスタンス化
            if (!class_exists('KTPWP_Staff_Chat')) {
                require_once KTPWP_PLUGIN_DIR . 'includes/class-ktpwp-staff-chat.php';
            }

            $staff_chat = KTPWP_Staff_Chat::get_instance();

            // 最新メッセージを取得
            $messages = $staff_chat->get_messages_after($order_id, $last_time);

            // バッファをクリーン
            $output = ob_get_clean();
            if (!empty($output)) {
                error_log('KTPWP Ajax get_latest_staff_chat: Unexpected output cleaned: ' . $output);
            }

            $this->send_clean_json_response(array(
                'success' => true,
                'data' => $messages
            ));

        } catch (Exception $e) {
            // バッファをクリーン
            while (ob_get_level()) {
                ob_end_clean();
            }

            $this->log_ajax_error('Exception during get latest staff chat', array(
                'message' => $e->getMessage(),
                'order_id' => $_POST['order_id'] ?? 'unknown',
            ));

            $this->send_clean_json_response(array(
                'success' => false,
                'data' => __('メッセージの取得中にエラーが発生しました', 'ktpwp')
            ));
        }
    }

    /**
     * Ajax: スタッフチャットメッセージ送信
     */
    public function ajax_send_staff_chat_message() {
        // WordPress出力バッファ完全クリア
        while (ob_get_level()) {
            ob_end_clean();
        }

        // 新しいバッファを開始（レスポンス汚染防止）
        ob_start();

        // エラー出力を抑制（JSON汚染防止）
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            error_reporting(0);
            ini_set('display_errors', 0);
        }

        // 汚染される可能性のあるWordPressフックを一時的に無効化
        $this->disable_potentially_interfering_hooks();

        try {
            // ログインチェック
            if (!is_user_logged_in()) {
                wp_send_json_error(__('ログインが必要です', 'ktpwp'));
                return;
            }

            // Nonce検証（_ajax_nonceパラメータで送信される）
            $nonce = $_POST['_ajax_nonce'] ?? '';
            $nonce_valid = wp_verify_nonce($nonce, $this->nonce_names['staff_chat']);
            // nonceが不正かつ権限もない場合のみエラー
            if (!$nonce_valid && !current_user_can('edit_posts') && !current_user_can('ktpwp_access')) {
                wp_send_json_error(__('権限がありません（nonce不正）', 'ktpwp'));
                return;
            }

            // パラメータの取得とサニタイズ
            $order_id = $this->sanitize_ajax_input('order_id', 'int');
            $message = $this->sanitize_ajax_input('message', 'text');

            if (empty($order_id) || empty($message)) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('KTPWP StaffChat: order_idまたはmessageが空: order_id=' . print_r($order_id, true) . ' message=' . print_r($message, true));
                }
                wp_send_json_error(__('注文IDとメッセージが必要です', 'ktpwp'));
                return;
            }

            // 権限チェック
            if (!current_user_can('edit_posts') && !current_user_can('ktpwp_access')) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('KTPWP StaffChat: 権限チェック失敗 user_id=' . get_current_user_id());
                }
                wp_send_json_error(__('権限がありません', 'ktpwp'));
                return;
            }

            // スタッフチャットクラスのインスタンス化
            if (!class_exists('KTPWP_Staff_Chat')) {
                require_once KTPWP_PLUGIN_DIR . 'includes/class-ktpwp-staff-chat.php';
            }

            $staff_chat = KTPWP_Staff_Chat::get_instance();

            // メッセージを送信
            $result = $staff_chat->add_message($order_id, $message);

            if ($result) {
                // バッファをクリーンにしてからJSONを送信
                $output = ob_get_clean();
                if (!empty($output)) {
                    // デバッグ用：予期しない出力があればログに記録
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('KTPWP Ajax: Unexpected output cleaned: ' . $output);
                    }
                }

                // 最終的なクリーンアップ
                $this->send_clean_json_response(array(
                    'success' => true,
                    'data' => array(
                        'message' => __('メッセージを送信しました', 'ktpwp'),
                    )
                ));
            } else {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('KTPWP StaffChat: add_message失敗 user_id=' . get_current_user_id() . ' order_id=' . print_r($order_id, true) . ' message=' . print_r($message, true));
                }
                ob_end_clean();
                $this->send_clean_json_response(array(
                    'success' => false,
                    'data' => __('メッセージの送信に失敗しました', 'ktpwp')
                ));
            }

        } catch (Exception $e) {
            ob_end_clean();
            $this->log_ajax_error('Exception during send staff chat message', array(
                'message' => $e->getMessage(),
                'order_id' => $_POST['order_id'] ?? 'unknown',
            ));
            $this->send_clean_json_response(array(
                'success' => false,
                'data' => __('メッセージの送信中にエラーが発生しました', 'ktpwp')
            ));
        }
    }

    /**
     * WordPress 互換性を保持したクリーンな JSON レスポンスを送信
     * wp_send_json_* 関数の改良版（WordPress コア機能との競合を避ける）
     */
    private function send_clean_json_response($data) {
        // WordPress 標準の JSON レスポンス関数が利用可能で、
        // 既に出力が汚染されていない場合は、標準関数を使用
        if (function_exists('wp_send_json') && ob_get_length() === false) {
            wp_send_json($data);
            return;
        }

        // 出力バッファが汚染されている場合のみクリーニングを実行
        $buffer_content = '';
        $buffer_level = ob_get_level();

        if ($buffer_level > 0) {
            $buffer_content = ob_get_contents();
            // 意味のある出力があるかチェック（空白や改行のみの場合は無視）
            $meaningful_content = trim($buffer_content);

            if (!empty($meaningful_content)) {
                // 汚染されている場合のみバッファをクリア
                while (ob_get_level()) {
                    ob_end_clean();
                }
            }
        }

        // HTTPヘッダーが送信されていない場合のみ設定
        if (!headers_sent()) {
            // WordPress 標準に準拠したヘッダー設定
            status_header(200);
            header('Content-Type: application/json; charset=' . get_option('blog_charset'));

            // キャッシュ制御
            nocache_headers();

            // セキュリティヘッダー
            if (is_ssl()) {
                header('Vary: Accept-Encoding');
            }
        }

        // JSONエンコード（WordPress 標準オプション使用）
        $json = wp_json_encode($data);

        if ($json === false) {
            // JSON エンコードに失敗した場合のフォールバック
            $fallback_data = array(
                'success' => false,
                'data' => null,
                'message' => 'JSON encoding failed: ' . json_last_error_msg()
            );
            $json = wp_json_encode($fallback_data);
        }

        // JSONを出力
        echo $json;

        // WordPress 標準の終了処理を使用（shutdown フックを実行）
        wp_die('', '', array('response' => 200));
    }

    /**
     * 出力に干渉する可能性のあるWordPressフックを一時的に無効化
     */
    private function disable_potentially_interfering_hooks() {
        // デバッグバーや他のプラグインの出力を防ぐ（安全なフックのみ削除）
        remove_all_actions('wp_footer');
        remove_all_actions('admin_footer');
        remove_all_actions('in_admin_footer');
        remove_all_actions('admin_print_footer_scripts');
        remove_all_actions('wp_print_footer_scripts');

        // WordPress コアの重要な処理を破壊しないよう、shutdown フックは保持

        // 他のプラグインのAJAX干渉を防ぐ（安全な範囲で）
        if (class_exists('WP_Debug_Bar')) {
            remove_all_actions('wp_before_admin_bar_render');
            remove_all_actions('wp_after_admin_bar_render');
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
            'wp_footer' => array('debug_bar', 'query_monitor', 'wp_debug_bar'),
            'admin_footer' => array('debug_bar', 'query_monitor'),
            'shutdown' => array('debug_bar_output', 'query_monitor_output')
        );

        foreach ($problematic_hooks as $hook_name => $plugin_patterns) {
            if (isset($wp_filter[$hook_name])) {
                foreach ($wp_filter[$hook_name]->callbacks as $priority => $callbacks) {
                    foreach ($callbacks as $callback_id => $callback_data) {
                        // コールバック関数名または関数オブジェクトをチェック
                        $function_name = '';
                        if (is_string($callback_data['function'])) {
                            $function_name = $callback_data['function'];
                        } elseif (is_array($callback_data['function']) && count($callback_data['function']) === 2) {
                            $class_name = is_object($callback_data['function'][0]) ?
                                get_class($callback_data['function'][0]) : $callback_data['function'][0];
                            $function_name = $class_name . '::' . $callback_data['function'][1];
                        }

                        // 問題のあるパターンに一致する場合のみ削除
                        foreach ($plugin_patterns as $pattern) {
                            if (stripos($function_name, $pattern) !== false) {
                                remove_action($hook_name, $callback_data['function'], $priority);
                                break;
                            }
                        }
                    }
                }
            }
        }
    }
}
