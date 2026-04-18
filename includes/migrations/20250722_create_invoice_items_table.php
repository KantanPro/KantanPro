<?php
/**
 * 請求項目テーブル作成マイグレーション
 * 
 * @package KTPWP
 * @since 1.0.0
 */

// 直接実行を防ぐ
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 請求項目テーブルを作成
 */
function ktpwp_create_invoice_items_table() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'ktp_invoice_items';
    $charset_collate = $wpdb->get_charset_collate();
    
    // テーブルが既に存在するかチェック
    if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name) {
        error_log("KTPWP: テーブル {$table_name} は既に存在します");
        return true;
    }
    
    $sql = "CREATE TABLE {$table_name} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        order_id bigint(20) unsigned NOT NULL,
        item_name varchar(255) NOT NULL DEFAULT '',
        unit_price decimal(10,2) NOT NULL DEFAULT 0.00,
        quantity decimal(10,2) NOT NULL DEFAULT 1.00,
        total_price decimal(10,2) NOT NULL DEFAULT 0.00,
        tax_rate decimal(5,2) NOT NULL DEFAULT 10.00,
        remarks text,
        sort_order int(11) NOT NULL DEFAULT 0,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY order_id (order_id),
        KEY sort_order (sort_order)
    ) {$charset_collate};";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    $result = dbDelta($sql);
    
    if (empty($wpdb->last_error)) {
        error_log("KTPWP: テーブル {$table_name} を作成しました");
        return true;
    } else {
        error_log("KTPWP: テーブル {$table_name} の作成に失敗しました: " . $wpdb->last_error);
        return false;
    }
}

// マイグレーション実行
if (isset($_GET['run_migration']) && $_GET['run_migration'] === 'create_invoice_items_table') {
    if (!current_user_can('manage_options')) {
        wp_die('権限がありません');
    }
    
    $result = ktpwp_create_invoice_items_table();
    if ($result) {
        echo "請求項目テーブルの作成が完了しました。";
    } else {
        echo "請求項目テーブルの作成に失敗しました。";
    }
    exit;
} 