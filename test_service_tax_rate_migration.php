<?php
/**
 * サービステーブル税率カラム追加マイグレーションテスト
 * 実行日: 2025-01-31
 */

// WordPress環境を読み込み
require_once dirname( __FILE__ ) . '/../../../wp-load.php';

// 直接実行禁止
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

echo "=== サービス税率カラム追加マイグレーションテスト ===\n";

// マイグレーションファイルを実行
require_once dirname( __FILE__ ) . '/includes/migrations/20250131_add_tax_rate_to_service.php';

echo "マイグレーション完了\n";

// 結果を確認
global $wpdb;
$service_table = $wpdb->prefix . 'ktp_service';

// テーブルが存在するか確認
$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$service_table'" );
if ( $table_exists ) {
    echo "✓ サービステーブルが存在します: $service_table\n";
    
    // tax_rateカラムが存在するか確認
    $column_exists = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
             WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'tax_rate'",
            DB_NAME,
            $service_table
        )
    );
    
    if ( ! empty( $column_exists ) ) {
        echo "✓ tax_rateカラムが存在します\n";
        
        // カラムの詳細情報を取得
        $column_info = $wpdb->get_row( "SHOW COLUMNS FROM `$service_table` LIKE 'tax_rate'" );
        if ( $column_info ) {
            echo "  型: " . $column_info->Type . "\n";
            echo "  NULL許可: " . $column_info->Null . "\n";
            echo "  デフォルト値: " . ( $column_info->Default ?? 'NULL' ) . "\n";
        }
        
        // 既存データの確認
        $existing_data = $wpdb->get_results( "SELECT id, service_name, tax_rate FROM `$service_table` LIMIT 5" );
        if ( $existing_data ) {
            echo "\n既存データの税率確認:\n";
            foreach ( $existing_data as $row ) {
                $tax_display = $row->tax_rate !== null ? $row->tax_rate . '%' : 'NULL（非課税）';
                echo "  ID: {$row->id}, サービス名: {$row->service_name}, 税率: {$tax_display}\n";
            }
        }
        
    } else {
        echo "✗ tax_rateカラムが存在しません\n";
    }
    
} else {
    echo "✗ サービステーブルが存在しません\n";
}

echo "\n=== テスト完了 ===\n"; 