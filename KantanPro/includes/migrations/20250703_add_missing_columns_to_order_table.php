<?php
/**
 * 本番環境向けマイグレーション: top_ktp_orderテーブルに不足カラムを追加
 * 実行日: 2025-07-03
 * 対象: 本番環境 (top_ktp_order) と ローカル環境 (wp_ktp_order)
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
            error_log( 'KTPWP Migration: 新規インストール環境のため20250703_add_missing_columns_to_order_tableをスキップ' );
        }
        return;
    }
}

// 環境判定（本番かローカルか）
$is_production = false;
$table_name = $wpdb->prefix . 'ktp_order';

// 本番環境の判定（テーブル名で判定）
$production_table = 'top_ktp_order';
$local_table = $wpdb->prefix . 'ktp_order';

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

// 既存のカラムを取得
$existing_columns = $wpdb->get_col( "SHOW COLUMNS FROM `{$table_name}`", 0 );

if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
    error_log( 'KTPWP Migration: 現在のカラム: ' . implode( ', ', $existing_columns ) );
}

// 追加するカラムの定義
$columns_to_add = array(
    'desired_delivery_date' => "DATE NULL DEFAULT NULL COMMENT '希望納期'",
    'expected_delivery_date' => "DATE NULL DEFAULT NULL COMMENT '納品予定日'",
    'completion_date' => "DATE NULL DEFAULT NULL COMMENT '完了日'",
    'closing_day' => "VARCHAR(50) NULL DEFAULT NULL COMMENT '締め日'",
    'payment_month' => "VARCHAR(50) NULL DEFAULT NULL COMMENT '支払月'",
    'payment_day' => "VARCHAR(50) NULL DEFAULT NULL COMMENT '支払日'",
    'payment_method' => "VARCHAR(100) NULL DEFAULT NULL COMMENT '支払方法'",
    'tax_category' => "VARCHAR(100) NULL DEFAULT '税込' COMMENT '税区分'",
    'company_name' => "VARCHAR(255) NULL DEFAULT NULL COMMENT '会社名'",
    'order_number' => "VARCHAR(100) NULL DEFAULT NULL COMMENT '受注番号'",
    'delivery_date_1' => "DATE NULL DEFAULT NULL COMMENT '納期1'",
    'delivery_date_2' => "DATE NULL DEFAULT NULL COMMENT '納期2'",
    'delivery_date_3' => "DATE NULL DEFAULT NULL COMMENT '納期3'",
    'delivery_status_1' => "VARCHAR(50) NULL DEFAULT 'pending' COMMENT '納期1のステータス'",
    'delivery_status_2' => "VARCHAR(50) NULL DEFAULT 'pending' COMMENT '納期2のステータス'",
    'delivery_status_3' => "VARCHAR(50) NULL DEFAULT 'pending' COMMENT '納期3のステータス'",
    'client_company' => "VARCHAR(255) NULL DEFAULT NULL COMMENT 'クライアント会社名'",
    'order_status' => "VARCHAR(50) NULL DEFAULT 'draft' COMMENT '受注ステータス'",
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

// インデックスの追加（存在しない場合のみ）
$indexes = array(
    'idx_order_number' => 'order_number',
    'idx_client_company' => 'client_company',
    'idx_order_status' => 'order_status',
    'idx_delivery_date_1' => 'delivery_date_1',
    'idx_expected_delivery_date' => 'expected_delivery_date',
    'idx_completion_date' => 'completion_date',
);

$added_indexes = array();
foreach ( $indexes as $index_name => $column_name ) {
    // カラムが存在する場合のみインデックスを追加
    if ( in_array( $column_name, $existing_columns ) || in_array( $column_name, $added_columns ) ) {
        $existing_index = $wpdb->get_row( "SHOW INDEX FROM `{$table_name}` WHERE Key_name = '{$index_name}'" );
        if ( ! $existing_index ) {
            $sql = "ALTER TABLE `{$table_name}` ADD INDEX `{$index_name}` (`{$column_name}`)";
            $result = $wpdb->query( $sql );

            if ( $result !== false ) {
                $added_indexes[] = $index_name;
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( "KTPWP Migration: インデックス '{$index_name}' を {$table_name} に追加しました" );
                }
            }
        }
    }
}

// 最終結果のログ出力
if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
    error_log( 'KTPWP Migration: マイグレーション完了' );
    error_log( 'KTPWP Migration: 追加されたカラム: ' . implode( ', ', $added_columns ) );
    error_log( 'KTPWP Migration: スキップされたカラム: ' . implode( ', ', $skipped_columns ) );
    error_log( 'KTPWP Migration: 追加されたインデックス: ' . implode( ', ', $added_indexes ) );
    // 最終的なカラム構造を確認
    $final_columns = $wpdb->get_col( "SHOW COLUMNS FROM `{$table_name}`", 0 );
    error_log( 'KTPWP Migration: 最終的なカラム: ' . implode( ', ', $final_columns ) );
}

// マイグレーション完了フラグを設定
update_option( 'ktp_order_migration_20250703_completed', true );
update_option( 'ktp_order_migration_20250703_timestamp', current_time( 'mysql' ) );
