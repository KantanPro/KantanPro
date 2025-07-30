<?php
/**
 * KantanPro更新チェッククラス
 * 
 * GitHubリリースをチェックして、プラグインの更新通知を管理します。
 * 
 * @package KantanPro
 * @since 1.0.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * KantanPro更新チェッククラス
 */
class KTPWP_Update_Checker {
    
    /**
     * GitHubリポジトリURL
     */
    private $github_repo = 'KantanPro/KantanPro-a-';
    
    /**
     * GitHub Personal Access Token（非公開リポジトリ用）
     * 注意: 配布用プラグインでは使用しない
     */
    private $github_token = '';
    
    /**
     * プラグインファイルのベース名
     */
    private $plugin_basename;
    
    /**
     * プラグインのSlug
     */
    private $plugin_slug;
    
    /**
     * 現在のバージョン
     */
    private $current_version;
    
    /**
     * 更新チェックの間隔（秒）
     */
    private $check_interval;
    
    /**
     * フロントエンド通知の実行フラグ
     */
    private $frontend_notice_shown = false;
    
    /**
     * コンストラクタ
     */
    public function __construct() {
        // プラグインベースネームを確実に取得
        $plugin_file = defined( 'KANTANPRO_PLUGIN_FILE' ) ? KANTANPRO_PLUGIN_FILE : __FILE__;
        $this->plugin_basename = plugin_basename( $plugin_file );
        
        // 備用のベースネーム取得方法
        if ( empty( $this->plugin_basename ) || $this->plugin_basename === basename( __FILE__ ) ) {
            $this->plugin_basename = 'KantanPro/ktpwp.php';
        }
        
        $this->plugin_slug = dirname( $this->plugin_basename );
        $this->current_version = defined( 'KANTANPRO_PLUGIN_VERSION' ) ? KANTANPRO_PLUGIN_VERSION : '1.0.5';
        
        // GitHubトークンを設定から取得
        $update_settings = $this->get_update_notification_settings();
        $this->github_token = isset( $update_settings['github_token'] ) ? $update_settings['github_token'] : '';
        
        // チェック間隔を設定から取得
        $check_interval_hours = isset( $update_settings['check_interval'] ) ? intval( $update_settings['check_interval'] ) : 24;
        $this->check_interval = $check_interval_hours * 3600; // 時間を秒に変換
        
        // WordPress.orgとの接続エラーを防ぐため、管理画面でのみフックを設定
        if ( is_admin() ) {
            // フック設定
            add_action( 'init', array( $this, 'init' ) );
            add_action( 'admin_init', array( $this, 'admin_init' ) );
            
            // プラグインメタ行にリンクを追加（即座に登録）
            add_filter( 'plugin_row_meta', array( $this, 'add_check_update_meta_link' ), 10, 2 );
            add_action( 'admin_post_ktpwp_check_update', array( $this, 'handle_manual_update_check' ) );
            
            // 更新通知の表示
            add_action( 'admin_notices', array( $this, 'show_update_notice' ) );
            
            // AJAX処理
            add_action( 'wp_ajax_ktpwp_dismiss_update_notice', array( $this, 'dismiss_update_notice' ) );
            add_action( 'wp_ajax_ktpwp_check_github_update', array( $this, 'ajax_check_github_update' ) );
            add_action( 'wp_ajax_ktpwp_perform_update', array( $this, 'perform_plugin_update' ) );
            add_action( 'wp_ajax_ktpwp_clear_plugin_cache', array( $this, 'ajax_clear_plugin_cache' ) );
            
            // ヘッダー更新通知用のAJAX処理
            add_action( 'wp_ajax_ktpwp_dismiss_header_update_notice', array( $this, 'dismiss_header_update_notice' ) );
            add_action( 'wp_ajax_ktpwp_check_header_update', array( $this, 'ajax_check_header_update' ) );
        }
        
        // フロントエンド通知は条件付きで設定
        if ( $this->is_frontend_notification_enabled() ) {
            add_action( 'wp_body_open', array( $this, 'check_frontend_update' ) );
            add_action( 'wp_footer', array( $this, 'check_frontend_update' ) );
        }
        
        // バージョン更新時の自動キャッシュクリアを実行
        $this->auto_clear_cache_on_version_update();
        
        // 配布先での確実な更新チェックのため、初期化時に強制更新チェックを実行
        if ( is_admin() ) {
            add_action( 'admin_init', array( $this, 'force_update_check_on_init' ), 5 );
        }
        
        // 設定変更時にトークンを再読み込み
        add_action( 'update_option_ktp_update_notification_settings', array( $this, 'reload_github_token' ), 10, 2 );
        
        // デバッグ用のログ出力
        error_log( 'KantanPro Update Checker: 初期化完了 - basename: ' . $this->plugin_basename );
        error_log( 'KantanPro Update Checker: 管理画面: ' . ( is_admin() ? 'はい' : 'いいえ' ) );
    }
    
    /**
     * 初期化
     */
    public function init() {
        // 毎日の自動更新チェック
        if ( ! wp_next_scheduled( 'ktpwp_daily_update_check' ) ) {
            wp_schedule_event( time(), 'daily', 'ktpwp_daily_update_check' );
        }
        add_action( 'ktpwp_daily_update_check', array( $this, 'check_github_updates' ) );
    }
    
    /**
     * 管理画面の初期化
     */
    public function admin_init() {
        // 管理画面でのスクリプトとスタイルの読み込み
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
    }
    
    /**
     * 管理画面用スクリプトの読み込み
     */
    public function enqueue_admin_scripts( $hook ) {
        if ( 'plugins.php' === $hook ) {
            wp_enqueue_script( 'jquery' );
            wp_enqueue_script( 'ktpwp-update-checker', KANTANPRO_PLUGIN_URL . 'js/ktpwp-update-checker.js', array( 'jquery' ), $this->current_version, true );
            wp_localize_script( 'ktpwp-update-checker', 'ktpwp_update_checker', array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce' => wp_create_nonce( 'ktpwp_update_checker' ),
                'checking_text' => __( '更新をチェック中...', 'KantanPro' ),
                'check_text' => __( '更新をチェック', 'KantanPro' ),
                'error_text' => __( 'エラーが発生しました', 'KantanPro' ),
            ) );
        }
        
        // フロントエンド更新チェック用のスクリプト設定を追加
        if ( $this->is_frontend_notification_enabled() && ! wp_script_is( 'ktpwp-update-balloon', 'enqueued' ) ) {
            wp_enqueue_script( 'ktpwp-update-balloon', KANTANPRO_PLUGIN_URL . 'js/ktpwp-update-balloon.js', array( 'jquery' ), $this->current_version, true );
            wp_localize_script( 'ktpwp-update-balloon', 'ktpwp_update_ajax', array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce' => wp_create_nonce( 'ktpwp_header_update_check' ),
                'admin_url' => admin_url(),
                'notifications_enabled' => $this->is_update_notification_enabled()
            ) );
            
