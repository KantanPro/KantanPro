<?php
/**
 * 更新チェッカーデバッグ用ファイル
 * 
 * @package KantanPro
 */

// WordPress環境の読み込み
require_once dirname(__FILE__) . '/../../../wp-load.php';

// 管理者権限チェック
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( 'この操作を実行する権限がありません。' );
}

echo '<h1>更新チェッカーデバッグ</h1>';

// プラグインベースネームの確認
$plugin_basename = plugin_basename( KANTANPRO_PLUGIN_FILE );
echo '<h2>プラグインベースネーム</h2>';
echo '<p>' . esc_html( $plugin_basename ) . '</p>';

// フックの確認
$hook_name = 'plugin_action_links_' . $plugin_basename;
echo '<h2>フック名</h2>';
echo '<p>' . esc_html( $hook_name ) . '</p>';

// クラスの存在確認
echo '<h2>クラス存在チェック</h2>';
echo '<p>KTPWP_Update_Checker: ' . ( class_exists( 'KTPWP_Update_Checker' ) ? '存在' : '存在しない' ) . '</p>';

// フックの登録確認
global $wp_filter;
echo '<h2>フック登録状況</h2>';
if ( isset( $wp_filter[ $hook_name ] ) ) {
    echo '<p>フックが登録されています:</p>';
    echo '<pre>' . print_r( $wp_filter[ $hook_name ]->callbacks, true ) . '</pre>';
} else {
    echo '<p>フックが登録されていません。</p>';
}

// 更新チェッカーインスタンスの作成テスト
if ( class_exists( 'KTPWP_Update_Checker' ) ) {
    echo '<h2>更新チェッカーインスタンス作成テスト</h2>';
    try {
        $update_checker = new KTPWP_Update_Checker();
        echo '<p>更新チェッカーインスタンスが正常に作成されました。</p>';
        
        // プラグインリンクのテスト
        echo '<h2>プラグインリンクテスト</h2>';
        $test_links = array( 'テストリンク' );
        $result_links = $update_checker->add_check_update_link( $test_links );
        echo '<p>元のリンク: ' . implode( ', ', $test_links ) . '</p>';
        echo '<p>結果リンク: ' . implode( ', ', $result_links ) . '</p>';
        
    } catch ( Exception $e ) {
        echo '<p>エラー: ' . $e->getMessage() . '</p>';
    }
} else {
    echo '<p>KTPWP_Update_Checker クラスが見つかりません。</p>';
}

// 現在のプラグインの状態確認
echo '<h2>現在のプラグイン状態</h2>';
$all_plugins = get_plugins();
if ( isset( $all_plugins[ $plugin_basename ] ) ) {
    echo '<p>プラグインが認識されています:</p>';
    echo '<pre>' . print_r( $all_plugins[ $plugin_basename ], true ) . '</pre>';
} else {
    echo '<p>プラグインが認識されていません。</p>';
}

// プラグインアクションリンクの確認
echo '<h2>プラグインアクションリンク</h2>';
$plugin_data = get_plugin_data( KANTANPRO_PLUGIN_FILE );
$actions = array();

// 通常のプラグインアクションリンクを取得
if ( is_plugin_active( $plugin_basename ) ) {
    $actions['deactivate'] = sprintf(
        '<a href="%s" aria-label="%s">%s</a>',
        wp_nonce_url( 'plugins.php?action=deactivate&amp;plugin=' . urlencode( $plugin_basename ), 'deactivate-plugin_' . $plugin_basename ),
        esc_attr( sprintf( __( '%s を無効化' ), $plugin_data['Name'] ) ),
        __( '無効化' )
    );
}

// カスタムフィルターを適用
$actions = apply_filters( 'plugin_action_links_' . $plugin_basename, $actions );

echo '<p>プラグインアクションリンク:</p>';
echo '<pre>' . print_r( $actions, true ) . '</pre>';

// WordPress環境の確認
echo '<h2>WordPress環境</h2>';
echo '<p>WordPress バージョン: ' . get_bloginfo( 'version' ) . '</p>';
echo '<p>管理画面URL: ' . admin_url() . '</p>';
echo '<p>現在のユーザー: ' . wp_get_current_user()->display_name . '</p>';
echo '<p>現在のユーザー権限: ' . ( current_user_can( 'update_plugins' ) ? '有り' : '無し' ) . '</p>';

// アクションフックの確認
echo '<h2>アクションフック確認</h2>';
$actions_to_check = array(
    'init',
    'admin_init',
    'wp_footer',
    'admin_notices',
    'wp_ajax_ktpwp_dismiss_update_notice',
    'wp_ajax_ktpwp_check_github_update',
    'admin_post_ktpwp_check_update',
    'admin_enqueue_scripts'
);

foreach ( $actions_to_check as $action ) {
    if ( has_action( $action ) ) {
        echo '<p>' . $action . ': 登録済み</p>';
    } else {
        echo '<p>' . $action . ': 未登録</p>';
    }
}

// 定数の確認
echo '<h2>定数確認</h2>';
echo '<p>KANTANPRO_PLUGIN_FILE: ' . ( defined( 'KANTANPRO_PLUGIN_FILE' ) ? KANTANPRO_PLUGIN_FILE : '未定義' ) . '</p>';
echo '<p>KANTANPRO_PLUGIN_VERSION: ' . ( defined( 'KANTANPRO_PLUGIN_VERSION' ) ? KANTANPRO_PLUGIN_VERSION : '未定義' ) . '</p>';
echo '<p>KANTANPRO_PLUGIN_URL: ' . ( defined( 'KANTANPRO_PLUGIN_URL' ) ? KANTANPRO_PLUGIN_URL : '未定義' ) . '</p>';

?>
<style>
body {
    font-family: Arial, sans-serif;
    line-height: 1.6;
    margin: 20px;
}
h1, h2 {
    color: #333;
}
pre {
    background: #f5f5f5;
    padding: 10px;
    border-radius: 4px;
    overflow-x: auto;
}
</style> 