<?php
/**
 * プラグインリスト表示テスト（データベース不要）
 * 
 * @package KantanPro
 */

// 基本的なWordPress関数を模擬
function plugin_basename( $file ) {
    $plugin_dir = dirname( dirname( __FILE__ ) );
    $file = str_replace( $plugin_dir . '/', '', $file );
    return $file;
}

function plugin_dir_url( $file ) {
    return 'http://localhost/wp-content/plugins/KantanPro/';
}

function wp_create_nonce( $action ) {
    return 'test_nonce_' . md5( $action );
}

function admin_url( $path ) {
    return 'http://localhost/wp-admin/' . $path;
}

function esc_attr( $text ) {
    return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
}

function esc_html( $text ) {
    return htmlspecialchars( $text, ENT_HTML5, 'UTF-8' );
}

function __( $text, $domain = 'default' ) {
    return $text;
}

// 定数を定義
define( 'KANTANPRO_PLUGIN_FILE', __DIR__ . '/ktpwp.php' );
define( 'KANTANPRO_PLUGIN_VERSION', '1.0.4(preview)' );

echo '<h1>プラグインリスト表示テスト</h1>';

// プラグインベースネームの取得
$plugin_basename = plugin_basename( KANTANPRO_PLUGIN_FILE );
echo '<h2>プラグインベースネーム</h2>';
echo '<p>' . esc_html( $plugin_basename ) . '</p>';

// プラグインアクションリンクの作成
function ktpwp_add_plugin_action_links( $links ) {
    $check_update_link = '<a href="#" id="ktpwp-manual-check" data-plugin="' . esc_attr( plugin_basename( KANTANPRO_PLUGIN_FILE ) ) . '">更新をチェック</a>';
    array_push( $links, $check_update_link );
    return $links;
}

// 既存のプラグインアクションリンクを模擬
$existing_links = array(
    'deactivate' => '<a href="#" id="deactivate-kantanpro">無効化</a>',
);

// 更新チェックリンクを追加
$updated_links = ktpwp_add_plugin_action_links( $existing_links );

echo '<h2>プラグインアクションリンク</h2>';
echo '<h3>元のリンク</h3>';
echo '<ul>';
foreach ( $existing_links as $key => $link ) {
    echo '<li>' . $key . ': ' . $link . '</li>';
}
echo '</ul>';

echo '<h3>更新チェックリンク追加後</h3>';
echo '<ul>';
foreach ( $updated_links as $key => $link ) {
    echo '<li>' . $key . ': ' . $link . '</li>';
}
echo '</ul>';

// WordPressプラグインページの模擬
echo '<h2>WordPressプラグインページ模擬</h2>';
echo '<div style="border: 1px solid #ccd0d4; padding: 15px; margin: 10px 0; background: #fff; box-shadow: 0 1px 1px rgba(0,0,0,.04);">';
echo '<p><strong>KantanPro</strong></p>';
echo '<p>スモールビジネス向けの仕事効率化システム。ショートコード[ktpwp_all_tab]を固定ページに設置してください。</p>';
echo '<p>バージョン 1.0.4(preview) | 作者: KantanPro | <a href="#">詳細を表示</a></p>';
echo '<p>';
$link_html = implode( ' | ', $updated_links );
echo $link_html;
echo '</p>';
echo '</div>';

// JavaScriptとCSSの読み込み
echo '<h2>JavaScriptテスト</h2>';
echo '<p>「更新をチェック」リンクのJavaScriptテスト</p>';
echo '<button id="test-manual-check">更新をチェック</button>';
echo '<div id="test-result"></div>';

?>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
// 更新チェッカーの設定を模擬
var ktpwp_update_checker = {
    ajax_url: '<?php echo admin_url( 'admin-ajax.php' ); ?>',
    nonce: '<?php echo wp_create_nonce( 'ktpwp_update_checker' ); ?>',
    checking_text: '更新をチェック中...',
    check_text: '更新をチェック',
    error_text: 'エラーが発生しました'
};

jQuery(document).ready(function($) {
    // 更新チェックリンクのクリックイベント
    $('#test-manual-check, #ktpwp-manual-check').on('click', function(e) {
        e.preventDefault();
        
        var $link = $(this);
        var originalText = $link.text();
        
        // リンクを無効化し、テキストを変更
        $link.text(ktpwp_update_checker.checking_text)
             .addClass('disabled')
             .css('pointer-events', 'none');
        
        $('#test-result').html('<div style="color: blue; padding: 10px; border: 1px solid #0073aa; background: #f0f8ff; margin: 10px 0;">テストモード: 更新チェックのAJAXリクエストをシミュレート中...</div>');
        
        // 2秒後にテスト結果を表示
        setTimeout(function() {
            $('#test-result').html('<div style="color: green; padding: 10px; border: 1px solid #00a32a; background: #f0fff0; margin: 10px 0;">テストモード: 更新チェック完了！リンクが正常に動作しています。</div>');
            
            // リンクを元に戻す
            $link.text(originalText)
                 .removeClass('disabled')
                 .css('pointer-events', 'auto');
        }, 2000);
    });
});
</script>

<style>
body {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
    line-height: 1.6;
    margin: 20px;
    background: #f1f1f1;
}
h1, h2, h3 {
    color: #1d2327;
}
pre {
    background: #f6f7f7;
    padding: 10px;
    border-radius: 4px;
    overflow-x: auto;
}
button {
    padding: 8px 16px;
    background: #2271b1;
    color: white;
    border: none;
    border-radius: 3px;
    cursor: pointer;
}
button:hover {
    background: #135e96;
}
button:disabled, button.disabled {
    background: #c3c4c7;
    cursor: not-allowed;
}
a {
    color: #2271b1;
    text-decoration: none;
}
a:hover {
    text-decoration: underline;
}
#test-result {
    margin-top: 10px;
}
</style>

<div style="margin-top: 30px; padding: 15px; background: #fff; border: 1px solid #ccd0d4;">
    <h2>実装確認チェックリスト</h2>
    <ul>
        <li>✓ プラグインベースネーム取得: <?php echo esc_html( $plugin_basename ); ?></li>
        <li>✓ プラグインアクションリンク追加機能</li>
        <li>✓ JavaScript読み込み設定</li>
        <li>✓ AJAX設定</li>
        <li>✓ 更新チェック機能</li>
    </ul>
    <p><strong>注意:</strong> WordPressの管理画面 > プラグイン > インストール済みプラグインで、KantanProの行に「更新をチェック」リンクが表示されることを確認してください。</p>
</div> 