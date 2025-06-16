<?php
/**
 * Plugin Name: KantanPro
 * Plugin URI: https://ktpwp.com/
 * Description: 包括的なビジネス管理プラグイン。ショートコード[ktpwp_all_tab]で7つのタブ（仕事リスト・伝票処理・得意先・サービス・協力会社・レポート・設定）による完全なワークフロー管理を実現。
 * Version: 1.0.8(beta)
 * Author: KantanPro
 * Author URI: https://ktpwp.com/blog/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ktpwp
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.5
 * Requires PHP: 7.4
 *
 * @package KTPWP
 */

// セキュリティ: 直接アクセスを防止
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// プラグイン定数定義
if ( ! defined( 'KANTANPRO_PLUGIN_VERSION' ) ) {
    define( 'KANTANPRO_PLUGIN_VERSION', '1.0.8(beta)' );
}
if ( ! defined( 'KANTANPRO_PLUGIN_NAME' ) ) {
    define( 'KANTANPRO_PLUGIN_NAME', 'KantanPro' );
}
if ( ! defined( 'KANTANPRO_PLUGIN_DESCRIPTION' ) ) {
    // 翻訳読み込み警告を回避するため、initアクションで設定
    define( 'KANTANPRO_PLUGIN_DESCRIPTION', '包括的なビジネス管理プラグイン。ショートコード[ktpwp_all_tab]で7つのタブ（仕事リスト・伝票処理・得意先・サービス・協力会社・レポート・設定）による完全なワークフロー管理を実現。' );
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

// === GitHub自動アップデート: plugin-update-checker を利用 ===
// 1. 事前に https://github.com/YahnisElsts/plugin-update-checker をダウンロードし、
//    KantanPro/vendor/plugin-update-checker/ に配置してください。
if ( file_exists( __DIR__ . '/vendor/plugin-update-checker/plugin-update-checker.php' ) ) {
    require_once __DIR__ . '/vendor/plugin-update-checker/plugin-update-checker.php';

    $kantanpro_update_checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
        'https://github.com/KantanPro/freeKTP.git', // GitHubリポジトリURL
        __FILE__,                                   // プラグインのメインファイル
        'KantanPro'                                 // プラグインのスラッグ
    );
    $kantanpro_update_checker->setBranch('main');
    $kantanpro_update_checker->getVcsApi()->enableReleaseAssets();
    // プライベートリポジトリの場合は下記を有効化
    // $kantanpro_update_checker->setAuthentication('your-github-personal-access-token');
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
        'KTPWP_Setting_Class'   => 'includes/class-tab-setting.php',
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
        'KTPWP_GitHub_Updater'  => 'includes/class-ktpwp-github-updater.php',
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

// クラスの読み込み実行
ktpwp_autoload_classes();

// デバッグログ: プラグイン読み込み開始
if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
    error_log( 'KTPWP Plugin: Loading started' );
}

// メインクラスの初期化はinit以降に遅延（翻訳エラー防止）
add_action('init', function() { // Changed from plugins_loaded to init
    if ( class_exists( 'KTPWP_Main' ) ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP Plugin: KTPWP_Main class found, initializing on init hook...' );
        }
        KTPWP_Main::get_instance();
    } else {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP Plugin: KTPWP_Main class not found on init hook' );
        }
    }
}, 0); // Run early on init hook

// Contact Form 7連携クラスも必ず初期化
add_action('plugins_loaded', function() { // Changed from 'init' to 'plugins_loaded'
    if ( class_exists( 'KTPWP_Contact_Form' ) ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP Plugin: KTPWP_Contact_Form class found, initializing...' );
        }
        KTPWP_Contact_Form::get_instance();
    } else {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP Plugin: KTPWP_Contact_Form class not found' );
        }
    }
});

// プラグインリファレンス機能の初期化はinit以降に遅延（翻訳エラー防止）
add_action('init', function() {
    if ( class_exists( 'KTPWP_Plugin_Reference' ) ) {
        KTPWP_Plugin_Reference::get_instance();
    }
});

/**
 * セキュリティ強化: REST API制限 & HTTPヘッダー追加
 */

