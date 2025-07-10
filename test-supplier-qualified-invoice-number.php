<?php
/**
 * Test script for supplier qualified invoice number field
 *
 * This script tests the implementation of the qualified invoice number field
 * in the supplier form.
 *
 * @package KTPWP
 * @subpackage Tests
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    require_once( dirname( __FILE__ ) . '/wp-config.php' );
}

echo '<h1>協力会社適格請求書番号フィールドテスト</h1>';

// Test 1: Check if the supplier table has the qualified_invoice_number column
echo '<h2>テスト1: データベースカラムの存在確認</h2>';

global $wpdb;
$table_name = $wpdb->prefix . 'ktp_supplier';

$column_exists = $wpdb->get_var( 
    $wpdb->prepare( 
        "SHOW COLUMNS FROM `{$table_name}` LIKE %s", 
        'qualified_invoice_number' 
    ) 
);

if ( $column_exists ) {
    echo '<p style="color: green;">✓ qualified_invoice_number カラムが存在します</p>';
} else {
    echo '<p style="color: red;">✗ qualified_invoice_number カラムが存在しません</p>';
}

// Test 2: Check table structure
echo '<h2>テスト2: テーブル構造の確認</h2>';

$columns = $wpdb->get_results( "SHOW COLUMNS FROM `{$table_name}`" );
echo '<table border="1" cellpadding="5">';
echo '<tr><th>カラム名</th><th>データ型</th><th>NULL</th><th>デフォルト値</th></tr>';
foreach ( $columns as $column ) {
    $color = $column->Field === 'qualified_invoice_number' ? 'background-color: yellow;' : '';
    echo '<tr style="' . $color . '">';
    echo '<td>' . esc_html( $column->Field ) . '</td>';
    echo '<td>' . esc_html( $column->Type ) . '</td>';
    echo '<td>' . esc_html( $column->Null ) . '</td>';
    echo '<td>' . esc_html( $column->Default ) . '</td>';
    echo '</tr>';
}
echo '</table>';

// Test 3: Test data insertion
echo '<h2>テスト3: データ挿入テスト</h2>';

// Create a test supplier data
$test_data = array(
    'company_name' => 'テスト株式会社',
    'name' => 'テスト太郎',
    'email' => 'test@example.com',
    'url' => 'https://example.com',
    'representative_name' => 'テスト代表',
    'phone' => '0312345678',
    'postal_code' => '1000001',
    'prefecture' => '東京都',
    'city' => '千代田区',
    'address' => '千代田1-1-1',
    'building' => 'テストビル',
    'closing_day' => '末日',
    'payment_month' => '翌月',
    'payment_day' => '末日',
    'payment_method' => '銀行振込（前）',
    'tax_category' => '内税',
    'memo' => 'テストメモ',
    'qualified_invoice_number' => 'T1234567890123',
    'category' => 'テスト',
    'search_field' => 'テスト検索フィールド',
    'time' => current_time( 'timestamp' ),
    'frequency' => 0
);

$insert_result = $wpdb->insert( $table_name, $test_data );

if ( $insert_result !== false ) {
    $inserted_id = $wpdb->insert_id;
    echo '<p style="color: green;">✓ テストデータが正常に挿入されました（ID: ' . $inserted_id . '）</p>';
    
    // Test 4: Test data retrieval
    echo '<h2>テスト4: データ取得テスト</h2>';
    
    $retrieved_data = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table_name}` WHERE id = %d", $inserted_id ) );
    
    if ( $retrieved_data ) {
        echo '<p style="color: green;">✓ データが正常に取得されました</p>';
        echo '<p><strong>適格請求書番号:</strong> ' . esc_html( $retrieved_data->qualified_invoice_number ) . '</p>';
        
        // Display all data
        echo '<table border="1" cellpadding="5">';
        echo '<tr><th>フィールド</th><th>値</th></tr>';
        foreach ( $retrieved_data as $field => $value ) {
            $color = $field === 'qualified_invoice_number' ? 'background-color: yellow;' : '';
            echo '<tr style="' . $color . '">';
            echo '<td>' . esc_html( $field ) . '</td>';
            echo '<td>' . esc_html( $value ) . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    } else {
        echo '<p style="color: red;">✗ データの取得に失敗しました</p>';
    }
    
    // Test 5: Test data update
    echo '<h2>テスト5: データ更新テスト</h2>';
    
    $updated_qualified_invoice_number = 'T9876543210987';
    $update_result = $wpdb->update( 
        $table_name, 
        array( 'qualified_invoice_number' => $updated_qualified_invoice_number ),
        array( 'id' => $inserted_id ),
        array( '%s' ),
        array( '%d' )
    );
    
    if ( $update_result !== false ) {
        echo '<p style="color: green;">✓ データが正常に更新されました</p>';
        
        // Verify the update
        $updated_data = $wpdb->get_row( $wpdb->prepare( "SELECT qualified_invoice_number FROM `{$table_name}` WHERE id = %d", $inserted_id ) );
        
        if ( $updated_data && $updated_data->qualified_invoice_number === $updated_qualified_invoice_number ) {
            echo '<p style="color: green;">✓ 更新が正常に反映されました</p>';
            echo '<p><strong>更新後の適格請求書番号:</strong> ' . esc_html( $updated_data->qualified_invoice_number ) . '</p>';
        } else {
            echo '<p style="color: red;">✗ 更新が反映されていません</p>';
        }
    } else {
        echo '<p style="color: red;">✗ データの更新に失敗しました</p>';
    }
    
    // Clean up: Delete test data
    echo '<h2>テスト完了: テストデータの削除</h2>';
    
    $delete_result = $wpdb->delete( $table_name, array( 'id' => $inserted_id ), array( '%d' ) );
    
    if ( $delete_result !== false ) {
        echo '<p style="color: green;">✓ テストデータが正常に削除されました</p>';
    } else {
        echo '<p style="color: red;">✗ テストデータの削除に失敗しました</p>';
    }
    
} else {
    echo '<p style="color: red;">✗ テストデータの挿入に失敗しました</p>';
    echo '<p>エラー: ' . $wpdb->last_error . '</p>';
}

// Test 6: Check if KTPWP_Supplier_Data class handles the new field
echo '<h2>テスト6: KTPWP_Supplier_Data クラスのテスト</h2>';

if ( class_exists( 'KTPWP_Supplier_Data' ) ) {
    echo '<p style="color: green;">✓ KTPWP_Supplier_Data クラスが存在します</p>';
    
    // Test sanitization function
    $test_post_data = array(
        'qualified_invoice_number' => 'T1234567890123 <script>alert("xss")</script>',
        'company_name' => 'テスト会社',
        'category' => 'テスト'
    );
    
    $supplier_data = new KTPWP_Supplier_Data();
    $reflection = new ReflectionClass( 'KTPWP_Supplier_Data' );
    $method = $reflection->getMethod( 'sanitize_supplier_data' );
    $method->setAccessible( true );
    
    $sanitized_data = $method->invoke( $supplier_data, $test_post_data );
    
    if ( isset( $sanitized_data['qualified_invoice_number'] ) ) {
        echo '<p style="color: green;">✓ qualified_invoice_number フィールドがサニタイズされました</p>';
        echo '<p><strong>サニタイズ結果:</strong> ' . esc_html( $sanitized_data['qualified_invoice_number'] ) . '</p>';
    } else {
        echo '<p style="color: red;">✗ qualified_invoice_number フィールドがサニタイズされていません</p>';
    }
    
} else {
    echo '<p style="color: red;">✗ KTPWP_Supplier_Data クラスが存在しません</p>';
}

echo '<h2>テスト完了</h2>';
echo '<p>すべてのテストが完了しました。上記の結果を確認してください。</p>';
?> 