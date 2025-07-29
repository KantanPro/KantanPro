<?php
/**
 * KTPWP Database Class
 *
 * データベーステーブル管理クラス
 *
 * @package KTPWP
 * @since 1.0.0
 */

// セキュリティ: 直接アクセスを防止
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * KTPWP_Databaseクラス
 *
 * プラグインのデータベーステーブルを管理
 */
class KTPWP_Database {

    /**
     * シングルトンインスタンス
     *
     * @var KTPWP_Database|null
     */
    private static $instance = null;

    /**
     * テーブル作成に必要なクラスマップ
     *
     * @var array
     */
    private $table_classes = array(
        'client' => 'Kntan_Client_Class',
        'service' => 'Kntan_Service_Class',
        'supplier' => 'KTPWP_Supplier_Class',
    );

    /**
     * クラスファイルマップ
     *
     * @var array
     */
    private $class_files = array(
        'Kntan_Client_Class' => 'class-tab-client.php',
        'Kntan_Service_Class' => 'class-tab-service.php',
        'KTPWP_Supplier_Class' => 'class-tab-supplier.php',
        'KTPWP_Supplier_Security' => 'class-supplier-security.php',
        'KTPWP_Supplier_Data' => 'class-supplier-data.php',
        'Kantan_Login_Error' => 'class-login-error.php',
    );

    /**
     * コンストラクタ
     */
    private function __construct() {
        // プライベートコンストラクタ（シングルトン）
    }

