<?php
/**
 * 協力会社タブでの職能管理セクション表示テスト
 */

// WordPress環境の初期化
if (!defined('WP_USE_THEMES')) {
    define('WP_USE_THEMES', false);
}

// WordPress環境をロード
require_once('/Users/kantanpro/ktplocal/wp/wp-load.php');

echo "<h1>協力会社タブ 職能管理セクション ブラウザテスト</h1>\n";

// クエリパラメータをGETで設定（協力会社選択状態をシミュレート）
$_GET['tab_name'] = 'supplier';

// 利用可能な協力会社を確認
global $wpdb;
$supplier_table = $wpdb->prefix . 'ktp_supplier';
$suppliers = $wpdb->get_results("SELECT id, company_name FROM $supplier_table ORDER BY id DESC LIMIT 5");

if (!empty($suppliers)) {
    $test_supplier = $suppliers[0];
    $_GET['data_id'] = $test_supplier->id;
    $_GET['query_post'] = 'update';
    
    echo "<h2>テスト対象協力会社: {$test_supplier->company_name} (ID: {$test_supplier->id})</h2>\n";
    
    // 協力会社タブクラスの実行
    require_once dirname(__FILE__) . '/includes/class-tab-supplier.php';
    $supplier_class = new KTPWP_Supplier_Class();
    
    echo "<h3>実際のView_Table出力:</h3>\n";
    echo "<div style='border: 2px solid #0073aa; padding: 15px; background: #f0f8ff;'>\n";
    
    // View_Tableメソッドを実行
    $result = $supplier_class->View_Table('supplier');
    
    if (!empty($result)) {
        echo $result;
    } else {
        echo "<p>⚠ View_Tableメソッドから結果が返されませんでした。</p>\n";
    }
    
    echo "</div>\n";
    
    // 職能セクションの個別テスト
    echo "<h3>職能セクション個別テスト:</h3>\n";
    
    require_once dirname(__FILE__) . '/includes/class-ktpwp-supplier-skills.php';
    $skills_manager = KTPWP_Supplier_Skills::get_instance();
    
    if ($skills_manager) {
        $skills_html = $skills_manager->render_skills_interface($test_supplier->id);
        
        if (!empty($skills_html)) {
            echo "<div style='border: 2px solid #28a745; padding: 15px; background: #f0fff0; margin: 15px 0;'>\n";
            echo "<h4>職能管理インターフェース:</h4>\n";
            echo $skills_html;
            echo "</div>\n";
        } else {
            echo "<p>⚠ 職能管理インターフェースが空です。</p>\n";
        }
    }
    
} else {
    echo "<p>⚠ 協力会社データがありません。まず協力会社を追加してください。</p>\n";
}

echo "<h3>ページ完了</h3>\n";
?>