/**
 * 未認証ユーザーのREST APIアクセス制限
 *
 * @param WP_Error|null|true $result Authentication result.
 * @return WP_Error|null|true
 */
function ktpwp_restrict_rest_api( $result ) {
    if ( ! empty( $result ) ) {
        return $result;
    }

    if ( ! is_user_logged_in() ) {
        return new WP_Error(
            'rest_forbidden',
            'REST APIはログインユーザーのみ利用可能です。',
            array( 'status' => 403 )
        );
    }

    return $result;
}
add_filter( 'rest_authentication_errors', 'ktpwp_restrict_rest_api' );

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

register_activation_hook(KANTANPRO_PLUGIN_FILE, array('KTP_Settings', 'activate'));



// リダイレクト処理クラス
class KTPWP_Redirect {

    public function __construct() {
        add_action('template_redirect', array($this, 'handle_redirect'));
        add_filter('post_link', array($this, 'custom_post_link'), 10, 2);
        add_filter('page_link', array($this, 'custom_page_link'), 10, 2);
    }

    public function handle_redirect() {
        if (isset($_GET['tab_name']) || $this->has_ktpwp_shortcode()) {
            return;
        }

        if (is_single() || is_page()) {
            $post = get_queried_object();

            if ($post && $this->should_redirect($post)) {
                $external_url = $this->get_external_url($post);
                if ($external_url) {
                    // 外部リダイレクト先の安全性を検証（ホワイトリスト方式）
                    $allowed_hosts = [
                        'ktpwp.com',
                        parse_url(home_url(), PHP_URL_HOST)
                    ];
                    $parsed = wp_parse_url($external_url);
                    $host = isset($parsed['host']) ? $parsed['host'] : '';
                    if (in_array($host, $allowed_hosts, true)) {
                        $clean_external_url = $parsed['scheme'] . '://' . $host . (isset($parsed['path']) ? $parsed['path'] : '');
                        wp_redirect($clean_external_url, 301);
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
        if (!$post || !isset($post->post_content)) {
            return false;
        }

        return (
            has_shortcode($post->post_content, 'kantanAllTab') ||
            has_shortcode($post->post_content, 'ktpwp_all_tab')
        );
    }

    /**
     * リダイレクト対象かどうかを判定
     */
    private function should_redirect($post) {
        if (!$post) {
            return false;
        }

        // ショートコードが含まれるページの場合はリダイレクトしない
        if ($this->has_ktpwp_shortcode()) {
            return false;
        }

        // KTPWPのクエリパラメータがある場合はリダイレクトしない
        if (isset($_GET['tab_name']) || isset($_GET['from_client']) || isset($_GET['order_id'])) {
            return false;
        }

        // external_urlが設定されている投稿のみリダイレクト対象とする
        $external_url = get_post_meta($post->ID, 'external_url', true);
        if (!empty($external_url)) {
            return true;
        }

        // カスタム投稿タイプ「blog」で、特定の条件を満たす場合のみ
        if ($post->post_type === 'blog') {
            // 特定のスラッグやタイトルの場合のみリダイレクト
            $redirect_slugs = array('redirect-to-ktpwp', 'external-link');
            return in_array($post->post_name, $redirect_slugs);
        }

        return false;
    }

    /**
     * 外部URLを取得（クエリパラメータなし）
     */
    private function get_external_url($post) {
        if (!$post) {
            return false;
        }

        $external_url = get_post_meta($post->ID, 'external_url', true);

        if (empty($external_url)) {
            // デフォルトのベースURL
            $base_url = 'https://ktpwp.com/blog/';

            if ($post->post_type === 'blog') {
                $external_url = $base_url;
            } elseif ($post->post_type === 'post') {
                $categories = wp_get_post_categories($post->ID, array('fields' => 'slugs'));

                if (in_array('blog', $categories)) {
                    $external_url = $base_url;
                } elseif (in_array('news', $categories)) {
                    $external_url = $base_url . 'news/';
                } elseif (in_array('column', $categories)) {
                    $external_url = $base_url . 'column/';
                }
            }
        }

        // URLからクエリパラメータを除去
        if ($external_url) {
            $external_url = strtok($external_url, '?');
        }

        return $external_url;
    }

    public function custom_post_link($permalink, $post) {
        if ($post->post_type === 'blog') {
            $external_url = $this->get_external_url($post);
            if ($external_url) {
                return $external_url;
            }
        }

        if ($post->post_type === 'post') {
            $categories = wp_get_post_categories($post->ID, array('fields' => 'slugs'));
            $redirect_categories = array('blog', 'news', 'column');

            if (!empty(array_intersect($categories, $redirect_categories))) {
                $external_url = $this->get_external_url($post);
                if ($external_url) {
                    return $external_url;
                }
            }
        }

        return $permalink;
    }

    public function custom_page_link($permalink, $post_id) {
        $post = get_post($post_id);

        if ($post && $this->should_redirect($post)) {
            $external_url = $this->get_external_url($post);
            if ($external_url) {
                return $external_url;
            }
        }

        return $permalink;
    }
}

// POSTパラメータをGETパラメータに変換する処理
function ktpwp_handle_form_redirect() {
    // POSTデータハンドラーを使用した安全な処理
    if (!KTPWP_Post_Data_Handler::has_post_keys(['tab_name', 'from_client'])) {
        return;
    }

    $post_data = KTPWP_Post_Data_Handler::get_multiple_post_data([
        'tab_name' => 'text',
        'from_client' => 'text'
    ]);

    // orderタブのチェック
    if ($post_data['tab_name'] !== 'order') {
        return;
    }

    // リダイレクトパラメータの構築
    $redirect_params = [
        'tab_name' => $post_data['tab_name'],
        'from_client' => $post_data['from_client']
    ];

    // オプションパラメータの追加
    $optional_params = KTPWP_Post_Data_Handler::get_multiple_post_data([
        'customer_name' => 'text',
        'user_name' => 'text',
        'client_id' => ['type' => 'int', 'default' => 0]
    ]);

    foreach ($optional_params as $key => $value) {
        if (!empty($value) && ($key !== 'client_id' || $value > 0)) {
            $redirect_params[$key] = $value;
        }
    }

    // 現在のURLからKTPWPパラメータを除去してクリーンなベースURLを作成
    $current_url = add_query_arg(NULL, NULL);
    $clean_url = remove_query_arg([
        'tab_name', 'from_client', 'customer_name', 'user_name', 'client_id',
        'order_id', 'delete_order', 'data_id', 'view_mode', 'query_post'
    ], $current_url);

    // 新しいパラメータを追加してリダイレクト
    $redirect_url = add_query_arg($redirect_params, $clean_url);

    wp_redirect($redirect_url, 302);
    exit;
}

add_action('wp_loaded', 'ktpwp_handle_form_redirect', 1);


// ファイルをインクルード
// アクティベーションフックのために class-ktp-settings.php は常にインクルード
if (file_exists(MY_PLUGIN_PATH . 'includes/class-ktp-settings.php')) {
    include_once MY_PLUGIN_PATH . 'includes/class-ktp-settings.php';
} else {
    add_action( 'admin_notices', function() {
        echo '<div class="notice notice-error"><p>' . __('KTPWP Critical Error: includes/class-ktp-settings.php not found.', 'ktpwp') . '</p></div>';
    } );
}

add_action( 'plugins_loaded', 'KTPWP_Index' );

function ktpwp_scripts_and_styles() {
    wp_enqueue_script( 'ktp-js', plugins_url( 'js/ktp-js.js', __FILE__ ) . '?v=' . time(), array( 'jquery' ), null, true );

    // デバッグモードの設定（WP_DEBUGまたは開発環境でのみ有効）
    $debug_mode = (defined('WP_DEBUG') && WP_DEBUG) || (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG);
    wp_add_inline_script('ktp-js', 'var ktpwpDebugMode = ' . json_encode($debug_mode) . ';');

    // コスト項目トグル用の国際化ラベルをJSに渡す
    wp_add_inline_script('ktp-js', 'var ktpwpCostShowLabel = ' . json_encode('表示') . ';');
    wp_add_inline_script('ktp-js', 'var ktpwpCostHideLabel = ' . json_encode('非表示') . ';');
    wp_add_inline_script('ktp-js', 'var ktpwpStaffChatShowLabel = ' . json_encode('表示') . ';');
    wp_add_inline_script('ktp-js', 'var ktpwpStaffChatHideLabel = ' . json_encode('非表示') . ';');

    wp_register_style('ktp-css', plugins_url('css/styles.css', __FILE__) . '?v=' . time(), array(), KANTANPRO_PLUGIN_VERSION, 'all');
    wp_enqueue_style('ktp-css');
    // 進捗プルダウン用のスタイルシートを追加
    wp_enqueue_style('ktp-progress-select', plugins_url('css/progress-select.css', __FILE__) . '?v=' . time(), array('ktp-css'), KANTANPRO_PLUGIN_VERSION, 'all');
    // 設定タブ用のスタイルシートを追加
    wp_enqueue_style('ktp-setting-tab', plugins_url('css/ktp-setting-tab.css', __FILE__) . '?v=' . time(), array('ktp-css'), KANTANPRO_PLUGIN_VERSION, 'all');

    // Material Symbols アイコンフォントをプリロードとして読み込み
    wp_enqueue_style('material-symbols-outlined', 'https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0', array(), '1.0.0', 'all');

    // Google Fontsのプリロード設定
    add_action('wp_head', function() {
        echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
        echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
        echo '<link rel="preload" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" as="style" onload="this.onload=null;this.rel=\'stylesheet\'">' . "\n";
    }, 1);
    wp_enqueue_script('jquery', 'https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js', array(), '3.5.1', true);
    wp_enqueue_script('ktp-order-inline-projectname', plugins_url('js/ktp-order-inline-projectname.js', __FILE__), array('jquery'), KANTANPRO_PLUGIN_VERSION, true);
    // Nonceをjsに渡す（案件名インライン編集用）
    if (current_user_can('manage_options') || current_user_can('ktpwp_access')) {
        wp_add_inline_script('ktp-order-inline-projectname', 'var ktpwp_inline_edit_nonce = ' . json_encode(array(
            'nonce' => wp_create_nonce('ktp_update_project_name')
        )) . ';');
    }

    // ajaxurl をフロントエンドに渡す
    wp_add_inline_script('ktp-js', 'var ktp_ajax_object = ' . json_encode(array('ajax_url' => admin_url('admin-ajax.php'))) . ';');

    // Ajax nonceを追加
    wp_add_inline_script('ktp-invoice-items', 'var ktp_ajax_nonce = ' . json_encode(wp_create_nonce('ktp_ajax_nonce')) . ';');
    wp_add_inline_script('ktp-cost-items', 'var ktp_ajax_nonce = ' . json_encode(wp_create_nonce('ktp_ajax_nonce')) . ';');

    // ajaxurlをJavaScriptで利用可能にする
    wp_add_inline_script('ktp-invoice-items', 'var ajaxurl = ' . json_encode(admin_url('admin-ajax.php')) . ';');
    wp_add_inline_script('ktp-cost-items', 'var ajaxurl = ' . json_encode(admin_url('admin-ajax.php')) . ';');

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
            'var ktpwp_reference = ' . json_encode(array(
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
                )
            )) . ';'
        );
    }
}
add_action( 'wp_enqueue_scripts', 'ktpwp_scripts_and_styles' );
add_action( 'admin_enqueue_scripts', 'ktpwp_scripts_and_styles' );

/**
 * Ajax ハンドラーを初期化（旧システム用）
 */
function ktpwp_init_ajax_handlers() {
}
add_action('init', 'ktpwp_init_ajax_handlers');

function ktp_table_setup() {
    // プラグイン有効化時にテーブルセットアップを実行
    // まず必要なクラスファイルを読み込む
    $class_files = [
        'class-tab-client.php',
        'class-tab-service.php',
        'class-tab-supplier.php',
        'class-tab-setting.php',
        'class-login-error.php'
    ];

    foreach ($class_files as $file) {
        $file_path = plugin_dir_path(__FILE__) . 'includes/' . $file;
        if (file_exists($file_path)) {
            if ($file === 'class-tab-service.php') { // スキップ対象を class-tab-service.php に変更
                // class-tab-service.php の読み込みをスキップ
            } else {
                require_once $file_path;
            }
        } else {
        }
    }

    // 各クラスでテーブル作成/更新処理を行う
    if (class_exists('Kntan_Client_Class')) {
        $client = new Kntan_Client_Class();
        $client->Create_Table('client');
        // $client->Update_Table('client');
    }        if (class_exists('Kntan_Service_Class')) {
            $service = new Kntan_Service_Class();
            $service->Create_Table('service');
        }        if (class_exists('Kantan_Supplier_Class')) {
            $supplier = new Kantan_Supplier_Class();
            $supplier->Create_Table('supplier');
        }        if (class_exists('KTPWP_Setting_Class')) {
            $setting = new KTPWP_Setting_Class();
            $setting->Create_Table('setting');
        }
}
register_activation_hook(KANTANPRO_PLUGIN_FILE, 'ktp_table_setup'); // テーブル作成処理
register_activation_hook(KANTANPRO_PLUGIN_FILE, array('KTP_Settings', 'activate')); // 設定クラスのアクティベート処理
register_activation_hook(KANTANPRO_PLUGIN_FILE, array('KTPWP_Plugin_Reference', 'on_plugin_activation')); // プラグインリファレンス更新処理

function check_activation_key() {
    $activation_key = get_site_option('ktp_activation_key');
    return empty($activation_key) ? '' : '';
}

function add_htmx_to_head() {
}
add_action('wp_head', 'add_htmx_to_head');

function KTPWP_Index(){

    //すべてのタブのショートコード[kantanAllTab]
    function kantanAllTab(){

        // ログイン中のユーザーは全員ヘッダーを表示（権限による制限を緩和）
        if (is_user_logged_in()) {
            // XSS対策: 画面に出力する変数は必ずエスケープ

            // ユーザーのログインログアウト状況を取得するためのAjaxを登録
            add_action('wp_ajax_get_logged_in_users', 'get_logged_in_users');
            add_action('wp_ajax_nopriv_get_logged_in_users', 'get_logged_in_users');

            // get_logged_in_users の再宣言防止
            if (!function_exists('get_logged_in_users')) {
                function get_logged_in_users() {
                    // スタッフ権限チェック
                    if (!current_user_can('edit_posts') && !current_user_can('ktpwp_access')) {
                        wp_send_json_error(__('この操作を行う権限がありません。', 'ktpwp'));
                        return;
                    }

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
                        if (in_array('administrator', $user->roles) || user_can($user->ID, 'ktpwp_access')) {
                            $nickname = get_user_meta($user->ID, 'nickname', true);
                            if (empty($nickname)) {
                                $nickname = $user->display_name ? $user->display_name : $user->user_login;
                            }
                            $logged_in_staff[] = array(
                                'id' => $user->ID,
                                'name' => esc_html($nickname) . 'さん',
                                'is_current' => (get_current_user_id() === $user->ID),
                                'avatar_url' => get_avatar_url($user->ID, array('size' => 32))
                            );
                        }
                    }

                    wp_send_json($logged_in_staff);
                }
            }

            // 現在メインのログインユーザー情報を取得
            global $current_user;

            // ログアウトのリンク
            $logout_link = esc_url(wp_logout_url());

            // ヘッダー表示ログインユーザー名など
            $act_key = esc_html(check_activation_key());

            // ログイン中のユーザー情報を取得（ログインしている場合のみ）
            $logged_in_users_html = '';

            // ショートコードクラスのインスタンスからスタッフアバター表示を取得
            if (is_user_logged_in()) {
                $shortcodes_instance = KTPWP_Shortcodes::get_instance();
                $logged_in_users_html = $shortcodes_instance->get_staff_avatars_display();
            }

            // 画像タグをPHP変数で作成（ベースラインを10px上げる）
            $icon_img = '<img src="' . esc_url(plugins_url('images/default/icon.png', __FILE__)) . '" style="height:40px;vertical-align:middle;margin-right:8px;position:relative;top:-5px;">';

            // バージョン番号を定数から取得
            $plugin_version = defined('MY_PLUGIN_VERSION') ? esc_html(MY_PLUGIN_VERSION) : '';

            // プラグイン名とバージョンを定数から取得
            $plugin_name = esc_html(KANTANPRO_PLUGIN_NAME);
            $plugin_version = esc_html(KANTANPRO_PLUGIN_VERSION);
            $current_page_id = get_queried_object_id();
            $update_link_url = esc_url(get_permalink($current_page_id));

            // ログインしているユーザーのみにナビゲーションリンクを表示
            $navigation_links = '';
            if ( is_user_logged_in() && $current_user && $current_user->ID > 0 ) {
                // セッションの有効性も確認
                $user_sessions = WP_Session_Tokens::get_instance( $current_user->ID );
                if ( $user_sessions && ! empty( $user_sessions->get_all() ) ) {
                    $navigation_links .= '　<a href="' . $logout_link . '">ログアウト</a>';
                    // 更新リンクは編集者権限がある場合のみ
                    if (current_user_can('edit_posts')) {
                        $navigation_links .= '　<a href="' . $update_link_url . '">更新</a>';
                        $navigation_links .= '　' . $act_key;
                    }
                    // リファレンスボタンはログインユーザー全員に表示
                    $reference_instance = KTPWP_Plugin_Reference::get_instance();
                    $navigation_links .= $reference_instance->get_reference_link();
                }
            }

            $front_message = '<div class="ktp_header">'
                . '<div class="parent"><div class="title">' . $icon_img . $plugin_name . '</div><div class="version">v' . $plugin_version . '</div></div>'
                . '<div style="margin-left: auto; display: flex; align-items: center;">'
                . $logged_in_users_html
                . $navigation_links
                . '</div>'
                . '</div>';
            $tab_name = isset($_GET['tab_name']) ? $_GET['tab_name'] : 'default_tab'; // URLパラメータからtab_nameを取得

            // $order_content など未定義変数の初期化
            $order_content    = isset($order_content) ? $order_content : '';
            $client_content   = isset($client_content) ? $client_content : '';
            $service_content  = isset($service_content) ? $service_content : '';
            $supplier_content = isset($supplier_content) ? $supplier_content : '';
            $report_content   = isset($report_content) ? $report_content : '';
            $setting_content  = isset($setting_content) ? $setting_content : '';

            if (!isset($list_content)) {
                $list_content = '';
            }

            // デバッグ：タブ処理開始

            switch ($tab_name) {
                case 'list':
                    $list = new Kantan_List_Class();
                    $list_content = $list->List_Tab_View($tab_name);
                    break;
                case 'order':
                    $order = new Kntan_Order_Class();
                    $order_content = $order->Order_Tab_View($tab_name);
                    $order_content = $order_content ?? '';
                    break;
                case 'client':
                    $client = new Kntan_Client_Class();
                    if (current_user_can('edit_posts')) {
                        $client->Create_Table($tab_name);
                        $client->Update_Table($tab_name);
                    }
                    $client_content = $client->View_Table($tab_name);
                    break;
                case 'service':
                    $service = new Kntan_Service_Class();
                    if (current_user_can('edit_posts')) {
                        $service->Create_Table($tab_name);
                        $service->Update_Table($tab_name);
                    }
                    $service_content = $service->View_Table($tab_name);
                    break;
                case 'supplier':
                    $supplier = new KTPWP_Supplier_Class();
                    if (current_user_can('edit_posts')) {
                        $supplier->Create_Table($tab_name);

                        if ( ! empty( $_POST ) ) {
                            $supplier->Update_Table($tab_name);
                        }
                    }
                    $supplier_content = $supplier->View_Table($tab_name);
                    break;
                case 'report':
                    $report = new KTPWP_Report_Class();
                    $report_content = $report->Report_Tab_View($tab_name);
                    break;
                case 'setting':
                    $setting = new KTPWP_Setting_Class();
                    if (current_user_can('edit_posts')) {
                        $setting->Create_Table($tab_name);
                        // $setting->Update_Table($tab_name);
                    }
                    $setting_content = $setting->Setting_Tab_View($tab_name);
                    break;
                default:
                    // デフォルトの処理
                    $list = new Kantan_List_Class();
                    $tab_name = 'list';
                    $list_content = $list->List_Tab_View($tab_name);
                    break;
            }
            // view
            $view = new view_tabs_Class();
            $tab_view = $view->TabsView($list_content, $order_content, $client_content, $service_content, $supplier_content, $report_content, $setting_content);
            $return_value = $front_message . $tab_view;
            return $return_value;

        } else {
            // ログインしていない場合、または権限がない場合
            if (!is_user_logged_in()) {
                $login_error = new Kantan_Login_Error();
                $error = $login_error->Error_View();
                return $error;
            } else {
                // ログインしているが権限がない場合
                return '<div class="ktpwp-error">このコンテンツを表示する権限がありません。</div>';
            }
        }
    }
    add_shortcode('kantanAllTab','kantanAllTab');
    // ktpwp_all_tab ショートコードを追加（同じ機能を別名で提供）
    add_shortcode('ktpwp_all_tab', 'kantanAllTab');
}

