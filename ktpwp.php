<?php
/**
 * Plugin Name: KantanPro
 * Plugin URI: https://www.kantanpro.com/
 * Description: スモールビジネス向けの仕事効率化システム。ショートコード[ktpwp_all_tab]を固定ページに設置してください。
 * Version: 1.0.6(preview)
 * Author: KantanPro
 * Author URI: https://www.kantanpro.com/kantanpro-page
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: KantanPro
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.5
 * Requires PHP: 7.4
 * Update URI: https://github.com/KantanPro/freeKTP
 *
 * @package KantanPro
 */

// セキュリティ: 直接アクセスを防止
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// プラグイン定数定義
if ( ! defined( 'KANTANPRO_PLUGIN_VERSION' ) ) {
    define( 'KANTANPRO_PLUGIN_VERSION', '1.0.6(preview)' );
}
if ( ! defined( 'KANTANPRO_PLUGIN_NAME' ) ) {
    define( 'KANTANPRO_PLUGIN_NAME', 'KantanPro' );
}
if ( ! defined( 'KANTANPRO_PLUGIN_DESCRIPTION' ) ) {
    // 翻訳読み込み警告を回避するため、initアクションで設定
    define( 'KANTANPRO_PLUGIN_DESCRIPTION', 'スモールビジネス向けの仕事効率化システム。ショートコード[ktpwp_all_tab]を固定ページに設置してください。' );
}

// Define KTPWP_PLUGIN_VERSION if not already defined, possibly aliasing KANTANPRO_PLUGIN_VERSION
if ( ! defined( 'KTPWP_PLUGIN_VERSION' ) ) {
    if ( defined( 'KANTANPRO_PLUGIN_VERSION' ) ) {
        define( 'KTPWP_PLUGIN_VERSION', KANTANPRO_PLUGIN_VERSION );
    } else {
        // Fallback if KANTANPRO_PLUGIN_VERSION is also not defined for some reason
        define( 'KTPWP_PLUGIN_VERSION', '1.0.0' ); // You might want to set a default or handle this case differently
    }
}

if ( ! defined( 'KANTANPRO_PLUGIN_FILE' ) ) {
    define( 'KANTANPRO_PLUGIN_FILE', __FILE__ );
}
if ( ! defined( 'KANTANPRO_PLUGIN_DIR' ) ) {
    define( 'KANTANPRO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'KANTANPRO_PLUGIN_URL' ) ) {
    define( 'KANTANPRO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

// KTPWP Prefixed constants for internal consistency
if ( ! defined( 'KTPWP_PLUGIN_FILE' ) ) {
    define( 'KTPWP_PLUGIN_FILE', __FILE__ );
}
if ( ! defined( 'KTPWP_PLUGIN_DIR' ) ) {
    define( 'KTPWP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'MY_PLUGIN_VERSION' ) ) {
    define( 'MY_PLUGIN_VERSION', KANTANPRO_PLUGIN_VERSION );
}
if ( ! defined( 'MY_PLUGIN_PATH' ) ) {
    define( 'MY_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'MY_PLUGIN_URL' ) ) {
    define( 'MY_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

// === WordPress標準更新システム ===
// シンプルなバージョン管理

add_action( 'admin_init', 'ktpwp_upgrade', 10, 0 );

/**
 * シンプルなアップグレード処理
 */
function ktpwp_upgrade() {
    $old_ver = get_option( 'ktpwp_version', '0' );
    $new_ver = KANTANPRO_PLUGIN_VERSION;

    if ( $old_ver === $new_ver ) {
        return;
    }

    do_action( 'ktpwp_upgrade', $new_ver, $old_ver );

    update_option( 'ktpwp_version', $new_ver );
}

/**
 * プラグインクラスの自動読み込み
 */
function ktpwp_autoload_classes() {
    $classes = array(
        'Kntan_Client_Class'    => 'includes/class-tab-client.php',
        'Kntan_Service_Class'   => 'includes/class-tab-service.php',
        'KTPWP_Supplier_Class'  => 'includes/class-tab-supplier.php',
        'KTPWP_Supplier_Security' => 'includes/class-supplier-security.php',
        'KTPWP_Supplier_Data'   => 'includes/class-supplier-data.php',
        'KTPWP_Report_Class'    => 'includes/class-tab-report.php',
        'Kntan_Order_Class'     => 'includes/class-tab-order.php',
        'KTPWP_Plugin_Reference' => 'includes/class-plugin-reference.php',
        // 新しいクラス構造
        'KTPWP'                 => 'includes/class-ktpwp.php',
        'KTPWP_Main'            => 'includes/class-ktpwp-main.php',
        'KTPWP_Loader'          => 'includes/class-ktpwp-loader.php',
        'KTPWP_Security'        => 'includes/class-ktpwp-security.php',
        'KTPWP_Ajax'            => 'includes/class-ktpwp-ajax.php',
        'KTPWP_Assets'          => 'includes/class-ktpwp-assets.php',
        'KTPWP_Nonce_Manager'   => 'includes/class-ktpwp-nonce-manager.php',
        'KTPWP_Shortcodes'      => 'includes/class-ktpwp-shortcodes.php',
        'KTPWP_Redirect'        => 'includes/class-ktpwp-redirect.php',
        'KTPWP_Contact_Form'    => 'includes/class-ktpwp-contact-form.php',
        'KTPWP_Database'        => 'includes/class-ktpwp-database.php',
        'KTPWP_Order'           => 'includes/class-ktpwp-order.php',
        'KTPWP_Order_Items'     => 'includes/class-ktpwp-order-items.php',
        'KTPWP_Order_UI'        => 'includes/class-ktpwp-order-ui.php',
        'KTPWP_Staff_Chat'      => 'includes/class-ktpwp-staff-chat.php',
        'KTPWP_Service_DB'      => 'includes/class-ktpwp-service-db.php',
        'KTPWP_Service_UI'      => 'includes/class-ktpwp-service-ui.php',
        'KTPWP_UI_Generator'    => 'includes/class-ktpwp-ui-generator.php',
        'KTPWP_Graph_Renderer'  => 'includes/class-ktpwp-graph-renderer.php',
        // POSTデータ安全処理クラス（Adminer警告対策）
        'KTPWP_Post_Data_Handler' => 'includes/class-ktpwp-post-handler.php',
        // クライアント管理の新クラス
        'KTPWP_Client_DB'       => 'includes/class-ktpwp-client-db.php',
        'KTPWP_Client_UI'       => 'includes/class-ktpwp-client-ui.php',
        'KTPWP_Department_Manager' => 'includes/class-department-manager.php',
        'KTPWP_Terms_Of_Service' => 'includes/class-ktpwp-terms-of-service.php',
        'KTPWP_Update_Checker'  => 'includes/class-ktpwp-update-checker.php',
        'KTPWP_Donation'        => 'includes/class-ktpwp-donation.php',
    );

    foreach ( $classes as $class_name => $file_path ) {
        if ( ! class_exists( $class_name ) ) {
            $full_path = MY_PLUGIN_PATH . $file_path;
            if ( file_exists( $full_path ) ) {
                require_once $full_path;
            }
        }
    }
}

// --- Ajaxハンドラ（協力会社・職能リスト取得）を必ず読み込む ---
require_once __DIR__ . '/includes/ajax-supplier-cost.php';

// --- 部署管理AJAXハンドラを読み込む ---
require_once __DIR__ . '/includes/ajax-department.php';

// クラスの読み込み実行
ktpwp_autoload_classes();

// === 独自更新チェッカーの初期化 ===
function ktpwp_init_update_checker() {
    if ( class_exists( 'KTPWP_Update_Checker' ) ) {
        global $ktpwp_update_checker;
        $ktpwp_update_checker = new KTPWP_Update_Checker();
        error_log( 'KantanPro: 更新チェッカーが初期化されました' );
    } else {
        error_log( 'KantanPro: 更新チェッカークラスが見つかりません' );
    }
}

// === 寄付通知クラスの初期化 ===
function ktpwp_init_donation() {
    if ( class_exists( 'KTPWP_Donation' ) ) {
        global $ktpwp_donation;
        $ktpwp_donation = KTPWP_Donation::get_instance();
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP Donation: 寄付通知クラスが初期化されました' );
        }
    } else {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP Donation: 寄付通知クラスが見つかりません' );
        }
    }
}

// プラグインが完全に読み込まれた後に実行
add_action( 'plugins_loaded', 'ktpwp_init_update_checker' );
add_action( 'plugins_loaded', 'ktpwp_init_donation' );

// プラグインアクションリンクは更新チェッカークラスで管理

// スクリプト読み込みも更新チェッカークラスで管理

// === WordPress標準自動更新機能のサポート ===
add_filter( 'auto_update_plugin', 'ktpwp_enable_auto_updates', 10, 2 );
function ktpwp_enable_auto_updates( $update, $item ) {
    // このプラグインの場合のみ自動更新を許可
    if ( isset( $item->plugin ) && $item->plugin === plugin_basename( __FILE__ ) ) {
        return true;
    }
    return $update;
}

// 自動更新が利用可能であることをWordPressに通知
add_filter( 'plugins_auto_update_enabled', '__return_true' );

// プラグインリストページで自動更新リンクを表示
add_filter( 'plugin_auto_update_setting_html', 'ktpwp_auto_update_setting_html', 10, 3 );
function ktpwp_auto_update_setting_html( $html, $plugin_file, $plugin_data ) {
    if ( $plugin_file === plugin_basename( __FILE__ ) ) {
        $auto_updates_enabled = (bool) get_site_option( 'auto_update_plugins', array() );
        $auto_update_plugins = (array) get_site_option( 'auto_update_plugins', array() );
        
        if ( in_array( $plugin_file, $auto_update_plugins, true ) ) {
            $action = 'disable';
            $text = __( '自動更新を無効化' );
            $aria_label = esc_attr( sprintf( __( '%s の自動更新を無効化' ), $plugin_data['Name'] ) );
        } else {
            $action = 'enable';
            $text = __( '自動更新を有効化' );
            $aria_label = esc_attr( sprintf( __( '%s の自動更新を有効化' ), $plugin_data['Name'] ) );
        }
        
        $url = wp_nonce_url(
            add_query_arg(
                array(
                    'action' => $action . '-auto-update',
                    'plugin' => $plugin_file,
                ),
                admin_url( 'plugins.php' )
            ),
            'updates'
        );
        
        $html = sprintf(
            '<a href="%s" class="toggle-auto-update" aria-label="%s" data-wp-toggle-auto-update="%s">%s</a>',
            esc_url( $url ),
            $aria_label,
            esc_attr( $action ),
            $text
        );
    }
    return $html;
}

// 自動更新の有効/無効を処理
add_action( 'admin_init', 'ktpwp_handle_auto_update_toggle' );
function ktpwp_handle_auto_update_toggle() {
    if ( ! current_user_can( 'update_plugins' ) ) {
        return;
    }
    
    $action = isset( $_GET['action'] ) ? $_GET['action'] : '';
    $plugin = isset( $_GET['plugin'] ) ? $_GET['plugin'] : '';
    
    if ( ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'updates' ) ) {
        return;
    }
    
    if ( $plugin === plugin_basename( __FILE__ ) ) {
        $auto_update_plugins = (array) get_site_option( 'auto_update_plugins', array() );
        
        if ( $action === 'enable-auto-update' ) {
            $auto_update_plugins[] = $plugin;
            $auto_update_plugins = array_unique( $auto_update_plugins );
        } elseif ( $action === 'disable-auto-update' ) {
            $auto_update_plugins = array_diff( $auto_update_plugins, array( $plugin ) );
        }
        
        update_site_option( 'auto_update_plugins', $auto_update_plugins );
        
        wp_redirect( admin_url( 'plugins.php' ) );
        exit;
    }
}

// === 自動マイグレーション機能 ===
function ktpwp_run_auto_migrations() {
    // 現在のDBバージョンを取得
    $current_db_version = get_option( 'ktpwp_db_version', '0.0.0' );
    $plugin_version = KANTANPRO_PLUGIN_VERSION;

    // DBバージョンが古い場合、または新規インストールの場合
    if ( version_compare( $current_db_version, $plugin_version, '<' ) ) {

        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP Auto Migration: Starting migration from ' . $current_db_version . ' to ' . $plugin_version );
        }

        // 基本テーブル作成
        ktp_table_setup();

        // 部署テーブルの作成
        ktpwp_create_department_table();

        // 部署テーブルに選択状態カラムを追加
        ktpwp_add_department_selection_column();

        // 顧客テーブルにselected_department_idカラムを追加
        ktpwp_add_client_selected_department_column();

        // マイグレーションファイルの実行
        $migrations_dir = __DIR__ . '/includes/migrations';
        if ( is_dir( $migrations_dir ) ) {
            $files = glob( $migrations_dir . '/*.php' );
            if ( $files ) {
                sort( $files );
                foreach ( $files as $file ) {
                    if ( file_exists( $file ) ) {
                        try {
                            require_once $file;
                            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                                error_log( 'KTPWP Migration: Executed ' . basename( $file ) );
                            }
                        } catch ( Exception $e ) {
                            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                                error_log( 'KTPWP Migration Error: ' . $e->getMessage() . ' in ' . basename( $file ) );
                            }
                        }
                    }
                }
            }
        }

        // 追加のテーブル構造修正
        ktpwp_fix_table_structures();

        // 既存データの修復
        ktpwp_repair_existing_data();

        // DBバージョンを更新
        update_option( 'ktpwp_db_version', $plugin_version );

        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP Auto Migration: Updated DB version from ' . $current_db_version . ' to ' . $plugin_version );
        }
    }
}

/**
 * テーブル構造の修正を実行
 */
