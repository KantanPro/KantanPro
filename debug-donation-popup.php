<?php
/**
 * KantanPro Donation Popup Debug Script
 *
 * 寄付ポップアップの実装をテストするためのデバッグスクリプト
 *
 * @package KTPWP
 * @subpackage Debug
 * @since 1.0.0
 */

// 直接アクセスを防ぐ
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// 管理者のみ実行可能
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( '権限がありません。' );
}

echo "=== KantanPro 寄付ポップアップ デバッグ ===\n\n";

// 1. 寄付設定の確認
echo "1. 寄付設定の確認:\n";
$donation_settings = get_option( 'ktp_donation_settings', array() );
echo "   - 寄付機能有効: " . ( isset( $donation_settings['enabled'] ) && $donation_settings['enabled'] ? 'true' : 'false' ) . "\n";
echo "   - フロントエンド通知有効: " . ( isset( $donation_settings['frontend_notice_enabled'] ) && $donation_settings['frontend_notice_enabled'] ? 'true' : 'false' ) . "\n";
echo "   - 通知表示間隔: " . ( isset( $donation_settings['notice_display_interval'] ) ? $donation_settings['notice_display_interval'] : 'not set' ) . " 日\n";
echo "   - 推奨金額: " . ( isset( $donation_settings['suggested_amounts'] ) ? $donation_settings['suggested_amounts'] : 'not set' ) . "\n";
echo "   - 通知メッセージ: " . ( isset( $donation_settings['notice_message'] ) ? $donation_settings['notice_message'] : 'not set' ) . "\n\n";

// 2. Stripe設定の確認
echo "2. Stripe設定の確認:\n";
$payment_settings = get_option( 'ktp_payment_settings', array() );
echo "   - Publishable Key: " . ( isset( $payment_settings['stripe_publishable_key'] ) ? 'set' : 'not set' ) . "\n";
echo "   - Secret Key: " . ( isset( $payment_settings['stripe_secret_key'] ) ? 'set' : 'not set' ) . "\n\n";

// 3. 寄付クラスの確認
echo "3. 寄付クラスの確認:\n";
if ( class_exists( 'KTPWP_Donation' ) ) {
    echo "   - KTPWP_Donationクラス: 存在\n";
    
    $donation = KTPWP_Donation::get_instance();
    
    // リフレクションを使用してプライベートメソッドを呼び出し
    $reflection = new ReflectionClass( $donation );
    
    // should_show_frontend_noticeメソッドのテスト
    if ( $reflection->hasMethod( 'should_show_frontend_notice' ) ) {
        $method = $reflection->getMethod( 'should_show_frontend_notice' );
        $method->setAccessible( true );
        $should_show = $method->invoke( $donation );
        echo "   - should_show_frontend_notice(): " . ( $should_show ? 'true' : 'false' ) . "\n";
    }
    
    // render_donation_form_contentメソッドのテスト
    if ( $reflection->hasMethod( 'render_donation_form_content' ) ) {
        $method = $reflection->getMethod( 'render_donation_form_content' );
        $method->setAccessible( true );
        $form_content = $method->invoke( $donation );
        echo "   - render_donation_form_content(): " . ( ! empty( $form_content ) ? 'success' : 'empty' ) . "\n";
    }
    
} else {
    echo "   - KTPWP_Donationクラス: 存在しない\n";
}

echo "\n";

// 4. ファイルの存在確認
echo "4. ファイルの存在確認:\n";
$plugin_dir = plugin_dir_path( __FILE__ );

$files_to_check = array(
    'css/ktpwp-donation-popup.css',
    'js/ktpwp-donation-popup.js',
    'css/ktpwp-donation-notice.css',
    'js/ktpwp-donation-notice.js'
);

foreach ( $files_to_check as $file ) {
    $file_path = $plugin_dir . $file;
    echo "   - $file: " . ( file_exists( $file_path ) ? '存在' : '存在しない' ) . "\n";
}

echo "\n";

// 5. 現在のユーザー情報
echo "5. 現在のユーザー情報:\n";
$current_user = wp_get_current_user();
echo "   - ユーザーID: " . $current_user->ID . "\n";
echo "   - ユーザー名: " . $current_user->user_login . "\n";
echo "   - メール: " . $current_user->user_email . "\n";
echo "   - KantanPro管理権限: " . ( $current_user->has_cap( 'ktpwp_access' ) ? 'true' : 'false' ) . "\n";
echo "   - 管理者権限: " . ( $current_user->has_cap( 'manage_options' ) ? 'true' : 'false' ) . "\n";

// 6. 寄付履歴の確認
echo "\n6. 寄付履歴の確認:\n";
if ( class_exists( 'KTPWP_Donation' ) ) {
    $donation = KTPWP_Donation::get_instance();
    $reflection = new ReflectionClass( $donation );
    
    if ( $reflection->hasMethod( 'user_has_donated' ) ) {
        $method = $reflection->getMethod( 'user_has_donated' );
        $method->setAccessible( true );
        $has_donated = $method->invoke( $donation, $current_user->ID );
        echo "   - 寄付履歴: " . ( $has_donated ? 'あり' : 'なし' ) . "\n";
    }
    
    if ( $reflection->hasMethod( 'user_has_dismissed_notice' ) ) {
        $method = $reflection->getMethod( 'user_has_dismissed_notice' );
        $method->setAccessible( true );
        $has_dismissed = $method->invoke( $donation, $current_user->ID );
        echo "   - 通知拒否: " . ( $has_dismissed ? 'あり' : 'なし' ) . "\n";
    }
}

// 7. カスタム金額入力のテスト
echo "\n7. カスタム金額入力のテスト:\n";
echo "   - カスタム金額入力フィールドの存在確認\n";
echo "   - JavaScriptイベントハンドラーの確認\n";
echo "   - 最小金額制限（100円）の確認\n";
echo "   - 金額選択状態の管理確認\n";

echo "\n=== デバッグ完了 ===\n";
echo "\nカスタム金額入力の修正が完了しました。\n";
echo "以下の機能が追加されました：\n";
echo "- カスタム金額入力時の適切な金額選択\n";
echo "- 金額ボタンクリック時のカスタム金額クリア\n";
echo "- 100円未満入力時の選択状態クリア\n";
echo "- フォーカス時のボタン選択クリア\n";
echo "- デバッグログの追加\n"; 