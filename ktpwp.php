<?php
/**
 * Plugin Name: KantanPro
 * Plugin URI: https://www.kantanpro.com/
 * Description: フリーランス・スモールビジネス向けの仕事効率化システム。ショートコード[ktpwp_all_tab]を固定ページに設置してください。
 * Version: 1.0.21
 * Author: KantanPro
 * Author URI: https://www.kantanpro.com/kantanpro-page
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: KantanPro
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.9.1
 * Requires PHP: 7.4
 * Update URI: https://github.com/KantanPro/KantanPro
 *
 * @package KantanPro
 */

// セキュリティ: 直接アクセスを防止
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Composer autoload を読み込みます（現在は外部依存関係なし）
if ( file_exists( plugin_dir_path( __FILE__ ) . 'vendor/autoload.php' ) ) {
    require_once plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';
}

// 開発環境設定を読み込み
if ( file_exists( plugin_dir_path( __FILE__ ) . 'development-config.php' ) ) {
    require_once plugin_dir_path( __FILE__ ) . 'development-config.php';
}

// プラグイン定数定義
if ( ! defined( 'KANTANPRO_PLUGIN_VERSION' ) ) {
    define( 'KANTANPRO_PLUGIN_VERSION', '1.0.21' );
}
if ( ! defined( 'KANTANPRO_PLUGIN_NAME' ) ) {
    define( 'KANTANPRO_PLUGIN_NAME', 'KantanPro' );
}
if ( ! defined( 'KANTANPRO_PLUGIN_DESCRIPTION' ) ) {
    // 翻訳読み込み警告を回避するため、initアクションで設定
    define( 'KANTANPRO_PLUGIN_DESCRIPTION', 'フリーランス・スモールビジネス向けの仕事効率化システム。ショートコード[ktpwp_all_tab]を固定ページに設置してください。' );
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

/**
 * プラグイン有効化フック（包括的マイグレーション）
 * 新規インストール・再有効化時に必要なデータベーステーブルを作成し、マイグレーションを実行します。
 */
register_activation_hook( __FILE__, 'ktpwp_comprehensive_activation' );

// ダミーデータ作成機能の初期化
add_action( 'admin_menu', 'ktpwp_add_dummy_data_menu' );


// === WordPress標準更新システム ===
// シンプルなバージョン管理

add_action( 'admin_init', 'ktpwp_upgrade', 10, 0 );

/**
 * 改善されたアップグレード処理
 * バージョン変更時に確実にマイグレーションを実行
 */
function ktpwp_upgrade() {
    $old_ver = get_option( 'ktpwp_version', '0' );
    $new_ver = KANTANPRO_PLUGIN_VERSION;

    if ( $old_ver === $new_ver ) {
        return;
    }

    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( 'KTPWP: アップグレード処理開始 - ' . $old_ver . ' → ' . $new_ver );
    }

    do_action( 'ktpwp_upgrade', $new_ver, $old_ver );

    // アップグレード時の自動マイグレーションを確実に実行
    try {
        if ( function_exists('ktpwp_run_auto_migrations') ) {
            ktpwp_run_auto_migrations();
        }
        
        // 適格請求書ナンバー機能のマイグレーション（確実に実行）
        if ( function_exists('ktpwp_run_qualified_invoice_migration') ) {
            ktpwp_run_qualified_invoice_migration();
        }
        
        // コスト項目テーブルに適格請求書番号カラムを追加
        if ( function_exists('ktpwp_run_qualified_invoice_number_cost_items_migration') ) {
            ktpwp_run_qualified_invoice_number_cost_items_migration();
        }
        
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP: アップグレード処理正常完了' );
        }
        
    } catch ( Exception $e ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP: アップグレード処理でエラー発生: ' . $e->getMessage() );
        }
    }

    update_option( 'ktpwp_version', $new_ver );
    update_option( 'ktpwp_upgrade_timestamp', current_time( 'mysql' ) );
}

/**
 * プラグインクラスの自動読み込み
 */
function ktpwp_autoload_classes() {
    $classes = array(
        'Kantan_Client_Class'    => 'includes/class-kantan-client.php',
        'Kantan_Service_Class'   => 'includes/class-kantan-service.php',
        'KTPWP_Supplier_Class'  => 'includes/class-tab-supplier.php',
        'KTPWP_Supplier_Security' => 'includes/class-supplier-security.php',
        'KTPWP_Supplier_Data'   => 'includes/class-supplier-data.php',
        'KTPWP_Report_Class'    => 'includes/class-tab-report.php',
        'Kantan_Order_Class'     => 'includes/class-kantan-order.php',
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
        'KTPWP_License_Manager' => 'includes/class-ktpwp-license-manager.php',
        'KTPWP_Graph_Renderer'  => 'includes/class-ktpwp-graph-renderer.php',
        // POSTデータ安全処理クラス（Adminer警告対策）
        'KTPWP_Post_Data_Handler' => 'includes/class-ktpwp-post-handler.php',
        // クライアント管理の新クラス
        'KTPWP_Client_DB'       => 'includes/class-ktpwp-client-db.php',
        'KTPWP_Client_UI'       => 'includes/class-ktpwp-client-ui.php',
        'KTPWP_Department_Manager' => 'includes/class-department-manager.php',
        'KTPWP_Terms_Of_Service' => 'includes/class-ktpwp-terms-of-service.php',
        'KTPWP_Update_Checker'  => 'includes/class-ktpwp-update-checker.php',
        'KTPWP_SVG_Icons'       => 'includes/class-ktpwp-svg-icons.php',
    );

    foreach ( $classes as $class_name => $file_path ) {
        if ( ! class_exists( $class_name ) ) {
            $full_path = MY_PLUGIN_PATH . $file_path;
            if ( file_exists( $full_path ) ) {
                require_once $full_path;
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG && $class_name === 'KTPWP_Department_Manager' ) {
                    error_log( "KTPWP: Loaded {$class_name} from {$file_path}" );
                }
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

// 部署テーブルの存在確認と作成
if ( function_exists( 'ktpwp_create_department_table' ) ) {
    $department_table_created = ktpwp_create_department_table();
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( "KTPWP: Department table creation result: " . ( $department_table_created ? 'success' : 'failed' ) );
    }
}

/**
 * 更新チェッカーの初期化
 */
function ktpwp_init_update_checker() {
    // WordPress.orgとの接続エラーを防ぐため、条件付きで初期化
    if ( class_exists( 'KTPWP_Update_Checker' ) ) {
        // 管理画面でのみ更新チェッカーを初期化
        if ( is_admin() ) {
            global $ktpwp_update_checker;
            $ktpwp_update_checker = new KTPWP_Update_Checker();
            
            // エラーログに初期化完了を記録
            error_log( 'KantanPro: 更新チェッカーが管理画面で初期化されました' );
        }
    }
}



/**
 * キャッシュマネージャーの初期化
 */
function ktpwp_init_cache() {
    if ( class_exists( 'KTPWP_Cache' ) ) {
        global $ktpwp_cache;
        $ktpwp_cache = KTPWP_Cache::get_instance();
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP Cache: キャッシュマネージャーが初期化されました' );
        }
    } else {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP Cache: キャッシュマネージャークラスが見つかりません' );
        }
    }
}

/**
 * フックマネージャーの初期化
 */
function ktpwp_init_hook_manager() {
    if ( class_exists( 'KTPWP_Hook_Manager' ) ) {
        global $ktpwp_hook_manager;
        $ktpwp_hook_manager = KTPWP_Hook_Manager::get_instance();
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP Hook Manager: フックマネージャーが初期化されました' );
        }
    } else {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP Hook Manager: フックマネージャークラスが見つかりません' );
        }
    }
}

/**
 * 画像最適化機能の初期化
 */
function ktpwp_init_image_optimizer() {
    if ( class_exists( 'KTPWP_Image_Optimizer' ) ) {
        global $ktpwp_image_optimizer;
        $ktpwp_image_optimizer = KTPWP_Image_Optimizer::get_instance();
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP Image Optimizer: 画像最適化機能が初期化されました' );
        }
    } else {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP Image Optimizer: 画像最適化クラスが見つかりません' );
        }
    }
}

// プラグインが完全に読み込まれた後に実行（最初に実行してフック最適化を行う）
add_action( 'plugins_loaded', 'ktpwp_init_hook_manager', 0 );
add_action( 'plugins_loaded', 'ktpwp_init_update_checker' );
add_action( 'plugins_loaded', 'ktpwp_init_cache' );
add_action( 'plugins_loaded', 'ktpwp_init_image_optimizer' );

// キャッシュクリア処理のAJAXハンドラー
add_action( 'wp_ajax_ktpwp_clear_cache', 'ktpwp_handle_clear_cache_ajax' );
function ktpwp_handle_clear_cache_ajax() {
    // 権限チェック
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( '権限がありません' );
    }
    
    // ナンスチェック
    if ( ! wp_verify_nonce( $_POST['nonce'], 'ktpwp_clear_cache' ) ) {
        wp_send_json_error( 'セキュリティチェックに失敗しました' );
    }
    
    try {
        // すべてのキャッシュをクリア
        ktpwp_clear_all_cache();
        
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP Cache: 管理画面からキャッシュをクリアしました' );
        }
        
        wp_send_json_success( 'キャッシュが正常にクリアされました' );
    } catch ( Exception $e ) {
        wp_send_json_error( 'キャッシュのクリアに失敗しました: ' . $e->getMessage() );
    }
}

// 一括画像変換処理のAJAXハンドラー
add_action( 'wp_ajax_ktpwp_convert_all_images', 'ktpwp_handle_convert_all_images_ajax' );
function ktpwp_handle_convert_all_images_ajax() {
    // 権限チェック
    if ( ! current_user_can( 'upload_files' ) ) {
        wp_send_json_error( '権限がありません' );
    }
    
    // ナンスチェック
    if ( ! wp_verify_nonce( $_POST['nonce'], 'ktpwp_image_optimization' ) ) {
        wp_send_json_error( 'セキュリティチェックに失敗しました' );
    }
    
    try {
        // 画像最適化インスタンスを取得
        global $ktpwp_image_optimizer;
        
        if ( ! $ktpwp_image_optimizer ) {
            wp_send_json_error( '画像最適化機能が利用できません' );
        }
        
        // すべての画像添付ファイルを取得
        $attachments = get_posts( array(
            'post_type' => 'attachment',
            'post_mime_type' => array( 'image/jpeg', 'image/png', 'image/gif' ),
            'posts_per_page' => 100, // 最初の100件のみ（パフォーマンス考慮）
            'post_status' => 'inherit',
        ) );
        
        $converted_count = 0;
        $total_count = count( $attachments );
        
        foreach ( $attachments as $attachment ) {
            $image_path = get_attached_file( $attachment->ID );
            
            if ( $image_path && file_exists( $image_path ) ) {
                $webp_file = $ktpwp_image_optimizer->convert_to_webp( $image_path );
                
                if ( $webp_file ) {
                    $converted_count++;
                }
            }
        }
        
        wp_send_json_success( "{$converted_count} / {$total_count} 個の画像をWebPに変換しました" );
        
    } catch ( Exception $e ) {
        wp_send_json_error( '一括変換に失敗しました: ' . $e->getMessage() );
    }
}

// 管理画面でキャッシュ管理スクリプトを読み込み
add_action( 'admin_enqueue_scripts', 'ktpwp_enqueue_cache_admin_scripts' );
function ktpwp_enqueue_cache_admin_scripts( $hook ) {
    // KantanPro設定ページでのみ読み込み
    if ( 'toplevel_page_ktp-settings' === $hook || 'settings_page_ktp-settings' === $hook ) {
        wp_enqueue_script(
            'ktpwp-cache-admin',
            KANTANPRO_PLUGIN_URL . 'js/ktpwp-cache-admin.js',
            array( 'jquery' ),
            KANTANPRO_PLUGIN_VERSION,
            true
        );
        
        // ナンスを JavaScript に渡す
        wp_localize_script( 'ktpwp-cache-admin', 'ktpwp_cache_admin', array(
            'nonce' => wp_create_nonce( 'ktpwp_clear_cache' ),
            'ajaxurl' => admin_url( 'admin-ajax.php' )
        ) );
    }
}

// 管理画面で画像最適化スクリプトを読み込み
add_action( 'admin_enqueue_scripts', 'ktpwp_enqueue_image_optimizer_scripts' );

// 管理画面で通知非表示スクリプトを読み込み
add_action( 'admin_enqueue_scripts', 'ktpwp_enqueue_notification_dismiss_scripts' );
function ktpwp_enqueue_image_optimizer_scripts( $hook ) {
    // メディアライブラリまたは設定ページで読み込み
    if ( 'upload.php' === $hook || 'post.php' === $hook || 'post-new.php' === $hook || 
         'toplevel_page_ktp-settings' === $hook || 'settings_page_ktp-settings' === $hook ) {
        
        wp_enqueue_script(
            'ktpwp-image-optimizer',
            KANTANPRO_PLUGIN_URL . 'js/ktpwp-image-optimizer.js',
            array( 'jquery' ),
            KANTANPRO_PLUGIN_VERSION,
            true
        );
        
        // ナンスを JavaScript に渡す
        wp_localize_script( 'ktpwp-image-optimizer', 'ktpwp_image_optimizer', array(
            'nonce' => wp_create_nonce( 'ktpwp_image_optimization' ),
            'ajaxurl' => admin_url( 'admin-ajax.php' )
        ) );
    }
}

// 通知非表示スクリプトを読み込み
function ktpwp_enqueue_notification_dismiss_scripts( $hook ) {
    // KantanPro設定ページでのみ読み込み
    if ( 'toplevel_page_ktp-settings' === $hook || 'settings_page_ktp-settings' === $hook ) {
        wp_enqueue_script(
            'ktpwp-notification-dismiss',
            KANTANPRO_PLUGIN_URL . 'js/ktpwp-notification-dismiss.js',
            array( 'jquery' ),
            KANTANPRO_PLUGIN_VERSION,
            true
        );
        
        // ナンスを JavaScript に渡す
        wp_localize_script( 'ktpwp-notification-dismiss', 'ktpwp_notification_dismiss', array(
            'nonce' => wp_create_nonce( 'ktpwp_dismiss_notification' ),
            'ajaxurl' => admin_url( 'admin-ajax.php' )
        ) );
    }
}

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

// === 改善された自動マイグレーション機能 ===

/**
 * 配布環境対応の強化された自動マイグレーション実行関数
 * 不特定多数のサイトでの配布に対応
 */
