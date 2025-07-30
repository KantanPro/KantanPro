<?php
/**
 * WP-CLIコマンド: ダミーデータ作成
 * 
 * 使用方法: wp ktp create-dummy-data
 * 
 * 以下のデータを作成します：
 * - 協力会社×6件
 * - 職能×18件（協力会社×6件 × 税率3パターン）
 * - サービス×6件（一般：税率10%・食品：税率8%・不動産：非課税）各×2
 */

if (!defined('WP_CLI') || !WP_CLI) {
    return;
}

/**
 * ダミーデータ作成コマンド
 */
class KTP_Create_Dummy_Data_Command {

    /**
     * ダミーデータを作成します
     *
     * ## OPTIONS
     *
     * [--force]
     * : 既存データがある場合でも強制的に作成する
     *
     * ## EXAMPLES
     *
     *     wp ktp create-dummy-data
     *     wp ktp create-dummy-data --force
     *
     * @param array $args
     * @param array $assoc_args
     */
    public function __invoke($args, $assoc_args) {
        global $wpdb;

        WP_CLI::log('ダミーデータ作成を開始します...');

        // 既存データのチェック
        if (!isset($assoc_args['force'])) {
            $existing_suppliers = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ktp_supplier");
            $existing_services = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ktp_service");
            
            if ($existing_suppliers > 0 || $existing_services > 0) {
                WP_CLI::warning('既存のデータが存在します。--forceオプションを使用して強制的に作成してください。');
                return;
            }
        }

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
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                ),
                array('%s', '%s', '%s', '%s', '%s', '%s')
            );
            
            if ($result) {
                $supplier_ids[] = $wpdb->insert_id;
                WP_CLI::log("✓ 協力会社作成: {$supplier['company_name']}");
            }
        }

        // 2. 職能データの作成（協力会社×6件 × 税率3パターン）
        $skill_names = array('プログラミング', 'デザイン', 'ライティング', 'マーケティング', 'コンサルティング', 'データ分析', '翻訳', '動画編集', '写真撮影', 'SEO対策', 'SNS運用', '動画制作');
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
                        'frequency' => rand(1, 100),
                        'created_at' => current_time('mysql'),
                        'updated_at' => current_time('mysql')
                    ),
                    array('%d', '%s', '%f', '%d', '%s', '%f', '%d', '%s', '%s')
                );
                
                if ($result) {
                    WP_CLI::log("✓ 職能作成: {$product_name} (税率: " . ($tax_rate ?? '非課税') . "%)");
                }
            }
        }

        // 3. サービスデータの作成（一般：税率10%・食品：税率8%・不動産：非課税）各×2
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
                    'category' => $service['category'],
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                ),
                array('%s', '%f', '%f', '%s', '%s', '%s', '%s')
            );
            
            if ($result) {
                $service_ids[] = $wpdb->insert_id;
                WP_CLI::log("✓ サービス作成: {$service['service_name']} (税率: " . ($service['tax_rate'] ?? '非課税') . "%)");
            }
        }

        WP_CLI::success('ダミーデータ作成が完了しました！');
        WP_CLI::log('作成されたデータ:');
        WP_CLI::log("- 協力会社: " . count($supplier_ids) . "件");
        WP_CLI::log("- 職能: " . (count($supplier_ids) * 3) . "件");
        WP_CLI::log("- サービス: " . count($service_ids) . "件");
        WP_CLI::log('');
        WP_CLI::log('詳細:');
        WP_CLI::log("- 協力会社: 各社のメールアドレスは全て info@kantanpro.com");
        WP_CLI::log("- 職能: 各協力会社に税率10%、税率8%、非課税の3パターン");
        WP_CLI::log("- サービス: 一般（税率10%）×2、食品（税率8%）×2、不動産（非課税）×2");
    }
}

WP_CLI::add_command('ktp create-dummy-data', 'KTP_Create_Dummy_Data_Command');
?> 