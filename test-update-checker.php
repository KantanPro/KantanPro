<?php
/**
 * KantanPro更新チェッカーテスト
 * 
 * @package KantanPro
 * @since 1.0.4
 */

// WordPress環境の読み込み
require_once dirname(__FILE__) . '/../../../wp-load.php';

// 管理者権限チェック
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( 'この操作を実行する権限がありません。' );
}

// 更新チェッカークラスのテスト
if ( ! class_exists( 'KTPWP_Update_Checker' ) ) {
    echo '<div style="color: red;">エラー: KTPWP_Update_Checker クラスが見つかりません。</div>';
    exit;
}

echo '<h1>KantanPro更新チェッカー テスト</h1>';

// 現在のバージョン情報
echo '<h2>現在のバージョン情報</h2>';
echo '<p>定義されているバージョン: ' . KANTANPRO_PLUGIN_VERSION . '</p>';

// 更新チェッカーのインスタンス化
$update_checker = new KTPWP_Update_Checker();

// プライベートメソッドを使用するため、リフレクションを使用
$reflection = new ReflectionClass( $update_checker );
$clean_version_method = $reflection->getMethod( 'clean_version' );
$clean_version_method->setAccessible( true );

// バージョンクリーニングのテスト
echo '<h2>バージョンクリーニングテスト</h2>';
$test_versions = array(
    '1.0.4(preview)',
    'v1.0.5',
    '1.0.6(beta)',
    '1.0.7',
    'v1.0.8(release)',
);

foreach ( $test_versions as $version ) {
    $clean_version = $clean_version_method->invoke( $update_checker, $version );
    echo '<p>' . $version . ' → ' . $clean_version . '</p>';
}

// 保存されたオプションの確認
echo '<h2>保存されたオプション</h2>';
$last_check = get_option( 'ktpwp_last_update_check', 0 );
$update_available = get_option( 'ktpwp_update_available', false );
$last_frontend_check = get_option( 'ktpwp_last_frontend_check', 0 );

echo '<p>最後のチェック時刻: ' . ( $last_check ? date( 'Y-m-d H:i:s', $last_check ) : '未実行' ) . '</p>';
echo '<p>フロントエンド最後のチェック時刻: ' . ( $last_frontend_check ? date( 'Y-m-d H:i:s', $last_frontend_check ) : '未実行' ) . '</p>';
echo '<p>更新が利用可能: ' . ( $update_available ? 'はい' : 'いいえ' ) . '</p>';

if ( $update_available ) {
    echo '<h3>更新情報の詳細</h3>';
    echo '<pre>' . print_r( $update_available, true ) . '</pre>';
}

// 手動更新チェックのテスト
echo '<h2>手動更新チェックテスト</h2>';
echo '<p><a href="' . admin_url( 'admin-post.php?action=ktpwp_check_update&_wpnonce=' . wp_create_nonce( 'ktpwp_manual_update_check' ) ) . '">手動更新チェック実行</a></p>';

// GitHub API直接テスト
echo '<h2>GitHub API直接テスト</h2>';
$api_url = 'https://api.github.com/repos/KantanPro/freeKTP/releases/latest';
$response = wp_remote_get( $api_url, array(
    'timeout' => 30,
    'user-agent' => 'KantanPro/' . KANTANPRO_PLUGIN_VERSION . '; ' . get_bloginfo( 'url' )
) );

if ( is_wp_error( $response ) ) {
    echo '<p style="color: red;">GitHub API エラー: ' . $response->get_error_message() . '</p>';
} else {
    $response_code = wp_remote_retrieve_response_code( $response );
    echo '<p>HTTPステータス: ' . $response_code . '</p>';
    
    if ( $response_code === 200 ) {
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );
        
        if ( isset( $data['tag_name'] ) ) {
            echo '<p>最新リリース: ' . $data['tag_name'] . '</p>';
            echo '<p>リリース日: ' . $data['published_at'] . '</p>';
            echo '<p>リリース内容: ' . substr( $data['body'], 0, 200 ) . '...</p>';
        } else {
            echo '<p style="color: red;">tag_nameが見つかりません</p>';
        }
    } else {
        echo '<p style="color: red;">GitHub APIからのレスポンスエラー</p>';
    }
}

// クリーンアップ機能のテスト
echo '<h2>クリーンアップ機能テスト</h2>';
echo '<p><a href="?cleanup=1">テストデータクリーンアップ</a></p>';

if ( isset( $_GET['cleanup'] ) && $_GET['cleanup'] == '1' ) {
    delete_option( 'ktpwp_last_update_check' );
    delete_option( 'ktpwp_last_frontend_check' );
    delete_option( 'ktpwp_update_notice_dismissed' );
    delete_option( 'ktpwp_frontend_update_notice_dismissed' );
    delete_option( 'ktpwp_update_available' );
    wp_clear_scheduled_hook( 'ktpwp_daily_update_check' );
    
    echo '<p style="color: green;">テストデータがクリーンアップされました。</p>';
}

// スケジュールされたイベントの確認
echo '<h2>スケジュールされたイベント</h2>';
$scheduled = wp_next_scheduled( 'ktpwp_daily_update_check' );
if ( $scheduled ) {
    echo '<p>次回自動チェック: ' . date( 'Y-m-d H:i:s', $scheduled ) . '</p>';
} else {
    echo '<p>自動チェックはスケジュールされていません</p>';
}

// 現在の時刻
echo '<h2>現在の時刻</h2>';
echo '<p>現在時刻: ' . date( 'Y-m-d H:i:s' ) . '</p>';
echo '<p>タイムゾーン: ' . get_option( 'timezone_string' ) . '</p>';

?>
<style>
body {
    font-family: Arial, sans-serif;
    line-height: 1.6;
    margin: 20px;
}
h1, h2, h3 {
    color: #333;
}
pre {
    background: #f5f5f5;
    padding: 10px;
    border-radius: 4px;
    overflow-x: auto;
}
</style> 