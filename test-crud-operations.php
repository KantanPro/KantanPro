<?php
/**
 * 職能CRUD操作テスト
 */

// WordPress環境の初期化
if (!defined('WP_USE_THEMES')) {
    define('WP_USE_THEMES', false);
}

// WordPress環境をロード
require_once('/Users/kantanpro/ktplocal/wp/wp-load.php');

// 必要なクラスファイルを読み込み
require_once dirname(__FILE__) . '/includes/class-ktpwp-supplier-skills.php';

echo "<h1>職能CRUD操作テスト</h1>\n";

// 職能管理クラスのインスタンス取得
$skills_manager = KTPWP_Supplier_Skills::get_instance();

if (!$skills_manager) {
    echo "<p>✗ 職能管理クラスのインスタンス取得に失敗しました。</p>\n";
    exit;
}

// まず、テスト用の協力会社を作成
global $wpdb;
$supplier_table = $wpdb->prefix . 'ktp_supplier';

// 協力会社テーブルが存在するか確認
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$supplier_table'");

if (!$table_exists) {
    echo "<p>ⓘ 協力会社テーブルが存在しないため、テスト用に作成します。</p>\n";
    
    // 協力会社データクラスを読み込んで作成
    require_once dirname(__FILE__) . '/includes/class-supplier-data.php';
    $supplier_data = new KTPWP_Supplier_Data();
    $supplier_data->create_table('supplier');
}

// テスト用協力会社の作成
$test_supplier_name = 'テスト協力会社 ' . date('Y-m-d H:i:s');
$test_supplier_id = $wpdb->insert(
    $supplier_table,
    array(
        'company_name' => $test_supplier_name,
        'name' => 'テスト担当者',
        'email' => 'test@example.com',
        'time' => current_time('mysql')
    ),
    array('%s', '%s', '%s', '%s')
);

if ($test_supplier_id) {
    $test_supplier_id = $wpdb->insert_id;
    echo "<p>✓ テスト用協力会社を作成しました。ID: {$test_supplier_id}</p>\n";
} else {
    echo "<p>✗ テスト用協力会社の作成に失敗しました。</p>\n";
    exit;
}

echo "<h2>1. 職能追加テスト</h2>\n";

// テスト用職能データ
$test_skills = array(
    array(
        'skill_name' => 'Webデザイン',
        'skill_description' => 'レスポンシブWebデザインの作成',
        'price' => 50000,
        'unit' => 'ページ',
        'category' => 'デザイン'
    ),
    array(
        'skill_name' => 'PHP開発',
        'skill_description' => 'WordPress プラグイン開発',
        'price' => 100000,
        'unit' => '機能',
        'category' => '開発'
    ),
    array(
        'skill_name' => 'データベース設計',
        'skill_description' => 'MySQLデータベースの設計・最適化',
        'price' => 80000,
        'unit' => 'プロジェクト',
        'category' => '設計'
    )
);

$added_skill_ids = array();

foreach ($test_skills as $skill) {
    $skill_id = $skills_manager->add_skill(
        $test_supplier_id,
        $skill['skill_name'],
        $skill['skill_description'],
        $skill['price'],
        $skill['unit'],
        $skill['category']
    );
    
    if ($skill_id) {
        $added_skill_ids[] = $skill_id;
        echo "<p>✓ 職能 '{$skill['skill_name']}' を追加しました。ID: {$skill_id}</p>\n";
    } else {
        echo "<p>✗ 職能 '{$skill['skill_name']}' の追加に失敗しました。</p>\n";
    }
}

echo "<h2>2. 職能取得テスト</h2>\n";

$retrieved_skills = $skills_manager->get_supplier_skills($test_supplier_id);

