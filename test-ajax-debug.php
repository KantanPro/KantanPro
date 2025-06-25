<?php
/**
 * Ajax通信テスト用デバッグスクリプト
 */

// WordPressの読み込み
require_once('../../../wp-load.php');

// デバッグログの有効化
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Ajax通信テスト</h1>";

// 現在のユーザー情報
$current_user = wp_get_current_user();
echo "<h2>現在のユーザー</h2>";
echo "<p>ID: " . $current_user->ID . "</p>";
echo "<p>名前: " . $current_user->display_name . "</p>";
echo "<p>権限: " . (current_user_can('edit_posts') ? 'edit_posts OK' : 'edit_posts NG') . "</p>";

// Ajax URL
$ajax_url = admin_url('admin-ajax.php');
echo "<h2>Ajax URL</h2>";
echo "<p>" . $ajax_url . "</p>";

// テーブルの存在確認
global $wpdb;
$table = $wpdb->prefix . 'ktp_order_cost_items';
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;

echo "<h2>データベース</h2>";
echo "<p>テーブル存在: " . ($table_exists ? 'OK' : 'NG') . "</p>";
echo "<p>テーブル名: " . $table . "</p>";

if ($table_exists) {
    // テーブル構造の確認
    $columns = $wpdb->get_results("SHOW COLUMNS FROM `{$table}`");
    echo "<h3>テーブル構造</h3>";
    echo "<ul>";
    foreach ($columns as $column) {
        echo "<li>" . $column->Field . " (" . $column->Type . ")</li>";
    }
    echo "</ul>";
    
    // サンプルデータの確認
    $sample_data = $wpdb->get_results("SELECT * FROM `{$table}` LIMIT 5");
    echo "<h3>サンプルデータ</h3>";
    if ($sample_data) {
        echo "<table border='1'>";
        echo "<tr>";
        foreach ($columns as $column) {
            echo "<th>" . $column->Field . "</th>";
        }
        echo "</tr>";
        foreach ($sample_data as $row) {
            echo "<tr>";
            foreach ($columns as $column) {
                echo "<td>" . htmlspecialchars($row->{$column->Field}) . "</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>データがありません</p>";
    }
}

// Ajaxハンドラーのテスト
echo "<h2>Ajaxハンドラーテスト</h2>";

// テスト用のPOSTデータ
$_POST = array(
    'action' => 'ktpwp_save_order_cost_item',
    'force_save' => true,
    'id' => '0',
    'order_id' => '1',
    'supplier_id' => '1',
    'product_name' => 'テスト商品',
    'unit_price' => '1000',
    'quantity' => '1',
    'unit' => '個',
    'amount' => '1000'
);

echo "<h3>テストデータ</h3>";
echo "<pre>" . print_r($_POST, true) . "</pre>";

// Ajaxハンドラーの実行
echo "<h3>Ajaxハンドラー実行</h3>";

// 出力バッファリングを開始
ob_start();

// Ajaxハンドラーを直接実行
do_action('wp_ajax_ktpwp_save_order_cost_item');

// 出力を取得
$output = ob_get_clean();

echo "<h3>出力結果</h3>";
echo "<pre>" . htmlspecialchars($output) . "</pre>";

// エラーログの確認
echo "<h2>エラーログ</h2>";
$log_file = WP_CONTENT_DIR . '/debug.log';
if (file_exists($log_file)) {
    $log_content = file_get_contents($log_file);
    $lines = explode("\n", $log_content);
    $recent_lines = array_slice($lines, -20); // 最新20行
    echo "<pre>" . htmlspecialchars(implode("\n", $recent_lines)) . "</pre>";
} else {
    echo "<p>デバッグログファイルが見つかりません</p>";
}

echo "<h2>完了</h2>";
echo "<p>テストが完了しました。</p>";
?> 