<?php
/**
 * ダミーデータ作成スクリプト
 * 
 * 以下のデータを作成します：
 * - 顧客×6件
 * - 協力会社×6件
 * - サービス×6件（一般：税率10%・食品：税率8%・不動産：非課税）
 * - 受注書×18件（顧客×3件）
 * - 職能×18件（協力会社×3件）
 */

// WordPress環境の読み込み
require_once('../../../wp-config.php');

// セキュリティチェック
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__FILE__) . '/../../../');
}

global $wpdb;

echo "ダミーデータ作成を開始します...\n";

// 1. 顧客データの作成
$clients = array(
    array('company_name' => '株式会社サンプル商事', 'name' => '田中太郎', 'email' => 'info@kantanpro.com', 'memo' => '大手商社'),
    array('company_name' => '有限会社テックソリューション', 'name' => '佐藤花子', 'email' => 'info@kantanpro.com', 'memo' => 'IT企業'),
    array('company_name' => '合同会社デザイン工房', 'name' => '鈴木一郎', 'email' => 'info@kantanpro.com', 'memo' => 'デザイン会社'),
    array('company_name' => '株式会社マーケティングプロ', 'name' => '高橋美咲', 'email' => 'info@kantanpro.com', 'memo' => 'マーケティング会社'),
    array('company_name' => '有限会社建設工業', 'name' => '渡辺健太', 'email' => 'info@kantanpro.com', 'memo' => '建設会社'),
    array('company_name' => '株式会社フードサービス', 'name' => '伊藤恵子', 'email' => 'info@kantanpro.com', 'memo' => '飲食会社')
);

$client_ids = array();
foreach ($clients as $client) {
    $result = $wpdb->insert(
        $wpdb->prefix . 'ktp_client',
        array(
            'company_name' => $client['company_name'],
            'name' => $client['name'],
            'email' => $client['email'],
            'memo' => $client['memo']
        ),
        array('%s', '%s', '%s', '%s')
    );
    
    if ($result) {
        $client_ids[] = $wpdb->insert_id;
        echo "顧客作成: {$client['company_name']}\n";
    }
}

// 2. 協力会社データの作成
$suppliers = array(
    array('company_name' => '株式会社フリーランスネット', 'name' => '山田次郎', 'email' => 'info@kantanpro.com', 'memo' => 'フリーランス専門'),
    array('company_name' => '有限会社デジタルクリエイター', 'name' => '中村由美', 'email' => 'info@kantanpro.com', 'memo' => 'デジタル制作'),
    array('company_name' => '合同会社システム開発', 'name' => '小林正男', 'email' => 'info@kantanpro.com', 'memo' => 'システム開発'),
    array('company_name' => '株式会社ウェブデザイン', 'name' => '加藤真理', 'email' => 'info@kantanpro.com', 'memo' => 'ウェブデザイン'),
    array('company_name' => '有限会社コンサルティング', 'name' => '松本和也', 'email' => 'info@kantanpro.com', 'memo' => '経営コンサル'),
    array('company_name' => '株式会社ロジスティクス', 'name' => '井上智子', 'email' => 'info@kantanpro.com', 'memo' => '物流サービス')
);

$supplier_ids = array();
foreach ($suppliers as $supplier) {
    $result = $wpdb->insert(
        $wpdb->prefix . 'ktp_supplier',
        array(
            'company_name' => $supplier['company_name'],
            'name' => $supplier['name'],
            'email' => $supplier['email'],
            'memo' => $supplier['memo'],
            'time' => time()
        ),
        array('%s', '%s', '%s', '%s', '%d')
    );
    
    if ($result) {
        $supplier_ids[] = $wpdb->insert_id;
        echo "協力会社作成: {$supplier['company_name']}\n";
    }
}