if (!empty($retrieved_skills)) {
    echo "<p>✓ 職能データを取得しました。件数: " . count($retrieved_skills) . "</p>\n";
    
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
    echo "<tr><th>ID</th><th>職能名</th><th>価格</th><th>単位</th><th>カテゴリー</th><th>説明</th></tr>\n";
    
    foreach ($retrieved_skills as $skill) {
        echo "<tr>";
        echo "<td>" . esc_html($skill['id']) . "</td>";
        echo "<td>" . esc_html($skill['skill_name']) . "</td>";
        echo "<td>" . number_format($skill['price']) . "円</td>";
        echo "<td>" . esc_html($skill['unit']) . "</td>";
        echo "<td>" . esc_html($skill['category']) . "</td>";
        echo "<td>" . esc_html($skill['skill_description']) . "</td>";
        echo "</tr>\n";
    }
    echo "</table>\n";
} else {
    echo "<p>✗ 職能データの取得に失敗しました。</p>\n";
}

echo "<h2>3. 職能更新テスト</h2>\n";

if (!empty($added_skill_ids)) {
    $first_skill_id = $added_skill_ids[0];
    
    $update_result = $skills_manager->update_skill(
        $first_skill_id,
        'Webデザイン（更新版）',
        'レスポンシブWebデザインの作成・修正',
        60000,
        'ページ',
        'デザイン'
    );
    
    if ($update_result) {
        echo "<p>✓ 職能ID {$first_skill_id} を更新しました。</p>\n";
        
        // 更新されたデータを取得して確認
        $updated_skill = $skills_manager->get_skill($first_skill_id);
        if ($updated_skill) {
            echo "<p>✓ 更新後の職能データ: {$updated_skill['skill_name']} - " . number_format($updated_skill['price']) . "円</p>\n";
        }
    } else {
        echo "<p>✗ 職能の更新に失敗しました。</p>\n";
    }
}

echo "<h2>4. HTMLインターフェーステスト</h2>\n";

$html_interface = $skills_manager->render_skills_interface($test_supplier_id);

if (!empty($html_interface)) {
    echo "<p>✓ HTMLインターフェースが生成されました。</p>\n";
    echo "<div style='border: 2px solid #ccc; padding: 15px; margin: 10px 0; background: #f9f9f9;'>\n";
    echo "<h3>生成されたインターフェース:</h3>\n";
    echo $html_interface;
    echo "</div>\n";
} else {
    echo "<p>✗ HTMLインターフェースの生成に失敗しました。</p>\n";
}

echo "<h2>5. 職能削除テスト</h2>\n";

if (!empty($added_skill_ids)) {
    $last_skill_id = end($added_skill_ids);
    
    $delete_result = $skills_manager->delete_skill($last_skill_id);
    
    if ($delete_result) {
        echo "<p>✓ 職能ID {$last_skill_id} を削除しました。</p>\n";
        
        // 削除後の職能数を確認
        $remaining_skills = $skills_manager->get_supplier_skills($test_supplier_id);
        echo "<p>✓ 削除後の職能数: " . count($remaining_skills) . "</p>\n";
    } else {
        echo "<p>✗ 職能の削除に失敗しました。</p>\n";
    }
}

echo "<h2>6. 協力会社削除時の職能自動削除テスト</h2>\n";

// 協力会社削除時の職能自動削除をテスト
$delete_supplier_result = $wpdb->delete(
    $supplier_table,
    array('id' => $test_supplier_id),
    array('%d')
);

if ($delete_supplier_result) {
    echo "<p>✓ テスト用協力会社を削除しました。</p>\n";
    
    // 削除アクションを手動で実行（通常は自動実行される）
    do_action('ktpwp_supplier_deleted', $test_supplier_id);
    
    // 職能が自動削除されたか確認
    $remaining_skills_after_supplier_delete = $skills_manager->get_supplier_skills($test_supplier_id);
    
    if (empty($remaining_skills_after_supplier_delete)) {
        echo "<p>✓ 協力会社削除時に職能も自動削除されました。</p>\n";
    } else {
        echo "<p>⚠ 協力会社削除後も職能が残っています。件数: " . count($remaining_skills_after_supplier_delete) . "</p>\n";
    }
} else {
    echo "<p>✗ テスト用協力会社の削除に失敗しました。</p>\n";
}

echo "<h2>テスト完了</h2>\n";
echo "<p>職能CRUD操作のテストが完了しました。</p>\n";
echo "<p><a href='#' onclick='location.reload();'>ページを再読み込み</a></p>\n";
?>
