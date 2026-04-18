<?php
/**
 * 強化版ダミーデータ作成スクリプト
 * バージョン: 2.5.3
 * 
 * 以下のデータを作成します：
 * - 顧客×6件（カテゴリー別）
 * - 協力会社×6件
 * - サービス×6件（カテゴリー別・税率自動設定）
 * - 受注書×ランダム件数（顧客ごとに2-8件、進捗は重み付きランダム分布）
 * - 職能×18件（協力会社×6件 × 税率3パターン：税率10%・税率8%・非課税）
 * - 請求項目とコスト項目を各受注書に追加
 * 
 * 対象外データ：
 * - スタッフチャット（ktp_order_staff_chat）は削除対象に含めるが、新規作成時は初期メッセージを作成しない
 * 
 * 修正内容（v2.5.3）:
 * - スタッフチャットテーブル（ktp_order_staff_chat）を削除対象に含める
 * - ダミーデータ作成時はスタッフチャットの初期メッセージを作成しない
 * 
 * 修正内容（v2.5.2）:
 * - コスト項目の税率がnullの場合のデフォルト値設定（10%）
 * - 利益計算時の税率空欄対応
 * 
 * 修正内容（v2.5.1）:
 * - コスト項目作成時のカラム存在チェック対応
 * - 適格請求書番号カラムが存在しない場合でも正常動作
 * 
 * 修正内容（v2.5.0）:
 * - ダミーデータ仕入れ先の適格請求書対応
 * - 協力会社作成時に適格請求書番号を自動生成
 * - コスト項目作成時に適格請求書番号を設定（カラム存在チェック対応）
 * - 利益計算でダミーデータ仕入れ先を内税でインボイスありとして計算
 * 
 * 修正内容（v2.4.0）:
 * - 品名に基づく税率設定に変更（食品関連品名は税率8%、その他は10%）
 * - 食品関連品名の場合は必ず税率8%を1つ含めるように修正
 * - より現実的な税率設定
 * 
 * 修正内容（v2.3.1）:
 * - 食品カテゴリーの協力会社に必ず税率8%の職能を1つ含めるように修正
 * - 税率パターンの最適化
 * 
 * 修正内容（v2.3.0）:
 * - カテゴリー機能を追加
 * - 税率の自動設定（食品8%、不動産非課税、その他10%）
 * - 顧客・サービス・職能にカテゴリーを適用
 * 
 * 進捗分布：
 * - 受付中: 15%
 * - 見積中: 20%
 * - 受注: 25%
 * - 進行中: 20%
 * - 完成: 15%
 * - 請求済: 5%
 * 
 * 日付設定：
 * - 受注・進行中: 将来の納期を設定
 * - 完成・請求済: 過去の納期と適切な完了日を設定
 */

// エラーハンドリングを強化
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// WordPress環境の読み込み
$wp_config_path = dirname(__FILE__) . '/../../../wp-config.php';
if (file_exists($wp_config_path)) {
    require_once($wp_config_path);
} else {
    // Dockerコンテナ内でのパス
    require_once('/var/www/html/wp-config.php');
}

// セキュリティチェック
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__FILE__) . '/../../../');
}

// ダミーデータ作成フラグを設定（スタッフチャット初期メッセージ作成を防ぐため）
define('KTPWP_DUMMY_DATA_CREATION', true);

global $wpdb;

// データベース接続チェック
if (!$wpdb->check_connection()) {
    error_log('KTPWP: データベース接続エラー');
    return false;
}

// テーブル存在チェック
$required_tables = array(
    'ktp_client',
    'ktp_supplier', 
    'ktp_service',
    'ktp_supplier_skills',
    'ktp_order',
    'ktp_order_invoice_items',
    'ktp_order_cost_items'
);

foreach ($required_tables as $table) {
    $table_name = $wpdb->prefix . $table;
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'");
    if (!$table_exists) {
        error_log("KTPWP: 必要なテーブルが存在しません: {$table_name}");
        return false;
    }
}

