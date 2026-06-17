<?php
/**
 * セキュリティ管理クラス
 *
 * プラグインのセキュリティ機能を管理
 *
 * @package KTPWP
 * @since 1.0.0
 */

// セキュリティ: 直接アクセスを防止
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * セキュリティ管理クラス
 */
class KTPWP_Security {

    /** @var self|null */
    private static $instance = null;

    /**
     * @return self
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * 初期化
     */
    public function init() {
        $this->init_hooks();
    }

    /**
     * 管理画面アクセス保護のみ初期化（REST 制限等は ktpwp.php 側で管理）。
     */
    public function boot_admin_access_protection() {
        add_action( 'init', array( $this, 'enforce_admin_access' ), 0 );
    }

    /**
     * フック初期化
     */
    private function init_hooks() {
        // REST API制限
        add_filter( 'rest_authentication_errors', array( $this, 'restrict_rest_api' ) );

        // HTTPセキュリティヘッダー
        add_action( 'admin_init', array( $this, 'add_security_headers' ) );

        // ファイルアップロード制限
        add_filter( 'upload_mimes', array( $this, 'restrict_upload_types' ) );

        // セキュリティ関連のショートコード無効化
        add_action( 'init', array( $this, 'disable_dangerous_shortcodes' ) );
    }

    /**
     * REST API制限
     *
     * @param WP_Error|null|true $result Authentication result.
     * @return WP_Error|null|true
     */
    public function restrict_rest_api( $result ) {
        if ( ! empty( $result ) ) {
            return $result;
        }

        $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';
        if ( strpos( $request_uri, '/wp-json/' ) !== false ) {
            return $result;
        }

        if ( ! is_user_logged_in() ) {
            return new WP_Error(
                'rest_forbidden',
                __( 'REST APIはログインユーザーのみ利用可能です。', 'ktpwp' ),
                array( 'status' => 403 )
            );
        }

        return $result;
    }

    /**
     * HTTPセキュリティヘッダー追加
     */
    public function add_security_headers() {
        if ( is_admin() && ! wp_doing_ajax() ) {
            if ( ! headers_sent() ) {
                // クリックジャッキング防止
                header( 'X-Frame-Options: SAMEORIGIN' );
                // XSS対策
                header( 'X-Content-Type-Options: nosniff' );
                // Referrer情報制御
                header( 'Referrer-Policy: no-referrer-when-downgrade' );
                // XSS Protection
                header( 'X-XSS-Protection: 1; mode=block' );
            }
        }
    }

    /**
     * ファイルアップロード制限
     *
     * @param array $mime_types 許可されるMIMEタイプ
     * @return array
     */
    public function restrict_upload_types( $mime_types ) {
        // 危険なファイルタイプを削除
        unset( $mime_types['exe'] );
        unset( $mime_types['bat'] );
        unset( $mime_types['cmd'] );
        unset( $mime_types['com'] );
        unset( $mime_types['pif'] );
        unset( $mime_types['scr'] );
        unset( $mime_types['vbs'] );
        unset( $mime_types['php'] );

        return $mime_types;
    }

    /**
     * 危険なショートコード無効化
     */
    public function disable_dangerous_shortcodes() {
        // 一般的に危険とされるショートコードを無効化
        remove_shortcode( 'php' );
        remove_shortcode( 'exec' );
        remove_shortcode( 'eval' );
    }

    /**
     * ユーザー入力のサニタイズ
     *
     * @param mixed  $input 入力値
     * @param string $type サニタイズタイプ
     * @return mixed サニタイズされた値
     */
    public function sanitize_input( $input, $type = 'text' ) {
        switch ( $type ) {
            case 'email':
                return sanitize_email( $input );
            case 'url':
                return esc_url_raw( $input );
            case 'textarea':
                return sanitize_textarea_field( $input );
            case 'html':
                return wp_kses_post( $input );
            case 'int':
                return intval( $input );
            case 'float':
                return floatval( $input );
            case 'key':
                return sanitize_key( $input );
            case 'title':
                return sanitize_title( $input );
            case 'text':
            default:
                return sanitize_text_field( $input );
        }
    }

