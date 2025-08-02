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
        'テック' => array(
            'tax_rate' => 10.00,
            'description' => 'IT・テクノロジー関連'
        ),
        '不動産' => array(
            'tax_rate' => null, // 非課税
            'description' => '不動産・建設関連'
        ),
        '一般' => array(
            'tax_rate' => 10.00,
            'description' => '一般的なサービス'
        ),
        'ロジスティック' => array(
            'tax_rate' => 10.00,
            'description' => '物流・輸送関連'
        ),
        '食品' => array(
            'tax_rate' => 8.00,
            'description' => '食品・飲食関連'
        ),
        '医療' => array(
            'tax_rate' => 10.00,
            'description' => '医療・ヘルスケア関連'
        ),
        '教育' => array(
            'tax_rate' => 10.00,
            'description' => '教育・研修関連'
        ),
        '金融' => array(
            'tax_rate' => 10.00,
            'description' => '金融・保険関連'
        )
    );

    // カテゴリー別データ定義
    private $category_data = array(
        'テック' => array(
            'companies' => array('株式会社テックソリューション', '有限会社デジタルクリエイター', '合同会社システム開発', '株式会社ウェブデザイン'),
            'services' => array('ウェブサイト制作', 'システム開発', 'モバイルアプリ開発', 'クラウド構築', 'データベース設計', 'API開発'),
            'skills' => array('プログラミング', 'システム設計', 'データベース管理', 'クラウドインフラ', 'セキュリティ対策', 'AI・機械学習')
        ),
        '不動産' => array(
            'companies' => array('株式会社不動産コンサルティング', '有限会社建設工業', '合同会社建築設計', '株式会社プロパティマネジメント'),
            'services' => array('不動産仲介', '物件管理', '建築設計', '建設工事', '不動産投資相談', '物件査定'),
            'skills' => array('建築設計', '不動産鑑定', '施工管理', 'CAD設計', '不動産法務', 'プロジェクトマネジメント')
        ),
        '一般' => array(
            'companies' => array('株式会社サンプル商事', '有限会社コンサルティング', '合同会社デザイン工房', '株式会社マーケティングプロ'),
            'services' => array('経営コンサルティング', 'マーケティング戦略', 'デザイン制作', '翻訳サービス', 'イベント企画', '調査・分析'),
            'skills' => array('経営コンサル', 'マーケティング', 'デザイン', '翻訳', 'イベント企画', 'データ分析')
        ),
        'ロジスティック' => array(
            'companies' => array('株式会社ロジスティクス', '有限会社輸送サービス', '合同会社倉庫管理', '株式会社配送センター'),
            'services' => array('物流管理', '配送サービス', '倉庫管理', '輸出入手続き', 'サプライチェーン管理', '配送ルート最適化'),
            'skills' => array('物流管理', '配送計画', '倉庫運営', '通関手続き', 'ルート最適化', '在庫管理')
        ),
        '食品' => array(
            'companies' => array('株式会社フードサービス', '有限会社ケータリング', '合同会社食材配送', '株式会社レストラン運営'),
            'services' => array('ケータリングサービス', '食材配送', 'レストラン運営', '食品加工', '栄養管理', '食品安全管理'),
            'skills' => array('食品品質管理', '栄養管理', '食品安全', '食材調達', 'メニュー開発', '衛生管理')
        ),
        '医療' => array(
            'companies' => array('株式会社メディカルサービス', '有限会社ヘルスケア', '合同会社医療コンサル', '株式会社薬局運営'),
            'services' => array('医療コンサルティング', '健康診断', '薬局運営', '医療機器管理', '看護サービス', '医療事務'),
            'skills' => array('医療コンサル', '看護', '薬剤師', '医療事務', '健康管理', '医療機器操作')
        ),
        '教育' => array(
            'companies' => array('株式会社教育サービス', '有限会社研修センター', '合同会社オンライン教育', '株式会社スクール運営'),
            'services' => array('研修サービス', 'オンライン教育', 'スクール運営', '教材開発', '資格取得支援', '教育コンサル'),
            'skills' => array('講師', '教材開発', '教育コンサル', 'オンライン教育', '資格指導', 'カリキュラム設計')
        ),
        '金融' => array(
            'companies' => array('株式会社フィナンシャルサービス', '有限会社保険代理店', '合同会社投資コンサル', '株式会社会計事務所'),
            'services' => array('投資コンサルティング', '保険相談', '会計サービス', '税務相談', '資産運用', 'リスク管理'),
            'skills' => array('投資コンサル', '保険設計', '会計', '税務', '資産運用', 'リスク管理')
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
        $client_categories = array('テック', '不動産', '一般', 'ロジスティック', '食品', '医療');

        foreach ($client_categories as $category) {
            $companies = $this->category_data[$category]['companies'];
            $company_name = $companies[array_rand($companies)];
            $names = array('田中太郎', '佐藤花子', '鈴木一郎', '高橋美咲', '渡辺健太', '伊藤恵子');
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
        $supplier_categories = array('テック', '不動産', '一般', 'ロジスティック', '食品', '教育');

        foreach ($supplier_categories as $category) {
            $companies = $this->category_data[$category]['companies'];
            $company_name = $companies[array_rand($companies)];
            $names = array('山田次郎', '中村由美', '小林正男', '加藤真理', '松本和也', '井上智子');
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
            $result = $wpdb->insert(
                $wpdb->prefix . 'ktp_supplier',
                array(
                    'company_name' => $supplier['company_name'],
                    'name' => $supplier['name'],
                    'email' => $supplier['email'],
                    'memo' => $supplier['memo'],
                    'category' => $supplier['category'],
                    'time' => time()
                ),
                array('%s', '%s', '%s', '%s', '%s', '%d')
            );
            
            if ($result) {
                $supplier_ids[] = $wpdb->insert_id;
                $tax_rate = $this->get_tax_rate_by_category($supplier['category']);
                $tax_info = $tax_rate ? "税率{$tax_rate}%" : "非課税";
                WP_CLI::log("✓ 協力会社作成: {$supplier['company_name']} (カテゴリー: {$supplier['category']}, {$tax_info})");
            }
        }

        // 3. サービスデータの作成（カテゴリー別・税率自動設定）
        $services = array();
        $service_categories = array('テック', '不動産', '一般', 'ロジスティック', '食品', '金融');

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
                if ($service_name === '食品') {
                    $tax_rate = 8.00; // サービス名「食品」のみ税率8%
                } else {
                    $tax_rate = 10.00; // その他は一般税率10%
                }
                
                $price = rand(50000, 800000);
                $units = array('式', '月', '時間', '件', '回');
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
                $supplier_category = '一般';
            }
            
            $skill_names = $this->category_data[$supplier_category]['skills'];
            WP_CLI::log("職能名リスト: " . implode(', ', $skill_names));
            
            // 各協力会社に3つの職能を作成（品名に基づく税率設定）
            $tax_patterns = array();
            
            // 品名に基づいて税率を決定
            $food_skill_names = array('食品品質管理', '栄養管理', '食品安全', '食材調達', 'メニュー開発', '衛生管理');
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
                $unit = '時間';
                
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
?> 
?> 