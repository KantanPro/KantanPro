<?php
/**
 * Settings class for KTPWP plugin
 *
 * Handles plugin settings including SMTP configuration,
 * admin interface, and security implementations.
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

/**
 * Settings class for managing plugin settings
 *
 * @since 1.0.0
 */
class KTP_Settings {
    
    /**
     * Single instance of the class
     *
     * @var KTP_Settings
     */
    private static $instance = null;
    
    /**
     * Options group name
     *
     * @var string
     */
    private $options_group = 'ktp_settings';
    
    /**
     * Option name for SMTP settings
     *
     * @var string
     */
    private $option_name = 'ktp_smtp_settings';
    
    /**
     * Test mail message
     *
     * @var string
     */
    private $test_mail_message = '';
    
    /**
     * Test mail status
     *
     * @var string
     */
    private $test_mail_status = '';

    /**
     * Get singleton instance
     *
     * @since 1.0.0
     * @return KTP_Settings
     */
    public static function get_instance() {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get work list range setting
     *
     * @since 1.0.0
     * @return int Work list range setting (default: 20)
     */
    public static function get_work_list_range() {
        $options = get_option( 'ktp_general_settings', array() );
        return isset( $options['work_list_range'] ) ? intval( $options['work_list_range'] ) : 20;
    }

    /**
     * Get company information setting
     *
     * @since 1.0.0
     * @return string Company information content (default: empty string)
     */
    public static function get_company_info() {
        $options = get_option( 'ktp_general_settings', array() );
        return isset( $options['company_info'] ) ? $options['company_info'] : '';
    }

    /**
     * Get design settings
     *
     * @since 1.0.0
     * @return array Design settings
     */
    public static function get_design_settings() {
        // システムデフォルト値
        $system_defaults = array(
            'tab_active_color' => '#B7CBFB',
            'tab_inactive_color' => '#E6EDFF',
            'tab_border_color' => '#B7CBFB',
            'odd_row_color' => '#E7EEFD',
            'even_row_color' => '#FFFFFF',
            'header_bg_image' => 'images/default/header_bg_image.png',
            'custom_css' => ''
        );
        
        return get_option( 'ktp_design_settings', $system_defaults );
    }

    /**
     * Get header background image URL
     *
     * @since 1.0.0
     * @return string Header background image URL (empty string if not set)
     */
    public static function get_header_bg_image_url() {
        $design_settings = self::get_design_settings();
        
        $header_bg_image = ! empty( $design_settings['header_bg_image'] ) ? $design_settings['header_bg_image'] : 'images/default/header_bg_image.png';
        
        // 数値の場合はWordPressの添付ファイルIDとして処理
        if ( is_numeric( $header_bg_image ) ) {
            return wp_get_attachment_image_url( $header_bg_image, 'full' );
        } else {
            // 文字列の場合は直接パスとして処理
            $image_path = $header_bg_image;
            // 相対パスの場合は、プラグインディレクトリからの絶対URLに変換
            if ( strpos( $image_path, 'http' ) !== 0 ) {
                return plugin_dir_url( dirname( __FILE__ ) ) . $image_path;
            }
            return $image_path;
        }
    }

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    private function __construct() {
        add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
        add_action( 'admin_init', array( $this, 'page_init' ) );
        add_action( 'phpmailer_init', array( $this, 'setup_smtp_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_styles' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_media_scripts' ) );
        add_action( 'wp_head', array( $this, 'output_custom_styles' ) );
        add_action( 'admin_init', array( $this, 'handle_default_settings_actions' ) );
        
        // ユーザーアクティビティの追跡
        add_action( 'wp_login', array( $this, 'record_user_last_login' ), 10, 2 );
    }

    /**
     * Enqueue media scripts for image upload
     *
     * @since 1.0.0
     * @param string $hook Current admin page hook
     * @return void
     */
    public function enqueue_media_scripts( $hook ) {
        // KTPWPのデザイン設定ページでのみメディアライブラリを読み込む
        if ( strpos( $hook, 'ktp-design' ) !== false ) {
            wp_enqueue_media();
            wp_enqueue_script(
                'ktp-media-upload',
                plugin_dir_url( dirname( __FILE__ ) ) . 'js/ktp-media-upload.js',
                array( 'jquery' ),
                '1.0.0',
                true
            );
        }
    }

    /**
     * Enqueue admin styles
     *
     * @since 1.0.0
     * @param string $hook Current admin page hook
     * @return void
     */
    public function enqueue_admin_styles( $hook ) {
        // Load CSS on KTPWP settings pages only
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        
        if ( strpos( $hook, 'ktp-' ) !== false ) {
            wp_enqueue_style(
                'ktp-admin-settings',
                plugin_dir_url( dirname( __FILE__ ) ) . 'css/ktp-admin-settings.css',
                array(),
                '1.0.1'
            );
            
            wp_enqueue_style(
                'ktp-setting-tab',
                plugin_dir_url( dirname( __FILE__ ) ) . 'css/ktp-setting-tab.css',
                array(),
                '1.0.1'
            );
        }
    }

    /**
     * Activate plugin and set default options
     *
     * @since 1.0.0
     * @return void
     */
    public static function activate() {
        $option_name = 'ktp_smtp_settings';
        if ( false === get_option( $option_name ) ) {
            add_option( $option_name, array(
                'email_address' => '',
                'smtp_host' => '',
                'smtp_port' => '',
                'smtp_user' => '',
                'smtp_pass' => '',
                'smtp_secure' => '',
                'smtp_from_name' => ''
            ));
        }
        
        // 一般設定のデフォルト値を設定
        $general_option_name = 'ktp_general_settings';
        if ( false === get_option( $general_option_name ) ) {
            add_option( $general_option_name, array(
                'work_list_range' => 20,
                'company_info' => ''
            ));
        }
        
        // デザイン設定のデフォルト値を設定
        $design_option_name = 'ktp_design_settings';
        $design_defaults = array(
            'tab_active_color' => '#B7CBFB',
            'tab_inactive_color' => '#E6EDFF',
            'tab_border_color' => '#B7CBFB',
            'odd_row_color' => '#E7EEFD',
            'even_row_color' => '#FFFFFF',
            'header_bg_image' => 'images/default/header_bg_image.png',
            'custom_css' => ''
        );
        
        if ( false === get_option( $design_option_name ) ) {
            add_option( $design_option_name, $design_defaults );
        } else {
            // 既存設定に新しいフィールドが不足している場合は追加
            $existing_design = get_option( $design_option_name );
            $updated = false;
            
            // 古いmain_color、sub_color、tab_bg_colorを削除
            if ( array_key_exists( 'main_color', $existing_design ) ) {
                unset( $existing_design['main_color'] );
                $updated = true;
            }
            if ( array_key_exists( 'sub_color', $existing_design ) ) {
                unset( $existing_design['sub_color'] );
                $updated = true;
            }
            if ( array_key_exists( 'tab_bg_color', $existing_design ) ) {
                unset( $existing_design['tab_bg_color'] );
                $updated = true;
            }
            
            foreach ( $design_defaults as $key => $default_value ) {
                if ( ! array_key_exists( $key, $existing_design ) ) {
                    $existing_design[ $key ] = $default_value;
                    $updated = true;
                }
            }
            
            if ( $updated ) {
                update_option( $design_option_name, $existing_design );
            }
        }
        
        // 旧システムから新システムへのデータ移行処理
        self::migrate_company_info_from_old_system();
        
        self::create_or_update_tables(); // テーブル作成/更新処理を呼び出す
    }

    /**
     * Create or update database tables.
     *
     * @since 1.0.1 // バージョンは適宜更新
     */
    public static function create_or_update_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

        // wp_ktp_client テーブル
        $table_name_client = $wpdb->prefix . 'ktp_client';
        $sql_client = "CREATE TABLE $table_name_client (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            company_name varchar(255) DEFAULT '' NOT NULL,
            name varchar(255) DEFAULT '' NOT NULL,
            email varchar(100) DEFAULT '' NOT NULL,
            memo text,
            category varchar(100) DEFAULT '',
            created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            updated_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            PRIMARY KEY  (id),
            KEY email (email)
        ) $charset_collate;";
        dbDelta( $sql_client );

        // wp_ktp_order テーブル
        $table_name_order = $wpdb->prefix . 'ktp_order';
        $sql_order = "CREATE TABLE $table_name_order (
            id MEDIUMINT(9) NOT NULL AUTO_INCREMENT,
            time BIGINT(11) DEFAULT 0 NOT NULL,
            client_id MEDIUMINT(9) DEFAULT NULL,
            customer_name VARCHAR(100) NOT NULL,
            company_name VARCHAR(255) DEFAULT NULL,
            user_name TINYTEXT,
            project_name VARCHAR(255),
            progress TINYINT(1) NOT NULL DEFAULT 1,
            invoice_items TEXT,
            cost_items TEXT,
            memo TEXT,
            search_field TEXT,
            created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL, 
            updated_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            PRIMARY KEY  (id),
            KEY client_id (client_id) 
        ) $charset_collate;";
        dbDelta( $sql_order );

        // 既存テーブルにカラムを追加
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name_order'") == $table_name_order) {
            $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $table_name_order LIKE 'company_name'");
            if (empty($column_exists)) {
                $wpdb->query("ALTER TABLE $table_name_order ADD company_name VARCHAR(255) DEFAULT NULL;");
            }
        }

