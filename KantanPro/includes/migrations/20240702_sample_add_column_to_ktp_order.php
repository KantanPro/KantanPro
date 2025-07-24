<?php
/**
 * 本番環境向け自動マイグレーション: ktp_orderテーブル構造を最新版に更新
 * 実行日: 2024-07-02
 */

// 直接実行禁止
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;

// ktp_orderテーブルの構造を最新版に更新
$table_name = $wpdb->prefix . 'ktp_order';

// 既存のカラムを取得
$existing_columns = $wpdb->get_col( "SHOW COLUMNS FROM `$table_name`" );

// 必要なカラムの定義
$columns_to_add = array(
    'delivery_date_1' => "DATE NULL COMMENT '納期1'",
    'delivery_date_2' => "DATE NULL COMMENT '納期2'",
    'delivery_date_3' => "DATE NULL COMMENT '納期3'",
    'delivery_status_1' => "VARCHAR(50) DEFAULT 'pending' COMMENT '納期1のステータス'",
    'delivery_status_2' => "VARCHAR(50) DEFAULT 'pending' COMMENT '納期2のステータス'",
    'delivery_status_3' => "VARCHAR(50) DEFAULT 'pending' COMMENT '納期3のステータス'",
    'project_name' => "VARCHAR(255) NULL COMMENT 'プロジェクト名'",
    'order_number' => "VARCHAR(100) NULL COMMENT '受注番号'",
    'client_company' => "VARCHAR(255) NULL COMMENT 'クライアント会社名'",
    'order_status' => "VARCHAR(50) DEFAULT 'draft' COMMENT '受注ステータス'",
    'updated_at' => "TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日時'",
);

// カラムを一つずつ追加
foreach ( $columns_to_add as $column_name => $column_definition ) {
    if ( ! in_array( $column_name, $existing_columns ) ) {
        $sql = "ALTER TABLE `$table_name` ADD COLUMN `$column_name` $column_definition";
        $result = $wpdb->query( $sql );

        if ( $result === false ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( "KTPWP Migration Error: Failed to add column '$column_name': " . $wpdb->last_error );
            }
        } elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( "KTPWP Migration: Added column '$column_name' to $table_name" );
        }
    }
}

// インデックスの追加（存在しない場合のみ）
$indexes = array(
    'idx_order_number' => 'order_number',
    'idx_client_company' => 'client_company',
    'idx_order_status' => 'order_status',
    'idx_delivery_date_1' => 'delivery_date_1',
);

foreach ( $indexes as $index_name => $column_name ) {
    $existing_index = $wpdb->get_row( "SHOW INDEX FROM `$table_name` WHERE Key_name = '$index_name'" );
    if ( ! $existing_index && in_array( $column_name, $existing_columns ) ) {
        $sql = "ALTER TABLE `$table_name` ADD INDEX `$index_name` (`$column_name`)";
        $result = $wpdb->query( $sql );

        if ( $result !== false && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( "KTPWP Migration: Added index '$index_name' to $table_name" );
        }
    }
}
