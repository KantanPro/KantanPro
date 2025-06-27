<?php
/**
 * 自動テーブル作成・カラム追加スクリプト
 */

// エラー表示を有効化
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== 自動テーブル作成・カラム追加スクリプト ===\n\n";

// データベース設定
$db_host = 'localhost';
$db_name = 'wordpress';
$db_user = 'root';
$db_pass = '';
$table_prefix = 'wp_';
$table_name = $table_prefix . 'ktp_order';

echo "1. データベース接続中...\n";

// データベース接続
$mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($mysqli->connect_error) {
    echo "❌ データベース接続エラー: " . $mysqli->connect_error . "\n";
    exit(1);
}

echo "✅ データベース接続成功\n\n";

echo "2. テーブル存在確認中...\n";

// テーブル存在確認
$result = $mysqli->query("SHOW TABLES LIKE '{$table_name}'");
$table_exists = $result->num_rows > 0;

if ($table_exists) {
    echo "✅ テーブル存在: {$table_name}\n";
    
    // 既存テーブルのカラム確認
    echo "\n3. 既存カラム確認中...\n";
    $columns_result = $mysqli->query("SHOW COLUMNS FROM `{$table_name}`");
    $existing_columns = [];
    
    while ($row = $columns_result->fetch_assoc()) {
        $existing_columns[] = $row['Field'];
        echo "  - {$row['Field']} ({$row['Type']}) デフォルト: " . ($row['Default'] ?? 'NULL') . "\n";
    }
    
    // created_atカラムの修正
    if (in_array('created_at', $existing_columns)) {
        echo "\n4. created_atカラム修正中...\n";
        $sql = "ALTER TABLE `{$table_name}` MODIFY COLUMN `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時'";
        
        if ($mysqli->query($sql)) {
            echo "✅ created_atカラム修正成功\n";
        } else {
            echo "❌ created_atカラム修正失敗: " . $mysqli->error . "\n";
        }
    }
    
    // 納期フィールドの追加
    $desired_exists = in_array('desired_delivery_date', $existing_columns);
    $expected_exists = in_array('expected_delivery_date', $existing_columns);
    
    echo "\n5. 納期フィールド追加中...\n";
    
    if (!$desired_exists) {
        $sql = "ALTER TABLE `{$table_name}` ADD COLUMN `desired_delivery_date` DATE NULL DEFAULT NULL COMMENT '希望納期'";
        if ($mysqli->query($sql)) {
            echo "✅ 希望納期フィールド追加成功\n";
        } else {
            echo "❌ 希望納期フィールド追加失敗: " . $mysqli->error . "\n";
        }
    } else {
        echo "ℹ️ 希望納期フィールドは既に存在\n";
    }
    
    if (!$expected_exists) {
        $sql = "ALTER TABLE `{$table_name}` ADD COLUMN `expected_delivery_date` DATE NULL DEFAULT NULL COMMENT '納品予定日'";
        if ($mysqli->query($sql)) {
            echo "✅ 納品予定日フィールド追加成功\n";
        } else {
            echo "❌ 納品予定日フィールド追加失敗: " . $mysqli->error . "\n";
        }
    } else {
        echo "ℹ️ 納品予定日フィールドは既に存在\n";
    }
    
} else {
    echo "❌ テーブル不存在: {$table_name}\n";
    echo "\n3. テーブル作成中...\n";
    
    $sql = "CREATE TABLE `{$table_name}` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `order_number` varchar(50) NOT NULL COMMENT '受注番号',
        `client_id` int(11) NOT NULL COMMENT 'クライアントID',
        `project_name` varchar(255) NOT NULL COMMENT 'プロジェクト名',
        `order_date` date NOT NULL COMMENT '受注日',
        `desired_delivery_date` date NULL DEFAULT NULL COMMENT '希望納期',
        `expected_delivery_date` date NULL DEFAULT NULL COMMENT '納品予定日',
        `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '合計金額',
        `status` varchar(20) NOT NULL DEFAULT 'pending' COMMENT 'ステータス',
        `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
        `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日時',
        PRIMARY KEY (`id`),
        UNIQUE KEY `order_number` (`order_number`),
        KEY `client_id` (`client_id`),
        KEY `order_date` (`order_date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='受注書テーブル'";
    
    if ($mysqli->query($sql)) {
        echo "✅ テーブル作成成功\n";
    } else {
        echo "❌ テーブル作成失敗: " . $mysqli->error . "\n";
        exit(1);
    }
}

echo "\n6. 最終確認中...\n";

// 最終的なテーブル構造確認
$final_result = $mysqli->query("SHOW COLUMNS FROM `{$table_name}`");
echo "最終テーブル構造:\n";

while ($row = $final_result->fetch_assoc()) {
    $is_target = in_array($row['Field'], ['desired_delivery_date', 'expected_delivery_date']);
    $marker = $is_target ? "🎯" : "  ";
    echo "{$marker} {$row['Field']} ({$row['Type']}) デフォルト: " . ($row['Default'] ?? 'NULL') . "\n";
}

$mysqli->close();

echo "\n=== 処理完了 ===\n";
echo "テーブル: {$table_name}\n";
echo "納期フィールドが正常に追加されました。\n";
?> 