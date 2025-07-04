<?php
/**
 * 本番環境向け追加マイグレーション: データベース構造の修正
 * 実行日: 2024-07-03
 */

// 直接実行禁止
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;

// === ktp_invoice_items テーブルの修正 ===
$invoice_table = $wpdb->prefix . 'ktp_invoice_items';
if ( $wpdb->get_var( "SHOW TABLES LIKE '$invoice_table'" ) == $invoice_table ) {

    $existing_columns = $wpdb->get_col( "SHOW COLUMNS FROM `$invoice_table`" );

    // 必要なカラムの追加
    $invoice_columns = array(
        'unit_price' => "DECIMAL(10,2) DEFAULT 0.00 COMMENT '単価'",
        'quantity' => "INT DEFAULT 1 COMMENT '数量'",
        'total_amount' => "DECIMAL(10,2) DEFAULT 0.00 COMMENT '合計金額'",
        'tax_rate' => "DECIMAL(5,2) DEFAULT 10.00 COMMENT '税率'",
        'created_at' => "TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時'",
        'updated_at' => "TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日時'",
    );

    foreach ( $invoice_columns as $column_name => $column_definition ) {
        if ( ! in_array( $column_name, $existing_columns ) ) {
            $sql = "ALTER TABLE `$invoice_table` ADD COLUMN `$column_name` $column_definition";
            $result = $wpdb->query( $sql );

            if ( $result !== false && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( "KTPWP Migration: Added column '$column_name' to $invoice_table" );
            }
        }
    }
}

// === ktp_cost_items テーブルの修正 ===
$cost_table = $wpdb->prefix . 'ktp_cost_items';
if ( $wpdb->get_var( "SHOW TABLES LIKE '$cost_table'" ) == $cost_table ) {

    $existing_columns = $wpdb->get_col( "SHOW COLUMNS FROM `$cost_table`" );

    // 必要なカラムの追加
    $cost_columns = array(
        'supplier_cost' => "DECIMAL(10,2) DEFAULT 0.00 COMMENT '協力会社原価'",
        'internal_cost' => "DECIMAL(10,2) DEFAULT 0.00 COMMENT '社内原価'",
        'profit_margin' => "DECIMAL(5,2) DEFAULT 0.00 COMMENT '利益率'",
        'cost_status' => "VARCHAR(50) DEFAULT 'draft' COMMENT '原価ステータス'",
        'created_at' => "TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時'",
        'updated_at' => "TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日時'",
    );

    foreach ( $cost_columns as $column_name => $column_definition ) {
        if ( ! in_array( $column_name, $existing_columns ) ) {
            $sql = "ALTER TABLE `$cost_table` ADD COLUMN `$column_name` $column_definition";
            $result = $wpdb->query( $sql );

            if ( $result !== false && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( "KTPWP Migration: Added column '$column_name' to $cost_table" );
            }
        }
    }
}

// === ktp_staff_chat テーブルの修正 ===
$chat_table = $wpdb->prefix . 'ktp_staff_chat';
if ( $wpdb->get_var( "SHOW TABLES LIKE '$chat_table'" ) == $chat_table ) {

    $existing_columns = $wpdb->get_col( "SHOW COLUMNS FROM `$chat_table`" );

    // 必要なカラムの追加
    $chat_columns = array(
        'is_read' => "TINYINT(1) DEFAULT 0 COMMENT '既読フラグ'",
        'read_at' => "TIMESTAMP NULL COMMENT '既読日時'",
        'message_type' => "VARCHAR(50) DEFAULT 'text' COMMENT 'メッセージタイプ'",
        'attachment_url' => "TEXT NULL COMMENT '添付ファイルURL'",
        'updated_at' => "TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日時'",
    );

    foreach ( $chat_columns as $column_name => $column_definition ) {
        if ( ! in_array( $column_name, $existing_columns ) ) {
            $sql = "ALTER TABLE `$chat_table` ADD COLUMN `$column_name` $column_definition";
            $result = $wpdb->query( $sql );

            if ( $result !== false && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( "KTPWP Migration: Added column '$column_name' to $chat_table" );
            }
        }
    }
}

// === 空のorder_numberレコードの修正 ===
$order_table = $wpdb->prefix . 'ktp_order';
if ( $wpdb->get_var( "SHOW TABLES LIKE '$order_table'" ) == $order_table ) {

    // 空のorder_numberを持つレコードをUUIDで更新
    $empty_order_numbers = $wpdb->get_results(
        "SELECT id FROM `$order_table` WHERE order_number IS NULL OR order_number = ''"
    );

    foreach ( $empty_order_numbers as $record ) {
        $new_order_number = 'ORD-' . date( 'Ymd' ) . '-' . str_pad( $record->id, 6, '0', STR_PAD_LEFT );
        $wpdb->update(
            $order_table,
            array( 'order_number' => $new_order_number ),
            array( 'id' => $record->id ),
            array( '%s' ),
            array( '%d' )
        );
    }

    if ( count( $empty_order_numbers ) > 0 && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( 'KTPWP Migration: Fixed ' . count( $empty_order_numbers ) . ' empty order_number records' );
    }
}
