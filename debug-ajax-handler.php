<?php
/**
 * AJAX デバッグハンドラー
 * 
 * AJAX リクエストの詳細ログを記録し、400エラーの原因を特定します。
 */

// セキュリティ: 直接アクセスを防止
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * AJAX デバッグログクラス
 */
class KTP_AJAX_Debug_Logger {
    
    /**
     * ログファイルパス
     */
    private static $log_file = null;
    
    /**
     * デバッグが有効かどうか
     */
    private static $debug_enabled = null;
    
    /**
     * 初期化（デバッグ時かつ管理画面でのみ実行）
     */
    public static function init() {
        if ( self::is_debug_enabled() && is_admin() ) {
            // AJAX リクエストの監視
            add_action( 'wp_ajax_*', array( __CLASS__, 'monitor_ajax_request' ), 1 );
            add_action( 'wp_ajax_nopriv_*', array( __CLASS__, 'monitor_ajax_request' ), 1 );
            
            // WordPress の全てのAJAXアクションを監視
            add_action( 'wp_ajax_*', array( __CLASS__, 'log_ajax_request' ), 999 );
            add_action( 'wp_ajax_nopriv_*', array( __CLASS__, 'log_ajax_request' ), 999 );
            
            // HTTP レスポンスの監視
            add_action( 'wp_ajax_*', array( __CLASS__, 'monitor_http_response' ), 999 );
            add_action( 'wp_ajax_nopriv_*', array( __CLASS__, 'monitor_http_response' ), 999 );
            
            // PHP エラーのキャッチ（管理画面でのみ）
            add_action( 'init', array( __CLASS__, 'setup_error_handling' ) );
        }
    }
    
    /**
     * AJAX リクエストを監視
     */
    public static function monitor_ajax_request() {
        if ( ! self::is_debug_enabled() || ! defined( 'DOING_AJAX' ) || ! DOING_AJAX ) {
            return;
        }
        
        $action = isset( $_POST['action'] ) ? $_POST['action'] : '';
        
        // KantanPro関連のアクションのみログ
        if ( empty( $action ) || strpos( $action, 'ktp' ) === false ) {
            return;
        }
        
        self::log( 'AJAX Request Started: ' . $action );
    }
    
    /**
     * HTTPレスポンスを監視
     */
    public static function monitor_http_response() {
        if ( ! self::is_debug_enabled() || ! defined( 'DOING_AJAX' ) || ! DOING_AJAX ) {
            return;
        }
        
        $status_code = http_response_code();
        $action = isset( $_POST['action'] ) ? $_POST['action'] : '';
        
        // KantanPro関連のアクションのみログ
        if ( empty( $action ) || strpos( $action, 'ktp' ) === false ) {
            return;
        }
        
        if ( $status_code >= 400 ) {
            self::log( 'HTTP Error Response', array(
                'action' => $action,
                'status_code' => $status_code,
                'headers_sent' => headers_sent(),
                'response_headers' => self::get_response_headers()
            ) );
        }
    }
    
    /**
     * デバッグが有効かどうか確認
     */
    public static function is_debug_enabled() {
        if ( self::$debug_enabled === null ) {
            self::$debug_enabled = defined( 'WP_DEBUG' ) && WP_DEBUG;
        }
        return self::$debug_enabled;
    }
    
    /**
     * ログファイルパスを取得
     */
    public static function get_log_file() {
        if ( self::$log_file === null ) {
            $upload_dir = wp_upload_dir();
            self::$log_file = $upload_dir['basedir'] . '/ktp-ajax-debug.log';
        }
        return self::$log_file;
    }
    
    /**
     * ログを記録
     */
    public static function log( $message, $data = null ) {
        if ( ! self::is_debug_enabled() ) {
            return;
        }
        
        $timestamp = date( 'Y-m-d H:i:s' );
        $log_entry = "[{$timestamp}] {$message}";
        
        if ( $data !== null ) {
            $log_entry .= " | Data: " . json_encode( $data, JSON_UNESCAPED_UNICODE );
        }
        
        $log_entry .= PHP_EOL;
        
        error_log( $log_entry, 3, self::get_log_file() );
    }
    
    /**
     * AJAX リクエストをログに記録
     */
    public static function log_ajax_request() {
        if ( ! self::is_debug_enabled() ) {
            return;
        }
        
        $action = isset( $_POST['action'] ) ? $_POST['action'] : '';
        $nonce = isset( $_POST['nonce'] ) ? $_POST['nonce'] : ( isset( $_POST['_wpnonce'] ) ? $_POST['_wpnonce'] : '' );
        
        // リクエストデータを安全に記録
        $safe_post_data = array();
        foreach ( $_POST as $key => $value ) {
            if ( is_string( $value ) && strlen( $value ) > 500 ) {
                $safe_post_data[ $key ] = substr( $value, 0, 500 ) . '... (truncated)';
            } else {
                $safe_post_data[ $key ] = $value;
            }
        }
        
        self::log( 'AJAX Request Started', array(
            'action' => $action,
            'nonce' => $nonce,
            'user_id' => get_current_user_id(),
            'is_user_logged_in' => is_user_logged_in(),
            'request_method' => $_SERVER['REQUEST_METHOD'],
            'content_type' => isset( $_SERVER['CONTENT_TYPE'] ) ? $_SERVER['CONTENT_TYPE'] : '',
            'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : '',
            'referer' => isset( $_SERVER['HTTP_REFERER'] ) ? $_SERVER['HTTP_REFERER'] : '',
            'post_data' => $safe_post_data,
            'headers' => self::get_request_headers()
        ) );
    }
    
