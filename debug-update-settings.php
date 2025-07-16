<?php
/**
 * 更新通知設定デバッグスクリプト
 * 
 * 現在の更新通知設定の状況を確認します。
 */

// WordPressの読み込み
require_once( dirname( __FILE__ ) . '/../../../wp-load.php' );

// 管理者権限チェック
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( 'このページにアクセスする権限がありません。' );
}

echo '<h1>更新通知設定デバッグ</h1>';

// 1. 現在の設定値を確認
echo '<h2>1. 現在の設定値</h2>';
$update_settings = get_option( 'ktp_update_notification_settings', array() );
echo '<pre>';
print_r( $update_settings );
echo '</pre>';

// 2. 設定のデフォルト値
echo '<h2>2. 期待されるデフォルト値</h2>';
$expected_defaults = array(
    'enable_notifications' => true,
    'enable_admin_notifications' => true,
    'enable_frontend_notifications' => true,
    'check_interval' => 24,
    'notification_roles' => array( 'administrator' )
);
echo '<pre>';
print_r( $expected_defaults );
echo '</pre>';

// 3. 設定の比較
echo '<h2>3. 設定の比較</h2>';
foreach ( $expected_defaults as $key => $expected_value ) {
    $current_value = isset( $update_settings[ $key ] ) ? $update_settings[ $key ] : 'NOT_SET';
    $status = ( $current_value === $expected_value ) ? '✅ 一致' : '❌ 不一致';
    echo "<p><strong>{$key}:</strong> 現在値 = " . var_export( $current_value, true ) . " | 期待値 = " . var_export( $expected_value, true ) . " | {$status}</p>";
}

// 4. 更新チェッカークラスの動作確認
echo '<h2>4. 更新チェッカークラスの動作確認</h2>';
if ( class_exists( 'KTPWP_Update_Checker' ) ) {
    $update_checker = new KTPWP_Update_Checker();
    
    echo '<p><strong>更新通知が有効:</strong> ' . ( $update_checker->is_update_notification_enabled() ? '✅ はい' : '❌ いいえ' ) . '</p>';
    echo '<p><strong>管理画面通知が有効:</strong> ' . ( $update_checker->is_admin_notification_enabled() ? '✅ はい' : '❌ いいえ' ) . '</p>';
    echo '<p><strong>フロントエンド通知が有効:</strong> ' . ( $update_checker->is_frontend_notification_enabled() ? '✅ はい' : '❌ いいえ' ) . '</p>';
    echo '<p><strong>ユーザーが通知権限を持つ:</strong> ' . ( $update_checker->user_has_notification_permission() ? '✅ はい' : '❌ いいえ' ) . '</p>';
} else {
    echo '<p>❌ 更新チェッカークラスが見つかりません</p>';
}

// 5. 設定の強制初期化
echo '<h2>5. 設定の強制初期化</h2>';
if ( isset( $_POST['force_init'] ) ) {
    $force_settings = array(
        'enable_notifications' => true,
        'enable_admin_notifications' => true,
        'enable_frontend_notifications' => true,
        'check_interval' => 24,
        'notification_roles' => array( 'administrator' )
    );
    
    update_option( 'ktp_update_notification_settings', $force_settings );
    echo '<p>✅ 設定を強制的に初期化しました。</p>';
    echo '<p><a href="' . esc_url( $_SERVER['REQUEST_URI'] ) . '">ページを再読み込み</a></p>';
} else {
    echo '<form method="post">';
    echo '<p>設定が正しくない場合は、以下のボタンで強制的に初期化できます：</p>';
    echo '<input type="submit" name="force_init" value="設定を強制初期化" class="button button-primary">';
    echo '</form>';
}

// 6. 設定ページへのリンク
echo '<h2>6. 設定ページへのリンク</h2>';
echo '<p><a href="' . admin_url( 'admin.php?page=ktp-developer-settings&tab=updates' ) . '" target="_blank">開発者設定 → 更新通知設定</a></p>';

// 7. 現在のユーザー情報
echo '<h2>7. 現在のユーザー情報</h2>';
$current_user = wp_get_current_user();
echo '<p><strong>ユーザーID:</strong> ' . $current_user->ID . '</p>';
echo '<p><strong>ユーザー名:</strong> ' . $current_user->user_login . '</p>';
echo '<p><strong>表示名:</strong> ' . $current_user->display_name . '</p>';
echo '<p><strong>権限:</strong> ' . implode( ', ', $current_user->roles ) . '</p>';

// 8. 権限チェック
echo '<h2>8. 権限チェック</h2>';
$roles_to_check = array( 'administrator', 'editor', 'author', 'contributor', 'subscriber' );
foreach ( $roles_to_check as $role ) {
    $has_role = current_user_can( $role );
    echo '<p><strong>' . $role . ':</strong> ' . ( $has_role ? '✅ はい' : '❌ いいえ' ) . '</p>';
}

echo '<hr>';
echo '<p><small>このデバッグページは管理者のみアクセス可能です。</small></p>';
?> 