<?php
/**
 * KantanPro Hook Manager
 * 
 * フックの最適化とパフォーマンス向上を管理するクラス
 * 
 * @package KantanPro
 * @since 1.1.4
 */

// セキュリティ: 直接アクセスを防止
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * KTPWP_Hook_Manager クラス
 * 
 * 不要なフックを削除し、条件付きでフックを登録することで
 * プラグインのパフォーマンスを向上させます。
 */
class KTPWP_Hook_Manager {

    /**
     * シングルトンインスタンス
     * 
     * @var KTPWP_Hook_Manager
     */
    private static $instance = null;
    
    /**
     * 削除されたフック数
     * 
     * @var int
     */
    private $removed_hooks_count = 0;
    
    /**
     * 最適化されたフック数
     * 
     * @var int
     */
    private $optimized_hooks_count = 0;

    /**
     * シングルトンインスタンスを取得
     * 
     * @return KTPWP_Hook_Manager
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * コンストラクタ
     */
    private function __construct() {
        $this->init_optimization();
    }

    /**
     * フック最適化を初期化
     */
    private function init_optimization() {
        // 管理画面でのみ実行されるべきフックを最適化
        add_action( 'plugins_loaded', array( $this, 'optimize_admin_hooks' ), 1 );
        
        // フロントエンドでのみ実行されるべきフックを最適化
        add_action( 'init', array( $this, 'optimize_frontend_hooks' ), 1 );
        
        // 重複フックを削除
        add_action( 'plugins_loaded', array( $this, 'remove_duplicate_hooks' ), 2 );
        
        // デバッグ時の統計表示
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG && is_admin() ) {
            add_action( 'admin_footer', array( $this, 'display_optimization_stats' ) );
        }
    }

    /**
     * 管理画面専用フックを最適化
     */
    public function optimize_admin_hooks() {
        if ( ! is_admin() ) {
            // フロントエンドでは管理画面専用フックを削除
            $this->remove_admin_only_hooks();
        }
    }

    /**
     * フロントエンド専用フックを最適化
     */
    public function optimize_frontend_hooks() {
        if ( is_admin() ) {
            // 管理画面ではフロントエンド専用フックを削除
            $this->remove_frontend_only_hooks();
        }
    }

    /**
     * 管理画面専用フックを削除（フロントエンド実行時）
     */
    private function remove_admin_only_hooks() {
        // admin_notices関連のフックを削除
        remove_action( 'admin_notices', 'ktpwp_admin_migration_status' );
        remove_action( 'admin_notices', 'ktpwp_admin_notices' );
        $this->removed_hooks_count += 2;
        
        // admin_init関連の重い処理を削除
        remove_action( 'admin_init', 'ktpwp_handle_auto_update_toggle' );
        remove_action( 'admin_init', 'ktpwp_check_terms_agreement' );
        $this->removed_hooks_count += 2;
        
        // admin_enqueue_scripts関連を削除
        remove_action( 'admin_enqueue_scripts', 'ktpwp_enqueue_cache_admin_scripts' );
        $this->removed_hooks_count += 1;
        
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( "KTPWP Hook Manager: Removed {$this->removed_hooks_count} admin-only hooks from frontend" );
        }
    }

    /**
     * フロントエンド専用フックを削除（管理画面実行時）
     */
    private function remove_frontend_only_hooks() {
        // フロントエンド専用のショートコード関連処理を削除
        // （現在は該当するフックが少ないため、将来の拡張に備えて準備）
        
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( "KTPWP Hook Manager: Checked frontend-only hooks in admin" );
        }
    }

    /**
     * 重複フックを削除
     */
    public function remove_duplicate_hooks() {
        global $wp_filter;
        
        // 利用規約チェック関連の重複を削除
        $this->remove_duplicate_terms_check_hooks();
        
        // plugins_loaded関連の重複チェック
        $this->optimize_plugins_loaded_hooks();
        
        // デバッグ関連の不要なフックを削除
        $this->remove_debug_hooks();
    }

    /**
     * 利用規約チェックの重複フックを削除
     */
    private function remove_duplicate_terms_check_hooks() {
        // 'wp'フックでの利用規約チェックを削除（admin_initで十分）
        if ( is_admin() ) {
            remove_action( 'wp', 'ktpwp_check_terms_agreement' );
            $this->removed_hooks_count++;
            
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'KTPWP Hook Manager: Removed duplicate terms check hook from wp action' );
            }
        }
    }

    /**
     * plugins_loadedフックを最適化
     */
    private function optimize_plugins_loaded_hooks() {
        // データベース整合性チェックを条件付きで実行
        if ( ! $this->should_run_database_checks() ) {
            remove_action( 'plugins_loaded', 'ktpwp_check_database_integrity', 5 );
            remove_action( 'plugins_loaded', 'ktpwp_sync_database_version', 6 );
            $this->removed_hooks_count += 2;
            
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'KTPWP Hook Manager: Removed database integrity checks (not needed)' );
            }
        }

        // 自動マイグレーションを条件付きで実行
        if ( ! $this->should_run_migrations() ) {
            remove_action( 'plugins_loaded', 'ktpwp_run_auto_migrations', 8 );
            $this->removed_hooks_count++;
            
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'KTPWP Hook Manager: Removed auto migrations (not needed)' );
            }
        }
    }

    /**
     * デバッグ関連の不要なフックを削除
     */
    private function remove_debug_hooks() {
        // プロダクション環境ではデバッグフックを削除
        if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
            // デバッグ用のREST API制限を削除
            remove_action( 'plugins_loaded', 'ktpwp_disable_rest_api_restriction_during_init', 1 );
            remove_filter( 'rest_authentication_errors', 'ktpwp_allow_internal_requests' );
            $this->removed_hooks_count += 2;
            
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'KTPWP Hook Manager: Removed debug hooks in production' );
            }
        }
    }

    /**
     * データベースチェックが必要かどうかを判断
     * 
     * @return bool
     */
    private function should_run_database_checks() {
        // 以下の場合のみデータベースチェックを実行
        // 1. プラグインが最近更新された場合
        // 2. 管理画面でアクセスされた場合
        // 3. WP-CLIでの実行時
        
        if ( is_admin() ) {
            return true;
        }
        
        if ( defined( 'WP_CLI' ) && WP_CLI ) {
            return true;
        }
        
        // プラグイン更新後24時間以内の場合
        $last_upgrade = get_option( 'ktpwp_upgrade_timestamp' );
        if ( $last_upgrade && ( time() - strtotime( $last_upgrade ) ) < DAY_IN_SECONDS ) {
            return true;
        }
        
        return false;
    }

    /**
     * マイグレーションが必要かどうかを判断
     * 
     * @return bool
     */
    private function should_run_migrations() {
        // 以下の場合のみマイグレーションを実行
        // 1. バージョンが変更された場合
        // 2. 管理画面での初回アクセス時
        
        $stored_version = get_option( 'ktpwp_version', '0' );
        $current_version = KANTANPRO_PLUGIN_VERSION;
        
        if ( $stored_version !== $current_version ) {
            return true;
        }
        
        // 管理画面で特定のページにアクセスした場合
        if ( is_admin() && isset( $_GET['page'] ) && strpos( $_GET['page'], 'ktp-' ) === 0 ) {
            return true;
        }
        
        return false;
    }

    /**
     * 条件付きでフックを追加
     * 
     * @param string $hook_name フック名
     * @param callable $callback コールバック関数
     * @param array $conditions 実行条件
     * @param int $priority 優先度
     * @param int $accepted_args 引数数
     */
    public function add_conditional_action( $hook_name, $callback, $conditions = array(), $priority = 10, $accepted_args = 1 ) {
        if ( $this->check_conditions( $conditions ) ) {
            add_action( $hook_name, $callback, $priority, $accepted_args );
            $this->optimized_hooks_count++;
        }
    }

    /**
     * 条件付きでフィルターを追加
     * 
     * @param string $hook_name フック名
     * @param callable $callback コールバック関数
     * @param array $conditions 実行条件
     * @param int $priority 優先度
     * @param int $accepted_args 引数数
     */
    public function add_conditional_filter( $hook_name, $callback, $conditions = array(), $priority = 10, $accepted_args = 1 ) {
        if ( $this->check_conditions( $conditions ) ) {
            add_filter( $hook_name, $callback, $priority, $accepted_args );
            $this->optimized_hooks_count++;
        }
    }

    /**
     * 条件をチェック
     * 
     * @param array $conditions 条件配列
     * @return bool
     */
    private function check_conditions( $conditions ) {
        if ( empty( $conditions ) ) {
            return true;
        }

        foreach ( $conditions as $condition => $value ) {
            switch ( $condition ) {
                case 'is_admin':
                    if ( is_admin() !== $value ) {
                        return false;
                    }
                    break;
                
                case 'is_frontend':
                    if ( ! is_admin() !== $value ) {
                        return false;
                    }
                    break;
                
                case 'wp_debug':
                    if ( ( defined( 'WP_DEBUG' ) && WP_DEBUG ) !== $value ) {
                        return false;
                    }
                    break;
                
                case 'user_can':
                    if ( ! current_user_can( $value ) ) {
                        return false;
                    }
                    break;
                
                case 'page':
                    if ( ! isset( $_GET['page'] ) || $_GET['page'] !== $value ) {
                        return false;
                    }
                    break;
            }
        }

        return true;
    }

    /**
     * 最適化統計を取得
     * 
     * @return array
     */
    public function get_optimization_stats() {
        return array(
            'removed_hooks' => $this->removed_hooks_count,
            'optimized_hooks' => $this->optimized_hooks_count,
            'total_optimizations' => $this->removed_hooks_count + $this->optimized_hooks_count,
        );
    }

    /**
     * 最適化統計を表示（デバッグ用）
     */
    public function display_optimization_stats() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        
        $stats = $this->get_optimization_stats();
        
        echo '<div style="position: fixed; bottom: 70px; right: 10px; background: #fff; border: 1px solid #ccc; padding: 10px; font-size: 12px; z-index: 9999;">';
        echo '<strong>KTPWP Hook Optimization:</strong><br>';
        echo "Removed Hooks: {$stats['removed_hooks']}<br>";
        echo "Optimized Hooks: {$stats['optimized_hooks']}<br>";
        echo "Total Optimizations: {$stats['total_optimizations']}";
        echo '</div>';
    }

    /**
     * すべてのフック最適化をリセット（デバッグ用）
     */
    public function reset_optimization() {
        $this->removed_hooks_count = 0;
        $this->optimized_hooks_count = 0;
        
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP Hook Manager: Optimization stats reset' );
        }
    }
}
