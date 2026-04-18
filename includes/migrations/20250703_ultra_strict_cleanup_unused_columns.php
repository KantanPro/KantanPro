<?php
/**
 * 超厳密な不要カラム削除マイグレーション
 * 実行日: 2025-07-03
 * 目的: 実際に使用されている最小限のカラムのみを残してテーブル構造を超最適化
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
            error_log( 'KTPWP Migration: 新規インストール環境のため20250703_ultra_strict_cleanup_unused_columnsをスキップ' );
        }
        return;
    }
}

// マイグレーション実行済みチェック
$migration_key = 'ktp_ultra_strict_cleanup_unused_columns_20250703_completed';
$migration_completed = get_option( $migration_key, false );

if ( $migration_completed ) {
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( 'KTPWP Migration: 超厳密なカラムクリーンアップは既に実行済みです' );
    }
    return;
}

// 環境判定
$is_production = false;
$order_table = $wpdb->prefix . 'ktp_order';
$supplier_table = $wpdb->prefix . 'ktp_supplier';

// 本番環境の判定
$production_order_table = 'top_ktp_order';
$production_supplier_table = 'top_ktp_supplier';

$production_order_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$production_order_table}'" );
if ( $production_order_exists === $production_order_table ) {
    $is_production = true;
    $order_table = $production_order_table;
    $supplier_table = $production_supplier_table;
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( 'KTPWP Migration: 本番環境を検出' );
    }
}

// === ORDER テーブル：超厳密カラム削除 ===
$order_columns_to_drop = array(
    // 複数納期システム（未使用）
    'delivery_date_1',
    'delivery_date_2',
    'delivery_date_3',
    'delivery_status_1',
    'delivery_status_2',
    'delivery_status_3',

    // 支払い情報（clientテーブルで管理済み）
    'closing_day',
    'payment_month',
    'payment_day',
    'payment_method',
    'tax_category',

    // 重複・未使用情報
    'company_name',        // client_companyと重複
    'client_company',      // client_idから取得可能
    'order_status',        // progressで十分
    'order_number',        // 実際には使用されていない

    // アイテム管理（別テーブルで管理済み）
    'invoice_items',       // ktp_order_invoice_itemsテーブルで管理
    'cost_items',           // ktp_order_cost_itemsテーブルで管理
);

// === SUPPLIER テーブル：超厳密カラム削除 ===
$supplier_columns_to_drop = array(
    // 未使用フィールド
    'text',                // 使用されていない
    'url',                 // プレビューのみで実機能では不要

    // 銀行情報（未使用）
    'bank_name',
    'bank_branch',
    'account_type',
    'account_number',
    'account_holder',

    // 重複情報
    'notes',               // memoと同じ役割
    'status',               // 不要
);

// 削除前にインデックスを削除
$order_indexes_to_drop = array(
    'idx_delivery_date_1',
    'idx_client_company',
    'idx_order_status',
    'idx_company_name',
    'idx_order_number',
);

$order_existing_columns = $wpdb->get_col( "SHOW COLUMNS FROM `{$order_table}`", 0 );
$order_dropped_columns = array();
$order_dropped_indexes = array();

// ORDERテーブル：インデックス削除
foreach ( $order_indexes_to_drop as $index_name ) {
    $existing_index = $wpdb->get_row( "SHOW INDEX FROM `{$order_table}` WHERE Key_name = '{$index_name}'" );
    if ( $existing_index ) {
        $drop_index_sql = "ALTER TABLE `{$order_table}` DROP INDEX `{$index_name}`";
        $result = $wpdb->query( $drop_index_sql );
        if ( $result !== false ) {
            $order_dropped_indexes[] = $index_name;
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( "KTPWP Migration: インデックス '{$index_name}' を削除しました" );
            }
        }
    }
}

// ORDERテーブル：カラム削除
foreach ( $order_columns_to_drop as $column_name ) {
    if ( in_array( $column_name, $order_existing_columns ) ) {
        $drop_sql = "ALTER TABLE `{$order_table}` DROP COLUMN `{$column_name}`";
        $result = $wpdb->query( $drop_sql );

        if ( $result !== false ) {
            $order_dropped_columns[] = $column_name;
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( "KTPWP Migration: カラム '{$column_name}' を {$order_table} から削除しました" );
            }
        } elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( "KTPWP Migration Error: カラム '{$column_name}' の削除に失敗: " . $wpdb->last_error );
        }
    }
}

// SUPPLIERテーブル：カラム削除
$supplier_existing_columns = $wpdb->get_col( "SHOW COLUMNS FROM `{$supplier_table}`", 0 );
$supplier_dropped_columns = array();

foreach ( $supplier_columns_to_drop as $column_name ) {
    if ( in_array( $column_name, $supplier_existing_columns ) ) {
        $drop_sql = "ALTER TABLE `{$supplier_table}` DROP COLUMN `{$column_name}`";
        $result = $wpdb->query( $drop_sql );

        if ( $result !== false ) {
            $supplier_dropped_columns[] = $column_name;
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( "KTPWP Migration: カラム '{$column_name}' を {$supplier_table} から削除しました" );
            }
        } elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( "KTPWP Migration Error: カラム '{$column_name}' の削除に失敗: " . $wpdb->last_error );
        }
    }
}

// 最終結果のログ出力
if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
    error_log( 'KTPWP Migration: 超厳密カラムクリーンアップ完了' );
    error_log( 'KTPWP Migration: ORDERテーブルから削除されたカラム: ' . implode( ', ', $order_dropped_columns ) );
    error_log( 'KTPWP Migration: ORDERテーブルから削除されたインデックス: ' . implode( ', ', $order_dropped_indexes ) );
    error_log( 'KTPWP Migration: SUPPLIERテーブルから削除されたカラム: ' . implode( ', ', $supplier_dropped_columns ) );

    // 最終的なカラム構造を確認
    $final_order_columns = $wpdb->get_col( "SHOW COLUMNS FROM `{$order_table}`", 0 );
    $final_supplier_columns = $wpdb->get_col( "SHOW COLUMNS FROM `{$supplier_table}`", 0 );

    error_log( 'KTPWP Migration: 最終ORDERカラム数: ' . count( $final_order_columns ) . ' (' . implode( ', ', $final_order_columns ) . ')' );
    error_log( 'KTPWP Migration: 最終SUPPLIERカラム数: ' . count( $final_supplier_columns ) . ' (' . implode( ', ', $final_supplier_columns ) . ')' );
}

// マイグレーション完了フラグを設定
update_option( $migration_key, true );
update_option( 'ktp_ultra_strict_cleanup_unused_columns_timestamp', current_time( 'mysql' ) );

if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
    error_log( 'KTPWP Migration: 20250703_ultra_strict_cleanup_unused_columns 完了' );
}
