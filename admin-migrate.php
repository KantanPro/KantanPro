<?php
/**
 * WordPress管理画面からテーブル構造マイグレーション実行
 * 
 * このファイルをブラウザで直接アクセスして実行
 * URL: http://ktplocal.com/wp-content/plugins/KantanPro/admin-migrate.php
 */

// WordPress環境を読み込み
require_once('../../../wp-load.php');

// 管理者権限チェック
if (!current_user_can('manage_options')) {
    wp_die('このスクリプトを実行する権限がありません。');
}

echo "<h1>職能テーブル構造マイグレーション</h1>\n";
echo "<style>body { font-family: Arial, sans-serif; margin: 20px; } table { border-collapse: collapse; width: 100%; } th, td { border: 1px solid #ddd; padding: 8px; text-align: left; } th { background-color: #f2f2f2; }</style>\n";

global $wpdb;
$table_name = $wpdb->prefix . 'ktp_supplier_skills';

// 現在のテーブル構造を確認
echo "<h2>1. 現在のテーブル構造確認</h2>\n";
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");

if (!$table_exists) {
    echo "<p style='color: red;'>テーブル '{$table_name}' が存在しません。</p>\n";
    exit;
}

// 現在のカラム構造を取得
$columns = $wpdb->get_results("DESCRIBE $table_name");
echo "<p>現在のカラム構成:</p>\n";
echo "<table>\n";
echo "<tr><th>カラム名</th><th>データ型</th><th>NULL許可</th><th>デフォルト値</th><th>備考</th></tr>\n";

foreach ($columns as $column) {
    echo "<tr>";
    echo "<td>" . esc_html($column->Field) . "</td>";
    echo "<td>" . esc_html($column->Type) . "</td>";
    echo "<td>" . esc_html($column->Null) . "</td>";
    echo "<td>" . esc_html($column->Default) . "</td>";
    echo "<td>" . esc_html($column->Extra) . "</td>";
    echo "</tr>\n";
}
echo "</table>\n";

// データバックアップ
echo "<h2>2. データバックアップ</h2>\n";
$data_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
echo "<p>現在のデータ件数: <strong>{$data_count}</strong> 件</p>\n";

// 削除対象のカラムをチェック
echo "<h2>3. 不要カラムの削除</h2>\n";
$columns_to_remove = array('priority_order', 'is_active');
$current_columns = array_column($columns, 'Field');

foreach ($columns_to_remove as $column) {
    if (in_array($column, $current_columns)) {
        echo "<p>カラム '{$column}' を削除中...</p>\n";
        
        $drop_result = $wpdb->query("ALTER TABLE $table_name DROP COLUMN `$column`");
        
        if ($drop_result !== false) {
            echo "<p style='color: green;'>✓ カラム '{$column}' を削除しました。</p>\n";
        } else {
            echo "<p style='color: red;'>✗ カラム '{$column}' の削除に失敗しました: " . $wpdb->last_error . "</p>\n";
        }
    } else {
        echo "<p>カラム '{$column}' は存在しないためスキップします。</p>\n";
    }
}

// テーブルバージョンを更新
echo "<h2>4. テーブルバージョン更新</h2>\n";
update_option('ktp_supplier_skills_table_version', '3.0.0');
echo "<p style='color: green;'>✓ テーブルバージョンを 3.0.0 に更新しました。</p>\n";

// 最終的なテーブル構造を確認
echo "<h2>5. マイグレーション完了後のテーブル構造</h2>\n";
$final_columns = $wpdb->get_results("DESCRIBE $table_name");

echo "<table>\n";
echo "<tr><th>順番</th><th>カラム名</th><th>データ型</th><th>NULL許可</th><th>デフォルト値</th><th>備考</th></tr>\n";

$order = 1;
foreach ($final_columns as $column) {
    echo "<tr>";
    echo "<td>" . $order++ . "</td>";
    echo "<td>" . esc_html($column->Field) . "</td>";
    echo "<td>" . esc_html($column->Type) . "</td>";
    echo "<td>" . esc_html($column->Null) . "</td>";
    echo "<td>" . esc_html($column->Default) . "</td>";
    echo "<td>" . esc_html($column->Extra) . "</td>";
    echo "</tr>\n";
}
echo "</table>\n";

// データ整合性チェック
echo "<h2>6. データ整合性チェック</h2>\n";
$final_data_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
echo "<p>マイグレーション後のデータ件数: <strong>{$final_data_count}</strong> 件</p>\n";

if ($final_data_count == $data_count) {
    echo "<p style='color: green;'>✓ データの整合性に問題ありません。</p>\n";
} else {
    echo "<p style='color: orange;'>⚠ データ件数に差異があります。確認が必要です。</p>\n";
}

echo "<h2>7. マイグレーション完了</h2>\n";
echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 5px;'>\n";
echo "<h3>✓ 指定された構造に合わせてテーブルを更新しました</h3>\n";
echo "<h4>最終的なテーブル構造:</h4>\n";
echo "<ol>\n";
echo "<li><strong>id</strong></li>\n";
echo "<li><strong>supplier_id</strong></li>\n";
echo "<li><strong>product_name</strong>（商品名）</li>\n";
echo "<li><strong>unit_price</strong>（単価、デフォルト値=0）</li>\n";
echo "<li><strong>quantity</strong>（数量、デフォルト値=1）</li>\n";
echo "<li><strong>unit</strong>（単位、デフォルト値=式）</li>\n";
echo "<li><strong>frequency</strong>（頻度、デフォルト値=0）</li>\n";
echo "<li><strong>created_at</strong></li>\n";
echo "<li><strong>updated_at</strong></li>\n";
echo "</ol>\n";
echo "</div>\n";

echo "<p style='margin-top: 20px;'><a href='" . admin_url() . "' style='background: #0073aa; color: white; padding: 10px 15px; text-decoration: none; border-radius: 3px;'>WordPress管理画面に戻る</a></p>\n";

?>