function ktpwp_run_auto_migrations() {
    // 出力バッファリングを開始（予期しない出力を防ぐ）
    ob_start();
    
    // マイグレーション進行中チェック（重複実行防止）
    if ( get_option( 'ktpwp_migration_in_progress', false ) ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP Auto Migration: マイグレーションが既に進行中です' );
        }
        return;
    }
    
    // 現在のDBバージョンを取得
    $current_db_version = get_option( 'ktpwp_db_version', '0.0.0' );
    $plugin_version = KANTANPRO_PLUGIN_VERSION;

    // DBバージョンが古い場合、または新規インストールの場合
    if ( version_compare( $current_db_version, $plugin_version, '<' ) ) {

        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP Auto Migration: Starting migration from ' . $current_db_version . ' to ' . $plugin_version );
        }

        try {
            // マイグレーション開始フラグを設定
            update_option( 'ktpwp_migration_in_progress', true );
            update_option( 'ktpwp_migration_start_time', current_time( 'mysql' ) );
            update_option( 'ktpwp_migration_attempts', get_option( 'ktpwp_migration_attempts', 0 ) + 1 );

            // 配布環境での安全性チェック
            if ( ! ktpwp_verify_migration_safety() ) {
                throw new Exception( 'マイグレーション安全性チェックに失敗しました' );
            }

            // 新規インストール判定の強化
            $is_new_installation = ktpwp_is_new_installation();
            
            if ( $is_new_installation ) {
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( 'KTPWP Auto Migration: 新規インストールを検出 - 基本構造のみで初期化' );
                }
                
                // 新規インストール時は基本構造のみで初期化
                ktpwp_initialize_new_installation();
            } else {
                // 既存環境での段階的マイグレーション
                ktpwp_run_staged_migrations( $current_db_version, $plugin_version );
            }

            // 適格請求書ナンバー機能のマイグレーション（確実に実行）
            if ( function_exists('ktpwp_run_qualified_invoice_migration') ) {
                ktpwp_run_qualified_invoice_migration();
            }

            // データベースバージョンを更新
            update_option( 'ktpwp_db_version', $plugin_version );
            update_option( 'ktpwp_last_migration_timestamp', current_time( 'mysql' ) );
            update_option( 'ktpwp_migration_success_count', get_option( 'ktpwp_migration_success_count', 0 ) + 1 );
            
            // 配布環境での確実なバージョン同期
            $updated_db_version = get_option( 'ktpwp_db_version', '0.0.0' );
            if ( $updated_db_version !== $plugin_version ) {
                // 強制的に再設定
                update_option( 'ktpwp_db_version', $plugin_version );
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( 'KTPWP Auto Migration: バージョン同期を強制実行しました' );
                }
            }

            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'KTPWP Auto Migration: Migration completed successfully' );
            }

        } catch ( Exception $e ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'KTPWP Auto Migration Error: ' . $e->getMessage() );
            }
            
            // 配布環境での誤ったエラー設定を防ぐためのチェック
            $should_record_error = true;
            
            // マイグレーションが実際に成功している場合はエラーを記録しない
            $migration_success_count = get_option( 'ktpwp_migration_success_count', 0 );
            $last_migration_timestamp = get_option( 'ktpwp_last_migration_timestamp', '' );
            
            if ( $migration_success_count > 0 && ! empty( $last_migration_timestamp ) ) {
                // 最終マイグレーションから1時間以内の場合はエラーを記録しない
                $last_migration_time = strtotime( $last_migration_timestamp );
                $current_time = current_time( 'timestamp' );
                
                if ( $current_time - $last_migration_time <= 3600 ) { // 1時間 = 3600秒
                    $should_record_error = false;
                    
                    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                        error_log( 'KTPWP: 配布環境での誤ったマイグレーションエラー記録を防止しました' );
                    }
                }
            }
            
            // エラー情報を詳細に記録（配布環境での誤記録を防ぐ）
            if ( $should_record_error ) {
                update_option( 'ktpwp_migration_error', $e->getMessage() );
                update_option( 'ktpwp_migration_error_timestamp', current_time( 'mysql' ) );
                update_option( 'ktpwp_migration_error_count', get_option( 'ktpwp_migration_error_count', 0 ) + 1 );
            }
        } finally {
            // マイグレーション進行中フラグをクリア
            delete_option( 'ktpwp_migration_in_progress' );
        }
    }
    
    // 出力バッファをクリア（予期しない出力を除去）
    $output = ob_get_clean();
    
    // デバッグ時のみ、予期しない出力があればログに記録
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG && ! empty( $output ) ) {
        error_log( 'KTPWP Auto Migration: 予期しない出力を検出: ' . substr( $output, 0, 1000 ) );
    }
}

/**
 * 新規インストール時の基本構造初期化
 */
function ktpwp_initialize_new_installation() {
    try {
        // 1. 基本テーブル作成（確実に実行）
        ktpwp_safe_table_setup();

        // 2. 部署テーブルの作成（確実に実行）
        ktpwp_safe_create_department_table();
        ktpwp_safe_add_department_selection_column();
        ktpwp_safe_add_client_selected_department_column();

        // 3. 新規インストール完了フラグを設定
        update_option( 'ktpwp_new_installation_completed', true );
        update_option( 'ktpwp_new_installation_timestamp', current_time( 'mysql' ) );
        
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP: 新規インストールの基本構造初期化が完了' );
        }
        
    } catch ( Exception $e ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP New Installation Error: ' . $e->getMessage() );
        }
        throw $e;
    }
}

/**
 * 段階的マイグレーション実行（既存環境用）
 */
function ktpwp_run_staged_migrations( $from_version, $to_version ) {
    try {
        // 1. 基本テーブル作成（確実に実行）
        ktpwp_safe_table_setup();

        // 2. 部署テーブルの作成（確実に実行）
        ktpwp_safe_create_department_table();
        ktpwp_safe_add_department_selection_column();
        ktpwp_safe_add_client_selected_department_column();

        // 3. マイグレーションファイルの実行（順序付き・安全実行）
        ktpwp_safe_run_migration_files( $from_version, $to_version );

        // 4. 適格請求書マイグレーション（確実に実行）
        ktpwp_safe_run_qualified_invoice_migration();

        // 5. テーブル構造修正（安全実行）
        ktpwp_safe_fix_table_structures();

        // 6. 既存データ修復（安全実行）
        ktpwp_safe_repair_existing_data();

        // 7. データベース整合性の最終チェック
        if ( ! ktpwp_verify_database_integrity() ) {
            throw new Exception( 'マイグレーション後のデータベース整合性チェックに失敗しました' );
        }
        
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP: 段階的マイグレーションが正常に完了' );
        }
        
    } catch ( Exception $e ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP Staged Migration Error: ' . $e->getMessage() );
        }
        throw $e;
    }
}

/**
 * 配布環境用の新規インストール検出と自動マイグレーション
 * より確実な新規インストール判定とマイグレーション実行
 */
function ktpwp_distribution_auto_migration() {
    // 新規インストール判定の強化
    $is_new_installation = ktpwp_is_new_installation();
    $needs_migration = ktpwp_needs_migration();
    
    if ( $is_new_installation || $needs_migration ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP Distribution: Auto migration triggered - New install: ' . ($is_new_installation ? 'true' : 'false') . ', Needs migration: ' . ($needs_migration ? 'true' : 'false') );
        }
        
        // 自動マイグレーションを実行
        ktpwp_run_auto_migrations();
        
        // 適格請求書ナンバー機能のマイグレーション（確実に実行）
        if ( function_exists('ktpwp_run_qualified_invoice_migration') ) {
            ktpwp_run_qualified_invoice_migration();
        }
        
        // 新規インストールの場合は完了フラグを設定
        if ( $is_new_installation ) {
            update_option( 'ktpwp_new_installation_completed', true );
            update_option( 'ktpwp_new_installation_timestamp', current_time( 'mysql' ) );
        }
    }
}

/**
 * マイグレーション安全性チェック
 */
function ktpwp_verify_migration_safety() {
    global $wpdb;
    
    // データベース接続チェック
    if ( ! $wpdb->check_connection() ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP Migration Safety: データベース接続エラー' );
        }
        return false;
    }
    
    // 書き込み権限チェック
    $test_option = 'ktpwp_migration_test_' . time();
    $test_result = update_option( $test_option, 'test' );
    if ( ! $test_result ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP Migration Safety: オプションテーブル書き込み権限エラー' );
        }
        return false;
    }
    delete_option( $test_option );
    
    // メモリ制限チェック
    $memory_limit = ini_get( 'memory_limit' );
    $memory_limit_bytes = wp_convert_hr_to_bytes( $memory_limit );
    if ( $memory_limit_bytes < 64 * 1024 * 1024 ) { // 64MB未満
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP Migration Safety: メモリ制限が低すぎます: ' . $memory_limit );
        }
        return false;
    }
    
    // 実行時間制限チェック
    $max_execution_time = ini_get( 'max_execution_time' );
    if ( $max_execution_time > 0 && $max_execution_time < 30 ) { // 30秒未満
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP Migration Safety: 実行時間制限が短すぎます: ' . $max_execution_time . '秒' );
        }
        return false;
    }
    
    // ディスク容量チェック
    $upload_dir = wp_upload_dir();
    $disk_free_space = disk_free_space( $upload_dir['basedir'] );
    if ( $disk_free_space !== false && $disk_free_space < 50 * 1024 * 1024 ) { // 50MB未満
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP Migration Safety: ディスク容量が不足しています: ' . round( $disk_free_space / 1024 / 1024, 2 ) . 'MB' );
        }
        return false;
    }
    
    // WordPressバージョンチェック
    global $wp_version;
    if ( version_compare( $wp_version, '5.0', '<' ) ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP Migration Safety: WordPressバージョンが古すぎます: ' . $wp_version );
        }
        return false;
    }
    
    // PHPバージョンチェック
    if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP Migration Safety: PHPバージョンが古すぎます: ' . PHP_VERSION );
        }
        return false;
    }
    
    // 必須PHP拡張機能チェック
    $required_extensions = array( 'mysqli', 'json', 'mbstring' );
    foreach ( $required_extensions as $ext ) {
        if ( ! extension_loaded( $ext ) ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'KTPWP Migration Safety: 必須PHP拡張機能が不足しています: ' . $ext );
            }
            return false;
        }
    }
    
    // データベース権限チェック
    try {
        $test_table = $wpdb->prefix . 'ktpwp_migration_test_' . time();
        $create_result = $wpdb->query( "CREATE TABLE IF NOT EXISTS `{$test_table}` (id INT PRIMARY KEY)" );
        if ( $create_result === false ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'KTPWP Migration Safety: テーブル作成権限エラー' );
            }
            return false;
        }
        
        $drop_result = $wpdb->query( "DROP TABLE IF EXISTS `{$test_table}`" );
        if ( $drop_result === false ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'KTPWP Migration Safety: テーブル削除権限エラー' );
            }
            return false;
        }
    } catch ( Exception $e ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP Migration Safety: データベース権限チェックエラー: ' . $e->getMessage() );
        }
        return false;
    }
    
    // プラグイン競合チェック
    $conflicting_plugins = array(
        'woocommerce/woocommerce.php',
        'easy-digital-downloads/easy-digital-downloads.php'
    );
    
    $active_plugins = get_option( 'active_plugins', array() );
    foreach ( $conflicting_plugins as $plugin ) {
        if ( in_array( $plugin, $active_plugins ) ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'KTPWP Migration Safety: 競合プラグインが検出されました: ' . $plugin );
            }
            // 競合プラグインがあっても警告のみで続行
        }
    }
    
    return true;
}

/**
 * 安全なテーブルセットアップ
 */
function ktpwp_safe_table_setup() {
    try {
        if ( function_exists( 'ktp_table_setup' ) ) {
            ktp_table_setup();
        } else {
            // フォールバック: 基本的なテーブル作成
            ktpwp_create_basic_tables();
        }
    } catch ( Exception $e ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP Safe Table Setup Error: ' . $e->getMessage() );
        }
        throw $e;
    }
}

/**
 * 安全な部署テーブル作成
 */
function ktpwp_safe_create_department_table() {
    try {
        if ( function_exists( 'ktpwp_create_department_table' ) ) {
            ktpwp_create_department_table();
        }
    } catch ( Exception $e ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP Safe Department Table Creation Error: ' . $e->getMessage() );
        }
        // 部署テーブル作成エラーは致命的ではないため、ログのみ記録
    }
}

/**
 * 安全な部署選択カラム追加
 */
function ktpwp_safe_add_department_selection_column() {
    try {
        if ( function_exists( 'ktpwp_add_department_selection_column' ) ) {
            ktpwp_add_department_selection_column();
        }
    } catch ( Exception $e ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP Safe Department Selection Column Error: ' . $e->getMessage() );
        }
    }
}

/**
 * 安全なクライアント部署カラム追加
 */
function ktpwp_safe_add_client_selected_department_column() {
    try {
        if ( function_exists( 'ktpwp_add_client_selected_department_column' ) ) {
            ktpwp_add_client_selected_department_column();
        }
    } catch ( Exception $e ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP Safe Client Department Column Error: ' . $e->getMessage() );
        }
    }
}

/**
 * 安全なマイグレーションファイル実行
 */
function ktpwp_safe_run_migration_files( $from_version, $to_version ) {
    try {
        if ( function_exists( 'ktpwp_run_migration_files' ) ) {
            ktpwp_run_migration_files( $from_version, $to_version );
        } else {
            // マイグレーションファイルを直接実行
            ktpwp_run_migration_files_directly( $from_version, $to_version );
        }
    } catch ( Exception $e ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP Safe Migration Files Error: ' . $e->getMessage() );
        }
        throw $e;
    }
}

/**
 * マイグレーションファイルを直接実行する関数
 */
function ktpwp_run_migration_files_directly( $from_version, $to_version ) {
    global $wpdb;
    
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( 'KTPWP: マイグレーションファイルを直接実行します: ' . $from_version . ' -> ' . $to_version );
    }
    
    // マイグレーションディレクトリのパス
    $migration_dir = plugin_dir_path( __FILE__ ) . 'includes/migrations/';
    
    // マイグレーションファイルが存在するかチェック
    if ( ! is_dir( $migration_dir ) ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP: マイグレーションディレクトリが存在しません: ' . $migration_dir );
        }
        return;
    }
    
    // マイグレーションファイルを取得
    $migration_files = glob( $migration_dir . '*.php' );
    
    if ( empty( $migration_files ) ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP: マイグレーションファイルが見つかりません' );
        }
        return;
    }
    
    // ファイル名でソート
    sort( $migration_files );
    
    foreach ( $migration_files as $migration_file ) {
        $filename = basename( $migration_file );
        
        // 既に実行済みかチェック
        $migration_key = 'ktp_migration_' . md5( $filename );
        if ( get_option( $migration_key, false ) ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'KTPWP: マイグレーションファイルは既に実行済みです: ' . $filename );
            }
            continue;
        }
        
        try {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'KTPWP: マイグレーションファイルを実行中: ' . $filename );
            }
            
            // マイグレーションファイルを読み込み
            require_once $migration_file;
            
            // 実行完了フラグを設定
            update_option( $migration_key, true );
            update_option( $migration_key . '_timestamp', current_time( 'mysql' ) );
            
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'KTPWP: マイグレーションファイルが正常に実行されました: ' . $filename );
            }
            
        } catch ( Exception $e ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'KTPWP Migration File Error: ' . $filename . ' - ' . $e->getMessage() );
            }
            // マイグレーションファイルのエラーは致命的ではないため、ログのみ記録して続行
        }
    }
}

/**
 * 安全な適格請求書マイグレーション実行
 */
function ktpwp_safe_run_qualified_invoice_migration() {
    try {
        if ( function_exists( 'ktpwp_run_qualified_invoice_migration' ) ) {
            ktpwp_run_qualified_invoice_migration();
        }
    } catch ( Exception $e ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP Safe Qualified Invoice Migration Error: ' . $e->getMessage() );
        }
        // 適格請求書マイグレーションエラーは致命的ではないため、ログのみ記録
    }
}

/**
 * 安全なテーブル構造修正
 */
function ktpwp_safe_fix_table_structures() {
    try {
        if ( function_exists( 'ktpwp_fix_table_structures' ) ) {
            ktpwp_fix_table_structures();
        }
    } catch ( Exception $e ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP Safe Table Structure Fix Error: ' . $e->getMessage() );
        }
        // テーブル構造修正エラーは致命的ではないため、ログのみ記録
    }
}

/**
 * 安全な既存データ修復
 */
function ktpwp_safe_repair_existing_data() {
    try {
        if ( function_exists( 'ktpwp_repair_existing_data' ) ) {
            ktpwp_repair_existing_data();
        }
    } catch ( Exception $e ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP Safe Data Repair Error: ' . $e->getMessage() );
        }
        // データ修復エラーは致命的ではないため、ログのみ記録
    }
}

/**
 * データベース整合性チェック
 */
function ktpwp_verify_database_integrity() {
    global $wpdb;
    
    try {
        // 主要テーブルの存在チェック
        $required_tables = array(
            $wpdb->prefix . 'ktp_order',
            $wpdb->prefix . 'ktp_supplier',
            $wpdb->prefix . 'ktp_client',
            $wpdb->prefix . 'ktp_service'
        );
        
        foreach ( $required_tables as $table ) {
            $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$table'" );
            if ( ! $table_exists ) {
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( 'KTPWP Database Integrity: 必須テーブルが存在しません: ' . $table );
                }
                return false;
            }
        }
        
        return true;
    } catch ( Exception $e ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP Database Integrity Check Error: ' . $e->getMessage() );
        }
        return false;
    }
}



/**
 * 基本的なテーブル作成（フォールバック用）
 */
