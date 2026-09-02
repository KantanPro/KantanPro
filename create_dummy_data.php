<?php
/**
 * 強化版ダミーデータ作成スクリプト
 * バージョン: 2.6.2
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
 * 修正内容（v2.6.2）:
 * - 受注書 INSERT をテーブル構造に合わせて動的にカラムを選ぶよう修正（案件が作成されない不具合）
 * 
 * 修正内容（v2.6.1）:
 * - 受注書（案件）を顧客ごとに進捗1〜6を各1件ずつ作成し、全体でもバランスよく分布
 * - 同一顧客内の案件名重複を抑える
 * 
 * 修正内容（v2.6.0）:
 * - WordPress が日本語ロケールのとき、顧客・協力会社・サービス・受注名などを日本語の豊富なバリエーションで生成
 * - 進捗ラベルをアプリ仕様（受付中・見積中・受注・完了・請求済・入金済）に合わせて修正
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
 * 進捗分布（v2.6.1）:
 * - 顧客ごとに進捗1〜6を各1件（計6件）を作成し、案件の進捗をバランスよく表示
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

/**
 * 日本語ロケールでダミーデータを生成するか
 *
 * @return bool
 */
function ktp_dummy_data_uses_japanese() {
	if ( function_exists( 'determine_locale' ) ) {
		$locale = determine_locale();
	} elseif ( function_exists( 'get_locale' ) ) {
		$locale = get_locale();
	} else {
		$locale = 'en_US';
	}

	return strpos( (string) $locale, 'ja' ) === 0;
}

/**
 * 配列からランダムに1件取得
 *
 * @param array<int|string, mixed> $items 候補。
 * @return mixed
 */
function ktp_dummy_pick( array $items ) {
	if ( $items === array() ) {
		return '';
	}

	return $items[ array_rand( $items ) ];
}

/**
 * 配列から重複なしで複数件取得
 *
 * @param array<int|string, mixed> $items 候補。
 * @param int                      $count 件数。
 * @return array<int, mixed>
 */
function ktp_dummy_pick_many( array $items, $count ) {
	$count = max( 0, (int) $count );
	if ( $count === 0 || $items === array() ) {
		return array();
	}
	$keys = array_keys( $items );
	shuffle( $keys );
	$keys   = array_slice( $keys, 0, min( $count, count( $keys ) ) );
	$result = array();
	foreach ( $keys as $key ) {
		$result[] = $items[ $key ];
	}

	return $result;
}

/**
 * 日本語のランダムな担当者名
 *
 * @return string
 */
function ktp_dummy_random_japanese_person_name() {
	$family = array(
		'山田', '佐藤', '鈴木', '田中', '高橋', '伊藤', '渡辺', '中村', '小林', '加藤',
		'吉田', '山本', '松本', '井上', '木村', '林', '斎藤', '清水', '山口', '阿部',
		'森', '池田', '橋本', '石川', '前田', '藤田', '岡田', '後藤', '長谷川', '村上',
	);
	$given = array(
		'太郎', '花子', '一郎', '美咲', '健太', '由美', '翔太', '彩', '大輔', '真理',
		'直樹', '裕子', '浩二', '恵', '葵', '颯太', '陽子', '誠', '奈々', '拓也',
		'智子', '亮', '麻衣', '慎一', '優子', '和也', '里奈', '雄大', '千尋', '光',
	);

	return ktp_dummy_pick( $family ) . ktp_dummy_pick( $given );
}

/**
 * ダミーデータ用カタログ（ロケール別）
 *
 * @return array<string, mixed>
 */
