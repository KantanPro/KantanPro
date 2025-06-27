<?php
/**
 * 受注書テーブルに希望納期と納品予定日フィールドを追加するマイグレーション
 *
 * @package KTPWP
 * @since 1.0.0
 */

// 直接アクセスを防止
if (!defined('ABSPATH')) {
    // WordPress環境を読み込み（コマンドライン実行時）
    if (php_sapi_name() === 'cli') {
        $wp_load_path = __DIR__ . '/../../../wp-load.php';
        if (file_exists($wp_load_path)) {
            require_once $wp_load_path;
        } else {
            echo "WordPress環境が見つかりません。\n";
            exit(1);
        }
    } else {
        exit;
    }
}

// WordPressのアップグレード機能を読み込み
require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

/**
 * 受注書テーブルに希望納期と納品予定日フィールドを追加
 */
function ktpwp_add_delivery_date_fields() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'ktp_order';
    
    echo "テーブル名: {$table_name}\n";
    
    // テーブルの存在確認
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'");
    if (!$table_exists) {
        echo "❌ テーブルが存在しません: {$table_name}\n";
        return false;
    }
    
    echo "✅ テーブルが存在します\n";
    
    // 既存のカラムを確認
    $existing_columns = $wpdb->get_col("SHOW COLUMNS FROM `{$table_name}`", 0);
    echo "既存のカラム: " . implode(', ', $existing_columns) . "\n";
    
    // 希望納期フィールドを追加
    if (!in_array('desired_delivery_date', $existing_columns)) {
        echo "希望納期フィールドを追加中...\n";
        $sql = "ALTER TABLE `{$table_name}` ADD COLUMN `desired_delivery_date` DATE NULL DEFAULT NULL COMMENT '希望納期'";
        $result = $wpdb->query($sql);
        
        if ($result === false) {
            echo "❌ 希望納期フィールドの追加に失敗しました: " . $wpdb->last_error . "\n";
            return false;
        }
        
        echo "✅ 希望納期フィールドを追加しました。\n";
    } else {
        echo "✅ 希望納期フィールドは既に存在します。\n";
    }
    
    // 納品予定日フィールドを追加
    if (!in_array('expected_delivery_date', $existing_columns)) {
        echo "納品予定日フィールドを追加中...\n";
        $sql = "ALTER TABLE `{$table_name}` ADD COLUMN `expected_delivery_date` DATE NULL DEFAULT NULL COMMENT '納品予定日'";
        $result = $wpdb->query($sql);
        
        if ($result === false) {
            echo "❌ 納品予定日フィールドの追加に失敗しました: " . $wpdb->last_error . "\n";
            return false;
        }
        
        echo "✅ 納品予定日フィールドを追加しました。\n";
    } else {
        echo "✅ 納品予定日フィールドは既に存在します。\n";
    }
    
    // 最終確認
    $final_columns = $wpdb->get_col("SHOW COLUMNS FROM `{$table_name}`", 0);
    echo "最終的なカラム: " . implode(', ', $final_columns) . "\n";
    
    // テーブルバージョンを更新
    update_option('ktp_order_table_version', '1.3');
    
    echo "✅ マイグレーションが完了しました。\n";
    return true;
}

// コマンドラインから実行された場合
if (php_sapi_name() === 'cli') {
    echo "受注書テーブルに納期フィールドを追加中...\n";
    $result = ktpwp_add_delivery_date_fields();
    if ($result) {
        echo "✅ 完了しました。\n";
        exit(0);
    } else {
        echo "❌ 失敗しました。\n";
        exit(1);
    }
} 