        // 他のテーブルも同様に追加・更新

        // デバッグ用: テーブル作成/更新が試行されたことをログに記録
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
            // テーブル構造の確認 (デバッグ時のみ)
        }
    }

    public function add_plugin_page() {
        // メインメニュー
        add_menu_page(
            __( 'KantanPro', 'ktpwp' ), // ページタイトル
            __( 'KantanPro', 'ktpwp' ), // メニュータイトル
            'manage_options', // 権限
            'ktp-settings', // メニューのスラッグ
            array( $this, 'create_general_page' ), // 表示を処理する関数（一般設定を最初に表示）
            'dashicons-admin-generic', // アイコン
            80 // メニューの位置
        );
        
        // サブメニュー - 一般設定（最初に表示）
        add_submenu_page(
            'ktp-settings', // 親メニューのスラッグ
            __( '一般設定', 'ktpwp' ), // ページタイトル
            __( '一般設定', 'ktpwp' ), // メニュータイトル
            'manage_options', // 権限
            'ktp-settings', // メニューのスラッグ（親と同じにすると選択時にハイライト）
            array( $this, 'create_general_page' ) // 表示を処理する関数
        );
        
        // サブメニュー - メール・SMTP設定
        add_submenu_page(
            'ktp-settings', // 親メニューのスラッグ
            __( 'メール・SMTP設定', 'ktpwp' ), // ページタイトル
            __( 'メール・SMTP設定', 'ktpwp' ), // メニュータイトル
            'manage_options', // 権限
            'ktp-mail-settings', // メニューのスラッグ
            array( $this, 'create_admin_page' ) // 表示を処理する関数
        );
        
        // サブメニュー - デザイン設定
        add_submenu_page(
            'ktp-settings', // 親メニューのスラッグ
            __( 'デザイン設定', 'ktpwp' ), // ページタイトル
            __( 'デザイン', 'ktpwp' ), // メニュータイトル
            'manage_options', // 権限
            'ktp-design-settings', // メニューのスラッグ
            array( $this, 'create_design_page' ) // 表示を処理する関数
        );
        
        // サブメニュー - ライセンス設定
        add_submenu_page(
            'ktp-settings', // 親メニューのスラッグ
            __( 'ライセンス設定', 'ktpwp' ), // ページタイトル
            __( 'ライセンス設定', 'ktpwp' ), // メニュータイトル
            'manage_options', // 権限
            'ktp-license', // メニューのスラッグ
            array( $this, 'create_license_page' ) // 表示を処理する関数
        );

        // サブメニュー - スタッフ管理
        add_submenu_page(
            'ktp-settings', // 親メニューのスラッグ
            __( 'スタッフ管理', 'ktpwp' ), // ページタイトル
            __( 'スタッフ管理', 'ktpwp' ), // メニュータイトル
            'manage_options', // 権限
            'ktp-staff', // メニューのスラッグ
            array( $this, 'create_staff_page' ) // 表示を処理する関数
        );
    }
    /**
     * スタッフ管理ページの表示
     */
    public function create_staff_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'この設定ページにアクセスする権限がありません。', 'ktpwp' ) );
        }

        // KantanPro利用権限（ktpwp_access）付加/削除処理
        if ( isset($_POST['ktpwp_access_user']) && isset($_POST['ktpwp_access_action']) && check_admin_referer('ktp_staff_role_action', 'ktp_staff_role_nonce') ) {
            $user_id = intval($_POST['ktpwp_access_user']);
            $action = sanitize_text_field($_POST['ktpwp_access_action']);
            $user_obj = get_userdata($user_id);
            if ($user_obj) {
                if ($action === 'add') {
                    $user_obj->add_cap('ktpwp_access');
                    // 最終変更日時を記録
                    update_user_meta($user_id, 'last_activity', current_time('mysql'));
                    echo '<div class="notice notice-success is-dismissible"><p>KantanPro利用権限（ktpwp_access）を付加しました。</p></div>';
                    
                    // スタッフ追加時のメール通知を送信
                    $mail_sent = $this->send_staff_notification_email($user_obj, 'add');
                    if ($mail_sent) {
                        echo '<div class="notice notice-success is-dismissible"><p>📧 スタッフ追加の通知メールを ' . esc_html($user_obj->user_email) . ' に送信しました。</p></div>';
                    } else {
                        echo '<div class="notice notice-warning is-dismissible"><p>⚠️ スタッフ追加の通知メール送信に失敗しました。メール設定をご確認ください。</p></div>';
                    }
                } elseif ($action === 'remove') {
                    $user_obj->remove_cap('ktpwp_access');
                    // 最終変更日時を記録
                    update_user_meta($user_id, 'last_activity', current_time('mysql'));
                    echo '<div class="notice notice-success is-dismissible"><p>KantanPro利用権限（ktpwp_access）を削除しました。</p></div>';
                    
                    // スタッフ削除時のメール通知を送信
                    $mail_sent = $this->send_staff_notification_email($user_obj, 'remove');
                    if ($mail_sent) {
                        echo '<div class="notice notice-success is-dismissible"><p>📧 スタッフ削除の通知メールを ' . esc_html($user_obj->user_email) . ' に送信しました。</p></div>';
                    } else {
                        echo '<div class="notice notice-warning is-dismissible"><p>⚠️ スタッフ削除の通知メール送信に失敗しました。メール設定をご確認ください。</p></div>';
                    }
                }
            }
        }

        // 管理者以外のユーザーのみ取得
        $users = get_users( array( 'role__not_in' => array('administrator') ) );
        global $wp_roles;
        // $all_roles = $wp_roles->roles; // プルダウンがなくなったため不要
        ?>
        <div class="wrap ktp-admin-wrap">
            <h1><span class="dashicons dashicons-groups"></span> <?php echo esc_html__( 'スタッフ管理', 'ktpwp' ); ?></h1>

            <?php $this->display_settings_tabs('staff'); ?>

            <div class="ktp-settings-container">
                <div class="ktp-settings-section">
                    <h2>登録スタッフ一覧</h2>
                    <div style="margin-bottom: 10px; color: #555; font-size: 13px;">
                        <?php echo esc_html__('管理者は登録者の権限に関わらずここでスタッフの追加削除が行えます', 'ktpwp'); ?>
                    </div>
                    <div style="margin-bottom: 15px; padding: 12px; background: #e7f3ff; border: 1px solid #b3d9ff; border-radius: 4px; font-size: 13px;">
                        <span class="dashicons dashicons-info" style="color: #0073aa; margin-right: 5px;"></span>
                        <strong>メール通知について：</strong>スタッフの追加・削除時に、該当ユーザーへ自動でメール通知が送信されます。
                        通知内容にはログイン情報や権限の変更についての案内が含まれます。
                    </div>
                    <table class="widefat fixed striped ktp-staff-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('表示名', 'ktpwp'); ?></th>
                                <th><?php esc_html_e('メールアドレス', 'ktpwp'); ?></th>
                                <th><?php esc_html_e('スタッフ', 'ktpwp'); ?></th>
                                <th><?php esc_html_e('最終変更日時', 'ktpwp'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ( $users as $user ) : ?>
                            <tr>
                                <td><?php echo esc_html( $user->display_name ); ?></td>
                                <td><?php echo esc_html( $user->user_email ); ?></td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <?php if ( $user->has_cap('ktpwp_access') ) : ?>
                                            <span style="color:green;font-weight:bold;">利用中</span>
                                        <?php else: ?>
                                            <span style="color:red;">未使用</span>
                                        <?php endif; ?>
                                        <form method="post" style="display: flex; align-items: center; gap: 10px; margin-bottom: 0;">
                                            <?php wp_nonce_field('ktp_staff_role_action', 'ktp_staff_role_nonce'); ?>
                                            <input type="hidden" name="ktpwp_access_user" value="<?php echo esc_attr($user->ID); ?>">
                                            <label style="margin-bottom: 0;">
                                                <input type="radio" name="ktpwp_access_action" value="add" <?php checked( ! $user->has_cap('ktpwp_access') ); ?>>
                                                <?php esc_html_e('追加', 'ktpwp'); ?>
                                            </label>
                                            <label style="margin-bottom: 0;">
                                                <input type="radio" name="ktpwp_access_action" value="remove" <?php checked( $user->has_cap('ktpwp_access') ); ?>>
                                                <?php esc_html_e('削除', 'ktpwp'); ?>
                                            </label>
                                            <button type="submit" class="button"><?php esc_html_e('適用', 'ktpwp'); ?></button>
                                        </form>
                                    </div>
                                </td>
                                <td>
                                    <?php
                                    // WordPressのユーザーメタからカスタムフィールドで最終更新日時を取得
                                    $last_modified = get_user_meta($user->ID, 'last_activity', true);
                                    
                                    // カスタムフィールドがない場合は、ユーザー登録日時を使用
                                    if (empty($last_modified)) {
                                        $last_modified = $user->user_registered;
                                    }
                                    
                                    // 日時をフォーマットして表示
                                    if (!empty($last_modified)) {
                                        echo esc_html( date_i18n( 'Y-m-d H:i', strtotime($last_modified) ) );
                                    } else {
                                        echo esc_html__( '未記録', 'ktpwp' );
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * スタッフ追加・削除時のメール通知を送信
     *
     * @since 1.0.0
     * @param WP_User $user_obj 対象ユーザーオブジェクト
     * @param string $action 'add' または 'remove'
     * @return bool 送信成功/失敗
     */
    private function send_staff_notification_email($user_obj, $action) {
        // メールアドレスが存在しない場合は送信しない
        if (empty($user_obj->user_email)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('KTPWP Staff Notification: メールアドレスが未設定のため通知を送信できません (User ID: ' . $user_obj->ID . ')');
            }
            return false;
        }

        // SMTP設定を取得
        $smtp_settings = get_option('ktp_smtp_settings', array());
        $from_email = !empty($smtp_settings['email_address']) ? sanitize_email($smtp_settings['email_address']) : get_option('admin_email');
        $from_name = !empty($smtp_settings['smtp_from_name']) ? sanitize_text_field($smtp_settings['smtp_from_name']) : get_bloginfo('name');

        // 会社情報を取得
        $company_info = self::get_company_info();
        if (empty($company_info)) {
            $company_info = get_bloginfo('name');
        } else {
            // HTMLタグを除去してプレーンテキストに変換
            $company_info = wp_strip_all_tags($company_info);
        }

        // メール内容を生成
        $to = sanitize_email($user_obj->user_email);
        $display_name = !empty($user_obj->display_name) ? $user_obj->display_name : $user_obj->user_login;
        
        if ($action === 'add') {
            $subject = '[' . get_bloginfo('name') . '] スタッフ権限が付与されました';
            $body = $display_name . ' 様' . "\n\n";
            $body .= 'この度、' . get_bloginfo('name') . ' の業務管理システム（KantanPro）のスタッフ権限が付与されました。' . "\n\n";
            $body .= '以下のURLからログインして、システムをご利用ください：' . "\n";
            $body .= wp_login_url() . "\n\n";
            $body .= 'ログイン情報：' . "\n";
            $body .= 'ユーザー名: ' . $user_obj->user_login . "\n";
            $body .= 'メールアドレス: ' . $user_obj->user_email . "\n\n";
            $body .= 'パスワードをお忘れの場合は、ログイン画面の「パスワードをお忘れですか？」からリセットしてください。' . "\n\n";
            $body .= 'ご不明な点がございましたら、システム管理者までお問い合わせください。' . "\n\n";
        } else {
            $subject = '[' . get_bloginfo('name') . '] スタッフ権限が削除されました';
            $body = $display_name . ' 様' . "\n\n";
            $body .= get_bloginfo('name') . ' の業務管理システム（KantanPro）のスタッフ権限が削除されました。' . "\n\n";
            $body .= '今後、システムへのアクセスができなくなります。' . "\n";
            $body .= 'ご質問がございましたら、システム管理者までお問い合わせください。' . "\n\n";
        }

        // 署名を追加
        if (!empty($company_info)) {
            $body .= '―――――――――――――――――――――――――――' . "\n";
            $body .= $company_info . "\n";
        }

        // 自動送信であることを明記
        $body .= "\n※ このメールは自動送信されています。" . "\n";

        // ヘッダーを設定
        $headers = array();
        if (!empty($from_email)) {
            if (!empty($from_name)) {
                $headers[] = 'From: ' . $from_name . ' <' . $from_email . '>';
            } else {
                $headers[] = 'From: ' . $from_email;
            }
        }

        // メール送信を実行
        $sent = wp_mail($to, $subject, $body, $headers);

        // ログ出力（詳細なエラー情報を含む）
        if (defined('WP_DEBUG') && WP_DEBUG) {
            if ($sent) {
                error_log('KTPWP Staff Notification: ' . $action . ' 通知メールを送信しました (User: ' . $display_name . ', Email: ' . $to . ')');
            } else {
                // PHPMailerのエラー情報を取得
                global $phpmailer;
                $error_message = '';
                if (isset($phpmailer) && is_object($phpmailer) && !empty($phpmailer->ErrorInfo)) {
                    $error_message = $phpmailer->ErrorInfo;
                }
                error_log('KTPWP Staff Notification: ' . $action . ' 通知メールの送信に失敗しました (User: ' . $display_name . ', Email: ' . $to . ', Error: ' . $error_message . ')');
            }
        }

        return $sent;
    }

    /**
     * ユーザーの最終ログイン時間を記録
     *
     * @since 1.0.0
     * @param string $user_login ユーザーログイン名
     * @param WP_User $user ユーザーオブジェクト
     * @return void
     */
    public function record_user_last_login($user_login, $user) {
        // KantanPro利用権限を持つユーザーのみ記録
        if ($user->has_cap('ktpwp_access') || $user->has_cap('manage_options')) {
            update_user_meta($user->ID, 'last_activity', current_time('mysql'));
        }
    }

    public function create_admin_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('この設定ページにアクセスする権限がありません。'));
        }

        // 初期設定値がない場合は作成
        if (false === get_option($this->option_name)) {
            add_option($this->option_name, array(
                'email_address' => '',
                'smtp_host' => '',
                'smtp_port' => '',
                'smtp_user' => '',
                'smtp_pass' => '',
                'smtp_secure' => '',
                'smtp_from_name' => ''
            ));
        }

        $options = get_option($this->option_name);
        ?>
        <div class="wrap ktp-admin-wrap">
            <h1><span class="dashicons dashicons-email-alt"></span> <?php echo esc_html__( 'メール・SMTP設定', 'ktpwp' ); ?></h1>
            
            <?php 
            // タブナビゲーション
            $this->display_settings_tabs('mail');
            
            // 通知表示
            settings_errors('ktp_settings');
            
            if (isset($_POST['test_email'])) {
                $this->send_test_email();
            }

            // スタイリングされたコンテナ
            echo '<div class="ktp-settings-container">';
            
            // メール設定フォーム
            echo '<div class="ktp-settings-section">';
            echo '<form method="post" action="options.php">';
            settings_fields($this->options_group);
            
            global $wp_settings_sections, $wp_settings_fields;
            
            // メール設定セクションの出力
            if (isset($wp_settings_sections['ktp-settings']['email_setting_section'])) {
                $section = $wp_settings_sections['ktp-settings']['email_setting_section'];
                echo '<h2>' . esc_html($section['title']) . '</h2>';
                if ($section['callback']) call_user_func($section['callback'], $section);
                if (isset($wp_settings_fields['ktp-settings']['email_setting_section'])) {
                    echo '<table class="form-table">';
                    foreach ($wp_settings_fields['ktp-settings']['email_setting_section'] as $field) {
                        echo '<tr><th scope="row">' . esc_html($field['title']) . '</th><td>';
                        call_user_func($field['callback'], $field['args']);
                        echo '</td></tr>';
                    }
                    echo '</table>';
                }
            }
            
            // SMTP設定セクションの出力
            if (isset($wp_settings_sections['ktp-settings']['smtp_setting_section'])) {
                $section = $wp_settings_sections['ktp-settings']['smtp_setting_section'];
                echo '<h2>' . esc_html($section['title']) . '</h2>';
                if ($section['callback']) call_user_func($section['callback'], $section);
                if (isset($wp_settings_fields['ktp-settings']['smtp_setting_section'])) {
                    echo '<table class="form-table">';
                    foreach ($wp_settings_fields['ktp-settings']['smtp_setting_section'] as $field) {
                        echo '<tr><th scope="row">' . esc_html($field['title']) . '</th><td>';
                        call_user_func($field['callback'], $field['args']);
                        echo '</td></tr>';
                    }
                    echo '</table>';
                }
            }
            
            echo '<div class="ktp-submit-button">';
            submit_button('設定を保存', 'primary', 'submit', false);
            echo '</div>';
            echo '</form>';
            
            // テストメール送信フォーム
            echo '<div class="ktp-test-mail-form">';
            echo '<h3>テストメール送信</h3>';
            echo '<p>SMTPの設定が正しく機能しているか確認するためのテストメールを送信します。</p>';
            echo '<form method="post">';
            echo '<input type="hidden" name="test_email" value="1">';
            submit_button('テストメール送信', 'secondary', 'submit', false);
            echo '</form>';
            echo '</div>';
            
            // 印刷ボタンセクション
            // 印刷機能は削除されました
            
            echo '</div>'; // .ktp-settings-section
            echo '</div>'; // .ktp-settings-container
            ?>
        </div>
        <?php
    }
    
    /**
     * 一般設定ページの表示
     * 
     * @since 1.0.0
     * @return void
     */
    public function create_general_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'この設定ページにアクセスする権限がありません。', 'ktpwp' ) );
        }
        ?>
        <div class="wrap ktp-admin-wrap">
            <h1><span class="dashicons dashicons-admin-settings"></span> <?php echo esc_html__( '一般設定', 'ktpwp' ); ?></h1>
            
            <?php 
            // タブナビゲーション
            $this->display_settings_tabs( 'general' );
            
            // 通知表示
            settings_errors( 'ktp_general_settings' );
            ?>
            
            <div class="ktp-settings-container">
                <div class="ktp-settings-section">
                    <form method="post" action="options.php">
                        <?php
                        settings_fields( 'ktp_general_group' );
                        
                        // 一般設定セクションの出力
                        global $wp_settings_sections, $wp_settings_fields;
                        if ( isset( $wp_settings_sections['ktp-general']['general_setting_section'] ) ) {
                            $section = $wp_settings_sections['ktp-general']['general_setting_section'];
                            echo '<h2>' . esc_html( $section['title'] ) . '</h2>';
                            if ( $section['callback'] ) {
                                call_user_func( $section['callback'], $section );
                            }
                            if ( isset( $wp_settings_fields['ktp-general']['general_setting_section'] ) ) {
                                echo '<table class="form-table">';
                                foreach ( $wp_settings_fields['ktp-general']['general_setting_section'] as $field ) {
                                    echo '<tr><th scope="row">' . esc_html( $field['title'] ) . '</th><td>';
                                    call_user_func( $field['callback'], $field['args'] );
                                    echo '</td></tr>';
                                }
                                echo '</table>';
                            }
                        }
                        ?>
                        
                        <div class="ktp-submit-button">
                            <?php submit_button( __( '設定を保存', 'ktpwp' ), 'primary', 'submit', false ); ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * ライセンス設定ページの表示
     */
    public function create_license_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'この設定ページにアクセスする権限がありません。', 'ktpwp' ) );
        }
        ?>
        <div class="wrap ktp-admin-wrap">
            <h1><span class="dashicons dashicons-admin-network"></span> <?php echo esc_html__( 'ライセンス設定', 'ktpwp' ); ?></h1>
            
            <?php 
            // タブナビゲーション
            $this->display_settings_tabs('license');
            
            // 通知表示
            settings_errors('ktp_activation_key');
            ?>
            
            <div class="ktp-settings-container">
                <div class="ktp-settings-section">
                    <?php
                    // ライセンス設定（アクティベーションキー）フォーム
                    echo '<form method="post" action="options.php">';
                    settings_fields('ktp-group');
                    
                    // ライセンス設定セクションのみ出力
                    global $wp_settings_sections, $wp_settings_fields;
                    if (isset($wp_settings_sections['ktp-settings']['license_setting_section'])) {
                        $section = $wp_settings_sections['ktp-settings']['license_setting_section'];
                        if ($section['callback']) call_user_func($section['callback'], $section);
                        if (isset($wp_settings_fields['ktp-settings']['license_setting_section'])) {
                            echo '<table class="form-table">';
                            foreach ($wp_settings_fields['ktp-settings']['license_setting_section'] as $field) {
                                echo '<tr><th scope="row">' . esc_html($field['title']) . '</th><td>';
                                call_user_func($field['callback'], $field['args']);
                                echo '</td></tr>';
                            }
                            echo '</table>';
                        }
                    }
                    
                    echo '<div class="ktp-submit-button">';
                    submit_button('ライセンスを認証', 'primary', 'submit', false);
                    echo '</div>';
                    echo '</form>';
                    ?>
                    
                    <div class="ktp-license-info">
                        <h3>ライセンスについて</h3>
                        <p>KTPWPプラグインを利用するには有効なライセンスキーが必要です。ライセンスキーに関する問題がございましたら、サポートまでお問い合わせください。</p>
                        <p><a href="mailto:support@example.com" class="button button-secondary">サポートに問い合わせる</a></p>
                    </div>
                    
                    <!-- 印刷ボタンセクション -->
                    <!-- 印刷機能は削除されました -->
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * デザイン設定ページの表示
     */
    public function create_design_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'この設定ページにアクセスする権限がありません。', 'ktpwp' ) );
        }
        ?>
        <div class="wrap ktp-admin-wrap">
            <h1><span class="dashicons dashicons-admin-appearance"></span> <?php echo esc_html__( 'デザイン設定', 'ktpwp' ); ?></h1>
            
            <?php 
            // タブナビゲーション
            $this->display_settings_tabs('design');
            
            // 通知表示
            settings_errors('ktp_design_settings');
            ?>
            
            <div class="ktp-settings-container">
                <div class="ktp-settings-section">
                    <form method="post" action="options.php">
                        <?php
                        settings_fields( 'ktp_design_group' );
                        
                        // デザイン設定セクションの出力
                        global $wp_settings_sections, $wp_settings_fields;
                        if ( isset( $wp_settings_sections['ktp-design']['design_setting_section'] ) ) {
                            $section = $wp_settings_sections['ktp-design']['design_setting_section'];
                            echo '<h2>' . esc_html( $section['title'] ) . '</h2>';
                            if ( $section['callback'] ) {
                                call_user_func( $section['callback'], $section );
                            }
                            if ( isset( $wp_settings_fields['ktp-design']['design_setting_section'] ) ) {
                                echo '<table class="form-table">';
                                foreach ( $wp_settings_fields['ktp-design']['design_setting_section'] as $field ) {
                                    echo '<tr><th scope="row">' . esc_html( $field['title'] ) . '</th><td>';
                                    call_user_func( $field['callback'], $field['args'] );
                                    echo '</td></tr>';
                                }
                                echo '</table>';
                            }
                        }
                        ?>
                        
                        <div class="ktp-submit-button">
                            <?php submit_button( __( '設定を保存', 'ktpwp' ), 'primary', 'submit', false ); ?>
                        </div>
                    </form>
                    
                    <!-- デフォルト設定管理セクション -->
                    <div class="ktp-default-settings-section" style="margin-top: 30px;">
                        <form method="post" action="" onsubmit="return confirm('<?php echo esc_js( __( 'すべてのデザイン設定がデフォルト値にリセットされます。よろしいですか？', 'ktpwp' ) ); ?>');">
                            <?php wp_nonce_field( 'ktp_reset_to_default', 'ktp_reset_to_default_nonce' ); ?>
                            <input type="hidden" name="action" value="reset_to_default">
                            <?php submit_button( __( 'デフォルトに戻す', 'ktpwp' ), 'secondary', 'reset_to_default', false ); ?>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * 設定ページのタブナビゲーションを表示
     *
     * @param string $current_tab 現在選択されているタブ
     */
    private function display_settings_tabs($current_tab) {
        $tabs = array(
            'general' => array(
                'name' => __( '一般設定', 'ktpwp' ),
                'url' => admin_url( 'admin.php?page=ktp-settings' ),
                'icon' => 'dashicons-admin-settings'
            ),
            'mail' => array(
                'name' => __( 'メール・SMTP設定', 'ktpwp' ),
                'url' => admin_url( 'admin.php?page=ktp-mail-settings' ),
                'icon' => 'dashicons-email-alt'
            ),
            'design' => array(
                'name' => __( 'デザイン', 'ktpwp' ),
                'url' => admin_url( 'admin.php?page=ktp-design-settings' ),
                'icon' => 'dashicons-admin-appearance'
            ),
            'license' => array(
                'name' => __( 'ライセンス設定', 'ktpwp' ),
                'url' => admin_url( 'admin.php?page=ktp-license' ),
                'icon' => 'dashicons-admin-network'
            ),
            'staff' => array(
                'name' => __( 'スタッフ管理', 'ktpwp' ),
                'url' => admin_url( 'admin.php?page=ktp-staff' ),
                'icon' => 'dashicons-groups'
            ),
        );

        echo '<h2 class="nav-tab-wrapper">';
        foreach ($tabs as $tab_id => $tab) {
            $active = ($current_tab === $tab_id) ? 'nav-tab-active' : '';
            echo '<a href="' . esc_url($tab['url']) . '" class="nav-tab ' . esc_attr( $active ) . '">';
            echo '<span class="dashicons ' . esc_attr($tab['icon']) . '"></span> ';
            echo esc_html($tab['name']);
            echo '</a>';
        }
        echo '</h2>';
    }

    public function page_init() {

        // アクティベーションキー保存時の通知
        if (isset($_POST['ktp_activation_key'])) {
            $old = get_option('ktp_activation_key');
            $new = sanitize_text_field($_POST['ktp_activation_key']);
            if ($old !== $new) {
                update_option('ktp_activation_key', $new);
                if (method_exists($this, 'show_notification')) {
                    $this->show_notification('アクティベーションキーを保存しました。', true);
                }
                add_settings_error('ktp_activation_key', 'activation_key_saved', 'アクティベーションキーを保存しました。', 'updated');
            }
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // 一般設定グループの登録
        register_setting(
            'ktp_general_group',
            'ktp_general_settings',
            array( $this, 'sanitize_general_settings' )
        );

        register_setting(
            $this->options_group,
            $this->option_name,
            array($this, 'sanitize')
        );
        
        // 以前の設定ページから移行したアクティベーションキー設定
        register_setting(
            'ktp-group',
            'ktp_activation_key'
        );

        // デザイン設定グループの登録
        register_setting(
            'ktp_design_group',
            'ktp_design_settings',
            array( $this, 'sanitize_design_settings' )
        );

        // 一般設定セクション
        add_settings_section(
            'general_setting_section',
            __( '基本設定', 'ktpwp' ),
            array( $this, 'print_general_section_info' ),
            'ktp-general'
        );

        // リストの表示件数
        add_settings_field(
            'work_list_range',
            __( 'リストの表示件数', 'ktpwp' ),
            array( $this, 'work_list_range_callback' ),
            'ktp-general',
            'general_setting_section'
        );

        // 会社情報
        add_settings_field(
            'company_info',
            __( '会社情報', 'ktpwp' ),
            array( $this, 'company_info_callback' ),
            'ktp-general',
            'general_setting_section'
        );

        // メール設定セクション
        add_settings_section(
            'email_setting_section',
            'メール設定',
            array($this, 'print_section_info'),
            'ktp-settings'
        );

        // 自社メールアドレス
        add_settings_field(
            'email_address',
            __( '自社メールアドレス', 'ktpwp' ),
            array( $this, 'email_address_callback' ),
            'ktp-settings',
            'email_setting_section'
        );

        // SMTP設定セクション
        add_settings_section(
            'smtp_setting_section',
            __( 'SMTP設定', 'ktpwp' ),
            array( $this, 'print_smtp_section_info' ),
            'ktp-settings'
        );

        // ライセンス設定セクション
        add_settings_section(
            'license_setting_section',
            __( 'ライセンス設定', 'ktpwp' ),
            array( $this, 'print_license_section_info' ),
            'ktp-settings'
        );

        // デザイン設定セクション
        add_settings_section(
            'design_setting_section',
            __( 'デザイン設定', 'ktpwp' ),
            array( $this, 'print_design_section_info' ),
            'ktp-design'
        );

        // アクティベーションキー
        add_settings_field(
            'activation_key',
            __( 'アクティベーションキー', 'ktpwp' ),
            array( $this, 'activation_key_callback' ),
            'ktp-settings',
            'license_setting_section'
        );

        // SMTPホスト
        add_settings_field(
            'smtp_host',
            __( 'SMTPホスト', 'ktpwp' ),
            array( $this, 'smtp_host_callback' ),
            'ktp-settings',
            'smtp_setting_section'
        );

        // SMTPポート
        add_settings_field(
            'smtp_port',
            __( 'SMTPポート', 'ktpwp' ),
            array( $this, 'smtp_port_callback' ),
            'ktp-settings',
            'smtp_setting_section'
        );

        // SMTPユーザー
        add_settings_field(
            'smtp_user',
            __( 'SMTPユーザー', 'ktpwp' ),
            array( $this, 'smtp_user_callback' ),
            'ktp-settings',
            'smtp_setting_section'
        );

        // SMTPパスワード
        add_settings_field(
            'smtp_pass',
            __( 'SMTPパスワード', 'ktpwp' ),
            array( $this, 'smtp_pass_callback' ),
            'ktp-settings',
            'smtp_setting_section'
        );

        // 暗号化方式
        add_settings_field(
            'smtp_secure',
            __( '暗号化方式', 'ktpwp' ),
            array($this, 'smtp_secure_callback'),
            'ktp-settings',
            'smtp_setting_section'
        );

        // 送信者名
        add_settings_field(
            'smtp_from_name',
            __( '送信者名', 'ktpwp' ),
            array( $this, 'smtp_from_name_callback' ),
            'ktp-settings',
            'smtp_setting_section'
        );

        // デザイン設定フィールド
        // タブのアクティブ時の色
        add_settings_field(
            'tab_active_color',
            __( 'タブのアクティブ時の色', 'ktpwp' ),
            array( $this, 'tab_active_color_callback' ),
            'ktp-design',
            'design_setting_section'
        );

        // タブの非アクティブ時の色（背景色として設定）
        add_settings_field(
            'tab_inactive_color',
            __( 'タブの非アクティブ時の背景色', 'ktpwp' ),
            array( $this, 'tab_inactive_color_callback' ),
            'ktp-design',
            'design_setting_section'
        );

        // タブの下線色
        add_settings_field(
            'tab_border_color',
            __( 'タブの下線色', 'ktpwp' ),
            array( $this, 'tab_border_color_callback' ),
            'ktp-design',
            'design_setting_section'
        );

        // 奇数行の色
        add_settings_field(
            'odd_row_color',
            __( '奇数行の背景色', 'ktpwp' ),
            array( $this, 'odd_row_color_callback' ),
            'ktp-design',
            'design_setting_section'
        );

        // 偶数行の色
        add_settings_field(
            'even_row_color',
            __( '偶数行の背景色', 'ktpwp' ),
            array( $this, 'even_row_color_callback' ),
            'ktp-design',
            'design_setting_section'
        );

        // ヘッダー背景画像
        add_settings_field(
            'header_bg_image',
            __( 'ヘッダー背景画像', 'ktpwp' ),
            array( $this, 'header_bg_image_callback' ),
            'ktp-design',
            'design_setting_section'
        );

        // カスタムCSS
        add_settings_field(
            'custom_css',
            __( 'カスタムCSS', 'ktpwp' ),
            array( $this, 'custom_css_callback' ),
            'ktp-design',
            'design_setting_section'
        );
    }

    public function sanitize($input) {
        $new_input = array();
        
        if (isset($input['email_address']))
            $new_input['email_address'] = sanitize_email($input['email_address']);
            
        if (isset($input['smtp_host']))
            $new_input['smtp_host'] = sanitize_text_field($input['smtp_host']);
            
        if (isset($input['smtp_port']))
            $new_input['smtp_port'] = sanitize_text_field($input['smtp_port']);
            
        if (isset($input['smtp_user']))
            $new_input['smtp_user'] = sanitize_text_field($input['smtp_user']);
            
        if (isset($input['smtp_pass']))
            $new_input['smtp_pass'] = $input['smtp_pass'];
            
        if (isset($input['smtp_secure']))
            $new_input['smtp_secure'] = sanitize_text_field($input['smtp_secure']);
            
        if (isset($input['smtp_from_name']))
            $new_input['smtp_from_name'] = sanitize_text_field($input['smtp_from_name']);

        return $new_input;
    }

    /**
     * デザイン設定のサニタイズ
     *
     * @since 1.0.0
     * @param array $input 入力データ
     * @return array サニタイズされたデータ
     */
    public function sanitize_design_settings( $input ) {
        $new_input = array();

        if ( isset( $input['tab_active_color'] ) ) {
            $new_input['tab_active_color'] = sanitize_hex_color( $input['tab_active_color'] );
        }

        if ( isset( $input['tab_inactive_color'] ) ) {
            $new_input['tab_inactive_color'] = sanitize_hex_color( $input['tab_inactive_color'] );
        }

        if ( isset( $input['tab_border_color'] ) ) {
            $new_input['tab_border_color'] = sanitize_hex_color( $input['tab_border_color'] );
        }

        if ( isset( $input['odd_row_color'] ) ) {
            $new_input['odd_row_color'] = sanitize_hex_color( $input['odd_row_color'] );
        }

        if ( isset( $input['even_row_color'] ) ) {
            $new_input['even_row_color'] = sanitize_hex_color( $input['even_row_color'] );
        }

        if ( isset( $input['header_bg_image'] ) ) {
            // 数値（添付ファイルID）または文字列（画像パス）に対応
            if ( is_numeric( $input['header_bg_image'] ) ) {
                $new_input['header_bg_image'] = absint( $input['header_bg_image'] );
            } else {
                $new_input['header_bg_image'] = sanitize_text_field( $input['header_bg_image'] );
            }
        }

        if ( isset( $input['custom_css'] ) ) {
            $new_input['custom_css'] = wp_strip_all_tags( $input['custom_css'] );
        }

        return $new_input;
    }

    public function print_section_info() {
        echo esc_html__( 'メール送信に関する基本設定を行います。', 'ktpwp' );
    }

    public function print_smtp_section_info() {
        echo esc_html__( 'SMTPサーバーを使用したメール送信の設定を行います。SMTPを利用しない場合は空欄のままにしてください。', 'ktpwp' );
    }
    
    public function print_license_section_info() {
        echo esc_html__( 'プラグインのライセンス情報を設定します。', 'ktpwp' );
    }

    /**
     * デザイン設定セクションの説明
     *
     * @since 1.0.0
     * @return void
     */
    public function print_design_section_info() {
        echo esc_html__( 'プラグインの外観とデザインに関する設定を行います。', 'ktpwp' );
    }

    public function activation_key_callback() {
        $activation_key = get_option('ktp_activation_key');
        $has_license = !empty($activation_key);
        ?>
        <input type="text" id="ktp_activation_key" name="ktp_activation_key" 
               value="<?php echo esc_attr($activation_key); ?>" 
               style="width:320px;max-width:100%;"
               placeholder="XXXX-XXXX-XXXX-XXXX">
        <div class="ktp-license-status <?php echo $has_license ? 'active' : 'inactive'; ?>">
            <?php if ($has_license): ?>
                <span class="dashicons dashicons-yes-alt"></span> ライセンスキーが登録されています
            <?php else: ?>
                <span class="dashicons dashicons-warning"></span> ライセンスキーが未登録です
            <?php endif; ?>
        </div>
        <div style="font-size:12px;color:#555;margin-top:8px;">※ プラグインのライセンスキーを入力して、機能を有効化してください。</div>
        <?php
    }

    public function email_address_callback() {
        $options = get_option($this->option_name);
        ?>
        <input type="email" id="email_address" name="<?php echo esc_attr($this->option_name); ?>[email_address]" 
               value="<?php echo isset($options['email_address']) ? esc_attr($options['email_address']) : ''; ?>" 
               style="width:320px;max-width:100%;" required 
               pattern="^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$" 
               placeholder="info@example.com">
        <div style="font-size:12px;color:#555;margin-top:4px;">※ サイトから届くメールが迷惑メールと認識されないよう、サイトのドメインと同じメールアドレスをご入力ください。</div>
        <?php
    }

    public function smtp_host_callback() {
        $options = get_option($this->option_name);
        ?>
        <input type="text" id="smtp_host" name="<?php echo esc_attr($this->option_name); ?>[smtp_host]" 
               value="<?php echo isset($options['smtp_host']) ? esc_attr($options['smtp_host']) : ''; ?>" 
               style="width:220px;max-width:100%;" 
               placeholder="smtp.example.com">
        <?php
    }

    public function smtp_port_callback() {
        $options = get_option($this->option_name);
        ?>
        <input type="text" id="smtp_port" name="<?php echo esc_attr($this->option_name); ?>[smtp_port]" 
               value="<?php echo isset($options['smtp_port']) ? esc_attr($options['smtp_port']) : ''; ?>" 
               style="width:80px;max-width:100%;" 
               placeholder="587">
        <?php
    }

    public function smtp_user_callback() {
        $options = get_option($this->option_name);
        ?>
        <input type="text" id="smtp_user" name="<?php echo esc_attr($this->option_name); ?>[smtp_user]" 
               value="<?php echo isset($options['smtp_user']) ? esc_attr($options['smtp_user']) : ''; ?>" 
               style="width:220px;max-width:100%;" 
               placeholder="user@example.com">
        <?php
    }

    public function smtp_pass_callback() {
        $options = get_option($this->option_name);
        ?>
        <input type="password" id="smtp_pass" name="<?php echo esc_attr($this->option_name); ?>[smtp_pass]" 
               value="<?php echo isset($options['smtp_pass']) ? esc_attr($options['smtp_pass']) : ''; ?>" 
               style="width:220px;max-width:100%;" 
               autocomplete="off">
        <?php
    }

    public function smtp_secure_callback() {
        $options = get_option($this->option_name);
        $selected = isset($options['smtp_secure']) ? $options['smtp_secure'] : '';
        ?>
        <select id="smtp_secure" name="<?php echo $this->option_name; ?>[smtp_secure]">
            <option value="" <?php selected($selected, ''); ?>>なし</option>
            <option value="ssl" <?php selected($selected, 'ssl'); ?>>SSL</option>
            <option value="tls" <?php selected($selected, 'tls'); ?>>TLS</option>
        </select>
        <?php
    }

    public function smtp_from_name_callback() {
        $options = get_option($this->option_name);
        ?>
        <input type="text" id="smtp_from_name" name="<?php echo esc_attr($this->option_name); ?>[smtp_from_name]" 
               value="<?php echo isset($options['smtp_from_name']) ? esc_attr($options['smtp_from_name']) : ''; ?>" 
               style="width:220px;max-width:100%;" 
               placeholder="会社名や担当者名">
        <?php
    }

    public function setup_smtp_settings($phpmailer) {
        try {
            $options = get_option($this->option_name);
            
            if (!empty($options['smtp_host']) && !empty($options['smtp_port']) && !empty($options['smtp_user']) && !empty($options['smtp_pass'])) {
                $phpmailer->isSMTP();
                $phpmailer->Host = $options['smtp_host'];
                $phpmailer->Port = $options['smtp_port'];
                $phpmailer->SMTPAuth = true;
                $phpmailer->Username = $options['smtp_user'];
                $phpmailer->Password = $options['smtp_pass'];
                
                if (!empty($options['smtp_secure'])) {
                    $phpmailer->SMTPSecure = $options['smtp_secure'];
                }
                
                $phpmailer->CharSet = 'UTF-8';
                
                if (!empty($options['email_address'])) {
                    $phpmailer->setFrom(
                        $options['email_address'],
                        !empty($options['smtp_from_name']) ? $options['smtp_from_name'] : $options['email_address'],
                        false
                    );
                }
            }
        } catch (Throwable $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) { error_log($e->getMessage()); }
        }
    }

    private function send_test_email() {
        $options = get_option($this->option_name);
        $to = $options['email_address'];
        $subject = '【KTPWP】SMTPテストメール';
        $body = "このメールはKTPWPプラグインのSMTPテスト送信です。\n\n送信元: {$options['email_address']}";
        $headers = array();
        
        if (!empty($options['smtp_from_name'])) {
            $headers[] = 'From: ' . $options['smtp_from_name'] . ' <' . $options['email_address'] . '>';
        } else {
            $headers[] = 'From: ' . $options['email_address'];
        }

        $sent = wp_mail($to, $subject, $body, $headers);
        
        if ($sent) {
            $this->test_mail_message = 'テストメールを送信しました。メールボックスをご確認ください。';
            $this->test_mail_status = 'success';
            
            // 成功通知を表示
            $this->show_notification('✉️ テストメールを送信しました。メールボックスをご確認ください。', true);
            
            add_settings_error(
                'ktp_settings',
                'test_mail_success',
                'テストメールを送信しました。メールボックスをご確認ください。',
                'updated'
            );
        } else {
            global $phpmailer;
            $error_message = '';
            if (isset($phpmailer) && is_object($phpmailer)) {
                $error_message = $phpmailer->ErrorInfo;
                if (defined('WP_DEBUG') && WP_DEBUG) { error_log('KTPWP SMTPテストメール送信失敗: ' . $error_message); }
            } else {
                $error_message = 'PHPMailerインスタンスが取得できませんでした';
                error_log('KTPWP SMTPテストメール送信失敗: ' . $error_message);
            }
            
            $this->test_mail_message = 'テストメールの送信に失敗しました。SMTP設定をご確認ください。';
            $this->test_mail_status = 'error';
            
            // エラー通知を表示
            $this->show_notification('⚠️ テストメールの送信に失敗しました。SMTP設定をご確認ください。', false);
            
            add_settings_error(
                'ktp_settings',
                'test_mail_error',
                'テストメールの送信に失敗しました。SMTP設定をご確認ください。',
                'error'
            );
        }
    }
    
    /**
     * 新しいフローティング通知システムを使用して通知を表示する
     *
     * @param string $message 表示するメッセージ
     * @param bool $success 成功メッセージかどうか（true=成功、false=エラー）
     */
    private function show_notification($message, $success = true) {
        $notification_type = $success ? 'success' : 'error';
        
        echo '<script>
            document.addEventListener("DOMContentLoaded", function() {
                if (typeof showKtpNotification === "function") {
                    showKtpNotification("' . esc_js($message) . '", "' . $notification_type . '");
                } else {
                    // フォールバック: 古い通知システム
                    console.warn("KTP Notification system not loaded, using fallback");
                    alert("' . esc_js($message) . '");
                }
            });
        </script>';
    }

    /**
     * 一般設定のサニタイズ処理
     *
     * @since 1.0.0
     * @param array $input 入力値
     * @return array サニタイズされた値
     */
    public function sanitize_general_settings( $input ) {
        $new_input = array();
        
        if ( isset( $input['work_list_range'] ) ) {
            $range = intval( $input['work_list_range'] );
            // 最小5件、最大500件に制限
            $new_input['work_list_range'] = max( 5, min( 500, $range ) );
        }

        if ( isset( $input['company_info'] ) ) {
            // HTMLコンテンツを許可し、wp_ksesで安全なHTMLタグのみ保持
            $allowed_html = array(
                'br' => array(),
                'p' => array(),
                'strong' => array(),
                'b' => array(),
                'em' => array(),
                'i' => array(),
                'u' => array(),
                'a' => array(
                    'href' => array(),
                    'target' => array(),
                    'rel' => array(),
                ),
                'span' => array(
                    'style' => array(),
                ),
                'div' => array(
                    'style' => array(),
                ),
            );
            $new_input['company_info'] = wp_kses( $input['company_info'], $allowed_html );
        }

        return $new_input;
    }

    /**
     * 一般設定セクションの説明
     *
     * @since 1.0.0
     * @return void
     */
    public function print_general_section_info() {
        echo esc_html__( 'プラグインの基本設定を行います。', 'ktpwp' );
    }

    /**
     * 仕事リスト表示件数フィールドのコールバック
     *
     * @since 1.0.0
     * @return void
     */
    public function work_list_range_callback() {
        $options = get_option( 'ktp_general_settings' );
        $value = isset( $options['work_list_range'] ) ? $options['work_list_range'] : 20;
        ?>
        <select id="work_list_range" name="ktp_general_settings[work_list_range]">
            <option value="5" <?php selected( $value, 5 ); ?>>5件</option>
            <option value="10" <?php selected( $value, 10 ); ?>>10件</option>
            <option value="20" <?php selected( $value, 20 ); ?>>20件</option>
            <option value="30" <?php selected( $value, 30 ); ?>>30件</option>
            <option value="50" <?php selected( $value, 50 ); ?>>50件</option>
            <option value="100" <?php selected( $value, 100 ); ?>>100件</option>
            <option value="200" <?php selected( $value, 200 ); ?>>200件</option>
            <option value="500" <?php selected( $value, 500 ); ?>>500件</option>
        </select>
        <div style="font-size:12px;color:#555;margin-top:4px;">
            <?php echo esc_html__( '※ リストで一度に表示する件数を設定してください。', 'ktpwp' ); ?>
        </div>
        <?php
    }

    /**
     * 会社情報フィールドのコールバック
     *
     * @since 1.0.0
     * @return void
     */
    public function company_info_callback() {
        $options = get_option( 'ktp_general_settings' );
        $value = isset( $options['company_info'] ) ? $options['company_info'] : '';
        // nullや非文字列の場合は空文字列に変換
        $value = is_string($value) ? $value : '';
        // WordPress Visual Editor (TinyMCE) を表示
        $editor_id = 'company_info_editor';
        $settings = array(
            'textarea_name' => 'ktp_general_settings[company_info]',
            'media_buttons' => true,
            'tinymce' => array(
                'height' => 200,
                'toolbar1' => 'formatselect bold italic underline | alignleft aligncenter alignright alignjustify | removeformat',
                'toolbar2' => 'styleselect | forecolor backcolor | table | charmap | pastetext | code',
                'toolbar3' => '',
                'wp_adv' => false,
            ),
            'default_editor' => 'tinymce',
        );
        wp_editor( $value, $editor_id, $settings );
        ?>
        <div style="font-size:12px;color:#555;margin-top:8px;">
            <?php echo esc_html__( '※ メール送信時に署名として使用される会社情報です。HTMLタグが使用できます。', 'ktpwp' ); ?>
        </div>
        <?php
    }

    /**
     * 旧システムから新システムへのデータ移行処理
     *
     * @since 1.0.0
     */
    private static function migrate_company_info_from_old_system() {
        global $wpdb;
        
        // 移行済みフラグをチェック
        if ( get_option( 'ktp_company_info_migrated' ) ) {
            return; // 既に移行済み
        }
        
        // 旧設定テーブルから会社情報を取得
        $setting_table = $wpdb->prefix . 'ktp_setting';
        $old_setting = $wpdb->get_row( $wpdb->prepare( 
            "SELECT my_company_content FROM {$setting_table} WHERE id = %d", 
            1 
        ) );
        
        if ( $old_setting && ! empty( $old_setting->my_company_content ) ) {
            // 現在の一般設定を取得
            $general_settings = get_option( 'ktp_general_settings', array() );
            
            // 会社情報が未設定の場合のみ移行
            if ( empty( $general_settings['company_info'] ) ) {
                $general_settings['company_info'] = $old_setting->my_company_content;
                update_option( 'ktp_general_settings', $general_settings );
            }
        }
        
        // 移行完了フラグを設定
        update_option( 'ktp_company_info_migrated', true );
    }

    /**
     * タブのアクティブ時の色フィールドのコールバック
     *
     * @since 1.0.0
     * @return void
     */
    public function tab_active_color_callback() {
        $options = get_option( 'ktp_design_settings' );
        $value = isset( $options['tab_active_color'] ) ? $options['tab_active_color'] : '#cdcccc';
        ?>
        <input type="color" id="tab_active_color" name="ktp_design_settings[tab_active_color]" 
               value="<?php echo esc_attr( $value ); ?>" 
               style="width:100px;height:40px;">
        <div style="font-size:12px;color:#555;margin-top:4px;">
            <?php echo esc_html__( '※ アクティブなタブの背景色を設定してください。', 'ktpwp' ); ?>
        </div>
        <?php
    }

    /**
     * タブの非アクティブ時の背景色フィールドのコールバック
     *
     * @since 1.0.0
     * @return void
     */
    public function tab_inactive_color_callback() {
        $options = get_option( 'ktp_design_settings' );
        $value = isset( $options['tab_inactive_color'] ) ? $options['tab_inactive_color'] : '#bbbbbb';
        ?>
        <input type="color" id="tab_inactive_color" name="ktp_design_settings[tab_inactive_color]" 
               value="<?php echo esc_attr( $value ); ?>" 
               style="width:100px;height:40px;">
        <div style="font-size:12px;color:#555;margin-top:4px;">
            <?php echo esc_html__( '※ 非アクティブなタブの背景色を設定してください。', 'ktpwp' ); ?>
        </div>
        <?php
    }

    /**
     * タブの下線色フィールドのコールバック
     *
     * @since 1.0.0
     * @return void
     */
    public function tab_border_color_callback() {
        $options = get_option( 'ktp_design_settings' );
        $value = isset( $options['tab_border_color'] ) ? $options['tab_border_color'] : '#cdcccc';
        ?>
        <input type="color" id="tab_border_color" name="ktp_design_settings[tab_border_color]" 
               value="<?php echo esc_attr( $value ); ?>" 
               style="width:100px;height:40px;">
        <div style="font-size:12px;color:#555;margin-top:4px;">
            <?php echo esc_html__( '※ タブの下線（border-bottom）の色を設定してください。', 'ktpwp' ); ?>
        </div>
        <?php
    }

    /**
     * 奇数行の背景色フィールドのコールバック
     *
     * @since 1.0.0
     * @return void
     */
    public function odd_row_color_callback() {
        $options = get_option( 'ktp_design_settings' );
        $value = isset( $options['odd_row_color'] ) ? $options['odd_row_color'] : '#ffffff';
        ?>
        <input type="color" id="odd_row_color" name="ktp_design_settings[odd_row_color]" 
               value="<?php echo esc_attr( $value ); ?>" 
               style="width:100px;height:40px;">
        <div style="font-size:12px;color:#555;margin-top:4px;">
            <?php echo esc_html__( '※ リスト表示で奇数行（1行目、3行目など）の背景色を設定してください。', 'ktpwp' ); ?>
        </div>
        <?php
    }

    /**
     * 偶数行の背景色フィールドのコールバック
     *
     * @since 1.0.0
     * @return void
     */
    public function even_row_color_callback() {
        $options = get_option( 'ktp_design_settings' );
        $value = isset( $options['even_row_color'] ) ? $options['even_row_color'] : '#f9f9f9';
        ?>
        <input type="color" id="even_row_color" name="ktp_design_settings[even_row_color]" 
               value="<?php echo esc_attr( $value ); ?>" 
               style="width:100px;height:40px;">
        <div style="font-size:12px;color:#555;margin-top:4px;">
            <?php echo esc_html__( '※ リスト表示で偶数行（2行目、4行目など）の背景色を設定してください。', 'ktpwp' ); ?>
        </div>
        <?php
    }

    /**
     * ヘッダー背景画像フィールドのコールバック
     *
     * @since 1.0.0
     * @return void
     */
    public function header_bg_image_callback() {
        $options = get_option( 'ktp_design_settings' );
        $image_value = isset( $options['header_bg_image'] ) ? $options['header_bg_image'] : 'images/default/header_bg_image.png';
        $image_url = '';
        
        // 数値の場合は添付ファイルID、文字列の場合は画像パス
        // デフォルト値がある場合は常に画像URLを設定
        if ( is_numeric( $image_value ) ) {
            // 添付ファイルIDの場合
            $image_url = wp_get_attachment_image_url( $image_value, 'full' );
        } else {
            // 文字列パスの場合
            $image_path = $image_value;
            if ( strpos( $image_path, 'http' ) !== 0 ) {
                // 相対パスの場合は、プラグインディレクトリからの絶対URLに変換
                $image_url = plugin_dir_url( dirname( __FILE__ ) ) . $image_path;
            } else {
                $image_url = $image_path;
            }
        }
        ?>
        <div class="ktp-image-upload-field">
            <input type="hidden" id="header_bg_image" name="ktp_design_settings[header_bg_image]" value="<?php echo esc_attr( $image_value ); ?>" data-default-url="<?php echo esc_url( plugin_dir_url( dirname( __FILE__ ) ) . 'images/default/header_bg_image.png' ); ?>" />
            
            <div class="ktp-image-preview" style="margin-bottom: 10px;">
                <img id="header_bg_image_preview" src="<?php echo esc_url( $image_url ); ?>" style="max-width: 300px; max-height: 200px; border: 1px solid #ddd; border-radius: 4px;" />
                <br>
                <button type="button" class="button ktp-remove-image" style="margin-top: 5px;">画像を削除</button>
            </div>
            
            <button type="button" class="button ktp-upload-image">
                画像を変更
            </button>
            
            <div style="font-size:12px;color:#555;margin-top:4px;">
                <?php echo esc_html__( '※ ヘッダーの背景画像として使用されます。推奨サイズ: 1920×100px', 'ktpwp' ); ?>
            </div>
        </div>
        <?php
    }

    /**
     * カスタムCSSフィールドのコールバック
     *
     * @since 1.0.0
     * @return void
     */
    public function custom_css_callback() {
        $options = get_option( 'ktp_design_settings' );
        $value = isset( $options['custom_css'] ) ? $options['custom_css'] : '';
        ?>
        <textarea id="custom_css" name="ktp_design_settings[custom_css]" 
                  rows="10" cols="80" style="width:100%;max-width:600px;font-family:monospace;" 
                  placeholder="<?php echo esc_attr__( 'カスタムCSSを入力してください...', 'ktpwp' ); ?>"><?php echo esc_textarea( $value ); ?></textarea>
        <div style="font-size:12px;color:#555;margin-top:4px;">
            <?php echo esc_html__( '※ プラグインに適用するカスタムCSSを記述してください。HTMLタグは使用できません。', 'ktpwp' ); ?>
        </div>
        <?php
    }

    /**
     * Output custom styles to frontend
     *
     * @since 1.0.0
     * @return void
     */
    public function output_custom_styles() {
        $design_options = get_option( 'ktp_design_settings', array() );
        
        // デザイン設定が存在しない場合は何もしない
        if ( empty( $design_options ) ) {
            return;
        }
        
        $custom_css = '';
        
        // div.ktp_headerの基本スタイル
        $custom_css .= '
div.ktp_header {
    border: none !important;
    margin-bottom: 10px;
    position: relative;
}';

        // タブを手前に表示するためのz-index設定
        $custom_css .= '
.tabs {
    z-index: 200;
    position: relative;
}';
        
        // ヘッダー背景画像の設定
        $header_bg_image = ! empty( $design_options['header_bg_image'] ) ? $design_options['header_bg_image'] : 'images/default/header_bg_image.png';
        $image_url = '';
        
        // 数値の場合は添付ファイルID、文字列の場合は画像パス
        if ( is_numeric( $header_bg_image ) ) {
            // 添付ファイルIDの場合
            $image_url = wp_get_attachment_image_url( $header_bg_image, 'full' );
        } else {
            // 文字列パスの場合
            $image_path = $header_bg_image;
            if ( strpos( $image_path, 'http' ) !== 0 ) {
                // 相対パスの場合は、プラグインディレクトリからの絶対URLに変換
                $image_url = plugin_dir_url( dirname( __FILE__ ) ) . $image_path;
            } else {
                $image_url = $image_path;
            }
        }
        
        if ( $image_url ) {
                $custom_css .= '
div.ktp_header {
    background-image: url(' . esc_url( $image_url ) . ');
    background-size: cover;
    background-position: center center;
    background-repeat: no-repeat;
    border: none !important;
    width: 100%;
    height: 100px;
    max-width: 1920px;
    margin: 0 auto 10px auto;
    position: relative;
    overflow: hidden;
}

div.ktp_header::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: transparent;
    z-index: 1;
}

div.ktp_header > * {
    position: relative;
    z-index: 2;
}';
        }
        
        // タブのアクティブ時の色設定
        if ( ! empty( $design_options['tab_active_color'] ) ) {
            $tab_active_color = sanitize_hex_color( $design_options['tab_active_color'] );
            if ( $tab_active_color ) {
                $custom_css .= '
.tabs input:checked + .tab_item,
.tab_item.active {
    background-color: ' . esc_attr( $tab_active_color ) . ' !important;
}';
            }
        }
        
        // タブの非アクティブ時の色設定（背景色として設定）
        if ( ! empty( $design_options['tab_inactive_color'] ) ) {
            $tab_inactive_color = sanitize_hex_color( $design_options['tab_inactive_color'] );
            if ( $tab_inactive_color ) {
                $custom_css .= '
.tab_item {
    background-color: ' . esc_attr( $tab_inactive_color ) . ' !important;
}';
            }
        }
        
        // タブの下線色設定
        if ( ! empty( $design_options['tab_border_color'] ) ) {
            $tab_border_color = sanitize_hex_color( $design_options['tab_border_color'] );
            if ( $tab_border_color ) {
                $custom_css .= '
.tab_item {
    border-bottom-color: ' . esc_attr( $tab_border_color ) . ' !important;
}';
            }
        }
        
        // 奇数行の背景色設定
        if ( ! empty( $design_options['odd_row_color'] ) ) {
            $odd_row_color = sanitize_hex_color( $design_options['odd_row_color'] );
            if ( $odd_row_color ) {
                $custom_css .= '
/* KTPWPプラグイン用奇数行色設定 - 固有プレフィックス付きでテーマとの競合を防止 */
.ktp_data_list_box .ktp_list_item:nth-child(odd),
.ktp_data_list_box > a:nth-of-type(odd) .ktp_data_list_item,
.ktp_data_list_box > .ktp_data_list_item:nth-of-type(odd),
.ktp_work_list_box .ktp_work_list_item:nth-child(odd),
.ktp_work_list_box ul li:nth-child(odd),
.ktp_work_list_item:nth-child(odd),
.ktp_list_item:nth-child(odd),
.ktp_plugin_container ul li:nth-child(odd),
.ktp_data_contents .ktp_data_list_box > a:nth-of-type(odd) .ktp_data_list_item,
.ktp_search_list_box ul li:nth-child(odd),
.ktp_search_list_box > a:nth-of-type(odd) .ktp_data_list_item,
.ktp_plugin_container tr:nth-child(odd),
.ktp_plugin_container tbody tr:nth-child(odd) {
    background-color: ' . esc_attr( $odd_row_color ) . ' !important;
}';
            }
        }
        
        // 偶数行の背景色設定
        if ( ! empty( $design_options['even_row_color'] ) ) {
            $even_row_color = sanitize_hex_color( $design_options['even_row_color'] );
            if ( $even_row_color ) {
                $custom_css .= '
/* KTPWPプラグイン用偶数行色設定 - 固有プレフィックス付きでテーマとの競合を防止 */
.ktp_data_list_box .ktp_list_item:nth-child(even),
.ktp_data_list_box > a:nth-of-type(even) .ktp_data_list_item,
.ktp_data_list_box > .ktp_data_list_item:nth-of-type(even),
.ktp_work_list_box .ktp_work_list_item:nth-child(even),
.ktp_work_list_box ul li:nth-child(even),
.ktp_work_list_item:nth-child(even),
.ktp_list_item:nth-child(even),
.ktp_plugin_container ul li:nth-child(even),
.ktp_data_contents .ktp_data_list_box > a:nth-of-type(even) .ktp_data_list_item,
.ktp_search_list_box ul li:nth-child(even),
.ktp_search_list_box > a:nth-of-type(even) .ktp_data_list_item,
.ktp_plugin_container tr:nth-child(even),
.ktp_plugin_container tbody tr:nth-child(even) {
    background-color: ' . esc_attr( $even_row_color ) . ' !important;
}';
            }
        }
        
        // カスタムCSSの追加
        if ( ! empty( $design_options['custom_css'] ) ) {
            $custom_css .= "\n" . wp_strip_all_tags( $design_options['custom_css'] );
        }
        
        // スタイルを出力
        if ( ! empty( $custom_css ) ) {
            echo '<style type="text/css" id="ktp-custom-styles">';
            echo $custom_css;
            echo '</style>';
        }
    }

    /**
     * デフォルト設定管理のアクションを処理
     *
     * @since 1.0.0
     * @return void
     */
    public function handle_default_settings_actions() {
        // 管理者権限チェック
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // デザイン設定ページでのみ実行
        if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'ktp-design-settings' ) {
            return;
        }

        // 設定をデフォルト値にリセット
        if ( isset( $_POST['action'] ) && $_POST['action'] === 'reset_to_default' ) {
            if ( ! wp_verify_nonce( $_POST['ktp_reset_to_default_nonce'], 'ktp_reset_to_default' ) ) {
                wp_die( __( 'セキュリティチェックに失敗しました。', 'ktpwp' ) );
            }

            // システムデフォルト値を使用
            $system_defaults = array(
                'tab_active_color' => '#B7CBFB',
                'tab_inactive_color' => '#E6EDFF',
                'tab_border_color' => '#B7CBFB',
                'odd_row_color' => '#E7EEFD',
                'even_row_color' => '#FFFFFF',
                'header_bg_image' => 'images/default/header_bg_image.png',
                'custom_css' => ''
            );
            update_option( 'ktp_design_settings', $system_defaults );
            add_settings_error( 
                'ktp_design_settings', 
                'reset_to_default', 
                __( 'デザイン設定をデフォルト値にリセットしました。', 'ktpwp' ), 
                'updated' 
            );
            
            // リダイレクトでページを再読み込みし、フォームの再送信を防ぐ
            wp_redirect( admin_url( 'admin.php?page=ktp-design-settings&settings-updated=true' ) );
            exit;
        }
    }

}

// インスタンスを初期化
KTP_Settings::get_instance();
