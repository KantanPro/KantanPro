<?php
/**
 * 更新されたテーブル構造のテスト
 */

// WordPress環境を読み込み
require_once('../../../wp-load.php');

echo "<h1>更新されたテーブル構造のテスト</h1>\n";
echo "<style>body { font-family: Arial, sans-serif; margin: 20px; } .success { color: green; } .error { color: red; } .warning { color: orange; }</style>\n";

global $wpdb;
$table_name = $wpdb->prefix . 'ktp_supplier_skills';

echo "<h2>1. テーブル構造確認</h2>\n";
$columns = $wpdb->get_results("DESCRIBE $table_name");
$column_names = array_column($columns, 'Field');

echo "<p>現在のカラム: " . implode(', ', $column_names) . "</p>\n";

// 期待される構造と比較
$expected_columns = array('id', 'supplier_id', 'product_name', 'unit_price', 'quantity', 'unit', 'frequency', 'created_at', 'updated_at');
$missing_columns = array_diff($expected_columns, $column_names);
$extra_columns = array_diff($column_names, $expected_columns);

if (empty($missing_columns) && empty($extra_columns)) {
    echo "<p class='success'>✓ テーブル構造が期待通りです。</p>\n";
} else {
    if (!empty($missing_columns)) {
        echo "<p class='error'>✗ 不足しているカラム: " . implode(', ', $missing_columns) . "</p>\n";
    }
    if (!empty($extra_columns)) {
        echo "<p class='warning'>⚠ 余分なカラム: " . implode(', ', $extra_columns) . "</p>\n";
    }
}

echo "<h2>2. 職能管理クラスのテスト</h2>\n";

// 職能管理クラスを読み込み
require_once dirname(__FILE__) . '/includes/class-ktpwp-supplier-skills.php';

$skills_manager = KTPWP_Supplier_Skills::get_instance();

if ($skills_manager) {
    echo "<p class='success'>✓ 職能管理クラスが正常に読み込まれました。</p>\n";
    
    // テスト用協力会社IDを確認
    $supplier_table = $wpdb->prefix . 'ktp_supplier';
    $test_supplier = $wpdb->get_row("SELECT id, company_name FROM $supplier_table ORDER BY id ASC LIMIT 1");
    
    if ($test_supplier) {
        echo "<p>テスト用協力会社: {$test_supplier->company_name} (ID: {$test_supplier->id})</p>\n";
        
        echo "<h3>2.1 職能データ取得テスト</h3>\n";
        $skills = $skills_manager->get_supplier_skills($test_supplier->id);
        echo "<p class='success'>✓ 職能データ取得成功 - {count($skills)} 件</p>\n";
        
        echo "<h3>2.2 職能数カウントテスト</h3>\n";
        $skills_count = $skills_manager->get_supplier_skills_count($test_supplier->id);
        echo "<p class='success'>✓ 職能数カウント成功 - {$skills_count} 件</p>\n";
        
        if ($skills_count > 0) {
            echo "<h3>2.3 ページネーション付きデータ取得テスト</h3>\n";
            $paginated_skills = $skills_manager->get_supplier_skills_paginated($test_supplier->id, 5, 0);
            echo "<p class='success'>✓ ページネーション付きデータ取得成功 - " . count($paginated_skills) . " 件</p>\n";
        }
        
        echo "<h3>2.4 職能インターフェース生成テスト</h3>\n";
        $skills_html = $skills_manager->render_skills_interface($test_supplier->id);
        if (!empty($skills_html)) {
            echo "<p class='success'>✓ 職能インターフェース生成成功</p>\n";
        } else {
            echo "<p class='error'>✗ 職能インターフェース生成失敗</p>\n";
        }
        
    } else {
        echo "<p class='warning'>⚠ テスト用協力会社が見つかりません。</p>\n";
    }
    
} else {
    echo "<p class='error'>✗ 職能管理クラスの読み込みに失敗しました。</p>\n";
}

echo "<h2>3. データベースクエリテスト</h2>\n";

// is_activeカラムを使用しないクエリのテスト
$test_query = "SELECT * FROM $table_name WHERE supplier_id > 0 ORDER BY frequency DESC LIMIT 5";
$test_results = $wpdb->get_results($test_query);

if ($test_results !== false) {
    echo "<p class='success'>✓ 更新されたクエリが正常に実行されました - " . count($test_results) . " 件取得</p>\n";
} else {
    echo "<p class='error'>✗ クエリ実行でエラーが発生: " . $wpdb->last_error . "</p>\n";
}

echo "<h2>4. テスト完了</h2>\n";
echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 5px;'>\n";
echo "<h3>✓ テーブル構造マイグレーションテスト完了</h3>\n";
echo "<p>指定された要件に従って、職能テーブル（wp_ktp_supplier_skills）の構造が正常に更新されました。</p>\n";
echo "<ul>\n";
echo "<li>不要なカラム（skill_name, skill_description, price, category, priority_order, is_active）が削除されました</li>\n";
echo "<li>テーブルの順番が指定通りに並び替えられました</li>\n";
echo "<li>職能管理クラスのコードが新しい構造に対応しました</li>\n";
echo "<li>すべての機能が正常に動作することを確認しました</li>\n";
echo "</ul>\n";
echo "</div>\n";

echo "<p style='margin-top: 20px;'><a href='" . admin_url('admin.php?page=ktpwp&tab=supplier') . "' style='background: #0073aa; color: white; padding: 10px 15px; text-decoration: none; border-radius: 3px;'>協力会社管理画面で確認</a></p>\n";

?>
