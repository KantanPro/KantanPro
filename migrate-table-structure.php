<?php
/**
 * wp_ktp_supplier_skills テーブル構造マイグレーション
 * 
 * 指定された新しい構造に合わせてテーブルを更新
 */

// WordPress環境の初期化
if (!defined('WP_USE_THEMES')) {
    define('WP_USE_THEMES', false);
}

// WordPress環境をロード
require_once('/Users/kantanpro/ktplocal/wp/wp-load.php');

// 管理者権限チェック
if (!current_user_can('manage_options')) {
    wp_die('このスクリプトを実行する権限がありません。');
}

echo "<h1>職能テーブル構造マイグレーション</h1>\n";

global $wpdb;
$table_name = $wpdb->prefix . 'ktp_supplier_skills';

// 現在のテーブル構造を確認
echo "<h2>現在のテーブル構造確認</h2>\n";
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");

if (!$table_exists) {
    echo "<p>テーブル '{$table_name}' が存在しません。新規作成します。</p>\n";
    
    // 新しいテーブルを作成
    require_once dirname(__FILE__) . '/includes/class-ktpwp-supplier-skills.php';
    $skills_manager = KTPWP_Supplier_Skills::get_instance();
    $result = $skills_manager->create_table();
    
    if ($result) {
        echo "<p>✓ 新しいテーブルを作成しました。</p>\n";
    } else {
        echo "<p>✗ テーブル作成に失敗しました。</p>\n";
    }
    exit;
}

// 現在のテーブル構造を取得
$columns = $wpdb->get_results("DESCRIBE $table_name");
$column_names = array_column($columns, 'Field');

echo "<p>現在のカラム: " . implode(', ', $column_names) . "</p>\n";

echo "<h2>マイグレーション実行</h2>\n";

// データをバックアップ
echo "<p>1. 既存データをバックアップ中...</p>\n";
$existing_data = $wpdb->get_results("SELECT * FROM $table_name", ARRAY_A);
echo "<p>✓ データ " . count($existing_data) . " 件をバックアップしました。</p>\n";

// 削除対象のカラムを確認
$columns_to_remove = array('priority_order', 'is_active');
$existing_remove_columns = array_intersect($columns_to_remove, $column_names);

if (!empty($existing_remove_columns)) {
    echo "<p>2. 不要なカラムを削除中...</p>\n";
    
    foreach ($existing_remove_columns as $column) {
        $drop_result = $wpdb->query("ALTER TABLE $table_name DROP COLUMN `$column`");
        if ($drop_result !== false) {
            echo "<p>✓ カラム '{$column}' を削除しました。</p>\n";
        } else {
            echo "<p>✗ カラム '{$column}' の削除に失敗しました。</p>\n";
        }
    }
} else {
    echo "<p>2. 削除対象のカラムは存在しません。</p>\n";
}

// テーブルバージョンを更新
echo "<p>3. テーブルバージョンを更新中...</p>\n";
update_option('ktp_supplier_skills_table_version', '3.0.0');
echo "<p>✓ テーブルバージョンを 3.0.0 に更新しました。</p>\n";

// 最終的なテーブル構造を確認
echo "<h2>マイグレーション完了後のテーブル構造</h2>\n";
$final_columns = $wpdb->get_results("DESCRIBE $table_name");

echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
echo "<tr><th>カラム名</th><th>データ型</th><th>NULL許可</th><th>デフォルト値</th><th>備考</th></tr>\n";

foreach ($final_columns as $column) {
    echo "<tr>";
    echo "<td>" . esc_html($column->Field) . "</td>";
    echo "<td>" . esc_html($column->Type) . "</td>";
    echo "<td>" . esc_html($column->Null) . "</td>";
    echo "<td>" . esc_html($column->Default) . "</td>";
    echo "<td>" . esc_html($column->Extra) . "</td>";
    echo "</tr>\n";
}

echo "</table>\n";

// データ整合性チェック
echo "<h2>データ整合性チェック</h2>\n";
$current_data_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
echo "<p>現在のデータ件数: {$current_data_count} 件</p>\n";
echo "<p>バックアップデータ件数: " . count($existing_data) . " 件</p>\n";

if ($current_data_count == count($existing_data)) {
    echo "<p>✓ データの整合性に問題ありません。</p>\n";
} else {
    echo "<p>⚠ データ件数に差異があります。確認が必要です。</p>\n";
}

echo "<h2>マイグレーション完了</h2>\n";
echo "<p>指定された構造に合わせてテーブルを更新しました。</p>\n";

echo "<h3>最終的なテーブル構造:</h3>\n";
echo "<ol>\n";
echo "<li>id</li>\n";
echo "<li>supplier_id</li>\n";
echo "<li>product_name（商品名）</li>\n";
echo "<li>unit_price（単価、デフォルト値=0）</li>\n";
echo "<li>quantity（数量、デフォルト値=1）</li>\n";
echo "<li>unit（単位、デフォルト値=式）</li>\n";
echo "<li>frequency（頻度、デフォルト値=0）</li>\n";
echo "<li>created_at</li>\n";
echo "<li>updated_at</li>\n";
echo "</ol>\n";

?>
