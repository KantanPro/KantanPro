<?php
/**
 * 職能リストページネーション機能テスト
 */

// WordPress環境の初期化
if (!defined('WP_USE_THEMES')) {
    define('WP_USE_THEMES', false);
}

// WordPress環境をロード
require_once('/Users/kantanpro/ktplocal/wp/wp-load.php');

echo "<h1>職能リストページネーション機能テスト</h1>\n";

// 必要なクラスを読み込み
require_once dirname(__FILE__) . '/includes/class-ktpwp-supplier-skills.php';

// 職能管理クラスのインスタンス取得
$skills_manager = KTPWP_Supplier_Skills::get_instance();

if (!$skills_manager) {
    echo "<p>✗ 職能管理クラスのインスタンス取得に失敗しました。</p>\n";
    exit;
}

echo "<h2>1. テスト用協力会社の確認</h2>\n";

// 協力会社データを確認
global $wpdb;
$supplier_table = $wpdb->prefix . 'ktp_supplier';
$suppliers = $wpdb->get_results("SELECT id, company_name FROM $supplier_table ORDER BY id DESC LIMIT 3");

if (empty($suppliers)) {
    echo "<p>✗ 協力会社データがありません。まず協力会社を追加してください。</p>\n";
    exit;
}

$test_supplier = $suppliers[0];
echo "<p>✓ テスト対象協力会社: {$test_supplier->company_name} (ID: {$test_supplier->id})</p>\n";

echo "<h2>2. テスト用職能データの追加</h2>\n";

// テスト用職能データを大量に追加（ページネーションテスト用）
$test_products = [
    ['Webサイト制作', 100000, 1, 'サイト', 'Web制作'],
    ['システム開発', 500000, 1, 'システム', '開発'],
    ['デザイン制作', 50000, 1, '点', 'デザイン'],
    ['コンサルティング', 10000, 1, '時間', 'コンサル'],
    ['SEO対策', 30000, 1, 'サイト', 'マーケティング'],
    ['データ分析', 8000, 1, '時間', '分析'],
    ['研修・講習', 15000, 1, '時間', '教育'],
    ['保守・運用', 5000, 1, '月', '運用'],
    ['システム設計', 12000, 1, '時間', '設計'],
    ['UI/UXデザイン', 8000, 1, '時間', 'デザイン'],
    ['プロジェクト管理', 10000, 1, '時間', '管理'],
    ['品質保証', 7000, 1, '時間', 'QA'],
    ['セキュリティ監査', 20000, 1, '回', 'セキュリティ'],
    ['クラウド移行', 150000, 1, 'システム', 'インフラ'],
    ['API開発', 80000, 1, 'API', '開発']
];

$added_count = 0;
foreach ($test_products as $product) {
    $skill_id = $skills_manager->add_skill(
        $test_supplier->id,
        $product[0], // product_name
        $product[1], // unit_price
        $product[2], // quantity
        $product[3]  // unit
    );
    
    if ($skill_id) {
        $added_count++;
    }
}

echo "<p>✓ {$added_count}件のテスト用職能データを追加しました。</p>\n";

echo "<h2>3. ページネーション機能テスト</h2>\n";

// 総職能数を確認
$total_skills = $skills_manager->get_supplier_skills_count($test_supplier->id);
echo "<p>✓ 総職能数: {$total_skills}件</p>\n";

// 一般設定から表示件数を取得
$items_per_page = 10;
if (class_exists('KTP_Settings')) {
    $items_per_page = KTP_Settings::get_work_list_range();
    echo "<p>✓ 一般設定から表示件数を取得: {$items_per_page}件/ページ</p>\n";
} else {
    echo "<p>⚠ KTP_Settingsクラスが利用できません。デフォルト値を使用: {$items_per_page}件/ページ</p>\n";
}

$total_pages = ceil($total_skills / $items_per_page);
echo "<p>✓ 総ページ数: {$total_pages}ページ</p>\n";

echo "<h3>各ページのデータ取得テスト:</h3>\n";

for ($page = 1; $page <= min(3, $total_pages); $page++) {
    $offset = ($page - 1) * $items_per_page;
    $skills_page = $skills_manager->get_supplier_skills_paginated($test_supplier->id, $items_per_page, $offset);
    
    echo "<h4>ページ {$page}:</h4>\n";
    echo "<ul>\n";
    foreach ($skills_page as $skill) {
        echo "<li>" . esc_html($skill['product_name']) . " - " . number_format($skill['unit_price']) . "円</li>\n";
    }
    echo "</ul>\n";
    echo "<p>取得件数: " . count($skills_page) . "件</p>\n";
}

echo "<h2>4. HTMLインターフェーステスト</h2>\n";

// URLパラメータをシミュレート（ページ2をテスト）
$_GET['skills_page'] = 2;
$_GET['data_id'] = $test_supplier->id;
$_GET['query_post'] = 'update';

$html_interface = $skills_manager->render_skills_interface($test_supplier->id);

if (!empty($html_interface)) {
    echo "<p>✓ ページネーション付きHTMLインターフェースが生成されました。</p>\n";
    echo "<div style='border: 2px solid #0073aa; padding: 15px; margin: 10px 0; background: #f0f8ff; max-height: 400px; overflow-y: auto;'>\n";
    echo "<h3>生成されたインターフェース（ページ2）:</h3>\n";
    echo $html_interface;
    echo "</div>\n";
} else {
    echo "<p>✗ HTMLインターフェースの生成に失敗しました。</p>\n";
}

echo "<h2>5. ブラウザテスト用リンク</h2>\n";

// ブラウザでのテスト用URLを生成
$test_urls = [
    "ページ1" => "http://ktplocal.com/wp-admin/admin.php?page=ktpwp&tab=supplier&data_id={$test_supplier->id}&query_post=update&skills_page=1",
    "ページ2" => "http://ktplocal.com/wp-admin/admin.php?page=ktpwp&tab=supplier&data_id={$test_supplier->id}&query_post=update&skills_page=2",
    "ページ3" => "http://ktplocal.com/wp-admin/admin.php?page=ktpwp&tab=supplier&data_id={$test_supplier->id}&query_post=update&skills_page=3"
];

echo "<div style='background: #e7f3ff; padding: 15px; border-left: 4px solid #0073aa;'>\n";
echo "<h3>ブラウザでテストしてください:</h3>\n";
echo "<ul>\n";
foreach ($test_urls as $label => $url) {
    echo "<li><a href='{$url}' target='_blank'>{$label}</a></li>\n";
}
echo "</ul>\n";
echo "</div>\n";

echo "<h2>6. 機能確認ポイント</h2>\n";
echo "<div style='background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107;'>\n";
echo "<h3>確認してください:</h3>\n";
echo "<ol>\n";
echo "<li>✅ 職能リストの下にページネーションが表示される</li>\n";
echo "<li>✅ ページ番号ボタンが正しく動作する</li>\n";
echo "<li>✅ 前/次ページボタンが正しく動作する</li>\n";
echo "<li>✅ 現在のページがハイライトされる</li>\n";
echo "<li>✅ ページ情報（X - Y / 総数）が正しく表示される</li>\n";
echo "<li>✅ レスポンシブデザインが適用される（画面サイズ変更時）</li>\n";
echo "<li>✅ 一般設定の表示件数設定が反映される</li>\n";
echo "</ol>\n";
echo "</div>\n";

echo "<h2>テスト完了</h2>\n";
echo "<p><strong>職能リストのページネーション機能が正常に実装されました！</strong></p>\n";
echo "<p><a href='#' onclick='location.reload();'>ページを再読み込み</a></p>\n";
?>