function ktpwp_create_basic_tables() {
    global $wpdb;
    
    $charset_collate = $wpdb->get_charset_collate();
    
    // 基本的なテーブル作成SQL
    $sql = array();
    
    // 注文テーブル
    $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ktp_order (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        order_name varchar(255) NOT NULL,
        client_id mediumint(9) NOT NULL,
        supplier_id mediumint(9) NOT NULL,
        service_id mediumint(9) NOT NULL,
        order_date date NOT NULL,
        delivery_date date NOT NULL,
        order_amount decimal(10,2) NOT NULL,
        order_status varchar(50) NOT NULL DEFAULT '進行中',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";
    
    // サプライヤーテーブル
    $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ktp_supplier (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        supplier_name varchar(255) NOT NULL,
        supplier_email varchar(255),
        supplier_phone varchar(50),
        supplier_address text,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";
    
    // クライアントテーブル
    $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ktp_client (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        client_name varchar(255) NOT NULL,
        client_email varchar(255),
        client_phone varchar(50),
        client_address text,
        client_status varchar(50) DEFAULT '対象',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";
    
    // サービステーブル
    $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ktp_service (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        service_name varchar(255) NOT NULL,
        service_description text,
        service_price decimal(10,2) NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";
    
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );
}

/**
 * マイグレーション必要性の判定
 */
function ktpwp_needs_migration() {
    $current_db_version = get_option( 'ktpwp_db_version', '0.0.0' );
    $plugin_version = KANTANPRO_PLUGIN_VERSION;
    
    return version_compare( $current_db_version, $plugin_version, '<' );
}

/**
 * 配布環境用の包括的アクティベーション
 * 新規インストール・再有効化時の確実なマイグレーション実行
 */
function ktpwp_comprehensive_activation() {
    // 出力バッファリングを開始（予期しない出力を防ぐ）
    ob_start();
    
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( 'KTPWP: 配布環境対応の包括的プラグイン有効化処理を開始' );
    }

    try {
        // 配布環境での安全性チェック
        if ( ! ktpwp_verify_migration_safety() ) {
            throw new Exception( '有効化時のマイグレーション安全性チェックに失敗しました' );
        }
        
        // 新規インストール判定
        $is_new_installation = ktpwp_is_new_installation();
        
        if ( $is_new_installation ) {
            update_option( 'ktpwp_new_installation_detected', true );
            
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'KTPWP: 新規インストールを検出' );
            }
        }
        
        // 1. 基本テーブル作成処理（安全実行）
        ktpwp_safe_table_setup();
        
        // 2. 設定クラスのアクティベート処理
        if ( class_exists( 'KTP_Settings' ) && method_exists( 'KTP_Settings', 'activate' ) ) {
            KTP_Settings::activate();
        }
        
        // 3. プラグインリファレンス更新処理
        if ( class_exists( 'KTPWP_Plugin_Reference' ) && method_exists( 'KTPWP_Plugin_Reference', 'on_plugin_activation' ) ) {
            KTPWP_Plugin_Reference::on_plugin_activation();
        }
        
        // 4. 寄付機能テーブルの作成

        
        // 5. 配布環境用の自動マイグレーションの実行
        ktpwp_distribution_auto_migration();
        
        // 6. 適格請求書ナンバー機能のマイグレーション（確実に実行）
        if ( function_exists('ktpwp_run_qualified_invoice_migration') ) {
            ktpwp_run_qualified_invoice_migration();
        }
        
        // 7. データベース整合性チェック
        if ( ! ktpwp_verify_database_integrity() ) {
            throw new Exception( '有効化後のデータベース整合性チェックに失敗しました' );
        }
        
        // 7. 有効化完了フラグの設定
        update_option( 'ktpwp_activation_completed', true );
        update_option( 'ktpwp_activation_timestamp', current_time( 'mysql' ) );
        update_option( 'ktpwp_version', KANTANPRO_PLUGIN_VERSION );
        update_option( 'ktpwp_activation_success_count', get_option( 'ktpwp_activation_success_count', 0 ) + 1 );
        
        // 8. 再有効化フラグをクリア（正常に有効化された場合）
        delete_option( 'ktpwp_reactivation_required' );
        
        // 9. 有効化成功通知の設定
        if ( $is_new_installation ) {
            set_transient( 'ktpwp_activation_success', 'KantanProプラグインが正常にインストールされました。', 60 );
        } else {
            set_transient( 'ktpwp_activation_success', 'KantanProプラグインが正常に有効化されました。', 60 );
        }
        
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP: 配布環境対応の包括的プラグイン有効化処理が正常に完了' );
        }
        
    } catch ( Exception $e ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP: プラグイン有効化処理でエラーが発生: ' . $e->getMessage() );
        }
        
        // エラー情報を詳細に記録
        update_option( 'ktpwp_activation_error', $e->getMessage() );
        update_option( 'ktpwp_activation_error_timestamp', current_time( 'mysql' ) );
        update_option( 'ktpwp_activation_error_count', get_option( 'ktpwp_activation_error_count', 0 ) + 1 );
        
        // エラーが発生した場合でも基本的な設定は保存
        update_option( 'ktpwp_version', KANTANPRO_PLUGIN_VERSION );
        update_option( 'ktpwp_db_version', KANTANPRO_PLUGIN_VERSION );
        
        // エラー通知を設定
        set_transient( 'ktpwp_activation_error', 'プラグインの有効化中にエラーが発生しました。管理者にお問い合わせください。', 300 );
    }
    
    // 出力バッファをクリア（予期しない出力を除去）
    $output = ob_get_clean();
    
    // デバッグ時のみ、予期しない出力があればログに記録
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG && ! empty( $output ) ) {
        error_log( 'KTPWP: プラグイン有効化処理中に予期しない出力を検出: ' . substr( $output, 0, 1000 ) );
    }
}



/**
 * 配布環境用の再有効化時のマイグレーション処理
 */
function ktpwp_check_reactivation_migration() {
    // 再有効化フラグをチェック
    $reactivation_flag = get_option( 'ktpwp_reactivation_required', false );
    
    if ( ! $reactivation_flag ) {
        return;
    }
    
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( 'KTPWP: プラグイン再有効化時のマイグレーションを実行' );
    }
    
    try {
        // 配布環境での安全性チェック
        if ( ! ktpwp_verify_migration_safety() ) {
            throw new Exception( '再有効化時のマイグレーション安全性チェックに失敗しました' );
        }
        
        // 配布環境用の自動マイグレーションを実行
        ktpwp_distribution_auto_migration();
        
        // 再有効化フラグをクリア
        delete_option( 'ktpwp_reactivation_required' );
        
        // 再有効化完了フラグを設定
        update_option( 'ktpwp_reactivation_completed', true );
        update_option( 'ktpwp_reactivation_timestamp', current_time( 'mysql' ) );
        
        // 成功通知を設定
        set_transient( 'ktpwp_reactivation_success', 'プラグインの再有効化が正常に完了しました。', 60 );
        
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP: プラグイン再有効化時のマイグレーションが正常に完了' );
        }
        
    } catch ( Exception $e ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP: プラグイン再有効化時のマイグレーションでエラー: ' . $e->getMessage() );
        }
        
        // エラー情報を詳細に記録
        update_option( 'ktpwp_reactivation_error', $e->getMessage() );
        update_option( 'ktpwp_reactivation_error_timestamp', current_time( 'mysql' ) );
        update_option( 'ktpwp_reactivation_error_count', get_option( 'ktpwp_reactivation_error_count', 0 ) + 1 );
        
        // エラー通知を設定
        set_transient( 'ktpwp_reactivation_error', 'プラグインの再有効化中にエラーが発生しました。', 300 );
    }
}

/**
 * 新規インストール判定関数
 * 配布環境対応の強化版
 * 
 * @return bool 新規インストールの場合true、既存インストールの場合false
 */
function ktpwp_is_new_installation() {
    // 既に判定済みの場合はキャッシュを使用
    $cached_result = get_transient( 'ktpwp_new_installation_check' );
    if ( $cached_result !== false ) {
        return $cached_result;
    }
    
    global $wpdb;
    
    // 1. メインテーブルの存在確認
    $main_tables = array(
        $wpdb->prefix . 'ktp_order',
        $wpdb->prefix . 'ktp_supplier',
        $wpdb->prefix . 'ktp_client',
        $wpdb->prefix . 'ktp_service'
    );

    $existing_tables = array();
    foreach ( $main_tables as $table ) {
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) === $table ) {
            $existing_tables[] = $table;
        }
    }

    // テーブルが1つも存在しない場合は確実に新規インストール
    if ( empty( $existing_tables ) ) {
        set_transient( 'ktpwp_new_installation_check', true, HOUR_IN_SECONDS );
        return true;
    }

    // 2. データの存在確認
    $has_data = false;
    foreach ( $existing_tables as $table ) {
        $count = $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" );
        if ( $count > 0 ) {
            $has_data = true;
            break;
        }
    }

    // データが存在しない場合は新規インストール
    if ( ! $has_data ) {
        set_transient( 'ktpwp_new_installation_check', true, HOUR_IN_SECONDS );
        return true;
    }

    // 3. プラグイン設定の存在確認
    $plugin_options = array(
        'ktpwp_design_settings',
        'ktpwp_company_info',
        'ktp_order_table_version',
        'ktpwp_version',
        'ktpwp_db_version'
    );

    foreach ( $plugin_options as $option ) {
        if ( get_option( $option, false ) !== false ) {
            set_transient( 'ktpwp_new_installation_check', false, HOUR_IN_SECONDS );
            return false; // 既存環境
        }
    }

    // 4. マイグレーション履歴の確認
    $migration_options = $wpdb->get_results( 
        "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'ktpwp_migration_%_completed' LIMIT 1" 
    );
    
    if ( ! empty( $migration_options ) ) {
        set_transient( 'ktpwp_new_installation_check', false, HOUR_IN_SECONDS );
        return false; // 既存環境（マイグレーション履歴あり）
    }

    // 5. データベースバージョンの確認
    $db_version = get_option( 'ktpwp_db_version', '0.0.0' );
    if ( $db_version !== '0.0.0' && ! empty( $db_version ) ) {
        set_transient( 'ktpwp_new_installation_check', false, HOUR_IN_SECONDS );
        return false; // 既存環境（DBバージョン設定済み）
    }

    // 全ての条件をクリアした場合は新規インストール
    set_transient( 'ktpwp_new_installation_check', true, HOUR_IN_SECONDS );
    return true;
}

/**
 * 新規インストール検出と自動マイグレーション
 * 配布環境対応の強化版
 */
function ktpwp_detect_new_installation() {
    // 既に検出済みの場合はスキップ
    if ( get_transient( 'ktpwp_new_installation_detected' ) ) {
        return;
    }
    
    $is_new_installation = ktpwp_is_new_installation();
    
    if ( $is_new_installation ) {
        // 新規インストールフラグを設定
        update_option( 'ktpwp_new_installation_detected', true );
        set_transient( 'ktpwp_new_installation_detected', true, DAY_IN_SECONDS );
        
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP: 新規インストールを検出しました' );
        }
        
        // 新規インストール時の基本構造初期化
        try {
            ktpwp_initialize_new_installation();
            
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'KTPWP: 新規インストールの基本構造初期化が完了しました' );
            }
        } catch ( Exception $e ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'KTPWP: 新規インストール初期化エラー: ' . $e->getMessage() );
            }
        }
    } else {
        // 既存環境の場合、マイグレーション必要性をチェック
        if ( ktpwp_needs_migration() ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'KTPWP: 既存環境でマイグレーションが必要です' );
            }
            
            // 自動マイグレーションを実行
            try {
                ktpwp_run_auto_migrations();
            } catch ( Exception $e ) {
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( 'KTPWP: 既存環境マイグレーションエラー: ' . $e->getMessage() );
                }
            }
        }
    }
}

/**
 * マイグレーションのバージョン互換性をチェック
 */
function ktpwp_check_migration_compatibility( $filename, $from_version, $to_version ) {
    // 基本的な互換性チェック
    // 必要に応じて詳細なバージョンチェックロジックを追加
    
    // 環境判定（本番/ローカル）
    global $wpdb;
    $is_production = false;
    
    // 本番環境の判定
    $production_order_table = 'top_ktp_order';
    $production_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$production_order_table}'" );
    if ( $production_exists === $production_order_table ) {
        $is_production = true;
    }
    
    // 本番環境専用マイグレーションのチェック
    if ( strpos( $filename, 'production' ) !== false && ! $is_production ) {
        return false;
    }
    
    // ローカル環境専用マイグレーションのチェック
    if ( strpos( $filename, 'local' ) !== false && $is_production ) {
        return false;
    }
    
    return true;
}

/**
 * 致命的なマイグレーションエラーかどうかを判定
 */
function ktpwp_is_critical_migration_error( $exception, $filename ) {
    $critical_patterns = array(
        'table_creation',
        'basic_structure',
        'department_table',
        'qualified_invoice'
    );
    
    foreach ( $critical_patterns as $pattern ) {
        if ( strpos( $filename, $pattern ) !== false ) {
            return true;
        }
    }
    
    return false;
}

/**
 * 適格請求書ナンバー機能のマイグレーションを実行
 */
function ktpwp_run_qualified_invoice_migration() {
    // 適格請求書ナンバー機能のマイグレーションが既に完了しているかチェック
    $migration_completed = get_option( 'ktpwp_qualified_invoice_profit_calculation_migrated', false );
    
    if ( $migration_completed ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP: Qualified invoice profit calculation migration already completed' );
        }
        return true;
    }

    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( 'KTPWP: Starting qualified invoice profit calculation migration' );
    }

    try {
        // マイグレーションファイルを直接実行
        $migration_file = __DIR__ . '/includes/migrations/20250131_add_qualified_invoice_profit_calculation.php';
        
        if ( file_exists( $migration_file ) ) {
            require_once $migration_file;
            
            $class_name = 'KTPWP_Migration_20250131_Add_Qualified_Invoice_Profit_Calculation';
            
            if ( class_exists( $class_name ) && method_exists( $class_name, 'up' ) ) {
                $result = $class_name::up();
                
                if ( $result ) {
                    // マイグレーション完了フラグを設定
                    update_option( 'ktpwp_qualified_invoice_profit_calculation_migrated', true );
                    update_option( 'ktpwp_qualified_invoice_profit_calculation_timestamp', current_time( 'mysql' ) );
                    
                    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                        error_log( 'KTPWP: Successfully completed qualified invoice profit calculation migration' );
                    }
                    return true;
                } else {
                    error_log( 'KTPWP: Failed to execute qualified invoice profit calculation migration' );
                    return false;
                }
            } else {
                error_log( 'KTPWP: Qualified invoice profit calculation migration class not found' );
                return false;
            }
        } else {
            error_log( 'KTPWP: Qualified invoice profit calculation migration file not found: ' . $migration_file );
            return false;
        }
        
    } catch ( Exception $e ) {
        error_log( 'KTPWP Qualified Invoice Migration Error: ' . $e->getMessage() );
        return false;
    }
}

/**
 * コスト項目テーブルに適格請求書番号カラムを追加するマイグレーションを実行
 */