// 3. サービスデータの作成
$services = array(
    // 一般（税率10%）
    array('service_name' => 'ウェブサイト制作', 'price' => 500000, 'tax_rate' => 10.00, 'unit' => '式', 'category' => '一般'),
    array('service_name' => 'システム開発', 'price' => 800000, 'tax_rate' => 10.00, 'unit' => '式', 'category' => '一般'),
    
    // 食品（税率8%）
    array('service_name' => 'ケータリングサービス', 'price' => 150000, 'tax_rate' => 8.00, 'unit' => '式', 'category' => '食品'),
    array('service_name' => '食材配送', 'price' => 50000, 'tax_rate' => 8.00, 'unit' => '式', 'category' => '食品'),
    
    // 不動産（非課税）
    array('service_name' => '不動産仲介', 'price' => 300000, 'tax_rate' => null, 'unit' => '式', 'category' => '不動産'),
    array('service_name' => '物件管理', 'price' => 100000, 'tax_rate' => null, 'unit' => '月', 'category' => '不動産')
);

$service_ids = array();
foreach ($services as $service) {
    $result = $wpdb->insert(
        $wpdb->prefix . 'ktp_service',
        array(
            'service_name' => $service['service_name'],
            'price' => $service['price'],
            'tax_rate' => $service['tax_rate'],
            'unit' => $service['unit'],
            'category' => $service['category']
        ),
        array('%s', '%f', '%f', '%s', '%s')
    );
    
    if ($result) {
        $service_ids[] = $wpdb->insert_id;
        echo "サービス作成: {$service['service_name']} (税率: " . ($service['tax_rate'] ?? '非課税') . "%)\n";
    }
}

// 4. 受注書データの作成（顧客×3件）
$order_statuses = array('pending', 'ordered', 'completed'); // 受付中、受注、完成
$order_names = array('Webサイトリニューアル', 'ECサイト構築', '業務システム開発', 'マーケティング戦略策定', 'ロゴデザイン制作', 'データ分析サービス');

$order_ids = array();
foreach ($client_ids as $client_id) {
    foreach ($order_statuses as $status) {
        $project_name = $order_names[array_rand($order_names)];
        $order_date = date('Y-m-d', strtotime('-' . rand(1, 30) . ' days'));
        $delivery_date = date('Y-m-d', strtotime('+' . rand(1, 60) . ' days'));
        $total_amount = rand(100000, 1000000);
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'ktp_order',
            array(
                'order_number' => 'ORD-' . date('Ymd') . '-' . sprintf('%03d', rand(1, 999)),
                'client_id' => $client_id,
                'project_name' => $project_name,
                'order_date' => $order_date,
                'desired_delivery_date' => $delivery_date,
                'expected_delivery_date' => $delivery_date,
                'total_amount' => $total_amount,
                'status' => $status
            ),
            array('%s', '%d', '%s', '%s', '%s', '%s', '%f', '%s')
        );
        
        if ($result) {
            $order_ids[] = $wpdb->insert_id;
            echo "受注書作成: {$project_name} (ステータス: {$status})\n";
        }
    }
}

// 5. 職能データの作成（協力会社×3件）
$skill_names = array('プログラミング', 'デザイン', 'ライティング', 'マーケティング', 'コンサルティング', 'データ分析', '翻訳', '動画編集', '写真撮影');
$tax_rates = array(10.00, 8.00, null); // 税率10%、税率8%、非課税

foreach ($supplier_ids as $supplier_id) {
    foreach ($tax_rates as $tax_rate) {
        $product_name = $skill_names[array_rand($skill_names)];
        $unit_price = rand(5000, 50000);
        $quantity = rand(1, 10);
        $unit = '時間';
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'ktp_supplier_skills',
            array(
                'supplier_id' => $supplier_id,
                'product_name' => $product_name,
                'unit_price' => $unit_price,
                'quantity' => $quantity,
                'unit' => $unit,
                'tax_rate' => $tax_rate,
                'frequency' => rand(1, 100)
            ),
            array('%d', '%s', '%f', '%d', '%s', '%f', '%d')
        );
        
        if ($result) {
            echo "職能作成: {$product_name} (税率: " . ($tax_rate ?? '非課税') . "%)\n";
        }
    }
}

echo "\nダミーデータ作成が完了しました！\n";
echo "作成されたデータ:\n";
echo "- 顧客: " . count($client_ids) . "件\n";
echo "- 協力会社: " . count($supplier_ids) . "件\n";
echo "- サービス: " . count($service_ids) . "件\n";
echo "- 受注書: " . count($order_ids) . "件\n";
echo "- 職能: " . (count($supplier_ids) * 3) . "件\n";
?> 