    /**
     * シングルトンインスタンスを取得
     *
     * @return KTPWP_Database
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * プラグインアクティベーション時のテーブルセットアップ
     */
    public function setup_tables() {
        // 必要なクラスファイルを読み込む
        $this->load_required_classes();

        // 各クラスでテーブル作成処理を行う
        foreach ( $this->table_classes as $table_name => $class_name ) {
            if ( class_exists( $class_name ) ) {
                try {
                    $instance = new $class_name();
                    if ( method_exists( $instance, 'Create_Table' ) ) {
                        $instance->Create_Table( $table_name );
                        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                            error_log( "KTPWP: Table created for {$table_name} using {$class_name}" );
                        }
                    }
                } catch ( Exception $e ) {
                    error_log( "KTPWP Error: Failed to create table {$table_name}: " . $e->getMessage() );
                }
            } else {
                error_log( "KTPWP Error: Class {$class_name} not found for table {$table_name}" );
            }
        }
    }

    /**
     * テーブル更新処理
     *
     * @param string $table_name テーブル名
     */
    public function update_table( $table_name ) {
        if ( ! isset( $this->table_classes[ $table_name ] ) ) {
            return false;
        }

        $class_name = $this->table_classes[ $table_name ];

        if ( class_exists( $class_name ) ) {
            try {
                $instance = new $class_name();
                if ( method_exists( $instance, 'Update_Table' ) ) {
                    $instance->Update_Table( $table_name );
                    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                        error_log( "KTPWP: Table updated for {$table_name} using {$class_name}" );
                    }
                    return true;
                }
            } catch ( Exception $e ) {
                error_log( "KTPWP Error: Failed to update table {$table_name}: " . $e->getMessage() );
            }
        }

        return false;
    }

    /**
     * 必要なクラスファイルを読み込む
     */
    private function load_required_classes() {
        foreach ( $this->class_files as $class_name => $file_name ) {
            if ( ! class_exists( $class_name ) ) {
                $file_path = KANTANPRO_PLUGIN_DIR . 'includes/' . $file_name;

                if ( file_exists( $file_path ) ) {
                    // class-tab-service.php は現在スキップ
                    if ( $file_name === 'class-tab-service.php' ) {
                        continue;
                    }

                    require_once $file_path;

                    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                        error_log( "KTPWP: Loaded class file {$file_name}" );
                    }
                } else {
                    error_log( "KTPWP Error: Class file not found: {$file_path}" );
                }
            }
        }
    }

    /**
     * テーブルが存在するかチェック（キャッシュ対応）
     *
     * @param string $table_name テーブル名
     * @return bool
     */
    public function table_exists( $table_name ) {
        // キャッシュキーを生成
        $cache_key = "table_exists_{$table_name}";
        
        // キャッシュから取得を試行
        $cached_result = ktpwp_cache_get( $cache_key );
        if ( false !== $cached_result ) {
            return (bool) $cached_result;
        }
        
        // キャッシュにない場合はDBから取得
        global $wpdb;
        $full_table_name = $wpdb->prefix . 'ktp_' . $table_name;
        $query = $wpdb->prepare( 'SHOW TABLES LIKE %s', $full_table_name );
        $exists = $wpdb->get_var( $query ) === $full_table_name;
        
        // 結果をキャッシュに保存（5分間）
        ktpwp_cache_set( $cache_key, $exists, 300 );
        
        return $exists;
    }

    /**
     * テーブル構造を取得（キャッシュ対応）
     *
     * @param string $table_name テーブル名
     * @return array|null
     */
    public function get_table_structure( $table_name ) {
        // キャッシュキーを生成
        $cache_key = "table_structure_{$table_name}";
        
        // キャッシュから取得を試行
        $cached_result = ktpwp_cache_get( $cache_key );
        if ( false !== $cached_result ) {
            return $cached_result;
        }
        
        // テーブルの存在チェック
        if ( ! $this->table_exists( $table_name ) ) {
            return null;
        }
        
        // キャッシュにない場合はDBから取得
        global $wpdb;
        $full_table_name = $wpdb->prefix . 'ktp_' . $table_name;
        $structure = $wpdb->get_results( "DESCRIBE {$full_table_name}", ARRAY_A );
        
        // 結果をキャッシュに保存（10分間）
        ktpwp_cache_set( $cache_key, $structure, 600 );
        
        return $structure;
    }

    /**
     * テーブルを削除（デアクティベーション時等）
     *
     * @param string $table_name テーブル名
     * @return bool
     */
    public function drop_table( $table_name ) {
        global $wpdb;
        $full_table_name = $wpdb->prefix . 'ktp_' . $table_name;

        $query = "DROP TABLE IF EXISTS {$full_table_name}";
        $result = $wpdb->query( $query );

        if ( $result !== false ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( "KTPWP: Table {$full_table_name} dropped successfully" );
            }
            return true;
        } else {
            error_log( "KTPWP Error: Failed to drop table {$full_table_name}" );
            return false;
        }
    }

    /**
     * 全テーブルを削除
     *
     * @return bool
     */
    public function drop_all_tables() {
        $success = true;

        foreach ( array_keys( $this->table_classes ) as $table_name ) {
            if ( ! $this->drop_table( $table_name ) ) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * テーブルデータをクリア
     *
     * @param string $table_name テーブル名
     * @return bool
     */
    public function clear_table_data( $table_name ) {
        global $wpdb;
        $full_table_name = $wpdb->prefix . 'ktp_' . $table_name;

        if ( ! $this->table_exists( $table_name ) ) {
            return false;
        }

        $query = "TRUNCATE TABLE {$full_table_name}";
        $result = $wpdb->query( $query );

        if ( $result !== false ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( "KTPWP: Table data cleared for {$full_table_name}" );
            }
            return true;
        } else {
            error_log( "KTPWP Error: Failed to clear table data for {$full_table_name}" );
            return false;
        }
    }

    /**
     * データベースバージョン管理
     *
     * @param string $version バージョン
     */
    public function update_db_version( $version ) {
        update_option( 'ktpwp_db_version', $version );
    }

    /**
     * データベースバージョン取得
     *
     * @return string
     */
    public function get_db_version() {
        return get_option( 'ktpwp_db_version', '0.0.0' );
    }
}
