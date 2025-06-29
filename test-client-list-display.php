<?php
/**
 * 顧客リスト表示テストスクリプト
 * 
 * 新しい表示形式「D:* 会社名 | 担当者 | カテゴリー | 頻度(*)」をテストします。
 */

// WordPressの読み込み
require_once('../../../wp-load.php');

// セキュリティチェック
if (!current_user_can('manage_options')) {
    wp_die('権限がありません。');
}

global $wpdb;

echo "<h2>顧客リスト表示テスト</h2>";

// テーブル名
$table_name = $wpdb->prefix . 'ktp_client';

echo "<h3>1. テストデータの作成</h3>";

// 複数のテストデータを挿入（異なるカテゴリーで）
$test_data_array = array(
    array(
        'company_name' => 'テスト株式会社A',
        'name' => '田中太郎',
        'email' => 'tanaka@test-a.com',
        'category' => '一般',
        'client_status' => '対象'
    ),
    array(
        'company_name' => 'テスト株式会社B',
        'name' => '佐藤花子',
        'email' => 'sato@test-b.com',
        'category' => 'VIP',
        'client_status' => '対象'
    ),
    array(
        'company_name' => 'テスト株式会社C',
        'name' => '鈴木一郎',
        'email' => 'suzuki@test-c.com',
        'category' => '', // 空のカテゴリー（デフォルトで「一般」が表示される）
        'client_status' => '対象'
    ),
    array(
        'company_name' => 'テスト株式会社D',
        'name' => '高橋美咲',
        'email' => 'takahashi@test-d.com',
        'category' => '新規',
        'client_status' => '対象外' // 削除済み
    )
);

$created_ids = array();

foreach ($test_data_array as $test_data) {
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
        $created_ids[] = $test_id;
        echo "✅ テストデータを作成しました。ID: {$test_id} - {$test_data['company_name']}<br>";
    } else {
        echo "❌ テストデータの作成に失敗しました。{$test_data['company_name']}<br>";
        echo "エラー: " . $wpdb->last_error . "<br>";
    }
}

echo "<h3>2. 作成したデータの確認</h3>";
$created_data = $wpdb->get_results("SELECT * FROM {$table_name} WHERE id IN (" . implode(',', $created_ids) . ") ORDER BY id");

if ($created_data) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background-color: #f0f0f0;'>";
    echo "<th>ID</th><th>会社名</th><th>担当者</th><th>カテゴリー</th><th>ステータス</th><th>頻度</th>";
    echo "</tr>";
    
    foreach ($created_data as $row) {
        $category_display = !empty($row->category) ? $row->category : '一般';
        $status_style = ($row->client_status === '対象外') ? ' style="color: red;"' : '';
        
        echo "<tr>";
        echo "<td>{$row->id}</td>";
        echo "<td>" . esc_html($row->company_name) . "</td>";
        echo "<td>" . esc_html($row->name) . "</td>";
        echo "<td>" . esc_html($category_display) . "</td>";
        echo "<td{$status_style}>" . esc_html($row->client_status) . "</td>";
        echo "<td>{$row->frequency}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "❌ 作成したデータの取得に失敗しました。<br>";
}

echo "<h3>3. 新しい表示形式の確認</h3>";
echo "<p>以下のリンクから得意先タブにアクセスして、新しい表示形式を確認してください：</p>";

foreach ($created_ids as $id) {
    $client_data = $wpdb->get_row($wpdb->prepare("SELECT company_name, name, category, frequency FROM {$table_name} WHERE id = %d", $id));
    if ($client_data) {
        $category_display = !empty($client_data->category) ? $client_data->category : '一般';
        $expected_format = "D:* {$client_data->company_name} | {$client_data->name} | {$category_display} | 頻度({$client_data->frequency})";
        
        echo "<div style='margin: 10px 0; padding: 10px; background: #f9f9f9; border-left: 3px solid #0073aa;'>";
        echo "<strong>ID {$id} の期待される表示形式:</strong><br>";
        echo "<code>" . esc_html($expected_format) . "</code><br>";
        echo "<a href='" . admin_url('admin.php?page=ktpwp&tab_name=client&data_id=' . $id) . "' target='_blank'>得意先タブで確認</a>";
        echo "</div>";
    }
}

echo "<h3>4. 一覧表示の確認</h3>";
echo "<p>以下のリンクから顧客一覧を確認してください：</p>";
echo "<a href='" . admin_url('admin.php?page=ktpwp&tab_name=client') . "' target='_blank'>顧客一覧を表示</a>";

echo "<h3>5. クリーンアップ</h3>";
echo "<p>テストが完了したら、以下のボタンでテストデータを削除できます：</p>";
echo "<form method='post'>";
echo "<input type='hidden' name='delete_test_data' value='1'>";
echo "<input type='submit' value='テストデータを削除' style='background: #dc3545; color: white; border: none; padding: 10px 20px; cursor: pointer;'>";
echo "</form>";

// テストデータの削除処理
if (isset($_POST['delete_test_data'])) {
    $delete_count = 0;
    foreach ($created_ids as $id) {
        $delete_result = $wpdb->delete($table_name, array('id' => $id), array('%d'));
        if ($delete_result !== false) {
            $delete_count++;
        }
    }
    
    if ($delete_count > 0) {
        echo "<p style='color: green;'>✅ {$delete_count}件のテストデータを削除しました。</p>";
    } else {
        echo "<p style='color: red;'>❌ テストデータの削除に失敗しました。</p>";
    }
}

echo "<h3>6. 完了</h3>";
echo "顧客リスト表示のテストが完了しました。<br>";
echo "<a href='javascript:history.back()'>戻る</a>";
?> 