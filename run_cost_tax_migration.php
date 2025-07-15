<?php
/**
 * コスト項目と職能テーブルに税率カラムを追加するマイグレーション
 */

// WordPress環境を読み込み
require_once __DIR__ . '/../../../../wp-config.php';
require_once __DIR__ . '/../../../../wp-load.php';

global $wpdb;

echo "=== コスト項目と職能テーブルの税率カラム追加マイグレーション開始 ===\n";

// 1. コスト項目テーブルに税率カラムを追加
$cost_table = $wpdb->prefix . 'ktp_order_cost_items';
$cost_column_exists = $wpdb->get_results("SHOW COLUMNS FROM `{$cost_table}` LIKE 'tax_rate'");

if (empty($cost_column_exists)) {
    echo "コスト項目テーブルに税率カラムを追加中...\n";
    $result = $wpdb->query("ALTER TABLE `{$cost_table}` ADD COLUMN `tax_rate` DECIMAL(5,2) DEFAULT 10.00 AFTER `amount`");
    if ($result !== false) {
        echo "✓ コスト項目テーブルに税率カラムを追加しました\n";
    } else {
        echo "✗ コスト項目テーブルへの税率カラム追加に失敗しました: " . $wpdb->last_error . "\n";
    }
} else {
    echo "✓ コスト項目テーブルには既に税率カラムが存在します\n";
}

// 2. 職能テーブルに税率カラムを追加
$skills_table = $wpdb->prefix . 'ktp_supplier_skills';
$skills_column_exists = $wpdb->get_results("SHOW COLUMNS FROM `{$skills_table}` LIKE 'tax_rate'");

if (empty($skills_column_exists)) {
    echo "職能テーブルに税率カラムを追加中...\n";
    $result = $wpdb->query("ALTER TABLE `{$skills_table}` ADD COLUMN `tax_rate` DECIMAL(5,2) DEFAULT 10.00 AFTER `unit`");
    if ($result !== false) {
        echo "✓ 職能テーブルに税率カラムを追加しました\n";
    } else {
        echo "✗ 職能テーブルへの税率カラム追加に失敗しました: " . $wpdb->last_error . "\n";
    }
} else {
    echo "✓ 職能テーブルには既に税率カラムが存在します\n";
}

// 3. 既存データの税率を10%に設定
echo "既存データの税率を10%に設定中...\n";

$cost_update_result = $wpdb->query("UPDATE `{$cost_table}` SET `tax_rate` = 10.00 WHERE `tax_rate` IS NULL OR `tax_rate` = 0");
if ($cost_update_result !== false) {
    echo "✓ コスト項目の既存データを更新しました（更新件数: {$cost_update_result}）\n";
} else {
    echo "✗ コスト項目の既存データ更新に失敗しました: " . $wpdb->last_error . "\n";
}

$skills_update_result = $wpdb->query("UPDATE `{$skills_table}` SET `tax_rate` = 10.00 WHERE `tax_rate` IS NULL OR `tax_rate` = 0");
if ($skills_update_result !== false) {
    echo "✓ 職能の既存データを更新しました（更新件数: {$skills_update_result}）\n";
} else {
    echo "✗ 職能の既存データ更新に失敗しました: " . $wpdb->last_error . "\n";
}

echo "=== マイグレーション完了 ===\n";
echo "コスト項目と職能テーブルに税率カラムが正常に追加されました。\n";
echo "既存データの税率は10%に設定されています。\n";
?> 