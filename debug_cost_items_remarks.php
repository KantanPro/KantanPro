<?php
/**
 * コスト項目備考欄デバッグスクリプト
 * 
 * このスクリプトは、コスト項目テーブルの備考欄の値を確認し、
 * 「0」が表示される問題の原因を特定するために使用します。
 */

// WordPress環境を読み込み
require_once('/var/www/html/wp-load.php');

// 権限チェック
if (!current_user_can('manage_options')) {
    die('権限がありません');
}

global $wpdb;

echo "<h2>コスト項目備考欄デバッグ</h2>";

// コスト項目テーブルの構造を確認
$table_name = $wpdb->prefix . 'ktp_order_cost_items';
echo "<h3>テーブル構造確認</h3>";
echo "<p>テーブル名: {$table_name}</p>";

$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'");
if ($table_exists) {
    echo "<p>✅ テーブルが存在します</p>";
    
    // カラム情報を取得
    $columns = $wpdb->get_results("SHOW COLUMNS FROM `{$table_name}`");
    echo "<h4>カラム情報:</h4>";
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>カラム名</th><th>型</th><th>NULL</th><th>キー</th><th>デフォルト</th><th>その他</th></tr>";
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td>{$column->Field}</td>";
        echo "<td>{$column->Type}</td>";
        echo "<td>{$column->Null}</td>";
        echo "<td>{$column->Key}</td>";
        echo "<td>{$column->Default}</td>";
        echo "<td>{$column->Extra}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>❌ テーブルが存在しません</p>";
    exit;
}

// コスト項目データを取得
echo "<h3>コスト項目データ確認</h3>";

$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;

if ($order_id > 0) {
    echo "<p>受注書ID: {$order_id}</p>";
    
    $items = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM `{$table_name}` WHERE order_id = %d ORDER BY sort_order ASC, id ASC",
            $order_id
        ),
        ARRAY_A
    );
    
    if ($items) {
        echo "<h4>コスト項目一覧:</h4>";
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>商品名</th><th>単価</th><th>数量</th><th>単位</th><th>金額</th><th>税率</th><th>備考</th><th>備考の型</th><th>仕入</th></tr>";
        
        foreach ($items as $item) {
            $remarks = isset($item['remarks']) ? $item['remarks'] : '';
            $remarks_type = gettype($remarks);
            $remarks_length = strlen($remarks);
            
            echo "<tr>";
            echo "<td>{$item['id']}</td>";
            echo "<td>" . htmlspecialchars($item['product_name']) . "</td>";
            echo "<td>{$item['price']}</td>";
            echo "<td>{$item['quantity']}</td>";
            echo "<td>" . htmlspecialchars($item['unit']) . "</td>";
            echo "<td>{$item['amount']}</td>";
            echo "<td>{$item['tax_rate']}</td>";
            echo "<td>" . htmlspecialchars($remarks) . "</td>";
            echo "<td>{$remarks_type} (長さ: {$remarks_length})</td>";
            echo "<td>" . htmlspecialchars($item['purchase']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>この受注書のコスト項目が見つかりません。</p>";
    }
} else {
    // 全コスト項目の備考欄を確認
    echo "<h4>全コスト項目の備考欄確認:</h4>";
    
    $all_items = $wpdb->get_results(
        "SELECT id, order_id, product_name, remarks FROM `{$table_name}` ORDER BY order_id ASC, sort_order ASC, id ASC LIMIT 50",
        ARRAY_A
    );
    
    if ($all_items) {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>受注書ID</th><th>商品名</th><th>備考</th><th>備考の型</th><th>備考の長さ</th></tr>";
        
        foreach ($all_items as $item) {
            $remarks = isset($item['remarks']) ? $item['remarks'] : '';
            $remarks_type = gettype($remarks);
            $remarks_length = strlen($remarks);
            
            echo "<tr>";
            echo "<td>{$item['id']}</td>";
            echo "<td>{$item['order_id']}</td>";
            echo "<td>" . htmlspecialchars($item['product_name']) . "</td>";
            echo "<td>" . htmlspecialchars($remarks) . "</td>";
            echo "<td>{$remarks_type}</td>";
            echo "<td>{$remarks_length}</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>コスト項目データが見つかりません。</p>";
    }
}

// 受注書一覧を表示
echo "<h3>受注書一覧</h3>";
$orders = $wpdb->get_results(
    "SELECT id, order_number, customer_name, project_name FROM `{$wpdb->prefix}ktp_order` ORDER BY id DESC LIMIT 20",
    ARRAY_A
);

if ($orders) {
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>受注書番号</th><th>顧客名</th><th>案件名</th><th>操作</th></tr>";
    
    foreach ($orders as $order) {
        echo "<tr>";
        echo "<td>{$order['id']}</td>";
        echo "<td>" . htmlspecialchars($order['order_number']) . "</td>";
        echo "<td>" . htmlspecialchars($order['customer_name']) . "</td>";
        echo "<td>" . htmlspecialchars($order['project_name']) . "</td>";
        echo "<td><a href='?order_id={$order['id']}'>詳細確認</a></td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>受注書データが見つかりません。</p>";
}

echo "<hr>";
echo "<p><a href='?'>全データ確認に戻る</a></p>";
?> 