function ktp_dummy_data_get_config() {
	static $config = null;
	if ( $config !== null ) {
		return $config;
	}

	$ja = ktp_dummy_data_uses_japanese();

	if ( $ja ) {
		$config = array(
			'locale_label'     => 'ja',
			'remarks'          => 'ダミーデータ',
			'purchase_label'   => 'ダミーデータ',
			'order_memo'       => 'ダミーデータ',
			'progress_labels'  => array(
				1 => '受付中',
				2 => '見積中',
				3 => '受注',
				4 => '完了',
				5 => '請求済',
				6 => '入金済',
			),
			'units'            => array( '式', '月', '時間', '件', '回', '人月', 'セット', '日' ),
			'client_categories' => array( 'tech', 'real_estate', 'general', 'logistics', 'food', 'healthcare' ),
			'supplier_categories' => array( 'tech', 'real_estate', 'general', 'logistics', 'food', 'education' ),
			'service_categories' => array( 'tech', 'real_estate', 'general', 'logistics', 'food', 'finance' ),
			'skill_categories' => array( 'tech', 'real_estate', 'general', 'logistics', 'food', 'healthcare' ),
			'order_names'      => array(
				'コーポレートサイトリニューアル', 'ECサイト構築プロジェクト', '業務システム開発', 'マーケティング戦略立案',
				'ロゴ・VIデザイン制作', 'データ分析・レポート作成', 'モバイルアプリ開発', 'SEO対策コンサルティング',
				'SNS運用代行', '動画制作・プロモーション', 'ランディングページ制作', '会員サイト構築',
				'在庫管理システム導入', '顧客管理CRM整備', '採用サイト制作', 'オンライン講座プラットフォーム構築',
				'店舗予約システム開発', '請求業務フロー改善', '多言語サイト対応', 'サーバー移行・クラウド化',
				'年末キャンペーン企画', '商品撮影・カタログ制作', '社内ポータル刷新', 'アクセス解析ダッシュボード構築',
			),
			'categories'       => array(
				'tech'         => array( 'key' => 'tech', 'label' => 'IT・テック', 'tax_rate' => 10.00, 'description' => 'IT・システム開発関連' ),
				'real_estate'  => array( 'key' => 'real_estate', 'label' => '不動産', 'tax_rate' => null, 'description' => '不動産・建設関連' ),
				'general'      => array( 'key' => 'general', 'label' => '一般', 'tax_rate' => 10.00, 'description' => '一般的なビジネスサービス' ),
				'logistics'    => array( 'key' => 'logistics', 'label' => '物流', 'tax_rate' => 10.00, 'description' => '物流・配送関連' ),
				'food'         => array( 'key' => 'food', 'label' => '食品', 'tax_rate' => 8.00, 'description' => '食品・飲食関連（軽減税率）' ),
				'healthcare'   => array( 'key' => 'healthcare', 'label' => '医療・健康', 'tax_rate' => 10.00, 'description' => '医療・ヘルスケア関連' ),
				'education'    => array( 'key' => 'education', 'label' => '教育', 'tax_rate' => 10.00, 'description' => '教育・研修関連' ),
				'finance'      => array( 'key' => 'finance', 'label' => '金融', 'tax_rate' => 10.00, 'description' => '金融・保険関連' ),
			),
			'category_data'    => array(
				'tech' => array(
					'companies' => array(
						'株式会社テックソリューション', 'デジタルクリエイターズ合同会社', 'システム開発パートナーズ',
						'ウェブデザイン工房株式会社', 'クラウドワークス東京', '株式会社ネットイノベーション',
						'合同会社コードラボ', '株式会社UXデザインセンター', '株式会社データドリブン',
						'株式会社セキュアシステムズ', 'AIソリューションズ株式会社', '株式会社モバイルファクトリー',
					),
					'services' => array(
						'Webサイト制作', 'システム開発', 'モバイルアプリ開発', 'クラウド基盤構築',
						'データベース設計', 'API開発', 'UX/UIデザイン', '保守・運用サポート',
						'セキュリティ診断', 'AI導入コンサルティング', 'RPA構築', '社内ITヘルプデスク',
					),
					'skills' => array(
						'プログラミング', 'システム設計', 'データベース管理', 'クラウドインフラ',
						'セキュリティコンサル', '機械学習', 'フロントエンド開発', 'バックエンド開発',
						'テスト設計', 'DevOps支援', '要件定義', '技術ドキュメント作成',
					),
					'food_skill' => '',
				),
				'real_estate' => array(
					'companies' => array(
						'株式会社不動産コンサルティング', '建設工房合同会社', '建築設計パートナーズ',
						'プロパティマネジメント株式会社', '株式会社都市開発企画', 'リフォーム専門店サポート',
						'株式会社土地活用アドバイザー', '建築士事務所ネットワーク', '株式会社賃貸管理センター',
						'株式会社解体・改修工房', '不動産査定サービス株式会社', '株式会社内装デザイン',
					),
					'services' => array(
						'不動産仲介', '物件管理', '建築設計', 'リフォーム工事',
						'不動産投資コンサル', '物件査定', '内装プランニング', '維持管理',
						'テナント誘致支援', '法務・登記サポート', '解体工事', '耐震診断',
					),
					'skills' => array(
						'建築設計', '不動産鑑定', '施工管理', 'CAD設計',
						'不動産法務サポート', 'プロジェクト管理', '積算業務', '現場監督',
						'マスタープラン策定', '内装コーディネート', '設備設計', '測量',
					),
					'food_skill' => '',
				),
				'general' => array(
					'companies' => array(
						'株式会社サンプル商事', 'ビジネスコンサルティング合同会社', 'デザインワークショップ',
						'マーケティングプロ株式会社', '株式会社総合サポート', '合同会社オフィスアシスト',
						'株式会社広報戦略室', '株式会社経営改善ラボ', '合同会社翻訳センター',
						'株式会社イベント企画', '株式会社リサーチパートナーズ', '合同会社ブランディング',
					),
					'services' => array(
						'経営コンサルティング', 'マーケティング戦略', 'デザイン制作', '翻訳・通訳',
						'イベント企画', 'リサーチ・分析', '広報支援', '採用ブランディング',
						'業務改善コンサル', '社内研修', 'プレゼン資料作成', 'コピーライティング',
					),
					'skills' => array(
						'経営コンサル', 'マーケティング', 'デザイン', '翻訳',
						'イベント運営', 'データ分析', '広報PR', 'ファシリテーション',
						'議事録作成', '資料デザイン', '競合調査', '顧客ヒアリング',
					),
					'food_skill' => '',
				),
				'logistics' => array(
					'companies' => array(
						'株式会社ロジスティクス', '運送サービス合同会社', '倉庫管理パートナーズ',
						'デリバリーセンター株式会社', '株式会社サプライチェーン', '合同会社配送最適化',
						'株式会社国際物流', '株式会社冷蔵流通', '株式会社ラストワンマイル',
						'合同会社通関サポート', '株式会社在庫管理センター', '株式会社フルフィルメント',
					),
					'services' => array(
						'物流管理', '配送サービス', '倉庫管理', '輸出入手続き',
						'サプライチェーン管理', '配送ルート最適化', '在庫棚卸', '梱包・発送代行',
						'国際輸送手配', '冷蔵・冷凍配送', '返品処理', '物流コスト削減コンサル',
					),
					'skills' => array(
						'物流管理', '配送計画', '倉庫オペレーション', '通関手続き',
						'ルート最適化', '在庫管理', 'フォークリフト操作', '配送ドライバー',
						'WMS運用', '輸送コスト分析', '危険物取扱', '荷役作業',
					),
					'food_skill' => '',
				),
				'food' => array(
					'companies' => array(
						'株式会社フードサービス', 'ケータリング合同会社', 'フードデリバリーパートナーズ',
						'レストラン運営株式会社', '株式会社給食センター', '合同会社フードラボ',
						'株式会社農産物直販', 'ベーカリーチェーン株式会社', '株式会社仕出し料理',
						'合同会社フードセーフティ', '株式会社カフェ運営', '株式会社食品加工',
					),
					'services' => array(
						'食品', 'ケータリング', 'フードデリバリー', '飲食店運営',
						'食品加工', '栄養管理', '衛生管理', 'メニュー開発',
						'食材仕入れ', '店舗オペレーション', 'テイクアウト企画', '給食委託',
					),
					'skills' => array(
						'食品', '品質管理', '栄養管理', '食品衛生',
						'食材仕入れ', 'メニュー開発', '衛生管理', '調理',
						'HACCP対応', '仕込み作業', '盛り付け', '店舗販売',
					),
					'food_skill' => '食品',
				),
				'healthcare' => array(
					'companies' => array(
						'株式会社メディカルサービス', 'ヘルスケア合同会社', '医療コンサルティングパートナーズ',
						'調剤サポート株式会社', '株式会社ウェルネスラボ', '合同会社訪問看護ステーション',
						'株式会社健康経営支援', '医療機器メンテナンス株式会社', '株式会社リハビリセンター',
						'合同会社介護サポート', '株式会社健診センター', '株式会社薬局運営',
					),
					'services' => array(
						'医療コンサルティング', '健康診断', '調剤業務支援', '医療機器管理',
						'看護サービス', '医事業務支援', '健康経営プログラム', '訪問リハビリ',
						'介護施設運営支援', '医療事務代行', '院内DX支援', '福祉用具レンタル',
					),
					'skills' => array(
						'医療コンサル', '看護', '薬剤師業務', '医事業務',
						'健康管理', '医療機器操作', 'リハビリ支援', '介護計画',
						'カルテ入力', '受付業務', '検査補助', '栄養指導',
					),
					'food_skill' => '',
				),
				'education' => array(
					'companies' => array(
						'株式会社教育サービス', 'トレーニングセンター合同会社', 'オンライン教育パートナーズ',
						'スクール運営株式会社', '株式会社学習支援ラボ', '合同会社eラーニング',
						'株式会社資格試験対策', '株式会社塾運営サポート', '合同会社教材開発',
						'株式会社企業研修', '株式会社語学スクール', '株式会社STEM教育',
					),
					'services' => array(
						'研修サービス', 'オンライン教育', 'スクール運営', '教材開発',
						'資格取得支援', '教育コンサル', '企業内研修', '学習eラーニング',
						'コーチング', '進路指導', 'カリキュラム設計', '講師派遣',
					),
					'skills' => array(
						'講師業務', '教材開発', '教育コンサル', 'オンライン講義',
						'資格研修', 'カリキュラム設計', '学習コーチング', '試験対策指導',
						'授業運営', '学習評価', 'eラーニング制作', '進路相談',
					),
					'food_skill' => '',
				),
				'finance' => array(
					'companies' => array(
						'株式会社ファイナンシャルサービス', '保険代理店合同会社', '投資コンサルティングパートナーズ',
						'会計事務所サポート株式会社', '株式会社資産運用ラボ', '合同会社税務アドバイザー',
						'株式会社FPオフィス', '株式会社融資サポート', '合同会社経理代行',
						'株式会社リスクマネジメント', '株式会社監査支援', '株式会社福利厚生コンサル',
					),
					'services' => array(
						'投資コンサルティング', '保険コンサル', '会計サービス', '税務コンサル',
						'資産管理', 'リスク管理', '経理代行', '融資相談',
						'補助金申請支援', '財務分析', 'M&Aアドバイザリー', '年金プランニング',
					),
					'skills' => array(
						'投資コンサル', '保険設計', '会計', '税務',
						'資産管理', 'リスク管理', '経理実務', '財務分析',
						'監査支援', '給与計算', '補助金調査', '契約書レビュー',
					),
					'food_skill' => '',
				),
			),
		);

		return $config;
	}

	$config = array(
		'locale_label'     => 'en',
		'remarks'          => 'Dummy Data',
		'purchase_label'   => 'Dummy Data',
		'order_memo'       => 'Dummy Data',
		'progress_labels'  => array(
			1 => 'Received',
			2 => 'Estimating',
			3 => 'Ordered',
			4 => 'Completed',
			5 => 'Invoiced',
			6 => 'Paid',
		),
		'units'            => array( 'project', 'month', 'hour', 'item', 'session', 'day', 'set' ),
		'client_categories' => array( 'tech', 'real_estate', 'general', 'logistics', 'food', 'healthcare' ),
		'supplier_categories' => array( 'tech', 'real_estate', 'general', 'logistics', 'food', 'education' ),
		'service_categories' => array( 'tech', 'real_estate', 'general', 'logistics', 'food', 'finance' ),
		'skill_categories' => array( 'tech', 'real_estate', 'general', 'logistics', 'food', 'healthcare' ),
		'order_names'      => array(
			'Website Renewal', 'E-commerce Site Development', 'Business System Development', 'Marketing Strategy Planning',
			'Logo Design Production', 'Data Analysis Service', 'Mobile App Development', 'SEO Consulting Service',
			'Social Media Management', 'Video Production', 'Landing Page Build', 'Membership Site Development',
			'Inventory System Rollout', 'CRM Setup Project', 'Recruitment Site Launch', 'Online Course Platform',
			'Booking System Development', 'Billing Workflow Improvement', 'Multilingual Site Migration', 'Cloud Migration',
		),
		'categories'       => array(
			'tech'         => array( 'key' => 'tech', 'label' => 'Tech', 'tax_rate' => 10.00, 'description' => 'IT and technology services' ),
			'real_estate'  => array( 'key' => 'real_estate', 'label' => 'Real Estate', 'tax_rate' => null, 'description' => 'Real estate and construction services' ),
			'general'      => array( 'key' => 'general', 'label' => 'General', 'tax_rate' => 10.00, 'description' => 'General business services' ),
			'logistics'    => array( 'key' => 'logistics', 'label' => 'Logistics', 'tax_rate' => 10.00, 'description' => 'Logistics and transportation services' ),
			'food'         => array( 'key' => 'food', 'label' => 'Food', 'tax_rate' => 8.00, 'description' => 'Food and restaurant services' ),
			'healthcare'   => array( 'key' => 'healthcare', 'label' => 'Healthcare', 'tax_rate' => 10.00, 'description' => 'Medical and healthcare services' ),
			'education'    => array( 'key' => 'education', 'label' => 'Education', 'tax_rate' => 10.00, 'description' => 'Education and training services' ),
			'finance'      => array( 'key' => 'finance', 'label' => 'Finance', 'tax_rate' => 10.00, 'description' => 'Finance and insurance services' ),
		),
		'category_data'    => array(
			'tech' => array(
				'companies' => array( 'Tech Solutions Inc.', 'Digital Creators LLC', 'System Development Partners', 'Web Design Studio Inc.', 'Cloud Works Tokyo', 'Net Innovation Corp.' ),
				'services' => array( 'Website Development', 'System Development', 'Mobile App Development', 'Cloud Infrastructure Setup', 'Database Design', 'API Development', 'UX Design', 'Maintenance Support' ),
				'skills' => array( 'Programming', 'System Design', 'Database Administration', 'Cloud Infrastructure', 'Security Consulting', 'AI and Machine Learning', 'Frontend Development', 'Backend Development' ),
				'food_skill' => '',
			),
			'real_estate' => array(
				'companies' => array( 'Real Estate Consulting Inc.', 'Construction Works LLC', 'Architectural Design Partners', 'Property Management Inc.', 'Urban Development Corp.', 'Renovation Support LLC' ),
				'services' => array( 'Real Estate Brokerage', 'Property Management', 'Architectural Design', 'Construction Work', 'Real Estate Investment Consulting', 'Property Appraisal', 'Interior Planning', 'Building Maintenance' ),
				'skills' => array( 'Architectural Design', 'Real Estate Appraisal', 'Construction Management', 'CAD Design', 'Real Estate Legal Support', 'Project Management', 'Cost Estimation', 'Site Supervision' ),
				'food_skill' => '',
			),
			'general' => array(
				'companies' => array( 'Sample Trading Inc.', 'Business Consulting LLC', 'Design Workshop Partners', 'Marketing Pro Inc.', 'General Support Corp.', 'Office Assist LLC' ),
				'services' => array( 'Business Consulting', 'Marketing Strategy', 'Design Production', 'Translation Services', 'Event Planning', 'Research and Analysis', 'PR Support', 'Copywriting' ),
				'skills' => array( 'Business Consulting', 'Marketing', 'Design', 'Translation', 'Event Planning', 'Data Analysis', 'PR', 'Facilitation' ),
				'food_skill' => '',
			),
			'logistics' => array(
				'companies' => array( 'Logistics Inc.', 'Transport Services LLC', 'Warehouse Management Partners', 'Delivery Center Inc.', 'Supply Chain Corp.', 'Route Optimization LLC' ),
				'services' => array( 'Logistics Management', 'Delivery Services', 'Warehouse Management', 'Import and Export Procedures', 'Supply Chain Management', 'Delivery Route Optimization', 'Inventory Counting', 'Fulfillment' ),
				'skills' => array( 'Logistics Management', 'Delivery Planning', 'Warehouse Operations', 'Customs Procedures', 'Route Optimization', 'Inventory Management', 'Forklift Operation', 'Driver Services' ),
				'food_skill' => '',
			),
			'food' => array(
				'companies' => array( 'Food Services Inc.', 'Catering LLC', 'Food Delivery Partners', 'Restaurant Operations Inc.', 'Meal Center Corp.', 'Food Lab LLC' ),
				'services' => array( 'Food', 'Catering Services', 'Food Delivery', 'Restaurant Operations', 'Food Processing', 'Nutrition Management', 'Food Safety Management', 'Menu Development' ),
				'skills' => array( 'Food', 'Food Quality Control', 'Nutrition Management', 'Food Safety', 'Ingredient Procurement', 'Menu Development', 'Sanitation Management', 'Cooking' ),
				'food_skill' => 'Food',
			),
			'healthcare' => array(
				'companies' => array( 'Medical Services Inc.', 'Healthcare LLC', 'Medical Consulting Partners', 'Pharmacy Operations Inc.', 'Wellness Lab Corp.', 'Home Nursing LLC' ),
				'services' => array( 'Medical Consulting', 'Health Checkups', 'Pharmacy Operations', 'Medical Equipment Management', 'Nursing Services', 'Medical Administration', 'Wellness Programs', 'Rehabilitation Support' ),
				'skills' => array( 'Medical Consulting', 'Nursing', 'Pharmacist Services', 'Medical Administration', 'Health Management', 'Medical Equipment Operation', 'Rehabilitation Support', 'Care Planning' ),
				'food_skill' => '',
			),
			'education' => array(
				'companies' => array( 'Education Services Inc.', 'Training Center LLC', 'Online Education Partners', 'School Operations Inc.', 'Learning Lab Corp.', 'E-Learning LLC' ),
				'services' => array( 'Training Services', 'Online Education', 'School Operations', 'Teaching Material Development', 'Certification Support', 'Education Consulting', 'Corporate Training', 'Coaching' ),
				'skills' => array( 'Instructor Services', 'Teaching Material Development', 'Education Consulting', 'Online Education', 'Certification Training', 'Curriculum Design', 'Learning Coaching', 'Exam Preparation' ),
				'food_skill' => '',
			),
			'finance' => array(
				'companies' => array( 'Financial Services Inc.', 'Insurance Agency LLC', 'Investment Consulting Partners', 'Accounting Office Inc.', 'Asset Lab Corp.', 'Tax Advisory LLC' ),
				'services' => array( 'Investment Consulting', 'Insurance Consulting', 'Accounting Services', 'Tax Consulting', 'Asset Management', 'Risk Management', 'Bookkeeping', 'Loan Consulting' ),
				'skills' => array( 'Investment Consulting', 'Insurance Planning', 'Accounting', 'Tax Services', 'Asset Management', 'Risk Management', 'Bookkeeping', 'Financial Analysis' ),
				'food_skill' => '',
			),
		),
	);

	return $config;
}