    /**
     * nonceの生成
     *
     * @param string $action アクション名
     * @return string nonce値
     */
    public function create_nonce( $action ) {
        return wp_create_nonce( 'ktpwp_' . $action );
    }

    /**
     * nonceの検証
     *
     * @param string $nonce nonce値
     * @param string $action アクション名
     * @return bool 検証結果
     */
    public function verify_nonce( $nonce, $action ) {
        return wp_verify_nonce( $nonce, 'ktpwp_' . $action );
    }

    /**
     * 管理者権限チェック
     *
     * @return bool
     */
    public function check_admin_capability() {
        return current_user_can( 'manage_options' );
    }

    /**
     * 編集権限チェック
     *
     * @return bool
     */
    public function check_edit_capability() {
        return current_user_can( 'edit_posts' );
    }

    /**
     * IPアドレスの取得
     *
     * @return string
     */
    public function get_client_ip() {
        $ip_keys = array(
            'HTTP_CF_CONNECTING_IP',
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR',
        );

        foreach ( $ip_keys as $key ) {
            if ( array_key_exists( $key, $_SERVER ) === true ) {
                foreach ( explode( ',', $_SERVER[ $key ] ) as $ip ) {
                    $ip = trim( $ip );

                    if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) !== false ) {
                        return $ip;
                    }
                }
            }
        }

        return isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '';
    }

    /**
     * ログイン試行制限チェック
     *
     * @param string $username ユーザー名
     * @return bool 制限に引っかかっているかどうか
     */
    public function is_login_blocked( $username ) {
        $attempts_key = 'ktpwp_login_attempts_' . sanitize_key( $username );
        $attempts = get_transient( $attempts_key );

        // 5回以上失敗している場合は15分間ブロック
        if ( $attempts && $attempts >= 5 ) {
            return true;
        }

        return false;
    }

    /**
     * ログイン試行回数を記録
     *
     * @param string $username ユーザー名
     */
    public function record_login_attempt( $username ) {
        $attempts_key = 'ktpwp_login_attempts_' . sanitize_key( $username );
        $attempts = get_transient( $attempts_key );
        $attempts = $attempts ? $attempts + 1 : 1;

        // 15分間保持
        set_transient( $attempts_key, $attempts, 15 * MINUTE_IN_SECONDS );
    }

    /**
     * ログイン試行回数をリセット
     *
     * @param string $username ユーザー名
     */
    public function reset_login_attempts( $username ) {
        $attempts_key = 'ktpwp_login_attempts_' . sanitize_key( $username );
        delete_transient( $attempts_key );
    }

    /**
     * wp-admin / wp-login.php へのアクセスを IP 制限・Basic 認証で保護。
     */
    public function enforce_admin_access() {
        if ( ! $this->is_admin_access_protection_active() || ! $this->is_protected_admin_request() ) {
            return;
        }

        if ( $this->is_ip_restriction_enabled() && $this->is_client_ip_allowed() ) {
            return;
        }

        if ( $this->is_basic_auth_enabled() && $this->verify_basic_auth_credentials() ) {
            return;
        }

        if ( $this->is_basic_auth_enabled() ) {
            $this->send_basic_auth_challenge();
        }

        $this->deny_admin_access();
    }

    /**
     * @return bool
     */
    public function is_admin_access_protection_active() {
        return $this->is_ip_restriction_enabled() || $this->is_basic_auth_enabled();
    }

    /**
     * @return bool
     */
    public function is_ip_restriction_enabled() {
        return class_exists( 'KTPWP_Settings' )
            && KTPWP_Settings::get_setting( 'admin_ip_restriction_enabled', '0' ) === '1';
    }

    /**
     * @return bool
     */
    public function is_basic_auth_enabled() {
        return class_exists( 'KTPWP_Settings' )
            && KTPWP_Settings::get_setting( 'admin_basic_auth_enabled', '0' ) === '1'
            && $this->get_basic_auth_username() !== ''
            && $this->get_basic_auth_password_hash() !== '';
    }

    /**
     * @return bool
     */
    private function is_protected_admin_request() {
        if ( ( defined( 'DOING_CRON' ) && DOING_CRON ) || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
            return false;
        }

        $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
        $path        = (string) parse_url( $request_uri, PHP_URL_PATH );

        if ( $path === '' ) {
            return false;
        }

        if ( false !== strpos( $path, 'wp-login.php' ) ) {
            return true;
        }

        if ( false !== strpos( $path, '/wp-admin' ) && false === strpos( $path, 'admin-ajax.php' ) ) {
            return true;
        }

        return false;
    }

    /**
     * @return bool
     */
    public function is_client_ip_allowed() {
        $allowed_ips = $this->get_allowed_ips();
        if ( empty( $allowed_ips ) ) {
            return false;
        }

        $client_ip = $this->get_client_ip();
        if ( $client_ip === '' ) {
            return false;
        }

        foreach ( $allowed_ips as $allowed_ip ) {
            if ( $this->ip_matches( $client_ip, $allowed_ip ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    public function get_allowed_ips() {
        if ( ! class_exists( 'KTPWP_Settings' ) ) {
            return array();
        }

        $raw = (string) KTPWP_Settings::get_setting( 'admin_allowed_ips', '' );
        $ips = preg_split( '/\r\n|\r|\n/', $raw );
        if ( ! is_array( $ips ) ) {
            return array();
        }

        $allowed = array();
        foreach ( $ips as $ip ) {
            $ip = trim( (string) $ip );
            if ( $ip === '' || ! $this->is_valid_ip_rule( $ip ) ) {
                continue;
            }
            $allowed[] = $ip;
        }

        return $allowed;
    }

    /**
     * @param string $rule IP または CIDR。
     * @return bool
     */
    private function is_valid_ip_rule( $rule ) {
        if ( false !== strpos( $rule, '/' ) ) {
            list( $subnet, $mask ) = array_pad( explode( '/', $rule, 2 ), 2, '' );
            if ( ! filter_var( trim( $subnet ), FILTER_VALIDATE_IP ) ) {
                return false;
            }

            $mask = (int) $mask;
            if ( false !== filter_var( trim( $subnet ), FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
                return $mask >= 0 && $mask <= 32;
            }

            return $mask >= 0 && $mask <= 128;
        }

        return (bool) filter_var( $rule, FILTER_VALIDATE_IP );
    }

    /**
     * @param string $ip     クライアント IP。
     * @param string $pattern 許可 IP / CIDR。
     * @return bool
     */
    private function ip_matches( $ip, $pattern ) {
        $pattern = trim( $pattern );
        if ( $ip === '' || $pattern === '' ) {
            return false;
        }

        if ( false === strpos( $pattern, '/' ) ) {
            return $ip === $pattern;
        }

        list( $subnet, $mask ) = array_pad( explode( '/', $pattern, 2 ), 2, '' );
        $subnet = trim( $subnet );
        $mask   = (int) $mask;

        if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) || ! filter_var( $subnet, FILTER_VALIDATE_IP ) ) {
            return false;
        }

        if ( false !== filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
            $ip_long      = ip2long( $ip );
            $subnet_long  = ip2long( $subnet );
            if ( false === $ip_long || false === $subnet_long || $mask < 0 || $mask > 32 ) {
                return false;
            }
            $wildcard = ( 0xFFFFFFFF << ( 32 - $mask ) ) & 0xFFFFFFFF;

            return ( $ip_long & $wildcard ) === ( $subnet_long & $wildcard );
        }

        if ( $mask < 0 || $mask > 128 || ! function_exists( 'inet_pton' ) ) {
            return false;
        }

        $ip_bin     = inet_pton( $ip );
        $subnet_bin = inet_pton( $subnet );
        if ( false === $ip_bin || false === $subnet_bin ) {
            return false;
        }

        $bytes = (int) floor( $mask / 8 );
        $bits  = $mask % 8;

        if ( $bytes > 0 && substr( $ip_bin, 0, $bytes ) !== substr( $subnet_bin, 0, $bytes ) ) {
            return false;
        }

        if ( $bits === 0 ) {
            return true;
        }

        $mask_byte = ( 0xFF << ( 8 - $bits ) ) & 0xFF;

        return ( ord( $ip_bin[ $bytes ] ) & $mask_byte ) === ( ord( $subnet_bin[ $bytes ] ) & $mask_byte );
    }

    /**
     * @return bool
     */
    private function verify_basic_auth_credentials() {
        $username = $this->get_basic_auth_request_username();
        $password = $this->get_basic_auth_request_password();
        if ( $username === '' || $password === '' ) {
            return false;
        }

        if ( $username !== $this->get_basic_auth_username() ) {
            return false;
        }

        $hash = $this->get_basic_auth_password_hash();
        if ( $hash === '' ) {
            return false;
        }

        return wp_check_password( $password, $hash );
    }

    /**
     * @return string
     */
    private function get_basic_auth_request_username() {
        if ( isset( $_SERVER['PHP_AUTH_USER'] ) ) {
            return sanitize_user( wp_unslash( (string) $_SERVER['PHP_AUTH_USER'] ), true );
        }

        $header = $this->get_authorization_header();
        if ( $header !== '' && 0 === stripos( $header, 'Basic ' ) ) {
            $decoded = base64_decode( substr( $header, 6 ), true );
            if ( is_string( $decoded ) && false !== strpos( $decoded, ':' ) ) {
                list( $user ) = explode( ':', $decoded, 2 );
                return sanitize_user( $user, true );
            }
        }

        return '';
    }

    /**
     * @return string
     */
    private function get_basic_auth_request_password() {
        if ( isset( $_SERVER['PHP_AUTH_PW'] ) ) {
            return (string) wp_unslash( $_SERVER['PHP_AUTH_PW'] );
        }

        $header = $this->get_authorization_header();
        if ( $header !== '' && 0 === stripos( $header, 'Basic ' ) ) {
            $decoded = base64_decode( substr( $header, 6 ), true );
            if ( is_string( $decoded ) && false !== strpos( $decoded, ':' ) ) {
                $parts = explode( ':', $decoded, 2 );
                return isset( $parts[1] ) ? (string) $parts[1] : '';
            }
        }

        return '';
    }

    /**
     * @return string
     */
    private function get_authorization_header() {
        if ( isset( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
            return trim( (string) wp_unslash( $_SERVER['HTTP_AUTHORIZATION'] ) );
        }

        if ( isset( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) ) {
            return trim( (string) wp_unslash( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) );
        }

        return '';
    }

    /**
     * @return string
     */
    private function get_basic_auth_username() {
        if ( ! class_exists( 'KTPWP_Settings' ) ) {
            return '';
        }

        return sanitize_user( (string) KTPWP_Settings::get_setting( 'admin_basic_auth_user', '' ), true );
    }

    /**
     * @return string
     */
    private function get_basic_auth_password_hash() {
        if ( ! class_exists( 'KTPWP_Settings' ) ) {
            return '';
        }

        return (string) KTPWP_Settings::get_setting( 'admin_basic_auth_pass', '' );
    }

    /**
     * Basic 認証ダイアログを表示。
     */
    private function send_basic_auth_challenge() {
        if ( ! headers_sent() ) {
            status_header( 401 );
            header( 'WWW-Authenticate: Basic realm="' . esc_attr__( 'KantanProEX 管理画面', 'ktpwp' ) . '"' );
            header( 'Content-Type: text/plain; charset=UTF-8' );
        }

        echo esc_html__( '認証が必要です。', 'ktpwp' );
        exit;
    }

    /**
     * 管理画面アクセス拒否。
     */
    private function deny_admin_access() {
        if ( ! headers_sent() ) {
            status_header( 403 );
            header( 'Content-Type: text/plain; charset=UTF-8' );
        }

        echo esc_html__( 'この管理画面へのアクセスは許可されていません。', 'ktpwp' );
        exit;
    }
}
