<?php
/**
 * WP-CLIコマンド: ダミーデータ作成
 * 
 * 使用方法: wp ktp create-dummy-data
 * 
 * 以下のデータを作成します：
 * - 顧客×6件（カテゴリー別）
 * - 協力会社×6件（カテゴリー別）
 * - サービス×6件（カテゴリー別・税率自動設定）
 * - 職能×18件（協力会社×6件 × 税率3パターン）
 * 
 * カテゴリー別税率：
 * - 食品: 8%
 * - 不動産: 非課税
 * - その他: 10%
 */

if (!defined('WP_CLI') || !WP_CLI) {
    return;
}

/**
 * ダミーデータ作成コマンド
 */
class KTP_Create_Dummy_Data_Command {

    // カテゴリー定義
    private $categories = array(
        'Tech' => array(
            'tax_rate' => 10.00,
            'description' => 'IT and technology services'
        ),
        'Real Estate' => array(
            'tax_rate' => null, // Tax exempt
            'description' => 'Real estate and construction services'
        ),
        'General' => array(
            'tax_rate' => 10.00,
            'description' => 'General business services'
        ),
        'Logistics' => array(
            'tax_rate' => 10.00,
            'description' => 'Logistics and transportation services'
        ),
        'Food' => array(
            'tax_rate' => 8.00,
            'description' => 'Food and restaurant services'
        ),
        'Healthcare' => array(
            'tax_rate' => 10.00,
            'description' => 'Medical and healthcare services'
        ),
        'Education' => array(
            'tax_rate' => 10.00,
            'description' => 'Education and training services'
        ),
        'Finance' => array(
            'tax_rate' => 10.00,
            'description' => 'Finance and insurance services'
        )
    );

    // カテゴリー別データ定義
    private $category_data = array(
        'Tech' => array(
            'companies' => array('Tech Solutions Inc.', 'Digital Creators LLC', 'System Development Partners', 'Web Design Studio Inc.'),
            'services' => array('Website Development', 'System Development', 'Mobile App Development', 'Cloud Infrastructure Setup', 'Database Design', 'API Development'),
            'skills' => array('Programming', 'System Design', 'Database Administration', 'Cloud Infrastructure', 'Security Consulting', 'AI and Machine Learning')
        ),
        'Real Estate' => array(
            'companies' => array('Real Estate Consulting Inc.', 'Construction Works LLC', 'Architectural Design Partners', 'Property Management Inc.'),
            'services' => array('Real Estate Brokerage', 'Property Management', 'Architectural Design', 'Construction Work', 'Real Estate Investment Consulting', 'Property Appraisal'),
            'skills' => array('Architectural Design', 'Real Estate Appraisal', 'Construction Management', 'CAD Design', 'Real Estate Legal Support', 'Project Management')
        ),
        'General' => array(
            'companies' => array('Sample Trading Inc.', 'Business Consulting LLC', 'Design Workshop Partners', 'Marketing Pro Inc.'),
            'services' => array('Business Consulting', 'Marketing Strategy', 'Design Production', 'Translation Services', 'Event Planning', 'Research and Analysis'),
            'skills' => array('Business Consulting', 'Marketing', 'Design', 'Translation', 'Event Planning', 'Data Analysis')
        ),
        'Logistics' => array(
            'companies' => array('Logistics Inc.', 'Transport Services LLC', 'Warehouse Management Partners', 'Delivery Center Inc.'),
            'services' => array('Logistics Management', 'Delivery Services', 'Warehouse Management', 'Import and Export Procedures', 'Supply Chain Management', 'Delivery Route Optimization'),
            'skills' => array('Logistics Management', 'Delivery Planning', 'Warehouse Operations', 'Customs Procedures', 'Route Optimization', 'Inventory Management')
        ),
        'Food' => array(
            'companies' => array('Food Services Inc.', 'Catering LLC', 'Food Delivery Partners', 'Restaurant Operations Inc.'),
            'services' => array('Food', 'Catering Services', 'Food Delivery', 'Restaurant Operations', 'Food Processing', 'Nutrition Management', 'Food Safety Management'),
            'skills' => array('Food', 'Food Quality Control', 'Nutrition Management', 'Food Safety', 'Ingredient Procurement', 'Menu Development', 'Sanitation Management')
        ),
        'Healthcare' => array(
            'companies' => array('Medical Services Inc.', 'Healthcare LLC', 'Medical Consulting Partners', 'Pharmacy Operations Inc.'),
            'services' => array('Medical Consulting', 'Health Checkups', 'Pharmacy Operations', 'Medical Equipment Management', 'Nursing Services', 'Medical Administration'),
            'skills' => array('Medical Consulting', 'Nursing', 'Pharmacist Services', 'Medical Administration', 'Health Management', 'Medical Equipment Operation')
        ),
        'Education' => array(
            'companies' => array('Education Services Inc.', 'Training Center LLC', 'Online Education Partners', 'School Operations Inc.'),
            'services' => array('Training Services', 'Online Education', 'School Operations', 'Teaching Material Development', 'Certification Support', 'Education Consulting'),
            'skills' => array('Instructor Services', 'Teaching Material Development', 'Education Consulting', 'Online Education', 'Certification Training', 'Curriculum Design')
        ),
        'Finance' => array(
            'companies' => array('Financial Services Inc.', 'Insurance Agency LLC', 'Investment Consulting Partners', 'Accounting Office Inc.'),
            'services' => array('Investment Consulting', 'Insurance Consulting', 'Accounting Services', 'Tax Consulting', 'Asset Management', 'Risk Management'),
            'skills' => array('Investment Consulting', 'Insurance Planning', 'Accounting', 'Tax Services', 'Asset Management', 'Risk Management')
        )
    );