function ktpwp_run_qualified_invoice_number_cost_items_migration() {
    // マイグレーションが既に完了しているかチェック
    $migration_completed = get_option( 'ktpwp_qualified_invoice_number_cost_items_migrated', false );
    
    if ( $migration_completed ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP: Qualified invoice number cost items migration already completed' );
        }
        return true;
    }

    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( 'KTPWP: Starting qualified invoice number cost items migration' );
    }

    try {
        // マイグレーションファイルを直接実行
        $migration_file = __DIR__ . '/includes/migrations/20250131_add_qualified_invoice_number_to_cost_items.php';
        
        if ( file_exists( $migration_file ) ) {
            require_once $migration_file;
            
            $class_name = 'KTPWP_Migration_20250131_Add_Qualified_Invoice_Number_To_Cost_Items';
            
            if ( class_exists( $class_name ) && method_exists( $class_name, 'up' ) ) {
                $result = $class_name::up();
                
                if ( $result ) {
                    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                        error_log( 'KTPWP: Successfully completed qualified invoice number cost items migration' );
                    }
                    return true;
                } else {
                    error_log( 'KTPWP: Failed to execute qualified invoice number cost items migration' );
                    return false;
                }
            } else {
                error_log( 'KTPWP: Qualified invoice number cost items migration class not found' );
                return false;
            }
        } else {
            error_log( 'KTPWP: Qualified invoice number cost items migration file not found: ' . $migration_file );
            return false;
        }
        
    } catch ( Exception $e ) {
        error_log( 'KTPWP Qualified Invoice Number Cost Items Migration Error: ' . $e->getMessage() );
        return false;
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

// === 配布環境対応の自動マイグレーション機能 ===

// プラグイン有効化時の自動マイグレーション
add_action( 'plugins_loaded', 'ktpwp_run_auto_migrations', 8 );

// プラグイン再有効化時の自動マイグレーション
add_action( 'admin_init', 'ktpwp_check_reactivation_migration' );

// プラグイン更新時の自動マイグレーション
add_action( 'upgrader_process_complete', 'ktpwp_plugin_upgrade_migration', 10, 2 );

// 新規インストール検出と自動マイグレーション
add_action( 'admin_init', 'ktpwp_detect_new_installation' );

// データベース整合性チェック（定期的実行）
add_action( 'admin_init', 'ktpwp_check_database_integrity' );

// データベースバージョン同期（既存インストール対応）
add_action( 'admin_init', 'ktpwp_sync_database_version' );

// 利用規約同意チェック（管理画面でのみ実行 - パフォーマンス最適化）
if ( is_admin() ) {
    add_action( 'admin_init', 'ktpwp_check_terms_agreement' );
}

// 配布用の追加安全チェック
add_action( 'init', 'ktpwp_distribution_safety_check', 1 );

// プラグイン無効化時の処理
register_deactivation_hook( KANTANPRO_PLUGIN_FILE, 'ktpwp_plugin_deactivation' );

/**
 * プラグイン有効化時の処理（改善版）
 */
function ktpwp_plugin_activation() {
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( 'KTPWP: プラグイン有効化処理を開始' );
    }

    try {
        // 配布環境での誤ったエラー表示を防ぐため、既存のマイグレーションエラーをクリア
        delete_option( 'ktpwp_migration_error' );
        delete_option( 'ktpwp_migration_error_timestamp' );
        
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP: プラグイン有効化時に既存のマイグレーションエラーをクリアしました' );
        }
        
        // 自動マイグレーションを実行
        if ( function_exists('ktpwp_run_auto_migrations') ) {
            ktpwp_run_auto_migrations();
        }
        
        // 有効化完了フラグを設定
        update_option( 'ktpwp_activation_completed', true );
        update_option( 'ktpwp_activation_timestamp', current_time( 'mysql' ) );
        
        // リダイレクトフラグを設定
        add_option( 'ktpwp_activation_redirect', true );
        
        // 有効化完了通知を設定
        set_transient( 'ktpwp_activation_message', 'KantanProプラグインが正常に有効化されました。すべての機能が利用可能です。', 60 );
        
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP: プラグイン有効化処理が正常に完了' );
        }
        
    } catch ( Exception $e ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP: プラグイン有効化処理でエラーが発生: ' . $e->getMessage() );
        }
        
        // エラーが発生した場合でも基本的な設定は保存
        update_option( 'ktpwp_version', KANTANPRO_PLUGIN_VERSION );
        update_option( 'ktpwp_db_version', KANTANPRO_PLUGIN_VERSION );
        
        // エラー通知を設定
        set_transient( 'ktpwp_activation_error', 'プラグインの有効化中にエラーが発生しました。プラグインを再有効化してください。', 60 );
    }
}

/**
 * プラグイン無効化時の処理
 */
function ktpwp_plugin_deactivation() {
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( 'KTPWP: プラグイン無効化処理を開始' );
    }

    try {
        // 一時ファイルのクリーンアップをスケジュール
        if ( function_exists('ktpwp_unschedule_temp_file_cleanup') ) {
            ktpwp_unschedule_temp_file_cleanup();
        }
        
        // セッション関連のクリーンアップ
        if ( function_exists('ktpwp_safe_session_close') ) {
            ktpwp_safe_session_close();
        }
        
        // 再有効化フラグを設定（プラグイン再有効化時にマイグレーションを実行するため）
        update_option( 'ktpwp_reactivation_required', true );
        
        // 無効化完了フラグを設定
        update_option( 'ktpwp_deactivation_completed', true );
        update_option( 'ktpwp_deactivation_timestamp', current_time( 'mysql' ) );
        
        // 有効化フラグをクリア
        delete_option( 'ktpwp_activation_completed' );
        delete_option( 'ktpwp_activation_redirect' );
        
        // 一時的な通知をクリア
        delete_transient( 'ktpwp_activation_message' );
        delete_transient( 'ktpwp_activation_error' );
        
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP: プラグイン無効化処理が正常に完了（再有効化フラグを設定）' );
        }
        
    } catch ( Exception $e ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP: プラグイン無効化処理でエラーが発生: ' . $e->getMessage() );
        }
    }
}

/**
 * 更新履歴の初期化処理
 */


// プラグイン読み込み時の差分マイグレーション（管理画面またはバージョン変更時のみ）
if ( is_admin() || get_option( 'ktpwp_version', '0' ) !== KANTANPRO_PLUGIN_VERSION ) {
    add_action( 'plugins_loaded', 'ktpwp_check_database_integrity', 5 );
}

// データベースバージョンの同期（管理画面でのみ実行）
if ( is_admin() ) {
    add_action( 'plugins_loaded', 'ktpwp_sync_database_version', 6 );
}

// 利用規約テーブル存在チェック（管理画面でのみ実行）
if ( is_admin() ) {
    add_action( 'plugins_loaded', 'ktpwp_ensure_terms_table', 7 );
}

// プラグイン読み込み時の自動マイグレーション（バージョン変更時のみ）
if ( get_option( 'ktpwp_version', '0' ) !== KANTANPRO_PLUGIN_VERSION ) {
    add_action( 'plugins_loaded', 'ktpwp_run_auto_migrations', 8 );
}

// プラグイン再有効化時の自動マイグレーション
add_action( 'admin_init', 'ktpwp_check_reactivation_migration' );

// プラグイン更新時の自動マイグレーション
add_action( 'upgrader_process_complete', 'ktpwp_plugin_upgrade_migration', 10, 2 );

// 新規インストール検出と自動マイグレーション
add_action( 'admin_init', 'ktpwp_detect_new_installation' );

// 利用規約同意チェック（管理画面でのみ実行 - パフォーマンス最適化）
if ( is_admin() ) {
    add_action( 'admin_init', 'ktpwp_check_terms_agreement' );
}

// 配布用の追加安全チェック
add_action( 'init', 'ktpwp_distribution_safety_check', 1 );

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
 * 配布環境対応の強化版
 */
function ktpwp_plugin_upgrade_migration( $upgrader, $hook_extra ) {
    // KantanProプラグインの更新かどうかをチェック
    if ( ! isset( $hook_extra['plugin'] ) || strpos( $hook_extra['plugin'], 'ktpwp.php' ) === false ) {
        return;
    }

    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( 'KTPWP: Plugin upgrade detected, running enhanced migration' );
    }

    try {
        // 更新前のバージョンを保存
        $old_version = get_option( 'ktpwp_version', '0.0.0' );
        update_option( 'ktpwp_previous_version', $old_version );
        
        // 配布環境での安全性チェック
        if ( ! ktpwp_verify_migration_safety() ) {
            throw new Exception( 'アップグレード時のマイグレーション安全性チェックに失敗しました' );
        }
        
        // 新規インストール判定
        $is_new_installation = ktpwp_is_new_installation();
        
        if ( $is_new_installation ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'KTPWP: アップグレード時に新規インストールを検出 - 基本構造のみで初期化' );
            }
            
            // 新規インストール時は基本構造のみで初期化
            ktpwp_initialize_new_installation();
        } else {
            // 既存環境での段階的マイグレーション
            $current_db_version = get_option( 'ktpwp_db_version', '0.0.0' );
            $plugin_version = KANTANPRO_PLUGIN_VERSION;
            
            ktpwp_run_staged_migrations( $current_db_version, $plugin_version );
        }
        
        // 適格請求書ナンバー機能のマイグレーション（確実に実行）
        ktpwp_safe_run_qualified_invoice_migration();
        
        // データベース整合性の最終チェック
        if ( ! ktpwp_verify_database_integrity() ) {
            throw new Exception( 'アップグレード後のデータベース整合性チェックに失敗しました' );
        }
        
        // 更新完了フラグを設定
        update_option( 'ktpwp_upgrade_completed', true );
        update_option( 'ktpwp_upgrade_timestamp', current_time( 'mysql' ) );
        update_option( 'ktpwp_upgrade_success_count', get_option( 'ktpwp_upgrade_success_count', 0 ) + 1 );
        
        // アップデート通知を設定
        set_transient( 'ktpwp_upgrade_message', 'KantanProプラグインが正常に更新されました。適格請求書ナンバー機能も含まれています。', 60 );

        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP: Plugin upgrade migration completed successfully' );
        }
        
    } catch ( Exception $e ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP: Plugin upgrade migration failed: ' . $e->getMessage() );
        }
        
        // エラー情報を詳細に記録
        update_option( 'ktpwp_upgrade_error', $e->getMessage() );
        update_option( 'ktpwp_upgrade_error_timestamp', current_time( 'mysql' ) );
        update_option( 'ktpwp_upgrade_error_count', get_option( 'ktpwp_upgrade_error_count', 0 ) + 1 );
        
        // エラー通知を設定
        set_transient( 'ktpwp_upgrade_error', 'プラグインの更新中にエラーが発生しました。詳細はログを確認してください。', 60 );
    }
}

/**
 * マイグレーション状態をチェックする関数
 */
function ktpwp_check_migration_status() {
    $current_db_version = get_option( 'ktpwp_db_version', '0.0.0' );
    $plugin_version = KANTANPRO_PLUGIN_VERSION;
    
    // 新規インストールの場合、データベースバージョンを更新
    if ( $current_db_version === '0.0.0' || empty( $current_db_version ) ) {
        update_option( 'ktpwp_db_version', $plugin_version );
        $current_db_version = $plugin_version;
    }
    
    // バージョンが同じ場合は更新不要
    $needs_migration = false;
    if ( $current_db_version !== $plugin_version ) {
        $needs_migration = version_compare( $current_db_version, $plugin_version, '<' );
    }
    
    // マイグレーションエラーの確認と配布環境での修正
    $migration_error = get_option( 'ktpwp_migration_error', null );
    
    // 配布環境での誤ったエラー表示を防ぐためのチェック
    if ( $migration_error ) {
        // マイグレーションが実際に成功している場合はエラーをクリア
        $migration_success_count = get_option( 'ktpwp_migration_success_count', 0 );
        $last_migration_timestamp = get_option( 'ktpwp_last_migration_timestamp', '' );
        $migration_in_progress = get_option( 'ktpwp_migration_in_progress', false );
        
        // エンドユーザー向けの自動クリア条件（より積極的）
        $should_clear_error = false;
        
        // 条件1: マイグレーションが進行中でない、かつ成功回数が1以上
        if ( ! $migration_in_progress && $migration_success_count > 0 ) {
            $should_clear_error = true;
        }
        
        // 条件2: 最終マイグレーションが最近（30分以内）の場合
        if ( ! empty( $last_migration_timestamp ) ) {
            $last_migration_time = strtotime( $last_migration_timestamp );
            $current_time = current_time( 'timestamp' );
            
            if ( $current_time - $last_migration_time <= 1800 ) { // 30分 = 1800秒
                $should_clear_error = true;
            }
        }
        
        // 条件3: データベースバージョンが最新の場合
        if ( $current_db_version === $plugin_version ) {
            $should_clear_error = true;
        }
        
        // 条件4: エラーが古い場合（24時間以上経過）
        $error_timestamp = get_option( 'ktpwp_migration_error_timestamp', '' );
        if ( ! empty( $error_timestamp ) ) {
            $error_time = strtotime( $error_timestamp );
            $current_time = current_time( 'timestamp' );
            
            if ( $current_time - $error_time > 86400 ) { // 24時間 = 86400秒
                $should_clear_error = true;
            }
        }
        
        // エラーをクリア
        if ( $should_clear_error ) {
            delete_option( 'ktpwp_migration_error' );
            delete_option( 'ktpwp_migration_error_timestamp' );
            $migration_error = null;
            
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'KTPWP: エンドユーザー向けにマイグレーションエラーを自動クリアしました' );
            }
        }
    }
    
    // 適格請求書ナンバー機能の状態をチェック
    $qualified_invoice_migrated = get_option( 'ktpwp_qualified_invoice_profit_calculation_migrated', false );
    $qualified_invoice_version = get_option( 'ktpwp_qualified_invoice_profit_calculation_version', '0.0.0' );
    $qualified_invoice_enabled = get_option( 'ktpwp_qualified_invoice_enabled', false );
    
    $status = array(
        'current_db_version' => $current_db_version,
        'plugin_version' => $plugin_version,
        'needs_migration' => $needs_migration,
        'last_migration' => get_option( 'ktpwp_last_migration_timestamp', 'Never' ),
        'activation_completed' => get_option( 'ktpwp_activation_completed', false ),
        'upgrade_completed' => get_option( 'ktpwp_upgrade_completed', false ),
        'reactivation_completed' => get_option( 'ktpwp_reactivation_completed', false ),
        'new_installation_completed' => get_option( 'ktpwp_new_installation_completed', false ),
        'migration_error' => $migration_error,
        'qualified_invoice' => array(
            'migrated' => $qualified_invoice_migrated,
            'version' => $qualified_invoice_version,
            'enabled' => $qualified_invoice_enabled,
            'timestamp' => get_option( 'ktpwp_qualified_invoice_profit_calculation_timestamp', 'Never' )
        )
    );
    
    return $status;
}

/**
 * 管理画面でのマイグレーション状態表示
 */
function ktpwp_admin_migration_status() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    
    $status = ktpwp_check_migration_status();
    
    // 配布環境での誤った通知表示を防ぐための追加チェック
    $current_db_version = get_option( 'ktpwp_db_version', '0.0.0' );
    $plugin_version = KANTANPRO_PLUGIN_VERSION;
    
    // バージョンが実際に一致している場合は通知を表示しない
    if ( $current_db_version === $plugin_version ) {
        return;
    }
    
    if ( $status['needs_migration'] ) {
        $message = sprintf(
            'データベースの更新が必要です。現在のバージョン: %s、プラグインバージョン: %s',
            $status['current_db_version'],
            $status['plugin_version']
        );
        
        echo '<div class="notice notice-warning is-dismissible">';
        echo '<p><strong>KantanPro:</strong> ' . esc_html( $message ) . '</p>';
        echo '<p><button type="button" class="button button-primary" id="ktpwp-manual-db-update">今すぐ更新</button></p>';
        echo '</div>';
        
        // JavaScript for manual database update
        ?>
        <script>
        jQuery(document).ready(function($) {
            $('#ktpwp-manual-db-update').on('click', function() {
                var $button = $(this);
                var originalText = $button.text();
                
                $button.text('更新中...').prop('disabled', true);
                
                $.post(ajaxurl, {
                    action: 'ktpwp_manual_db_update',
                    nonce: '<?php echo wp_create_nonce( 'ktpwp_manual_db_update' ); ?>'
                }, function(response) {
                    if (response.success) {
                        $button.text('更新完了').removeClass('button-primary').addClass('button-secondary');
                        setTimeout(function() {
                            window.location.reload();
                        }, 2000);
                    } else {
                        alert('更新に失敗しました: ' + (response.data || '不明なエラー'));
                        $button.text(originalText).prop('disabled', false);
                    }
                }).fail(function() {
                    alert('更新に失敗しました。ネットワークエラーが発生しました。');
                    $button.text(originalText).prop('disabled', false);
                });
            });
        });
        </script>
        <?php
    }
    
    // 適格請求書ナンバー機能の状態表示
    $qualified_invoice = $status['qualified_invoice'];
    if ( ! $qualified_invoice['migrated'] ) {
        echo '<div class="notice notice-warning is-dismissible">';
        echo '<p><strong>KantanPro:</strong> 適格請求書ナンバー機能のマイグレーションが必要です。プラグインを再有効化してください。</p>';
        echo '<p><button type="button" class="button button-primary" id="ktpwp-run-qualified-invoice-migration">適格請求書機能を有効化</button></p>';
        echo '</div>';
        
        // JavaScript for qualified invoice migration
        ?>
        <script>
        jQuery(document).ready(function($) {
            $('#ktpwp-run-qualified-invoice-migration').on('click', function() {
                var $button = $(this);
                var originalText = $button.text();
                
                $button.text('有効化中...').prop('disabled', true);
                
                $.post(ajaxurl, {
                    action: 'ktpwp_run_qualified_invoice_migration',
                    nonce: '<?php echo wp_create_nonce( 'ktpwp_run_qualified_invoice_migration' ); ?>'
                }, function(response) {
                    if (response.success) {
                        $button.text('有効化完了').removeClass('button-primary').addClass('button-secondary');
                        setTimeout(function() {
                            window.location.reload();
                        }, 2000);
                    } else {
                        alert('有効化に失敗しました: ' + (response.data || '不明なエラー'));
                        $button.text(originalText).prop('disabled', false);
                    }
                }).fail(function() {
                    alert('有効化に失敗しました。ネットワークエラーが発生しました。');
                    $button.text(originalText).prop('disabled', false);
                });
            });
        });
        </script>
        <?php
    }
}

