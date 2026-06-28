<?php
/**
 * Plugin Name: KantanPro
 * Plugin URI: https://www.kantanpro.com/
 * Description: スモールビジネスのための販売支援ツール。ショートコード[ktpwp_all_tab]を固定ページに設置してください。
 * Version: 1.3.5
 * Author: KantanPro
 * Author URI: https://www.kantanpro.com/kantanpro-page
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ktpwp
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

// 更新の一時展開で別パスから本ファイルが二重 require される場合の対策。
if ( function_exists( 'ktpwp_autoload_classes' ) ) {
    return;
}


if ( ! defined( 'KTPWP_EDITION' ) ) {
    define( 'KTPWP_EDITION', 'free' );
}

if ( ! function_exists( 'ktpwp_is_plugin_active_by_basename' ) ) {
    function ktpwp_is_plugin_active_by_basename( $plugin_basename ) {
        $active_plugins = (array) get_option( 'active_plugins', array() );
        if ( in_array( $plugin_basename, $active_plugins, true ) ) {
            return true;
        }
        if ( is_multisite() ) {
            $network_active_plugins = (array) get_site_option( 'active_sitewide_plugins', array() );
            if ( isset( $network_active_plugins[ $plugin_basename ] ) ) {
                return true;
            }
        }
        return false;
    }
}

if ( ! function_exists( 'ktpwp_normalize_plugin_basename' ) ) {
    function ktpwp_normalize_plugin_basename( $plugin_basename ) {
        if ( ! is_string( $plugin_basename ) || $plugin_basename === '' ) {
            return '';
        }
        $file = strtolower( basename( $plugin_basename ) );
        $dir  = strtolower( dirname( $plugin_basename ) );
        $dir  = str_replace( array( '-', '_', '.', '/' ), '', $dir );
        return $dir . '/' . $file;
    }
}

if ( ! function_exists( 'ktpwp_is_pro_edition_active' ) ) {
    function ktpwp_is_pro_edition_active() {
        $normalized_target = ktpwp_normalize_plugin_basename( 'KantanProEX/ktpwp.php' );
        $active_plugins = (array) get_option( 'active_plugins', array() );
        foreach ( $active_plugins as $active_plugin ) {
            if ( ktpwp_normalize_plugin_basename( $active_plugin ) === $normalized_target ) {
                return true;
            }
        }
        if ( is_multisite() ) {
            $network_active_plugins = (array) get_site_option( 'active_sitewide_plugins', array() );
            foreach ( array_keys( $network_active_plugins ) as $network_plugin ) {
                if ( ktpwp_normalize_plugin_basename( $network_plugin ) === $normalized_target ) {
                    return true;
                }
            }
        }
        return defined( 'KTPWP_EDITION' ) && KTPWP_EDITION === 'pro';
    }
}

if ( ! function_exists( 'ktpwp_should_bootstrap_free_edition' ) ) {
    function ktpwp_should_bootstrap_free_edition() {
        if ( ktpwp_is_pro_edition_active() ) {
            update_option( 'ktp_active_edition', 'pro', false );
            return false;
        }
        update_option( 'ktp_active_edition', 'free', false );
        return true;
    }
}

if ( ! function_exists( 'ktpwp_render_free_edition_suspended_notice' ) ) {
    function ktpwp_render_free_edition_suspended_notice() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        echo '<div class="notice notice-info"><p>';
        echo esc_html__( 'KantanPro 無料版は、有料版（KantanProEX）が有効化されているため停止中です。設定メニューと機能はKantanProEX側で表示され、ショートコードはそのまま有料版へ引き継がれています。', 'ktpwp' );
        echo '</p></div>';
    }
}

if ( ! ktpwp_should_bootstrap_free_edition() ) {
    add_action( 'admin_notices', 'ktpwp_render_free_edition_suspended_notice' );
    return;
}

require_once plugin_dir_path( __FILE__ ) . 'includes/class-ktpwp-edition.php';

/**
 * WordPress 6.7+ の翻訳「早期読み込み」Notice を抑制
 * WooCommerce / WooCommerce for Japan / WooCommerce PayPal Payments 等が init 前に
 * 翻訳を読むと Notice が出力され "headers already sent" でリダイレクトが失敗するため、
 * _load_textdomain_just_in_time の doing_it_wrong を出さないようにする。
 */
add_filter(
	'doing_it_wrong_trigger_error',
	function ( $trigger, $function_name, $message, $version ) {
		if ( $function_name === '_load_textdomain_just_in_time' && strpos( $message, 'triggered too early' ) !== false ) {
			return false;
		}
		return $trigger;
	},
	10,
	4
);

// Composer autoload を読み込みます（現在は外部依存関係なし）
if ( file_exists( plugin_dir_path( __FILE__ ) . 'vendor/autoload.php' ) ) {
    require_once plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';
}

// 開発環境設定を読み込み
if ( file_exists( plugin_dir_path( __FILE__ ) . 'development-config.php' ) ) {
    require_once plugin_dir_path( __FILE__ ) . 'development-config.php';
}

if ( ! defined( 'KTPWP_EDITION' ) ) {
    define( 'KTPWP_EDITION', 'free' );
}
if ( ! defined( 'KTPWP_STAFF_LIMIT' ) ) {
    // 無料版: 0 = スタッフ追加不可（管理者のみ）。有料 pro の 0（無制限）とは別扱い。
    define( 'KTPWP_STAFF_LIMIT', 0 );
}