// カテゴリー定義
$categories = array(
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
$category_data = array(
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
        'services' => array('食品', 'ケータリングサービス', '食材配送', 'レストラン運営', '食品加工', '栄養管理', '食品安全管理'),
        'skills' => array('食品', '食品品質管理', '栄養管理', '食品安全', '食材調達', 'メニュー開発', '衛生管理')
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

// 安全なデータベース操作関数
function safe_db_insert($table, $data, $format = null) {
    global $wpdb;
    
    try {
        $result = $wpdb->insert($table, $data, $format);
        if ($result === false) {
            error_log("KTPWP: データベース挿入エラー - テーブル: {$table}, エラー: " . $wpdb->last_error);
            return false;
        }
        return $wpdb->insert_id;
    } catch (Exception $e) {
        error_log("KTPWP: データベース挿入例外 - テーブル: {$table}, エラー: " . $e->getMessage());
        return false;
    }
}

// 重み付きランダム選択関数
function weighted_random_choice($weights) {
    $total_weight = array_sum($weights);
    $random = mt_rand(1, $total_weight);
    $current_weight = 0;
    
    foreach ($weights as $key => $weight) {
        $current_weight += $weight;
        if ($random <= $current_weight) {
            return $key;
        }
    }
    
    // フォールバック
    return array_keys($weights)[0];
}

// カテゴリーに基づく税率取得関数
function get_tax_rate_by_category($category) {
    global $categories;
    return isset($categories[$category]) ? $categories[$category]['tax_rate'] : 10.00;
}

// 安全な出力関数
function safe_echo($message) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        echo $message . "\n";
    }
}

safe_echo("強化版ダミーデータ作成を開始します...");
safe_echo("バージョン: 2.4.0 (品名ベース税率設定版)");
safe_echo("==========================================");

// 警告メッセージの表示
safe_echo("⚠️  警告: ダミーデータ作成について");
safe_echo("==========================================");
safe_echo("• 既存のダミーデータは完全に削除されます");
safe_echo("• 本番環境での実行は絶対に避けてください");
safe_echo("• 実行前にデータベースのバックアップを推奨します");
safe_echo("• この操作は取り消しできません");
safe_echo("==========================================");

// 既存のダミーデータをクリアしてIDをリセット
safe_echo("既存のダミーデータをクリアしてIDをリセットします...");
clear_dummy_data();
safe_echo("==========================================");

// 1. 顧客データの作成（カテゴリー別）
$clients = array();
$client_categories = array('テック', '不動産', '一般', 'ロジスティック', '食品', '医療');

foreach ($client_categories as $category) {
    $companies = $category_data[$category]['companies'];
    $company_name = $companies[array_rand($companies)];
    $names = array('田中太郎', '佐藤花子', '鈴木一郎', '高橋美咲', '渡辺健太', '伊藤恵子');
    $name = $names[array_rand($names)];
    
    $clients[] = array(
        'company_name' => $company_name,
        'name' => $name,
        'email' => 'info@kantanpro.com',
        'memo' => $categories[$category]['description'],
        'category' => $category
    );
}

$client_ids = array();
foreach ($clients as $client) {
    $insert_id = safe_db_insert(
        $wpdb->prefix . 'ktp_client',
        array(
            'company_name' => $client['company_name'],
            'name' => $client['name'],
            'email' => $client['email'],
            'memo' => $client['memo'],
            'category' => $client['category'],
            'time' => time()
        ),
        array("%s", "%s", "%s", "%s", "%s", "%d")
    );
    
    if ($insert_id) {
        $client_ids[] = $insert_id;
        $tax_rate = get_tax_rate_by_category($client['category']);
        $tax_info = $tax_rate ? "税率{$tax_rate}%" : "非課税";
        safe_echo("顧客作成: {$client['company_name']} (カテゴリー: {$client['category']}, {$tax_info})");
    }
}

// 2. 協力会社データの作成（カテゴリー別）
$suppliers = array();
$supplier_categories = array('テック', '不動産', '一般', 'ロジスティック', '食品', '教育');

