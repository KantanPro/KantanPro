<?php
/**
 * データベーステーブル作成テスト
 */

// WordPress環境の初期化
if (!defined('WP_USE_THEMES')) {
    define('WP_USE_THEMES', false);
}

// WordPress環境をロード
require_once('/Users/kantanpro/ktplocal/wp/wp-load.php');

// 必要なクラスファイルを読み込み
require_once dirname(__FILE__) . '/includes/class-ktpwp-supplier-skills.php';

echo "<h1>データベーステーブル作成テスト</h1>\n";

// 職能管理クラスのテスト
$skills_manager = KTPWP_Supplier_Skills::get_instance();

if ($skills_manager) {
    echo "<p>✓ 職能管理クラスのインスタンスを取得しました。</p>\n";
    
    // テーブル作成テスト
    $result = $skills_manager->create_table();
    
    if ($result) {
        echo "<p>✓ 職能テーブルが正常に作成されました。</p>\n";
        
        // テーブルの存在確認
        global $wpdb;
        $table_name = $wpdb->prefix . 'ktp_supplier_skills';
        
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
        
        if ($table_exists) {
            echo "<p>✓ テーブル '{$table_name}' が存在します。</p>\n";
            
            // テーブル構造の確認
            $table_structure = $wpdb->get_results("DESCRIBE $table_name");
            
            if (!empty($table_structure)) {
                echo "<p>✓ テーブル構造:</p>\n";
                echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
                echo "<tr><th>フィールド</th><th>型</th><th>NULL</th><th>キー</th><th>デフォルト</th></tr>\n";
                
                foreach ($table_structure as $column) {
                    echo "<tr>";
                    echo "<td>" . esc_html($column->Field) . "</td>";
                    echo "<td>" . esc_html($column->Type) . "</td>";
                    echo "<td>" . esc_html($column->Null) . "</td>";
                    echo "<td>" . esc_html($column->Key) . "</td>";
                    echo "<td>" . esc_html($column->Default) . "</td>";
                    echo "</tr>\n";
                }
                echo "</table>\n";
            }
            
        } else {
            echo "<p>✗ テーブル '{$table_name}' が見つかりません。</p>\n";
        }
        
    } else {
        echo "<p>✗ 職能テーブルの作成に失敗しました。</p>\n";
    }
    
} else {
    echo "<p>✗ 職能管理クラスのインスタンス取得に失敗しました。</p>\n";
}

// 協力会社テーブルの確認
echo "<h2>協力会社テーブルの確認</h2>\n";

global $wpdb;
$supplier_table = $wpdb->prefix . 'ktp_supplier';
$supplier_exists = $wpdb->get_var("SHOW TABLES LIKE '$supplier_table'");

if ($supplier_exists) {
    echo "<p>✓ 協力会社テーブル '{$supplier_table}' が存在します。</p>\n";
    
    // サンプル協力会社データの確認
    $sample_suppliers = $wpdb->get_results("SELECT id, company_name FROM $supplier_table LIMIT 3");
    
    if (!empty($sample_suppliers)) {
        echo "<p>✓ 協力会社データが存在します:</p>\n";
        echo "<ul>\n";
        foreach ($sample_suppliers as $supplier) {
            echo "<li>ID: {$supplier->id}, 会社名: " . esc_html($supplier->company_name) . "</li>\n";
        }
        echo "</ul>\n";
    } else {
        echo "<p>ⓘ 協力会社データがまだありません。</p>\n";
    }
    
} else {
    echo "<p>ⓘ 協力会社テーブルがまだ作成されていません。</p>\n";
}

echo "<h2>テスト完了</h2>\n";
echo "<p>データベーステーブルの作成テストが完了しました。</p>\n";
?>
