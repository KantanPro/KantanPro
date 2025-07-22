<?php
/**
 * 協力会社選択ポップアップでの税率更新問題テスト
 * 
 * 問題：受注書＞コスト項目＞協力会社選択ポップアップで「更新」の場合、
 * 協力会社＞職能に設定された税率が正しく入らないケースがある
 * ※ 1行目以外は税率0の職能は税率NULLで保存される
 */

// WordPress環境を読み込み
require_once('../../../wp-load.php');

// 権限チェック
if (!current_user_can('edit_posts')) {
    wp_die('権限がありません');
}

echo "<h1>協力会社選択ポップアップでの税率更新問題テスト</h1>";

global $wpdb;

// 1. 現在のコスト項目テーブルの構造を確認
echo "<h2>1. コスト項目テーブル構造確認</h2>";
$cost_table = $wpdb->prefix . 'ktp_order_cost_items';
$columns = $wpdb->get_results("SHOW COLUMNS FROM `{$cost_table}`");
echo "<table border='1'>";
echo "<tr><th>カラム名</th><th>型</th><th>NULL</th><th>デフォルト</th><th>コメント</th></tr>";
foreach ($columns as $column) {
    echo "<tr>";
    echo "<td>{$column->Field}</td>";
    echo "<td>{$column->Type}</td>";
    echo "<td>{$column->Null}</td>";
    echo "<td>{$column->Default}</td>";
    echo "<td>{$column->Comment}</td>";
    echo "</tr>";
}
echo "</table>";

// 2. 協力会社職能テーブルの構造を確認
echo "<h2>2. 協力会社職能テーブル構造確認</h2>";
$skills_table = $wpdb->prefix . 'ktp_supplier_skills';
$columns = $wpdb->get_results("SHOW COLUMNS FROM `{$skills_table}`");
echo "<table border='1'>";
echo "<tr><th>カラム名</th><th>型</th><th>NULL</th><th>デフォルト</th><th>コメント</th></tr>";
foreach ($columns as $column) {
    echo "<tr>";
    echo "<td>{$column->Field}</td>";
    echo "<td>{$column->Type}</td>";
    echo "<td>{$column->Null}</td>";
    echo "<td>{$column->Default}</td>";
    echo "<td>{$column->Comment}</td>";
    echo "</tr>";
}
echo "</table>";

// 3. サンプルデータの確認
echo "<h2>3. サンプルデータ確認</h2>";

