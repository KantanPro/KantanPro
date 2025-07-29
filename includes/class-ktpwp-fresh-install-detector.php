<?php
/**
 * Fresh Install Detector Class
 *
 * 新規インストール判定とローカル環境基本構造保護を管理
 *
 * @package KTPWP
 * @since 1.2.9
 */

// セキュリティ: 直接アクセスを防止
if (!defined('ABSPATH')) {
    exit;
}

/**
 * KTPWP_Fresh_Install_Detector クラス
 * 
 * 新規インストール時の判定と基本構造保護
 */
class KTPWP_Fresh_Install_Detector {
    
    /**
     * シングルトンインスタンス
     */
    private static $instance = null;

    /**
     * 新規インストール判定フラグ保存キー
     */
    private $fresh_install_key = 'ktpwp_is_fresh_install';

    /**
     * 初期化完了フラグ保存キー
     */
    private $init_completed_key = 'ktpwp_fresh_install_init_completed';

    /**
     * シングルトンインスタンスを取得
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 新規インストールかどうかを判定
     */
    public function is_fresh_install() {
        // 既に判定済みの場合はその結果を返す
        $cached_result = get_option($this->fresh_install_key, null);
        if ($cached_result !== null) {
            return ($cached_result === 'yes');
        }

        // 判定ロジック実行
        $is_fresh = $this->detect_fresh_install();
        
        // 結果をキャッシュ
        update_option($this->fresh_install_key, $is_fresh ? 'yes' : 'no');
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $type = $is_fresh ? '新規インストール' : '既存環境';
            error_log("KTPWP Fresh Install Detector: {$type}を検出");
        }

        return $is_fresh;
    }

    /**
     * 新規インストール判定の実際のロジック
     */
    private function detect_fresh_install() {
        global $wpdb;

        // 1. メインテーブルの存在確認
        $main_tables = array(
            $wpdb->prefix . 'ktp_order',
            $wpdb->prefix . 'ktp_supplier',
            $wpdb->prefix . 'ktp_client'
        );

        $existing_tables = array();
        foreach ($main_tables as $table) {
            if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") === $table) {
                $existing_tables[] = $table;
            }
        }

        // テーブルが1つも存在しない場合は確実に新規インストール
        if (empty($existing_tables)) {
            return true;
        }

        // 2. データの存在確認
        $has_data = false;
        foreach ($existing_tables as $table) {
            $count = $wpdb->get_var("SELECT COUNT(*) FROM `{$table}`");
            if ($count > 0) {
                $has_data = true;
                break;
            }
        }

        // データが存在しない場合は新規インストール
        if (!$has_data) {
            return true;
        }

        // 3. プラグイン設定の存在確認
        $plugin_options = array(
            'ktpwp_design_settings',
            'ktpwp_company_info',
            'ktp_order_table_version'
        );

        foreach ($plugin_options as $option) {
            if (get_option($option, false) !== false) {
                return false; // 既存環境
            }
        }

        // 全ての条件をクリアした場合は新規インストール
        return true;
    }

    /**
     * マイグレーションを新規インストール時にスキップすべきかチェック
     */
    public function should_skip_migrations() {
        return $this->is_fresh_install();
    }

    /**
     * 新規インストール時の基本構造初期化
     */
    public function initialize_fresh_install() {
        if (!$this->is_fresh_install()) {
            return false;
        }

        // 初期化済みチェック
        if (get_option($this->init_completed_key, false)) {
            return true;
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("KTPWP Fresh Install: 新規インストール用基本構造初期化を開始");
        }

        // KTP_Settings の基本テーブル作成処理を呼び出す
        if (class_exists('KTP_Settings')) {
            KTP_Settings::create_or_update_tables();
            
            // 初期化完了フラグを設定
            update_option($this->init_completed_key, true);
            update_option('ktpwp_fresh_install_timestamp', current_time('mysql'));
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("KTPWP Fresh Install: 基本構造初期化が完了");
            }
            return true;
        }

        return false;
    }
} 