// add_submenu_page の第7引数修正
// 例: add_submenu_page( $parent_slug, $page_title, $menu_title, $capability, $menu_slug, $function );
// 直接呼び出しを削除し、admin_menuフックで登録
add_action('admin_menu', function() {
    add_submenu_page(
        'parent_slug',
        __('ページタイトル', 'ktpwp'),
        __('メニュータイトル', 'ktpwp'),
        'manage_options',
        'menu_slug',
        'function_name'
        // 第7引数（メニュー位置）は不要なら省略
    );
});

// GitHub Updater


// 案件名インライン編集用Ajaxハンドラ

// 案件名インライン編集用Ajaxハンドラ（管理者のみ許可＆nonce検証）
add_action('wp_ajax_ktp_update_project_name', function() {
    // 権限チェック
    if (!current_user_can('manage_options') && !current_user_can('ktpwp_access')) {
        wp_send_json_error(__('権限がありません', 'ktpwp'));
    }

    // POSTデータの安全な取得
    if (!KTPWP_Post_Data_Handler::has_post_keys(['_wpnonce', 'order_id', 'project_name'])) {
        wp_send_json_error(__('必要なデータが不足しています', 'ktpwp'));
    }

    $post_data = KTPWP_Post_Data_Handler::get_multiple_post_data([
        '_wpnonce' => 'text',
        'order_id' => ['type' => 'int', 'default' => 0],
        'project_name' => 'text'
    ]);

    // nonceチェック
    if (!wp_verify_nonce($post_data['_wpnonce'], 'ktp_update_project_name')) {
        wp_send_json_error(__('セキュリティ検証に失敗しました', 'ktpwp'));
    }

    global $wpdb;
    $order_id = $post_data['order_id'];
    // wp_strip_all_tags()でタグのみ削除（HTMLエンティティは保持）
    $project_name = wp_strip_all_tags($post_data['project_name']);
    if ($order_id > 0) {
        $table = $wpdb->prefix . 'ktp_order';
        $wpdb->update(
            $table,
            ['project_name' => $project_name],
            ['id' => $order_id],
            ['%s'],
            ['%d']
        );
        wp_send_json_success();
    } else {
        wp_send_json_error(__('Invalid order_id', 'ktpwp'));
    }
});

// 非ログイン時はAjaxで案件名編集不可（セキュリティのため）
add_action('wp_ajax_nopriv_ktp_update_project_name', function() {
    wp_send_json_error(__('ログインが必要です', 'ktpwp'));
});




// includes/class-tab-list.php, class-view-tab.php を明示的に読み込む（自動読み込みされていない場合のみ）
if (!class_exists('Kantan_List_Class')) {
    include_once(MY_PLUGIN_PATH . 'includes/class-tab-list.php');
}
if (!class_exists('view_tabs_Class')) {
    include_once(MY_PLUGIN_PATH . 'includes/class-view-tab.php');
}
if (!class_exists('Kantan_Login_Error')) {
    include_once(MY_PLUGIN_PATH . 'includes/class-login-error.php');
}
if (!class_exists('Kntan_Report_Class')) {
    include_once(MY_PLUGIN_PATH . 'includes/class-tab-report.php');
}

/**
 * Contact Form 7の送信データをwp_ktp_clientテーブルに登録する
 *
 * @param WPCF7_ContactForm $contact_form Contact Form 7のフォームオブジェクト.
 */