/**
 * カテゴリーキーから税率を取得
 *
 * @param string $category_key カテゴリーキー。
 * @param array<string, mixed> $config カタログ。
 * @return float|null
 */
function ktp_dummy_get_tax_rate_by_category_key( $category_key, array $config ) {
	if ( ! isset( $config['categories'][ $category_key ] ) ) {
		return 10.00;
	}

	return $config['categories'][ $category_key ]['tax_rate'];
}

/**
 * ダミーデータ用メールアドレス
 *
 * @param string $prefix 接頭辞。
 * @return string
 */
function ktp_dummy_random_email( $prefix = 'demo' ) {
	$suffix = strtolower( bin2hex( random_bytes( 3 ) ) );

	return sanitize_email( $prefix . '+' . $suffix . '@example.com' );
}

// カタログ読み込み
$dummy_config = ktp_dummy_data_get_config();
$categories = $dummy_config['categories'];
$category_data = $dummy_config['category_data'];

// 安全なデータベース操作関数
/**
 * テーブルのカラム一覧を取得
 *
 * @param string $table_name テーブル名。
 * @return string[]
 */
function ktp_dummy_get_table_columns( $table_name ) {
	static $cache = array();
	if ( isset( $cache[ $table_name ] ) ) {
		return $cache[ $table_name ];
	}
	global $wpdb;
	$columns = $wpdb->get_col( "SHOW COLUMNS FROM `{$table_name}`", 0 );
	$cache[ $table_name ] = is_array( $columns ) ? $columns : array();

	return $cache[ $table_name ];
}

