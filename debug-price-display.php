<?php
/**
 * 価格表示デバッグ用スクリプト
 */

// WordPressの設定ファイルを読み込み
$wp_config_path = '../../../../wp-config.php';
if (file_exists($wp_config_path)) {
    require_once($wp_config_path);
} else {
    echo "WordPressの設定ファイルが見つかりません。";
    exit;
}

echo "<h1>価格表示デバッグ</h1>";

// データベース接続
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASSWORD,
        array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        )
    );
    
    echo "<p>✅ データベース接続成功</p>";
    
} catch (PDOException $e) {
    echo "<p>❌ データベース接続エラー: " . $e->getMessage() . "</p>";
    exit;
}

// サービスタブのテーブル名
$table_prefix = defined('DB_PREFIX') ? DB_PREFIX : 'wp_';
$service_table_name = $table_prefix . 'ktp_service';

echo "<h2>データベース内の価格データ確認</h2>";

// 最新の5件のデータを取得
$stmt = $pdo->prepare("SELECT id, service_name, price FROM `{$service_table_name}` ORDER BY id DESC LIMIT 5");
$stmt->execute();
$test_data = $stmt->fetchAll();

if ($test_data) {
    echo "<h3>最新の5件のデータ:</h3>";
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background-color: #f0f0f0;'>";
    echo "<th>ID</th><th>サービス名</th><th>価格（DB値）</th><th>価格（型）</th>";
    echo "</tr>";
    
    foreach ($test_data as $row) {
        $price = $row['price'];
        $price_type = gettype($price);
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>{$row['service_name']}</td>";
        echo "<td>{$price}</td>";
        echo "<td>{$price_type}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>テストデータがありません。</p>";
}

echo "<h2>format_price_display()メソッドのテスト</h2>";

// format_price_display()メソッドのテスト
function format_price_display($price) {
    // 小数点以下がある場合は2桁まで表示（末尾の0は消す）
    if (is_numeric($price)) {
        $formatted = rtrim(rtrim(number_format($price, 2, '.', ''), '0'), '.');
        return $formatted;
    }
    return $price;
}

echo "<h3>テストケース:</h3>";
$test_cases = array(777, 777.0, 777.01, 777.10, 777.00, 777.99);

echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr style='background-color: #f0f0f0;'>";
echo "<th>入力値</th><th>format_price_display()結果</th>";
echo "</tr>";

foreach ($test_cases as $test_price) {
    $result = format_price_display($test_price);
    echo "<tr>";
    echo "<td>{$test_price}</td>";
    echo "<td>{$result}</td>";
    echo "</tr>";
}
echo "</table>";

echo "<h2>実際のデータでのテスト</h2>";

if ($test_data) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background-color: #f0f0f0;'>";
    echo "<th>ID</th><th>サービス名</th><th>DB価格</th><th>format_price_display()結果</th>";
    echo "</tr>";
    
    foreach ($test_data as $row) {
        $price = $row['price'];
        $formatted = format_price_display($price);
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>{$row['service_name']}</td>";
        echo "<td>{$price}</td>";
        echo "<td>{$formatted}</td>";
        echo "</tr>";
    }
    echo "</table>";
}

echo "<p><a href='debug-price-display.php'>ページを再読み込み</a></p>";
?> 