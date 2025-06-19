<?php
/**
 * 職能管理セクション表示デバッグ
 */

// WordPress環境の初期化
if (!defined('WP_USE_THEMES')) {
    define('WP_USE_THEMES', false);
}

// WordPress環境をロード
require_once('/Users/kantanpro/ktplocal/wp/wp-load.php');

echo "<h1>職能管理セクション表示デバッグ</h1>\n";

// 現在のURLパラメータを確認
echo "<h2>現在のURLパラメータ:</h2>\n";
echo "<ul>\n";
echo "<li>tab_name: " . (isset($_GET['tab_name']) ? esc_html($_GET['tab_name']) : 'なし') . "</li>\n";
echo "<li>data_id: " . (isset($_GET['data_id']) ? esc_html($_GET['data_id']) : 'なし') . "</li>\n";
echo "<li>query_post: " . (isset($_GET['query_post']) ? esc_html($_GET['query_post']) : 'なし') . "</li>\n";
echo "</ul>\n";

// 協力会社データを確認
global $wpdb;
$supplier_table = $wpdb->prefix . 'ktp_supplier';
$suppliers = $wpdb->get_results("SELECT id, company_name FROM $supplier_table ORDER BY id DESC LIMIT 5");

if (!empty($suppliers)) {
    echo "<h2>利用可能な協力会社データ:</h2>\n";
    echo "<ul>\n";
    foreach ($suppliers as $supplier) {
        $test_url = "http://ktplocal.com/wp-admin/admin.php?page=ktpwp&tab=supplier&data_id={$supplier->id}&query_post=update";
        echo "<li>";
        echo "ID: {$supplier->id} - " . esc_html($supplier->company_name);
        echo " <a href='{$test_url}' target='_blank'>[詳細表示]</a>";
        echo "</li>\n";
    }
    echo "</ul>\n";
    
    // 最初の協力会社で職能管理セクションのテスト
    $test_supplier_id = $suppliers[0]->id;
    echo "<h2>職能管理セクションテスト (ID: {$test_supplier_id}):</h2>\n";
    
    // 職能管理クラスのテスト
    require_once dirname(__FILE__) . '/includes/class-ktpwp-supplier-skills.php';
    $skills_manager = KTPWP_Supplier_Skills::get_instance();
    
    if ($skills_manager) {
        echo "<p>✓ 職能管理クラスが読み込まれました。</p>\n";
        
        // 既存の職能データを確認
        $existing_skills = $skills_manager->get_supplier_skills($test_supplier_id);
        echo "<p>既存職能数: " . count($existing_skills) . "</p>\n";
        
        if (empty($existing_skills)) {
            // テスト用職能データを追加
            $test_skill_id = $skills_manager->add_skill(
                $test_supplier_id,
                'テスト職能',
                'デバッグ用のテスト職能です',
                50000,
                '時間',
                'テスト'
            );
            
            if ($test_skill_id) {
                echo "<p>✓ テスト用職能を追加しました (ID: {$test_skill_id})</p>\n";
            } else {
                echo "<p>✗ テスト用職能の追加に失敗しました</p>\n";
            }
        }
        
        // HTMLインターフェースの生成テスト
        $skills_html = $skills_manager->render_skills_interface($test_supplier_id);
        
        if (!empty($skills_html)) {
            echo "<p>✓ 職能管理HTMLが生成されました（長さ: " . strlen($skills_html) . " 文字）</p>\n";
            echo "<div style='border: 2px solid #007cba; padding: 10px; margin: 10px 0; background: #f0f8ff;'>\n";
            echo "<h3>生成されたHTML:</h3>\n";
            echo $skills_html;
            echo "</div>\n";
        } else {
            echo "<p>✗ 職能管理HTMLの生成に失敗しました</p>\n";
        }
        
    } else {
        echo "<p>✗ 職能管理クラスの取得に失敗しました</p>\n";
    }
    
} else {
    echo "<p>⚠ 協力会社データがありません。まず協力会社を追加してください。</p>\n";
}

// 現在の協力会社タブの処理を確認
echo "<h2>協力会社タブ処理の確認:</h2>\n";

// View_Tableメソッドのシミュレーション（一部）
$query_id = isset($_GET['data_id']) ? absint($_GET['data_id']) : null;
echo "<p>現在のquery_id: " . ($query_id ? $query_id : 'なし') . "</p>\n";

if ($query_id && is_numeric($query_id)) {
    echo "<p>✓ 協力会社が選択されています。職能管理セクションが表示されるはずです。</p>\n";
    
    // 実際にView_Tableメソッドを呼び出してテスト
    require_once dirname(__FILE__) . '/includes/class-tab-supplier.php';
    $supplier_class = new KTPWP_Supplier_Class();
    
    echo "<h3>実際のView_Table出力テスト:</h3>\n";
    echo "<div style='border: 1px solid #ccc; padding: 10px; background: #f9f9f9; max-height: 400px; overflow: auto;'>\n";
    
    // 出力を キャプチャ
    ob_start();
    $result = $supplier_class->View_Table('supplier');
    $captured_output = ob_get_clean();
    
    if (!empty($captured_output)) {
        echo "<h4>キャプチャされた出力:</h4>\n";
        echo "<pre>" . esc_html($captured_output) . "</pre>\n";
    }
    
    if (!empty($result)) {
        echo "<h4>戻り値の一部:</h4>\n";
        echo "<pre>" . esc_html(substr($result, 0, 500)) . "...</pre>\n";
        
        // 職能管理セクションが含まれているか確認
        if (strpos($result, '職能') !== false || strpos($result, 'skill') !== false) {
            echo "<p>✓ 戻り値に職能関連のコンテンツが含まれています。</p>\n";
        } else {
            echo "<p>✗ 戻り値に職能関連のコンテンツが含まれていません。</p>\n";
        }
    } else {
        echo "<p>✗ View_Tableメソッドから戻り値がありません。</p>\n";
    }
    
    echo "</div>\n";
    
} else {
    echo "<p>⚠ 協力会社が選択されていません。協力会社の詳細を表示するには、協力会社リストから選択してください。</p>\n";
    
    if (!empty($suppliers)) {
        $first_supplier = $suppliers[0];
        $detail_url = "http://ktplocal.com/wp-admin/admin.php?page=ktpwp&tab=supplier&data_id={$first_supplier->id}&query_post=update";
        echo "<p><strong>テスト用リンク:</strong> <a href='{$detail_url}' target='_blank'>{$first_supplier->company_name}の詳細を表示</a></p>\n";
    }
}

echo "<h2>解決策:</h2>\n";
echo "<div style='background: #e7f3ff; padding: 15px; border-left: 4px solid #0073aa;'>\n";
echo "<ol>\n";
echo "<li>協力会社リストから協力会社を選択すると職能管理セクションが表示されます</li>\n";
echo "<li>職能管理セクションは協力会社リストの下、詳細フォームの上に配置されています</li>\n";
echo "<li>協力会社が選択されていない場合は表示されません（仕様通り）</li>\n";
echo "</ol>\n";
echo "</div>\n";

echo "<h2>デバッグ完了</h2>\n";
?>