    /**
     * AJAX エラーハンドリング
     */
    public static function handle_ajax_error() {
        if ( ! self::is_debug_enabled() ) {
            return;
        }
        
        // HTTP ステータスコードを確認
        $status_code = http_response_code();
        if ( $status_code !== 200 ) {
            self::log( 'AJAX Error - Non-200 Status Code', array(
                'status_code' => $status_code,
                'action' => isset( $_POST['action'] ) ? $_POST['action'] : '',
                'headers_sent' => headers_sent(),
                'output_buffer' => ob_get_contents()
            ) );
        }
    }
    
    /**
     * PHP エラーハンドリングを設定
     */
    public static function setup_error_handling() {
        if ( ! self::is_debug_enabled() ) {
            return;
        }
        
        // PHP エラーをキャッチ
        set_error_handler( array( __CLASS__, 'handle_php_error' ) );
        
        // 致命的エラーをキャッチ
        register_shutdown_function( array( __CLASS__, 'handle_fatal_error' ) );
    }
    
    /**
     * PHP エラーハンドラー
     */
    public static function handle_php_error( $errno, $errstr, $errfile, $errline ) {
        if ( ! self::is_debug_enabled() ) {
            return;
        }
        
        // AJAX リクエスト中のエラーのみログ
        if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
            self::log( 'PHP Error during AJAX', array(
                'errno' => $errno,
                'errstr' => $errstr,
                'errfile' => $errfile,
                'errline' => $errline,
                'action' => isset( $_POST['action'] ) ? $_POST['action'] : '',
                'backtrace' => debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 5 )
            ) );
        }
    }
    
    /**
     * 致命的エラーハンドラー
     */
    public static function handle_fatal_error() {
        if ( ! self::is_debug_enabled() ) {
            return;
        }
        
        $error = error_get_last();
        if ( $error && defined( 'DOING_AJAX' ) && DOING_AJAX ) {
            self::log( 'Fatal Error during AJAX', array(
                'error' => $error,
                'action' => isset( $_POST['action'] ) ? $_POST['action'] : '',
                'headers_sent' => headers_sent(),
                'output_buffer' => ob_get_contents()
            ) );
        }
    }
    
    /**
     * レスポンスヘッダーを取得
     */
    private static function get_response_headers() {
        $headers = array();
        
        if ( function_exists( 'headers_list' ) ) {
            $sent_headers = headers_list();
            foreach ( $sent_headers as $header ) {
                $parts = explode( ':', $header, 2 );
                if ( count( $parts ) === 2 ) {
                    $headers[ trim( $parts[0] ) ] = trim( $parts[1] );
                }
            }
        }
        
        return $headers;
    }
    
    /**
     * リクエストヘッダーを取得
     */
    private static function get_request_headers() {
        $headers = array();
        
        if ( function_exists( 'getallheaders' ) ) {
            $headers = getallheaders();
        } else {
            // Fallback for servers without getallheaders
            foreach ( $_SERVER as $key => $value ) {
                if ( strpos( $key, 'HTTP_' ) === 0 ) {
                    $header_name = str_replace( ' ', '-', ucwords( str_replace( '_', ' ', strtolower( substr( $key, 5 ) ) ) ) );
                    $headers[ $header_name ] = $value;
                }
            }
        }
        
        return $headers;
    }
    
    /**
     * ログファイルをクリア
     */
    public static function clear_log() {
        if ( self::is_debug_enabled() ) {
            $log_file = self::get_log_file();
            if ( file_exists( $log_file ) ) {
                unlink( $log_file );
            }
        }
    }
    
    /**
     * ログの内容を取得
     */
    public static function get_log_contents( $lines = 100 ) {
        if ( ! self::is_debug_enabled() ) {
            return '';
        }
        
        $log_file = self::get_log_file();
        if ( ! file_exists( $log_file ) ) {
            return '';
        }
        
        // 最新のN行を取得
        $file_lines = file( $log_file );
        $recent_lines = array_slice( $file_lines, -$lines );
        
        return implode( '', $recent_lines );
    }
}

// 初期化
KTP_AJAX_Debug_Logger::init();

// デバッグ用のAJAXハンドラー（デバッグモードかつ管理画面でのみ登録）
if ( defined( 'WP_DEBUG' ) && WP_DEBUG && is_admin() ) {
    add_action( 'wp_ajax_ktp_get_ajax_debug_log', function() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Permission denied' );
        }
        
        // nonce チェック（オプション）
        if ( isset( $_POST['_wpnonce'] ) && ! wp_verify_nonce( $_POST['_wpnonce'], 'ktp_ajax_debug_nonce' ) ) {
            wp_send_json_error( 'Invalid nonce' );
        }
        
        $lines = isset( $_POST['lines'] ) ? intval( $_POST['lines'] ) : 100;
        $log_contents = KTP_AJAX_Debug_Logger::get_log_contents( $lines );
        
        wp_send_json_success( array(
            'log' => $log_contents,
            'log_file' => KTP_AJAX_Debug_Logger::get_log_file()
        ) );
    } );
}

if ( defined( 'WP_DEBUG' ) && WP_DEBUG && is_admin() ) {
    add_action( 'wp_ajax_ktp_clear_ajax_debug_log', function() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Permission denied' );
        }
        
        // nonce チェック（オプション）
        if ( isset( $_POST['_wpnonce'] ) && ! wp_verify_nonce( $_POST['_wpnonce'], 'ktp_ajax_debug_nonce' ) ) {
            wp_send_json_error( 'Invalid nonce' );
        }
        
        KTP_AJAX_Debug_Logger::clear_log();
        
        wp_send_json_success( array(
            'message' => 'Debug log cleared successfully',
            'log_file' => KTP_AJAX_Debug_Logger::get_log_file()
        ) );
    } );
}
