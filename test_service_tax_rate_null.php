<?php
/**
 * 税率NULLのサービス読み込みテスト
 */

// WordPress環境の読み込み
require_once('../../../wp-load.php');

// デバッグモードを有効化
if (!defined('WP_DEBUG')) {
    define('WP_DEBUG', true);
}
if (!defined('WP_DEBUG_LOG')) {
    define('WP_DEBUG_LOG', true);
}

echo "<h1>税率NULLのサービス読み込みテスト</h1>\n";

// サービスDBクラスの読み込み
if (!class_exists('KTPWP_Service_DB')) {
    require_once KTPWP_PLUGIN_DIR . 'includes/class-ktpwp-service-db.php';
}

if (!class_exists('KTPWP_Service_DB')) {
    echo "<p style='color: red;'>エラー: KTPWP_Service_DBクラスが見つかりません</p>\n";
    exit;
}

$service_db = KTPWP_Service_DB::get_instance();
if (!$service_db) {
    echo "<p style='color: red;'>エラー: サービスDBインスタンスの取得に失敗</p>\n";
    exit;
}

// サービス一覧を取得
$search_args = array(
    'limit'    => 50,
    'offset'   => 0,
    'order_by' => 'id',
    'order'    => 'DESC',
    'search'   => '',
    'category' => '',
);

$services = $service_db->get_services('service', $search_args);

echo "<h2>取得されたサービス一覧</h2>\n";
echo "<p>取得件数: " . count($services) . "件</p>\n";

if (empty($services)) {
    echo "<p style='color: orange;'>警告: サービスが見つかりません</p>\n";
} else {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
    echo "<tr style='background-color: #f0f0f0;'>\n";
    echo "<th>ID</th>\n";
    echo "<th>サービス名</th>\n";
    echo "<th>価格</th>\n";
    echo "<th>単位</th>\n";
    echo "<th>税率</th>\n";
    echo "<th>税率の型</th>\n";
    echo "<th>税率NULL判定</th>\n";
    echo "</tr>\n";
    
    foreach ($services as $service) {
        $tax_rate = isset($service->tax_rate) ? $service->tax_rate : 'NOT_SET';
        $tax_rate_type = gettype($tax_rate);
        $is_null = ($tax_rate === null) ? 'YES' : 'NO';
        
        echo "<tr>\n";
        echo "<td>" . esc_html($service->id) . "</td>\n";
        echo "<td>" . esc_html($service->service_name) . "</td>\n";
        echo "<td>" . esc_html($service->price) . "</td>\n";
        echo "<td>" . esc_html($service->unit) . "</td>\n";
        echo "<td>" . esc_html($tax_rate) . "</td>\n";
        echo "<td>" . esc_html($tax_rate_type) . "</td>\n";
        echo "<td style='color: " . ($is_null === 'YES' ? 'green' : 'black') . ";'>" . esc_html($is_null) . "</td>\n";
        echo "</tr>\n";
    }
    
    echo "</table>\n";
}

// Ajaxハンドラーのテスト
echo "<h2>Ajaxハンドラーのテスト</h2>\n";

// モックPOSTデータを作成
$_POST['action'] = 'ktp_get_service_list';
$_POST['page'] = '1';
$_POST['limit'] = '20';
$_POST['search'] = '';
$_POST['category'] = '';
$_POST['nonce'] = wp_create_nonce('ktp_ajax_nonce');

// Ajaxハンドラーを直接呼び出し
if (class_exists('KTPWP_Ajax')) {
    $ajax_handler = KTPWP_Ajax::get_instance();
    
    // 出力をキャプチャ
    ob_start();
    $ajax_handler->ajax_get_service_list();
    $response = ob_get_clean();
    
    echo "<h3>Ajaxレスポンス</h3>\n";
    echo "<pre style='background-color: #f5f5f5; padding: 10px; border: 1px solid #ccc;'>\n";
    echo esc_html($response);
    echo "</pre>\n";
    
    // JSONレスポンスを解析
    $json_data = json_decode($response, true);
    if ($json_data && isset($json_data['success']) && $json_data['success']) {
        echo "<h3>解析されたサービスデータ</h3>\n";
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
        echo "<tr style='background-color: #f0f0f0;'>\n";
        echo "<th>ID</th>\n";
        echo "<th>サービス名</th>\n";
        echo "<th>価格</th>\n";
        echo "<th>単位</th>\n";
        echo "<th>税率</th>\n";
        echo "<th>税率の型</th>\n";
        echo "<th>税率NULL判定</th>\n";
        echo "</tr>\n";
        
        foreach ($json_data['data']['services'] as $service) {
            $tax_rate = isset($service['tax_rate']) ? $service['tax_rate'] : 'NOT_SET';
            $tax_rate_type = gettype($tax_rate);
            $is_null = ($tax_rate === null) ? 'YES' : 'NO';
            
            echo "<tr>\n";
            echo "<td>" . esc_html($service['id']) . "</td>\n";
            echo "<td>" . esc_html($service['service_name']) . "</td>\n";
            echo "<td>" . esc_html($service['price']) . "</td>\n";
            echo "<td>" . esc_html($service['unit']) . "</td>\n";
            echo "<td>" . esc_html($tax_rate) . "</td>\n";
            echo "<td>" . esc_html($tax_rate_type) . "</td>\n";
            echo "<td style='color: " . ($is_null === 'YES' ? 'green' : 'black') . ";'>" . esc_html($is_null) . "</td>\n";
            echo "</tr>\n";
        }
        
        echo "</table>\n";
    } else {
        echo "<p style='color: red;'>Ajaxレスポンスの解析に失敗しました</p>\n";
    }
} else {
    echo "<p style='color: red;'>KTPWP_Ajaxクラスが見つかりません</p>\n";
}

echo "<h2>テスト完了</h2>\n";
echo "<p>税率NULLのサービスが正しく処理されているか確認してください。</p>\n";
?> 