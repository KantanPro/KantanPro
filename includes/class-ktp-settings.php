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
     * Get delivery warning days setting
     *
     * @since 1.0.0
     * @return int Delivery warning days setting (default: 3)
     */
    public static function get_delivery_warning_days() {
        $options = get_option( 'ktp_general_settings', array() );
        return isset( $options['delivery_warning_days'] ) ? intval( $options['delivery_warning_days'] ) : 3;
    }

    /**
     * Get qualified invoice number setting
     *
     * @since 1.0.0
     * @return string Qualified invoice number setting
     */
    public static function get_qualified_invoice_number() {
        $options = get_option( 'ktp_general_settings', array() );
        return isset( $options['qualified_invoice_number'] ) ? $options['qualified_invoice_number'] : '';
    }

    /**
     * Get company info setting
     *
     * @since 1.0.0
     * @return string Company info setting
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
            'custom_css' => '',
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
                return plugin_dir_url( __DIR__ ) . $image_path;
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
        add_action( 'admin_head', array( $this, 'output_custom_styles' ) );
        add_action( 'admin_init', array( $this, 'handle_default_settings_actions' ) );
        add_action( 'admin_init', array( $this, 'init_payment_settings' ) );

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
                plugin_dir_url( __DIR__ ) . 'js/ktp-media-upload.js',
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
                plugin_dir_url( __DIR__ ) . 'css/ktp-admin-settings.css',
                array(),
                '1.0.1'
            );

            wp_enqueue_style(
                'ktp-setting-tab',
                plugin_dir_url( __DIR__ ) . 'css/ktp-setting-tab.css',
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
            add_option(
                $option_name,
                array(
					'email_address' => '',
					'smtp_host' => '',
					'smtp_port' => '',
					'smtp_user' => '',
					'smtp_pass' => '',
					'smtp_secure' => '',
					'smtp_from_name' => '',
                )
            );
        }

        // 一般設定のデフォルト値を設定
        $general_option_name = 'ktp_general_settings';
        if ( false === get_option( $general_option_name ) ) {
            add_option(
                $general_option_name,
                array(
					'work_list_range' => 20,
					'delivery_warning_days' => 3,
					'qualified_invoice_number' => '',
					'company_info' => '',
                )
            );
        } else {
            // 既存設定に新しいフィールドが不足している場合は追加
            $existing_general = get_option( $general_option_name );
            $general_updated = false;

            // 適格請求書番号フィールドが存在しない場合は追加
            if ( ! array_key_exists( 'qualified_invoice_number', $existing_general ) ) {
                $existing_general['qualified_invoice_number'] = '';
                $general_updated = true;
            }

            if ( $general_updated ) {
                update_option( $general_option_name, $existing_general );
            }
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
            'custom_css' => '',
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

        // 決済設定のデフォルト値を保存
        $payment_option_name = 'ktp_payment_settings';
        $payment_defaults = array(
            'stripe_publishable_key' => 'pk_live_51RiD7U09Cs900UhV74cscfgq2LbjWJgec9X3HdKTdMAryOMeno9tgBhbe6UchBqyUX7qUa1fhgTSoqwSjbg78kjv005XjrsP1q',
            'stripe_secret_key'      => self::strong_encrypt_static('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'),
            'edd_api_key'            => self::strong_encrypt_static('58c310e565651392890a7a28cd6527b6'),
            'edd_api_token'          => self::strong_encrypt_static('45055ce430c876042c6b9571508d5977'),
        );
        
        if ( false === get_option( $payment_option_name ) ) {
            add_option( $payment_option_name, $payment_defaults );
        } else {
            // 既存設定がある場合は、強固な暗号化で更新
            $existing_payment = get_option( $payment_option_name );
            $payment_updated = false;
            
            // EDD APIキーがbase64暗号化または平文の場合は強固な暗号化に移行
            if ( isset( $existing_payment['edd_api_key'] ) && ! empty( $existing_payment['edd_api_key'] ) ) {
                $decrypted_key = self::strong_decrypt_static( $existing_payment['edd_api_key'] );
                if ( empty( $decrypted_key ) ) {
                    // base64暗号化または平文の場合
                    $maybe_old = base64_decode( $existing_payment['edd_api_key'] );
                    if ( $maybe_old && preg_match('/^[0-9a-f]{32}$/', $maybe_old) ) {
                        $existing_payment['edd_api_key'] = self::strong_encrypt_static( $maybe_old );
                        $payment_updated = true;
                    } elseif ( preg_match('/^[0-9a-f]{32}$/', $existing_payment['edd_api_key'] ) ) {
                        // 平文の場合
                        $existing_payment['edd_api_key'] = self::strong_encrypt_static( $existing_payment['edd_api_key'] );
                        $payment_updated = true;
                    }
                }
            } else {
                // 設定が空の場合はデフォルト値を設定
                $existing_payment['edd_api_key'] = self::strong_encrypt_static('58c310e565651392890a7a28cd6527b6');
                $payment_updated = true;
            }
            
            // EDD APIトークンがbase64暗号化または平文の場合は強固な暗号化に移行
            if ( isset( $existing_payment['edd_api_token'] ) && ! empty( $existing_payment['edd_api_token'] ) ) {
                $decrypted_token = self::strong_decrypt_static( $existing_payment['edd_api_token'] );
                if ( empty( $decrypted_token ) ) {
                    // base64暗号化または平文の場合
                    $maybe_old = base64_decode( $existing_payment['edd_api_token'] );
                    if ( $maybe_old && preg_match('/^[0-9a-f]{32}$/', $maybe_old) ) {
                        $existing_payment['edd_api_token'] = self::strong_encrypt_static( $maybe_old );
                        $payment_updated = true;
                    } elseif ( preg_match('/^[0-9a-f]{32}$/', $existing_payment['edd_api_token'] ) ) {
                        // 平文の場合
                        $existing_payment['edd_api_token'] = self::strong_encrypt_static( $existing_payment['edd_api_token'] );
                        $payment_updated = true;
                    }
                }
            } else {
                // 設定が空の場合はデフォルト値を設定
                $existing_payment['edd_api_token'] = self::strong_encrypt_static('45055ce430c876042c6b9571508d5977');
                $payment_updated = true;
            }
            
            // Stripeシークレットキーも同様に強固な暗号化に移行
            if ( isset( $existing_payment['stripe_secret_key'] ) && ! empty( $existing_payment['stripe_secret_key'] ) ) {
                $decrypted_stripe = self::strong_decrypt_static( $existing_payment['stripe_secret_key'] );
                if ( empty( $decrypted_stripe ) ) {
                    // base64暗号化または平文の場合
                    $maybe_old = base64_decode( $existing_payment['stripe_secret_key'] );
                    if ( $maybe_old && strpos($maybe_old, 'sk_') === 0 ) {
                        $existing_payment['stripe_secret_key'] = self::strong_encrypt_static( $maybe_old );
                        $payment_updated = true;
                    } elseif ( strpos($existing_payment['stripe_secret_key'], 'sk_') === 0 ) {
                        // 平文の場合
                        $existing_payment['stripe_secret_key'] = self::strong_encrypt_static( $existing_payment['stripe_secret_key'] );
                        $payment_updated = true;
                    }
                }
            }
            
            if ( $payment_updated ) {
                update_option( $payment_option_name, $existing_payment );
            }
        }

        // 寄付設定のデフォルト値を保存
        $donation_option_name = 'ktp_donation_settings';
        $donation_defaults = array(
            'enabled'                 => true, // 寄付機能を有効化
            'monthly_goal'            => 10000,
            'suggested_amounts'       => '500,1000,3000,5000',
            'frontend_notice_enabled' => true,
            'notice_display_interval' => 30, // 30日ごとに再表示
            'notice_message'          => 'システム開発を継続するために費用がかかります。よろしければご寄付をお願いいたします。',
            'donation_url'            => 'https://www.kantanpro.com/donation',
        );
        if ( false === get_option( $donation_option_name ) ) {
            add_option( $donation_option_name, $donation_defaults );
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
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

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

        // テーブル作成後、AUTO_INCREMENTカウンターを確実に1に設定
        $order_row_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name_order}" );
        if ( $order_row_count == 0 ) {
            $wpdb->query( "ALTER TABLE {$table_name_order} AUTO_INCREMENT = 1" );
        }

        // 顧客テーブルのAUTO_INCREMENTカウンターも確実に1に設定
        $client_row_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name_client}" );
        if ( $client_row_count == 0 ) {
            $wpdb->query( "ALTER TABLE {$table_name_client} AUTO_INCREMENT = 1" );
        }

        // 既存テーブルにカラムを追加
        if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name_order'" ) == $table_name_order ) {
            $column_exists = $wpdb->get_results( "SHOW COLUMNS FROM $table_name_order LIKE 'company_name'" );
            if ( empty( $column_exists ) ) {
                $wpdb->query( "ALTER TABLE $table_name_order ADD company_name VARCHAR(255) DEFAULT NULL;" );
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

        // サブメニュー - スタッフ管理
        add_submenu_page(
            'ktp-settings', // 親メニューのスラッグ
            __( 'スタッフ管理', 'ktpwp' ), // ページタイトル
            __( 'スタッフ管理', 'ktpwp' ), // メニュータイトル
            'manage_options', // 権限
            'ktp-staff', // メニューのスラッグ
            array( $this, 'create_staff_page' ) // 表示を処理する関数
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

        // サブメニュー - 利用規約管理
        add_submenu_page(
            'ktp-settings', // 親メニューのスラッグ
            __( '利用規約管理', 'ktpwp' ), // ページタイトル
            __( '利用規約管理', 'ktpwp' ), // メニュータイトル
            'manage_options', // 権限
            'ktp-terms', // メニューのスラッグ
            array( $this, 'create_terms_page' ) // 表示を処理する関数
        );

        // サブメニュー - 決済設定
        add_submenu_page(
            'ktp-settings', // 親メニューのスラッグ
            __( '決済設定', 'ktpwp' ), // ページタイトル
            __( '決済設定', 'ktpwp' ), // メニュータイトル
            'manage_options', // 権限
            'ktp-payment-settings', // メニューのスラッグ
            array( $this, 'create_payment_settings_page' ) // 表示を処理する関数
        );
    }
    /**
     * 利用規約管理ページの表示
     */
    public function create_terms_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'この設定ページにアクセスする権限がありません。', 'ktpwp' ) );
        }

        ?>
        <div class="wrap ktp-admin-wrap">
            <h1><span class="dashicons dashicons-text-page"></span> <?php echo esc_html__( '利用規約管理', 'ktpwp' ); ?></h1>

            <?php $this->display_settings_tabs( 'terms' ); ?>

            <?php
            // 利用規約管理クラスが存在する場合は委譲
            if ( class_exists( 'KTPWP_Terms_Of_Service' ) ) {
                $terms_service = KTPWP_Terms_Of_Service::get_instance();
                $terms_service->create_terms_page();
            } else {
                // フォールバック
                echo '<div class="ktp-settings-container"><div class="ktp-settings-section"><p>' . esc_html__( '利用規約管理機能が利用できません。', 'ktpwp' ) . '</p></div></div>';
            }
            ?>
        </div>
        <?php
    }

    /**
     * 決済設定ページの表示
     */
    public function create_payment_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'この設定ページにアクセスする権限がありません。', 'ktpwp' ) );
        }

        // 開発者パスワード認証
        if ( ! $this->verify_developer_password() ) {
            $this->display_payment_password_form();
            return;
        }

        ?>
        <div class="wrap ktp-admin-wrap">
            <h1><span class="dashicons dashicons-money-alt"></span> <?php echo esc_html__( '決済設定', 'ktpwp' ); ?></h1>

            <?php $this->display_settings_tabs( 'payment' ); ?>

            <?php
            // 認証成功メッセージ
            if ( isset( $_GET['authenticated'] ) && $_GET['authenticated'] === '1' ) {
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( '認証に成功しました。決済設定の編集が可能です。', 'ktpwp' ) . '</p></div>';
            }

            // ログアウト処理
            if ( isset( $_GET['logout'] ) && $_GET['logout'] === '1' ) {
                ktpwp_set_session_data( 'ktp_payment_authenticated', false );
                echo '<div class="notice notice-info is-dismissible"><p>' . esc_html__( '認証を解除しました。', 'ktpwp' ) . '</p></div>';
            }
            ?>

            <div class="ktp-settings-container">
                <div style="margin-bottom: 20px;">
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=ktp-payment-settings&logout=1' ) ); ?>" class="button">
                        <?php esc_html_e( '認証解除', 'ktpwp' ); ?>
                    </a>
                </div>
                
                <form method="post" action="options.php">
                    <?php
                    settings_fields( 'ktp_payment_settings' );
                    do_settings_sections( 'ktp-payment-settings' );
                    ?>
                    <p class="submit">
                        <input type="submit" name="submit" id="submit" class="button button-primary" value="<?php esc_attr_e( '設定を保存', 'ktpwp' ); ?>">
                    </p>
                </form>
                
                <?php
                // 寄付通知プレビューセクション（表示専用）
                $this->display_donation_preview_section();
                
                // 寄付統計セクション（表示専用）
                $this->display_donation_stats_section();
                ?>
                
                <div class="ktp-settings-section">
                    <h3>寄付実績</h3>
                    <?php $this->display_donation_stats(); ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * 開発者パスワード認証（決済設定用）
     */
    private function verify_developer_password() {
        // セッション開始
        ktpwp_safe_session_start();

        // パスワード認証処理
        if ( isset( $_POST['developer_password'] ) ) {
            $password = sanitize_text_field( $_POST['developer_password'] );
            $encrypted = get_option( 'ktp_developer_password' );
            $plain = self::strong_decrypt_static( $encrypted );
            if ( $password === $plain ) {
                ktpwp_set_session_data( 'ktp_payment_authenticated', true );
                wp_redirect( admin_url( 'admin.php?page=ktp-payment-settings&authenticated=1' ) );
                exit;
            } else {
                ktpwp_set_session_data( 'ktp_payment_authenticated', false );
            }
        }

        // セッションから認証状態を確認
        return ktpwp_get_session_data( 'ktp_payment_authenticated', false ) === true;
    }

    /**
     * 決済設定用パスワード入力フォームを表示
     */
    private function display_payment_password_form() {
        ?>
        <div class="wrap ktp-admin-wrap">
            <h1><span class="dashicons dashicons-money-alt"></span> <?php echo esc_html__( '決済設定', 'ktpwp' ); ?></h1>

            <?php $this->display_settings_tabs( 'payment' ); ?>

            <div class="ktp-settings-container">
                <div class="notice notice-warning">
                    <p><?php echo esc_html__( '決済設定にアクセスするには開発者パスワードが必要です。', 'ktpwp' ); ?></p>
                </div>
                <?php
                // 認証失敗メッセージ
                if ( isset( $_POST['developer_password'] ) && sanitize_text_field( $_POST['developer_password'] ) !== '8bee1222' ) {
                    echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'パスワードが正しくありません。', 'ktpwp' ) . '</p></div>';
                }
                ?>
                <form method="post" style="max-width: 600px;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <label for="developer_password" style="white-space: nowrap;"><?php echo esc_html__( '開発者パスワード', 'ktpwp' ); ?></label>
                        <input type="password" name="developer_password" id="developer_password" class="regular-text" required style="flex: 1;" />
                        <?php submit_button( __( '認証', 'ktpwp' ), 'primary', 'submit', false ); ?>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }

    /**
     * 決済設定の初期化
     */
    public function init_payment_settings() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // 決済設定グループの登録
        register_setting(
            'ktp_payment_settings',
            'ktp_payment_settings',
            array( $this, 'sanitize_payment_settings' )
        );

        // 寄付設定グループの登録
        register_setting(
            'ktp_payment_settings',
            'ktp_donation_settings',
            array( $this, 'sanitize_donation_settings' )
        );

        // Stripe設定セクション
        add_settings_section(
            'stripe_settings',
            __( 'Stripe設定', 'ktpwp' ),
            array( $this, 'print_stripe_section_info' ),
            'ktp-payment-settings'
        );

        add_settings_field(
            'stripe_publishable_key',
            __( 'Stripe 公開可能キー', 'ktpwp' ),
            array( $this, 'stripe_publishable_key_callback' ),
            'ktp-payment-settings',
            'stripe_settings'
        );

        add_settings_field(
            'stripe_secret_key',
            __( 'Stripe シークレットキー', 'ktpwp' ),
            array( $this, 'stripe_secret_key_callback' ),
            'ktp-payment-settings',
            'stripe_settings'
        );

        // EDD設定セクション
        add_settings_section(
            'edd_settings',
            __( 'EDD連携設定', 'ktpwp' ),
            array( $this, 'print_edd_section_info' ),
            'ktp-payment-settings'
        );

        add_settings_field(
            'edd_api_key',
            __( 'EDD API キー', 'ktpwp' ),
            array( $this, 'edd_api_key_callback' ),
            'ktp-payment-settings',
            'edd_settings'
        );

        add_settings_field(
            'edd_api_token',
            __( 'EDD API トークン', 'ktpwp' ),
            array( $this, 'edd_api_token_callback' ),
            'ktp-payment-settings',
            'edd_settings'
        );

        // 寄付設定セクション
        add_settings_section(
            'donation_settings',
            __( '寄付設定', 'ktpwp' ),
            array( $this, 'print_donation_section_info' ),
            'ktp-payment-settings'
        );

        add_settings_field(
            'donation_enabled',
            __( '寄付機能を有効化', 'ktpwp' ),
            array( $this, 'donation_enabled_callback' ),
            'ktp-payment-settings',
            'donation_settings'
        );

        add_settings_field(
            'monthly_goal',
            __( '月間目標金額', 'ktpwp' ),
            array( $this, 'monthly_goal_callback' ),
            'ktp-payment-settings',
            'donation_settings'
        );

        add_settings_field(
            'suggested_amounts',
            __( '推奨寄付金額', 'ktpwp' ),
            array( $this, 'suggested_amounts_callback' ),
            'ktp-payment-settings',
            'donation_settings'
        );

        add_settings_field(
            'frontend_notice_enabled',
            __( 'フロントエンド通知を有効化', 'ktpwp' ),
            array( $this, 'frontend_notice_enabled_callback' ),
            'ktp-payment-settings',
            'donation_settings'
        );

        add_settings_field(
            'notice_display_interval',
            __( '通知表示間隔（日）', 'ktpwp' ),
            array( $this, 'notice_display_interval_callback' ),
            'ktp-payment-settings',
            'donation_settings'
        );

        add_settings_field(
            'notice_message',
            __( '通知メッセージ', 'ktpwp' ),
            array( $this, 'notice_message_callback' ),
            'ktp-payment-settings',
            'donation_settings'
        );

        add_settings_field(
            'donation_url',
            __( '寄付URL', 'ktpwp' ),
            array( $this, 'donation_url_callback' ),
            'ktp-payment-settings',
            'donation_settings'
        );
    }

    /**
     * Stripe設定セクションの説明
     */
    public function print_stripe_section_info() {
        echo '<p>' . esc_html__( 'Stripeアカウントで取得したAPIキーを入力してください。', 'ktpwp' ) . '</p>';
    }

    /**
     * EDD設定セクションの説明
     */
    public function print_edd_section_info() {
        echo '<p>' . esc_html__( '販売サイトのEasy Digital Downloads設定を入力してください。', 'ktpwp' ) . '</p>';
    }

    /**
     * 寄付設定セクションの説明
     */
    public function print_donation_section_info() {
        echo '<p>' . esc_html__( '寄付機能の基本設定を行います。通知設定は開発者専用です。', 'ktpwp' ) . '</p>';
    }



    /**
     * Stripe公開可能キーのコールバック
     */
    public function stripe_publishable_key_callback() {
        $options = get_option( 'ktp_payment_settings' );
        ?>
        <input type="text" 
               id="stripe_publishable_key" 
               name="ktp_payment_settings[stripe_publishable_key]" 
               value="<?php echo isset( $options['stripe_publishable_key'] ) ? esc_attr( $options['stripe_publishable_key'] ) : ''; ?>" 
               class="regular-text" 
               placeholder="pk_test_... または pk_live_...">
        <p class="description"><?php esc_html_e( 'Stripeダッシュボードで取得した公開可能キー', 'ktpwp' ); ?></p>
        <?php
    }

    /**
     * Stripeシークレットキーのコールバック
     */
    public function stripe_secret_key_callback() {
        $options = get_option( 'ktp_payment_settings' );
        $masked_value = '';
        if ( isset( $options['stripe_secret_key'] ) && ! empty( $options['stripe_secret_key'] ) ) {
            $masked_value = $this->mask_api_key( $this->decrypt_api_key( $options['stripe_secret_key'] ) );
        }
        ?>
        <input type="password" 
               id="stripe_secret_key" 
               name="ktp_payment_settings[stripe_secret_key]" 
               value="<?php echo esc_attr( $masked_value ); ?>" 
               class="regular-text" 
               placeholder="sk_test_... または sk_live_..."
               autocomplete="off">
        <p class="description">⚠️ <?php esc_html_e( '機密情報：Stripeダッシュボードで取得したシークレットキー', 'ktpwp' ); ?></p>
        <?php
    }

    /**
     * EDD API キーのコールバック
     */
    public function edd_api_key_callback() {
        $options = get_option( 'ktp_payment_settings' );
        $masked_value = '';
        if ( isset( $options['edd_api_key'] ) && ! empty( $options['edd_api_key'] ) ) {
            $decrypted_key = self::strong_decrypt_static( $options['edd_api_key'] );
            if ( empty( $decrypted_key ) ) {
                // 古いbase64暗号化または平文の場合
                $maybe_old = base64_decode( $options['edd_api_key'] );
                if ( $maybe_old && preg_match('/^[0-9a-f]{32}$/', $maybe_old) ) {
                    $masked_value = $this->mask_api_key( $maybe_old );
                } elseif ( preg_match('/^[0-9a-f]{32}$/', $options['edd_api_key'] ) ) {
                    $masked_value = $this->mask_api_key( $options['edd_api_key'] );
                }
            } else {
                $masked_value = $this->mask_api_key( $decrypted_key );
            }
        }
        ?>
        <input type="password" 
               id="edd_api_key" 
               name="ktp_payment_settings[edd_api_key]" 
               value="<?php echo esc_attr( $masked_value ); ?>" 
               class="regular-text" 
               placeholder="EDD API キー"
               autocomplete="off">
        <p class="description"><?php esc_html_e( '販売サイトのEDD API キー', 'ktpwp' ); ?></p>
        <?php
    }

    /**
     * EDD API トークンのコールバック
     */
    public function edd_api_token_callback() {
        $options = get_option( 'ktp_payment_settings' );
        $masked_value = '';
        if ( isset( $options['edd_api_token'] ) && ! empty( $options['edd_api_token'] ) ) {
            $decrypted_token = self::strong_decrypt_static( $options['edd_api_token'] );
            if ( empty( $decrypted_token ) ) {
                // 古いbase64暗号化または平文の場合
                $maybe_old = base64_decode( $options['edd_api_token'] );
                if ( $maybe_old && preg_match('/^[0-9a-f]{32}$/', $maybe_old) ) {
                    $masked_value = $this->mask_api_key( $maybe_old );
                } elseif ( preg_match('/^[0-9a-f]{32}$/', $options['edd_api_token'] ) ) {
                    $masked_value = $this->mask_api_key( $options['edd_api_token'] );
                }
            } else {
                $masked_value = $this->mask_api_key( $decrypted_token );
            }
        }
        ?>
        <input type="password" 
               id="edd_api_token" 
               name="ktp_payment_settings[edd_api_token]" 
               value="<?php echo esc_attr( $masked_value ); ?>" 
               class="regular-text" 
               placeholder="EDD API トークン"
               autocomplete="off">
        <p class="description"><?php esc_html_e( '販売サイトのEDD API トークン', 'ktpwp' ); ?></p>
        <?php
    }

    /**
     * 寄付機能有効化のコールバック
     */
    public function donation_enabled_callback() {
        $options = get_option( 'ktp_donation_settings' );
        ?>
        <label>
            <input type="checkbox" 
                   name="ktp_donation_settings[enabled]" 
                   value="1" 
                   <?php checked( isset( $options['enabled'] ) ? $options['enabled'] : false, 1 ); ?>>
            <?php esc_html_e( '寄付機能を有効にする', 'ktpwp' ); ?>
        </label>
        <?php
    }

    /**
     * 月間目標金額のコールバック
     */
    public function monthly_goal_callback() {
        $options = get_option( 'ktp_donation_settings' );
        ?>
        <input type="number" 
               id="monthly_goal" 
               name="ktp_donation_settings[monthly_goal]" 
               value="<?php echo isset( $options['monthly_goal'] ) ? esc_attr( $options['monthly_goal'] ) : '10000'; ?>" 
               min="1000" 
               step="1000"
               class="small-text">
        <span><?php esc_html_e( '円', 'ktpwp' ); ?></span>
        <p class="description"><?php esc_html_e( 'サーバー運営費などの月間目標額', 'ktpwp' ); ?></p>
        <?php
    }

    /**
     * 推奨寄付金額のコールバック
     */
    public function suggested_amounts_callback() {
        $options = get_option( 'ktp_donation_settings' );
        ?>
        <input type="text" 
               id="suggested_amounts" 
               name="ktp_donation_settings[suggested_amounts]" 
               value="<?php echo isset( $options['suggested_amounts'] ) ? esc_attr( $options['suggested_amounts'] ) : '500,1000,3000,5000'; ?>" 
               class="regular-text">
        <p class="description"><?php esc_html_e( 'カンマ区切りで複数金額を設定（例：500,1000,3000,5000）', 'ktpwp' ); ?></p>
        <?php
    }

    /**
     * フロントエンド通知有効化のコールバック
     */
    public function frontend_notice_enabled_callback() {
        $options = get_option( 'ktp_donation_settings' );
        ?>
        <label>
            <input type="checkbox" 
                   name="ktp_donation_settings[frontend_notice_enabled]" 
                   value="1" 
                   <?php checked( isset( $options['frontend_notice_enabled'] ) ? $options['frontend_notice_enabled'] : false, 1 ); ?>>
            <?php esc_html_e( 'フロントエンドで寄付通知を表示する', 'ktpwp' ); ?>
        </label>
        <p class="description"><?php esc_html_e( '有効にすると、KantanPro管理権限を持つユーザーがKantanProを設置したページにアクセスした際に寄付の案内が表示されます', 'ktpwp' ); ?></p>
        <?php
    }



    /**
     * 通知表示間隔のコールバック
     */
    public function notice_display_interval_callback() {
        $options = get_option( 'ktp_donation_settings' );
        ?>
        <input type="number" 
               id="notice_display_interval" 
               name="ktp_donation_settings[notice_display_interval]" 
               value="<?php echo isset( $options['notice_display_interval'] ) ? esc_attr( $options['notice_display_interval'] ) : '30'; ?>" 
               min="0" 
               max="365"
               class="small-text">
        <span><?php esc_html_e( '日', 'ktpwp' ); ?></span>
        <p class="description">
            <?php esc_html_e( 'KantanPro管理権限を持つユーザーが寄付を拒否した場合に再表示されるまでの間隔', 'ktpwp' ); ?>
            <br>
            <strong><?php esc_html_e( '0に設定すると、拒否しても即座に再表示されます（常時通知表示）', 'ktpwp' ); ?></strong>
        </p>
        <?php
    }

    /**
     * 通知メッセージのコールバック
     */
    public function notice_message_callback() {
        $options = get_option( 'ktp_donation_settings' );
        $default_message = 'このサイトの運営にご協力いただける方は、寄付をお願いいたします。';
        ?>
        <textarea id="notice_message" 
                  name="ktp_donation_settings[notice_message]" 
                  rows="3" 
                  cols="50" 
                  class="large-text"><?php echo isset( $options['notice_message'] ) ? esc_textarea( $options['notice_message'] ) : $default_message; ?></textarea>
        <p class="description"><?php esc_html_e( 'KantanPro管理権限を持つユーザー向けにフロントエンドで表示される寄付通知のメッセージ', 'ktpwp' ); ?></p>
        <?php
    }

    /**
     * 寄付URLのコールバック
     */
    public function donation_url_callback() {
        $options = get_option( 'ktp_donation_settings' );
        ?>
        <input type="url" 
               id="donation_url" 
               name="ktp_donation_settings[donation_url]" 
               value="<?php echo isset( $options['donation_url'] ) ? esc_url( $options['donation_url'] ) : ''; ?>" 
               class="regular-text" 
               placeholder="https://example.com/donation">
        <p class="description"><?php esc_html_e( '寄付通知の「寄付する」ボタンをクリックした際に遷移するURL', 'ktpwp' ); ?></p>
        <p class="description"><?php esc_html_e( '空欄の場合は https://www.kantanpro.com/donation が使用されます', 'ktpwp' ); ?></p>
        <?php
    }

    /**
     * 寄付通知プレビューのコールバック
     */
    public function donation_notice_preview_callback() {
        $donation_settings = get_option( 'ktp_donation_settings', array() );
        $message = isset( $donation_settings['notice_message'] ) ? $donation_settings['notice_message'] : 'このサイトの運営にご協力いただける方は、寄付をお願いいたします。';
        
        ?>
        <div class="ktpwp-notice-preview-container">
            <h4><?php esc_html_e( '現在の設定での通知表示例：', 'ktpwp' ); ?></h4>
            
            <div id="ktpwp-notice-preview" class="ktpwp-donation-notice" style="position: relative; top: auto; left: auto; right: auto; z-index: 1; margin: 10px 0;">
                <div class="ktpwp-notice-content">
                    <span class="ktpwp-notice-icon">💝</span>
                    <span class="ktpwp-notice-message"><?php echo esc_html( $message ); ?></span>
                    <div class="ktpwp-notice-actions">
                        <a href="#" class="ktpwp-notice-donate-btn"><?php esc_html_e( '寄付する', 'ktpwp' ); ?></a>
                        <button type="button" class="ktpwp-notice-dismiss-btn" aria-label="<?php esc_attr_e( '閉じる', 'ktpwp' ); ?>">×</button>
                    </div>
                </div>
            </div>
            
            <div class="ktpwp-preview-controls">
                <button type="button" class="button" onclick="testNoticeDisplay()">
                    <?php esc_html_e( '通知表示テスト', 'ktpwp' ); ?>
                </button>
                <button type="button" class="button" onclick="testNoticeDismiss()">
                    <?php esc_html_e( '閉じるテスト', 'ktpwp' ); ?>
                </button>
            </div>
            
            <div class="ktpwp-preview-info">
                <p><strong><?php esc_html_e( '表示条件：', 'ktpwp' ); ?></strong></p>
                <ul>
                    <li><?php esc_html_e( 'KantanPro管理権限を持つログインユーザーのみ', 'ktpwp' ); ?></li>
                    <li><?php esc_html_e( 'KantanProが設置されているページにアクセス', 'ktpwp' ); ?></li>
                    <li><?php esc_html_e( '寄付機能が有効', 'ktpwp' ); ?></li>
                    <li><?php esc_html_e( 'フロントエンド通知が有効', 'ktpwp' ); ?></li>
                    <li><?php esc_html_e( 'Stripe設定が完了', 'ktpwp' ); ?></li>
                    <li><?php esc_html_e( 'ユーザーがまだ寄付していない', 'ktpwp' ); ?></li>
                    <li><?php esc_html_e( 'ユーザーが通知を拒否していない（間隔0の場合は常時表示）', 'ktpwp' ); ?></li>
                </ul>
            </div>
        </div>

        <script>
        function testNoticeDisplay() {
            var $preview = jQuery('#ktpwp-notice-preview');
            $preview.fadeOut(300, function() {
                setTimeout(function() {
                    $preview.fadeIn(500);
                }, 100);
            });
        }
        
        function testNoticeDismiss() {
            var $preview = jQuery('#ktpwp-notice-preview');
            $preview.fadeOut(300);
        }
        </script>

        <style>
        .ktpwp-notice-preview-container {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 5px;
            margin: 10px 0;
        }
        
        .ktpwp-preview-controls {
            margin: 15px 0;
        }
        
        .ktpwp-preview-controls .button {
            margin-right: 10px;
        }
        
        .ktpwp-preview-info {
            margin-top: 15px;
            padding: 15px;
            background: #fff;
            border-left: 4px solid #0073aa;
        }
        
        .ktpwp-preview-info ul {
            margin: 10px 0;
            padding-left: 20px;
        }
        
        .ktpwp-preview-info li {
            margin: 5px 0;
        }
        </style>
        <?php
    }

    /**
     * 寄付統計の表示
     */
    private function display_donation_stats() {
        if ( ! class_exists( 'KTPWP_Donation' ) ) {
            echo '<p>' . esc_html__( '寄付機能が利用できません。', 'ktpwp' ) . '</p>';
            return;
        }

        $donation_instance = KTPWP_Donation::get_instance();
        $monthly_total = $donation_instance->get_monthly_total();
        $monthly_progress = $donation_instance->get_monthly_progress();
        $donation_settings = get_option( 'ktp_donation_settings', array() );
        $monthly_goal = isset( $donation_settings['monthly_goal'] ) ? intval( $donation_settings['monthly_goal'] ) : 10000;

        ?>
        <div class="ktpwp-donation-stats">
            <div class="ktpwp-stat-box">
                <h4><?php esc_html_e( '今月の寄付総額', 'ktpwp' ); ?></h4>
                <p class="ktpwp-stat-amount">¥<?php echo number_format( $monthly_total ); ?></p>
            </div>
            
            <div class="ktpwp-stat-box">
                <h4><?php esc_html_e( '目標達成率', 'ktpwp' ); ?></h4>
                <p class="ktpwp-stat-progress"><?php echo number_format( $monthly_progress, 1 ); ?>%</p>
                <div class="ktpwp-progress-bar">
                    <div class="ktpwp-progress-fill" style="width: <?php echo esc_attr( $monthly_progress ); ?>%"></div>
                </div>
            </div>
            
            <div class="ktpwp-stat-box">
                <h4><?php esc_html_e( '月間目標', 'ktpwp' ); ?></h4>
                <p class="ktpwp-stat-amount">¥<?php echo number_format( $monthly_goal ); ?></p>
            </div>
        </div>
        <?php
    }

    /**
     * 寄付統計ダッシュボードのコールバック
     */
    public function donation_stats_dashboard_callback() {
        if ( ! class_exists( 'KTPWP_Donation' ) ) {
            echo '<p>' . esc_html__( '寄付機能が利用できません。', 'ktpwp' ) . '</p>';
            return;
        }

        $donation_instance = KTPWP_Donation::get_instance();
        $monthly_total = $donation_instance->get_monthly_total();
        $monthly_progress = $donation_instance->get_monthly_progress();
        $donation_settings = get_option( 'ktp_donation_settings', array() );
        $monthly_goal = isset( $donation_settings['monthly_goal'] ) ? intval( $donation_settings['monthly_goal'] ) : 10000;

        // 過去3ヶ月のデータを取得
        $last_3_months = array();
        for ( $i = 0; $i < 3; $i++ ) {
            $month = date( 'Y-m', strtotime( "-{$i} months" ) );
            $last_3_months[] = array(
                'month' => $month,
                'total' => $this->get_monthly_total_by_date( $month ),
                'goal' => $monthly_goal
            );
        }

        ?>
        <div class="ktpwp-stats-dashboard">
            <div class="ktpwp-stats-grid">
                <div class="ktpwp-stat-card">
                    <h4><?php esc_html_e( '今月の寄付総額', 'ktpwp' ); ?></h4>
                    <div class="ktpwp-stat-value">¥<?php echo number_format( $monthly_total ); ?></div>
                    <div class="ktpwp-stat-progress">
                        <div class="ktpwp-progress-bar">
                            <div class="ktpwp-progress-fill" style="width: <?php echo esc_attr( $monthly_progress ); ?>%"></div>
                        </div>
                        <span><?php echo number_format( $monthly_progress, 1 ); ?>%</span>
                    </div>
                </div>
                
                <div class="ktpwp-stat-card">
                    <h4><?php esc_html_e( '月間目標', 'ktpwp' ); ?></h4>
                    <div class="ktpwp-stat-value">¥<?php echo number_format( $monthly_goal ); ?></div>
                    <div class="ktpwp-stat-remaining">
                        <?php 
                        $remaining = $monthly_goal - $monthly_total;
                        if ( $remaining > 0 ) {
                            echo '<span class="ktpwp-remaining">残り ¥' . number_format( $remaining ) . '</span>';
                        } else {
                            echo '<span class="ktpwp-achieved">目標達成！</span>';
                        }
                        ?>
                    </div>
                </div>
                
                <div class="ktpwp-stat-card">
                    <h4><?php esc_html_e( '過去3ヶ月の推移', 'ktpwp' ); ?></h4>
                    <div class="ktpwp-monthly-chart">
                        <?php foreach ( $last_3_months as $month_data ): ?>
                        <div class="ktpwp-monthly-bar">
                            <div class="ktpwp-bar-label"><?php echo esc_html( date( 'M', strtotime( $month_data['month'] . '-01' ) ) ); ?></div>
                            <div class="ktpwp-bar-container">
                                <div class="ktpwp-bar-fill" style="width: <?php echo esc_attr( min( 100, ( $month_data['total'] / $month_data['goal'] ) * 100 ) ); ?>%"></div>
                            </div>
                            <div class="ktpwp-bar-value">¥<?php echo number_format( $month_data['total'] ); ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <div class="ktpwp-stats-actions">
                <button type="button" class="button" onclick="refreshDonationStats()">
                    <?php esc_html_e( '統計を更新', 'ktpwp' ); ?>
                </button>
                <button type="button" class="button" onclick="exportDonationData()">
                    <?php esc_html_e( 'データをエクスポート', 'ktpwp' ); ?>
                </button>
            </div>
        </div>

        <style>
        .ktpwp-stats-dashboard {
            background: #fff;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .ktpwp-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .ktpwp-stat-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            border-left: 4px solid #0073aa;
        }
        
        .ktpwp-stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #0073aa;
            margin: 10px 0;
        }
        
        .ktpwp-stat-progress {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .ktpwp-progress-bar {
            flex: 1;
            height: 8px;
            background: #e0e0e0;
            border-radius: 4px;
            overflow: hidden;
        }
        
        .ktpwp-progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #0073aa, #00a0d2);
            transition: width 0.3s ease;
        }
        
        .ktpwp-monthly-chart {
            margin-top: 15px;
        }
        
        .ktpwp-monthly-bar {
            display: flex;
            align-items: center;
            margin: 8px 0;
            gap: 10px;
        }
        
        .ktpwp-bar-label {
            width: 30px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .ktpwp-bar-container {
            flex: 1;
            height: 20px;
            background: #e0e0e0;
            border-radius: 10px;
            overflow: hidden;
        }
        
        .ktpwp-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, #0073aa, #00a0d2);
            transition: width 0.3s ease;
        }
        
        .ktpwp-bar-value {
            width: 80px;
            font-size: 12px;
            text-align: right;
        }
        
        .ktpwp-remaining {
            color: #d63638;
            font-weight: bold;
        }
        
        .ktpwp-achieved {
            color: #00a32a;
            font-weight: bold;
        }
        
        .ktpwp-stats-actions {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
        }
        
        .ktpwp-stats-actions .button {
            margin-right: 10px;
        }
        </style>

        <script>
        function refreshDonationStats() {
            location.reload();
        }
        
        function exportDonationData() {
            // 将来的にCSVエクスポート機能を実装
            alert('<?php esc_js_e( 'エクスポート機能は今後実装予定です。', 'ktpwp' ); ?>');
        }
        </script>
        <?php
    }

    /**
     * 指定月の寄付総額を取得
     */
    private function get_monthly_total_by_date( $month ) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ktp_donations';
        $start_date = $month . '-01';
        $end_date = date( 'Y-m-t', strtotime( $start_date ) );
        
        $total = $wpdb->get_var( $wpdb->prepare(
            "SELECT SUM(amount) FROM $table_name WHERE status = 'completed' AND created_at >= %s AND created_at <= %s",
            $start_date,
            $end_date
        ) );
        
        return intval( $total );
    }

    /**
     * 寄付セクションの表示
     */
    private function display_donation_section() {
        // 寄付設定を取得
        $donation_settings = get_option( 'ktp_donation_settings', array() );
        $payment_settings = get_option( 'ktp_payment_settings', array() );
        
        // 寄付機能が無効の場合は表示しない
        if ( empty( $donation_settings['enabled'] ) ) {
            return;
        }

        // Stripe設定が完了していない場合は表示しない
        if ( empty( $payment_settings['stripe_publishable_key'] ) ) {
            return;
        }

        // 寄付クラスが利用できない場合は表示しない
        if ( ! class_exists( 'KTPWP_Donation' ) ) {
            return;
        }

        $donation_instance = KTPWP_Donation::get_instance();
        $monthly_total = $donation_instance->get_monthly_total();
        $monthly_progress = $donation_instance->get_monthly_progress();
        $monthly_goal = isset( $donation_settings['monthly_goal'] ) ? intval( $donation_settings['monthly_goal'] ) : 10000;
        $suggested_amounts = isset( $donation_settings['suggested_amounts'] ) ? $donation_settings['suggested_amounts'] : '500,1000,3000,5000';

        ?>
        <div class="ktp-settings-section ktpwp-donation-section" style="margin-top: 30px;">
            <h3><span class="dashicons dashicons-heart"></span> <?php esc_html_e( 'KantanPro開発支援', 'ktpwp' ); ?></h3>
            
            <div class="ktpwp-donation-intro">
                <p><?php esc_html_e( 'KantanProプラグインは無料で提供していますが、継続的な開発・保守にはサーバー運営費などの固定費が発生します。', 'ktpwp' ); ?></p>
                <p><?php esc_html_e( 'このプラグインがお役に立った場合、開発継続のためのご支援をお願いします。', 'ktpwp' ); ?></p>
            </div>

            <div class="ktpwp-donation-progress" style="margin-bottom: 20px;">
                <h4><?php esc_html_e( '今月の目標達成状況', 'ktpwp' ); ?></h4>
                <div class="ktpwp-progress-bar">
                    <div class="ktpwp-progress-fill" style="width: <?php echo esc_attr( $monthly_progress ); ?>%"></div>
                </div>
                <p><strong>¥<?php echo number_format( $monthly_total ); ?></strong> / ¥<?php echo number_format( $monthly_goal ); ?> (<?php echo number_format( $monthly_progress, 1 ); ?>%)</p>
            </div>

            <div id="ktpwp-admin-donation-form" class="ktpwp-donation-container">
                <div class="ktpwp-donation-amounts">
                    <?php 
                    $amounts = explode( ',', $suggested_amounts );
                    foreach ( $amounts as $amount ): 
                        $amount = intval( trim( $amount ) );
                        if ( $amount > 0 ):
                    ?>
                    <button type="button" class="ktpwp-amount-btn" data-amount="<?php echo esc_attr( $amount ); ?>">
                        ¥<?php echo number_format( $amount ); ?>
                    </button>
                    <?php 
                        endif;
                    endforeach; 
                    ?>
                </div>
                
                <div class="ktpwp-custom-amount">
                    <label for="ktpwp-custom-amount"><?php esc_html_e( 'カスタム金額：', 'ktpwp' ); ?></label>
                    <input type="number" id="ktpwp-custom-amount" min="100" step="100" placeholder="100" style="width: 120px;">
                    <span><?php esc_html_e( '円', 'ktpwp' ); ?></span>
                </div>
                
                <div class="ktpwp-donor-info">
                    <input type="text" id="ktpwp-donor-name" placeholder="<?php esc_attr_e( 'お名前（任意）', 'ktpwp' ); ?>">
                    <input type="email" id="ktpwp-donor-email" placeholder="<?php esc_attr_e( 'メールアドレス（任意）', 'ktpwp' ); ?>">
                    <textarea id="ktpwp-donor-message" placeholder="<?php esc_attr_e( 'メッセージ（任意）', 'ktpwp' ); ?>"></textarea>
                </div>
                
                <div id="ktpwp-card-element">
                    <!-- Stripe Elements will create form elements here -->
                </div>
                
                <button type="button" id="ktpwp-donate-btn" class="button button-primary ktpwp-btn-primary">
                    <?php esc_html_e( '寄付する', 'ktpwp' ); ?>
                </button>
                
                <div id="ktpwp-donation-messages"></div>
            </div>
            
            <div class="ktpwp-donation-usage">
                <h4><?php esc_html_e( 'ご支援の使途', 'ktpwp' ); ?></h4>
                <ul>
                    <li><?php esc_html_e( 'サーバー運営費', 'ktpwp' ); ?></li>
                    <li><?php esc_html_e( '開発・保守費用', 'ktpwp' ); ?></li>
                    <li><?php esc_html_e( '新機能の追加', 'ktpwp' ); ?></li>
                    <li><?php esc_html_e( 'セキュリティアップデート', 'ktpwp' ); ?></li>
                </ul>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // 管理画面用の寄付フォーム処理
            var currentAmount = 0;
            
            $('#ktpwp-admin-donation-form').on('click', '.ktpwp-amount-btn', function() {
                $('.ktpwp-amount-btn').removeClass('active');
                $(this).addClass('active');
                currentAmount = parseInt($(this).data('amount'));
                $('#ktpwp-custom-amount').val('');
            });

            $('#ktpwp-custom-amount').on('input', function() {
                $('.ktpwp-amount-btn').removeClass('active');
                currentAmount = parseInt($(this).val()) || 0;
            });

            $('#ktpwp-donate-btn').on('click', function() {
                var amount = currentAmount;
                
                if (amount < 100) {
                    alert('<?php esc_js_e( '最小寄付額は100円です。', 'ktpwp' ); ?>');
                    return;
                }
                
                // 寄付処理を実行
                if (typeof Stripe !== 'undefined' && typeof ktpwp_donation !== 'undefined') {
                    processAdminDonation(amount);
                } else {
                    alert('<?php esc_js_e( '寄付機能を初期化中です。ページを再読み込みしてください。', 'ktpwp' ); ?>');
                }
            });
            
            function processAdminDonation(amount) {
                var $button = $('#ktpwp-donate-btn');
                var $messages = $('#ktpwp-donation-messages');
                
                // ボタンを無効化
                $button.prop('disabled', true).text('<?php esc_js_e( '処理中...', 'ktpwp' ); ?>');
                
                // 寄付情報を取得
                var donationData = {
                    amount: amount,
                    donor_name: $('#ktpwp-donor-name').val().trim(),
                    donor_email: $('#ktpwp-donor-email').val().trim(),
                    donor_message: $('#ktpwp-donor-message').val().trim(),
                    nonce: ktpwp_donation.nonce
                };
                
                // PaymentIntentを作成
                $.ajax({
                    url: ktpwp_donation.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'ktpwp_create_payment_intent',
                        ...donationData
                    },
                    success: function(response) {
                        if (response.success) {
                            // Stripe決済処理
                            var stripe = Stripe(ktpwp_donation.stripe_publishable_key);
                            var elements = stripe.elements();
                            var cardElement = elements.create('card');
                            cardElement.mount('#ktpwp-card-element');
                            
                            stripe.confirmCardPayment(response.data.client_secret, {
                                payment_method: {
                                    card: cardElement,
                                    billing_details: {
                                        name: donationData.donor_name,
                                        email: donationData.donor_email
                                    }
                                }
                            }).then(function(result) {
                                if (result.error) {
                                    showMessage(result.error.message, 'error');
                                } else {
                                    // 寄付成功
                                    confirmDonationSuccess(response.data.donation_id, result.paymentIntent.id);
                                }
                            });
                        } else {
                            showMessage(response.data || '<?php esc_js_e( '決済の準備中にエラーが発生しました。', 'ktpwp' ); ?>', 'error');
                        }
                        
                        // ボタンを有効化
                        $button.prop('disabled', false).text('<?php esc_js_e( '寄付する', 'ktpwp' ); ?>');
                    },
                    error: function() {
                        showMessage('<?php esc_js_e( '決済の準備中にエラーが発生しました。', 'ktpwp' ); ?>', 'error');
                        $button.prop('disabled', false).text('<?php esc_js_e( '寄付する', 'ktpwp' ); ?>');
                    }
                });
            }
            
            function confirmDonationSuccess(donationId, paymentIntentId) {
                $.ajax({
                    url: ktpwp_donation.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'ktpwp_confirm_donation',
                        donation_id: donationId,
                        payment_intent_id: paymentIntentId,
                        nonce: ktpwp_donation.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            showMessage('<?php esc_js_e( 'ご寄付ありがとうございます！', 'ktpwp' ); ?>', 'success');
                            // フォームをリセット
                            $('#ktpwp-donor-name, #ktpwp-donor-email, #ktpwp-donor-message, #ktpwp-custom-amount').val('');
                            $('.ktpwp-amount-btn').removeClass('active');
                            currentAmount = 0;
                            
                            // 進捗バーを更新
                            setTimeout(function() {
                                location.reload();
                            }, 2000);
                        } else {
                            showMessage(response.data || '<?php esc_js_e( '寄付の確認中にエラーが発生しました。', 'ktpwp' ); ?>', 'error');
                        }
                    },
                    error: function() {
                        showMessage('<?php esc_js_e( '寄付の確認中にエラーが発生しました。', 'ktpwp' ); ?>', 'error');
                    }
                });
            }
            
            function showMessage(message, type) {
                var $messages = $('#ktpwp-donation-messages');
                $messages.removeClass('success error').addClass(type);
                $messages.text(message).show();
            }
        });
        </script>
        <?php
    }

    /**
     * 決済設定のサニタイズ
     */
    public function sanitize_payment_settings( $input ) {
        $new_input = array();
        // Stripe公開キーはそのまま
        if ( isset( $input['stripe_publishable_key'] ) ) {
            $new_input['stripe_publishable_key'] = sanitize_text_field( $input['stripe_publishable_key'] );
        }
        // Stripeシークレットキー
        if ( isset( $input['stripe_secret_key'] ) ) {
            $secret_key = sanitize_text_field( $input['stripe_secret_key'] );
            if ( ! empty( $secret_key ) && strpos( $secret_key, '*' ) === false ) {
                $new_input['stripe_secret_key'] = $this->strong_encrypt( $secret_key );
            } else {
                $existing = get_option( 'ktp_payment_settings', array() );
                $old = isset( $existing['stripe_secret_key'] ) ? $existing['stripe_secret_key'] : '';
                // base64方式なら自動移行
                $maybe_old = base64_decode( $old );
                if ($maybe_old && strpos($maybe_old, 'sk_') === 0) {
                    $new_input['stripe_secret_key'] = $this->strong_encrypt( $maybe_old );
                } else {
                    $new_input['stripe_secret_key'] = $old;
                }
            }
        }
        // EDD APIキー
        if ( isset( $input['edd_api_key'] ) ) {
            $api_key = sanitize_text_field( $input['edd_api_key'] );
            if ( ! empty( $api_key ) && strpos( $api_key, '*' ) === false ) {
                $new_input['edd_api_key'] = $this->strong_encrypt( $api_key );
            } else {
                $existing = get_option( 'ktp_payment_settings', array() );
                $old = isset( $existing['edd_api_key'] ) ? $existing['edd_api_key'] : '';
                // 強固な暗号化で復号してみる
                $decrypted_old = self::strong_decrypt_static( $old );
                if ( ! empty( $decrypted_old ) ) {
                    $new_input['edd_api_key'] = $old; // 既に強固な暗号化されている
                } else {
                    // base64暗号化または平文の場合
                    $maybe_old = base64_decode( $old );
                    if ($maybe_old && preg_match('/^[0-9a-f]{32}$/', $maybe_old)) {
                        $new_input['edd_api_key'] = $this->strong_encrypt( $maybe_old );
                    } elseif ( preg_match('/^[0-9a-f]{32}$/', $old ) ) {
                        $new_input['edd_api_key'] = $this->strong_encrypt( $old );
                    } else {
                        $new_input['edd_api_key'] = $old;
                    }
                }
            }
        }
        // EDD APIトークン
        if ( isset( $input['edd_api_token'] ) ) {
            $api_token = sanitize_text_field( $input['edd_api_token'] );
            if ( ! empty( $api_token ) && strpos( $api_token, '*' ) === false ) {
                $new_input['edd_api_token'] = $this->strong_encrypt( $api_token );
            } else {
                $existing = get_option( 'ktp_payment_settings', array() );
                $old = isset( $existing['edd_api_token'] ) ? $existing['edd_api_token'] : '';
                // 強固な暗号化で復号してみる
                $decrypted_old = self::strong_decrypt_static( $old );
                if ( ! empty( $decrypted_old ) ) {
                    $new_input['edd_api_token'] = $old; // 既に強固な暗号化されている
                } else {
                    // base64暗号化または平文の場合
                    $maybe_old = base64_decode( $old );
                    if ($maybe_old && preg_match('/^[0-9a-f]{32}$/', $maybe_old)) {
                        $new_input['edd_api_token'] = $this->strong_encrypt( $maybe_old );
                    } elseif ( preg_match('/^[0-9a-f]{32}$/', $old ) ) {
                        $new_input['edd_api_token'] = $this->strong_encrypt( $old );
                    } else {
                        $new_input['edd_api_token'] = $old;
                    }
                }
            }
        }
        return $new_input;
    }

    /**
     * 寄付設定のサニタイズ
     */
    public function sanitize_donation_settings( $input ) {
        $new_input = array();
        
        if ( isset( $input['enabled'] ) ) {
            $new_input['enabled'] = (bool) $input['enabled'];
        }
        
        if ( isset( $input['monthly_goal'] ) ) {
            $new_input['monthly_goal'] = max( 1000, absint( $input['monthly_goal'] ) );
        }
        
        if ( isset( $input['suggested_amounts'] ) ) {
            $new_input['suggested_amounts'] = sanitize_text_field( $input['suggested_amounts'] );
        }
        
        if ( isset( $input['frontend_notice_enabled'] ) ) {
            $new_input['frontend_notice_enabled'] = (bool) $input['frontend_notice_enabled'];
        }
        
        if ( isset( $input['notice_display_interval'] ) ) {
            $new_input['notice_display_interval'] = max( 0, min( 365, absint( $input['notice_display_interval'] ) ) );
        }
        
        if ( isset( $input['notice_message'] ) ) {
            $new_input['notice_message'] = sanitize_textarea_field( $input['notice_message'] );
        }
        
        if ( isset( $input['donation_url'] ) ) {
            $donation_url = esc_url_raw( $input['donation_url'] );
            $new_input['donation_url'] = $donation_url;
        }
        
        return $new_input;
    }

    /**
     * API キーの暗号化
     */
    private function encrypt_api_key( $key ) {
        if ( empty( $key ) ) {
            return '';
        }
        
        return base64_encode( $key );
    }

    /**
     * API キーの復号化
     */
    private function decrypt_api_key( $encrypted_key ) {
        if ( empty( $encrypted_key ) ) {
            return '';
        }
        
        return base64_decode( $encrypted_key );
    }

    /**
     * API キーのマスク表示
     */
    private function mask_api_key( $key ) {
        if ( empty( $key ) ) {
            return '';
        }
        
        if ( strlen( $key ) <= 8 ) {
            return str_repeat( '*', strlen( $key ) );
        }
        
        return substr( $key, 0, 4 ) . str_repeat( '*', strlen( $key ) - 8 ) . substr( $key, -4 );
    }

    /**
     * スタッフ管理ページの表示
     */
    public function create_staff_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'この設定ページにアクセスする権限がありません。', 'ktpwp' ) );
        }

        // KantanPro利用権限（ktpwp_access）付加/削除処理
        if ( isset( $_POST['ktpwp_access_user'] ) && isset( $_POST['ktpwp_access_action'] ) && check_admin_referer( 'ktp_staff_role_action', 'ktp_staff_role_nonce' ) ) {
            $user_id = intval( $_POST['ktpwp_access_user'] );
            $action = sanitize_text_field( $_POST['ktpwp_access_action'] );
            $user_obj = get_userdata( $user_id );
            if ( $user_obj ) {
                if ( $action === 'add' ) {
                    $user_obj->add_cap( 'ktpwp_access' );
                    // 最終変更日時を記録
                    update_user_meta( $user_id, 'last_activity', current_time( 'mysql' ) );
                    echo '<div class="notice notice-success is-dismissible"><p>KantanPro利用権限（ktpwp_access）を付加しました。</p></div>';

                    // スタッフ追加時のメール通知を送信
                    $mail_sent = $this->send_staff_notification_email( $user_obj, 'add' );
                    if ( $mail_sent ) {
                        echo '<div class="notice notice-success is-dismissible"><p>📧 スタッフ追加の通知メールを ' . esc_html( $user_obj->user_email ) . ' に送信しました。</p></div>';
                    } else {
                        echo '<div class="notice notice-warning is-dismissible"><p>⚠️ スタッフ追加の通知メール送信に失敗しました。メール設定をご確認ください。</p></div>';
                    }
                } elseif ( $action === 'remove' ) {
                    $user_obj->remove_cap( 'ktpwp_access' );
                    // 最終変更日時を記録
                    update_user_meta( $user_id, 'last_activity', current_time( 'mysql' ) );
                    echo '<div class="notice notice-success is-dismissible"><p>KantanPro利用権限（ktpwp_access）を削除しました。</p></div>';

                    // スタッフ削除時のメール通知を送信
                    $mail_sent = $this->send_staff_notification_email( $user_obj, 'remove' );
                    if ( $mail_sent ) {
                        echo '<div class="notice notice-success is-dismissible"><p>📧 スタッフ削除の通知メールを ' . esc_html( $user_obj->user_email ) . ' に送信しました。</p></div>';
                    } else {
                        echo '<div class="notice notice-warning is-dismissible"><p>⚠️ スタッフ削除の通知メール送信に失敗しました。メール設定をご確認ください。</p></div>';
                    }
                }
            }
        }

        // 管理者以外のユーザーのみ取得
        $users = get_users( array( 'role__not_in' => array( 'administrator' ) ) );
        global $wp_roles;
        // $all_roles = $wp_roles->roles; // プルダウンがなくなったため不要
        ?>
        <div class="wrap ktp-admin-wrap">
            <h1><span class="dashicons dashicons-groups"></span> <?php echo esc_html__( 'スタッフ管理', 'ktpwp' ); ?></h1>

            <?php $this->display_settings_tabs( 'staff' ); ?>

            <div class="ktp-settings-container">
                <div class="ktp-settings-section">
                    <h2>登録スタッフ一覧</h2>
                    <div style="margin-bottom: 10px; color: #555; font-size: 13px;">
                        <?php echo esc_html__( '管理者は登録者の権限に関わらずここでスタッフの追加削除が行えます', 'ktpwp' ); ?>
                    </div>
                    <div style="margin-bottom: 15px; padding: 12px; background: #e7f3ff; border: 1px solid #b3d9ff; border-radius: 4px; font-size: 13px;">
                        <span class="dashicons dashicons-info" style="color: #0073aa; margin-right: 5px;"></span>
                        <strong>メール通知について：</strong>スタッフの追加・削除時に、該当ユーザーへ自動でメール通知が送信されます。
                        通知内容にはログイン情報や権限の変更についての案内が含まれます。
                    </div>
                    <table class="widefat fixed striped ktp-staff-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( '表示名', 'ktpwp' ); ?></th>
                                <th><?php esc_html_e( 'メールアドレス', 'ktpwp' ); ?></th>
                                <th><?php esc_html_e( 'スタッフ', 'ktpwp' ); ?></th>
                                <th><?php esc_html_e( '最終変更日時', 'ktpwp' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ( $users as $user ) : ?>
                            <tr>
                                <td><?php echo esc_html( $user->display_name ); ?></td>
                                <td><?php echo esc_html( $user->user_email ); ?></td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <?php if ( $user->has_cap( 'ktpwp_access' ) ) : ?>
                                            <span style="color:green;font-weight:bold;">利用中</span>
                                        <?php else : ?>
                                            <span style="color:red;">未使用</span>
                                        <?php endif; ?>
                                        <form method="post" style="display: flex; align-items: center; gap: 10px; margin-bottom: 0;">
                                            <?php wp_nonce_field( 'ktp_staff_role_action', 'ktp_staff_role_nonce' ); ?>
                                            <input type="hidden" name="ktpwp_access_user" value="<?php echo esc_attr( $user->ID ); ?>">
                                            <label style="margin-bottom: 0;">
                                                <input type="radio" name="ktpwp_access_action" value="add" <?php checked( ! $user->has_cap( 'ktpwp_access' ) ); ?>>
                                                <?php esc_html_e( '追加', 'ktpwp' ); ?>
                                            </label>
                                            <label style="margin-bottom: 0;">
                                                <input type="radio" name="ktpwp_access_action" value="remove" <?php checked( $user->has_cap( 'ktpwp_access' ) ); ?>>
                                                <?php esc_html_e( '削除', 'ktpwp' ); ?>
                                            </label>
                                            <button type="submit" class="button"><?php esc_html_e( '適用', 'ktpwp' ); ?></button>
                                        </form>
                                    </div>
                                </td>
                                <td>
                                    <?php
                                    // WordPressのユーザーメタからカスタムフィールドで最終更新日時を取得
                                    $last_modified = get_user_meta( $user->ID, 'last_activity', true );

                                    // カスタムフィールドがない場合は、ユーザー登録日時を使用
                                    if ( empty( $last_modified ) ) {
                                        $last_modified = $user->user_registered;
                                    }

                                    // 日時をフォーマットして表示
                                    if ( ! empty( $last_modified ) ) {
                                        echo esc_html( date_i18n( 'Y-m-d H:i', strtotime( $last_modified ) ) );
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
     * @param string  $action 'add' または 'remove'
     * @return bool 送信成功/失敗
     */
    private function send_staff_notification_email( $user_obj, $action ) {
        // メールアドレスが存在しない場合は送信しない
        if ( empty( $user_obj->user_email ) ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'KTPWP Staff Notification: メールアドレスが未設定のため通知を送信できません (User ID: ' . $user_obj->ID . ')' );
            }
            return false;
        }

        // SMTP設定を取得
        $smtp_settings = get_option( 'ktp_smtp_settings', array() );
        $from_email = ! empty( $smtp_settings['email_address'] ) ? sanitize_email( $smtp_settings['email_address'] ) : get_option( 'admin_email' );
        $from_name = ! empty( $smtp_settings['smtp_from_name'] ) ? sanitize_text_field( $smtp_settings['smtp_from_name'] ) : get_bloginfo( 'name' );

        // 会社情報を取得
        $company_info = self::get_company_info();
        if ( empty( $company_info ) ) {
            $company_info = get_bloginfo( 'name' );
        } else {
            // HTMLタグを除去してプレーンテキストに変換
            $company_info = wp_strip_all_tags( $company_info );
        }

        // メール内容を生成
        $to = sanitize_email( $user_obj->user_email );
        $display_name = ! empty( $user_obj->display_name ) ? $user_obj->display_name : $user_obj->user_login;

        if ( $action === 'add' ) {
            $subject = '[' . get_bloginfo( 'name' ) . '] スタッフ権限が付与されました';
            $body = $display_name . ' 様' . "\n\n";
            $body .= 'この度、' . get_bloginfo( 'name' ) . ' の業務管理システム（KantanPro）のスタッフ権限が付与されました。' . "\n\n";
            $body .= '以下のURLからログインして、システムをご利用ください：' . "\n";
            $body .= wp_login_url() . "\n\n";
            $body .= 'ログイン情報：' . "\n";
            $body .= 'ユーザー名: ' . $user_obj->user_login . "\n";
            $body .= 'メールアドレス: ' . $user_obj->user_email . "\n\n";
            $body .= 'パスワードをお忘れの場合は、ログイン画面の「パスワードをお忘れですか？」からリセットしてください。' . "\n\n";
            $body .= 'ご不明な点がございましたら、システム管理者までお問い合わせください。' . "\n\n";
        } else {
            $subject = '[' . get_bloginfo( 'name' ) . '] スタッフ権限が削除されました';
            $body = $display_name . ' 様' . "\n\n";
            $body .= get_bloginfo( 'name' ) . ' の業務管理システム（KantanPro）のスタッフ権限が削除されました。' . "\n\n";
            $body .= '今後、システムへのアクセスができなくなります。' . "\n";
            $body .= 'ご質問がございましたら、システム管理者までお問い合わせください。' . "\n\n";
        }

        // 署名を追加
        if ( ! empty( $company_info ) ) {
            $body .= '―――――――――――――――――――――――――――' . "\n";
            $body .= $company_info . "\n";
        }

        // 自動送信であることを明記
        $body .= "\n※ このメールは自動送信されています。" . "\n";

        // ヘッダーを設定
        $headers = array();
        if ( ! empty( $from_email ) ) {
            if ( ! empty( $from_name ) ) {
                $headers[] = 'From: ' . $from_name . ' <' . $from_email . '>';
            } else {
                $headers[] = 'From: ' . $from_email;
            }
        }

        // メール送信を実行
        $sent = wp_mail( $to, $subject, $body, $headers );

        // ログ出力（詳細なエラー情報を含む）
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            if ( $sent ) {
                error_log( 'KTPWP Staff Notification: ' . $action . ' 通知メールを送信しました (User: ' . $display_name . ', Email: ' . $to . ')' );
            } else {
                // PHPMailerのエラー情報を取得
                global $phpmailer;
                $error_message = '';
                if ( isset( $phpmailer ) && is_object( $phpmailer ) && ! empty( $phpmailer->ErrorInfo ) ) {
                    $error_message = $phpmailer->ErrorInfo;
                }
                error_log( 'KTPWP Staff Notification: ' . $action . ' 通知メールの送信に失敗しました (User: ' . $display_name . ', Email: ' . $to . ', Error: ' . $error_message . ')' );
            }
        }

        return $sent;
    }

    /**
     * ユーザーの最終ログイン時間を記録
     *
     * @since 1.0.0
     * @param string  $user_login ユーザーログイン名
     * @param WP_User $user ユーザーオブジェクト
     * @return void
     */
    public function record_user_last_login( $user_login, $user ) {
        // KantanPro利用権限を持つユーザーのみ記録
        if ( $user->has_cap( 'ktpwp_access' ) || $user->has_cap( 'manage_options' ) ) {
            update_user_meta( $user->ID, 'last_activity', current_time( 'mysql' ) );
        }
    }

    public function create_admin_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'この設定ページにアクセスする権限がありません。' ) );
        }

        // 初期設定値がない場合は作成
        if ( false === get_option( $this->option_name ) ) {
            add_option(
                $this->option_name,
                array(
					'email_address' => '',
					'smtp_host' => '',
					'smtp_port' => '',
					'smtp_user' => '',
					'smtp_pass' => '',
					'smtp_secure' => '',
					'smtp_from_name' => '',
                )
            );
        }

        $options = get_option( $this->option_name );
        ?>
        <div class="wrap ktp-admin-wrap">
            <h1><span class="dashicons dashicons-email-alt"></span> <?php echo esc_html__( 'メール・SMTP設定', 'ktpwp' ); ?></h1>
            
            <?php
            // タブナビゲーション
            $this->display_settings_tabs( 'mail' );

            // 通知表示
            settings_errors( 'ktp_settings' );

            if ( isset( $_POST['test_email'] ) ) {
                $this->send_test_email();
            }

            // スタイリングされたコンテナ
            echo '<div class="ktp-settings-container">';

            // メール設定フォーム
            echo '<div class="ktp-settings-section">';
            echo '<form method="post" action="options.php">';
            settings_fields( $this->options_group );

            global $wp_settings_sections, $wp_settings_fields;

            // メール設定セクションの出力
            if ( isset( $wp_settings_sections['ktp-settings']['email_setting_section'] ) ) {
                $section = $wp_settings_sections['ktp-settings']['email_setting_section'];
                echo '<h2>' . esc_html( $section['title'] ) . '</h2>';
                if ( $section['callback'] ) {
					call_user_func( $section['callback'], $section );
                }
                if ( isset( $wp_settings_fields['ktp-settings']['email_setting_section'] ) ) {
                    echo '<table class="form-table">';
                    foreach ( $wp_settings_fields['ktp-settings']['email_setting_section'] as $field ) {
                        echo '<tr><th scope="row">' . esc_html( $field['title'] ) . '</th><td>';
                        call_user_func( $field['callback'], $field['args'] );
                        echo '</td></tr>';
                    }
                    echo '</table>';
                }
            }

            // SMTP設定セクションの出力
            if ( isset( $wp_settings_sections['ktp-settings']['smtp_setting_section'] ) ) {
                $section = $wp_settings_sections['ktp-settings']['smtp_setting_section'];
                echo '<h2>' . esc_html( $section['title'] ) . '</h2>';
                if ( $section['callback'] ) {
					call_user_func( $section['callback'], $section );
                }
                if ( isset( $wp_settings_fields['ktp-settings']['smtp_setting_section'] ) ) {
                    echo '<table class="form-table">';
                    foreach ( $wp_settings_fields['ktp-settings']['smtp_setting_section'] as $field ) {
                        echo '<tr><th scope="row">' . esc_html( $field['title'] ) . '</th><td>';
                        call_user_func( $field['callback'], $field['args'] );
                        echo '</td></tr>';
                    }
                    echo '</table>';
                }
            }

            echo '<div class="ktp-submit-button">';
            submit_button( '設定を保存', 'primary', 'submit', false );
            echo '</div>';
            echo '</form>';

            // テストメール送信フォーム
            echo '<div class="ktp-test-mail-form">';
            echo '<h3>テストメール送信</h3>';
            echo '<p>SMTPの設定が正しく機能しているか確認するためのテストメールを送信します。</p>';
            echo '<form method="post">';
            echo '<input type="hidden" name="test_email" value="1">';
            submit_button( 'テストメール送信', 'secondary', 'submit', false );
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

                <?php
                // 寄付フォームの表示
                $this->display_donation_section();
                ?>
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
            $this->display_settings_tabs( 'license' );

            // 通知表示
            settings_errors( 'ktp_activation_key' );
            ?>
            
            <div class="ktp-settings-container">
                <div class="ktp-settings-section">
                    <?php
                    // ライセンス設定（アクティベーションキー）フォーム
                    echo '<form method="post" action="options.php">';
                    settings_fields( 'ktp-group' );

                    // ライセンス設定セクションのみ出力
                    global $wp_settings_sections, $wp_settings_fields;
                    if ( isset( $wp_settings_sections['ktp-settings']['license_setting_section'] ) ) {
                        $section = $wp_settings_sections['ktp-settings']['license_setting_section'];
                        if ( $section['callback'] ) {
							call_user_func( $section['callback'], $section );
                        }
                        if ( isset( $wp_settings_fields['ktp-settings']['license_setting_section'] ) ) {
                            echo '<table class="form-table">';
                            foreach ( $wp_settings_fields['ktp-settings']['license_setting_section'] as $field ) {
                                echo '<tr><th scope="row">' . esc_html( $field['title'] ) . '</th><td>';
                                call_user_func( $field['callback'], $field['args'] );
                                echo '</td></tr>';
                            }
                            echo '</table>';
                        }
                    }

                    echo '<div class="ktp-submit-button">';
                    submit_button( 'ライセンスを認証', 'primary', 'submit', false );
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
            $this->display_settings_tabs( 'design' );

            // 通知表示
            settings_errors( 'ktp_design_settings' );
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
    private function display_settings_tabs( $current_tab ) {
        $tabs = array(
            'general' => array(
                'name' => __( '一般設定', 'ktpwp' ),
                'url' => admin_url( 'admin.php?page=ktp-settings' ),
                'icon' => 'dashicons-admin-settings',
            ),
            'mail' => array(
                'name' => __( 'メール・SMTP設定', 'ktpwp' ),
                'url' => admin_url( 'admin.php?page=ktp-mail-settings' ),
                'icon' => 'dashicons-email-alt',
            ),
            'design' => array(
                'name' => __( 'デザイン', 'ktpwp' ),
                'url' => admin_url( 'admin.php?page=ktp-design-settings' ),
                'icon' => 'dashicons-admin-appearance',
            ),
            'staff' => array(
                'name' => __( 'スタッフ管理', 'ktpwp' ),
                'url' => admin_url( 'admin.php?page=ktp-staff' ),
                'icon' => 'dashicons-groups',
            ),
            'license' => array(
                'name' => __( 'ライセンス設定', 'ktpwp' ),
                'url' => admin_url( 'admin.php?page=ktp-license' ),
                'icon' => 'dashicons-admin-network',
            ),
            'terms' => array(
                'name' => __( '利用規約管理', 'ktpwp' ),
                'url' => admin_url( 'admin.php?page=ktp-terms' ),
                'icon' => 'dashicons-text-page',
            ),
            'payment' => array(
                'name' => __( '決済設定', 'ktpwp' ),
                'url' => admin_url( 'admin.php?page=ktp-payment-settings' ),
                'icon' => 'dashicons-money-alt',
            ),
        );

        echo '<h2 class="nav-tab-wrapper">';
        foreach ( $tabs as $tab_id => $tab ) {
            $active = ( $current_tab === $tab_id ) ? 'nav-tab-active' : '';
            echo '<a href="' . esc_url( $tab['url'] ) . '" class="nav-tab ' . esc_attr( $active ) . '">';
            echo '<span class="dashicons ' . esc_attr( $tab['icon'] ) . '"></span> ';
            echo esc_html( $tab['name'] );
            echo '</a>';
        }
        echo '</h2>';
    }

    public function page_init() {

        // メディアライブラリ用のスクリプトとスタイルを読み込み
        if ( isset( $_GET['page'] ) && $_GET['page'] === 'ktp-settings' ) {
            wp_enqueue_media();
            wp_enqueue_script( 'media-upload' );
            wp_enqueue_script( 'thickbox' );
            wp_enqueue_style( 'thickbox' );
        }

        // アクティベーションキー保存時の通知
        if ( isset( $_POST['ktp_activation_key'] ) ) {
            $old = get_option( 'ktp_activation_key' );
            $new = sanitize_text_field( $_POST['ktp_activation_key'] );
            if ( $old !== $new ) {
                update_option( 'ktp_activation_key', $new );
                if ( method_exists( $this, 'show_notification' ) ) {
                    $this->show_notification( 'アクティベーションキーを保存しました。', true );
                }
                add_settings_error( 'ktp_activation_key', 'activation_key_saved', 'アクティベーションキーを保存しました。', 'updated' );
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

        // ロゴマークの登録
        register_setting(
            'ktp_general_group',
            'ktp_logo_image',
            array( $this, 'sanitize_text_field' )
        );

        // システム名の登録
        register_setting(
            'ktp_general_group',
            'ktp_system_name',
            array( $this, 'sanitize_text_field' )
        );

        // システムの説明の登録
        register_setting(
            'ktp_general_group',
            'ktp_system_description',
            array( $this, 'sanitize_textarea_field' )
        );

        register_setting(
            $this->options_group,
            $this->option_name,
            array( $this, 'sanitize' )
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

        // ロゴマーク
        add_settings_field(
            'ktp_logo_image',
            __( 'ロゴマーク', 'ktpwp' ),
            array( $this, 'logo_image_callback' ),
            'ktp-general',
            'general_setting_section'
        );

        // システム名
        add_settings_field(
            'ktp_system_name',
            __( 'システム名', 'ktpwp' ),
            array( $this, 'system_name_callback' ),
            'ktp-general',
            'general_setting_section'
        );

        // システムの説明
        add_settings_field(
            'ktp_system_description',
            __( 'システムの説明', 'ktpwp' ),
            array( $this, 'system_description_callback' ),
            'ktp-general',
            'general_setting_section'
        );

        // リストの表示件数
        add_settings_field(
            'work_list_range',
            __( 'リストの表示件数', 'ktpwp' ),
            array( $this, 'work_list_range_callback' ),
            'ktp-general',
            'general_setting_section'
        );

        // 納期警告日数
        add_settings_field(
            'delivery_warning_days',
            __( '納期警告日数', 'ktpwp' ),
            array( $this, 'delivery_warning_days_callback' ),
            'ktp-general',
            'general_setting_section'
        );

        // 適格請求書番号
        add_settings_field(
            'qualified_invoice_number',
            __( '適格請求書番号', 'ktpwp' ),
            array( $this, 'qualified_invoice_number_callback' ),
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
            array( $this, 'print_section_info' ),
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

        // 決済設定の初期化
        $this->init_payment_settings();

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
            array( $this, 'smtp_secure_callback' ),
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

    /**
     * テキストフィールドのサニタイズ
     *
     * @since 1.0.0
     * @param string $input 入力値
     * @return string サニタイズされた値
     */
    public function sanitize_text_field( $input ) {
        return sanitize_text_field( $input );
    }

    /**
     * テキストエリアフィールドのサニタイズ
     *
     * @since 1.0.0
     * @param string $input 入力値
     * @return string サニタイズされた値
     */
    public function sanitize_textarea_field( $input ) {
        return sanitize_textarea_field( $input );
    }

    public function sanitize( $input ) {
        $new_input = array();

        if ( isset( $input['email_address'] ) ) {
            $new_input['email_address'] = sanitize_email( $input['email_address'] );
        }

        if ( isset( $input['smtp_host'] ) ) {
            $new_input['smtp_host'] = sanitize_text_field( $input['smtp_host'] );
        }

        if ( isset( $input['smtp_port'] ) ) {
            $new_input['smtp_port'] = sanitize_text_field( $input['smtp_port'] );
        }

        if ( isset( $input['smtp_user'] ) ) {
            $new_input['smtp_user'] = sanitize_text_field( $input['smtp_user'] );
        }

        if ( isset( $input['smtp_pass'] ) ) {
            $new_input['smtp_pass'] = $input['smtp_pass'];
        }

        if ( isset( $input['smtp_secure'] ) ) {
            $new_input['smtp_secure'] = sanitize_text_field( $input['smtp_secure'] );
        }

        if ( isset( $input['smtp_from_name'] ) ) {
            $new_input['smtp_from_name'] = sanitize_text_field( $input['smtp_from_name'] );
        }

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
        $activation_key = get_option( 'ktp_activation_key' );
        $has_license = ! empty( $activation_key );
        ?>
        <input type="password" id="ktp_activation_key" name="ktp_activation_key" 
               value="<?php echo esc_attr( $activation_key ); ?>" 
               style="width:320px;max-width:100%;"
               placeholder="XXXX-XXXX-XXXX-XXXX"
               autocomplete="off">
        <div class="ktp-license-status <?php echo $has_license ? 'active' : 'inactive'; ?>">
            <?php if ( $has_license ) : ?>
                <span class="dashicons dashicons-yes-alt"></span> ライセンスキーが登録されています
            <?php else : ?>
                <span class="dashicons dashicons-warning"></span> ライセンスキーが未登録です
            <?php endif; ?>
        </div>
        <div style="font-size:12px;color:#555;margin-top:8px;">※ プラグインのライセンスキーを入力して、機能を有効化してください。</div>
        <?php
    }

    public function email_address_callback() {
        $options = get_option( $this->option_name );
        ?>
        <input type="email" id="email_address" name="<?php echo esc_attr( $this->option_name ); ?>[email_address]" 
               value="<?php echo isset( $options['email_address'] ) ? esc_attr( $options['email_address'] ) : ''; ?>" 
               style="width:320px;max-width:100%;" required 
               pattern="^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$" 
               placeholder="info@example.com">
        <div style="font-size:12px;color:#555;margin-top:4px;">※ サイトから届くメールが迷惑メールと認識されないよう、サイトのドメインと同じメールアドレスをご入力ください。</div>
        <?php
    }

    public function smtp_host_callback() {
        $options = get_option( $this->option_name );
        ?>
        <input type="text" id="smtp_host" name="<?php echo esc_attr( $this->option_name ); ?>[smtp_host]" 
               value="<?php echo isset( $options['smtp_host'] ) ? esc_attr( $options['smtp_host'] ) : ''; ?>" 
               style="width:220px;max-width:100%;" 
               placeholder="smtp.example.com">
        <?php
    }

    public function smtp_port_callback() {
        $options = get_option( $this->option_name );
        ?>
        <input type="text" id="smtp_port" name="<?php echo esc_attr( $this->option_name ); ?>[smtp_port]" 
               value="<?php echo isset( $options['smtp_port'] ) ? esc_attr( $options['smtp_port'] ) : ''; ?>" 
               style="width:80px;max-width:100%;" 
               placeholder="587">
        <?php
    }

    public function smtp_user_callback() {
        $options = get_option( $this->option_name );
        ?>
        <input type="text" id="smtp_user" name="<?php echo esc_attr( $this->option_name ); ?>[smtp_user]" 
               value="<?php echo isset( $options['smtp_user'] ) ? esc_attr( $options['smtp_user'] ) : ''; ?>" 
               style="width:220px;max-width:100%;" 
               placeholder="user@example.com">
        <?php
    }

    public function smtp_pass_callback() {
        $options = get_option( $this->option_name );
        ?>
        <input type="password" id="smtp_pass" name="<?php echo esc_attr( $this->option_name ); ?>[smtp_pass]" 
               value="<?php echo isset( $options['smtp_pass'] ) ? esc_attr( $options['smtp_pass'] ) : ''; ?>" 
               style="width:220px;max-width:100%;" 
               autocomplete="off">
        <?php
    }

    public function smtp_secure_callback() {
        $options = get_option( $this->option_name );
        $selected = isset( $options['smtp_secure'] ) ? $options['smtp_secure'] : '';
        ?>
        <select id="smtp_secure" name="<?php echo $this->option_name; ?>[smtp_secure]">
            <option value="" <?php selected( $selected, '' ); ?>>なし</option>
            <option value="ssl" <?php selected( $selected, 'ssl' ); ?>>SSL</option>
            <option value="tls" <?php selected( $selected, 'tls' ); ?>>TLS</option>
        </select>
        <?php
    }

    public function smtp_from_name_callback() {
        $options = get_option( $this->option_name );
        ?>
        <input type="text" id="smtp_from_name" name="<?php echo esc_attr( $this->option_name ); ?>[smtp_from_name]" 
               value="<?php echo isset( $options['smtp_from_name'] ) ? esc_attr( $options['smtp_from_name'] ) : ''; ?>" 
               style="width:220px;max-width:100%;" 
               placeholder="会社名や担当者名">
        <?php
    }

    public function setup_smtp_settings( $phpmailer ) {
        try {
            $options = get_option( $this->option_name );

            if ( ! empty( $options['smtp_host'] ) && ! empty( $options['smtp_port'] ) && ! empty( $options['smtp_user'] ) && ! empty( $options['smtp_pass'] ) ) {
                $phpmailer->isSMTP();
                $phpmailer->Host = $options['smtp_host'];
                $phpmailer->Port = $options['smtp_port'];
                $phpmailer->SMTPAuth = true;
                $phpmailer->Username = $options['smtp_user'];
                $phpmailer->Password = $options['smtp_pass'];

                if ( ! empty( $options['smtp_secure'] ) ) {
                    $phpmailer->SMTPSecure = $options['smtp_secure'];
                }

                $phpmailer->CharSet = 'UTF-8';

                if ( ! empty( $options['email_address'] ) ) {
                    $phpmailer->setFrom(
                        $options['email_address'],
                        ! empty( $options['smtp_from_name'] ) ? $options['smtp_from_name'] : $options['email_address'],
                        false
                    );
                }
            }
        } catch ( Throwable $e ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( $e->getMessage() ); }
        }
    }

    private function send_test_email() {
        $options = get_option( $this->option_name );
        $to = $options['email_address'];
        $subject = '【KTPWP】SMTPテストメール';
        $body = "このメールはKTPWPプラグインのSMTPテスト送信です。\n\n送信元: {$options['email_address']}";
        $headers = array();

        if ( ! empty( $options['smtp_from_name'] ) ) {
            $headers[] = 'From: ' . $options['smtp_from_name'] . ' <' . $options['email_address'] . '>';
        } else {
            $headers[] = 'From: ' . $options['email_address'];
        }

        $sent = wp_mail( $to, $subject, $body, $headers );

        if ( $sent ) {
            $this->test_mail_message = 'テストメールを送信しました。メールボックスをご確認ください。';
            $this->test_mail_status = 'success';

            // 成功通知を表示
            $this->show_notification( '✉️ テストメールを送信しました。メールボックスをご確認ください。', true );

            add_settings_error(
                'ktp_settings',
                'test_mail_success',
                'テストメールを送信しました。メールボックスをご確認ください。',
                'updated'
            );
        } else {
            global $phpmailer;
            $error_message = '';
            if ( isset( $phpmailer ) && is_object( $phpmailer ) ) {
                $error_message = $phpmailer->ErrorInfo;
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'KTPWP SMTPテストメール送信失敗: ' . $error_message ); }
            } else {
                $error_message = 'PHPMailerインスタンスが取得できませんでした';
                error_log( 'KTPWP SMTPテストメール送信失敗: ' . $error_message );
            }

            $this->test_mail_message = 'テストメールの送信に失敗しました。SMTP設定をご確認ください。';
            $this->test_mail_status = 'error';

            // エラー通知を表示
            $this->show_notification( '⚠️ テストメールの送信に失敗しました。SMTP設定をご確認ください。', false );

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
     * @param bool   $success 成功メッセージかどうか（true=成功、false=エラー）
     */
    private function show_notification( $message, $success = true ) {
        $notification_type = $success ? 'success' : 'error';

        echo '<script>
            document.addEventListener("DOMContentLoaded", function() {
                if (typeof showKtpNotification === "function") {
                    showKtpNotification("' . esc_js( $message ) . '", "' . $notification_type . '");
                } else {
                    // フォールバック: 古い通知システム
                    console.warn("KTP Notification system not loaded, using fallback");
                    alert("' . esc_js( $message ) . '");
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

        if ( isset( $input['delivery_warning_days'] ) ) {
            $warning_days = intval( $input['delivery_warning_days'] );
            // 最小1日、最大365日に制限
            $new_input['delivery_warning_days'] = max( 1, min( 365, $warning_days ) );
        }

        if ( isset( $input['qualified_invoice_number'] ) ) {
            // 適格請求書番号のサニタイズ：半角英数字、ハイフン、スペースのみ許可
            $qualified_invoice_number = sanitize_text_field( $input['qualified_invoice_number'] );
            // 英数字、ハイフン、スペースのみ許可（全角文字は半角に変換）
            $qualified_invoice_number = preg_replace( '/[^a-zA-Z0-9\-\s]/', '', $qualified_invoice_number );
            $new_input['qualified_invoice_number'] = $qualified_invoice_number;
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
     * ロゴマークフィールドのコールバック
     *
     * @since 1.0.0
     * @return void
     */
    public function logo_image_callback() {
        $default_logo = plugins_url( 'images/default/icon.png', KANTANPRO_PLUGIN_FILE );
        $value = get_option( 'ktp_logo_image', $default_logo );
        ?>
        <div class="logo-upload-field">
            <input type="hidden" id="ktp_logo_image" name="ktp_logo_image" value="<?php echo esc_attr( $value ); ?>" />
            <div class="logo-preview" style="margin-bottom: 10px;">
                <?php if ( ! empty( $value ) ) : ?>
                    <img src="<?php echo esc_url( $value ); ?>" alt="<?php echo esc_attr__( 'ロゴマーク', 'ktpwp' ); ?>" style="max-width: 200px; max-height: 100px; display: block;" />
                <?php else : ?>
                    <div class="no-logo-placeholder" style="width: 200px; height: 100px; border: 2px dashed #ccc; display: flex; align-items: center; justify-content: center; color: #999; font-size: 14px;">
                        <?php echo esc_html__( 'ロゴマークが設定されていません', 'ktpwp' ); ?>
                    </div>
                <?php endif; ?>
            </div>
            <button type="button" class="button" id="upload-logo-btn">
                <?php echo esc_html__( 'ロゴマークを選択', 'ktpwp' ); ?>
            </button>
            <button type="button" class="button" id="remove-logo-btn" style="<?php echo empty( $value ) ? 'display:none;' : ''; ?>">
                <?php echo esc_html__( 'ロゴマークを削除', 'ktpwp' ); ?>
            </button>
            <div style="font-size:12px;color:#555;margin-top:4px;">
                <?php echo esc_html__( '※ ヘッダーに表示するロゴマーク画像を設定してください。推奨サイズ: 200×100px以下', 'ktpwp' ); ?>
            </div>
        </div>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            var mediaUploader;
            
            $('#upload-logo-btn').click(function(e) {
                e.preventDefault();
                
                if (mediaUploader) {
                    mediaUploader.open();
                    return;
                }
                
                mediaUploader = wp.media.frames.file_frame = wp.media({
                    title: '<?php echo esc_js( __( 'ロゴマークを選択', 'ktpwp' ) ); ?>',
                    button: {
                        text: '<?php echo esc_js( __( 'この画像を使用', 'ktpwp' ) ); ?>'
                    },
                    multiple: false,
                    library: {
                        type: 'image'
                    }
                });
                
                mediaUploader.on('select', function() {
                    var attachment = mediaUploader.state().get('selection').first().toJSON();
                    $('#ktp_logo_image').val(attachment.url);
                    $('.logo-preview').html('<img src="' + attachment.url + '" alt="<?php echo esc_attr__( 'ロゴマーク', 'ktpwp' ); ?>" style="max-width: 200px; max-height: 100px; display: block;" />');
                    $('#remove-logo-btn').show();
                });
                
                mediaUploader.open();
            });
            
            $('#remove-logo-btn').click(function(e) {
                e.preventDefault();
                $('#ktp_logo_image').val('');
                $('.logo-preview').html('<div class="no-logo-placeholder" style="width: 200px; height: 100px; border: 2px dashed #ccc; display: flex; align-items: center; justify-content: center; color: #999; font-size: 14px;"><?php echo esc_js( __( 'ロゴマークが設定されていません', 'ktpwp' ) ); ?></div>');
                $(this).hide();
            });
        });
        </script>
        <?php
    }

    /**
     * システム名フィールドのコールバック
     *
     * @since 1.0.0
     * @return void
     */
    public function system_name_callback() {
        $value = get_option( 'ktp_system_name', 'ChaChatWorks' );
        ?>
        <input type="text" id="ktp_system_name" name="ktp_system_name" value="<?php echo esc_attr( $value ); ?>" class="regular-text" />
        <div style="font-size:12px;color:#555;margin-top:4px;">
            <?php echo esc_html__( '※ システムの名称を設定してください。', 'ktpwp' ); ?>
        </div>
        <?php
    }

    /**
     * システムの説明フィールドのコールバック
     *
     * @since 1.0.0
     * @return void
     */
    public function system_description_callback() {
        $value = get_option( 'ktp_system_description', 'チャチャと仕事が片付く神システム！' );
        ?>
        <textarea id="ktp_system_description" name="ktp_system_description" rows="3" cols="50" class="large-text"><?php echo esc_textarea( $value ); ?></textarea>
        <div style="font-size:12px;color:#555;margin-top:4px;">
            <?php echo esc_html__( '※ システムの説明文を設定してください。', 'ktpwp' ); ?>
        </div>
        <?php
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
     * 納期警告日数フィールドのコールバック
     *
     * @since 1.0.0
     * @return void
     */
    public function delivery_warning_days_callback() {
        $options = get_option( 'ktp_general_settings' );
        $value = isset( $options['delivery_warning_days'] ) ? $options['delivery_warning_days'] : 3;
        ?>
        <select id="delivery_warning_days" name="ktp_general_settings[delivery_warning_days]">
            <option value="1" <?php selected( $value, 1 ); ?>>1日</option>
            <option value="3" <?php selected( $value, 3 ); ?>>3日</option>
            <option value="7" <?php selected( $value, 7 ); ?>>7日</option>
            <option value="14" <?php selected( $value, 14 ); ?>>14日</option>
            <option value="30" <?php selected( $value, 30 ); ?>>30日</option>
        </select>
        <div style="font-size:12px;color:#555;margin-top:4px;">
            <?php echo esc_html__( '※ 納期警告日数を設定してください。', 'ktpwp' ); ?>
        </div>
        <?php
    }

    /**
     * 適格請求書番号フィールドのコールバック
     *
     * @since 1.0.0
     * @return void
     */
    public function qualified_invoice_number_callback() {
        $options = get_option( 'ktp_general_settings' );
        $value = isset( $options['qualified_invoice_number'] ) ? $options['qualified_invoice_number'] : '';
        ?>
        <input type="text" id="qualified_invoice_number" name="ktp_general_settings[qualified_invoice_number]" value="<?php echo esc_attr( $value ); ?>" class="regular-text" />
        <div style="font-size:12px;color:#555;margin-top:4px;">
            <?php echo esc_html__( '※ 適格請求書発行事業者の登録番号を入力してください。（例：T1234567890123）', 'ktpwp' ); ?>
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
        $value = is_string( $value ) ? $value : '';
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
        $old_setting = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT my_company_content FROM {$setting_table} WHERE id = %d",
                1
            )
        );

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
                $image_url = plugin_dir_url( __DIR__ ) . $image_path;
            } else {
                $image_url = $image_path;
            }
        }
        ?>
        <div class="ktp-image-upload-field">
            <input type="hidden" id="header_bg_image" name="ktp_design_settings[header_bg_image]" value="<?php echo esc_attr( $image_value ); ?>" data-default-url="<?php echo esc_url( plugin_dir_url( __DIR__ ) . 'images/default/header_bg_image.png' ); ?>" />
            
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
                $image_url = plugin_dir_url( __DIR__ ) . $image_path;
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

                // コントローラーの背景色設定（タブの下線色を使用）
                $custom_css .= '
/* 各タブのコントローラー背景色設定 - PC/タブレット/モバイル共通 */
.controller {
    background-color: ' . esc_attr( $tab_border_color ) . ' !important;
    padding: 10px 10px 0 10px !important;
    border-radius: 0 0 4px 4px !important;
    margin-bottom: 10px !important;
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
.ktp_data_skill_list_box > .ktp_data_list_item:nth-child(odd),
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
.ktp_data_skill_list_box > .ktp_data_list_item:nth-child(even),
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

        // 決済設定の初期値保存処理
        if ( isset( $_GET['page'] ) && $_GET['page'] === 'ktp-payment-settings' ) {
            $this->ensure_payment_settings_defaults();
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
                'custom_css' => '',
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

    /**
     * 決済設定の初期値を確実に保存
     */
    private function ensure_payment_settings_defaults() {
        $payment_option_name = 'ktp_payment_settings';
        $existing_payment = get_option( $payment_option_name, array() );
        $payment_updated = false;
        
        // EDD APIキーが空または復号できない場合はデフォルト値を設定
        if ( ! isset( $existing_payment['edd_api_key'] ) || empty( $existing_payment['edd_api_key'] ) ) {
            $existing_payment['edd_api_key'] = self::strong_encrypt_static('58c310e565651392890a7a28cd6527b6');
            $payment_updated = true;
        } else {
            // 既存の値を復号してみる
            $decrypted_key = self::strong_decrypt_static( $existing_payment['edd_api_key'] );
            if ( empty( $decrypted_key ) ) {
                // base64暗号化または平文の場合
                $maybe_old = base64_decode( $existing_payment['edd_api_key'] );
                if ( $maybe_old && preg_match('/^[0-9a-f]{32}$/', $maybe_old) ) {
                    $existing_payment['edd_api_key'] = self::strong_encrypt_static( $maybe_old );
                    $payment_updated = true;
                } elseif ( preg_match('/^[0-9a-f]{32}$/', $existing_payment['edd_api_key'] ) ) {
                    // 平文の場合
                    $existing_payment['edd_api_key'] = self::strong_encrypt_static( $existing_payment['edd_api_key'] );
                    $payment_updated = true;
                } else {
                    // 復号できない場合はデフォルト値を設定
                    $existing_payment['edd_api_key'] = self::strong_encrypt_static('58c310e565651392890a7a28cd6527b6');
                    $payment_updated = true;
                }
            }
        }
        
        // EDD APIトークンが空または復号できない場合はデフォルト値を設定
        if ( ! isset( $existing_payment['edd_api_token'] ) || empty( $existing_payment['edd_api_token'] ) ) {
            $existing_payment['edd_api_token'] = self::strong_encrypt_static('45055ce430c876042c6b9571508d5977');
            $payment_updated = true;
        } else {
            // 既存の値を復号してみる
            $decrypted_token = self::strong_decrypt_static( $existing_payment['edd_api_token'] );
            if ( empty( $decrypted_token ) ) {
                // base64暗号化または平文の場合
                $maybe_old = base64_decode( $existing_payment['edd_api_token'] );
                if ( $maybe_old && preg_match('/^[0-9a-f]{32}$/', $maybe_old) ) {
                    $existing_payment['edd_api_token'] = self::strong_encrypt_static( $maybe_old );
                    $payment_updated = true;
                } elseif ( preg_match('/^[0-9a-f]{32}$/', $existing_payment['edd_api_token'] ) ) {
                    // 平文の場合
                    $existing_payment['edd_api_token'] = self::strong_encrypt_static( $existing_payment['edd_api_token'] );
                    $payment_updated = true;
                } else {
                    // 復号できない場合はデフォルト値を設定
                    $existing_payment['edd_api_token'] = self::strong_encrypt_static('45055ce430c876042c6b9571508d5977');
                    $payment_updated = true;
                }
            }
        }
        
        // Stripeシークレットキーも同様に処理
        if ( isset( $existing_payment['stripe_secret_key'] ) && ! empty( $existing_payment['stripe_secret_key'] ) ) {
            $decrypted_stripe = self::strong_decrypt_static( $existing_payment['stripe_secret_key'] );
            if ( empty( $decrypted_stripe ) ) {
                // base64暗号化または平文の場合
                $maybe_old = base64_decode( $existing_payment['stripe_secret_key'] );
                if ( $maybe_old && strpos($maybe_old, 'sk_') === 0 ) {
                    $existing_payment['stripe_secret_key'] = self::strong_encrypt_static( $maybe_old );
                    $payment_updated = true;
                } elseif ( strpos($existing_payment['stripe_secret_key'], 'sk_') === 0 ) {
                    // 平文の場合
                    $existing_payment['stripe_secret_key'] = self::strong_encrypt_static( $existing_payment['stripe_secret_key'] );
                    $payment_updated = true;
                }
            }
        }
        
        if ( $payment_updated ) {
            update_option( $payment_option_name, $existing_payment );
        }
    }

    /**
     * 管理画面メニューの追加（デバッグログとREST API設定用）
     *
     * @since 1.3.0
     */
    public static function add_admin_menu() {
        add_options_page(
            'KTPWP設定',
            'KTPWP設定',
            'manage_options',
            'ktpwp-settings',
            array( __CLASS__, 'admin_page' )
        );
    }

    /**
     * 管理画面ページの表示
     *
     * @since 1.3.0
     */
    public static function admin_page() {
        // 設定の保存処理
        if ( isset( $_POST['submit'] ) && wp_verify_nonce( $_POST['ktpwp_settings_nonce'], 'ktpwp_settings' ) ) {
            self::save_settings();
        }

        $current_settings = self::get_all_settings();
        ?>
        <div class="wrap">
            <h1>KTPWP設定</h1>
            
            <form method="post" action="">
                <?php wp_nonce_field( 'ktpwp_settings', 'ktpwp_settings_nonce' ); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">デバッグログ設定</th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="checkbox" name="ktpwp_debug_log_enabled" value="1" 
                                           <?php checked( $current_settings['debug_log_enabled'], '1' ); ?> />
                                    デバッグログを有効にする
                                </label>
                                <p class="description">
                                    デバッグログは安全な場所（wp-content/logs/）に保存されます。
                                </p>
                            </fieldset>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">REST API制限</th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="checkbox" name="ktpwp_rest_api_restricted" value="1" 
                                           <?php checked( $current_settings['rest_api_restricted'], '1' ); ?> />
                                    フロントエンドでのREST APIをログインユーザーのみに制限する
                                </label>
                                <p class="description">
                                    管理画面やブロックエディターは常に許可されます。
                                </p>
                            </fieldset>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">トラブルシューティング</th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="checkbox" name="ktpwp_disable_rest_api_restriction" value="1" 
                                           <?php checked( $current_settings['disable_rest_api_restriction'], '1' ); ?> />
                                    REST API制限を完全に無効化する（サイトヘルスエラーが解決されない場合）
                                </label>
                                <p class="description">
                                    <strong>注意:</strong> この設定を有効にすると、セキュリティが低下する可能性があります。
                                    サイトヘルスエラーが解決されない場合のみ使用してください。
                                </p>
                            </fieldset>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
            
            <h2>現在の設定状況</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">プラグインバージョン</th>
                    <td><?php echo esc_html( $current_settings['version'] ); ?></td>
                </tr>
                <tr>
                    <th scope="row">インストール日</th>
                    <td><?php echo esc_html( $current_settings['installed_date'] ); ?></td>
                </tr>
                <tr>
                    <th scope="row">デバッグモード</th>
                    <td><?php echo esc_html( $current_settings['debug_mode'] ); ?></td>
                </tr>
                <tr>
                    <th scope="row">サイトURL</th>
                    <td><?php echo esc_html( home_url() ); ?></td>
                </tr>
                <tr>
                    <th scope="row">開発環境</th>
                    <td><?php echo ( strpos( home_url(), 'localhost' ) !== false || strpos( home_url(), '127.0.0.1' ) !== false ) ? 'はい' : 'いいえ'; ?></td>
                </tr>
                <tr>
                    <th scope="row">REST API制限の状態</th>
                    <td>
                        <?php
                        $rest_api_status = '有効';
                        if ( class_exists( 'KTP_Settings' ) ) {
                            $rest_api_restricted = self::get_setting( 'rest_api_restricted', '1' );
                            $disable_rest_api_restriction = self::get_setting( 'disable_rest_api_restriction', '0' );

                            if ( $disable_rest_api_restriction === '1' ) {
                                $rest_api_status = '<span style="color: red;">完全無効化</span>';
                            } elseif ( $rest_api_restricted !== '1' ) {
                                $rest_api_status = '<span style="color: orange;">無効</span>';
                            } elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG && ( strpos( home_url(), 'localhost' ) !== false || strpos( home_url(), '127.0.0.1' ) !== false ) ) {
                                $rest_api_status = '<span style="color: blue;">開発環境で緩和</span>';
                            }
                        }
                        echo $rest_api_status;
                        ?>
                    </td>
                </tr>
            </table>
            
            <h2>推奨設定（wp-config.php）</h2>
            <div class="notice notice-info">
                <p><strong>デバッグログの安全な設定:</strong></p>
                <pre><code>// デバッグモードを有効化
define( 'WP_DEBUG', true );

// デバッグログを安全な場所に保存
define( 'WP_DEBUG_LOG', WP_CONTENT_DIR . '/logs/debug.log' );

// デバッグ表示を無効化（本番環境では必須）
define( 'WP_DEBUG_DISPLAY', false );

// スクリプトエラーの表示を無効化
@ini_set( 'display_errors', 0 );</code></pre>
            </div>
        </div>
        <?php
    }

    /**
     * 設定の保存
     *
     * @since 1.3.0
     */
    private static function save_settings() {
        // デバッグログ設定
        $debug_log_enabled = isset( $_POST['ktpwp_debug_log_enabled'] ) ? '1' : '0';
        update_option( 'ktpwp_debug_log_enabled', $debug_log_enabled );

        // REST API制限設定
        $rest_api_restricted = isset( $_POST['ktpwp_rest_api_restricted'] ) ? '1' : '0';
        update_option( 'ktpwp_rest_api_restricted', $rest_api_restricted );

        // REST API制限の完全無効化設定
        $disable_rest_api_restriction = isset( $_POST['ktpwp_disable_rest_api_restriction'] ) ? '1' : '0';
        update_option( 'ktpwp_disable_rest_api_restriction', $disable_rest_api_restriction );

        // 設定保存メッセージ
        add_action(
            'admin_notices',
            function () {
				echo '<div class="notice notice-success is-dismissible"><p>設定を保存しました。</p></div>';
			}
        );
    }

    /**
     * すべての設定を取得
     *
     * @since 1.3.0
     * @return array
     */
    public static function get_all_settings() {
        return array(
            'version' => get_option( 'ktpwp_version', KANTANPRO_PLUGIN_VERSION ),
            'installed_date' => get_option( 'ktpwp_installed_date', '不明' ),
            'debug_mode' => get_option( 'ktpwp_debug_mode', 'disabled' ),
            'debug_log_enabled' => get_option( 'ktpwp_debug_log_enabled', '0' ),
            'rest_api_restricted' => get_option( 'ktpwp_rest_api_restricted', '1' ),
            'disable_rest_api_restriction' => get_option( 'ktpwp_disable_rest_api_restriction', '0' ),
        );
    }

    /**
     * プラグインの設定を取得
     *
     * @since 1.3.0
     * @param string $key 設定キー
     * @param mixed  $default デフォルト値
     * @return mixed
     */
    public static function get_setting( $key, $default = null ) {
        return get_option( 'ktpwp_' . $key, $default );
    }

    /**
     * プラグインの設定を保存
     *
     * @since 1.3.0
     * @param string $key 設定キー
     * @param mixed  $value 設定値
     * @return bool
     */
    public static function save_setting( $key, $value ) {
        return update_option( 'ktpwp_' . $key, $value );
    }

    /**
     * デバッグログの書き込み（安全な方法）
     *
     * @since 1.3.0
     * @param string $message ログメッセージ
     * @param array  $context コンテキスト情報
     */
    public static function log_debug( $message, $context = array() ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            $log_message = '[' . date( 'Y-m-d H:i:s' ) . '] KTPWP: ' . $message;

            if ( ! empty( $context ) ) {
                $log_message .= ' | Context: ' . wp_json_encode( $context );
            }

            // 安全なログファイルパスを使用
            $log_file = defined( 'WP_DEBUG_LOG' ) ? WP_DEBUG_LOG : WP_CONTENT_DIR . '/logs/debug.log';

            // ログディレクトリが存在しない場合は作成
            $log_dir = dirname( $log_file );
            if ( ! is_dir( $log_dir ) ) {
                wp_mkdir_p( $log_dir );
            }

            // ログファイルに書き込み
            error_log( $log_message );
        }
    }

    /**
     * 寄付通知プレビューセクションの表示
     */
    public function display_donation_preview_section() {
        ?>
        <div class="ktp-settings-section">
            <h3><?php esc_html_e( '寄付通知プレビュー', 'ktpwp' ); ?></h3>
            <p><?php esc_html_e( 'フロントエンドで表示される寄付通知のプレビューを確認できます。', 'ktpwp' ); ?></p>
            <?php $this->donation_notice_preview_callback(); ?>
        </div>
        <?php
    }

    /**
     * 寄付統計セクションの表示
     */
    public function display_donation_stats_section() {
        ?>
        <div class="ktp-settings-section">
            <h3><?php esc_html_e( '寄付統計', 'ktpwp' ); ?></h3>
            <p><?php esc_html_e( '寄付の統計情報と分析データを確認できます。', 'ktpwp' ); ?></p>
            <?php $this->donation_stats_dashboard_callback(); ?>
        </div>
        <?php
    }

    /**
     * 強固な暗号化（AES-256-CBC + サイト固有キー）
     */
    private function strong_encrypt( $plain_text ) {
        if ( empty( $plain_text ) ) {
            return '';
        }
        
        $key = self::get_encryption_key();
        $iv = self::get_encryption_iv();
        
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP Encryption: Using site-specific encryption key' );
        }
        
        return base64_encode( openssl_encrypt( $plain_text, 'AES-256-CBC', $key, 0, $iv ) );
    }

    /**
     * 強固な復号
     */
    private function strong_decrypt( $encrypted_text ) {
        if ( empty( $encrypted_text ) ) {
            return '';
        }
        
        $key = self::get_encryption_key();
        $iv = self::get_encryption_iv();
        
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP Decryption: Using site-specific encryption key' );
        }
        
        $decrypted = openssl_decrypt( base64_decode( $encrypted_text ), 'AES-256-CBC', $key, 0, $iv );
        return $decrypted === false ? '' : $decrypted;
    }

    // 静的に使える強固な暗号化
    public static function strong_encrypt_static( $plain_text ) {
        if ( empty( $plain_text ) ) {
            return '';
        }
        
        $key = self::get_encryption_key();
        $iv = self::get_encryption_iv();
        
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP Encryption Static: Using site-specific encryption key' );
        }
        
        return base64_encode( openssl_encrypt( $plain_text, 'AES-256-CBC', $key, 0, $iv ) );
    }
    
    public static function strong_decrypt_static( $encrypted_text ) {
        if ( empty( $encrypted_text ) ) {
            return '';
        }
        
        $key = self::get_encryption_key();
        $iv = self::get_encryption_iv();
        
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP Decryption Static: Using site-specific encryption key' );
        }
        
        $decrypted = openssl_decrypt( base64_decode( $encrypted_text ), 'AES-256-CBC', $key, 0, $iv );
        return $decrypted === false ? '' : $decrypted;
    }

    /**
     * 暗号化キーの取得（サイト固有）
     */
    private static function get_encryption_key() {
        // WordPressのSALT定数をチェック
        if ( defined( 'AUTH_KEY' ) && ! empty( AUTH_KEY ) ) {
            return AUTH_KEY;
        }
        
        // フォールバック：サイトURLとDBプレフィックスを使用
        $site_url = get_site_url();
        global $wpdb;
        $prefix = $wpdb->prefix;
        return hash( 'sha256', $site_url . $prefix . 'ktpwp_key' );
    }
    
    /**
     * 初期化ベクトル（IV）の取得（サイト固有）
     */
    private static function get_encryption_iv() {
        if ( defined( 'SECURE_AUTH_KEY' ) && ! empty( SECURE_AUTH_KEY ) ) {
            return substr( hash( 'sha256', SECURE_AUTH_KEY ), 0, 16 );
        }
        
        // フォールバック：サイトURLとDBプレフィックスを使用
        $site_url = get_site_url();
        global $wpdb;
        $prefix = $wpdb->prefix;
        return substr( hash( 'sha256', $site_url . $prefix . 'ktpwp_iv' ), 0, 16 );
    }
}

// インスタンスを初期化
KTP_Settings::get_instance();
