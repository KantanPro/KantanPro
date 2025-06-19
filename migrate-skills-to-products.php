<?php
/**
 * 職能テーブルを商品テーブルにマイグレーションするスクリプト
 * 
 * 使用方法: ブラウザで直接アクセスするか、WP-CLIで実行
 * 
 * @package KTPWP
 * @since 2.0.0
 */

// WordPress環境をロード
if (!defined('WP_USE_THEMES')) {
    define('WP_USE_THEMES', false);
}

// WordPressのパスを設定（環境に合わせて調整）
$wp_load_path = dirname(dirname(dirname(__DIR__))) . '/wp-load.php';
if (file_exists($wp_load_path)) {
    require_once($wp_load_path);
} else {
    die('WordPress環境をロードできませんでした。');
}

// 管理者権限チェック
if (!current_user_can('manage_options')) {
    wp_die('このスクリプトを実行する権限がありません。');
}

echo "<h1>職能テーブル → 商品テーブル マイグレーション</h1>\n";

global $wpdb;
$table_name = $wpdb->prefix . 'ktp_supplier_skills';

// 現在のテーブル構造を確認
echo "<h2>現在のテーブル構造確認</h2>\n";
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");

if (!$table_exists) {
    echo "<p>テーブル '{$table_name}' が存在しません。新規作成します。</p>\n";
    
    // 新しいテーブルを作成
    require_once dirname(__FILE__) . '/includes/class-ktpwp-supplier-skills.php';
    $skills_manager = KTPWP_Supplier_Skills::get_instance();
    $result = $skills_manager->create_table();
    
    if ($result) {
        echo "<p>✓ 新しい商品テーブルを作成しました。</p>\n";
    } else {
        echo "<p>✗ テーブル作成に失敗しました。</p>\n";
    }
    exit;
}

// 現在のテーブル構造を取得
$columns = $wpdb->get_results("DESCRIBE $table_name");
$column_names = array_column($columns, 'Field');

echo "<p>現在のカラム: " . implode(', ', $column_names) . "</p>\n";

// 新しい構造のカラムが存在するかチェック
$has_new_structure = in_array('product_name', $column_names) && 
                     in_array('unit_price', $column_names) && 
                     in_array('quantity', $column_names);

if ($has_new_structure) {
    echo "<p>✓ 既に新しい商品テーブル構造になっています。</p>\n";
    exit;
}

// 既存データのバックアップ
echo "<h2>データバックアップ</h2>\n";
$backup_table = $table_name . '_backup_' . date('Y_m_d_H_i_s');
$backup_result = $wpdb->query("CREATE TABLE $backup_table AS SELECT * FROM $table_name");

if ($backup_result !== false) {
    echo "<p>✓ バックアップテーブル '{$backup_table}' を作成しました。</p>\n";
} else {
    echo "<p>✗ バックアップの作成に失敗しました。処理を中止します。</p>\n";
    exit;
}

// 既存データを取得
$existing_data = $wpdb->get_results("SELECT * FROM $table_name", ARRAY_A);
echo "<p>既存データ件数: " . count($existing_data) . "</p>\n";

// テーブル構造を変更
echo "<h2>テーブル構造変更</h2>\n";

// 古いテーブルを削除
$drop_result = $wpdb->query("DROP TABLE $table_name");
if ($drop_result !== false) {
    echo "<p>✓ 古いテーブルを削除しました。</p>\n";
} else {
    echo "<p>✗ 古いテーブルの削除に失敗しました。</p>\n";
    exit;
}

// 新しいテーブル構造で作成
require_once dirname(__FILE__) . '/includes/class-ktpwp-supplier-skills.php';
$skills_manager = KTPWP_Supplier_Skills::get_instance();
$create_result = $skills_manager->create_table();

if ($create_result) {
    echo "<p>✓ 新しい商品テーブル構造でテーブルを作成しました。</p>\n";
} else {
    echo "<p>✗ 新しいテーブルの作成に失敗しました。</p>\n";
    exit;
}

// データ移行
echo "<h2>データ移行</h2>\n";
$migrated_count = 0;
$failed_count = 0;

foreach ($existing_data as $old_record) {
    // 旧構造から新構造へのマッピング
    $new_data = array(
        'supplier_id' => $old_record['supplier_id'],
        'product_name' => $old_record['skill_name'] ?? '商品名未設定',
        'unit_price' => floatval($old_record['price'] ?? 0),
        'quantity' => 1, // デフォルト値
        'unit' => $old_record['unit'] ?? '式', // デフォルト値
        'priority_order' => $old_record['priority_order'] ?? 0,
        'is_active' => $old_record['is_active'] ?? 1,
        'created_at' => $old_record['created_at'] ?? current_time('mysql'),
        'updated_at' => current_time('mysql')
    );
    
    $insert_result = $wpdb->insert(
        $table_name,
        $new_data,
        array('%d', '%s', '%f', '%d', '%s', '%d', '%d', '%s', '%s')
    );
    
    if ($insert_result !== false) {
        $migrated_count++;
    } else {
        $failed_count++;
        echo "<p>⚠ レコードID {$old_record['id']} の移行に失敗: " . $wpdb->last_error . "</p>\n";
    }
}

echo "<p>✓ データ移行完了: {$migrated_count}件成功, {$failed_count}件失敗</p>\n";

// マイグレーション結果の確認
echo "<h2>マイグレーション結果確認</h2>\n";
$new_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
echo "<p>新しいテーブルのレコード数: {$new_count}</p>\n";

if ($new_count > 0) {
    echo "<p>サンプルデータ:</p>\n";
    $sample_data = $wpdb->get_results("SELECT * FROM $table_name LIMIT 3", ARRAY_A);
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
    echo "<tr><th>ID</th><th>協力会社ID</th><th>商品名</th><th>単価</th><th>数量</th><th>単位</th></tr>\n";
    foreach ($sample_data as $row) {
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>{$row['supplier_id']}</td>";
        echo "<td>" . esc_html($row['product_name']) . "</td>";
        echo "<td>" . number_format($row['unit_price'], 2) . "</td>";
        echo "<td>{$row['quantity']}</td>";
        echo "<td>" . esc_html($row['unit']) . "</td>";
        echo "</tr>\n";
    }
    echo "</table>\n";
}

echo "<h2>マイグレーション完了</h2>\n";
echo "<p>✅ 職能テーブルから商品テーブルへのマイグレーションが正常に完了しました。</p>\n";
echo "<p>バックアップテーブル '{$backup_table}' は安全のため保持されています。</p>\n";
echo "<p>問題がないことを確認後、必要に応じてバックアップテーブルを削除してください。</p>\n";

// マイグレーション情報をログに記録
error_log("KTPWP: Skills to Products migration completed. Migrated: {$migrated_count}, Failed: {$failed_count}");
?>
