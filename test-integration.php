<?php
/**
 * WordPress プラグイン統合テスト
 */

// WordPress環境の初期化
if (!defined('WP_USE_THEMES')) {
    define('WP_USE_THEMES', false);
}

// WordPress環境をロード
require_once('/Users/kantanpro/ktplocal/wp/wp-load.php');

echo "<h1>KantanPro プラグイン統合テスト</h1>\n";

// プラグインがアクティブかどうか確認
if (is_plugin_active('KantanPro/ktpwp.php')) {
    echo "<p>✓ KantanPro プラグインがアクティブです。</p>\n";
} else {
    echo "<p>ⓘ KantanPro プラグインの状態を確認できませんでした。</p>\n";
}

// 必要なクラスの読み込み確認
$required_classes = array(
    'KTPWP_Supplier_Skills' => 'includes/class-ktpwp-supplier-skills.php',
    'KTPWP_Supplier_Class' => 'includes/class-tab-supplier.php',
    'KTPWP_Supplier_Data' => 'includes/class-supplier-data.php'
);

echo "<h2>必要なクラスの読み込み確認</h2>\n";

foreach ($required_classes as $class_name => $file_path) {
    $full_path = dirname(__FILE__) . '/' . $file_path;
    
    if (file_exists($full_path)) {
        require_once $full_path;
        
        if (class_exists($class_name)) {
            echo "<p>✓ {$class_name} クラスが正常に読み込まれました。</p>\n";
        } else {
            echo "<p>✗ {$class_name} クラスの読み込みに失敗しました。</p>\n";
        }
    } else {
        echo "<p>✗ ファイル {$file_path} が見つかりません。</p>\n";
    }
}

// データベーステーブルの確認
echo "<h2>データベーステーブル確認</h2>\n";

global $wpdb;

$tables_to_check = array(
    'ktp_supplier' => '協力会社テーブル',
    'ktp_supplier_skills' => '職能テーブル'
);

foreach ($tables_to_check as $table_suffix => $description) {
    $table_name = $wpdb->prefix . $table_suffix;
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
    
    if ($table_exists) {
        echo "<p>✓ {$description} ({$table_name}) が存在します。</p>\n";
        
        // データ件数の確認
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        echo "<p>　データ件数: {$count}</p>\n";
    } else {
        echo "<p>ⓘ {$description} ({$table_name}) がまだ作成されていません。</p>\n";
    }
}

// 協力会社クラスの機能テスト
echo "<h2>協力会社クラス機能テスト</h2>\n";

if (class_exists('KTPWP_Supplier_Class')) {
    $supplier_class = new KTPWP_Supplier_Class();
    
    // 必要なメソッドの存在確認
    $required_methods = array(
        'Create_Table',
        'Update_Table',
        'View_Table',
        'handle_skills_operations'
    );
    
    foreach ($required_methods as $method) {
        if (method_exists($supplier_class, $method)) {
            echo "<p>✓ {$method} メソッドが実装されています。</p>\n";
        } else {
            echo "<p>✗ {$method} メソッドが見つかりません。</p>\n";
        }
    }
    
    // テーブル作成テスト
    try {
        $supplier_class->Create_Table('supplier');
        echo "<p>✓ 協力会社テーブル作成メソッドが正常に実行されました。</p>\n";
    } catch (Exception $e) {
        echo "<p>✗ 協力会社テーブル作成でエラーが発生しました: " . $e->getMessage() . "</p>\n";
    }
}

// 職能管理クラスの機能テスト
echo "<h2>職能管理クラス機能テスト</h2>\n";

if (class_exists('KTPWP_Supplier_Skills')) {
    $skills_manager = KTPWP_Supplier_Skills::get_instance();
    
    // 必要なメソッドの存在確認
    $required_methods = array(
        'create_table',
        'get_supplier_skills',
        'add_skill',
        'update_skill',
        'delete_skill',
        'get_skill',
        'render_skills_interface'
    );
    
    foreach ($required_methods as $method) {
        if (method_exists($skills_manager, $method)) {
            echo "<p>✓ {$method} メソッドが実装されています。</p>\n";
        } else {
            echo "<p>✗ {$method} メソッドが見つかりません。</p>\n";
        }
    }
    
    // テーブル作成テスト
    try {
        $result = $skills_manager->create_table();
        if ($result) {
            echo "<p>✓ 職能テーブル作成メソッドが正常に実行されました。</p>\n";
        } else {
            echo "<p>⚠ 職能テーブル作成メソッドが false を返しました。</p>\n";
        }
    } catch (Exception $e) {
        echo "<p>✗ 職能テーブル作成でエラーが発生しました: " . $e->getMessage() . "</p>\n";
    }
}

// WordPressフック確認
echo "<h2>WordPressフック確認</h2>\n";

// 職能削除フックの確認
$hook_name = 'ktpwp_supplier_deleted';
$has_hook = has_action($hook_name);

if ($has_hook) {
    echo "<p>✓ '{$hook_name}' フックが登録されています。</p>\n";
} else {
    echo "<p>ⓘ '{$hook_name}' フックが見つかりません。</p>\n";
}

// ローダークラスの確認
echo "<h2>ローダークラス確認</h2>\n";

$loader_file = dirname(__FILE__) . '/includes/class-ktpwp-loader.php';

if (file_exists($loader_file)) {
    $loader_content = file_get_contents($loader_file);
    
    if (strpos($loader_content, 'KTPWP_Supplier_Skills') !== false) {
        echo "<p>✓ ローダークラスに職能管理クラスが登録されています。</p>\n";
    } else {
        echo "<p>⚠ ローダークラスに職能管理クラスが見つかりません。</p>\n";
    }
} else {
    echo "<p>✗ ローダークラスファイルが見つかりません。</p>\n";
}

echo "<h2>統合テスト完了</h2>\n";
echo "<p>KantanPro プラグインの職能管理機能統合テストが完了しました。</p>\n";
echo "<p>問題が発見された場合は、該当する部分を修正してください。</p>\n";

// 管理画面へのリンク
if (current_user_can('manage_options')) {
    $admin_url = admin_url('admin.php?page=ktpwp&tab=supplier');
    echo "<p><a href='{$admin_url}' target='_blank'>→ KantanPro 協力会社管理画面を開く</a></p>\n";
}
?>