/**
 * テーブルに存在するカラムだけに行データを絞る
 *
 * @param string               $table_name テーブル名。
 * @param array<string, mixed> $data       行データ。
 * @return array<string, mixed>
 */
function ktp_dummy_filter_row_for_table( $table_name, array $data ) {
	$columns  = ktp_dummy_get_table_columns( $table_name );
	$filtered = array();
	foreach ( $data as $key => $value ) {
		if ( in_array( $key, $columns, true ) ) {
			$filtered[ $key ] = $value;
		}
	}

	return $filtered;
}

/**
 * wpdb->insert 用 format 配列を推定
 *
 * @param array<string, mixed> $data 行データ。
 * @return string[]
 */
function ktp_dummy_guess_formats( array $data ) {
	$formats = array();
	foreach ( $data as $value ) {
		if ( is_int( $value ) ) {
			$formats[] = '%d';
		} elseif ( is_float( $value ) ) {
			$formats[] = '%f';
		} else {
			$formats[] = '%s';
		}
	}

	return $formats;
}

function safe_db_insert($table, $data, $format = null) {
    global $wpdb;

	$data = ktp_dummy_filter_row_for_table( $table, $data );
	if ( $data === array() ) {
		error_log( "KTPWP: 挿入可能なカラムがありません - テーブル: {$table}" );
		return false;
	}
	$format = ktp_dummy_guess_formats( $data );
    
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

/**
 * 進捗 1〜6 を均等に含むシーケンスを生成（不足分はローテーション）
 *
 * @param int $count 件数。
 * @return int[]
 */
function ktp_dummy_build_balanced_progress_sequence( $count ) {
	$count            = max( 1, (int) $count );
	$progress_values  = array( 1, 2, 3, 4, 5, 6 );
	$sequence         = array();

	while ( count( $sequence ) < $count ) {
		$batch = $progress_values;
		shuffle( $batch );
		foreach ( $batch as $progress ) {
			$sequence[] = $progress;
			if ( count( $sequence ) >= $count ) {
				break;
			}
		}
	}

	return array_slice( $sequence, 0, $count );
}

/**
 * 顧客ごとの案件名リスト（重複を抑える）
 *
 * @param array<int, string> $order_names 候補。
 * @param int                $count       件数。
 * @return string[]
 */
function ktp_dummy_pick_unique_order_names( array $order_names, $count ) {
	$count = max( 1, (int) $count );
	$picked = ktp_dummy_pick_many( $order_names, min( $count, count( $order_names ) ) );

	while ( count( $picked ) < $count ) {
		$picked[] = ktp_dummy_pick( $order_names );
	}

	return $picked;
}

// カテゴリーに基づく税率取得関数
function get_tax_rate_by_category( $category_key ) {
	global $dummy_config;
	if ( ! isset( $dummy_config ) ) {
		$dummy_config = ktp_dummy_data_get_config();
	}

	return ktp_dummy_get_tax_rate_by_category_key( $category_key, $dummy_config );
}

// 安全な出力関数
function safe_echo($message) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        echo $message . "\n";
    }
}