function ktpwp_fix_table_structures() {
    global $wpdb;

    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( 'KTPWP: Starting table structure fixes' );
    }

    // 1. 請求項目テーブルの修正
    $invoice_table = $wpdb->prefix . 'ktp_order_invoice_items';
    $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$invoice_table'" );

    if ( $table_exists ) {
        $existing_columns = $wpdb->get_col( "SHOW COLUMNS FROM `{$invoice_table}`", 0 );

        // 不要なカラムを削除
        $unwanted_columns = array( 'purchase', 'ordered' );
        foreach ( $unwanted_columns as $column ) {
            if ( in_array( $column, $existing_columns ) ) {
                $wpdb->query( "ALTER TABLE `{$invoice_table}` DROP COLUMN `{$column}`" );
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( "KTPWP: Removed unwanted column '{$column}' from invoice table" );
                }
            }
        }

        // 必要なカラムを追加
        $required_columns = array(
            'sort_order' => 'INT NOT NULL DEFAULT 0',
            'updated_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
        );

        foreach ( $required_columns as $column => $definition ) {
            if ( ! in_array( $column, $existing_columns ) ) {
                $wpdb->query( "ALTER TABLE `{$invoice_table}` ADD COLUMN `{$column}` {$definition}" );
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( "KTPWP: Added column '{$column}' to invoice table" );
                }
            }
        }
    } else {
        // テーブルが存在しない場合は作成
        if ( class_exists( 'KTPWP_Order_Items' ) ) {
            $order_items = KTPWP_Order_Items::get_instance();
            $order_items->create_invoice_items_table();
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'KTPWP: Created invoice items table' );
            }
        }
    }

    // 2. スタッフチャットテーブルの修正
    $chat_table = $wpdb->prefix . 'ktp_order_staff_chat';
    $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$chat_table'" );

    if ( $table_exists ) {
        $existing_columns = $wpdb->get_col( "SHOW COLUMNS FROM `{$chat_table}`", 0 );

        // 必要なカラムを追加
        $required_columns = array(
            'is_initial' => 'TINYINT(1) NOT NULL DEFAULT 0',
        );

        foreach ( $required_columns as $column => $definition ) {
            if ( ! in_array( $column, $existing_columns ) ) {
                $wpdb->query( "ALTER TABLE `{$chat_table}` ADD COLUMN `{$column}` {$definition}" );
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( "KTPWP: Added column '{$column}' to staff chat table" );
                }
            }
        }
    } else {
        // テーブルが存在しない場合は作成
        if ( class_exists( 'KTPWP_Staff_Chat' ) ) {
            $staff_chat = KTPWP_Staff_Chat::get_instance();
            $staff_chat->create_table();
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'KTPWP: Created staff chat table' );
            }
        }
    }

    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( 'KTPWP: Table structure fixes completed' );
    }
}

/**
 * 既存データの修復を実行
 */
function ktpwp_repair_existing_data() {
    global $wpdb;

    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( 'KTPWP: Starting existing data repair' );
    }

    // 既存の受注書にスタッフチャットの初期メッセージを作成
    $chat_table = $wpdb->prefix . 'ktp_order_staff_chat';
    $order_table = $wpdb->prefix . 'ktp_order';

    if ( $wpdb->get_var( "SHOW TABLES LIKE '$chat_table'" ) && $wpdb->get_var( "SHOW TABLES LIKE '$order_table'" ) ) {
        // スタッフチャットが存在しない受注書を取得
        $orders_without_chat = $wpdb->get_results(
            "
            SELECT o.id 
            FROM `{$order_table}` o 
            LEFT JOIN `{$chat_table}` c ON o.id = c.order_id 
            WHERE c.order_id IS NULL
        "
        );

        if ( ! empty( $orders_without_chat ) ) {
            $success_count = 0;
            foreach ( $orders_without_chat as $order ) {
                // 初期メッセージを作成
                $result = $wpdb->insert(
                    $chat_table,
                    array(
                        'order_id' => $order->id,
                        'user_id' => 1, // 管理者ユーザーID
                        'user_display_name' => 'システム',
                        'message' => '受注書を作成しました。',
                        'is_initial' => 1,
                        'created_at' => current_time( 'mysql' ),
                    ),
                    array( '%d', '%d', '%s', '%s', '%d', '%s' )
                );

                if ( $result !== false ) {
                    $success_count++;
                }
            }

            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( "KTPWP: Created initial chat messages for {$success_count} orders" );
            }
        }
    }

    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( 'KTPWP: Existing data repair completed' );
    }
}

// プラグイン有効化時の自動マイグレーション
register_activation_hook( KANTANPRO_PLUGIN_FILE, 'ktpwp_plugin_activation' );

/**
 * プラグイン有効化時の処理
 */
function ktpwp_plugin_activation() {
    // データベーステーブルの作成
    ktp_table_setup();
    
    // 部署テーブルの作成
    ktpwp_create_department_table();
    
    // 部署テーブルに選択状態カラムを追加
    ktpwp_add_department_selection_column();
    
    // 顧客テーブルにselected_department_idカラムを追加
    ktpwp_add_client_selected_department_column();
    
    // 選択された部署の初期化
    ktpwp_initialize_selected_department();
    
    // 利用規約テーブルの作成
    ktpwp_ensure_terms_table();
    

    
    // プラグインのバージョン情報を保存
    update_option( 'ktpwp_version', KANTANPRO_PLUGIN_VERSION );
    
    // データベースバージョンを更新
    update_option( 'ktpwp_db_version', KANTANPRO_PLUGIN_VERSION );
    
    // フラッシュメッセージを設定
    set_transient( 'ktpwp_activation_message', 'KantanProプラグインが正常に有効化されました。', 60 );
    
    // リダイレクトフラグを設定
    add_option( 'ktpwp_activation_redirect', true );
}

/**
 * 更新履歴の初期化処理
 */


// プラグイン読み込み時の差分マイグレーション（アップデート時）
add_action( 'plugins_loaded', 'ktpwp_check_database_integrity', 5 );

// プラグイン読み込み時に部署マイグレーションの完了を確認
add_action( 'plugins_loaded', 'ktpwp_ensure_department_migration', 6 );

// 利用規約テーブル存在チェック（自動修復）
add_action( 'plugins_loaded', 'ktpwp_ensure_terms_table', 7 );



// 利用規約同意チェック
add_action( 'admin_init', 'ktpwp_check_terms_agreement' );
add_action( 'wp', 'ktpwp_check_terms_agreement' );

// プラグイン更新時の自動マイグレーション
add_action( 'upgrader_process_complete', 'ktpwp_plugin_upgrade_migration', 10, 2 );

/**
 * 部署テーブルを作成する関数
 */
function ktpwp_create_department_table() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'ktp_department';
    $charset_collate = $wpdb->get_charset_collate();

    // テーブルが既に存在するかチェック
    $table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );

    if ( $table_exists !== $table_name ) {
        $sql = "CREATE TABLE {$table_name} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            client_id mediumint(9) NOT NULL COMMENT '顧客ID',
            department_name varchar(255) NOT NULL COMMENT '部署名',
            contact_person varchar(255) NOT NULL COMMENT '担当者名',
            email varchar(100) NOT NULL COMMENT 'メールアドレス',
            is_selected TINYINT(1) NOT NULL DEFAULT 0 COMMENT '選択状態',
            created_at datetime DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日時',
            PRIMARY KEY (id),
            KEY client_id (client_id),
            KEY email (email),
            KEY is_selected (is_selected)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $result = dbDelta( $sql );

        if ( ! empty( $result ) ) {
            // マイグレーション完了フラグを設定
            update_option( 'ktp_department_table_version', '1.1.0' );

            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'KTPWP: 部署テーブルが正常に作成されました（is_selectedカラム付き）。' );
            }

            return true;
        }

        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP: 部署テーブルの作成に失敗しました。エラー: ' . $wpdb->last_error );
        }

        return false;
    }

    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( 'KTPWP: 部署テーブルは既に存在します。' );
    }

    return true;
}

/**
 * 部署テーブルに選択状態カラムを追加する関数
 */
function ktpwp_add_department_selection_column() {
    global $wpdb;

    $department_table = $wpdb->prefix . 'ktp_department';

    // 部署テーブルが存在するかチェック
    $table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $department_table ) );

    if ( $table_exists !== $department_table ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP: 部署テーブルが存在しないため、選択状態カラムの追加をスキップします。' );
        }
        return false;
    }

    // is_selectedカラムが存在するかチェック
    $column_exists = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM `{$department_table}` LIKE %s", 'is_selected' ) );

    if ( empty( $column_exists ) ) {
        // カラム追加を試行
        $result = $wpdb->query( "ALTER TABLE {$department_table} ADD COLUMN is_selected TINYINT(1) NOT NULL DEFAULT 0 COMMENT '選択状態'" );

        if ( $result !== false ) {
            // インデックスも追加
            $wpdb->query( "ALTER TABLE {$department_table} ADD INDEX is_selected (is_selected)" );

            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'KTPWP: 部署テーブルに選択状態カラムとインデックスを追加しました。' );
            }
            return true;
        } else {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'KTPWP: 部署テーブルへの選択状態カラム追加に失敗しました。エラー: ' . $wpdb->last_error );
            }
            return false;
        }
    }

    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( 'KTPWP: 部署テーブルの選択状態カラムは既に存在します。' );
    }

    return true;
}

/**
 * 顧客テーブルにselected_department_idカラムを追加する関数
 */
function ktpwp_add_client_selected_department_column() {
    global $wpdb;

    $client_table = $wpdb->prefix . 'ktp_client';

    // 顧客テーブルが存在するかチェック
    $table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $client_table ) );

    if ( $table_exists !== $client_table ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP: 顧客テーブルが存在しないため、selected_department_idカラムの追加をスキップします。' );
        }
        return false;
    }

    // selected_department_idカラムが存在するかチェック
    $column_exists = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM `{$client_table}` LIKE %s", 'selected_department_id' ) );

    if ( empty( $column_exists ) ) {
        // カラム追加を試行
        $result = $wpdb->query( "ALTER TABLE {$client_table} ADD COLUMN selected_department_id INT NULL COMMENT '選択された部署ID'" );

        if ( $result !== false ) {
            // インデックスも追加
            $wpdb->query( "ALTER TABLE {$client_table} ADD INDEX selected_department_id (selected_department_id)" );

            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'KTPWP: 顧客テーブルにselected_department_idカラムとインデックスを追加しました。' );
            }
            return true;
        } else {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'KTPWP: 顧客テーブルへのselected_department_idカラム追加に失敗しました。エラー: ' . $wpdb->last_error );
            }
            return false;
        }
    }

    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( 'KTPWP: 顧客テーブルのselected_department_idカラムは既に存在します。' );
    }

    return true;
}

/**
 * 既存顧客データの選択された部署IDを初期化する関数
 */
function ktpwp_initialize_selected_department() {
    global $wpdb;

    $client_table = $wpdb->prefix . 'ktp_client';
    $department_table = $wpdb->prefix . 'ktp_department';

    // 選択された部署IDが設定されていない顧客を取得
    $clients_without_selection = $wpdb->get_results(
        "SELECT c.id FROM `{$client_table}` c 
         LEFT JOIN `{$client_table}` c2 ON c.id = c2.id AND c2.selected_department_id IS NOT NULL 
         WHERE c2.id IS NULL"
    );

    // 自動初期化は無効化（ユーザーが明示的に選択した場合のみ部署が選択される）
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( 'KTPWP: 部署選択の自動初期化は無効化されています（' . count( $clients_without_selection ) . '件の顧客が選択なし状態）' );
    }

    return true;
}

/**
 * プラグイン更新時の自動マイグレーション処理
 */
function ktpwp_plugin_upgrade_migration( $upgrader, $hook_extra ) {
    // KantanProプラグインの更新かどうかをチェック
    if ( isset( $hook_extra['plugin'] ) && strpos( $hook_extra['plugin'], 'ktpwp.php' ) !== false ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP: Plugin upgrade detected, running migration' );
        }

        try {
            // 部署テーブルの作成
            $department_table_created = ktpwp_create_department_table();

            // 部署テーブルに選択状態カラムを追加
            $column_added = ktpwp_add_department_selection_column();

            // 顧客テーブルにselected_department_idカラムを追加
            $client_column_added = ktpwp_add_client_selected_department_column();

            // 自動マイグレーション実行
            ktpwp_run_auto_migrations();

            // マイグレーション完了フラグを設定
            update_option( 'ktpwp_department_migration_completed', '1' );

            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'KTPWP: Plugin upgrade migration completed successfully' );
                if ( $department_table_created ) {
                    error_log( 'KTPWP: Department table created/verified during upgrade' );
                }
                if ( $column_added ) {
                    error_log( 'KTPWP: Department selection column added/verified during upgrade' );
                }
                if ( $client_column_added ) {
                    error_log( 'KTPWP: Client selected_department_id column added/verified during upgrade' );
                }
            }
        } catch ( Exception $e ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'KTPWP: Plugin upgrade migration failed: ' . $e->getMessage() );
            }
        }
    }
}

/**
 * 部署マイグレーションの完了状態を確認する関数
 */
function ktpwp_check_department_migration_status() {
    $migration_completed = get_option( 'ktpwp_department_migration_completed', '0' );
    return $migration_completed === '1';
}

/**
 * 部署マイグレーションの完了を確実にする関数
 */
function ktpwp_ensure_department_migration() {
    // 既にマイグレーションが完了している場合はスキップ
    if ( ktpwp_check_department_migration_status() ) {
        return;
    }

    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( 'KTPWP: Ensuring department migration completion' );
    }

    try {
        // 部署テーブルの作成
        $department_table_created = ktpwp_create_department_table();

        // 部署テーブルに選択状態カラムを追加
        $column_added = ktpwp_add_department_selection_column();

        // 顧客テーブルにselected_department_idカラムを追加
        $client_column_added = ktpwp_add_client_selected_department_column();

        // マイグレーション完了フラグを設定
        update_option( 'ktpwp_department_migration_completed', '1' );

        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP: Department migration ensured successfully' );
            if ( $department_table_created ) {
                error_log( 'KTPWP: Department table created/verified during ensure check' );
            }
            if ( $column_added ) {
                error_log( 'KTPWP: Department selection column added/verified during ensure check' );
            }
            if ( $client_column_added ) {
                error_log( 'KTPWP: Client selected_department_id column added/verified during ensure check' );
            }
        }
    } catch ( Exception $e ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP: Department migration ensure failed: ' . $e->getMessage() );
        }
    }
}