// プラグイン定数定義
if ( ! defined( 'KANTANPRO_PLUGIN_VERSION' ) ) {
    // プラグインヘッダーから Version を取得して動的に定義
    $plugin_header = get_file_data( __FILE__, array( 'Version' => 'Version' ) );
    $detected_version = ( isset( $plugin_header['Version'] ) && $plugin_header['Version'] !== '' ) ? $plugin_header['Version'] : '1.0.0';
    define( 'KANTANPRO_PLUGIN_VERSION', $detected_version );
}
if ( ! defined( 'KANTANPRO_PLUGIN_NAME' ) ) {
    define( 'KANTANPRO_PLUGIN_NAME', 'KantanPro' );
}
if ( ! defined( 'KANTANPRO_PLUGIN_DESCRIPTION' ) ) {
    // 翻訳読み込み警告を回避するため、initアクションで設定
    define( 'KANTANPRO_PLUGIN_DESCRIPTION', 'スモールビジネスのための販売支援ツール' );
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

if ( ! defined( 'KANTANPRO_PLUGIN_CANONICAL_DIR' ) ) {
    define( 'KANTANPRO_PLUGIN_CANONICAL_DIR', 'KantanPro' );
}

if ( ! function_exists( 'ktpwp_plugin_asset_url' ) ) {
    /**
     * プラグイン配下ファイルの公開 URL。
     * 実フォルダが無料版の KantanPro や zipball 名のままでも、公式ディレクトリ名 KantanProEX を URL に使う。
     *
     * wp-config で KANTANPRO_PLUGIN_ROOT_RELATIVE_ASSETS が true のときはドメインなしのパス（/wp-content/plugins/KantanProEX/...）を返す。
     *
     * @param string $relative_path plugins_url 第1引数相当（例: images/default/icon.png）。
     * @return string
     */
    function ktpwp_plugin_asset_url( $relative_path ) {
        if ( ! defined( 'KANTANPRO_PLUGIN_FILE' ) ) {
            return '';
        }
        $canonical     = KANTANPRO_PLUGIN_CANONICAL_DIR;
        $relative_path = is_string( $relative_path ) ? ltrim( $relative_path, '/' ) : '';

        if ( defined( 'KANTANPRO_PLUGIN_ROOT_RELATIVE_ASSETS' ) && KANTANPRO_PLUGIN_ROOT_RELATIVE_ASSETS ) {
            $content_path = wp_parse_url( content_url( '/' ), PHP_URL_PATH );
            if ( ! is_string( $content_path ) || '' === $content_path ) {
                $content_path = '/wp-content';
            }
            $path = $content_path . '/plugins/' . $canonical . '/' . $relative_path;
            /**
             * Filters root-relative or absolute plugin asset URL.
             *
             * @param string $url           URL or path.
             * @param string $relative_path Relative path inside plugin.
             */
            return apply_filters( 'ktpwp_plugin_asset_url', $path, $relative_path );
        }

        // plugins_url(__FILE__) は実フォルダ名（例: 無料版 KantanPro）に依存し icon.png が別フォルダのとき 404 になる。
        // 常に公式ディレクトリ KantanProEX 配下を content_url で指す（実体も KantanProEX に配置すること）。
        $url = content_url( 'plugins/' . $canonical . '/' . $relative_path );

        // 取りこぼし対策（KantanProEX を誤って KantanProEXEX にしない）
        $url = preg_replace( '#/plugins/KantanPro(?!EX)(/)#i', '/plugins/' . $canonical . '$1', $url );

        return apply_filters( 'ktpwp_plugin_asset_url', $url, $relative_path );
    }
}

if ( ! function_exists( 'ktpwp_normalize_plugin_asset_urls' ) ) {
    /**
     * HTML 内に残った無料版フォルダのアセット URL を EX の公式フォルダへ補正する。
     *
     * @param string $html HTML.
     * @return string
     */
    function ktpwp_normalize_plugin_asset_urls( $html ) {
        if ( ! is_string( $html ) || '' === $html || ! defined( 'KANTANPRO_PLUGIN_CANONICAL_DIR' ) ) {
            return $html;
        }

        return preg_replace(
            '#/plugins/KantanPro(?!EX)(/)#i',
            '/plugins/' . KANTANPRO_PLUGIN_CANONICAL_DIR . '$1',
            $html
        );
    }
}

if ( ! function_exists( 'ktpwp_start_asset_url_normalizer_buffer' ) ) {
    /**
     * フロントの最終 HTML でも src/data-src の旧パスを補正する。
     *
     * @return void
     */
    function ktpwp_start_asset_url_normalizer_buffer() {
        if ( is_admin() || wp_doing_ajax() || wp_is_json_request() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
            return;
        }

        ob_start( 'ktpwp_normalize_plugin_asset_urls' );
    }
}
add_action( 'template_redirect', 'ktpwp_start_asset_url_normalizer_buffer', 0 );

// KTPWP Prefixed constants for internal consistency
if ( ! defined( 'KTPWP_PLUGIN_FILE' ) ) {
    define( 'KTPWP_PLUGIN_FILE', __FILE__ );
}
if ( ! defined( 'KTPWP_PLUGIN_DIR' ) ) {
    define( 'KTPWP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

$ktpwp_composer_autoload = __DIR__ . '/vendor/autoload.php';
if ( is_readable( $ktpwp_composer_autoload ) ) {
    require_once $ktpwp_composer_autoload;
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


if ( ! function_exists( 'ktpwp_ex_customize_delete_confirm_text' ) ) {
    /**
     * プラグイン削除確認メッセージを実際のアンインストール設定に合わせる。
     *
     * @return void
     */
    function ktpwp_ex_customize_delete_confirm_text() {
        if ( ! is_admin() || ! current_user_can( 'delete_plugins' ) ) {
            return;
        }

        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( ! $screen || ! in_array( $screen->id, array( 'plugins', 'plugins-network' ), true ) ) {
            return;
        }

        $settings = get_option( 'ktp_uninstall_settings', array() );
        $mode     = isset( $settings['uninstall_mode'] ) ? (string) $settings['uninstall_mode'] : 'keep_data';

        $message_keep = __( '本当に KantanPro を削除してもよいですか？\n\n現在の設定は「データを残す」です。プラグインファイルのみ削除され、データは残ります。', 'ktpwp' );
        $message_full = __( '本当に KantanPro とそのデータを削除してもよいですか？\n\n現在の設定は「完全削除」です。関連データも削除されます。', 'ktpwp' );
        $message      = ( $mode === 'full_delete' ) ? $message_full : $message_keep;
        ?>
        <script>
        (function() {
            var row = document.querySelector('tr[data-plugin=<?php echo wp_json_encode( plugin_basename( __FILE__ ) ); ?>]');
            if (!row) return;

            var deleteLink = row.querySelector('.delete a');
            if (!deleteLink) return;

            deleteLink.addEventListener('click', function(event) {
                event.preventDefault();
                event.stopPropagation();

                if (window.confirm(<?php echo wp_json_encode( $message ); ?>)) {
                    window.location.href = deleteLink.getAttribute('href');
                }
            }, true);
        })();
        </script>
        <?php
    }
}
add_action( 'admin_footer', 'ktpwp_ex_customize_delete_confirm_text', 99 );

/**
 * プラグイン有効化フック（包括的マイグレーション）
 * 新規インストール・再有効化時に必要なデータベーステーブルを作成し、マイグレーションを実行します。
 */
register_activation_hook( __FILE__, 'ktpwp_comprehensive_activation' );

// ダミーデータ作成機能の管理メニュー登録は KTP_Settings 内のサブメニューとして追加


// === WordPress標準更新システム ===
// シンプルなバージョン管理

/**
 * 協力会社「職能」POST をテーマ出力より前に処理する（投稿名パーマリンク等で the_content 内リダイレクトが失敗し白画面になるのを防ぐ）。
 */
if ( ! function_exists( 'ktpwp_supplier_skills_template_redirect' ) ) {
    add_action( 'template_redirect', 'ktpwp_supplier_skills_template_redirect', 0 );
    function ktpwp_supplier_skills_template_redirect() {
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			return;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return;
		}
		if ( is_admin() ) {
			return;
		}
		if ( 'POST' !== strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) {
			return;
		}
		if ( empty( $_POST['skills_action'] ) || empty( $_POST['ktp_skills_nonce'] ) ) {
			return;
		}
		if ( ! is_user_logged_in() || ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		$nonce = isset( $_POST['ktp_skills_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['ktp_skills_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'ktp_skills_action' ) ) {
			return;
		}

		if ( ! class_exists( 'KTPWP_Supplier_Class' ) ) {
			require_once KANTANPRO_PLUGIN_DIR . 'includes/class-ktpwp-tab-supplier.php';
		}

		$GLOBALS['ktpwp_supplier_skills_early_done'] = true;

		$supplier = new KTPWP_Supplier_Class();
		$supplier->handle_skills_operations_front_before_template( wp_unslash( $_POST ) );
    }
}

/**
 * 改善されたアップグレード処理
 * バージョン変更時に確実にマイグレーションを実行
 */
if ( ! function_exists( 'ktpwp_upgrade' ) ) {
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
            if ( function_exists( 'ktpwp_run_auto_migrations' ) ) {
                ktpwp_run_auto_migrations();
            }

            // 適格請求書ナンバー機能のマイグレーション（確実に実行）
            if ( function_exists( 'ktpwp_run_qualified_invoice_migration' ) ) {
                ktpwp_run_qualified_invoice_migration();
            }

            // コスト項目テーブルに適格請求書番号カラムを追加
            if ( function_exists( 'ktpwp_run_qualified_invoice_number_cost_items_migration' ) ) {
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
}

if ( ! has_action( 'admin_init', 'ktpwp_upgrade' ) ) {
    add_action( 'admin_init', 'ktpwp_upgrade', 10, 0 );
}

/**
 * プラグインクラスの自動読み込み
 */
if ( ! function_exists( 'ktpwp_autoload_classes' ) ) {
    function ktpwp_autoload_classes() {
    $classes = array(
        'KTPWP_Client_Class'    => 'includes/class-ktpwp-client.php',
        'KTPWP_Service_Class'   => 'includes/class-ktpwp-service-main.php',
        'KTPWP_Supplier_Class'  => 'includes/class-ktpwp-tab-supplier.php',
        'KTPWP_Supplier_Security' => 'includes/class-ktpwp-supplier-security.php',
        'KTPWP_Supplier_Data'   => 'includes/class-ktpwp-supplier-data.php',
        'KTPWP_Report_Class'    => 'includes/class-ktpwp-tab-report.php',
        'KTPWP_Order_Class'     => 'includes/class-ktpwp-order-main.php',

        // 新しいクラス構造
        'KTPWP'                 => 'includes/class-ktpwp.php',
        'KTPWP_Main'            => 'includes/class-ktpwp-main.php',
        'KTPWP_I18n'            => 'includes/class-ktpwp-i18n.php',
        'KTPWP_Loader'          => 'includes/class-ktpwp-loader.php',
        'KTPWP_Security'        => 'includes/class-ktpwp-security.php',
        'KTPWP_Ajax'            => 'includes/class-ktpwp-ajax.php',
        'KTPWP_Assets'          => 'includes/class-ktpwp-assets.php',
        'KTPWP_Nonce_Manager'   => 'includes/class-ktpwp-nonce-manager.php',
        'KTPWP_Shortcodes'      => 'includes/class-ktpwp-shortcodes.php',
        'KTPWP_Redirect'        => 'includes/class-ktpwp-redirect.php',
        'KTPWP_Contact_Form'    => 'includes/class-ktpwp-contact-form.php',
        'KTPWP_Public_Product_Order' => 'includes/class-ktpwp-public-product-order.php',
        'KTPWP_Database'        => 'includes/class-ktpwp-database.php',
        'KTPWP_Order'           => 'includes/class-ktpwp-order.php',
        'KTPWP_Order_Items'     => 'includes/class-ktpwp-order-items.php',
        'KTPWP_Invoice_Line_Amount' => 'includes/class-ktpwp-invoice-line-amount.php',
        'KTPWP_Order_UI'        => 'includes/class-ktpwp-order-ui.php',
        'KTPWP_Staff_Chat'      => 'includes/class-ktpwp-staff-chat.php',
        'KTPWP_Order_Auxiliary' => 'includes/class-ktpwp-order-auxiliary.php',
        'KTPWP_Order_Admin_Notification' => 'includes/class-ktpwp-order-admin-notification.php',
        'KTPWP_Service_DB'      => 'includes/class-ktpwp-service-db.php',
        'KTPWP_Service_UI'      => 'includes/class-ktpwp-service-ui.php',
        'KTPWP_UI_Generator'    => 'includes/class-ktpwp-ui-generator.php',
        'KTPWP_Tab_Search_UI'   => 'includes/class-ktpwp-tab-search-ui.php',
        'KTPWP_Image_Processor' => 'includes/class-ktpwp-image-processor.php',
        'KTPWP_Service_Image_Storage' => 'includes/class-ktpwp-service-image-storage.php',
        'KTPWP_JapanPost_Address_API' => 'includes/class-ktpwp-japanpost-address-api.php',
        'KTPWP_Login_Error'     => 'includes/class-ktpwp-login-error.php',
        'KTPWP_Print_Class'     => 'includes/class-ktpwp-print.php',
        'KTPWP_Upgrade'         => 'includes/class-ktpwp-upgrade.php',
        'KTPWP_License_Manager' => 'includes/class-ktpwp-license-manager.php',
        'KTPWP_Graph_Renderer'  => 'includes/class-ktpwp-graph-renderer.php',
        // POSTデータ安全処理クラス（Adminer警告対策）
        'KTPWP_Post_Data_Handler' => 'includes/class-ktpwp-post-handler.php',
        // クライアント管理の新クラス
        'KTPWP_Client_DB'       => 'includes/class-ktpwp-client-db.php',
        'KTPWP_Client_UI'       => 'includes/class-ktpwp-client-ui.php',
        'KTPWP_Department_Manager' => 'includes/class-ktpwp-department-manager.php',
        'KTPWP_Terms_Of_Service' => 'includes/class-ktpwp-terms-of-service.php',
        'KTPWP_Update_Checker'  => 'includes/class-ktpwp-update-checker.php',
        'KTPWP_SVG_Icons'       => 'includes/class-ktpwp-svg-icons.php',
        'KTPWP_FM_Import'       => 'includes/class-ktpwp-fm-import.php',
        'KTPWP_Contract_Reminder_Mail' => 'includes/class-ktpwp-contract-reminder-mail.php',
        'KTPWP_Edition'         => 'includes/class-ktpwp-edition.php',
        'KTPWP_Settings'        => 'includes/class-ktpwp-settings.php',
        'KTPWP_Pdf_Document_Kind' => 'includes/class-ktpwp-pdf-document-kind.php',
        'KTPWP_Pdf_Document_Settings' => 'includes/class-ktpwp-pdf-document-settings.php',
        'KTPWP_Pdf_Branding'    => 'includes/class-ktpwp-pdf-branding.php',
        'KTPWP_Pdf_Document_Renderer' => 'includes/class-ktpwp-pdf-document-renderer.php',
        'KTPWP_Pdf_Branding_Admin' => 'includes/class-ktpwp-pdf-branding-admin.php',
        'KTPWP_Payment_Timing'  => 'includes/class-ktpwp-payment-timing.php',
        'KTPWP_External_Url'    => 'includes/class-ktpwp-external-url.php',
        'KTPWP_Contract_Billing_Cycle' => 'includes/class-ktpwp-contract-billing-cycle.php',
        'KTPWP_Contract_Recurring_Items' => 'includes/class-ktpwp-contract-recurring-items.php',
        'KTPWP_Service_Initial_Fees' => 'includes/class-ktpwp-service-initial-fees.php',
        'KTPWP_Service_Related_Orders' => 'includes/class-ktpwp-service-related-orders.php',
        'KTPWP_Contract_DB'     => 'includes/class-ktpwp-contract-db.php',
        'KTPWP_Contract_Service_Public_Availability' => 'includes/class-ktpwp-contract-service-public-availability.php',
        'KTPWP_Order_Progress_Effects' => 'includes/class-ktpwp-order-progress-effects.php',
        'KTPWP_Contract_UI'     => 'includes/class-ktpwp-contract-ui.php',
        'KTPWP_Contract_Billing'    => 'includes/class-ktpwp-contract-billing.php',
        'KTPWP_Contract_Billing_UI' => 'includes/class-ktpwp-contract-billing-ui.php',
        'KTPWP_Public_Product_Order_Memo' => 'includes/class-ktpwp-public-product-order-memo.php',
        'KTPWP_Order_Contract_Draft_Resolver' => 'includes/class-ktpwp-order-contract-draft-resolver.php',
        'KTPWP_Order_Contract_Conversion' => 'includes/class-ktpwp-order-contract-conversion.php',
        'KTPWP_Order_Contract_UI' => 'includes/class-ktpwp-order-contract-ui.php',
        'KTPWP_Stripe_Billing'    => 'includes/class-ktpwp-stripe-billing.php',
        'KTPWP_Stripe_Subscription' => 'includes/class-ktpwp-stripe-subscription.php',
        'KTPWP_Contract_Invoice_Mail' => 'includes/class-ktpwp-contract-invoice-mail.php',
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
    // 税制ポリシー
    $tax_policy_file = __DIR__ . '/includes/class-ktpwp-tax-policy.php';
    if ( file_exists( $tax_policy_file ) ) {
        require_once $tax_policy_file;
    }
    }
}

// --- Ajaxハンドラ（協力会社・職能リスト取得）を必ず読み込む ---
require_once __DIR__ . '/includes/ajax-supplier-cost.php';

// --- 部署管理AJAXハンドラを読み込む ---
require_once __DIR__ . '/includes/ajax-department.php';

// --- 定期契約AJAXハンドラを読み込む ---
require_once __DIR__ . '/includes/ajax-contract.php';
require_once __DIR__ . '/includes/ajax-contract-billing.php';
require_once __DIR__ . '/includes/ajax-order-contract.php';

// --- 売上台帳PDF生成AJAXハンドラを読み込む ---
require_once __DIR__ . '/includes/ajax-sales-ledger-pdf.php';



// クラスの読み込み実行
ktpwp_autoload_classes();

// FileMaker 取り込み：オートロードがスキップされた場合でも管理画面コールバックで落ちないよう明示読み込み
if ( defined( 'MY_PLUGIN_PATH' ) && is_string( MY_PLUGIN_PATH ) && MY_PLUGIN_PATH !== '' ) {
	$ktpwp_fm_import_file = trailingslashit( MY_PLUGIN_PATH ) . 'includes/class-ktpwp-fm-import.php';
	if ( is_readable( $ktpwp_fm_import_file ) && ! class_exists( 'KTPWP_FM_Import', false ) ) {
		require_once $ktpwp_fm_import_file;
	}
}

if ( class_exists( 'KTPWP_FM_Import' ) ) {
	KTPWP_FM_Import::bootstrap();
}

// 多言語化サポートを初期化
if ( class_exists( 'KTPWP_I18n' ) ) {
    KTPWP_I18n::get_instance()->init();
}

// 部署テーブルの存在確認と作成
if ( function_exists( 'ktpwp_create_department_table' ) ) {
    $department_table_created = ktpwp_create_department_table();
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( "KTPWP: Department table creation result: " . ( $department_table_created ? 'success' : 'failed' ) );
    }
}

/**
 * サービス画像を uploads 配下へ移行（データを残す設定時）
 */
function ktpwp_maybe_migrate_service_images() {
    if ( class_exists( 'KTPWP_Service_Image_Storage' ) ) {
        KTPWP_Service_Image_Storage::migrate_legacy_images();
    }
}

/**
 * 更新チェッカーの初期化
 */
function ktpwp_init_update_checker() {
    if ( ! class_exists( 'KTPWP_Update_Checker' ) ) {
        return;
    }

    global $ktpwp_update_checker;
    if ( $ktpwp_update_checker instanceof KTPWP_Update_Checker ) {
        return;
    }

    // WordPress.org 向けプラグイン更新フックはクラス内で is_admin() ガード済み。
    // フロントでもインスタンス化し、ヘッダー更新チェック用 JS/AJAX を有効にする。
    $ktpwp_update_checker = new KTPWP_Update_Checker();
}



/**
 * 定期契約の請求予定メール Cron を初期化
 */
function ktpwp_init_contract_reminder_mail() {
    if ( function_exists( 'ktpwp_contracts_feature_enabled' ) && ! ktpwp_contracts_feature_enabled() ) {
        if ( class_exists( 'KTPWP_Contract_Reminder_Mail' ) ) {
            KTPWP_Contract_Reminder_Mail::unschedule();
        }
        return;
    }
    if ( class_exists( 'KTPWP_Contract_Reminder_Mail' ) ) {
        KTPWP_Contract_Reminder_Mail::boot();
    }
}

/**
 * Stripe 請求連携を初期化
 */
function ktpwp_init_stripe_billing() {
    if ( function_exists( 'ktpwp_is_feature_enabled' ) && ! ktpwp_is_feature_enabled( 'stripe_billing' ) ) {
        return;
    }
    if ( class_exists( 'KTPWP_Stripe_Billing' ) ) {
        KTPWP_Stripe_Billing::boot();
    }
}

/**
 * 管理画面アクセス保護（IP 制限 / Basic 認証）を初期化
 */
function ktpwp_init_admin_access_protection() {
    if ( class_exists( 'KTPWP_Security' ) ) {
        KTPWP_Security::get_instance()->boot_admin_access_protection();
    }
}

/**
 * 定期契約の請求メール自動送信 Cron を初期化
 */
function ktpwp_init_contract_invoice_mail() {
    if ( function_exists( 'ktpwp_is_feature_enabled' ) && ! ktpwp_is_feature_enabled( 'contract_invoice_auto_mail' ) ) {
        if ( class_exists( 'KTPWP_Contract_Invoice_Mail' ) ) {
            KTPWP_Contract_Invoice_Mail::unschedule();
        }
        return;
    }
    if ( class_exists( 'KTPWP_Contract_Invoice_Mail' ) ) {
        KTPWP_Contract_Invoice_Mail::boot();
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
add_action( 'plugins_loaded', 'ktpwp_init_admin_access_protection', 0 );
add_action( 'plugins_loaded', 'ktpwp_maybe_migrate_service_images', 8 );
add_action( 'plugins_loaded', 'ktpwp_init_update_checker' );
add_action( 'plugins_loaded', 'ktpwp_init_contract_reminder_mail' );
add_action( 'plugins_loaded', 'ktpwp_init_stripe_billing' );
add_action( 'plugins_loaded', 'ktpwp_init_contract_invoice_mail' );
add_action( 'plugins_loaded', 'ktpwp_init_cache' );
add_action( 'plugins_loaded', 'ktpwp_init_image_optimizer' );
add_action(
	'plugins_loaded',
	static function () {
		if ( class_exists( 'KTPWP_Pdf_Branding_Admin' ) ) {
			KTPWP_Pdf_Branding_Admin::init();
		}
	},
	15
);

// WooCommerce 連携: 注文を KantanPro に自動追加
add_action( 'woocommerce_loaded', 'ktpwp_init_woocommerce_integration' );
function ktpwp_init_woocommerce_integration() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-ktpwp-woocommerce-integration.php';
	KTPWP_WooCommerce_Integration::get_instance();
}

// キャッシュクリア処理のAJAXハンドラー
add_action( 'wp_ajax_ktpwp_clear_cache', 'ktpwp_handle_clear_cache_ajax' );
function ktpwp_handle_clear_cache_ajax() {
    // 権限チェック
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( __( '権限がありません', 'ktpwp' ) );
    }
    
    // ナンスチェック
    if ( ! wp_verify_nonce( $_POST['nonce'], 'ktpwp_clear_cache' ) ) {
        wp_send_json_error( __( 'セキュリティチェックに失敗しました', 'ktpwp' ) );
    }
    
    try {
        // すべてのキャッシュをクリア
        ktpwp_clear_all_cache();
        
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP Cache: 管理画面からキャッシュをクリアしました' );
        }
        
        wp_send_json_success( __( 'キャッシュが正常にクリアされました', 'ktpwp' ) );
    } catch ( Exception $e ) {
        wp_send_json_error( __( 'キャッシュのクリアに失敗しました: ', 'ktpwp' ) . $e->getMessage() );
    }
}

// 一括画像変換処理のAJAXハンドラー
add_action( 'wp_ajax_ktpwp_convert_all_images', 'ktpwp_handle_convert_all_images_ajax' );
function ktpwp_handle_convert_all_images_ajax() {
    // 権限チェック
    if ( ! current_user_can( 'upload_files' ) ) {
        wp_send_json_error( __( '権限がありません', 'ktpwp' ) );
    }
    
    // ナンスチェック
    if ( ! wp_verify_nonce( $_POST['nonce'], 'ktpwp_image_optimization' ) ) {
        wp_send_json_error( __( 'セキュリティチェックに失敗しました', 'ktpwp' ) );
    }
    
    try {
        // 画像最適化インスタンスを取得
        global $ktpwp_image_optimizer;
        
        if ( ! $ktpwp_image_optimizer ) {
            wp_send_json_error( __( '画像最適化機能が利用できません', 'ktpwp' ) );
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
        
        wp_send_json_success( __( "{$converted_count} / {$total_count} 個の画像をWebPに変換しました", 'ktpwp' ) );
        
    } catch ( Exception $e ) {
        wp_send_json_error( __( '一括変換に失敗しました: ', 'ktpwp' ) . $e->getMessage() );
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

// プラグイン自動更新 UI を有効化（有効/無効は auto_update_plugins オプションとコア AJAX に委譲）
add_filter( 'plugins_auto_update_enabled', '__return_true' );

// === 改善された自動マイグレーション機能 ===

/**
 * スタックしたマイグレーション進行中フラグをクリアする
 *
 * PHP タイムアウトや Fatal エラーで finally が実行されない場合、
 * ktpwp_migration_in_progress が true のまま残り通知が消えなくなる。
 *
 * @return bool フラグをクリアした場合 true
 */
function ktpwp_clear_stale_migration_in_progress() {
    if ( ! get_option( 'ktpwp_migration_in_progress', false ) ) {
        return false;
    }

    $plugin_version     = KANTANPRO_PLUGIN_VERSION;
    $current_db_version = get_option( 'ktpwp_db_version', '0.0.0' );
    $should_clear       = false;

    // DB バージョンが最新なら完了済みとみなす
    if ( $current_db_version === $plugin_version ) {
        $should_clear = true;
    }

    // 開始から一定時間経過していればスタックとみなす
    if ( ! $should_clear ) {
        $start_time = get_option( 'ktpwp_migration_start_time', '' );
        if ( empty( $start_time ) ) {
            $should_clear = true;
        } else {
            $elapsed = current_time( 'timestamp' ) - strtotime( $start_time );
            if ( $elapsed > 600 ) { // 10分
                $should_clear = true;
            }
        }
    }

    if ( $should_clear ) {
        delete_option( 'ktpwp_migration_in_progress' );
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP: スタックしたマイグレーション進行中フラグをクリアしました' );
        }
        return true;
    }

    return false;
}

/**
 * 配布環境対応の強化された自動マイグレーション実行関数
 * 不特定多数のサイトでの配布に対応
 */
function ktpwp_run_auto_migrations() {
    // 出力バッファリングを開始（予期しない出力を防ぐ）
    ob_start();

    // スタックした進行中フラグを先にクリア（以降のマイグレーション再試行を可能にする）
    ktpwp_clear_stale_migration_in_progress();
    
    // マイグレーション進行中チェック（重複実行防止）
    if ( get_option( 'ktpwp_migration_in_progress', false ) ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP Auto Migration: マイグレーションが既に進行中です' );
        }
        ob_end_clean();
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
            ktpwp_flush_db_version_cache();

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
 * 受注書メール履歴・案件ファイル用テーブルを確保
 */
function ktpwp_ensure_order_auxiliary_tables() {
	$path = KTPWP_PLUGIN_DIR . 'includes/class-ktpwp-order-auxiliary.php';
	if ( ! file_exists( $path ) ) {
		return;
	}
	require_once $path;
	if ( class_exists( 'KTPWP_Order_Auxiliary' ) ) {
		KTPWP_Order_Auxiliary::install_tables();
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
        ktpwp_safe_add_order_client_department_column();

        ktpwp_ensure_order_auxiliary_tables();

        if ( class_exists( 'KTPWP_Settings' ) ) {
            KTPWP_Settings::seed_default_page_content_widths();
        }

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
 * DBバージョンオプションのキャッシュを削除（配布環境でリロード時に正しく読むため）
 */
function ktpwp_flush_db_version_cache() {
	wp_cache_delete( 'ktpwp_db_version', 'options' );
	wp_cache_flush();
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
        ktpwp_safe_add_order_client_department_column();

        ktpwp_ensure_order_auxiliary_tables();

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
 * 安全な受注部署カラム追加
 */
function ktpwp_safe_add_order_client_department_column() {
    try {
        if ( function_exists( 'ktpwp_add_order_client_department_column' ) ) {
            ktpwp_add_order_client_department_column();
        }
    } catch ( Exception $e ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP Safe Order Department Column Error: ' . $e->getMessage() );
        }
    }
}

/**
 * 安全なマイグレーションファイル実行
 */
function ktpwp_safe_run_migration_files( $from_version, $to_version ) {
    try {
		if ( function_exists( 'ktpwp_run_migration_files' ) ) {
			$executed_ok = ktpwp_run_migration_files( $from_version, $to_version );
			if ( $executed_ok === false ) {
				throw new Exception( 'マイグレーションファイルの実行に失敗しました' );
			}
		} else {
			// マイグレーションファイルを直接実行
			$executed_ok = ktpwp_run_migration_files_directly( $from_version, $to_version );
			if ( $executed_ok === false ) {
				throw new Exception( 'マイグレーションファイルの実行に失敗しました' );
			}
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
		return true; // 実行対象なしは成功扱い
    }
    
    // マイグレーションファイルを取得
    $migration_files = glob( $migration_dir . '*.php' );
    
    if ( empty( $migration_files ) ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP: マイグレーションファイルが見つかりません' );
        }
		return true; // 実行対象なしは成功扱い
    }
    
    // ファイル名でソート
    sort( $migration_files );

	$all_ok = true;
    
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
            
			// マイグレーションファイルの echo を画面に出さない（更新直後の表示をすっきりさせる）
			ob_start();
			// マイグレーションファイルを読み込み（読み込み前後のクラス差分を取得）
			$before_classes = get_declared_classes();
			require_once $migration_file;
			$after_classes = get_declared_classes();
			$new_classes = array_diff( $after_classes, $before_classes );
			
			$executed = false;
			
			// 1) 新規に宣言されたクラスから up() を呼ぶ
			foreach ( $new_classes as $class_name ) {
				if ( method_exists( $class_name, 'up' ) ) {
					$result = call_user_func( array( $class_name, 'up' ) );
					if ( $result !== false ) {
						$executed = true;
					}
				}
			}
			
			// 2) ファイル名から関数名を推測して実行（例: 20250722_create_invoice_items_table → ktpwp_create_invoice_items_table）
			if ( ! $executed ) {
				$base = preg_replace( '/\.php$/', '', $filename );
				if ( preg_match( '/^\d{8,}[_-]?(.*)$/', $base, $m ) ) {
					$slug = $m[1];
				} else {
					$slug = $base;
				}
				$slug = strtolower( preg_replace( '/[^a-zA-Z0-9_\-]+/', '_', $slug ) );
				$slug = str_replace( '-', '_', $slug );
				$candidate_function = 'ktpwp_' . $slug;
				if ( function_exists( $candidate_function ) ) {
					$result = call_user_func( $candidate_function );
					if ( $result !== false ) {
						$executed = true;
					}
				}
			}
			
			if ( $executed ) {
				// 実行完了フラグを設定（成功時のみ）
				update_option( $migration_key, true );
				update_option( $migration_key . '_timestamp', current_time( 'mysql' ) );
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'KTPWP: マイグレーションを正常に実行しました: ' . $filename );
				}
			} else {
				// 実行できなかった場合はフラグを立てない（次回再試行）
				$all_ok = false;
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'KTPWP: このマイグレーションファイルで実行可能な処理を見つけられませんでした（未完了扱い）: ' . $filename );
				}
			}
			ob_end_clean();
            
        } catch ( Exception $e ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'KTPWP Migration File Error: ' . $filename . ' - ' . $e->getMessage() );
            }
			// このファイルは未完了とみなし、全体成功フラグを下げる
			$all_ok = false;
			if ( ob_get_level() ) {
				ob_end_clean();
			}
        }
    }

	return $all_ok;
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
        if ( class_exists( 'KTPWP_Settings' ) && method_exists( 'KTPWP_Settings', 'activate' ) ) {
            KTPWP_Settings::activate();
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
            set_transient( 'ktpwp_activation_success', __( 'KantanProプラグインが正常にインストールされました。', 'ktpwp' ), 60 );
        } else {
            set_transient( 'ktpwp_activation_success', __( 'KantanProプラグインが正常に有効化されました。', 'ktpwp' ), 60 );
        }

        // 初回有効化後、最初の管理画面読み込みで KantanPro 設定へ誘導（空白の管理トップを避ける）
        update_option( 'ktpwp_pending_admin_settings_redirect', true );

        // 固定ページ＋ショートコード利用時の 404 を防ぐため、リライトルールを再生成
        flush_rewrite_rules( false );
        
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
        
		// エラーが発生した場合でも基本的な設定は保存（DBバージョンは更新しない）
		update_option( 'ktpwp_version', KANTANPRO_PLUGIN_VERSION );
        
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

    // 4. マイグレーション履歴の確認（ktp_migration_* はマイグレーションファイル実行時のフラグ名と一致させる）
    $migration_hit = $wpdb->get_var(
        "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'ktpwp_migration_%' OR option_name LIKE 'ktp_migration_%' LIMIT 1"
    );

    if ( ! empty( $migration_hit ) ) {
        set_transient( 'ktpwp_new_installation_check', false, HOUR_IN_SECONDS );
        return false; // 既存環境（マイグレーション履歴あり）
    }

    // 5. データベースバージョンの確認
    $db_version = get_option( 'ktpwp_db_version', '0.0.0' );
    if ( $db_version !== '0.0.0' && ! empty( $db_version ) ) {
        set_transient( 'ktpwp_new_installation_check', false, HOUR_IN_SECONDS );
        return false; // 既存環境（DBバージョン設定済み）
    }

    // 6. ここまで来た時点で $has_data は true（手順2で空テーブルなら既に return 済み）。
    // オプション欠落・DBバージョン未設定でも、メインテーブルに行があれば既存環境（誤って新規扱いにしない）。
    set_transient( 'ktpwp_new_installation_check', false, HOUR_IN_SECONDS );
    return false;
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
                        'message' => __( '受注書を作成しました。', 'ktpwp' ),
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
        set_transient( 'ktpwp_activation_message', __( 'KantanProプラグインが正常に有効化されました。すべての機能が利用可能です。', 'ktpwp' ), 60 );
        
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP: プラグイン有効化処理が正常に完了' );
        }
        
    } catch ( Exception $e ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP: プラグイン有効化処理でエラーが発生: ' . $e->getMessage() );
        }
        
		// エラーが発生した場合でも基本的な設定は保存（DBバージョンは更新しない）
		update_option( 'ktpwp_version', KANTANPRO_PLUGIN_VERSION );
        
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

        if ( class_exists( 'KTPWP_Contract_Reminder_Mail' ) ) {
            KTPWP_Contract_Reminder_Mail::unschedule();
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


// 受注書：メール履歴・案件ファイルのダウンロード（admin-post）
add_action(
	'plugins_loaded',
	static function () {
		if ( ! defined( 'KTPWP_PLUGIN_DIR' ) ) {
			return;
		}
		$path = KTPWP_PLUGIN_DIR . 'includes/class-ktpwp-order-auxiliary.php';
		if ( file_exists( $path ) ) {
			require_once $path;
			if ( class_exists( 'KTPWP_Order_Auxiliary' ) ) {
				KTPWP_Order_Auxiliary::register_hooks();
			}
		}
	},
	14
);

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
 * 受注テーブルに client_department_id カラムを追加する関数
 */
function ktpwp_add_order_client_department_column() {
    global $wpdb;

    $order_table = $wpdb->prefix . 'ktp_order';
    $table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $order_table ) );

    if ( $table_exists !== $order_table ) {
        return false;
    }

    $column_exists = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM `{$order_table}` LIKE %s", 'client_department_id' ) );
    if ( ! empty( $column_exists ) ) {
        return true;
    }

    $result = $wpdb->query( "ALTER TABLE {$order_table} ADD COLUMN client_department_id INT NULL DEFAULT NULL COMMENT '依頼元部署ID' AFTER client_id" );
    if ( $result === false ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP: 受注テーブルへの client_department_id カラム追加に失敗しました。エラー: ' . $wpdb->last_error );
        }
        return false;
    }

    $wpdb->query( "ALTER TABLE {$order_table} ADD INDEX client_department_id (client_department_id)" );

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
        set_transient( 'ktpwp_upgrade_message', __( 'KantanProプラグインが正常に更新されました。適格請求書ナンバー機能も含まれています。', 'ktpwp' ), 60 );
        // マイグレーション成功後「次にやること」を表示するためのフラグ
        set_transient( 'ktpwp_show_update_complete_guide', '1', 600 );
        // 更新結果画面から設定ページへ自動遷移するための専用フラグ
        set_transient( 'ktpwp_redirect_to_settings_after_update', '1', 600 );

        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP: Plugin upgrade migration completed successfully' );
        }
        
    } catch ( Exception $e ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP: Plugin upgrade migration failed: ' . $e->getMessage() );
        }
        
        // エラー情報を詳細に記録（管理者・ログ用）
        update_option( 'ktpwp_upgrade_error', $e->getMessage() );
        update_option( 'ktpwp_upgrade_error_timestamp', current_time( 'mysql' ) );
        update_option( 'ktpwp_upgrade_error_count', get_option( 'ktpwp_upgrade_error_count', 0 ) + 1 );
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
        $db_update_required_text = __( 'データベースの更新が必要です。', 'ktpwp' );
        $update_now_text         = __( '今すぐ更新', 'ktpwp' );
        $updating_text           = __( '更新中...', 'ktpwp' );
        $update_done_text        = __( '更新完了', 'ktpwp' );
        $unknown_error_text      = __( '不明なエラー', 'ktpwp' );
        $update_failed_prefix    = __( '更新に失敗しました: ', 'ktpwp' );
        $network_error_text      = __( '更新に失敗しました。ネットワークエラーが発生しました。', 'ktpwp' );

        echo '<div class="notice notice-warning is-dismissible">';
        echo '<p><strong>' . esc_html( KANTANPRO_PLUGIN_NAME ) . ':</strong> ' . esc_html( $db_update_required_text ) . ' ';
        echo '<button type="button" class="button button-primary button-small" id="ktpwp-manual-db-update">' . esc_html( $update_now_text ) . '</button></p>';
        echo '</div>';
        
        // JavaScript for manual database update
        ?>
        <script>
        jQuery(document).ready(function($) {
            $('#ktpwp-manual-db-update').on('click', function() {
                var $button = $(this);
                var originalText = $button.text();
                
                $button.text(<?php echo wp_json_encode( $updating_text ); ?>).prop('disabled', true);
                
                $.post(ajaxurl, {
                    action: 'ktpwp_manual_db_update',
                    nonce: '<?php echo wp_create_nonce( 'ktpwp_manual_db_update' ); ?>'
                }, function(response) {
                    if (response.success) {
                        $button.text(<?php echo wp_json_encode( $update_done_text ); ?>).removeClass('button-primary').addClass('button-secondary');
                        setTimeout(function() {
                            window.location.reload();
                        }, 2000);
                    } else {
                        alert(<?php echo wp_json_encode( $update_failed_prefix ); ?> + (response.data || <?php echo wp_json_encode( $unknown_error_text ); ?>));
                        $button.text(originalText).prop('disabled', false);
                    }
                }).fail(function() {
                    alert(<?php echo wp_json_encode( $network_error_text ); ?>);
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
        $qualified_migration_required_text = __( '適格請求書ナンバー機能のマイグレーションが必要です。プラグインを再有効化してください。', 'ktpwp' );
        $qualified_enable_text             = __( '適格請求書機能を有効化', 'ktpwp' );
        $qualified_enabling_text           = __( '有効化中...', 'ktpwp' );
        $qualified_enabled_text            = __( '有効化完了', 'ktpwp' );
        $qualified_enable_failed_prefix    = __( '有効化に失敗しました: ', 'ktpwp' );
        $qualified_network_error_text      = __( '有効化に失敗しました。ネットワークエラーが発生しました。', 'ktpwp' );
        $unknown_error_text                = __( '不明なエラー', 'ktpwp' );

        echo '<div class="notice notice-warning is-dismissible">';
        echo '<p><strong>' . esc_html( KANTANPRO_PLUGIN_NAME ) . ':</strong> ' . esc_html( $qualified_migration_required_text ) . '</p>';
        echo '<p><button type="button" class="button button-primary" id="ktpwp-run-qualified-invoice-migration">' . esc_html( $qualified_enable_text ) . '</button></p>';
        echo '</div>';
        
        // JavaScript for qualified invoice migration
        ?>
        <script>
        jQuery(document).ready(function($) {
            $('#ktpwp-run-qualified-invoice-migration').on('click', function() {
                var $button = $(this);
                var originalText = $button.text();
                
                $button.text(<?php echo wp_json_encode( $qualified_enabling_text ); ?>).prop('disabled', true);
                
                $.post(ajaxurl, {
                    action: 'ktpwp_run_qualified_invoice_migration',
                    nonce: '<?php echo wp_create_nonce( 'ktpwp_run_qualified_invoice_migration' ); ?>'
                }, function(response) {
                    if (response.success) {
                        $button.text(<?php echo wp_json_encode( $qualified_enabled_text ); ?>).removeClass('button-primary').addClass('button-secondary');
                        setTimeout(function() {
                            window.location.reload();
                        }, 2000);
                    } else {
                        alert(<?php echo wp_json_encode( $qualified_enable_failed_prefix ); ?> + (response.data || <?php echo wp_json_encode( $unknown_error_text ); ?>));
                        $button.text(originalText).prop('disabled', false);
                    }
                }).fail(function() {
                    alert(<?php echo wp_json_encode( $qualified_network_error_text ); ?>);
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
        wp_send_json_error( __( 'セキュリティチェックに失敗しました。', 'ktpwp' ) );
    }
    
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( __( 'この操作を実行する権限がありません。', 'ktpwp' ) );
    }
    
    $plugin_version = KANTANPRO_PLUGIN_VERSION;
    
    try {
        // マイグレーション進行中フラグをクリア（手動更新のため）
        delete_option( 'ktpwp_migration_in_progress' );
        
        // マイグレーション実行
        ktpwp_run_auto_migrations();
        
        // 適格請求書ナンバー機能のマイグレーション（確実に実行）
        if ( function_exists( 'ktpwp_run_qualified_invoice_migration' ) ) {
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
            update_option( 'ktpwp_db_version', $plugin_version );
        }
        
        ktpwp_flush_db_version_cache();
        wp_cache_flush();
        
        wp_send_json_success( __( 'データベースの更新が完了しました。', 'ktpwp' ) );
        
    } catch ( Exception $e ) {
        // 配布先で安全性チェック等で失敗しても、バージョンのみ更新して通知を消す
        delete_option( 'ktpwp_migration_in_progress' );
        update_option( 'ktpwp_db_version', $plugin_version );
        delete_option( 'ktpwp_migration_error' );
        delete_option( 'ktpwp_migration_error_timestamp' );
        ktpwp_flush_db_version_cache();
        wp_cache_flush();
        
        wp_send_json_success( __( 'データベースバージョンを ', 'ktpwp' ) . $plugin_version . ' に更新しました。一部のチェックで問題がありましたが、表示は解消されます。'
        );
    }
}

/**
 * 適格請求書ナンバー機能のマイグレーションを実行するAJAXハンドラー
 */
function ktpwp_handle_qualified_invoice_migration() {
    // セキュリティチェック
    if ( ! wp_verify_nonce( $_POST['nonce'], 'ktpwp_run_qualified_invoice_migration' ) ) {
        wp_send_json_error( __( 'セキュリティチェックに失敗しました。', 'ktpwp' ) );
    }
    
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( __( 'この操作を実行する権限がありません。', 'ktpwp' ) );
    }
    
    try {
        // 適格請求書ナンバー機能のマイグレーションを実行
        if ( function_exists('ktpwp_run_qualified_invoice_migration') ) {
            $result = ktpwp_run_qualified_invoice_migration();
            
            if ( $result ) {
                wp_send_json_success( __( '適格請求書ナンバー機能の有効化が完了しました。', 'ktpwp' ) );
            } else {
                wp_send_json_error( __( '適格請求書ナンバー機能の有効化に失敗しました。', 'ktpwp' ) );
            }
        } else {
            wp_send_json_error( __( '適格請求書ナンバー機能のマイグレーション関数が見つかりません。', 'ktpwp' ) );
        }
        
    } catch ( Exception $e ) {
        wp_send_json_error( __( '有効化に失敗しました: ', 'ktpwp' ) . $e->getMessage() );
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

/**
 * 管理画面通知の保存済み日本語メッセージを現在のロケール向けに変換する。
 *
 * @param string $message Notice message.
 * @return string
 */
function ktpwp_translate_admin_notice_message( $message ) {
    $messages = array(
        'KantanProプラグインが正常にインストールされました。' => __( 'KantanProプラグインが正常にインストールされました。', 'ktpwp' ),
        'KantanProプラグインが正常に有効化されました。' => __( 'KantanProプラグインが正常に有効化されました。', 'ktpwp' ),
        'KantanProプラグインが正常に有効化されました。すべての機能が利用可能です。' => __( 'KantanProプラグインが正常に有効化されました。すべての機能が利用可能です。', 'ktpwp' ),
        'KantanProプラグインが正常に更新されました。適格請求書ナンバー機能も含まれています。' => __( 'KantanProプラグインが正常に更新されました。適格請求書ナンバー機能も含まれています。', 'ktpwp' ),
    );

    return isset( $messages[ $message ] ) ? $messages[ $message ] : $message;
}

function ktpwp_admin_notice_label() {
    return defined( 'KANTANPRO_PLUGIN_NAME' ) ? KANTANPRO_PLUGIN_NAME : 'KantanPro';
}

function ktpwp_admin_notices() {
    $notice_label = ktpwp_admin_notice_label();
    // 有効化完了通知
    if ( get_transient( 'ktpwp_activation_message' ) ) {
        echo '<div class="notice notice-success is-dismissible">';
        echo '<p><strong>' . esc_html( $notice_label ) . ':</strong> ' . esc_html( ktpwp_translate_admin_notice_message( get_transient( 'ktpwp_activation_message' ) ) ) . '</p>';
        echo '</div>';
    }
    
    // 有効化エラー通知
    if ( get_transient( 'ktpwp_activation_error' ) ) {
        echo '<div class="notice notice-error is-dismissible">';
        echo '<p><strong>' . esc_html( $notice_label ) . ':</strong> ' . esc_html( get_transient( 'ktpwp_activation_error' ) ) . '</p>';
        echo '</div>';
    }
    
    // 新規インストール完了通知
    if ( get_transient( 'ktpwp_new_installation_message' ) ) {
        echo '<div class="notice notice-success is-dismissible">';
        echo '<p><strong>' . esc_html( $notice_label ) . ':</strong> ' . esc_html( get_transient( 'ktpwp_new_installation_message' ) ) . '</p>';
        echo '</div>';
    }
    
    // 新規インストールエラー通知
    if ( get_transient( 'ktpwp_new_installation_error' ) ) {
        echo '<div class="notice notice-error is-dismissible">';
        echo '<p><strong>' . esc_html( $notice_label ) . ':</strong> ' . esc_html( get_transient( 'ktpwp_new_installation_error' ) ) . '</p>';
        echo '</div>';
    }
    
    // 再有効化完了通知
    if ( get_transient( 'ktpwp_reactivation_message' ) ) {
        echo '<div class="notice notice-success is-dismissible">';
        echo '<p><strong>' . esc_html( $notice_label ) . ':</strong> ' . esc_html( get_transient( 'ktpwp_reactivation_message' ) ) . '</p>';
        echo '</div>';
    }
    
    // 再有効化エラー通知
    if ( get_transient( 'ktpwp_reactivation_error' ) ) {
        echo '<div class="notice notice-error is-dismissible">';
        echo '<p><strong>' . esc_html( $notice_label ) . ':</strong> ' . esc_html( get_transient( 'ktpwp_reactivation_error' ) ) . '</p>';
        echo '</div>';
    }
    
    // アップデート完了通知
    if ( get_transient( 'ktpwp_upgrade_message' ) ) {
        echo '<div class="notice notice-success is-dismissible">';
        echo '<p><strong>' . esc_html( $notice_label ) . ':</strong> ' . esc_html( ktpwp_translate_admin_notice_message( get_transient( 'ktpwp_upgrade_message' ) ) ) . '</p>';
        echo '</div>';
    }
}

// 管理画面での通知表示（管理画面でのみ実行）
if ( is_admin() ) {
    add_action( 'admin_notices', 'ktpwp_admin_notices' );
}

/**
 * プラグイン更新結果画面（update.php）で「次にやること」をフッターに表示
 */
add_action( 'admin_footer', 'ktpwp_footer_update_complete_guide' );
function ktpwp_footer_update_complete_guide() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    $screen = get_current_screen();
    if ( ! $screen || strpos( $screen->id, 'update' ) === false ) {
        return;
    }

    $should_show_guide = get_transient( 'ktpwp_show_update_complete_guide' );
    $should_redirect = get_transient( 'ktpwp_redirect_to_settings_after_update' );

    if ( ! $should_show_guide && ! $should_redirect ) {
        return;
    }

    $should_redirect = (bool) $should_redirect || (bool) $should_show_guide;
    delete_transient( 'ktpwp_show_update_complete_guide' );
    delete_transient( 'ktpwp_redirect_to_settings_after_update' );
    $plugins_url = admin_url( 'plugins.php' );
    $settings_url = admin_url( 'admin.php?page=ktp-settings&ktpwp_updated=1' );
    echo '<div class="notice notice-success" style="margin:20px 0;padding:20px;border-left:4px solid #00a32a;background:#f0f9f0;border-radius:4px;">';
    echo '<p style="font-size:15px;margin:0 0 8px 0;"><strong>✅ KantanProEX の更新とマイグレーションが完了しました。</strong></p>';
    echo '<p style="margin:0 0 12px 0;">プラグイン一覧へ戻ります。通常の管理画面読み込み後に設定を開いてください。</p>';
    echo '<p style="margin:0;"><a href="' . esc_url( $plugins_url ) . '" class="button button-primary">プラグイン一覧へ</a> ';
    echo '<a href="' . esc_url( $settings_url ) . '" class="button">KantanProEX 設定を開く</a></p>';
    echo '</div>';

    if ( $should_redirect ) {
        echo '<script>';
        echo 'window.setTimeout(function(){ window.top.location.href = ' . wp_json_encode( add_query_arg( 'ktpwp_updated', '1', $plugins_url ) ) . '; }, 800);';
        echo '</script>';
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

		if ( class_exists( 'KTPWP_Public_Product_Order' )
			&& ( ! function_exists( 'ktpwp_is_feature_enabled' ) || ktpwp_is_feature_enabled( 'public_products' ) ) ) {
			KTPWP_Public_Product_Order::get_instance();
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
    if ( class_exists( 'KTPWP_Settings' ) ) {
        $rest_api_restricted = KTPWP_Settings::get_setting( 'rest_api_restricted', '1' );
        if ( $rest_api_restricted !== '1' ) {
            return $result;
        }

        // REST API制限の完全無効化設定をチェック
        $disable_rest_api_restriction = KTPWP_Settings::get_setting( 'disable_rest_api_restriction', '0' );
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
            has_shortcode( $post->post_content, 'ktpwp_all_tab' ) ||
            has_shortcode( $post->post_content, 'kantanpro_ex' )
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
// アクティベーションフックのために class-ktpwp-settings.php は常にインクルード
if ( file_exists( MY_PLUGIN_PATH . 'includes/class-ktpwp-settings.php' ) ) {
    include_once MY_PLUGIN_PATH . 'includes/class-ktpwp-settings.php';
} else {
    add_action(
        'admin_notices',
        function () {
			echo '<div class="notice notice-error"><p>' . __( 'KTPWP Critical Error: includes/class-ktpwp-settings.php not found.', 'ktpwp' ) . '</p></div>';
		}
    );
}

add_action( 'plugins_loaded', 'KTPWP_Index' );

/**
 * ショートコード登録の保険処理。
 * KTPWP_Index 経由で ktpwp_all_tab のみ登録された場合でも、公開用ショートコード等を確実に登録する。
 */
function ktpwp_ensure_shortcodes_registered() {
    if ( ! get_option( 'ktp_service_is_public_migration_completed' ) ) {
        $migration_file = plugin_dir_path( __FILE__ ) . 'includes/migrations/20260611_add_is_public_to_service.php';
        if ( file_exists( $migration_file ) ) {
            require_once $migration_file;
        }
    }

    if ( ! get_option( 'ktp_service_stock_migration_completed' ) ) {
        $migration_file = plugin_dir_path( __FILE__ ) . 'includes/migrations/20260617_add_stock_to_service.php';
        if ( file_exists( $migration_file ) ) {
            require_once $migration_file;
        }
    }

    if ( ! get_option( 'ktp_service_public_quantity_fixed_migration_completed' ) ) {
        $migration_file = plugin_dir_path( __FILE__ ) . 'includes/migrations/20260618_add_public_quantity_fixed_to_service.php';
        if ( file_exists( $migration_file ) ) {
            require_once $migration_file;
        }
    }

    if ( ! get_option( 'ktp_service_public_html_migration_completed' ) ) {
        $migration_file = plugin_dir_path( __FILE__ ) . 'includes/migrations/20260619_add_public_html_to_service.php';
        if ( file_exists( $migration_file ) ) {
            require_once $migration_file;
        }
    }

    if ( ! get_option( 'ktp_service_public_instant_purchase_migration_completed' ) ) {
        $migration_file = plugin_dir_path( __FILE__ ) . 'includes/migrations/20260620_add_public_instant_purchase_to_service.php';
        if ( file_exists( $migration_file ) ) {
            require_once $migration_file;
        }
    }

    if ( ! class_exists( 'KTPWP_Shortcodes' ) ) {
        $shortcodes_file = plugin_dir_path( __FILE__ ) . 'includes/class-ktpwp-shortcodes.php';
        if ( file_exists( $shortcodes_file ) ) {
            require_once $shortcodes_file;
        }
    }

    if ( ! class_exists( 'KTPWP_Shortcodes' ) ) {
        return;
    }

    $shortcodes = KTPWP_Shortcodes::get_instance();
    $shortcodes->register_shortcodes();
}
add_action( 'init', 'ktpwp_ensure_shortcodes_registered', 20 );

/**
 * フロントで KantanPro 業務画面（[ktpwp_all_tab] 等）が表示されているか判定する。
 *
 * 公開商品ショートコード（ktpwp_public_products）のみのページは対象外。
 *
 * @return bool
 */
function ktpwp_is_frontend_kantanpro_app_page() {
    if ( is_admin() ) {
        return false;
    }

    if ( isset( $_GET['tab_name'] ) && (string) wp_unslash( $_GET['tab_name'] ) !== '' ) {
        return true;
    }

    global $post;
    if ( ! $post instanceof WP_Post ) {
        return false;
    }

    $content = (string) $post->post_content;
    if ( $content === '' ) {
        return false;
    }

    $shortcodes = array( 'kantanAllTab', 'ktpwp_all_tab', 'kantanpro_ex', 'ktpwp_login_error' );
    foreach ( $shortcodes as $shortcode ) {
        if ( has_shortcode( $content, $shortcode ) ) {
            return true;
        }
    }

    return false;
}

/**
 * フロントで KantanPro 業務画面向け CSS/JS を読み込むか。
 *
 * @return bool
 */
function ktpwp_should_enqueue_frontend_assets() {
    $load = ktpwp_is_frontend_kantanpro_app_page();

    return (bool) apply_filters( 'ktpwp_should_enqueue_frontend_assets', $load );
}

function ktpwp_scripts_and_styles( $hook = '' ) {
    // 管理画面ではフロント用 JS/CSS・Google CDN の jQuery を読み込まない。
    // コアの jQuery / メニュー用スクリプトを上書きすると、ダッシュボードなど左メニューのサブメニューが表示されなくなる。
    // KantanPro 管理画面向けアセットは includes/class-ktpwp-assets.php の admin_enqueue_scripts で読み込む。
    if ( is_admin() ) {
        $current_screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        $current_page   = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
        $current_action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : '';

        $is_site_health_page = (
            ( $current_screen && (
                $current_screen->id === 'tools_page_site-health' ||
                $current_screen->id === 'site-health_page_site-health' ||
                strpos( (string) $current_screen->id, 'site-health' ) !== false
            ) ) ||
            $current_page === 'site-health' ||
            $current_action === 'site-health' ||
            ( isset( $_SERVER['REQUEST_URI'] ) && strpos( (string) $_SERVER['REQUEST_URI'], 'site-health' ) !== false )
        );

        if ( $is_site_health_page ) {
            wp_enqueue_style( 'ktpwp-site-health-reset', plugins_url( 'css/site-health-reset.css', __FILE__ ) . '?v=' . time(), array(), KANTANPRO_PLUGIN_VERSION, 'all' );
        }

        return;
    }

    if ( ! ktpwp_should_enqueue_frontend_assets() ) {
        return;
    }

    wp_enqueue_script(
        'ktp-number-format',
        plugins_url( 'js/ktp-number-format.js', __FILE__ ),
        array(),
        KANTANPRO_PLUGIN_VERSION,
        true
    );
    wp_enqueue_script( 'ktp-js', plugins_url( 'js/ktp-js.js', __FILE__ ) . '?v=' . time(), array( 'jquery', 'ktp-number-format' ), null, true );

    // デバッグモードの設定（WP_DEBUGまたは開発環境でのみ有効）
    $debug_mode = ( defined( 'WP_DEBUG' ) && WP_DEBUG ) || ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG );
    wp_add_inline_script( 'ktp-js', 'var ktpwpDebugMode = ' . json_encode( $debug_mode ) . ';' );

    // コスト項目トグル用の国際化ラベルをJSに渡す
    wp_add_inline_script( 'ktp-js', 'var ktpwpCostShowLabel = ' . json_encode( '表示' ) . ';' );
    wp_add_inline_script( 'ktp-js', 'var ktpwpCostHideLabel = ' . json_encode( '非表示' ) . ';' );
    wp_add_inline_script( 'ktp-js', 'var ktpwpStaffChatShowLabel = ' . json_encode( '表示' ) . ';' );
    wp_add_inline_script( 'ktp-js', 'var ktpwpStaffChatHideLabel = ' . json_encode( '非表示' ) . ';' );

    // スタイルを読み込み（フロントエンドのみ）
    wp_register_style( 'ktp-css', plugins_url( 'css/styles.css', __FILE__ ) . '?v=' . time(), array(), KANTANPRO_PLUGIN_VERSION, 'all' );
    wp_enqueue_style( 'ktp-css' );
    // 進捗プルダウン用のスタイルシートを追加
    wp_enqueue_style( 'ktp-progress-select', plugins_url( 'css/progress-select.css', __FILE__ ) . '?v=' . time(), array( 'ktp-css' ), KANTANPRO_PLUGIN_VERSION, 'all' );
    // 設定タブ用のスタイルシートを追加
    wp_enqueue_style( 'ktp-setting-tab', plugins_url( 'css/ktp-setting-tab.css', __FILE__ ) . '?v=' . time(), array( 'ktp-css' ), KANTANPRO_PLUGIN_VERSION, 'all' );
    // レポートタブ用のスタイルシートを追加
    wp_enqueue_style( 'ktp-report', plugins_url( 'css/ktp-report.css', __FILE__ ) . '?v=' . time(), array( 'ktp-css' ), KANTANPRO_PLUGIN_VERSION, 'all' );

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

    // ajaxurl をフロントエンドに渡す（nonce は AJAX クラス / Assets で設定するため、ここでは上書きしない）
    wp_add_inline_script( 'ktp-js', 'var ajaxurl = ' . json_encode( admin_url( 'admin-ajax.php' ) ) . ';' );

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

    // 協力会社職能テーブル（Supplier_Data::get_schema では get_schema 未実装のため未作成になるため明示的に作成）
    if ( ! class_exists( 'KTPWP_Supplier_Skills' ) ) {
        require_once KANTANPRO_PLUGIN_DIR . 'includes/class-ktpwp-supplier-skills.php';
    }
    if ( class_exists( 'KTPWP_Supplier_Skills' ) ) {
        KTPWP_Supplier_Skills::get_instance()->create_table();
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


/**
 * ブロックエディター保存（REST API）や Ajax 中にショートコードの重い描画を抑止する。
 *
 * @return bool
 */
function ktpwp_should_suppress_shortcode_output() {
	if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
		return true;
	}
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		return true;
	}
	if ( function_exists( 'wp_is_json_request' ) && wp_is_json_request() ) {
		return true;
	}
	return false;
}

function KTPWP_Index() {

    // すべてのタブのショートコード[kantanAllTab]
    function kantanAllTab() {

        if ( ktpwp_should_suppress_shortcode_output() ) {
            return '';
        }

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
            $logged_in_users_html       = '';
            $readonly_profile_suffix_ktp = '';

            if ( is_user_logged_in() ) {
                $shortcodes_instance = KTPWP_Shortcodes::get_instance();
                $logged_in_raw        = $shortcodes_instance->get_staff_avatars_display();
                $modal_pos            = strpos( $logged_in_raw, '<div id="ktp-readonly-profile-modal"' );
                if ( false !== $modal_pos ) {
                    $logged_in_users_html       = substr( $logged_in_raw, 0, $modal_pos );
                    $readonly_profile_suffix_ktp = substr( $logged_in_raw, $modal_pos );
                } else {
                    $logged_in_users_html = $logged_in_raw;
                }
            }

            // バージョン番号を定数から取得
            $plugin_version = defined( 'MY_PLUGIN_VERSION' ) ? esc_html( MY_PLUGIN_VERSION ) : '';

            // プラグイン名とバージョンを定数から取得
            $plugin_name = esc_html( KANTANPRO_PLUGIN_NAME );
            $plugin_version = esc_html( KANTANPRO_PLUGIN_VERSION );
            $update_link_url = esc_url( KTPWP_Main::get_current_page_base_url() );

            // ヘッダー更新バッジ表示判定（ナビリンク生成前）
            $show_update_badge = false;
            $update_settings = get_option( 'ktp_update_notification_settings', array() );
            $enable_notifications = isset( $update_settings['enable_notifications'] ) ? $update_settings['enable_notifications'] : true;
            if ( $enable_notifications && class_exists( 'KTPWP_Update_Checker' ) ) {
                global $ktpwp_update_checker;
                if ( $ktpwp_update_checker ) {
                    $ktpwp_update_checker->maybe_run_badge_update_check();
                    $show_update_badge = $ktpwp_update_checker->has_header_update_badge();
                }
            }

            // ログインしているユーザーのみにナビゲーションリンクを表示
            $navigation_links = '';
            global $ktpwp_update_checker;
            if ( is_user_logged_in() && $current_user && $current_user->ID > 0 ) {
                // セッションの有効性も確認
                $user_sessions = WP_Session_Tokens::get_instance( $current_user->ID );
                if ( $user_sessions && ! empty( $user_sessions->get_all() ) ) {
                    // ログアウトボタン
                    $navigation_links .= ' <a href="' . $logout_link . '" title="ログアウト" style="display: inline-flex; align-items: center; gap: 4px; color: #0073aa; text-decoration: none;"><span class="material-symbols-outlined" style="font-size: 20px; vertical-align: middle;">logout</span></a>';
                    // 更新リンクは編集者権限があり、かつ更新通知対象ロールの場合のみ
                    $can_show_update_check = current_user_can( 'edit_posts' );
                    if ( $enable_notifications && $ktpwp_update_checker ) {
                        $can_show_update_check = $ktpwp_update_checker->user_has_notification_permission();
                    }
                    if ( $can_show_update_check ) {
                        if ( $enable_notifications ) {
                            $update_check_title = $show_update_badge ? esc_attr__( '更新が利用可能です', 'ktpwp' ) : esc_attr__( '更新チェック', 'ktpwp' );
                            $update_link_class = $show_update_badge ? ' has-update' : '';
                            $update_badge_markup = '<span class="ktpwp-header-update-badge' . ( $show_update_badge ? ' is-visible' : '' ) . '" aria-hidden="true"></span>';
                            // 更新通知機能付きのリンク
                            $refresh_icon = class_exists( 'KTPWP_SVG_Icons' )
                                ? KTPWP_SVG_Icons::get_icon( 'refresh', array( 'style' => 'font-size: 20px; vertical-align: middle;' ) )
                                : '<span class="material-symbols-outlined" style="font-size: 20px; vertical-align: middle;">refresh</span>';
                            $navigation_links .= ' <a href="#" id="ktpwp-header-update-check" class="ktpwp-header-update-link' . esc_attr( $update_link_class ) . '" title="' . $update_check_title . '" style="display: inline-flex; align-items: center; gap: 4px; color: #0073aa; text-decoration: none; cursor: pointer; position: relative;">' . $refresh_icon . $update_badge_markup . '</a>';
                        } else {
                            // 通常のページリロードリンク
                            $navigation_links .= ' <a href="' . $update_link_url . '" title="更新" style="display: inline-flex; align-items: center; gap: 4px; color: #0073aa; text-decoration: none;"><span class="material-symbols-outlined" style="font-size: 20px; vertical-align: middle;">refresh</span></a>';
                        }
                        $navigation_links .= ' ' . $act_key;
                    }
                    // ヘルプリンク（外部リンク）
                    $navigation_links .= ' <a href="https://www.kantanpro.com/docs" target="_blank" title="' . esc_attr__( 'ヘルプ', 'ktpwp' ) . '" style="display: inline-flex; align-items: center; gap: 4px; color: #0073aa; text-decoration: none;">' . KTPWP_SVG_Icons::get_icon('help', array('style' => 'font-size: 20px; vertical-align: middle;')) . '<span>' . esc_html__( 'ヘルプ', 'ktpwp' ) . '</span></a>';
                    // 設定メニュー（クリックで一般設定・スタッフ管理・バックアップを表示）
                    $navigation_links .= ' <span class="ktp-header-settings-menu">'
                        . '<button type="button" class="ktp-header-settings-toggle" aria-expanded="false" aria-haspopup="true" data-settings-url="' . esc_url( admin_url( 'admin.php?page=ktp-settings' ) ) . '" title="' . esc_attr__( '設定', 'ktpwp' ) . '">'
                        . KTPWP_SVG_Icons::get_icon('settings', array('style' => 'font-size: 20px; vertical-align: middle;'))
                        . '<span>' . esc_html__( '設定', 'ktpwp' ) . '</span>'
                        . '</button>'
                        . '<span class="ktp-header-settings-dropdown" role="menu">'
                        . '<a href="' . esc_url( admin_url( 'admin.php?page=ktp-settings' ) ) . '" role="menuitem">' . esc_html__( '一般設定', 'ktpwp' ) . '</a>'
                        . '<a href="' . esc_url( admin_url( 'admin.php?page=ktp-staff' ) ) . '" role="menuitem">' . esc_html__( 'スタッフ管理', 'ktpwp' ) . '</a>'
                        . '<a href="' . esc_url( admin_url( 'admin.php?page=ktp-data-tools' ) ) . '" role="menuitem">' . esc_html__( 'バックアップ', 'ktpwp' ) . '</a>'
                        . '</span>'
                        . '</span>';
                }
            }

            // システム名はプラグイン定数を優先して固定表示（無料版の残存オプション値に影響されない）
            $system_name = defined( 'KANTANPRO_PLUGIN_NAME' ) ? KANTANPRO_PLUGIN_NAME : 'KantanProEX';
            $system_description = defined( 'KANTANPRO_PLUGIN_DESCRIPTION' )
                ? KANTANPRO_PLUGIN_DESCRIPTION
                : 'スモールビジネスのための販売支援ツール';

            // ロゴは一般設定で固定の icon.png。オプション ktp_logo_image は zip 展開フォルダ移行や旧URLのまま残ると 404 になるため、表示は常に現行プラグインルート基準の URL とする。
            $logo_url = ktpwp_plugin_asset_url( 'images/default/icon.png' );

            // 更新通知設定を確認
            // 更新情報を取得（吹き出し表示用。バッジは $show_update_badge を使用）
            $update_data = null;
            if ( $enable_notifications && class_exists( 'KTPWP_Update_Checker' ) ) {
                global $ktpwp_update_checker;
                if ( $ktpwp_update_checker ) {
                    $update_data = $ktpwp_update_checker->check_header_update();
                    $show_update_badge = $ktpwp_update_checker->has_header_update_badge();
                }
            }
            
            // SVGアイコンに置換
            if (class_exists('KTPWP_SVG_Icons')) {
                $navigation_links = KTPWP_SVG_Icons::replace_material_symbols($navigation_links);
            }
            
            $front_message = '<div class="ktp_header">'
                . '<div class="parent">'
                . '<div class="logo-and-system-info">'
                . '<button type="button" class="ktp-header-plugin-reload" title="' . esc_attr__( 'リロード', 'ktpwp' ) . '" aria-label="' . esc_attr__( 'リロード', 'ktpwp' ) . '" onclick="window.location.reload();">'
                . '<img src="' . esc_url( $logo_url ) . '" data-src="' . esc_url( $logo_url ) . '" alt="' . esc_attr( $system_name ) . '" class="header-logo ktp-header-plugin-icon" width="40" height="40" decoding="async" loading="eager" fetchpriority="high" data-no-lazy="1" style="height:40px;vertical-align:middle;margin-right:12px;position:relative;top:-2px;">'
                . '</button>'
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
                . '</div>'
                . $readonly_profile_suffix_ktp;

            $front_message .= '<script>(function(){'
                . 'var logoUrl=' . wp_json_encode( esc_url_raw( $logo_url ) ) . ';'
                . 'function fixKtpHeaderLogo(){'
                . 'var img=document.querySelector(".ktp_header .header-logo");'
                . 'if(!img){return;}'
                . 'img.setAttribute("src",logoUrl);'
                . 'img.setAttribute("data-src",logoUrl);'
                . 'img.setAttribute("loading","eager");'
                . 'img.setAttribute("data-no-lazy","1");'
                . '}'
                . 'fixKtpHeaderLogo();'
                . 'if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",fixKtpHeaderLogo);}else{setTimeout(fixKtpHeaderLogo,0);}'
                . '})();</script>';

            $front_message .= '<script>(function(){'
                . 'if(window.__ktpHeaderSettingsMenuInit){return;}window.__ktpHeaderSettingsMenuInit=true;'
                . 'function positionMenu(menu){var btn=menu&&menu.querySelector(".ktp-header-settings-toggle");var dropdown=menu&&menu.querySelector(".ktp-header-settings-dropdown");if(!btn||!dropdown){return;}var rect=btn.getBoundingClientRect();dropdown.style.position="fixed";dropdown.style.zIndex="2147483647";dropdown.style.top=(rect.bottom+6)+"px";var left=Math.max(8,Math.min(rect.right-dropdown.offsetWidth,window.innerWidth-dropdown.offsetWidth-8));dropdown.style.left=left+"px";dropdown.style.right="auto";}'
                . 'function closeMenus(except){document.querySelectorAll(".ktp-header-settings-menu.is-open").forEach(function(menu){if(menu!==except){menu.classList.remove("is-open");var btn=menu.querySelector(".ktp-header-settings-toggle");var dropdown=menu.querySelector(".ktp-header-settings-dropdown");if(btn){btn.setAttribute("aria-expanded","false");}if(dropdown){dropdown.removeAttribute("style");}}});}'
                . 'function repositionOpenMenus(){document.querySelectorAll(".ktp-header-settings-menu.is-open").forEach(positionMenu);}'
                . 'document.addEventListener("click",function(e){'
                . 'var toggle=e.target.closest(".ktp-header-settings-toggle");'
                . 'if(toggle){e.preventDefault();if(window.matchMedia&&window.matchMedia("(max-width: 768px)").matches){window.location.href=toggle.getAttribute("data-settings-url")||"' . esc_js( admin_url( 'admin.php?page=ktp-settings' ) ) . '";return;}var menu=toggle.closest(".ktp-header-settings-menu");var isOpen=menu&&menu.classList.contains("is-open");closeMenus(menu);if(menu){menu.classList.toggle("is-open",!isOpen);toggle.setAttribute("aria-expanded",isOpen?"false":"true");if(!isOpen){requestAnimationFrame(function(){positionMenu(menu);});}}return;}'
                . 'if(!e.target.closest(".ktp-header-settings-menu")){closeMenus(null);}'
                . '});'
                . 'window.addEventListener("resize",repositionOpenMenus);'
                . 'window.addEventListener("scroll",repositionOpenMenus,true);'
                . 'document.addEventListener("keydown",function(e){if(e.key==="Escape"){closeMenus(null);}});'
                . '})();</script>';
            
            // 更新通知用のアセットをフッターへ登録（jQuery 読み込み後に実行されるよう保証）
            if ( class_exists( 'KTPWP_Update_Checker' ) ) {
                global $ktpwp_update_checker;
                if ( $ktpwp_update_checker ) {
                    $ktpwp_update_checker->enqueue_header_update_balloon_assets(
                        (bool) $show_update_badge,
                        is_array( $update_data ) ? $update_data : null,
                        (bool) $enable_notifications
                    );
                }
            }
            
            // デバッグ用のHTMLコメントを追加
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                $front_message .= '<!-- KantanPro Debug: update balloon assets enqueued -->';
            }
            // POST 時は tab_name を POST から優先（検索フォーム送信でタブが維持されるようにする）
            $tab_name = ( isset( $_POST['tab_name'] ) && is_string( $_POST['tab_name'] ) )
                ? sanitize_text_field( wp_unslash( $_POST['tab_name'] ) )
                : ( isset( $_GET['tab_name'] ) ? sanitize_text_field( wp_unslash( $_GET['tab_name'] ) ) : 'default_tab' );

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
                    $list = new KTPWP_List_Class();
                    $list_content = $list->List_Tab_View( $tab_name );
                    break;
                case 'order':
                    $order = new KTPWP_Order_Class();
                    $order_content = $order->Order_Tab_View( $tab_name );
                    $order_content = $order_content ?? '';
                    break;
                case 'client':
                    $client = new KTPWP_Client_Class();
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
                    $service = new KTPWP_Service_Class();
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
                    if ( function_exists( 'ktpwp_is_feature_enabled' ) && ! ktpwp_is_feature_enabled( 'report' ) ) {
                        if ( ! class_exists( 'KTPWP_Ui_Generator' ) ) {
                            require_once KANTANPRO_PLUGIN_DIR . 'includes/class-ktpwp-ui-generator.php';
                        }
                        $ktpwp_report_ui_title = new KTPWP_Ui_Generator();
                        $report_content        = $ktpwp_report_ui_title->generate_free_edition_report_title_bar();
                        $report_content       .= class_exists( 'KTPWP_Edition' )
                            ? KTPWP_Edition::get_upgrade_message_html( __( 'レポート', 'ktpwp' ) )
                            : '';
                    } else {
                        $report = new KTPWP_Report_Class();
                        $report_content = $report->Report_Tab_View( $tab_name );
                    }
                    break;
                default:
                    // デフォルトの処理
                    $list = new KTPWP_List_Class();
                    $tab_name = 'list';
                    $list_content = $list->List_Tab_View( $tab_name );
                    break;
            }
            // view
            $view = new KTPWP_View_Tabs_Class();
            $tab_view = $view->TabsView( $list_content, $order_content, $client_content, $service_content, $supplier_content, $report_content );
            // KantanProEX では KTP banner を表示しない
            $before_header_banner = '';

            $layout_attrs = class_exists( 'KTPWP_Settings' )
                ? KTPWP_Settings::get_page_layout_wrapper_attributes()
                : 'class="ktpwp-page-layout"';
            $return_value = '<div ' . $layout_attrs . '>' . $before_header_banner . $front_message . $tab_view . '</div>';

            // 出力 HTML 内の /plugins/KantanPro/ を KantanProEX に統一（src/data-src 等に旧パスが残る取りこぼし対策）
            if ( defined( 'KANTANPRO_PLUGIN_CANONICAL_DIR' ) ) {
                $return_value = preg_replace(
                    '#/plugins/KantanPro(?!EX)(/)#i',
                    '/plugins/' . KANTANPRO_PLUGIN_CANONICAL_DIR . '$1',
                    $return_value
                );
            }

            return $return_value;

        } else {
            // ログインしていない場合、または権限がない場合
            if ( ! is_user_logged_in() ) {
                $login_error = new KTPWP_Login_Error();
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
    // KantanProEX 用のショートコード
    add_shortcode( 'kantanpro_ex', 'kantanAllTab' );
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

// 案件名インライン編集は class-ktpwp-ajax.php の ajax_update_project_name で処理



// includes/class-ktpwp-tab-list.php, class-ktpwp-view-tab.php を明示的に読み込む（自動読み込みされていない場合のみ）
if ( ! class_exists( 'KTPWP_Tab_Search_UI' ) ) {
	include_once MY_PLUGIN_PATH . 'includes/class-ktpwp-tab-search-ui.php';
}
if ( ! class_exists( 'KTPWP_List_Class' ) ) {
    include_once MY_PLUGIN_PATH . 'includes/class-ktpwp-tab-list.php';
}
if ( ! class_exists( 'KTPWP_View_Tabs_Class' ) ) {
    include_once MY_PLUGIN_PATH . 'includes/class-ktpwp-view-tab.php';
}
if ( ! class_exists( 'KTPWP_Login_Error' ) ) {
    include_once MY_PLUGIN_PATH . 'includes/class-ktpwp-login-error.php';
}
if ( ! class_exists( 'KTPWP_Report_Class' ) ) {
    include_once MY_PLUGIN_PATH . 'includes/class-ktpwp-tab-report.php';
}

/**
 * メール添付ファイル用一時ファイルクリーンアップ機能
 */

// プラグイン有効化時にクリーンアップスケジュールを設定
register_activation_hook( __FILE__, 'ktpwp_schedule_temp_file_cleanup' );

// プラグイン無効化時にクリーンアップスケジュールを削除
register_deactivation_hook( __FILE__, 'ktpwp_unschedule_temp_file_cleanup' );

/* ==========================================================================
 * プラグイン削除（アンインストール）時のデータ保持設定
 *   - エンドユーザーがプラグイン一覧画面からKantanProを「削除」する際に
 *     「データを残す／完全削除」を明示的に選べるようにする。
 *   - プラグインの行に現在の削除モードを表示し、変更リンクを提示する。
 *   - 「削除」リンク押下時にはどちらのモードで実行されるかを確認する
 *     JavaScriptダイアログを出す。
 * ========================================================================== */

/**
 * 現在のアンインストールモードを取得
 *
 * @return string 'keep_data' | 'full_delete'
 */
function ktpwp_get_uninstall_mode() {
    $options = get_option( 'ktp_uninstall_settings', array() );
    $mode    = isset( $options['uninstall_mode'] ) ? (string) $options['uninstall_mode'] : 'keep_data';
    return ( $mode === 'full_delete' ) ? 'full_delete' : 'keep_data';
}

/**
 * プラグイン一覧の行メタに削除モード表示と変更リンクを追加
 */
add_filter(
    'plugin_row_meta',
    function ( $links, $file ) {
        if ( $file !== plugin_basename( __FILE__ ) ) {
            return $links;
        }
        if ( ! current_user_can( 'delete_plugins' ) ) {
            return $links;
        }

        $mode                  = ktpwp_get_uninstall_mode();
        $is_full               = ( $mode === 'full_delete' );
        $locale                = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();
        $ktpwp_is_english_locale = ( strpos( (string) $locale, 'en' ) === 0 );
        $mode_label            = $is_full
            ? ( $ktpwp_is_english_locale ? 'Full delete' : __( '完全削除', 'ktpwp' ) )
            : ( $ktpwp_is_english_locale ? 'Keep data' : __( 'データを残す', 'ktpwp' ) );
        $mode_color            = $is_full ? '#d63638' : '#00a32a';
        $deletion_behavior_label = $ktpwp_is_english_locale ? 'Deletion behavior' : __( '削除時の動作', 'ktpwp' );
        $change_label          = $ktpwp_is_english_locale ? 'Change' : __( '変更', 'ktpwp' );

        $settings_url = admin_url( 'admin.php?page=ktp-settings#uninstall_setting_section' );

        $badge = sprintf(
            '<span style="display:inline-block;padding:1px 8px;border:1px solid %1$s;border-radius:10px;color:%1$s;font-size:11px;line-height:1.4;vertical-align:middle;">%2$s: %3$s</span>',
            esc_attr( $mode_color ),
            esc_html( $deletion_behavior_label ),
            esc_html( $mode_label )
        );

        $change_link = sprintf(
            '<a href="%s">%s</a>',
            esc_url( $settings_url ),
            esc_html( $change_label )
        );

        $links[] = $badge . ' ' . $change_link;
        return $links;
    },
    10,
    2
);

/**
 * プラグイン一覧画面で「削除」リンク押下時に確認ダイアログを出す
 *
 * WordPress標準の削除確認画面は全プラグイン共通の文言のため、
 * KantanProの削除モードを踏まえた案内を事前に表示する。
 */
add_action(
    'admin_footer-plugins.php',
    function () {
        if ( ! current_user_can( 'delete_plugins' ) ) {
            return;
        }

        $plugin_basename = plugin_basename( __FILE__ );
        $mode            = ktpwp_get_uninstall_mode();
        $settings_url    = admin_url( 'admin.php?page=ktp-settings#uninstall_setting_section' );

        $msg_keep = __(
            "KantanPro を削除します。\n\n現在の設定: 【データを残す】\n→ 顧客・サービス・協力会社・受注書などのデータはデータベースに残ります。\n  後で再インストールすれば、以前のデータを引き続き利用できます。\n\n設定を変更するには、いったんキャンセルして「KantanPro 設定 → 一般設定」から\n「プラグイン削除時のデータ保持設定」を変更してください。\n\nこのまま削除してよろしいですか？",
            'ktpwp'
        );
        $msg_full = __(
            "⚠ 警告: KantanPro を【完全削除】します。\n\n現在の設定: 【完全削除（すべてのデータを消す）】\n→ 顧客・サービス・協力会社・受注書・請求書・設定など、\n  KantanProに関するすべてのデータがデータベースから完全に削除されます。\n  この操作は元に戻せません。\n\n必ず事前にバックアップを取得していることを確認してください。\n\n設定を変更するには、いったんキャンセルして「KantanPro 設定 → 一般設定」から\n「プラグイン削除時のデータ保持設定」を変更してください。\n\n本当にすべてのデータを削除してよろしいですか？",
            'ktpwp'
        );

        $message = ( $mode === 'full_delete' ) ? $msg_full : $msg_keep;
        ?>
<script>
(function(){
    var pluginFile = <?php echo wp_json_encode( $plugin_basename ); ?>;
    var message    = <?php echo wp_json_encode( $message ); ?>;

    document.addEventListener('click', function(e){
        var a = e.target;
        while (a && a.tagName !== 'A') { a = a.parentNode; }
        if (!a || !a.href) return;

        var href = (a.getAttribute('href') || '').split('#')[0];
        // プラグイン更新では DB データは残るため、削除確認は出さない。更新系 URL はすべて除外
        if (/[?&]action=upgrade-plugin\b|[?&]action=update-selected\b|\/update\.php(\?|$)/i.test(href)) {
            return;
        }
        // コアの「削除」は plugins.php のみ（ここ以外のリンクではカスタム確認しない）
        if (href.indexOf('plugins.php') === -1) {
            return;
        }
        if (href.indexOf('action=delete') === -1 && href.indexOf('action=delete-selected') === -1) {
            return;
        }
        if (href.indexOf(encodeURIComponent(pluginFile)) === -1 && href.indexOf(pluginFile) === -1) {
            return;
        }

        if (!window.confirm(message)) {
            e.preventDefault();
            e.stopPropagation();
            return false;
        }
    }, true);
})();
</script>
        <?php
    }
);

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
add_action( 'admin_menu', array( 'KTPWP_Settings', 'add_admin_menu' ) );

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

    if ( class_exists( 'KTPWP_Terms_Of_Service' ) ) {
        KTPWP_Terms_Of_Service::maybe_upgrade_default_terms();
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

    if ( ! class_exists( 'KTPWP_Terms_Of_Service' ) ) {
        return;
    }
    
    $terms_table = $wpdb->prefix . 'ktp_terms_of_service';
    $default_terms = KTPWP_Terms_Of_Service::get_default_terms_content();
    $version = KTPWP_Terms_Of_Service::get_default_terms_version();
    
    $result = $wpdb->insert(
        $terms_table,
        array(
            'terms_content' => $default_terms,
            'version' => $version,
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

    if ( ! class_exists( 'KTPWP_Terms_Of_Service' ) ) {
        return;
    }
    
    $terms_table = $wpdb->prefix . 'ktp_terms_of_service';
    $default_terms = KTPWP_Terms_Of_Service::get_default_terms_content();
    
    $result = $wpdb->update(
        $terms_table,
        array(
            'terms_content' => $default_terms,
            'version' => KTPWP_Terms_Of_Service::get_default_terms_version(),
        ),
        array( 'id' => $terms_id ),
        array( '%s', '%s' ),
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
    if (
        $post &&
        (
            has_shortcode( $post->post_content, 'ktpwp_all_tab' ) ||
            has_shortcode( $post->post_content, 'kantanpro_ex' )
        )
    ) {
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
 * 頻度カウント対象テーブル名を返す。
 *
 * @param string $entity_key client|supplier|service|supplier_skill
 * @return string|null
 */
function ktpwp_get_frequency_table_name( $entity_key ) {
    global $wpdb;

    $tables = array(
        'client'         => $wpdb->prefix . 'ktp_client',
        'supplier'       => $wpdb->prefix . 'ktp_supplier',
        'service'        => $wpdb->prefix . 'ktp_service',
        'supplier_skill' => $wpdb->prefix . 'ktp_supplier_skills',
    );

    $entity_key = sanitize_key( (string) $entity_key );

    return isset( $tables[ $entity_key ] ) ? $tables[ $entity_key ] : null;
}

/**
 * レコードの利用頻度を +1 する。
 *
 * @param string $entity_key client|supplier|service|supplier_skill
 * @param int    $record_id  レコード ID。
 * @return void
 */
function ktpwp_increment_record_frequency( $entity_key, $record_id ) {
    global $wpdb;

    $record_id = absint( $record_id );
    if ( $record_id <= 0 ) {
        return;
    }

    $table_name = ktpwp_get_frequency_table_name( $entity_key );
    if ( ! $table_name ) {
        return;
    }

    $wpdb->query(
        $wpdb->prepare(
            "UPDATE {$table_name} SET frequency = COALESCE(frequency, 0) + 1 WHERE id = %d",
            $record_id
        )
    );
}

/**
 * 詳細表示時の頻度加算（同一 URL の再読み込みでは重複しない）。
 *
 * @param string $entity_key client|supplier|service|supplier_skill
 * @param int    $record_id  レコード ID。
 * @return void
 */
function ktpwp_increment_record_frequency_on_view( $entity_key, $record_id ) {
    if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
        return;
    }

    $record_id = absint( $record_id );
    if ( $record_id <= 0 ) {
        return;
    }

    ktpwp_safe_session_start();

    $uri_hash    = md5( isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '' );
    $session_key = 'ktp_freq_view_' . sanitize_key( (string) $entity_key );
    $dedup_token = sanitize_key( (string) $entity_key ) . ':' . $record_id . ':' . $uri_hash;

    if ( isset( $_SESSION[ $session_key ] ) && $_SESSION[ $session_key ] === $dedup_token ) {
        return;
    }

    ktpwp_increment_record_frequency( $entity_key, $record_id );
    $_SESSION[ $session_key ] = $dedup_token;
}

/**
 * GET 表示時の頻度加算をスキップするか（更新・検索リダイレクト直後は POST 側で加算済み）。
 *
 * @return bool
 */
function ktpwp_should_skip_frequency_on_view() {
    if ( ! isset( $_GET['message'] ) ) {
        return false;
    }

    $message = sanitize_key( wp_unslash( (string) $_GET['message'] ) );

    return in_array( $message, array( 'updated', 'found' ), true );
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
// 更新完了案内を最優先で画面のいちばん上に表示（更新結果画面で隠れないように）
add_action( 'admin_notices', 'ktpwp_show_update_complete_guide', 1 );

/**
 * プラグイン更新・マイグレーション成功後の「次にやること」案内
 */
function ktpwp_show_update_complete_guide() {
    if ( ! current_user_can( 'manage_options' ) || ! get_transient( 'ktpwp_show_update_complete_guide' ) ) {
        return;
    }

    if ( function_exists( 'get_current_screen' ) ) {
        $screen = get_current_screen();
        if ( $screen && strpos( (string) $screen->id, 'update' ) !== false ) {
            // 更新結果画面では admin_footer 側で案内と自動遷移を処理する。
            return;
        }
    }

    delete_transient( 'ktpwp_show_update_complete_guide' );
    $plugins_url = admin_url( 'plugins.php' );
    $settings_url = admin_url( 'admin.php?page=ktp-settings' );
    $notice_label = ktpwp_admin_notice_label();
    echo '<div class="notice notice-success" style="border-left-color:#00a32a;padding:16px 20px;margin:20px 0;">';
    echo '<p style="font-size:15px;margin:0 0 10px 0;"><strong>✅ ' . esc_html( $notice_label ) . ' の更新とマイグレーションが完了しました。</strong></p>';
    echo '<p style="margin:0 0 12px 0;">このあと、下のいずれかで通常どおりご利用いただけます。</p>';
    echo '<p style="margin:0;">';
    echo '<a href="' . esc_url( $plugins_url ) . '" class="button button-primary">プラグイン一覧へ</a> ';
    echo '<a href="' . esc_url( $settings_url ) . '" class="button">' . esc_html( $notice_label ) . ' 設定を開く</a>';
    echo '</p></div>';
}

function ktpwp_distribution_admin_notices() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $notice_label = ktpwp_admin_notice_label();
    
    // 有効化成功通知
    if ( $success_message = get_transient( 'ktpwp_activation_success' ) ) {
        echo '<div class="notice notice-success is-dismissible">';
        echo '<p><strong>' . esc_html( $notice_label ) . ':</strong> ' . esc_html( ktpwp_translate_admin_notice_message( $success_message ) ) . '</p>';
        echo '</div>';
        delete_transient( 'ktpwp_activation_success' );
    }
    
    // 再有効化成功通知
    if ( $success_message = get_transient( 'ktpwp_reactivation_success' ) ) {
        echo '<div class="notice notice-success is-dismissible">';
        echo '<p><strong>' . esc_html( $notice_label ) . ':</strong> ' . esc_html( ktpwp_translate_admin_notice_message( $success_message ) ) . '</p>';
        echo '</div>';
        delete_transient( 'ktpwp_reactivation_success' );
    }
    
    // 新規インストール成功通知
    if ( $success_message = get_transient( 'ktpwp_new_installation_success' ) ) {
        echo '<div class="notice notice-success is-dismissible">';
        echo '<p><strong>' . esc_html( $notice_label ) . ':</strong> ' . esc_html( ktpwp_translate_admin_notice_message( $success_message ) ) . '</p>';
        echo '</div>';
        delete_transient( 'ktpwp_new_installation_success' );
    }
    
    // 有効化エラー通知
    if ( $error_message = get_transient( 'ktpwp_activation_error' ) ) {
        echo '<div class="notice notice-error is-dismissible">';
        echo '<p><strong>' . esc_html( $notice_label ) . ':</strong> ' . esc_html( $error_message ) . '</p>';
        echo '<p><a href="' . esc_url( add_query_arg( 'ktpwp_manual_migration', '1' ) ) . '" class="button">手動マイグレーション実行</a></p>';
        echo '</div>';
        delete_transient( 'ktpwp_activation_error' );
    }
    
    // 再有効化エラー通知
    if ( $error_message = get_transient( 'ktpwp_reactivation_error' ) ) {
        echo '<div class="notice notice-error is-dismissible">';
        echo '<p><strong>' . esc_html( $notice_label ) . ':</strong> ' . esc_html( $error_message ) . '</p>';
        echo '<p><a href="' . esc_url( add_query_arg( 'ktpwp_manual_migration', '1' ) ) . '" class="button">手動マイグレーション実行</a></p>';
        echo '</div>';
        delete_transient( 'ktpwp_reactivation_error' );
    }
    
    // 新規インストールエラー通知
    if ( $error_message = get_transient( 'ktpwp_new_installation_error' ) ) {
        echo '<div class="notice notice-error is-dismissible">';
        echo '<p><strong>' . esc_html( $notice_label ) . ':</strong> ' . esc_html( $error_message ) . '</p>';
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
            echo '<p><strong>' . esc_html( $notice_label ) . ':</strong> 手動マイグレーションを実行しますか？</p>';
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
            echo '<p><strong>' . esc_html( $notice_label ) . ':</strong> invoice_itemsカラム修正を実行しますか？</p>';
            echo '<p><a href="' . esc_url( wp_nonce_url( add_query_arg( 'ktpwp_invoice_items_fix', '1' ), 'ktpwp_invoice_items_fix' ) ) . '" class="button button-primary">実行する</a></p>';
            echo '</div>';
        }
    }
    
    // マイグレーション進行中の通知（スタックしたフラグは自動クリアして非表示）
    if ( get_option( 'ktpwp_migration_in_progress', false ) ) {
        if ( ! ktpwp_clear_stale_migration_in_progress() ) {
            echo '<div class="notice notice-info is-dismissible">';
            echo '<p><strong>' . esc_html( $notice_label ) . ':</strong> データベースの更新を実行中です。完了までお待ちください。</p>';
            echo '</div>';
        }
    }
    
    // データベース更新通知は KantanPro 設定ページ（ktp-*）でのみ ktpwp_admin_migration_status で表示（他画面では表示しない）
    
    // invoice_itemsカラム修正成功通知
    if ( $success_message = get_transient( 'ktpwp_invoice_items_fix_success' ) ) {
        echo '<div class="notice notice-success is-dismissible">';
        echo '<p><strong>' . esc_html( $notice_label ) . ':</strong> ' . esc_html( ktpwp_translate_admin_notice_message( $success_message ) ) . '</p>';
        echo '</div>';
        delete_transient( 'ktpwp_invoice_items_fix_success' );
    }
    
    // invoice_itemsカラム修正エラー通知
    if ( $error_message = get_transient( 'ktpwp_invoice_items_fix_error' ) ) {
        echo '<div class="notice notice-error is-dismissible">';
        echo '<p><strong>' . esc_html( $notice_label ) . ':</strong> ' . esc_html( $error_message ) . '</p>';
        echo '</div>';
        delete_transient( 'ktpwp_invoice_items_fix_error' );
    }
    
    // マイグレーション完了チェック
    $migration_completed = get_option( 'ktp_order_migration_20250108_invoice_items_completed', false );
    $notification_dismissed = get_option( 'ktpwp_invoice_items_fix_notification_dismissed', false );
    
    // マイグレーションが完了していない場合のみ通知を表示
    if ( ! $migration_completed && ! $notification_dismissed ) {
        echo '<div class="notice notice-info is-dismissible" id="ktpwp-invoice-items-fix-notice">';
        echo '<p><strong>' . esc_html( $notice_label ) . ':</strong> データベースエラーが発生している場合は、以下のボタンで修正してください。</p>';
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

            flush_rewrite_rules( false );
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
        wp_die( __( '権限がありません', 'ktpwp' ) );
    }
    
    check_ajax_referer( 'ktpwp_dismiss_notification', 'nonce' );
    
    update_option( 'ktpwp_invoice_items_fix_notification_dismissed', true );
    
    wp_send_json_success( __( '通知が非表示になりました', 'ktpwp' ) );
}

/**
 * DB に invoice_items / cost_items 列が既にあるのにマイグレーション完了フラグだけ欠けている場合、修復通知を出さない
 */
function ktpwp_sync_invoice_items_migration_notice_flag() {
    if ( ! is_admin() || wp_doing_ajax() ) {
        return;
    }
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    if ( get_option( 'ktp_order_migration_20250108_invoice_items_completed', false ) ) {
        return;
    }
    global $wpdb;
    $table_name = $wpdb->prefix . 'ktp_order';
    if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) !== $table_name ) {
        return;
    }
    $existing_columns = $wpdb->get_col( "SHOW COLUMNS FROM `{$table_name}`", 0 );
    if ( ! is_array( $existing_columns ) ) {
        return;
    }
    if ( in_array( 'invoice_items', $existing_columns, true ) && in_array( 'cost_items', $existing_columns, true ) ) {
        update_option( 'ktp_order_migration_20250108_invoice_items_completed', true );
        update_option( 'ktp_order_migration_20250108_invoice_items_timestamp', current_time( 'mysql' ) );
    }
}
add_action( 'admin_init', 'ktpwp_sync_invoice_items_migration_notice_flag', 5 );

/**
 * 有効化直後の最初の管理画面読み込みで KantanPro 設定へ誘導
 */
function ktpwp_redirect_to_settings_after_first_activation() {
    if ( ! is_admin() || wp_doing_ajax() ) {
        return;
    }
    if ( ! get_option( 'ktpwp_pending_admin_settings_redirect' ) ) {
        return;
    }
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
    if ( 'ktp-settings' === $page ) {
        delete_option( 'ktpwp_pending_admin_settings_redirect' );
        return;
    }
    delete_option( 'ktpwp_pending_admin_settings_redirect' );
    wp_safe_redirect( admin_url( 'admin.php?page=ktp-settings' ) );
    exit;
}
add_action( 'admin_init', 'ktpwp_redirect_to_settings_after_first_activation', 1 );

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
        __( 'KantanPro ダミーデータ作成', 'ktpwp' ),
        __( 'KantanPro ダミーデータ', 'ktpwp' ),
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
        return __( 'スクリプトが見つかりません', 'ktpwp' );
    }
    
    $content = file_get_contents($script_path);
    
    // バージョン情報を抽出
    if (preg_match('/バージョン:\s*([0-9]+\.[0-9]+\.[0-9]+)/', $content, $matches)) {
        $version = $matches[1];
        
        // 説明文も抽出
        if (preg_match('/バージョン:\s*[0-9]+\.[0-9]+\.[0-9]+\s*\(([^)]+)\)/', $content, $desc_matches)) {
            $description = $desc_matches[1] === '品名ベース税率設定版' ? __( '品名ベース税率設定版', 'ktpwp' ) : $desc_matches[1];
            return $version . ' (' . $description . ')';
        }
        
        return $version;
    }
    
    return __( 'バージョン情報が見つかりません', 'ktpwp' );
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
        <h1><span class="dashicons dashicons-database" style="margin-right: 10px; font-size: 24px; width: 24px; height: 24px;"></span><?php echo esc_html__( 'KantanPro ダミーデータ作成', 'ktpwp' ); ?></h1>
        
        <div class="ktp-dummy-data-version">
            <p><strong><?php echo esc_html__( 'プラグインバージョン:', 'ktpwp' ); ?></strong> <?php echo esc_html(KANTANPRO_PLUGIN_VERSION); ?></p>
            <p><strong><?php echo esc_html__( 'ダミーデータ作成スクリプトバージョン:', 'ktpwp' ); ?></strong> <?php echo esc_html(ktpwp_get_dummy_data_script_version()); ?></p>
        </div>
        
        <div class="ktp-dummy-data-info">
            <p><strong><?php echo esc_html__( '作成されるデータ:', 'ktpwp' ); ?></strong></p>
            <ul>
                <li><?php echo esc_html__( '顧客: 6件（メールアドレス: info@kantanpro.com）', 'ktpwp' ); ?></li>
                <li><?php echo esc_html__( '受注書: 18件（顧客×6件 × ステータス3パターン：受付中・受注・完成）', 'ktpwp' ); ?></li>
                <li><?php echo esc_html__( '協力会社: 6件（メールアドレス: info@kantanpro.com）', 'ktpwp' ); ?></li>
                <li><?php echo esc_html__( '職能: 18件（協力会社×6件 × 税率3パターン：10%・8%・非課税）', 'ktpwp' ); ?></li>
                <li><?php echo esc_html__( 'サービス: 6件（一般：税率10%・食品：税率8%・不動産：非課税）各×2', 'ktpwp' ); ?></li>
                <li><?php echo esc_html__( '請求項目・コスト項目: 受注書に自動追加', 'ktpwp' ); ?></li>
            </ul>
            <p><strong><?php echo esc_html__( '対象外データ:', 'ktpwp' ); ?></strong></p>
            <ul>
                <li><?php echo esc_html__( 'スタッフチャット: 既存データは削除されますが、新規作成時は初期メッセージを作成しません', 'ktpwp' ); ?></li>
            </ul>
        </div>
        
        <div class="ktp-dummy-data-card">
            <h2><?php echo esc_html__( 'ダミーデータ作成', 'ktpwp' ); ?></h2>
            <p><?php echo esc_html__( 'テスト用のダミーデータを作成します。既存データがある場合は確認メッセージが表示されます。', 'ktpwp' ); ?></p>
            
            <div class="ktp-dummy-data-buttons">
                <button type="button" id="create-dummy-data" class="ktp-dummy-data-button">
                    <?php echo esc_html__( 'ダミーデータを作成', 'ktpwp' ); ?>
                </button>
                
                <button type="button" id="clear-data" class="ktp-dummy-data-button clear-button">
                    <?php echo esc_html__( 'データをクリア', 'ktpwp' ); ?>
                </button>
            </div>
            
            <div id="dummy-data-result"></div>
        </div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        var ktpDummyDataStrings = <?php echo wp_json_encode( array(
            'creating' => __( '作成中...', 'ktpwp' ),
            'creating_message' => __( 'ダミーデータを作成中...', 'ktpwp' ),
            'create_button' => __( 'ダミーデータを作成', 'ktpwp' ),
            'error_prefix' => __( 'エラー: ', 'ktpwp' ),
            'unknown_error' => __( '不明なエラー', 'ktpwp' ),
            'network_error' => __( '通信エラーが発生しました。', 'ktpwp' ),
            'server_error_prefix' => __( 'サーバーエラー: ', 'ktpwp' ),
            'clear_confirm' => __( "本当にデータをクリアしますか？\nこの操作は元に戻せません。", 'ktpwp' ),
            'clearing' => __( 'クリア中...', 'ktpwp' ),
            'clearing_message' => __( 'データをクリア中...', 'ktpwp' ),
            'clear_button' => __( 'データをクリア', 'ktpwp' ),
        ) ); ?>;
        // ダミーデータ作成ボタン
        $('#create-dummy-data').on('click', function() {
            var button = $(this);
            var resultDiv = $('#dummy-data-result');
            
            button.prop('disabled', true).text(ktpDummyDataStrings.creating);
            resultDiv.html('<div class="ktp-dummy-data-result info"><p>' + ktpDummyDataStrings.creating_message + '</p></div>');
            
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
                        resultDiv.html('<div class="ktp-dummy-data-result error"><p>' + ktpDummyDataStrings.error_prefix + (response.data ? response.data.message : ktpDummyDataStrings.unknown_error) + '</p></div>');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', {xhr: xhr, status: status, error: error});
                    var errorMessage = ktpDummyDataStrings.network_error;
                    if (xhr.responseText) {
                        try {
                            var response = JSON.parse(xhr.responseText);
                            if (response.data && response.data.message) {
                                errorMessage = response.data.message;
                            }
                        } catch (e) {
                            errorMessage = ktpDummyDataStrings.server_error_prefix + xhr.status + ' ' + xhr.statusText;
                        }
                    }
                    resultDiv.html('<div class="ktp-dummy-data-result error"><p>' + errorMessage + '</p></div>');
                },
                complete: function() {
                    button.prop('disabled', false).text(ktpDummyDataStrings.create_button);
                }
            });
        });
        
        // データクリアボタン
        $('#clear-data').on('click', function() {
            if (!confirm(ktpDummyDataStrings.clear_confirm)) {
                return;
            }
            
            var button = $(this);
            var resultDiv = $('#dummy-data-result');
            
            button.prop('disabled', true).text(ktpDummyDataStrings.clearing);
            resultDiv.html('<div class="ktp-dummy-data-result info"><p>' + ktpDummyDataStrings.clearing_message + '</p></div>');
            
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
                        resultDiv.html('<div class="ktp-dummy-data-result error"><p>' + ktpDummyDataStrings.error_prefix + (response.data ? response.data.message : ktpDummyDataStrings.unknown_error) + '</p></div>');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Clear Data AJAX Error:', {xhr: xhr, status: status, error: error});
                    var errorMessage = ktpDummyDataStrings.network_error;
                    if (xhr.responseText) {
                        try {
                            var response = JSON.parse(xhr.responseText);
                            if (response.data && response.data.message) {
                                errorMessage = response.data.message;
                            }
                        } catch (e) {
                            errorMessage = ktpDummyDataStrings.server_error_prefix + xhr.status + ' ' + xhr.statusText;
                        }
                    }
                    resultDiv.html('<div class="ktp-dummy-data-result error"><p>' + errorMessage + '</p></div>');
                },
                complete: function() {
                    button.prop('disabled', false).text(ktpDummyDataStrings.clear_button);
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
            wp_send_json_error(array('message' => __( 'セキュリティチェックに失敗しました。', 'ktpwp' )));
            return;
        }
        
        if (!current_user_can('manage_options')) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('KTPWP: 権限がありません');
            }
            wp_send_json_error(array('message' => __( '権限がありません。', 'ktpwp' )));
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
                'message' => __( '既存のデータが存在します。データを削除してから再実行してください。', 'ktpwp' )
            ));
            return;
        }
        
        // 新しいダミーデータ作成スクリプトを実行
        $dummy_data_script = plugin_dir_path(__FILE__) . 'create_dummy_data.php';
        
        if (!file_exists($dummy_data_script)) {
            wp_send_json_error(array('message' => __( 'ダミーデータ作成スクリプトが見つかりません。', 'ktpwp' )));
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

            // include が早期 return false した場合でも成功扱いになっていた不具合を防ぐ
            $client_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}ktp_client" );
            $order_count  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}ktp_order" );
            if ( $client_count < 1 ) {
                wp_send_json_error( array( 'message' => __( 'ダミーデータの作成に失敗しました。必要なテーブルが存在するか、PHPエラーログを確認してください。', 'ktpwp' ) ) );
                return;
            }
            if ( $order_count < 1 ) {
                wp_send_json_error(
                    array(
                        'message' => __( '顧客データは作成されましたが、受注書（案件）の作成に失敗しました。プラグインを最新版に更新してから、データを削除して再度お試しください。', 'ktpwp' ),
                        'output'  => $output,
                    )
                );
                return;
            }

            // ダミーデータ作成直後の初期表示を揃えるため、
            // 各タブの選択IDクッキーを「1」に統一設定（30日間有効）
            if (!headers_sent()) {
                $cookie_lifetime = time() + (86400 * 30);
                $cookie_path = '/';
                // 顧客・サービス・協力会社のID選択クッキー
                setcookie('ktp_client_id', '1', $cookie_lifetime, $cookie_path);
                setcookie('ktp_service_id', '1', $cookie_lifetime, $cookie_path);
                setcookie('ktp_supplier_id', '1', $cookie_lifetime, $cookie_path);
            } else if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('KTPWP: ヘッダー送信後のため、初期表示クッキーを設定できませんでした');
            }

            // 成功メッセージを返す
            wp_send_json_success(array(
                'message' => __( 'ダミーデータが正常に作成されました。', 'ktpwp' ),
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
            wp_send_json_error(array('message' => __( 'ダミーデータ作成中にエラーが発生しました: ', 'ktpwp' ) . $e->getMessage()));
        }
        
    } catch (Exception $e) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('KTPWP: ダミーデータ作成中にエラーが発生しました: ' . $e->getMessage());
        }
        wp_send_json_error(array('message' => __( 'ダミーデータ作成中にエラーが発生しました: ', 'ktpwp' ) . $e->getMessage()));
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
            wp_send_json_error(array('message' => __( 'セキュリティチェックに失敗しました。', 'ktpwp' )));
            return;
        }
        
        if (!current_user_can('manage_options')) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('KTPWP: 権限がありません');
            }
            wp_send_json_error(array('message' => __( '権限がありません。', 'ktpwp' )));
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
                $cleared_tables[] = sprintf( __( 'コスト項目: %d件', 'ktpwp' ), $cost_items_count );
                $total_cleared += $cost_items_count;
            }
        }
        
        $invoice_items_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ktp_order_invoice_items");
        if ($invoice_items_count > 0) {
            $result = $wpdb->query("DELETE FROM {$wpdb->prefix}ktp_order_invoice_items");
            if ($result !== false) {
                $cleared_tables[] = sprintf( __( '請求項目: %d件', 'ktpwp' ), $invoice_items_count );
                $total_cleared += $invoice_items_count;
            }
        }
        
        // 2. 受注書テーブルをクリア
        $order_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ktp_order");
        if ($order_count > 0) {
            $result = $wpdb->query("DELETE FROM {$wpdb->prefix}ktp_order");
            if ($result !== false) {
                $cleared_tables[] = sprintf( __( '受注書: %d件', 'ktpwp' ), $order_count );
                $total_cleared += $order_count;
            }
        }
        
        // 3. 顧客テーブルをクリア
        $client_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ktp_client");
        if ($client_count > 0) {
            $result = $wpdb->query("DELETE FROM {$wpdb->prefix}ktp_client");
            if ($result !== false) {
                $cleared_tables[] = sprintf( __( '顧客: %d件', 'ktpwp' ), $client_count );
                $total_cleared += $client_count;
            }
        }
        
        // 4. 協力会社職能テーブルをクリア
        $supplier_skills_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ktp_supplier_skills");
        if ($supplier_skills_count > 0) {
            $result = $wpdb->query("DELETE FROM {$wpdb->prefix}ktp_supplier_skills");
            if ($result !== false) {
                $cleared_tables[] = sprintf( __( '協力会社職能: %d件', 'ktpwp' ), $supplier_skills_count );
                $total_cleared += $supplier_skills_count;
            }
        }
        
        // 5. 協力会社テーブルをクリア
        $supplier_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ktp_supplier");
        if ($supplier_count > 0) {
            $result = $wpdb->query("DELETE FROM {$wpdb->prefix}ktp_supplier");
            if ($result !== false) {
                $cleared_tables[] = sprintf( __( '協力会社: %d件', 'ktpwp' ), $supplier_count );
                $total_cleared += $supplier_count;
            }
        }
        
        // 6. サービステーブルをクリア
        $service_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ktp_service");
        if ($service_count > 0) {
            $result = $wpdb->query("DELETE FROM {$wpdb->prefix}ktp_service");
            if ($result !== false) {
                $cleared_tables[] = sprintf( __( 'サービス: %d件', 'ktpwp' ), $service_count );
                $total_cleared += $service_count;
            }
        }
        
        if ($total_cleared > 0) {
            $success_message = sprintf(
                __( 'データクリアが完了しました！<br><br>クリアされたデータ:<br>• %s<br><br>合計: %d件のデータを削除しました。', 'ktpwp' ),
                implode('<br>• ', $cleared_tables),
                $total_cleared
            );
        } else {
            $success_message = __( 'クリアするデータがありませんでした。', 'ktpwp' );
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
            'message' => __( 'エラーが発生しました: ', 'ktpwp' ) . $e->getMessage()
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
