<?php
/**
 * 納期保存機能のテストファイル
 *
 * @package KTPWP
 * @since 1.0.0
 */

// WordPress環境を読み込み
require_once('../../../wp-load.php');

// セキュリティチェック
if (!current_user_can('edit_posts')) {
    wp_die('権限がありません。');
}

echo "<h1>納期保存機能テスト</h1>";

// データベース接続確認
global $wpdb;
$table_name = $wpdb->prefix . 'ktp_order';

echo "<h2>1. データベース構造確認</h2>";

// テーブルの存在確認
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'");
if ($table_exists) {
    echo "✅ 受注書テーブルが存在します<br>";
    
    // カラムの存在確認
    $columns = $wpdb->get_results("SHOW COLUMNS FROM `{$table_name}`");
    $column_names = array_column($columns, 'Field');
    
    echo "<h3>テーブル構造:</h3>";
    echo "<ul>";
    foreach ($columns as $column) {
        $marker = in_array($column->Field, ['desired_delivery_date', 'expected_delivery_date']) ? '🎯' : '📋';
        echo "<li>{$marker} {$column->Field} - {$column->Type}</li>";
    }
    echo "</ul>";
    
    // 納期フィールドの確認
    if (in_array('desired_delivery_date', $column_names)) {
        echo "✅ 希望納期フィールドが存在します<br>";
    } else {
        echo "❌ 希望納期フィールドが存在しません<br>";
    }
    
    if (in_array('expected_delivery_date', $column_names)) {
        echo "✅ 納品予定日フィールドが存在します<br>";
    } else {
        echo "❌ 納品予定日フィールドが存在しません<br>";
    }
    
} else {
    echo "❌ 受注書テーブルが存在しません<br>";
}

echo "<h2>2. 受注書データ確認</h2>";

// 最新の受注書を取得
$latest_order = $wpdb->get_row("SELECT * FROM `{$table_name}` ORDER BY id DESC LIMIT 1");

if ($latest_order) {
    echo "✅ 最新の受注書ID: {$latest_order->id}<br>";
    echo "案件名: " . esc_html($latest_order->project_name) . "<br>";
    echo "希望納期: " . esc_html($latest_order->desired_delivery_date ?? '未設定') . "<br>";
    echo "納品予定日: " . esc_html($latest_order->expected_delivery_date ?? '未設定') . "<br>";
    
    $test_order_id = $latest_order->id;
} else {
    echo "❌ 受注書データがありません<br>";
    $test_order_id = 0;
}

echo "<h2>3. Ajax設定確認</h2>";

// nonceの確認
$nonce = wp_create_nonce('ktpwp_ajax_nonce');
echo "✅ nonce生成: {$nonce}<br>";

// Ajax URLの確認
$ajax_url = admin_url('admin-ajax.php');
echo "✅ Ajax URL: {$ajax_url}<br>";

echo "<h2>4. 手動テスト</h2>";

if ($test_order_id > 0) {
    echo "<form method='post' action=''>";
    echo "<input type='hidden' name='test_delivery_date' value='1'>";
    echo "<input type='hidden' name='order_id' value='{$test_order_id}'>";
    echo "<input type='hidden' name='nonce' value='{$nonce}'>";
    
    echo "<h3>希望納期テスト</h3>";
    echo "<input type='date' name='desired_date' value='" . date('Y-m-d') . "'>";
    echo "<button type='submit' name='test_desired'>希望納期を保存</button><br><br>";
    
    echo "<h3>納品予定日テスト</h3>";
    echo "<input type='date' name='expected_date' value='" . date('Y-m-d', strtotime('+1 week')) . "'>";
    echo "<button type='submit' name='test_expected'>納品予定日を保存</button>";
    
    echo "</form>";
}

// テスト実行
if (isset($_POST['test_delivery_date']) && isset($_POST['order_id'])) {
    $order_id = absint($_POST['order_id']);
    $nonce = sanitize_text_field($_POST['nonce']);
    
    if (wp_verify_nonce($nonce, 'ktpwp_ajax_nonce')) {
        echo "<h3>テスト実行結果:</h3>";
        
        if (isset($_POST['test_desired']) && isset($_POST['desired_date'])) {
            $desired_date = sanitize_text_field($_POST['desired_date']);
            
            $result = $wpdb->update(
                $table_name,
                array('desired_delivery_date' => $desired_date),
                array('id' => $order_id),
                array('%s'),
                array('%d')
            );
            
            if ($result !== false) {
                echo "✅ 希望納期の保存に成功しました: {$desired_date}<br>";
            } else {
                echo "❌ 希望納期の保存に失敗しました: " . $wpdb->last_error . "<br>";
            }
        }
        
        if (isset($_POST['test_expected']) && isset($_POST['expected_date'])) {
            $expected_date = sanitize_text_field($_POST['expected_date']);
            
            $result = $wpdb->update(
                $table_name,
                array('expected_delivery_date' => $expected_date),
                array('id' => $order_id),
                array('%s'),
                array('%d')
            );
            
            if ($result !== false) {
                echo "✅ 納品予定日の保存に成功しました: {$expected_date}<br>";
            } else {
                echo "❌ 納品予定日の保存に失敗しました: " . $wpdb->last_error . "<br>";
            }
        }
    } else {
        echo "❌ nonce検証に失敗しました<br>";
    }
}

echo "<h2>5. JavaScript設定確認</h2>";

echo "<script>";
echo "console.log('ktpwp_ajax:', typeof ktpwp_ajax !== 'undefined' ? ktpwp_ajax : 'undefined');";
echo "console.log('ktpwp_ajax_nonce:', typeof ktpwp_ajax_nonce !== 'undefined' ? ktpwp_ajax_nonce : 'undefined');";
echo "console.log('ajaxurl:', typeof ajaxurl !== 'undefined' ? ajaxurl : 'undefined');";
echo "</script>";

echo "<p>ブラウザの開発者ツールのコンソールでJavaScript変数を確認してください。</p>";

echo "<h2>6. 修正案</h2>";

echo "<p>もし納期保存が失敗する場合は、以下の点を確認してください：</p>";
echo "<ul>";
echo "<li>データベースに納期フィールドが正しく追加されているか</li>";
echo "<li>JavaScriptでnonceが正しく設定されているか</li>";
echo "<li>Ajax処理でエラーが発生していないか</li>";
echo "<li>権限が正しく設定されているか</li>";
echo "</ul>";

echo "<p><a href='javascript:location.reload()'>ページを再読み込み</a></p>";
?> 