/**
 * データベースの整合性をチェックし、必要に応じて修正を実行
 */
function ktpwp_check_database_integrity() {
    // 既にチェック済みの場合はスキップ
    if ( get_transient( 'ktpwp_db_integrity_checked' ) ) {
        return;
    }

    global $wpdb;

    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( 'KTPWP: Checking database integrity' );
    }

    $needs_fix = false;

    // 1. 請求項目テーブルのチェック
    $invoice_table = $wpdb->prefix . 'ktp_order_invoice_items';
    $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$invoice_table'" );

    if ( $table_exists ) {
        $existing_columns = $wpdb->get_col( "SHOW COLUMNS FROM `{$invoice_table}`", 0 );

        // 不要なカラムが存在するかチェック
        $unwanted_columns = array( 'purchase', 'ordered' );
        foreach ( $unwanted_columns as $column ) {
            if ( in_array( $column, $existing_columns ) ) {
                $needs_fix = true;
                break;
            }
        }

        // 必要なカラムが不足しているかチェック
        $required_columns = array( 'sort_order', 'updated_at' );
        foreach ( $required_columns as $column ) {
            if ( ! in_array( $column, $existing_columns ) ) {
                $needs_fix = true;
                break;
            }
        }
    } else {
        $needs_fix = true;
    }

    // 2. スタッフチャットテーブルのチェック
    $chat_table = $wpdb->prefix . 'ktp_order_staff_chat';
    $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$chat_table'" );

    if ( $table_exists ) {
        $existing_columns = $wpdb->get_col( "SHOW COLUMNS FROM `{$chat_table}`", 0 );

        // 必要なカラムが不足しているかチェック
        if ( ! in_array( 'is_initial', $existing_columns ) ) {
            $needs_fix = true;
        }
    } else {
        $needs_fix = true;
    }

    // 3. 部署テーブルのチェック
    $department_table = $wpdb->prefix . 'ktp_department';
    $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$department_table'" );

    if ( ! $table_exists ) {
        $needs_fix = true;
    } else {
        // 部署テーブルが存在する場合、選択状態カラムの存在をチェック
        $existing_columns = $wpdb->get_col( "SHOW COLUMNS FROM `{$department_table}`", 0 );
        if ( ! in_array( 'is_selected', $existing_columns ) ) {
            $needs_fix = true;
        }
    }

    // 4. 顧客テーブルのselected_department_idカラムチェック
    $client_table = $wpdb->prefix . 'ktp_client';
    $client_table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$client_table'" );

    if ( $client_table_exists ) {
        $client_columns = $wpdb->get_col( "SHOW COLUMNS FROM `{$client_table}`", 0 );
        if ( ! in_array( 'selected_department_id', $client_columns ) ) {
            $needs_fix = true;
        }
    }

    // 5. 既存データのチェック
    if ( $wpdb->get_var( "SHOW TABLES LIKE '$chat_table'" ) && $wpdb->get_var( "SHOW TABLES LIKE '$order_table'" ) ) {
        $orders_without_chat = $wpdb->get_var(
            "
            SELECT COUNT(*) 
            FROM `{$order_table}` o 
            LEFT JOIN `{$chat_table}` c ON o.id = c.order_id 
            WHERE c.order_id IS NULL
        "
        );

        if ( $orders_without_chat > 0 ) {
            $needs_fix = true;
        }
    }

    // 修正が必要な場合は実行
    if ( $needs_fix ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP: Database integrity issues detected, running fixes' );
        }

        try {
            // 部署テーブルの作成
            $department_table_created = ktpwp_create_department_table();

            // 部署テーブルに選択状態カラムを追加
            $column_added = ktpwp_add_department_selection_column();

            // 顧客テーブルにselected_department_idカラムを追加
            $client_column_added = ktpwp_add_client_selected_department_column();

            ktpwp_fix_table_structures();
            ktpwp_repair_existing_data();

            // マイグレーション完了フラグを設定
            update_option( 'ktpwp_department_migration_completed', '1' );

            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'KTPWP: Database integrity fixes completed successfully' );
                if ( $department_table_created ) {
                    error_log( 'KTPWP: Department table created/verified during integrity check' );
                }
                if ( $column_added ) {
                    error_log( 'KTPWP: Department selection column added/verified during integrity check' );
                }
                if ( $client_column_added ) {
                    error_log( 'KTPWP: Client selected_department_id column added/verified during integrity check' );
                }
            }
        } catch ( Exception $e ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'KTPWP: Database integrity fixes failed: ' . $e->getMessage() );
            }
        }
    }

    // チェック完了を記録（1時間有効）
    set_transient( 'ktpwp_db_integrity_checked', true, HOUR_IN_SECONDS );
}

// デバッグログ: プラグイン読み込み開始
if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
    error_log( 'KTPWP Plugin: Loading started' );
}

// 安全なログディレクトリの自動作成
function ktpwp_setup_safe_logging() {
    // wp-config.phpでWP_DEBUG_LOGが設定されている場合のみ実行
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
        $log_dir = WP_CONTENT_DIR . '/logs';

        // ログディレクトリが存在しない場合は作成
        if ( ! is_dir( $log_dir ) ) {
            wp_mkdir_p( $log_dir );

            // .htaccessファイルを作成してログディレクトリへのアクセスを制限
            $htaccess_content = "Order deny,allow\nDeny from all";
            file_put_contents( $log_dir . '/.htaccess', $htaccess_content );

            // index.phpファイルを作成してディレクトリリスティングを防止
            file_put_contents( $log_dir . '/index.php', '<?php // Silence is golden' );

            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'KTPWP: Created secure log directory at ' . $log_dir );
            }
        }

        // 既存のログディレクトリの保護を確認
        if ( is_dir( $log_dir ) ) {
            $htaccess_file = $log_dir . '/.htaccess';
            $index_file = $log_dir . '/index.php';

            // .htaccessファイルが存在しない場合は作成
            if ( ! file_exists( $htaccess_file ) ) {
                $htaccess_content = "Order deny,allow\nDeny from all";
                file_put_contents( $htaccess_file, $htaccess_content );
            }

            // index.phpファイルが存在しない場合は作成
            if ( ! file_exists( $index_file ) ) {
                file_put_contents( $index_file, '<?php // Silence is golden' );
            }
        }
    }
}

// プラグイン読み込み時にログディレクトリを設定
add_action( 'plugins_loaded', 'ktpwp_setup_safe_logging', 1 );

// プラグイン初期化時のREST API制限を一時的に無効化
function ktpwp_disable_rest_api_restriction_during_init() {
    // プラグイン初期化中はREST API制限を無効化
    remove_filter( 'rest_authentication_errors', 'ktpwp_allow_internal_requests' );

    // 初期化完了後にREST API制限を再適用
    add_action(
        'init',
        function () {
			add_filter( 'rest_authentication_errors', 'ktpwp_allow_internal_requests' );
		},
        20
    );
}

// プラグイン読み込み時にREST API制限を一時的に無効化
add_action( 'plugins_loaded', 'ktpwp_disable_rest_api_restriction_during_init', 1 );

// メインクラスの初期化はinit以降に遅延（翻訳エラー防止）
add_action(
    'init',
    function () {
		// Changed from plugins_loaded to init
		if ( class_exists( 'KTPWP_Main' ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'KTPWP Plugin: KTPWP_Main class found, initializing on init hook...' );
			}
			KTPWP_Main::get_instance();
		} elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP Plugin: KTPWP_Main class not found on init hook' );
		}
	},
    0
); // Run early on init hook

// Contact Form 7連携クラスも必ず初期化
add_action(
    'plugins_loaded',
    function () {
		// Changed from 'init' to 'plugins_loaded'
		if ( class_exists( 'KTPWP_Contact_Form' ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'KTPWP Plugin: KTPWP_Contact_Form class found, initializing...' );
			}
			KTPWP_Contact_Form::get_instance();
		} elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP Plugin: KTPWP_Contact_Form class not found' );
		}
	}
);

// プラグインリファレンス機能の初期化はinit以降に遅延（翻訳エラー防止）
add_action(
    'init',
    function () {
		if ( class_exists( 'KTPWP_Plugin_Reference' ) ) {
			KTPWP_Plugin_Reference::get_instance();
		}
	}
);

/**
 * セキュリティ強化: REST API制限 & HTTPヘッダー追加
 */

/**
 * REST API制限機能（管理画面とブロックエディターを除外）
 */
function ktpwp_restrict_rest_api( $result ) {
    if ( ! empty( $result ) ) {
        return $result;
    }

    // 管理画面では制限しない
    if ( is_admin() ) {
        return $result;
    }

    // ブロックエディター関連のリクエストは制限しない
    $request_uri = $_SERVER['REQUEST_URI'] ?? '';
    if ( strpos( $request_uri, '/wp-json/wp/v2/' ) !== false ) {
        // 投稿タイプ、タクソノミー、メディアなどの基本的なREST APIは許可
        return $result;
    }

    // サイトヘルスチェック用のエンドポイントは制限しない
    if ( strpos( $request_uri, '/wp-json/wp-site-health/' ) !== false ) {
        return $result;
    }

    // その他のREST APIはログインユーザーのみに制限
    if ( ! is_user_logged_in() ) {
        return new WP_Error(
            'rest_forbidden',
            'REST APIはログインユーザーのみ利用可能です。',
            array( 'status' => 403 )
        );
    }

    return $result;
}

// === ループバックリクエストとサイトヘルスチェックの改善 ===
function ktpwp_allow_internal_requests( $result ) {
    // 既にエラーがある場合はそのまま返す
    if ( ! empty( $result ) ) {
        return $result;
    }

    // 管理画面では制限しない
    if ( is_admin() ) {
        return $result;
    }

    // 設定でREST API制限が無効化されている場合は制限しない
    if ( class_exists( 'KTP_Settings' ) ) {
        $rest_api_restricted = KTP_Settings::get_setting( 'rest_api_restricted', '1' );
        if ( $rest_api_restricted !== '1' ) {
            return $result;
        }

        // REST API制限の完全無効化設定をチェック
        $disable_rest_api_restriction = KTP_Settings::get_setting( 'disable_rest_api_restriction', '0' );
        if ( $disable_rest_api_restriction === '1' ) {
            return $result;
        }
    }

    // 開発環境では制限を緩和
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        // ローカル開発環境では制限しない
        if ( strpos( home_url(), 'localhost' ) !== false || strpos( home_url(), '127.0.0.1' ) !== false ) {
            return $result;
        }
    }

    // WordPressの内部通信用エンドポイントは制限しない
    $request_uri = $_SERVER['REQUEST_URI'] ?? '';

    // すべてのWordPress REST APIエンドポイントを許可
    if ( strpos( $request_uri, '/wp-json/' ) !== false ) {
        return $result;
    }

    // その他のREST APIはログインユーザーのみに制限
    if ( ! is_user_logged_in() ) {
        return new WP_Error(
            'rest_forbidden',
            'REST APIはログインユーザーのみ利用可能です。',
            array( 'status' => 403 )
        );
    }

    return $result;
}

// REST API制限の改善版を適用
remove_filter( 'rest_authentication_errors', 'ktpwp_restrict_rest_api' );
// REST API制限はinitアクションで適用（プラグイン初期化完了後）
add_action(
    'init',
    function () {
		add_filter( 'rest_authentication_errors', 'ktpwp_allow_internal_requests' );
	},
    10
);

/**
 * HTTPセキュリティヘッダー追加
 */
function ktpwp_add_security_headers() {
    // 管理画面でのみ適用
    if ( is_admin() && ! wp_doing_ajax() ) {
        // クリックジャッキング防止
        if ( ! headers_sent() ) {
            header( 'X-Frame-Options: SAMEORIGIN' );
            // XSS対策
            header( 'X-Content-Type-Options: nosniff' );
            // Referrer情報制御
            header( 'Referrer-Policy: no-referrer-when-downgrade' );
        }
    }
}
add_action( 'admin_init', 'ktpwp_add_security_headers' );

register_activation_hook( KANTANPRO_PLUGIN_FILE, array( 'KTP_Settings', 'activate' ) );



// リダイレクト処理クラス
class KTPWP_Redirect {

    public function __construct() {
        add_action( 'template_redirect', array( $this, 'handle_redirect' ) );
        add_filter( 'post_link', array( $this, 'custom_post_link' ), 10, 2 );
        add_filter( 'page_link', array( $this, 'custom_page_link' ), 10, 2 );
    }

    public function handle_redirect() {
        if ( isset( $_GET['tab_name'] ) || $this->has_ktpwp_shortcode() ) {
            return;
        }

        if ( is_single() || is_page() ) {
            $post = get_queried_object();

            if ( $post && $this->should_redirect( $post ) ) {
                $external_url = $this->get_external_url( $post );
                if ( $external_url ) {
                    // 外部リダイレクト先の安全性を検証（ホワイトリスト方式）
                    $allowed_hosts = array(
                        'ktpwp.com',
                        parse_url( home_url(), PHP_URL_HOST ),
                    );
                    $parsed = wp_parse_url( $external_url );
                    $host = isset( $parsed['host'] ) ? $parsed['host'] : '';
                    if ( in_array( $host, $allowed_hosts, true ) ) {
                        $clean_external_url = $parsed['scheme'] . '://' . $host . ( isset( $parsed['path'] ) ? $parsed['path'] : '' );
                        wp_redirect( $clean_external_url, 301 );
                        exit;
                    }
                }
            }
        }
    }