foreach ($supplier_categories as $category) {
    $companies = $category_data[$category]['companies'];
    $company_name = $companies[array_rand($companies)];
    $names = array('山田次郎', '中村由美', '小林正男', '加藤真理', '松本和也', '井上智子');
    $name = $names[array_rand($names)];
    
    $suppliers[] = array(
        'company_name' => $company_name,
        'name' => $name,
        'email' => 'info@kantanpro.com',
        'memo' => $categories[$category]['description'],
        'category' => $category
    );
}

$supplier_ids = array();
foreach ($suppliers as $supplier) {
    // ダミーデータ用の適格請求書番号を生成
    $qualified_invoice_number = 'T' . str_pad(rand(1, 999999), 6, '0', STR_PAD_LEFT);
    
    $insert_id = safe_db_insert(
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
        array("%s", "%s", "%s", "%s", "%s", "%s", "%d")
    );
    
    if ($insert_id) {
        $supplier_ids[] = $insert_id;
        $tax_rate = get_tax_rate_by_category($supplier['category']);
        $tax_info = $tax_rate ? "税率{$tax_rate}%" : "非課税";
        safe_echo("協力会社作成: {$supplier['company_name']} (カテゴリー: {$supplier['category']}, {$tax_info}, 適格請求書番号: {$qualified_invoice_number})");
    }
}

// 3. サービスデータの作成（カテゴリー別・税率自動設定）
$services = array();
$service_categories = array('テック', '不動産', '一般', 'ロジスティック', '食品', '金融');

foreach ($service_categories as $category) {
    $service_names = $category_data[$category]['services'];
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
    $insert_id = safe_db_insert(
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
    
    if ($insert_id) {
        $service_ids[] = $insert_id;
        $tax_info = $service['tax_rate'] ? "税率{$service['tax_rate']}%" : "非課税";
        safe_echo("サービス作成: {$service['service_name']} (カテゴリー: {$service['category']}, {$tax_info})");
    }
}

// 4. 職能データの作成（カテゴリー別・税率自動設定）
$skill_categories = array('テック', '不動産', '一般', 'ロジスティック', '食品', '医療');

safe_echo("職能作成を開始します...");
safe_echo("協力会社数: " . count($supplier_ids));

foreach ($supplier_ids as $supplier_id) {
    safe_echo("協力会社ID {$supplier_id} の職能を作成中...");
    
    // 協力会社のカテゴリーを取得
    $supplier_info = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT category FROM {$wpdb->prefix}ktp_supplier WHERE id = %d",
            $supplier_id
        )
    );
    
    if (!$supplier_info) {
        safe_echo("ERROR: 協力会社ID {$supplier_id} の情報が見つかりません");
        continue;
    }
    
    $supplier_category = $supplier_info->category;
    safe_echo("協力会社のカテゴリー: {$supplier_category}");
    
    if (!isset($category_data[$supplier_category])) {
        safe_echo("ERROR: カテゴリー '{$supplier_category}' のデータが定義されていません");
        $supplier_category = '一般';
    }
    
    $skill_names = $category_data[$supplier_category]['skills'];
    safe_echo("職能名リスト: " . implode(', ', $skill_names));
    
    // 各協力会社に3つの職能を作成（品名に基づく税率設定）
    $tax_patterns = array();
    
    // 品名に基づいて税率を決定
    $has_food_skill = false;
    
    // 職能名「食品」があるかチェック
    foreach ($skill_names as $skill_name) {
        if ($skill_name === '食品') {
            $has_food_skill = true;
            break;
        }
    }
    
    if ($has_food_skill) {
        // 職能名「食品」がある場合は、必ず税率8%を含める
        $tax_patterns = array(
            8.00, // 食品税率（必ず含める）
            10.00, // 一般税率
            10.00  // 一般税率
        );
        
        // 職能名「食品」を必ず1つ含めるように修正
        $skill_names_for_tax_8 = array('食品'); // 税率8%用の職能名リスト
        $skill_names_for_tax_10 = array_diff($skill_names, array('食品')); // 税率10%用の職能名リスト（食品以外）
        
        safe_echo("税率8%用職能名: " . implode(', ', $skill_names_for_tax_8));
        safe_echo("税率10%用職能名: " . implode(', ', $skill_names_for_tax_10));
    } else {
        // 職能名「食品」がない場合は、基本的に一般税率
        $tax_patterns = array(
            10.00, // 一般税率
            10.00, // 一般税率
            null   // 非課税（一部）
        );
        
        $skill_names_for_tax_8 = array();
        $skill_names_for_tax_10 = $skill_names;
    }
    
    safe_echo("税率パターン: " . implode(', ', array_map(function($rate) { return $rate ? $rate . '%' : '非課税'; }, $tax_patterns)));
    
    foreach ($tax_patterns as $index => $tax_rate) {
        // 税率に応じて職能名を選択
        if ($tax_rate == 8.00 && !empty($skill_names_for_tax_8)) {
            // 税率8%の場合は「食品」を必ず選択
            $skill_name = $skill_names_for_tax_8[array_rand($skill_names_for_tax_8)];
            safe_echo("税率8%用職能名から選択: {$skill_name}");
        } else {
            // その他の税率の場合は食品以外から選択
            $skill_name = $skill_names_for_tax_10[array_rand($skill_names_for_tax_10)];
            safe_echo("税率{$tax_rate}%用職能名から選択: {$skill_name}");
        }
        
        $unit_price = rand(5000, 50000);
        $quantity = rand(1, 10);
        $unit = '時間';
        
        // 税率がnullの場合は10%をデフォルトとして設定
        $default_tax_rate = $tax_rate !== null ? $tax_rate : 10.00;
        
        $skill_data = array(
            'supplier_id' => $supplier_id,
            'product_name' => $skill_name,
            'unit_price' => $unit_price,
            'quantity' => $quantity,
            'unit' => $unit,
            'tax_rate' => $default_tax_rate,
            'frequency' => rand(1, 100)
        );
        
        safe_echo("職能データ: " . json_encode($skill_data, JSON_UNESCAPED_UNICODE));
        
        $insert_id = safe_db_insert(
            $wpdb->prefix . 'ktp_supplier_skills',
            $skill_data,
            array("%d", "%s", "%f", "%d", "%s", "%f", "%d")
        );
        
        if ($insert_id) {
            $tax_info = $tax_rate ? "税率{$tax_rate}%" : "非課税";
            safe_echo("✓ 職能作成成功: {$skill_name} (カテゴリー: {$supplier_category}, {$tax_info})");
        } else {
            safe_echo("✗ 職能作成失敗: {$skill_name} - " . $wpdb->last_error);
        }
    }
}

