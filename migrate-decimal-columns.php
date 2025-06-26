<?php
/**
 * データベースマイグレーション: priceとquantityカラムをDECIMAL(10,2)型に変更
 * 
 * 使用方法:
 * 1. このファイルをWordPressのルートディレクトリに配置
 * 2. ブラウザで http://your-site.com/migrate-decimal-columns.php にアクセス
 * 3. マイグレーション完了後、このファイルを削除
 */

// WordPressを読み込み
require_once('wp-config.php');

// 管理者権限チェック
if (!current_user_can('manage_options')) {
    wp_die('管理者権限が必要です。');
}

echo '<h1>データベースマイグレーション: DECIMAL型への変更</h1>';

global $wpdb;

// 請求項目テーブルのマイグレーション
$invoice_table = $wpdb->prefix . 'ktp_order_invoice_items';
echo '<h2>請求項目テーブル: ' . $invoice_table . '</h2>';

// テーブルが存在するかチェック
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$invoice_table'");
if ($table_exists) {
    echo '<p>テーブルが存在します。マイグレーションを開始します...</p>';
    
    // priceカラムをDECIMAL(10,2)に変更
    $result = $wpdb->query("ALTER TABLE `$invoice_table` MODIFY `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00");
    if ($result !== false) {
        echo '<p style="color: green;">✓ priceカラムをDECIMAL(10,2)に変更しました。</p>';
    } else {
        echo '<p style="color: red;">✗ priceカラムの変更に失敗しました: ' . $wpdb->last_error . '</p>';
    }
    
    // quantityカラムをDECIMAL(10,2)に変更
    $result = $wpdb->query("ALTER TABLE `$invoice_table` MODIFY `quantity` DECIMAL(10,2) NOT NULL DEFAULT 0.00");
    if ($result !== false) {
        echo '<p style="color: green;">✓ quantityカラムをDECIMAL(10,2)に変更しました。</p>';
    } else {
        echo '<p style="color: red;">✗ quantityカラムの変更に失敗しました: ' . $wpdb->last_error . '</p>';
    }
    
    // テーブルバージョンを更新
    update_option('ktp_invoice_items_table_version', '2.1');
    echo '<p style="color: green;">✓ テーブルバージョンを2.1に更新しました。</p>';
    
} else {
    echo '<p style="color: orange;">テーブルが存在しません。新規作成時にDECIMAL型で作成されます。</p>';
}

// コスト項目テーブルのマイグレーション
$cost_table = $wpdb->prefix . 'ktp_order_cost_items';
echo '<h2>コスト項目テーブル: ' . $cost_table . '</h2>';

// テーブルが存在するかチェック
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$cost_table'");
if ($table_exists) {
    echo '<p>テーブルが存在します。マイグレーションを開始します...</p>';
    
    // priceカラムをDECIMAL(10,2)に変更
    $result = $wpdb->query("ALTER TABLE `$cost_table` MODIFY `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00");
    if ($result !== false) {
        echo '<p style="color: green;">✓ priceカラムをDECIMAL(10,2)に変更しました。</p>';
    } else {
        echo '<p style="color: red;">✗ priceカラムの変更に失敗しました: ' . $wpdb->last_error . '</p>';
    }
    
    // quantityカラムをDECIMAL(10,2)に変更
    $result = $wpdb->query("ALTER TABLE `$cost_table` MODIFY `quantity` DECIMAL(10,2) NOT NULL DEFAULT 0.00");
    if ($result !== false) {
        echo '<p style="color: green;">✓ quantityカラムをDECIMAL(10,2)に変更しました。</p>';
    } else {
        echo '<p style="color: red;">✗ quantityカラムの変更に失敗しました: ' . $wpdb->last_error . '</p>';
    }
    
    // テーブルバージョンを更新
    update_option('ktp_cost_items_table_version', '2.2');
    echo '<p style="color: green;">✓ テーブルバージョンを2.2に更新しました。</p>';
    
} else {
    echo '<p style="color: orange;">テーブルが存在しません。新規作成時にDECIMAL型で作成されます。</p>';
}

echo '<h2>マイグレーション完了</h2>';
echo '<p>マイグレーションが完了しました。このファイルを削除してください。</p>';
echo '<p><a href="' . admin_url() . '">管理画面に戻る</a></p>';
?> 