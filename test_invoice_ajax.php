<?php
/**
 * 請求書発行AJAXハンドラーのテスト
 */

// WordPress環境を読み込み
require_once('wp-config.php');

// テスト用のnonceを作成
$nonce = wp_create_nonce('ktp_ajax_nonce');

echo "=== 請求書発行AJAXハンドラーテスト ===\n";
echo "Nonce: " . $nonce . "\n";
echo "Nonce検証結果: " . (wp_verify_nonce($nonce, 'ktp_ajax_nonce') ? 'OK' : 'NG') . "\n\n";

// テスト用のPOSTデータを設定
$_POST['action'] = 'ktp_get_invoice_candidates';
$_POST['client_id'] = '1'; // テスト用の顧客ID
$_POST['nonce'] = $nonce;

echo "POSTデータ:\n";
echo "- action: " . $_POST['action'] . "\n";
echo "- client_id: " . $_POST['client_id'] . "\n";
echo "- nonce: " . $_POST['nonce'] . "\n\n";

// AJAXハンドラークラスを読み込み
require_once('wp-content/plugins/KantanPro/includes/class-ktpwp-ajax.php');

// インスタンスを作成
$ajax_handler = KTPWP_Ajax::get_instance();

// メソッドが存在するかチェック
if (method_exists($ajax_handler, 'ajax_get_invoice_candidates')) {
    echo "✓ ajax_get_invoice_candidates メソッドが見つかりました\n";
    
    // メソッドを実行（実際の実行はWordPressのAJAXシステムが必要なため、リフレクションでテスト）
    $reflection = new ReflectionMethod($ajax_handler, 'ajax_get_invoice_candidates');
    echo "✓ メソッドの実行が可能です\n";
    echo "- パラメータ数: " . $reflection->getNumberOfParameters() . "\n";
    echo "- 戻り値の型: " . ($reflection->getReturnType() ? $reflection->getReturnType()->getName() : 'void') . "\n";
} else {
    echo "✗ ajax_get_invoice_candidates メソッドが見つかりません\n";
}

echo "\n=== テスト完了 ===\n"; 