// 5. 受注書データの作成（ランダムな進捗分布）
$order_statuses = array(1, 2, 3, 4, 5, 6); // 受付中、見積中、受注、進行中、完成、請求済
$order_names = array('Webサイトリニューアル', 'ECサイト構築', '業務システム開発', 'マーケティング戦略策定', 'ロゴデザイン制作', 'データ分析サービス', 'モバイルアプリ開発', 'SEO対策サービス', 'SNS運用代行', '動画制作');

$order_ids = array();
foreach ($client_ids as $client_id) {
    // 顧客ごとにランダムな数の注文を作成（2-8件）
    $order_count = rand(2, 8);
    for ($i = 0; $i < $order_count; $i++) {
        // 進捗をランダムに選択（重み付きランダム）
        $status_weights = array(
            1 => 15, // 受付中: 15%
            2 => 20, // 見積中: 20%
            3 => 25, // 受注: 25%
            4 => 20, // 進行中: 20%
            5 => 15, // 完成: 15%
            6 => 5   // 請求済: 5%
        );
        
        $status = weighted_random_choice($status_weights);
        $project_name = $order_names[array_rand($order_names)];
        
        // 進捗に応じて日付を設定
        switch ($status) {
            case 1: // 受付中 - 最近（1-30日前）
                $days_ago = rand(1, 30);
                $delivery_days_from_now = rand(30, 120); // 将来の納期
                break;
            case 2: // 見積中 - 最近（1-60日前）
                $days_ago = rand(1, 60);
                $delivery_days_from_now = rand(30, 150); // 将来の納期
                break;
            case 3: // 受注 - 中程度（30-120日前）
                $days_ago = rand(30, 120);
                $delivery_days_from_now = rand(30, 180); // 将来の納期
                break;
            case 4: // 進行中 - 中程度（60-150日前）
                $days_ago = rand(60, 150);
                $delivery_days_from_now = rand(7, 90); // 近い将来の納期
                break;
            case 5: // 完成 - 過去（90-180日前）
                $days_ago = rand(90, 180);
                $delivery_days_from_now = rand(-60, 30); // 過去から近い将来の納期
                break;
            case 6: // 請求済 - 過去（120-200日前）
                $days_ago = rand(120, 200);
                $delivery_days_from_now = rand(-120, -30); // 過去の納期
                break;
            default:
                $days_ago = rand(1, 365);
                $delivery_days_from_now = rand(30, 180);
        }
        
        $order_date = date('Y-m-d', strtotime('-' . $days_ago . ' days'));
        $delivery_date = date('Y-m-d', strtotime($delivery_days_from_now . ' days'));
        
        // 完了済みの注文には完了日を設定
        $completion_date = null;
        if ($status == 5 || $status == 6) { // 完成または請求済
            // 注文日より後、納期より前または同時の完了日を設定
            $order_to_delivery_days = (strtotime($delivery_date) - strtotime($order_date)) / (24 * 60 * 60);
            if ($order_to_delivery_days > 0) {
                $completion_days_before_delivery = rand(0, min(30, $order_to_delivery_days)); // 納期の0-30日前に完了
                $completion_date = date('Y-m-d', strtotime($delivery_date . ' -' . $completion_days_before_delivery . ' days'));
            } else {
                // 納期が過去の場合は、注文日から適切な期間後に完了
                $completion_days_after_order = rand(30, 90);
                $completion_date = date('Y-m-d', strtotime($order_date . ' +' . $completion_days_after_order . ' days'));
            }
        }
        
        // ステータスラベルの定義
        $status_labels = array(
            1 => '受付中',
            2 => '見積中',
            3 => '受注',
            4 => '進行中',
            5 => '完成',
            6 => '請求済'
        );
        
        // 作成日時を設定
        $created_time = $order_date . ' ' . sprintf('%02d:%02d:%02d', rand(9, 18), rand(0, 59), rand(0, 59));
        
        // 現在の日時を取得
        $current_datetime = current_time('mysql');
        
        // 受注番号を生成
        $order_number = 'ORD-' . date('Ymd', strtotime($order_date)) . '-' . sprintf('%03d', rand(1, 999));
        
        // order_dateを基に適切なタイムスタンプを生成
        $hour = rand(9, 18);
        $minute = rand(0, 59);
        $second = rand(0, 59);
        $datetime_string = $order_date . ' ' . sprintf('%02d:%02d:%02d', $hour, $minute, $second);
        $order_timestamp = strtotime($datetime_string);
        
        if ($order_timestamp === false) {
            $order_timestamp = time(); // フォールバック
        }
        
        // 顧客情報を取得（より確実な方法）
        $customer_name = '';
        $user_name = '';
        $company_name = '';
        $search_field = '';
        
        if ($client_id) {
            $client_table = $wpdb->prefix . 'ktp_client';
            $client_info = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT company_name, name FROM {$client_table} WHERE id = %d",
                    $client_id
                )
            );
            
            if ($client_info) {
                $customer_name = $client_info->company_name;
                $user_name = $client_info->name;
                $company_name = $client_info->company_name;
                // 画面表示用の形式: "会社名 (担当者名)"
                            $search_field = $client_info->company_name . ', ' . $client_info->name;
            safe_echo("DEBUG: 顧客ID {$client_id} の情報を取得しました: {$customer_name}, {$user_name}");
            } else {
                // 顧客情報が見つからない場合のフォールバック
                safe_echo("WARNING: 顧客ID {$client_id} の情報が見つかりませんでした。");
                $display_name = '';
            }
        } else {
            safe_echo("WARNING: client_idが設定されていません。");
        }
        
        // サービスIDは使用しない（テーブル構造に存在しないため）
        
        // まず基本的なデータを挿入
        $sql = $wpdb->prepare(
            "INSERT INTO {$wpdb->prefix}ktp_order (
                order_number, client_id, project_name, order_date, 
                desired_delivery_date, expected_delivery_date, 
                status, updated_at, time, customer_name, user_name, search_field,
                progress, memo, completion_date
            ) VALUES (
                %s, %d, %s, %s, %s, %s, %s, %s, %d, %s, %s, %s, %d, %s, %s
            )",
            $order_number,
            $client_id,
            $project_name,
            $order_date,
            $delivery_date,
            $delivery_date,
            $status_labels[$status],
            $current_datetime,
            $order_timestamp,
            $customer_name, // 会社名のみを使用
            $user_name,
            $search_field,
            $status,
            'ダミーデータ',
            $completion_date
        );
        
        $result = $wpdb->query($sql);
        
        if ($result === false) {
            safe_echo("ERROR: 受注書作成に失敗しました: " . $wpdb->last_error);
        } else {
            // 挿入後にcreated_atフィールドを更新（強制的に値を設定）
            $update_sql = $wpdb->prepare(
                "UPDATE {$wpdb->prefix}ktp_order SET created_at = %s WHERE id = %d",
                $created_time,
                $wpdb->insert_id
            );
            
            $update_result = $wpdb->query($update_sql);
            if ($update_result === false) {
                safe_echo("WARNING: created_atフィールドの更新に失敗しました: " . $wpdb->last_error);
            }
        }
        
        if ($result) {
            $order_id = $wpdb->insert_id;
            $order_ids[] = $order_id;
            
            // 受注書に請求項目を追加
            add_invoice_items_to_order($order_id, $service_ids);
            
            // 受注書にコスト項目を追加
            add_cost_items_to_order($order_id, $supplier_ids);
            
            $completion_info = $completion_date ? ", 完了日: {$completion_date}" : "";
            $customer_info = $customer_name ? " (顧客: {$customer_name})" : " (顧客情報なし)";
            safe_echo("受注書作成: {$project_name}{$customer_info} (進捗: {$status_labels[$status]}, 作成日: {$created_time}{$completion_info})");
        }
    }
}