// 管理画面でのマイグレーション状態表示（KantanPro設定ページでのみ実行）
if ( is_admin() && isset( $_GET['page'] ) && strpos( $_GET['page'], 'ktp-' ) === 0 ) {
    add_action( 'admin_notices', 'ktpwp_admin_migration_status' );
    
    // エンドユーザー向け: 管理画面アクセス時に自動的にエラーをクリア
    add_action( 'admin_init', 'ktpwp_auto_clear_migration_error_for_end_users' );
}

// 手動データベース更新のAJAXハンドラー
add_action( 'wp_ajax_ktpwp_manual_db_update', 'ktpwp_handle_manual_db_update' );
add_action( 'wp_ajax_ktpwp_run_qualified_invoice_migration', 'ktpwp_handle_qualified_invoice_migration' );

/**
 * 手動データベース更新のAJAXハンドラー
 */
function ktpwp_handle_manual_db_update() {
    // セキュリティチェック
    if ( ! wp_verify_nonce( $_POST['nonce'], 'ktpwp_manual_db_update' ) ) {
        wp_send_json_error( 'セキュリティチェックに失敗しました。' );
    }
    
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'この操作を実行する権限がありません。' );
    }
    
    try {
        // 現在のバージョン情報を取得
        $current_db_version = get_option( 'ktpwp_db_version', '0.0.0' );
        $plugin_version = KANTANPRO_PLUGIN_VERSION;
        
        // マイグレーション進行中フラグをクリア（手動更新のため）
        delete_option( 'ktpwp_migration_in_progress' );
        
        // マイグレーション実行
        ktpwp_run_auto_migrations();
        
        // 適格請求書ナンバー機能のマイグレーション（確実に実行）
        if ( function_exists('ktpwp_run_qualified_invoice_migration') ) {
            ktpwp_run_qualified_invoice_migration();
        }
        
        // データベースバージョンを強制的に更新
        update_option( 'ktpwp_db_version', $plugin_version );
        update_option( 'ktpwp_last_migration_timestamp', current_time( 'mysql' ) );
        update_option( 'ktpwp_migration_success_count', get_option( 'ktpwp_migration_success_count', 0 ) + 1 );
        
        // マイグレーションエラーをクリア
        delete_option( 'ktpwp_migration_error' );
        delete_option( 'ktpwp_migration_error_timestamp' );
        
        // バージョン同期の確認
        $updated_db_version = get_option( 'ktpwp_db_version', '0.0.0' );
        if ( $updated_db_version !== $plugin_version ) {
            // 強制的に再設定
            update_option( 'ktpwp_db_version', $plugin_version );
        }
        
        // キャッシュをクリア
        wp_cache_flush();
        
        wp_send_json_success( 'データベースの更新が完了しました。' );
        
    } catch ( Exception $e ) {
        wp_send_json_error( '更新に失敗しました: ' . $e->getMessage() );
    }
}

/**
 * 適格請求書ナンバー機能のマイグレーションを実行するAJAXハンドラー
 */
function ktpwp_handle_qualified_invoice_migration() {
    // セキュリティチェック
    if ( ! wp_verify_nonce( $_POST['nonce'], 'ktpwp_run_qualified_invoice_migration' ) ) {
        wp_send_json_error( 'セキュリティチェックに失敗しました。' );
    }
    
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'この操作を実行する権限がありません。' );
    }
    
    try {
        // 適格請求書ナンバー機能のマイグレーションを実行
        if ( function_exists('ktpwp_run_qualified_invoice_migration') ) {
            $result = ktpwp_run_qualified_invoice_migration();
            
            if ( $result ) {
                wp_send_json_success( '適格請求書ナンバー機能の有効化が完了しました。' );
            } else {
                wp_send_json_error( '適格請求書ナンバー機能の有効化に失敗しました。' );
            }
        } else {
            wp_send_json_error( '適格請求書ナンバー機能のマイグレーション関数が見つかりません。' );
        }
        
    } catch ( Exception $e ) {
        wp_send_json_error( '有効化に失敗しました: ' . $e->getMessage() );
    }
}

/**
 * エンドユーザー向け: 管理画面アクセス時に自動的にマイグレーションエラーをクリア
 */
function ktpwp_auto_clear_migration_error_for_end_users() {
    // マイグレーションエラーが存在する場合
    $migration_error = get_option( 'ktpwp_migration_error', null );
    if ( $migration_error ) {
        // データベースバージョンが最新の場合、エラーを自動クリア
        $current_db_version = get_option( 'ktpwp_db_version', '0.0.0' );
        $plugin_version = KANTANPRO_PLUGIN_VERSION;
        
        if ( $current_db_version === $plugin_version ) {
            delete_option( 'ktpwp_migration_error' );
            delete_option( 'ktpwp_migration_error_timestamp' );
            
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'KTPWP: 管理画面アクセス時にマイグレーションエラーを自動クリアしました' );
            }
        }
    }
}

/**
 * 管理画面での通知表示
 */
function ktpwp_admin_notices() {
    // 有効化完了通知
    if ( get_transient( 'ktpwp_activation_message' ) ) {
        echo '<div class="notice notice-success is-dismissible">';
        echo '<p><strong>KantanPro:</strong> ' . esc_html( get_transient( 'ktpwp_activation_message' ) ) . '</p>';
        echo '</div>';
    }
    
    // 有効化エラー通知
    if ( get_transient( 'ktpwp_activation_error' ) ) {
        echo '<div class="notice notice-error is-dismissible">';
        echo '<p><strong>KantanPro:</strong> ' . esc_html( get_transient( 'ktpwp_activation_error' ) ) . '</p>';
        echo '</div>';
    }
    
    // 新規インストール完了通知
    if ( get_transient( 'ktpwp_new_installation_message' ) ) {
        echo '<div class="notice notice-success is-dismissible">';
        echo '<p><strong>KantanPro:</strong> ' . esc_html( get_transient( 'ktpwp_new_installation_message' ) ) . '</p>';
        echo '</div>';
    }
    
    // 新規インストールエラー通知
    if ( get_transient( 'ktpwp_new_installation_error' ) ) {
        echo '<div class="notice notice-error is-dismissible">';
        echo '<p><strong>KantanPro:</strong> ' . esc_html( get_transient( 'ktpwp_new_installation_error' ) ) . '</p>';
        echo '</div>';
    }
    
    // 再有効化完了通知
    if ( get_transient( 'ktpwp_reactivation_message' ) ) {
        echo '<div class="notice notice-success is-dismissible">';
        echo '<p><strong>KantanPro:</strong> ' . esc_html( get_transient( 'ktpwp_reactivation_message' ) ) . '</p>';
        echo '</div>';
    }
    
    // 再有効化エラー通知
    if ( get_transient( 'ktpwp_reactivation_error' ) ) {
        echo '<div class="notice notice-error is-dismissible">';
        echo '<p><strong>KantanPro:</strong> ' . esc_html( get_transient( 'ktpwp_reactivation_error' ) ) . '</p>';
        echo '</div>';
    }
    
    // アップデート完了通知
    if ( get_transient( 'ktpwp_upgrade_message' ) ) {
        echo '<div class="notice notice-success is-dismissible">';
        echo '<p><strong>KantanPro:</strong> ' . esc_html( get_transient( 'ktpwp_upgrade_message' ) ) . '</p>';
        echo '</div>';
    }
    
    // アップデートエラー通知
    if ( get_transient( 'ktpwp_upgrade_error' ) ) {
        echo '<div class="notice notice-error is-dismissible">';
        echo '<p><strong>KantanPro:</strong> ' . esc_html( get_transient( 'ktpwp_upgrade_error' ) ) . '</p>';
        echo '</div>';
    }
}

// 管理画面での通知表示（管理画面でのみ実行）
if ( is_admin() ) {
    add_action( 'admin_notices', 'ktpwp_admin_notices' );
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
    $order_table = $wpdb->prefix . 'ktp_order';
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

/**
 * データベースバージョンの同期（既存インストール対応）
 */
function ktpwp_sync_database_version() {
    // 既に同期済みの場合はスキップ
    if ( get_transient( 'ktpwp_db_version_synced' ) ) {
        return;
    }

    $current_db_version = get_option( 'ktpwp_db_version', '0.0.0' );
    $plugin_version = KANTANPRO_PLUGIN_VERSION;

    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( 'KTPWP: Syncing database version. Current DB version: ' . $current_db_version . ', Plugin version: ' . $plugin_version );
    }

    // データベースバージョンが設定されていない場合、プラグインバージョンに同期
    if ( $current_db_version === '0.0.0' || empty( $current_db_version ) ) {
        // 既存のテーブルが存在するかチェック
        global $wpdb;
        $main_table = $wpdb->prefix . 'ktp_order';
        $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$main_table'" );

        if ( $table_exists ) {
            // テーブルが存在する場合、既存インストールと判断してバージョンを同期
            update_option( 'ktpwp_db_version', $plugin_version );
            
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'KTPWP: Database version synchronized to plugin version: ' . $plugin_version );
            }
        } else {
            // テーブルが存在しない場合、新規インストール
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'KTPWP: New installation detected, database version will be set during migration' );
            }
        }
    } else {
        // データベースバージョンが設定されている場合、比較チェック
        if ( version_compare( $current_db_version, $plugin_version, '>' ) ) {
            // データベースバージョンがプラグインバージョンより新しい場合、警告ログ
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'KTPWP: Warning - Database version (' . $current_db_version . ') is newer than plugin version (' . $plugin_version . ')' );
            }
        }
    }

    // 同期完了フラグを設定（1時間有効）
    set_transient( 'ktpwp_db_version_synced', true, HOUR_IN_SECONDS );
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

// プラグイン読み込み時にログディレクトリを設定（デバッグ時のみ）
if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
    add_action( 'plugins_loaded', 'ktpwp_setup_safe_logging', 1 );
}

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

// プラグイン読み込み時にREST API制限を一時的に無効化（デバッグ時のみ）
if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
    add_action( 'plugins_loaded', 'ktpwp_disable_rest_api_restriction_during_init', 1 );
}

// メインクラスの初期化はinit以降に遅延（翻訳エラー防止）
// KTPWP_Mainクラスの初期化（一度だけ実行）
add_action(
    'init',
    function () {
		if ( class_exists( 'KTPWP_Main' ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'KTPWP Plugin: KTPWP_Main class found, initializing on init hook...' );
			}
			KTPWP_Main::get_instance();
		} elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP Plugin: KTPWP_Main class not found on init hook' );
		}
	},
    10
); // Run after init

// Contact Form 7連携クラスも必ず初期化
add_action(
    'init',
    function () {
		if ( class_exists( 'KTPWP_Contact_Form' ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'KTPWP Plugin: KTPWP_Contact_Form class found, initializing...' );
			}
			KTPWP_Contact_Form::get_instance();
		} elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP Plugin: KTPWP_Contact_Form class not found' );
		}
	},
    20
); // Run after KTPWP_Main initialization

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

// 包括的アクティベーションで処理されるため、個別のフックは不要
// register_activation_hook( KANTANPRO_PLUGIN_FILE, array( 'KTP_Settings', 'activate' ) );



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
        // レポートタブ用のスタイルシートを追加
        wp_enqueue_style( 'ktp-report', plugins_url( 'css/ktp-report.css', __FILE__ ) . '?v=' . time(), array( 'ktp-css' ), KANTANPRO_PLUGIN_VERSION, 'all' );
    }

    // Material Symbolsを無効化し、SVGアイコンに置き換え
    // wp_enqueue_style( 'ktpwp-material-icons', 'https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0', array(), null );

    // Google Fontsのプリロード設定も無効化
    // add_action(
    //     'wp_head',
    //     function () {
    //         echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
    //         echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
    //         echo '<link rel="preload" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" as="style" onload="this.onload=null;this.rel=\'stylesheet\'">' . "\n";
    //     },
    //     1
    // );
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

    // Ajax nonceを追加 - AJAXクラスで管理されるため、ここでは設定しない
    // wp_add_inline_script( 'ktp-invoice-items', 'var ktp_ajax_nonce = ' . json_encode( wp_create_nonce( 'ktp_ajax_nonce' ) ) . ';' );
    // wp_add_inline_script( 'ktp-cost-items', 'var ktp_ajax_nonce = ' . json_encode( wp_create_nonce( 'ktp_ajax_nonce' ) ) . ';' );

    // ajaxurlをJavaScriptで利用可能にする - AJAXクラスで管理されるため、ここでは設定しない
    // wp_add_inline_script( 'ktp-invoice-items', 'var ajaxurl = ' . json_encode( admin_url( 'admin-ajax.php' ) ) . ';' );
    // wp_add_inline_script( 'ktp-cost-items', 'var ajaxurl = ' . json_encode( admin_url( 'admin-ajax.php' ) ) . ';' );

    // デバッグモードでAJAXデバッグスクリプトを読み込み（ファイルが存在する場合のみ）
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG && file_exists( plugin_dir_path( __FILE__ ) . 'debug-ajax.js' ) ) {
        wp_enqueue_script(
            'ktp-ajax-debug',
            plugins_url( 'debug-ajax.js', __FILE__ ),
            array( 'jquery' ),
            KANTANPRO_PLUGIN_VERSION,
            true
        );
        
        // デバッグ用nonce をスクリプトに渡す
        wp_localize_script( 'ktp-ajax-debug', 'ktp_ajax_debug', array(
            'nonce' => wp_create_nonce( 'ktp_ajax_debug_nonce' ),
            'ajaxurl' => admin_url( 'admin-ajax.php' )
        ) );
        
        // PHP側のAJAXデバッグハンドラーを読み込み（デバッグファイルが存在する場合のみ）
        if ( file_exists( KANTANPRO_PLUGIN_DIR . 'debug-ajax-handler.php' ) ) {
            require_once KANTANPRO_PLUGIN_DIR . 'debug-ajax-handler.php';
        }
    }

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
    // ダミーデータ作成AJAXハンドラー
    if (function_exists('ktpwp_handle_create_dummy_data_ajax')) {
        add_action( 'wp_ajax_ktpwp_create_dummy_data', 'ktpwp_handle_create_dummy_data_ajax' );
    } else {
        error_log('KTPWP: ktpwp_handle_create_dummy_data_ajax function not found');
    }
    
    // 協力会社関連AJAXハンドラー（ajax-supplier-cost.phpで定義済み）
    // これらのハンドラーはajax-supplier-cost.phpファイルで直接add_actionされているため、
    // ここでの追加は不要です。
    
    // データクリアAJAXハンドラー
    if (function_exists('ktpwp_handle_clear_data_ajax')) {
        add_action( 'wp_ajax_ktpwp_clear_data', 'ktpwp_handle_clear_data_ajax' );
    } else {
        error_log('KTPWP: ktpwp_handle_clear_data_ajax function not found');
    }
    
    // テスト用AJAXハンドラー
    add_action( 'wp_ajax_ktpwp_test_ajax', 'ktpwp_test_ajax_handler' );
    
    // 通知非表示AJAXハンドラー
    add_action( 'wp_ajax_ktpwp_dismiss_invoice_items_fix_notification', 'ktpwp_dismiss_invoice_items_fix_notification' );
}
add_action( 'init', 'ktpwp_init_ajax_handlers' );

