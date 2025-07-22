<?php
/**
 * 税率NULL対応マイグレーション実行スクリプト（直接実行版）
 */

echo '<h1>税率NULL対応マイグレーション（直接実行版）</h1>';

// データベース接続設定
$db_host = 'localhost';
$db_name = 'wordpress';
$db_user = 'root';
$db_pass = '';

try {
    // データベースに直接接続
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo '<p style="color: green;">データベース接続成功</p>';
    
    // === ktp_order_invoice_items テーブルの税率フィールド修正 ===
    $invoice_table = 'wp_ktp_order_invoice_items';
    echo '<p>請求項目テーブル（' . $invoice_table . '）の税率フィールドを修正中...</p>';
    
    try {
        $sql = "ALTER TABLE `$invoice_table` MODIFY `tax_rate` DECIMAL(5,2) NULL DEFAULT NULL COMMENT '税率（NULL=非課税）'";
        $pdo->exec($sql);
        echo '<p style="color: green;">✓ 請求項目テーブルの税率フィールドを修正しました</p>';
    } catch (PDOException $e) {
        echo '<p style="color: orange;">⚠ 請求項目テーブルの修正でエラー: ' . $e->getMessage() . '</p>';
    }
    
    // === ktp_order_cost_items テーブルの税率フィールド修正 ===
    $cost_table = 'wp_ktp_order_cost_items';
    echo '<p>コスト項目テーブル（' . $cost_table . '）の税率フィールドを修正中...</p>';
    
    try {
        $sql = "ALTER TABLE `$cost_table` MODIFY `tax_rate` DECIMAL(5,2) NULL DEFAULT NULL COMMENT '税率（NULL=非課税）'";
        $pdo->exec($sql);
        echo '<p style="color: green;">✓ コスト項目テーブルの税率フィールドを修正しました</p>';
    } catch (PDOException $e) {
        echo '<p style="color: orange;">⚠ コスト項目テーブルの修正でエラー: ' . $e->getMessage() . '</p>';
    }
    
    // === ktp_service テーブルの税率フィールド修正 ===
    $service_table = 'wp_ktp_service';
    echo '<p>サービステーブル（' . $service_table . '）の税率フィールドを修正中...</p>';
    
    try {
        $sql = "ALTER TABLE `$service_table` MODIFY `tax_rate` DECIMAL(5,2) NULL DEFAULT NULL COMMENT '税率（NULL=非課税）'";
        $pdo->exec($sql);
        echo '<p style="color: green;">✓ サービステーブルの税率フィールドを修正しました</p>';
    } catch (PDOException $e) {
        echo '<p style="color: orange;">⚠ サービステーブルの修正でエラー: ' . $e->getMessage() . '</p>';
    }
    
    // 税率フィールドが存在しない場合の追加処理
    echo '<p>税率フィールドの存在確認と追加処理中...</p>';
    
    // 請求項目テーブル
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM `$invoice_table` LIKE 'tax_rate'");
        if ($stmt->rowCount() == 0) {
            $sql = "ALTER TABLE `$invoice_table` ADD COLUMN `tax_rate` DECIMAL(5,2) NULL DEFAULT NULL COMMENT '税率（NULL=非課税）'";
            $pdo->exec($sql);
            echo '<p style="color: green;">✓ 請求項目テーブルに税率フィールドを追加しました</p>';
        } else {
            echo '<p style="color: blue;">ℹ 請求項目テーブルには既に税率フィールドが存在します</p>';
        }
    } catch (PDOException $e) {
        echo '<p style="color: orange;">⚠ 請求項目テーブルの確認でエラー: ' . $e->getMessage() . '</p>';
    }
    
    // コスト項目テーブル
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM `$cost_table` LIKE 'tax_rate'");
        if ($stmt->rowCount() == 0) {
            $sql = "ALTER TABLE `$cost_table` ADD COLUMN `tax_rate` DECIMAL(5,2) NULL DEFAULT NULL COMMENT '税率（NULL=非課税）'";
            $pdo->exec($sql);
            echo '<p style="color: green;">✓ コスト項目テーブルに税率フィールドを追加しました</p>';
        } else {
            echo '<p style="color: blue;">ℹ コスト項目テーブルには既に税率フィールドが存在します</p>';
        }
    } catch (PDOException $e) {
        echo '<p style="color: orange;">⚠ コスト項目テーブルの確認でエラー: ' . $e->getMessage() . '</p>';
    }
    
    // サービステーブル
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM `$service_table` LIKE 'tax_rate'");
        if ($stmt->rowCount() == 0) {
            $sql = "ALTER TABLE `$service_table` ADD COLUMN `tax_rate` DECIMAL(5,2) NULL DEFAULT NULL COMMENT '税率（NULL=非課税）'";
            $pdo->exec($sql);
            echo '<p style="color: green;">✓ サービステーブルに税率フィールドを追加しました</p>';
        } else {
            echo '<p style="color: blue;">ℹ サービステーブルには既に税率フィールドが存在します</p>';
        }
    } catch (PDOException $e) {
        echo '<p style="color: orange;">⚠ サービステーブルの確認でエラー: ' . $e->getMessage() . '</p>';
    }
    
    echo '<p style="color: green; font-weight: bold;">マイグレーションが完了しました！</p>';
    echo '<p>税率フィールドがNULL値を許可するように修正されました。</p>';
    
} catch (PDOException $e) {
    echo '<p style="color: red;">データベース接続エラー: ' . $e->getMessage() . '</p>';
    echo '<p>データベース設定を確認してください：</p>';
    echo '<ul>';
    echo '<li>ホスト: ' . $db_host . '</li>';
    echo '<li>データベース名: ' . $db_name . '</li>';
    echo '<li>ユーザー名: ' . $db_user . '</li>';
    echo '</ul>';
}

echo '<p><strong>注意:</strong> このファイルは実行完了後、セキュリティのため削除してください。</p>'; 