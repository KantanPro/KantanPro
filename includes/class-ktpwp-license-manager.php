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
            return array(
                'success' => true,
                'data'    => $data['data'] ?? array(),
                'message' => $data['message'] ?? __( 'ライセンスが正常に認証されました。', 'ktpwp' )
            );
        } else {
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
        $license_key = get_option( 'ktp_license_key' );
        $license_status = get_option( 'ktp_license_status' );
        $verified_at = get_option( 'ktp_license_verified_at' );

        if ( empty( $license_key ) || $license_status !== 'active' ) {
            return false;
        }

        // Check if verification is older than 24 hours
        if ( $verified_at && ( current_time( 'timestamp' ) - $verified_at ) > 86400 ) {
            // Re-verify license
            $result = $this->verify_license( $license_key );
            if ( ! $result['success'] ) {
                update_option( 'ktp_license_status', 'invalid' );
                return false;
            }
            update_option( 'ktp_license_verified_at', current_time( 'timestamp' ) );
        }

        return true;
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

        if ( $license_status === 'active' && $this->is_license_valid() ) {
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
    }
}

// Initialize the license manager
KTPWP_License_Manager::get_instance(); 