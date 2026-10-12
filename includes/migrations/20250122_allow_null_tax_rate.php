<?php
/**
 * 税率フィールドをNULL許可にして非課税取引に対応
 * 実行日: 2025-01-22
 */

// 直接実行禁止
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ファイル内の変数をグローバルに出さないよう、処理全体を無名関数で包む（読み込んだ時点で実行される）。
( static function () {

global $wpdb;

// === ktpwp_order_invoice_items テーブルの税率フィールド修正 ===
$invoice_table = $wpdb->prefix . 'ktpwp_order_invoice_items';
if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $invoice_table ) ) == $invoice_table ) {
    
    // 税率フィールドをNULL許可に変更
    $sql = $wpdb->prepare( "ALTER TABLE %i MODIFY `tax_rate` DECIMAL(5,2) NULL DEFAULT NULL COMMENT '税率（NULL=非課税）'", $invoice_table );
    $result = $wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql は直前で $wpdb->prepare() 済み。
    
    if ( $result !== false ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            ktpwp_debug_log( "KTPWP Migration: Modified tax_rate column in $invoice_table to allow NULL" );
        }
    } else {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            ktpwp_debug_log( "KTPWP Migration: Failed to modify tax_rate column in $invoice_table" );
        }
    }
}

// === ktpwp_order_cost_items テーブルの税率フィールド修正 ===
$cost_table = $wpdb->prefix . 'ktpwp_order_cost_items';
if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $cost_table ) ) == $cost_table ) {
    
    // 税率フィールドをNULL許可に変更
    $sql = $wpdb->prepare( "ALTER TABLE %i MODIFY `tax_rate` DECIMAL(5,2) NULL DEFAULT NULL COMMENT '税率（NULL=非課税）'", $cost_table );
    $result = $wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql は直前で $wpdb->prepare() 済み。
    
    if ( $result !== false ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            ktpwp_debug_log( "KTPWP Migration: Modified tax_rate column in $cost_table to allow NULL" );
        }
    } else {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            ktpwp_debug_log( "KTPWP Migration: Failed to modify tax_rate column in $cost_table" );
        }
    }
}

// === ktpwp_service テーブルの税率フィールド修正 ===
$service_table = $wpdb->prefix . 'ktpwp_service';
if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $service_table ) ) == $service_table ) {
    
    // 税率フィールドをNULL許可に変更
    $sql = $wpdb->prepare( "ALTER TABLE %i MODIFY `tax_rate` DECIMAL(5,2) NULL DEFAULT NULL COMMENT '税率（NULL=非課税）'", $service_table );
    $result = $wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql は直前で $wpdb->prepare() 済み。
    
    if ( $result !== false ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            ktpwp_debug_log( "KTPWP Migration: Modified tax_rate column in $service_table to allow NULL" );
        }
    } else {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            ktpwp_debug_log( "KTPWP Migration: Failed to modify tax_rate column in $service_table" );
        }
    }
}

// === ktpwp_order_invoice_items テーブルに税率フィールドが存在しない場合の追加 ===
$invoice_columns = $wpdb->get_col( $wpdb->prepare( 'SHOW COLUMNS FROM %i', $invoice_table ) );
if ( ! in_array( 'tax_rate', $invoice_columns ) ) {
    $sql = $wpdb->prepare( "ALTER TABLE %i ADD COLUMN `tax_rate` DECIMAL(5,2) NULL DEFAULT NULL COMMENT '税率（NULL=非課税）'", $invoice_table );
    $result = $wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql は直前で $wpdb->prepare() 済み。
    
    if ( $result !== false ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            ktpwp_debug_log( "KTPWP Migration: Added tax_rate column to $invoice_table" );
        }
    }
}

// === ktpwp_order_cost_items テーブルに税率フィールドが存在しない場合の追加 ===
$cost_columns = $wpdb->get_col( $wpdb->prepare( 'SHOW COLUMNS FROM %i', $cost_table ) );
if ( ! in_array( 'tax_rate', $cost_columns ) ) {
    $sql = $wpdb->prepare( "ALTER TABLE %i ADD COLUMN `tax_rate` DECIMAL(5,2) NULL DEFAULT NULL COMMENT '税率（NULL=非課税）'", $cost_table );
    $result = $wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql は直前で $wpdb->prepare() 済み。
    
    if ( $result !== false ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            ktpwp_debug_log( "KTPWP Migration: Added tax_rate column to $cost_table" );
        }
    }
}

// === ktpwp_service テーブルに税率フィールドが存在しない場合の追加 ===
$service_columns = $wpdb->get_col( $wpdb->prepare( 'SHOW COLUMNS FROM %i', $service_table ) );
if ( ! in_array( 'tax_rate', $service_columns ) ) {
    $sql = $wpdb->prepare( "ALTER TABLE %i ADD COLUMN `tax_rate` DECIMAL(5,2) NULL DEFAULT NULL COMMENT '税率（NULL=非課税）'", $service_table );
    $result = $wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql は直前で $wpdb->prepare() 済み。
    
    if ( $result !== false ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            ktpwp_debug_log( "KTPWP Migration: Added tax_rate column to $service_table" );
        }
    }
}

if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
    ktpwp_debug_log( "KTPWP Migration: Tax rate NULL support migration completed" );
}
} )();
