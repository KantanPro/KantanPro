<?php
/**
 * KantanPro アンインストールスクリプト
 *
 * WordPress 管理画面の「プラグイン > 削除」実行時に自動的に呼び出される。
 *
 * 設定画面（KantanPro > 開発者設定 > アンインストール時の動作）で
 * 選択されたモードに従って動作する:
 *
 *   - keep_data   : データを保持（DBテーブル・設定値はそのまま残す）
 *   - full_delete : 完全削除（KantanPro 関連の全テーブル・オプション・
 *                   ユーザーメタ・トランジェント・クロンを削除）
 *
 * 配布先での新規インストール検証や、完全なアンインストールを行いたい
 * 場合に `full_delete` を選択すること。
 *
 * @package KTPWP
 */

// WordPress のアンインストール以外からの直接実行を禁止
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

/**
 * 現在のサイトの KantanPro データを削除する
 *
 * ktp_uninstall_settings.uninstall_mode が 'full_delete' のときのみ
 * 実データを削除する。'keep_data' の場合は何もしない。
 */
function ktpwp_perform_uninstall_for_current_site() {
    global $wpdb;

    // ユーザー選択の取得
    $settings = get_option( 'ktp_uninstall_settings', array() );
    $mode     = isset( $settings['uninstall_mode'] ) ? (string) $settings['uninstall_mode'] : 'keep_data';

    // データを残すモード: 何もせず終了
    if ( $mode !== 'full_delete' ) {
        return;
    }

    $prefix = $wpdb->prefix;

    /* -----------------------------------------------------------
     * 1) カスタムテーブルを DROP
     *    prefix_ktp_* で始まる全テーブルを対象にする
     * ----------------------------------------------------------- */
    $table_like = $wpdb->esc_like( $prefix . 'ktp_' ) . '%';
    $tables = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_like ) );

    if ( is_array( $tables ) && ! empty( $tables ) ) {
        foreach ( $tables as $table ) {
            // テーブル名はシステム内部由来で信頼できるが、念のためバッククォート処理
            $safe_table = '`' . str_replace( '`', '', $table ) . '`';
            $wpdb->query( "DROP TABLE IF EXISTS {$safe_table}" );
        }
    }

    /* -----------------------------------------------------------
     * 2) オプション削除
     *    KantanPro 系オプションは ktp_* / ktpwp_* プレフィックス。
     *    トランジェント（_transient_ktp_*, _transient_timeout_ktp_*）
     *    も併せて削除する。
     * ----------------------------------------------------------- */
    $like_ktp       = $wpdb->esc_like( 'ktp_' ) . '%';
    $like_ktpwp     = $wpdb->esc_like( 'ktpwp_' ) . '%';
    $like_trans1    = $wpdb->esc_like( '_transient_ktp_' ) . '%';
    $like_trans2    = $wpdb->esc_like( '_transient_timeout_ktp_' ) . '%';
    $like_trans3    = $wpdb->esc_like( '_transient_ktpwp_' ) . '%';
    $like_trans4    = $wpdb->esc_like( '_transient_timeout_ktpwp_' ) . '%';
    $like_site_opt1 = $wpdb->esc_like( '_site_transient_ktp_' ) . '%';
    $like_site_opt2 = $wpdb->esc_like( '_site_transient_timeout_ktp_' ) . '%';
    $like_site_opt3 = $wpdb->esc_like( '_site_transient_ktpwp_' ) . '%';
    $like_site_opt4 = $wpdb->esc_like( '_site_transient_timeout_ktpwp_' ) . '%';

    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE "
            . 'option_name LIKE %s OR option_name LIKE %s '
            . 'OR option_name LIKE %s OR option_name LIKE %s '
            . 'OR option_name LIKE %s OR option_name LIKE %s '
            . 'OR option_name LIKE %s OR option_name LIKE %s '
            . 'OR option_name LIKE %s OR option_name LIKE %s',
            $like_ktp,
            $like_ktpwp,
            $like_trans1,
            $like_trans2,
            $like_trans3,
            $like_trans4,
            $like_site_opt1,
            $like_site_opt2,
            $like_site_opt3,
            $like_site_opt4
        )
    );

    /* -----------------------------------------------------------
     * 3) ユーザーメタ削除
     *    ktp_ / ktpwp_ プレフィックスのユーザーメタを削除
     * ----------------------------------------------------------- */
    $meta_like1 = $wpdb->esc_like( 'ktp_' ) . '%';
    $meta_like2 = $wpdb->esc_like( 'ktpwp_' ) . '%';
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s OR meta_key LIKE %s",
            $meta_like1,
            $meta_like2
        )
    );

    /* -----------------------------------------------------------
     * 4) 投稿メタ削除（念のため）
     *    _ktp_ / ktp_ / ktpwp_ 系の投稿メタを削除
     * ----------------------------------------------------------- */
    $pm_like1 = $wpdb->esc_like( 'ktp_' ) . '%';
    $pm_like2 = $wpdb->esc_like( '_ktp_' ) . '%';
    $pm_like3 = $wpdb->esc_like( 'ktpwp_' ) . '%';
    $pm_like4 = $wpdb->esc_like( '_ktpwp_' ) . '%';
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->postmeta} WHERE "
            . 'meta_key LIKE %s OR meta_key LIKE %s OR meta_key LIKE %s OR meta_key LIKE %s',
            $pm_like1,
            $pm_like2,
            $pm_like3,
            $pm_like4
        )
    );

    /* -----------------------------------------------------------
     * 5) スケジュールされたクロンイベントの解除
     * ----------------------------------------------------------- */
    $cron_hooks = array(
        'ktp_check_updates',
        'ktp_cleanup_transients',
        'ktp_daily_maintenance',
        'ktp_central_banner_refresh',
        'ktpwp_check_updates',
        'ktpwp_license_check',
        'ktpwp_donation_notice_cleanup',
    );
    foreach ( $cron_hooks as $hook ) {
        if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
            wp_clear_scheduled_hook( $hook );
        }
    }
}

// ------------------------------------------------------------------
// マルチサイト対応: 全サブサイトで削除処理を実行する
// ------------------------------------------------------------------
if ( is_multisite() ) {
    $site_ids = get_sites(
        array(
            'fields' => 'ids',
            'number' => 0,
        )
    );
    if ( is_array( $site_ids ) ) {
        foreach ( $site_ids as $site_id ) {
            switch_to_blog( (int) $site_id );
            ktpwp_perform_uninstall_for_current_site();
            restore_current_blog();
        }
    }
} else {
    ktpwp_perform_uninstall_for_current_site();
}
