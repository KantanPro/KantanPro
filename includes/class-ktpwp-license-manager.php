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
        // 開発環境用万能ライセンスキーのチェック
        if ( $this->is_development_license_valid() ) {
            error_log( 'KTPWP License Check: Development license is valid' );
            return true;
        }

        $license_key = get_option( 'ktp_license_key' );
        $license_status = get_option( 'ktp_license_status' );
        $verified_at = get_option( 'ktp_license_verified_at' );

        // ライセンスキーが空の場合、ステータスを確実に'not_set'にする
        if ( empty( $license_key ) ) {
            if ( $license_status !== 'not_set' ) {
                update_option( 'ktp_license_status', 'not_set' );
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
        // 環境変数で判定
        if ( defined( 'WP_ENV' ) && WP_ENV === 'development' ) {
            return true;
        }

        // ホスト名で判定（localhost, .local, .test など）
        $host = $_SERVER['HTTP_HOST'] ?? '';
        if ( in_array( $host, ['localhost', '127.0.0.1'] ) || 
             strpos( $host, '.local' ) !== false || 
             strpos( $host, '.test' ) !== false ||
             strpos( $host, '.dev' ) !== false ) {
            return true;
        }

        // wp-config.phpで定義された定数で判定
        if ( defined( 'KTPWP_DEVELOPMENT_MODE' ) && KTPWP_DEVELOPMENT_MODE === true ) {
            return true;
        }

        return false;
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

        $result = $this->verify_license( $license_key );
        
        if ( $result['success'] ) {
            wp_send_json_success( $result );
        } else {
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

        // 開発環境用万能ライセンスキーのチェック
        if ( $this->is_development_license_valid() ) {
            return array(
                'status' => 'active',
                'message' => __( 'ライセンスが有効です。（開発環境）', 'ktpwp' ),
                'icon' => 'dashicons-yes-alt',
                'color' => '#46b450',
                'info' => array_merge( $license_info, array(
                    'type' => 'development',
                    'environment' => 'development'
                ) )
            );
        }

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