function ktp_table_setup() {
    // 出力バッファリングを開始（予期しない出力を防ぐ）
    ob_start();

    // dbDelta関数を確実に利用可能にする
    if ( ! function_exists( 'dbDelta' ) ) {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    }

    $results = [];
    $table_classes = [
        'client'         => 'KTPWP_Client_DB',
        'service'        => 'KTPWP_Service_DB',
        'supplier'       => 'KTPWP_Supplier_Data',
        'order'          => 'KTPWP_Order',
        'order_items'    => 'KTPWP_Order_Items',
        'staff_chat'     => 'KTPWP_Staff_Chat',
    ];

    foreach ( $table_classes as $slug => $class_name ) {
        if ( class_exists( $class_name ) ) {
            $instance = null;
            // シングルトンと通常のインスタンス化に対応
            if ( method_exists( $class_name, 'get_instance' ) ) {
                $instance = $class_name::get_instance();
            } else {
                $instance = new $class_name();
            }

            if ( method_exists( $instance, 'get_schema' ) ) {
                $schema = $instance->get_schema();
                if ( ! empty( $schema ) ) {
                    $results[ $class_name ] = dbDelta( $schema );
                }
            }
        }
    }

    // dbDeltaの実行結果をログに出力
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        if ( ! empty( $results ) ) {
            error_log( "KTPWP: dbDelta execution results: " . print_r( $results, true ) );
        }
    }

    // 出力バッファをクリア（予期しない出力を除去）
    $output = ob_get_clean();

    // デバッグ時のみ、予期しない出力があればログに記録
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG && ! empty( $output ) ) {
        error_log( 'KTPWP: ktp_table_setup中に予期しない出力を検出: ' . substr( $output, 0, 1000 ) );
    }
}
// 包括的アクティベーションで処理されるため、個別のフックは不要
// register_activation_hook( KANTANPRO_PLUGIN_FILE, 'ktp_table_setup' ); // テーブル作成処理
// register_activation_hook( KANTANPRO_PLUGIN_FILE, array( 'KTP_Settings', 'activate' ) ); // 設定クラスのアクティベート処理
// register_activation_hook( KANTANPRO_PLUGIN_FILE, array( 'KTPWP_Plugin_Reference', 'on_plugin_activation' ) ); // プラグインリファレンス更新処理

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
            $update_link_url = esc_url( KTPWP_Main::get_current_page_base_url() );

            // ログインしているユーザーのみにナビゲーションリンクを表示
            $navigation_links = '';
            if ( is_user_logged_in() && $current_user && $current_user->ID > 0 ) {
                // セッションの有効性も確認
                $user_sessions = WP_Session_Tokens::get_instance( $current_user->ID );
                if ( $user_sessions && ! empty( $user_sessions->get_all() ) ) {
                    // 寄付ボタンを最初に追加（常時表示）
                    $donation_settings = get_option( 'ktp_donation_settings', array() );
                    $donation_url = ! empty( $donation_settings['donation_url'] ) ? esc_url( $donation_settings['donation_url'] ) : 'https://www.kantanpro.com/donation';
                    // 管理者情報を取得
                    $admin_email = get_option( 'admin_email' );
                    $admin_name = get_option( 'blogname' );
                    // POSTパラメータを追加
                    $donation_url_with_params = add_query_arg( array(
                        'admin_email' => urlencode( $admin_email ),
                        'admin_name' => urlencode( $admin_name )
                    ), $donation_url );
                    $navigation_links .= ' <a href="' . $donation_url_with_params . '" target="_blank" rel="noopener noreferrer" title="寄付する" style="display: inline-flex; align-items: center; gap: 4px; color: #0073aa; text-decoration: none;"><span class="material-symbols-outlined" style="font-size: 20px; vertical-align: middle;">favorite</span><span>寄付する</span></a>';
                    // ログアウトボタン
                    $navigation_links .= ' <a href="' . $logout_link . '" title="ログアウト" style="display: inline-flex; align-items: center; gap: 4px; color: #0073aa; text-decoration: none;"><span class="material-symbols-outlined" style="font-size: 20px; vertical-align: middle;">logout</span></a>';
                    // 更新リンクは編集者権限がある場合のみ
                    if ( current_user_can( 'edit_posts' ) ) {
                        // 更新通知設定を確認
                        $update_settings = get_option( 'ktp_update_notification_settings', array() );
                        $enable_notifications = isset( $update_settings['enable_notifications'] ) ? $update_settings['enable_notifications'] : true;
                        if ( $enable_notifications ) {
                            // 更新通知機能付きのリンク
                            $navigation_links .= ' <a href="#" id="ktpwp-header-update-check" title="更新チェック" style="display: inline-flex; align-items: center; gap: 4px; color: #0073aa; text-decoration: none; cursor: pointer;"><span class="material-symbols-outlined" style="font-size: 20px; vertical-align: middle;">refresh</span></a>';
                        } else {
                            // 通常のページリロードリンク
                            $navigation_links .= ' <a href="' . $update_link_url . '" title="更新" style="display: inline-flex; align-items: center; gap: 4px; color: #0073aa; text-decoration: none;"><span class="material-symbols-outlined" style="font-size: 20px; vertical-align: middle;">refresh</span></a>';
                        }
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

            // 更新通知設定を確認
            $update_settings = get_option( 'ktp_update_notification_settings', array() );
            $enable_notifications = isset( $update_settings['enable_notifications'] ) ? $update_settings['enable_notifications'] : true;
            
            // 更新情報を取得
            $update_data = null;
            if ( $enable_notifications && class_exists( 'KTPWP_Update_Checker' ) ) {
                global $ktpwp_update_checker;
                if ( $ktpwp_update_checker ) {
                    $update_data = $ktpwp_update_checker->check_header_update();
                }
            }
            
            // SVGアイコンに置換
            if (class_exists('KTPWP_SVG_Icons')) {
                $navigation_links = KTPWP_SVG_Icons::replace_material_symbols($navigation_links);
            }
            
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
            
            // 更新通知用のスクリプトとスタイルを追加（常に読み込み）
            $front_message .= '<link rel="stylesheet" href="' . esc_url( plugins_url( 'css/ktpwp-update-balloon.css', __FILE__ ) ) . '?v=' . KANTANPRO_PLUGIN_VERSION . '">';
            
            // AJAX用の変数を設定（常に設定）- JavaScriptファイルの読み込み前に設定
            $ajax_data = array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce' => wp_create_nonce( 'ktpwp_header_update_check' ),
                'dismiss_nonce' => wp_create_nonce( 'ktpwp_header_update_notice' ),
                'admin_url' => admin_url(),
                'notifications_enabled' => $enable_notifications
            );
            
            // デバッグ用のログを追加
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'KantanPro: JavaScript変数設定 - nonce: ' . $ajax_data['nonce'] );
                error_log( 'KantanPro: JavaScript変数設定 - notifications_enabled: ' . ( $enable_notifications ? 'true' : 'false' ) );
                error_log( 'KantanPro: JavaScript変数設定 - ajax_url: ' . $ajax_data['ajax_url'] );
                error_log( 'KantanPro: JavaScript変数設定 - admin_url: ' . $ajax_data['admin_url'] );
            }
            
            $front_message .= '<script>var ktpwp_update_ajax = ' . wp_json_encode( $ajax_data ) . ';</script>';
            
            // デバッグ用のHTMLコメントを追加
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                $front_message .= '<!-- KantanPro Debug: JavaScript variables set -->';
            }
            
            // 更新情報をJavaScript変数として設定
            if ( $update_data ) {
                $front_message .= '<script>var ktpwp_update_data = ' . wp_json_encode( array(
                    'has_update' => true,
                    'message' => '新しいバージョンが利用可能です！',
                    'update_data' => $update_data
                ) ) . ';</script>';
            }
            
            // JavaScriptファイルを読み込み
            $front_message .= '<script src="' . esc_url( plugins_url( 'js/ktpwp-update-balloon.js', __FILE__ ) ) . '?v=' . KANTANPRO_PLUGIN_VERSION . '"></script>';
            
            // デバッグ用のHTMLコメントを追加
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                $front_message .= '<!-- KantanPro Debug: JavaScript file loaded -->';
            }
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
                    $order = new Kantan_Order_Class();
                    $order_content = $order->Order_Tab_View( $tab_name );
                    $order_content = $order_content ?? '';
                    break;
                case 'client':
                    $client = new Kantan_Client_Class();
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
                    $service = new Kantan_Service_Class();
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
if ( ! class_exists( 'KTPWP_Report_Class' ) ) {
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

// プラグイン初期化時のマイグレーション
// add_action( 'init', 'ktpwp_ensure_department_migration' );

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

    // 管理者権限チェック（管理者のみに表示）
    if ( ! current_user_can( 'manage_options' ) ) {
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

    // 管理者権限チェック（管理者のみに表示）
    if ( ! current_user_can( 'manage_options' ) ) {
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
 * 配布用安全チェック機能
 */
function ktpwp_distribution_safety_check() {
    // 1時間に1回だけ実行
    if ( get_transient( 'ktpwp_distribution_safety_checked' ) ) {
        return;
    }

    global $wpdb;

    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( 'KTPWP: Running distribution safety check' );
    }

    $issues_found = false;

    // 必須テーブルの存在チェック
    $required_tables = array( 'ktp_order', 'ktp_client', 'ktp_staff', 'ktp_department' );
    foreach ( $required_tables as $table_name ) {
        $full_table_name = $wpdb->prefix . $table_name;
        $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$full_table_name'" );
        if ( ! $table_exists ) {
            $issues_found = true;
            break;
        }
    }

    // バージョン同期チェック
    $current_db_version = get_option( 'ktpwp_db_version', '0.0.0' );
    $plugin_version = KANTANPRO_PLUGIN_VERSION;
    
    if ( version_compare( $current_db_version, $plugin_version, '<' ) ) {
        $issues_found = true;
    }

    // 問題が見つかった場合の自動修復
    if ( $issues_found ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP Distribution Safety: Issues found, attempting repair' );
        }

        try {
            // 基本テーブルの作成
            if ( function_exists( 'ktp_table_setup' ) ) {
                ktp_table_setup();
            }

            // 部署テーブル関連の修正
            if ( function_exists( 'ktpwp_create_department_table' ) ) {
                ktpwp_create_department_table();
            }

            // バージョン同期
            if ( $current_db_version === '0.0.0' || version_compare( $current_db_version, $plugin_version, '<' ) ) {
                update_option( 'ktpwp_db_version', $plugin_version );
            }

            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'KTPWP Distribution Safety: Repair completed' );
            }

        } catch ( Exception $e ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'KTPWP Distribution Safety: Repair failed: ' . $e->getMessage() );
            }
        }
    }

    // チェック完了フラグを設定（1時間有効）
    set_transient( 'ktpwp_distribution_safety_checked', true, HOUR_IN_SECONDS );
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

        // 部署関連テーブルの作成
        ktpwp_create_department_table();
        ktpwp_add_department_selection_column();
        ktpwp_add_client_selected_department_column();
        ktpwp_initialize_selected_department();
        
        // 利用規約テーブルの作成
        ktpwp_ensure_terms_table();
        
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
 * 配布版用の管理画面通知機能
 * マイグレーション状態と手動実行オプションを提供
 */
add_action( 'admin_notices', 'ktpwp_distribution_admin_notices' );

function ktpwp_distribution_admin_notices() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    
    // 有効化成功通知
    if ( $success_message = get_transient( 'ktpwp_activation_success' ) ) {
        echo '<div class="notice notice-success is-dismissible">';
        echo '<p><strong>KantanPro:</strong> ' . esc_html( $success_message ) . '</p>';
        echo '</div>';
        delete_transient( 'ktpwp_activation_success' );
    }
    
    // 再有効化成功通知
    if ( $success_message = get_transient( 'ktpwp_reactivation_success' ) ) {
        echo '<div class="notice notice-success is-dismissible">';
        echo '<p><strong>KantanPro:</strong> ' . esc_html( $success_message ) . '</p>';
        echo '</div>';
        delete_transient( 'ktpwp_reactivation_success' );
    }
    
    // 新規インストール成功通知
    if ( $success_message = get_transient( 'ktpwp_new_installation_success' ) ) {
        echo '<div class="notice notice-success is-dismissible">';
        echo '<p><strong>KantanPro:</strong> ' . esc_html( $success_message ) . '</p>';
        echo '</div>';
        delete_transient( 'ktpwp_new_installation_success' );
    }
    
    // 有効化エラー通知
    if ( $error_message = get_transient( 'ktpwp_activation_error' ) ) {
        echo '<div class="notice notice-error is-dismissible">';
        echo '<p><strong>KantanPro:</strong> ' . esc_html( $error_message ) . '</p>';
        echo '<p><a href="' . esc_url( add_query_arg( 'ktpwp_manual_migration', '1' ) ) . '" class="button">手動マイグレーション実行</a></p>';
        echo '</div>';
        delete_transient( 'ktpwp_activation_error' );
    }
    
    // 再有効化エラー通知
    if ( $error_message = get_transient( 'ktpwp_reactivation_error' ) ) {
        echo '<div class="notice notice-error is-dismissible">';
        echo '<p><strong>KantanPro:</strong> ' . esc_html( $error_message ) . '</p>';
        echo '<p><a href="' . esc_url( add_query_arg( 'ktpwp_manual_migration', '1' ) ) . '" class="button">手動マイグレーション実行</a></p>';
        echo '</div>';
        delete_transient( 'ktpwp_reactivation_error' );
    }
    
    // 新規インストールエラー通知
    if ( $error_message = get_transient( 'ktpwp_new_installation_error' ) ) {
        echo '<div class="notice notice-error is-dismissible">';
        echo '<p><strong>KantanPro:</strong> ' . esc_html( $error_message ) . '</p>';
        echo '<p><a href="' . esc_url( add_query_arg( 'ktpwp_manual_migration', '1' ) ) . '" class="button">手動マイグレーション実行</a></p>';
        echo '</div>';
        delete_transient( 'ktpwp_new_installation_error' );
    }
    
    // 手動マイグレーション実行
    if ( isset( $_GET['ktpwp_manual_migration'] ) && $_GET['ktpwp_manual_migration'] === '1' ) {
        if ( wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'ktpwp_manual_migration' ) ) {
            ktpwp_execute_manual_migration();
        } else {
            // nonceが無い場合は確認画面を表示
            echo '<div class="notice notice-warning">';
            echo '<p><strong>KantanPro:</strong> 手動マイグレーションを実行しますか？</p>';
            echo '<p><a href="' . esc_url( wp_nonce_url( add_query_arg( 'ktpwp_manual_migration', '1' ), 'ktpwp_manual_migration' ) ) . '" class="button button-primary">実行する</a></p>';
            echo '</div>';
        }
    }
    
    // invoice_itemsカラム修正の緊急マイグレーション実行
    if ( isset( $_GET['ktpwp_invoice_items_fix'] ) && $_GET['ktpwp_invoice_items_fix'] === '1' ) {
        if ( wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'ktpwp_invoice_items_fix' ) ) {
            ktpwp_execute_invoice_items_fix();
        } else {
            // nonceが無い場合は確認画面を表示
            echo '<div class="notice notice-warning">';
            echo '<p><strong>KantanPro:</strong> invoice_itemsカラム修正を実行しますか？</p>';
            echo '<p><a href="' . esc_url( wp_nonce_url( add_query_arg( 'ktpwp_invoice_items_fix', '1' ), 'ktpwp_invoice_items_fix' ) ) . '" class="button button-primary">実行する</a></p>';
            echo '</div>';
        }
    }
    
    // マイグレーション進行中の通知
    if ( get_option( 'ktpwp_migration_in_progress', false ) ) {
        echo '<div class="notice notice-info is-dismissible">';
        echo '<p><strong>KantanPro:</strong> データベースの更新を実行中です。完了までお待ちください。</p>';
        echo '</div>';
    }
    
    // マイグレーション状態チェック
    $migration_status = ktpwp_check_migration_status();
    if ( $migration_status['needs_migration'] ) {
        echo '<div class="notice notice-warning">';
        echo '<p><strong>KantanPro:</strong> データベースの更新が必要です。</p>';
        echo '<p>現在のDBバージョン: ' . esc_html( $migration_status['current_db_version'] ) . '</p>';
        echo '<p>必要なバージョン: ' . esc_html( $migration_status['plugin_version'] ) . '</p>';
        echo '<p><a href="' . esc_url( wp_nonce_url( add_query_arg( 'ktpwp_manual_migration', '1' ), 'ktpwp_manual_migration' ) ) . '" class="button button-primary">今すぐ更新</a></p>';
        echo '</div>';
    }
    
    // invoice_itemsカラム修正成功通知
    if ( $success_message = get_transient( 'ktpwp_invoice_items_fix_success' ) ) {
        echo '<div class="notice notice-success is-dismissible">';
        echo '<p><strong>KantanPro:</strong> ' . esc_html( $success_message ) . '</p>';
        echo '</div>';
        delete_transient( 'ktpwp_invoice_items_fix_success' );
    }
    
    // invoice_itemsカラム修正エラー通知
    if ( $error_message = get_transient( 'ktpwp_invoice_items_fix_error' ) ) {
        echo '<div class="notice notice-error is-dismissible">';
        echo '<p><strong>KantanPro:</strong> ' . esc_html( $error_message ) . '</p>';
        echo '</div>';
        delete_transient( 'ktpwp_invoice_items_fix_error' );
    }
    
    // マイグレーション完了チェック
    $migration_completed = get_option( 'ktp_order_migration_20250108_invoice_items_completed', false );
    $notification_dismissed = get_option( 'ktpwp_invoice_items_fix_notification_dismissed', false );
    
    // マイグレーションが完了していない場合のみ通知を表示
    if ( ! $migration_completed && ! $notification_dismissed ) {
        echo '<div class="notice notice-info is-dismissible" id="ktpwp-invoice-items-fix-notice">';
        echo '<p><strong>KantanPro:</strong> データベースエラーが発生している場合は、以下のボタンで修正してください。</p>';
        echo '<p><a href="' . esc_url( wp_nonce_url( add_query_arg( 'ktpwp_invoice_items_fix', '1' ), 'ktpwp_invoice_items_fix' ) ) . '" class="button button-primary">invoice_itemsカラム修正を実行</a></p>';
        echo '<p><a href="#" class="button button-secondary" onclick="dismissInvoiceItemsFixNotification(); return false;">この通知を非表示にする</a></p>';
        echo '</div>';
    }
}