            // 更新データがある場合は設定
            $update_data = get_option( 'ktpwp_update_available', false );
            if ( $update_data ) {
                wp_localize_script( 'ktpwp-update-balloon', 'ktpwp_update_data', array(
                    'has_update' => true,
                    'message' => __( '新しいバージョンが利用可能です！', 'KantanPro' ),
                    'update_data' => $update_data
                ) );
            }
        }
    }
    
    /**
     * プラグインメタ行に「更新をチェック」リンクを追加
     */
    public function add_check_update_meta_link( $plugin_meta, $plugin_file ) {
        // このプラグインの場合のみリンクを追加
        if ( $plugin_file === $this->plugin_basename ) {
            // デバッグ情報をログに出力
            error_log( 'KantanPro: プラグインメタリンクが追加されました - basename: ' . $this->plugin_basename );
            
            $check_link = sprintf(
                '<a href="#" id="ktpwp-manual-check" data-plugin="%s">%s</a>',
                esc_attr( $this->plugin_basename ),
                __( '更新をチェック', 'KantanPro' )
            );
            
            $cache_clear_link = sprintf(
                '<a href="#" id="ktpwp-cache-clear" data-plugin="%s">%s</a>',
                esc_attr( $this->plugin_basename ),
                __( 'キャッシュクリア', 'KantanPro' )
            );
            
            array_push( $plugin_meta, $check_link, $cache_clear_link );
        }
        return $plugin_meta;
    }
    
    /**
     * 手動更新チェックの処理
     */
    public function handle_manual_update_check() {
        // セキュリティチェック
        if ( ! wp_verify_nonce( $_POST['_wpnonce'], 'ktpwp_manual_update_check' ) ) {
            wp_die( __( 'セキュリティチェックに失敗しました。', 'KantanPro' ) );
        }
        
        if ( ! current_user_can( 'update_plugins' ) ) {
            wp_die( __( 'この操作を実行する権限がありません。', 'KantanPro' ) );
        }
        
        // 更新チェック実行
        $this->check_github_updates();
        
        // プラグインページにリダイレクト
        wp_redirect( admin_url( 'plugins.php' ) );
        exit;
    }
    
    /**
     * AJAX更新チェック
     */
    public function ajax_check_github_update() {
        // POSTデータの存在チェック
        if ( ! isset( $_POST['nonce'] ) ) {
            error_log( 'KantanPro: ajax_check_github_update - nonceが送信されていません' );
            wp_send_json_error( array(
                'message' => 'セキュリティトークンが送信されていません。',
                'error_type' => 'missing_nonce'
            ) );
            return;
        }
        
        // セキュリティチェック
        if ( ! wp_verify_nonce( $_POST['nonce'], 'ktpwp_update_checker' ) ) {
            error_log( 'KantanPro: ajax_check_github_update - nonce検証に失敗しました' );
            wp_send_json_error( array(
                'message' => 'セキュリティチェックに失敗しました。',
                'error_type' => 'security'
            ) );
            return;
        }
        
        if ( ! current_user_can( 'update_plugins' ) ) {
            wp_send_json_error( array(
                'message' => 'この操作を実行する権限がありません。',
                'error_type' => 'permission'
            ) );
            return;
        }
        
        // 更新チェック実行
        $update_available = $this->check_github_updates();
        
                    // デバッグ情報を追加
            $debug_info = array(
                'current_version' => $this->current_version,
                'github_repo' => $this->github_repo,
                'github_token_set' => ! empty( $this->github_token ),
                'last_check' => get_transient( 'ktpwp_last_update_check' ),
                'update_available' => get_option( 'ktpwp_update_available' ),
                'latest_version' => get_option( 'ktpwp_latest_version' ),
                'check_result' => $update_available
            );
        
        if ( $update_available ) {
            wp_send_json_success( array(
                'message' => __( '新しいバージョンが利用可能です！', 'KantanPro' ),
                'reload' => true,
                'debug_info' => $debug_info
            ) );
        } else {
            wp_send_json_success( array(
                'message' => __( '最新バージョンです。', 'KantanPro' ),
                'reload' => false,
                'debug_info' => $debug_info
            ) );
        }
    }
    
    /**
     * GitHub更新チェック
     */
    public function check_github_updates() {
        // 更新通知が無効の場合は何もしない
        if ( ! $this->is_update_notification_enabled() ) {
            error_log( 'KantanPro: 更新通知が無効のため、更新チェックをスキップします' );
            return false;
        }

        // 最後のチェックから一定時間経過していない場合はスキップ
        $last_check = get_transient( 'ktpwp_last_update_check' );
        if ( $last_check && ( time() - $last_check ) < $this->check_interval ) {
            error_log( 'KantanPro: 更新チェック間隔が短すぎるため、スキップします' );
            // 配布先での確実な更新チェックのため、間隔制限を緩和
            // return false;
        }

        // エラーハンドリングを強化
        try {
            // WordPress.orgとの接続エラーを防ぐため、タイムアウトを設定
            $timeout = 30;
            $headers = array(
                'Accept' => 'application/vnd.github.v3+json',
            );
            
            // 公開リポジトリ用のため、トークン認証は無効化
            // 配布用プラグインではセキュリティ上の理由でトークンを使用しない
            error_log( 'KantanPro: 公開リポジトリとしてAPIに接続します' );
            
            $args = array(
                'timeout' => $timeout,
                'user-agent' => 'KantanPro-Plugin/' . $this->current_version,
                'headers' => $headers,
            );

            // GitHub API URL
            $api_url = 'https://api.github.com/repos/' . $this->github_repo . '/releases/latest';
            
            error_log( 'KantanPro: GitHub APIに接続中: ' . $api_url );
            
            // wp_remote_getを使用してGitHub APIに接続
            $response = wp_remote_get( $api_url, $args );
            
            // レスポンスエラーチェック
            if ( is_wp_error( $response ) ) {
                error_log( 'KantanPro: GitHub API接続エラー: ' . $response->get_error_message() );
                return false;
            }
            
            $response_code = wp_remote_retrieve_response_code( $response );
            if ( $response_code !== 200 ) {
                $response_body = wp_remote_retrieve_body( $response );
                error_log( 'KantanPro: GitHub API エラーレスポンス: ' . $response_code . ' - ' . $response_body );
                
                // 公開リポジトリ用のエラーハンドリング
                if ( $response_code === 404 ) {
                    error_log( 'KantanPro: リポジトリが見つかりません。リポジトリ名またはURLを確認してください。' );
                } elseif ( $response_code === 403 ) {
                    error_log( 'KantanPro: GitHub APIレート制限に達しました。しばらく時間をおいて再試行してください。' );
                } elseif ( $response_code === 401 ) {
                    error_log( 'KantanPro: GitHub API認証エラー。公開リポジトリの場合は認証は不要です。' );
                }
                
                return false;
            }
            
            $body = wp_remote_retrieve_body( $response );
            $data = json_decode( $body, true );
            
            if ( ! $data || ! isset( $data['tag_name'] ) ) {
                error_log( 'KantanPro: GitHub APIレスポンスの解析に失敗しました' );
                return false;
            }
            
            $latest_version = $this->clean_version( $data['tag_name'] );
            $current_version = $this->clean_version( $this->current_version );
            
            error_log( 'KantanPro: 元のバージョン文字列 - 現在: ' . $this->current_version . ', 最新: ' . $data['tag_name'] );
            error_log( 'KantanPro: クリーン後のバージョン - 現在: ' . $current_version . ', 最新: ' . $latest_version );
            
            // バージョン比較
            $comparison_result = version_compare( $latest_version, $current_version, '>' );
            error_log( 'KantanPro: バージョン比較結果: ' . $latest_version . ' > ' . $current_version . ' = ' . ( $comparison_result ? 'true' : 'false' ) );
            
            // 配布先での確実な更新検出のため、プレビューバージョンの場合は強制更新チェック
            $force_update = false;
            if ( preg_match( '/\(preview\)/i', $data['tag_name'] ) && preg_match( '/\(preview\)/i', $this->current_version ) ) {
                // 両方ともプレビューバージョンの場合、バージョン番号のみで比較
                $latest_version_number = preg_replace( '/\(preview\)/i', '', $data['tag_name'] );
                $current_version_number = preg_replace( '/\(preview\)/i', '', $this->current_version );
                $force_update = version_compare( $latest_version_number, $current_version_number, '>' );
                error_log( 'KantanPro: プレビューバージョン強制比較 - 最新: ' . $latest_version_number . ', 現在: ' . $current_version_number . ', 結果: ' . ( $force_update ? 'true' : 'false' ) );
            }
            
            if ( $comparison_result || $force_update ) {
                // 更新が利用可能
                $update_data = array(
                    'version' => $latest_version,
                    'new_version' => $data['tag_name'], // 元のバージョン文字列も保存
                    'download_url' => $data['zipball_url'],
                    'changelog' => isset( $data['body'] ) ? $data['body'] : '',
                    'published_at' => isset( $data['published_at'] ) ? $data['published_at'] : '',
                    'current_version' => $this->current_version, // 現在のバージョンも保存
                    'cleaned_current_version' => $current_version, // クリーン後の現在バージョンも保存
                    'cleaned_latest_version' => $latest_version, // クリーン後の最新バージョンも保存
                );
                
                // 更新情報を保存
                update_option( 'ktpwp_latest_version', $update_data );
                update_option( 'ktpwp_update_available', $update_data ); // 配列全体を保存
                
                error_log( 'KantanPro: 更新が利用可能です - バージョン: ' . $latest_version );
                error_log( 'KantanPro: 保存された更新データ: ' . print_r( $update_data, true ) );
                return $update_data;
            } else {
                // 更新なし
                update_option( 'ktpwp_update_available', false );
                error_log( 'KantanPro: 更新は利用できません - 最新版です' );
                error_log( 'KantanPro: 比較詳細 - 現在: ' . $current_version . ', 最新: ' . $latest_version . ', 比較結果: ' . $comparison_result );
                return false;
            }
            
        } catch ( Exception $e ) {
            error_log( 'KantanPro: 更新チェック中に例外が発生: ' . $e->getMessage() );
            return false;
        } finally {
            // 最後のチェック時刻を更新
            set_transient( 'ktpwp_last_update_check', time(), DAY_IN_SECONDS );
        }
    }
    
    /**
     * 更新通知設定を取得（デフォルト値付き）
     */
    private function get_update_notification_settings() {
        $update_settings = get_option( 'ktp_update_notification_settings', array() );
        
        // デフォルト値を設定
        $defaults = array(
            'enable_notifications' => true,
            'enable_admin_notifications' => true,
            'enable_frontend_notifications' => true,
            'check_interval' => 24,
            'notification_roles' => array( 'administrator' ),
            'github_token' => ''
        );
        
        // 設定が存在しない場合はデフォルト値を保存
        if ( empty( $update_settings ) ) {
            update_option( 'ktp_update_notification_settings', $defaults );
            return $defaults;
        }
        
        // 既存設定にデフォルト値をマージ
        $merged_settings = wp_parse_args( $update_settings, $defaults );
        
        // 設定が更新された場合は保存
        if ( $merged_settings !== $update_settings ) {
            update_option( 'ktp_update_notification_settings', $merged_settings );
        }
        
        return $merged_settings;
    }

    /**
     * 更新通知が有効かどうかを確認
     */
    public function is_update_notification_enabled() {
        $update_settings = $this->get_update_notification_settings();
        return ! empty( $update_settings['enable_notifications'] );
    }

    /**
     * 管理画面通知が有効かどうかを確認
     */
    public function is_admin_notification_enabled() {
        $update_settings = $this->get_update_notification_settings();
        return ! empty( $update_settings['enable_admin_notifications'] );
    }

    /**
     * フロントエンド通知が有効かどうかを確認
     */
    public function is_frontend_notification_enabled() {
        $update_settings = $this->get_update_notification_settings();
        return ! empty( $update_settings['enable_frontend_notifications'] );
    }

    /**
     * ユーザーが通知対象権限を持っているかどうかを確認
     */
    public function user_has_notification_permission() {
        $update_settings = $this->get_update_notification_settings();
        $notification_roles = isset( $update_settings['notification_roles'] ) ? $update_settings['notification_roles'] : array( 'administrator' );
        
        foreach ( $notification_roles as $role ) {
            if ( current_user_can( $role ) ) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * 更新通知を表示
     */
    public function show_update_notice() {
        // 更新通知が無効の場合は表示しない
        if ( ! $this->is_update_notification_enabled() || ! $this->is_admin_notification_enabled() ) {
            return;
        }

        // 管理画面でのみ表示
        if ( ! is_admin() ) {
            return;
        }

        // ログイン中のユーザーで、通知対象権限を持つユーザーのみ表示
        if ( ! is_user_logged_in() || ! $this->user_has_notification_permission() ) {
            return;
        }

        // 更新情報を取得
        $update_data = get_option( 'ktpwp_update_available', false );
        if ( ! $update_data ) {
            return;
        }

        // 現在のページを判定
        $current_screen = get_current_screen();
        $is_plugins_page = ( $current_screen && $current_screen->id === 'plugins' );
        $is_ktpwp_page = $this->is_ktpwp_page();

        // KantanPro設置ページとプラグインリストでのみ表示
        if ( ! $is_plugins_page && ! $is_ktpwp_page ) {
            return;
        }

        // KantanPro設置ページの場合、通知が無視されているかチェック
        if ( $is_ktpwp_page && get_option( 'ktpwp_update_notice_dismissed', false ) ) {
            return;
        }

        // プラグインリストの場合は常に表示（無視フラグはチェックしない）
        
        $plugin_name = get_plugin_data( KANTANPRO_PLUGIN_FILE )['Name'];
        
        // $update_dataが配列か文字列かをチェック
        if ( is_array( $update_data ) && isset( $update_data['new_version'] ) ) {
            $new_version = $update_data['new_version'];
        } elseif ( is_array( $update_data ) && isset( $update_data['version'] ) ) {
            $new_version = $update_data['version'];
        } elseif ( is_string( $update_data ) ) {
            $new_version = $update_data;
        } else {
            // 予期しない形式の場合は処理を中断
            error_log( 'KantanPro: 更新データの形式が不正です: ' . print_r( $update_data, true ) );
            return;
        }

        ?>
        <div class="notice notice-warning is-dismissible" id="ktpwp-update-notice" data-page-type="<?php echo esc_attr( $is_ktpwp_page ? 'ktpwp' : 'plugins' ); ?>">
            <p>
                <strong><?php echo esc_html( $plugin_name ); ?></strong> の新しいバージョン 
                <strong><?php echo esc_html( $new_version ); ?></strong> が利用可能です。
                <a href="#" id="ktpwp-perform-update" data-version="<?php echo esc_attr( $new_version ); ?>">今すぐ更新</a>
            </p>
        </div>
        <script>
        jQuery(document).ready(function($) {
            $('#ktpwp-update-notice').on('click', '.notice-dismiss', function() {
                var pageType = $('#ktpwp-update-notice').data('page-type');
                
                $.post(ajaxurl, {
                    action: 'ktpwp_dismiss_update_notice',
                    page_type: pageType,
                    nonce: '<?php echo wp_create_nonce( 'ktpwp_dismiss_update_notice' ); ?>'
                });
            });
            
            $('#ktpwp-perform-update').on('click', function(e) {
                e.preventDefault();
                
                var $link = $(this);
                var originalText = $link.text();
                var version = $link.data('version');
                
                if (confirm('プラグインを更新しますか？更新中はサイトが一時的に利用できなくなる可能性があります。')) {
                    $link.text('更新中...');
                    
                    $.post(ajaxurl, {
                        action: 'ktpwp_perform_update',
                        version: version,
                        nonce: '<?php echo wp_create_nonce( 'ktpwp_perform_update' ); ?>'
                    }, function(response) {
                        if (response.success) {
                            $link.text('更新完了');
                            $('#ktpwp-update-notice').addClass('notice-success').removeClass('notice-warning');
                            $('#ktpwp-update-notice p').html('<strong>更新が完了しました！</strong> ページを再読み込みしてください。');
                            setTimeout(function() {
                                window.location.reload();
                            }, 2000);
                        } else {
                            // エラーメッセージの修正
                            var errorMessage = '更新に失敗しました';
                            if (response.data) {
                                if (typeof response.data === 'string') {
                                    errorMessage = response.data;
                                } else if (response.data.message) {
                                    errorMessage = response.data.message;
                                } else {
                                    errorMessage = '更新に失敗しました: ' + JSON.stringify(response.data);
                                }
                            }
                            alert(errorMessage);
                            $link.text(originalText);
                        }
                    }).fail(function() {
                        alert('更新に失敗しました。ネットワークエラーが発生しました。');
                        $link.text(originalText);
                    });
                }
            });
        });
        </script>
        <?php
    }
    
    /**
     * 現在のページがKantanPro設置ページかどうかを判定
     */
    private function is_ktpwp_page() {
        global $post;
        
        // 投稿ページでない場合はfalse
        if ( ! is_a( $post, 'WP_Post' ) ) {
            return false;
        }
        
        // KantanProショートコードが含まれているかチェック
        return has_shortcode( $post->post_content, 'ktpwp_all_tab' ) || 
               has_shortcode( $post->post_content, 'kantanAllTab' );
    }
    
    /**
     * 更新通知の無視
     */
    public function dismiss_update_notice() {
        // POSTデータの存在チェック
        if ( ! isset( $_POST['nonce'] ) ) {
            error_log( 'KantanPro: dismiss_update_notice - nonceが送信されていません' );
            wp_send_json_error( array(
                'message' => 'セキュリティトークンが送信されていません。',
                'error_type' => 'missing_nonce'
            ) );
            return;
        }
        
        // セキュリティチェック
        if ( ! wp_verify_nonce( $_POST['nonce'], 'ktpwp_dismiss_update_notice' ) ) {
            error_log( 'KantanPro: dismiss_update_notice - nonce検証に失敗しました' );
            wp_send_json_error( array(
                'message' => 'セキュリティチェックに失敗しました。',
                'error_type' => 'security'
            ) );
            return;
        }
        
        if ( ! current_user_can( 'update_plugins' ) ) {
            wp_send_json_error( array(
                'message' => 'この操作を実行する権限がありません。',
                'error_type' => 'permission'
            ) );
            return;
        }
        
        $page_type = isset( $_POST['page_type'] ) ? sanitize_text_field( $_POST['page_type'] ) : '';
        
        // KantanPro設置ページの場合のみ無視フラグを設定
        if ( $page_type === 'ktpwp' ) {
            update_option( 'ktpwp_update_notice_dismissed', true );
        }
        // プラグインリストの場合は無視フラグを設定しない（再表示される）
        
        wp_send_json_success( array(
            'message' => '更新通知を無視しました。'
        ) );
    }
    
    /**
     * フロントエンドでの更新チェック
     */
    public function check_frontend_update() {
        // 更新通知が無効の場合は実行しない
        if ( ! $this->is_update_notification_enabled() || ! $this->is_frontend_notification_enabled() ) {
            return;
        }

        // 既に実行済みの場合はスキップ
        if ( $this->frontend_notice_shown ) {
            return;
        }
        
        // ログイン中のユーザーで、通知対象権限を持つユーザーのみ表示
        if ( ! is_user_logged_in() || ! $this->user_has_notification_permission() ) {
            return;
        }

        // KantanProが表示されているページでのみ実行
        global $post;
        if ( ! is_a( $post, 'WP_Post' ) || ! has_shortcode( $post->post_content, 'ktpwp_all_tab' ) ) {
            return;
        }

        // フロントエンド更新チェック用のスクリプトを読み込み（まだ読み込まれていない場合のみ）
        if ( ! wp_script_is( 'ktpwp-update-balloon', 'enqueued' ) ) {
            wp_enqueue_script( 'ktpwp-update-balloon', KANTANPRO_PLUGIN_URL . 'js/ktpwp-update-balloon.js', array( 'jquery' ), $this->current_version, true );
            wp_localize_script( 'ktpwp-update-balloon', 'ktpwp_update_ajax', array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce' => wp_create_nonce( 'ktpwp_header_update_check' ),
                'admin_url' => admin_url(),
                'notifications_enabled' => $this->is_update_notification_enabled()
            ) );
        }

        // 1日1回のみチェック
        $last_frontend_check = get_option( 'ktpwp_last_frontend_check', 0 );
        $current_time = time();
        
        if ( ( $current_time - $last_frontend_check ) < $this->check_interval ) {
            // チェック間隔内でも、更新が利用可能な場合は通知を表示
            $update_data = get_option( 'ktpwp_update_available', false );
            if ( $update_data ) {
                $this->show_frontend_update_notice();
            }
            return;
        }

        // 更新チェック実行
        $update_available = $this->check_github_updates();
        
        if ( $update_available ) {
            $this->show_frontend_update_notice();
        }
        
        // 最後のフロントエンドチェック時刻を更新
        update_option( 'ktpwp_last_frontend_check', $current_time );
    }
    
    /**
     * フロントエンドでの更新通知表示（WordPress標準の1行通知スタイル）
     */
    private function show_frontend_update_notice() {
        // ログイン中のユーザーで、通知対象権限を持つユーザーのみ表示
        if ( ! is_user_logged_in() || ! $this->user_has_notification_permission() ) {
            return;
        }
        // 通知が無視されているかチェック（KantanPro設置ページでのみ適用）
        if ( get_option( 'ktpwp_frontend_update_notice_dismissed', false ) ) {
            return;
        }
        
        $update_data = get_option( 'ktpwp_update_available', false );
        if ( ! $update_data ) {
            return;
        }
        
        // $update_dataが配列か文字列かをチェック
        if ( is_array( $update_data ) && isset( $update_data['new_version'] ) ) {
            $new_version = $update_data['new_version'];
        } elseif ( is_array( $update_data ) && isset( $update_data['version'] ) ) {
            $new_version = $update_data['version'];
        } elseif ( is_string( $update_data ) ) {
            $new_version = $update_data;
        } else {
            // 予期しない形式の場合は処理を中断
            return;
        }
        
        // 過去に無視されたバージョンと同じ場合は表示しない
        $dismissed_version = get_option( 'ktpwp_frontend_dismissed_version', '' );
        if ( $dismissed_version === $new_version ) {
            return;
        }
        
        // 実行フラグを設定
        $this->frontend_notice_shown = true;
        
        $plugin_name = get_plugin_data( KANTANPRO_PLUGIN_FILE )['Name'];
        
        ?>
        <div id="ktpwp-frontend-update-notice" style="
            background: #fff; 
            border-left: 4px solid #0073aa; 
            margin: 0; 
            padding: 8px 12px; 
            box-shadow: 0 1px 1px rgba(0,0,0,0.04);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 999999;
        ">
            <button type="button" style="
                position: absolute; 
                top: 4px; 
                right: 8px; 
                background: none; 
                border: none; 
                font-size: 18px; 
                cursor: pointer;
                color: #666;
                line-height: 1;
            " onclick="ktpwpDismissFrontendNotice()" title="非表示にする">&times;</button>
            <p style="margin: 0; color: #0073aa; font-size: 14px; line-height: 1.4;">
                <strong><?php echo esc_html( $plugin_name ); ?></strong> の新しいバージョン 
                <strong><?php echo esc_html( $new_version ); ?></strong> が利用可能です。
                <a href="<?php echo esc_url( admin_url( 'plugins.php' ) ); ?>" style="color: #0073aa; text-decoration: none;">管理画面で更新</a>
            </p>
        </div>
        <script>
        // フロントエンド更新チェック用のスクリプト設定を追加
        if (typeof ktpwp_update_ajax === 'undefined') {
            var ktpwp_update_ajax = {
                ajax_url: '<?php echo admin_url( 'admin-ajax.php' ); ?>',
                nonce: '<?php echo wp_create_nonce( 'ktpwp_header_update_check' ); ?>',
                admin_url: '<?php echo admin_url(); ?>',
                notifications_enabled: <?php echo $this->is_update_notification_enabled() ? 'true' : 'false'; ?>
            };
        }
        
        function ktpwpDismissFrontendNotice() {
            document.getElementById('ktpwp-frontend-update-notice').style.display = 'none';
            // bodyのマージンを削除
            document.body.style.marginTop = '';
            // AJAX で無視フラグを設定（KantanPro設置ページでのみ）
            if (typeof jQuery !== 'undefined') {
                jQuery.post('<?php echo admin_url( 'admin-ajax.php' ); ?>', {
                    action: 'ktpwp_dismiss_frontend_update_notice',
                    nonce: '<?php echo wp_create_nonce( 'ktpwp_dismiss_frontend_update_notice' ); ?>'
                });
            }
        }
        </script>
        <style>
        body {
            margin-top: 45px !important;
        }
        </style>
        <?php
        
        // フロントエンド通知の無視処理
        add_action( 'wp_ajax_ktpwp_dismiss_frontend_update_notice', array( $this, 'dismiss_frontend_update_notice' ) );
        add_action( 'wp_ajax_nopriv_ktpwp_dismiss_frontend_update_notice', array( $this, 'dismiss_frontend_update_notice' ) );
    }
    
    /**
     * フロントエンド更新通知の無視
     */
    public function dismiss_frontend_update_notice() {
        // POSTデータの存在チェック
        if ( ! isset( $_POST['nonce'] ) ) {
            error_log( 'KantanPro: dismiss_frontend_update_notice - nonceが送信されていません' );
            wp_send_json_error( array(
                'message' => 'セキュリティトークンが送信されていません。',
                'error_type' => 'missing_nonce'
            ) );
            return;
        }
        
        // セキュリティチェック
        if ( ! wp_verify_nonce( $_POST['nonce'], 'ktpwp_dismiss_frontend_update_notice' ) ) {
            error_log( 'KantanPro: dismiss_frontend_update_notice - nonce検証に失敗しました' );
            wp_send_json_error( array(
                'message' => 'セキュリティチェックに失敗しました。',
                'error_type' => 'security'
            ) );
            return;
        }
        
        // 現在の更新バージョンを記録して、同じバージョンでは再度通知しないようにする
        $update_data = get_option( 'ktpwp_update_available', false );
        if ( $update_data ) {
            if ( is_array( $update_data ) && isset( $update_data['version'] ) ) {
                update_option( 'ktpwp_frontend_dismissed_version', $update_data['version'] );
            } elseif ( is_string( $update_data ) ) {
                update_option( 'ktpwp_frontend_dismissed_version', $update_data );
            }
        }
        
        // KantanPro設置ページでのみ無視フラグを設定
        update_option( 'ktpwp_frontend_update_notice_dismissed', true );
        wp_send_json_success( array(
            'message' => 'フロントエンド更新通知を無視しました。'
        ) );
    }
    
    /**
     * プラグイン更新の実行
     */
    public function perform_plugin_update() {
        // POSTデータの存在チェック
        if ( ! isset( $_POST['nonce'] ) ) {
            error_log( 'KantanPro: perform_plugin_update - nonceが送信されていません' );
            wp_send_json_error( array(
                'message' => 'セキュリティトークンが送信されていません。',
                'error_type' => 'missing_nonce'
            ) );
            return;
        }
        
        // セキュリティチェック
        if ( ! wp_verify_nonce( $_POST['nonce'], 'ktpwp_perform_update' ) ) {
            error_log( 'KantanPro: perform_plugin_update - nonce検証に失敗しました' );
            wp_send_json_error( array(
                'message' => 'セキュリティチェックに失敗しました。',
                'error_type' => 'security'
            ) );
            return;
        }
        
        if ( ! current_user_can( 'update_plugins' ) ) {
            wp_send_json_error( array(
                'message' => 'この操作を実行する権限がありません。',
                'error_type' => 'permission'
            ) );
            return;
        }
        
        $version = sanitize_text_field( $_POST['version'] );
        
        // 更新情報を取得
        $update_data = get_option( 'ktpwp_update_available', false );
        error_log( 'KantanPro: 更新実行時の更新データ: ' . print_r( $update_data, true ) );
        if ( ! $update_data ) {
            wp_send_json_error( array(
                'message' => '更新情報が見つかりません。',
                'error_type' => 'no_update_data'
            ) );
        }
        
        // $update_dataが配列か文字列かをチェック
        $update_version = '';
        if ( is_array( $update_data ) && isset( $update_data['new_version'] ) ) {
            $update_version = $update_data['new_version'];
        } elseif ( is_array( $update_data ) && isset( $update_data['version'] ) ) {
            $update_version = $update_data['version'];
        } elseif ( is_string( $update_data ) ) {
            $update_version = $update_data;
        } else {
            error_log( 'KantanPro: 更新データの形式が不正です: ' . print_r( $update_data, true ) );
            wp_send_json_error( array(
                'message' => '更新情報の形式が正しくありません。',
                'error_type' => 'invalid_update_data'
            ) );
        }
        
        // バージョン比較を緩和（プレビューバージョンの場合）
        $cleaned_update_version = $this->clean_version( $update_version );
        $cleaned_requested_version = $this->clean_version( $version );
        
        error_log( 'KantanPro: バージョン比較詳細 - 更新: ' . $update_version . ' (' . $cleaned_update_version . '), 要求: ' . $version . ' (' . $cleaned_requested_version . ')' );
        
        if ( $cleaned_update_version !== $cleaned_requested_version ) {
            error_log( 'KantanPro: バージョン比較失敗' );
            wp_send_json_error( array(
                'message' => '更新情報が見つかりません。',
                'error_type' => 'version_mismatch'
            ) );
        }
        
        error_log( 'KantanPro: バージョン比較成功' );
        
        // WordPress標準の更新システムを使用
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
        
        // カスタム更新処理
        $result = $this->download_and_install_update( $update_data );
        
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array(
                'message' => $result->get_error_message(),
                'error_type' => 'update_failed'
            ) );
        }
        
        if ( $result ) {
            // 更新成功時の処理
            delete_option( 'ktpwp_update_available' );
            delete_option( 'ktpwp_update_notice_dismissed' );
            delete_option( 'ktpwp_frontend_update_notice_dismissed' );
            
            wp_send_json_success( array(
                'message' => '更新が完了しました。'
            ) );
        } else {
            wp_send_json_error( array(
                'message' => '更新に失敗しました。',
                'error_type' => 'update_failed'
            ) );
        }
    }
    
    /**
     * GitHubからダウンロードして更新を実行
     */
    private function download_and_install_update( $update_data ) {
        $download_url = $update_data['download_url'];
        $plugin_slug = $this->plugin_slug;
        
        // 一時ディレクトリを作成
        $temp_dir = WP_PLUGIN_DIR . '/ktpwp_temp_' . time();
        if ( ! wp_mkdir_p( $temp_dir ) ) {
            return new WP_Error( 'temp_dir_failed', '一時ディレクトリの作成に失敗しました。' );
        }
        
        // 一時ファイルにダウンロード
        $temp_file = download_url( $download_url );
        if ( is_wp_error( $temp_file ) ) {
            $this->recursive_rmdir( $temp_dir );
            return new WP_Error( 'download_failed', 'ファイルのダウンロードに失敗しました: ' . $temp_file->get_error_message() );
        }
        
        // ZIPファイルを一時ディレクトリに解凍
        $unzip_result = unzip_file( $temp_file, $temp_dir );
        if ( is_wp_error( $unzip_result ) ) {
            @unlink( $temp_file );
            $this->recursive_rmdir( $temp_dir );
            return new WP_Error( 'unzip_failed', 'ファイルの解凍に失敗しました: ' . $unzip_result->get_error_message() );
        }
        
        // 解凍されたフォルダを見つける（GitHubのzipballは特殊な構造）
        $extracted_dirs = glob( $temp_dir . '/*', GLOB_ONLYDIR );
        if ( empty( $extracted_dirs ) ) {
            @unlink( $temp_file );
            $this->recursive_rmdir( $temp_dir );
            return new WP_Error( 'extract_failed', '解凍されたフォルダが見つかりません。' );
        }
        
        $source_dir = $extracted_dirs[0]; // 最初のディレクトリを使用
        
        // プラグインファイルの存在確認
        if ( ! file_exists( $source_dir . '/ktpwp.php' ) ) {
            @unlink( $temp_file );
            $this->recursive_rmdir( $temp_dir );
            return new WP_Error( 'invalid_plugin', 'プラグインファイルが見つかりません。' );
        }
        
        // 古いプラグインフォルダをバックアップ
        $plugin_dir = WP_PLUGIN_DIR . '/' . $plugin_slug;
        $backup_dir = $plugin_dir . '_backup_' . date( 'Y-m-d_H-i-s' );
        
        if ( is_dir( $plugin_dir ) ) {
            if ( ! rename( $plugin_dir, $backup_dir ) ) {
                @unlink( $temp_file );
                $this->recursive_rmdir( $temp_dir );
                return new WP_Error( 'backup_failed', 'バックアップの作成に失敗しました。' );
            }
        }
        
        // 新しいプラグインフォルダを配置
        if ( ! rename( $source_dir, $plugin_dir ) ) {
            // 失敗時はバックアップを復元
            if ( is_dir( $backup_dir ) ) {
                rename( $backup_dir, $plugin_dir );
            }
            @unlink( $temp_file );
            $this->recursive_rmdir( $temp_dir );
            return new WP_Error( 'install_failed', 'プラグインの配置に失敗しました。' );
        }
        
        // 成功時はバックアップを削除
        if ( is_dir( $backup_dir ) ) {
            $this->recursive_rmdir( $backup_dir );
        }
        
        // 一時ファイルとディレクトリを削除
        @unlink( $temp_file );
        $this->recursive_rmdir( $temp_dir );
        
        return true;
    }
    
    /**
     * ディレクトリを再帰的に削除
     */
    private function recursive_rmdir( $dir ) {
        if ( is_dir( $dir ) ) {
            $objects = scandir( $dir );
            foreach ( $objects as $object ) {
                if ( $object != '.' && $object != '..' ) {
                    if ( is_dir( $dir . '/' . $object ) ) {
                        $this->recursive_rmdir( $dir . '/' . $object );
                    } else {
                        unlink( $dir . '/' . $object );
                    }
                }
            }
            rmdir( $dir );
        }
    }
    
    /**
     * バージョン文字列をクリーンにする
     * 
     * @param string $version バージョン文字列
     * @return string クリーンなバージョン文字列
     */
    private function clean_version( $version ) {
        // プレビューバージョンの判定
        $is_preview = false;
        if ( preg_match( '/\(preview\)/i', $version ) ) {
            $is_preview = true;
        }
        
        // (preview)や(beta)などの文字を削除
        $version = preg_replace( '/\([^)]*\)/', '', $version );
        // 先頭の'v'を削除
        $version = ltrim( $version, 'v' );
        // 空白を削除
        $version = trim( $version );
        
        // プレビューバージョンの場合は、バージョン番号を少し上げて比較
        // 例: 1.1.22(preview) → 1.1.22.1 として扱う（通常版より上位）
        if ( $is_preview ) {
            $version_parts = explode( '.', $version );
            if ( count( $version_parts ) >= 3 ) {
                // .1を追加してプレビューバージョンを通常版より上位にする
                $version = implode( '.', $version_parts ) . '.1';
            } else {
                // 3つ未満の場合は.1を追加
                $version .= '.1';
            }
        }
        
        return $version;
    }
    
    /**
     * ヘッダー更新リンク用の更新チェック
     */
    public function check_header_update() {
        // 更新通知が無効の場合は実行しない
        if ( ! $this->is_update_notification_enabled() ) {
            return false;
        }
        
        // ログイン中のユーザーで、通知対象権限を持つユーザーのみ実行
        if ( ! is_user_logged_in() || ! $this->user_has_notification_permission() ) {
            return false;
        }
        
        // 更新情報を取得
        $update_data = get_option( 'ktpwp_update_available', false );
        if ( ! $update_data ) {
            return false;
        }
        
        // 通知が無視されているかチェック
        if ( get_option( 'ktpwp_header_update_notice_dismissed', false ) ) {
            return false;
        }
        
        // 過去に無視されたバージョンと同じ場合は表示しない
        $dismissed_version = get_option( 'ktpwp_header_dismissed_version', '' );
        if ( $dismissed_version === $update_data['version'] ) { // 更新データからversionを取得
            return false;
        }
        
        return $update_data;
    }

    /**
     * ヘッダー更新通知の無視
     */
    public function dismiss_header_update_notice() {
        // POSTデータの存在チェック
        if ( ! isset( $_POST['nonce'] ) ) {
            error_log( 'KantanPro: dismiss_header_update_notice - nonceが送信されていません' );
            wp_send_json_error( array(
                'message' => 'セキュリティトークンが送信されていません。',
                'error_type' => 'missing_nonce'
            ) );
            return;
        }
        
        // セキュリティチェック
        if ( ! wp_verify_nonce( $_POST['nonce'], 'ktpwp_header_update_notice' ) ) {
            error_log( 'KantanPro: dismiss_header_update_notice - nonce検証に失敗しました' );
            wp_send_json_error( array(
                'message' => 'セキュリティチェックに失敗しました。',
                'error_type' => 'invalid_nonce'
            ) );
            return;
        }
        
        // 現在の更新バージョンを記録
        $update_data = get_option( 'ktpwp_update_available', false );
        if ( $update_data ) {
            update_option( 'ktpwp_header_dismissed_version', $update_data['version'] ); // 更新データからversionを取得
        }
        
        update_option( 'ktpwp_header_update_notice_dismissed', true );
        wp_send_json_success( array(
            'message' => '更新通知を無視しました。'
        ) );
    }

    /**
     * ヘッダー更新リンク用のAJAX更新チェック
     */
    public function ajax_check_header_update() {
        try {
            error_log( 'KantanPro: ajax_check_header_update 開始' );
            error_log( 'KantanPro: POSTデータ: ' . print_r( $_POST, true ) );
            
            // POSTデータの存在チェック
            if ( ! isset( $_POST['nonce'] ) ) {
                error_log( 'KantanPro: ajax_check_header_update - nonceが送信されていません' );
                wp_send_json_error( array(
                    'message' => 'セキュリティトークンが送信されていません。',
                    'error_type' => 'missing_nonce'
                ) );
                return;
            }
            
            // セキュリティチェック
            error_log( 'KantanPro: nonce検証開始 - 受信nonce: ' . $_POST['nonce'] );
            if ( ! wp_verify_nonce( $_POST['nonce'], 'ktpwp_header_update_check' ) ) {
                error_log( 'KantanPro: ajax_check_header_update - nonce検証に失敗しました' );
                error_log( 'KantanPro: 期待されるnonce: ' . wp_create_nonce( 'ktpwp_header_update_check' ) );
                wp_send_json_error( array(
                    'message' => 'セキュリティチェックに失敗しました。',
                    'error_type' => 'security'
                ) );
                return;
            }
            error_log( 'KantanPro: nonce検証成功' );
            
            error_log( 'KantanPro: 権限チェック開始' );
            if ( ! $this->user_has_notification_permission() ) {
                error_log( 'KantanPro: 権限チェック失敗' );
                wp_send_json_error( array(
                    'message' => 'この操作を実行する権限がありません。',
                    'error_type' => 'permission'
                ) );
                return;
            }
            error_log( 'KantanPro: 権限チェック成功' );
            
            // 更新通知が無効の場合は即座に返す
            if ( ! $this->is_update_notification_enabled() ) {
                wp_send_json_success( array(
                    'message' => '更新通知が無効化されています。',
                    'has_update' => false,
                    'notifications_disabled' => true
                ) );
                return;
            }
            
            // 更新チェック実行
            error_log( 'KantanPro: 更新チェック実行開始' );
            $update_available = $this->check_github_updates();
            error_log( 'KantanPro: 更新チェック結果: ' . ( $update_available ? 'true' : 'false' ) );
            
            // 更新データを取得して詳細ログを出力
            $update_data = get_option( 'ktpwp_update_available', false );
            error_log( 'KantanPro: 保存された更新データ: ' . print_r( $update_data, true ) );
            
            if ( $update_available ) {
                $update_data = get_option( 'ktpwp_update_available', false );
                error_log( 'KantanPro: 更新あり - 更新データ: ' . print_r( $update_data, true ) );
                wp_send_json_success( array(
                    'message' => __( '新しいバージョンが利用可能です！', 'KantanPro' ),
                    'has_update' => true,
                    'update_data' => $update_data
                ) );
            } else {
                error_log( 'KantanPro: 更新なし' );
                wp_send_json_success( array(
                    'message' => __( '最新バージョンです。', 'KantanPro' ),
                    'has_update' => false
                ) );
            }
            
        } catch ( Exception $e ) {
            error_log( 'KantanPro: AJAX更新チェックで例外が発生: ' . $e->getMessage() );
            wp_send_json_error( array(
                'message' => '更新チェック中にエラーが発生しました: ' . $e->getMessage(),
                'error_type' => 'exception'
            ) );
        }
    }
    
    /**
     * プラグイン情報キャッシュをクリア
     */
    public function clear_plugin_cache() {
        // WordPressのプラグイン情報キャッシュをクリア
        wp_clean_plugins_cache();
        
        // サイトトランジェントキャッシュをクリア
        delete_site_transient( 'update_plugins' );
        
        // ローカルトランジェントキャッシュをクリア
        delete_transient( 'update_plugins' );
        
        // プラグインリストキャッシュをクリア
        if ( function_exists( 'wp_cache_flush' ) ) {
            wp_cache_flush();
        }
        
        // オブジェクトキャッシュをクリア（利用可能な場合）
        if ( function_exists( 'wp_cache_flush_group' ) ) {
            wp_cache_flush_group( 'plugins' );
        }
        
        // KantanPro固有のキャッシュをクリア
        delete_transient( 'ktpwp_last_update_check' );
        delete_transient( 'ktpwp_last_force_check' );
        delete_option( 'ktpwp_update_available' );
        delete_option( 'ktpwp_latest_version' );
        delete_option( 'ktpwp_update_notice_dismissed' );
        delete_option( 'ktpwp_frontend_update_notice_dismissed' );
        delete_option( 'ktpwp_header_update_notice_dismissed' );
        delete_option( 'ktpwp_header_dismissed_version' );
        delete_option( 'ktpwp_last_frontend_check' );
        
        error_log( 'KantanPro: プラグイン情報キャッシュとKantanPro固有キャッシュをクリアしました' );
    }

    /**
     * バージョン更新時の自動キャッシュクリア
     */
    public function auto_clear_cache_on_version_update() {
        $old_version = get_option( 'ktpwp_version', '0' );
        $new_version = KANTANPRO_PLUGIN_VERSION;
        
        // バージョンが変更された場合
        if ( $old_version !== $new_version ) {
            $this->clear_plugin_cache();
            
            // 新しいバージョンを保存
            update_option( 'ktpwp_version', $new_version );
            
            error_log( 'KantanPro: バージョン更新を検出 - ' . $old_version . ' → ' . $new_version . ' (キャッシュクリア完了)' );
        }
    }

    /**
     * AJAXキャッシュクリア処理
     */
    public function ajax_clear_plugin_cache() {
        // 権限チェック
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array(
                'message' => '権限がありません。',
                'error_type' => 'permission_denied'
            ) );
            return;
        }
        
        // ナンスチェック
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'ktpwp_update_checker' ) ) {
            wp_send_json_error( array(
                'message' => 'セキュリティチェックに失敗しました。',
                'error_type' => 'invalid_nonce'
            ) );
            return;
        }
        
        try {
            // キャッシュをクリア
            $this->clear_plugin_cache();
            
            // デバッグ情報を追加
            $debug_info = array(
                'current_version' => $this->current_version,
                'github_repo' => $this->github_repo,
                'last_check' => get_transient( 'ktpwp_last_update_check' ),
                'update_available' => get_option( 'ktpwp_update_available' ),
                'latest_version' => get_option( 'ktpwp_latest_version' ),
                'cache_cleared' => true
            );
            
            wp_send_json_success( array(
                'message' => 'キャッシュが正常にクリアされました。更新チェックを再実行してください。',
                'debug_info' => $debug_info
            ) );
            
        } catch ( Exception $e ) {
            wp_send_json_error( array(
                'message' => 'キャッシュクリアに失敗しました: ' . $e->getMessage(),
                'error_type' => 'cache_clear_failed'
            ) );
        }
    }

    /**
     * 初期化時の強制更新チェック
     */
    public function force_update_check_on_init() {
        // 1日1回のみ実行
        $last_force_check = get_transient( 'ktpwp_last_force_check' );
        if ( $last_force_check && ( time() - $last_force_check ) < DAY_IN_SECONDS ) {
            return;
        }
        
        // 強制更新チェックを実行
        $this->check_github_updates();
        
        // 最後の強制チェック時刻を記録
        set_transient( 'ktpwp_last_force_check', time(), DAY_IN_SECONDS );
        
        error_log( 'KantanPro: 強制更新チェックを実行しました' );
    }
    
    /**
     * GitHubトークンの再読み込み
     */
    public function reload_github_token( $old_value, $new_value ) {
        if ( isset( $new_value['github_token'] ) ) {
            $this->github_token = $new_value['github_token'];
            error_log( 'KantanPro: GitHubトークンが更新されました' );
        }
    }

    /**
     * プラグイン無効化時のクリーンアップ
     */
    public static function deactivate() {
        // スケジュールされたイベントをクリア
        wp_clear_scheduled_hook( 'ktpwp_daily_update_check' );
        
        // 一時的なオプションをクリア
        delete_option( 'ktpwp_last_update_check' );
        delete_option( 'ktpwp_last_frontend_check' );
        delete_option( 'ktpwp_update_notice_dismissed' );
        delete_option( 'ktpwp_frontend_update_notice_dismissed' );
        delete_option( 'ktpwp_frontend_dismissed_version' );
        delete_option( 'ktpwp_update_available' );
    }
}

// 無効化時のクリーンアップ
register_deactivation_hook( KANTANPRO_PLUGIN_FILE, array( 'KTPWP_Update_Checker', 'deactivate' ) ); 