    /**
     * 現在のページにKTPWPショートコードが含まれているかチェック
     */
    private function has_ktpwp_shortcode() {
        $post = get_queried_object();
        if ( ! $post || ! isset( $post->post_content ) ) {
            return false;
        }

        return (
            has_shortcode( $post->post_content, 'kantanAllTab' ) ||
            has_shortcode( $post->post_content, 'ktpwp_all_tab' )
        );
    }

    /**
     * リダイレクト対象かどうかを判定
     */
    private function should_redirect( $post ) {
        if ( ! $post ) {
            return false;
        }

        // ショートコードが含まれるページの場合はリダイレクトしない
        if ( $this->has_ktpwp_shortcode() ) {
            return false;
        }

        // KTPWPのクエリパラメータがある場合はリダイレクトしない
        if ( isset( $_GET['tab_name'] ) || isset( $_GET['from_client'] ) || isset( $_GET['order_id'] ) ) {
            return false;
        }

        // external_urlが設定されている投稿のみリダイレクト対象とする
        $external_url = get_post_meta( $post->ID, 'external_url', true );
        if ( ! empty( $external_url ) ) {
            return true;
        }

        // カスタム投稿タイプ「blog」で、特定の条件を満たす場合のみ
        if ( $post->post_type === 'blog' ) {
            // 特定のスラッグやタイトルの場合のみリダイレクト
            $redirect_slugs = array( 'redirect-to-ktpwp', 'external-link' );
            return in_array( $post->post_name, $redirect_slugs );
        }

        return false;
    }

    /**
     * 外部URLを取得（クエリパラメータなし）
     */
    private function get_external_url( $post ) {
        if ( ! $post ) {
            return false;
        }

        $external_url = get_post_meta( $post->ID, 'external_url', true );

        if ( empty( $external_url ) ) {
            // デフォルトのベースURL
            $base_url = 'https://ktpwp.com/blog/';

            if ( $post->post_type === 'blog' ) {
                $external_url = $base_url;
            } elseif ( $post->post_type === 'post' ) {
                $categories = wp_get_post_categories( $post->ID, array( 'fields' => 'slugs' ) );

                if ( in_array( 'blog', $categories ) ) {
                    $external_url = $base_url;
                } elseif ( in_array( 'news', $categories ) ) {
                    $external_url = $base_url . 'news/';
                } elseif ( in_array( 'column', $categories ) ) {
                    $external_url = $base_url . 'column/';
                }
            }
        }

        // URLからクエリパラメータを除去
        if ( $external_url ) {
            $external_url = strtok( $external_url, '?' );
        }

        return $external_url;
    }

    public function custom_post_link( $permalink, $post ) {
        if ( $post->post_type === 'blog' ) {
            $external_url = $this->get_external_url( $post );
            if ( $external_url ) {
                return $external_url;
            }
        }

        if ( $post->post_type === 'post' ) {
            $categories = wp_get_post_categories( $post->ID, array( 'fields' => 'slugs' ) );
            $redirect_categories = array( 'blog', 'news', 'column' );

            if ( ! empty( array_intersect( $categories, $redirect_categories ) ) ) {
                $external_url = $this->get_external_url( $post );
                if ( $external_url ) {
                    return $external_url;
                }
            }
        }

        return $permalink;
    }

    public function custom_page_link( $permalink, $post_id ) {
        $post = get_post( $post_id );

        if ( $post && $this->should_redirect( $post ) ) {
            $external_url = $this->get_external_url( $post );
            if ( $external_url ) {
                return $external_url;
            }
        }

        return $permalink;
    }
}

// POSTパラメータをGETパラメータに変換する処理
function ktpwp_handle_form_redirect() {
    // POSTデータハンドラーを使用した安全な処理
    if ( ! KTPWP_Post_Data_Handler::has_post_keys( array( 'tab_name', 'from_client' ) ) ) {
        return;
    }

    $post_data = KTPWP_Post_Data_Handler::get_multiple_post_data(
        array(
			'tab_name' => 'text',
			'from_client' => 'text',
        )
    );

    // orderタブのチェック
    if ( $post_data['tab_name'] !== 'order' ) {
        return;
    }

    // リダイレクトパラメータの構築
    $redirect_params = array(
        'tab_name' => $post_data['tab_name'],
        'from_client' => $post_data['from_client'],
    );

    // オプションパラメータの追加
    $optional_params = KTPWP_Post_Data_Handler::get_multiple_post_data(
        array(
			'customer_name' => 'text',
			'user_name' => 'text',
			'client_id' => array(
				'type' => 'int',
				'default' => 0,
			),
        )
    );

    foreach ( $optional_params as $key => $value ) {
        if ( ! empty( $value ) && ( $key !== 'client_id' || $value > 0 ) ) {
            $redirect_params[ $key ] = $value;
        }
    }

    // 現在のURLからKTPWPパラメータを除去してクリーンなベースURLを作成
    $current_url = add_query_arg( null, null );
    $clean_url = remove_query_arg(
        array(
			'tab_name',
			'from_client',
			'customer_name',
			'user_name',
			'client_id',
			'order_id',
			'delete_order',
			'data_id',
			'view_mode',
			'query_post',
        ),
        $current_url
    );

    // 新しいパラメータを追加してリダイレクト
    $redirect_url = add_query_arg( $redirect_params, $clean_url );

    wp_redirect( $redirect_url, 302 );
    exit;
}

add_action( 'wp_loaded', 'ktpwp_handle_form_redirect', 1 );


// ファイルをインクルード
// アクティベーションフックのために class-ktp-settings.php は常にインクルード
if ( file_exists( MY_PLUGIN_PATH . 'includes/class-ktp-settings.php' ) ) {
    include_once MY_PLUGIN_PATH . 'includes/class-ktp-settings.php';
} else {
    add_action(
        'admin_notices',
        function () {
			echo '<div class="notice notice-error"><p>' . __( 'KTPWP Critical Error: includes/class-ktp-settings.php not found.', 'ktpwp' ) . '</p></div>';
		}
    );
}

add_action( 'plugins_loaded', 'KTPWP_Index' );

function ktpwp_scripts_and_styles() {
    wp_enqueue_script( 'ktp-js', plugins_url( 'js/ktp-js.js', __FILE__ ) . '?v=' . time(), array( 'jquery' ), null, true );

    // デバッグモードの設定（WP_DEBUGまたは開発環境でのみ有効）
    $debug_mode = ( defined( 'WP_DEBUG' ) && WP_DEBUG ) || ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG );
    wp_add_inline_script( 'ktp-js', 'var ktpwpDebugMode = ' . json_encode( $debug_mode ) . ';' );

    // コスト項目トグル用の国際化ラベルをJSに渡す
    wp_add_inline_script( 'ktp-js', 'var ktpwpCostShowLabel = ' . json_encode( '表示' ) . ';' );
    wp_add_inline_script( 'ktp-js', 'var ktpwpCostHideLabel = ' . json_encode( '非表示' ) . ';' );
    wp_add_inline_script( 'ktp-js', 'var ktpwpStaffChatShowLabel = ' . json_encode( '表示' ) . ';' );
    wp_add_inline_script( 'ktp-js', 'var ktpwpStaffChatHideLabel = ' . json_encode( '非表示' ) . ';' );

    // サイトヘルスページでのスタイル競合を防ぐため、条件分岐を追加
    $is_site_health_page = false;

    // 管理画面でのみチェック
    if ( is_admin() ) {
        // より確実なサイトヘルスページ検出
        $current_screen = get_current_screen();
        $current_page = isset( $_GET['page'] ) ? $_GET['page'] : '';
        $current_action = isset( $_GET['action'] ) ? $_GET['action'] : '';

        $is_site_health_page = (
            ( $current_screen && (
                $current_screen->id === 'tools_page_site-health' ||
                $current_screen->id === 'site-health_page_site-health' ||
                strpos( $current_screen->id, 'site-health' ) !== false
            ) ) ||
            $current_page === 'site-health' ||
            $current_action === 'site-health' ||
            ( isset( $_SERVER['REQUEST_URI'] ) && strpos( $_SERVER['REQUEST_URI'], 'site-health' ) !== false )
        );

        // デバッグ用（必要に応じてコメントアウト）
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log(
                'KTPWP Site Health Check: ' . ( $is_site_health_page ? 'true' : 'false' ) .
                     ' | Screen: ' . ( $current_screen ? $current_screen->id : 'none' ) .
                     ' | Page: ' . $current_page .
                ' | URI: ' . ( $_SERVER['REQUEST_URI'] ?? 'none' )
            );
        }
    }

    // サイトヘルスページでの処理
    if ( $is_site_health_page ) {
        // サイトヘルスページでは専用のリセットCSSのみ読み込み
        wp_enqueue_style( 'ktpwp-site-health-reset', plugins_url( 'css/site-health-reset.css', __FILE__ ) . '?v=' . time(), array(), KANTANPRO_PLUGIN_VERSION, 'all' );
    } else {
        // サイトヘルスページ以外では通常のスタイルを読み込み
        wp_register_style( 'ktp-css', plugins_url( 'css/styles.css', __FILE__ ) . '?v=' . time(), array(), KANTANPRO_PLUGIN_VERSION, 'all' );
        wp_enqueue_style( 'ktp-css' );
        // 進捗プルダウン用のスタイルシートを追加
        wp_enqueue_style( 'ktp-progress-select', plugins_url( 'css/progress-select.css', __FILE__ ) . '?v=' . time(), array( 'ktp-css' ), KANTANPRO_PLUGIN_VERSION, 'all' );
        // 設定タブ用のスタイルシートを追加
        wp_enqueue_style( 'ktp-setting-tab', plugins_url( 'css/ktp-setting-tab.css', __FILE__ ) . '?v=' . time(), array( 'ktp-css' ), KANTANPRO_PLUGIN_VERSION, 'all' );
    }

    // Material Symbols アイコンフォントをプリロードとして読み込み
    wp_enqueue_style( 'ktpwp-material-icons', 'https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0', array(), null );

    // Google Fontsのプリロード設定
    add_action(
        'wp_head',
        function () {
			echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
			echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
			echo '<link rel="preload" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" as="style" onload="this.onload=null;this.rel=\'stylesheet\'">' . "\n";
		},
        1
    );
    wp_enqueue_script( 'jquery', 'https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js', array(), '3.5.1', true );
    wp_enqueue_script( 'ktp-order-inline-projectname', plugins_url( 'js/ktp-order-inline-projectname.js', __FILE__ ), array( 'jquery' ), KANTANPRO_PLUGIN_VERSION, true );
    // Nonceをjsに渡す（案件名インライン編集用）
    if ( current_user_can( 'manage_options' ) || current_user_can( 'ktpwp_access' ) ) {
        wp_add_inline_script(
            'ktp-order-inline-projectname',
            'var ktpwp_inline_edit_nonce = ' . json_encode(
                array(
					'nonce' => wp_create_nonce( 'ktp_update_project_name' ),
                )
            ) . ';'
        );
    }

    // ajaxurl をフロントエンドに渡す
    wp_add_inline_script( 'ktp-js', 'var ktp_ajax_object = ' . json_encode( array( 'ajax_url' => admin_url( 'admin-ajax.php' ) ) ) . ';' );

    // Ajax nonceを追加
    wp_add_inline_script( 'ktp-invoice-items', 'var ktp_ajax_nonce = ' . json_encode( wp_create_nonce( 'ktp_ajax_nonce' ) ) . ';' );
    wp_add_inline_script( 'ktp-cost-items', 'var ktp_ajax_nonce = ' . json_encode( wp_create_nonce( 'ktp_ajax_nonce' ) ) . ';' );

    // ajaxurlをJavaScriptで利用可能にする
    wp_add_inline_script( 'ktp-invoice-items', 'var ajaxurl = ' . json_encode( admin_url( 'admin-ajax.php' ) ) . ';' );
    wp_add_inline_script( 'ktp-cost-items', 'var ajaxurl = ' . json_encode( admin_url( 'admin-ajax.php' ) ) . ';' );

    // リファレンス機能のスクリプトを読み込み（ログイン済みユーザーのみ）
    if ( is_user_logged_in() ) {
        wp_enqueue_script(
            'ktpwp-reference',
            plugins_url( 'js/plugin-reference.js', __FILE__ ),
            array( 'jquery' ),
            KANTANPRO_PLUGIN_VERSION,
            true
        );

        wp_add_inline_script(
            'ktpwp-reference',
            'var ktpwp_reference = ' . json_encode(
                array(
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'nonce'    => wp_create_nonce( 'ktpwp_reference_nonce' ),
					'strings'  => array(
						'modal_title'         => esc_html__( 'プラグインリファレンス', 'ktpwp' ),
						'loading'             => esc_html__( '読み込み中...', 'ktpwp' ),
						'error_loading'       => esc_html__( 'コンテンツの読み込みに失敗しました。', 'ktpwp' ),
						'close'               => esc_html__( '閉じる', 'ktpwp' ),
						'nav_overview'        => esc_html__( '概要', 'ktpwp' ),
						'nav_tabs'            => esc_html__( 'タブ機能', 'ktpwp' ),
						'nav_shortcodes'      => esc_html__( 'ショートコード', 'ktpwp' ),
						'nav_settings'        => esc_html__( '設定', 'ktpwp' ),
						'nav_security'        => esc_html__( 'セキュリティ', 'ktpwp' ),
						'nav_troubleshooting' => esc_html__( 'トラブルシューティング', 'ktpwp' ),
					),
                )
            ) . ';'
        );
    }
}
// サイトヘルスページ専用のCSS読み込み
function ktpwp_site_health_styles() {
    // サイトヘルスページ専用のリセットCSSを読み込み
    wp_enqueue_style( 'ktpwp-site-health-reset', plugins_url( 'css/site-health-reset.css', __FILE__ ) . '?v=' . time(), array(), KANTANPRO_PLUGIN_VERSION, 'all' );
}