/**
 * 手動マイグレーション実行機能
 */
function ktpwp_execute_manual_migration() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    
    try {
        if ( function_exists( 'ktpwp_run_complete_migration' ) ) {
            ktpwp_run_complete_migration();
        } else {
            ktpwp_run_auto_migrations();
        }
        
        set_transient( 'ktpwp_manual_migration_success', 'マイグレーションが正常に完了しました。', 60 );
        
    } catch ( Exception $e ) {
        set_transient( 'ktpwp_manual_migration_error', 'マイグレーション中にエラーが発生しました: ' . $e->getMessage(), 300 );
    }
    
    // リダイレクトして重複実行を防ぐ
    wp_redirect( admin_url( 'admin.php?page=ktp-settings' ) );
    exit;
}

/**
 * invoice_itemsカラム修正の緊急マイグレーション実行
 */
function ktpwp_execute_invoice_items_fix() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    
    try {
        // 特定のマイグレーションファイルを実行
        $migration_file = __DIR__ . '/includes/migrations/20250108_fix_invoice_items_column.php';
        
        if ( file_exists( $migration_file ) ) {
            require_once $migration_file;
            
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'KTPWP: invoice_itemsカラム修正マイグレーションを実行しました' );
            }
            
            set_transient( 'ktpwp_invoice_items_fix_success', 'invoice_itemsカラムの修正が正常に完了しました。', 60 );
            
            // マイグレーション完了時は通知非表示フラグをリセット
            delete_option( 'ktpwp_invoice_items_fix_notification_dismissed' );
        } else {
            throw new Exception( 'マイグレーションファイルが見つかりません: ' . $migration_file );
        }
        
    } catch ( Exception $e ) {
        set_transient( 'ktpwp_invoice_items_fix_error', 'invoice_itemsカラム修正中にエラーが発生しました: ' . $e->getMessage(), 300 );
    }
    
    // リダイレクトして重複実行を防ぐ
    wp_redirect( admin_url( 'admin.php?page=ktp-settings' ) );
    exit;
}

/**
 * 通知を非表示にするAJAXハンドラー
 */
function ktpwp_dismiss_invoice_items_fix_notification() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( '権限がありません' );
    }
    
    check_ajax_referer( 'ktpwp_dismiss_notification', 'nonce' );
    
    update_option( 'ktpwp_invoice_items_fix_notification_dismissed', true );
    
    wp_send_json_success( '通知が非表示になりました' );
}

// ============================================================================
// キャッシュヘルパー関数
// ============================================================================

/**
 * KantanProキャッシュマネージャーのインスタンスを取得
 * 
 * @return KTPWP_Cache|null キャッシュマネージャーインスタンス
 */
function ktpwp_cache() {
    global $ktpwp_cache;
    return $ktpwp_cache instanceof KTPWP_Cache ? $ktpwp_cache : null;
}

/**
 * キャッシュからデータを取得
 * 
 * @param string $key キャッシュキー
 * @param string $group キャッシュグループ（オプション）
 * @return mixed キャッシュされたデータ、存在しない場合はfalse
 */
function ktpwp_cache_get( $key, $group = null ) {
    $cache = ktpwp_cache();
    return $cache ? $cache->get( $key, $group ) : false;
}

/**
 * データをキャッシュに保存
 * 
 * @param string $key キャッシュキー
 * @param mixed $data 保存するデータ
 * @param int $expiration 有効期限（秒）
 * @param string $group キャッシュグループ（オプション）
 * @return bool 成功時true、失敗時false
 */
function ktpwp_cache_set( $key, $data, $expiration = null, $group = null ) {
    $cache = ktpwp_cache();
    return $cache ? $cache->set( $key, $data, $expiration, $group ) : false;
}

/**
 * キャッシュからデータを削除
 * 
 * @param string $key キャッシュキー
 * @param string $group キャッシュグループ（オプション）
 * @return bool 成功時true、失敗時false
 */
function ktpwp_cache_delete( $key, $group = null ) {
    $cache = ktpwp_cache();
    return $cache ? $cache->delete( $key, $group ) : false;
}

/**
 * データベースクエリ結果をキャッシュから取得または実行
 * 
 * @param string $key キャッシュキー
 * @param callable $callback データを取得するコールバック関数
 * @param int $expiration キャッシュ有効期限（秒）
 * @return mixed キャッシュされたデータまたはコールバックの実行結果
 */
function ktpwp_cache_remember( $key, $callback, $expiration = null ) {
    $cache = ktpwp_cache();
    return $cache ? $cache->remember( $key, $callback, $expiration ) : ( is_callable( $callback ) ? call_user_func( $callback ) : false );
}

/**
 * KantanPro Transientを取得
 * 
 * @param string $key Transientキー
 * @return mixed 保存されたデータ、存在しない場合はfalse
 */
function ktpwp_get_transient( $key ) {
    $cache = ktpwp_cache();
    return $cache ? $cache->get_transient( $key ) : false;
}

/**
 * KantanPro Transientを設定
 * 
 * @param string $key Transientキー
 * @param mixed $data 保存するデータ
 * @param int $expiration 有効期限（秒）
 * @return bool 成功時true、失敗時false
 */
function ktpwp_set_transient( $key, $data, $expiration = null ) {
    $cache = ktpwp_cache();
    return $cache ? $cache->set_transient( $key, $data, $expiration ) : false;
}

/**
 * KantanPro Transientを削除
 * 
 * @param string $key Transientキー
 * @return bool 成功時true、失敗時false
 */
function ktpwp_delete_transient( $key ) {
    $cache = ktpwp_cache();
    return $cache ? $cache->delete_transient( $key ) : false;
}

/**
 * すべてのKantanProキャッシュをクリア
 */
function ktpwp_clear_all_cache() {
    $cache = ktpwp_cache();
    if ( $cache ) {
        $cache->clear_all_cache();
    }
}

/**
 * パターンに一致するキャッシュを削除
 * 
 * @param string $pattern キーパターン（ワイルドカード*使用可能）
 */
function ktpwp_clear_cache_pattern( $pattern ) {
    $cache = ktpwp_cache();
    if ( $cache ) {
        $cache->clear_cache_by_pattern( $pattern );
    }
}

/**
 * 配布先での表示速度向上のための特別なキャッシュ関数
 * 
 * @param string $key キャッシュキー
 * @param callable $callback データ取得コールバック
 * @param int $expiration 有効期限
 * @return mixed キャッシュされたデータ
 */
function ktpwp_distribution_cache( $key, $callback, $expiration = null ) {
    $cache = ktpwp_cache();
    return $cache ? $cache->distribution_cache( $key, $callback, $expiration ) : false;
}

/**
 * キャッシュの自動有効化を実行
 */
function ktpwp_auto_enable_cache() {
    $cache = ktpwp_cache();
    if ( $cache ) {
        $cache->auto_enable_cache();
    }
}

/**
 * パフォーマンス監視を実行
 */
function ktpwp_monitor_performance() {
    $cache = ktpwp_cache();
    if ( $cache ) {
        $cache->monitor_performance();
    }
}

// ============================================================================
// フックマネージャーヘルパー関数
// ============================================================================

/**
 * KantanProフックマネージャーのインスタンスを取得
 * 
 * @return KTPWP_Hook_Manager|null フックマネージャーインスタンス
 */
function ktpwp_hook_manager() {
    global $ktpwp_hook_manager;
    return $ktpwp_hook_manager instanceof KTPWP_Hook_Manager ? $ktpwp_hook_manager : null;
}

/**
 * 条件付きでアクションフックを追加
 * 
 * @param string $hook_name フック名
 * @param callable $callback コールバック関数
 * @param array $conditions 実行条件
 * @param int $priority 優先度
 * @param int $accepted_args 引数数
 */
function ktpwp_add_conditional_action( $hook_name, $callback, $conditions = array(), $priority = 10, $accepted_args = 1 ) {
    $hook_manager = ktpwp_hook_manager();
    if ( $hook_manager ) {
        $hook_manager->add_conditional_action( $hook_name, $callback, $conditions, $priority, $accepted_args );
    } else {
        // フォールバック: 通常のadd_action
        add_action( $hook_name, $callback, $priority, $accepted_args );
    }
}

/**
 * 条件付きでフィルターフックを追加
 * 
 * @param string $hook_name フック名
 * @param callable $callback コールバック関数
 * @param array $conditions 実行条件
 * @param int $priority 優先度
 * @param int $accepted_args 引数数
 */
function ktpwp_add_conditional_filter( $hook_name, $callback, $conditions = array(), $priority = 10, $accepted_args = 1 ) {
    $hook_manager = ktpwp_hook_manager();
    if ( $hook_manager ) {
        $hook_manager->add_conditional_filter( $hook_name, $callback, $conditions, $priority, $accepted_args );
    } else {
        // フォールバック: 通常のadd_filter
        add_filter( $hook_name, $callback, $priority, $accepted_args );
    }
}

/**
 * フック最適化統計を取得
 * 
 * @return array フック最適化統計
 */
function ktpwp_get_hook_optimization_stats() {
    $hook_manager = ktpwp_hook_manager();
    return $hook_manager ? $hook_manager->get_optimization_stats() : array();
}

// ============================================================================
// 画像最適化ヘルパー関数
// ============================================================================

/**
 * KantanPro画像最適化インスタンスを取得
 * 
 * @return KTPWP_Image_Optimizer|null 画像最適化インスタンス
 */
function ktpwp_image_optimizer() {
    global $ktpwp_image_optimizer;
    return $ktpwp_image_optimizer instanceof KTPWP_Image_Optimizer ? $ktpwp_image_optimizer : null;
}

/**
 * 画像をWebPに変換
 * 
 * @param string $image_path 画像ファイルパス
 * @return string|false WebPファイルパス、失敗時はfalse
 */
function ktpwp_convert_to_webp( $image_path ) {
    $optimizer = ktpwp_image_optimizer();
    return $optimizer ? $optimizer->convert_to_webp( $image_path ) : false;
}

/**
 * 画像最適化統計を取得
 * 
 * @return array 最適化統計
 */
function ktpwp_get_image_optimization_stats() {
    $optimizer = ktpwp_image_optimizer();
    return $optimizer ? $optimizer->get_optimization_stats() : array();
}

/**
 * WebPサポート状況を確認
 * 
 * @return array サポート状況の詳細
 */
function ktpwp_check_webp_support() {
    $support_info = array(
        'server_support' => function_exists( 'imagewebp' ),
        'gd_extension' => extension_loaded( 'gd' ),
        'gd_version' => extension_loaded( 'gd' ) ? gd_info()['GD Version'] : 'Not available',
        'webp_support' => false,
    );
    
    // GD拡張のWebPサポートチェック
    if ( $support_info['gd_extension'] ) {
        $gd_info = gd_info();
        $support_info['webp_support'] = isset( $gd_info['WebP Support'] ) && $gd_info['WebP Support'];
    }
    
    return $support_info;
}

/**
 * 配布環境対応のマイグレーション状態監視機能
 */
function ktpwp_distribution_migration_monitor() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    
    // マイグレーション状態を取得
    $migration_status = ktpwp_get_distribution_migration_status();
    
    // エラーがある場合は通知を表示
    if ( ! empty( $migration_status['errors'] ) ) {
        foreach ( $migration_status['errors'] as $error ) {
            echo '<div class="notice notice-error"><p><strong>KantanPro マイグレーションエラー:</strong> ' . esc_html( $error ) . '</p></div>';
        }
    }
    
    // 成功通知を表示
    if ( ! empty( $migration_status['successes'] ) ) {
        foreach ( $migration_status['successes'] as $success ) {
            echo '<div class="notice notice-success"><p><strong>KantanPro マイグレーション成功:</strong> ' . esc_html( $success ) . '</p></div>';
        }
    }
}

/**
 * 配布環境でのマイグレーション状態を取得
 */
function ktpwp_get_distribution_migration_status() {
    $status = array(
        'current_db_version' => get_option( 'ktpwp_db_version', '0.0.0' ),
        'plugin_version' => KANTANPRO_PLUGIN_VERSION,
        'needs_migration' => false,
        'errors' => array(),
        'successes' => array(),
        'statistics' => array()
    );
    
    // マイグレーション必要性チェック
    $status['needs_migration'] = version_compare( $status['current_db_version'], $status['plugin_version'], '<' );
    
    // エラー情報を収集
    $migration_error = get_option( 'ktpwp_migration_error' );
    if ( $migration_error ) {
        $status['errors'][] = 'マイグレーションエラー: ' . $migration_error;
    }
    
    $activation_error = get_option( 'ktpwp_activation_error' );
    if ( $activation_error ) {
        $status['errors'][] = '有効化エラー: ' . $activation_error;
    }
    
    $upgrade_error = get_option( 'ktpwp_upgrade_error' );
    if ( $upgrade_error ) {
        $status['errors'][] = 'アップグレードエラー: ' . $upgrade_error;
    }
    
    // 成功情報を収集
    if ( get_option( 'ktpwp_activation_completed', false ) ) {
        $status['successes'][] = 'プラグイン有効化が完了しました';
    }
    
    if ( get_option( 'ktpwp_upgrade_completed', false ) ) {
        $status['successes'][] = 'プラグインアップグレードが完了しました';
    }
    
    if ( get_option( 'ktpwp_migration_completed', false ) ) {
        $status['successes'][] = 'データベースマイグレーションが完了しました';
    }
    
    // 統計情報を収集
    $status['statistics'] = array(
        'migration_attempts' => get_option( 'ktpwp_migration_attempts', 0 ),
        'migration_success_count' => get_option( 'ktpwp_migration_success_count', 0 ),
        'migration_error_count' => get_option( 'ktpwp_migration_error_count', 0 ),
        'activation_success_count' => get_option( 'ktpwp_activation_success_count', 0 ),
        'activation_error_count' => get_option( 'ktpwp_activation_error_count', 0 ),
        'upgrade_success_count' => get_option( 'ktpwp_upgrade_success_count', 0 ),
        'upgrade_error_count' => get_option( 'ktpwp_upgrade_error_count', 0 ),
        'last_migration' => get_option( 'ktpwp_last_migration_timestamp', 'Never' ),
        'last_activation' => get_option( 'ktpwp_activation_timestamp', 'Never' ),
        'last_upgrade' => get_option( 'ktpwp_upgrade_timestamp', 'Never' )
    );
    
    return $status;
}