// 5. 受注書データの作成（ランダムな進捗分布）

safe_echo("==========================================");
safe_echo("強化版ダミーデータ作成が完了しました！");
safe_echo("バージョン: 2.4.0 (品名ベース税率設定版)");
safe_echo("作成されたデータ:");
safe_echo("- 顧客: " . count($client_ids) . "件");
safe_echo("- 協力会社: " . count($supplier_ids) . "件");
safe_echo("- サービス: " . count($service_ids) . "件");
safe_echo("- 受注書: " . count($order_ids) . "件");
safe_echo("- 職能: " . (count($supplier_ids) * 3) . "件");
safe_echo("");
safe_echo("詳細:");
safe_echo("- 顧客: 各社のメールアドレスは全て info@kantanpro.com");
safe_echo("- 協力会社: 各社のメールアドレスは全て info@kantanpro.com");
safe_echo("- 受注書: ランダムな進捗分布で作成（受付中15%、見積中20%、受注25%、進行中20%、完成15%、請求済5%）");
safe_echo("- 納期設定: 進捗に応じて適切な納期を設定（受注・進行中は将来、完成・請求済は過去）");
safe_echo("- 完了日設定: 完成・請求済の注文には適切な完了日を設定");
safe_echo("- カテゴリー別税率: 食品8%、不動産非課税、その他10%");
safe_echo("- サービス: カテゴリー別に自動生成（テック、不動産、一般、ロジスティック、食品、金融）");
safe_echo("- 職能: 協力会社のカテゴリーに応じて適切な職能を生成");
safe_echo("- 各受注書に請求項目とコスト項目を自動追加");
safe_echo("");
safe_echo("修正内容（v2.4.0）:");
safe_echo("- 品名に基づく税率設定に変更（食品関連品名は税率8%、その他は10%）");
safe_echo("- 食品関連品名の場合は必ず税率8%を1つ含めるように修正");
safe_echo("- より現実的な税率設定");
safe_echo("");
safe_echo("修正内容（v2.3.1）:");
safe_echo("- 食品カテゴリーの協力会社に必ず税率8%の職能を1つ含めるように修正");
safe_echo("- 税率パターンの最適化");
safe_echo("");
safe_echo("修正内容（v2.3.0）:");
safe_echo("- カテゴリー機能を追加");
safe_echo("- 税率の自動設定（食品8%、不動産非課税、その他10%）");
safe_echo("- 顧客・サービス・職能にカテゴリーを適用");
safe_echo("- 配布先サイトでの正常動作を確認");
safe_echo("");
safe_echo("注意: このデータはテスト用です。本番環境では使用しないでください。");

