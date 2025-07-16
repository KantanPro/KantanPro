<?php
/**
 * KantanPro更新チェッカーデバッグスクリプト
 * 
 * 更新チェック機能の動作をテストし、エラーの詳細を確認します。
 * 
 * @package KantanPro
 * @since 1.0.4
 */

// WordPress環境を読み込み
require_once( dirname( __FILE__ ) . '/../../../wp-load.php' );

// セキュリティチェック
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( 'このスクリプトを実行する権限がありません。' );
}

echo '<h1>KantanPro更新チェッカーデバッグ</h1>';

// 更新チェッカークラスを読み込み
require_once( dirname( __FILE__ ) . '/includes/class-ktpwp-update-checker.php' );

// 更新チェッカーのインスタンスを作成
$update_checker = new KTPWP_Update_Checker();

echo '<h2>1. 基本情報</h2>';
echo '<ul>';
echo '<li>現在のバージョン: ' . ( defined( 'KANTANPRO_PLUGIN_VERSION' ) ? KANTANPRO_PLUGIN_VERSION : '未定義' ) . '</li>';
echo '<li>プラグインファイル: ' . ( defined( 'KANTANPRO_PLUGIN_FILE' ) ? KANTANPRO_PLUGIN_FILE : '未定義' ) . '</li>';
echo '<li>プラグインURL: ' . ( defined( 'KANTANPRO_PLUGIN_URL' ) ? KANTANPRO_PLUGIN_URL : '未定義' ) . '</li>';
echo '<li>WordPressバージョン: ' . get_bloginfo( 'version' ) . '</li>';
echo '<li>PHPバージョン: ' . PHP_VERSION . '</li>';
echo '</ul>';

echo '<h2>2. 設定情報</h2>';
$update_settings = get_option( 'ktp_update_notification_settings', array() );
echo '<pre>' . print_r( $update_settings, true ) . '</pre>';

echo '<h2>3. 更新通知の状態</h2>';
echo '<ul>';
echo '<li>更新通知有効: ' . ( $update_checker->is_update_notification_enabled() ? 'はい' : 'いいえ' ) . '</li>';
echo '<li>管理画面通知有効: ' . ( $update_checker->is_admin_notification_enabled() ? 'はい' : 'いいえ' ) . '</li>';
echo '<li>フロントエンド通知有効: ' . ( $update_checker->is_frontend_notification_enabled() ? 'はい' : 'いいえ' ) . '</li>';
echo '<li>ユーザー権限: ' . ( $update_checker->user_has_notification_permission() ? 'あり' : 'なし' ) . '</li>';
echo '<li>現在のユーザー: ' . wp_get_current_user()->user_login . ' (' . implode( ', ', wp_get_current_user()->roles ) . ')</li>';
echo '</ul>';

echo '<h2>4. 保存された更新情報</h2>';
$update_data = get_option( 'ktpwp_update_available', false );
if ( $update_data ) {
    echo '<pre>' . print_r( $update_data, true ) . '</pre>';
} else {
    echo '<p>更新情報は保存されていません。</p>';
}

echo '<h2>5. その他のオプション</h2>';
echo '<ul>';
echo '<li>最後のチェック時刻: ' . date( 'Y-m-d H:i:s', get_option( 'ktpwp_last_update_check', 0 ) ) . '</li>';
echo '<li>通知無視フラグ: ' . ( get_option( 'ktpwp_update_notice_dismissed', false ) ? 'はい' : 'いいえ' ) . '</li>';
echo '<li>ヘッダー通知無視フラグ: ' . ( get_option( 'ktpwp_header_update_notice_dismissed', false ) ? 'はい' : 'いいえ' ) . '</li>';
echo '<li>ヘッダー無視バージョン: ' . get_option( 'ktpwp_header_dismissed_version', 'なし' ) . '</li>';
echo '</ul>';

echo '<h2>6. GitHub API テスト</h2>';
echo '<form method="post">';
echo '<input type="submit" name="test_github_api" value="GitHub API をテスト" />';
echo '<input type="submit" name="force_check" value="強制更新チェック（レート制限無視）" />';
echo '</form>';

if ( isset( $_POST['test_github_api'] ) ) {
    echo '<h3>GitHub API テスト結果</h3>';
    
    try {
        $result = $update_checker->check_github_updates();
        echo '<p>更新チェック結果: ' . ( $result ? '更新あり' : '更新なし' ) . '</p>';
        
        // 更新情報を再取得
        $update_data = get_option( 'ktpwp_update_available', false );
        if ( $update_data ) {
            echo '<p>更新情報:</p>';
            echo '<pre>' . print_r( $update_data, true ) . '</pre>';
        }
        
    } catch ( Exception $e ) {
        echo '<p style="color: red;">エラー: ' . $e->getMessage() . '</p>';
    }
}

