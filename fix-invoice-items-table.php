<?php
/**
 * 請求項目テーブル修正スクリプト
 * 
 * 本番サイトで請求項目の保存が正常に動作するように、
 * テーブル構造を修正します。
 * 
 * @package KTPWP
 * @since 1.0.0
 */

// WordPressの読み込み
require_once('../../../wp-load.php');

// 管理者権限チェック
if (!current_user_can('manage_options')) {
    wp_die('このスクリプトを実行する権限がありません。');
}

global $wpdb;

echo "<h1>請求項目テーブル修正スクリプト</h1>\n";
echo "<p>実行開始時刻: " . date('Y-m-d H:i:s') . "</p>\n";

// 1. 請求項目テーブルの存在確認
$table_name = $wpdb->prefix . 'ktp_order_invoice_items';
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");

if (!$table_exists) {
    echo "<p style='color: red;'>エラー: テーブル {$table_name} が存在しません。</p>\n";
    echo "<p>プラグインの初期化を実行してください。</p>\n";
    exit;
}

echo "<p>✓ テーブル {$table_name} が存在します。</p>\n";

// 2. 現在のカラム構造を確認
$existing_columns = $wpdb->get_col("SHOW COLUMNS FROM `{$table_name}`", 0);
echo "<p>現在のカラム: " . implode(', ', $existing_columns) . "</p>\n";

// 3. 不要なカラムを削除
$unnecessary_columns = array('purchase', 'ordered');
foreach ($unnecessary_columns as $col_name) {
    if (in_array($col_name, $existing_columns, true)) {
        echo "<p>不要なカラム '{$col_name}' を削除中...</p>\n";
        $result = $wpdb->query("ALTER TABLE `{$table_name}` DROP COLUMN {$col_name}");
        if ($result === false) {
            echo "<p style='color: red;'>エラー: カラム {$col_name} の削除に失敗しました。エラー: " . $wpdb->last_error . "</p>\n";
        } else {
            echo "<p style='color: green;'>✓ カラム {$col_name} を削除しました。</p>\n";
        }
    } else {
        echo "<p>カラム '{$col_name}' は存在しないため、削除をスキップします。</p>\n";
    }
}

// 4. 必要なカラムが存在するか確認
$required_columns = array('id', 'order_id', 'product_name', 'price', 'unit', 'quantity', 'amount', 'remarks', 'sort_order', 'created_at', 'updated_at');
$current_columns = $wpdb->get_col("SHOW COLUMNS FROM `{$table_name}`", 0);

$missing_columns = array_diff($required_columns, $current_columns);
if (!empty($missing_columns)) {
    echo "<p style='color: orange;'>警告: 以下のカラムが不足しています: " . implode(', ', $missing_columns) . "</p>\n";
    
    // 不足しているカラムを追加
    foreach ($missing_columns as $col_name) {
        echo "<p>不足しているカラム '{$col_name}' を追加中...</p>\n";
        
        switch ($col_name) {
            case 'sort_order':
                $result = $wpdb->query("ALTER TABLE `{$table_name}` ADD COLUMN sort_order INT NOT NULL DEFAULT 0 AFTER remarks");
                break;
            case 'created_at':
                $result = $wpdb->query("ALTER TABLE `{$table_name}` ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER sort_order");
                break;
            case 'updated_at':
                $result = $wpdb->query("ALTER TABLE `{$table_name}` ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at");
                break;
            default:
                echo "<p style='color: red;'>エラー: カラム {$col_name} の追加方法が定義されていません。</p>\n";
                continue;
        }
        
        if ($result === false) {
            echo "<p style='color: red;'>エラー: カラム {$col_name} の追加に失敗しました。エラー: " . $wpdb->last_error . "</p>\n";
        } else {
            echo "<p style='color: green;'>✓ カラム {$col_name} を追加しました。</p>\n";
        }
    }
} else {
    echo "<p style='color: green;'>✓ 必要なカラムはすべて存在します。</p>\n";
}

// 5. データ型の修正
echo "<p>データ型を修正中...</p>\n";

// priceカラムをDECIMAL(10,2)に修正
$price_column = $wpdb->get_row("SHOW COLUMNS FROM `{$table_name}` WHERE Field = 'price'");
if ($price_column && $price_column->Type !== 'decimal(10,2)') {
    $result = $wpdb->query("ALTER TABLE `{$table_name}` MODIFY `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00");
    if ($result === false) {
        echo "<p style='color: red;'>エラー: priceカラムの修正に失敗しました。エラー: " . $wpdb->last_error . "</p>\n";
    } else {
        echo "<p style='color: green;'>✓ priceカラムをDECIMAL(10,2)に修正しました。</p>\n";
    }
}

// quantityカラムをDECIMAL(10,2)に修正
$quantity_column = $wpdb->get_row("SHOW COLUMNS FROM `{$table_name}` WHERE Field = 'quantity'");
if ($quantity_column && $quantity_column->Type !== 'decimal(10,2)') {
    $result = $wpdb->query("ALTER TABLE `{$table_name}` MODIFY `quantity` DECIMAL(10,2) NOT NULL DEFAULT 0.00");
    if ($result === false) {
        echo "<p style='color: red;'>エラー: quantityカラムの修正に失敗しました。エラー: " . $wpdb->last_error . "</p>\n";
    } else {
        echo "<p style='color: green;'>✓ quantityカラムをDECIMAL(10,2)に修正しました。</p>\n";
    }
}

// 6. 最終的なカラム構造を確認
$final_columns = $wpdb->get_col("SHOW COLUMNS FROM `{$table_name}`", 0);
echo "<p>修正後のカラム: " . implode(', ', $final_columns) . "</p>\n";

// 7. テーブルバージョンを更新
update_option('ktp_invoice_items_table_version', '2.2');
echo "<p style='color: green;'>✓ テーブルバージョンを2.2に更新しました。</p>\n";

// 8. サンプルデータの確認
$sample_data = $wpdb->get_results("SELECT * FROM `{$table_name}` LIMIT 5", ARRAY_A);
if (!empty($sample_data)) {
    echo "<p>サンプルデータ（最初の5件）:</p>\n";
    echo "<pre>" . print_r($sample_data, true) . "</pre>\n";
} else {
    echo "<p>テーブルにデータがありません。</p>\n";
}

echo "<p style='color: green; font-weight: bold;'>✓ 請求項目テーブルの修正が完了しました。</p>\n";
echo "<p>実行終了時刻: " . date('Y-m-d H:i:s') . "</p>\n";

// 9. 代替テーブルの確認と削除
$alt_table_name = $wpdb->prefix . 'ktp_invoice_item';
$alt_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$alt_table_name'");
if ($alt_table_exists) {
    echo "<p style='color: orange;'>警告: 代替テーブル {$alt_table_name} が存在します。</p>\n";
    echo "<p>このテーブルは不要です。削除する場合は以下のSQLを実行してください:</p>\n";
    echo "<code>DROP TABLE `{$alt_table_name}`;</code>\n";
}

echo "<p><a href='javascript:history.back()'>← 戻る</a></p>\n";
?> 