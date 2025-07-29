<?php
/**
 * 不要なカラムを削除するクリーンアップマイグレーション
 * 実行日: 2025-07-03
 * 目的: 使用されていないカラムを削除してテーブル構造を最適化
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
            error_log( 'KTPWP Migration: 新規インストール環境のため20250703_cleanup_unused_columnsをスキップ' );
        }
        return;
    }
}

// マイグレーション実行済みチェック
$migration_key = 'ktp_cleanup_unused_columns_20250703_completed';
$migration_completed = get_option( $migration_key, false );

if ( $migration_completed ) {
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( 'KTPWP Migration: カラムクリーンアップは既に実行済みです' );
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

// === ORDER テーブルから不要カラムを削除 ===
$order_columns_to_drop = array(
    'delivery_date_1',      // 複数納期（未使用）
    'delivery_date_2',
    'delivery_date_3',
    'delivery_status_1',    // 納期ステータス（未使用）
    'delivery_status_2',
    'delivery_status_3',
    'company_name',          // client_companyと重複
);

// orderテーブルの現在のカラムを取得
$order_existing_columns = $wpdb->get_col( "SHOW COLUMNS FROM `{$order_table}`", 0 );
$order_dropped_columns = array();
$order_dropped_indexes = array();

foreach ( $order_columns_to_drop as $column_name ) {
    if ( in_array( $column_name, $order_existing_columns ) ) {
        // 関連するインデックスを先に削除
        $related_indexes = array(
            'delivery_date_1' => 'idx_delivery_date_1',
            'company_name' => 'idx_company_name',
        );

        if ( isset( $related_indexes[ $column_name ] ) ) {
            $index_name = $related_indexes[ $column_name ];
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

        // カラム削除
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

// === SUPPLIER テーブルから不要カラムを削除 ===
$supplier_columns_to_drop = array(
    'bank_name',           // 銀行情報（未使用）
    'bank_branch',
    'account_type',
    'account_number',
    'account_holder',
    'notes',               // memoと重複
    'status',               // ステータス（未使用）
);

// supplierテーブルの現在のカラムを取得
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
    error_log( 'KTPWP Migration: カラムクリーンアップ完了' );
    error_log( 'KTPWP Migration: ORDERテーブルから削除されたカラム: ' . implode( ', ', $order_dropped_columns ) );
    error_log( 'KTPWP Migration: ORDERテーブルから削除されたインデックス: ' . implode( ', ', $order_dropped_indexes ) );
    error_log( 'KTPWP Migration: SUPPLIERテーブルから削除されたカラム: ' . implode( ', ', $supplier_dropped_columns ) );

    // 最終的なカラム構造を確認
    $final_order_columns = $wpdb->get_col( "SHOW COLUMNS FROM `{$order_table}`", 0 );
    $final_supplier_columns = $wpdb->get_col( "SHOW COLUMNS FROM `{$supplier_table}`", 0 );

    error_log( 'KTPWP Migration: 最終ORDERカラム数: ' . count( $final_order_columns ) );
    error_log( 'KTPWP Migration: 最終SUPPLIERカラム数: ' . count( $final_supplier_columns ) );
}

// マイグレーション完了フラグを設定
update_option( $migration_key, true );
update_option( 'ktp_cleanup_unused_columns_timestamp', current_time( 'mysql' ) );

if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
    error_log( 'KTPWP Migration: 20250703_cleanup_unused_columns 完了' );
}
