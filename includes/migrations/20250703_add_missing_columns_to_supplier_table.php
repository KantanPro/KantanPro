<?php
/**
 * 本番環境向けマイグレーション: top_ktp_supplierテーブルに不足カラムを追加
 * 実行日: 2025-07-03
 * 対象: 本番環境 (top_ktp_supplier) と ローカル環境 (wp_ktp_supplier)
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
            error_log( 'KTPWP Migration: 新規インストール環境のため20250703_add_missing_columns_to_supplier_tableをスキップ' );
        }
        return;
    }
}

// 環境判定（本番かローカルか）
$is_production = false;
$table_name = $wpdb->prefix . 'ktp_supplier';

// 本番環境の判定（テーブル名で判定）
$production_table = 'top_ktp_supplier';
$local_table = $wpdb->prefix . 'ktp_supplier';

// 本番テーブルが存在するかチェック
$production_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$production_table}'" );
if ( $production_exists === $production_table ) {
    $is_production = true;
    $table_name = $production_table;
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( "KTPWP Migration: 本番環境を検出 - テーブル: {$table_name}" );
    }
} elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( "KTPWP Migration: ローカル環境を検出 - テーブル: {$table_name}" );
}

// マイグレーション実行済みチェック
$migration_key = 'ktp_supplier_migration_20250703_completed';
$migration_completed = get_option( $migration_key, false );

if ( $migration_completed ) {
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( 'KTPWP Migration: 20250703_add_missing_columns_to_supplier_table は既に実行済みです' );
    }
    return;
}

// 追加するカラムの定義
$columns_to_add = array(
    'company_name' => "VARCHAR(100) NOT NULL DEFAULT 'Regular Supplier'",
    'email' => 'VARCHAR(100) NOT NULL',
    'representative_name' => "VARCHAR(100) NOT NULL DEFAULT ''",
    'phone' => 'VARCHAR(20) NOT NULL',
    'postal_code' => 'VARCHAR(10) NOT NULL',
    'prefecture' => 'TINYTEXT NOT NULL',
    'city' => 'TINYTEXT NOT NULL',
    'address' => 'TEXT NOT NULL',
    'building' => 'TINYTEXT NOT NULL',
    'closing_day' => 'TINYTEXT NOT NULL',
    'payment_month' => 'TINYTEXT NOT NULL',
    'payment_day' => 'TINYTEXT NOT NULL',
    'payment_method' => 'TINYTEXT NOT NULL',
    'tax_category' => 'TINYTEXT NOT NULL',
    'bank_name' => 'VARCHAR(100) NOT NULL',
    'bank_branch' => 'VARCHAR(100) NOT NULL',
    'account_type' => 'VARCHAR(20) NOT NULL',
    'account_number' => 'VARCHAR(20) NOT NULL',
    'account_holder' => 'VARCHAR(100) NOT NULL',
    'notes' => 'TEXT NOT NULL',
    'status' => "VARCHAR(20) NOT NULL DEFAULT 'active'",
    'created_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
    'updated_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
);

// 現在のカラムを取得
$existing_columns = $wpdb->get_col( "SHOW COLUMNS FROM `{$table_name}`", 0 );

if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
    error_log( 'KTPWP Migration: 現在のカラム: ' . implode( ', ', $existing_columns ) );
}

// 不足しているカラムを特定
$missing_columns = array();
foreach ( $columns_to_add as $column_name => $column_definition ) {
    if ( ! in_array( $column_name, $existing_columns ) ) {
        $missing_columns[ $column_name ] = $column_definition;
    }
}

if ( empty( $missing_columns ) ) {
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( 'KTPWP Migration: 追加するカラムはありません' );
    }
    update_option( $migration_key, true );
    return;
}

if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
    error_log( 'KTPWP Migration: 追加予定カラム: ' . implode( ', ', array_keys( $missing_columns ) ) );
}

// カラムを追加
$success_count = 0;
$error_count = 0;

foreach ( $missing_columns as $column_name => $column_definition ) {
    $sql = "ALTER TABLE `{$table_name}` ADD COLUMN `{$column_name}` {$column_definition}";

    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( "KTPWP Migration: 実行SQL: {$sql}" );
    }

    $result = $wpdb->query( $sql );

    if ( $result !== false ) {
        $success_count++;
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( "KTPWP Migration: カラム '{$column_name}' の追加に成功しました" );
        }
    } else {
        $error_count++;
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( "KTPWP Migration: カラム '{$column_name}' の追加に失敗しました - " . $wpdb->last_error );
        }
    }
}

// マイグレーション完了を記録
update_option( $migration_key, true );

if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
    error_log( 'KTPWP Migration: 20250703_add_missing_columns_to_supplier_table 完了' );
    error_log( "KTPWP Migration: 成功: {$success_count}, 失敗: {$error_count}" );
}

// 最終確認
$final_columns = $wpdb->get_col( "SHOW COLUMNS FROM `{$table_name}`", 0 );
if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
    error_log( 'KTPWP Migration: 最終カラム数: ' . count( $final_columns ) );
    error_log( 'KTPWP Migration: 最終カラム: ' . implode( ', ', $final_columns ) );
}
