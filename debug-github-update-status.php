<?php
/**
 * KantanPro GitHub更新状況デバッグツール
 * 
 * GitHubリリースとplugin-update-checkerの連携状況を確認し、
 * 更新通知が表示されない問題の原因を特定するためのツール
 */

// WordPress環境での実行を確認
if (!defined('ABSPATH')) {
    // 通常のWordPress環境をロード
    require_once '../../../wp-load.php';
}

// 権限チェック
if (!current_user_can('manage_options')) {
    wp_die('このツールを実行する権限がありません。');
}

echo '<h1>KantanPro GitHub更新状況デバッグ</h1>';
echo '<style>
body { font-family: Arial, sans-serif; margin: 20px; }
.debug-section { border: 1px solid #ddd; margin: 20px 0; padding: 15px; border-radius: 5px; }
.success { background-color: #d4edda; border-color: #c3e6cb; }
.warning { background-color: #fff3cd; border-color: #ffeaa7; }
.error { background-color: #f8d7da; border-color: #f5c6cb; }
.info { background-color: #d1ecf1; border-color: #bee5eb; }
pre { background: #f8f9fa; padding: 10px; border-radius: 3px; overflow-x: auto; }
</style>';

// 1. プラグイン基本情報
echo '<div class="debug-section info">';
echo '<h2>📋 プラグイン基本情報</h2>';
echo '<p><strong>プラグイン名:</strong> ' . (defined('KANTANPRO_PLUGIN_NAME') ? KANTANPRO_PLUGIN_NAME : 'undefined') . '</p>';
echo '<p><strong>現在のバージョン:</strong> ' . (defined('KANTANPRO_PLUGIN_VERSION') ? KANTANPRO_PLUGIN_VERSION : 'undefined') . '</p>';
echo '<p><strong>プラグインファイル:</strong> ' . (defined('KANTANPRO_PLUGIN_FILE') ? KANTANPRO_PLUGIN_FILE : 'undefined') . '</p>';
echo '<p><strong>プラグインディレクトリ:</strong> ' . (defined('KANTANPRO_PLUGIN_DIR') ? KANTANPRO_PLUGIN_DIR : 'undefined') . '</p>';
echo '</div>';

// 2. plugin-update-checker状況
echo '<div class="debug-section info">';
echo '<h2>🔍 Plugin Update Checker状況</h2>';

// plugin-update-checkerライブラリの存在確認
$puc_file = KANTANPRO_PLUGIN_DIR . '/vendor/plugin-update-checker/plugin-update-checker.php';
if (file_exists($puc_file)) {
    echo '<p>✅ plugin-update-checkerライブラリ: <span style="color:green;">存在</span></p>';
    
    // グローバル変数の確認
    if (isset($GLOBALS['kantanpro_update_checker'])) {
        echo '<p>✅ 更新チェッカーインスタンス: <span style="color:green;">初期化済み</span></p>';
        
        $checker = $GLOBALS['kantanpro_update_checker'];
        
        // 設定情報の表示
        echo '<h3>設定情報:</h3>';
        echo '<ul>';
        echo '<li><strong>Repository URL:</strong> ' . $checker->getMetadata()->getRepositoryUrl() . '</li>';
        echo '<li><strong>Branch:</strong> ' . $checker->getBranch() . '</li>';
        echo '<li><strong>Plugin Slug:</strong> ' . $checker->getSlug() . '</li>';
        echo '<li><strong>Plugin File:</strong> ' . $checker->getPluginFile() . '</li>';
        echo '</ul>';
        
    } else {
        echo '<p>❌ 更新チェッカーインスタンス: <span style="color:red;">未初期化</span></p>';
    }
} else {
    echo '<p>❌ plugin-update-checkerライブラリ: <span style="color:red;">見つからない</span></p>';
    echo '<p>ファイルパス: ' . $puc_file . '</p>';
}
echo '</div>';

// 3. GitHub API確認
echo '<div class="debug-section info">';
echo '<h2>🐙 GitHub API確認</h2>';

$github_repo_url = 'https://github.com/KantanPro/freeKTP';
$api_url = 'https://api.github.com/repos/KantanPro/freeKTP/releases/latest';

echo '<p><strong>Repository URL:</strong> ' . $github_repo_url . '</p>';
echo '<p><strong>API URL:</strong> ' . $api_url . '</p>';

// GitHub APIからリリース情報を取得
$response = wp_remote_get($api_url, array(
    'timeout' => 30,
    'headers' => array(
        'User-Agent' => 'KantanPro-Update-Checker'
    )
));

if (is_wp_error($response)) {
    echo '<p>❌ GitHub API接続エラー: <span style="color:red;">' . $response->get_error_message() . '</span></p>';
} else {
    $response_code = wp_remote_retrieve_response_code($response);
    echo '<p><strong>Response Code:</strong> ' . $response_code . '</p>';
    
    if ($response_code === 200) {
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if ($data) {
            echo '<h3>✅ 最新リリース情報:</h3>';
            echo '<ul>';
            echo '<li><strong>Tag Name:</strong> ' . ($data['tag_name'] ?? 'N/A') . '</li>';
            echo '<li><strong>Name:</strong> ' . ($data['name'] ?? 'N/A') . '</li>';
            echo '<li><strong>Published:</strong> ' . ($data['published_at'] ?? 'N/A') . '</li>';
            echo '<li><strong>Draft:</strong> ' . ($data['draft'] ? 'Yes' : 'No') . '</li>';
            echo '<li><strong>Prerelease:</strong> ' . ($data['prerelease'] ? 'Yes' : 'No') . '</li>';
            echo '<li><strong>Assets Count:</strong> ' . (isset($data['assets']) ? count($data['assets']) : '0') . '</li>';
            echo '</ul>';
            
            // Assets情報
            if (isset($data['assets']) && !empty($data['assets'])) {
                echo '<h3>📦 Assets情報:</h3>';
                echo '<ul>';
                foreach ($data['assets'] as $asset) {
                    echo '<li>';
                    echo '<strong>Name:</strong> ' . $asset['name'] . ' ';
                    echo '<strong>Size:</strong> ' . round($asset['size'] / 1024) . 'KB ';
                    echo '<strong>Download URL:</strong> <a href="' . $asset['browser_download_url'] . '" target="_blank">ダウンロード</a>';
                    echo '</li>';
                }
                echo '</ul>';
            } else {
                echo '<p>⚠️ <span style="color:orange;">Assets が見つかりません（ZIPファイルが添付されていない可能性があります）</span></p>';
            }
            
            // バージョン比較
            $latest_version = $data['tag_name'] ?? '';
            $current_version = defined('KANTANPRO_PLUGIN_VERSION') ? KANTANPRO_PLUGIN_VERSION : '';
            
            echo '<h3>🔄 バージョン比較:</h3>';
            echo '<p><strong>現在のバージョン:</strong> ' . $current_version . '</p>';
            echo '<p><strong>最新のバージョン:</strong> ' . $latest_version . '</p>';
            
            if ($latest_version && $current_version) {
                // バージョン比較
                $comparison = version_compare($current_version, $latest_version);
                if ($comparison < 0) {
                    echo '<p>🆙 <span style="color:blue;">更新が利用可能です</span></p>';
                } elseif ($comparison > 0) {
                    echo '<p>⬆️ <span style="color:green;">現在のバージョンが最新より新しいです</span></p>';
                } else {
                    echo '<p>✅ <span style="color:green;">最新バージョンを使用中です</span></p>';
                }
            }
            
        } else {
            echo '<p>❌ <span style="color:red;">レスポンス解析エラー</span></p>';
        }
    } else {
        echo '<p>❌ <span style="color:red;">GitHub API エラー (Code: ' . $response_code . ')</span></p>';
    }
}
echo '</div>';

// 4. WordPressプラグインキャッシュ確認
echo '<div class="debug-section info">';
echo '<h2>💾 WordPressプラグインキャッシュ確認</h2>';

// 更新キャッシュ確認
$update_plugins = get_site_transient('update_plugins');
if ($update_plugins) {
    echo '<p>✅ update_plugins transient: <span style="color:green;">存在</span></p>';
    
    $plugin_basename = plugin_basename(KANTANPRO_PLUGIN_FILE);
    echo '<p><strong>Plugin Basename:</strong> ' . $plugin_basename . '</p>';
    
    if (isset($update_plugins->response[$plugin_basename])) {
        echo '<p>🆙 <span style="color:blue;">更新情報がキャッシュされています</span></p>';
        $update_info = $update_plugins->response[$plugin_basename];
        echo '<pre>' . print_r($update_info, true) . '</pre>';
    } else {
        echo '<p>❌ <span style="color:orange;">更新情報がキャッシュされていません</span></p>';
    }
    
    // 強制更新チェック
    echo '<h3>🔄 強制更新チェック</h3>';
    if (isset($GLOBALS['kantanpro_update_checker'])) {
        echo '<p>更新チェックを実行中...</p>';
        
        $checker = $GLOBALS['kantanpro_update_checker'];
        $update = $checker->checkForUpdates();
        
        if ($update) {
            echo '<p>✅ <span style="color:green;">更新が見つかりました</span></p>';
            echo '<ul>';
            echo '<li><strong>Version:</strong> ' . $update->version . '</li>';
            echo '<li><strong>Download URL:</strong> ' . $update->download_url . '</li>';
            echo '<li><strong>Details URL:</strong> ' . $update->details_url . '</li>';
            echo '</ul>';
        } else {
            echo '<p>❌ <span style="color:orange;">更新が見つかりませんでした</span></p>';
        }
    } else {
        echo '<p>❌ <span style="color:red;">更新チェッカーが利用できません</span></p>';
    }
    
} else {
    echo '<p>❌ update_plugins transient: <span style="color:red;">存在しない</span></p>';
}
echo '</div>';

// 5. 推奨解決策
echo '<div class="debug-section warning">';
echo '<h2>💡 推奨解決策</h2>';
echo '<ol>';
echo '<li><strong>GitHubリリースにZIPファイルを添付:</strong> リリース作成時にプラグインのZIPファイルを添付してください</li>';
echo '<li><strong>タグ名をプラグインバージョンと一致:</strong> GitHubのタグ名とプラグインヘッダーのVersionを一致させてください</li>';
echo '<li><strong>キャッシュクリア:</strong> <code>delete_site_transient(\'update_plugins\');</code> を実行してください</li>';
echo '<li><strong>手動更新チェック:</strong> プラグインページの「今すぐ更新を確認」をクリックしてください</li>';
echo '<li><strong>ログ確認:</strong> <code>WP_DEBUG</code>を有効にしてエラーログを確認してください</li>';
echo '</ol>';
echo '</div>';

// 6. アクションボタン
echo '<div class="debug-section info">';
echo '<h2>🔧 アクション</h2>';
echo '<p>';
echo '<a href="?action=clear_cache" class="button" style="background:#0073aa;color:white;padding:10px 15px;text-decoration:none;border-radius:3px;margin-right:10px;">キャッシュクリア</a>';
echo '<a href="?action=force_check" class="button" style="background:#00a32a;color:white;padding:10px 15px;text-decoration:none;border-radius:3px;margin-right:10px;">強制更新チェック</a>';
echo '<a href="' . admin_url('plugins.php') . '" class="button" style="background:#646970;color:white;padding:10px 15px;text-decoration:none;border-radius:3px;">プラグインページ</a>';
echo '</p>';
echo '</div>';

// アクション処理
if (isset($_GET['action'])) {
    echo '<div class="debug-section success">';
    echo '<h2>🎯 アクション結果</h2>';
    
    switch ($_GET['action']) {
        case 'clear_cache':
            delete_site_transient('update_plugins');
            if (function_exists('wp_clean_update_cache')) {
                wp_clean_update_cache();
            }
            echo '<p>✅ プラグイン更新キャッシュをクリアしました</p>';
            break;
            
        case 'force_check':
            if (isset($GLOBALS['kantanpro_update_checker'])) {
                $checker = $GLOBALS['kantanpro_update_checker'];
                $update = $checker->checkForUpdates();
                if ($update) {
                    echo '<p>✅ 更新が見つかりました: ' . $update->version . '</p>';
                } else {
                    echo '<p>❌ 更新は見つかりませんでした</p>';
                }
            } else {
                echo '<p>❌ 更新チェッカーが利用できません</p>';
            }
            break;
    }
    echo '</div>';
}

echo '<hr>';
echo '<p><small>実行時刻: ' . current_time('mysql') . '</small></p>';
?> 