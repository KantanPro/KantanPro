<?php
/**
 * サービステーブルに税率カラムを追加し、NULLを許可するマイグレーション
 * 実行日: 2025-01-31
 */

// 直接実行禁止
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ファイル内の変数をグローバルに出さないよう、処理全体を無名関数で包む（読み込んだ時点で実行される）。
( static function () {

global $wpdb;

// === ktpwp_service テーブルの修正 ===
$service_table = $wpdb->prefix . 'ktpwp_service';
if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $service_table ) ) == $service_table ) {

    $existing_columns = $wpdb->get_col( $wpdb->prepare( 'SHOW COLUMNS FROM %i', $service_table ) );

    // tax_rateカラムが存在しない場合は追加
    if ( ! in_array( 'tax_rate', $existing_columns ) ) {
        $sql = $wpdb->prepare( "ALTER TABLE %i ADD COLUMN `tax_rate` DECIMAL(5,2) NULL DEFAULT NULL COMMENT '税率（%%）'", $service_table );
        $result = $wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql は直前で $wpdb->prepare() 済み。

        if ( $result !== false ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                ktpwp_debug_log( "KTPWP Migration: Added tax_rate column to $service_table" );
            }
            
            // 既存のレコードにデフォルト税率を設定（オプション）
            $update_sql = $wpdb->prepare( "UPDATE %i SET `tax_rate` = 10.00 WHERE `tax_rate` IS NULL", $service_table );
            $update_result = $wpdb->query( $update_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $update_sql は直前で $wpdb->prepare() 済み。
            
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                ktpwp_debug_log( "KTPWP Migration: Updated existing records with default tax rate in $service_table" );
            }
        } else {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                ktpwp_debug_log( "KTPWP Migration: Failed to add tax_rate column to $service_table" );
            }
        }
    } else {
        // tax_rateカラムが既に存在する場合は、NULLを許可するように修正
        $column_info = $wpdb->get_row( $wpdb->prepare( 'SHOW COLUMNS FROM %i LIKE %s', $service_table, 'tax_rate' ) );
        if ( $column_info && $column_info->Null !== 'YES' ) {
            $sql = $wpdb->prepare( "ALTER TABLE %i MODIFY COLUMN `tax_rate` DECIMAL(5,2) NULL DEFAULT NULL COMMENT '税率（%%）'", $service_table );
            $result = $wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql は直前で $wpdb->prepare() 済み。

            if ( $result !== false && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                ktpwp_debug_log( "KTPWP Migration: Modified tax_rate column to allow NULL in $service_table" );
            }
        }
    }
}

// マイグレーション完了の記録
update_option( 'ktpwp_service_tax_rate_migration_completed', '2025-01-31' );

if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
    ktpwp_debug_log( "KTPWP Migration: Service tax rate migration completed successfully" );
}
} )();