/**
 * ダミーデータ作成メニューを追加
 */
function ktpwp_add_dummy_data_menu() {
    add_submenu_page(
        'tools.php',
        'KantanPro ダミーデータ作成',
        'KantanPro ダミーデータ',
        'manage_options',
        'ktpwp-dummy-data',
        'ktpwp_dummy_data_page'
    );
}

/**
 * ダミーデータ作成スクリプトのバージョンを取得
 */
function ktpwp_get_dummy_data_script_version() {
    $script_path = KANTANPRO_PLUGIN_DIR . 'create_dummy_data.php';
    
    if (!file_exists($script_path)) {
        return 'スクリプトが見つかりません';
    }
    
    $content = file_get_contents($script_path);
    
    // バージョン情報を抽出
    if (preg_match('/バージョン:\s*([0-9]+\.[0-9]+\.[0-9]+)/', $content, $matches)) {
        $version = $matches[1];
        
        // 説明文も抽出
        if (preg_match('/バージョン:\s*[0-9]+\.[0-9]+\.[0-9]+\s*\(([^)]+)\)/', $content, $desc_matches)) {
            return $version . ' (' . $desc_matches[1] . ')';
        }
        
        return $version;
    }
    
    return 'バージョン情報が見つかりません';
}

/**
 * ダミーデータ作成ページの表示
 */
function ktpwp_dummy_data_page() {
    // CSSファイルを読み込み
    wp_enqueue_style('ktp-dummy-data', plugins_url('css/ktp-dummy-data.css', __FILE__), array(), KANTANPRO_PLUGIN_VERSION);
    
    // jQueryを確実に読み込み
    wp_enqueue_script('jquery');
    
    ?>
    <div class="wrap ktp-dummy-data-wrap">
        <h1>KantanPro ダミーデータ作成</h1>
        
        <div class="ktp-dummy-data-version">
            <p><strong>プラグインバージョン:</strong> <?php echo esc_html(KANTANPRO_PLUGIN_VERSION); ?></p>
            <p><strong>ダミーデータ作成スクリプトバージョン:</strong> <?php echo esc_html(ktpwp_get_dummy_data_script_version()); ?></p>
        </div>
        
        <div class="ktp-dummy-data-info">
            <p><strong>作成されるデータ:</strong></p>
            <ul>
                <li>顧客: 6件（メールアドレス: info@kantanpro.com）</li>
                <li>受注書: 18件（顧客×6件 × ステータス3パターン：受付中・受注・完成）</li>
                <li>協力会社: 6件（メールアドレス: info@kantanpro.com）</li>
                <li>職能: 18件（協力会社×6件 × 税率3パターン：10%・8%・非課税）</li>
                <li>サービス: 6件（一般：税率10%・食品：税率8%・不動産：非課税）各×2</li>
                <li>請求項目・コスト項目: 受注書に自動追加</li>
            </ul>
            <p><strong>対象外データ:</strong></p>
            <ul>
                <li>スタッフチャット: 既存データは削除されますが、新規作成時は初期メッセージを作成しません</li>
            </ul>
        </div>
        
        <div class="ktp-dummy-data-card">
            <h2>ダミーデータ作成</h2>
            <p>テスト用のダミーデータを作成します。既存データがある場合は確認メッセージが表示されます。</p>
            
            <div class="ktp-dummy-data-buttons">
                <button type="button" id="create-dummy-data" class="ktp-dummy-data-button">
                    ダミーデータを作成
                </button>
                
                <button type="button" id="clear-data" class="ktp-dummy-data-button clear-button">
                    データをクリア
                </button>
            </div>
            
            <div id="dummy-data-result"></div>
        </div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        // ダミーデータ作成ボタン
        $('#create-dummy-data').on('click', function() {
            var button = $(this);
            var resultDiv = $('#dummy-data-result');
            
            button.prop('disabled', true).text('作成中...');
            resultDiv.html('<div class="ktp-dummy-data-result info"><p>ダミーデータを作成中...</p></div>');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'ktpwp_create_dummy_data',
                    nonce: '<?php echo wp_create_nonce('ktpwp_dummy_data_nonce'); ?>'
                },
                success: function(response) {
                    console.log('AJAX Response:', response);
                    if (response.success) {
                        resultDiv.html('<div class="ktp-dummy-data-result success"><p>' + response.data.message + '</p></div>');
                    } else {
                        resultDiv.html('<div class="ktp-dummy-data-result error"><p>エラー: ' + (response.data ? response.data.message : '不明なエラー') + '</p></div>');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', {xhr: xhr, status: status, error: error});
                    var errorMessage = '通信エラーが発生しました。';
                    if (xhr.responseText) {
                        try {
                            var response = JSON.parse(xhr.responseText);
                            if (response.data && response.data.message) {
                                errorMessage = response.data.message;
                            }
                        } catch (e) {
                            errorMessage = 'サーバーエラー: ' + xhr.status + ' ' + xhr.statusText;
                        }
                    }
                    resultDiv.html('<div class="ktp-dummy-data-result error"><p>' + errorMessage + '</p></div>');
                },
                complete: function() {
                    button.prop('disabled', false).text('ダミーデータを作成');
                }
            });
        });
        
        // データクリアボタン
        $('#clear-data').on('click', function() {
            if (!confirm('本当にデータをクリアしますか？\nこの操作は元に戻せません。')) {
                return;
            }
            
            var button = $(this);
            var resultDiv = $('#dummy-data-result');
            
            button.prop('disabled', true).text('クリア中...');
            resultDiv.html('<div class="ktp-dummy-data-result info"><p>データをクリア中...</p></div>');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'ktpwp_clear_data',
                    nonce: '<?php echo wp_create_nonce('ktpwp_clear_data_nonce'); ?>'
                },
                success: function(response) {
                    console.log('Clear Data AJAX Response:', response);
                    if (response.success) {
                        resultDiv.html('<div class="ktp-dummy-data-result success"><p>' + response.data.message + '</p></div>');
                    } else {
                        resultDiv.html('<div class="ktp-dummy-data-result error"><p>エラー: ' + (response.data ? response.data.message : '不明なエラー') + '</p></div>');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Clear Data AJAX Error:', {xhr: xhr, status: status, error: error});
                    var errorMessage = '通信エラーが発生しました。';
                    if (xhr.responseText) {
                        try {
                            var response = JSON.parse(xhr.responseText);
                            if (response.data && response.data.message) {
                                errorMessage = response.data.message;
                            }
                        } catch (e) {
                            errorMessage = 'サーバーエラー: ' + xhr.status + ' ' + xhr.statusText;
                        }
                    }
                    resultDiv.html('<div class="ktp-dummy-data-result error"><p>' + errorMessage + '</p></div>');
                },
                complete: function() {
                    button.prop('disabled', false).text('データをクリア');
                }
            });
        });
    });
    </script>
    <?php
}

/**
 * ダミーデータ作成AJAXハンドラー
 */
function ktpwp_handle_create_dummy_data_ajax() {
    // 出力バッファリングを開始（予期しない出力を防ぐ）
    ob_start();
    
    // デバッグ情報をログに記録
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('KTPWP: ダミーデータ作成AJAXハンドラーが呼び出されました');
    }
    
    try {
        // セキュリティチェック
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ktpwp_dummy_data_nonce')) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('KTPWP: セキュリティチェックに失敗しました');
            }
            wp_send_json_error(array('message' => 'セキュリティチェックに失敗しました。'));
            return;
        }
        
        if (!current_user_can('manage_options')) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('KTPWP: 権限がありません');
            }
            wp_send_json_error(array('message' => '権限がありません。'));
            return;
        }
        
        global $wpdb;
        
        // 既存データのチェック
        $existing_clients = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ktp_client");
        $existing_orders = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ktp_order");
        $existing_suppliers = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ktp_supplier");
        $existing_services = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ktp_service");
        
        if ($existing_clients > 0 || $existing_orders > 0 || $existing_suppliers > 0 || $existing_services > 0) {
            wp_send_json_error(array(
                'message' => '既存のデータが存在します。データを削除してから再実行してください。'
            ));
            return;
        }
        
        // 新しいダミーデータ作成スクリプトを実行
        $dummy_data_script = plugin_dir_path(__FILE__) . 'create_dummy_data.php';
        
        if (!file_exists($dummy_data_script)) {
            wp_send_json_error(array('message' => 'ダミーデータ作成スクリプトが見つかりません。'));
            return;
        }
        
        // 出力をキャプチャするために出力バッファリングを使用
        ob_start();
        
        // エラーハンドリングを強化
        $old_error_reporting = error_reporting();
        error_reporting(E_ALL);
        
        // エラーハンドラーを設定
        $error_handler = function($errno, $errstr, $errfile, $errline) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("KTPWP: ダミーデータ作成中にエラー: [$errno] $errstr in $errfile on line $errline");
            }
            return false; // 標準のエラーハンドラーも実行
        };
        set_error_handler($error_handler);
        
        try {
            // データベース接続を確認
            if (!$wpdb->check_connection()) {
                throw new Exception('データベース接続エラー');
            }
            
            // メモリ制限を一時的に増加
            $old_memory_limit = ini_get('memory_limit');
            ini_set('memory_limit', '256M');
            
            // 実行時間制限を一時的に増加
            $old_max_execution_time = ini_get('max_execution_time');
            set_time_limit(300); // 5分
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('KTPWP: ダミーデータ作成開始 - メモリ制限: ' . ini_get('memory_limit') . ', 実行時間制限: ' . ini_get('max_execution_time'));
            }
            
            // ダミーデータ作成スクリプトをインクルード
            include_once $dummy_data_script;
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('KTPWP: ダミーデータ作成スクリプト実行完了');
            }
            
            // 設定を復元
            ini_set('memory_limit', $old_memory_limit);
            set_time_limit($old_max_execution_time);
            
            // エラーハンドラーを復元
            restore_error_handler();
            error_reporting($old_error_reporting);
            
            $output = ob_get_clean();
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('KTPWP: ダミーデータ作成成功 - 出力長: ' . strlen($output));
            }
            
            // 成功メッセージを返す
            wp_send_json_success(array(
                'message' => 'ダミーデータが正常に作成されました。',
                'output' => $output
            ));
            
        } catch (Exception $e) {
            // 設定を復元
            ini_set('memory_limit', $old_memory_limit);
            set_time_limit($old_max_execution_time);
            
            // エラーハンドラーを復元
            restore_error_handler();
            error_reporting($old_error_reporting);
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('KTPWP: ダミーデータ作成中に例外が発生しました: ' . $e->getMessage());
                error_log('KTPWP: 例外の詳細: ' . $e->getTraceAsString());
            }
            wp_send_json_error(array('message' => 'ダミーデータ作成中にエラーが発生しました: ' . $e->getMessage()));
        }
        
    } catch (Exception $e) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('KTPWP: ダミーデータ作成中にエラーが発生しました: ' . $e->getMessage());
        }
        wp_send_json_error(array('message' => 'ダミーデータ作成中にエラーが発生しました: ' . $e->getMessage()));
    } finally {
        // 出力バッファをクリア（予期しない出力を除去）
        $output = ob_get_clean();
        
        // デバッグ時のみ、予期しない出力があればログに記録
        if (defined('WP_DEBUG') && WP_DEBUG && !empty($output)) {
            error_log('KTPWP: ダミーデータ作成AJAX中に予期しない出力を検出: ' . substr($output, 0, 1000));
        }
    }
}

/**
 * データクリアAJAXハンドラー
 */
function ktpwp_handle_clear_data_ajax() {
    // 出力バッファリングを開始（予期しない出力を防ぐ）
    ob_start();
    
    // デバッグ情報をログに記録
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('KTPWP: データクリアAJAXハンドラーが呼び出されました');
    }
    
    try {
        // セキュリティチェック
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ktpwp_clear_data_nonce')) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('KTPWP: セキュリティチェックに失敗しました');
            }
            wp_send_json_error(array('message' => 'セキュリティチェックに失敗しました。'));
            return;
        }
        
        if (!current_user_can('manage_options')) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('KTPWP: 権限がありません');
            }
            wp_send_json_error(array('message' => '権限がありません。'));
            return;
        }
        
        global $wpdb;
        
        $cleared_tables = array();
        $total_cleared = 0;
        
        // 1. 受注書関連テーブルをクリア（外部キー制約のため順序が重要）
        $cost_items_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ktp_order_cost_items");
        if ($cost_items_count > 0) {
            $result = $wpdb->query("DELETE FROM {$wpdb->prefix}ktp_order_cost_items");
            if ($result !== false) {
                $cleared_tables[] = "コスト項目: {$cost_items_count}件";
                $total_cleared += $cost_items_count;
            }
        }
        
        $invoice_items_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ktp_order_invoice_items");
        if ($invoice_items_count > 0) {
            $result = $wpdb->query("DELETE FROM {$wpdb->prefix}ktp_order_invoice_items");
            if ($result !== false) {
                $cleared_tables[] = "請求項目: {$invoice_items_count}件";
                $total_cleared += $invoice_items_count;
            }
        }
        
        // 2. 受注書テーブルをクリア
        $order_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ktp_order");
        if ($order_count > 0) {
            $result = $wpdb->query("DELETE FROM {$wpdb->prefix}ktp_order");
            if ($result !== false) {
                $cleared_tables[] = "受注書: {$order_count}件";
                $total_cleared += $order_count;
            }
        }
        
        // 3. 顧客テーブルをクリア
        $client_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ktp_client");
        if ($client_count > 0) {
            $result = $wpdb->query("DELETE FROM {$wpdb->prefix}ktp_client");
            if ($result !== false) {
                $cleared_tables[] = "顧客: {$client_count}件";
                $total_cleared += $client_count;
            }
        }
        
        // 4. 協力会社職能テーブルをクリア
        $supplier_skills_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ktp_supplier_skills");
        if ($supplier_skills_count > 0) {
            $result = $wpdb->query("DELETE FROM {$wpdb->prefix}ktp_supplier_skills");
            if ($result !== false) {
                $cleared_tables[] = "協力会社職能: {$supplier_skills_count}件";
                $total_cleared += $supplier_skills_count;
            }
        }
        
        // 5. 協力会社テーブルをクリア
        $supplier_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ktp_supplier");
        if ($supplier_count > 0) {
            $result = $wpdb->query("DELETE FROM {$wpdb->prefix}ktp_supplier");
            if ($result !== false) {
                $cleared_tables[] = "協力会社: {$supplier_count}件";
                $total_cleared += $supplier_count;
            }
        }
        
        // 6. サービステーブルをクリア
        $service_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ktp_service");
        if ($service_count > 0) {
            $result = $wpdb->query("DELETE FROM {$wpdb->prefix}ktp_service");
            if ($result !== false) {
                $cleared_tables[] = "サービス: {$service_count}件";
                $total_cleared += $service_count;
            }
        }
        
        if ($total_cleared > 0) {
            $success_message = sprintf(
                'データクリアが完了しました！<br><br>クリアされたデータ:<br>• %s<br><br>合計: %d件のデータを削除しました。',
                implode('<br>• ', $cleared_tables),
                $total_cleared
            );
        } else {
            $success_message = 'クリアするデータがありませんでした。';
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('KTPWP: データクリア成功 - ' . $success_message);
        }
        
        wp_send_json_success(array(
            'message' => $success_message,
            'cleared_count' => $total_cleared
        ));
        
    } catch (Exception $e) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('KTPWP: データクリアエラー - ' . $e->getMessage());
        }
        
        wp_send_json_error(array(
            'message' => 'エラーが発生しました: ' . $e->getMessage()
        ));
    } finally {
        // 出力バッファをクリア（予期しない出力を除去）
        $output = ob_get_clean();
        
        // デバッグ時のみ、予期しない出力があればログに記録
        if (defined('WP_DEBUG') && WP_DEBUG && !empty($output)) {
            error_log('KTPWP: データクリアAJAX中に予期しない出力を検出: ' . substr($output, 0, 1000));
        }
    }
}
