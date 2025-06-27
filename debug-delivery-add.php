<?php
/**
 * 納期フィールド追加デバッグファイル
 */

// エラー表示を有効化
error_reporting(E_ALL);
ini_set('display_errors', 1);

// WordPress環境を読み込み
if (!file_exists('../../../wp-load.php')) {
    echo "<h1>❌ WordPress環境が見つかりません</h1>";
    echo "<p>現在のディレクトリ: " . __DIR__ . "</p>";
    echo "<p>wp-load.phpの期待パス: " . realpath('../../../wp-load.php') . "</p>";
    echo "<p>WordPressのルートディレクトリを確認してください。</p>";
    exit;
}

require_once('../../../wp-load.php');

// WordPress読み込みに失敗した場合の代替手段
if (!function_exists('get_option')) {
    echo "<h1>⚠️ WordPress環境の読み込みに失敗</h1>";
    echo "<p>データベース接続情報を手動で設定します。</p>";
    
    // データベース設定（wp-config.phpから取得するか、手動で設定）
    $db_host = 'localhost';
    $db_name = 'wordpress'; // デフォルトのデータベース名
    $db_user = 'root';     // ユーザー名を確認してください
    $db_pass = '';         // パスワードを確認してください
    $table_prefix = 'wp_'; // テーブルプレフィックスを確認してください
    
    // データベース接続
    $wpdb = new mysqli($db_host, $db_user, $db_pass, $db_name);
    
    if ($wpdb->connect_error) {
        echo "<p>❌ データベース接続エラー: " . $wpdb->connect_error . "</p>";
        echo "<p>データベース設定を確認してください。</p>";
        exit;
    }
    
    echo "<p>✅ データベース接続成功</p>";
} else {
    global $wpdb;
}

echo "<h1>納期フィールド追加デバッグ</h1>";

// テーブル名の設定
if (isset($table_prefix)) {
    $table_name = $table_prefix . 'ktp_order';
} else {
    $table_name = $wpdb->prefix . 'ktp_order';
}

echo "<h2>1. 基本情報確認</h2>";
echo "テーブル名: {$table_name}<br>";
echo "WordPressプレフィックス: " . $wpdb->prefix . "<br>";
echo "データベース名: " . DB_NAME . "<br>";

echo "<h2>2. テーブル存在確認</h2>";
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'");
if ($table_exists) {
    echo "✅ テーブル存在: {$table_name}<br>";
} else {
    echo "❌ テーブル不存在: {$table_name}<br>";
    
    echo "<h3>テーブル作成</h3>";
    echo "<form method='post' style='margin: 10px 0;'>";
    echo "<input type='hidden' name='create_table' value='1'>";
    echo "<button type='submit' style='background: #dc3545; color: white; padding: 10px; border: none; border-radius: 4px;'>受注書テーブルを作成</button>";
    echo "</form>";
    
    // 類似テーブルを検索
    echo "<h3>類似テーブルの検索:</h3>";
    $similar_tables = $wpdb->get_results("SHOW TABLES LIKE '%ktp%'");
    if ($similar_tables) {
        echo "<ul>";
        foreach ($similar_tables as $table) {
            $table_array = get_object_vars($table);
            $table_name_actual = array_values($table_array)[0];
            echo "<li>📋 {$table_name_actual}</li>";
        }
        echo "</ul>";
    }
    exit;
}

echo "<h2>3. 現在のカラム構造</h2>";
$columns = $wpdb->get_results("SHOW COLUMNS FROM `{$table_name}`");
if ($columns) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background-color: #f0f0f0;'>";
    echo "<th>フィールド名</th><th>型</th><th>NULL</th><th>キー</th><th>デフォルト</th><th>Extra</th>";
    echo "</tr>";
    
    foreach ($columns as $column) {
        $is_target = in_array($column->Field, ['desired_delivery_date', 'expected_delivery_date']);
        $bg_color = $is_target ? '#ffffcc' : '';
        
        echo "<tr style='background-color: {$bg_color};'>";
        echo "<td>{$column->Field}</td>";
        echo "<td>{$column->Type}</td>";
        echo "<td>{$column->Null}</td>";
        echo "<td>{$column->Key}</td>";
        echo "<td>" . ($column->Default ?? 'NULL') . "</td>";
        echo "<td>{$column->Extra}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "❌ カラム情報の取得に失敗<br>";
    echo "エラー: " . $wpdb->last_error . "<br>";
    exit;
}

echo "<h2>4. 納期フィールドの存在確認</h2>";
$column_names = array_column($columns, 'Field');
$desired_exists = in_array('desired_delivery_date', $column_names);
$expected_exists = in_array('expected_delivery_date', $column_names);

echo "希望納期フィールド (desired_delivery_date): " . ($desired_exists ? "✅ 存在" : "❌ 不存在") . "<br>";
echo "納品予定日フィールド (expected_delivery_date): " . ($expected_exists ? "✅ 存在" : "❌ 不存在") . "<br>";

echo "<h2>5. 手動でフィールドを追加</h2>";

// 既存のcreated_atカラムの修正
echo "<h3>created_atカラムの修正</h3>";
echo "<form method='post' style='margin: 10px 0;'>";
echo "<input type='hidden' name='fix_created_at' value='1'>";
echo "<button type='submit' style='background: #ffc107; color: black; padding: 10px; border: none; border-radius: 4px;'>created_atカラムを修正</button>";
echo "</form>";

if (!$desired_exists) {
    echo "<h3>希望納期フィールドを手動追加</h3>";
    echo "<form method='post' style='margin: 10px 0;'>";
    echo "<input type='hidden' name='add_desired' value='1'>";
    echo "<button type='submit' style='background: #007cba; color: white; padding: 10px; border: none; border-radius: 4px;'>希望納期フィールドを追加</button>";
    echo "</form>";
}

