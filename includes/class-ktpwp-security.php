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

    /**
     * 初期化
     */
    public function init() {
        $this->init_hooks();
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
}
