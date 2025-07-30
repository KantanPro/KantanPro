<?php
/**
 * 強化版ダミーデータ作成スクリプト
 * バージョン: 2.1.0
 * 
 * 以下のデータを作成します：
 * - 顧客×6件
 * - 協力会社×6件
 * - サービス×6件（一般：税率10%・食品：税率8%・不動産：非課税）
 * - 受注書×ランダム件数（顧客ごとに2-8件、進捗は重み付きランダム分布）
 * - 職能×18件（協力会社×6件 × 税率3パターン：税率10%・税率8%・非課税）
 * - 請求項目とコスト項目を各受注書に追加
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

global $wpdb;

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

echo "強化版ダミーデータ作成を開始します...\n";
echo "バージョン: 2.1.0 (ランダム進捗分布対応)\n";
echo "==========================================\n";

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
            'memo' => $client['memo'],
            'time' => time()
        ),
        array("%s", "%s", "%s", "%s", "%d")
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
        array("%s", "%s", "%s", "%s", "%d")
    );
    
    if ($result) {
        $supplier_ids[] = $wpdb->insert_id;
        echo "協力会社作成: {$supplier['company_name']}\n";
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
            'time' => time()
        ),
        array('%s', '%f', '%f', '%s', '%s', '%d')
    );
    
    if ($result) {
        $service_ids[] = $wpdb->insert_id;
        echo "サービス作成: {$service['service_name']} (税率: " . ($service['tax_rate'] ?? '非課税') . "%)\n";
    }
}

// 4. 受注書データの作成（ランダムな進捗分布）
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
        $total_amount = rand(100000, 2000000);
        
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
                $display_name = $client_info->company_name . ' (' . $client_info->name . ')';
                $search_field = $client_info->company_name . ', ' . $client_info->name;
                echo "DEBUG: 顧客ID {$client_id} の情報を取得しました: {$customer_name}, {$user_name}\n";
            } else {
                // 顧客情報が見つからない場合のフォールバック
                echo "WARNING: 顧客ID {$client_id} の情報が見つかりませんでした。\n";
                $display_name = '';
            }
        } else {
            echo "WARNING: client_idが設定されていません。\n";
        }
        
        // ランダムにサービスを選択
        $service_id = $service_ids[array_rand($service_ids)];
        
        // まず基本的なデータを挿入
        $sql = $wpdb->prepare(
            "INSERT INTO {$wpdb->prefix}ktp_order (
                order_number, client_id, service_id, project_name, order_date, 
                desired_delivery_date, expected_delivery_date, total_amount, 
                status, updated_at, time, customer_name, user_name, company_name, search_field,
                progress, memo, completion_date
            ) VALUES (
                %s, %d, %d, %s, %s, %s, %s, %f, %s, %s, %d, %s, %s, %s, %s, %d, %s, %s
            )",
            $order_number,
            $client_id,
            $service_id,
            $project_name,
            $order_date,
            $delivery_date,
            $delivery_date,
            $total_amount,
            $status_labels[$status],
            $current_datetime,
            $order_timestamp,
            $display_name, // 画面表示用の形式を使用
            $user_name,
            $company_name,
            $search_field,
            $status,
            'ダミーデータ',
            $completion_date
        );
        
        $result = $wpdb->query($sql);
        
        if ($result === false) {
            echo "ERROR: 受注書作成に失敗しました: " . $wpdb->last_error . "\n";
        } else {
            // 挿入後にcreated_atフィールドを更新（強制的に値を設定）
            $update_sql = $wpdb->prepare(
                "UPDATE {$wpdb->prefix}ktp_order SET created_at = %s WHERE id = %d",
                $created_time,
                $wpdb->insert_id
            );
            
            $update_result = $wpdb->query($update_sql);
            if ($update_result === false) {
                echo "WARNING: created_atフィールドの更新に失敗しました: " . $wpdb->last_error . "\n";
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
            echo "受注書作成: {$project_name}{$customer_info} (進捗: {$status_labels[$status]}, 作成日: {$created_time}{$completion_info}, 金額: ¥" . number_format($total_amount) . ")\n";
        }
    }
}

// 5. 職能データの作成（協力会社×6件 × 税率3パターン：税率10%・税率8%・非課税）
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
                'frequency' => rand(1, 100)
            ),
            array("%d", "%s", "%f", "%d", "%s", "%f", "%d")
        );
        
        if ($result) {
            echo "職能作成: {$product_name} (税率: " . ($tax_rate ?? '非課税') . "%)\n";
        }
    }
}

echo "\n==========================================\n";
echo "強化版ダミーデータ作成が完了しました！\n";
echo "バージョン: 2.1.0 (ランダム進捗分布対応)\n";
echo "作成されたデータ:\n";
echo "- 顧客: " . count($client_ids) . "件\n";
echo "- 協力会社: " . count($supplier_ids) . "件\n";
echo "- サービス: " . count($service_ids) . "件\n";
echo "- 受注書: " . count($order_ids) . "件\n";
echo "- 職能: " . (count($supplier_ids) * 3) . "件\n";
echo "\n詳細:\n";
echo "- 顧客: 各社のメールアドレスは全て info@kantanpro.com\n";
echo "- 協力会社: 各社のメールアドレスは全て info@kantanpro.com\n";
echo "- 受注書: ランダムな進捗分布で作成（受付中15%、見積中20%、受注25%、進行中20%、完成15%、請求済5%）\n";
echo "- 納期設定: 進捗に応じて適切な納期を設定（受注・進行中は将来、完成・請求済は過去）\n";
echo "- 完了日設定: 完成・請求済の注文には適切な完了日を設定\n";
echo "- 職能: 各協力会社に税率10%、税率8%、非課税の3パターン\n";
echo "- サービス: 一般（税率10%）×2、食品（税率8%）×2、不動産（非課税）×2\n";
echo "- 各受注書に請求項目とコスト項目を自動追加\n";

/**
 * 受注書に請求項目を追加
 */
