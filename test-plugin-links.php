<?php
/**
 * プラグインリンクテスト
 * 
 * @package KantanPro
 */

// WordPress環境の読み込み
require_once dirname(__FILE__) . '/../../../wp-load.php';

// 管理者権限チェック
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( 'この操作を実行する権限がありません。' );
}

echo '<h1>プラグインリンクテスト</h1>';

// プラグインの基本情報
$plugin_basename = plugin_basename( KANTANPRO_PLUGIN_FILE );
$plugin_file = KANTANPRO_PLUGIN_FILE;

echo '<h2>プラグイン基本情報</h2>';
echo '<p>Plugin File: ' . esc_html( $plugin_file ) . '</p>';
echo '<p>Plugin Basename: ' . esc_html( $plugin_basename ) . '</p>';
echo '<p>Plugin Active: ' . ( is_plugin_active( $plugin_basename ) ? 'Yes' : 'No' ) . '</p>';

// プラグインデータの取得
$plugin_data = get_plugin_data( $plugin_file );
echo '<h2>プラグインデータ</h2>';
echo '<pre>' . print_r( $plugin_data, true ) . '</pre>';

// 更新チェッカーの状態確認
echo '<h2>更新チェッカー状態</h2>';
if ( class_exists( 'KTPWP_Update_Checker' ) ) {
    echo '<p>KTPWP_Update_Checker: 存在します</p>';
    
    // インスタンスの作成
    $update_checker = new KTPWP_Update_Checker();
    
    // テストリンクの生成
    $test_links = array(
        'edit' => '<a href="#">編集</a>',
        'settings' => '<a href="#">設定</a>',
    );
    
    echo '<h3>元のリンク</h3>';
    echo '<ul>';
    foreach ( $test_links as $key => $link ) {
        echo '<li>' . $key . ': ' . $link . '</li>';
    }
    echo '</ul>';
    
    // 更新チェックリンクを追加
    $modified_links = $update_checker->add_check_update_link( $test_links );
    
    echo '<h3>更新チェックリンク追加後</h3>';
    echo '<ul>';
    foreach ( $modified_links as $key => $link ) {
        echo '<li>' . $key . ': ' . $link . '</li>';
    }
    echo '</ul>';
    
} else {
    echo '<p>KTPWP_Update_Checker: 存在しません</p>';
}

// プラグインアクションリンクの実際の取得
echo '<h2>実際のプラグインアクションリンク</h2>';

// WordPress標準のプラグインアクションリンクを取得
$actions = array();

if ( is_plugin_active( $plugin_basename ) ) {
    $actions['deactivate'] = sprintf(
        '<a href="%s" id="deactivate-kantanpro" aria-label="KantanPro を無効化">無効化</a>',
        wp_nonce_url( 'plugins.php?action=deactivate&amp;plugin=' . urlencode( $plugin_basename ) . '&amp;plugin_status=all&amp;paged=1&amp;s=', 'deactivate-plugin_' . $plugin_basename )
    );
} else {
    $actions['activate'] = sprintf(
        '<a href="%s" id="activate-kantanpro" aria-label="KantanPro を有効化">有効化</a>',
        wp_nonce_url( 'plugins.php?action=activate&amp;plugin=' . urlencode( $plugin_basename ) . '&amp;plugin_status=all&amp;paged=1&amp;s=', 'activate-plugin_' . $plugin_basename )
    );
}

// フィルターを適用
$actions = apply_filters( 'plugin_action_links_' . $plugin_basename, $actions, $plugin_file, $plugin_data );

echo '<h3>フィルター適用後のアクション</h3>';
echo '<ul>';
foreach ( $actions as $key => $action ) {
    echo '<li>' . $key . ': ' . $action . '</li>';
}
echo '</ul>';

// フックの登録状況確認
echo '<h2>フック登録状況</h2>';
global $wp_filter;
$hook_name = 'plugin_action_links_' . $plugin_basename;

if ( isset( $wp_filter[ $hook_name ] ) ) {
    echo '<p>フック "' . $hook_name . '" が登録されています。</p>';
    echo '<h3>登録されているコールバック</h3>';
    echo '<pre>' . print_r( $wp_filter[ $hook_name ]->callbacks, true ) . '</pre>';
} else {
    echo '<p>フック "' . $hook_name . '" が登録されていません。</p>';
}

// プラグインリストページのHTML模擬
echo '<h2>プラグインリストページ模擬</h2>';
echo '<div style="border: 1px solid #ccc; padding: 10px; margin: 10px 0; background: #f9f9f9;">';
echo '<p><strong>' . $plugin_data['Name'] . '</strong> バージョン ' . $plugin_data['Version'] . ' | 作者: ' . $plugin_data['Author'] . '</p>';
echo '<p>';
$link_html = implode( ' | ', $actions );
echo $link_html;
echo '</p>';
echo '</div>';

// JavaScriptテスト
echo '<h2>JavaScriptテスト</h2>';
echo '<p>更新チェックボタンをクリックしてテストしてください。</p>';
echo '<button id="test-update-check">更新をチェック</button>';
echo '<div id="test-result"></div>';

// 必要なJavaScriptとCSSを読み込み
wp_enqueue_script( 'jquery' );
wp_enqueue_script( 'ktpwp-update-checker', plugin_dir_url( __FILE__ ) . 'js/ktpwp-update-checker.js', array( 'jquery' ), '1.0', true );
wp_localize_script( 'ktpwp-update-checker', 'ktpwp_update_checker', array(
    'ajax_url' => admin_url( 'admin-ajax.php' ),
    'nonce' => wp_create_nonce( 'ktpwp_update_checker' ),
    'checking_text' => '更新をチェック中...',
    'check_text' => '更新をチェック',
    'error_text' => 'エラーが発生しました',
) );
wp_print_scripts();

?>
<script>
jQuery(document).ready(function($) {
    $('#test-update-check').on('click', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var originalText = $button.text();
        
        $button.text('チェック中...').prop('disabled', true);
        
        $.ajax({
            url: ktpwp_update_checker.ajax_url,
            type: 'POST',
            data: {
                action: 'ktpwp_check_github_update',
                nonce: ktpwp_update_checker.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#test-result').html('<div style="color: green;">' + response.data.message + '</div>');
                } else {
                    $('#test-result').html('<div style="color: red;">エラー: ' + response.data + '</div>');
                }
            },
            error: function() {
                $('#test-result').html('<div style="color: red;">AJAX エラーが発生しました</div>');
            },
            complete: function() {
                $button.text(originalText).prop('disabled', false);
            }
        });
    });
});
</script>

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
button {
    padding: 10px 20px;
    background: #0073aa;
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
}
button:hover {
    background: #005a87;
}
button:disabled {
    background: #ccc;
    cursor: not-allowed;
}
#test-result {
    margin-top: 10px;
    padding: 10px;
    border-radius: 4px;
}
</style> 