// サイトヘルスページでのみ実行
add_action(
    'admin_enqueue_scripts',
    function ( $hook ) {
		// より確実なサイトヘルスページ検出
		$is_site_health = (
        strpos( $hook, 'site-health' ) !== false ||
        ( isset( $_GET['page'] ) && $_GET['page'] === 'site-health' ) ||
        ( isset( $_GET['action'] ) && $_GET['action'] === 'site-health' ) ||
        ( isset( $_SERVER['REQUEST_URI'] ) && strpos( $_SERVER['REQUEST_URI'], 'site-health' ) !== false ) ||
        ( isset( $_SERVER['REQUEST_URI'] ) && strpos( $_SERVER['REQUEST_URI'], 'tools.php?page=site-health' ) !== false )
		);

		if ( $is_site_health ) {
			ktpwp_site_health_styles();

			// デバッグ用（必要に応じてコメントアウト）
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'KTPWP Site Health Reset CSS loaded for hook: ' . $hook );
			}
		}
	}
);

add_action( 'wp_enqueue_scripts', 'ktpwp_scripts_and_styles' );
add_action( 'admin_enqueue_scripts', 'ktpwp_scripts_and_styles' );

/**
 * Ajax ハンドラーを初期化（旧システム用）
 */
function ktpwp_init_ajax_handlers() {
}
add_action( 'init', 'ktpwp_init_ajax_handlers' );

function ktp_table_setup() {
    if ( class_exists( 'Kntan_Client_Class' ) ) {
        $client = new Kntan_Client_Class();
        $client->Create_Table( 'client' );
        // $client->Update_Table('client');
    }        if ( class_exists( 'Kntan_Service_Class' ) ) {
            $service = new Kntan_Service_Class();
            $service->Create_Table( 'service' );
	}        if ( class_exists( 'Kantan_Supplier_Class' ) ) {
		$supplier = new Kantan_Supplier_Class();
		$supplier->Create_Table( 'supplier' );
	}

    // 新しい受注書テーブル作成処理
    if ( class_exists( 'KTPWP_Order' ) ) {
        $order_manager = KTPWP_Order::get_instance();
        $order_manager->create_order_table();
    }

    // 受注明細・原価明細テーブル作成処理
    if ( class_exists( 'KTPWP_Order_Items' ) ) {
        $order_items = KTPWP_Order_Items::get_instance();
        $order_items->create_invoice_items_table();
        $order_items->create_cost_items_table();
    }

    // スタッフチャットテーブル作成処理（重要：確実に実行）
    if ( class_exists( 'KTPWP_Staff_Chat' ) ) {
        $staff_chat = KTPWP_Staff_Chat::get_instance();
        $result = $staff_chat->create_table();
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP: Staff chat table creation result: ' . ( $result ? 'success' : 'failed' ) );
        }
    } else {
        error_log( 'KTPWP: KTPWP_Staff_Chat class not found during table setup' );
    }
}
register_activation_hook( KANTANPRO_PLUGIN_FILE, 'ktp_table_setup' ); // テーブル作成処理
register_activation_hook( KANTANPRO_PLUGIN_FILE, array( 'KTP_Settings', 'activate' ) ); // 設定クラスのアクティベート処理
register_activation_hook( KANTANPRO_PLUGIN_FILE, array( 'KTPWP_Plugin_Reference', 'on_plugin_activation' ) ); // プラグインリファレンス更新処理

// プラグインアップデート時の処理
add_action(
    'upgrader_process_complete',
    function ( $upgrader_object, $options ) {
		if ( $options['action'] == 'update' && $options['type'] == 'plugin' ) {
			if ( isset( $options['plugins'] ) ) {
				foreach ( $options['plugins'] as $plugin ) {
					if ( $plugin == plugin_basename( KANTANPRO_PLUGIN_FILE ) ) {
						// プラグインが更新された場合、リファレンスキャッシュをクリア
						if ( class_exists( 'KTPWP_Plugin_Reference' ) ) {
							KTPWP_Plugin_Reference::clear_all_cache();
						}
						break;
					}
				}
			}
		}
	},
    10,
    2
);

function check_activation_key() {
    $activation_key = get_site_option( 'ktp_activation_key' );
    return empty( $activation_key ) ? '' : '';
}

function add_htmx_to_head() {
}
add_action( 'wp_head', 'add_htmx_to_head' );

function KTPWP_Index() {

    // すべてのタブのショートコード[kantanAllTab]
    function kantanAllTab() {

        // 利用規約同意チェック
        ktpwp_check_terms_on_shortcode();

        // 利用規約管理クラスが存在しない場合はエラー表示
        if ( ! class_exists( 'KTPWP_Terms_Of_Service' ) ) {
            return '<div class="notice notice-error"><p>利用規約管理機能が利用できません。</p></div>';
        }

        $terms_service = KTPWP_Terms_Of_Service::get_instance();
        // 利用規約に同意していない場合は、同意ダイアログが表示されるが、プラグインの機能は通常通り表示

        // ログイン中のユーザーは全員ヘッダーを表示（権限による制限を緩和）
        if ( is_user_logged_in() ) {
            // XSS対策: 画面に出力する変数は必ずエスケープ

            // ユーザーのログインログアウト状況を取得するためのAjaxを登録
            add_action( 'wp_ajax_get_logged_in_users', 'get_logged_in_users' );
            add_action( 'wp_ajax_nopriv_get_logged_in_users', 'get_logged_in_users' );

            // get_logged_in_users の再宣言防止
            if ( ! function_exists( 'get_logged_in_users' ) ) {
                function get_logged_in_users() {
                    // スタッフ権限チェック
                    if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'ktpwp_access' ) ) {
                        wp_send_json_error( __( 'この操作を行う権限がありません。', 'ktpwp' ) );
                        return;
                    }

                    // アクティブなセッションを持つユーザーを取得
                    $users_with_sessions = get_users(
                        array(
							'meta_key' => 'session_tokens',
							'meta_compare' => 'EXISTS',
							'fields' => 'all',
                        )
                    );

                    $logged_in_staff = array();
                    foreach ( $users_with_sessions as $user ) {
                        // セッションが有効かチェック
                        $sessions = get_user_meta( $user->ID, 'session_tokens', true );
                        if ( empty( $sessions ) ) {
                            continue;
                        }

                        $has_valid_session = false;
                        foreach ( $sessions as $session ) {
                            if ( isset( $session['expiration'] ) && $session['expiration'] > time() ) {
                                $has_valid_session = true;
                                break;
                            }
                        }

                        if ( ! $has_valid_session ) {
                            continue;
                        }

                        // スタッフ権限をチェック（ktpwp_access または管理者権限）
                        if ( in_array( 'administrator', $user->roles ) || user_can( $user->ID, 'ktpwp_access' ) ) {
                            $nickname = get_user_meta( $user->ID, 'nickname', true );
                            if ( empty( $nickname ) ) {
                                $nickname = $user->display_name ? $user->display_name : $user->user_login;
                            }
                            $logged_in_staff[] = array(
                                'id' => $user->ID,
                                'name' => esc_html( $nickname ) . 'さん',
                                'is_current' => ( get_current_user_id() === $user->ID ),
                                'avatar_url' => get_avatar_url( $user->ID, array( 'size' => 32 ) ),
                            );
                        }
                    }

                    wp_send_json( $logged_in_staff );
                }
            }

            // 現在メインのログインユーザー情報を取得
            global $current_user;

            // ログアウトのリンク
            $logout_link = esc_url( wp_logout_url() );

            // ヘッダー表示ログインユーザー名など
            $act_key = esc_html( check_activation_key() );

            // ログイン中のユーザー情報を取得（ログインしている場合のみ）
            $logged_in_users_html = '';

            // ショートコードクラスのインスタンスからスタッフアバター表示を取得
            if ( is_user_logged_in() ) {
                $shortcodes_instance = KTPWP_Shortcodes::get_instance();
                $logged_in_users_html = $shortcodes_instance->get_staff_avatars_display();
            }

            // 画像タグをPHP変数で作成（ベースラインを10px上げる）
            $icon_img = '<img src="' . esc_url( plugins_url( 'images/default/icon.png', __FILE__ ) ) . '" style="height:40px;vertical-align:middle;margin-right:8px;position:relative;top:-5px;">';

            // バージョン番号を定数から取得
            $plugin_version = defined( 'MY_PLUGIN_VERSION' ) ? esc_html( MY_PLUGIN_VERSION ) : '';

            // プラグイン名とバージョンを定数から取得
            $plugin_name = esc_html( KANTANPRO_PLUGIN_NAME );
            $plugin_version = esc_html( KANTANPRO_PLUGIN_VERSION );
            $current_page_id = get_queried_object_id();
            $update_link_url = esc_url( get_permalink( $current_page_id ) );

            // ログインしているユーザーのみにナビゲーションリンクを表示
            $navigation_links = '';
            if ( is_user_logged_in() && $current_user && $current_user->ID > 0 ) {
                // セッションの有効性も確認
                $user_sessions = WP_Session_Tokens::get_instance( $current_user->ID );
                if ( $user_sessions && ! empty( $user_sessions->get_all() ) ) {
                    $navigation_links .= ' <a href="' . $logout_link . '" title="ログアウト" style="display: inline-flex; align-items: center; gap: 4px; color: #0073aa; text-decoration: none;"><span class="material-symbols-outlined" style="font-size: 20px; vertical-align: middle;">logout</span></a>';
                    // 更新リンクは編集者権限がある場合のみ
                    if ( current_user_can( 'edit_posts' ) ) {
                        $navigation_links .= ' <a href="' . $update_link_url . '" title="更新" style="display: inline-flex; align-items: center; gap: 4px; color: #0073aa; text-decoration: none;"><span class="material-symbols-outlined" style="font-size: 20px; vertical-align: middle;">refresh</span></a>';
                        $navigation_links .= ' ' . $act_key;
                    }
                    // リファレンスボタンはログインユーザー全員に表示
                    $reference_instance = KTPWP_Plugin_Reference::get_instance();
                    $navigation_links .= $reference_instance->get_reference_link();
                }
            }

            // 設定からシステム名とシステムの説明を取得
            $system_name = get_option( 'ktp_system_name', 'ChaChatWorks' );
            $system_description = get_option( 'ktp_system_description', 'チャチャと仕事が片付く神システム！' );

            // ロゴマークを取得（デフォルトは既存のicon.png）
            $default_logo = plugins_url( 'images/default/icon.png', __FILE__ );
            $logo_url = get_option( 'ktp_logo_image', $default_logo );

            $front_message = '<div class="ktp_header">'
                . '<div class="parent">'
                . '<div class="logo-and-system-info">'
                . '<img src="' . esc_url( $logo_url ) . '" alt="' . esc_attr( $system_name ) . '" class="header-logo" style="height:40px;vertical-align:middle;margin-right:12px;position:relative;top:-2px;">'
                . '<div class="system-info">'
                . '<div class="system-name">' . esc_html( $system_name ) . '</div>'
                . '<div class="system-description">' . esc_html( $system_description ) . '</div>'
                . '</div>'
                . '</div>'
                . '</div>'
                . '<div class="header-right-section">'
                . '<div class="navigation-links">' . $navigation_links . '</div>'
                . '<div class="user-avatars-section">' . $logged_in_users_html . '</div>'
                . '</div>'
                . '</div>';
            $tab_name = isset( $_GET['tab_name'] ) ? $_GET['tab_name'] : 'default_tab'; // URLパラメータからtab_nameを取得

            // $order_content など未定義変数の初期化
            $order_content    = isset( $order_content ) ? $order_content : '';
            $client_content   = isset( $client_content ) ? $client_content : '';
            $service_content  = isset( $service_content ) ? $service_content : '';
            $supplier_content = isset( $supplier_content ) ? $supplier_content : '';
            $report_content   = isset( $report_content ) ? $report_content : '';

            if ( ! isset( $list_content ) ) {
                $list_content = '';
            }

            // デバッグ：タブ処理開始

            switch ( $tab_name ) {
                case 'list':
                    $list = new Kantan_List_Class();
                    $list_content = $list->List_Tab_View( $tab_name );
                    break;
                case 'order':
                    $order = new Kntan_Order_Class();
                    $order_content = $order->Order_Tab_View( $tab_name );
                    $order_content = $order_content ?? '';
                    break;
                case 'client':
                    $client = new Kntan_Client_Class();
                    if ( current_user_can( 'edit_posts' ) ) {
                        $client->Create_Table( $tab_name );
                        // POSTリクエストがある場合のみUpdate_Tableを呼び出す
                        if ( ! empty( $_POST ) ) {
                            $client->Update_Table( $tab_name );
                        }
                    }
                    $client_content = $client->View_Table( $tab_name );
                    break;
                case 'service':
                    $service = new Kntan_Service_Class();
                    if ( current_user_can( 'edit_posts' ) ) {
                        $service->Create_Table( $tab_name );
                        $service->Update_Table( $tab_name );
                    }
                    $service_content = $service->View_Table( $tab_name );
                    break;
                case 'supplier':
                    $supplier = new KTPWP_Supplier_Class();
                    if ( current_user_can( 'edit_posts' ) ) {
                        $supplier->Create_Table( $tab_name );

                        if ( ! empty( $_POST ) ) {
                            $supplier->Update_Table( $tab_name );
                        }
                    }
                    $supplier_content = $supplier->View_Table( $tab_name );
                    break;
                case 'report':
                    $report = new KTPWP_Report_Class();
                    $report_content = $report->Report_Tab_View( $tab_name );
                    break;
                default:
                    // デフォルトの処理
                    $list = new Kantan_List_Class();
                    $tab_name = 'list';
                    $list_content = $list->List_Tab_View( $tab_name );
                    break;
            }
            // view
            $view = new view_tabs_Class();
            $tab_view = $view->TabsView( $list_content, $order_content, $client_content, $service_content, $supplier_content, $report_content );
            $return_value = $front_message . $tab_view;
            return $return_value;

        } else {
            // ログインしていない場合、または権限がない場合
            if ( ! is_user_logged_in() ) {
                $login_error = new Kantan_Login_Error();
                $error = $login_error->Error_View();
                return $error;
            } else {
                // ログインしているが権限がない場合
                return '<div class="ktpwp-error">このコンテンツを表示する権限がありません。</div>';
            }
        }
    }
    add_shortcode( 'kantanAllTab', 'kantanAllTab' );
    // ktpwp_all_tab ショートコードを追加（同じ機能を別名で提供）
    add_shortcode( 'ktpwp_all_tab', 'kantanAllTab' );
}

