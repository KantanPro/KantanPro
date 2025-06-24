<?php
/**
 * unit_priceカラムの型変更とデータ移行
 */

// WordPress環境の読み込み
require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php');

echo "=== wp_ktp_supplier_skills unit_price型変更とデータ移行 ===\n";

try {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'ktp_supplier_skills';
    
    // バックアップテーブルの作成
    $backup_table = $table_name . '_backup_' . date('Ymd_His');
    echo "1. バックアップテーブルの作成: {$backup_table}\n";
    
    $wpdb->query("CREATE TABLE {$backup_table} LIKE {$table_name}");
    $wpdb->query("INSERT INTO {$backup_table} SELECT * FROM {$table_name}");
    
    echo "✓ バックアップ完了\n\n";
    
    // 現在のデータを取得
    echo "2. 既存データの取得中...\n";
    $skills = $wpdb->get_results("SELECT id, unit_price FROM {$table_name}");
    echo "✓ " . count($skills) . "件のデータを取得\n\n";
    
    // カラム型の変更
    echo "3. unit_priceカラムの型を変更中...\n";
    $alter_query = "ALTER TABLE {$table_name} 
                   MODIFY COLUMN unit_price DECIMAL(20,10) NOT NULL DEFAULT 0 COMMENT '単価'";
    
    $result = $wpdb->query($alter_query);
    
    if ($result !== false) {
        echo "✓ カラム型を変更しました\n\n";
        
        // データの更新
        echo "4. データの更新中...\n";
        $updated = 0;
        $errors = 0;
        
        foreach ($skills as $skill) {
            $price = floatval($skill->unit_price);
            $result = $wpdb->update(
                $table_name,
                array('unit_price' => $price),
                array('id' => $skill->id),
                array('%f'),
                array('%d')
            );
            
            if ($result !== false) {
                $updated++;
            } else {
                $errors++;
                echo "! ID {$skill->id} の更新に失敗: {$wpdb->last_error}\n";
            }
        }
        
        echo "✓ {$updated}件のデータを更新しました\n";
        if ($errors > 0) {
            echo "! {$errors}件の更新エラーが発生しました\n";
        }
        
        // 最終確認
        echo "\n5. 変更後の構造を確認中...\n";
        $column = $wpdb->get_row("SHOW COLUMNS FROM {$table_name} LIKE 'unit_price'");
        echo "新しい型: {$column->Type}\n";
        
        echo "\n=== 完了 ===\n";
        echo "バックアップテーブル: {$backup_table}\n";
        
    } else {
        echo "✗ カラム型の変更に失敗しました: " . $wpdb->last_error . "\n";
        exit(1);
    }
    
} catch (Exception $e) {
    echo "エラーが発生しました: " . $e->getMessage() . "\n";
    exit(1);
} 