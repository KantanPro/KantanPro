<?php
/**
 * 強化版ダミーデータ作成スクリプト
 * バージョン: 2.2.8
 * 
 * 以下のデータを作成します：
 * - 顧客×6件
 * - 協力会社×6件
 * - サービス×6件（一般：税率10%・食品：税率8%・不動産：非課税）
 * - 受注書×ランダム件数（顧客ごとに2-8件、進捗は重み付きランダム分布）
 * - 職能×18件（協力会社×6件 × 税率3パターン：税率10%・税率8%・非課税）
 * - 請求項目とコスト項目を各受注書に追加
 * 
 * 修正内容（v2.2.8）:
 * - テーブル構造の不一致を修正（service_id、total_amountカラムを削除）
 * - 受注書作成エラーの解決
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

// 安全な出力関数
function safe_echo($message) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        echo $message . "\n";
    }
}

safe_echo("強化版ダミーデータ作成を開始します...");
safe_echo("バージョン: 2.2.8 (配布先サイト対応・テーブル構造修正版)");
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
    $insert_id = safe_db_insert(
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
    
    if ($insert_id) {
        $client_ids[] = $insert_id;
        safe_echo("顧客作成: {$client['company_name']}");
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
    $insert_id = safe_db_insert(
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
    
    if ($insert_id) {
        $supplier_ids[] = $insert_id;
        safe_echo("協力会社作成: {$supplier['company_name']}");
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
        safe_echo("サービス作成: {$service['service_name']} (税率: " . ($service['tax_rate'] ?? '非課税') . "%)");
    }
}

// 4. 職能データの作成（協力会社×6件 × 税率3パターン：税率10%・税率8%・非課税）
$skill_names = array('プログラミング', 'デザイン', 'ライティング', 'マーケティング', 'コンサルティング', 'データ分析', '翻訳', '動画編集', '写真撮影', 'SEO対策', 'SNS運用', '動画制作');
$tax_rates = array(10.00, 8.00, null); // 税率10%、税率8%、非課税

foreach ($supplier_ids as $supplier_id) {
    foreach ($tax_rates as $tax_rate) {
        $product_name = $skill_names[array_rand($skill_names)];
        $unit_price = rand(5000, 50000);
        $quantity = rand(1, 10);
        $unit = '時間';
        
        $insert_id = safe_db_insert(
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
        
        if ($insert_id) {
            safe_echo("職能作成: {$product_name} (税率: " . ($tax_rate ?? '非課税') . "%)");
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
                status, updated_at, time, customer_name, user_name, company_name, search_field,
                progress, memo, completion_date
            ) VALUES (
                %s, %d, %s, %s, %s, %s, %s, %s, %d, %s, %s, %s, %s, %d, %s, %s
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
            $company_name,
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
safe_echo("バージョン: 2.2.8 (配布先サイト対応・テーブル構造修正版)");
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
safe_echo("- 職能: 各協力会社に税率10%、税率8%、非課税の3パターン");
safe_echo("- サービス: 一般（税率10%）×2、食品（税率8%）×2、不動産（非課税）×2");
safe_echo("- 各受注書に請求項目とコスト項目を自動追加");
safe_echo("");
safe_echo("修正内容（v2.2.8）:");
safe_echo("- テーブル構造の不一致を修正（service_id、total_amountカラムを削除）");
safe_echo("- 受注書作成エラーの解決");
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
                        'quantity' => $quantity,
                        'unit' => $service->unit,
                        'amount' => $total_price,
                        'tax_rate' => $service->tax_rate,
                        'remarks' => 'ダミーデータ',
                        'sort_order' => 1,
                        'created_at' => current_time('mysql'),
                        'updated_at' => current_time('mysql')
                    ),
                    array('%d', '%s', '%f', '%f', '%s', '%f', '%f', '%s', '%d', '%s', '%s')
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
            
                    $result = $wpdb->insert(
                        $wpdb->prefix . 'ktp_order_cost_items',
                array(
                    'order_id' => $order_id,
                    'supplier_id' => $supplier_id,
                            'product_name' => $skill->product_name,
                            'price' => $unit_price,
                    'quantity' => $quantity,
                            'unit' => $skill->unit,
                            'amount' => $total_cost,
                    'tax_rate' => $skill->tax_rate,
                            'remarks' => 'ダミーデータ',
                            'sort_order' => 1,
                            'created_at' => current_time('mysql'),
                            'updated_at' => current_time('mysql')
                ),
                        array('%d', '%d', '%s', '%f', '%f', '%s', '%f', '%f', '%s', '%d', '%s', '%s')
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
        'ktp_client'
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
?> 