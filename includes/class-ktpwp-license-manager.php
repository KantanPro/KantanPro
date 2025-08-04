<?php
/**
 * License Manager class for KTPWP plugin
 *
 * Handles license verification and management with KantanPro License Manager.
 *
 * @package KTPWP
 * @subpackage Includes
 * @since 1.0.0
 * @author Kantan Pro
 * @copyright 2024 Kantan Pro
 * @license GPL-2.0+
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * License Manager class for managing plugin licenses
 *
 * @since 1.0.0
 */
class KTPWP_License_Manager {

    /**
     * Single instance of the class
     *
     * @var KTPWP_License_Manager
     */
    private static $instance = null;

    /**
     * License API endpoints
     *
     * @var array
     */
    private $api_endpoints = array(
        'verify' => 'https://www.kantanpro.com/wp-json/ktp-license/v1/verify',
        'info'   => 'https://www.kantanpro.com/wp-json/ktp-license/v1/info',
        'create' => 'https://www.kantanpro.com/wp-json/ktp-license/v1/create'
    );

    /**
     * Rate limit settings
     *
     * @var array
     */
    private $rate_limit = array(
        'max_requests' => 100,
        'time_window'  => 3600 // 1 hour in seconds
    );

    /**
     * Get singleton instance
     *
     * @since 1.0.0
     * @return KTPWP_License_Manager
     */
    public static function get_instance() {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     *
     * @since 1.0.0
     */
    private function __construct() {
        // Initialize hooks
        add_action( 'admin_init', array( $this, 'handle_license_activation' ) );
        add_action( 'wp_ajax_ktpwp_verify_license', array( $this, 'ajax_verify_license' ) );
        add_action( 'wp_ajax_ktpwp_get_license_info', array( $this, 'ajax_get_license_info' ) );
        add_action( 'wp_ajax_ktpwp_toggle_dev_license', array( $this, 'ajax_toggle_dev_license' ) );
        
        // ライセンス状態の初期化
        $this->initialize_license_state();
    }

    /**
     * Initialize license state
     *
     * @since 1.0.0
     */
    private function initialize_license_state() {
        $license_key = get_option( 'ktp_license_key' );
        $license_status = get_option( 'ktp_license_status' );
        
        // ライセンスキーが設定されていない場合、明示的に無効な状態にする
        if ( empty( $license_key ) ) {
            if ( $license_status !== 'not_set' ) {
                update_option( 'ktp_license_status', 'not_set' );
                update_option( 'ktp_license_info', array(
                    'message' => 'レポート機能を利用するにはライセンスキーが必要です。',
                    'features' => array(
                        'reports' => false,
                        'analytics' => false
                    )
                ));
                error_log( 'KTPWP License: Initializing license status to not_set (no license key)' );
            }
        }
    }

    /**
     * Handle license activation form submission
     *
     * @since 1.0.0
     */
    public function handle_license_activation() {
        if ( ! isset( $_POST['ktp_license_activation'] ) || ! wp_verify_nonce( $_POST['ktp_license_nonce'], 'ktp_license_activation' ) ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'この操作を実行する権限がありません。', 'ktpwp' ) );
        }

        $license_key = sanitize_text_field( $_POST['ktp_license_key'] ?? '' );
        
        if ( empty( $license_key ) ) {
            add_settings_error( 'ktp_license', 'empty_key', __( 'ライセンスキーを入力してください。', 'ktpwp' ), 'error' );
            return;
        }

        $result = $this->verify_license( $license_key );
        
