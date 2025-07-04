<?php
/**
 * 部署選択状態確認テストスクリプト
 * 
 * 使用方法:
 * ブラウザで http://localhost:8010/wp-content/plugins/KantanPro/check-department-selection.php にアクセス
 */

// WordPress環境を読み込み
require_once('../../../wp-load.php');

// セキュリティチェック
if (!current_user_can('edit_posts')) {
    die('権限がありません。');
}

echo "<h1>部署選択状態確認テスト</h1>";
echo "<p>実行時刻: " . date('Y-m-d H:i:s') . "</p>";

// データベース接続確認
global $wpdb;
if (!$wpdb) {
    die('データベース接続エラー');
}

echo "<h2>1. データベース接続確認</h2>";
echo "<p>データベース接続: OK</p>";

// 部署テーブル確認
echo "<h2>2. 部署テーブル確認</h2>";
$departments = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}ktp_department ORDER BY id");
if ($departments) {
    echo "<p>部署テーブル: 存在</p>";
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>部署名</th><th>作成日</th></tr>";
    foreach ($departments as $dept) {
        echo "<tr>";
        echo "<td>{$dept->id}</td>";
        echo "<td>{$dept->name}</td>";
        echo "<td>{$dept->created_at}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>部署テーブル: 存在しないか、データがありません</p>";
}

// 顧客テーブルの部署選択カラム確認
echo "<h2>3. 顧客テーブルの部署選択カラム確認</h2>";
$client_columns = $wpdb->get_results("DESCRIBE {$wpdb->prefix}ktp_client");
$has_selected_department = false;
foreach ($client_columns as $column) {
    if ($column->Field === 'selected_department_id') {
        $has_selected_department = true;
        break;
    }
}

if ($has_selected_department) {
    echo "<p>selected_department_idカラム: 存在</p>";
    
    // 顧客の部署選択状態を確認
    $clients = $wpdb->get_results("
        SELECT c.id, c.company_name, c.name, c.selected_department_id, d.name as department_name
        FROM {$wpdb->prefix}ktp_client c
        LEFT JOIN {$wpdb->prefix}ktp_department d ON c.selected_department_id = d.id
        ORDER BY c.id
    ");
    
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>顧客ID</th><th>会社名</th><th>担当者名</th><th>選択部署ID</th><th>部署名</th></tr>";
    foreach ($clients as $client) {
        echo "<tr>";
        echo "<td>{$client->id}</td>";
        echo "<td>{$client->company_name}</td>";
        echo "<td>{$client->name}</td>";
        echo "<td>" . ($client->selected_department_id ?: '未選択') . "</td>";
        echo "<td>" . ($client->department_name ?: 'なし') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>selected_department_idカラム: 存在しません</p>";
}

// AJAXハンドラーの確認
echo "<h2>4. AJAXハンドラー確認</h2>";
if (has_action('wp_ajax_update_department_selection')) {
    echo "<p>update_department_selection AJAXハンドラー: 登録済み</p>";
} else {
    echo "<p>update_department_selection AJAXハンドラー: 未登録</p>";
}

if (has_action('wp_ajax_nopriv_update_department_selection')) {
    echo "<p>update_department_selection AJAXハンドラー（未認証）: 登録済み</p>";
} else {
    echo "<p>update_department_selection AJAXハンドラー（未認証）: 未登録</p>";
}

// 部署選択解除テスト
echo "<h2>5. 部署選択解除テスト</h2>";
echo "<p>顧客ID 1の部署選択を解除します...</p>";

$result = $wpdb->update(
    $wpdb->prefix . 'ktp_client',
    array('selected_department_id' => null),
    array('id' => 1),
    array('%d'),
    array('%d')
);

if ($result !== false) {
    echo "<p>部署選択解除: 成功</p>";
    
    // 解除後の状態確認
    $client_after = $wpdb->get_row("
        SELECT id, company_name, name, selected_department_id
        FROM {$wpdb->prefix}ktp_client
        WHERE id = 1
    ");
    
    echo "<p>解除後の状態:</p>";
    echo "<ul>";
    echo "<li>顧客ID: {$client_after->id}</li>";
    echo "<li>会社名: {$client_after->company_name}</li>";
    echo "<li>担当者名: {$client_after->name}</li>";
    echo "<li>選択部署ID: " . ($client_after->selected_department_id ?: 'null（解除済み）') . "</li>";
    echo "</ul>";
} else {
    echo "<p>部署選択解除: 失敗</p>";
    echo "<p>エラー: " . $wpdb->last_error . "</p>";
}

echo "<h2>6. テスト完了</h2>";
echo "<p>このスクリプトの実行が完了しました。</p>";
echo "<p><a href='?tab_name=client'>顧客タブに戻る</a></p>";
?> 