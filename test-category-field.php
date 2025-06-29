<?php
/**
 * カテゴリーフィールドテストスクリプト
 * 
 * 得意先タブのカテゴリーフィールドが正しく動作するかテストします。
 */

// WordPressの読み込み
require_once('../../../wp-load.php');

// セキュリティチェック
if (!current_user_can('manage_options')) {
    wp_die('権限がありません。');
}

global $wpdb;

echo "<h2>カテゴリーフィールドテスト</h2>";

// テーブル名
$table_name = $wpdb->prefix . 'ktp_client';

echo "<h3>1. テストデータの作成</h3>";

// テストデータを挿入
$test_data = array(
    'company_name' => 'テスト株式会社',
    'name' => 'テスト太郎',
    'email' => 'test@example.com',
    'category' => 'テストカテゴリー',
    'client_status' => '対象'
);

$result = $wpdb->insert(
    $table_name,
    array(
        'time' => current_time('mysql'),
        'company_name' => $test_data['company_name'],
        'name' => $test_data['name'],
        'email' => $test_data['email'],
        'category' => $test_data['category'],
        'client_status' => $test_data['client_status'],
        'search_field' => implode(', ', $test_data)
    ),
    array('%s', '%s', '%s', '%s', '%s', '%s', '%s')
);

if ($result !== false) {
    $test_id = $wpdb->insert_id;
    echo "✅ テストデータを作成しました。ID: {$test_id}<br>";
} else {
    echo "❌ テストデータの作成に失敗しました。<br>";
    echo "エラー: " . $wpdb->last_error . "<br>";
    exit;
}

echo "<h3>2. 作成したデータの確認</h3>";
$created_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_name} WHERE id = %d", $test_id));

if ($created_data) {
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>フィールド</th><th>値</th></tr>";
    echo "<tr><td>ID</td><td>{$created_data->id}</td></tr>";
    echo "<tr><td>会社名</td><td>" . esc_html($created_data->company_name) . "</td></tr>";
    echo "<tr><td>担当者名</td><td>" . esc_html($created_data->name) . "</td></tr>";
    echo "<tr><td>メール</td><td>" . esc_html($created_data->email) . "</td></tr>";
    echo "<tr><td>カテゴリー</td><td>" . esc_html($created_data->category ?? '未設定') . "</td></tr>";
    echo "<tr><td>ステータス</td><td>" . esc_html($created_data->client_status) . "</td></tr>";
    echo "</table>";
} else {
    echo "❌ 作成したデータの取得に失敗しました。<br>";
}

echo "<h3>3. フォーム表示テスト</h3>";
echo "<p>以下のリンクから得意先タブにアクセスして、カテゴリーフィールドが正しく表示されるか確認してください：</p>";
echo "<a href='" . admin_url('admin.php?page=ktpwp&tab_name=client&data_id=' . $test_id) . "' target='_blank'>得意先タブでテストデータを表示</a>";

echo "<h3>4. データベースクエリテスト</h3>";

// カテゴリーフィールドを含むクエリのテスト
$test_query = $wpdb->prepare("SELECT id, company_name, name, category FROM {$table_name} WHERE id = %d", $test_id);
$test_result = $wpdb->get_row($test_query);

if ($test_result) {
    echo "✅ クエリテスト成功<br>";
    echo "取得したカテゴリー: " . esc_html($test_result->category ?? '未設定') . "<br>";
} else {
    echo "❌ クエリテスト失敗<br>";
    echo "エラー: " . $wpdb->last_error . "<br>";
}

echo "<h3>5. クリーンアップ</h3>";
echo "<p>テストが完了したら、以下のボタンでテストデータを削除できます：</p>";
echo "<form method='post'>";
echo "<input type='hidden' name='delete_test_data' value='1'>";
echo "<input type='submit' value='テストデータを削除' style='background: #dc3545; color: white; border: none; padding: 10px 20px; cursor: pointer;'>";
echo "</form>";

// テストデータの削除処理
if (isset($_POST['delete_test_data'])) {
    $delete_result = $wpdb->delete($table_name, array('id' => $test_id), array('%d'));
    if ($delete_result !== false) {
        echo "<p style='color: green;'>✅ テストデータを削除しました。</p>";
    } else {
        echo "<p style='color: red;'>❌ テストデータの削除に失敗しました。</p>";
    }
}

echo "<h3>6. 完了</h3>";
echo "カテゴリーフィールドのテストが完了しました。<br>";
echo "<a href='javascript:history.back()'>戻る</a>";
?> 