    /**
     * カテゴリーに基づく税率取得
     */
    private function get_tax_rate_by_category($category) {
        return isset($this->categories[$category]) ? $this->categories[$category]['tax_rate'] : 10.00;
    }

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
        WP_CLI::log('バージョン: 2.4.0 (品名ベース税率設定版)');

        // 既存データのチェック
        if (!isset($assoc_args['force'])) {
            $existing_clients = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ktp_client");
            $existing_suppliers = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ktp_supplier");
            $existing_services = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ktp_service");
            
            if ($existing_clients > 0 || $existing_suppliers > 0 || $existing_services > 0) {
                WP_CLI::warning('既存のデータが存在します。--forceオプションを使用して強制的に作成してください。');
                return;
            }
        }

        // 1. 顧客データの作成（カテゴリー別）
        $clients = array();
        $client_categories = array('Tech', 'Real Estate', 'General', 'Logistics', 'Food', 'Healthcare');

        foreach ($client_categories as $category) {
            $companies = $this->category_data[$category]['companies'];
            $company_name = $companies[array_rand($companies)];
            $names = array('John Smith', 'Emily Johnson', 'Michael Brown', 'Sarah Davis', 'David Wilson', 'Jessica Taylor');
            $name = $names[array_rand($names)];
            
            $clients[] = array(
                'company_name' => $company_name,
                'name' => $name,
                'email' => 'info@kantanpro.com',
                'memo' => $this->categories[$category]['description'],
                'category' => $category
            );
        }

        $client_ids = array();
        foreach ($clients as $client) {
            $result = $wpdb->insert(
                $wpdb->prefix . 'ktp_client',
                array(
                    'company_name' => $client['company_name'],
                    'name' => $client['name'],
                    'email' => $client['email'],
                    'memo' => $client['memo'],
                    'category' => $client['category'],
                    'time' => time()
                ),
                array('%s', '%s', '%s', '%s', '%s', '%d')
            );
            
            if ($result) {
                $client_ids[] = $wpdb->insert_id;
                $tax_rate = $this->get_tax_rate_by_category($client['category']);
                $tax_info = $tax_rate ? "税率{$tax_rate}%" : "非課税";
                WP_CLI::log("✓ 顧客作成: {$client['company_name']} (カテゴリー: {$client['category']}, {$tax_info})");
            }
        }