safe_echo("強化版ダミーデータ作成を開始します...");
safe_echo("バージョン: 2.6.1 (案件バランス配分版)");
safe_echo("ロケール: " . $dummy_config['locale_label']);
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

foreach ( $dummy_config['client_categories'] as $category_key ) {
	if ( ! isset( $category_data[ $category_key ] ) ) {
		continue;
	}
	$cat_meta = $categories[ $category_key ];
	$company_name = ktp_dummy_pick( $category_data[ $category_key ]['companies'] );
	$name = ktp_dummy_data_uses_japanese()
		? ktp_dummy_random_japanese_person_name()
		: ktp_dummy_pick( array( 'John Smith', 'Emily Johnson', 'Michael Brown', 'Sarah Davis', 'David Wilson', 'Jessica Taylor', 'Robert Lee', 'Amanda Clark', 'Daniel White', 'Laura Martinez' ) );

	$clients[] = array(
		'company_name' => $company_name,
		'name'         => $name,
		'email'        => ktp_dummy_random_email( 'client' ),
		'memo'         => $cat_meta['description'],
		'category'     => $cat_meta['label'],
		'category_key' => $category_key,
	);
}

$client_ids = array();
foreach ($clients as $client) {
    $now = current_time( 'mysql' );
    $insert_id = safe_db_insert(
        $wpdb->prefix . 'ktp_client',
        array(
            'company_name' => $client['company_name'],
            'name' => $client['name'],
            'email' => $client['email'],
            'memo' => $client['memo'],
            'category' => $client['category'],
            'time' => time(),
            'created_at' => $now,
            'updated_at' => $now,
        )
    );
    
    if ($insert_id) {
        $client_ids[] = $insert_id;
        $tax_rate = get_tax_rate_by_category($client['category_key']);
        $tax_info = $tax_rate !== null ? "税率{$tax_rate}%" : "非課税";
        safe_echo("顧客作成: {$client['company_name']} (カテゴリー: {$client['category']}, {$tax_info})");
    }
}