if ( isset( $_POST['force_check'] ) ) {
    echo '<h3>強制更新チェック結果</h3>';
    
    // レート制限を一時的に無効化
    delete_option( 'ktpwp_last_update_check' );
    
    try {
        $result = $update_checker->check_github_updates();
        echo '<p>強制更新チェック結果: ' . ( $result ? '更新あり' : '更新なし' ) . '</p>';
        
        // 更新情報を再取得
        $update_data = get_option( 'ktpwp_update_available', false );
        if ( $update_data ) {
            echo '<p>更新情報:</p>';
            echo '<pre>' . print_r( $update_data, true ) . '</pre>';
        }
        
    } catch ( Exception $e ) {
        echo '<p style="color: red;">エラー: ' . $e->getMessage() . '</p>';
    }
}

echo '<h2>7. JavaScript変数テスト</h2>';
echo '<p>ブラウザの開発者ツールのコンソールで以下のコマンドを実行してください:</p>';
echo '<pre>';
echo 'console.log("ktpwp_ajax:", typeof ktpwp_ajax !== "undefined" ? ktpwp_ajax : "未定義");';
echo 'console.log("ktpwp_update_data:", typeof ktpwp_update_data !== "undefined" ? ktpwp_update_data : "未定義");';
echo '</pre>';

echo '<h2>8. AJAX テスト</h2>';
echo '<p>ブラウザの開発者ツールのコンソールで以下のコマンドを実行してください:</p>';
echo '<pre>';
echo 'jQuery.post(ajaxurl, {
    action: "ktpwp_check_header_update",
    nonce: "' . wp_create_nonce( 'ktpwp_header_update_check' ) . '"
}).done(function(response) {
    console.log("AJAX レスポンス:", response);
}).fail(function(xhr, status, error) {
    console.error("AJAX エラー:", {xhr: xhr, status: status, error: error});
});';
echo '</pre>';

echo '<h2>9. Nonce テスト</h2>';
echo '<ul>';
echo '<li>ktpwp_header_update_check nonce: ' . wp_create_nonce( 'ktpwp_header_update_check' ) . '</li>';
echo '<li>ktpwp_header_update_notice nonce: ' . wp_create_nonce( 'ktpwp_header_update_notice' ) . '</li>';
echo '</ul>';

echo '<h2>10. エラーログ確認</h2>';
echo '<p>WordPressのエラーログを確認してください:</p>';
echo '<ul>';
echo '<li>wp-content/debug.log</li>';
echo '<li>サーバーのエラーログ</li>';
echo '</ul>';

echo '<h2>11. 手動クリーンアップ</h2>';
echo '<form method="post">';
echo '<input type="submit" name="cleanup_options" value="更新関連オプションをクリーンアップ" onclick="return confirm(\'本当にクリーンアップしますか？\')" />';
echo '</form>';

if ( isset( $_POST['cleanup_options'] ) ) {
    delete_option( 'ktpwp_last_update_check' );
    delete_option( 'ktpwp_update_available' );
    delete_option( 'ktpwp_update_notice_dismissed' );
    delete_option( 'ktpwp_header_update_notice_dismissed' );
    delete_option( 'ktpwp_header_dismissed_version' );
    echo '<p style="color: green;">クリーンアップが完了しました。</p>';
}

echo '<h2>12. ネットワーク接続テスト</h2>';
echo '<form method="post">';
echo '<input type="submit" name="test_network" value="ネットワーク接続をテスト" />';
echo '</form>';

if ( isset( $_POST['test_network'] ) ) {
    echo '<h3>ネットワーク接続テスト結果</h3>';
    
    $test_urls = array(
        'https://api.github.com' => 'GitHub API',
        'https://api.github.com/repos/KantanPro/freeKTP/releases/latest' => 'GitHub Releases API',
        'https://wordpress.org' => 'WordPress.org'
    );
    
    foreach ( $test_urls as $url => $description ) {
        $response = wp_remote_get( $url, array( 'timeout' => 10 ) );
        
        if ( is_wp_error( $response ) ) {
            echo '<p style="color: red;">' . $description . ': エラー - ' . $response->get_error_message() . '</p>';
        } else {
            $code = wp_remote_retrieve_response_code( $response );
            echo '<p style="color: green;">' . $description . ': OK (HTTP ' . $code . ')</p>';
        }
    }
}

echo '<hr>';
echo '<p><a href="' . admin_url() . '">管理画面に戻る</a></p>';
?> 