// add_submenu_page の第7引数修正
// 例: add_submenu_page( $parent_slug, $page_title, $menu_title, $capability, $menu_slug, $function );
// 直接呼び出しを削除し、admin_menuフックで登録
add_action(
    'admin_menu',
    function () {
		add_submenu_page(
            'parent_slug',
            __( 'ページタイトル', 'ktpwp' ),
            __( 'メニュータイトル', 'ktpwp' ),
            'manage_options',
            'menu_slug',
            'function_name'
            // 第7引数（メニュー位置）は不要なら省略
		);
	}
);

// プラグインリファレンス更新処理（バージョン1.0.9対応）
add_action(
    'init',
    function () {
		// バージョン不一致を検出した場合のキャッシュクリア
		$stored_version = get_option( 'ktpwp_reference_version', '' );
		if ( $stored_version !== KANTANPRO_PLUGIN_VERSION ) {
			if ( class_exists( 'KTPWP_Plugin_Reference' ) ) {
				KTPWP_Plugin_Reference::clear_all_cache();

				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( "KTPWP: バージョン更新を検出しました。{$stored_version} → " . KANTANPRO_PLUGIN_VERSION );
				}
			}
		}
	},
    5
);

// 案件名インライン編集用Ajaxハンドラ

// 案件名インライン編集用Ajaxハンドラ（管理者のみ許可＆nonce検証）
add_action(
    'wp_ajax_ktp_update_project_name',
    function () {
		// 権限チェック
		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'ktpwp_access' ) ) {
			wp_send_json_error( __( '権限がありません', 'ktpwp' ) );
		}

		// POSTデータの安全な取得
		if ( ! KTPWP_Post_Data_Handler::has_post_keys( array( '_wpnonce', 'order_id', 'project_name' ) ) ) {
			wp_send_json_error( __( '必要なデータが不足しています', 'ktpwp' ) );
		}

		$post_data = KTPWP_Post_Data_Handler::get_multiple_post_data(
            array(
				'_wpnonce' => 'text',
				'order_id' => array(
					'type' => 'int',
					'default' => 0,
				),
				'project_name' => 'text',
            )
        );

		// nonceチェック
		if ( ! wp_verify_nonce( $post_data['_wpnonce'], 'ktp_update_project_name' ) ) {
			wp_send_json_error( __( 'セキュリティ検証に失敗しました', 'ktpwp' ) );
		}

		global $wpdb;
		$order_id = $post_data['order_id'];
		// wp_strip_all_tags()でタグのみ削除（HTMLエンティティは保持）
		$project_name = wp_strip_all_tags( $post_data['project_name'] );
		if ( $order_id > 0 ) {
			$table = $wpdb->prefix . 'ktp_order';
			$result = $wpdb->update(
                $table,
                array( 'project_name' => $project_name ),
                array( 'id' => $order_id ),
                array( '%s' ),
                array( '%d' )
			);
			if ( $result === false && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( "KTPWP: Failed SQL: UPDATE `$table` SET `project_name` = '$project_name' WHERE `id` = $order_id | Error: " . $wpdb->last_error );
			}
			wp_send_json_success();
		} else {
			wp_send_json_error( __( 'Invalid order_id', 'ktpwp' ) );
		}
	}
);

// 非ログイン時はAjaxで案件名編集不可（セキュリティのため）
add_action(
    'wp_ajax_nopriv_ktp_update_project_name',
    function () {
		wp_send_json_error( __( 'ログインが必要です', 'ktpwp' ) );
	}
);




// includes/class-tab-list.php, class-view-tab.php を明示的に読み込む（自動読み込みされていない場合のみ）
if ( ! class_exists( 'Kantan_List_Class' ) ) {
    include_once MY_PLUGIN_PATH . 'includes/class-tab-list.php';
}
if ( ! class_exists( 'view_tabs_Class' ) ) {
    include_once MY_PLUGIN_PATH . 'includes/class-view-tab.php';
}
if ( ! class_exists( 'Kantan_Login_Error' ) ) {
    include_once MY_PLUGIN_PATH . 'includes/class-login-error.php';
}
if ( ! class_exists( 'Kntan_Report_Class' ) ) {
    include_once MY_PLUGIN_PATH . 'includes/class-tab-report.php';
}

/**
 * メール添付ファイル用一時ファイルクリーンアップ機能
 */

// プラグイン有効化時にクリーンアップスケジュールを設定
register_activation_hook( __FILE__, 'ktpwp_schedule_temp_file_cleanup' );

// プラグイン無効化時にクリーンアップスケジュールを削除
register_deactivation_hook( __FILE__, 'ktpwp_unschedule_temp_file_cleanup' );

/**
 * 一時ファイルクリーンアップのスケジュール設定
 */
function ktpwp_schedule_temp_file_cleanup() {
    if ( ! wp_next_scheduled( 'ktpwp_cleanup_temp_files' ) ) {
        wp_schedule_event( time(), 'hourly', 'ktpwp_cleanup_temp_files' );
    }
}

/**
 * 一時ファイルクリーンアップのスケジュール削除
 */
function ktpwp_unschedule_temp_file_cleanup() {
    $timestamp = wp_next_scheduled( 'ktpwp_cleanup_temp_files' );
    if ( $timestamp ) {
        wp_unschedule_event( $timestamp, 'ktpwp_cleanup_temp_files' );
    }
}

/**
 * 一時ファイルクリーンアップ処理
 */
add_action(
    'ktpwp_cleanup_temp_files',
    function () {
		$upload_dir = wp_upload_dir();
		$temp_dir = $upload_dir['basedir'] . '/ktp-email-temp/';

		if ( ! file_exists( $temp_dir ) ) {
			return;
		}

		$current_time = time();
		$cleanup_age = 3600; // 1時間以上古いファイルを削除

		$files = glob( $temp_dir . '*' );
		if ( $files ) {
			foreach ( $files as $file ) {
				if ( is_file( $file ) ) {
					$file_age = $current_time - filemtime( $file );
					if ( $file_age > $cleanup_age ) {
						unlink( $file );
						if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
							error_log( 'KTPWP: Cleaned up temp file: ' . basename( $file ) );
						}
					}
				}
			}
		}

		// 空のディレクトリを削除
		if ( is_dir( $temp_dir ) && count( scandir( $temp_dir ) ) == 2 ) {
			rmdir( $temp_dir );
		}
	}
);

/**
 * 手動一時ファイルクリーンアップ関数（デバッグ用）
 */
function ktpwp_manual_cleanup_temp_files() {
    do_action( 'ktpwp_cleanup_temp_files' );
}

/**
 * Contact Form 7の送信データをwp_ktp_clientテーブルに登録する
 *
 * @param WPCF7_ContactForm $contact_form Contact Form 7のフォームオブジェクト.
 */

if ( defined( 'WP_CLI' ) && WP_CLI ) {
    require_once __DIR__ . '/includes/ktp-migration-cli.php';
}

// プラグイン初期化時のマイグレーション実行
add_action( 'init', 'ktpwp_ensure_department_migration' );

// 管理画面での自動マイグレーション
add_action( 'admin_init', 'ktpwp_admin_auto_migrations' );

// 管理画面メニューの登録
add_action( 'admin_menu', array( 'KTP_Settings', 'add_admin_menu' ) );

/**
 * 管理画面での自動マイグレーション実行
 */
/**
 * 利用規約テーブルの存在を確認して自動修復
 */
function ktpwp_ensure_terms_table() {
    global $wpdb;
    
    $terms_table = $wpdb->prefix . 'ktp_terms_of_service';
    $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$terms_table'" );
    
    if ( ! $table_exists ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP: Terms table not found, attempting to create' );
        }
        
        // 利用規約テーブルを直接作成
        ktpwp_create_terms_table_directly();
    } else {
        // テーブルは存在するが、データが空の場合をチェック
        $terms_count = $wpdb->get_var( "SELECT COUNT(*) FROM $terms_table WHERE is_active = 1" );
        
        if ( $terms_count == 0 ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'KTPWP: Terms table exists but no active terms found, attempting to insert default' );
            }
            
            // デフォルトの利用規約を直接挿入
            ktpwp_insert_default_terms_directly();
        } else {
            // 利用規約の内容が空でないかチェック
            $terms_data = $wpdb->get_row( "SELECT * FROM $terms_table WHERE is_active = 1 ORDER BY id DESC LIMIT 1" );
            if ( $terms_data && empty( trim( $terms_data->terms_content ) ) ) {
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( 'KTPWP: Terms content is empty, attempting to fix automatically' );
                }
                
                // 空の利用規約を修復
                ktpwp_fix_empty_terms_content( $terms_data->id );
            }
        }
    }
}

/**
 * 利用規約テーブルを直接作成
 */
function ktpwp_create_terms_table_directly() {
    global $wpdb;
    
    $terms_table = $wpdb->prefix . 'ktp_terms_of_service';
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE {$terms_table} (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        terms_content longtext NOT NULL,
        version varchar(20) NOT NULL DEFAULT '1.0',
        is_active tinyint(1) NOT NULL DEFAULT 1,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY is_active (is_active),
        KEY version (version)
    ) {$charset_collate};";
    
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $result = dbDelta( $sql );
    
    if ( ! empty( $result ) ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP: Terms table created successfully during runtime' );
        }
        
        // テーブル作成後、デフォルトデータを挿入
        ktpwp_insert_default_terms_directly();
    } else {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP: Failed to create terms table during runtime' );
        }
    }
}

/**
 * デフォルトの利用規約を直接挿入
 */
function ktpwp_insert_default_terms_directly() {
    global $wpdb;
    
    $terms_table = $wpdb->prefix . 'ktp_terms_of_service';
    
    $default_terms = '### 第1条（適用）
本規約は、KantanProプラグイン（以下「本プラグイン」）の利用に関して適用されます。

### 第2条（利用条件）
1. 本プラグインは、WordPress環境での利用を前提としています。
2. 利用者は、本プラグインの利用にあたり、適切な権限を有する必要があります。

### 第3条（禁止事項）
利用者は、本プラグインの利用にあたり、以下の行為を行ってはなりません：
1. 法令または公序良俗に違反する行為
2. 犯罪行為に関連する行為
3. 本プラグインの運営を妨害する行為
4. 他の利用者に迷惑をかける行為
5. その他、当社が不適切と判断する行為

### 第4条（本プラグインの提供の停止等）
当社は、以下のいずれかの事由があると判断した場合、利用者に事前に通知することなく本プラグインの全部または一部の提供を停止または中断することができるものとします。
1. 本プラグインにかかるコンピュータシステムの保守点検または更新を行う場合
2. 地震、落雷、火災、停電または天災などの不可抗力により、本プラグインの提供が困難となった場合
3. その他、当社が本プラグインの提供が困難と判断した場合

### 第5条（免責事項）
1. 当社は、本プラグインに関して、利用者と他の利用者または第三者との間において生じた取引、連絡または紛争等について一切責任を負いません。
2. 当社は、本プラグインの利用により生じる損害について一切の責任を負いません。
3. 当社は、本プラグインの利用により生じるデータの損失について一切の責任を負いません。

### 第6条（サービス内容の変更等）
当社は、利用者に通知することなく、本プラグインの内容を変更しまたは本プラグインの提供を中止することができるものとし、これによって利用者に生じた損害について一切の責任を負いません。

### 第7条（利用規約の変更）
当社は、必要と判断した場合には、利用者に通知することなくいつでも本規約を変更することができるものとします。

### 第8条（準拠法・裁判管轄）
1. 本規約の解釈にあたっては、日本法を準拠法とします。
2. 本プラグインに関して紛争が生じた場合には、当社の本店所在地を管轄する裁判所を専属的合意管轄とします。

### 第9条（お問い合わせ）
本規約に関するお問い合わせは、以下のメールアドレスまでお願いいたします。
kantanpro22@gmail.com

以上';
    
    $result = $wpdb->insert(
        $terms_table,
        array(
            'terms_content' => $default_terms,
            'version' => '1.0',
            'is_active' => 1
        ),
        array( '%s', '%s', '%d' )
    );
    
    if ( $result ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP: Default terms inserted successfully during runtime' );
        }
    } else {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP: Failed to insert default terms during runtime: ' . $wpdb->last_error );
        }
    }
}

/**
 * 空の利用規約内容を修復
 */
