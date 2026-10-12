<?php
/**
 * 請求項目テーブルに税率カラムを追加するマイグレーション
 * 実行日: 2025-01-31
 */

// 直接実行禁止
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ファイル内の変数をグローバルに出さないよう、処理全体を無名関数で包む（読み込んだ時点で実行される）。
( static function () {

global $wpdb;

// === ktpwp_order_invoice_items テーブルに税率カラムを追加 ===
$invoice_items_table = $wpdb->prefix . 'ktpwp_order_invoice_items';

if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $invoice_items_table ) ) == $invoice_items_table ) {
    
    // 既存のカラムを確認
    $existing_columns = $wpdb->get_col( $wpdb->prepare( 'SHOW COLUMNS FROM %i', $invoice_items_table ) );
    
    // 税率カラムが存在しない場合のみ追加
    if ( ! in_array( 'tax_rate', $existing_columns ) ) {
        $sql = $wpdb->prepare( "ALTER TABLE %i ADD COLUMN `tax_rate` DECIMAL(5,2) DEFAULT 10.00 COMMENT '税率（%%）' AFTER `amount`", $invoice_items_table );
        $result = $wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql は直前で $wpdb->prepare() 済み。
        
        if ( $result !== false ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                ktpwp_debug_log( "KTPWP Migration: Added tax_rate column to $invoice_items_table" );
            }
            
            // 既存データの税率を基本税率（10%）に設定
            $update_sql = $wpdb->prepare( "UPDATE %i SET `tax_rate` = 10.00 WHERE `tax_rate` IS NULL", $invoice_items_table );
            $update_result = $wpdb->query( $update_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $update_sql は直前で $wpdb->prepare() 済み。
            
            if ( $update_result !== false && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                ktpwp_debug_log( "KTPWP Migration: Updated existing invoice items with default tax rate" );
            }
        } else {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                ktpwp_debug_log( "KTPWP Migration: Failed to add tax_rate column to $invoice_items_table" );
            }
        }
    } else {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            ktpwp_debug_log( "KTPWP Migration: tax_rate column already exists in $invoice_items_table" );
        }
    }
}

// === ktpwp_order_cost_items テーブルにも税率カラムを追加 ===
$cost_items_table = $wpdb->prefix . 'ktpwp_order_cost_items';

if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $cost_items_table ) ) == $cost_items_table ) {
    
    // 既存のカラムを確認
    $existing_columns = $wpdb->get_col( $wpdb->prepare( 'SHOW COLUMNS FROM %i', $cost_items_table ) );
    
    // 税率カラムが存在しない場合のみ追加
    if ( ! in_array( 'tax_rate', $existing_columns ) ) {
        $sql = $wpdb->prepare( "ALTER TABLE %i ADD COLUMN `tax_rate` DECIMAL(5,2) DEFAULT 10.00 COMMENT '税率（%%）' AFTER `amount`", $cost_items_table );
        $result = $wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql は直前で $wpdb->prepare() 済み。
        
        if ( $result !== false ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                ktpwp_debug_log( "KTPWP Migration: Added tax_rate column to $cost_items_table" );
            }
            
            // 既存データの税率を基本税率（10%）に設定
            $update_sql = $wpdb->prepare( "UPDATE %i SET `tax_rate` = 10.00 WHERE `tax_rate` IS NULL", $cost_items_table );
            $update_result = $wpdb->query( $update_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $update_sql は直前で $wpdb->prepare() 済み。
            
            if ( $update_result !== false && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                ktpwp_debug_log( "KTPWP Migration: Updated existing cost items with default tax rate" );
            }
        } else {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                ktpwp_debug_log( "KTPWP Migration: Failed to add tax_rate column to $cost_items_table" );
            }
        }
    } else {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            ktpwp_debug_log( "KTPWP Migration: tax_rate column already exists in $cost_items_table" );
        }
    }
}
} )();
