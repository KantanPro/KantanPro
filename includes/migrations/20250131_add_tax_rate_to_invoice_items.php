<?php
/**
 * 請求項目テーブルに税率カラムを追加するマイグレーション
 * 実行日: 2025-01-31
 */

// 直接実行禁止
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;

// === ktp_order_invoice_items テーブルに税率カラムを追加 ===
$invoice_items_table = $wpdb->prefix . 'ktp_order_invoice_items';

if ( $wpdb->get_var( "SHOW TABLES LIKE '$invoice_items_table'" ) == $invoice_items_table ) {
    
    // 既存のカラムを確認
    $existing_columns = $wpdb->get_col( "SHOW COLUMNS FROM `$invoice_items_table`" );
    
    // 税率カラムが存在しない場合のみ追加
    if ( ! in_array( 'tax_rate', $existing_columns ) ) {
        $sql = "ALTER TABLE `$invoice_items_table` ADD COLUMN `tax_rate` DECIMAL(5,2) DEFAULT 10.00 COMMENT '税率（%）' AFTER `amount`";
        $result = $wpdb->query( $sql );
        
        if ( $result !== false ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( "KTPWP Migration: Added tax_rate column to $invoice_items_table" );
            }
            
            // 既存データの税率を基本税率（10%）に設定
            $update_sql = "UPDATE `$invoice_items_table` SET `tax_rate` = 10.00 WHERE `tax_rate` IS NULL";
            $update_result = $wpdb->query( $update_sql );
            
            if ( $update_result !== false && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( "KTPWP Migration: Updated existing invoice items with default tax rate" );
            }
        } else {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( "KTPWP Migration: Failed to add tax_rate column to $invoice_items_table" );
            }
        }
    } else {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( "KTPWP Migration: tax_rate column already exists in $invoice_items_table" );
        }
    }
}

// === ktp_order_cost_items テーブルにも税率カラムを追加 ===
$cost_items_table = $wpdb->prefix . 'ktp_order_cost_items';

if ( $wpdb->get_var( "SHOW TABLES LIKE '$cost_items_table'" ) == $cost_items_table ) {
    
    // 既存のカラムを確認
    $existing_columns = $wpdb->get_col( "SHOW COLUMNS FROM `$cost_items_table`" );
    
    // 税率カラムが存在しない場合のみ追加
    if ( ! in_array( 'tax_rate', $existing_columns ) ) {
        $sql = "ALTER TABLE `$cost_items_table` ADD COLUMN `tax_rate` DECIMAL(5,2) DEFAULT 10.00 COMMENT '税率（%）' AFTER `amount`";
        $result = $wpdb->query( $sql );
        
        if ( $result !== false ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( "KTPWP Migration: Added tax_rate column to $cost_items_table" );
            }
            
            // 既存データの税率を基本税率（10%）に設定
            $update_sql = "UPDATE `$cost_items_table` SET `tax_rate` = 10.00 WHERE `tax_rate` IS NULL";
            $update_result = $wpdb->query( $update_sql );
            
            if ( $update_result !== false && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( "KTPWP Migration: Updated existing cost items with default tax rate" );
            }
        } else {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( "KTPWP Migration: Failed to add tax_rate column to $cost_items_table" );
            }
        }
    } else {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( "KTPWP Migration: tax_rate column already exists in $cost_items_table" );
        }
    }
} 