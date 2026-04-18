<?php
/**
 * 本番環境をローカル環境の基本構造に同期するマイグレーション
 * 実行日: 2025-07-03
 * 目的: 本番環境のテーブル構造をローカル環境の基本構造に統一
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
            error_log( 'KTPWP Migration: 新規インストール環境のため20250703_sync_production_to_local_structureをスキップ' );
        }
        return;
    }
}

// マイグレーション実行済みチェック
$migration_key = 'ktp_sync_production_to_local_structure_20250703_completed';
$migration_completed = get_option( $migration_key, false );

if ( $migration_completed ) {
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( 'KTPWP Migration: 本番→ローカル構造同期は既に実行済みです' );
    }
    return;
}

// 本番環境の判定
$production_order_table = 'top_ktp_order';
$production_supplier_table = 'top_ktp_supplier';

$production_order_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$production_order_table}'" );
if ( $production_order_exists !== $production_order_table ) {
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( 'KTPWP Migration: 本番環境テーブルが見つからないため終了' );
    }
    update_option( $migration_key, true );
    return;
}

if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
    error_log( 'KTPWP Migration: 本番環境を検出 - ローカル環境構造に同期開始' );
}

// === ORDER テーブル：ローカル環境の基本構造に合わせる ===
// ローカル環境の基本カラム（保持する）
// ※ 実際に使用されている納期カラムを含む
$order_basic_columns = array(
    'id',
    'time',
    'client_id',
    'customer_name',
    'user_name',
    'project_name',
    'progress',
    'invoice_items',
    'cost_items',
    'memo',
    'search_field',
    'created_at',
    'updated_at',
    'desired_delivery_date',    // 実際に使用されている
    'expected_delivery_date',   // 実際に使用されている
    'completion_date',           // 実際に使用されている
);

// 本番環境の余分なカラム（削除対象）
// ※ desired_delivery_date, expected_delivery_date, completion_date は実際に使用されているため保持
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

    // 重複・未使用カラム
    'company_name',         // client_companyと重複
    'client_company',       // client_idから取得可能
    'order_status',         // progressで十分
    'order_number',          // 実際には未使用
);

// === SUPPLIER テーブル：ローカル環境の基本構造に合わせる ===
// ローカル環境の基本カラム（保持する）
// ※ 実際に使用されているカラムを含む
$supplier_basic_columns = array(
    'id',
    'time',
    'name',
    'text',
    'url',
    'company_name',        // 実際に使用されている
    'email',               // 実際に使用されている
    'phone',               // 実際に使用されている
    'memo',
    'search_field',
    'frequency',
    'category',
    'created_at',
    'updated_at',
);

// 本番環境の余分なカラム（削除対象）
// ※ company_name, email, phone は実際に使用されているため保持
$supplier_columns_to_drop = array(
    // 詳細住所情報（基本機能では不要）
    'postal_code',
    'prefecture',
    'city',
    'address',
    'building',

    // 詳細取引条件（基本機能では不要）
    'closing_day',
    'payment_month',
    'payment_day',
    'payment_method',
    'tax_category',

    // 銀行情報（未使用）
    'bank_name',
    'bank_branch',
    'account_type',
    'account_number',
    'account_holder',

    // 重複・未使用
    'notes',                // memoと重複
    'status',               // 未使用
    'representative_name',   // 基本機能では不要
);

// 削除前にインデックスを削除
// ※ expected_delivery_date, completion_date は残すためインデックスも保持
$order_indexes_to_drop = array(
    'idx_order_number',
    'idx_client_company',
    'idx_order_status',
    'idx_delivery_date_1',
    'idx_company_name',
);

// ORDERテーブル処理
$order_existing_columns = $wpdb->get_col( "SHOW COLUMNS FROM `{$production_order_table}`", 0 );
$order_dropped_columns = array();
$order_dropped_indexes = array();

if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
    error_log( 'KTPWP Migration: 本番ORDER現在のカラム: ' . implode( ', ', $order_existing_columns ) );
}

// インデックス削除
foreach ( $order_indexes_to_drop as $index_name ) {
    $existing_index = $wpdb->get_row( "SHOW INDEX FROM `{$production_order_table}` WHERE Key_name = '{$index_name}'" );
    if ( $existing_index ) {
        $drop_index_sql = "ALTER TABLE `{$production_order_table}` DROP INDEX `{$index_name}`";
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
foreach ( $order_columns_to_drop as $column_name ) {
    if ( in_array( $column_name, $order_existing_columns ) ) {
        $drop_sql = "ALTER TABLE `{$production_order_table}` DROP COLUMN `{$column_name}`";
        $result = $wpdb->query( $drop_sql );

        if ( $result !== false ) {
            $order_dropped_columns[] = $column_name;
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( "KTPWP Migration: カラム '{$column_name}' を {$production_order_table} から削除しました" );
            }
        } elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( "KTPWP Migration Error: カラム '{$column_name}' の削除に失敗: " . $wpdb->last_error );
        }
    }
}

// SUPPLIERテーブル処理
$supplier_existing_columns = $wpdb->get_col( "SHOW COLUMNS FROM `{$production_supplier_table}`", 0 );
$supplier_dropped_columns = array();

if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
    error_log( 'KTPWP Migration: 本番SUPPLIER現在のカラム: ' . implode( ', ', $supplier_existing_columns ) );
}

foreach ( $supplier_columns_to_drop as $column_name ) {
    if ( in_array( $column_name, $supplier_existing_columns ) ) {
        $drop_sql = "ALTER TABLE `{$production_supplier_table}` DROP COLUMN `{$column_name}`";
        $result = $wpdb->query( $drop_sql );

        if ( $result !== false ) {
            $supplier_dropped_columns[] = $column_name;
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( "KTPWP Migration: カラム '{$column_name}' を {$production_supplier_table} から削除しました" );
            }
        } elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( "KTPWP Migration Error: カラム '{$column_name}' の削除に失敗: " . $wpdb->last_error );
        }
    }
}

// 最終結果のログ出力
if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
    error_log( 'KTPWP Migration: 本番→ローカル構造同期完了' );
    error_log( 'KTPWP Migration: ORDERテーブルから削除されたカラム: ' . implode( ', ', $order_dropped_columns ) );
    error_log( 'KTPWP Migration: ORDERテーブルから削除されたインデックス: ' . implode( ', ', $order_dropped_indexes ) );
    error_log( 'KTPWP Migration: SUPPLIERテーブルから削除されたカラム: ' . implode( ', ', $supplier_dropped_columns ) );

    // 最終的なカラム構造を確認
    $final_order_columns = $wpdb->get_col( "SHOW COLUMNS FROM `{$production_order_table}`", 0 );
    $final_supplier_columns = $wpdb->get_col( "SHOW COLUMNS FROM `{$production_supplier_table}`", 0 );

    error_log( 'KTPWP Migration: 最終ORDERカラム数: ' . count( $final_order_columns ) . ' (' . implode( ', ', $final_order_columns ) . ')' );
    error_log( 'KTPWP Migration: 最終SUPPLIERカラム数: ' . count( $final_supplier_columns ) . ' (' . implode( ', ', $final_supplier_columns ) . ')' );
}

// マイグレーション完了フラグを設定
update_option( $migration_key, true );
update_option( 'ktp_sync_production_to_local_structure_timestamp', current_time( 'mysql' ) );

if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
    error_log( 'KTPWP Migration: 20250703_sync_production_to_local_structure 完了' );
}