/**
 * 受注書に請求項目を追加
 */
function add_invoice_items_to_order($order_id, $service_ids) {
    global $wpdb;
    
    // order_invoice_itemsテーブルが存在するかチェック
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}ktp_order_invoice_items'");
    
    if ($table_exists) {
        // 1-3個のサービスをランダムに選択
        $num_items = rand(1, 3);
        $selected_services = array_rand(array_flip($service_ids), $num_items);
        
        // 単一の値の場合は配列に変換
        if (!is_array($selected_services)) {
            $selected_services = array($selected_services);
        }
        
        foreach ($selected_services as $service_id) {
            // サービス情報を取得
            $service = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ktp_service WHERE id = %d",
                $service_id
            ));
            
            if ($service) {
                $quantity = rand(1, 5);
                $unit_price = $service->price;
                $total_price = $quantity * $unit_price;
                
                $wpdb->insert(
                    $wpdb->prefix . 'ktp_order_invoice_items',
                    array(
                        'order_id' => $order_id,
                        'product_name' => $service->service_name,
                        'price' => $unit_price,
                        'unit' => $service->unit,
                        'quantity' => $quantity,
                        'amount' => $total_price,
                        'tax_rate' => $service->tax_rate,
                        'remarks' => 'ダミーデータ',
                        'sort_order' => 1,
                        'created_at' => current_time('mysql'),
                        'updated_at' => current_time('mysql')
                    ),
                    array('%d', '%s', '%f', '%s', '%f', '%d', '%f', '%s', '%d', '%s', '%s')
                );
            }
        }
    }
}

