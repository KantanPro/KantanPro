<?php
/**
 * 寄付通知デバッグスクリプト
 */

// WordPressを読み込み
require_once 'wp-config.php';
require_once 'wp-load.php';

echo "=== 寄付通知デバッグ情報 ===\n\n";

// 寄付設定を確認
$donation_settings = get_option( 'ktp_donation_settings', array() );
echo "寄付設定:\n";
echo "- enabled: " . ( isset( $donation_settings['enabled'] ) ? $donation_settings['enabled'] : 'not set' ) . "\n";
echo "- frontend_notice_enabled: " . ( isset( $donation_settings['frontend_notice_enabled'] ) ? $donation_settings['frontend_notice_enabled'] : 'not set' ) . "\n";
echo "- notice_display_interval: " . ( isset( $donation_settings['notice_display_interval'] ) ? $donation_settings['notice_display_interval'] : 'not set' ) . "\n";
echo "- notice_message: " . ( isset( $donation_settings['notice_message'] ) ? $donation_settings['notice_message'] : 'not set' ) . "\n\n";

// 決済設定を確認
$payment_settings = get_option( 'ktp_payment_settings', array() );
echo "決済設定:\n";
echo "- stripe_publishable_key: " . ( isset( $payment_settings['stripe_publishable_key'] ) ? 'set' : 'not set' ) . "\n";
echo "- stripe_secret_key: " . ( isset( $payment_settings['stripe_secret_key'] ) ? 'set' : 'not set' ) . "\n\n";

// 現在のユーザー情報を確認
if ( is_user_logged_in() ) {
    $user = wp_get_current_user();
    echo "現在のユーザー:\n";
    echo "- ID: " . $user->ID . "\n";
    echo "- Login: " . $user->user_login . "\n";
    echo "- Email: " . $user->user_email . "\n";
    echo "- has ktpwp_access: " . ( $user->has_cap( 'ktpwp_access' ) ? 'true' : 'false' ) . "\n";
    echo "- has manage_options: " . ( $user->has_cap( 'manage_options' ) ? 'true' : 'false' ) . "\n\n";
} else {
    echo "ユーザーがログインしていません\n\n";
}

// 寄付通知クラスのインスタンスを取得
if ( class_exists( 'KTPWP_Donation' ) ) {
    $donation = KTPWP_Donation::get_instance();
    
    // リフレクションを使用してプライベートメソッドを呼び出し
    $reflection = new ReflectionClass( $donation );
    $method = $reflection->getMethod( 'should_show_frontend_notice' );
    $method->setAccessible( true );
    
    $should_show = $method->invoke( $donation );
    echo "should_show_frontend_notice(): " . ( $should_show ? 'true' : 'false' ) . "\n\n";
} else {
    echo "KTPWP_Donationクラスが見つかりません\n\n";
}

echo "=== デバッグ完了 ===\n"; 