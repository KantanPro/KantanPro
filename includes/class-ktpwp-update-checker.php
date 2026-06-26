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
    private $github_repo = 'KantanPro/KantanProEx';
    
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
     * ヘッダーバッジ用の更新チェック間隔（秒）
     */
    private $badge_check_interval = 3600;
    
    /**
     * フロントエンド通知の実行フラグ
     */
    private $frontend_notice_shown = false;
    
    /**
     * コンストラクタ
     */
    public function __construct() {
        $this->plugin_basename = $this->detect_canonical_plugin_basename();

        if ( class_exists( 'KTPWP_Edition' ) ) {
            $this->github_repo = KTPWP_Edition::get_github_repo();
        }
        
        $this->plugin_slug = dirname( $this->plugin_basename );
        $this->current_version = defined( 'KANTANPRO_PLUGIN_VERSION' ) ? KANTANPRO_PLUGIN_VERSION : '1.0.5';
        
        // GitHubトークンを設定から取得
        $update_settings = $this->get_update_notification_settings();
        $this->github_token = isset( $update_settings['github_token'] ) ? $update_settings['github_token'] : '';
        
        // チェック間隔を設定から取得
        $check_interval_hours = isset( $update_settings['check_interval'] ) ? intval( $update_settings['check_interval'] ) : 24;
        $this->check_interval = $check_interval_hours * 3600; // 時間を秒に変換

        // 日次更新チェックの WP-Cron 登録（管理画面アクセス不要）
        add_action( 'init', array( $this, 'init' ), 5 );
        
        // WordPress.orgとの接続エラーを防ぐため、管理画面でのみフックを設定
        if ( is_admin() ) {
            // フック設定
            add_action( 'admin_init', array( $this, 'admin_init' ) );
            add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_plugin_update' ) );
            add_filter( 'site_transient_update_plugins', array( $this, 'check_for_plugin_update' ) );
            add_filter( 'plugins_api', array( $this, 'plugins_api_handler' ), 20, 3 );
            add_filter( 'upgrader_pre_download', array( $this, 'upgrader_pre_download' ), 10, 3 );
            add_filter( 'upgrader_source_selection', array( $this, 'normalize_github_zipball_source' ), 1, 4 );
            add_filter( 'upgrader_pre_install', array( $this, 'before_update' ), 10, 3 );
            add_filter( 'upgrader_post_install', array( $this, 'rename_github_source' ), 9, 3 );
            add_filter( 'upgrader_post_install', array( $this, 'after_update' ), 10, 3 );
            add_action( 'upgrader_process_complete', array( $this, 'handle_auto_activation' ), 10, 2 );

            
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
        }

        // ヘッダー更新チェック AJAX（フロントエンドからも利用）
        add_action( 'wp_ajax_ktpwp_dismiss_header_update_notice', array( $this, 'dismiss_header_update_notice' ) );
        add_action( 'wp_ajax_ktpwp_check_header_update', array( $this, 'ajax_check_header_update' ) );
        add_action( 'wp_ajax_ktpwp_poll_header_update', array( $this, 'ajax_poll_header_update' ) );

        // フロントエンドでの定期更新チェック（管理画面アクセス不要・バナー表示とは独立）
        if ( ! is_admin() && $this->is_update_notification_enabled() ) {
            add_action( 'wp', array( $this, 'maybe_check_on_ktpwp_page' ), 20 );
        }
        
        // フロントエンド通知バナーは条件付きで設定
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
     * 一時展開パスを避け、現在の正規プラグインベースネームを検出する
     *
     * @return string
     */
    private function detect_canonical_plugin_basename() {
        $fallback = 'KantanProEX/ktpwp.php';

        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugins = get_plugins();
        if ( is_array( $plugins ) ) {
            // 安定ディレクトリ（KantanProEX/）を最優先
            if ( isset( $plugins['KantanProEX/ktpwp.php'] ) ) {
                $name = isset( $plugins['KantanProEX/ktpwp.php']['Name'] ) ? (string) $plugins['KantanProEX/ktpwp.php']['Name'] : '';
                if ( stripos( $name, 'KantanProEX' ) !== false ) {
                    return 'KantanProEX/ktpwp.php';
                }
            }

            foreach ( $plugins as $basename => $plugin_data ) {
                if ( basename( $basename ) !== 'ktpwp.php' ) {
                    continue;
                }

                $name = isset( $plugin_data['Name'] ) ? (string) $plugin_data['Name'] : '';
                if ( stripos( $name, 'KantanProEX' ) === false ) {
                    continue;
                }

                // 一時展開ディレクトリ由来のベースネームは採用しない
                if ( strpos( $basename, '~' ) !== false ) {
                    continue;
                }

                // GitHub zip 更新時の一時フォルダ（例: KantanPro-KantanProEx-a6ca47e）は正規キーにしない
                $dir = dirname( $basename );
                if ( preg_match( '/^KantanPro-KantanProEx-[a-f0-9]{6,}$/i', $dir ) ) {
                    continue;
                }

                return (string) $basename;
            }
        }

        if ( defined( 'KANTANPRO_PLUGIN_FILE' ) && file_exists( KANTANPRO_PLUGIN_FILE ) ) {
            $detected = plugin_basename( KANTANPRO_PLUGIN_FILE );
            if ( is_string( $detected ) && $detected !== '' && strpos( $detected, '~' ) === false ) {
                return $detected;
            }
        }

        $main_plugin_file = dirname( __DIR__ ) . '/ktpwp.php';
        if ( file_exists( $main_plugin_file ) ) {
            $detected = plugin_basename( $main_plugin_file );
            if ( is_string( $detected ) && $detected !== '' && strpos( $detected, '~' ) === false ) {
                return $detected;
            }
        }

        return $fallback;
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

        $this->maybe_clear_stale_update_available();
    }

    /**
     * 更新チェッカー用の一意なキーを生成する
     *
     * @param string $suffix キーのサフィックス
     * @return string
     */
    private function key( $suffix ) {
        return 'ktpwp_upd_' . md5( $this->plugin_basename ) . '_' . $suffix;
    }

    /**
     * プラグインベースネームを比較用に正規化する
     *
     * @param string $basename ベースネーム
     * @return string
     */
    private function normalize_basename( $basename ) {
        $file = strtolower( basename( $basename ) );
        $dir  = strtolower( dirname( $basename ) );
        $dir  = str_replace( array( '-', '_', '.', '/' ), '', $dir );

        return $dir . '/' . $file;
    }

    /**
     * 指定ベースネームがこの更新対象プラグインか判定する
     *
     * @param string $basename ベースネーム
     * @return bool
     */
    private function is_target_plugin_basename( $basename ) {
        if ( ! is_string( $basename ) || $basename === '' ) {
            return false;
        }

        $normalized_target = $this->normalize_basename( $this->plugin_basename );
        $normalized_given  = $this->normalize_basename( $basename );

        if ( $normalized_target === $normalized_given ) {
            return true;
        }

        $file = strtolower( basename( $basename ) );
        if ( $file !== 'ktpwp.php' ) {
            return false;
        }

        $dir = strtolower( dirname( $basename ) );

        // 標準インストール先。strpos で kantanproex を含む判定は kantanpro-kantanproex-* に誤マッチするため使わない。
        if ( $dir === strtolower( $this->plugin_slug ) ) {
            return true;
        }

        // GitHub zipball のルートフォルダ（Owner-Repo-commit）
        if ( preg_match( '/^kantanpro-kantanproex-[a-f0-9]{6,}$/i', $dir ) ) {
            return true;
        }

        return false;
    }

    /**
     * 更新フック引数から対象プラグインのベースネームを解決する
     *
     * @param array $hook_extra フック情報
     * @return string
     */
    private function resolve_target_basename( $hook_extra ) {
        if ( isset( $hook_extra['plugin'] ) && $this->is_target_plugin_basename( $hook_extra['plugin'] ) ) {
            return (string) $hook_extra['plugin'];
        }

        if ( ! empty( $hook_extra['plugins'] ) && is_array( $hook_extra['plugins'] ) ) {
            foreach ( $hook_extra['plugins'] as $plugin_basename ) {
                if ( $this->is_target_plugin_basename( $plugin_basename ) ) {
                    return (string) $plugin_basename;
                }
            }
        }

        return $this->plugin_basename;
    }

    /**
     * 実在するプラグインベースネームへ解決する
     *
     * @param string $basename 候補ベースネーム
     * @return string
     */
    private function resolve_installed_basename( $basename ) {
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $installed_plugins = get_plugins();
        $basename          = (string) $basename;

        // 呼び出し側が提示したパスがそのまま存在するなら最優先（更新後の再有効化など）
        if ( $basename !== '' && isset( $installed_plugins[ $basename ] ) ) {
            return $basename;
        }

        $canonical = $this->plugin_basename;
        if ( isset( $installed_plugins[ $canonical ] ) ) {
            return $canonical;
        }

        // 正規フォルダ名を zipball の一時名より優先（get_plugins() のキー順に依存しない）
        $stable_key = $this->plugin_slug . '/ktpwp.php';
        foreach ( array_keys( $installed_plugins ) as $installed_basename ) {
            if ( strcasecmp( $installed_basename, $stable_key ) === 0 && $this->is_target_plugin_basename( $installed_basename ) ) {
                return (string) $installed_basename;
            }
        }

        foreach ( array_keys( $installed_plugins ) as $installed_basename ) {
            if ( $this->is_target_plugin_basename( $installed_basename ) ) {
                return (string) $installed_basename;
            }
        }

        return $basename;
    }

    /**
     * 対象プラグインを有効化し、成功可否を返す
     *
     * @param string $basename ベースネーム
     * @param bool $network_active ネットワーク有効化フラグ
     * @return bool
     */
    private function activate_target_plugin( $basename, $network_active = false ) {
        if ( ! function_exists( 'is_plugin_active' ) || ! function_exists( 'activate_plugin' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $resolved_basename = $this->resolve_installed_basename( $basename );
        if ( ! $this->is_target_plugin_basename( $resolved_basename ) ) {
            return false;
        }

        if ( is_plugin_active( $resolved_basename ) ) {
            return true;
        }

        if ( $network_active ) {
            $activation_result = activate_plugin( $resolved_basename, '', true );
        } else {
            $activation_result = activate_plugin( $resolved_basename );
        }

        return ! is_wp_error( $activation_result );
    }

    /**
     * WordPress標準更新後に有効化レコードだけが外れた場合でも、次回リクエストで読み込まれるよう復旧する。
     *
     * @param string $basename       プラグインベースネーム
     * @param bool   $network_active ネットワーク有効化フラグ
     * @return void
     */
    private function restore_active_plugin_record( $basename, $network_active = false ) {
        $resolved_basename = $this->resolve_installed_basename( $basename );
        if ( ! $this->is_target_plugin_basename( $resolved_basename ) ) {
            return;
        }

        if ( $network_active && function_exists( 'is_multisite' ) && is_multisite() ) {
            $sitewide = get_site_option( 'active_sitewide_plugins', array() );
            if ( ! is_array( $sitewide ) ) {
                $sitewide = array();
            }

            foreach ( array_keys( $sitewide ) as $key ) {
                if ( $key !== $resolved_basename && $this->is_target_plugin_basename( $key ) ) {
                    unset( $sitewide[ $key ] );
                }
            }

            $sitewide[ $resolved_basename ] = isset( $sitewide[ $resolved_basename ] ) ? (int) $sitewide[ $resolved_basename ] : time();
            update_site_option( 'active_sitewide_plugins', $sitewide );
            if ( function_exists( 'wp_clean_plugins_cache' ) ) {
                wp_clean_plugins_cache();
            }
            return;
        }

        $active_plugins = get_option( 'active_plugins', array() );
        if ( ! is_array( $active_plugins ) ) {
            $active_plugins = array();
        }

        $next = array();
        foreach ( $active_plugins as $plugin ) {
            if ( $plugin !== $resolved_basename && $this->is_target_plugin_basename( $plugin ) ) {
                continue;
            }
            $next[] = $plugin;
        }

        if ( ! in_array( $resolved_basename, $next, true ) ) {
            $next[] = $resolved_basename;
        }

        update_option( 'active_plugins', array_values( array_unique( $next ) ), false );
        if ( function_exists( 'wp_clean_plugins_cache' ) ) {
            wp_clean_plugins_cache();
        }
    }

    /**
     * 保留中の再有効化を再試行する
     *
     * @return void
     */
    private function retry_pending_activation() {
        $pending = get_site_transient( $this->key( 'pending_activation' ) );
        if ( ! is_array( $pending ) || empty( $pending['basename'] ) ) {
            return;
        }

        $network_active = ! empty( $pending['network_active'] );
        if ( $this->activate_target_plugin( (string) $pending['basename'], $network_active ) ) {
            delete_site_transient( $this->key( 'pending_activation' ) );
            set_transient( $this->key( 'admin_reload' ), 1, 5 * MINUTE_IN_SECONDS );
        }
    }

    /**
     * GitHub API/ダウンロード用ヘッダーを生成する
     *
     * @return array
     */
    private function get_github_headers( $accept = 'application/vnd.github.v3+json' ) {
        $headers = array(
            'Accept' => $accept,
            'X-GitHub-Api-Version' => '2022-11-28',
            'User-Agent' => 'KantanPro-Plugin/' . $this->current_version,
            'Cache-Control' => 'no-cache',
        );

        $github_token = trim( (string) $this->github_token );
        if ( $github_token === '' && defined( 'KP_GITHUB_TOKEN' ) && KP_GITHUB_TOKEN ) {
            $github_token = trim( (string) KP_GITHUB_TOKEN );
        }

        if ( $github_token !== '' ) {
            $headers['Authorization'] = 'Bearer ' . $github_token;
        }

        return $headers;
    }

    /**
     * GitHubダウンロード用ヘッダーを生成する
     *
     * @param string $url ダウンロードURL
     * @return array
     */
    private function get_github_download_headers( $url = '' ) {
        $accept = 'application/vnd.github.v3+json';
        if ( strpos( $url, 'api.github.com' ) !== false && strpos( $url, '/releases/assets/' ) !== false ) {
            $accept = 'application/octet-stream';
        }

        return $this->get_github_headers( $accept );
    }

    /**
     * GitHub関連URLかどうかを判定する
     *
     * @param string $url URL
     * @return bool
     */
    private function is_github_url( $url ) {
        return is_string( $url ) && (
            strpos( $url, 'github.com' ) !== false ||
            strpos( $url, 'githubusercontent.com' ) !== false
        );
    }

    /**
     * 最新の公開済みGitHub Releaseを取得する
     *
     * /releases/latest が取れない場合は /releases の先頭から draft/prerelease を除外して取得する。
     *
     * @return array|false
     */
    private function get_latest_github_release() {
        $args = array(
            'timeout' => 30,
            'headers' => $this->get_github_headers(),
        );

        $latest_url = 'https://api.github.com/repos/' . $this->github_repo . '/releases/latest';
        error_log( 'KantanPro: GitHub APIに接続中: ' . $latest_url );
        $response = wp_remote_get( $latest_url, $args );

        if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
            $data = json_decode( wp_remote_retrieve_body( $response ), true );
            if ( is_array( $data ) && isset( $data['tag_name'] ) && empty( $data['draft'] ) && empty( $data['prerelease'] ) ) {
                return $data;
            }
        } else {
            if ( is_wp_error( $response ) ) {
                error_log( 'KantanPro: GitHub latest API接続エラー: ' . $response->get_error_message() );
            } else {
                error_log( 'KantanPro: GitHub latest APIレスポンス: ' . wp_remote_retrieve_response_code( $response ) . ' - ' . wp_remote_retrieve_body( $response ) );
            }
        }

        $list_url = 'https://api.github.com/repos/' . $this->github_repo . '/releases';
        error_log( 'KantanPro: GitHub Releases一覧にフォールバックします: ' . $list_url );
        $list_response = wp_remote_get( $list_url, $args );

        if ( is_wp_error( $list_response ) ) {
            error_log( 'KantanPro: GitHub releases API接続エラー: ' . $list_response->get_error_message() );
            return false;
        }

        $response_code = wp_remote_retrieve_response_code( $list_response );
        if ( $response_code !== 200 ) {
            error_log( 'KantanPro: GitHub releases API エラーレスポンス: ' . $response_code . ' - ' . wp_remote_retrieve_body( $list_response ) );
            return false;
        }

        $releases = json_decode( wp_remote_retrieve_body( $list_response ), true );
        if ( ! is_array( $releases ) ) {
            return false;
        }

        foreach ( $releases as $release ) {
            if ( ! empty( $release['draft'] ) || ! empty( $release['prerelease'] ) ) {
                continue;
            }
            if ( isset( $release['tag_name'] ) ) {
                return $release;
            }
        }

        return false;
    }
    
    /**
     * 管理画面の初期化
     */
    public function admin_init() {
        // 管理画面でのスクリプトとスタイルの読み込み
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
        $this->reconcile_active_plugin_keys();
        $this->sanitize_update_plugins_transient();
        $this->retry_pending_activation();
        $this->maybe_reload_admin_after_activation();
    }

    /**
     * active_plugins 内に残った一時ベースネームを正規キーへ補正する
     *
     * @return void
     */
    private function reconcile_active_plugin_keys() {
        $active_plugins = get_option( 'active_plugins', array() );
        if ( ! is_array( $active_plugins ) || empty( $active_plugins ) ) {
            return;
        }

        $changed = false;
        $has_canonical = in_array( $this->plugin_basename, $active_plugins, true );

        foreach ( $active_plugins as $index => $basename ) {
            if ( ! $this->is_target_plugin_basename( $basename ) ) {
                continue;
            }
            if ( $basename === $this->plugin_basename ) {
                continue;
            }

            unset( $active_plugins[ $index ] );
            $changed = true;
        }

        if ( ! $has_canonical ) {
            $active_plugins[] = $this->plugin_basename;
            $changed = true;
        }

        if ( $changed ) {
            $active_plugins = array_values( array_unique( $active_plugins ) );
            update_option( 'active_plugins', $active_plugins, false );
            wp_clean_plugins_cache();
        }
    }

    /**
     * update_plugins トランジェント内の一時ベースネームを正規化する
     *
     * @return void
     */
    private function sanitize_update_plugins_transient() {
        $transient = get_site_transient( 'update_plugins' );
        if ( ! is_object( $transient ) ) {
            return;
        }

        $changed = false;
        foreach ( array( 'checked', 'response', 'no_update' ) as $bucket ) {
            if ( ! isset( $transient->{$bucket} ) || ! is_array( $transient->{$bucket} ) ) {
                continue;
            }

            foreach ( array_keys( $transient->{$bucket} ) as $basename ) {
                if ( ! $this->is_target_plugin_basename( $basename ) ) {
                    continue;
                }
                if ( $basename === $this->plugin_basename ) {
                    continue;
                }

                $value = $transient->{$bucket}[ $basename ];
                unset( $transient->{$bucket}[ $basename ] );

                if ( $bucket === 'response' && is_object( $value ) ) {
                    $value->plugin = $this->plugin_basename;
                    if ( property_exists( $value, 'id' ) ) {
                        $value->id = $this->plugin_slug;
                    }
                }

                $transient->{$bucket}[ $this->plugin_basename ] = $value;
                $changed = true;
            }
        }

        if ( $changed ) {
            set_site_transient( 'update_plugins', $transient );
        }
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
                'checking_text' => __( '更新をチェック中...', 'ktpwp' ),
                'check_text' => __( '更新をチェック', 'ktpwp' ),
                'error_text' => __( 'エラーが発生しました', 'ktpwp' ),
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
            
            // プラグイン更新結果画面では「更新が利用可能です」を出さない（更新直後の違和感を防ぐ）
            $is_plugin_update_result = in_array( $hook, array( 'update-core.php', 'update.php' ), true );
            if ( $is_plugin_update_result ) {
                wp_localize_script( 'ktpwp-update-balloon', 'ktpwp_update_data', array( 'has_update' => false ) );
                wp_add_inline_script(
                    'ktpwp-update-balloon',
                    'var ktpwp_update_badge_available = ' . wp_json_encode( false ) . ';',
                    'before'
                );
            } else {
                // 更新データがある場合は設定
                $update_data = get_option( 'ktpwp_update_available', false );
                if ( $this->is_update_newer_than_installed( $update_data ) ) {
                    wp_localize_script( 'ktpwp-update-balloon', 'ktpwp_update_data', array(
                        'has_update' => true,
                        'message' => __( '新しいバージョンが利用可能です！', 'ktpwp' ),
                        'update_data' => $update_data
                    ) );
                }
                wp_add_inline_script(
                    'ktpwp-update-balloon',
                    'var ktpwp_update_badge_available = ' . wp_json_encode( (bool) $this->has_header_update_badge() ) . ';',
                    'before'
                );
            }
        }
    }
    
    /**
     * プラグインメタ行のリンクを調整する
     * - 自前の「更新をチェック」「キャッシュクリア」を除去
     * - PluginURI のラベルを「詳細を表示」に変更
     */
    public function add_check_update_meta_link( $plugin_meta, $plugin_file ) {
        if ( $plugin_file !== $this->plugin_basename ) {
            return $plugin_meta;
        }

        $new_meta        = array();
        $details_added   = false; // WP標準の詳細モーダル用リンクの重複防止
        $has_plugin_uri  = false; // PluginURI リンクが存在したか
        $plugin_data = function_exists( 'get_plugin_data' ) && defined( 'KANTANPRO_PLUGIN_FILE' )
            ? get_plugin_data( KANTANPRO_PLUGIN_FILE )
            : array();
        $plugin_uri  = ! empty( $plugin_data['PluginURI'] ) ? $plugin_data['PluginURI'] : '';

        // PluginURI の href 厳密一致文字列（末尾スラッシュ有無の両方を考慮）
        $plugin_uri_with_slash = $plugin_uri ? trailingslashit( $plugin_uri ) : '';
        $plugin_uri_no_slash   = $plugin_uri ? rtrim( $plugin_uri, '/' ) : '';
        $href_exact_1 = $plugin_uri ? 'href="' . esc_attr( $plugin_uri_with_slash ) . '"' : '';
        $href_exact_2 = $plugin_uri ? 'href="' . esc_attr( $plugin_uri_no_slash ) . '"' : '';

        foreach ( (array) $plugin_meta as $meta_html ) {
            // 自前で追加していたリンクは除外
            if ( strpos( $meta_html, 'id="ktpwp-manual-check"' ) !== false ) {
                continue;
            }
            if ( strpos( $meta_html, 'id="ktpwp-cache-clear"' ) !== false ) {
                continue;
            }

            // PluginURI へのリンク（href が厳密一致）の場合は除外して後で1つだけ追加
            if (
                $plugin_uri && (
                    strpos( $meta_html, $href_exact_1 ) !== false ||
                    strpos( $meta_html, $href_exact_2 ) !== false
                )
            ) {
                $has_plugin_uri = true;
                continue;
            }

            // 既存の WordPress 標準の「詳細を表示」（thickbox）リンクがある場合は重複しないようフラグのみ立てる
            if (
                strpos( $meta_html, 'open-plugin-details-modal' ) !== false ||
                strpos( $meta_html, 'plugin-install.php?tab=plugin-information' ) !== false
            ) {
                $details_added = true;
            }

            $new_meta[] = $meta_html;
        }

        // 既存に無ければ、WordPress標準モーダル用の「詳細を表示」を1つ追加
        if ( ! $details_added ) {
            $details_link = sprintf(
                '<a href="%s" class="thickbox open-plugin-details-modal" aria-label="%s">%s</a>',
                esc_url( network_admin_url( 'plugin-install.php?tab=plugin-information&plugin=' . rawurlencode( $this->plugin_slug ) . '&TB_iframe=true&width=600&height=550' ) ),
                esc_attr( sprintf( __( '%s の詳細を表示', 'ktpwp' ), isset( $plugin_data['Name'] ) ? $plugin_data['Name'] : 'ktpwp' ) ),
                esc_html__( '詳細を表示', 'ktpwp' )
            );
            $new_meta[] = $details_link;
        }

        return $new_meta;
    }

    /**
     * plugins_api フィルターでプラグイン詳細モーダル用情報を返す
     */
    public function plugins_api_handler( $result, $action, $args ) {
        if ( $action !== 'plugin_information' ) {
            return $result;
        }

        $requested_slug = isset( $args->slug ) ? $args->slug : ( isset( $args->plugin ) ? $args->plugin : '' );
        if ( ! $requested_slug ) {
            return $result;
        }

        // 自プラグイン以外はスルー
        if ( $requested_slug !== $this->plugin_slug ) {
            return $result;
        }

        // 既存の更新データやプラグインデータから情報を構築
        $update_data = get_option( 'ktpwp_update_available', array() );
        $plugin_data = function_exists( 'get_plugin_data' ) && defined( 'KANTANPRO_PLUGIN_FILE' )
            ? get_plugin_data( KANTANPRO_PLUGIN_FILE )
            : array();

        $info = new stdClass();
        $info->name           = isset( $plugin_data['Name'] ) ? $plugin_data['Name'] : 'ktpwp';
        $info->slug           = $this->plugin_slug;
        $info->version        = isset( $update_data['new_version'] ) ? $update_data['new_version'] : $this->current_version;
        $info->author         = isset( $plugin_data['Author'] ) ? $plugin_data['Author'] : 'ktpwp';
        $info->author_profile = isset( $plugin_data['AuthorURI'] ) ? $plugin_data['AuthorURI'] : 'https://www.kantanpro.com/kantanpro-page';
        $info->homepage       = isset( $plugin_data['PluginURI'] ) ? $plugin_data['PluginURI'] : 'https://www.kantanpro.com/';
        $info->requires       = '5.0';
        $info->tested         = get_bloginfo( 'version' );
        $info->requires_php   = '7.4';
        $info->last_updated   = isset( $update_data['published_at'] ) ? $update_data['published_at'] : '';
        $info->download_link  = isset( $update_data['download_url'] ) ? $update_data['download_url'] : '';

        $info->sections = array();
        $info->sections['description'] = isset( $plugin_data['Description'] )
            ? $plugin_data['Description']
            : __( 'スモールビジネスのための販売支援ツール', 'ktpwp' );
        $info->sections['changelog']   = isset( $update_data['changelog'] ) && ! empty( $update_data['changelog'] )
            ? $update_data['changelog']
            : __( '詳細な変更履歴は公式サイトまたはリポジトリをご確認ください。', 'ktpwp' );

        $banner_url = function_exists( 'ktpwp_plugin_asset_url' )
            ? ktpwp_plugin_asset_url( 'images/default/header_bg_image.png' )
            : KANTANPRO_PLUGIN_URL . 'images/default/header_bg_image.png';
        $icon_url = function_exists( 'ktpwp_plugin_asset_url' )
            ? ktpwp_plugin_asset_url( 'images/default/icon.png' )
            : KANTANPRO_PLUGIN_URL . 'images/default/icon.png';

        $info->banners = array(
            'high' => $banner_url,
            'low'  => $banner_url,
        );
        $info->icons = array(
            'default' => $icon_url,
        );

        return $info;
    }
    
    /**
     * 手動更新チェックの処理
     */
    public function handle_manual_update_check() {
        // セキュリティチェック
        if ( ! wp_verify_nonce( $_POST['_wpnonce'], 'ktpwp_manual_update_check' ) ) {
            wp_die( __( 'セキュリティチェックに失敗しました。', 'ktpwp' ) );
        }
        
        if ( ! current_user_can( 'update_plugins' ) ) {
            wp_die( __( 'この操作を実行する権限がありません。', 'ktpwp' ) );
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
                'message' => __( 'セキュリティトークンが送信されていません。', 'ktpwp' ),
                'error_type' => 'missing_nonce'
            ) );
            return;
        }
        
        // セキュリティチェック
        if ( ! wp_verify_nonce( $_POST['nonce'], 'ktpwp_update_checker' ) ) {
            error_log( 'KantanPro: ajax_check_github_update - nonce検証に失敗しました' );
            wp_send_json_error( array(
                'message' => __( 'セキュリティチェックに失敗しました。', 'ktpwp' ),
                'error_type' => 'security'
            ) );
            return;
        }
        
        if ( ! current_user_can( 'update_plugins' ) ) {
            wp_send_json_error( array(
                'message' => __( 'この操作を実行する権限がありません。', 'ktpwp' ),
                'error_type' => 'permission'
            ) );
            return;
        }
        
        // 更新チェック実行
        $this->clear_plugin_cache();
        $this->check_github_updates();
        $status = $this->resolve_header_update_status();
        $debug_info = array(
            'current_version' => $this->current_version,
            'github_repo' => $this->github_repo,
            'latest_version' => get_option( 'ktpwp_latest_version' ),
            'update_available' => get_option( 'ktpwp_update_available' ),
        );
        
        if ( $status['has_update'] ) {
            wp_send_json_success( array(
                'message' => __( '新しいバージョンが利用可能です！', 'ktpwp' ),
                'reload' => true,
                'has_update' => true,
                'update_data' => $status['update_data'],
                'debug_info' => $debug_info
            ) );
        } else {
            wp_send_json_success( array(
                'message' => __( '最新バージョンです。', 'ktpwp' ),
                'reload' => false,
                'has_update' => false,
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
            error_log( 'KantanPro: 更新チェック間隔が短いため、保存済み情報を使用します' );
            $cached = get_option( 'ktpwp_update_available' );
            if ( is_array( $cached ) ) {
                if ( ! empty( $cached['no_update'] ) ) {
                    return false;
                }
                if ( $this->is_update_newer_than_installed( $cached ) ) {
                    return $cached;
                }
                $this->maybe_clear_stale_update_available();
            }
            return false;
        }

        // エラーハンドリングを強化
        try {
            $data = $this->get_latest_github_release();
            if ( ! $data ) {
                error_log( 'KantanPro: GitHub Release情報を取得できませんでした' );
                $existing = get_option( 'ktpwp_update_available', false );
                if ( is_array( $existing ) && $this->has_header_update_badge() ) {
                    error_log( 'KantanPro: GitHub 取得失敗のため保存済み更新情報を維持します' );
                    return $existing;
                }
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

                // アセットからzipファイルのURLを探す
                $download_url = '';
                if ( ! empty($data['assets']) ) {
                    foreach ( $data['assets'] as $asset ) {
                        // EX用の固定名または配布スクリプトのバージョン付きZIPを優先する
                        if (
                            ( $asset['name'] === 'KantanProEX.zip' || preg_match( '/^KantanProEX_.*\.zip$/', $asset['name'] ) )
                            && $asset['content_type'] === 'application/zip'
                        ) {
                            $download_url = ! empty( $asset['url'] ) ? $asset['url'] : $asset['browser_download_url'];
                            error_log('KantanPro: Found release asset: ' . $download_url);
                            break;
                        }
                    }
                }
                // アセットが見つからない場合は、次に`.zip`で終わるアセットを探す
                if ( empty($download_url) && ! empty($data['assets']) ) {
                    foreach ( $data['assets'] as $asset ) {
                        if ( substr($asset['name'], -4) === '.zip' ) {
                            $download_url = ! empty( $asset['url'] ) ? $asset['url'] : $asset['browser_download_url'];
                            error_log('KantanPro: Found a .zip release asset: ' . $download_url);
                            break;
                        }
                    }
                }

                // zipアセットがなければ、zipball_urlをフォールバックとして使う
                if ( empty($download_url) ) {
                    $download_url = $data['zipball_url'];
                    error_log('KantanPro: No release asset found, falling back to zipball_url.');
                }

                $update_data = array(
                    'version' => $latest_version,
                    'new_version' => $data['tag_name'], // 元のバージョン文字列も保存
                    'download_url' => $download_url,
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

        // KantanPro設置ページでのみ表示（プラグインリストでは表示しない）
        if ( ! $is_ktpwp_page ) {
            return;
        }

        // KantanPro設置ページの場合、通知が無視されているかチェック
        if ( get_option( 'ktpwp_update_notice_dismissed', false ) ) {
            return;
        }
        
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
                'message' => __( 'セキュリティトークンが送信されていません。', 'ktpwp' ),
                'error_type' => 'missing_nonce'
            ) );
            return;
        }
        
        // セキュリティチェック
        if ( ! wp_verify_nonce( $_POST['nonce'], 'ktpwp_dismiss_update_notice' ) ) {
            error_log( 'KantanPro: dismiss_update_notice - nonce検証に失敗しました' );
            wp_send_json_error( array(
                'message' => __( 'セキュリティチェックに失敗しました。', 'ktpwp' ),
                'error_type' => 'security'
            ) );
            return;
        }
        
        if ( ! current_user_can( 'update_plugins' ) ) {
            wp_send_json_error( array(
                'message' => __( 'この操作を実行する権限がありません。', 'ktpwp' ),
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
            'message' => __( '更新通知を無視しました。', 'ktpwp' )
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

        if ( ! $this->is_ktpwp_shortcode_page() ) {
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

        $this->maybe_run_scheduled_update_check();

        $update_data = get_option( 'ktpwp_update_available', false );
        if ( $this->is_update_newer_than_installed( $update_data ) ) {
            $this->show_frontend_update_notice();
        }
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
        if ( ! $this->is_update_newer_than_installed( $update_data ) ) {
            $this->maybe_clear_stale_update_available();
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
            " onclick="ktpwpDismissFrontendNotice()" title="<?php echo esc_attr__( '非表示にする', 'ktpwp' ); ?>">&times;</button>
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
                'message' => __( 'セキュリティトークンが送信されていません。', 'ktpwp' ),
                'error_type' => 'missing_nonce'
            ) );
            return;
        }
        
        // セキュリティチェック
        if ( ! wp_verify_nonce( $_POST['nonce'], 'ktpwp_dismiss_frontend_update_notice' ) ) {
            error_log( 'KantanPro: dismiss_frontend_update_notice - nonce検証に失敗しました' );
            wp_send_json_error( array(
                'message' => __( 'セキュリティチェックに失敗しました。', 'ktpwp' ),
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
            'message' => __( 'フロントエンド更新通知を無視しました。', 'ktpwp' )
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
                'message' => __( 'セキュリティトークンが送信されていません。', 'ktpwp' ),
                'error_type' => 'missing_nonce'
            ) );
            return;
        }
        
        // セキュリティチェック
        if ( ! wp_verify_nonce( $_POST['nonce'], 'ktpwp_perform_update' ) ) {
            error_log( 'KantanPro: perform_plugin_update - nonce検証に失敗しました' );
            wp_send_json_error( array(
                'message' => __( 'セキュリティチェックに失敗しました。', 'ktpwp' ),
                'error_type' => 'security'
            ) );
            return;
        }
        
        if ( ! current_user_can( 'update_plugins' ) ) {
            wp_send_json_error( array(
                'message' => __( 'この操作を実行する権限がありません。', 'ktpwp' ),
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
                'message' => __( '更新情報が見つかりません。', 'ktpwp' ),
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
                'message' => __( '更新情報の形式が正しくありません。', 'ktpwp' ),
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
                'message' => __( '更新情報が見つかりません。', 'ktpwp' ),
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
                'message' => __( '更新が完了しました。', 'ktpwp' )
            ) );
        } else {
            wp_send_json_error( array(
                'message' => __( '更新に失敗しました。', 'ktpwp' ),
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
        $added_download_filter = false;
        if ( $this->is_github_url( $download_url ) ) {
            add_filter( 'http_request_args', array( $this, 'github_download_args' ), 10, 2 );
            $added_download_filter = true;
        }

        $temp_file = download_url( $download_url );
        if ( $added_download_filter ) {
            remove_filter( 'http_request_args', array( $this, 'github_download_args' ), 10 );
        }

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

        // バックアップからサービス画像（images/upload/）を復元
        $backup_upload_dir = $backup_dir . '/images/upload';
        $plugin_upload_dir = $plugin_dir . '/images/upload';
        if ( is_dir( $backup_upload_dir ) ) {
            if ( ! is_dir( $plugin_upload_dir ) ) {
                wp_mkdir_p( $plugin_upload_dir );
            }
            $this->merge_upload_directory( $backup_upload_dir, $plugin_upload_dir, true );
        }

        if ( class_exists( 'KTPWP_Service_Image_Storage' ) ) {
            KTPWP_Service_Image_Storage::migrate_legacy_images();
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
     * サービス画像アップロードディレクトリをマージする。
     *
     * @param string $source_dir コピー元ディレクトリ。
     * @param string $target_dir コピー先ディレクトリ。
     * @param bool   $overwrite  true のとき既存ファイルを上書きする（更新復元時）。
     * @return void
     */
    private function merge_upload_directory( $source_dir, $target_dir, $overwrite = false ) {
        if ( ! is_dir( $source_dir ) || ! is_dir( $target_dir ) ) {
            return;
        }

        $files = scandir( $source_dir );
        if ( ! is_array( $files ) ) {
            return;
        }

        foreach ( $files as $file ) {
            if ( $file === '.' || $file === '..' ) {
                continue;
            }

            $source_file = $source_dir . '/' . $file;
            $target_file = $target_dir . '/' . $file;

            if ( ! is_file( $source_file ) ) {
                continue;
            }

            if ( $overwrite || ! file_exists( $target_file ) ) {
                @copy( $source_file, $target_file );
            }
        }
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
     * 保存済み更新情報が、インストール版より新しいか
     *
     * @param mixed $update_data ktpwp_update_available の値。
     * @return bool
     */
    private function is_update_newer_than_installed( $update_data ) {
        if ( ! is_array( $update_data ) || ! empty( $update_data['no_update'] ) ) {
            return false;
        }

        $latest_version = '';
        if ( isset( $update_data['new_version'] ) ) {
            $latest_version = $this->clean_version( $update_data['new_version'] );
        } elseif ( isset( $update_data['version'] ) ) {
            $latest_version = $this->clean_version( $update_data['version'] );
        }

        if ( $latest_version === '' ) {
            return false;
        }

        return version_compare( $latest_version, $this->clean_version( $this->current_version ), '>' );
    }

    /**
     * 手動更新後などで残った「更新あり」キャッシュを最新版として正規化する
     */
    private function maybe_clear_stale_update_available() {
        $update_data = get_option( 'ktpwp_update_available', false );
        if ( ! is_array( $update_data ) || ! empty( $update_data['no_update'] ) ) {
            return;
        }

        if ( $this->is_update_newer_than_installed( $update_data ) ) {
            return;
        }

        $current_clean = $this->clean_version( $this->current_version );
        update_option(
            'ktpwp_update_available',
            array(
                'no_update'   => true,
                'version'     => $current_clean,
                'new_version' => $this->current_version,
                'checked_at'  => time(),
            )
        );
        delete_option( 'ktpwp_header_update_notice_dismissed' );
        delete_option( 'ktpwp_frontend_update_notice_dismissed' );
        delete_option( 'ktpwp_header_dismissed_version' );
        delete_option( 'ktpwp_frontend_dismissed_version' );
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
     * KantanPro 設置ページ（ショートコード設置ページ）かどうか
     *
     * @return bool
     */
    private function is_ktpwp_shortcode_page() {
        global $post;

        if ( ! is_a( $post, 'WP_Post' ) ) {
            return false;
        }

        $shortcodes = array( 'kantanAllTab', 'ktpwp_all_tab', 'kantanpro_ex' );
        foreach ( $shortcodes as $shortcode ) {
            if ( has_shortcode( $post->post_content, $shortcode ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * スケジュールに従って GitHub 更新チェックを実行（同一リクエスト内は1回のみ）
     *
     * @param bool $force 間隔制限を無視してチェックする
     * @return bool 更新が利用可能か
     */
    public function maybe_run_scheduled_update_check( $force = false ) {
        if ( ! $this->is_update_notification_enabled() ) {
            return false;
        }

        if ( ! is_user_logged_in() || ! $this->user_has_notification_permission() ) {
            return false;
        }

        static $ran = false;
        if ( $ran && ! $force ) {
            return (bool) $this->has_header_update_badge();
        }

        if ( ! $force ) {
            $last_check = get_transient( 'ktpwp_last_update_check' );
            if ( $last_check && ( time() - (int) $last_check ) < $this->check_interval ) {
                $ran = true;
                return (bool) $this->has_header_update_badge();
            }
        }

        $this->check_github_updates();
        $ran = true;

        return (bool) $this->has_header_update_badge();
    }

    /**
     * フロントエンド KantanPro ページ表示時の更新チェック
     */
    public function maybe_check_on_ktpwp_page() {
        if ( ! $this->is_ktpwp_shortcode_page() ) {
            return;
        }

        $this->maybe_run_scheduled_update_check();
    }

    /**
     * バッジ用の軽量 AJAX ポーリング
     */
    public function ajax_poll_header_update() {
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'ktpwp_header_update_check' ) ) {
            wp_send_json_error( array(
                'message' => __( 'セキュリティチェックに失敗しました。', 'ktpwp' ),
            ) );
        }

        if ( ! $this->user_has_notification_permission() ) {
            wp_send_json_error( array(
                'message' => __( 'この操作を実行する権限がありません。', 'ktpwp' ),
            ) );
        }

        if ( ! $this->is_update_notification_enabled() ) {
            wp_send_json_success( array( 'has_update' => false ) );
        }

        $this->maybe_run_badge_update_check( true );

        wp_send_json_success( array(
            'has_update' => $this->has_header_update_badge(),
        ) );
    }

    /**
     * ヘッダーバッジ用の更新チェック（既定1時間間隔・ポーリング時は強制実行可）
     *
     * @param bool $force 間隔制限を無視してチェックする
     * @return bool 更新が利用可能か
     */
    public function maybe_run_badge_update_check( $force = false ) {
        if ( ! $this->is_update_notification_enabled() ) {
            return false;
        }

        if ( ! is_user_logged_in() || ! $this->user_has_notification_permission() ) {
            return false;
        }

        if ( ! $force ) {
            $last_badge_check = get_transient( 'ktpwp_last_badge_update_check' );
            if ( $last_badge_check && ( time() - (int) $last_badge_check ) < $this->badge_check_interval ) {
                return (bool) $this->has_header_update_badge();
            }
        }

        $this->check_github_updates();
        set_transient( 'ktpwp_last_badge_update_check', time(), DAY_IN_SECONDS );

        return (bool) $this->has_header_update_badge();
    }

    /**
     * フロントヘッダー更新バッジ用のアセットをフッターへ登録（jQuery 依存を保証）
     *
     * @param bool       $show_update_badge     バッジ表示可否
     * @param array|null $update_data           吹き出し用の更新データ
     * @param bool       $notifications_enabled 更新通知の有効状態
     */
    public function enqueue_header_update_balloon_assets( $show_update_badge, $update_data, $notifications_enabled ) {
        $script_handle = 'ktpwp-update-balloon';
        $script_loaded = wp_script_is( $script_handle, 'enqueued' ) || wp_script_is( $script_handle, 'done' );

        if ( ! wp_style_is( 'ktpwp-update-balloon', 'enqueued' ) && ! wp_style_is( 'ktpwp-update-balloon', 'done' ) ) {
            wp_enqueue_style(
                'ktpwp-update-balloon',
                KANTANPRO_PLUGIN_URL . 'css/ktpwp-update-balloon.css',
                array(),
                $this->current_version
            );
        }

        wp_enqueue_script( 'jquery' );

        if ( ! $script_loaded ) {
            wp_enqueue_script(
                $script_handle,
                KANTANPRO_PLUGIN_URL . 'js/ktpwp-update-balloon.js',
                array( 'jquery' ),
                $this->current_version,
                true
            );
        }

        wp_localize_script(
            'ktpwp-update-balloon',
            'ktpwp_update_ajax',
            array(
                'ajax_url'              => admin_url( 'admin-ajax.php' ),
                'nonce'                 => wp_create_nonce( 'ktpwp_header_update_check' ),
                'dismiss_nonce'         => wp_create_nonce( 'ktpwp_header_update_notice' ),
                'admin_url'             => admin_url(),
                'notifications_enabled' => (bool) $notifications_enabled,
                'badge_poll_interval'   => $this->badge_check_interval * 1000,
            )
        );

        if ( is_array( $update_data ) && ! empty( $update_data ) ) {
            wp_localize_script(
                'ktpwp-update-balloon',
                'ktpwp_update_data',
                array(
                    'has_update'  => true,
                    'message'     => __( '新しいバージョンが利用可能です！', 'ktpwp' ),
                    'update_data' => $update_data,
                )
            );
        }

        wp_add_inline_script(
            'ktpwp-update-balloon',
            'var ktpwp_update_badge_available = ' . wp_json_encode( (bool) $show_update_badge ) . ';',
            'before'
        );
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
        if ( ! $update_data || ! is_array( $update_data ) ) {
            return false;
        }

        if ( ! $this->is_update_newer_than_installed( $update_data ) ) {
            $this->maybe_clear_stale_update_available();
            return false;
        }
        
        // 通知が無視されているかチェック
        if ( get_option( 'ktpwp_header_update_notice_dismissed', false ) ) {
            return false;
        }
        
        // 過去に無視されたバージョンと同じ場合は表示しない
        $dismissed_version = get_option( 'ktpwp_header_dismissed_version', '' );
        $stored_version    = isset( $update_data['new_version'] ) ? (string) $update_data['new_version'] : (string) ( $update_data['version'] ?? '' );
        if ( $dismissed_version !== '' && $dismissed_version === $stored_version ) {
            return false;
        }
        
        return $update_data;
    }

    /**
     * ヘッダー更新ボタンにバッジを表示するか（「後で」で吹き出しを閉じてもバッジは残す）
     */
    public function has_header_update_badge() {
        if ( ! $this->is_update_notification_enabled() ) {
            return false;
        }

        if ( ! is_user_logged_in() || ! $this->user_has_notification_permission() ) {
            return false;
        }

        $update_data = get_option( 'ktpwp_update_available', false );
        if ( ! $this->is_update_newer_than_installed( $update_data ) ) {
            $this->maybe_clear_stale_update_available();
            return false;
        }

        return true;
    }

    /**
     * ヘッダー更新通知の無視
     */
    public function dismiss_header_update_notice() {
        // POSTデータの存在チェック
        if ( ! isset( $_POST['nonce'] ) ) {
            error_log( 'KantanPro: dismiss_header_update_notice - nonceが送信されていません' );
            wp_send_json_error( array(
                'message' => __( 'セキュリティトークンが送信されていません。', 'ktpwp' ),
                'error_type' => 'missing_nonce'
            ) );
            return;
        }
        
        // セキュリティチェック
        if ( ! wp_verify_nonce( $_POST['nonce'], 'ktpwp_header_update_notice' ) ) {
            error_log( 'KantanPro: dismiss_header_update_notice - nonce検証に失敗しました' );
            wp_send_json_error( array(
                'message' => __( 'セキュリティチェックに失敗しました。', 'ktpwp' ),
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
            'message' => __( '更新通知を無視しました。', 'ktpwp' )
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
                    'message' => __( 'セキュリティトークンが送信されていません。', 'ktpwp' ),
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
                    'message' => __( 'セキュリティチェックに失敗しました。', 'ktpwp' ),
                    'error_type' => 'security'
                ) );
                return;
            }
            error_log( 'KantanPro: nonce検証成功' );
            
            error_log( 'KantanPro: 権限チェック開始' );
            if ( ! $this->user_has_notification_permission() ) {
                error_log( 'KantanPro: 権限チェック失敗' );
                wp_send_json_error( array(
                    'message' => __( 'この操作を実行する権限がありません。', 'ktpwp' ),
                    'error_type' => 'permission'
                ) );
                return;
            }
            error_log( 'KantanPro: 権限チェック成功' );
            
            // 更新通知が無効の場合は即座に返す
            if ( ! $this->is_update_notification_enabled() ) {
                wp_send_json_success( array(
                    'message' => __( '更新通知が無効化されています。', 'ktpwp' ),
                    'has_update' => false,
                    'notifications_disabled' => true
                ) );
                return;
            }
            
            // 更新チェック実行
            error_log( 'KantanPro: 更新チェック実行開始' );
            $this->clear_plugin_cache_for_update_check();
            $this->check_github_updates();
            $status = $this->resolve_header_update_status();
            error_log( 'KantanPro: 更新チェック結果 has_update=' . ( $status['has_update'] ? 'true' : 'false' ) );
            error_log( 'KantanPro: 保存された更新データ: ' . print_r( get_option( 'ktpwp_update_available', false ), true ) );
            
            if ( $status['has_update'] ) {
                error_log( 'KantanPro: 更新あり - 更新データ: ' . print_r( $status['update_data'], true ) );
                wp_send_json_success( array(
                    'message' => __( '新しいバージョンが利用可能です！', 'ktpwp' ),
                    'has_update' => true,
                    'update_data' => $status['update_data']
                ) );
            } else {
                error_log( 'KantanPro: 更新なし' );
                wp_send_json_success( array(
                    'message' => __( '最新バージョンです。', 'ktpwp' ),
                    'has_update' => false
                ) );
            }
            
        } catch ( Exception $e ) {
            error_log( 'KantanPro: AJAX更新チェックで例外が発生: ' . $e->getMessage() );
            wp_send_json_error( array(
                'message' => __( '更新チェック中にエラーが発生しました: ', 'ktpwp' ) . $e->getMessage(),
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
     * 手動更新チェック用の軽量キャッシュクリア（保存済みの更新情報は維持）
     *
     * GitHub 再取得に失敗した場合でも、バッジ用の ktpwp_update_available を先に消さない。
     */
    public function clear_plugin_cache_for_update_check() {
        wp_clean_plugins_cache();
        delete_site_transient( 'update_plugins' );
        delete_transient( 'update_plugins' );
        delete_transient( 'ktpwp_last_update_check' );
        delete_transient( 'ktpwp_last_force_check' );
        error_log( 'KantanPro: 更新チェック用キャッシュをクリアしました（更新情報オプションは維持）' );
    }

    /**
     * ヘッダー更新チェック後のレスポンス用データ
     *
     * @return array{ has_update: bool, update_data: array|false }
     */
    private function resolve_header_update_status() {
        $update_data = get_option( 'ktpwp_update_available', false );
        $has_update  = $this->has_header_update_badge();

        if ( $has_update && is_array( $update_data ) ) {
            return array(
                'has_update'  => true,
                'update_data' => $update_data,
            );
        }

        return array(
            'has_update'  => false,
            'update_data' => false,
        );
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
            $this->maybe_clear_stale_update_available();
            if ( is_admin() && $old_version !== '0' ) {
                set_transient( $this->key( 'admin_reload' ), 1, 5 * MINUTE_IN_SECONDS );
            }
            
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
                'message' => __( '権限がありません。', 'ktpwp' ),
                'error_type' => 'permission_denied'
            ) );
            return;
        }
        
        // ナンスチェック
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'ktpwp_update_checker' ) ) {
            wp_send_json_error( array(
                'message' => __( 'セキュリティチェックに失敗しました。', 'ktpwp' ),
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
                'message' => __( 'キャッシュが正常にクリアされました。更新チェックを再実行してください。', 'ktpwp' ),
                'debug_info' => $debug_info
            ) );
            
        } catch ( Exception $e ) {
            wp_send_json_error( array(
                'message' => __( 'キャッシュクリアに失敗しました: ', 'ktpwp' ) . $e->getMessage(),
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
     * WordPressの更新トランジェントにプラグインの更新情報を挿入する
     *
     * @param object $transient 更新トランジェント
     * @return object 更新されたトランジェント
     */
    public function check_for_plugin_update( $transient ) {
        if ( ! is_admin() && ! ( defined( 'DOING_CRON' ) && DOING_CRON ) ) {
            return $transient;
        }

        if ( ! is_object( $transient ) ) {
            $transient = new stdClass();
        }

        if ( ! isset( $transient->checked ) || ! is_array( $transient->checked ) ) {
            $transient->checked = array();
        }

        $transient->checked[ $this->plugin_basename ] = $this->current_version;

        // 一時展開ディレクトリ由来の古いキーを除去し、functions.phpの file_get_contents 警告を回避する
        foreach ( array( 'checked', 'response', 'no_update' ) as $bucket ) {
            if ( ! isset( $transient->{$bucket} ) || ! is_array( $transient->{$bucket} ) ) {
                continue;
            }

            foreach ( array_keys( $transient->{$bucket} ) as $basename ) {
                if ( ! $this->is_target_plugin_basename( $basename ) ) {
                    continue;
                }
                if ( $basename === $this->plugin_basename ) {
                    continue;
                }

                unset( $transient->{$bucket}[ $basename ] );
            }
        }

        // キャッシュされた更新情報を取得
        $update_data = get_option( 'ktpwp_update_available' );

        // このフックは頻繁に実行されるため、毎回APIを叩かないように
        // check_github_updates() は内部で実行間隔をチェックしているので安全に呼び出せる
        if ( ! $update_data ) {
            $update_data = $this->check_github_updates();
        }
        
        if ( $update_data && is_array( $update_data ) && isset( $update_data['version'] ) ) {
            $latest_version = $this->clean_version( $update_data['version'] );
            $current_version = $this->clean_version( $this->current_version );

            if ( version_compare( $latest_version, $current_version, '>' ) ) {
                $plugin_info = new stdClass();
                $plugin_info->slug = $this->plugin_slug;
                $plugin_info->plugin = $this->plugin_basename;
                // 表示用のバージョン文字列を優先的に使用
                $plugin_info->new_version = isset( $update_data['new_version'] ) ? $update_data['new_version'] : $update_data['version'];
                $plugin_info->url = 'https://github.com/' . $this->github_repo;
                $plugin_info->package = $update_data['download_url'];
                $plugin_info->tested = get_bloginfo( 'version' ); // 現在のWPバージョンをセット
                $plugin_info->requires = '5.0'; // 必要なWordPressバージョン
                $plugin_info->requires_php = '7.4'; // 必要なPHPバージョン
                $plugin_info->last_updated = isset( $update_data['published_at'] ) ? $update_data['published_at'] : '';
                $banner_url = function_exists( 'ktpwp_plugin_asset_url' )
                    ? ktpwp_plugin_asset_url( 'images/default/header_bg_image.png' )
                    : KANTANPRO_PLUGIN_URL . 'images/default/header_bg_image.png';
                $icon_url = function_exists( 'ktpwp_plugin_asset_url' )
                    ? ktpwp_plugin_asset_url( 'images/default/icon.png' )
                    : KANTANPRO_PLUGIN_URL . 'images/default/icon.png';
                $dummy_url = function_exists( 'ktpwp_plugin_asset_url' )
                    ? ktpwp_plugin_asset_url( 'images/default/dummy_graph.png' )
                    : KANTANPRO_PLUGIN_URL . 'images/default/dummy_graph.png';

                $plugin_info->icons = array(
                    'default' => $icon_url,
                );
                
                // バナー画像を設定
                $plugin_info->banners = array(
                    'high' => $banner_url,
                    'low' => $banner_url,
                );
                
                // スクリーンショットを設定
                $plugin_info->screenshots = array(
                    array(
                        'src' => $dummy_url,
                        'caption' => 'KantanPro ダッシュボード'
                    )
                );

                // プラグイン詳細情報のセクションを設定
                $plugin_info->sections = array();
                
                // 現在のプラグイン情報を取得
                $current_plugin_data = get_plugin_data( KANTANPRO_PLUGIN_FILE );
                
                // 説明セクション
                $plugin_info->sections['description'] = isset( $current_plugin_data['Description'] ) ? $current_plugin_data['Description'] : __( 'スモールビジネスのための販売支援ツール', 'ktpwp' );
                
                // 変更履歴セクション
                if ( isset( $update_data['changelog'] ) && ! empty( $update_data['changelog'] ) ) {
                    $plugin_info->sections['changelog'] = $update_data['changelog'];
                } else {
                    $plugin_info->sections['changelog'] = __( '詳細な変更履歴については、GitHubリポジトリをご確認ください。', 'ktpwp' );
                }
                
                // インストールセクション
                $plugin_info->sections['installation'] = __( 'プラグインをアップロードして有効化してください。ショートコード[ktpwp_all_tab]を固定ページに設置することで、システムが利用可能になります。', 'ktpwp' );
                
                // よくある質問セクション
                $plugin_info->sections['faq'] = __( 'よくある質問については、プラグインのドキュメントをご確認ください。', 'ktpwp' );
                
                // プラグインの基本情報を設定
                $plugin_info->name = isset( $current_plugin_data['Name'] ) ? $current_plugin_data['Name'] : 'ktpwp';
                $plugin_info->author = isset( $current_plugin_data['Author'] ) ? $current_plugin_data['Author'] : 'ktpwp';
                $plugin_info->author_homepage = isset( $current_plugin_data['AuthorURI'] ) ? $current_plugin_data['AuthorURI'] : 'https://www.kantanpro.com/kantanpro-page';
                $plugin_info->homepage = isset( $current_plugin_data['PluginURI'] ) ? $current_plugin_data['PluginURI'] : 'https://www.kantanpro.com/';

                if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
                    $transient->response = array();
                }
                $transient->response[ $this->plugin_basename ] = $plugin_info;
                if ( isset( $transient->no_update[ $this->plugin_basename ] ) ) {
                    unset( $transient->no_update[ $this->plugin_basename ] );
                }
            } else {
                if ( ! isset( $transient->no_update ) || ! is_array( $transient->no_update ) ) {
                    $transient->no_update = array();
                }
                $transient->no_update[ $this->plugin_basename ] = (object) array(
                    'id' => $this->plugin_slug,
                    'slug' => $this->plugin_slug,
                    'plugin' => $this->plugin_basename,
                    'new_version' => $this->current_version,
                    'url' => 'https://github.com/' . $this->github_repo,
                    'package' => '',
                );
                if ( isset( $transient->response[ $this->plugin_basename ] ) ) {
                    unset( $transient->response[ $this->plugin_basename ] );
                }
            }
        }

        return $transient;
    }

    /**
     * GitHubからのダウンロード時に認証ヘッダーを付与する
     *
     * @param mixed $reply ダウンロード前の応答
     * @param string $package パッケージURL
     * @param object $upgrader アップグレーダー
     * @return mixed
     */
    public function upgrader_pre_download( $reply, $package, $upgrader ) {
        if ( $this->is_github_url( $package ) ) {
            add_filter( 'http_request_args', array( $this, 'github_download_args' ), 10, 2 );
        }

        return $reply;
    }

    /**
     * GitHubダウンロード用のHTTPリクエスト引数を調整する
     *
     * @param array $args HTTPリクエスト引数
     * @param string $url URL
     * @return array
     */
    public function github_download_args( $args, $url ) {
        if ( $this->is_github_url( $url ) ) {
            $args['timeout'] = 60;
            $args['headers'] = isset( $args['headers'] ) && is_array( $args['headers'] ) ? $args['headers'] : array();
            $args['headers'] = array_merge( $args['headers'], $this->get_github_download_headers( $url ) );
        }

        return $args;
    }

    /**
     * 更新前に有効化状態を保存し、更新中の競合を避ける
     *
     * @param mixed $response 応答
     * @param array $hook_extra フック情報
     * @param mixed $result 結果
     * @return mixed
     */
    public function before_update( $response, $hook_extra, $result = null ) {
        $target_basename = $this->resolve_installed_basename( $this->resolve_target_basename( $hook_extra ) );
        if ( ! $this->is_target_plugin_basename( $target_basename ) ) {
            return $response;
        }

        if ( ! function_exists( 'is_plugin_active' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $was_network_active = is_multisite() && is_plugin_active_for_network( $target_basename );
        $was_active = is_plugin_active( $target_basename ) || $was_network_active;

        set_site_transient(
            $this->key( 'pre_update_state' ),
            array(
                'was_active' => $was_active,
                'network_active' => $was_network_active,
                'basename' => $target_basename,
            ),
            30 * MINUTE_IN_SECONDS
        );

        $this->backup_service_images_before_update();

        if ( class_exists( 'KTPWP_Service_Image_Storage' ) ) {
            KTPWP_Service_Image_Storage::migrate_legacy_images();
        }

        return $response;
    }

    /**
     * GitHub zipball 展開直後にトップフォルダ名をプラグインディレクトリへ揃える
     *
     * WP は保護ディレクトリ直下へのインストール時に basename( $source ) をそのまま
     * プラグインフォルダ名に使うため、KantanPro-KantanProEx-<hash> のままだと
     * active_plugins の KantanProEX/ktpwp.php と不一致になる。post_install より前に補正する。
     *
     * @param string|WP_Error $source        選択されたソースパス。
     * @param string          $remote_source 解凍ルート。
     * @param WP_Upgrader     $upgrader      アップグレーダー。
     * @param array           $hook_extra    フック情報。
     * @return string|WP_Error
     */
    public function normalize_github_zipball_source( $source, $remote_source, $upgrader, $hook_extra ) {
        if ( is_wp_error( $source ) || ! is_string( $source ) || '' === trim( $source ) ) {
            return $source;
        }

        if ( empty( $hook_extra['type'] ) || 'plugin' !== $hook_extra['type'] ) {
            return $source;
        }

        $explicit = isset( $hook_extra['plugin'] ) ? (string) $hook_extra['plugin'] : '';
        if ( $explicit !== '' && ! $this->is_target_plugin_basename( $explicit ) ) {
            return $source;
        }

        $candidate = trailingslashit( $source );
        if ( ! is_dir( $candidate ) || ! file_exists( $candidate . 'ktpwp.php' ) ) {
            return $source;
        }

        if ( ! function_exists( 'get_plugin_data' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugin_data = get_plugin_data( $candidate . 'ktpwp.php', false, false );
        $name        = isset( $plugin_data['Name'] ) ? (string) $plugin_data['Name'] : '';
        if ( stripos( $name, 'KantanProEX' ) === false ) {
            return $source;
        }

        $slug = $this->plugin_slug;
        $src  = untrailingslashit( $source );
        if ( strtolower( basename( $src ) ) === strtolower( $slug ) ) {
            return $source;
        }

        $parent = dirname( $src );
        $dest   = $parent . '/' . $slug;

        if ( is_dir( $dest ) ) {
            $this->recursive_rmdir( trailingslashit( $dest ) );
        }

        if ( @rename( $src, $dest ) ) {
            return trailingslashit( $dest );
        }

        return $source;
    }

    /**
     * GitHub zipball展開時のディレクトリ名をプラグインslugに補正する
     *
     * @param mixed $response 応答
     * @param array $hook_extra フック情報
     * @param array $result インストール結果
     * @return mixed
     */
    public function rename_github_source( $response, $hook_extra, $result ) {
        $target_basename = $this->resolve_installed_basename( $this->resolve_target_basename( $hook_extra ) );
        if ( ! $this->is_target_plugin_basename( $target_basename ) ) {
            return $response;
        }

        if ( empty( $result ) || ! is_array( $result ) || empty( $result['destination'] ) ) {
            return $response;
        }

        $expected_dir = trailingslashit( WP_PLUGIN_DIR ) . $this->plugin_slug . '/';
        if ( untrailingslashit( $result['destination'] ) === untrailingslashit( $expected_dir ) ) {
            return $response;
        }

        $installed_dir = '';
        foreach ( array( 'destination', 'remote_destination', 'local_destination', 'source' ) as $key ) {
            if ( empty( $result[ $key ] ) || ! is_string( $result[ $key ] ) ) {
                continue;
            }
            $dir = trailingslashit( $result[ $key ] );
            if ( is_dir( $dir ) && file_exists( $dir . 'ktpwp.php' ) ) {
                $installed_dir = $dir;
                break;
            }
        }

        if ( $installed_dir === '' ) {
            return $response;
        }

        if ( untrailingslashit( $installed_dir ) === untrailingslashit( $expected_dir ) ) {
            return $response;
        }

        if ( is_dir( $expected_dir ) ) {
            $this->recursive_rmdir( $expected_dir );
        }

        if ( @rename( untrailingslashit( $installed_dir ), untrailingslashit( $expected_dir ) ) ) {
            $result['destination'] = $expected_dir;
            if ( isset( $result['remote_destination'] ) ) {
                $result['remote_destination'] = $expected_dir;
            }
            return $result;
        }

        return $response;
    }

    /**
     * 更新後にキャッシュをクリアする
     *
     * @param mixed $response 応答
     * @param array $hook_extra フック情報
     * @param array $result インストール結果
     * @return mixed
     */
    public function after_update( $response, $hook_extra, $result ) {
        $target_basename = $this->resolve_target_basename( $hook_extra );
        if ( $this->is_target_plugin_basename( $target_basename ) ) {
            $this->restore_service_images_after_update();

            if ( class_exists( 'KTPWP_Service_Image_Storage' ) ) {
                KTPWP_Service_Image_Storage::migrate_legacy_images();
            }

            remove_filter( 'http_request_args', array( $this, 'github_download_args' ), 10 );
            delete_option( 'ktpwp_update_available' );
            delete_option( 'ktpwp_latest_version' );
            delete_transient( 'ktpwp_last_update_check' );
            delete_site_transient( 'update_plugins' );
            delete_site_transient( 'update_plugins_checked' );
            wp_clean_plugins_cache();

            if ( function_exists( 'wp_cache_flush' ) ) {
                wp_cache_flush();
            }
        }

        return $response;
    }

    /**
     * プラグイン更新前にサービス画像を wp-content/uploads へ退避する。
     *
     * WordPress 標準のプラグイン更新は配布 ZIP にユーザー画像が含まれないため、
     * 更新前に退避しないと images/upload/ が消える。
     *
     * @return void
     */
    private function backup_service_images_before_update() {
        $plugin_upload_dir = trailingslashit( WP_PLUGIN_DIR ) . $this->plugin_slug . '/images/upload';
        if ( ! is_dir( $plugin_upload_dir ) ) {
            return;
        }

        $upload_info = wp_upload_dir();
        if ( ! empty( $upload_info['error'] ) ) {
            return;
        }

        $backup_dir = trailingslashit( $upload_info['basedir'] ) . 'ktpwp-service-images-backup';
        if ( is_dir( $backup_dir ) ) {
            $this->recursive_rmdir( $backup_dir );
        }

        if ( ! wp_mkdir_p( $backup_dir ) ) {
            return;
        }

        $this->merge_upload_directory( $plugin_upload_dir, $backup_dir );
        set_site_transient( $this->key( 'upload_backup_path' ), $backup_dir, 30 * MINUTE_IN_SECONDS );
    }

    /**
     * プラグイン更新後に退避したサービス画像を復元する。
     *
     * @return void
     */
    private function restore_service_images_after_update() {
        $backup_dir = get_site_transient( $this->key( 'upload_backup_path' ) );
        delete_site_transient( $this->key( 'upload_backup_path' ) );

        if ( ! is_string( $backup_dir ) || $backup_dir === '' || ! is_dir( $backup_dir ) ) {
            return;
        }

        $plugin_upload_dir = trailingslashit( WP_PLUGIN_DIR ) . $this->plugin_slug . '/images/upload';
        if ( ! is_dir( $plugin_upload_dir ) ) {
            wp_mkdir_p( $plugin_upload_dir );
        }

        if ( is_dir( $plugin_upload_dir ) ) {
            $this->merge_upload_directory( $backup_dir, $plugin_upload_dir, true );
        }

        $this->recursive_rmdir( $backup_dir );
    }

    /**
     * 更新後に元の有効化状態へ戻す
     *
     * @param object $upgrader_object アップグレーダー
     * @param array $options 更新オプション
     * @return void
     */
    public function handle_auto_activation( $upgrader_object, $options ) {
        if ( empty( $options['action'] ) || empty( $options['type'] ) ) {
            return;
        }

        if ( $options['action'] !== 'update' || $options['type'] !== 'plugin' ) {
            return;
        }

        $target_basename = $this->resolve_installed_basename( $this->resolve_target_basename( $options ) );
        if ( ! $this->is_target_plugin_basename( $target_basename ) ) {
            return;
        }

        if ( ! function_exists( 'is_plugin_active' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $pre_update_state = get_site_transient( $this->key( 'pre_update_state' ) );
        $activation_basename = ! empty( $pre_update_state['basename'] ) ? $pre_update_state['basename'] : $target_basename;
        $activation_basename = $this->resolve_installed_basename( $activation_basename );
        if ( ! $this->is_target_plugin_basename( $activation_basename ) ) {
            $activation_basename = $target_basename;
        }

        $should_reactivate = $pre_update_state && ! empty( $pre_update_state['was_active'] );
        $network_active    = $should_reactivate && ! empty( $pre_update_state['network_active'] );

        if ( $should_reactivate ) {
            $this->restore_active_plugin_record( $activation_basename, $network_active );
        }

        if ( $should_reactivate && ! is_plugin_active( $activation_basename ) ) {
            if ( ! $this->activate_target_plugin( $activation_basename, $network_active ) ) {
                set_site_transient(
                    $this->key( 'pending_activation' ),
                    array(
                        'basename' => $activation_basename,
                        'network_active' => $network_active,
                    ),
                    30 * MINUTE_IN_SECONDS
                );
            } else {
                delete_site_transient( $this->key( 'pending_activation' ) );
            }
        }

        set_transient( $this->key( 'admin_reload' ), 1, 5 * MINUTE_IN_SECONDS );
        delete_site_transient( $this->key( 'pre_update_state' ) );
    }

    /**
     * 更新直後に管理画面を一度だけリロードする
     *
     * @return void
     */
    public function maybe_reload_admin_after_activation() {
        if ( ! is_admin() || ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) || ! current_user_can( 'manage_options' ) ) {
            return;
        }

        if ( ! get_transient( $this->key( 'admin_reload' ) ) ) {
            return;
        }

        if ( ! isset( $_GET['ktpwp_reloaded'] ) ) {
            delete_transient( $this->key( 'admin_reload' ) );
            wp_safe_redirect( admin_url( 'plugins.php?ktpwp_reloaded=1&ktpwp_updated=1' ) );
            exit;
        }

        delete_transient( $this->key( 'admin_reload' ) );
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