if (!$expected_exists) {
    echo "<h3>納品予定日フィールドを手動追加</h3>";
    echo "<form method='post' style='margin: 10px 0;'>";
    echo "<input type='hidden' name='add_expected' value='1'>";
    echo "<button type='submit' style='background: #007cba; color: white; padding: 10px; border: none; border-radius: 4px;'>納品予定日フィールドを追加</button>";
    echo "</form>";
}

// フィールド追加処理
if (isset($_POST['create_table'])) {
    echo "<h3>受注書テーブル作成中...</h3>";
    
    $sql = "CREATE TABLE `{$table_name}` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `order_number` varchar(50) NOT NULL COMMENT '受注番号',
        `client_id` int(11) NOT NULL COMMENT 'クライアントID',
        `project_name` varchar(255) NOT NULL COMMENT 'プロジェクト名',
        `order_date` date NOT NULL COMMENT '受注日',
        `desired_delivery_date` date NULL DEFAULT NULL COMMENT '希望納期',
        `expected_delivery_date` date NULL DEFAULT NULL COMMENT '納品予定日',
        `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '合計金額',
        `status` varchar(20) NOT NULL DEFAULT 'pending' COMMENT 'ステータス',
        `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
        `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日時',
        PRIMARY KEY (`id`),
        UNIQUE KEY `order_number` (`order_number`),
        KEY `client_id` (`client_id`),
        KEY `order_date` (`order_date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='受注書テーブル';";
    
    echo "実行SQL: <pre>" . htmlspecialchars($sql) . "</pre><br>";
    
    $result = $wpdb->query($sql);
    
    if ($result !== false) {
        echo "✅ 受注書テーブルの作成に成功しました<br>";
        echo "<script>setTimeout(function(){ location.reload(); }, 2000);</script>";
    } else {
        echo "❌ 受注書テーブルの作成に失敗しました<br>";
        echo "エラー: " . $wpdb->last_error . "<br>";
    }
}

// created_atカラム修正処理
if (isset($_POST['fix_created_at'])) {
    echo "<h3>created_atカラム修正中...</h3>";
    
    // 現在のcreated_atカラムの設定を確認
    $created_at_info = $wpdb->get_row("SHOW COLUMNS FROM `{$table_name}` LIKE 'created_at'");
    
    if ($created_at_info) {
        echo "現在のcreated_at設定: デフォルト値 = " . ($created_at_info->Default ?? 'NULL') . "<br>";
        
        // デフォルト値を修正
        $sql = "ALTER TABLE `{$table_name}` MODIFY COLUMN `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時'";
        echo "実行SQL: <pre>" . htmlspecialchars($sql) . "</pre><br>";
        
        $result = $wpdb->query($sql);
        
        if ($result !== false) {
            echo "✅ created_atカラムの修正に成功しました<br>";
            echo "<script>setTimeout(function(){ location.reload(); }, 2000);</script>";
        } else {
            echo "❌ created_atカラムの修正に失敗しました<br>";
            echo "エラー: " . $wpdb->last_error . "<br>";
        }
    } else {
        echo "❌ created_atカラムが見つかりません<br>";
    }
}

if (isset($_POST['add_desired'])) {
    echo "<h3>希望納期フィールド追加中...</h3>";
    $sql = "ALTER TABLE `{$table_name}` ADD COLUMN `desired_delivery_date` DATE NULL DEFAULT NULL COMMENT '希望納期'";
    echo "実行SQL: {$sql}<br>";
    
    $result = $wpdb->query($sql);
    
    if ($result !== false) {
        echo "✅ 希望納期フィールドの追加に成功しました<br>";
    } else {
        echo "❌ 希望納期フィールドの追加に失敗しました<br>";
        echo "エラー: " . $wpdb->last_error . "<br>";
    }
}

if (isset($_POST['add_expected'])) {
    echo "<h3>納品予定日フィールド追加中...</h3>";
    $sql = "ALTER TABLE `{$table_name}` ADD COLUMN `expected_delivery_date` DATE NULL DEFAULT NULL COMMENT '納品予定日'";
    echo "実行SQL: {$sql}<br>";
    
    $result = $wpdb->query($sql);
    
    if ($result !== false) {
        echo "✅ 納品予定日フィールドの追加に成功しました<br>";
    } else {
        echo "❌ 納品予定日フィールドの追加に失敗しました<br>";
        echo "エラー: " . $wpdb->last_error . "<br>";
    }
}

echo "<h2>6. 直接SQL実行</h2>";
echo "<p>以下のSQLをphpMyAdminで直接実行することもできます：</p>";

echo "<h3>created_atカラム修正</h3>";
echo "<pre style='background: #f5f5f5; padding: 10px; border: 1px solid #ccc;'>";
echo "ALTER TABLE `{$table_name}` MODIFY COLUMN `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時';";
echo "</pre>";

if (!$desired_exists) {
    echo "<pre style='background: #f5f5f5; padding: 10px; border: 1px solid #ccc;'>";
    echo "ALTER TABLE `{$table_name}` ADD COLUMN `desired_delivery_date` DATE NULL DEFAULT NULL COMMENT '希望納期';";
    echo "</pre>";
}

if (!$expected_exists) {
    echo "<pre style='background: #f5f5f5; padding: 10px; border: 1px solid #ccc;'>";
    echo "ALTER TABLE `{$table_name}` ADD COLUMN `expected_delivery_date` DATE NULL DEFAULT NULL COMMENT '納品予定日';";
    echo "</pre>";
}

echo "<p><a href='javascript:location.reload()' style='background: #28a745; color: white; padding: 10px; text-decoration: none; border-radius: 4px;'>ページを再読み込み</a></p>";
?> 