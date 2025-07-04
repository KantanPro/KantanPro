<?php
/**
 * 部署選択解除テストスクリプト
 * 
 * 使用方法:
 * ブラウザで http://localhost:8010/wp-content/plugins/KantanPro/test-department-deselection.php にアクセス
 */

// WordPress環境を読み込み
require_once('../../../wp-load.php');

// セキュリティチェック
if (!current_user_can('edit_posts')) {
    die('権限がありません。');
}

echo "<h1>部署選択解除テスト</h1>";
echo "<p>実行時刻: " . date('Y-m-d H:i:s') . "</p>";

// データベース接続確認
global $wpdb;
if (!$wpdb) {
    die('データベース接続エラー');
}

// テスト前の状態確認
echo "<h2>1. テスト前の状態確認</h2>";
$client_before = $wpdb->get_row("
    SELECT id, company_name, name, selected_department_id
    FROM {$wpdb->prefix}ktp_client
    WHERE id = 1
");

echo "<p>顧客ID 1の現在の状態:</p>";
echo "<ul>";
echo "<li>顧客ID: {$client_before->id}</li>";
echo "<li>会社名: {$client_before->company_name}</li>";
echo "<li>担当者名: {$client_before->name}</li>";
echo "<li>選択部署ID: " . ($client_before->selected_department_id ?: 'null（未選択）') . "</li>";
echo "</ul>";

// AJAXハンドラーの確認
echo "<h2>2. AJAXハンドラー確認</h2>";
if (has_action('wp_ajax_ktp_update_department_selection')) {
    echo "<p>ktp_update_department_selection AJAXハンドラー: 登録済み</p>";
} else {
    echo "<p style='color: red;'>ktp_update_department_selection AJAXハンドラー: 未登録</p>";
}

if (has_action('wp_ajax_nopriv_ktp_update_department_selection')) {
    echo "<p>ktp_update_department_selection AJAXハンドラー（未認証）: 登録済み</p>";
} else {
    echo "<p>ktp_update_department_selection AJAXハンドラー（未認証）: 未登録</p>";
}

// 部署管理クラスの確認
echo "<h2>3. 部署管理クラス確認</h2>";
if (class_exists('KTPWP_Department_Manager')) {
    echo "<p>KTPWP_Department_Managerクラス: 存在</p>";
    
    // 部署テーブル確認
    if (KTPWP_Department_Manager::table_exists()) {
        echo "<p>部署テーブル: 存在</p>";
    } else {
        echo "<p style='color: red;'>部署テーブル: 存在しません</p>";
    }
} else {
    echo "<p style='color: red;'>KTPWP_Department_Managerクラス: 存在しません</p>";
}

// AJAXリクエストをシミュレート
echo "<h2>4. AJAX解除処理のシミュレート</h2>";

// 現在選択されている部署IDを取得
$current_selection = $wpdb->get_var($wpdb->prepare(
    "SELECT selected_department_id FROM {$wpdb->prefix}ktp_client WHERE id = %d",
    1
));

echo "<p>現在選択されている部署ID: " . ($current_selection ?: 'NULL') . "</p>";

if ($current_selection) {
    echo "<p>部署選択解除を実行します...</p>";
    
    // 部署管理クラスを使用して解除
    if (class_exists('KTPWP_Department_Manager')) {
        $result = KTPWP_Department_Manager::update_department_selection($current_selection, false);
        
        if ($result) {
            echo "<p style='color: green;'>部署選択解除: 成功</p>";
        } else {
            echo "<p style='color: red;'>部署選択解除: 失敗</p>";
        }
    } else {
        echo "<p style='color: red;'>部署管理クラスが見つかりません</p>";
    }
} else {
    echo "<p>現在選択されている部署がありません。</p>";
}

// テスト後の状態確認
echo "<h2>5. テスト後の状態確認</h2>";
$client_after = $wpdb->get_row("
    SELECT id, company_name, name, selected_department_id
    FROM {$wpdb->prefix}ktp_client
    WHERE id = 1
");

echo "<p>顧客ID 1の解除後の状態:</p>";
echo "<ul>";
echo "<li>顧客ID: {$client_after->id}</li>";
echo "<li>会社名: {$client_after->company_name}</li>";
echo "<li>担当者名: {$client_after->name}</li>";
echo "<li>選択部署ID: " . ($client_after->selected_department_id ?: 'null（解除済み）') . "</li>";
echo "</ul>";

// 変更の確認
if ($client_before->selected_department_id != $client_after->selected_department_id) {
    echo "<p style='color: green;'>✓ 部署選択が正常に解除されました</p>";
} else {
    echo "<p style='color: red;'>✗ 部署選択の解除に失敗しました</p>";
}

// データベース直接操作テスト
echo "<h2>6. データベース直接操作テスト</h2>";
echo "<p>データベースを直接更新して部署選択を解除します...</p>";

$direct_result = $wpdb->update(
    $wpdb->prefix . 'ktp_client',
    array('selected_department_id' => null),
    array('id' => 1),
    array(null),
    array('%d')
);

if ($direct_result !== false) {
    echo "<p style='color: green;'>データベース直接更新: 成功</p>";
    echo "<p>影響を受けた行数: {$direct_result}</p>";
    
    // 更新後の状態確認
    $client_direct = $wpdb->get_row("
        SELECT id, company_name, name, selected_department_id
        FROM {$wpdb->prefix}ktp_client
        WHERE id = 1
    ");
    
    echo "<p>直接更新後の選択部署ID: " . ($client_direct->selected_department_id ?: 'null（解除済み）') . "</p>";
} else {
    echo "<p style='color: red;'>データベース直接更新: 失敗</p>";
    echo "<p>エラー: " . $wpdb->last_error . "</p>";
}

// JavaScriptのテスト
echo "<h2>7. JavaScript解除テスト</h2>";
echo "<p>以下のボタンをクリックしてJavaScriptでの解除をテストしてください:</p>";
echo "<button onclick='testDeselection()'>部署選択解除テスト</button>";
echo "<div id='test-result'></div>";

echo "<script>";
echo "function testDeselection() {";
echo "    var resultDiv = document.getElementById('test-result');";
echo "    resultDiv.innerHTML = '<p>JavaScript解除テストを実行中...</p>';";
echo "    ";
echo "    // AJAXリクエストを送信";
echo "    var xhr = new XMLHttpRequest();";
echo "    xhr.open('POST', '" . admin_url('admin-ajax.php') . "', true);";
echo "    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');";
echo "    ";
echo "    xhr.onreadystatechange = function() {";
echo "        if (xhr.readyState === 4) {";
echo "            if (xhr.status === 200) {";
echo "                resultDiv.innerHTML = '<p style=\"color: green;\">✓ AJAX解除リクエスト成功</p><p>レスポンス: ' + xhr.responseText + '</p>';";
echo "            } else {";
echo "                resultDiv.innerHTML = '<p style=\"color: red;\">✗ AJAX解除リクエスト失敗</p><p>ステータス: ' + xhr.status + '</p>';";
echo "            }";
echo "        }";
echo "    };";
echo "    ";
echo "    var data = 'action=ktp_update_department_selection&department_id=1&is_selected=false&nonce=" . wp_create_nonce('ktp_department_nonce') . "';";
echo "    xhr.send(data);";
echo "}";
echo "</script>";

// ログファイルの確認
echo "<h2>8. デバッグログ確認</h2>";
$log_file = WP_CONTENT_DIR . '/debug.log';
if (file_exists($log_file)) {
    echo "<p>デバッグログファイル: 存在</p>";
    echo "<p>ログファイルサイズ: " . filesize($log_file) . " bytes</p>";
    
    // 最新のログを表示
    $log_content = file_get_contents($log_file);
    $log_lines = explode("\n", $log_content);
    $recent_logs = array_slice($log_lines, -20); // 最新20行
    
    echo "<h3>最新のログ（最新20行）:</h3>";
    echo "<pre style='background: #f5f5f5; padding: 10px; max-height: 300px; overflow-y: auto;'>";
    foreach ($recent_logs as $line) {
        if (strpos($line, 'KTPWP') !== false || strpos($line, 'Department') !== false) {
            echo htmlspecialchars($line) . "\n";
        }
    }
    echo "</pre>";
} else {
    echo "<p>デバッグログファイル: 存在しません</p>";
}

echo "<h2>9. テスト完了</h2>";
echo "<p>このスクリプトの実行が完了しました。</p>";
echo "<p><a href='?tab_name=client'>顧客タブに戻る</a></p>";
?> 