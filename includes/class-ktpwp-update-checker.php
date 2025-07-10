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
    private $github_repo = 'KantanPro/freeKTP';
    
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
    private $check_interval = 86400; // 24時間
    
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
        
        // フック設定
        add_action( 'init', array( $this, 'init' ) );
        add_action( 'admin_init', array( $this, 'admin_init' ) );
        add_action( 'wp_footer', array( $this, 'check_frontend_update' ) );
        
        // プラグインメタ行にリンクを追加（即座に登録）
        add_filter( 'plugin_row_meta', array( $this, 'add_check_update_meta_link' ), 10, 2 );
        add_action( 'admin_post_ktpwp_check_update', array( $this, 'handle_manual_update_check' ) );
        
        // 更新通知の表示
        add_action( 'admin_notices', array( $this, 'show_update_notice' ) );
        
        // AJAX処理
        add_action( 'wp_ajax_ktpwp_dismiss_update_notice', array( $this, 'dismiss_update_notice' ) );
        add_action( 'wp_ajax_ktpwp_check_github_update', array( $this, 'ajax_check_github_update' ) );
        
        // デバッグ用のログ出力
        error_log( 'KantanPro Update Checker: 初期化完了 - basename: ' . $this->plugin_basename );
        error_log( 'KantanPro Update Checker: フック名: plugin_row_meta' );
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
            array_push( $plugin_meta, $check_link );
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
        // セキュリティチェック
        if ( ! wp_verify_nonce( $_POST['nonce'], 'ktpwp_update_checker' ) ) {
            wp_die( 'セキュリティチェックに失敗しました。' );
        }
        
        if ( ! current_user_can( 'update_plugins' ) ) {
            wp_die( 'この操作を実行する権限がありません。' );
        }
        
        // 更新チェック実行
        $update_available = $this->check_github_updates();
        
        if ( $update_available ) {
            wp_send_json_success( array(
                'message' => __( '新しいバージョンが利用可能です！', 'KantanPro' ),
                'reload' => true
            ) );
        } else {
            wp_send_json_success( array(
                'message' => __( '最新バージョンです。', 'KantanPro' ),
                'reload' => false
            ) );
        }
    }
    
    /**
     * GitHubから更新情報をチェック
     */
    public function check_github_updates() {
        // レート制限チェック
        $last_check = get_option( 'ktpwp_last_update_check', 0 );
        $current_time = time();
        
        if ( ( $current_time - $last_check ) < 3600 ) { // 1時間未満の場合はスキップ
            return false;
        }
        
        // GitHub API URL
        $api_url = 'https://api.github.com/repos/' . $this->github_repo . '/releases/latest';
        
        // GitHub APIからリリース情報を取得
        $response = wp_remote_get( $api_url, array(
            'timeout' => 30,
            'user-agent' => 'KantanPro/' . $this->current_version . '; ' . get_bloginfo( 'url' )
        ) );
        
        if ( is_wp_error( $response ) ) {
            error_log( 'KantanPro: GitHub API エラー: ' . $response->get_error_message() );
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code( $response );
        if ( $response_code !== 200 ) {
            error_log( 'KantanPro: GitHub API HTTPエラー: ' . $response_code );
            return false;
        }
        
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );
        
        if ( empty( $data['tag_name'] ) ) {
            error_log( 'KantanPro: GitHub APIレスポンスにtag_nameがありません' );
            return false;
        }
        
        $latest_version = $data['tag_name'];
        $download_url = $data['zipball_url'];
        
        // 最後のチェック時刻を更新
        update_option( 'ktpwp_last_update_check', $current_time );
        
        // バージョン比較（プレビューバージョンの処理）
        $clean_current_version = $this->clean_version( $this->current_version );
        $clean_latest_version = $this->clean_version( $latest_version );
        
        if ( version_compare( $clean_current_version, $clean_latest_version, '<' ) ) {
            // 更新情報を保存
            $update_data = array(
                'new_version' => $latest_version,
                'download_url' => $download_url,
                'changelog' => $data['body'],
                'published_at' => $data['published_at'],
                'checked_at' => $current_time
            );
            
            update_option( 'ktpwp_update_available', $update_data );
            delete_option( 'ktpwp_update_notice_dismissed' );
            
            return true;
        } else {
            // 更新情報をクリア
            delete_option( 'ktpwp_update_available' );
            delete_option( 'ktpwp_update_notice_dismissed' );
            
            return false;
        }
    }
    
    /**
     * 更新通知を表示
     */
    public function show_update_notice() {
        // 管理画面でのみ表示
        if ( ! is_admin() ) {
            return;
        }
        
        // 権限チェック
        if ( ! current_user_can( 'update_plugins' ) ) {
            return;
        }
        
        // 更新情報を取得
        $update_data = get_option( 'ktpwp_update_available', false );
        if ( ! $update_data ) {
            return;
        }
        
        // 通知が無視されているかチェック
        if ( get_option( 'ktpwp_update_notice_dismissed', false ) ) {
            return;
        }
        
        $plugin_name = get_plugin_data( KANTANPRO_PLUGIN_FILE )['Name'];
        $new_version = $update_data['new_version'];
        $github_url = 'https://github.com/' . $this->github_repo . '/releases/tag/' . $new_version;
        
        ?>
        <div class="notice notice-warning is-dismissible" id="ktpwp-update-notice">
            <p>
                <strong><?php echo esc_html( $plugin_name ); ?></strong> の新しいバージョン 
                <strong><?php echo esc_html( $new_version ); ?></strong> が利用可能です。
                <a href="<?php echo esc_url( $github_url ); ?>" target="_blank">更新内容を確認</a> | 
                <a href="<?php echo esc_url( $github_url ); ?>" target="_blank">今すぐ更新</a>
            </p>
        </div>
        <script>
        jQuery(document).ready(function($) {
            $('#ktpwp-update-notice').on('click', '.notice-dismiss', function() {
                $.post(ajaxurl, {
                    action: 'ktpwp_dismiss_update_notice',
                    nonce: '<?php echo wp_create_nonce( 'ktpwp_dismiss_update_notice' ); ?>'
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * 更新通知の無視
     */
    public function dismiss_update_notice() {
        // セキュリティチェック
        if ( ! wp_verify_nonce( $_POST['nonce'], 'ktpwp_dismiss_update_notice' ) ) {
            wp_die( 'セキュリティチェックに失敗しました。' );
        }
        
        if ( ! current_user_can( 'update_plugins' ) ) {
            wp_die( 'この操作を実行する権限がありません。' );
        }
        
        update_option( 'ktpwp_update_notice_dismissed', true );
        wp_die();
    }
    
    /**
     * フロントエンドでの更新チェック
     */
    public function check_frontend_update() {
        // 管理者でない場合は何もしない
        if ( ! current_user_can( 'update_plugins' ) ) {
            return;
        }
        
        // KantanProが表示されているページでのみ実行
        global $post;
        if ( ! is_a( $post, 'WP_Post' ) || ! has_shortcode( $post->post_content, 'ktpwp_all_tab' ) ) {
            return;
        }
        
        // 1日1回のみチェック
        $last_frontend_check = get_option( 'ktpwp_last_frontend_check', 0 );
        $current_time = time();
        
        if ( ( $current_time - $last_frontend_check ) < $this->check_interval ) {
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
     * フロントエンドでの更新通知表示
     */
    private function show_frontend_update_notice() {
        // 通知が無視されているかチェック
        if ( get_option( 'ktpwp_frontend_update_notice_dismissed', false ) ) {
            return;
        }
        
        $update_data = get_option( 'ktpwp_update_available', false );
        if ( ! $update_data ) {
            return;
        }
        
        $plugin_name = get_plugin_data( KANTANPRO_PLUGIN_FILE )['Name'];
        $new_version = $update_data['new_version'];
        $github_url = 'https://github.com/' . $this->github_repo . '/releases/tag/' . $new_version;
        
        ?>
        <div id="ktpwp-frontend-update-notice" style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 10px; margin: 10px 0; border-radius: 4px; position: relative;">
            <button type="button" style="position: absolute; top: 5px; right: 5px; background: none; border: none; font-size: 16px; cursor: pointer;" onclick="ktpwpDismissFrontendNotice()">&times;</button>
            <p style="margin: 0; color: #856404;">
                <strong><?php echo esc_html( $plugin_name ); ?></strong> の新しいバージョン 
                <strong><?php echo esc_html( $new_version ); ?></strong> が利用可能です。
                <a href="<?php echo esc_url( admin_url( 'plugins.php' ) ); ?>" style="color: #0073aa;">管理画面で更新</a>
            </p>
        </div>
        <script>
        function ktpwpDismissFrontendNotice() {
            document.getElementById('ktpwp-frontend-update-notice').style.display = 'none';
            // AJAX で無視フラグを設定
            if (typeof jQuery !== 'undefined') {
                jQuery.post('<?php echo admin_url( 'admin-ajax.php' ); ?>', {
                    action: 'ktpwp_dismiss_frontend_update_notice',
                    nonce: '<?php echo wp_create_nonce( 'ktpwp_dismiss_frontend_update_notice' ); ?>'
                });
            }
        }
        </script>
        <?php
        
        // フロントエンド通知の無視処理
        add_action( 'wp_ajax_ktpwp_dismiss_frontend_update_notice', array( $this, 'dismiss_frontend_update_notice' ) );
        add_action( 'wp_ajax_nopriv_ktpwp_dismiss_frontend_update_notice', array( $this, 'dismiss_frontend_update_notice' ) );
    }
    
    /**
     * フロントエンド更新通知の無視
     */
    public function dismiss_frontend_update_notice() {
        // セキュリティチェック
        if ( ! wp_verify_nonce( $_POST['nonce'], 'ktpwp_dismiss_frontend_update_notice' ) ) {
            wp_die( 'セキュリティチェックに失敗しました。' );
        }
        
        update_option( 'ktpwp_frontend_update_notice_dismissed', true );
        wp_die();
    }
    
    /**
     * バージョン文字列をクリーンにする
     * 
     * @param string $version バージョン文字列
     * @return string クリーンなバージョン文字列
     */
    private function clean_version( $version ) {
        // (preview)や(beta)などの文字を削除
        $version = preg_replace( '/\([^)]*\)/', '', $version );
        // 先頭の'v'を削除
        $version = ltrim( $version, 'v' );
        // 空白を削除
        $version = trim( $version );
        
        return $version;
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
        delete_option( 'ktpwp_update_available' );
    }
}

// 無効化時のクリーンアップ
register_deactivation_hook( KANTANPRO_PLUGIN_FILE, array( 'KTPWP_Update_Checker', 'deactivate' ) ); 