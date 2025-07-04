<?php

class Kantan_Login_Error {

    public function __construct() {
        // 必要なアクションやフィルターを追加
        add_shortcode( 'ktpwp_login_error', array( $this, 'Error_View' ) );
    }

    // ログインしていない場合のエラービュー
    public function Error_View() {
        // ログインのリンク
        $login_link = esc_url( wp_login_url() );

        // 登録リンク（複数のパターンを試す）
        $registration_link = '';

        // パターン1: WordPressの標準登録URL
        if ( get_option( 'users_can_register' ) ) {
            $registration_link = esc_url( wp_registration_url() );
        }

        // パターン2: 登録が無効でも標準の登録URLを生成
        if ( empty( $registration_link ) ) {
            $registration_link = esc_url( site_url( 'wp-login.php?action=register' ) );
        }

        // ホームページのリンク
        $home_link = esc_url( home_url( '/' ) );

        // 表示する内容
        $content  = '<h3>' . esc_html__( 'KantanProを利用するにはログインしてください。', 'ktpwp' ) . '</h3>';
        $content .= '<!--ログイン-->';
        $content .= '<p>';
        $content .= '<font size="4"><a href="' . $login_link . '">' . esc_html__( 'ログイン', 'ktpwp' ) . '</a></font>';

        // 登録リンクは常に表示
        $content .= '　<font size="4"><a href="' . $registration_link . '">' . esc_html__( '登録', 'ktpwp' ) . '</a></font>';

        $content .= '　<font size="4"><a href="' . $home_link . '">' . esc_html__( 'ホームへ', 'ktpwp' ) . '</a></font>';
        $content .= '</p>';

        return $content;
    }
}
