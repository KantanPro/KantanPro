<?php
/**
 * プラグイン状態確認スクリプト
 */

// WordPress環境を読み込み
require_once('../../../wp-load.php');

// 権限チェック
if (!current_user_can('edit_posts') && !current_user_can('ktpwp_access')) {
    die('権限がありません');
}

echo "<h1>KantanPro プラグイン状態確認</h1>";

// 1. プラグインの有効化状態
echo "<h2>1. プラグイン有効化状態</h2>";
$active_plugins = get_option('active_plugins');
$ktpwp_active = false;
foreach ($active_plugins as $plugin) {
    if (strpos($plugin, 'ktpwp.php') !== false) {
        $ktpwp_active = true;
        break;
    }
}
echo "<p>KantanPro プラグイン有効: " . ($ktpwp_active ? "はい" : "いいえ") . "</p>";

// 2. プラグインバージョン
echo "<h2>2. プラグインバージョン</h2>";
$plugin_version = defined('KANTANPRO_PLUGIN_VERSION') ? KANTANPRO_PLUGIN_VERSION : '未定義';
echo "<p>プラグインバージョン: {$plugin_version}</p>";

// 3. データベースバージョン
echo "<h2>3. データベースバージョン</h2>";
$db_version = get_option('ktpwp_db_version', '0.0.0');
echo "<p>DBバージョン: {$db_version}</p>";

// 4. マイグレーション状態
echo "<h2>4. マイグレーション状態</h2>";
$migration_completed = get_option('ktpwp_department_migration_completed', '0');
echo "<p>部署マイグレーション完了: " . ($migration_completed ? "はい" : "いいえ") . "</p>";

// 5. テーブル存在確認
echo "<h2>5. テーブル存在確認</h2>";
global $wpdb;

$client_table = $wpdb->prefix . 'ktp_client';
$department_table = $wpdb->prefix . 'ktp_department';

$client_table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$client_table}'");
$department_table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$department_table}'");

echo "<p>顧客テーブル存在: " . ($client_table_exists ? "はい" : "いいえ") . "</p>";
echo "<p>部署テーブル存在: " . ($department_table_exists ? "はい" : "いいえ") . "</p>";

// 6. カラム存在確認
echo "<h2>6. カラム存在確認</h2>";
if ($client_table_exists) {
    $client_columns = $wpdb->get_col("SHOW COLUMNS FROM `{$client_table}`");
    $has_selected_department_id = in_array('selected_department_id', $client_columns);
    echo "<p>顧客テーブルのselected_department_idカラム: " . ($has_selected_department_id ? "存在" : "不存在") . "</p>";
}

if ($department_table_exists) {
    $department_columns = $wpdb->get_col("SHOW COLUMNS FROM `{$department_table}`");
    $has_is_selected = in_array('is_selected', $department_columns);
    echo "<p>部署テーブルのis_selectedカラム: " . ($has_is_selected ? "存在" : "不存在") . "</p>";
}

// 7. 関数存在確認
echo "<h2>7. 関数存在確認</h2>";
$functions_to_check = array(
    'ktpwp_create_department_table',
    'ktpwp_add_department_selection_column',
    'ktpwp_add_client_selected_department_column',
    'ktpwp_initialize_selected_department'
);

foreach ($functions_to_check as $function) {
    $exists = function_exists($function);
    echo "<p>{$function}: " . ($exists ? "存在" : "不存在") . "</p>";
}

// 8. クラス存在確認
echo "<h2>8. クラス存在確認</h2>";
$classes_to_check = array(
    'KTPWP_Department_Manager',
    'KTPWP_Client_DB'
);

foreach ($classes_to_check as $class) {
    $exists = class_exists($class);
    echo "<p>{$class}: " . ($exists ? "存在" : "不存在") . "</p>";
}

// 9. プラグイン再有効化の必要性判定
echo "<h2>9. プラグイン再有効化の必要性</h2>";
$needs_reactivation = false;
$issues = array();

if (!$ktpwp_active) {
    $needs_reactivation = true;
    $issues[] = "プラグインが無効化されています";
}

if (!$client_table_exists || !$department_table_exists) {
    $needs_reactivation = true;
    $issues[] = "必要なテーブルが存在しません";
}

if ($client_table_exists && !$has_selected_department_id) {
    $needs_reactivation = true;
    $issues[] = "顧客テーブルにselected_department_idカラムが存在しません";
}

if ($department_table_exists && !$has_is_selected) {
    $needs_reactivation = true;
    $issues[] = "部署テーブルにis_selectedカラムが存在しません";
}

if ($migration_completed !== '1') {
    $needs_reactivation = true;
    $issues[] = "部署マイグレーションが完了していません";
}

if ($needs_reactivation) {
    echo "<p style='color: red; font-weight: bold;'>⚠️ プラグインの再有効化が必要です</p>";
    echo "<p>問題点:</p>";
    echo "<ul>";
    foreach ($issues as $issue) {
        echo "<li>{$issue}</li>";
    }
    echo "</ul>";
    
    echo "<h3>再有効化手順:</h3>";
    echo "<ol>";
    echo "<li>WordPress管理画面の「プラグイン」ページに移動</li>";
    echo "<li>KantanProプラグインを無効化</li>";
    echo "<li>KantanProプラグインを再有効化</li>";
    echo "<li>このページを再読み込みして状態を確認</li>";
    echo "</ol>";
} else {
    echo "<p style='color: green; font-weight: bold;'>✅ プラグインの再有効化は不要です</p>";
    echo "<p>すべての必要なテーブルとカラムが存在し、マイグレーションも完了しています。</p>";
}

// 10. 手動マイグレーション実行オプション
echo "<h2>10. 手動マイグレーション実行</h2>";
if (isset($_GET['run_migration']) && $_GET['run_migration'] === '1') {
    echo "<h3>マイグレーション実行中...</h3>";
    
    try {
        // 部署テーブルの作成
        if (function_exists('ktpwp_create_department_table')) {
            $result = ktpwp_create_department_table();
            echo "<p>部署テーブル作成: " . ($result ? "成功" : "失敗") . "</p>";
        }
        
        // 部署テーブルに選択状態カラムを追加
        if (function_exists('ktpwp_add_department_selection_column')) {
            $result = ktpwp_add_department_selection_column();
            echo "<p>部署選択カラム追加: " . ($result ? "成功" : "失敗") . "</p>";
        }
        
        // 顧客テーブルにselected_department_idカラムを追加
        if (function_exists('ktpwp_add_client_selected_department_column')) {
            $result = ktpwp_add_client_selected_department_column();
            echo "<p>顧客選択部署カラム追加: " . ($result ? "成功" : "失敗") . "</p>";
        }
        
        // マイグレーション完了フラグを設定
        update_option('ktpwp_department_migration_completed', '1');
        echo "<p>マイグレーション完了フラグを設定しました。</p>";
        
        echo "<p style='color: green;'>✅ マイグレーションが完了しました。</p>";
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ マイグレーション中にエラーが発生しました: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p>手動でマイグレーションを実行する場合は、<a href='?run_migration=1'>ここをクリック</a>してください。</p>";
}

echo "<h2>11. 推奨アクション</h2>";
echo "<ul>";
if ($needs_reactivation) {
    echo "<li>プラグインの再有効化を実行してください</li>";
} else {
    echo "<li>プラグインの状態は正常です</li>";
    echo "<li>部署選択の問題が続く場合は、デバッグスクリプトを実行して詳細を確認してください</li>";
}
echo "<li>問題が解決しない場合は、デバッグログを確認してください</li>";
echo "</ul>";
?> 