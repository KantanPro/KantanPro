<?php
/**
 * WordPress.org接続問題診断スクリプト
 * 
 * このスクリプトは、WordPress.orgとの接続問題を診断し、
 * KantanProプラグインの更新チェック機能の状態を確認します。
 * 
 * 使用方法: ブラウザで直接アクセス
 * 
 * @package KantanPro
 * @since 1.1.1
 */

// WordPress環境を読み込み
require_once( dirname( __FILE__ ) . '/../../../wp-load.php' );

// セキュリティチェック
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( 'このページにアクセスする権限がありません。' );
}

// ヘッダー出力
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>KantanPro - WordPress.org接続診断</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .success { background-color: #d4edda; border-color: #c3e6cb; }
        .error { background-color: #f8d7da; border-color: #f5c6cb; }
        .warning { background-color: #fff3cd; border-color: #ffeaa7; }
        .info { background-color: #d1ecf1; border-color: #bee5eb; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 3px; overflow-x: auto; }
        .test-button { 
            background: #0073aa; color: white; padding: 10px 20px; 
            border: none; border-radius: 3px; cursor: pointer; margin: 5px;
        }
        .test-button:hover { background: #005a87; }
    </style>
</head>
<body>
    <h1>KantanPro - WordPress.org接続診断</h1>
    
    <div class="section info">
        <h2>診断概要</h2>
        <p>このページでは、WordPress.orgとの接続問題とKantanProプラグインの更新チェック機能の状態を診断します。</p>
        <p><strong>診断日時:</strong> <?php echo current_time( 'Y-m-d H:i:s' ); ?></p>
    </div>

    <?php
    // 1. WordPress.org接続テスト
    echo '<div class="section">';
    echo '<h2>1. WordPress.org接続テスト</h2>';
    
    $wp_api_url = 'https://api.wordpress.org/plugins/update-check/1.1/';
    $test_response = wp_remote_post( $wp_api_url, array(
        'timeout' => 30,
        'body' => json_encode( array(
            'plugins' => array(
                'kantanpro/ktpwp.php' => array(
                    'Version' => '1.1.1'
                )
            )
        ) ),
        'headers' => array(
            'Content-Type' => 'application/json'
        )
    ) );
    
    if ( is_wp_error( $test_response ) ) {
        echo '<div class="error">';
        echo '<h3>❌ WordPress.org接続エラー</h3>';
        echo '<p><strong>エラー:</strong> ' . esc_html( $test_response->get_error_message() ) . '</p>';
        echo '<p><strong>エラーコード:</strong> ' . esc_html( $test_response->get_error_code() ) . '</p>';
        echo '</div>';
    } else {
        $response_code = wp_remote_retrieve_response_code( $test_response );
        if ( $response_code === 200 ) {
            echo '<div class="success">';
            echo '<h3>✅ WordPress.org接続成功</h3>';
            echo '<p><strong>レスポンスコード:</strong> ' . esc_html( $response_code ) . '</p>';
            echo '</div>';
        } else {
            echo '<div class="warning">';
            echo '<h3>⚠️ WordPress.org接続警告</h3>';
            echo '<p><strong>レスポンスコード:</strong> ' . esc_html( $response_code ) . '</p>';
            echo '<p><strong>レスポンス:</strong> ' . esc_html( wp_remote_retrieve_body( $test_response ) ) . '</p>';
            echo '</div>';
        }
    }
    echo '</div>';

    // 2. KantanPro更新チェッカー状態
    echo '<div class="section">';
    echo '<h2>2. KantanPro更新チェッカー状態</h2>';
    
    if ( class_exists( 'KTPWP_Update_Checker' ) ) {
        echo '<div class="success">';
        echo '<h3>✅ 更新チェッカークラス利用可能</h3>';
        
        $update_checker = new KTPWP_Update_Checker();
        
        echo '<p><strong>更新通知有効:</strong> ' . ( $update_checker->is_update_notification_enabled() ? 'はい' : 'いいえ' ) . '</p>';
        echo '<p><strong>管理画面通知有効:</strong> ' . ( $update_checker->is_admin_notification_enabled() ? 'はい' : 'いいえ' ) . '</p>';
        echo '<p><strong>フロントエンド通知有効:</strong> ' . ( $update_checker->is_frontend_notification_enabled() ? 'はい' : 'いいえ' ) . '</p>';
        echo '<p><strong>ユーザー権限:</strong> ' . ( $update_checker->user_has_notification_permission() ? 'あり' : 'なし' ) . '</p>';
        
        echo '</div>';
        
        // 手動テストボタン
        echo '<form method="post" style="margin-top: 15px;">';
        echo '<input type="hidden" name="test_github_update" value="1">';
        echo '<button type="submit" class="test-button">GitHub更新チェックを手動実行</button>';
        echo '</form>';
        
    } else {
        echo '<div class="error">';
        echo '<h3>❌ 更新チェッカークラスが見つかりません</h3>';
        echo '<p>KTPWP_Update_Checkerクラスが読み込まれていません。</p>';
        echo '</div>';
    }
    echo '</div>';

    // 3. 手動GitHub更新チェック
    if ( isset( $_POST['test_github_update'] ) && class_exists( 'KTPWP_Update_Checker' ) ) {
        echo '<div class="section">';
        echo '<h2>3. GitHub更新チェック結果</h2>';
        
        $update_checker = new KTPWP_Update_Checker();
        $result = $update_checker->check_github_updates();
        
        if ( $result ) {
            echo '<div class="success">';
            echo '<h3>✅ GitHub更新チェック成功</h3>';
            echo '<p><strong>最新バージョン:</strong> ' . esc_html( $result['version'] ) . '</p>';
            echo '<p><strong>ダウンロードURL:</strong> ' . esc_html( $result['download_url'] ) . '</p>';
            if ( ! empty( $result['changelog'] ) ) {
                echo '<p><strong>変更履歴:</strong></p>';
                echo '<pre>' . esc_html( $result['changelog'] ) . '</pre>';
            }
            echo '</div>';
        } else {
            echo '<div class="warning">';
            echo '<h3>⚠️ GitHub更新チェック結果</h3>';
            echo '<p>更新は利用できません（最新版またはエラー）</p>';
            echo '</div>';
        }
        echo '</div>';
    }

    // 4. システム情報
    echo '<div class="section">';
    echo '<h2>4. システム情報</h2>';
    
    echo '<p><strong>WordPress バージョン:</strong> ' . esc_html( get_bloginfo( 'version' ) ) . '</p>';
    echo '<p><strong>PHP バージョン:</strong> ' . esc_html( PHP_VERSION ) . '</p>';
    echo '<p><strong>サーバー:</strong> ' . esc_html( $_SERVER['SERVER_SOFTWARE'] ?? '不明' ) . '</p>';
    echo '<p><strong>cURL 利用可能:</strong> ' . ( function_exists( 'curl_init' ) ? 'はい' : 'いいえ' ) . '</p>';
    echo '<p><strong>allow_url_fopen:</strong> ' . ( ini_get( 'allow_url_fopen' ) ? '有効' : '無効' ) . '</p>';
    echo '<p><strong>タイムアウト設定:</strong> ' . esc_html( ini_get( 'default_socket_timeout' ) ) . '秒</p>';
    
    echo '</div>';

    // 5. 推奨解決策
    echo '<div class="section info">';
    echo '<h2>5. 推奨解決策</h2>';
    
    echo '<h3>WordPress.org接続エラーが発生する場合:</h3>';
    echo '<ul>';
    echo '<li><strong>サーバー設定の確認:</strong> ファイアウォールやプロキシ設定を確認</li>';
    echo '<li><strong>DNS設定の確認:</strong> wordpress.orgへの名前解決が正常か確認</li>';
    echo '<li><strong>SSL証明書の確認:</strong> サーバーのSSL証明書が有効か確認</li>';
    echo '<li><strong>PHP設定の確認:</strong> allow_url_fopenが有効になっているか確認</li>';
    echo '<li><strong>タイムアウト設定の調整:</strong> 必要に応じてタイムアウト値を増加</li>';
    echo '</ul>';
    
    echo '<h3>KantanPro更新チェック機能について:</h3>';
    echo '<ul>';
    echo '<li>このプラグインは独自の更新チェック機能を使用しています</li>';
    echo '<li>WordPress.orgとの接続エラーは、このプラグインの動作に影響しません</li>';
    echo '<li>GitHubからの更新チェックは正常に動作します</li>';
    echo '</ul>';
    
    echo '</div>';
    ?>

    <div class="section">
        <h2>6. ログ確認</h2>
        <p>詳細なエラー情報は、WordPressのデバッグログを確認してください。</p>
        <p><strong>デバッグログの場所:</strong> <?php echo WP_CONTENT_DIR; ?>/debug.log</p>
        <p><strong>KantanPro関連のログ:</strong> "KantanPro:" で始まるメッセージを検索してください。</p>
    </div>

    <div style="margin-top: 30px; text-align: center;">
        <a href="<?php echo admin_url( 'plugins.php' ); ?>" class="test-button">プラグイン一覧に戻る</a>
    </div>

</body>
</html> 