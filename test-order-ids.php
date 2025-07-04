<?php
/**
 * 受注書ID確認用テストファイル
 */

// WordPressを読み込み
require_once('../../../wp-load.php');

// データベース接続
global $wpdb;
$table_name = $wpdb->prefix . 'ktp_order';

echo "<h2>受注書ID一覧</h2>";

// 受注書一覧を取得
$orders = $wpdb->get_results("SELECT id, customer_name, project_name, progress FROM {$table_name} ORDER BY id DESC LIMIT 10");

if ($orders) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>顧客名</th><th>案件名</th><th>進捗</th><th>詳細画面URL</th></tr>";
    
    foreach ($orders as $order) {
        $progress_labels = array(
            1 => '受付中',
            2 => '見積中', 
            3 => '受注',
            4 => '完了',
            5 => '請求済'
        );
        
        $progress_label = isset($progress_labels[$order->progress]) ? $progress_labels[$order->progress] : '不明';
        
        $detail_url = "http://localhost:8010/?tab_name=order&order_id=" . $order->id;
        
        echo "<tr>";
        echo "<td>{$order->id}</td>";
        echo "<td>" . esc_html($order->customer_name) . "</td>";
        echo "<td>" . esc_html($order->project_name) . "</td>";
        echo "<td>{$progress_label} ({$order->progress})</td>";
        echo "<td><a href='{$detail_url}' target='_blank'>{$detail_url}</a></td>";
        echo "</tr>";
    }
    
    echo "</table>";
} else {
    echo "<p>受注書が見つかりません。</p>";
}

echo "<h3>使用方法</h3>";
echo "<p>上記の詳細画面URLをクリックして受注書詳細画面にアクセスしてください。</p>";
echo "<p>その後、進捗プルダウンを変更して完了日自動設定機能をテストしてください。</p>";
?> 