function add_invoice_items_to_order($order_id, $service_ids) {
    global $wpdb;
    
    // invoice_itemsテーブルが存在するかチェック
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}ktp_invoice_items'");
    
    if ($table_exists) {
        // 1-3個のサービスをランダムに選択
        $num_items = rand(1, 3);
        $selected_services = array_rand(array_flip($service_ids), $num_items);
        
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
                    $wpdb->prefix . 'ktp_invoice_items',
                    array(
                        'order_id' => $order_id,
                        'service_id' => $service_id,
                        'item_name' => $service->service_name,
                        'quantity' => $quantity,
                        'unit_price' => $unit_price,
                        'total_price' => $total_price,
                        'tax_rate' => $service->tax_rate,
                        'unit' => $service->unit,
                        'created_at' => current_time('mysql')
                    ),
                    array('%d', '%d', '%s', '%d', '%f', '%f', '%f', '%s', '%s')
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
    
    // 1-3個の協力会社をランダムに選択
    $num_items = min(rand(1, 3), count($supplier_ids));
    if ($num_items > 0 && !empty($supplier_ids)) {
        $selected_suppliers = array_rand(array_flip($supplier_ids), $num_items);
        
        // 単一の値の場合は配列に変換
        if (!is_array($selected_suppliers)) {
            $selected_suppliers = array($selected_suppliers);
        }
        
        foreach ($selected_suppliers as $supplier_id) {
        // 協力会社の職能をランダムに選択
        $skill = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ktp_supplier_skills WHERE supplier_id = %d ORDER BY RAND() LIMIT 1",
            $supplier_id
        ));
        
        if ($skill) {
            $quantity = rand(1, 10);
            $unit_price = $skill->unit_price;
            $total_cost = $quantity * $unit_price;
            
            $wpdb->insert(
                $wpdb->prefix . 'ktp_supplier_cost',
                array(
                    'order_id' => $order_id,
                    'supplier_id' => $supplier_id,
                    'skill_id' => $skill->id,
                    'item_name' => $skill->product_name,
                    'quantity' => $quantity,
                    'unit_price' => $unit_price,
                    'total_cost' => $total_cost,
                    'tax_rate' => $skill->tax_rate,
                    'unit' => $skill->unit,
                    'created_at' => current_time('mysql')
                ),
                array('%d', '%d', '%d', '%s', '%d', '%f', '%f', '%f', '%s', '%s')
            );
        }
    }
    }
}

/**
 * データクリア機能
 */
function clear_dummy_data() {
    global $wpdb;
    
    echo "ダミーデータのクリアを開始します...\n";
    
    // 外部キー制約を無効化
    $wpdb->query("SET FOREIGN_KEY_CHECKS = 0");
    
    // 関連テーブルから削除
    $tables_to_clear = array(
        'ktp_supplier_cost',
        'ktp_invoice_items',
        'ktp_order',
        'ktp_supplier_skills',
        'ktp_service',
        'ktp_supplier',
        'ktp_client'
    );
    
    foreach ($tables_to_clear as $table) {
        $table_name = $wpdb->prefix . $table;
        $result = $wpdb->query("DELETE FROM {$table_name}");
        if ($result !== false) {
            echo "テーブル {$table} をクリアしました\n";
        } else {
            echo "テーブル {$table} のクリアに失敗しました: " . $wpdb->last_error . "\n";
        }
    }
    
    // 外部キー制約を再有効化
    $wpdb->query("SET FOREIGN_KEY_CHECKS = 1");
    
    echo "ダミーデータのクリアが完了しました！\n";
}

// コマンドライン引数でクリア機能を実行
if (isset($argv[1]) && $argv[1] === 'clear') {
    clear_dummy_data();
    exit;
}
?> 