// 2. 協力会社データの作成（カテゴリー別）
$suppliers = array();

foreach ( $dummy_config['supplier_categories'] as $category_key ) {
	if ( ! isset( $category_data[ $category_key ] ) ) {
		continue;
	}
	$cat_meta = $categories[ $category_key ];
	$company_name = ktp_dummy_pick( $category_data[ $category_key ]['companies'] );
	$name = ktp_dummy_data_uses_japanese()
		? ktp_dummy_random_japanese_person_name()
		: ktp_dummy_pick( array( 'Robert Anderson', 'Laura Martinez', 'William Thompson', 'Karen White', 'James Harris', 'Linda Clark', 'Thomas Young', 'Susan King', 'Charles Wright', 'Nancy Scott' ) );

	$suppliers[] = array(
		'company_name' => $company_name,
		'name'         => $name,
		'email'        => ktp_dummy_random_email( 'supplier' ),
		'memo'         => $cat_meta['description'],
		'category'     => $cat_meta['label'],
		'category_key' => $category_key,
	);
}

$supplier_ids = array();
foreach ($suppliers as $supplier) {
    // ダミーデータ用の適格請求書番号を生成
    $qualified_invoice_number = 'T' . str_pad(rand(1, 999999), 6, '0', STR_PAD_LEFT);
    $now = current_time( 'mysql' );
    
    $insert_id = safe_db_insert(
        $wpdb->prefix . 'ktp_supplier',
        array(
            'company_name' => $supplier['company_name'],
            'name' => $supplier['name'],
            'email' => $supplier['email'],
            'memo' => $supplier['memo'],
            'category' => $supplier['category'],
            'qualified_invoice_number' => $qualified_invoice_number,
            'time' => time(),
            'created_at' => $now,
            'updated_at' => $now,
        )
    );
    
    if ($insert_id) {
        $supplier_ids[] = $insert_id;
        $tax_rate = get_tax_rate_by_category($supplier['category_key']);
        $tax_info = $tax_rate !== null ? "税率{$tax_rate}%" : "非課税";
        safe_echo("協力会社作成: {$supplier['company_name']} (カテゴリー: {$supplier['category']}, {$tax_info}, 適格請求書番号: {$qualified_invoice_number})");
    }
}

// 3. サービスデータの作成（カテゴリー別・税率自動設定）
$services = array();

foreach ( $dummy_config['service_categories'] as $category_key ) {
	if ( ! isset( $category_data[ $category_key ] ) ) {
		continue;
	}
	$cat_meta       = $categories[ $category_key ];
	$service_names  = $category_data[ $category_key ]['services'];
	$selected_names = ktp_dummy_pick_many( $service_names, 2 );

	foreach ( $selected_names as $service_name ) {
		$tax_rate = ktp_dummy_get_tax_rate_by_category_key( $category_key, $dummy_config );
		if ( $tax_rate === null ) {
			$tax_rate = 10.00;
		}

		$price = rand( 50000, 800000 );
		$unit  = ktp_dummy_pick( $dummy_config['units'] );

		$services[] = array(
			'service_name' => $service_name,
			'price'        => $price,
			'tax_rate'     => $tax_rate,
			'unit'         => $unit,
			'category'     => $cat_meta['label'],
			'category_key' => $category_key,
		);
	}
}