/**
 * 受注書にコスト項目を追加
 */
function add_cost_items_to_order($order_id, $supplier_ids) {
    global $wpdb;
    
    echo "DEBUG: コスト項目作成開始 - 受注書ID: {$order_id}, 協力会社数: " . count($supplier_ids) . "\n";
    
    // order_cost_itemsテーブルが存在するかチェック
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}ktp_order_cost_items'");
    
    if ($table_exists) {
        echo "DEBUG: コスト項目テーブルが存在します\n";
    
    // 1-3個の協力会社をランダムに選択
        $num_items = min(rand(1, 3), count($supplier_ids));
        echo "DEBUG: 選択する協力会社数: {$num_items}\n";
        
        if ($num_items > 0 && !empty($supplier_ids)) {
    $selected_suppliers = array_rand(array_flip($supplier_ids), $num_items);
            
            // 単一の値の場合は配列に変換
            if (!is_array($selected_suppliers)) {
                $selected_suppliers = array($selected_suppliers);
            }
            
            echo "DEBUG: 選択された協力会社ID: " . implode(', ', $selected_suppliers) . "\n";
    
    foreach ($selected_suppliers as $supplier_id) {
                echo "DEBUG: 協力会社ID {$supplier_id} の職能を検索中...\n";
                
        // 協力会社の職能をランダムに選択
        $skill = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ktp_supplier_skills WHERE supplier_id = %d ORDER BY RAND() LIMIT 1",
            $supplier_id
        ));
        
        if ($skill) {
                    safe_echo("DEBUG: 職能が見つかりました: {$skill->product_name}");
                    
            $quantity = rand(1, 10);
            $unit_price = $skill->unit_price;
            $total_cost = $quantity * $unit_price;
            
                                // ダミーデータ用の適格請求書番号を生成
            $dummy_qualified_invoice_number = 'T' . str_pad(rand(1, 999999), 6, '0', STR_PAD_LEFT);
            
            // 適格請求書番号カラムが存在するかチェック
            $qualified_invoice_column_exists = $wpdb->get_var(
                $wpdb->prepare(
                    "SHOW COLUMNS FROM {$wpdb->prefix}ktp_order_cost_items LIKE %s",
                    'qualified_invoice_number'
                )
            );
            
            // 税率がnullの場合は10%をデフォルトとして設定
            $default_tax_rate = $skill->tax_rate !== null ? $skill->tax_rate : 10.00;
            
            $insert_data = array(
                'order_id' => $order_id,
                'product_name' => $skill->product_name,
                'price' => $unit_price,
                'quantity' => $quantity,
                'unit' => $skill->unit,
                'amount' => $total_cost,
                'tax_rate' => $default_tax_rate,
                'remarks' => 'ダミーデータ',
                'purchase' => 'ダミーデータ',
                'ordered' => 0,
                'sort_order' => 1,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            );
            
            $format_array = array('%d', '%s', '%f', '%f', '%s', '%d', '%f', '%s', '%s', '%d', '%d', '%s', '%s');
            
            // 適格請求書番号カラムが存在する場合は追加
            if ($qualified_invoice_column_exists) {
                $insert_data['qualified_invoice_number'] = $dummy_qualified_invoice_number;
                $format_array = array('%d', '%s', '%f', '%f', '%s', '%d', '%f', '%s', '%s', '%s', '%d', '%d', '%s', '%s');
            }
            
            $result = $wpdb->insert(
                $wpdb->prefix . 'ktp_order_cost_items',
                $insert_data,
                $format_array
            );
                    
                    if ($result) {
                        safe_echo("DEBUG: コスト項目作成成功: {$skill->product_name} (数量: {$quantity}, 金額: ¥{$total_cost})");
                    } else {
                        safe_echo("DEBUG: コスト項目作成失敗: " . $wpdb->last_error);
                    }
                } else {
                    safe_echo("DEBUG: 協力会社ID {$supplier_id} の職能が見つかりませんでした");
                }
            }
        } else {
            safe_echo("DEBUG: 協力会社が選択されませんでした (num_items: {$num_items}, supplier_ids: " . implode(', ', $supplier_ids) . ")");
        }
    } else {
        safe_echo("DEBUG: コスト項目テーブルが存在しません");
    }
}

