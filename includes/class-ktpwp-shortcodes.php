<?php
/**
 * KTPWP ショートコード管理クラス
 *
 * @package KTPWP
 * @since 0.1.0
 */

// セキュリティ: 直接アクセスを防止
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ショートコード管理クラス
 */
class KTPWP_Shortcodes {

    /**
     * シングルトンインスタンス
     *
     * @var KTPWP_Shortcodes|null
     */
    private static $instance = null;

    /**
     * ユーザーログイン状況キャッシュ
     *
     * @var array
     */
    private $logged_in_users_cache = null;

    /**
     * 登録されたショートコード一覧
     *
     * @var array
     */
    private $registered_shortcodes = array();

    /**
     * シングルトンインスタンス取得
     *
     * @return KTPWP_Shortcodes
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
        // WordPressがプラグインを読み込んだ後にショートコードを登録
        add_action('plugins_loaded', array($this, 'register_shortcodes'), 20);
        // ※Ajaxハンドラの登録は class-ktpwp-ajax.php 側でのみ行う
    }

    /**
     * ショートコード登録
     */
    public function register_shortcodes() {
        // メインショートコード（旧名）
        add_shortcode('kantanAllTab', array($this, 'render_all_tabs'));
        $this->registered_shortcodes[] = 'kantanAllTab';

        // メインショートコード（新名）
        add_shortcode('ktpwp_all_tab', array($this, 'render_all_tabs'));
        $this->registered_shortcodes[] = 'ktpwp_all_tab';

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('KTPWP Shortcodes: Registered shortcodes - ' . implode(', ', $this->registered_shortcodes));
        }
    }

    /**
     * 全タブショートコードの描画
     *
     * @param array $atts ショートコード属性
     * @return string 描画されたHTML
     */
    public function render_all_tabs($atts = array()) {
        // Ajaxリクエスト中（特に投稿保存時など）は、ショートコードの出力を抑制する
        // これにより、JSONレスポンスが壊れるのを防ぐ
        // ただし、このショートコード自体がAjaxでコンテンツを返すことを意図している場合は、この条件分岐は見直す必要がある
        if (defined('DOING_AJAX') && DOING_AJAX) {
            // WordPressの投稿保存処理など、特定のAjaxアクションを判定して分岐することも検討できる
            // 例: if (defined('DOING_AJAX') && DOING_AJAX && isset($_POST['action']) && $_POST['action'] === 'editpost')
            return '';
        }

        // ログイン状態と権限チェック
        if (!is_user_logged_in()) { // ログインしているかチェック
            return $this->render_login_error();
        }
        // 権限チェックを削除し、ログインしていれば誰でも表示するように変更
        // if (!current_user_can('edit_posts') && !current_user_can('ktpwp_access')) { // 次に権限をチェック
        //     return $this->render_permission_error(); // 権限がない場合は専用のエラーメッセージ
        // }

        // 属性のデフォルト値設定
        $atts = shortcode_atts(array(
            'debug' => 'false',
            'cache' => 'true',
        ), $atts, 'ktpwp_all_tab');

        ob_start(); // 出力バッファリングを開始
        echo '<div class="ktpwp-shortcode-container">'; // コンテナ開始

        try {
            // 各種コンテンツの取得
            $header_content = $this->get_header_content();
            $tab_content = $this->get_tab_content();

            echo $header_content . $tab_content; // バッファに出力

        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('KTPWP Shortcode Error: ' . $e->getMessage());
            }
            echo '<div class="ktpwp-error">' . esc_html__('エラーが発生しました。', 'ktpwp') . '</div>'; // バッファに出力
        }

        echo '</div>'; // コンテナ終了
        return ob_get_clean(); // バッファの内容を取得して返す
    }

    /**
     * ヘッダーコンテンツ取得
     *
     * @return string ヘッダーHTML
     */
    private function get_header_content() {
        global $current_user;

        // 基本情報の取得
        $plugin_name = esc_html(KANTANPRO_PLUGIN_NAME);
        $plugin_version = esc_html(KANTANPRO_PLUGIN_VERSION);
        $icon_img = $this->get_plugin_icon();

        // ナビゲーション要素の生成
        $logged_in_users_html = $this->get_logged_in_users_display();
        $navigation_links = $this->get_navigation_links();

        // ヘッダーHTML構築（PC・タブレット表示用）
        $header_html = '<div class="ktp_header">';
        $header_html .= '<div class="parent">';
        $header_html .= '<div class="title">' . $icon_img . $plugin_name . '</div>';
        $header_html .= '<div class="version">v' . $plugin_version . '</div>';
        $header_html .= '</div>';
        $header_html .= '<div class="header-right-section">';
        $header_html .= '<div class="navigation-links">' . $navigation_links . '</div>';
        $header_html .= '<div class="user-avatars-section">' . $logged_in_users_html . '</div>';
        $header_html .= '</div>';
        $header_html .= '</div>';

        return $header_html;
    }

    /**
     * プラグインアイコン取得
     *
     * @return string アイコンIMGタグ
     */
    private function get_plugin_icon() {
        $icon_url = plugins_url('images/default/icon.png', KANTANPRO_PLUGIN_FILE);
        return '<img src="' . esc_url($icon_url) . '" style="height:40px;vertical-align:middle;margin-right:8px;position:relative;top:-5px;">';
    }

    /**
     * ログイン中ユーザー表示の取得
     *
     * @return string ユーザー表示HTML
     */
    private function get_logged_in_users_display() {
        global $current_user;

        // 厳密なログイン状態確認
        if (!is_user_logged_in() || !$current_user || $current_user->ID <= 0) {
            return '';
        }

        // 全てのログイン中のスタッフを取得
        $logged_in_staff = $this->get_logged_in_staff_users();
        
        if (empty($logged_in_staff)) {
            return '';
        }

        $logged_in_users_html = '<div class="logged-in-staff-avatars">';
        
        foreach ($logged_in_staff as $user) {
            $nickname = get_user_meta($user->ID, 'nickname', true);
            if (empty($nickname)) {
                $nickname = $user->display_name ? $user->display_name : $user->user_login;
            }
            $nickname_esc = esc_attr($nickname);
            
            // 現在のユーザーかどうかで表示を変更
            $is_current = (get_current_user_id() === $user->ID);
            $class = $is_current ? 'user_icon user_icon--current' : 'user_icon user_icon--staff';
            
            if ($is_current) {
                $logged_in_users_html .= '<strong><span title="' . $nickname_esc . '">' . 
                    get_avatar($user->ID, 32, '', '', array('class' => $class)) . '</span></strong>';
            } else {
                $logged_in_users_html .= '<span title="' . $nickname_esc . '">' . 
                    get_avatar($user->ID, 32, '', '', array('class' => $class)) . '</span>';
            }
        }

        $logged_in_users_html .= '</div>';
        return $logged_in_users_html;
    }

    /**
     * ログイン中のスタッフユーザーを取得
     *
     * @return array ログイン中のスタッフユーザー配列
     */
    private function get_logged_in_staff_users() {
        // アクティブなセッションを持つユーザーを取得
        $users_with_sessions = get_users(array(
            'meta_key' => 'session_tokens',
            'meta_compare' => 'EXISTS',
            'fields' => 'all'
        ));
        
        $logged_in_staff = array();
        
        foreach ($users_with_sessions as $user) {
            // セッションが有効かチェック
            $sessions = get_user_meta($user->ID, 'session_tokens', true);
            if (empty($sessions)) {
                continue;
            }
            
            $has_valid_session = false;
            foreach ($sessions as $session) {
                if (isset($session['expiration']) && $session['expiration'] > time()) {
                    $has_valid_session = true;
                    break;
                }
            }
            
            if (!$has_valid_session) {
                continue;
            }
            
            // スタッフ権限をチェック（ktpwp_access または管理者権限）
            if ($this->is_staff_user($user)) {
                $logged_in_staff[] = $user;
            }
        }
        
        // 現在のユーザーを先頭に並べ替え
        usort($logged_in_staff, function($a, $b) {
            $current_user_id = get_current_user_id();
            if ($a->ID === $current_user_id) return -1;
            if ($b->ID === $current_user_id) return 1;
            return strcmp($a->display_name, $b->display_name);
        });
        
        return $logged_in_staff;
    }

    /**
     * スタッフユーザーかどうかを判定
     *
     * @param WP_User $user ユーザーオブジェクト
     * @return bool スタッフかどうか
     */
    private function is_staff_user($user) {
        // 管理者は常にスタッフ扱い
        if (in_array('administrator', $user->roles)) {
            return true;
        }
        
        // ktpwp_access権限を持つユーザーをスタッフとして判定
        return user_can($user->ID, 'ktpwp_access');
    }

    /**
     * ナビゲーションリンク取得
     *
     * @return string ナビゲーションHTML
     */
    private function get_navigation_links() {
        global $current_user;

        // ログイン状態とセッション確認 (権限チェック部分を削除)
        if (!is_user_logged_in() || !$current_user || $current_user->ID <= 0) {
            return '';
        }

        // セッション有効性確認の改善
        $user_sessions = WP_Session_Tokens::get_instance($current_user->ID);
        if (!$user_sessions) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('KTPWP Debug: get_navigation_links - WP_Session_Tokens::get_instance returned null for user ID: ' . $current_user->ID);
            }
            return '';
        }

        $all_sessions = $user_sessions->get_all();
        if (empty($all_sessions)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('KTPWP Debug: get_navigation_links - No active sessions found for user ID: ' . $current_user->ID);
            }
            return '';
        }

        // 各種リンクの生成
        $logout_url = esc_url(wp_logout_url());
        $current_page_id = get_queried_object_id();
        $update_url = esc_url(get_permalink($current_page_id));
        $activation_key = esc_html($this->check_activation_key());

        $links = array();
        
        // 外部リンクの定数化とセキュリティ強化
        $external_links = array(
            'official_site' => 'https://www.kantanpro.com/',
            'features' => 'https://www.kantanpro.com/features/',
            'community' => 'https://www.kantanpro.com/community/'
        );

        // 寄付ボタンを最初に追加（常時表示）
        $donation_settings = get_option('ktp_donation_settings', array());
        $donation_url = !empty($donation_settings['donation_url']) ? esc_url($donation_settings['donation_url']) : 'https://www.kantanpro.com/donation';
        
        // 管理者情報を取得
        $admin_email = get_option('admin_email');
        $admin_name = get_option('blogname');
        
        // POSTパラメータを追加
        $donation_url_with_params = add_query_arg(array(
            'admin_email' => urlencode($admin_email),
            'admin_name' => urlencode($admin_name)
        ), $donation_url);
        
        $links[] = sprintf(
            '<a href="%s" target="_blank" rel="noopener noreferrer" title="%s" style="display: inline-flex; align-items: center; gap: 4px; color: #0073aa; text-decoration: none;"><span class="material-symbols-outlined" style="font-size: 20px; vertical-align: middle;">favorite</span><span>%s</span></a>',
            $donation_url_with_params,
            esc_attr__('寄付する', 'ktpwp'),
            esc_html__('寄付する', 'ktpwp')
        );

        // 公式サイト（KantanPro）
        $links[] = sprintf(
            '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
            esc_url($external_links['official_site']),
            esc_html__('KantanPro', 'ktpwp')
        );
        
        // 詳細を表示（公式サイトの機能紹介ページ等。なければトップページ）
        $links[] = sprintf(
            '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
            esc_url($external_links['features']),
            esc_html__('詳細を表示', 'ktpwp')
        );
        
        // コミュニティ
        $links[] = sprintf(
            '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
            esc_url($external_links['community']),
            esc_html__('コミュニティ', 'ktpwp')
        );
        
        // ログアウト
        $links[] = sprintf(
            '<a href="%s" title="%s" style="display: inline-flex; align-items: center; gap: 4px; color: #0073aa; text-decoration: none;">%s</a>',
            $logout_url,
            esc_attr__('ログアウト', 'ktpwp'),
            '<span class="material-symbols-outlined" style="font-size: 20px; vertical-align: middle;">logout</span>'
        );
        
        // 更新
        $links[] = sprintf(
            '<a href="%s" title="%s" style="display: inline-flex; align-items: center; gap: 4px; color: #0073aa; text-decoration: none;">%s</a>',
            $update_url,
            esc_attr__('更新', 'ktpwp'),
            '<span class="material-symbols-outlined" style="font-size: 20px; vertical-align: middle;">refresh</span>'
        );
        // アクティベーションキー（空文字列）
        if (!empty($activation_key)) {
            $links[] = $activation_key;
        }
        // ヘルプ（リファレンス）
        $reference_instance = KTPWP_Plugin_Reference::get_instance();
        $links[] = $reference_instance->get_reference_link();

        return ' ' . implode(' ', $links);
    }

    /**
     * アクティベーションキー確認
     *
     * @return string アクティベーションキー状態
     */
    private function check_activation_key() {
        $activation_key = get_site_option('ktp_activation_key');
        return empty($activation_key) ? '' : '';
    }

    /**
     * タブコンテンツ取得
     *
     * @return string タブHTML
     */
    private function get_tab_content() {
        $tab_name = $this->get_current_tab();

        // 各タブコンテンツの初期化
        $tab_contents = array(
            'list' => '',
            'order' => '',
            'client' => '',
            'service' => '',
            'supplier' => '',
            'report' => ''
        );

        // 現在のタブに応じてコンテンツを生成
        switch ($tab_name) {
            case 'list':
                $tab_contents['list'] = $this->get_list_content($tab_name);
                break;

            case 'order':
                $tab_contents['order'] = $this->get_order_content($tab_name);
                break;

            case 'client':
                $tab_contents['client'] = $this->get_client_content($tab_name);
                break;

            case 'service':
                $tab_contents['service'] = $this->get_service_content($tab_name);
                break;

            case 'supplier':
                $tab_contents['supplier'] = $this->get_supplier_content($tab_name);
                break;

            case 'report':
                $tab_contents['report'] = $this->get_report_content($tab_name);
                break;

            default:
                // デフォルトでリストタブを表示
                $tab_name = 'list';
                $tab_contents['list'] = $this->get_list_content($tab_name);
                break;
        }

        // タブビューの生成
        return $this->render_tabs_view(
            $tab_contents['list'],
            $tab_contents['order'],
            $tab_contents['client'],
            $tab_contents['service'],
            $tab_contents['supplier'],
            $tab_contents['report']
        );
    }

    /**
     * 現在のタブ名取得
     *
     * @return string タブ名
     */
    private function get_current_tab() {
        $tab_name = isset($_GET['tab_name']) ? sanitize_text_field($_GET['tab_name']) : 'list';

        // 許可されたタブ名のホワイトリスト
        $allowed_tabs = array('list', 'order', 'client', 'service', 'supplier', 'report');

        if (!in_array($tab_name, $allowed_tabs, true)) {
            $tab_name = 'list';
        }

        return $tab_name;
    }

    /**
     * リストコンテンツ取得
     *
     * @param string $tab_name タブ名
     * @return string コンテンツHTML
     */
    private function get_list_content($tab_name) {
        if (!current_user_can('edit_posts') && !current_user_can('ktpwp_access')) {
            return $this->render_permission_error();
        }
        if (!class_exists('Kantan_List_Class')) {
            $this->load_required_class('class-tab-list.php');
        }

        if (class_exists('Kantan_List_Class')) {
            $list = new Kantan_List_Class();
            return $list->List_Tab_View($tab_name);
        }

        return $this->get_error_content('Kantan_List_Class');
    }

    /**
     * 受注コンテンツ取得
     *
     * @param string $tab_name タブ名
     * @return string コンテンツHTML
     */
    private function get_order_content($tab_name) {
        if (!current_user_can('edit_posts') && !current_user_can('ktpwp_access')) {
            return $this->render_permission_error();
        }
        if (!class_exists('Kntan_Order_Class')) {
            $this->load_required_class('class-tab-order.php');
        }

        if (class_exists('Kntan_Order_Class')) {
            $order = new Kntan_Order_Class();
            $content = $order->Order_Tab_View($tab_name);
            return $content ?? '';
        }

        return $this->get_error_content('Kntan_Order_Class');
    }

    /**
     * 顧客コンテンツ取得
     *
     * @param string $tab_name タブ名
     * @return string コンテンツHTML
     */
    private function get_client_content($tab_name) {
        if (!current_user_can('edit_posts') && !current_user_can('ktpwp_access')) {
            return $this->render_permission_error();
        }
        if (!class_exists('Kntan_Client_Class')) {
            $this->load_required_class('class-tab-client.php');
        }

        if (class_exists('Kntan_Client_Class')) {
            $client = new Kntan_Client_Class();

            // 管理者権限がある場合のみテーブル操作 -> 編集者権限に変更
            if (current_user_can('edit_posts') || current_user_can('ktpwp_access')) {
                $client->Create_Table($tab_name);
                $client->Update_Table($tab_name);
            }

            return $client->View_Table($tab_name);
        }

        return $this->get_error_content('Kntan_Client_Class');
    }

    /**
     * サービスコンテンツ取得
     *
     * @param string $tab_name タブ名
     * @return string コンテンツHTML
     */
    private function get_service_content($tab_name) {
        if (!current_user_can('edit_posts') && !current_user_can('ktpwp_access')) {
            return $this->render_permission_error();
        }
        if (!class_exists('Kntan_Service_Class')) {
            $this->load_required_class('class-tab-service.php');
        }

        if (class_exists('Kntan_Service_Class')) {
            $service = new Kntan_Service_Class();

            // 管理者権限がある場合のみテーブル操作
            if (current_user_can('manage_options')) {
                $service->Create_Table($tab_name);
                $service->Update_Table($tab_name);
            }

            return $service->View_Table($tab_name);
        }

        return $this->get_error_content('Kntan_Service_Class');
    }

    /**
     * 仕入先コンテンツ取得
     *
     * @param string $tab_name タブ名
     * @return string コンテンツHTML
     */
    private function get_supplier_content($tab_name) {
        if (!current_user_can('edit_posts') && !current_user_can('ktpwp_access')) {
            return $this->render_permission_error();
        }
        if (!class_exists('KTPWP_Supplier_Class')) {
            $this->load_required_class('class-tab-supplier.php');
        }

        if (class_exists('KTPWP_Supplier_Class')) {
            $supplier = new KTPWP_Supplier_Class();

            // 編集者権限がある場合のみテーブル操作
            if (current_user_can('edit_posts') || current_user_can('ktpwp_access')) {
                $supplier->Create_Table($tab_name);
                
                if (!empty($_POST)) {
                    $supplier->Update_Table($tab_name);
                }
            }

            return $supplier->View_Table($tab_name);
        }

        return $this->get_error_content('KTPWP_Supplier_Class');
    }

    /**
     * レポートコンテンツ取得
     *
     * @param string $tab_name タブ名
     * @return string コンテンツHTML
     */
    private function get_report_content($tab_name) {
        if (!current_user_can('edit_posts') && !current_user_can('ktpwp_access')) {
            return $this->render_permission_error();
        }
        if (!class_exists('KTPWP_Report_Class')) {
            $this->load_required_class('class-tab-report.php');
        }

        if (class_exists('KTPWP_Report_Class')) {
            $report = new KTPWP_Report_Class();
            return $report->Report_Tab_View($tab_name);
        }

        return $this->get_error_content('KTPWP_Report_Class');
    }

    /**
     * 設定コンテンツ取得
     *
     * @param string $tab_name タブ名
     * @return string コンテンツHTML
     */
    private function get_setting_content($tab_name) {
        // 設定タブは廃止されたため、廃止メッセージを返す
        return '<div class="ktpwp-notice" style="padding: 20px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px; margin: 20px 0;">
            <h3 style="margin: 0 0 10px 0; color: #6c757d;">設定タブについて</h3>
            <p style="margin: 0; color: #6c757d;">設定タブは廃止されました。ヘッダー画像などの設定は管理画面の「KantanPro設定」から行ってください。</p>
        </div>';
    }

    /**
     * 必要なクラスファイルを読み込み
     *
     * @param string $filename ファイル名
     */
    private function load_required_class($filename) {
        $file_path = KANTANPRO_PLUGIN_DIR . 'includes/' . $filename;

        if (file_exists($file_path)) {
            require_once $file_path;
        } else {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('KTPWP Error: Required class file not found - ' . $filename);
            }
        }
    }

    /**
     * エラーコンテンツ取得
     *
     * @param string $class_name クラス名
     * @return string エラーHTML
     */
    private function get_error_content($class_name) {
        $message = sprintf(
            esc_html__('クラス %s が見つかりません。', 'ktpwp'),
            esc_html($class_name)
        );

        return '<div class="ktpwp-error">' . $message . '</div>';
    }

    /**
     * タブビューレンダリング
     *
     * @param string $list_content リストコンテンツ
     * @param string $order_content 受注コンテンツ
     * @param string $client_content 顧客コンテンツ
     * @param string $service_content サービスコンテンツ
     * @param string $supplier_content 仕入先コンテンツ
     * @param string $report_content レポートコンテンツ
     * @return string タブビューHTML
     */
    private function render_tabs_view($list_content, $order_content, $client_content, $service_content, $supplier_content, $report_content) {
        if (!class_exists('view_tabs_Class')) {
            $this->load_required_class('class-view-tab.php');
        }

        if (class_exists('view_tabs_Class')) {
            $view = new view_tabs_Class();
            return $view->TabsView($list_content, $order_content, $client_content, $service_content, $supplier_content, $report_content);
        }

        return $this->get_error_content('view_tabs_Class');
    }

    /**
     * ログインエラー表示
     *
     * @return string ログインエラーHTML
     */
    private function render_login_error() {
        return '<div class="ktpwp-error">' . esc_html__('このコンテンツを表示するにはログインが必要です。', 'ktpwp') . '</div>';
    }

    /**
     * 権限エラーメッセージ描画
     *
     * @return string エラーHTML
     */
    private function render_permission_error() {
        return '<div class="ktpwp-error">' . esc_html__('このコンテンツを表示する権限がありません。', 'ktpwp') . '</div>';
    }

    /**
     * Ajax: ログイン中ユーザー取得
     */
    public function ajax_get_logged_in_users() {
        // Ajax以外からのアクセスは何も返さない
        if (
            !defined('DOING_AJAX') ||
            !DOING_AJAX ||
            (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest')
        ) {
            wp_die();
        }

        // キャッシュがある場合は使用
        if ($this->logged_in_users_cache !== null) {
            wp_send_json($this->logged_in_users_cache);
        }

        // ログイン中スタッフを取得
        $logged_in_staff = $this->get_logged_in_staff_users();

        $users_data = array();
        foreach ($logged_in_staff as $user) {
            $nickname = get_user_meta($user->ID, 'nickname', true);
            if (empty($nickname)) {
                $nickname = $user->display_name ? $user->display_name : $user->user_login;
            }
            
            $users_data[] = array(
                'id' => $user->ID,
                'name' => esc_html($nickname) . 'さん',
                'is_current' => (get_current_user_id() === $user->ID),
                'avatar_url' => get_avatar_url($user->ID, array('size' => 32))
            );
        }

        // キャッシュに保存（30秒）
        $this->logged_in_users_cache = $users_data;
        wp_cache_set('ktpwp_logged_in_staff', $users_data, '', 30);

        wp_send_json($users_data);
    }

    /**
     * 登録済みショートコード一覧取得
     *
     * @return array ショートコード名配列
     */
    public function get_registered_shortcodes() {
        return $this->registered_shortcodes;
    }

    /**
     * ショートコード存在チェック
     *
     * @param string $shortcode_name ショートコード名
     * @return bool 存在するかどうか
     */
    public function shortcode_exists($shortcode_name) {
        return in_array($shortcode_name, $this->registered_shortcodes, true);
    }

    /**
     * ログイン中スタッフアバター表示の公開メソッド
     *
     * @return string ユーザー表示HTML
     */
    public function get_staff_avatars_display() {
        return $this->get_logged_in_users_display();
    }

    /**
     * デストラクタ
     */
    public function __destruct() {
        // キャッシュクリア（必要に応じて）
        $this->logged_in_users_cache = null;
    }
}