        // 2. 協力会社データの作成（カテゴリー別）
        $suppliers = array();
        $supplier_categories = array('Tech', 'Real Estate', 'General', 'Logistics', 'Food', 'Education');

        foreach ($supplier_categories as $category) {
            $companies = $this->category_data[$category]['companies'];
            $company_name = $companies[array_rand($companies)];
            $names = array('Robert Anderson', 'Laura Martinez', 'William Thompson', 'Karen White', 'James Harris', 'Linda Clark');
            $name = $names[array_rand($names)];
            
            $suppliers[] = array(
                'company_name' => $company_name,
                'name' => $name,
                'email' => 'info@kantanpro.com',
                'memo' => $this->categories[$category]['description'],
                'category' => $category
            );
        }

        $supplier_ids = array();
        foreach ($suppliers as $supplier) {
            // ダミーデータ用の適格請求書番号を生成
            $qualified_invoice_number = 'T' . str_pad(rand(1, 999999), 6, '0', STR_PAD_LEFT);
            
            $result = $wpdb->insert(
                $wpdb->prefix . 'ktp_supplier',
                array(
                    'company_name' => $supplier['company_name'],
                    'name' => $supplier['name'],
                    'email' => $supplier['email'],
                    'memo' => $supplier['memo'],
                    'category' => $supplier['category'],
                    'qualified_invoice_number' => $qualified_invoice_number,
                    'time' => time()
                ),
                array('%s', '%s', '%s', '%s', '%s', '%s', '%d')
            );
            
            if ($result) {
                $supplier_ids[] = $wpdb->insert_id;
                $tax_rate = $this->get_tax_rate_by_category($supplier['category']);
                $tax_info = $tax_rate ? "税率{$tax_rate}%" : "非課税";
                WP_CLI::log("✓ 協力会社作成: {$supplier['company_name']} (カテゴリー: {$supplier['category']}, {$tax_info}, 適格請求書番号: {$qualified_invoice_number})");
            }
        }

        // 3. サービスデータの作成（カテゴリー別・税率自動設定）
        $services = array();
        $service_categories = array('Tech', 'Real Estate', 'General', 'Logistics', 'Food', 'Finance');

