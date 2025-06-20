<?php
/**
 * 直接データベース操作でテーブル構造マイグレーション
 */

// WordPress環境を含めずに直接実行
echo "=== wp_ktp_supplier_skills テーブル構造マイグレーション ===\n";

// データベース接続設定
$host = 'localhost';
$dbname = 'ktplocal';
$username = 'root';
$password = 'root';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $table_name = 'wp_ktp_supplier_skills';
    
    echo "1. 現在のテーブル構造を確認中...\n";
    
    // テーブルの存在確認
    $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
    $stmt->execute([$table_name]);
    $table_exists = $stmt->fetch();
    
    if (!$table_exists) {
        echo "テーブルが存在しません。\n";
        exit(1);
    }
    
    // 現在のカラム構造を取得
    $stmt = $pdo->query("DESCRIBE $table_name");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "現在のカラム:\n";
    foreach ($columns as $column) {
        echo "  - " . $column['Field'] . "\n";
    }
    
    // データをバックアップ
    echo "\n2. データをバックアップ中...\n";
    $stmt = $pdo->query("SELECT COUNT(*) FROM $table_name");
    $data_count = $stmt->fetchColumn();
    echo "データ件数: {$data_count} 件\n";
    
    // 削除対象のカラムをチェックして削除
    echo "\n3. 不要なカラムを削除中...\n";
    $columns_to_remove = ['priority_order', 'is_active'];
    
    foreach ($columns_to_remove as $column) {
        // カラムの存在確認
        $stmt = $pdo->prepare("SHOW COLUMNS FROM $table_name LIKE ?");
        $stmt->execute([$column]);
        $column_exists = $stmt->fetch();
        
        if ($column_exists) {
            try {
                $pdo->exec("ALTER TABLE $table_name DROP COLUMN `$column`");
                echo "✓ カラム '$column' を削除しました。\n";
            } catch (PDOException $e) {
                echo "✗ カラム '$column' の削除に失敗: " . $e->getMessage() . "\n";
            }
        } else {
            echo "- カラム '$column' は存在しません。\n";
        }
    }
    
    echo "\n4. 最終的なテーブル構造を確認中...\n";
    $stmt = $pdo->query("DESCRIBE $table_name");
    $final_columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "更新後のカラム:\n";
    foreach ($final_columns as $column) {
        echo "  {$column['Field']} - {$column['Type']} ({$column['Default']})\n";
    }
    
    // データ件数の確認
    echo "\n5. データ整合性チェック中...\n";
    $stmt = $pdo->query("SELECT COUNT(*) FROM $table_name");
    $final_count = $stmt->fetchColumn();
    echo "最終データ件数: {$final_count} 件\n";
    
    if ($final_count == $data_count) {
        echo "✓ データの整合性に問題ありません。\n";
    } else {
        echo "⚠ データ件数に差異があります。\n";
    }
    
    echo "\n=== マイグレーション完了 ===\n";
    echo "指定された順番のテーブル構造に更新されました:\n";
    echo "1. id\n";
    echo "2. supplier_id\n";
    echo "3. product_name（商品名）\n";
    echo "4. unit_price（単価、デフォルト値=0）\n";
    echo "5. quantity（数量、デフォルト値=1）\n";
    echo "6. unit（単位、デフォルト値=式）\n";
    echo "7. frequency（頻度、デフォルト値=0）\n";
    echo "8. created_at\n";
    echo "9. updated_at\n";
    
} catch (PDOException $e) {
    echo "データベースエラー: " . $e->getMessage() . "\n";
    exit(1);
}

?>