$service_ids = array();
foreach ($services as $service) {
    $now = current_time( 'mysql' );
    $insert_id = safe_db_insert(
        $wpdb->prefix . 'ktp_service',
        array(
            'service_name' => $service['service_name'],
            'price' => $service['price'],
            'tax_rate' => $service['tax_rate'],
            'unit' => $service['unit'],
            'category' => $service['category'],
            'time' => time(),
            'created_at' => $now,
            'updated_at' => $now,
        )
    );
    
    if ($insert_id) {
        $service_ids[] = $insert_id;
        $tax_info = $service['tax_rate'] ? "税率{$service['tax_rate']}%" : "非課税";
        safe_echo("サービス作成: {$service['service_name']} (カテゴリー: {$service['category']}, {$tax_info})");
    }
}

// 4. 職能データの作成（カテゴリー別・税率自動設定）
$skill_categories = $dummy_config['skill_categories'];

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
    
    $supplier_category_label = $supplier_info->category;
    $supplier_category_key = 'general';
    foreach ( $categories as $key => $meta ) {
        if ( $meta['label'] === $supplier_category_label ) {
            $supplier_category_key = $key;
            break;
        }
    }
    safe_echo("協力会社のカテゴリー: {$supplier_category_label}");
    
    if (!isset($category_data[$supplier_category_key])) {
        safe_echo("ERROR: カテゴリー '{$supplier_category_label}' のデータが定義されていません");
        $supplier_category_key = 'general';
    }
    
    $skill_names = $category_data[$supplier_category_key]['skills'];
    $food_skill_name = isset( $category_data[ $supplier_category_key ]['food_skill'] )
        ? (string) $category_data[ $supplier_category_key ]['food_skill']
        : '';
    safe_echo("職能名リスト: " . implode(', ', $skill_names));
    
    $tax_patterns = array();
    $skill_names_for_tax_8 = array();
    $skill_names_for_tax_10 = $skill_names;
    
    if ( $supplier_category_key === 'food' && $food_skill_name !== '' ) {
        $tax_patterns = array( 8.00, 10.00, 10.00 );
        $skill_names_for_tax_8  = array( $food_skill_name );
        $skill_names_for_tax_10 = array_values( array_diff( $skill_names, array( $food_skill_name ) ) );
        if ( $skill_names_for_tax_10 === array() ) {
            $skill_names_for_tax_10 = $skill_names;
        }
        safe_echo("税率8%用職能名: " . implode(', ', $skill_names_for_tax_8));
        safe_echo("税率10%用職能名: " . implode(', ', $skill_names_for_tax_10));
    } else {
        $category_tax = ktp_dummy_get_tax_rate_by_category_key( $supplier_category_key, $dummy_config );
        if ( $category_tax === null ) {
            $tax_patterns = array( null, 10.00, 10.00 );
        } else {
            $tax_patterns = array( 10.00, 10.00, null );
        }
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
        $unit = 'hour';
        
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
        
        safe_echo("職能データ: " . wp_json_encode($skill_data, JSON_UNESCAPED_UNICODE));
        
        $insert_id = safe_db_insert(
            $wpdb->prefix . 'ktp_supplier_skills',
            $skill_data,
            array("%d", "%s", "%f", "%d", "%s", "%f", "%d")
        );
        
        if ($insert_id) {
            $tax_info = $tax_rate !== null ? "税率{$tax_rate}%" : "非課税";
            safe_echo("✓ 職能作成成功: {$skill_name} (カテゴリー: {$supplier_category_label}, {$tax_info})");
        } else {
            safe_echo("✗ 職能作成失敗: {$skill_name} - " . $wpdb->last_error);
        }
    }
}

// 5. 受注書データの作成（進捗バランス配分）
$order_names = $dummy_config['order_names'];
$progress_labels = $dummy_config['progress_labels'];
$orders_per_client = 6; // 進捗1〜6を各1件
$progress_totals = array_fill( 1, 6, 0 );