        if ( $result['success'] ) {
            // Save license key
            update_option( 'ktp_license_key', $license_key );
            update_option( 'ktp_license_status', 'active' );
            update_option( 'ktp_license_info', $result['data'] );
            update_option( 'ktp_license_verified_at', current_time( 'timestamp' ) );
            
            add_settings_error( 'ktp_license', 'activation_success', __( 'ライセンスが正常に認証されました。', 'ktpwp' ), 'success' );
        } else {
            add_settings_error( 'ktp_license', 'activation_failed', $result['message'], 'error' );
        }
    }

    /**
     * Verify license with KantanPro License Manager
     *
     * @since 1.0.0
     * @param string $license_key License key to verify
     * @return array Verification result
     */
    public function verify_license( $license_key ) {
        // Check rate limit
        if ( ! $this->check_rate_limit() ) {
            return array(
                'success' => false,
                'message' => __( 'レート制限に達しました。1時間後に再試行してください。', 'ktpwp' )
            );
        }

        $site_url = get_site_url();
        
        $response = wp_remote_post( $this->api_endpoints['verify'], array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'User-Agent'   => 'KantanPro/' . KANTANPRO_PLUGIN_VERSION
            ),
            'body' => json_encode( array(
                'license_key' => $license_key,
                'site_url'    => $site_url
            ) ),
            'timeout' => 30,
            'sslverify' => true
        ) );

        if ( is_wp_error( $response ) ) {
            return array(
                'success' => false,
                'message' => __( 'ライセンスサーバーとの通信に失敗しました。', 'ktpwp' ) . ' ' . $response->get_error_message()
            );
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( ! $data ) {
            return array(
                'success' => false,
                'message' => __( 'ライセンスサーバーからの応答が無効です。', 'ktpwp' )
            );
        }

        if ( isset( $data['success'] ) && $data['success'] ) {
            error_log( 'KTPWP License: Verification successful - ' . json_encode( $data ) );
            return array(
                'success' => true,
                'data'    => $data['data'] ?? array(),
                'message' => $data['message'] ?? __( 'ライセンスが正常に認証されました。', 'ktpwp' )
            );
        } else {
            error_log( 'KTPWP License: Verification failed - ' . json_encode( $data ) );
            return array(
                'success' => false,
                'message' => $data['message'] ?? __( 'ライセンスの認証に失敗しました。', 'ktpwp' )
            );
        }
    }

    /**
     * Get license information
     *
     * @since 1.0.0
     * @param string $license_key License key
     * @return array License information
     */
    public function get_license_info( $license_key ) {
        $response = wp_remote_post( $this->api_endpoints['info'], array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'User-Agent'   => 'KantanPro/' . KANTANPRO_PLUGIN_VERSION
            ),
            'body' => json_encode( array(
                'license_key' => $license_key
            ) ),
            'timeout' => 30,
            'sslverify' => true
        ) );

        if ( is_wp_error( $response ) ) {
            return array(
                'success' => false,
                'message' => $response->get_error_message()
            );
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( ! $data ) {
            return array(
                'success' => false,
                'message' => __( 'ライセンスサーバーからの応答が無効です。', 'ktpwp' )
            );
        }

        return $data;
    }

    /**
     * Check if license is valid
     *
     * @since 1.0.0
     * @return bool True if license is valid
     */
    public function is_license_valid() {
        // 開発環境の判定
        if ( $this->is_development_environment() ) {
            // 開発ライセンスが無効化されている場合は false
            if ( ! $this->is_dev_license_enabled() ) {
                error_log( 'KTPWP License Check: Development license is disabled by setting.' );
                return false;
            }
            
            // 開発環境であれば、ライセンスキーの検証をスキップして常に有効とみなす
            error_log( 'KTPWP License Check: Development environment active, license assumed valid.' );
            return true;
        }

        // --- 以下、本番環境のライセンスチェックロジック ---

        $license_key = get_option( 'ktp_license_key' );
        $license_status = get_option( 'ktp_license_status' );
        $verified_at = get_option( 'ktp_license_verified_at' );

        // ライセンスキーが空の場合、ステータスを確実に'not_set'にする
        if ( empty( $license_key ) ) {
            if ( $license_status !== 'not_set' ) {
                update_option( 'ktp_license_status', 'not_set' );
                update_option( 'ktp_license_info', array(
                    'message' => 'レポート機能を利用するにはライセンスキーが必要です。',
                    'features' => array(
                        'reports' => false,
                        'analytics' => false
                    )
                ));
                error_log( 'KTPWP License Check: License key is empty, setting status to not_set' );
            }
            return false;
        }

        // デバッグログを追加
        error_log( 'KTPWP License Check: license_key = set, status = ' . $license_status );

        if ( $license_status !== 'active' ) {
            error_log( 'KTPWP License Check: License status is not active: ' . $license_status );
            return false;
        }

        // not_setステータスの場合も明示的に無効とする
        if ( $license_status === 'not_set' ) {
            error_log( 'KTPWP License Check: License status is not_set' );
            return false;
        }

        // Check if verification is older than 24 hours
        if ( $verified_at && ( current_time( 'timestamp' ) - $verified_at ) > 86400 ) {
            // Re-verify license
            $result = $this->verify_license( $license_key );
            if ( ! $result['success'] ) {
                update_option( 'ktp_license_status', 'invalid' );
                error_log( 'KTPWP License Check: License verification failed' );
                return false;
            }
            update_option( 'ktp_license_verified_at', current_time( 'timestamp' ) );
        }

        error_log( 'KTPWP License Check: License is valid' );
        return true;
    }

    /**
     * Check if development license is valid
     *
     * @since 1.0.0
     * @return bool True if development license is valid
     */
    private function is_development_license_valid() {
        // 開発環境の判定
        if ( ! $this->is_development_environment() ) {
            return false;
        }

        // 開発用万能ライセンスキーのチェック
        $dev_license_key = $this->get_development_license_key();
        $current_license_key = get_option( 'ktp_license_key' );

        if ( ! empty( $dev_license_key ) && $current_license_key === $dev_license_key ) {
            return true;
        }

        return false;
    }

    /**
     * Check if current environment is development
     *
     * @since 1.0.0
     * @return bool True if development environment
     */
    private function is_development_environment() {
        /**
         * 開発環境かどうかを判定します。
         * 判定の優先順位:
         * 1. KTPWP_DEVELOPMENT_MODE 定数による明示的な指定
         * 2. WP_ENV 定数による指定
         * 3. 厳格なローカル開発環境の判定
         * 4. Docker環境でのローカル開発環境マーカー
         */

        $debug_info = array();
        $is_dev = false;

        // 1. KTPWP_DEVELOPMENT_MODE 定数による明示的な上書き
        if ( defined( 'KTPWP_DEVELOPMENT_MODE' ) ) {
            $is_dev = KTPWP_DEVELOPMENT_MODE === true;
            $debug_info[] = 'KTPWP_DEVELOPMENT_MODE: ' . ( $is_dev ? 'true' : 'false' );
            if ( $is_dev ) {
                error_log( 'KTPWP Dev Environment Check: Detected by KTPWP_DEVELOPMENT_MODE constant' );
                return true;
            }
        }

        // 2. WP_ENV 定数による判定
        if ( defined( 'WP_ENV' ) && 'development' === WP_ENV ) {
            $debug_info[] = 'WP_ENV: development';
            error_log( 'KTPWP Dev Environment Check: Detected by WP_ENV constant' );
            return true;
        } else {
            $debug_info[] = 'WP_ENV: ' . ( defined( 'WP_ENV' ) ? WP_ENV : 'undefined' );
        }

        // 3. 厳格なローカル開発環境の判定
        if ( $this->is_strict_local_environment() ) {
            $debug_info[] = 'strict_local_environment: true';
            error_log( 'KTPWP Dev Environment Check: Detected by strict local environment check' );
            return true;
        } else {
            $debug_info[] = 'strict_local_environment: false';
        }

        // 4. Docker環境でのローカル開発環境マーカー
        if ( $this->is_docker_local_development() ) {
            $debug_info[] = 'docker_local_development: true';
            error_log( 'KTPWP Dev Environment Check: Detected by Docker local development markers' );
            return true;
        } else {
            $debug_info[] = 'docker_local_development: false';
        }

        // デバッグ情報をログに記録
        error_log( 'KTPWP Dev Environment Check: NOT development environment - ' . implode( ', ', $debug_info ) );

        return false;
    }

    /**
     * Check if environment is strictly local development
     *
     * @since 1.0.0
     * @return bool True if strictly local development
     */
    private function is_strict_local_environment() {
        // ローカル開発環境の厳格な判定
        
        // 1. ローカルホスト名の確認
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $server_name = $_SERVER['SERVER_NAME'] ?? '';
        
        // 完全なローカルホスト名のみを許可
        $local_hosts = [
            'localhost',
            '127.0.0.1',
            '[::1]'
        ];
        
        $is_local_host = in_array( $host, $local_hosts, true ) || 
                        in_array( $server_name, $local_hosts, true );
        
        if ( $is_local_host ) {
            // さらに、WP_DEBUGが有効であることを確認
            if ( defined( 'WP_DEBUG' ) && true === WP_DEBUG ) {
                return true;
            }
            
            // または、明示的にローカル開発を示すマーカーファイルが存在する
            if ( file_exists( ABSPATH . '.local-development' ) ) {
                return true;
            }
        }
        
        // .localや.testドメインの場合も、追加条件を満たす場合のみ
        if ( preg_match( '/\.(local|test)$/', $host ) || preg_match( '/\.(local|test)$/', $server_name ) ) {
            // WP_DEBUGが有効かつ、サーバーIPがローカル範囲内
            if ( defined( 'WP_DEBUG' ) && true === WP_DEBUG && $this->is_local_ip() ) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Check if current environment is Docker local development
     *
     * @since 1.0.0
     * @return bool True if Docker local development
     */
    private function is_docker_local_development() {
        // Docker環境かどうかを確認
        if ( ! file_exists( '/.dockerenv' ) ) {
            return false;
        }
        
        // Docker環境でも、以下の条件を全て満たす場合のみローカル開発環境とみなす
        $conditions = [
            // 1. WP_DEBUGが有効
            defined( 'WP_DEBUG' ) && true === WP_DEBUG,
            
            // 2. ローカル開発を示す環境変数が設定されている
            $this->has_local_development_markers(),
            
            // 3. サーバーIPがローカル範囲内
            $this->is_local_ip(),
            
            // 4. ホスト名がローカル開発環境を示している
            $this->is_local_development_hostname()
        ];
        
        // 全ての条件を満たす場合のみtrueを返す
        return count( array_filter( $conditions ) ) >= 3;
    }

    /**
     * Check if local development markers exist
     *
     * @since 1.0.0
     * @return bool True if local development markers exist
     */
    private function has_local_development_markers() {
        // 環境変数による判定
        $env_markers = [
            'DOCKER_LOCAL_DEV',
            'KTPWP_LOCAL_DEV',
            'LOCAL_DEVELOPMENT',
            'COMPOSE_PROJECT_NAME' // docker-composeプロジェクト名
        ];
        
        foreach ( $env_markers as $marker ) {
            if ( getenv( $marker ) ) {
                return true;
            }
        }
        
        // ファイルマーカーによる判定
        $file_markers = [
            ABSPATH . '.local-development',
            ABSPATH . 'docker-compose.yml',
            ABSPATH . 'docker-compose.yaml',
            ABSPATH . '../docker-compose.yml',
            ABSPATH . '../docker-compose.yaml'
        ];
        
        foreach ( $file_markers as $file ) {
            if ( file_exists( $file ) ) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Check if server IP is in local range
     *
     * @since 1.0.0
     * @return bool True if server IP is local
     */
    private function is_local_ip() {
        $server_ip = $_SERVER['SERVER_ADDR'] ?? '';
        
        if ( empty( $server_ip ) ) {
            return false;
        }
        
        // ローカルIPの範囲
        $local_ranges = [
            '127.0.0.0/8',    // 127.0.0.1
            '10.0.0.0/8',     // 10.x.x.x
            '172.16.0.0/12',  // 172.16.x.x - 172.31.x.x
            '192.168.0.0/16', // 192.168.x.x
            '::1/128'         // IPv6 localhost
        ];
        
        foreach ( $local_ranges as $range ) {
            if ( $this->ip_in_range( $server_ip, $range ) ) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Check if hostname indicates local development
     *
     * @since 1.0.0
     * @return bool True if hostname indicates local development
     */
    private function is_local_development_hostname() {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        
        // 明確にローカル開発を示すパターン
        $local_patterns = [
            '/^localhost(:\d+)?$/',
            '/^127\.0\.0\.1(:\d+)?$/',
            '/^.*\.local(:\d+)?$/',
            '/^.*\.test(:\d+)?$/',
            '/^.*\.dev(:\d+)?$/',
            '/^.*-local\./',
            '/^dev-.*\./',
            '/^local-.*\./'
        ];
        
        foreach ( $local_patterns as $pattern ) {
            if ( preg_match( $pattern, $host ) ) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Check if IP is in given range
     *
     * @since 1.0.0
     * @param string $ip IP address to check
     * @param string $range IP range in CIDR notation
     * @return bool True if IP is in range
     */
    private function ip_in_range( $ip, $range ) {
        if ( strpos( $range, '/' ) === false ) {
            return $ip === $range;
        }
        
        list( $subnet, $mask ) = explode( '/', $range );
        
        if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
            return $this->ipv6_in_range( $ip, $subnet, $mask );
        } else {
            return $this->ipv4_in_range( $ip, $subnet, $mask );
        }
    }

    /**
     * Check if IPv4 is in range
     *
     * @since 1.0.0
     * @param string $ip IPv4 address
     * @param string $subnet Subnet
     * @param int $mask Mask bits
     * @return bool True if IP is in range
     */
    private function ipv4_in_range( $ip, $subnet, $mask ) {
        $ip_long = ip2long( $ip );
        $subnet_long = ip2long( $subnet );
        $mask_long = -1 << ( 32 - $mask );
        
        if ( $ip_long === false || $subnet_long === false ) {
            return false;
        }
        
        return ( $ip_long & $mask_long ) === ( $subnet_long & $mask_long );
    }

    /**
     * Check if IPv6 is in range
     *
     * @since 1.0.0
     * @param string $ip IPv6 address
     * @param string $subnet Subnet
     * @param int $mask Mask bits
     * @return bool True if IP is in range
     */
    private function ipv6_in_range( $ip, $subnet, $mask ) {
        $ip_bin = inet_pton( $ip );
        $subnet_bin = inet_pton( $subnet );
        
        if ( $ip_bin === false || $subnet_bin === false ) {
            return false;
        }
        
        $mask_bytes = intval( $mask / 8 );
        $mask_bits = $mask % 8;
        
        // Compare full bytes
        if ( $mask_bytes > 0 && substr( $ip_bin, 0, $mask_bytes ) !== substr( $subnet_bin, 0, $mask_bytes ) ) {
            return false;
        }
        
        // Compare remaining bits
        if ( $mask_bits > 0 && $mask_bytes < strlen( $ip_bin ) ) {
            $ip_byte = ord( $ip_bin[$mask_bytes] );
            $subnet_byte = ord( $subnet_bin[$mask_bytes] );
            $bit_mask = 0xFF << ( 8 - $mask_bits );
            
            return ( $ip_byte & $bit_mask ) === ( $subnet_byte & $bit_mask );
        }
        
        return true;
    }

    /**
     * Get development license key
     *
     * @since 1.0.0
     * @return string Development license key
     */
    private function get_development_license_key() {
        // 環境変数から取得
        $dev_key = getenv( 'KTPWP_DEV_LICENSE_KEY' );
        if ( ! empty( $dev_key ) ) {
            return $dev_key;
        }

        // wp-config.phpで定義された定数から取得
        if ( defined( 'KTPWP_DEV_LICENSE_KEY' ) ) {
            return KTPWP_DEV_LICENSE_KEY;
        }

        // デフォルトの開発用キー（本番環境では使用されない）
        return 'DEV-KTPWP-2024-UNIVERSAL-KEY';
    }

    /**
     * Check rate limit
     *
     * @since 1.0.0
     * @return bool True if within rate limit
     */
    private function check_rate_limit() {
        $current_time = current_time( 'timestamp' );
        $requests = get_option( 'ktp_license_requests', array() );
        
        // Remove old requests outside the time window
        $requests = array_filter( $requests, function( $timestamp ) use ( $current_time ) {
            return ( $current_time - $timestamp ) < $this->rate_limit['time_window'];
        } );

        // Check if we're within the rate limit
        if ( count( $requests ) >= $this->rate_limit['max_requests'] ) {
            return false;
        }

        // Add current request
        $requests[] = $current_time;
        update_option( 'ktp_license_requests', $requests );

        return true;
    }

    /**
     * AJAX handler for license verification
     *
     * @since 1.0.0
     */
    public function ajax_verify_license() {
        check_ajax_referer( 'ktp_license_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'この操作を実行する権限がありません。', 'ktpwp' ) );
        }

        $license_key = sanitize_text_field( $_POST['license_key'] ?? '' );
        
        if ( empty( $license_key ) ) {
            wp_send_json_error( __( 'ライセンスキーを入力してください。', 'ktpwp' ) );
        }

        // フロントエンドの正規表現と一致させる
        $pattern = '/^KTPA-[A-Z0-9]{6}-[A-Z0-9]{6}-[A-Z0-9]{4}$/';
        if ( ! preg_match( $pattern, $license_key ) ) {
            wp_send_json_error( __( 'ライセンスキーの形式が正しくありません。', 'ktpwp' ) );
        }

        $result = $this->verify_license( $license_key );
        
        if ( $result['success'] ) {
            // ライセンスキーとステータスを保存
            update_option( 'ktp_license_key', $license_key );
            update_option( 'ktp_license_status', 'active' );
            update_option( 'ktp_license_info', $result['data'] ?? array() );
            update_option( 'ktp_license_verified_at', current_time( 'timestamp' ) );

            wp_send_json_success( array(
                'message' => $result['message'] ?? __( 'ライセンスが正常に認証されました。', 'ktpwp' )
            ) );
        } else {
            // 失敗した場合もステータスを更新
            update_option( 'ktp_license_status', 'invalid' );
            wp_send_json_error( $result['message'] );
        }
    }

    /**
     * AJAX handler for getting license information
     *
     * @since 1.0.0
     */
    public function ajax_get_license_info() {
        check_ajax_referer( 'ktp_license_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'この操作を実行する権限がありません。', 'ktpwp' ) );
        }

        $license_key = get_option( 'ktp_license_key' );
        
        if ( empty( $license_key ) ) {
            wp_send_json_error( __( 'ライセンスキーが設定されていません。', 'ktpwp' ) );
        }

        $result = $this->get_license_info( $license_key );
        
        if ( $result['success'] ) {
            wp_send_json_success( $result );
        } else {
            wp_send_json_error( $result['message'] );
        }
    }

    /**
     * AJAX handler for toggling development license status
     *
     * @since 1.0.0
     */
    public function ajax_toggle_dev_license() {
        check_ajax_referer( 'ktp_license_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'この操作を実行する権限がありません。', 'ktpwp' ) );
        }

        if ( ! $this->is_development_environment() ) {
            wp_send_json_error( __( 'この機能は開発環境でのみ利用可能です。', 'ktpwp' ) );
        }

        $enabled = $this->is_dev_license_enabled();
        update_option( 'ktp_dev_license_enabled', $enabled ? '0' : '1' );

        wp_send_json_success( array(
            'new_status' => ! $enabled
        ) );
    }

    /**
     * Check if development license is enabled via settings
     *
     * @since 1.0.0
     * @return bool True if enabled
     */
    public function is_dev_license_enabled() {
        // オプションが存在しない場合や '1' の場合は有効とみなす
        return get_option( 'ktp_dev_license_enabled', '1' ) === '1';
    }

    /**
     * Get license status for display
     *
     * @since 1.0.0
     * @return array License status information
     */
    public function get_license_status() {
        $license_key = get_option( 'ktp_license_key' );
        $license_status = get_option( 'ktp_license_status' );
        $license_info = get_option( 'ktp_license_info', array() );
        $verified_at = get_option( 'ktp_license_verified_at' );

        if ( empty( $license_key ) ) {
            return array(
                'status' => 'not_set',
                'message' => __( 'ライセンスキーが設定されていません。', 'ktpwp' ),
                'icon' => 'dashicons-warning',
                'color' => '#f56e28'
            );
        }

        // 開発環境の特別な処理
        if ( $this->is_development_environment() ) {
            if ( $this->is_dev_license_enabled() ) {
                return array(
                    'status' => 'active_dev',
                    'message' => __( 'ライセンスが有効です。（開発環境）', 'ktpwp' ),
                    'icon' => 'dashicons-yes-alt',
                    'color' => '#46b450',
                    'info' => array_merge( $license_info, array(
                        'type' => 'development',
                        'environment' => 'development'
                    ) ),
                    'is_dev_mode' => true
                );
            } else {
                return array(
                    'status' => 'inactive_dev',
                    'message' => __( 'ライセンスが無効です。（開発環境モードで無効化中）', 'ktpwp' ),
                    'icon' => 'dashicons-warning',
                    'color' => '#f56e28',
                    'is_dev_mode' => true
                );
            }
        }

        // 本番環境、または開発用ライセンスキーが設定されていない場合

        // ライセンスステータスがactiveの場合、KLMサーバーで最新の状態を確認
        if ( $license_status === 'active' ) {
            // 検証が24時間以上古い場合、または強制再検証が必要な場合
            $needs_verification = false;
            
            if ( ! $verified_at || ( current_time( 'timestamp' ) - $verified_at ) > 86400 ) {
                $needs_verification = true;
            }
            
            // 設定ページでの表示時は常に最新状態を確認（KLMでの無効化を検出するため）
            if ( isset( $_GET['page'] ) && $_GET['page'] === 'ktp-license' ) {
                $needs_verification = true;
            }
            
            if ( $needs_verification ) {
                $result = $this->verify_license( $license_key );
                
                if ( $result['success'] ) {
                    // ライセンスが有効な場合、情報を更新
                    update_option( 'ktp_license_info', $result['data'] );
                    update_option( 'ktp_license_verified_at', current_time( 'timestamp' ) );
                    $license_info = $result['data'];
                } else {
                    // ライセンスが無効な場合、ステータスを更新
                    update_option( 'ktp_license_status', 'invalid' );
                    error_log( 'KTPWP License: License verification failed in get_license_status: ' . $result['message'] );
                    
                    return array(
                        'status' => 'invalid',
                        'message' => __( 'ライセンスが無効です。', 'ktpwp' ) . ' (' . $result['message'] . ')',
                        'icon' => 'dashicons-no-alt',
                        'color' => '#dc3232'
                    );
                }
            }
        }

        if ( $license_status === 'active' ) {
            return array(
                'status' => 'active',
                'message' => __( 'ライセンスが有効です。', 'ktpwp' ),
                'icon' => 'dashicons-yes-alt',
                'color' => '#46b450',
                'info' => $license_info
            );
        } else {
            return array(
                'status' => 'invalid',
                'message' => __( 'ライセンスが無効です。', 'ktpwp' ),
                'icon' => 'dashicons-no-alt',
                'color' => '#dc3232'
            );
        }
    }

    /**
     * Check if report functionality should be enabled
     *
     * @since 1.0.0
     * @return bool True if reports should be enabled
     */
    public function is_report_enabled() {
        return $this->is_license_valid();
    }

    /**
     * Deactivate license
     *
     * @since 1.0.0
     */
    public function deactivate_license() {
        delete_option( 'ktp_license_key' );
        delete_option( 'ktp_license_status' );
        delete_option( 'ktp_license_info' );
        delete_option( 'ktp_license_verified_at' );
        error_log( 'KTPWP License: License deactivated' );
    }

    /**
     * Reset license to invalid state for testing
     *
     * @since 1.0.0
     */
    public function reset_license_for_testing() {
        update_option( 'ktp_license_status', 'not_set' );
        error_log( 'KTPWP License: License reset to not_set for testing' );
    }

    /**
     * Clear all license data for testing
     *
     * @since 1.0.0
     */
    public function clear_all_license_data() {
        delete_option( 'ktp_license_key' );
        delete_option( 'ktp_license_status' );
        delete_option( 'ktp_license_info' );
        delete_option( 'ktp_license_verified_at' );
        error_log( 'KTPWP License: All license data cleared for testing' );
    }

    /**
     * Set development license for testing
     *
     * @since 1.0.0
     */
    public function set_development_license() {
        if ( ! $this->is_development_environment() ) {
            error_log( 'KTPWP License: Cannot set development license in production environment' );
            return false;
        }

        $dev_license_key = $this->get_development_license_key();
        
        update_option( 'ktp_license_key', $dev_license_key );
        update_option( 'ktp_license_status', 'active' );
        update_option( 'ktp_license_info', array(
            'type' => 'development',
            'expires' => '2099-12-31',
            'sites' => 'unlimited',
            'features' => 'all'
        ) );
        update_option( 'ktp_license_verified_at', current_time( 'timestamp' ) );
        
        error_log( 'KTPWP License: Development license set successfully' );
        return true;
    }

    /**
     * Get development environment info
     *
     * @since 1.0.0
     * @return array Development environment information
     */
    public function get_development_info() {
        return array(
            'is_development' => $this->is_development_environment(),
            'host' => $_SERVER['HTTP_HOST'] ?? 'unknown',
            'dev_license_key' => $this->get_development_license_key(),
            'current_license_key' => get_option( 'ktp_license_key' ),
            'license_status' => get_option( 'ktp_license_status' ),
            'is_dev_license_active' => $this->is_development_license_valid()
        );
    }
}

// Initialize the license manager
KTPWP_License_Manager::get_instance(); 