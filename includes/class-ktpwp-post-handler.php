<?php
/**
 * KTPWP POST データの安全な処理用ユーティリティクラス
 *
 * Adminer警告「Trying to access array offset on null」の
 * 完全な解決を目的とした堅牢なPOSTデータ処理
 *
 * @package KTPWP
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'KTPWP_Post_Data_Handler' ) ) {

    /**
     * POST データの安全な処理クラス
     */
    class KTPWP_Post_Data_Handler {

        /**
         * 安全なPOSTデータ取得
         *
         * @param string $key POSTキー
         * @param mixed  $default デフォルト値
         * @param string $sanitize_type サニタイズタイプ
         * @return mixed サニタイズされた値
         */
        public static function get_post_data( $key, $default = '', $sanitize_type = 'text' ) {
            // $_POST配列の存在と有効性を確認
            if ( ! is_array( $_POST ) || empty( $_POST ) || ! isset( $_POST[ $key ] ) ) {
                return $default;
            }

            $value = $_POST[ $key ];

            // null値の明示的チェック
            if ( $value === null ) {
                return $default;
            }

            // サニタイズ処理
            switch ( $sanitize_type ) {
                case 'int':
                    return is_numeric( $value ) ? intval( $value ) : intval( $default );

                case 'float':
                    return is_numeric( $value ) ? floatval( $value ) : floatval( $default );

                case 'email':
                    return sanitize_email( $value );

                case 'textarea':
                    return sanitize_textarea_field( wp_unslash( $value ) );

                case 'url':
                    return esc_url_raw( $value );

                case 'key':
                    return sanitize_key( $value );

                case 'text':
                default:
                    return sanitize_text_field( wp_unslash( $value ) );
            }
        }

        /**
         * 複数のPOSTデータを安全に取得
         *
         * @param array $keys キーと設定の配列
         * @return array サニタイズされたデータ配列
         */
        public static function get_multiple_post_data( $keys ) {
            $result = array();

            foreach ( $keys as $key => $config ) {
                if ( is_string( $config ) ) {
                    // 単純な形式: 'key' => 'sanitize_type'
                    $result[ $key ] = self::get_post_data( $key, '', $config );
                } elseif ( is_array( $config ) ) {
                    // 詳細な形式: 'key' => array('default' => '', 'type' => 'text')
                    $default = isset( $config['default'] ) ? $config['default'] : '';
                    $type = isset( $config['type'] ) ? $config['type'] : 'text';
                    $result[ $key ] = self::get_post_data( $key, $default, $type );
                }
            }

            return $result;
        }

        /**
         * POSTデータの配列存在チェック
         *
         * @return bool $_POST配列が有効かどうか
         */
        public static function is_post_valid() {
            return is_array( $_POST ) && ! empty( $_POST );
        }

        /**
         * 特定のPOSTキーの存在チェック
         *
         * @param string|array $keys チェックするキー（複数可）
         * @return bool 全てのキーが存在するかどうか
         */
        public static function has_post_keys( $keys ) {
            if ( ! self::is_post_valid() ) {
                return false;
            }

            if ( is_string( $keys ) ) {
                $keys = array( $keys );
            }

            foreach ( $keys as $key ) {
                if ( ! isset( $_POST[ $key ] ) || $_POST[ $key ] === null ) {
                    return false;
                }
            }

            return true;
        }

        /**
         * POSTデータのログ出力（デバッグ用）
         *
         * @param string $context ログコンテキスト
         */
        public static function log_post_data( $context = 'POST_DATA' ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( "[$context] POST data: " . print_r( $_POST, true ) );
                error_log( "[$context] POST is_array: " . ( is_array( $_POST ) ? 'true' : 'false' ) );
                error_log( "[$context] POST empty: " . ( empty( $_POST ) ? 'true' : 'false' ) );
            }
        }
    }
}