$order_ids = array();
foreach ( $client_ids as $client_id ) {
	$progress_sequence = ktp_dummy_build_balanced_progress_sequence( $orders_per_client );
	shuffle( $progress_sequence );
	$client_project_names = ktp_dummy_pick_unique_order_names( $order_names, $orders_per_client );

	foreach ( $progress_sequence as $order_index => $status ) {
		$project_name = $client_project_names[ $order_index ];
		++$progress_totals[ $status ];
        
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
            case 4: // 完了 - 過去（90-180日前）
                $days_ago = rand(90, 180);
                $delivery_days_from_now = rand(-60, 30);
                break;
            case 5: // 請求済 - 過去（120-200日前）
                $days_ago = rand(120, 200);
                $delivery_days_from_now = rand(-120, -30);
                break;
            case 6: // 入金済 - 過去（150-240日前）
                $days_ago = rand(150, 240);
                $delivery_days_from_now = rand(-180, -60);
                break;
            default:
                $days_ago = rand(1, 365);
                $delivery_days_from_now = rand(30, 180);
        }
        
        $order_date = date('Y-m-d', strtotime('-' . $days_ago . ' days'));
        $delivery_date = date('Y-m-d', strtotime($delivery_days_from_now . ' days'));
        
        // 完了済みの注文には完了日を設定
        $completion_date = null;
        if ($status == 4 || $status == 5 || $status == 6) {
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
        
        // ステータスラベル（progress と同期）
        
        // 作成日時を設定
        $created_time = $order_date . ' ' . sprintf('%02d:%02d:%02d', rand(9, 18), rand(0, 59), rand(0, 59));
        
        // 現在の日時を取得
        $current_datetime = current_time('mysql');
        
        // 受注番号を生成（重複しにくい値）
        $order_number = 'ORD-' . date( 'Ymd', strtotime( $order_date ) ) . '-' . sprintf( '%04d', count( $order_ids ) + 1 );
        
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
        
        // テーブル構造に合わせて存在するカラムのみ挿入
        $order_data = array(
            'order_number'           => $order_number,
            'client_id'              => $client_id,
            'project_name'           => $project_name,
            'order_date'             => $order_date,
            'desired_delivery_date'  => $delivery_date,
            'expected_delivery_date' => $delivery_date,
            'status'                 => $progress_labels[ $status ],
            'order_status'           => 'draft',
            'updated_at'             => $current_datetime,
            'time'                   => $order_timestamp,
            'customer_name'          => $customer_name,
            'user_name'              => $user_name,
            'search_field'           => $search_field,
            'progress'               => $status,
            'memo'                   => $dummy_config['order_memo'],
            'created_at'             => $created_time,
            'invoice_items'          => '',
            'cost_items'             => '',
        );

        if ( $completion_date !== null && $completion_date !== '' ) {
            $order_data['completion_date'] = $completion_date;
        }

        $order_id = safe_db_insert( $wpdb->prefix . 'ktp_order', $order_data );

        if ( $order_id === false ) {
            safe_echo( 'ERROR: 受注書作成に失敗しました: ' . $wpdb->last_error );
        } else {
            $order_ids[] = $order_id;

            // 受注書に請求項目を追加
            add_invoice_items_to_order( $order_id, $service_ids );

            // 受注書にコスト項目を追加
            add_cost_items_to_order( $order_id, $supplier_ids );

            $completion_info = $completion_date ? ", 完了日: {$completion_date}" : '';
            $customer_info   = $customer_name ? " (顧客: {$customer_name})" : ' (顧客情報なし)';
            safe_echo( "受注書作成: {$project_name}{$customer_info} (進捗: {$progress_labels[ $status ]}, 作成日: {$created_time}{$completion_info})" );
        }
    }
}

// 受注書データの作成完了

safe_echo("==========================================");
safe_echo("受注書の進捗内訳:");
for ( $p = 1; $p <= 6; $p++ ) {
	$label = isset( $progress_labels[ $p ] ) ? $progress_labels[ $p ] : (string) $p;
	safe_echo("  - {$label}: {$progress_totals[$p]}件");
}
safe_echo("強化版ダミーデータ作成が完了しました！");
safe_echo("バージョン: 2.6.2 (受注書INSERT互換修正版)");
safe_echo("作成されたデータ:");
safe_echo("- 顧客: " . count($client_ids) . "件");
safe_echo("- 協力会社: " . count($supplier_ids) . "件");
safe_echo("- サービス: " . count($service_ids) . "件");
safe_echo("- 受注書: " . count($order_ids) . "件");
safe_echo("- 職能: " . (count($supplier_ids) * 3) . "件");
safe_echo("");
safe_echo("詳細:");
safe_echo("- 顧客: 各社にユニークなデモ用メールアドレスを設定");
safe_echo("- 協力会社: 各社にユニークなデモ用メールアドレスを設定");
safe_echo("- 受注書: 顧客ごとに進捗1〜6を各1件ずつ作成（全体で進捗がバランスよく分布）");
safe_echo("- 納期設定: 進捗に応じて適切な納期を設定（受注以前は将来、完了以降は過去中心）");
safe_echo("- 完了日設定: 完了・請求済・入金済の案件には適切な完了日を設定");
safe_echo("- カテゴリー別税率: 食品8%、不動産非課税、その他10%");
safe_echo("- サービス: カテゴリー別に自動生成（テック、不動産、一般、ロジスティック、食品、金融）");
safe_echo("- 職能: 協力会社のカテゴリーに応じて適切な職能を生成");
safe_echo("- 各受注書に請求項目とコスト項目を自動追加");
safe_echo("");
safe_echo("");
safe_echo("修正内容（v2.6.2）:");
safe_echo("- 受注書 INSERT をテーブル構造に合わせて動的にカラムを選ぶよう修正（案件が作成されない不具合）");
safe_echo("");
safe_echo("修正内容（v2.6.1）:");
safe_echo("- 受注書（案件）を顧客ごとに進捗1〜6を各1件ずつ作成し、デモ画面で進捗がバランスよく見えるように改善");
safe_echo("");
safe_echo("修正内容（v2.6.0）:");
safe_echo("- WordPress が日本語ロケールのとき、日本語の会社名・担当者名・案件名などを豊富なバリエーションで生成");
safe_echo("- 進捗ラベルをアプリ仕様（受付中・見積中・受注・完了・請求済・入金済）に合わせて修正");
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
    global $wpdb, $dummy_config;
    
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
                        'remarks' => $dummy_config['remarks'],
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
    global $wpdb, $dummy_config;
    
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
                'remarks' => $dummy_config['remarks'],
                'purchase' => $dummy_config['purchase_label'],
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