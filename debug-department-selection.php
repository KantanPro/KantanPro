<?php
/**
 * 部署選択状態デバッグスクリプト
 */

// WordPress環境を読み込み
require_once('../../../wp-load.php');

// 権限チェック
if (!current_user_can('edit_posts') && !current_user_can('ktpwp_access')) {
    die('権限がありません');
}

global $wpdb;

echo "<h1>部署選択状態デバッグ</h1>";

// 1. 顧客テーブルの確認
echo "<h2>1. 顧客テーブル確認</h2>";
$client_table = $wpdb->prefix . 'ktp_client';
$clients = $wpdb->get_results("SELECT id, company_name, selected_department_id FROM {$client_table} ORDER BY id DESC LIMIT 10");
echo "<p>顧客テーブル: {$client_table}</p>";
echo "<p>顧客数: " . count($clients) . "</p>";
if ($clients) {
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>会社名</th><th>選択された部署ID</th><th>状態</th></tr>";
    foreach ($clients as $client) {
        $status = $client->selected_department_id ? "選択済み" : "未選択";
        $status_color = $client->selected_department_id ? "green" : "red";
        echo "<tr>";
        echo "<td>{$client->id}</td>";
        echo "<td>{$client->company_name}</td>";
        echo "<td>{$client->selected_department_id}</td>";
        echo "<td style='color: {$status_color};'>{$status}</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// 2. 部署テーブルの確認
echo "<h2>2. 部署テーブル確認</h2>";
$department_table = $wpdb->prefix . 'ktp_department';
$departments = $wpdb->get_results("SELECT id, client_id, department_name, contact_person, email, is_selected FROM {$department_table} ORDER BY client_id, id LIMIT 20");
echo "<p>部署テーブル: {$department_table}</p>";
echo "<p>部署数: " . count($departments) . "</p>";
if ($departments) {
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>顧客ID</th><th>部署名</th><th>担当者</th><th>メール</th><th>選択状態</th></tr>";
    foreach ($departments as $dept) {
        $status = $dept->is_selected ? "選択済み" : "未選択";
        $status_color = $dept->is_selected ? "green" : "red";
        echo "<tr>";
        echo "<td>{$dept->id}</td>";
        echo "<td>{$dept->client_id}</td>";
        echo "<td>{$dept->department_name}</td>";
        echo "<td>{$dept->contact_person}</td>";
        echo "<td>{$dept->email}</td>";
        echo "<td style='color: {$status_color};'>{$status}</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// 3. 部署選択の整合性チェック
echo "<h2>3. 部署選択の整合性チェック</h2>";
$inconsistent_clients = $wpdb->get_results("
    SELECT c.id, c.company_name, c.selected_department_id, d.id as dept_id, d.department_name
    FROM {$client_table} c
    LEFT JOIN {$department_table} d ON c.selected_department_id = d.id
    WHERE c.selected_department_id IS NOT NULL AND d.id IS NULL
");
echo "<p>選択された部署が存在しない顧客数: " . count($inconsistent_clients) . "</p>";
if ($inconsistent_clients) {
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>顧客ID</th><th>会社名</th><th>選択された部署ID</th><th>問題</th></tr>";
    foreach ($inconsistent_clients as $client) {
        echo "<tr>";
        echo "<td>{$client->id}</td>";
        echo "<td>{$client->company_name}</td>";
        echo "<td>{$client->selected_department_id}</td>";
        echo "<td style='color: red;'>部署が存在しません</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// 4. 部署選択の自動初期化処理の確認
echo "<h2>4. 部署選択の自動初期化処理確認</h2>";
$auto_init_enabled = get_option('ktpwp_department_auto_init_enabled', '0');
echo "<p>自動初期化有効: " . ($auto_init_enabled ? "はい" : "いいえ") . "</p>";

// 5. マイグレーション状態の確認
echo "<h2>5. マイグレーション状態確認</h2>";
$migration_completed = get_option('ktpwp_department_migration_completed', '0');
$db_version = get_option('ktpwp_db_version', '0.0.0');
echo "<p>部署マイグレーション完了: " . ($migration_completed ? "はい" : "いいえ") . "</p>";
echo "<p>DBバージョン: {$db_version}</p>";

// 6. 特定の顧客の部署選択状態を詳細確認
if (isset($_GET['client_id'])) {
    $client_id = intval($_GET['client_id']);
    echo "<h2>6. 顧客ID {$client_id} の詳細確認</h2>";
    
    $client = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$client_table} WHERE id = %d", $client_id));
    if ($client) {
        echo "<p><strong>顧客情報:</strong></p>";
        echo "<ul>";
        echo "<li>ID: {$client->id}</li>";
        echo "<li>会社名: {$client->company_name}</li>";
        echo "<li>選択された部署ID: " . ($client->selected_department_id ?: 'NULL') . "</li>";
        echo "</ul>";
        
        $departments = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$department_table} WHERE client_id = %d", $client_id));
        echo "<p><strong>部署一覧:</strong></p>";
        if ($departments) {
            echo "<table border='1' style='border-collapse: collapse;'>";
            echo "<tr><th>ID</th><th>部署名</th><th>担当者</th><th>メール</th><th>選択状態</th></tr>";
            foreach ($departments as $dept) {
                $is_selected = ($dept->id == $client->selected_department_id);
                $status = $is_selected ? "選択済み" : "未選択";
                $status_color = $is_selected ? "green" : "red";
                echo "<tr>";
                echo "<td>{$dept->id}</td>";
                echo "<td>{$dept->department_name}</td>";
                echo "<td>{$dept->contact_person}</td>";
                echo "<td>{$dept->email}</td>";
                echo "<td style='color: {$status_color};'>{$status}</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p>部署が登録されていません。</p>";
        }
    } else {
        echo "<p>顧客が見つかりません。</p>";
    }
}

// 7. デバッグログの確認
echo "<h2>7. デバッグログ確認</h2>";
$debug_log_file = WP_CONTENT_DIR . '/debug.log';
if (file_exists($debug_log_file)) {
    $log_lines = file($debug_log_file);
    $department_logs = array_filter($log_lines, function($line) {
        return strpos($line, 'KTPWP') !== false || strpos($line, 'Department') !== false;
    });
    
    if ($department_logs) {
        echo "<p>部署関連のログ（最新10行）:</p>";
        echo "<pre style='background: #f5f5f5; padding: 10px; max-height: 300px; overflow-y: auto;'>";
        $recent_logs = array_slice($department_logs, -10);
        foreach ($recent_logs as $log) {
            echo htmlspecialchars($log);
        }
        echo "</pre>";
    } else {
        echo "<p>部署関連のログが見つかりません。</p>";
    }
} else {
    echo "<p>デバッグログファイルが存在しません。</p>";
}

echo "<h2>8. テスト用リンク</h2>";
echo "<p>特定の顧客の詳細を確認するには、URLに ?client_id=顧客ID を追加してください。</p>";
echo "<p>例: <a href='?client_id=1'>顧客ID 1 の詳細確認</a></p>";

echo "<h2>9. 推奨アクション</h2>";
echo "<ul>";
echo "<li>部署選択を解除した後、このページをリロードして状態を確認してください。</li>";
echo "<li>問題が続く場合は、プラグインの再有効化を試してください。</li>";
echo "<li>デバッグログを確認して、エラーメッセージがないかチェックしてください。</li>";
echo "</ul>";
?> 