/**
 * データクリア機能
 */
function clear_dummy_data() {
    global $wpdb;
    
    safe_echo("⚠️  データクリア警告: 既存のダミーデータを削除します...");
    
    // 外部キー制約を無効化
    $wpdb->query("SET FOREIGN_KEY_CHECKS = 0");
    
    // 関連テーブルから削除（IDリセット対象）
    $tables_to_clear = array(
        'ktp_order_cost_items',
        'ktp_order_invoice_items',
        'ktp_order',
        'ktp_supplier_skills',
        'ktp_service',
        'ktp_supplier',
        'ktp_client',
        'ktp_order_staff_chat'  // スタッフチャットも削除対象に含める
    );
    
    foreach ($tables_to_clear as $table) {
        $table_name = $wpdb->prefix . $table;
        
        // データを削除
        $result = $wpdb->query("DELETE FROM {$table_name}");
        if ($result !== false) {
            safe_echo("テーブル {$table} をクリアしました");
        } else {
            safe_echo("テーブル {$table} のクリアに失敗しました: " . $wpdb->last_error);
        }
        
        // AUTO_INCREMENTをリセット
        $reset_result = $wpdb->query("ALTER TABLE {$table_name} AUTO_INCREMENT = 1");
        if ($reset_result !== false) {
            safe_echo("テーブル {$table} のAUTO_INCREMENTをリセットしました");
        } else {
            safe_echo("テーブル {$table} のAUTO_INCREMENTリセットに失敗しました: " . $wpdb->last_error);
        }
    }
    
    // 外部キー制約を再有効化
    $wpdb->query("SET FOREIGN_KEY_CHECKS = 1");
    
    safe_echo("✅ ダミーデータのクリアが完了しました！");
}

// コマンドライン引数でクリア機能を実行
if (isset($argv[1]) && $argv[1] === 'clear') {
    clear_dummy_data();
    exit;
} 