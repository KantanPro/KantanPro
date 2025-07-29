<?php
/**
 * 税率フィールドをNULL許可にして非課税取引に対応
 * 実行日: 2025-01-22
 */

// 直接実行禁止
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;

// === ktp_order_invoice_items テーブルの税率フィールド修正 ===
$invoice_table = $wpdb->prefix . 'ktp_order_invoice_items';
if ( $wpdb->get_var( "SHOW TABLES LIKE '$invoice_table'" ) == $invoice_table ) {
    
    // 税率フィールドをNULL許可に変更
    $sql = "ALTER TABLE `$invoice_table` MODIFY `tax_rate` DECIMAL(5,2) NULL DEFAULT NULL COMMENT '税率（NULL=非課税）'";
    $result = $wpdb->query( $sql );
    
    if ( $result !== false ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( "KTPWP Migration: Modified tax_rate column in $invoice_table to allow NULL" );
        }
    } else {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( "KTPWP Migration: Failed to modify tax_rate column in $invoice_table" );
        }
    }
}

// === ktp_order_cost_items テーブルの税率フィールド修正 ===
$cost_table = $wpdb->prefix . 'ktp_order_cost_items';
if ( $wpdb->get_var( "SHOW TABLES LIKE '$cost_table'" ) == $cost_table ) {
    
    // 税率フィールドをNULL許可に変更
    $sql = "ALTER TABLE `$cost_table` MODIFY `tax_rate` DECIMAL(5,2) NULL DEFAULT NULL COMMENT '税率（NULL=非課税）'";
    $result = $wpdb->query( $sql );
    
    if ( $result !== false ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( "KTPWP Migration: Modified tax_rate column in $cost_table to allow NULL" );
        }
    } else {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( "KTPWP Migration: Failed to modify tax_rate column in $cost_table" );
        }
    }
}

// === ktp_service テーブルの税率フィールド修正 ===
$service_table = $wpdb->prefix . 'ktp_service';
if ( $wpdb->get_var( "SHOW TABLES LIKE '$service_table'" ) == $service_table ) {
    
    // 税率フィールドをNULL許可に変更
    $sql = "ALTER TABLE `$service_table` MODIFY `tax_rate` DECIMAL(5,2) NULL DEFAULT NULL COMMENT '税率（NULL=非課税）'";
    $result = $wpdb->query( $sql );
    
    if ( $result !== false ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( "KTPWP Migration: Modified tax_rate column in $service_table to allow NULL" );
        }
    } else {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( "KTPWP Migration: Failed to modify tax_rate column in $service_table" );
        }
    }
}

// === ktp_order_invoice_items テーブルに税率フィールドが存在しない場合の追加 ===
$invoice_columns = $wpdb->get_col( "SHOW COLUMNS FROM `$invoice_table`" );
if ( ! in_array( 'tax_rate', $invoice_columns ) ) {
    $sql = "ALTER TABLE `$invoice_table` ADD COLUMN `tax_rate` DECIMAL(5,2) NULL DEFAULT NULL COMMENT '税率（NULL=非課税）'";
    $result = $wpdb->query( $sql );
    
    if ( $result !== false ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( "KTPWP Migration: Added tax_rate column to $invoice_table" );
        }
    }
}

// === ktp_order_cost_items テーブルに税率フィールドが存在しない場合の追加 ===
$cost_columns = $wpdb->get_col( "SHOW COLUMNS FROM `$cost_table`" );
if ( ! in_array( 'tax_rate', $cost_columns ) ) {
    $sql = "ALTER TABLE `$cost_table` ADD COLUMN `tax_rate` DECIMAL(5,2) NULL DEFAULT NULL COMMENT '税率（NULL=非課税）'";
    $result = $wpdb->query( $sql );
    
    if ( $result !== false ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( "KTPWP Migration: Added tax_rate column to $cost_table" );
        }
    }
}

// === ktp_service テーブルに税率フィールドが存在しない場合の追加 ===
$service_columns = $wpdb->get_col( "SHOW COLUMNS FROM `$service_table`" );
if ( ! in_array( 'tax_rate', $service_columns ) ) {
    $sql = "ALTER TABLE `$service_table` ADD COLUMN `tax_rate` DECIMAL(5,2) NULL DEFAULT NULL COMMENT '税率（NULL=非課税）'";
    $result = $wpdb->query( $sql );
    
    if ( $result !== false ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( "KTPWP Migration: Added tax_rate column to $service_table" );
        }
    }
}

if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
    error_log( "KTPWP Migration: Tax rate NULL support migration completed" );
} 