function ktpwp_fix_empty_terms_content( $terms_id ) {
    global $wpdb;
    
    $terms_table = $wpdb->prefix . 'ktp_terms_of_service';
    
    $default_terms = '### 第1条（適用）
本規約は、KantanProプラグイン（以下「本プラグイン」）の利用に関して適用されます。

### 第2条（利用条件）
1. 本プラグインは、WordPress環境での利用を前提としています。
2. 利用者は、本プラグインの利用にあたり、適切な権限を有する必要があります。

### 第3条（禁止事項）
利用者は、本プラグインの利用にあたり、以下の行為を行ってはなりません：
1. 法令または公序良俗に違反する行為
2. 犯罪行為に関連する行為
3. 本プラグインの運営を妨害する行為
4. 他の利用者に迷惑をかける行為
5. その他、当社が不適切と判断する行為

### 第4条（本プラグインの提供の停止等）
当社は、以下のいずれかの事由があると判断した場合、利用者に事前に通知することなく本プラグインの全部または一部の提供を停止または中断することができるものとします。
1. 本プラグインにかかるコンピュータシステムの保守点検または更新を行う場合
2. 地震、落雷、火災、停電または天災などの不可抗力により、本プラグインの提供が困難となった場合
3. その他、当社が本プラグインの提供が困難と判断した場合

### 第5条（免責事項）
1. 当社は、本プラグインに関して、利用者と他の利用者または第三者との間において生じた取引、連絡または紛争等について一切責任を負いません。
2. 当社は、本プラグインの利用により生じる損害について一切の責任を負いません。
3. 当社は、本プラグインの利用により生じるデータの損失について一切の責任を負いません。

### 第6条（サービス内容の変更等）
当社は、利用者に通知することなく、本プラグインの内容を変更しまたは本プラグインの提供を中止することができるものとし、これによって利用者に生じた損害について一切の責任を負いません。

### 第7条（利用規約の変更）
当社は、必要と判断した場合には、利用者に通知することなくいつでも本規約を変更することができるものとします。

### 第8条（準拠法・裁判管轄）
1. 本規約の解釈にあたっては、日本法を準拠法とします。
2. 本プラグインに関して紛争が生じた場合には、当社の本店所在地を管轄する裁判所を専属的合意管轄とします。

### 第9条（お問い合わせ）
本規約に関するお問い合わせは、以下のメールアドレスまでお願いいたします。
kantanpro22@gmail.com

以上';
    
    $result = $wpdb->update(
        $terms_table,
        array( 'terms_content' => $default_terms ),
        array( 'id' => $terms_id ),
        array( '%s' ),
        array( '%d' )
    );
    
    if ( $result !== false ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP: Empty terms content fixed successfully during runtime' );
        }
    } else {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP: Failed to fix empty terms content during runtime: ' . $wpdb->last_error );
        }
    }
}

/**
 * 利用規約同意チェック
 */
function ktpwp_check_terms_agreement() {
    // 最優先条件: ユーザーがログインしていること
    if ( ! is_user_logged_in() ) {
        return;
    }

    // 利用規約管理クラスが存在しない場合はスキップ
    if ( ! class_exists( 'KTPWP_Terms_Of_Service' ) ) {
        return;
    }

    $terms_service = KTPWP_Terms_Of_Service::get_instance();
    
    // 既に同意済みの場合はスキップ
    if ( $terms_service->has_user_agreed_to_terms() ) {
        return;
    }

    // 利用規約が存在しない場合はスキップ
    if ( empty( $terms_service->get_terms_content() ) ) {
        return;
    }

    // 管理画面の場合は利用規約を表示しない
    if ( is_admin() ) {
        return;
    }

    // フロントエンドの場合、ショートコードが使用されているページでのみ表示
    global $post;
    if ( $post && has_shortcode( $post->post_content, 'ktpwp_all_tab' ) ) {
        // 利用規約同意ダイアログを表示
        add_action( 'wp_footer', array( $terms_service, 'display_terms_dialog' ) );
    }
}

/**
 * ショートコード実行時の利用規約チェック
 */
function ktpwp_check_terms_on_shortcode() {
    // 最優先条件: ユーザーがログインしていること
    if ( ! is_user_logged_in() ) {
        return;
    }

    // 利用規約管理クラスが存在しない場合はスキップ
    if ( ! class_exists( 'KTPWP_Terms_Of_Service' ) ) {
        return;
    }

    $terms_service = KTPWP_Terms_Of_Service::get_instance();
    
    // 既に同意済みの場合はスキップ
    if ( $terms_service->has_user_agreed_to_terms() ) {
        return;
    }

    // 利用規約が存在しない場合はスキップ
    if ( empty( $terms_service->get_terms_content() ) ) {
        return;
    }

    // 利用規約同意ダイアログを表示
    add_action( 'wp_footer', array( $terms_service, 'display_terms_dialog' ) );
}

/**
 * セッション管理ヘルパー関数
 */

/**
 * 安全にセッションを開始
 */
function ktpwp_safe_session_start() {
    // 既にセッションが開始されている場合は何もしない
    if ( session_status() === PHP_SESSION_ACTIVE ) {
        return true;
    }
    
    // REST APIリクエストの場合はセッションを開始しない
    if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
        return false;
    }
    
    // AJAXリクエストの場合はセッションを開始しない（必要な場合のみ）
    if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
        return false;
    }
    
    // CLIの場合はセッションを開始しない
    if ( defined( 'WP_CLI' ) && WP_CLI ) {
        return false;
    }
    
    // ヘッダーが既に送信されている場合はセッションを開始しない
    if ( headers_sent() ) {
        return false;
    }
    
    return session_start();
}

/**
 * 安全にセッションを閉じる
 */
function ktpwp_safe_session_close() {
    if ( session_status() === PHP_SESSION_ACTIVE ) {
        session_write_close();
        return true;
    }
    return false;
}

/**
 * セッションデータを取得
 */
function ktpwp_get_session_data( $key, $default = null ) {
    if ( session_status() !== PHP_SESSION_ACTIVE ) {
        return $default;
    }
    
    return isset( $_SESSION[ $key ] ) ? $_SESSION[ $key ] : $default;
}

/**
 * セッションデータを設定
 */
function ktpwp_set_session_data( $key, $value ) {
    if ( session_status() !== PHP_SESSION_ACTIVE ) {
        return false;
    }
    
    $_SESSION[ $key ] = $value;
    return true;
}

/**
 * REST APIリクエスト前にセッションを閉じる
 */
function ktpwp_close_session_before_rest() {
    ktpwp_safe_session_close();
}
add_action( 'rest_api_init', 'ktpwp_close_session_before_rest', 1 );

/**
 * AJAXリクエスト前にセッションを閉じる（必要に応じて）
 */
function ktpwp_close_session_before_ajax() {
    // 特定のAJAXアクションでのみセッションを閉じる
    $close_session_actions = array(
        'wp_ajax_ktpwp_manual_update_check',
        'wp_ajax_nopriv_ktpwp_manual_update_check',
    );
    
    $current_action = 'wp_ajax_' . ( $_POST['action'] ?? '' );
    $current_action_nopriv = 'wp_ajax_nopriv_' . ( $_POST['action'] ?? '' );
    
    if ( in_array( $current_action, $close_session_actions ) || in_array( $current_action_nopriv, $close_session_actions ) ) {
        ktpwp_safe_session_close();
    }
}
add_action( 'wp_ajax_init', 'ktpwp_close_session_before_ajax', 1 );

/**
 * HTTPリクエスト前にセッションを閉じる
 */
function ktpwp_close_session_before_http_request( $parsed_args, $url ) {
    // 内部リクエストの場合はセッションを閉じる
    if ( strpos( $url, site_url() ) === 0 || strpos( $url, home_url() ) === 0 ) {
        ktpwp_safe_session_close();
    }
    
    return $parsed_args;
}
add_filter( 'http_request_args', 'ktpwp_close_session_before_http_request', 1, 2 );

/**
 * WP_Cronジョブ実行前にセッションを閉じる
 */
function ktpwp_close_session_before_cron() {
    ktpwp_safe_session_close();
}
add_action( 'wp_cron', 'ktpwp_close_session_before_cron', 1 );

/**
 * プラグインのアップデート処理前にセッションを閉じる
 */
function ktpwp_close_session_before_plugin_update() {
    ktpwp_safe_session_close();
}
add_action( 'upgrader_process_complete', 'ktpwp_close_session_before_plugin_update', 1 );

function ktpwp_admin_auto_migrations() {
    // 管理画面でのみ実行
    if ( ! is_admin() ) {
        return;
    }

    // 既に実行済みの場合はスキップ
    if ( get_transient( 'ktpwp_admin_migration_completed' ) ) {
        return;
    }

    // 現在のDBバージョンを取得
    $current_db_version = get_option( 'ktpwp_db_version', '0.0.0' );
    $plugin_version = KANTANPRO_PLUGIN_VERSION;

    // DBバージョンが古い場合、または新規インストールの場合
    if ( version_compare( $current_db_version, $plugin_version, '<' ) ) {

        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP Admin Migration: Starting migration from ' . $current_db_version . ' to ' . $plugin_version );
        }

        // 基本テーブル作成
        ktp_table_setup();

        // 部署テーブルの作成
        $department_table_created = ktpwp_create_department_table();

        // 部署テーブルに選択状態カラムを追加
        $column_added = ktpwp_add_department_selection_column();

        // 顧客テーブルにselected_department_idカラムを追加
        $client_column_added = ktpwp_add_client_selected_department_column();

        // マイグレーションファイルの実行
        $migrations_dir = __DIR__ . '/includes/migrations';
        if ( is_dir( $migrations_dir ) ) {
            $files = glob( $migrations_dir . '/*.php' );
            if ( $files ) {
                sort( $files );
                foreach ( $files as $file ) {
                    if ( file_exists( $file ) ) {
                        try {
                            require_once $file;
                            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                                error_log( 'KTPWP Admin Migration: Executed ' . basename( $file ) );
                            }
                        } catch ( Exception $e ) {
                            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                                error_log( 'KTPWP Admin Migration Error: ' . $e->getMessage() . ' in ' . basename( $file ) );
                            }
                        }
                    }
                }
            }
        }

        // 追加のテーブル構造修正
        ktpwp_fix_table_structures();

        // 既存データの修復
        ktpwp_repair_existing_data();

        // DBバージョンを更新
        update_option( 'ktpwp_db_version', $plugin_version );

        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP Admin Migration: Updated DB version from ' . $current_db_version . ' to ' . $plugin_version );
            if ( $department_table_created ) {
                error_log( 'KTPWP Admin Migration: Department table created/verified' );
            }
            if ( $column_added ) {
                error_log( 'KTPWP Admin Migration: Department selection column added/verified' );
            }
            if ( $client_column_added ) {
                error_log( 'KTPWP Admin Migration: Client selected_department_id column added/verified' );
            }
        }
    }

    // 実行完了を記録（1日有効）
    set_transient( 'ktpwp_admin_migration_completed', true, DAY_IN_SECONDS );
}





/**
 * プラグイン詳細情報を提供
 */
function ktpwp_plugin_information( $res, $action, $args ) {
    // プラグイン情報の要求でない場合は処理しない
    if ( $action !== 'plugin_information' ) {
        return $res;
    }

    // 対象プラグインでない場合は処理しない（複数のスラッグパターンに対応）
    $target_slugs = array( 'KantanPro', 'kantanpro', 'ktpwp' );
    if ( ! isset( $args->slug ) || ! in_array( $args->slug, $target_slugs ) ) {
        return $res;
    }

    // プラグイン情報を構築
    $plugin_info = new stdClass();
    $plugin_info->name = 'KantanPro';
    $plugin_info->slug = 'KantanPro';
    $plugin_info->version = KANTANPRO_PLUGIN_VERSION;
    $plugin_info->author = '<a href="https://www.kantanpro.com/kantanpro-page">KantanPro</a>';
    $plugin_info->homepage = 'https://www.kantanpro.com/';
    $plugin_info->requires = '5.0';
    $plugin_info->tested = '6.8.1';
    $plugin_info->requires_php = '7.4';
    $plugin_info->last_updated = date( 'Y-m-d', filemtime( __FILE__ ) );
    $plugin_info->active_installs = false;
    $plugin_info->downloaded = false;

    // ヘッダー画像を設定
    $plugin_info->banners = array(
        'high' => KANTANPRO_PLUGIN_URL . 'images/default/header_bg_image.png',
        'low' => KANTANPRO_PLUGIN_URL . 'images/default/header_bg_image.png',
    );

    // アイコンを設定
    $plugin_info->icons = array(
        '1x' => KANTANPRO_PLUGIN_URL . 'images/default/icon.png',
        '2x' => KANTANPRO_PLUGIN_URL . 'images/default/icon.png',
    );

    // セクション情報を設定
    $plugin_info->sections = array(
        'description' => ktpwp_get_plugin_description(),
        'changelog' => ktpwp_get_plugin_changelog(),
    );

    return $plugin_info;
}
add_filter( 'plugins_api', 'ktpwp_plugin_information', 10, 3 );

/**
 * プラグインの説明を取得
 */
