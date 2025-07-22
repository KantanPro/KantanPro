<?php
/**
 * Test script for tax rate NULL migration
 * 
 * This script tests the migration that allows NULL values for tax_rate
 * in the supplier skills table.
 * 
 * @package KTPWP
 * @since 1.0.0
 */

// Load WordPress
require_once( dirname( __FILE__ ) . '/../../../wp-load.php' );

// Check if user is logged in and has admin privileges
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( 'このページにアクセスする権限がありません。' );
}

// Load the migration class
require_once( __DIR__ . '/includes/migrations/20250131_allow_null_tax_rate_in_supplier_skills.php' );

echo '<h1>税率NULL許可マイグレーションテスト</h1>';

// Run the migration
echo '<h2>マイグレーション実行</h2>';
$result = KTPWP_Migration_Allow_Null_Tax_Rate_In_Supplier_Skills::run();

if ( $result ) {
    echo '<p style="color: green;">✅ マイグレーションが正常に完了しました。</p>';
} else {
    echo '<p style="color: red;">❌ マイグレーションが失敗しました。</p>';
}

// Check current table structure
echo '<h2>現在のテーブル構造確認</h2>';
global $wpdb;

$table_name = $wpdb->prefix . 'ktp_supplier_skills';

// Check if table exists
$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" );
if ( ! $table_exists ) {
    echo '<p style="color: red;">❌ テーブルが存在しません: ' . $table_name . '</p>';
    exit;
}

echo '<p style="color: green;">✅ テーブルが存在します: ' . $table_name . '</p>';

// Check tax_rate column
$tax_rate_column = $wpdb->get_row( "SHOW COLUMNS FROM `{$table_name}` WHERE Field = 'tax_rate'" );
if ( $tax_rate_column ) {
    echo '<p>税率カラム情報:</p>';
    echo '<ul>';
    echo '<li>フィールド名: ' . $tax_rate_column->Field . '</li>';
    echo '<li>型: ' . $tax_rate_column->Type . '</li>';
    echo '<li>NULL許可: ' . ( $tax_rate_column->Null === 'YES' ? 'YES' : 'NO' ) . '</li>';
    echo '<li>デフォルト値: ' . $tax_rate_column->Default . '</li>';
    echo '</ul>';
    
    if ( $tax_rate_column->Null === 'YES' ) {
        echo '<p style="color: green;">✅ 税率カラムでNULL値が許可されています。</p>';
    } else {
        echo '<p style="color: red;">❌ 税率カラムでNULL値が許可されていません。</p>';
    }
} else {
    echo '<p style="color: red;">❌ 税率カラムが存在しません。</p>';
}

// Test inserting NULL tax_rate
echo '<h2>NULL税率の挿入テスト</h2>';

$test_data = array(
    'supplier_id' => 1,
    'product_name' => 'テスト商品（非課税）',
    'unit_price' => 1000.00,
    'quantity' => 1,
    'unit' => '式',
    'tax_rate' => null,
    'frequency' => 0,
    'created_at' => current_time( 'mysql' ),
    'updated_at' => current_time( 'mysql' ),
);

// Prepare format array for NULL tax_rate
$format_array = array( '%d', '%s', '%f', '%d', '%s', null, '%d', '%s', '%s' );

$insert_result = $wpdb->insert( $table_name, $test_data, $format_array );

if ( $insert_result !== false ) {
    echo '<p style="color: green;">✅ NULL税率での挿入が成功しました。ID: ' . $wpdb->insert_id . '</p>';
    
    // Verify the inserted data
    $inserted_data = $wpdb->get_row( 
        $wpdb->prepare( 
            "SELECT * FROM {$table_name} WHERE id = %d", 
            $wpdb->insert_id 
        ), 
        ARRAY_A 
    );
    
    if ( $inserted_data ) {
        echo '<p>挿入されたデータ:</p>';
        echo '<ul>';
        echo '<li>ID: ' . $inserted_data['id'] . '</li>';
        echo '<li>商品名: ' . $inserted_data['product_name'] . '</li>';
        echo '<li>単価: ' . $inserted_data['unit_price'] . '</li>';
        echo '<li>税率: ' . ( $inserted_data['tax_rate'] === null ? 'NULL（非課税）' : $inserted_data['tax_rate'] . '%' ) . '</li>';
        echo '</ul>';
    }
    
    // Clean up test data
    $wpdb->delete( $table_name, array( 'id' => $wpdb->insert_id ), array( '%d' ) );
    echo '<p style="color: blue;">🧹 テストデータを削除しました。</p>';
    
} else {
    echo '<p style="color: red;">❌ NULL税率での挿入が失敗しました。エラー: ' . $wpdb->last_error . '</p>';
}

// Check current version
echo '<h2>現在のバージョン確認</h2>';
$current_version = get_option( 'ktp_supplier_skills_table_version' );
echo '<p>現在のバージョン: ' . $current_version . '</p>';

if ( version_compare( $current_version, '3.4.0', '>=' ) ) {
    echo '<p style="color: green;">✅ バージョンが正しく更新されています。</p>';
} else {
    echo '<p style="color: red;">❌ バージョンが正しく更新されていません。</p>';
}

echo '<h2>テスト完了</h2>';
echo '<p>税率NULL許可機能のテストが完了しました。</p>';
echo '<p><a href="' . admin_url() . '">管理画面に戻る</a></p>'; 