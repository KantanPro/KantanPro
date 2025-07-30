<?php
/**
 * KantanPro Cache Manager
 * 
 * プラグイン全体のキャッシュ機能を管理するクラス
 * 
 * @package KantanPro
 * @since 1.1.4
 */

// セキュリティ: 直接アクセスを防止
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * KTPWP_Cache クラス
 * 
 * WordPressのオブジェクトキャッシュとTransient APIを使用して
 * プラグインのパフォーマンスを向上させます。
 */
class KTPWP_Cache {

    /**
     * キャッシュグループ名
     */
    const CACHE_GROUP = 'kantanpro';
    
    /**
     * デフォルトキャッシュ有効期限（秒）
     */
    const DEFAULT_EXPIRATION = 7200; // 2時間（配布先での表示速度向上のため延長）
    
    /**
     * 長時間キャッシュ有効期限（秒）
     */
    const LONG_EXPIRATION = 86400; // 24時間
    
    /**
     * 短時間キャッシュ有効期限（秒）
     */
    const SHORT_EXPIRATION = 1800; // 30分
    
    /**
     * キャッシュ自動有効化フラグ
     */
    const AUTO_ENABLE_CACHE = true;
    
    /**
     * シングルトンインスタンス
     * 
     * @var KTPWP_Cache
     */
    private static $instance = null;
    
    /**
     * キャッシュ統計
     * 
     * @var array
     */
    private $cache_stats = array(
        'hits' => 0,
        'misses' => 0,
        'sets' => 0,
        'deletes' => 0,
    );

    /**
     * シングルトンインスタンスを取得
     * 
     * @return KTPWP_Cache
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
        $this->init_hooks();
    }

    /**
     * フックを初期化
     */
    private function init_hooks() {
        // プラグイン無効化時にキャッシュをクリア
        register_deactivation_hook( KANTANPRO_PLUGIN_FILE, array( $this, 'clear_all_cache' ) );
        
        // 管理画面でキャッシュ統計を表示（デバッグ時）
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG && is_admin() ) {
            add_action( 'admin_footer', array( $this, 'display_cache_stats' ) );
        }
        
        // 配布先での表示速度向上のため、キャッシュを自動有効化
        if ( self::AUTO_ENABLE_CACHE ) {
            add_action( 'init', array( $this, 'auto_enable_cache' ) );
        }
        
