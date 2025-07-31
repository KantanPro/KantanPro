<?php
/**
 * License Reset Test Script
 * 
 * This script resets the license state and tests the report functionality
 * 
 * @package KTPWP
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    // Load WordPress
    require_once( dirname( __FILE__ ) . '/../../../wp-load.php' );
}

// Check if user has admin privileges
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( 'このスクリプトを実行する権限がありません。' );
}

echo '<h1>ライセンス状態リセットテスト</h1>';

// Get license manager instance
$license_manager = KTPWP_License_Manager::get_instance();

// Show current license state
echo '<h2>現在のライセンス状態</h2>';
$license_key = get_option( 'ktp_license_key' );
$license_status = get_option( 'ktp_license_status' );
$license_info = get_option( 'ktp_license_info' );

echo '<p><strong>ライセンスキー:</strong> ' . ( empty( $license_key ) ? '未設定' : '設定済み' ) . '</p>';
echo '<p><strong>ライセンスステータス:</strong> ' . $license_status . '</p>';
echo '<p><strong>ライセンス情報:</strong> ' . ( empty( $license_info ) ? 'なし' : 'あり' ) . '</p>';

// Test license validity
echo '<h2>ライセンス有効性チェック</h2>';
$is_valid = $license_manager->is_license_valid();
echo '<p><strong>is_license_valid():</strong> ' . ( $is_valid ? 'true' : 'false' ) . '</p>';

// Reset license to invalid state
echo '<h2>ライセンス状態をリセット</h2>';
$license_manager->reset_license_for_testing();

// Also clear all license data for thorough testing
echo '<h2>ライセンスデータを完全クリア</h2>';
$license_manager->clear_all_license_data();

// Show updated license state
echo '<h2>リセット後のライセンス状態</h2>';
$license_status_after = get_option( 'ktp_license_status' );
$is_valid_after = $license_manager->is_license_valid();

echo '<p><strong>ライセンスステータス:</strong> ' . $license_status_after . '</p>';
echo '<p><strong>is_license_valid():</strong> ' . ( $is_valid_after ? 'true' : 'false' ) . '</p>';

// Test report functionality
echo '<h2>レポート機能テスト</h2>';
if ( class_exists( 'KTPWP_Report_Class' ) ) {
    $report_class = new KTPWP_Report_Class();
    $report_content = $report_class->Report_Tab_View( 'report' );
    
    // Check if dummy graph is rendered
    if ( strpos( $report_content, 'dummy_graph.png' ) !== false ) {
        echo '<p style="color: green;"><strong>✓ ダミーグラフが正しく表示されています</strong></p>';
    } else {
        echo '<p style="color: red;"><strong>✗ ダミーグラフが表示されていません</strong></p>';
    }
    
    // Check if comprehensive reports are rendered
    if ( strpos( $report_content, 'report_content' ) !== false && strpos( $report_content, 'dummy_graph.png' ) === false ) {
        echo '<p style="color: red;"><strong>✗ 本格的なレポートが表示されています（ライセンスが無効な場合は表示されるべきではありません）</strong></p>';
    }
} else {
    echo '<p style="color: red;"><strong>✗ レポートクラスが見つかりません</strong></p>';
}

echo '<h2>テスト完了</h2>';
echo '<p>WordPressの管理画面でレポートタブを確認してください。</p>';
echo '<p><a href="' . admin_url( 'admin.php?page=ktp-settings&tab=report' ) . '">レポートタブを開く</a></p>';
?> 