<?php
/**
 * ダミーデータで作成された受注書の作成日時修正マイグレーション
 * 
 * 問題：ダミーデータで作成された受注書のtimeフィールドが0になっているため、
 * 作成日が表示されない
 * 
 * 解決策：order_dateを基に適切なtimeフィールドを設定する
 */

// WordPress環境の読み込み
if (!defined('ABSPATH')) {
    require_once(dirname(__FILE__) . '/../../../../wp-config.php');
}

global $wpdb;

echo "ダミーデータ受注書の作成日時修正を開始します...\n";

// 対象テーブル
$table_name = $wpdb->prefix . 'ktp_order';

// timeフィールドが0またはNULLの受注書を取得
$orders_to_fix = $wpdb->get_results(
    "SELECT id, order_date, order_number, project_name 
     FROM {$table_name} 
     WHERE (time = 0 OR time IS NULL) 
     AND order_date != '0000-00-00' 
     AND order_date IS NOT NULL"
);

if (empty($orders_to_fix)) {
    echo "修正対象の受注書が見つかりませんでした。\n";
    return;
}

echo "修正対象受注書数: " . count($orders_to_fix) . "\n";

$updated_count = 0;
$error_count = 0;

foreach ($orders_to_fix as $order) {
    // order_dateを基に適切なタイムスタンプを生成
    // 受注日の9:00-18:00の間のランダムな時間を設定
    $order_date = $order->order_date;
    $hour = rand(9, 18);
    $minute = rand(0, 59);
    $second = rand(0, 59);
    
    // 日付文字列からタイムスタンプを生成
    $datetime_string = $order_date . ' ' . sprintf('%02d:%02d:%02d', $hour, $minute, $second);
    $timestamp = strtotime($datetime_string);
    
    if ($timestamp === false) {
        echo "エラー: 受注書ID {$order->id} の日付変換に失敗: {$order_date}\n";
        $error_count++;
        continue;
    }
    
    // timeフィールドを更新
    $result = $wpdb->update(
        $table_name,
        array('time' => $timestamp),
        array('id' => $order->id),
        array('%d'),
        array('%d')
    );
    
    if ($result === false) {
        echo "エラー: 受注書ID {$order->id} の更新に失敗: " . $wpdb->last_error . "\n";
        $error_count++;
    } else {
        $formatted_date = date('Y-m-d H:i:s', $timestamp);
        echo "成功: 受注書ID {$order->id} ({$order->order_number}) の作成日時を {$formatted_date} に設定\n";
        $updated_count++;
    }
}

echo "\n修正完了:\n";
echo "- 更新成功: {$updated_count}件\n";
echo "- エラー: {$error_count}件\n";

// 修正後の確認
$remaining_zero_time = $wpdb->get_var(
    "SELECT COUNT(*) FROM {$table_name} WHERE time = 0 OR time IS NULL"
);

echo "修正後、timeフィールドが0またはNULLの受注書数: {$remaining_zero_time}件\n";

if ($remaining_zero_time > 0) {
    echo "注意: まだ修正が必要な受注書が残っています。\n";
} else {
    echo "全ての受注書の作成日時が正常に設定されました。\n";
} 