        // パフォーマンス監視
        add_action( 'wp_footer', array( $this, 'monitor_performance' ) );
        add_action( 'admin_footer', array( $this, 'monitor_performance' ) );
    }

    /**
     * キャッシュからデータを取得
     * 
     * @param string $key キャッシュキー
     * @param string $group キャッシュグループ（オプション）
     * @return mixed キャッシュされたデータ、存在しない場合はfalse
     */
    public function get( $key, $group = null ) {
        if ( null === $group ) {
            $group = self::CACHE_GROUP;
        }
        
        $cache_key = $this->get_cache_key( $key );
        $data = wp_cache_get( $cache_key, $group );
        
        if ( false !== $data ) {
            $this->cache_stats['hits']++;
            
            // デバッグログ
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( "KTPWP Cache HIT: {$cache_key}" );
            }
        } else {
            $this->cache_stats['misses']++;
            
            // デバッグログ
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( "KTPWP Cache MISS: {$cache_key}" );
            }
        }
        
        return $data;
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
    public function set( $key, $data, $expiration = null, $group = null ) {
        if ( null === $expiration ) {
            $expiration = self::DEFAULT_EXPIRATION;
        }
        
        if ( null === $group ) {
            $group = self::CACHE_GROUP;
        }
        
        $cache_key = $this->get_cache_key( $key );
        $result = wp_cache_set( $cache_key, $data, $group, $expiration );
        
        if ( $result ) {
            $this->cache_stats['sets']++;
            
            // デバッグログ
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( "KTPWP Cache SET: {$cache_key} (expires in {$expiration}s)" );
            }
        }
        
        return $result;
    }

    /**
     * キャッシュからデータを削除
     * 
     * @param string $key キャッシュキー
     * @param string $group キャッシュグループ（オプション）
     * @return bool 成功時true、失敗時false
     */
    public function delete( $key, $group = null ) {
        if ( null === $group ) {
            $group = self::CACHE_GROUP;
        }
        
        $cache_key = $this->get_cache_key( $key );
        $result = wp_cache_delete( $cache_key, $group );
        
        if ( $result ) {
            $this->cache_stats['deletes']++;
            
            // デバッグログ
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( "KTPWP Cache DELETE: {$cache_key}" );
            }
        }
        
        return $result;
    }

    /**
     * Transient APIを使用してデータを取得
     * 
     * @param string $key Transientキー
     * @return mixed 保存されたデータ、存在しない場合はfalse
     */
    public function get_transient( $key ) {
        $transient_key = $this->get_transient_key( $key );
        $data = get_transient( $transient_key );
        
        if ( false !== $data ) {
            $this->cache_stats['hits']++;
            
            // デバッグログ
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( "KTPWP Transient HIT: {$transient_key}" );
            }
        } else {
            $this->cache_stats['misses']++;
            
            // デバッグログ
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( "KTPWP Transient MISS: {$transient_key}" );
            }
        }
        
        return $data;
    }

    /**
     * Transient APIを使用してデータを保存
     * 
     * @param string $key Transientキー
     * @param mixed $data 保存するデータ
     * @param int $expiration 有効期限（秒）
     * @return bool 成功時true、失敗時false
     */
    public function set_transient( $key, $data, $expiration = null ) {
        if ( null === $expiration ) {
            $expiration = self::DEFAULT_EXPIRATION;
        }
        
        $transient_key = $this->get_transient_key( $key );
        $result = set_transient( $transient_key, $data, $expiration );
        
        if ( $result ) {
            $this->cache_stats['sets']++;
            
            // デバッグログ
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( "KTPWP Transient SET: {$transient_key} (expires in {$expiration}s)" );
            }
        }
        
        return $result;
    }

    /**
     * Transientを削除
     * 
     * @param string $key Transientキー
     * @return bool 成功時true、失敗時false
     */
    public function delete_transient( $key ) {
        $transient_key = $this->get_transient_key( $key );
        $result = delete_transient( $transient_key );
        
        if ( $result ) {
            $this->cache_stats['deletes']++;
            
            // デバッグログ
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( "KTPWP Transient DELETE: {$transient_key}" );
            }
        }
        
        return $result;
    }

    /**
     * キャッシュキーを生成
     * 
     * @param string $key ベースキー
     * @return string プレフィックス付きキャッシュキー
     */
    private function get_cache_key( $key ) {
        return 'ktpwp_' . md5( $key );
    }

    /**
     * Transientキーを生成
     * 
     * @param string $key ベースキー
     * @return string プレフィックス付きTransientキー
     */
    private function get_transient_key( $key ) {
        // Transientキーは45文字制限があるため、短縮
        $hash = substr( md5( $key ), 0, 20 );
        return 'ktpwp_' . $hash;
    }

    /**
     * すべてのキャッシュをクリア
     */
    public function clear_all_cache() {
        // オブジェクトキャッシュグループをフラッシュ
        wp_cache_flush_group( self::CACHE_GROUP );
        
        // KantanPro関連のTransientを削除
        global $wpdb;
        $wpdb->query( 
            $wpdb->prepare( 
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                '_transient_ktpwp_%',
                '_transient_timeout_ktpwp_%'
            )
        );
        
        // デバッグログ
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP Cache: All cache cleared' );
        }
    }

    /**
     * 特定のパターンに一致するキャッシュを削除
     * 
     * @param string $pattern キーパターン
     */
    public function clear_cache_by_pattern( $pattern ) {
        // Transientを削除（パターンマッチ）
        global $wpdb;
        $like_pattern = 'ktpwp_' . str_replace( '*', '%', $pattern );
        
        $wpdb->query( 
            $wpdb->prepare( 
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                '_transient_' . $like_pattern,
                '_transient_timeout_' . $like_pattern
            )
        );
        
        // デバッグログ
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( "KTPWP Cache: Cleared cache pattern: {$pattern}" );
        }
    }

    /**
     * キャッシュ統計を取得
     * 
     * @return array キャッシュ統計
     */
    public function get_cache_stats() {
        return $this->cache_stats;
    }

    /**
     * キャッシュ統計を表示（デバッグ用）
     */
    public function display_cache_stats() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        
        $stats = $this->get_cache_stats();
        $total_requests = $stats['hits'] + $stats['misses'];
        $hit_rate = $total_requests > 0 ? round( ( $stats['hits'] / $total_requests ) * 100, 2 ) : 0;
        
        echo '<div style="position: fixed; bottom: 10px; right: 10px; background: #fff; border: 1px solid #ccc; padding: 10px; font-size: 12px; z-index: 9999;">';
        echo '<strong>KTPWP Cache Stats:</strong><br>';
        echo "Hits: {$stats['hits']}<br>";
        echo "Misses: {$stats['misses']}<br>";
        echo "Sets: {$stats['sets']}<br>";
        echo "Deletes: {$stats['deletes']}<br>";
        echo "Hit Rate: {$hit_rate}%";
        echo '</div>';
    }

    /**
     * データベースクエリ結果をキャッシュから取得または実行
     * 
     * @param string $key キャッシュキー
     * @param callable $callback データを取得するコールバック関数
     * @param int $expiration キャッシュ有効期限（秒）
     * @return mixed キャッシュされたデータまたはコールバックの実行結果
     */
    public function remember( $key, $callback, $expiration = null ) {
        // キャッシュから取得を試行
        $data = $this->get( $key );
        
        if ( false !== $data ) {
            return $data;
        }
        
        // キャッシュにない場合はコールバックを実行
        if ( is_callable( $callback ) ) {
            $data = call_user_func( $callback );
            
            // 結果をキャッシュに保存
            if ( false !== $data ) {
                $this->set( $key, $data, $expiration );
            }
        }
        
        return $data;
    }

    /**
     * Transient版のremember関数
     * 
     * @param string $key Transientキー
     * @param callable $callback データを取得するコールバック関数
     * @param int $expiration 有効期限（秒）
     * @return mixed キャッシュされたデータまたはコールバックの実行結果
     */
    public function remember_transient( $key, $callback, $expiration = null ) {
        // Transientから取得を試行
        $data = $this->get_transient( $key );
        
        if ( false !== $data ) {
            return $data;
        }
        
        // Transientにない場合はコールバックを実行
        if ( is_callable( $callback ) ) {
            $data = call_user_func( $callback );
            
            // 結果をTransientに保存
            if ( false !== $data ) {
                $this->set_transient( $key, $data, $expiration );
            }
        }
        
        return $data;
    }

    /**
     * キャッシュを自動有効化（配布先での表示速度向上）
     */
    public function auto_enable_cache() {
        // オブジェクトキャッシュが利用可能かチェック
        if ( ! wp_using_ext_object_cache() ) {
            // 外部オブジェクトキャッシュがない場合は、WordPressの内部キャッシュを最適化
            $this->optimize_internal_cache();
        }
        
        // キャッシュ統計の初期化
        $this->initialize_cache_stats();
        
        // デバッグログ
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP Cache: 自動有効化が実行されました' );
        }
    }

    /**
     * 内部キャッシュを最適化
     */
    private function optimize_internal_cache() {
        // WordPressの内部キャッシュ設定を最適化
        if ( ! defined( 'WP_CACHE' ) ) {
            define( 'WP_CACHE', true );
        }
        
        // キャッシュディレクトリの確認と作成
        $cache_dir = WP_CONTENT_DIR . '/cache';
        if ( ! is_dir( $cache_dir ) ) {
            wp_mkdir_p( $cache_dir );
        }
        
        // キャッシュファイルのパーミッション設定
        if ( is_dir( $cache_dir ) ) {
            chmod( $cache_dir, 0755 );
        }
    }

    /**
     * キャッシュ統計を初期化
     */
    private function initialize_cache_stats() {
        // キャッシュ統計をTransientに保存
        $stats = $this->get_cache_stats();
        $this->set_transient( 'ktpwp_cache_stats', $stats, self::LONG_EXPIRATION );
    }

    /**
     * パフォーマンス監視
     */
    public function monitor_performance() {
        // 管理者のみに表示
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        
        // パフォーマンス統計を取得
        $stats = $this->get_cache_stats();
        $total_requests = $stats['hits'] + $stats['misses'];
        $hit_rate = $total_requests > 0 ? round( ( $stats['hits'] / $total_requests ) * 100, 2 ) : 0;
        
        // メモリ使用量を取得
        $memory_usage = memory_get_usage( true );
        $memory_limit = ini_get( 'memory_limit' );
        
        // 実行時間を取得
        $execution_time = microtime( true ) - $_SERVER['REQUEST_TIME_FLOAT'];
        
        // パフォーマンス警告を表示
        if ( $hit_rate < 50 && $total_requests > 10 ) {
            echo '<div style="position: fixed; bottom: 10px; left: 10px; background: #fff3cd; border: 1px solid #ffeaa7; padding: 10px; font-size: 12px; z-index: 9999; max-width: 300px;">';
            echo '<strong>⚠️ パフォーマンス警告</strong><br>';
            echo "キャッシュヒット率: {$hit_rate}%<br>";
            echo "メモリ使用量: " . size_format( $memory_usage ) . "<br>";
            echo "実行時間: " . round( $execution_time, 3 ) . "秒<br>";
            echo '<button onclick="this.parentElement.style.display=\'none\'" style="margin-top: 5px;">閉じる</button>';
            echo '</div>';
        }
    }

    /**
     * 配布先での表示速度向上のための特別なキャッシュ戦略
     * 
     * @param string $key キャッシュキー
     * @param callable $callback データ取得コールバック
     * @param int $expiration 有効期限
     * @return mixed キャッシュされたデータ
     */
    public function distribution_cache( $key, $callback, $expiration = null ) {
        // 配布先ではより長いキャッシュ時間を使用
        if ( null === $expiration ) {
            $expiration = self::LONG_EXPIRATION;
        }
        
        // 複数のキャッシュレイヤーを使用
        $data = $this->get( $key );
        if ( false !== $data ) {
            return $data;
        }
        
        $data = $this->get_transient( $key );
        if ( false !== $data ) {
            // Transientから取得したデータをオブジェクトキャッシュにも保存
            $this->set( $key, $data, $expiration );
            return $data;
        }
        
        // データが存在しない場合はコールバックを実行
        if ( is_callable( $callback ) ) {
            $data = call_user_func( $callback );
            
            if ( false !== $data ) {
                // 両方のキャッシュレイヤーに保存
                $this->set( $key, $data, $expiration );
                $this->set_transient( $key, $data, $expiration );
            }
        }
        
        return $data;
    }
}
