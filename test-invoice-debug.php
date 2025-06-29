<?php
/**
 * 請求書発行機能デバッグスクリプト
 */

// WordPress環境を読み込み
require_once('../../../wp-load.php');

// 権限チェック
if (!current_user_can('edit_posts') && !current_user_can('ktpwp_access')) {
    die('権限がありません');
}

global $wpdb;

echo "<h1>請求書発行機能デバッグ</h1>";

// 1. 顧客テーブルの確認
echo "<h2>1. 顧客テーブル確認</h2>";
$client_table = $wpdb->prefix . 'ktp_client';
$clients = $wpdb->get_results("SELECT id, company_name, address, contact_person, closing_day FROM {$client_table} LIMIT 5");
echo "<p>顧客テーブル: {$client_table}</p>";
echo "<p>顧客数: " . count($clients) . "</p>";
if ($clients) {
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>会社名</th><th>住所</th><th>担当者</th><th>締日</th></tr>";
    foreach ($clients as $client) {
        echo "<tr>";
        echo "<td>{$client->id}</td>";
        echo "<td>{$client->company_name}</td>";
        echo "<td>" . substr($client->address, 0, 50) . "...</td>";
        echo "<td>{$client->contact_person}</td>";
        echo "<td>{$client->closing_day}</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// 2. 案件テーブルの確認
echo "<h2>2. 案件テーブル確認</h2>";
$order_table = $wpdb->prefix . 'ktp_order';
$orders = $wpdb->get_results("SELECT id, client_id, project_name, progress, completion_date FROM {$order_table} ORDER BY id DESC LIMIT 10");
echo "<p>案件テーブル: {$order_table}</p>";
echo "<p>案件数: " . count($orders) . "</p>";
if ($orders) {
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>顧客ID</th><th>案件名</th><th>進捗</th><th>完了日</th></tr>";
    foreach ($orders as $order) {
        echo "<tr>";
        echo "<td>{$order->id}</td>";
        echo "<td>{$order->client_id}</td>";
        echo "<td>{$order->project_name}</td>";
        echo "<td>{$order->progress}</td>";
        echo "<td>{$order->completion_date}</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// 3. 完了案件の確認
echo "<h2>3. 完了案件（progress=4）の確認</h2>";
$completed_orders = $wpdb->get_results("SELECT id, client_id, project_name, completion_date FROM {$order_table} WHERE progress = 4 AND completion_date IS NOT NULL ORDER BY completion_date DESC");
echo "<p>完了案件数: " . count($completed_orders) . "</p>";
if ($completed_orders) {
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>顧客ID</th><th>案件名</th><th>完了日</th></tr>";
    foreach ($completed_orders as $order) {
        echo "<tr>";
        echo "<td>{$order->id}</td>";
        echo "<td>{$order->client_id}</td>";
        echo "<td>{$order->project_name}</td>";
        echo "<td>{$order->completion_date}</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// 4. 請求項目テーブルの確認
echo "<h2>4. 請求項目テーブル確認</h2>";
$invoice_items_table = $wpdb->prefix . 'ktp_order_invoice_items';
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$invoice_items_table}'");
if ($table_exists) {
    $invoice_items = $wpdb->get_results("SELECT id, order_id, product_name, quantity, price, amount FROM {$invoice_items_table} LIMIT 10");
    echo "<p>請求項目テーブル: {$invoice_items_table}</p>";
    echo "<p>請求項目数: " . count($invoice_items) . "</p>";
    if ($invoice_items) {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>案件ID</th><th>商品名</th><th>数量</th><th>単価</th><th>金額</th></tr>";
        foreach ($invoice_items as $item) {
            echo "<tr>";
            echo "<td>{$item->id}</td>";
            echo "<td>{$item->order_id}</td>";
            echo "<td>{$item->product_name}</td>";
            echo "<td>{$item->quantity}</td>";
            echo "<td>{$item->price}</td>";
            echo "<td>{$item->amount}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} else {
    echo "<p style='color: red;'>請求項目テーブルが存在しません: {$invoice_items_table}</p>";
}

// 5. 特定の顧客でのテスト
if ($clients) {
    $test_client = $clients[0];
    echo "<h2>5. テスト顧客（ID: {$test_client->id}）での請求書候補確認</h2>";
    
    // 締日の計算テスト
    $closing_day = $test_client->closing_day;
    echo "<p>締日: {$closing_day}</p>";
    
    // 完了案件の取得
    $test_orders = $wpdb->get_results($wpdb->prepare(
        "SELECT id, project_name, completion_date FROM {$order_table} 
         WHERE client_id = %d AND progress = 4 AND completion_date IS NOT NULL 
         ORDER BY completion_date DESC",
        $test_client->id
    ));
    
    echo "<p>完了案件数: " . count($test_orders) . "</p>";
    
    if ($test_orders) {
        echo "<h3>締日比較テスト</h3>";
        foreach ($test_orders as $order) {
            $completion_date = $order->completion_date;
            $completion_year = date('Y', strtotime($completion_date));
            $completion_month = date('m', strtotime($completion_date));
            
            // 完了月の前月締日を計算
            if ($closing_day === '末日') {
                $month_closing_date = date('Y-m-t', strtotime($completion_year . '-' . $completion_month . '-01 -1 month'));
            } else {
                $closing_day_num = intval($closing_day);
                $month_closing_date = date('Y-m-d', strtotime($completion_year . '-' . $completion_month . '-01 -1 month +' . ($closing_day_num - 1) . ' days'));
            }
            
            $is_over = $completion_date > $month_closing_date;
            
            echo "<p>";
            echo "案件ID: {$order->id}, ";
            echo "完了日: {$completion_date}, ";
            echo "前月締日: {$month_closing_date}, ";
            echo "請求対象: " . ($is_over ? 'YES' : 'NO');
            echo "</p>";
        }
    }
}

echo "<h2>6. デバッグ完了</h2>";
echo "<p><a href='javascript:history.back()'>戻る</a></p>";
?> 