<?php
/**
 * サービステーブルに税率カラムを追加し、NULLを許可するマイグレーション
 * 実行日: 2025-01-31
 */

// 直接実行禁止
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;

// === ktp_service テーブルの修正 ===
$service_table = $wpdb->prefix . 'ktp_service';
if ( $wpdb->get_var( "SHOW TABLES LIKE '$service_table'" ) == $service_table ) {

    $existing_columns = $wpdb->get_col( "SHOW COLUMNS FROM `$service_table`" );

    // tax_rateカラムが存在しない場合は追加
    if ( ! in_array( 'tax_rate', $existing_columns ) ) {
        $sql = "ALTER TABLE `$service_table` ADD COLUMN `tax_rate` DECIMAL(5,2) NULL DEFAULT NULL COMMENT '税率（%）'";
        $result = $wpdb->query( $sql );

        if ( $result !== false ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( "KTPWP Migration: Added tax_rate column to $service_table" );
            }
            
            // 既存のレコードにデフォルト税率を設定（オプション）
            $update_sql = "UPDATE `$service_table` SET `tax_rate` = 10.00 WHERE `tax_rate` IS NULL";
            $update_result = $wpdb->query( $update_sql );
            
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( "KTPWP Migration: Updated existing records with default tax rate in $service_table" );
            }
        } else {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( "KTPWP Migration: Failed to add tax_rate column to $service_table" );
            }
        }
    } else {
        // tax_rateカラムが既に存在する場合は、NULLを許可するように修正
        $column_info = $wpdb->get_row( "SHOW COLUMNS FROM `$service_table` LIKE 'tax_rate'" );
        if ( $column_info && $column_info->Null !== 'YES' ) {
            $sql = "ALTER TABLE `$service_table` MODIFY COLUMN `tax_rate` DECIMAL(5,2) NULL DEFAULT NULL COMMENT '税率（%）'";
            $result = $wpdb->query( $sql );

            if ( $result !== false && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( "KTPWP Migration: Modified tax_rate column to allow NULL in $service_table" );
            }
        }
    }
}

// マイグレーション完了の記録
update_option( 'ktp_service_tax_rate_migration_completed', '2025-01-31' );

if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
    error_log( "KTPWP Migration: Service tax rate migration completed successfully" );
} 