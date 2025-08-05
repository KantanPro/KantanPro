<?php
/**
 * 緊急修正マイグレーション: wp_ktp_orderテーブルのinvoice_itemsカラム追加
 * 実行日: 2025-01-08
 * 対象: wp_ktp_orderテーブル
 * 問題: "Unknown column 'invoice_items' in 'field list'" エラーの修正
 */

// 直接実行禁止
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;

// 新規インストール判定 - 新規インストール時はスキップ
if ( class_exists( 'KTPWP_Fresh_Install_Detector' ) ) {
    $fresh_detector = KTPWP_Fresh_Install_Detector::get_instance();
    if ( $fresh_detector->should_skip_migrations() ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'KTPWP Migration: 新規インストール環境のため20250108_fix_invoice_items_columnをスキップ' );
        }
        return;
    }
}

// テーブル名の設定
$table_name = $wpdb->prefix . 'ktp_order';

// テーブルの存在確認
$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" );
if ( $table_exists !== $table_name ) {
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( "KTPWP Migration Error: テーブル {$table_name} が存在しません" );
    }
    return;
}

// 既存のカラムを取得
$existing_columns = $wpdb->get_col( "SHOW COLUMNS FROM `{$table_name}`", 0 );

if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
    error_log( 'KTPWP Migration: 現在のカラム: ' . implode( ', ', $existing_columns ) );
}

// 追加するカラムの定義（エラーメッセージで不足しているカラム）
$columns_to_add = array(
    'invoice_items' => "TEXT NULL DEFAULT NULL COMMENT '請求項目'",
    'cost_items' => "TEXT NULL DEFAULT NULL COMMENT '原価項目'",
);

// カラムを一つずつ追加
$added_columns = array();
$skipped_columns = array();

foreach ( $columns_to_add as $column_name => $column_definition ) {
    if ( ! in_array( $column_name, $existing_columns ) ) {
        $sql = "ALTER TABLE `{$table_name}` ADD COLUMN `{$column_name}` {$column_definition}";
        $result = $wpdb->query( $sql );

        if ( $result === false ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( "KTPWP Migration Error: カラム '{$column_name}' の追加に失敗: " . $wpdb->last_error );
            }
        } else {
            $added_columns[] = $column_name;
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( "KTPWP Migration: カラム '{$column_name}' を {$table_name} に追加しました" );
            }
        }
    } else {
        $skipped_columns[] = $column_name;
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( "KTPWP Migration: カラム '{$column_name}' は既に存在するためスキップ" );
        }
    }
}

// 最終結果のログ出力
if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
    error_log( 'KTPWP Migration: invoice_itemsカラム修正マイグレーション完了' );
    error_log( 'KTPWP Migration: 追加されたカラム: ' . implode( ', ', $added_columns ) );
    error_log( 'KTPWP Migration: スキップされたカラム: ' . implode( ', ', $skipped_columns ) );
    
    // 最終的なカラム構造を確認
    $final_columns = $wpdb->get_col( "SHOW COLUMNS FROM `{$table_name}`", 0 );
    error_log( 'KTPWP Migration: 最終的なカラム: ' . implode( ', ', $final_columns ) );
}

// マイグレーション完了フラグを設定
update_option( 'ktp_order_migration_20250108_invoice_items_completed', true );
update_option( 'ktp_order_migration_20250108_invoice_items_timestamp', current_time( 'mysql' ) );

// 成功メッセージ
if ( ! empty( $added_columns ) ) {
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( 'KTPWP Migration: invoice_itemsカラムの追加が正常に完了しました' );
    }
} 