function ktpwp_get_plugin_description() {
    $description = '
    <h3>KantanPro - ビジネスハブシステム</h3>
    <p>KantanProは、WordPress上で以下の業務を一元管理できる多機能プラグインです。中小企業から大企業まで対応可能な包括的なビジネスハブシステムです。</p>
    
    <h4>🚀 主な機能</h4>
    <ul>
        <li><strong>📊 6つの管理タブ</strong> - 仕事リスト・伝票処理・得意先・サービス・協力会社・レポート</li>
        <li><strong>📈 受注案件の進捗管理</strong> - 7段階（受注→進行中→完了→請求→支払い→ボツ）</li>
        <li><strong>📄 受注書・請求書の作成・編集・PDF保存</strong> - 個別・一括出力対応</li>
        <li><strong>👥 顧客・サービス・協力会社のマスター管理</strong> - 検索・ソート・ページネーション</li>
        <li><strong>💬 スタッフチャット</strong> - 自動スクロール・削除連動・安定化</li>
        <li><strong>📱 モバイル対応UI</strong> - gap→margin対応、iOS/Android実機対応</li>
        <li><strong>🏢 部署管理機能</strong> - 顧客ごとの部署・担当者管理</li>
        <li><strong>📋 利用規約管理機能</strong> - 同意ダイアログ・管理画面</li>
        <li><strong>🔄 シンプル更新システム</strong> - WordPress標準の更新システムに最適化</li>
        <li><strong>🔒 セキュリティ機能</strong> - XSS/CSRF/SQLi/権限管理/ファイル検証/ノンス/prepare文</li>
        <li><strong>⚡ セッション管理最適化</strong> - REST API・AJAX・内部リクエスト対応</li>
        <li><strong>📦 ページネーション機能</strong> - 全タブ・ポップアップ対応・一般設定連携</li>
        <li><strong>📎 ファイル添付機能</strong> - ドラッグ&ドロップ・複数ファイル・自動クリーンアップ</li>
        <li><strong>📅 完了日自動設定機能</strong> - 進捗変更時の自動処理</li>
        <li><strong>⚠️ 納期警告機能</strong> - 期限管理・アラート表示</li>
        <li><strong>💰 商品管理機能</strong> - 価格・数量・単位管理</li>
        <li><strong>👤 スタッフアバター表示機能</strong> - ログイン中スタッフの可視化</li>
    </ul>
    
    <h4>💡 特徴</h4>
    <ul>
        <li>直感的で使いやすいユーザーインターフェース</li>
        <li>高いカスタマイズ性と拡張性</li>
        <li>自動更新機能による最新機能の提供</li>
        <li>詳細なログ機能とデバッグサポート</li>
        <li>WordPressの標準機能との完全な互換性</li>
        <li>レスポンシブデザインでモバイル対応</li>
        <li>WordPress標準の更新システムに完全対応</li>
        <li>軽量で安定性を重視した設計</li>
    </ul>
    
    <h4>🔧 使用方法</h4>
    <p>プラグインを有効化後、ショートコード <code>[ktpwp_all_tab]</code> を固定ページに設置してご利用ください。初回利用時には利用規約への同意が必要です。</p>
    
    <h4>⚙️ システム要件</h4>
    <ul>
        <li>WordPress 5.0以上（推奨: 最新版）</li>
        <li>PHP 7.4以上（推奨: PHP 8.0以上）</li>
        <li>MySQL 5.6以上 または MariaDB 10.0以上</li>
        <li>メモリ: 最低128MB（推奨: 256MB以上）</li>
        <li>推奨PHP拡張: GD（画像処理用）</li>
    </ul>
    
    <h4>🆕 最新の改善点（1.0.6(preview)）</h4>
    <ul>
        <li>プラグイン説明文の大幅改善（機能詳細の追加）</li>
        <li>管理画面でのプラグイン情報表示の最適化</li>
        <li>プラグインリファレンス機能の強化</li>
        <li>セキュリティ機能のさらなる改善</li>
        <li>パフォーマンス最適化とUI/UX改善</li>
        <li>エラーハンドリングの強化</li>
        <li>データベース構造の最適化</li>
    </ul>
    
    <h4>📞 サポート</h4>
    <p>技術サポート、カスタマイズのご相談については、<a href="https://www.kantanpro.com/kantanpro-page" target="_blank">公式サイト</a>をご確認ください。</p>
    
    <h4>🎯 対象ユーザー</h4>
    <p>中小企業、フリーランス、プロジェクトマネージャー、営業チーム、カスタマーサポートチームなど、業務効率化を求めるあらゆる組織に最適です。</p>
    ';
    
    return $description;
}

/**
 * プラグインの更新履歴を取得（詳細版）
 */
function ktpwp_get_plugin_changelog() {
    $changelog = '
    <h3>変更履歴</h3>
    <p>KantanProプラグインの主要な更新履歴をご紹介します。</p>
    
    <h4>1.0.6(preview) - 2025年1月</h4>
    <ul>
        <li><strong>プラグイン説明文の大幅改善</strong> - 機能詳細の追加</li>
        <li><strong>管理画面でのプラグイン情報表示の最適化</strong> - ユーザビリティ向上</li>
        <li><strong>プラグインリファレンス機能の強化</strong> - ヘルプシステム改善</li>
        <li><strong>セキュリティ機能のさらなる改善</strong> - セキュリティ強化</li>
        <li><strong>パフォーマンス最適化とUI/UX改善</strong> - 操作性向上</li>
        <li><strong>エラーハンドリングの強化</strong> - 安定性向上</li>
        <li><strong>データベース構造の最適化</strong> - パフォーマンス向上</li>
    </ul>
    
    <h4>1.0.5(preview) - 2024年12月</h4>
    <ul>
        <li><strong>スタッフアバター表示機能の追加</strong> - ログイン中スタッフの可視化</li>
        <li><strong>完了日自動設定機能の実装</strong> - 進捗変更時の自動処理</li>
        <li><strong>納期警告機能の実装</strong> - 期限管理・アラート表示</li>
        <li><strong>商品管理機能の大幅改善</strong> - DECIMAL型・インデックス最適化</li>
        <li><strong>ページネーション機能の全面実装</strong> - サービス選択ポップアップ対応</li>
        <li><strong>ファイル添付機能の追加</strong> - ドラッグ&ドロップ・複数ファイル対応</li>
        <li><strong>スタッフチャット機能の強化</strong> - 自動スクロール・AJAX送信・キーボードショートカット</li>
        <li><strong>レスポンシブデザインの改善</strong> - モバイル対応強化</li>
        <li><strong>セキュリティ機能の追加強化</strong> - XSS/CSRF/SQLi対策</li>
        <li><strong>パフォーマンス最適化とUI/UX改善</strong> - 操作性向上</li>
    </ul>
    
    <h4>1.0.3(preview) - 2024年11月</h4>
    <ul>
        <li><strong>シンプルな更新システムを実装</strong></li>
        <li><strong>WordPress標準の更新システムに完全対応</strong></li>
        <li><strong>軽量化を実現し大幅なパフォーマンス向上</strong></li>
        <li><strong>不要なライブラリを削除</strong>（約90%のサイズ削減）</li>
        <li><strong>安定性とパフォーマンスの大幅向上</strong></li>
        <li><strong>保守性の高いアーキテクチャを実現</strong></li>
    </ul>
    
    <h4>1.0.2(preview) - 2024年10月</h4>
    <ul>
        <li>GitHub更新通知機能の修復・強化</li>
        <li>管理画面更新チェックツールの追加（ツール > KantanPro更新チェック）</li>
        <li>プラグインリストでの更新通知表示機能</li>
        <li>GitHubリポジトリURLの修正（https://github.com/KantanPro/freeKTP）</li>
        <li>デバッグツールの追加・強化（GitHub API連携状況確認）</li>
        <li>プラグイン更新キャッシュクリア機能</li>
        <li>手動更新チェック機能（ワンクリック確認）</li>
        <li>更新通知バナーの改善（管理画面表示）</li>
    </ul>
    
    <h4>1.0.1(preview) - 2024年9月</h4>
    <ul>
        <li>最新プレビュー版リリース</li>
        <li>ページネーション機能の全面実装（全タブ・ポップアップ対応）</li>
        <li>ファイル添付機能追加（ドラッグ&ドロップ・複数ファイル対応）</li>
        <li>完了日自動設定機能実装（進捗変更時の自動処理）</li>
        <li>納期警告機能実装（期限管理・アラート表示）</li>
        <li>商品管理機能改善（価格・数量・単位管理強化）</li>
        <li>スタッフチャット機能強化（AJAX送信・自動スクロール・キーボードショートカット）</li>
        <li>レスポンシブデザイン改善（モバイル対応強化）</li>
        <li>セキュリティ機能の追加強化</li>
        <li>パフォーマンス最適化</li>
    </ul>
    
    <h4>1.0.0(preview) - 2024年8月</h4>
    <ul>
        <li>プレビュー版リリース</li>
        <li>6つの管理タブ（仕事リスト・伝票処理・得意先・サービス・協力会社・レポート）</li>
        <li>受注案件の進捗管理（7段階）</li>
        <li>受注書・請求書のPDF出力機能</li>
        <li>顧客・サービス・協力会社のマスター管理</li>
        <li>スタッフチャット機能</li>
        <li>モバイル対応UI（レスポンシブデザイン）</li>
        <li>部署管理機能</li>
        <li>利用規約管理機能</li>
        <li>自動更新機能（GitHub連携）</li>
        <li>動的更新履歴システム</li>
        <li>セキュリティ機能の強化</li>
        <li>セッション管理最適化</li>
    </ul>
    
    <h4>🔧 技術的な改善点</h4>
    <ul>
        <li><strong>WordPress標準準拠</strong> - コーディング規約に完全準拠</li>
        <li><strong>セキュリティ強化</strong> - nonce認証、データサニタイゼーション、権限チェック</li>
        <li><strong>パフォーマンス最適化</strong> - キャッシュ機能、効率的なクエリ、メモリ使用量削減</li>
        <li><strong>レスポンシブデザイン</strong> - すべてのデバイスで最適な表示</li>
        <li><strong>アクセシビリティ向上</strong> - WAI-ARIA対応、キーボード操作対応</li>
    </ul>
    
    <p><strong>現在のバージョン:</strong> ' . esc_html( KANTANPRO_PLUGIN_VERSION ) . '</p>
    <p><em>継続的な改善により、より使いやすく安定したプラグインを提供いたします。</em></p>
    ';
    
    return $changelog;
}







/**
 * 管理画面用のスタイルを追加（プラグイン詳細モーダル用）
 */
function ktpwp_admin_plugin_styles() {
    $screen = get_current_screen();
    if ( $screen && $screen->id === 'plugins' ) {
        ?>
        <style>
        .plugin-card-KantanPro .plugin-icon img,
        .plugin-card-kantanpro .plugin-icon img {
            width: 128px;
            height: 128px;
            object-fit: cover;
        }
        
        #plugin-information-content .plugin-version-author-uri {
            margin-bottom: 15px;
        }
        
        #plugin-information-content .plugin-version-author-uri a {
            color: #0073aa;
            text-decoration: none;
        }
        
        #plugin-information-content .plugin-version-author-uri a:hover {
            text-decoration: underline;
        }
        
        #plugin-information-header {
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
        }
        
        #plugin-information-header.with-banner {
            background-image: url('<?php echo esc_url( KANTANPRO_PLUGIN_URL . 'images/default/header_bg_image.png' ); ?>');
        }
        
        /* WordPress標準のプラグイン詳細ポップアップのスタイル */
        #TB_window {
            max-width: 772px !important;
            max-height: 600px !important;
        }
        
        #TB_ajaxContent {
            width: 100% !important;
            height: 100% !important;
            overflow: auto;
        }
        
        #plugin-information {
            width: 100%;
            height: 100%;
            overflow: hidden;
        }
        
        #plugin-information-content {
            height: calc(100% - 200px);
            overflow-y: auto;
            padding: 20px;
        }
        
        #plugin-information-header {
            height: 200px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            display: flex;
            align-items: center;
        }
        
        #plugin-information-header .plugin-icon {
            margin-right: 20px;
        }
        
        #plugin-information-header .plugin-icon img {
            width: 80px;
            height: 80px;
            border-radius: 8px;
        }
        
        #plugin-information-header .plugin-info h2 {
            margin: 0 0 10px 0;
            font-size: 24px;
            color: white;
        }
        
        #plugin-information-header .plugin-info p {
            margin: 5px 0;
            opacity: 0.9;
        }
        
        #plugin-information-content h3 {
            color: #333;
            margin-top: 0;
            margin-bottom: 15px;
            font-size: 18px;
        }
        
        #plugin-information-content p {
            line-height: 1.6;
            color: #555;
        }
        
        #plugin-information-content ul {
            margin: 15px 0;
            padding-left: 20px;
        }
        
        #plugin-information-content li {
            margin-bottom: 8px;
            line-height: 1.5;
        }
        
        #plugin-information-content code {
            background: #f1f1f1;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
        }
        </style>
        <?php
    }
}
add_action( 'admin_head', 'ktpwp_admin_plugin_styles' );

/**
 * プラグイン詳細モーダルのヘッダー画像を設定
 */
function ktpwp_plugin_information_header() {
    ?>
    <script>
    jQuery(document).ready(function($) {
        // プラグイン詳細モーダルが開かれた時の処理
        $(document).on('click', '.open-plugin-details-modal', function(e) {
            var plugin = $(this).data('plugin');
            
            if (plugin === 'KantanPro') {
                // モーダルが開かれた後の処理を設定
                setTimeout(function() {
                    $('#plugin-information-header').addClass('with-banner');
                }, 200);
            }
        });
        
        // ThickBoxが開かれた時の処理
        $(document).on('tb_show', function() {
            setTimeout(function() {
                $('#plugin-information-header').addClass('with-banner');
            }, 100);
        });
    });
    </script>
    <?php
}
add_action( 'admin_footer', 'ktpwp_plugin_information_header' );

// === プラグインリスト表示の修正 ===
add_filter( 'plugin_row_meta', 'ktpwp_plugin_row_meta', 10, 2 );

function ktpwp_plugin_row_meta( $links, $file ) {
    if ( plugin_basename( __FILE__ ) === $file ) {
        // 既存のリンクをフィルタリングして「プラグインのサイトを表示」を削除
        $filtered_links = array();
        foreach ( $links as $link ) {
            // 「プラグインのサイトを表示」リンクを除外
            if ( strpos( $link, 'plugin-install.php' ) === false && 
                 strpos( $link, 'プラグインのサイトを表示' ) === false ) {
                $filtered_links[] = $link;
            }
        }
        
        // WordPress標準の詳細ポップアップを使用するリンクを追加
        $details_link = '<a href="' . admin_url( 'plugin-install.php?tab=plugin-information&plugin=KantanPro&TB_iframe=true&width=772&height=600' ) . '" class="thickbox open-plugin-details-modal" data-plugin="KantanPro">詳細を表示</a>';
        $filtered_links[] = $details_link;
        
        return $filtered_links;
    }
    return $links;
}

// 既存の ktpwp_plugin_information 関数が使用されます






