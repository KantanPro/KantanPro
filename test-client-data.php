<?php
/**
 * 顧客データ確認スクリプト
 */

// WordPress環境を読み込み
require_once('../../../wp-load.php');

// 権限チェック
if (!current_user_can('edit_posts') && !current_user_can('ktpwp_access')) {
    die('権限がありません');
}

global $wpdb;

echo "<h1>顧客データ確認</h1>";

// 顧客テーブルの確認
$client_table = $wpdb->prefix . 'ktp_client';
$clients = $wpdb->get_results("SELECT * FROM {$client_table} LIMIT 5");

echo "<h2>顧客データ一覧</h2>";
echo "<p>顧客テーブル: {$client_table}</p>";
echo "<p>顧客数: " . count($clients) . "</p>";

if ($clients) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background-color: #f0f0f0;'>";
    echo "<th>ID</th><th>会社名</th><th>担当者名</th><th>郵便番号</th><th>都道府県</th><th>市区町村</th><th>住所</th><th>建物名</th><th>締日</th>";
    echo "</tr>";
    
    foreach ($clients as $client) {
        echo "<tr>";
        echo "<td>{$client->id}</td>";
        echo "<td>" . htmlspecialchars($client->company_name) . "</td>";
        echo "<td>" . htmlspecialchars($client->representative_name) . "</td>";
        echo "<td>" . htmlspecialchars($client->postal_code) . "</td>";
        echo "<td>" . htmlspecialchars($client->prefecture) . "</td>";
        echo "<td>" . htmlspecialchars($client->city) . "</td>";
        echo "<td>" . htmlspecialchars($client->address) . "</td>";
        echo "<td>" . htmlspecialchars($client->building) . "</td>";
        echo "<td>" . htmlspecialchars($client->closing_day) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // 特定の顧客（ID: 2）の詳細確認
    echo "<h2>顧客ID: 2 の詳細確認</h2>";
    $client_2 = $wpdb->get_row("SELECT * FROM {$client_table} WHERE id = 2");
    if ($client_2) {
        echo "<p><strong>会社名:</strong> " . htmlspecialchars($client_2->company_name) . "</p>";
        echo "<p><strong>担当者名:</strong> '" . htmlspecialchars($client_2->representative_name) . "' (長さ: " . strlen($client_2->representative_name) . ")</p>";
        echo "<p><strong>担当者名（trim後）:</strong> '" . htmlspecialchars(trim($client_2->representative_name)) . "' (長さ: " . strlen(trim($client_2->representative_name)) . ")</p>";
        echo "<p><strong>郵便番号:</strong> " . htmlspecialchars($client_2->postal_code) . "</p>";
        echo "<p><strong>都道府県:</strong> " . htmlspecialchars($client_2->prefecture) . "</p>";
        echo "<p><strong>市区町村:</strong> " . htmlspecialchars($client_2->city) . "</p>";
        echo "<p><strong>住所:</strong> " . htmlspecialchars($client_2->address) . "</p>";
        echo "<p><strong>建物名:</strong> " . htmlspecialchars($client_2->building) . "</p>";
        echo "<p><strong>締日:</strong> " . htmlspecialchars($client_2->closing_day) . "</p>";
        
        // 住所の組み立てテスト
        $full_address = '';
        if ($client_2->postal_code) {
            $full_address .= '〒' . $client_2->postal_code . ' ';
        }
        if ($client_2->prefecture) {
            $full_address .= $client_2->prefecture;
        }
        if ($client_2->city) {
            $full_address .= $client_2->city;
        }
        if ($client_2->address) {
            $full_address .= $client_2->address;
        }
        if ($client_2->building) {
            $full_address .= ' ' . $client_2->building;
        }
        
        if (empty(trim($full_address))) {
            $full_address = '未設定';
        }
        
        echo "<p><strong>組み立て住所:</strong> " . htmlspecialchars($full_address) . "</p>";
        
        // 担当者名の処理テスト
        $contact_name = $client_2->representative_name;
        if (empty(trim($contact_name))) {
            $contact_name = '未設定';
        }
        
        echo "<p><strong>処理後担当者名:</strong> " . htmlspecialchars($contact_name) . "</p>";
    }
}

echo "<h2>完了</h2>";
echo "<p><a href='javascript:history.back()'>戻る</a></p>";
?> 