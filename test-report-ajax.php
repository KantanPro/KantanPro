<?php
/**
 * レポート機能AJAXテストスクリプト
 */

// WordPress環境を読み込み
require_once('wordpress/wp-config.php');

// データベース接続確認
global $wpdb;

echo "=== レポート機能AJAXテスト ===\n";

// 1. データベース接続確認
echo "1. データベース接続確認...\n";
$test_query = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ktp_order");
echo "注文テーブル件数: " . $test_query . "\n";

// 2. 売上データ取得テスト
echo "\n2. 売上データ取得テスト...\n";

// 月別売上データ
$monthly_query = "SELECT 
    DATE_FORMAT(created_at, '%Y-%m') as month,
    SUM(order_amount) as total_sales
    FROM {$wpdb->prefix}ktp_order
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month";

$monthly_results = $wpdb->get_results($monthly_query);
echo "月別売上データ:\n";
foreach ($monthly_results as $result) {
    echo "  {$result->month}: ¥" . number_format($result->total_sales) . "\n";
}

// 進捗別売上データ
$progress_query = "SELECT 
    order_status,
    SUM(order_amount) as total_sales
    FROM {$wpdb->prefix}ktp_order
    GROUP BY order_status
    ORDER BY order_status";

$progress_results = $wpdb->get_results($progress_query);
echo "\n進捗別売上データ:\n";
foreach ($progress_results as $result) {
    echo "  {$result->order_status}: ¥" . number_format($result->total_sales) . "\n";
}

// 3. AJAXハンドラークラスのテスト
echo "\n3. AJAXハンドラークラステスト...\n";

// AJAXクラスファイルを読み込み
require_once('wordpress/wp-content/plugins/KantanPro/includes/class-ktpwp-ajax.php');

// AJAXクラスのインスタンスを作成
$ajax_handler = new KTPWP_Ajax();

// リフレクションを使用してプライベートメソッドをテスト
$reflection = new ReflectionClass($ajax_handler);

// get_sales_chart_dataメソッドをテスト
if ($reflection->hasMethod('get_sales_chart_data')) {
    $method = $reflection->getMethod('get_sales_chart_data');
    $method->setAccessible(true);
    
    $sales_data = $method->invoke($ajax_handler, 'all_time');
    echo "売上データ取得成功:\n";
    echo "月別売上: " . json_encode($sales_data['monthly_sales']) . "\n";
    echo "進捗別売上: " . json_encode($sales_data['progress_sales']) . "\n";
} else {
    echo "get_sales_chart_dataメソッドが見つかりません\n";
}

echo "\n=== テスト完了 ===\n";
?> 