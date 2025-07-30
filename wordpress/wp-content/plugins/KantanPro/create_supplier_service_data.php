<?php
/**
 * 協力会社とサービスのダミーデータ作成スクリプト
 * 
 * 以下のデータを作成します：
 * - 協力会社×6件
 * - 職能×18件（協力会社×3件、税率10%・8%・非課税）
 * - サービス×6件（一般：税率10%・食品：税率8%・不動産：非課税）
 */

// WordPress環境の読み込み
require_once('../../../wp-config.php');

// セキュリティチェック
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__FILE__) . '/../../../');
}

global $wpdb;

echo "協力会社とサービスのダミーデータ作成を開始します...\n";

// 1. 協力会社データの作成
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

// 2. サービスデータの作成
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

// 3. 職能データの作成（協力会社×3件）
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

echo "\n協力会社とサービスのダミーデータ作成が完了しました！\n";
echo "作成されたデータ:\n";
echo "- 協力会社: " . count($supplier_ids) . "件\n";
echo "- サービス: " . count($service_ids) . "件\n";
echo "- 職能: " . (count($supplier_ids) * 3) . "件\n";
?> 