        foreach ($service_categories as $category) {
            $service_names = $this->category_data[$category]['services'];
            // 各カテゴリーから2つのサービスを選択
            $selected_services = array_rand($service_names, 2);
            if (!is_array($selected_services)) {
                $selected_services = array($selected_services);
            }
            
            foreach ($selected_services as $index) {
                $service_name = $service_names[$index];
                
                // 品名に基づいて税率を決定
                if ($service_name === 'Food') {
                    $tax_rate = 8.00; // サービス名「食品」のみ税率8%
                } else {
                    $tax_rate = 10.00; // その他は一般税率10%
                }
                
                $price = rand(50000, 800000);
                $units = array('project', 'month', 'hour', 'item', 'session');
                $unit = $units[array_rand($units)];
                
                $services[] = array(
                    'service_name' => $service_name,
                    'price' => $price,
                    'tax_rate' => $tax_rate,
                    'unit' => $unit,
                    'category' => $category
                );
            }
        }

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
                    'time' => time()
                ),
                array('%s', '%f', '%f', '%s', '%s', '%d')
            );
            
            if ($result) {
                $service_ids[] = $wpdb->insert_id;
                $tax_info = $service['tax_rate'] ? "税率{$service['tax_rate']}%" : "非課税";
                WP_CLI::log("✓ サービス作成: {$service['service_name']} (カテゴリー: {$service['category']}, {$tax_info})");
            }
        }

        // 4. 職能データの作成（カテゴリー別・税率自動設定）
        WP_CLI::log("職能作成を開始します...");
        WP_CLI::log("協力会社数: " . count($supplier_ids));
        
        foreach ($supplier_ids as $supplier_id) {
            WP_CLI::log("協力会社ID {$supplier_id} の職能を作成中...");
            
            // 協力会社のカテゴリーを取得
            $supplier_info = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT category FROM {$wpdb->prefix}ktp_supplier WHERE id = %d",
                    $supplier_id
                )
            );
            
            if (!$supplier_info) {
                WP_CLI::warning("協力会社ID {$supplier_id} の情報が見つかりません");
                continue;
            }
            
            $supplier_category = $supplier_info->category;
            WP_CLI::log("協力会社のカテゴリー: {$supplier_category}");
            
            if (!isset($this->category_data[$supplier_category])) {
                WP_CLI::warning("カテゴリー '{$supplier_category}' のデータが定義されていません");
                $supplier_category = 'General';
            }
            
            $skill_names = $this->category_data[$supplier_category]['skills'];
            WP_CLI::log("職能名リスト: " . implode(', ', $skill_names));
            
            // 各協力会社に3つの職能を作成（品名に基づく税率設定）
            $tax_patterns = array();
            
            // 品名に基づいて税率を決定
            $food_skill_names = array('Food', 'Food Quality Control', 'Nutrition Management', 'Food Safety', 'Ingredient Procurement', 'Menu Development', 'Sanitation Management');
            $has_food_skill = false;
            
            // 食品関連の職能があるかチェック
            foreach ($skill_names as $skill_name) {
                if (in_array($skill_name, $food_skill_names)) {
                    $has_food_skill = true;
                    break;
                }
            }
            
            if ($has_food_skill) {
                // 食品関連の職能がある場合は、必ず税率8%を含める
                $tax_patterns = array(
                    8.00, // 食品税率（必ず含める）
                    10.00, // 一般税率
                    10.00  // 一般税率
                );
            } else {
                // 食品関連の職能がない場合は、基本的に一般税率
                $tax_patterns = array(
                    10.00, // 一般税率
                    10.00, // 一般税率
                    null   // 非課税（一部）
                );
            }
            
            WP_CLI::log("税率パターン: " . implode(', ', array_map(function($rate) { return $rate ? $rate . '%' : '非課税'; }, $tax_patterns)));
            
            foreach ($tax_patterns as $tax_rate) {
                $skill_name = $skill_names[array_rand($skill_names)];
                $unit_price = rand(5000, 50000);
                $quantity = rand(1, 10);
                $unit = 'hour';
                
                $skill_data = array(
                    'supplier_id' => $supplier_id,
                    'product_name' => $skill_name,
                    'unit_price' => $unit_price,
                    'quantity' => $quantity,
                    'unit' => $unit,
                    'tax_rate' => $tax_rate,
                    'frequency' => rand(1, 100)
                );
                
                WP_CLI::log("職能データ: " . json_encode($skill_data, JSON_UNESCAPED_UNICODE));
                
                $result = $wpdb->insert(
                    $wpdb->prefix . 'ktp_supplier_skills',
                    $skill_data,
                    array('%d', '%s', '%f', '%d', '%s', '%f', '%d')
                );
                
                if ($result) {
                    $tax_info = $tax_rate ? "税率{$tax_rate}%" : "非課税";
                    WP_CLI::log("✓ 職能作成成功: {$skill_name} (カテゴリー: {$supplier_category}, {$tax_info})");
                } else {
                    WP_CLI::warning("✗ 職能作成失敗: {$skill_name} - " . $wpdb->last_error);
                }
            }
        }

        WP_CLI::success('ダミーデータ作成が完了しました！');
        WP_CLI::log("作成されたデータ:");
        WP_CLI::log("- 顧客: " . count($client_ids) . "件");
        WP_CLI::log("- 協力会社: " . count($supplier_ids) . "件");
        WP_CLI::log("- サービス: " . count($service_ids) . "件");
        WP_CLI::log("- 職能: " . (count($supplier_ids) * 3) . "件");
        WP_CLI::log("");
        WP_CLI::log("カテゴリー別税率:");
        WP_CLI::log("- 食品: 8%");
        WP_CLI::log("- 不動産: 非課税");
        WP_CLI::log("- その他: 10%");
    }
}

WP_CLI::add_command('ktp create-dummy-data', 'KTP_Create_Dummy_Data_Command'); 