// 協力会社職能のサンプルデータ
echo "<h3>協力会社職能データ（税率0のもの）</h3>";
$skills = $wpdb->get_results("
    SELECT * FROM `{$skills_table}` 
    WHERE tax_rate = 0 OR tax_rate IS NULL 
    ORDER BY supplier_id, id 
    LIMIT 10
");

if ($skills) {
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>協力会社ID</th><th>商品名</th><th>単価</th><th>数量</th><th>単位</th><th>税率</th><th>税率型</th></tr>";
    foreach ($skills as $skill) {
        $tax_rate_type = is_null($skill->tax_rate) ? 'NULL' : gettype($skill->tax_rate);
        echo "<tr>";
        echo "<td>{$skill->id}</td>";
        echo "<td>{$skill->supplier_id}</td>";
        echo "<td>{$skill->product_name}</td>";
        echo "<td>{$skill->unit_price}</td>";
        echo "<td>{$skill->quantity}</td>";
        echo "<td>{$skill->unit}</td>";
        echo "<td>" . ($skill->tax_rate === null ? 'NULL' : $skill->tax_rate) . "</td>";
        echo "<td>{$tax_rate_type}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>税率0またはNULLの職能データが見つかりません</p>";
}

// コスト項目のサンプルデータ
echo "<h3>コスト項目データ（最近のもの）</h3>";
$cost_items = $wpdb->get_results("
    SELECT * FROM `{$cost_table}` 
    ORDER BY id DESC 
    LIMIT 10
");

if ($cost_items) {
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>受注書ID</th><th>商品名</th><th>単価</th><th>数量</th><th>単位</th><th>税率</th><th>税率型</th><th>協力会社ID</th></tr>";
    foreach ($cost_items as $item) {
        $tax_rate_type = is_null($item->tax_rate) ? 'NULL' : gettype($item->tax_rate);
        echo "<tr>";
        echo "<td>{$item->id}</td>";
        echo "<td>{$item->order_id}</td>";
        echo "<td>{$item->product_name}</td>";
        echo "<td>{$item->price}</td>";
        echo "<td>{$item->quantity}</td>";
        echo "<td>{$item->unit}</td>";
        echo "<td>" . ($item->tax_rate === null ? 'NULL' : $item->tax_rate) . "</td>";
        echo "<td>{$tax_rate_type}</td>";
        echo "<td>" . ($item->supplier_id ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>コスト項目データが見つかりません</p>";
}

// 4. 税率更新テスト
echo "<h2>4. 税率更新テスト</h2>";

// テスト用の受注書IDを取得（最新のもの）
$latest_order = $wpdb->get_row("SELECT id FROM `{$wpdb->prefix}ktp_orders` ORDER BY id DESC LIMIT 1");
if ($latest_order) {
    $test_order_id = $latest_order->id;
    echo "<p>テスト用受注書ID: {$test_order_id}</p>";
    
         // テスト用のコスト項目を作成（税率0）
     $test_data = array(
         'order_id' => $test_order_id,
         'product_name' => 'テスト商品（税率0）',
         'price' => 1000,
         'quantity' => 1,
         'unit' => '式',
         'amount' => 1000,
         'tax_rate' => 0.0, // 税率0を0として設定
         'remarks' => '税率更新テスト用',
         'created_at' => current_time('mysql'),
         'updated_at' => current_time('mysql')
     );
     
     $format = array('%d', '%s', '%f', '%f', '%s', '%f', '%f', '%s', '%s', '%s');
    
    $insert_result = $wpdb->insert($cost_table, $test_data, $format);
    if ($insert_result) {
        $test_item_id = $wpdb->insert_id;
        echo "<p>テスト用コスト項目を作成しました。ID: {$test_item_id}</p>";
        
        // 作成されたデータを確認
        $created_item = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$cost_table}` WHERE id = %d", $test_item_id));
        if ($created_item) {
            echo "<p>作成されたデータ:</p>";
            echo "<ul>";
            echo "<li>ID: {$created_item->id}</li>";
            echo "<li>商品名: {$created_item->product_name}</li>";
            echo "<li>税率: " . ($created_item->tax_rate === null ? 'NULL' : $created_item->tax_rate) . "</li>";
            echo "<li>税率型: " . (is_null($created_item->tax_rate) ? 'NULL' : gettype($created_item->tax_rate)) . "</li>";
            echo "</ul>";
        }
        
        // 税率を10%に更新するテスト
        $update_data = array(
            'tax_rate' => 10.0,
            'updated_at' => current_time('mysql')
        );
        $update_format = array('%f', '%s');
        
        $update_result = $wpdb->update($cost_table, $update_data, array('id' => $test_item_id), $update_format, array('%d'));
        if ($update_result !== false) {
            echo "<p>税率更新テスト成功</p>";
            
            // 更新後のデータを確認
            $updated_item = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$cost_table}` WHERE id = %d", $test_item_id));
            if ($updated_item) {
                echo "<p>更新後のデータ:</p>";
                echo "<ul>";
                echo "<li>ID: {$updated_item->id}</li>";
                echo "<li>商品名: {$updated_item->product_name}</li>";
                echo "<li>税率: " . ($updated_item->tax_rate === null ? 'NULL' : $updated_item->tax_rate) . "</li>";
                echo "<li>税率型: " . (is_null($updated_item->tax_rate) ? 'NULL' : gettype($updated_item->tax_rate)) . "</li>";
                echo "</ul>";
            }
        } else {
            echo "<p>税率更新テスト失敗: " . $wpdb->last_error . "</p>";
        }
        
        // テストデータを削除
        $wpdb->delete($cost_table, array('id' => $test_item_id), array('%d'));
        echo "<p>テストデータを削除しました</p>";
        
    } else {
        echo "<p>テスト用コスト項目の作成に失敗しました: " . $wpdb->last_error . "</p>";
    }
} else {
    echo "<p>テスト用の受注書が見つかりません</p>";
}

// 5. Ajax処理のテスト
echo "<h2>5. Ajax処理テスト</h2>";
echo "<p>以下のデータでAjax処理をテストします：</p>";

 $test_ajax_data = array(
     'action' => 'ktpwp_save_order_cost_item',
     'id' => 0,
     'order_id' => $latest_order ? $latest_order->id : 1,
     'product_name' => 'Ajaxテスト商品',
     'unit_price' => 2000,
     'quantity' => 2,
     'unit' => '式',
     'amount' => 4000,
     'tax_rate' => 0, // 税率0を0として送信
     'remarks' => 'Ajaxテスト用'
 );

echo "<pre>" . print_r($test_ajax_data, true) . "</pre>";

echo "<p><strong>注意:</strong> 実際のAjax処理をテストするには、ブラウザからJavaScriptで実行する必要があります。</p>";

echo "<h2>6. 修正内容の確認</h2>";
echo "<p>修正されたファイル: <code>includes/ajax-supplier-cost.php</code></p>";
echo "<p>修正内容:</p>";
echo "<ul>";
echo "<li>税率（tax_rate）の処理を追加</li>";
echo "<li>NULL値を許可する処理を実装</li>";
echo "<li>保存データに税率フィールドを追加</li>";
echo "<li>フォーマット配列で税率のNULL処理を追加</li>";
echo "</ul>";

 echo "<h2>7. テスト手順</h2>";
 echo "<ol>";
 echo "<li>受注書画面でコスト項目を追加</li>";
 echo "<li>協力会社選択ポップアップを開く</li>";
 echo "<li>税率0の職能を選択して「更新」をクリック</li>";
 echo "<li>税率が正しく0として保存されることを確認</li>";
 echo "</ol>";

echo "<hr>";
echo "<p><em>テスト完了: " . date('Y-m-d H:i:s') . "</em></p>";
?> 